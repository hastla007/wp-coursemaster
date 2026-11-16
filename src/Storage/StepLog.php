<?php
/**
 * Repository for ccap_migration_step (R0-37 §4.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Status per migration step: pending → running → done | failed.
 * Every status write is guarded by the lease (fencing).
 */
final class StepLog {

	public const PENDING = 'pending';
	public const RUNNING = 'running';
	public const FAILED  = 'failed';
	public const DONE    = 'done';

	/**
	 * `blocked_reason` of a step that automatic runs no longer try.
	 */
	public const POISON = 'poison';

	/**
	 * Aborted runs in the same step until it is poisoned (R0-37 §4.5).
	 */
	public const CRASH_LIMIT = 3;

	/**
	 * Failed attempts of the same step until it is poisoned (R0-37 §4.6).
	 */
	public const FAIL_LIMIT = 5;

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * One step row.
	 *
	 * @param string $step_id Step ID.
	 * @return array<string, mixed>|null
	 */
	public function get( string $step_id ): ?array {
		$table = $this->db->table( 'ccap_migration_step' );
		return $this->db->row( $this->db->prepare( "SELECT * FROM `{$table}` WHERE step_id = %s", $step_id ) );
	}

	/**
	 * All step rows keyed by ID.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$table  = $this->db->table( 'ccap_migration_step' );
		$result = array();
		foreach ( $this->db->rows( "SELECT * FROM `{$table}` ORDER BY step_id" ) as $row ) {
			$result[ (string) $row['step_id'] ] = $row;
		}
		return $result;
	}

	/**
	 * Most recent failure time of any step, as Unix time.
	 *
	 * @return int|null
	 */
	public function last_failure(): ?int {
		$table = $this->db->table( 'ccap_migration_step' );
		$value = $this->db->scalar( $this->db->prepare( "SELECT MAX(finished_at) FROM `{$table}` WHERE status = %s", self::FAILED ) );
		return null === $value ? null : (int) strtotime( $value . ' UTC' );
	}

	/**
	 * Mark a step as running (creates the row if needed).
	 *
	 * A row still `running` belongs to a run that died (timeout, fatal,
	 * `kill -9`): `crash_count` grows. From {@see self::CRASH_LIMIT} crashes
	 * on, automatic runs stop and the step becomes `failed` with
	 * `blocked_reason = poison` (R0-37 §4.5/§4.6). A manual run (CLI) clears
	 * the poison mark and starts again.
	 *
	 * Cursor and progress survive a restart (resume, I-M4).
	 *
	 * @param string $step_id  Step ID.
	 * @param string $module   Module.
	 * @param string $kind     structure|data|backfill|contract.
	 * @param string $checksum Checksum of the step definition.
	 * @param string $release  Plugin version introducing the step.
	 * @param Lease  $lease    Held lease.
	 * @param bool   $manual   Manual run (CLI): ignores and clears the poison mark.
	 * @return bool False when the step is poisoned and was not started.
	 */
	public function start( string $step_id, string $module, string $kind, string $checksum, string $release, Lease $lease, bool $manual = false ): bool {
		$this->guard( $lease );
		$table = $this->db->table( 'ccap_migration_step' );
		$now   = $this->db->now();
		$row   = $this->get( $step_id );

		if ( null === $row ) {
			$this->db->execute(
				$this->db->prepare(
					"INSERT INTO `{$table}` (step_id, module, kind, status, attempts, fence, checksum, started_at, heartbeat_at, released_in) VALUES (%s, %s, %s, %s, 1, %d, %s, %s, %s, %s)",
					$step_id,
					$module,
					$kind,
					self::RUNNING,
					$lease->fence,
					$checksum,
					$now,
					$now,
					$release
				)
			);
			return true;
		}

		$poisoned = self::POISON === $row['blocked_reason'];
		$crashes  = (int) $row['crash_count'] + ( self::RUNNING === $row['status'] ? 1 : 0 );
		if ( $manual && $poisoned ) {
			$crashes = 0;
		}
		if ( ! $manual && ( $poisoned || $crashes >= self::CRASH_LIMIT ) ) {
			$this->db->execute(
				$this->db->prepare(
					"UPDATE `{$table}` SET status = %s, crash_count = %d, blocked_reason = %s, fence = %d, last_error = %s, finished_at = %s, heartbeat_at = %s WHERE step_id = %s",
					self::FAILED,
					$crashes,
					self::POISON,
					$lease->fence,
					$poisoned ? (string) $row['last_error'] : sprintf( 'Run aborted %d times in this step (poison).', $crashes ),
					$poisoned && null !== $row['finished_at'] ? (string) $row['finished_at'] : $now,
					$now,
					$step_id
				)
			);
			return false;
		}

		$this->db->execute(
			$this->db->prepare(
				"UPDATE `{$table}` SET status = %s, attempts = attempts + 1, crash_count = %d, fence = %d, checksum = %s, started_at = %s, heartbeat_at = %s, finished_at = NULL, last_error = NULL, blocked_reason = NULL WHERE step_id = %s",
				self::RUNNING,
				$crashes,
				$lease->fence,
				$checksum,
				$now,
				$now,
				$step_id
			)
		);
		return true;
	}

	/**
	 * Save backfill progress (cursor and rows) of a running step; part of
	 * the batch transaction, guarded by the fence (I-M4).
	 *
	 * @param string $step_id Step ID.
	 * @param string $cursor  New cursor.
	 * @param int    $rows    Rows written in this batch.
	 * @param Lease  $lease   Held lease.
	 * @return void
	 * @throws MigrationFencedException When the step belongs to another fence.
	 */
	public function progress( string $step_id, string $cursor, int $rows, Lease $lease ): void {
		$this->guard( $lease );
		$table = $this->db->table( 'ccap_migration_step' );
		$this->db->execute(
			$this->db->prepare(
				"UPDATE `{$table}` SET `cursor` = %s, rows_done = rows_done + %d, heartbeat_at = %s WHERE step_id = %s AND fence = %d",
				$cursor,
				$rows,
				$this->db->now(),
				$step_id,
				$lease->fence
			)
		);
		$this->assert_fence( $step_id, $lease );
	}

	/**
	 * Store the number of rows a backfill expects (display only).
	 *
	 * @param string $step_id Step ID.
	 * @param int    $total   Expected rows.
	 * @param Lease  $lease   Held lease.
	 * @return void
	 */
	public function set_total( string $step_id, int $total, Lease $lease ): void {
		$this->guard( $lease );
		$table = $this->db->table( 'ccap_migration_step' );
		$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET rows_total = %d WHERE step_id = %s AND fence = %d", $total, $step_id, $lease->fence ) );
	}

	/**
	 * Hand a running step back to `pending` (time budget used up). The next
	 * run resumes at the cursor without counting a crash.
	 *
	 * @param string $step_id Step ID.
	 * @param Lease  $lease   Held lease.
	 * @return void
	 */
	public function pause( string $step_id, Lease $lease ): void {
		$this->guard( $lease );
		$table = $this->db->table( 'ccap_migration_step' );
		$this->db->execute(
			$this->db->prepare( "UPDATE `{$table}` SET status = %s, heartbeat_at = %s WHERE step_id = %s AND fence = %d", self::PENDING, $this->db->now(), $step_id, $lease->fence )
		);
	}

	/**
	 * Mark a step as done.
	 *
	 * @param string $step_id Step ID.
	 * @param Lease  $lease   Held lease.
	 * @return void
	 */
	public function done( string $step_id, Lease $lease ): void {
		$this->finish( $step_id, self::DONE, null, $lease );
	}

	/**
	 * Mark a step as failed with a shortened message.
	 *
	 * @param string $step_id Step ID.
	 * @param string $error   Error message (shortened, no secrets).
	 * @param Lease  $lease   Held lease.
	 * @return void
	 */
	public function failed( string $step_id, string $error, Lease $lease ): void {
		$this->finish( $step_id, self::FAILED, substr( $error, 0, 500 ), $lease );
	}

	/**
	 * Write the final status.
	 *
	 * @param string      $step_id Step ID.
	 * @param string      $status  done|failed.
	 * @param string|null $error   Error.
	 * @param Lease       $lease   Held lease.
	 * @return void
	 */
	private function finish( string $step_id, string $status, ?string $error, Lease $lease ): void {
		$this->guard( $lease );
		$table = $this->db->table( 'ccap_migration_step' );
		$now   = $this->db->now();
		$this->db->execute(
			$this->db->prepare(
				"UPDATE `{$table}` SET status = %s, last_error = %s, finished_at = %s, heartbeat_at = %s, blocked_reason = IF(%s = %s AND attempts >= %d, %s, NULL) WHERE step_id = %s AND fence = %d",
				$status,
				(string) $error,
				$now,
				$now,
				$status,
				self::FAILED,
				self::FAIL_LIMIT,
				self::POISON,
				$step_id,
				$lease->fence
			)
		);
	}

	/**
	 * The step row must carry our fence, otherwise another runner owns it.
	 *
	 * @param string $step_id Step ID.
	 * @param Lease  $lease   Held lease.
	 * @return void
	 * @throws MigrationFencedException When the fence differs.
	 */
	private function assert_fence( string $step_id, Lease $lease ): void {
		$table = $this->db->table( 'ccap_migration_step' );
		$fence = $this->db->scalar( $this->db->prepare( "SELECT fence FROM `{$table}` WHERE step_id = %s", $step_id ) );
		if ( (string) $lease->fence !== $fence ) {
			throw new MigrationFencedException( 'Step owned by another runner.' );
		}
	}

	/**
	 * Fencing guard (R0-37 §3.1): no status write without the lease.
	 *
	 * @param Lease $lease Lease.
	 * @return void
	 * @throws MigrationFencedException When the lease is lost.
	 */
	private function guard( Lease $lease ): void {
		if ( ! $lease->heartbeat( Migrator::LEASE_TTL_DDL ) ) {
			throw new MigrationFencedException( 'Lease lost.' );
		}
	}
}
