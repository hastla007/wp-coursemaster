<?php
/**
 * Frozen clock for tests and deterministic runs.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Clock;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Clock that only moves when told to (R0-05 determinism).
 */
final class FrozenClock implements Clock {

	/**
	 * Frozen Unix timestamp.
	 *
	 * @var int
	 */
	private int $timestamp;

	/**
	 * Constructor.
	 *
	 * @param int|string $at Unix timestamp or a UTC date string such as `2026-11-16 09:30:00`.
	 */
	public function __construct( int|string $at ) {
		$this->timestamp = is_int( $at ) ? $at : ( new DateTimeImmutable( $at, new DateTimeZone( 'UTC' ) ) )->getTimestamp();
	}

	/**
	 * Current (frozen) time in UTC.
	 *
	 * @return DateTimeImmutable
	 */
	public function now(): DateTimeImmutable {
		return ( new DateTimeImmutable( '@' . $this->timestamp ) )->setTimezone( new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Current (frozen) Unix timestamp.
	 *
	 * @return int
	 */
	public function timestamp(): int {
		return $this->timestamp;
	}

	/**
	 * Frozen time as UTC `datetime` literal.
	 *
	 * @param int $offset_seconds Seconds added to now.
	 * @return string
	 */
	public function mysql( int $offset_seconds = 0 ): string {
		return gmdate( self::MYSQL_FORMAT, $this->timestamp + $offset_seconds );
	}

	/**
	 * Move the clock forward (or back with a negative value).
	 *
	 * @param int $seconds Seconds.
	 * @return void
	 */
	public function advance( int $seconds ): void {
		$this->timestamp += $seconds;
	}

	/**
	 * Set the clock to an absolute timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return void
	 */
	public function set( int $timestamp ): void {
		$this->timestamp = $timestamp;
	}
}
