<?php
/**
 * Transaction policy.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Lock wait timeout and deadlock retries of one operation (R0-21 §2.4/§2.6).
 */
final class TxPolicy {

	/**
	 * Constructor.
	 *
	 * @param int $lock_wait_timeout Session `innodb_lock_wait_timeout` in seconds.
	 * @param int $max_retries       Full repetitions after a deadlock (T-2).
	 * @param int $jitter_min_ms     Lower bound of the wait before a retry.
	 * @param int $jitter_max_ms     Upper bound of the wait before a retry.
	 */
	public function __construct(
		public readonly int $lock_wait_timeout,
		public readonly int $max_retries = 3,
		public readonly int $jitter_min_ms = 50,
		public readonly int $jitter_max_ms = 200,
	) {
	}

	/**
	 * Interactive requests: 5 s lock wait (D-14-03, RB-08).
	 *
	 * @return self
	 */
	public static function interactive(): self {
		return new self( 5 );
	}

	/**
	 * Background jobs and migrations: 15 s lock wait (R0-21 Δ-21-8).
	 *
	 * @return self
	 */
	public static function background(): self {
		return new self( 15 );
	}
}
