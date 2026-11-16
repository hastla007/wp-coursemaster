<?php
/**
 * R1-34: roles, privacy, media and mail settings with server-side effect
 * (R0-31 §1, §3.1–§3.3, PSD-xxx; R0-30 §3.3.6, §3.3.8, §3.3.9).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ExternalMedia;
use CourseCaptain\Admin\RoleSettings;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Admin\TestMail;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\RoleCaps;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Privacy\PrivacySettings;
use CourseCaptain\Security\ClientIp;
use CourseCaptain\Settings\RiskConfirm;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Storage\Database;

/**
 * Real options, roles and requests.
 *
 * @group R1-34
 */
final class PrivacySettingsTest extends SchemaTestCase {

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
		}
		delete_option( RoleCaps::UNDO_OPTION );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		RiskConfirm::drop( $this->admin );
		foreach ( Roles::ALL as $role ) {
			$object = get_role( $role );
			if ( null !== $object ) {
				foreach ( Capabilities::PRIMITIVE as $cap ) {
					$object->remove_cap( $cap );
				}
			}
		}
		Roles::install();
		$editor = get_role( 'editor' );
		if ( null !== $editor ) {
			foreach ( Capabilities::PRIMITIVE as $cap ) {
				$editor->remove_cap( $cap );
			}
		}
		parent::tear_down();
	}

	/**
	 * Audit count.
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
	 * PSD-001: defaults of the privacy-relevant fields.
	 *
	 * @group PSD-001
	 */
	public function test_PSD_001_defaults(): void {
		self::assertTrue( Settings::get( 'privacy.auto_export_on_confirm' ) );
		self::assertFalse( Settings::get( 'privacy.auto_erase_on_confirm' ) );
		self::assertSame( 'always_load', Settings::get( 'privacy.external_media_mode' ) );
		self::assertSame( 'marketing', Settings::get( 'privacy.consent_category' ) );
		self::assertSame( array(), Settings::get( 'privacy.trusted_proxies' ) );
		self::assertFalse( Settings::get( 'privacy.show_avatars' ) );
		self::assertSame( 'enforce', Settings::get( 'privacy.csp_mode' ) );
		self::assertSame( 'no_attempts_left_or_passed', Settings::get( 'exams.feedback' )['show_correct_answers_when'] );
		self::assertFalse( Settings::get( 'enrollment.self_enrollment' ) );
		self::assertFalse( Settings::get( 'enrollment.groups_enabled' ) );
		self::assertSame( 'learner_opt_in', Settings::get( 'certificates.holder_name_policy' ) );
		foreach ( array( 'editor', 'author', 'contributor' ) as $role ) {
			self::assertSame( array(), RoleCaps::current( $role ), "RB-1: $role without ccap_*" );
		}
		self::assertNotContains( 'ccap_view_attempt_details', RoleCaps::current( Roles::SUPPORT ), 'RB-3' );
	}

	/**
	 * PSD-006 / PSD-008: audit by class; admin-only fields.
	 *
	 * @group PSD-006
	 * @group PSD-008
	 */
	public function test_PSD_006_008_audit_and_rights(): void {
		Settings::save( Actor::user( $this->admin ), 'emails', array( 'from_name' => 'Akademie Muster' ), array( 'from_name' ), 0 );
		$audit = $this->audits( 'settings_saved' );
		self::assertSame( 'changed', end( $audit )['changed']['from_name'] );
		self::assertStringNotContainsString( 'Akademie Muster', (string) wp_json_encode( $audit ) );

		$manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		get_role( Roles::COURSE_MANAGER )->add_cap( Settings::CAP );
		Settings::save( Actor::user( $manager ), 'privacy', array( 'trusted_proxies' => array( '10.0.0.5' ) ), array( 'trusted_proxies' ), 0 );
		self::assertSame( array(), Settings::get( 'privacy.trusted_proxies' ), 'trusted_proxies only by administrators' );
		self::assertSame( 'ccap_forbidden', SettingsScreen::process_save( array( 'tab' => 'hardening' ), $manager )['result'] );
		$support = (int) self::factory()->user->create( array( 'role' => Roles::SUPPORT ) );
		self::assertSame( 'ccap_forbidden', SettingsScreen::process_save( array( 'tab' => 'privacy' ), $support )['result'] );
	}

	/**
	 * PSD-010 / PSD-009 / PSD-025: R fields from R0-31 need confirmation.
	 *
	 * @group PSD-009
	 * @group PSD-010
	 * @group PSD-025
	 */
	public function test_PSD_010_risk_fields(): void {
		$post = array(
			'tab'      => 'privacy',
			'_wpnonce' => wp_create_nonce( 'ccap_settings_save_privacy' ),
			'_rev'     => 0,
			'_fields'  => array( 'show_avatars', 'consent_category' ),
			'ccap'     => array(
				'show_avatars'     => '1',
				'consent_category' => 'functional',
			),
		);
		self::assertSame( 'confirm', SettingsScreen::process_save( $post, $this->admin )['result'] );
		self::assertSame( array( 'RC-20', 'RC-21' ), RiskConfirm::pending( $this->admin )['risks'] );
		self::assertFalse( Settings::get( 'privacy.show_avatars' ) );

		$exam = array(
			'tab'      => 'exams',
			'_wpnonce' => wp_create_nonce( 'ccap_settings_save_exams' ),
			'_rev'     => 0,
			'_fields'  => array( 'feedback.show_correct_answers_when' ),
			'ccap'     => array( 'feedback' => array( 'show_correct_answers_when' => 'after_submit' ) ),
		);
		self::assertSame( 'confirm', SettingsScreen::process_save( $exam, $this->admin )['result'] );
		self::assertSame( array( 'RC-19' ), RiskConfirm::pending( $this->admin )['risks'] );
		$token = (string) RiskConfirm::pending( $this->admin )['token'];
		$out   = SettingsScreen::process_save(
			array(
				'tab'           => 'exams',
				'_ccap_confirm' => '1',
				'_ccap_token'   => $token,
				'_wpnonce'      => wp_create_nonce( 'ccap_settings_confirm_exams' ),
			),
			$this->admin
		);
		self::assertSame( 'saved', $out['result'] );
		self::assertSame( 'after_submit', Settings::get( 'exams.feedback' )['show_correct_answers_when'] );
		self::assertTrue( Settings::get( 'exams.feedback' )['show_score'], 'other subfields kept' );
	}

	/**
	 * MC-1…MC-5: mode and consent from the settings service.
	 *
	 * @group PSD-022
	 * @group PSD-023
	 * @group PSD-025
	 */
	public function test_MC_external_media_and_consent(): void {
		$url = array( 'url' => 'https://www.youtube-nocookie.com/embed/abc' );
		self::assertNotNull( ExternalMedia::video_view( $url )['video_url'], 'default always_load embeds' );
		self::assertFalse( ExternalMedia::consent()['remember_offer'] );

		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'external_media_mode' => 'click_to_load' ), array( 'external_media_mode' ), 0 );
		$view = ExternalMedia::video_view( $url );
		self::assertNull( $view['video_url'] );
		self::assertTrue( $view['consent_required'] );
		$consent = ExternalMedia::consent();
		self::assertTrue( $consent['remember_offer'] );
		self::assertFalse( $consent['remember_prechecked'], 'MC-3: never pre-ticked' );
		self::assertSame( 'marketing', $consent['category'] );

		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'external_media_remember' => false ), array( 'external_media_remember' ), Settings::rev( 'privacy' ) );
		self::assertFalse( ExternalMedia::consent()['remember_offer'] );

		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'external_media_mode' => 'link_only' ), array( 'external_media_mode' ), Settings::rev( 'privacy' ) );
		self::assertSame( $url['url'], ExternalMedia::video_view( $url )['link_url'] );
		self::assertSame( 'link_only', ExternalMedia::mode() );
	}

	/**
	 * SET-115: trusted proxies decide whether X-Forwarded-For counts.
	 *
	 * @group SET-115
	 * @group ADM-106
	 */
	public function test_SET_115_trusted_proxies(): void {
		self::assertSame( '10.0.0.5', ClientIp::resolve( '10.0.0.5', '203.0.113.9', array() ), 'header ignored without trusted proxies' );
		self::assertSame( '203.0.113.9', ClientIp::resolve( '10.0.0.5', '203.0.113.9', array( '10.0.0.0/8' ) ) );
		self::assertSame( '203.0.113.9', ClientIp::resolve( '10.0.0.5', '198.51.100.1, 203.0.113.9, 10.0.0.7', array( '10.0.0.0/8' ) ), 'right-most untrusted' );
		self::assertSame( '192.0.2.1', ClientIp::resolve( '192.0.2.1', '203.0.113.9', array( '10.0.0.0/8' ) ), 'request not from a proxy' );
		self::assertTrue( ClientIp::in_list( '2001:db8::1', array( '2001:db8::/32' ) ) );

		$_SERVER['REMOTE_ADDR']          = '10.0.0.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';
		self::assertSame( '10.0.0.5', ClientIp::current() );
		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'trusted_proxies' => array( '10.0.0.0/8' ) ), array( 'trusted_proxies' ), 0 );
		self::assertSame( '203.0.113.9', ClientIp::current() );

		$check = Settings::check( Actor::user( $this->admin ), 'privacy', array( 'trusted_proxies' => array( '0.0.0.0/0' ) ), array( 'trusted_proxies' ) );
		self::assertSame( 'ip', $check['errors']['trusted_proxies'] ?? null, 'F-82' );
		$decoded = \CourseCaptain\Admin\SettingsForm::decode( 'privacy', array( 'trusted_proxies' ), array( 'ccap' => array( 'trusted_proxies' => "10.0.0.1\n10.0.0.2\nkein-ip" ) ), Settings::values( 'privacy' ) );
		self::assertSame( 3, $decoded['errors']['trusted_proxies']['params']['line'] );
		$decoded = \CourseCaptain\Admin\SettingsForm::decode( 'privacy', array( 'trusted_proxies' ), array( 'ccap' => array( 'trusted_proxies' => '::/0' ) ), Settings::values( 'privacy' ) );
		self::assertSame( 'ip_all', $decoded['errors']['trusted_proxies']['rule'] );
		$many    = implode( "\n", array_map( static fn( int $i ): string => '10.0.1.' . $i, range( 1, 51 ) ) );
		$decoded = \CourseCaptain\Admin\SettingsForm::decode( 'privacy', array( 'trusted_proxies' ), array( 'ccap' => array( 'trusted_proxies' => $many ) ), Settings::values( 'privacy' ) );
		self::assertSame( 'count', $decoded['errors']['trusted_proxies']['rule'] );
	}

	/**
	 * E-223 / E-43 / RB-9: confirmed data requests.
	 *
	 * @group SET-110
	 * @group PRIV-014
	 */
	public function test_SET_110_confirmed_requests(): void {
		$learner = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'lerner@example.org',
			)
		);
		$export  = (int) wp_create_user_request( 'lerner@example.org', 'export_personal_data' );
		self::assertSame( 'export_scheduled', PrivacySettings::on_confirmed( $export ) );
		self::assertNotFalse( wp_next_scheduled( CronHooks::PRIVACY_EXPORT, array( $export ) ) );
		wp_clear_scheduled_hook( CronHooks::PRIVACY_EXPORT, array( $export ) );
		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'auto_export_on_confirm' => false ), array( 'auto_export_on_confirm' ), 0 );
		self::assertSame( 'none', PrivacySettings::on_confirmed( $export ) );
		self::assertFalse( wp_next_scheduled( CronHooks::PRIVACY_EXPORT, array( $export ) ) );

		$erase = (int) wp_create_user_request( 'lerner@example.org', 'remove_personal_data' );
		self::assertSame( 'none', PrivacySettings::on_confirmed( $erase ), 'default: no automatic deletion' );
		self::assertNotFalse( get_userdata( $learner ) );
		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'auto_erase_on_confirm' => true ), array( 'auto_erase_on_confirm' ), Settings::rev( 'privacy' ) );
		$staff         = (int) self::factory()->user->create(
			array(
				'role'       => Roles::TRAINER,
				'user_email' => 'trainer@example.org',
			)
		);
		$staff_request = (int) wp_create_user_request( 'trainer@example.org', 'remove_personal_data' );
		self::assertSame( 'protected', PrivacySettings::on_confirmed( $staff_request ) );
		self::assertNotFalse( get_userdata( $staff ) );
		if ( ! is_multisite() ) {
			self::assertSame( 'erased', PrivacySettings::on_confirmed( $erase ) );
			self::assertFalse( get_userdata( $learner ) );
		}
		self::assertNotEmpty( $this->audits( 'privacy_request_automated' ) );
		self::assertStringNotContainsString( 'lerner@example.org', (string) wp_json_encode( $this->audits( 'privacy_request_automated' ) ) );
	}

	/**
	 * MC-9: no Gravatar in the learning area unless switched on.
	 *
	 * @group PSD-009
	 */
	public function test_MC_9_avatars(): void {
		update_option( 'show_avatars', '1' );
		self::assertSame( 'kept', PrivacySettings::show_avatars( 'kept' ), 'outside the learning area' );
		set_query_var( \CourseCaptain\Learner\LearningArea::QUERY_VAR, 'kurse' );
		do_action( 'parse_query' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Simulates the core hook.
		self::assertSame( '0', PrivacySettings::show_avatars( false ) );
		Settings::save( Actor::user( $this->admin ), 'privacy', array( 'show_avatars' => true ), array( 'show_avatars' ), 0 );
		self::assertFalse( PrivacySettings::show_avatars( false ) );
		set_query_var( \CourseCaptain\Learner\LearningArea::QUERY_VAR, null );
	}

	/**
	 * PSD-034 / PSD-035 / PSD-036 / PSD-037 / ADM-100: role tab.
	 *
	 * @group PSD-034
	 * @group PSD-035
	 * @group PSD-036
	 * @group PSD-037
	 * @group ADM-100
	 * @group ADM-101
	 */
	public function test_PSD_035_roles(): void {
		$html = SettingsScreen::html( array( 'tab' => 'roles' ), $this->admin );
		self::assertStringContainsString( 'Permissions per role (display only)', $html );
		self::assertStringContainsString( 'Course Trainer: Read exam answers of others (always logged)', $html, 'ADM-101: full name' );
		self::assertStringContainsString( 'high impact', $html );
		self::assertStringContainsString( 'Administrator: always has all permissions', $html );
		self::assertContains( 'roles', SettingsScreen::tabs_for( $this->admin ) );

		// #14 to the trainer by the administrator: RC-07, audit.
		$caps = array_merge( RoleCaps::current( Roles::TRAINER ), array( 'ccap_view_attempt_details' ) );
		$post = array(
			'tab'      => 'roles',
			'role'     => Roles::TRAINER,
			'caps'     => $caps,
			'_wpnonce' => wp_create_nonce( 'ccap_settings_save_roles' ),
		);
		self::assertSame( 'confirm', SettingsScreen::process_save( $post, $this->admin )['result'] );
		self::assertNotContains( 'ccap_view_attempt_details', RoleCaps::current( Roles::TRAINER ) );
		$page = SettingsScreen::html(
			array(
				'tab'         => 'roles',
				'ccap_result' => 'confirm',
			),
			$this->admin
		);
		self::assertStringContainsString( 'Grant high-impact permissions?', $page );
		$out = SettingsScreen::process_save(
			array(
				'tab'           => 'roles',
				'_ccap_confirm' => '1',
				'_ccap_token'   => (string) RiskConfirm::pending( $this->admin )['token'],
				'_wpnonce'      => wp_create_nonce( 'ccap_settings_confirm_roles' ),
			),
			$this->admin
		);
		self::assertSame( 'saved', $out['result'] );
		self::assertContains( 'ccap_view_attempt_details', RoleCaps::current( Roles::TRAINER ) );
		$audit = $this->audits( 'role_caps_changed' );
		self::assertSame( array( 'ccap_view_attempt_details' ), end( $audit )['added'] );
		self::assertSame( array( 'RC-07' ), end( $audit )['confirmed_risks'] );
		self::assertContains( 'ccap_view_attempt_details', Capabilities::recorded_grants()[ Roles::TRAINER ] );

		// Course manager (#24 + promote_users) may not grant #19 (admin only) nor caps it lacks (F-50).
		$manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		get_role( Roles::COURSE_MANAGER )->add_cap( 'promote_users' );
		self::assertTrue( RoleCaps::can_manage( $manager ) );
		self::assertSame( array( 'roles' ), SettingsScreen::tabs_for( $manager ), 'ADM-006' );
		$check = RoleCaps::check( Actor::user( $manager ), Roles::AUTHOR, array( 'ccap_edit_courses', 'ccap_manage_settings' ) );
		self::assertSame( 'cap_not_own', $check['errors']['ccap_manage_settings'] );
		get_role( Roles::COURSE_MANAGER )->add_cap( 'ccap_view_audit_log' );
		$check = RoleCaps::check( Actor::user( $manager ), Roles::AUTHOR, array( 'ccap_edit_courses', 'ccap_view_audit_log' ) );
		self::assertSame( 'cap_admin_only', $check['errors']['ccap_view_audit_log'] );
		$check = RoleCaps::check( Actor::user( $this->admin ), 'administrator', array() );
		self::assertSame( 'role_admin', $check['errors']['role'], 'F-51' );
		$check = RoleCaps::check( Actor::user( $this->admin ), Roles::AUTHOR, array( 'unfiltered_html' ) );
		self::assertSame( 'cap_unknown', $check['errors']['unfiltered_html'], 'RB-7' );
		get_role( Roles::COURSE_MANAGER )->remove_cap( 'promote_users' );
		get_role( Roles::COURSE_MANAGER )->remove_cap( 'ccap_view_audit_log' );

		// RB-8: reset removes only manifest grants, foreign grants stay; undo.
		get_role( 'editor' )->add_cap( 'ccap_view_reports' );
		RoleCaps::apply( Actor::user( $this->admin ), 'editor', array( 'ccap_view_reports', 'ccap_edit_courses' ) );
		self::assertSame( 'roles_reset_confirm', RoleSettings::process_reset( array( '_wpnonce' => wp_create_nonce( RoleSettings::RESET ) ), $this->admin ) );
		self::assertSame(
			'reset',
			RoleSettings::process_reset(
				array(
					'_wpnonce' => wp_create_nonce( RoleSettings::RESET ),
					'confirm'  => '1',
				),
				$this->admin
			)
		);
		self::assertSame( array( 'ccap_view_reports' ), RoleCaps::current( 'editor' ), 'foreign grant kept' );
		self::assertNotContains( 'ccap_view_attempt_details', RoleCaps::current( Roles::TRAINER ) );
		self::assertSame( array_values( array_diff( Roles::capabilities()[ Roles::TRAINER ], Roles::BASE_CAPS ) ), RoleCaps::current( Roles::TRAINER ) );
		foreach ( Roles::ALL as $role ) {
			self::assertFalse( get_role( $role )->has_cap( 'unfiltered_html' ), 'RB-7 after reset' );
		}
		self::assertSame( 'undone', RoleSettings::process_undo( array( '_wpnonce' => wp_create_nonce( RoleSettings::UNDO ) ), $this->admin ) );
		self::assertContains( 'ccap_view_attempt_details', RoleCaps::current( Roles::TRAINER ) );
		self::assertSame( array( 'ccap_edit_courses', 'ccap_view_reports' ), RoleCaps::current( 'editor' ) );

		try {
			RoleCaps::reset( Actor::user( $manager ) );
			self::fail( 'reset needs an administrator' );
		} catch ( CourseException $e ) {
			self::assertSame( 403, $e->status );
		}
	}

	/**
	 * ADM-033 / ADM-103 / SET-102: test mail, required mails, informational mails.
	 *
	 * @group ADM-033
	 * @group ADM-103
	 * @group SET-102
	 */
	public function test_ADM_103_mail_settings(): void {
		$nonce = array( '_wpnonce' => wp_create_nonce( TestMail::ACTION ) );
		self::assertSame( 'sent', TestMail::process( $nonce, $this->admin ) );
		self::assertNotEmpty( get_option( NotificationService::STATE_OPTION )['test_ok_at'] );
		for ( $i = 0; $i < 4; $i++ ) {
			TestMail::process( $nonce, $this->admin );
		}
		self::assertSame( 'ccap_rate_limited', TestMail::process( $nonce, $this->admin ), 'F-63' );
		self::assertStringContainsString( '5 test mails', TestMail::message( 'ccap_rate_limited' ) );
		self::assertSame( 'ccap_invalid_nonce', TestMail::process( array(), $this->admin ) );
		$subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::assertSame( 'ccap_forbidden', TestMail::process( $nonce, $subscriber ) );
		self::assertStringContainsString( 'Send test mail to', SettingsScreen::html( array( 'tab' => 'emails' ), $this->admin ) );

		// F-64: a required type cannot be switched off (strict object).
		$check = Settings::check( Actor::user( $this->admin ), 'emails', array( 'types' => array( 'seat_invitation' => false ) ), array( 'types' ) );
		self::assertSame( 'required_mail', $check['errors']['types'] ?? null );

		// RC-08: switching an informational mail off needs a confirmation.
		$types                      = Settings::get( 'emails.types' );
		$posted                     = array_map( static fn( $on ): string => $on ? '1' : '0', $types );
		$posted['course_completed'] = '0';
		$out                        = SettingsScreen::process_save(
			array(
				'tab'      => 'emails',
				'_wpnonce' => wp_create_nonce( 'ccap_settings_save_emails' ),
				'_rev'     => 0,
				'_fields'  => array( 'types' ),
				'ccap'     => array( 'types' => $posted ),
			),
			$this->admin
		);
		self::assertSame( 'confirm', $out['result'] );
		self::assertSame( array( 'RC-08' ), RiskConfirm::pending( $this->admin )['risks'] );
	}
}
