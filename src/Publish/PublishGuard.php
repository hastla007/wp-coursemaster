<?php
/**
 * Every publish path runs through the validator (R0-22 §2.5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Publish;

use CourseCaptain\Policy\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * Publishing a course through the WordPress editor (status change in the
 * admin or the block editor's REST request) is kept as draft when the
 * validator reports blockers; the author sees the reason as a notice.
 * Internal writers (import activation, CLI, tests) use the course
 * publisher directly and are not affected here.
 */
final class PublishGuard {

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_insert_post_data', array( self::class, 'filter' ), 10, 2 );
	}

	/**
	 * `wp_insert_post_data`.
	 *
	 * @param mixed $data    Post data.
	 * @param mixed $postarr Raw input.
	 * @return mixed
	 */
	public static function filter( $data, $postarr ) {
		if ( ! is_array( $data ) || Relations::COURSE_TYPE !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) || ! self::interactive() ) {
			return $data;
		}
		$id = (int) ( is_array( $postarr ) ? ( $postarr['ID'] ?? 0 ) : 0 );
		if ( $id <= 0 || 'publish' === get_post_status( $id ) ) {
			return $data;
		}
		$report = CourseValidator::validate( $id, 'publish' );
		if ( $report['summary']['blocker'] > 0 ) {
			$data['post_status'] = 'draft';
			set_transient( 'ccap_editor_error_' . get_current_user_id(), __( 'Not published: the course check found blockers. Open "Publish revision" for details.', 'course-captain' ), 600 );
		}
		return $data;
	}

	/**
	 * Admin screen or REST request of the editor.
	 *
	 * @return bool
	 */
	private static function interactive(): bool {
		$rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
		/**
		 * Whether the publish guard applies to the current request (tests).
		 *
		 * @since 1.0.0
		 * @param bool $applies Admin or REST request.
		 */
		return (bool) apply_filters( 'course_captain_publish_guard_applies', is_admin() || $rest );
	}
}
