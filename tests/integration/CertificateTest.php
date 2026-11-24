<?php
/**
 * R1-44: basic certificate domain (R0-29 §2/§4.4, R0-08 §7.2, R0-23 §5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Access\ContentGate;
use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GrantTransitions;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Certificate\CertificatePages;
use CourseCaptain\Certificate\CertificateService;
use CourseCaptain\Certificate\CertificateSnapshot;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Progress\Competency;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-44
 */
final class CertificateTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Course manager (certificate manager, #17).
	 *
	 * @var int
	 */
	private int $manager;

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
	 * Module with the question pool and two lessons.
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
	 * Email of the learner (unique: schema tests commit).
	 *
	 * @var string
	 */
	private string $email = '';

	/**
	 * Grant of the learner.
	 *
	 * @var int
	 */
	private int $grant;

	/**
	 * Course with one module (two lessons, three questions), published; learner with grant.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->email   = 'juergen-' . wp_generate_password( 8, false ) . '@example.org';
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		$this->learner = (int) self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'display_name' => 'Jürgen Müller-Lüdenscheidt',
				'user_email'   => $this->email,
			)
		);
		$this->course  = $this->post( 'ccap_course', 0, 'Grundkurs Sicherheit' );
		PolicyService::flush();
		$admin        = Actor::user( $this->admin );
		$this->module = $this->post( 'ccap_module', 0, 'Modul A' );
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
					'question_text' => 'Q ' . $i . wp_generate_password( 6, false ),
					'answers'       => array(
						array(
							'key'     => 'A',
							'text'    => 'right',
							'correct' => true,
						),
						array(
							'key'  => 'B',
							'text' => 'wrong',
						),
					),
				)
			);
			$repo->publish( $admin, $id );
			$repo->add_to_pool( $admin, $this->module, $id );
		}
		CoursePublisher::current()->publish( $admin, $this->course );
		$db          = Database::current();
		$this->grant = $db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $this->learner,
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		add_filter( 'course_captain_internal_subscribers', array( self::class, 'add_test_subscriber' ) );
	}

	/**
	 * Remove the test subscriber.
	 */
	public function tear_down(): void {
		remove_filter( 'course_captain_internal_subscribers', array( self::class, 'add_test_subscriber' ) );
		parent::tear_down();
	}

	/**
	 * Test subscriber so that certificate events are stored (Free: internal outbox only).
	 *
	 * @param mixed $subscribers Subscribers.
	 * @return array<string, mixed>
	 */
	public static function add_test_subscriber( $subscribers ): array {
		return (array) $subscribers + array( 'test' => array( 'certificate.*' ) );
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
				'post_content' => 'content',
			)
		);
	}

	/**
	 * Switch `certificates.*` settings.
	 *
	 * @param array<string, mixed> $values Values.
	 */
	private function settings( array $values ): void {
		Settings::save( Actor::user( $this->admin ), 'certificates', $values, array_keys( $values ), Settings::rev( 'certificates' ) );
	}

	/**
	 * Complete the course (both lessons) and dispatch the outbox.
	 */
	private function complete_course(): void {
		foreach ( $this->lessons as $lesson ) {
			ActivityProgress::current()->interact( Actor::user( $this->learner ), $this->course, $lesson, 'confirm' );
		}
		OutboxDispatcher::current()->dispatch();
	}

	/**
	 * Published rule (3 questions, pass 2/3) with settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return int
	 */
	private function rule( array $settings = array() ): int {
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
			600,
			$settings
		);
		$rules->set_blueprint( $admin, $rule, $this->module, 3 );
		$rules->publish( $admin, $rule );
		return $rule;
	}

	/**
	 * Take an exam (all right or all wrong) without dispatching.
	 *
	 * @param int  $rule  Rule.
	 * @param bool $right Answer right.
	 * @return int Session.
	 */
	private function take_exam( int $rule, bool $right = true ): int {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $sessions->start( $learner, $rule, null, wp_generate_uuid4(), new Randomizer( '20300115' ) );
		foreach ( array( 1, 2, 3 ) as $position ) {
			$sessions->save_answer( $learner, $session, $position, array( $right ? 'A' : 'B' ) );
		}
		$sessions->submit( $learner, $session );
		return $session;
	}

	/**
	 * Certificate rows.
	 *
	 * @param string $where Condition.
	 * @return list<array<string, mixed>>
	 */
	private static function certs( string $where = '1=1' ): array {
		$db = Database::current();
		return $db->rows( 'SELECT * FROM `' . $db->table( 'ccap_certificate' ) . "` WHERE {$where} ORDER BY id" );
	}

	/**
	 * Count outbox events of a type.
	 *
	 * @param string $type Event type.
	 * @return int
	 */
	private static function events( string $type ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_event' ) . '` WHERE event_type = %s', $type ) );
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
	 * Certificate from a passed exam (auto issue on).
	 *
	 * @param array<string, mixed> $settings Rule settings.
	 * @return array{session: int, uid: string, row: array<string, mixed>}
	 */
	private function exam_certificate( array $settings = array() ): array {
		$this->settings( array( 'auto_issue' => true ) );
		$session = $this->take_exam( $this->rule( $settings ) );
		OutboxDispatcher::current()->dispatch();
		$rows = self::certs( "evidence_type = 'exam_session' AND evidence_id = " . $session );
		self::assertCount( 1, $rows );
		return array(
			'session' => $session,
			'uid'     => (string) $rows[0]['certificate_uid'],
			'row'     => $rows[0],
		);
	}

	/**
	 * CRT-001 / CRT-002: completion with auto_issue issues exactly one certificate; snapshot and event.
	 *
	 * @group CRT-001
	 * @group CRT-002
	 */
	public function test_CRT_001_002_auto_issue_once(): void {
		$this->settings( array( 'auto_issue' => false ) );
		$this->complete_course();
		self::assertCount( 0, self::certs(), 'auto_issue off: nothing' );

		$this->settings( array( 'auto_issue' => true ) );
		$progress = (int) Database::current()->scalar( Database::current()->prepare( 'SELECT id FROM `' . Database::current()->table( 'ccap_course_progress' ) . '` WHERE user_id = %d', $this->learner ) );
		$first    = CertificateService::current()->auto_issue( CertificateService::EVIDENCE_COURSE, $progress );
		$second   = CertificateService::current()->auto_issue( CertificateService::EVIDENCE_COURSE, $progress );
		self::assertNotNull( $first );
		self::assertTrue( $first['created'] );
		self::assertSame( $first['certificate_uid'], $second['certificate_uid'] ?? null, 'second call returns the same' );
		self::assertFalse( $second['created'] ?? true );
		$manual = CertificateService::current()->issue( Actor::user( $this->manager ), CertificateService::EVIDENCE_COURSE, $progress, 'support case' );
		self::assertSame( $first['certificate_uid'], $manual['certificate_uid'], 'manual issuance on the same evidence too' );

		$rows = self::certs();
		self::assertCount( 1, $rows );
		self::assertSame( '1', (string) $rows[0]['revision_no'] );
		self::assertSame( 'issued', $rows[0]['status'] );
		self::assertSame( 1, self::events( 'certificate.issued' ) );
		$snapshot = json_decode( (string) $rows[0]['snapshot'], true );
		$revision = CoursePublisher::current()->current_revision( $this->course );
		self::assertSame( (int) $revision['id'], $snapshot['course']['course_revision_id'] );
		self::assertSame( 'Grundkurs Sicherheit', $snapshot['course']['title'] );
		self::assertSame( 'course_completion', $snapshot['evidence']['type'] );
		self::assertTrue( $snapshot['evidence']['evidence_eligible'] );
		self::assertSame( 'base', $snapshot['template']['kind'] );
		self::assertSame( (string) $snapshot['template']['version'], (string) $rows[0]['template_version'] );
		self::assertNotSame( '', $snapshot['locale']['content'] );
		self::assertSame( 'Jürgen Müller-Lüdenscheidt', $snapshot['holder']['display_name'] );
		self::assertSame(
			array(
				'role' => 'system',
				'type' => 'system',
			),
			$snapshot['issued_by'],
			'canonical key order'
		);
		self::assertSame( CertificateSnapshot::hash( $snapshot ), $rows[0]['snapshot_hash'] );
		self::assertSame( 64, strlen( (string) $rows[0]['verify_token_hash'] ) );

		// Dispatching again (cron) never duplicates.
		OutboxDispatcher::current()->dispatch();
		self::assertCount( 1, self::certs() );
	}

	/**
	 * CRT-001 via the outbox: exam pass issues; a later course completion adds no second certificate.
	 *
	 * @group CRT-001
	 */
	public function test_CRT_001_exam_then_completion(): void {
		$cert = $this->exam_certificate();
		$snap = json_decode( (string) $cert['row']['snapshot'], true );
		self::assertSame( 'exam_session', $snap['evidence']['type'] );
		self::assertSame( 3000, $snap['evidence']['points'] );
		self::assertSame( 1, $snap['evidence']['evaluation_no'] );
		$this->complete_course();
		self::assertCount( 1, self::certs(), 'completion adds none while a valid certificate exists' );
	}

	/**
	 * CRT-004 / CRT-011: no grant at submission → no automatic certificate; manual issuance
	 * possible; never without evidence, never from reduced/practice or failed attempts.
	 *
	 * @group CRT-004
	 * @group CRT-011
	 */
	public function test_CRT_004_011_grant_and_evidence_rules(): void {
		$this->settings( array( 'auto_issue' => true ) );
		$rule     = $this->rule();
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $sessions->start( $learner, $rule, null, wp_generate_uuid4(), new Randomizer( '20300115' ) );
		foreach ( array( 1, 2, 3 ) as $position ) {
			$sessions->save_answer( $learner, $session, $position, array( 'A' ) );
		}
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . "` SET status = 'revoked' WHERE id = %d", $this->grant ) );
		PolicyService::flush();
		self::assertTrue( $sessions->submit( $learner, $session )['passed'], 'result is evaluated' );
		OutboxDispatcher::current()->dispatch();
		self::assertCount( 0, self::certs(), 'E-113: no automatic certificate' );

		$service = CertificateService::current();
		$manager = Actor::user( $this->manager );
		$this->assertRejected( fn() => $service->issue( $manager, CertificateService::EVIDENCE_EXAM, $session, ' ' ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $service->issue( $manager, CertificateService::EVIDENCE_EXAM, 999999, 'none' ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $service->issue( Actor::user( $this->learner ), CertificateService::EVIDENCE_EXAM, $session, 'self' ), 'ccap_forbidden' );
		$issued = $service->issue( $manager, CertificateService::EVIDENCE_EXAM, $session, 'E-113 case' );
		self::assertTrue( $issued['created'] );
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'certificate_issued'" ) );

		// Practice and failed attempts are never evidence.
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . "` SET status = 'active' WHERE id = %d", $this->grant ) );
		PolicyService::flush();
		$practice = $this->take_exam( $this->rule( array( 'purpose' => 'practice' ) ) );
		$this->assertRejected( fn() => $service->issue( $manager, CertificateService::EVIDENCE_EXAM, $practice, 'practice' ), 'ccap_validation_failed' );
		$failed = $this->take_exam( $this->rule(), false );
		$this->assertRejected( fn() => $service->issue( $manager, CertificateService::EVIDENCE_EXAM, $failed, 'failed' ), 'ccap_validation_failed' );
		OutboxDispatcher::current()->dispatch();
		self::assertCount( 1, self::certs(), 'only the manual one' );
	}

	/**
	 * CRT-003 / CRT-010 / CRT-012: correction after re-evaluation and name change; immutable fields.
	 *
	 * @group CRT-003
	 * @group CRT-010
	 * @group CRT-012
	 */
	public function test_CRT_003_010_012_correction(): void {
		$cert    = $this->exam_certificate();
		$service = CertificateService::current();
		$manager = Actor::user( $this->manager );
		$token1  = CertificateService::token( $cert['uid'], 1 );

		// CRT-010: profile change alone changes nothing.
		wp_update_user(
			array(
				'ID'           => $this->learner,
				'display_name' => 'Jürgen Neuname',
			)
		);
		self::assertSame( 'Jürgen Müller-Lüdenscheidt', $service->view( $manager, $cert['uid'] )['snapshot']['holder']['display_name'] );

		// CRT-012: evidence, person, course are immutable.
		$this->assertRejected( fn() => $service->correct( $manager, $cert['uid'], 'move', array( 'course_id' => 1 ) ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $service->correct( $manager, $cert['uid'], 'move', array( 'user_id' => 1 ) ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $service->correct( $manager, $cert['uid'], ' ', array( 'holder_name' => 'X' ) ), 'ccap_validation_failed' );

		// CRT-003: re-evaluation keeps "passed", points change → correction refreshes the evidence.
		ExamSessions::current()->regrade( Actor::user( $this->admin ), $cert['session'], 3, 500, 'Partial credit only' );
		OutboxDispatcher::current()->dispatch();
		self::assertCount( 1, self::certs(), 'no automatic correction' );
		$key = wp_generate_uuid4();
		$out = $service->correct(
			$manager,
			$cert['uid'],
			'Re-evaluation and name change on request',
			array(
				'refresh_evidence' => true,
				'holder_name'      => 'Jürgen Neuname',
			),
			$key
		);
		self::assertSame( 2, $out['revision_no'] );
		$again = $service->correct(
			$manager,
			$cert['uid'],
			'Re-evaluation and name change on request',
			array(
				'refresh_evidence' => true,
				'holder_name'      => 'Jürgen Neuname',
			),
			$key
		);
		self::assertSame( $out, $again, 'double click: same revision' );
		$this->assertRejected( fn() => $service->correct( $manager, $cert['uid'], 'other', array( 'holder_name' => 'Y' ), $key ), 'ccap_idempotency_conflict' );

		$rows = self::certs( "certificate_uid = '" . $cert['uid'] . "'" );
		self::assertCount( 2, $rows );
		self::assertSame( 'corrected', $rows[0]['status'] );
		self::assertSame( 'issued', $rows[1]['status'] );
		self::assertSame( (int) $rows[0]['id'], (int) $rows[1]['supersedes_id'] );
		self::assertSame( $rows[0]['template_version'], $rows[1]['template_version'], 'template unchanged' );
		$new = json_decode( (string) $rows[1]['snapshot'], true );
		self::assertSame( 'correction', $new['revision_kind'] );
		self::assertSame( 2500, $new['evidence']['points'] );
		self::assertSame( 2, $new['evidence']['evaluation_no'] );
		self::assertSame( 'Jürgen Neuname', $new['holder']['display_name'] );
		self::assertSame( 1, self::events( 'certificate.corrected' ) );

		$old = $service->verify( $token1 );
		self::assertSame( 'corrected', $old['status'] );
		self::assertSame( array( 'status', 'superseded_on' ), array_keys( $old ), 'no data of the new version over the old token' );
		self::assertSame( 'valid', $service->verify( CertificateService::token( $cert['uid'], 2 ) )['status'] );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'certificate_corrected'" ) );
	}

	/**
	 * CRT-013 / CRT-014 / CRT-028: revocation of revision 2 revokes all; rights; immediate.
	 *
	 * @group CRT-013
	 * @group CRT-014
	 * @group CRT-028
	 * @group REV-046
	 */
	public function test_CRT_013_014_028_revocation(): void {
		$cert    = $this->exam_certificate( array( 'certifies_objectives' => array( 'obj-safety' ) ) );
		$service = CertificateService::current();
		$manager = Actor::user( $this->manager );
		$service->correct( $manager, $cert['uid'], 'typo', array( 'course_title' => 'Grundkurs Sicherheit (2026)' ) );
		$token1 = CertificateService::token( $cert['uid'], 1 );
		$token2 = CertificateService::token( $cert['uid'], 2 );
		self::assertSame( 'valid', $service->verify( $token2 )['status'], 'before' );

		// CRT-014.
		$this->assertRejected( fn() => $service->revoke( $manager, $cert['uid'], '' ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $service->revoke( $manager, $cert['uid'], 'other', ' ' ), 'ccap_validation_failed' );
		$trainer = (int) self::factory()->user->create( array( 'role' => Roles::TRAINER ) );
		$this->assertRejected( fn() => $service->revoke( Actor::user( $trainer ), $cert['uid'], 'fraud' ), 'ccap_forbidden' );
		( new \WP_User( $this->learner ) )->add_cap( 'ccap_manage_certificates' );
		PolicyService::flush();
		$this->assertRejected( fn() => $service->revoke( Actor::user( $this->learner ), $cert['uid'], 'fraud' ), 'ccap_forbidden' );
		( new \WP_User( $this->learner ) )->remove_cap( 'ccap_manage_certificates' );
		PolicyService::flush();

		self::assertSame( 'evidenced', Competency::current()->states( $this->learner, $this->course )['obj-safety']['state'] );
		self::assertTrue( $service->revoke( $manager, $cert['uid'], 'fraud', 'Proctoring report 17' ) );
		self::assertFalse( $service->revoke( $manager, $cert['uid'], 'other', 'again' ), 'final, no second revocation' );
		foreach ( self::certs( "certificate_uid = '" . $cert['uid'] . "'" ) as $row ) {
			self::assertSame( 'revoked', $row['status'] );
		}
		foreach ( array( $token1, $token2 ) as $token ) {
			$result = $service->verify( $token );
			self::assertSame( array( 'status', 'revoked_on' ), array_keys( $result ) );
			self::assertSame( 'revoked', $result['status'] );
		}
		self::assertSame( 1, self::events( 'certificate.revoked' ) );
		$db      = Database::current();
		$payload = (string) $db->scalar( 'SELECT payload FROM `' . $db->table( 'ccap_outbox_event' ) . "` WHERE event_type = 'certificate.revoked'" );
		self::assertStringContainsString( '"reason":"fraud"', $payload );
		self::assertStringNotContainsString( 'Proctoring', $payload, 'only the category' );
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'certificate_revoked'" ) );
		self::assertSame( 'revoked', Competency::current()->states( $this->learner, $this->course )['obj-safety']['state'], 'R0-23 §5.4' );
		$this->assertRejected( fn() => $service->correct( $manager, $cert['uid'], 'revive', array( 'course_title' => 'x' ) ), 'ccap_validation_failed' );
	}

	/**
	 * CRT-015 / CRT-016: verification per state with the field policy; unknown without oracle.
	 *
	 * @group CRT-015
	 * @group CRT-016
	 */
	public function test_CRT_015_016_verification_policy(): void {
		$cert    = $this->exam_certificate( array( 'competency_validity_days' => 30 ) );
		$service = CertificateService::current();
		$token   = CertificateService::token( $cert['uid'], 1 );
		$valid   = $service->verify( $token );
		self::assertSame( 'valid', $valid['status'] );
		self::assertSame( array( 'check_code', 'course_title', 'display_id', 'issued_on', 'issuer', 'objectives', 'status', 'valid_until' ), self::sorted_keys( $valid ) );
		self::assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', (string) $valid['issued_on'], 'date without time' );
		self::assertSame( gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ), $valid['valid_until'] );
		$json = (string) wp_json_encode( $valid );
		foreach ( array( $this->email, 'Jürgen', '"user_id"', 'points', '3000', 'reason', 'base', 'template' ) as $never ) {
			self::assertStringNotContainsString( $never, $json, $never );
		}

		// Expired: derived display, not a state.
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . '` SET evaluated_at = %s WHERE id = %d', gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS ), $cert['session'] ) );
		CertificateService::current()->correct( Actor::user( $this->manager ), $cert['uid'], 'refresh', array( 'refresh_evidence' => true ) );
		$expired = $service->verify( CertificateService::token( $cert['uid'], 2 ) );
		self::assertSame( 'expired', $expired['status'] );
		self::assertSame( 'issued', self::certs( "certificate_uid = '" . $cert['uid'] . "' AND revision_no = 2" )[0]['status'] );

		$this->assertRejected( fn() => $service->verify( str_repeat( 'a', 40 ) ), 'ccap_not_found' );
		$this->assertRejected( fn() => $service->verify( substr( $token, 0, 39 ) . 'x' ), 'ccap_not_found' );
	}

	/**
	 * Sorted keys.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return list<string>
	 */
	private static function sorted_keys( array $data ): array {
		$keys = array_map( 'strval', array_keys( $data ) );
		sort( $keys );
		return $keys;
	}

	/**
	 * CRT-017 / SET-091: name opt-in per certificate, immediate, all revisions; policy `never`.
	 *
	 * @group CRT-017
	 */
	public function test_CRT_017_name_opt_in(): void {
		$cert    = $this->exam_certificate();
		$service = CertificateService::current();
		$token   = CertificateService::token( $cert['uid'], 1 );
		self::assertArrayNotHasKey( 'holder_name', $service->verify( $token ), 'default off (E-97)' );
		$this->assertRejected( fn() => $service->set_name_public( Actor::user( $this->manager ), $cert['uid'], true ), 'ccap_not_found' );
		$service->set_name_public( Actor::user( $this->learner ), $cert['uid'], true );
		self::assertSame( 'Jürgen Müller-Lüdenscheidt', $service->verify( $token )['holder_name'] );
		$service->correct( Actor::user( $this->manager ), $cert['uid'], 'typo', array( 'course_title' => 'Neu' ) );
		self::assertArrayHasKey( 'holder_name', $service->verify( CertificateService::token( $cert['uid'], 2 ) ), 'all revisions' );
		$service->set_name_public( Actor::user( $this->learner ), $cert['uid'], false );
		self::assertArrayNotHasKey( 'holder_name', $service->verify( CertificateService::token( $cert['uid'], 2 ) ), 'immediately' );

		$service->set_name_public( Actor::user( $this->learner ), $cert['uid'], true );
		$this->settings( array( 'holder_name_policy' => 'never' ) );
		self::assertArrayNotHasKey( 'holder_name', $service->verify( CertificateService::token( $cert['uid'], 2 ) ) );
		$this->assertRejected( fn() => $service->set_name_public( Actor::user( $this->learner ), $cert['uid'], true ), 'ccap_forbidden' );
	}

	/**
	 * CRT-018 / CRT-019: template change → new version, old snapshots unchanged; check code; integrity.
	 *
	 * @group CRT-018
	 * @group CRT-019
	 */
	public function test_CRT_018_019_template_and_integrity(): void {
		$cert    = $this->exam_certificate();
		$service = CertificateService::current();
		$before  = $cert['row'];
		$this->settings(
			array(
				'base_template' => array(
					'issuer_name'     => 'Akademie Nord',
					'footer_text'     => 'Fußtext',
					'logo_id'         => 0,
					'signature_label' => 'Leitung',
				),
			)
		);
		$template = CertificateService::template();
		self::assertSame( (int) $before['template_version'] + 1, $template['version'] );
		self::assertSame( $template['version'], CertificateService::template()['version'], 'stable without change' );
		self::assertSame( $before['snapshot'], self::certs( 'id = ' . (int) $before['id'] )[0]['snapshot'], 'old certificate unchanged' );
		$second = $service->issue( Actor::user( $this->manager ), CertificateService::EVIDENCE_EXAM, $this->take_exam( $this->rule() ), 'second' );
		$snap   = json_decode( (string) self::certs( 'id = ' . $second['id'] )[0]['snapshot'], true );
		self::assertSame( 'Akademie Nord', $snap['issuer']['name'] );
		self::assertSame( 'Leitung', $snap['issuer']['signature_label'] );
		self::assertSame( $template['version'], $snap['template']['version'] );

		// Check code: print = verification page.
		$view = $service->view( Actor::user( $this->learner ), $cert['uid'] );
		$code = $service->verify( CertificateService::token( $cert['uid'], 1 ) )['check_code'];
		self::assertMatchesRegularExpression( '/^[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/', $code );
		self::assertStringContainsString( $code, CertificatePages::render( $view['snapshot'], $view['status'], $view['verify_url'], (string) $view['row']['snapshot_hash'] ) );
		self::assertSame( array(), $service->integrity_failures() );
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_certificate' ) . '` SET snapshot = REPLACE(snapshot, %s, %s) WHERE id = %d', 'Grundkurs', 'Grundkurz', (int) $before['id'] ) );
		self::assertSame( array( $cert['uid'] ), $service->integrity_failures() );
	}

	/**
	 * CRT-005 / CRT-022 / CRT-023: HTML/print view; fallback for other template kinds.
	 *
	 * @group CRT-005
	 * @group CRT-022
	 * @group CRT-023
	 */
	public function test_CRT_005_022_023_rendering(): void {
		wp_update_post(
			array(
				'ID'         => $this->course,
				'post_title' => 'دورة السلامة المتقدمة للمشرفين في المصانع الكبيرة',
			)
		);
		$cert = $this->exam_certificate();
		$page = CertificatePages::certificate_page( Actor::user( $this->learner ), $cert['uid'] );
		self::assertSame( 200, $page['status'] );
		$html = $page['html'];
		self::assertStringContainsString( 'Jürgen Müller-Lüdenscheidt', $html );
		self::assertStringContainsString( 'دورة السلامة', $html );
		self::assertStringContainsString( 'dir="auto"', $html );
		self::assertStringContainsString( '@page{size:A4', $html );
		self::assertStringContainsString( CertificateSnapshot::display_id( $cert['uid'] ), $html );
		self::assertStringContainsString( CertificatePages::verify_url( CertificateService::token( $cert['uid'], 1 ) ), $html, 'verification link as text' );
		self::assertStringNotContainsString( '<img', $html, 'text instead of images' );
		self::assertStringNotContainsString( 'pdf', strtolower( $html ) );
		self::assertSame( 1, substr_count( $html, '<h1' ) );
		$other = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::assertSame( 404, CertificatePages::certificate_page( Actor::user( $other ), $cert['uid'] )['status'] );
		self::assertStringNotContainsString( 'pdf', strtolower( (string) wp_json_encode( CertificateService::current()->mine( Actor::user( $this->learner ) ) ) ), 'R-40 without pdf_url' );

		$snapshot                     = json_decode( (string) $cert['row']['snapshot'], true );
		$snapshot['template']['kind'] = 'designer';
		$snapshot['fields']           = array(
			array(
				'key'   => 'ue',
				'label' => 'Unterrichtseinheiten',
				'value' => '16',
				'type'  => 'number',
			),
		);
		$snapshot['assets']           = array(
			array(
				'role'          => 'signature',
				'attachment_id' => 5,
				'sha256'        => str_repeat( 'a', 64 ),
				'alt'           => 'Unterschrift der Leitung',
			),
		);
		$fallbacks                    = array();
		$listener                     = static function ( $kind ) use ( &$fallbacks ): void {
			$fallbacks[] = $kind;
		};
		add_action( 'course_captain_certificate_fallback_rendered', $listener );
		$designer = CertificatePages::render( $snapshot, 'valid', 'https://example.org/v', (string) $cert['row']['snapshot_hash'] );
		self::assertStringContainsString( 'Shown in the standard layout.', $designer );
		self::assertStringContainsString( 'Unterrichtseinheiten', $designer );
		self::assertStringContainsString( 'Unterschrift der Leitung', $designer );
		$snapshot['template']['kind'] = 'xyz';
		self::assertStringContainsString( 'Shown in the standard layout.', CertificatePages::render( $snapshot, 'valid', 'https://example.org/v', (string) $cert['row']['snapshot_hash'] ) );
		remove_action( 'course_captain_certificate_fallback_rendered', $listener );
		self::assertSame( array( 'designer', 'xyz' ), $fallbacks, 'diagnostic entry' );
	}

	/**
	 * CRT-006: export with snapshot data, no hashes/tokens; erasure deletes, verification → not found.
	 *
	 * @group CRT-006
	 */
	public function test_CRT_006_privacy(): void {
		$cert   = $this->exam_certificate();
		$token  = CertificateService::token( $cert['uid'], 1 );
		$export = array_values( array_filter( PrivacyService::export( $this->email )['data'], static fn( array $i ): bool => 'cc-certificates' === $i['group_id'] ) );
		self::assertCount( 1, $export );
		$json = (string) wp_json_encode( $export );
		self::assertStringContainsString( 'Grundkurs Sicherheit', $json );
		self::assertStringContainsString( CertificateSnapshot::display_id( $cert['uid'] ), $json );
		self::assertStringNotContainsString( 'hash', $json );
		self::assertStringNotContainsString( 'token', $json );
		CertificateService::current()->set_name_public( Actor::user( $this->learner ), $cert['uid'], true );

		PrivacyService::erase( $this->email );
		self::assertCount( 0, self::certs() );
		self::assertSame( '', get_user_meta( $this->learner, CertificateService::NAME_META, true ) );
		$this->assertRejected( fn() => CertificateService::current()->verify( $token ), 'ccap_not_found' );
	}

	/**
	 * RT-09: certificates expire 10 years after their newest revision.
	 *
	 * @group CRT-006
	 */
	public function test_RT_09_retention(): void {
		$cert = $this->exam_certificate();
		CertificateService::current()->correct( Actor::user( $this->manager ), $cert['uid'], 'typo', array( 'course_title' => 'Neu' ) );
		self::assertSame( 0, RetentionService::run()['RT-09_certificates'] );
		self::assertSame( 2, RetentionService::run( time() + 3654 * DAY_IN_SECONDS )['RT-09_certificates'] );
		self::assertCount( 0, self::certs() );
	}

	/**
	 * CRT-029 and R-42/R-40 over REST: token minimal, UID without right not found, manager full;
	 * cache headers; rate limit fail-closed bucket.
	 *
	 * @group CRT-029
	 */
	public function test_CRT_029_reference_and_rest(): void {
		$cert    = $this->exam_certificate();
		$service = CertificateService::current();
		$token   = CertificateService::token( $cert['uid'], 1 );
		self::assertSame( 'valid', $service->verify_reference( Actor::user( 0 ), $token )['status'] );
		$other = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertRejected( fn() => $service->verify_reference( Actor::user( $other ), $cert['uid'] ), 'ccap_not_found' );
		$full = $service->verify_reference( Actor::user( $this->manager ), $cert['uid'] );
		self::assertSame( 1, $full['revision_no'] );
		self::assertArrayHasKey( 'snapshot', $full );

		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		wp_set_current_user( 0 );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/certificates/verify/' . $token ) );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
		self::assertSame( 'valid', $response->get_data()['status'] );
		self::assertSame( 404, rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/certificates/verify/' . str_repeat( 'b', 40 ) ) )->get_status() );

		wp_set_current_user( $this->learner );
		$mine = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/me/certificates' ) );
		self::assertSame( 200, $mine->get_status() );
		self::assertSame( 'private, no-store', $mine->get_headers()['Cache-Control'] );
		self::assertSame( $cert['uid'], $mine->get_data()[0]['uid'] );
		self::assertArrayNotHasKey( 'pdf_url', $mine->get_data()[0] );
		$put = new \WP_REST_Request( 'PUT', '/course-captain/v1/me/certificates/' . $cert['uid'] . '/visibility' );
		$put->set_param( 'holder_name_public', true );
		self::assertTrue( rest_do_request( $put )->get_data()['holder_name_public'] );

		// Learner lock: own list locked, verification stays (RBAC-066).
		$db = Database::current();
		$db->insert(
			'ccap_learner_lock',
			array(
				'user_id'     => $this->learner,
				'reason_code' => 'test',
				'valid_from'  => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		self::assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/me/certificates' ) )->get_status() );
		self::assertSame( 200, CertificatePages::verification_page( $token, 'client-a' )['status'] );
		wp_set_current_user( 0 );

		// Rate limit `verify`: 30 per minute and client.
		for ( $i = 0; $i < 29; $i++ ) {
			$service->rate_limit( 'client-b' );
		}
		$page = CertificatePages::verification_page( $token, 'client-b' );
		self::assertSame( 200, $page['status'] );
		self::assertStringContainsString( 'noindex', $page['html'] );
		self::assertSame( 429, CertificatePages::verification_page( $token, 'client-b' )['status'] );
	}

	/**
	 * Competency second source (R0-08 §5.3) and R0-23 §5.2 review task on passed → failed.
	 *
	 * @group R1-44
	 */
	public function test_competency_and_review(): void {
		$cert = $this->exam_certificate(
			array(
				'certifies_objectives'     => array( 'obj-a' ),
				'competency_validity_days' => 30,
			)
		);
		$db   = Database::current();
		// Attempt gone (e.g. attempt retention), the certificate still evidences the objective.
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . "` SET status = 'broken' WHERE id = %d", $cert['session'] ) );
		$state = Competency::current()->states( $this->learner, $this->course, array( 'obj-a', 'obj-b' ) );
		self::assertSame( 'evidenced', $state['obj-a']['state'] );
		self::assertSame( $cert['uid'], $state['obj-a']['source'] );
		self::assertSame( 'not_evidenced', $state['obj-b']['state'] );
		self::assertSame( 'expired', Competency::current()->states( $this->learner, $this->course, array(), time() + 40 * DAY_IN_SECONDS )['obj-a']['state'] );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . "` SET status = 'evaluated' WHERE id = %d", $cert['session'] ) );

		// Re-evaluation passed → failed: no automatic revocation, a review task.
		$admin = Actor::user( $this->admin );
		ExamSessions::current()->regrade( $admin, $cert['session'], 1, 0, 'wrong key' );
		ExamSessions::current()->regrade( $admin, $cert['session'], 2, 0, 'wrong key' );
		OutboxDispatcher::current()->dispatch();
		self::assertSame( 'issued', self::certs( "certificate_uid = '" . $cert['uid'] . "'" )[0]['status'] );
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'certificate_review_required'" ) );
	}

	/**
	 * Progress row of the learner's course completion.
	 *
	 * @return int
	 */
	private function progress_id(): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_course_progress' ) . '` WHERE user_id = %d', $this->learner ) );
	}

	/**
	 * Pretend that language packs are installed (locale switcher and `get_available_languages`).
	 *
	 * @param array $locales Locales besides en_US.
	 * @phpstan-param list<string> $locales
	 * @return callable Filter to remove again.
	 */
	private function install_locales( array $locales ): callable {
		$filter   = static fn(): array => $locales;
		add_filter( 'get_available_languages', $filter );
		$property = new \ReflectionProperty( $GLOBALS['wp_locale_switcher'], 'available_languages' );
		$property->setValue( $GLOBALS['wp_locale_switcher'], array_merge( array( 'en_US' ), $locales ) );
		return $filter;
	}

	/**
	 * SET-CC-08: default validity for evidence without an own validity; an
	 * exam rule's own validity wins; changing the setting never touches issued
	 * certificates (snapshot, hash); a correction keeps the issued date;
	 * renewal notice lead time from the setting.
	 *
	 * @group SET-CC-08
	 */
	public function test_SET_CC_08_default_validity_and_notice(): void {
		$this->settings(
			array(
				'auto_issue'            => true,
				'default_validity_days' => 365,
			)
		);
		$this->complete_course();
		$rows = self::certs( "evidence_type = 'course_completion'" );
		self::assertCount( 1, $rows );
		$snapshot = json_decode( (string) $rows[0]['snapshot'], true );
		$done     = (string) Database::current()->scalar( Database::current()->prepare( 'SELECT completed_at FROM `' . Database::current()->table( 'ccap_course_progress' ) . '` WHERE id = %d', $this->progress_id() ) );
		self::assertSame( CertificateService::site_date_after( $done, 365 ), $snapshot['valid_until'], 'completion certificate gets the default validity' );
		self::assertSame( array( 'language', 'paper' ), array_keys( $snapshot['layout'] ), 'canonical (sorted) keys' );

		// Settings change: the issued certificate stays byte-identical.
		$this->settings(
			array(
				'default_validity_days' => 30,
				'paper_format'          => 'letter',
			)
		);
		$again = self::certs( "evidence_type = 'course_completion'" );
		self::assertSame( $rows[0]['snapshot'], $again[0]['snapshot'] );
		self::assertSame( $rows[0]['snapshot_hash'], $again[0]['snapshot_hash'] );
		self::assertTrue( CertificateSnapshot::intact( (string) $again[0]['snapshot'], (string) $again[0]['snapshot_hash'] ) );

		// Correction with fresh evidence keeps the date of the issuance (no own validity).
		$uid       = (string) $rows[0]['certificate_uid'];
		$corrected = CertificateService::current()->correct( Actor::user( $this->manager ), $uid, 'refresh', array( 'refresh_evidence' => true ) );
		self::assertNotEmpty( $corrected );
		$latest = CertificateService::current()->latest( $uid );
		self::assertSame( $snapshot['valid_until'], json_decode( (string) $latest['snapshot'], true )['valid_until'] );

		// An exam rule with its own validity wins over the default.
		$exam      = $this->exam_certificate( array( 'competency_validity_days' => 10 ) );
		$evaluated = (string) Database::current()->scalar( Database::current()->prepare( 'SELECT evaluated_at FROM `' . Database::current()->table( 'ccap_exam_session' ) . '` WHERE id = %d', $exam['session'] ) );
		self::assertSame( CertificateService::site_date_after( $evaluated, 10 ), json_decode( (string) $exam['row']['snapshot'], true )['valid_until'] );

		// Renewal notice: from `expiry_notice_days` before the end.
		$in_20 = CertificateService::site_today( 20 );
		self::assertTrue( CertificateService::recertify_hint( $in_20 ), 'default 30 days' );
		$this->settings( array( 'expiry_notice_days' => 10 ) );
		self::assertFalse( CertificateService::recertify_hint( $in_20 ) );
		self::assertTrue( CertificateService::recertify_hint( CertificateService::site_today( 5 ) ) );
		self::assertFalse( CertificateService::recertify_hint( null ), 'unlimited certificates never show it' );

		// Unlimited stays the default.
		$this->settings( array( 'default_validity_days' => null ) );
		self::assertNull( Settings::get( 'certificates.default_validity_days' ) );
	}

	/**
	 * SET-CC-08: paper format and language are snapshotted at issuance;
	 * certificates issued before keep A4 and the request language.
	 *
	 * @group SET-CC-08
	 */
	public function test_SET_CC_08_paper_and_language(): void {
		$filter = $this->install_locales( array( 'de_DE' ) );
		try {
			// Fixed locale must be installed.
			$admin = Actor::user( $this->admin );
			$check = Settings::check(
				$admin,
				'certificates',
				array(
					'language'     => 'fixed',
					'fixed_locale' => 'fr_FR',
				),
				array( 'language', 'fixed_locale' )
			);
			self::assertSame( 'locale', $check['errors']['fixed_locale'] ?? null );
			$check = Settings::check( $admin, 'certificates', array( 'language' => 'fixed' ), array( 'language' ) );
			self::assertSame( 'locale', $check['errors']['fixed_locale'] ?? null, 'fixed without a locale' );

			$this->settings(
				array(
					'paper_format' => 'letter',
					'language'     => 'fixed',
					'fixed_locale' => 'de_DE',
				)
			);
			$cert     = $this->exam_certificate();
			$snapshot = json_decode( (string) $cert['row']['snapshot'], true );
			self::assertSame(
				array(
					'language' => 'fixed',
					'paper'    => 'letter',
				),
				$snapshot['layout']
			);
			self::assertSame( 'de_DE', $snapshot['locale']['ui'] );
			self::assertSame( 'Leistungszertifikat', $snapshot['texts']['heading'], 'fixed texts in the certificate language' );
			self::assertSame( 'en_US', determine_locale(), 'locale restored after issuing' );
			$page = CertificatePages::certificate_page( Actor::user( $this->learner ), $cert['uid'] );
			self::assertStringContainsString( '@page{size:letter', $page['html'] );
			self::assertStringContainsString( 'Ausgestellt am', $page['html'], 'labels in the certificate language' );
			self::assertStringContainsString( '>Print<', $page['html'], 'controls stay in the viewer language' );

			// Learner mode: profile language of the learner.
			$this->settings( array( 'language' => 'learner' ) );
			update_user_meta( $this->learner, 'locale', 'de_DE' );
			$progress = $this->progress_id();
			if ( 0 === $progress ) {
				$this->complete_course();
				$progress = $this->progress_id();
			}
			$issued = CertificateService::current()->issue( Actor::user( $this->manager ), CertificateService::EVIDENCE_COURSE, $progress, 'support case' );
			$row    = self::certs( "certificate_uid = '" . $issued['certificate_uid'] . "'" )[0];
			$learn  = json_decode( (string) $row['snapshot'], true );
			self::assertSame( 'de_DE', $learn['locale']['ui'] );
			self::assertSame( 'Teilnahmezertifikat', $learn['texts']['heading'] );

			// A certificate issued before the settings existed (no `layout`) renders as before: A4, request language.
			unset( $snapshot['layout'] );
			$snapshot['locale']['ui'] = 'en_US';
			$old                      = CertificatePages::render( $snapshot, 'valid', 'https://example.org/v', (string) $cert['row']['snapshot_hash'] );
			self::assertStringContainsString( 'Issued on', $old );
			$snapshot['locale']['ui'] = 'de_DE';
			self::assertStringContainsString( 'Issued on', CertificatePages::render( $snapshot, 'valid', 'https://example.org/v', (string) $cert['row']['snapshot_hash'] ), 'no switch without layout' );
			$legacy = $snapshot;
			unset( $legacy['snapshot_hash'] );
			CertificateSnapshot::validate( $legacy );
		} finally {
			remove_filter( 'get_available_languages', $filter );
			delete_user_meta( $this->learner, 'locale' );
		}
	}

	/**
	 * Learning history of the learner without volatile columns (R0-41 §3:
	 * expected DB state "history unchanged").
	 *
	 * @return array<string, list<array<string, mixed>>>
	 */
	private function history(): array {
		$db  = Database::current();
		$out = array();
		foreach ( array( 'ccap_exam_session', 'ccap_certificate', 'ccap_course_progress', 'ccap_activity_progress' ) as $table ) {
			$rows = $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( $table ) . '` WHERE user_id = %d ORDER BY id', $this->learner ) );
			foreach ( $rows as $i => $row ) {
				unset( $rows[ $i ]['updated_at'], $rows[ $i ]['last_access_at'], $rows[ $i ]['row_version'] );
			}
			$out[ $table ] = $rows;
		}
		return $out;
	}

	/**
	 * Count rows of a table under a condition.
	 *
	 * @param string $table Table without prefix.
	 * @param string $where Condition.
	 * @return int
	 */
	private static function count_rows( string $table, string $where = '1=1' ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . "` WHERE {$where}" );
	}

	/**
	 * CORE-06 (R1 part, konzept 35.3/35.5; R0-41 §3): refund of the only
	 * source after course completion and certificate. The grant of this
	 * source is revoked (`order_refunded`, grant event and `access.changed`),
	 * the course is locked again; progress, exam attempt and the certificate
	 * stay unchanged and the certificate still verifies as valid. No
	 * certificate event, no certificate audit entry.
	 *
	 * @group CORE-06
	 * @group GRANT-028
	 * @group REV-046
	 */
	public function test_CORE_06_refund_after_certificate_keeps_history_and_status(): void {
		$cert = $this->exam_certificate();
		$this->complete_course();
		self::assertCount( 1, self::certs(), 'completion adds no second certificate' );
		$learner = Actor::user( $this->learner );
		self::assertTrue( ContentGate::current()->lesson( $learner, $this->course, $this->lessons[0] )->allowed() );
		$history = $this->history();
		$token   = CertificateService::token( $cert['uid'], 1 );
		$before  = CertificateService::current()->verify( $token );
		$changed = self::count_rows( 'ccap_outbox_event', "event_type = 'access.changed'" );
		$audit   = self::count_rows( 'ccap_audit_log', "action LIKE 'certificate%'" );

		self::assertSame( GrantTransitions::REVOKED, GrantService::current()->transition( Actor::cron(), $this->grant, GrantTransitions::REVOKED, 'order_refunded' ) );
		PolicyService::flush();
		OutboxDispatcher::current()->dispatch();

		// Access from this source is gone.
		self::assertFalse( ContentGate::current()->lesson( $learner, $this->course, $this->lessons[0] )->allowed() );
		self::assertSame( 1, self::count_rows( 'ccap_grant_event', 'grant_id = ' . $this->grant . " AND from_status = 'active' AND to_status = 'revoked' AND reason_code = 'order_refunded'" ) );
		self::assertSame( $changed + 1, self::count_rows( 'ccap_outbox_event', "event_type = 'access.changed'" ) );
		// History and certificate status unchanged.
		self::assertSame( $history, $this->history() );
		self::assertSame( $before, CertificateService::current()->verify( $token ) );
		self::assertSame( 'valid', $before['status'] );
		self::assertSame( array( $cert['uid'] ), array_column( CertificateService::current()->mine( $learner ), 'uid' ), 'the learner still sees the certificate' );
		self::assertSame( 0, self::events( 'certificate.revoked' ) );
		self::assertSame( $audit, self::count_rows( 'ccap_audit_log', "action LIKE 'certificate%'" ), 'a refund is no certificate action' );
		// A late "paid" for the same source revision does not re-grant (CORE-07).
		self::assertSame( 'ignored', GrantService::current()->transition( Actor::cron(), $this->grant, GrantTransitions::ACTIVE, 'order_paid' ) );
		self::assertSame( 'revoked', (string) Database::current()->scalar( 'SELECT status FROM `' . Database::current()->table( 'ccap_grant' ) . '` WHERE id = ' . $this->grant ) );
	}

	/**
	 * REV-046 (R0-23 RV-13, §5.4, §5.5): an issued certificate stays as it
	 * is after a new course revision, a new template version, a change of
	 * the profile name and the withdrawal of the course: status `issued`,
	 * snapshot and template version unchanged, the verification stays
	 * valid. A recertification issues a new `certificate_uid`; the old one
	 * stays `issued`. The refund and the revocation (all revisions
	 * `revoked`, no revival) are part of REV-046 in
	 * `test_CORE_06_refund_after_certificate_keeps_history_and_status()` and
	 * `test_CRT_013_014_028_revocation()`.
	 *
	 * @group REV-046
	 */
	public function test_REV_046_certificate_survives_course_and_template_changes(): void {
		$cert      = $this->exam_certificate();
		$service   = CertificateService::current();
		$token     = CertificateService::token( $cert['uid'], 1 );
		$before    = self::certs( "certificate_uid = '" . $cert['uid'] . "'" );
		$publisher = CoursePublisher::current();
		$admin     = Actor::user( $this->admin );
		self::assertCount( 1, $before );
		self::assertSame( 'valid', $service->verify( $token )['status'] );

		// New course revision, new template version, new profile name, course withdrawn.
		$revision = (int) $publisher->current_revision( $this->course )['id'];
		$this->post( 'ccap_lesson', $this->module, 'Additional lesson' );
		self::assertNotSame( $revision, $publisher->publish( $admin, $this->course, 'minor' ), 'a new course revision' );
		$this->settings(
			array(
				'base_template' => array(
					'issuer_name'     => 'Akademie Süd',
					'footer_text'     => 'Neu',
					'logo_id'         => 0,
					'signature_label' => 'Vorstand',
				),
			)
		);
		self::assertSame( (int) $before[0]['template_version'] + 1, CertificateService::template()['version'] );
		wp_update_user(
			array(
				'ID'           => $this->learner,
				'display_name' => 'Jürgen Anders',
			)
		);
		$publisher->archive( $admin, $this->course );
		OutboxDispatcher::current()->dispatch();

		$after = self::certs( "certificate_uid = '" . $cert['uid'] . "'" );
		self::assertCount( 1, $after, 'no automatic correction' );
		self::assertSame( 'issued', $after[0]['status'] );
		self::assertSame( $before[0]['snapshot'], $after[0]['snapshot'], 'snapshot unchanged' );
		self::assertSame( $before[0]['template_version'], $after[0]['template_version'], 'rendered with the old template version' );
		self::assertSame( 'valid', $service->verify( $token )['status'] );
		self::assertSame( 0, self::events( 'certificate.revoked' ) );
		self::assertSame( 0, self::events( 'certificate.corrected' ) );

		// Recertification after the course is offered again: new UID, the old certificate stays issued.
		$publisher->publish( $admin, $this->course );
		$second = $service->issue( Actor::user( $this->manager ), CertificateService::EVIDENCE_EXAM, $this->take_exam( $this->rule() ), 'recertification' );
		$rows   = self::certs( 'id = ' . (int) $second['id'] );
		self::assertNotSame( $cert['uid'], (string) $rows[0]['certificate_uid'] );
		self::assertSame( 'issued', self::certs( "certificate_uid = '" . $cert['uid'] . "'" )[0]['status'] );
		self::assertSame( 'valid', $service->verify( $token )['status'] );
	}

	/**
	 * CORE-10 (Free part, R1-18; konzept 35.4/35.5, R0-39 FG-03/FG-04,
	 * R0-11 ER-3; R0-41 §3): the Pro licence ends and the add-on is
	 * deactivated. Free never reads licence state and sends no request, so
	 * learning goes on (lesson, new exam attempt, course completion), the
	 * stored history stays byte-identical and the certificate verifies as
	 * before. The Pro part (frozen designer template, CERT-008/CRT-064)
	 * belongs to R3-15.
	 *
	 * @group CORE-10
	 * @group SA-001
	 */
	public function test_CORE_10_pro_licence_end_keeps_free_learning_history_and_verification(): void {
		$cert    = $this->exam_certificate();
		$learner = Actor::user( $this->learner );
		ActivityProgress::current()->interact( $learner, $this->course, $this->lessons[0], 'confirm' );
		OutboxDispatcher::current()->dispatch();
		$token    = CertificateService::token( $cert['uid'], 1 );
		$verified = CertificateService::current()->verify( $token );
		self::assertSame( 'valid', $verified['status'] );
		$history  = $this->history();
		$requests = count( $GLOBALS['ccap_test_http_requests'] );
		$audit    = self::count_rows( 'ccap_audit_log', "action IN ('certificate_corrected', 'certificate_revoked')" );

		// What an add-on would leave behind at licence end (R0-39 §3: class E/A). Free must not look at it.
		$reads   = 0;
		$options = array( 'course_captain_pro_license', 'course_captain_pro_license_state', 'course_captain_pro_settings_general' );
		$probe   = static function ( $value ) use ( &$reads ) {
			++$reads;
			return $value;
		};
		foreach ( $options as $option ) {
			add_filter( 'pre_option_' . $option, $probe );
		}
		update_option(
			'course_captain_pro_license',
			array(
				'status'     => 'expired',
				'expires_at' => gmdate( 'c', time() - DAY_IN_SECONDS ),
			),
			false
		);
		$reads = 0;
		do_action( 'deactivated_plugin', 'course-captain-pro/course-captain-pro.php', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook, simulates the add-on deactivation.
		PolicyService::flush();

		try {
			// History and verification unchanged.
			self::assertSame( $history, $this->history() );
			self::assertSame( $verified, CertificateService::current()->verify( $token ) );
			self::assertSame( array( $cert['uid'] ), array_column( CertificateService::current()->mine( $learner ), 'uid' ) );

			// Free learning goes on: the next lesson, a new exam attempt, course completion.
			self::assertTrue( ContentGate::current()->lesson( $learner, $this->course, $this->lessons[1] )->allowed() );
			ActivityProgress::current()->interact( $learner, $this->course, $this->lessons[1], 'confirm' );
			$second = $this->take_exam( $this->rule() );
			OutboxDispatcher::current()->dispatch();
			self::assertNotSame( $cert['session'], $second );
			self::assertSame( 1, self::count_rows( 'ccap_exam_session', 'id = ' . $second . " AND passed = 1 AND status = 'evaluated'" ), 'new attempt evaluated' );
			self::assertSame( 'completed', (string) Database::current()->scalar( 'SELECT status FROM `' . Database::current()->table( 'ccap_course_progress' ) . '` WHERE user_id = ' . $this->learner ) );
			$after = $this->history();
			self::assertCount( 1, $history['ccap_certificate'] );
			self::assertSame( $history['ccap_certificate'][0], $after['ccap_certificate'][0], 'the issued certificate is untouched' );
			self::assertCount( 2, $after['ccap_certificate'], 'Free keeps issuing base certificates (new evidence, CR-5)' );
			self::assertSame( $history['ccap_exam_session'][0], $after['ccap_exam_session'][0], 'the earlier attempt is untouched' );
			self::assertSame( $verified, CertificateService::current()->verify( $token ) );

			// Free never asked for licence state and sent no request (FG-03, FG-04, LG).
			self::assertSame( 0, $reads, 'no read of add-on licence options' );
			self::assertSame( $requests, count( $GLOBALS['ccap_test_http_requests'] ) );
			self::assertSame( $audit, self::count_rows( 'ccap_audit_log', "action IN ('certificate_corrected', 'certificate_revoked')" ), 'no correction or revocation' );
		} finally {
			foreach ( $options as $option ) {
				remove_filter( 'pre_option_' . $option, $probe );
			}
			delete_option( 'course_captain_pro_license' );
		}
	}
}
