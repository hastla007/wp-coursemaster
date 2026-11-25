<?php
/**
 * CORE-SPEC-007: R1 core of the reference acceptance cases CORE-01,
 * CORE-02, CORE-03, CORE-05, CORE-07 and CORE-12 (konzept 35.5) with the
 * expected DB state and audit trail of
 * docs/r0/R0-41-prozesskatalog-matrix.md §3. The sources are the neutral
 * FakeSource (R1-13); the Woo and Voucher variants are R3 (R0-40 §6,
 * FIN-001 ff., E-186).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\FakeSource;
use CourseCaptain\Access\GrantReconciler;
use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GrantTransitions;
use CourseCaptain\Access\GroupService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-13
 * @group CORE-SPEC-007
 */
final class CoreAcceptanceTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Buyer / learner of the source object.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Existing third party whose address a source reports.
	 *
	 * @var int
	 */
	private int $third;

	/**
	 * Courses.
	 *
	 * @var list<int>
	 */
	private array $courses = array();

	/**
	 * Two learners and three published courses.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->third   = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'dritte-' . wp_generate_password( 8, false ) . '@example.org',
			)
		);
		foreach ( array( 'A', 'B', 'C' ) as $title ) {
			$this->courses[] = (int) wp_insert_post(
				array(
					'post_type'   => 'ccap_course',
					'post_status' => 'publish',
					'post_title'  => 'CORE-' . $title,
					'post_author' => $this->admin,
				)
			);
		}
		PolicyService::flush();
	}

	/**
	 * Rows of a table under a condition.
	 *
	 * @param string $table Table without prefix.
	 * @param string $where Condition.
	 * @return int
	 */
	private static function rows_in( string $table, string $where = '1=1' ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '` WHERE ' . $where );
	}

	/**
	 * Grants of a source reference: line => status.
	 *
	 * @param string $reference Source reference.
	 * @return array<string, string>
	 */
	private static function grants_of( string $reference ): array {
		$db  = Database::current();
		$out = array();
		foreach ( $db->rows( $db->prepare( 'SELECT source_line, status FROM `' . $db->table( 'ccap_grant' ) . "` WHERE source_type = 'fake' AND source_id = %s ORDER BY source_line", $reference ) ) as $row ) {
			$out[ (string) $row['source_line'] ] = (string) $row['status'];
		}
		return $out;
	}

	/**
	 * Grant events of a source reference.
	 *
	 * @param string $reference Source reference.
	 * @return list<array<string, mixed>>
	 */
	private static function events_of( string $reference ): array {
		$db = Database::current();
		return $db->rows( $db->prepare( 'SELECT e.event_type, e.from_status, e.to_status, e.reason_code, e.actor_type FROM `' . $db->table( 'ccap_grant_event' ) . '` e JOIN `' . $db->table( 'ccap_grant' ) . "` g ON g.id = e.grant_id WHERE g.source_type = 'fake' AND g.source_id = %s ORDER BY e.id", $reference ) );
	}

	/**
	 * Effective access.
	 *
	 * @param int $user   User.
	 * @param int $course Course.
	 * @return string
	 */
	private static function access( int $user, int $course ): string {
		PolicyService::flush();
		return ( new Relations( Database::current() ) )->effective_access( $user, $course, 0, 'view' );
	}

	/**
	 * Source snapshot.
	 *
	 * @param string               $reference Reference.
	 * @param string               $status    Source status.
	 * @param int                  $revision  Source revision.
	 * @param array<string, mixed> $extra     Further fields (user_id, email, lines).
	 * @return array<string, mixed>
	 */
	private function snapshot( string $reference, string $status, int $revision, array $extra = array() ): array {
		return $extra + array(
			'reference' => $reference,
			'status'    => $status,
			'revision'  => $revision,
			'user_id'   => $this->learner,
			'lines'     => array(
				array(
					'line'    => '1',
					'targets' => array( array( 'course_id' => $this->courses[0] ) ),
				),
			),
		);
	}

	/**
	 * CORE-01 (R1 core, ID-001): a source reports a paid order with the
	 * address of an existing third party and no verified account. No grant,
	 * no grant event, no `access.changed`, no audit entry; the third party
	 * has no access. The grant API refuses a request without a learner
	 * account (`ccap_user_required`), an address is never resolved.
	 *
	 * @group CORE-01
	 * @group ID-001
	 */
	public function test_CORE_01_foreign_address_without_claim_creates_nothing(): void {
		$source = new FakeSource();
		$email  = get_userdata( $this->third )->user_email;
		$snap   = $this->snapshot( 'order-c01', 'paid', 1, array( 'email' => $email ) );
		unset( $snap['user_id'] );
		$source->put( $snap );
		$audit  = self::rows_in( 'ccap_audit_log' );
		$result = GrantReconciler::current()->reconcile( $source, $source, 'order-c01', 'evt-c01' );
		self::assertSame( 0, $result['created'] );
		self::assertSame( array(), self::grants_of( 'order-c01' ) );
		self::assertSame( 0, self::rows_in( 'ccap_grant', 'user_id = ' . $this->third ) );
		self::assertSame( 0, self::rows_in( 'ccap_outbox_event', "event_type IN ('grant.created', 'access.changed')" ) );
		self::assertSame( $audit, self::rows_in( 'ccap_audit_log' ) );
		self::assertNotSame( 'ok', self::access( $this->third, $this->courses[0] ) );

		try {
			GrantService::current()->grant_from_source(
				array(
					'user_id'          => 0,
					'email'            => $email,
					'course_id'        => $this->courses[0],
					'source_type'      => 'fake',
					'source_reference' => 'order-c01b',
				),
				Actor::connector( 'fake' )
			);
			self::fail( 'grant without account' );
		} catch ( CourseException $e ) {
			self::assertSame( array( 401, 'ccap_user_required' ), array( $e->status, $e->error_code ) );
		}
		self::assertSame( 0, self::rows_in( 'ccap_grant' ) );
	}

	/**
	 * CORE-02 (R1 seats, SEAT-001 with 10 seats and 10 accounts; konzept
	 * 35.1): the buyer owns a group with ten seats and assigns them to ten
	 * different learners. Ten seat rows with ten distinct learners, ten
	 * seat grants with distinct seat IDs, the owner holds no grant; an
	 * eleventh invitation finds no seat; the owner sees neither the group
	 * progress nor a member (RBAC-036).
	 *
	 * @group CORE-02
	 * @group SEAT-001
	 */
	public function test_CORE_02_ten_seats_for_ten_learners(): void {
		$service  = GroupService::current();
		$owner    = $this->learner;
		$group    = $service->create( Actor::user( $this->admin ), $owner, 'Zehn Plätze', 10, array( array( 'course_id' => $this->courses[0] ) ), wp_generate_uuid4() );
		$learners = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$learners[] = (int) self::factory()->user->create(
				array(
					'role'       => 'subscriber',
					'user_email' => 'platz' . $i . '-' . wp_generate_password( 6, false ) . '@example.org',
				)
			);
		}
		$seats = array();
		foreach ( $learners as $user ) {
			$invite = $service->invite( Actor::user( $owner ), $group, get_userdata( $user )->user_email );
			self::assertNotNull( $invite );
			$seats[] = $service->claim( Actor::user( $user ), $invite['token'] );
		}
		self::assertNull( $service->invite( Actor::user( $owner ), $group, 'elf@example.org' ), 'no eleventh seat' );

		$db   = Database::current();
		$rows = $db->rows( $db->prepare( 'SELECT id, user_id, status, invite_email FROM `' . $db->table( 'ccap_seat' ) . '` WHERE group_id = %d ORDER BY id', $group ) );
		self::assertCount( 10, $rows );
		self::assertSame( array( 'active' ), array_values( array_unique( array_column( $rows, 'status' ) ) ) );
		self::assertSame( array( null ), array_values( array_unique( array_column( $rows, 'invite_email' ) ) ), 'address cleared on claim' );
		self::assertCount( 10, array_unique( array_column( $rows, 'user_id' ) ) );
		self::assertCount( 10, array_unique( $seats ) );
		$grants = $db->rows( 'SELECT user_id, seat_id, status FROM `' . $db->table( 'ccap_grant' ) . "` WHERE source_type = 'group_seat'" );
		self::assertCount( 10, $grants );
		self::assertCount( 10, array_unique( array_column( $grants, 'seat_id' ) ) );
		self::assertEqualsCanonicalizing( array_map( 'strval', $learners ), array_map( 'strval', array_column( $grants, 'user_id' ) ) );
		self::assertSame( 0, self::rows_in( 'ccap_grant', 'user_id = ' . $owner ), 'the owner holds no grant' );
		self::assertSame( 1, self::rows_in( 'ccap_audit_log', "action = 'group.created'" ) );
		self::assertSame( 10, self::rows_in( 'ccap_outbox_event', "event_type = 'seat.claimed'" ) );
		foreach ( $learners as $user ) {
			self::assertSame( 'ok', self::access( $user, $this->courses[0] ) );
		}
		$policy = PolicyService::instance();
		self::assertFalse( $policy->decide( Actor::user( $owner ), PolicyService::VIEW_GROUP_PROGRESS, array( $group ) )->allowed );
		self::assertFalse( $policy->decide( Actor::user( $owner ), PolicyService::VIEW_LEARNER, array( $learners[0] ) )->allowed );
	}

	/**
	 * CORE-03 (R1 core, GRANT-004): the same source callback ten times (same
	 * revision, same event ID) and the same grant request ten times: exactly
	 * one grant per source key, one `created` grant event, one
	 * `access.changed`, one `grant.source_created` audit entry.
	 *
	 * @group CORE-03
	 * @group GRANT-004
	 */
	public function test_CORE_03_same_callback_ten_times_gives_one_grant(): void {
		$source = new FakeSource();
		$source->put( $this->snapshot( 'order-c03', 'paid', 1 ) );
		$created = 0;
		for ( $i = 0; $i < 10; $i++ ) {
			$created += GrantReconciler::current()->reconcile( $source, $source, 'order-c03', 'evt-c03' )['created'];
		}
		self::assertSame( 1, $created );
		self::assertSame( array( '1' => GrantTransitions::ACTIVE ), self::grants_of( 'order-c03' ) );
		self::assertSame( array( 'created' ), array_column( self::events_of( 'order-c03' ), 'event_type' ) );
		self::assertSame( 'connector', self::events_of( 'order-c03' )[0]['actor_type'] );
		self::assertSame( 1, self::rows_in( 'ccap_outbox_event', "event_type = 'access.changed'" ) );
		self::assertSame( 'ok', self::access( $this->learner, $this->courses[0] ) );

		$fake    = Actor::connector( 'fake' );
		$request = array(
			'user_id'          => $this->learner,
			'course_id'        => $this->courses[1],
			'source_type'      => 'fake',
			'source_reference' => 'voucher-c03',
		);
		$codes   = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$codes[] = GrantService::current()->grant_from_source( $request, $fake )['code'];
		}
		self::assertSame( array_merge( array( 'created' ), array_fill( 0, 9, 'duplicate' ) ), $codes );
		self::assertSame( 1, self::rows_in( 'ccap_grant', "source_id = 'voucher-c03'" ) );
		self::assertSame( 1, self::rows_in( 'ccap_audit_log', "action = 'grant.source_created'" ) );
	}

	/**
	 * CORE-05 (R1 core of the Woo variant R3.1, FakeSource lines): one of
	 * three positions is refunded. Only the grant of that line becomes
	 * `revoked` (`item_refunded`), one status event, the two other grants
	 * and their access stay.
	 *
	 * @group CORE-05
	 */
	public function test_CORE_05_partial_refund_revokes_only_that_line(): void {
		$lines  = static fn( array $courses, string $refunded = '' ): array => array_map(
			static fn( int $i, int $course ): array => array(
				'line'    => (string) ( $i + 1 ),
				'targets' => array( array( 'course_id' => $course ) ),
				'refund'  => (string) ( $i + 1 ) === $refunded ? 'full' : 'none',
			),
			array_keys( $courses ),
			$courses
		);
		$source = new FakeSource();
		$source->put( $this->snapshot( 'order-c05', 'paid', 1, array( 'lines' => $lines( $this->courses ) ) ) );
		self::assertSame( 3, GrantReconciler::current()->reconcile( $source, $source, 'order-c05' )['created'] );

		$source->put( $this->snapshot( 'order-c05', 'paid', 2, array( 'lines' => $lines( $this->courses, '2' ) ) ) );
		self::assertSame( 1, GrantReconciler::current()->reconcile( $source, $source, 'order-c05' )['changed'] );
		self::assertSame(
			array(
				'1' => GrantTransitions::ACTIVE,
				'2' => GrantTransitions::REVOKED,
				'3' => GrantTransitions::ACTIVE,
			),
			self::grants_of( 'order-c05' )
		);
		$changes = array_values( array_filter( self::events_of( 'order-c05' ), static fn( array $e ): bool => 'created' !== $e['event_type'] ) );
		self::assertCount( 1, $changes );
		self::assertSame( array( 'active', 'revoked', 'item_refunded' ), array( $changes[0]['from_status'], $changes[0]['to_status'], $changes[0]['reason_code'] ) );
		self::assertSame( array( 'ok', 'ok' ), array( self::access( $this->learner, $this->courses[0] ), self::access( $this->learner, $this->courses[2] ) ) );
		self::assertNotSame( 'ok', self::access( $this->learner, $this->courses[1] ) );
	}

	/**
	 * CORE-07 (R1 core, GRANT-007/GRANT-025 with FakeSource): the refund is
	 * seen before the late payment. A refund without a grant creates none
	 * and the late payment callback reads the refunded state; the older
	 * `paid` revision is stale; after a revocation a newer `paid`
	 * is ignored and logged as an `ignored` grant event, never re-granted.
	 *
	 * @group CORE-07
	 * @group GRANT-007
	 * @group GRANT-025
	 */
	public function test_CORE_07_refund_before_late_payment_never_regrants(): void {
		$reconciler = GrantReconciler::current();
		$source     = new FakeSource();

		// Order A: the refund is the first state the core sees; the late payment
		// callback triggers a reconcile, which reads the current source state
		// (state-based adapter, R0-40 §4.1), not the payload of the late event.
		$source->put( $this->snapshot( 'order-c07a', 'refunded', 2 ) );
		self::assertSame( 0, $reconciler->reconcile( $source, $source, 'order-c07a', 'evt-refund' )['created'] );
		self::assertSame( 0, $reconciler->reconcile( $source, $source, 'order-c07a', 'evt-late-paid' )['created'] );
		self::assertSame( array(), self::grants_of( 'order-c07a' ), 'no grant from a late payment after the refund' );

		// Order B: paid → refunded → late paid with an older and a newer revision.
		$source->put( $this->snapshot( 'order-c07b', 'paid', 1 ) );
		$reconciler->reconcile( $source, $source, 'order-c07b' );
		$source->put( $this->snapshot( 'order-c07b', 'refunded', 3 ) );
		self::assertSame( 1, $reconciler->reconcile( $source, $source, 'order-c07b' )['changed'] );
		$source->put( $this->snapshot( 'order-c07b', 'paid', 2 ) );
		self::assertTrue( $reconciler->reconcile( $source, $source, 'order-c07b' )['stale'] );
		$source->put( $this->snapshot( 'order-c07b', 'paid', 4 ) );
		self::assertSame( 1, $reconciler->reconcile( $source, $source, 'order-c07b' )['ignored'] );
		self::assertSame( array( '1' => GrantTransitions::REVOKED ), self::grants_of( 'order-c07b' ) );
		self::assertSame( array( 'created', 'status_changed', 'ignored' ), array_column( self::events_of( 'order-c07b' ), 'event_type' ) );
		self::assertNotSame( 'ok', self::access( $this->learner, $this->courses[0] ) );
	}

	/**
	 * CORE-12 (R1 core, GRANT-021): the source is switched off (no snapshot)
	 * and later back. No revocation while it is gone, no grant event; back
	 * with the known revision it is stale, a newer revision with the same
	 * state creates no duplicate and no event.
	 *
	 * @group CORE-12
	 * @group GRANT-021
	 */
	public function test_CORE_12_source_off_and_back_without_mass_revocation_or_duplicates(): void {
		$reconciler = GrantReconciler::current();
		$source     = new FakeSource();
		foreach ( array( 'order-c12a', 'order-c12b' ) as $reference ) {
			$source->put( $this->snapshot( $reference, 'paid', 1 ) );
			$reconciler->reconcile( $source, $source, $reference );
		}
		$events = self::rows_in( 'ccap_grant_event' );
		$outbox = self::rows_in( 'ccap_outbox_event' );

		// Connector off: the adapter delivers nothing.
		$off = new FakeSource();
		foreach ( array( 'order-c12a', 'order-c12b' ) as $reference ) {
			self::assertTrue( $reconciler->reconcile( $off, $off, $reference )['no_change'] );
			self::assertSame( array( '1' => GrantTransitions::ACTIVE ), self::grants_of( $reference ) );
		}
		self::assertSame( $events, self::rows_in( 'ccap_grant_event' ), 'no silent mass revocation' );

		// Connector back: same revision stale, newer revision with the same state no duplicate.
		foreach ( array( 'order-c12a', 'order-c12b' ) as $reference ) {
			self::assertTrue( $reconciler->reconcile( $source, $source, $reference )['stale'] );
			$source->put( $this->snapshot( $reference, 'paid', 2 ) );
			$again = $reconciler->reconcile( $source, $source, $reference );
			self::assertSame( array( 0, 0 ), array( $again['created'], $again['changed'] ) );
		}
		self::assertSame( 2, self::rows_in( 'ccap_grant', "source_type = 'fake'" ) );
		self::assertSame( $events, self::rows_in( 'ccap_grant_event' ) );
		self::assertSame( $outbox, self::rows_in( 'ccap_outbox_event' ) );
		self::assertSame( 'ok', self::access( $this->learner, $this->courses[0] ) );
	}
}
