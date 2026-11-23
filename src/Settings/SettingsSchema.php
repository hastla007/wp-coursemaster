<?php
/**
 * Settings register (R0-27 §1.3) — the single source for validation,
 * defaults, rights, export and audit (SR-2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Field contract: `type`, `default`, `cap` (`settings` = #19,
 * `admin` = #19 and administrator only), `privacy`
 * (public/internal/personal/secret), `side_effects`, `export`, plus type
 * rules (`min`, `max`, `nullable`, `values`, `fields`). `export` is `true`,
 * `false` or `never_env` (environment-bound, never exported or imported, R0-31 EX-2). Free core fields
 * only; `pro.*` keys are registered by the add-on (SR-8). Roles, mail
 * templates and the retention sub-keys have their own stores (R1-34,
 * R1-21, R1-17); `emails.types` is a strict object (R1-21).
 */
final class SettingsSchema {

	/** 2: business rules and module switches (SET-CC-13/24/25/26); v1 values stay as they are. */
	public const VERSION = 2;

	/**
	 * Tabs with their autoload flag (R0-27 §2.1).
	 *
	 * @var array<string, bool>
	 */
	public const TABS = array(
		'general'      => true,
		'display'      => true,
		'learning'     => true,
		'enrollment'   => true,
		'advanced'     => true,
		'exams'        => false,
		'certificates' => false,
		'emails'       => false,
		'privacy'      => false,
		'hardening'    => true,
	);

	/**
	 * Field definitions per tab.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public static function fields(): array {
		$hardening = array(
			'type'    => 'enum',
			'values'  => array( 'auto', 'force', 'off' ),
			'default' => 'off',
			'cap'     => 'admin',
		);
		$schema    = array(
			'general'      => array(
				'learning_area_slug'              => array(
					'type'         => 'slug',
					'default'      => 'lernen',
					'side_effects' => array( 'rewrite_flush' ),
				),
				'course_slug'                     => array(
					'type'         => 'slug',
					'default'      => 'kurs',
					'side_effects' => array( 'rewrite_flush' ),
				),
				'catalog_page_id'                 => array(
					'type'    => 'page_id',
					'default' => 0,
				),
				'learner_home_page_id'            => array(
					'type'    => 'page_id',
					'default' => 0,
				),
				'learning_area_title'             => array(
					'type'    => 'string',
					'max'     => 60,
					'default' => '',
				),
				'use_profile_locale'              => array(
					'type'     => 'bool',
					'nullable' => true,
					'default'  => null,
				),
				'catalog_group_language_versions' => array(
					'type'    => 'bool',
					'default' => false,
				),
				'catalog_indexing'                => array(
					'type'    => 'enum',
					'values'  => array( 'public_catalog', 'noindex' ),
					'default' => 'public_catalog',
				),
				'delete_data_on_uninstall'        => array(
					'type'    => 'bool',
					'default' => false,
					'cap'     => 'admin',
					'export'  => false,
				),
			),
			'display'      => array(
				'container_width'  => array(
					'type'    => 'enum',
					'values'  => array( 'theme', 'wide', 'full' ),
					'default' => 'theme',
				),
				'breadcrumbs'      => array(
					'type'    => 'bool',
					'default' => true,
				),
				'catalog_sort'     => array(
					'type'    => 'enum',
					'values'  => array( 'manual', 'title', 'newest' ),
					'default' => 'manual',
				),
				'card_elements'    => array(
					'type'    => 'set',
					'values'  => array( 'cover', 'summary', 'lesson_count', 'progress' ),
					'default' => array( 'cover', 'summary', 'lesson_count', 'progress' ),
				),
				'require_captions' => array(
					'type'    => 'bool',
					'default' => false,
				),
			),
			'learning'     => array(
				'course_completion_default' => array(
					'type'    => 'enum',
					'values'  => array( 'required_modules', 'required_modules_and_exam' ),
					'default' => 'required_modules',
				),
				'mastery_algorithm'         => array(
					'type'    => 'enum',
					'values'  => array( 'smarttrainer.v1' ),
					'default' => 'smarttrainer.v1',
				),
				'favorites_enabled'         => array(
					'type'    => 'bool',
					'default' => true,
				),
				'mistake_training_enabled'  => array(
					'type'    => 'bool',
					'default' => true,
				),
				// Module switches (SET-CC-24): off hides UI, REST and jobs; no data is deleted.
				'flashcards_enabled'        => array(
					'type'    => 'bool',
					'default' => true,
				),
				'assignments_enabled'       => array(
					'type'    => 'bool',
					'default' => true,
				),
				// Smart trainer review intervals in days (SET-CC-13; former SmartTrainer::REVIEW_INTERVAL_DAYS).
				'review_intervals'          => array(
					'type'    => 'object',
					'fields'  => array(
						'first'  => array(
							'type' => 'int',
							'min'  => 1,
							'max'  => 365,
						),
						'second' => array(
							'type' => 'int',
							'min'  => 1,
							'max'  => 730,
						),
						'third'  => array(
							'type' => 'int',
							'min'  => 1,
							'max'  => 1825,
						),
					),
					'default' => array(
						'first'  => 7,
						'second' => 21,
						'third'  => 60,
					),
				),
				// Register `assignments.*` (R0-27 SET-134, E-289): a task may only be stricter.
				'assignment_file_types'     => array(
					'type'    => 'set',
					'values'  => \CourseCaptain\Activity\Providers\AssignmentProvider::FILE_TYPES,
					'default' => \CourseCaptain\Activity\Providers\AssignmentProvider::FILE_TYPES,
				),
				'assignment_max_file_mb'    => array(
					'type'    => 'int',
					'min'     => 1,
					'max'     => 100,
					'default' => 20,
				),
				'assignment_max_files'      => array(
					'type'    => 'int',
					'min'     => 1,
					'max'     => 20,
					'default' => 5,
				),
				'feedback'                  => array(
					'type'    => 'object',
					'fields'  => array(
						'visibility'           => array(
							'type'   => 'enum',
							'values' => array( 'always', 'after_submit', 'never' ),
						),
						'show_correct_answers' => array( 'type' => 'bool' ),
					),
					'default' => array(
						'visibility'           => 'always',
						'show_correct_answers' => true,
					),
				),
			),
			'exams'        => array(
				'max_attempts'              => array(
					'type'     => 'int',
					'min'      => 1,
					'max'      => 100,
					'nullable' => true,
					'default'  => null,
				),
				'cooldown_seconds'          => array(
					'type'    => 'int',
					'min'     => 0,
					'max'     => 2592000,
					'default' => 0,
				),
				'count_cancelled'           => array(
					'type'    => 'bool',
					'default' => true,
				),
				'time_limit_minutes'        => array(
					'type'     => 'int',
					'min'      => 1,
					'max'      => 1440,
					'nullable' => true,
					'default'  => null,
				),
				'timer_mode'                => array(
					'type'    => 'enum',
					'values'  => array( 'strict', 'practice' ),
					'default' => 'strict',
				),
				'late_tolerance_seconds'    => array(
					'type'    => 'int',
					'min'     => 0,
					'max'     => 600,
					'default' => 120,
				),
				'passing_ratio_proposal'    => array(
					'type'    => 'object',
					'fields'  => array(
						'num' => array(
							'type' => 'int',
							'min'  => 1,
							'max'  => 1000,
						),
						'den' => array(
							'type' => 'int',
							'min'  => 1,
							'max'  => 1000,
						),
					),
					'default' => array(
						'num' => 7,
						'den' => 10,
					),
				),
				'shuffle_questions'         => array(
					'type'    => 'enum',
					'values'  => array( 'none', 'within_section', 'all' ),
					'default' => 'within_section',
				),
				'shuffle_answers'           => array(
					'type'    => 'bool',
					'default' => true,
				),
				'feedback'                  => array(
					'type'    => 'object',
					'fields'  => array(
						'visibility'                => array(
							'type'   => 'enum',
							'values' => array( 'after_submit', 'never' ),
						),
						'show_score'                => array( 'type' => 'bool' ),
						'show_correct_answers'      => array( 'type' => 'bool' ),
						'show_correct_answers_when' => array(
							'type'   => 'enum',
							'values' => array( 'no_attempts_left_or_passed', 'after_submit' ),
						),
					),
					'default' => array(
						'visibility'                => 'after_submit',
						'show_score'                => true,
						'show_correct_answers'      => true,
						'show_correct_answers_when' => 'no_attempts_left_or_passed',
					),
				),
				'auto_submit_inactive_days' => array(
					'type'     => 'int',
					'min'      => 1,
					'max'      => 365,
					'nullable' => true,
					'default'  => null,
				),
				// Exam starts per learner and hour (SET-CC-13; former ExamSessions::START_LIMIT_HOUR).
				'starts_per_hour'           => array(
					'type'    => 'int',
					'min'     => 1,
					'max'     => 1000,
					'default' => 40,
				),
			),
			'enrollment'   => array(
				'self_enrollment'             => array(
					'type'    => 'bool',
					'default' => false,
				),
				'default_access_days'         => array(
					'type'     => 'int',
					'min'      => 1,
					'max'      => 3650,
					'nullable' => true,
					'default'  => null,
				),
				// Global drip plan (SET-CC-25): module n unlocks n × days after the access start; empty = at once.
				'default_drip_days'           => array(
					'type'     => 'int',
					'min'      => 1,
					'max'      => 365,
					'nullable' => true,
					'default'  => null,
				),
				'groups_enabled'              => array(
					'type'    => 'bool',
					'default' => false,
				),
				'invite_valid_days'           => array(
					'type'    => 'int',
					'min'     => 1,
					'max'     => 365,
					'default' => 30,
				),
				'claim_policy'                => array(
					'type'    => 'enum',
					'values'  => array( 'transferable', 'invited_address_only' ),
					'default' => 'transferable',
				),
				'pending_grant_timeout_hours' => array(
					'type'    => 'int',
					'min'     => 1,
					'max'     => 168,
					'default' => 24,
				),
				'source_disabled_policy'      => array(
					'type'    => 'enum',
					'values'  => array( 'keep', 'keep_and_flag' ),
					'default' => 'keep',
				),
			),
			'certificates' => array(
				// Module switch (SET-CC-24): issued certificates stay verifiable.
				'enabled'            => array(
					'type'    => 'bool',
					'default' => true,
				),
				'auto_issue'         => array(
					'type'    => 'bool',
					'default' => false,
				),
				'holder_name_policy' => array(
					'type'    => 'enum',
					'values'  => array( 'never', 'learner_opt_in' ),
					'default' => 'learner_opt_in',
				),
				'base_template'      => array(
					'type'    => 'object',
					'fields'  => array(
						'issuer_name'     => array(
							'type' => 'string',
							'max'  => 120,
						),
						'footer_text'     => array(
							'type' => 'text',
							'max'  => 500,
						),
						'logo_id'         => array(
							'type' => 'int',
							'min'  => 0,
						),
						'signature_label' => array(
							'type' => 'string',
							'max'  => 120,
						),
					),
					'default' => array(
						'issuer_name'     => '',
						'footer_text'     => '',
						'logo_id'         => 0,
						'signature_label' => '',
					),
				),
			),
			'emails'       => array(
				'from_name'    => array(
					'type'    => 'string',
					'max'     => 100,
					'default' => '',
					'privacy' => 'personal',
				),
				'from_address' => array(
					'type'    => 'email',
					'default' => '',
					'privacy' => 'personal',
				),
				'footer_text'  => array(
					'type'    => 'text',
					'max'     => 1000,
					'default' => '',
					'privacy' => 'personal',
				),
				'send_format'  => array(
					'type'    => 'enum',
					'values'  => array( 'multipart', 'text_only' ),
					'default' => 'multipart',
				),
				// Informational types only; required types cannot be switched off (R0-27 §1.3, SET-102).
				'types'        => \CourseCaptain\Notification\NotificationTypes::settings_field(),
			),
			'privacy'      => array(
				'auto_export_on_confirm'  => array(
					'type'    => 'bool',
					'default' => true,
				),
				'auto_erase_on_confirm'   => array(
					'type'    => 'bool',
					'default' => false,
				),
				'backup_max_age_days'     => array(
					'type'    => 'int',
					'min'     => 1,
					'max'     => 3650,
					'default' => 90,
				),
				'external_media_mode'     => array(
					'type'    => 'enum',
					'values'  => array( 'always_load', 'click_to_load', 'link_only' ),
					'default' => 'always_load',
				),
				'consent_category'        => array(
					'type'    => 'enum',
					'values'  => array( 'marketing', 'statistics', 'preferences', 'functional' ),
					'default' => 'marketing',
				),
				'external_media_remember' => array(
					'type'    => 'bool',
					'default' => true,
				),
				'trusted_proxies'         => array(
					'type'    => 'ip_list',
					'max'     => 50,
					'default' => array(),
					'cap'     => 'admin',
					'export'  => 'never_env',
				),
				// Hosts that do not count as foreign media hosts (SET-CC-13; filter `course_captain_trusted_media_hosts` wins).
				'trusted_media_hosts'     => array(
					'type'    => 'host_list',
					'max'     => 50,
					'default' => array(),
				),
				'show_avatars'            => array(
					'type'    => 'bool',
					'default' => false,
				),
				'csp_mode'                => array(
					'type'    => 'enum',
					'values'  => array( 'enforce', 'report_only' ),
					'default' => 'enforce',
				),
			),
			'hardening'    => array(
				'login_limit'        => $hardening,
				'reset_limit'        => $hardening,
				'registration_limit' => $hardening,
				'known_device'       => array(
					'type'    => 'bool',
					'default' => true,
					'cap'     => 'admin',
				),
				// Lifetime of the device cookie (SET-CC-13; former Hardening::DEVICE_DAYS).
				'known_device_days'  => array(
					'type'    => 'int',
					'min'     => 1,
					'max'     => 365,
					'default' => 180,
					'cap'     => 'admin',
				),
				// Failed logins per 15 minutes (SET-CC-13, R1-45): `ip`, `account_ip`, `account`; windows and
				// the reset/registration limits stay technical constants; the filter `course_captain_hardening_limits` wins.
				'login_limits'       => array(
					'type'    => 'object',
					'fields'  => array(
						'ip'         => array(
							'type' => 'int',
							'min'  => 1,
							'max'  => 1000,
						),
						'account_ip' => array(
							'type' => 'int',
							'min'  => 1,
							'max'  => 1000,
						),
						'account'    => array(
							'type' => 'int',
							'min'  => 1,
							'max'  => 1000,
						),
					),
					'default' => array(
						'ip'         => 10,
						'account_ip' => 5,
						'account'    => 30,
					),
					'cap'     => 'admin',
				),
				'xmlrpc'             => $hardening,
				'site_headers'       => $hardening,
			),
			'advanced'     => array(
				'import_execution'           => array(
					'type'    => 'enum',
					'values'  => array( 'background', 'browser' ),
					'default' => 'background',
				),
				'file_delivery'              => array(
					'type'    => 'enum',
					'values'  => array( 'php', 'x_sendfile', 'x_accel_redirect' ),
					'default' => 'php',
					'export'  => 'never_env',
				),
				'media_delete_grace_days'    => array(
					'type'    => 'int',
					'min'     => 1,
					'max'     => 90,
					'default' => 7,
				),
				'cron_stale_warning_minutes' => array(
					'type'    => 'int',
					'min'     => 60,
					'max'     => 1440,
					'default' => 180,
				),
				// Reports (SET-CC-26): default CSV columns and pseudonymised exports.
				'report_csv_columns'         => array(
					'type'    => 'set',
					'values'  => \CourseCaptain\Progress\CourseReport::CSV_COLUMNS,
					'default' => \CourseCaptain\Progress\CourseReport::CSV_COLUMNS,
				),
				'report_pseudonymize'        => array(
					'type'    => 'bool',
					'default' => false,
				),
			),
		);
		foreach ( $schema as $tab => $fields ) {
			foreach ( $fields as $key => $field ) {
				$schema[ $tab ][ $key ] = $field + array(
					'cap'          => 'settings',
					'privacy'      => 'internal',
					'side_effects' => array(),
					'export'       => true,
				);
			}
		}
		return $schema;
	}

	/**
	 * Override metadata (R0-28 §1.4, T28-5): register keys with a scope
	 * beyond `global`. `scopes` = levels that may override (course,
	 * activity, exam_rule, group, media); `class` = resolution class
	 * (L live, K course revision, R exam rule/attempt start, G grant or
	 * invitation, E event, A issuance); `lockable` = a site lock may forbid
	 * overrides; `unit` = `field` or `leaf` (sub-keys of an object override
	 * one by one); `monotonic` = `shorter` (only smaller than global) or
	 * `stricter` (only stricter, ordered by the enum values).
	 *
	 * @return array<string, array{scopes: list<string>, class: string, lockable: bool, unit: string, monotonic?: string}>
	 */
	public static function overridable(): array {
		$course = static fn( string $resolve_class, string $unit = 'field' ): array => array(
			'scopes'   => array( 'course' ),
			'class'    => $resolve_class,
			'lockable' => true,
			'unit'     => $unit,
		);
		$exam   = static fn( bool $lockable = true, string $unit = 'field' ): array => array(
			'scopes'   => array( 'exam_rule' ),
			'class'    => 'R',
			'lockable' => $lockable,
			'unit'     => $unit,
		);
		return array(
			'general.catalog_indexing'           => $course( 'L' ),
			'display.container_width'            => $course( 'L' ),
			'display.breadcrumbs'                => $course( 'L' ),
			'learning.course_completion_default' => $course( 'K' ),
			'learning.mastery_algorithm'         => $course( 'K' ),
			'learning.favorites_enabled'         => $course( 'L' ),
			'learning.mistake_training_enabled'  => $course( 'L' ),
			'learning.feedback'                  => array(
				'scopes'   => array( 'course', 'activity' ),
				'class'    => 'L',
				'lockable' => true,
				'unit'     => 'leaf',
			),
			'exams.max_attempts'                 => $exam(),
			'exams.cooldown_seconds'             => $exam(),
			'exams.count_cancelled'              => $exam(),
			'exams.time_limit_minutes'           => $exam(),
			'exams.timer_mode'                   => $exam(),
			'exams.late_tolerance_seconds'       => $exam(),
			'exams.shuffle_questions'            => $exam( false ),
			'exams.shuffle_answers'              => $exam(),
			'exams.feedback'                     => $exam( true, 'leaf' ),
			'exams.auto_submit_inactive_days'    => $exam(),
			'enrollment.self_enrollment'         => $course( 'L' ),
			'enrollment.default_access_days'     => $course( 'G' ),
			'enrollment.groups_enabled'          => $course( 'L' ),
			'certificates.auto_issue'            => $course( 'E' ),
			'certificates.base_template'         => $course( 'A', 'leaf' ),
			'enrollment.claim_policy'            => array(
				'scopes'   => array( 'group' ),
				'class'    => 'G',
				'lockable' => true,
				'unit'     => 'field',
			),
			'enrollment.invite_valid_days'       => array(
				'scopes'    => array( 'group' ),
				'class'     => 'G',
				'lockable'  => false,
				'unit'      => 'field',
				'monotonic' => 'shorter',
			),
			'privacy.external_media_mode'        => array(
				'scopes'    => array( 'media' ),
				'class'     => 'L',
				'lockable'  => false,
				'unit'      => 'field',
				'monotonic' => 'stricter',
			),
		);
	}

	/**
	 * Defaults of a tab.
	 *
	 * @param string $tab Tab.
	 * @return array<string, mixed>
	 */
	public static function defaults( string $tab ): array {
		return array_map( static fn( array $f ) => $f['default'], self::fields()[ $tab ] ?? array() );
	}

	/**
	 * Validate and normalize a value against a field definition.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param mixed                $value Raw value.
	 * @return array{ok: bool, value: mixed}
	 */
	public static function validate( array $field, $value ): array {
		$bad = array(
			'ok'    => false,
			'value' => null,
		);
		if ( null === $value || '' === $value ) {
			if ( ! empty( $field['nullable'] ) ) {
				return array(
					'ok'    => true,
					'value' => null,
				);
			}
			if ( in_array( $field['type'], array( 'string', 'text', 'email' ), true ) ) {
				return array(
					'ok'    => true,
					'value' => '',
				);
			}
		}
		switch ( $field['type'] ) {
			case 'bool':
				if ( is_bool( $value ) || in_array( $value, array( 0, 1, '0', '1' ), true ) ) {
					return array(
						'ok'    => true,
						'value' => (bool) $value,
					);
				}
				return $bad;
			case 'int':
			case 'page_id':
				if ( ! is_int( $value ) && ! ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', $value ) ) ) {
					return $bad;
				}
				$int = (int) $value;
				$min = (int) ( $field['min'] ?? ( 'page_id' === $field['type'] ? 0 : PHP_INT_MIN ) );
				$max = (int) ( $field['max'] ?? PHP_INT_MAX );
				return $int < $min || $int > $max ? $bad : array(
					'ok'    => true,
					'value' => $int,
				);
			case 'enum':
				return is_string( $value ) && in_array( $value, (array) $field['values'], true ) ? array(
					'ok'    => true,
					'value' => $value,
				) : $bad;
			case 'set':
				if ( ! is_array( $value ) || array() !== array_diff( $value, (array) $field['values'] ) ) {
					return $bad;
				}
				return array(
					'ok'    => true,
					'value' => array_values( array_intersect( (array) $field['values'], $value ) ),
				);
			case 'slug':
				return is_string( $value ) && 1 === preg_match( '/^[a-z0-9][a-z0-9-]{0,48}[a-z0-9]$/', $value ) ? array(
					'ok'    => true,
					'value' => $value,
				) : $bad;
			case 'string':
			case 'text':
				if ( ! is_string( $value ) ) {
					return $bad;
				}
				$clean = 'text' === $field['type'] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
				return mb_strlen( $clean ) > (int) ( $field['max'] ?? 255 ) ? $bad : array(
					'ok'    => true,
					'value' => $clean,
				);
			case 'email':
				return is_string( $value ) && false !== is_email( $value ) ? array(
					'ok'    => true,
					'value' => sanitize_email( $value ),
				) : $bad;
			case 'ip_list':
				if ( ! is_array( $value ) || count( $value ) > (int) ( $field['max'] ?? 50 ) ) {
					return $bad;
				}
				foreach ( $value as $ip ) {
					if ( ! is_string( $ip ) || ! self::is_ip_or_cidr( $ip ) ) {
						return $bad;
					}
				}
				return array(
					'ok'    => true,
					'value' => array_values( $value ),
				);
			case 'host_list':
				if ( ! is_array( $value ) || count( $value ) > (int) ( $field['max'] ?? 50 ) ) {
					return $bad;
				}
				$hosts = array();
				foreach ( $value as $host ) {
					if ( ! is_string( $host ) || ! self::is_host( strtolower( trim( $host ) ) ) ) {
						return $bad;
					}
					$hosts[] = strtolower( trim( $host ) );
				}
				return array(
					'ok'    => true,
					'value' => array_values( array_unique( $hosts ) ),
				);
			case 'object':
				if ( ! is_array( $value ) ) {
					return $bad;
				}
				// Strict objects reject unknown keys instead of ignoring them.
				if ( ! empty( $field['strict'] ) && array() !== array_diff( array_keys( $value ), array_keys( (array) $field['fields'] ) ) ) {
					return $bad;
				}
				$out = array();
				foreach ( (array) $field['fields'] as $sub => $def ) {
					if ( ! array_key_exists( $sub, $value ) ) {
						$out[ $sub ] = $field['default'][ $sub ] ?? null;
						continue;
					}
					$check = self::validate( $def, $value[ $sub ] );
					if ( ! $check['ok'] ) {
						return $bad;
					}
					$out[ $sub ] = $check['value'];
				}
				return array(
					'ok'    => true,
					'value' => $out,
				);
		}
		return $bad;
	}

	/**
	 * Host name (lower case, at least one dot, no scheme, port or path).
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	public static function is_host( string $value ): bool {
		return strlen( $value ) <= 253 && 1 === preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/', $value );
	}

	/**
	 * IPv4/IPv6 address or CIDR.
	 *
	 * @param string $value Value.
	 * @return bool
	 */
	private static function is_ip_or_cidr( string $value ): bool {
		$parts = explode( '/', $value, 2 );
		if ( false === filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
			return false;
		}
		if ( ! isset( $parts[1] ) ) {
			return true;
		}
		$max = str_contains( $parts[0], ':' ) ? 128 : 32;
		// `0.0.0.0/0` and `::/0` would trust every address (F-82, SET-115).
		return 1 === preg_match( '/^\d{1,3}$/', $parts[1] ) && (int) $parts[1] <= $max && (int) $parts[1] > 0;
	}
}
