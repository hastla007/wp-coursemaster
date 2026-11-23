<?php
/**
 * Konzept Kap. 28 (TODO Z. 704): queue retry along the full backoff ladder,
 * dead-letter after the last attempt and recovery without double delivery.
 *
 * Complements OutboxTest (first backoff step, dead-letter, lease takeover).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;
use RuntimeException;

/**
 * Real tables; R0-19 §5.4.
 *
 * @group R1-43
 * @group K28-704
 */
final class OutboxBackoffLadderTest extends SchemaTestCase {

	/**
	 * Backoff in seconds after failed attempt 1…7 (R0-19 §5.4: 1 min … 24 h).
	 */
	private const LADDER = array( 60, 300, 900, 3600, 21600, 86400, 86400 );

	/**
	 * The `cache` handler fails while true.
	 *
	 * @var bool
	 */
	private bool $failing = true;

	/**
	 * Admin, learner, course.
	 *
	 * @var list<int>
	 */
	private array $ids = array();

	/**
	 * Setup with a `cache` subscriber that fails on demand.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$admin     = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$learner   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$course    = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'C',
				'post_author' => $admin,
			)
		);
		$this->ids = array( $admin, $learner, $course );
		PolicyService::flush();
		add_filter( 'course_captain_internal_subscriber_handlers', array( $this, 'handlers' ) );
	}

	/**
	 * Remove the filter.
	 */
	public function tear_down(): void {
		remove_filter( 'course_captain_internal_subscriber_handlers', array( $this, 'handlers' ) );
		parent::tear_down();
	}

	/**
	 * Handler override: `cache` fails while `$failing`, `notification` always succeeds.
	 *
	 * @param array<string, callable> $handlers Handlers.
	 * @return array<string, callable>
	 */
	public function handlers( array $handlers ): array {
		$handlers['cache']        = function (): void {
			if ( $this->failing ) {
				throw new RuntimeException( 'temporary' );
			}
		};
		$handlers['notification'] = static function (): void {};
		return $handlers;
	}

	/**
	 * One manual grant (3 `cache` deliveries, 1 `notification` delivery).
	 */
	private function grant(): void {
		GrantService::current()->grant_manual( Actor::user( $this->ids[0] ), $this->ids[1], $this->ids[2], wp_generate_uuid4(), 'x' );
	}

	/**
	 * `cache` delivery rows.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function cache_rows(): array {
		$db = Database::current();
		return array_map(
			static fn( $row ): array => (array) $row,
			$db->rows( 'SELECT id, status, attempts, next_attempt_at, last_error_class FROM `' . $db->table( 'ccap_outbox_delivery' ) . "` WHERE subscriber_id = 'cache' ORDER BY id" )
		);
	}

	/**
	 * Make every pending delivery due now.
	 */
	private static function make_due(): void {
		$db = Database::current();
		$db->execute( 'UPDATE `' . $db->table( 'ccap_outbox_delivery' ) . "` SET next_attempt_at = NULL WHERE status = 'pending'" );
	}

	/**
	 * Failed attempts 1…7 each schedule the next try on the ladder, nothing is
	 * dispatched before it is due, attempt 8 dead-letters every delivery once
	 * and dead letters are never retried automatically.
	 */
	public function test_backoff_ladder_then_dead_letter(): void {
		$this->grant();
		$dispatcher = OutboxDispatcher::current();
		$db         = Database::current();
		$first      = $dispatcher->dispatch();
		self::assertSame( 1, $first['delivered'], 'notification delivered' );
		self::assertSame( 3, $first['retried'], 'cache attempt 1 failed' );

		foreach ( self::LADDER as $index => $seconds ) {
			$attempt = $index + 1;
			if ( $attempt > 1 ) {
				self::assertSame( 3, $dispatcher->dispatch()['retried'], "attempt {$attempt} failed and was rescheduled" );
			}
			$now = $db->time();
			foreach ( self::cache_rows() as $row ) {
				self::assertSame( 'pending', $row['status'] );
				self::assertSame( $attempt, (int) $row['attempts'] );
				$delay = (int) strtotime( $row['next_attempt_at'] . ' UTC' ) - $now;
				self::assertGreaterThanOrEqual( $seconds - 5, $delay, "backoff after attempt {$attempt}" );
				self::assertLessThanOrEqual( $seconds + 5, $delay, "backoff after attempt {$attempt}" );
				self::assertNotEmpty( $row['last_error_class'] );
			}
			self::assertSame( 0, $dispatcher->dispatch()['retried'], "not dispatched before the backoff after attempt {$attempt}" );
			self::make_due();
		}

		$dead = array();
		add_action(
			'course_captain_outbox_dead_lettered',
			static function ( $id ) use ( &$dead ): void {
				$dead[] = (string) $id;
			}
		);
		self::assertSame( 3, $dispatcher->dispatch()['dead'], 'attempt 8 dead-letters' );
		$rows = self::cache_rows();
		sort( $dead ); // Dispatch order follows the event object, not the delivery ID.
		self::assertSame( array_map( 'strval', array_column( $rows, 'id' ) ), $dead, 'one dead-letter signal per delivery' );
		foreach ( $rows as $row ) {
			self::assertSame( 'dead', $row['status'] );
			self::assertSame( OutboxDispatcher::MAX_ATTEMPTS, (int) $row['attempts'] );
		}
		self::make_due();
		$again = $dispatcher->dispatch();
		self::assertSame( 0, $again['retried'] + $again['dead'] + $again['delivered'], 'dead letters stay put' );
	}

	/**
	 * Once the cause is gone, the next due dispatch delivers exactly once.
	 */
	public function test_recovery_delivers_once(): void {
		$this->grant();
		$dispatcher = OutboxDispatcher::current();
		self::assertSame( 3, $dispatcher->dispatch()['retried'] );
		$this->failing = false;
		self::assertSame( 0, $dispatcher->dispatch()['delivered'], 'still waiting for the backoff' );
		self::make_due();
		self::assertSame( 3, $dispatcher->dispatch()['delivered'] );
		foreach ( self::cache_rows() as $row ) {
			self::assertSame( 'delivered', $row['status'] );
			self::assertSame( 1, (int) $row['attempts'], 'only the failed try is counted' );
		}
		self::make_due();
		self::assertSame( 0, $dispatcher->dispatch()['delivered'], 'no second delivery' );
	}
}
