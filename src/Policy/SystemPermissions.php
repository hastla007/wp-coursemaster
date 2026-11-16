<?php
/**
 * Fixed permissions of system actors (R0-07 P-12, §4.6–§4.8).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * System actors never call `current_user_can()`. Each actor type has a
 * fixed list of actions; everything else is denied. Allowed system actions
 * are always audited (`actor_type` in the audit log).
 */
final class SystemPermissions {

	/**
	 * Actions per system actor type. Connectors need the Pro registry
	 * (E-38, E-291): no connector action is allowed in Free.
	 *
	 * @var array<string, list<string>>
	 */
	public const ALLOWED = array(
		Actor::SYSTEM_CRON => array(
			PolicyService::MANAGE_GRANT,
			PolicyService::GRADE_ATTEMPT,
			PolicyService::MANAGE_GROUP,
			PolicyService::MANAGE_PROGRESS,
		),
		Actor::SYSTEM_CLI  => array(
			PolicyService::MANAGE_GRANT,
			PolicyService::CREATE_GRANT,
			PolicyService::MANAGE_GROUP,
			PolicyService::MANAGE_IMPORT,
			PolicyService::MANAGE_PROGRESS,
			PolicyService::LOCK_LEARNER,
		),
		Actor::CONNECTOR   => array(),
	);

	/**
	 * Decide for a system actor.
	 *
	 * @param Actor  $actor  System actor.
	 * @param string $action Action.
	 * @return Decision
	 */
	public static function decide( Actor $actor, string $action ): Decision {
		if ( in_array( $action, self::ALLOWED[ $actor->type ] ?? array(), true ) ) {
			return Decision::allow( array(), true );
		}
		return Decision::deny( Decision::FORBIDDEN, Actor::CONNECTOR === $actor->type ? 'feature_unavailable' : 'system_not_allowed' );
	}
}
