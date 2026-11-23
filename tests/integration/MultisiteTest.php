<?php
/**
 * Integration tests of the multisite boundary (R0-25 §1.2, MS-001…MS-015).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Infrastructure\Migration\NullMigrationRunner;
use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\HoldMode;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Kernel\UninstallManifest;
use WPDieException;

/**
 * Runs only with WP_TESTS_MULTISITE=1 (composer test:integration-ms).
 *
 * @group R1-01
 * @group ms-required
 */
final class MultisiteTest extends TestCase {

	/**
	 * Second site of the network.
	 *
	 * @var int
	 */
	private int $site2 = 0;

	/**
	 * Create a second site; start without own cron events on both sites.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->site2 = (int) self::factory()->blog->create();
		foreach ( array( get_main_site_id(), $this->site2 ) as $site ) {
			$this->on_site(
				$site,
				static function (): void {
					self::clear_own_cron();
					// Written by the booted test copy of the plugin on `init`, not by an activation.
					delete_option( Plugin::REWRITE_VERSION_OPTION );
					// Runtime state of the booted copy (hardening status on `init`, backfill signal of earlier runs).
					delete_option( 'course_captain_hardening_state' );
					delete_option( \CourseCaptain\Storage\Migrator::BACKFILL_OPEN );
				}
			);
		}
	}

	/**
	 * Run a callback on a site and always switch back (MS-R-07).
	 *
	 * @template T
	 * @param int           $site     Site ID.
	 * @param callable(): T $callback Callback.
	 * @return T
	 */
	private function on_site( int $site, callable $callback ) {
		switch_to_blog( $site );
		try {
			wp_roles()->for_site( $site );
			return $callback();
		} finally {
			restore_current_blog();
			wp_roles()->for_site( get_current_blog_id() );
		}
	}

	/**
	 * Snapshot of everything an activation could write on a site.
	 *
	 * @param int $site Site ID.
	 * @return array{caps: list<string>, cron: list<string>, options: list<string>}
	 */
	private function footprint( int $site ): array {
		return $this->on_site(
			$site,
			static fn(): array => array(
				'caps'    => self::ccap_caps_of( 'administrator' ),
				'cron'    => self::own_cron_events(),
				'options' => self::own_options(),
			)
		);
	}

	/**
	 * Empty footprint.
	 *
	 * @return array{caps: list<string>, cron: list<string>, options: list<string>}
	 */
	private static function nothing(): array {
		return array(
			'caps'    => array(),
			'cron'    => array(),
			'options' => array(),
		);
	}

	/**
	 * MS-001 / KOMP-001: network activation via activate_plugin( …, true ) aborts with
	 * a message before anything is written on any site.
	 *
	 * @group MS-001
	 * @group KOMP-001
	 */
	public function test_MS_001_network_activation_is_blocked_before_any_write(): void {
		$sitewide_before = get_site_option( 'active_sitewide_plugins', array() );
		$level           = ob_get_level();
		$message         = '';

		try {
			activate_plugin( self::basename(), '', true );
		} catch ( WPDieException $e ) {
			$message = $e->getMessage();
		} finally {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
		}

		self::assertStringContainsString( 'cannot be network activated', $message );
		self::assertSame( $sitewide_before, get_site_option( 'active_sitewide_plugins', array() ) );
		self::assertArrayNotHasKey( self::basename(), (array) get_site_option( 'active_sitewide_plugins', array() ) );
		self::assertSame( self::nothing(), $this->footprint( get_main_site_id() ) );
		self::assertSame( self::nothing(), $this->footprint( $this->site2 ) );
	}

	/**
	 * MS-001: the activation error page in the network admin re-runs the hook without
	 * arguments; it is refused as well and writes nothing.
	 *
	 * @group MS-001
	 */
	public function test_MS_001_error_scrape_in_network_admin_is_blocked(): void {
		set_current_screen( 'plugins-network' );
		$_GET['action'] = 'error_scrape';
		$message        = '';
		try {
			Plugin::activate();
		} catch ( WPDieException $e ) {
			$message = $e->getMessage();
		} finally {
			unset( $_GET['action'] );
			set_current_screen( 'front' );
		}

		self::assertStringContainsString( 'cannot be network activated', $message );
		self::assertSame( self::nothing(), $this->footprint( get_main_site_id() ) );
	}

	/**
	 * MS-003: the "Network Activate" link is removed from the network plugin list.
	 *
	 * @group MS-003
	 */
	public function test_MS_003_network_activate_link_is_removed(): void {
		$links = apply_filters(
			'network_admin_plugin_action_links_' . self::basename(), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook under test (MS-003).
			array(
				'activate' => '<a href="#">Network Activate</a>',
				'edit'     => '<a href="#">Edit</a>',
			),
			self::basename(),
			array(),
			'all'
		);

		self::assertSame( array( 'edit' => '<a href="#">Edit</a>' ), $links );
	}

	/**
	 * MS-004 / KOMP-001: forced network activation boots in hold mode (no routes,
	 * no migration, no cron, notice); removing the entry restores normal operation
	 * without data loss.
	 *
	 * @group MS-004
	 * @group KOMP-001
	 */
	public function test_MS_004_forced_network_activation_hold_mode(): void {
		Plugin::activate( false );
		$grants_before = Capabilities::recorded_grants();
		$loaded_before = did_action( 'course_captain_loaded_v1' );

		$sitewide                     = (array) get_site_option( 'active_sitewide_plugins', array() );
		$sitewide[ self::basename() ] = time();
		update_site_option( 'active_sitewide_plugins', $sitewide );

		$held = new Plugin( self::basename(), new NullMigrationRunner() );
		$held->boot();

		self::assertFalse( $held->is_running() );
		self::assertTrue( $held->hold_mode()->has( HoldMode::REASON_NETWORK_FORCED ) );
		self::assertSame( $loaded_before, did_action( 'course_captain_loaded_v1' ) );
		self::assertNotFalse( has_action( 'admin_notices', array( $held, 'render_hold_mode_notice' ) ) );
		self::assertNotFalse( has_action( 'network_admin_notices', array( $held, 'render_hold_mode_notice' ) ) );
		self::assertFalse( has_action( 'admin_init', array( $held, 'on_admin_init' ) ), 'No migration trigger.' );
		self::assertFalse( has_action( 'init', array( $held, 'maybe_flush_rewrite_rules' ) ), 'No rewrite rules.' );
		self::assertFalse( has_action( 'upgrader_process_complete', array( $held, 'on_upgrader_process_complete' ) ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		grant_super_admin( get_current_user_id() );
		ob_start();
		$held->render_hold_mode_notice();
		self::assertStringContainsString( 'hold mode', (string) ob_get_clean() );

		unset( $sitewide[ self::basename() ] );
		update_site_option( 'active_sitewide_plugins', $sitewide );

		$normal = new Plugin( self::basename(), new NullMigrationRunner() );
		$normal->boot();

		self::assertTrue( $normal->is_running() );
		self::assertSame( $grants_before, Capabilities::recorded_grants(), 'No data loss.' );
		self::assertSame( Capabilities::PRIMITIVE, self::ccap_caps_of( 'administrator' ) );
	}

	/**
	 * MS-005 (R1-01 part, tables follow in R1-02): activation on site 2 writes only to site 2.
	 *
	 * @group MS-005
	 */
	public function test_MS_005_activation_on_site_2_is_isolated(): void {
		$this->on_site( $this->site2, static fn() => Plugin::activate( false ) );

		$site2 = $this->footprint( $this->site2 );
		self::assertSame( Capabilities::PRIMITIVE, $site2['caps'] );
		self::assertSame( array( CronHooks::HOURLY ), $site2['cron'] );
		self::assertSame( array( Capabilities::GRANTS_OPTION ), $site2['options'] );
		self::assertSame( self::nothing(), $this->footprint( get_main_site_id() ) );
	}

	/**
	 * MS-012: a new site created while the plugin is active on site 1 gets nothing.
	 *
	 * @group MS-012
	 */
	public function test_MS_012_new_site_gets_nothing(): void {
		Plugin::activate( false );

		$site3 = (int) self::factory()->blog->create();

		self::assertSame( self::nothing(), $this->footprint( $site3 ) );
		self::assertSame( array( CronHooks::HOURLY ), $this->footprint( get_main_site_id() )['cron'] );
	}

	/**
	 * MS-014: deactivation on site 2 removes only the cron events of site 2 and keeps data;
	 * reactivation plans exactly one event again.
	 *
	 * @group MS-014
	 */
	public function test_MS_014_deactivation_is_per_site(): void {
		Plugin::activate( false );
		$this->on_site( $this->site2, static fn() => Plugin::activate( false ) );

		$this->on_site( $this->site2, static fn() => Plugin::deactivate() );

		$site2 = $this->footprint( $this->site2 );
		self::assertSame( array(), $site2['cron'] );
		self::assertSame( Capabilities::PRIMITIVE, $site2['caps'], 'Data stays.' );
		self::assertSame( array( Capabilities::GRANTS_OPTION ), $site2['options'] );
		self::assertSame( array( CronHooks::HOURLY ), $this->footprint( get_main_site_id() )['cron'] );

		$this->on_site( $this->site2, static fn() => Plugin::activate( false ) );
		self::assertSame( array( CronHooks::HOURLY ), $this->footprint( $this->site2 )['cron'] );
	}

	/**
	 * MS-015 (decision part, full probe in R1-23): uninstall deletes data only on
	 * the site that confirmed the deletion.
	 *
	 * @group MS-015
	 */
	public function test_MS_015_uninstall_deletes_only_confirmed_site(): void {
		Plugin::activate( false );
		update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( 'delete_data_on_uninstall' => true ) ) );
		$this->on_site( $this->site2, static fn() => Plugin::activate( false ) );

		$result = Uninstaller::run();

		self::assertTrue( $result[ get_main_site_id() ] );
		self::assertFalse( $result[ $this->site2 ] );
		self::assertSame( self::nothing(), $this->footprint( get_main_site_id() ) );

		$site2 = $this->footprint( $this->site2 );
		self::assertSame( Capabilities::PRIMITIVE, $site2['caps'] );
		self::assertSame( array(), $site2['cron'] );
		self::assertSame( array( Capabilities::GRANTS_OPTION ), $site2['options'] );
		self::assertSame( get_main_site_id(), get_current_blog_id() );
	}
}
