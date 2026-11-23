<?php
/**
 * R1-31: settings storage, rights, migration (R0-27 §1–§2; R0-05 GM-178, GM-181).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Storage\Database;

/**
 * Real options.
 *
 * @group R1-31
 */
final class SettingsTest extends SchemaTestCase {

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
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 */
	private function assertRejected( callable $call, string $code ): void {
		try {
			$call();
			self::fail( 'expected ' . $code );
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code );
		}
	}

	/**
	 * Save helper.
	 *
	 * @param int                  $user   User.
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $values Values.
	 * @return array{changed: list<string>, rev: int}
	 */
	private function save( int $user, string $tab, array $values ): array {
		return Settings::save( Actor::user( $user ), $tab, $values, array_keys( $values ), Settings::rev( $tab ) );
	}

	/**
	 * GM-178: defaults without option; limits; `false` is storable.
	 *
	 * @group GM-178
	 */
	public function test_GM_178_defaults_and_limits(): void {
		self::assertSame( 30, Settings::get( 'enrollment.invite_valid_days' ) );
		self::assertTrue( Settings::get( 'learning.favorites_enabled' ) );
		self::assertSame( 'always', Settings::get( 'learning.feedback' )['visibility'] );
		$this->assertRejected( fn() => $this->save( $this->admin, 'enrollment', array( 'invite_valid_days' => 500 ) ), 'ccap_validation_failed' );
		self::assertSame( 30, Settings::get( 'enrollment.invite_valid_days' ), 'nothing saved' );
		$this->save( $this->admin, 'learning', array( 'favorites_enabled' => false ) );
		self::assertFalse( Settings::get( 'learning.favorites_enabled' ) );
		$stored = get_option( Settings::OPTION_PREFIX . 'learning' );
		self::assertCount( count( SettingsSchema::fields()['learning'] ), $stored['values'], 'first save materializes all fields' );

		// S-10: a broken stored value falls back per field.
		$stored['values']['mastery_algorithm'] = 'v99';
		update_option( Settings::OPTION_PREFIX . 'learning', $stored );
		self::assertSame( 'smarttrainer.v1', Settings::get( 'learning.mastery_algorithm' ) );
		$this->expectException( CourseException::class );
		Settings::get( 'learning.unknown' );
	}

	/**
	 * S-2…S-6: partial update keeps other fields, unknown keys dropped, all-or-nothing, rev conflict, caps.
	 */
	public function test_partial_update_rights_and_conflicts(): void {
		$this->save( $this->admin, 'enrollment', array( 'claim_policy' => 'invited_address_only' ) );
		$result = Settings::save(
			Actor::user( $this->admin ),
			'enrollment',
			array(
				'invite_valid_days' => '14',
				'evil'              => 'x',
			),
			array( 'invite_valid_days', 'evil' ),
			Settings::rev( 'enrollment' )
		);
		self::assertSame( array( 'invite_valid_days' ), $result['changed'] );
		self::assertSame( 'invited_address_only', Settings::get( 'enrollment.claim_policy' ), 'INV-C-069: missing key = unchanged' );
		self::assertArrayNotHasKey( 'evil', get_option( Settings::OPTION_PREFIX . 'enrollment' )['values'] );

		$this->assertRejected(
			fn() => $this->save(
				$this->admin,
				'enrollment',
				array(
					'invite_valid_days' => 20,
					'claim_policy'      => 'nobody',
				)
			),
			'ccap_validation_failed'
		);
		self::assertSame( 14, Settings::get( 'enrollment.invite_valid_days' ), 'all or nothing' );

		$stale = Settings::rev( 'enrollment' ) - 1;
		$this->assertRejected( fn() => Settings::save( Actor::user( $this->admin ), 'enrollment', array( 'invite_valid_days' => 20 ), array( 'invite_valid_days' ), $stale ), 'ccap_settings_conflict' );

		$subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertRejected( fn() => $this->save( $subscriber, 'enrollment', array( 'invite_valid_days' => 20 ) ), 'ccap_forbidden' );

		// A settings manager without manage_options cannot touch admin-only fields.
		$manager = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		get_userdata( $manager )->add_cap( Settings::CAP );
		$result = $this->save(
			$manager,
			'general',
			array(
				'delete_data_on_uninstall' => true,
				'learning_area_title'      => 'Lernen',
			)
		);
		self::assertSame( array( 'learning_area_title' ), $result['changed'] );
		self::assertFalse( Settings::get( 'general.delete_data_on_uninstall' ) );
		self::assertSame( array(), $this->save( $this->admin, 'general', array( 'learning_area_title' => 'Lernen' ) )['changed'], 'no change, no write' );
	}

	/**
	 * SR-6 / S-8: audit by privacy class, side effects.
	 */
	public function test_audit_and_side_effects(): void {
		update_option( Plugin::REWRITE_VERSION_OPTION, Plugin::REWRITE_VERSION );
		$this->save( $this->admin, 'general', array( 'course_slug' => 'course' ) );
		self::assertFalse( get_option( Plugin::REWRITE_VERSION_OPTION ), 'rewrite flush scheduled' );
		$this->save( $this->admin, 'emails', array( 'from_name' => 'Akademie' ) );

		$db   = Database::current();
		$rows = $db->rows( 'SELECT object_id, context FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'settings_saved' ORDER BY id" );
		self::assertCount( 2, $rows );
		$general = json_decode( (string) $rows[0]['context'], true );
		self::assertSame(
			array(
				'before' => 'kurs',
				'after'  => 'course',
			),
			$general['changed']['course_slug']
		);
		$emails = json_decode( (string) $rows[1]['context'], true );
		self::assertSame( 'changed', $emails['changed']['from_name'], 'personal: no values' );
		self::assertStringNotContainsString( 'Akademie', (string) $rows[1]['context'] );
	}

	/**
	 * §2.4 reset with undo; §2.5 export without secrets/personal.
	 */
	public function test_reset_undo_export(): void {
		$this->save(
			$this->admin,
			'exams',
			array(
				'max_attempts'    => 3,
				'shuffle_answers' => false,
			)
		);
		$admin = Actor::user( $this->admin );
		self::assertEqualsCanonicalizing( array( 'max_attempts', 'shuffle_answers' ), Settings::reset( $admin, 'exams' ) );
		self::assertNull( Settings::get( 'exams.max_attempts' ) );
		self::assertTrue( Settings::undo( $admin, 'exams' ) );
		self::assertSame( 3, Settings::get( 'exams.max_attempts' ) );
		self::assertFalse( Settings::undo( $admin, 'exams' ), 'one generation' );

		$this->save( $this->admin, 'emails', array( 'from_name' => 'Akademie' ) );
		$export = Settings::export( $admin );
		self::assertSame( 'course-captain-settings', $export['format'] );
		self::assertArrayNotHasKey( 'from_name', $export['tabs']['emails']['values'] );
		self::assertArrayNotHasKey( 'delete_data_on_uninstall', $export['tabs']['general']['values'] );
		self::assertSame( 'Akademie', Settings::export( $admin, true )['tabs']['emails']['values']['from_name'] );
		self::assertSame( hash( 'sha256', home_url() ), $export['site_hash'] );
		self::assertStringNotContainsString( home_url(), (string) wp_json_encode( $export ) );
	}

	/**
	 * GM-181: migration n−1 → n; S-9 foreign writes; downgrade passthrough.
	 *
	 * @group GM-181
	 */
	public function test_GM_181_migration_guard_downgrade(): void {
		add_option(
			Settings::OPTION_PREFIX . 'enrollment',
			array(
				'invite_valid_days' => 10,
				'unknown_key'       => 'x',
				'claim_policy'      => 'broken',
			)
		);
		self::assertContains( 'enrollment', Settings::migrate() );
		$stored = get_option( Settings::OPTION_PREFIX . 'enrollment' );
		self::assertSame( SettingsSchema::VERSION, $stored['_schema'] );
		self::assertSame( array( 'invite_valid_days' => 10 ), $stored['values'], 'known kept, unknown and invalid dropped' );
		self::assertSame( array(), Settings::migrate(), 'idempotent' );
		self::assertSame( SettingsSchema::VERSION, (int) get_option( Settings::VERSION_OPTION ) );

		// S-9: an invalid foreign write is rejected.
		update_option(
			Settings::OPTION_PREFIX . 'enrollment',
			array(
				'_schema' => 1,
				'values'  => array( 'invite_valid_days' => 'many' ),
			)
		);
		self::assertSame( 10, Settings::get( 'enrollment.invite_valid_days' ) );

		// Downgrade (E-90): keys of a newer schema pass through a save.
		delete_option( Settings::OPTION_PREFIX . 'display' );
		add_option(
			Settings::OPTION_PREFIX . 'display',
			array(
				'_schema' => 99,
				'_rev'    => 4,
				'values'  => array(
					'breadcrumbs' => true,
					'future_key'  => 'kept',
				),
			)
		);
		$this->save( $this->admin, 'display', array( 'breadcrumbs' => false ) );
		$stored = get_option( Settings::OPTION_PREFIX . 'display' );
		self::assertSame( 'kept', $stored['values']['future_key'] );
		self::assertSame( 99, $stored['_schema'] );
		self::assertSame( 5, $stored['_rev'] );
	}
}
