<?php
/**
 * Module switches (SET-CC-24).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Course\CourseException;

defined( 'ABSPATH' ) || exit;

/**
 * Certificates, assignments and flashcards can be switched off per site.
 * A switched-off module is hidden in the learning area and the
 * administration, its REST routes are not registered, its jobs do not
 * run and the course check ignores it. Nothing is deleted: switching on
 * again restores every screen with the stored data. Deliberate
 * exceptions: public verification of issued certificates stays reachable
 * (third parties rely on it), privacy export, erasure and retention keep
 * covering the module data (legal duties, not module functions).
 */
final class Modules {

	public const CERTIFICATES = 'certificates';
	public const ASSIGNMENTS  = 'assignments';
	public const FLASHCARDS   = 'flashcards';

	/**
	 * Register key per module.
	 *
	 * @var array<string, string>
	 */
	public const KEYS = array(
		self::CERTIFICATES => 'certificates.enabled',
		self::ASSIGNMENTS  => 'learning.assignments_enabled',
		self::FLASHCARDS   => 'learning.flashcards_enabled',
	);

	/**
	 * Module of an activity type.
	 *
	 * @var array<string, string>
	 */
	public const ACTIVITY_TYPES = array(
		'assignment' => self::ASSIGNMENTS,
		'flashcard'  => self::FLASHCARDS,
	);

	/**
	 * Whether a module is switched on (unknown modules and read errors count as on).
	 *
	 * @param string $module Module.
	 * @return bool
	 */
	public static function enabled( string $module ): bool {
		if ( ! isset( self::KEYS[ $module ] ) ) {
			return true;
		}
		try {
			return false !== Settings::get( self::KEYS[ $module ] );
		} catch ( \Throwable $e ) {
			unset( $e );
			return true;
		}
	}

	/**
	 * Whether the module of an activity type is switched on (types without a module: always).
	 *
	 * @param string $type Activity type.
	 * @return bool
	 */
	public static function activity_enabled( string $type ): bool {
		return ! isset( self::ACTIVITY_TYPES[ $type ] ) || self::enabled( self::ACTIVITY_TYPES[ $type ] );
	}

	/**
	 * Refuse while a module is switched off.
	 *
	 * @param string $module Module.
	 * @return void
	 * @throws CourseException `ccap_module_disabled` (404).
	 */
	public static function assert_enabled( string $module ): void {
		if ( ! self::enabled( $module ) ) {
			throw new CourseException( 'ccap_module_disabled', 'This function is switched off.', 404, array( 'module' => $module ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
		}
	}
}
