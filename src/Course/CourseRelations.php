<?php
/**
 * Bundle relation "contains" (E-25a, E-297, E-298).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * A bundle course contains other courses, one level only: a bundle is
 * never contained in another bundle, a contained course is never itself a
 * bundle, no self reference. In R1 the only relation type is `contains`.
 */
final class CourseRelations {

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
	 * Add a contained course to a bundle (idempotent).
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $bundle_id Bundle course.
	 * @param int   $course_id Contained course.
	 * @return void
	 * @throws CourseException On invalid relations or missing rights.
	 */
	public function add( Actor $actor, int $bundle_id, int $course_id ): void {
		$decision = $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $bundle_id ) );
		CourseException::unless_allowed( $decision, 'Not allowed to edit this bundle.' );
		if ( null === $this->relations->post( $course_id, Relations::COURSE_TYPE ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		if ( $bundle_id === $course_id ) {
			throw new CourseException( 'ccap_bundle_invalid', 'A course cannot contain itself.', 422 );
		}
		if ( array() !== $this->contained( $course_id ) ) {
			throw new CourseException( 'ccap_bundle_invalid', 'A bundle cannot contain another bundle.', 422 );
		}
		if ( array() !== $this->bundles_of( $bundle_id ) ) {
			throw new CourseException( 'ccap_bundle_invalid', 'A contained course cannot become a bundle.', 422 );
		}
		$table = $this->db->table( 'ccap_course_relation' );
		$now   = $this->db->now();
		$this->db->execute(
			$this->db->prepare(
				"INSERT IGNORE INTO `{$table}` (course_id, related_course_id, relation_type, sort_order, created_by, created_at, updated_at) VALUES (%d, %d, 'contains', %d, %d, %s, %s)",
				$bundle_id,
				$course_id,
				count( $this->contained( $bundle_id ) ) * CourseStructure::SORT_STEP + CourseStructure::SORT_STEP,
				$actor->user_id,
				$now,
				$now
			)
		);
		\CourseCaptain\Cache\CacheStore::bump_course( $bundle_id );
		\CourseCaptain\Cache\CacheStore::bump_course( $course_id );
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * Remove a contained course.
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $bundle_id Bundle.
	 * @param int   $course_id Course.
	 * @return void
	 * @throws CourseException When denied.
	 */
	public function remove( Actor $actor, int $bundle_id, int $course_id ): void {
		$decision = $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $bundle_id ) );
		CourseException::unless_allowed( $decision, 'Not allowed to edit this bundle.' );
		$table = $this->db->table( 'ccap_course_relation' );
		$this->db->execute( $this->db->prepare( "DELETE FROM `{$table}` WHERE course_id = %d AND related_course_id = %d AND relation_type = 'contains'", $bundle_id, $course_id ) );
		\CourseCaptain\Cache\CacheStore::bump_course( $bundle_id );
		\CourseCaptain\Cache\CacheStore::bump_course( $course_id );
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * Courses contained in a bundle, in order.
	 *
	 * @param int $bundle_id Bundle.
	 * @return list<int>
	 */
	public function contained( int $bundle_id ): array {
		$table = $this->db->table( 'ccap_course_relation' );
		$rows  = $this->db->rows( $this->db->prepare( "SELECT related_course_id FROM `{$table}` WHERE course_id = %d AND relation_type = 'contains' ORDER BY sort_order, related_course_id", $bundle_id ) );
		return array_map( static fn( array $row ): int => (int) $row['related_course_id'], $rows );
	}

	/**
	 * Bundles containing a course.
	 *
	 * @param int $course_id Course.
	 * @return list<int>
	 */
	public function bundles_of( int $course_id ): array {
		$table = $this->db->table( 'ccap_course_relation' );
		$rows  = $this->db->rows( $this->db->prepare( "SELECT course_id FROM `{$table}` WHERE related_course_id = %d AND relation_type = 'contains' ORDER BY course_id", $course_id ) );
		return array_map( static fn( array $row ): int => (int) $row['course_id'], $rows );
	}
}
