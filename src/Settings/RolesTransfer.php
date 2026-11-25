<?php
/**
 * Transfer surface "Roles & permissions" (SET-CC-15, R0-27 §2.5, R0-31 IM-2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\RoleCaps;

defined( 'ABSPATH' ) || exit;

/**
 * Section `roles` = `{_schema: 1, roles: {<role>: [ccap_* …]}}`; never the
 * administrator role. Export only with its own opt-in (EX-1) and for
 * #24 ∧ `promote_users` or administrators; import only by administrators
 * (R0-27 §2.5) through {@see RoleCaps::check()} / {@see RoleCaps::apply()}
 * (never capabilities above the own set). Granting a high-impact
 * capability needs RC-07 (`roles.<role>`). The previous state goes to the
 * role undo slot (30 days).
 */
final class RolesTransfer implements TransferProvider {

	public const KEY = 'roles';

	/**
	 * Top-level key in the file.
	 *
	 * @return string
	 */
	public function key(): string {
		return self::KEY;
	}

	/**
	 * Translated name.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Roles & permissions', 'course-captain' );
	}

	/**
	 * Whether the export needs an opt-in.
	 *
	 * @return bool
	 */
	public function opt_in(): bool {
		return true;
	}

	/**
	 * Whether the actor may export this surface.
	 *
	 * @param Actor $actor Actor.
	 * @return bool
	 */
	public function can_export( Actor $actor ): bool {
		return $actor->is_system() || RoleCaps::can_manage( $actor->user_id );
	}

	/**
	 * Export section.
	 *
	 * @param Actor $actor Actor.
	 * @return array<string, mixed>
	 */
	public function export( Actor $actor ): array {
		$roles = array();
		foreach ( array_keys( RoleCaps::roles() ) as $role ) {
			$roles[ $role ] = RoleCaps::current( $role );
		}
		ksort( $roles );
		return array(
			'_schema' => 1,
			'roles'   => $roles,
		);
	}

	/**
	 * Diff against the current state; writes nothing.
	 *
	 * @param Actor                $actor     Actor.
	 * @param array<string, mixed> $section   File section.
	 * @param array                $confirmed Confirmed items (`*` = all).
	 * @phpstan-param list<string> $confirmed
	 * @return array{changed: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: array<string, string>}
	 */
	public function plan( Actor $actor, array $section, array $confirmed ): array {
		$out = array(
			'changed' => array(),
			'skipped' => array(),
			'errors'  => array(),
		);
		if ( ! $actor->is_system() && ! ( user_can( $actor->user_id, 'manage_options' ) && RoleCaps::can_manage( $actor->user_id ) ) ) {
			$out['skipped'][] = array(
				'item'   => '*',
				'reason' => 'forbidden',
			);
			return $out;
		}
		$roles = is_array( $section['roles'] ?? null ) ? $section['roles'] : array();
		ksort( $roles );
		foreach ( $roles as $role => $caps ) {
			$role = (string) $role;
			if ( 'administrator' === $role || null === get_role( $role ) ) {
				$out['skipped'][] = array(
					'item'   => $role,
					'reason' => 'administrator' === $role ? 'administrator' : 'unknown',
				);
				continue;
			}
			$caps    = array_values( array_unique( array_map( 'strval', array_filter( (array) $caps, 'is_scalar' ) ) ) );
			$unknown = array_values( array_diff( $caps, Capabilities::PRIMITIVE ) );
			foreach ( $unknown as $cap ) {
				$out['skipped'][] = array(
					'item'   => $role . ':' . $cap,
					'reason' => 'unknown',
				);
			}
			$caps = array_values( array_intersect( Capabilities::PRIMITIVE, $caps ) );
			try {
				$check = RoleCaps::check( $actor, $role, $caps );
			} catch ( CourseException $e ) {
				$out['skipped'][] = array(
					'item'   => $role,
					'reason' => 'ccap_forbidden' === $e->error_code ? 'forbidden' : 'unknown',
				);
				continue;
			}
			if ( array() !== $check['errors'] ) {
				$out['errors'][ $role ] = (string) reset( $check['errors'] );
				continue;
			}
			if ( array() === $check['added'] && array() === $check['removed'] ) {
				continue;
			}
			$risks = array() === $check['high_risk'] ? array() : array( 'RC-07' );
			if ( array() !== $risks && ! self::confirmed( $confirmed, $role ) ) {
				$out['skipped'][] = array(
					'item'   => $role,
					'reason' => 'confirmation',
					'risks'  => $risks,
				);
				continue;
			}
			$out['changed'][] = array(
				'item'    => $role,
				'before'  => RoleCaps::current( $role ),
				'after'   => $caps,
				'risks'   => $risks,
				'summary' => sprintf(
					/* translators: 1: role name, 2: added capabilities, 3: removed capabilities */
					__( '%1$s: adds %2$s; removes %3$s', 'course-captain' ),
					RoleCaps::roles()[ $role ] ?? $role,
					array() === $check['added'] ? '–' : implode( ', ', $check['added'] ),
					array() === $check['removed'] ? '–' : implode( ', ', $check['removed'] )
				),
			);
		}
		return $out;
	}

	/**
	 * Apply the plan.
	 *
	 * @param Actor                $actor     Actor.
	 * @param array<string, mixed> $section   File section.
	 * @param array                $confirmed Confirmed items (`*` = all).
	 * @phpstan-param list<string> $confirmed
	 * @return list<string> Applied items.
	 */
	public function apply( Actor $actor, array $section, array $confirmed ): array {
		$plan = $this->plan( $actor, $section, $confirmed );
		if ( array() !== $plan['errors'] || array() === $plan['changed'] ) {
			return array();
		}
		$previous = array();
		foreach ( array_keys( RoleCaps::roles() ) as $role ) {
			$previous[ $role ] = RoleCaps::current( $role );
		}
		update_option(
			RoleCaps::UNDO_OPTION,
			array(
				'values'     => $previous,
				'expires_at' => time() + RoleCaps::UNDO_TTL,
			),
			false
		);
		$done = array();
		foreach ( $plan['changed'] as $row ) {
			RoleCaps::apply( $actor, (string) $row['item'], array_values( array_map( 'strval', (array) $row['after'] ) ), array_values( array_map( 'strval', (array) $row['risks'] ) ) );
			$done[] = (string) $row['item'];
		}
		return $done;
	}

	/**
	 * Whether an item is confirmed.
	 *
	 * @param array  $confirmed Confirmed items.
	 * @param string $item      Item.
	 * @phpstan-param list<string> $confirmed
	 * @return bool
	 */
	private static function confirmed( array $confirmed, string $item ): bool {
		return in_array( '*', $confirmed, true ) || in_array( $item, $confirmed, true );
	}
}
