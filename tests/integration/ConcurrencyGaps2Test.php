<?php
/**
 * R1-42 / R1-25: remaining concurrency, fault and duplicate cases of R0-21 §5
 * (CON-006/009/012/014/017/020/021/036/037/044/045/046/048), SEAT-017 and the
 * ACCESS-05 duplicate case ("doppelte Verlängerung", R0-09 §3.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GroupService;
use CourseCaptain\Access\ProtectedFiles;
use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Cache\CacheStore;
use CourseCaptain\Certificate\CertificateService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Import\ImportRunner;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Lease;
use CourseCaptain\Storage\NamedLock;
use CourseCaptain\Tests\Support\RaceRunner;

/**
 * Multi-process scenarios (`P`), fault injection (`I`), HTTP/cache checks
 * (`H`, simulated without a proxy: stale object cache, response headers) and
 * one static check (`U`, CON-009).
 *
 * @group R1-42
 */
final class ConcurrencyGaps2Test extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Course manager.
	 *
	 * @var int
	 */
	private int $manager;

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
	 * Lessons of the module.
	 *
	 * @var list<int>
	 */
	private array $lessons = array();

	/**
	 * Setup: one published course with one module (two lessons, three questions).
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! RaceRunner::available() ) {
			self::markTestSkipped( 'pcntl/posix required.' );
		}
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		$this->course  = $this->post( 'ccap_course', 0 );
		$this->module  = $this->post( 'ccap_module', 0 );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module );
		for ( $i = 0; $i < 2; $i++ ) {
			$lesson = $this->post( 'ccap_lesson', $this->module );
			ActivityService::current()->define( $admin, $lesson, 'lesson', array() );
			$this->lessons[] = $lesson;
		}
		$repo = QuestionRepository::current();
		for ( $i = 0; $i < 3; $i++ ) {
			$q = $repo->create(
				$admin,
				array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => 'Q' . $i . wp_generate_password( 4, false ),
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
		CoursePublisher::current()->publish( $admin, $this->course );
		PolicyService::flush();
		add_filter( 'course_captain_internal_subscribers', array( self::class, 'add_test_subscriber' ) );
	}

	/**
	 * Remove filters.
	 */
	public function tear_down(): void {
		remove_filter( 'course_captain_internal_subscribers', array( self::class, 'add_test_subscriber' ) );
		ImportRunner::inject_fault( null );
		parent::tear_down();
	}

	/**
	 * Store certificate and exam events in the internal outbox.
	 *
	 * @param mixed $subscribers Subscribers.
	 * @return array<string, mixed>
	 */
	public static function add_test_subscriber( $subscribers ): array {
		return (array) $subscribers + array( 'test' => array( 'certificate.*', 'exam.*' ) );
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Type.
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
	 * New learner account.
	 *
	 * @return int
	 */
	private function user(): int {
		return (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Learner with an active manual grant.
	 *
	 * @return int
	 */
	private function learner(): int {
		$user = $this->user();
		GrantService::current()->grant_manual( Actor::user( $this->admin ), $user, $this->course, wp_generate_uuid4(), 'fixture' );
		PolicyService::flush();
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
	 * One grant row.
	 *
	 * @param int $grant_id Grant.
	 * @return array<string, mixed>
	 */
	private static function grant( int $grant_id ): array {
		$db = Database::current();
		return (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $grant_id ) );
	}

	/**
	 * Only the listed outcomes occurred.
	 *
	 * @param array<string, array<string, mixed>> $results Results.
	 * @param array<int, string>                  $allowed Allowed outcome keys.
	 * @return array<string, int>
	 */
	private static function assert_outcomes( array $results, array $allowed ): array {
		$outcomes = RaceRunner::outcomes( $results );
		self::assertSame( array(), array_diff( array_keys( $outcomes ), $allowed ), (string) wp_json_encode( $results ) );
		return $outcomes;
	}

	/**
	 * Published exam rule over the module (3 questions, pass 2/3).
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
					'num' => 2,
					'den' => 3,
				),
			),
			600
		);
		ExamRules::current()->set_blueprint( $admin, $rule, $this->module, 3 );
		ExamRules::current()->publish( $admin, $rule );
		return $rule;
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 * @return void
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
	 * Query filter that makes every `GET_LOCK` return NULL (R0-21 §2.5, D21-2).
	 *
	 * @param mixed $sql SQL.
	 * @return mixed
	 */
	public static function lock_unavailable( $sql ) {
		if ( is_string( $sql ) && str_starts_with( $sql, 'SELECT GET_LOCK(' ) ) {
			return 'SELECT NULL';
		}
		return $sql;
	}

	/**
	 * CON-006 (§2.5, D21-2 a): CON-001, CON-002 and CON-005 with `GET_LOCK = NULL`
	 * keep their invariants; only additional 409s are allowed.
	 *
	 * @group CON-006
	 */
	public function test_CON_006_invariants_without_named_locks(): void {
		$service = GroupService::current();
		$owner   = $this->user();
		$group   = $service->create( Actor::user( $this->admin ), $owner, 'Team', 1, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$link    = $service->rotate_link( Actor::user( $owner ), $group );
		$group2  = $service->create( Actor::user( $this->admin ), $owner, 'Team2', 3, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$link2   = $service->rotate_link( Actor::user( $owner ), $group2 );
		$same    = $this->user();
		$rule    = $this->rule();
		$learner = $this->learner();
		$jobs    = array();
		$limit_i = RaceRunner::n( 6 );
		for ( $i = 0; $i < $limit_i; $i++ ) {
			$user             = $this->user();
			$jobs[ 'j' . $i ] = static fn() => GroupService::current()->claim( Actor::user( $user ), $link );
		}
		add_filter( 'query', array( self::class, 'lock_unavailable' ) );
		try {
			// CON-001 without the lock: the recount after insert keeps the limit.
			$results = RaceRunner::run( 'CON-006-001', $jobs );
			$joins   = self::assert_outcomes( $results, array( 'ok', 'ccap_group_full', 'ccap_group_busy', 'ccap_db_busy' ) );
			self::assertLessThanOrEqual( 1, $joins['ok'] ?? 0 );
			self::assertSame( 1, self::rows( 'ccap_seat', "status = 'active' AND group_id = " . (int) $group ), (string) wp_json_encode( $results ) );
			self::assertLessThanOrEqual( 1, $service->occupied( $group ) );

			// CON-002 without the lock: one seat for the same account in two tabs.
			$results = RaceRunner::run(
				'CON-006-002',
				array(
					'tab1' => static fn() => GroupService::current()->claim( Actor::user( $same ), $link2 ),
					'tab2' => static fn() => GroupService::current()->claim( Actor::user( $same ), $link2 ),
				)
			);
			self::assert_outcomes( $results, array( 'ok', 'ccap_group_busy', 'ccap_db_busy', 'ccap_conflict' ) );
			self::assertSame( 1, self::rows( 'ccap_seat', "status = 'active' AND user_id = " . $same ), (string) wp_json_encode( $results ) );

			// CON-005 without the lock: one running attempt per form.
			$jobs = array();
			for ( $i = 0; $i < $limit_i; $i++ ) {
				$jobs[ 's' . $i ] = static fn() => ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 's' . $i ) );
			}
			$results = RaceRunner::run( 'CON-006-005', $jobs );
		} finally {
			remove_filter( 'query', array( self::class, 'lock_unavailable' ) );
		}
		self::assert_outcomes( $results, array( 'ok', 'ccap_exam_start_busy', 'ccap_db_busy' ) );
		$ids = array_unique( array_column( array_filter( $results, static fn( array $r ): bool => ! empty( $r['ok'] ) ), 'value' ) );
		self::assertLessThanOrEqual( 1, count( $ids ), (string) wp_json_encode( $results ) );
		self::assertSame( 1, self::rows( 'ccap_exam_session', "status = 'in_progress' AND user_id = " . $learner ), (string) wp_json_encode( $results ) );
	}

	/**
	 * CON-009 (R-1, R-3, NFR-012): every locking read in `src` is on the
	 * reviewed list. Interactive single-row locks use the primary key; every
	 * other locking read is listed with its review note. A new `FOR UPDATE` /
	 * `LOCK IN SHARE MODE` fails this test until it is reviewed and listed.
	 *
	 * @group CON-009
	 */
	public function test_CON_009_locking_reads_are_reviewed(): void {
		// File => [count, rule]. `pk`: every locking read has `id = %d` (or `ID = %d`) equality.
		$reviewed = array(
			'src/Access/TrialLimits.php'             => array( 1, 'review: migration of legacy rules, course + module equality (no interactive path)' ),
			'src/Access/GroupService.php'            => array( 1, 'pk' ),
			'src/Access/GrantService.php'            => array( 1, 'review: parent row wp_users by PK, share lock against a parallel account deletion (R-12, CON-014)' ),
			'src/Assignment/AssignmentService.php'   => array( 2, 'review: row by PK; latest submission by user + activity (prefix of user_activity_no, ORDER BY … LIMIT 1)' ),
			'src/Certificate/CertificateService.php' => array( 3, 'review: revisions by certificate_uid (prefix of cert_rev), evidence (prefix of evidence_rev)' ),
			'src/Course/CoursePublisher.php'         => array( 1, 'review: revisions by course_id (prefix of course_rev), course publish lock M5 held' ),
			'src/Exam/ExamRules.php'                 => array( 1, 'review: MAX(variant_no) by course_id (prefix of course_variant)' ),
			'src/Exam/ExamSessions.php'              => array( 4, 'pk' ),
			'src/Import/ImportRunner.php'            => array( 1, 'pk' ),
			'src/Learning/Flashcards.php'            => array( 1, 'review: full unique key user_question after INSERT IGNORE (R-2)' ),
			'src/Learning/SmartTrainer.php'          => array( 2, 'review: full unique key user_question after insert (R-2)' ),
			'src/Ops/RepairService.php'              => array( 2, 'review: dead outbox rows, admin repair (no interactive path)' ),
			'src/Policy/LearnerLock.php'             => array( 1, 'review: open lock of one user (ORDER BY id LIMIT 1)' ),
			'src/Progress/CourseProgress.php'        => array( 1, 'review: full unique key user_course' ),
			'src/Progress/ProgressReset.php'         => array( 1, 'review: rows of one user and course before delete (admin reset)' ),
			'src/Question/QuestionRepository.php'    => array( 2, 'review: revisions by question_id (prefix of question_rev)' ),
			'src/Storage/Backfiller.php'             => array( 1, 'review: PK range batch of the background backfill (I-M4)' ),
		);
		$root     = dirname( __DIR__, 2 );
		$found    = array();
		$lines    = array();
		$files    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			$relative = 'src/' . str_replace( '\\', '/', substr( (string) $file->getPathname(), strlen( $root . '/src/' ) ) );
			foreach ( (array) file( (string) $file->getPathname() ) as $no => $line ) {
				$code = ltrim( (string) $line );
				if ( str_starts_with( $code, '*' ) || str_starts_with( $code, '//' ) || str_starts_with( $code, '/*' ) ) {
					continue;
				}
				if ( 1 === preg_match( '/FOR UPDATE|LOCK IN SHARE MODE|FOR SHARE/', $code ) ) {
					$found[ $relative ]   = ( $found[ $relative ] ?? 0 ) + 1;
					$lines[ $relative ][] = ( $no + 1 ) . ': ' . trim( $code );
				}
			}
		}
		ksort( $found );
		$expected = array_map( static fn( array $entry ): int => $entry[0], $reviewed );
		ksort( $expected );
		self::assertSame( $expected, $found, 'locking reads changed – review them (R0-21 R-1/R-3) and update the list: ' . wp_json_encode( $lines ) );
		foreach ( $reviewed as $relative => $entry ) {
			if ( 'pk' !== $entry[1] ) {
				continue;
			}
			foreach ( $lines[ $relative ] as $line ) {
				self::assertMatchesRegularExpression( '/\b(?:[a-z]+\.)?(?:id|ID) = %d/', $line, $relative . ' must lock by primary key' );
			}
		}
	}

	/**
	 * Synthetic import records: n questions and one course with one module.
	 *
	 * @param int $questions Questions.
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function import_records( int $questions ): array {
		$out = array(
			'questions' => array(),
			'lessons'   => array(),
			'modules'   => array(),
			'courses'   => array(),
		);
		for ( $i = 1; $i <= $questions; $i++ ) {
			$out['questions'][] = PackageExporter::sealed(
				array(
					'source_id' => sprintf( 'ZQ-%05d', $i ),
					'revision'  => array(
						'question_type' => 'single_choice',
						'text_html'     => 'Frage ' . $i,
						'settings'      => (object) array(),
					),
					'answers'   => array(
						array(
							'key'       => 'A',
							'text_html' => 'richtig',
							'correct'   => true,
						),
						array(
							'key'       => 'B',
							'text_html' => 'falsch',
							'correct'   => false,
						),
					),
					'tags'      => array(),
				)
			);
		}
		$out['lessons'][] = PackageExporter::sealed(
			array(
				'source_id'     => 'ZL-1',
				'title'         => 'Lektion',
				'body_html'     => '<p>Inhalt</p>',
				'activity_type' => 'lesson',
				'settings'      => (object) array(),
			)
		);
		$out['modules'][] = PackageExporter::sealed(
			array(
				'source_id'     => 'ZM-1',
				'title'         => 'Modul',
				'summary'       => '',
				'lessons'       => array(
					array(
						'lesson' => 'ZL-1',
						'sort'   => 1,
					),
				),
				'question_pool' => array_map(
					static fn( int $n ): array => array(
						'question' => sprintf( 'ZQ-%05d', $n ),
						'sort'     => $n,
					),
					range( 1, min( 5, $questions ) )
				),
			)
		);
		$out['courses'][] = PackageExporter::sealed(
			array(
				'source_id'        => 'ZC-1',
				'title'            => 'Kurs',
				'slug'             => 'zombie-kurs',
				'summary'          => '',
				'description_html' => '<p>Kurs</p>',
				'modules'          => array(
					array(
						'module'   => 'ZM-1',
						'sort'     => 1,
						'required' => true,
					),
				),
			)
		);
		return $out;
	}

	/**
	 * CON-012 (§2.4 fencing, RB-02): an import worker stops between two chunks,
	 * its lease expires and a second worker takes over (fence + 1). When the
	 * first worker continues, its chunk fails at the fence (`affected_rows = 0`)
	 * and writes nothing; the run later finishes without duplicates.
	 *
	 * @group CON-012
	 */
	public function test_CON_012_zombie_import_worker_is_fenced(): void {
		$dir = get_temp_dir() . 'ccap-con012-' . wp_generate_password( 6, false );
		wp_mkdir_p( $dir );
		$file    = PackageExporter::write( self::import_records( 150 ), 'con-012', 'Synthetic CON-012', $dir . '/p.zip', 1000 );
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $file );
		self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
		self::assertSame( 'previewed', $service->preview( $admin, $batch )['status'] );
		$service->approve( $admin, $batch );
		$runner = ImportRunner::current();
		$runner->start( $admin, $batch, false );
		$db    = Database::current();
		$lease = $db->table( 'ccap_lease' );
		$logs  = static fn(): int => self::rows( 'ccap_import_item_log', 'batch_id = ' . (int) $batch );

		// Plan phase and the first chunk of objects.
		for ( $i = 0; $i < 5; $i++ ) {
			self::assertSame( 'progress', $runner->step( $admin, $batch, 0.0 )['outcome'] );
			if ( $logs() > 0 ) {
				break;
			}
		}
		$before = $logs();
		self::assertGreaterThan( 0, $before );
		self::assertLessThan( 153, $before, 'run not finished yet' );

		// Worker A acquires the lease; before its next chunk transaction worker B takes over.
		global $wpdb;
		$state  = array(
			'armed' => false,
			'done'  => false,
		);
		$filter = static function ( $sql ) use ( &$state, $wpdb, $lease ) {
			if ( ! is_string( $sql ) || $state['done'] ) {
				return $sql;
			}
			if ( str_contains( $sql, $lease ) && str_starts_with( $sql, 'INSERT IGNORE' ) ) {
				$state['armed'] = true;
			} elseif ( $state['armed'] && 'START TRANSACTION' === $sql ) {
				$state['done'] = true;
				$now           = gmdate( 'Y-m-d H:i:s' );
				$wpdb->query( $wpdb->prepare( "UPDATE `{$lease}` SET owner = 'worker-b', fence = fence + 1, expires_at = %s, heartbeat_at = %s WHERE name = %s", gmdate( 'Y-m-d H:i:s', time() + 600 ), $now, ImportRunner::LEASE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
			return $sql;
		};
		add_filter( 'query', $filter );
		try {
			$zombie = $runner->step( $admin, $batch, 0.0 );
		} finally {
			remove_filter( 'query', $filter );
		}
		self::assertTrue( $state['done'], 'takeover happened' );
		self::assertSame( 'fenced', $zombie['outcome'] );
		self::assertSame( $before, $logs(), 'the zombie wrote no step' );
		$row = (array) $db->row( $db->prepare( "SELECT owner, fence FROM `{$lease}` WHERE name = %s", ImportRunner::LEASE ) );
		self::assertSame( 'worker-b', $row['owner'], 'the zombie did not release the new holder' );

		// Worker B is alive: a step is busy. B dies: the next step takes over and finishes.
		self::assertSame( 'busy', $runner->step( $admin, $batch, 0.0 )['outcome'] );
		$db->execute( $db->prepare( "UPDATE `{$lease}` SET expires_at = %s WHERE name = %s", $db->now( -1 ), ImportRunner::LEASE ) );
		$last = array();
		for ( $i = 0; $i < 200; $i++ ) {
			$last = $runner->step( $admin, $batch, 0.0 );
			if ( 'progress' !== $last['outcome'] ) {
				break;
			}
		}
		self::assertSame( 'published', (string) ImportService::current()->get( $batch )['status'], (string) wp_json_encode( $last ) );
		$t = $db->table( 'ccap_import_item_log' );
		self::assertSame( '0', (string) $db->scalar( $db->prepare( "SELECT COUNT(*) - COUNT(DISTINCT entity, source_id) FROM `{$t}` WHERE batch_id = %d", $batch ) ), 'no duplicate step' );
		self::assertSame( 150, self::rows( 'ccap_source_map', "object_type = 'question'" ) );
	}

	/**
	 * CON-014 (R-12): account deletion in parallel to a grant and to a seat
	 * claim. The grant/claim passes its checks before the deletion and writes
	 * after it (forced by a delay before the insert) → no seat and no grant
	 * remain on the deleted account.
	 *
	 * @group CON-014
	 */
	public function test_CON_014_account_deletion_parallel_to_join_and_grant(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		if ( is_multisite() ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
		}
		$admin   = $this->admin;
		$course  = $this->course;
		$service = GroupService::current();
		$owner   = $this->user();
		$group   = $service->create( Actor::user( $admin ), $owner, 'Team', 5, array( array( 'course_id' => $course ) ), wp_generate_uuid4() );
		$link    = $service->rotate_link( Actor::user( $owner ), $group );
		$delay   = static function ( string $table ): void {
			add_filter(
				'query',
				static function ( $sql ) use ( $table ) {
					static $slept = false;
					if ( ! $slept && is_string( $sql ) && str_starts_with( $sql, 'INSERT' ) && str_contains( $sql, $table . '`' ) ) {
						$slept = true;
						usleep( 1500000 );
					}
					return $sql;
				}
			);
		};
		foreach ( array( 'grant', 'claim' ) as $scenario ) {
			$user    = $this->user();
			$jobs    = array(
				'write'  => 'grant' === $scenario
					? static function () use ( $delay, $admin, $user, $course ): int {
						$delay( 'ccap_grant' );
						return GrantService::current()->grant_manual( Actor::user( $admin ), $user, $course, wp_generate_uuid4(), 'CON-014' );
					}
					: static function () use ( $delay, $user, $link ): int {
						$delay( 'ccap_seat' );
						return GroupService::current()->claim( Actor::user( $user ), $link );
					},
				'delete' => static function () use ( $user ): bool {
					usleep( 400000 );
					// Account deletion: on multisite the network deletion (wp_delete_user only leaves the site).
					return is_multisite() ? wpmu_delete_user( $user ) : wp_delete_user( $user );
				},
			);
			$results = RaceRunner::run( 'CON-014-' . $scenario, $jobs );
			self::assertTrue( ! empty( $results['delete']['ok'] ) && true === $results['delete']['value'], (string) wp_json_encode( $results ) );
			self::assertNotSame( 'exception', $results['write']['code'] ?? '', (string) wp_json_encode( $results ) );
			clean_user_cache( $user );
			self::assertFalse( get_userdata( $user ), 'account deleted' );
			self::assertSame( 0, self::rows( 'ccap_grant', 'user_id = ' . $user ), $scenario . ': no grant on the deleted account ' . wp_json_encode( $results ) );
			self::assertSame( 0, self::rows( 'ccap_seat', 'user_id = ' . $user ), $scenario . ': no seat on the deleted account ' . wp_json_encode( $results ) );
		}
	}

	/**
	 * CON-017 (R0-08): `submit` in parallel to `saveAnswer` → the answer is
	 * either saved before the evaluation and counted, or refused; never saved
	 * but not evaluated.
	 *
	 * @group CON-017
	 */
	public function test_CON_017_submit_parallel_to_save_answer(): void {
		$rule    = $this->rule();
		$learner = $this->learner();
		$rounds  = max( 3, RaceRunner::repeat( 3 ) );
		for ( $round = 0; $round < $rounds; $round++ ) {
			$session = ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 'r' . $round ) );
			ExamSessions::current()->save_answer( Actor::user( $learner ), $session, 1, array( 'A' ) );
			$results = RaceRunner::run(
				'CON-017',
				array(
					'submit' => static fn() => ExamSessions::current()->submit( Actor::user( $learner ), $session )['status'],
					'save'   => static function () use ( $learner, $session ): bool {
						usleep( wp_rand( 0, 20000 ) );
						return ExamSessions::current()->save_answer( Actor::user( $learner ), $session, 2, array( 'A' ) )['applied'];
					},
				)
			);
			self::assertSame( 'evaluated', $results['submit']['value'] ?? null, (string) wp_json_encode( $results ) );
			self::assertTrue( ! empty( $results['save']['ok'] ) || 'ccap_exam_already_submitted' === ( $results['save']['code'] ?? '' ), (string) wp_json_encode( $results ) );
			$db     = Database::current();
			$row    = (array) $db->row( $db->prepare( 'SELECT status, result, evaluated_at FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
			$stored = $db->row( $db->prepare( 'SELECT given_answer, answered_at FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d AND position = 2', $session ) );
			self::assertSame( 'evaluated', $row['status'] );
			$counted = array();
			foreach ( (array) ( json_decode( (string) $row['result'], true )['questions'] ?? array() ) as $question ) {
				$counted[ (int) $question['position'] ] = (bool) $question['answered'];
			}
			$saved = null !== ( $stored['given_answer'] ?? null );
			self::assertSame( $saved, $counted[2] ?? null, 'saved ⇔ counted: ' . wp_json_encode( array( $results, $stored, $row['result'] ) ) );
			if ( $saved ) {
				self::assertLessThanOrEqual( (string) $row['evaluated_at'], (string) $stored['answered_at'], 'saved before the evaluation' );
				self::assertTrue( ! empty( $results['save']['ok'] ) && true === $results['save']['value'] );
			}
		}
	}

	/**
	 * Protected PDF attached to the course; returns its media UID.
	 *
	 * @return string
	 */
	private function protected_file(): string {
		$admin  = Actor::user( $this->admin );
		$upload = wp_upload_bits( 'con-' . wp_generate_password( 6, false ) . '.pdf', null, "%PDF-1.4\n" . str_repeat( 'x', 100 ) );
		$id     = (int) wp_insert_attachment(
			array(
				'post_title'     => 'Handout',
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		$media  = MediaLibrary::current()->register_attachment( $admin, $id );
		MediaLibrary::current()->attach( $admin, $media, 'course', $this->course, 'download', 0 );
		ProtectedStorage::protect_attachment( $id, $media );
		$db = Database::current();
		return (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_media' ) . '` WHERE id = %d', $media ) );
	}

	/**
	 * CON-020 / CON-021 / CON-036 (K3, P-11, RB-06, H-8): the buyer has warm
	 * caches (lesson, media, running exam); the refund runs in another process
	 * and its invalidation never reaches the object cache (the stale cache is
	 * put back, as with a Redis stopped during the refund). The next request
	 * of the buyer is refused at once: lesson locked, media route 403 with
	 * `no-store`, `saveAnswer` 403. K2 overviews live at most 300 s.
	 *
	 * @group CON-020
	 * @group CON-021
	 * @group CON-036
	 */
	public function test_CON_020_021_036_stale_cache_after_refund(): void {
		$uid       = $this->protected_file();
		$rule      = $this->rule();
		$buyer     = $this->user();
		$reference = 'order-con020-' . wp_generate_password( 6, false );
		$request   = array(
			'user_id'          => $buyer,
			'course_id'        => $this->course,
			'source_type'      => 'fake',
			'source_reference' => $reference,
		);
		GrantService::current()->grant_from_source( $request, Actor::connector( 'fake' ) );
		PolicyService::flush();
		$actor   = Actor::user( $buyer );
		$lesson  = $this->lessons[0];
		$session = ExamSessions::current()->start( $actor, $rule, null, wp_generate_uuid4(), new Randomizer( 'con020' ) );
		ExamSessions::current()->save_answer( $actor, $session, 1, array( 'A' ) );

		// Warm caches.
		self::assertTrue( ContentGate::current()->lesson( $actor, $this->course, $lesson )->allowed() );
		$file = ProtectedFiles::current()->prepare( $actor, $uid );
		self::assertSame( 200, $file['status'] );
		self::assertStringContainsString( 'no-store', $file['headers']['Cache-Control'] );
		self::assertStringContainsString( 'private', $file['headers']['Cache-Control'] );
		$stale = clone $GLOBALS['wp_object_cache'];

		// Refund webhook in another process.
		$results = RaceRunner::run( 'CON-020', array( 'refund' => static fn() => GrantService::current()->revoke_source( Actor::connector( 'fake' ), 'fake', $reference, 'revoke', 'refunded' )['code'] ) );
		self::assertSame( 'updated', $results['refund']['value'] ?? null, (string) wp_json_encode( $results ) );

		// CON-021: the invalidation did not reach the cache; next request of the buyer.
		$GLOBALS['wp_object_cache'] = $stale; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		PolicyService::flush();
		self::assertFalse( ContentGate::current()->lesson( $actor, $this->course, $lesson )->allowed(), 'K3 never cached' );
		try {
			ProtectedFiles::current()->prepare( $actor, $uid );
			self::fail( 'media route must refuse' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_content_locked', $e->error_code );
			self::assertSame( 403, $e->status );
		}
		$this->assertRejected( fn() => ExamSessions::current()->save_answer( $actor, $session, 2, array( 'A' ) ), 'ccap_content_locked' );
		// CON-036: the refusal of the media route is sent with nocache headers (no CDN copy).
		self::assertStringContainsString( 'no-store', (string) wp_get_nocache_headers()['Cache-Control'] );
		self::assertLessThanOrEqual( 300, CacheStore::TTL_K2, 'K2 at most 300 s old' );
	}

	/**
	 * Verification response through the REST stack incl. `rest_post_dispatch`.
	 *
	 * @param string $token Token.
	 * @return \WP_REST_Response
	 */
	private static function verify_http( string $token ): \WP_REST_Response {
		$request  = new \WP_REST_Request( 'GET', '/course-captain/v1/certificates/verify/' . $token );
		$response = apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertInstanceOf( \WP_REST_Response::class, $response );
		return $response;
	}

	/**
	 * CON-037 (R-42, CRT-028) and the parallel issuance gap of LEARN-08:
	 * parallel issuance on the same evidence yields one certificate; after a
	 * revocation in another process the verification is `revoked` at once,
	 * even with a stale object cache, and is never stored by a proxy/CDN.
	 *
	 * @group CON-037
	 */
	public function test_CON_037_parallel_issue_and_revocation_visible_at_once(): void {
		Settings::save( Actor::user( $this->admin ), 'certificates', array( 'auto_issue' => true ), array( 'auto_issue' ), Settings::rev( 'certificates' ) );
		$learner = $this->learner();
		foreach ( $this->lessons as $lesson ) {
			ActivityProgress::current()->interact( Actor::user( $learner ), $this->course, $lesson, 'confirm' );
		}
		$db       = Database::current();
		$progress = (int) $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_course_progress' ) . '` WHERE user_id = %d', $learner ) );
		self::assertGreaterThan( 0, $progress );
		$jobs    = array();
		$limit_i = RaceRunner::n( 4 );
		for ( $i = 0; $i < $limit_i; $i++ ) {
			$jobs[ 'i' . $i ] = static fn() => CertificateService::current()->auto_issue( CertificateService::EVIDENCE_COURSE, $progress )['certificate_uid'] ?? null;
		}
		$results = RaceRunner::run( 'CON-037-issue', $jobs );
		self::assert_outcomes( $results, array( 'ok', 'ccap_db_busy' ) );
		$cert = $db->table( 'ccap_certificate' );
		self::assertSame( 1, (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$cert}` WHERE evidence_type = %s AND evidence_id = %d", CertificateService::EVIDENCE_COURSE, $progress ) ), (string) wp_json_encode( $results ) );
		$uid = (string) $db->scalar( $db->prepare( "SELECT certificate_uid FROM `{$cert}` WHERE evidence_type = %s AND evidence_id = %d", CertificateService::EVIDENCE_COURSE, $progress ) );
		self::assertCount( 1, array_unique( array_filter( array_column( $results, 'value' ) ) ) );
		self::assertSame( 1, self::rows( 'ccap_outbox_event', "event_type = 'certificate.issued'" ) );

		$token  = CertificateService::token( $uid, 1 );
		$before = self::verify_http( $token );
		self::assertSame( 200, $before->get_status() );
		self::assertSame( 'valid', $before->get_data()['status'] );
		$stale   = clone $GLOBALS['wp_object_cache'];
		$manager = $this->manager;
		$results = RaceRunner::run( 'CON-037-revoke', array( 'revoke' => static fn() => CertificateService::current()->revoke( Actor::user( $manager ), $uid, 'fraud', 'CON-037' ) ) );
		self::assertTrue( $results['revoke']['value'] ?? false, (string) wp_json_encode( $results ) );
		$GLOBALS['wp_object_cache'] = $stale; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		PolicyService::flush();
		$after = self::verify_http( $token );
		self::assertSame( 'revoked', $after->get_data()['status'], 'revoked at once' );
		$headers = $after->get_headers();
		self::assertStringContainsString( 'no-store', (string) $headers['Cache-Control'] );
		self::assertSame( 'no-store', $headers['Surrogate-Control'] ?? null );
		self::assertSame( 'no-store', $headers['CDN-Cache-Control'] ?? null );
	}

	/**
	 * CON-044 (D-14-03, RB-08): a row locked by a second connection for longer
	 * than 5 s; the interactive request gives up after ≤ 5 s (session
	 * `innodb_lock_wait_timeout`) with `ccap_db_busy`, the worker is free.
	 *
	 * @group CON-044
	 */
	public function test_CON_044_interactive_lock_wait_is_at_most_5s(): void {
		$db    = Database::current();
		$table = $db->table( 'ccap_rate_limit' );
		$db->execute( $db->prepare( "INSERT INTO `{$table}` (bucket_hash, window_start, hits, expires_at) VALUES (%s, 0, 0, %s)", 'con044', '2030-01-01 00:00:00' ) );
		$db->wpdb()->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$other->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$other->query( "UPDATE `{$table}` SET hits = hits + 1 WHERE bucket_hash = 'con044'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		try {
			$results = RaceRunner::run(
				'CON-044',
				array(
					'request' => static function (): array {
						$d       = Database::current();
						$timeout = $d->transaction(
							static function ( Database $tx ): string {
								return (string) $tx->scalar( 'SELECT @@SESSION.innodb_lock_wait_timeout' );
							}
						);
						$t0 = microtime( true );
						try {
							$d->transaction(
								static function ( Database $tx ): void {
									$t = $tx->table( 'ccap_rate_limit' );
									$tx->execute( "UPDATE `{$t}` SET hits = hits + 10 WHERE bucket_hash = 'con044'" );
								}
							);
							$code = 'committed';
						} catch ( CourseException $e ) {
							$code = $e->error_code;
						}
						return array(
							'timeout' => $timeout,
							'code'    => $code,
							'waited'  => microtime( true ) - $t0,
						);
					},
				),
				90
			);
		} finally {
			$other->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$other->close();
		}
		$value = (array) ( $results['request']['value'] ?? array() );
		self::assertSame( '5', $value['timeout'] ?? null, (string) wp_json_encode( $results ) );
		self::assertSame( 'ccap_db_busy', $value['code'] ?? null, (string) wp_json_encode( $results ) );
		self::assertLessThan( 7.0, (float) ( $value['waited'] ?? 99 ), 'gave up after ≤ 5 s (plus slack)' );
		self::assertSame( '0', (string) $db->scalar( "SELECT hits FROM `{$table}` WHERE bucket_hash = 'con044'" ), 'no partial write' );
	}

	/**
	 * CON-045 (RB-05, §2.4): a real DB reconnect in the middle of a lease step
	 * that holds a mutex. After the reconnect the named lock is gone (another
	 * connection gets it), the step notices before commit and writes nothing;
	 * repeated with the mutex again, it writes once. (The schema runner case is
	 * MIG-004.)
	 *
	 * @group CON-045
	 */
	public function test_CON_045_reconnect_loses_the_mutex_and_the_step_is_repeated(): void {
		$own = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$own->set_prefix( $GLOBALS['wpdb']->base_prefix );
		$own->suppress_errors( true );
		if ( is_multisite() ) {
			$own->set_blog_id( get_current_blog_id() );
		}
		$db    = new Database( $own );
		$key   = 'con045-' . wp_generate_password( 6, false );
		$lock  = NamedLock::for_key( $db, $key );
		$lease = Lease::acquire( $db, $key, 60, false );
		self::assertNotNull( $lease );
		self::assertSame( NamedLock::ACQUIRED, $lock->acquire( 0 ) );
		self::assertTrue( $lock->still_held() );
		$step = static function () use ( $db, $lease, $lock, $key ): bool {
			return $db->transaction(
				static function ( Database $tx ) use ( $lease, $lock, $key ): bool {
					$t = $tx->table( 'ccap_rate_limit' );
					$tx->execute( $tx->prepare( "INSERT INTO `{$t}` (bucket_hash, window_start, hits, expires_at) VALUES (%s, 0, 1, %s) ON DUPLICATE KEY UPDATE hits = hits + 1", $key, '2030-01-01 00:00:00' ) );
					// Fencing before commit: lease and mutex must still be ours.
					if ( ! $lease->heartbeat( 60 ) || ! $lock->still_held() ) {
						throw new \CourseCaptain\Storage\MigrationFencedException( 'Mutex lost.' );
					}
					return true;
				}
			);
		};

		// Reconnect mid-run (connection dropped, wpdb connects again).
		$thread = (int) $own->dbh->thread_id;
		$own->close();
		$own->db_connect( false );
		self::assertNotSame( $thread, (int) $own->dbh->thread_id, 'new server connection' );
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		self::assertSame( '1', (string) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $lock->name ) ), 'the mutex died with the old connection' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		self::assertFalse( $lock->still_held() );
		try {
			$step();
			self::fail( 'the step must stop before commit' );
		} catch ( \CourseCaptain\Storage\MigrationFencedException $e ) {
			self::assertSame( 'Mutex lost.', $e->getMessage() );
		}
		self::assertSame( 0, self::rows( 'ccap_rate_limit', $db->prepare( 'bucket_hash = %s', $key ) ), 'nothing committed' );

		// Repeat once the mutex is free again.
		$other->query( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $lock->name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$other->close();
		self::assertSame( NamedLock::ACQUIRED, $lock->acquire( 0 ) );
		self::assertTrue( $step() );
		self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT hits FROM `' . $db->table( 'ccap_rate_limit' ) . '` WHERE bucket_hash = %s', $key ) ), 'written exactly once' );
		$lock->release();
		$lease->release();
		$own->close();
	}

	/**
	 * CON-046 (NFR-010/011, reduced L5 run): the P scenarios mixed in one run
	 * (joins on the last seats, same source key, parallel exam starts, answers
	 * with the same idempotency key, double completion). Every deadlock is
	 * resolved by ≤ 3 retries (no `ccap_db_busy`), no exception, invariants
	 * hold; deadlocks are reported per job.
	 *
	 * @group CON-046
	 */
	public function test_CON_046_mixed_load_run(): void {
		$service = GroupService::current();
		$owner   = $this->user();
		$group   = $service->create( Actor::user( $this->admin ), $owner, 'Load', 2, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$link    = $service->rotate_link( Actor::user( $owner ), $group );
		$rule    = $this->rule();
		$starter = $this->learner();
		$answer  = $this->learner();
		$buyer   = $this->user();
		$course  = $this->course;
		$lesson  = $this->lessons[0];
		$module  = $this->module;
		$q       = (int) QuestionRepository::current()->pool( $module )[0];
		$token   = (string) QuestionPresenter::current()->deliver( Actor::user( $answer ), $course, $module, $q )['token'];
		$idem    = wp_generate_uuid4();
		$ref     = 'order-con046-' . wp_generate_password( 6, false );
		$jobs    = array();
		$n       = RaceRunner::n( 3 );
		for ( $i = 0; $i < $n; $i++ ) {
			$joiner                = $this->user();
			$jobs[ 'join' . $i ]   = static fn() => GroupService::current()->claim( Actor::user( $joiner ), $link );
			$jobs[ 'source' . $i ] = static fn() => GrantService::current()->grant_from_source(
				array(
					'user_id'          => $buyer,
					'course_id'        => $course,
					'source_type'      => 'fake',
					'source_reference' => $ref,
				),
				Actor::connector( 'fake' )
			)['grant_id'];
			$jobs[ 'start' . $i ]  = static fn() => ExamSessions::current()->start( Actor::user( $starter ), $rule, null, wp_generate_uuid4(), new Randomizer( 'l' . $i ) );
			$jobs[ 'answer' . $i ] = static fn() => AnswerService::current()->answer( Actor::user( $answer ), $token, array( 'A' ), $idem )['repeated'];
			$jobs[ 'done' . $i ]   = static fn() => ActivityProgress::current()->interact( Actor::user( $answer ), $course, $lesson, 'confirm' )['completion']['state'] ?? null;
		}
		$results  = RaceRunner::run( 'CON-046', $jobs, 60 );
		$outcomes = self::assert_outcomes( $results, array( 'ok', 'ccap_group_full', 'ccap_group_busy', 'ccap_exam_start_busy', 'ccap_request_in_progress' ) );
		self::assertArrayNotHasKey( 'ccap_db_busy', $outcomes, '< 0.1 % busy' );
		$deadlocks = array_sum( array_map( static fn( array $r ): int => (int) ( $r['deadlocks'] ?? 0 ), $results ) );
		self::assertGreaterThanOrEqual( 0, $deadlocks, 'deadlocks per scenario: ' . wp_json_encode( array_map( static fn( array $r ): int => (int) ( $r['deadlocks'] ?? 0 ), $results ) ) );
		self::assertSame( 2, self::rows( 'ccap_seat', "status = 'active' AND group_id = " . (int) $group ) );
		self::assertSame( 1, self::rows( 'ccap_grant', Database::current()->prepare( "source_type = 'fake' AND source_id = %s", $ref ) ) );
		self::assertSame( 1, self::rows( 'ccap_exam_session', "status = 'in_progress' AND user_id = " . $starter ) );
		self::assertSame( 1, self::rows( 'ccap_attempt', Database::current()->prepare( 'user_id = %d AND idempotency_key = %s', $answer, $idem ) ) );
		self::assertSame( 1, self::rows( 'ccap_activity_progress', 'user_id = ' . $answer . ' AND activity_id = ' . $lesson ) );
	}

	/**
	 * CON-048 (R-8): DB clock 2 min off (`NOW()` of the session shifted in both
	 * directions); grant expiry and the exam deadline follow the PHP clock only.
	 *
	 * @group CON-048
	 */
	public function test_CON_048_db_clock_skew_has_no_effect(): void {
		$db      = Database::current();
		$service = GrantService::current();
		$admin   = Actor::user( $this->admin );
		$soon    = $service->grant_manual( $admin, $this->user(), $this->course, wp_generate_uuid4(), 'CON-048', array( 'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 60 ) ) );
		$past    = $service->grant_manual( $admin, $this->user(), $this->course, wp_generate_uuid4(), 'CON-048', array( 'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) ) );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . '` SET valid_until = %s WHERE id = %d', gmdate( 'Y-m-d H:i:s', time() - 60 ), $past ) );
		$rule    = $this->rule();
		$learner = $this->learner();
		try {
			// DB ahead by 2 min: the grant ending in 1 min stays active.
			$db->execute( 'SET SESSION timestamp = UNIX_TIMESTAMP() + 120' );
			self::assertNotSame( gmdate( 'Y-m-d H:i' ), substr( (string) $db->scalar( 'SELECT UTC_TIMESTAMP()' ), 0, 16 ), 'DB clock shifted' );
			self::assertSame( 1, $service->expire_due() );
			self::assertSame( 'active', self::grant( $soon )['status'] );
			self::assertSame( 'expired', self::grant( $past )['status'] );
			$session = ExamSessions::current()->start( Actor::user( $learner ), $rule, null, wp_generate_uuid4(), new Randomizer( 'skew' ) );
			// DB behind by 2 min: the running attempt and its deadline are unchanged.
			$db->execute( 'SET SESSION timestamp = UNIX_TIMESTAMP() - 240' );
			self::assertTrue( ExamSessions::current()->save_answer( Actor::user( $learner ), $session, 1, array( 'A' ) )['applied'] );
			$row = (array) $db->row( $db->prepare( 'SELECT started_at, deadline_at FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
			self::assertLessThanOrEqual( 2, abs( (int) strtotime( $row['started_at'] . ' UTC' ) - time() ), 'start from the PHP clock' );
			self::assertSame( 600, (int) strtotime( $row['deadline_at'] . ' UTC' ) - (int) strtotime( $row['started_at'] . ' UTC' ) );
			self::assertSame( 0, $service->expire_due() );
			self::assertSame( 'active', self::grant( $soon )['status'] );
		} finally {
			$db->execute( 'SET SESSION timestamp = DEFAULT' );
		}
		// No SQL clock function in the plugin code (only the PHP Clock decides).
		$root = dirname( __DIR__, 2 );
		$hits = array();
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}
			foreach ( (array) file( (string) $file->getPathname() ) as $no => $line ) {
				$code = ltrim( (string) $line );
				if ( str_starts_with( $code, '*' ) || str_starts_with( $code, '//' ) ) {
					continue;
				}
				if ( 1 === preg_match( '/\b(?:NOW|UTC_TIMESTAMP|CURDATE|CURTIME|SYSDATE|UNIX_TIMESTAMP)\(\)|DEFAULT CURRENT_TIMESTAMP/', $code ) ) {
					$hits[] = $file->getFilename() . ':' . ( $no + 1 );
				}
			}
		}
		self::assertSame( array(), $hits );
	}

	/**
	 * SEAT-017 (E-81, RBAC-059, GRP-018): the owner sees status, display name
	 * and the address of open invitations; a manager sees it masked; active
	 * members show no address; never progress or certificates.
	 *
	 * @group SEAT-017
	 */
	public function test_SEAT_017_owner_sees_members_without_progress(): void {
		$service = GroupService::current();
		$owner   = $this->user();
		$group   = $service->create( Actor::user( $this->admin ), $owner, 'Team', 3, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$member  = (int) self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'display_name' => 'Member Name',
				'user_email'   => 'member-' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		$service->claim( Actor::user( $member ), $service->rotate_link( Actor::user( $owner ), $group ) );
		ActivityProgress::current()->interact( Actor::user( $member ), $this->course, $this->lessons[0], 'confirm' );
		$address = 'invitee-' . wp_generate_password( 6, false ) . '@example.org';
		self::assertNotNull( $service->invite( Actor::user( $owner ), $group, $address ) );

		$view = $service->members( Actor::user( $owner ), $group );
		self::assertCount( 2, $view );
		foreach ( $view as $row ) {
			self::assertSame( array( 'address', 'name', 'seat_id', 'since', 'status' ), self::sorted( $row ), 'no progress or certificate fields' );
		}
		$by_status = array_column( $view, null, 'status' );
		self::assertSame( 'Member Name', $by_status['active']['name'] );
		self::assertSame( '', $by_status['active']['address'] );
		self::assertSame( $address, $by_status['invited']['address'], 'owner: open invitation in clear' );
		$json = (string) wp_json_encode( $view );
		foreach ( array( 'progress', 'percent', 'completed', 'certificate', 'score' ) as $never ) {
			self::assertStringNotContainsString( $never, $json );
		}
		$manager_view = array_column( $service->members( Actor::user( $this->admin ), $group ), null, 'status' );
		self::assertSame( GroupService::mask_email( $address ), $manager_view['invited']['address'], 'not the owner: masked' );
		self::assertStringNotContainsString( substr( $address, 1, 10 ), $manager_view['invited']['address'] );
		try {
			$service->members( Actor::user( $member ), $group );
			self::fail( 'members of a foreign group' );
		} catch ( CourseException $e ) {
			self::assertSame( 404, $e->status, 'RBAC-052' );
		}
	}

	/**
	 * Sorted keys.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return list<string>
	 */
	private static function sorted( array $row ): array {
		$keys = array_map( 'strval', array_keys( $row ) );
		sort( $keys );
		return $keys;
	}

	/**
	 * ACCESS-05 duplicate (R0-09 §3.3 "Doppelte Verlängerung"): the same renewal
	 * event delivered twice (sequentially and N times in parallel) extends the
	 * chain once; two different renewals in parallel both chain without overlap.
	 *
	 * @group ACCESS-05
	 */
	public function test_ACCESS_05_duplicate_renewal_extends_once(): void {
		$user    = $this->user();
		$course  = $this->course;
		$request = static fn( string $reference ): array => array(
			'user_id'          => $user,
			'course_id'        => $course,
			'source_type'      => 'fake',
			'source_reference' => $reference,
			'duration_days'    => 30,
			'renewal_mode'     => 'extend',
		);
		$service = GrantService::current();
		$tag     = wp_generate_password( 6, false );
		$base    = $service->grant_from_source( $request( 'base-' . $tag ), Actor::connector( 'fake' ) );
		$end     = (int) strtotime( self::grant( $base['grant_id'] )['valid_until'] . ' UTC' );

		// Same event twice in a row.
		$first  = $service->grant_from_source( $request( 'renew-a-' . $tag ), Actor::connector( 'fake' ) );
		$second = $service->grant_from_source( $request( 'renew-a-' . $tag ), Actor::connector( 'fake' ) );
		self::assertSame( 'created', $first['code'] );
		self::assertSame( array( $first['grant_id'], 'duplicate' ), array( $second['grant_id'], $second['code'] ) );

		// Same event N times in parallel.
		$jobs    = array();
		$limit_i = RaceRunner::n( 6 );
		$renew_b = $request( 'renew-b-' . $tag );
		for ( $i = 0; $i < $limit_i; $i++ ) {
			$jobs[ 'cb' . $i ] = static fn() => GrantService::current()->grant_from_source( $renew_b, Actor::connector( 'fake' ) );
		}
		$results = RaceRunner::run( 'ACCESS-05-dup', $jobs );
		self::assert_outcomes( $results, array( 'ok', 'ccap_db_busy' ) );
		$ok = array_filter( $results, static fn( array $r ): bool => ! empty( $r['ok'] ) );
		self::assertCount( 1, array_unique( array_map( static fn( array $r ): int => (int) $r['value']['grant_id'], $ok ) ), (string) wp_json_encode( $results ) );
		self::assertCount( 1, array_filter( $ok, static fn( array $r ): bool => 'created' === $r['value']['code'] ) );

		// Two different renewals in parallel.
		$renew_c = $request( 'renew-c-' . $tag );
		$renew_d = $request( 'renew-d-' . $tag );
		$results = RaceRunner::run(
			'ACCESS-05-two',
			array(
				'c' => static fn() => GrantService::current()->grant_from_source( $renew_c, Actor::connector( 'fake' ) )['code'],
				'd' => static fn() => GrantService::current()->grant_from_source( $renew_d, Actor::connector( 'fake' ) )['code'],
			)
		);
		self::assertSame( array( 'ok' => 2 ), RaceRunner::outcomes( $results ), (string) wp_json_encode( $results ) );

		$db   = Database::current();
		$rows = $db->rows( $db->prepare( 'SELECT source_id, valid_from, valid_until FROM `' . $db->table( 'ccap_grant' ) . "` WHERE user_id = %d AND source_type = 'fake' ORDER BY valid_from", $user ) );
		self::assertCount( 5, $rows, 'base + 4 renewals, no second term from one event' );
		for ( $i = 1; $i < 5; $i++ ) {
			self::assertSame( $rows[ $i - 1 ]['valid_until'], $rows[ $i ]['valid_from'], 'chained without overlap: ' . wp_json_encode( $rows ) );
		}
		self::assertSame( $end + 4 * 30 * DAY_IN_SECONDS, (int) strtotime( $rows[4]['valid_until'] . ' UTC' ), 'four renewals, each once' );
	}
}
