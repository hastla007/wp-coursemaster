<?php
/**
 * SET-CC slice "mail-felder": mail text editor (SET-CC-04), test mode in
 * the emails tab (SET-CC-23), assignment fields (SET-CC-09), CSP
 * (SET-CC-10), branding (SET-CC-16), content accessibility (SET-CC-18)
 * and the log level (SET-CC-22).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Admin\MailTextsScreen;
use CourseCaptain\Admin\SettingsForm;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Notification\MailTemplates;
use CourseCaptain\Ops\Log;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Publish\CourseValidator;
use CourseCaptain\Security\Csp;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsRules;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables; mails go into a sink via `pre_wp_mail`.
 *
 * @group SET-CC
 */
final class SettingsMailFieldsTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Caught mails.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $sink = array();

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_email' => 'boss@example.org',
			)
		);
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			delete_option( Settings::OPTION_PREFIX . $tab );
		}
		delete_option( MailTemplates::OPTION_PREFIX . 'access_granted' );
		delete_option( Log::SINCE_OPTION );
		delete_transient( MailTemplates::RATE_PREFIX . $this->admin );
		delete_transient( MailTextsScreen::DRAFT . $this->admin );
		delete_transient( MailTextsScreen::IMPORT . $this->admin );
		wp_set_current_user( $this->admin );
		$this->sink = array();
		add_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10, 2 );
	}

	/**
	 * Cleanup.
	 */
	public function tear_down(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'catch_mail' ), 10 );
		remove_all_filters( 'course_captain_outbound_mode' );
		remove_all_filters( 'course_captain_notification_hold' );
		Csp::stop();
		parent::tear_down();
	}

	/**
	 * Mail sink.
	 *
	 * @param mixed                $value Short-circuit value.
	 * @param array<string, mixed> $atts  wp_mail attributes.
	 * @return bool
	 */
	public function catch_mail( $value, $atts ): bool {
		unset( $value );
		$this->sink[] = (array) $atts;
		return true;
	}

	/**
	 * Outbound mode for the test.
	 *
	 * @param string $mode Mode.
	 * @return void
	 */
	private function outbound( string $mode ): void {
		remove_all_filters( 'course_captain_outbound_mode' );
		add_filter(
			'course_captain_outbound_mode',
			static fn(): array => array(
				'mode'          => $mode,
				'catch_address' => 'catch' === $mode ? 'catch@example.org' : '',
				'warning'       => '',
			)
		);
	}

	/**
	 * POST of the mail text editor.
	 *
	 * @param array<string, mixed> $fields Fields.
	 * @return array<string, mixed>
	 */
	private function mail_post( array $fields ): array {
		return $fields + array(
			'_wpnonce' => wp_create_nonce( MailTextsScreen::ACTION ),
			'type'     => 'access_granted',
			'locale'   => 'en_US',
		);
	}

	/**
	 * POST of a settings tab.
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $values `ccap` values.
	 * @return array<string, mixed>
	 */
	private function tab_post( string $tab, array $values ): array {
		$fields = array();
		foreach ( $values as $field => $value ) {
			if ( is_array( $value ) && 'assignment_file_types' !== $field ) {
				foreach ( array_keys( $value ) as $sub ) {
					$fields[] = $field . '.' . $sub;
				}
				continue;
			}
			$fields[] = $field;
		}
		return array(
			'tab'      => $tab,
			'_wpnonce' => wp_create_nonce( 'ccap_settings_save_' . $tab ),
			'_rev'     => Settings::rev( $tab ),
			'_fields'  => $fields,
			'ccap'     => $values,
		);
	}

	/**
	 * Audit actions written so far.
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
	 * SET-CC-04: save with placeholder validation, preview, test mail, reset.
	 *
	 * @group SET-CC-04
	 */
	public function test_SET_CC_04_editor_save_preview_test_reset(): void {
		// Unknown placeholder: nothing saved, field-near error names the allowed placeholders.
		$out = MailTextsScreen::process(
			$this->mail_post(
				array(
					'do'        => 'save',
					'subject'   => 'Hi {{course_title}}',
					'body_html' => '<p>Your score {{score}}</p>',
				)
			),
			'',
			$this->admin
		);
		self::assertSame( 'invalid', $out['result'] );
		self::assertSame( array(), MailTemplates::stored( 'access_granted' ) );
		$html = MailTextsScreen::html(
			array(
				'type'        => 'access_granted',
				'locale'      => 'en_US',
				'ccap_result' => 'invalid',
			),
			$this->admin
		);
		self::assertStringContainsString( 'role="alert"', $html );
		self::assertStringContainsString( 'unknown placeholder {{score}}', $html );
		self::assertStringContainsString( '{{course_url}}', $html );
		self::assertMatchesRegularExpression( '/id="ccap-mail-body-html"[^>]*aria-describedby="[^"]*ccap-mail-body-html-error"[^>]*aria-invalid="true"/', $html );
		self::assertStringContainsString( 'Your score {{score}}', $html, 'input kept' );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );

		// Preview renders the draft with sample data and saves nothing.
		$draft = array(
			'subject'   => 'Welcome to {{course_title}}',
			'body_html' => '<p>Hello {{recipient_display_name}}, open {{course_url}}</p><script>x()</script>',
		);
		self::assertSame( 'preview', MailTextsScreen::process( $this->mail_post( array( 'do' => 'preview' ) + $draft ), '', $this->admin )['result'] );
		self::assertSame( array(), MailTemplates::stored( 'access_granted' ), 'preview never saves' );
		$html = MailTextsScreen::html(
			array(
				'type'        => 'access_granted',
				'locale'      => 'en_US',
				'ccap_result' => 'preview',
			),
			$this->admin
		);
		self::assertStringContainsString( 'Welcome to Sample course', $html );
		self::assertStringContainsString( 'sandbox=""', $html );
		self::assertStringNotContainsString( 'script&gt;', $html, 'kses removes script tags' );
		self::assertStringNotContainsString( '<script', $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );

		// Test mail of the draft to the own address, with [Test] prefix; catch mode redirects.
		$this->outbound( 'on' );
		self::assertSame( 'test_sent', MailTextsScreen::process( $this->mail_post( array( 'do' => 'test' ) + $draft ), '', $this->admin )['result'] );
		self::assertCount( 1, $this->sink );
		self::assertSame( 'boss@example.org', $this->sink[0]['to'] );
		self::assertSame( '[Test] Welcome to Sample course', $this->sink[0]['subject'] );
		$this->outbound( 'catch' );
		self::assertSame( 'test_caught', MailTextsScreen::process( $this->mail_post( array( 'do' => 'test' ) + $draft ), '', $this->admin )['result'] );
		self::assertSame( 'catch@example.org', $this->sink[1]['to'] );
		$this->outbound( 'off' );
		self::assertSame( 'test_suppressed', MailTextsScreen::process( $this->mail_post( array( 'do' => 'test' ) + $draft ), '', $this->admin )['result'] );
		self::assertCount( 2, $this->sink, 'outbound off sends nothing' );
		self::assertSame( array(), MailTemplates::stored( 'access_granted' ), 'test mail never saves' );

		// Save, unchanged, reset with confirmation.
		self::assertSame( 'saved', MailTextsScreen::process( $this->mail_post( array( 'do' => 'save' ) + $draft ), '', $this->admin )['result'] );
		self::assertSame( 'Welcome to {{course_title}}', MailTemplates::stored( 'access_granted' )['en_US']['subject'] );
		self::assertSame( 'unchanged', MailTextsScreen::process( $this->mail_post( array( 'do' => 'save' ) + $draft ), '', $this->admin )['result'] );
		self::assertSame( 'reset_confirm', MailTextsScreen::process( $this->mail_post( array( 'do' => 'reset' ) ), '', $this->admin )['result'] );
		self::assertArrayHasKey( 'en_US', MailTemplates::stored( 'access_granted' ), 'reset needs the confirmation' );
		$confirm = MailTextsScreen::html(
			array(
				'type'        => 'access_granted',
				'locale'      => 'en_US',
				'ccap_result' => 'reset_confirm',
			),
			$this->admin
		);
		self::assertStringContainsString( 'name="confirm" value="1"', $confirm );
		self::assertSame( array(), A11yDom::fragment_problems( $confirm ) );
		self::assertSame(
			'reset',
			MailTextsScreen::process(
				$this->mail_post(
					array(
						'do'      => 'reset',
						'confirm' => '1',
					)
				),
				'',
				$this->admin
			)['result']
		);
		self::assertSame( array(), MailTemplates::stored( 'access_granted' ) );
		self::assertCount( 1, $this->audits( 'mail_template_reset' ) );
		self::assertSame( 'en_US', $this->audits( 'mail_template_reset' )[0]['locale'] );

		// Rights and nonce.
		$editor = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		self::assertSame( 'ccap_forbidden', MailTextsScreen::process( $this->mail_post( array( 'do' => 'save' ) + $draft ), '', $editor )['result'] );
		self::assertSame( 'ccap_invalid_nonce', MailTextsScreen::process( array( '_wpnonce' => 'x' ) + $this->mail_post( array( 'do' => 'save' ) + $draft ), '', $this->admin )['result'] );
		self::assertStringContainsString( 'not allowed', MailTextsScreen::html( array(), $editor ) );
	}

	/**
	 * SET-CC-04: export/import round trip with preview.
	 *
	 * @group SET-CC-04
	 */
	public function test_SET_CC_04_export_import(): void {
		$actor = Actor::user( $this->admin );
		MailTemplates::save( $actor, 'access_granted', 'en_US', 'Own {{course_title}}', '<p>Own text {{course_url}}</p>' );
		$export = MailTemplates::export( $actor );
		self::assertSame( MailTemplates::FORMAT, $export['format'] );
		$json = (string) wp_json_encode( $export );
		self::assertStringNotContainsString( 'boss@example.org', $json, 'no personal data' );

		MailTemplates::reset( $actor, 'access_granted', 'en_US' );
		$data                                      = json_decode( $json, true );
		$data['templates']['seat_claimed']         = array(
			'en_US' => array(
				'subject'   => 'Bad {{claim_url}}',
				'body_html' => '<p>x</p>',
			),
		);
		$data['templates']['unknown_type']         = array( 'en_US' => $data['templates']['access_granted']['en_US'] );
		$data['templates']['access_expired']['xx'] = $data['templates']['access_granted']['en_US'];

		$result = MailTextsScreen::process( $this->mail_post( array( 'do' => 'import' ) ), (string) wp_json_encode( $data ), $this->admin );
		self::assertSame( 'import_preview', $result['result'] );
		self::assertSame( array(), MailTemplates::stored( 'access_granted' ), 'preview writes nothing' );
		$html = MailTextsScreen::html( array( 'ccap_result' => 'import_preview' ), $this->admin );
		self::assertStringContainsString( 'skipped: invalid text or placeholder', $html );
		self::assertStringContainsString( 'skipped: unknown message', $html );
		self::assertStringContainsString( 'skipped: language not installed', $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );

		self::assertSame( 'imported', MailTextsScreen::process( $this->mail_post( array( 'do' => 'import_apply' ) ), '', $this->admin )['result'] );
		self::assertSame( 'Own {{course_title}}', MailTemplates::stored( 'access_granted' )['en_US']['subject'] );
		self::assertSame( array(), MailTemplates::stored( 'seat_claimed' ), 'invalid entry skipped' );
		self::assertSame( 1, (int) ( $this->audits( 'mail_templates_imported' )[0]['applied'] ?? 0 ) );
		self::assertSame( 'ccap_confirmation_expired', MailTextsScreen::process( $this->mail_post( array( 'do' => 'import_apply' ) ), '', $this->admin )['result'] );
		self::assertSame( 'import_invalid', MailTextsScreen::process( $this->mail_post( array( 'do' => 'import' ) ), '{"format":"other"}', $this->admin )['result'] );
	}

	/**
	 * SET-CC-23 / SET-CC-09: read-only test mode and assignment fields.
	 *
	 * @group SET-CC-23
	 * @group SET-CC-09
	 */
	public function test_SET_CC_23_09_test_mode_and_assignment_fields(): void {
		$this->outbound( 'catch' );
		$html = SettingsScreen::html( array( 'tab' => 'emails' ), $this->admin );
		self::assertStringContainsString( 'id="ccap-outbound-mode"', $html );
		self::assertStringContainsString( 'every mail goes only to catch@example.org', $html );
		self::assertStringContainsString( 'COURSE_CAPTAIN_MAIL_CATCH_ADDRESS', $html );
		self::assertStringContainsString( MailTextsScreen::SLUG, $html, 'link to the mail texts' );
		self::assertSame( 0, substr_count( $html, 'name="ccap[outbound' ), 'read only, no field' );
		$this->outbound( 'off' );
		self::assertStringContainsString( 'no mails are sent at all', SettingsScreen::html( array( 'tab' => 'emails' ), $this->admin ) );

		$html = SettingsScreen::html( array( 'tab' => 'learning' ), $this->admin );
		foreach ( array( 'assignment_file_types', 'assignment_max_file_mb', 'assignment_max_files' ) as $key ) {
			self::assertStringContainsString( 'id="' . SettingsForm::id( 'learning', $key ) . '-help"', $html, $key );
		}
		$out = SettingsScreen::process_save(
			$this->tab_post(
				'learning',
				array(
					'assignment_file_types'  => array( 'pdf', 'png' ),
					'assignment_max_file_mb' => '10',
					'assignment_max_files'   => '3',
				)
			),
			$this->admin
		);
		self::assertSame( 'saved', $out['result'] );
		self::assertSame( array( 'pdf', 'png' ), Settings::get( 'learning.assignment_file_types' ) );
		self::assertSame( 10, Settings::get( 'learning.assignment_max_file_mb' ) );
		$out = SettingsScreen::process_save( $this->tab_post( 'learning', array( 'assignment_max_files' => '21' ) ), $this->admin );
		self::assertSame( 'invalid', $out['result'] );
		self::assertSame( 3, Settings::get( 'learning.assignment_max_files' ) );
	}

	/**
	 * SET-CC-10: CSP header per mode, nonce on every WordPress script.
	 *
	 * @group SET-CC-10
	 */
	public function test_SET_CC_10_csp(): void {
		list( $name, $value ) = Csp::header( 'enforce', 'abc', array( 'player.vimeo.com', 'bad host;', 'x' ) );
		self::assertSame( 'Content-Security-Policy', $name );
		self::assertStringContainsString( "script-src 'nonce-abc' 'strict-dynamic'", $value );
		self::assertStringContainsString( "object-src 'none'", $value );
		self::assertStringContainsString( "frame-src 'self' https://player.vimeo.com", $value );
		self::assertStringNotContainsString( 'bad host', $value, 'invalid hosts are dropped' );
		self::assertSame( 'Content-Security-Policy-Report-Only', Csp::header( 'report_only', 'abc', array() )[0] );
		self::assertSame( array( 'enforce', 'report_only' ), SettingsSchema::fields()['privacy']['csp_mode']['values'], 'E-224: no "off" value' );

		add_filter( 'course_captain_csp_frame_hosts', static fn( array $hosts ): array => array_merge( $hosts, array( 'video.example.net' ) ) );
		$header = Csp::start();
		remove_all_filters( 'course_captain_csp_frame_hosts' );
		self::assertSame( 'Content-Security-Policy', $header[0], 'default enforce' );
		self::assertStringContainsString( 'https://www.youtube-nocookie.com', $header[1] );
		self::assertStringContainsString( 'https://video.example.net', $header[1] );
		$nonce = Csp::nonce();
		self::assertNotSame( '', $nonce );
		self::assertStringContainsString( 'nonce="' . $nonce . '"', wp_get_inline_script_tag( 'var a = 1;' ) );
		self::assertStringContainsString( 'nonce="' . $nonce . '"', wp_get_script_tag( array( 'src' => 'https://example.org/a.js' ) ) );

		// The learning area including the exam room script prints scripts only with the nonce.
		$page = LearningArea::page( Actor::user( $this->admin ), '' );
		self::assertSame( 0, preg_match( '/<script(?![^>]*nonce=)[^>]*>/', $page ), 'no script without nonce' );
		Csp::stop();
		self::assertStringNotContainsString( 'nonce=', wp_get_inline_script_tag( 'var a = 1;' ) );

		Settings::save( Actor::cli(), 'privacy', array( 'csp_mode' => 'report_only' ), array( 'csp_mode' ), Settings::rev( 'privacy' ) );
		self::assertSame( 'Content-Security-Policy-Report-Only', Csp::start()[0] );
	}

	/**
	 * SET-CC-16: logo, accent colour with contrast check, button labels.
	 *
	 * @group SET-CC-16
	 */
	public function test_SET_CC_16_branding(): void {
		self::assertGreaterThan( 20.9, SettingsRules::contrast( '#000000', '#ffffff' ) );
		self::assertLessThan( 4.5, SettingsRules::contrast( '#ffff00', '#ffffff' ) );

		// Too light: rejected with a field-near message.
		$out = SettingsScreen::process_save( $this->tab_post( 'display', array( 'accent_color' => '#FFFF00' ) ), $this->admin );
		self::assertSame( 'invalid', $out['result'] );
		self::assertSame( '', Settings::get( 'display.accent_color' ) );
		$html = SettingsScreen::html(
			array(
				'tab'         => 'display',
				'ccap_result' => 'invalid',
			),
			$this->admin
		);
		self::assertStringContainsString( 'Error: the colour #FFFF00 is too light', $html );
		self::assertSame( 'invalid', SettingsScreen::process_save( $this->tab_post( 'display', array( 'accent_color' => 'blue' ) ), $this->admin )['result'] );

		$image = (int) self::factory()->attachment->create_object(
			'logo.png',
			0,
			array(
				'post_mime_type' => 'image/png',
				'post_type'      => 'attachment',
			)
		);
		$page  = (int) self::factory()->post->create( array( 'post_type' => 'page' ) );
		self::assertSame( 'invalid', SettingsScreen::process_save( $this->tab_post( 'display', array( 'logo_id' => (string) $page ) ), $this->admin )['result'], 'not an image' );
		$out = SettingsScreen::process_save(
			$this->tab_post(
				'display',
				array(
					'accent_color'  => '#1D4ED8',
					'logo_id'       => (string) $image,
					'button_labels' => array(
						'enroll'      => 'Join now',
						'complete'    => '',
						'start_exam'  => 'Begin',
						'submit_exam' => '',
					),
				)
			),
			$this->admin
		);
		self::assertSame( 'saved', $out['result'] );
		self::assertSame( '#1d4ed8', Settings::get( 'display.accent_color' ), 'normalized' );
		self::assertStringContainsString( '--ccap-accent:#1d4ed8', LearningArea::brand_css() );
		self::assertSame( 'Join now', LearningArea::button_label( 'enroll', 'Enroll for free' ) );
		self::assertSame( 'Mark as completed', LearningArea::button_label( 'complete', 'Mark as completed' ) );
		self::assertSame( 'invalid', SettingsScreen::process_save( $this->tab_post( 'display', array( 'button_labels' => array( 'enroll' => str_repeat( 'x', 41 ) ) ) ), $this->admin )['result'] );
		self::assertFalse( SettingsSchema::fields()['display']['logo_id']['export'], 'media ID not exported' );
		self::assertArrayNotHasKey( 'logo_id', Settings::export( Actor::user( $this->admin ) )['tabs']['display']['values'] );

		$doc = LearningArea::page( Actor::user( $this->admin ), '' );
		self::assertStringContainsString( '--ccap-accent:#1d4ed8', $doc );
		self::assertSame( array(), A11yDom::document_problems( $doc ) );
	}

	/**
	 * SET-CC-18: transcript notice/blocker, statement link, transcript output.
	 *
	 * @group SET-CC-18
	 */
	public function test_SET_CC_18_transcripts(): void {
		$actor  = Actor::user( $this->admin );
		$course = (int) self::factory()->post->create(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'draft',
				'post_author' => $this->admin,
			)
		);
		$module = (int) self::factory()->post->create(
			array(
				'post_type'   => 'ccap_module',
				'post_author' => $this->admin,
			)
		);
		PolicyService::flush();
		CourseStructure::current()->assign_module( $actor, $course, $module );
		$lesson = (int) self::factory()->post->create(
			array(
				'post_type'   => 'ccap_lesson',
				'post_parent' => $module,
				'post_author' => $this->admin,
			)
		);
		ActivityService::current()->define( $actor, $lesson, 'video', array( 'url' => 'https://videos.example.test/v.mp4' ) );
		$issues = static fn(): array => array_values( array_filter( CourseValidator::validate( $course )['issues'], static fn( array $i ): bool => 'VAL-144' === $i['code'] ) );
		self::assertSame( 'notice', $issues()[0]['severity'] ?? null, 'reported without blocking' );
		Settings::save( Actor::cli(), 'display', array( 'require_transcripts' => true ), array( 'require_transcripts' ), Settings::rev( 'display' ) );
		self::assertSame( 'blocker', $issues()[0]['severity'] ?? null );
		ActivityService::current()->define(
			$actor,
			$lesson,
			'video',
			array(
				'url'            => 'https://videos.example.test/v.mp4',
				'transcript'     => 'Line one',
				'transcript_url' => 'https://videos.example.test/t.html',
			)
		);
		self::assertSame( array(), $issues() );
		self::assertNotSame( array(), \CourseCaptain\Activity\ActivityRegistry::instance()->validate_settings( 'video', array( 'transcript_url' => 'http://insecure.test/t' ) ) );

		$page = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$out  = SettingsScreen::process_save( $this->tab_post( 'display', array( 'accessibility_page_id' => (string) $page ) ), $this->admin );
		self::assertSame( 'saved', $out['result'] );
		$doc = LearningArea::page( $actor, '' );
		self::assertStringContainsString( '<footer class="ccap-footer"><a href="' . esc_url( (string) get_permalink( $page ) ) . '">Accessibility statement</a></footer>', $doc );
		$draft = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		self::assertSame( 'invalid', SettingsScreen::process_save( $this->tab_post( 'display', array( 'accessibility_page_id' => (string) $draft ) ), $this->admin )['result'] );
	}

	/**
	 * SET-CC-22: level filter, automatic fall-back after 7 days, retention.
	 *
	 * @group SET-CC-22
	 */
	public function test_SET_CC_22_log_level_and_retention(): void {
		self::assertSame( 'warning', Log::level() );
		self::assertTrue( Log::enabled( 'error' ) );
		self::assertFalse( Log::enabled( 'debug' ) );
		self::assertFalse( Log::write( 'debug', 'test.debug' ), 'debug off by default' );
		self::assertTrue( Log::write( 'warning', 'test.warning', array( 'who' => 'a@b.example' ) ) );
		$files = glob( Log::dir() . '/cc-error-*.log' );
		self::assertNotEmpty( $files );
		$content = (string) file_get_contents( (string) end( $files ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Own test file.
		self::assertStringContainsString( '"event":"test_warning"', $content );
		self::assertStringNotContainsString( 'a@b.example', $content, 'emails masked' );
		self::assertFileExists( dirname( Log::dir() ) . '/.htaccess', 'deny-all folder' );

		$out = SettingsScreen::process_save( $this->tab_post( 'advanced', array( 'log_level' => 'debug' ) ), $this->admin );
		self::assertSame( 'saved', $out['result'] );
		$since = (int) get_option( Log::SINCE_OPTION );
		self::assertGreaterThan( 0, $since );
		self::assertTrue( Log::write( 'debug', 'test.debug' ) );
		self::assertFalse( Log::auto_reset( $since + 6 * DAY_IN_SECONDS ) );
		self::assertSame( 'debug', Log::level() );
		self::assertTrue( Log::auto_reset( $since + 7 * DAY_IN_SECONDS ) );
		self::assertSame( 'warning', Log::level() );
		self::assertFalse( get_option( Log::SINCE_OPTION ) );
		$audit = $this->audits( 'settings_saved' );
		self::assertSame( 'auto_reset', end( $audit )['context'] ?? null );
		self::assertSame( 'never_env', SettingsSchema::fields()['advanced']['log_level']['export'] );

		// Retention: error files after error_log_days, debug files after debug_file_days.
		$dir = Log::dir();
		$now = time();
		$old = gmdate( 'Ymd', $now - 10 * DAY_IN_SECONDS );
		file_put_contents( $dir . '/cc-debug-' . $old . '.log', "x\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		file_put_contents( $dir . '/cc-error-' . $old . '.log', "x\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		self::assertSame( 1, Log::prune( $now ), 'debug 7 days, error 30 days' );
		self::assertFileExists( $dir . '/cc-error-' . $old . '.log' );
		// SET-CC-02 (register v4): the periods live in `privacy.retention`.
		$retention                   = (array) Settings::get( 'privacy.retention' );
		$retention['error_log_days'] = 5;
		Settings::save( Actor::cli(), 'privacy', array( 'retention' => $retention ), array( 'retention' ), Settings::rev( 'privacy' ) );
		self::assertSame( 1, Log::prune( $now ) );
		self::assertFileDoesNotExist( $dir . '/cc-error-' . $old . '.log' );
		self::assertSame( 'invalid', SettingsScreen::process_save( $this->tab_post( 'privacy', array( 'retention' => array( 'debug_file_days' => '31' ) ) ), $this->admin )['result'] );
		foreach ( (array) glob( $dir . '/cc-*.log' ) as $file ) {
			wp_delete_file( (string) $file );
		}
	}
}
