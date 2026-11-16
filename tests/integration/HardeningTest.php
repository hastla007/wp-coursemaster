<?php
/**
 * R1-45: website hardening module (R0-31 §3.4/§3.5, PSD-039…056, PSD-065).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Security\Hardening;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

/**
 * Hardening.
 *
 * @group R1-45
 */
final class HardeningTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner.
	 *
	 * @var int
	 */
	private int $user;

	/**
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin                     = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->user                      = (int) self::factory()->user->create(
			array(
				'user_login' => 'lea',
				'user_pass'  => 'right-password',
			)
		);
		$_SERVER['REMOTE_ADDR']          = '203.0.113.7';
		Hardening::$store_broken         = false;
		Hardening::$kill_switch_override = false;
		Hardening::set_now( 1800000000 );
		unset( $_COOKIE[ Hardening::DEVICE_COOKIE ] );
	}

	/**
	 * Teardown.
	 */
	public function tear_down(): void {
		Hardening::$kill_switch_override = false;
		Hardening::$store_broken         = false;
		Hardening::set_now( null );
		remove_all_filters( 'course_captain_hardening_conflicts' );
		Hardening::apply();
		parent::tear_down();
	}

	/**
	 * Save the hardening tab (confirmation, HM-0).
	 *
	 * @param array<string, mixed> $values Values.
	 * @return void
	 */
	private function confirm( array $values = array() ): void {
		$values = $values + array_fill_keys( Hardening::FEATURES, 'auto' );
		Settings::save( Actor::user( $this->admin ), 'hardening', $values, array_keys( $values ), Settings::rev( 'hardening' ) );
		Hardening::apply();
	}

	/**
	 * Login attempt.
	 *
	 * @param string $login    Login.
	 * @param string $password Password.
	 * @return mixed
	 */
	private static function login( string $login, string $password ) {
		return wp_authenticate( $login, $password );
	}

	/**
	 * PSD-065 / HM-0 / HM-2: before confirmation nothing is hooked.
	 *
	 * @group PSD-065
	 */
	public function test_PSD_065_inactive_until_confirmed(): void {
		Hardening::apply();
		self::assertSame( 'off:unconfirmed', Hardening::states()['login_limit'] );
		self::assertFalse( has_filter( 'authenticate', array( Hardening::class, 'authenticate' ) ) );
		self::assertFalse( has_filter( 'xmlrpc_enabled', '__return_false' ) );
		$this->confirm();
		self::assertSame( 'active', Hardening::states()['login_limit'] );
		self::assertNotFalse( has_filter( 'authenticate', array( Hardening::class, 'authenticate' ) ) );
	}

	/**
	 * PSD-039 / PSD-040 / HM-7: IP and account+IP limits, neutral message, window end.
	 *
	 * @group PSD-039
	 * @group PSD-040
	 */
	public function test_PSD_039_040_login_limits(): void {
		$this->confirm();
		for ( $i = 0; $i < 5; $i++ ) {
			self::assertInstanceOf( \WP_Error::class, self::login( 'lea', 'wrong' ) );
		}
		$locked = self::login( 'lea', 'right-password' );
		self::assertInstanceOf( \WP_Error::class, $locked, '5 per account+IP' );
		self::assertSame( Hardening::ERROR_CODE, $locked->get_error_code() );
		$unknown = self::login( 'nobody', 'x' );
		for ( $i = 0; $i < 5; $i++ ) {
			self::login( 'nobody', 'x' );
		}
		self::assertSame( $locked->get_error_message(), self::login( 'nobody', 'x' )->get_error_message(), 'neutral for unknown accounts' );
		unset( $unknown );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
		self::assertInstanceOf( \WP_User::class, self::login( 'lea', 'right-password' ), 'other IP, account below 30' );
		Hardening::set_now( 1800000000 + 900 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		self::assertInstanceOf( \WP_User::class, self::login( 'lea', 'right-password' ), 'free after window end' );
	}

	/**
	 * PSD-046 / E-242: device cookie bypasses IP and account+IP locks; invalid after password change.
	 *
	 * @group PSD-046
	 */
	public function test_PSD_046_known_device(): void {
		$this->confirm();
		for ( $i = 0; $i < 10; $i++ ) {
			self::login( 'someone' . $i, 'x' );
		}
		self::assertSame( Hardening::ERROR_CODE, self::login( 'lea', 'right-password' )->get_error_code(), 'IP locked' );
		$_COOKIE[ Hardening::DEVICE_COOKIE ] = Hardening::device_token( $this->user, 1800000000 + 86400 );
		self::assertSame( $this->user, Hardening::device_user() );
		self::assertInstanceOf( \WP_User::class, self::login( 'lea', 'right-password' ), 'known device' );
		$_COOKIE[ Hardening::DEVICE_COOKIE ] = $this->user . '|' . ( 1800000000 + 86400 ) . '|forged';
		self::assertNull( Hardening::device_user(), 'manipulated cookie' );
		$_COOKIE[ Hardening::DEVICE_COOKIE ] = Hardening::device_token( $this->user, 1800000000 + 86400 );
		wp_set_password( 'new-password', $this->user );
		self::assertNull( Hardening::device_user(), 'invalid after password change' );
	}

	/**
	 * PSD-044 / HM-5 / NA-3: broken counter store fails closed, except a known device.
	 *
	 * @group PSD-044
	 */
	public function test_PSD_044_fail_closed(): void {
		$this->confirm();
		Hardening::$store_broken = true;
		$error                   = self::login( 'lea', 'right-password' );
		self::assertInstanceOf( \WP_Error::class, $error );
		self::assertSame( 'ccap_temporarily_unavailable', $error->get_error_code() );
		$_COOKIE[ Hardening::DEVICE_COOKIE ] = Hardening::device_token( $this->user, 1800000000 + 86400 );
		self::assertInstanceOf( \WP_User::class, self::login( 'lea', 'right-password' ) );
	}

	/**
	 * PSD-043: password reset limits before mail; same response for unknown accounts.
	 *
	 * @group PSD-043
	 */
	public function test_PSD_043_reset_limit(): void {
		$this->confirm();
		$user = get_userdata( $this->user );
		for ( $i = 0; $i < 3; $i++ ) {
			$errors = new \WP_Error();
			Hardening::lost_password( $errors, $user );
			self::assertFalse( $errors->has_errors() );
		}
		$errors = new \WP_Error();
		Hardening::lost_password( $errors, $user );
		self::assertSame( array( Hardening::ERROR_CODE ), $errors->get_error_codes(), '4th per account+IP' );
	}

	/**
	 * PSD-053: registration limit only with open registration.
	 *
	 * @group PSD-053
	 */
	public function test_PSD_053_registration(): void {
		update_option( 'users_can_register', 0 );
		$this->confirm();
		self::assertSame( 'off:not_needed', Hardening::states()['registration_limit'] );
		update_option( 'users_can_register', 1 );
		Hardening::apply();
		for ( $i = 0; $i < 10; $i++ ) {
			self::assertFalse( Hardening::registration( new \WP_Error() )->has_errors() );
		}
		self::assertTrue( Hardening::registration( new \WP_Error() )->has_errors(), '11th per IP and hour' );
	}

	/**
	 * PSD-047 / PSD-048 / HM-1…HM-3: conflict holds the feature completely; force overrides; audit on change.
	 *
	 * @group PSD-047
	 * @group PSD-048
	 */
	public function test_PSD_047_048_conflicts(): void {
		$this->confirm();
		add_filter( 'course_captain_hardening_conflicts', static fn( array $c ): array => $c + array( 'login_limit' => 'wordfence' ) );
		Hardening::apply();
		self::assertSame( 'held:wordfence', Hardening::states()['login_limit'] );
		self::assertFalse( has_filter( 'authenticate', array( Hardening::class, 'authenticate' ) ), 'held = no hooks' );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'hardening_state_changed'" ) );
		$this->confirm( array( 'login_limit' => 'force' ) );
		self::assertSame( 'active', Hardening::states()['login_limit'], 'force despite conflict' );
		remove_all_filters( 'course_captain_hardening_conflicts' );
		Hardening::apply();
		self::assertSame( 'active', Hardening::states()['login_limit'] );
	}

	/**
	 * PSD-050: XML-RPC restrictions.
	 *
	 * @group PSD-050
	 */
	public function test_PSD_050_xmlrpc(): void {
		$this->confirm();
		self::assertFalse( apply_filters( 'xmlrpc_enabled', true ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		$methods = apply_filters(
			'xmlrpc_methods', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
			array(
				'system.multicall' => 'x',
				'pingback.ping'    => 'x',
				'demo.sayHello'    => 'x',
			)
		);
		self::assertSame( array( 'demo.sayHello' ), array_keys( $methods ) );
		self::assertArrayNotHasKey( 'X-Pingback', apply_filters( 'wp_headers', array( 'X-Pingback' => 'u' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		add_filter( 'course_captain_hardening_conflicts', static fn( array $c ): array => $c + array( 'xmlrpc' => 'jetpack' ) );
		Hardening::apply();
		self::assertSame( 'held:jetpack', Hardening::states()['xmlrpc'] );
		self::assertTrue( apply_filters( 'xmlrpc_enabled', true ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
	}

	/**
	 * PSD-051 / E-307 / E-309: only missing headers; Permissions-Policy only on own REST responses; no HSTS.
	 *
	 * @group PSD-051
	 */
	public function test_PSD_051_site_headers(): void {
		$missing = Hardening::missing_headers( array( 'X-Frame-Options: DENY', 'Content-Type: text/html' ) );
		self::assertSame( array( 'X-Content-Type-Options', 'Referrer-Policy' ), array_keys( $missing ) );
		self::assertArrayNotHasKey( 'Strict-Transport-Security', Hardening::SITE_HEADERS );
		$this->confirm();
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$request  = new \WP_REST_Request( 'GET', '/course-captain/v1/me/courses' );
		$response = apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertSame( Hardening::PERMISSIONS_POLICY, $response->get_headers()['Permissions-Policy'] );
	}

	/**
	 * PSD-045 / NA-1 / NA-2: kill switch and CLI disable/unlock.
	 *
	 * @group PSD-045
	 */
	public function test_PSD_045_kill_switch_and_unlock(): void {
		$this->confirm();
		for ( $i = 0; $i < 6; $i++ ) {
			self::login( 'lea', 'wrong' );
		}
		self::assertGreaterThan( 0, Hardening::unlock_all() );
		self::assertInstanceOf( \WP_User::class, self::login( 'lea', 'right-password' ), 'unlocked' );

		Hardening::$kill_switch_override = true;
		Hardening::apply();
		self::assertSame( 'off:kill_switch', Hardening::states()['login_limit'] );
		self::assertFalse( has_filter( 'authenticate', array( Hardening::class, 'authenticate' ) ) );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'hardening_kill_switch_detected'" ) );
		Hardening::apply();
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'hardening_kill_switch_detected'" ), 'audited once' );
		self::assertSame( 'recommended', Hardening::health_result()['status'] );

		Hardening::$kill_switch_override = false;
		Hardening::disable_all( Actor::user( $this->admin ) );
		self::assertSame( 'off', Hardening::states()['login_limit'] );
	}

	/**
	 * PSD-056 / HM-6: counters are salted hashes, no IP stored; no IP in audit.
	 *
	 * @group PSD-056
	 */
	public function test_PSD_056_data_minimisation(): void {
		$this->confirm();
		self::login( 'lea', 'wrong' );
		$db   = Database::current();
		$rows = $db->rows( 'SELECT * FROM `' . $db->table( 'ccap_rate_limit' ) . "` WHERE bucket_hash LIKE 'h%'" );
		self::assertCount( 3, $rows );
		self::assertStringNotContainsString( '203.0.113.7', (string) wp_json_encode( $rows ) );
		self::assertStringNotContainsString( 'lea', (string) wp_json_encode( $rows ) );
		self::assertStringNotContainsString( '203.0.113.7', (string) wp_json_encode( $db->rows( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . '`' ) ) );
	}
}
