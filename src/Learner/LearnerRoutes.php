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
use CourseCaptain\Exam\ExamSessions;
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
			array(
				'/courses/' . $course . '/exam-sessions',
				'POST',
				static function ( Actor $a, \WP_REST_Request $r ): \WP_REST_Response {
					$course_id = self::course( $r );
					$rule      = self::rule( $course_id, (string) ( $r->get_param( 'rule_uid' ) ?? $r->get_param( 'rule' ) ?? '' ) );
					$variant   = $r->get_param( 'variant' );
					$key       = (string) ( $r->get_param( 'request_key' ) ?? '' );
					if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{8,36}$/', $key ) ) {
						throw new CourseException( 'ccap_invalid_param', 'request_key', 400 );
					}
					$sessions = ExamSessions::current();
					$id       = $sessions->start( $a, $rule, null === $variant ? null : (int) $variant, $key );
					$db       = \CourseCaptain\Storage\Database::current();
					$fresh    = $key === (string) $db->scalar( $db->prepare( 'SELECT start_token FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $id ) );
					return new \WP_REST_Response( array( 'session' => $sessions->view( $a, $id ) ), $fresh ? 201 : 200 );
				},
			),
			array( '/exam-sessions/(?P<session>\d+)', 'GET', static fn( Actor $a, \WP_REST_Request $r ) => ExamSessions::current()->view( $a, (int) $r['session'] ) ),
			array( '/exam-sessions/(?P<session>\d+)/answers/(?P<position>\d+)', 'PUT', static fn( Actor $a, \WP_REST_Request $r ) => ExamSessions::current()->save_answer( $a, (int) $r['session'], (int) $r['position'], $r->get_param( 'answer' ), max( 0, (int) $r->get_param( 'age_ms' ) ) ) ),
			array(
				'/exam-sessions/(?P<session>\d+)/answers/(?P<position>\d+)/flag',
				'PUT',
				static function ( Actor $a, \WP_REST_Request $r ): array {
					$on = (bool) $r->get_param( 'flagged' );
					ExamSessions::current()->flag( $a, (int) $r['session'], (int) $r['position'], $on );
					return array( 'flagged' => $on );
				},
			),
			array( '/exam-sessions/(?P<session>\d+)/answers/(?P<position>\d+)/reveal', 'POST', static fn( Actor $a, \WP_REST_Request $r ) => array( 'model_answer' => ExamSessions::current()->reveal( $a, (int) $r['session'], (int) $r['position'] ) ) ),
			array( '/exam-sessions/(?P<session>\d+)/submit', 'POST', static fn( Actor $a, \WP_REST_Request $r ) => ExamSessions::current()->submit( $a, (int) $r['session'] ) ),
			array(
				'/me/privacy-requests',
				'POST',
				static function ( Actor $a, \WP_REST_Request $r ): \WP_REST_Response {
					$result = \CourseCaptain\Privacy\SelfService::request( $a, (string) $r->get_param( 'type' ) );
					return new \WP_REST_Response( array( 'status' => $result['status'] ), 202 );
				},
			),
			array(
				'/me/data-exports',
				'POST',
				static function ( Actor $a ): \WP_REST_Response {
					$result = \CourseCaptain\Privacy\DataExport::request( $a );
					return new \WP_REST_Response( $result['export'], 202 );
				},
			),
			array( '/me/data-exports/(?P<uid>[0-9a-f-]{36})', 'GET', static fn( Actor $a, \WP_REST_Request $r ) => \CourseCaptain\Privacy\DataExport::get( $a, (string) $r['uid'] ) ),
			array( '/courses/' . $course . '/faqs', 'GET', static fn( Actor $a, \WP_REST_Request $r ) => \CourseCaptain\Course\FaqService::for_course( $a, self::course( $r ) ) ),
			array( '/exam-sessions/(?P<session>\d+)/abort', 'POST', static fn( Actor $a, \WP_REST_Request $r ) => array( 'status' => ExamSessions::current()->cancel( $a, (int) $r['session'] ) ) ),
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
	 * Exam rule of this course by UID or ID (foreign rules are 404).
	 *
	 * @param int    $course_id Course.
	 * @param string $reference UID or numeric ID.
	 * @return int
	 * @throws CourseException 404.
	 */
	private static function rule( int $course_id, string $reference ): int {
		$db    = \CourseCaptain\Storage\Database::current();
		$table = $db->table( 'ccap_exam_rule' );
		$id    = ctype_digit( $reference )
			? (int) $db->scalar( $db->prepare( "SELECT id FROM `{$table}` WHERE id = %d AND course_id = %d", (int) $reference, $course_id ) )
			: (int) $db->scalar( $db->prepare( "SELECT id FROM `{$table}` WHERE uid = %s AND course_id = %d", $reference, $course_id ) );
		if ( $id <= 0 ) {
			throw new CourseException( 'ccap_not_found', 'Unknown exam.', 404 );
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
			$result   = $callback( Actor::user( get_current_user_id() ), $request );
			$response = $result instanceof \WP_REST_Response ? $result : new \WP_REST_Response( $result );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		} catch ( CourseException $e ) {
			return new \WP_Error( $e->error_code, $e->getMessage(), array( 'status' => $e->status ) + $e->data );
		}
	}
}
