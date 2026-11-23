<?php
/**
 * Read-only access to an external grant source (R0-19 §5.8, K-02).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

defined( 'ABSPATH' ) || exit;

/**
 * Real adapters (WooCommerce, Voucher, Membership, Workflow) are Pro; the
 * Free core ships the contract, the reconciler and a FakeSource for tests.
 *
 * Snapshot (array): `source_type`, `reference`, `status` (raw), `revision`
 * (monotonic int, stored as `source_seq`), `user_id` (or null), `lines`
 * (list of `line`, `targets` [`course_id`, `module_id`, `scopes`],
 * `valid_until`, `refund` none|partial|full).
 */
interface GrantSourceAdapter {

	/**
	 * Source type of the created grants `[a-z0-9_]{1,32}`.
	 *
	 * @return string
	 */
	public function source_type(): string;

	/**
	 * Contract version (SemVer).
	 *
	 * @return string
	 */
	public function contract_version(): string;

	/**
	 * Current state of a source object; null = does not exist (any more).
	 *
	 * @param string $reference Source reference.
	 * @return array<string, mixed>|null
	 */
	public function snapshot( string $reference ): ?array;
}
