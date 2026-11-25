<?php
/**
 * Read-only backup and restore check `backup-check` (R0-16 §1.4; R0-10
 * §4.4; R0-05 GM-117).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Access\StorageLayout;
use CourseCaptain\Assignment\AssignmentFiles;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\Schema;
use CourseCaptain\Storage\SchemaInspector;

defined( 'ABSPATH' ) || exit;

/**
 * Checks after a restore without any side effect: no folder, file, option,
 * transient or row is created, moved or deleted, no audit entry is written
 * and no access filter runs. Areas of R0-16 §1.4 in this order:
 *
 * - `schema`: stored schema version missing or newer than the code → fail;
 *   migration due or backfill open → warn;
 * - `tables`: an own table missing or not InnoDB → fail;
 * - `foreign_keys`: fewer own foreign keys than expected or recorded
 *   `course_captain_fk_issues` → warn;
 * - `options`: folder secret or installation UUID missing → fail;
 * - `storage`: configured private folder not readable → fail; class folder
 *   missing, folder outside `ABSPATH` (backup hint), foreign class folders
 *   of another secret (masked, left in place) → warn;
 * - `package`: imported content of a source without a `published` batch →
 *   fail; package copy of a published batch missing → warn;
 * - `files`: protected file referenced by a media row missing → fail (items
 *   are media UIDs); size/SHA-256 differs (sample of 25, `$full` checks
 *   all) or unreferenced files in the protected folder → warn;
 * - `grants`: sample of 25 active grants whose access evaluation says "no",
 *   active grants without a user account → warn;
 * - `jobs`: imports `importing/activating` without heartbeat, exam sessions
 *   `in_progress` past their deadline, deliveries `dispatching` with an
 *   expired lease → warn;
 * - `identity`: site identity differs (hold mode active) → warn.
 *
 * Overall status `fail` > `warn` > `ok`. Output never contains the own
 * folder secret; foreign secrets are masked; IDs are UIDs or row IDs only.
 */
final class RestoreCheck {

	public const SAMPLE    = 25;
	public const MAX_ITEMS = 20;

	private const SECRET_PATTERN   = '/^[a-z0-9]{24}$/';
	public const INSTALLATION_UUID = \CourseCaptain\Kernel\InstallationUuid::OPTION;

	private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

	/**
	 * Run all checks.
	 *
	 * @param bool $full Check size and SHA-256 of every file (CLI `--full`), not a sample.
	 * @return array{status: string, checks: list<array{id: string, status: string, message: string, items: list<string>}>}
	 */
	public static function run( bool $full = false ): array {
		$db     = Database::current();
		$secret = get_option( ProtectedStorage::SECRET_OPTION );
		$secret = is_string( $secret ) && 1 === preg_match( self::SECRET_PATTERN, $secret ) ? $secret : '';
		$tables = ( new SchemaInspector( $db ) )->tables();
		$checks = array(
			self::schema_check( $db ),
			self::tables_check( $tables ),
			self::foreign_keys_check( $db ),
			self::options_check( $secret ),
		);
		$base   = self::base( $checks );
		if ( null !== $base ) {
			$checks[] = self::storage_check( $base, $secret );
		}
		$checks[] = self::package_check( $db, $tables );
		if ( null !== $base ) {
			$checks[] = self::files_check( $db, $base, $secret, $full );
		}
		$checks[] = self::grants_check( $db );
		$checks[] = self::jobs_check( $db );
		$checks[] = self::identity_check( null !== $base );
		$status   = 'ok';
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
	 * Schema version and migration state.
	 *
	 * @param Database $db Database.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function schema_check( Database $db ): array {
		$report = ( new Migrator( $db, COURSE_CAPTAIN_VERSION ) )->report();
		if ( null === $report['stored_version'] ) {
			return self::check( 'schema', 'fail', __( 'The schema version is missing: restore the options table together with the tables.', 'course-captain' ) );
		}
		if ( 'newer' === $report['state'] ) {
			/* translators: 1: stored schema version, 2: schema version of the code */
			return self::check( 'schema', 'fail', sprintf( __( 'The database schema (%1$s) is newer than this plugin version (%2$s): install the matching plugin version.', 'course-captain' ), $report['stored_version'], $report['code_version'] ) );
		}
		if ( 'backfill' === $report['state'] ) {
			return self::check( 'schema', 'warn', __( 'A data backfill of the database update is still open.', 'course-captain' ), $report['backfill_open'] );
		}
		if ( 'ok' !== $report['state'] ) {
			/* translators: %s: migration state, e.g. "pending" */
			return self::check( 'schema', 'warn', sprintf( __( 'A database update is due (state: %s). Run "wp course-captain migrate".', 'course-captain' ), $report['state'] ), array_values( array_merge( $report['poison'], $report['failed'] ) ) );
		}
		/* translators: %s: schema version */
		return self::check( 'schema', 'ok', sprintf( __( 'The database schema %s matches this plugin version.', 'course-captain' ), $report['stored_version'] ) );
	}

	/**
	 * Own tables present and InnoDB.
	 *
	 * @param array<string, array{engine: string, comment: string}> $tables Existing own tables.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function tables_check( array $tables ): array {
		$items = array();
		foreach ( Schema::table_names() as $name ) {
			if ( ! isset( $tables[ $name ] ) ) {
				$items[] = $name . ' (missing)';
			} elseif ( 'InnoDB' !== $tables[ $name ]['engine'] ) {
				$items[] = $name . ' (' . $tables[ $name ]['engine'] . ')';
			}
		}
		if ( array() === $items ) {
			return self::check( 'tables', 'ok', __( 'All Course Captain tables exist and use InnoDB.', 'course-captain' ) );
		}
		return self::check( 'tables', 'fail', __( 'Course Captain tables are missing or do not use InnoDB: restore the complete database.', 'course-captain' ), $items );
	}

	/**
	 * Own foreign keys (E-48: failures are not fatal).
	 *
	 * @param Database $db Database.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function foreign_keys_check( Database $db ): array {
		$existing = ( new SchemaInspector( $db ) )->foreign_keys();
		$prefix   = $db->wpdb()->prefix;
		$missing  = array();
		foreach ( Schema::foreign_keys() as $fk ) {
			if ( ! isset( $existing[ Migrator::foreign_key_name( $prefix, $fk['table'], $fk['column'] ) ] ) ) {
				$missing[] = $fk['table'] . '.' . $fk['column'];
			}
		}
		$issues = get_option( Migrator::FK_ISSUES );
		$issues = is_array( $issues ) ? array_map( 'strval', array_keys( $issues ) ) : array();
		$items  = array_values( array_unique( array_merge( $missing, $issues ) ) );
		if ( array() === $items ) {
			return self::check( 'foreign_keys', 'ok', __( 'All foreign keys are in place.', 'course-captain' ) );
		}
		/* translators: 1: number of foreign keys found, 2: number expected */
		return self::check( 'foreign_keys', 'warn', sprintf( __( '%1$d of %2$d foreign keys are in place. Course Captain works without them; run "wp course-captain migrate" to add them again.', 'course-captain' ), count( Schema::foreign_keys() ) - count( $missing ), count( Schema::foreign_keys() ) ), self::limit( $items ) );
	}

	/**
	 * Folder secret and installation UUID. The UUID option is not created
	 * by this version (R1-18 h3 F-1, release pending): a missing UUID is
	 * reported as a note, an unreadable value fails.
	 *
	 * @param string $secret Secret or empty.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function options_check( string $secret ): array {
		$items = array();
		if ( '' === $secret ) {
			$items[] = __( 'The folder secret is missing.', 'course-captain' );
		}
		$uuid = get_option( self::INSTALLATION_UUID );
		if ( false !== $uuid && ( ! is_string( $uuid ) || 1 !== preg_match( self::UUID_PATTERN, $uuid ) ) ) {
			$items[] = __( 'The installation UUID is not readable.', 'course-captain' );
		}
		if ( array() !== $items ) {
			return self::check( 'options', 'fail', __( 'Options are missing: restore the options table together with the files.', 'course-captain' ), $items );
		}
		if ( false === $uuid ) {
			return self::check( 'options', 'ok', __( 'The folder secret is present.', 'course-captain' ), array( __( 'No installation UUID is recorded (not created by this version).', 'course-captain' ) ) );
		}
		return self::check( 'options', 'ok', __( 'The folder secret and the installation UUID are present.', 'course-captain' ) );
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
	 * Active package: every source with imported content has a published
	 * batch, and the package copy of each published batch exists.
	 *
	 * @param Database                                              $db     Database.
	 * @param array<string, array{engine: string, comment: string}> $tables Existing own tables.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function package_check( Database $db, array $tables ): array {
		if ( ! isset( $tables['ccap_source_map'], $tables['ccap_import_batch'] ) ) {
			return self::check( 'package', 'fail', __( 'The import tables are missing.', 'course-captain' ) );
		}
		$systems = array_map( 'strval', array_column( $db->rows( 'SELECT DISTINCT source_system FROM `' . $db->table( 'ccap_source_map' ) . '` WHERE last_batch_id IS NOT NULL ORDER BY source_system' ), 'source_system' ) );
		if ( array() === $systems ) {
			return self::check( 'package', 'ok', __( 'No imported package is in use.', 'course-captain' ) );
		}
		$published = array();
		$copies    = array();
		foreach ( $db->rows( 'SELECT id, manifest, storage_path FROM `' . $db->table( 'ccap_import_batch' ) . "` WHERE status = 'published' ORDER BY id" ) as $row ) {
			$manifest             = json_decode( (string) $row['manifest'], true );
			$system               = is_array( $manifest ) ? (string) ( $manifest['source']['system'] ?? '' ) : '';
			$published[ $system ] = true;
			$path                 = (string) $row['storage_path'];
			if ( '' === $path || ! is_file( $path ) ) {
				$copies[] = 'import:' . (int) $row['id'];
			}
		}
		$orphans = array_values( array_filter( $systems, static fn( string $system ): bool => ! isset( $published[ $system ] ) ) );
		if ( array() !== $orphans ) {
			return self::check( 'package', 'fail', __( 'Imported content has no published import (active package missing): restore the import tables completely.', 'course-captain' ), self::limit( $orphans ) );
		}
		if ( array() !== $copies ) {
			return self::check( 'package', 'warn', __( 'The package copy of the active import is missing: a content rollback is not possible.', 'course-captain' ), self::limit( $copies ) );
		}
		return self::check( 'package', 'ok', __( 'The active package and its package copy are present.', 'course-captain' ) );
	}

	/**
	 * Protected files referenced by media rows: presence (fail), size and
	 * SHA-256 (warn, sample or all), unreferenced files (warn).
	 *
	 * @param Database $db     Database.
	 * @param string   $base   Base.
	 * @param string   $secret Secret or empty.
	 * @param bool     $full   Check every file.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function files_check( Database $db, string $base, string $secret, bool $full ): array {
		$table   = $db->table( 'ccap_media' );
		$rows    = $db->rows( "SELECT id, uid, attachment_id, storage_key, source_system, size_bytes, sha256 FROM `{$table}` WHERE access = 'protected' AND status = 'active' ORDER BY id" );
		$missing = array();
		$present = array();
		$keys    = array();
		$folders = array();
		foreach ( $rows as $row ) {
			if ( AssignmentFiles::SOURCE === (string) $row['source_system'] ) {
				$key          = (string) $row['storage_key'];
				$keys[ $key ] = true;
				$path         = '' === $secret ? '' : $base . '/protected-' . $secret . '/' . $key;
			} else {
				$attachment = (int) $row['attachment_id'];
				if ( $attachment <= 0 || '' === (string) get_post_meta( $attachment, '_ccap_protected_origin', true ) ) {
					continue;
				}
				$folders[ (string) (int) $row['id'] ] = true;
				$path                                 = (string) get_attached_file( $attachment, true );
			}
			if ( '' === $path || ! is_file( $path ) ) {
				$missing[] = (string) $row['uid'];
			} else {
				$present[] = array( $row, $path );
			}
		}
		if ( array() !== $missing ) {
			/* translators: %d: number of files */
			return self::check( 'files', 'fail', sprintf( _n( '%d protected file is missing.', '%d protected files are missing.', count( $missing ), 'course-captain' ), count( $missing ) ), $missing );
		}
		if ( ! $full && count( $present ) > self::SAMPLE ) {
			shuffle( $present );
			$present = array_slice( $present, 0, self::SAMPLE );
		}
		$items = array();
		foreach ( $present as $entry ) {
			list( $row, $path ) = $entry;
			if ( (int) filesize( $path ) !== (int) $row['size_bytes'] || ! hash_equals( strtolower( (string) $row['sha256'] ), (string) hash_file( 'sha256', $path ) ) ) {
				/* translators: %s: media UID */
				$items[] = sprintf( __( '%s: size or SHA-256 differs', 'course-captain' ), (string) $row['uid'] );
			}
		}
		if ( '' !== $secret ) {
			foreach ( self::unreferenced( $base . '/protected-' . $secret, $keys, $folders ) as $entry ) {
				/* translators: %s: path inside the protected folder (without the folder secret) */
				$items[] = sprintf( __( 'Unreferenced: %s', 'course-captain' ), $entry );
			}
		}
		if ( array() === $items ) {
			/* translators: %d: number of files checked */
			return self::check( 'files', 'ok', sprintf( _n( 'All protected files are present; %d file checked for size and SHA-256.', 'All protected files are present; %d files checked for size and SHA-256.', count( $present ), 'course-captain' ), count( $present ) ) );
		}
		return self::check( 'files', 'warn', __( 'Protected files differ from the database or are not referenced.', 'course-captain' ), self::limit( $items ) );
	}

	/**
	 * Files in the protected folder that no media row references:
	 * assignment files below `assignments/` and attachment folders `<media id>/`.
	 *
	 * @param string                  $dir     Protected folder.
	 * @param array<string, true>     $keys    Referenced assignment storage keys.
	 * @param array<int|string, true> $folders Referenced attachment folders (media IDs).
	 * @return list<string> Relative paths.
	 */
	private static function unreferenced( string $dir, array $keys, array $folders ): array {
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$found = array();
		foreach ( self::entries( $dir ) as $entry ) {
			if ( 1 === preg_match( '/^\d+$/', $entry ) && is_dir( $dir . '/' . $entry ) && ! isset( $folders[ $entry ] ) && array() !== array_diff( self::entries( $dir . '/' . $entry ), array( 'index.php' ) ) ) {
				$found[] = $entry . '/';
			}
		}
		if ( is_dir( $dir . '/assignments' ) ) {
			foreach ( self::entries( $dir . '/assignments' ) as $submission ) {
				if ( ! is_dir( $dir . '/assignments/' . $submission ) ) {
					continue;
				}
				foreach ( self::entries( $dir . '/assignments/' . $submission ) as $file ) {
					$key = 'assignments/' . $submission . '/' . $file;
					if ( 'index.php' !== $file && ! isset( $keys[ $key ] ) ) {
						$found[] = $key;
					}
				}
			}
		}
		return $found;
	}

	/**
	 * Sample of active grants: access evaluation and user account.
	 *
	 * @param Database $db Database.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function grants_check( Database $db ): array {
		$grant   = $db->table( 'ccap_grant' );
		$users   = $db->wpdb()->users;
		$now     = $db->now();
		$items   = array();
		$orphans = $db->rows( $db->prepare( "SELECT g.uid FROM `{$grant}` g LEFT JOIN `{$users}` u ON u.ID = g.user_id WHERE g.status = 'active' AND g.user_id IS NOT NULL AND u.ID IS NULL ORDER BY g.id LIMIT %d", self::MAX_ITEMS ) );
		foreach ( $orphans as $row ) {
			/* translators: %s: grant UID */
			$items[] = sprintf( __( 'grant:%s without user account', 'course-captain' ), (string) $row['uid'] );
		}
		$sample    = $db->rows( $db->prepare( "SELECT g.uid, g.user_id, g.course_id, g.module_id FROM `{$grant}` g JOIN `{$users}` u ON u.ID = g.user_id WHERE g.status = 'active' AND (g.valid_from IS NULL OR g.valid_from <= %s) AND (g.valid_until IS NULL OR g.valid_until > %s) ORDER BY RAND() LIMIT %d", $now, $now, self::SAMPLE ) );
		$relations = new Relations( $db );
		foreach ( $sample as $row ) {
			$access = $relations->effective_access( (int) $row['user_id'], (int) $row['course_id'], (int) $row['module_id'], 'view' );
			if ( 'ok' !== $access ) {
				/* translators: 1: grant UID, 2: reason code */
				$items[] = sprintf( __( 'grant:%1$s access check says no (%2$s)', 'course-captain' ), (string) $row['uid'], $access );
			}
		}
		if ( array() === $items ) {
			/* translators: %d: number of grants checked */
			return self::check( 'grants', 'ok', sprintf( _n( '%d active grant checked: access confirmed.', '%d active grants checked: access confirmed.', count( $sample ), 'course-captain' ), count( $sample ) ) );
		}
		return self::check( 'grants', 'warn', __( 'Active grants do not give access or have no user account.', 'course-captain' ), self::limit( $items ) );
	}

	/**
	 * Running jobs left behind by the backup.
	 *
	 * @param Database $db Database.
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function jobs_check( Database $db ): array {
		$now   = $db->now();
		$items = array_merge(
			self::ids( $db, $db->prepare( "SELECT CONCAT('import:', id) FROM `" . $db->table( 'ccap_import_batch' ) . "` WHERE status IN ('importing', 'activating') AND (heartbeat_at IS NULL OR heartbeat_at < %s) ORDER BY id LIMIT %d", $db->now( -OpsStatus::HEARTBEAT_STALE ), self::MAX_ITEMS ) ),
			self::ids( $db, $db->prepare( "SELECT CONCAT('exam:', uid) FROM `" . $db->table( 'ccap_exam_session' ) . "` WHERE status = 'in_progress' AND deadline_at IS NOT NULL AND deadline_at < %s ORDER BY id LIMIT %d", $now, self::MAX_ITEMS ) ),
			self::ids( $db, $db->prepare( "SELECT CONCAT('delivery:', id) FROM `" . $db->table( 'ccap_outbox_delivery' ) . "` WHERE status = 'dispatching' AND locked_until < %s ORDER BY id LIMIT %d", $now, self::MAX_ITEMS ) )
		);
		if ( array() === $items ) {
			return self::check( 'jobs', 'ok', __( 'No interrupted imports, overdue exams or stale deliveries.', 'course-captain' ) );
		}
		return self::check( 'jobs', 'warn', __( 'Interrupted jobs from the time of the backup: imports without heartbeat, exams past their deadline or deliveries with an expired lease. The maintenance run takes them over; resume or discard imports in the import screen.', 'course-captain' ), $items );
	}

	/**
	 * Site identity (hold mode, R0-16 §2.3).
	 *
	 * @param bool $storage Whether the private folder is usable (identity reads it).
	 * @return array{id: string, status: string, message: string, items: list<string>}
	 */
	private static function identity_check( bool $storage ): array {
		if ( ! $storage ) {
			return self::check( 'identity', 'warn', __( 'The site identity could not be compared because the private folder is missing.', 'course-captain' ) );
		}
		$identity = SiteIdentity::evaluate();
		if ( ! $identity['held'] ) {
			return self::check( 'identity', 'ok', 'unrecorded' === $identity['state'] ? __( 'The site identity is not recorded yet.', 'course-captain' ) : __( 'The site identity is unchanged.', 'course-captain' ) );
		}
		return self::check( 'identity', 'warn', __( 'The site identity differs: hold mode is active, messages are held. Confirm the identity in the system status or with "wp course-captain identity".', 'course-captain' ), array_values( array_map( 'strval', $identity['changed'] ) ) );
	}

	/**
	 * First column of a query.
	 *
	 * @param Database $db  Database.
	 * @param string   $sql Prepared SQL.
	 * @return list<string>
	 */
	private static function ids( Database $db, string $sql ): array {
		return array_values( array_map( static fn( array $row ): string => (string) reset( $row ), $db->rows( $sql ) ) );
	}

	/**
	 * At most MAX_ITEMS items.
	 *
	 * @param array $items Items.
	 * @phpstan-param list<string> $items
	 * @return list<string>
	 */
	private static function limit( array $items ): array {
		return array_slice( $items, 0, self::MAX_ITEMS );
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
