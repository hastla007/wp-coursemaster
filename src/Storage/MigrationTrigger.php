<?php
/**
 * Pure decision function for schema migration triggers (R0-37 §2.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Decides per request context whether to run, schedule, wait or do nothing.
 * No side effects, fully unit-testable.
 */
final class MigrationTrigger {

	public const RUN      = 'run';
	public const SCHEDULE = 'schedule';
	public const WAIT     = 'wait';
	public const NONE     = 'none';
	public const BLOCKED  = 'blocked';

	public const CONTEXT_ACTIVATION = 'activation';
	public const CONTEXT_ADMIN      = 'admin';
	public const CONTEXT_CRON       = 'cron';
	public const CONTEXT_CLI        = 'cli';
	public const CONTEXT_FRONTEND   = 'frontend';

	/**
	 * Backoff after a failed run for admin/cron/overdue (R0-37 §4.6).
	 */
	public const BACKOFF_SECONDS = 900;

	/**
	 * A scheduled `ccap_migrate` overdue by more than this runs in one frontend request.
	 */
	public const OVERDUE_SECONDS = 300;

	/**
	 * Decide.
	 *
	 * @param string                                                                                                                                                                                             $context One of the CONTEXT_* constants.
	 * @param array{network_forced?: bool, stored_version?: string|null, code_version: string, fingerprint_current?: bool, steps_open?: bool, poison?: bool, last_failed_at?: int|null, scheduled_at?: int|null} $state Current state.
	 * @param int                                                                                                                                                                                                $now     Unix time.
	 * @return string One of RUN, SCHEDULE, WAIT, NONE, BLOCKED.
	 */
	public static function action( string $context, array $state, int $now ): string {
		if ( ! empty( $state['network_forced'] ) ) {
			return self::BLOCKED;
		}

		$stored = $state['stored_version'] ?? null;
		$code   = $state['code_version'];
		if ( null !== $stored && version_compare( Schema::semver_of( $stored ), Schema::semver_of( $code ), '>' ) ) {
			return self::NONE;
		}

		if ( ! empty( $state['fingerprint_current'] ) && $stored === $code && empty( $state['steps_open'] ) ) {
			return self::NONE;
		}

		if ( self::CONTEXT_CLI === $context ) {
			return self::RUN;
		}

		if ( ! empty( $state['poison'] ) ) {
			return self::NONE;
		}

		$failed = $state['last_failed_at'] ?? null;
		if ( null !== $failed && $failed + self::BACKOFF_SECONDS > $now ) {
			return self::WAIT;
		}

		if ( in_array( $context, array( self::CONTEXT_ACTIVATION, self::CONTEXT_ADMIN, self::CONTEXT_CRON ), true ) ) {
			return self::RUN;
		}

		$scheduled = $state['scheduled_at'] ?? null;
		if ( null !== $scheduled && $now - $scheduled > self::OVERDUE_SECONDS ) {
			return self::RUN;
		}
		return self::SCHEDULE;
	}
}
