<?php
/**
 * Bootstrap of the integration suite (WordPress test suite, E-11).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

// The wp-phpunit package reads its configuration path from the environment.
putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv

$ccap_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( false === $ccap_tests_dir || '' === $ccap_tests_dir ) {
	$ccap_tests_dir = dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
}

require_once $ccap_tests_dir . '/includes/functions.php';

// R1-40: enables the migration fault points (course_captain_migration_fault, R0-37 §7).
define( 'COURSE_CAPTAIN_TESTING', true );

/*
 * LG-1 (R0-39 §1.3, R0-18 G-07): every outbound HTTP request is blocked and
 * recorded; the base test case fails a test that caused one.
 */
$GLOBALS['ccap_test_http_requests'] = array();
tests_add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		unset( $pre, $args );
		$GLOBALS['ccap_test_http_requests'][] = (string) $url;
		return new WP_Error( 'ccap_test_http_blocked', 'Outbound HTTP is blocked in tests.' );
	},
	1,
	3
);

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/course-captain.php';
	}
);

require $ccap_tests_dir . '/includes/bootstrap.php';
