<?php
/**
 * Certificate REST routes (R0-19 R-40, R-42, R0-29 Δ-9) and admin-post
 * entry points (CF-03, CF-09, CF-10).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Certificate;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Learner\LearnerRoutes;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * R-40 and Δ-9 are personal (`private, no-store`); R-42 is public with
 * `no-store` only, rate limited and without personal data except the
 * released name. The admin-post handlers are the domain entry points for
 * the management UI of R1-35.
 */
final class CertificateRoutes {

	private const UID = '(?P<uid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( 'admin_post_ccap_certificate_issue', array( self::class, 'handle_issue' ) );
		add_action( 'admin_post_ccap_certificate_correct', array( self::class, 'handle_correct' ) );
		add_action( 'admin_post_ccap_certificate_revoke', array( self::class, 'handle_revoke' ) );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		// Public verification of issued certificates stays available when the module is off (SET-CC-24).
		register_rest_route(
			LearnerRoutes::NAMESPACE,
			'/certificates/verify/(?P<token>[0-9a-zA-Z]{1,64})',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'verify' ),
				// Public route (R-42, "Ö"): token-based, rate limited, minimal fields.
				'permission_callback' => '__return_true',
			)
		);
		if ( ! \CourseCaptain\Settings\Modules::enabled( \CourseCaptain\Settings\Modules::CERTIFICATES ) ) {
			return;
		}
		register_rest_route(
			LearnerRoutes::NAMESPACE,
			'/me/certificates',
			array(
				'methods'             => 'GET',
				'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => CertificateService::current()->mine( $a ), $r ),
				'permission_callback' => array( LearnerRoutes::class, 'logged_in' ),
			)
		);
		register_rest_route(
			LearnerRoutes::NAMESPACE,
			'/me/certificates/' . self::UID . '/visibility',
			array(
				'methods'             => 'PUT',
				'callback'            => static fn( \WP_REST_Request $r ) => self::run(
					static fn( Actor $a, \WP_REST_Request $r ) => array( 'holder_name_public' => CertificateService::current()->set_name_public( $a, (string) $r['uid'], (bool) $r->get_param( 'holder_name_public' ) ) ),
					$r
				),
				'permission_callback' => array( LearnerRoutes::class, 'logged_in' ),
				'args'                => array(
					'holder_name_public' => array(
						'type'     => 'boolean',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * R-42.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function verify( \WP_REST_Request $request ) {
		try {
			$service = CertificateService::current();
			$service->rate_limit( \CourseCaptain\Security\ClientIp::rate_key() );
			$response = new \WP_REST_Response( $service->verify( (string) $request['token'] ) );
			$response->header( 'Cache-Control', 'no-store' );
			$response->header( 'X-Robots-Tag', 'noindex' );
			return $response;
		} catch ( CourseException $e ) {
			$data = array( 'status' => $e->status );
			if ( 'ccap_rate_limited' === $e->error_code ) {
				$data['details'] = array( 'bucket' => 'verify' );
			}
			return new \WP_Error( $e->error_code, $e->getMessage(), $data );
		}
	}

	/**
	 * Admin-post CF-03: `type`, `evidence_id`, `reason`.
	 *
	 * @return void
	 */
	public static function handle_issue(): void {
		check_admin_referer( 'ccap_certificate_issue' );
		self::admin(
			static fn( Actor $a ) => CertificateService::current()->issue( $a, self::field( 'type' ), (int) self::field( 'evidence_id' ), self::field( 'reason', true ) )['certificate_uid']
		);
	}

	/**
	 * Admin-post CF-09: `uid`, `reason`, `request_key`, `holder_name`/`course_title`/`refresh_evidence`.
	 *
	 * @return void
	 */
	public static function handle_correct(): void {
		check_admin_referer( 'ccap_certificate_correct' );
		self::admin(
			static function ( Actor $a ): string {
				$changes = array();
				foreach ( array( 'holder_name', 'course_title' ) as $key ) {
					if ( '' !== self::field( $key ) ) {
						$changes[ $key ] = self::field( $key );
					}
				}
				if ( '' !== self::field( 'refresh_evidence' ) ) {
					$changes['refresh_evidence'] = true;
				}
				return CertificateService::current()->correct( $a, self::field( 'uid' ), self::field( 'reason', true ), $changes, self::field( 'request_key' ) )['certificate_uid'];
			}
		);
	}

	/**
	 * Admin-post CF-10: `uid`, `category`, `reason`.
	 *
	 * @return void
	 */
	public static function handle_revoke(): void {
		check_admin_referer( 'ccap_certificate_revoke' );
		self::admin(
			static function ( Actor $a ): string {
				CertificateService::current()->revoke( $a, self::field( 'uid' ), self::field( 'category' ), self::field( 'reason', true ) );
				return self::field( 'uid' );
			}
		);
	}

	/**
	 * Run an admin action and redirect back with the result.
	 *
	 * @param callable $action fn(Actor): string.
	 * @return void
	 */
	private static function admin( callable $action ): void {
		try {
			\CourseCaptain\Settings\Modules::assert_enabled( \CourseCaptain\Settings\Modules::CERTIFICATES );
			$args = array(
				'ccap_certificate'        => (string) $action( Actor::user( get_current_user_id() ) ),
				'ccap_certificate_result' => 'ok',
			);
		} catch ( CourseException $e ) {
			$args = array( 'ccap_certificate_result' => $e->error_code );
		}
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( $args, false === $back ? admin_url() : $back ) );
		exit;
	}

	/**
	 * POST field.
	 *
	 * @param string $key       Key.
	 * @param bool   $multiline Textarea.
	 * @return string
	 */
	private static function field( string $key, bool $multiline = false ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in the handler.
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}
		$value = $multiline ? sanitize_textarea_field( wp_unslash( (string) $_POST[ $key ] ) ) : sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );
		// phpcs:enable
		return $value;
	}

	/**
	 * Run a personal route; never cached.
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
			return new \WP_Error( $e->error_code, $e->getMessage(), array( 'status' => $e->status ) + $e->data );
		}
	}
}
