<?php
/**
 * Assignment REST routes (R0-19 R-46, R-54…R-58; R1-46).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Assignment;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Learner\LearnerQueries;
use CourseCaptain\Learner\LearnerRoutes;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * All routes need a login (401 `ccap_auth_required`), answer with
 * `Cache-Control: private, no-store` and map `CourseException` incl. its
 * data (`reason`, `retry_after`, …). Lessons are addressed like R-27/R-28
 * (numeric ID), submissions by UID. Uploads accept only real PHP uploads
 * (`is_uploaded_file`); `Idempotency-Key` (or `request_key`) is mandatory
 * for grading and optional for uploads.
 */
final class AssignmentRoutes {

	private const UID    = '(?P<uid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})';
	private const COURSE = '(?P<course>[0-9a-f-]+)';

	/**
	 * Hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		$service = static fn(): AssignmentService => AssignmentService::current();
		$routes  = array(
			// R-54.
			array( '/courses/' . self::COURSE . '/assignments/(?P<lesson>\d+)', 'GET', static fn( Actor $a, \WP_REST_Request $r ) => $service()->view( $a, self::course( $r ), (int) $r['lesson'] ), array() ),
			// R-55.
			array(
				'/courses/' . self::COURSE . '/assignments/(?P<lesson>\d+)/draft',
				'PUT',
				static function ( Actor $a, \WP_REST_Request $r ) use ( $service ): array {
					$match = $r->get_header( 'if_match' );
					$match = null === $match || '' === trim( $match, ' "W/' ) ? null : (int) trim( $match, ' "W/' );
					$text  = $r->get_param( 'text' );
					return $service()->save_draft( $a, self::course( $r ), (int) $r['lesson'], null === $text ? null : (string) $text, (string) ( $r->get_param( 'submission_uid' ) ?? '' ), $match );
				},
				array(
					'text'           => array( 'type' => 'string' ),
					'submission_uid' => array( 'type' => 'string' ),
				),
			),
			// R-56.
			array(
				'/assignment-submissions/' . self::UID . '/files',
				'POST',
				static function ( Actor $a, \WP_REST_Request $r ) use ( $service ): \WP_REST_Response {
					$file = $r->get_file_params()['file'] ?? null;
					if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
						$code = is_array( $file ) ? (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) : UPLOAD_ERR_NO_FILE;
						throw new CourseException( 'ccap_submission_file_rejected', 'Upload failed.', 422, array( 'reason' => in_array( $code, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ? 'size' : 'upload' ) );
					}
					$tmp = (string) ( $file['tmp_name'] ?? '' );
					if ( ! self::is_upload( $tmp ) ) {
						throw new CourseException( 'ccap_submission_file_rejected', 'Not an uploaded file.', 422, array( 'reason' => 'upload' ) );
					}
					$result = $service()->add_file( $a, (string) $r['uid'], $tmp, (string) ( $file['name'] ?? '' ), (string) ( $file['type'] ?? '' ), self::key( $r ) );
					$status = $result['replayed'] ? 200 : 201;
					unset( $result['replayed'] );
					return new \WP_REST_Response( $result, $status );
				},
				array(),
			),
			// Addition to R-56: remove a file from the own draft.
			array( '/assignment-submissions/' . self::UID . '/files/(?P<media>[0-9a-f-]+)', 'DELETE', static fn( Actor $a, \WP_REST_Request $r ) => $service()->remove_file( $a, (string) $r['uid'], (string) $r['media'] ), array() ),
			// R-57.
			array(
				'/assignment-submissions/' . self::UID . '/submit',
				'POST',
				static function ( Actor $a, \WP_REST_Request $r ) use ( $service ): array {
					$result = $service()->submit( $a, (string) $r['uid'] );
					unset( $result['replayed'] );
					return $result;
				},
				array(),
			),
			// Addition: one version for its owner or a grader.
			array( '/assignment-submissions/' . self::UID, 'GET', static fn( Actor $a, \WP_REST_Request $r ) => $service()->detail( $a, (string) $r['uid'] ), array() ),
			// R-58.
			array(
				'/assignment-submissions/' . self::UID . '/grade',
				'PUT',
				static function ( Actor $a, \WP_REST_Request $r ) use ( $service ): array {
					$points = $r->get_param( 'points_milli' );
					$result = $service()->grade(
						$a,
						(string) $r['uid'],
						(string) $r->get_param( 'action' ),
						null === $points ? null : (int) $points,
						(string) ( $r->get_param( 'feedback' ) ?? '' ),
						(string) ( $r->get_param( 'reason' ) ?? '' ),
						(string) ( self::key( $r ) ?? '' )
					);
					unset( $result['replayed'] );
					return $result;
				},
				array(
					// grade|return; checked by the service (WP validates args before the login check).
					'action'       => array(
						'type'     => 'string',
						'required' => true,
					),
					'points_milli' => array( 'type' => 'integer' ),
					'feedback'     => array( 'type' => 'string' ),
					'reason'       => array( 'type' => 'string' ),
				),
			),
			// R-46.
			array(
				'/grading/queue',
				'GET',
				static function ( Actor $a, \WP_REST_Request $r ) use ( $service ): \WP_REST_Response {
					$course = (string) ( $r->get_param( 'course' ) ?? '' );
					$id     = '' === $course ? null : LearnerQueries::current()->course_id( $course );
					if ( null !== $id && $id <= 0 ) {
						throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
					}
					$page     = $service()->queue( $a, $id, max( 1, (int) ( $r->get_param( 'page' ) ?? 1 ) ), (int) ( $r->get_param( 'per_page' ) ?? 20 ) );
					$response = new \WP_REST_Response( $page['items'] );
					$response->header( 'X-WP-Total', (string) $page['total'] );
					$response->header( 'X-WP-TotalPages', (string) $page['pages'] );
					return $response;
				},
				array(
					'course'   => array( 'type' => 'string' ),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					),
				),
			),
		);
		foreach ( $routes as $route ) {
			list( $path, $method, $callback, $args ) = $route;
			register_rest_route(
				LearnerRoutes::NAMESPACE,
				$path,
				array(
					'methods'             => $method,
					'callback'            => static fn( \WP_REST_Request $r ) => self::run( $callback, $r ),
					'permission_callback' => array( LearnerRoutes::class, 'logged_in' ),
					'args'                => $args,
				)
			);
		}
	}

	/**
	 * Course parameter (ID or UID).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 * @throws CourseException 404.
	 */
	private static function course( \WP_REST_Request $request ): int {
		$id = LearnerQueries::current()->course_id( (string) $request['course'] );
		if ( $id <= 0 ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		return $id;
	}

	/**
	 * Idempotency key from the header or `request_key`.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string|null
	 */
	private static function key( \WP_REST_Request $request ): ?string {
		$key = (string) ( $request->get_header( 'idempotency_key' ) ?? $request->get_param( 'request_key' ) ?? '' );
		return '' === $key ? null : $key;
	}

	/**
	 * Real PHP upload (tests run with `COURSE_CAPTAIN_TESTING`).
	 *
	 * @param string $tmp Temporary path.
	 * @return bool
	 */
	public static function is_upload( string $tmp ): bool {
		if ( '' === $tmp || ! is_file( $tmp ) ) {
			return false;
		}
		return is_uploaded_file( $tmp ) || ( defined( 'COURSE_CAPTAIN_TESTING' ) && true === constant( 'COURSE_CAPTAIN_TESTING' ) && str_starts_with( (string) realpath( $tmp ), (string) realpath( get_temp_dir() ) ) );
	}

	/**
	 * Run and map errors; responses are never cached.
	 *
	 * @param callable         $callback fn(Actor, WP_REST_Request).
	 * @param \WP_REST_Request $request  Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private static function run( callable $callback, \WP_REST_Request $request ) {
		try {
			$result   = $callback( Actor::user( get_current_user_id() ), $request );
			$response = $result instanceof \WP_REST_Response ? $result : new \WP_REST_Response( $result );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		} catch ( CourseException $e ) {
			return new \WP_Error( $e->error_code, $e->getMessage(), array( 'status' => $e->status ) + $e->data );
		}
	}
}
