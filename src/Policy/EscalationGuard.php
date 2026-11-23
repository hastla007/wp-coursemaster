<?php
/**
 * Role assignment limits (R0-07 §3.5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Nobody can hand out a role that carries rights the assigning user does
 * not hold; `administrator` and `ccap_course_manager` additionally need
 * `promote_users`. The filter only removes roles, never adds any (E-84).
 */
final class EscalationGuard {

	/**
	 * Register the filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'editable_roles', array( self::class, 'filter_editable_roles' ), 20 );
	}

	/**
	 * `editable_roles` callback.
	 *
	 * @param mixed $roles Roles keyed by slug.
	 * @return mixed
	 */
	public static function filter_editable_roles( $roles ) {
		if ( ! is_array( $roles ) ) {
			return $roles;
		}
		$user = get_current_user_id();
		foreach ( array_keys( $roles ) as $slug ) {
			$caps = isset( $roles[ $slug ]['capabilities'] ) && is_array( $roles[ $slug ]['capabilities'] ) ? $roles[ $slug ]['capabilities'] : array();
			if ( ! self::may_assign( $user, (string) $slug, $caps ) ) {
				unset( $roles[ $slug ] );
			}
		}
		return $roles;
	}

	/**
	 * Whether a user may assign a role.
	 *
	 * Own roles: `ccap_author`/`ccap_trainer` with `ccap_assign_staff`,
	 * `ccap_support` additionally with `ccap_manage_grants` (course manager),
	 * `ccap_course_manager` only with `promote_users`. Any role (also core
	 * roles) is removed when it carries an own or sensitive capability the
	 * user lacks.
	 *
	 * @param int                 $user User.
	 * @param string              $slug Role slug.
	 * @param array<string, bool> $caps Capabilities of the role.
	 * @return bool
	 */
	public static function may_assign( int $user, string $slug, array $caps ): bool {
		foreach ( PolicyService::sensitive_caps() as $cap ) {
			if ( ! empty( $caps[ $cap ] ) && ! user_can( $user, $cap ) ) {
				return false;
			}
		}
		if ( 'administrator' === $slug || Roles::COURSE_MANAGER === $slug ) {
			return user_can( $user, 'promote_users' );
		}
		if ( Roles::SUPPORT === $slug ) {
			return user_can( $user, 'promote_users' ) || ( user_can( $user, 'ccap_assign_staff' ) && user_can( $user, 'ccap_manage_grants' ) );
		}
		if ( Roles::is_own( $slug ) ) {
			return user_can( $user, 'promote_users' ) || user_can( $user, 'ccap_assign_staff' );
		}
		return true;
	}
}
