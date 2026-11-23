<?php
/**
 * Non-waiting MySQL named lock (R0-21 M0, R0-37 L-A).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * `GET_LOCK(name, 0)` per site. The lock dies with the connection.
 */
final class NamedLock {

	public const ACQUIRED    = 'acquired';
	public const BUSY        = 'busy';
	public const UNAVAILABLE = 'unavailable';

	/**
	 * Whether this instance holds the lock.
	 *
	 * @var bool
	 */
	private bool $held = false;

	/**
	 * Result of the last acquire attempt.
	 *
	 * @var string
	 */
	private string $state = '';

	/**
	 * Constructor.
	 *
	 * @param Database $db   Database access.
	 * @param string   $name Full lock name (≤ 64 characters).
	 */
	public function __construct( private Database $db, public readonly string $name ) {
	}

	/**
	 * Named lock for an arbitrary key of the current site (≤ 64 characters).
	 *
	 * @param Database $db  Database access.
	 * @param string   $key Key, e.g. `group-seats:12`.
	 * @return self
	 */
	public static function for_key( Database $db, string $key ): self {
		$db_name = defined( 'DB_NAME' ) ? (string) DB_NAME : '';
		return new self( $db, 'ccap_' . substr( md5( $db_name . '|' . $db->wpdb()->prefix . '|' . $key ), 0, 24 ) );
	}

	/**
	 * Lock name for the schema migration of the current site (R0-37 §3.1).
	 *
	 * @param Database $db Database access.
	 * @return self
	 */
	public static function schema_migration( Database $db ): self {
		$db_name = defined( 'DB_NAME' ) ? (string) DB_NAME : '';
		return new self( $db, 'ccap_mig_' . substr( md5( $db_name . '|' . $db->wpdb()->prefix . '|schema-migration' ), 0, 16 ) );
	}

	/**
	 * Try to acquire; waits at most `$timeout` seconds (0 = never wait).
	 *
	 * @param int $timeout Seconds (interactive ≤ 2, background ≤ 10; R0-09 §6.1).
	 * @return string One of the class constants.
	 */
	public function acquire( int $timeout = 0 ): string {
		$result = $this->db->scalar( $this->db->prepare( 'SELECT GET_LOCK(%s, %d)', $this->name, max( 0, $timeout ) ) );
		if ( null === $result ) {
			$this->state = self::UNAVAILABLE;
		} elseif ( '1' === $result ) {
			$this->held  = true;
			$this->state = self::ACQUIRED;
		} else {
			$this->state = self::BUSY;
		}
		return $this->state;
	}

	/**
	 * Result of the last acquire attempt ('' before the first).
	 *
	 * @return string
	 */
	public function acquire_state(): string {
		return $this->state;
	}

	/**
	 * Whether the lock is still held by this connection (after reconnects).
	 *
	 * @return bool
	 */
	public function still_held(): bool {
		if ( ! $this->held ) {
			return false;
		}
		return $this->db->scalar( $this->db->prepare( 'SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', $this->name ) ) === '1';
	}

	/**
	 * Release if held.
	 *
	 * @return void
	 */
	public function release(): void {
		if ( $this->held ) {
			$this->db->scalar( $this->db->prepare( 'SELECT RELEASE_LOCK(%s)', $this->name ) );
			$this->held = false;
		}
	}
}
