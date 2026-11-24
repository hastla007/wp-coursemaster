<?php
/**
 * Rights overrides and bulk release of imported content (R0-10 §2, E-18).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Access\RightsGate;
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
	public const COURSE_META  = '_ccap_rights';

	/**
	 * Rights fields of a course (R0-10 §1.5 `rights`, §2.1 post meta).
	 */
	public const COURSE_FIELDS = array( 'status', 'owner', 'license', 'license_url', 'attribution', 'source_url', 'valid_until', 'note' );

	/**
	 * Licences that require an attribution (R0-22 VAL-105 licence registry):
	 * Creative Commons BY family (CC-BY, CC-BY-SA, CC-BY-NC …), Open Data
	 * Commons Attribution and Datenlizenz Deutschland – Namensnennung.
	 */
	public const ATTRIBUTION_LICENSES = '/^(cc[\s_-]*by|odc[\s_-]*by|dl[\s_-]*de[\s\/_-]*by)/i';

	/**
	 * Own-content verdict per source system (cache of {@see own_system()}).
	 *
	 * @var array<string, bool>
	 */
	private array $own_systems = array();

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
				RightsGate::changed( RightsGate::courses_of_questions( array( $object_id ) ) );
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
				RightsGate::changed( RightsGate::courses_of_questions( array_column( $preview['items'], 'object_id' ) ) );
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
	 * Questions and flashcards in the pools of a course's modules with
	 * their effective rights status and origin (UI-F8 "Rights & release").
	 *
	 * @param int $course_id Course.
	 * @return list<array{type: string, object_id: int, status: string, rights: string, foreign: bool, source_system: string}>
	 */
	public function course_items( int $course_id ): array {
		$modules = array_map( static fn( array $l ): int => (int) $l['module_id'], \CourseCaptain\Course\CourseStructure::current()->modules( $course_id ) );
		if ( array() === $modules ) {
			return array();
		}
		$mq   = $this->db->table( 'ccap_module_question' );
		$q    = $this->db->table( 'ccap_question' );
		$rev  = $this->db->table( 'ccap_question_revision' );
		$map  = $this->db->table( 'ccap_source_map' );
		$ovr  = $this->db->table( 'ccap_override' );
		$rows = $this->db->rows(
			$this->db->prepare(
				"SELECT DISTINCT q.id, q.status, r.question_type, r.rights_status, m.object_type, m.source_system, o.value AS override_value FROM `{$mq}` mq JOIN `{$q}` q ON q.id = mq.question_id LEFT JOIN `{$rev}` r ON r.id = q.current_revision_id LEFT JOIN `{$map}` m ON m.object_id = q.id AND m.object_type IN ('question', 'flashcard') LEFT JOIN `{$ovr}` o ON o.object_id = q.id AND o.object_type IN ('question', 'flashcard') AND o.field = %s WHERE mq.module_id IN (" . implode( ',', $modules ) . ') ORDER BY q.id', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list and table names.
				self::FIELD
			)
		);
		$out  = array();
		foreach ( $rows as $row ) {
			$id = (int) $row['id'];
			if ( isset( $out[ $id ] ) ) {
				continue;
			}
			$system     = (string) ( $row['source_system'] ?? '' );
			$out[ $id ] = array(
				'type'          => null !== $row['object_type'] ? (string) $row['object_type'] : ( 'flashcard' === $row['question_type'] ? 'flashcard' : 'question' ),
				'object_id'     => $id,
				'status'        => (string) $row['status'],
				'rights'        => (string) ( $row['override_value'] ?? $row['rights_status'] ?? 'own' ),
				'foreign'       => ! $this->own_system( $system ),
				'source_system' => $system,
			);
		}
		return array_values( $out );
	}

	/**
	 * Release status of a course: counts per rights status and origin,
	 * items hidden from learners (foreign and blocking, R0-10 §2.2) and
	 * items still in review (VAL-100 / VAL-104 wording of R0-22).
	 *
	 * @param int $course_id Course.
	 * @return array{counts: array<string, array{own: int, foreign: int}>, blocked: int, review: int, total: int}
	 */
	public function course_summary( int $course_id ): array {
		$counts = array_fill_keys(
			self::STATUSES,
			array(
				'own'     => 0,
				'foreign' => 0,
			)
		);
		$out    = array(
			'counts'  => $counts,
			'blocked' => 0,
			'review'  => 0,
			'total'   => 0,
		);
		foreach ( $this->course_items( $course_id ) as $item ) {
			if ( 'archived' === $item['status'] ) {
				continue;
			}
			$status = in_array( $item['rights'], self::STATUSES, true ) ? $item['rights'] : 'unknown';
			++$out['counts'][ $status ][ $item['foreign'] ? 'foreign' : 'own' ];
			++$out['total'];
			if ( $item['foreign'] && in_array( $status, self::BLOCKING, true ) ) {
				++$out['blocked'];
			}
			if ( 'review' === $status ) {
				++$out['review'];
			}
		}
		return $out;
	}

	/**
	 * Dry run of the release for one course: foreign, not archived items
	 * with `unknown` rights would become `licensed` overrides.
	 *
	 * @param Actor $actor     Actor with `ccap_release_rights` and course rights.
	 * @param int   $course_id Course.
	 * @return array{count: int, items: list<array{type: string, object_id: int}>, kept: array{restricted: int, archived: int, other: int}}
	 * @throws CourseException 403.
	 */
	public function course_release_preview( Actor $actor, int $course_id ): array {
		$this->authorize_course( $actor, $course_id );
		$items = array();
		$kept  = array(
			'restricted' => 0,
			'archived'   => 0,
			'other'      => 0,
		);
		foreach ( $this->course_items( $course_id ) as $item ) {
			if ( ! $item['foreign'] || 'unknown' !== $item['rights'] ) {
				if ( 'restricted' === $item['rights'] ) {
					++$kept['restricted'];
				}
				continue;
			}
			if ( 'archived' === $item['status'] ) {
				++$kept['archived'];
				continue;
			}
			$items[] = array(
				'type'      => $item['type'],
				'object_id' => $item['object_id'],
			);
		}
		return array(
			'count' => count( $items ),
			'items' => $items,
			'kept'  => $kept,
		);
	}

	/**
	 * Release the unclear rights of one course (same rules as the package
	 * release: reason and the confirmed count of the preview are required).
	 *
	 * @param Actor    $actor     Actor with `ccap_release_rights` and course rights.
	 * @param int      $course_id Course.
	 * @param string   $reason    Reason.
	 * @param int|null $confirmed Confirmed count.
	 * @return array{released: int}
	 * @throws CourseException 403, 422, 409.
	 */
	public function course_release( Actor $actor, int $course_id, string $reason, ?int $confirmed ): array {
		$preview = $this->course_release_preview( $actor, $course_id );
		$reason  = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_validation_failed', 'reason', 422 );
		}
		if ( null === $confirmed || $confirmed !== $preview['count'] ) {
			throw new CourseException( 'ccap_confirmation_required', 'Confirm the number of items.', 409, array( 'count' => $preview['count'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
		}
		$this->db->transaction(
			function () use ( $actor, $course_id, $reason, $preview ): void {
				foreach ( $preview['items'] as $item ) {
					$this->write( $actor, $item['type'], $item['object_id'], 'licensed', $reason );
				}
				RightsGate::changed( array_merge( array( $course_id ), RightsGate::courses_of_questions( array_column( $preview['items'], 'object_id' ) ) ) );
				AuditLog::write(
					$this->db,
					$actor,
					'rights_course_release',
					'course',
					(string) $course_id,
					$reason,
					array(
						'count'   => $preview['count'],
						'objects' => array_map( static fn( array $i ): string => $i['type'] . ':' . $i['object_id'], array_slice( $preview['items'], 0, 100 ) ),
						'kept'    => $preview['kept'],
					)
				);
			}
		);
		return array( 'released' => $preview['count'] );
	}

	/**
	 * Rights metadata of a course (post meta `_ccap_rights`, R0-10 §2.1).
	 *
	 * @param int $course_id Course.
	 * @return array<string, string> Keys of {@see COURSE_FIELDS}; empty when none are stored.
	 */
	public static function course_meta( int $course_id ): array {
		$raw = json_decode( (string) get_post_meta( $course_id, self::COURSE_META, true ), true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( self::COURSE_FIELDS as $field ) {
			if ( isset( $raw[ $field ] ) && is_scalar( $raw[ $field ] ) ) {
				$out[ $field ] = (string) $raw[ $field ];
			}
		}
		return $out;
	}

	/**
	 * Save the rights metadata of a course (audited with before/after).
	 *
	 * @param Actor                $actor     Actor with `ccap_release_rights` and course rights.
	 * @param int                  $course_id Course.
	 * @param array<string, mixed> $input     Fields of {@see COURSE_FIELDS}.
	 * @param string               $reason    Reason (required).
	 * @return array<string, string> Field errors (field → code); empty = saved.
	 * @throws CourseException 403.
	 */
	public function set_course_meta( Actor $actor, int $course_id, array $input, string $reason ): array {
		$this->authorize_course( $actor, $course_id );
		list( $value, $errors ) = self::clean_meta( $input );
		$reason                 = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			$errors['reason'] = 'required';
		}
		if ( array() !== $errors ) {
			return $errors;
		}
		$before = self::course_meta( $course_id );
		if ( array() === $value ) {
			delete_post_meta( $course_id, self::COURSE_META );
		} else {
			update_post_meta( $course_id, self::COURSE_META, wp_slash( (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) );
		}
		RightsGate::changed( array( $course_id ) );
		AuditLog::write(
			$this->db,
			$actor,
			'course_rights_saved',
			'course',
			(string) $course_id,
			$reason,
			array(
				'before' => $before,
				'after'  => $value,
			)
		);
		return array();
	}

	/**
	 * Sanitised rights metadata and field errors (form and import share the rules).
	 *
	 * @param array<string, mixed> $input Fields of {@see COURSE_FIELDS}.
	 * @return array{0: array<string, string>, 1: array<string, string>} Value, errors (field → code).
	 */
	public static function clean_meta( array $input ): array {
		$errors = array();
		$value  = array();
		foreach ( self::COURSE_FIELDS as $field ) {
			$raw = is_scalar( $input[ $field ] ?? null ) ? trim( (string) $input[ $field ] ) : '';
			if ( '' === $raw ) {
				continue;
			}
			switch ( $field ) {
				case 'status':
					if ( ! in_array( $raw, self::STATUSES, true ) ) {
						$errors[ $field ] = 'status';
					}
					break;
				case 'license_url':
				case 'source_url':
					$raw = esc_url_raw( $raw, array( 'https' ) );
					if ( '' === $raw ) {
						$errors[ $field ] = 'url';
					}
					break;
				case 'valid_until':
					$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $raw );
					if ( false === $date || $date->format( 'Y-m-d' ) !== $raw ) {
						$errors[ $field ] = 'date';
					}
					break;
				case 'note':
					$raw = mb_substr( sanitize_textarea_field( $raw ), 0, 1000 );
					break;
				default:
					$raw = mb_substr( sanitize_text_field( $raw ), 0, 255 );
			}
			$value[ $field ] = $raw;
		}
		return array( $value, $errors );
	}

	/**
	 * Store the rights of an imported course or module as `_ccap_rights`
	 * (R0-10 §2.1). Rights saved locally on the "Rights & release" page
	 * win and survive every re-import (like overrides); imported metadata
	 * is marked with `_source = import` and replaced or removed by the
	 * next import.
	 *
	 * @param int                       $post_id Course or module.
	 * @param array<string, mixed>|null $rights  Resolved rights (`status`, `owner`, `license` fields) or null for none.
	 * @return bool Whether the meta was written or removed.
	 */
	public static function import_meta( int $post_id, ?array $rights ): bool {
		$raw = json_decode( (string) get_post_meta( $post_id, self::COURSE_META, true ), true );
		if ( is_array( $raw ) && array() !== $raw && 'import' !== ( $raw['_source'] ?? '' ) ) {
			return false;
		}
		if ( null === $rights ) {
			if ( is_array( $raw ) ) {
				delete_post_meta( $post_id, self::COURSE_META );
				RightsGate::changed( RightsGate::courses_of_post( $post_id ) );
				return true;
			}
			return false;
		}
		list( $value ) = self::clean_meta( array( 'status' => $rights['status'] ?? '', 'owner' => $rights['owner'] ?? '' ) + (array) ( $rights['license'] ?? array() ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short input.
		if ( ! isset( $value['status'] ) ) {
			$value['status'] = 'unknown';
		}
		$value['_source'] = 'import';
		$json             = (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( is_array( $raw ) && $raw === $value ) {
			return false;
		}
		update_post_meta( $post_id, self::COURSE_META, wp_slash( $json ) );
		RightsGate::changed( RightsGate::courses_of_post( $post_id ) );
		return true;
	}

	/**
	 * Shape check of a package `rights` object (R0-10 §1.5 `common.rights`):
	 * object with a valid `status`, only known keys, https URLs, ISO date.
	 *
	 * @param mixed $rights Rights value of a record or `default_rights`.
	 * @return bool
	 */
	public static function valid_package_rights( $rights ): bool {
		if ( ! is_array( $rights ) || ! in_array( (string) ( $rights['status'] ?? '' ), self::STATUSES, true ) ) {
			return false;
		}
		if ( array() !== array_diff( array_keys( $rights ), self::COURSE_FIELDS ) ) {
			return false;
		}
		foreach ( $rights as $key => $value ) {
			if ( 'status' === $key || null === $value ) {
				continue;
			}
			if ( ! is_string( $value ) ) {
				return false;
			}
			$max = array(
				'owner'   => 255,
				'license' => 64,
			)[ $key ] ?? 0;
			if ( ( $max > 0 && mb_strlen( $value ) > $max )
				|| ( in_array( $key, array( 'license_url', 'source_url' ), true ) && ! str_starts_with( $value, 'https://' ) )
				|| ( 'valid_until' === $key && ! self::iso_date( $value ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a string is a real ISO date `YYYY-MM-DD`.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function iso_date( string $value ): bool {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return false !== $date && $date->format( 'Y-m-d' ) === $value;
	}

	/**
	 * Origin of an imported course (fallback when no rights metadata is
	 * stored): source system, publisher and `default_rights` of the newest
	 * published package of that source.
	 *
	 * @param int $course_id Course.
	 * @return array{system: string, publisher: string, package: string, default_rights: array<string, mixed>}|null Null for local courses.
	 */
	public function package_rights( int $course_id ): ?array {
		$system = (string) $this->db->scalar( $this->db->prepare( 'SELECT source_system FROM `' . $this->db->table( 'ccap_source_map' ) . "` WHERE object_type = 'course' AND object_id = %d LIMIT 1", $course_id ) );
		if ( '' === $system || 'local' === $system ) {
			return null;
		}
		$out     = array(
			'system'         => $system,
			'publisher'      => '',
			'package'        => '',
			'default_rights' => array(),
		);
		$batches = $this->db->table( 'ccap_import_batch' );
		foreach ( $this->db->rows( "SELECT package_id, manifest FROM `{$batches}` WHERE status IN ('published', 'superseded') ORDER BY id DESC LIMIT 200" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
			$manifest = (array) json_decode( (string) $row['manifest'], true );
			if ( ( $manifest['source']['system'] ?? '' ) !== $system ) {
				continue;
			}
			$out['package']        = (string) $row['package_id'];
			$out['publisher']      = (string) ( $manifest['publisher']['name'] ?? '' );
			$out['default_rights'] = is_array( $manifest['default_rights'] ?? null ) ? $manifest['default_rights'] : array();
			break;
		}
		return $out;
	}

	/**
	 * Whether a manifest describes this site's own content (R0-10 §2.2):
	 * source system `local`, or `source.exported_from_site` is the
	 * installation UUID of this site (re-import of an own export).
	 *
	 * @param array<string, mixed> $manifest Manifest.
	 * @return bool
	 */
	public static function own_manifest( array $manifest ): bool {
		$site = (string) get_option( 'course_captain_installation_uuid', '' );
		$from = (string) ( $manifest['source']['exported_from_site'] ?? '' );
		return 'local' === ( $manifest['source']['system'] ?? '' ) || ( '' !== $site && $site === $from );
	}

	/**
	 * Whether content of a source system is own content: locally created
	 * (`local` or no source) or imported from a package this site exported
	 * (newest package of that source system, {@see own_manifest()}).
	 *
	 * @param string $system Source system.
	 * @return bool
	 */
	public function own_system( string $system ): bool {
		if ( '' === $system || 'local' === $system ) {
			return true;
		}
		if ( ! isset( $this->own_systems[ $system ] ) ) {
			$own     = false;
			$batches = $this->db->table( 'ccap_import_batch' );
			foreach ( $this->db->rows( "SELECT manifest FROM `{$batches}` WHERE manifest IS NOT NULL ORDER BY id DESC LIMIT 200" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
				$manifest = (array) json_decode( (string) $row['manifest'], true );
				if ( ( $manifest['source']['system'] ?? '' ) === $system ) {
					$own = self::own_manifest( $manifest );
					break;
				}
			}
			$this->own_systems[ $system ] = $own;
		}
		return $this->own_systems[ $system ];
	}

	/**
	 * Whether a licence has expired: `valid_until` (ISO date) lies before today.
	 *
	 * @param array<string, mixed> $license Licence fields (`valid_until`).
	 * @param string               $today   Today as `Y-m-d`.
	 * @return bool
	 */
	public static function expired( array $license, string $today ): bool {
		$until = is_scalar( $license['valid_until'] ?? null ) ? (string) $license['valid_until'] : '';
		return '' !== $until && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until ) && $until < $today;
	}

	/**
	 * Why content is withheld from learners (R0-10 §2.2), or null when it
	 * may be delivered: only foreign content is withheld, with the status
	 * `unknown` or `restricted` (blocking list) or an expired `valid_until`
	 * (treated like `restricted`). Own content is never withheld (E-18).
	 *
	 * @param string               $status  Effective rights status.
	 * @param array<string, mixed> $license Licence fields (`valid_until`).
	 * @param bool                 $foreign Foreign per R0-10 §2.2.
	 * @param string               $today   Today as `Y-m-d`.
	 * @return string|null `unknown`, `restricted`, `expired` or null.
	 */
	public static function withheld( string $status, array $license, bool $foreign, string $today ): ?string {
		if ( ! $foreign ) {
			return null;
		}
		$status = in_array( $status, self::STATUSES, true ) ? $status : 'unknown';
		if ( in_array( $status, self::BLOCKING, true ) ) {
			return $status;
		}
		return self::expired( $license, $today ) ? 'expired' : null;
	}

	/**
	 * Whether a medium is foreign (R0-10 §2.2): media of a foreign source,
	 * and media of this site whose status the editors set to anything but
	 * `own` (e.g. a photo of a third party uploaded locally).
	 *
	 * @param string $system Source system.
	 * @param string $status Rights status.
	 * @return bool
	 */
	public function media_foreign( string $system, string $status ): bool {
		return 'own' !== $status || ! $this->own_system( $system );
	}

	/**
	 * Whether a licence name requires an attribution (VAL-105).
	 *
	 * @param string $license Licence name, e.g. `CC-BY-SA-4.0`.
	 * @return bool
	 */
	public static function requires_attribution( string $license ): bool {
		return 1 === preg_match( self::ATTRIBUTION_LICENSES, trim( $license ) );
	}

	/**
	 * Everything of a course that carries rights, for the publish validator
	 * (R0-22 VAL-100…105): the rights metadata of the course and its
	 * modules (`_ccap_rights`), the questions and flashcards of the module
	 * pools (effective status incl. overrides) and the media linked to the
	 * course, its modules, lessons and current question revisions.
	 *
	 * `required` = part of the mandatory path (the course itself, required
	 * modules and everything in them); `foreign` per R0-10 §2.2; `expired`
	 * = `valid_until` lies before today.
	 *
	 * @param int $course_id Course.
	 * @return list<array{type: string, id: int, title: string, where: string, status: string, foreign: bool, required: bool, expired: bool, license: string, attribution: string}>
	 */
	public function course_rights_items( int $course_id ): array {
		$today  = gmdate( 'Y-m-d', $this->db->time() );
		$out    = array();
		$system = function ( string $type, int $id ): string {
			return (string) $this->db->scalar( $this->db->prepare( 'SELECT source_system FROM `' . $this->db->table( 'ccap_source_map' ) . '` WHERE object_type = %s AND object_id = %d LIMIT 1', $type, $id ) );
		};
		$item   = static function ( string $type, int $id, string $title, string $where, string $status, bool $foreign, bool $required, array $license ) use ( $today ): array {
			return array(
				'type'        => $type,
				'id'          => $id,
				'title'       => $title,
				'where'       => $where,
				'status'      => in_array( $status, self::STATUSES, true ) ? $status : 'unknown',
				'foreign'     => $foreign,
				'required'    => $required,
				'expired'     => self::expired( $license, $today ),
				'license'     => is_scalar( $license['license'] ?? null ) ? (string) $license['license'] : '',
				'attribution' => is_scalar( $license['attribution'] ?? null ) ? trim( (string) $license['attribution'] ) : '',
			);
		};
		$media  = function ( string $object_type, int $object_id, string $where, bool $required ) use ( &$out, $item ): void {
			$rows = $this->db->rows( $this->db->prepare( 'SELECT m.id, m.source_system, m.package_path, m.storage_key, m.alt_text, m.rights_status, m.license_reference FROM `' . $this->db->table( 'ccap_content_media' ) . '` cm JOIN `' . $this->db->table( 'ccap_media' ) . "` m ON m.id = cm.media_id WHERE cm.object_type = %s AND cm.object_id = %d AND m.status = 'active' ORDER BY cm.sort_order, m.id", $object_type, $object_id ) );
			foreach ( $rows as $row ) {
				$id   = (int) $row['id'];
				$key  = 'media:' . $id;
				$name = basename( (string) ( $row['package_path'] ?? '' ) );
				$name = '' !== $name ? $name : basename( (string) ( $row['storage_key'] ?? '' ) );
				$name = '' !== $name ? $name : (string) ( $row['alt_text'] ?? '' );
				if ( isset( $out[ $key ] ) ) {
					$out[ $key ]['required'] = $out[ $key ]['required'] || $required;
					continue;
				}
				$license     = (array) json_decode( (string) ( $row['license_reference'] ?? '' ), true );
				$out[ $key ] = $item( 'media', $id, $name, $where, (string) $row['rights_status'], $this->media_foreign( (string) $row['source_system'], (string) $row['rights_status'] ), $required, $license );
			}
		};
		$title  = (string) get_the_title( $course_id );
		$meta   = self::course_meta( $course_id );
		if ( isset( $meta['status'] ) ) {
			$out[ 'course:' . $course_id ] = $item( 'course', $course_id, $title, $title, $meta['status'], ! $this->own_system( $system( 'course', $course_id ) ), true, $meta );
		}
		$media( 'course', $course_id, $title, true );
		$q   = $this->db->table( 'ccap_question' );
		$rev = $this->db->table( 'ccap_question_revision' );
		$ovr = $this->db->table( 'ccap_override' );
		$mq  = $this->db->table( 'ccap_module_question' );
		foreach ( \CourseCaptain\Course\CourseStructure::current()->modules( $course_id ) as $link ) {
			$module   = (int) $link['module_id'];
			$required = (bool) $link['required'];
			$mtitle   = (string) get_the_title( $module );
			$mmeta    = self::course_meta( $module );
			if ( isset( $mmeta['status'] ) ) {
				$key         = 'module:' . $module;
				$out[ $key ] = isset( $out[ $key ] ) ? array_merge( $out[ $key ], array( 'required' => $out[ $key ]['required'] || $required ) ) : $item( 'module', $module, $mtitle, $mtitle, $mmeta['status'], ! $this->own_system( $system( 'module', $module ) ), $required, $mmeta );
			}
			$media( 'module', $module, $mtitle, $required );
			foreach ( \CourseCaptain\Course\CourseStructure::current()->lessons( $module ) as $lesson ) {
				if ( 'publish' !== $lesson->post_status ) {
					continue;
				}
				// Lesson rights (`_ccap_rights`, R0-10 §2.1): imported from packages, checked like course and module rights.
				$lmeta = self::course_meta( (int) $lesson->ID );
				if ( isset( $lmeta['status'] ) ) {
					$key         = 'lesson:' . (int) $lesson->ID;
					$out[ $key ] = isset( $out[ $key ] ) ? array_merge( $out[ $key ], array( 'required' => $out[ $key ]['required'] || $required ) ) : $item( 'lesson', (int) $lesson->ID, $lesson->post_title, $mtitle, $lmeta['status'], ! $this->own_system( $system( 'lesson', (int) $lesson->ID ) ), $required, $lmeta );
				}
				$media( 'lesson', (int) $lesson->ID, $lesson->post_title, $required );
			}
			$rows = $this->db->rows(
				$this->db->prepare(
					"SELECT q.id, q.current_revision_id, r.question_type, r.question_text, r.rights_status, r.license_reference, o.value AS override_value, (SELECT sm.source_system FROM `{$this->db->table( 'ccap_source_map' )}` sm WHERE sm.object_id = q.id AND sm.object_type IN ('question', 'flashcard') LIMIT 1) AS source_system FROM `{$mq}` mq JOIN `{$q}` q ON q.id = mq.question_id JOIN `{$rev}` r ON r.id = q.current_revision_id LEFT JOIN `{$ovr}` o ON o.object_id = q.id AND o.object_type IN ('question', 'flashcard') AND o.field = %s WHERE mq.module_id = %d AND q.status <> 'archived' ORDER BY q.id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names.
					self::FIELD,
					$module
				)
			);
			foreach ( $rows as $row ) {
				$id  = (int) $row['id'];
				$key = 'question:' . $id;
				if ( isset( $out[ $key ] ) ) {
					$out[ $key ]['required'] = $out[ $key ]['required'] || $required;
					continue;
				}
				$type        = 'flashcard' === $row['question_type'] ? 'flashcard' : 'question';
				$out[ $key ] = $item( $type, $id, mb_substr( wp_strip_all_tags( (string) $row['question_text'] ), 0, 60 ), $mtitle, (string) ( $row['override_value'] ?? $row['rights_status'] ), ! $this->own_system( (string) ( $row['source_system'] ?? '' ) ), $required, (array) json_decode( (string) ( $row['license_reference'] ?? '' ), true ) );
				$media( 'question_revision', (int) $row['current_revision_id'], $mtitle, $required );
			}
		}
		return array_values( $out );
	}

	/**
	 * Release right on a course (`ccap_release_rights`, M-04 course edit).
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @return void
	 * @throws CourseException 403.
	 */
	private function authorize_course( Actor $actor, int $course_id ): void {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_release_rights' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to release rights.', 403 );
		}
		CourseException::unless_allowed( \CourseCaptain\Policy\PolicyService::instance()->decide( $actor, \CourseCaptain\Policy\PolicyService::EDIT_COURSE, array( $course_id ) ), 'Not allowed to edit this course.' );
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
