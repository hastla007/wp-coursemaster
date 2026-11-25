<?php
/**
 * R1-32: settings tabs and setup assistant (R0-30 §2, §3, §4; ADM-xxx).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\Readiness;
use CourseCaptain\Admin\SettingsFields;
use CourseCaptain\Admin\SettingsForm;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Admin\SetupState;
use CourseCaptain\Admin\SetupWizard;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Security\Hardening;
use CourseCaptain\Settings\RiskConfirm;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Storage\Database;

/**
 * Admin-post cores and rendered HTML.
 *
 * @group R1-32
 */
final class AdminSettingsTest extends SchemaTestCase {

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
		delete_option( SetupState::OPTION );
		update_option( 'permalink_structure', '/%postname%/' );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		remove_all_filters( 'course_captain_hardening_conflicts' );
		RiskConfirm::drop( $this->admin );
		parent::tear_down();
	}

	/**
	 * Settings POST.
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $ccap   Values (`ccap[...]`).
	 * @param array<string, mixed> $extra  Extra POST keys.
	 * @return array<string, mixed>
	 */
	private function post( string $tab, array $ccap, array $extra = array() ): array {
		$fields = array();
		foreach ( $ccap as $field => $value ) {
			if ( is_array( $value ) && in_array( $field, array( 'feedback', 'base_template' ), true ) ) {
				foreach ( array_keys( $value ) as $sub ) {
					$fields[] = $field . '.' . $sub;
				}
				continue;
			}
			$fields[] = $field;
		}
		return array_merge(
			array(
				'tab'      => $tab,
				'_wpnonce' => wp_create_nonce( 'ccap_settings_save_' . $tab ),
				'_rev'     => Settings::rev( $tab ),
				'_fields'  => array_merge( $fields, array_keys( (array) ( $extra['ccap_mode'] ?? array() ) ) ),
				'ccap'     => $ccap,
			),
			$extra
		);
	}

	/**
	 * Audit rows.
	 *
	 * @param string $action Action.
	 * @return list<array<string, mixed>>
	 */
	private function audits( string $action ): array {
		$db   = Database::current();
		$rows = $db->rows( $db->prepare( 'SELECT object_id, context FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s ORDER BY id', $action ) );
		return array_map(
			static function ( array $row ): array {
				$row['context'] = json_decode( (string) $row['context'], true );
				return $row;
			},
			$rows
		);
	}

	/**
	 * ADM-070 / ADM-059 / ADM-006: tabs per capability; area navigation.
	 *
	 * @group ADM-070
	 * @group ADM-059
	 * @group GM-179
	 */
	public function test_ADM_070_tabs_and_navigation(): void {
		$html = SettingsScreen::html( array( 'tab' => 'exams' ), $this->admin );
		self::assertStringContainsString( 'aria-label="Settings: areas"', $html );
		self::assertStringContainsString( 'nav-tab-active" aria-current="page', $html );
		self::assertSame( SettingsFields::TAB_ORDER, SettingsScreen::tabs_for( $this->admin ) );
		self::assertSame( 11, count( SettingsFields::TAB_ORDER ) );

		$manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		get_role( Roles::COURSE_MANAGER )->add_cap( Settings::CAP );
		self::assertNotContains( 'hardening', SettingsScreen::tabs_for( $manager ), 'hardening needs manage_options' );
		$out = SettingsScreen::process_save( $this->post( 'hardening', array( 'login_limit' => '1' ) ), $manager );
		self::assertSame( 'ccap_forbidden', $out['result'] );
		get_role( Roles::COURSE_MANAGER )->remove_cap( Settings::CAP );
		self::assertSame( array(), SettingsScreen::tabs_for( $manager ) );
		self::assertSame( 'ccap_forbidden', SettingsScreen::process_save( $this->post( 'exams', array( 'shuffle_answers' => '0' ) ), $manager )['result'] );
		self::assertStringNotContainsString( '<form', SettingsScreen::html( array(), $manager ) );
	}

	/**
	 * ADM-071 / ADM-080 / ADM-084: PRG save, unchanged, field contract, dependent fields.
	 *
	 * @group ADM-071
	 * @group ADM-080
	 * @group ADM-084
	 */
	public function test_ADM_071_save_and_field_contract(): void {
		$out = SettingsScreen::process_save(
			$this->post(
				'learning',
				array(
					'favorites_enabled' => '0',
					'feedback'          => array(
						'visibility'           => 'never',
						'show_correct_answers' => '1',
					),
				)
			),
			$this->admin
		);
		self::assertSame( 'saved', $out['result'] );
		self::assertSame( 2, $out['count'] );
		self::assertFalse( Settings::get( 'learning.favorites_enabled' ) );
		self::assertSame( 'never', Settings::get( 'learning.feedback' )['visibility'] );
		$audit = $this->audits( 'settings_saved' );
		self::assertSame( 'settings', end( $audit )['context']['context'] );

		$again = SettingsScreen::process_save( $this->post( 'learning', array( 'favorites_enabled' => '0' ) ), $this->admin );
		self::assertSame( 'unchanged', $again['result'] );
		self::assertCount( count( $audit ), $this->audits( 'settings_saved' ), 'no audit without change' );

		$html = SettingsScreen::html(
			array(
				'tab'         => 'learning',
				'ccap_result' => 'saved',
			),
			$this->admin
		);
		self::assertStringContainsString( 'Settings “Learning” saved.', $html );
		self::assertStringContainsString( 'data-ccap-focus="1"', $html );
		$id = SettingsForm::id( 'learning', 'feedback.show_correct_answers' );
		self::assertMatchesRegularExpression( '/id="' . preg_quote( $id, '/' ) . '"[^>]*aria-disabled="true"/', $html, 'T-12: dependent field stays focusable' );

		// Schema scan: every rendered key has label, help, meta and described-by targets.
		foreach ( SettingsFields::TAB_ORDER as $tab ) {
			if ( 'roles' === $tab ) {
				continue;
			}
			$tab_html = SettingsScreen::html( array( 'tab' => $tab ), $this->admin );
			foreach ( SettingsScreen::all_keys( $tab ) as $key ) {
				$ui = SettingsFields::ui( $tab, $key );
				self::assertNotSame( $key, $ui['label'], "label of $tab.$key" );
				self::assertNotSame( '', $ui['help'], "help of $tab.$key" );
				if ( 'mastery_algorithm' === $key ) {
					continue;
				}
				$id = SettingsForm::id( $tab, $key );
				self::assertStringContainsString( 'id="' . $id . '-help"', $tab_html, "help element of $tab.$key" );
				self::assertStringContainsString( 'id="' . $id . '-meta"', $tab_html, "meta element of $tab.$key" );
			}
			self::assertStringNotContainsString( 'title="', $tab_html, 'no tooltip help texts' );
		}
	}

	/**
	 * ADM-072 / ADM-096 / ADM-028 / ADM-030: invalid input saves nothing, field-near errors.
	 *
	 * @group ADM-072
	 * @group ADM-096
	 * @group ADM-028
	 * @group ADM-030
	 */
	public function test_ADM_072_invalid_input(): void {
		$before = get_option( Settings::OPTION_PREFIX . 'exams' );
		$out    = SettingsScreen::process_save(
			$this->post(
				'exams',
				array(
					'cooldown_seconds'       => '60',
					'late_tolerance_seconds' => '900',
					'shuffle_answers'        => '0',
				)
			),
			$this->admin
		);
		self::assertSame( 'invalid', $out['result'] );
		self::assertSame( $before, get_option( Settings::OPTION_PREFIX . 'exams' ), 'all or nothing' );
		$html = SettingsScreen::html(
			array(
				'tab'         => 'exams',
				'ccap_result' => 'invalid',
			),
			$this->admin
		);
		$id   = SettingsForm::id( 'exams', 'late_tolerance_seconds' );
		self::assertStringContainsString( 'role="alert"', $html );
		self::assertStringContainsString( 'href="#' . $id . '"', $html );
		self::assertMatchesRegularExpression( '/id="' . preg_quote( $id, '/' ) . '"[^>]*value="900"[^>]*aria-describedby="[^"]*' . preg_quote( $id, '/' ) . '-error"[^>]*aria-invalid="true"/', $html, 'input kept, error referenced' );
		self::assertStringContainsString( 'Error: Tolerance for connection loss: enter a whole number from 0 to 600.', $html );
		self::assertMatchesRegularExpression( '/id="' . preg_quote( SettingsForm::id( 'exams', 'cooldown_seconds' ), '/' ) . '"[^>]*value="60"/', $html );

		// Percent parsing (F-31, F-32).
		foreach ( array(
			'0'     => 'percent_range',
			'101'   => 'percent_range',
			'70,55' => 'percent_precision',
		) as $value => $rule ) {
			$decoded = SettingsForm::decode( 'exams', array( 'passing_ratio_proposal' ), array( 'ccap' => array( 'passing_ratio_proposal' => $value ) ), Settings::values( 'exams' ) );
			self::assertSame( $rule, $decoded['errors']['passing_ratio_proposal']['rule'] ?? null, (string) $value );
		}
		$ok = SettingsScreen::process_save( $this->post( 'exams', array( 'passing_ratio_proposal' => '66,7' ) ), $this->admin );
		self::assertSame( 'saved', $ok['result'] );
		self::assertSame(
			array(
				'num' => 667,
				'den' => 1000,
			),
			Settings::get( 'exams.passing_ratio_proposal' )
		);
		self::assertSame( 'unchanged', SettingsScreen::process_save( $this->post( 'exams', array( 'passing_ratio_proposal' => '66.7' ) ), $this->admin )['result'] );

		// Slugs (F-01, F-02, F-04).
		foreach ( array(
			'wp-admin' => 'reserved',
			'feed'     => 'reserved',
			'-lernen'  => 'format',
			'über'     => 'format',
		) as $slug => $rule ) {
			$check = Settings::check( \CourseCaptain\Policy\Actor::user( $this->admin ), 'general', array( 'learning_area_slug' => $slug ), array( 'learning_area_slug' ) );
			self::assertSame( $rule, $check['errors']['learning_area_slug'] ?? null, $slug );
		}
		$check = Settings::check( \CourseCaptain\Policy\Actor::user( $this->admin ), 'general', array( 'course_slug' => 'lernen' ), array( 'course_slug' ) );
		self::assertSame( 'equal', $check['errors']['course_slug'] ?? null );
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'akademie',
				'post_status' => 'publish',
			)
		);
		$check = Settings::check( \CourseCaptain\Policy\Actor::user( $this->admin ), 'general', array( 'learning_area_slug' => 'akademie' ), array( 'learning_area_slug' ) );
		self::assertSame( 'collision', $check['errors']['learning_area_slug'] ?? null );
		self::assertSame( 'ueber', \CourseCaptain\Settings\SettingsRules::slug_suggestion( 'über' ) );

		// Pages (F-10, F-11).
		$draft = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		$check = Settings::check( \CourseCaptain\Policy\Actor::user( $this->admin ), 'general', array( 'catalog_page_id' => (string) $draft ), array( 'catalog_page_id' ) );
		self::assertSame( 'page_unpublished', $check['errors']['catalog_page_id'] ?? null );
		$check = Settings::check( \CourseCaptain\Policy\Actor::user( $this->admin ), 'general', array( 'catalog_page_id' => '999999' ), array( 'catalog_page_id' ) );
		self::assertSame( 'page_missing', $check['errors']['catalog_page_id'] ?? null );
	}

	/**
	 * ADM-079 / ADM-078: risk fields need a one-time token bound to the values.
	 *
	 * @group ADM-079
	 * @group ADM-078
	 * @group PSD-004
	 */
	public function test_ADM_079_risk_confirmation(): void {
		$post = $this->post(
			'privacy',
			array(
				'auto_erase_on_confirm' => '1',
				'csp_mode'              => 'report_only',
			)
		);
		$out  = SettingsScreen::process_save( $post, $this->admin );
		self::assertSame( 'confirm', $out['result'] );
		self::assertFalse( Settings::get( 'privacy.auto_erase_on_confirm' ), 'nothing saved without token' );
		$pending = RiskConfirm::pending( $this->admin );
		self::assertSame( array( 'RC-09', 'RC-13' ), $pending['risks'] );
		$html = SettingsScreen::html(
			array(
				'tab'         => 'privacy',
				'ccap_result' => 'confirm',
			),
			$this->admin
		);
		self::assertSame( 2, substr_count( $html, '<h3>' ), 'D-5: one section per risk' );
		self::assertStringContainsString( 'Confirm changes and save', $html );
		self::assertMatchesRegularExpression( '/data-ccap-focus="1">Cancel</', $html, 'D-3: initial focus on cancel' );

		$confirm = array(
			'tab'           => 'privacy',
			'_ccap_confirm' => '1',
			'_ccap_token'   => 'forged',
			'_wpnonce'      => wp_create_nonce( 'ccap_settings_confirm_privacy' ),
		);
		self::assertSame( 'ccap_confirmation_expired', SettingsScreen::process_save( $confirm, $this->admin )['result'] );
		self::assertFalse( Settings::get( 'privacy.auto_erase_on_confirm' ) );

		SettingsScreen::process_save( $post, $this->admin );
		$token                  = (string) RiskConfirm::pending( $this->admin )['token'];
		$confirm['_ccap_token'] = $token;
		$other                  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::assertSame( 'ccap_confirmation_expired', SettingsScreen::process_save( array_merge( $confirm, array( '_wpnonce' => wp_create_nonce( 'ccap_settings_confirm_privacy' ) ) ), $other )['result'], 'token of another user' );
		self::assertSame( 'saved', SettingsScreen::process_save( $confirm, $this->admin )['result'] );
		self::assertTrue( Settings::get( 'privacy.auto_erase_on_confirm' ) );
		$audit = $this->audits( 'settings_saved' );
		self::assertSame( array( 'RC-09', 'RC-13' ), end( $audit )['context']['confirmed_risks'] );
		self::assertSame( 'ccap_confirmation_expired', SettingsScreen::process_save( $confirm, $this->admin )['result'], 'token is single use' );

		// Values changed after confirmation: hash mismatch (F-89).
		$token = RiskConfirm::issue( $this->admin, 'settings:privacy', array( 'csp_mode' => 'report_only' ), array( 'RC-13' ) );
		self::assertNull( RiskConfirm::consume( $this->admin, 'settings:privacy', $token, array( 'csp_mode' => 'enforce' ) ) );
		// Stricter changes need no confirmation (PD-1).
		self::assertSame( 'saved', SettingsScreen::process_save( $this->post( 'privacy', array( 'csp_mode' => 'enforce' ) ), $this->admin )['result'] );
	}

	/**
	 * ADM-075 / ADM-076 / ADM-077 / ADM-040: reset with confirmation and undo, conflict, nonce.
	 *
	 * @group ADM-075
	 * @group ADM-076
	 * @group ADM-077
	 */
	public function test_ADM_075_reset_undo_conflict(): void {
		$html = SettingsScreen::html( array( 'tab' => 'exams' ), $this->admin );
		self::assertStringContainsString( 'aria-disabled="true" aria-describedby="cc-reset-exams-hint"', $html, 'nothing to reset' );
		SettingsScreen::process_save( $this->post( 'exams', array( 'shuffle_answers' => '0' ) ), $this->admin );

		$reset = array(
			'tab'      => 'exams',
			'_wpnonce' => wp_create_nonce( 'ccap_settings_reset_exams' ),
		);
		self::assertSame( 'reset_confirm', SettingsScreen::process_reset( $reset, $this->admin )['result'] );
		self::assertFalse( Settings::get( 'exams.shuffle_answers' ), 'RC-17 first' );
		$page = SettingsScreen::html(
			array(
				'tab'         => 'exams',
				'ccap_result' => 'reset_confirm',
			),
			$this->admin
		);
		self::assertStringContainsString( 'Shuffle answers: off → on', $page );
		$out = SettingsScreen::process_reset( $reset + array( 'confirm' => '1' ), $this->admin );
		self::assertSame( 'reset', $out['result'] );
		self::assertTrue( Settings::get( 'exams.shuffle_answers' ) );
		self::assertGreaterThan( 0, Settings::undo_until( 'exams' ) );
		$undo = array(
			'tab'      => 'exams',
			'_wpnonce' => wp_create_nonce( 'ccap_settings_undo_exams' ),
		);
		self::assertSame( 'undone', SettingsScreen::process_undo( $undo, $this->admin )['result'] );
		self::assertFalse( Settings::get( 'exams.shuffle_answers' ) );
		self::assertSame( 'undo_expired', SettingsScreen::process_undo( $undo, $this->admin )['result'] );

		// Conflict (T-7): form rendered with an old _rev.
		$stale = $this->post( 'exams', array( 'cooldown_seconds' => '30' ) );
		$other = (int) self::factory()->user->create(
			array(
				'role'         => 'administrator',
				'display_name' => 'Admin B',
			)
		);
		Settings::save( \CourseCaptain\Policy\Actor::user( $other ), 'exams', array( 'cooldown_seconds' => 90 ), array( 'cooldown_seconds' ), Settings::rev( 'exams' ) );
		self::assertSame( 'conflict', SettingsScreen::process_save( $stale, $this->admin )['result'] );
		self::assertSame( 90, Settings::get( 'exams.cooldown_seconds' ) );
		$html = SettingsScreen::html(
			array(
				'tab'         => 'exams',
				'ccap_result' => 'conflict',
			),
			$this->admin
		);
		self::assertStringContainsString( 'changed in the meantime by Admin B', $html );
		self::assertStringContainsString( 'Changed in the meantime to: 90 seconds', $html );

		// Nonce.
		$bad             = $this->post( 'exams', array( 'cooldown_seconds' => '10' ) );
		$bad['_wpnonce'] = 'x';
		self::assertSame( 'ccap_invalid_nonce', SettingsScreen::process_save( $bad, $this->admin )['result'] );
		self::assertSame( 'ccap_invalid_nonce', SettingsScreen::process_reset( array( 'tab' => 'exams' ) + array( '_wpnonce' => 'x' ), $this->admin )['result'] );
	}

	/**
	 * ADM-085 / ADM-091: warnings save anyway; data deletion needs the site name.
	 *
	 * @group ADM-085
	 * @group ADM-091
	 */
	public function test_ADM_085_warnings_and_site_name(): void {
		$out = SettingsScreen::process_save( $this->post( 'emails', array( 'from_address' => 'kurse@other-domain.test' ) ), $this->admin );
		self::assertSame( 'saved', $out['result'] );
		$html = SettingsScreen::html(
			array(
				'tab'         => 'emails',
				'ccap_result' => 'saved',
			),
			$this->admin
		);
		self::assertStringContainsString( 'saved, 1 warning', $html );
		self::assertStringContainsString( 'Warning: the address does not belong to the domain', $html );

		self::assertSame( 'invalid', SettingsScreen::process_save( $this->post( 'general', array( 'delete_data_on_uninstall' => '1' ) ), $this->admin )['result'] );
		self::assertFalse( Settings::get( 'general.delete_data_on_uninstall' ) );
		// SET-CC-30: the site name no longer confirms; the typed word does (any letter case).
		$name = trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
		self::assertSame( 'invalid', SettingsScreen::process_save( $this->post( 'general', array( 'delete_data_on_uninstall' => '1' ), array( Settings::CONFIRM_WORD_FIELD => $name ) ), $this->admin )['result'] );
		$html = SettingsScreen::html( array( 'tab' => 'general' ), $this->admin );
		self::assertStringContainsString( 'To switch it on, type LÖSCHEN or DELETE:', $html );
		self::assertStringContainsString( 'name="' . Settings::CONFIRM_WORD_FIELD . '"', $html );
		$ok = SettingsScreen::process_save( $this->post( 'general', array( 'delete_data_on_uninstall' => '1' ), array( Settings::CONFIRM_WORD_FIELD => 'Delete' ) ), $this->admin );
		self::assertSame( 'saved', $ok['result'] );
		self::assertTrue( Settings::get( 'general.delete_data_on_uninstall' ) );
		self::assertStringNotContainsString( 'name="' . Settings::CONFIRM_WORD_FIELD . '"', SettingsScreen::html( array( 'tab' => 'general' ), $this->admin ), 'switching off needs no word' );
		self::assertSame( 'saved', SettingsScreen::process_save( $this->post( 'general', array( 'delete_data_on_uninstall' => '0' ) ), $this->admin )['result'] );
		self::assertFalse( Settings::get( 'general.delete_data_on_uninstall' ) );
	}

	/**
	 * Wizard step POST.
	 *
	 * @param string                              $step  Step.
	 * @param array<string, array<string, mixed>> $tabs  Values per tab.
	 * @param array<string, mixed>                $extra Extra POST.
	 * @return array{step: string, result: string}
	 */
	private function step( string $step, array $tabs, array $extra = array() ): array {
		$post = array(
			'step'     => $step,
			'_wpnonce' => wp_create_nonce( 'ccap_setup_step_' . $step ),
			't'        => array(),
			'_rev'     => array(),
		);
		foreach ( SetupWizard::step_fields( $step ) as $tab => $keys ) {
			$post['_rev'][ $tab ] = Settings::rev( $tab );
			$post['t'][ $tab ]    = array(
				'_fields' => $keys,
				'ccap'    => $tabs[ $tab ] ?? array(),
			);
			if ( isset( $tabs[ $tab ]['_mode'] ) ) {
				$post['t'][ $tab ]['ccap_mode'] = $tabs[ $tab ]['_mode'];
			}
		}
		return SetupWizard::process_step( array_merge( $post, $extra ), $this->admin );
	}

	/**
	 * ADM-022 / ADM-029 / ADM-034 / PSD-065: full run with suggestions.
	 *
	 * @group ADM-022
	 * @group ADM-029
	 * @group ADM-034
	 * @group ADM-050
	 * @group ADM-051
	 * @group PSD-065
	 */
	public function test_ADM_022_full_run(): void {
		self::assertSame( 'off:unconfirmed', Hardening::states()['login_limit'], 'ADM-050: off before the assistant' );
		self::assertStringContainsString( 'Start setup', SetupWizard::notice_html( $this->admin ) );

		self::assertSame( 'saved', $this->step( 'purpose_language', array( 'general' => array( 'use_profile_locale' => '' ) ), array( 'purpose' => 'exam_prep' ) )['result'] );
		self::assertSame( 'exam_prep', SetupState::get()['purpose'] );
		self::assertSame( 'addresses', SetupState::get()['current_step'] );
		self::assertStringContainsString( 'The setup stopped at step 2 of 8.', SetupWizard::notice_html( $this->admin ) );

		$addresses = array(
			'general' => array(
				'learning_area_slug'   => 'lernen',
				'course_slug'          => 'kurs',
				'catalog_page_id'      => '0',
				'learner_home_page_id' => '0',
			),
		);
		self::assertSame( 'saved', $this->step( 'addresses', $addresses, array( 'create_catalog' => '1' ) )['result'] );
		$page = (int) SetupState::get()['created']['catalog_page_id'];
		self::assertGreaterThan( 0, $page );
		self::assertSame( $page, Settings::get( 'general.catalog_page_id' ) );
		self::assertStringContainsString( 'course-captain/course-catalog', (string) get_post( $page )->post_content );
		$this->step( 'addresses', $addresses, array( 'create_catalog' => '1' ) );
		self::assertSame( $page, (int) SetupState::get()['created']['catalog_page_id'], 'ADM-029: no second page' );
		self::assertSame( 1, count( get_posts( array( 'post_type' => 'page', 'title' => 'Courses', 'post_status' => 'publish' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.

		self::assertSame(
			'saved',
			$this->step(
				'enrollment',
				array(
					'enrollment' => array(
						'self_enrollment'   => '0',
						'groups_enabled'    => '1',
						'invite_valid_days' => '14',
						'_mode'             => array( 'default_access_days' => 'none' ),
					),
				)
			)['result']
		);
		self::assertTrue( Settings::get( 'enrollment.groups_enabled' ) );

		// Step 4: suggestion for exam_prep is shown, not stored.
		$html = SetupWizard::html( array( 'step' => 'learning_exams' ), $this->admin );
		self::assertStringContainsString( 'Suggestion for your purpose.', $html );
		self::assertNull( Settings::get( 'exams.time_limit_minutes' ) );
		self::assertSame(
			'saved',
			$this->step(
				'learning_exams',
				array(
					'learning' => array( 'feedback' => array( 'visibility' => 'after_submit' ) ),
					'exams'    => array(
						'max_attempts'           => '3',
						'passing_ratio_proposal' => '75',
						'time_limit_minutes'     => '60',
						'timer_mode'             => 'strict',
						'feedback'               => array( 'show_correct_answers_when' => 'no_attempts_left_or_passed' ),
						'_mode'                  => array(
							'max_attempts'       => 'value',
							'time_limit_minutes' => 'value',
						),
					),
				)
			)['result']
		);
		self::assertSame( 60, Settings::get( 'exams.time_limit_minutes' ) );
		self::assertSame( 'after_submit', Settings::get( 'learning.feedback' )['visibility'] );
		self::assertTrue( Settings::get( 'learning.feedback' )['show_correct_answers'], 'other subfields untouched' );
		self::assertSame(
			array(
				'num' => 3,
				'den' => 4,
			),
			Settings::get( 'exams.passing_ratio_proposal' )
		);

		self::assertSame(
			'saved',
			$this->step(
				'mail_privacy',
				array(
					'emails'  => array(
						'from_name'    => '',
						'from_address' => '',
					),
					'privacy' => array(
						'external_media_mode'    => 'always_load',
						'auto_export_on_confirm' => '1',
					),
				)
			)['result']
		);

		// Step 6: suggestions (all on, no conflict) → confirmation and active login limit.
		$html = SetupWizard::html( array( 'step' => 'hardening' ), $this->admin );
		self::assertStringContainsString( 'No security plugin detected.', $html );
		$hardening = array(
			'login_limit'  => '1',
			'known_device' => '1',
			'reset_limit'  => '1',
			'xmlrpc'       => 'auto',
			'site_headers' => '1',
		);
		self::assertSame( 'confirm', $this->step( 'hardening', array( 'hardening' => $hardening ) )['result'], 'RC-14/RC-15' );
		self::assertFalse( Hardening::confirmed() );
		$pending = RiskConfirm::pending( $this->admin );
		self::assertSame(
			'saved',
			SetupWizard::process_step(
				array(
					'step'          => 'hardening',
					'_ccap_confirm' => '1',
					'_ccap_token'   => (string) $pending['token'],
					'_wpnonce'      => wp_create_nonce( 'ccap_setup_confirm_hardening' ),
				),
				$this->admin
			)['result']
		);
		self::assertTrue( Hardening::confirmed() );
		self::assertSame( 'active', Hardening::states()['login_limit'], 'ADM-051' );

		self::assertSame( 'invalid', $this->step( 'first_course', array(), array( 'choice' => 'create' ) )['result'], 'F-70' );
		self::assertSame( 'invalid', $this->step( 'first_course', array(), array( 'choice' => 'create', 'course_title' => str_repeat( 'x', 201 ) ) )['result'], 'F-71' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short args.
		SetupWizard::html( array( 'step' => 'first_course' ), $this->admin );
		self::assertSame( 'saved', $this->step( 'first_course', array(), array( 'choice' => 'create', 'course_title' => 'Grundkurs' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short args.
		$course = (int) SetupState::get()['created']['first_course_id'];
		self::assertSame( 'draft', get_post_status( $course ) );
		self::assertSame( Relations::COURSE_TYPE, get_post_type( $course ) );
		$modules = \CourseCaptain\Course\CourseStructure::current()->modules( $course );
		self::assertCount( 1, $modules );
		self::assertCount( 1, \CourseCaptain\Course\CourseStructure::current()->lessons( (int) $modules[0]['module_id'] ) );
		$this->step( 'first_course', array(), array( 'choice' => 'create', 'course_title' => 'Grundkurs' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short args.
		self::assertSame( 1, count( get_posts( array( 'post_type' => Relations::COURSE_TYPE, 'post_status' => 'any' ) ) ), 'ADM-034: nothing twice' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short query.

		$steps = array_column( array_filter( $this->audits( 'settings_saved' ), static fn( array $row ): bool => 'setup' === ( $row['context']['context'] ?? '' ) ), 'context' );
		self::assertSame( array( 'addresses', 'enrollment', 'learning_exams', 'learning_exams', 'hardening' ), array_column( $steps, 'step' ), 'audit per changed tab with step key' );

		$readiness = SetupWizard::html( array( 'step' => 'readiness' ), $this->admin );
		self::assertStringContainsString( 'Check finished:', $readiness );
		self::assertCount( 12, Readiness::run() );
		$done = SetupWizard::process_complete( array( '_wpnonce' => wp_create_nonce( SetupWizard::COMPLETE ) ), $this->admin );
		self::assertContains( $done['result'], array( 'completed', 'completed_with_issues' ) );
		self::assertSame( $done['result'], SetupState::get()['status'] );
		self::assertSame( '', SetupWizard::notice_html( $this->admin ) );
	}

	/**
	 * ADM-025 / ADM-026 / ADM-046 / ADM-021 / ADM-040: skip, leave, passthrough, rights.
	 *
	 * @group ADM-025
	 * @group ADM-026
	 * @group ADM-046
	 * @group ADM-021
	 * @group ADM-040
	 */
	public function test_ADM_025_skip_leave_rights(): void {
		$emails  = get_option( Settings::OPTION_PREFIX . 'emails' );
		$privacy = get_option( Settings::OPTION_PREFIX . 'privacy' );
		$skip    = SetupWizard::process_skip(
			array(
				'step'     => 'mail_privacy',
				'_wpnonce' => wp_create_nonce( 'ccap_setup_skip_mail_privacy' ),
			),
			$this->admin
		);
		self::assertSame( 'skipped', $skip['result'] );
		self::assertSame( 'skipped', SetupState::get()['steps']['mail_privacy']['status'] );
		self::assertSame( $emails, get_option( Settings::OPTION_PREFIX . 'emails' ) );
		self::assertSame( $privacy, get_option( Settings::OPTION_PREFIX . 'privacy' ) );
		self::assertStringContainsString( 'E-Mail', 'E-Mail' );
		self::assertStringContainsString( 'Email &amp; privacy – skipped', SetupWizard::html( array( 'step' => 'purpose_language' ), $this->admin ) );

		// ADM-026: rendering every step changes nothing.
		$state = get_option( SetupState::OPTION );
		$db    = Database::current();
		$count = (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . '`' );
		foreach ( SetupState::STEPS as $step ) {
			SetupWizard::html( array( 'step' => $step ), $this->admin );
		}
		self::assertSame( $state, get_option( SetupState::OPTION ) );
		self::assertSame( $count, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . '`' ) );

		// ADM-046: unknown step key of a newer version survives.
		$raw                         = get_option( SetupState::OPTION );
		$raw['steps']['future_step'] = array( 'status' => 'saved' );
		update_option( SetupState::OPTION, $raw, false );
		$this->step( 'purpose_language', array( 'general' => array( 'use_profile_locale' => '1' ) ) );
		self::assertSame( array( 'status' => 'saved' ), get_option( SetupState::OPTION )['steps']['future_step'] );

		// ADM-021 / ADM-040.
		$manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		self::assertSame( '', SetupWizard::notice_html( $manager ) );
		try {
			SetupWizard::html( array(), $manager );
			self::fail( '403 expected' );
		} catch ( CourseException $e ) {
			self::assertSame( 403, $e->status );
		}
		$before = get_option( SetupState::OPTION );
		self::assertSame( 'ccap_forbidden', SetupWizard::process_step( array( 'step' => 'enrollment' ), $manager )['result'] );
		self::assertSame( 'ccap_invalid_nonce', SetupWizard::process_step( array( 'step' => 'enrollment', '_wpnonce' => 'x' ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short args.
		self::assertSame( $before, get_option( SetupState::OPTION ) );

		// Dismiss per user.
		SetupWizard::dismiss( $this->admin );
		self::assertSame( '', SetupWizard::notice_html( $this->admin ) );
		$second = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::assertNotSame( '', SetupWizard::notice_html( $second ) );
	}

	/**
	 * ADM-039 / ADM-052 / ADM-027: parallel admins, hardening suggestion with a security plugin, slug collision.
	 *
	 * @group ADM-039
	 * @group ADM-052
	 * @group ADM-027
	 */
	public function test_ADM_039_conflict_and_conflicting_plugin(): void {
		$post  = array(
			'step'     => 'enrollment',
			'_wpnonce' => wp_create_nonce( 'ccap_setup_step_enrollment' ),
			'_rev'     => array( 'enrollment' => Settings::rev( 'enrollment' ) ),
			't'        => array(
				'enrollment' => array(
					'_fields' => array( 'invite_valid_days' ),
					'ccap'    => array( 'invite_valid_days' => '10' ),
				),
			),
		);
		$other = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		Settings::save( \CourseCaptain\Policy\Actor::user( $other ), 'enrollment', array( 'invite_valid_days' => 20 ), array( 'invite_valid_days' ), 0 );
		self::assertSame( 'conflict', SetupWizard::process_step( $post, $this->admin )['result'] );
		self::assertSame( 20, Settings::get( 'enrollment.invite_valid_days' ) );

		add_filter(
			'course_captain_hardening_conflicts',
			static fn(): array => array(
				'login_limit' => 'wordfence',
				'reset_limit' => 'wordfence',
			)
		);
		$suggest = SetupWizard::suggestions( 'hardening', SetupState::get() );
		self::assertSame( 'off', $suggest['hardening.login_limit'] );
		self::assertSame( 'auto', $suggest['hardening.site_headers'] );
		self::assertStringContainsString( 'wordfence', SetupWizard::html( array( 'step' => 'hardening' ), $this->admin ) );

		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'schule',
				'post_status' => 'publish',
			)
		);
		$result = $this->step(
			'addresses',
			array(
				'general' => array(
					'learning_area_slug'   => 'schule',
					'course_slug'          => 'kurs',
					'catalog_page_id'      => '0',
					'learner_home_page_id' => '0',
				),
			)
		);
		self::assertSame( 'invalid', $result['result'] );
		self::assertSame( 'lernen', Settings::get( 'general.learning_area_slug' ) );
		$html = SetupWizard::html( array( 'step' => 'addresses' ), $this->admin );
		self::assertStringContainsString( 'is already used by a page or post', $html );
		self::assertStringContainsString( 'value="schule"', $html, 'input kept' );
	}
}
