<?php
/**
 * Mail type catalogue R1 (R0-27 §3.2, §3.10; E-216, E-217).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Notification;

defined( 'ABSPATH' ) || exit;

/**
 * Class (`required`/`informational`), operator default and the variable
 * allowlist of every R1 mail type. `wired` marks types whose triggering
 * event exists in the code today; the others (certificates, support
 * dialogs, transfers, assignments) are catalogued so that templates,
 * settings and opt-out already cover them, and are wired by the tasks that
 * add their events (R1-44, R1-38, R1-46).
 */
final class NotificationTypes {

	public const REQUIRED      = 'required';
	public const INFORMATIONAL = 'informational';

	/** Variables of every type (§3.10). */
	private const COMMON = array( 'site_name', 'site_url', 'learning_area_url', 'privacy_policy_url' );

	/** Variables holding URLs (escaped with `esc_url`). */
	public const URL_VARIABLES = array( 'site_url', 'learning_area_url', 'privacy_policy_url', 'unsubscribe_url', 'course_url', 'claim_url', 'certificate_url', 'verify_url', 'result_url' );

	/**
	 * Type => [class, default enabled, wired, extra variables].
	 *
	 * @var array<string, array{0: string, 1: bool, 2: bool, 3: list<string>}>
	 */
	private const TYPES = array(
		'access_granted'        => array( self::INFORMATIONAL, true, true, array( 'course_title', 'course_url' ) ),
		'seat_invitation'       => array( self::REQUIRED, true, true, array( 'group_name', 'inviter_display_name', 'invite_expires_at', 'claim_url' ) ),
		'seat_claimed'          => array( self::INFORMATIONAL, true, true, array( 'course_title', 'course_url' ) ),
		'course_completed'      => array( self::INFORMATIONAL, true, true, array( 'course_title', 'course_url', 'certificate_url', 'verify_url' ) ),
		'certificate_issued'    => array( self::INFORMATIONAL, true, false, array( 'course_title', 'course_url', 'certificate_url', 'verify_url' ) ),
		'certificate_corrected' => array( self::REQUIRED, true, false, array( 'certificate_url', 'verify_url', 'reason_label' ) ),
		'certificate_revoked'   => array( self::REQUIRED, true, false, array( 'reason_label' ) ),
		'attempt_voided'        => array( self::REQUIRED, true, false, array( 'reason_label', 'attempt_title', 'result_url' ) ),
		'attempt_annulled'      => array( self::REQUIRED, true, false, array( 'reason_label', 'attempt_title', 'result_url' ) ),
		'access_transferred'    => array( self::INFORMATIONAL, true, false, array( 'course_title', 'course_url' ) ),
		'grading_completed'     => array( self::INFORMATIONAL, true, true, array( 'course_title', 'course_url', 'attempt_title', 'result_url' ) ),
		'access_expired'        => array( self::INFORMATIONAL, false, true, array( 'course_title', 'course_url', 'access_end_at' ) ),
		'test_mail'             => array( self::REQUIRED, true, true, array() ),
		'assignment_submitted'  => array( self::INFORMATIONAL, true, false, array( 'course_title', 'course_url' ) ),
		'assignment_returned'   => array( self::INFORMATIONAL, true, false, array( 'course_title', 'course_url' ) ),
		'assignment_graded'     => array( self::INFORMATIONAL, true, false, array( 'course_title', 'course_url' ) ),
	);

	/**
	 * All type keys.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array_keys( self::TYPES );
	}

	/**
	 * Whether a type exists.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function exists( string $type ): bool {
		return isset( self::TYPES[ $type ] );
	}

	/**
	 * Class of a type.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function type_class( string $type ): string {
		return self::TYPES[ $type ][0] ?? self::REQUIRED;
	}

	/**
	 * Whether a type can be unsubscribed and switched off (E-216).
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function is_informational( string $type ): bool {
		return self::INFORMATIONAL === self::type_class( $type );
	}

	/**
	 * Informational types (opt-out and `emails.types`).
	 *
	 * @return list<string>
	 */
	public static function informational(): array {
		return array_values( array_filter( self::all(), array( self::class, 'is_informational' ) ) );
	}

	/**
	 * Operator default of an informational type (N-12 off, E-217).
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function default_enabled( string $type ): bool {
		return self::TYPES[ $type ][1] ?? false;
	}

	/**
	 * Whether the triggering event exists in R1 today.
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	public static function is_wired( string $type ): bool {
		return self::TYPES[ $type ][2] ?? false;
	}

	/**
	 * Variable allowlist of a type (§3.10).
	 *
	 * @param string $type Type.
	 * @return list<string>
	 */
	public static function variables( string $type ): array {
		if ( ! self::exists( $type ) ) {
			return array();
		}
		$vars = self::COMMON;
		if ( 'seat_invitation' !== $type ) {
			$vars[] = 'recipient_display_name';
		}
		if ( self::is_informational( $type ) ) {
			$vars[] = 'unsubscribe_url';
		}
		return array_values( array_unique( array_merge( $vars, self::TYPES[ $type ][3] ) ) );
	}

	/**
	 * Settings field definition of `emails.types` (R0-27 §1.3): only
	 * informational types are listed; any other key is rejected.
	 *
	 * @return array<string, mixed>
	 */
	public static function settings_field(): array {
		$fields  = array();
		$default = array();
		foreach ( self::informational() as $type ) {
			$fields[ $type ]  = array( 'type' => 'bool' );
			$default[ $type ] = self::default_enabled( $type );
		}
		return array(
			'type'    => 'object',
			'strict'  => true,
			'fields'  => $fields,
			'default' => $default,
		);
	}
}
