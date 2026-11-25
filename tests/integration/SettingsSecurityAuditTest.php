<?php
/**
 * SET-CC-P4: rights and nonces of every settings/import action, secrets
 * never in export, audit log or log files, uninstall of the new data.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Admin\AdminUi;
use CourseCaptain\Admin\ImportScreen;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Ops\Diagnostics;
use CourseCaptain\Ops\Log;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsHints;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Settings\SettingsTransfer;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group SET-CC
 * @group SET-CC-P4
 */
final class SettingsSecurityAuditTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Subscriber.
	 *
	 * @var int
	 */
	private int $subscriber;

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin      = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			delete_option( Settings::OPTION_PREFIX . $tab );
			delete_option( Settings::UNDO_PREFIX . $tab );
		}
		add_filter( SettingsHints::FILTER, array( self::class, 'hint' ) );
	}

	/**
	 * Clean up.
	 */
	public function tear_down(): void {
		remove_all_filters( SettingsHints::FILTER );
		remove_all_filters( 'wp_redirect' );
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/**
	 * One hint line for the dismiss action.
	 *
	 * @param array<int, array<string, string>> $hints Hints.
	 * @return array<int, array<string, string>>
	 */
	public static function hint( $hints ): array {
		$hints   = (array) $hints;
		$hints[] = array(
			'id'    => 'webhooks',
			'tab'   => 'advanced',
			'title' => 'Webhooks',
			'text'  => 'Send course events to your own addresses.',
		);
		return $hints;
	}

	/**
	 * Every admin-post action of settings, setup, backup, mail texts, locks,
	 * roles, certificate template, import and audit export: payload and nonce action.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	private static function actions(): array {
		return array(
			'ccap_settings_save'         => array(
				array(
					'tab'     => 'general',
					'_rev'    => 0,
					'_fields' => array( 'learning_area_title' ),
					'ccap'    => array( 'learning_area_title' => 'Changed by attacker' ),
				),
				'ccap_settings_save_general',
			),
			'ccap_settings_reset'        => array( array( 'tab' => 'exams' ), 'ccap_settings_reset_exams' ),
			'ccap_settings_undo'         => array( array( 'tab' => 'exams' ), 'ccap_settings_undo_exams' ),
			'ccap_settings_hint_dismiss' => array(
				array(
					'tab'  => 'advanced',
					'hint' => 'webhooks',
				),
				'ccap_settings_hint_dismiss_webhooks',
			),
			'ccap_settings_export'       => array( array(), 'ccap_settings_export' ),
			'ccap_settings_import'       => array( array( 'apply' => '1' ), 'ccap_settings_import' ),
			'ccap_test_mail'             => array( array(), 'ccap_test_mail' ),
			'ccap_mail_text'             => array(
				array(
					'do'      => 'save',
					'type'    => 'access_granted',
					'locale'  => 'en_US',
					'subject' => 'Changed',
				),
				'ccap_mail_text',
			),
			'ccap_mail_text_export'      => array( array(), 'ccap_mail_text_export' ),
			'ccap_setting_locks'         => array(
				array(
					'op'  => 'lock',
					'key' => 'exams.max_attempts',
				),
				'ccap_setting_locks_lock_exams.max_attempts',
			),
			'ccap_setup_step'            => array(
				array(
					'step'    => 'addresses',
					'_fields' => array( 'learning_area_title' ),
					'ccap'    => array( 'general' => array( 'learning_area_title' => 'Changed' ) ),
				),
				'ccap_setup_step_addresses',
			),
			'ccap_setup_skip'            => array( array( 'step' => 'addresses' ), 'ccap_setup_skip_addresses' ),
			'ccap_setup_complete'        => array( array(), 'ccap_setup_complete' ),
			'ccap_setup_dismiss'         => array( array(), 'ccap_setup_dismiss' ),
			'ccap_roles_reset'           => array( array(), 'ccap_roles_reset' ),
			'ccap_roles_undo'            => array( array(), 'ccap_roles_undo' ),
			'ccap_certificate_template'  => array( array( 'issuer_name' => 'Changed' ), 'ccap_certificate_template' ),
			'ccap_import_admin'          => array(
				array(
					'op'     => 'discard',
					'object' => 1,
				),
				AdminUi::nonce_action( ImportScreen::ACTION, 'discard', 1 ),
			),
			'ccap_audit_export'          => array( array( 'area' => 'settings' ), 'ccap_audit_export' ),
		);
	}

	/**
	 * Fingerprint of everything a settings action could change.
	 *
	 * @return string
	 */
	private static function state(): string {
		global $wpdb;
		$options = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'course\\_captain\\_%' OR option_name = '{$wpdb->prefix}user_roles' ORDER BY option_name", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$meta    = $wpdb->get_results( "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE '%course\\_captain%' OR meta_key LIKE 'ccap\\_%' ORDER BY umeta_id", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$db      = Database::current();
		$audit   = (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . '`' );
		return md5( (string) wp_json_encode( array( $options, $meta, $audit ) ) );
	}

	/**
	 * Run an admin-post action like WordPress does; the redirect or wp_die ends it.
	 *
	 * @param string               $action Action.
	 * @param array<string, mixed> $post   POST.
	 * @return string Output (must not carry data).
	 */
	private static function dispatch( string $action, array $post ): string {
		$_POST    = $post + array( 'action' => $action ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test sets the request.
		$_GET     = $post + array( 'action' => $action ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test sets the request.
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test sets the request.
		// The redirect ends the request before the handler's `exit` (wp_die throws in tests).
		add_filter(
			'wp_redirect',
			static function ( $location ) {
				wp_die( 'redirect: ' . esc_html( (string) $location ) );
			}
		);
		ob_start();
		try {
			do_action( 'admin_post_' . $action ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook of the action under test.
		} catch ( \WPDieException $e ) {
			unset( $e );
		} finally {
			$out = (string) ob_get_clean();
			remove_all_filters( 'wp_redirect' );
		}
		return $out;
	}

	/**
	 * Every settings/import action: registered; without nonce (admin) and
	 * without rights (subscriber with a valid nonce) nothing changes and
	 * nothing is sent; the hint action also works for the admin (control).
	 */
	public function test_SET_CC_P4_admin_post_actions_need_nonce_and_right(): void {
		foreach ( self::actions() as $action => $spec ) {
			list( $post, $nonce ) = $spec;
			self::assertTrue( has_action( 'admin_post_' . $action ), $action . ' registered' );
			self::assertFalse( has_action( 'admin_post_nopriv_' . $action ), $action . ' never for guests' );

			wp_set_current_user( $this->admin );
			$before = self::state();
			$out    = self::dispatch( $action, $post );
			self::assertSame( $before, self::state(), $action . ': admin without nonce changes nothing' );
			self::assertStringNotContainsString( 'course-captain-settings', $out, $action . ': no export without nonce' );

			wp_set_current_user( $this->subscriber );
			$before = self::state();
			$out    = self::dispatch( $action, $post + array( '_wpnonce' => wp_create_nonce( $nonce ) ) );
			self::assertSame( $before, self::state(), $action . ': subscriber with nonce changes nothing' );
			foreach ( array( 'course-captain-settings', '"tabs"', 'settings_saved', 'course-captain-mail-templates' ) as $data ) {
				self::assertStringNotContainsString( $data, $out, $action . ': no data to the subscriber' );
			}
		}

		// Positive control: the same dispatch with nonce and right does change something.
		wp_set_current_user( $this->admin );
		$before = self::state();
		self::dispatch( 'ccap_settings_hint_dismiss', self::actions()['ccap_settings_hint_dismiss'][0] + array( '_wpnonce' => wp_create_nonce( 'ccap_settings_hint_dismiss_webhooks' ) ) );
		self::assertNotSame( $before, self::state(), 'control: valid request is processed' );
		self::assertSame( array( 'webhooks' ), SettingsHints::dismissed( $this->admin ) );
	}

	/**
	 * Import REST routes: guests 401, other people never get a batch.
	 */
	public function test_SET_CC_P4_import_rest_routes(): void {
		$routes = array(
			array( 'GET', '/course-captain/v1/imports/1' ),
			array( 'POST', '/course-captain/v1/imports/1/step' ),
			array( 'POST', '/course-captain/v1/imports/1/cancel' ),
			array( 'POST', '/course-captain/v1/imports/1/resume' ),
			array( 'GET', '/course-captain/v1/imports/1/report' ),
		);
		foreach ( array( 0, $this->subscriber ) as $user ) {
			wp_set_current_user( $user );
			foreach ( $routes as $route ) {
				$response = rest_do_request( new \WP_REST_Request( $route[0], $route[1] ) );
				self::assertContains( $response->get_status(), 0 === $user ? array( 401 ) : array( 403, 404 ), $route[1] . ' as user ' . $user );
			}
		}
	}

	/**
	 * Secrets never in export, audit log, log files or diagnostics; personal
	 * fields only "changed" in the audit and only with opt-in in the export.
	 */
	public function test_SET_CC_P4_secrets_never_leave(): void {
		wp_set_current_user( $this->admin );
		$secret = 'SENTINEL-' . bin2hex( random_bytes( 8 ) );
		update_option( ProtectedStorage::SECRET_OPTION, $secret, false );
		$actor = Actor::user( $this->admin );
		Settings::save( $actor, 'emails', array( 'from_address' => 'person-sentinel@example.org' ), array( 'from_address' ), Settings::rev( 'emails' ) );
		Settings::save( $actor, 'privacy', array( 'trusted_proxies' => array( '10.98.76.54' ) ), array( 'trusted_proxies' ), Settings::rev( 'privacy' ) );
		Settings::save( $actor, 'advanced', array( 'log_level' => 'debug' ), array( 'log_level' ), Settings::rev( 'advanced' ) );

		$plain = (string) wp_json_encode( SettingsTransfer::export( $actor, array( 'include' => array( 'roles' ) ) ) );
		$full  = (string) wp_json_encode( SettingsTransfer::export( $actor, array( 'include_personal' => true, 'include' => array( 'roles' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short options.
		foreach ( array( $plain, $full ) as $json ) {
			self::assertStringNotContainsString( $secret, $json );
			self::assertStringNotContainsString( '10.98.76.54', $json, 'environment-bound field' );
			self::assertStringNotContainsString( '"log_level"', $json, 'environment-bound field' );
		}
		self::assertStringNotContainsString( 'person-sentinel@example.org', $plain, 'personal only with opt-in' );
		self::assertStringContainsString( 'person-sentinel@example.org', $full );

		$file = SettingsTransfer::export( $actor, array( 'include_personal' => true ) );
		SettingsTransfer::import( $actor, SettingsTransfer::parse( (string) wp_json_encode( $file ) ), array( 'apply' => true ) );
		Settings::reset( $actor, 'emails' );

		$db    = Database::current();
		$audit = (string) wp_json_encode( $db->rows( 'SELECT action, context FROM `' . $db->table( 'ccap_audit_log' ) . '`' ) );
		self::assertStringContainsString( 'settings_saved', $audit );
		self::assertStringNotContainsString( $secret, $audit );
		self::assertStringNotContainsString( 'person-sentinel@example.org', $audit, 'personal values only as "changed"' );

		$dir = Log::dir();
		self::assertTrue( Log::write( 'error', 'security.audit', array( 'token' => $secret, 'client_secret' => $secret, 'password' => $secret, 'mail' => 'person-sentinel@example.org', 'step' => 'ok' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Test context.
		$logs = '';
		foreach ( (array) glob( $dir . '/cc-*.log' ) as $file_name ) {
			$logs .= (string) file_get_contents( (string) $file_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test reads own log files.
		}
		self::assertStringContainsString( 'security_audit', $logs );
		self::assertStringContainsString( '[redacted]', $logs );
		self::assertStringNotContainsString( $secret, $logs );
		self::assertStringNotContainsString( 'person-sentinel@example.org', $logs );
		foreach ( (array) glob( $dir . '/cc-*.log' ) as $file_name ) {
			unlink( (string) $file_name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test cleanup.
		}

		$diagnostics = Diagnostics::current()->export( $actor );
		self::assertStringNotContainsString( $secret, (string) $diagnostics['body'] );
	}

	/**
	 * Uninstall with confirmed deletion removes the new user option; every
	 * option a settings flow creates is in the manifest.
	 */
	public function test_SET_CC_P4_uninstall_removes_new_data(): void {
		wp_set_current_user( $this->admin );
		$actor = Actor::user( $this->admin );
		self::assertTrue( SettingsHints::dismiss( $this->admin, 'webhooks' ) );
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			Settings::reset( $actor, $tab );
		}
		Settings::save( $actor, 'exams', array( 'shuffle_answers' => false ), array( 'shuffle_answers' ), Settings::rev( 'exams' ) );
		SettingsTransfer::import( $actor, SettingsTransfer::parse( (string) wp_json_encode( SettingsTransfer::export( $actor ) ) ), array( 'apply' => true ) );
		global $wpdb;
		$names = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'course\\_captain\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $names as $name ) {
			self::assertTrue( UninstallManifest::covers( (string) $name ), $name . ' in the uninstall manifest' );
		}
		self::assertSame( array( 'webhooks' ), get_user_option( SettingsHints::USER_KEY, $this->admin ) );

		update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( UninstallManifest::DELETE_DATA_KEY => true ) ) );
		self::assertTrue( Uninstaller::uninstall_current_site() );
		clean_user_cache( $this->admin );
		self::assertFalse( get_user_option( SettingsHints::USER_KEY, $this->admin ) );
		foreach ( UninstallManifest::all_option_names() as $option ) {
			self::assertFalse( get_option( $option ), $option . ' removed' );
		}
	}
}
