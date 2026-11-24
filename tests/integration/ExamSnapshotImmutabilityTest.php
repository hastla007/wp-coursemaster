<?php
/**
 * Konzept Kap. 28 (TODO Z. 702): immutable exam snapshots.
 *
 * Questions, answer options, order, time limits, rules and seed are fixed
 * when an attempt starts; later author changes never change what the
 * learner sees or how the attempt is graded (R0-08 §6.3/§7.1, R0-23 RV-01, E-50).
 * Kap. 28 (TODO Z. 713): the competency evidence of a passed attempt keeps
 * source, date, rule version and validity from that snapshot (R0-08 §5.3, E-73).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Progress\Competency;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-08
 * @group K28-702
 * @group K28-713
 */
final class ExamSnapshotImmutabilityTest extends SchemaTestCase {

	private const SEED = '20300115';

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learners with grant.
	 *
	 * @var list<int>
	 */
	private array $learners = array();

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
	 * Course with two modules (4 + 6 single-choice questions) and two learners with grants.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->course = $this->post( 'ccap_course' );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		foreach ( array( 4, 6 ) as $count ) {
			$module          = $this->post( 'ccap_module' );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module );
			for ( $i = 0; $i < $count; $i++ ) {
				$this->question( $module );
			}
		}
		$db = Database::current();
		for ( $i = 0; $i < 2; $i++ ) {
			$learner          = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
			$this->learners[] = $learner;
			$db->insert(
				'ccap_grant',
				array(
					'uid'         => $db->uuid(),
					'user_id'     => $learner,
					'course_id'   => $this->course,
					'status'      => 'active',
					'source_type' => 'manual',
					'source_id'   => $db->uuid(),
				),
				array( 'created_at', 'updated_at' )
			);
		}
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
	 * Published single-choice question in a pool (correct option always A).
	 *
	 * @param int $module Module.
	 * @return int
	 */
	private function question( int $module ): int {
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
					array(
						'key'  => 'C',
						'text' => 'wrong too',
					),
				),
			)
		);
		$repo->publish( $admin, $id );
		$repo->add_to_pool( $admin, $module, $id );
		return $id;
	}

	/**
	 * Published rule with blueprint A1:2, A2:3.
	 *
	 * @param int                  $num        Pass mark numerator (of 5).
	 * @param int                  $time_limit Time limit in seconds.
	 * @param array<string, mixed> $settings   Own rule settings.
	 * @return int
	 */
	private function rule( int $num, int $time_limit, array $settings = array() ): int {
		$rules = ExamRules::current();
		$admin = Actor::user( $this->admin );
		$rule  = $rules->create(
			$admin,
			$this->course,
			'v' . wp_generate_password( 4, false ),
			array(
				'min_ratio' => array(
					'num' => $num,
					'den' => 5,
				),
			),
			$time_limit,
			$settings
		);
		$rules->set_blueprint( $admin, $rule, $this->modules[0], 2 );
		$rules->set_blueprint( $admin, $rule, $this->modules[1], 3 );
		$rules->publish( $admin, $rule );
		return $rule;
	}

	/**
	 * Start an attempt with the fixed seed.
	 *
	 * @param int $rule    Rule.
	 * @param int $learner Learner index.
	 * @return int
	 */
	private function start( int $rule, int $learner = 0 ): int {
		return ExamSessions::current()->start( Actor::user( $this->learners[ $learner ] ), $rule, null, wp_generate_uuid4(), new Randomizer( self::SEED ) );
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
	 * Frozen question rows of a session (position, question, revision, answer order).
	 *
	 * @param int $session Session.
	 * @return list<array<string, mixed>>
	 */
	private static function frozen_questions( int $session ): array {
		$db = Database::current();
		return array_map(
			static fn( $row ): array => (array) $row,
			$db->rows( $db->prepare( 'SELECT position, question_id, revision_id, answer_order FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY position', $session ) )
		);
	}

	/**
	 * Question text, option texts and order as the learner sees them.
	 *
	 * @param int $session Session.
	 * @param int $learner Learner index.
	 * @return list<mixed>
	 */
	private function delivered( int $session, int $learner = 0 ): array {
		$view = ExamSessions::current()->view( Actor::user( $this->learners[ $learner ] ), $session );
		return array_map( static fn( array $item ): array => array( $item['position'], $item['question'] ), $view['questions'] );
	}

	/**
	 * Questions, answer options and order stay frozen while authors edit,
	 * archive and re-pool questions; grading uses the frozen correct option.
	 */
	public function test_question_options_and_order_frozen_against_author_changes(): void {
		$rule     = $this->rule( 3, 600 );
		$session  = $this->start( $rule );
		$row      = self::row( $session );
		$frozen   = self::frozen_questions( $session );
		$before   = $this->delivered( $session );
		$admin    = Actor::user( $this->admin );
		$repo     = QuestionRepository::current();
		$archived = (int) $frozen[0]['question_id'];

		self::assertCount( 5, $frozen );
		foreach ( $before as $item ) {
			self::assertCount( 3, $item[1]['options'], 'three options at the start' );
		}

		// Every drawn question: new text, option C removed, option D added, B now correct.
		foreach ( $frozen as $question ) {
			$repo->save(
				$admin,
				(int) $question['question_id'],
				array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => 'changed after start',
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
						array(
							'key'  => 'D',
							'text' => 'new option',
						),
					),
				),
				'substantive'
			);
			$repo->publish( $admin, (int) $question['question_id'] );
		}
		$repo->archive( $admin, $archived );
		// New questions in both pools change what a new attempt could draw.
		$this->question( $this->modules[0] );
		$this->question( $this->modules[1] );

		self::assertSame( $before, $this->delivered( $session ), 'text, options and order unchanged for the learner' );
		self::assertSame( $frozen, self::frozen_questions( $session ), 'questions, revisions and answer order unchanged' );
		$after = self::row( $session );
		self::assertSame( $row['snapshot'], $after['snapshot'] );
		self::assertSame( $row['snapshot_hash'], $after['snapshot_hash'] );
		self::assertSame( 'sha256:' . hash( 'sha256', (string) $after['snapshot'] ), $after['snapshot_hash'], 'hash still matches' );

		$sessions = ExamSessions::current();
		$learner  = Actor::user( $this->learners[0] );
		foreach ( range( 1, 5 ) as $position ) {
			$sessions->save_answer( $learner, $session, $position, array( 'A' ) );
		}
		$result = $sessions->submit( $learner, $session );
		self::assertSame( 5000, $result['points_milli'], 'graded against the frozen revisions (A correct), archived question included' );
		self::assertTrue( $result['passed'] );
	}

	/**
	 * A new rule version with another time limit and pass mark retires the old
	 * rule but leaves the running attempt on its frozen timing and passing rule.
	 */
	public function test_time_limit_and_passing_rule_frozen_against_new_rule_version(): void {
		$old      = $this->rule( 3, 600 );
		$sessions = ExamSessions::current();
		$now      = time();
		$sessions->set_now( $now );
		try {
			$session  = $this->start( $old );
			$deadline = self::row( $session )['deadline_at'];

			$new = $this->rule( 5, 60 );
			self::assertSame( 'retired', ExamRules::current()->get( $old )['status'] );
			self::assertSame( 'published', ExamRules::current()->get( $new )['status'] );

			$sessions->set_now( $now + 120 );
			$learner = Actor::user( $this->learners[0] );
			$view    = $sessions->view( $learner, $session );
			self::assertSame( 'in_progress', $view['status'], 'not expired by the new 60 s limit' );
			self::assertSame( 480, $view['remaining_seconds'], 'still on the frozen 600 s limit' );
			self::assertSame( $deadline, self::row( $session )['deadline_at'] );

			$snapshot = json_decode( (string) self::row( $session )['snapshot'], true );
			self::assertSame( 600, (int) $snapshot['timing']['time_limit_seconds'] );
			self::assertSame( 3, (int) $snapshot['passing_rule']['min_ratio']['num'] );

			foreach ( array( 1, 2, 3 ) as $position ) {
				$sessions->save_answer( $learner, $session, $position, array( 'A' ) );
			}
			$sessions->save_answer( $learner, $session, 4, array( 'B' ) );
			$result = $sessions->submit( $learner, $session );
			self::assertSame( 3000, $result['points_milli'] );
			self::assertTrue( $result['passed'], '3/5 passes the frozen 3/5 rule, not the new 5/5 rule' );
			self::assertSame( $old, (int) self::row( $session )['rule_id'] );
		} finally {
			$sessions->set_now( null );
		}
	}

	/**
	 * The seed is stored in the row and the snapshot; the same seed and pool
	 * reproduce the same composition, order and answer order.
	 */
	public function test_seed_frozen_and_reproducible(): void {
		$rule  = $this->rule( 3, 600 );
		$first = $this->start( $rule, 0 );
		$row   = self::row( $first );
		$snap  = json_decode( (string) $row['snapshot'], true );
		self::assertSame( (int) self::SEED, (int) $row['seed'] );
		self::assertSame( self::SEED, (string) $snap['randomizer']['seed'] );
		self::assertSame( Randomizer::VERSION, (int) $snap['randomizer']['randomizer_version'] );

		$second = $this->start( $rule, 1 );
		$strip  = static fn( array $rows ): array => array_map(
			static fn( array $r ): array => array( $r['position'], $r['question_id'], $r['revision_id'], $r['answer_order'] ),
			$rows
		);
		self::assertSame( $strip( self::frozen_questions( $first ) ), $strip( self::frozen_questions( $second ) ), 'same seed + same pool = same draw' );
		self::assertSame( $this->delivered( $first, 0 ), $this->delivered( $second, 1 ), 'same delivered order' );
	}

	/**
	 * Objectives certified by a rule are frozen at the start: a passed attempt
	 * evidences them with source, date, rule version and validity, even after a
	 * new rule version certifies other objectives; validity ends on time.
	 */
	public function test_competency_evidence_frozen_with_the_snapshot(): void {
		$old      = $this->rule(
			3,
			600,
			array(
				'certifies_objectives'     => array( 'obj-x' ),
				'competency_validity_days' => 30,
			)
		);
		$version  = (string) ExamRules::current()->get( $old )['version'];
		$sessions = ExamSessions::current();
		$now      = (int) strtotime( '2026-11-20 10:00:00 UTC' );
		$sessions->set_now( $now );
		try {
			$session = $this->start( $old );
			$this->rule(
				3,
				600,
				array(
					'certifies_objectives'     => array( 'obj-y' ),
					'competency_validity_days' => 1,
				)
			);
			$learner = Actor::user( $this->learners[0] );
			foreach ( range( 1, 5 ) as $position ) {
				$sessions->save_answer( $learner, $session, $position, array( 'A' ) );
			}
			self::assertTrue( $sessions->submit( $learner, $session )['passed'] );
		} finally {
			$sessions->set_now( null );
		}
		$row        = self::row( $session );
		$competency = Competency::current();
		$states     = $competency->states( $this->learners[0], $this->course, array( 'obj-x', 'obj-y' ), $now + DAY_IN_SECONDS );
		self::assertSame(
			array(
				'state'            => 'evidenced',
				'source_type'      => 'exam_session',
				'source'           => (string) $row['uid'],
				'rule_version'     => $version,
				'evaluated_at'     => '2026-11-20 10:00:00',
				'points_milli'     => 5000,
				'max_points_milli' => 5000,
				'valid_until'      => '2026-12-20 10:00:00',
			),
			$states['obj-x']
		);
		self::assertSame( array( 'state' => 'not_evidenced' ), $states['obj-y'], 'objectives of the newer rule version are not certified by the older attempt' );
		self::assertSame( 'expired', $competency->states( $this->learners[0], $this->course, array( 'obj-x' ), $now + 31 * DAY_IN_SECONDS )['obj-x']['state'] );
		self::assertSame( array( 'state' => 'not_evidenced' ), $competency->states( $this->learners[1], $this->course, array( 'obj-x' ), $now )['obj-x'], 'per learner' );
	}
}
