<?php
/**
 * Database busy after deadlock retries or a lock wait timeout.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Database;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Maps to `503 ccap_db_busy` with `Retry-After: 2` (R0-21 T-2/T-3, R0-19 §3.2).
 */
final class DbBusy extends DatabaseError {

	/** Reason: deadlock retries exhausted. */
	public const REASON_DEADLOCK = 'deadlock';

	/** Reason: lock wait timeout (never retried on the server). */
	public const REASON_LOCK_WAIT = 'lock_wait';

	/** Seconds the client waits before retrying. */
	public const RETRY_AFTER = 2;

	/**
	 * Constructor.
	 *
	 * @param string        $reason   One of the REASON_* constants.
	 * @param DatabaseError $previous Last server error.
	 */
	public function __construct( private string $reason, DatabaseError $previous ) {
		parent::__construct( $previous->getMessage(), $previous->errno(), $previous->sql() );
	}

	/**
	 * Why the database was busy.
	 *
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * Error for REST responses and the PHP API.
	 *
	 * @return WP_Error
	 */
	public function to_wp_error(): WP_Error {
		return new WP_Error(
			'ccap_db_busy',
			__( 'The database is busy. Please try again in a moment.', 'course-captain' ),
			array(
				'status'      => 503,
				'retryable'   => true,
				'retry_after' => self::RETRY_AFTER,
			)
		);
	}
}
