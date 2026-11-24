<?php
/**
 * SEC-028 (R0-17 AC-12, extends IMP-009): ZIP entries that collide by
 * Unicode normalisation (NFC/NFD) or case, nesting deeper than 32 levels
 * and segments longer than 255 bytes are rejected with a V3 rule.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Import\PackageValidator;
use PHPUnit\Framework\TestCase;

/**
 * Raw archives, read by the validator without WordPress.
 *
 * @group SEC-028
 * @group IMP-009
 */
final class PackageValidatorPathTest extends TestCase {

	/**
	 * Written archives.
	 *
	 * @var list<string>
	 */
	private array $files = array();

	/**
	 * Remove the archives.
	 */
	protected function tearDown(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test cleanup without WordPress.
			}
		}
		parent::tearDown();
	}

	/**
	 * Archive with a (minimal) manifest and the given entries.
	 *
	 * @param array $names Entry names.
	 * @phpstan-param list<string> $names
	 * @return string Path.
	 */
	private function zip( array $names ): string {
		if ( ! class_exists( \ZipArchive::class ) ) {
			self::markTestSkipped( 'ext-zip missing' );
		}
		$file = (string) tempnam( sys_get_temp_dir(), 'ccap-sec028-' );
		$zip  = new \ZipArchive();
		$zip->open( $file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( 'manifest.json', '{}' );
		foreach ( $names as $name ) {
			$zip->addFromString( $name, 'x' );
		}
		$zip->close();
		$this->files[] = $file;
		return $file;
	}

	/**
	 * V3 findings (rule and entry) of an archive.
	 *
	 * @param array $names Entry names.
	 * @phpstan-param list<string> $names
	 * @return list<string> `rule path`.
	 */
	private function v3( array $names ): array {
		$out = array();
		foreach ( PackageValidator::validate( $this->zip( $names ) )['errors'] as $error ) {
			if ( str_starts_with( $error['rule'], 'V3_' ) ) {
				$out[] = $error['rule'] . ' ' . $error['path'];
			}
		}
		return $out;
	}

	/**
	 * NFC and NFD spelling of the same name collide; the second entry is rejected.
	 */
	public function test_unicode_normalisation_collision(): void {
		$nfc = "media/\u{00DC}bersicht.pdf";
		$nfd = "media/U\u{0308}bersicht.pdf";
		self::assertNotSame( $nfc, $nfd, 'different bytes' );
		self::assertSame( array( 'V3_collision ' . $nfd ), $this->v3( array( $nfc, $nfd ) ) );
	}

	/**
	 * Names that differ only in case collide (also non-ASCII letters).
	 */
	public function test_case_collision(): void {
		self::assertSame( array( 'V3_collision media/Logo.PNG' ), $this->v3( array( 'media/logo.png', 'media/Logo.PNG' ) ) );
		self::assertSame( array( "V3_collision media/\u{00C4}rger.pdf" ), $this->v3( array( "media/\u{00E4}rger.pdf", "media/\u{00C4}rger.pdf" ) ) );
		self::assertSame( array( 'V3_collision media/Sub/x.png' ), $this->v3( array( 'media/sub/x.png', 'media/Sub/x.png' ) ), 'folder names count too' );
	}

	/**
	 * Nesting depth: 32 segments allowed, 33 rejected.
	 */
	public function test_depth_limit(): void {
		$ok   = 'media/' . str_repeat( 'd/', PackageValidator::MAX_DEPTH - 2 ) . 'f.png';
		$deep = 'media/' . str_repeat( 'd/', PackageValidator::MAX_DEPTH - 1 ) . 'f.png';
		self::assertCount( PackageValidator::MAX_DEPTH, explode( '/', $ok ) );
		self::assertSame( array(), $this->v3( array( $ok ) ) );
		self::assertSame( array( 'V3_path ' . $deep ), $this->v3( array( $deep ) ) );
	}

	/**
	 * Segment length in bytes: 255 allowed, 256 rejected (multi-byte counts bytes).
	 */
	public function test_name_length_limit(): void {
		$ok    = 'media/' . str_repeat( 'a', 251 ) . '.png';
		$long  = 'media/' . str_repeat( 'a', 252 ) . '.png';
		$bytes = 'media/' . str_repeat( "\u{00E4}", 126 ) . '.png';
		self::assertSame( array(), $this->v3( array( $ok ) ) );
		self::assertSame( array( 'V3_path ' . $long ), $this->v3( array( $long ) ) );
		self::assertSame( 256, strlen( (string) substr( $bytes, 6 ) ) );
		self::assertSame( array( 'V3_path ' . $bytes ), $this->v3( array( $bytes ) ), '130 characters, 256 bytes' );
	}

	/**
	 * Distinct names stay valid; the existing V3 rules still apply.
	 */
	public function test_distinct_names_and_existing_rules(): void {
		self::assertSame( array(), $this->v3( array( 'media/a.png', 'media/b.png', 'data/questions/000001.json', 'data/questions/000002.json' ) ) );
		self::assertSame( array( 'V3_path ../evil.php', 'V3_entry other/file.json' ), $this->v3( array( '../evil.php', 'other/file.json' ) ) );
	}
}
