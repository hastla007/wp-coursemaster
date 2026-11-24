<?php
/**
 * R1-23: restore and uninstall probes per site (REC-019, REC-024; E-49, MS-015).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Schema;

/**
 * Two sites with real tables. Multisite is not officially supported (E-07,
 * E-49); the probes prove site isolation only.
 *
 * @group R1-23
 * @group ms-required
 */
final class RecoveryMultisiteTest extends SchemaTestCase {

	/**
	 * Second site.
	 *
	 * @var int
	 */
	private int $site2 = 0;

	/**
	 * Network administrator.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Site address before the test.
	 *
	 * @var string
	 */
	private string $home = '';

	/**
	 * Journal directory.
	 *
	 * @var string
	 */
	private string $journal;

	/**
	 * Both sites installed.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->home  = (string) get_option( 'home' );
		$this->admin = (int) self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin-ms-' . strtolower( wp_generate_password( 8, false ) ) . '@example.org',
			)
		);
		grant_super_admin( $this->admin );
		$this->journal = get_temp_dir() . 'ccap-r123-ms-' . wp_generate_password( 8, false, false );
		wp_mkdir_p( $this->journal );
		SiteIdentity::with_journal_dir( $this->journal );
		Plugin::activate( false );
		$this->site2 = (int) self::factory()->blog->create();
		$this->on_site2(
			static function (): void {
				self::reset_schema();
				Plugin::activate( false );
			}
		);
	}

	/**
	 * Remove site 2 and its tables (the DDL committed the site).
	 */
	public function tear_down(): void {
		// DDL commits: put the address back for the following tests.
		update_option( 'home', $this->home );
		if ( $this->site2 > 0 ) {
			$this->on_site2(
				static function (): void {
					SiteIdentity::delete_journal();
					self::reset_schema();
				}
			);
			wp_delete_site( $this->site2 );
			$GLOBALS['wpdb']->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		SiteIdentity::delete_journal();
		SiteIdentity::with_journal_dir( null );
		foreach ( (array) glob( $this->journal . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		if ( is_dir( $this->journal ) ) {
			rmdir( $this->journal ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
		}
		revoke_super_admin( $this->admin );
		parent::tear_down();
	}

	/**
	 * Run a callback on site 2.
	 *
	 * @param callable $callback Callback.
	 * @return mixed
	 */
	private function on_site2( callable $callback ) {
		switch_to_blog( $this->site2 );
		try {
			return $callback();
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Own tables of the current site.
	 *
	 * @return int
	 */
	private static function own_table_count(): int {
		return count( array_intersect_key( self::inspector()->tables(), array_flip( Schema::table_names() ) ) );
	}

	/**
	 * REC-024: a restore of one site holds only that site; identity, epoch
	 * journal and confirmation are per site (MS-021 "= REC-024": site 1
	 * unchanged, site identity per site).
	 *
	 * @group REC-024
	 * @group MS-021
	 */
	public function test_REC_024_restore_of_one_site_leaves_the_other_untouched(): void {
		SiteIdentity::check();
		SiteIdentity::bump_epoch();
		$main_file = SiteIdentity::journal_file();
		$site2     = $this->on_site2(
			static function (): array {
				SiteIdentity::check();
				$backup = get_option( SiteIdentity::EPOCH_OPTION );
				SiteIdentity::bump_epoch();
				SiteIdentity::bump_epoch();
				return array(
					'file'   => SiteIdentity::journal_file(),
					'backup' => $backup,
				);
			}
		);
		self::assertNotSame( $main_file, $site2['file'], 'one journal head per site' );
		self::assertSame( 1, SiteIdentity::journal_epoch() );

		// Restore site 2 from before its last mails.
		$this->on_site2(
			static function () use ( $site2 ): void {
				false === $site2['backup'] ? delete_option( SiteIdentity::EPOCH_OPTION ) : update_option( SiteIdentity::EPOCH_OPTION, $site2['backup'] );
				self::assertTrue( SiteIdentity::is_held() );
			}
		);
		self::assertFalse( SiteIdentity::is_held(), 'main site not affected' );
		self::assertSame( SiteIdentity::STATUS_OK, get_option( SiteIdentity::OPTION )['status'] );

		$admin = $this->admin;
		$this->on_site2(
			static function () use ( $admin ): void {
				SiteIdentity::confirm( Actor::user( $admin ), SiteIdentity::CHOICE_SAME_PLACE );
				self::assertFalse( SiteIdentity::is_held() );
				self::assertSame( 2, SiteIdentity::db_epoch() );
				$db = Database::current();
				self::assertSame( '1', $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'site_identity_confirmed'" ) );
			}
		);
		$db = Database::current();
		self::assertSame( '0', $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'site_identity_confirmed'" ) );

		// A changed address on the main site does not hold site 2.
		update_option( 'home', 'https://main-moved-r123.example.org' );
		self::assertTrue( SiteIdentity::is_held() );
		$this->on_site2( static fn() => self::assertFalse( SiteIdentity::is_held() ) );
	}

	/**
	 * REC-019 / MS-015 (full): only the agreeing site loses its data (tables,
	 * options, user options); network user meta stays while one site keeps
	 * its data and goes once every site agreed.
	 *
	 * @group REC-019
	 * @group MS-015
	 * @group GM-186
	 */
	public function test_REC_019_multisite_deletes_only_on_agreeing_sites(): void {
		$user = (int) self::factory()->user->create();
		update_user_option( $user, 'course_captain_notification_prefs', array( 'course_completed' => false ) );
		update_user_meta( $user, 'ccap_certificate_name_public', 'Name' );
		$this->on_site2(
			static function () use ( $user ): void {
				update_user_option( $user, 'course_captain_notification_prefs', array( 'course_completed' => false ) );
			}
		);
		update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( UninstallManifest::DELETE_DATA_KEY => true ) ) );

		$result = Uninstaller::run();

		self::assertTrue( $result[ get_main_site_id() ] );
		self::assertFalse( $result[ $this->site2 ] );
		self::assertSame( 0, self::own_table_count() );
		self::assertFalse( get_user_option( 'course_captain_notification_prefs', $user ) );
		self::assertSame( 'Name', get_user_meta( $user, 'ccap_certificate_name_public', true ), 'site 2 keeps data: network meta stays' );
		$this->on_site2(
			static function () use ( $user ): void {
				self::assertSame( count( Schema::table_names() ), self::own_table_count() );
				self::assertNotFalse( get_user_option( 'course_captain_notification_prefs', $user ) );
				self::assertNotFalse( get_option( 'course_captain_db_version' ) );
				update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( UninstallManifest::DELETE_DATA_KEY => true ) ) );
			}
		);
		// Second uninstall attempt (e.g. after reinstall): both sites agree now.
		update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( UninstallManifest::DELETE_DATA_KEY => true ) ) );

		$result = Uninstaller::run();

		self::assertTrue( $result[ $this->site2 ] );
		self::assertSame( '', get_user_meta( $user, 'ccap_certificate_name_public', true ), 'every site agreed: network meta gone' );
		$this->on_site2(
			static function () use ( $user ): void {
				self::assertSame( 0, self::own_table_count() );
				self::assertFalse( get_user_option( 'course_captain_notification_prefs', $user ) );
			}
		);
	}
}
