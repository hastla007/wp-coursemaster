<?php
/**
 * R1-23: full uninstall probes with real tables (R0-16 §2.5, E-89; REC-019, LIC-063, SET-035).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Schema;

/**
 * Every manifest category is filled with real data, next to foreign data
 * with confusingly similar names. Without the confirmed setting nothing but
 * cron, transients and rewrite rules goes; with it exactly the manifest goes.
 *
 * @group R1-23
 */
final class UninstallProbeTest extends SchemaTestCase {

	/**
	 * Foreign options that must survive.
	 *
	 * @var array<string, string>
	 */
	private const FOREIGN_OPTIONS = array(
		'course_captain_pro_license' => 'add-on',
		'course_captain_foreign'     => 'unknown owner',
		'ccap_foreign_option'        => 'unknown owner',
	);

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Own course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Foreign post with a foreign meta key.
	 *
	 * @var int
	 */
	private int $foreign_post;

	/**
	 * Journal directory.
	 *
	 * @var string
	 */
	private string $journal;

	/**
	 * Fixture with data in every manifest category.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$tag            = strtolower( wp_generate_password( 8, false ) );
		$this->admin    = (int) self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'admin-uni-' . $tag . '@example.org',
			)
		);
		$this->learner  = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'learner-uni-' . $tag . '@example.org',
			)
		);
		$this->blogname = (string) get_option( 'blogname' );
		update_option( 'blogname', 'Akademie Nord' );
		PolicyService::flush();
		$this->journal = get_temp_dir() . 'ccap-r123-uni-' . wp_generate_password( 8, false, false );
		wp_mkdir_p( $this->journal );
		SiteIdentity::with_journal_dir( $this->journal );

		$this->course = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Course X',
				'post_author' => $this->admin,
			)
		);
		wp_update_post(
			array(
				'ID'           => $this->course,
				'post_content' => 'revision',
			)
		);
		update_post_meta( $this->course, '_ccap_listing', 'catalog' );
		$this->foreign_post = (int) wp_insert_post(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'Foreign',
			)
		);
		update_post_meta( $this->foreign_post, '_ccap_foreign_meta', 'stays' );
		update_post_meta( $this->foreign_post, '_ccap_seo_index', 'own key on a foreign post' );

		GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->learner, $this->course, wp_generate_uuid4(), 'x' );
		update_user_option( $this->learner, 'course_captain_notification_prefs', array( 'course_completed' => false ) );
		update_user_meta( $this->learner, 'ccap_notice_state', array( 'x' => 1 ) );
		update_user_meta( $this->learner, 'ccap_foreign_user_meta', 'stays' );
		update_option( 'course_captain_mail_template_test_mail', array( 'en_US' => array() ) );
		SiteIdentity::bump_epoch();
		foreach ( self::FOREIGN_OPTIONS as $name => $value ) {
			update_option( $name, $value );
		}
		$db = Database::current();
		$db->execute( 'CREATE TABLE IF NOT EXISTS `' . $db->table( 'ccap_foreign_probe' ) . "` (id int) ENGINE=InnoDB COMMENT='someone else'" );
	}

	/**
	 * Site name before the test.
	 *
	 * @var string
	 */
	private string $blogname = '';

	/**
	 * Cleanup (the DDL in set_up committed the fixture).
	 */
	public function tear_down(): void {
		update_option( 'blogname', $this->blogname );
		wp_delete_post( $this->foreign_post, true );
		delete_user_meta( $this->learner, 'ccap_foreign_user_meta' );
		delete_user_meta( $this->learner, 'ccap_notice_state' );
		SiteIdentity::delete_journal();
		SiteIdentity::with_journal_dir( null );
		if ( is_dir( $this->journal ) ) {
			rmdir( $this->journal ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
		}
		parent::tear_down();
	}

	/**
	 * Foreign data is untouched.
	 */
	private function assert_foreign_data_intact(): void {
		foreach ( self::FOREIGN_OPTIONS as $name => $value ) {
			self::assertSame( $value, get_option( $name ), $name );
		}
		self::assertSame( 'stays', get_post_meta( $this->foreign_post, '_ccap_foreign_meta', true ) );
		self::assertSame( 'stays', get_user_meta( $this->learner, 'ccap_foreign_user_meta', true ) );
		self::assertArrayHasKey( 'ccap_foreign_probe', self::inspector()->tables(), 'table without ownership marker stays' );
		self::assertTrue( get_role( 'administrator' )?->has_cap( 'manage_options' ) ?? false );
		self::assertNotNull( get_userdata( $this->learner ), 'accounts are never deleted' );
	}

	/**
	 * REC-019 (without confirmation) / REC-018: nothing of the learning data goes.
	 *
	 * @group REC-019
	 * @group REC-018
	 */
	public function test_REC_019_without_confirmation_no_user_data_is_removed(): void {
		$tables = self::inspector()->tables();

		self::assertSame( array( get_current_blog_id() => false ), Uninstaller::run() );

		self::assertSame( $tables, self::inspector()->tables() );
		self::assertNotNull( get_post( $this->course ) );
		self::assertSame( 'catalog', get_post_meta( $this->course, '_ccap_listing', true ) );
		self::assertNotFalse( get_user_option( 'course_captain_notification_prefs', $this->learner ) );
		self::assertNotSame( '', get_user_meta( $this->learner, 'ccap_notice_state', true ) );
		self::assertNotFalse( get_option( SiteIdentity::OPTION ) );
		self::assertNotNull( SiteIdentity::journal_epoch(), 'journal head stays' );
		self::assertNotSame( array(), self::ccap_caps_of( 'administrator' ) );
		self::assertSame( array(), self::own_cron_events() );
		$this->assert_foreign_data_intact();
	}

	/**
	 * REC-019 / LIC-063: with the confirmed setting every manifest entry is
	 * gone (tables, options, post types with revisions, post meta, user
	 * options, user meta, roles, caps, cron, journal head), nothing foreign.
	 *
	 * @group REC-019
	 * @group LIC-063
	 * @group GM-184
	 */
	public function test_REC_019_confirmed_deletion_removes_exactly_the_manifest(): void {
		$revisions = wp_get_post_revisions( $this->course );
		self::assertNotSame( array(), $revisions );
		Settings::save( Actor::user( $this->admin ), 'general', array( 'delete_data_on_uninstall' => true, Settings::CONFIRM_WORD_FIELD => 'LÖSCHEN' ), array( 'delete_data_on_uninstall' ), Settings::rev( 'general' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

		self::assertSame( array( get_current_blog_id() => true ), Uninstaller::run() );

		$tables = self::inspector()->tables();
		foreach ( UninstallManifest::tables() as $table ) {
			self::assertArrayNotHasKey( $table, $tables, $table );
		}
		$remaining = array_diff( self::own_options(), array_keys( self::FOREIGN_OPTIONS ) );
		self::assertSame( array(), array_values( $remaining ), 'only foreign options remain' );
		foreach ( UninstallManifest::post_types() as $type ) {
			self::assertSame( 0, (int) Database::current()->scalar( Database::current()->prepare( 'SELECT COUNT(*) FROM `' . Database::current()->wpdb()->posts . '` WHERE post_type = %s', $type ) ), $type );
		}
		foreach ( $revisions as $revision ) {
			self::assertNull( get_post( $revision->ID ), 'revisions go with the course' );
		}
		self::assertSame( '', get_post_meta( $this->foreign_post, '_ccap_seo_index', true ), 'own meta key also on foreign posts' );
		self::assertFalse( get_user_option( 'course_captain_notification_prefs', $this->learner ) );
		self::assertSame( '', get_user_meta( $this->learner, 'ccap_notice_state', true ), 'network user meta: single site agreed' );
		foreach ( UninstallManifest::roles() as $role ) {
			self::assertNull( get_role( $role ), $role );
		}
		self::assertSame( array(), self::ccap_caps_of( 'administrator' ) );
		self::assertSame( array(), self::own_cron_events() );
		self::assertNull( SiteIdentity::journal_epoch(), 'own journal head removed' );
		self::assertSame( array(), glob( $this->journal . '/*' ), 'nothing else in the journal folder' );
		$this->assert_foreign_data_intact();
	}

	/**
	 * SET-CC-30 / SET-035 (replaces E-89): switching the deletion on needs
	 * the typed word LÖSCHEN/LOESCHEN/DELETE in any letter case, checked on
	 * the server; the site name no longer counts; the audit records the
	 * change; switching off needs nothing.
	 *
	 * @group SET-035
	 * @group SET-CC-30
	 * @group REC-019
	 */
	public function test_SET_CC_30_deletion_setting_needs_typed_word(): void {
		$admin = Actor::user( $this->admin );
		foreach ( array( null, '', 'Akademie Nord', 'LOSCHEN', 'DELET', 'ja', 'LÖSCHEN!' ) as $typed ) {
			$input = array( 'delete_data_on_uninstall' => true );
			if ( null !== $typed ) {
				$input[ Settings::CONFIRM_WORD_FIELD ] = $typed;
			}
			try {
				Settings::save( $admin, 'general', $input, array( 'delete_data_on_uninstall' ), Settings::rev( 'general' ) );
				self::fail( 'expected rejection for ' . var_export( $typed, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			} catch ( CourseException $e ) {
				self::assertSame( 'ccap_validation_failed', $e->error_code );
				self::assertSame( 'delete_data_on_uninstall', $e->getMessage() );
			}
		}
		self::assertFalse( Settings::get( 'general.delete_data_on_uninstall' ) );
		self::assertFalse( Uninstaller::is_data_deletion_confirmed( get_option( UninstallManifest::GENERAL_SETTINGS_OPTION ) ) );

		foreach ( array( 'löschen', 'Loeschen', 'delete', ' DELETE ', "lo\u{0308}schen" ) as $word ) {
			self::assertTrue( Settings::is_confirm_word( $word ), $word );
		}
		$result = Settings::save( $admin, 'general', array( 'delete_data_on_uninstall' => true, Settings::CONFIRM_WORD_FIELD => ' löschen ' ), array( 'delete_data_on_uninstall' ), Settings::rev( 'general' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		self::assertSame( array( 'delete_data_on_uninstall' ), $result['changed'] );
		self::assertTrue( Uninstaller::is_data_deletion_confirmed( get_option( UninstallManifest::GENERAL_SETTINGS_OPTION ) ) );
		$db    = Database::current();
		$audit = $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'settings_saved' AND actor_ref = %s", \CourseCaptain\Event\AuditIdentity::ref_of( $db, $this->admin ) ) );
		self::assertCount( 1, $audit );
		self::assertStringContainsString( 'delete_data_on_uninstall', (string) $audit[0]['context'] );

		// Saving other fields later needs no confirmation; switching off neither.
		Settings::save( $admin, 'general', array( 'learning_area_title' => 'Lernen' ), array( 'learning_area_title' ), Settings::rev( 'general' ) );
		Settings::save( $admin, 'general', array( 'delete_data_on_uninstall' => false ), array( 'delete_data_on_uninstall' ), Settings::rev( 'general' ) );
		self::assertFalse( Settings::get( 'general.delete_data_on_uninstall' ) );
	}

	/**
	 * Reinstall after a confirmed deletion starts from scratch (fresh schema,
	 * fresh identity), without leftovers blocking the activation (KOMP-131).
	 *
	 * @group REC-019
	 */
	public function test_reinstall_after_confirmed_deletion_starts_clean(): void {
		update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( UninstallManifest::DELETE_DATA_KEY => true ) ) );
		Uninstaller::run();

		Plugin::activate( false );

		self::assertCount( count( Schema::table_names() ), array_intersect_key( self::inspector()->tables(), array_flip( Schema::table_names() ) ) );
		self::assertSame( 0, (int) Database::current()->scalar( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_grant' ) . '`' ) );
		self::assertSame( Capabilities::PRIMITIVE, self::ccap_caps_of( 'administrator' ) );
		self::assertSame( 'unrecorded', SiteIdentity::evaluate()['state'] );
		self::assertFalse( SiteIdentity::is_held(), 'a fresh install is never held' );
	}
}
