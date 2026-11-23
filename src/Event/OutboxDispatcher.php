<?php
/**
 * Delivery of internal outbox events (R0-19 §5.4, E-116, E-215, E-258, E-311).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Event;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * At-least-once per subscriber: claim with a 60 s lease, deliver in event
 * order, backoff 1 min … 24 h, dead-letter after 8 attempts; permanent
 * errors go straight to `failed`. Delivered events are kept 90 days, open
 * deliveries at most 30 days (`dead`, class `retention`).
 */
final class OutboxDispatcher {

	public const LEASE_SECONDS  = 60;
	public const BATCH          = 100;
	public const MAX_ATTEMPTS   = 8;
	public const DELIVERED_DAYS = 90;
	public const OPEN_DAYS      = 30;
	private const BACKOFF       = array( 60, 300, 900, 3600, 21600, 86400, 86400, 86400 );

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current() );
	}

	/**
	 * Handlers of the internal subscribers: id => callable(array $envelope): void.
	 * A handler throws on failure; PermanentDeliveryError means `failed` at once.
	 *
	 * @return array<string, callable>
	 */
	public static function handlers(): array {
		$defaults = array(
			// The core cache is invalidated synchronously after commit (R0-21 §3.7); this
			// subscriber only refreshes request-independent caches.
			'cache' => static function (): void {
				do_action( 'course_captain_policy_changed' );
			},
		);
		/**
		 * Handlers of internal outbox subscribers (Free modules only).
		 *
		 * @since 1.0.0
		 * @param array<string, callable> $defaults Subscriber id => handler.
		 */
		$filtered = apply_filters( 'course_captain_internal_subscriber_handlers', $defaults );
		return is_array( $filtered ) ? $filtered : $defaults;
	}

	/**
	 * One dispatch run (cron `ccap_outbox_dispatch`).
	 *
	 * @return array{delivered: int, retried: int, failed: int, dead: int, skipped: int}
	 */
	public function dispatch(): array {
		$stats = array(
			'delivered' => 0,
			'retried'   => 0,
			'failed'    => 0,
			'dead'      => 0,
			'skipped'   => 0,
		);
		$table = $this->db->table( 'ccap_outbox_delivery' );
		$owner = substr( str_replace( '-', '', $this->db->uuid() ), 0, 32 );
		$now   = $this->db->now();
		$this->db->execute(
			$this->db->prepare(
				"UPDATE `{$table}` SET status = 'dispatching', lock_owner = %s, locked_until = %s WHERE ((status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= %s)) OR (status = 'dispatching' AND locked_until < %s)) ORDER BY id LIMIT %d",
				$owner,
				$this->db->now( self::LEASE_SECONDS ),
				$now,
				$now,
				self::BATCH
			)
		);
		$events   = $this->db->table( 'ccap_outbox_event' );
		$rows     = $this->db->rows(
			$this->db->prepare( "SELECT d.id, d.subscriber_id, d.attempts, e.payload FROM `{$table}` d JOIN `{$events}` e ON e.event_uuid = d.event_uuid WHERE d.lock_owner = %s AND d.status = 'dispatching' ORDER BY e.object_type, e.object_id, e.object_version, d.id", $owner )
		);
		$handlers = self::handlers();
		$outbound = defined( 'COURSE_CAPTAIN_OUTBOUND' ) ? (string) constant( 'COURSE_CAPTAIN_OUTBOUND' ) : 'on';
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			if ( 'off' === $outbound ) {
				$this->finish( $id, $owner, 'skipped', array( 'skip_reason' => 'disabled' ) );
				++$stats['skipped'];
				continue;
			}
			$handler = $handlers[ (string) $row['subscriber_id'] ] ?? null;
			if ( null === $handler ) {
				$this->finish( $id, $owner, 'skipped', array( 'skip_reason' => 'disabled' ) );
				++$stats['skipped'];
				continue;
			}
			try {
				$envelope = json_decode( (string) $row['payload'], true );
				$handler( is_array( $envelope ) ? $envelope : array() );
				$this->finish( $id, $owner, 'delivered', array( 'delivered_at' => $this->db->now() ) );
				++$stats['delivered'];
			} catch ( PermanentDeliveryError $e ) {
				$this->finish( $id, $owner, 'failed', self::error_fields( $e, 'validation', (int) $row['attempts'] + 1 ) );
				++$stats['failed'];
			} catch ( Throwable $e ) {
				$attempts = (int) $row['attempts'] + 1;
				if ( $attempts >= self::MAX_ATTEMPTS ) {
					$this->finish( $id, $owner, 'dead', self::error_fields( $e, 'internal', $attempts ) );
					do_action( 'course_captain_outbox_dead_lettered', $id, (string) $row['subscriber_id'] );
					++$stats['dead'];
				} else {
					$this->finish( $id, $owner, 'pending', self::error_fields( $e, 'internal', $attempts ) + array( 'next_attempt_at' => $this->db->now( self::BACKOFF[ $attempts - 1 ] ) ) );
					++$stats['retried'];
				}
			}
		}
		return $stats;
	}

	/**
	 * Retention (E-116/E-215/E-258): open deliveries older than 30 days
	 * become `dead` (`retention`); fully delivered events older than 90 days
	 * are deleted together with their deliveries.
	 *
	 * @return array{dead: int, purged: int}
	 */
	public function purge(): array {
		$deliveries = $this->db->table( 'ccap_outbox_delivery' );
		$events     = $this->db->table( 'ccap_outbox_event' );
		$dead       = $this->db->execute(
			$this->db->prepare(
				"UPDATE `{$deliveries}` SET status = 'dead', last_error_class = 'retention', locked_until = NULL, lock_owner = NULL WHERE status IN ('pending', 'dispatching', 'failed') AND created_at < %s",
				$this->db->now( -self::OPEN_DAYS * DAY_IN_SECONDS )
			)
		);
		$purged     = $this->db->execute(
			$this->db->prepare(
				"DELETE e FROM `{$events}` e WHERE e.created_at < %s AND NOT EXISTS (SELECT 1 FROM `{$deliveries}` d WHERE d.event_uuid = e.event_uuid AND d.status NOT IN ('delivered', 'skipped', 'dead'))",
				$this->db->now( -self::DELIVERED_DAYS * DAY_IN_SECONDS )
			)
		);
		return array(
			'dead'   => $dead,
			'purged' => $purged,
		);
	}

	/**
	 * Replay with the same event ID (R0-19 §5.4 Nr. 7, E-311).
	 *
	 * @param Actor  $actor             Actor (`ccap_manage_integrations`).
	 * @param string $event_uuid        Event.
	 * @param bool   $include_delivered Also re-send delivered entries.
	 * @param string $reason            Mandatory when `$include_delivered`.
	 * @return int Reset deliveries.
	 * @throws CourseException On missing rights or reason.
	 */
	public function replay( Actor $actor, string $event_uuid, bool $include_delivered = false, string $reason = '' ): int {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_manage_integrations' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to replay events.', 403 );
		}
		if ( $include_delivered && '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_reason_required', 'A reason is required to re-send delivered events.', 422 );
		}
		$table    = $this->db->table( 'ccap_outbox_delivery' );
		$statuses = $include_delivered ? "'failed', 'dead', 'skipped', 'delivered'" : "'failed', 'dead', 'skipped'";
		$count    = $this->db->execute(
			$this->db->prepare(
				"UPDATE `{$table}` SET status = 'pending', attempts = 0, next_attempt_at = NULL, manual_retries = manual_retries + 1, locked_until = NULL, lock_owner = NULL WHERE event_uuid = %s AND status IN ({$statuses})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed status list.
				$event_uuid
			)
		);
		AuditLog::write(
			$this->db,
			$actor,
			'outbox.replayed',
			'outbox_event',
			$event_uuid,
			'' === $reason ? null : $reason,
			array(
				'include_delivered' => $include_delivered,
				'count'             => $count,
			)
		);
		return $count;
	}

	/**
	 * Schedule a near dispatch once (after new events).
	 *
	 * @return void
	 */
	public static function schedule_soon(): void {
		if ( function_exists( 'wp_next_scheduled' ) && false === wp_next_scheduled( \CourseCaptain\Kernel\CronHooks::OUTBOX_DISPATCH ) ) {
			wp_schedule_single_event( time() + 5, \CourseCaptain\Kernel\CronHooks::OUTBOX_DISPATCH );
		}
	}

	/**
	 * Close a claimed delivery (only with our lease).
	 *
	 * @param int                  $id     Delivery.
	 * @param string               $owner  Lease owner.
	 * @param string               $status New status.
	 * @param array<string, mixed> $fields Extra columns.
	 * @return void
	 */
	private function finish( int $id, string $owner, string $status, array $fields ): void {
		$sets   = array( 'status = %s', 'locked_until = NULL', 'lock_owner = NULL' );
		$values = array( $status );
		foreach ( $fields as $column => $value ) {
			Database::assert_identifier( $column );
			$sets[]   = "`{$column}` = " . ( is_int( $value ) ? '%d' : '%s' );
			$values[] = $value;
		}
		$values[] = $id;
		$values[] = $owner;
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_outbox_delivery' ) . '` SET ' . implode( ', ', $sets ) . ' WHERE id = %d AND lock_owner = %s', ...$values ) );
	}

	/**
	 * Error columns, shortened, without payload.
	 *
	 * @param Throwable $e        Error.
	 * @param string    $error_class Error class.
	 * @param int       $attempts Attempts.
	 * @return array<string, int|string>
	 */
	private static function error_fields( Throwable $e, string $error_class, int $attempts ): array {
		return array(
			'attempts'         => $attempts,
			'last_error_class' => $error_class,
			'last_error'       => substr( $e->getMessage(), 0, 300 ),
		);
	}
}
