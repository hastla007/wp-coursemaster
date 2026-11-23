<?php
/**
 * Who asks: a user or a system actor (R0-07 P-12).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Actor of a policy decision.
 */
final class Actor {

	public const USER        = 'user';
	public const SYSTEM_CRON = 'system:cron';
	public const SYSTEM_CLI  = 'system:cli';
	public const CONNECTOR   = 'connector';

	/**
	 * Constructor.
	 *
	 * @param string $type    Actor type.
	 * @param int    $user_id WordPress user ID (0 = guest or system).
	 * @param string $id      Connector ID for connector actors.
	 */
	private function __construct(
		public readonly string $type,
		public readonly int $user_id,
		public readonly string $id = '',
	) {
	}

	/**
	 * A user (0 = not logged in).
	 *
	 * @param int $user_id User ID.
	 * @return self
	 */
	public static function user( int $user_id ): self {
		return new self( self::USER, max( 0, $user_id ) );
	}

	/**
	 * The current user of the request (never from the payload, P-09).
	 *
	 * @return self
	 */
	public static function current(): self {
		return self::user( get_current_user_id() );
	}

	/**
	 * WP-Cron.
	 *
	 * @return self
	 */
	public static function cron(): self {
		return new self( self::SYSTEM_CRON, 0 );
	}

	/**
	 * WP-CLI.
	 *
	 * @return self
	 */
	public static function cli(): self {
		return new self( self::SYSTEM_CLI, 0 );
	}

	/**
	 * A connector (Pro registry, E-38).
	 *
	 * @param string $id Connector ID.
	 * @return self
	 */
	public static function connector( string $id ): self {
		return new self( self::CONNECTOR, 0, $id );
	}

	/**
	 * Whether this is a system or connector actor.
	 *
	 * @return bool
	 */
	public function is_system(): bool {
		return self::USER !== $this->type;
	}

	/**
	 * Stable key for memoization and audit (`actor_type:id`).
	 *
	 * @return string
	 */
	public function key(): string {
		return self::CONNECTOR === $this->type ? 'connector:' . $this->id : $this->type . ':' . $this->user_id;
	}
}
