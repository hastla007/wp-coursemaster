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
	 * Try once, without waiting.
	 *
	 * @return string One of the class constants.
	 */
	public function acquire(): string {
		$result = $this->db->scalar( $this->db->prepare( 'SELECT GET_LOCK(%s, 0)', $this->name ) );
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
