<?php
/**
 * Content gate: one server-side unlock decision (R0-08 §1.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Decision;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluated on every read and write, first matching lock wins:
 *
 * 1. module access (E-27/E-286; grant, `free` rule) → `no_access`;
 *    without access, a `free_limited` rule of the requested kind grants
 *    partial access;
 * 2. partial access: only the first *n* elements per section (revision
 *    order) → `trial`; each kind has its own limit (E-332,
 *    {@see TrialLimits}): questions and flashcards per module, lessons
 *    per module, fixed variants and files of the course;
 * 3. drip: module unlocks *d* days after the access start (max of the
 *    rules; start = earliest active grant, else registration) → `drip`;
 * 4. prerequisites (`completed_module`, `completed_activity`,
 *    `passed_exam`) → `prerequisite` with `requires`;
 * 5. a running exam locks its questions in learning mode → `exam_running`.
 */
final class ContentGate {

	/**
	 * Injected clock (tests).
	 *
	 * @var int|null
	 */
	private static ?int $clock = null;

	/**
	 * Request memo of the access start per (user, course).
	 *
	 * @var array<string, int>
	 */
	private array $starts = array();

	/**
	 * Constructor.
	 *
	 * @param Database      $db     Database access.
	 * @param PolicyService $policy Policy.
	 */
	public function __construct( private Database $db, private PolicyService $policy ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), PolicyService::instance() );
	}

	/**
	 * Set the clock (tests only).
	 *
	 * @param int|null $now Unix time.
	 * @return void
	 */
	public static function set_now( ?int $now ): void {
		self::$clock = $now;
	}

	/**
	 * Gate of a module.
	 *
	 * The partial-access limit returned in `limit` is the one of `$kind`
	 * (E-332); `null` accepts a rule of any kind (course room, access
	 * state) and returns the largest limit.
	 *
	 * @param Actor       $actor     Learner.
	 * @param int         $course_id Course.
	 * @param int         $module_id Module.
	 * @param string      $scope     view|download|exam.
	 * @param string|null $kind      TrialLimits kind or null (any).
	 * @return GateDecision
	 */
	public function module( Actor $actor, int $course_id, int $module_id, string $scope = 'view', ?string $kind = TrialLimits::QUESTIONS ): GateDecision {
		$decision = $this->policy->decide(
			$actor,
			PolicyService::LEARN,
			array( $course_id ),
			array(
				'module' => $module_id,
				'scope'  => $scope,
			)
		);
		$refs     = array();
		$limit    = null;
		if ( ! $decision->allowed ) {
			$trial = 'view' === $scope && Decision::CONTENT_LOCKED === $decision->code ? $this->limited_rule( $course_id, $module_id, $kind ) : null;
			if ( null === $trial ) {
				return GateDecision::locked( 'no_access', array(), null, array(), $decision );
			}
			$refs[] = 'free_limited:' . $trial['id'];
			$limit  = $trial['n'];
		}
		$drip = $this->drip( $actor->user_id, $course_id, $module_id );
		if ( null !== $drip ) {
			return GateDecision::locked( 'drip', array( 'drip:' . $drip['id'] ), $drip['unlock_at'] );
		}
		$missing = $this->missing( $actor->user_id, $course_id, $this->module_rules( $course_id, $module_id ) );
		if ( array() !== $missing ) {
			return GateDecision::locked( 'prerequisite', array( 'module_rules:' . $module_id ), null, $missing );
		}
		return GateDecision::unlocked( $refs, $limit );
	}

	/**
	 * Gate of a lesson (activity): module gate, position under partial access, activity prerequisites.
	 *
	 * @param Actor  $actor     Learner.
	 * @param int    $course_id Course.
	 * @param int    $lesson_id Lesson.
	 * @param string $scope     Scope.
	 * @return GateDecision
	 */
	public function lesson( Actor $actor, int $course_id, int $lesson_id, string $scope = 'view' ): GateDecision {
		$module = (int) wp_get_post_parent_id( $lesson_id );
		$gate   = $this->module( $actor, $course_id, $module, $scope, TrialLimits::LESSONS );
		if ( ! $gate->allowed() ) {
			return $gate;
		}
		if ( null !== $gate->limit ) {
			$position = array_search( $lesson_id, $this->lessons( $module ), true );
			if ( false === $position || $position >= $gate->limit ) {
				return GateDecision::locked( 'trial', $gate->rule_refs );
			}
		}
		$rules   = (array) ( ActivityService::definition( $lesson_id )['settings']['prerequisites'] ?? array() );
		$missing = $this->missing( $actor->user_id, $course_id, $rules );
		if ( array() !== $missing ) {
			return GateDecision::locked( 'prerequisite', array( 'activity:' . $lesson_id ), null, $missing );
		}
		return $gate;
	}

	/**
	 * Gate of a pool question in learning mode.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param int   $module_id Module.
	 * @param int   $question  Question.
	 * @return GateDecision
	 */
	public function question( Actor $actor, int $course_id, int $module_id, int $question ): GateDecision {
		$gate = $this->module( $actor, $course_id, $module_id );
		if ( ! $gate->allowed() ) {
			return $gate;
		}
		if ( null !== $gate->limit ) {
			$position = array_search( $question, $this->pool( $module_id ), true );
			if ( false === $position || $position >= $gate->limit ) {
				return GateDecision::locked( 'trial', $gate->rule_refs );
			}
		}
		if ( in_array( $question, $this->running_exam_questions( $actor->user_id ), true ) ) {
			return GateDecision::locked( 'exam_running', array( 'exam_session' ) );
		}
		return $gate;
	}

	/**
	 * Gate of a flashcard of a deck lesson (GM-062, AR-06): the lesson gate,
	 * and under partial access only the first *n* cards of the deck with
	 * the question limit (flashcards are questions, E-332).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param int   $lesson_id Deck lesson.
	 * @param int   $question  Card (question).
	 * @param array $cards     Cards of the deck in order.
	 * @phpstan-param list<int> $cards
	 * @return GateDecision
	 */
	public function card( Actor $actor, int $course_id, int $lesson_id, int $question, array $cards ): GateDecision {
		$gate = $this->lesson( $actor, $course_id, $lesson_id );
		if ( ! $gate->allowed() || null === $gate->limit ) {
			return $gate;
		}
		$limit = $this->module( $actor, $course_id, (int) wp_get_post_parent_id( $lesson_id ), 'view', TrialLimits::QUESTIONS );
		if ( ! $limit->allowed() ) {
			return GateDecision::locked( 'trial', $gate->rule_refs );
		}
		$position = array_search( $question, $cards, true );
		if ( null !== $limit->limit && ( false === $position || $position >= $limit->limit ) ) {
			return GateDecision::locked( 'trial', $limit->rule_refs );
		}
		return $gate;
	}

	/**
	 * Gate of an exam over modules: all modules unlocked with scope `exam`
	 * (E-286, drip); partial access allows fixed variants up to *n* only and
	 * never random exams.
	 *
	 * @param Actor    $actor      Learner.
	 * @param int      $course_id  Course.
	 * @param array    $modules    Blueprint/variant modules.
	 * @param int|null $variant_no Variant number (null = random exam).
	 * @phpstan-param list<int> $modules
	 * @return GateDecision
	 */
	public function exam( Actor $actor, int $course_id, array $modules, ?int $variant_no = null ): GateDecision {
		$locked = array();
		$first  = null;
		foreach ( array_values( array_unique( $modules ) ) as $module ) {
			$gate = $this->module( $actor, $course_id, (int) $module, 'exam' );
			if ( ! $gate->allowed() ) {
				$locked[] = 'module:' . (int) $module;
				$first    = $first ?? $gate;
			}
		}
		if ( null === $first ) {
			return GateDecision::unlocked();
		}
		if ( 'no_access' === $first->reason ) {
			// Partial access: only fixed variants within the limit.
			$trial = array();
			foreach ( $modules as $module ) {
				$rule = $this->limited_rule( $course_id, (int) $module, TrialLimits::VARIANTS );
				if ( null === $rule ) {
					return GateDecision::locked( 'no_access', array(), null, $locked, $first->policy );
				}
				$trial[] = $rule;
			}
			$limits = array_column( $trial, 'n' );
			if ( array() === $limits ) {
				return GateDecision::locked( 'no_access', array(), null, $locked, $first->policy );
			}
			$n = min( $limits );
			if ( null === $variant_no || $variant_no > $n ) {
				return GateDecision::locked( 'trial', array_map( static fn( array $r ): string => 'free_limited:' . $r['id'], $trial ) );
			}
			return GateDecision::unlocked( array(), $n );
		}
		return GateDecision::locked( (string) $first->reason, $first->rule_refs, $first->unlock_at, $locked );
	}

	/**
	 * Gate of a protected file of a course (GM-015): listed for everyone,
	 * under partial access only the first *n* files of the course are free.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param int   $media_id  Medium.
	 * @return GateDecision
	 */
	public function file( Actor $actor, int $course_id, int $media_id ): GateDecision {
		$decision = $this->policy->decide( $actor, PolicyService::LEARN, array( $course_id ), array( 'scope' => 'download' ) );
		if ( $decision->allowed ) {
			return GateDecision::unlocked();
		}
		$rule = Decision::CONTENT_LOCKED === $decision->code ? $this->limited_rule( $course_id, 0, TrialLimits::FILES ) : null;
		if ( null === $rule ) {
			return GateDecision::locked( 'no_access', array(), null, array(), $decision );
		}
		$position = array_search( $media_id, $this->course_files( $course_id ), true );
		if ( false === $position || $position >= $rule['n'] ) {
			return GateDecision::locked( 'trial', array( 'free_limited:' . $rule['id'] ) );
		}
		return GateDecision::unlocked( array( 'free_limited:' . $rule['id'] ), $rule['n'] );
	}

	/**
	 * Protected files of a course in order (course, then modules, lessons).
	 *
	 * @param int $course_id Course.
	 * @return list<int>
	 */
	public function course_files( int $course_id ): array {
		$cm    = $this->db->table( 'ccap_content_media' );
		$media = $this->db->table( 'ccap_media' );
		$rows  = $this->db->rows(
			$this->db->prepare(
				"SELECT c.media_id FROM `{$cm}` c JOIN `{$media}` m ON m.id = c.media_id WHERE c.object_type = 'course' AND c.object_id = %d AND m.access = 'protected' AND m.status = 'active' ORDER BY c.sort_order, c.media_id",
				$course_id
			)
		);
		return array_values( array_unique( array_map( static fn( array $r ): int => (int) $r['media_id'], $rows ) ) );
	}

	/**
	 * Active `free_limited` rule of course/module for a kind (E-332): rules
	 * of that kind and legacy rules without a kind (`first_items`/NULL,
	 * one limit for every kind); the largest limit wins.
	 *
	 * @param int         $course_id Course.
	 * @param int         $module_id Module (0 = course).
	 * @param string|null $kind      TrialLimits kind or null (any).
	 * @return array{id: int, n: int}|null
	 */
	private function limited_rule( int $course_id, int $module_id, ?string $kind ): ?array {
		$now  = self::sql( self::now() );
		$kind = null !== $kind && isset( TrialLimits::KINDS[ $kind ] ) ? $kind : '';
		$row  = $this->db->row(
			$this->db->prepare(
				'SELECT id, limit_value FROM `' . $this->db->table( 'ccap_access_rule' ) . "` WHERE course_id = %d AND access_type = 'free_limited' AND (module_id IS NULL OR module_id = 0 OR module_id = %d) AND (%s = '' OR limit_type IS NULL OR limit_type = %s OR limit_type = %s) AND (valid_from IS NULL OR valid_from <= %s) AND (valid_until IS NULL OR valid_until > %s) ORDER BY limit_value DESC, id LIMIT 1",
				$course_id,
				$module_id,
				$kind,
				$kind,
				TrialLimits::LEGACY,
				$now,
				$now
			)
		);
		return null === $row ? null : array(
			'id' => (int) $row['id'],
			'n'  => max( 0, (int) $row['limit_value'] ),
		);
	}

	/**
	 * Drip lock of a module (max days of the rules).
	 *
	 * @param int $user      Learner.
	 * @param int $course_id Course.
	 * @param int $module_id Module.
	 * @return array{id: int, unlock_at: string}|null
	 */
	private function drip( int $user, int $course_id, int $module_id ): ?array {
		$row = $this->db->row( $this->db->prepare( 'SELECT id, limit_value FROM `' . $this->db->table( 'ccap_access_rule' ) . "` WHERE course_id = %d AND module_id = %d AND access_type = 'drip' ORDER BY limit_value DESC, id LIMIT 1", $course_id, $module_id ) );
		if ( null === $row ) {
			return null;
		}
		$unlock = $this->access_start( $user, $course_id ) + 86400 * max( 0, (int) $row['limit_value'] );
		return $unlock > self::now() ? array(
			'id'        => (int) $row['id'],
			'unlock_at' => self::sql( $unlock ),
		) : null;
	}

	/**
	 * Access start: earliest active grant (`valid_from`, else creation), else registration (memoized).
	 *
	 * @param int $user      Learner.
	 * @param int $course_id Course.
	 * @return int
	 */
	public function access_start( int $user, int $course_id ): int {
		$key = $user . ':' . $course_id;
		if ( ! isset( $this->starts[ $key ] ) ) {
			$first = $this->db->scalar( $this->db->prepare( 'SELECT MIN(COALESCE(valid_from, created_at)) FROM `' . $this->db->table( 'ccap_grant' ) . "` WHERE user_id = %d AND course_id = %d AND status = 'active'", $user, $course_id ) );
			if ( null === $first ) {
				$userdata = get_userdata( $user );
				$first    = false === $userdata ? null : (string) $userdata->user_registered;
			}
			$this->starts[ $key ] = null === $first ? 0 : (int) strtotime( $first . ' UTC' );
		}
		return $this->starts[ $key ];
	}

	/**
	 * Prerequisites of a module assignment.
	 *
	 * @param int $course_id Course.
	 * @param int $module_id Module.
	 * @return list<mixed>
	 */
	private function module_rules( int $course_id, int $module_id ): array {
		$json  = $this->db->scalar( $this->db->prepare( 'SELECT rules FROM `' . $this->db->table( 'ccap_course_module' ) . '` WHERE course_id = %d AND module_id = %d', $course_id, $module_id ) );
		$rules = null === $json ? null : json_decode( $json, true );
		return is_array( $rules ) ? array_values( (array) ( $rules['prerequisites'] ?? array() ) ) : array();
	}

	/**
	 * Unmet prerequisites as references.
	 *
	 * @param int          $user      Learner.
	 * @param int          $course_id Course.
	 * @param array<mixed> $rules    Rules.
	 * @return list<string>
	 */
	private function missing( int $user, int $course_id, array $rules ): array {
		$out = array();
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$type = (string) ( $rule['type'] ?? '' );
			$id   = (int) ( $rule['id'] ?? 0 );
			$met  = match ( $type ) {
				'completed_activity' => $this->activity_completed( $user, $id ),
				'completed_module'   => $this->module_completed( $user, $id ),
				'passed_exam'        => $this->exam_passed( $user, $course_id, $id ),
				default              => false,
			};
			if ( ! $met ) {
				$out[] = $type . ':' . $id;
			}
		}
		return $out;
	}

	/**
	 * Activity completed.
	 *
	 * @param int $user   Learner.
	 * @param int $lesson Lesson.
	 * @return bool
	 */
	private function activity_completed( int $user, int $lesson ): bool {
		return null !== $this->db->scalar( $this->db->prepare( 'SELECT 1 FROM `' . $this->db->table( 'ccap_activity_progress' ) . "` WHERE user_id = %d AND activity_id = %d AND status = 'completed'", $user, $lesson ) );
	}

	/**
	 * Module completed: all published lessons of the module completed.
	 *
	 * @param int $user   Learner.
	 * @param int $module Module.
	 * @return bool
	 */
	private function module_completed( int $user, int $module ): bool {
		$lessons = $this->lessons( $module );
		if ( array() === $lessons ) {
			return false;
		}
		foreach ( $lessons as $lesson ) {
			if ( ! $this->activity_completed( $user, $lesson ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Exam activity passed: an evaluated, passed session of the lesson's activity.
	 *
	 * @param int $user      Learner.
	 * @param int $course_id Course.
	 * @param int $lesson    Exam lesson.
	 * @return bool
	 */
	private function exam_passed( int $user, int $course_id, int $lesson ): bool {
		$uid = (string) get_post_meta( $lesson, '_ccap_activity_uid', true );
		if ( '' === $uid ) {
			return false;
		}
		return null !== $this->db->scalar( $this->db->prepare( 'SELECT 1 FROM `' . $this->db->table( 'ccap_exam_session' ) . "` WHERE user_id = %d AND course_id = %d AND activity_uid = %s AND status = 'evaluated' AND passed = 1 LIMIT 1", $user, $course_id, $uid ) );
	}

	/**
	 * Published lessons of a module in order.
	 *
	 * @param int $module Module.
	 * @return list<int>
	 */
	private function lessons( int $module ): array {
		$ids = get_posts(
			array(
				'post_type'        => Relations::LESSON_TYPE,
				'post_parent'      => $module,
				'post_status'      => 'publish',
				'orderby'          => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * Module pool in delivery order.
	 *
	 * @param int $module Module.
	 * @return list<int>
	 */
	private function pool( int $module ): array {
		return array_map( 'intval', $this->db->wpdb()->get_col( $this->db->prepare( 'SELECT question_id FROM `' . $this->db->table( 'ccap_module_question' ) . '` WHERE module_id = %d ORDER BY (sort_order IS NULL), sort_order, question_id', $module ) ) );
	}

	/**
	 * Questions locked by a running exam (deadline, without limit 24 h).
	 *
	 * @param int $user Learner.
	 * @return list<int>
	 */
	private function running_exam_questions( int $user ): array {
		return array_map(
			'intval',
			$this->db->wpdb()->get_col(
				$this->db->prepare(
					'SELECT sq.question_id FROM `' . $this->db->table( 'ccap_exam_session_question' ) . '` sq JOIN `' . $this->db->table( 'ccap_exam_session' ) . "` s ON s.id = sq.session_id WHERE s.user_id = %d AND s.status = 'in_progress' AND (s.deadline_at IS NOT NULL OR s.started_at > %s)",
					$user,
					self::sql( time() - \CourseCaptain\Exam\ExamSessions::UNLIMITED_LOCK )
				)
			)
		);
	}

	/**
	 * Now.
	 *
	 * @return int
	 */
	private static function now(): int {
		return self::$clock ?? time();
	}

	/**
	 * UTC SQL time.
	 *
	 * @param int $time Unix time.
	 * @return string
	 */
	private static function sql( int $time ): string {
		return gmdate( 'Y-m-d H:i:s', $time );
	}
}
