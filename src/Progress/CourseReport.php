<?php
/**
 * Progress report per course (R0-05 GM-079, R0-07 RG-15, #12).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Progress;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * One row per learner with learning progress in the course (course,
 * activity or question progress): the stage counts of the smart trainer
 * (`summaryFrom`, GM-075) over the learner's unlocked scope and the course
 * completion. Columns are minimised (no e-mail, no answers).
 *
 * Rights: `ccap_view_reports` (#12). Site-wide staff (course manager,
 * admin) see every learner; a trainer only the courses assigned to them
 * (B-2) and, when the assignment is limited to groups, only learners with
 * an active seat in those groups (B-3). Anything else is 403.
 */
final class CourseReport {

	/**
	 * Constructor.
	 *
	 * @param Database     $db        Database access.
	 * @param Relations    $relations Relations.
	 * @param SmartTrainer $trainer  Smart trainer (stage counts).
	 */
	public function __construct( private Database $db, private Relations $relations, private SmartTrainer $trainer ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, new Relations( $db ), SmartTrainer::current() );
	}

	/**
	 * Report rows ordered by user ID.
	 *
	 * @param Actor $viewer    Viewer.
	 * @param int   $course_id Course.
	 * @return list<array{user_id: int, display_name: string, stages: array{total: int, new: int, unsure: int, learning: int, secure: int, mastered: int, due: int, completed: int, percent: int|null, secure_percent: int|null}, progress: array{status: string, completion_percent: int, last_activity_at: string|null}}>
	 * @throws CourseException 404 for unknown courses, 403 without right or assignment.
	 */
	public function rows( Actor $viewer, int $course_id ): array {
		if ( null === $this->relations->post( $course_id, Relations::COURSE_TYPE ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		$scope = $this->scope( $viewer, $course_id );
		$out   = array();
		foreach ( $this->learners( $course_id ) as $learner ) {
			if ( null !== $scope && ! $this->in_groups( $learner, $scope ) ) {
				continue;
			}
			$user = get_userdata( $learner );
			if ( false === $user ) {
				continue;
			}
			$stages = $this->trainer->summary( Actor::user( $learner ), $course_id );
			unset( $stages['topics'] );
			$progress = $this->db->row( $this->db->prepare( 'SELECT status, completion_ratio, last_activity_at FROM `' . $this->db->table( 'ccap_course_progress' ) . '` WHERE user_id = %d AND course_id = %d', $learner, $course_id ) );
			$out[]    = array(
				'user_id'      => $learner,
				'display_name' => (string) $user->display_name,
				'stages'       => $stages,
				'progress'     => array(
					'status'             => null === $progress ? 'not_started' : (string) $progress['status'],
					'completion_percent' => null === $progress ? 0 : (int) floor( (float) $progress['completion_ratio'] * 100 ),
					'last_activity_at'   => null === $progress || null === $progress['last_activity_at'] ? null : (string) $progress['last_activity_at'],
				),
			);
		}
		return $out;
	}

	/**
	 * Viewer scope: null = all learners, list = group IDs (B-3).
	 *
	 * @param Actor $viewer    Viewer.
	 * @param int   $course_id Course.
	 * @return list<int>|null
	 * @throws CourseException 403.
	 */
	private function scope( Actor $viewer, int $course_id ): ?array {
		if ( $viewer->user_id <= 0 || ! user_can( $viewer->user_id, 'ccap_view_reports' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to view reports.', 403 );
		}
		if ( PolicyService::has_site_wide_learner_scope( $viewer->user_id ) ) {
			return null;
		}
		$groups = $this->relations->trainer_groups( $viewer->user_id, $course_id );
		if ( array() === $groups ) {
			throw new CourseException( 'ccap_forbidden', 'Course not assigned.', 403 );
		}
		return array( 0 ) === $groups ? null : $groups;
	}

	/**
	 * Learners with any progress in the course.
	 *
	 * @param int $course_id Course.
	 * @return list<int>
	 */
	private function learners( int $course_id ): array {
		$cp   = $this->db->table( 'ccap_course_progress' );
		$ap   = $this->db->table( 'ccap_activity_progress' );
		$qp   = $this->db->table( 'ccap_question_progress' );
		$pool = $this->db->table( 'ccap_module_question' );
		$cm   = $this->db->table( 'ccap_course_module' );
		$ids  = $this->db->wpdb()->get_col(
			$this->db->prepare(
				"SELECT user_id FROM `{$cp}` WHERE course_id = %d UNION SELECT user_id FROM `{$ap}` WHERE course_id = %d UNION SELECT qp.user_id FROM `{$qp}` qp JOIN `{$pool}` p ON p.question_id = qp.question_id JOIN `{$cm}` cm ON cm.module_id = p.module_id AND cm.course_id = %d WHERE qp.attempts > 0",
				$course_id,
				$course_id,
				$course_id
			)
		);
		$ids  = array_values( array_unique( array_map( 'intval', $ids ) ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Active seat of the learner in one of the groups.
	 *
	 * @param int   $learner Learner.
	 * @param array $groups  Groups.
	 * @phpstan-param list<int> $groups
	 * @return bool
	 */
	private function in_groups( int $learner, array $groups ): bool {
		$in = implode( ',', array_map( 'intval', $groups ) );
		return null !== $this->db->scalar( $this->db->prepare( 'SELECT 1 FROM `' . $this->db->table( 'ccap_seat' ) . "` WHERE user_id = %d AND status = 'active' AND group_id IN ({$in}) LIMIT 1", $learner ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
	}
}
