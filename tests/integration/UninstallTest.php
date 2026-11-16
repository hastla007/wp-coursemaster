<?php
/**
 * Integration tests of the uninstall routine.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Kernel\UninstallManifest;

/**
 * R0-16 §2.5, R0-39 FG-10 (REC-018/019 are executed in full by R1-23).
 *
 * @group R1-01
 */
final class UninstallTest extends TestCase {

	/**
	 * Foreign options with similar names that must survive every uninstall.
	 *
	 * @var array<string, string>
	 */
	private const FOREIGN = array(
		'course_captain_pro_license'       => 'add-on activation',
		'course_captain_pro_license_state' => 'add-on snapshot',
		'course_captain_foreign_setting'   => 'unknown owner',
		'ccap_foreign_option'              => 'unknown owner',
		'cc_settings'                      => 'cookie banner',
	);

	/**
	 * Activate and add foreign data.
	 */
	public function set_up(): void {
		parent::set_up();
		self::clear_own_cron();
		Plugin::activate( false );
		update_option( 'course_captain_settings_display', array( 'values' => array() ) );
		update_option( 'course_captain_mail_template_test_mail', array( 'en_US' => 'x' ) );
		foreach ( self::FOREIGN as $name => $value ) {
			update_option( $name, $value );
		}
		set_transient( 'course_captain_foreign_transient', 'x' );
	}

	/**
	 * Foreign data is untouched.
	 */
	private static function assert_foreign_data_intact(): void {
		foreach ( self::FOREIGN as $name => $value ) {
			self::assertSame( $value, get_option( $name ), $name );
		}
		self::assertSame( 'x', get_transient( 'course_captain_foreign_transient' ) );
	}

	/**
	 * REC-018 (partial): default uninstall keeps data and removes only cron and rewrite rules.
	 *
	 * @group REC-018
	 */
	public function test_REC_018_default_uninstall_keeps_data(): void {
		update_option( 'rewrite_rules', array( 'x' => 'y' ) );

		$result = Uninstaller::run();

		self::assertSame( array( get_current_blog_id() => false ), $result );
		self::assertSame( array(), self::own_cron_events() );
		self::assertFalse( get_option( 'rewrite_rules' ) );
		self::assertSame( Capabilities::PRIMITIVE, self::ccap_caps_of( 'administrator' ) );
		self::assertNotFalse( get_option( Capabilities::GRANTS_OPTION ) );
		self::assertNotFalse( get_option( 'course_captain_settings_display' ) );
		self::assert_foreign_data_intact();
	}

	/**
	 * A non-boolean flag (e.g. "1" from a manual edit) never deletes data (fail closed).
	 *
	 * @group REC-019
	 */
	public function test_REC_019_unconfirmed_flag_keeps_data(): void {
		update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( 'delete_data_on_uninstall' => '1' ) ) );

		self::assertSame( array( get_current_blog_id() => false ), Uninstaller::run() );
		self::assertNotFalse( get_option( UninstallManifest::GENERAL_SETTINGS_OPTION ) );
		self::assertSame( Capabilities::PRIMITIVE, self::ccap_caps_of( 'administrator' ) );
	}

	/**
	 * REC-019 (partial) / LIC-063: confirmed deletion removes exactly the manifest, nothing foreign.
	 *
	 * @group REC-019
	 * @group LIC-063
	 */
	public function test_REC_019_confirmed_deletion_removes_only_manifest_names(): void {
		update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( 'delete_data_on_uninstall' => true ) ) );
		get_role( 'editor' )?->add_cap( 'ccap_view_reports' );
		get_role( 'editor' )?->add_cap( 'edit_others_posts' );

		$manifests = array();
		$listener  = static function ( array $manifest ) use ( &$manifests ): void {
			$manifests[] = $manifest;
		};
		add_action( 'course_captain_uninstall', $listener );

		$result = Uninstaller::run();
		remove_action( 'course_captain_uninstall', $listener );

		self::assertSame( array( get_current_blog_id() => true ), $result );
		self::assertSame( array( UninstallManifest::to_array() ), $manifests );
		self::assertSame( array(), self::own_cron_events() );
		self::assertSame( array(), self::ccap_caps_of( 'administrator' ) );
		self::assertSame( array(), self::ccap_caps_of( 'editor' ) );
		self::assertTrue( get_role( 'editor' )?->has_cap( 'edit_others_posts' ) ?? false, 'Foreign caps stay.' );

		$remaining = array_diff( self::own_options(), array_keys( self::FOREIGN ) );
		self::assertSame( array(), array_values( $remaining ), 'Only foreign options may remain.' );
		self::assert_foreign_data_intact();
	}
}
