<?php
/**
 * Base class of the integration tests.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Infrastructure\Migration\NullMigrationRunner;
use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\CronHooks;
use WP_UnitTestCase;

/**
 * Shared helpers; fails every test that made an outbound HTTP request (LG-1).
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Reload roles from the (rolled back) database and reset the HTTP recorder.
	 */
	public function set_up(): void {
		parent::set_up();
		// Lifecycle tests run without schema DDL (DDL would commit the test transaction);
		// the schema itself is covered by SchemaTestCase subclasses.
		Plugin::override_migration_runner( new NullMigrationRunner() );
		wp_roles()->for_site( get_current_blog_id() );
		$GLOBALS['ccap_test_http_requests'] = array();
	}

	/**
	 * LG-1: no outbound request happened during the test.
	 */
	public function tear_down(): void {
		$requests                           = $GLOBALS['ccap_test_http_requests'];
		$GLOBALS['ccap_test_http_requests'] = array();
		Plugin::override_migration_runner( null );
		parent::tear_down();
		wp_roles()->for_site( get_current_blog_id() );
		$this->assertSame( array(), $requests, 'Outbound HTTP requests are not allowed (LG-1).' );
	}

	/**
	 * Plugin basename as WordPress sees it in the test installation.
	 *
	 * @return string
	 */
	protected static function basename(): string {
		return plugin_basename( COURSE_CAPTAIN_FILE );
	}

	/**
	 * Course Captain capabilities present on a role of the current site.
	 *
	 * @param string $role_name Role.
	 * @return list<string>
	 */
	protected static function ccap_caps_of( string $role_name ): array {
		$role = get_role( $role_name );
		if ( null === $role ) {
			return array();
		}
		return array_values( array_intersect( Capabilities::PRIMITIVE, array_keys( array_filter( $role->capabilities ) ) ) );
	}

	/**
	 * Own cron events of the current site, as "hook" entries (one per event).
	 *
	 * @return list<string>
	 */
	protected static function own_cron_events(): array {
		$events = array();
		foreach ( _get_cron_array() as $hooks ) {
			foreach ( $hooks as $hook => $instances ) {
				if ( str_starts_with( (string) $hook, 'ccap_' ) ) {
					foreach ( $instances as $unused ) {
						$events[] = (string) $hook;
					}
				}
			}
		}
		sort( $events );
		return $events;
	}

	/**
	 * Names of own options stored on the current site.
	 *
	 * @return list<string>
	 */
	protected static function own_options(): array {
		global $wpdb;
		$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'course\\_captain\\_%' OR option_name LIKE 'ccap\\_%' ORDER BY option_name" );
		return array_map( 'strval', $names );
	}

	/**
	 * Remove any own cron event of the current site (test isolation).
	 */
	protected static function clear_own_cron(): void {
		CronHooks::unschedule_all();
	}
}
