<?php
/**
 * Learner progress per activity (ccap_activity_progress, R0-08 §5.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Interactions write only the actor's own progress, after a learning
 * access check *now*. Completion is monotonic: once `completed`, later
 * interactions never reset it (R0-08 §5.1).
 */
final class ActivityProgress {

	/**
	 * Constructor.
	 *
	 * @param Database         $db        Database access.
	 * @param ActivityRegistry $registry  Registry.
	 * @param Relations        $relations Relations.
	 */
	public function __construct( private Database $db, private ActivityRegistry $registry, private Relations $relations ) {
	}

	/**
	 * Instance for the request.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, ActivityRegistry::instance(), new Relations( $db ) );
	}

	/**
	 * Record an interaction: `position` (seconds, optional), `confirm`,
	 * `view` (seconds), `download`.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param int                  $lesson_id Lesson (activity).
	 * @param string               $kind      Interaction kind.
	 * @param array<string, mixed> $payload   Payload.
	 * @return array<string, mixed> Stored state incl. `status`.
	 * @throws CourseException When not allowed or the lesson is not in the course.
	 */
	public function interact( Actor $actor, int $course_id, int $lesson_id, string $kind, array $payload = array() ): array {
		$lesson = $this->relations->post( $lesson_id, Relations::LESSON_TYPE );
		if ( null === $lesson || ! in_array( $course_id, $this->relations->courses_of_item( $lesson ), true ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown lesson.', 404 );
		}
		\CourseCaptain\Access\ContentGate::current()->lesson( $actor, $course_id, $lesson_id )->assert();
		$definition = ActivityService::definition( $lesson_id );
		$row        = $this->ensure_row( $actor->user_id, $course_id, $lesson_id, $definition['type'] );
		$data       = self::data_of( $row );
		$pos        = (int) $row['position_seconds'];
		switch ( $kind ) {
			case 'position':
				// GM-011: an update without position keeps the stored position.
				if ( isset( $payload['position_seconds'] ) && is_int( $payload['position_seconds'] ) && $payload['position_seconds'] >= 0 ) {
					$pos = $payload['position_seconds'];
				}
				break;
			case 'confirm':
				$data['confirmed'] = true;
				break;
			case 'view':
				$data['view_seconds'] = max( (int) ( $data['view_seconds'] ?? 0 ), (int) ( $payload['seconds'] ?? 0 ) );
				break;
			case 'download':
				$data['downloaded'] = true;
				break;
			case 'cards':
				// Card counts are always computed server-side below.
				break;
			default:
				throw new CourseException( 'ccap_invalid_param', 'Unknown interaction.', 400 );
		}

		if ( 'flashcard' === $definition['type'] ) {
			$data = \CourseCaptain\Learning\Flashcards::current()->counts( $actor->user_id, $lesson_id ) + $data;
		}
		$this->store( $actor->user_id, $course_id, $lesson, $definition, $row, $data, $pos );
		return (array) $this->state( $actor->user_id, $lesson_id );
	}

	/**
	 * Server-side evidence of a provider workflow (assignment `submitted`,
	 * `graded`, `passed`; R0-08 §2.5). No access check: the evidence was
	 * produced by a checked workflow (a grade stays effective after the
	 * learner lost access). Completion stays monotonic.
	 *
	 * @param int                 $user_id   Learner.
	 * @param int                 $course_id Course.
	 * @param int                 $lesson_id Lesson (activity).
	 * @param array<string, bool> $flags     Evidence flags merged into the progress data.
	 * @return array<string, mixed> Stored state.
	 */
	public function record_evidence( int $user_id, int $course_id, int $lesson_id, array $flags ): array {
		$lesson = $this->relations->post( $lesson_id, Relations::LESSON_TYPE );
		if ( null === $lesson || $user_id <= 0 ) {
			return array();
		}
		$definition = ActivityService::definition( $lesson_id );
		$row        = $this->ensure_row( $user_id, $course_id, $lesson_id, $definition['type'] );
		$this->store( $user_id, $course_id, $lesson, $definition, $row, $flags + self::data_of( $row ), (int) $row['position_seconds'] );
		return (array) $this->state( $user_id, $lesson_id );
	}

	/**
	 * Progress row, created as `started` when missing.
	 *
	 * @param int    $user_id   Learner.
	 * @param int    $course_id Course.
	 * @param int    $lesson_id Lesson.
	 * @param string $type      Activity type.
	 * @return array<string, mixed>
	 */
	private function ensure_row( int $user_id, int $course_id, int $lesson_id, string $type ): array {
		$table = $this->db->table( 'ccap_activity_progress' );
		$now   = $this->db->now();
		$this->db->execute(
			$this->db->prepare(
				"INSERT IGNORE INTO `{$table}` (user_id, activity_id, course_id, activity_type, status, last_access_at, created_at) VALUES (%d, %d, %d, %s, 'started', %s, %s)",
				$user_id,
				$lesson_id,
				$course_id,
				'' === $type ? 'lesson' : $type,
				$now,
				$now
			)
		);
		return (array) $this->state( $user_id, $lesson_id );
	}

	/**
	 * Decoded progress data.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return array<string, mixed>
	 */
	private static function data_of( array $row ): array {
		$data = json_decode( (string) ( $row['data'] ?? '' ), true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Evaluate the completion rule (monotonic) and store the row.
	 *
	 * @param int                  $user_id    Learner.
	 * @param int                  $course_id  Course.
	 * @param \WP_Post             $lesson     Lesson.
	 * @param array<string, mixed> $definition Activity definition.
	 * @param array<string, mixed> $row        Stored row.
	 * @param array<string, mixed> $data       New data.
	 * @param int                  $pos        Position.
	 * @return void
	 */
	private function store( int $user_id, int $course_id, \WP_Post $lesson, array $definition, array $row, array $data, int $pos ): void {
		$table  = $this->db->table( 'ccap_activity_progress' );
		$now    = $this->db->now();
		$status = (string) $row['status'];
		$reason = $row['reason_code'];
		if ( 'completed' !== $status ) {
			$provider = $this->registry->get( '' === $definition['type'] ? 'lesson' : $definition['type'] );
			$decision = null === $provider ? 'in_progress' : $provider->evaluate_completion(
				$definition['rule'],
				$data + array(
					'started'          => true,
					'position_seconds' => $pos,
					'duration_seconds' => (int) ( $definition['settings']['duration_seconds'] ?? 0 ),
				)
			);
			if ( 'completed' === $decision ) {
				$status = 'completed';
				$reason = (string) ( $definition['rule']['type'] ?? 'manual_confirm' );
			}
		}
		$this->db->execute(
			$this->db->prepare(
				"UPDATE `{$table}` SET position_seconds = %d, data = %s, status = %s, reason_code = %s, rule_version = %d, completed_at = IF(%s = 'completed' AND completed_at IS NULL, %s, completed_at), content_hash = IF(%s = 'completed' AND content_hash IS NULL, %s, content_hash), last_access_at = %s WHERE id = %d",
				$pos,
				(string) wp_json_encode( $data ),
				$status,
				(string) $reason,
				(int) ( $definition['rule']['rule_version'] ?? 1 ),
				$status,
				$now,
				$status,
				'sha256:' . hash( 'sha256', $lesson->post_title . "\n" . $lesson->post_content ),
				$now,
				(int) $row['id']
			)
		);
		/**
		 * Activity progress changed (course progress recalculates, R0-08 §5.1).
		 *
		 * @since 1.0.0
		 * @param int $user_id   Learner.
		 * @param int $course_id Course.
		 * @param int $lesson_id Lesson.
		 */
		do_action( 'course_captain_activity_progress_changed', $user_id, $course_id, (int) $lesson->ID );
	}

	/**
	 * Stored state.
	 *
	 * @param int $user_id   Learner.
	 * @param int $lesson_id Lesson.
	 * @return array<string, mixed>|null
	 */
	public function state( int $user_id, int $lesson_id ): ?array {
		$table = $this->db->table( 'ccap_activity_progress' );
		return $this->db->row( $this->db->prepare( "SELECT * FROM `{$table}` WHERE user_id = %d AND activity_id = %d", $user_id, $lesson_id ) );
	}
}
