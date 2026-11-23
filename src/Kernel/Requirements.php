<?php
/**
 * Minimum version and environment requirements.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

defined( 'ABSPATH' ) || exit;

/**
 * Pure evaluation of the platform matrix (R0-20 §1.2, E-08, E-58, E-195).
 *
 * Below the hard minimum, activation is refused before anything is written.
 * Between the hard minimum and "supported from" the database is accepted as
 * best effort; the system status (R1-38) reads {@see self::database_support_level()}.
 */
final class Requirements {

	public const MIN_PHP = '8.5.0';
	public const MIN_WP  = '7.0';

	/** Hard minimum MariaDB (E-08). */
	public const MIN_MARIADB = '10.6.0';

	/** Hard minimum MySQL (E-08). */
	public const MIN_MYSQL = '8.0.0';

	/** MariaDB "supported from" (E-58, raised by MINVER-1 on 17.11.2026; older servers only warn). */
	public const SUPPORTED_MARIADB = '12.3.0';

	/** MySQL "supported from" (E-58, raised by MINVER-1 on 17.11.2026; older servers only warn). */
	public const SUPPORTED_MYSQL = '9.7.0';

	public const LEVEL_SUPPORTED   = 'supported';
	public const LEVEL_BEST_EFFORT = 'best_effort';
	public const LEVEL_UNSUPPORTED = 'unsupported';

	/**
	 * Evaluate a snapshot.
	 *
	 * @param EnvironmentSnapshot $env Environment facts.
	 * @return list<RequirementFailure> Empty when all requirements are met.
	 */
	public static function evaluate( EnvironmentSnapshot $env ): array {
		$failures = array();

		if ( version_compare( $env->php_version, self::MIN_PHP, '<' ) ) {
			$failures[] = new RequirementFailure( RequirementFailure::PHP, array( self::MIN_PHP, $env->php_version ) );
		}

		if ( version_compare( self::normalise_wp_version( $env->wp_version ), self::MIN_WP, '<' ) ) {
			$failures[] = new RequirementFailure( RequirementFailure::WORDPRESS, array( self::MIN_WP, $env->wp_version ) );
		}

		if ( null === $env->db_server_version ) {
			return $failures;
		}

		if ( self::LEVEL_UNSUPPORTED === self::database_support_level( $env->db_server_version ) ) {
			$failures[] = new RequirementFailure(
				RequirementFailure::DATABASE,
				array( $env->db_server_version, self::MIN_MARIADB, self::MIN_MYSQL )
			);
		}

		if ( ! $env->innodb ) {
			$failures[] = new RequirementFailure( RequirementFailure::INNODB );
		}

		if ( ! $env->json_functions ) {
			$failures[] = new RequirementFailure( RequirementFailure::JSON );
		}

		if ( ! $env->utf8mb4 ) {
			$failures[] = new RequirementFailure( RequirementFailure::UTF8MB4 );
		}

		return $failures;
	}

	/**
	 * Support level of a database server version string.
	 *
	 * @param string $server_version Raw `SELECT VERSION()` result.
	 * @return string One of the LEVEL_* constants.
	 */
	public static function database_support_level( string $server_version ): string {
		$parsed = self::parse_database_version( $server_version );
		if ( null === $parsed ) {
			return self::LEVEL_UNSUPPORTED;
		}

		list( $vendor, $version ) = $parsed;
		$minimum                  = 'mariadb' === $vendor ? self::MIN_MARIADB : self::MIN_MYSQL;
		$supported                = 'mariadb' === $vendor ? self::SUPPORTED_MARIADB : self::SUPPORTED_MYSQL;

		if ( version_compare( $version, $minimum, '<' ) ) {
			return self::LEVEL_UNSUPPORTED;
		}
		if ( version_compare( $version, $supported, '<' ) ) {
			return self::LEVEL_BEST_EFFORT;
		}
		return self::LEVEL_SUPPORTED;
	}

	/**
	 * Split a server version string into vendor and numeric version.
	 *
	 * @param string $server_version For example "10.11.6-MariaDB-1:10.11.6+maria~ubu2204" or "8.4.0".
	 * @return array{0: 'mariadb'|'mysql', 1: string}|null Null when the string is not recognised.
	 */
	public static function parse_database_version( string $server_version ): ?array {
		$raw = trim( $server_version );
		// Old replication-compatible prefix used by some MariaDB builds.
		if ( str_starts_with( $raw, '5.5.5-' ) ) {
			$raw = substr( $raw, 6 );
		}

		if ( 1 !== preg_match( '/^(\d+\.\d+(?:\.\d+)?)/', $raw, $match ) ) {
			return null;
		}

		$vendor = false !== stripos( $raw, 'mariadb' ) ? 'mariadb' : 'mysql';
		return array( $vendor, $match[1] );
	}

	/**
	 * Drop pre-release suffixes ("7.0-RC1" counts as 7.0) for the comparison.
	 *
	 * @param string $version WordPress version.
	 * @return string
	 */
	private static function normalise_wp_version( string $version ): string {
		if ( 1 === preg_match( '/^\d+(?:\.\d+){0,2}/', $version, $match ) ) {
			return $match[0];
		}
		return '0';
	}
}
