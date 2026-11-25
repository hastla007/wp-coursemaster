<?php
/**
 * SET-CC-06: links to the legal texts (`general.legal_links`) – validation,
 * "Legal review" marking, privacy fallback, footer of the learning area and
 * of mails, mail placeholders, export/import and CLI.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\MailTextsScreen;
use CourseCaptain\Admin\SettingsForm;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Notification\MailTemplates;
use CourseCaptain\Notification\NotificationTypes;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\LegalLinks;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsCommand;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Settings\SettingsTransfer;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables.
 *
 * @group SET-CC
 * @group SET-CC-06
 */
final class SettingsLegalLinksTest extends SchemaTestCase {

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
		delete_option( 'wp_page_for_privacy_policy' );
		delete_transient( SettingsTransfer::RATE_PREFIX . $this->admin );
		delete_transient( MailTemplates::RATE_PREFIX . $this->admin );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Cleanup.
	 */
	public function tear_down(): void {
		delete_option( 'wp_page_for_privacy_policy' );
		parent::tear_down();
	}

	/**
	 * POST of the general tab with legal links.
	 *
	 * @param array<string, string> $links Sub-key => raw value.
	 * @return array<string, mixed>
	 */
	private function post( array $links ): array {
		return array(
			'tab'      => 'general',
			'_wpnonce' => wp_create_nonce( 'ccap_settings_save_general' ),
			'_rev'     => Settings::rev( 'general' ),
			'_fields'  => array_map( static fn( string $sub ): string => 'legal_links.' . $sub, array_keys( $links ) ),
			'ccap'     => array( 'legal_links' => $links ),
		);
	}

	/**
	 * Save legal links directly.
	 *
	 * @param array<string, string> $links Links.
	 */
	private function links( array $links ): void {
		$value = $links + array_fill_keys( SettingsSchema::LEGAL_LINKS, '' );
		Settings::save( Actor::user( $this->admin ), 'general', array( 'legal_links' => $value ), array( 'legal_links' ), Settings::rev( 'general' ) );
	}

	/**
	 * Published privacy page of WordPress.
	 *
	 * @return int
	 */
	private function privacy_page(): int {
		$page = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Privacy',
			)
		);
		update_option( 'wp_page_for_privacy_policy', $page );
		return $page;
	}

	/**
	 * URL type: only absolute http/https addresses with a host; empty is allowed
	 * for every link (no field is required); field-level error with its own text;
	 * "Legal review" marking at every field.
	 */
	public function test_validation_and_legal_review_marking(): void {
		$def = array( 'type' => 'url' );
		foreach ( array( 'https://example.org/impressum', 'http://example.org', 'https://example.org/agb?x=1#teil-2', '  https://example.org/a  ' ) as $ok ) {
			$check = SettingsSchema::validate( $def, $ok );
			self::assertTrue( $check['ok'], $ok );
			self::assertSame( trim( $ok ), $check['value'] );
		}
		self::assertSame( '', SettingsSchema::validate( $def, '' )['value'] );
		foreach ( array( 'javascript:alert(1)', 'ftp://example.org/x', '/impressum', 'example.org/impressum', 'https://exa mple.org', 'https://user:pass@example.org/', 'data:text/html,x', 'https://', 'https://example.org/"onmouseover=x', 'https://example.org/' . str_repeat( 'a', SettingsSchema::URL_MAX ), 42 ) as $bad ) {
			self::assertFalse( SettingsSchema::validate( $def, $bad )['ok'], (string) wp_json_encode( $bad ) );
		}
		self::assertContains( 'url', SettingsSchema::TYPES );

		// Nothing is required: all four empty saves.
		$out = SettingsScreen::process_save( $this->post( array_fill_keys( SettingsSchema::LEGAL_LINKS, '' ) ), $this->admin );
		self::assertNotSame( 'invalid', $out['result'] );

		$out = SettingsScreen::process_save(
			$this->post(
				array(
					'imprint' => 'https://example.org/impressum',
					'terms'   => 'javascript:alert(1)',
				)
			),
			$this->admin
		);
		self::assertSame( 'invalid', $out['result'] );
		self::assertSame( '', Settings::get( 'general.legal_links' )['imprint'], 'all or nothing' );
		$html = SettingsScreen::html(
			array(
				'tab'         => 'general',
				'ccap_result' => 'invalid',
			),
			$this->admin
		);
		$id   = SettingsForm::id( 'general', 'legal_links.terms' );
		self::assertStringContainsString( 'Error: Terms and conditions (address): enter a complete address starting with https:// or http://', $html );
		self::assertMatchesRegularExpression( '/id="' . preg_quote( $id, '/' ) . '"[^>]*aria-describedby="[^"]*' . preg_quote( $id, '/' ) . '-error"[^>]*aria-invalid="true"/', $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );

		$page = SettingsScreen::html( array( 'tab' => 'general' ), $this->admin );
		foreach ( SettingsSchema::LEGAL_LINKS as $sub ) {
			$id = SettingsForm::id( 'general', 'legal_links.' . $sub );
			self::assertMatchesRegularExpression( '/<input type="url"[^>]*id="' . preg_quote( $id, '/' ) . '"[^>]*aria-describedby="[^"]*' . preg_quote( $id, '/' ) . '-legal/', $page, "url input with legal marking: $sub" );
			self::assertStringContainsString( 'id="' . $id . '-legal" class="description ccap-legal-review"><strong>Legal review:</strong>', $page );
		}
		self::assertStringContainsString( 'privacy page of WordPress', $page, 'default of the privacy link in the meta line' );
		self::assertSame( array(), A11yDom::fragment_problems( $page ) );
	}

	/**
	 * Privacy link: own address wins, else the WordPress privacy page, else none.
	 */
	public function test_privacy_fallback(): void {
		self::assertSame( '', LegalLinks::urls()['privacy'] );
		self::assertSame( array(), LegalLinks::present() );
		$page = $this->privacy_page();
		self::assertSame( (string) get_permalink( $page ), LegalLinks::urls()['privacy'] );
		self::assertSame( array( 'privacy' ), array_keys( LegalLinks::present() ) );
		$this->links( array( 'privacy' => 'https://example.org/datenschutz' ) );
		self::assertSame( 'https://example.org/datenschutz', LegalLinks::urls()['privacy'] );
		self::assertSame( 'https://example.org/datenschutz', MailTemplates::site_vars()['privacy_policy_url'] );
	}

	/**
	 * Footer of the learning area: only set links, as a labelled navigation, together with the accessibility statement.
	 */
	public function test_learning_area_footer(): void {
		$actor = Actor::user( $this->admin );
		self::assertStringNotContainsString( '<footer class="ccap-footer"', LearningArea::page( $actor, '' ), 'no link set: no footer' );
		$this->links(
			array(
				'imprint' => 'https://example.org/impressum',
				'terms'   => 'https://example.org/agb',
			)
		);
		$doc = LearningArea::page( $actor, '' );
		self::assertStringContainsString( '<footer class="ccap-footer"><nav aria-label="Legal information"><ul class="ccap-footer-links"><li><a href="https://example.org/impressum">Imprint</a></li><li><a href="https://example.org/agb">Terms and conditions</a></li></ul></nav></footer>', $doc );
		self::assertStringNotContainsString( 'Right of withdrawal', $doc );
		self::assertStringNotContainsString( 'Privacy policy', $doc );
		self::assertSame( array(), A11yDom::fragment_problems( $doc ) );

		$this->privacy_page();
		$statement = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		Settings::save( $actor, 'display', array( 'accessibility_page_id' => $statement ), array( 'accessibility_page_id' ), Settings::rev( 'display' ) );
		$doc = LearningArea::page( $actor, '' );
		self::assertMatchesRegularExpression( '#>Imprint</a></li><li><a [^>]+>Terms and conditions</a></li><li><a [^>]+>Privacy policy</a></li><li><a href="' . preg_quote( esc_url( (string) get_permalink( $statement ) ), '#' ) . '">Accessibility statement</a></li></ul>#', $doc );
	}

	/**
	 * Mails: placeholders for every type, editor preview resolves them, footer with set links only.
	 */
	public function test_mail_placeholders_and_footer(): void {
		foreach ( NotificationTypes::all() as $type ) {
			foreach ( array( 'imprint_url', 'terms_url', 'withdrawal_url', 'privacy_policy_url' ) as $var ) {
				self::assertContains( $var, NotificationTypes::variables( $type ), "$type: $var" );
				self::assertContains( $var, NotificationTypes::URL_VARIABLES );
			}
		}
		$admin = Actor::user( $this->admin );
		$draft = array(
			'subject'   => 'Hallo {{site_name}}',
			'body_html' => '<p>Impressum: {{imprint_url}} AGB: {{terms_url}} Widerruf: {{withdrawal_url}}</p>',
			'body_text' => '',
		);
		$empty = MailTemplates::preview( $admin, 'access_granted', 'en_US', $draft );
		self::assertStringNotContainsString( '{{', $empty['html'] . $empty['text'], 'unset links resolve to empty' );
		self::assertStringNotContainsString( 'Imprint', $empty['text'], 'no footer line without links' );

		$this->links(
			array(
				'imprint'    => 'https://example.org/impressum',
				'withdrawal' => 'https://example.org/widerruf',
			)
		);
		$preview = MailTemplates::preview( $admin, 'access_granted', 'en_US', $draft );
		self::assertStringContainsString( 'Impressum: https://example.org/impressum AGB:  Widerruf: https://example.org/widerruf', $preview['html'] );
		self::assertStringContainsString( '<p><a href="https://example.org/impressum">Imprint</a> · <a href="https://example.org/widerruf">Right of withdrawal</a></p>', $preview['html'] );
		self::assertStringContainsString( "Imprint: https://example.org/impressum\nRight of withdrawal: https://example.org/widerruf", $preview['text'] );
		self::assertStringNotContainsString( 'Terms and conditions', $preview['text'] );

		$builtin = MailTemplates::preview( $admin, 'seat_invitation', 'en_US' );
		self::assertStringContainsString( '>Imprint</a>', $builtin['html'], 'built-in texts get the footer as well' );

		$this->privacy_page();
		self::assertStringContainsString( 'Privacy policy: ', MailTemplates::preview( $admin, 'test_mail', 'en_US' )['text'] );

		// The editor lists the placeholders and explains where they come from.
		$editor = MailTextsScreen::html(
			array(
				'type'   => 'access_granted',
				'locale' => 'en_US',
			),
			$this->admin
		);
		self::assertStringContainsString( '<code>{{imprint_url}}</code>', $editor );
		self::assertStringContainsString( 'Settings › General › Legal texts', $editor );
	}

	/**
	 * Export/import and CLI use the register: same validation, same audit.
	 */
	public function test_export_import_and_cli(): void {
		$admin = Actor::user( $this->admin );
		$this->links( array( 'imprint' => 'https://example.org/impressum' ) );
		$file = SettingsTransfer::export( $admin );
		self::assertSame( 'https://example.org/impressum', $file['tabs']['general']['values']['legal_links']['imprint'] );

		$file['tabs']['general']['values']['legal_links']['terms'] = 'https://example.org/agb';
		$data    = SettingsTransfer::parse( (string) wp_json_encode( $file ) );
		$preview = SettingsTransfer::import( $admin, $data );
		self::assertContains( 'legal_links', array_column( $preview['tabs']['general']['changed'], 'field' ) );
		SettingsTransfer::import(
			$admin,
			$data,
			array(
				'apply' => true,
				'tabs'  => array( 'general' ),
			)
		);
		self::assertSame( 'https://example.org/agb', Settings::get( 'general.legal_links' )['terms'] );

		$file['tabs']['general']['values']['legal_links']['terms'] = 'javascript:alert(1)';
		delete_transient( SettingsTransfer::RATE_PREFIX . $this->admin );
		$bad = SettingsTransfer::import( $admin, SettingsTransfer::parse( (string) wp_json_encode( $file ) ) );
		self::assertArrayHasKey( 'legal_links', $bad['tabs']['general']['errors'] );

		$cli = SettingsCommand::execute( 'set', array( 'general.legal_links', '{"imprint":"https://example.org/i","terms":"","withdrawal":"https://example.org/w","privacy":""}' ), array(), Actor::cli() );
		self::assertSame( 0, $cli['exit'], implode( "\n", $cli['lines'] ) );
		self::assertSame( 'https://example.org/w', Settings::get( 'general.legal_links' )['withdrawal'] );
		$cli = SettingsCommand::execute( 'set', array( 'general.legal_links', '{"imprint":"ftp://example.org/i"}' ), array(), Actor::cli() );
		self::assertSame( 1, $cli['exit'] );
		self::assertStringContainsString( 'ccap_validation_failed general.legal_links', $cli['lines'][0] );
		self::assertSame( 'https://example.org/i', Settings::get( 'general.legal_links' )['imprint'], 'nothing saved' );
	}
}
