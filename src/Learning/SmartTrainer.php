<?php
/**
 * Smart trainer v1 (R0-08 §5.2, ADR-005; R0-05 PAR-06).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learning;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * `smarttrainer.v1`: parameters are fixed (a change is a new version that
 * only affects new answers). Stages: `new` (no row), `unsure`, `learning`,
 * `secure`, `mastered`. The documented quirks G-1…G-5 are kept on purpose
 * (parity, not "repaired").
 */
final class SmartTrainer {

	public const ALGORITHM_VERSION     = 1;
	public const MODES                 = array( 'smart', 'new', 'wrong', 'favorites' );
	public const RATE_LIMIT_PER_MINUTE = 60;
	public const STREAK_GAP_SECONDS    = 300;
	public const RECENT_EXCLUDE        = 3;
	public const SECURE_STREAK         = 3;
	public const CONSOLIDATE_GAP       = 72000;
	public const REVIEW_INTERVAL_DAYS  = array( 7, 21, 60 );

	/**
	 * Injected clock (tests); null = real time.
	 *
	 * @var int|null
	 */
	private static ?int $clock = null;

	/**
	 * Constructor.
	 *
	 * @param Database          $db        Database access.
	 * @param PolicyService     $policy    Policy.
	 * @param QuestionPresenter $presenter Presenter.
	 * @param Favorites         $favorites Favorites.
	 */
	public function __construct( private Database $db, private PolicyService $policy, private QuestionPresenter $presenter, private Favorites $favorites ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), PolicyService::instance(), QuestionPresenter::current(), Favorites::current() );
	}

	/**
	 * Set the clock (tests only).
	 *
	 * @param int|null $now Unix time or null.
	 * @return void
	 */
	public static function set_now( ?int $now ): void {
		self::$clock = $now;
	}

	/**
	 * Current time.
	 *
	 * @return int
	 */
	public static function now(): int {
		return self::$clock ?? time();
	}

	/**
	 * UTC SQL timestamp.
	 *
	 * @param int $time Unix time.
	 * @return string
	 */
	public static function sql( int $time ): string {
		return gmdate( 'Y-m-d H:i:s', $time );
	}

	/**
	 * R0-21 R-2: create the progress row before the business transaction
	 * (autocommit, content-neutral, idempotent), so parallel first answers
	 * never upgrade S→X locks inside one transaction.
	 *
	 * @param Database $db       Database.
	 * @param int      $user     Learner.
	 * @param int      $question Question.
	 * @param string   $status   Initial status (`new`, flashcards `open`).
	 * @return void
	 */
	public static function ensure_row( Database $db, int $user, int $question, string $status = 'new' ): void {
		$table = $db->table( 'ccap_question_progress' );
		$db->execute( $db->prepare( "INSERT IGNORE INTO `{$table}` (user_id, question_id, algorithm_version, learn_status) VALUES (%d, %d, %d, %s)", $user, $question, self::ALGORITHM_VERSION, $status ) );
	}

	/**
	 * Progress update for one learning answer (`applyProgress`); call inside
	 * the transaction that stores the answer.
	 *
	 * @param Database $db       Database in transaction.
	 * @param int      $user     Learner.
	 * @param int      $question Question.
	 * @param bool     $correct  Correct.
	 * @return array{previous: string, streak_counted: bool|null, status: string, streak: int, attempts: int, due_at: string|null, mastery_score: string}
	 */
	public static function apply( Database $db, int $user, int $question, bool $correct ): array {
		$now   = self::now();
		$table = $db->table( 'ccap_question_progress' );
		$row   = $db->row( $db->prepare( "SELECT * FROM `{$table}` WHERE user_id = %d AND question_id = %d FOR UPDATE", $user, $question ) );
		if ( null === $row ) {
			// Fallback for callers without ensure_row() (R0-21 R-2 expects the row to exist).
			self::ensure_row( $db, $user, $question );
			$row = $db->row( $db->prepare( "SELECT * FROM `{$table}` WHERE user_id = %d AND question_id = %d FOR UPDATE", $user, $question ) );
		}
		$row      = (array) $row;
		$attempts = (int) $row['attempts'] + 1;
		$right    = (int) $row['correct_count'] + ( $correct ? 1 : 0 );
		$wrong    = (int) $row['wrong_count'] + ( $correct ? 0 : 1 );
		$streak   = (int) $row['streak'];
		$status   = (string) $row['learn_status'];
		$previous = $status;
		$due      = null === $row['due_at'] ? null : (string) $row['due_at'];
		if ( ! $correct ) {
			$counted = null;
			$streak  = 0;
			$status  = 'unsure';
			$due     = null;
		} elseif ( in_array( $status, array( 'secure', 'mastered' ), true ) ) {
			$counted = null !== $due && $due <= self::sql( $now );
			if ( $counted ) {
				++$streak;
				$status = 'mastered';
				$due    = self::sql( $now + 86400 * self::review_interval( $streak ) );
			}
		} else {
			$counted = self::spaced( $db, $user, $question, $row, $now );
			$streak += $counted ? 1 : 0;
			$status  = $streak >= self::SECURE_STREAK ? 'secure' : 'learning';
			$due     = 'secure' === $status ? self::sql( $now + self::CONSOLIDATE_GAP ) : null;
		}
		$score   = sprintf( '%.4f', round( min( 1, 0.4 * $right / $attempts + 0.6 * min( $streak, self::SECURE_STREAK ) / self::SECURE_STREAK ), 4 ) );
		$due_sql = null === $due ? 'NULL' : $db->prepare( '%s', $due );
		$db->execute(
			$db->prepare(
				"UPDATE `{$table}` SET attempts = %d, correct_count = %d, wrong_count = %d, streak = %d, learn_status = %s, due_at = {$due_sql}, mastery_score = %s, algorithm_version = %d, first_answered_at = COALESCE(first_answered_at, %s), last_answered_at = %s, row_version = row_version + 1 WHERE id = %d",
				$attempts,
				$right,
				$wrong,
				$streak,
				$status,
				$score,
				self::ALGORITHM_VERSION,
				self::sql( $now ),
				self::sql( $now ),
				(int) $row['id']
			)
		);
		return array(
			'previous'       => $previous,
			'streak_counted' => $counted,
			'status'         => $status,
			'streak'         => $streak,
			'attempts'       => $attempts,
			'due_at'         => $due,
			'mastery_score'  => $score,
		);
	}

	/**
	 * Review interval in days (`reviewInterval`): streak 4 → 7, 5 → 21, ≥ 6 → 60.
	 *
	 * @param int $streak Streak after the answer.
	 * @return int
	 */
	public static function review_interval( int $streak ): int {
		$index = max( 0, min( 2, $streak - self::SECURE_STREAK - 1 ) );
		return self::REVIEW_INTERVAL_DAYS[ $index ];
	}

	/**
	 * Spacing (`spaced`): first answer, ≥ 300 s since the last answer, or any
	 * answer to another question since then (all courses, G-2).
	 *
	 * @param Database             $db       Database.
	 * @param int                  $user     Learner.
	 * @param int                  $question Question.
	 * @param array<string, mixed> $row      Progress row before the update.
	 * @param int                  $now      Now.
	 * @return bool
	 */
	private static function spaced( Database $db, int $user, int $question, array $row, int $now ): bool {
		if ( 0 === (int) $row['attempts'] || null === $row['last_answered_at'] ) {
			return true;
		}
		$last = (string) $row['last_answered_at'];
		if ( $now - (int) strtotime( $last . ' UTC' ) >= self::STREAK_GAP_SECONDS ) {
			return true;
		}
		return null !== $db->scalar( $db->prepare( 'SELECT 1 FROM `' . $db->table( 'ccap_attempt' ) . "` WHERE user_id = %d AND question_id <> %d AND created_at >= %s AND context <> 'flashcard' LIMIT 1", $user, $question, $last ) );
	}

	/**
	 * Atomic answer rate limit (60 per user and minute, table counter).
	 *
	 * @param int $user Learner.
	 * @return void
	 * @throws CourseException `ccap_rate_limited` (429, bucket `answer`).
	 */
	public function rate_limit( int $user ): void {
		\CourseCaptain\Storage\RateLimiter::hit( $this->db, 'answer', $user, 60, self::RATE_LIMIT_PER_MINUTE, self::now() );
	}


	/**
	 * Next question (`next`): candidates, mode filter, score, spacing rule,
	 * deterministic winner; delivered with a signed token.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param string               $mode      smart|new|wrong|favorites.
	 * @param array<string, mixed> $options   `topic`, `only_ids` (list<int>), `collection`.
	 * @return array{question_id: int, module_id: int, reason: string, score: float, progress: array<string, mixed>, question: array<string, mixed>, token: string}|null
	 * @throws CourseException For unknown modes or missing access.
	 */
	public function next( Actor $actor, int $course_id, string $mode = 'smart', array $options = array() ): ?array {
		if ( ! in_array( $mode, self::MODES, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown mode.', 422 );
		}
		$candidates = $this->candidates( $actor, $course_id, isset( $options['topic'] ) ? (string) $options['topic'] : null );
		if ( array() === $candidates && ! $this->any_module_accessible( $actor, $course_id ) ) {
			CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::LEARN, array( $course_id ) ), 'No learning access.' );
		}
		if ( isset( $options['only_ids'] ) ) {
			$only       = array_map( 'intval', (array) $options['only_ids'] );
			$candidates = array_filter( $candidates, static fn( array $c ): bool => in_array( $c['question_id'], $only, true ) );
		}
		if ( 'favorites' === $mode ) {
			$ids        = $this->favorites->question_ids( $actor, $course_id, isset( $options['collection'] ) ? (int) $options['collection'] : null );
			$candidates = array_filter( $candidates, static fn( array $c ): bool => in_array( $c['question_id'], $ids, true ) );
		}
		$progress = $this->progress_rows( $actor->user_id, array_column( $candidates, 'question_id' ) );
		if ( 'new' === $mode ) {
			$candidates = array_filter( $candidates, static fn( array $c ): bool => ! isset( $progress[ $c['question_id'] ] ) || 0 === (int) $progress[ $c['question_id'] ]['attempts'] );
		} elseif ( 'wrong' === $mode ) {
			$candidates = array_filter( $candidates, static fn( array $c ): bool => isset( $progress[ $c['question_id'] ] ) && (int) $progress[ $c['question_id'] ]['attempts'] > 0 && 0 === (int) $progress[ $c['question_id'] ]['streak'] );
		}
		$candidates = array_values( $candidates );
		if ( array() === $candidates ) {
			return null;
		}
		$weak   = $this->weak_topics( $candidates, $progress );
		$recent = array_slice( $this->recent( $actor->user_id ), 0, self::RECENT_EXCLUDE );
		$best   = null;
		$backup = null;
		foreach ( $candidates as $candidate ) {
			$scored = self::score( $candidate, $progress[ $candidate['question_id'] ] ?? null, $weak );
			if ( in_array( $candidate['question_id'], $recent, true ) ) {
				if ( null === $backup || $scored['score'] > $backup['score'] ) {
					$backup = $scored + $candidate;
				}
				continue;
			}
			if ( null === $best || $scored['score'] > $best['score'] ) {
				$best = $scored + $candidate;
			}
		}
		$winner = $best ?? $backup;
		if ( null === $winner ) {
			return null;
		}
		$row      = $progress[ $winner['question_id'] ] ?? null;
		$delivery = $this->presenter->deliver( $actor, $course_id, $winner['module_id'], $winner['question_id'] );
		return array(
			'question_id' => $winner['question_id'],
			'module_id'   => $winner['module_id'],
			'reason'      => $winner['reason'],
			'score'       => $winner['score'],
			'progress'    => array(
				'status'   => null === $row ? 'new' : (string) $row['learn_status'],
				'streak'   => null === $row ? 0 : (int) $row['streak'],
				'attempts' => null === $row ? 0 : (int) $row['attempts'],
				'due_at'   => null === $row ? null : $row['due_at'],
			),
			'question'    => $delivery['question'],
			'token'       => $delivery['token'],
		);
	}

	/**
	 * Targeted practice of one question (GM-074, web path of the source's
	 * app route): the question is delivered with the normal learning token,
	 * so the answer updates the same learning state (`ccap_question_progress`).
	 *
	 * Order of checks: parameters (422), course membership (404), learning
	 * access, then the gate of the question now: partial access → 403
	 * `ccap_content_locked`/`trial`, drip → 403 `ccap_content_locked`/`drip`.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param array<string, mixed> $params    `question` (required, ID), `module` (optional, ID).
	 * @return array{question_id: int, module_id: int, reason: string, progress: array<string, mixed>, question: array<string, mixed>, token: string}
	 * @throws CourseException 422 for missing or inconsistent parameters, 404 outside the course, 403 when locked.
	 */
	public function practice( Actor $actor, int $course_id, array $params ): array {
		$question = self::positive_id( $params['question'] ?? null );
		$module   = array_key_exists( 'module', $params ) && null !== $params['module'] && '' !== $params['module'] ? self::positive_id( $params['module'] ) : 0;
		if ( null === $question || null === $module ) {
			throw new CourseException( 'ccap_invalid_param', 'question', 422 );
		}
		$cm      = $this->db->table( 'ccap_course_module' );
		$pool    = $this->db->table( 'ccap_module_question' );
		$q       = $this->db->table( 'ccap_question' );
		$rev     = $this->db->table( 'ccap_question_revision' );
		$modules = array_map(
			'intval',
			$this->db->wpdb()->get_col(
				$this->db->prepare(
					"SELECT p.module_id FROM `{$pool}` p JOIN `{$cm}` cm ON cm.module_id = p.module_id AND cm.course_id = %d JOIN `{$q}` q ON q.id = p.question_id JOIN `{$rev}` r ON r.id = q.current_revision_id WHERE p.question_id = %d AND q.status = 'published' AND r.question_type <> %s ORDER BY cm.sort_order, cm.module_id",
					$course_id,
					$question,
					QuestionTypes::FLASHCARD
				)
			)
		);
		if ( array() === $modules ) {
			throw new CourseException( 'ccap_not_found', 'Question not in this course.', 404 );
		}
		if ( $module > 0 && ! in_array( $module, $modules, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'module', 422 );
		}
		// Without `module` the first module of the course that unlocks the question wins.
		$gate      = ContentGate::current();
		$decision  = null;
		$candidate = $module > 0 ? array( $module ) : $modules;
		foreach ( $candidate as $id ) {
			$check = $gate->question( $actor, $course_id, $id, $question );
			if ( null === $decision || $check->allowed() ) {
				$decision = $check;
				$module   = $id;
			}
			if ( $check->allowed() ) {
				break;
			}
		}
		$decision->assert();
		$rows     = $this->progress_rows( $actor->user_id, array( $question ) );
		$row      = $rows[ $question ] ?? null;
		$delivery = $this->presenter->deliver( $actor, $course_id, $module, $question );
		return array(
			'question_id' => $question,
			'module_id'   => $module,
			'reason'      => 'targeted',
			'progress'    => array(
				'status'   => null === $row ? 'new' : (string) $row['learn_status'],
				'streak'   => null === $row ? 0 : (int) $row['streak'],
				'attempts' => null === $row ? 0 : (int) $row['attempts'],
				'due_at'   => null === $row ? null : $row['due_at'],
			),
			'question'    => $delivery['question'],
			'token'       => $delivery['token'],
		);
	}

	/**
	 * Positive integer ID from a request value, null when invalid.
	 *
	 * @param mixed $value Value.
	 * @return int|null
	 */
	private static function positive_id( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value > 0 ? $value : null;
		}
		if ( is_string( $value ) && 1 === preg_match( '/^[1-9][0-9]{0,18}$/', $value ) ) {
			return (int) $value;
		}
		return null;
	}

	/**
	 * Stage distribution (`summaryFrom`); without answered questions the
	 * percentages are null ("no data yet", never 0 %).
	 *
	 * @param Actor       $actor     Learner.
	 * @param int         $course_id Course.
	 * @param string|null $topic     Topic filter.
	 * @return array{total: int, new: int, unsure: int, learning: int, secure: int, mastered: int, due: int, completed: int, percent: int|null, secure_percent: int|null, topics?: array<string, array<string, int>>}
	 */
	public function summary( Actor $actor, int $course_id, ?string $topic = null ): array {
		$candidates = $this->candidates( $actor, $course_id, $topic );
		$progress   = $this->progress_rows( $actor->user_id, array_column( $candidates, 'question_id' ) );
		$out        = array(
			'total'    => count( $candidates ),
			'new'      => 0,
			'unsure'   => 0,
			'learning' => 0,
			'secure'   => 0,
			'mastered' => 0,
			'due'      => 0,
		);
		$topics     = array();
		$answered   = 0;
		$now        = self::sql( self::now() );
		foreach ( $candidates as $candidate ) {
			$row   = $progress[ $candidate['question_id'] ] ?? null;
			$stage = null === $row || 0 === (int) $row['attempts'] ? 'new' : (string) $row['learn_status'];
			if ( ! isset( $out[ $stage ] ) || 'total' === $stage || 'due' === $stage ) {
				$stage = 'new';
			}
			++$out[ $stage ];
			$answered += 'new' === $stage ? 0 : 1;
			if ( null !== $row && in_array( $stage, array( 'secure', 'mastered' ), true ) && null !== $row['due_at'] && (string) $row['due_at'] <= $now ) {
				++$out['due'];
			}
			$key                      = '' === $candidate['topic'] ? '-' : $candidate['topic'];
			$topics[ $key ][ $stage ] = ( $topics[ $key ][ $stage ] ?? 0 ) + 1;
		}
		// GM-075: `completed` = consolidated questions (the stage the percentage counts).
		$out['completed']      = $out['mastered'];
		$out['percent']        = 0 === $answered || 0 === $out['total'] ? null : (int) floor( $out['mastered'] * 100 / $out['total'] );
		$out['secure_percent'] = 0 === $answered || 0 === $out['total'] ? null : (int) floor( $out['secure'] * 100 / $out['total'] );
		if ( count( $topics ) >= 2 ) {
			ksort( $topics );
			$out['topics'] = $topics;
		}
		return $out;
	}

	/**
	 * Due reviews (mode "due" of the learning area, R0-11 L-08): consolidated
	 * questions (`secure`/`mastered`) whose `due_at` has passed, same rule as
	 * `due` in {@see self::summary()}; plus the next future due date.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return array{ids: list<int>, next_due: string|null}
	 */
	public function due( Actor $actor, int $course_id ): array {
		$candidates = $this->candidates( $actor, $course_id, null );
		$progress   = $this->progress_rows( $actor->user_id, array_column( $candidates, 'question_id' ) );
		$now        = self::sql( self::now() );
		$ids        = array();
		$next       = null;
		foreach ( $candidates as $candidate ) {
			$row = $progress[ $candidate['question_id'] ] ?? null;
			if ( null === $row || 0 === (int) $row['attempts'] || null === $row['due_at'] || ! in_array( (string) $row['learn_status'], array( 'secure', 'mastered' ), true ) ) {
				continue;
			}
			if ( (string) $row['due_at'] <= $now ) {
				$ids[] = $candidate['question_id'];
			} elseif ( null === $next || (string) $row['due_at'] < $next ) {
				$next = (string) $row['due_at'];
			}
		}
		return array(
			'ids'      => $ids,
			'next_due' => $next,
		);
	}

	/**
	 * Topics of a module with their question counts (GM-004).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param int   $module_id Module.
	 * @return array<string, int>
	 */
	public function topics( Actor $actor, int $course_id, int $module_id ): array {
		$out = array();
		foreach ( $this->candidates( $actor, $course_id, null ) as $candidate ) {
			if ( $candidate['module_id'] === $module_id ) {
				$out[ $candidate['topic'] ] = ( $out[ $candidate['topic'] ] ?? 0 ) + 1;
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Candidates in module and question order: accessible modules, no
	 * flashcards, no questions of a running exam (G-5), optional topic.
	 *
	 * @param Actor       $actor     Learner.
	 * @param int         $course_id Course.
	 * @param string|null $topic     Topic.
	 * @return list<array{question_id: int, module_id: int, topic: string}>
	 */
	private function candidates( Actor $actor, int $course_id, ?string $topic ): array {
		$cm      = $this->db->table( 'ccap_course_module' );
		$pool    = $this->db->table( 'ccap_module_question' );
		$q       = $this->db->table( 'ccap_question' );
		$rev     = $this->db->table( 'ccap_question_revision' );
		$tag     = $this->db->table( 'ccap_question_tag' );
		$modules = array_map( 'intval', $this->db->wpdb()->get_col( $this->db->prepare( "SELECT module_id FROM `{$cm}` WHERE course_id = %d ORDER BY sort_order, module_id", $course_id ) ) );
		$locked  = $this->running_exam_questions( $actor->user_id );
		$seen    = array();
		$out     = array();
		$gate    = ContentGate::current();
		foreach ( $modules as $module ) {
			$decision = $gate->module( $actor, $course_id, $module );
			if ( ! $decision->allowed() ) {
				continue;
			}
			$rows = $this->db->rows(
				$this->db->prepare(
					"SELECT p.question_id, COALESCE((SELECT t.tag_key FROM `{$tag}` t WHERE t.revision_id = r.id AND t.tag_type = 'smart_category' ORDER BY t.id LIMIT 1), (SELECT t.tag_key FROM `{$tag}` t WHERE t.revision_id = r.id AND t.tag_type = 'topic' ORDER BY t.id LIMIT 1), '') AS topic FROM `{$pool}` p JOIN `{$q}` q ON q.id = p.question_id JOIN `{$rev}` r ON r.id = q.current_revision_id WHERE p.module_id = %d AND q.status = 'published' AND r.question_type <> %s ORDER BY (p.sort_order IS NULL), p.sort_order, p.question_id",
					$module,
					QuestionTypes::FLASHCARD
				)
			);
			if ( null !== $decision->limit ) {
				// Partial access: only the first n questions of the module (R0-08 §1.4 Nr. 2).
				$rows = array_slice( $rows, 0, $decision->limit );
			}
			foreach ( $rows as $row ) {
				$id = (int) $row['question_id'];
				if ( isset( $seen[ $id ] ) || in_array( $id, $locked, true ) || ( null !== $topic && (string) $row['topic'] !== $topic ) ) {
					continue;
				}
				$seen[ $id ] = true;
				$out[]       = array(
					'question_id' => $id,
					'module_id'   => $module,
					'topic'       => (string) $row['topic'],
				);
			}
		}
		return $out;
	}

	/**
	 * Whether at least one module of the course is accessible (module grants, E-286).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return bool
	 */
	private function any_module_accessible( Actor $actor, int $course_id ): bool {
		$modules = $this->db->wpdb()->get_col( $this->db->prepare( 'SELECT module_id FROM `' . $this->db->table( 'ccap_course_module' ) . '` WHERE course_id = %d', $course_id ) );
		$gate    = ContentGate::current();
		foreach ( $modules as $module ) {
			if ( $gate->module( $actor, $course_id, (int) $module )->allowed() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Score (`score`) with reason.
	 *
	 * @param array{question_id: int, module_id: int, topic: string} $candidate Candidate.
	 * @param array<string, mixed>|null                              $row       Progress.
	 * @param array<string, bool>                                    $weak      Weak topics.
	 * @return array{score: float, reason: string}
	 */
	private static function score( array $candidate, ?array $row, array $weak ): array {
		if ( null === $row || 0 === (int) $row['attempts'] ) {
			return array(
				'score'  => 1000.0,
				'reason' => 'never_seen',
			);
		}
		if ( 0 === (int) $row['streak'] ) {
			return array(
				'score'  => 800.0 + 10 * min( (int) $row['wrong_count'], 10 ),
				'reason' => 'last_wrong',
			);
		}
		$status = (string) $row['learn_status'];
		$stable = in_array( $status, array( 'secure', 'mastered' ), true );
		if ( $stable && null !== $row['due_at'] && (string) $row['due_at'] <= self::sql( self::now() ) ) {
			return array(
				'score'  => 700.0,
				'reason' => 'secure' === $status ? 'review_new_day' : 'review_due',
			);
		}
		$base = ( 1 - (float) $row['mastery_score'] ) * 300;
		if ( $stable ) {
			return array(
				'score'  => $base * 0.1,
				'reason' => 'practice',
			);
		}
		if ( ! empty( $weak[ $candidate['topic'] ] ) ) {
			return array(
				'score'  => $base + 150,
				'reason' => 'weak_topic',
			);
		}
		return array(
			'score'  => $base,
			'reason' => 'practice',
		);
	}

	/**
	 * Weak topics: hit ratio over the current candidate set < 0.6 (no attempts = 1.0).
	 *
	 * @param list<array{question_id: int, module_id: int, topic: string}> $candidates Candidates.
	 * @param array<int, array<string, mixed>>                             $progress   Progress rows.
	 * @return array<string, bool>
	 */
	private function weak_topics( array $candidates, array $progress ): array {
		$sum = array();
		foreach ( $candidates as $candidate ) {
			$row                             = $progress[ $candidate['question_id'] ] ?? null;
			$sum[ $candidate['topic'] ]['a'] = ( $sum[ $candidate['topic'] ]['a'] ?? 0 ) + ( null === $row ? 0 : (int) $row['attempts'] );
			$sum[ $candidate['topic'] ]['c'] = ( $sum[ $candidate['topic'] ]['c'] ?? 0 ) + ( null === $row ? 0 : (int) $row['correct_count'] );
		}
		$out = array();
		foreach ( $sum as $topic => $s ) {
			$out[ (string) $topic ] = $s['a'] > 0 && $s['c'] / $s['a'] < 0.6;
		}
		return $out;
	}

	/**
	 * Questions of the last 100 learning answers in the last 2 h, newest first.
	 *
	 * @param int $user Learner.
	 * @return list<int>
	 */
	private function recent( int $user ): array {
		$rows = $this->db->wpdb()->get_col( $this->db->prepare( 'SELECT question_id FROM `' . $this->db->table( 'ccap_attempt' ) . "` WHERE user_id = %d AND created_at >= %s AND context <> 'flashcard' ORDER BY created_at DESC, id DESC LIMIT 100", $user, self::sql( self::now() - 7200 ) ) );
		return array_values( array_unique( array_map( 'intval', $rows ) ) );
	}

	/**
	 * Progress rows by question.
	 *
	 * @param int   $user Learner.
	 * @param array $ids  Question IDs.
	 * @phpstan-param list<int> $ids
	 * @return array<int, array<string, mixed>>
	 */
	private function progress_rows( int $user, array $ids ): array {
		if ( array() === $ids ) {
			return array();
		}
		$list = implode( ',', array_map( 'intval', $ids ) );
		$out  = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_question_progress' ) . "` WHERE user_id = %d AND question_id IN ({$list})", $user ) ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
			$out[ (int) $row['question_id'] ] = $row;
		}
		return $out;
	}

	/**
	 * Questions locked by a running exam (deadline + tolerance, without limit 24 h).
	 *
	 * @param int $user Learner.
	 * @return list<int>
	 */
	private function running_exam_questions( int $user ): array {
		$sessions  = $this->db->table( 'ccap_exam_session' );
		$questions = $this->db->table( 'ccap_exam_session_question' );
		return array_map(
			'intval',
			$this->db->wpdb()->get_col(
				$this->db->prepare(
					"SELECT sq.question_id FROM `{$questions}` sq JOIN `{$sessions}` s ON s.id = sq.session_id WHERE s.user_id = %d AND s.status = 'in_progress' AND (s.deadline_at IS NOT NULL OR s.started_at > %s)",
					$user,
					self::sql( time() - \CourseCaptain\Exam\ExamSessions::UNLIMITED_LOCK )
				)
			)
		);
	}
}
