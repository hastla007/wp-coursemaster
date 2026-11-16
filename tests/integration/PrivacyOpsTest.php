<?php
/**
 * R1-17: privacy export/erasure, retention, maintenance isolation, health, audit view.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\AuditScreen;
use CourseCaptain\Ops\AuditView;
use CourseCaptain\Ops\HealthChecks;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Schema;

/**
 * Real tables.
 *
 * @group R1-17
 */
final class PrivacyOpsTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->course = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'C',
			)
		);
		PolicyService::flush();
	}

	/**
	 * Learner with data in several tables.
	 *
	 * @return int
	 */
	private function learner_with_data(): int {
		$user = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'learner-' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		$db   = Database::current();
		$db->insert(
			'ccap_course_progress',
			array(
				'user_id'    => $user,
				'course_id'  => $this->course,
				'status'     => 'in_progress',
				'updated_at' => $db->now(),
			)
		);
		$question = $db->insert(
			'ccap_question',
			array(
				'uid'    => $db->uuid(),
				'status' => 'published',
			),
			array( 'created_at', 'updated_at' )
		);
		$db->insert(
			'ccap_favorite',
			array(
				'user_id'     => $user,
				'question_id' => $question,
				'created_at'  => $db->now(),
			)
		);
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		return $user;
	}

	/**
	 * Rows of a user in a table.
	 *
	 * @param string $table  Table.
	 * @param int    $user   User.
	 * @param string $column Column.
	 * @return int
	 */
	private static function rows( string $table, int $user, string $column = 'user_id' ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . "` WHERE `{$column}` = %d", $user ) );
	}

	/**
	 * GM-173: every own table is classified.
	 *
	 * @group GM-173
	 */
	public function test_GM_173_classification(): void {
		$tables = array_keys( Schema::tables() );
		sort( $tables );
		$classified = array_keys( PrivacyService::CLASSIFICATION );
		sort( $classified );
		self::assertSame( $tables, $classified );
	}

	/**
	 * GM-172 / GM-173: export groups, no foreign data, no hashes; erasure leaves foreign data.
	 *
	 * @group GM-172
	 */
	public function test_GM_172_export_and_erase(): void {
		$user   = $this->learner_with_data();
		$other  = $this->learner_with_data();
		$email  = get_userdata( $user )->user_email;
		$data   = PrivacyService::export( $email )['data'];
		$groups = array_unique( array_column( $data, 'group_id' ) );
		self::assertContains( 'cc-progress', $groups );
		self::assertContains( 'cc-favorite', $groups );
		self::assertContains( 'cc-grants', $groups );
		$json = (string) wp_json_encode( $data );
		self::assertStringNotContainsString( 'hash', $json );
		self::assertStringNotContainsString( 'token', $json );
		self::assertSame( 3, count( $data ), 'only own rows' );

		$result = PrivacyService::erase( $email );
		self::assertTrue( $result['items_removed'] );
		foreach ( array( 'ccap_course_progress', 'ccap_favorite', 'ccap_grant' ) as $table ) {
			self::assertSame( 0, self::rows( $table, $user ), $table );
			self::assertSame( 1, self::rows( $table, $other ), 'foreign data stays: ' . $table );
		}
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'personal_data_erased'" ) );
		self::assertStringNotContainsString( (string) $user, (string) $db->scalar( 'SELECT object_id FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'personal_data_erased'" ) );
		self::assertFalse( PrivacyService::erase( $email )['items_removed'], 'nothing left' );
	}

	/**
	 * GM-102 / GM-103: account deletion releases seats and ownership; invitations without account.
	 *
	 * @group GM-102
	 * @group GM-103
	 */
	public function test_GM_102_103_seats_and_invitations(): void {
		$owner  = $this->learner_with_data();
		$member = $this->learner_with_data();
		$db     = Database::current();
		$group  = $db->insert(
			'ccap_group',
			array(
				'uid'           => $db->uuid(),
				'owner_user_id' => $owner,
				'name'          => 'G',
				'seats'         => 5,
				'status'        => 'active',
			)
		);
		$seat   = $db->insert(
			'ccap_seat',
			array(
				'group_id' => $group,
				'user_id'  => $member,
				'status'   => 'active',
			)
		);
		$db->insert(
			'ccap_seat',
			array(
				'group_id'     => $group,
				'invite_email' => 'guest@example.org',
				'status'       => 'invited',
			)
		);
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $member );
		$row = (array) $db->row( $db->prepare( 'SELECT status, user_id FROM `' . $db->table( 'ccap_seat' ) . '` WHERE id = %d', $seat ) );
		self::assertSame( array( 'removed', null ), array( $row['status'], $row['user_id'] ) );
		self::assertSame( 0, self::rows( 'ccap_grant', $member ) );
		wp_delete_user( $owner );
		self::assertSame( '0', (string) $db->scalar( $db->prepare( 'SELECT owner_user_id FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $group ) ), 'group stays without owner' );

		$export = PrivacyService::export( 'guest@example.org' )['data'];
		self::assertSame( array( 'cc-invitations' ), array_values( array_unique( array_column( $export, 'group_id' ) ) ) );
		self::assertTrue( PrivacyService::erase( 'guest@example.org' )['items_removed'] );
		self::assertSame( '0', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_seat' ) . "` WHERE invite_email = 'guest@example.org'" ) );
		$registered = apply_filters( 'wp_privacy_personal_data_exporters', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertArrayHasKey( PrivacyService::EXPORTER, $registered );
	}

	/**
	 * Retention (GM-099 addresses, GM-131 package copies, GM-161 counters, RT-04 answers).
	 *
	 * @group GM-131
	 * @group GM-161
	 */
	public function test_retention(): void {
		$db  = Database::current();
		$now = time();
		$old = gmdate( 'Y-m-d H:i:s', $now - 800 * DAY_IN_SECONDS );
		$db->insert(
			'ccap_rate_limit',
			array(
				'bucket_hash'  => hash( 'sha256', 'x' ),
				'window_start' => 0,
				'hits'         => 3,
				'expires_at'   => gmdate( 'Y-m-d H:i:s', $now - 10 ),
			)
		);
		$user     = $this->learner_with_data();
		$question = (int) $db->scalar( 'SELECT MAX(id) FROM `' . $db->table( 'ccap_question' ) . '`' );
		$db->insert(
			'ccap_question_revision',
			array(
				'question_id'   => $question,
				'revision_no'   => 1,
				'question_type' => 'single_choice',
				'question_text' => 'q',
				'content_hash'  => str_repeat( 'a', 64 ),
				'created_at'    => $db->now(),
			)
		);
		$revision = (int) $db->scalar( 'SELECT MAX(id) FROM `' . $db->table( 'ccap_question_revision' ) . '`' );
		foreach ( array( $old, $db->now() ) as $created ) {
			$db->insert(
				'ccap_attempt',
				array(
					'user_id'         => $user,
					'question_id'     => $question,
					'revision_id'     => $revision,
					'idempotency_key' => wp_generate_uuid4(),
					'created_at'      => $created,
				)
			);
		}
		$group = $db->insert(
			'ccap_group',
			array(
				'uid'           => $db->uuid(),
				'owner_user_id' => $user,
				'name'          => 'G',
				'seats'         => 1,
				'status'        => 'active',
			)
		);
		$seat  = $db->insert(
			'ccap_seat',
			array(
				'group_id'         => $group,
				'invite_email'     => 'expired@example.org',
				'status'           => 'expired',
				'claim_expires_at' => gmdate( 'Y-m-d H:i:s', $now - 31 * DAY_IN_SECONDS ),
			)
		);
		$dir   = \CourseCaptain\Access\ProtectedStorage::dir( 'packages' );
		$files = array();
		foreach ( array( 'superseded', 'superseded', 'superseded', 'superseded', 'published' ) as $i => $status ) {
			$file = $dir . '/ret-' . $i . '-' . wp_generate_password( 4, false ) . '.zip';
			file_put_contents( $file, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$files[] = $file;
			$db->insert(
				'ccap_import_batch',
				array(
					'package_id'     => 'p',
					'schema_version' => '1.0.0',
					'status'         => $status,
					'manifest'       => (string) wp_json_encode( array( 'source' => array( 'system' => 'sys-r' ) ) ),
					'storage_path'   => $file,
				),
				array( 'created_at' )
			);
		}
		$result = RetentionService::run( $now );
		self::assertSame( 1, $result['RT-40_rate_limits'], 'GM-161' );
		self::assertSame( 1, self::rows( 'ccap_attempt', $user ), 'RT-04: old answer removed' );
		self::assertNull( $db->scalar( $db->prepare( 'SELECT invite_email FROM `' . $db->table( 'ccap_seat' ) . '` WHERE id = %d', $seat ) ), 'GM-099' );
		self::assertSame( array( false, false, false, true, true ), array_map( 'is_file', $files ), 'GM-131/RT-26: active package + newest rollback target stay' );
		self::assertGreaterThan( 0, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'retention_applied'" ) );
		self::assertSame( 0, RetentionService::run( $now )['RT-40_rate_limits'], 'idempotent' );
	}

	/**
	 * GM-156 / GM-157: isolated steps, self-healing schedule; health tests.
	 *
	 * @group GM-156
	 * @group GM-157
	 */
	public function test_GM_156_157_maintenance_and_health(): void {
		$ran      = array();
		$failures = HealthChecks::run_isolated(
			array(
				'first'  => static function () use ( &$ran ): void {
					$ran[] = 'first';
				},
				'broken' => static function (): void {
					throw new \RuntimeException( 'boom' );
				},
				'last'   => static function () use ( &$ran ): void {
					$ran[] = 'last';
				},
			)
		);
		self::assertSame( array( 'first', 'last' ), $ran );
		self::assertSame( array( 'broken' => 'RuntimeException' ), $failures );
		self::assertSame( 'recommended', HealthChecks::test_cron()['status'] );

		CronHooks::run_hourly();
		self::assertSame( array(), get_option( HealthChecks::STATE_OPTION )['failures'], 'real hourly run without failures' );

		wp_unschedule_hook( CronHooks::HOURLY );
		self::assertSame( 'critical', HealthChecks::test_cron()['status'] );
		HealthChecks::heal();
		HealthChecks::heal();
		$events = array_filter( (array) _get_cron_array(), static fn( $hooks ): bool => is_array( $hooks ) && isset( $hooks[ CronHooks::HOURLY ] ) );
		self::assertCount( 1, $events, 'no double planning' );
		self::assertSame( 'good', HealthChecks::test_cron()['status'] );
		self::assertSame( 'good', HealthChecks::test_storage()['status'] );
		self::assertSame( 'good', HealthChecks::test_outbox()['status'] );
		self::assertArrayHasKey( 'course_captain_store', HealthChecks::tests( array() )['direct'] );
	}

	/**
	 * GM-138 / GM-139: audit view with plain text, filters, rights, masking; every action registered.
	 *
	 * @group GM-138
	 * @group GM-139
	 */
	public function test_GM_138_139_audit_view(): void {
		$db = Database::current();
		AuditLog::write( $db, Actor::user( $this->admin ), 'grant.manual_created', 'grant', '17', 'Support case', array( 'email' => 'person@example.org' ), 5 );
		AuditLog::write( $db, Actor::user( $this->admin ), 'group.created', 'group', '3' );
		AuditLog::write( $db, Actor::user( $this->admin ), 'grant.extended', 'grant', '17' );
		$view = AuditView::query( Actor::user( $this->admin ), array( 'area' => 'access' ) );
		self::assertSame( 2, $view['total'] );
		self::assertSame( 'Access extended', $view['items'][0]['label'] );
		self::assertStringContainsString( 'p***@example.org', $view['items'][1]['details'] );
		self::assertStringNotContainsString( 'person@', $view['items'][1]['details'] );
		self::assertSame( 1, AuditView::query( Actor::user( $this->admin ), array( 'action' => 'group.created' ) )['total'] );
		self::assertSame( 2, AuditView::query( Actor::user( $this->admin ), array( 'object_id' => '17' ) )['total'] );
		try {
			AuditView::query( Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) );
			self::fail( 'no right' );
		} catch ( CourseException $e ) {
			self::assertSame( 403, $e->status );
		}
		wp_set_current_user( $this->admin );
		$html = AuditScreen::html( array( 'area' => 'access' ) );
		self::assertStringContainsString( 'for="ccap-audit-user"', $html );
		self::assertStringContainsString( 'Access granted manually', $html );

		// GM-139: every audited action in the code has a label and area.
		$actions = array();
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__, 2 ) . '/src' ) ) as $file ) {
			if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			preg_match_all( '/AuditLog::write\(\s*[^,]+,\s*[^,]+,\s*\'([a-z_.]+)\'/s', $code, $m );
			preg_match_all( '/self::audit\(\s*\$actor,\s*\'([a-z_.]+)\'/', $code, $n );
			$actions = array_merge( $actions, $m[1], $n[1] );
		}
		$unknown = array_diff( array_unique( $actions ), array_keys( AuditView::registry() ) );
		self::assertSame( array(), array_values( $unknown ) );
		self::assertGreaterThan( 8, count( array_unique( $actions ) ) );
	}
}
