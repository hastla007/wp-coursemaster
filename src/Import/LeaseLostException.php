<?php
/**
 * The import lease was taken over (fencing, R0-21 CON-012).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown inside a chunk transaction; the chunk is rolled back and the
 * step ends without writing (IMP-B1.02).
 */
final class LeaseLostException extends \RuntimeException {
}
