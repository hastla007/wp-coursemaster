<?php
/**
 * Business rules from the settings register (SET-CC-13, S2/E-346).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Business policy is a setting with a safe default plus a filter, and the
 * filter wins (S2). The defaults equal the former constants, so an
 * installation that never saved the fields behaves as before. Technical
 * values (time windows, token formats) stay constants.
 *
 * - `course_captain_business_rule( $value, $key )` may replace any value
 *   read here; a filtered value that fails the register validation is
 *   ignored (the setting stays effective).
 * - The older specific filters `course_captain_hardening_limits` and
 *   `course_captain_trusted_media_hosts` keep working and run last.
 */
final class BusinessRules {

	public const FILTER = 'course_captain_business_rule';

	/**
	 * Value of a register key with the generic filter applied.
	 *
	 * @param string $key Register key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		try {
			$field = EffectiveSettings::field( $key );
			$value = Settings::get( $key );
		} catch ( \Throwable $e ) {
			unset( $e );
			list( $tab, $name ) = array_pad( explode( '.', $key, 2 ), 2, '' );
			return SettingsSchema::fields()[ $tab ][ $name ]['default'] ?? null;
		}
		/**
		 * Business rule value (S2: the filter wins over the setting).
		 *
		 * @since 1.0.0
		 * @param mixed  $value Value from the settings register.
		 * @param string $key   Register key, e.g. `exams.starts_per_hour`.
		 */
		$filtered = apply_filters( 'course_captain_business_rule', $value, $key );
		if ( $filtered === $value ) {
			return $value;
		}
		$check = SettingsSchema::validate( $field, $filtered );
		return $check['ok'] ? $check['value'] : $value;
	}

	/**
	 * Exam starts per learner and hour (former `ExamSessions::START_LIMIT_HOUR` = 40).
	 *
	 * @return int
	 */
	public static function exam_starts_per_hour(): int {
		return max( 1, (int) self::get( 'exams.starts_per_hour' ) );
	}

	/**
	 * Lifetime of the known-device cookie in days (former `Hardening::DEVICE_DAYS` = 180).
	 *
	 * @return int
	 */
	public static function known_device_days(): int {
		return max( 1, (int) self::get( 'hardening.known_device_days' ) );
	}

	/**
	 * Validity of new group invitations in days (former `GroupService::INVITE_DAYS` = 30).
	 *
	 * @return int
	 */
	public static function invite_valid_days(): int {
		return max( 1, (int) self::get( 'enrollment.invite_valid_days' ) );
	}

	/**
	 * Smart trainer review intervals in days for streak 4, 5 and ≥ 6
	 * (former `SmartTrainer::REVIEW_INTERVAL_DAYS` = 7, 21, 60). Never
	 * decreasing: a later stage is at least as long as the one before.
	 *
	 * @return array{0: int, 1: int, 2: int}
	 */
	public static function review_intervals(): array {
		$value  = (array) self::get( 'learning.review_intervals' );
		$first  = max( 1, (int) ( $value['first'] ?? 7 ) );
		$second = max( $first, (int) ( $value['second'] ?? 21 ) );
		$third  = max( $second, (int) ( $value['third'] ?? 60 ) );
		return array( $first, $second, $third );
	}

	/**
	 * Trusted media hosts: register value, then the filter
	 * `course_captain_trusted_media_hosts` (wins).
	 *
	 * @return list<string>
	 */
	public static function trusted_media_hosts(): array {
		$hosts = array_values( array_map( 'strval', (array) self::get( 'privacy.trusted_media_hosts' ) ) );
		/**
		 * Hosts that do not count as foreign media hosts.
		 *
		 * @since 1.0.0
		 * @param list<string> $hosts Hosts from the setting `privacy.trusted_media_hosts`.
		 */
		return array_values( array_map( 'strtolower', array_map( 'strval', (array) apply_filters( 'course_captain_trusted_media_hosts', $hosts ) ) ) );
	}

	/**
	 * Hardening limits `key => [hits, window seconds]` (R1-45): the login
	 * hits from `hardening.login_limits` (defaults 10/5/30 = former
	 * constants), everything else from `Hardening::LIMITS`; the filter
	 * `course_captain_hardening_limits` wins. Invalid filter entries keep
	 * the previous value, so a limit can never disappear (fail closed).
	 *
	 * @return array<string, array{0: int, 1: int}>
	 */
	public static function hardening_limits(): array {
		$login  = (array) self::get( 'hardening.login_limits' );
		$limits = array();
		foreach ( \CourseCaptain\Security\Hardening::LIMITS as $key => $default ) {
			$sub            = 0 === strpos( $key, 'login_' ) ? substr( $key, 6 ) : '';
			$limits[ $key ] = array( '' !== $sub && isset( $login[ $sub ] ) ? max( 1, (int) $login[ $sub ] ) : $default[0], $default[1] );
		}
		/**
		 * Hardening limits: key => [hits, window seconds].
		 *
		 * @param array<string, array{0: int, 1: int}> $limits Limits incl. the setting `hardening.login_limits`.
		 */
		$filtered = apply_filters( 'course_captain_hardening_limits', $limits );
		foreach ( $limits as $key => $limit ) {
			$entry = is_array( $filtered ) ? ( $filtered[ $key ] ?? null ) : null;
			if ( is_array( $entry ) && isset( $entry[0], $entry[1] ) && (int) $entry[0] > 0 && (int) $entry[1] > 0 ) {
				$limits[ $key ] = array( (int) $entry[0], (int) $entry[1] );
			}
		}
		return $limits;
	}
}
