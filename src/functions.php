<?php
/**
 * Public PHP functions of the version handshake (R0-19 §1.6).
 *
 * Loaded by the kernel right before `course_captain_loaded_v1` fires.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'course_captain_contract_version' ) ) {
	/**
	 * Contract version of the running Course Captain core.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	function course_captain_contract_version(): string {
		return ( new CourseCaptain\Contract\Api() )->contract_version();
	}
}

if ( ! function_exists( 'course_captain_supports' ) ) {
	/**
	 * Whether the running core supports a feature key; unknown keys return false.
	 *
	 * @since 1.0.0
	 * @param string $feature Feature key.
	 * @return bool
	 */
	function course_captain_supports( string $feature ): bool {
		return ( new CourseCaptain\Contract\Api() )->supports( $feature );
	}
}
