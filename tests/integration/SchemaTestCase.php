<?php
/**
 * Base class for tests that need real (non-temporary) tables.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Schema;
use CourseCaptain\Storage\SchemaInspector;

/**
 * The WP test suite rewrites CREATE/DROP TABLE to temporary tables, which
 * information_schema does not show. Schema tests switch that off and clean
 * up themselves: DDL commits the test transaction, so every test starts and
 * ends with no own tables and no schema options.
 */
abstract class SchemaTestCase extends TestCase {

	/**
	 * Real tables, real migrator.
	 */
	public function set_up(): void {
		parent::set_up();
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
		Plugin::override_migration_runner( null );
		self::reset_schema();
	}

	/**
	 * Remove everything this test created.
	 */
	public function tear_down(): void {
		$GLOBALS['wpdb']->suppress_errors( false );
		self::reset_schema();
		parent::tear_down();
	}

	/**
	 * Drop own tables of the current site and delete the schema options.
	 */
	protected static function reset_schema(): void {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
		foreach ( Schema::drop_order() as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}{$table}`" );
		}
		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}ccap_foreign_probe`" );
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
		// phpcs:enable
		// DDL commits the test transaction: remove every own option, not only the schema ones.
		foreach ( self::own_options() as $option ) {
			delete_option( $option );
		}
		self::clear_own_cron();
		// DDL committed earlier writes, so capabilities may survive the rollback.
		Capabilities::remove_all();
		// autocommit is off in the test suite: make the cleanup survive the final ROLLBACK.
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Inspector of the current site.
	 *
	 * @return SchemaInspector
	 */
	protected static function inspector(): SchemaInspector {
		return new SchemaInspector( Database::current() );
	}
}
