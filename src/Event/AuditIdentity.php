<?php
/**
 * Pseudonyms of the audit log (owner decision D12, R0-31 RT-23).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Event;

use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * The audit hash chain covers pseudonyms only. A pseudonym is `A-` plus 16
 * random hex characters, created on first use per user and kept in
 * `ccap_audit_identity` (the only link to the WordPress account). Erasure
 * deletes that row: the audit entries stay, their hashes stay valid, and the
 * pseudonym can no longer be traced back to the person (it is random, not
 * derived from the user ID, unlike the export pseudonym of SET-CC-26).
 *
 * Personal references that are replaced in an entry: actor and affected
 * user, `object_id` of `user` objects, values of {@see self::PERSON_KEYS} in
 * the context (any depth) and `user:<ID>` tokens in `object_id` and context
 * strings. Free-text reasons are written by staff and are not rewritten.
 */
final class AuditIdentity {

	/** Context keys whose numeric values are user IDs. */
	public const PERSON_KEYS = array( 'user', 'user_id', 'owner', 'previous_owner', 'owner_user_id', 'learner_id', 'member', 'actor_user_id', 'subject_user_id', 'recipient_user_id' );

	/** Option with the state of the re-seal of legacy entries (chain epoch 1). */
	public const RESEAL_STATE = 'course_captain_audit_reseal';

	private const TABLE  = 'ccap_audit_identity';
	private const PREFIX = 'A-';

	/**
	 * Pseudonym of a user, created on first use.
	 *
	 * @param Database $db      Database.
	 * @param int      $user_id User.
	 * @return string
	 */
	public static function ref_for( Database $db, int $user_id ): string {
		$known = self::ref_of( $db, $user_id );
		if ( null !== $known ) {
			return $known;
		}
		$db->execute(
			$db->prepare(
				'INSERT IGNORE INTO `' . $db->table( self::TABLE ) . '` (pseudonym, user_id, created_at) VALUES (%s, %d, %s)',
				self::PREFIX . bin2hex( random_bytes( 8 ) ),
				$user_id,
				$db->now()
			)
		);
		// A parallel request may have won the unique key `user_id`: read back.
		return (string) self::ref_of( $db, $user_id );
	}

	/**
	 * Existing pseudonym of a user (no new row).
	 *
	 * @param Database $db      Database.
	 * @param int      $user_id User.
	 * @return string|null
	 */
	public static function ref_of( Database $db, int $user_id ): ?string {
		if ( $user_id <= 0 ) {
			return null;
		}
		return $db->scalar( $db->prepare( 'SELECT pseudonym FROM `' . $db->table( self::TABLE ) . '` WHERE user_id = %d', $user_id ) );
	}

	/**
	 * User behind a pseudonym (null when unknown or erased).
	 *
	 * @param Database    $db  Database.
	 * @param string|null $ref Pseudonym.
	 * @return int|null
	 */
	public static function user_of( Database $db, ?string $ref ): ?int {
		if ( null === $ref || '' === $ref ) {
			return null;
		}
		$id = $db->scalar( $db->prepare( 'SELECT user_id FROM `' . $db->table( self::TABLE ) . '` WHERE pseudonym = %s', $ref ) );
		return null === $id ? null : (int) $id;
	}

	/**
	 * Users behind several pseudonyms.
	 *
	 * @param Database $db   Database.
	 * @param array    $refs Pseudonyms.
	 * @phpstan-param list<string> $refs
	 * @return array<string, int> Pseudonym → user.
	 */
	public static function users_of( Database $db, array $refs ): array {
		$refs = array_values( array_unique( array_filter( $refs, static fn( string $r ): bool => '' !== $r ) ) );
		if ( array() === $refs ) {
			return array();
		}
		$in  = implode( ',', array_map( static fn( string $r ): string => $db->prepare( '%s', $r ), $refs ) );
		$out = array();
		foreach ( $db->rows( 'SELECT pseudonym, user_id FROM `' . $db->table( self::TABLE ) . "` WHERE pseudonym IN ({$in})" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Values prepared above.
			$out[ (string) $row['pseudonym'] ] = (int) $row['user_id'];
		}
		return $out;
	}

	/**
	 * `object_id` with user references replaced.
	 *
	 * @param Database $db          Database.
	 * @param string   $object_type Object type.
	 * @param string   $object_id   Object ID.
	 * @return string
	 */
	public static function object_id( Database $db, string $object_type, string $object_id ): string {
		if ( 'user' === $object_type && 1 === preg_match( '/^[1-9]\d*$/', $object_id ) ) {
			return self::ref_for( $db, (int) $object_id );
		}
		return self::tokens( $db, $object_id );
	}

	/**
	 * Context with user references replaced (any depth).
	 *
	 * @param Database             $db      Database.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	public static function context( Database $db, array $context ): array {
		$out = array();
		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$out[ $key ] = self::context( $db, $value );
			} elseif ( is_string( $key ) && in_array( $key, self::PERSON_KEYS, true ) && ( is_int( $value ) || ( is_string( $value ) && 1 === preg_match( '/^\d+$/', $value ) ) ) ) {
				$out[ $key ] = (int) $value > 0 ? self::ref_for( $db, (int) $value ) : $value;
			} elseif ( is_string( $value ) ) {
				$out[ $key ] = self::tokens( $db, $value );
			} else {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * Pseudonymised values of a stored (legacy) audit row.
	 *
	 * @param Database             $db  Database.
	 * @param array<string, mixed> $row Audit row as read.
	 * @return array{actor_ref: string|null, subject_ref: string|null, object_id: string|null, context: string|null, actor_user_id: null, subject_user_id: null}
	 */
	public static function pseudonymise( Database $db, array $row ): array {
		$actor   = (int) ( $row['actor_user_id'] ?? 0 );
		$subject = (int) ( $row['subject_user_id'] ?? 0 );
		$context = null === $row['context'] ? null : (string) $row['context'];
		if ( null !== $context ) {
			$decoded = json_decode( $context, true );
			if ( is_array( $decoded ) ) {
				$mapped  = self::context( $db, $decoded );
				$context = $mapped === $decoded ? $context : (string) wp_json_encode( $mapped );
			}
		}
		return array(
			'actor_ref'       => null !== ( $row['actor_ref'] ?? null ) ? (string) $row['actor_ref'] : ( $actor > 0 ? self::ref_for( $db, $actor ) : null ),
			'subject_ref'     => null !== ( $row['subject_ref'] ?? null ) ? (string) $row['subject_ref'] : ( $subject > 0 ? self::ref_for( $db, $subject ) : null ),
			'object_id'       => null === $row['object_id'] ? null : self::object_id( $db, (string) $row['object_type'], (string) $row['object_id'] ),
			'context'         => $context,
			'actor_user_id'   => null,
			'subject_user_id' => null,
		);
	}

	/**
	 * Erasure (RT-23): pseudonymise legacy entries of the person that were not
	 * re-sealed yet (their IDs leave the table; the re-seal skips the legacy
	 * hash check for them), then delete the mapping. The chain stays valid.
	 *
	 * @param Database $db      Database (inside the eraser transaction).
	 * @param int      $user_id User.
	 * @return int Rows changed (mapping row plus legacy entries).
	 */
	public static function erase( Database $db, int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		$table = $db->table( 'ccap_audit_log' );
		$rows  = $db->rows(
			$db->prepare(
				"SELECT * FROM `{$table}` WHERE chain_epoch = 0 AND (actor_user_id = %d OR subject_user_id = %d OR (object_type = 'user' AND object_id = %s) OR object_id LIKE %s OR context REGEXP %s) ORDER BY id",
				$user_id,
				$user_id,
				(string) $user_id,
				'%user:' . $user_id . '%',
				'"(' . implode( '|', self::PERSON_KEYS ) . ')":"?' . $user_id . '"?[,}]|user:' . $user_id . '[^0-9]'
			)
		);
		$count = 0;
		if ( array() !== $rows ) {
			$state           = get_option( self::RESEAL_STATE );
			$state           = is_array( $state ) ? $state : array();
			$state['erased'] = array_values( array_map( 'intval', (array) ( $state['erased'] ?? array() ) ) );
			foreach ( $rows as $row ) {
				$values = self::pseudonymise( $db, $row );
				$sets   = array();
				foreach ( $values as $column => $value ) {
					$sets[] = null === $value ? "`{$column}` = NULL" : $db->prepare( "`{$column}` = %s", $value );
				}
				$count            += $db->execute( $db->prepare( "UPDATE `{$table}` SET " . implode( ', ', $sets ) . ' WHERE id = %d AND chain_epoch = 0', (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Values prepared above.
				$state['erased'][] = (int) $row['id'];
			}
			update_option( self::RESEAL_STATE, $state, false );
		}
		return $count + $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( self::TABLE ) . '` WHERE user_id = %d', $user_id ) );
	}

	/**
	 * `user:<ID>` tokens in a string replaced by `user:<pseudonym>`.
	 *
	 * @param Database $db    Database.
	 * @param string   $value Value.
	 * @return string
	 */
	private static function tokens( Database $db, string $value ): string {
		if ( ! str_contains( $value, 'user:' ) ) {
			return $value;
		}
		return (string) preg_replace_callback( '/\buser:([1-9]\d*)\b/', static fn( array $m ): string => 'user:' . self::ref_for( $db, (int) $m[1] ), $value );
	}
}
