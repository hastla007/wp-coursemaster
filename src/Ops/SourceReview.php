<?php
/**
 * Operator policy for existing grants when their source disappears
 * (ACCESS-09, konzept 35.2; R0-27 `enrollment.source_disabled_policy`,
 * SET-075; R0-09 RF-15, GRANT-021).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Access\GrantTransitions;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Never revokes. With the policy `keep` (default) a missing source changes
 * nothing. With `keep_and_flag` every grant of the missing source reference
 * that is not final gets the marker `meta.source_review` and an audit entry
 * `grant.source_flagged`; the system status lists the marked grants (H-29a).
 * When the source delivers again the marker is removed
 * (`grant.source_review_cleared`). Status, validity, grant events and the
 * outbox stay untouched in both cases.
 */
final class SourceReview {

	public const SETTING      = 'enrollment.source_disabled_policy';
	public const META_KEY     = 'source_review';
	public const FLAGGED      = 'grant.source_flagged';
	public const CLEARED      = 'grant.source_review_cleared';
	public const CHECK_ID     = 'H-29a';
	private const MAX_IDS     = 20;
	private const OPEN_STATES = array(
		GrantTransitions::PENDING_CLAIM,
		GrantTransitions::PENDING_CONFIRMATION,
		GrantTransitions::ACTIVE,
		GrantTransitions::SUSPENDED,
	);

	/**
	 * Current operator policy (`keep` or `keep_and_flag`).
	 *
	 * @return string
	 */
	public static function policy(): string {
		try {
			$value = (string) Settings::get( self::SETTING );
		} catch ( Throwable $e ) {
			unset( $e );
			return 'keep';
		}
		return 'keep_and_flag' === $value ? 'keep_and_flag' : 'keep';
	}

	/**
	 * The source of a reference delivered nothing (deactivated, unreachable,
	 * deleted). Marks the open grants of this reference for review when the
	 * policy asks for it.
	 *
	 * @param Database $db          Database.
	 * @param Actor    $actor       Connector actor.
	 * @param string   $source_type Source type.
	 * @param string   $reference   Source reference.
	 * @return int Number of newly marked grants.
	 */
	public static function source_missing( Database $db, Actor $actor, string $source_type, string $reference ): int {
		if ( 'keep_and_flag' !== self::policy() ) {
			return 0;
		}
		$marked = 0;
		foreach ( self::grants( $db, $source_type, $reference ) as $row ) {
			if ( ! in_array( (string) $row['status'], self::OPEN_STATES, true ) ) {
				continue;
			}
			$meta = self::meta( $row );
			if ( isset( $meta[ self::META_KEY ] ) ) {
				continue;
			}
			$meta[ self::META_KEY ] = array(
				'since'  => $db->now(),
				'reason' => 'source_unavailable',
			);
			self::store( $db, (int) $row['id'], $meta );
			self::audit( $db, $actor, self::FLAGGED, $row, $source_type, $reference );
			++$marked;
		}
		return $marked;
	}

	/**
	 * The source of a reference delivers again: removes the review markers.
	 * Runs whatever the current policy is, so switching back to `keep`
	 * leaves no stale markers behind once the source is back.
	 *
	 * @param Database $db          Database.
	 * @param Actor    $actor       Connector actor.
	 * @param string   $source_type Source type.
	 * @param string   $reference   Source reference.
	 * @return int Number of cleared markers.
	 */
	public static function source_back( Database $db, Actor $actor, string $source_type, string $reference ): int {
		$table = $db->table( 'ccap_grant' );
		$any   = $db->scalar( $db->prepare( "SELECT id FROM `{$table}` WHERE source_type = %s AND source_id = %s AND meta LIKE %s LIMIT 1", $source_type, $reference, '%' . $db->wpdb()->esc_like( '"' . self::META_KEY . '"' ) . '%' ) );
		if ( null === $any ) {
			return 0;
		}
		$cleared = 0;
		foreach ( self::grants( $db, $source_type, $reference ) as $row ) {
			$meta = self::meta( $row );
			if ( ! isset( $meta[ self::META_KEY ] ) ) {
				continue;
			}
			unset( $meta[ self::META_KEY ] );
			self::store( $db, (int) $row['id'], $meta );
			self::audit( $db, $actor, self::CLEARED, $row, $source_type, $reference );
			++$cleared;
		}
		return $cleared;
	}

	/**
	 * Marked grants that are still open: count and `grant:<uid>` IDs.
	 *
	 * @param Database $db Database.
	 * @return array{count: int, ids: list<string>}
	 */
	public static function open_items( Database $db ): array {
		$table  = $db->table( 'ccap_grant' );
		$states = "'" . implode( "','", self::OPEN_STATES ) . "'";
		$like   = '%' . $db->wpdb()->esc_like( '"' . self::META_KEY . '"' ) . '%';
		$count  = (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status IN ({$states}) AND meta LIKE %s", $like ) );
		$ids    = array();
		if ( $count > 0 ) {
			foreach ( $db->rows( $db->prepare( "SELECT uid FROM `{$table}` WHERE status IN ({$states}) AND meta LIKE %s ORDER BY id LIMIT %d", $like, self::MAX_IDS ) ) as $row ) {
				$ids[] = 'grant:' . (string) $row['uid'];
			}
		}
		return array(
			'count' => $count,
			'ids'   => $ids,
		);
	}

	/**
	 * Check record for the system status (H-29a).
	 *
	 * @param Database $db Database.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	public static function check( Database $db ): array {
		try {
			$open = self::open_items( $db );
		} catch ( Throwable $e ) {
			unset( $e );
			$open = array(
				'count' => 0,
				'ids'   => array(),
			);
		}
		if ( 0 === $open['count'] ) {
			return array(
				'id'     => self::CHECK_ID,
				'status' => 'ok',
				'label'  => 'keep_and_flag' === self::policy()
					? __( 'No access grants of missing sources to review.', 'course-captain' )
					: __( 'Access of missing sources is kept without review list.', 'course-captain' ),
				'hint'   => '',
				'ids'    => array(),
			);
		}
		return array(
			'id'     => self::CHECK_ID,
			'status' => 'warn',
			/* translators: %d: number of access grants */
			'label'  => sprintf( _n( '%d access grant comes from a source that no longer delivers; access was kept.', '%d access grants come from a source that no longer delivers; access was kept.', $open['count'], 'course-captain' ), $open['count'] ),
			'hint'   => __( 'Check the source. Withdraw access manually only where it is no longer justified; nothing is withdrawn automatically.', 'course-captain' ),
			'ids'    => $open['ids'],
		);
	}

	/**
	 * Grants of a source reference.
	 *
	 * @param Database $db          Database.
	 * @param string   $source_type Source type.
	 * @param string   $reference   Source reference.
	 * @return list<array<string, mixed>>
	 */
	private static function grants( Database $db, string $source_type, string $reference ): array {
		return array_values( $db->rows( $db->prepare( 'SELECT id, user_id, status, meta FROM `' . $db->table( 'ccap_grant' ) . '` WHERE source_type = %s AND source_id = %s ORDER BY id', $source_type, $reference ) ) );
	}

	/**
	 * Decoded grant meta.
	 *
	 * @param array<string, mixed> $row Grant row.
	 * @return array<string, mixed>
	 */
	private static function meta( array $row ): array {
		$meta = is_string( $row['meta'] ?? null ) ? json_decode( (string) $row['meta'], true ) : null;
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Writes the grant meta (status and validity untouched).
	 *
	 * @param Database             $db   Database.
	 * @param int                  $id   Grant ID.
	 * @param array<string, mixed> $meta Meta.
	 * @return void
	 */
	private static function store( Database $db, int $id, array $meta ): void {
		$value = array() === $meta ? null : (string) wp_json_encode( $meta );
		$table = $db->table( 'ccap_grant' );
		$sql   = null === $value
			? $db->prepare( "UPDATE `{$table}` SET meta = NULL, row_version = row_version + 1, updated_at = %s WHERE id = %d", $db->now(), $id )
			: $db->prepare( "UPDATE `{$table}` SET meta = %s, row_version = row_version + 1, updated_at = %s WHERE id = %d", $value, $db->now(), $id );
		$db->execute( $sql );
	}

	/**
	 * Audit entry for one grant.
	 *
	 * @param Database             $db          Database.
	 * @param Actor                $actor       Actor.
	 * @param string               $action      Audit action.
	 * @param array<string, mixed> $row         Grant row.
	 * @param string               $source_type Source type.
	 * @param string               $reference   Source reference.
	 * @return void
	 */
	private static function audit( Database $db, Actor $actor, string $action, array $row, string $source_type, string $reference ): void {
		AuditLog::write(
			$db,
			$actor,
			$action,
			'grant',
			(string) $row['id'],
			null,
			array(
				'source_type'      => $source_type,
				'source_reference' => $reference,
				'policy'           => self::policy(),
			),
			null === $row['user_id'] ? null : (int) $row['user_id']
		);
	}
}
