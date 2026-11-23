<?php
/**
 * Stable pseudonyms for exports (SET-CC-26).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Privacy;

use CourseCaptain\Access\ProtectedStorage;

defined( 'ABSPATH' ) || exit;

/**
 * `P-` plus 12 hex characters of an HMAC over the user ID with the site
 * secret (generated once, never replaced): the same person gets the same
 * pseudonym in every export of this website, different websites give
 * different pseudonyms, and the user ID cannot be read back without the
 * secret. Names and email addresses never enter the value.
 */
final class Pseudonym {

	/**
	 * Pseudonym of a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function of( int $user_id ): string {
		return 'P-' . substr( hash_hmac( 'sha256', 'course-captain-export|' . $user_id, ProtectedStorage::secret() ), 0, 12 );
	}

	/**
	 * Whether exports are pseudonymised (`advanced.report_pseudonymize`).
	 *
	 * @return bool
	 */
	public static function exports_enabled(): bool {
		return true === \CourseCaptain\Settings\Settings::get( 'advanced.report_pseudonymize' );
	}
}
