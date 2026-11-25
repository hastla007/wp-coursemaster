<?php
/**
 * Backfill: re-seal legacy audit rows with pseudonyms (owner decision D12,
 * R0-31 RT-23, R0-37 §4.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Event;

use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\BackfillStep;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Rows written before D12 (`chain_epoch = 0`) hashed over user IDs, so an
 * erasure would have broken them. Per batch, in ID order:
 *
 * 1. check the legacy hash against the legacy chain (the previous old hash
 *    is carried in {@see AuditIdentity::RESEAL_STATE}); rows an erasure
 *    pseudonymised before the re-seal are counted instead of checked;
 * 2. replace the personal references by pseudonyms ({@see AuditIdentity::pseudonymise()});
 * 3. hash the row again in the pseudonymised format as chain epoch 1
 *    (previous = last re-sealed row, or the epoch-1 anchor / `''`).
 *
 * New rows (epoch 2) never chain onto legacy rows, so writes during the
 * online backfill (W2) do not race with it. When no legacy row is left the
 * batch writes the audited marker `audit_chain_resealed` (epoch 2) that
 * documents the epoch change: number of rows, first/last ID, result of the
 * legacy check, old head hash and new head hash of epoch 1, which binds the
 * re-sealed history into the current chain. Natural idempotency: a re-sealed
 * row has `chain_epoch = 1` and leaves `pending_where()`.
 */
final class AuditResealBackfill implements BackfillStep {

	public const ID = 'core:0005_audit_pseudonym_reseal';

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function module(): string {
		return 'core';
	}

	/**
	 * {@inheritDoc}
	 */
	public function table(): string {
		return 'ccap_audit_log';
	}

	/**
	 * {@inheritDoc}
	 */
	public function pending_where(): string {
		return 'chain_epoch = 0';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Database $db  Database.
	 * @param array    $ids IDs.
	 * @phpstan-param list<int> $ids
	 */
	public function apply( Database $db, array $ids ): int {
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return 0;
		}
		sort( $ids );
		$table = $db->table( 'ccap_audit_log' );
		$rows  = $db->rows( "SELECT * FROM `{$table}` WHERE chain_epoch = 0 AND id IN (" . implode( ',', $ids ) . ') ORDER BY id' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer IDs.
		$state = get_option( AuditIdentity::RESEAL_STATE );
		$state = is_array( $state ) ? $state : array();
		if ( ! isset( $state['legacy_prev'] ) ) {
			$state += array(
				'legacy_prev'    => AuditLog::genesis( $db, 0 ),
				'rows'           => 0,
				'mismatches'     => 0,
				'first_mismatch' => 0,
				'first_id'       => 0,
				'last_id'        => 0,
				'legacy_head'    => '',
				'sealed'         => false,
			);
		}
		$erased = array_flip( array_map( 'intval', (array) ( $state['erased'] ?? array() ) ) );
		$prev   = $db->scalar( $db->prepare( "SELECT row_hash FROM `{$table}` WHERE chain_epoch = %d AND id < %d ORDER BY id DESC LIMIT 1", AuditLog::EPOCH_RESEALED, $ids[0] ) );
		$prev   = null === $prev ? AuditLog::genesis( $db, AuditLog::EPOCH_RESEALED ) : (string) $prev;
		$count  = 0;
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			if ( ! isset( $erased[ $id ] ) && AuditLog::legacy_seal( (string) $state['legacy_prev'], $row ) !== (string) $row['row_hash'] ) {
				++$state['mismatches'];
				$state['first_mismatch'] = 0 === (int) $state['first_mismatch'] ? $id : (int) $state['first_mismatch'];
			}
			$state['legacy_prev'] = (string) $row['row_hash'];
			$state['legacy_head'] = (string) $row['row_hash'];
			$values               = AuditIdentity::pseudonymise( $db, $row );
			$sealed               = array_merge( $row, $values, array( 'chain_epoch' => AuditLog::EPOCH_RESEALED ) );
			$hash                 = AuditLog::seal( $prev, $sealed );
			$sets                 = array();
			foreach ( $values + array(
				'chain_epoch' => (string) AuditLog::EPOCH_RESEALED,
				'row_hash'    => $hash,
			) as $column => $value ) {
				$sets[] = null === $value ? "`{$column}` = NULL" : $db->prepare( "`{$column}` = %s", $value );
			}
			$count += $db->execute( $db->prepare( "UPDATE `{$table}` SET " . implode( ', ', $sets ) . ' WHERE id = %d AND chain_epoch = 0', $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Values prepared above.
			$prev   = $hash;
			++$state['rows'];
			$state['first_id'] = 0 === (int) $state['first_id'] ? $id : (int) $state['first_id'];
			$state['last_id']  = $id;
		}
		$left = (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE chain_epoch = 0" );
		if ( 0 === $left && empty( $state['sealed'] ) ) {
			$state['sealed'] = true;
			AuditLog::write(
				$db,
				Actor::cron(),
				'audit_chain_resealed',
				'audit',
				'chain_epoch_' . AuditLog::EPOCH_RESEALED,
				null,
				array(
					'epoch'           => AuditLog::EPOCH_RESEALED,
					'step'            => self::ID,
					'rows'            => (int) $state['rows'],
					'first_id'        => (int) $state['first_id'],
					'last_id'         => (int) $state['last_id'],
					'legacy_verified' => 0 === (int) $state['mismatches'],
					'legacy_broken'   => (int) $state['mismatches'],
					'first_broken_id' => (int) $state['first_mismatch'],
					'erased_before'   => count( $erased ),
					'legacy_head'     => (string) $state['legacy_head'],
					'resealed_head'   => $prev,
				)
			);
		}
		update_option( AuditIdentity::RESEAL_STATE, $state, false );
		return $count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function checksum(): string {
		return sha1( self::ID . '|' . $this->pending_where() . '|' . implode( ',', AuditIdentity::PERSON_KEYS ) . '|' . AuditLog::EPOCH_RESEALED );
	}
}
