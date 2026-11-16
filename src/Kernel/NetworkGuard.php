<?php
/**
 * Multisite boundary: per-site activation only (E-07, E-49).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks network activation before anything is stored (R0-25 MS-R-01) and
 * detects a forced network activation (MS-R-02).
 */
final class NetworkGuard {

	/**
	 * Whether an activation request must be refused.
	 *
	 * WordPress passes `$network_wide = true` for "Network Activate" and for
	 * `wp plugin activate --network`. The activation error page re-runs the
	 * activation hook without arguments (`action=error_scrape`); inside the network
	 * admin that run is refused as well.
	 *
	 * @param bool $network_wide Value passed to the activation hook.
	 * @return bool
	 */
	public static function must_block_activation( bool $network_wide ): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		return $network_wide || is_network_admin();
	}

	/**
	 * Message shown when network activation is refused.
	 *
	 * @return string
	 */
	public static function blocked_message(): string {
		return __( 'Course Captain cannot be network activated. Activate it on each site individually instead. Nothing has been changed.', 'course-captain' );
	}

	/**
	 * Whether the plugin is listed in `active_sitewide_plugins` (forced network activation).
	 *
	 * @param string $basename Plugin basename, e.g. "course-captain/course-captain.php".
	 * @return bool
	 */
	public static function is_network_forced( string $basename ): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		$sitewide = get_site_option( 'active_sitewide_plugins', array() );
		return is_array( $sitewide ) && array_key_exists( $basename, $sitewide );
	}

	/**
	 * Remove the "Network Activate" link from the network plugin list (MS-R-01, MS-003).
	 *
	 * @param array<string, string>|mixed $actions Action links keyed by action.
	 * @return array<string, string>|mixed
	 */
	public static function filter_network_action_links( $actions ) {
		if ( is_array( $actions ) ) {
			unset( $actions['activate'] );
		}
		return $actions;
	}
}
