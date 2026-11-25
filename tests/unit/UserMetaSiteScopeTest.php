<?php
/**
 * MS-008 (R0-25 §4, MS-R-05): no fixed Course Captain user-meta key outside
 * the declared exception list.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Tests\Support\StorageKeyScanner;

/**
 * Static check of `src/`, `course-captain.php` and `uninstall.php`.
 *
 * @group MS-008
 * @group R1-02
 */
final class UserMetaSiteScopeTest extends TestCase {

	/**
	 * Fixed user-meta keys allowed by MS-R-05: the WordPress profile
	 * language `locale` (network-wide core key) and the network user meta
	 * declared in the uninstall manifest (`UninstallManifest::USER_META`,
	 * deleted only once every site agreed, REC-019/MS-015). Everything else
	 * per user and site goes through `update_user_option()`.
	 *
	 * @return list<string>
	 */
	private static function allowed(): array {
		return array_merge( array( 'locale' ), UninstallManifest::user_meta() );
	}

	/**
	 * MS-008: every `get|update|add|delete_user_meta()` call in the plugin
	 * names a literal key or a class constant of the same file, and the key
	 * is on the exception list; dynamic keys are reported, too.
	 *
	 * @group MS-008
	 */
	public function test_MS_008_no_fixed_user_meta_outside_exceptions(): void {
		$root       = self::root();
		$violations = array();
		$seen       = array();
		foreach ( StorageKeyScanner::php_files( array( $root . '/course-captain.php', $root . '/uninstall.php', $root . '/src' ) ) as $file ) {
			$code = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Source scan.
			preg_match_all( '/\b(?:get|update|add|delete)_user_meta\(\s*[^,()]+(?:\([^()]*\))?[^,()]*,\s*([^,)]+)/', $code, $calls );
			foreach ( $calls[1] as $argument ) {
				$key = self::resolve( trim( $argument ), $code );
				if ( null === $key ) {
					$violations[] = substr( $file, strlen( $root ) + 1 ) . ': dynamic user-meta key ' . trim( $argument );
					continue;
				}
				$seen[ $key ] = true;
				if ( ! in_array( $key, self::allowed(), true ) ) {
					$violations[] = substr( $file, strlen( $root ) + 1 ) . ': user meta ' . $key . ' (use update_user_option, MS-R-05)';
				}
			}
		}
		self::assertSame( array(), $violations );
		self::assertNotSame( array(), $seen, 'the scan finds the known calls (scanner works)' );
		foreach ( array_keys( $seen ) as $key ) {
			self::assertContains( $key, self::allowed() );
		}
	}

	/**
	 * The scanner flags a fixed key that is not on the list (negative check).
	 *
	 * @group MS-008
	 */
	public function test_MS_008_resolver(): void {
		$code = "<?php class X { public const META = 'ccap_x'; }";
		self::assertSame( 'ccap_learning_mode', self::resolve( "'ccap_learning_mode'", $code ) );
		self::assertSame( 'ccap_x', self::resolve( 'self::META', $code ) );
		self::assertNull( self::resolve( '$key', $code ) );
		self::assertNotContains( 'ccap_learning_mode', self::allowed() );
	}

	/**
	 * Key of a call argument: literal string or `self::`/`static::` constant
	 * defined in the same file; null when dynamic.
	 *
	 * @param string $argument Argument source.
	 * @param string $code     File source.
	 * @return string|null
	 */
	private static function resolve( string $argument, string $code ): ?string {
		if ( 1 === preg_match( '/^([\'"])([^\'"]+)\1$/', $argument, $m ) ) {
			return $m[2];
		}
		if ( 1 === preg_match( '/^(?:self|static)::([A-Z0-9_]+)$/', $argument, $m )
			&& 1 === preg_match( '/const\s+' . $m[1] . '\s*=\s*([\'"])([^\'"]+)\1/', $code, $c ) ) {
			return $c[2];
		}
		return null;
	}
}
