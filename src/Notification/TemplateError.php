<?php
/**
 * Mail template could not be rendered (R0-27 §3.5 `template_error`).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Notification;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Leads to `failed (template_error)` without retry; the next send uses
 * the built-in text as fallback.
 */
final class TemplateError extends RuntimeException {
}
