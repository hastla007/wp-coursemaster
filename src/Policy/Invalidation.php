<?php
/**
 * Immediate effect of role, grant, staff and lock changes (R0-07 P-11).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Flushes the policy memo whenever an input of a decision changes.
 * Repositories of grants, seats, staff and locks fire
 * `course_captain_policy_changed` after their commit.
 */
final class Invalidation {

	/**
	 * WordPress hooks that change roles, capabilities or the site.
	 *
	 * @var list<string>
	 */
	public const HOOKS = array(
		'set_user_role',
		'add_user_role',
		'remove_user_role',
		'profile_update',
		'remove_user_from_blog',
		'add_user_to_blog',
		'switch_blog',
		'set_current_user',
		'deleted_user',
		'course_captain_policy_changed',
	);

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::HOOKS as $hook ) {
			add_action( $hook, array( PolicyService::class, 'flush' ), 1, 0 );
		}
		// Capability changes on roles (`WP_Role::add_cap`) update the roles option.
		add_action( 'update_option_' . $GLOBALS['wpdb']->prefix . 'user_roles', array( PolicyService::class, 'flush' ), 1, 0 );
	}
}
