<?php
/**
 * RR-4 (R0-31 §2.3, PSD-018): `raw_answers_years_after_course_end` ≤
 * `exam_attempts_years`, otherwise a field error in the service, the
 * settings screen and WP-CLI; a notice when certificates disappear before
 * the exam attempts they rest on.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\RetentionPreview;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Privacy\RetentionPolicy;
use CourseCaptain\Settings\RiskConfirm;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsCommand;
use CourseCaptain\Settings\SettingsSchema;

/**
 * Real options.
 *
 * @group SET-CC
 * @group SET-CC-02
 * @group RR-4
 * @group PSD-018
 */
final class SettingsRetentionRr4Test extends SchemaTestCase {

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
		RetentionPreview::flush();
		PolicyService::flush();
		wp_set_current_user( $this->admin );
	}

	/**
	 * Clean up.
	 */
	public function tear_down(): void {
		RiskConfirm::drop( $this->admin );
		parent::tear_down();
	}

	/**
	 * Retention values with changes.
	 *
	 * @param array<string, int> $changes Changes.
	 * @return array<string, mixed>
	 */
	private static function retention( array $changes ): array {
		return array_merge( (array) Settings::get( 'privacy.retention' ), $changes );
	}

	/**
	 * PSD-018 in the service: longer raw answers than exam attempts are
	 * refused even with a reason (the rule error comes before E-245);
	 * shortening the attempts below the answers is refused too; equal is fine.
	 */
	public function test_PSD_018_service(): void {
		$actor  = Actor::cli();
		$reason = array( Settings::RETENTION_REASON_FIELD => 'Accreditation body rule' );
		$longer = self::retention( array( 'raw_answers_years_after_course_end' => 11 ) );
		self::assertSame( array( 'retention' => 'retention_order' ), Settings::check( $actor, 'privacy', array( 'retention' => $longer ), array( 'retention' ) )['errors'], 'without reason: the rule error wins' );
		self::assertSame( array( 'retention' => 'retention_order' ), Settings::check( $actor, 'privacy', array( 'retention' => $longer ) + $reason, array( 'retention' ) )['errors'], 'a reason does not help' );
		try {
			Settings::save( $actor, 'privacy', array( 'retention' => $longer ) + $reason, array( 'retention' ), Settings::rev( 'privacy' ) );
			self::fail( 'raw answers longer than exam attempts must be refused' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
			self::assertSame( array( 'retention' => 'retention_order' ), $e->data['fields'] );
		}
		self::assertSame( 2, Settings::get( 'privacy.retention' )['raw_answers_years_after_course_end'], 'nothing saved' );

		$shorter = self::retention( array( 'exam_attempts_years' => 1 ) );
		self::assertSame( array( 'retention' => 'retention_order' ), Settings::check( $actor, 'privacy', array( 'retention' => $shorter ), array( 'retention' ) )['errors'], 'attempts shortened below the answers' );

		$equal = self::retention( array( 'raw_answers_years_after_course_end' => 10 ) );
		self::assertSame( array( 'retention' => 'retention_reason' ), Settings::check( $actor, 'privacy', array( 'retention' => $equal ), array( 'retention' ) )['errors'], 'equal is allowed; above the default it still needs the E-245 reason' );
		Settings::save( $actor, 'privacy', array( 'retention' => $equal ) + $reason, array( 'retention' ), Settings::rev( 'privacy' ) );
		self::assertSame( 10, Settings::get( 'privacy.retention' )['raw_answers_years_after_course_end'] );
		self::assertSame( 10, RetentionPolicy::int( 'raw_answers_years_after_course_end' ) );

		$both = self::retention(
			array(
				'exam_attempts_years'                => 12,
				'raw_answers_years_after_course_end' => 12,
			)
		);
		self::assertSame( array(), Settings::check( $actor, 'privacy', array( 'retention' => $both ) + $reason, array( 'retention' ) )['errors'], 'both extended together' );
	}

	/**
	 * PSD-018 on the settings screen (field error at the raw answers) and in WP-CLI.
	 */
	public function test_PSD_018_screen_and_cli(): void {
		$post = array(
			'tab'               => 'privacy',
			'_wpnonce'          => wp_create_nonce( 'ccap_settings_save_privacy' ),
			'_rev'              => Settings::rev( 'privacy' ),
			'_fields'           => array( 'retention.raw_answers_years_after_course_end' ),
			'ccap'              => array( 'retention' => array( 'raw_answers_years_after_course_end' => '15' ) ),
			'_retention_reason' => 'Accreditation body rule',
		);
		self::assertSame( 'invalid', SettingsScreen::process_save( $post, $this->admin )['result'] );
		$html = SettingsScreen::html( array( 'tab' => 'privacy' ), $this->admin );
		self::assertStringContainsString( 'Learning answers, exam answers and assignment submissions: answers cannot be kept longer than the exam attempts they belong to.', $html );
		self::assertSame( 2, Settings::get( 'privacy.retention' )['raw_answers_years_after_course_end'] );

		$actor   = Actor::cli();
		$refused = SettingsCommand::execute( 'set', array( 'privacy.retention.raw_answers_years_after_course_end', '15' ), array( 'reason' => 'Accreditation body rule' ), $actor );
		self::assertSame( 1, $refused['exit'] );
		self::assertStringContainsString( 'retention_order', $refused['lines'][0] );
		self::assertStringContainsString( 'exam_attempts_years', $refused['lines'][0] );
		self::assertSame( 2, Settings::get( 'privacy.retention' )['raw_answers_years_after_course_end'], 'nothing written' );
	}

	/**
	 * RR-4 sentence 2: certificates shorter than the exam attempts are saved,
	 * the retention section shows a notice.
	 */
	public function test_RR_4_notice_certificates_before_attempts(): void {
		self::assertStringNotContainsString( 'ccap-retention-rr4', RetentionPreview::intro(), 'defaults: 10 = 10' );
		Settings::save( Actor::cli(), 'privacy', array( 'retention' => self::retention( array( 'certificates_years' => 5 ) ) ), array( 'retention' ), Settings::rev( 'privacy' ) );
		self::assertSame( 5, Settings::get( 'privacy.retention' )['certificates_years'], 'allowed' );
		$intro = RetentionPreview::intro();
		self::assertStringContainsString( 'ccap-retention-rr4', $intro );
		self::assertStringContainsString( 'certificates are deleted earlier than the exam attempts they are based on', $intro );
	}
}
