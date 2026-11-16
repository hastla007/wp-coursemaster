<?php
/**
 * R1-36: admin settings regression and migration (R0-27 §4.1 SET-xxx,
 * R0-31 §4 EX-/IM-/RS-, PSD-057…064).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Settings\SettingsTransfer;
use CourseCaptain\Storage\Database;

/**
 * Regression over all tabs.
 *
 * @group R1-36
 */
final class SettingsRegressionTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			delete_option( Settings::OPTION_PREFIX . $tab );
			delete_option( Settings::UNDO_PREFIX . $tab );
		}
		delete_transient( SettingsTransfer::RATE_PREFIX . $this->admin );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Admin actor.
	 *
	 * @return Actor
	 */
	private function actor(): Actor {
		return Actor::user( $this->admin );
	}

	/**
	 * Audit rows of an action.
	 *
	 * @param string $action Action.
	 * @return list<array<string, mixed>>
	 */
	private function audits( string $action ): array {
		$db = Database::current();
		return array_map(
			static fn( array $row ): array => (array) json_decode( (string) $row['context'], true ),
			$db->rows( $db->prepare( 'SELECT context FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s ORDER BY id', $action ) )
		);
	}

	/**
	 * SET-001: every default passes its own validator; every field has a UI contract.
	 *
	 * @group SET-001
	 */
	public function test_SET_001_schema(): void {
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			foreach ( $fields as $field => $def ) {
				foreach ( array( 'type', 'default', 'cap', 'privacy', 'side_effects', 'export' ) as $key ) {
					self::assertArrayHasKey( $key, $def, "$tab.$field.$key" );
				}
				$check = SettingsSchema::validate( $def, $def['default'] );
				self::assertTrue( $check['ok'], "$tab.$field default valid" );
				self::assertSame( $def['default'], $check['value'], "$tab.$field default normalized" );
			}
		}
	}

	/**
	 * SET-002 / SET-003 / SET-005: partial save, checkbox off, foreign keys.
	 *
	 * @group SET-002
	 * @group SET-003
	 * @group SET-005
	 */
	public function test_SET_002_005_partial_save(): void {
		Settings::save( $this->actor(), 'learning', array( 'mistake_training_enabled' => false ), array( 'mistake_training_enabled' ), 0 );
		$general = get_option( Settings::OPTION_PREFIX . 'general' );
		$out     = SettingsScreen::process_save(
			array(
				'tab'      => 'learning',
				'_wpnonce' => wp_create_nonce( 'ccap_settings_save_learning' ),
				'_rev'     => Settings::rev( 'learning' ),
				'_fields'  => array( 'favorites_enabled', 'learning_area_slug', 'unknown' ),
				'ccap'     => array(
					'favorites_enabled'  => '0',
					'learning_area_slug' => 'akademie',
					'unknown'            => 'x',
				),
			),
			$this->admin
		);
		self::assertSame( 'saved', $out['result'] );
		self::assertFalse( Settings::get( 'learning.favorites_enabled' ), 'checkbox off stored as false' );
		self::assertFalse( Settings::get( 'learning.mistake_training_enabled' ), 'missing field unchanged' );
		self::assertSame( $general, get_option( Settings::OPTION_PREFIX . 'general' ), 'other tab untouched' );
		$stored = get_option( Settings::OPTION_PREFIX . 'learning' )['values'];
		self::assertArrayNotHasKey( 'unknown', $stored );
		self::assertArrayNotHasKey( 'learning_area_slug', $stored );
	}

	/**
	 * SET-004 / SET-008 / SET-013: invalid input, conflict, manipulated option.
	 *
	 * @group SET-004
	 * @group SET-008
	 * @group SET-013
	 */
	public function test_SET_004_008_013_invalid_conflict_broken(): void {
		$cases = array(
			array( 'enrollment', 'invite_valid_days', 0 ),
			array( 'enrollment', 'invite_valid_days', '366' ),
			array( 'exams', 'timer_mode', 'relaxed' ),
			array( 'display', 'card_elements', array( 'cover', 'rating' ) ),
			array( 'emails', 'from_address', 'not-an-address' ),
			array( 'general', 'learning_area_title', str_repeat( 'x', 61 ) ),
			array( 'privacy', 'trusted_proxies', array( '::/0' ) ),
			array( 'advanced', 'cron_stale_warning_minutes', 59 ),
			array( 'general', 'course_slug', 'Kurs' ),
		);
		foreach ( $cases as $case ) {
			list( $tab, $field, $value ) = $case;
			$before                      = get_option( Settings::OPTION_PREFIX . $tab );
			try {
				Settings::save( $this->actor(), $tab, array( $field => $value ), array( $field ), Settings::rev( $tab ) );
				self::fail( "$tab.$field accepted an invalid value" );
			} catch ( CourseException $e ) {
				self::assertSame( 'ccap_validation_failed', $e->error_code );
				self::assertArrayHasKey( $field, $e->data['fields'] );
			}
			self::assertSame( $before, get_option( Settings::OPTION_PREFIX . $tab ) );
		}

		$rev = Settings::rev( 'exams' );
		Settings::save( $this->actor(), 'exams', array( 'cooldown_seconds' => 60 ), array( 'cooldown_seconds' ), $rev );
		try {
			Settings::save( $this->actor(), 'exams', array( 'cooldown_seconds' => 120 ), array( 'cooldown_seconds' ), $rev );
			self::fail( 'conflict expected' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_settings_conflict', $e->error_code );
		}
		self::assertSame( 60, Settings::get( 'exams.cooldown_seconds' ) );

		// SET-013: broken option reads defaults field by field; foreign garbage is rejected.
		add_option( Settings::OPTION_PREFIX . 'display', 'garbage', '', true );
		self::assertSame( 'theme', Settings::get( 'display.container_width' ) );
		$raw = array(
			'_schema' => 1,
			'_rev'    => 3,
			'values'  => array(
				'container_width' => 'wide',
				'breadcrumbs'     => 'yes',
			),
		);
		update_option( Settings::OPTION_PREFIX . 'display', $raw );
		self::assertSame( 'garbage', get_option( Settings::OPTION_PREFIX . 'display' ), 'S-9 rejects an invalid foreign write' );
	}

	/**
	 * SET-009: autoload only for §2.1 "ja" tabs; ≤ 4 KB with maximal values; migration fixes wrong flags.
	 *
	 * @group SET-009
	 */
	public function test_SET_009_autoload_budget(): void {
		$max = array(
			'general'    => array(
				'learning_area_slug'  => str_repeat( 'a', 50 ),
				'course_slug'         => str_repeat( 'b', 50 ),
				'learning_area_title' => str_repeat( 'x', 60 ),
				'catalog_page_id'     => 0,
			),
			'display'    => array( 'card_elements' => array( 'cover', 'summary', 'lesson_count', 'progress' ) ),
			'learning'   => array( 'course_completion_default' => 'required_modules_and_exam' ),
			'enrollment' => array(
				'default_access_days'    => 3650,
				'source_disabled_policy' => 'keep_and_flag',
			),
			'advanced'   => array( 'cron_stale_warning_minutes' => 1440 ),
			'hardening'  => array(
				'login_limit'        => 'force',
				'reset_limit'        => 'force',
				'registration_limit' => 'force',
				'xmlrpc'             => 'force',
				'site_headers'       => 'force',
			),
		);
		foreach ( $max as $tab => $values ) {
			Settings::save( Actor::cli(), $tab, $values, array_keys( $values ), Settings::rev( $tab ) );
		}
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			Settings::save( Actor::cli(), $tab, array(), array(), Settings::rev( $tab ) );
		}
		global $wpdb;
		$rows  = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE 'course\_captain\_settings\_%'", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$bytes = 0;
		foreach ( $rows as $row ) {
			$tab      = substr( (string) $row['option_name'], strlen( Settings::OPTION_PREFIX ) );
			$autoload = in_array( $row['autoload'], array( 'yes', 'on', 'auto-on', 'auto' ), true );
			if ( isset( SettingsSchema::TABS[ $tab ] ) ) {
				self::assertSame( SettingsSchema::TABS[ $tab ], $autoload, "autoload flag of $tab" );
			}
			if ( $autoload ) {
				$bytes += strlen( (string) $row['option_value'] );
			}
		}
		self::assertLessThanOrEqual( 4096, $bytes, 'autoloaded settings ≤ 4 KB' );
		self::assertLessThanOrEqual( 512, strlen( (string) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'course_captain_settings_hardening'" ) ), 'ADM-062' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// Wrong flag of an older installation is corrected by the migration.
		wp_set_option_autoload_values( array( Settings::OPTION_PREFIX . 'exams' => true ) );
		Settings::migrate();
		$flag = (string) $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Settings::OPTION_PREFIX . 'exams' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::assertContains( $flag, array( 'no', 'off', 'auto-off' ) );
	}

	/**
	 * SET-011 / SET-012: default change and downgrade passthrough.
	 *
	 * @group SET-011
	 * @group SET-012
	 */
	public function test_SET_011_012_defaults_and_downgrade(): void {
		// First save materializes all defaults, so a later default change never acts silently.
		Settings::save( $this->actor(), 'exams', array( 'cooldown_seconds' => 5 ), array( 'cooldown_seconds' ), 0 );
		$stored = get_option( Settings::OPTION_PREFIX . 'exams' )['values'];
		self::assertSame( array_keys( SettingsSchema::fields()['exams'] ), array_keys( $stored ) );
		self::assertSame( 120, $stored['late_tolerance_seconds'] );

		add_option(
			Settings::OPTION_PREFIX . 'learning',
			array(
				'_schema' => SettingsSchema::VERSION + 1,
				'_rev'    => 4,
				'values'  => array(
					'favorites_enabled' => true,
					'future_field'      => array( 'x' => 1 ),
				),
			),
			'',
			true
		);
		Settings::save( $this->actor(), 'learning', array( 'favorites_enabled' => false ), array( 'favorites_enabled' ), 4 );
		$raw = get_option( Settings::OPTION_PREFIX . 'learning' );
		self::assertSame( array( 'x' => 1 ), $raw['values']['future_field'], 'E-90 passthrough' );
		self::assertSame( SettingsSchema::VERSION + 1, $raw['_schema'] );
		Settings::reset( $this->actor(), 'learning' );
		self::assertSame( array( 'x' => 1 ), get_option( Settings::OPTION_PREFIX . 'learning' )['values']['future_field'], 'reset keeps newer keys' );
		self::assertSame( array(), Settings::migrate(), 'no back-migration of a newer schema' );
	}

	/**
	 * SET-014 / RS-2: reset per tab only; other tabs and data untouched; no global reset.
	 *
	 * @group SET-014
	 * @group PSD-060
	 */
	public function test_SET_014_reset_scope(): void {
		Settings::save( $this->actor(), 'exams', array( 'shuffle_answers' => false ), array( 'shuffle_answers' ), 0 );
		Settings::save( $this->actor(), 'privacy', array( 'backup_max_age_days' => 30 ), array( 'backup_max_age_days' ), 0 );
		$privacy = get_option( Settings::OPTION_PREFIX . 'privacy' );
		$changed = Settings::reset( $this->actor(), 'exams' );
		self::assertSame( array( 'shuffle_answers' ), $changed );
		self::assertSame( $privacy, get_option( Settings::OPTION_PREFIX . 'privacy' ) );
		self::assertFalse( method_exists( Settings::class, 'reset_all' ), 'INV-S-14' );
		self::assertTrue( Settings::undo( $this->actor(), 'exams' ) );
		self::assertFalse( Settings::get( 'exams.shuffle_answers' ) );
		$audit = $this->audits( 'settings_reset' );
		self::assertSame( 'exams', end( $audit )['tab'] );
	}

	/**
	 * PSD-057 / PSD-064 / EX-1…EX-5 / SET-015: export.
	 *
	 * @group PSD-057
	 * @group PSD-064
	 * @group SET-015
	 */
	public function test_PSD_057_064_export(): void {
		Settings::save( $this->actor(), 'emails', array( 'from_name' => 'Akademie' ), array( 'from_name' ), 0 );
		Settings::save( $this->actor(), 'privacy', array( 'trusted_proxies' => array( '10.0.0.1' ) ), array( 'trusted_proxies' ), 0 );
		Settings::save( $this->actor(), 'advanced', array( 'file_delivery' => 'x_sendfile' ), array( 'file_delivery' ), 0 );
		Settings::save( $this->actor(), 'hardening', array( 'login_limit' => 'auto' ), array( 'login_limit' ), 0 );
		$export = Settings::export( $this->actor() );
		self::assertArrayNotHasKey( 'from_name', $export['tabs']['emails']['values'], 'EX-1 personal only with opt-in' );
		self::assertArrayNotHasKey( 'trusted_proxies', $export['tabs']['privacy']['values'], 'EX-2' );
		self::assertArrayNotHasKey( 'file_delivery', $export['tabs']['advanced']['values'], 'EX-2' );
		self::assertArrayNotHasKey( 'delete_data_on_uninstall', $export['tabs']['general']['values'], 'EX-2' );
		self::assertArrayNotHasKey( 'roles', $export['tabs'] );
		self::assertSame( 'auto', $export['tabs']['hardening']['values']['login_limit'], 'EX-3 mode only' );
		self::assertSame( 'Akademie', Settings::export( $this->actor(), true )['tabs']['emails']['values']['from_name'] );
		$head = array_diff_key( $export, array( 'tabs' => true ) );
		self::assertSame( array( 'format', 'format_version', 'plugin_version', 'settings_version', 'exported_at', 'site_hash' ), array_keys( $head ) );
		$user = get_userdata( $this->admin );
		$json = (string) wp_json_encode( $head );
		self::assertStringNotContainsString( (string) wp_parse_url( home_url(), PHP_URL_HOST ), $json, 'EX-5 no URL' );
		self::assertStringNotContainsString( $user->user_login, $json );
		self::assertStringNotContainsString( $user->user_email, $json );
		self::assertStringNotContainsString( 'undo', (string) wp_json_encode( $export ), 'RS-4' );
	}

	/**
	 * SET-016 / PSD-058 / PSD-059 / IM-1…IM-5: import.
	 *
	 * @group SET-016
	 * @group PSD-058
	 * @group PSD-059
	 */
	public function test_SET_016_PSD_058_059_import(): void {
		$file              = Settings::export( $this->actor() );
		$file['site_hash'] = hash( 'sha256', 'https://elsewhere.example' );
		$file['tabs']['privacy']['values']['auto_erase_on_confirm']    = true;
		$file['tabs']['privacy']['values']['show_avatars']             = true;
		$file['tabs']['privacy']['values']['backup_max_age_days']      = 120;
		$file['tabs']['privacy']['values']['trusted_proxies']          = array( '10.0.0.0/8' );
		$file['tabs']['general']['values']['delete_data_on_uninstall'] = true;
		$file['tabs']['advanced']['values']['file_delivery']           = 'x_accel_redirect';
		$file['tabs']['emails']['values']['from_address']              = '<gesetzt>';
		$file['tabs']['hardening']['values']['login_limit']            = 'auto';
		$file['tabs']['exams']['values']['shuffle_answers']            = false;
		$data = SettingsTransfer::parse( (string) wp_json_encode( $file ) );

		$preview = SettingsTransfer::import( $this->actor(), $data );
		self::assertContains( 'other_site', $preview['notices'], 'IM-4' );
		self::assertSame( array(), $preview['applied'], 'preview writes nothing' );
		self::assertTrue( Settings::get( 'exams.shuffle_answers' ) );
		$skipped = array_column( $preview['tabs']['privacy']['skipped'], 'reason', 'field' );
		self::assertSame( 'confirmation', $skipped['auto_erase_on_confirm'] );
		self::assertSame( 'confirmation', $skipped['show_avatars'] );
		self::assertSame( 'environment', $skipped['trusted_proxies'], 'IM-3' );
		self::assertSame( 'environment', array_column( $preview['tabs']['general']['skipped'], 'reason', 'field' )['delete_data_on_uninstall'] );
		self::assertSame( 'environment', array_column( $preview['tabs']['advanced']['skipped'], 'reason', 'field' )['file_delivery'] );
		self::assertSame( 'secret', array_column( $preview['tabs']['emails']['skipped'], 'reason', 'field' )['from_address'], 'SH-6' );
		self::assertSame( 'confirmation', array_column( $preview['tabs']['hardening']['skipped'], 'reason', 'field' )['login_limit'] );

		$report = SettingsTransfer::import(
			$this->actor(),
			$data,
			array(
				'apply'     => true,
				'confirmed' => array( 'privacy.show_avatars' ),
			)
		);
		self::assertContains( 'privacy', $report['applied'] );
		self::assertTrue( Settings::get( 'privacy.show_avatars' ), 'confirmed risk field applied' );
		self::assertFalse( Settings::get( 'privacy.auto_erase_on_confirm' ), 'unconfirmed risk field skipped' );
		self::assertSame( 120, Settings::get( 'privacy.backup_max_age_days' ) );
		self::assertSame( array(), Settings::get( 'privacy.trusted_proxies' ) );
		self::assertFalse( Settings::get( 'general.delete_data_on_uninstall' ) );
		self::assertSame( 'php', Settings::get( 'advanced.file_delivery' ) );
		self::assertFalse( Settings::get( 'exams.shuffle_answers' ) );
		self::assertGreaterThan( 0, Settings::undo_until( 'exams' ), 'IM-5 undo snapshot' );
		self::assertTrue( Settings::undo( $this->actor(), 'exams' ) );
		self::assertTrue( Settings::get( 'exams.shuffle_answers' ) );
		$audit = $this->audits( 'settings_imported' );
		self::assertNotEmpty( $audit );
		self::assertContains( 'privacy', end( $audit )['tabs'] );

		// Rejections.
		foreach ( array( '{"format":"other"}', 'not json', str_repeat( ' ', SettingsTransfer::MAX_BYTES + 1 ) ) as $raw ) {
			try {
				SettingsTransfer::parse( $raw );
				self::fail( 'format must be rejected' );
			} catch ( CourseException $e ) {
				self::assertSame( 'ccap_settings_import_invalid', $e->error_code );
			}
		}
		$newer                     = $file;
		$newer['settings_version'] = SettingsSchema::VERSION + 1;
		try {
			SettingsTransfer::parse( (string) wp_json_encode( $newer ) );
			self::fail( 'newer version must be rejected' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_settings_version_newer', $e->error_code );
		}

		// Rights and rate limit.
		$manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		try {
			SettingsTransfer::import( Actor::user( $manager ), $data );
			self::fail( 'import needs #19' );
		} catch ( CourseException $e ) {
			self::assertSame( 403, $e->status );
		}
		for ( $i = 0; $i < 9; $i++ ) {
			SettingsTransfer::import( $this->actor(), $data, array( 'apply' => true ) );
		}
		try {
			SettingsTransfer::import( $this->actor(), $data, array( 'apply' => true ) );
			self::fail( '11th import in an hour' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_rate_limited', $e->error_code );
		}
	}

	/**
	 * Import screen: preview stores the file, apply needs the same nonce and confirmations.
	 *
	 * @group SET-016
	 */
	public function test_SET_016_import_screen(): void {
		$file = Settings::export( $this->actor() );
		$file['tabs']['exams']['values']['shuffle_answers'] = false;
		$file['tabs']['privacy']['values']['csp_mode']      = 'report_only';
		$nonce = array( '_wpnonce' => wp_create_nonce( \CourseCaptain\Admin\SettingsBackup::IMPORT ) );
		self::assertSame( 'ccap_invalid_nonce', \CourseCaptain\Admin\SettingsBackup::process( array(), '{}', $this->admin ) );
		self::assertSame( 'ccap_settings_import_invalid', \CourseCaptain\Admin\SettingsBackup::process( $nonce, 'x', $this->admin ) );
		self::assertSame( 'preview', \CourseCaptain\Admin\SettingsBackup::process( $nonce, (string) wp_json_encode( $file ), $this->admin ) );
		self::assertTrue( Settings::get( 'exams.shuffle_answers' ), 'preview writes nothing' );
		$html = \CourseCaptain\Admin\SettingsBackup::html( array( 'ccap_result' => 'preview' ), $this->admin );
		self::assertStringContainsString( 'name="confirmed[]" value="privacy.csp_mode"', $html );
		self::assertStringContainsString( 'shuffle_answers: true', $html );
		$out = \CourseCaptain\Admin\SettingsBackup::process( $nonce + array( 'apply' => '1', 'tabs' => array( 'exams', 'privacy' ) ), '', $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short args.
		self::assertSame( 'imported', $out );
		self::assertFalse( Settings::get( 'exams.shuffle_answers' ) );
		self::assertSame( 'enforce', Settings::get( 'privacy.csp_mode' ), 'risk field not confirmed' );
		self::assertSame( 'ccap_confirmation_expired', \CourseCaptain\Admin\SettingsBackup::process( $nonce + array( 'apply' => '1' ), '', $this->admin ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short args.
		$subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::assertSame( '', \CourseCaptain\Admin\SettingsBackup::html( array(), $subscriber ) );
	}

	/**
	 * SET-023: WordPress values are inherited, not duplicated.
	 *
	 * @group SET-023
	 */
	public function test_SET_023_wordpress_inheritance(): void {
		$fields = array();
		foreach ( SettingsSchema::fields() as $tab_fields ) {
			$fields = array_merge( $fields, array_keys( $tab_fields ) );
		}
		foreach ( array( 'locale', 'timezone_string', 'gmt_offset', 'date_format', 'time_format', 'admin_email', 'blogname', 'users_can_register', 'default_role' ) as $wp ) {
			self::assertNotContains( $wp, $fields, 'no duplicate of WP value ' . $wp );
		}
		self::assertSame( '', Settings::get( 'emails.from_name' ), 'empty = inherit WordPress' );
		self::assertNull( Settings::get( 'general.use_profile_locale' ), 'null = automatic' );
		$html = SettingsScreen::html( array( 'tab' => 'emails' ), $this->admin );
		self::assertStringContainsString( 'Leave empty to use the WordPress default.', $html );
	}

	/**
	 * SET-018: settings and setup state per site, never network options.
	 *
	 * @group SET-018
	 * @group ms-required
	 */
	public function test_SET_018_multisite_boundaries(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'multisite only' );
		}
		Settings::save( Actor::cli(), 'exams', array( 'cooldown_seconds' => 77 ), array( 'cooldown_seconds' ), Settings::rev( 'exams' ) );
		$blog = (int) self::factory()->blog->create();
		switch_to_blog( $blog );
		try {
			Plugin::activate( false );
			self::assertSame( 0, Settings::get( 'exams.cooldown_seconds' ), 'other site keeps its own defaults' );
			Settings::save( Actor::cli(), 'exams', array( 'cooldown_seconds' => 5 ), array( 'cooldown_seconds' ), Settings::rev( 'exams' ) );
			self::assertFalse( get_option( 'course_captain_setup_state' ) );
		} finally {
			restore_current_blog();
		}
		self::assertSame( 77, Settings::get( 'exams.cooldown_seconds' ) );
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			self::assertFalse( get_site_option( Settings::OPTION_PREFIX . $tab ), 'no network option ' . $tab );
		}
	}

	/**
	 * SET-020 / RS-4: every settings option and undo slot is in the uninstall manifest.
	 *
	 * @group SET-020
	 * @group PSD-061
	 */
	public function test_SET_020_manifest(): void {
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			self::assertContains( Settings::OPTION_PREFIX . $tab, UninstallManifest::OPTIONS );
			self::assertContains( $tab, UninstallManifest::DYNAMIC_OPTION_SUFFIXES[ Settings::UNDO_PREFIX ] );
		}
		self::assertContains( Settings::VERSION_OPTION, UninstallManifest::OPTIONS );
		self::assertContains( 'course_captain_setup_state', UninstallManifest::OPTIONS );
		self::assertContains( 'roles', UninstallManifest::DYNAMIC_OPTION_SUFFIXES[ Settings::UNDO_PREFIX ] );
	}
}
