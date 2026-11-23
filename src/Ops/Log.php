<?php
/**
 * Log files with level, automatic fall-back and retention (SET-CC-22;
 * R0-13 §2.3 DL-3/DL-5, E-143).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;

defined( 'ABSPATH' ) || exit;

/**
 * `advanced.log_level` (`error` < `warning` < `info` < `debug`, default
 * `warning`) decides which lines are written. `error`/`warning` go to
 * `cc-error-YYYYMMDD.log` (kept `advanced.error_log_days`), `info`/`debug`
 * to `cc-debug-YYYYMMDD.log` (kept `advanced.debug_file_days`), both in
 * `<protected>/logs/` (deny-all folder, never a public URL). A level above
 * the default falls back after {@see self::RESET_DAYS} days in the hourly
 * run (audit `settings_saved`, context `auto_reset`). Lines are JSON with
 * time, level, event and scalar context; email addresses are masked and
 * values are cut to 200 characters (no answers, tokens or secrets are ever
 * passed in).
 */
final class Log {

	/** Level order. */
	public const LEVELS = array(
		'error'   => 0,
		'warning' => 1,
		'info'    => 2,
		'debug'   => 3,
	);

	/** Days until `info`/`debug` fall back to the default (SET-CC-22). */
	public const RESET_DAYS = 7;

	/** Option: since when the current level above the default is active (Unix time). */
	public const SINCE_OPTION = 'course_captain_log_level_since';

	/** Rotation size per file (R0-13 DL-5). */
	public const MAX_BYTES = 5242880;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'add_option_' . Settings::OPTION_PREFIX . 'advanced', array( self::class, 'track' ), 10, 0 );
		add_action( 'update_option_' . Settings::OPTION_PREFIX . 'advanced', array( self::class, 'track' ), 10, 0 );
	}

	/**
	 * Effective level.
	 *
	 * @return string
	 */
	public static function level(): string {
		$level = (string) Settings::get( 'advanced.log_level' );
		return isset( self::LEVELS[ $level ] ) ? $level : self::default_level();
	}

	/**
	 * Default level of the register.
	 *
	 * @return string
	 */
	public static function default_level(): string {
		return (string) SettingsSchema::fields()['advanced']['log_level']['default'];
	}

	/**
	 * Whether a level is written with the current setting.
	 *
	 * @param string $level Level.
	 * @return bool
	 */
	public static function enabled( string $level ): bool {
		return isset( self::LEVELS[ $level ] ) && self::LEVELS[ $level ] <= self::LEVELS[ self::level() ];
	}

	/**
	 * Write one line (never throws).
	 *
	 * @param string               $level   Level.
	 * @param string               $event   Machine event key, for example `notification.failed`.
	 * @param array<string, mixed> $context Scalar context (no personal data).
	 * @return bool Written.
	 */
	public static function write( string $level, string $event, array $context = array() ): bool {
		if ( ! self::enabled( $level ) ) {
			return false;
		}
		try {
			$file = self::file( self::LEVELS[ $level ] <= self::LEVELS['warning'] ? 'error' : 'debug', time() );
			$line = (string) wp_json_encode(
				array(
					'time'    => gmdate( 'c' ),
					'level'   => $level,
					'event'   => sanitize_key( str_replace( '.', '_', $event ) ),
					'context' => self::scrub( $context ),
				)
			);
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Append to an own log file in the protected folder.
			return false !== file_put_contents( $file, $line . "\n", FILE_APPEND | LOCK_EX );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Log folder (`<protected>/logs`, created on demand).
	 *
	 * @return string
	 */
	public static function dir(): string {
		$dir = ProtectedStorage::dir( 'protected' ) . '/logs';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return $dir;
	}

	/**
	 * Current file of a channel, rotated at 5 MB (`-1`, `-2`, …).
	 *
	 * @param string $channel `error` or `debug`.
	 * @param int    $now     Unix time.
	 * @return string
	 */
	private static function file( string $channel, int $now ): string {
		$base = self::dir() . '/cc-' . $channel . '-' . gmdate( 'Ymd', $now );
		$file = $base . '.log';
		$i    = 1;
		while ( $i < 100 && is_file( $file ) && (int) filesize( $file ) >= self::MAX_BYTES ) {
			$file = $base . '-' . $i . '.log';
			++$i;
		}
		return $file;
	}

	/**
	 * Context without personal data patterns, scalar values only.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, string|int|float|bool|null>
	 */
	private static function scrub( array $context ): array {
		$out = array();
		foreach ( $context as $key => $value ) {
			if ( ! is_scalar( $value ) && null !== $value ) {
				continue;
			}
			if ( is_string( $value ) ) {
				$value = (string) preg_replace( '/[^\s@]+@[^\s@]+/', '***@***', $value );
				$value = mb_substr( $value, 0, 200 );
			}
			$out[ sanitize_key( (string) $key ) ] = $value;
		}
		return $out;
	}

	/**
	 * `update_option`/`add_option` of the advanced tab: remember when a level
	 * above the default was switched on (any path: form, import, CLI, reset).
	 *
	 * @return void
	 */
	public static function track(): void {
		if ( self::LEVELS[ self::level() ] <= self::LEVELS[ self::default_level() ] ) {
			delete_option( self::SINCE_OPTION );
			return;
		}
		if ( false === get_option( self::SINCE_OPTION ) ) {
			update_option( self::SINCE_OPTION, time(), false );
		}
	}

	/**
	 * Hourly: fall back to the default level after 7 days and delete old files.
	 *
	 * @param int|null $now Unix time (tests).
	 * @return array{reset: bool, deleted: int}
	 */
	public static function hourly( ?int $now = null ): array {
		$now = $now ?? time();
		return array(
			'reset'   => self::auto_reset( $now ),
			'deleted' => self::prune( $now ),
		);
	}

	/**
	 * Fall back to the default level when the higher level is older than 7 days.
	 *
	 * @param int $now Unix time.
	 * @return bool Reset.
	 */
	public static function auto_reset( int $now ): bool {
		if ( self::LEVELS[ self::level() ] <= self::LEVELS[ self::default_level() ] ) {
			return false;
		}
		$since = get_option( self::SINCE_OPTION );
		if ( false === $since ) {
			// Older installations without the marker: start the 7 days now.
			update_option( self::SINCE_OPTION, $now, false );
			return false;
		}
		if ( (int) $since + self::RESET_DAYS * DAY_IN_SECONDS > $now ) {
			return false;
		}
		Settings::save(
			Actor::cron(),
			'advanced',
			array( 'log_level' => self::default_level() ),
			array( 'log_level' ),
			Settings::rev( 'advanced' ),
			array( 'context' => 'auto_reset' )
		);
		delete_option( self::SINCE_OPTION );
		return true;
	}

	/**
	 * Delete log files after their retention (`error_log_days`, `debug_file_days`).
	 *
	 * @param int $now Unix time.
	 * @return int Deleted files.
	 */
	public static function prune( int $now ): int {
		$dir = ProtectedStorage::dir( 'protected' ) . '/logs';
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		$days    = array(
			'error' => (int) Settings::get( 'advanced.error_log_days' ),
			'debug' => (int) Settings::get( 'advanced.debug_file_days' ),
		);
		$deleted = 0;
		foreach ( (array) glob( $dir . '/cc-*.log' ) as $file ) {
			if ( ! is_string( $file ) || 1 !== preg_match( '/cc-(error|debug)-(\d{8})(?:-\d+)?\.log$/', $file, $m ) ) {
				continue;
			}
			$day = strtotime( $m[2] . ' 00:00:00 UTC' );
			// A file of day D holds lines up to D 23:59; it is due when its last line is older than the retention.
			if ( false !== $day && $day + DAY_IN_SECONDS + $days[ $m[1] ] * DAY_IN_SECONDS <= $now && wp_delete_file_from_directory( $file, $dir ) ) {
				++$deleted;
			}
		}
		return $deleted;
	}
}
