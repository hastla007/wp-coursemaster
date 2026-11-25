<?php
/**
 * Verification of the audit hash chain (owner decision D12, R0-31 RT-23).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Event;

use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Walks every epoch in ID order and recomputes each hash ({@see AuditLog}).
 * Read only; the result never contains personal data (IDs of broken rows
 * and counts only). The chain is ok when every epoch verifies, no legacy row
 * fails its legacy hash (rows pseudonymised by an erasure before the re-seal
 * are counted, not checked) and no pseudonymised row still carries a user ID.
 * CLI: `wp course-captain audit verify`.
 */
final class AuditChain {

	private const BATCH = 1000;

	/**
	 * Verify the chain.
	 *
	 * @param Database $db Database.
	 * @return array{ok: bool, rows: int, epochs: array<int, array{rows: int, first_id: int, last_id: int, ok: bool, broken_at: int}>, legacy: array{rows: int, mismatches: int, erased: int, first_mismatch: int}, personal_refs: int, mappings: int}
	 */
	public static function verify( Database $db ): array {
		$table  = $db->table( 'ccap_audit_log' );
		$epochs = array();
		foreach ( array( AuditLog::EPOCH_RESEALED, AuditLog::EPOCH ) as $epoch ) {
			$stats = array(
				'rows'      => 0,
				'first_id'  => 0,
				'last_id'   => 0,
				'ok'        => true,
				'broken_at' => 0,
			);
			$prev  = AuditLog::genesis( $db, $epoch );
			self::walk(
				$db,
				$epoch,
				static function ( array $row ) use ( &$stats, &$prev ): void {
					++$stats['rows'];
					$id                = (int) $row['id'];
					$stats['first_id'] = 0 === $stats['first_id'] ? $id : $stats['first_id'];
					$stats['last_id']  = $id;
					if ( $stats['ok'] && AuditLog::seal( $prev, $row ) !== (string) $row['row_hash'] ) {
						$stats['ok']        = false;
						$stats['broken_at'] = $id;
					}
					$prev = (string) $row['row_hash'];
				}
			);
			$epochs[ $epoch ] = $stats;
		}
		$state  = get_option( AuditIdentity::RESEAL_STATE );
		$state  = is_array( $state ) ? $state : array();
		$erased = array_flip( array_map( 'intval', (array) ( $state['erased'] ?? array() ) ) );
		$legacy = array(
			'rows'           => 0,
			'mismatches'     => 0,
			'erased'         => 0,
			'first_mismatch' => 0,
		);
		$prev   = isset( $state['legacy_prev'] ) ? (string) $state['legacy_prev'] : AuditLog::genesis( $db, 0 );
		self::walk(
			$db,
			0,
			static function ( array $row ) use ( &$legacy, &$prev, $erased ): void {
				++$legacy['rows'];
				if ( isset( $erased[ (int) $row['id'] ] ) ) {
					++$legacy['erased'];
				} elseif ( AuditLog::legacy_seal( $prev, $row ) !== (string) $row['row_hash'] ) {
					++$legacy['mismatches'];
					$legacy['first_mismatch'] = 0 === $legacy['first_mismatch'] ? (int) $row['id'] : $legacy['first_mismatch'];
				}
				$prev = (string) $row['row_hash'];
			}
		);
		$personal = (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE chain_epoch > 0 AND (actor_user_id IS NOT NULL OR subject_user_id IS NOT NULL)" );
		$ok       = 0 === $legacy['mismatches'] && 0 === $personal;
		foreach ( $epochs as $stats ) {
			$ok = $ok && $stats['ok'];
		}
		return array(
			'ok'            => $ok,
			'rows'          => $legacy['rows'] + (int) array_sum( array_column( $epochs, 'rows' ) ),
			'epochs'        => $epochs,
			'legacy'        => $legacy,
			'personal_refs' => $personal,
			'mappings'      => (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_identity' ) . '`' ),
		);
	}

	/**
	 * Rows of one epoch in ID order, in batches.
	 *
	 * @param Database $db    Database.
	 * @param int      $epoch Epoch.
	 * @param callable $visit Visitor.
	 * @phpstan-param callable(array<string, mixed>):void $visit
	 * @return void
	 */
	private static function walk( Database $db, int $epoch, callable $visit ): void {
		$table  = $db->table( 'ccap_audit_log' );
		$cursor = 0;
		do {
			$rows = $db->rows( $db->prepare( "SELECT * FROM `{$table}` WHERE chain_epoch = %d AND id > %d ORDER BY id LIMIT %d", $epoch, $cursor, self::BATCH ) );
			foreach ( $rows as $row ) {
				$visit( $row );
				$cursor = (int) $row['id'];
			}
			$full = self::BATCH === count( $rows );
		} while ( $full );
	}
}
