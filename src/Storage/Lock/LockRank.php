<?php
/**
 * Global lock order.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Lock;

defined( 'ABSPATH' ) || exit;

/**
 * Ranks M0 … M6 of R0-21 §2.4. Locks are acquired in ascending rank;
 * no mutex is acquired inside an open transaction.
 */
final class LockRank {

	/** Migration, leases of long runs. */
	public const M0_RUN = 0;

	/** `learner:<user>`. */
	public const M1_LEARNER = 1;

	/** `grant-source:<type>:<id>`. */
	public const M2_SOURCE = 2;

	/** `group-seats:<group>`. */
	public const M3_GROUP = 3;

	/** `grant-chain:<user>:<course>`. */
	public const M4_CHAIN = 4;

	/** `course_publish:<course>`. */
	public const M5_PUBLISH = 5;

	/** `exam_start:<user>`. */
	public const M6_EXAM_START = 6;

	/**
	 * Wait times in seconds per rank: interactive / background (R0-21 §2.4).
	 *
	 * @var array<int, array{0: int, 1: int}>
	 */
	public const WAIT = array(
		self::M0_RUN        => array( 0, 0 ),
		self::M1_LEARNER    => array( 2, 10 ),
		self::M2_SOURCE     => array( 2, 10 ),
		self::M3_GROUP      => array( 2, 10 ),
		self::M4_CHAIN      => array( 2, 10 ),
		self::M5_PUBLISH    => array( 0, 0 ),
		self::M6_EXAM_START => array( 2, 2 ),
	);
}
