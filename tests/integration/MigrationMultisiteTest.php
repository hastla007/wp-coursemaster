<?php
/**
 * R1-40 multisite: migration locks and state per site (R0-37 §5, MIG-020).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\MigrationTrigger;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\NamedLock;
use CourseCaptain\Storage\Schema;

/**
 * Runs only with WP_TESTS_MULTISITE=1.
 *
 * @group R1-40
 * @group ms-required
 */
final class MigrationMultisiteTest extends SchemaTestCase {

	/**
	 * MIG-020 (lock part): the named lock and the lease are per site; a
	 * runner holding site 1 never blocks site 2, and the step state of one
	 * site does not leak into the other. MS-020: the upgrade is lazy per
	 * site (site 2 migrates on its own run, site 1 keeps its version) and
	 * concurrent runs on two sites do not block each other (lock per site).
	 *
	 * @group MIG-020
	 * @group MS-020
	 */
	public function test_MIG_020_locks_are_independent_per_site(): void {
		global $wpdb;
		$site2 = (int) self::factory()->blog->create();
		Plugin::activate( false );
		update_option( Migrator::DB_VERSION, '0.9.0+000000000000' );
		$main_lock = NamedLock::schema_migration( Database::current() )->name;

		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$other->set_prefix( $wpdb->base_prefix );
		self::assertSame( '1', (string) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $main_lock ) ) );

		switch_to_blog( $site2 );
		try {
			self::reset_schema();
			self::assertNotSame( $main_lock, NamedLock::schema_migration( Database::current() )->name );
			$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN );
			self::assertSame( 'done', $result['status'], 'site 2 migrates while site 1 is locked' );
			self::assertSame( Schema::db_version(), get_option( Migrator::DB_VERSION ) );
		} finally {
			restore_current_blog();
		}

		self::assertSame( 'skipped_locked', Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN )['status'] );
		self::assertSame( '0.9.0+000000000000', get_option( Migrator::DB_VERSION ), 'site 1 untouched' );
		$other->query( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $main_lock ) );
		$other->close();
		self::assertSame( 'done', Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN )['status'] );

		switch_to_blog( $site2 );
		try {
			self::reset_schema();
		} finally {
			restore_current_blog();
			wp_delete_site( $site2 );
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
}
