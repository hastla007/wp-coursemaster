<?php
/**
 * Error training sets (R0-05 GM-070, GM-071).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learning;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Two neutral sets: questions whose latest learning answer in the course
 * was wrong (mode `wrong`, oldest mistake first), and the wrongly answered
 * questions of an own evaluated exam (exam answers never change the
 * learning state; the set is only a practice selection). Both are cut to
 * the currently accessible scope.
 */
final class ErrorTraining {

	/**
	 * Constructor.
	 *
	 * @param Database  $db        Database access.
	 * @param Favorites $favorites Scope check helper.
	 */
	public function __construct( private Database $db, private Favorites $favorites ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), Favorites::current() );
	}

	/**
	 * Mode `wrong`: latest learning answer wrong.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return list<int>
	 */
	public function wrong( Actor $actor, int $course_id ): array {
		$table = $this->db->table( 'ccap_attempt' );
		$ids   = $this->db->wpdb()->get_col(
			$this->db->prepare(
				"SELECT a.question_id FROM `{$table}` a JOIN (SELECT question_id, MAX(id) AS last_id FROM `{$table}` WHERE user_id = %d AND course_id = %d AND is_correct IS NOT NULL GROUP BY question_id) l ON l.last_id = a.id WHERE a.is_correct = 0 ORDER BY a.created_at, a.id",
				$actor->user_id,
				$course_id
			)
		);
		return $this->in_scope( $actor, $course_id, array_map( 'intval', $ids ) );
	}

	/**
	 * Wrong questions of an own evaluated exam (GM-071).
	 *
	 * @param Actor $actor   Learner.
	 * @param int   $session Exam session.
	 * @return list<int>
	 * @throws CourseException 404 foreign, 409 running.
	 */
	public function from_exam( Actor $actor, int $session ): array {
		$row = $this->db->row( $this->db->prepare( 'SELECT user_id, course_id, status, result FROM `' . $this->db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		if ( null === $row || (int) $row['user_id'] !== $actor->user_id ) {
			throw new CourseException( 'ccap_not_found', 'Unknown attempt.', 404 );
		}
		if ( 'in_progress' === $row['status'] ) {
			throw new CourseException( 'ccap_exam_running', 'The attempt is still running.', 409 );
		}
		if ( 'evaluated' !== $row['status'] ) {
			return array();
		}
		$wrong = array();
		foreach ( (array) ( json_decode( (string) $row['result'], true )['questions'] ?? array() ) as $q ) {
			if ( is_array( $q ) && empty( $q['correct'] ) ) {
				$wrong[] = (int) $q['position'];
			}
		}
		if ( array() === $wrong ) {
			return array();
		}
		$positions = implode( ',', $wrong );
		$ids       = $this->db->wpdb()->get_col( $this->db->prepare( 'SELECT question_id FROM `' . $this->db->table( 'ccap_exam_session_question' ) . "` WHERE session_id = %d AND position IN ({$positions}) ORDER BY position", $session ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		return $this->in_scope( $actor, (int) $row['course_id'], array_map( 'intval', $ids ) );
	}

	/**
	 * Keep questions of the accessible scope.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param array $ids       Question IDs.
	 * @phpstan-param list<int> $ids
	 * @return list<int>
	 */
	private function in_scope( Actor $actor, int $course_id, array $ids ): array {
		return array_values( array_unique( array_filter( $ids, fn( int $id ): bool => $this->favorites->accessible( $actor, $course_id, $id ) ) ) );
	}
}
