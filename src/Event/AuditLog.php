<?php
/**
 * Append-only audit log with hash chain (R0-06 §4.7, R0-13 §8, E-139).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Event;

use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Every row carries `row_hash = sha256(previous_hash | canonical row)`.
 * Viewing, export and retention follow with R1-17; here only writing.
 */
final class AuditLog {

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
		$table           = $db->table( 'ccap_audit_log' );
		$previous        = (string) $db->scalar( "SELECT row_hash FROM `{$table}` ORDER BY id DESC LIMIT 1" );
		$row             = array(
			'actor_user_id'   => $actor->user_id > 0 ? $actor->user_id : null,
			'actor_type'      => $actor->is_system() ? ( str_starts_with( $actor->type, 'system' ) ? 'system' : 'connector' ) : 'user',
			'subject_user_id' => $subject,
			'action'          => $action,
			'object_type'     => $object_type,
			'object_id'       => $object_id,
			'reason'          => $reason,
			'context'         => array() === $context ? null : (string) wp_json_encode( $context ),
			'created_at'      => $db->now(),
		);
		$row['row_hash'] = hash( 'sha256', $previous . '|' . (string) wp_json_encode( $row ) );
		return $db->insert( 'ccap_audit_log', $row );
	}
}
