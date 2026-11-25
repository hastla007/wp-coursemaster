<?php
/**
 * E-245 follow-up (SET-CC-aufbewahrung-2, "Was noch fehlt"): undoing a
 * reset or import of the privacy tab that restores retention periods
 * above their default needs a reason, like any other extension.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Privacy\RetentionPolicy;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real options and audit log.
 *
 * @group SET-CC
 * @group SET-CC-02
 * @group E-245
 */
final class SettingsUndoRetentionReasonTest extends SchemaTestCase {

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
		PolicyService::flush();
		wp_set_current_user( $this->admin );
	}

	/**
	 * Newest audit row of an action.
	 *
	 * @param string $action Action.
	 * @return array<string, mixed>
	 */
	private static function last_audit( string $action ): array {
		$db = Database::current();
		return (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s ORDER BY id DESC LIMIT 1', $action ) );
	}

	/**
	 * Save an extended audit period (with reason) and reset the tab.
	 *
	 * @return void
	 */
	private function extend_and_reset(): void {
		$actor = Actor::user( $this->admin );
		Settings::save(
			$actor,
			'privacy',
			array(
				'retention'                      => array_merge( RetentionPolicy::defaults(), array( 'audit_log_years' => 6 ) ),
				Settings::RETENTION_REASON_FIELD => 'Tax audit obligation',
			),
			array( 'retention' ),
			Settings::rev( 'privacy' )
		);
		Settings::reset( $actor, 'privacy' );
		self::assertSame( 4, Settings::get( 'privacy.retention' )['audit_log_years'], 'reset to the default' );
	}

	/**
	 * Undo without a reason is refused and changes nothing; with a reason
	 * the old periods come back and the audit entry carries the reason.
	 */
	public function test_undo_restoring_longer_periods_needs_a_reason(): void {
		$actor = Actor::user( $this->admin );
		$this->extend_and_reset();
		self::assertSame( array( 'audit_log_years' ), Settings::undo_extended( 'privacy' ) );
		self::assertSame( array(), Settings::undo_extended( 'exams' ), 'other tabs never' );

		try {
			Settings::undo( $actor, 'privacy' );
			self::fail( 'undo restoring a longer period without reason must be refused' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
			self::assertSame( array( 'retention' => 'retention_reason' ), $e->data['fields'] );
		}
		self::assertSame( 4, Settings::get( 'privacy.retention' )['audit_log_years'], 'nothing restored' );
		self::assertGreaterThan( 0, Settings::undo_until( 'privacy' ), 'undo slot kept' );

		// Whitespace only or longer than 500 characters is no reason.
		foreach ( array( '   ', str_repeat( 'x', Settings::RETENTION_REASON_MAX + 1 ) ) as $bad ) {
			try {
				Settings::undo( $actor, 'privacy', array( Settings::RETENTION_REASON_FIELD => $bad ) );
				self::fail( 'invalid reason' );
			} catch ( CourseException $e ) {
				self::assertSame( 'ccap_validation_failed', $e->error_code );
			}
		}

		// Admin screen: reason field in the undo form, own result message.
		$page = SettingsScreen::html( array( 'tab' => 'privacy' ), $this->admin );
		self::assertStringContainsString( 'id="ccap-field-privacy-undo-retention-reason"', $page );
		self::assertSame( array(), A11yDom::fragment_problems( $page ) );
		$post = array(
			'tab'      => 'privacy',
			'_wpnonce' => wp_create_nonce( 'ccap_settings_undo_privacy' ),
		);
		self::assertSame( 'undo_reason', SettingsScreen::process_undo( $post, $this->admin )['result'] );
		self::assertStringContainsString( 'Enter a reason next to the undo button', SettingsScreen::html( array( 'tab' => 'privacy', 'ccap_result' => 'undo_reason' ), $this->admin ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.

		self::assertSame( 'undone', SettingsScreen::process_undo( $post + array( Settings::RETENTION_REASON_FIELD => '  Tax audit obligation again  ' ), $this->admin )['result'] );
		self::assertSame( 6, Settings::get( 'privacy.retention' )['audit_log_years'] );
		$audit   = self::last_audit( 'settings_saved' );
		$context = (array) json_decode( (string) $audit['context'], true );
		self::assertSame( 'Tax audit obligation again', $audit['reason'] );
		self::assertSame( array( 'audit_log_years' ), $context['retention_extended'] );
		self::assertSame( 'undo', $context['context'] );
		self::assertSame( 0, Settings::undo_until( 'privacy' ), 'one generation' );
		self::assertStringNotContainsString( 'undo-retention-reason', SettingsScreen::html( array( 'tab' => 'privacy' ), $this->admin ) );
	}

	/**
	 * An undo that restores only default or shorter periods needs no reason.
	 */
	public function test_undo_without_longer_periods_needs_no_reason(): void {
		$actor = Actor::user( $this->admin );
		Settings::save( $actor, 'privacy', array( 'retention' => array_merge( RetentionPolicy::defaults(), array( 'audit_log_years' => 2 ) ) ), array( 'retention' ), Settings::rev( 'privacy' ) );
		Settings::reset( $actor, 'privacy' );
		self::assertSame( array(), Settings::undo_extended( 'privacy' ) );
		self::assertTrue( Settings::undo( $actor, 'privacy' ) );
		self::assertSame( 2, Settings::get( 'privacy.retention' )['audit_log_years'] );
		$audit = self::last_audit( 'settings_saved' );
		self::assertNull( $audit['reason'] );
		self::assertArrayNotHasKey( 'retention_extended', (array) json_decode( (string) $audit['context'], true ) );
	}
}
