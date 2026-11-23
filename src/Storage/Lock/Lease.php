<?php
/**
 * Held lease.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Lock;

defined( 'ABSPATH' ) || exit;

/**
 * A lease row this process holds, identified by owner and fencing token.
 */
final class Lease {

	/**
	 * Constructor.
	 *
	 * @param string      $name           Lease name (`schema-migration`, `import`, …).
	 * @param string      $owner          Random owner id of this run.
	 * @param int         $fence          Fencing token; grows with every takeover.
	 * @param string|null $previous_owner Owner that was taken over, if any.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $owner,
		public readonly int $fence,
		public readonly ?string $previous_owner = null,
	) {
	}

	/**
	 * Whether the lease was taken over from another (dead or expired) owner.
	 *
	 * @return bool
	 */
	public function took_over(): bool {
		return null !== $this->previous_owner;
	}
}
