<?php
/**
 * SET-CC-P5 / SET-CC-21: every settings field has a help text and a
 * field-level error message, checked over the register (SettingsSchema)
 * and the UI catalogue (SettingsFields) through the real form path.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\MailTextsScreen;
use CourseCaptain\Admin\SettingLocksScreen;
use CourseCaptain\Admin\SettingsBackup;
use CourseCaptain\Admin\SettingsFields;
use CourseCaptain\Admin\SettingsForm;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Admin\SetupState;
use CourseCaptain\Admin\SetupWizard;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\AuditScreen;
use CourseCaptain\Settings\RiskConfirm;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * One invalid value per field, submitted like the browser does.
 *
 * @group SET-CC-P5
 * @group SET-CC-21
 */
final class SettingsFieldContractTest extends SchemaTestCase {

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
		wp_set_current_user( $this->admin );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		RiskConfirm::drop( $this->admin );
		parent::tear_down();
	}

	/**
	 * Invalid form input a person can type for a field, or null when the
	 * control only offers valid values (checkbox, radio of a yes/no field).
	 * `tamper` marks values only a manipulated request can send.
	 *
	 * @param array<string, mixed> $ui  UI definition.
	 * @param array<string, mixed> $def Schema of the key.
	 * @return array{value: mixed, tamper: bool}|null
	 */
	private static function invalid_input( array $ui, array $def ): ?array {
		$type = (string) ( $def['type'] ?? '' );
		switch ( $ui['element'] ) {
			case 'number':
			case 'nullable_number':
				return array(
					'value'  => isset( $def['max'] ) ? (string) ( (int) $def['max'] + 1 ) : (string) ( (int) ( $def['min'] ?? 0 ) - 1 ),
					'tamper' => false,
				);
			case 'page':
				return array(
					'value'  => '999999',
					'tamper' => false,
				);
			case 'percent':
				return array(
					'value'  => '101',
					'tamper' => false,
				);
			case 'email':
				return array(
					'value'  => 'no-address',
					'tamper' => false,
				);
			case 'url':
				return array(
					'value'  => 'javascript:alert(1)',
					'tamper' => false,
				);
			case 'hosts':
				return array(
					'value'  => "media.example.org\nnot a host/",
					'tamper' => false,
				);
			case 'lines':
				return array(
					'value'  => "10.0.0.0/8\n999.1.1.1",
					'tamper' => false,
				);
			case 'text':
			case 'textarea':
				if ( 'slug' === $type ) {
					return array(
						'value'  => '-lernen',
						'tamper' => false,
					);
				}
				if ( 'color' === $type ) {
					return array(
						'value'  => 'blue',
						'tamper' => false,
					);
				}
				return array(
					'value'  => str_repeat( 'x', (int) ( $def['max'] ?? 255 ) + 1 ),
					'tamper' => false,
				);
			case 'select':
				return array(
					'value'  => 'no-such-option',
					'tamper' => true,
				);
			case 'radio':
				return 'bool' === $type ? null : array(
					'value'  => 'no-such-option',
					'tamper' => true,
				);
			case 'set':
				return array(
					'value'  => array( 'no-such-option' ),
					'tamper' => true,
				);
		}
		return null;
	}

	/**
	 * POST of one key.
	 *
	 * @param string $tab   Tab.
	 * @param string $key   Key.
	 * @param mixed  $value Raw form value.
	 * @return array<string, mixed>
	 */
	private function post( string $tab, string $key, $value ): array {
		list( $f, $sub ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		return array(
			'tab'       => $tab,
			'_wpnonce'  => wp_create_nonce( 'ccap_settings_save_' . $tab ),
			'_rev'      => Settings::rev( $tab ),
			'_fields'   => array( $key ),
			'ccap'      => array( $f => '' === $sub ? $value : array( $sub => $value ) ),
			'ccap_mode' => array( str_replace( '.', '__', $key ) => 'value' ),
		);
	}

	/**
	 * Every rendered key: label, help text, meta line; an invalid value comes
	 * back as a field-level error (aria-invalid, aria-describedby → "Error:"
	 * text naming the field's own rule, error summary link to the field).
	 */
	public function test_every_field_has_help_and_a_field_level_error(): void {
		$checked = array(
			'typed'  => 0,
			'tamper' => 0,
			'forced' => 0,
		);
		foreach ( SettingsFields::TAB_ORDER as $tab ) {
			if ( 'roles' === $tab ) {
				continue;
			}
			$page = SettingsScreen::html( array( 'tab' => $tab ), $this->admin );
			self::assertSame( array(), A11yDom::fragment_problems( $page ), "DOM problems on tab $tab" );
			foreach ( SettingsScreen::all_keys( $tab ) as $key ) {
				$ui  = SettingsFields::ui( $tab, $key );
				$def = (array) SettingsFields::schema( $tab, $key );
				$id  = SettingsForm::id( $tab, $key );
				self::assertNotSame( $key, $ui['label'], "label of $tab.$key" );
				self::assertGreaterThan( 20, strlen( (string) $ui['help'] ), "help text of $tab.$key" );
				if ( 'static' === $ui['element'] || ( 'learning' === $tab && 'mastery_algorithm' === $key ) ) {
					continue;
				}
				self::assertStringContainsString( 'id="' . $id . '-help"', $page, "help element of $tab.$key" );
				$generic = sprintf( 'Error: %s: check the value.', $ui['label'] );
				$case    = self::invalid_input( $ui, $def );
				if ( null === $case ) {
					// The control offers only valid values; a server-side error still renders at the field.
					$html = SettingsForm::field(
						$tab,
						$key,
						$def['default'] ?? null,
						array(
							'rule'   => 'choice',
							'params' => array(),
						)
					);
					self::assertStringContainsString( 'id="' . $id . '-error"', $html, "forced error of $tab.$key" );
					self::assertMatchesRegularExpression( '/aria-describedby="[^"]*' . preg_quote( $id, '/' ) . '-error"[^>]*aria-invalid="true"/', $html, "forced error of $tab.$key" );
					++$checked['forced'];
					continue;
				}
				$out = SettingsScreen::process_save( $this->post( $tab, $key, $case['value'] ), $this->admin );
				self::assertSame( 'invalid', $out['result'], "invalid value of $tab.$key is rejected" );
				$html = SettingsScreen::html(
					array(
						'tab'         => $tab,
						'ccap_result' => 'invalid',
					),
					$this->admin
				);
				self::assertMatchesRegularExpression( '/<p id="' . preg_quote( $id, '/' ) . '-error" class="ccap-field-error"><strong>Error: [^<]+<\/strong>/', $html, "error text of $tab.$key" );
				preg_match( '/<p id="' . preg_quote( $id, '/' ) . '-error" class="ccap-field-error"><strong>([^<]+)<\/strong>/', $html, $m );
				$message = html_entity_decode( $m[1] ?? '', ENT_QUOTES );
				if ( ! $case['tamper'] ) {
					self::assertNotSame( $generic, $message, "specific error message of $tab.$key" );
				}
				self::assertDoesNotMatchRegularExpression( '/ to \.$/', $message, "no empty limit in the message of $tab.$key" );
				$control = in_array( $ui['element'], array( 'radio', 'set', 'nullable_number' ), true ) ? $id . '-[a-z0-9]+' : preg_quote( $id, '/' );
				self::assertMatchesRegularExpression( '/id="' . $control . '"[^>]*aria-describedby="[^"]*' . preg_quote( $id, '/' ) . '-error"[^>]*aria-invalid="true"/', $html, "aria-invalid and aria-describedby of $tab.$key" );
				self::assertStringContainsString( 'href="#' . $id . '"', $html, "error summary links to $tab.$key" );
				self::assertSame( array(), A11yDom::fragment_problems( $html ), "DOM problems in the error state of $tab.$key" );
				++$checked[ $case['tamper'] ? 'tamper' : 'typed' ];
			}
		}
		self::assertGreaterThan( 40, $checked['typed'] + $checked['tamper'] + $checked['forced'] );
		self::assertGreaterThan( 25, $checked['typed'] );
	}

	/**
	 * An invalid part of an object field (here `hardening.login_limits.account`)
	 * names its own limits and marks only that part, not its siblings.
	 */
	public function test_object_part_error_is_marked_at_the_part(): void {
		$post = array(
			'tab'      => 'hardening',
			'_wpnonce' => wp_create_nonce( 'ccap_settings_save_hardening' ),
			'_rev'     => Settings::rev( 'hardening' ),
			'_fields'  => array( 'login_limits.ip', 'login_limits.account_ip', 'login_limits.account' ),
			'ccap'     => array(
				'login_limits' => array(
					'ip'         => '10',
					'account_ip' => '5',
					'account'    => '5000',
				),
			),
		);
		self::assertSame( 'invalid', SettingsScreen::process_save( $post, $this->admin )['result'] );
		$html    = SettingsScreen::html(
			array(
				'tab'         => 'hardening',
				'ccap_result' => 'invalid',
			),
			$this->admin
		);
		$account = SettingsForm::id( 'hardening', 'login_limits.account' );
		self::assertStringContainsString( 'id="' . $account . '-error"', $html );
		self::assertStringContainsString( 'enter a whole number from 1 to 1000.', $html );
		self::assertStringNotContainsString( 'id="' . SettingsForm::id( 'hardening', 'login_limits.ip' ) . '-error"', $html, 'valid sibling stays unmarked' );
		self::assertStringContainsString( 'href="#' . $account . '"', $html, 'summary links to the invalid part' );
	}

	/**
	 * DOM checks (A11yDom) of the settings surfaces outside the tabs: setup
	 * assistant steps, mail texts, locks, backup and the audit settings view.
	 */
	public function test_settings_surfaces_pass_the_dom_checks(): void {
		foreach ( SetupState::STEPS as $step ) {
			self::assertSame( array(), A11yDom::fragment_problems( SetupWizard::html( array( 'step' => $step ), $this->admin ) ), "setup step $step" );
		}
		self::assertSame( array(), A11yDom::fragment_problems( MailTextsScreen::html( array(), $this->admin ) ), 'mail texts' );
		self::assertSame(
			array(),
			A11yDom::fragment_problems(
				MailTextsScreen::html(
					array(
						'type'   => 'access_granted',
						'locale' => 'en_US',
					),
					$this->admin
				)
			),
			'mail text editor'
		);
		$locks = SettingLocksScreen::html( array(), $this->admin );
		self::assertSame( array(), A11yDom::fragment_problems( $locks ), 'locks' );
		self::assertMatchesRegularExpression( '/<div class="ccap-table-scroll" role="region" tabindex="0" aria-label="[^"]+"><table/', $locks, 'wide table scrolls in its own named region' );
		self::assertSame( array(), A11yDom::fragment_problems( SettingsBackup::html( array(), $this->admin ) ), 'backup' );
		$audit = AuditScreen::html( array( 'area' => 'settings' ) );
		self::assertSame( array(), A11yDom::fragment_problems( $audit ), 'audit settings view' );
		self::assertDoesNotMatchRegularExpression( '/<input id="ccap-audit-/', $audit, 'filter inputs carry a type (admin styles, target size)' );
	}
}
