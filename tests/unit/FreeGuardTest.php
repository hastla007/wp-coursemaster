<?php
/**
 * Unit tests of the Free guard and the text domain lint.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Tests\Guards\FreeGuard;
use CourseCaptain\Tests\Guards\TextdomainLint;

require_once dirname( __DIR__ ) . '/guards/free-guard.php';
require_once dirname( __DIR__ ) . '/guards/textdomain-lint.php';

/**
 * R0-18 §2.5, R0-39 §1.2 (LIC-*), R0-35 RC-014/RC-020.
 *
 * @group R1-01
 */
final class FreeGuardTest extends TestCase {

	/**
	 * Temporary directories created by a test.
	 *
	 * @var list<string>
	 */
	private array $temp = array();

	/**
	 * Remove temporary directories.
	 */
	protected function tearDown(): void {
		foreach ( $this->temp as $dir ) {
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $iterator as $file ) {
				$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
			}
			rmdir( $dir );
		}
		parent::tearDown();
	}

	/**
	 * Create a temporary plugin tree.
	 *
	 * @param array<string, string> $files Relative path => content.
	 * @return string
	 */
	private function tree( array $files ): string {
		$dir = sys_get_temp_dir() . '/ccap-guard-' . bin2hex( random_bytes( 6 ) );
		mkdir( $dir );
		$this->temp[] = $dir;
		foreach ( $files as $path => $content ) {
			if ( ! is_dir( dirname( $dir . '/' . $path ) ) ) {
				mkdir( dirname( $dir . '/' . $path ), 0777, true );
			}
			file_put_contents( $dir . '/' . $path, $content );
		}
		return $dir;
	}

	/**
	 * LIC-001 / RC-020: the Free source tree has no findings.
	 *
	 * @group LIC-001
	 * @group RC-020
	 */
	public function test_LIC_001_source_tree_is_clean(): void {
		$guard  = new FreeGuard( self::root(), self::root() . '/tools/qa/free-guard-allow.txt' );
		$result = $guard->run();

		self::assertContains( 'course-captain.php', $guard->files() );
		self::assertContains( 'uninstall.php', $guard->files() );
		self::assertContains( 'src/Kernel/Plugin.php', $guard->files() );
		self::assertContains( 'languages/course-captain.pot', $guard->files() );
		self::assertSame( array(), $result['errors'] );
		self::assertSame( array(), $result['findings'] );
	}

	/**
	 * LIC-010: self-test with one hit fixture per guard ID (15, G-15 rights keys from SET-CC-01) and a clean fixture (0).
	 *
	 * @group LIC-010
	 */
	public function test_LIC_010_self_test(): void {
		self::assertSame( array(), FreeGuard::self_test( dirname( __DIR__ ) . '/guards/fixtures/free-guard' ) );

		$hits = ( new FreeGuard( dirname( __DIR__ ) . '/guards/fixtures/free-guard/hits' ) )->run();
		self::assertCount( 15, $hits['findings'] );
	}

	/**
	 * LIC-011: an allowlist entry for G-7 makes the guard fail.
	 *
	 * @group LIC-011
	 */
	public function test_LIC_011_g7_is_not_allowable(): void {
		$root = $this->tree(
			array(
				'src/a.php' => "<?php\n\$x = 'entitlement';\n",
				'allow.txt' => "src/a.php | G-7 | test | 2999-12-31\n",
			)
		);

		$result = ( new FreeGuard( $root, $root . '/allow.txt' ) )->run();

		self::assertCount( 1, $result['findings'] );
		self::assertSame( 'G-7', $result['findings'][0]['id'] );
		self::assertCount( 1, $result['errors'] );
		self::assertStringContainsString( 'not allowable', $result['errors'][0] );
	}

	/**
	 * Allowlist: a valid entry suppresses a finding, an expired one is an error.
	 */
	public function test_allowlist_entries_and_expiry(): void {
		$root = $this->tree(
			array(
				'src/a.php' => "<?php\n\$k = 'license_key';\n",
				'src/b.php' => "<?php\n\$k = 'license_key';\n",
				'allow.txt' => "# comment\nsrc/a.php | G-8 | documented reason | 2999-12-31\nsrc/b.php | G-8 | old | 2000-01-01\n",
			)
		);

		$result = ( new FreeGuard( $root, $root . '/allow.txt' ) )->run();

		self::assertSame( array( 'src/b.php' ), array_column( $result['findings'], 'file' ) );
		self::assertCount( 1, $result['errors'] );
		self::assertStringContainsString( 'expiry', $result['errors'][0] );
	}

	/**
	 * RC-014 / RC-020 negative tests: legacy name and `Update URI` turn the guard red.
	 *
	 * @group RC-014
	 * @group RC-020
	 * @group LIC-003
	 */
	public function test_RC_014_negative_samples_are_detected(): void {
		$root = $this->tree(
			array(
				'course-captain.php' => "<?php\n/**\n * Plugin Name: X\n * Update URI: https://example.test\n * Requires Plugins: other\n */\n",
				'src/legacy.php'     => "<?php\n// copied from bootspruefung\n",
			)
		);

		$ids = array_column( ( new FreeGuard( $root ) )->run()['findings'], 'id' );
		sort( $ids );

		self::assertSame( array( 'G-13', 'G-14', 'G-4' ), $ids );
	}

	/**
	 * J-03: the text domain lint is clean on the source tree and catches violations.
	 */
	public function test_textdomain_lint(): void {
		self::assertSame( array(), ( new TextdomainLint( self::root() ) )->run() );

		$root     = $this->tree(
			array(
				'src/a.php' => "<?php\n__( 'Hello', 'other-domain' );\n__( 'Prüfung', 'course-captain' );\n\$d = 'course-captain';\n_e( 'x', \$d );\n",
			)
		);
		$problems = ( new TextdomainLint( $root ) )->run();

		self::assertCount( 4, $problems );
		self::assertStringContainsString( '.pot is missing', $problems[0] );
	}

	/**
	 * R1-19 (I18N-R-02/-03/-06/-12, I18N-005): static i18n scan catches variable or
	 * concatenated texts, placeholders without translator comment, fixed display
	 * date formats and physical CSS properties; clean code passes.
	 *
	 * @group R1-19
	 */
	public function test_i18n_static_scan(): void {
		$root     = $this->tree(
			array(
				'languages/course-captain.pot' => '',
				'src/a.php'                    => "<?php\n__( \$text, 'course-captain' );\n__( 'Hello ' . \$name, 'course-captain' );\nsprintf( __( 'Hello %s', 'course-captain' ), \$n );\nsprintf( _n( '%d item', '%d items', \$c, 'course-captain' ), \$c );\n\$d = wp_date( 'd.m.Y H:i', 0 );\n\$css = '.a{margin-left:1rem}';\n",
				'src/b.php'                    => "<?php\n/* translators: %s: name */\nsprintf( __( 'Hi %s', 'course-captain' ), \$n );\n__( '100%% done', 'course-captain' );\n\$iso = wp_date( 'Y-m-d', 0 );\n\$css = '.a{margin-inline-start:1rem;inset-inline-start:0}';\n",
			)
		);
		$problems = ( new TextdomainLint( $root ) )->run();
		self::assertCount( 6, $problems, implode( "\n", $problems ) );
		$all = implode( "\n", $problems );
		foreach ( array( 'a.php:2: text argument 1', 'a.php:3: text argument 1', 'a.php:4: __() with placeholders', 'a.php:5: _n() with placeholders', 'a.php:6: wp_date() with fixed display format', 'a.php:7: physical CSS property' ) as $expected ) {
			self::assertStringContainsString( $expected, $all );
		}
		self::assertStringNotContainsString( 'b.php', $all );
	}
}
