<?php
/**
 * System clock in UTC.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Clock;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the real time, always in UTC regardless of the PHP default timezone.
 */
final class SystemClock implements Clock {

	/**
	 * Current time in UTC.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Current Unix timestamp.
	 *
	 * @return int
	 */
	public function timestamp(): int {
		return time();
	}

	/**
	 * Current time as UTC `datetime` literal.
	 *
	 * @param int $offset_seconds Seconds added to now.
	 * @return string
	 */
	public function mysql( int $offset_seconds = 0 ): string {
		return gmdate( self::MYSQL_FORMAT, $this->timestamp() + $offset_seconds );
	}
}
