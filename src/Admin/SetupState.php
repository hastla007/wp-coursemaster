<?php
/**
 * Progress of the setup assistant (R0-30 §2.1 `course_captain_setup_state`,
 * R0-27 §2.7 system state).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Not a setting: never exported, autoload off, one per site. Has its own
 * `_rev` for parallel admins; unknown step keys of a newer version are
 * kept on every write (passthrough like R0-27 §2.3).
 */
final class SetupState {

	public const OPTION = 'course_captain_setup_state';
	public const SCHEMA = 1;

	/**
	 * Steps in order (R0-30 §2.2).
	 *
	 * @var list<string>
	 */
	public const STEPS = array( 'purpose_language', 'addresses', 'enrollment', 'learning_exams', 'mail_privacy', 'hardening', 'first_course', 'readiness' );

	/**
	 * Purposes (step 1).
	 *
	 * @var list<string>
	 */
	public const PURPOSES = array( 'internal_training', 'customer_courses', 'exam_prep', 'other' );

	/**
	 * Current state (normalized; nothing is written).
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$raw   = get_option( self::OPTION );
		$raw   = is_array( $raw ) ? $raw : array();
		$steps = is_array( $raw['steps'] ?? null ) ? $raw['steps'] : array();
		foreach ( self::STEPS as $step ) {
			$entry           = is_array( $steps[ $step ] ?? null ) ? $steps[ $step ] : array();
			$status          = (string) ( $entry['status'] ?? 'pending' );
			$entry['status'] = in_array( $status, array( 'pending', 'saved', 'skipped' ), true ) ? $status : 'pending';
			$steps[ $step ]  = $entry;
		}
		$status  = (string) ( $raw['status'] ?? 'not_started' );
		$purpose = $raw['purpose'] ?? null;
		return array_merge(
			$raw,
			array(
				'_schema'      => max( self::SCHEMA, (int) ( $raw['_schema'] ?? self::SCHEMA ) ),
				'_rev'         => (int) ( $raw['_rev'] ?? 0 ),
				'status'       => in_array( $status, array( 'not_started', 'in_progress', 'completed', 'completed_with_issues' ), true ) ? $status : 'not_started',
				'purpose'      => is_string( $purpose ) && in_array( $purpose, self::PURPOSES, true ) ? $purpose : null,
				'current_step' => in_array( $raw['current_step'] ?? '', self::STEPS, true ) ? (string) $raw['current_step'] : self::STEPS[0],
				'steps'        => $steps,
				'created'      => array_merge(
					array(
						'catalog_page_id' => 0,
						'first_course_id' => 0,
					),
					is_array( $raw['created'] ?? null ) ? $raw['created'] : array()
				),
				'dismissed_by' => array_values( array_map( 'intval', is_array( $raw['dismissed_by'] ?? null ) ? $raw['dismissed_by'] : array() ) ),
			)
		);
	}

	/**
	 * Write the state when the revision still matches.
	 *
	 * @param array<string, mixed> $state   New state.
	 * @param int                  $user_id Writer.
	 * @param int                  $rev     Revision the change is based on.
	 * @return bool False on a conflict.
	 */
	public static function write( array $state, int $user_id, int $rev ): bool {
		if ( self::get()['_rev'] !== $rev ) {
			return false;
		}
		$state['_rev']       = $rev + 1;
		$state['updated_at'] = gmdate( 'c' );
		$state['updated_by'] = $user_id;
		if ( empty( $state['started_by'] ) ) {
			$state['started_by'] = $user_id;
		}
		update_option( self::OPTION, $state, false );
		return true;
	}

	/**
	 * Mark a step and move the pointer to the next open step.
	 *
	 * @param string $step    Step.
	 * @param string $status  saved|skipped.
	 * @param int    $user_id User.
	 * @param array  $extra   Extra top-level values (`purpose`, `created`).
	 * @phpstan-param array<string, mixed> $extra
	 * @return bool
	 */
	public static function mark( string $step, string $status, int $user_id, array $extra = array() ): bool {
		$state                   = self::get();
		$rev                     = $state['_rev'];
		$state['steps'][ $step ] = array(
			'status' => $status,
			'at'     => gmdate( 'c' ),
			'by'     => $user_id,
		);
		$state                   = array_merge( $state, $extra );
		$state['current_step']   = self::next_after( $step, $state );
		if ( 'not_started' === ( $state['status'] ?? 'not_started' ) ) {
			$state['status'] = 'in_progress';
		}
		return self::write( $state, $user_id, $rev );
	}

	/**
	 * First pending step after a step (wrapping around), else the last step.
	 *
	 * @param string               $step  Step.
	 * @param array<string, mixed> $state State.
	 * @return string
	 */
	public static function next_after( string $step, array $state ): string {
		$index = (int) array_search( $step, self::STEPS, true );
		$count = count( self::STEPS );
		for ( $i = 1; $i < $count; $i++ ) {
			$candidate = self::STEPS[ ( $index + $i ) % $count ];
			if ( 'pending' === ( $state['steps'][ $candidate ]['status'] ?? 'pending' ) && 'readiness' !== $candidate ) {
				return $candidate;
			}
		}
		return 'readiness';
	}

	/**
	 * Step to open when the assistant is opened without a step (AD-T02).
	 *
	 * @return string
	 */
	public static function resume_step(): string {
		return self::get()['current_step'];
	}

	/**
	 * 1-based number of a step.
	 *
	 * @param string $step Step.
	 * @return int
	 */
	public static function number( string $step ): int {
		return (int) array_search( $step, self::STEPS, true ) + 1;
	}
}
