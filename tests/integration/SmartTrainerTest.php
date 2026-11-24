<?php
/**
 * R1-12: smart trainer v1 (R0-08 §5.2; R0-05 GM-004, GM-065…GM-069, GM-072, GM-073).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;

/**
 * Real tables, injected clock.
 *
 * @group R1-12
 */
final class SmartTrainerTest extends SchemaTestCase {

	private const T0 = 1794830400; // 2026-11-16 12:00:00 UTC.

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
	 * Modules A1 (24 questions, topics T-a/T-b) and A2 (2 questions, topic T-solo + T-x).
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Questions per module in pool order.
	 *
	 * @var array<int, list<int>>
	 */
	private array $pool = array();

	/**
	 * Module of each question.
	 *
	 * @var array<int, int>
	 */
	private array $module_of = array();

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		SmartTrainer::set_now( self::T0 );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course' );
		PolicyService::flush();
		$topics = array(
			array_merge( array_fill( 0, 12, 'T-a' ), array_fill( 0, 12, 'T-b' ) ),
			array( 'T-solo', 'T-x' ),
		);
		foreach ( $topics as $list ) {
			$module          = $this->post( 'ccap_module' );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $module );
			foreach ( $list as $i => $topic ) {
				$id = $this->question( $topic );
				Database::current()->insert(
					'ccap_module_question',
					array(
						'module_id'   => $module,
						'question_id' => $id,
						'sort_order'  => $i + 1,
					)
				);
				$this->pool[ $module ][] = $id;
				$this->module_of[ $id ]  = $module;
			}
		}
		$this->grant( $this->learner, 0 );
	}

	/**
	 * Reset the clock.
	 */
	public function tear_down(): void {
		SmartTrainer::set_now( null );
		parent::tear_down();
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
	 * Published single-choice question with a topic.
	 *
	 * @param string $topic Topic.
	 * @return int
	 */
	private function question( string $topic ): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$id    = $repo->create(
			$admin,
			array(
				'question_type' => QuestionTypes::SINGLE,
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
				),
				'tags'          => array(
					array(
						'type'  => 'topic',
						'key'   => $topic,
						'label' => $topic,
					),
				),
			)
		);
		$repo->publish( $admin, $id );
		return $id;
	}

	/**
	 * Active grant.
	 *
	 * @param int $user   User.
	 * @param int $module Module (0 = course).
	 */
	private function grant( int $user, int $module ): void {
		$db = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $this->course,
				'module_id'   => $module,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
	}

	/**
	 * Token for a question.
	 *
	 * @param int $question Question.
	 * @return string
	 */
	private function token( int $question ): string {
		return QuestionPresenter::current()->deliver( Actor::user( $this->learner ), $this->course, $this->module_of[ $question ], $question, new Randomizer( '7' ) )['token'];
	}

	/**
	 * Answer at a point in time.
	 *
	 * @param int  $question Question.
	 * @param bool $correct  Correct.
	 * @param int  $at       Seconds after T0.
	 * @return array<string, mixed>
	 */
	private function answer( int $question, bool $correct, int $at ): array {
		SmartTrainer::set_now( self::T0 + $at );
		return AnswerService::current()->answer( Actor::user( $this->learner ), $this->token( $question ), array( $correct ? 'A' : 'B' ), wp_generate_uuid4() );
	}

	/**
	 * Next question at a point in time.
	 *
	 * @param int                  $at      Seconds after T0.
	 * @param string               $mode    Mode.
	 * @param array<string, mixed> $options Options.
	 * @return array<string, mixed>|null
	 */
	private function next( int $at, string $mode = 'smart', array $options = array() ): ?array {
		SmartTrainer::set_now( self::T0 + $at );
		return SmartTrainer::current()->next( Actor::user( $this->learner ), $this->course, $mode, $options );
	}

	/**
	 * Progress row.
	 *
	 * @param int $question Question.
	 * @return array<string, mixed>
	 */
	private function progress( int $question ): array {
		$db = Database::current();
		return (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_question_progress' ) . '` WHERE user_id = %d AND question_id = %d', $this->learner, $question ) );
	}

	/**
	 * GM-065: new first, then wrong; mode `wrong` at once.
	 *
	 * @group GM-065
	 */
	public function test_GM_065_order(): void {
		$a1 = $this->pool[ $this->modules[0] ];
		self::assertSame( 'unsure', $this->answer( $a1[0], false, 0 )['progress']['status'] );
		$seen = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$next = (array) $this->next( $i * 10 );
			self::assertSame( 'never_seen', $next['reason'] );
			self::assertNotContains( $next['question_id'], $seen );
			$seen[] = $next['question_id'];
			$this->answer( $next['question_id'], true, $i * 10 + 1 );
		}
		self::assertSame( $a1[0], $this->next( 200, 'wrong' )['question_id'] );

		$all = array_merge( $a1, $this->pool[ $this->modules[1] ] );
		foreach ( array_diff( $all, $seen, array( $a1[0] ) ) as $i => $q ) {
			$this->answer( $q, true, 300 + $i );
		}
		$next = (array) $this->next( 400 );
		self::assertSame( $a1[0], $next['question_id'] );
		self::assertSame( 'last_wrong', $next['reason'] );
		self::assertNull( $this->next( 400, 'new' ) );
	}

	/**
	 * GM-066 / GM-067 / G-3: stages, consolidation on a new day, growing intervals, reset.
	 *
	 * @group GM-066
	 * @group GM-067
	 */
	public function test_GM_066_067_stages_and_intervals(): void {
		$q       = $this->pool[ $this->modules[1] ][0];
		$solo    = array( 'topic' => 'T-solo' );
		$trainer = SmartTrainer::current();
		$learner = Actor::user( $this->learner );
		self::assertNull( $trainer->summary( $learner, $this->course, 'T-solo' )['percent'], 'no data yet, not 0 %' );

		$streaks = array();
		foreach ( array( 0, 301, 602 ) as $at ) {
			$streaks[] = $this->answer( $q, true, $at )['progress']['streak'];
		}
		self::assertSame( array( 1, 2, 3 ), $streaks );
		$row = $this->progress( $q );
		self::assertSame( 'secure', $row['learn_status'] );
		self::assertSame( SmartTrainer::sql( self::T0 + 602 + 72000 ), $row['due_at'] );

		$same_day = $this->answer( $q, true, 1000 );
		self::assertFalse( $same_day['streak_counted'], 'G-3: not due, not counted' );
		self::assertSame( 3, $same_day['progress']['streak'] );
		self::assertSame( '4', (string) $this->progress( $q )['attempts'] );
		SmartTrainer::set_now( self::T0 + 1000 );
		$summary = $trainer->summary( $learner, $this->course, 'T-solo' );
		self::assertSame( array( 1, 0, 0, 100, 0 ), array( $summary['secure'], $summary['mastered'], $summary['percent'], $summary['secure_percent'], $summary['due'] ) );

		// GM-067: due after 20 h; new questions before due; due on another day → mastered, +7 d.
		$t21 = 21 * 3600;
		SmartTrainer::set_now( self::T0 + $t21 );
		self::assertSame( 1, $trainer->summary( $learner, $this->course, 'T-solo' )['due'] );
		self::assertSame( 'never_seen', $this->next( $t21 )['reason'], 'new before due' );
		$due = (array) $this->next( $t21, 'smart', $solo );
		self::assertSame( array( $q, 'review_new_day' ), array( $due['question_id'], $due['reason'] ) );
		$this->answer( $q, true, $t21 );
		$row = $this->progress( $q );
		self::assertSame( array( 'mastered', '4' ), array( $row['learn_status'], (string) $row['streak'] ) );
		self::assertSame( SmartTrainer::sql( self::T0 + $t21 + 7 * 86400 ), $row['due_at'] );

		self::assertFalse( $this->answer( $q, true, $t21 + 3600 )['streak_counted'], 'not due' );
		$t7 = $t21 + 7 * 86400;
		$this->answer( $q, true, $t7 );
		self::assertSame( SmartTrainer::sql( self::T0 + $t7 + 21 * 86400 ), $this->progress( $q )['due_at'], 'streak 5 → 21 d' );
		self::assertSame( 21, SmartTrainer::review_interval( 5 ) );
		self::assertSame( 60, SmartTrainer::review_interval( 9 ) );

		$wrong = $this->answer( $q, false, $t7 + 10 );
		self::assertSame( array( 'unsure', 0, null ), array( $wrong['progress']['status'], $wrong['progress']['streak'], $wrong['progress']['due_at'] ) );
		self::assertNull( $this->progress( $q )['due_at'] );
	}

	/**
	 * Spacing: another question in between counts (G-2); G-1: right after unsure without gap → learning, streak 0.
	 */
	public function test_spacing_and_G1(): void {
		list( $x, $y ) = $this->pool[ $this->modules[0] ];
		$this->answer( $x, true, 0 );
		$this->answer( $y, true, 10 );
		self::assertSame( 2, $this->answer( $x, true, 20 )['progress']['streak'], 'other question in between' );
		self::assertFalse( $this->answer( $x, true, 30 )['streak_counted'], 'no gap, nothing in between' );

		$this->answer( $y, false, 40 );
		$g1 = $this->answer( $y, true, 50 );
		self::assertSame( array( 'learning', 0 ), array( $g1['progress']['status'], $g1['progress']['streak'] ) );
		self::assertContains( $this->next( 60, 'wrong' )['question_id'], array( $y ), 'still "last wrong"' );
		self::assertSame( '0.8000', $this->progress( $x )['mastery_score'], '0.4 · 3/3 + 0.6 · 2/3' );
	}

	/**
	 * GM-069: second tab does not raise the streak; the just answered question does not come back at once.
	 *
	 * @group GM-069
	 * @group GM-188
	 */
	public function test_GM_069_tabs_and_recent(): void {
		$q      = $this->pool[ $this->modules[0] ][0];
		$first  = $this->token( $q );
		$second = $this->token( $q );
		SmartTrainer::set_now( self::T0 );
		$a = AnswerService::current()->answer( Actor::user( $this->learner ), $first, array( 'A' ), wp_generate_uuid4() );
		SmartTrainer::set_now( self::T0 + 5 );
		$b = AnswerService::current()->answer( Actor::user( $this->learner ), $second, array( 'A' ), wp_generate_uuid4() );
		self::assertSame( 1, $a['progress']['streak'] );
		self::assertFalse( $b['streak_counted'] );
		self::assertSame( 1, $b['progress']['streak'] );
		self::assertNotSame( $q, $this->next( 10, 'smart', array( 'only_ids' => array( $q, $this->pool[ $this->modules[0] ][1] ) ) )['question_id'] );
		self::assertSame( $q, $this->next( 10, 'smart', array( 'only_ids' => array( $q ) ) )['question_id'], 'fallback when nothing else is left' );
	}

	/**
	 * GM-072: only unlocked modules; GM-004: topics.
	 *
	 * @group GM-072
	 * @group GM-004
	 */
	public function test_GM_072_scope_and_GM_004_topics(): void {
		$partial = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->grant( $partial, $this->modules[0] );
		$trainer = SmartTrainer::current();
		for ( $i = 0; $i < 4; $i++ ) {
			$next = (array) $trainer->next( Actor::user( $partial ), $this->course );
			self::assertContains( $next['question_id'], $this->pool[ $this->modules[0] ] );
			SmartTrainer::set_now( self::T0 + $i );
			AnswerService::current()->answer( Actor::user( $partial ), (string) $next['token'], array( 'A' ), wp_generate_uuid4() );
		}

		$learner = Actor::user( $this->learner );
		$topics  = $trainer->topics( $learner, $this->course, $this->modules[0] );
		self::assertSame(
			array(
				'T-a' => 12,
				'T-b' => 12,
			),
			$topics
		);
		self::assertSame( count( $this->pool[ $this->modules[0] ] ), array_sum( $topics ) );
		$picked = (array) $this->next( 0, 'smart', array( 'topic' => 'T-b' ) );
		self::assertContains( $picked['question_id'], array_slice( $this->pool[ $this->modules[0] ], 12 ) );
		self::assertNull( $this->next( 0, 'smart', array( 'topic' => 'unknown' ) ) );
		$stranger = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		try {
			$trainer->next( $stranger, $this->course );
			self::fail( 'no access' );
		} catch ( CourseException $e ) {
			self::assertSame( 403, $e->status );
		}
		$this->expectException( CourseException::class );
		$trainer->next( $learner, $this->course, 'random' );
	}

	/**
	 * GM-073: 60 answers per minute, atomic table counter.
	 *
	 * @group GM-073
	 * @group SEC-017
	 */
	public function test_GM_073_rate_limit(): void {
		$db     = Database::current();
		$window = self::T0 - self::T0 % 60;
		$db->insert(
			'ccap_rate_limit',
			array(
				'bucket_hash'  => hash( 'sha256', 'answer:' . $this->learner ),
				'window_start' => $window,
				'hits'         => 59,
				'expires_at'   => SmartTrainer::sql( $window + 120 ),
			)
		);
		$q = $this->pool[ $this->modules[0] ][0];
		$this->answer( $q, true, 0 );
		try {
			$this->answer( $q, true, 1 );
			self::fail( '61st answer' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_rate_limited', $e->error_code );
			self::assertSame( 'answer', $e->data['bucket'] );
			self::assertSame( 429, $e->status );
		}
		self::assertFalse( get_option( 'ccap_rate_limit' ), 'no option' );
		self::assertGreaterThan( 0, $this->answer( $q, true, 61 )['attempts'], 'next minute' );
	}
}
