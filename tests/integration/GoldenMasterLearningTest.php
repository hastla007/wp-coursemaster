<?php
/**
 * R1-18 follow-up (group g1): GM-006, GM-010, GM-074, GM-078, GM-079 on `cc-fixture-neutral-v1`.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Cache\CacheStore;
use CourseCaptain\Cache\StructureCache;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Progress\CourseReport;
use CourseCaptain\Progress\ProgressReset;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

/**
 * Each test loads the fixture itself (idempotent, D-03); on-demand steps
 * run inside the test transaction.
 *
 * @group R1-18
 */
final class GoldenMasterLearningTest extends SchemaTestCase {

	/**
	 * Loaded fixture.
	 *
	 * @var array{users: array<string, int>, courses: array<string, int>, modules: array<string, int>, rules: array<string, int>, variants: array<string, int>, media: array<string, int>, pages: array<string, int>, groups: array<string, int>}
	 */
	private array $fx;

	/**
	 * Load the fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$dir = get_temp_dir() . 'ccap-fx-' . wp_generate_password( 6, false );
		wp_mkdir_p( $dir );
		$this->fx = NeutralFixture::load( $dir );
		PolicyService::flush();
	}

	/**
	 * Reset clocks.
	 */
	public function tear_down(): void {
		SmartTrainer::set_now( null );
		parent::tear_down();
	}

	/**
	 * GM-006: courses and modules in ≤ 2 queries, cached with the same
	 * fields and order; reloaded after `invalidate()` and after a cache flush.
	 *
	 * @group GM-006
	 */
	public function test_GM_006_structure_cache(): void {
		global $wpdb;
		CacheStore::content_gen();
		StructureCache::invalidate();
		$before = $wpdb->num_queries;
		$first  = StructureCache::all();
		self::assertLessThanOrEqual( 2, $wpdb->num_queries - $before, '≤ 2 queries for all courses and modules' );
		$before = $wpdb->num_queries;
		self::assertSame( $first, StructureCache::all(), 'cache hit: same fields and order' );
		self::assertSame( 0, $wpdb->num_queries - $before, 'hit without query' );
		self::assertSame( StructureCache::load(), $first, 'cached value = fresh load' );

		$by_id = array_column( $first, null, 'course_id' );
		self::assertSame( 'publish', $by_id[ $this->fx['courses']['C-A'] ]['status'] );
		self::assertSame( 'draft', $by_id[ $this->fx['courses']['C-C'] ]['status'] );
		self::assertSame( 'ccap_archived', $by_id[ $this->fx['courses']['C-D'] ]['status'] );
		self::assertSame(
			array( 'Modul M-A1', 'Modul M-A2', 'Modul M-A3', 'Modul LM-A1', 'Modul LM-A2', 'Modul LM-A3' ),
			array_column( $by_id[ $this->fx['courses']['C-A'] ]['modules'], 'title' ),
			'module order of the structure'
		);
		self::assertSame( array( $this->fx['modules']['M-B1'], $this->fx['modules']['M-B2'] ), array_column( $by_id[ $this->fx['courses']['C-B'] ]['modules'], 'module_id' ) );
		self::assertCount( 1, $by_id[ $this->fx['courses']['C-C'] ]['modules'], 'M-C1' );
		$modules = 0;
		foreach ( $first as $course ) {
			$modules += count( $course['modules'] );
		}
		self::assertSame( (int) Database::current()->scalar( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_course_module' ) . '`' ), $modules );

		// A change without hooks stays invisible until invalidate().
		$module = $this->fx['modules']['M-A2'];
		$wpdb->update( $wpdb->posts, array( 'post_title' => 'Modul M-A2 neu' ), array( 'ID' => $module ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bypass hooks on purpose.
		clean_post_cache( $module );
		self::assertSame( $first, StructureCache::all(), 'stale until invalidated' );
		StructureCache::invalidate();
		$fresh = array_column( StructureCache::all(), null, 'course_id' );
		self::assertSame( 'Modul M-A2 neu', $fresh[ $this->fx['courses']['C-A'] ]['modules'][1]['title'] );

		// Hooks: saving a module and changing the structure invalidate.
		wp_update_post(
			array(
				'ID'         => $module,
				'post_title' => 'Modul M-A2 gespeichert',
			)
		);
		self::assertSame( 'Modul M-A2 gespeichert', array_column( StructureCache::all(), null, 'course_id' )[ $this->fx['courses']['C-A'] ]['modules'][1]['title'] );
		CourseStructure::current()->remove_module( Actor::user( $this->fx['users']['admin'] ), $this->fx['courses']['C-B'], $this->fx['modules']['M-B2'] );
		self::assertSame( array( $this->fx['modules']['M-B1'] ), array_column( array_column( StructureCache::all(), null, 'course_id' )[ $this->fx['courses']['C-B'] ]['modules'], 'module_id' ) );

		// Cache flush: reloaded from the database.
		$cached = StructureCache::all();
		wp_cache_flush();
		self::assertSame( $cached, StructureCache::all() );
		self::assertSame( StructureCache::load(), StructureCache::all() );
	}

	/**
	 * GM-010 (finding F-3 fixed): a lesson quiz is evaluated in the lesson
	 * context and completes the lesson through the quiz provider.
	 *
	 * @group GM-010
	 */
	public function test_GM_010_lesson_quiz_completes_lesson(): void {
		$lesson   = $this->lesson_quiz();
		$lea      = Actor::user( $this->fx['users']['lea'] );
		$course   = $this->fx['courses']['C-A'];
		$progress = ActivityProgress::current();
		$db       = Database::current();
		$keys     = array( 'A1-010', 'A1-011', 'A1-012' );
		foreach ( $keys as $i => $key ) {
			$question = NeutralFixture::question( $key );
			$delivery = QuestionPresenter::current()->deliver_inline( $lea, $course, $lesson, $question, new Randomizer( '20300115' ) );
			$result   = AnswerService::current()->answer( $lea, $delivery['token'], array( 0 === $i ? 'B' : 'A' ), wp_generate_uuid4() );
			self::assertSame( 0 !== $i, $result['correct'] );
			$attempt = (array) $db->row( $db->prepare( 'SELECT context, context_ref FROM `' . $db->table( 'ccap_attempt' ) . '` WHERE user_id = %d AND question_id = %d ORDER BY id DESC LIMIT 1', $lea->user_id, $question ) );
			self::assertSame( 'lesson', $attempt['context'], 'attempt with context=lesson' );
			self::assertSame( 'lesson:' . $lesson, $attempt['context_ref'] );
			$state = (array) $progress->state( $lea->user_id, $lesson );
			$data  = (array) json_decode( (string) $state['data'], true );
			self::assertSame( $i + 1, $data['answered'] );
			self::assertSame( 3, $data['total'] );
			self::assertSame( 2 === $i ? 'completed' : 'started', $state['status'] );
		}
		$state = (array) $progress->state( $lea->user_id, $lesson );
		self::assertSame( 'attempted', $state['reason_code'] );
		self::assertSame( 2, (int) json_decode( (string) $state['data'], true )['correct'] );

		// Monotonic: answering again keeps the lesson completed; the same key counts once.
		$delivery = QuestionPresenter::current()->deliver_inline( $lea, $course, $lesson, NeutralFixture::question( 'A1-010' ), new Randomizer( '1' ) );
		$key      = wp_generate_uuid4();
		AnswerService::current()->answer( $lea, $delivery['token'], array( 'A' ), $key );
		self::assertTrue( AnswerService::current()->answer( $lea, $delivery['token'], array( 'A' ), $key )['repeated'] );
		$state = (array) $progress->state( $lea->user_id, $lesson );
		self::assertSame( 'completed', $state['status'] );
		self::assertSame( 3, (int) json_decode( (string) $state['data'], true )['correct'], 'all correct once now' );

		// A learning answer to the same question outside the lesson does not feed the quiz.
		$other = Actor::user( $this->fx['users']['ole'] );
		$token = QuestionPresenter::current()->deliver( $other, $course, $this->fx['modules']['M-A1'], NeutralFixture::question( 'A1-010' ) )['token'];
		AnswerService::current()->answer( $other, $token, array( 'A' ), wp_generate_uuid4() );
		self::assertNull( $progress->state( $other->user_id, $lesson ) );
	}

	/**
	 * GM-074: targeted practice of one question (web path): 422 for missing
	 * or inconsistent parameters, 404 for another course, the answer counts
	 * in the same learning state, partial access 403 (`trial`), drip 403 (`drip`).
	 *
	 * @group GM-074
	 */
	public function test_GM_074_targeted_practice(): void {
		$trainer = SmartTrainer::current();
		$lea     = Actor::user( $this->fx['users']['lea'] );
		$course  = $this->fx['courses']['C-A'];
		$q5      = NeutralFixture::question( 'A1-005' );

		$practice = $trainer->practice( $lea, $course, array( 'question' => $q5 ) );
		self::assertSame( $q5, $practice['question_id'] );
		self::assertSame( $this->fx['modules']['M-A1'], $practice['module_id'] );
		self::assertSame( 'new', $practice['progress']['status'] );
		self::assertStringNotContainsString( 'correct', (string) wp_json_encode( $practice['question'] ) );
		$result = AnswerService::current()->answer( $lea, $practice['token'], array( 'A' ), wp_generate_uuid4() );
		self::assertTrue( $result['correct'] );
		$db  = Database::current();
		$row = (array) $db->row( $db->prepare( 'SELECT attempts, streak, learn_status FROM `' . $db->table( 'ccap_question_progress' ) . '` WHERE user_id = %d AND question_id = %d', $lea->user_id, $q5 ) );
		self::assertSame( array( '1', '1', 'learning' ), array( (string) $row['attempts'], (string) $row['streak'], (string) $row['learn_status'] ), 'same learning state' );
		self::assertSame( 1, $trainer->practice( $lea, $course, array( 'question' => (string) $q5 ) )['progress']['attempts'] );
		self::assertSame( 1, $trainer->summary( $lea, $course )['learning'] );

		foreach ( array(
			'missing'      => array(),
			'not a number' => array( 'question' => 'abc' ),
			'zero'         => array( 'question' => 0 ),
			'bad module'   => array(
				'question' => $q5,
				'module'   => 'x',
			),
			'other module' => array(
				'question' => $q5,
				'module'   => $this->fx['modules']['M-A2'],
			),
		) as $label => $params ) {
			$e = $this->rejected( fn() => $trainer->practice( $lea, $course, $params ), 'ccap_invalid_param' );
			self::assertSame( 422, $e->status, $label );
		}
		$e = $this->rejected( fn() => $trainer->practice( $lea, $course, array( 'question' => NeutralFixture::question( 'B1-001' ) ) ), 'ccap_not_found' );
		self::assertSame( 404, $e->status, 'question of another course' );

		$free = Actor::user( $this->fx['users']['free'] );
		self::assertSame( NeutralFixture::question( 'A1-002' ), $trainer->practice( $free, $course, array( 'question' => NeutralFixture::question( 'A1-002' ) ) )['question_id'], 'within the free scope (AR-01)' );
		$e = $this->rejected( fn() => $trainer->practice( $free, $course, array( 'question' => $q5 ) ), 'ccap_content_locked' );
		self::assertSame( array( 403, 'trial' ), array( $e->status, $e->getMessage() ), 'partial access' );

		NeutralFixture::rule( 'AR-04', $this->fx );
		PolicyService::flush();
		$e = $this->rejected( fn() => $trainer->practice( $lea, $course, array( 'question' => NeutralFixture::question( 'A2-002' ) ) ), 'ccap_content_locked' );
		self::assertSame( array( 403, 'drip' ), array( $e->status, $e->getMessage() ), 'scheduled release' );

		// REST: same codes through `GET /courses/{course}/practice`.
		wp_set_current_user( $lea->user_id );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/courses/' . $course . '/practice' ) );
		self::assertSame( 422, $response->get_status() );
		$request = new \WP_REST_Request( 'GET', '/course-captain/v1/courses/' . $course . '/practice' );
		$request->set_param( 'question', (string) $q5 );
		$response = rest_do_request( $request );
		self::assertSame( 200, $response->get_status() );
		self::assertSame( $q5, $response->get_data()['question_id'] );
		$request->set_param( 'question', (string) NeutralFixture::question( 'B1-001' ) );
		self::assertSame( 404, rest_do_request( $request )->get_status() );
		wp_set_current_user( 0 );
	}

	/**
	 * GM-078: reset of the course progress of U-L1 after learning (GM-067
	 * stages), a completed lesson and an evaluated exam: 0 progress rows for
	 * C-A, exam history and attempts kept, audit entry and reset journal.
	 *
	 * @group GM-078
	 */
	public function test_GM_078_progress_reset(): void {
		$db     = Database::current();
		$lea    = Actor::user( $this->fx['users']['lea'] );
		$course = $this->fx['courses']['C-A'];
		$t0     = (int) strtotime( NeutralFixture::T0 . ' UTC' );
		// Stage `secure` for one question (GM-066/GM-067), `learning` for another.
		foreach ( array( 0, 400, 800 ) as $at ) {
			$this->learn( 'lea', 'A1-001', true, $t0 + $at );
		}
		$this->learn( 'lea', 'A1-002', true, $t0 + 1200 );
		SmartTrainer::set_now( null );
		$lesson = (int) NeutralFixture::object( 'lesson', 'cc-fx-l-a-01' );
		ActivityProgress::current()->interact( $lea, $course, $lesson, 'confirm' );
		$session = ExamSessions::current()->start( $lea, $this->fx['rules']['ER-A'], $this->fx['variants']['EP-A-1'], wp_generate_uuid4(), new Randomizer( '20300115' ) );
		ExamSessions::current()->submit( $lea, $session );
		// Progress of another course and of another learner stays.
		$this->learn( 'ole', 'A1-001', true, $t0 );
		SmartTrainer::set_now( null );

		$count = function ( string $table, int $user ) use ( $db, $course ): int {
			$questions = '(SELECT p.question_id FROM `' . $db->table( 'ccap_module_question' ) . '` p JOIN `' . $db->table( 'ccap_course_module' ) . '` cm ON cm.module_id = p.module_id WHERE cm.course_id = ' . $course . ')';
			$where     = 'ccap_question_progress' === $table ? 'question_id IN ' . $questions : 'course_id = ' . $course;
			return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '` WHERE user_id = %d AND ', $user ) . $where );
		};
		self::assertSame( 1, $count( 'ccap_course_progress', $lea->user_id ) );
		self::assertSame( 1, $count( 'ccap_activity_progress', $lea->user_id ) );
		self::assertSame( 2, $count( 'ccap_question_progress', $lea->user_id ) );
		$sessions = (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE user_id = %d', $lea->user_id ) );
		$attempts = (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_attempt' ) . '` WHERE user_id = %d', $lea->user_id ) );
		self::assertSame( 1, $sessions );

		// Staff needs the right and a reason; another learner may not reset.
		$admin = Actor::user( $this->fx['users']['admin'] );
		$this->rejected( fn() => ProgressReset::current()->reset_course( $admin, $lea->user_id, $course ), 'ccap_invalid_param' );
		$e = $this->rejected( fn() => ProgressReset::current()->reset_course( Actor::user( $this->fx['users']['tutor'] ), $lea->user_id, $course, 'Support' ), 'ccap_forbidden' );
		self::assertSame( 403, $e->status );
		$this->rejected( fn() => ProgressReset::current()->reset_course( $admin, $lea->user_id, 999999, 'Support' ), 'ccap_not_found' );

		$result = ProgressReset::current()->reset_course( $admin, $lea->user_id, $course, 'Support case' );
		self::assertSame(
			array(
				'ccap_course_progress'   => 1,
				'ccap_activity_progress' => 1,
				'ccap_question_progress' => 2,
			),
			$result['rows']
		);
		foreach ( ProgressReset::TABLES as $table ) {
			self::assertSame( 0, $count( $table, $lea->user_id ), 'progress of C-A = 0 rows: ' . $table );
		}
		self::assertSame( 1, $count( 'ccap_question_progress', $this->fx['users']['ole'] ), 'other learner untouched' );
		self::assertSame( $sessions, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE user_id = %d', $lea->user_id ) ), 'exam history kept' );
		self::assertSame( $attempts, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_attempt' ) . '` WHERE user_id = %d', $lea->user_id ) ), 'attempts kept' );

		$audit = (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE id = %d', $result['audit_id'] ) );
		self::assertSame( 'progress_reset', $audit['action'] );
		self::assertSame( (string) $lea->user_id, (string) $audit['subject_user_id'] );
		self::assertSame( (string) $admin->user_id, (string) $audit['actor_user_id'] );
		self::assertSame( 'Support case', $audit['reason'] );
		self::assertSame( 'course', json_decode( (string) $audit['context'], true )['scope'] );
		self::assertSame( 2, json_decode( (string) $audit['context'], true )['rows']['ccap_question_progress'] );
		$journal = (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_reset_journal' ) . '` WHERE id = %d', $result['journal_id'] ) );
		self::assertSame( (string) $result['audit_id'], (string) $journal['audit_id'] );
		self::assertCount( 2, json_decode( (string) $journal['prior_state'], true )['ccap_question_progress'], 'prior state for the undo (E-138)' );

		// Self-reset without reason.
		$this->learn( 'lea', 'A1-003', false, $t0 + 2000 );
		SmartTrainer::set_now( null );
		$self = ProgressReset::current()->reset_course( $lea, $lea->user_id, $course );
		self::assertSame( 1, $self['rows']['ccap_question_progress'] );
		self::assertNull( $db->scalar( $db->prepare( 'SELECT reason FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE id = %d', $self['audit_id'] ) ) );
	}

	/**
	 * GM-079: progress report of C-A with U-L1, U-L2, U-L3: 3 rows with the
	 * stage counts of GM-075; U-TUT only for C-A; learners and others 403.
	 *
	 * @group GM-079
	 */
	public function test_GM_079_course_report(): void {
		$t0 = (int) strtotime( NeutralFixture::T0 . ' UTC' );
		$this->learn( 'lea', 'A1-001', true, $t0 );
		$this->learn( 'lea', 'A1-002', false, $t0 + 400 );
		$this->learn( 'ole', 'A1-001', true, $t0 + 800 );
		$this->learn( 'free', 'A1-001', true, $t0 + 1200 );
		$this->learn( 'free', 'A1-002', true, $t0 + 1600 );
		ActivityProgress::current()->interact( Actor::user( $this->fx['users']['ole'] ), $this->fx['courses']['C-A'], (int) NeutralFixture::object( 'lesson', 'cc-fx-l-a-01' ), 'confirm' );
		SmartTrainer::set_now( $t0 + 2000 );

		$course = $this->fx['courses']['C-A'];
		$rows   = CourseReport::current()->rows( Actor::user( $this->fx['users']['admin'] ), $course );
		$users  = array( $this->fx['users']['lea'], $this->fx['users']['ole'], $this->fx['users']['free'] );
		sort( $users );
		self::assertSame( $users, array_column( $rows, 'user_id' ), '3 rows: U-L1, U-L2, U-L3' );
		$by_user = array_column( $rows, null, 'user_id' );
		foreach ( $rows as $row ) {
			$expected = SmartTrainer::current()->summary( Actor::user( $row['user_id'] ), $course );
			unset( $expected['topics'] );
			self::assertSame( $expected, $row['stages'], 'stage counts of GM-075' );
			$stages = $row['stages'];
			self::assertSame( $stages['total'], $stages['new'] + $stages['unsure'] + $stages['learning'] + $stages['secure'] + $stages['mastered'] );
			self::assertArrayNotHasKey( 'user_email', $row );
		}
		$lea = $by_user[ $this->fx['users']['lea'] ]['stages'];
		self::assertSame( array( 50, 48, 1, 1 ), array( $lea['total'], $lea['new'], $lea['unsure'], $lea['learning'] ) );
		self::assertSame( 9, $by_user[ $this->fx['users']['free'] ]['stages']['total'], 'U-L3: free scope only' );
		self::assertSame( 2, $by_user[ $this->fx['users']['free'] ]['stages']['learning'] );
		self::assertSame( 'in_progress', $by_user[ $this->fx['users']['ole'] ]['progress']['status'] );
		self::assertGreaterThan( 0, $by_user[ $this->fx['users']['ole'] ]['progress']['completion_percent'] );

		$tutor = Actor::user( $this->fx['users']['tutor'] );
		self::assertSame( array_column( $rows, 'user_id' ), array_column( CourseReport::current()->rows( $tutor, $course ), 'user_id' ), 'U-TUT for C-A' );
		$e = $this->rejected( fn() => CourseReport::current()->rows( $tutor, $this->fx['courses']['C-B'] ), 'ccap_forbidden' );
		self::assertSame( 403, $e->status, 'U-TUT not for C-B' );
		$this->rejected( fn() => CourseReport::current()->rows( Actor::user( $this->fx['users']['lea'] ), $course ), 'ccap_forbidden' );
		$this->rejected( fn() => CourseReport::current()->rows( Actor::user( $this->fx['users']['admin'] ), 999999 ), 'ccap_not_found' );

		// REST `GET /reports/courses/{course}` (RG-15).
		wp_set_current_user( $tutor->user_id );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/reports/courses/' . $course ) );
		self::assertSame( 200, $response->get_status() );
		self::assertCount( 3, $response->get_data()['learners'] );
		self::assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/reports/courses/' . $this->fx['courses']['C-B'] ) )->get_status() );
		wp_set_current_user( 0 );
	}

	/**
	 * Learning answer of a fixture user at a point in time (module pool).
	 *
	 * @param string $user    Login key.
	 * @param string $key     Question key, e.g. `A1-001`.
	 * @param bool   $correct Correct answer.
	 * @param int    $at      Unix time.
	 * @return void
	 */
	private function learn( string $user, string $key, bool $correct, int $at ): void {
		SmartTrainer::set_now( $at );
		$actor    = Actor::user( $this->fx['users'][ $user ] );
		$module   = $this->fx['modules'][ 'M-' . substr( $key, 0, 2 ) ];
		$question = NeutralFixture::question( $key );
		$token    = QuestionPresenter::current()->deliver( $actor, $this->fx['courses']['C-A'], $module, $question, new Randomizer( '20300115' ) )['token'];
		AnswerService::current()->answer( $actor, $token, array( $correct ? 'A' : 'B' ), wp_generate_uuid4() );
	}

	/**
	 * L-A-05 quiz part (R0-05 §2.2: 3 quiz questions Q-A1-010…012). One
	 * lesson holds one activity in Course Captain, so the quiz is its own
	 * lesson in the optional module LM-QZ of C-A (the GM-009 denominator
	 * stays 8). On-demand step inside the test transaction.
	 *
	 * @return int Quiz lesson.
	 */
	private function lesson_quiz(): int {
		$admin  = Actor::user( $this->fx['users']['admin'] );
		$module = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_module',
				'post_name'   => 'cc-fx-lm-qz',
				'post_title'  => 'Modul LM-QZ',
				'post_status' => 'publish',
			)
		);
		CourseStructure::current()->assign_module( $admin, $this->fx['courses']['C-A'], $module, 80, false );
		$lesson = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_lesson',
				'post_name'   => 'cc-fx-l-a-05-quiz',
				'post_title'  => 'Lektion L-A-05 Quiz',
				'post_status' => 'publish',
				'post_parent' => $module,
			)
		);
		ActivityService::current()->define( $admin, $lesson, 'quiz', array( 'source' => 'inline' ), array( 'type' => 'attempted' ) );
		foreach ( array( 'A1-010', 'A1-011', 'A1-012' ) as $i => $key ) {
			Database::current()->insert(
				'ccap_activity_question',
				array(
					'activity_id' => $lesson,
					'question_id' => NeutralFixture::question( $key ),
					'sort_order'  => $i + 1,
				)
			);
		}
		self::assertSame( 'quiz', ActivityService::definition( $lesson )['type'] );
		self::assertCount( 3, QuestionPresenter::current()->inline_questions( $lesson ) );
		PolicyService::flush();
		return $lesson;
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
			self::assertSame( $code, $e->error_code, $e->getMessage() );
			return $e;
		}
		self::fail( 'expected ' . $code );
	}
}
