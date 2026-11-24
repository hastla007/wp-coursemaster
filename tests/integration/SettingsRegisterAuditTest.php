<?php
/**
 * SET-CC-P1: audit over the whole settings register (render, effect,
 * default, export/import round trip, reset, WP-CLI) and the effect of the
 * fields that were dead before.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\SettingsFields;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Ops\Monitor;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Security\Hardening;
use CourseCaptain\Settings\Modules;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsCommand;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Settings\SettingsTransfer;

/**
 * Real tables.
 *
 * @group SET-CC
 * @group SET-CC-P1
 */
final class SettingsRegisterAuditTest extends SchemaTestCase {

	/**
	 * Fields not rendered on the settings tab, with the place where they are edited.
	 *
	 * @var array<string, string>
	 */
	public const HIDDEN = array(
		// Edited on the certificates screen (A-11); the tab links there.
		'certificates.base_template'        => 'certificates screen',
		// Only rendered while an extension registers external access sources (filter `course_captain_external_access_sources`).
		'enrollment.source_disabled_policy' => 'only with external access sources',
	);

	/**
	 * Files that define, render, validate or transfer settings; a key that
	 * only appears there has no effect.
	 *
	 * @var list<string>
	 */
	public const NOT_EFFECT = array(
		'src/Settings/SettingsSchema.php',
		'src/Settings/Settings.php',
		'src/Settings/SettingsRules.php',
		'src/Settings/RiskRules.php',
		'src/Settings/RiskConfirm.php',
		'src/Settings/SettingsTransfer.php',
		'src/Settings/SettingsCommand.php',
		'src/Settings/EffectiveSettings.php',
		'src/Admin/SettingsFields.php',
		'src/Admin/SettingsForm.php',
		'src/Admin/SettingsScreen.php',
		'src/Admin/SettingsBackup.php',
		'src/Admin/SettingLocksScreen.php',
		'src/Admin/CourseSettingsPanel.php',
		'src/Admin/SetupWizard.php',
		'src/Admin/SetupState.php',
		'src/Admin/RiskDialog.php',
	);

	/**
	 * Keys read through a computed name or the raw option: file => needle
	 * that must stay in that file.
	 *
	 * @var array<string, array{0: string, 1: string}>
	 */
	public const DYNAMIC_READERS = array(
		'general.course_slug'              => array( 'src/Course/PostTypes.php', "['course_slug']" ),
		'general.delete_data_on_uninstall' => array( 'src/Kernel/Uninstaller.php', 'DELETE_DATA_KEY' ),
		'hardening.login_limit'            => array( 'src/Security/Hardening.php', "Settings::get( 'hardening.' . \$feature )" ),
		'hardening.reset_limit'            => array( 'src/Security/Hardening.php', "Settings::get( 'hardening.' . \$feature )" ),
		'hardening.registration_limit'     => array( 'src/Security/Hardening.php', "Settings::get( 'hardening.' . \$feature )" ),
		'hardening.xmlrpc'                 => array( 'src/Security/Hardening.php', "Settings::get( 'hardening.' . \$feature )" ),
		'hardening.site_headers'           => array( 'src/Security/Hardening.php', "Settings::get( 'hardening.' . \$feature )" ),
		'privacy.trusted_proxies'          => array( 'src/Security/ClientIp.php', "['trusted_proxies']" ),
	);

	/**
	 * Register fields without an effect in the core, with the finding that
	 * documents why (evidence file `docs/r1/SET-CC-pro-grenze-pruefung.md`).
	 *
	 * @var array<string, string>
	 */
	public const PENDING = array(
		// Only one registered algorithm version; the field pins it for later versions (R0-27 SET-051).
		'learning.mastery_algorithm'        => 'P1-F06',
		// Effect from R3 (R0-27 SET-075): external access sources only exist with an extension.
		'enrollment.source_disabled_policy' => 'P1-F07',
		// "Information only" (R0-27 SET-113); deletion markers wait for SET-CC-02 (retention, group h8).
		'privacy.backup_max_age_days'       => 'P1-F08',
	);

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
		delete_transient( SettingsTransfer::RATE_PREFIX . $this->admin );
		wp_set_current_user( $this->admin );
	}

	/**
	 * Remove filters.
	 */
	public function tear_down(): void {
		remove_all_filters( 'course_captain_external_access_sources' );
		parent::tear_down();
	}

	/**
	 * Every register key as `tab.field`.
	 *
	 * @return list<string>
	 */
	private static function keys(): array {
		$out = array();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			foreach ( array_keys( $fields ) as $field ) {
				$out[] = $tab . '.' . $field;
			}
		}
		return $out;
	}

	/**
	 * A valid value other than the default.
	 *
	 * @param string               $key Key.
	 * @param array<string, mixed> $def Definition.
	 * @return mixed
	 */
	private function other_value( string $key, array $def ) {
		$special = array(
			'general.learning_area_slug'     => 'lernen-neu',
			'general.course_slug'            => 'kurs-neu',
			'display.accent_color'           => '#1d4ed8',
			'learning.review_intervals'      => array(
				'first'  => 5,
				'second' => 15,
				'third'  => 45,
			),
			'emails.from_address'            => 'akademie@example.org',
			'privacy.trusted_media_hosts'    => array( 'media.example.org' ),
			'privacy.trusted_proxies'        => array( '10.0.0.1' ),
			'display.card_elements'          => array( 'cover' ),
			'learning.assignment_file_types' => array( 'pdf' ),
			'advanced.report_csv_columns'    => array( 'user_id', 'status' ),
			// SET-CC-08: a fixed certificate language must be installed.
			'certificates.fixed_locale'      => 'en_US',
		);
		if ( array_key_exists( $key, $special ) ) {
			return $special[ $key ];
		}
		return self::other( $def, $def['default'] );
	}

	/**
	 * Generic other value by type.
	 *
	 * @param array<string, mixed> $def     Definition.
	 * @param mixed                $base    Default.
	 * @return mixed
	 */
	private static function other( array $def, $base ) {
		switch ( $def['type'] ) {
			case 'bool':
				return null === $base ? true : ! $base;
			case 'int':
				$min = (int) ( $def['min'] ?? 0 );
				$max = (int) ( $def['max'] ?? $min + 10 );
				return null === $base ? $min : ( (int) $base < $max ? (int) $base + 1 : (int) $base - 1 );
			case 'enum':
				foreach ( (array) $def['values'] as $value ) {
					if ( $value !== $base ) {
						return $value;
					}
				}
				return $base;
			case 'set':
				return array_slice( (array) $def['values'], 0, 1 ) === $base ? array() : array_slice( (array) $def['values'], 0, 1 );
			case 'string':
			case 'text':
				return 'Changed text';
			case 'url':
				return 'https://example.org/changed';
			case 'object':
				$out = array();
				foreach ( (array) $def['fields'] as $sub => $sub_def ) {
					$out[ $sub ] = self::other( $sub_def + array( 'default' => $base[ $sub ] ?? null ), $base[ $sub ] ?? null );
				}
				return $out;
		}
		return $base;
	}

	/**
	 * Every field is rendered on its tab (`_fields[]` marker of the form) or
	 * listed as hidden with the place where it is edited.
	 */
	public function test_SET_CC_P1_every_field_is_rendered_or_hidden(): void {
		$rendered = array();
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			$html = SettingsScreen::html( array( 'tab' => $tab ), $this->admin );
			preg_match_all( '/name="_fields\[\]" value="([a-z0-9_.]+)"/', $html, $m );
			foreach ( $m[1] as $key ) {
				$rendered[ $tab . '.' . explode( '.', $key )[0] ] = true;
			}
			if ( 'learning' === $tab ) {
				self::assertStringContainsString( esc_html( SettingsFields::ui( 'learning', 'mastery_algorithm' )['options']['smarttrainer.v1'] ), $html, 'single algorithm shown as text' );
				$rendered['learning.mastery_algorithm'] = true;
			}
			if ( 'certificates' === $tab ) {
				self::assertStringContainsString( 'page=course-captain-certificates', $html, 'base template is edited on the certificates screen' );
			}
		}
		$missing = array_values( array_diff( self::keys(), array_keys( $rendered ), array_keys( self::HIDDEN ) ) );
		self::assertSame( array(), $missing, 'register fields without a form element' );
		foreach ( array_keys( self::HIDDEN ) as $key ) {
			self::assertContains( $key, self::keys(), $key . ' still in the register' );
		}
		add_filter( 'course_captain_external_access_sources', '__return_true' );
		self::assertStringContainsString( 'value="source_disabled_policy"', SettingsScreen::html( array( 'tab' => 'enrollment' ), $this->admin ) );
		foreach ( SettingsSchema::REMOVED as $key ) {
			self::assertNotContains( $key, self::keys(), $key . ' removed (v3)' );
		}
	}

	/**
	 * No dead values: every key is read outside the register/form code
	 * (static scan for the literal key or a documented dynamic reader);
	 * remaining exceptions carry a finding.
	 */
	public function test_SET_CC_P1_no_dead_values(): void {
		$root   = dirname( __DIR__, 2 );
		$source = array();
		$files  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			$relative = substr( $file->getPathname(), strlen( $root ) + 1 );
			if ( 'php' === $file->getExtension() && ! in_array( $relative, self::NOT_EFFECT, true ) ) {
				$source[ $relative ] = (string) file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Source scan.
			}
		}
		$all  = implode( "\n", $source );
		$dead = array();
		foreach ( self::keys() as $key ) {
			if ( str_contains( $all, "'" . $key . "'" ) ) {
				continue;
			}
			if ( isset( self::DYNAMIC_READERS[ $key ] ) ) {
				list( $file, $needle ) = self::DYNAMIC_READERS[ $key ];
				self::assertStringContainsString( $needle, $source[ $file ] ?? '', $key . ' reader in ' . $file );
				continue;
			}
			if ( isset( self::PENDING[ $key ] ) ) {
				continue;
			}
			$dead[] = $key;
		}
		self::assertSame( array(), $dead, 'register fields without an effect in the code' );
		foreach ( array( 'login_limit', 'reset_limit', 'registration_limit', 'xmlrpc', 'site_headers' ) as $feature ) {
			self::assertContains( $feature, Hardening::FEATURES );
		}
		self::assertSame( 'delete_data_on_uninstall', UninstallManifest::DELETE_DATA_KEY );
		$evidence = (string) file_get_contents( $root . '/docs/r1/SET-CC-pro-grenze-pruefung.md' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Evidence file.
		foreach ( self::PENDING as $key => $finding ) {
			self::assertStringContainsString( $finding, $evidence, $key . ' finding documented' );
		}
	}

	/**
	 * Every default passes its own validator unchanged.
	 */
	public function test_SET_CC_P1_defaults_validate(): void {
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			foreach ( $fields as $field => $def ) {
				$check = SettingsSchema::validate( $def, $def['default'] );
				self::assertTrue( $check['ok'], $tab . '.' . $field );
				self::assertSame( $def['default'], $check['value'], $tab . '.' . $field . ' normalized default' );
			}
		}
	}

	/**
	 * Export/import round trip of every exportable field, then reset of
	 * every tab and WP-CLI `settings get` of every key.
	 */
	public function test_SET_CC_P1_round_trip_reset_and_cli(): void {
		$actor    = Actor::cli();
		$page     = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$expected = array();
		foreach ( SettingsSchema::fields() as $tab => $fields ) {
			$input = array();
			foreach ( $fields as $field => $def ) {
				$value = 'page_id' === $def['type'] ? $page : $this->other_value( $tab . '.' . $field, $def );
				// Media ID and the uninstall switch are never exported; a single-value enum has no other value.
				if ( 'logo_id' === $field || 'delete_data_on_uninstall' === $field || ( 'enum' === $def['type'] && 1 === count( (array) $def['values'] ) ) ) {
					continue;
				}
				self::assertNotSame( $def['default'], $value, $tab . '.' . $field . ' test value differs from the default' );
				$input[ $field ] = $value;
			}
			$result = Settings::check( $actor, $tab, $input, array_keys( $input ) );
			self::assertSame( array(), $result['errors'], $tab . ' test values are valid' );
			Settings::save( $actor, $tab, $input, array_keys( $input ), Settings::rev( $tab ) );
			foreach ( $fields as $field => $def ) {
				if ( true === $def['export'] && 'secret' !== $def['privacy'] && isset( $input[ $field ] ) ) {
					$expected[ $tab ][ $field ] = Settings::values( $tab )[ $field ];
				}
			}
		}
		$file = SettingsTransfer::export( $actor, array( 'include_personal' => true ) );
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			Settings::reset( $actor, $tab );
			foreach ( SettingsSchema::fields()[ $tab ] as $field => $def ) {
				self::assertSame( $def['default'], Settings::get( $tab . '.' . $field ), $tab . '.' . $field . ' reset to the default' );
			}
		}
		$report = SettingsTransfer::import(
			$actor,
			SettingsTransfer::parse( (string) wp_json_encode( $file ) ),
			array(
				'apply'     => true,
				'confirmed' => array( '*' ),
			)
		);
		foreach ( $report['tabs'] as $tab => $entry ) {
			self::assertSame( array(), $entry['errors'], $tab . ' import errors' );
		}
		foreach ( $expected as $tab => $values ) {
			foreach ( $values as $field => $value ) {
				self::assertSame( $value, Settings::get( $tab . '.' . $field ), $tab . '.' . $field . ' survives export/import' );
			}
		}
		foreach ( self::keys() as $key ) {
			$out = SettingsCommand::execute( 'get', array( $key ), array( 'format' => 'json' ), $actor );
			self::assertSame( 0, $out['exit'], $key . ' via WP-CLI' );
			$data = json_decode( $out['lines'][0], true );
			self::assertSame( $key, $data['key'] );
			self::assertSame( Settings::get( $key ), $data['value'], $key . ' CLI value' );
		}
	}

	/**
	 * Effect of the fields that were dead before (P1-F01…F05).
	 */
	public function test_SET_CC_P1_former_dead_values_take_effect(): void {
		$actor = Actor::cli();
		self::assertSame( 3 * HOUR_IN_SECONDS, Monitor::cron_stale() );
		Settings::save( $actor, 'advanced', array( 'cron_stale_warning_minutes' => 600 ), array( 'cron_stale_warning_minutes' ), Settings::rev( 'advanced' ) );
		self::assertSame( 600 * MINUTE_IN_SECONDS, Monitor::cron_stale() );

		self::assertTrue( Modules::course_feature( 'favorites', 0 ) );
		Settings::save(
			$actor,
			'learning',
			array(
				'favorites_enabled'        => false,
				'mistake_training_enabled' => false,
			),
			array( 'favorites_enabled', 'mistake_training_enabled' ),
			Settings::rev( 'learning' )
		);
		self::assertFalse( Modules::course_feature( 'favorites', 0 ) );
		self::assertFalse( Modules::course_feature( 'mistake_training', 0 ) );
		try {
			Modules::assert_course_feature( 'mistake_training', 0 );
			self::fail( 'mistake training off' );
		} catch ( \CourseCaptain\Course\CourseException $e ) {
			self::assertSame( 'ccap_module_disabled', $e->error_code );
		}

		$course = (int) self::factory()->post->create(
			array(
				'post_type'    => 'ccap_course',
				'post_status'  => 'publish',
				'post_title'   => 'Card course',
				'post_excerpt' => 'Short summary of the course.',
			)
		);
		$html   = \CourseCaptain\Learner\LearningArea::render_catalog( array() );
		self::assertStringContainsString( 'Short summary of the course.', $html, 'summary shown by default' );
		self::assertStringContainsString( 'ccap-card-lessons', $html );
		Settings::save( $actor, 'display', array( 'card_elements' => array() ), array( 'card_elements' ), Settings::rev( 'display' ) );
		$html = \CourseCaptain\Learner\LearningArea::render_catalog( array() );
		self::assertStringNotContainsString( 'Short summary', $html, 'empty selection = title only' );
		self::assertStringContainsString( 'Card course', $html );

		$home = (int) self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Start',
			)
		);
		self::assertStringContainsString( \CourseCaptain\Learner\LearningArea::url(), \CourseCaptain\Learner\LearningArea::render_link( array() ) );
		Settings::save( $actor, 'general', array( 'learner_home_page_id' => $home ), array( 'learner_home_page_id' ), Settings::rev( 'general' ) );
		self::assertStringContainsString( (string) get_permalink( $home ), \CourseCaptain\Learner\LearningArea::render_link( array() ) );
		Settings::save( $actor, 'general', array( 'catalog_page_id' => $home ), array( 'catalog_page_id' ), Settings::rev( 'general' ) );
		self::assertStringContainsString( (string) get_permalink( $home ), \CourseCaptain\Learner\LearningArea::my_learning( Actor::user( $this->admin ) ) );

		self::assertSame( 'theme', \CourseCaptain\Learner\LearningArea::container_width( '' ) );
		Settings::save( $actor, 'display', array( 'container_width' => 'wide' ), array( 'container_width' ), Settings::rev( 'display' ) );
		self::assertSame( 'wide', \CourseCaptain\Learner\LearningArea::container_width( '' ) );
		unset( $course );
	}
}
