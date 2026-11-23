<?php
/**
 * Application-level RESTRICT for CPTs (R0-06 §4.9).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Permanent deletion of a course, module or lesson is refused while
 * revisions, learning data, grants or certificates reference it. Trash and
 * archive stay possible. Content is never hard-deleted under history.
 */
final class DeletionGuard {

	/**
	 * Register the filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'pre_delete_post', array( self::class, 'filter' ), 10, 2 );
	}

	/**
	 * `pre_delete_post` callback: `false` short-circuits the deletion.
	 *
	 * @param mixed $delete Previous result.
	 * @param mixed $post   Post.
	 * @return mixed
	 */
	public static function filter( $delete, $post ) {
		if ( null !== $delete || ! $post instanceof \WP_Post || ! in_array( $post->post_type, array( Relations::COURSE_TYPE, Relations::MODULE_TYPE, Relations::LESSON_TYPE ), true ) ) {
			return $delete;
		}
		return self::is_protected( $post ) ? false : $delete;
	}

	/**
	 * Whether history references the object.
	 *
	 * @param \WP_Post $post Course, module or lesson.
	 * @return bool
	 */
	public static function is_protected( \WP_Post $post ): bool {
		$db        = Database::current();
		$relations = new Relations( $db );
		$id        = (int) $post->ID;
		if ( Relations::LESSON_TYPE === $post->post_type ) {
			foreach ( array( 'ccap_activity_progress', 'ccap_assignment_submission' ) as $table ) {
				if ( self::exists( $db, $table, 'activity_id', $id ) ) {
					return true;
				}
			}
		}
		if ( Relations::MODULE_TYPE === $post->post_type && self::exists( $db, 'ccap_exam_blueprint', 'module_id', $id ) ) {
			return true;
		}
		foreach ( $relations->courses_of_item( $post ) as $course ) {
			foreach ( array( 'ccap_course_revision', 'ccap_grant', 'ccap_course_progress', 'ccap_certificate', 'ccap_exam_session' ) as $table ) {
				if ( self::exists( $db, $table, 'course_id', $course ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Row existence check.
	 *
	 * @param Database $db     Database.
	 * @param string   $table  Table.
	 * @param string   $column Column.
	 * @param int      $id     Value.
	 * @return bool
	 */
	private static function exists( Database $db, string $table, string $column, int $id ): bool {
		Database::assert_identifier( $column );
		$name = $db->table( $table );
		return null !== $db->scalar( $db->prepare( "SELECT 1 FROM `{$name}` WHERE `{$column}` = %d LIMIT 1", $id ) );
	}
}
