<?php
/**
 * One-time quarantine of legacy media (R0-10 §3.2 V4, §4.1; R0-05 GM-128; INV-C-125).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Files in the public media folder that the import would reject today
 * (V4: extension outside the allowlist, dangerous inner extension, content
 * that does not match the extension, links) are moved once into
 * `<private>/packages-<secret>/quarantine/` with their relative path; the
 * folder is not reachable over the web (R0-10 §4.1). The own guard files
 * (`index.php`, `.htaccess` with the marker) stay.
 *
 * The run is recorded in an option: it happens once per quarantine version
 * and writes exactly one audit entry (`media_quarantined`); later runs,
 * also after an update, do nothing. New files cannot reach the folder any
 * more without the import check.
 */
final class MediaQuarantine {

	public const OPTION  = 'course_captain_media_quarantine';
	public const VERSION = 1;

	/**
	 * Run the quarantine unless it ran already (hourly housekeeping).
	 *
	 * @return array{moved: list<string>, failed: list<string>}|null Null when done before.
	 */
	public static function run_once(): ?array {
		if ( (int) get_option( self::OPTION, 0 ) >= self::VERSION ) {
			return null;
		}
		$result = self::run();
		update_option( self::OPTION, self::VERSION, false );
		AuditLog::write(
			Database::current(),
			Actor::cron(),
			'media_quarantined',
			'storage',
			'media',
			null,
			array(
				'moved'  => count( $result['moved'] ),
				'failed' => count( $result['failed'] ),
			)
		);
		return $result;
	}

	/**
	 * Move every disallowed file of the public media folder.
	 *
	 * @return array{moved: list<string>, failed: list<string>} Relative paths.
	 */
	public static function run(): array {
		$out  = array(
			'moved'  => array(),
			'failed' => array(),
		);
		$base = ProtectedStorage::base() . '/media';
		if ( ! is_dir( $base ) ) {
			return $out;
		}
		$files = array();
		$iter  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::LEAVES_ONLY );
		foreach ( $iter as $entry ) {
			if ( $entry instanceof \SplFileInfo && ( $entry->isLink() || $entry->isFile() ) ) {
				$files[] = wp_normalize_path( $entry->getPathname() );
			}
		}
		sort( $files, SORT_STRING );
		$root = wp_normalize_path( $base ) . '/';
		foreach ( $files as $path ) {
			$relative = substr( $path, strlen( $root ) );
			if ( self::allowed( $path, $relative ) ) {
				continue;
			}
			$target = ProtectedStorage::dir( 'packages' ) . '/quarantine/' . $relative;
			if ( ( is_dir( dirname( $target ) ) || wp_mkdir_p( dirname( $target ) ) ) && @rename( $path, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-volume move; failure is reported.
				$out['moved'][] = $relative;
			} else {
				$out['failed'][] = $relative;
			}
		}
		return $out;
	}

	/**
	 * Whether a file may stay in the public media folder.
	 *
	 * @param string $path     Absolute path.
	 * @param string $relative Path below the media folder.
	 * @return bool
	 */
	private static function allowed( string $path, string $relative ): bool {
		if ( is_link( $path ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Small local guard file.
		if ( 'index.php' === $relative && "<?php\n// Silence is golden.\n" === (string) file_get_contents( $path ) ) {
			return true;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Small local guard file.
		if ( '.htaccess' === $relative && str_starts_with( (string) file_get_contents( $path ), ProtectedStorage::MARKER ) ) {
			return true;
		}
		return null === PackageValidator::file_error( wp_basename( $path ), $path );
	}
}
