<?php
/**
 * REST status, health and monitor routes (R0-19 R-01/R-50, R0-13 §2.1
 * "Monitor", R0-17 T-58/SEC-035; R0-05 GM-146…GM-148).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Notification\OutboundMode;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Rest\RestContract;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\StorageException;

defined( 'ABSPATH' ) || exit;

/**
 * - `GET /status` (R-01, cap #22): versions, outbound and hold mode, check list.
 * - `GET /health` and `GET /monitor` (R-50, auth class K): anonymous callers
 *   get only the overall status `ok|error` (200/503), never a version;
 *   single checks only with the header `X-CC-Monitor-Key` matching the
 *   constant `COURSE_CAPTAIN_MONITOR_KEY` (`hash_equals`) or for #22.
 *   `?key=` is ignored (it would end up in server logs). Only H-02 (due),
 *   H-04, H-07, H-10, H-14 and H-26 make the monitor fail (R0-13 §2.1);
 *   H-32 (REST reachable) is implicit: this route answers.
 * Responses are never cached. 60 calls per IP and minute, fail-open.
 */
final class Monitor {

	public const HEADER      = 'X-CC-Monitor-Key';
	public const CONSTANT    = 'COURSE_CAPTAIN_MONITOR_KEY';
	public const CRON_STALE  = 3 * HOUR_IN_SECONDS;
	public const TASK_WINDOW = 2 * HOUR_IN_SECONDS;
	public const PER_MINUTE  = 60;

	/**
	 * Clock override (tests).
	 *
	 * @var int|null
	 */
	private static ?int $clock = null;

	/**
	 * Key override (tests): null = constant, '' = no key configured.
	 *
	 * @var string|null
	 */
	private static ?string $key = null;

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
		register_rest_route(
			RestContract::NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'status' ),
				'permission_callback' => array( self::class, 'can_view_status' ),
			)
		);
		foreach ( array( '/health', '/monitor' ) as $route ) {
			register_rest_route(
				RestContract::NAMESPACE,
				$route,
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'health' ),
					// Public route (R-50, auth class "K"): overall status only; details need the header key or #22; rate limited.
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/**
	 * Set the clock (tests only).
	 *
	 * @param int|null $now Unix time or null.
	 * @return void
	 */
	public static function set_now( ?int $now ): void {
		self::$clock = $now;
	}

	/**
	 * Set the monitor key (tests only).
	 *
	 * @param string|null $key Key, '' = none, null = constant.
	 * @return void
	 */
	public static function with_key( ?string $key ): void {
		self::$key = $key;
	}

	/**
	 * Current time.
	 *
	 * @return int
	 */
	private static function now(): int {
		return self::$clock ?? time();
	}

	/**
	 * Configured monitor key ('' = none).
	 *
	 * @return string
	 */
	private static function key(): string {
		if ( null !== self::$key ) {
			return self::$key;
		}
		return defined( self::CONSTANT ) ? (string) constant( self::CONSTANT ) : '';
	}

	/**
	 * R-01 permission: 401 for guests, 403 without #22.
	 *
	 * @return bool|\WP_Error
	 */
	public static function can_view_status() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'ccap_auth_required', __( 'Please log in.', 'course-captain' ), array( 'status' => 401 ) );
		}
		return current_user_can( OpsStatus::CAP ) ? true : new \WP_Error( 'ccap_forbidden', __( 'You are not allowed to do this.', 'course-captain' ), array( 'status' => 403 ) );
	}

	/**
	 * R-01 `GET /status`.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function status() {
		try {
			$report = OpsStatus::report( Actor::current() );
		} catch ( CourseException $e ) {
			return new \WP_Error( $e->error_code, $e->getMessage(), array( 'status' => $e->status ) );
		}
		$checks = array();
		foreach ( $report['checks'] as $check ) {
			$checks[] = array(
				'id'      => $check['id'],
				'status'  => 'info' === $check['status'] ? 'ok' : $check['status'],
				'message' => $check['label'],
			);
		}
		$response = new \WP_REST_Response(
			array(
				'plugin_version'   => COURSE_CAPTAIN_VERSION,
				'contract_version' => defined( 'COURSE_CAPTAIN_CONTRACT_VERSION' ) ? (string) COURSE_CAPTAIN_CONTRACT_VERSION : '',
				'schema_version'   => (string) ( $report['migration']['stored_version'] ?? '' ),
				'outbound_mode'    => OutboundMode::current()['mode'],
				'hold_mode'        => SiteIdentity::is_held(),
				'checks'           => $checks,
			)
		);
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	/**
	 * R-50 `GET /health` (= `/monitor`).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function health( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! self::within_rate_limit() ) {
			$response = new \WP_REST_Response(
				array(
					'code'    => 'ccap_rate_limited',
					'message' => __( 'Too many requests. Please wait a moment.', 'course-captain' ),
					'data'    => array(
						'status'      => 429,
						'bucket'      => 'health',
						'retry_after' => 60 - self::now() % 60,
					),
				),
				429
			);
			$response->header( 'Cache-Control', 'no-store' );
			return $response;
		}
		$checks = self::checks();
		$failed = in_array( 'fail', array_column( $checks, 'status' ), true );
		$body   = array( 'status' => $failed ? 'error' : 'ok' );
		if ( self::may_see_details( $request ) ) {
			$body['checks'] = $checks;
		}
		$response = new \WP_REST_Response( $body, $failed ? 503 : 200 );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Details for the header key (never the query string) or #22.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function may_see_details( \WP_REST_Request $request ): bool {
		$key   = self::key();
		$given = (string) $request->get_header( strtolower( str_replace( '-', '_', self::HEADER ) ) );
		if ( '' !== $key && '' !== $given && hash_equals( $key, $given ) ) {
			return true;
		}
		return is_user_logged_in() && current_user_can( OpsStatus::CAP );
	}

	/**
	 * Monitor checks (`id`, `status` ok|fail), without texts, versions or paths.
	 *
	 * @param Migrator|null $migrator Migrator (tests); default current site.
	 * @return list<array{id: string, status: string}>
	 */
	public static function checks( ?Migrator $migrator = null ): array {
		$now      = self::now();
		$state    = get_option( HealthChecks::STATE_OPTION );
		$state    = is_array( $state ) ? $state : array();
		$last     = (int) ( $state['last_run'] ?? 0 );
		$failures = (array) ( $state['failures'] ?? array() );
		$next     = wp_next_scheduled( CronHooks::HOURLY );
		$report   = ( $migrator ?? Migrator::for_current_site() )->report();
		$storage  = HealthChecks::test_storage();

		$checks = array(
			// H-02: a due or failed schema update (newer schema, poisoned or failed step).
			'H-02' => ! in_array( $report['state'], array( 'pending', 'newer', 'blocked', 'failed' ), true ),
			// H-04: own tables present.
			'H-04' => self::tables_present(),
			// H-07: hourly maintenance ran within 3 h; freshly installed and planned = ok.
			'H-07' => false !== $next && ( $last > 0 ? $now - $last <= self::CRON_STALE : (int) $next >= $now - self::CRON_STALE ),
			// H-10: no whole maintenance task failed within 2 h.
			'H-10' => array() === $failures || $now - $last > self::TASK_WINDOW,
			// H-14: protected folder reachable and guarded.
			'H-14' => 'critical' !== $storage['status'],
			// H-26: no hold mode after a site identity change.
			'H-26' => ! SiteIdentity::is_held(),
		);
		$out = array();
		foreach ( $checks as $id => $ok ) {
			$out[] = array(
				'id'     => $id,
				'status' => $ok ? 'ok' : 'fail',
			);
		}
		return $out;
	}

	/**
	 * Whether the own core tables exist (H-04).
	 *
	 * @return bool
	 */
	private static function tables_present(): bool {
		$db = Database::current();
		try {
			foreach ( array( 'ccap_grant', 'ccap_exam_session', 'ccap_outbox_event' ) as $table ) {
				if ( $db->table( $table ) !== (string) $db->scalar( $db->prepare( 'SHOW TABLES LIKE %s', $db->wpdb()->esc_like( $db->table( $table ) ) ) ) ) {
					return false;
				}
			}
		} catch ( StorageException $e ) {
			unset( $e );
			return false;
		}
		return true;
	}

	/**
	 * 60 calls per IP and minute; a broken counter store lets the call pass (fail-open, R0-17).
	 *
	 * @return bool
	 */
	private static function within_rate_limit(): bool {
		$db     = Database::current();
		$now    = self::now();
		$window = $now - $now % 60;
		$bucket = hash( 'sha256', 'health:' . \CourseCaptain\Security\ClientIp::get() );
		$table  = $db->table( 'ccap_rate_limit' );
		try {
			$db->execute(
				$db->prepare(
					"INSERT INTO `{$table}` (bucket_hash, window_start, hits, expires_at) VALUES (%s, %d, 1, %s) ON DUPLICATE KEY UPDATE hits = IF(window_start = VALUES(window_start), hits + 1, 1), window_start = VALUES(window_start), expires_at = VALUES(expires_at)",
					$bucket,
					$window,
					gmdate( 'Y-m-d H:i:s', $window + 120 )
				)
			);
			$hits = $db->scalar( $db->prepare( "SELECT hits FROM `{$table}` WHERE bucket_hash = %s", $bucket ) );
		} catch ( StorageException $e ) {
			unset( $e );
			return true;
		}
		return null === $hits || (int) $hits <= self::PER_MINUTE;
	}
}
