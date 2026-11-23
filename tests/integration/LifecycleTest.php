<?php
/**
 * Integration tests of bootstrap, activation and deactivation.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Infrastructure\Migration\MigrationRunner;
use CourseCaptain\Infrastructure\Migration\NullMigrationRunner;
use CourseCaptain\Kernel\Activator;
use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\EnvironmentSnapshot;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\Requirements;
use RuntimeException;
use WPDieException;

/**
 * R1-01 life cycle (BS-01): GM-158, E-83, R0-20 §1.2, R0-37 T-1…T-3.
 *
 * @group R1-01
 */
final class LifecycleTest extends TestCase {

	/**
	 * Start every test from a site without own cron events and capabilities.
	 */
	public function set_up(): void {
		parent::set_up();
		self::clear_own_cron();
	}

	/**
	 * Spy that records which trigger point was called.
	 *
	 * @param bool $fail_on_activation Throw in run_on_activation().
	 * @return MigrationRunner&object{calls: list<string>}
	 */
	private static function spy( bool $fail_on_activation = false ): MigrationRunner {
		return new class( $fail_on_activation ) implements MigrationRunner {
			/**
			 * Recorded calls.
			 *
			 * @var list<string>
			 */
			public array $calls = array();

			/**
			 * Constructor.
			 *
			 * @param bool $fail Throw on activation.
			 */
			public function __construct( private bool $fail ) {
			}

			/**
			 * T-1.
			 *
			 * @throws RuntimeException When configured to fail.
			 */
			public function run_on_activation(): void {
				$this->calls[] = 'activation';
				if ( $this->fail ) {
					throw new RuntimeException( 'migration failed' );
				}
			}

			/**
			 * T-2.
			 */
			public function schedule_after_update(): void {
				$this->calls[] = 'update';
			}

			/**
			 * T-3.
			 */
			public function maybe_run_in_admin(): void {
				$this->calls[] = 'admin';
			}

			/**
			 * T-5.
			 */
			public function run_cron(): void {
				$this->calls[] = 'cron';
			}

			/**
			 * T-4.
			 */
			public function on_request(): void {
				$this->calls[] = 'request';
			}

			/**
			 * KOMP-131.
			 *
			 * @return list<string>
			 */
			public function conflicting_tables(): array {
				return array();
			}
		};
	}

	/**
	 * Run an activation that is expected to die, and return the message.
	 *
	 * @param Activator $activator Activator.
	 * @return string
	 */
	private static function expect_die( Activator $activator ): string {
		try {
			$activator->activate( false );
		} catch ( WPDieException $e ) {
			return $e->getMessage();
		}
		self::fail( 'Activation was expected to abort.' );
	}

	/**
	 * Assert that the site carries no trace of an activation.
	 */
	private static function assert_nothing_written(): void {
		self::assertSame( array(), self::ccap_caps_of( 'administrator' ) );
		self::assertSame( array(), self::own_cron_events() );
		self::assertFalse( get_option( Capabilities::GRANTS_OPTION ) );
	}

	/**
	 * Boot: requirements met, no hold mode, handshake fired (R0-19 §1.6).
	 */
	public function test_boot_fires_loaded_hook_and_registers_handlers(): void {
		$plugin = Plugin::instance();

		self::assertInstanceOf( Plugin::class, $plugin );
		self::assertTrue( $plugin->is_running() );
		self::assertSame( array(), $plugin->requirement_failures() );
		self::assertSame( 1, did_action( 'course_captain_loaded_v1' ) );
		self::assertTrue( function_exists( 'course_captain_contract_version' ) );
		self::assertSame( COURSE_CAPTAIN_CONTRACT_VERSION, course_captain_contract_version() );
		self::assertFalse( course_captain_supports( 'events.v1' ) );
		self::assertNotFalse( has_action( CronHooks::HOURLY, array( CronHooks::class, 'run_hourly' ) ) );
	}

	/**
	 * The real test database meets the requirements (MariaDB 10.11 / MySQL 8.4 in CI).
	 *
	 * @group KOMP-001
	 */
	public function test_live_environment_meets_requirements(): void {
		$env = EnvironmentSnapshot::with_database();

		self::assertSame( array(), Requirements::evaluate( $env ) );
		self::assertNotSame( Requirements::LEVEL_UNSUPPORTED, Requirements::database_support_level( (string) $env->db_server_version ) );
		self::assertTrue( $env->innodb );
		self::assertTrue( $env->json_functions );
		self::assertTrue( $env->utf8mb4 );
	}

	/**
	 * GM-158 + E-83: activation grants caps only to administrator and plans cron;
	 * deactivation removes all own events (also single ones) and keeps data;
	 * reactivation plans again.
	 *
	 * @group GM-158
	 * @group GM-153
	 */
	public function test_GM_158_activation_deactivation_reactivation(): void {
		Plugin::activate( false );

		self::assertSame( Capabilities::PRIMITIVE, self::ccap_caps_of( 'administrator' ) );
		foreach ( array( 'editor', 'author', 'contributor', 'subscriber' ) as $role ) {
			self::assertSame( array(), self::ccap_caps_of( $role ), $role . ' must not receive caps (E-83).' );
		}
		self::assertSame( array( 'administrator' => Capabilities::PRIMITIVE ), Capabilities::recorded_grants() );
		self::assertSame( array( CronHooks::HOURLY ), self::own_cron_events() );

		wp_schedule_single_event( time() + 60, CronHooks::TASK_CONTINUE, array( 'task', 5 ) );
		wp_schedule_single_event( time() + 120, CronHooks::MIGRATE );
		self::assertCount( 3, self::own_cron_events() );

		Plugin::deactivate();

		self::assertSame( array(), self::own_cron_events() );
		self::assertSame( Capabilities::PRIMITIVE, self::ccap_caps_of( 'administrator' ), 'Deactivation keeps data.' );
		self::assertNotFalse( get_option( Capabilities::GRANTS_OPTION ) );

		Plugin::activate( false );
		self::assertSame( array( CronHooks::HOURLY ), self::own_cron_events() );
	}

	/**
	 * Activation is idempotent: no duplicate events or capability records.
	 */
	public function test_activation_is_idempotent(): void {
		Plugin::activate( false );
		Plugin::activate( false );

		self::assertSame( array( CronHooks::HOURLY ), self::own_cron_events() );
		self::assertSame( array( 'administrator' => Capabilities::PRIMITIVE ), Capabilities::recorded_grants() );
	}

	/**
	 * RC-047 / R0-20 §1.2: below the hard minimum the activation aborts with a
	 * message before anything is written.
	 *
	 * @group RC-047
	 */
	public function test_RC_047_activation_aborts_on_unmet_requirements(): void {
		$activator = new Activator(
			new NullMigrationRunner(),
			static fn(): EnvironmentSnapshot => new EnvironmentSnapshot( '8.2.29', '6.9', '10.5.9-MariaDB', true, true, true )
		);

		$message = self::expect_die( $activator );

		self::assertStringContainsString( '8.2.29', $message );
		self::assertStringContainsString( '6.9', $message );
		self::assertStringContainsString( '10.5.9-MariaDB', $message );
		self::assert_nothing_written();
	}

	/**
	 * R0-37 T-1: a failing migration aborts before capabilities, cron and rewrite rules.
	 */
	public function test_failing_migration_aborts_before_caps_and_cron(): void {
		$spy = self::spy( true );
		update_option( Plugin::REWRITE_VERSION_OPTION, Plugin::REWRITE_VERSION );

		self::expect_die( new Activator( $spy ) );

		self::assertSame( array( 'activation' ), $spy->calls );
		self::assert_nothing_written();
		self::assertSame( Plugin::REWRITE_VERSION, get_option( Plugin::REWRITE_VERSION_OPTION ) );
	}

	/**
	 * The activation error page (action=error_scrape) re-runs the hook but never writes.
	 * A normal activation still writes although WordPress defines WP_SANDBOX_SCRAPING.
	 */
	public function test_error_scrape_run_writes_nothing(): void {
		set_current_screen( 'plugins' );
		$_GET['action'] = 'error_scrape';
		try {
			Plugin::activate();
		} finally {
			unset( $_GET['action'] );
			set_current_screen( 'front' );
		}
		self::assert_nothing_written();

		if ( ! defined( 'WP_SANDBOX_SCRAPING' ) ) {
			define( 'WP_SANDBOX_SCRAPING', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core constant, set like activate_plugin() does.
		}
		Plugin::activate( false );
		self::assertSame( Capabilities::PRIMITIVE, self::ccap_caps_of( 'administrator' ) );
	}

	/**
	 * Activation requests a one-time rewrite flush on the next request.
	 */
	public function test_rewrite_flush_after_activation(): void {
		update_option( Plugin::REWRITE_VERSION_OPTION, Plugin::REWRITE_VERSION );
		Plugin::activate( false );
		self::assertFalse( get_option( Plugin::REWRITE_VERSION_OPTION ) );

		$plugin = Plugin::instance();
		self::assertInstanceOf( Plugin::class, $plugin );
		$plugin->maybe_flush_rewrite_rules();

		self::assertSame( Plugin::REWRITE_VERSION, get_option( Plugin::REWRITE_VERSION_OPTION ) );
	}

	/**
	 * R0-37 T-2/T-3: update only schedules, admin_init runs, Ajax and foreign updates are ignored.
	 */
	public function test_migration_trigger_points(): void {
		$spy    = self::spy();
		$plugin = new Plugin( self::basename(), $spy );

		$plugin->on_upgrader_process_complete(
			null,
			array(
				'type'    => 'plugin',
				'plugins' => array( 'other/other.php' ),
			)
		);
		$plugin->on_upgrader_process_complete(
			null,
			array(
				'type'    => 'theme',
				'plugins' => array( self::basename() ),
			)
		);
		self::assertSame( array(), $spy->calls );

		$plugin->on_upgrader_process_complete(
			null,
			array(
				'type'    => 'plugin',
				'plugins' => array( self::basename() ),
			)
		);
		$plugin->on_admin_init();
		self::assertSame( array( 'update', 'admin' ), $spy->calls );

		add_filter( 'wp_doing_ajax', '__return_true' );
		$plugin->on_admin_init();
		remove_filter( 'wp_doing_ajax', '__return_true' );
		self::assertSame( array( 'update', 'admin' ), $spy->calls );
	}

	/**
	 * LIC-007: no Course Captain callback on updater hooks (FG-02).
	 *
	 * @group LIC-007
	 */
	public function test_LIC_007_no_updater_hooks(): void {
		global $wp_filter;

		$updater_hooks = array( 'pre_set_site_transient_update_plugins', 'site_transient_update_plugins', 'plugins_api', 'upgrader_pre_download', 'upgrader_source_selection' );
		foreach ( $updater_hooks as $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					$owner    = is_array( $function ) ? ( is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0] ) : ( is_string( $function ) ? $function : '' );
					self::assertStringNotContainsString( 'CourseCaptain', $owner, $hook );
					self::assertStringNotContainsString( 'course_captain', $owner, $hook );
				}
			}
		}
	}

	/**
	 * LIC-008 / SA-001 (partial): after activation there are no license options and no outbound requests.
	 *
	 * @group LIC-008
	 * @group SA-001
	 */
	public function test_LIC_008_activation_creates_no_license_state(): void {
		Plugin::activate( false );

		foreach ( self::own_options() as $option ) {
			self::assertDoesNotMatchRegularExpression( '/wplc_|licen[cs]e/i', $option );
		}
		// The hardening state is system state written on `init` (R1-45), depending on test order.
		self::assertSame( array( Capabilities::GRANTS_OPTION ), array_values( array_diff( self::own_options(), array( \CourseCaptain\Security\Hardening::STATE_OPTION ) ) ) );
		self::assertSame( array(), $GLOBALS['ccap_test_http_requests'] );
	}
}
