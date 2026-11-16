<?php
/**
 * Fatal stop of an import run with a rule (R0-38 §5.3, class F).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Carries the internal rule (see {@see ImportReport::CODES}); the runner
 * aborts the batch with it and keeps the cursor.
 */
final class ImportStopException extends \RuntimeException {

	/**
	 * Internal rule, e.g. `package_missing`.
	 *
	 * @var string
	 */
	public readonly string $reason;

	/**
	 * Constructor.
	 *
	 * @param string $reason Internal rule.
	 */
	public function __construct( string $reason ) {
		$this->reason = $reason;
		parent::__construct( 'Import stopped: ' . $reason );
	}
}
