<?php
/**
 * Read-only restore check of the file storage (R0-16 §1.4; R0-10 §4.4; R0-05 GM-117).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Access\StorageLayout;
use CourseCaptain\Assignment\AssignmentFiles;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Checks after a restore without any side effect (no folder, file, option
 * or row is created, moved or deleted): folder secret present; storage
 * (configured private folder readable, class folders present, folder
 * outside `ABSPATH` as backup hint, foreign class folders of another
 * secret reported and left in place); protected files present (protected
 * attachments and assignment files, listed by media UID). Overall status
 * `fail` > `warn` > `ok`. Output never contains the own folder secret;
 * foreign secrets are masked. The remaining `backup-check` areas of
 * R0-16 §1.4 (schema, tables, grants, running jobs) are not part of this
 * check.
 */
final class RestoreCheck {

	private const SECRET_PATTERN = '/^[a-z0-9]{24}$/';

	/**
	 * Run all checks.
	 *
	 * @return array{status: string, checks: list<array{id: string, status: string, message: string, items: list<string>}>}
	 */
	public static function run(): array {
		$secret = get_option( ProtectedStorage::SECRET_OPTION );
		$secret = is_string( $secret ) && 1 === preg_match( self::SECRET_PATTERN, $secret ) ? $secret : '';
		$checks = array( self::secret_check( $secret ) );
		$base   = self::base( $checks );
		if ( null !== $base ) {
			$checks[] = self::storage_check( $base, $secret );
			$checks[] = self::files_check( $base, $secret );
		}
		$status = 'ok';
		foreach ( $checks as $check ) {
			if ( 'fail' === $check['status'] ) {
				$status = 'fail';
				break;
			}
			if ( 'warn' === $check['status'] ) {
				$status = 'warn';
			}
		}
		return array(
			'status' => $status,
			'checks' => $checks,
		);
	}

	/**
	 * Folder secret.
	 *
	 * @param string $secret Secret or empty.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function secret_check( string $secret ): array {
		return '' === $secret
			? self::check( 'options', 'fail', __( 'The folder secret is missing: restore the options table together with the files.', 'course-captain' ) )
			: self::check( 'options', 'ok', __( 'The folder secret is present.', 'course-captain' ) );
	}

	/**
	 * Active base without creating it; a missing configured folder fails.
	 *
	 * @param list<array{id: string, status: string, message: string, items: list<string>}> $checks Checks (appended on failure).
	 * @return string|null
	 */
	private static function base( array &$checks ): ?string {
		try {
			$configured = ProtectedStorage::configured_base( false );
		} catch ( \Throwable $e ) {
			$checks[] = self::check( 'storage', 'fail', __( 'The configured private folder is missing or not readable.', 'course-captain' ) );
			return null;
		}
		$state = StorageLayout::state();
		// A failed move keeps the recorded folder active; otherwise the configured one counts.
		return $state['failed_at'] > 0 && '' !== $state['base'] ? $state['base'] : $configured;
	}

	/**
	 * Storage folders.
	 *
	 * @param string $base   Base.
	 * @param string $secret Secret or empty.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function storage_check( string $base, string $secret ): array {
		if ( ! is_dir( $base ) || ! is_readable( $base ) ) {
			return self::check( 'storage', 'warn', __( 'The storage folder does not exist yet.', 'course-captain' ) );
		}
		$items   = array();
		$foreign = array();
		$missing = array();
		foreach ( self::entries( $base ) as $entry ) {
			if ( 1 === preg_match( '/^(protected|packages)-([a-z0-9]+)$/', $entry, $m ) && $m[2] !== $secret && is_dir( $base . '/' . $entry ) ) {
				$foreign[] = $m[1] . '-…' . substr( $m[2], -4 );
			}
		}
		foreach ( array( 'protected', 'packages' ) as $kind ) {
			if ( '' !== $secret && ! is_dir( $base . '/' . $kind . '-' . $secret ) ) {
				$missing[] = $kind;
			}
		}
		$status = 'ok';
		if ( array() !== $foreign ) {
			$status = 'warn';
			/* translators: %s: masked folder names */
			$items[] = sprintf( __( 'Folders of another installation found (left in place): %s', 'course-captain' ), implode( ', ', $foreign ) );
		}
		if ( array() !== $missing ) {
			$status = 'warn';
			/* translators: %s: storage classes, e.g. "protected, packages" */
			$items[] = sprintf( __( 'Storage folders missing: %s', 'course-captain' ), implode( ', ', $missing ) );
		}
		if ( null === ProtectedStorage::public_url( $base ) ) {
			$status  = 'warn';
			$items[] = __( 'The private folder lies outside the WordPress folder: include it in the backup.', 'course-captain' );
		}
		return self::check( 'storage', $status, 'ok' === $status ? __( 'The storage folders are in place.', 'course-captain' ) : __( 'The storage folders need attention.', 'course-captain' ), $items );
	}

	/**
	 * Protected files referenced by media rows.
	 *
	 * @param string $base   Base.
	 * @param string $secret Secret or empty.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function files_check( string $base, string $secret ): array {
		$db      = Database::current();
		$table   = $db->table( 'ccap_media' );
		$rows    = $db->rows( "SELECT uid, attachment_id, storage_key, source_system FROM `{$table}` WHERE access = 'protected' AND status = 'active' ORDER BY id" );
		$missing = array();
		foreach ( $rows as $row ) {
			if ( AssignmentFiles::SOURCE === (string) $row['source_system'] ) {
				$key  = (string) $row['storage_key'];
				$path = '' === $secret ? '' : $base . '/protected-' . $secret . '/' . $key;
			} else {
				$attachment = (int) $row['attachment_id'];
				if ( $attachment <= 0 || '' === (string) get_post_meta( $attachment, '_ccap_protected_origin', true ) ) {
					continue;
				}
				$path = (string) get_attached_file( $attachment, true );
			}
			if ( '' === $path || ! is_file( $path ) ) {
				$missing[] = (string) $row['uid'];
			}
		}
		if ( array() === $missing ) {
			return self::check( 'files', 'ok', __( 'All protected files are present.', 'course-captain' ) );
		}
		/* translators: %d: number of files */
		return self::check( 'files', 'fail', sprintf( _n( '%d protected file is missing.', '%d protected files are missing.', count( $missing ), 'course-captain' ), count( $missing ) ), $missing );
	}

	/**
	 * Directory entries.
	 *
	 * @param string $dir Directory.
	 * @return list<string>
	 */
	private static function entries( string $dir ): array {
		$list = scandir( $dir );
		return false === $list ? array() : array_values( array_diff( $list, array( '.', '..' ) ) );
	}

	/**
	 * One check.
	 *
	 * @param string $id      ID.
	 * @param string $status  ok|warn|fail.
	 * @param string $message Message.
	 * @param array  $items   Items.
	 * @phpstan-param list<string> $items
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function check( string $id, string $status, string $message, array $items = array() ): array {
		return array(
			'id'      => $id,
			'status'  => $status,
			'message' => $message,
			'items'   => $items,
		);
	}
}
