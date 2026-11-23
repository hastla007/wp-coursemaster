<?php
/**
 * Lock order violation.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Lock;

use LogicException;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown in debug and test runs when code breaks the lock order of
 * R0-21 §2.4 (CON-007). Production only counts the violation.
 */
final class LockOrderViolation extends LogicException {
}
