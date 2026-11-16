<?php
/**
 * Minimal JSON-Schema validator for activity settings.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity;

defined( 'ABSPATH' ) || exit;

/**
 * Supports the subset the R1 providers use: `type` (object, string,
 * integer, number, boolean, array), `properties`, `required`,
 * `additionalProperties: false`, `enum`, `minimum`, `maximum`,
 * `minLength`, `maxLength`, `pattern`, `items`, `format: https-url`.
 */
final class SettingsSchema {

	/**
	 * Validate a value; returns JSON-pointer-like error strings.
	 *
	 * @param mixed                $value  Value.
	 * @param array<string, mixed> $schema Schema.
	 * @param string               $path   Pointer.
	 * @return list<string>
	 */
	public static function validate( $value, array $schema, string $path = '' ): array {
		$errors = array();
		$type   = $schema['type'] ?? null;
		if ( null !== $type && ! self::is_type( $value, (string) $type ) ) {
			return array( ( '' === $path ? '/' : $path ) . ': type ' . $type );
		}
		if ( isset( $schema['enum'] ) && is_array( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
			$errors[] = $path . ': enum';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			if ( isset( $schema['minimum'] ) && $value < $schema['minimum'] ) {
				$errors[] = $path . ': minimum';
			}
			if ( isset( $schema['maximum'] ) && $value > $schema['maximum'] ) {
				$errors[] = $path . ': maximum';
			}
		}
		if ( is_string( $value ) ) {
			$length = mb_strlen( $value );
			if ( isset( $schema['minLength'] ) && $length < $schema['minLength'] ) {
				$errors[] = $path . ': minLength';
			}
			if ( isset( $schema['maxLength'] ) && $length > $schema['maxLength'] ) {
				$errors[] = $path . ': maxLength';
			}
			if ( isset( $schema['pattern'] ) && 1 !== preg_match( '/' . str_replace( '/', '\/', (string) $schema['pattern'] ) . '/u', $value ) ) {
				$errors[] = $path . ': pattern';
			}
			if ( 'https-url' === ( $schema['format'] ?? '' ) && ! self::is_https_url( $value ) ) {
				$errors[] = $path . ': https-url';
			}
		}
		if ( is_array( $value ) && 'object' === $type ) {
			$properties = is_array( $schema['properties'] ?? null ) ? $schema['properties'] : array();
			foreach ( (array) ( $schema['required'] ?? array() ) as $required ) {
				if ( ! array_key_exists( (string) $required, $value ) ) {
					$errors[] = $path . '/' . $required . ': required';
				}
			}
			foreach ( $value as $key => $item ) {
				if ( isset( $properties[ $key ] ) ) {
					$errors = array_merge( $errors, self::validate( $item, (array) $properties[ $key ], $path . '/' . $key ) );
				} elseif ( false === ( $schema['additionalProperties'] ?? true ) ) {
					$errors[] = $path . '/' . $key . ': additional';
				}
			}
		}
		if ( is_array( $value ) && 'array' === $type && isset( $schema['items'] ) ) {
			foreach ( $value as $i => $item ) {
				$errors = array_merge( $errors, self::validate( $item, (array) $schema['items'], $path . '/' . $i ) );
			}
		}
		return $errors;
	}

	/**
	 * Strict https URL with host.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_https_url( string $url ): bool {
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && 'https' === ( $parts['scheme'] ?? '' ) && '' !== (string) ( $parts['host'] ?? '' );
	}

	/**
	 * JSON type check.
	 *
	 * @param mixed  $value Value.
	 * @param string $type  Type.
	 * @return bool
	 */
	private static function is_type( $value, string $type ): bool {
		switch ( $type ) {
			case 'object':
				return is_array( $value ) && ( array() === $value || ! array_is_list( $value ) );
			case 'array':
				return is_array( $value ) && array_is_list( $value );
			case 'string':
				return is_string( $value );
			case 'integer':
				return is_int( $value );
			case 'number':
				return is_int( $value ) || is_float( $value );
			case 'boolean':
				return is_bool( $value );
		}
		return false;
	}
}
