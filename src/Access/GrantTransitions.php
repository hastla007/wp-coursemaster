<?php
/**
 * Grant state machine G-01…G-15 (R0-09 §2.1/§2.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

defined( 'ABSPATH' ) || exit;

/**
 * Pure guard. `revoked` and `expired` are final per source revision;
 * a renewed right is a new revision (E-29).
 */
final class GrantTransitions {

	public const PENDING_CLAIM        = 'pending_claim';
	public const PENDING_CONFIRMATION = 'pending_confirmation';
	public const ACTIVE               = 'active';
	public const SUSPENDED            = 'suspended';
	public const EXPIRED              = 'expired';
	public const REVOKED              = 'revoked';
	public const FAILED               = 'failed';

	/**
	 * All states.
	 *
	 * @var list<string>
	 */
	public const STATES = array( self::PENDING_CLAIM, self::PENDING_CONFIRMATION, self::ACTIVE, self::SUSPENDED, self::EXPIRED, self::REVOKED, self::FAILED );

	/**
	 * Allowed transitions (from => list of to). Creation (∅ →) is handled
	 * by the service; `active → active` (G-14) is an update without status change.
	 *
	 * @var array<string, list<string>>
	 */
	private const ALLOWED = array(
		self::PENDING_CONFIRMATION => array( self::ACTIVE, self::FAILED, self::REVOKED ),
		self::PENDING_CLAIM        => array( self::ACTIVE, self::FAILED, self::REVOKED ),
		self::ACTIVE               => array( self::SUSPENDED, self::EXPIRED, self::REVOKED ),
		self::SUSPENDED            => array( self::ACTIVE, self::EXPIRED, self::REVOKED ),
		self::FAILED               => array( self::PENDING_CONFIRMATION, self::PENDING_CLAIM, self::ACTIVE ),
		self::EXPIRED              => array(),
		self::REVOKED              => array(),
	);

	/**
	 * Whether a transition is allowed.
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @return bool
	 */
	public static function allowed( string $from, string $to ): bool {
		return in_array( $to, self::ALLOWED[ $from ] ?? array(), true );
	}

	/**
	 * Whether a state is final for its source revision.
	 *
	 * @param string $state State.
	 * @return bool
	 */
	public static function is_final( string $state ): bool {
		return in_array( $state, array( self::EXPIRED, self::REVOKED ), true );
	}
}
