<?php
/**
 * Website hardening module (R0-31 §3.4/§3.5, E-10, E-239…E-242, E-260…E-263, E-307…E-309).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Security;

use CourseCaptain\Event\AuditLog;
use CourseCaptain\Ops\Compatibility;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Rules:
 * - HM-0: nothing is active before the hardening tab was saved once.
 * - HM-1/HM-2: per feature `auto` (held on a detected conflict), `force`,
 *   `off`; a held or disabled feature registers no hooks at all.
 * - HM-4: own means only – no .htaccess, wp-config or foreign options.
 * - HM-5: login/reset/registration limits fail closed; a valid device
 *   cookie (E-242) and the kill switch (NA-1) prevent lock-outs.
 * - HM-6: counters are salted hashes in `ccap_rate_limit` (prefix `h`),
 *   deleted after the window; no IP in audit or logs.
 * - HM-7: neutral messages for known and unknown accounts.
 */
final class Hardening {

	public const FEATURES       = array( 'login_limit', 'reset_limit', 'registration_limit', 'xmlrpc', 'site_headers' );
	public const STATE_OPTION   = 'course_captain_hardening_state';
	public const DEVICE_COOKIE  = 'ccap_device';
	public const DEVICE_DAYS    = 180; // Default of `hardening.known_device_days` (SET-CC-13).
	public const ERROR_CODE     = 'ccap_rate_limited';
	public const LOGIN_FEATURES = array( 'login_limit', 'reset_limit', 'registration_limit' );

	/**
	 * Default limits (behaviour as LB, E-01): [hits, window seconds]. The
	 * hits are the defaults of `hardening.limits` (SET-CC-13); the windows
	 * are technical constants. Effective values: {@see self::limits()}.
	 *
	 * @var array<string, array{0: int, 1: int}>
	 */
	public const LIMITS = array(
		'login_ip'         => array( 10, 900 ),
		'login_account_ip' => array( 5, 900 ),
		'login_account'    => array( 30, 900 ),
		'reset_ip'         => array( 10, 3600 ),
		'reset_account_ip' => array( 3, 3600 ),
		'reset_account'    => array( 10, 3600 ),
		'registration_ip'  => array( 10, 3600 ),
	);

	/**
	 * Header set of `site_headers` (E-261); Permissions-Policy only on own responses (E-309).
	 *
	 * @var array<string, string>
	 */
	public const SITE_HEADERS = array(
		'X-Content-Type-Options' => 'nosniff',
		'Referrer-Policy'        => 'strict-origin-when-cross-origin',
		'X-Frame-Options'        => 'SAMEORIGIN',
	);

	public const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=()';

	/**
	 * Test seam: simulate the kill switch constant.
	 *
	 * @internal
	 * @var bool
	 */
	public static bool $kill_switch_override = false;

	/**
	 * Test seam: simulate a broken counter store.
	 *
	 * @internal
	 * @var bool
	 */
	public static bool $store_broken = false;

	/**
	 * Clock for tests.
	 *
	 * @var int|null
	 */
	private static ?int $now = null;

	/**
	 * Registered hook callbacks per feature (for re-registration in tests).
	 *
	 * @var array<string, list<array{0: string, 1: callable, 2: int, 3: int, 4: string}>>
	 */
	private static array $hooks = array();

	/**
	 * Boot: compute states and register only active features.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'init', array( self::class, 'apply' ), 1 );
		add_action( 'activated_plugin', array( self::class, 'refresh' ) );
		add_action( 'deactivated_plugin', array( self::class, 'refresh' ) );
		add_action( 'switch_theme', array( self::class, 'refresh' ) );
		add_filter( 'site_status_tests', array( self::class, 'site_health' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'course-captain hardening', array( self::class, 'cli' ) );
		}
	}

	/**
	 * Set the clock (tests).
	 *
	 * @param int|null $now Unix time.
	 * @return void
	 */
	public static function set_now( ?int $now ): void {
		self::$now = $now;
	}

	/**
	 * Current time.
	 *
	 * @return int
	 */
	private static function now(): int {
		return self::$now ?? time();
	}

	/**
	 * Kill switch (NA-1): `define( 'COURSE_CAPTAIN_HARDENING', 'off' )`.
	 *
	 * @return bool
	 */
	public static function kill_switch(): bool {
		return self::$kill_switch_override || ( defined( 'COURSE_CAPTAIN_HARDENING' ) && 'off' === constant( 'COURSE_CAPTAIN_HARDENING' ) );
	}

	/**
	 * HM-0: confirmed by saving the tab once.
	 *
	 * @return bool
	 */
	public static function confirmed(): bool {
		return Settings::rev( 'hardening' ) > 0;
	}

	/**
	 * Configured mode of a feature.
	 *
	 * @param string $feature Feature.
	 * @return string auto|force|off
	 */
	public static function mode( string $feature ): string {
		try {
			return (string) Settings::get( 'hardening.' . $feature );
		} catch ( \Throwable $e ) {
			unset( $e );
			return 'off';
		}
	}

	/**
	 * Detected conflicts per feature (security plugins, Jetpack, multisite).
	 *
	 * @return array<string, string> feature => reason slug
	 */
	public static function conflicts(): array {
		$login   = Compatibility::security_plugin();
		$jetpack = Compatibility::constant( 'JETPACK__VERSION' );
		if ( null === $login && $jetpack && is_callable( array( 'Jetpack', 'is_module_active' ) ) && call_user_func( array( 'Jetpack', 'is_module_active' ), 'protect' ) ) {
			$login = 'jetpack-protect';
		}
		if ( null === $login && is_multisite() && ! is_main_site() ) {
			$login = 'multisite';
		}
		$out = array();
		if ( null !== $login ) {
			foreach ( self::LOGIN_FEATURES as $feature ) {
				$out[ $feature ] = $login;
			}
		}
		if ( $jetpack ) {
			$out['xmlrpc'] = 'jetpack';
		} elseif ( self::foreign_xmlrpc_rule() ) {
			// KOMP-058: another plugin already switches XML-RPC off; `auto` steps back.
			$out['xmlrpc'] = 'other_plugin';
		}
		/**
		 * Conflicts per feature (maintained with the conflict matrix R1-30).
		 *
		 * @param array<string, string> $conflicts feature => reason slug.
		 */
		return (array) apply_filters( 'course_captain_hardening_conflicts', $out );
	}

	/**
	 * Effective state per feature: `off`, `active`, `held:<reason>`.
	 *
	 * @return array<string, string>
	 */
	public static function states(): array {
		$states    = array();
		$conflicts = self::conflicts();
		foreach ( self::FEATURES as $feature ) {
			$mode = self::mode( $feature );
			if ( self::kill_switch() ) {
				$states[ $feature ] = 'off:kill_switch';
			} elseif ( ! self::confirmed() ) {
				$states[ $feature ] = 'off:unconfirmed';
			} elseif ( 'off' === $mode ) {
				$states[ $feature ] = 'off';
			} elseif ( 'registration_limit' === $feature && ! get_option( 'users_can_register' ) ) {
				$states[ $feature ] = 'off:not_needed';
			} elseif ( 'auto' === $mode && isset( $conflicts[ $feature ] ) ) {
				$states[ $feature ] = 'held:' . $conflicts[ $feature ];
			} else {
				$states[ $feature ] = 'active';
			}
		}
		return $states;
	}

	/**
	 * Whether a feature is active.
	 *
	 * @param string $feature Feature.
	 * @return bool
	 */
	public static function active( string $feature ): bool {
		return 'active' === ( self::states()[ $feature ] ?? 'off' );
	}

	/**
	 * Register hooks of active features only (HM-2); audit state changes (HM-3).
	 *
	 * @return void
	 */
	public static function apply(): void {
		self::unhook();
		$states = self::states();
		self::record_states( $states );
		$map = array(
			'login_limit'        => array(
				array( 'filter', array( self::class, 'authenticate' ), 99, 3, 'authenticate' ),
				array( 'action', array( self::class, 'login_failed' ), 10, 1, 'wp_login_failed' ),
				array( 'action', array( self::class, 'login_succeeded' ), 10, 2, 'wp_login' ),
			),
			'reset_limit'        => array(
				array( 'action', array( self::class, 'lost_password' ), 1, 2, 'lostpassword_post' ),
			),
			'registration_limit' => array(
				array( 'filter', array( self::class, 'registration' ), 99, 3, 'registration_errors' ),
			),
			'xmlrpc'             => array(
				array( 'filter', array( self::class, 'xmlrpc_off' ), 99, 1, 'xmlrpc_enabled' ),
				array( 'filter', array( self::class, 'xmlrpc_methods' ), 99, 1, 'xmlrpc_methods' ),
				array( 'filter', array( self::class, 'remove_pingback_header' ), 99, 1, 'wp_headers' ),
			),
			'site_headers'       => array(
				array( 'action', array( self::class, 'send_site_headers' ), 99, 0, 'send_headers' ),
				array( 'filter', array( self::class, 'rest_permissions_policy' ), 97, 3, 'rest_post_dispatch' ),
			),
		);
		foreach ( $map as $feature => $hooks ) {
			if ( 'active' !== $states[ $feature ] ) {
				continue;
			}
			foreach ( $hooks as $hook ) {
				list( $type, $callback, $priority, $args, $name ) = $hook;
				add_filter( $name, $callback, $priority, $args );
				self::$hooks[ $feature ][] = array( $type, $callback, $priority, $args, $name );
			}
		}
	}

	/**
	 * Re-evaluate after plugin/theme changes.
	 *
	 * @return void
	 */
	public static function refresh(): void {
		self::apply();
	}

	/**
	 * Remove all own hooks.
	 *
	 * @return void
	 */
	private static function unhook(): void {
		foreach ( self::$hooks as $hooks ) {
			foreach ( $hooks as $hook ) {
				remove_filter( $hook[4], $hook[1], $hook[2] );
			}
		}
		self::$hooks = array();
	}

	/**
	 * Persist states and audit transitions (system actor, no personal data).
	 *
	 * @param array<string, string> $states States.
	 * @return void
	 */
	private static function record_states( array $states ): void {
		$stored = get_option( self::STATE_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$before = is_array( $stored['states'] ?? null ) ? $stored['states'] : array();
		if ( $before === $states && ( ! self::kill_switch() || ! empty( $stored['kill_switch_seen'] ) ) ) {
			return;
		}
		$db = Database::current();
		if ( self::kill_switch() && empty( $stored['kill_switch_seen'] ) ) {
			AuditLog::write( $db, Actor::cron(), 'hardening_kill_switch_detected', 'settings', 'hardening' );
		}
		$changed = array();
		foreach ( $states as $feature => $state ) {
			if ( ( $before[ $feature ] ?? null ) !== $state ) {
				$changed[ $feature ] = $state;
			}
		}
		if ( array() !== $changed && array() !== $before ) {
			AuditLog::write( $db, Actor::cron(), 'hardening_state_changed', 'settings', 'hardening', null, $changed );
		}
		update_option(
			self::STATE_OPTION,
			array(
				'states'           => $states,
				'kill_switch_seen' => self::kill_switch(),
			),
			false
		);
	}

	/**
	 * Client IP as hash input (IPv6 per /64).
	 *
	 * @return string
	 */
	private static function ip(): string {
		return ClientIp::rate_key();
	}

	/**
	 * Salted bucket key with prefix `h` (HM-6).
	 *
	 * @param string $dimension Dimension name.
	 * @param string $value     Raw value (IP, login).
	 * @return string
	 */
	private static function bucket( string $dimension, string $value ): string {
		return 'h' . substr( hash_hmac( 'sha256', $dimension . '|' . strtolower( $value ), wp_salt( 'nonce' ) ), 0, 63 );
	}

	/**
	 * Hits in the current window.
	 *
	 * @param string $limit  Limit key.
	 * @param string $value  Raw value.
	 * @return int
	 * @throws \RuntimeException When the counter store fails.
	 */
	private static function hits( string $limit, string $value ): int {
		if ( self::$store_broken ) {
			throw new \RuntimeException( 'counter store unavailable' );
		}
		$db     = Database::current();
		$window = self::limits()[ $limit ][1];
		$start  = self::now() - self::now() % $window;
		$row    = $db->row( $db->prepare( 'SELECT window_start, hits FROM `' . $db->table( 'ccap_rate_limit' ) . '` WHERE bucket_hash = %s', self::bucket( $limit, $value ) ) );
		return null !== $row && (int) $row['window_start'] === $start ? (int) $row['hits'] : 0;
	}

	/**
	 * Count one hit.
	 *
	 * @param string $limit Limit key.
	 * @param string $value Raw value.
	 * @return int Hits after counting.
	 * @throws \RuntimeException When the counter store fails.
	 */
	private static function count( string $limit, string $value ): int {
		if ( self::$store_broken ) {
			throw new \RuntimeException( 'counter store unavailable' );
		}
		$db     = Database::current();
		$window = self::limits()[ $limit ][1];
		$start  = self::now() - self::now() % $window;
		$table  = $db->table( 'ccap_rate_limit' );
		$key    = self::bucket( $limit, $value );
		$db->execute( $db->prepare( "INSERT INTO `{$table}` (bucket_hash, window_start, hits, expires_at) VALUES (%s, %d, 1, %s) ON DUPLICATE KEY UPDATE hits = IF(window_start = VALUES(window_start), hits + 1, 1), window_start = VALUES(window_start), expires_at = VALUES(expires_at)", $key, $start, gmdate( 'Y-m-d H:i:s', $start + $window ) ) );
		return self::hits( $limit, $value );
	}

	/**
	 * Effective limits: `hardening.limits` with the technical windows; the
	 * filter `course_captain_hardening_limits` wins (Δ-7, SET-CC-13).
	 *
	 * @return array<string, array{0: int, 1: int}>
	 */
	public static function limits(): array {
		return \CourseCaptain\Settings\BusinessRules::hardening_limits();
	}

	/**
	 * Neutral lock error (HM-7).
	 *
	 * @return \WP_Error
	 */
	private static function locked(): \WP_Error {
		return new \WP_Error( self::ERROR_CODE, __( 'Too many attempts. Please wait a few minutes and try again.', 'course-captain' ) );
	}

	/**
	 * `authenticate` (after core): refuse while locked; fail closed without counter store.
	 *
	 * @param mixed $user     User or error.
	 * @param mixed $username Login name.
	 * @param mixed $password Password.
	 * @return mixed
	 */
	public static function authenticate( $user, $username, $password ) {
		unset( $password );
		$login = is_string( $username ) ? $username : '';
		if ( '' === $login ) {
			return $user;
		}
		$device = self::device_user();
		$known  = null !== $device && $user instanceof \WP_User && $device === (int) $user->ID;
		try {
			$limits = self::limits();
			$locked = self::hits( 'login_account', $login ) >= $limits['login_account'][0];
			if ( ! $known ) {
				$locked = $locked
					|| self::hits( 'login_ip', self::ip() ) >= $limits['login_ip'][0]
					|| self::hits( 'login_account_ip', $login . '|' . self::ip() ) >= $limits['login_account_ip'][0];
			}
		} catch ( \Throwable $e ) {
			unset( $e );
			if ( $known ) {
				return $user;
			}
			return new \WP_Error( 'ccap_temporarily_unavailable', __( 'Login is temporarily unavailable. Please try again later.', 'course-captain' ), array( 'status' => 503 ) );
		}
		return $locked ? self::locked() : $user;
	}

	/**
	 * `wp_login_failed`: count all dimensions (only first-factor failures reach this hook).
	 *
	 * @param mixed $username Login name.
	 * @return void
	 */
	public static function login_failed( $username ): void {
		$login = is_string( $username ) ? $username : '';
		try {
			self::count( 'login_ip', self::ip() );
			if ( '' !== $login ) {
				self::count( 'login_account_ip', $login . '|' . self::ip() );
				self::count( 'login_account', $login );
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}

	/**
	 * `wp_login`: issue the known-device cookie (E-242).
	 *
	 * @param mixed $login Login.
	 * @param mixed $user  User.
	 * @return void
	 */
	public static function login_succeeded( $login, $user ): void {
		unset( $login );
		if ( ! $user instanceof \WP_User || ! (bool) Settings::get( 'hardening.known_device' ) || headers_sent() ) {
			return;
		}
		$value = self::device_token( (int) $user->ID, self::now() + \CourseCaptain\Settings\BusinessRules::known_device_days() * DAY_IN_SECONDS );
		setcookie(
			self::DEVICE_COOKIE,
			$value,
			array(
				'expires'  => self::now() + \CourseCaptain\Settings\BusinessRules::known_device_days() * DAY_IN_SECONDS,
				'path'     => defined( 'COOKIEPATH' ) && is_string( constant( 'COOKIEPATH' ) ) && '' !== constant( 'COOKIEPATH' ) ? constant( 'COOKIEPATH' ) : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && is_string( constant( 'COOKIE_DOMAIN' ) ) ? constant( 'COOKIE_DOMAIN' ) : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Device token: user, expiry, HMAC bound to the password hash (invalid after a password change).
	 *
	 * @param int $user    User.
	 * @param int $expires Expiry.
	 * @return string
	 */
	public static function device_token( int $user, int $expires ): string {
		$data = get_userdata( $user );
		$pass = $data instanceof \WP_User ? substr( (string) $data->user_pass, 8, 4 ) : '';
		return $user . '|' . $expires . '|' . hash_hmac( 'sha256', $user . '|' . $expires . '|' . $pass, wp_salt( 'auth' ) );
	}

	/**
	 * User of a valid device cookie, or null.
	 *
	 * @return int|null
	 */
	public static function device_user(): ?int {
		if ( ! (bool) Settings::get( 'hardening.known_device' ) || empty( $_COOKIE[ self::DEVICE_COOKIE ] ) ) {
			return null;
		}
		$raw   = sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::DEVICE_COOKIE ] ) );
		$parts = explode( '|', $raw );
		if ( 3 !== count( $parts ) || (int) $parts[1] < self::now() ) {
			return null;
		}
		return hash_equals( self::device_token( (int) $parts[0], (int) $parts[1] ), $raw ) ? (int) $parts[0] : null;
	}

	/**
	 * `lostpassword_post`: limits before the mail is sent; neutral for unknown accounts.
	 *
	 * @param mixed $errors    WP_Error.
	 * @param mixed $user_data User or false.
	 * @return void
	 */
	public static function lost_password( $errors, $user_data = false ): void {
		if ( ! $errors instanceof \WP_Error ) {
			return;
		}
		$login = $user_data instanceof \WP_User ? (string) $user_data->user_login : ( isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['user_login'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Core form, read-only for counting.
		try {
			$limits = self::limits();
			$over   = self::count( 'reset_ip', self::ip() ) > $limits['reset_ip'][0];
			if ( '' !== $login ) {
				$over = self::count( 'reset_account_ip', $login . '|' . self::ip() ) > $limits['reset_account_ip'][0] || $over;
				$over = self::count( 'reset_account', $login ) > $limits['reset_account'][0] || $over;
			}
		} catch ( \Throwable $e ) {
			unset( $e );
			$over = true;
		}
		if ( $over ) {
			$errors->add( self::ERROR_CODE, self::locked()->get_error_message() );
		}
	}

	/**
	 * `registration_errors`: 10 per IP and hour.
	 *
	 * @param mixed $errors WP_Error.
	 * @param mixed $login  Login.
	 * @param mixed $email  Email.
	 * @return mixed
	 */
	public static function registration( $errors, $login = '', $email = '' ) {
		unset( $login, $email );
		if ( ! $errors instanceof \WP_Error ) {
			return $errors;
		}
		try {
			$over = self::count( 'registration_ip', self::ip() ) > self::limits()['registration_ip'][0];
		} catch ( \Throwable $e ) {
			unset( $e );
			$over = true;
		}
		if ( $over ) {
			$errors->add( self::ERROR_CODE, self::locked()->get_error_message() );
		}
		return $errors;
	}

	/**
	 * `xmlrpc_enabled` callback of the own XML-RPC block (distinct from foreign `__return_false`).
	 *
	 * @return bool
	 */
	public static function xmlrpc_off(): bool {
		return false;
	}

	/**
	 * Whether another plugin already disables XML-RPC (KOMP-058). The own
	 * callback is left out of the evaluation.
	 *
	 * @return bool
	 */
	public static function foreign_xmlrpc_rule(): bool {
		$own      = array( self::class, 'xmlrpc_off' );
		$priority = has_filter( 'xmlrpc_enabled', $own );
		if ( false !== $priority ) {
			remove_filter( 'xmlrpc_enabled', $own, (int) $priority );
		}
		$enabled = (bool) apply_filters( 'xmlrpc_enabled', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter probed read-only.
		if ( false !== $priority ) {
			add_filter( 'xmlrpc_enabled', $own, (int) $priority );
		}
		return ! $enabled;
	}

	/**
	 * XML-RPC: drop multicall and pingbacks; authenticated methods are off via `xmlrpc_enabled`.
	 *
	 * @param mixed $methods Methods.
	 * @return mixed
	 */
	public static function xmlrpc_methods( $methods ) {
		if ( is_array( $methods ) ) {
			unset( $methods['system.multicall'], $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		}
		return $methods;
	}

	/**
	 * No `X-Pingback` header.
	 *
	 * @param mixed $headers Headers.
	 * @return mixed
	 */
	public static function remove_pingback_header( $headers ) {
		if ( is_array( $headers ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	/**
	 * Headers that are still missing (E-307: never duplicate a header set elsewhere).
	 *
	 * @param array<int, string> $sent Header lines already queued.
	 * @return array<string, string>
	 */
	public static function missing_headers( array $sent ): array {
		$present = array();
		foreach ( $sent as $line ) {
			$present[ strtolower( trim( (string) strstr( $line, ':', true ) ) ) ] = true;
		}
		return array_filter( self::SITE_HEADERS, static fn( string $value, string $name ): bool => ! isset( $present[ strtolower( $name ) ] ), ARRAY_FILTER_USE_BOTH );
	}

	/**
	 * `send_headers`: add the missing site headers.
	 *
	 * @return void
	 */
	public static function send_site_headers(): void {
		if ( headers_sent() ) {
			return;
		}
		foreach ( self::missing_headers( headers_list() ) as $name => $value ) {
			header( $name . ': ' . $value );
		}
	}

	/**
	 * Permissions-Policy only on own REST responses (E-309).
	 *
	 * @param mixed $response Response.
	 * @param mixed $server   Server.
	 * @param mixed $request  Request.
	 * @return mixed
	 */
	public static function rest_permissions_policy( $response, $server, $request ) {
		unset( $server );
		if ( $response instanceof \WP_HTTP_Response && $request instanceof \WP_REST_Request && 0 === strpos( $request->get_route(), '/course-captain/' ) ) {
			// E-307: a header another plugin already set on this response rests.
			$present = array_change_key_case( $response->get_headers(), CASE_LOWER );
			if ( ! isset( $present['permissions-policy'] ) ) {
				$response->header( 'Permissions-Policy', self::PERMISSIONS_POLICY );
			}
		}
		return $response;
	}

	/**
	 * Remove all hardening counters (NA-2 `unlock --all`).
	 *
	 * @return int Rows removed.
	 */
	public static function unlock_all(): int {
		$db = Database::current();
		return $db->execute( 'DELETE FROM `' . $db->table( 'ccap_rate_limit' ) . "` WHERE bucket_hash LIKE 'h%'" );
	}

	/**
	 * Switch every feature off (NA-2 `disable`), audited as maintenance action.
	 *
	 * @param Actor $actor Actor (CLI).
	 * @return void
	 */
	public static function disable_all( Actor $actor ): void {
		Settings::save( $actor, 'hardening', array_fill_keys( self::FEATURES, 'off' ), self::FEATURES, Settings::rev( 'hardening' ) );
		AuditLog::write( Database::current(), $actor, 'maintenance_action', 'settings', 'hardening', 'hardening_disable' );
		self::apply();
	}

	/**
	 * WP-CLI: `wp course-captain hardening status|disable|unlock [--all] [--format=json]`.
	 *
	 * @param array<int, string>    $args  Positional.
	 * @param array<string, string> $assoc Options.
	 * @return void
	 */
	public static function cli( array $args, array $assoc ): void {
		$command = $args[0] ?? 'status';
		if ( 'disable' === $command ) {
			self::disable_all( Actor::cli() );
			\WP_CLI::success( 'Hardening disabled.' );
			return;
		}
		if ( 'unlock' === $command ) {
			$rows = self::unlock_all();
			AuditLog::write( Database::current(), Actor::cli(), 'maintenance_action', 'settings', 'hardening', 'hardening_unlock_all', array( 'rows' => $rows ) );
			\WP_CLI::success( sprintf( 'Removed %d counters.', $rows ) );
			return;
		}
		$states = self::states();
		if ( 'json' === ( $assoc['format'] ?? '' ) ) {
			\WP_CLI::line( (string) wp_json_encode( $states ) );
			return;
		}
		foreach ( $states as $feature => $state ) {
			\WP_CLI::line( $feature . ': ' . $state );
		}
	}

	/**
	 * Site Health test.
	 *
	 * @param mixed $tests Tests.
	 * @return mixed
	 */
	public static function site_health( $tests ) {
		if ( is_array( $tests ) ) {
			$tests['direct']['course_captain_hardening'] = array(
				'label' => __( 'Course Captain website hardening', 'course-captain' ),
				'test'  => array( self::class, 'health_result' ),
			);
		}
		return $tests;
	}

	/**
	 * Site Health result.
	 *
	 * @return array<string, mixed>
	 */
	public static function health_result(): array {
		$states = self::states();
		$items  = '';
		foreach ( $states as $feature => $state ) {
			$items .= '<li>' . esc_html( $feature . ': ' . $state ) . "</li>\n";
		}
		$warning = self::kill_switch() || ( self::confirmed() && 'off' === ( $states['login_limit'] ?? 'off' ) );
		return array(
			'label'       => self::kill_switch() ? __( 'Website hardening is switched off by constant', 'course-captain' ) : __( 'Course Captain website hardening', 'course-captain' ),
			'status'      => $warning ? 'recommended' : 'good',
			'badge'       => array(
				'label' => __( 'Security', 'course-captain' ),
				'color' => 'blue',
			),
			'description' => '<ul>' . $items . '</ul>',
			'test'        => 'course_captain_hardening',
		);
	}
}
