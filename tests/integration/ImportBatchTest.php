<?php
/**
 * R1-41: large imports in budgeted batches, resume, error report (R0-38 BIG-xxx).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Import\ImportReport;
use CourseCaptain\Import\ImportRunner;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\StorageException;

/**
 * Real tables, synthetic neutral packages (E-02).
 *
 * @group R1-41
 */
final class ImportBatchTest extends SchemaTestCase {

	private const SYSTEM = 'big-a';

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Scratch directory.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->dir   = get_temp_dir() . 'ccap-big-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->dir );
		PolicyService::flush();
	}

	/**
	 * Remove the fault hook.
	 */
	public function tear_down(): void {
		ImportRunner::inject_fault( null );
		parent::tear_down();
	}

	/**
	 * Synthetic records: n questions, one lesson per course, one module per
	 * course with the first questions in its pool.
	 *
	 * @param int                  $questions Questions.
	 * @param int                  $courses   Courses.
	 * @param array<string, mixed> $options   `empty_module` (course index with an empty module), `text` (question text suffix), `bad_hash` (question source IDs).
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function records( int $questions, int $courses = 1, array $options = array() ): array {
		$out = array(
			'questions' => array(),
			'lessons'   => array(),
			'modules'   => array(),
			'courses'   => array(),
		);
		for ( $i = 1; $i <= $questions; $i++ ) {
			$id     = sprintf( 'BQ-%05d', $i );
			$record = PackageExporter::sealed(
				array(
					'source_id' => $id,
					'revision'  => array(
						'question_type' => 'single_choice',
						'text_html'     => 'Frage ' . $i . ' über Äpfel' . (string) ( $options['text'] ?? '' ),
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
			if ( in_array( $id, (array) ( $options['bad_hash'] ?? array() ), true ) ) {
				$record['hash'] = 'sha256:' . str_repeat( '0', 64 );
			}
			$out['questions'][] = $record;
		}
		for ( $c = 1; $c <= $courses; $c++ ) {
			$empty            = ( $options['empty_module'] ?? 0 ) === $c;
			$out['lessons'][] = PackageExporter::sealed(
				array(
					'source_id'     => 'BL-' . $c,
					'title'         => 'Lektion ' . $c,
					'body_html'     => '<p>Inhalt ' . $c . '</p>',
					'activity_type' => 'lesson',
					'settings'      => (object) array(),
				)
			);
			$out['modules'][] = PackageExporter::sealed(
				array(
					'source_id'     => 'BM-' . $c,
					'title'         => 'Modul ' . $c,
					'summary'       => '',
					'lessons'       => $empty ? array() : array(
						array(
							'lesson' => 'BL-' . $c,
							'sort'   => 1,
						),
					),
					'question_pool' => $empty ? array() : array_map(
						static fn( int $n ): array => array(
							'question' => sprintf( 'BQ-%05d', $n ),
							'sort'     => $n,
						),
						range( 1, min( 5, max( 1, $questions ) ) )
					),
				)
			);
			$out['courses'][] = PackageExporter::sealed(
				array(
					'source_id'        => 'BC-' . $c,
					'title'            => 'Kurs ' . $c,
					'slug'             => 'big-kurs-' . $c,
					'summary'          => '',
					'description_html' => '<p>Kurs</p>',
					'modules'          => array(
						array(
							'module'   => 'BM-' . $c,
							'sort'     => 1,
							'required' => true,
						),
					),
				)
			);
		}
		return $out;
	}

	/**
	 * Write a package (1 000 records per data file).
	 *
	 * @param array<string, list<array<string, mixed>>> $records Records.
	 * @param string                                    $name    File name.
	 * @return string
	 */
	private function package( array $records, string $name ): string {
		return PackageExporter::write( $records, self::SYSTEM, 'Synthetic ' . $name, $this->dir . '/' . $name . '.zip', 1000 );
	}

	/**
	 * Upload, validate, dry run, approve; returns the batch.
	 *
	 * @param string $file Package.
	 * @return int
	 */
	private function approved( string $file ): int {
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $file );
		self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
		self::assertSame( 'previewed', $service->preview( $admin, $batch )['status'] );
		$service->approve( $admin, $batch );
		return $batch;
	}

	/**
	 * Steps with a zero budget (exactly one chunk per step) until the run stops.
	 *
	 * @param int $batch Batch.
	 * @param int $max   Maximum steps.
	 * @return list<array<string, mixed>>
	 */
	private function steps( int $batch, int $max = 500 ): array {
		$runner = ImportRunner::current();
		$out    = array();
		for ( $i = 0; $i < $max; $i++ ) {
			$progress = $runner->step( Actor::user( $this->admin ), $batch, 0.0 );
			$out[]    = $progress;
			if ( 'progress' !== $progress['outcome'] ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Row counts of the content tables.
	 *
	 * @return array<string, int>
	 */
	private static function counts(): array {
		$db  = Database::current();
		$out = array();
		foreach ( array( 'ccap_question', 'ccap_question_revision', 'ccap_answer', 'ccap_source_map', 'ccap_course_module', 'ccap_module_question', 'ccap_course_revision' ) as $table ) {
			$out[ $table ] = (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '`' );
		}
		$out['posts'] = (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->wpdb()->posts . "` WHERE post_type LIKE 'ccap\\_%'" );
		return $out;
	}

	/**
	 * Object ID of a source ID.
	 *
	 * @param string $type   Type.
	 * @param string $source Source.
	 * @return int
	 */
	private static function object( string $type, string $source ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT object_id FROM `' . $db->table( 'ccap_source_map' ) . '` WHERE source_system = %s AND object_type = %s AND source_id = %s', self::SYSTEM, $type, $source ) );
	}

	/**
	 * Scalar helper.
	 *
	 * @param string $sql SQL with `{t}` for the table.
	 * @param string $table Table.
	 * @param mixed  ...$args Arguments.
	 * @return int
	 */
	private static function num( string $sql, string $table, ...$args ): int {
		$db  = Database::current();
		$sql = str_replace( '{t}', '`' . $db->table( $table ) . '`', $sql );
		return (int) $db->scalar( array() === $args ? $sql : $db->prepare( $sql, ...$args ) );
	}

	/**
	 * BIG-001 (scaled to BIG-P0/2): dry run of 5 000 questions in several data files:
	 * statistics and action matrix, nothing written.
	 *
	 * @group BIG-001
	 */
	public function test_BIG_001_dry_run_statistics_of_a_large_package(): void {
		$file    = $this->package( self::records( 5000 ), 'p5000' );
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $file );
		$service->validate( $admin, $batch );
		$before  = self::counts();
		$preview = $service->preview( $admin, $batch );
		self::assertSame( 'previewed', $preview['status'] );
		self::assertSame( array( 'create' => 5000 ), $preview['plan']['questions'] );
		self::assertSame( array( 'create' => 1 ), $preview['plan']['courses'] );
		self::assertSame( 5003, $preview['stats']['records'] );
		self::assertSame( 8, $preview['stats']['files'], '5 question files + lesson, module, course' );
		self::assertSame( 0, $preview['stats']['object_errors'] );
		self::assertSame( 101, $preview['stats']['threshold'], 'max(100, ceil(2 % of 5 003))' );
		self::assertGreaterThan( 0, $preview['stats']['records_per_s'] );
		self::assertGreaterThan( 0, $preview['stats']['estimate_commit'] );
		self::assertSame( $before, self::counts(), 'dry run writes no content (IMP-017)' );
		$stats = (array) json_decode( (string) $service->get( $batch )['stats'], true );
		self::assertSame( 5003, $stats['dry_run']['records'] );
	}

	/**
	 * BIG-020/BIG-022 + BIG-011: chunked commit of 1 500 questions over many
	 * budgeted steps (monotonic progress, adaptive chunk size), activation,
	 * then the same package again creates nothing.
	 *
	 * @group BIG-011
	 * @group BIG-020
	 * @group BIG-022
	 * @group GM-125
	 */
	public function test_BIG_020_chunked_commit_and_idempotent_reimport(): void {
		$file  = $this->package( self::records( 1500 ), 'p1500' );
		$batch = $this->approved( $file );
		$admin = Actor::user( $this->admin );
		$start = ImportRunner::current()->start( $admin, $batch, false );
		self::assertSame( 'importing', $start['status'] );
		$steps = $this->steps( $batch );
		$last  = end( $steps );
		self::assertSame( 'published', $last['status'] );
		self::assertGreaterThan( 5, count( $steps ), 'one chunk per step with a zero budget' );
		$done   = array_column( $steps, 'done' );
		$sorted = $done;
		sort( $sorted );
		self::assertSame( $sorted, $done, 'progress is monotonic' );
		self::assertSame( 100, $last['percent'] );
		self::assertGreaterThan( ImportRunner::CHUNK_START, max( array_column( $steps, 'chunk_size' ) ), 'chunk size grows when chunks are fast (AIMD)' );
		self::assertLessThanOrEqual( ImportRunner::CHUNK_MAX, max( array_column( $steps, 'chunk_size' ) ) );
		self::assertSame( 1500, self::num( "SELECT COUNT(*) FROM {t} WHERE status = 'published'", 'ccap_question' ) );
		self::assertSame( 'publish', get_post_status( self::object( 'course', 'BC-1' ) ) );
		self::assertSame( 1503, self::num( 'SELECT COUNT(*) FROM {t} WHERE batch_id = %d', 'ccap_import_item_log', $batch ) );
		self::assertSame( 0, self::num( 'SELECT COUNT(*) FROM (SELECT source_id FROM {t} GROUP BY source_system, object_type, source_id HAVING COUNT(*) > 1) d', 'ccap_source_map' ) );
		$stats = (array) json_decode( (string) ImportService::current()->get( $batch )['stats'], true );
		self::assertSame( 0, $stats['failed_objects'] );
		self::assertSame( array(), $stats['held_courses'] );

		// BIG-011: second import of the same package.
		$before  = self::counts();
		$service = ImportService::current();
		$again   = $service->upload( $admin, $file );
		$service->validate( $admin, $again );
		$plan = $service->preview( $admin, $again )['plan'];
		self::assertSame( array( 'unchanged' => 1500 ), $plan['questions'] );
		$service->approve( $admin, $again );
		$summary = $service->run( $admin, $again );
		self::assertSame( array( 'unchanged' => 1500 ), $summary['questions'] );
		self::assertSame( $before, self::counts(), '0 new rows in content tables (NFR-006)' );
		self::assertSame( 'superseded', $service->get( $batch )['status'] );
	}

	/**
	 * BIG-023/BIG-024/BIG-027: crash inside a chunk, live and expired foreign
	 * leases, resume repeats at most one chunk without duplicates.
	 *
	 * @group BIG-023
	 * @group BIG-024
	 * @group BIG-027
	 * @group GM-136
	 */
	public function test_BIG_023_crash_resume_and_lease(): void {
		$batch  = $this->approved( $this->package( self::records( 300 ), 'p300' ) );
		$admin  = Actor::user( $this->admin );
		$runner = ImportRunner::current();
		$runner->start( $admin, $batch, false );
		$db    = Database::current();
		$lease = $db->table( 'ccap_lease' );

		// BIG-027: a live lease of another worker → busy, nothing written.
		$db->execute( $db->prepare( "INSERT INTO `{$lease}` (name, owner, fence, expires_at, heartbeat_at, created_at) VALUES ('import', 'other-worker', 5, %s, %s, %s)", $db->now( 600 ), $db->now(), $db->now() ) );
		self::assertSame( 'busy', $runner->step( $admin, $batch, 0.0 )['outcome'] );
		self::assertSame( 0, self::num( 'SELECT COUNT(*) FROM {t} WHERE batch_id = %d', 'ccap_import_item_log', $batch ) );
		// BIG-024: expired lease (dead worker) is taken over with a higher fence.
		$db->execute( $db->prepare( "UPDATE `{$lease}` SET expires_at = %s WHERE name = 'import'", $db->now( -1 ) ) );
		self::assertSame( 'progress', $runner->step( $admin, $batch, 0.0 )['outcome'], 'plan phase' );

		ImportRunner::inject_fault(
			static function ( string $point, int $position ): void {
				if ( 'apply' === $point && 120 === $position ) {
					throw new \RuntimeException( 'simulated crash' );
				}
			}
		);
		$this->steps( $batch );
		$row = (array) ImportService::current()->get( $batch );
		self::assertSame( 'aborted', $row['status'] );
		$run    = ImportRunner::run_state( $row );
		$logged = self::num( 'SELECT COUNT(*) FROM {t} WHERE batch_id = %d', 'ccap_import_item_log', $batch );
		self::assertLessThanOrEqual( 120, $run['line'] );
		self::assertSame( (int) $run['line'], $logged, 'cursor and written objects agree (exactly-once per chunk)' );
		self::assertSame( $logged, self::num( "SELECT COUNT(*) FROM {t} WHERE object_type = 'question'", 'ccap_source_map' ), 'crashed chunk left nothing behind' );

		ImportRunner::inject_fault( null );
		$runner->resume( $admin, $batch, false );
		$steps = $this->steps( $batch );
		self::assertSame( 'published', end( $steps )['status'] );
		self::assertSame( 300, self::num( "SELECT COUNT(*) FROM {t} WHERE object_type = 'question'", 'ccap_source_map' ) );
		self::assertSame( 300, self::num( 'SELECT COUNT(*) FROM {t}', 'ccap_question' ), '0 duplicates' );
		self::assertSame( 303, self::num( 'SELECT COUNT(*) FROM {t} WHERE batch_id = %d', 'ccap_import_item_log', $batch ) );
		self::assertSame( 1, self::num( "SELECT COUNT(*) FROM {t} WHERE action = 'import_resumed'", 'ccap_audit_log' ) );
	}

	/**
	 * GM-136 (scaled PKG-LARGE): 600 instead of 20 000 synthetic questions,
	 * 3 of them defective; the run is aborted at 50 % and resumed: 597
	 * imported, the report has exactly the 3 defective entries, no duplicates.
	 *
	 * @group R1-18
	 * @group GM-136
	 */
	public function test_GM_136_defects_abort_at_half_and_resume(): void {
		$bad    = array( 'BQ-00100', 'BQ-00250', 'BQ-00480' );
		$batch  = $this->approved( $this->package( self::records( 600, 1, array( 'bad_hash' => $bad ) ), 'large' ) );
		$admin  = Actor::user( $this->admin );
		$issues = static fn(): array => Database::current()->wpdb()->get_col( Database::current()->prepare( 'SELECT source_id FROM `' . Database::current()->table( 'ccap_import_issue' ) . "` WHERE batch_id = %d AND severity = 'error' ORDER BY source_id", $batch ) );
		self::assertSame( $bad, $issues(), 'report lists the 3 defective records' );

		ImportRunner::current()->start( $admin, $batch, false );
		ImportRunner::inject_fault(
			static function ( string $point, int $position ): void {
				if ( 'apply' === $point && 300 === $position ) {
					throw new \RuntimeException( 'simulated abort at 50 %' );
				}
			}
		);
		$this->steps( $batch );
		self::assertSame( 'aborted', ImportService::current()->get( $batch )['status'] );
		$half = self::num( "SELECT COUNT(*) FROM {t} WHERE object_type = 'question'", 'ccap_source_map' );
		self::assertGreaterThan( 0, $half );
		self::assertLessThan( 597, $half );

		ImportRunner::inject_fault( null );
		ImportRunner::current()->resume( $admin, $batch, false );
		$steps = $this->steps( $batch );
		self::assertSame( 'published', end( $steps )['status'] );
		self::assertSame( 597, self::num( 'SELECT COUNT(*) FROM {t}', 'ccap_question' ), '19 997 analog: defective records skipped' );
		self::assertSame( 597, self::num( "SELECT COUNT(*) FROM {t} WHERE object_type = 'question'", 'ccap_source_map' ) );
		self::assertSame( 597, self::num( "SELECT COUNT(DISTINCT source_id) FROM {t} WHERE object_type = 'question'", 'ccap_source_map' ), 'no duplicates' );
		foreach ( $bad as $source ) {
			self::assertSame( 0, self::object( 'question', $source ), $source );
		}
		self::assertSame( $bad, $issues(), 'report still has exactly 3 entries' );
	}

	/**
	 * BIG-026: deadlock in a chunk is retried, no object error.
	 *
	 * @group BIG-026
	 */
	public function test_BIG_026_deadlock_retry(): void {
		$batch = $this->approved( $this->package( self::records( 120 ), 'p120' ) );
		ImportRunner::current()->start( Actor::user( $this->admin ), $batch, false );
		$thrown = 0;
		ImportRunner::inject_fault(
			static function ( string $point, int $position ) use ( &$thrown ): void {
				if ( 'apply' === $point && 60 === $position && $thrown < 2 ) {
					++$thrown;
					// Raw deadlock, then the "database busy" form of the retrying transaction layer.
					throw 1 === $thrown ? new StorageException( 'Deadlock found', 1213 ) : new CourseException( 'ccap_db_busy', 'busy', 503 );
				}
			}
		);
		$steps = $this->steps( $batch );
		self::assertSame( 2, $thrown );
		self::assertSame( 'published', end( $steps )['status'] );
		self::assertSame( 0, self::num( "SELECT COUNT(*) FROM {t} WHERE batch_id = %d AND outcome <> 'ok'", 'ccap_import_item_log', $batch ) );
		self::assertSame( 120, self::num( 'SELECT COUNT(*) FROM {t}', 'ccap_question' ) );
	}

	/**
	 * E-176 + BIG-035: a failing object is isolated (savepoint), the rest
	 * imports; systematic failures above max(100, 2 %) abort the run.
	 *
	 * @group BIG-035
	 * @group GM-136
	 */
	public function test_BIG_035_object_isolation_and_threshold(): void {
		$admin = Actor::user( $this->admin );
		$batch = $this->approved( $this->package( self::records( 40 ), 'p40' ) );
		ImportRunner::current()->start( $admin, $batch, false );
		ImportRunner::inject_fault(
			static function ( string $point, int $position ): void {
				if ( 'write' === $point && 30 === $position ) {
					throw new CourseException( 'ccap_conflict', 'simulated constraint', 409 );
				}
			}
		);
		$steps = $this->steps( $batch );
		self::assertSame( 'published', end( $steps )['status'] );
		self::assertSame( 39, self::num( "SELECT COUNT(*) FROM {t} WHERE status = 'published'", 'ccap_question' ) );
		self::assertSame( 0, self::object( 'question', 'BQ-00031' ), 'savepoint rolled the failed object back' );
		$issue = Database::current()->row( Database::current()->prepare( 'SELECT rule, severity, class, phase, params, source_id FROM `' . Database::current()->table( 'ccap_import_issue' ) . "` WHERE batch_id = %d AND phase = 'commit'", $batch ) );
		self::assertSame(
			array(
				'rule'      => 'IMP-B1.04',
				'severity'  => 'error',
				'class'     => 'O',
				'phase'     => 'commit',
				'params'    => '{"code":"ccap_conflict"}',
				'source_id' => 'BQ-00031',
			),
			$issue
		);
		self::assertSame( 1, (int) json_decode( (string) ImportService::current()->get( $batch )['stats'], true )['failed_objects'] );

		// Threshold: every write fails.
		$batch2 = $this->approved( $this->package( self::records( 150, 1, array( 'text' => ' v2' ) ), 'p150' ) );
		ImportRunner::current()->start( $admin, $batch2, false );
		ImportRunner::inject_fault(
			static function ( string $point ): void {
				if ( 'write' === $point ) {
					throw new CourseException( 'ccap_conflict', 'simulated constraint', 409 );
				}
			}
		);
		$steps = $this->steps( $batch2 );
		self::assertSame( 'aborted', end( $steps )['status'] );
		self::assertSame( 'IMP-B1.05', end( $steps )['reason'] );
		self::assertSame( 1, self::num( "SELECT COUNT(*) FROM {t} WHERE action = 'import_threshold_exceeded'", 'ccap_audit_log' ) );
		self::assertSame( 1, self::num( "SELECT COUNT(*) FROM {t} WHERE batch_id = %d AND rule = 'IMP-B1.05' AND class = 'F'", 'ccap_import_issue', $batch2 ) );
	}

	/**
	 * BIG-031 (R1-26 A-2): activation runs the course validator; a course with
	 * a blocker is held, the other one is activated in its own transaction.
	 *
	 * @group BIG-031
	 */
	public function test_BIG_031_validator_holds_blocked_course(): void {
		$batch = $this->approved( $this->package( self::records( 10, 2, array( 'empty_module' => 2 ) ), 'held' ) );
		$admin = Actor::user( $this->admin );
		ImportService::current()->run( $admin, $batch );
		$row = (array) ImportService::current()->get( $batch );
		self::assertSame( 'published', $row['status'] );
		$held = self::object( 'course', 'BC-2' );
		self::assertSame( 'publish', get_post_status( self::object( 'course', 'BC-1' ) ) );
		self::assertSame( 'draft', get_post_status( $held ), 'held course stays unpublished (E-179)' );
		self::assertSame( array( $held ), json_decode( (string) $row['stats'], true )['held_courses'] );
		self::assertGreaterThan( 0, self::num( "SELECT COUNT(*) FROM {t} WHERE batch_id = %d AND phase = 'activate' AND severity = 'blocker' AND source_id = 'BC-2' AND rule LIKE 'VAL-%%'", 'ccap_import_issue', $batch ) );
		self::assertSame( 1, self::num( "SELECT COUNT(*) FROM {t} WHERE action = 'import_course_held'", 'ccap_audit_log' ) );
		$report = ImportReport::export( $admin, $batch, 'json', array( 'severity' => 'blocker' ) );
		self::assertIsArray( $report );
		self::assertSame( 1, $report['summary']['held_courses'] );
		self::assertNotSame( '', $report['issues'][0]['message'] );
	}

	/**
	 * BIG-032: crash between two course activations; activated courses stay,
	 * discard is refused, resume activates the rest.
	 *
	 * @group BIG-032
	 */
	public function test_BIG_032_crash_between_courses(): void {
		$batch  = $this->approved( $this->package( self::records( 10, 2 ), 'two' ) );
		$admin  = Actor::user( $this->admin );
		$runner = ImportRunner::current();
		$runner->start( $admin, $batch, false );
		$second = 0;
		ImportRunner::inject_fault(
			static function ( string $point, int $course ) use ( &$second ): void {
				if ( 'course' === $point && $course === $second ) {
					throw new \RuntimeException( 'simulated crash' );
				}
			}
		);
		$steps = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$second  = self::object( 'course', 'BC-2' );
			$steps[] = $runner->step( $admin, $batch, 0.0 );
			if ( 'progress' !== end( $steps )['outcome'] ) {
				break;
			}
		}
		self::assertSame( 'aborted', end( $steps )['status'] );
		self::assertSame( 'publish', get_post_status( self::object( 'course', 'BC-1' ) ), 'course 1 activated in its own transaction' );
		self::assertSame( 'draft', get_post_status( $second ), 'course 2 unchanged' );
		try {
			ImportService::current()->discard( $admin, $batch );
			self::fail( 'discard after activation' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_import_state', $e->error_code );
		}
		ImportRunner::inject_fault( null );
		self::assertSame( 'activating', $runner->resume( $admin, $batch, false )['status'] );
		$steps = $this->steps( $batch );
		self::assertSame( 'published', end( $steps )['status'] );
		self::assertSame( 'publish', get_post_status( $second ) );
		self::assertSame( 1, self::num( 'SELECT COUNT(*) FROM {t} WHERE course_id = %d', 'ccap_course_revision', self::object( 'course', 'BC-1' ) ), 'course 1 not published twice' );
	}

	/**
	 * BIG-030 + BIG-016: cancel stops at the chunk boundary; discard is refused
	 * while a step holds the lease, then removes what the batch created.
	 *
	 * @group BIG-016
	 * @group BIG-030
	 * @group GM-126
	 */
	public function test_BIG_030_cancel_and_discard(): void {
		$before = self::counts();
		$batch  = $this->approved( $this->package( self::records( 400 ), 'p400' ) );
		$admin  = Actor::user( $this->admin );
		$runner = ImportRunner::current();
		$runner->start( $admin, $batch, false );
		$runner->step( $admin, $batch, 0.0 );
		$runner->step( $admin, $batch, 0.0 );
		self::assertGreaterThan( 0, self::num( 'SELECT COUNT(*) FROM {t}', 'ccap_question' ) );
		$db    = Database::current();
		$lease = $db->table( 'ccap_lease' );
		// A live step holds the lease: cancellation is only requested.
		$db->execute( $db->prepare( "INSERT INTO `{$lease}` (name, owner, fence, expires_at, heartbeat_at, created_at) VALUES ('import', 'live-step', 9, %s, %s, %s)", $db->now( 600 ), $db->now(), $db->now() ) );
		$cancel = $runner->cancel( $admin, $batch );
		self::assertTrue( $cancel['cancel_requested'] );
		self::assertSame( 'importing', $cancel['status'] );
		$db->execute( "DELETE FROM `{$lease}` WHERE name = 'import'" );
		$stop = $runner->step( $admin, $batch, 0.0 );
		self::assertSame( 'aborted', $stop['status'] );
		self::assertSame( 'IMP-B1.09', $stop['reason'] );

		// BIG-016: discard while a step holds the lease.
		$db->execute( $db->prepare( "INSERT INTO `{$lease}` (name, owner, fence, expires_at, heartbeat_at, created_at) VALUES ('import', 'live-step', 10, %s, %s, %s)", $db->now( 600 ), $db->now(), $db->now() ) );
		try {
			ImportService::current()->discard( $admin, $batch );
			self::fail( 'discard while running' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_import_busy', $e->error_code );
		}
		$db->execute( "DELETE FROM `{$lease}` WHERE name = 'import'" );
		ImportService::current()->discard( $admin, $batch );
		self::assertSame( 'discarded', ImportService::current()->get( $batch )['status'] );
		$after = self::counts();
		self::assertSame( $before['ccap_question'], $after['ccap_question'], 'created questions removed' );
		self::assertSame( $before['ccap_question_revision'], $after['ccap_question_revision'] );
		self::assertSame( $before['ccap_source_map'], $after['ccap_source_map'] );
		self::assertSame( 0, self::num( "SELECT COUNT(*) FROM {t} WHERE batch_id = %d AND action = 'create' AND outcome = 'ok'", 'ccap_import_item_log', $batch ) );
		self::assertSame( 1, self::num( "SELECT COUNT(*) FROM {t} WHERE action = 'import_discarded'", 'ccap_audit_log' ) );
		self::assertSame( 1, self::num( "SELECT COUNT(*) FROM {t} WHERE action = 'import_cancel_requested'", 'ccap_audit_log' ) );
	}

	/**
	 * BIG-037/BIG-038: resume pre-checks (changed spool, newer activated import).
	 *
	 * @group BIG-037
	 * @group BIG-038
	 * @group GM-124
	 */
	public function test_BIG_037_resume_prechecks(): void {
		$admin  = Actor::user( $this->admin );
		$runner = ImportRunner::current();
		$batch  = $this->approved( $this->package( self::records( 200 ), 'p200' ) );
		$runner->start( $admin, $batch, false );
		$runner->step( $admin, $batch, 0.0 );
		$runner->step( $admin, $batch, 0.0 );
		$runner->cancel( $admin, $batch );
		$run   = ImportRunner::run_state( (array) ImportService::current()->get( $batch ) );
		$spool = \CourseCaptain\Access\ProtectedStorage::dir( 'packages' ) . '/' . $run['spool'];
		file_put_contents( $spool, "tampered\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test manipulates the spool.
		try {
			$runner->resume( $admin, $batch, false );
			self::fail( 'changed spool' );
		} catch ( CourseException $e ) {
			self::assertSame( 'IMP-B1.07', $e->getMessage() );
		}
		// A newer import of the same source gets activated meanwhile.
		ImportService::current()->run( $admin, $this->approved( $this->package( self::records( 3 ), 'p3' ) ) );
		try {
			$runner->resume( $admin, $batch, false );
			self::fail( 'superseded' );
		} catch ( CourseException $e ) {
			self::assertSame( 'IMP-B1.06', $e->getMessage() );
		}
	}

	/**
	 * E-177/E-181: background chain over `ccap_task_continue`, approver rights
	 * re-checked, hourly watchdog resumes stale runs up to three times.
	 *
	 * @group E-177
	 * @group E-181
	 */
	public function test_background_chain_and_watchdog(): void {
		$admin = Actor::user( $this->admin );
		$batch = $this->approved( $this->package( self::records( 150 ), 'bg' ) );
		ImportRunner::current()->start( $admin, $batch );
		$task = 'import:' . $batch;
		self::assertNotFalse( wp_next_scheduled( CronHooks::TASK_CONTINUE, array( $task, 0 ) ) );
		wp_set_current_user( 0 );
		for ( $i = 0; $i < 30; $i++ ) {
			ImportRunner::run_task( $task, $i );
			if ( 'published' === ImportService::current()->get( $batch )['status'] ) {
				break;
			}
		}
		self::assertSame( 'published', ImportService::current()->get( $batch )['status'] );
		self::assertSame( 150, self::num( "SELECT COUNT(*) FROM {t} WHERE status = 'published'", 'ccap_question' ) );

		// Approver lost the right to publish: the background step aborts (IMP-B1.10).
		$editor = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$batch2 = $this->approved( $this->package( self::records( 5, 1, array( 'text' => ' v2' ) ), 'bg2' ) );
		ImportRunner::current()->start( Actor::user( $this->admin ), $batch2, false );
		( new \WP_User( $this->admin ) )->remove_cap( 'ccap_publish_courses' );
		( new \WP_User( $this->admin ) )->set_role( 'subscriber' );
		ImportRunner::run_task( 'import:' . $batch2, 0 );
		self::assertSame( 'aborted', ImportService::current()->get( $batch2 )['status'] );
		self::assertSame( 'IMP-B1.10', ImportService::current()->get( $batch2 )['error'] );

		// Watchdog: stale run without lease → automatic resume scheduled, after three → aborted.
		( new \WP_User( $editor ) )->set_role( 'administrator' );
		$this->admin = $editor;
		$batch3      = $this->approved( $this->package( self::records( 5, 1, array( 'text' => ' v3' ) ), 'bg3' ) );
		ImportRunner::current()->start( Actor::user( $editor ), $batch3, false );
		$db = Database::current();
		for ( $i = 1; $i <= 4; $i++ ) {
			$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_import_batch' ) . '` SET heartbeat_at = %s WHERE id = %d', $db->now( -3600 ), $batch3 ) );
			self::assertSame( 1, ImportRunner::current()->watchdog() );
		}
		self::assertSame( 'aborted', ImportService::current()->get( $batch3 )['status'] );
		self::assertSame( 'IMP-B1.02', ImportService::current()->get( $batch3 )['error'] );
		self::assertSame( 3, self::num( "SELECT COUNT(*) FROM {t} WHERE action = 'import_auto_resumed'", 'ccap_audit_log' ) );
	}

	/**
	 * BIG-002/003/004/005/008: per-object report with stable codes, CSV
	 * (BOM, CRLF, injection-safe) and JSON pages over REST, rights.
	 *
	 * @group BIG-002
	 * @group BIG-003
	 * @group BIG-004
	 * @group BIG-005
	 */
	public function test_BIG_003_report_export_and_rest(): void {
		$records                            = self::records( 30, 1, array( 'bad_hash' => array( 'BQ-00007' ) ) );
		$records['questions'][8]            = array_merge( $records['questions'][8], array( 'source_id' => '=HYPERLINK("x")' ) );
		$records['modules'][0]['lessons'][] = array(
			'lesson' => 'BL-missing',
			'sort'   => 9,
		);
		$records['modules'][0]              = PackageExporter::sealed( array_diff_key( $records['modules'][0], array( 'hash' => 1 ) ) );
		$file                               = $this->package( $records, 'report' );
		$service                            = ImportService::current();
		$admin                              = Actor::user( $this->admin );
		$batch                              = $service->upload( $admin, $file );
		$service->validate( $admin, $batch );
		$preview = $service->preview( $admin, $batch );
		self::assertSame( 'previewed', $preview['status'] );
		$rules = array_column( Database::current()->rows( Database::current()->prepare( 'SELECT source_id, rule, severity, class FROM `' . Database::current()->table( 'ccap_import_issue' ) . '` WHERE batch_id = %d ORDER BY id', $batch ) ), 'rule', 'source_id' );
		self::assertSame( 'IMP-V6.06', $rules['BQ-00007'] );
		self::assertSame( 'IMP-V6.03', $rules['BM-1'] );
		self::assertSame( 'IMP-B1.08', $rules['BC-1'] );

		$csv = ImportReport::export( $admin, $batch, 'csv' );
		self::assertIsString( $csv );
		self::assertStringStartsWith( "\xEF\xBB\xBF" . implode( ',', ImportReport::CSV_COLUMNS ) . "\r\n", $csv );
		self::assertSame( 5, substr_count( $csv, "\r\n" ), 'header + 4 findings' );
		self::assertStringContainsString( "'=HYPERLINK", $csv );
		self::assertStringNotContainsString( '=HYPERLINK', str_replace( "'=HYPERLINK", '', $csv ) );
		self::assertSame( "'=x", ImportReport::cell( '=x' ) );
		self::assertSame( "'@x", ImportReport::cell( '@x' ) );
		self::assertSame( "'-1", ImportReport::cell( '-1' ) );
		self::assertSame( 'ok', ImportReport::cell( 'ok' ) );
		self::assertStringNotContainsString( ABSPATH, $csv );
		self::assertStringNotContainsString( 'packages-', $csv );

		// REST: JSON pages, filters, progress; rights (BIG-005).
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook.
		wp_set_current_user( $this->admin );
		$request = new \WP_REST_Request( 'GET', '/course-captain/v1/imports/' . $batch . '/report' );
		$request->set_query_params(
			array(
				'per_page' => 2,
				'class'    => 'O',
			)
		);
		$page = rest_do_request( $request );
		self::assertSame( 200, $page->get_status() );
		$data = $page->get_data();
		self::assertCount( 2, $data['issues'] );
		self::assertSame( '1', $data['report_version'] );
		self::assertNotNull( $data['next'] );
		self::assertSame( 'private, no-store', $page->get_headers()['Cache-Control'] );
		$request->set_query_params(
			array(
				'per_page' => 2,
				'after'    => $data['next'],
			)
		);
		self::assertCount( 2, rest_do_request( $request )->get_data()['issues'] );
		$csv_request = new \WP_REST_Request( 'GET', '/course-captain/v1/imports/' . $batch . '/report' );
		$csv_request->set_query_params( array( 'format' => 'csv' ) );
		$csv_response = rest_do_request( $csv_request );
		self::assertStringStartsWith( 'text/csv', $csv_response->get_headers()['Content-Type'] );
		ob_start();
		self::assertTrue( \CourseCaptain\Import\ImportRoutes::serve_csv( false, $csv_response, $csv_request, null ) );
		self::assertStringStartsWith( "\xEF\xBB\xBF", (string) ob_get_clean() );
		$progress = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/imports/' . $batch ) );
		self::assertSame( 'previewed', $progress->get_data()['status'] );
		self::assertArrayNotHasKey( 'storage_path', $progress->get_data() );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertSame( 403, rest_do_request( $request )->get_status() );
		wp_set_current_user( $this->admin );
		self::assertSame( 404, rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/imports/999999/report' ) )->get_status() );
		wp_set_current_user( 0 );
		self::assertSame( 401, rest_do_request( $request )->get_status() );
		self::assertGreaterThanOrEqual( 2, self::num( "SELECT COUNT(*) FROM {t} WHERE action = 'import_report_exported'", 'ccap_audit_log' ) );
	}

	/**
	 * E-178: WP-CLI path with the same runner, no time budget; report to a file.
	 *
	 * @group E-178
	 * @group GM-134
	 */
	public function test_cli_run_status_and_report(): void {
		$admin = Actor::user( $this->admin );
		$batch = $this->approved( $this->package( self::records( 60 ), 'cli' ) );
		self::assertSame( 1, \CourseCaptain\Import\ImportCommand::execute( 'run', array( (string) $batch ), array(), Actor::cli() )['exit'], 'needs --user' );
		$run = \CourseCaptain\Import\ImportCommand::execute( 'run', array( (string) $batch ), array(), $admin );
		self::assertSame( 0, $run['exit'] );
		self::assertSame( 'published', json_decode( $run['lines'][0], true )['status'] );
		$status = json_decode( \CourseCaptain\Import\ImportCommand::execute( 'status', array( (string) $batch ), array(), $admin )['lines'][0], true );
		self::assertSame( 100, $status['percent'] );
		$file = $this->dir . '/report.json';
		self::assertSame(
			0,
			\CourseCaptain\Import\ImportCommand::execute(
				'report',
				array( (string) $batch ),
				array(
					'format' => 'json',
					'output' => $file,
				),
				$admin
			)['exit']
		);
		$report = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test reads the CLI output.
		self::assertSame( 60, $report['summary']['actions']['questions']['create'] );
		self::assertSame( array(), $report['issues'] );
		self::assertSame( 1, \CourseCaptain\Import\ImportCommand::execute( 'cancel', array( (string) $batch ), array(), $admin )['exit'], 'published run cannot be cancelled' );
	}
}
