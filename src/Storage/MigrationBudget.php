<?php
/**
 * Time budget of one migration run (R0-37 §2.3, R0-14 RB-02).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Divisible work (backfill batches) stops when the budget is used up; the
 * run then re-schedules `ccap_migrate`. A single DDL is not divisible.
 */
final class MigrationBudget {

	/**
	 * Upper limit for admin, cron and overdue runs, in seconds.
	 */
	public const MAX_SECONDS = 20;

	/**
	 * Deadline as `microtime(true)`, INF when unlimited.
	 *
	 * @var float
	 */
	private float $deadline;

	/**
	 * Constructor.
	 *
	 * @param float|null $seconds Budget in seconds; null = unlimited.
	 */
	public function __construct( private ?float $seconds ) {
		$this->deadline = null === $seconds ? INF : microtime( true ) + $seconds;
	}

	/**
	 * Budget for a trigger context: CLI unlimited, otherwise
	 * min(20 s, max_execution_time / 2).
	 *
	 * @param string   $context            Trigger context.
	 * @param int|null $max_execution_time Override of `max_execution_time` (tests).
	 * @return self
	 */
	public static function for_context( string $context, ?int $max_execution_time = null ): self {
		if ( MigrationTrigger::CONTEXT_CLI === $context ) {
			return new self( null );
		}
		$limit = $max_execution_time ?? (int) ini_get( 'max_execution_time' );
		return new self( $limit > 0 ? min( (float) self::MAX_SECONDS, $limit / 2 ) : (float) self::MAX_SECONDS );
	}

	/**
	 * Budget in seconds, null when unlimited.
	 *
	 * @return float|null
	 */
	public function seconds(): ?float {
		return $this->seconds;
	}

	/**
	 * Whether the budget is used up.
	 *
	 * @return bool
	 */
	public function expired(): bool {
		return microtime( true ) >= $this->deadline;
	}
}
