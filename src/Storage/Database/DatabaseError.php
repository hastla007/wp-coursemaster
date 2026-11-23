<?php
/**
 * Failed SQL statement.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Database;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown for every failed statement (R0-21 T-1). The exception code is the
 * MySQL/MariaDB error number (0 if unknown).
 */
class DatabaseError extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $message Server message (never shown to visitors unescaped).
	 * @param int    $errno   Server error number.
	 * @param string $sql     Statement, kept for logs and tests only.
	 */
	public function __construct( string $message, int $errno, private string $sql = '' ) {
		parent::__construct( $message, $errno );
	}

	/**
	 * Server error number.
	 *
	 * @return int
	 */
	public function errno(): int {
		return (int) $this->getCode();
	}

	/**
	 * The failed statement.
	 *
	 * @return string
	 */
	public function sql(): string {
		return $this->sql;
	}

	/**
	 * Deadlock: the server already rolled back the whole transaction.
	 *
	 * @return bool
	 */
	public function is_deadlock(): bool {
		return Connection::ER_LOCK_DEADLOCK === $this->errno();
	}

	/**
	 * Lock wait timeout: only the statement was rolled back.
	 *
	 * @return bool
	 */
	public function is_lock_wait_timeout(): bool {
		return Connection::ER_LOCK_WAIT_TIMEOUT === $this->errno();
	}

	/**
	 * Lost connection (RB-05).
	 *
	 * @return bool
	 */
	public function is_connection_lost(): bool {
		return in_array( $this->errno(), array( Connection::CR_SERVER_GONE_ERROR, Connection::CR_SERVER_LOST ), true );
	}

	/**
	 * Transient error that a later run may not hit again (R0-37 §4.5).
	 *
	 * @return bool
	 */
	public function is_transient(): bool {
		return $this->is_deadlock() || $this->is_lock_wait_timeout() || $this->is_connection_lost();
	}
}
