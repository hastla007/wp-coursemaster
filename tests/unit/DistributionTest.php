<?php
/**
 * R1-39: release ZIP build and J-17 artefact checks, incl. negative tests (R0-35 §3.1, RC-011/014/020).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions, WordPress.WP.AlternativeFunctions -- Release tooling test runs CLI scripts and reads local files.

/**
 * Builds the ZIP from the working tree and checks it; manipulated copies must fail.
 *
 * @group R1-39
 */
final class DistributionTest extends TestCase {

	/**
	 * Temp dir.
	 *
	 * @var string
	 */
	private static string $dir = '';

	/**
	 * Built ZIP.
	 *
	 * @var string
	 */
	private static string $zip = '';

	/**
	 * Build once.
	 */
	public static function setUpBeforeClass(): void {
		if ( ! class_exists( \ZipArchive::class ) || '' === (string) shell_exec( 'command -v git' ) ) {
			return;
		}
		$root      = dirname( __DIR__, 2 );
		self::$dir = sys_get_temp_dir() . '/ccap-dist-' . bin2hex( random_bytes( 4 ) );
		mkdir( self::$dir );
		self::$zip = trim( (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/tools/release/build-zip.php' ) . ' --worktree --out=' . escapeshellarg( self::$dir ) ) );
	}

	/**
	 * Cleanup.
	 */
	public static function tearDownAfterClass(): void {
		if ( '' !== self::$dir ) {
			shell_exec( 'rm -rf ' . escapeshellarg( self::$dir ) );
		}
	}

	/**
	 * Run the checker.
	 *
	 * @param string $zip ZIP.
	 * @return array{code: int, findings: list<string>}
	 */
	private static function check( string $zip ): array {
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/tools/release/check-zip.php' ) . ' ' . escapeshellarg( $zip ) . ' --json 2>&1', $out, $code );
		$data = json_decode( implode( "\n", $out ), true );
		return array(
			'code'     => $code,
			'findings' => array_map( static fn( array $f ): string => $f['id'], (array) ( $data['findings'] ?? array() ) ),
		);
	}

	/**
	 * Copy of the ZIP with one change.
	 *
	 * @param callable $change fn(\ZipArchive).
	 * @return string
	 */
	private static function variant( callable $change ): string {
		$copy = self::$dir . '/variant-' . bin2hex( random_bytes( 3 ) ) . '.zip';
		copy( self::$zip, $copy );
		$zip = new \ZipArchive();
		$zip->open( $copy );
		$change( $zip );
		$zip->close();
		return $copy;
	}

	/**
	 * Guard: environment.
	 */
	protected function setUp(): void {
		if ( '' === self::$zip || ! is_file( self::$zip ) ) {
			self::markTestSkipped( 'git/ZipArchive required.' );
		}
	}

	/**
	 * Positive: the built package passes all checks; build is reproducible.
	 */
	public function test_package_passes_and_is_reproducible(): void {
		self::assertSame(
			array(
				'code'     => 0,
				'findings' => array(),
			),
			self::check( self::$zip )
		);
		$first = hash_file( 'sha256', self::$zip );
		$again = trim( (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( dirname( __DIR__, 2 ) . '/tools/release/build-zip.php' ) . ' --worktree --out=' . escapeshellarg( self::$dir . '/second' ) ) );
		self::assertSame( $first, hash_file( 'sha256', $again ), 'same input, same bytes (SOURCE_DATE_EPOCH)' );
		self::assertStringContainsString( basename( self::$zip ), (string) file_get_contents( self::$dir . '/SHA256SUMS' ) );
	}

	/**
	 * Negative: development files, Update URI, legacy names, version mismatch, syntax error.
	 */
	public function test_manipulated_packages_fail(): void {
		$cases = array(
			'NEGATIVE' => static fn( \ZipArchive $z ) => $z->addFromString( 'course-captain/tests/unit/Leak.php', '<?php' ),
			'RC-020'   => static function ( \ZipArchive $z ): void {
				$header = (string) $z->getFromName( 'course-captain/course-captain.php' );
				$z->addFromString( 'course-captain/course-captain.php', str_replace( ' * Text Domain:', " * Update URI:        https://example.org/\n * Text Domain:", $header ) );
			},
			'RC-014'   => static fn( \ZipArchive $z ) => $z->addFromString( 'course-captain/src/bootspruefung.txt', 'x' ),
			'VERSION'  => static function ( \ZipArchive $z ): void {
				$readme = (string) $z->getFromName( 'course-captain/readme.txt' );
				$z->addFromString( 'course-captain/readme.txt', (string) preg_replace( '/^Stable tag:.*$/m', 'Stable tag: 9.9.9', $readme ) );
			},
			'LINT'     => static fn( \ZipArchive $z ) => $z->addFromString( 'course-captain/src/Broken.php', '<?php function (' ),
			'POSITIVE' => static fn( \ZipArchive $z ) => $z->deleteName( 'course-captain/LICENSE' ),
			'ROOT'     => static fn( \ZipArchive $z ) => $z->addFromString( 'other/file.txt', 'x' ),
		);
		foreach ( $cases as $expected => $change ) {
			$result = self::check( self::variant( $change ) );
			self::assertSame( 1, $result['code'], $expected );
			self::assertContains( $expected, $result['findings'], $expected . ': ' . implode( ',', $result['findings'] ) );
		}
	}
}
