<?php
/**
 * Domain error of the course model.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Policy\Decision;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Carries a machine-readable error code (`ccap_*`, R0-19) and an HTTP status.
 */
final class CourseException extends RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $error_code `ccap_*` code.
	 * @param string $message    English message for logs.
	 * @param int    $status     HTTP status for entry points.
	 */
	public function __construct( public readonly string $error_code, string $message, public readonly int $status = 409 ) {
		parent::__construct( $message );
	}

	/**
	 * Throw when a policy decision denies (entry points map code/status).
	 *
	 * @param Decision $decision Decision.
	 * @param string   $message  English log message.
	 * @return void
	 * @throws CourseException When denied.
	 */
	public static function unless_allowed( Decision $decision, string $message ): void {
		if ( ! $decision->allowed ) {
			throw new CourseException( $decision->code, $message, $decision->status() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: machine code and status for the entry point.
		}
	}
}
