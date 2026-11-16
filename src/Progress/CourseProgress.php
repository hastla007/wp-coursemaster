<?php
/**
 * Materialized course completion (R0-08 §5.1, R0-06 `ccap_course_progress`).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Progress;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Event\Outbox;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Completion is computed against the structure of the learner's course
 * revision (`course_revision_id`, frozen at the first progress write),
 * never against live posts:
 *
 * - module done ⇔ all required activities of the module completed;
 * - course done ⇔ all required modules done (independent of access);
 * - `completion_ratio` = completed / required activities of the
 *   **accessible** modules (E-286), placeholders and unavailable
 *   activities excluded (GM-009);
 * - once completed, the course stays completed (`completed_revision_id`,
 *   monotonic, E-70); `course.completed` is recorded exactly once per cycle.
 */
final class CourseProgress {

	public const RULE_VERSION = 1;

	/**
	 * Constructor.
	 *
	 * @param Database  $db        Database access.
	 * @param Relations $relations Relations.
	 */
	public function __construct( private Database $db, private Relations $relations ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, new Relations( $db ) );
	}

	/**
	 * Hook: recalculate after every activity progress change.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'course_captain_activity_progress_changed',
			static function ( $user_id, $course_id ): void {
				self::current()->recalculate( (int) $user_id, (int) $course_id );
			},
			10,
			2
		);
		add_action( CronHooks::TASK_CONTINUE, array( self::class, 'run_rebuild' ), 10, 2 );
	}

	/**
	 * Recalculate and store the course progress of a learner.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return array{status: string, completion_ratio: string, mastery_ratio: string|null, activities_done: int, activities_total: int, course_revision_id: int|null, completed_revision_id: int|null, modules: list<array{module_id: int, required: bool, accessible: bool, done: int, total: int, completed: bool}>}
	 */
	public function recalculate( int $user_id, int $course_id ): array {
		return $this->db->transaction(
			function ( Database $db ) use ( $user_id, $course_id ): array {
				$table    = $db->table( 'ccap_course_progress' );
				$now      = $db->now();
				$revision = $this->current_revision_id( $course_id );
				$db->execute(
					$db->prepare(
						"INSERT IGNORE INTO `{$table}` (user_id, course_id, course_revision_id, status, updated_at) VALUES (%d, %d, %s, 'not_started', %s)",
						$user_id,
						$course_id,
						null === $revision ? null : (string) $revision,
						$now
					)
				);
				$row = (array) $db->row( $db->prepare( "SELECT * FROM `{$table}` WHERE user_id = %d AND course_id = %d FOR UPDATE", $user_id, $course_id ) );
				if ( null === $row['course_revision_id'] && null !== $revision ) {
					$row['course_revision_id'] = $revision;
				}
				$calc   = $this->calculate( $user_id, $course_id, null === $row['course_revision_id'] ? null : (int) $row['course_revision_id'] );
				$was    = (string) $row['status'];
				$status = 'completed' === $was ? 'completed' : $calc['status'];
				$ratio  = $calc['total'] > 0 ? sprintf( '%.4f', floor( $calc['done'] * 10000 / $calc['total'] ) / 10000 ) : '0.0000';
				$first  = 'completed' === $status && 'completed' !== $was;
				$db->compare_and_swap(
					'ccap_course_progress',
					(int) $row['id'],
					(int) $row['row_version'],
					array(
						'course_revision_id'    => $row['course_revision_id'],
						'status'                => $status,
						'completion_ratio'      => $ratio,
						'mastery_ratio'         => $calc['mastery'],
						'activities_done'       => $calc['done'],
						'activities_total'      => $calc['total'],
						'started_at'            => $row['started_at'] ?? ( 'not_started' === $status ? null : $now ),
						'completed_at'          => $first ? $now : $row['completed_at'],
						'completed_revision_id' => $first ? $row['course_revision_id'] : $row['completed_revision_id'],
						'last_activity_at'      => 'not_started' === $status ? $row['last_activity_at'] : $now,
						'updated_at'            => $now,
					)
				);
				if ( $first ) {
					$this->record_completed( $db, $user_id, $course_id, $row, $now );
				}
				return array(
					'status'                => $status,
					'completion_ratio'      => $ratio,
					'mastery_ratio'         => $calc['mastery'],
					'activities_done'       => $calc['done'],
					'activities_total'      => $calc['total'],
					'course_revision_id'    => null === $row['course_revision_id'] ? null : (int) $row['course_revision_id'],
					'completed_revision_id' => $first ? (int) $row['course_revision_id'] : ( null === $row['completed_revision_id'] ? null : (int) $row['completed_revision_id'] ),
					'modules'               => $calc['modules'],
				);
			}
		);
	}

	/**
	 * Stored progress row.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return array<string, mixed>|null
	 */
	public function get( int $user_id, int $course_id ): ?array {
		return $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_course_progress' ) . '` WHERE user_id = %d AND course_id = %d', $user_id, $course_id ) );
	}

	/**
	 * Pure calculation against a revision structure.
	 *
	 * @param int      $user_id   Learner.
	 * @param int      $course_id Course.
	 * @param int|null $revision  Course revision.
	 * @return array{status: string, done: int, total: int, mastery: string|null, modules: list<array{module_id: int, required: bool, accessible: bool, done: int, total: int, completed: bool}>}
	 */
	private function calculate( int $user_id, int $course_id, ?int $revision ): array {
		$structure = null === $revision ? null : $this->structure( $revision );
		if ( null === $structure ) {
			return array(
				'status'  => 'not_started',
				'done'    => 0,
				'total'   => 0,
				'mastery' => null,
				'modules' => array(),
			);
		}
		$completed = array_flip(
			array_map(
				'intval',
				$this->db->wpdb()->get_col( $this->db->prepare( 'SELECT activity_id FROM `' . $this->db->table( 'ccap_activity_progress' ) . "` WHERE user_id = %d AND status = 'completed'", $user_id ) )
			)
		);
		$started   = (int) $this->db->scalar( $this->db->prepare( 'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_activity_progress' ) . '` WHERE user_id = %d AND course_id = %d', $user_id, $course_id ) ) > 0;
		$done      = 0;
		$total     = 0;
		$all       = true;
		$modules   = array();
		foreach ( (array) ( $structure['modules'] ?? array() ) as $module ) {
			$module     = (array) $module;
			$module_id  = (int) $module['module_id'];
			$required   = ! empty( $module['required'] );
			$accessible = 'ok' === $this->relations->effective_access( $user_id, $course_id, $module_id, 'view' );
			$m_done     = 0;
			$m_total    = 0;
			foreach ( (array) ( $module['lessons'] ?? array() ) as $lesson ) {
				$lesson = (array) $lesson;
				if ( empty( $lesson['required'] ) || ! self::counts( $lesson ) ) {
					continue;
				}
				++$m_total;
				if ( isset( $completed[ (int) $lesson['lesson_id'] ] ) ) {
					++$m_done;
				}
			}
			$module_done = $m_done === $m_total;
			if ( $required && ! $module_done ) {
				$all = false;
			}
			if ( $required && $accessible ) {
				$done  += $m_done;
				$total += $m_total;
			}
			$modules[] = array(
				'module_id'  => $module_id,
				'required'   => $required,
				'accessible' => $accessible,
				'done'       => $m_done,
				'total'      => $m_total,
				'completed'  => $module_done,
			);
		}
		$has_required = array() !== array_filter( $modules, static fn( array $m ): bool => $m['required'] && $m['total'] > 0 );
		$status       = $all && $has_required ? 'completed' : ( $started ? 'in_progress' : 'not_started' );
		return array(
			'status'  => $status,
			'done'    => $done,
			'total'   => $total,
			'mastery' => $this->mastery( $user_id, array_column( array_filter( $modules, static fn( array $m ): bool => $m['accessible'] ), 'module_id' ) ),
			'modules' => $modules,
		);
	}

	/**
	 * Mastery aggregate: mastered / questions of the unlocked modules
	 * (R0-08 §5.2 `summaryFrom`); null without questions. Flashcards have
	 * their own status range (E-21) and do not count.
	 *
	 * @param int   $user_id Learner.
	 * @param array $modules Accessible modules.
	 * @phpstan-param list<int> $modules
	 * @return string|null
	 */
	private function mastery( int $user_id, array $modules ): ?string {
		if ( array() === $modules ) {
			return null;
		}
		$ids      = implode( ',', array_map( 'intval', $modules ) );
		$pool     = $this->db->table( 'ccap_module_question' );
		$progress = $this->db->table( 'ccap_question_progress' );
		$question = $this->db->table( 'ccap_question' );
		$revision = $this->db->table( 'ccap_question_revision' );
		$total    = (int) $this->db->scalar( "SELECT COUNT(DISTINCT p.question_id) FROM `{$pool}` p JOIN `{$question}` q ON q.id = p.question_id JOIN `{$revision}` r ON r.id = q.current_revision_id WHERE p.module_id IN ({$ids}) AND r.question_type <> 'flashcard'" );
		if ( 0 === $total ) {
			return null;
		}
		$mastered = (int) $this->db->scalar( $this->db->prepare( "SELECT COUNT(DISTINCT p.question_id) FROM `{$pool}` p JOIN `{$question}` q ON q.id = p.question_id JOIN `{$revision}` r ON r.id = q.current_revision_id JOIN `{$progress}` g ON g.question_id = p.question_id AND g.user_id = %d WHERE p.module_id IN ({$ids}) AND r.question_type <> 'flashcard' AND g.learn_status = 'mastered'", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		return sprintf( '%.4f', floor( $mastered * 10000 / $total ) / 10000 );
	}

	/**
	 * Rebuild job: recalculate a batch of learners of a course (after
	 * resets, repairs or a structure import). Returns the last user ID of
	 * the batch, 0 when done.
	 *
	 * @param int $course_id Course.
	 * @param int $after     Continue after this user ID.
	 * @param int $batch     Batch size.
	 * @return int
	 */
	public function rebuild( int $course_id, int $after = 0, int $batch = 200 ): int {
		$users = array_map(
			'intval',
			$this->db->wpdb()->get_col(
				$this->db->prepare(
					'SELECT user_id FROM (SELECT user_id FROM `' . $this->db->table( 'ccap_course_progress' ) . '` WHERE course_id = %d UNION SELECT user_id FROM `' . $this->db->table( 'ccap_activity_progress' ) . '` WHERE course_id = %d) u WHERE user_id > %d ORDER BY user_id LIMIT %d',
					$course_id,
					$course_id,
					$after,
					max( 1, $batch )
				)
			)
		);
		foreach ( $users as $user ) {
			$this->recalculate( $user, $course_id );
		}
		return count( $users ) < $batch ? 0 : (int) end( $users );
	}

	/**
	 * `ccap_task_continue` handler for task `progress_rebuild:<course>`
	 * (R0-19 §4.8: single event +60 s, arguments `[task, cursor]`).
	 *
	 * @param mixed $task   Task name.
	 * @param mixed $cursor Last user ID.
	 * @return void
	 */
	public static function run_rebuild( $task, $cursor = 0 ): void {
		if ( ! is_string( $task ) || 1 !== preg_match( '/^progress_rebuild:(\d+)$/', $task, $m ) ) {
			return;
		}
		$next = self::current()->rebuild( (int) $m[1], (int) $cursor );
		if ( $next > 0 ) {
			wp_schedule_single_event( time() + 60, CronHooks::TASK_CONTINUE, array( $task, $next ) );
		}
	}

	/**
	 * Schedule a rebuild of a course.
	 *
	 * @param int $course_id Course.
	 * @return void
	 */
	public static function schedule_rebuild( int $course_id ): void {
		wp_schedule_single_event( time() + 60, CronHooks::TASK_CONTINUE, array( 'progress_rebuild:' . $course_id, 0 ) );
	}

	/**
	 * Whether a frozen lesson counts for completion (GM-009: no
	 * placeholders, no unavailable provider, no rule `none`).
	 *
	 * @param array<string, mixed> $lesson Frozen lesson.
	 * @return bool
	 */
	public static function counts( array $lesson ): bool {
		$activity = (array) ( $lesson['activity'] ?? array() );
		if ( array_key_exists( 'available', $activity ) && false === $activity['available'] ) {
			return false;
		}
		if ( 'none' === ( $activity['completion_rule']['type'] ?? '' ) ) {
			return false;
		}
		$settings = ActivityService::definition( (int) ( $lesson['lesson_id'] ?? 0 ) )['settings'];
		return empty( $settings['placeholder'] );
	}

	/**
	 * Current published revision ID.
	 *
	 * @param int $course_id Course.
	 * @return int|null
	 */
	private function current_revision_id( int $course_id ): ?int {
		$id = $this->db->scalar( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_course_revision' ) . "` WHERE course_id = %d AND status = 'published' ORDER BY revision_no DESC LIMIT 1", $course_id ) );
		return null === $id ? null : (int) $id;
	}

	/**
	 * Structure of a revision.
	 *
	 * @param int $revision Revision.
	 * @return array<string, mixed>|null
	 */
	private function structure( int $revision ): ?array {
		$json = $this->db->scalar( $this->db->prepare( 'SELECT structure FROM `' . $this->db->table( 'ccap_course_revision' ) . '` WHERE id = %d', $revision ) );
		$data = null === $json ? null : json_decode( $json, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * `course.completed` (R0-19 §5, stable R1), once per cycle.
	 *
	 * @param Database             $db        Database in transaction.
	 * @param int                  $user_id   Learner.
	 * @param int                  $course_id Course.
	 * @param array<string, mixed> $row       Progress row.
	 * @param string               $now       Completion time.
	 * @return void
	 */
	private function record_completed( Database $db, int $user_id, int $course_id, array $row, string $now ): void {
		$revision_no = (int) $db->scalar( $db->prepare( 'SELECT revision_no FROM `' . $db->table( 'ccap_course_revision' ) . '` WHERE id = %d', (int) $row['course_revision_id'] ) );
		$course_uid  = (string) ( $this->structure( (int) $row['course_revision_id'] )['course']['uid'] ?? '' );
		$object      = $course_id . ':' . $user_id . ':' . (int) $row['cycle_no'];
		Outbox::record(
			$db,
			'course.completed',
			'course_completion',
			$object,
			(int) $row['cycle_no'],
			array(
				'completed_at'       => $now,
				'course_revision_no' => $revision_no,
				'rule_version'       => self::RULE_VERSION,
				'evidence'           => array(),
			),
			array(
				'user_id'         => $user_id,
				'course_uid'      => '' === $course_uid ? null : $course_uid,
				'actor_type'      => 'system',
				'idempotency_key' => 'course.completed:' . $object . ':' . (int) $row['cycle_no'],
				'privacy_tags'    => array( 'personal_ref', 'learning_progress' ),
			)
		);
	}
}
