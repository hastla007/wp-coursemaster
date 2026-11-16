<?php
/**
 * Atomic fixed-window rate limits in `ccap_rate_limit` (R0-08 §6.5, R0-19).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

use CourseCaptain\Course\CourseException;

defined( 'ABSPATH' ) || exit;

/**
 * One counter row per bucket and subject; the bucket key is hashed so no
 * user ID is stored in clear text. Never options (no autoload growth).
 */
final class RateLimiter {

	/**
	 * Count one hit and throw when the limit is exceeded.
	 *
	 * @param Database $db      Database.
	 * @param string   $bucket  Catalog bucket (`answer`, `exam_start`, …).
	 * @param int      $subject Subject (user ID).
	 * @param int      $window  Window length in seconds.
	 * @param int      $limit   Allowed hits per window.
	 * @param int      $now     Current Unix time.
	 * @return void
	 * @throws CourseException `ccap_rate_limited` (429) with `bucket` and `retry_after`.
	 */
	public static function hit( Database $db, string $bucket, int $subject, int $window, int $limit, int $now ): void {
		$start = $now - $now % $window;
		$key   = hash( 'sha256', $bucket . ':' . $subject );
		$table = $db->table( 'ccap_rate_limit' );
		$db->execute(
			$db->prepare(
				"INSERT INTO `{$table}` (bucket_hash, window_start, hits, expires_at) VALUES (%s, %d, 1, %s) ON DUPLICATE KEY UPDATE hits = IF(window_start = VALUES(window_start), hits + 1, 1), window_start = VALUES(window_start), expires_at = VALUES(expires_at)",
				$key,
				$start,
				gmdate( 'Y-m-d H:i:s', $start + 2 * $window )
			)
		);
		$hits = (int) $db->scalar( $db->prepare( "SELECT hits FROM `{$table}` WHERE bucket_hash = %s", $key ) );
		if ( $hits > $limit ) {
			$details = array(
				'bucket'      => $bucket,
				'retry_after' => max( 1, $start + $window - $now ),
			);
			throw new CourseException( 'ccap_rate_limited', 'Rate limit reached.', 429, $details ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: machine details for the entry point.
		}
	}
}
