<?php
/**
 * Append-only audit log with hash chain (R0-06 §4.7, R0-13 §8, E-139;
 * pseudonymised chain: owner decision D12, R0-31 RT-23).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Event;

use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Every row carries `row_hash = sha256(previous_hash | canonical row)`. The
 * canonical row contains pseudonyms only ({@see AuditIdentity}): actor and
 * affected user are stored as `actor_ref`/`subject_ref`, the columns
 * `actor_user_id`/`subject_user_id` stay empty (kept for the expand phase of
 * R0-37, removed only by a later contract step). Erasure deletes the
 * pseudonym mapping, so the chain keeps verifying.
 *
 * Chains per `chain_epoch`: 0 = legacy rows hashed over user IDs (until the
 * re-seal backfill `core:0005` has run), 1 = legacy rows re-sealed with
 * pseudonyms, 2 = rows written by this code. Each epoch is its own chain in
 * ID order; it starts at `''` or, after retention removed its oldest rows,
 * at the anchor recorded in the newest `audit_pruned` entry
 * ({@see self::genesis()}). Verification: {@see AuditChain::verify()}.
 */
final class AuditLog {

	/** Hash format of new rows. */
	public const EPOCH = 2;

	/** Epoch of re-sealed legacy rows. */
	public const EPOCH_RESEALED = 1;

	/** Columns of the canonical row (order is part of the format). */
	private const FIELDS = array( 'actor_ref', 'actor_type', 'subject_ref', 'action', 'object_type', 'object_id', 'reason', 'context', 'created_at', 'chain_epoch' );

	/**
	 * Write an entry (inside or outside a transaction).
	 *
	 * @param Database             $db          Database.
	 * @param Actor                $actor       Actor.
	 * @param string               $action      e.g. `grant.manual_created`.
	 * @param string               $object_type Object type.
	 * @param string               $object_id   Object ID or UID.
	 * @param string|null          $reason      Justification (free text).
	 * @param array<string, mixed> $context     Context without secrets/tokens.
	 * @param int|null             $subject     Affected user.
	 * @return int Audit row ID.
	 */
	public static function write( Database $db, Actor $actor, string $action, string $object_type, string $object_id, ?string $reason = null, array $context = array(), ?int $subject = null ): int {
		$table    = $db->table( 'ccap_audit_log' );
		$context  = AuditIdentity::context( $db, $context );
		$row      = array(
			'actor_ref'   => $actor->user_id > 0 ? AuditIdentity::ref_for( $db, $actor->user_id ) : null,
			'actor_type'  => $actor->is_system() ? ( str_starts_with( $actor->type, 'system' ) ? 'system' : 'connector' ) : 'user',
			'subject_ref' => null !== $subject && $subject > 0 ? AuditIdentity::ref_for( $db, $subject ) : null,
			'action'      => $action,
			'object_type' => $object_type,
			'object_id'   => AuditIdentity::object_id( $db, $object_type, $object_id ),
			'reason'      => $reason,
			'context'     => array() === $context ? null : (string) wp_json_encode( $context ),
			'created_at'  => $db->now(),
			'chain_epoch' => self::EPOCH,
		);
		$previous = $db->scalar( $db->prepare( "SELECT row_hash FROM `{$table}` WHERE chain_epoch = %d ORDER BY id DESC LIMIT 1", self::EPOCH ) );
		if ( null === $previous ) {
			// Retention removed every row of this epoch: the summary entry continues from its own anchor.
			$anchors  = (array) ( $context['anchors'] ?? array() );
			$previous = 'audit_pruned' === $action && isset( $anchors[ self::EPOCH ] ) ? (string) $anchors[ self::EPOCH ] : self::genesis( $db, self::EPOCH );
		}
		$row['row_hash'] = self::seal( (string) $previous, $row );
		return $db->insert( 'ccap_audit_log', $row );
	}

	/**
	 * Hash of a row in the pseudonymised format (epochs 1 and 2).
	 *
	 * @param string               $previous Hash of the previous row of the epoch.
	 * @param array<string, mixed> $row      Row (as written or as read).
	 * @return string
	 */
	public static function seal( string $previous, array $row ): string {
		$canonical = array();
		foreach ( self::FIELDS as $field ) {
			$canonical[ $field ] = isset( $row[ $field ] ) ? (string) $row[ $field ] : null;
		}
		return hash( 'sha256', $previous . '|' . (string) wp_json_encode( $canonical ) );
	}

	/**
	 * Hash of a row in the legacy format (epoch 0: over user IDs).
	 *
	 * @param string               $previous Hash of the previous row.
	 * @param array<string, mixed> $row      Row as read.
	 * @return string
	 */
	public static function legacy_seal( string $previous, array $row ): string {
		$legacy = array(
			'actor_user_id'   => null === $row['actor_user_id'] ? null : (int) $row['actor_user_id'],
			'actor_type'      => (string) $row['actor_type'],
			'subject_user_id' => null === $row['subject_user_id'] ? null : (int) $row['subject_user_id'],
			'action'          => (string) $row['action'],
			'object_type'     => (string) $row['object_type'],
			'object_id'       => (string) $row['object_id'],
			'reason'          => null === $row['reason'] ? null : (string) $row['reason'],
			'context'         => null === $row['context'] ? null : (string) $row['context'],
			'created_at'      => (string) $row['created_at'],
		);
		return hash( 'sha256', $previous . '|' . (string) wp_json_encode( $legacy ) );
	}

	/**
	 * Start value of an epoch's chain: the anchor of its newest removed row
	 * from the newest `audit_pruned` entry that names one, else `''`. Legacy
	 * summaries (single `anchor`, written before D12) count for epoch 0 only.
	 *
	 * @param Database $db    Database.
	 * @param int      $epoch Epoch.
	 * @return string
	 */
	public static function genesis( Database $db, int $epoch ): string {
		$table = $db->table( 'ccap_audit_log' );
		foreach ( $db->rows( "SELECT context FROM `{$table}` WHERE action = 'audit_pruned' ORDER BY id DESC LIMIT 50" ) as $row ) {
			$context = json_decode( (string) ( $row['context'] ?? '' ), true );
			if ( ! is_array( $context ) ) {
				continue;
			}
			if ( isset( $context['anchors'] ) && is_array( $context['anchors'] ) ) {
				if ( isset( $context['anchors'][ $epoch ] ) ) {
					return (string) $context['anchors'][ $epoch ];
				}
				continue;
			}
			if ( 0 === $epoch && isset( $context['anchor'] ) ) {
				return (string) $context['anchor'];
			}
		}
		return '';
	}
}
