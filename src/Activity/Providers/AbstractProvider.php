<?php
/**
 * Shared defaults of the core providers.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity\Providers;

use CourseCaptain\Activity\ActivityProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Contract 1.0.0, settings version 1, conservative capabilities.
 */
abstract class AbstractProvider implements ActivityProvider {

	/**
	 * Contract version.
	 *
	 * @return string
	 */
	public function contract_version(): string {
		return '1.0.0';
	}

	/**
	 * Settings version.
	 *
	 * @return int
	 */
	public function settings_version(): int {
		return 1;
	}

	/**
	 * Default capabilities, overridden per provider.
	 *
	 * @return array<string, bool|string>
	 */
	public function capabilities(): array {
		return array(
			'completable'            => true,
			'gradable'               => false,
			'feeds_mastery'          => false,
			'produces_evidence'      => false,
			'access_scope'           => 'view',
			'drip_aware'             => true,
			'preview_limit_type'     => 'first_items',
			'needs_external_consent' => false,
		);
	}

	/**
	 * Started or not, when nothing else decides.
	 *
	 * @param array<string, mixed> $progress Progress.
	 * @return string
	 */
	protected static function started_or_not( array $progress ): string {
		return empty( $progress['started'] ) ? 'not_started' : 'in_progress';
	}

	/**
	 * Completed when a flag is set, else started/not started.
	 *
	 * @param array<string, mixed> $progress Progress.
	 * @param string               $flag     Flag.
	 * @return string
	 */
	protected static function by_flag( array $progress, string $flag ): string {
		return ! empty( $progress[ $flag ] ) ? 'completed' : self::started_or_not( $progress );
	}
}
