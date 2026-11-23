<?php
/**
 * R1-22: query budgets, constancy rule, scan rule and job backlog (R0-14 §1.1, §2.3, NFR-002/003/009).
 *
 * Profile `ci` (R-CI): checks query counts and invariants, not wall times.
 * `CCAP_PERF_REPORT=<file>` appends one JSON line per measurement.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Event\Outbox;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearnerQueries;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;

/**
 * Performance gates on the real DB.
 *
 * @group R1-22
 */
final class PerformanceTest extends SchemaTestCase {

	/**
	 * Regression ceilings (own queries) measured on 16.11.2026; R0-14 §1.1 budgets in comments.
	 * Lowering them is welcome; raising them needs a reason in docs/r1/R1-22-nachweis.md.
	 */
	private const CEILING = array(
		'OP-01' => 23, // Budget 15; measured 21.
		'OP-03' => 25, // Budget 10; measured 23.
		'OP-04' => 30, // Budget 8; measured 26–28.
		'OP-05' => 25, // Budget 15; measured 22–23.
		'OP-06' => 13, // Budget 6; measured 12.
		'OP-07' => 10, // Budget 20; measured 9.
	);

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		PolicyService::flush();
	}

	/**
	 * Count queries (all and own tables) of one call.
	 *
	 * @param callable $call Call.
	 * @return array{all: int, own: int, selects: list<string>, ms: float}
	 */
	private static function measure( callable $call ): array {
		$stats   = array(
			'all'     => 0,
			'own'     => 0,
			'selects' => array(),
		);
		$counter = static function ( $sql ) use ( &$stats ) {
			++$stats['all'];
			if ( false !== strpos( (string) $sql, 'ccap_' ) ) {
				++$stats['own'];
				if ( 0 === stripos( ltrim( (string) $sql ), 'SELECT' ) ) {
					$stats['selects'][] = (string) $sql;
				}
			}
			return $sql;
		};
		add_filter( 'query', $counter );
		$t0 = microtime( true );
		$call();
		$stats['ms'] = round( ( microtime( true ) - $t0 ) * 1000, 1 );
		remove_filter( 'query', $counter );
		return $stats;
	}

	/**
	 * Append a measurement line.
	 *
	 * @param string               $op   Operation.
	 * @param array<string, mixed> $data Data.
	 * @return void
	 */
	private static function report( string $op, array $data ): void {
		$file = (string) getenv( 'CCAP_PERF_REPORT' );
		if ( '' !== $file ) {
			unset( $data['selects'] );
			file_put_contents( $file, wp_json_encode( array( 'op' => $op ) + $data ) . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Published post.
	 *
	 * @param string $type   Type.
	 * @param int    $parent_id Parent.
	 * @return int
	 */
	private function post( string $type, int $parent_id = 0 ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_parent'  => $parent_id,
				'post_title'   => $type . ' ' . wp_generate_password( 4, false ),
				'post_content' => 'content',
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Published course with one module, two lessons and `$questions` questions.
	 *
	 * @param int $questions Questions in the pool.
	 * @return array{course: int, module: int, questions: list<int>}
	 */
	private function course( int $questions = 0 ): array {
		$admin  = Actor::user( $this->admin );
		$course = $this->post( 'ccap_course' );
		$module = $this->post( 'ccap_module' );
		CourseStructure::current()->assign_module( $admin, $course, $module );
		foreach ( array( 1, 2 ) as $n ) {
			ActivityService::current()->define( $admin, $this->post( 'ccap_lesson', $module ), 'lesson', array() );
			unset( $n );
		}
		$ids  = array();
		$repo = QuestionRepository::current();
		for ( $i = 0; $i < $questions; $i++ ) {
			$q = $repo->create(
				$admin,
				array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => 'Q' . $i,
					'answers'       => array(
						array(
							'key'     => 'A',
							'text'    => 'a',
							'correct' => true,
						),
						array(
							'key'  => 'B',
							'text' => 'b',
						),
					),
				)
			);
			$repo->publish( $admin, $q );
			$repo->add_to_pool( $admin, $module, $q );
			$ids[] = $q;
		}
		CoursePublisher::current()->publish( $admin, $course );
		return array(
			'course'    => $course,
			'module'    => $module,
			'questions' => $ids,
		);
	}

	/**
	 * Active grant.
	 *
	 * @param int $user   User.
	 * @param int $course Course.
	 * @return void
	 */
	private static function grant( int $user, int $course ): void {
		$db = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Exam rule with `$count` questions from the module.
	 *
	 * @param array{course: int, module: int, questions: list<int>} $c     Course.
	 * @param int                                                   $count Questions.
	 * @return int
	 */
	private function rule( array $c, int $count ): int {
		$admin = Actor::user( $this->admin );
		$rule  = ExamRules::current()->create(
			$admin,
			$c['course'],
			'v' . $count,
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			3600
		);
		ExamRules::current()->set_blueprint( $admin, $rule, $c['module'], $count );
		ExamRules::current()->publish( $admin, $rule );
		return $rule;
	}

	/**
	 * NFR-002 constancy rule for OP-01 (My learning): own queries must not grow with the number of courses.
	 *
	 * @group NFR-002
	 * @group GM-142
	 */
	public function test_OP_01_my_learning_constancy(): void {
		$learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$counts  = array();
		$have    = 0;
		foreach ( array( 1, 5, 20 ) as $n ) {
			for ( ; $have < $n; $have++ ) {
				self::grant( $learner, $this->course()['course'] );
			}
			PolicyService::flush();
			wp_cache_flush();
			$m            = self::measure( static fn() => LearnerQueries::current()->my_courses( Actor::user( $learner ) ) );
			$counts[ $n ] = $m['own'];
			self::report( 'OP-01', array( 'n' => $n ) + $m );
		}
		self::assertLessThanOrEqual( self::CEILING['OP-01'], $counts[1], 'OP-01 ceiling' );
		self::assertSame( $counts[5], $counts[20], 'no N+1: ' . wp_json_encode( $counts ) );
	}

	/**
	 * OP-03/OP-04 budgets (smart trainer next/answer) with a large pool.
	 *
	 * @group NFR-002
	 */
	public function test_OP_03_04_trainer_budgets(): void {
		$c       = $this->course( 60 );
		$learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::grant( $learner, $c['course'] );
		PolicyService::flush();
		$next = null;
		$m    = self::measure(
			static function () use ( $learner, $c, &$next ) {
				$next = SmartTrainer::current()->next( Actor::user( $learner ), $c['course'] );
			}
		);
		self::report( 'OP-03', $m );
		self::assertNotNull( $next );
		self::assertLessThanOrEqual( self::CEILING['OP-03'], $m['own'], 'OP-03 ceiling' );
		$answer = self::measure( static fn() => AnswerService::current()->answer( Actor::user( $learner ), (string) $next['token'], array( 'A' ), wp_generate_uuid4() ) );
		self::report( 'OP-04', $answer );
		self::assertLessThanOrEqual( self::CEILING['OP-04'], $answer['own'], 'OP-04 ceiling' );
	}

	/**
	 * OP-05/06/07 exam start, saveAnswer and submit; submit must not query per question (constancy 5 vs. 30).
	 *
	 * @group NFR-002
	 * @group GM-187
	 */
	public function test_OP_05_06_07_exam_constancy(): void {
		$c       = $this->course( 30 );
		$learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::grant( $learner, $c['course'] );
		PolicyService::flush();
		$counts = array();
		foreach ( array( 5, 30 ) as $size ) {
			$rule            = $this->rule( $c, $size );
			$session         = 0;
			$start           = self::measure(
				static function () use ( $learner, $rule, &$session ) {
					$session = ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 'p' ) );
				}
			);
			$save            = self::measure( static fn() => ExamSessions::current()->save_answer( Actor::user( $learner ), $session, 1, array( 'A' ) ) );
			$submit          = self::measure( static fn() => ExamSessions::current()->submit( Actor::user( $learner ), $session ) );
			$counts[ $size ] = array(
				'start'  => $start['own'],
				'save'   => $save['own'],
				'submit' => $submit['own'],
			);
			self::report( 'OP-05', array( 'n' => $size ) + $start );
			self::report( 'OP-06', array( 'n' => $size ) + $save );
			self::report( 'OP-07', array( 'n' => $size ) + $submit );
		}
		self::assertLessThanOrEqual( self::CEILING['OP-05'], $counts[30]['start'], 'OP-05 ceiling' );
		self::assertLessThanOrEqual( $counts[5]['start'] + 2, $counts[30]['start'], 'start independent of size (one multi-row INSERT): ' . wp_json_encode( $counts ) );
		self::assertLessThanOrEqual( self::CEILING['OP-06'], $counts[30]['save'], 'OP-06 ceiling' );
		self::assertLessThanOrEqual( self::CEILING['OP-07'], $counts[30]['submit'], 'OP-07 ceiling' );
		self::assertSame( $counts[5]['save'], $counts[30]['save'], 'saveAnswer independent of size' );
		self::assertLessThanOrEqual( $counts[5]['submit'] + 2, $counts[30]['submit'], 'submit without per-question queries: ' . wp_json_encode( $counts ) );
	}

	/**
	 * NFR-003 scan rule: own SELECTs of the hot paths use an index (no `type = ALL` on seeded tables).
	 *
	 * @group NFR-003
	 */
	public function test_NFR_003_no_full_scans_on_hot_paths(): void {
		$c       = $this->course( 30 );
		$learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::grant( $learner, $c['course'] );
		$db = Database::current();
		// Seed volume into the large tables (profile ci: 20 000 progress rows, 5 000 grants).
		$rows = array();
		for ( $u = 1; $u <= 400; $u++ ) {
			foreach ( $c['questions'] as $i => $q ) {
				if ( $i < 50 ) {
					$rows[] = $db->prepare( '(%d, %d, 1, %s, %s)', 100000 + $u, $q, 'secure', $db->now( 86400 ) );
				}
			}
		}
		foreach ( array_chunk( $rows, 1000 ) as $chunk ) {
			$db->execute( 'INSERT IGNORE INTO `' . $db->table( 'ccap_question_progress' ) . '` (user_id, question_id, algorithm_version, learn_status, due_at) VALUES ' . implode( ',', $chunk ) );
		}
		$grants = array();
		for ( $u = 1; $u <= 5000; $u++ ) {
			$grants[] = $db->prepare( '(%s, %d, %d, %s, %s, %s, %s, %s)', $db->uuid(), 200000 + $u, $c['course'], 'active', 'manual', $db->uuid(), $db->now(), $db->now() );
		}
		foreach ( array_chunk( $grants, 1000 ) as $chunk ) {
			$db->execute( 'INSERT INTO `' . $db->table( 'ccap_grant' ) . '` (uid, user_id, course_id, status, source_type, source_id, created_at, updated_at) VALUES ' . implode( ',', $chunk ) );
		}
		$db->execute( 'ANALYZE TABLE `' . $db->table( 'ccap_question_progress' ) . '`, `' . $db->table( 'ccap_grant' ) . '`' );
		PolicyService::flush();
		$selects = array();
		foreach ( array(
			static fn() => LearnerQueries::current()->my_courses( Actor::user( $learner ) ),
			static fn() => SmartTrainer::current()->next( Actor::user( $learner ), $c['course'] ),
			static fn() => SmartTrainer::current()->summary( Actor::user( $learner ), $c['course'] ),
		) as $call ) {
			$selects = array_merge( $selects, self::measure( $call )['selects'] );
		}
		$large = array( $db->table( 'ccap_question_progress' ), $db->table( 'ccap_grant' ) );
		$scans = array();
		foreach ( array_unique( $selects ) as $sql ) {
			foreach ( $db->rows( 'EXPLAIN ' . $sql ) as $plan ) {
				if ( 'ALL' === strtoupper( (string) ( $plan['type'] ?? '' ) ) && in_array( (string) $plan['table'], $large, true ) ) {
					$scans[] = $plan['table'] . ': ' . substr( $sql, 0, 160 );
				}
			}
		}
		self::report(
			'NFR-003',
			array(
				'selects' => array(),
				'scans'   => $scans,
				'checked' => count( array_unique( $selects ) ),
			)
		);
		self::assertSame( array(), $scans, 'full table scans on large tables' );
	}

	/**
	 * NFR-009 job backlog: 2 000 outbox events are drained in bounded runs, each within its time budget.
	 *
	 * @group NFR-009
	 */
	public function test_NFR_009_outbox_backlog(): void {
		add_filter(
			'course_captain_internal_subscribers',
			static fn( array $s ): array => $s + array( 'perf' => array( 'perf.*' ) )
		);
		add_filter(
			'course_captain_internal_subscriber_handlers',
			static fn( array $h ): array => $h + array( 'perf' => static fn(): bool => true )
		);
		$db = Database::current();
		for ( $i = 0; $i < 2000; $i++ ) {
			Outbox::record( $db, 'perf.tick', 'perf', 'p' . $i, 1, array() );
		}
		$runs  = 0;
		$times = array();
		while ( $runs < 50 ) {
			++$runs;
			$t0      = microtime( true );
			$done    = OutboxDispatcher::current()->dispatch();
			$times[] = microtime( true ) - $t0;
			if ( 0 === (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_delivery' ) . "` WHERE status IN ('pending','dispatching')" ) ) {
				break;
			}
			unset( $done );
		}
		self::report(
			'NFR-009',
			array(
				'events' => 2000,
				'runs'   => $runs,
				'max_s'  => round( max( $times ), 2 ),
			)
		);
		self::assertSame( 0, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_delivery' ) . "` WHERE status IN ('pending','dispatching')" ), 'backlog drained' );
		self::assertLessThan( 20.0, max( $times ), 'each run within its 20 s budget (RB-02)' );
	}
}
