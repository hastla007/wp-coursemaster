<?php
/**
 * Rights overrides and bulk release of imported content (R0-10 §2, E-18).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * The rights status of a question or flashcard lives on its revision
 * (`ccap_question_revision.rights_status`, set by the import). A local
 * override (`ccap_override`, field `rights_status`) wins over it and
 * survives every re-import (R0-10 §3.7). Every override needs a reason
 * and is audited (`rights_override_set`).
 *
 * Bulk release: all imported `unknown` items of a package's source that
 * are not archived become `licensed` as overrides; `restricted` and other
 * states stay. The caller confirms the count shown in the preview; a
 * different or missing confirmation changes nothing (`rights_bulk_release`).
 */
final class Rights {

	public const STATUSES     = array( 'own', 'licensed', 'public', 'review', 'unknown', 'restricted' );
	public const BLOCKING     = array( 'unknown', 'restricted' );
	public const OBJECT_TYPES = array( 'question', 'flashcard' );
	public const FIELD        = 'rights_status';

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current() );
	}

	/**
	 * Effective rights status: override, else the current revision's value.
	 *
	 * @param string $type      `question` or `flashcard`.
	 * @param int    $object_id Question ID.
	 * @return string
	 */
	public function effective( string $type, int $object_id ): string {
		$override = $this->db->scalar( $this->db->prepare( 'SELECT value FROM `' . $this->db->table( 'ccap_override' ) . '` WHERE object_type = %s AND object_id = %d AND field = %s', $type, $object_id, self::FIELD ) );
		if ( null !== $override ) {
			return (string) $override;
		}
		return (string) $this->db->scalar( $this->db->prepare( 'SELECT r.rights_status FROM `' . $this->db->table( 'ccap_question' ) . '` q JOIN `' . $this->db->table( 'ccap_question_revision' ) . '` r ON r.id = q.current_revision_id WHERE q.id = %d', $object_id ) );
	}

	/**
	 * Set a rights override with a mandatory reason (GM-123).
	 *
	 * @param Actor  $actor     Actor with `ccap_import_content`.
	 * @param string $type      `question` or `flashcard`.
	 * @param int    $object_id Question ID.
	 * @param string $status    New status.
	 * @param string $reason    Reason (required).
	 * @return array{before: string, after: string}
	 * @throws CourseException 403 without the right, 404 unknown object, 422 invalid status or missing reason.
	 */
	public function override( Actor $actor, string $type, int $object_id, string $status, string $reason ): array {
		$this->authorize( $actor );
		$reason = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_validation_failed', 'reason', 422 );
		}
		if ( ! in_array( $type, self::OBJECT_TYPES, true ) || ! in_array( $status, self::STATUSES, true ) ) {
			throw new CourseException( 'ccap_validation_failed', 'status', 422 );
		}
		if ( null === $this->db->scalar( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $object_id ) ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown object.', 404 );
		}
		$before = $this->effective( $type, $object_id );
		$this->db->transaction(
			function () use ( $actor, $type, $object_id, $status, $reason, $before ): void {
				$this->write( $actor, $type, $object_id, $status, $reason );
				AuditLog::write(
					$this->db,
					$actor,
					'rights_override_set',
					$type,
					(string) $object_id,
					$reason,
					array(
						'field'  => self::FIELD,
						'before' => $before,
						'after'  => $status,
					)
				);
			}
		);
		return array(
			'before' => $before,
			'after'  => $status,
		);
	}

	/**
	 * Dry run of the bulk release for a package (GM-127): the items that
	 * would change and the ones that stay.
	 *
	 * @param Actor $actor Actor with `ccap_import_content`.
	 * @param int   $batch Published or superseded import batch.
	 * @return array{count: int, items: list<array{type: string, object_id: int, source_id: string}>, kept: array{restricted: int, archived: int, other: int}}
	 * @throws CourseException 403/404/409.
	 */
	public function release_preview( Actor $actor, int $batch ): array {
		$this->authorize( $actor );
		$system = $this->system( $batch );
		$items  = array();
		$kept   = array(
			'restricted' => 0,
			'archived'   => 0,
			'other'      => 0,
		);
		$map    = $this->db->table( 'ccap_source_map' );
		$q      = $this->db->table( 'ccap_question' );
		$rev    = $this->db->table( 'ccap_question_revision' );
		$ovr    = $this->db->table( 'ccap_override' );
		$rows   = $this->db->rows(
			$this->db->prepare(
				"SELECT m.object_type, m.object_id, m.source_id, q.status, COALESCE(o.value, r.rights_status) AS rights FROM `{$map}` m JOIN `{$q}` q ON q.id = m.object_id LEFT JOIN `{$rev}` r ON r.id = q.current_revision_id LEFT JOIN `{$ovr}` o ON o.object_type = m.object_type AND o.object_id = m.object_id AND o.field = %s WHERE m.source_system = %s AND m.object_type IN ('question', 'flashcard') ORDER BY m.object_type, m.source_id",
				self::FIELD,
				$system
			)
		);
		foreach ( $rows as $row ) {
			if ( 'unknown' !== $row['rights'] ) {
				if ( 'restricted' === $row['rights'] ) {
					++$kept['restricted'];
				}
				continue;
			}
			if ( 'archived' === $row['status'] ) {
				++$kept['archived'];
				continue;
			}
			$items[] = array(
				'type'      => (string) $row['object_type'],
				'object_id' => (int) $row['object_id'],
				'source_id' => (string) $row['source_id'],
			);
		}
		return array(
			'count' => count( $items ),
			'items' => $items,
			'kept'  => $kept,
		);
	}

	/**
	 * Release all `unknown` items of a package as `licensed` overrides.
	 *
	 * @param Actor    $actor     Actor with `ccap_import_content`.
	 * @param int      $batch     Import batch.
	 * @param string   $reason    Reason (required).
	 * @param int|null $confirmed Count the person confirmed (must equal the preview).
	 * @return array{released: int}
	 * @throws CourseException 422 without reason, 409 without or with a stale confirmation.
	 */
	public function release( Actor $actor, int $batch, string $reason, ?int $confirmed ): array {
		$preview = $this->release_preview( $actor, $batch );
		$reason  = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_validation_failed', 'reason', 422 );
		}
		if ( null === $confirmed || $confirmed !== $preview['count'] ) {
			throw new CourseException( 'ccap_confirmation_required', 'Confirm the number of items.', 409, array( 'count' => $preview['count'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
		}
		$this->db->transaction(
			function () use ( $actor, $batch, $reason, $preview ): void {
				foreach ( $preview['items'] as $item ) {
					$this->write( $actor, $item['type'], $item['object_id'], 'licensed', $reason );
				}
				AuditLog::write(
					$this->db,
					$actor,
					'rights_bulk_release',
					'import_batch',
					(string) $batch,
					$reason,
					array(
						'count'   => $preview['count'],
						'objects' => array_map( static fn( array $i ): string => $i['type'] . ':' . $i['source_id'], array_slice( $preview['items'], 0, 100 ) ),
						'kept'    => $preview['kept'],
					)
				);
			}
		);
		return array( 'released' => $preview['count'] );
	}

	/**
	 * Upsert the override row.
	 *
	 * @param Actor  $actor     Actor.
	 * @param string $type      Object type.
	 * @param int    $object_id Object.
	 * @param string $status    Status.
	 * @param string $reason    Reason.
	 * @return void
	 */
	private function write( Actor $actor, string $type, int $object_id, string $status, string $reason ): void {
		$this->db->execute(
			$this->db->prepare(
				'INSERT INTO `' . $this->db->table( 'ccap_override' ) . '` (object_type, object_id, field, value, reason, user_id, created_at) VALUES (%s, %d, %s, %s, %s, %d, %s) ON DUPLICATE KEY UPDATE value = VALUES(value), reason = VALUES(reason), user_id = VALUES(user_id), created_at = VALUES(created_at)',
				$type,
				$object_id,
				self::FIELD,
				$status,
				$reason,
				$actor->user_id,
				$this->db->now()
			)
		);
	}

	/**
	 * Source system of a published batch.
	 *
	 * @param int $batch Batch.
	 * @return string
	 * @throws CourseException 404 unknown, 409 not published.
	 */
	private function system( int $batch ): string {
		$row = $this->db->row( $this->db->prepare( 'SELECT status, manifest FROM `' . $this->db->table( 'ccap_import_batch' ) . '` WHERE id = %d', $batch ) );
		if ( null === $row ) {
			throw new CourseException( 'ccap_not_found', 'Unknown import.', 404 );
		}
		if ( ! in_array( (string) $row['status'], array( 'published', 'superseded' ), true ) ) {
			throw new CourseException( 'ccap_import_state', esc_html( (string) $row['status'] ), 409 );
		}
		$manifest = (array) json_decode( (string) $row['manifest'], true );
		return (string) ( $manifest['source']['system'] ?? '' );
	}

	/**
	 * Import right (`ccap_import_content`).
	 *
	 * @param Actor $actor Actor.
	 * @return void
	 * @throws CourseException 403.
	 */
	private function authorize( Actor $actor ): void {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_import_content' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change rights.', 403 );
		}
	}
}
