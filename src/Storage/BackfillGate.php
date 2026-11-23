<?php
/**
 * Gate for features that need backfilled data (R0-37 §3.3 W2, K-3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and maintains the autoload signal `course_captain_backfill_open`
 * (list of backfill step IDs that are not done yet).
 */
final class BackfillGate {

	/**
	 * Whether the backfill is complete. While the schema of this code is not
	 * current (W1) or no signal exists yet, nothing counts as done: a
	 * backfill introduced by the new code is only listed once its upgrade
	 * has started.
	 *
	 * @param string $step_id Backfill step ID.
	 * @return bool
	 */
	public static function done( string $step_id ): bool {
		if ( get_option( Migrator::FINGERPRINT ) !== Schema::fingerprint( COURSE_CAPTAIN_VERSION ) ) {
			return false;
		}
		$open = get_option( Migrator::BACKFILL_OPEN );
		return is_array( $open ) && ! in_array( $step_id, $open, true );
	}

	/**
	 * Open backfill IDs.
	 *
	 * @return list<string>
	 */
	public static function open_ids(): array {
		$open = get_option( Migrator::BACKFILL_OPEN );
		return is_array( $open ) ? array_values( array_map( 'strval', $open ) ) : array();
	}

	/**
	 * Mark backfills as open (before the structure phase of an upgrade).
	 *
	 * @param array $step_ids Step IDs.
	 * @phpstan-param list<string> $step_ids
	 * @return void
	 */
	public static function open( array $step_ids ): void {
		$open = array_values( array_unique( array_merge( self::open_ids(), $step_ids ) ) );
		update_option( Migrator::BACKFILL_OPEN, $open, true );
	}

	/**
	 * Mark a backfill as done.
	 *
	 * @param string $step_id Step ID.
	 * @return void
	 */
	public static function close( string $step_id ): void {
		update_option( Migrator::BACKFILL_OPEN, array_values( array_diff( self::open_ids(), array( $step_id ) ) ), true );
	}
}
