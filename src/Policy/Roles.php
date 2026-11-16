<?php
/**
 * Own WordPress roles of Course Captain (R0-07 §2, §3.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * The four `ccap_` roles with their default capabilities.
 *
 * Rules (P-10, E-83): activation creates the roles and only adds
 * capabilities to them and to `administrator` (Kernel\Capabilities);
 * foreign roles such as `editor` or `shop_manager` stay untouched.
 * Deactivation removes nothing; uninstall with confirmed deletion removes
 * the roles (users keep their other roles).
 */
final class Roles {

	public const COURSE_MANAGER = 'ccap_course_manager';
	public const AUTHOR         = 'ccap_author';
	public const TRAINER        = 'ccap_trainer';
	public const SUPPORT        = 'ccap_support';

	/**
	 * All own role slugs.
	 *
	 * @var list<string>
	 */
	public const ALL = array( self::COURSE_MANAGER, self::AUTHOR, self::TRAINER, self::SUPPORT );

	/**
	 * Default capabilities per role (R0-07 §3.1 table, §2).
	 *
	 * @return array<string, list<string>>
	 */
	public static function capabilities(): array {
		return array(
			// Caps 1–18, 22, 24.
			self::COURSE_MANAGER => array(
				'read',
				'upload_files',
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
				'ccap_view_system_status',
				'ccap_assign_staff',
			),
			self::AUTHOR         => array( 'read', 'upload_files', 'ccap_edit_courses' ),
			self::TRAINER        => array( 'read', 'ccap_view_reports', 'ccap_view_learner_details', 'ccap_grade_attempts' ),
			self::SUPPORT        => array( 'read', 'ccap_view_learner_details', 'ccap_lock_learners', 'ccap_view_system_status', 'ccap_view_audit_log' ),
		);
	}

	/**
	 * English role names, stored untranslated like the core role names
	 * (WordPress translates stored role names only at display time).
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			self::COURSE_MANAGER => 'Course Manager',
			self::AUTHOR         => 'Course Author',
			self::TRAINER        => 'Course Trainer',
			self::SUPPORT        => 'Course Support',
		);
	}

	/**
	 * Create missing roles and add missing default capabilities. Never
	 * removes capabilities an operator added (idempotent re-activation).
	 *
	 * @return void
	 */
	public static function install(): void {
		$labels = self::labels();
		foreach ( self::capabilities() as $slug => $caps ) {
			$role = get_role( $slug );
			if ( null === $role ) {
				add_role( $slug, $labels[ $slug ], array_fill_keys( $caps, true ) );
				continue;
			}
			foreach ( $caps as $cap ) {
				if ( ! $role->has_cap( $cap ) ) {
					$role->add_cap( $cap );
				}
			}
		}
	}

	/**
	 * Remove the own roles (uninstall with confirmed deletion only).
	 *
	 * @return void
	 */
	public static function remove(): void {
		foreach ( self::ALL as $slug ) {
			remove_role( $slug );
		}
	}

	/**
	 * Whether a role slug belongs to Course Captain.
	 *
	 * @param string $slug Role slug.
	 * @return bool
	 */
	public static function is_own( string $slug ): bool {
		return in_array( $slug, self::ALL, true );
	}
}
