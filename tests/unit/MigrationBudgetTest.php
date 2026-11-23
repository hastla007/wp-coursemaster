<?php
/**
 * R1-40: time budget and backfill cursor (R0-37 §2.3, §4.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Storage\Backfiller;
use CourseCaptain\Storage\MigrationBudget;
use CourseCaptain\Storage\MigrationTrigger;

/**
 * Pure helpers of the migrator.
 *
 * @group R1-40
 * @group MIG-009
 */
final class MigrationBudgetTest extends TestCase {

	/**
	 * CLI unlimited, otherwise min(20 s, max_execution_time / 2).
	 */
	public function test_budget_per_context(): void {
		self::assertNull( MigrationBudget::for_context( MigrationTrigger::CONTEXT_CLI, 30 )->seconds() );
		self::assertSame( 15.0, MigrationBudget::for_context( MigrationTrigger::CONTEXT_CRON, 30 )->seconds() );
		self::assertSame( 20.0, MigrationBudget::for_context( MigrationTrigger::CONTEXT_ADMIN, 120 )->seconds() );
		self::assertSame( 20.0, MigrationBudget::for_context( MigrationTrigger::CONTEXT_FRONTEND, 0 )->seconds() );
		self::assertTrue( ( new MigrationBudget( 0.0 ) )->expired() );
		self::assertFalse( ( new MigrationBudget( null ) )->expired() );
		self::assertFalse( ( new MigrationBudget( 60.0 ) )->expired() );
	}

	/**
	 * Remaining seconds for the "stop before the next chunk" rule (R0-38 §4.3).
	 */
	public function test_remaining(): void {
		self::assertSame( INF, ( new MigrationBudget( null ) )->remaining() );
		self::assertSame( 0.0, ( new MigrationBudget( 0.0 ) )->remaining() );
		$left = ( new MigrationBudget( 60.0 ) )->remaining();
		self::assertGreaterThan( 59.0, $left );
		self::assertLessThanOrEqual( 60.0, $left );
	}

	/**
	 * Cursor `<pass>:<id>`; anything else starts from the beginning.
	 */
	public function test_cursor_format(): void {
		self::assertSame( array( 0, 0 ), Backfiller::parse_cursor( null ) );
		self::assertSame( array( 0, 300 ), Backfiller::parse_cursor( '0:300' ) );
		self::assertSame( array( 2, 5 ), Backfiller::parse_cursor( '2:5' ) );
		self::assertSame( array( 0, 0 ), Backfiller::parse_cursor( 'garbage' ) );
		self::assertSame( array( 0, 0 ), Backfiller::parse_cursor( 17 ) );
	}
}
