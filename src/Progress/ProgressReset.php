<?php
/**
 * Reset of a learner's course progress (R0-05 GM-078, R0-13 K-11, E-70, E-138).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Progress;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Deletes, in one transaction, the progress rows of one learner for one
 * course: course progress, activity progress of the course and question
 * progress of the questions in the course's module pools. Exam sessions,
 * attempts (answer history) and certificates stay (R0-08 §5.1, R0-23 §5).
 * The prior rows go to `ccap_reset_journal` (undo within 30 days, E-138,
 * R1-38); the audit entry `progress_reset` carries the scope and the row
 * counts. A reset by course staff needs `ccap_manage_learner_progress`
 * (#16, M-12) and a reason; a learner may reset their own progress
 * without a reason.
 */
final class ProgressReset {

	public const TABLES = array( 'ccap_course_progress', 'ccap_activity_progress', 'ccap_question_progress' );

	/**
	 * Constructor.
	 *
	 * @param Database      $db        Database access.
	 * @param PolicyService $policy    Policy.
	 * @param Relations     $relations Relations.
	 */
	public function __construct( private Database $db, private PolicyService $policy, private Relations $relations ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, PolicyService::instance(), new Relations( $db ) );
	}

	/**
	 * Reset the progress of a learner in a course.
	 *
	 * @param Actor       $actor     Acting user.
	 * @param int         $user_id   Learner.
	 * @param int         $course_id Course.
	 * @param string|null $reason    Justification (required unless self-reset).
	 * @return array{rows: array<string, int>, journal_id: int, audit_id: int}
	 * @throws CourseException 404 unknown course/learner, 403 without right, 422 without reason.
	 */
	public function reset_course( Actor $actor, int $user_id, int $course_id, ?string $reason = null ): array {
		if ( null === $this->relations->post( $course_id, Relations::COURSE_TYPE ) || $user_id <= 0 || false === get_userdata( $user_id ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course or learner.', 404 );
		}
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_PROGRESS, array( $user_id ) ), 'Not allowed to reset progress.' );
		$self   = $actor->user_id === $user_id;
		$reason = null === $reason ? '' : trim( $reason );
		if ( ! $self && '' === $reason ) {
			throw new CourseException( 'ccap_invalid_param', 'reason', 422 );
		}
		$result = $this->db->transaction(
			function ( Database $db ) use ( $actor, $user_id, $course_id, $reason, $self ): array {
				$where = $this->conditions( $db, $user_id, $course_id );
				$prior = array();
				$rows  = array();
				foreach ( self::TABLES as $table ) {
					$name            = $db->table( $table );
					$prior[ $table ] = $db->rows( "SELECT * FROM `{$name}` WHERE {$where[ $table ]} ORDER BY id FOR UPDATE" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared conditions.
					$rows[ $table ]  = $db->execute( "DELETE FROM `{$name}` WHERE {$where[ $table ]}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared conditions.
				}
				$audit   = AuditLog::write(
					$db,
					$actor,
					'progress_reset',
					'course',
					(string) $course_id,
					$self ? null : $reason,
					array(
						'scope' => 'course',
						'rows'  => $rows,
						'self'  => $self,
					),
					$user_id
				);
				$journal = $db->insert(
					'ccap_reset_journal',
					array(
						'user_id'       => $user_id,
						'course_id'     => $course_id,
						'object_type'   => 'course',
						'object_id'     => $course_id,
						'prior_state'   => (string) wp_json_encode( $prior ),
						'actor_user_id' => $actor->user_id > 0 ? $actor->user_id : null,
						'actor_role'    => $self ? 'learner' : 'staff',
						'audit_id'      => $audit,
						'created_at'    => $db->now(),
					)
				);
				return array(
					'rows'       => $rows,
					'journal_id' => $journal,
					'audit_id'   => $audit,
				);
			}
		);
		\CourseCaptain\Cache\CacheStore::bump_user( $user_id );
		return $result;
	}

	/**
	 * Prepared row conditions per table.
	 *
	 * @param Database $db        Database.
	 * @param int      $user_id   Learner.
	 * @param int      $course_id Course.
	 * @return array<string, string>
	 */
	private function conditions( Database $db, int $user_id, int $course_id ): array {
		$pool = $db->table( 'ccap_module_question' );
		$cm   = $db->table( 'ccap_course_module' );
		$aq   = $db->table( 'ccap_activity_question' );
		$post = $db->wpdb()->posts;
		$ids  = array_values(
			array_unique(
				array_map(
					'intval',
					array_merge(
						$db->wpdb()->get_col( $db->prepare( "SELECT p.question_id FROM `{$pool}` p JOIN `{$cm}` cm ON cm.module_id = p.module_id WHERE cm.course_id = %d", $course_id ) ),
						$db->wpdb()->get_col( $db->prepare( "SELECT aq.question_id FROM `{$aq}` aq JOIN `{$post}` l ON l.ID = aq.activity_id JOIN `{$cm}` cm ON cm.module_id = l.post_parent WHERE cm.course_id = %d", $course_id ) )
					)
				)
			)
		);
		return array(
			'ccap_course_progress'   => $db->prepare( 'user_id = %d AND course_id = %d', $user_id, $course_id ),
			'ccap_activity_progress' => $db->prepare( 'user_id = %d AND course_id = %d', $user_id, $course_id ),
			'ccap_question_progress' => array() === $ids ? '1 = 0' : $db->prepare( 'user_id = %d', $user_id ) . ' AND question_id IN (' . implode( ',', $ids ) . ')',
		);
	}
}
