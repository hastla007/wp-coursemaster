<?php
/**
 * Unit tests of the uninstall manifest and the uninstall decision.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Tests\Support\StorageKeyScanner;

/**
 * R0-16 §2.5, R0-39 FG-10.
 *
 * @group R1-01
 */
final class UninstallManifestTest extends TestCase {

	/**
	 * Shipped PHP code.
	 *
	 * @return list<string>
	 */
	private static function shipped_files(): array {
		$root = self::root();
		return StorageKeyScanner::php_files( array( $root . '/course-captain.php', $root . '/uninstall.php', $root . '/src' ) );
	}

	/**
	 * REC-020: every own storage key used in code is in the manifest.
	 *
	 * @group REC-020
	 */
	public function test_REC_020_every_used_key_is_in_the_manifest(): void {
		$keys = StorageKeyScanner::own_keys( self::shipped_files() );

		self::assertArrayHasKey( 'course_captain_role_grants', $keys, 'Scanner must resolve class constants.' );
		self::assertArrayHasKey( 'ccap_hourly', $keys, 'Scanner must see cron hooks.' );

		$missing = array();
		foreach ( $keys as $key => $locations ) {
			if ( ! UninstallManifest::covers( $key ) ) {
				$missing[ $key ] = $locations;
			}
		}
		self::assertSame( array(), $missing, 'Keys missing in UninstallManifest: ' . implode( ', ', array_keys( $missing ) ) );
	}

	/**
	 * REC-020 negative test: a deliberately forgotten option is detected.
	 *
	 * @group REC-020
	 */
	public function test_REC_020_forgotten_option_is_detected(): void {
		$keys = StorageKeyScanner::own_keys( array( __DIR__ . '/fixtures/forgotten-option.php' ) );

		self::assertSame( array( 'ccap_forgotten_job', 'course_captain_forgotten_flag' ), array_keys( $keys ) );
		self::assertFalse( UninstallManifest::covers( 'course_captain_forgotten_flag' ) );
		self::assertFalse( UninstallManifest::covers( 'ccap_forgotten_job' ) );
	}

	/**
	 * LIC-063 / FG-10: the manifest never covers names of the paid add-on and holds no wildcard.
	 *
	 * @group LIC-063
	 */
	public function test_LIC_063_manifest_has_only_exact_own_names(): void {
		foreach ( UninstallManifest::all_option_names() as $name ) {
			self::assertMatchesRegularExpression( '/^course_captain_[a-z0-9_]+$/', $name );
			self::assertStringStartsNotWith( 'course_captain_pro', $name );
			self::assertStringNotContainsString( '%', $name );
		}
		self::assertFalse( UninstallManifest::covers( 'course_captain_pro_license' ) );
		self::assertFalse( UninstallManifest::covers( 'course_captain_something_foreign' ) );

		$uninstaller = (string) file_get_contents( self::root() . '/src/Kernel/Uninstaller.php' );
		self::assertDoesNotMatchRegularExpression( '/\bLIKE\b/i', $uninstaller, 'Uninstall must not delete by pattern (FG-10).' );
	}

	/**
	 * The manifest lists the capabilities and cron hooks of the kernel.
	 */
	public function test_manifest_contains_caps_and_cron_hooks(): void {
		$manifest = UninstallManifest::to_array();

		self::assertSame( Capabilities::PRIMITIVE, $manifest['capabilities'] );
		self::assertSame( CronHooks::ALL, $manifest['cron_hooks'] );
		self::assertContains( 'course_captain_settings_undo_general', $manifest['options'] );
		self::assertContains( 'course_captain_mail_template_test_mail', $manifest['options'] );
		self::assertSame( count( $manifest['options'] ), count( array_unique( $manifest['options'] ) ) );
	}

	/**
	 * Values of the general settings option and the expected decision.
	 *
	 * @return array<string, array{mixed, bool}>
	 */
	public static function deletion_settings(): array {
		return array(
			'option missing'         => array( null, false ),
			'not an array'           => array( 'yes', false ),
			'no values'              => array( array( '_schema' => 1 ), false ),
			'flag missing'           => array( array( 'values' => array() ), false ),
			'flag false'             => array( array( 'values' => array( 'delete_data_on_uninstall' => false ) ), false ),
			'flag string "1"'        => array( array( 'values' => array( 'delete_data_on_uninstall' => '1' ) ), false ),
			'flag integer 1'         => array( array( 'values' => array( 'delete_data_on_uninstall' => 1 ) ), false ),
			'flag confirmed (true)'  => array( array( 'values' => array( 'delete_data_on_uninstall' => true ) ), true ),
			'flag at top level only' => array( array( 'delete_data_on_uninstall' => true ), false ),
		);
	}

	/**
	 * REC-018/REC-019 (decision part): data is deleted only with the confirmed setting (E-89), fail closed.
	 *
	 * @group REC-018
	 * @group REC-019
	 *
	 * @param mixed $general  Option value.
	 * @param bool  $expected Expected decision.
	 * @dataProvider deletion_settings
	 */
	public function test_REC_019_deletion_requires_confirmed_setting( $general, bool $expected ): void {
		self::assertSame( $expected, Uninstaller::is_data_deletion_confirmed( $general ) );
	}
}
