<?php
/**
 * SEC-045 (R0-17 §4.3, Gate C-15): no forbidden claims ("tamper-proof",
 * "proctoring", "DSGVO-konform" …) in UI strings, readme.txt and the user
 * documentation. The same list runs in check-zip.php on the release ZIP.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Reads local files.

/**
 * Word list over POT, PO, readme.txt and docs/user.
 *
 * @group R1-39
 * @group SEC-045
 */
final class WordListTest extends TestCase {

	/**
	 * Patterns.
	 *
	 * @return array<string, string>
	 */
	private static function words(): array {
		return require dirname( __DIR__, 2 ) . '/tools/qa/sec-045-wordlist.php';
	}

	/**
	 * Claims found in a text.
	 *
	 * @param string $text Text.
	 * @return list<string>
	 */
	private static function claims( string $text ): array {
		$out = array();
		foreach ( self::words() as $word => $pattern ) {
			if ( 1 === preg_match( $pattern, $text ) ) {
				$out[] = $word;
			}
		}
		return $out;
	}

	/**
	 * Files under review are free of the listed claims.
	 */
	public function test_SEC_045_no_forbidden_claims(): void {
		$root  = dirname( __DIR__, 2 );
		$files = array_merge(
			array( $root . '/readme.txt' ),
			(array) glob( $root . '/languages/*.pot' ),
			(array) glob( $root . '/languages/*.po' ),
			(array) glob( $root . '/docs/user/*.md' )
		);
		self::assertGreaterThanOrEqual( 4, count( $files ) );
		$hits = array();
		foreach ( $files as $file ) {
			foreach ( self::claims( (string) file_get_contents( (string) $file ) ) as $word ) {
				$hits[] = basename( (string) $file ) . ': ' . $word;
			}
		}
		self::assertSame( array(), $hits );
	}

	/**
	 * The patterns catch the listed claims and spare the recommended wording.
	 */
	public function test_SEC_045_patterns(): void {
		foreach ( array( 'Proctoring included', 'manipulationssichere Prüfung', 'Fälschungssicher!', 'DSGVO-konform', 'eine amtliche Prüfung', 'tamper-proof certificates', 'GDPR-compliant by default', 'official exams' ) as $claim ) {
			self::assertNotSame( array(), self::claims( $claim ), $claim );
		}
		foreach ( array( 'serverseitige Zeitmessung', 'Nachweis online überprüfbar', 'Server-side timing', 'Certificate can be verified online', 'practice or course exam' ) as $ok ) {
			self::assertSame( array(), self::claims( $ok ), $ok );
		}
	}
}
