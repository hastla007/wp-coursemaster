<?php
/**
 * R1-08: exam rules, attempts, snapshot, evaluation (R0-08 §4/§6, R0-05).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-08
 */
final class ExamTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner with grant.
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
	 * Modules A1, A2.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Questions per module.
	 *
	 * @var array<int, list<int>>
	 */
	private array $pool = array();

	/**
	 * Grant of the learner.
	 *
	 * @var int
	 */
	private int $grant;

	/**
	 * Course with two modules (4 + 6 single-choice questions, one open question in A1).
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course' );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		foreach ( array( 4, 6 ) as $count ) {
			$module          = $this->post( 'ccap_module' );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module );
			for ( $i = 0; $i < $count; $i++ ) {
				$this->pool[ $module ][] = $this->question( $module, QuestionTypes::SINGLE );
			}
		}
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
	}

	/**
	 * Published post.
	 *
	 * @param string $type Type.
	 * @return int
	 */
	private function post( string $type ): int {
		return (int) wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => $type,
				'post_author' => $this->admin,
			)
		);
	}

	/**
	 * Published question in a pool (correct option always A).
	 *
	 * @param int    $module Module.
	 * @param string $type   Type.
	 * @return int
	 */
	private function question( int $module, string $type ): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$data  = QuestionTypes::OPEN_TEXT === $type
			? array(
				'question_type' => $type,
				'question_text' => 'Open',
				'answers'       => array(
					array(
						'key'     => 'MODEL',
						'text'    => 'Model',
						'correct' => true,
					),
				),
			)
			: array(
				'question_type' => $type,
				'question_text' => 'Q ' . wp_generate_password( 6, false ),
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
					array(
						'key'  => 'C',
						'text' => 'wrong too',
					),
				),
			);
		$id    = $repo->create( $admin, $data );
		$repo->publish( $admin, $id );
		$repo->add_to_pool( $admin, $module, $id );
		return $id;
	}

	/**
	 * Published rule with blueprint A1:2, A2:3 and pass mark 3/5.
	 *
	 * @param array<string, mixed> $settings   Settings.
	 * @param int|null             $time_limit Time limit.
	 * @param bool                 $publish    Publish.
	 * @return int
	 */
	private function rule( array $settings = array(), ?int $time_limit = 600, bool $publish = true ): int {
		$rules = ExamRules::current();
		$admin = Actor::user( $this->admin );
		$rule  = $rules->create(
			$admin,
			$this->course,
			'v' . wp_generate_password( 4, false ),
			array(
				'min_ratio' => array(
					'num' => 3,
					'den' => 5,
				),
			),
			$time_limit,
			$settings
		);
		$rules->set_blueprint( $admin, $rule, $this->modules[0], 2 );
		$rules->set_blueprint( $admin, $rule, $this->modules[1], 3 );
		if ( $publish ) {
			$rules->publish( $admin, $rule );
		}
		return $rule;
	}

	/**
	 * Start helper.
	 *
	 * @param int      $rule    Rule.
	 * @param int|null $variant Variant.
	 * @return int
	 */
	private function start( int $rule, ?int $variant = null ): int {
		return ExamSessions::current()->start( Actor::user( $this->learner ), $rule, $variant, wp_generate_uuid4(), new Randomizer( '20300115' ) );
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
	 * Session row.
	 *
	 * @param int $session Session.
	 * @return array<string, mixed>
	 */
	private static function row( int $session ): array {
		$db = Database::current();
		return (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
	}

	/**
	 * Rule validation: no default pass mark (INV-C-132); published rules frozen.
	 */
	public function test_rule_rules(): void {
		$admin = Actor::user( $this->admin );
		$this->assertRejected( fn() => ExamRules::current()->create( $admin, $this->course, 'v1', array() ), 'ccap_validation_failed' );
		$rule = $this->rule();
		$this->assertRejected( fn() => ExamRules::current()->set_blueprint( $admin, $rule, $this->modules[0], 1 ), 'ccap_rule_frozen' );
		$second = $this->rule();
		self::assertSame( 'retired', ExamRules::current()->get( $rule )['status'] );
		self::assertSame( 'published', ExamRules::current()->get( $second )['status'] );
	}

	/**
	 * GM-027 / GM-028 / GM-029: start and resume; no solutions; blueprint composition; snapshot.
	 *
	 * @group GM-027
	 * @group GM-028
	 * @group GM-029
	 */
	public function test_GM_027_028_029_start_resume_and_composition(): void {
		$rule    = $this->rule();
		$session = $this->start( $rule );
		self::assertSame( $session, $this->start( $rule ), 'resume the running attempt' );

		$view = ExamSessions::current()->view( Actor::user( $this->learner ), $session );
		self::assertCount( 5, $view['questions'] );
		self::assertStringNotContainsString( 'correct', (string) wp_json_encode( $view ) );
		self::assertSame( 600, $view['remaining_seconds'] );

		$row      = self::row( $session );
		$snapshot = json_decode( (string) $row['snapshot'], true );
		self::assertSame( 'sha256:' . hash( 'sha256', (string) $row['snapshot'] ), $row['snapshot_hash'] );
		self::assertTrue( $snapshot['evidence_eligible'] );
		self::assertFalse( $snapshot['reduced'] );
		$sections = array_count_values( array_column( $snapshot['scoring'], 'section' ) );
		self::assertSame(
			array(
				$this->modules[0] => 2,
				$this->modules[1] => 3,
			),
			$sections,
			'grouped by blueprint section'
		);
		self::assertSame( 5, (int) $snapshot['timing']['grace_seconds'] );
		self::assertSame( 120, (int) $snapshot['timing']['late_tolerance_seconds'] );
	}

	/**
	 * GM-032 / GM-033: reduced and practice attempts are never evidence; drafts never startable.
	 *
	 * @group GM-032
	 * @group GM-033
	 */
	public function test_GM_032_033_reduced_practice_draft(): void {
		$admin = Actor::user( $this->admin );
		$rules = ExamRules::current();
		$big   = $rules->create(
			$admin,
			$this->course,
			'big',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			)
		);
		$rules->set_blueprint( $admin, $big, $this->modules[0], 100 );
		$rules->publish( $admin, $big );
		$snapshot = json_decode( (string) self::row( $this->start( $big ) )['snapshot'], true );
		self::assertTrue( $snapshot['reduced'] );
		self::assertFalse( $snapshot['evidence_eligible'] );

		$practice = $this->rule( array( 'purpose' => 'practice' ) );
		$snapshot = json_decode( (string) self::row( $this->start( $practice ) )['snapshot'], true );
		self::assertTrue( $snapshot['practice'] );
		self::assertFalse( $snapshot['evidence_eligible'] );

		$draft = $this->rule( array(), 600, false );
		$this->assertRejected( fn() => $this->start( $draft ), 'ccap_exam_not_startable' );
	}

	/**
	 * GM-034: fixed variant keeps the position order.
	 *
	 * @group GM-034
	 */
	public function test_GM_034_variant(): void {
		$rule      = $this->rule();
		$questions = array( $this->pool[ $this->modules[1] ][2], $this->pool[ $this->modules[0] ][0], $this->pool[ $this->modules[1] ][0] );
		$variant   = ExamRules::current()->create_variant( Actor::user( $this->admin ), $this->course, 1, $questions );
		$session   = $this->start( $rule, $variant );
		$db        = Database::current();
		$ids       = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY position', $session ) ) );
		self::assertSame( $questions, $ids );
		self::assertSame( 'variant', self::row( $session )['mode'] );
	}

	/**
	 * Evaluation against the snapshot; GM-039: no change after submit, idempotent submit.
	 *
	 * @group GM-039
	 */
	public function test_GM_039_submit_and_evaluation(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $this->start( $this->rule() );
		foreach ( array( 1, 2, 3 ) as $position ) {
			$sessions->save_answer( $learner, $session, $position, array( 'A' ) );
		}
		$sessions->save_answer( $learner, $session, 4, array( 'B' ) );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 5, array( 'Z' ) ), 'ccap_exam_invalid_answer' );

		$result = $sessions->submit( $learner, $session );
		self::assertSame( 'evaluated', $result['status'] );
		self::assertSame( 3000, $result['points_milli'] );
		self::assertSame( 5000, $result['max_points_milli'] );
		self::assertTrue( $result['passed'], '3/5 reaches 3/5' );
		self::assertSame( 'submitted', self::row( $session )['end_reason'] );

		self::assertSame( $result, $sessions->submit( $learner, $session ), 'second submit returns the same result' );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 5, array( 'A' ) ), 'ccap_exam_finished' );
		$this->assertRejected( fn() => $sessions->flag( $learner, $session, 1, true ), 'ccap_exam_finished' );
	}

	/**
	 * Author changes after the start do not change the result (frozen revisions).
	 */
	public function test_author_change_after_start_changes_nothing(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $this->start( $this->rule() );
		$db       = Database::current();
		$first    = (int) $db->scalar( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d AND position = 1', $session ) );
		$repo     = QuestionRepository::current();
		$repo->save(
			Actor::user( $this->admin ),
			$first,
			array(
				'question_type' => QuestionTypes::SINGLE,
				'question_text' => 'changed',
				'answers'       => array(
					array(
						'key'  => 'A',
						'text' => 'now wrong',
					),
					array(
						'key'     => 'B',
						'text'    => 'now right',
						'correct' => true,
					),
				),
			)
		);
		$repo->publish( Actor::user( $this->admin ), $first );
		$sessions->save_answer( $learner, $session, 1, array( 'A' ) );
		$result = $sessions->submit( $learner, $session );
		self::assertSame( 1000, $result['points_milli'], 'graded against the frozen revision' );
	}

	/**
	 * GM-040 / GM-041: cancel without evaluation; an overdue attempt is evaluated instead.
	 *
	 * @group GM-040
	 * @group GM-041
	 */
	public function test_GM_040_041_cancel(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $this->start( $this->rule() );
		$sessions->save_answer( $learner, $session, 1, array( 'A' ) );
		self::assertSame( 'cancelled', $sessions->cancel( $learner, $session ) );
		self::assertNull( self::row( $session )['points_milli'] );
		self::assertSame( 'cancelled', $sessions->cancel( $learner, $session ), 'repeat changes nothing' );
		$stranger = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertRejected( fn() => $sessions->cancel( Actor::user( $stranger ), $session ), 'ccap_not_found' );

		$other = $this->start( $this->rule() );
		$sessions->set_now( time() + 3 * 3600 );
		self::assertSame( 'evaluated', $sessions->cancel( $learner, $other ) );
		self::assertSame( 'expired', self::row( $other )['end_reason'] );
	}

	/**
	 * GM-035: grace, late answers, automatic evaluation after the tolerance.
	 *
	 * @group GM-035
	 */
	public function test_GM_035_expiry(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $this->start( $this->rule( array(), 60 ) );
		$sessions->set_now( time() + 63 );
		self::assertTrue( $sessions->save_answer( $learner, $session, 1, array( 'A' ) )['applied'], 'within 5 s grace' );
		$sessions->set_now( time() + 70 );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 2, array( 'A' ) ), 'ccap_exam_expired' );
		self::assertSame( 0, $sessions->expire_due(), 'still within late tolerance' );
		$sessions->set_now( time() + 60 + 121 );
		self::assertSame( 1, $sessions->expire_due() );
		self::assertSame( 'expired', self::row( $session )['end_reason'] );
	}

	/**
	 * GM-021: open question needs reveal; GM-047: flags idempotent.
	 *
	 * @group GM-021
	 * @group GM-047
	 */
	public function test_GM_021_047_open_question_and_flags(): void {
		$open    = $this->question( $this->modules[0], QuestionTypes::OPEN_TEXT );
		$admin   = Actor::user( $this->admin );
		$rule    = ExamRules::current()->create(
			$admin,
			$this->course,
			'open',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 1,
				),
			)
		);
		$variant = ExamRules::current()->create_variant( $admin, $this->course, 2, array( $open ) );
		ExamRules::current()->set_blueprint( $admin, $rule, $this->modules[0], 1 );
		ExamRules::current()->publish( $admin, $rule );
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $this->start( $rule, $variant );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 1, array( 'known' => true ) ), 'ccap_exam_answer_locked' );
		self::assertSame( 'Model', $sessions->reveal( $learner, $session, 1 ) );
		self::assertTrue( $sessions->save_answer( $learner, $session, 1, array( 'known' => true ) )['applied'] );

		self::assertTrue( $sessions->flag( $learner, $session, 1, true )['applied'] );
		$sessions->flag( $learner, $session, 1, true );
		self::assertTrue( $sessions->view( $learner, $session )['questions'][0]['flagged'] );
		$this->assertRejected( fn() => $sessions->flag( $learner, $session, 9, true ), 'ccap_not_found' );
		self::assertTrue( $sessions->submit( $learner, $session )['passed'] );
	}

	/**
	 * GM-043 / GM-044: running exam locks its questions in learning mode, without limit at most 24 h.
	 *
	 * @group GM-043
	 * @group GM-044
	 */
	public function test_GM_043_044_learning_lock(): void {
		$session  = $this->start( $this->rule( array(), null ) );
		$db       = Database::current();
		$question = (int) $db->scalar( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d AND position = 1', $session ) );
		$module   = in_array( $question, $this->pool[ $this->modules[0] ], true ) ? $this->modules[0] : $this->modules[1];
		$this->assertRejected( fn() => QuestionPresenter::current()->deliver( Actor::user( $this->learner ), $this->course, $module, $question ), 'ccap_content_locked' );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . '` SET started_at = %s WHERE id = %d', $db->now( -2 * 86400 ), $session ) );
		self::assertArrayHasKey( 'token', QuestionPresenter::current()->deliver( Actor::user( $this->learner ), $this->course, $module, $question ) );
	}

	/**
	 * GRANT-027 / E-113: answers refused after revocation; submission still evaluated.
	 *
	 * @group GRANT-027
	 */
	public function test_GRANT_027_revoked_during_exam(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $this->start( $this->rule() );
		$sessions->save_answer( $learner, $session, 1, array( 'A' ) );
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . "` SET status = 'revoked' WHERE id = %d", $this->grant ) );
		do_action( 'course_captain_policy_changed' );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 2, array( 'A' ) ), 'ccap_content_locked' );
		self::assertSame( 'evaluated', $sessions->submit( $learner, $session )['status'] );
	}

	/**
	 * Attempt limit and cooldown (§4.4, GM-210).
	 *
	 * @group GM-210
	 */
	public function test_GM_210_attempts_and_cooldown(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$limited  = $this->rule(
			array(
				'attempt_policy' => array(
					'max_attempts'     => 1,
					'cooldown_seconds' => 0,
				),
			)
		);
		$sessions->submit( $learner, $this->start( $limited ) );
		$this->assertRejected( fn() => $this->start( $limited ), 'ccap_exam_attempts_exhausted' );

		$cool = $this->rule(
			array(
				'attempt_policy' => array(
					'max_attempts'     => null,
					'cooldown_seconds' => 3600,
				),
			)
		);
		$sessions->submit( $learner, $this->start( $cool ) );
		$this->assertRejected( fn() => $this->start( $cool ), 'ccap_exam_attempts_exhausted' );
		$later = ExamSessions::current();
		$later->set_now( time() + 3601 );
		self::assertGreaterThan( 0, $later->start( $learner, $cool, null, wp_generate_uuid4() ) );
	}
}
