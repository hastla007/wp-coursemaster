<?php
/**
 * SET-CC-02 follow-up (slice aufbewahrung-2): E-245 reason for longer
 * periods (UI, import, WP-CLI), RT-05 raw exam answers, E-244 course end
 * as the start of RT-04/RT-05/RT-45 (PSD-013), start dates in the preview,
 * eraser pseudonymises purchase grants for RT-13 (R0-09 §7, D12).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Admin\RetentionPreview;
use CourseCaptain\Admin\SettingsBackup;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditChain;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionPolicy;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Settings\RiskConfirm;
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
 * @group aufbewahrung-2
 */
final class SettingsRetention2Test extends SchemaTestCase {

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
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->course = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'C',
			)
		);
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			delete_option( Settings::OPTION_PREFIX . $tab );
			delete_option( Settings::UNDO_PREFIX . $tab );
		}
		delete_transient( SettingsTransfer::RATE_PREFIX . $this->admin );
		RetentionPreview::flush();
		PolicyService::flush();
		wp_set_current_user( $this->admin );
	}

	/**
	 * Clean up.
	 */
	public function tear_down(): void {
		self::fk( true );
		RiskConfirm::drop( $this->admin );
		parent::tear_down();
	}

	/**
	 * UTC time N days ago.
	 *
	 * @param int $days Days.
	 * @return string
	 */
	private static function ago( int $days ): string {
		return gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
	}

	/**
	 * Foreign key checks (fixture rows without parents).
	 *
	 * @param bool $on On.
	 * @return void
	 */
	private static function fk( bool $on ): void {
		$GLOBALS['wpdb']->query( 'SET FOREIGN_KEY_CHECKS = ' . ( $on ? '1' : '0' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
	 * Grant row.
	 *
	 * @param array<string, mixed> $data Columns.
	 * @return int
	 */
	private function grant( array $data ): int {
		$db = Database::current();
		return $db->insert(
			'ccap_grant',
			$data + array(
				'uid'         => $db->uuid(),
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
				'created_at'  => self::ago( 2000 ),
				'updated_at'  => self::ago( 2000 ),
			)
		);
	}

	/**
	 * Learning answer row (parents not needed, FK checks off by the caller).
	 *
	 * @param int      $user    User.
	 * @param int|null $course  Course.
	 * @param int      $days    Age in days.
	 * @return int
	 */
	private static function attempt( int $user, ?int $course, int $days ): int {
		return Database::current()->insert(
			'ccap_attempt',
			array(
				'user_id'         => $user,
				'question_id'     => 1,
				'revision_id'     => 1,
				'course_id'       => $course,
				'idempotency_key' => wp_generate_uuid4(),
				'created_at'      => self::ago( $days ),
			)
		);
	}

	/**
	 * Finished exam attempt with two answered questions.
	 *
	 * @param int $user User.
	 * @param int $days Age in days.
	 * @return int Session.
	 */
	private function exam( int $user, int $days ): int {
		$db      = Database::current();
		$session = $db->insert(
			'ccap_exam_session',
			array(
				'uid'              => wp_generate_uuid4(),
				'user_id'          => $user,
				'course_id'        => $this->course,
				'rule_id'          => 1,
				'mode'             => 'exam',
				'form_key'         => 'f',
				'status'           => 'evaluated',
				'start_token'      => wp_generate_uuid4(),
				'snapshot'         => '{}',
				'snapshot_hash'    => 'h',
				'started_at'       => self::ago( $days ),
				'submitted_at'     => self::ago( $days ),
				'evaluated_at'     => self::ago( $days ),
				'points_milli'     => 1000,
				'max_points_milli' => 2000,
				'passed'           => 0,
			)
		);
		foreach ( array( 1, 2 ) as $position ) {
			$db->insert(
				'ccap_exam_session_question',
				array(
					'session_id'   => $session,
					'position'     => $position,
					'question_id'  => 1,
					'revision_id'  => 1,
					'answer_order' => '["a","b"]',
					'given_answer' => '{"text":"My free text answer"}',
					'is_correct'   => 1 === $position ? 1 : 0,
					'answered_at'  => self::ago( $days ),
					'flagged'      => 1,
				)
			);
		}
		return $session;
	}

	/**
	 * Count of rows matching a condition.
	 *
	 * @param string $table Table.
	 * @param string $where Condition (prepared).
	 * @return int
	 */
	private static function rows_where( string $table, string $where = '1=1' ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '` WHERE ' . $where );
	}

	/**
	 * E-245 (RR-3, PSD-015) in the service, the settings screen and WP-CLI:
	 * extending beyond the default without a reason is refused; with a
	 * reason it is saved and the reason is in the audit entry.
	 */
	public function test_E245_reason_for_longer_periods(): void {
		$actor     = Actor::cli();
		$retention = array_merge( RetentionPolicy::defaults(), array( 'audit_log_years' => 6 ) );
		try {
			Settings::save( $actor, 'privacy', array( 'retention' => $retention ), array( 'retention' ), Settings::rev( 'privacy' ) );
			self::fail( 'longer without reason must be refused' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
			self::assertSame( array( 'retention' => 'retention_reason' ), $e->data['fields'] );
		}
		self::assertSame( 4, Settings::get( 'privacy.retention' )['audit_log_years'], 'nothing saved' );
		$too_long = array(
			'retention'                      => $retention,
			Settings::RETENTION_REASON_FIELD => str_repeat( 'x', Settings::RETENTION_REASON_MAX + 1 ),
		);
		self::assertSame( array( 'retention' => 'retention_reason' ), Settings::check( $actor, 'privacy', $too_long, array( 'retention' ) )['errors'], 'reason longer than 500 characters' );
		Settings::save(
			$actor,
			'privacy',
			array(
				'retention'                      => $retention,
				Settings::RETENTION_REASON_FIELD => '  Tax audit obligation  ',
			),
			array( 'retention' ),
			Settings::rev( 'privacy' )
		);
		self::assertSame( 6, Settings::get( 'privacy.retention' )['audit_log_years'] );
		$audit = self::last_audit( 'settings_saved' );
		self::assertSame( 'Tax audit obligation', $audit['reason'] );
		self::assertSame( array( 'audit_log_years' ), json_decode( (string) $audit['context'], true )['retention_extended'] );
		self::assertTrue( AuditChain::verify( Database::current() )['ok'], 'reason is part of the hash chain' );

		// Already above the default and unchanged, or shortened, or at most the default: no reason.
		self::assertSame( array(), RetentionPolicy::extended( array( 'audit_log_years' => 6 ), array( 'audit_log_years' => 6 ) ) );
		self::assertSame( array(), RetentionPolicy::extended( array( 'audit_log_years' => 6 ), array( 'audit_log_years' => 5 ) ) );
		self::assertSame( array(), RetentionPolicy::extended( array( 'audit_log_years' => 2 ), array( 'audit_log_years' => 4 ) ) );
		self::assertSame( array( 'audit_log_years' ), RetentionPolicy::extended( array( 'audit_log_years' => 6 ), array( 'audit_log_years' => 7 ) ) );
		self::assertSame( array(), RetentionPolicy::extended( array(), array( 'inactive_learning_data_years' => 20 ) ), 'default "off": switching on is shortening (RC-23)' );
		self::assertSame( array( 'import_keep_packages' ), RetentionPolicy::extended( array(), array( 'import_keep_packages' => 5 ) ) );
		$other = array_merge( (array) Settings::get( 'privacy.retention' ), array( 'import_log_days' => 100 ) );
		self::assertSame( array(), Settings::check( $actor, 'privacy', array( 'retention' => $other ), array( 'retention' ) )['errors'], 'changing another period keeps the earlier extension without a new reason' );

		// Settings screen without JS.
		$post = array(
			'tab'      => 'privacy',
			'_wpnonce' => wp_create_nonce( 'ccap_settings_save_privacy' ),
			'_rev'     => Settings::rev( 'privacy' ),
			'_fields'  => array( 'retention.removed_seats_days' ),
			'ccap'     => array( 'retention' => array( 'removed_seats_days' => '500' ) ),
		);
		self::assertSame( 'invalid', SettingsScreen::process_save( $post, $this->admin )['result'] );
		$html  = SettingsScreen::html( array( 'tab' => 'privacy' ), $this->admin );
		$xpath = A11yDom::xpath( $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );
		$area = $xpath->query( '//textarea[@id="ccap-field-privacy-retention-reason"]' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $area );
		self::assertSame( 'true', $area->getAttribute( 'aria-invalid' ) );
		self::assertSame( Settings::RETENTION_REASON_FIELD, $area->getAttribute( 'name' ) );
		self::assertSame( 1, $xpath->query( '//label[@for="ccap-field-privacy-retention-reason"]' )->length );
		self::assertStringContainsString( 'Removed group places: this period is longer than its suggested default', $html, 'field error at the extended period' );
		self::assertSame( 365, Settings::get( 'privacy.retention' )['removed_seats_days'] );
		$post['_retention_reason'] = 'Customer contract requires it';
		self::assertSame( 'saved', SettingsScreen::process_save( $post, $this->admin )['result'] );
		self::assertSame( 500, Settings::get( 'privacy.retention' )['removed_seats_days'] );
		self::assertSame( 'Customer contract requires it', self::last_audit( 'settings_saved' )['reason'] );

		// WP-CLI.
		$refused = SettingsCommand::execute( 'set', array( 'privacy.retention.lifted_locks_days', '90' ), array(), $actor );
		self::assertSame( 1, $refused['exit'] );
		self::assertStringContainsString( '--reason', $refused['lines'][0] );
		self::assertSame( 30, Settings::get( 'privacy.retention' )['lifted_locks_days'], 'nothing written' );
		self::assertSame( 0, SettingsCommand::execute( 'set', array( 'privacy.retention.lifted_locks_days', '90' ), array( 'reason' => 'Works council agreement' ), $actor )['exit'] );
		self::assertSame( 90, Settings::get( 'privacy.retention' )['lifted_locks_days'] );
		$audit = self::last_audit( 'settings_saved' );
		self::assertSame( 'Works council agreement', $audit['reason'] );
		self::assertSame( 'cli', json_decode( (string) $audit['context'], true )['context'] );
		self::assertSame( 0, SettingsCommand::execute( 'set', array( 'privacy.retention.lifted_locks_days', '20' ), array( 'confirm' => '' ), $actor )['exit'], 'shortening needs no reason' );
	}

	/**
	 * E-245 on import: service, WP-CLI and the backup screen.
	 */
	public function test_E245_reason_on_import(): void {
		$actor = Actor::cli();
		$file  = SettingsTransfer::export( $actor );
		$file['tabs']['privacy']['values']['retention']['certificates_years'] = 20;
		$file['tabs']['enrollment']['values']['invite_valid_days']            = 45;
		$data = SettingsTransfer::parse( (string) wp_json_encode( $file ) );

		$report = SettingsTransfer::import( $actor, $data, array( 'apply' => true ) );
		self::assertSame( 'retention_reason', $report['tabs']['privacy']['skipped'][0]['reason'] );
		self::assertSame( array( 'certificates_years' ), $report['tabs']['privacy']['skipped'][0]['extended'] );
		self::assertSame( 10, Settings::get( 'privacy.retention' )['certificates_years'], 'skipped without reason' );
		self::assertSame( 45, Settings::get( 'enrollment.invite_valid_days' ), 'other tabs still import' );

		$path = get_temp_dir() . 'ccap-retention-import-' . wp_generate_password( 6, false ) . '.json';
		file_put_contents( $path, (string) wp_json_encode( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		try {
			delete_transient( SettingsTransfer::RATE_PREFIX . 0 );
			$out = SettingsCommand::execute( 'import', array( $path ), array( 'yes' => '' ), $actor );
			self::assertStringContainsString( 'retention: skipped (retention_reason certificates_years; add --reason)', implode( "\n", $out['lines'] ) );
			$out = SettingsCommand::execute(
				'import',
				array( $path ),
				array(
					'yes'    => '',
					'reason' => 'Accreditation body rule',
				),
				$actor
			);
			self::assertSame( 0, $out['exit'] );
		} finally {
			wp_delete_file( $path );
		}
		self::assertSame( 20, Settings::get( 'privacy.retention' )['certificates_years'] );
		$audit = self::last_audit( 'settings_saved' );
		self::assertSame( 'Accreditation body rule', $audit['reason'] );
		self::assertSame( array( 'certificates_years' ), json_decode( (string) $audit['context'], true )['retention_extended'] );

		// Backup screen: preview shows the reason field; applying without it keeps the preview.
		Settings::reset( Actor::user( $this->admin ), 'privacy' );
		$nonce = wp_create_nonce( SettingsBackup::IMPORT );
		self::assertSame( 'preview', SettingsBackup::process( array( '_wpnonce' => $nonce ), (string) wp_json_encode( $file ), $this->admin ) );
		$html = SettingsBackup::html( array( 'ccap_result' => 'preview' ), $this->admin );
		self::assertStringContainsString( 'name="retention_reason"', $html );
		self::assertStringContainsString( 'Longer than the suggested default: Certificates.', $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );
		$apply = array(
			'_wpnonce'  => $nonce,
			'apply'     => '1',
			'tabs'      => array( 'privacy' ),
			'confirmed' => array(),
		);
		self::assertSame( 'ccap_reason_required', SettingsBackup::process( $apply, '', $this->admin ) );
		self::assertSame( 10, Settings::get( 'privacy.retention' )['certificates_years'] );
		$html = SettingsBackup::html( array( 'ccap_result' => 'ccap_reason_required' ), $this->admin );
		self::assertStringContainsString( 'aria-invalid="true"', $html, 'preview kept, reason marked' );
		$apply['retention_reason'] = 'Accreditation body rule';
		self::assertSame( 'imported', SettingsBackup::process( $apply, '', $this->admin ) );
		self::assertSame( 20, Settings::get( 'privacy.retention' )['certificates_years'] );
		self::assertSame( 'Accreditation body rule', self::last_audit( 'settings_saved' )['reason'] );
	}

	/**
	 * E-244 / PSD-013: RT-04 counts from the course end (a)–(d), learning
	 * answers without a course from their creation; the preview table names
	 * the start of every period.
	 */
	public function test_E244_course_end_for_learning_answers(): void {
		$db    = Database::current();
		$users = array();
		foreach ( array( 'a', 'b', 'b2', 'c', 'd', 'e', 'f' ) as $case ) {
			$users[ $case ] = (int) self::factory()->user->create();
		}
		// (a) active grant without end.
		$this->grant( array( 'user_id' => $users['a'] ) );
		// (b) grant expired 3 years ago, last activity before.
		$this->grant(
			array(
				'user_id'     => $users['b'],
				'status'      => 'expired',
				'valid_until' => self::ago( 1100 ),
				'updated_at'  => self::ago( 1099 ),
			)
		);
		// (b2) grant expired 1 year ago: the period runs from the grant end, not from the answer.
		$this->grant(
			array(
				'user_id'     => $users['b2'],
				'status'      => 'expired',
				'valid_until' => self::ago( 400 ),
				'updated_at'  => self::ago( 399 ),
			)
		);
		// (c) completed long ago, grant still active until next year.
		$this->grant(
			array(
				'user_id'     => $users['c'],
				'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 300 * DAY_IN_SECONDS ),
			)
		);
		$db->insert(
			'ccap_course_progress',
			array(
				'user_id'          => $users['c'],
				'course_id'        => $this->course,
				'status'           => 'completed',
				'completed_at'     => self::ago( 1500 ),
				'last_activity_at' => self::ago( 1500 ),
				'updated_at'       => self::ago( 1500 ),
			)
		);
		// (d) grant expired 3 years ago, new grant active.
		$this->grant(
			array(
				'user_id'     => $users['d'],
				'status'      => 'expired',
				'valid_until' => self::ago( 1100 ),
				'updated_at'  => self::ago( 1100 ),
			)
		);
		$this->grant( array( 'user_id' => $users['d'] ) );
		// (e) no grant, but recent activity in the course.
		$db->insert(
			'ccap_course_progress',
			array(
				'user_id'          => $users['e'],
				'course_id'        => $this->course,
				'last_activity_at' => self::ago( 100 ),
				'updated_at'       => self::ago( 100 ),
			)
		);
		self::fk( false );
		$answers = array();
		foreach ( array( 'a', 'b', 'b2', 'c', 'd', 'e' ) as $case ) {
			$answers[ $case ] = self::attempt( $users[ $case ], $this->course, 1200 );
		}
		$db->insert(
			'ccap_attempt_answer',
			array(
				'attempt_id' => $answers['b'],
				'answer_id'  => 1,
			)
		);
		// (f) answer without course: counted from its creation as before.
		$answers['f']   = self::attempt( $users['f'], null, 800 );
		$answers['new'] = self::attempt( $users['f'], null, 10 );
		self::fk( true );

		$preview = RetentionService::preview()['raw_answers_years_after_course_end'];
		$result  = RetentionService::run();
		self::assertSame( 2, $result['RT-04_answers'], 'b and f' );
		self::assertSame( 2, $preview['count'], 'preview = run' );
		$left = array_map( 'intval', $db->wpdb()->get_col( 'SELECT id FROM `' . $db->table( 'ccap_attempt' ) . '` ORDER BY id' ) );
		foreach ( array( 'a', 'b2', 'c', 'd', 'e', 'new' ) as $case ) {
			self::assertContains( $answers[ $case ], $left, 'kept: ' . $case );
		}
		self::assertNotContains( $answers['b'], $left );
		self::assertNotContains( $answers['f'], $left );
		self::assertSame( 0, self::rows_where( 'ccap_attempt_answer' ), 'answer rows deleted with the attempt' );
		self::assertSame( 0, RetentionService::run()['RT-04_answers'], 'second run without effect' );

		// Period 0: deleted as soon as the course has ended (b2, e and the course-less answer; a/c/d keep running).
		$retention = array_merge( (array) Settings::get( 'privacy.retention' ), array( 'raw_answers_years_after_course_end' => 0 ) );
		Settings::save( Actor::cli(), 'privacy', array( 'retention' => $retention ), array( 'retention' ), Settings::rev( 'privacy' ) );
		self::assertSame( 3, RetentionService::run()['RT-04_answers'] );
		$left = array_map( 'intval', $db->wpdb()->get_col( 'SELECT id FROM `' . $db->table( 'ccap_attempt' ) . '` ORDER BY id' ) );
		self::assertSame( array( $answers['a'], $answers['c'], $answers['d'] ), $left, 'running grants keep the answers (PSD-013 a, c, d)' );

		// Preview table: start of every period.
		RetentionPreview::flush();
		$xpath = A11yDom::xpath( RetentionPreview::table() );
		self::assertSame( 'Period starts', trim( (string) $xpath->query( '//thead//th[3]' )->item( 0 )->textContent ) );
		$cells = $xpath->query( '//tbody/tr/td[2]' );
		self::assertSame( 22, $cells->length );
		foreach ( $cells as $cell ) {
			self::assertNotSame( '', trim( (string) $cell->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
		}
		self::assertStringContainsString( 'course end: the later of the end of the last access and the last activity in the course', RetentionPreview::start_text( 'raw_answers_years_after_course_end' ) );
		foreach ( array_keys( RetentionPolicy::KEYS ) as $key ) {
			self::assertNotSame( '', RetentionPreview::start_text( $key ), $key );
		}
	}

	/**
	 * RT-05: raw exam answers are deleted two years after the course end;
	 * the attempt with its result stays (RT-06) and can no longer be
	 * re-evaluated. A running course keeps them.
	 */
	public function test_RT05_raw_exam_answers(): void {
		$db      = Database::current();
		$ended   = (int) self::factory()->user->create();
		$running = (int) self::factory()->user->create();
		$this->grant(
			array(
				'user_id'     => $ended,
				'status'      => 'expired',
				'valid_until' => self::ago( 1000 ),
				'updated_at'  => self::ago( 1000 ),
			)
		);
		$this->grant( array( 'user_id' => $running ) );
		self::fk( false );
		$old  = $this->exam( $ended, 1000 );
		$keep = $this->exam( $running, 1000 );
		self::fk( true );

		self::assertSame( 2, RetentionService::preview()['raw_answers_years_after_course_end']['count'], 'two question rows of one attempt' );
		self::assertContains( 'RT-05_exam_answers', RetentionPolicy::KEYS['raw_answers_years_after_course_end']['steps'] );
		$result = RetentionService::run();
		self::assertSame( 2, $result['RT-05_exam_answers'] );
		$rows = $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY position', $old ) );
		self::assertCount( 2, $rows, 'rows stay' );
		foreach ( $rows as $row ) {
			self::assertNull( $row['given_answer'] );
			self::assertNull( $row['self_assessed_known'] );
			self::assertSame( '0', (string) $row['flagged'] );
			self::assertNotNull( $row['answered_at'] );
		}
		self::assertSame( array( '1', '0' ), array_map( 'strval', array_column( $rows, 'is_correct' ) ), 'per-question result stays' );
		$session = (array) $db->row( $db->prepare( 'SELECT points_milli, passed, status FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $old ) );
		self::assertSame( array( '1000', '0', 'evaluated' ), array_map( 'strval', array_values( $session ) ), 'core data RT-06 unchanged' );
		self::assertSame( 2, self::rows_where( 'ccap_exam_session_question', $db->prepare( 'session_id = %d AND given_answer IS NOT NULL', $keep ) ), 'active grant keeps the answers' );
		self::assertSame( 0, RetentionService::run()['RT-05_exam_answers'], 'second run without effect' );
		self::assertGreaterThan( 0, self::rows_where( 'ccap_audit_log', "action = 'retention_applied' AND object_id = 'RT-05_exam_answers'" ) );
		try {
			ExamSessions::current()->regrade( Actor::user( $this->admin ), $old, 1, 0, 'Key was wrong' );
			self::fail( 'regrade without raw answers' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_exam_conflict', $e->error_code );
			self::assertSame( 'raw_answers_deleted', $e->data['reason'] );
		}
	}

	/**
	 * R0-09 §7 / RT-13: the eraser pseudonymises purchase grants (running
	 * ones end as revoked/account_deleted with event), deletes other
	 * grants; nothing personal enters the audit chain (D12); RT-13 deletes
	 * the pseudonymised history after its period.
	 */
	public function test_eraser_pseudonymises_purchase_grants_for_RT13(): void {
		$db      = Database::current();
		$learner = (int) self::factory()->user->create( array( 'user_email' => 'buyer-' . wp_generate_password( 6, false ) . '@example.org' ) );
		$other   = (int) self::factory()->user->create();
		$service = GrantService::current();
		$order   = $service->grant_from_source(
			array(
				'user_id'          => $learner,
				'course_id'        => $this->course,
				'source_type'      => 'woo_order',
				'source_reference' => 'order-4711',
				'renewal_mode'     => 'parallel',
			),
			Actor::cron()
		)['grant_id'];
		$voucher = $this->grant(
			array(
				'user_id'     => $learner,
				'status'      => 'expired',
				'source_type' => 'voucher',
				'valid_until' => self::ago( 30 ),
				'updated_at'  => self::ago( 30 ),
			)
		);
		$manual  = $service->grant_manual( Actor::user( $this->admin ), $learner, $this->course, wp_generate_uuid4(), 'Trial' );
		$foreign = $this->grant(
			array(
				'user_id'    => $other,
				'granted_by' => $learner,
			)
		);
		$db->insert(
			'ccap_grant_event',
			array(
				'grant_id'    => $foreign,
				'event_type'  => 'status_changed',
				'to_status'   => 'active',
				'actor_type'  => 'user',
				'actor_id'    => $learner,
				'occurred_at' => $db->now(),
				'created_at'  => $db->now(),
			)
		);
		self::assertSame( 0, RetentionService::preview()['grant_history_deleted_accounts_years']['count'], 'RT-13 finds nothing before the erasure' );

		self::assertTrue( PrivacyService::erase( (string) get_userdata( $learner )->user_email )['items_removed'] );

		$ref = PrivacyService::grant_subject_ref( $learner );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{64}$/', $ref );
		self::assertNotSame( hash( 'sha256', (string) $learner ), $ref, 'HMAC with the site secret, not a plain hash' );
		$kept = $db->rows( 'SELECT id, user_id, subject_ref, status, revocation_reason, updated_at FROM `' . $db->table( 'ccap_grant' ) . "` WHERE source_type IN ('woo_order', 'voucher') ORDER BY id" );
		self::assertSame( array( $order, $voucher ), array_map( 'intval', array_column( $kept, 'id' ) ), 'purchase grants stay' );
		foreach ( $kept as $row ) {
			self::assertNull( $row['user_id'] );
			self::assertSame( $ref, $row['subject_ref'] );
			self::assertGreaterThan( self::ago( 1 ), $row['updated_at'], 'period starts with the erasure' );
		}
		self::assertSame( array( 'revoked', 'account_deleted' ), array( $kept[0]['status'], $kept[0]['revocation_reason'] ), 'running purchase grant ends as revoked' );
		self::assertSame( 'expired', $kept[1]['status'], 'ended grant keeps its state (no transition after expired)' );
		self::assertSame( 1, self::rows_where( 'ccap_grant_event', $db->prepare( "grant_id = %d AND reason_code = 'account_deleted' AND to_status = 'revoked'", $order ) ), 'one revocation event (grant service)' );
		self::assertSame( 0, self::rows_where( 'ccap_grant', $db->prepare( 'id = %d', $manual ) ), 'manual grant deleted (RT-14)' );
		self::assertSame( 0, self::rows_where( 'ccap_grant_event', $db->prepare( 'grant_id = %d', $manual ) ) );
		self::assertSame( 0, self::rows_where( 'ccap_grant_event', $db->prepare( 'actor_id = %d', $learner ) ), 'person leaves the event history' );
		self::assertSame( 0, self::rows_where( 'ccap_grant', $db->prepare( 'user_id = %d OR granted_by = %d', $learner, $learner ) ) );
		self::assertSame( 1, self::rows_where( 'ccap_grant', $db->prepare( 'id = %d AND user_id = %d', $foreign, $other ) ), 'foreign grant stays' );
		self::assertSame( 1, self::rows_where( 'ccap_outbox_event', "event_type = 'grant.revoked'" ) );

		// D12: no subject_ref, no user ID of the person in the audit chain.
		foreach ( $db->rows( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . '`' ) as $row ) {
			self::assertStringNotContainsString( $ref, (string) wp_json_encode( $row ) );
			self::assertNotSame( (string) $learner, (string) $row['actor_user_id'] );
			self::assertNotSame( (string) $learner, (string) $row['subject_user_id'] );
		}
		self::assertTrue( AuditChain::verify( $db )['ok'] );

		// RT-13: deleted after `grant_history_deleted_accounts_years` (10), counted from the erasure.
		self::assertSame( 2, RetentionService::preview( null, time() + 11 * YEAR_IN_SECONDS )['grant_history_deleted_accounts_years']['count'] );
		self::assertSame( 0, RetentionService::run()['RT-13_grant_history_deleted'], 'not before the period' );
		$result = RetentionService::run( time() + 11 * YEAR_IN_SECONDS );
		self::assertSame( 2, $result['RT-13_grant_history_deleted'] );
		self::assertSame( 0, self::rows_where( 'ccap_grant', "source_type IN ('woo_order', 'voucher')" ) );
		self::assertSame( 0, self::rows_where( 'ccap_grant_event', $db->prepare( 'grant_id = %d', $order ) ) );
		self::assertSame( 1, self::rows_where( 'ccap_grant', $db->prepare( 'id = %d', $foreign ) ), 'RT-12 is not RT-13: the grant of the existing account stays' );
	}
}
