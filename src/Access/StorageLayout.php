<?php
/**
 * Storage layout: move the class folders when the base changes (R0-10 §4.1, §4.4; R0-05 GM-113, GM-114).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * The option `course_captain_storage_layout` records the base the class
 * folders (`protected-<secret>`, `packages-<secret>`, `media`) live in.
 * Without the option the default below `uploads` is the recorded base
 * (legacy layout). When the configured base differs and the recorded base
 * still holds class folders, they are moved: `rename`, else copy, check
 * and only then delete the source; symlinks are never followed. Absolute
 * attachment paths (`_wp_attached_file`) and package copies of import
 * batches (`ccap_import_batch.storage_path`) are rewritten. If the move
 * fails, everything already moved goes back, the previous base stays
 * active, the failure is recorded (Site Health, audit) and the next
 * attempt waits one hour. Moving back (private folder removed) is the same
 * path in the other direction.
 */
final class StorageLayout {

	public const OPTION      = 'course_captain_storage_layout';
	public const RETRY_AFTER = 3600;

	/**
	 * Test seam: `fn( string $op, string $path ): bool`, false makes the
	 * operation (`rename`, `copy`) fail.
	 *
	 * @var callable|null
	 */
	private static $fault = null;

	/**
	 * Re-entrancy guard.
	 *
	 * @var bool
	 */
	private static bool $busy = false;

	/**
	 * Inject a fault (tests only).
	 *
	 * @internal
	 * @param callable|null $fault Fault or null.
	 * @return void
	 */
	public static function with_fault( ?callable $fault ): void {
		self::$fault = $fault;
	}

	/**
	 * Recorded state.
	 *
	 * @return array{base: string, target: string, failed_at: int, error: string, moved_at: int}
	 */
	public static function state(): array {
		$state = get_option( self::OPTION );
		$state = is_array( $state ) ? $state : array();
		return array(
			'base'      => (string) ( $state['base'] ?? '' ),
			'target'    => (string) ( $state['target'] ?? '' ),
			'failed_at' => (int) ( $state['failed_at'] ?? 0 ),
			'error'     => (string) ( $state['error'] ?? '' ),
			'moved_at'  => (int) ( $state['moved_at'] ?? 0 ),
		);
	}

	/**
	 * Class folder names below a base.
	 *
	 * @return list<string>
	 */
	public static function folders(): array {
		$secret = ProtectedStorage::secret();
		return array( 'protected-' . $secret, 'packages-' . $secret, 'media' );
	}

	/**
	 * Active base for the configured one; moves the folders when needed.
	 *
	 * @param string $desired  Configured base.
	 * @param string $fallback Default base (legacy layout).
	 * @return string
	 */
	public static function resolve( string $desired, string $fallback ): string {
		if ( self::$busy ) {
			return $desired;
		}
		$state    = self::state();
		$recorded = '' === $state['base'] ? $fallback : $state['base'];
		if ( self::same( $recorded, $desired ) ) {
			if ( '' !== $state['base'] && ( $state['failed_at'] > 0 || $state['base'] !== $desired ) ) {
				self::save( $desired, array() );
			}
			return $desired;
		}
		if ( ! self::has_folders( $recorded ) ) {
			self::save( $desired, array( 'moved_at' => $state['moved_at'] ) );
			return $desired;
		}
		if ( $state['failed_at'] > 0 && self::same( $state['target'], $desired ) && time() < $state['failed_at'] + self::RETRY_AFTER ) {
			// The previous folder stays active until the waiting time is over.
			return $recorded;
		}
		self::$busy = true;
		try {
			$moved = self::move( $recorded, $desired );
			self::save( $desired, array( 'moved_at' => time() ) );
			AuditLog::write( Database::current(), Actor::cron(), 'storage_moved', 'storage', 'layout', null, array( 'folders' => $moved ) );
			return $desired;
		} catch ( \Throwable $e ) {
			$code = $e instanceof CourseException ? $e->error_code : 'ccap_storage_move_failed';
			self::save(
				$recorded,
				array(
					'target'    => $desired,
					'failed_at' => time(),
					'error'     => $code,
				)
			);
			AuditLog::write( Database::current(), Actor::cron(), 'storage_move_failed', 'storage', 'layout', null, array( 'error' => $code ) );
			return $recorded;
		} finally {
			self::$busy = false;
		}
	}

	/**
	 * Move the class folders from one base to another.
	 *
	 * @param string $from Old base.
	 * @param string $to   New base.
	 * @return int Number of moved folders.
	 * @throws CourseException When a folder cannot be moved (nothing stays half moved).
	 * @throws \Throwable Never: every failure is converted to a CourseException.
	 */
	public static function move( string $from, string $to ): int {
		if ( ! is_dir( $to ) && ! wp_mkdir_p( $to ) ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Target folder not writable.', 500 );
		}
		$done = array();
		try {
			foreach ( self::folders() as $name ) {
				$source = $from . '/' . $name;
				$target = $to . '/' . $name;
				if ( ! is_dir( $source ) || is_link( $source ) ) {
					continue;
				}
				if ( file_exists( $target ) ) {
					if ( ! is_dir( $target ) || is_link( $target ) || array() !== self::entries( $target ) ) {
						throw new CourseException( 'ccap_storage_target_occupied', 'The target folder already exists.', 409 );
					}
					rmdir( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Empty own folder.
				}
				self::move_dir( $source, $target );
				$done[ $target ] = $source;
			}
		} catch ( \Throwable $e ) {
			$fault       = self::$fault;
			self::$fault = null;
			try {
				foreach ( array_reverse( $done, true ) as $target => $source ) {
					self::move_dir( $target, $source );
				}
			} finally {
				self::$fault = $fault;
			}
			throw self::failure( $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: machine code for the caller.
		}
		self::rewrite_paths( $from, $to );
		return count( $done );
	}

	/**
	 * Whether a base holds any class folder.
	 *
	 * @param string $base Base.
	 * @return bool
	 */
	private static function has_folders( string $base ): bool {
		foreach ( self::folders() as $name ) {
			if ( is_dir( $base . '/' . $name ) && ! is_link( $base . '/' . $name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Move one folder: rename, else copy + check + delete the source.
	 *
	 * @param string $source Source.
	 * @param string $target Target (does not exist).
	 * @return void
	 * @throws CourseException When copying fails (the partial copy is removed).
	 */
	private static function move_dir( string $source, string $target ): void {
		if ( self::allowed( 'rename', $source ) && @rename( $source, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Fallback below.
			return;
		}
		try {
			self::copy_tree( $source, $target );
		} catch ( \Throwable $e ) {
			self::remove_tree( $target );
			throw self::failure( $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: machine code for the caller.
		}
		self::remove_tree( $source );
	}

	/**
	 * Copy a folder tree (no symlinks) and check every size.
	 *
	 * @param string $source Source.
	 * @param string $target Target.
	 * @return void
	 * @throws CourseException On the first failing file.
	 */
	private static function copy_tree( string $source, string $target ): void {
		if ( ! is_dir( $target ) && ! wp_mkdir_p( $target ) ) {
			throw new CourseException( 'ccap_storage_move_failed', 'Target folder not writable.', 500 );
		}
		foreach ( self::entries( $source ) as $entry ) {
			$from = $source . '/' . $entry;
			$to   = $target . '/' . $entry;
			if ( is_link( $from ) ) {
				continue;
			}
			if ( is_dir( $from ) ) {
				self::copy_tree( $from, $to );
				continue;
			}
			if ( ! self::allowed( 'copy', $from ) || ! @copy( $from, $to ) || filesize( $from ) !== filesize( $to ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reported by the exception.
				throw new CourseException( 'ccap_storage_move_failed', 'Copying a file failed.', 500 );
			}
		}
	}

	/**
	 * Remove a tree without following symlinks.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function remove_tree( string $dir ): void {
		if ( is_link( $dir ) || is_file( $dir ) ) {
			wp_delete_file( $dir );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( self::entries( $dir ) as $entry ) {
			$path = $dir . '/' . $entry;
			if ( is_link( $path ) ) {
				unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove the link itself, never its target.
			} else {
				self::remove_tree( $path );
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Own folder, emptied above.
	}

	/**
	 * Directory entries without `.`/`..`.
	 *
	 * @param string $dir Directory.
	 * @return list<string>
	 */
	private static function entries( string $dir ): array {
		$list = scandir( $dir );
		return false === $list ? array() : array_values( array_diff( $list, array( '.', '..' ) ) );
	}

	/**
	 * Rewrite absolute attachment and package copy paths below the old base.
	 *
	 * @param string $from Old base.
	 * @param string $to   New base.
	 * @return void
	 */
	private static function rewrite_paths( string $from, string $to ): void {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s", $wpdb->esc_like( $from . '/' ) . '%' ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off rewrite after a move.
		foreach ( (array) $rows as $row ) {
			update_post_meta( (int) $row['post_id'], '_wp_attached_file', $to . substr( (string) $row['meta_value'], strlen( $from ) ) );
		}
		// Package copies of import batches move with `packages-<secret>` (found by `backup-check`, R1-18 h3).
		$db    = Database::current();
		$table = $db->table( 'ccap_import_batch' );
		$db->execute( $db->prepare( "UPDATE `{$table}` SET storage_path = CONCAT(%s, SUBSTRING(storage_path, %d)) WHERE storage_path LIKE %s", $to, strlen( $from ) + 1, $wpdb->esc_like( $from . '/' ) . '%' ) );
	}

	/**
	 * Failure as course exception.
	 *
	 * @param \Throwable $e Cause.
	 * @return CourseException
	 */
	private static function failure( \Throwable $e ): CourseException {
		return $e instanceof CourseException ? $e : new CourseException( 'ccap_storage_move_failed', 'Moving the folders failed.', 500 );
	}

	/**
	 * Fault check.
	 *
	 * @param string $op   Operation.
	 * @param string $path Path.
	 * @return bool
	 */
	private static function allowed( string $op, string $path ): bool {
		return null === self::$fault || false !== ( self::$fault )( $op, $path );
	}

	/**
	 * Compare two paths.
	 *
	 * @param string $a Path.
	 * @param string $b Path.
	 * @return bool
	 */
	private static function same( string $a, string $b ): bool {
		return untrailingslashit( wp_normalize_path( $a ) ) === untrailingslashit( wp_normalize_path( $b ) );
	}

	/**
	 * Persist the state.
	 *
	 * @param string               $base  Active base.
	 * @param array<string, mixed> $extra Extra fields.
	 * @return void
	 */
	private static function save( string $base, array $extra ): void {
		update_option( self::OPTION, array( 'base' => $base ) + $extra, false );
	}
}
