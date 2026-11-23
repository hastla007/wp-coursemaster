<?php
/**
 * Lease with fencing token in ccap_lease (R0-21 §2.1/§2.4, R0-37 L-B).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * A held lease. Every write of the holder must be guarded by `heartbeat()`
 * (affected rows = 1) so that a taken-over "zombie" cannot write.
 */
final class Lease {

	/**
	 * Constructor.
	 *
	 * @param Database $db    Database access.
	 * @param string   $name  Lease name.
	 * @param string   $owner Owner token of this process.
	 * @param int      $fence Fencing token.
	 */
	private function __construct(
		private Database $db,
		public readonly string $name,
		public readonly string $owner,
		public readonly int $fence,
	) {
	}

	/**
	 * Try to acquire a lease, never waiting.
	 *
	 * @param Database $db       Database access.
	 * @param string   $name     Lease name.
	 * @param int      $ttl      Seconds until expiry.
	 * @param bool     $takeover Take over a live lease (caller holds the named lock, so the holder is dead).
	 * @return self|null
	 */
	public static function acquire( Database $db, string $name, int $ttl, bool $takeover ): ?self {
		$owner = substr( str_replace( '-', '', $db->uuid() ), 0, 32 );
		$table = $db->table( 'ccap_lease' );
		$now   = $db->now();
		$until = $db->now( $ttl );

		$inserted = $db->execute(
			$db->prepare(
				"INSERT IGNORE INTO `{$table}` (name, owner, fence, expires_at, heartbeat_at, created_at) VALUES (%s, %s, 1, %s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.QuotedSimplePlaceholder
				$name,
				$owner,
				$until,
				$now,
				$now
			)
		);
		if ( 1 !== $inserted ) {
			$condition = $takeover ? '' : $db->prepare( ' AND expires_at < %s', $now );
			$taken     = $db->execute(
				$db->prepare( "UPDATE `{$table}` SET owner = %s, fence = fence + 1, expires_at = %s, heartbeat_at = %s WHERE name = %s", $owner, $until, $now, $name ) . $condition
			);
			if ( 1 !== $taken ) {
				return null;
			}
		}
		$fence = (int) $db->scalar( $db->prepare( "SELECT fence FROM `{$table}` WHERE name = %s AND owner = %s", $name, $owner ) );
		return 0 === $fence ? null : new self( $db, $name, $owner, $fence );
	}

	/**
	 * Extend the lease; false when it was taken over (fencing lost).
	 *
	 * @param int $ttl Seconds until expiry.
	 * @return bool
	 */
	public function heartbeat( int $ttl ): bool {
		$table = $this->db->table( 'ccap_lease' );
		$rows  = $this->db->execute(
			$this->db->prepare(
				"UPDATE `{$table}` SET heartbeat_at = %s, expires_at = %s WHERE name = %s AND owner = %s AND fence = %d",
				$this->db->now(),
				$this->db->now( $ttl ),
				$this->name,
				$this->owner,
				$this->fence
			)
		);
		// Same second: MySQL reports 0 changed rows although the row matched.
		return 1 === $rows || $this->is_held();
	}

	/**
	 * Whether this process still holds the lease.
	 *
	 * @return bool
	 */
	public function is_held(): bool {
		$table = $this->db->table( 'ccap_lease' );
		return null !== $this->db->scalar(
			$this->db->prepare( "SELECT 1 FROM `{$table}` WHERE name = %s AND owner = %s AND fence = %d", $this->name, $this->owner, $this->fence )
		);
	}

	/**
	 * Release (only if still ours).
	 *
	 * @return void
	 */
	public function release(): void {
		$table = $this->db->table( 'ccap_lease' );
		$this->db->execute(
			$this->db->prepare( "DELETE FROM `{$table}` WHERE name = %s AND owner = %s AND fence = %d", $this->name, $this->owner, $this->fence )
		);
	}
}
