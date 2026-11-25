<?php
/**
 * R1-42: parallelism and data integrity against the real DB (R0-21 §5 CON-0xx).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GroupService;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
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
use CourseCaptain\Storage\RateLimiter;
use CourseCaptain\Storage\StorageException;
use CourseCaptain\Tests\Support\RaceRunner;

/**
 * Multi-process scenarios (`P`) and fault injection (`I`).
 *
 * @group R1-42
 */
final class ConcurrencyTest extends SchemaTestCase {

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
	 * Module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Setup: one published course with one module (5 questions) and one lesson.
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! RaceRunner::available() ) {
			self::markTestSkipped( 'pcntl/posix required.' );
		}
		Plugin::activate( false );
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->course = $this->post( 'ccap_course', 0 );
		$this->module = $this->post( 'ccap_module', 0 );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module );
		$repo = QuestionRepository::current();
		for ( $i = 0; $i < 5; $i++ ) {
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
			$repo->add_to_pool( $admin, $this->module, $q );
		}
	}

	/**
	 * Published post.
	 *
	 * @param string $type   Type.
	 * @param int    $parent_id Parent.
	 * @return int
	 */
	private function post( string $type, int $parent_id ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_parent'  => $parent_id,
				'post_title'   => $type,
				'post_content' => 'content',
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Learner with an active grant.
	 *
	 * @return int
	 */
	private function learner(): int {
		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$db   = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		return $user;
	}

	/**
	 * Count rows.
	 *
	 * @param string $table Table.
	 * @param string $where Trusted condition.
	 * @return int
	 */
	private static function rows( string $table, string $where ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '` WHERE ' . $where );
	}

	/**
	 * Published exam rule over the module (5 questions, 60 %).
	 *
	 * @return int
	 */
	private function rule(): int {
		$admin = Actor::user( $this->admin );
		$rule  = ExamRules::current()->create(
			$admin,
			$this->course,
			'v' . wp_generate_password( 4, false ),
			array(
				'min_ratio' => array(
					'num' => 3,
					'den' => 5,
				),
			),
			600
		);
		ExamRules::current()->set_blueprint( $admin, $rule, $this->module, 5 );
		ExamRules::current()->publish( $admin, $rule );
		return $rule;
	}

	/**
	 * CON-001 / CON-002: N parallel joins on the last seat; the same account in two tabs.
	 *
	 * @group CON-001
	 * @group CON-002
	 * @group GM-096
	 * @group SEAT-003
	 * @group SEC-027
	 */
	public function test_CON_001_002_last_seat(): void {
		$limit_round = RaceRunner::repeat( 2 );
		for ( $round = 0; $round < $limit_round; $round++ ) {
			$service = GroupService::current();
			$owner   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
			$group   = $service->create( Actor::user( $this->admin ), $owner, 'Team', 1, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
			$link    = $service->rotate_link( Actor::user( $owner ), $group );
			$users   = array();
			$jobs    = array();
			$limit_i = RaceRunner::n( 8 );
			for ( $i = 0; $i < $limit_i; $i++ ) {
				$users[]          = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
				$user             = end( $users );
				$jobs[ 'u' . $i ] = static fn() => GroupService::current()->claim( Actor::user( $user ), $link );
			}
			$results  = RaceRunner::run( 'CON-001', $jobs );
			$outcomes = RaceRunner::outcomes( $results );
			self::assertSame( 1, $outcomes['ok'] ?? 0, wp_json_encode( $outcomes ) );
			self::assertSame( 0, ( $outcomes['exception'] ?? 0 ), wp_json_encode( $results ) );
			self::assertSame( 1, self::rows( 'ccap_seat', "status = 'active' AND group_id = " . (int) $group ) );
			self::assertLessThanOrEqual( 1, $service->occupied( $group ) );

			// CON-002: one more seat, same account twice in parallel.
			$group2 = $service->create( Actor::user( $this->admin ), $owner, 'Team2', 3, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
			$link2  = $service->rotate_link( Actor::user( $owner ), $group2 );
			$same   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
			$twice  = RaceRunner::run(
				'CON-002',
				array(
					'tab1' => static fn() => GroupService::current()->claim( Actor::user( $same ), $link2 ),
					'tab2' => static fn() => GroupService::current()->claim( Actor::user( $same ), $link2 ),
				)
			);
			self::assertSame( 1, self::rows( 'ccap_seat', "status = 'active' AND user_id = " . $same ) );
			$ok = array_filter( $twice, static fn( array $r ): bool => ! empty( $r['ok'] ) );
			self::assertNotEmpty( $ok, wp_json_encode( $twice ) );
			self::assertCount( 1, array_unique( array_column( $ok, 'value' ) ), 'second answer = existing membership' );
		}
	}

	/**
	 * CON-005: parallel exam starts of one learner with different request keys.
	 *
	 * @group CON-005
	 * @group GM-045
	 */
	public function test_CON_005_parallel_starts(): void {
		$rule    = $this->rule();
		$learner = $this->learner();
		$jobs    = array();
		$limit_i = RaceRunner::n( 6 );
		for ( $i = 0; $i < $limit_i; $i++ ) {
			$jobs[ 's' . $i ] = static fn() => ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 's' . $i ) );
		}
		$results = RaceRunner::run( 'CON-005', $jobs );
		$ids     = array_unique( array_column( array_filter( $results, static fn( array $r ): bool => ! empty( $r['ok'] ) ), 'value' ) );
		self::assertCount( 1, $ids, wp_json_encode( $results ) );
		foreach ( $results as $result ) {
			self::assertTrue( ! empty( $result['ok'] ) || 'ccap_exam_start_busy' === ( $result['code'] ?? '' ), wp_json_encode( $result ) );
		}
		self::assertSame( 1, self::rows( 'ccap_exam_session', "status = 'in_progress' AND user_id = " . $learner ) );
	}

	/**
	 * CON-016: submit of one session in parallel → one evaluation, identical results, one event.
	 *
	 * @group CON-016
	 * @group GM-053
	 */
	public function test_CON_016_parallel_submit(): void {
		add_filter( 'course_captain_internal_subscribers', static fn( array $s ): array => $s + array( 'test' => array( 'exam.*' ) ) );
		$rule    = $this->rule();
		$learner = $this->learner();
		$session = ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 'x' ) );
		ExamSessions::current()->save_answer( Actor::user( $learner ), $session, 1, array( 'A' ) );
		$jobs    = array();
		$limit_i = RaceRunner::n( 6 );
		for ( $i = 0; $i < $limit_i; $i++ ) {
			$jobs[ 'submit' . $i ] = static function () use ( $learner, $session ) {
				add_filter( 'course_captain_internal_subscribers', static fn( array $s ): array => $s + array( 'test' => array( 'exam.*' ) ) );
				return ExamSessions::current()->submit( Actor::user( $learner ), $session );
			};
		}
		$results = RaceRunner::run( 'CON-016', $jobs );
		$values  = array_map( static fn( array $r ): string => (string) wp_json_encode( $r['value'] ?? $r ), $results );
		self::assertCount( 1, array_unique( $values ), 'identical results: ' . wp_json_encode( $results ) );
		$db  = Database::current();
		$row = $db->row( $db->prepare( 'SELECT status, evaluation_no FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		self::assertSame( 'evaluated', $row['status'] );
		self::assertSame( '1', (string) $row['evaluation_no'] );
		self::assertSame( 1, self::rows( 'ccap_outbox_event', "event_type IN ('exam.passed', 'exam.failed') AND user_ref = 'user:" . $learner . "'" ) );
	}

	/**
	 * CON-008: first answers of many tokens on the same question in parallel → one progress row.
	 *
	 * @group CON-008
	 * @group GM-188
	 */
	public function test_CON_008_first_progress_row(): void {
		$learner  = $this->learner();
		$question = (int) QuestionRepository::current()->pool( $this->module )[0];
		$tokens   = array();
		$limit_i  = RaceRunner::n( 6 );
		for ( $i = 0; $i < $limit_i; $i++ ) {
			$tokens[] = (string) QuestionPresenter::current()->deliver( Actor::user( $learner ), $this->course, $this->module, $question )['token'];
		}
		$jobs = array();
		foreach ( $tokens as $i => $token ) {
			$jobs[ 'a' . $i ] = static fn() => AnswerService::current()->answer( Actor::user( $learner ), $token, array( 'A' ), wp_generate_uuid4() )['repeated'] ?? false;
		}
		$results = RaceRunner::run( 'CON-008', $jobs );
		self::assertSame( array( 'ok' => count( $tokens ) ), RaceRunner::outcomes( $results ), wp_json_encode( $results ) );
		self::assertSame( 1, self::rows( 'ccap_question_progress', 'user_id = ' . $learner . ' AND question_id = ' . $question ) );
		self::assertSame( count( $tokens ), self::rows( 'ccap_attempt', 'user_id = ' . $learner ) );
	}

	/**
	 * CON-019: activity completion from two devices → one progress row, one completion.
	 *
	 * @group CON-019
	 */
	public function test_CON_019_double_completion(): void {
		$lesson = $this->post( 'ccap_lesson', $this->module );
		ActivityService::current()->define( Actor::user( $this->admin ), $lesson, 'lesson', array() );
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
		$learner = $this->learner();
		$course  = $this->course;
		$jobs    = array();
		$limit_i = RaceRunner::n( 4 );
		for ( $i = 0; $i < $limit_i; $i++ ) {
			$jobs[ 'd' . $i ] = static fn() => ActivityProgress::current()->interact( Actor::user( $learner ), $course, $lesson, 'confirm' )['completion']['state'] ?? null;
		}
		$results = RaceRunner::run( 'CON-019', $jobs );
		self::assertSame( 0, RaceRunner::outcomes( $results )['exception'] ?? 0, wp_json_encode( $results ) );
		self::assertSame( 1, self::rows( 'ccap_activity_progress', 'user_id = ' . $learner . ' AND activity_id = ' . $lesson ) );
		self::assertSame( 1, self::rows( 'ccap_course_progress', 'user_id = ' . $learner . ' AND course_id = ' . $course ) );
	}

	/**
	 * CON-011: two parallel publications of the same course → one new revision.
	 *
	 * @group CON-011
	 */
	public function test_CON_011_parallel_publish(): void {
		$admin  = $this->admin;
		$course = $this->course;
		CoursePublisher::current()->publish( Actor::user( $admin ), $course );
		$before = self::rows( 'ccap_course_revision', 'course_id = ' . $course );
		$lesson = $this->post( 'ccap_lesson', $this->module );
		ActivityService::current()->define( Actor::user( $admin ), $lesson, 'lesson', array() );
		$results = RaceRunner::run(
			'CON-011',
			array(
				'author1' => static fn() => CoursePublisher::current()->publish( Actor::user( $admin ), $course ),
				'author2' => static fn() => CoursePublisher::current()->publish( Actor::user( $admin ), $course ),
			)
		);
		self::assertSame( 0, RaceRunner::outcomes( $results )['exception'] ?? 0, wp_json_encode( $results ) );
		self::assertSame( $before + 1, self::rows( 'ccap_course_revision', 'course_id = ' . $course ), wp_json_encode( $results ) );
		$db = Database::current();
		self::assertSame( '0', (string) $db->scalar( 'SELECT COUNT(*) - COUNT(DISTINCT revision_no) FROM `' . $db->table( 'ccap_course_revision' ) . '` WHERE course_id = ' . (int) $course ) );
	}

	/**
	 * CON-015: rate-limit counter under parallel hits equals the number of requests.
	 *
	 * @group CON-015
	 */
	public function test_CON_015_counter_exact(): void {
		$n    = RaceRunner::n( 12 );
		$jobs = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$jobs[ 'h' . $i ] = static function (): bool {
				RateLimiter::hit( Database::current(), 'race', 77, 3600, 10000, 1800000000 );
				return true;
			};
		}
		RaceRunner::run( 'CON-015', $jobs );
		$db = Database::current();
		self::assertSame( (string) $n, (string) $db->scalar( $db->prepare( 'SELECT hits FROM `' . $db->table( 'ccap_rate_limit' ) . '` WHERE bucket_hash = %s', hash( 'sha256', 'race:77' ) ) ) );
	}

	/**
	 * CON-040: a real deadlock (A→B vs. B→A) is retried completely; both units commit.
	 *
	 * @group CON-040
	 */
	public function test_CON_040_real_deadlock_retried(): void {
		$db    = Database::current();
		$table = $db->table( 'ccap_rate_limit' );
		foreach ( array( 'lockA', 'lockB' ) as $key ) {
			$db->execute( $db->prepare( "INSERT INTO `{$table}` (bucket_hash, window_start, hits, expires_at) VALUES (%s, 0, 0, %s)", $key, '2030-01-01 00:00:00' ) );
		}
		$unit    = static function ( string $first, string $second ): int {
			return Database::current()->transaction(
				static function ( Database $d ) use ( $first, $second ): int {
					$t = $d->table( 'ccap_rate_limit' );
					$d->execute( $d->prepare( "UPDATE `{$t}` SET hits = hits + 1 WHERE bucket_hash = %s", $first ) );
					usleep( 300000 );
					$d->execute( $d->prepare( "UPDATE `{$t}` SET hits = hits + 1 WHERE bucket_hash = %s", $second ) );
					return 1;
				}
			);
		};
		$results = RaceRunner::run(
			'CON-040',
			array(
				'ab' => static fn() => $unit( 'lockA', 'lockB' ),
				'ba' => static fn() => $unit( 'lockB', 'lockA' ),
			)
		);
		self::assertSame( array( 'ok' => 2 ), RaceRunner::outcomes( $results ), wp_json_encode( $results ) );
		self::assertGreaterThanOrEqual( 1, array_sum( array_column( $results, 'deadlocks' ) ), 'a deadlock happened and was retried' );
		self::assertSame( '2', (string) $db->scalar( "SELECT hits FROM `{$table}` WHERE bucket_hash = 'lockA'" ) );
		self::assertSame( '2', (string) $db->scalar( "SELECT hits FROM `{$table}` WHERE bucket_hash = 'lockB'" ) );
	}

	/**
	 * CON-041 / CON-043: injected 1213 is retried; after three deadlocks `503 ccap_db_busy`, no after-commit callbacks.
	 *
	 * @group CON-043
	 */
	public function test_CON_043_deadlock_retries_and_busy(): void {
		$db       = Database::current();
		$attempts = 0;
		$fired    = 0;
		$result   = $db->transaction(
			static function ( Database $d ) use ( &$attempts, &$fired ): string {
				++$attempts;
				$d->after_commit(
					static function () use ( &$fired ): void {
						++$fired;
					}
				);
				if ( $attempts < 3 ) {
					throw new StorageException( 'Deadlock found when trying to get lock', 1213 );
				}
				return 'done';
			}
		);
		self::assertSame( 'done', $result );
		self::assertSame( 3, $attempts );
		self::assertSame( 1, $fired, 'only the committed attempt fires callbacks' );

		$attempts = 0;
		$fired    = 0;
		try {
			$db->transaction(
				static function ( Database $d ) use ( &$attempts, &$fired ): void {
					++$attempts;
					$d->after_commit(
						static function () use ( &$fired ): void {
							++$fired;
						}
					);
					throw new StorageException( 'Deadlock found when trying to get lock', 1213 );
				}
			);
			self::fail( 'ccap_db_busy expected' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_db_busy', $e->error_code );
			self::assertSame( 503, $e->status );
			self::assertSame( 2, $e->data['retry_after'] );
		}
		self::assertSame( Database::DEADLOCK_RETRIES, $attempts );
		self::assertSame( 0, $fired );
		self::assertFalse( $db->in_transaction() );
	}

	/**
	 * CON-042: lock wait timeout (1205) → explicit rollback, no retry, `ccap_db_busy`, no partial write.
	 *
	 * @group CON-042
	 */
	public function test_CON_042_lock_wait_timeout(): void {
		$db       = Database::current();
		$attempts = 0;
		try {
			$db->transaction(
				static function ( Database $d ) use ( &$attempts ): void {
					++$attempts;
					RateLimiter::hit( $d, 'partial', 5, 3600, 100, 1800000000 );
					throw new StorageException( 'Lock wait timeout exceeded; try restarting transaction', 1205 );
				}
			);
			self::fail( 'ccap_db_busy expected' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_db_busy', $e->error_code );
		}
		self::assertSame( 1, $attempts );
		self::assertSame( 0, self::rows( 'ccap_rate_limit', "bucket_hash = '" . hash( 'sha256', 'partial:5' ) . "'" ), 'rolled back' );
	}
}
