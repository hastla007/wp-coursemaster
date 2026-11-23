<?php
/**
 * R1-18 follow-up (group g1): stage counts (GM-075) and legacy rows without `due_at` (GM-068).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\LegacyDueBackfill;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;

/**
 * Real tables, injected clock (D-04); one course with one module of `n`
 * questions per test.
 *
 * @group R1-18
 */
final class LearningStagesTest extends SchemaTestCase {

	private const T0 = 1894701600; // 2030-01-15 10:00:00 UTC (fixture T0).

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
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Reset the clock.
	 */
	public function tear_down(): void {
		SmartTrainer::set_now( null );
		parent::tear_down();
	}

	/**
	 * GM-075: 10 questions – 4 new, 1 unsure, 2 learning, 2 secure today,
	 * 1 consolidated (due) – sum up to the total; `secure=2`,
	 * `secure_percent=20`, `due=1`, `completed=1`.
	 *
	 * @group GM-075
	 */
	public function test_GM_075_stage_counts(): void {
		$q = $this->course_with( 10 );
		// Q9 consolidated: secure on day 0, consolidated on the next day (+7 d), due after 8 days.
		foreach ( array( 0, 400, 800 ) as $at ) {
			$this->answer( $q[9], true, $at );
		}
		$this->answer( $q[9], true, 21 * HOUR_IN_SECONDS );
		self::assertSame( 'mastered', $this->row( $q[9] )['learn_status'] );
		$day = 9 * DAY_IN_SECONDS;
		// Q7/Q8 secure today (due tomorrow).
		foreach ( array( 7, 8 ) as $i ) {
			foreach ( array( 0, 400, 800 ) as $step ) {
				$this->answer( $q[ $i ], true, $day + $i * 10 + $step );
			}
		}
		$this->answer( $q[5], true, $day + 2000 );
		$this->answer( $q[6], true, $day + 2010 );
		$this->answer( $q[4], false, $day + 2020 );

		SmartTrainer::set_now( self::T0 + $day + 3000 );
		$summary = SmartTrainer::current()->summary( Actor::user( $this->learner ), $this->course );
		self::assertSame( 10, $summary['total'] );
		self::assertSame(
			array(
				'new'      => 4,
				'unsure'   => 1,
				'learning' => 2,
				'secure'   => 2,
				'mastered' => 1,
			),
			array_intersect_key( $summary, array_flip( array( 'new', 'unsure', 'learning', 'secure', 'mastered' ) ) )
		);
		self::assertSame( $summary['total'], $summary['new'] + $summary['unsure'] + $summary['learning'] + $summary['secure'] + $summary['mastered'], 'stages sum up to the total' );
		self::assertSame( array( 2, 20, 1, 1, 10 ), array( $summary['secure'], $summary['secure_percent'], $summary['due'], $summary['completed'], $summary['percent'] ) );
	}

	/**
	 * GM-068: consolidated legacy rows without `due_at` (last answer T0 − 8 d
	 * and T0 − 1 d) stay consolidated; the backfill sets `due_at = last + 7 d`;
	 * `mastered=2, percent=100, due=1`; the due question is presented.
	 *
	 * @group GM-068
	 */
	public function test_GM_068_legacy_rows_without_due_at(): void {
		$q  = $this->course_with( 2 );
		$db = Database::current();
		// D-04: backdating in the database only for legacy rows.
		foreach ( array(
			$q[0] => self::T0 - 8 * DAY_IN_SECONDS,
			$q[1] => self::T0 - DAY_IN_SECONDS,
		) as $question => $last ) {
			$db->insert(
				'ccap_question_progress',
				array(
					'user_id'           => $this->learner,
					'question_id'       => $question,
					'attempts'          => 5,
					'correct_count'     => 5,
					'streak'            => 4,
					'mastery_score'     => '1.0000',
					'learn_status'      => 'mastered',
					'first_answered_at' => SmartTrainer::sql( $last - 3 * DAY_IN_SECONDS ),
					'last_answered_at'  => SmartTrainer::sql( $last ),
				)
			);
		}
		// A secure legacy row of another learner gets last + 20 h; new/learning rows stay untouched.
		$other = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$db->insert(
			'ccap_question_progress',
			array(
				'user_id'          => $other,
				'question_id'      => $q[0],
				'attempts'         => 3,
				'correct_count'    => 3,
				'streak'           => 3,
				'learn_status'     => 'secure',
				'last_answered_at' => SmartTrainer::sql( self::T0 - 3600 ),
			)
		);
		$db->insert(
			'ccap_question_progress',
			array(
				'user_id'          => $other,
				'question_id'      => $q[1],
				'attempts'         => 1,
				'correct_count'    => 1,
				'streak'           => 1,
				'learn_status'     => 'learning',
				'last_answered_at' => SmartTrainer::sql( self::T0 - 3600 ),
			)
		);

		$step = new LegacyDueBackfill();
		self::assertSame( 3, $this->apply( $step ), 'pending rows filled' );
		self::assertSame( 0, $this->apply( $step ), 'idempotent' );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{40}$/', $step->checksum() );

		self::assertSame( 'mastered', $this->row( $q[0] )['learn_status'], 'consolidated stays consolidated' );
		self::assertSame( SmartTrainer::sql( self::T0 - DAY_IN_SECONDS ), $this->row( $q[0] )['due_at'], 'last (T0 − 8 d) + 7 d' );
		self::assertSame( SmartTrainer::sql( self::T0 + 6 * DAY_IN_SECONDS ), $this->row( $q[1] )['due_at'], 'last (T0 − 1 d) + 7 d' );
		self::assertSame( SmartTrainer::sql( self::T0 - 3600 + SmartTrainer::CONSOLIDATE_GAP ), $this->row( $q[0], $other )['due_at'], 'secure: last + 20 h' );
		self::assertNull( $this->row( $q[1], $other )['due_at'], 'learning keeps no due date' );

		SmartTrainer::set_now( self::T0 );
		$trainer = SmartTrainer::current();
		$summary = $trainer->summary( Actor::user( $this->learner ), $this->course );
		self::assertSame( array( 2, 2, 100, 1 ), array( $summary['total'], $summary['mastered'], $summary['percent'], $summary['due'] ) );
		$next = (array) $trainer->next( Actor::user( $this->learner ), $this->course );
		self::assertSame( $q[0], $next['question_id'], 'the due question is presented' );
		self::assertSame( 'review_due', $next['reason'] );

		// A correct answer to the due question counts (streak 5 → +21 d).
		$result = AnswerService::current()->answer( Actor::user( $this->learner ), (string) $next['token'], array( 'A' ), wp_generate_uuid4() );
		self::assertTrue( $result['streak_counted'] );
		self::assertSame( SmartTrainer::sql( self::T0 + 21 * DAY_IN_SECONDS ), $this->row( $q[0] )['due_at'] );
	}

	/**
	 * Run the backfill step on its pending rows (one batch, in a transaction).
	 *
	 * @param LegacyDueBackfill $step Step.
	 * @return int Rows changed.
	 */
	private function apply( LegacyDueBackfill $step ): int {
		$db = Database::current();
		return (int) $db->transaction(
			static function ( Database $tx ) use ( $step ): int {
				$ids = array_map( 'intval', $tx->wpdb()->get_col( 'SELECT id FROM `' . $tx->table( $step->table() ) . '` WHERE ' . $step->pending_where() . ' ORDER BY id FOR UPDATE' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Constant condition of the step.
				return $step->apply( $tx, $ids );
			}
		);
	}

	/**
	 * Course with one module of `$n` published single-choice questions; learner granted.
	 *
	 * @param int $n Questions.
	 * @return list<int> Question IDs in pool order.
	 */
	private function course_with( int $n ): array {
		$admin        = Actor::user( $this->admin );
		$this->course = $this->post( 'ccap_course' );
		$this->module = $this->post( 'ccap_module' );
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module );
		$repo = QuestionRepository::current();
		$db   = Database::current();
		$ids  = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$id = $repo->create(
				$admin,
				array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => 'Stage question ' . $i,
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
			$db->insert(
				'ccap_module_question',
				array(
					'module_id'   => $this->module,
					'question_id' => $id,
					'sort_order'  => $i + 1,
				)
			);
			$ids[] = $id;
		}
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $this->learner,
				'course_id'   => $this->course,
				'module_id'   => 0,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		return $ids;
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
				'post_author' => $this->admin,
				'post_title'  => $type,
			)
		);
	}

	/**
	 * Learning answer at T0 + `$at`.
	 *
	 * @param int  $question Question.
	 * @param bool $correct  Correct.
	 * @param int  $at       Seconds after T0.
	 * @return void
	 */
	private function answer( int $question, bool $correct, int $at ): void {
		SmartTrainer::set_now( self::T0 + $at );
		$token = QuestionPresenter::current()->deliver( Actor::user( $this->learner ), $this->course, $this->module, $question, new Randomizer( '7' ) )['token'];
		AnswerService::current()->answer( Actor::user( $this->learner ), $token, array( $correct ? 'A' : 'B' ), wp_generate_uuid4() );
	}

	/**
	 * Progress row.
	 *
	 * @param int      $question Question.
	 * @param int|null $user     Learner (default: the learner).
	 * @return array<string, mixed>
	 */
	private function row( int $question, ?int $user = null ): array {
		$db = Database::current();
		return (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_question_progress' ) . '` WHERE user_id = %d AND question_id = %d', $user ?? $this->learner, $question ) );
	}
}
