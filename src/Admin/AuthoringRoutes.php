<?php
/**
 * REST routes of the authoring screens (R0-19 namespace `course-captain/v1`).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Cookie authentication with the `wp_rest` nonce (core); every route checks
 * login in `permission_callback` and the object right in the service, so
 * the HTTP variants return the same 401/403/404 as the PHP API.
 */
final class AuthoringRoutes {

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
	 * Register routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		$auth = array( self::class, 'logged_in' );
		register_rest_route(
			self::NAMESPACE,
			'/courses/(?P<id>\d+)/structure',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => AuthoringService::current()->structure( $a, (int) $r['id'] ) ),
					'permission_callback' => $auth,
				),
				array(
					'methods'             => 'PUT',
					'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => AuthoringService::current()->update_structure( $a, (int) $r['id'], array_values( (array) $r->get_param( 'modules' ) ) ) ),
					'permission_callback' => $auth,
					'args'                => array(
						'modules' => array(
							'type'     => 'array',
							'required' => true,
						),
					),
				),
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/courses/(?P<id>\d+)/publish',
			array(
				'methods'             => 'POST',
				'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => array( 'revision_id' => AuthoringService::current()->publish( $a, (int) $r['id'], (string) ( $r->get_param( 'change_class' ) ?? 'minor' ), null === $r->get_param( 'notes' ) ? null : (string) $r->get_param( 'notes' ) ) ) ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/lessons/(?P<id>\d+)/activity',
			array(
				'methods'             => 'PUT',
				'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => AuthoringService::current()->define_activity( $a, (int) $r['id'], (string) $r->get_param( 'type' ), (array) ( $r->get_param( 'settings' ) ?? array() ), (array) ( $r->get_param( 'rule' ) ?? array() ) ) ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			self::NAMESPACE,
			'/items/(?P<id>\d+)/duplicate',
			array(
				'methods'             => 'POST',
				'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => array( 'id' => AuthoringService::current()->duplicate( $a, (int) $r['id'] ) ), 201 ),
				'permission_callback' => $auth,
			)
		);
	}

	/**
	 * Logged-in check (401 otherwise).
	 *
	 * @return bool|\WP_Error
	 */
	public static function logged_in() {
		return is_user_logged_in() ? true : new \WP_Error( 'ccap_auth_required', __( 'Please log in.', 'course-captain' ), array( 'status' => 401 ) );
	}

	/**
	 * Run a service call and map domain errors.
	 *
	 * @param callable $call   fn(Actor): mixed.
	 * @param int      $status Success status.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private static function run( callable $call, int $status = 200 ) {
		try {
			return new \WP_REST_Response( $call( Actor::user( get_current_user_id() ) ), $status );
		} catch ( CourseException $e ) {
			return new \WP_Error( $e->error_code, $e->getMessage(), array( 'status' => $e->status ) );
		}
	}
}
