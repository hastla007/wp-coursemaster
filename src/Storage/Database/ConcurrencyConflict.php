<?php
/**
 * Lost compare-and-swap race.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Database;

use RuntimeException;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Maps to `409 ccap_conflict` after three CAS attempts (R0-21 T-7).
 */
final class ConcurrencyConflict extends RuntimeException {

	/**
	 * Error for REST responses and the PHP API.
	 *
	 * @return WP_Error
	 */
	public function to_wp_error(): WP_Error {
		return new WP_Error(
			'ccap_conflict',
			__( 'The data was changed at the same time. Please reload and try again.', 'course-captain' ),
			array(
				'status'    => 409,
				'retryable' => false,
			)
		);
	}
}
