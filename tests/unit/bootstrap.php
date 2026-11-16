<?php
/**
 * Bootstrap of the unit suite (no WordPress, functions mocked with Brain Monkey).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/ccap-unit-wordpress/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

// Constants of course-captain.php without loading WordPress.
require_once dirname( __DIR__, 2 ) . '/tools/qa/phpstan-bootstrap.php';
