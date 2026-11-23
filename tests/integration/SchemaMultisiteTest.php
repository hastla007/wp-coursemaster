<?php
/**
 * R1-02 multisite: schema per site (R0-06 §6.6, MS-005).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\Schema;

/**
 * Runs only with WP_TESTS_MULTISITE=1.
 *
 * @group R1-02
 * @group ms-required
 */
final class SchemaMultisiteTest extends SchemaTestCase {

	/**
	 * MS-005 (tables): activation on site 2 creates tables, options and
	 * foreign keys only with the prefix of site 2; uninstall drops per site.
	 *
	 * @group MS-005
	 */
	public function test_MS_005_schema_is_created_and_removed_per_site(): void {
		$site2 = (int) self::factory()->blog->create();

		switch_to_blog( $site2 );
		try {
			self::reset_schema();
			Plugin::activate( false );
			self::assertCount( count( Schema::table_names() ), self::inspector()->tables() );
			self::assertCount( count( Schema::foreign_keys() ), self::inspector()->foreign_keys() );
			self::assertSame( Schema::db_version(), get_option( Migrator::DB_VERSION ) );
		} finally {
			restore_current_blog();
		}

		self::assertSame( array(), self::inspector()->tables(), 'main site untouched' );
		self::assertFalse( get_option( Migrator::DB_VERSION ) );

		switch_to_blog( $site2 );
		try {
			update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( UninstallManifest::DELETE_DATA_KEY => true ) ) );
			Uninstaller::uninstall_current_site();
			self::assertSame( array(), self::inspector()->tables() );
			self::reset_schema();
		} finally {
			restore_current_blog();
			// The DDL above committed the site; remove it for the following tests.
			wp_delete_site( $site2 );
			$GLOBALS['wpdb']->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
}
