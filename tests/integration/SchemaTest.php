<?php
/**
 * R1-02: schema install, upgrade, idempotency, ownership and uninstall.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Lease;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\MigrationTrigger;
use CourseCaptain\Storage\Schema;
use CourseCaptain\Storage\StorageException;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Server;
use WPDieException;

/**
 * Schema tests against a real MariaDB/MySQL (R0-06, R0-37, R0-05 GM-163/164/168/149).
 *
 * @group R1-02
 */
final class SchemaTest extends SchemaTestCase {

	/**
	 * GM-163: first activation creates the full schema.
	 *
	 * @group GM-163
	 */
	public function test_GM_163_activation_creates_complete_schema(): void {
		Plugin::activate( false );

		$tables = self::inspector()->tables();
		foreach ( Schema::table_names() as $table ) {
			self::assertArrayHasKey( $table, $tables, $table );
			self::assertSame( 'InnoDB', $tables[ $table ]['engine'], $table );
			self::assertSame( Schema::OWNER_COMMENT, $tables[ $table ]['comment'], $table );
		}
		self::assertCount( count( Schema::table_names() ), array_intersect_key( $tables, array_flip( Schema::table_names() ) ) );
		self::assertCount( count( Schema::foreign_keys() ), self::inspector()->foreign_keys(), 'FK count = target' );
		self::assertSame( array(), get_option( Migrator::FK_ISSUES ) );
		self::assertSame( array(), self::inspector()->drift( Schema::table_names() ) );
		self::assertSame( Schema::db_version(), get_option( Migrator::DB_VERSION ) );
		self::assertSame( Schema::fingerprint( COURSE_CAPTAIN_VERSION ), get_option( Migrator::FINGERPRINT ) );
		self::assertSame( Schema::COMPAT_MIN, get_option( Migrator::COMPAT_MIN ) );
		self::assertContains( 'running', self::inspector()->columns( 'ccap_exam_session' ) );
		self::assertContains( 'active_user', self::inspector()->columns( 'ccap_seat' ) );
		self::assertNotSame( array(), self::ccap_caps_of( 'administrator' ) );

		$steps = Database::current()->rows( 'SELECT step_id, status FROM `' . Database::current()->table( 'ccap_migration_step' ) . '` ORDER BY step_id' );
		self::assertSame(
			array( Migrator::STEP_TABLES, 'core:0001_exam_session_running', 'core:0002_seat_active_user', Migrator::STEP_FOREIGN ),
			array_column( $steps, 'step_id' )
		);
		self::assertSame( array( 'done' ), array_values( array_unique( array_column( $steps, 'status' ) ) ) );
	}

	/**
	 * GM-164: deactivate/reactivate/migrate again changes nothing; FKs hold.
	 *
	 * @group GM-164
	 */
	public function test_GM_164_reactivation_and_rerun_are_idempotent(): void {
		Plugin::activate( false );
		$fks = self::inspector()->foreign_keys();

		Plugin::deactivate();
		Plugin::activate( false );
		$rerun = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CLI, true );

		self::assertSame( 'done', $rerun['status'] );
		self::assertSame( array(), $rerun['dbdelta'], 'dbDelta reports nothing on a current schema' );
		self::assertSame( array(), $rerun['fk_issues'] );
		self::assertSame( $fks, self::inspector()->foreign_keys(), 'no duplicate foreign keys' );

		// An answer without an existing question revision is rejected by the FK.
		$db = Database::current();
		$db->wpdb()->suppress_errors( true );
		$this->expectException( StorageException::class );
		$db->insert(
			'ccap_attempt',
			array(
				'user_id'         => 1,
				'question_id'     => 999999,
				'revision_id'     => 999999,
				'idempotency_key' => $db->uuid(),
			),
			array( 'created_at' )
		);
	}

	/**
	 * GM-168: a missing index of schema n−1 is added by the migration.
	 *
	 * @group GM-168
	 */
	public function test_GM_168_missing_index_is_restored_by_migration(): void {
		Plugin::activate( false );
		$db = Database::current();
		$db->execute( 'ALTER TABLE `' . $db->table( 'ccap_question_progress' ) . '` DROP INDEX user_due' );
		update_option( Migrator::DB_VERSION, '0.9.0+000000000000' );
		self::assertArrayNotHasKey( 'user_due', self::inspector()->indexes( 'ccap_question_progress' ) );

		$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN );

		self::assertSame( 'done', $result['status'] );
		self::assertSame( array( 'user_id', 'due_at' ), self::inspector()->indexes( 'ccap_question_progress' )['user_due']['columns'] );
		self::assertSame( Schema::db_version(), get_option( Migrator::DB_VERSION ) );
	}

	/**
	 * GM-149: while the schema is pending, writes to the own REST namespace
	 * get 503 + Retry-After, reads pass, `ccap_migrate` is scheduled once.
	 *
	 * @group GM-149
	 */
	public function test_GM_149_writes_get_503_while_migration_is_due(): void {
		global $wp_rest_server;
		Plugin::activate( false );
		self::clear_own_cron();
		update_option( Migrator::FINGERPRINT, 'stale' );

		$wp_rest_server = new WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core test REST server.
		add_action(
			'rest_api_init',
			static function (): void {
				register_rest_route(
					'course-captain/v1',
					'/probe',
					array(
						'methods'             => array( 'GET', 'POST' ),
						'callback'            => static fn() => array( 'ok' => true ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);
		do_action( 'rest_api_init', $wp_rest_server ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		$migrator = Migrator::for_current_site();
		$migrator->on_request();

		$write = rest_do_request( new WP_REST_Request( 'POST', '/course-captain/v1/probe' ) );
		self::assertSame( 503, $write->get_status() );
		self::assertSame( 'ccap_maintenance', $write->get_data()['code'] );
		self::assertSame( '30', $write->get_headers()['Retry-After'] );

		$read = rest_do_request( new WP_REST_Request( 'GET', '/course-captain/v1/probe' ) );
		self::assertSame( 200, $read->get_status() );
		self::assertSame( array( CronHooks::MIGRATE ), self::own_cron_events() );

		$migrator->run( MigrationTrigger::CONTEXT_CRON, true );
		$after = rest_do_request( new WP_REST_Request( 'POST', '/course-captain/v1/probe' ) );
		self::assertSame( 200, $after->get_status() );

		remove_filter( 'rest_pre_dispatch', array( $migrator, 'filter_rest_maintenance' ), 5 );
		$wp_rest_server = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core test REST server.
	}

	/**
	 * KOMP-131: a foreign table with an own name blocks the activation
	 * before any write.
	 *
	 * @group KOMP-131
	 */
	public function test_KOMP_131_foreign_table_blocks_activation_without_writes(): void {
		$db = Database::current();
		$db->execute( 'CREATE TABLE `' . $db->table( 'ccap_grant' ) . '` (id bigint(20) unsigned NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB' );

		try {
			Plugin::activate( false );
			self::fail( 'Activation must abort.' );
		} catch ( WPDieException $e ) {
			self::assertStringContainsString( 'ccap_grant', $e->getMessage() );
		}

		self::assertSame( array( 'ccap_grant' ), array_keys( self::inspector()->tables() ) );
		self::assertFalse( get_option( Migrator::DB_VERSION ) );
		self::assertSame( array(), self::ccap_caps_of( 'administrator' ) );
	}

	/**
	 * A schema newer than the code is never touched (R0-06 §6.1, WN).
	 */
	public function test_newer_schema_is_left_alone(): void {
		Plugin::activate( false );
		update_option( Migrator::DB_VERSION, '9.0.0+ffffffffffff' );

		$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN );

		self::assertSame( 'newer', $result['status'] );
		self::assertSame( '9.0.0+ffffffffffff', get_option( Migrator::DB_VERSION ) );
	}

	/**
	 * A second runner is skipped while the named lock is held (R0-37 L-A).
	 */
	public function test_second_runner_skips_while_locked(): void {
		Plugin::activate( false );
		update_option( Migrator::DB_VERSION, '0.9.0+000000000000' );

		global $wpdb;
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$other->set_prefix( $wpdb->prefix );
		$name = \CourseCaptain\Storage\NamedLock::schema_migration( Database::current() )->name;
		self::assertSame( '1', (string) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) );

		$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN );

		$other->query( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		$other->close();
		self::assertSame( 'skipped_locked', $result['status'] );
		self::assertSame( '0.9.0+000000000000', get_option( Migrator::DB_VERSION ) );
	}

	/**
	 * Lease with fencing: no takeover of a live lease without the named lock;
	 * a takeover raises the fence and the old holder loses its writes.
	 */
	public function test_lease_fencing(): void {
		Plugin::activate( false );
		$db = Database::current();

		$first = Lease::acquire( $db, 'probe', 60, false );
		self::assertNotNull( $first );
		self::assertNull( Lease::acquire( $db, 'probe', 60, false ) );

		$second = Lease::acquire( $db, 'probe', 60, true );
		self::assertNotNull( $second );
		self::assertGreaterThan( $first->fence, $second->fence );
		self::assertFalse( $first->heartbeat( 60 ) );
		self::assertTrue( $second->heartbeat( 60 ) );
		$second->release();
		self::assertNotNull( Lease::acquire( $db, 'probe', 60, false ) );
	}

	/**
	 * Repository base: compare-and-swap and transaction rollback (R0-06 §2 Nr. 5).
	 */
	public function test_compare_and_swap_and_rollback(): void {
		Plugin::activate( false );
		$db    = Database::current();
		$group = $db->insert(
			'ccap_group',
			array(
				'uid'  => $db->uuid(),
				'name' => 'Probe',
			),
			array( 'created_at', 'updated_at' )
		);

		self::assertTrue( $db->compare_and_swap( 'ccap_group', $group, 0, array( 'status' => 'suspended' ) ) );
		self::assertFalse( $db->compare_and_swap( 'ccap_group', $group, 0, array( 'status' => 'active' ) ), 'stale version' );
		self::assertSame( 'suspended', $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $group ) ) );

		try {
			$db->transaction(
				static function ( Database $tx ) use ( $group ): void {
					$tx->compare_and_swap( 'ccap_group', $group, 1, array( 'name' => 'Changed' ) );
					throw new RuntimeException( 'abort' );
				}
			);
		} catch ( RuntimeException $e ) {
			unset( $e );
		}
		self::assertSame( 'Probe', $db->scalar( $db->prepare( 'SELECT name FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $group ) ) );
	}

	/**
	 * Generated column + UNIQUE: one active seat per user and group; a
	 * re-invite after removal stays possible (R0-21 D21-4).
	 */
	public function test_one_active_seat_per_user_and_group(): void {
		Plugin::activate( false );
		$db    = Database::current();
		$group = $db->insert(
			'ccap_group',
			array(
				'uid'  => $db->uuid(),
				'name' => 'Seats',
			),
			array( 'created_at', 'updated_at' )
		);
		$seat  = static fn( string $status ): int => $db->insert(
			'ccap_seat',
			array(
				'group_id' => $group,
				'user_id'  => 7,
				'status'   => $status,
			),
			array( 'created_at', 'updated_at' )
		);

		$seat( 'removed' );
		$seat( 'removed' );
		$seat( 'active' );
		$db->wpdb()->suppress_errors( true );
		$this->expectException( StorageException::class );
		$seat( 'active' );
	}

	/**
	 * REC-019: confirmed deletion drops all marked tables; without
	 * confirmation the tables stay.
	 *
	 * @group REC-019
	 */
	public function test_REC_019_uninstall_drops_tables_only_when_confirmed(): void {
		Plugin::activate( false );

		Uninstaller::uninstall_current_site();
		self::assertCount( count( Schema::table_names() ), self::inspector()->tables(), 'data stays by default' );

		update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( UninstallManifest::DELETE_DATA_KEY => true ) ) );
		Uninstaller::uninstall_current_site();

		self::assertSame( array(), self::inspector()->tables() );
		self::assertFalse( get_option( Migrator::DB_VERSION ) );
	}
}
