<?php
/**
 * R1-23: restore and copy probes (R0-16 §2.3, REC-002/003/016/021; E-85, E-88, E-92 detection part).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Admin\IdentityNotice;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Notification\OutboundMode;
use CourseCaptain\Ops\HealthChecks;
use CourseCaptain\Ops\IdentityCommand;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Storage\Database;

/**
 * Real tables; mails go into a sink via `pre_wp_mail`. A "backup" is a copy
 * of the affected table rows and options, a "restore" writes them back
 * (same place: identity unchanged, only the external journal knows better).
 *
 * @group R1-23
 */
final class RecoveryTest extends SchemaTestCase {

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
	 * Site address before the test.
	 *
	 * @var string
	 */
	private string $home = '';

	/**
	 * Journal directory outside the "backup".
	 *
	 * @var string
	 */
	private string $journal;

	/**
	 * Extra directories to remove.
	 *
	 * @var list<string>
	 */
	private array $dirs = array();

	/**
	 * Mail sink.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $sink = array();

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->home = (string) get_option( 'home' );
		Plugin::activate( false );
		$tag           = strtolower( wp_generate_password( 8, false ) );
		$this->admin   = (int) self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin-rec-' . $tag . '@example.org',
			)
		);
		$this->learner = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'learner-rec-' . $tag . '@example.org',
			)
		);
		$this->course  = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Course R',
				'post_author' => $this->admin,
			)
		);
		PolicyService::flush();
		$this->journal = self::temp_dir( 'journal' );
		$this->dirs[]  = $this->journal;
		SiteIdentity::with_journal_dir( $this->journal );
		$this->sink = array();
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
		add_filter( 'course_captain_outbound_mode', array( self::class, 'mode_on' ) );
	}

	/**
	 * Cleanup.
	 */
	public function tear_down(): void {
		// DDL commits: put the address back for the following tests.
		update_option( 'home', $this->home );
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		remove_filter( 'course_captain_outbound_mode', array( self::class, 'mode_on' ) );
		remove_all_filters( 'course_captain_private_dir' );
		SiteIdentity::with_journal_dir( null );
		SiteIdentity::with_environment( null );
		foreach ( $this->dirs as $dir ) {
			self::remove_dir( $dir );
		}
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
	 * Outbound mode `on` (the hold mode must win over it).
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
	 * Unique temporary directory.
	 *
	 * @param string $name Name part.
	 * @return string
	 */
	private static function temp_dir( string $name ): string {
		$dir = get_temp_dir() . 'ccap-r123-' . $name . '-' . wp_generate_password( 8, false, false );
		wp_mkdir_p( $dir );
		return untrailingslashit( $dir );
	}

	/**
	 * Remove a test directory recursively.
	 *
	 * @param string $dir Directory.
	 */
	private static function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $items as $item ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
			$item->isDir() ? rmdir( $item->getPathname() ) : wp_delete_file( $item->getPathname() );
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
	}

	/**
	 * Notification rows.
	 *
	 * @param string $where Condition.
	 * @return list<array<string, mixed>>
	 */
	private static function rows( string $where = '1 = 1' ): array {
		$db = Database::current();
		return $db->rows( 'SELECT * FROM `' . $db->table( 'ccap_notification' ) . "` WHERE {$where} ORDER BY id" );
	}

	/**
	 * Queued test mail to the learner.
	 *
	 * @return int
	 */
	private function queue_mail(): int {
		return Database::current()->insert(
			'ccap_notification',
			array(
				'uid'               => wp_generate_uuid4(),
				'dedupe_key'        => hash( 'sha256', wp_generate_uuid4() ),
				'event_uuid'        => wp_generate_uuid4(),
				'status'            => 'queued',
				'type'              => 'test_mail',
				'recipient_user_id' => $this->learner,
			),
			array( 'created_at' )
		);
	}

	/**
	 * Deliver outbox events, then run the send queue.
	 *
	 * @return array<string, int>
	 */
	private static function run_all(): array {
		OutboxDispatcher::current()->dispatch();
		return NotificationService::current()->process_queue();
	}

	/**
	 * "Backup": rows of the given tables and the epoch option.
	 *
	 * @param string[] $tables Tables without prefix.
	 * @phpstan-param list<string> $tables
	 * @return array{rows: array<string, list<array<string, mixed>>>, epoch: mixed}
	 */
	private static function backup( array $tables ): array {
		$db   = Database::current();
		$rows = array();
		foreach ( $tables as $table ) {
			$rows[ $table ] = $db->rows( 'SELECT * FROM `' . $db->table( $table ) . '` ORDER BY 1' );
		}
		return array(
			'rows'  => $rows,
			'epoch' => get_option( SiteIdentity::EPOCH_OPTION ),
		);
	}

	/**
	 * "Restore" at the same place: table rows and epoch back, journal untouched.
	 *
	 * @param array{rows: array<string, list<array<string, mixed>>>, epoch: mixed} $backup Backup.
	 */
	private static function restore( array $backup ): void {
		$db   = Database::current();
		$wpdb = $db->wpdb();
		$db->execute( 'SET FOREIGN_KEY_CHECKS = 0' );
		foreach ( array_reverse( $backup['rows'], true ) as $table => $unused ) {
			$db->execute( 'DELETE FROM `' . $db->table( $table ) . '`' );
		}
		foreach ( $backup['rows'] as $table => $rows ) {
			$generated = array_column( $db->rows( "SHOW COLUMNS FROM `{$db->table( $table )}` WHERE Extra LIKE '%GENERATED%'" ), 'Field' );
			foreach ( $rows as $row ) {
				$wpdb->insert( $db->table( $table ), array_diff_key( $row, array_flip( $generated ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		$db->execute( 'SET FOREIGN_KEY_CHECKS = 1' );
		if ( false === $backup['epoch'] ) {
			delete_option( SiteIdentity::EPOCH_OPTION );
		} else {
			update_option( SiteIdentity::EPOCH_OPTION, $backup['epoch'], false );
		}
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 */
	private function assertRejected( callable $call, string $code ): void {
		try {
			$call();
			self::fail( 'expected ' . $code );
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code );
		}
	}

	/**
	 * Audit rows of an action.
	 *
	 * @param string $action Action.
	 * @return list<array<string, mixed>>
	 */
	private static function audit( string $action ): array {
		$db = Database::current();
		return $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s ORDER BY id', $action ) );
	}

	/**
	 * Rendered identity notice for a user.
	 *
	 * @param int $user User.
	 * @return string
	 */
	private static function notice_for( int $user ): string {
		wp_set_current_user( $user );
		ob_start();
		IdentityNotice::render();
		$html = (string) ob_get_clean();
		wp_set_current_user( 0 );
		return $html;
	}

	/**
	 * A fresh install (or an upgrade from a version without identity) is
	 * recorded silently; a stable site is never held (no false positive).
	 *
	 * @group REC-001
	 */
	public function test_identity_is_recorded_silently_and_stays_stable(): void {
		self::assertFalse( get_option( SiteIdentity::OPTION ) );
		self::assertSame( 'unrecorded', SiteIdentity::evaluate()['state'] );

		$this->queue_mail();
		NotificationService::current()->process_queue();

		self::assertCount( 1, $this->sink, 'first send records the identity and sends' );
		$stored = get_option( SiteIdentity::OPTION );
		self::assertSame( SiteIdentity::STATUS_OK, $stored['status'] );
		self::assertSame( SiteIdentity::COMPONENTS, array_keys( $stored['components'] ) );
		self::assertSame( 1, SiteIdentity::db_epoch(), 'a sent mail raises the epoch' );
		self::assertSame( 1, SiteIdentity::journal_epoch() );
		for ( $i = 0; $i < 3; $i++ ) {
			self::assertFalse( SiteIdentity::check()['held'] );
		}
		self::assertSame( $stored, get_option( SiteIdentity::OPTION ), 'nothing rewritten while stable' );
		self::assertSame( 'good', HealthChecks::test_identity()['status'] );
		self::assertSame( '', self::notice_for( $this->admin ) );
		self::assertSame( 0, IdentityCommand::execute( array(), array() )['exit'] );
	}

	/**
	 * REC-002 / E-85: restore on a new domain holds mails until "moved" is
	 * confirmed; held mails are never sent automatically (E-88).
	 *
	 * @group REC-002
	 * @group NTF-017
	 */
	public function test_REC_002_new_domain_holds_mails_until_moved_is_confirmed(): void {
		SiteIdentity::check();
		$home = (string) get_option( 'home' );
		update_option( 'home', 'https://moved-r123.example.org' );
		$held = $this->queue_mail();

		$stats = NotificationService::current()->process_queue();

		self::assertSame( 1, $stats['held'] );
		self::assertSame( array(), $this->sink, '0 mails after the move' );
		$stored = get_option( SiteIdentity::OPTION );
		self::assertSame( SiteIdentity::STATUS_HELD, $stored['status'] );
		self::assertSame( array( 'home' ), $stored['changed'] );
		self::assertGreaterThan( 0, $stored['detected_at'] );
		self::assertSame( 'critical', HealthChecks::test_identity()['status'] );
		self::assertStringContainsString( 'site address', HealthChecks::test_identity()['label'] );
		$notice = self::notice_for( $this->admin );
		self::assertSame( 3, substr_count( $notice, 'name="choice"' ) );
		self::assertStringContainsString( 'value="moved"', $notice );
		self::assertSame( '', self::notice_for( $this->learner ), 'only site administrators see the notice' );
		$status = IdentityCommand::execute( array( 'status' ), array( 'format' => 'json' ) );
		self::assertSame( 1, $status['exit'] );
		self::assertSame( 'held', json_decode( $status['lines'][0], true )['state'] );

		// Going back to the old address does not lift the persisted hold.
		update_option( 'home', $home );
		self::assertTrue( SiteIdentity::is_held() );
		update_option( 'home', 'https://moved-r123.example.org' );

		$this->assertRejected( fn() => SiteIdentity::confirm( Actor::user( $this->learner ), SiteIdentity::CHOICE_MOVED ), 'ccap_forbidden' );
		$this->assertRejected( fn() => SiteIdentity::confirm( Actor::user( $this->admin ), 'whatever' ), 'ccap_invalid_param' );
		self::assertSame( array(), self::audit( 'site_identity_confirmed' ) );

		$state = SiteIdentity::confirm( Actor::user( $this->admin ), SiteIdentity::CHOICE_MOVED );

		self::assertFalse( $state['held'] );
		self::assertSame( SiteIdentity::STATUS_OK, $state['state'] );
		$audit = self::audit( 'site_identity_confirmed' );
		self::assertCount( 1, $audit );
		self::assertSame( (string) $this->admin, (string) \CourseCaptain\Event\AuditIdentity::user_of( Database::current(), $audit[0]['actor_ref'] ) );
		self::assertSame(
			array(
				'choice'   => 'moved',
				'previous' => 'held',
				'changed'  => array( 'home' ),
			),
			json_decode( (string) $audit[0]['context'], true )
		);
		self::assertSame( 'good', HealthChecks::test_identity()['status'] );

		// The held mail stays held: no catch-up after the confirmation (E-88).
		NotificationService::current()->process_queue();
		self::assertSame( 'held', self::rows( 'id = ' . $held )[0]['status'] );
		self::assertSame( array(), $this->sink );
		self::assertSame( 1, NotificationService::current()->discard_held( Actor::user( $this->admin ) ), 'default: discard' );

		$this->queue_mail();
		NotificationService::current()->process_queue();
		self::assertCount( 1, $this->sink, 'new mails go out at the new address' );
	}

	/**
	 * REC-003 / REC-021: a copy next to the original (own private folder,
	 * staging) is held; "copy" keeps outbound blocked for good.
	 *
	 * @group REC-003
	 * @group REC-021
	 */
	public function test_REC_003_copy_next_to_original_keeps_outbound_blocked(): void {
		SiteIdentity::check();
		$private      = self::temp_dir( 'private' );
		$this->dirs[] = $private;
		add_filter( 'course_captain_private_dir', static fn(): string => $private );
		SiteIdentity::with_environment( 'staging' );

		self::assertSame( array( 'private', 'environment' ), SiteIdentity::check()['changed'] );

		$state = IdentityCommand::execute( array( 'confirm', 'copy' ), array() );
		self::assertSame( 0, $state['exit'] );
		self::assertSame( 'copy', $state['state'] );
		self::assertSame( SiteIdentity::STATUS_COPY, get_option( SiteIdentity::OPTION )['status'] );
		self::assertSame( array(), get_option( SiteIdentity::OPTION )['changed'] );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->queue_mail();
		}
		CronHooks::run_hourly();
		NotificationService::current()->process_queue();
		self::assertCount( 3, self::rows( "status = 'held'" ) );
		self::assertSame( array(), $this->sink, 'a copy never sends' );
		self::assertSame( 'recommended', HealthChecks::test_identity()['status'] );
		$notice = self::notice_for( $this->admin );
		self::assertStringNotContainsString( 'value="copy"', $notice );
		self::assertStringContainsString( 'value="moved"', $notice );
		self::assertSame( 0, IdentityCommand::execute( array(), array() )['exit'], 'copy is a confirmed state' );
		self::assertTrue( OutboundMode::is_held() );

		// The copy becomes a site of its own.
		self::assertSame( 1, IdentityCommand::execute( array( 'confirm', 'nonsense' ), array() )['exit'] );
		$state = SiteIdentity::confirm( Actor::cli(), SiteIdentity::CHOICE_MOVED );
		self::assertFalse( $state['held'] );
		self::assertSame( 'system', self::audit( 'site_identity_confirmed' )[1]['actor_type'] );
	}

	/**
	 * REC-016: backup before the access mail, restore after it at the same
	 * place → the epoch journal detects the older DB, the hold catches the
	 * replayed event, nothing is sent twice, the event keeps its UUID.
	 *
	 * @group REC-016
	 * @group REC-001
	 */
	public function test_REC_016_restore_of_older_database_never_resends(): void {
		SiteIdentity::check();
		GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->learner, $this->course, wp_generate_uuid4(), 'x' );
		$backup = self::backup( array( 'ccap_outbox_event', 'ccap_outbox_delivery', 'ccap_notification' ) );
		self::assertSame( array(), $backup['rows']['ccap_notification'] );

		self::run_all();
		self::assertCount( 1, $this->sink );
		$sent = self::rows();
		self::assertSame( array( 'sent' ), array_column( $sent, 'status' ) );
		self::assertSame( 1, SiteIdentity::journal_epoch() );

		self::restore( $backup );
		self::assertSame( array( SiteIdentity::REASON_EPOCH ), SiteIdentity::evaluate()['changed'] );

		$stats = self::run_all();

		self::assertSame( 1, $stats['held'] );
		self::assertCount( 1, $this->sink, 'no second access mail after the restore' );
		$after = self::rows();
		self::assertSame( array( 'held' ), array_column( $after, 'status' ) );
		self::assertSame( $sent[0]['event_uuid'], $after[0]['event_uuid'], 'event keeps its UUID' );
		self::assertSame( $sent[0]['dedupe_key'], $after[0]['dedupe_key'] );
		self::assertStringContainsString( 'restored', HealthChecks::test_identity()['label'] );

		SiteIdentity::confirm( Actor::user( $this->admin ), SiteIdentity::CHOICE_SAME_PLACE );

		self::assertFalse( SiteIdentity::is_held() );
		self::assertSame( SiteIdentity::journal_epoch(), SiteIdentity::db_epoch(), 'epoch synchronized' );
		NotificationService::current()->process_queue();
		self::assertCount( 1, $this->sink, 'held mail not sent automatically' );
		self::assertSame( 1, NotificationService::current()->discard_held( Actor::user( $this->admin ) ) );
	}

	/**
	 * Without a journal directory a same-place restore cannot be detected
	 * (residual risk R0-17 §5.5); identity changes are still detected.
	 *
	 * @group REC-001
	 */
	public function test_restore_without_journal_is_not_detected(): void {
		SiteIdentity::with_journal_dir( null );
		SiteIdentity::check();
		SiteIdentity::bump_epoch();
		self::assertSame( '', SiteIdentity::journal_file() );
		delete_option( SiteIdentity::EPOCH_OPTION );

		self::assertFalse( SiteIdentity::is_held() );
		update_option( 'home', 'https://elsewhere-r123.example.org' );
		self::assertTrue( SiteIdentity::is_held() );
	}

	/**
	 * PRIV-010 (detection part): an erasure raises the epoch; a restore of
	 * the DB from before the erasure puts the site into the hold mode.
	 * The re-application of erasures from tombstones is not built (R1-23 proof O-2).
	 *
	 * @group PRIV-010
	 */
	public function test_PRIV_010_erasure_then_restore_is_detected(): void {
		SiteIdentity::check();
		GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->learner, $this->course, wp_generate_uuid4(), 'x' );
		$epoch = get_option( SiteIdentity::EPOCH_OPTION );

		self::assertGreaterThan( 0, PrivacyService::erase_user( $this->learner ) );
		self::assertSame( 1, SiteIdentity::journal_epoch() );
		self::assertFalse( SiteIdentity::is_held() );

		if ( false === $epoch ) {
			delete_option( SiteIdentity::EPOCH_OPTION );
		}
		self::assertTrue( SiteIdentity::is_held() );
		self::assertSame( array( SiteIdentity::REASON_EPOCH ), get_option( SiteIdentity::OPTION )['changed'] );

		// A further bump while held never hides the detection.
		SiteIdentity::bump_epoch();
		self::assertTrue( SiteIdentity::is_held() );
	}
}
