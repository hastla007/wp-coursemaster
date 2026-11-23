<?php
/**
 * Uninstall entry point.
 *
 * By default all data stays; only cron events, transients and rewrite rules
 * are removed. Data is deleted only where the confirmed setting
 * `delete_data_on_uninstall` is enabled (R0-16 §2.5, E-89).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( version_compare( PHP_VERSION, '8.5.0', '<' ) ) {
	return;
}

require_once __DIR__ . '/src/autoload.php';

CourseCaptain\Kernel\Uninstaller::run();
