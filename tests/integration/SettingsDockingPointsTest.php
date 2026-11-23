<?php
/**
 * SET-CC-01 (core side) and SET-CC-P6: hint lines, docking point for
 * add-on register fields, `pro.*` in export/import, free/pro guard,
 * contract docs and the settings upgrade from earlier register versions.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\BusinessRules;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsHints;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Settings\SettingsTransfer;
use CourseCaptain\Settings\TransferProvider;
use CourseCaptain\Tests\Guards\FreeGuard;
use CourseCaptain\Tests\Support\A11yDom;

require_once dirname( __DIR__ ) . '/guards/free-guard.php';

/**
 * Real tables.
 *
 * @group SET-CC
 * @group SET-CC-01
 * @group SET-CC-P6
 */
final class SettingsDockingPointsTest extends SchemaTestCase {

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
		delete_option( Settings::VERSION_OPTION );
		delete_transient( SettingsTransfer::RATE_PREFIX . $this->admin );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Remove filters.
	 */
	public function tear_down(): void {
		remove_all_filters( SettingsHints::FILTER );
		remove_all_filters( SettingsSchema::ADDON_FIELDS_FILTER );
		remove_all_filters( SettingsTransfer::PROVIDERS );
		parent::tear_down();
	}

	/**
	 * A valid hint line.
	 *
	 * @param string $id  Id.
	 * @param string $tab Tab.
	 * @return array<string, string>
	 */
	private static function hint( string $id, string $tab ): array {
		return array(
			'id'    => $id,
			'tab'   => $tab,
			'title' => 'Reports over time',
			'text'  => 'Cohort reports and saved reports. Available with an extension.',
			'link'  => 'https://example.org/extension',
		);
	}

	/**
	 * S5: the core ships no hint line; no tab shows the hint area.
	 */
	public function test_SET_CC_01_core_list_is_empty(): void {
		self::assertSame( array(), SettingsHints::all() );
		foreach ( SettingsScreen::tabs_for( $this->admin ) as $tab ) {
			self::assertStringNotContainsString( 'ccap-hints', SettingsScreen::html( array( 'tab' => $tab ), $this->admin ), $tab );
		}
	}

	/**
	 * S1: data shape `{id, tab, title, text, link}`, nothing else survives; no
	 * closures, no rights, no invalid tabs or insecure links.
	 */
	public function test_SET_CC_01_hint_shape_without_function_code(): void {
		$closure = static fn(): string => 'x';
		add_filter(
			SettingsHints::FILTER,
			static fn( array $hints ): array => array_merge(
				$hints,
				array(
					self::hint( 'analytics', 'advanced' ) + array(
						'right'    => 'course.analytics',
						'callback' => $closure,
						'field'    => 'pro.analytics.enabled',
					),
					self::hint( 'analytics', 'general' ),
					array( 'title' => $closure ) + self::hint( 'closure_title', 'advanced' ),
					self::hint( 'bad_tab', 'nowhere' ),
					array( 'link' => 'http://example.org/' ) + self::hint( 'insecure', 'advanced' ),
					array( 'link' => 'javascript:alert(1)' ) + self::hint( 'script', 'advanced' ),
					array( 'id' => 'Bad Id' ) + self::hint( 'x', 'advanced' ),
					array( 'text' => str_repeat( 'a', SettingsHints::TEXT_MAX + 1 ) ) + self::hint( 'long', 'advanced' ),
					array( 'link' => admin_url( 'plugins.php' ) ) + self::hint( 'admin_link', 'roles' ),
					'not an array',
				)
			)
		);
		$all     = SettingsHints::all();
		self::assertSame( array( 'analytics', 'admin_link' ), array_keys( $all ) );
		self::assertSame( SettingsHints::SHAPE, array_keys( $all['analytics'] ), 'only the data fields survive' );
		self::assertSame( 'advanced', $all['analytics']['tab'], 'first registration of an id wins' );
		self::assertStringNotContainsString( 'course.analytics', (string) wp_json_encode( $all ) );
	}

	/**
	 * Grey line at the end of the tab, hidden per person through a nonce
	 * form; the dismissal is a per-site user option in the uninstall manifest.
	 */
	public function test_SET_CC_01_render_and_dismiss_per_user(): void {
		add_filter( SettingsHints::FILTER, static fn( array $hints ): array => array_merge( $hints, array( self::hint( 'analytics', 'advanced' ), self::hint( 'advanced_roles', 'roles' ) ) ) );
		$html = SettingsScreen::html( array( 'tab' => 'advanced' ), $this->admin );
		self::assertStringContainsString( 'class="ccap-hint description"', $html );
		self::assertStringContainsString( 'Reports over time', $html );
		self::assertGreaterThan( strpos( $html, 'Save changes' ), strpos( $html, 'ccap-hints' ), 'at the end of the tab' );
		self::assertStringContainsString( 'name="action" value="' . SettingsScreen::HINT . '"', $html );
		self::assertStringNotContainsString( '<input type="checkbox"', substr( $html, (int) strpos( $html, 'ccap-hints' ) ), 'no field' );
		self::assertStringContainsString( 'Reports over time', SettingsScreen::html( array( 'tab' => 'roles' ), $this->admin ), 'roles tab too' );
		// A11y (R0-15): the hint area has labelled controls, no positive tabindex, named buttons.
		$area = substr( $html, (int) strpos( $html, '<aside class="ccap-hints"' ) );
		$area = substr( $area, 0, (int) strpos( $area, '</aside>' ) + 8 );
		self::assertSame( array(), A11yDom::fragment_problems( $area ) );
		self::assertSame( 1, A11yDom::xpath( $area )->query( '//aside[@aria-label]' )->length );
		foreach ( A11yDom::xpath( $area )->query( '//button' ) as $button ) {
			self::assertInstanceOf( \DOMElement::class, $button );
			self::assertStringContainsString( 'Reports over time', A11yDom::name( A11yDom::xpath( $area ), $button ), 'button names the line' );
		}

		$post = array(
			'tab'  => 'advanced',
			'hint' => 'analytics',
		);
		self::assertSame( 'ccap_invalid_nonce', SettingsScreen::process_hint( $post, $this->admin )['result'] );
		$learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::assertSame( 'ccap_forbidden', SettingsScreen::process_hint( $post + array( '_wpnonce' => wp_create_nonce( SettingsScreen::HINT . '_analytics' ) ), $learner )['result'] );
		self::assertSame( 'unchanged', SettingsScreen::process_hint( array( 'hint' => 'advanced_roles' ) + $post + array( '_wpnonce' => wp_create_nonce( SettingsScreen::HINT . '_advanced_roles' ) ), $this->admin )['result'], 'line of another tab' );

		$result = SettingsScreen::process_hint( $post + array( '_wpnonce' => wp_create_nonce( SettingsScreen::HINT . '_analytics' ) ), $this->admin );
		self::assertSame( 'hint_hidden', $result['result'] );
		self::assertSame( array( 'analytics' ), get_user_option( SettingsHints::USER_KEY, $this->admin ) );
		$after = SettingsScreen::html(
			array(
				'tab'         => 'advanced',
				'ccap_result' => 'hint_hidden',
			),
			$this->admin
		);
		self::assertStringNotContainsString( 'Reports over time', $after );
		self::assertStringContainsString( 'The note is hidden for you.', $after );

		$other = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::assertStringContainsString( 'Reports over time', SettingsScreen::html( array( 'tab' => 'advanced' ), $other ), 'per person' );
		self::assertContains( SettingsHints::USER_KEY, UninstallManifest::user_options() );
		self::assertTrue( UninstallManifest::covers( SettingsHints::USER_KEY ) );
	}

	/**
	 * Docking point `course_captain_settings_register_fields_v1`: only valid
	 * `pro.<tab>.<field>` definitions, never a core key, never code.
	 */
	public function test_SET_CC_01_addon_fields_docking_point(): void {
		self::assertSame( array(), SettingsSchema::addon_fields() );
		self::assertTrue( course_captain_supports( 'settings.fields.v1' ) );
		self::assertTrue( course_captain_supports( 'settings.hints.v1' ) );
		$core = SettingsSchema::fields();
		add_filter(
			SettingsSchema::ADDON_FIELDS_FILTER,
			static fn( array $fields ): array => $fields + array(
				'pro.reminders.enabled'         => array(
					'type'         => 'bool',
					'default'      => false,
					'side_effects' => array( 'rewrite_flush' ),
				),
				'pro.reminders.days'            => array(
					'type'    => 'int',
					'min'     => 1,
					'max'     => 90,
					'default' => 14,
					'privacy' => 'public',
				),
				'reminders.enabled'             => array(
					'type'    => 'bool',
					'default' => false,
				),
				'general.course_slug'           => array(
					'type'    => 'slug',
					'default' => 'other',
				),
				'pro.x'                         => array(
					'type'    => 'bool',
					'default' => false,
				),
				'pro.reminders.unknown_type'    => array(
					'type'    => 'callable',
					'default' => null,
				),
				'pro.reminders.bad_default'     => array(
					'type'    => 'int',
					'min'     => 1,
					'default' => 0,
				),
				'pro.reminders.closure'         => array(
					'type'     => 'bool',
					'default'  => false,
					'sanitize' => static fn( $v ) => $v,
				),
				'pro.reminders.bad_cap'         => array(
					'type'    => 'bool',
					'default' => false,
					'cap'     => 'edit_posts',
				),
				'pro.learning.feedback.explain' => array(
					'type'    => 'bool',
					'default' => true,
				),
			)
		);
		$addon = SettingsSchema::addon_fields();
		self::assertSame( array( 'pro.learning.feedback.explain', 'pro.reminders.days', 'pro.reminders.enabled' ), array_keys( $addon ) );
		self::assertSame( 'reminders', $addon['pro.reminders.enabled']['tab'] );
		self::assertSame( array(), $addon['pro.reminders.enabled']['side_effects'], 'no core side effects for foreign fields' );
		self::assertSame( 'settings', $addon['pro.reminders.days']['cap'] );
		self::assertSame( $core, SettingsSchema::fields(), 'core register unchanged' );
		self::assertSame( 'kurs', Settings::get( 'general.course_slug' ) );
	}

	/**
	 * Export/import keeps unknown `pro.*` fields untouched: without the add-on
	 * nothing of them is written or reported as an error; with an add-on
	 * provider the section passes unchanged (also keys it does not know).
	 */
	public function test_SET_CC_01_transfer_keeps_pro_fields(): void {
		$actor                        = Actor::user( $this->admin );
		$file                         = SettingsTransfer::export( $actor );
		$file['pro']                  = array(
			'pro.reminders.enabled'   => true,
			'pro.reminders.new_thing' => array( 'nested' => 1 ),
		);
		$file['tabs']['pro.commerce'] = array(
			'_schema' => 1,
			'values'  => array( 'tax_class' => 'reduced' ),
		);
		$file['tabs']['emails']['values']['pro.reminders.days'] = 14;
		$file['tabs']['exams']['values']['shuffle_answers']     = false;
		global $wpdb;
		$options = static fn(): array => $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '%pro%' ORDER BY option_name", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$before  = $options();

		$report = SettingsTransfer::import(
			$actor,
			SettingsTransfer::parse( (string) wp_json_encode( $file ) ),
			array( 'apply' => true )
		);
		$kept   = $report['addon'];
		sort( $kept );
		self::assertSame( array( 'pro.commerce.tax_class', 'pro.reminders.days', 'pro.reminders.enabled', 'pro.reminders.new_thing' ), $kept );
		self::assertContains( 'addon_kept', $report['notices'] );
		self::assertSame( array(), $report['tabs']['emails']['skipped'], 'not reported as unknown' );
		self::assertSame( array(), $report['tabs']['emails']['errors'] );
		self::assertSame( array( 'exams' ), $report['applied'], 'core values still imported' );
		self::assertFalse( Settings::get( 'exams.shuffle_answers' ) );
		self::assertSame( $before, $options(), 'no add-on option written or deleted' );
		$emails = get_option( Settings::OPTION_PREFIX . 'emails' );
		self::assertArrayNotHasKey( 'pro.reminders.days', is_array( $emails ) ? (array) ( $emails['values'] ?? array() ) : array() );

		// With an add-on provider (key `pro`) the section reaches it unchanged.
		$seen     = array();
		$provider = new class( $seen ) implements TransferProvider {
			/**
			 * Captured sections.
			 *
			 * @var array<int, array<string, mixed>>
			 */
			public array $seen;

			/**
			 * Constructor.
			 *
			 * @param array<int, array<string, mixed>> $seen Captured sections.
			 */
			public function __construct( array &$seen ) {
				$this->seen = &$seen;
			}

			/** Key. */
			public function key(): string {
				return 'pro';
			}

			/** Label. */
			public function label(): string {
				return 'Add-on';
			}

			/** Opt-in. */
			public function opt_in(): bool {
				return false;
			}

			/**
			 * Export allowed.
			 *
			 * @param Actor $actor Actor.
			 */
			public function can_export( Actor $actor ): bool {
				return true;
			}

			/**
			 * Export with a key the core does not know.
			 *
			 * @param Actor $actor Actor.
			 * @return array<string, mixed>
			 */
			public function export( Actor $actor ): array {
				return array(
					'pro.reminders.enabled'    => true,
					'pro.future.unknown_shape' => array( 'a' => array( 1, 2 ) ),
				);
			}

			/**
			 * Plan.
			 *
			 * @param Actor                $actor     Actor.
			 * @param array<string, mixed> $section   Section.
			 * @param array<int, string>   $confirmed Confirmed.
			 * @return array{changed: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: array<string, string>}
			 */
			public function plan( Actor $actor, array $section, array $confirmed ): array {
				$this->seen[] = $section;
				return array(
					'changed' => array(),
					'skipped' => array(),
					'errors'  => array(),
				);
			}

			/**
			 * Apply.
			 *
			 * @param Actor                $actor     Actor.
			 * @param array<string, mixed> $section   Section.
			 * @param array<int, string>   $confirmed Confirmed.
			 * @return list<string>
			 */
			public function apply( Actor $actor, array $section, array $confirmed ): array {
				return array();
			}
		};
		add_filter( SettingsTransfer::PROVIDERS, static fn( array $providers ): array => array_merge( $providers, array( $provider ) ) );
		$export = SettingsTransfer::export( $actor );
		self::assertSame( array( 'a' => array( 1, 2 ) ), $export['pro']['pro.future.unknown_shape'], 'export keeps unknown add-on keys' );
		$report = SettingsTransfer::import( $actor, SettingsTransfer::parse( (string) wp_json_encode( $file ) ) );
		self::assertSame( array( $file['pro'] ), $provider->seen, 'section handed over unchanged' );
		self::assertNotContains( 'pro.reminders.enabled', $report['addon'], 'the add-on owns its section' );
	}

	/**
	 * P6: no license code or rights keys in the core (G-1…G-15), also in the
	 * new hint and docking code; G-15 finds a rights key.
	 */
	public function test_SET_CC_P6_free_guard_covers_new_code(): void {
		$root  = dirname( __DIR__, 2 );
		$guard = new FreeGuard( $root, $root . '/tools/qa/free-guard-allow.txt' );
		self::assertContains( 'src/Settings/SettingsHints.php', $guard->files() );
		self::assertSame( array(), $guard->run()['findings'] );
		self::assertArrayHasKey( 'G-15', FreeGuard::PATTERNS );
		self::assertSame( 1, preg_match( FreeGuard::PATTERNS['G-15'][0], "\$right = 'course.analytics';" ) );
		self::assertSame( 0, preg_match( FreeGuard::PATTERNS['G-15'][0], "\$event = 'course.completed';" ), 'events are no rights' );
		foreach ( array( 'src/Settings/SettingsHints.php', 'src/Settings/SettingsSchema.php', 'src/Settings/SettingsTransfer.php' ) as $file ) {
			$code = (string) file_get_contents( $root . '/' . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Source scan.
			self::assertDoesNotMatchRegularExpression( '/licen[cs]e|entitle|is_pro\b|pro_active/i', $code, $file );
		}
	}

	/**
	 * P6: contract version and hook docs complete (every snapshot hook in
	 * the API reference, the new docking points described).
	 */
	public function test_SET_CC_P6_contract_and_hook_docs(): void {
		$root  = dirname( __DIR__, 2 );
		$api   = (string) file_get_contents( $root . '/docs/API.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Fixture.
		$hooks = json_decode( (string) file_get_contents( $root . '/docs/api/hooks.v1.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Fixture.
		self::assertIsArray( $hooks );
		foreach ( $hooks as $hook => $kind ) {
			self::assertStringContainsString( '| `' . $hook . '` | ' . $kind . ' |', $api, $hook . ' documented in docs/API.md' );
		}
		self::assertArrayHasKey( SettingsSchema::ADDON_FIELDS_FILTER, $hooks );
		self::assertArrayHasKey( SettingsHints::FILTER, $hooks );
		self::assertStringContainsString( '`' . COURSE_CAPTAIN_CONTRACT_VERSION . '`', $api, 'contract version in the reference' );
		foreach ( array( 'settings.fields.v1', 'settings.hints.v1' ) as $feature ) {
			self::assertTrue( course_captain_supports( $feature ) );
			self::assertStringContainsString( '`' . $feature . '`', $api );
		}
		$changelog = (string) file_get_contents( $root . '/docs/API-CHANGELOG.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Fixture.
		self::assertStringContainsString( SettingsSchema::ADDON_FIELDS_FILTER, $changelog );
		self::assertStringContainsString( SettingsHints::FILTER, $changelog );
	}

	/**
	 * P6: upgrade from register v1 and v2 keeps every effective value; removed
	 * fields (v3) had no effect and are dropped; the run is idempotent.
	 */
	public function test_SET_CC_P6_upgrade_keeps_behaviour(): void {
		$v1 = array(
			'general'   => array(
				'course_slug'                     => 'kurse',
				'catalog_group_language_versions' => true,
				'legacy_key'                      => 'x',
			),
			'exams'     => array(
				'max_attempts'    => 3,
				'shuffle_answers' => false,
			),
			'advanced'  => array(
				'file_delivery'           => 'x_sendfile',
				'import_execution'        => 'browser',
				'media_delete_grace_days' => 30,
				'log_level'               => 'error',
				'error_log_days'          => 60,
			),
			'privacy'   => array(
				'backup_max_age_days' => 120,
				'show_avatars'        => true,
			),
			'hardening' => array( 'login_limit' => 'auto' ),
		);
		foreach ( $v1 as $tab => $values ) {
			// add_option: older stored data bypasses the foreign-writer guard like a real upgrade.
			add_option(
				Settings::OPTION_PREFIX . $tab,
				array(
					'_schema' => 'advanced' === $tab ? 2 : 1,
					'_rev'    => 4,
					'values'  => $values,
				),
				'',
				SettingsSchema::TABS[ $tab ]
			);
		}
		update_option( Settings::VERSION_OPTION, 1 );
		$effective = array(
			'exams.starts_per_hour'        => BusinessRules::get( 'exams.starts_per_hour' ),
			'hardening.login_limits'       => BusinessRules::get( 'hardening.login_limits' ),
			'learning.review_intervals'    => BusinessRules::get( 'learning.review_intervals' ),
			'enrollment.invite_valid_days' => BusinessRules::get( 'enrollment.invite_valid_days' ),
		);
		$before    = array();
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			$before[ $tab ] = Settings::values( $tab );
		}

		$migrated = Settings::maybe_migrate();
		sort( $migrated );
		self::assertSame( array( 'advanced', 'exams', 'general', 'hardening', 'privacy' ), $migrated );
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			self::assertSame( $before[ $tab ], Settings::values( $tab ), $tab . ': same values after the upgrade' );
		}
		self::assertSame( 'kurse', Settings::get( 'general.course_slug' ) );
		self::assertSame( 3, Settings::get( 'exams.max_attempts' ) );
		self::assertSame( 120, Settings::get( 'privacy.backup_max_age_days' ) );
		self::assertSame( 'error', Settings::get( 'advanced.log_level' ) );
		foreach ( $effective as $key => $value ) {
			self::assertSame( $value, BusinessRules::get( $key ), $key . ' (former constant)' );
		}
		self::assertSame( 40, BusinessRules::get( 'exams.starts_per_hour' ) );
		$advanced = get_option( Settings::OPTION_PREFIX . 'advanced' );
		self::assertSame( SettingsSchema::VERSION, $advanced['_schema'] );
		self::assertSame( 4, $advanced['_rev'], 'revision kept' );
		foreach ( SettingsSchema::REMOVED as $key ) {
			list( $tab, $field ) = explode( '.', $key );
			self::assertArrayNotHasKey( $field, (array) get_option( Settings::OPTION_PREFIX . $tab )['values'], $key . ' dropped' );
		}
		self::assertArrayNotHasKey( 'legacy_key', get_option( Settings::OPTION_PREFIX . 'general' )['values'] );
		self::assertSame( SettingsSchema::VERSION, (int) get_option( Settings::VERSION_OPTION ) );
		self::assertSame( array(), Settings::maybe_migrate(), 'idempotent' );
	}
}
