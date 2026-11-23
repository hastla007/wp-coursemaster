<?php
/**
 * Canonical JSON and record hashes (R0-10 §1.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

defined( 'ABSPATH' ) || exit;

/**
 * UTF-8, NFC (when intl is available), trimmed strings, object keys sorted
 * recursively, no escaped slashes/unicode (RFC 8785 subset for the value
 * types used in packages). Objects and associative arrays are equivalent
 * (an empty object hashes like an empty list, as after `json_decode(…, true)`).
 * The record hash is computed without `hash`.
 */
final class Canonical {

	/**
	 * Canonical serialization.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	public static function encode( $value ): string {
		return (string) wp_json_encode( self::normalize( $value ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION );
	}

	/**
	 * `sha256:` hash of a record without its `hash` field.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return string
	 */
	public static function hash( array $record ): string {
		unset( $record['hash'] );
		return 'sha256:' . hash( 'sha256', self::encode( $record ) );
	}

	/**
	 * Normalize recursively.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function normalize( $value ) {
		if ( $value instanceof \stdClass ) {
			// Decoded packages use associative arrays; objects hash the same way.
			$value = get_object_vars( $value );
		}
		if ( is_string( $value ) ) {
			$value = trim( $value );
			return class_exists( '\Normalizer' ) ? (string) \Normalizer::normalize( $value, \Normalizer::FORM_C ) : $value;
		}
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_is_list( $value ) ) {
			return array_map( array( self::class, 'normalize' ), $value );
		}
		ksort( $value, SORT_STRING );
		return (object) array_map( array( self::class, 'normalize' ), $value );
	}
}
