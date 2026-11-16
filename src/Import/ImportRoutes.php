<?php
/**
 * Import REST routes: progress, browser step, cancel, resume, report (R0-38 §2.4, §4.6, §4.7).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Learner\LearnerRoutes;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Logged-in routes (cookie nonce as every REST call); rights and foreign
 * batches are decided by the import policy (M-21: 403 without
 * `ccap_import_content`, 404 for unknown batches). Responses are
 * `private, no-store`. The CSV report is served raw with BOM.
 */
final class ImportRoutes {

	private const BASE = '/imports/(?P<id>\d+)';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_filter( 'rest_pre_serve_request', array( self::class, 'serve_csv' ), 10, 4 );
	}

	/**
	 * Routes.
	 *
	 * @return void
	 */
	public static function routes(): void {
		$auth = array( LearnerRoutes::class, 'logged_in' );
		register_rest_route(
			LearnerRoutes::NAMESPACE,
			self::BASE,
			array(
				'methods'             => 'GET',
				'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => ImportRunner::current()->progress( $a, (int) $r['id'] ) ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			LearnerRoutes::NAMESPACE,
			self::BASE . '/step',
			array(
				'methods'             => 'POST',
				'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => ImportRunner::current()->step( $a, (int) $r['id'], ImportRunner::budget() ) ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			LearnerRoutes::NAMESPACE,
			self::BASE . '/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => ImportRunner::current()->cancel( $a, (int) $r['id'] ) ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			LearnerRoutes::NAMESPACE,
			self::BASE . '/resume',
			array(
				'methods'             => 'POST',
				'callback'            => static fn( \WP_REST_Request $r ) => self::run( static fn( Actor $a ) => ImportRunner::current()->resume( $a, (int) $r['id'] ) ),
				'permission_callback' => $auth,
			)
		);
		register_rest_route(
			LearnerRoutes::NAMESPACE,
			self::BASE . '/report',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'report' ),
				'permission_callback' => $auth,
				'args'                => array(
					'format'   => array(
						'type'    => 'string',
						'enum'    => ImportReport::FORMATS,
						'default' => 'json',
					),
					'severity' => array( 'type' => 'string' ),
					'class'    => array( 'type' => 'string' ),
					'rule'     => array( 'type' => 'string' ),
					'phase'    => array( 'type' => 'string' ),
					'entity'   => array( 'type' => 'string' ),
					'after'    => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => ImportReport::EXPORT_BLOCK,
						'default' => 50,
					),
				),
			)
		);
	}

	/**
	 * Report route: JSON page or CSV download.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function report( \WP_REST_Request $request ) {
		$format  = (string) $request->get_param( 'format' );
		$filters = array();
		foreach ( ImportReport::FILTERS as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$filters[ $field ] = sanitize_text_field( (string) $request->get_param( $field ) );
			}
		}
		$response = self::run( static fn( Actor $a ) => ImportReport::export( $a, (int) $request['id'], $format, $filters, (int) $request->get_param( 'after' ), (int) $request->get_param( 'per_page' ) ) );
		if ( $response instanceof \WP_REST_Response && 'csv' === $format ) {
			$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
			$response->header( 'Content-Disposition', 'attachment; filename="import-' . (int) $request['id'] . '-report.csv"' );
		}
		return $response;
	}

	/**
	 * Serve CSV reports raw instead of JSON-encoded.
	 *
	 * @param bool             $served  Already served.
	 * @param mixed            $result  Response.
	 * @param \WP_REST_Request $request Request.
	 * @param mixed            $server  Server.
	 * @return bool
	 */
	public static function serve_csv( $served, $result, $request, $server ): bool {
		unset( $server );
		if ( $served || ! $result instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request || 1 !== preg_match( '#^/' . preg_quote( LearnerRoutes::NAMESPACE, '#' ) . '/imports/\d+/report$#', $request->get_route() ) ) {
			return (bool) $served;
		}
		$headers = $result->get_headers();
		if ( ! str_starts_with( (string) ( $headers['Content-Type'] ?? '' ), 'text/csv' ) || ! is_string( $result->get_data() ) ) {
			return false;
		}
		echo $result->get_data(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download; cells are neutralised against formula injection.
		return true;
	}

	/**
	 * Run a callback as the current user.
	 *
	 * @param callable $call Callback receiving the actor.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private static function run( callable $call ) {
		try {
			$response = new \WP_REST_Response( $call( Actor::user( get_current_user_id() ) ) );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		} catch ( CourseException $e ) {
			return new \WP_Error( $e->error_code, $e->getMessage(), array( 'status' => $e->status ) + $e->data );
		}
	}
}
