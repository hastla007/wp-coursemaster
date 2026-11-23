<?php
/**
 * R1-30: conflict and interoperability tests with foreign plugin stubs
 * (R0-20 §2; KOMP-0xx/1xx). Foreign names are "(v)": model knowledge.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Access\GrantService;
use CourseCaptain\Certificate\CertificatePages;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Ops\Compatibility;
use CourseCaptain\Ops\HealthChecks;
use CourseCaptain\Ops\IntegrationStatus;
use CourseCaptain\Ops\OpsStatus;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Security\ClientIp;
use CourseCaptain\Security\Hardening;
use CourseCaptain\Seo\CourseSeo;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\Compat\ForeignPlugin;
use CourseCaptain\Tests\Support\Compat\ForeignPlugins;

require_once dirname( __DIR__ ) . '/support/compat/ForeignPlugin.php';
require_once dirname( __DIR__ ) . '/support/compat/ForeignPlugins.php';
require_once dirname( __DIR__ ) . '/support/compat/UnknownDropIn.php';

/**
 * Compatibility matrix (stub tests).
 *
 * @group R1-30
 */
final class CompatibilityTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Saved `$_SERVER`.
	 *
	 * @var array<string, mixed>
	 */
	private array $server = array();

	/**
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin            = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->server           = $_SERVER;
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		Hardening::set_now( 1800000000 );
	}

	/**
	 * Teardown.
	 */
	public function tear_down(): void {
		ForeignPlugin::deactivate_all();
		$_SERVER = $this->server;
		Hardening::set_now( null );
		Hardening::apply();
		parent::tear_down();
	}

	/**
	 * Check of the system status by ID.
	 *
	 * @param string $id ID.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private function check( string $id ): array {
		foreach ( OpsStatus::report( Actor::user( $this->admin ) )['checks'] as $check ) {
			if ( $id === $check['id'] ) {
				return $check;
			}
		}
		self::fail( 'missing check ' . $id );
	}

	/**
	 * Confirm the hardening tab with all features on `auto`.
	 *
	 * @param array<string, string> $values Overrides.
	 * @return void
	 */
	private function confirm_hardening( array $values = array() ): void {
		$values = $values + array_fill_keys( Hardening::FEATURES, 'auto' );
		Settings::save( Actor::user( $this->admin ), 'hardening', $values, array_keys( $values ), Settings::rev( 'hardening' ) );
		Hardening::apply();
	}

	/**
	 * KOMP-057 (Pflicht, E-10/E-240): every security plugin with an own login
	 * limit is detected, login/reset/registration limits rest, no double lock,
	 * the status names the plugin; after deactivation `auto` applies again.
	 *
	 * @group KOMP-057
	 * @group GM-208
	 */
	public function test_KOMP_057_security_plugins_auto_retreat(): void {
		update_option( 'users_can_register', 1 );
		if ( is_multisite() ) {
			update_site_option( 'registration', 'user' );
		}
		$this->confirm_hardening();
		self::assertSame( 'active', Hardening::states()['login_limit'] );
		$user = (int) self::factory()->user->create(
			array(
				'user_login' => 'komp057',
				'user_pass'  => 'right-password',
			)
		);

		foreach ( ForeignPlugins::security() as $reason => $plugin ) {
			$plugin->activate();
			$states = Hardening::states();
			foreach ( Hardening::LOGIN_FEATURES as $feature ) {
				self::assertSame( 'held:' . $reason, $states[ $feature ], $reason . ' ' . $feature );
			}
			self::assertSame( 'active', $states['site_headers'], 'unrelated features stay active' );
			self::assertFalse( has_filter( 'authenticate', array( Hardening::class, 'authenticate' ) ), $reason . ': no CC login hook' );
			self::assertFalse( has_action( 'lostpassword_post', array( Hardening::class, 'lost_password' ) ), $reason . ': no CC reset hook' );
			$status = $this->check( 'hardening' );
			self::assertStringContainsString( 'login_limit: held:' . $reason, $status['hint'], 'status names feature and plugin' );

			// No double lock: CC never answers with its own rate-limit error while the plugin is active.
			for ( $i = 0; $i < 12; $i++ ) {
				$result = wp_authenticate( 'komp057', 'wrong' );
				self::assertInstanceOf( \WP_Error::class, $result );
				self::assertNotSame( Hardening::ERROR_CODE, $result->get_error_code(), $reason );
			}
			self::assertInstanceOf( \WP_User::class, wp_authenticate( 'komp057', 'right-password' ) );

			$plugin->deactivate();
			self::assertSame( 'active', Hardening::states()['login_limit'], $reason . ': auto applies again' );
			self::assertNotFalse( has_filter( 'authenticate', array( Hardening::class, 'authenticate' ) ), $reason . ': hook back after deactivated_plugin' );
		}
		unset( $user );

		// `force` overrides the detection on purpose (admin decision, E-240).
		$wordfence = ForeignPlugins::security()['wordfence']->activate();
		$this->confirm_hardening( array( 'login_limit' => 'force' ) );
		self::assertSame( 'active', Hardening::states()['login_limit'] );
		self::assertSame( 'held:wordfence', Hardening::states()['reset_limit'] );
		$wordfence->deactivate();
	}

	/**
	 * KOMP-058 (Pflicht): Jetpack and foreign XML-RPC rules make `auto` step
	 * back; with `force` both blocks work side by side without error; the
	 * admin can switch the CC block off.
	 *
	 * @group KOMP-058
	 */
	public function test_KOMP_058_xmlrpc_double_block(): void {
		$this->confirm_hardening();
		$own = array( Hardening::class, 'xmlrpc_off' );
		self::assertSame( 'active', Hardening::states()['xmlrpc'] );
		self::assertNotFalse( has_filter( 'xmlrpc_enabled', $own ) );
		self::assertFalse( Hardening::foreign_xmlrpc_rule(), 'own block is not a foreign rule' );

		$jetpack = ForeignPlugins::jetpack()->activate();
		self::assertSame( 'held:jetpack', Hardening::states()['xmlrpc'] );
		self::assertTrue( apply_filters( 'xmlrpc_enabled', true ), 'Jetpack keeps XML-RPC' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		$jetpack->deactivate();

		$blocker = ForeignPlugins::xmlrpc_blocker()->activate();
		Hardening::apply();
		self::assertSame( 'held:other_plugin', Hardening::states()['xmlrpc'] );
		self::assertFalse( has_filter( 'xmlrpc_enabled', $own ) );
		self::assertFalse( apply_filters( 'xmlrpc_enabled', true ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.

		$this->confirm_hardening( array( 'xmlrpc' => 'force' ) );
		self::assertSame( 'active', Hardening::states()['xmlrpc'] );
		self::assertFalse( apply_filters( 'xmlrpc_enabled', true ), 'both blocks, same result' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		$methods = apply_filters( 'xmlrpc_methods', array( 'demo.sayHello' => 'x' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertSame( array( 'demo.sayHello' ), array_keys( $methods ) );

		$this->confirm_hardening( array( 'xmlrpc' => 'off' ) );
		self::assertSame( 'off', Hardening::states()['xmlrpc'] );
		self::assertFalse( has_filter( 'xmlrpc_enabled', $own ) );
		$blocker->deactivate();
	}

	/**
	 * KOMP-059 (Pflicht, E-307/E-309): headers set by another plugin rest;
	 * no duplicate; Permissions-Policy only on own REST responses and never
	 * over a foreign value.
	 *
	 * @group KOMP-059
	 */
	public function test_KOMP_059_header_precedence(): void {
		$this->confirm_hardening();
		ForeignPlugins::header_plugin()->activate();
		$lines = array();
		foreach ( (array) apply_filters( 'wp_headers', array() ) as $name => $value ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
			$lines[] = $name . ': ' . $value;
		}
		$missing = Hardening::missing_headers( $lines );
		self::assertArrayNotHasKey( 'X-Frame-Options', $missing );
		self::assertArrayNotHasKey( 'X-Content-Type-Options', $missing, 'case-insensitive' );
		self::assertSame( array( 'Referrer-Policy' ), array_keys( $missing ) );
		self::assertSame( array(), Hardening::missing_headers( array_merge( $lines, array( 'referrer-policy: no-referrer' ) ) ), 'server header counts as well' );

		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$request  = new \WP_REST_Request( 'GET', '/course-captain/v1/me/courses' );
		$response = apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertSame( 'camera=(self)', $response->get_headers()['Permissions-Policy'], 'foreign value rests untouched' );
		self::assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );

		ForeignPlugin::deactivate_all();
		$request  = new \WP_REST_Request( 'GET', '/wp/v2/types' );
		$response = apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertArrayNotHasKey( 'Permissions-Policy', $response->get_headers(), 'E-309: not on foreign routes' );
	}

	/**
	 * KOMP-052 / KOMP-051: REST blocked for guests is detected
	 * (`rest_guest_blocked`), logged-in users are unaffected, the
	 * verification page renders without REST (D-20-09).
	 *
	 * @group KOMP-051
	 * @group KOMP-052
	 */
	public function test_KOMP_052_rest_guest_blocked(): void {
		$learner = (int) self::factory()->user->create();
		wp_set_current_user( $learner );
		self::assertFalse( Compatibility::rest_guest_blocked() );
		self::assertSame( 'ok', $this->check( 'rest_guest_blocked' )['status'] );

		foreach ( array( false, true ) as $solid ) {
			$plugin = ForeignPlugins::rest_guest_block( $solid )->activate();
			wp_set_current_user( $learner );
			self::assertTrue( Compatibility::rest_guest_blocked() );
			self::assertSame( $learner, get_current_user_id(), 'probe restores the user' );
			self::assertNotInstanceOf( \WP_Error::class, apply_filters( 'rest_authentication_errors', null ), 'learners not affected' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
			$check = $this->check( 'rest_guest_blocked' );
			self::assertSame( 'warn', $check['status'] );
			self::assertNotSame( '', $check['hint'] );
			if ( $solid ) {
				self::assertSame( 'solid-security', Compatibility::security_plugin() );
			}
			$plugin->deactivate();
		}

		wp_set_current_user( 0 );
		$page = CertificatePages::verification_page( 'unknown-token', 'komp052' );
		self::assertGreaterThanOrEqual( 400, $page['status'] );
		self::assertStringContainsString( '<html', $page['html'], 'server-rendered without REST' );
	}

	/**
	 * KOMP-034 (E-198): a query-result cache is detected and reported as critical.
	 *
	 * @group KOMP-034
	 */
	public function test_KOMP_034_db_query_cache(): void {
		require_once dirname( __DIR__ ) . '/support/compat/w3tc-dbcache-stub.php';
		self::assertNull( Compatibility::db_query_cache() );
		self::assertSame( 'ok', $this->check( 'db_query_cache' )['status'] );

		$w3tc  = ( new \ReflectionClass( \W3TC\DbCache_Wpdb::class ) )->newInstanceWithoutConstructor();
		$found = Compatibility::db_query_cache( $w3tc );
		self::assertSame( 'w3-total-cache', $found['plugin'] ?? null );
		$check = Compatibility::db_cache_check( $w3tc );
		self::assertSame( 'fail', $check['status'] );
		self::assertSame( array( 'plugin:w3-total-cache' ), $check['ids'] );

		$unknown = ( new \ReflectionClass( \wpdb::class ) )->newInstanceWithoutConstructor();
		self::assertNull( Compatibility::db_query_cache( $unknown ), 'plain wpdb' );
		$unknown = ( new \ReflectionClass( \CourseCaptain\Tests\Support\Compat\UnknownDropIn::class ) )->newInstanceWithoutConstructor();
		self::assertSame( 'info', Compatibility::db_cache_check( $unknown )['status'], 'unknown drop-in: hint only' );
	}

	/**
	 * KOMP-056: mail logs that store invitation links are reported.
	 *
	 * @group KOMP-056
	 */
	public function test_KOMP_056_mail_log(): void {
		self::assertSame( 'ok', $this->check( 'mail_log' )['status'] );
		foreach ( array( 'wp-mail-smtp', 'fluent-smtp', 'post-smtp', 'wp-mail-logging' ) as $slug ) {
			$plugin = ForeignPlugins::mail( $slug, true )->activate();
			$check  = $this->check( 'mail_log' );
			self::assertSame( 'warn', $check['status'], $slug );
			self::assertSame( array( 'plugin:' . $slug ), $check['ids'] );
			$plugin->deactivate();
		}
		foreach ( array( 'wp-mail-smtp', 'fluent-smtp' ) as $slug ) {
			$plugin = ForeignPlugins::mail( $slug, false )->activate();
			self::assertSame( array(), Compatibility::mail_logs(), $slug . ' without log' );
			$plugin->deactivate();
		}
	}

	/**
	 * KOMP-060…063: SEO plugin detection; with any SEO plugin CC prints no
	 * own JSON-LD; CC never filters options of SEO plugins (LB-K-05).
	 *
	 * @group KOMP-060
	 * @group KOMP-061
	 * @group KOMP-062
	 * @group KOMP-063
	 */
	public function test_KOMP_060_063_seo_plugins(): void {
		global $wp_filter;
		self::assertSame( 'none', CourseSeo::mode() );
		$course   = (int) self::factory()->post->create(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
			)
		);
		$expected = array(
			'yoast'             => 'yoast',
			'rank_math'         => 'rank_math',
			'aioseo'            => 'other',
			'seopress'          => 'other',
			'the-seo-framework' => 'other',
		);
		foreach ( ForeignPlugins::seo() as $slug => $plugin ) {
			$plugin->activate();
			self::assertSame( $expected[ $slug ], CourseSeo::mode(), $slug );
			$this->go_to( (string) get_permalink( $course ) );
			ob_start();
			CourseSeo::json_ld();
			self::assertSame( '', (string) ob_get_clean(), $slug . ': no second Course graph' );
			$plugin->deactivate();
		}
		foreach ( array_keys( (array) $wp_filter ) as $hook ) {
			self::assertDoesNotMatchRegularExpression( '/^(pre_)?(update_)?option_(wpseo|rank_math|aioseo|seopress|autodescription)/', (string) $hook, 'no filters on foreign SEO options' );
		}
	}

	/**
	 * KOMP-101 / KOMP-102 (D-20-10): coexistence with WooCommerce: no Woo
	 * hooks, no assets, `shop_manager` without `ccap_*` caps; CC roles keep
	 * wp-admin under the Woo admin lock.
	 *
	 * @group KOMP-101
	 * @group KOMP-102
	 */
	public function test_KOMP_101_102_woocommerce(): void {
		global $wp_filter;
		ForeignPlugins::woocommerce()->activate();
		Plugin::activate( false );
		self::assertSame( array(), self::ccap_caps_of( 'shop_manager' ), 'D07-6' );

		foreach ( Roles::ALL as $role ) {
			$user = (int) self::factory()->user->create( array( 'role' => $role ) );
			self::assertTrue( user_can( $user, 'view_admin_dashboard' ), $role ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- Shop capability granted by D-20-10.
			self::assertFalse( ForeignPlugins::woo_blocks_admin( $user ), $role . ' reaches wp-admin' );
		}
		self::assertTrue( ForeignPlugins::woo_blocks_admin( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) ), 'fixture sanity' );

		foreach ( (array) $wp_filter as $hook => $callbacks ) {
			if ( 1 !== preg_match( '/^(woocommerce_|wc_|before_woocommerce|after_woocommerce)/', (string) $hook ) ) {
				continue;
			}
			foreach ( $callbacks->callbacks as $by_priority ) {
				foreach ( $by_priority as $entry ) {
					self::assertFalse( Compatibility::own_callback( $entry['function'] ), 'CC hooks into ' . $hook );
				}
			}
		}

		$this->go_to( home_url( '/' ) );
		do_action( 'wp_enqueue_scripts' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		foreach ( array_merge( wp_scripts()->queue, wp_styles()->queue ) as $handle ) {
			self::assertStringNotContainsString( 'course-captain', (string) $handle, 'no CC assets on shop pages' );
		}

		$woo = array_values( array_filter( IntegrationStatus::all( Actor::user( $this->admin ) ), static fn( array $r ): bool => 'woocommerce' === $r['provider_id'] ) );
		self::assertSame( IntegrationStatus::ACTIVE_COMPATIBLE, $woo[0]['state'], 'read-only detection, no exchange in R1' );
	}

	/**
	 * KOMP-091 (D-25-06): with Polylang the own post types are not translatable.
	 *
	 * @group KOMP-091
	 */
	public function test_KOMP_091_polylang(): void {
		self::assertNull( Compatibility::polylang_translated_types() );
		ForeignPlugins::polylang()->activate();
		self::assertSame( array(), Compatibility::polylang_translated_types() );
		self::assertSame( 'info', $this->check( 'translation' )['status'] );

		remove_filter( 'pll_get_post_types', array( Compatibility::class, 'polylang_post_types' ), 99 );
		self::assertCount( 5, (array) Compatibility::polylang_translated_types(), 'without the CC filter' );
		self::assertSame( 'warn', $this->check( 'translation' )['status'] );
	}

	/**
	 * KOMP-073: Elementor "Element Caching" is reported (critical when active).
	 *
	 * @group KOMP-073
	 */
	public function test_KOMP_073_elementor_element_cache(): void {
		self::assertSame( 'ok', $this->check( 'element_cache' )['status'] );
		foreach ( array(
			'active'   => 'fail',
			'inactive' => 'ok',
			'default'  => 'warn',
		) as $state => $status ) {
			$plugin = ForeignPlugins::elementor( 'default' === $state ? null : $state )->activate();
			self::assertSame( $state, Compatibility::elementor_element_cache() );
			self::assertSame( $status, $this->check( 'element_cache' )['status'], $state );
			$plugin->deactivate();
		}
	}

	/**
	 * KOMP-072: Elementor layout data of a protected post is not handed out (LB-K-07 behaviour) (v).
	 *
	 * @group KOMP-072
	 */
	public function test_KOMP_072_elementor_layout_gate(): void {
		ForeignPlugins::elementor( 'inactive' )->activate();
		$course = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'KOMP-072',
				'post_author' => $this->admin,
			)
		);
		$page   = (int) self::factory()->post->create( array( 'post_type' => 'page' ) );
		$open   = (int) self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, ContentProtection::META, $course );
		$layout = array(
			array(
				'id'     => 'a1',
				'elType' => 'section',
			),
		);
		$hook   = 'elementor/frontend/builder_content_data';
		wp_set_current_user( 0 );
		self::assertSame( array(), apply_filters( $hook, $layout, $page ), 'guest gets no layout' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Foreign filter emulated.
		self::assertSame( $layout, apply_filters( $hook, $layout, $open ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Foreign filter emulated.
		wp_set_current_user( $this->admin );
		self::assertSame( $layout, apply_filters( $hook, $layout, $page ), 'editor sees the layout' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Foreign filter emulated.
	}

	/**
	 * KOMP-120: DISABLE_WP_CRON without a server cron warns; deadlines are
	 * checked on access without any cron run.
	 *
	 * @group KOMP-120
	 */
	public function test_KOMP_120_wp_cron_disabled(): void {
		$enabled = ForeignPlugins::wp_cron_enabled()->activate();
		self::assertSame( 'ok', $this->check( 'wp_cron' )['status'] );
		$enabled->deactivate();
		ForeignPlugins::wp_cron_disabled()->activate();
		delete_option( HealthChecks::STATE_OPTION );
		self::assertSame( 'warn', $this->check( 'wp_cron' )['status'] );
		update_option( HealthChecks::STATE_OPTION, array( 'last_run' => time() ) );
		self::assertSame( 'info', $this->check( 'wp_cron' )['status'], 'server cron runs' );

		$learner = (int) self::factory()->user->create();
		$course  = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'KOMP-120',
				'post_author' => $this->admin,
			)
		);
		$db      = Database::current();
		$grant   = GrantService::current()->grant_manual( Actor::user( $this->admin ), $learner, $course, wp_generate_uuid4(), 'x', array( 'valid_until' => $db->now( 60 ) ) );
		PolicyService::flush();
		self::assertTrue( PolicyService::instance()->decide( Actor::user( $learner ), PolicyService::LEARN, array( $course ) )->allowed );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . '` SET valid_until = %s WHERE id = %d', $db->now( -1 ), $grant ) );
		PolicyService::flush();
		wp_cache_flush();
		self::assertFalse( PolicyService::instance()->decide( Actor::user( $learner ), PolicyService::LEARN, array( $course ) )->allowed, 'expired without cron run' );
	}

	/**
	 * KOMP-016 (D-20-08): plain permalinks: query fallback for the learning
	 * area and protected files, warning in the status; pretty links otherwise.
	 *
	 * @group KOMP-016
	 */
	public function test_KOMP_016_plain_permalinks(): void {
		$this->set_permalink_structure( '' );
		$url = LearningArea::url( 'kurs/abc' );
		self::assertStringContainsString( '?' . LearningArea::QUERY_VAR . '=', $url );
		$this->go_to( $url );
		self::assertSame( 'kurs/abc', get_query_var( LearningArea::QUERY_VAR ) );
		self::assertSame( 'warn', $this->check( 'permalinks' )['status'] );

		$db  = Database::current();
		$uid = $db->uuid();
		$id  = (int) self::factory()->attachment->create();
		$db->insert(
			'ccap_media',
			array(
				'uid'           => $uid,
				'attachment_id' => $id,
				'access'        => 'protected',
				'status'        => 'active',
				'sha256'        => str_repeat( 'a', 64 ),
			),
			array( 'created_at', 'updated_at' )
		);
		self::assertSame( add_query_arg( \CourseCaptain\Access\ProtectedFiles::QUERY_VAR, $uid, home_url( '/' ) ), \CourseCaptain\Access\ProtectedFiles::attachment_url( 'x', $id ) );

		$this->set_permalink_structure( '/%postname%/' );
		$url = LearningArea::url( 'kurs/abc' );
		self::assertSame( home_url( '/lernen/kurs/abc' ), $url );
		$this->go_to( $url );
		self::assertSame( 'kurs/abc', get_query_var( LearningArea::QUERY_VAR ) );
		self::assertSame( 'ok', $this->check( 'permalinks' )['status'] );
		self::assertSame( home_url( '/cc-file/' . $uid . '/' ), \CourseCaptain\Access\ProtectedFiles::attachment_url( 'x', $id ) );
	}

	/**
	 * KOMP-015 / KOMP-045: forwarding headers count only from trusted proxies.
	 *
	 * @group KOMP-015
	 * @group KOMP-045
	 * @group GM-152
	 */
	public function test_KOMP_015_045_trusted_proxies(): void {
		$_SERVER['REMOTE_ADDR']            = '10.1.2.3';
		$_SERVER['HTTP_X_FORWARDED_FOR']   = '198.51.100.7, 10.0.0.9';
		$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
		unset( $_SERVER['HTTPS'], $_SERVER['HTTP_CF_CONNECTING_IP'] );
		self::assertSame( '10.1.2.3', ClientIp::get(), 'safe default: remote address' );
		self::assertFalse( ClientIp::forwarded_https() );

		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'trusted_proxies' => array( '10.0.0.0/8' ) ), array( 'trusted_proxies' ), Settings::rev( 'privacy' ) );
		self::assertSame( '198.51.100.7', ClientIp::get(), 'rightmost untrusted hop' );
		self::assertTrue( ClientIp::forwarded_https() );
		ClientIp::apply_forwarded_https();
		self::assertTrue( is_ssl() );

		$_SERVER['HTTP_CF_CONNECTING_IP'] = '192.0.2.44';
		self::assertSame( '192.0.2.44', ClientIp::get(), 'CF-Connecting-IP from a trusted proxy' );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		self::assertSame( '203.0.113.9', ClientIp::get(), 'spoofed headers from an untrusted peer are ignored' );

		self::assertTrue( ClientIp::in_range( '2001:db8::1', '2001:db8::/32' ) );
		self::assertFalse( ClientIp::in_range( '2001:db9::1', '2001:db8::/32' ) );
		self::assertTrue( ClientIp::in_range( '10.255.0.1', '10.0.0.0/8' ) );
		self::assertFalse( ClientIp::in_range( '11.0.0.1', '10.0.0.0/8' ) );
		self::assertFalse( ClientIp::in_range( '10.0.0.1', '2001:db8::/32' ) );
	}

	/**
	 * GM-152: IPv6 clients share a limit per /64 (2001:db8::1 and ::2);
	 * forwarding headers of an untrusted peer are ignored; behind the trusted
	 * proxy 10.0.0.0/8 the rightmost untrusted X-Forwarded-For entry counts.
	 * Checked on the login limit (5 per account+IP and 15 min, fixed clock).
	 *
	 * @group R1-18
	 * @group GM-152
	 */
	public function test_GM_152_ipv6_prefix_shares_the_limit(): void {
		self::assertSame( ClientIp::rate_key_of( '2001:db8::1' ), ClientIp::rate_key_of( '2001:db8::2' ) );
		self::assertSame( ClientIp::rate_key_of( '2001:db8::1' ), ClientIp::rate_key_of( '2001:0db8:0000:0000:ffff:ffff:ffff:ffff' ) );
		self::assertNotSame( ClientIp::rate_key_of( '2001:db8::1' ), ClientIp::rate_key_of( '2001:db8:0:1::1' ), 'next /64' );
		self::assertSame( '198.51.100.7', ClientIp::rate_key_of( '198.51.100.7' ), 'IPv4 per address' );

		self::factory()->user->create(
			array(
				'user_login' => 'gm152',
				'user_pass'  => 'right-password',
			)
		);
		$values = array_fill_keys( Hardening::FEATURES, 'auto' );
		Settings::save( Actor::user( $this->admin ), 'hardening', $values, array_keys( $values ), Settings::rev( 'hardening' ) );
		Hardening::set_now( 1800000000 );
		Hardening::apply();
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_CF_CONNECTING_IP'] );
		$login = static function ( string $remote, string $password = 'right-password', string $forwarded = '' ) {
			$_SERVER['REMOTE_ADDR']          = $remote;
			$_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded;
			return wp_authenticate( 'gm152', $password );
		};

		for ( $i = 0; $i < 5; $i++ ) {
			self::assertInstanceOf( \WP_Error::class, $login( '2001:db8::1', 'wrong' ) );
		}
		$shared = $login( '2001:db8::2' );
		self::assertInstanceOf( \WP_Error::class, $shared, 'same /64 shares the limit' );
		self::assertSame( Hardening::ERROR_CODE, $shared->get_error_code() );
		self::assertInstanceOf( \WP_User::class, $login( '2001:db8:0:1::1' ), 'other /64' );
		self::assertInstanceOf( \WP_User::class, $login( '2001:db8:0:2::1', 'right-password', '2001:db8::7' ), 'X-Forwarded-For of an untrusted peer ignored' );

		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'trusted_proxies' => array( '10.0.0.0/8' ) ), array( 'trusted_proxies' ), Settings::rev( 'privacy' ) );
		$locked = $login( '10.1.2.3', 'right-password', '2001:db8::7, 10.0.0.9' );
		self::assertInstanceOf( \WP_Error::class, $locked, 'rightmost untrusted entry (2001:db8::7) counts' );
		self::assertSame( Hardening::ERROR_CODE, $locked->get_error_code() );
		self::assertInstanceOf( \WP_User::class, $login( '10.1.2.3', 'right-password', '2001:db8::7, 203.0.113.5, 10.0.0.9' ), 'rightmost untrusted entry is 203.0.113.5' );
	}

	/**
	 * KOMP-013/014/030/032/033/035/037/041: protected HTML responses carry
	 * every page-cache signal (DONOTCACHEPAGE, LiteSpeed action and header,
	 * X-Accel-Expires, Surrogate-/CDN-Cache-Control, no-store).
	 *
	 * @group KOMP-032
	 * @group KOMP-033
	 * @group KOMP-035
	 * @group GM-116
	 */
	public function test_KOMP_03x_page_cache_signals(): void {
		$headers = ContentProtection::uncacheable_headers();
		self::assertSame( 'no-store, private', $headers['Cache-Control'] );
		self::assertSame( 'no-cache', $headers['X-LiteSpeed-Cache-Control'] );
		self::assertSame( '0', $headers['X-Accel-Expires'] );
		self::assertSame( 'no-store', $headers['Surrogate-Control'] );
		self::assertSame( 'no-store', $headers['CDN-Cache-Control'] );
		self::assertArrayNotHasKey( 'X-Robots-Tag', $headers, 'robots stay with the SEO layer' );

		$before = did_action( 'litespeed_control_set_nocache' );
		ContentProtection::mark_uncacheable();
		self::assertTrue( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE );
		self::assertSame( $before + 1, did_action( 'litespeed_control_set_nocache' ) );
	}

	/**
	 * Site Health: one compatibility test, critical on a critical finding.
	 *
	 * @group KOMP-034
	 * @group KOMP-073
	 */
	public function test_site_health_compatibility(): void {
		$tests = apply_filters( 'site_status_tests', array( 'direct' => array() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertArrayHasKey( 'course_captain_compatibility', $tests['direct'] );
		$this->set_permalink_structure( '/%postname%/' );
		ForeignPlugins::wp_cron_enabled()->activate();
		self::assertSame( 'good', Compatibility::site_health()['status'] );
		ForeignPlugins::elementor( 'active' )->activate();
		$result = Compatibility::site_health();
		self::assertSame( 'critical', $result['status'] );
		self::assertStringContainsString( 'Element Caching', (string) $result['description'] );
	}
}
