<?php
/**
 * Permanent delivery error (no retry).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Event;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown by a subscriber handler for errors a retry cannot fix
 * (validation, incompatible contract, forbidden; R0-19 §5.4 Nr. 4).
 */
final class PermanentDeliveryError extends RuntimeException {
}
