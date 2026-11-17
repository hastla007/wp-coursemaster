<?php
/**
 * R1-13: grant state machine, groups, seats and claims (R0-09 §6.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GrantTransitions as S;
use CourseCaptain\Access\GroupService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-13
 */
final class AccessTest extends SchemaTestCase {

	/**
	 * Manager.
	 *
	 * @var int
	 */
	private int $manager;

	/**
	 * Learners.
	 *
	 * @var list<int>
	 */
	private array $learners = array();

	/**
	 * Courses A and B.
	 *
	 * @var list<int>
	 */
	private array $courses = array();

	/**
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->manager = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		for ( $i = 0; $i < 6; $i++ ) {
			$this->learners[] = (int) self::factory()->user->create(
				array(
					'role'       => 'subscriber',
					'user_email' => 'learner' . $i . '-' . wp_generate_password( 8, false ) . '@example.org',
				)
			);
		}
		foreach ( array( 'A', 'B' ) as $title ) {
			$this->courses[] = (int) wp_insert_post(
				array(
					'post_type'   => 'ccap_course',
					'post_status' => 'publish',
					'post_title'  => 'C-' . $title,
					'post_author' => $this->manager,
				)
			);
		}
		PolicyService::flush();
	}

	/**
	 * Grant service.
	 *
	 * @return GrantService
	 */
	private static function grants(): GrantService {
		return GrantService::current();
	}

	/**
	 * Row count helper.
	 *
	 * @param string $table Table.
	 * @param string $where Condition (trusted SQL).
	 * @return int
	 */
	private static function rows_in( string $table, string $where = '1=1' ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '` WHERE ' . $where );
	}

	/**
	 * Grant status.
	 *
	 * @param int $id Grant.
	 * @return string
	 */
	private static function status( int $id ): string {
		$db = Database::current();
		return (string) $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $id ) );
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
	 * GRANT-001: transition table.
	 *
	 * @group GRANT-001
	 */
	public function test_GRANT_001_transition_table(): void {
		self::assertTrue( S::allowed( S::ACTIVE, S::REVOKED ) );
		self::assertTrue( S::allowed( S::PENDING_CONFIRMATION, S::ACTIVE ) );
		self::assertFalse( S::allowed( S::REVOKED, S::ACTIVE ) );
		self::assertFalse( S::allowed( S::EXPIRED, S::ACTIVE ) );
		self::assertFalse( S::allowed( S::PENDING_CONFIRMATION, S::SUSPENDED ) );
		self::assertTrue( S::is_final( S::REVOKED ) );
	}

	/**
	 * GRANT-002 / GRANT-003: manual grant idempotent per request key; reason and future end required.
	 *
	 * @group GRANT-002
	 * @group GRANT-003
	 * @group GM-087
	 */
	public function test_GRANT_002_003_manual(): void {
		$key   = wp_generate_uuid4();
		$actor = Actor::user( $this->manager );
		$first = self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], $key, 'Support case' );
		self::assertSame( $first, self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], $key, 'Support case' ) );
		self::assertSame( 1, self::rows_in( 'ccap_grant' ) );
		self::assertSame( 1, self::rows_in( 'ccap_grant_event', "event_type = 'created'" ) );
		self::assertSame( 1, self::rows_in( 'ccap_outbox_event', "event_type = 'grant.created'" ) );
		self::assertSame( 1, self::rows_in( 'ccap_outbox_event', "event_type = 'access.changed'" ) );
		self::assertSame( 1, self::rows_in( 'ccap_audit_log', "action = 'grant.manual_created'" ) );
		self::assertSame( 'ok', ( new \CourseCaptain\Policy\Relations( Database::current() ) )->effective_access( $this->learners[0], $this->courses[0], 0, 'view' ) );

		$this->assertRejected( fn() => self::grants()->grant_manual( $actor, $this->learners[1], $this->courses[0], wp_generate_uuid4(), ' ' ), 'ccap_reason_required' );
		$this->assertRejected( fn() => self::grants()->grant_manual( $actor, $this->learners[1], $this->courses[0], wp_generate_uuid4(), 'x', array( 'valid_until' => '2000-01-01 00:00:00' ) ), 'ccap_invalid_param' );
		$trainer = (int) self::factory()->user->create( array( 'role' => 'ccap_trainer' ) );
		$this->assertRejected( fn() => self::grants()->grant_manual( Actor::user( $trainer ), $this->learners[1], $this->courses[0], wp_generate_uuid4(), 'x' ), 'ccap_forbidden' );
		self::assertSame( 1, self::rows_in( 'ccap_grant' ) );
	}

	/**
	 * GRANT-004 / GRANT-006: same source event repeatedly → one grant; same key, other learner → conflict.
	 *
	 * @group GRANT-004
	 * @group GRANT-006
	 * @group GM-088
	 */
	public function test_GRANT_004_006_source_idempotency(): void {
		$spec = array(
			'user_id'     => $this->learners[0],
			'course_id'   => $this->courses[0],
			'source_type' => 'fake',
			'source_id'   => 'order-1',
			'source_line' => '1',
		);
		for ( $i = 0; $i < 10; $i++ ) {
			self::grants()->create( $spec, Actor::cron(), 'order_paid' );
		}
		self::assertSame( 1, self::rows_in( 'ccap_grant' ) );
		$spec['user_id'] = $this->learners[1];
		$this->assertRejected( fn() => self::grants()->create( $spec, Actor::cron(), 'order_paid' ), 'ccap_idempotency_conflict' );
	}

	/**
	 * GRANT-007 / GRANT-009: no revival after revoke or expiry; reactivation is a new revision (E-29).
	 *
	 * @group GRANT-007
	 * @group GRANT-009
	 */
	public function test_GRANT_007_009_final_states(): void {
		$actor = Actor::user( $this->manager );
		$id    = self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], wp_generate_uuid4(), 'x' );
		self::assertSame( S::REVOKED, self::grants()->transition( Actor::cron(), $id, S::REVOKED, 'order_refunded' ) );
		self::assertSame( 'ignored', self::grants()->transition( Actor::cron(), $id, S::ACTIVE, 'order_paid' ) );
		self::assertSame( S::REVOKED, self::status( $id ) );
		self::assertSame( 1, self::rows_in( 'ccap_grant_event', "event_type = 'ignored'" ) );

		$expired = self::grants()->grant_manual( $actor, $this->learners[1], $this->courses[0], wp_generate_uuid4(), 'x', array( 'valid_until' => Database::current()->now( 60 ) ) );
		Database::current()->execute( Database::current()->prepare( 'UPDATE `' . Database::current()->table( 'ccap_grant' ) . '` SET valid_until = %s WHERE id = %d', Database::current()->now( -1 ), $expired ) );
		self::assertSame( 1, self::grants()->expire_due() );
		$this->assertRejected( fn() => self::grants()->extend( $actor, $expired, Database::current()->now( 86400 ), 'x' ), 'ccap_conflict' );
		$new = self::grants()->reactivate( $actor, $expired, 'reactivated', Database::current()->now( 86400 ) );
		self::assertNotSame( $expired, $new );
		self::assertSame( S::EXPIRED, self::status( $expired ) );
		self::assertSame( S::ACTIVE, self::status( $new ) );
		$db = Database::current();
		self::assertSame( '2', $db->scalar( $db->prepare( 'SELECT source_revision FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $new ) ) );
	}

	/**
	 * GRANT-010: revoking one of two equivalent grants emits no access.changed.
	 *
	 * @group GRANT-010
	 * @group GM-085
	 */
	public function test_GRANT_010_no_access_change_with_second_source(): void {
		$actor = Actor::user( $this->manager );
		$one   = self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], wp_generate_uuid4(), 'x' );
		self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], wp_generate_uuid4(), 'y', array( 'renewal_mode' => 'parallel' ) );
		$before = self::rows_in( 'ccap_outbox_event', "event_type = 'access.changed'" );
		self::grants()->transition( $actor, $one, S::REVOKED, 'manual_revoke', true );
		self::assertSame( $before, self::rows_in( 'ccap_outbox_event', "event_type = 'access.changed'" ) );
	}

	/**
	 * GRANT-012: extend chain and restack after refund of the first grant.
	 *
	 * @group GRANT-012
	 * @group GM-084
	 */
	public function test_GRANT_012_chain_and_restack(): void {
		$actor  = Actor::user( $this->manager );
		$first  = self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], wp_generate_uuid4(), 'x', array( 'duration_days' => 30 ) );
		$second = self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], wp_generate_uuid4(), 'y', array( 'duration_days' => 30 ) );
		$db     = Database::current();
		$row    = static fn( int $id ): array => (array) $db->row( $db->prepare( 'SELECT valid_from, valid_until FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $id ) );
		self::assertSame( $row( $first )['valid_until'], $row( $second )['valid_from'], 'second starts where the first ends' );

		self::grants()->transition( $actor, $first, S::REVOKED, 'item_refunded', true );
		$moved = $row( $second );
		self::assertLessThanOrEqual( 2, abs( strtotime( $moved['valid_from'] ) - time() ), 'moved forward' );
		self::assertSame( 30 * 86400, strtotime( $moved['valid_until'] ) - strtotime( $moved['valid_from'] ), 'duration kept' );
		self::assertSame( 1, self::rows_in( 'ccap_grant_event', "event_type = 'rescheduled'" ) );
	}

	/**
	 * GRANT-015 / GRANT-016 / GRANT-020: manual precedence, ignored pending suspend, retry after failure.
	 *
	 * @group GRANT-015
	 * @group GRANT-016
	 * @group GRANT-020
	 */
	public function test_GRANT_015_016_020_precedence_and_retry(): void {
		$actor = Actor::user( $this->manager );
		$id    = self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], wp_generate_uuid4(), 'x' );
		self::grants()->transition( $actor, $id, S::SUSPENDED, 'manual_suspend', true );
		self::assertSame( 'ignored', self::grants()->transition( Actor::cron(), $id, S::ACTIVE, 'dispute_won' ) );
		self::assertSame( S::REVOKED, self::grants()->transition( Actor::cron(), $id, S::REVOKED, 'chargeback_lost' ) );

		$pending = self::grants()->create(
			array(
				'user_id'     => $this->learners[1],
				'course_id'   => $this->courses[0],
				'source_type' => 'fake',
				'source_id'   => 'order-9',
				'status'      => S::PENDING_CONFIRMATION,
			),
			Actor::cron(),
			'consent_missing'
		)['grant_id'];
		self::assertSame( 'ignored', self::grants()->transition( Actor::cron(), $pending, S::SUSPENDED, 'payment_disputed' ) );
		Database::current()->execute( Database::current()->prepare( 'UPDATE `' . Database::current()->table( 'ccap_grant' ) . '` SET created_at = %s WHERE id = %d', Database::current()->now( -25 * 3600 ), $pending ) );
		self::assertSame( 1, self::grants()->fail_stale_pending(), 'E-201 pending timeout' );
		self::assertSame( S::FAILED, self::status( $pending ) );
		self::assertSame( S::ACTIVE, self::grants()->transition( Actor::cron(), $pending, S::ACTIVE, 'retry' ) );
	}

	/**
	 * GRANT-018: failure before the outbox write rolls back grant and event.
	 *
	 * @group GRANT-018
	 */
	public function test_GRANT_018_atomic_transition(): void {
		$actor  = Actor::user( $this->manager );
		$id     = self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], wp_generate_uuid4(), 'x' );
		$events = self::rows_in( 'ccap_grant_event' );
		$broken = static fn(): array => array( str_repeat( 'x', 80 ) => array( 'grant.*' ) );
		add_filter( 'course_captain_internal_subscribers', $broken );
		Database::current()->wpdb()->suppress_errors( true );
		try {
			self::grants()->transition( $actor, $id, S::REVOKED, 'manual_revoke', true );
			self::fail( 'outbox failure must abort' );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		remove_filter( 'course_captain_internal_subscribers', $broken );
		self::assertSame( S::ACTIVE, self::status( $id ) );
		self::assertSame( $events, self::rows_in( 'ccap_grant_event' ) );
	}

	/**
	 * GRANT-033: dependency cycle rejected.
	 *
	 * @group GRANT-033
	 */
	public function test_GRANT_033_dependency_cycle(): void {
		$actor = Actor::user( $this->manager );
		self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[1], wp_generate_uuid4(), 'x', array( 'depends_on_course_id' => $this->courses[0] ) );
		$this->assertRejected( fn() => self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], wp_generate_uuid4(), 'x', array( 'depends_on_course_id' => $this->courses[1] ) ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => self::grants()->grant_manual( $actor, $this->learners[0], $this->courses[0], wp_generate_uuid4(), 'x', array( 'depends_on_course_id' => $this->courses[0] ) ), 'ccap_validation_failed' );
	}

	/**
	 * Group helper.
	 *
	 * @param int    $seats  Seats.
	 * @param string $policy Claim policy.
	 * @return int
	 */
	private function group( int $seats, string $policy = 'transferable' ): int {
		return GroupService::current()->create(
			Actor::user( $this->manager ),
			$this->learners[5],
			'GRP https://spam.example.com mail@x.de',
			$seats,
			array( array( 'course_id' => $this->courses[0] ) ),
			wp_generate_uuid4(),
			array( 'claim_policy' => $policy )
		);
	}

	/**
	 * SEAT-001 / SEAT-021: idempotent creation, owner without grant, sanitized name.
	 *
	 * @group SEAT-001
	 * @group SEAT-021
	 * @group GM-094
	 */
	public function test_SEAT_001_021_create(): void {
		$key   = wp_generate_uuid4();
		$args  = array( Actor::user( $this->manager ), $this->learners[5], 'Team', 3, array( array( 'course_id' => $this->courses[0] ) ), $key );
		$group = GroupService::current()->create( ...$args );
		self::assertSame( $group, GroupService::current()->create( ...$args ) );
		self::assertSame( 0, self::rows_in( 'ccap_grant' ), 'owner gets no grant' );
		self::assertSame( 'GRP', GroupService::sanitize_name( 'GRP https://spam.example.com mail@x.de' ) );
	}

	/**
	 * SEAT-002 / SEAT-005 / ID-002: invitations reserve seats; claim once; tokens only hashed.
	 *
	 * @group SEAT-002
	 * @group SEAT-005
	 * @group ID-002
	 * @group GM-098
	 */
	public function test_SEAT_002_005_invitations(): void {
		$service = GroupService::current();
		$owner   = Actor::user( $this->learners[5] );
		$group   = $this->group( 2 );
		$one     = $service->invite( $owner, $group, 'a@example.org' );
		$two     = $service->invite( $owner, $group, 'b@example.org' );
		self::assertNotNull( $one );
		self::assertNotNull( $two );
		self::assertNull( $service->invite( $owner, $group, 'c@example.org' ), 'no free seat, skipped' );
		self::assertStringStartsWith( 'cci_', $one['token'] );
		$db   = Database::current();
		$dump = (string) wp_json_encode( $db->rows( 'SELECT * FROM `' . $db->table( 'ccap_seat' ) . '`' ) ) . (string) wp_json_encode( $db->rows( 'SELECT * FROM `' . $db->table( 'ccap_outbox_event' ) . '`' ) );
		self::assertStringNotContainsString( $one['token'], $dump, 'only the hash is stored' );

		$seat = $service->claim( Actor::user( $this->learners[0] ), $one['token'] );
		self::assertSame( $seat, $service->claim( Actor::user( $this->learners[0] ), $one['token'] ), 'idempotent for the same account' );
		$this->assertRejected( fn() => $service->claim( Actor::user( $this->learners[1] ), $one['token'] ), 'ccap_claim_used' );
		self::assertSame( 1, self::rows_in( 'ccap_grant', "source_type = 'group_seat'" ) );
		self::assertSame( 1, self::rows_in( 'ccap_seat', 'invite_email IS NULL AND status = \'active\'' ), 'address cleared on claim' );
	}

	/**
	 * Group link: first come first served; group full; rotation (SEAT-014); SEAT-016.
	 *
	 * @group SEAT-014
	 * @group SEAT-016
	 * @group GM-095
	 */
	public function test_group_link_full_rotation_and_status(): void {
		$service = GroupService::current();
		$group   = $this->group( 2 );
		$link    = $service->rotate_link( Actor::user( $this->learners[5] ), $group );
		self::assertStringStartsWith( 'ccg_', $link );
		$service->claim( Actor::user( $this->learners[0] ), $link );
		$service->claim( Actor::user( $this->learners[1] ), $link );
		$this->assertRejected( fn() => $service->claim( Actor::user( $this->learners[2] ), $link ), 'ccap_group_full' );

		$new = $service->rotate_link( Actor::user( $this->learners[5] ), $group );
		$this->assertRejected( fn() => $service->claim( Actor::user( $this->learners[2] ), $link ), 'ccap_invalid_token' );

		$service->set_status( Actor::user( $this->manager ), $group, 'suspended' );
		$this->assertRejected( fn() => $service->claim( Actor::user( $this->learners[3] ), $new ), 'ccap_group_not_active' );
		self::assertSame( 2, self::rows_in( 'ccap_grant', "status = 'suspended'" ), 'SEAT-012: seat grants follow' );
		$service->set_status( Actor::user( $this->manager ), $group, 'active' );
		self::assertSame( 2, self::rows_in( 'ccap_grant', "status = 'active'" ) );
	}

	/**
	 * SEAT-009 / SEAT-010: removal and reduction order.
	 *
	 * @group SEAT-009
	 * @group SEAT-010
	 * @group GM-101
	 */
	public function test_SEAT_009_010_remove_and_reduce(): void {
		$service = GroupService::current();
		$owner   = Actor::user( $this->learners[5] );
		$group   = $this->group( 6 );
		$link    = $service->rotate_link( $owner, $group );
		$seats   = array();
		foreach ( array( 0, 1, 2, 3 ) as $i ) {
			$seats[] = $service->claim( Actor::user( $this->learners[ $i ] ), $link );
			Database::current()->execute( Database::current()->prepare( 'UPDATE `' . Database::current()->table( 'ccap_seat' ) . '` SET claimed_at = %s WHERE id = %d', Database::current()->now( -1000 + $i ), end( $seats ) ) );
		}
		$inv_a = $service->invite( $owner, $group, 'x@example.org' );
		$inv_b = $service->invite( $owner, $group, 'y@example.org' );

		$preview = $service->reduce_seats( Actor::user( $this->manager ), $group, 3, true );
		self::assertSame( array( $inv_b['seat_id'], $inv_a['seat_id'], $seats[3] ), $preview, 'invitations (newest first), then last joined' );
		self::assertSame( 6, $service->occupied( $group ), 'dry run writes nothing' );
		$service->reduce_seats( Actor::user( $this->manager ), $group, 3 );
		self::assertSame( 3, $service->occupied( $group ) );

		$service->remove_seat( $owner, $group, $seats[0] );
		self::assertSame( 2, self::rows_in( 'ccap_grant', "status = 'revoked'" ) );
		self::assertSame( $seats[1], $service->claim( Actor::user( $this->learners[1] ), $link ) );
		$again = $service->claim( Actor::user( $this->learners[0] ), $link );
		self::assertNotSame( $seats[0], $again, 'new seat row, no revival' );
	}

	/**
	 * SEAT-007 / SEAT-015: expired invitation; invited address only.
	 *
	 * @group SEAT-007
	 * @group SEAT-015
	 * @group GM-099
	 */
	public function test_SEAT_007_015_expiry_and_address_binding(): void {
		$service = GroupService::current();
		$group   = $this->group( 3, 'invited_address_only' );
		$invite  = $service->invite( Actor::user( $this->learners[5] ), $group, get_userdata( $this->learners[1] )->user_email );
		$this->assertRejected( fn() => $service->claim( Actor::user( $this->learners[2] ), $invite['token'] ), 'ccap_invite_other_account' );
		$this->assertRejected( fn() => $service->rotate_link( Actor::user( $this->learners[5] ), $group ), 'ccap_invalid_param' );
		$service->claim( Actor::user( $this->learners[1] ), $invite['token'] );

		$late = $service->invite( Actor::user( $this->learners[5] ), $group, get_userdata( $this->learners[3] )->user_email );
		Database::current()->execute( Database::current()->prepare( 'UPDATE `' . Database::current()->table( 'ccap_seat' ) . '` SET claim_expires_at = %s WHERE id = %d', Database::current()->now( -1 ), $late['seat_id'] ) );
		self::assertSame( 1, $service->occupied( $group ), 'expired invitation occupies no seat' );
		self::assertSame( 1, $service->expire_invitations() );
		$this->assertRejected( fn() => $service->claim( Actor::user( $this->learners[3] ), $late['token'] ), 'ccap_invalid_token' );
	}
}
