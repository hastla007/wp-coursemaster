<?php
/**
 * SET-CC-11/12/15/27: settings CLI, transfer surfaces, locks screen and the
 * settings view of the audit log.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\SettingLocksScreen;
use CourseCaptain\Admin\SettingsBackup;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\AuditScreen;
use CourseCaptain\Ops\AuditView;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\RoleCaps;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Settings\LocksTransfer;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsCommand;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Settings\SettingsTransfer;
use CourseCaptain\Settings\TransferProvider;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables.
 *
 * @group SET-CC
 */
final class SettingsCliTransferTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Files to remove.
	 *
	 * @var list<string>
	 */
	private array $files = array();

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
		delete_option( EffectiveSettings::LOCK_OPTION );
		delete_option( RoleCaps::UNDO_OPTION );
		delete_transient( SettingsTransfer::RATE_PREFIX . $this->admin );
		wp_set_current_user( $this->admin );
		$this->course = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Boat licence',
				'post_author' => $this->admin,
			)
		);
	}

	/**
	 * Remove written files.
	 */
	public function tear_down(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test cleanup.
			}
		}
		remove_all_filters( 'course_captain_settings_transfer_providers' );
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
	 * CLI call.
	 *
	 * @param string               $command Command.
	 * @param array<int, string>   $args    Args.
	 * @param array<string, mixed> $assoc   Options.
	 * @return array{exit: int, lines: list<string>}
	 */
	private static function cli( string $command, array $args = array(), array $assoc = array() ): array {
		return SettingsCommand::execute( $command, $args, $assoc, Actor::cli() );
	}

	/**
	 * SET-CC-12 / SET-021 / PSD-007: list, get, set with the form validator,
	 * risk fields only with --confirm, audit as system:cli.
	 *
	 * @group SET-021
	 * @group PSD-007
	 */
	public function test_cli_list_get_set(): void {
		$list = self::cli( 'list', array(), array( 'tab' => 'general' ) );
		self::assertSame( 0, $list['exit'] );
		self::assertStringContainsString( 'general.learning_area_slug', implode( "\n", $list['lines'] ) );
		self::assertSame( 1, self::cli( 'list', array(), array( 'tab' => 'nope' ) )['exit'] );
		self::assertSame( array( 'lernen' ), self::cli( 'get', array( 'general.learning_area_slug' ) )['lines'] );
		self::assertSame( 1, self::cli( 'get', array( 'general.nope' ) )['exit'] );

		// Same validator as the form.
		$bad = self::cli( 'set', array( 'general.learning_area_slug', 'wp-admin' ) );
		self::assertSame( 1, $bad['exit'] );
		self::assertStringContainsString( 'ccap_validation_failed', $bad['lines'][0] );
		self::assertSame( 1, self::cli( 'set', array( 'exams.max_attempts', 'many' ) )['exit'] );
		$ok = self::cli( 'set', array( 'exams.max_attempts', '4' ), array( 'format' => 'json' ) );
		self::assertSame( 0, $ok['exit'], implode( "\n", $ok['lines'] ) );
		self::assertSame( 4, Settings::get( 'exams.max_attempts' ) );
		self::assertSame( 4, json_decode( $ok['lines'][0], true )['value'] );
		self::assertSame( 0, self::cli( 'set', array( 'exams.shuffle_answers', 'false' ) )['exit'] );
		self::assertFalse( Settings::get( 'exams.shuffle_answers' ) );
		$saved = self::audits( 'settings_saved' );
		$last  = end( $saved );
		self::assertSame( 'system', $last['actor_type'] );
		self::assertSame( 'cli', json_decode( (string) $last['context'], true )['context'] );
		self::assertSame( Actor::SYSTEM_CLI, json_decode( (string) $last['context'], true )['actor'], 'SR-5 actor system:cli' );
		$count = count( $saved );

		// Risk field (RC-20) without and with --confirm.
		$refused = self::cli( 'set', array( 'privacy.show_avatars', 'true' ) );
		self::assertSame( 1, $refused['exit'] );
		self::assertStringContainsString( 'ccap_confirmation_required', $refused['lines'][0] );
		self::assertFalse( Settings::get( 'privacy.show_avatars' ) );
		self::assertCount( $count, self::audits( 'settings_saved' ), 'refused: no audit' );
		self::assertSame( 0, self::cli( 'set', array( 'privacy.show_avatars', 'true' ), array( 'confirm' => true ) )['exit'] );
		self::assertTrue( Settings::get( 'privacy.show_avatars' ) );
		$saved = self::audits( 'settings_saved' );
		self::assertContains( 'RC-20', json_decode( (string) end( $saved )['context'], true )['confirmed_risks'] );

		// E-89: data deletion only with the typed site name.
		self::assertSame( 1, self::cli( 'set', array( 'general.delete_data_on_uninstall', 'true' ), array( 'confirm' => true ) )['exit'] );
		self::assertFalse( Settings::get( 'general.delete_data_on_uninstall' ) );
		self::assertSame( 0, self::cli( 'set', array( 'general.delete_data_on_uninstall', 'true' ), array( 'confirm' => 'delete' ) )['exit'] );
		self::assertTrue( Settings::get( 'general.delete_data_on_uninstall' ) );

		// Reset: key → default (risk rules apply), tab only with --yes.
		self::assertSame( 0, self::cli( 'reset', array( 'exams.max_attempts' ) )['exit'] );
		self::assertNull( Settings::get( 'exams.max_attempts' ) );
		self::assertSame( 1, self::cli( 'reset', array( 'exams' ) )['exit'] );
		self::assertFalse( Settings::get( 'exams.shuffle_answers' ) );
		self::assertSame( 0, self::cli( 'reset', array( 'exams' ), array( 'yes' => true ) )['exit'] );
		self::assertTrue( Settings::get( 'exams.shuffle_answers' ) );
		self::assertGreaterThan( 0, Settings::undo_until( 'exams' ) );
		self::assertSame( Actor::SYSTEM_CLI, json_decode( (string) self::audits( 'settings_reset' )[0]['context'], true )['actor'] );
	}

	/**
	 * SET-CC-12 / EX-6: export to stdout and the safe writer; import preview,
	 * apply only with --yes, risk fields only with --confirm.
	 *
	 * @group SET-CC-12
	 */
	public function test_cli_export_import(): void {
		Settings::save( Actor::user( $this->admin ), 'emails', array( 'from_name' => 'Academy' ), array( 'from_name' ), Settings::rev( 'emails' ) );
		$out  = self::cli( 'export' );
		$data = json_decode( implode( "\n", $out['lines'] ), true );
		self::assertIsArray( $data );
		self::assertSame( 'course-captain-settings', $data['format'] );
		self::assertArrayHasKey( 'locks', $data, 'locks are always exported' );
		self::assertArrayNotHasKey( 'roles', $data, 'roles only with opt-in' );
		self::assertArrayNotHasKey( 'from_name', $data['tabs']['emails']['values'] );
		$with = json_decode(
			implode(
				"\n",
				self::cli(
					'export',
					array(),
					array(
						'include'          => 'roles',
						'include-personal' => true,
					)
				)['lines']
			),
			true
		);
		self::assertArrayHasKey( 'editor', $with['roles']['roles'] );
		self::assertSame( 'Academy', $with['tabs']['emails']['values']['from_name'] );
		$exported = self::audits( 'settings_exported' );
		self::assertSame( 'cli', json_decode( (string) end( $exported )['context'], true )['context'] );

		$file          = sys_get_temp_dir() . '/ccap-settings-' . wp_generate_password( 8, false ) . '.json';
		$this->files[] = $file;
		self::assertSame( 0, self::cli( 'export', array(), array( 'output' => $file ) )['exit'] );
		self::assertSame( '0600', substr( sprintf( '%o', fileperms( $file ) ), -4 ) );
		self::assertSame( 1, self::cli( 'export', array(), array( 'output' => $file ) )['exit'], 'never overwrites' );
		self::assertSame( 1, self::cli( 'export', array(), array( 'output' => ABSPATH . 'settings.json' ) )['exit'], 'never inside WordPress' );

		$import = $data;
		$import['tabs']['exams']['values']['shuffle_answers'] = false;
		$import['tabs']['privacy']['values']['csp_mode']      = 'report_only';
		$import['locks']['locks']['display.breadcrumbs']      = array( 'reason' => 'Corporate design' );
		$source        = sys_get_temp_dir() . '/ccap-import-' . wp_generate_password( 8, false ) . '.json';
		$this->files[] = $source;
		file_put_contents( $source, (string) wp_json_encode( $import ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		$preview = self::cli( 'import', array( $source ) );
		self::assertSame( 0, $preview['exit'] );
		$text = implode( "\n", $preview['lines'] );
		self::assertStringContainsString( 'shuffle_answers: true -> false', $text );
		self::assertStringContainsString( 'csp_mode: skipped (confirmation RC-13; add --confirm)', $text );
		self::assertStringContainsString( '[locks]', $text );
		self::assertStringContainsString( 'Preview only', $text );
		self::assertTrue( Settings::get( 'exams.shuffle_answers' ), 'preview writes nothing' );
		self::assertSame( array(), EffectiveSettings::locks() );

		$apply = self::cli( 'import', array( $source ), array( 'yes' => true ) );
		self::assertSame( 0, $apply['exit'], implode( "\n", $apply['lines'] ) );
		self::assertFalse( Settings::get( 'exams.shuffle_answers' ) );
		self::assertSame( 'enforce', Settings::get( 'privacy.csp_mode' ), 'risk field skipped without --confirm' );
		self::assertSame( 'Corporate design', EffectiveSettings::locks()['display.breadcrumbs']['reason'] );
		self::assertSame( 0, self::cli( 'import', array( $source ), array( 'yes' => true, 'confirm' => true ) )['exit'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short args.
		self::assertSame( 'report_only', Settings::get( 'privacy.csp_mode' ) );
		$imported = self::audits( 'settings_imported' );
		self::assertSame( Actor::SYSTEM_CLI, json_decode( (string) $imported[0]['context'], true )['actor'] );
		self::assertSame( Actor::SYSTEM_CLI, json_decode( (string) self::audits( 'settings_lock_set' )[0]['context'], true )['actor'] );
		self::assertSame( array( 'display.breadcrumbs' ), json_decode( (string) $imported[0]['context'], true )['surfaces']['locks'] );
		self::assertSame( 1, self::cli( 'import', array( '/nonexistent/file.json' ) )['exit'] );
	}

	/**
	 * SET-CC-15: roles and locks as surfaces with diff preview, admin-only
	 * role import, RC-07 confirmation, undo, provider list for later surfaces.
	 *
	 * @group SET-CC-15
	 */
	public function test_transfer_surfaces(): void {
		$admin = Actor::user( $this->admin );
		EffectiveSettings::lock( $admin, 'exams.max_attempts', 'Exam regulations' );
		$file = SettingsTransfer::export( $admin, array( 'include' => array( 'roles' ) ) );
		self::assertSame( array( 'exams.max_attempts' => array( 'reason' => 'Exam regulations' ) ), $file['locks']['locks'] );
		self::assertArrayNotHasKey( 'administrator', $file['roles']['roles'] );

		// Change the file: different lock set, editor gains a normal and a high-impact capability.
		unset( $file['locks']['locks']['exams.max_attempts'] );
		$file['locks']['locks']['display.breadcrumbs']     = array( 'reason' => 'Design' );
		$file['locks']['locks']['exams.shuffle_questions'] = array( 'reason' => 'not lockable' );
		$file['roles']['roles']['editor']                  = array_merge( RoleCaps::current( 'editor' ), array( 'ccap_view_reports', 'ccap_view_audit_log', 'ccap_unknown_future' ) );
		$file['roles']['roles']['administrator']           = array();
		$data    = SettingsTransfer::parse( (string) wp_json_encode( $file ) );
		$preview = SettingsTransfer::import( $admin, $data );
		$locks   = $preview['surfaces']['locks'];
		self::assertSame( array( 'display.breadcrumbs', 'exams.max_attempts' ), array_column( $locks['changed'], 'item' ) );
		self::assertNull( $locks['changed'][1]['after'], 'missing in the file = unlock' );
		self::assertSame( 'unknown', array_column( $locks['skipped'], 'reason', 'item' )['exams.shuffle_questions'] );
		$roles = $preview['surfaces']['roles'];
		self::assertSame( 'confirmation', array_column( $roles['skipped'], 'reason', 'item' )['editor'], 'RC-07 for high-impact caps' );
		self::assertSame( 'administrator', array_column( $roles['skipped'], 'reason', 'item' )['administrator'] );
		self::assertSame( 'unknown', array_column( $roles['skipped'], 'reason', 'item' )['editor:ccap_unknown_future'] );

		// A course manager may not import roles; a settings manager without manage_options neither.
		$manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		get_userdata( $manager )->add_cap( Settings::CAP );
		$foreign = SettingsTransfer::import( Actor::user( $manager ), $data );
		self::assertSame( 'forbidden', $foreign['surfaces']['roles']['skipped'][0]['reason'] );

		// Apply only selected surfaces; roles with RC-07 confirmed.
		$report = SettingsTransfer::import(
			$admin,
			$data,
			array(
				'apply'     => true,
				'tabs'      => array(),
				'surfaces'  => array( 'roles', 'locks' ),
				'confirmed' => array( 'roles.editor' ),
			)
		);
		self::assertSame( array( 'locks', 'roles' ), $report['applied_surfaces'] );
		self::assertSame( array(), $report['applied'] );
		self::assertSame( array( 'display.breadcrumbs' ), array_keys( EffectiveSettings::locks() ) );
		self::assertContains( 'ccap_view_audit_log', RoleCaps::current( 'editor' ) );
		$changed = self::audits( 'role_caps_changed' );
		self::assertContains( 'RC-07', json_decode( (string) end( $changed )['context'], true )['confirmed_risks'] );
		self::assertTrue( RoleCaps::undo( $admin ), 'role undo slot filled by the import' );
		self::assertNotContains( 'ccap_view_audit_log', RoleCaps::current( 'editor' ) );
		$imports = self::audits( 'settings_imported' );
		$audit   = json_decode( (string) end( $imports )['context'], true );
		self::assertSame( array( 'display.breadcrumbs', 'exams.max_attempts' ), $audit['surfaces']['locks'] );

		// Provider list: a later surface (e.g. mail templates) plugs in; reserved keys are refused.
		$provider = new class() implements TransferProvider {
			/**
			 * Applied sections.
			 *
			 * @var list<array<string, mixed>>
			 */
			public array $applied = array();

			/**
			 * Key.
			 *
			 * @return string
			 */
			public function key(): string {
				return 'mail_templates';
			}

			/**
			 * Label.
			 *
			 * @return string
			 */
			public function label(): string {
				return 'Mail templates';
			}

			/**
			 * Opt-in.
			 *
			 * @return bool
			 */
			public function opt_in(): bool {
				return true;
			}

			/**
			 * Exportable.
			 *
			 * @param Actor $actor Actor.
			 * @return bool
			 */
			public function can_export( Actor $actor ): bool {
				return true;
			}

			/**
			 * Export.
			 *
			 * @param Actor $actor Actor.
			 * @return array<string, mixed>
			 */
			public function export( Actor $actor ): array {
				return array( 'templates' => array( 'access_granted' => array( 'de_DE' => 'Hallo' ) ) );
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
				return array(
					'changed' => array(
						array(
							'item'   => 'access_granted',
							'before' => null,
							'after'  => 'Hallo',
							'risks'  => array(),
						),
					),
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
				$this->applied[] = $section;
				return array( 'access_granted' );
			}
		};
		$reserved = new LocksTransfer();
		add_filter( 'course_captain_settings_transfer_providers', static fn( array $providers ): array => array_merge( $providers, array( $provider, $reserved, 'junk' ) ) );
		self::assertSame( array( 'locks', 'roles', 'mail_templates' ), array_keys( SettingsTransfer::providers() ) );
		$with = SettingsTransfer::export( $admin, array( 'include' => array( 'mail_templates' ) ) );
		self::assertSame( 'Hallo', $with['mail_templates']['templates']['access_granted']['de_DE'] );
		self::assertArrayNotHasKey( 'mail_templates', SettingsTransfer::export( $admin ), 'opt-in surface' );
		delete_transient( SettingsTransfer::RATE_PREFIX . $this->admin );
		$report = SettingsTransfer::import(
			$admin,
			SettingsTransfer::parse( (string) wp_json_encode( $with ) ),
			array(
				'apply'    => true,
				'tabs'     => array(),
				'surfaces' => array( 'mail_templates' ),
			)
		);
		self::assertSame( array( 'mail_templates' ), $report['applied_surfaces'] );
		self::assertCount( 1, $provider->applied );
	}

	/**
	 * SET-CC-15: the backup screen previews surfaces and applies only the selected ones.
	 *
	 * @group SET-CC-15
	 */
	public function test_backup_screen_surfaces(): void {
		$file = SettingsTransfer::export( Actor::user( $this->admin ), array( 'include' => array( 'roles' ) ) );
		$file['locks']['locks']['display.breadcrumbs'] = array( 'reason' => 'Design' );
		$file['roles']['roles']['editor']              = array_merge( RoleCaps::current( 'editor' ), array( 'ccap_view_audit_log' ) );
		$nonce = array( '_wpnonce' => wp_create_nonce( SettingsBackup::IMPORT ) );
		self::assertSame( 'preview', SettingsBackup::process( $nonce, (string) wp_json_encode( $file ), $this->admin ) );
		$html = SettingsBackup::html( array( 'ccap_result' => 'preview' ), $this->admin );
		self::assertStringContainsString( 'name="surfaces[]" value="locks"', $html );
		self::assertStringContainsString( 'name="confirmed[]" value="roles.editor"', $html );
		self::assertStringContainsString( 'Breadcrumbs: locked for all courses; 0 own values rest.', $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );
		$page = SettingsBackup::html( array(), $this->admin );
		self::assertStringContainsString( 'name="include[]" value="roles"', $page );
		self::assertSame( array(), A11yDom::fragment_problems( $page ) );
		// A crafted wildcard confirmation is ignored.
		$out = SettingsBackup::process( $nonce + array( 'apply' => '1', 'surfaces' => array( 'locks', 'roles' ), 'confirmed' => array( '*' ) ), '', $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short args.
		self::assertSame( 'imported', $out );
		self::assertArrayHasKey( 'display.breadcrumbs', EffectiveSettings::locks() );
		self::assertNotContains( 'ccap_view_audit_log', RoleCaps::current( 'editor' ), 'RC-07 not confirmed' );
	}

	/**
	 * SET-CC-11: lock with "affects N courses", override list and bulk reset.
	 *
	 * @group SET-CC-11
	 */
	public function test_locks_screen(): void {
		$admin = Actor::user( $this->admin );
		EffectiveSettings::save_course( $admin, $this->course, array( 'display.breadcrumbs' => false ) );
		$rule = ExamRules::current()->create(
			$admin,
			$this->course,
			'v1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			null,
			array( 'overrides' => array( 'exams.max_attempts' => 3 ) )
		);

		$overview = SettingLocksScreen::html( array(), $this->admin );
		self::assertStringContainsString( 'display.breadcrumbs', $overview );
		self::assertStringContainsString( 'view=lock', $overview );
		self::assertSame( array(), A11yDom::fragment_problems( $overview ) );
		self::assertSame( '', SettingLocksScreen::html( array(), (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) ) );

		$dialog = SettingLocksScreen::html(
			array(
				'view' => 'lock',
				'key'  => 'display.breadcrumbs',
			),
			$this->admin
		);
		self::assertStringContainsString( 'This affects 1 course and 0 exams with an own value.', $dialog );
		self::assertStringContainsString( 'name="reason"', $dialog );
		self::assertSame( array(), A11yDom::fragment_problems( $dialog ) );

		$post = array(
			'op'     => 'lock',
			'key'    => 'display.breadcrumbs',
			'reason' => 'Corporate design',
		);
		self::assertSame( 'ccap_invalid_nonce', SettingLocksScreen::process( $post, $this->admin )['result'] );
		$done = SettingLocksScreen::process( $post + array( '_wpnonce' => wp_create_nonce( SettingLocksScreen::ACTION . '_lock_display.breadcrumbs' ) ), $this->admin );
		self::assertSame( 'locked', $done['result'] );
		self::assertSame( 1, $done['count'] );
		self::assertSame( 'Corporate design', EffectiveSettings::locks()['display.breadcrumbs']['reason'] );
		$manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		wp_set_current_user( $manager );
		$denied = SettingLocksScreen::process(
			array(
				'op'       => 'unlock',
				'key'      => 'display.breadcrumbs',
				'_wpnonce' => wp_create_nonce( SettingLocksScreen::ACTION . '_unlock_display.breadcrumbs' ),
			),
			$manager
		);
		self::assertSame( 'ccap_forbidden', $denied['result'] );
		wp_set_current_user( $this->admin );
		$unlock = SettingLocksScreen::html(
			array(
				'view' => 'unlock',
				'key'  => 'display.breadcrumbs',
			),
			$this->admin
		);
		self::assertStringContainsString( 'their own values apply again', $unlock );

		// Override list and bulk reset of a course and a draft exam rule.
		$list = SettingLocksScreen::html(
			array(
				'view' => 'overrides',
				'key'  => 'exams.max_attempts',
			),
			$this->admin
		);
		self::assertStringContainsString( 'value="exam_rule:' . $rule . '"', $list );
		self::assertSame( array(), A11yDom::fragment_problems( $list ) );
		$confirm = SettingLocksScreen::html(
			array(
				'view'  => 'reset',
				'key'   => 'exams.max_attempts',
				'items' => array( 'exam_rule:' . $rule ),
			),
			$this->admin
		);
		self::assertStringContainsString( 'Reset 1 own value of “Maximum attempts”?', $confirm );
		self::assertSame( array(), A11yDom::fragment_problems( $confirm ) );
		$reset = SettingLocksScreen::process(
			array(
				'op'       => 'reset',
				'key'      => 'exams.max_attempts',
				'items'    => array( 'exam_rule:' . $rule ),
				'_wpnonce' => wp_create_nonce( SettingLocksScreen::ACTION . '_reset_exams.max_attempts' ),
			),
			$this->admin
		);
		self::assertSame( 'reset', $reset['result'] );
		self::assertSame( array(), EffectiveSettings::list_overrides( 'exams.max_attempts' ) );
		$course_reset = EffectiveSettings::reset_overrides( $admin, 'display.breadcrumbs', array( 'course:' . $this->course, 'bogus' ) );
		self::assertSame( array( 'course:' . $this->course ), $course_reset['reset'] );
		self::assertSame( array( 'bogus' => 'ccap_invalid_param' ), $course_reset['failed'] );
		self::assertArrayNotHasKey( 'display.breadcrumbs', EffectiveSettings::course_overrides( $this->course ) );
		self::assertCount( 2, self::audits( 'settings_overrides_reset' ) );

		// Published rules are listed but frozen.
		$published = ExamRules::current()->create( $admin, $this->course, 'v2', array( 'min_ratio' => array( 'num' => 1, 'den' => 2 ) ), null, array( 'overrides' => array( 'exams.cooldown_seconds' => 60 ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short ratio.
		$db        = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_rule' ) . "` SET status = 'published' WHERE id = %d", $published ) );
		$rows = EffectiveSettings::list_overrides( 'exams.cooldown_seconds' );
		self::assertFalse( $rows[0]['resettable'] );
		$frozen = EffectiveSettings::reset_overrides( $admin, 'exams.cooldown_seconds', array( 'exam_rule:' . $published ) );
		self::assertSame( 'ccap_rule_frozen', $frozen['failed'][ 'exam_rule:' . $published ] );
	}

	/**
	 * SET-CC-27: settings view with before/after, never personal values; CSV export audited.
	 *
	 * @group SET-CC-27
	 */
	public function test_audit_settings_view_and_csv(): void {
		$admin = Actor::user( $this->admin );
		Settings::save( $admin, 'exams', array( 'max_attempts' => 5 ), array( 'max_attempts' ), Settings::rev( 'exams' ) );
		Settings::save( $admin, 'emails', array( 'from_name' => 'Secret Academy' ), array( 'from_name' ), Settings::rev( 'emails' ) );
		EffectiveSettings::lock( $admin, 'display.breadcrumbs', 'Design' );
		// A legacy entry that carried a personal value must not show it either.
		\CourseCaptain\Event\AuditLog::write( Database::current(), $admin, 'settings_saved', 'settings', 'emails', null, array( 'tab' => 'emails', 'changed' => array( 'footer_text' => array( 'before' => '', 'after' => 'Private footer =1+1' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short context.

		$view = AuditView::settings_changes( $admin );
		$rows = array_column( $view['items'], null, 'key' );
		self::assertSame( 'null', $rows['exams.max_attempts']['before'] );
		self::assertSame( '5', $rows['exams.max_attempts']['after'] );
		self::assertSame( 'changed', $rows['emails.from_name']['after'] );
		self::assertSame( 'changed', $rows['emails.footer_text']['after'] );
		self::assertStringContainsString( 'locked to', $rows['display.breadcrumbs']['after'] );
		self::assertCount( 1, AuditView::settings_changes( $admin, array( 'key' => 'exams' ) )['items'] );
		$json = (string) wp_json_encode( $view );
		self::assertStringNotContainsString( 'Secret Academy', $json );
		self::assertStringNotContainsString( 'Private footer', $json );

		$csv = AuditView::csv( $admin, array(), true );
		self::assertStringStartsWith( "\xEF\xBB\xBF" . 'time,label,actor,via,object,key,before,after', $csv );
		self::assertStringContainsString( '"exams.max_attempts","null","5"', $csv );
		self::assertStringNotContainsString( 'Secret Academy', $csv );
		self::assertStringNotContainsString( 'Private footer', $csv );
		self::assertCount( 1, self::audits( 'audit_exported' ) );
		$plain = AuditView::csv( $admin, array( 'area' => 'ops' ), false );
		self::assertStringStartsWith( "\xEF\xBB\xBF" . 'time,area,label,actor,object,details', $plain );

		$html = AuditScreen::html( array( 'area' => 'settings' ) );
		self::assertStringContainsString( 'Settings changes with values before and after', $html );
		self::assertStringContainsString( 'ccap_audit_export', $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );
		self::assertSame( array(), A11yDom::fragment_problems( AuditScreen::html( array() ) ) );

		$reader = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		try {
			AuditView::csv( Actor::user( $reader ), array(), true );
			self::fail( 'export needs ccap_view_audit_log' );
		} catch ( \CourseCaptain\Course\CourseException $e ) {
			self::assertSame( 403, $e->status );
		}
	}
}
