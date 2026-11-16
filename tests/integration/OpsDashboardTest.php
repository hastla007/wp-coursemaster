<?php
/**
 * R1-38: system status, safe repair actions, integration status, diagnostics, CLI.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Cache\CacheStore;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\AuditView;
use CourseCaptain\Ops\Diagnostics;
use CourseCaptain\Ops\IntegrationStatus;
use CourseCaptain\Ops\OpsCommand;
use CourseCaptain\Ops\OpsScreen;
use CourseCaptain\Ops\OpsStatus;
use CourseCaptain\Ops\RepairService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\MigrationTrigger;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\StepLog;

/**
 * Real tables.
 *
 * @group R1-38
 */
final class OpsDashboardTest extends SchemaTestCase {

	private const CANARY_EMAIL = 'jane.doe@mailhost.test';
	private const CANARY_IP4   = '203.0.113.77';
	private const CANARY_IP6   = '2001:db8::7';
	private const CANARY_TOKEN = 'abcdefABCDEF0123456789abcdefABCDEF0123456789';
	private const CANARY_DIR   = 'protected-S3cr3tF0lder';

	/**
	 * Administrator.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Support (`ccap_support`: #22, no #23).
	 *
	 * @var int
	 */
	private int $support;

	/**
	 * Course manager (#22, no #23).
	 *
	 * @var int
	 */
	private int $manager;

	/**
	 * Editor without Course Captain capabilities.
	 *
	 * @var int
	 */
	private int $editor;

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		wp_roles()->for_site( get_current_blog_id() );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->support = (int) self::factory()->user->create( array( 'role' => Roles::SUPPORT ) );
		$this->manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		$this->editor  = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		PolicyService::flush();
	}

	/**
	 * Reset globals.
	 */
	public function tear_down(): void {
		$_POST = array();
		remove_all_filters( 'wp_redirect' );
		wp_cache_delete( 'plugins', 'plugins' );
		parent::tear_down();
	}

	/**
	 * SUP-001/SUP-006, INT-010, INT-013: support and manager read, only #23 repairs; editor 403.
	 *
	 * @group SUP-001
	 * @group SUP-006
	 * @group INT-009
	 * @group INT-010
	 * @group INT-013
	 */
	public function test_SUP_001_006_roles(): void {
		$this->dead_delivery( 'boom' );
		foreach ( array( $this->support, $this->manager ) as $user ) {
			self::assertArrayHasKey( 'checks', OpsStatus::report( Actor::user( $user ) ) );
			self::assertFalse( RepairService::can( Actor::user( $user ) ) );
			wp_set_current_user( $user );
			$html = OpsScreen::html( array( 'area' => 'jobs' ) );
			self::assertStringContainsString( 'Internal deliveries', $html );
			self::assertStringNotContainsString( 'name="op"', $html, 'no action buttons without #23' );
			self::assertNotEmpty( IntegrationStatus::all( Actor::user( $user ) ) );
			$out = OpsScreen::process(
				array(
					'op'       => 'outbox_retry',
					'_wpnonce' => wp_create_nonce( 'ccap_ops_outbox_retry' ),
				),
				$user
			);
			self::assertSame( 'ccap_forbidden', $out['result'] );
			try {
				RepairService::current()->retry_deliveries( Actor::user( $user ) );
				self::fail( 'retry without #23' );
			} catch ( CourseException $e ) {
				self::assertSame( 403, $e->status );
			}
		}
		self::assertSame( 1, $this->deliveries( 'dead' ), 'no state change' );
		self::assertSame( 0, $this->audits( 'job_retried' ) );

		wp_set_current_user( $this->admin );
		self::assertStringContainsString( 'name="op" value="outbox_retry"', OpsScreen::html( array( 'area' => 'jobs' ) ) );

		foreach ( array( fn() => OpsStatus::report( Actor::user( $this->editor ) ), fn() => IntegrationStatus::all( Actor::user( $this->editor ) ), fn() => Diagnostics::current()->build( Actor::user( $this->editor ) ) ) as $call ) {
			try {
				$call();
				self::fail( 'editor without #22' );
			} catch ( CourseException $e ) {
				self::assertSame( 403, $e->status );
			}
		}
		wp_set_current_user( $this->editor );
		$this->expectException( CourseException::class );
		OpsScreen::html( array() );
	}

	/**
	 * SUP-024: failures with IDs (dead letter, failed grant, broken attempt, import without heartbeat); CLI status.
	 *
	 * @group SUP-024
	 * @group SUP-022
	 */
	public function test_SUP_024_checks_with_ids(): void {
		$report = OpsStatus::report( Actor::user( $this->admin ) );
		self::assertSame( 'ok', self::check( $report, 'H-27' )['status'] );
		self::assertSame( 'ok', self::check( $report, 'H-29' )['status'] );

		$delivery = $this->dead_delivery( 'mail transport down' );
		$db       = Database::current();
		$grant    = $db->uuid();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $grant,
				'user_id'     => $this->support,
				'course_id'   => $this->course(),
				'status'      => 'failed',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		$session = $this->broken_session();
		$batch   = $db->insert(
			'ccap_import_batch',
			array(
				'package_id'     => 'pkg',
				'schema_version' => '1.0',
				'status'         => 'importing',
				'manifest'       => '{}',
				'heartbeat_at'   => $db->now( -3600 ),
				'created_at'     => $db->now( -7200 ),
			)
		);

		$report = OpsStatus::report( Actor::user( $this->support ) );
		self::assertSame( 'fail', $report['overall'] );
		self::assertSame( 'fail', self::check( $report, 'H-27' )['status'] );
		self::assertStringStartsWith( 'delivery:' . $delivery, self::check( $report, 'H-27' )['ids'][0] );
		self::assertSame( array( 'grant:' . $grant ), self::check( $report, 'H-29' )['ids'] );
		self::assertSame( array( 'attempt:' . $session ), self::check( $report, 'H-30' )['ids'] );
		self::assertSame( array( 'import:' . $batch ), self::check( $report, 'H-31' )['ids'] );
		self::assertSame( 1, $report['outbox']['dead'] );

		$cli = OpsCommand::execute( 'status', array(), array(), Actor::cli() );
		self::assertSame( 1, $cli['exit'], 'fail → exit 1' );
		self::assertStringContainsString( 'H-27', implode( "\n", $cli['lines'] ) );
		$json = OpsCommand::execute( 'status', array(), array( 'format' => 'json' ), Actor::cli() );
		$data = json_decode( $json['lines'][0], true );
		self::assertSame( 'fail', $data['overall'] );
		self::assertContains( 'H-31', array_column( $data['checks'], 'id' ) );

		wp_set_current_user( $this->support );
		$html = OpsScreen::html( array() );
		self::assertStringContainsString( 'grant:' . $grant, $html );
		self::assertStringContainsString( 'aria-current="page"', $html );
		self::assertStringNotContainsString( 'name="op"', $html );
	}

	/**
	 * INT-012/014/015/016/017, SUP-009: retry and discard dead letters with audit, reason and dedupe.
	 *
	 * @group INT-012
	 * @group INT-014
	 * @group INT-015
	 * @group INT-016
	 * @group INT-017
	 * @group SUP-009
	 */
	public function test_INT_012_014_dead_letter_repair(): void {
		$id      = $this->dead_delivery( 'boom' );
		$db      = Database::current();
		$event   = (string) $db->scalar( $db->prepare( 'SELECT event_uuid FROM `' . $db->table( 'ccap_outbox_delivery' ) . '` WHERE id = %d', $id ) );
		$service = RepairService::current();
		$admin   = Actor::user( $this->admin );
		$key     = wp_generate_uuid4();

		self::assertSame( array( 'count' => 1 ), $service->retry_deliveries( $admin, $key ) );
		self::assertSame( array( 'count' => 1 ), $service->retry_deliveries( $admin, $key ), 'same request key: same answer' );
		self::assertSame( array( 'count' => 0 ), $service->retry_deliveries( $admin, wp_generate_uuid4() ), 'idempotent: nothing left' );
		self::assertSame( 1, $this->audits( 'job_retried' ), 'one audit entry' );
		$row = $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_outbox_delivery' ) . '` WHERE id = %d', $id ) );
		self::assertSame( 'pending', $row['status'] );
		self::assertSame( $event, $row['event_uuid'], 'same event ID' );
		self::assertSame( '1', (string) $row['manual_retries'] );
		self::assertSame( '0', (string) $row['attempts'] );
		self::assertSame( 1, OutboxDispatcher::current()->dispatch()['delivered'], 'delivered exactly once' );
		self::assertSame( 0, OutboxDispatcher::current()->dispatch()['delivered'] );

		$second = $this->dead_delivery( 'again' );
		foreach ( array( '', 'too short' ) as $reason ) {
			try {
				$service->discard_deliveries( $admin, $reason );
				self::fail( 'reason required' );
			} catch ( CourseException $e ) {
				self::assertSame( 'ccap_reason_required', $e->error_code );
			}
		}
		self::assertSame( 1, $this->deliveries( 'dead' ), 'no effect without reason' );
		$key = wp_generate_uuid4();
		self::assertSame( array( 'count' => 1 ), $service->discard_deliveries( $admin, 'Ticket 4711: subscriber removed', $key ) );
		try {
			$service->discard_deliveries( $admin, 'Ticket 4712: another selection', $key );
			self::fail( 'conflict' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_idempotency_conflict', $e->error_code );
			self::assertSame( 409, $e->status );
		}
		$row = $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_outbox_delivery' ) . '` WHERE id = %d', $second ) );
		self::assertSame( 'skipped', $row['status'] );
		self::assertSame( 'discarded', $row['skip_reason'] );
		self::assertNotNull( $row['discarded_at'] );
		self::assertSame( 2, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_event' ) . '`' ), 'events stay in the log' );
		$audit = $db->row( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'job_discarded'" );
		self::assertSame( 'Ticket 4711: subscriber removed', $audit['reason'] );
		self::assertSame( 1, json_decode( (string) $audit['context'], true )['count'] );

		// CLI: confirmation and reason.
		$this->dead_delivery( 'third' );
		self::assertSame( 1, OpsCommand::execute( 'outbox retry', array(), array(), Actor::cli() )['exit'], 'needs --yes' );
		self::assertSame( 1, OpsCommand::execute( 'outbox discard', array(), array( 'yes' => '' ), Actor::cli() )['exit'], 'needs --reason' );
		$out = OpsCommand::execute( 'outbox retry', array(), array( 'yes' => '' ), Actor::cli() );
		self::assertSame( 0, $out['exit'] );
		self::assertSame( 0, $this->deliveries( 'dead' ) );
		$status = json_decode( OpsCommand::execute( 'outbox status', array(), array( 'format' => 'json' ), Actor::cli() )['lines'][0], true );
		self::assertSame( 0, $status['dead'] );
		self::assertSame( 1, $status['pending'] );
	}

	/**
	 * Held notifications via admin-post: nonce, confirmation, reason, audit (E-88).
	 *
	 * @group SUP-038
	 */
	public function test_held_notifications_admin_post(): void {
		$this->held_notifications( 2 );
		wp_set_current_user( $this->admin );
		$nonce = wp_create_nonce( 'ccap_ops_held_release' );

		self::assertSame(
			'ccap_invalid_nonce',
			OpsScreen::process(
				array(
					'op'       => 'held_release',
					'_wpnonce' => wp_create_nonce( 'ccap_ops_held_discard' ),
					'confirm'  => '1',
					'reason'   => 'Ticket 1: same site confirmed',
				),
				$this->admin
			)['result']
		);
		self::assertSame(
			'ccap_confirmation_required',
			OpsScreen::process(
				array(
					'op'       => 'held_release',
					'_wpnonce' => $nonce,
					'reason'   => 'Ticket 1: same site confirmed',
				),
				$this->admin
			)['result']
		);
		self::assertSame(
			'ccap_reason_required',
			OpsScreen::process(
				array(
					'op'       => 'held_release',
					'_wpnonce' => $nonce,
					'confirm'  => '1',
					'reason'   => 'short',
				),
				$this->admin
			)['result']
		);
		self::assertSame(
			'ccap_not_found',
			OpsScreen::process(
				array(
					'op'       => 'drop_tables',
					'_wpnonce' => $nonce,
				),
				$this->admin
			)['result']
		);
		self::assertSame( 2, $this->notifications( 'held' ), 'no effect so far' );

		$html = OpsScreen::html( array( 'area' => 'jobs' ) );
		self::assertStringContainsString( 'name="op" value="held_release"', $html );
		self::assertStringContainsString( 'name="confirm"', $html );
		self::assertStringContainsString( 'minlength="10"', $html );

		$out = OpsScreen::process(
			array(
				'op'          => 'held_release',
				'_wpnonce'    => $nonce,
				'confirm'     => '1',
				'reason'      => 'Ticket 1: same site confirmed',
				'request_key' => wp_generate_uuid4(),
			),
			$this->admin
		);
		self::assertSame( 'ok', $out['result'] );
		self::assertSame( 2, $out['count'] );
		self::assertSame( 2, $this->notifications( 'queued' ) );
		$db = Database::current();
		self::assertSame( 'Ticket 1: same site confirmed', $db->scalar( 'SELECT reason FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'notifications_released'" ) );

		$this->held_notifications( 1 );
		$out = OpsScreen::process(
			array(
				'op'       => 'held_discard',
				'_wpnonce' => wp_create_nonce( 'ccap_ops_held_discard' ),
				'confirm'  => '1',
				'reason'   => 'Ticket 2: staging copy',
			),
			$this->admin
		);
		self::assertSame( 1, $out['count'] );
		self::assertSame( 1, $this->notifications( 'discarded' ) );
		self::assertSame( 1, $this->audits( 'notifications_discarded' ) );
	}

	/**
	 * Admin-post handler redirects back with the result (full entry point).
	 */
	public function test_admin_post_redirects_with_result(): void {
		wp_set_current_user( $this->admin );
		$_POST = array(
			'action'   => OpsScreen::ACTION,
			'op'       => 'cache_flush',
			'_wpnonce' => wp_create_nonce( 'ccap_ops_cache_flush' ),
		);
		add_filter(
			'wp_redirect',
			static function ( $location ): void {
				throw new \RuntimeException( (string) $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test signal, never output.
			}
		);
		try {
			OpsScreen::handle();
			self::fail( 'redirect expected' );
		} catch ( \RuntimeException $e ) {
			self::assertStringContainsString( 'page=' . OpsScreen::SLUG, $e->getMessage() );
			self::assertStringContainsString( 'ccap_ops_result=ok', $e->getMessage() );
		}
		self::assertSame( 1, $this->audits( 'cache_flushed' ) );
		self::assertStringContainsString( 'Done.', OpsScreen::html( array( 'ccap_ops_result' => 'ok' ) ) );
	}

	/**
	 * R0-21 §3.7: `wp course-captain cache flush [--user= --course=]`.
	 *
	 * @group CON-cache-flush
	 */
	public function test_cache_flush_cli(): void {
		$course  = $this->course();
		$content = CacheStore::content_gen();
		$out     = OpsCommand::execute( 'cache flush', array(), array(), Actor::cli() );
		self::assertSame( 0, $out['exit'] );
		self::assertNotSame( $content, CacheStore::content_gen() );

		$user_gen   = CacheStore::user_gen( $this->support );
		$course_gen = CacheStore::course_gen( $course );
		$content    = CacheStore::content_gen();
		$out        = OpsCommand::execute(
			'cache flush',
			array(),
			array(
				'user'   => (string) $this->support,
				'course' => (string) $course,
				'format' => 'json',
			),
			Actor::cli()
		);
		self::assertSame( array( 'scope' => array( 'user:' . $this->support, 'course:' . $course ) ), json_decode( $out['lines'][0], true ) );
		self::assertNotSame( $user_gen, CacheStore::user_gen( $this->support ) );
		self::assertNotSame( $course_gen, CacheStore::course_gen( $course ) );
		self::assertSame( $content, CacheStore::content_gen(), 'scoped flush keeps the content generation' );

		$bad = OpsCommand::execute( 'cache flush', array(), array( 'course' => '999999' ), Actor::cli() );
		self::assertSame( 1, $bad['exit'] );
		self::assertStringContainsString( 'ccap_not_found', $bad['lines'][0] );
		self::assertSame( 2, $this->audits( 'cache_flushed' ) );
		try {
			RepairService::current()->flush_cache( Actor::user( $this->support ) );
			self::fail( 'support may not flush' );
		} catch ( CourseException $e ) {
			self::assertSame( 403, $e->status );
		}
	}

	/**
	 * R0-37 §3.5 "Retry now": poisoned step, automatic runs stay off, the button retries it.
	 *
	 * @group MIG-011
	 */
	public function test_migration_retry_now(): void {
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_migration_step' ) . "` SET status = 'failed', blocked_reason = %s, crash_count = 3, last_error = %s WHERE step_id = %s", StepLog::POISON, 'Lost connection at /var/www/html/wp-content/x.php', 'core:0002_seat_active_user' ) );
		update_option( Migrator::DB_VERSION, '0.9.0+000000000000' );
		update_option( Migrator::FINGERPRINT, 'stale' );
		$GLOBALS['wpdb']->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$report = OpsStatus::report( Actor::user( $this->admin ) );
		self::assertSame( 'blocked', $report['migration']['state'] );
		self::assertSame( array( 'core:0002_seat_active_user' ), self::check( $report, 'H-03' )['ids'] );
		self::assertSame( 'poison', Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN )['status'], 'no automatic retry' );

		wp_set_current_user( $this->support );
		self::assertStringNotContainsString( 'value="migration_retry"', OpsScreen::html( array() ) );
		wp_set_current_user( $this->admin );
		$html = OpsScreen::html( array() );
		self::assertStringContainsString( 'value="migration_retry"', $html );
		self::assertStringNotContainsString( '/var/www/html', $html, 'error shown without paths' );

		$out = OpsScreen::process(
			array(
				'op'       => 'migration_retry',
				'_wpnonce' => wp_create_nonce( 'ccap_ops_migration_retry' ),
			),
			$this->admin
		);
		self::assertSame( 'ok', $out['result'] );
		self::assertSame( 'ok', Migrator::for_current_site()->report()['state'] );
		$audit = $db->row( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'job_retried'" );
		self::assertSame( 'migration', $audit['object_type'] );
		self::assertSame( 'blocked', json_decode( (string) $audit['context'], true )['before'] );
	}

	/**
	 * CT-09, INT-001…INT-007: read-only partner detection; missing/disabled partner leaves learning unchanged.
	 *
	 * @group CT-09
	 * @group INT-001
	 * @group INT-002
	 * @group INT-003
	 * @group INT-004
	 * @group INT-005
	 * @group INT-006
	 * @group INT-007
	 */
	public function test_CT_09_integration_status(): void {
		$admin = Actor::user( $this->admin );
		$all   = IntegrationStatus::all( $admin );
		self::assertSame( array_column( IntegrationStatus::catalog(), 'id' ), array_column( $all, 'provider_id' ) );
		foreach ( $all as $record ) {
			self::assertSame( 'not_installed', $record['state'] );
			self::assertSame( 'not_found', $record['state_reason'] );
			self::assertSame( array(), $record['actions'] );
			self::assertSame( '1.0', $record['schema_version'] );
		}
		self::assertSame( array(), IntegrationStatus::all( $admin, true ), 'INT-001: only detected → empty' );
		wp_set_current_user( $this->support );
		self::assertStringContainsString( 'No partner plugin is installed', OpsScreen::html( array( 'area' => 'integrations' ) ) );
		self::assertStringContainsString(
			'Voucher Captain',
			OpsScreen::html(
				array(
					'area'   => 'integrations',
					'filter' => 'all',
				)
			)
		);

		// A learner with access before detection.
		$course  = $this->course();
		$learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$db      = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $learner,
				'course_id'   => $course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		$before = PolicyService::instance()->decide( Actor::user( $learner ), PolicyService::LEARN, array( $course ) )->allowed;
		self::assertTrue( $before );

		// INT-005 via the real plugin list: Voucher Captain installed, inactive.
		wp_cache_set(
			'plugins',
			array(
				'' => array(
					'voucher-captain/voucher-captain.php' => array(
						'Name'    => 'Voucher Captain',
						'Version' => '1.1.0',
					),
				),
			),
			'plugins'
		);
		$writes = 0;
		$count  = static function ( $query ) use ( &$writes ) {
			if ( 1 === preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE)\b/i', (string) $query ) ) {
				++$writes;
			}
			return $query;
		};
		add_filter( 'query', $count );
		$voucher = array_values( array_filter( IntegrationStatus::all( $admin, true ), static fn( array $r ): bool => 'voucher-captain' === $r['provider_id'] ) );
		remove_filter( 'query', $count );
		self::assertSame( 0, $writes, 'INT-007: detection does not write' );
		self::assertCount( 1, $voucher );
		self::assertSame( 'inactive', $voucher[0]['state'] );
		self::assertSame( 'plugin_inactive', $voucher[0]['state_reason'] );
		self::assertSame( 'installed', $voucher[0]['partner']['detected'] );
		self::assertSame( '>=1.1.0', $voucher[0]['partner']['required'] );
		PolicyService::flush();
		self::assertSame( $before, PolicyService::instance()->decide( Actor::user( $learner ), PolicyService::LEARN, array( $course ) )->allowed, 'CT-09: free learning unchanged' );
		self::assertSame( 1, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_grant' ) . "` WHERE status = 'active'" ), 'grants unchanged' );

		// Derivation rules.
		$catalog = array_column( IntegrationStatus::catalog(), null, 'id' );
		$cases   = array(
			array( 'voucher-captain', 'active', '1.1.0', null, 'active_compatible', 'connector_missing' ),
			array( 'voucher-captain', 'active', '1.0.9', null, 'active_incompatible', 'version_too_low' ),
			array( 'voucher-captain', 'active', null, null, 'active_incompatible', 'no_published_contract' ),
			array( 'affiliate-captain', 'active', null, '1.3.0', 'active_compatible', 'no_exchange_planned' ),
			array( 'affiliate-captain', 'active', null, '2.0.0', 'active_incompatible', 'contract_major_mismatch' ),
			array( 'form-captain', 'active', null, null, 'active_incompatible', 'no_published_contract' ),
			array( 'woocommerce', 'installed', null, null, 'inactive', 'plugin_inactive' ),
			array( 'woocommerce', 'missing', null, null, 'not_installed', 'not_found' ),
		);
		foreach ( $cases as $case ) {
			$derived = IntegrationStatus::derive(
				$catalog[ $case[0] ],
				array(
					'detected' => $case[1],
					'version'  => $case[2],
					'contract' => $case[3],
				)
			);
			self::assertSame( array( $case[4], $case[5] ), array_values( $derived ), implode( ' ', array_map( 'strval', $case ) ) );
		}
		// INT-004: slug active, no documented API → not compatible.
		self::assertSame( array( 'active', null ), array( IntegrationStatus::probe( $catalog['voucher-captain'], array( 'voucher-captain' => true ) )['detected'], IntegrationStatus::probe( $catalog['voucher-captain'], array( 'voucher-captain' => true ) )['version'] ) );

		$cli = OpsCommand::execute( 'integrations status', array(), array( 'format' => 'json' ), Actor::cli() );
		self::assertSame( 0, $cli['exit'] );
		self::assertCount( count( $catalog ), json_decode( $cli['lines'][0], true ) );
	}

	/**
	 * SUP-017/SUP-018/SUP-021: redacted package (allowlist, scrubber, pseudonyms), audit, safe writer.
	 *
	 * @group SUP-017
	 * @group SUP-018
	 * @group SUP-021
	 * @group INT-019
	 */
	public function test_SUP_017_021_redacted_diagnostics(): void {
		$canary = 'Send to ' . self::CANARY_EMAIL . ' from ' . self::CANARY_IP4 . ' / ' . self::CANARY_IP6 . ' failed: Bearer ' . self::CANARY_TOKEN . ' at ' . ABSPATH . 'wp-content/uploads/course-captain/' . self::CANARY_DIR . '/f.pdf and /srv/private/data.json https://hooks.partner.test/x?secret=topsecret user:42 IBAN DE89370400440532013000 site ' . home_url( '/' );
		$this->dead_delivery( $canary );
		$db = Database::current();
		$db->insert(
			'ccap_import_batch',
			array(
				'package_id'     => 'pkg',
				'schema_version' => '1.0',
				'status'         => 'failed',
				'manifest'       => '{}',
				'error'          => $canary,
				'created_at'     => $db->now(),
			)
		);
		$learner = get_userdata( $this->support );

		$first  = Diagnostics::current()->export( Actor::user( $this->support ) );
		$second = Diagnostics::current()->export( Actor::cli() );
		foreach ( array( self::CANARY_EMAIL, 'jane.doe', self::CANARY_IP4, self::CANARY_IP6, self::CANARY_TOKEN, self::CANARY_DIR, '/srv/private', 'topsecret', 'user:42', 'DE89370400440532013000', (string) wp_parse_url( home_url(), PHP_URL_HOST ), rtrim( ABSPATH, '/' ), (string) $learner->user_email, (string) $learner->user_login ) as $secret ) {
			self::assertStringNotContainsString( $secret, $first['body'], 'leak: ' . $secret );
		}
		foreach ( array( '<email>', '<ip>', '<token>', 'protected-<…>', '?<redacted>', '<iban>', '<site>' ) as $placeholder ) {
			self::assertStringContainsString( $placeholder, $first['body'] );
		}
		$package = json_decode( $first['body'], true );
		self::assertSame( array( 'manifest', 'versions', 'environment', 'plugins', 'health', 'jobs', 'imports', 'outbox', 'notifications', 'integrations', 'counts', 'schema' ), array_keys( $package ) );
		foreach ( $package['manifest']['sections'] as $name => $sum ) {
			self::assertSame( $sum, hash( 'sha256', (string) wp_json_encode( $package[ $name ] ) ), 'checksum ' . $name );
		}
		self::assertSame( $package['manifest']['site'], $package['environment']['home'], 'pseudonyms consistent inside a package' );
		self::assertNotSame( $package['manifest']['site'], json_decode( $second['body'], true )['manifest']['site'], 'not comparable across packages' );
		self::assertSame( '<leer>', $package['environment']['secret_key'], 'SUP-018: never a value' );
		self::assertArrayNotHasKey( 'payload', $package['jobs'][0] );
		self::assertSame( hash( 'sha256', $first['body'] ), $first['sha256'] );

		$audit = $db->row( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'diagnostics_exported' ORDER BY id LIMIT 1" );
		$ctx   = json_decode( (string) $audit['context'], true );
		self::assertSame( $first['sha256'], $ctx['sha256'] );
		self::assertSame( $first['bytes'], $ctx['bytes'] );
		self::assertSame( 2, $this->audits( 'diagnostics_exported' ) );
		self::assertArrayHasKey( 'diagnostics_exported', AuditView::registry() );

		// Safe writer: outside the WordPress directory, new file only, 0600.
		$file = trailingslashit( sys_get_temp_dir() ) . 'ccap-diag-' . wp_generate_password( 8, false ) . '.json';
		$out  = OpsCommand::execute( 'diagnostics', array(), array( 'output' => $file ), Actor::cli() );
		self::assertSame( 0, $out['exit'], implode( "\n", $out['lines'] ) );
		self::assertSame( '0600', substr( sprintf( '%o', fileperms( $file ) ), -4 ) );
		self::assertSame( 1, OpsCommand::execute( 'diagnostics', array(), array( 'output' => $file ), Actor::cli() )['exit'], 'never overwrites' );
		wp_delete_file( $file );
		self::assertSame( 1, OpsCommand::execute( 'diagnostics', array(), array( 'output' => ABSPATH . 'ccap-diag.json' ), Actor::cli() )['exit'], 'not inside WordPress' );
		self::assertFileDoesNotExist( ABSPATH . 'ccap-diag.json' );

		wp_set_current_user( $this->support );
		self::assertStringContainsString( 'ccap_ops_diagnostics', OpsScreen::html( array( 'area' => 'diagnostics' ) ) );
	}

	// ---------------------------------------------------------------------
	// Helpers.
	// ---------------------------------------------------------------------

	/**
	 * A check of a report.
	 *
	 * @param array<string, mixed> $report Report.
	 * @param string               $id     Check ID.
	 * @return array<string, mixed>
	 */
	private static function check( array $report, string $id ): array {
		foreach ( $report['checks'] as $check ) {
			if ( $id === $check['id'] ) {
				return $check;
			}
		}
		self::fail( 'check ' . $id . ' missing' );
	}

	/**
	 * Dead internal delivery with an error message.
	 *
	 * @param string $error Error text.
	 * @return int Delivery ID.
	 */
	private function dead_delivery( string $error ): int {
		$db   = Database::current();
		$uuid = $db->uuid();
		$db->insert(
			'ccap_outbox_event',
			array(
				'event_uuid'       => $uuid,
				'event_type'       => 'access.changed',
				'schema_version'   => '1.0',
				'contract_version' => '0.1.0',
				'object_type'      => 'grant',
				'object_id'        => $db->uuid(),
				'user_ref'         => 'user:' . $this->support,
				'idempotency_key'  => 'k:' . $uuid,
				'payload'          => (string) wp_json_encode( array( 'user_ref' => 'user:' . $this->support ) ),
				'created_at'       => $db->now(),
			)
		);
		return $db->insert(
			'ccap_outbox_delivery',
			array(
				'event_uuid'       => $uuid,
				'subscriber_id'    => 'cache',
				'status'           => 'dead',
				'attempts'         => 8,
				'last_error_class' => 'internal',
				'last_error'       => $error,
				'created_at'       => $db->now(),
			)
		);
	}

	/**
	 * Broken exam session (FK checks off: no rule needed).
	 *
	 * @return string UID.
	 */
	private function broken_session(): string {
		$db  = Database::current();
		$uid = $db->uuid();
		$db->execute( 'SET FOREIGN_KEY_CHECKS = 0' );
		$db->insert(
			'ccap_exam_session',
			array(
				'uid'           => $uid,
				'user_id'       => $this->support,
				'course_id'     => 1,
				'rule_id'       => 999,
				'mode'          => 'exam',
				'form_key'      => 'f',
				'status'        => 'broken',
				'start_token'   => $db->uuid(),
				'snapshot'      => '{}',
				'snapshot_hash' => 'sha256:' . str_repeat( '0', 64 ),
				'started_at'    => $db->now(),
			)
		);
		$db->execute( 'SET FOREIGN_KEY_CHECKS = 1' );
		return $uid;
	}

	/**
	 * Held notifications.
	 *
	 * @param int $n Number.
	 * @return void
	 */
	private function held_notifications( int $n ): void {
		$db = Database::current();
		for ( $i = 0; $i < $n; $i++ ) {
			$db->insert(
				'ccap_notification',
				array(
					'uid'        => $db->uuid(),
					'type'       => 'access_granted',
					'dedupe_key' => hash( 'sha256', $db->uuid() ),
					'event_uuid' => $db->uuid(),
					'status'     => 'held',
					'created_at' => $db->now(),
				)
			);
		}
	}

	/**
	 * Published course.
	 *
	 * @return int
	 */
	private function course(): int {
		return (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Ops',
			)
		);
	}

	/**
	 * Deliveries with a status.
	 *
	 * @param string $status Status.
	 * @return int
	 */
	private function deliveries( string $status ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_delivery' ) . '` WHERE status = %s', $status ) );
	}

	/**
	 * Notifications with a status.
	 *
	 * @param string $status Status.
	 * @return int
	 */
	private function notifications( string $status ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_notification' ) . '` WHERE status = %s', $status ) );
	}

	/**
	 * Audit entries of an action.
	 *
	 * @param string $action Action.
	 * @return int
	 */
	private function audits( string $action ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s', $action ) );
	}
}
