<?php
/**
 * R1-18: Golden-Master traceability check (R0-05 §1 → tests or versioned exceptions).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Tools\Qa\GmCoverage;

require_once dirname( __DIR__, 2 ) . '/tools/qa/gm-coverage.php';

/**
 * Fails on every catalogue ID without test group and without exception entry.
 *
 * @group R1-18
 */
final class GmCoverageTest extends TestCase {

	/**
	 * The repository: GM-001…GM-210 complete; nothing missing, stale, invalid or unknown.
	 */
	public function test_every_catalogue_id_is_tested_or_excepted(): void {
		$root = dirname( __DIR__, 2 );
		$ids  = GmCoverage::catalogue( (string) file_get_contents( $root . '/docs/r0/R0-05-golden-master.md' ) );
		self::assertSame( array_map( static fn( int $n ): string => sprintf( 'GM-%03d', $n ), range( 1, 210 ) ), $ids );

		$report = GmCoverage::repository( $root );
		self::assertSame( array(), $report['missing'], 'GM IDs without test and without exception' );
		self::assertSame( array(), $report['stale'], 'exception or partial entries that no longer match' );
		self::assertSame( array(), $report['invalid'], 'exception entries without category/reference/reason' );
		self::assertSame( array(), $report['unknown'], 'IDs that are not in the catalogue' );
		self::assertSame( 210, count( $report['covered'] ) + count( $report['excepted'] ) );
	}

	/**
	 * The checker itself: gaps, stale and invalid entries are reported.
	 */
	public function test_checker_reports_gaps(): void {
		$doc = "## 1. Katalog\n\n| a | b | c | d | e | GM-ID | f | g |\n|---|---|---|---|---|---|---|---|\n"
			. "| x | – | y | F | z | GM-001 | R1-01 | nein |\n| x | – | y | F | z | GM-002 | R1-01 | nein |\n"
			. "| x | – | y | F | z | GM-003 | R1-01 | ja |\n| x | – | y | F | z | GM-004 | R1-01 | ja |\n\n## 2. Fixture\n\n| GM-999 | a | b | c | d | GM-998 |\n";
		$ids = GmCoverage::catalogue( $doc );
		self::assertSame( array( 'GM-001', 'GM-002', 'GM-003', 'GM-004' ), $ids );

		$report = GmCoverage::report(
			$ids,
			array(
				'GM-001' => array( 'ATest.php::test_a' ),
				'GM-002' => array( 'ATest.php::test_b' ),
				'GM-005' => array( 'ATest.php::test_c' ),
			),
			array(
				'exceptions' => array(
					'GM-002' => array( 'open', 'R1-01', 'Tagged and excepted at once.' ),
					'GM-003' => array( 'later', 'R1-01', 'Unknown category here.' ),
				),
				'partial'    => array( 'GM-004' => 'not tagged' ),
			)
		);
		self::assertSame( array( 'GM-004' ), $report['missing'] );
		self::assertSame( array( 'GM-002', 'GM-004' ), $report['stale'] );
		self::assertSame( array( 'GM-003' ), $report['invalid'] );
		self::assertSame( array( 'GM-005' ), $report['unknown'] );
	}
}
