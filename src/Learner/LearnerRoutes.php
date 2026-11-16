<?php
/**
 * Learner REST routes (R0-19 R-05…R-13, R-26…R-28).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\Favorites;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Courses are addressed by ID or UID. Responses carry `Cache-Control:
 * private, no-store` (personal data). Errors use the catalog codes.
 */
final class LearnerRoutes {

	public const NAMESPACE = 'course-captain/v1';

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
		$auth   = array( self::class, 'logged_in' );
		$course = '(?P<course>[0-9a-f-]+)';
		$routes = array(
			array( '/me/courses', 'GET', static fn( Actor $a ) => LearnerQueries::current()->my_courses( $a ) ),
			array( '/courses/' . $course, 'GET', static fn( Actor $a, \WP_REST_Request $r ) => LearnerQueries::current()->course( $a, self::course( $r ) ) ),
			array(
				'/courses/' . $course . '/lessons',
				'GET',
				static function ( Actor $a, \WP_REST_Request $r ): array {
					$lessons = array();
					foreach ( LearnerQueries::current()->course( $a, self::course( $r ) )['modules'] as $module ) {
						$lessons = array_merge( $lessons, $module['lessons'] );
					}
					return $lessons;
				},
			),
			array(
				'/courses/' . $course . '/next-question',
				'GET',
				static function ( Actor $a, \WP_REST_Request $r ) {
					$options = array_filter(
						array(
							'topic'      => $r->get_param( 'topic' ),
							'collection' => $r->get_param( 'collection' ),
						),
						static fn( $v ): bool => null !== $v && '' !== $v
					);
					$next    = SmartTrainer::current()->next( $a, self::course( $r ), (string) ( $r->get_param( 'mode' ) ?? 'smart' ), $options );
					return null === $next ? array( 'question' => null ) : $next;
				},
			),
			array( '/answers', 'POST', static fn( Actor $a, \WP_REST_Request $r ) => AnswerService::current()->answer( $a, (string) $r->get_param( 'token' ), $r->get_param( 'answer' ), (string) $r->get_param( 'idempotency_key' ) ) ),
			array( '/courses/' . $course . '/lessons/(?P<lesson>\d+)/progress', 'PUT', static fn( Actor $a, \WP_REST_Request $r ) => ActivityProgress::current()->interact( $a, self::course( $r ), (int) $r['lesson'], (string) $r->get_param( 'kind' ), (array) ( $r->get_param( 'payload' ) ?? array() ) ) ),
			array(
				'/courses/' . $course . '/favorites/(?P<question>\d+)',
				'PUT',
				static function ( Actor $a, \WP_REST_Request $r ): array {
					Favorites::current()->add( $a, self::course( $r ), (int) $r['question'] );
					return array( 'favorite' => true );
				},
			),
			array(
				'/courses/' . $course . '/favorites/(?P<question>\d+)',
				'DELETE',
				static function ( Actor $a, \WP_REST_Request $r ): array {
					Favorites::current()->remove( $a, (int) $r['question'] );
					return array( 'favorite' => false );
				},
			),
		);
		foreach ( $routes as $route ) {
			list( $path, $method, $callback ) = $route;
			register_rest_route(
				self::NAMESPACE,
				$path,
				array(
					'methods'             => $method,
					'callback'            => static fn( \WP_REST_Request $r ) => self::run( $callback, $r ),
					'permission_callback' => $auth,
				)
			);
		}
	}

	/**
	 * Logged in (401 otherwise).
	 *
	 * @return bool|\WP_Error
	 */
	public static function logged_in() {
		return is_user_logged_in() ? true : new \WP_Error( 'ccap_auth_required', __( 'Please log in.', 'course-captain' ), array( 'status' => 401 ) );
	}

	/**
	 * Course parameter.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int
	 * @throws CourseException 404 for unknown references.
	 */
	private static function course( \WP_REST_Request $request ): int {
		$id = LearnerQueries::current()->course_id( (string) $request['course'] );
		if ( $id <= 0 ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		return $id;
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
			$response = new \WP_REST_Response( $callback( Actor::user( get_current_user_id() ), $request ) );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		} catch ( CourseException $e ) {
			return new \WP_Error( $e->error_code, $e->getMessage(), array( 'status' => $e->status ) );
		}
	}
}
