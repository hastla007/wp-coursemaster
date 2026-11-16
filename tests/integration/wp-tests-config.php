<?php
/**
 * WordPress test suite configuration (no secrets; values come from the environment).
 *
 * Defaults match the local MariaDB setup described in docs/r1/R1-01-nachweis.md;
 * CI sets WP_TESTS_DB_* explicitly.
 *
 * @package CourseCaptain
 */

$ccap_env = static function ( string $name, string $fallback ): string {
	$value = getenv( $name );
	return false === $value || '' === $value ? $fallback : $value;
};

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/vendor/roots/wordpress-no-content/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG', true );

define( 'DB_NAME', $ccap_env( 'WP_TESTS_DB_NAME', 'ccap_test' ) );
define( 'DB_USER', $ccap_env( 'WP_TESTS_DB_USER', 'ccap' ) );
define( 'DB_PASSWORD', $ccap_env( 'WP_TESTS_DB_PASSWORD', 'ccap' ) );
define( 'DB_HOST', $ccap_env( 'WP_TESTS_DB_HOST', 'localhost' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// G-09 (R0-18): never run against a database whose name does not contain "test".
if ( false === strpos( DB_NAME, 'test' ) ) {
	fwrite( STDERR, 'Refusing to run: DB_NAME must contain "test".' . PHP_EOL );
	exit( 1 );
}

// Plugins live next to the repository checkout, so the plugin basename is "<checkout>/course-captain.php".
define( 'WP_PLUGIN_DIR', dirname( __DIR__, 3 ) );
define( 'WP_HTTP_BLOCK_EXTERNAL', true );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Course Captain Test' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
