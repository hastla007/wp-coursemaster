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
	 * @param string $step_id  Step ID.
	 * @param string $module   Module.
	 * @param string $kind     structure|data|backfill|contract.
	 * @param string $checksum Checksum of the step definition.
	 * @param string $release  Plugin version introducing the step.
	 * @param Lease  $lease    Held lease.
	 * @return void
	 */
	public function start( string $step_id, string $module, string $kind, string $checksum, string $release, Lease $lease ): void {
		$this->guard( $lease );
		$table = $this->db->table( 'ccap_migration_step' );
		$now   = $this->db->now();
		$this->db->execute(
			$this->db->prepare(
				"INSERT INTO `{$table}` (step_id, module, kind, status, attempts, fence, checksum, started_at, heartbeat_at, released_in) VALUES (%s, %s, %s, %s, 1, %d, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE crash_count = crash_count + IF(status = %s, 1, 0), status = VALUES(status), attempts = attempts + 1, fence = VALUES(fence), checksum = VALUES(checksum), started_at = VALUES(started_at), heartbeat_at = VALUES(heartbeat_at), finished_at = NULL, last_error = NULL, blocked_reason = NULL",
				$step_id,
				$module,
				$kind,
				self::RUNNING,
				$lease->fence,
				$checksum,
				$now,
				$now,
				$release,
				self::RUNNING
			)
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
				"UPDATE `{$table}` SET status = %s, last_error = %s, finished_at = %s, heartbeat_at = %s WHERE step_id = %s AND fence = %d",
				$status,
				(string) $error,
				$now,
				$now,
				$step_id,
				$lease->fence
			)
		);
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
