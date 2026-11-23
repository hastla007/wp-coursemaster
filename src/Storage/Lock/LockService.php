<?php
/**
 * Mutex and lease locks.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Lock;

use CourseCaptain\Storage\Clock\Clock;
use CourseCaptain\Storage\Database\Connection;
use CourseCaptain\Storage\Database\DatabaseError;

defined( 'ABSPATH' ) || exit;

/**
 * The single lock infrastructure of R0-21 §2.1:
 *
 * - **Mutex**: `GET_LOCK` on the current connection, bound to it, for short
 *   critical sections. `NULL` (not available, e.g. ProxySQL/Galera) lets the
 *   operation continue on its DB guard, is counted and reported (§2.5, D21-2).
 * - **Lease**: row in `ccap_lease` with owner, fencing token, heartbeat and
 *   expiry for work that spans requests (§2.4, D21-1).
 *
 * Lock order M0 → M6 and "no mutex inside an open transaction" are enforced:
 * a violation throws {@see LockOrderViolation} in debug/test runs and is
 * counted otherwise (CON-007).
 */
final class LockService {

	public const MUTEX_ACQUIRED    = 'acquired';
	public const MUTEX_BUSY        = 'busy';
	public const MUTEX_UNAVAILABLE = 'unavailable';

	/** Name of the migration mutex and lease (E-300). */
	public const MIGRATION = 'schema-migration';

	/** Option with health counters (named_lock_unavailable, lock_order_violation). */
	public const HEALTH_OPTION = 'course_captain_lock_health';

	/**
	 * Held mutexes: name => [rank, depth, real].
	 *
	 * @var array<string, array{rank: int, depth: int, real: bool}>
	 */
	private array $held = array();

	/**
	 * Counters of this request.
	 *
	 * @var array{named_lock_unavailable: int, lock_order_violation: int}
	 */
	private array $counters = array(
		'named_lock_unavailable' => 0,
		'lock_order_violation'   => 0,
	);

	/**
	 * Receives counter increments for persistence (Site Health).
	 *
	 * @var callable(string): void
	 */
	private $health_sink;

	/**
	 * Throw on order violations.
	 *
	 * @var bool
	 */
	private bool $strict;

	/**
	 * Constructor.
	 *
	 * @param Connection                 $db          Connection the mutexes are bound to.
	 * @param Clock                      $clock       UTC clock (lease times come from PHP, R0-21 R-8).
	 * @param bool|null                  $strict      Throw on order violations; default: WP_DEBUG or test run.
	 * @param (callable(string): void)|null $health_sink Counter persistence; default: option `course_captain_lock_health`.
	 */
	public function __construct(
		private Connection $db,
		private Clock $clock,
		?bool $strict = null,
		?callable $health_sink = null,
	) {
		$this->strict      = $strict ?? ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || self::testing() );
		$this->health_sink = $health_sink ?? array( self::class, 'persist_counter' );
	}

	/**
	 * Server-side name of a mutex, scoped to database and site prefix.
	 *
	 * @param string $name Logical name.
	 * @return string At most 64 characters.
	 */
	public function mutex_name( string $name ): string {
		$scope = ( defined( 'DB_NAME' ) ? (string) DB_NAME : '' ) . '|' . $this->db->prefix() . '|' . $name;
		if ( self::MIGRATION === $name ) {
			// R0-37 §3.1 L-A.
			return 'ccap_mig_' . substr( md5( $scope ), 0, 16 );
		}
		return 'ccap_' . substr( hash( 'sha256', $scope ), 0, 40 );
	}

	/**
	 * Acquire a mutex.
	 *
	 * @param string $name         Logical name.
	 * @param int    $rank         Rank of {@see LockRank}.
	 * @param int    $wait_seconds Wait time (0 = do not wait).
	 * @return string One of the MUTEX_* constants.
	 */
	public function mutex( string $name, int $rank, int $wait_seconds = 0 ): string {
		if ( isset( $this->held[ $name ] ) ) {
			++$this->held[ $name ]['depth'];
			return $this->held[ $name ]['real'] ? self::MUTEX_ACQUIRED : self::MUTEX_UNAVAILABLE;
		}

		if ( $this->db->in_transaction() ) {
			$this->violation( 'Mutex "' . $name . '" requested inside an open transaction.' );
		}
		$highest = $this->highest_rank();
		if ( null !== $highest && $rank < $highest ) {
			$this->violation( 'Mutex "' . $name . '" (rank ' . $rank . ') requested after rank ' . $highest . '.' );
		}

		$result = null;
		if ( ! $this->forced_unavailable( $name ) ) {
			try {
				$result = $this->db->get_var( $this->db->prepare( 'SELECT GET_LOCK(%s, %d)', $this->mutex_name( $name ), max( 0, $wait_seconds ) ) );
			} catch ( DatabaseError $e ) {
				$result = null;
			}
		}

		if ( '0' === $result ) {
			return self::MUTEX_BUSY;
		}
		$real                = '1' === $result;
		$this->held[ $name ] = array(
			'rank'  => $rank,
			'depth' => 1,
			'real'  => $real,
		);
		if ( ! $real ) {
			$this->count( 'named_lock_unavailable' );
			return self::MUTEX_UNAVAILABLE;
		}
		return self::MUTEX_ACQUIRED;
	}

	/**
	 * Release a mutex (re-entrant: only the last release frees it).
	 *
	 * @param string $name Logical name.
	 * @return void
	 */
	public function release_mutex( string $name ): void {
		if ( ! isset( $this->held[ $name ] ) ) {
			return;
		}
		if ( --$this->held[ $name ]['depth'] > 0 ) {
			return;
		}
		$real = $this->held[ $name ]['real'];
		unset( $this->held[ $name ] );
		if ( $real ) {
			try {
				$this->db->get_var( $this->db->prepare( 'SELECT RELEASE_LOCK(%s)', $this->mutex_name( $name ) ) );
			} catch ( DatabaseError $e ) {
				// The lock dies with the connection anyway.
				unset( $e );
			}
		}
	}

	/**
	 * Whether this connection still holds a real mutex (lost after a reconnect, RB-05).
	 *
	 * @param string $name Logical name.
	 * @return bool
	 */
	public function holds_mutex( string $name ): bool {
		try {
			return '1' === $this->db->get_var( $this->db->prepare( 'SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', $this->mutex_name( $name ) ) );
		} catch ( DatabaseError $e ) {
			return false;
		}
	}

	/**
	 * Acquire a lease.
	 *
	 * Takeover happens when the lease is expired, or immediately when
	 * `$take_over_live` is set (the caller holds the real mutex, so the
	 * recorded owner must be dead, R0-37 §3.1 step 2).
	 *
	 * @param string      $name           Lease name.
	 * @param int         $ttl            Seconds until expiry.
	 * @param string|null $owner          Owner id; random when omitted.
	 * @param bool        $take_over_live Take over a lease that has not expired yet.
	 * @return Lease|null Null when another live owner holds it.
	 */
	public function acquire_lease( string $name, int $ttl, ?string $owner = null, bool $take_over_live = false ): ?Lease {
		$owner   = $owner ?? self::new_owner();
		$table   = $this->db->table( 'lease' );
		$now     = $this->clock->mysql();
		$expires = $this->clock->mysql( $ttl );

		$inserted = $this->db->insert(
			$table,
			array(
				'name'         => $name,
				'owner'        => $owner,
				'fence'        => 1,
				'expires_at'   => $expires,
				'heartbeat_at' => $now,
				'created_at'   => $now,
			),
			true
		);
		if ( 1 === $inserted ) {
			return new Lease( $name, $owner, 1 );
		}

		$row = $this->lease_row( $name );
		if ( null === $row ) {
			// Released between both statements: try once more.
			return 1 === $this->db->insert(
				$table,
				array(
					'name'         => $name,
					'owner'        => $owner,
					'fence'        => 1,
					'expires_at'   => $expires,
					'heartbeat_at' => $now,
					'created_at'   => $now,
				),
				true
			) ? new Lease( $name, $owner, 1 ) : null;
		}

		$expired = (string) $row['expires_at'] < $now;
		if ( (string) $row['owner'] === $owner || ( ! $expired && ! $take_over_live ) ) {
			return null;
		}

		$fence   = (int) $row['fence'];
		$changed = $this->db->execute(
			$this->db->prepare(
				'UPDATE %i SET owner = %s, fence = fence + 1, expires_at = %s, heartbeat_at = %s WHERE name = %s AND owner = %s AND fence = %d',
				$table,
				$owner,
				$expires,
				$now,
				$name,
				(string) $row['owner'],
				$fence
			)
		);
		return 1 === $changed ? new Lease( $name, $owner, $fence + 1, (string) $row['owner'] ) : null;
	}

	/**
	 * Fenced heartbeat; run it as first statement of every step transaction (R0-21 §2.4).
	 *
	 * @param Lease $lease Held lease.
	 * @param int   $ttl   New time to live.
	 * @return bool False when another owner took over (fenced out).
	 */
	public function heartbeat( Lease $lease, int $ttl ): bool {
		$changed = $this->db->execute(
			$this->db->prepare(
				'UPDATE %i SET heartbeat_at = %s, expires_at = %s WHERE name = %s AND owner = %s AND fence = %d',
				$this->db->table( 'lease' ),
				$this->clock->mysql(),
				$this->clock->mysql( $ttl ),
				$lease->name,
				$lease->owner,
				$lease->fence
			)
		);
		return 1 === $changed;
	}

	/**
	 * Release a lease (only by its owner and fence).
	 *
	 * @param Lease $lease Held lease.
	 * @return void
	 */
	public function release_lease( Lease $lease ): void {
		$this->db->execute(
			$this->db->prepare(
				'DELETE FROM %i WHERE name = %s AND owner = %s AND fence = %d',
				$this->db->table( 'lease' ),
				$lease->name,
				$lease->owner,
				$lease->fence
			)
		);
	}

	/**
	 * Current row of a lease.
	 *
	 * @param string $name Lease name.
	 * @return array<string, string|null>|null
	 */
	public function lease_row( string $name ): ?array {
		return $this->db->get_row( $this->db->prepare( 'SELECT * FROM %i WHERE name = %s', $this->db->table( 'lease' ), $name ) );
	}

	/**
	 * Counters of this request.
	 *
	 * @return array{named_lock_unavailable: int, lock_order_violation: int}
	 */
	public function counters(): array {
		return $this->counters;
	}

	/**
	 * Random owner id.
	 *
	 * @return string
	 */
	public static function new_owner(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Default health sink: count into the option (once per counter and request).
	 *
	 * @param string $counter Counter name.
	 * @return void
	 */
	public static function persist_counter( string $counter ): void {
		$health = get_option( self::HEALTH_OPTION, array() );
		$health = is_array( $health ) ? $health : array();

		$health[ $counter ]            = (int) ( $health[ $counter ] ?? 0 ) + 1;
		$health[ $counter . '_last' ] = gmdate( Clock::MYSQL_FORMAT );
		update_option( self::HEALTH_OPTION, $health, false );
	}

	/**
	 * Whether this is a test run (fault hooks are only honoured then).
	 *
	 * @return bool
	 */
	public static function testing(): bool {
		return defined( 'COURSE_CAPTAIN_TESTING' ) && true === COURSE_CAPTAIN_TESTING;
	}

	/**
	 * Highest rank currently held.
	 *
	 * @return int|null
	 */
	private function highest_rank(): ?int {
		$highest = null;
		foreach ( $this->held as $lock ) {
			$highest = null === $highest ? $lock['rank'] : max( $highest, $lock['rank'] );
		}
		return $highest;
	}

	/**
	 * Test-only switch that simulates `GET_LOCK = NULL` (R0-21 §2.5, CON-006).
	 *
	 * @param string $name Logical name.
	 * @return bool
	 */
	private function forced_unavailable( string $name ): bool {
		if ( ! self::testing() || ! function_exists( 'apply_filters' ) ) {
			return false;
		}
		return true === apply_filters( 'course_captain_lock_force_unavailable', false, $name );
	}

	/**
	 * Report an order violation.
	 *
	 * @param string $message Description.
	 * @return void
	 * @throws LockOrderViolation In strict mode.
	 */
	private function violation( string $message ): void {
		$this->count( 'lock_order_violation' );
		if ( $this->strict ) {
			throw new LockOrderViolation( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal message, never rendered.
		}
	}

	/**
	 * Increment a counter; persist the first occurrence per request.
	 *
	 * @param string $counter Counter name.
	 * @return void
	 */
	private function count( string $counter ): void {
		if ( ! isset( $this->counters[ $counter ] ) ) {
			return;
		}
		++$this->counters[ $counter ];
		if ( 1 === $this->counters[ $counter ] ) {
			try {
				( $this->health_sink )( $counter );
			} catch ( DatabaseError $e ) {
				unset( $e );
			}
		}
	}
}
