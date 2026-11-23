<?php
/**
 * Schema error (not a data error): unknown keyword, broken reference.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Aborts validation; the message is a short machine code.
 */
final class SchemaException extends \RuntimeException {
}
