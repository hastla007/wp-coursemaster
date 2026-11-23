<?php
/**
 * Unit tests of the plugin header, readme and version equality.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Kernel\Requirements;

/**
 * R0-35 §1.4, §1.6, §3.2; R0-20 §1.2.
 *
 * @group R1-01
 */
final class PluginHeaderTest extends TestCase {

	/**
	 * Header fields of a file.
	 *
	 * @param string $file File.
	 * @return array<string, string>
	 */
	private static function headers( string $file ): array {
		$content = (string) file_get_contents( $file, false, null, 0, 8192 );
		preg_match_all( '/^[\s\*#@]*([A-Za-z][A-Za-z ]+?)\s*:\s*(.+?)\s*$/m', $content, $matches, PREG_SET_ORDER );
		$headers = array();
		foreach ( $matches as $match ) {
			$headers[ $match[1] ] ??= $match[2];
		}
		return $headers;
	}

	/**
	 * RC-046 / RC-011 / LIC-003: required header lines and forbidden ones.
	 *
	 * @group RC-046
	 * @group RC-011
	 * @group LIC-003
	 */
	public function test_RC_046_plugin_header(): void {
		$headers = self::headers( self::root() . '/course-captain.php' );

		self::assertSame( 'Course Captain', $headers['Plugin Name'] );
		self::assertSame( '8.5', $headers['Requires PHP'] );
		self::assertSame( '7.0', $headers['Requires at least'] );
		self::assertSame( 'course-captain', $headers['Text Domain'] );
		self::assertSame( '/languages', $headers['Domain Path'] );
		self::assertSame( 'GPL-2.0-or-later', $headers['License'] );
		self::assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $headers['License URI'] );

		foreach ( array( 'Update URI', 'Requires Plugins', 'Network', 'WC requires at least', 'WC tested up to' ) as $forbidden ) {
			self::assertArrayNotHasKey( $forbidden, $headers, $forbidden . ' must not be set.' );
		}

		self::assertSame( Requirements::MIN_PHP, COURSE_CAPTAIN_MIN_PHP );
		self::assertSame( Requirements::MIN_WP, COURSE_CAPTAIN_MIN_WP );
		self::assertTrue( version_compare( $headers['Requires PHP'] . '.0', Requirements::MIN_PHP, '==' ) );
		self::assertSame( $headers['Requires at least'], Requirements::MIN_WP );
		self::assertFileExists( self::root() . '/LICENSE' );
	}

	/**
	 * RC-041 (repository part): header version = constant = Stable tag = top CHANGELOG entry.
	 *
	 * @group RC-041
	 */
	public function test_RC_041_versions_are_equal(): void {
		$root    = self::root();
		$headers = self::headers( $root . '/course-captain.php' );
		$readme  = self::headers( $root . '/readme.txt' );

		preg_match( "/define\\( 'COURSE_CAPTAIN_VERSION', '([^']+)' \\)/", (string) file_get_contents( $root . '/course-captain.php' ), $constant );
		preg_match( '/^## \[?([0-9][^\]\s]*)/m', (string) file_get_contents( $root . '/CHANGELOG.md' ), $changelog );

		self::assertSame( $headers['Version'], $constant[1] ?? null );
		self::assertSame( $headers['Version'], COURSE_CAPTAIN_VERSION );
		self::assertSame( $headers['Version'], $readme['Stable tag'] );
		self::assertSame( $headers['Version'], $changelog[1] ?? null );

		self::assertSame( $headers['Requires PHP'], $readme['Requires PHP'] );
		self::assertSame( $headers['Requires at least'], $readme['Requires at least'] );
		self::assertSame( '7.1', $readme['Tested up to'] );
		self::assertSame( 'GPLv2 or later', $readme['License'] );
		self::assertArrayNotHasKey( 'Requires Plugins', $readme );
	}
}
