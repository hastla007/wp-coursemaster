<?php
/**
 * Storage error.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

use RuntimeException;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Raised for every failed DB operation. Carries the MySQL error number when
 * known, so callers can treat 1213/1205 as transient (R0-37 §4.5).
 */
class StorageException extends RuntimeException {

	/**
	 * Build from the last $wpdb error. The message is shortened and never
	 * contains the full SQL (no data in logs, R0-17).
	 *
	 * @param wpdb   $wpdb    Database object.
	 * @param string $context Short description of the operation.
	 * @return self
	 */
	public static function from_wpdb( wpdb $wpdb, string $context ): self {
		$error   = (string) $wpdb->last_error;
		$code    = self::errno_from_message( $error );
		$context = (string) preg_replace( '/\s+/', ' ', $context );
		return new self( substr( $error, 0, 300 ) . ' [' . substr( $context, 0, 60 ) . ']', $code );
	}

	/**
	 * MySQL error number for the errors the migrator must recognise. $wpdb
	 * exposes only the message, so the number is derived from it.
	 *
	 * @param string $error Error message.
	 * @return int
	 */
	public static function errno_from_message( string $error ): int {
		$patterns = array(
			1060 => '/^Duplicate column name/i',
			1062 => '/^Duplicate entry/i',
			1061 => '/^Duplicate key name/i',
			1205 => '/^Lock wait timeout exceeded/i',
			1213 => '/^Deadlock found/i',
			1054 => '/^Unknown column/i',
			1146 => "/^Table '.*' doesn't exist/i",
		);
		foreach ( $patterns as $code => $pattern ) {
			if ( 1 === preg_match( $pattern, $error ) ) {
				return $code;
			}
		}
		return 0;
	}

	/**
	 * Unique key violation.
	 *
	 * @return bool
	 */
	public function is_duplicate(): bool {
		return 1062 === $this->getCode();
	}

	/**
	 * Deadlock or lock wait timeout.
	 *
	 * @return bool
	 */
	public function is_transient(): bool {
		return in_array( $this->getCode(), array( 1205, 1213 ), true );
	}
}
