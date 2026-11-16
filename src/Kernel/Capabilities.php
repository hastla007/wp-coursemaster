<?php
/**
 * Primitive capabilities of Course Captain and their activation defaults.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

defined( 'ABSPATH' ) || exit;

/**
 * Capability scaffold (R1-01).
 *
 * Only the WordPress `administrator` role receives Course Captain
 * capabilities on activation (decision E-83). The own roles, meta
 * capabilities and the central policy service follow in R1-03.
 */
final class Capabilities {

	/**
	 * The 24 primitive capabilities (R0-07 §3.1, prefix after E-207).
	 *
	 * @var list<string>
	 */
	public const PRIMITIVE = array(
		'ccap_edit_courses',
		'ccap_edit_others_courses',
		'ccap_publish_courses',
		'ccap_delete_courses',
		'ccap_manage_question_bank',
		'ccap_import_content',
		'ccap_export_content',
		'ccap_release_rights',
		'ccap_manage_grants',
		'ccap_manage_access_rules',
		'ccap_manage_groups',
		'ccap_view_reports',
		'ccap_view_learner_details',
		'ccap_view_attempt_details',
		'ccap_grade_attempts',
		'ccap_manage_learner_progress',
		'ccap_manage_certificates',
		'ccap_lock_learners',
		'ccap_manage_settings',
		'ccap_manage_integrations',
		'ccap_view_audit_log',
		'ccap_view_system_status',
		'ccap_run_maintenance',
		'ccap_assign_staff',
	);

	/**
	 * Roles that receive all primitive capabilities on activation (E-83).
	 *
	 * @var list<string>
	 */
	public const ACTIVATION_ROLES = array( 'administrator' );

	/**
	 * Option that records which capabilities Course Captain granted to which role.
	 */
	public const GRANTS_OPTION = 'course_captain_role_grants';

	/**
	 * Grant the primitive capabilities to the activation roles of the current site.
	 *
	 * Foreign roles are never touched. A missing `administrator` role is skipped.
	 *
	 * @return void
	 */
	public static function grant_activation_defaults(): void {
		$grants = self::recorded_grants();

		foreach ( self::ACTIVATION_ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}
			foreach ( self::PRIMITIVE as $cap ) {
				$role->add_cap( $cap );
			}
			$known                = $grants[ $role_name ] ?? array();
			$grants[ $role_name ] = array_values( array_unique( array_merge( $known, self::PRIMITIVE ) ) );
		}

		update_option( self::GRANTS_OPTION, $grants, false );
	}

	/**
	 * Remove every Course Captain capability from every role of the current site.
	 *
	 * Used only by the confirmed data deletion on uninstall (R0-07 P-10).
	 *
	 * @return void
	 */
	public static function remove_all(): void {
		$roles = wp_roles();
		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = $roles->get_role( (string) $role_name );
			if ( null === $role ) {
				continue;
			}
			foreach ( self::PRIMITIVE as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	/**
	 * Capabilities recorded as granted by Course Captain, per role.
	 *
	 * @return array<string, list<string>>
	 */
	public static function recorded_grants(): array {
		$raw = get_option( self::GRANTS_OPTION, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$grants = array();
		foreach ( $raw as $role_name => $caps ) {
			if ( ! is_string( $role_name ) || ! is_array( $caps ) ) {
				continue;
			}
			$grants[ $role_name ] = array_values( array_filter( $caps, 'is_string' ) );
		}
		return $grants;
	}
}
