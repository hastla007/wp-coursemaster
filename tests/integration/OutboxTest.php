<?php
/**
 * R1-43: internal outbox delivery and grant source reconciliation.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\FakeSource;
use CourseCaptain\Access\GrantReconciler;
use CourseCaptain\Access\GrantService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Event\PermanentDeliveryError;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;
use RuntimeException;

/**
 * Real tables; R0-19 §5.4/§5.8.
 *
 * @group R1-43
 */
final class OutboxTest extends SchemaTestCase {

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
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'C',
				'post_author' => $this->admin,
			)
		);
		PolicyService::flush();
	}

	/**
	 * Delivery statuses.
	 *
	 * @return array<string, int>
	 */
	private static function statuses(): array {
		$db   = Database::current();
		$rows = $db->rows( 'SELECT status, COUNT(*) AS n FROM `' . $db->table( 'ccap_outbox_delivery' ) . '` GROUP BY status' );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[ (string) $row['status'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Create one manual grant (3 events: created, activated, access.changed).
	 */
	private function grant(): int {
		return GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->learner, $this->course, wp_generate_uuid4(), 'x' );
	}

	/**
	 * GRANT-002 (outbox part) and delivery: one delivery per internal subscriber, delivered by the dispatcher.
	 *
	 * @group GRANT-002
	 */
	public function test_delivery(): void {
		$this->grant();
		self::assertSame( array( 'pending' => 3 ), self::statuses() );
		self::assertNotFalse( wp_next_scheduled( \CourseCaptain\Kernel\CronHooks::OUTBOX_DISPATCH ), 'dispatch scheduled soon' );
		$stats = OutboxDispatcher::current()->dispatch();
		self::assertSame( 3, $stats['delivered'] );
		self::assertSame( array( 'delivered' => 3 ), self::statuses() );
		self::assertSame( 0, OutboxDispatcher::current()->dispatch()['delivered'], 'at least once, not twice' );
	}

	/**
	 * Backoff, dead-letter after 8 attempts, permanent errors.
	 */
	public function test_retry_dead_letter_and_permanent_errors(): void {
		$fail = static fn(): array => array(
			'cache' => static function (): void {
				throw new RuntimeException( 'temporary' );
			},
		);
		add_filter( 'course_captain_internal_subscriber_handlers', $fail );
		$this->grant();
		$stats = OutboxDispatcher::current()->dispatch();
		self::assertSame( 3, $stats['retried'] );
		$db    = Database::current();
		$table = $db->table( 'ccap_outbox_delivery' );
		$row   = $db->row( "SELECT attempts, next_attempt_at, last_error_class FROM `{$table}` ORDER BY id LIMIT 1" );
		self::assertSame( '1', (string) $row['attempts'] );
		self::assertGreaterThan( $db->now( 50 ), (string) $row['next_attempt_at'], 'backoff 1 min' );
		self::assertSame( 0, OutboxDispatcher::current()->dispatch()['retried'], 'not due yet' );

		$db->execute( "UPDATE `{$table}` SET attempts = 7, next_attempt_at = NULL" );
		$dead = 0;
		add_action(
			'course_captain_outbox_dead_lettered',
			static function () use ( &$dead ): void {
				++$dead;
			}
		);
		self::assertSame( 3, OutboxDispatcher::current()->dispatch()['dead'] );
		self::assertSame( 3, $dead );
		remove_filter( 'course_captain_internal_subscriber_handlers', $fail );

		$permanent = static fn(): array => array(
			'cache' => static function (): void {
				throw new PermanentDeliveryError( 'invalid' );
			},
		);
		add_filter( 'course_captain_internal_subscriber_handlers', $permanent );
		$this->grant();
		// Second grant for the same learner: access does not change, so no access.changed event.
		self::assertSame( 2, OutboxDispatcher::current()->dispatch()['failed'] );
		remove_filter( 'course_captain_internal_subscriber_handlers', $permanent );
	}

	/**
	 * Expired leases are taken over (crash of a dispatcher).
	 */
	public function test_expired_lease_is_reclaimed(): void {
		$this->grant();
		$db    = Database::current();
		$table = $db->table( 'ccap_outbox_delivery' );
		$db->execute( $db->prepare( "UPDATE `{$table}` SET status = 'dispatching', lock_owner = 'dead-process', locked_until = %s", $db->now( -1 ) ) );
		self::assertSame( 3, OutboxDispatcher::current()->dispatch()['delivered'] );
	}

	/**
	 * Retention (E-258, E-116) and replay with the same event ID (REC-016, E-311).
	 *
	 * @group REC-016
	 */
	public function test_retention_and_replay(): void {
		$this->grant();
		$db         = Database::current();
		$deliveries = $db->table( 'ccap_outbox_delivery' );
		$events     = $db->table( 'ccap_outbox_event' );
		$db->execute( $db->prepare( "UPDATE `{$deliveries}` SET created_at = %s", $db->now( -31 * DAY_IN_SECONDS ) ) );
		self::assertSame( 3, OutboxDispatcher::current()->purge()['dead'] );
		self::assertSame( array( 'dead' => 3 ), self::statuses() );

		$uuid = (string) $db->scalar( "SELECT event_uuid FROM `{$events}` WHERE event_type = 'grant.created'" );
		try {
			OutboxDispatcher::current()->replay( Actor::user( $this->learner ), $uuid );
			self::fail( 'learner replay' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_forbidden', $e->error_code );
		}
		self::assertSame( 1, OutboxDispatcher::current()->replay( Actor::user( $this->admin ), $uuid ) );
		OutboxDispatcher::current()->dispatch();
		self::assertSame( $uuid, (string) $db->scalar( "SELECT event_uuid FROM `{$deliveries}` WHERE status = 'delivered'" ), 'same event ID' );

		self::assertSame( 0, OutboxDispatcher::current()->replay( Actor::user( $this->admin ), $uuid ), 'delivered only with flag' );
		try {
			OutboxDispatcher::current()->replay( Actor::user( $this->admin ), $uuid, true );
			self::fail( 'reason required' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_reason_required', $e->error_code );
		}
		self::assertSame( 1, OutboxDispatcher::current()->replay( Actor::user( $this->admin ), $uuid, true, 'Support ticket' ) );

		$db->execute( "UPDATE `{$deliveries}` SET status = 'delivered'" );
		$db->execute( $db->prepare( "UPDATE `{$events}` SET created_at = %s", $db->now( -91 * DAY_IN_SECONDS ) ) );
		self::assertSame( 3, OutboxDispatcher::current()->purge()['purged'] );
		self::assertSame( array(), self::statuses(), 'deliveries cascade' );
	}

	/**
	 * GRANT-004/007/021 with the FakeSource: idempotent, no revival after
	 * refund, stale revisions ignored, unknown status changes nothing.
	 *
	 * @group GRANT-004
	 * @group GRANT-007
	 * @group GRANT-021
	 */
	public function test_reconciler_with_fake_source(): void {
		$source     = new FakeSource();
		$reconciler = GrantReconciler::current();
		$line       = array(
			array(
				'line'    => '1',
				'targets' => array( array( 'course_id' => $this->course ) ),
			),
		);
		$put        = function ( string $status, int $revision ) use ( $source, $line ): void {
			$source->put(
				array(
					'reference' => 'order-7',
					'status'    => $status,
					'revision'  => $revision,
					'user_id'   => $this->learner,
					'lines'     => $line,
				)
			);
		};
		$put( 'paid', 1 );
		self::assertSame( 1, $reconciler->reconcile( $source, $source, 'order-7' )['created'] );
		for ( $i = 0; $i < 9; $i++ ) {
			self::assertTrue( $reconciler->reconcile( $source, $source, 'order-7' )['stale'] );
		}
		$db = Database::current();
		self::assertSame( '1', $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_grant' ) . '`' ) );

		$put( 'refunded', 2 );
		self::assertSame( 1, $reconciler->reconcile( $source, $source, 'order-7' )['changed'] );
		$put( 'paid', 3 );
		self::assertSame( 1, $reconciler->reconcile( $source, $source, 'order-7' )['ignored'], 'late paid after refund' );
		self::assertSame( 'revoked', $db->scalar( 'SELECT status FROM `' . $db->table( 'ccap_grant' ) . '`' ) );

		$put( 'paid', 2 );
		self::assertTrue( $reconciler->reconcile( $source, $source, 'order-7' )['stale'], 'older revision' );
		$put( 'mystery_status', 9 );
		self::assertTrue( $reconciler->reconcile( $source, $source, 'order-7' )['no_change'] );
		self::assertTrue( $reconciler->reconcile( $source, $source, 'missing' )['no_change'], 'source gone: grants unchanged' );
	}
}
