<?php
/**
 * Certificate snapshot `format_version = 1` (R0-29 §2.4, R0-08 §7.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Certificate;

use CourseCaptain\Course\CourseException;

defined( 'ABSPATH' ) || exit;

/**
 * Pure functions, no WordPress: canonical JSON (keys sorted recursively,
 * strings UTF-8 NFC, no escaped slashes/Unicode), `snapshot_hash =
 * sha256:` over the canonical JSON without `snapshot_hash`, the printed
 * check code and the schema validation (unknown or missing fields →
 * `ccap_validation_failed`, rule 1). Every printed value is resolved in the
 * snapshot (CR-2).
 */
final class CertificateSnapshot {

	public const FORMAT_VERSION = 1;

	/**
	 * Field schema: name => [required, type, sub-schema]. Types: `int`,
	 * `string`, `bool`, `?string`, `?int`, `?bool`, `object`, `?object`, `list`.
	 *
	 * @return array<string, array{0: bool, 1: string, 2?: array<string, array{0: bool, 1: string, 2?: array<string, mixed>}>}>
	 */
	public static function schema(): array {
		return array(
			'format_version'  => array( true, 'int' ),
			'certificate_uid' => array( true, 'string' ),
			'revision_no'     => array( true, 'int' ),
			'revision_kind'   => array( true, 'string' ),
			'display_id'      => array( true, 'string' ),
			'template'        => array(
				true,
				'object',
				array(
					'template_uid'    => array( true, 'string' ),
					'version'         => array( true, 'int' ),
					'kind'            => array( true, 'string' ),
					'definition_hash' => array( true, 'string' ),
				),
			),
			'locale'          => array(
				true,
				'object',
				array(
					'content' => array( true, 'string' ),
					'ui'      => array( true, 'string' ),
				),
			),
			// SET-CC-08 (optional, so older snapshots stay valid): paper format and language mode at issuance.
			'layout'          => array(
				false,
				'object',
				array(
					'paper'    => array( true, 'string' ),
					'language' => array( true, 'string' ),
				),
			),
			'issued_at'       => array( true, 'string' ),
			'issued_by'       => array(
				true,
				'object',
				array(
					'type' => array( true, 'string' ),
					'role' => array( true, 'string' ),
				),
			),
			'issuer'          => array(
				true,
				'object',
				array(
					'name'            => array( true, 'string' ),
					'logo'            => array(
						false,
						'object',
						array(
							'attachment_id' => array( true, 'int' ),
							'sha256'        => array( true, '?string' ),
							'alt'           => array( true, 'string' ),
						),
					),
					'signature_label' => array( false, 'string' ),
				),
			),
			'holder'          => array(
				true,
				'object',
				array(
					'display_name' => array( true, 'string' ),
				),
			),
			'course'          => array(
				true,
				'object',
				array(
					'course_uid'         => array( true, 'string' ),
					'title'              => array( true, 'string' ),
					'course_revision_id' => array( true, 'int' ),
				),
			),
			'evidence'        => array(
				true,
				'object',
				array(
					'type'              => array( true, 'string' ),
					'ref_uuid'          => array( true, 'string' ),
					'points'            => array( true, '?int' ),
					'max_points'        => array( true, '?int' ),
					'passed'            => array( true, 'bool' ),
					'evaluated_at'      => array( true, 'string' ),
					'evaluation_no'     => array( true, 'int' ),
					'rule_uid'          => array( true, '?string' ),
					'rule_version'      => array( true, '?string' ),
					'evidence_eligible' => array( true, 'bool' ),
				),
			),
			'objectives'      => array( false, 'list' ),
			'valid_until'     => array( true, '?string' ),
			'fields'          => array( false, 'list' ),
			'assets'          => array( false, 'list' ),
			'texts'           => array(
				true,
				'object',
				array(
					'heading' => array( true, 'string' ),
					'body'    => array( true, 'string' ),
					'footer'  => array( true, 'string' ),
				),
			),
			'verify'          => array(
				true,
				'object',
				array(
					'path_at_issue' => array( true, 'string' ),
				),
			),
			'snapshot_hash'   => array( false, 'string' ),
		);
	}

	/**
	 * Validate against the schema (rule 1).
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return void
	 * @throws CourseException `ccap_validation_failed` naming the first bad field.
	 */
	public static function validate( array $snapshot ): void {
		self::validate_level( $snapshot, self::schema(), '' );
		if ( self::FORMAT_VERSION !== $snapshot['format_version'] ) {
			throw new CourseException( 'ccap_validation_failed', 'Unsupported snapshot format_version.', 422 );
		}
		foreach ( array( 'objectives', 'fields', 'assets' ) as $list ) {
			foreach ( (array) ( $snapshot[ $list ] ?? array() ) as $item ) {
				if ( ! is_array( $item ) ) {
					throw new CourseException( 'ccap_validation_failed', 'Invalid snapshot field: ' . $list, 422 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: field name for logs.
				}
			}
		}
	}

	/**
	 * Validate one object level.
	 *
	 * @param array<string, mixed> $data   Data.
	 * @param array<string, mixed> $schema Schema of this level.
	 * @param string               $path   Path for messages.
	 * @return void
	 * @throws CourseException On unknown, missing or mistyped fields.
	 */
	private static function validate_level( array $data, array $schema, string $path ): void {
		foreach ( array_keys( $data ) as $key ) {
			if ( ! isset( $schema[ $key ] ) ) {
				throw new CourseException( 'ccap_validation_failed', 'Unknown snapshot field: ' . $path . $key, 422 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: field name for logs.
			}
		}
		foreach ( $schema as $key => $def ) {
			$def = (array) $def;
			if ( ! array_key_exists( $key, $data ) ) {
				if ( ! empty( $def[0] ) ) {
					throw new CourseException( 'ccap_validation_failed', 'Missing snapshot field: ' . $path . $key, 422 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: field name for logs.
				}
				continue;
			}
			$value = $data[ $key ];
			$type  = (string) $def[1];
			if ( str_starts_with( $type, '?' ) ) {
				if ( null === $value ) {
					continue;
				}
				$type = substr( $type, 1 );
			}
			$ok = match ( $type ) {
				'int'    => is_int( $value ),
				'string' => is_string( $value ),
				'bool'   => is_bool( $value ),
				'list'   => is_array( $value ) && array_is_list( $value ),
				'object' => is_array( $value ) && ( array() === $value || ! array_is_list( $value ) ),
				default  => false,
			};
			if ( ! $ok ) {
				throw new CourseException( 'ccap_validation_failed', 'Invalid snapshot field: ' . $path . $key, 422 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: field name for logs.
			}
			if ( 'object' === $type && isset( $def[2] ) ) {
				self::validate_level( (array) $value, (array) $def[2], $path . $key . '.' );
			}
		}
	}

	/**
	 * Canonical form: keys sorted recursively, strings NFC.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function normalize( $value ) {
		if ( is_string( $value ) ) {
			if ( class_exists( \Normalizer::class ) ) {
				$nfc = \Normalizer::normalize( $value, \Normalizer::FORM_C );
				return false === $nfc ? $value : $nfc;
			}
			return $value;
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$out = array();
		foreach ( $value as $key => $item ) {
			$out[ is_string( $key ) ? (string) self::normalize( $key ) : $key ] = self::normalize( $item );
		}
		if ( ! array_is_list( $out ) ) {
			ksort( $out, SORT_STRING );
		}
		return $out;
	}

	/**
	 * Canonical JSON.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return string
	 */
	public static function canonical_json( array $snapshot ): string {
		return (string) json_encode( self::normalize( $snapshot ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure function without WordPress (unit tested); flags are part of the canonical form.
	}

	/**
	 * `snapshot_hash` over the canonical JSON without `snapshot_hash`.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return string
	 */
	public static function hash( array $snapshot ): string {
		unset( $snapshot['snapshot_hash'] );
		return 'sha256:' . hash( 'sha256', self::canonical_json( $snapshot ) );
	}

	/**
	 * Snapshot with its hash, validated, as canonical JSON (what is stored).
	 *
	 * @param array<string, mixed> $snapshot Snapshot without hash.
	 * @return array{json: string, hash: string, snapshot: array<string, mixed>}
	 * @throws CourseException `ccap_validation_failed`.
	 */
	public static function seal( array $snapshot ): array {
		unset( $snapshot['snapshot_hash'] );
		self::validate( $snapshot );
		$hash                      = self::hash( $snapshot );
		$snapshot['snapshot_hash'] = $hash;
		$snapshot                  = (array) self::normalize( $snapshot );
		return array(
			'json'     => self::canonical_json( $snapshot ),
			'hash'     => $hash,
			'snapshot' => $snapshot,
		);
	}

	/**
	 * Whether a stored snapshot still matches its hash (CRT-019).
	 *
	 * @param string $json        Stored JSON.
	 * @param string $column_hash Stored `snapshot_hash` column.
	 * @return bool
	 */
	public static function intact( string $json, string $column_hash ): bool {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return false;
		}
		$hash = self::hash( $data );
		return hash_equals( $column_hash, $hash ) && hash_equals( (string) ( $data['snapshot_hash'] ?? '' ), $hash );
	}

	/**
	 * Printed check code: first 12 hex characters of the hash, grouped by 4.
	 *
	 * @param string $hash `sha256:<hex>`.
	 * @return string
	 */
	public static function check_code( string $hash ): string {
		$hex = strtoupper( substr( (string) preg_replace( '/^sha256:/', '', $hash ), 0, 12 ) );
		return implode( '-', str_split( $hex, 4 ) );
	}

	/**
	 * Certificate number derived from the UID (not secret), e.g. `CC-7F3A-91D2`.
	 *
	 * @param string $uid Certificate UID.
	 * @return string
	 */
	public static function display_id( string $uid ): string {
		$hex = strtoupper( substr( str_replace( '-', '', $uid ), 0, 8 ) );
		return 'CC-' . implode( '-', str_split( $hex, 4 ) );
	}
}
