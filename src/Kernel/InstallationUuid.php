<?php
/**
 * Installation UUID of the site (R0-10 §2.2, R0-38 §3.3, E-392).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

defined( 'ABSPATH' ) || exit;

/**
 * Option `course_captain_installation_uuid` identifies the site, never a
 * person. Exports write it to `source.exported_from_site`; the import treats
 * a package with this site's UUID as own content (`Rights::own_manifest()`).
 *
 * Created on activation and, for installations that predate E-392, on the
 * next admin request ({@see ensure()}). A value that exists but is not
 * readable is left alone, so the restore check can report it.
 */
final class InstallationUuid {

	public const OPTION = 'course_captain_installation_uuid';

	public const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

	/**
	 * Create the UUID when the option does not exist yet (activation,
	 * upgrade). Never overwrites a stored value.
	 *
	 * @return void
	 */
	public static function ensure(): void {
		if ( false !== get_option( self::OPTION ) ) {
			return;
		}
		add_option( self::OPTION, self::generate(), '', false );
	}

	/**
	 * Valid UUID of this site; a missing or unreadable value is replaced
	 * (first export on an installation without one).
	 *
	 * @return string
	 */
	public static function get(): string {
		$uuid = get_option( self::OPTION );
		if ( is_string( $uuid ) && 1 === preg_match( self::PATTERN, $uuid ) ) {
			return $uuid;
		}
		$uuid = self::generate();
		update_option( self::OPTION, $uuid, false );
		return $uuid;
	}

	/**
	 * New lower-case UUID v4.
	 *
	 * @return string
	 */
	private static function generate(): string {
		return strtolower( wp_generate_uuid4() );
	}
}
