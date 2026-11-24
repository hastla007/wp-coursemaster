<?php
/**
 * SET-CC-02 (owner decision D1): retention periods `privacy.retention`
 * (R0-27 §1.4, R0-31 §2.1), filter `course_captain_retention_days`,
 * preview of the deletion counts, register v4 migration of the log periods,
 * export/import, WP-CLI and the privacy tab.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Admin\RetentionPreview;
use CourseCaptain\Admin\RiskDialog;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Ops\Log;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionPolicy;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Settings\RiskConfirm;
use CourseCaptain\Settings\RiskRules;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsCommand;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Settings\SettingsTransfer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables and options.
 *
 * @group SET-CC
 * @group SET-CC-02
 */
final class SettingsRetentionTest extends SchemaTestCase {

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
		RetentionPreview::flush();
		wp_set_current_user( $this->admin );
	}

	/**
	 * Remove filters.
	 */
	public function tear_down(): void {
		self::fk( true );
		remove_all_filters( RetentionPolicy::FILTER );
		RiskConfirm::drop( $this->admin );
		parent::tear_down();
	}

	/**
	 * UTC datetime `$days` days ago.
	 *
	 * @param int $days Days.
	 * @return string
	 */
	private static function ago( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	}

	/**
	 * Save retention sub-keys as `system:cli`.
	 *
	 * @param array<string, mixed> $values Sub-keys.
	 * @return void
	 */
	private static function set_retention( array $values ): void {
		$retention = array_merge( (array) Settings::get( 'privacy.retention' ), $values );
		Settings::save( Actor::cli(), 'privacy', array( 'retention' => $retention ), array( 'retention' ), Settings::rev( 'privacy' ) );
		RetentionPreview::flush();
	}

	/**
	 * Fixture rows without their parent objects (rule, revision, question):
	 * foreign key checks off while inserting only; every job runs with them on.
	 *
	 * @param bool $on Checks on.
	 * @return void
	 */
	private static function fk( bool $on ): void {
		$GLOBALS['wpdb']->query( 'SET FOREIGN_KEY_CHECKS = ' . ( $on ? '1' : '0' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Row count of a table.
	 *
	 * @param string $table Table.
	 * @return int
	 */
	private static function count_rows( string $table ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '`' );
	}

	/**
	 * Register: 21 sub-keys of R0-27 §1.4 plus `import_package_days`;
	 * defaults equal the former constants; ranges from the spec; values
	 * outside are a field error.
	 */
	public function test_register_defaults_and_ranges(): void {
		$def = SettingsSchema::fields()['privacy']['retention'];
		self::assertSame( 'object', $def['type'] );
		self::assertCount( 22, $def['fields'] );
		self::assertCount( 21, array_diff( array_keys( $def['fields'] ), array( 'import_package_days' ) ), 'R0-27 §1.4: 21 sub-keys' );
		$defaults = RetentionPolicy::effective();
		self::assertSame( RetentionService::AUDIT_YEARS, $defaults['audit_log_years'] );
		self::assertSame( RetentionService::IMPORT_LOG_DAYS, $defaults['import_log_days'] );
		self::assertSame( RetentionService::IMPORT_UNCHANGED_DAYS, $defaults['import_unchanged_days'] );
		self::assertSame( RetentionService::KEEP_PACKAGES, $defaults['import_keep_packages'] );
		self::assertSame( RetentionService::NOTIFICATION_LOG_DAYS, $defaults['notification_log_days'] );
		self::assertSame( RetentionService::INVITE_EMAIL_DAYS, $defaults['invite_email_days_after_expiry'] );
		self::assertSame( RetentionService::REMOVED_SEATS_DAYS, $defaults['removed_seats_days'] );
		self::assertSame( RetentionService::LIFTED_LOCKS_DAYS, $defaults['lifted_locks_days'] );
		self::assertSame( RetentionService::ANSWERS_DAYS, 365 * $defaults['raw_answers_years_after_course_end'] );
		self::assertSame( (int) round( RetentionService::CERTIFICATES_DAYS / 365.25 ), $defaults['certificates_years'] );
		self::assertSame( OutboxDispatcher::DELIVERED_DAYS, $defaults['inbox_days'] );
		self::assertSame( OutboxDispatcher::OPEN_DAYS, $defaults['outbox_event_days'] );
		self::assertSame( NotificationService::HELD_DAYS, $defaults['held_mail_days'] );
		self::assertSame( 30, $defaults['error_log_days'] );
		self::assertSame( 7, $defaults['debug_file_days'] );
		self::assertSame( 30, $defaults['import_package_days'], 'D1: shorter of GM-131 and R0-10 §3.6' );
		self::assertNull( $defaults['inactive_learning_data_years'], 'E-154: off' );
		self::assertSame( 'until_account_deletion', $defaults['learning_progress'] );

		$ranges = array(
			'audit_log_years'                    => array( 1, 10 ),
			'import_log_days'                    => array( 7, 3650 ),
			'removed_seats_days'                 => array( 30, 3650 ),
			'debug_file_days'                    => array( 1, 30 ),
			'certificates_years'                 => array( 1, 50 ),
			'raw_answers_years_after_course_end' => array( 0, 30 ),
			'held_mail_days'                     => array( 1, 90 ),
		);
		foreach ( $ranges as $key => $range ) {
			self::assertSame( $range, array( $def['fields'][ $key ]['min'], $def['fields'][ $key ]['max'] ), $key );
			foreach ( array( $range[0] - 1, $range[1] + 1 ) as $bad ) {
				$check = Settings::check( Actor::cli(), 'privacy', array( 'retention' => array( $key => $bad ) + $defaults ), array( 'retention' ) );
				self::assertArrayHasKey( 'retention', $check['errors'], $key . ' = ' . $bad . ' rejected' );
			}
		}
		$check = Settings::check( Actor::cli(), 'privacy', array( 'retention' => array( 'learning_progress' => 'forever' ) + $defaults ), array( 'retention' ) );
		self::assertArrayHasKey( 'retention', $check['errors'], 'learning_progress is fixed' );
		self::assertSame( array( 'removed_seats_days' ), RetentionPolicy::shortened( $defaults, array( 'removed_seats_days' => 30 ) + $defaults ) );
		self::assertSame( array( 'inactive_learning_data_years' ), RetentionPolicy::shortened( $defaults, array( 'inactive_learning_data_years' => 5 ) + $defaults ), 'switching on counts as shorter' );
		self::assertSame( array(), RetentionPolicy::shortened( $defaults, array( 'audit_log_years' => 6 ) + $defaults ), 'longer needs no confirmation' );
	}

	/**
	 * The filter wins over the setting; invalid filtered values are ignored.
	 */
	public function test_filter_wins_over_setting(): void {
		self::set_retention( array( 'audit_log_years' => 6 ) );
		self::assertSame( 6, RetentionPolicy::int( 'audit_log_years' ) );
		add_filter( RetentionPolicy::FILTER, static fn( $value, string $key ) => 'audit_log_years' === $key ? 2 : ( 'lifted_locks_days' === $key ? 9999 : $value ), 10, 2 );
		self::assertSame( 2, RetentionPolicy::int( 'audit_log_years' ), 'filter wins' );
		self::assertSame( 30, RetentionPolicy::int( 'lifted_locks_days' ), 'out-of-range filter value ignored' );
		self::assertSame( array( 'audit_log_years' => 2 ), RetentionPolicy::filtered() );
		self::assertStringContainsString( RetentionPolicy::FILTER, RetentionPreview::filter_warning( 'audit_log_years' ) );
		self::assertSame( '', RetentionPreview::filter_warning( 'lifted_locks_days' ) );
	}

	/**
	 * RetentionService reads the setting; the preview counts exactly what
	 * the next run deletes and deletes nothing itself.
	 */
	public function test_service_reads_setting_and_preview_matches_run(): void {
		$db = Database::current();
		self::fk( false );
		foreach ( array( 40, 400 ) as $days ) {
			$db->insert(
				'ccap_seat',
				array(
					'group_id'   => 999,
					'status'     => 'removed',
					'removed_at' => self::ago( $days ),
				),
				array( 'created_at', 'updated_at' )
			);
			$db->insert(
				'ccap_learner_lock',
				array(
					'user_id'     => 5,
					'reason_code' => 'x',
					'valid_from'  => self::ago( $days + 1 ),
					'lifted_at'   => self::ago( $days ),
				),
				array( 'created_at', 'updated_at' )
			);
		}
		self::fk( true );
		$preview = RetentionService::preview();
		self::assertSame( 1, $preview['removed_seats_days']['count'], 'default 365 days: one seat due' );
		self::assertSame( 2, $preview['lifted_locks_days']['count'], 'default 30 days: both locks due' );
		self::assertSame( 'rows', $preview['removed_seats_days']['kind'] );
		self::assertNull( $preview['learning_progress']['count'], 'fixed' );
		self::assertNull( $preview['inactive_learning_data_years']['count'], 'off' );
		self::assertSame( 2, self::count_rows( 'ccap_seat' ), 'preview is read-only' );

		self::set_retention(
			array(
				'removed_seats_days' => 30,
				'lifted_locks_days'  => 100,
			)
		);
		$preview = RetentionService::preview();
		self::assertSame( 2, $preview['removed_seats_days']['count'] );
		self::assertSame( 1, $preview['lifted_locks_days']['count'] );
		$candidate = RetentionService::preview( array( 'removed_seats_days' => 3650 ) + (array) Settings::get( 'privacy.retention' ) );
		self::assertSame( 0, $candidate['removed_seats_days']['count'], 'preview of candidate values' );

		$result = RetentionService::run();
		self::assertSame( 2, $result['RT-17_removed_seats'] );
		self::assertSame( 1, $result['RT-22_lifted_locks'] );
		self::assertSame( 0, self::count_rows( 'ccap_seat' ) );
		self::assertSame( 1, self::count_rows( 'ccap_learner_lock' ) );
		self::assertSame( 0, RetentionService::preview()['removed_seats_days']['count'], 'nothing left' );
	}

	/**
	 * New steps: ended grants (RT-12), ended groups (RT-18), exam attempts
	 * (RT-06, not while a certificate references them), inactive learning
	 * data (RT-02, off by default), copies of unfinished imports (RT-26).
	 */
	public function test_new_steps(): void {
		$db = Database::current();
		self::fk( false );
		$grant       = static function ( string $status, int $days, ?int $user = 7, ?int $seat = null ) use ( $db ): int {
			return $db->insert(
				'ccap_grant',
				array(
					'uid'         => wp_generate_uuid4(),
					'user_id'     => $user,
					'course_id'   => 3,
					'status'      => $status,
					'source_type' => 'manual',
					'source_id'   => wp_generate_uuid4(),
					'seat_id'     => $seat,
					'created_at'  => self::ago( $days ),
					'updated_at'  => self::ago( $days ),
				)
			);
		};
		$old_revoked = $grant( 'revoked', 4000 );
		$grant( 'revoked', 100 );
		$grant( 'active', 4000 );
		$db->insert(
			'ccap_grant_event',
			array(
				'grant_id'    => $old_revoked,
				'event_type'  => 'revoked',
				'occurred_at' => self::ago( 4000 ),
				'created_at'  => self::ago( 4000 ),
			)
		);
		$group      = static function ( string $status, int $days ) use ( $db ): int {
			return $db->insert(
				'ccap_group',
				array(
					'uid'        => wp_generate_uuid4(),
					'name'       => 'G',
					'status'     => $status,
					'created_at' => self::ago( $days ),
					'updated_at' => self::ago( $days ),
				)
			);
		};
		$old_group  = $group( 'revoked', 1200 );
		$live_group = $group( 'revoked', 1200 );
		$group( 'active', 1200 );
		$seat = $db->insert(
			'ccap_seat',
			array(
				'group_id' => $live_group,
				'status'   => 'active',
			),
			array( 'created_at', 'updated_at' )
		);
		$grant( 'active', 1, 8, $seat );
		$db->insert(
			'ccap_seat',
			array(
				'group_id' => $old_group,
				'status'   => 'active',
			),
			array( 'created_at', 'updated_at' )
		);
		$session     = static function ( int $days, string $status = 'evaluated' ) use ( $db ): int {
			return $db->insert(
				'ccap_exam_session',
				array(
					'uid'           => wp_generate_uuid4(),
					'user_id'       => 7,
					'course_id'     => 3,
					'rule_id'       => 1,
					'mode'          => 'exam',
					'form_key'      => 'f',
					'status'        => $status,
					'start_token'   => wp_generate_uuid4(),
					'snapshot'      => '{}',
					'snapshot_hash' => 'h',
					'started_at'    => self::ago( $days ),
					'submitted_at'  => 'in_progress' === $status ? null : self::ago( $days ),
				)
			);
		};
		$old_session = $session( 4000 );
		$certified   = $session( 4000 );
		$session( 4000, 'in_progress' );
		$session( 10 );
		$db->insert(
			'ccap_exam_session_question',
			array(
				'session_id'  => $old_session,
				'position'    => 1,
				'question_id' => 1,
				'revision_id' => 1,
			)
		);
		$db->insert(
			'ccap_certificate',
			array(
				'certificate_uid'    => wp_generate_uuid4(),
				'user_id'            => 7,
				'course_id'          => 3,
				'course_revision_id' => 1,
				'evidence_type'      => 'exam_session',
				'evidence_id'        => $certified,
				'template_version'   => '1',
				'snapshot'           => '{}',
				'snapshot_hash'      => 'h',
				'verify_token_hash'  => hash( 'sha256', wp_generate_uuid4() ),
				'issued_at'          => self::ago( 1 ),
				'created_at'         => self::ago( 1 ),
			)
		);
		foreach ( array(
			7 => 4000,
			9 => 4000,
		) as $user => $days ) {
			$db->insert(
				'ccap_course_progress',
				array(
					'user_id'          => $user,
					'course_id'        => 3,
					'last_activity_at' => self::ago( $days ),
					'updated_at'       => self::ago( $days ),
				)
			);
		}
		$dir   = ProtectedStorage::dir( 'packages' );
		$files = array();
		foreach ( array(
			'uploaded'  => 40,
			'aborted'   => 10,
			'importing' => 400,
		) as $status => $days ) {
			$file = $dir . '/work-' . $status . '-' . wp_generate_password( 4, false ) . '.zip';
			file_put_contents( $file, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
			$files[ $status ] = $file;
			$db->insert(
				'ccap_import_batch',
				array(
					'package_id'     => 'p',
					'schema_version' => '1.0.0',
					'status'         => $status,
					'manifest'       => '{}',
					'storage_path'   => $file,
					'created_at'     => self::ago( $days ),
				)
			);
		}

		self::fk( true );
		$preview = RetentionService::preview();
		self::assertSame( 1, $preview['ended_grants_years']['count'] );
		self::assertSame( 1, $preview['closed_groups_years']['count'], 'group with a live grant stays' );
		self::assertSame( 1, $preview['exam_attempts_years']['count'], 'certified and running attempts stay' );
		self::assertSame( 1, $preview['import_package_days']['count'] );
		self::assertSame( 'files', $preview['import_package_days']['kind'] );
		self::assertNull( $preview['inactive_learning_data_years']['count'] );

		$result = RetentionService::run();
		self::assertSame( 1, $result['RT-12_ended_grants'] );
		self::assertSame( 1, $result['RT-18_closed_groups'] );
		self::assertSame( 1, $result['RT-06_exam_attempts'] );
		self::assertSame( 1, $result['RT-26_package_work'] );
		self::assertArrayNotHasKey( 'RT-02_inactive_learning', $result, 'RT-02 off by default' );
		self::assertNull( $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $old_revoked ) ) );
		self::assertSame( 0, self::count_rows( 'ccap_grant_event' ) );
		self::assertNull( $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $old_group ) ) );
		self::assertNotNull( $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $live_group ) ) );
		self::assertSame( 1, self::count_rows( 'ccap_seat' ), 'seats of the deleted group removed' );
		self::assertSame( 0, self::count_rows( 'ccap_exam_session_question' ) );
		self::assertSame( 3, self::count_rows( 'ccap_exam_session' ) );
		self::assertSame( array( false, true, true ), array_map( 'is_file', array_values( $files ) ), 'running and recent imports keep their copy' );
		self::assertSame( 2, self::count_rows( 'ccap_course_progress' ) );

		self::set_retention( array( 'inactive_learning_data_years' => 5 ) );
		self::assertSame( 1, RetentionService::preview()['inactive_learning_data_years']['count'], 'person 7 still has an active grant' );
		self::assertSame( 'people', RetentionService::preview()['inactive_learning_data_years']['kind'] );
		self::assertSame( 1, RetentionService::run()['RT-02_inactive_learning'] );
		self::assertSame( 1, self::count_rows( 'ccap_course_progress' ) );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}

	/**
	 * Jobs outside the retention step use the same periods: log files,
	 * held mails, outbox purge; the privacy policy text shows them.
	 */
	public function test_other_jobs_read_the_periods(): void {
		$dir = Log::dir();
		$old = gmdate( 'Ymd', time() - 10 * DAY_IN_SECONDS );
		file_put_contents( $dir . '/cc-error-' . $old . '.log', "x\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		self::assertSame( 0, RetentionService::preview()['error_log_days']['count'] );
		self::set_retention( array( 'error_log_days' => 5 ) );
		self::assertSame( 1, RetentionService::preview()['error_log_days']['count'] );
		self::assertSame( 'files', RetentionService::preview()['error_log_days']['kind'] );
		self::assertFileExists( $dir . '/cc-error-' . $old . '.log', 'preview deletes nothing' );
		self::assertSame( 1, Log::prune( time() ) );
		self::assertFileDoesNotExist( $dir . '/cc-error-' . $old . '.log' );

		$db = Database::current();
		$db->insert(
			'ccap_notification',
			array(
				'uid'           => wp_generate_uuid4(),
				'type'          => 'test',
				'dedupe_key'    => hash( 'sha256', 'held-test' ),
				'event_uuid'    => wp_generate_uuid4(),
				'recipient_ref' => 'user:1',
				'status'        => 'held',
				'created_at'    => self::ago( 10 ),
			)
		);
		self::assertSame( 0, RetentionService::preview()['held_mail_days']['count'] );
		self::set_retention( array( 'held_mail_days' => 5 ) );
		self::assertSame( 1, RetentionService::preview()['held_mail_days']['count'] );
		self::assertSame( 1, NotificationService::expire_held( $db, RetentionPolicy::int( 'held_mail_days' ) ) );

		self::assertSame( array( 'dead', 'purged' ), array_keys( OutboxDispatcher::current()->due( 30, 90 ) ) );
		self::set_retention(
			array(
				'raw_answers_years_after_course_end' => 3,
				'certificates_years'                 => 12,
				'audit_log_years'                    => 5,
				'notification_log_days'              => 60,
			)
		);
		$text = PrivacyService::policy_html();
		foreach ( array( ' 3 years', ' 12 years', ' 5 years', ' 60 days' ) as $part ) {
			self::assertStringContainsString( $part, $text );
		}
	}

	/**
	 * Register v4: stored `advanced.error_log_days`/`debug_file_days` move
	 * into `privacy.retention` with their values (with and without an
	 * existing privacy tab); the run is idempotent; Log reads the new keys.
	 */
	public function test_migration_moves_log_periods(): void {
		foreach ( array( true, false ) as $with_privacy ) {
			foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
				delete_option( Settings::OPTION_PREFIX . $tab );
			}
			add_option(
				Settings::OPTION_PREFIX . 'advanced',
				array(
					'_schema' => 3,
					'_rev'    => 2,
					'values'  => array(
						'log_level'       => 'error',
						'error_log_days'  => 45,
						'debug_file_days' => 3,
					),
				),
				'',
				true
			);
			if ( $with_privacy ) {
				add_option(
					Settings::OPTION_PREFIX . 'privacy',
					array(
						'_schema' => 3,
						'_rev'    => 5,
						'values'  => array( 'backup_max_age_days' => 120 ),
					),
					'',
					false
				);
			}
			update_option( Settings::VERSION_OPTION, 3 );
			$migrated = Settings::maybe_migrate();
			sort( $migrated );
			self::assertSame( array( 'advanced', 'privacy' ), $migrated );
			self::assertSame( 45, Settings::get( 'privacy.retention' )['error_log_days'] );
			self::assertSame( 3, Settings::get( 'privacy.retention' )['debug_file_days'] );
			self::assertSame( RetentionPolicy::defaults()['audit_log_years'], Settings::get( 'privacy.retention' )['audit_log_years'], 'other periods keep the default' );
			self::assertSame( 45, RetentionPolicy::int( 'error_log_days' ) );
			self::assertSame( 'error', Settings::get( 'advanced.log_level' ) );
			$advanced = get_option( Settings::OPTION_PREFIX . 'advanced' );
			self::assertArrayNotHasKey( 'error_log_days', $advanced['values'] );
			self::assertArrayNotHasKey( 'debug_file_days', $advanced['values'] );
			self::assertSame( SettingsSchema::VERSION, $advanced['_schema'] );
			$privacy = get_option( Settings::OPTION_PREFIX . 'privacy' );
			self::assertSame( SettingsSchema::VERSION, $privacy['_schema'] );
			if ( $with_privacy ) {
				self::assertSame( 120, Settings::get( 'privacy.backup_max_age_days' ) );
				self::assertSame( 5, $privacy['_rev'], 'revision kept' );
			}
			self::assertSame( array(), Settings::maybe_migrate(), 'idempotent' );
			self::assertSame( 45, Settings::get( 'privacy.retention' )['error_log_days'] );
			self::assertSame( 4, SettingsSchema::VERSION );
		}
		self::assertArrayNotHasKey( 'error_log_days', SettingsSchema::fields()['advanced'] );
		self::assertArrayHasKey( 'advanced.error_log_days', SettingsSchema::MOVED );
	}

	/**
	 * Export/import round trip of `privacy.retention`; a file of register v3
	 * carries the log periods into the new place.
	 */
	public function test_export_import_and_old_files(): void {
		$actor = Actor::cli();
		self::set_retention(
			array(
				'audit_log_years'              => 7,
				'inactive_learning_data_years' => 4,
			)
		);
		$file = SettingsTransfer::export( $actor );
		self::assertSame( 7, $file['tabs']['privacy']['values']['retention']['audit_log_years'] );
		Settings::reset( $actor, 'privacy' );
		self::assertSame( RetentionPolicy::defaults(), Settings::get( 'privacy.retention' ) );
		$preview = SettingsTransfer::import( $actor, SettingsTransfer::parse( (string) wp_json_encode( $file ) ), array() );
		self::assertSame( array( 'retention' ), array_column( $preview['tabs']['privacy']['skipped'], 'field' ), 'shortening (switching RT-02 on) needs a confirmation' );
		self::assertSame( array( 'RC-23' ), $preview['tabs']['privacy']['skipped'][0]['risks'] );
		SettingsTransfer::import(
			$actor,
			SettingsTransfer::parse( (string) wp_json_encode( $file ) ),
			array(
				'apply'     => true,
				'confirmed' => array( 'privacy.retention' ),
			)
		);
		self::assertSame( 7, Settings::get( 'privacy.retention' )['audit_log_years'] );
		self::assertSame( 4, Settings::get( 'privacy.retention' )['inactive_learning_data_years'] );

		$old                     = $file;
		$old['settings_version'] = 3;
		$old['tabs']             = array(
			'advanced' => array(
				'_schema' => 3,
				'values'  => array(
					'error_log_days'  => 60,
					'debug_file_days' => 14,
				),
			),
		);
		$report                  = SettingsTransfer::import(
			$actor,
			SettingsTransfer::parse( (string) wp_json_encode( $old ) ),
			array(
				'apply'     => true,
				'confirmed' => array( '*' ),
			)
		);
		self::assertSame( array(), $report['tabs']['advanced']['skipped'], 'moved keys are not reported as unknown' );
		self::assertSame( array( 'privacy' ), $report['applied'] );
		self::assertSame( 60, Settings::get( 'privacy.retention' )['error_log_days'] );
		self::assertSame( 14, Settings::get( 'privacy.retention' )['debug_file_days'] );
		self::assertSame( 7, Settings::get( 'privacy.retention' )['audit_log_years'], 'other periods unchanged' );
	}

	/**
	 * WP-CLI: sub-keys for get/set/reset, confirmation when shortening,
	 * read-only preview command.
	 */
	public function test_cli(): void {
		$actor = Actor::cli();
		$out   = SettingsCommand::execute( 'get', array( 'privacy.retention.audit_log_years' ), array( 'format' => 'json' ), $actor );
		self::assertSame( 0, $out['exit'] );
		self::assertSame( 4, json_decode( $out['lines'][0], true )['value'] );
		self::assertSame( 0, SettingsCommand::execute( 'set', array( 'privacy.retention.audit_log_years', '6' ), array(), $actor )['exit'], 'longer: no confirmation' );
		self::assertSame( 6, Settings::get( 'privacy.retention' )['audit_log_years'] );
		self::assertSame( 180, Settings::get( 'privacy.retention' )['import_log_days'], 'other sub-keys kept' );
		$refused = SettingsCommand::execute( 'set', array( 'privacy.retention.audit_log_years', '2' ), array( 'format' => 'json' ), $actor );
		self::assertSame( 1, $refused['exit'] );
		self::assertSame( array( 'RC-23' ), json_decode( $refused['lines'][0], true )['risks'] );
		self::assertSame( 0, SettingsCommand::execute( 'set', array( 'privacy.retention.audit_log_years', '2' ), array( 'confirm' => '' ), $actor )['exit'] );
		self::assertSame( 2, Settings::get( 'privacy.retention' )['audit_log_years'] );
		self::assertSame( 1, SettingsCommand::execute( 'set', array( 'privacy.retention.audit_log_years', '11' ), array( 'confirm' => '' ), $actor )['exit'], 'range' );
		self::assertSame( 0, SettingsCommand::execute( 'set', array( 'privacy.retention.inactive_learning_data_years', '3' ), array( 'confirm' => '' ), $actor )['exit'] );
		self::assertSame( 0, SettingsCommand::execute( 'set', array( 'privacy.retention.inactive_learning_data_years', 'null' ), array(), $actor )['exit'] );
		self::assertNull( Settings::get( 'privacy.retention' )['inactive_learning_data_years'] );
		self::assertSame( 0, SettingsCommand::execute( 'reset', array( 'privacy.retention.audit_log_years' ), array(), $actor )['exit'] );
		self::assertSame( 4, Settings::get( 'privacy.retention' )['audit_log_years'] );
		self::assertSame( 1, SettingsCommand::execute( 'get', array( 'privacy.retention.unknown' ), array(), $actor )['exit'] );
		$preview = SettingsCommand::execute( 'retention', array(), array( 'format' => 'json' ), $actor );
		self::assertSame( 0, $preview['exit'] );
		$rows = json_decode( $preview['lines'][0], true );
		self::assertCount( 22, $rows );
		self::assertSame( 'privacy.retention.audit_log_years', $rows[0]['key'] );
		$text = SettingsCommand::execute( 'retention', array(), array(), $actor );
		self::assertStringContainsString( 'Legal review needed', (string) end( $text['lines'] ) );
	}

	/**
	 * Privacy tab: one field per period, each marked "Legal review needed",
	 * preview table, accessible markup; shortening asks for RC-23 with the
	 * preview count; saving works without JS.
	 */
	public function test_privacy_tab_and_rc23(): void {
		$html = SettingsScreen::html( array( 'tab' => 'privacy' ), $this->admin );
		self::assertSame( 22, substr_count( $html, 'Legal review needed' ) - substr_count( $html, '“Legal review needed”' ), 'marker at every period' );
		self::assertStringContainsString( 'ccap-retention-preview', $html );
		self::assertStringContainsString( 'value="retention.audit_log_years"', $html );
		self::assertStringNotContainsString( 'value="retention.learning_progress"', $html, 'fixed value shown as text' );
		self::assertStringContainsString( 'name="ccap_mode[retention__inactive_learning_data_years]"', $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );
		$xpath = A11yDom::xpath( $html );
		$input = $xpath->query( '//input[@id="ccap-field-privacy-retention-audit-log-years"]' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $input );
		self::assertStringContainsString( 'ccap-field-privacy-retention-audit-log-years-status', $input->getAttribute( 'aria-describedby' ), 'marker is part of the description' );
		self::assertSame( 22, $xpath->query( '//table[contains(@class, "ccap-retention-preview")]/tbody/tr' )->length );
		self::assertGreaterThan( 0, $xpath->query( '//table[contains(@class, "ccap-retention-preview")]/thead//th[@scope="col"]' )->length );
		self::assertStringNotContainsString( 'error_log_days', SettingsScreen::html( array( 'tab' => 'advanced' ), $this->admin ), 'moved out of the advanced tab' );

		$db = Database::current();
		self::fk( false );
		$db->insert(
			'ccap_seat',
			array(
				'group_id'   => 999,
				'status'     => 'removed',
				'removed_at' => self::ago( 40 ),
			),
			array( 'created_at', 'updated_at' )
		);
		self::fk( true );
		$post = array(
			'tab'      => 'privacy',
			'_wpnonce' => wp_create_nonce( 'ccap_settings_save_privacy' ),
			'_rev'     => Settings::rev( 'privacy' ),
			'_fields'  => array( 'retention.removed_seats_days' ),
			'ccap'     => array( 'retention' => array( 'removed_seats_days' => '30' ) ),
		);
		self::assertSame( 'confirm', SettingsScreen::process_save( $post, $this->admin )['result'] );
		$pending = RiskConfirm::pending( $this->admin );
		self::assertSame( array( 'RC-23' ), $pending['risks'] );
		$text = RiskDialog::text( 'RC-23', (array) $pending['before'], (array) $pending['after'] );
		self::assertStringContainsString( 'Removed group places: 1 entry', $text['text'] );
		self::assertSame( 365, Settings::get( 'privacy.retention' )['removed_seats_days'], 'nothing saved before the confirmation' );
		$confirm = array(
			'tab'           => 'privacy',
			'_ccap_confirm' => '1',
			'_ccap_token'   => (string) $pending['token'],
			'_wpnonce'      => wp_create_nonce( 'ccap_settings_confirm_privacy' ),
		);
		self::assertSame( 'saved', SettingsScreen::process_save( $confirm, $this->admin )['result'] );
		self::assertSame( 30, Settings::get( 'privacy.retention' )['removed_seats_days'] );

		$longer = array_merge( $post, array( '_rev' => Settings::rev( 'privacy' ) ) );
		$longer['ccap']['retention']['removed_seats_days'] = '400';
		self::assertSame( 'saved', SettingsScreen::process_save( $longer, $this->admin )['result'], 'longer: no dialog' );
		$bad = array_merge( $post, array( '_rev' => Settings::rev( 'privacy' ) ) );
		$bad['ccap']['retention']['removed_seats_days'] = '10';
		self::assertSame( 'invalid', SettingsScreen::process_save( $bad, $this->admin )['result'], 'below the minimum: field error' );
		self::assertContains( 'RC-23', RiskRules::CODES );
	}
}
