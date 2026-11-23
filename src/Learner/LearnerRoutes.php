<?php
/**
 * Learner REST routes (R0-19 R-05…R-13, R-26…R-28, R-31/R-32; R-33…R-37 in GroupOwnerRoutes).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Access\GroupService;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\Favorites;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Progress\CourseReport;
use CourseCaptain\Security\ClientIp;
use CourseCaptain\Storage\RateLimiter;

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
					$mode = (string) ( $r->get_param( 'mode' ) ?? 'smart' );
					if ( in_array( $mode, array( 'due', 'exam_mistakes' ), true ) ) {
						// UI-F3: own entries "Due" and "Practise exam mistakes".
						$entry = SmartTrainer::current()->entry( $a, self::course( $r ), $mode, array( 'exam_session' => $r->get_param( 'exam_session' ) ) );
						$extra = array_diff_key( $entry, array( 'question' => true ) );
						return null === $entry['question'] ? array( 'question' => null ) + $extra : $entry['question'] + $extra;
					}
					$options = array_filter(
						array(
							'topic'      => $r->get_param( 'topic' ),
							'collection' => $r->get_param( 'collection' ),
						),
						static fn( $v ): bool => null !== $v && '' !== $v
					);
					$next    = SmartTrainer::current()->next( $a, self::course( $r ), $mode, $options );
					return null === $next ? array( 'question' => null ) : $next;
				},
			),
			array(
				'/courses/' . $course . '/practice',
				'GET',
				static fn( Actor $a, \WP_REST_Request $r ) => SmartTrainer::current()->practice(
					$a,
					self::course( $r ),
					array_filter(
						array(
							'question' => $r->get_param( 'question' ),
							'module'   => $r->get_param( 'module' ),
						),
						static fn( $v ): bool => null !== $v
					)
				),
			),
			array(
				'/reports/courses/' . $course,
				'GET',
				static fn( Actor $a, \WP_REST_Request $r ) => array( 'learners' => CourseReport::current()->rows( $a, self::course( $r ) ) ),
			),
			array( '/answers', 'POST', static fn( Actor $a, \WP_REST_Request $r ) => AnswerService::current()->answer( $a, (string) $r->get_param( 'token' ), $r->get_param( 'answer' ), self::idempotency_key( $r ) ) ),
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
			// R-31: invitation preview (changes nothing).
			array( '/groups/join/(?P<token>[A-Za-z0-9_-]{1,64})', 'GET', static fn( Actor $a, \WP_REST_Request $r ) => self::join_preview( $a, (string) $r['token'] ) ),
			// R-32: accept an invitation or the group link.
			array( '/groups/join', 'POST', static fn( Actor $a, \WP_REST_Request $r ) => self::join( $a, $r->get_param( 'token' ) ) ),
			// Self-enrollment (L-02, UI-F2): `ccap_enrollment_closed` while the course enrollment is closed (E-108).
			array( '/courses/' . $course . '/enrollment', 'POST', static fn( Actor $a, \WP_REST_Request $r ) => \CourseCaptain\Access\SelfEnrollment::current()->enroll( $a, self::course( $r ) ) ),
		);
		// R-33…R-37: owner routes of the group area (UI-F1).
		GroupOwnerRoutes::routes();
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
	 * R-31: preview with masked data only (R0-09 §1.2).
	 *
	 * @param Actor  $actor Viewer.
	 * @param string $token Raw token.
	 * @return array{group: array{name: string}, course_titles: list<string>, seats_free: bool, bound_to_other_address: bool}
	 * @throws CourseException `ccap_invalid_token` 422, `ccap_invite_expired` 410, `ccap_group_not_active` 409, `ccap_rate_limited` 429.
	 */
	private static function join_preview( Actor $actor, string $token ): array {
		self::join_rate_limit( $actor, 'group_join_preview', 60, 120 );
		if ( null === Invitation::token( $token ) ) {
			throw new CourseException( 'ccap_invalid_token', 'Invalid invitation.', 422 );
		}
		$preview = GroupService::current()->preview( $actor, $token );
		if ( ! $preview['active'] ) {
			throw new CourseException( 'ccap_group_not_active', 'Group not active.', 409 );
		}
		return array(
			'group'                  => array( 'name' => $preview['group']['name'] ),
			'course_titles'          => $preview['course_titles'],
			'seats_free'             => $preview['seats_free'],
			'bound_to_other_address' => $preview['bound_to_other_address'],
		);
	}

	/**
	 * R-32: claim a seat; an existing membership returns the same answer (200).
	 *
	 * @param Actor $actor Learner.
	 * @param mixed $token Raw token.
	 * @return array{seat_id: int, group_uid: string, status: string}
	 * @throws CourseException Codes of R0-19 R-32.
	 */
	private static function join( Actor $actor, $token ): array {
		self::join_rate_limit( $actor, 'group_join', 10, 30 );
		$token = Invitation::token( $token );
		if ( null === $token ) {
			throw new CourseException( 'ccap_invalid_token', 'Invalid invitation.', 422 );
		}
		try {
			$seat = GroupService::current()->claim( $actor, $token );
		} catch ( CourseException $e ) {
			$map = array(
				'ccap_invalid_token'        => array( 'ccap_invalid_token', 422 ),
				'ccap_claim_used'           => array( 'ccap_invalid_token', 422 ),
				'ccap_claim_expired'        => array( 'ccap_invite_expired', 410 ),
				'ccap_invite_other_account' => array( 'ccap_invite_other_account', 403 ),
				'ccap_group_not_active'     => array( 'ccap_group_not_active', 409 ),
				'ccap_group_full'           => array( 'ccap_group_full', 409 ),
			);
			if ( ! isset( $map[ $e->error_code ] ) ) {
				throw $e;
			}
			throw new CourseException( $map[ $e->error_code ][0], 'Claim refused.', $map[ $e->error_code ][1] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Machine code for the entry point.
		}
		$db = \CourseCaptain\Storage\Database::current();
		return array(
			'seat_id'   => $seat,
			'group_uid' => (string) $db->scalar( $db->prepare( 'SELECT g.uid FROM `' . $db->table( 'ccap_group' ) . '` g JOIN `' . $db->table( 'ccap_seat' ) . '` s ON s.group_id = g.id WHERE s.id = %d', $seat ) ),
			'status'    => 'active',
		);
	}

	/**
	 * Rate limit per account and per IP (R0-09 §1.2 brute force; LB 10/account, 30/IP per hour for claims).
	 *
	 * @param Actor  $actor    Viewer.
	 * @param string $bucket   Bucket.
	 * @param int    $per_user Limit per account and hour.
	 * @param int    $per_ip   Limit per IP and hour.
	 * @return void
	 * @throws CourseException `ccap_rate_limited` 429.
	 */
	private static function join_rate_limit( Actor $actor, string $bucket, int $per_user, int $per_ip ): void {
		$db = \CourseCaptain\Storage\Database::current();
		RateLimiter::hit( $db, $bucket, $actor->user_id, HOUR_IN_SECONDS, $per_user, time() );
		RateLimiter::hit( $db, $bucket . '_ip', (int) sprintf( '%u', crc32( ClientIp::rate_key() ) ), HOUR_IN_SECONDS, $per_ip, time() );
	}

	/**
	 * Idempotency key: header `Idempotency-Key` (R0-19 §2.1), otherwise the
	 * body parameter `idempotency_key`.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	private static function idempotency_key( \WP_REST_Request $request ): string {
		$header = trim( (string) $request->get_header( 'idempotency_key' ) );
		return '' !== $header ? $header : (string) $request->get_param( 'idempotency_key' );
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
