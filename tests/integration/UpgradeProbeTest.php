<?php
/**
 * R1-23: upgrade, deactivation and reinstall probes (R0-16 §2.1/§2.5, R0-37 §4/§6; REC-018, REC-023).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Notification\OutboundMode;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Lease;
use CourseCaptain\Storage\MigrationTrigger;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\Schema;

/**
 * "Previous version" = the schema state an older release leaves behind:
 * no generated column `active_user`, no foreign keys, a missing index,
 * no step rows for them, an older `course_captain_db_version`. Data is real
 * (grant, events, progress, queued mails).
 *
 * @group R1-23
 */
final class UpgradeProbeTest extends SchemaTestCase {

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
	private int $learner;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Mail sink.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $sink = array();

	/**
	 * Fixture: installed plugin with learning data.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$tag           = strtolower( wp_generate_password( 8, false ) );
		$this->admin   = (int) self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin-upg-' . $tag . '@example.org',
			)
		);
		$this->learner = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'learner-upg-' . $tag . '@example.org',
			)
		);
		$this->course  = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Course U',
				'post_author' => $this->admin,
			)
		);
		PolicyService::flush();
		$this->sink = array();
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
		add_filter( 'course_captain_outbound_mode', array( self::class, 'mode_on' ) );
	}

	/**
	 * Cleanup.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		remove_filter( 'course_captain_outbound_mode', array( self::class, 'mode_on' ) );
		parent::tear_down();
	}

	/**
	 * Mail sink.
	 *
	 * @param mixed                $value Short-circuit value.
	 * @param array<string, mixed> $atts  wp_mail attributes.
	 * @return bool
	 */
	public function catch_mail( $value, $atts ): bool {
		unset( $value );
		$this->sink[] = (array) $atts;
		return true;
	}

	/**
	 * Outbound mode `on`.
	 *
	 * @return array{mode: string, catch_address: string, warning: string}
	 */
	public static function mode_on(): array {
		return array(
			'mode'          => OutboundMode::ON,
			'catch_address' => '',
			'warning'       => '',
		);
	}

	/**
	 * Learning data: grant (events, outbox), progress, a completed attempt-free course row.
	 */
	private function seed(): void {
		GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->learner, $this->course, wp_generate_uuid4(), 'seed' );
		$db = Database::current();
		$db->insert(
			'ccap_course_progress',
			array(
				'user_id'   => $this->learner,
				'course_id' => $this->course,
				'status'    => 'in_progress',
			),
			array( 'updated_at' )
		);
	}

	/**
	 * Row count per own table (without the migration bookkeeping).
	 *
	 * @return array<string, int>
	 */
	private static function counts(): array {
		$db  = Database::current();
		$out = array();
		foreach ( array_diff( Schema::table_names(), array( 'ccap_migration_step', 'ccap_lease' ) ) as $table ) {
			$out[ $table ] = (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '`' );
		}
		return $out;
	}

	/**
	 * Content hash of the learning data (grant, events, progress).
	 *
	 * @return string
	 */
	private static function data_hash(): string {
		$db   = Database::current();
		$data = array();
		foreach ( array( 'ccap_grant', 'ccap_grant_event', 'ccap_course_progress', 'ccap_outbox_event' ) as $table ) {
			$data[ $table ] = $db->rows( 'SELECT * FROM `' . $db->table( $table ) . '` ORDER BY 1' );
		}
		return hash( 'sha256', (string) wp_json_encode( $data ) );
	}

	/**
	 * Bring the installed schema back to the state of the previous release.
	 */
	private static function downgrade_schema_to_previous_release(): void {
		$db = Database::current();
		foreach ( self::inspector()->foreign_keys() as $name => $fk ) {
			$db->execute( 'ALTER TABLE `' . $db->table( $fk['table'] ) . "` DROP FOREIGN KEY `{$name}`" );
		}
		$db->execute( 'ALTER TABLE `' . $db->table( 'ccap_seat' ) . '` DROP INDEX active_member, DROP COLUMN active_user' );
		$db->execute( 'ALTER TABLE `' . $db->table( 'ccap_question_progress' ) . '` DROP INDEX user_due' );
		$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_migration_step' ) . '` WHERE step_id IN (%s, %s)', 'core:0002_seat_active_user', Migrator::STEP_FOREIGN ) );
		update_option( Migrator::DB_VERSION, '0.9.0+000000000000' );
		delete_option( Migrator::FINGERPRINT );
		delete_option( Migrator::COMPAT_MIN );
		self::assertNotContains( 'active_user', self::inspector()->columns( 'ccap_seat' ) );
		self::assertSame( array(), self::inspector()->foreign_keys() );
	}

	/**
	 * Attempts per step.
	 *
	 * @return array<string, int>
	 */
	private static function attempts(): array {
		$db = Database::current();
		return array_map( 'intval', array_column( $db->rows( 'SELECT step_id, attempts FROM `' . $db->table( 'ccap_migration_step' ) . '` ORDER BY step_id' ), 'attempts', 'step_id' ) );
	}

	/**
	 * REC-023 / R0-16 §3.3 upgrade probe: previous release with learning
	 * data, new code without reactivation (update hook → cron), migration ok,
	 * FKs complete, row counts and learning data unchanged; a second run is
	 * a no-op (idempotent).
	 *
	 * @group REC-023
	 * @group GM-164
	 * @group GM-170
	 */
	public function test_REC_023_upgrade_from_previous_release_keeps_data(): void {
		$this->seed();
		self::downgrade_schema_to_previous_release();
		$counts = self::counts();
		$hash   = self::data_hash();
		self::assertGreaterThan( 0, $counts['ccap_grant'] );
		self::assertFalse( Plugin::schema_ready(), 'W1 until migrated' );

		// The update replaces the package; the update hook only schedules (T-2).
		$migrator = Migrator::for_current_site();
		$migrator->schedule_after_update();
		self::assertNotFalse( wp_next_scheduled( CronHooks::MIGRATE ) );
		$result = $migrator->run( MigrationTrigger::CONTEXT_CRON );

		self::assertSame( 'done', $result['status'], $result['error'] );
		self::assertSame( Schema::db_version(), get_option( Migrator::DB_VERSION ) );
		self::assertSame( Schema::COMPAT_MIN, get_option( Migrator::COMPAT_MIN ) );
		self::assertContains( 'active_user', self::inspector()->columns( 'ccap_seat' ) );
		self::assertArrayHasKey( 'user_due', self::inspector()->indexes( 'ccap_question_progress' ) );
		self::assertCount( count( Schema::foreign_keys() ), self::inspector()->foreign_keys(), 'FKs complete' );
		self::assertSame( array(), get_option( Migrator::FK_ISSUES ) );
		self::assertSame( array(), self::inspector()->drift( Schema::table_names() ) );
		self::assertSame( $counts, self::counts(), 'row counts unchanged' );
		self::assertSame( $hash, self::data_hash(), 'learning data unchanged' );
		self::assertTrue( Plugin::schema_ready() );

		$attempts = self::attempts();
		$again    = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CLI );
		self::assertContains( $again['status'], array( 'none', 'done' ) );
		self::assertSame( array(), $again['dbdelta'] );
		self::assertSame( $attempts, self::attempts(), 'no step runs twice' );
		self::assertSame( $counts, self::counts() );
	}

	/**
	 * Upgrade while work is in flight: pending outbox deliveries, a mail in
	 * `sending` of a crashed old process and an orphaned migration lease.
	 * The migration takes the lease over; afterwards every event is
	 * delivered once and the half-sent mail is never resent automatically.
	 *
	 * @group REC-023
	 * @group REC-006
	 * @group MIG-021
	 */
	public function test_upgrade_with_pending_outbox_and_orphaned_locks(): void {
		$this->seed();
		$db = Database::current();
		self::assertGreaterThan( 0, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_delivery' ) . "` WHERE status = 'pending'" ) );
		$sending = $db->insert(
			'ccap_notification',
			array(
				'uid'               => wp_generate_uuid4(),
				'dedupe_key'        => hash( 'sha256', wp_generate_uuid4() ),
				'event_uuid'        => wp_generate_uuid4(),
				'status'            => 'sending',
				'type'              => 'test_mail',
				'recipient_user_id' => $this->learner,
				'lock_owner'        => 'old-process',
				'locked_until'      => $db->now( -60 ),
			),
			array( 'created_at' )
		);
		$orphan  = Lease::acquire( $db, Migrator::LEASE_NAME, Migrator::LEASE_TTL_DDL, false );
		self::assertNotNull( $orphan );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_lease' ) . '` SET expires_at = %s WHERE name = %s', $db->now( -60 ), Migrator::LEASE_NAME ) );
		self::downgrade_schema_to_previous_release();

		// W1: the dispatcher does not deliver against the old schema.
		Plugin::run_outbox_dispatch();
		self::assertGreaterThan( 0, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_delivery' ) . "` WHERE status = 'pending'" ) );

		$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN );

		self::assertSame( 'done', $result['status'], $result['error'] );
		$lease = $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_lease' ) . '` WHERE name = %s', Migrator::LEASE_NAME ) );
		self::assertNull( $lease, 'orphaned lease taken over and released after the run' );
		self::assertFalse( $orphan->heartbeat( 60 ), 'the old process is fenced out' );
		self::assertFalse( $orphan->is_held() );

		OutboxDispatcher::current()->dispatch();
		$stats = NotificationService::current()->process_queue();
		self::assertSame( 0, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_delivery' ) . "` WHERE status IN ('pending', 'dispatching')" ) );
		self::assertSame( 1, $stats['sent'], 'the access mail goes out once' );
		self::assertSame( 'unknown', (string) $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_notification' ) . '` WHERE id = %d', $sending ) ) );

		$db->execute( 'UPDATE `' . $db->table( 'ccap_outbox_delivery' ) . "` SET status = 'pending', next_attempt_at = NULL" );
		OutboxDispatcher::current()->dispatch();
		NotificationService::current()->process_queue();
		self::assertCount( 1, $this->sink, 'replay after the upgrade sends nothing twice' );
	}

	/**
	 * REC-018 / GM-158: deactivation keeps data and pauses cron; uninstall
	 * without deletion keeps data; reinstall plans cron again and runs no
	 * migration step a second time.
	 *
	 * @group REC-018
	 * @group GM-158
	 */
	public function test_REC_018_deactivate_uninstall_reinstall_keeps_everything(): void {
		$this->seed();
		$counts   = self::counts();
		$hash     = self::data_hash();
		$attempts = self::attempts();
		$options  = self::own_options();

		Plugin::deactivate();
		self::assertSame( array(), self::own_cron_events() );
		self::assertSame( $counts, self::counts() );

		$result = Uninstaller::run();

		self::assertSame( array( get_current_blog_id() => false ), $result );
		self::assertSame( $counts, self::counts(), 'all data present' );
		self::assertSame( $hash, self::data_hash() );
		self::assertSame( $options, self::own_options(), 'no option removed' );
		self::assertNotNull( get_post( $this->course ), 'courses stay' );
		self::assertNotSame( array(), self::ccap_caps_of( 'administrator' ) );

		Plugin::activate( false );

		self::assertSame( array( CronHooks::HOURLY ), self::own_cron_events(), 'cron planned again' );
		self::assertSame( $attempts, self::attempts(), 'no migration step twice' );
		self::assertSame( $counts, self::counts() );
		self::assertTrue( Plugin::schema_ready() );
	}
}
