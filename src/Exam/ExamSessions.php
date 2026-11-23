<?php
/**
 * Exam attempt state machine (R0-08 §4.3–§4.4, §6).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Exam;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Event\Outbox;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\NamedLock;
use CourseCaptain\Storage\RateLimiter;

defined( 'ABSPATH' ) || exit;

/**
 * States: in_progress → evaluated | cancelled | broken. Session, all
 * session questions and the snapshot are written in one transaction;
 * every write locks the session row (`FOR UPDATE`) and uses CAS. The
 * evaluation only uses the snapshot and the frozen question revisions.
 */
final class ExamSessions {

	public const FORMAT_VERSION    = 2;
	public const EVALUATOR_VERSION = '1.0.0';
	public const UNLIMITED_LOCK    = 86400;
	private const START_LOCK_WAIT  = 10;
	/** Default of `exams.starts_per_hour` (SET-CC-13); the effective value comes from BusinessRules. */
	public const START_LIMIT_HOUR = 40;

	/**
	 * Clock override (tests).
	 *
	 * @var int|null
	 */
	private ?int $fixed_now = null;

	/**
	 * Constructor.
	 *
	 * @param Database           $db        Database access.
	 * @param PolicyService      $policy    Policy.
	 * @param ExamRules          $rules     Rules.
	 * @param QuestionRepository $questions Question bank.
	 */
	public function __construct( private Database $db, private PolicyService $policy, private ExamRules $rules, private QuestionRepository $questions ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, PolicyService::instance(), new ExamRules( $db, PolicyService::instance() ), QuestionRepository::current() );
	}

	/**
	 * Start (or resume) an attempt.
	 *
	 * @param Actor           $actor       Learner.
	 * @param int             $rule_id     Published rule.
	 * @param int|null        $variant_id  Fixed variant or null (random from blueprints).
	 * @param string          $request_key Client UUID (`start_token`).
	 * @param Randomizer|null $randomizer  Injected for tests.
	 * @return int Session ID (the running one on repeats).
	 * @throws CourseException With `ccap_exam_*` codes.
	 */
	public function start( Actor $actor, int $rule_id, ?int $variant_id, string $request_key, ?Randomizer $randomizer = null ): int {
		$rule = $this->rules->get( $rule_id );
		if ( null === $rule ) {
			throw new CourseException( 'ccap_not_found', 'Unknown exam.', 404 );
		}
		$course = (int) $rule['course_id'];
		$target = $this->exam_modules( $rule, $variant_id );
		ContentGate::current()->exam( $actor, $course, $target['modules'], $target['variant_no'] )->assert();
		if ( 'published' !== $rule['status'] || null === $rule['passing_rule'] || array() !== ExamRules::validate_passing_rule( $rule['passing_rule'] ) ) {
			throw new CourseException( 'ccap_exam_not_startable', 'rule_unavailable', 422 );
		}

		$lock = NamedLock::for_key( $this->db, 'exam_start:' . $actor->user_id );
		if ( NamedLock::BUSY === $lock->acquire( self::START_LOCK_WAIT ) ) {
			throw new CourseException( 'ccap_exam_start_busy', 'Start in progress.', 409 );
		}
		try {
			$form_key = null === $variant_id ? 'random:' . $rule_id : 'variant:' . $variant_id;
			$table    = $this->db->table( 'ccap_exam_session' );
			// One read for: expire due attempts, the running attempt of this form, a repeated start token (R0-14 OP-05).
			$rows    = $this->db->rows( $this->db->prepare( "SELECT id, status, form_key, start_token, snapshot, deadline_at FROM `{$table}` WHERE user_id = %d AND (status = 'in_progress' OR start_token = %s) ORDER BY id", $actor->user_id, $request_key ) );
			$running = null;
			$known   = null;
			foreach ( $rows as $row ) {
				if ( 'in_progress' === $row['status'] && null !== $row['deadline_at'] && $this->is_overdue( $row ) ) {
					$this->finish( (int) $row['id'], 'expired' );
					$row['status'] = 'expired';
				}
				if ( null === $running && 'in_progress' === $row['status'] && $form_key === $row['form_key'] ) {
					$running = (int) $row['id'];
				}
				if ( null === $known && $request_key === $row['start_token'] ) {
					$known = (int) $row['id'];
				}
			}
			if ( null !== $running ) {
				return $running;
			}
			if ( null !== $known ) {
				return $known;
			}
			$this->assert_attempts_left( $actor->user_id, $rule );
			RateLimiter::hit( $this->db, 'exam_start', $actor->user_id, 3600, \CourseCaptain\Settings\BusinessRules::exam_starts_per_hour(), $this->now() );

			$randomizer = $randomizer ?? Randomizer::fresh();
			$plan       = null === $variant_id ? $this->draw( $actor, $rule, $randomizer ) : $this->variant( $actor, $rule, $variant_id );
			if ( array() === $plan['questions'] ) {
				throw new CourseException( 'ccap_exam_not_startable', 'no_questions', 422 );
			}
			return $this->create_session( $actor, $rule, $variant_id, $form_key, $request_key, $randomizer, $plan );
		} finally {
			$lock->release();
		}
	}

	/**
	 * Settings of a historical attempt (SN-3/SN-4): only from the snapshot,
	 * never resolved again; snapshots without provenance report `unknown`.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return array<string, array{v: mixed, src: string}>
	 */
	public static function settings_provenance( array $snapshot ): array {
		if ( isset( $snapshot['settings_provenance'] ) && is_array( $snapshot['settings_provenance'] ) ) {
			return $snapshot['settings_provenance'];
		}
		$out = array();
		foreach ( array_keys( ExamRules::REGISTER_MAP ) as $key ) {
			$out[ $key ] = array(
				'v'   => null,
				'src' => 'unknown',
			);
		}
		return $out;
	}

	/**
	 * Learner view of a session: never solutions while running (GM-028).
	 *
	 * @param Actor $actor   Learner.
	 * @param int   $session Session.
	 * @return array<string, mixed>
	 */
	public function view( Actor $actor, int $session ): array {
		$this->expire_if_due( $session );
		$row      = $this->owned( $actor, $session, false );
		$snapshot = (array) json_decode( (string) $row['snapshot'], true );
		$items    = array();
		foreach ( $this->session_questions( $session ) as $sq ) {
			$revision  = (array) $this->questions->revision( (int) $sq['revision_id'] );
			$order     = (array) json_decode( (string) $sq['answer_order'], true );
			$delivered = QuestionTypes::deliver( $revision, new Randomizer( (string) $snapshot['randomizer']['seed'] ), (string) $sq['question_id'] );
			$item      = array(
				'position' => (int) $sq['position'],
				'question' => self::reorder( $delivered['payload'], $order ),
				'answered' => null !== $sq['answered_at'],
				'answer'   => null === $sq['given_answer'] ? null : json_decode( (string) $sq['given_answer'], true ),
				'flagged'  => '1' === (string) $sq['flagged'],
				'revealed' => null !== $sq['revealed_at'],
			);
			if ( null !== $sq['revealed_at'] && QuestionTypes::OPEN_TEXT === $revision['question_type'] ) {
				$item['model_answer'] = (string) ( $revision['answers'][0]['text'] ?? '' );
			}
			$items[] = $item;
		}
		$deadline = null === $row['deadline_at'] ? null : (string) $row['deadline_at'];
		return array(
			'session_id'        => $session,
			'status'            => (string) $row['status'],
			'server_now'        => gmdate( 'c', $this->now() ),
			'deadline_at'       => null === $deadline ? null : gmdate( 'c', (int) strtotime( $deadline . ' UTC' ) ),
			'remaining_seconds' => null === $deadline ? null : max( 0, (int) strtotime( $deadline . ' UTC' ) - $this->now() ),
			'questions'         => $items,
			'result'            => 'evaluated' === $row['status'] ? $this->learner_result( $row, $snapshot ) : null,
		);
	}

	/**
	 * Result as the learner may see it (ExamFeedback over the snapshot).
	 *
	 * @param array<string, mixed> $row      Evaluated session row.
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return array<string, mixed>
	 */
	public function learner_result( array $row, array $snapshot ): array {
		$solutions = array();
		foreach ( $this->session_questions( (int) $row['id'] ) as $sq ) {
			$revision = (array) $this->questions->revision( (int) $sq['revision_id'] );
			if ( ! in_array( $revision['question_type'] ?? '', array( QuestionTypes::SINGLE, QuestionTypes::MULTI, QuestionTypes::TRUE_FALSE ), true ) ) {
				continue;
			}
			$keys = array();
			foreach ( (array) $revision['answers'] as $a ) {
				if ( is_array( $a ) && ! empty( $a['correct'] ) ) {
					$keys[] = (string) $a['key'];
				}
			}
			$solutions[ (int) $sq['position'] ] = $keys;
		}
		$rule = $this->rules->get( (int) $row['rule_id'] );
		return ExamFeedback::filter(
			(array) json_decode( (string) $row['result'], true ),
			(array) ( $snapshot['feedback'] ?? array() ),
			null !== $rule && $this->has_attempts_left( (int) $row['user_id'], $rule ),
			$solutions,
			(int) $row['evaluation_no']
		);
	}

	/**
	 * Whether another attempt is possible (ignoring cooldown).
	 *
	 * @param int                  $user Learner.
	 * @param array<string, mixed> $rule Rule.
	 * @return bool
	 */
	private function has_attempts_left( int $user, array $rule ): bool {
		if ( 'published' !== $rule['status'] ) {
			return false;
		}
		try {
			$this->assert_attempts_left( $user, array_replace_recursive( $rule, array( 'settings' => array( 'attempt_policy' => array( 'cooldown_seconds' => 0 ) ) ) ) );
			return true;
		} catch ( CourseException $e ) {
			return false;
		}
	}

	/**
	 * Save an answer (empty input withdraws the answer).
	 *
	 * @param Actor $actor    Learner.
	 * @param int   $session  Session.
	 * @param int   $position Position.
	 * @param mixed $answer   Answer.
	 * @param int   $age_ms   Age of a client-buffered answer (offline queue), bounded by the late tolerance.
	 * @return array{applied: bool}
	 * @throws CourseException With `ccap_exam_*` codes.
	 */
	public function save_answer( Actor $actor, int $session, int $position, $answer, int $age_ms = 0 ): array {
		$saved_at = null;
		$result   = $this->mutate(
			$actor,
			$session,
			$position,
			function ( array $row, array $sq, int $at ) use ( $answer, &$saved_at ): array {
				if ( null !== $sq['answered_at'] && (int) strtotime( $sq['answered_at'] . ' UTC' ) > $at ) {
					return array(); // Older buffered state from another tab/offline queue (§6.4).
				}
				$saved_at = gmdate( 'c', $at );
				$revision = (array) $this->questions->revision( (int) $sq['revision_id'] );
				if ( QuestionTypes::OPEN_TEXT === $revision['question_type'] && null === $sq['revealed_at'] ) {
					throw new CourseException( 'ccap_exam_answer_locked', 'Reveal the model answer first.', 422 );
				}
				$order      = (array) json_decode( (string) $sq['answer_order'], true );
				$evaluation = QuestionTypes::evaluate( $revision, $answer, array_values( array_map( 'strval', $order ) ) );
				if ( ! $evaluation->valid ) {
					throw new CourseException( 'ccap_exam_invalid_answer', 'Invalid answer.', 422 );
				}
				if ( ! $evaluation->answered ) {
					return array(
						'given_answer' => null,
						'answered_at'  => null,
						'is_correct'   => null,
					);
				}
				return array(
					'given_answer' => (string) wp_json_encode( $answer ),
					'answered_at'  => gmdate( 'Y-m-d H:i:s', $at ),
					'is_correct'   => $evaluation->correct() ? 1 : 0,
				);
			},
			$age_ms
		);
		return $result + array( 'saved_at' => $result['applied'] ? $saved_at : null );
	}

	/**
	 * Flag or unflag a position (idempotent).
	 *
	 * @param Actor $actor    Learner.
	 * @param int   $session  Session.
	 * @param int   $position Position.
	 * @param bool  $on       Flag state.
	 * @return array{applied: bool}
	 */
	public function flag( Actor $actor, int $session, int $position, bool $on ): array {
		return $this->mutate( $actor, $session, $position, static fn(): array => array( 'flagged' => $on ? 1 : 0 ) );
	}

	/**
	 * Reveal the model answer of an open question (once).
	 *
	 * @param Actor $actor    Learner.
	 * @param int   $session  Session.
	 * @param int   $position Position.
	 * @return string Model answer.
	 * @throws CourseException For other types.
	 */
	public function reveal( Actor $actor, int $session, int $position ): string {
		$model = '';
		$this->mutate(
			$actor,
			$session,
			$position,
			function ( array $row, array $sq ) use ( &$model ): array {
				$revision = (array) $this->questions->revision( (int) $sq['revision_id'] );
				if ( QuestionTypes::OPEN_TEXT !== $revision['question_type'] ) {
					throw new CourseException( 'ccap_invalid_param', 'Nothing to reveal.', 400 );
				}
				$model = (string) ( $revision['answers'][0]['text'] ?? '' );
				return null === $sq['revealed_at'] ? array( 'revealed_at' => $this->now_sql() ) : array();
			}
		);
		return $model;
	}

	/**
	 * Submit and evaluate (idempotent: a finished attempt returns its result).
	 *
	 * @param Actor $actor   Learner.
	 * @param int   $session Session.
	 * @return array<string, mixed> Result.
	 * @throws CourseException `ccap_exam_aborted` for a cancelled attempt.
	 */
	public function submit( Actor $actor, int $session ): array {
		$row = $this->owned( $actor, $session, false );
		if ( 'in_progress' === $row['status'] ) {
			$this->finish( $session, 'submitted' );
		}
		$after = (array) $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		if ( 'cancelled' === $after['status'] ) {
			throw new CourseException( 'ccap_exam_aborted', 'The attempt was cancelled.', 409 );
		}
		if ( 'evaluated' !== $after['status'] ) {
			return array( 'status' => (string) $after['status'] ) + (array) json_decode( (string) $after['result'], true );
		}
		return array( 'status' => 'evaluated' ) + $this->learner_result( $after, (array) json_decode( (string) $after['snapshot'], true ) );
	}

	/**
	 * Cancel without evaluation; an expired attempt is evaluated instead (GM-041).
	 *
	 * @param Actor $actor   Learner.
	 * @param int   $session Session.
	 * @return string Resulting status.
	 * @throws CourseException `ccap_exam_already_submitted` for an evaluated attempt.
	 */
	public function cancel( Actor $actor, int $session ): string {
		$row = $this->owned( $actor, $session, false );
		if ( 'cancelled' === $row['status'] ) {
			return 'cancelled';
		}
		if ( 'in_progress' !== $row['status'] ) {
			throw new CourseException( 'ccap_exam_already_submitted', 'The attempt is already submitted.', 409 );
		}
		if ( $this->is_overdue( $row ) ) {
			$this->finish( $session, 'expired' );
			return 'evaluated';
		}
		$this->db->transaction(
			function ( Database $db ) use ( $session ): void {
				$table = $db->table( 'ccap_exam_session' );
				$row   = (array) $db->row( $db->prepare( "SELECT row_version, status FROM `{$table}` WHERE id = %d FOR UPDATE", $session ) );
				if ( 'in_progress' === $row['status'] ) {
					$db->compare_and_swap(
						'ccap_exam_session',
						$session,
						(int) $row['row_version'],
						array(
							'status'       => 'cancelled',
							'end_reason'   => 'user',
							'submitted_at' => $this->now_sql(),
						)
					);
				}
			}
		);
		do_action( 'course_captain_policy_changed' );
		return 'cancelled';
	}

	/**
	 * Cron: evaluate attempts past deadline + late tolerance (GM-035).
	 *
	 * @return int Number of evaluated sessions.
	 */
	public function expire_due(): int {
		$table = $this->db->table( 'ccap_exam_session' );
		$rows  = $this->db->rows( $this->db->prepare( "SELECT id, snapshot, deadline_at FROM `{$table}` WHERE status = 'in_progress' AND deadline_at IS NOT NULL AND deadline_at < %s ORDER BY id LIMIT 200", $this->now_sql() ) );
		$count = 0;
		foreach ( $rows as $row ) {
			if ( $this->is_overdue( $row ) ) {
				$this->finish( (int) $row['id'], 'expired' );
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Fix the clock (tests).
	 *
	 * @param int|null $now Unix time or null.
	 * @return void
	 */
	public function set_now( ?int $now ): void {
		$this->fixed_now = $now;
	}

	/**
	 * Draw questions per blueprint (pool shuffled per section, grouped by section).
	 *
	 * @param Actor                $actor      Learner.
	 * @param array<string, mixed> $rule       Rule.
	 * @param Randomizer           $randomizer Randomizer.
	 * @return array{questions: list<array{question_id: int, section: int, points: int}>, reduced: bool, revisions: array<int, int>}
	 */
	private function draw( Actor $actor, array $rule, Randomizer $randomizer ): array {
		$questions = array();
		$reduced   = false;
		$revisions = array();
		foreach ( $rule['blueprints'] as $blueprint ) {
			$module     = (int) $blueprint['module_id'];
			$meta       = (array) json_decode( (string) $blueprint['rules'], true );
			$current    = $this->questions->pool_revisions( $module );
			$revisions += $current;
			$pool       = $randomizer->shuffle( array_keys( $current ), 'pool:' . $module );
			$count      = (int) $blueprint['question_count'];
			$picked     = array_slice( $pool, 0, $count );
			if ( count( $picked ) < $count ) {
				$reduced = true;
			}
			foreach ( $picked as $question ) {
				$questions[] = array(
					'question_id' => (int) $question,
					'section'     => $module,
					'points'      => (int) ( $meta['points'] ?? 1 ),
				);
			}
		}
		return array(
			'questions' => $questions,
			'reduced'   => $reduced,
			'revisions' => $revisions,
		);
	}

	/**
	 * Modules an exam draws from (blueprint or variant) and the variant number.
	 *
	 * @param array<string, mixed> $rule       Rule.
	 * @param int|null             $variant_id Variant.
	 * @return array{modules: list<int>, variant_no: int|null}
	 * @throws CourseException For unknown variants.
	 */
	private function exam_modules( array $rule, ?int $variant_id ): array {
		if ( null === $variant_id ) {
			return array(
				'modules'    => array_values( array_map( static fn( array $b ): int => (int) $b['module_id'], (array) $rule['blueprints'] ) ),
				'variant_no' => null,
			);
		}
		$variant = $this->db->row( $this->db->prepare( 'SELECT variant_no, course_id FROM `' . $this->db->table( 'ccap_exam_variant' ) . "` WHERE id = %d AND status = 'published'", $variant_id ) );
		if ( null === $variant || (int) $variant['course_id'] !== (int) $rule['course_id'] ) {
			throw new CourseException( 'ccap_not_found', 'Unknown variant.', 404 );
		}
		$relations = new Relations( $this->db );
		$modules   = array();
		foreach ( $this->db->wpdb()->get_col( $this->db->prepare( 'SELECT question_id FROM `' . $this->db->table( 'ccap_exam_variant_question' ) . '` WHERE variant_id = %d ORDER BY position', $variant_id ) ) as $question ) {
			foreach ( $this->modules_of_question( (int) $question ) as $module ) {
				if ( in_array( (int) $rule['course_id'], $relations->courses_of_module( $module ), true ) ) {
					$modules[] = $module;
					break;
				}
			}
		}
		return array(
			'modules'    => array_values( array_unique( $modules ) ),
			'variant_no' => (int) $variant['variant_no'],
		);
	}

	/**
	 * Questions of a fixed variant in position order.
	 *
	 * @param Actor                $actor      Learner.
	 * @param array<string, mixed> $rule       Rule.
	 * @param int                  $variant_id Variant.
	 * @return array{questions: list<array{question_id: int, section: int, points: int}>, reduced: bool}
	 * @throws CourseException For variants of other courses or with unavailable questions.
	 */
	private function variant( Actor $actor, array $rule, int $variant_id ): array {
		$variant = $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_exam_variant' ) . "` WHERE id = %d AND status = 'published'", $variant_id ) );
		if ( null === $variant || (int) $variant['course_id'] !== (int) $rule['course_id'] ) {
			throw new CourseException( 'ccap_not_found', 'Unknown variant.', 404 );
		}
		$rows      = $this->db->rows( $this->db->prepare( 'SELECT question_id FROM `' . $this->db->table( 'ccap_exam_variant_question' ) . '` WHERE variant_id = %d ORDER BY position', $variant_id ) );
		$relations = new Relations( $this->db );
		$questions = array();
		foreach ( $rows as $row ) {
			$question = (int) $row['question_id'];
			$sections = array_values( array_filter( $this->modules_of_question( $question ), static fn( int $m ): bool => in_array( (int) $rule['course_id'], $relations->courses_of_module( $m ), true ) ) );
			$section  = $sections[0] ?? 0;
			$current  = $this->db->row( $this->db->prepare( 'SELECT q.status, r.editorial_status FROM `' . $this->db->table( 'ccap_question' ) . '` q JOIN `' . $this->db->table( 'ccap_question_revision' ) . '` r ON r.id = q.current_revision_id WHERE q.id = %d', $question ) );
			if ( null === $current || 'published' !== $current['status'] || 'published' !== $current['editorial_status'] ) {
				throw new CourseException( 'ccap_exam_not_startable', 'variant_incomplete', 422 );
			}
			$questions[] = array(
				'question_id' => $question,
				'section'     => $section,
				'points'      => 1,
			);
		}
		return array(
			'questions' => $questions,
			'reduced'   => false,
		);
	}

	/**
	 * Write session, session questions and snapshot in one transaction.
	 *
	 * @param Actor                $actor       Learner.
	 * @param array<string, mixed> $rule        Rule.
	 * @param int|null             $variant_id  Variant.
	 * @param string               $form_key    Form key.
	 * @param string               $request_key Start token.
	 * @param Randomizer           $randomizer  Randomizer.
	 * @param array<string, mixed> $plan        Questions and reduced flag.
	 * @return int
	 */
	private function create_session( Actor $actor, array $rule, ?int $variant_id, string $form_key, string $request_key, Randomizer $randomizer, array $plan ): int {
		$settings = (array) $rule['settings'];
		$items    = array();
		// One query for the current revisions, three for their content (R0-14 OP-05: no per-question queries).
		$qids    = array_map( static fn( array $q ): int => (int) $q['question_id'], $plan['questions'] );
		$known   = (array) ( $plan['revisions'] ?? array() );
		$current = array();
		$missing = array();
		foreach ( $qids as $qid ) {
			if ( isset( $known[ $qid ] ) ) {
				$current[ $qid ] = (int) $known[ $qid ];
			} else {
				$missing[] = $qid;
			}
		}
		if ( array() !== $missing ) {
			foreach ( $this->db->rows( 'SELECT id, current_revision_id FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id IN (' . implode( ',', $missing ) . ')' ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Integer list.
				$current[ (int) $row['id'] ] = (int) $row['current_revision_id'];
			}
		}
		$revisions = $this->questions->revisions( array_values( $current ) );
		foreach ( $plan['questions'] as $i => $q ) {
			$revision_id = $current[ (int) $q['question_id'] ] ?? 0;
			$revision    = (array) ( $revisions[ $revision_id ] ?? array() );
			$order       = empty( $settings['shuffle_answers'] ) ? array() : QuestionTypes::deliver( $revision, $randomizer, (string) $q['question_id'] )['order'];
			$items[]     = $q + array(
				'position'    => $i + 1,
				'revision_id' => $revision_id,
				'order'       => array_values( $order ),
				'scoring'     => (string) ( $revision['settings']['scoring'] ?? QuestionTypes::ALL_OR_NOTHING ),
			);
		}
		$limit    = null === $rule['time_limit_seconds'] ? null : (int) $rule['time_limit_seconds'];
		$started  = $this->now();
		$practice = 'practice' === ( $settings['purpose'] ?? 'evidence' );
		// Accommodation (R0-28 §1.3 step 6, C-5): applies even over a lock; frozen, never shown in results (E-122).
		$accommodation = null === $limit ? null : EffectiveSettings::accommodation( $this->db, $actor->user_id, (int) $rule['course_id'], (int) $rule['id'], $started );
		if ( null !== $accommodation ) {
			$limit = $accommodation['unlimited'] ? null : ( null === $accommodation['factor_pct'] ? $limit : intdiv( $limit * $accommodation['factor_pct'], 100 ) );
		}
		$provenance = array();
		foreach ( (array) ( $rule['settings_provenance'] ?? array() ) as $key => $entry ) {
			$provenance[ (string) $key ] = array(
				'v'   => $entry['v'] ?? null,
				'src' => (string) ( $entry['src'] ?? 'unknown' ),
				'ref' => in_array( $entry['src'] ?? '', array( 'global', 'global_lock', 'schema_default' ), true ) ? 'site' : 'rule:' . (string) $rule['uid'] . '@' . (string) $rule['version'],
			);
		}
		$parts          = self::frozen_parts( (array) $rule['passing_rule'], $items );
		$question_parts = array();
		foreach ( $items as $item ) {
			$key = ExamRules::part_of_section( $parts, (int) $item['section'] );
			if ( null !== $key ) {
				$question_parts[ (string) $item['position'] ] = $key;
			}
		}
		$snapshot = array(
			'format_version'      => self::FORMAT_VERSION,
			'rule'                => array(
				'id'      => (int) $rule['id'],
				'uid'     => (string) $rule['uid'],
				'version' => (string) $rule['version'],
				'hash'    => 'sha256:' . hash( 'sha256', (string) wp_json_encode( array( $rule['passing_rule'], $rule['settings'], $rule['blueprints'] ) ) ),
			),
			'evidence_eligible'   => ! $practice && ! $plan['reduced'],
			'reduced'             => $plan['reduced'],
			'practice'            => $practice,
			'timing'              => array(
				'time_limit_seconds'     => $limit,
				'grace_seconds'          => (int) $settings['grace_seconds'],
				'late_tolerance_seconds' => (int) $settings['late_tolerance_seconds'],
				'timer_mode'             => (string) $settings['timer_mode'],
			),
			'passing_rule'        => $rule['passing_rule'],
			'randomizer'          => array(
				'seed'               => $randomizer->seed(),
				'randomizer_version' => Randomizer::VERSION,
			),
			'scoring'             => array_map(
				static fn( array $item ): array => array(
					'position' => $item['position'],
					'points'   => $item['points'],
					'scoring'  => $item['scoring'],
					'section'  => $item['section'],
				),
				$items
			),
			'feedback'            => $settings['feedback'],
			// Section/topic labels are frozen at the start (R0-08 §6.3, R0-23 RV-01 / REV-021).
			'section_labels'      => self::section_labels( $items ),
			// Parts over module groups (E-331) and question → part, frozen at the start (R0-08 §4.1, §6.3).
			'parts'               => $parts,
			'question_parts'      => $question_parts,
			'attempt_policy'      => $settings['attempt_policy'],
			'competency'          => array(
				'certifies_objectives' => array_values( array_map( 'strval', (array) ( $settings['certifies_objectives'] ?? array() ) ) ),
				'validity_days'        => isset( $settings['competency_validity_days'] ) ? max( 1, (int) $settings['competency_validity_days'] ) : null,
			),
			'open_text_in_score'  => (bool) $settings['open_text_in_score'],
			'evaluator_version'   => self::EVALUATOR_VERSION,
			// R0-28 T28-2: effective values with source; historical views read only this (SN-3).
			'settings_provenance' => $provenance,
			'accommodation'       => $accommodation,
		);
		ksort( $snapshot );
		$json = (string) wp_json_encode( $snapshot );
		$id   = $this->db->transaction(
			function ( Database $db ) use ( $actor, $rule, $variant_id, $form_key, $request_key, $randomizer, $items, $json, $limit, $started ): int {
				$session = $db->insert_values(
					'ccap_exam_session',
					array(
						'uid'                => $db->uuid(),
						'user_id'            => $actor->user_id,
						'course_id'          => (int) $rule['course_id'],
						'course_revision_id' => ( function () use ( $rule ): ?int {
							$rev = get_post_meta( (int) $rule['course_id'], '_ccap_current_revision_id', true );
							return '' === $rev ? null : (int) $rev;
						} )(),
						'rule_id'            => (int) $rule['id'],
						'variant_id'         => $variant_id,
						'mode'               => null === $variant_id ? 'random' : 'variant',
						'form_key'           => $form_key,
						'status'             => 'in_progress',
						'start_token'        => $request_key,
						'snapshot'           => $json,
						'snapshot_hash'      => 'sha256:' . hash( 'sha256', $json ),
						'seed'               => (int) $randomizer->seed(),
						'evaluator_version'  => self::EVALUATOR_VERSION,
						'started_at'         => gmdate( 'Y-m-d H:i:s', $started ),
						'deadline_at'        => null === $limit ? null : gmdate( 'Y-m-d H:i:s', $started + $limit ),
					)
				);
				// One multi-row INSERT for all session questions (R0-14 OP-05).
				$values = array();
				foreach ( $items as $item ) {
					$values[] = $db->prepare( '(%d, %d, %d, %d, %s)', $session, $item['position'], $item['question_id'], $item['revision_id'], (string) wp_json_encode( $item['order'] ) );
				}
				if ( array() !== $values ) {
					$db->execute( 'INSERT INTO `' . $db->table( 'ccap_exam_session_question' ) . '` (session_id, position, question_id, revision_id, answer_order) VALUES ' . implode( ',', $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Rows prepared above.
				}
				return $session;
			}
		);
		do_action( 'course_captain_policy_changed' );
		return $id;
	}

	/**
	 * Evaluate against the snapshot and close the attempt.
	 *
	 * @param int    $session    Session.
	 * @param string $end_reason submitted|expired.
	 * @return void
	 */
	private function finish( int $session, string $end_reason ): void {
		$this->db->transaction(
			function ( Database $db ) use ( $session, $end_reason ): void {
				$table = $db->table( 'ccap_exam_session' );
				$row   = (array) $db->row( $db->prepare( "SELECT * FROM `{$table}` WHERE id = %d FOR UPDATE", $session ) );
				if ( 'in_progress' !== $row['status'] ) {
					return;
				}
				$snapshot = json_decode( (string) $row['snapshot'], true );
				if ( ! is_array( $snapshot ) || 'sha256:' . hash( 'sha256', (string) $row['snapshot'] ) !== $row['snapshot_hash'] ) {
					$db->compare_and_swap(
						'ccap_exam_session',
						$session,
						(int) $row['row_version'],
						array(
							'status' => 'broken',
							'result' => (string) wp_json_encode( array( 'error' => 'ccap_exam_snapshot_invalid' ) ),
						)
					);
					return;
				}
				$result = $this->evaluate( $row, $snapshot );
				$db->compare_and_swap(
					'ccap_exam_session',
					$session,
					(int) $row['row_version'],
					array(
						'status'           => 'evaluated',
						'end_reason'       => $end_reason,
						'submitted_at'     => $this->now_sql(),
						'evaluated_at'     => $this->now_sql(),
						'evaluation_no'    => 1,
						'points_milli'     => $result['points_milli'],
						'max_points_milli' => $result['max_points_milli'],
						'passed'           => $result['passed'] ? 1 : 0,
						'result'           => (string) wp_json_encode( $result ),
					)
				);
				$this->record_result_event( $db, $row, $result['passed'] ? 'exam.passed' : 'exam.failed', 1, array() );
			}
		);
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * Manual re-evaluation of one question (E-74, R-47 data contract; route
	 * and UI are Pro). Never automatic, never the own attempt (M-10). Each
	 * call writes a new `evaluation_no`, an audit entry and
	 * `exam.reevaluated`.
	 *
	 * @param Actor    $actor        Grader.
	 * @param int      $session      Evaluated session.
	 * @param int      $position     Position.
	 * @param int|null $points_milli New points (0…weight), null with $neutralize.
	 * @param string   $reason       Mandatory justification.
	 * @param bool     $neutralize   Remove the question from the score (faulty question).
	 * @return array{evaluation_no: int, result: array<string, mixed>}
	 * @throws CourseException `ccap_forbidden`, `ccap_validation_failed`, `ccap_exam_conflict`.
	 */
	public function regrade( Actor $actor, int $session, int $position, ?int $points_milli, string $reason, bool $neutralize = false ): array {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::GRADE_ATTEMPT, array( $session ) ), 'Not allowed to grade this attempt.' );
		$reason = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) || ( ! $neutralize && ( null === $points_milli || $points_milli < 0 ) ) ) {
			throw new CourseException( 'ccap_validation_failed', 'Points and a reason are required.', 422 );
		}
		$out = $this->db->transaction(
			function ( Database $db ) use ( $actor, $session, $position, $points_milli, $reason, $neutralize ): array {
				$table = $db->table( 'ccap_exam_session' );
				$row   = (array) $db->row( $db->prepare( "SELECT * FROM `{$table}` WHERE id = %d FOR UPDATE", $session ) );
				if ( 'evaluated' !== ( $row['status'] ?? '' ) ) {
					throw new CourseException( 'ccap_exam_conflict', 'Only evaluated attempts can be re-evaluated.', 409 );
				}
				$snapshot = (array) json_decode( (string) $row['snapshot'], true );
				$scoring  = array_column( (array) ( $snapshot['scoring'] ?? array() ), null, 'position' );
				if ( ! isset( $scoring[ $position ] ) ) {
					throw new CourseException( 'ccap_validation_failed', 'Unknown position.', 422 );
				}
				$previous  = (array) json_decode( (string) $row['result'], true );
				$overrides = array_map( 'intval', (array) ( $previous['overrides'] ?? array() ) );
				if ( $neutralize ) {
					$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session_question' ) . '` SET neutralized = 1 WHERE session_id = %d AND position = %d', $session, $position ) );
					unset( $overrides[ $position ] );
				} else {
					$weight = 1000 * (int) $scoring[ $position ]['points'];
					if ( (int) $points_milli > $weight ) {
						throw new CourseException( 'ccap_validation_failed', 'Points exceed the question weight.', 422 );
					}
					$overrides[ $position ] = (int) $points_milli;
				}
				$result              = $this->evaluate( $row, $snapshot, $overrides );
				$result['overrides'] = $overrides;
				$number              = (int) $row['evaluation_no'] + 1;
				$db->compare_and_swap(
					'ccap_exam_session',
					$session,
					(int) $row['row_version'],
					array(
						'evaluation_no'    => $number,
						'evaluated_at'     => $this->now_sql(),
						'points_milli'     => $result['points_milli'],
						'max_points_milli' => $result['max_points_milli'],
						'passed'           => $result['passed'] ? 1 : 0,
						'result'           => (string) wp_json_encode( $result ),
					)
				);
				$passed_old = ! empty( $previous['passed'] );
				AuditLog::write(
					$db,
					$actor,
					'attempt_graded',
					'exam_session',
					(string) $row['uid'],
					$reason,
					array(
						'position'      => $position,
						'points_milli'  => $neutralize ? null : $points_milli,
						'neutralized'   => $neutralize,
						'evaluation_no' => $number,
						'passed_old'    => $passed_old,
						'passed'        => $result['passed'],
					),
					(int) $row['user_id']
				);
				$this->record_result_event(
					$db,
					$row,
					'exam.reevaluated',
					$number,
					array(
						'passed_old' => $passed_old,
						'passed'     => $result['passed'],
					)
				);
				return array(
					'evaluation_no' => $number,
					'result'        => $result,
				);
			}
		);
		do_action( 'course_captain_policy_changed' );
		return $out;
	}

	/**
	 * Outbox event for an evaluation (R0-19 §5: `exam.passed`/`exam.failed`
	 * once per `evaluation_no`, `exam.reevaluated`).
	 *
	 * @param Database             $db     Database in transaction.
	 * @param array<string, mixed> $row    Session row (before update).
	 * @param string               $type   Event type.
	 * @param int                  $number Evaluation number.
	 * @param array<string, mixed> $extra  Additional payload.
	 * @return void
	 */
	private function record_result_event( Database $db, array $row, string $type, int $number, array $extra ): void {
		$fresh = (array) $db->row( $db->prepare( 'SELECT points_milli, max_points_milli FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', (int) $row['id'] ) );
		Outbox::record(
			$db,
			$type,
			'exam_session',
			(string) $row['uid'],
			$number,
			array(
				'session_uid'       => (string) $row['uid'],
				'mode'              => (string) $row['mode'],
				'points_milli'      => (int) ( $fresh['points_milli'] ?? 0 ),
				'max_points_milli'  => (int) ( $fresh['max_points_milli'] ?? 0 ),
				'evaluation_no'     => $number,
				'evidence_eligible' => ! empty( json_decode( (string) $row['snapshot'], true )['evidence_eligible'] ),
			) + $extra,
			array(
				'user_id'      => (int) $row['user_id'],
				'actor_type'   => 'system',
				'privacy_tags' => array( 'personal_ref', 'assessment_result' ),
			)
		);
	}

	/**
	 * Evaluation (R0-08 §6.2 "Bewertung"): answers count only up to
	 * deadline + grace; unanswered = 0; pass = total and all parts.
	 *
	 * @param array<string, mixed> $row      Session row.
	 * @param array<string, mixed> $snapshot  Snapshot.
	 * @param array<int, int>      $overrides Manual points per position (E-74).
	 * @return array{points_milli: int, max_points_milli: int, passed: bool, expired: bool, correct: int, total: int, sections: array<int, array{points: int, max: int}>, parts: list<array<string, mixed>>, questions: list<array{position: int, correct: bool, points_milli: int, max_points_milli: int, answered: bool}>}
	 */
	private function evaluate( array $row, array $snapshot, array $overrides = array() ): array {
		$cutoff   = null === $row['deadline_at'] ? null : gmdate( 'Y-m-d H:i:s', (int) strtotime( $row['deadline_at'] . ' UTC' ) + (int) $snapshot['timing']['grace_seconds'] );
		$scoring  = array_column( (array) $snapshot['scoring'], null, 'position' );
		$points   = 0;
		$max      = 0;
		$correct  = 0;
		$sections = array();
		$per      = array();
		$parts    = self::snapshot_parts( $snapshot );
		$tally    = array();
		foreach ( $parts as $part ) {
			$tally[ $part['key'] ] = array(
				'points'  => 0,
				'max'     => 0,
				'correct' => 0,
				'total'   => 0,
			);
		}
		$questions = $this->session_questions( (int) $row['id'] );
		// All revisions with one batched read (R0-14 OP-07: no query per question without object cache).
		$revisions = $this->questions->revisions( array_map( static fn( array $sq ): int => (int) $sq['revision_id'], $questions ) );
		foreach ( $questions as $sq ) {
			if ( '1' === (string) $sq['neutralized'] ) {
				continue;
			}
			$position = (int) $sq['position'];
			$meta     = $scoring[ $position ] ?? array(
				'points'  => 1,
				'scoring' => null,
				'section' => 0,
			);
			$revision = (array) ( $revisions[ (int) $sq['revision_id'] ] ?? $this->questions->revision( (int) $sq['revision_id'] ) );
			if ( QuestionTypes::OPEN_TEXT === $revision['question_type'] && empty( $snapshot['open_text_in_score'] ) ) {
				continue;
			}
			$weight  = 1000 * (int) $meta['points'];
			$max    += $weight;
			$got     = 0;
			$counted = null !== $sq['answered_at'] && null !== $sq['given_answer'] && ( null === $cutoff || (string) $sq['answered_at'] <= $cutoff );
			if ( isset( $overrides[ $position ] ) ) {
				$got = min( $weight, max( 0, (int) $overrides[ $position ] ) );
			} elseif ( $counted ) {
				$evaluation = QuestionTypes::evaluate( $revision, json_decode( (string) $sq['given_answer'], true ), array_values( array_map( 'strval', (array) json_decode( (string) $sq['answer_order'], true ) ) ), $weight, $meta['scoring'] );
				$got        = $evaluation->points_milli;
			}
			if ( $got === $weight ) {
				++$correct;
			}
			$per[]                          = array(
				'position'         => $position,
				'correct'          => $got === $weight,
				'points_milli'     => $got,
				'max_points_milli' => $weight,
				'answered'         => $counted,
			);
			$points                        += $got;
			$section                        = (int) $meta['section'];
			$sections[ $section ]['points'] = ( $sections[ $section ]['points'] ?? 0 ) + $got;
			$sections[ $section ]['max']    = ( $sections[ $section ]['max'] ?? 0 ) + $weight;
			// Part of the question: frozen `question_parts` (E-331), else the first part covering the section (snapshots before E-331).
			$key = isset( $snapshot['question_parts'] ) && is_array( $snapshot['question_parts'] )
				? ( isset( $snapshot['question_parts'][ $position ] ) ? (string) $snapshot['question_parts'][ $position ] : null )
				: ExamRules::part_of_section( $parts, $section );
			if ( null !== $key && isset( $tally[ $key ] ) ) {
				$tally[ $key ]['points']  += $got;
				$tally[ $key ]['max']     += $weight;
				$tally[ $key ]['correct'] += $got === $weight ? 1 : 0;
				++$tally[ $key ]['total'];
			}
		}
		$ratio        = (array) $snapshot['passing_rule']['min_ratio'];
		$passed       = $max > 0 && $points * (int) $ratio['den'] >= (int) $ratio['num'] * $max;
		$empty        = 'fail' !== ( $snapshot['passing_rule']['empty_part_policy'] ?? 'pass' );
		$result_parts = array();
		foreach ( $parts as $part ) {
			$t              = $tally[ $part['key'] ];
			$need           = $part['min_ratio'];
			$ok             = 0 === $t['total'] ? $empty : $t['points'] * $need['den'] >= $need['num'] * $t['max'];
			$passed         = $passed && $ok;
			$result_parts[] = array(
				'key'       => $part['key'],
				'label'     => $part['label'],
				'sections'  => $part['sections'],
				'min_ratio' => $need,
				'points'    => $t['points'],
				'max'       => $t['max'],
				'correct'   => $t['correct'],
				'total'     => $t['total'],
				'empty'     => 0 === $t['total'],
				'passed'    => $ok,
			);
		}
		foreach ( array_keys( $sections ) as $section ) {
			$sections[ $section ]['title'] = isset( $snapshot['section_labels'][ $section ] ) ? (string) $snapshot['section_labels'][ $section ] : null;
		}
		return array(
			'points_milli'     => $points,
			'max_points_milli' => $max,
			'passed'           => $passed,
			'expired'          => null !== $row['deadline_at'] && $this->now_sql() >= (string) $row['deadline_at'],
			'correct'          => $correct,
			'total'            => count( $per ),
			'sections'         => $sections,
			'parts'            => $result_parts,
			'questions'        => $per,
		);
	}

	/**
	 * Parts of the snapshot: the frozen list (E-331) or, for snapshots
	 * before E-331, the parts of the frozen passing rule.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return list<array{key: string, label: string|null, sections: list<int>, min_ratio: array{num: int, den: int}}>
	 */
	private static function snapshot_parts( array $snapshot ): array {
		if ( isset( $snapshot['parts'] ) && is_array( $snapshot['parts'] ) ) {
			$out = array();
			foreach ( $snapshot['parts'] as $part ) {
				$part  = (array) $part;
				$out[] = array(
					'key'       => (string) $part['key'],
					'label'     => isset( $part['label'] ) ? (string) $part['label'] : null,
					'sections'  => array_values( array_map( 'intval', (array) ( $part['sections'] ?? array() ) ) ),
					'min_ratio' => array(
						'num' => (int) $part['min_ratio']['num'],
						'den' => (int) $part['min_ratio']['den'],
					),
				);
			}
			return $out;
		}
		return ExamRules::parts( (array) ( $snapshot['passing_rule'] ?? array() ) );
	}

	/**
	 * Parts frozen at the start with their labels: the rule label, else the
	 * module titles of the part joined with " + " (R0-08 §6.3).
	 *
	 * @param array<string, mixed>                   $passing Passing rule.
	 * @param list<array{section: int|string}|array> $items   Questions of the attempt.
	 * @return list<array{key: string, label: string|null, sections: list<int>, min_ratio: array{num: int, den: int}}>
	 */
	private static function frozen_parts( array $passing, array $items ): array {
		$labels = self::section_labels( $items );
		$parts  = ExamRules::parts( $passing );
		foreach ( $parts as $i => $part ) {
			if ( null !== $part['label'] ) {
				continue;
			}
			$names = array();
			foreach ( $part['sections'] as $section ) {
				$names[] = $labels[ $section ] ?? wp_strip_all_tags( get_the_title( $section ) );
			}
			$parts[ $i ]['label'] = implode( ' + ', $names );
		}
		return $parts;
	}

	/**
	 * Locked write on one session question.
	 *
	 * @param Actor    $actor    Learner.
	 * @param int      $session  Session.
	 * @param int      $position Position.
	 * @param callable $change   fn(session row, question row, bounded answer time): array of columns.
	 * @param int      $age_ms   Age of a client-buffered change in milliseconds.
	 * @return array{applied: bool}
	 * @throws CourseException With `ccap_exam_*` codes.
	 */
	private function mutate( Actor $actor, int $session, int $position, callable $change, int $age_ms = 0 ): array {
		// One read of the session for the expiry check, ownership and the policy decisions (R0-14 OP-06).
		$row = $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		if ( null !== $row && 'in_progress' === $row['status'] && $this->is_overdue( $row ) ) {
			$this->finish( $session, 'expired' );
			$row = $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		}
		$this->owned( $actor, $session, true, $row );
		return $this->db->transaction(
			function ( Database $db ) use ( $session, $position, $change, $age_ms ): array {
				$table  = $db->table( 'ccap_exam_session' );
				$qtable = $db->table( 'ccap_exam_session_question' );
				// Session and position locked by one statement (R0-14 OP-06); session columns carry the prefix `s__`.
				$locked = (array) $db->row( $db->prepare( "SELECT sq.*, s.status AS s__status, s.end_reason AS s__end_reason, s.snapshot AS s__snapshot, s.deadline_at AS s__deadline_at, s.started_at AS s__started_at, s.row_version AS s__row_version FROM `{$table}` s LEFT JOIN `{$qtable}` sq ON sq.session_id = s.id AND sq.position = %d WHERE s.id = %d FOR UPDATE", $position, $session ) );
				$row    = array();
				$sq     = array();
				foreach ( $locked as $column => $value ) {
					if ( str_starts_with( (string) $column, 's__' ) ) {
						$row[ substr( (string) $column, 3 ) ] = $value;
					} else {
						$sq[ $column ] = $value;
					}
				}
				self::assert_running( $row );
				$snapshot = (array) json_decode( (string) $row['snapshot'], true );
				// Offline buffer (§6.4): the answer time is bounded by the frozen late tolerance; device time is not trusted beyond it.
				$tolerance = null === $row['deadline_at'] ? 0 : (int) ( $snapshot['timing']['late_tolerance_seconds'] ?? 120 );
				$at        = $this->now() - min( max( 0, intdiv( $age_ms, 1000 ) ), $tolerance );
				$started   = (int) strtotime( (string) $row['started_at'] . ' UTC' );
				$at        = max( $at, $started );
				if ( null !== $row['deadline_at'] && $at > (int) strtotime( $row['deadline_at'] . ' UTC' ) + (int) ( $snapshot['timing']['grace_seconds'] ?? 5 ) ) {
					throw new CourseException( 'ccap_exam_expired', 'The time is over.', 410 );
				}
				if ( null === ( $sq['id'] ?? null ) ) {
					throw new CourseException( 'ccap_not_found', 'Unknown position.', 404 );
				}
				$columns = $change( $row, $sq, $at );
				if ( array() === $columns ) {
					return array( 'applied' => false );
				}
				$sets   = array();
				$values = array();
				foreach ( $columns as $column => $value ) {
					Database::assert_identifier( $column );
					if ( null === $value ) {
						$sets[] = "`{$column}` = NULL";
						continue;
					}
					$sets[]   = "`{$column}` = " . ( is_int( $value ) ? '%d' : '%s' );
					$values[] = $value;
				}
				$values[] = (int) $sq['id'];
				$db->execute( $db->prepare( "UPDATE `{$qtable}` SET " . implode( ', ', $sets ) . ' WHERE id = %d', ...$values ) );
				if ( ! $db->compare_and_swap( 'ccap_exam_session', $session, (int) $row['row_version'], array( 'status' => 'in_progress' ) ) ) {
					throw new CourseException( 'ccap_exam_conflict', 'Concurrent change.', 409 );
				}
				return array( 'applied' => true );
			}
		);
	}

	/**
	 * Running or the catalog code of the end state (R0-19).
	 *
	 * @param array<string, mixed> $row Session row.
	 * @return void
	 * @throws CourseException 410/409.
	 */
	private static function assert_running( array $row ): void {
		if ( 'in_progress' === $row['status'] ) {
			return;
		}
		if ( 'expired' === $row['end_reason'] ) {
			throw new CourseException( 'ccap_exam_expired', 'The time is over.', 410 );
		}
		if ( 'cancelled' === $row['status'] ) {
			throw new CourseException( 'ccap_exam_aborted', 'The attempt was cancelled.', 409 );
		}
		throw new CourseException( 'ccap_exam_already_submitted', 'The attempt is already submitted.', 409 );
	}

	/**
	 * Own session or 404; for writes also M-08 (access now, R-05).
	 *
	 * @param Actor                     $actor   Learner.
	 * @param int                       $session Session.
	 * @param bool                      $write   Write access needed.
	 * @param array<string, mixed>|null $row     Session row just read by the caller (null = read here).
	 * @return array<string, mixed>
	 * @throws CourseException 404/403.
	 */
	private function owned( Actor $actor, int $session, bool $write, ?array $row = null ): array {
		$row = $row ?? $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		// The policy decides on the row read here (one read per operation, R0-14 OP-06/OP-07).
		return $this->policy->with_row(
			'ccap_exam_session',
			$session,
			$row,
			function () use ( $actor, $session, $write, $row ): array {
				$decision = $this->policy->decide( $actor, PolicyService::VIEW_ATTEMPT, array( $session ) );
				if ( null === $row || (int) $row['user_id'] !== $actor->user_id || ! $decision->allowed ) {
					throw new CourseException( 'ccap_not_found', 'Unknown attempt.', 404 );
				}
				if ( $write && 'in_progress' === $row['status'] ) {
					CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::USE_ATTEMPT, array( $session ) ), 'No access to this attempt.' );
				}
				return $row;
			}
		);
	}

	/**
	 * Max attempts and cooldown (§4.4, GM-210).
	 *
	 * @param int                  $user Learner.
	 * @param array<string, mixed> $rule Rule.
	 * @return void
	 * @throws CourseException `ccap_exam_attempts_exhausted`.
	 */
	private function assert_attempts_left( int $user, array $rule ): void {
		$policy  = (array) $rule['settings']['attempt_policy'];
		$table   = $this->db->table( 'ccap_exam_session' );
		$counted = empty( $policy['count_cancelled'] ) ? "status = 'evaluated'" : "(status = 'evaluated' OR (status = 'cancelled' AND end_reason NOT IN ('withdrawn', 'support_void')))";
		if ( null !== ( $policy['max_attempts'] ?? null ) ) {
			$used = (int) $this->db->scalar( $this->db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE user_id = %d AND rule_id = %d AND {$counted}", $user, (int) $rule['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed condition.
			if ( $used >= (int) $policy['max_attempts'] ) {
				throw new CourseException( 'ccap_exam_attempts_exhausted', 'No attempts left.', 422 );
			}
		}
		$cooldown = (int) ( $policy['cooldown_seconds'] ?? 0 );
		if ( $cooldown > 0 ) {
			$last = $this->db->scalar( $this->db->prepare( "SELECT MAX(submitted_at) FROM `{$table}` WHERE user_id = %d AND rule_id = %d AND status = 'evaluated' AND passed = 0", $user, (int) $rule['id'] ) );
			if ( null !== $last && (int) strtotime( $last . ' UTC' ) + $cooldown > $this->now() ) {
				throw new CourseException( 'ccap_exam_attempts_exhausted', 'cooldown', 422 );
			}
		}
	}

	/**
	 * Evaluate one session if it is overdue.
	 *
	 * @param int $session Session.
	 * @return void
	 */
	private function expire_if_due( int $session ): void {
		$row = $this->db->row( $this->db->prepare( 'SELECT id, status, snapshot, deadline_at FROM `' . $this->db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		if ( null !== $row && 'in_progress' === $row['status'] && $this->is_overdue( $row ) ) {
			$this->finish( $session, 'expired' );
		}
	}

	/**
	 * Past deadline + late tolerance (frozen in the snapshot).
	 *
	 * @param array<string, mixed> $row Session row with snapshot and deadline.
	 * @return bool
	 */
	private function is_overdue( array $row ): bool {
		if ( null === $row['deadline_at'] ) {
			return false;
		}
		$snapshot = (array) json_decode( (string) $row['snapshot'], true );
		return $this->now() > (int) strtotime( $row['deadline_at'] . ' UTC' ) + (int) ( $snapshot['timing']['late_tolerance_seconds'] ?? 120 );
	}

	/**
	 * Session questions in position order.
	 *
	 * @param int $session Session.
	 * @return list<array<string, mixed>>
	 */
	private function session_questions( int $session ): array {
		return $this->db->rows( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY position', $session ) );
	}

	/**
	 * Modules whose pool contains a question.
	 *
	 * @param int $question Question.
	 * @return list<int>
	 */
	private function modules_of_question( int $question ): array {
		$rows = $this->db->rows( $this->db->prepare( 'SELECT module_id FROM `' . $this->db->table( 'ccap_module_question' ) . '` WHERE question_id = %d ORDER BY module_id', $question ) );
		return array_map( static fn( array $r ): int => (int) $r['module_id'], $rows );
	}

	/**
	 * Frozen section labels (module titles at start).
	 *
	 * @param array<int, array<string, mixed>> $items Snapshot items.
	 * @return array<int, string>
	 */
	private static function section_labels( array $items ): array {
		$labels = array();
		foreach ( $items as $item ) {
			$section = (int) $item['section'];
			if ( $section > 0 && ! isset( $labels[ $section ] ) ) {
				$labels[ $section ] = wp_strip_all_tags( get_the_title( $section ) );
			}
		}
		return $labels;
	}

	/**
	 * Apply the stored answer order to a delivered payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @param array<int, mixed>    $order   Stored keys.
	 * @return array<string, mixed>
	 */
	private static function reorder( array $payload, array $order ): array {
		foreach ( array( 'options', 'right', 'items' ) as $list ) {
			if ( ! isset( $payload[ $list ] ) || array() === $order ) {
				continue;
			}
			$by_key = array_column( (array) $payload[ $list ], null, 'key' );
			$sorted = array();
			foreach ( $order as $key ) {
				if ( isset( $by_key[ (string) $key ] ) ) {
					$sorted[] = $by_key[ (string) $key ];
				}
			}
			$payload[ $list ] = $sorted;
		}
		return $payload;
	}

	/**
	 * Current Unix time.
	 *
	 * @return int
	 */
	private function now(): int {
		return $this->fixed_now ?? $this->db->time();
	}

	/**
	 * Current UTC time as SQL datetime.
	 *
	 * @return string
	 */
	private function now_sql(): string {
		return gmdate( 'Y-m-d H:i:s', $this->now() );
	}
}
