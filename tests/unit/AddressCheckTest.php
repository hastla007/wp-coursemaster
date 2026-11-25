<?php
/**
 * SET-CC-14: the German translations keep their form of address
 * (de_DE "du", de_DE_formal "Sie"), checked by tools/i18n/check-address.php.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Runs the address check in a separate PHP process.
 *
 * @group SET-CC-14
 */
final class AddressCheckTest extends TestCase {

	/**
	 * Exit code and output of the check.
	 *
	 * @param string[] $args Arguments.
	 * @return array{0: int, 1: string}
	 */
	private static function run_check( array $args = array() ): array {
		$root = dirname( __DIR__, 2 );
		$cmd  = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/tools/i18n/check-address.php' );
		foreach ( $args as $arg ) {
			$cmd .= ' ' . escapeshellarg( $arg );
		}
		$out  = array();
		$code = 0;
		exec( $cmd . ' 2>&1', $out, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Test runs the repository tool.
		return array( $code, implode( "\n", $out ) );
	}

	/**
	 * The bundled de_DE file uses "du", de_DE_formal uses "Sie".
	 */
	public function test_bundled_translations_keep_their_address(): void {
		$root = dirname( __DIR__, 2 );
		self::assertFileExists( $root . '/languages/course-captain-de_DE.po' );
		self::assertFileExists( $root . '/languages/course-captain-de_DE_formal.po' );
		list( $code, $output ) = self::run_check();
		self::assertSame( 0, $code, $output );
		self::assertStringContainsString( 'course-captain-de_DE.po (du):', $output );
		self::assertStringContainsString( 'course-captain-de_DE_formal.po (Sie):', $output );
		self::assertStringContainsString( 'check-address: OK', $output );
	}

	/**
	 * The word list catches slips in both directions and spares "Sie"/"Ihr" at the start of a sentence ("they"/"their").
	 */
	public function test_word_list_flags_slips(): void {
		$fixtures              = __DIR__ . '/fixtures/i18n';
		list( $code, $output ) = self::run_check( array( '--informal=' . $fixtures . '/address-informal.po', '--formal=' . $fixtures . '/address-formal.po' ) );
		self::assertSame( 1, $code, $output );
		self::assertStringContainsString( 'address-informal.po (du): 6 translations, 3 findings', $output );
		self::assertStringContainsString( '[Sie] Bitte geben Sie einen Grund ein.', $output );
		self::assertStringContainsString( '[Ihnen] Dieser Inhalt steht Ihnen', $output );
		self::assertStringContainsString( '[Ihrem] %d Dateien in Ihrem Entwurf', $output );
		self::assertStringNotContainsString( 'Sie kommt am', $output );
		self::assertStringContainsString( 'address-formal.po (Sie): 5 translations, 3 findings', $output );
		self::assertStringContainsString( '[Wähle] Wähle einen Kurs.', $output );
		self::assertStringContainsString( '[Du, darfst] Du darfst das nicht tun.', $output );
		self::assertStringContainsString( '[deine] Prüfen Sie deine Eingabe', $output );
		self::assertStringNotContainsString( 'Ihre Eingaben sind noch da', $output );
		self::assertStringContainsString( 'check-address: 6 finding(s)', $output );
	}
}
