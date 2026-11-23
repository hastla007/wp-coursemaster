<?php
/**
 * Object passed to `course_captain_loaded_v1`.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Contract;

defined( 'ABSPATH' ) || exit;

/**
 * Version handshake for extensions (R0-19 §1.6).
 *
 * @api
 * @since 1.0.0
 */
final class Api {


	/**
	 * Contract version (SemVer, not the plugin version; R0-19 §1.1 V-01).
	 *
	 * @return string
	 */
	public function contract_version(): string {
		return COURSE_CAPTAIN_CONTRACT_VERSION;
	}

	/**
	 * Whether a feature key is supported. Unknown keys return false.
	 *
	 * @param string $feature Feature key, e.g. "events.v1".
	 * @return bool
	 */
	public function supports( string $feature ): bool {
		return in_array( $feature, self::features(), true );
	}

	/**
	 * Feature keys supported by this build. Unknown keys are unsupported.
	 *
	 * Later tasks add their keys here (for example `certificates`); keys that
	 * need the paid add-on are never listed in Free (E-38).
	 *
	 * @return list<string>
	 */
	private static function features(): array {
		// SET-CC-01: add-on register fields and settings hint lines (docking points, contract v1).
		return array( 'certificates', 'settings.fields.v1', 'settings.hints.v1' );
	}
}
