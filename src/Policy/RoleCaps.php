<?php
/**
 * Role tab service: Course Captain capabilities per WordPress role
 * (R0-27 `roles.cap_assignments`, R0-31 §3.3 RB-1…RB-8, R0-30 §3.3.6).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * The effect lives in the WordPress roles; every capability Course Captain
 * grants is recorded in the manifest `course_captain_role_grants` so that
 * reset, restore and uninstall only remove own grants (RB-8). Rules:
 * `administrator` is never changeable (F-51); only `ccap_*` primitives can
 * be assigned (never `unfiltered_html`, RB-7); nobody grants or removes a
 * capability they do not hold (F-50); #19…#23 only by administrators;
 * #14 and #19…#24 are high-impact and need RC-07 (checked by the caller).
 */
final class RoleCaps {

	public const UNDO_OPTION = 'course_captain_settings_undo_roles';
	public const UNDO_TTL    = 2592000;

	/**
	 * High-impact capabilities (#14, #19…#24).
	 *
	 * @var list<string>
	 */
	public const HIGH_RISK = array( 'ccap_view_attempt_details', 'ccap_manage_settings', 'ccap_manage_integrations', 'ccap_view_audit_log', 'ccap_view_system_status', 'ccap_run_maintenance', 'ccap_assign_staff' );

	/**
	 * Capabilities only administrators may assign (#19…#23).
	 *
	 * @var list<string>
	 */
	public const ADMIN_ONLY = array( 'ccap_manage_settings', 'ccap_manage_integrations', 'ccap_view_audit_log', 'ccap_view_system_status', 'ccap_run_maintenance' );

	/**
	 * Whether a user may open the role tab (#24 + `promote_users`, or administrator).
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function can_manage( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		return ( user_can( $user_id, 'manage_options' ) && user_can( $user_id, 'ccap_manage_settings' ) )
			|| ( user_can( $user_id, 'ccap_assign_staff' ) && user_can( $user_id, 'promote_users' ) );
	}

	/**
	 * Editable roles (all except administrator) => display name.
	 *
	 * @return array<string, string>
	 */
	public static function roles(): array {
		$out = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			if ( 'administrator' !== $slug ) {
				$out[ (string) $slug ] = translate_user_role( (string) $role['name'] );
			}
		}
		return $out;
	}

	/**
	 * Current Course Captain capabilities of a role.
	 *
	 * @param string $role Role slug.
	 * @return list<string>
	 */
	public static function current( string $role ): array {
		$object = get_role( $role );
		if ( null === $object ) {
			return array();
		}
		return array_values( array_intersect( Capabilities::PRIMITIVE, array_keys( array_filter( $object->capabilities ) ) ) );
	}

	/**
	 * Number of accounts per role.
	 *
	 * @return array<string, int>
	 */
	public static function counts(): array {
		$counts = count_users();
		return array_map( 'intval', (array) ( $counts['avail_roles'] ?? array() ) );
	}

	/**
	 * Check a desired capability set without writing.
	 *
	 * @param Actor  $actor Actor.
	 * @param string $role  Role.
	 * @param array  $caps  Desired `ccap_*` capabilities.
	 * @phpstan-param list<string> $caps
	 * @return array{added: list<string>, removed: list<string>, errors: array<string, string>, high_risk: list<string>}
	 * @throws CourseException `ccap_forbidden`, `ccap_not_found`.
	 */
	public static function check( Actor $actor, string $role, array $caps ): array {
		if ( ! $actor->is_system() && ! self::can_manage( $actor->user_id ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change roles.', 403 );
		}
		$errors = array();
		if ( 'administrator' === $role ) {
			return array(
				'added'     => array(),
				'removed'   => array(),
				'errors'    => array( 'role' => 'role_admin' ),
				'high_risk' => array(),
			);
		}
		if ( null === get_role( $role ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown role.', 404 );
		}
		$desired = array_values( array_intersect( Capabilities::PRIMITIVE, $caps ) );
		$current = self::current( $role );
		$added   = array_values( array_diff( $desired, $current ) );
		$removed = array_values( array_diff( $current, $desired ) );
		$admin   = $actor->is_system() || user_can( $actor->user_id, 'manage_options' );
		foreach ( array_merge( $added, $removed ) as $cap ) {
			if ( ! $actor->is_system() && ! user_can( $actor->user_id, $cap ) ) {
				$errors[ $cap ] = 'cap_not_own';
			} elseif ( in_array( $cap, self::ADMIN_ONLY, true ) && ! $admin ) {
				$errors[ $cap ] = 'cap_admin_only';
			}
		}
		foreach ( array_diff( $caps, Capabilities::PRIMITIVE ) as $foreign ) {
			$errors[ (string) $foreign ] = 'cap_unknown';
		}
		return array(
			'added'     => $added,
			'removed'   => $removed,
			'errors'    => $errors,
			'high_risk' => array_values( array_intersect( $added, self::HIGH_RISK ) ),
		);
	}

	/**
	 * Apply a capability set to a role (caller confirmed RC-07 where needed).
	 *
	 * @param Actor  $actor Actor.
	 * @param string $role  Role.
	 * @param array  $caps  Desired capabilities.
	 * @param array  $confirmed Confirmed risk codes (audit).
	 * @phpstan-param list<string> $caps
	 * @phpstan-param list<string> $confirmed
	 * @return array{added: list<string>, removed: list<string>}
	 * @throws CourseException `ccap_forbidden`, `ccap_not_found`, `ccap_validation_failed` (data.fields = cap => rule).
	 */
	public static function apply( Actor $actor, string $role, array $caps, array $confirmed = array() ): array {
		$check = self::check( $actor, $role, $caps );
		if ( array() !== $check['errors'] ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', array_keys( $check['errors'] ) ) ), 422, array( 'fields' => $check['errors'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
		}
		self::write( $role, $check['added'], $check['removed'] );
		if ( array() !== $check['added'] || array() !== $check['removed'] ) {
			self::audit( $actor, 'role_caps_changed', $role, $check['added'], $check['removed'], $confirmed );
		}
		return array(
			'added'   => $check['added'],
			'removed' => $check['removed'],
		);
	}

	/**
	 * Reset (RB-8): own roles back to their activation defaults, foreign roles
	 * lose only manifest grants; capabilities from role plugins stay. The
	 * previous state goes to the undo slot (30 days).
	 *
	 * @param Actor $actor Actor.
	 * @return array<string, array{added: list<string>, removed: list<string>}>
	 * @throws CourseException `ccap_forbidden`.
	 */
	public static function reset( Actor $actor ): array {
		if ( ! $actor->is_system() && ! ( user_can( $actor->user_id, 'manage_options' ) && self::can_manage( $actor->user_id ) ) ) {
			throw new CourseException( 'ccap_forbidden', 'Only administrators may reset roles.', 403 );
		}
		$grants   = Capabilities::recorded_grants();
		$defaults = Roles::capabilities();
		$previous = array();
		$changes  = array();
		foreach ( array_keys( self::roles() ) as $role ) {
			$previous[ $role ] = self::current( $role );
			$target            = array_values( array_intersect( Capabilities::PRIMITIVE, $defaults[ $role ] ?? array() ) );
			$removed           = array_values( array_diff( array_intersect( $grants[ $role ] ?? array(), $previous[ $role ] ), $target ) );
			$added             = Roles::is_own( $role ) ? array_values( array_diff( $target, $previous[ $role ] ) ) : array();
			if ( array() === $added && array() === $removed ) {
				continue;
			}
			self::write( $role, $added, $removed );
			$changes[ $role ] = array(
				'added'   => $added,
				'removed' => $removed,
			);
			self::audit( $actor, 'role_caps_changed', $role, $added, $removed, array(), 'reset' );
		}
		update_option(
			self::UNDO_OPTION,
			array(
				'values'     => $previous,
				'expires_at' => time() + self::UNDO_TTL,
			),
			false
		);
		return $changes;
	}

	/**
	 * Undo the last reset.
	 *
	 * @param Actor $actor Actor.
	 * @return bool
	 * @throws CourseException `ccap_forbidden`.
	 */
	public static function undo( Actor $actor ): bool {
		if ( ! $actor->is_system() && ! ( user_can( $actor->user_id, 'manage_options' ) && self::can_manage( $actor->user_id ) ) ) {
			throw new CourseException( 'ccap_forbidden', 'Only administrators may undo a role reset.', 403 );
		}
		$undo = get_option( self::UNDO_OPTION );
		if ( ! is_array( $undo ) || (int) ( $undo['expires_at'] ?? 0 ) < time() || ! is_array( $undo['values'] ?? null ) ) {
			return false;
		}
		foreach ( $undo['values'] as $role => $caps ) {
			if ( 'administrator' === $role || null === get_role( (string) $role ) ) {
				continue;
			}
			$caps    = array_values( array_intersect( Capabilities::PRIMITIVE, (array) $caps ) );
			$current = self::current( (string) $role );
			$added   = array_values( array_diff( $caps, $current ) );
			$removed = array_values( array_diff( $current, $caps ) );
			if ( array() !== $added || array() !== $removed ) {
				self::write( (string) $role, $added, $removed );
				self::audit( $actor, 'role_caps_changed', (string) $role, $added, $removed, array(), 'undo' );
			}
		}
		delete_option( self::UNDO_OPTION );
		return true;
	}

	/**
	 * Write to the role and the manifest.
	 *
	 * @param string $role    Role.
	 * @param array  $added   Added.
	 * @param array  $removed Removed.
	 * @phpstan-param list<string> $added
	 * @phpstan-param list<string> $removed
	 * @return void
	 */
	private static function write( string $role, array $added, array $removed ): void {
		$object = get_role( $role );
		if ( null === $object ) {
			return;
		}
		foreach ( $added as $cap ) {
			$object->add_cap( $cap );
		}
		foreach ( $removed as $cap ) {
			$object->remove_cap( $cap );
		}
		$grants          = Capabilities::recorded_grants();
		$grants[ $role ] = array_values( array_diff( array_unique( array_merge( $grants[ $role ] ?? array(), $added ) ), $removed ) );
		if ( array() === $grants[ $role ] ) {
			unset( $grants[ $role ] );
		}
		update_option( Capabilities::GRANTS_OPTION, $grants, false );
		\CourseCaptain\Cache\CacheStore::bump_content();
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * Audit `role_caps_changed` (SET-081, RB-4).
	 *
	 * @param Actor  $actor     Actor.
	 * @param string $action    Action.
	 * @param string $role      Role.
	 * @param array  $added     Added.
	 * @param array  $removed   Removed.
	 * @param array  $confirmed Confirmed risks.
	 * @param string $context   Context.
	 * @phpstan-param list<string> $added
	 * @phpstan-param list<string> $removed
	 * @phpstan-param list<string> $confirmed
	 * @return void
	 */
	private static function audit( Actor $actor, string $action, string $role, array $added, array $removed, array $confirmed, string $context = 'settings' ): void {
		AuditLog::write(
			Database::current(),
			$actor,
			$action,
			'role',
			$role,
			null,
			array(
				'role'            => $role,
				'added'           => $added,
				'removed'         => $removed,
				'confirmed_risks' => $confirmed,
				'context'         => $context,
			)
		);
	}
}
