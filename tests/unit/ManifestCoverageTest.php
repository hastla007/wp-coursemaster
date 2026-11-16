<?php
/**
 * REC-020 extension: tables and post types used in code are in the uninstall manifest.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Tests\Support\StorageKeyScanner;

/**
 * The key scan of R1-01 covers options, meta, transients and cron. This
 * scan adds every table name passed to `Database::table()`/`insert()` and
 * every post type registered in code.
 *
 * @group R1-23
 */
final class ManifestCoverageTest extends TestCase {

	/**
	 * Shipped PHP files.
	 *
	 * @return list<string>
	 */
	private static function files(): array {
		$root = self::root();
		return StorageKeyScanner::php_files( array( $root . '/course-captain.php', $root . '/uninstall.php', $root . '/src' ) );
	}

	/**
	 * Literal table names used in code.
	 *
	 * @param string[] $files Files.
	 * @phpstan-param list<string> $files
	 * @return array<string, string> Table → first location.
	 */
	private static function used_tables( array $files ): array {
		$found = array();
		foreach ( $files as $file ) {
			$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Source scan.
			preg_match_all( "/(?:->table|->insert|->update|->delete)\(\s*'(ccap_[a-z0-9_]+)'/", $code, $m );
			foreach ( $m[1] as $table ) {
				$found[ $table ] ??= basename( $file );
			}
		}
		ksort( $found );
		return $found;
	}

	/**
	 * REC-020: every table used in code is dropped by the confirmed uninstall
	 * and classified for privacy (GM-173).
	 *
	 * @group REC-020
	 */
	public function test_REC_020_every_used_table_is_in_the_manifest(): void {
		$used = self::used_tables( self::files() );
		self::assertArrayHasKey( 'ccap_grant', $used, 'scanner must find tables' );

		$missing = array_diff_key( $used, array_flip( UninstallManifest::tables() ) );
		self::assertSame( array(), $missing, 'Tables missing in the manifest: ' . implode( ', ', array_keys( $missing ) ) );
		self::assertSame( array(), array_values( array_diff( UninstallManifest::tables(), array_keys( PrivacyService::CLASSIFICATION ) ) ) );
	}

	/**
	 * REC-020 negative test: an unknown table is reported.
	 *
	 * @group REC-020
	 */
	public function test_REC_020_forgotten_table_is_detected(): void {
		$file = tempnam( sys_get_temp_dir(), 'ccap' );
		file_put_contents( $file, "<?php \$db->table( 'ccap_forgotten_table' );" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Fixture.

		$used = self::used_tables( array( (string) $file ) );
		unlink( (string) $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Fixture.

		self::assertSame( array( 'ccap_forgotten_table' ), array_keys( $used ) );
		self::assertNotContains( 'ccap_forgotten_table', UninstallManifest::tables() );
	}

	/**
	 * Every post type registered in code is an own post type of the manifest.
	 *
	 * @group REC-020
	 */
	public function test_REC_020_registered_post_types_are_in_the_manifest(): void {
		$code = (string) file_get_contents( self::root() . '/src/Course/PostTypes.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Source scan.
		preg_match_all( '/(?:register_post_type|self::register_type)\(\s*Relations::([A-Z_]+)/', $code, $m );
		$registered = array_map( static fn( string $name ): string => (string) constant( Relations::class . '::' . $name ), $m[1] );

		self::assertCount( 5, $registered );
		self::assertSame( array(), array_values( array_diff( $registered, UninstallManifest::post_types() ) ) );
		self::assertSame( UninstallManifest::to_array()['post_types'], UninstallManifest::post_types() );
	}
}
