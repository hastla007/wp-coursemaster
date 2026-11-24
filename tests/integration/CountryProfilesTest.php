<?php
/**
 * SET-CC-07 / SET-CC-P3: country profiles DE/AT/CH/EU/OTHER — before/after
 * comparison, never a silent overwrite, filter override, open owner
 * decisions (E4, E7), audit and export/import of the chosen profile.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\CountryProfilePanel;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Admin\SetupWizard;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\AuditView;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Settings\CountryProfiles;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Settings\SettingsTransfer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables.
 *
 * @group SET-CC
 * @group SET-CC-07
 */
final class CountryProfilesTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * WordPress options touched by the profiles.
	 *
	 * @var array<string, mixed>
	 */
	private array $wp_before = array();

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
		foreach ( array( 'WPLANG', 'timezone_string', 'date_format', 'time_format', 'start_of_week' ) as $option ) {
			$this->wp_before[ $option ] = get_option( $option );
		}
		update_option( 'date_format', 'F j, Y' );
		update_option( 'time_format', 'g:i a' );
		update_option( 'start_of_week', 0 );
		update_option( 'timezone_string', 'UTC' );
		update_option( 'WPLANG', '' );
		delete_transient( SettingsTransfer::RATE_PREFIX . $this->admin );
		CountryProfiles::flush();
		wp_set_current_user( $this->admin );
	}

	/**
	 * Restore.
	 */
	public function tear_down(): void {
		remove_all_filters( CountryProfiles::FILTER );
		foreach ( $this->wp_before as $option => $value ) {
			update_option( $option, $value );
		}
		parent::tear_down();
	}

	/**
	 * Audit rows of an action.
	 *
	 * @param string $action Action.
	 * @return list<array<string, mixed>>
	 */
	private static function audits( string $action ): array {
		$db = Database::current();
		return $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s ORDER BY id', $action ) );
	}

	/**
	 * Every register value and the WordPress options of the profiles.
	 *
	 * @return array<string, mixed>
	 */
	private static function snapshot(): array {
		$out = array();
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			$out[ $tab ] = Settings::values( $tab );
		}
		foreach ( CountryProfiles::WP_OPTIONS as $option ) {
			$out[ 'wp.' . $option ] = get_option( $option );
		}
		return $out;
	}

	/**
	 * Rows by key.
	 *
	 * @param string $id Profile.
	 * @return array<string, array<string, mixed>>
	 */
	private static function rows( string $id ): array {
		return array_column( CountryProfiles::compare( $id ), null, 'key' );
	}

	/**
	 * POST of the panel.
	 *
	 * @param string $profile Profile.
	 * @param string $mode    selected|all.
	 * @param array  $keys    Keys.
	 * @phpstan-param list<string> $keys
	 * @return array<string, mixed>
	 */
	private static function post( string $profile, string $mode, array $keys = array() ): array {
		return array(
			'_wpnonce' => wp_create_nonce( CountryProfilePanel::ACTION ),
			'profile'  => $profile,
			'context'  => 'settings',
			'mode'     => $mode,
			'keys'     => $keys,
			'seen'     => CountryProfiles::fingerprint( CountryProfiles::compare( $profile ) ),
		);
	}

	/**
	 * The data file holds all five profiles; legal values are unreviewed;
	 * E4 (EU external videos) and E7 (age limit) are unset with an owner note.
	 */
	public function test_SET_CC_07_data_file_and_open_owner_decisions(): void {
		$file = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . CountryProfiles::SOURCE ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Data file.
		self::assertIsArray( $file );
		self::assertSame( CountryProfiles::IDS, array_keys( $file['profiles'] ) );
		self::assertSame( array( '', 'DE', 'AT', 'CH', 'EU', 'OTHER' ), SettingsSchema::fields()['general']['country_profile']['values'] );
		self::assertNull( $file['profiles']['EU']['privacy.external_media_mode']['value'], 'E4 not decided: no EU video value' );
		self::assertSame( 'E4', $file['profiles']['EU']['privacy.external_media_mode']['open'] );
		foreach ( CountryProfiles::IDS as $id ) {
			self::assertNull( $file['profiles'][ $id ]['open.age_limit']['value'], "E7 not decided: no age limit in $id" );
			self::assertSame( 'E7', $file['profiles'][ $id ]['open.age_limit']['open'] );
			foreach ( $file['profiles'][ $id ] as $key => $entry ) {
				self::assertNotSame( '', CountryProfiles::kind( $key ), "$id: $key is an existing setting or an open placeholder" );
				if ( str_starts_with( $key, 'privacy.' ) || str_starts_with( $key, 'certificates.' ) || 'open.age_limit' === $key ) {
					self::assertTrue( $entry['legal'] ?? false, "$id: $key is marked as legal value" );
					self::assertFalse( $entry['reviewed'] ?? false, "$id: $key is not reviewed yet" );
				}
			}
		}
		$eu = self::rows( 'EU' );
		self::assertSame( 'open', $eu['privacy.external_media_mode']['status'] );
		self::assertSame( 'E4', $eu['privacy.external_media_mode']['open'] );
		self::assertSame( 'open', $eu['open.age_limit']['status'] );
		self::assertSame( 'E7', $eu['open.age_limit']['open'] );

		$html = SettingsScreen::html(
			array(
				'tab'          => 'general',
				'ccap_profile' => 'EU',
			),
			$this->admin
		);
		self::assertStringContainsString( 'open – decision by the owner (E4); never applied', $html );
		self::assertStringContainsString( 'open – decision by the owner (E7); never applied', $html );
		self::assertStringContainsString( '<strong>unreviewed</strong> – legal review needed', $html );
		self::assertMatchesRegularExpression( '/data-ccap-profile-key="privacy\.external_media_mode" data-ccap-profile-status="open"><td>–<\/td>/', $html, 'open value has no apply control' );
	}

	/**
	 * Before/after rows are correct: current value, suggestion and status per key.
	 */
	public function test_SET_CC_07_diff_is_correct(): void {
		$actor = Actor::user( $this->admin );
		Settings::save( $actor, 'advanced', array( 'report_pseudonymize' => true ), array( 'report_pseudonymize' ), Settings::rev( 'advanced' ) );
		$rows = self::rows( 'DE' );
		self::assertSame( 'always_load', $rows['privacy.external_media_mode']['current'] );
		self::assertSame( 'click_to_load', $rows['privacy.external_media_mode']['suggested'] );
		self::assertSame( 'differs', $rows['privacy.external_media_mode']['status'] );
		self::assertTrue( $rows['privacy.external_media_mode']['legal'] );
		self::assertSame( 'same', $rows['advanced.report_pseudonymize']['status'], 'already set value is not offered' );
		self::assertSame( 'same', $rows['privacy.show_avatars']['status'] );
		self::assertSame( 'F j, Y', $rows['wp.date_format']['current'] );
		self::assertSame( 'j. F Y', $rows['wp.date_format']['suggested'] );
		self::assertSame( 'differs', $rows['wp.date_format']['status'] );
		self::assertSame( 0, $rows['wp.start_of_week']['current'] );
		self::assertSame( 1, $rows['wp.start_of_week']['suggested'] );
		self::assertSame( 'Europe/Berlin', $rows['wp.timezone_string']['suggested'] );
		$installed = in_array( 'de_DE', get_available_languages(), true );
		self::assertSame( $installed ? 'differs' : 'unavailable', $rows['wp.WPLANG']['status'], 'language only applicable when installed' );
		self::assertSame( 'en_US', $rows['wp.WPLANG']['current'] );

		$html = SettingsScreen::html(
			array(
				'tab'          => 'general',
				'ccap_profile' => 'DE',
			),
			$this->admin
		);
		self::assertStringContainsString( 'Current value (before)', $html );
		self::assertStringContainsString( 'Suggestion (after)', $html );
		self::assertMatchesRegularExpression( '/data-ccap-profile-key="privacy\.external_media_mode" data-ccap-profile-status="differs"><td><input type="checkbox"[^>]*value="privacy\.external_media_mode"/', $html );
		self::assertMatchesRegularExpression( '/data-ccap-profile-key="advanced\.report_pseudonymize" data-ccap-profile-status="same"><td>–<\/td>/', $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ), 'settings tab with the profile panel' );
		$setup = SetupWizard::html(
			array(
				'step'         => 'purpose_language',
				'ccap_profile' => 'DE',
			),
			$this->admin
		);
		self::assertStringContainsString( 'id="ccap-country-profile"', $setup, 'panel in setup step 1' );
		self::assertSame( array(), A11yDom::fragment_problems( $setup ), 'setup step 1 with the profile panel' );
	}

	/**
	 * Showing, choosing and applying never overwrite anything silently:
	 * only the selected key changes; the choice alone changes nothing.
	 */
	public function test_SET_CC_07_no_silent_overwrite(): void {
		$before = self::snapshot();
		SettingsScreen::html(
			array(
				'tab'          => 'general',
				'ccap_profile' => 'DE',
			),
			$this->admin
		);
		SetupWizard::html(
			array(
				'step'         => 'purpose_language',
				'ccap_profile' => 'AT',
			),
			$this->admin
		);
		CountryProfiles::compare( 'CH' );
		self::assertSame( $before, self::snapshot(), 'showing suggestions writes nothing' );

		// Choosing the profile in the general tab records the choice only.
		Settings::save( Actor::user( $this->admin ), 'general', array( 'country_profile' => 'DE' ), array( 'country_profile' ), Settings::rev( 'general' ) );
		$after                                  = self::snapshot();
		$expected                               = $before;
		$expected['general']['country_profile'] = 'DE';
		self::assertSame( $expected, $after, 'the choice changes no other value' );

		// "Apply selected" with one key changes exactly that key.
		$out = CountryProfilePanel::process( self::post( 'DE', 'selected', array( 'wp.date_format' ) ), $this->admin );
		self::assertSame( 'applied', $out['result'] );
		self::assertSame( array( 'wp.date_format' ), $out['applied'] );
		$expected['wp.date_format'] = 'j. F Y';
		self::assertSame( $expected, self::snapshot(), 'only the selected value changed' );
		self::assertSame( 'always_load', Settings::get( 'privacy.external_media_mode' ), 'unselected legal value untouched' );

		// Nothing selected: nothing happens.
		self::assertSame( 'none_selected', CountryProfilePanel::process( self::post( 'DE', 'selected' ), $this->admin )['result'] );
		self::assertSame( $expected, self::snapshot() );

		// A stale comparison is refused.
		$post = self::post( 'DE', 'all' );
		update_option( 'time_format', 'G:i' );
		self::assertSame( 'conflict', CountryProfilePanel::process( $post, $this->admin )['result'] );
		self::assertSame( 'always_load', Settings::get( 'privacy.external_media_mode' ), 'nothing applied on conflict' );

		// Invalid nonce and missing right.
		$bad             = self::post( 'DE', 'all' );
		$bad['_wpnonce'] = 'x';
		self::assertSame( 'ccap_invalid_nonce', CountryProfilePanel::process( $bad, $this->admin )['result'] );
		$editor = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		self::assertSame( 'ccap_forbidden', CountryProfilePanel::process( self::post( 'DE', 'all' ), $editor )['result'] );
		self::assertSame( '', CountryProfilePanel::html( 'settings', array(), $editor ) );
	}

	/**
	 * "Apply all" applies only differing values, skips open/null ones,
	 * records the profile, writes the audit entry (also in the settings view)
	 * and keeps the profile ID through export/import.
	 */
	public function test_SET_CC_07_apply_all_audit_and_export_import(): void {
		$out = CountryProfilePanel::process( self::post( 'EU', 'all' ), $this->admin );
		self::assertSame( 'applied', $out['result'] );
		self::assertContains( 'wp.date_format', $out['applied'] );
		self::assertContains( 'advanced.report_pseudonymize', $out['applied'] );
		self::assertNotContains( 'privacy.external_media_mode', $out['applied'], 'E4 open: never applied' );
		self::assertSame( 'always_load', Settings::get( 'privacy.external_media_mode' ) );
		self::assertSame( 'Y-m-d', get_option( 'date_format' ) );
		self::assertSame( '1', (string) get_option( 'start_of_week' ) );
		self::assertSame( 'UTC', get_option( 'timezone_string' ), 'EU has no time zone suggestion (null skipped)' );
		self::assertSame( 'EU', Settings::get( CountryProfiles::CHOICE_KEY ) );

		$audit = self::audits( CountryProfiles::AUDIT_ACTION );
		self::assertCount( 1, $audit );
		$context = json_decode( (string) $audit[0]['context'], true );
		self::assertSame( 'EU', $context['profile'] );
		self::assertSame( '', $context['previous'] );
		self::assertSame(
			array(
				'before' => 'F j, Y',
				'after'  => 'Y-m-d',
			),
			$context['changed']['wp.date_format']
		);
		self::assertSame(
			array(
				'before' => false,
				'after'  => true,
			),
			$context['changed']['advanced.report_pseudonymize']
		);
		self::assertContains( 'advanced.report_pseudonymize', $context['unreviewed'] );
		self::assertArrayNotHasKey( 'privacy.external_media_mode', $context['changed'] );
		self::assertArrayHasKey( CountryProfiles::AUDIT_ACTION, AuditView::registry() );
		self::assertContains( CountryProfiles::AUDIT_ACTION, AuditView::settings_actions() );
		$saved = self::audits( 'settings_saved' );
		self::assertNotEmpty( $saved );
		$last = json_decode( (string) end( $saved )['context'], true );
		self::assertSame( 'country_profile', $last['context'] );
		self::assertSame( 'EU', $last['profile'] );
		$view = AuditView::settings_changes( Actor::user( $this->admin ), array( 'action' => CountryProfiles::AUDIT_ACTION ) );
		$keys = array_column( $view['items'], 'after', 'key' );
		self::assertSame( 'Y-m-d', $keys['wp.date_format'] ?? null );

		// Applying again changes nothing and writes no further entry.
		self::assertSame( 'nothing', CountryProfilePanel::process( self::post( 'EU', 'all' ), $this->admin )['result'] );
		self::assertCount( 1, self::audits( CountryProfiles::AUDIT_ACTION ) );

		// Export/import keeps the chosen profile ID.
		$actor = Actor::user( $this->admin );
		$file  = SettingsTransfer::export( $actor );
		self::assertSame( 'EU', $file['tabs']['general']['values']['country_profile'] ?? null );
		Settings::reset( $actor, 'general' );
		self::assertSame( '', Settings::get( CountryProfiles::CHOICE_KEY ) );
		SettingsTransfer::import(
			$actor,
			SettingsTransfer::parse( (string) wp_json_encode( $file ) ),
			array(
				'apply'     => true,
				'confirmed' => array( '*' ),
			)
		);
		self::assertSame( 'EU', Settings::get( CountryProfiles::CHOICE_KEY ) );
	}

	/**
	 * The filter replaces the data; unknown profiles, unknown or
	 * non-exportable keys and invalid values are dropped; null values are
	 * skipped; a reviewed value loses the "unreviewed" mark.
	 */
	public function test_SET_CC_07_filter_override_and_null_values_skipped(): void {
		add_filter(
			CountryProfiles::FILTER,
			static function ( array $profiles ): array {
				$profiles['DE']['wp.date_format']                   = array( 'value' => 'd.m.Y' );
				$profiles['DE']['privacy.external_media_mode']      = array(
					'value'    => 'link_only',
					'legal'    => true,
					'reviewed' => true,
				);
				$profiles['DE']['privacy.show_avatars']             = array( 'value' => 'yes' );
				$profiles['DE']['general.delete_data_on_uninstall'] = array( 'value' => true );
				$profiles['DE']['general.country_profile']          = array( 'value' => 'AT' );
				$profiles['DE']['foo.bar']                          = array( 'value' => 1 );
				$profiles['DE']['wp.siteurl']                       = array( 'value' => 'https://evil.example' );
				$profiles['DE']['open.something']                   = array( 'value' => 5 );
				$profiles['DE']['wp.time_format']                   = array( 'value' => null );
				$profiles['FR']                                     = array( 'wp.date_format' => array( 'value' => 'd/m/Y' ) );
				return $profiles;
			}
		);
		self::assertArrayNotHasKey( 'FR', CountryProfiles::profiles() );
		$rows = self::rows( 'DE' );
		self::assertSame( 'd.m.Y', $rows['wp.date_format']['suggested'], 'filter wins over the data file' );
		self::assertSame( 'link_only', $rows['privacy.external_media_mode']['suggested'] );
		self::assertTrue( $rows['privacy.external_media_mode']['reviewed'] );
		foreach ( array( 'privacy.show_avatars', 'general.delete_data_on_uninstall', 'general.country_profile', 'foo.bar', 'wp.siteurl', 'open.something', 'wp.time_format' ) as $dropped ) {
			self::assertArrayNotHasKey( $dropped, $rows, $dropped . ' dropped' );
		}
		$html = CountryProfilePanel::html( 'settings', array( 'ccap_profile' => 'DE' ), $this->admin );
		self::assertDoesNotMatchRegularExpression( '/data-ccap-profile-key="privacy\.external_media_mode"[^\n]*?unreviewed<\/strong>[^\n]*?<\/tr><tr data-ccap-profile-key="privacy\.backup/', $html );
		preg_match( '/<tr data-ccap-profile-key="privacy\.external_media_mode".*?<\/tr>/s', $html, $m );
		self::assertStringNotContainsString( 'unreviewed', $m[0] ?? 'missing', 'reviewed value is not marked unreviewed' );

		// Applying a dropped or null key does nothing.
		$siteurl = get_option( 'siteurl' );
		$out     = CountryProfiles::apply( Actor::user( $this->admin ), 'DE', array( 'wp.siteurl', 'wp.time_format', 'general.delete_data_on_uninstall', 'open.something' ) );
		self::assertSame( array(), $out['applied'] );
		self::assertSame( $siteurl, get_option( 'siteurl' ) );
		self::assertSame( 'g:i a', get_option( 'time_format' ) );
		self::assertFalse( Settings::get( 'general.delete_data_on_uninstall' ) );

		// A broken filter result means no profiles, not an error.
		remove_all_filters( CountryProfiles::FILTER );
		add_filter( CountryProfiles::FILTER, '__return_false' );
		self::assertSame( array(), CountryProfiles::compare( 'DE' ) );
		self::assertStringContainsString( 'This profile has no suggestions.', CountryProfilePanel::html( 'settings', array( 'ccap_profile' => 'DE' ), $this->admin ) );
	}

	/**
	 * WordPress values need `manage_options`; a settings manager without it
	 * applies only register values. Values needing a risk confirmation are skipped.
	 */
	public function test_SET_CC_07_wordpress_values_need_admin_and_risks_are_skipped(): void {
		$manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		get_userdata( $manager )->add_cap( Settings::CAP );
		wp_set_current_user( $manager );
		$out = CountryProfiles::apply( Actor::user( $manager ), 'DE', array( 'wp.date_format', 'privacy.external_media_mode' ) );
		self::assertSame( array( 'privacy.external_media_mode' ), $out['applied'] );
		self::assertSame( 'forbidden', $out['skipped']['wp.date_format'] );
		self::assertSame( 'F j, Y', get_option( 'date_format' ) );
		self::assertStringContainsString( 'Only administrators can change WordPress values.', CountryProfilePanel::html( 'settings', array( 'ccap_profile' => 'DE' ), $manager ) );

		wp_set_current_user( $this->admin );
		add_filter(
			CountryProfiles::FILTER,
			static function ( array $profiles ): array {
				$profiles['AT']['privacy.show_avatars'] = array( 'value' => true );
				return $profiles;
			}
		);
		$out = CountryProfiles::apply( Actor::user( $this->admin ), 'AT', array( 'privacy.show_avatars' ) );
		self::assertSame( 'risk', $out['skipped']['privacy.show_avatars'] ?? '', 'risk value needs the settings tab' );
		self::assertFalse( Settings::get( 'privacy.show_avatars' ) );
	}
}
