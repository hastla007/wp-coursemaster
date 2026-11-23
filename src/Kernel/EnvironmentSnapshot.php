<?php
/**
 * Facts about the runtime environment that the requirement check evaluates.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable snapshot, so that the evaluation stays a pure function (unit tests).
 */
final readonly class EnvironmentSnapshot {

	/**
	 * Constructor.
	 *
	 * @param string      $php_version       PHP version (PHP_VERSION).
	 * @param string      $wp_version        WordPress version.
	 * @param string|null $db_server_version Raw `SELECT VERSION()` result, null when the database was not inspected.
	 * @param bool        $innodb            Whether the InnoDB engine is available.
	 * @param bool        $json_functions    Whether JSON_VALID() works.
	 * @param bool        $utf8mb4           Whether the connection supports utf8mb4.
	 */
	public function __construct(
		public string $php_version,
		public string $wp_version,
		public ?string $db_server_version = null,
		public bool $innodb = true,
		public bool $json_functions = true,
		public bool $utf8mb4 = true,
	) {
	}

	/**
	 * Snapshot of PHP and WordPress only (cheap, used on every request).
	 *
	 * @return self
	 */
	public static function runtime(): self {
		return new self( PHP_VERSION, self::wp_version() );
	}

	/**
	 * Full snapshot including the database (used on activation).
	 *
	 * @return self
	 */
	public static function with_database(): self {
		global $wpdb;

		// Requirement probes run once during activation; caching would be wrong here.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$version = $wpdb->get_var( 'SELECT VERSION()' );
		$engine  = $wpdb->get_var( "SELECT SUPPORT FROM information_schema.ENGINES WHERE ENGINE = 'InnoDB'" );
		$json    = $wpdb->get_var( "SELECT JSON_VALID('{\"a\":1}')" );
		// phpcs:enable

		return new self(
			PHP_VERSION,
			self::wp_version(),
			is_string( $version ) ? $version : '',
			is_string( $engine ) && in_array( strtoupper( $engine ), array( 'YES', 'DEFAULT' ), true ),
			'1' === (string) $json,
			(bool) $wpdb->has_cap( 'utf8mb4' )
		);
	}

	/**
	 * WordPress version without loading version.php twice.
	 *
	 * @return string
	 */
	private static function wp_version(): string {
		$version = $GLOBALS['wp_version'] ?? get_bloginfo( 'version' );
		return is_string( $version ) ? $version : '0';
	}
}
