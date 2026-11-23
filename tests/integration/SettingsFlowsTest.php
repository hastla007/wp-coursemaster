<?php
/**
 * SET-CC-P2: flows from enrollment to certificate and expiry/extension with
 * changed settings – minimum, maximum, empty and conflicting values, deadlines
 * across midnight and year end in the site time zone (Europe/Vienna,
 * Pacific/Tongatapu = UTC+13), repeated requests and double clicks.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\SelfEnrollment;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Certificate\CertificateService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

/**
 * Whole flows through the domain services.
 *
 * @group SET-CC-P2
 */
final class SettingsFlowsTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Lessons.
	 *
	 * @var list<int>
	 */
	private array $lessons = array();

	/**
	 * Fixture: published course (2 lessons, 3 pool questions), no grant.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		foreach ( array( 'enrollment', 'exams', 'certificates' ) as $tab ) {
			delete_option( Settings::OPTION_PREFIX . $tab );
		}
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course', 0, 'Kurs Fristen' );
		PolicyService::flush();
		$admin        = Actor::user( $this->admin );
		$this->module = $this->post( 'ccap_module', 0, 'Modul' );
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module );
		for ( $i = 0; $i < 2; $i++ ) {
			$lesson = $this->post( 'ccap_lesson', $this->module, 'Lektion ' . $i );
			ActivityService::current()->define( $admin, $lesson, 'lesson', array() );
			$this->lessons[] = $lesson;
		}
		$repo = QuestionRepository::current();
		for ( $i = 0; $i < 3; $i++ ) {
			$id = $repo->create(
				$admin,
				array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => 'Frage ' . $i . ' ' . wp_generate_password( 6, false ),
					'answers'       => array(
						array(
							'key'     => 'A',
							'text'    => 'richtig',
							'correct' => true,
						),
						array(
							'key'  => 'B',
							'text' => 'falsch',
						),
					),
				)
			);
			$repo->publish( $admin, $id );
			$repo->add_to_pool( $admin, $this->module, $id );
		}
		CoursePublisher::current()->publish( $admin, $this->course );
		PolicyService::flush();
	}

	/**
	 * Reset the site time zone.
	 */
	public function tear_down(): void {
		update_option( 'timezone_string', '' );
		update_option( 'gmt_offset', 0 );
		parent::tear_down();
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Type.
	 * @param int    $parent_id Parent.
	 * @param string $title     Title.
	 * @return int
	 */
	private function post( string $type, int $parent_id, string $title ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
				'post_title'   => $title,
				'post_content' => 'Inhalt',
			)
		);
	}

	/**
	 * Save settings of a tab like the settings screen does.
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $values Values.
	 */
	private function settings( string $tab, array $values ): void {
		Settings::save( Actor::user( $this->admin ), $tab, $values, array_keys( $values ), Settings::rev( $tab ) );
	}

	/**
	 * Published rule (3 questions, pass 2/3), inheriting the global exam settings.
	 *
	 * @param array<string, mixed> $settings Rule settings.
	 * @param int|null             $limit    Time limit in seconds.
	 * @return int
	 */
	private function rule( array $settings = array(), ?int $limit = 600 ): int {
		$rules = ExamRules::current();
		$admin = Actor::user( $this->admin );
		$rule  = $rules->create(
			$admin,
			$this->course,
			'v' . wp_generate_password( 4, false ),
			array(
				'min_ratio' => array(
					'num' => 2,
					'den' => 3,
				),
			),
			$limit,
			$settings
		);
		$rules->set_blueprint( $admin, $rule, $this->module, 3 );
		$rules->publish( $admin, $rule );
		return $rule;
	}

	/**
	 * Grant row.
	 *
	 * @param int $id Grant.
	 * @return array<string, mixed>
	 */
	private static function grant( int $id ): array {
		return (array) ( new Relations( Database::current() ) )->row( 'ccap_grant', $id );
	}

	/**
	 * Count rows.
	 *
	 * @param string $table Table without prefix.
	 * @param string $where Condition.
	 * @return int
	 */
	private static function rows_count( string $table, string $where ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . "` WHERE {$where}" );
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 * @return CourseException
	 */
	private function rejected( callable $call, string $code ): CourseException {
		try {
			$call();
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code );
			return $e;
		}
		self::fail( 'expected ' . $code );
	}

	/**
	 * Services that see the given Unix time as "now".
	 *
	 * @param int $time Unix time.
	 * @return array{grants: GrantService, relations: Relations}
	 */
	private static function clocked( int $time ): array {
		$db        = new Database( $GLOBALS['wpdb'], static fn(): int => $time );
		$relations = new Relations( $db );
		return array(
			'grants'    => new GrantService( $db, PolicyService::instance(), $relations ),
			'relations' => $relations,
		);
	}

	/**
	 * Lowest values everywhere (access 1 day, 1 attempt, no cooldown) and every
	 * step clicked twice: one grant, one progress, one attempt, one
	 * certificate; the second exam start is refused; the rule keeps the value
	 * frozen at publication when the global value changes afterwards. Dispatching
	 * the outbox twice issues nothing twice.
	 */
	public function test_minimum_values_and_double_clicks_through_the_whole_flow(): void {
		$this->settings(
			'enrollment',
			array(
				'self_enrollment'     => true,
				'default_access_days' => 1,
			)
		);
		$this->settings(
			'exams',
			array(
				'max_attempts'     => 1,
				'cooldown_seconds' => 0,
			)
		);
		$this->settings( 'certificates', array( 'auto_issue' => true ) );
		$rule    = $this->rule();
		$learner = Actor::user( $this->learner );

		$first  = SelfEnrollment::current()->enroll( $learner, $this->course );
		$second = SelfEnrollment::current()->enroll( $learner, $this->course );
		self::assertTrue( $first['created'] );
		self::assertSame( array( $first['grant_id'], false ), array( $second['grant_id'], $second['created'] ), 'double click: same grant' );
		$grant = self::grant( $first['grant_id'] );
		self::assertSame( 86400, strtotime( $grant['valid_until'] . ' UTC' ) - strtotime( $grant['valid_from'] . ' UTC' ), 'access of exactly one day' );

		foreach ( $this->lessons as $lesson ) {
			ActivityProgress::current()->interact( $learner, $this->course, $lesson, 'confirm' );
			ActivityProgress::current()->interact( $learner, $this->course, $lesson, 'confirm' );
		}
		self::assertSame( 1, self::rows_count( 'ccap_course_progress', 'user_id = ' . $this->learner ) );

		$sessions = ExamSessions::current();
		$key      = wp_generate_uuid4();
		$session  = $sessions->start( $learner, $rule, null, $key, new Randomizer( '20261231' ) );
		self::assertSame( $session, $sessions->start( $learner, $rule, null, $key, new Randomizer( '20261231' ) ), 'double click on start: same attempt' );
		foreach ( array( 1, 2, 3 ) as $position ) {
			$sessions->save_answer( $learner, $session, $position, array( 'A' ) );
			$sessions->save_answer( $learner, $session, $position, array( 'A' ) );
		}
		$result = $sessions->submit( $learner, $session );
		$again  = $sessions->submit( $learner, $session );
		self::assertTrue( $result['passed'] );
		self::assertSame( $result, $again, 'double click on submit: same result, no second evaluation' );
		self::assertSame( 1, self::rows_count( 'ccap_exam_session', "status = 'evaluated' AND user_id = " . $this->learner ) );
		$this->rejected( fn() => $sessions->cancel( $learner, $session ), 'ccap_exam_already_submitted' );

		OutboxDispatcher::current()->dispatch();
		OutboxDispatcher::current()->dispatch();
		// One certificate per evidence (R0-23): completion came first, the exam pass is its own evidence.
		self::assertSame( 1, self::rows_count( 'ccap_certificate', "evidence_type = 'exam_session' AND user_id = " . $this->learner ), 'one certificate for the exam pass' );
		self::assertSame( 1, self::rows_count( 'ccap_certificate', "evidence_type = 'course_completion' AND user_id = " . $this->learner ), 'one certificate for the completion' );

		$this->rejected( fn() => $sessions->start( $learner, $rule, null, wp_generate_uuid4() ), 'ccap_exam_attempts_exhausted' );
		$this->settings( 'exams', array( 'max_attempts' => 100 ) );
		$this->rejected( fn() => $sessions->start( $learner, $rule, null, wp_generate_uuid4() ), 'ccap_exam_attempts_exhausted' );
	}

	/**
	 * Access duration: empty = permanent, maximum 3650 days, 0 is refused with
	 * the range rule; expiry at the exact second, extension, re-enrollment.
	 */
	public function test_access_duration_edges_expiry_and_extension(): void {
		$admin   = Actor::user( $this->admin );
		$learner = Actor::user( $this->learner );
		$check   = Settings::check( $admin, 'enrollment', array( 'default_access_days' => 0 ), array( 'default_access_days' ) );
		self::assertSame( 'range', $check['errors']['default_access_days'] ?? null, '0 days is not a duration' );
		$check = Settings::check( $admin, 'enrollment', array( 'default_access_days' => 3651 ), array( 'default_access_days' ) );
		self::assertSame( 'range', $check['errors']['default_access_days'] ?? null );

		$this->settings(
			'enrollment',
			array(
				'self_enrollment'     => true,
				'default_access_days' => null,
			)
		);
		$permanent = SelfEnrollment::current()->enroll( $learner, $this->course );
		self::assertNull( self::grant( $permanent['grant_id'] )['valid_until'], 'empty = no end' );

		$other = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->settings( 'enrollment', array( 'default_access_days' => 3650 ) );
		$max   = SelfEnrollment::current()->enroll( Actor::user( $other ), $this->course );
		$grant = self::grant( $max['grant_id'] );
		self::assertSame( 3650 * 86400, strtotime( $grant['valid_until'] . ' UTC' ) - strtotime( $grant['valid_from'] . ' UTC' ) );

		// Expiry at the exact second, whatever the site time zone.
		$end = strtotime( $grant['valid_until'] . ' UTC' );
		foreach ( array( 'Pacific/Tongatapu', 'Europe/Vienna' ) as $zone ) {
			update_option( 'timezone_string', $zone );
			self::assertSame( 'ok', self::clocked( $end - 1 )['relations']->effective_access( $other, $this->course, 0, 'view' ), $zone );
			self::assertSame( 'expired', self::clocked( $end )['relations']->effective_access( $other, $this->course, 0, 'view' ), $zone );
		}
		$this->settings( 'enrollment', array( 'self_enrollment' => true ) );

		// Extension before the end, then the expiry job after the new end; running it twice changes nothing.
		$new_end = gmdate( 'Y-m-d H:i:s', $end + 30 * 86400 );
		self::assertSame( 'active', GrantService::current()->extend( $admin, $max['grant_id'], $new_end, 'Verlängerung' ) );
		self::assertSame( 0, self::clocked( $end + 60 )['grants']->expire_due() );
		self::assertSame( 'ok', self::clocked( $end + 60 )['relations']->effective_access( $other, $this->course, 0, 'view' ) );
		self::assertSame( 1, self::clocked( $end + 30 * 86400 + 1 )['grants']->expire_due() );
		self::assertSame( 0, self::clocked( $end + 30 * 86400 + 2 )['grants']->expire_due(), 'repeat: nothing left' );
		self::assertSame( 'expired', self::grant( $max['grant_id'] )['status'] );
		$this->rejected( fn() => GrantService::current()->extend( $admin, $max['grant_id'], gmdate( 'Y-m-d H:i:s', time() + 86400 ), 'zu spät' ), 'ccap_conflict' );

		// Re-enrollment after the end: a new revision, the old grant stays expired.
		$again = SelfEnrollment::current()->enroll( Actor::user( $other ), $this->course );
		self::assertTrue( $again['created'] );
		self::assertNotSame( $max['grant_id'], $again['grant_id'] );
		self::assertSame( '2', (string) self::grant( $again['grant_id'] )['source_revision'] );
	}

	/**
	 * Conflicting combinations: self-enrollment off, enrollment closed while
	 * self-enrollment is on, automatic issuance on while the certificate module
	 * is off, cancelled attempts not counted with one attempt.
	 */
	public function test_conflicting_combinations(): void {
		$learner = Actor::user( $this->learner );
		$this->settings( 'enrollment', array( 'self_enrollment' => false ) );
		$e = $this->rejected( fn() => SelfEnrollment::current()->enroll( $learner, $this->course ), 'ccap_forbidden' );
		self::assertSame( 'self_enrollment_off', $e->data['reason'] ?? null );
		$this->settings( 'enrollment', array( 'self_enrollment' => true ) );
		SelfEnrollment::current()->set_closed( Actor::user( $this->admin ), $this->course, true );
		$this->rejected( fn() => SelfEnrollment::current()->enroll( $learner, $this->course ), 'ccap_enrollment_closed' );
		SelfEnrollment::current()->set_closed( Actor::user( $this->admin ), $this->course, false );
		SelfEnrollment::current()->enroll( $learner, $this->course );

		$this->settings(
			'certificates',
			array(
				'enabled'    => false,
				'auto_issue' => true,
			)
		);
		$this->settings(
			'exams',
			array(
				'max_attempts'    => 1,
				'count_cancelled' => false,
			)
		);
		$rule     = $this->rule();
		$sessions = ExamSessions::current();
		$first    = $sessions->start( $learner, $rule, null, wp_generate_uuid4(), new Randomizer( '1' ) );
		self::assertSame( 'cancelled', $sessions->cancel( $learner, $first ) );
		self::assertSame( 'cancelled', $sessions->cancel( $learner, $first ), 'double click on cancel' );
		$second = $sessions->start( $learner, $rule, null, wp_generate_uuid4(), new Randomizer( '2' ) );
		self::assertNotSame( $first, $second, 'a cancelled attempt does not count' );
		foreach ( array( 1, 2, 3 ) as $position ) {
			$sessions->save_answer( $learner, $second, $position, array( 'A' ) );
		}
		self::assertTrue( $sessions->submit( $learner, $second )['passed'] );
		OutboxDispatcher::current()->dispatch();
		self::assertSame( 0, self::rows_count( 'ccap_certificate', 'user_id = ' . $this->learner ), 'module off wins over automatic issuance' );
	}

	/**
	 * Deadlines across midnight and year end in the site time zone: the
	 * certificate's "valid until" counts whole days from the local issue
	 * date, validity ends after the local last day, release dates are shown in
	 * the local calendar, exam time limits run across midnight in UTC.
	 */
	public function test_deadlines_across_midnight_and_year_end(): void {
		update_option( 'date_format', 'Y-m-d' );
		$cases = array(
			// zone => [evaluation (UTC), local issue date, valid until after 365 days].
			'UTC'               => array( '2026-12-31 23:30:00', '2026-12-31', '2027-12-31' ),
			'Europe/Vienna'     => array( '2026-12-31 23:30:00', '2027-01-01', '2028-01-01' ),
			'Pacific/Tongatapu' => array( '2026-12-31 11:30:00', '2027-01-01', '2028-01-01' ),
		);
		foreach ( $cases as $zone => $case ) {
			update_option( 'timezone_string', $zone );
			self::assertSame( $case[1], CertificateService::site_date( $case[0] ), "issue date in $zone" );
			self::assertSame( $case[2], CertificateService::site_date_after( $case[0], 365 ), "valid until in $zone" );
			self::assertSame( $case[1], CertificateService::site_date_after( $case[0], 0 ), "0 days in $zone" );
		}
		// Daylight saving change in Vienna (2027-03-28): one day after a 00:30 evaluation is the next local day.
		update_option( 'timezone_string', 'Europe/Vienna' );
		self::assertSame( '2027-03-29', CertificateService::site_date_after( '2027-03-27 23:30:00', 1 ) );
		self::assertSame( '2027-10-31', CertificateService::site_date_after( '2027-10-29 22:30:00', 1 ), 'end of summer time' );

		// "Today" for the validity check is the local date: 21:00 UTC on 31 Dec is already 1 Jan in UTC+13.
		$t = (int) strtotime( '2027-12-31 21:00:00 UTC' );
		update_option( 'timezone_string', 'Pacific/Tongatapu' );
		self::assertSame( '2028-01-01', CertificateService::site_today( 0, $t ) );
		update_option( 'timezone_string', 'Europe/Vienna' );
		self::assertSame( '2027-12-31', CertificateService::site_today( 0, $t ) );

		// Release date of a module (drip) in the local calendar.
		update_option( 'timezone_string', 'Europe/Vienna' );
		self::assertSame( 'Available from 2027-01-01', LearningArea::lock_label( 'drip', '2026-12-31 23:30:00' ) );
		update_option( 'timezone_string', 'Pacific/Tongatapu' );
		self::assertSame( 'Available from 2027-01-01', LearningArea::lock_label( 'drip', '2026-12-31 11:00:00' ) );
		update_option( 'timezone_string', 'UTC' );
		self::assertSame( 'Available from 2026-12-31', LearningArea::lock_label( 'drip', '2026-12-31 23:30:00' ) );

		// Exam with a 10-minute limit started at 23:55 on 31 Dec (UTC): answers after midnight count until the deadline.
		$this->settings( 'enrollment', array( 'self_enrollment' => true ) );
		$learner = Actor::user( $this->learner );
		SelfEnrollment::current()->enroll( $learner, $this->course );
		$rule     = $this->rule( array( 'late_tolerance_seconds' => 0 ), 600 );
		$sessions = ExamSessions::current();
		$start    = (int) strtotime( '2026-12-31 23:55:00 UTC' );
		$sessions->set_now( $start );
		try {
			$session = $sessions->start( $learner, $rule, null, wp_generate_uuid4(), new Randomizer( '3' ) );
			$sessions->set_now( $start + 540 );
			$sessions->save_answer( $learner, $session, 1, array( 'A' ) );
			$sessions->set_now( $start + 600 + 120 );
			self::assertSame( 1, $sessions->expire_due(), 'evaluated after the deadline (late tolerance 0, grace only)' );
			self::assertSame( 0, $sessions->expire_due(), 'repeat: nothing left' );
		} finally {
			$sessions->set_now( null );
		}
		$row = Database::current()->row( Database::current()->prepare( 'SELECT status, end_reason, deadline_at FROM `' . Database::current()->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		self::assertSame( 'evaluated', $row['status'] );
		self::assertSame( '2027-01-01 00:05:00', $row['deadline_at'], 'deadline crosses the year end in UTC' );
	}

	/**
	 * Certificate with a validity issued at 23:30 UTC on 31 Dec in Vienna:
	 * issue date and "valid until" in the snapshot both follow the local date.
	 */
	public function test_certificate_validity_counts_from_the_local_issue_date(): void {
		update_option( 'timezone_string', 'Europe/Vienna' );
		$this->settings( 'enrollment', array( 'self_enrollment' => true ) );
		$this->settings( 'certificates', array( 'auto_issue' => true ) );
		$learner = Actor::user( $this->learner );
		SelfEnrollment::current()->enroll( $learner, $this->course );
		$rule     = $this->rule( array( 'competency_validity_days' => 365 ) );
		$sessions = ExamSessions::current();
		$sessions->set_now( (int) strtotime( '2026-12-31 23:20:00 UTC' ) );
		try {
			$session = $sessions->start( $learner, $rule, null, wp_generate_uuid4(), new Randomizer( '4' ) );
			foreach ( array( 1, 2, 3 ) as $position ) {
				$sessions->save_answer( $learner, $session, $position, array( 'A' ) );
			}
			$sessions->set_now( (int) strtotime( '2026-12-31 23:30:00 UTC' ) );
			self::assertTrue( $sessions->submit( $learner, $session )['passed'] );
		} finally {
			$sessions->set_now( null );
		}
		OutboxDispatcher::current()->dispatch();
		$db  = Database::current();
		$row = $db->row( $db->prepare( 'SELECT snapshot FROM `' . $db->table( 'ccap_certificate' ) . "` WHERE evidence_type = 'exam_session' AND evidence_id = %d", $session ) );
		self::assertNotNull( $row );
		self::assertSame( '2028-01-01', json_decode( (string) $row['snapshot'], true )['valid_until'], 'local issue date 2027-01-01 + 365 days' );
	}
}
