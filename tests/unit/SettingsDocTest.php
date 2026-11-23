<?php
/**
 * SET-CC-29: the operator handbook docs/EINSTELLUNGEN.md matches the settings register.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

/**
 * Runs the generator in a separate PHP process (it stubs WordPress functions
 * of its own) and compares with the committed file.
 *
 * @group SET-CC-29
 */
final class SettingsDocTest extends TestCase {

	/**
	 * Generator output.
	 *
	 * @param string $mode Argument.
	 * @return array{0: int, 1: string}
	 */
	private static function run_generator( string $mode ): array {
		$root = dirname( __DIR__, 2 );
		$cmd  = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $root . '/tools/docs/settings-doc.php' ) . ( '' === $mode ? '' : ' ' . escapeshellarg( $mode ) ) . ' 2>&1';
		$out  = array();
		$code = 0;
		exec( $cmd, $out, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Test runs the repository generator.
		return array( $code, implode( "\n", $out ) );
	}

	/**
	 * The committed handbook is up to date (`--check`).
	 */
	public function test_handbook_is_up_to_date(): void {
		list( $code, $output ) = self::run_generator( '--check' );
		self::assertSame( 0, $code, $output );
	}

	/**
	 * The handbook names every register key, every tab and the CLI.
	 */
	public function test_handbook_covers_the_register(): void {
		list( $code, $doc ) = self::run_generator( '' );
		self::assertSame( 0, $code );
		$schema = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Settings/SettingsSchema.php' );
		self::assertStringContainsString( '`general.learning_area_slug`', $doc );
		self::assertStringContainsString( '`privacy.trusted_proxies`', $doc );
		self::assertStringContainsString( '`certificates.base_template`', $doc );
		self::assertStringContainsString( 'wp course-captain settings set', $doc );
		self::assertStringContainsString( '## Sperren und Übersteuerungen', $doc );
		preg_match( '/const TABS = array\((.*?)\);/s', $schema, $block );
		preg_match_all( "/'([a-z]+)'\s+=> (?:true|false),/", (string) ( $block[1] ?? '' ), $tabs );
		self::assertNotEmpty( $tabs[1] );
		foreach ( $tabs[1] as $tab ) {
			self::assertStringContainsString( '(`' . $tab . '`)', $doc, $tab );
		}
	}
}
