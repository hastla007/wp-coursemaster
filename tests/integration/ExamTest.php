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
use CourseCaptain\Learning\AnswerService;
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
		self::assertContains( $view['remaining_seconds'], array( 599, 600 ), 'real clock may cross a second boundary' );

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
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 5, array( 'A' ) ), 'ccap_exam_already_submitted' );
		$this->assertRejected( fn() => $sessions->flag( $learner, $session, 1, true ), 'ccap_exam_already_submitted' );
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
	 * @group GM-042
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

	/**
	 * GM-018: learning answers are stored once per idempotency key; no explanation in Free.
	 *
	 * @group R1-09
	 * @group GM-018
	 * @group GM-150
	 * @group GM-197
	 */
	public function test_GM_018_learning_answer_idempotent(): void {
		$learner  = Actor::user( $this->learner );
		$module   = $this->modules[0];
		$question = $this->pool[ $module ][0];
		$token    = QuestionPresenter::current()->deliver( $learner, $this->course, $module, $question, new Randomizer( '20300115' ) )['token'];
		$answers  = AnswerService::current();
		$key      = wp_generate_uuid4();

		$first = $answers->answer( $learner, $token, array( 'A' ), $key );
		self::assertTrue( $first['correct'] );
		self::assertFalse( $first['repeated'] );
		self::assertSame( 1, $first['attempts'] );
		self::assertSame( array( 'A' ), $first['correct_ids'] );
		self::assertStringNotContainsString( 'explanation', (string) wp_json_encode( $first ), 'E-20' );

		$again = $answers->answer( $learner, $token, array( 'A' ), $key );
		self::assertTrue( $again['repeated'], 'double submit counts once' );
		self::assertTrue( $again['correct'], 'stored result' );
		self::assertSame( 1, $again['attempts'] );
		// Same key with another answer is a conflict, never a second evaluation (R0-19 §2.1, GM-150, GM-061).
		$this->assertRejected( fn() => $answers->answer( $learner, $token, array( 'B' ), $key ), 'ccap_idempotency_conflict' );

		$wrong = $answers->answer( $learner, $token, array( 'B' ), wp_generate_uuid4() );
		self::assertFalse( $wrong['correct'] );
		self::assertSame( 2, $wrong['attempts'] );

		$db       = Database::current();
		$progress = (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_question_progress' ) . '` WHERE user_id = %d AND question_id = %d', $this->learner, $question ) );
		self::assertSame( array( '2', '1', '1', '0' ), array( $progress['attempts'], $progress['correct_count'], $progress['wrong_count'], $progress['streak'] ) );
		self::assertSame( '2', (string) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_attempt' ) . '` WHERE user_id = %d', $this->learner ) ) );
		self::assertSame( '2', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_attempt_answer' ) . '`' ) );

		$this->assertRejected( fn() => $answers->answer( $learner, $token, array( 'A' ), 'not-a-uuid' ), 'ccap_idempotency_key_required' );
		$this->assertRejected( fn() => $answers->answer( $learner, $token, array(), wp_generate_uuid4() ), 'ccap_invalid_answer' );
	}

	/**
	 * E-147 / E-20: result visibility follows the frozen feedback settings.
	 *
	 * @group R1-09
	 * @group GM-197
	 */
	public function test_E147_feedback_visibility(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$fail     = function ( int $rule ) use ( $sessions, $learner ): array {
			$session = $this->start( $rule );
			foreach ( array( 1, 2, 3, 4, 5 ) as $position ) {
				$sessions->save_answer( $learner, $session, $position, array( 1 === $position ? 'A' : 'B' ) );
			}
			return $sessions->submit( $learner, $session );
		};

		$open = $fail( $this->rule() );
		self::assertTrue( $open['released'] );
		self::assertFalse( $open['passed'] );
		self::assertSame( 1000, $open['points_milli'] );
		self::assertCount( 5, $open['questions'] );
		self::assertSame( array( true, false, false, false, false ), array_column( $open['questions'], 'correct' ), 'right/wrong always visible' );
		self::assertFalse( $open['solutions_visible'], 'attempts left and not passed' );
		self::assertStringNotContainsString( 'correct_ids', (string) wp_json_encode( $open ) );
		self::assertStringNotContainsString( 'explanation', (string) wp_json_encode( $open ) );

		$last = $fail(
			$this->rule(
				array(
					'attempt_policy' => array(
						'max_attempts'     => 1,
						'cooldown_seconds' => 0,
					),
				)
			)
		);
		self::assertTrue( $last['solutions_visible'], 'no attempt left' );
		self::assertSame( array( 'A' ), $last['questions'][0]['correct_ids'] );

		$hidden = $fail( $this->rule( array( 'feedback' => array( 'show_score' => false ) ) ) );
		self::assertArrayNotHasKey( 'points_milli', $hidden );
		self::assertArrayNotHasKey( 'points_milli', $hidden['questions'][0] );

		$never = $fail( $this->rule( array( 'feedback' => array( 'feedback_visibility' => 'never' ) ) ) );
		self::assertSame(
			array(
				'status'   => 'evaluated',
				'released' => false,
			),
			$never
		);
	}

	/**
	 * E-74: manual re-evaluation writes a new evaluation_no, audit and event; never the own attempt.
	 *
	 * @group R1-09
	 */
	public function test_E74_regrade(): void {
		add_filter(
			'course_captain_internal_subscribers',
			static fn( array $s ): array => $s + array( 'test' => array( 'exam.*' ) )
		);
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$admin    = Actor::user( $this->admin );
		$session  = $this->start( $this->rule() );
		foreach ( array( 1, 2, 3, 4, 5 ) as $position ) {
			$sessions->save_answer( $learner, $session, $position, array( $position <= 2 ? 'A' : 'B' ) );
		}
		self::assertFalse( $sessions->submit( $learner, $session )['passed'] );

		$this->assertRejected( fn() => $sessions->regrade( $learner, $session, 3, 1000, 'mine' ), 'ccap_forbidden' );
		$this->assertRejected( fn() => $sessions->regrade( $admin, $session, 3, 1000, ' ' ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $sessions->regrade( $admin, $session, 3, 5000, 'too much' ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $sessions->regrade( $admin, $session, 99, 0, 'unknown' ), 'ccap_validation_failed' );

		$out = $sessions->regrade( $admin, $session, 3, 1000, 'Question 3 had two right options' );
		self::assertSame( 2, $out['evaluation_no'] );
		self::assertSame( 3000, $out['result']['points_milli'] );
		self::assertTrue( $out['result']['passed'] );
		$row = self::row( $session );
		self::assertSame( '2', (string) $row['evaluation_no'] );
		self::assertSame( '1', (string) $row['passed'] );

		$out = $sessions->regrade( $admin, $session, 5, null, 'Faulty question', true );
		self::assertSame( 3, $out['evaluation_no'] );
		self::assertSame( 4000, $out['result']['max_points_milli'] );
		self::assertSame( 3000, $out['result']['points_milli'], 'override kept' );
		self::assertSame( 3, ExamSessions::current()->view( $learner, $session )['result']['evaluation_no'] );

		$db = Database::current();
		self::assertSame( '2', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'attempt_graded'" ) );
		$types = $db->wpdb()->get_col( 'SELECT event_type FROM `' . $db->table( 'ccap_outbox_event' ) . '` ORDER BY id' );
		self::assertSame( array( 'exam.failed', 'exam.reevaluated', 'exam.reevaluated' ), $types );

		$fresh = $this->start( $this->rule() );
		$this->assertRejected( fn() => $sessions->regrade( $admin, $fresh, 1, 0, 'running' ), 'ccap_exam_conflict' );
	}

	/**
	 * R1-20: offline buffer bounded by the late tolerance; older tab state never overwrites newer.
	 *
	 * @group R1-20
	 * @group GM-036
	 * @group GM-037
	 * @group GM-054
	 */
	public function test_R120_offline_buffer_and_tabs(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $this->start( $this->rule( array(), 60 ) );
		$t0       = (int) strtotime( self::row( $session )['started_at'] . ' UTC' );

		$sessions->set_now( $t0 + 10 );
		$first = $sessions->save_answer( $learner, $session, 2, array( 'A' ) );
		self::assertTrue( $first['applied'] );
		self::assertSame( gmdate( 'c', $t0 + 10 ), $first['saved_at'] );
		$stale = $sessions->save_answer( $learner, $session, 2, array( 'B' ), 5000 );
		self::assertFalse( $stale['applied'], 'older buffered state from another tab' );
		self::assertNull( $stale['saved_at'] );
		self::assertSame( array( 'A' ), $sessions->view( $learner, $session )['questions'][1]['answer'] );
		self::assertTrue( $sessions->save_answer( $learner, $session, 2, array( 'B' ) )['applied'], 'same or newer state wins' );

		$sessions->set_now( $t0 + 100 );
		self::assertTrue( $sessions->save_answer( $learner, $session, 1, array( 'A' ), 40000 )['applied'], 'answered offline at +60 s, inside deadline + grace' );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 3, array( 'A' ), 10000 ), 'ccap_exam_expired' );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 3, array( 'A' ), -50000 ), 'ccap_exam_expired' );
		$huge = $sessions->save_answer( $learner, $session, 4, array( 'A' ), PHP_INT_MAX );
		self::assertSame( gmdate( 'c', $t0 ), $huge['saved_at'], 'age bounded by tolerance and start time' );

		$sessions->set_now( $t0 + 60 + 121 );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 5, array( 'A' ), 120000 ), 'ccap_exam_expired' );
		self::assertSame( 'expired', self::row( $session )['end_reason'], 'evaluated on access after the tolerance' );
		$sessions->set_now( null );
	}

	/**
	 * R1-20: reload / device change / connection loss resume the same attempt with saved state.
	 *
	 * @group R1-20
	 */
	public function test_R120_reload_resume(): void {
		$open    = $this->question( $this->modules[0], QuestionTypes::OPEN_TEXT );
		$admin   = Actor::user( $this->admin );
		$rule    = ExamRules::current()->create(
			$admin,
			$this->course,
			'resume',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			600
		);
		$variant = ExamRules::current()->create_variant( $admin, $this->course, 3, array( $open, $this->pool[ $this->modules[0] ][0] ) );
		ExamRules::current()->set_blueprint( $admin, $rule, $this->modules[0], 2 );
		ExamRules::current()->publish( $admin, $rule );
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $this->start( $rule, $variant );
		$sessions->reveal( $learner, $session, 1 );
		$sessions->save_answer( $learner, $session, 1, array( 'known' => true ) );
		$sessions->save_answer( $learner, $session, 2, array( 'B' ) );
		$sessions->flag( $learner, $session, 2, true );

		self::assertSame( $session, $this->start( $rule, $variant ), 'new tab / device: same attempt' );
		$view = $sessions->view( $learner, $session );
		self::assertSame( 'Model', $view['questions'][0]['model_answer'] );
		self::assertSame( array( 'known' => true ), $view['questions'][0]['answer'] );
		self::assertSame( array( 'B' ), $view['questions'][1]['answer'] );
		self::assertTrue( $view['questions'][1]['flagged'] );
		self::assertArrayNotHasKey( 'model_answer', $view['questions'][1] );
		self::assertNotNull( $view['deadline_at'] );
		self::assertLessThanOrEqual( 2, abs( strtotime( $view['server_now'] ) - time() ), 'server time is authoritative' );
		self::assertStringNotContainsString( '"correct"', (string) wp_json_encode( $view['questions'] ) );
	}

	/**
	 * R1-20: replay of start/submit, double submission, manipulation, foreign attempts.
	 *
	 * @group R1-20
	 */
	public function test_R120_replay_and_manipulation(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$rule     = $this->rule();
		$key      = wp_generate_uuid4();
		$session  = $sessions->start( $learner, $rule, null, $key, new Randomizer( '1' ) );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 0, array( 'A' ) ), 'ccap_not_found' );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 99, array( 'A' ) ), 'ccap_not_found' );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 1, array( 'A', 'B' ) ), 'ccap_exam_invalid_answer' );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 1, array( 'A"; DROP' ) ), 'ccap_exam_invalid_answer' );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 1, array( 'is_correct' => 1 ) ), 'ccap_exam_invalid_answer' );
		$this->assertRejected( fn() => $sessions->reveal( $learner, $session, 1 ), 'ccap_invalid_param' );

		$stranger = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertRejected( fn() => $sessions->view( $stranger, $session ), 'ccap_not_found' );
		$this->assertRejected( fn() => $sessions->save_answer( $stranger, $session, 1, array( 'A' ) ), 'ccap_not_found' );
		$this->assertRejected( fn() => $sessions->submit( $stranger, $session ), 'ccap_not_found' );

		$result = $sessions->submit( $learner, $session );
		self::assertSame( $result, $sessions->submit( $learner, $session ), 'double submission: one result' );
		self::assertSame( $session, $sessions->start( $learner, $rule, null, $key, new Randomizer( '2' ) ), 'replayed start key: no new attempt' );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE user_id = %d', $this->learner ) ) );
		$this->assertRejected( fn() => $sessions->save_answer( $learner, $session, 1, array( 'A' ) ), 'ccap_exam_already_submitted' );
		$this->assertRejected( fn() => $sessions->cancel( $learner, $session ), 'ccap_exam_already_submitted' );
		self::assertSame( '1', (string) self::row( $session )['evaluation_no'] );

		$other = $this->start( $rule );
		$sessions->cancel( $learner, $other );
		$this->assertRejected( fn() => $sessions->submit( $learner, $other ), 'ccap_exam_aborted' );
		$this->assertRejected( fn() => $sessions->flag( $learner, $other, 1, true ), 'ccap_exam_aborted' );
		self::assertSame( 'cancelled', $sessions->cancel( $learner, $other ), 'repeated abort is idempotent' );
	}

	/**
	 * R1-20: start limit 40/h (only new attempts count), ccap_rate_limited with retry_after.
	 *
	 * @group R1-20
	 * @group GM-046
	 */
	public function test_R120_start_rate_limit(): void {
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$now      = time();
		$sessions->set_now( $now );
		$start   = fn( int $rule ): int => $sessions->start( $learner, $rule, null, wp_generate_uuid4(), new Randomizer( '7' ) );
		$rule    = $this->rule();
		$running = $start( $rule );
		$db      = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_rate_limit' ) . '` SET hits = %d WHERE bucket_hash = %s', ExamSessions::START_LIMIT_HOUR, hash( 'sha256', 'exam_start:' . $this->learner ) ) );
		self::assertSame( $running, $start( $rule ), 'resume never counts' );
		try {
			$start( $this->rule() );
			self::fail( 'start limit' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_rate_limited', $e->error_code );
			self::assertSame( 429, $e->status );
			self::assertSame( 'exam_start', $e->data['bucket'] );
			self::assertSame( 3600 - $now % 3600, $e->data['retry_after'] );
		}
		$sessions->set_now( $now - $now % 3600 + 3600 );
		self::assertGreaterThan( 0, $start( $this->rule() ), 'next window' );
		$sessions->set_now( null );
	}

	/**
	 * R1-20 / R0-19 R-18…R-24: exam REST routes, no-store, catalog codes.
	 *
	 * @group R1-20
	 * @group GM-038
	 */
	public function test_R120_rest(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$call = static function ( string $method, string $route, array $body = array() ): \WP_REST_Response {
			$request = new \WP_REST_Request( $method, '/course-captain/v1' . $route );
			foreach ( $body as $key => $value ) {
				$request->set_param( $key, $value );
			}
			return rest_do_request( $request );
		};
		$rule = $this->rule();
		wp_set_current_user( 0 );
		self::assertSame( 401, $call( 'POST', '/courses/' . $this->course . '/exam-sessions', array( 'rule' => $rule ) )->get_status() );
		wp_set_current_user( $this->learner );
		self::assertSame( 400, $call( 'POST', '/courses/' . $this->course . '/exam-sessions', array( 'rule' => $rule ) )->get_status(), 'request_key required' );
		$other = $this->post( 'ccap_course' );
		self::assertSame(
			404,
			$call(
				'POST',
				'/courses/' . $other . '/exam-sessions',
				array(
					'rule'        => $rule,
					'request_key' => wp_generate_uuid4(),
				)
			)->get_status(),
			'rule of another course'
		);

		$started = $call(
			'POST',
			'/courses/' . $this->course . '/exam-sessions',
			array(
				'rule'        => $rule,
				'request_key' => wp_generate_uuid4(),
			)
		);
		self::assertSame( 201, $started->get_status() );
		self::assertSame( 'private, no-store', $started->get_headers()['Cache-Control'] );
		$session = (int) $started->get_data()['session']['session_id'];
		self::assertSame(
			200,
			$call(
				'POST',
				'/courses/' . $this->course . '/exam-sessions',
				array(
					'rule'        => $rule,
					'request_key' => wp_generate_uuid4(),
				)
			)->get_status(),
			'reload resumes'
		);

		$saved = $call(
			'PUT',
			'/exam-sessions/' . $session . '/answers/1',
			array(
				'answer' => array( 'A' ),
				'age_ms' => 0,
			)
		);
		self::assertSame( 200, $saved->get_status() );
		self::assertTrue( $saved->get_data()['applied'] );
		self::assertSame( array( 'flagged' => true ), $call( 'PUT', '/exam-sessions/' . $session . '/answers/2/flag', array( 'flagged' => true ) )->get_data() );
		self::assertSame( 422, $call( 'PUT', '/exam-sessions/' . $session . '/answers/1', array( 'answer' => array( 'Q' ) ) )->get_status() );
		self::assertSame( array( 'A' ), $call( 'GET', '/exam-sessions/' . $session )->get_data()['questions'][0]['answer'] );

		$submit = $call( 'POST', '/exam-sessions/' . $session . '/submit' );
		self::assertSame( 'evaluated', $submit->get_data()['status'] );
		self::assertSame( $submit->get_data(), $call( 'POST', '/exam-sessions/' . $session . '/submit' )->get_data() );
		$late = $call( 'POST', '/exam-sessions/' . $session . '/abort' );
		self::assertSame( 409, $late->get_status() );
		self::assertSame( 'ccap_exam_already_submitted', $late->as_error()->get_error_code() );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertSame( 404, $call( 'GET', '/exam-sessions/' . $session )->get_status() );
	}

	/**
	 * GM-197 (Free part, E-20/E-147/E-269): a question with explanation texts
	 * answers in the learning mode with `correct` and `correct_ids`, never an
	 * explanation; the stored texts stay unchanged and the package export
	 * carries them; a passed exam with attempts left shows the solutions, no
	 * explanation.
	 *
	 * @group R1-18
	 * @group GM-197
	 */
	public function test_GM_197_explanations_stored_exported_never_delivered(): void {
		$repo     = QuestionRepository::current();
		$admin    = Actor::user( $this->admin );
		$learner  = Actor::user( $this->learner );
		$module   = $this->modules[0];
		$question = $repo->create(
			$admin,
			array(
				'question_type'      => QuestionTypes::SINGLE,
				'question_text'      => 'Q-A1-001 like',
				'feedback_correct'   => 'Explanation right GM197',
				'feedback_incorrect' => 'Explanation wrong GM197',
				'answers'            => array(
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
		$repo->publish( $admin, $question );
		$repo->add_to_pool( $admin, $module, $question );
		$token  = QuestionPresenter::current()->deliver( $learner, $this->course, $module, $question, new Randomizer( '20300115' ) )['token'];
		$answer = AnswerService::current()->answer( $learner, $token, array( 'B' ), wp_generate_uuid4() );
		self::assertFalse( $answer['correct'] );
		self::assertSame( array( 'A' ), $answer['correct_ids'] );
		$json = (string) wp_json_encode( $answer );
		self::assertStringNotContainsString( 'explanation', $json );
		self::assertStringNotContainsString( 'GM197', $json, 'no explanation text in Free' );

		$db       = Database::current();
		$revision = (array) $db->row( $db->prepare( 'SELECT feedback_correct, feedback_incorrect FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE question_id = %d ORDER BY id DESC LIMIT 1', $question ) );
		self::assertSame(
			array(
				'feedback_correct'   => 'Explanation right GM197',
				'feedback_incorrect' => 'Explanation wrong GM197',
			),
			$revision,
			'stored text unchanged'
		);
		$records  = \CourseCaptain\Import\PackageExporter::records( $admin, array( $this->course ) );
		$exported = array_values( array_filter( $records['questions'], static fn( array $r ): bool => 'Q-A1-001 like' === $r['revision']['text_html'] ) );
		self::assertCount( 1, $exported );
		self::assertSame( 'Explanation right GM197', $exported[0]['revision']['feedback_correct'], 'export keeps the text' );
		self::assertSame( 'Explanation wrong GM197', $exported[0]['revision']['feedback_incorrect'] );

		$sessions = ExamSessions::current();
		$session  = $this->start( $this->rule() );
		foreach ( array( 1, 2, 3, 4, 5 ) as $position ) {
			$sessions->save_answer( $learner, $session, $position, array( 'A' ) );
		}
		$passed = $sessions->submit( $learner, $session );
		self::assertTrue( $passed['passed'] );
		self::assertTrue( $passed['solutions_visible'], 'passed (attempts left) → correct answer shown' );
		self::assertSame( array( 'A' ), $passed['questions'][0]['correct_ids'] );
		self::assertStringNotContainsString( 'explanation', (string) wp_json_encode( $passed ) );
		self::assertStringNotContainsString( 'GM197', (string) wp_json_encode( $passed ) );
	}

	/**
	 * GM-038: offline answers over REST after the deadline (setup as GM-037:
	 * time limit 60 s, grace 5 s, late tolerance 120 s, deadline = now − 40 s).
	 * Without `age_ms` the catalog code `ccap_exam_expired` comes back and
	 * nothing is stored; with `age_ms` inside deadline + grace the buffered
	 * answer is applied (200, `applied=true`); the submit evaluates the
	 * buffered answers (positions 1, 3, 4 right). Status 410 per the R0-19
	 * catalog (R0-05 names 422, see gm-coverage-exceptions).
	 *
	 * @group R1-18
	 * @group R1-20
	 * @group GM-038
	 */
	public function test_GM_038_offline_answers_over_rest(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$call    = static function ( string $method, string $route, array $body = array() ): \WP_REST_Response {
			$request = new \WP_REST_Request( $method, '/course-captain/v1' . $route );
			foreach ( $body as $key => $value ) {
				$request->set_param( $key, $value );
			}
			$response = rest_do_request( $request );
			return apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		};
		$session = $this->start( $this->rule( array(), 60 ) );
		$db      = Database::current();
		$now     = time();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . '` SET started_at = %s, deadline_at = %s WHERE id = %d', gmdate( 'Y-m-d H:i:s', $now - 100 ), gmdate( 'Y-m-d H:i:s', $now - 40 ), $session ) );
		wp_set_current_user( $this->learner );

		$late = $call( 'PUT', '/exam-sessions/' . $session . '/answers/2', array( 'answer' => array( 'A' ) ) );
		self::assertSame( 410, $late->get_status() );
		self::assertSame( 'ccap_exam_expired', $late->get_data()['code'], 'catalog code, not the text' );
		self::assertSame( 410, $late->get_data()['status'] );
		$view = $call( 'GET', '/exam-sessions/' . $session );
		self::assertSame( 200, $view->get_status(), 'still running inside the late tolerance' );
		self::assertSame( 'in_progress', self::row( $session )['status'] );
		self::assertEmpty( $view->get_data()['questions'][1]['answer'], 'nothing stored' );

		foreach ( array(
			1 => 60000,
			3 => 50000,
			4 => 45000,
		) as $position => $age ) {
			$saved = $call(
				'PUT',
				'/exam-sessions/' . $session . '/answers/' . $position,
				array(
					'answer' => array( 'A' ),
					'age_ms' => $age,
				)
			);
			self::assertSame( 200, $saved->get_status(), 'position ' . $position );
			self::assertTrue( $saved->get_data()['applied'] );
		}
		$stored = $call( 'GET', '/exam-sessions/' . $session )->get_data()['questions'];
		self::assertSame( array( 'A' ), $stored[0]['answer'], 'REST returns the stored state' );
		self::assertSame( array( 'A' ), $stored[3]['answer'] );

		$submit = $call( 'POST', '/exam-sessions/' . $session . '/submit' );
		self::assertSame( 200, $submit->get_status() );
		self::assertSame( 'evaluated', $submit->get_data()['status'] );
		self::assertSame( array( true, false, true, true, false ), array_column( $submit->get_data()['questions'], 'correct' ), 'positions 1, 3, 4 right' );
	}

	/**
	 * REV-021 / RV-01: section labels are frozen at the start; renaming a module changes nothing in the result.
	 *
	 * @group REV-021
	 */
	public function test_REV_021_section_labels_frozen(): void {
		wp_update_post(
			array(
				'ID'         => $this->modules[0],
				'post_title' => 'Topic before',
			)
		);
		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learner );
		$session  = $this->start( $this->rule() );
		wp_update_post(
			array(
				'ID'         => $this->modules[0],
				'post_title' => 'Topic after',
			)
		);
		$sessions->submit( $learner, $session );
		$result = json_decode( (string) self::row( $session )['result'], true );
		self::assertSame( 'Topic before', $result['sections'][ $this->modules[0] ]['title'] );
		$snapshot = json_decode( (string) self::row( $session )['snapshot'], true );
		self::assertSame( 'Topic before', $snapshot['section_labels'][ $this->modules[0] ] );
	}
}
