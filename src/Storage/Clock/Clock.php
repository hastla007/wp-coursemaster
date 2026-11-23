<?php
/**
 * UTC clock abstraction.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Clock;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of time for storage code (R0-21 R-8, R0-06 §2 Nr. 7).
 *
 * Every `*_at` column is written in UTC from PHP; SQL never mixes `NOW()`
 * of the database session with PHP time. Tests and the golden master
 * freeze the clock ({@see FrozenClock}, R0-05 determinism).
 */
interface Clock {

	/** Format of `datetime` columns. */
	public const MYSQL_FORMAT = 'Y-m-d H:i:s';

	/**
	 * Current time in UTC.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable;

	/**
	 * Current Unix timestamp.
	 *
	 * @return int
	 */
	public function timestamp(): int;

	/**
	 * Current time as UTC `datetime` literal, optionally shifted.
	 *
	 * @param int $offset_seconds Seconds added to now (may be negative).
	 * @return string
	 */
	public function mysql( int $offset_seconds = 0 ): string;
}
