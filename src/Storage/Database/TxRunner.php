<?php
/**
 * Transaction helper with deadlock retry.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Database;

use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Runs write operations in one transaction (R0-21 §2.6):
 *
 * - T-1: any failed statement aborts the operation and rolls back.
 * - T-2: deadlock (1213) repeats the whole operation, at most
 *   {@see TxPolicy::$max_retries} times with jitter, then {@see DbBusy}.
 * - T-3: lock wait timeout (1205) sends an explicit ROLLBACK and throws
 *   {@see DbBusy} without a server-side retry.
 * - T-4: nested calls join the outer transaction; only the outer call retries.
 * - T-5: after-commit callbacks run only after a successful commit.
 * - T-6: deadlocks, lock waits and retries are counted.
 *
 * The operation must not have side effects outside the database before the
 * commit (R0-21 R-6); use {@see Connection::after_commit()} for those.
 */
final class TxRunner {

	/**
	 * Telemetry counters.
	 *
	 * @var array{deadlocks: int, lock_waits: int, retries: int, busy: int}
	 */
	private array $stats = array(
		'deadlocks'  => 0,
		'lock_waits' => 0,
		'retries'    => 0,
		'busy'       => 0,
	);

	/**
	 * Waits between retries (milliseconds); replaceable in tests.
	 *
	 * @var callable(int): void
	 */
	private $sleeper;

	/**
	 * Constructor.
	 *
	 * @param Connection               $db      Connection.
	 * @param (callable(int): void)|null $sleeper Wait function in milliseconds.
	 */
	public function __construct( private Connection $db, ?callable $sleeper = null ) {
		$this->sleeper = $sleeper ?? static function ( int $ms ): void {
			usleep( $ms * 1000 );
		};
	}

	/**
	 * Connection used by this runner.
	 *
	 * @return Connection
	 */
	public function connection(): Connection {
		return $this->db;
	}

	/**
	 * Run an operation in a transaction.
	 *
	 * @template T
	 * @param callable(Connection): T $operation Operation; may be called several times.
	 * @param TxPolicy|null           $policy    Policy (default: interactive).
	 * @return T
	 * @throws DbBusy When deadlock retries are exhausted or a lock wait timed out.
	 * @throws Throwable Any other error of the operation, after rollback.
	 */
	public function run( callable $operation, ?TxPolicy $policy = null ): mixed {
		if ( $this->db->in_transaction() ) {
			// T-4: flat nesting; errors propagate to the outer run().
			$this->db->begin();
			try {
				$result = $operation( $this->db );
			} catch ( Throwable $e ) {
				$this->db->rollback();
				throw $e;
			}
			$this->db->commit();
			return $result;
		}

		$policy  = $policy ?? TxPolicy::interactive();
		$attempt = 0;
		while ( true ) {
			try {
				$this->db->begin( $policy->lock_wait_timeout );
				$result = $operation( $this->db );
				$this->db->commit();
				return $result;
			} catch ( DatabaseError $e ) {
				$this->safe_rollback();
				if ( $e instanceof DbBusy ) {
					throw $e;
				}
				if ( $e->is_deadlock() ) {
					++$this->stats['deadlocks'];
					if ( $attempt < $policy->max_retries ) {
						++$attempt;
						++$this->stats['retries'];
						( $this->sleeper )( random_int( $policy->jitter_min_ms, max( $policy->jitter_min_ms, $policy->jitter_max_ms ) ) );
						continue;
					}
					++$this->stats['busy'];
					throw new DbBusy( DbBusy::REASON_DEADLOCK, $e );
				}
				if ( $e->is_lock_wait_timeout() ) {
					++$this->stats['lock_waits'];
					++$this->stats['busy'];
					throw new DbBusy( DbBusy::REASON_LOCK_WAIT, $e );
				}
				throw $e;
			} catch ( Throwable $e ) {
				$this->safe_rollback();
				throw $e;
			}
		}
	}

	/**
	 * Telemetry counters of this runner (T-6).
	 *
	 * @return array{deadlocks: int, lock_waits: int, retries: int, busy: int}
	 */
	public function stats(): array {
		return $this->stats;
	}

	/**
	 * Roll back; a failing ROLLBACK (lost connection) must not hide the original error.
	 *
	 * @return void
	 */
	private function safe_rollback(): void {
		try {
			$this->db->rollback();
		} catch ( DatabaseError $ignored ) {
			unset( $ignored );
		}
	}
}
