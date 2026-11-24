<?php
/**
 * SEC-043 (R0-17 T-10): report CSVs neutralise formula starts (CSV
 * injection) – the cell helpers and a guard that every CSV producer in
 * `src/` builds its cells through one of them.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Certificate\CertificateAdmin;
use CourseCaptain\Import\ImportReport;
use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Reads local source files.

/**
 * Pure helpers, no WordPress.
 *
 * @group SEC-043
 */
final class CsvInjectionTest extends TestCase {

	/**
	 * Values that a spreadsheet would read as a formula or control input.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function dangerous(): array {
		return array(
			'equals' => array( '=HYPERLINK("http://evil","x")' ),
			'plus'   => array( '+1+cmd|\'/C calc\'!A0' ),
			'minus'  => array( '-2+3' ),
			'at'     => array( '@SUM(1,2)' ),
			'tab'    => array( "\t=1+1" ),
			'return' => array( "\r=1+1" ),
		);
	}

	/**
	 * Cell of certificate list, course report and audit export: prefixed
	 * with an apostrophe and quoted.
	 *
	 * @dataProvider dangerous
	 *
	 * @param string $value Value.
	 */
	public function test_report_cell_neutralises_formula_starts( string $value ): void {
		self::assertSame( '"\'' . str_replace( '"', '""', $value ) . '"', CertificateAdmin::csv_cell( $value ) );
	}

	/**
	 * Cell of the import report (BIG-004): prefixed with an apostrophe
	 * (quoting is left to `fputcsv`).
	 *
	 * @dataProvider dangerous
	 *
	 * @param string $value Value.
	 */
	public function test_import_report_cell_neutralises_formula_starts( string $value ): void {
		self::assertSame( "'" . $value, ImportReport::cell( $value ) );
	}

	/**
	 * Harmless values stay unchanged (apart from quoting); quotes are doubled.
	 */
	public function test_harmless_values_unchanged(): void {
		self::assertSame( '"Anna Muster"', CertificateAdmin::csv_cell( 'Anna Muster' ) );
		self::assertSame( '""', CertificateAdmin::csv_cell( '' ) );
		self::assertSame( '"Kurs ""A"" = gut"', CertificateAdmin::csv_cell( 'Kurs "A" = gut' ), 'formula sign inside the value is harmless' );
		self::assertSame( '"2026-11-24"', CertificateAdmin::csv_cell( '2026-11-24' ) );
		self::assertSame( 'Anna', ImportReport::cell( 'Anna' ) );
		self::assertSame( '42', ImportReport::cell( 42 ) );
		self::assertSame( '', ImportReport::cell( array( '=1' ) ), 'non-scalar values are dropped' );
	}

	/**
	 * Every CSV producer in `src/` (BOM-prefixed output or `fputcsv`) builds
	 * its cells through a neutralising helper.
	 */
	public function test_every_csv_producer_uses_a_cell_helper(): void {
		$root      = dirname( __DIR__, 2 );
		$producers = array();
		$iterator  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$code = (string) file_get_contents( $file->getPathname() );
			if ( str_contains( $code, '\xEF\xBB\xBF' ) || str_contains( $code, 'fputcsv(' ) ) {
				$producers[ substr( $file->getPathname(), strlen( $root ) + 1 ) ] = $code;
			}
		}
		ksort( $producers );
		self::assertSame(
			array( 'src/Certificate/CertificateAdmin.php', 'src/Import/ImportReport.php', 'src/Ops/AuditView.php', 'src/Progress/CourseReport.php' ),
			array_keys( $producers ),
			'new CSV producer: route its cells through CertificateAdmin::csv_cell() or ImportReport::cell() and add it here'
		);
		foreach ( $producers as $path => $code ) {
			self::assertMatchesRegularExpression( '/csv_cell|self::cell\(/', $code, $path );
		}
	}
}
