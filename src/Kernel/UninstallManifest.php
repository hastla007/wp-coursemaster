<?php
/**
 * Uninstall manifest of the Free core.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

use CourseCaptain\Policy\Roles;
use CourseCaptain\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Exact list of everything Course Captain Free may store (R0-16 §2.5).
 *
 * Uninstall deletes only names from this manifest, never a wildcard over the
 * plugin prefix, so that data of other plugins with a similar prefix, including
 * the paid add-on, survives (INV-C-026, R0-39 FG-10). A CI scan (REC-020)
 * fails when code uses an own key that is missing here.
 *
 * Roles come from `Policy\Roles` (R1-03); tables come from `Storage\Schema` (R1-02).
 */
final class UninstallManifest {

	/** Option holding the "General" settings tab (R0-27 §2.1). */
	public const GENERAL_SETTINGS_OPTION = 'course_captain_settings_general';

	/** Key inside the general tab values that enables data deletion (E-89). */
	public const DELETE_DATA_KEY = 'delete_data_on_uninstall';

	/**
	 * Exact option names (R0-27 §2.1, §2.7; R0-37 §4.3; R0-39 FG-11).
	 *
	 * @var list<string>
	 */
	public const OPTIONS = array(
		// Settings, one option per tab (R0-27 §2.1).
		'course_captain_settings_version',
		'course_captain_settings_general',
		'course_captain_settings_display',
		'course_captain_settings_learning',
		'course_captain_settings_enrollment',
		'course_captain_settings_advanced',
		'course_captain_settings_exams',
		'course_captain_settings_roles',
		'course_captain_settings_certificates',
		'course_captain_settings_emails',
		'course_captain_settings_privacy',
		'course_captain_settings_hardening',
		'course_captain_role_grants',
		// System state (R0-27 §2.7, R0-37 §4.3).
		'course_captain_db_version',
		'course_captain_schema_fingerprint',
		'course_captain_schema_compat_min',
		'course_captain_backfill_open',
		'course_captain_lock_migration',
		'course_captain_fk_issues',
		'course_captain_migration_bootstrap_lock',
		'course_captain_migration_failed',
		'course_captain_cron_state',
		'course_captain_lock_import',
		'course_captain_lock_retention',
		'course_captain_storage_secret',
		'course_captain_storage_layout',
		'course_captain_import_drop_state',
		'course_captain_cache_generation',
		'course_captain_retention_state',
		'course_captain_rewrite_version',
		'course_captain_installation_uuid',
		'course_captain_site_identity',
		'course_captain_setup_state',
		'course_captain_slug_history',
		'course_captain_integrations',
		'course_captain_extensions_seen',
	);

	/**
	 * Option families with a dynamic suffix (R0-27 §2.1). Uninstall expands
	 * them to exact names via {@see self::DYNAMIC_OPTION_SUFFIXES}.
	 *
	 * @var list<string>
	 */
	public const OPTION_PREFIXES = array(
		'course_captain_settings_undo_',
		'course_captain_mail_template_',
	);

	/**
	 * Known suffixes per dynamic option family.
	 *
	 * @var array<string, list<string>>
	 */
	public const DYNAMIC_OPTION_SUFFIXES = array(
		'course_captain_settings_undo_' => array(
			'general',
			'display',
			'learning',
			'enrollment',
			'advanced',
			'exams',
			'roles',
			'certificates',
			'emails',
			'privacy',
			'hardening',
		),
		// Mail types R1 (R0-27 §3.2).
		'course_captain_mail_template_' => array(
			'access_granted',
			'seat_invitation',
			'seat_claimed',
			'course_completed',
			'certificate_issued',
			'certificate_corrected',
			'certificate_revoked',
			'attempt_voided',
			'attempt_annulled',
			'access_transferred',
			'grading_completed',
			'access_expired',
			'test_mail',
			'assignment_submitted',
			'assignment_returned',
			'assignment_graded',
		),
	);

	/**
	 * Exact transient names. None in R1-01.
	 *
	 * @var list<string>
	 */
	public const TRANSIENTS = array();

	/**
	 * Network-wide user meta keys. None in R1-01 (user values use user options, MS-R-05).
	 *
	 * @var list<string>
	 */
	public const USER_META = array( 'ccap_notice_state' );

	/**
	 * Post meta keys (R1-04).
	 *
	 * @var list<string>
	 */
	public const POST_META = array(
		'_ccap_activity_settings',
		'_ccap_activity_type',
		'_ccap_activity_uid',
		'_ccap_cache_gen',
		'_ccap_completion_rule',
		'_ccap_copied_from_uid',
		'_ccap_current_revision_id',
		'_ccap_language',
		'_ccap_protected_course',
		'_ccap_protected_origin',
	);

	/**
	 * Own roles (R1-03).
	 *
	 * @var list<string>
	 */
	public const ROLES = Roles::ALL;

	/**
	 * Exact transient names.
	 *
	 * @return list<string>
	 */
	public static function transients(): array {
		return self::TRANSIENTS;
	}

	/**
	 * Network-wide user meta keys.
	 *
	 * @return list<string>
	 */
	public static function user_meta(): array {
		return self::USER_META;
	}

	/**
	 * Post meta keys.
	 *
	 * @return list<string>
	 */
	public static function post_meta(): array {
		return self::POST_META;
	}

	/**
	 * Own tables without the site prefix, children first.
	 *
	 * @return list<string>
	 */
	public static function tables(): array {
		return Schema::drop_order();
	}

	/**
	 * Own roles.
	 *
	 * @return list<string>
	 */
	public static function roles(): array {
		return self::ROLES;
	}

	/**
	 * Every exact option name including the expanded dynamic families.
	 *
	 * @return list<string>
	 */
	public static function all_option_names(): array {
		$names = self::OPTIONS;
		foreach ( self::DYNAMIC_OPTION_SUFFIXES as $prefix => $suffixes ) {
			foreach ( $suffixes as $suffix ) {
				$names[] = $prefix . $suffix;
			}
		}
		return array_values( array_unique( $names ) );
	}

	/**
	 * Whether a key is covered by the manifest (exact name or registered prefix).
	 *
	 * @param string $key Option, transient, meta or cron key.
	 * @return bool
	 */
	public static function covers( string $key ): bool {
		$exact = array_merge(
			self::all_option_names(),
			self::transients(),
			self::user_meta(),
			self::post_meta(),
			CronHooks::ALL,
			Capabilities::PRIMITIVE
		);
		if ( in_array( $key, $exact, true ) ) {
			return true;
		}
		foreach ( self::OPTION_PREFIXES as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The manifest as array, as passed to the `course_captain_uninstall` action.
	 *
	 * @return array<string, list<string>>
	 */
	public static function to_array(): array {
		return array(
			'options'      => self::all_option_names(),
			'transients'   => self::transients(),
			'user_meta'    => self::user_meta(),
			'post_meta'    => self::post_meta(),
			'tables'       => self::tables(),
			'roles'        => self::roles(),
			'capabilities' => Capabilities::PRIMITIVE,
			'cron_hooks'   => CronHooks::ALL,
		);
	}
}
