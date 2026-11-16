<?php
/**
 * Plugin Name:       Course Captain
 * Plugin URI:        https://wordpress.org/plugins/course-captain/
 * Description:       Neutral learning management for WordPress: courses, lessons, exams and certificates without required add-ons.
 * Version:           1.0.0-dev
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Author:            Course Captain
 * Author URI:        https://wordpress.org/plugins/course-captain/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       course-captain
 * Domain Path:       /languages
 *
 * Copyright (C) 2026 Course Captain contributors.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * IMPORTANT: This file must stay parseable on old PHP versions (see
 * tools/qa/phpcs-bootstrap-compat.xml). Only after the PHP version check
 * below has passed may code that needs PHP 8.3 be loaded.
 *
 * @package CourseCaptain
 */

defined( 'ABSPATH' ) || exit;

define( 'COURSE_CAPTAIN_VERSION', '1.0.0-dev' );
define( 'CourseCaptain\VERSION', COURSE_CAPTAIN_VERSION );
define( 'COURSE_CAPTAIN_CONTRACT_VERSION', '0.1.0' );
define( 'COURSE_CAPTAIN_MIN_PHP', '8.3.0' );
define( 'COURSE_CAPTAIN_MIN_WP', '7.0' );
define( 'COURSE_CAPTAIN_FILE', __FILE__ );
define( 'COURSE_CAPTAIN_DIR', __DIR__ );

if ( version_compare( PHP_VERSION, COURSE_CAPTAIN_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', 'course_captain_php_too_old_notice' );
	register_activation_hook( __FILE__, 'course_captain_php_too_old_activation' );
	return;
}

require_once __DIR__ . '/src/autoload.php';

register_activation_hook( __FILE__, array( 'CourseCaptain\Kernel\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CourseCaptain\Kernel\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'CourseCaptain\Kernel\Plugin', 'boot_instance' ), 20 );

/**
 * Text of the "PHP too old" message (old-PHP-safe).
 *
 * @return string
 */
function course_captain_php_too_old_message() {
	return sprintf(
		/* translators: 1: required PHP version, 2: current PHP version. */
		__( 'Course Captain requires PHP %1$s or newer. This site runs PHP %2$s. The plugin stays inactive and has not changed any data.', 'course-captain' ),
		COURSE_CAPTAIN_MIN_PHP,
		PHP_VERSION
	);
}

/**
 * Admin notice shown when the plugin files are present on a too old PHP.
 *
 * @return void
 */
function course_captain_php_too_old_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' . esc_html( course_captain_php_too_old_message() ) . '</p></div>';
}

/**
 * Activation callback on a too old PHP: abort before anything is written.
 *
 * @return void
 */
function course_captain_php_too_old_activation() {
	wp_die(
		esc_html( course_captain_php_too_old_message() ),
		esc_html__( 'Course Captain cannot be activated', 'course-captain' ),
		array( 'back_link' => true )
	);
}
