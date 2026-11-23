<?php
/**
 * Fault injection points of the migrator, active in tests only (R0-37 §7).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Fires `course_captain_migration_fault` with step ID and point
 * (`before_ddl`, `after_ddl`, `in_batch`, `after_batch`, `before_status`)
 * only when the constant `COURSE_CAPTAIN_TESTING` is true. Production never
 * runs the hook.
 */
final class MigrationFault {

	public const HOOK = 'course_captain_migration_fault';

	/**
	 * Injection point.
	 *
	 * @param string $step_id Step ID.
	 * @param string $point   Point name.
	 * @return void
	 */
	public static function point( string $step_id, string $point ): void {
		if ( defined( 'COURSE_CAPTAIN_TESTING' ) && true === constant( 'COURSE_CAPTAIN_TESTING' ) ) {
			do_action( 'course_captain_migration_fault', $step_id, $point );
		}
	}
}
