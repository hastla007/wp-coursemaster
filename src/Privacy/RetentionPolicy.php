<?php
/**
 * Retention periods `privacy.retention` (SET-CC-02, R0-27 §1.4, R0-31 §2.1,
 * owner decision D1 of 24.11.2026).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Privacy;

use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;

defined( 'ABSPATH' ) || exit;

/**
 * The 21 sub-keys of R0-27 §1.4 plus `import_package_days` (D1: copies of
 * imports in work, default = the shorter of GM-131 and R0-10 §3.6 = 30
 * days). Defaults equal the former constants, so an installation that never
 * saved the tab deletes exactly as before. Every value is a changeable
 * suggestion that the operator has to have checked legally (E-44, E-51;
 * marked "Legal review needed" in the UI).
 *
 * The filter `course_captain_retention_days( int|string|null $value, string $key )`
 * wins over the setting; the value is in the unit of the sub-key (`*_days`
 * days, `*_years` years, `import_keep_packages` count). A filtered value
 * outside the range of the sub-key is ignored (the setting stays effective).
 */
final class RetentionPolicy {

	public const FILTER = 'course_captain_retention_days';

	/**
	 * Sub-keys: unit, default, min, max (R0-27 §1.4) and the steps of the
	 * retention run that read them (R0-31 §2.1). `fixed` = no choice.
	 *
	 * @var array<string, array{unit: string, default: int|string|null, min?: int, max?: int, nullable?: bool, steps: list<string>}>
	 */
	public const KEYS = array(
		'audit_log_years'                      => array(
			'unit'    => 'years',
			'default' => 4,
			'min'     => 1,
			'max'     => 10,
			'steps'   => array( 'RT-23_audit' ),
		),
		'grant_history_deleted_accounts_years' => array(
			'unit'    => 'years',
			'default' => 10,
			'min'     => 1,
			'max'     => 30,
			'steps'   => array( 'RT-13_grant_history_deleted' ),
		),
		'import_log_days'                      => array(
			'unit'    => 'days',
			'default' => 180,
			'min'     => 7,
			'max'     => 3650,
			'steps'   => array( 'RT-25_import_log' ),
		),
		'import_unchanged_days'                => array(
			'unit'    => 'days',
			'default' => 14,
			'min'     => 1,
			'max'     => 365,
			'steps'   => array( 'RT-24_import_unchanged' ),
		),
		'import_keep_packages'                 => array(
			'unit'    => 'count',
			'default' => 2,
			'min'     => 1,
			'max'     => 20,
			'steps'   => array( 'RT-26_package_copies' ),
		),
		'import_package_days'                  => array(
			'unit'    => 'days',
			'default' => 30,
			'min'     => 1,
			'max'     => 365,
			'steps'   => array( 'RT-26_package_work' ),
		),
		'inbox_days'                           => array(
			'unit'    => 'days',
			'default' => 90,
			'min'     => 7,
			'max'     => 3650,
			'steps'   => array( 'RT-27_inbox', 'RT-28_outbox_delivered' ),
		),
		'outbox_event_days'                    => array(
			'unit'    => 'days',
			'default' => 30,
			'min'     => 7,
			'max'     => 365,
			'steps'   => array( 'RT-29_outbox_open' ),
		),
		'notification_log_days'                => array(
			'unit'    => 'days',
			'default' => 90,
			'min'     => 7,
			'max'     => 3650,
			'steps'   => array( 'RT-30_notifications' ),
		),
		'error_log_days'                       => array(
			'unit'    => 'days',
			'default' => 30,
			'min'     => 1,
			'max'     => 365,
			'steps'   => array( 'RT-33_error_logs' ),
		),
		'debug_file_days'                      => array(
			'unit'    => 'days',
			'default' => 7,
			'min'     => 1,
			'max'     => 30,
			'steps'   => array( 'RT-34_debug_logs' ),
		),
		'exam_attempts_years'                  => array(
			'unit'    => 'years',
			'default' => 10,
			'min'     => 1,
			'max'     => 30,
			'steps'   => array( 'RT-06_exam_attempts' ),
		),
		'raw_answers_years_after_course_end'   => array(
			'unit'    => 'years',
			'default' => 2,
			'min'     => 0,
			'max'     => 30,
			'steps'   => array( 'RT-04_answers', 'RT-05_exam_answers', 'RT-45_assignments' ),
		),
		'certificates_years'                   => array(
			'unit'    => 'years',
			'default' => 10,
			'min'     => 1,
			'max'     => 50,
			'steps'   => array( 'RT-09_certificates' ),
		),
		'inactive_learning_data_years'         => array(
			'unit'     => 'years',
			'default'  => null,
			'min'      => 1,
			'max'      => 20,
			'nullable' => true,
			'steps'    => array( 'RT-02_inactive_learning' ),
		),
		'invite_email_days_after_expiry'       => array(
			'unit'    => 'days',
			'default' => 30,
			'min'     => 1,
			'max'     => 365,
			'steps'   => array( 'RT-15_invite_emails' ),
		),
		'held_mail_days'                       => array(
			'unit'    => 'days',
			'default' => 30,
			'min'     => 1,
			'max'     => 90,
			'steps'   => array( 'RT-31_held_mails' ),
		),
		'learning_progress'                    => array(
			'unit'    => 'fixed',
			'default' => 'until_account_deletion',
			'steps'   => array(),
		),
		'ended_grants_years'                   => array(
			'unit'    => 'years',
			'default' => 10,
			'min'     => 1,
			'max'     => 30,
			'steps'   => array( 'RT-12_ended_grants' ),
		),
		'removed_seats_days'                   => array(
			'unit'    => 'days',
			'default' => 365,
			'min'     => 30,
			'max'     => 3650,
			'steps'   => array( 'RT-17_removed_seats' ),
		),
		'closed_groups_years'                  => array(
			'unit'    => 'years',
			'default' => 3,
			'min'     => 1,
			'max'     => 30,
			'steps'   => array( 'RT-18_closed_groups' ),
		),
		'lifted_locks_days'                    => array(
			'unit'    => 'days',
			'default' => 30,
			'min'     => 1,
			'max'     => 365,
			'steps'   => array( 'RT-22_lifted_locks' ),
		),
	);

	/**
	 * Register sub-field definitions of `privacy.retention`.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields(): array {
		$out = array();
		foreach ( self::KEYS as $key => $def ) {
			if ( 'fixed' === $def['unit'] ) {
				$out[ $key ] = array(
					'type'   => 'enum',
					'values' => array( (string) $def['default'] ),
				);
				continue;
			}
			$out[ $key ] = array(
				'type' => 'int',
				'min'  => (int) ( $def['min'] ?? 0 ),
				'max'  => (int) ( $def['max'] ?? 0 ),
			) + ( empty( $def['nullable'] ) ? array() : array( 'nullable' => true ) );
		}
		return $out;
	}

	/**
	 * Defaults of `privacy.retention` (= former constants).
	 *
	 * @return array<string, int|string|null>
	 */
	public static function defaults(): array {
		return array_map( static fn( array $def ) => $def['default'], self::KEYS );
	}

	/**
	 * Effective values: stored setting (or the given candidate), then the filter.
	 *
	 * @param array<string, mixed>|null $values Candidate values (preview before saving); null = stored setting.
	 * @return array<string, int|string|null>
	 */
	public static function effective( ?array $values = null ): array {
		if ( null === $values ) {
			try {
				$values = (array) Settings::get( 'privacy.retention' );
			} catch ( \Throwable $e ) {
				unset( $e );
				$values = self::defaults();
			}
		}
		$fields = self::fields();
		$out    = array();
		foreach ( self::KEYS as $key => $def ) {
			$value = array_key_exists( $key, $values ) && SettingsSchema::validate( $fields[ $key ], $values[ $key ] )['ok'] ? $values[ $key ] : $def['default'];
			/**
			 * Retention period of one `privacy.retention` sub-key (SET-CC-02; the filter wins over the setting).
			 *
			 * @since 1.0.0
			 * @param int|string|null $value Value from the setting, in the unit of the key (days, years or count; null = off).
			 * @param string          $key   Sub-key, e.g. `audit_log_years`.
			 */
			$filtered = apply_filters( 'course_captain_retention_days', $value, $key );
			if ( $filtered !== $value ) {
				$check = SettingsSchema::validate( $fields[ $key ], is_float( $filtered ) ? (int) $filtered : $filtered );
				$value = $check['ok'] ? $check['value'] : $value;
			}
			$out[ $key ] = $value;
		}
		return $out;
	}

	/**
	 * Effective value of one sub-key.
	 *
	 * @param string $key Sub-key.
	 * @return int|string|null
	 */
	public static function value( string $key ) {
		return self::effective()[ $key ] ?? ( self::KEYS[ $key ]['default'] ?? null );
	}

	/**
	 * Effective value of a day/count sub-key as int (default when unknown).
	 *
	 * @param string $key Sub-key.
	 * @return int
	 */
	public static function int( string $key ): int {
		return (int) self::value( $key );
	}

	/**
	 * Sub-keys whose value the filter replaces (UI notice).
	 *
	 * @return array<string, int|string|null> Key => effective value.
	 */
	public static function filtered(): array {
		try {
			$stored = (array) Settings::get( 'privacy.retention' );
		} catch ( \Throwable $e ) {
			unset( $e );
			$stored = self::defaults();
		}
		$out = array();
		foreach ( self::effective( $stored ) as $key => $value ) {
			$saved = $stored[ $key ] ?? null;
			if ( $saved !== $value ) {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * UTC cutoff (`Y-m-d H:i:s`) of a value: everything older is due; null when off.
	 *
	 * @param string          $key   Sub-key.
	 * @param int|string|null $value Value in the unit of the key.
	 * @param int             $now   Unix time.
	 * @return string|null
	 */
	public static function cutoff( string $key, $value, int $now ): ?string {
		$unit = self::KEYS[ $key ]['unit'] ?? 'days';
		if ( null === $value || ! in_array( $unit, array( 'days', 'years' ), true ) ) {
			return null;
		}
		$time = 'years' === $unit ? (int) strtotime( '-' . (int) $value . ' years', $now ) : $now - (int) $value * DAY_IN_SECONDS;
		return gmdate( 'Y-m-d H:i:s', $time );
	}

	/**
	 * Sub-keys that get shorter (or switch on) from `$before` to `$after`
	 * (risk confirmation RC-23 with the preview, R0-27 §1.3 "R beim Verkleinern", PRIV-008).
	 *
	 * @param array<string, mixed> $before Before.
	 * @param array<string, mixed> $after  After.
	 * @return list<string>
	 */
	public static function shortened( array $before, array $after ): array {
		$out = array();
		foreach ( self::KEYS as $key => $def ) {
			if ( 'fixed' === $def['unit'] || ! array_key_exists( $key, $after ) ) {
				continue;
			}
			$old = $before[ $key ] ?? $def['default'];
			$new = $after[ $key ];
			if ( null !== $new && ( null === $old || (int) $new < (int) $old ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}

	/**
	 * Sub-keys that are extended beyond their default from `$before` to
	 * `$after` (E-245, R0-31 RR-3/PSD-015: only with a mandatory reason
	 * that is stored in the audit entry). A value counts when it is above
	 * the default and above the previous value; keys whose default is "off"
	 * (`null`) and the fixed key never count.
	 *
	 * @param array<string, mixed> $before Before.
	 * @param array<string, mixed> $after  After.
	 * @return list<string>
	 */
	public static function extended( array $before, array $after ): array {
		$out = array();
		foreach ( self::KEYS as $key => $def ) {
			if ( 'fixed' === $def['unit'] || null === $def['default'] || ! array_key_exists( $key, $after ) || ! is_int( $after[ $key ] ) ) {
				continue;
			}
			$old = $before[ $key ] ?? $def['default'];
			$new = $after[ $key ];
			if ( $new > (int) $def['default'] && ( ! is_int( $old ) || $new > $old ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}
}
