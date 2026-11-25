<?php
/**
 * Import cases of the catalogue R0-10 §6.2 that had no tagged test
 * (R0-41 §4.1, LEARN-10), second part: IMP-016, IMP-018, IMP-024,
 * IMP-025, IMP-027, IMP-028, IMP-029, IMP-030.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\DeletionGuard;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Import\ImportMedia;
use CourseCaptain\Import\ImportRunner;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Import\PackageValidator;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

/**
 * Real tables and archives, synthetic neutral records (E-02).
 *
 * @group LEARN-10
 * @group R1-15
 */
final class ImportCatalogGaps2Test extends SchemaTestCase {

	private const SYSTEM = 'cc-gaps-b';

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
		$this->dir   = get_temp_dir() . 'ccap-icg2-' . wp_generate_password( 6, false, false );
		wp_mkdir_p( $this->dir );
		PolicyService::flush();
	}

	/**
	 * Remove the fault hook and scratch files.
	 */
	public function tear_down(): void {
		ImportRunner::inject_fault( null );
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test cleanup.
		}
		parent::tear_down();
	}

	/**
	 * Synthetic records: questions Q-1…Q-n in module M-1 of course C-1
	 * with lesson L-1 (and L-2 on request).
	 *
	 * @param int                  $questions Number of questions.
	 * @param array<string, mixed> $options   `text` (question text suffix), `correct` (key of the right answer), `lesson_body`, `question_html`, `description_html`, `second_lesson`, `media` (media records).
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function records( int $questions, array $options = array() ): array {
		$correct = (string) ( $options['correct'] ?? 'A' );
		$out     = array(
			'questions' => array(),
			'lessons'   => array(),
			'modules'   => array(),
			'courses'   => array(),
		);
		for ( $i = 1; $i <= $questions; $i++ ) {
			$out['questions'][] = PackageExporter::sealed(
				array(
					'source_id' => 'Q-' . $i,
					'revision'  => array(
						'question_type' => 'single_choice',
						'text_html'     => 1 === $i && isset( $options['question_html'] ) ? (string) $options['question_html'] : 'Frage ' . $i . (string) ( $options['text'] ?? '' ),
						'settings'      => (object) array(),
					),
					'answers'   => array(
						array(
							'key'       => 'A',
							'text_html' => 'Antwort A',
							'correct'   => 'A' === $correct,
						),
						array(
							'key'       => 'B',
							'text_html' => 'Antwort B',
							'correct'   => 'B' === $correct,
						),
					),
					'tags'      => array(),
				)
			);
		}
		$lessons = array( 'L-1' => (string) ( $options['lesson_body'] ?? '<p>Inhalt</p>' ) );
		if ( ! empty( $options['second_lesson'] ) ) {
			$lessons['L-2'] = '<p>Zweite Lektion</p>';
		}
		foreach ( $lessons as $id => $body ) {
			$out['lessons'][] = PackageExporter::sealed(
				array(
					'source_id'     => $id,
					'title'         => 'Lektion ' . $id,
					'body_html'     => $body,
					'activity_type' => 'lesson',
					'settings'      => (object) array(),
				)
			);
		}
		$out['modules'][] = PackageExporter::sealed(
			array(
				'source_id'     => 'M-1',
				'title'         => 'Modul 1',
				'summary'       => '',
				'lessons'       => array_map(
					static fn( string $id, int $i ): array => array(
						'lesson' => $id,
						'sort'   => $i + 1,
					),
					array_keys( $lessons ),
					array_keys( array_keys( $lessons ) )
				),
				'question_pool' => array_map(
					static fn( int $n ): array => array(
						'question' => 'Q-' . $n,
						'sort'     => $n,
					),
					range( 1, $questions )
				),
			)
		);
		$out['courses'][] = PackageExporter::sealed(
			array(
				'source_id'        => 'C-1',
				'title'            => 'Kurs 1',
				'slug'             => 'gaps-b-kurs',
				'summary'          => '',
				'description_html' => (string) ( $options['description_html'] ?? '<p>Kurs</p>' ),
				'modules'          => array(
					array(
						'module'   => 'M-1',
						'sort'     => 1,
						'required' => true,
					),
				),
			)
		);
		if ( isset( $options['media'] ) ) {
			$out['media'] = (array) $options['media'];
		}
		return $out;
	}

	/**
	 * Write a package.
	 *
	 * @param array<string, list<array<string, mixed>>> $records  Records.
	 * @param string                                    $name     File name.
	 * @param int                                       $per_file Records per data file.
	 * @param array<string, string>                     $media    Media files.
	 * @return string
	 */
	private function package( array $records, string $name, int $per_file = PackageExporter::RECORDS_PER_FILE, array $media = array() ): string {
		return PackageExporter::write( $records, self::SYSTEM, 'Gaps ' . $name, $this->dir . '/' . $name . '.zip', $per_file, $media );
	}

	/**
	 * Upload, validate, dry run, approve.
	 *
	 * @param string $file Package.
	 * @return int Batch.
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
	 * Whole path in one request.
	 *
	 * @param string $file Package.
	 * @return int Batch.
	 */
	private function import( string $file ): int {
		$batch = $this->approved( $file );
		ImportService::current()->run( Actor::user( $this->admin ), $batch );
		self::assertSame( 'published', ImportService::current()->get( $batch )['status'] );
		return $batch;
	}

	/**
	 * Steps with a zero budget (one chunk per step) until the run stops.
	 *
	 * @param int           $batch Batch.
	 * @param callable|null $after Called after each step with the batch status.
	 * @return string Final status.
	 */
	private function steps( int $batch, ?callable $after = null ): string {
		$runner = ImportRunner::current();
		for ( $i = 0; $i < 500; $i++ ) {
			$progress = $runner->step( Actor::user( $this->admin ), $batch, 0.0 );
			if ( null !== $after ) {
				$after( (string) ImportService::current()->get( $batch )['status'] );
			}
			if ( 'progress' !== $progress['outcome'] ) {
				break;
			}
		}
		return (string) ImportService::current()->get( $batch )['status'];
	}

	/**
	 * Object ID of a source ID.
	 *
	 * @param string $type   Type.
	 * @param string $source Source ID.
	 * @param string $system Source system.
	 * @return int
	 */
	private static function object( string $type, string $source, string $system = self::SYSTEM ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT object_id FROM `' . $db->table( 'ccap_source_map' ) . '` WHERE source_system = %s AND object_type = %s AND source_id = %s', $system, $type, $source ) );
	}

	/**
	 * Current revision per imported question (source ID → revision).
	 *
	 * @return array<string, int>
	 */
	private static function current_revisions(): array {
		$db  = Database::current();
		$out = array();
		foreach ( $db->rows( $db->prepare( 'SELECT m.source_id, q.current_revision_id FROM `' . $db->table( 'ccap_source_map' ) . '` m JOIN `' . $db->table( 'ccap_question' ) . "` q ON q.id = m.object_id WHERE m.source_system = %s AND m.object_type = 'question' ORDER BY m.source_id", self::SYSTEM ) ) as $row ) {
			$out[ (string) $row['source_id'] ] = (int) $row['current_revision_id'];
		}
		return $out;
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
	 * IMP-016: script, event handlers, `javascript:` links and iframes in
	 * lesson, course and question HTML are removed on import; harmless
	 * markup and https links stay. The dry run plans the records normally.
	 *
	 * Not covered (question to the lead): the dry run carries no warning
	 * about removed HTML (R0-10 §3.2 V6 "HTML-Filter-Diff → Warnung").
	 *
	 * @group IMP-016
	 */
	public function test_IMP_016_html_filter(): void {
		$evil    = '<p>Sicherer Text</p><script>alert(1)</script><img src="https://example.test/a.png" alt="Bild" onerror="alert(2)">'
			. '<a href="javascript:alert(3)">Schlechter Link</a><iframe src="https://evil.example.test/"></iframe>'
			. '<a href="https://example.test/ok">Guter Link</a><p onclick="x()">Absatz</p>';
		$records = self::records(
			2,
			array(
				'lesson_body'      => $evil,
				'description_html' => $evil,
				'question_html'    => 'Wähle <strong>eine</strong><script>steal()</script><img src="https://example.test/q.png" alt="Q" onerror="y()">',
			)
		);
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $this->package( $records, 'html' ) );
		self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
		$preview = $service->preview( $admin, $batch );
		self::assertSame( 'previewed', $preview['status'] );
		self::assertSame( array( 'create' => 1 ), $preview['plan']['lessons'] );
		self::assertSame( 0, self::object( 'lesson', 'L-1' ), 'dry run writes nothing' );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );

		foreach ( array( (string) get_post( self::object( 'lesson', 'L-1' ) )->post_content, (string) get_post( self::object( 'course', 'C-1' ) )->post_content ) as $html ) {
			foreach ( array( '<script', 'onerror', 'onclick', 'javascript:', '<iframe', 'evil.example.test' ) as $forbidden ) {
				self::assertStringNotContainsString( $forbidden, $html );
			}
			self::assertStringContainsString( '<p>Sicherer Text</p>', $html );
			self::assertStringContainsString( '<a href="https://example.test/ok">Guter Link</a>', $html );
			self::assertStringContainsString( 'Schlechter Link', $html, 'link text stays, the protocol goes' );
			self::assertStringContainsString( 'alt="Bild"', $html );
		}
		$db       = Database::current();
		$revision = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', self::object( 'question', 'Q-1' ) ) );
		$text     = (string) QuestionRepository::current()->revision( $revision )['question_text'];
		foreach ( array( '<script', 'onerror' ) as $forbidden ) {
			self::assertStringNotContainsString( $forbidden, $text );
		}
		self::assertStringContainsString( '<strong>eine</strong>', $text );
	}

	/**
	 * IMP-018: the dry run in time slices (zero budget = one unit per
	 * step, and the catalogue's 0.1 s) can be continued and gives the
	 * same plan, items, statistics and issue rows as one run at once;
	 * meanwhile the batch stays `validated` and cannot be approved.
	 *
	 * @group IMP-018
	 */
	public function test_IMP_018_dry_run_in_time_slices(): void {
		$records                         = self::records( 9, array( 'second_lesson' => true ) );
		$records['questions'][4]['hash'] = 'sha256:' . str_repeat( '0', 64 );
		$file                            = $this->package( $records, 'slices', 2 );
		$service                         = ImportService::current();
		$admin                           = Actor::user( $this->admin );
		$db                              = Database::current();
		$issues                          = static fn( int $batch ): array => $db->wpdb()->get_col( $db->prepare( "SELECT CONCAT(entity, ':', source_id, ':', rule) FROM `" . $db->table( 'ccap_import_issue' ) . '` WHERE batch_id = %d ORDER BY entity, source_id', $batch ) );

		$once = $service->upload( $admin, $file );
		$service->validate( $admin, $once );
		$whole = $service->preview( $admin, $once );
		self::assertSame( 'previewed', $whole['status'] );
		self::assertSame( 3, $whole['stats']['object_errors'], 'Q-5 with its dependants M-1 and C-1' );

		foreach ( array( 0.0, 0.1 ) as $budget ) {
			$sliced = $service->upload( $admin, $file );
			$service->validate( $admin, $sliced );
			$steps = 0;
			do {
				$out = $service->preview_step( $admin, $sliced, $budget );
				++$steps;
				if ( empty( $out['done'] ) ) {
					self::assertSame( 'validated', $service->get( $sliced )['status'] );
					self::assertGreaterThan( 0, $out['units_done'] );
					self::assertLessThan( $out['units_total'], $out['units_done'] );
					$this->assertRejected( fn() => $service->approve( $admin, $sliced ), 'ccap_import_state' );
				}
			} while ( empty( $out['done'] ) && $steps < 200 );
			self::assertTrue( (bool) $out['done'] );
			if ( 0.0 === $budget ) {
				self::assertGreaterThan( 10, $steps, 'one unit per step with a zero budget' );
				self::assertSame( $out['units_total'], $steps );
			}
			self::assertSame( 'previewed', $out['status'] );
			self::assertSame( 'previewed', $service->get( $sliced )['status'] );
			self::assertSame( $whole['plan'], $out['plan'], 'budget ' . $budget );
			self::assertSame( $whole['items'], $out['items'], 'budget ' . $budget );
			foreach ( array( 'records', 'items', 'files', 'object_errors', 'issues_stored', 'rules', 'threshold', 'units' ) as $key ) {
				self::assertSame( $whole['stats'][ $key ], $out['stats'][ $key ], $key );
			}
			self::assertSame( $issues( $once ), $issues( $sliced ) );
			$stats = (array) json_decode( (string) $service->get( $sliced )['stats'], true );
			self::assertArrayNotHasKey( 'preview_progress', $stats, 'state file reference removed at the end' );
		}
		self::assertSame( array(), (array) glob( ProtectedStorage::dir( 'packages' ) . '/preview-' . $once . '-*.json' ) );
	}

	/**
	 * IMP-024: while the batch imports (`importing`), the new question
	 * revisions and the new lesson are written, but no question pointer
	 * and no course revision switches; at the entry into `activating`
	 * still nothing is live; after the activation all pointers and the
	 * course revision are switched.
	 *
	 * Question to the lead: the runner switches the question pointers in
	 * the phase `questions` (own transactions, course-independent, E-50)
	 * before the per-course transaction of the course revision; R0-10
	 * §3.4 asks for one transaction per course. The test asserts the
	 * visible result only.
	 *
	 * @group IMP-024
	 */
	public function test_IMP_024_activation_switches_everything_at_once(): void {
		$this->import( $this->package( self::records( 250 ), 'act-v1' ) );
		$course     = self::object( 'course', 'C-1' );
		$before     = self::current_revisions();
		$course_rev = (int) CoursePublisher::current()->current_revision( $course )['id'];
		self::assertCount( 250, $before );

		$batch = $this->approved(
			$this->package(
				self::records(
					250,
					array(
						'text'          => ' (korrigiert)',
						'second_lesson' => true,
					)
				),
				'act-v2'
			)
		);
		$admin = Actor::user( $this->admin );
		$db    = Database::current();
		ImportRunner::current()->start( $admin, $batch, false );
		$seen   = array();
		$status = $this->steps(
			$batch,
			function ( string $status ) use ( $batch, $before, $course, $course_rev, $db, &$seen ): void {
				$written = (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_import_item_log' ) . "` WHERE batch_id = %d AND entity = 'questions' AND outcome = 'ok'", $batch ) );
				$run     = ImportRunner::run_state( (array) ImportService::current()->get( $batch ) );
				$entered = 'activating' === $status && 'questions' === ( $run['phase'] ?? '' ) && 0 === (int) ( $run['after'] ?? 0 );
				if ( ( 'importing' === $status && $written > 0 ) || $entered ) {
					$seen[] = $status;
					self::assertSame( $before, self::current_revisions(), 'no question pointer switched before the activation' );
					self::assertSame( $course_rev, (int) CoursePublisher::current()->current_revision( $course )['id'], 'no course revision before the activation' );
					$revisions = (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_question_revision' ) . '`' );
					self::assertGreaterThan( 250, $revisions, 'new revisions are written already' );
				}
			}
		);
		self::assertSame( 'published', $status );
		self::assertContains( 'importing', $seen, 'a step ended with written but not activated revisions' );
		self::assertContains( 'activating', $seen );

		$after = self::current_revisions();
		self::assertCount( 250, $after );
		foreach ( $after as $source => $revision ) {
			self::assertNotSame( $before[ $source ], $revision, $source );
			self::assertStringEndsWith( '(korrigiert)', (string) QuestionRepository::current()->revision( $revision )['question_text'] );
		}
		$new = CoursePublisher::current()->current_revision( $course );
		self::assertNotSame( $course_rev, (int) $new['id'], 'new course revision with the new lesson' );
	}

	/**
	 * IMP-025: a question changes upstream (new revision, other correct
	 * answer) while a learner's exam attempt runs: the attempt keeps the
	 * old revisions and is graded against them; a new attempt uses the
	 * new revisions.
	 *
	 * @group IMP-025
	 */
	public function test_IMP_025_running_attempt_keeps_old_revision(): void {
		$this->import( $this->package( self::records( 2 ), 'exam-v1' ) );
		$course  = self::object( 'course', 'C-1' );
		$module  = self::object( 'module', 'M-1' );
		$old     = self::current_revisions();
		$admin   = Actor::user( $this->admin );
		$learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$db      = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $learner,
				'course_id'   => $course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		$rules = ExamRules::current();
		$rule  = $rules->create(
			$admin,
			$course,
			'v1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			600
		);
		$rules->set_blueprint( $admin, $rule, $module, 2 );
		$rules->publish( $admin, $rule );
		$sessions = ExamSessions::current();
		$actor    = Actor::user( $learner );
		$session  = $sessions->start( $actor, $rule, null, wp_generate_uuid4(), new Randomizer( '20300115' ) );

		// Upstream correction: B is right now.
		$this->import(
			$this->package(
				self::records(
					2,
					array(
						'text'    => ' (neu)',
						'correct' => 'B',
					)
				),
				'exam-v2'
			)
		);
		$new = self::current_revisions();
		self::assertNotSame( $old['Q-1'], $new['Q-1'] );
		self::assertNotSame( $old['Q-2'], $new['Q-2'] );

		$frozen = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT revision_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY revision_id', $session ) ) );
		$expect = array_values( $old );
		sort( $expect );
		self::assertSame( $expect, $frozen, 'the attempt stays on the old revisions' );
		$sessions->save_answer( $actor, $session, 1, array( 'A' ) );
		$sessions->save_answer( $actor, $session, 2, array( 'A' ) );
		$result = $sessions->submit( $actor, $session );
		self::assertSame( 'evaluated', $result['status'] );
		self::assertSame( 2000, $result['points_milli'], 'graded against the old revisions (A was right)' );
		self::assertTrue( $result['passed'] );

		$next   = $sessions->start( $actor, $rule, null, wp_generate_uuid4(), new Randomizer( '20300116' ) );
		$fresh  = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT revision_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY revision_id', $next ) ) );
		$expect = array_values( $new );
		sort( $expect );
		self::assertSame( $expect, $fresh, 'a new attempt uses the new revisions' );
	}

	/**
	 * IMP-027: a rollback is refused without a reason, on a batch that is
	 * still active (approved, importing; not published/superseded), and
	 * when the kept package copy is gone; nothing is created then.
	 *
	 * Question to the lead: if "auf aktives Paket ohne Abbruch" means a
	 * rollback while another import still runs (must be aborted first, LB
	 * `restoreActive`), the code allows it today (no such check).
	 *
	 * @group IMP-027
	 */
	public function test_IMP_027_rollback_refusals(): void {
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$first   = $this->import( $this->package( self::records( 2 ), 'rb-v1' ) );
		$db      = Database::current();
		$batches = static fn(): int => (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_import_batch' ) . '`' );
		$count   = $batches();

		$this->assertRejected( fn() => $service->rollback( $admin, $first, '' ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $service->rollback( $admin, $first, " \n\t " ), 'ccap_validation_failed' );

		$second = $this->approved( $this->package( self::records( 2, array( 'text' => ' v2' ) ), 'rb-v2' ) );
		$this->assertRejected( fn() => $service->rollback( $admin, $second, 'Zurück' ), 'ccap_import_state' );
		ImportRunner::current()->start( $admin, $second, false );
		self::assertSame( 'importing', $service->get( $second )['status'] );
		$this->assertRejected( fn() => $service->rollback( $admin, $second, 'Zurück' ), 'ccap_import_state' );
		self::assertSame( $count + 1, $batches(), 'no rollback batch created' );
		self::assertSame( 'published', $this->steps( $second ) );
		self::assertSame( 'superseded', $service->get( $first )['status'] );

		$copy = (string) $service->get( $first )['storage_path'];
		self::assertFileExists( $copy );
		wp_delete_file( $copy );
		$this->assertRejected( fn() => $service->rollback( $admin, $first, 'Paketkopie fehlt' ), 'ccap_not_found' );
		self::assertSame( $count + 1, $batches() );
		self::assertSame( '0', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'import_rollback_started'" ) );

		// Counter-check: the published second batch can be rolled back.
		$rb = $service->rollback( $admin, $second, 'Zurück auf v2' );
		self::assertSame( 'uploaded', $service->get( $rb )['status'] );
	}

	/**
	 * IMP-028: a lesson edited locally and changed upstream is a
	 * `conflict` in the dry run; by default the local version wins (the
	 * object is skipped, the item log keeps `conflict_kept`), the other
	 * changes of the package are applied.
	 *
	 * Question to the lead: the choice "Quelle übernehmen" per object or
	 * for all in the approval (E-63, with audit) does not exist yet
	 * (`ImportService::approve()` has no option), so it is not tested.
	 *
	 * @group IMP-028
	 */
	public function test_IMP_028_conflict_local_wins(): void {
		$this->import( $this->package( self::records( 2 ), 'conf-v1' ) );
		$lesson = self::object( 'lesson', 'L-1' );
		wp_update_post(
			array(
				'ID'           => $lesson,
				'post_content' => '<p>Lokal überarbeitet</p>',
			)
		);
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT locally_modified FROM `' . $db->table( 'ccap_source_map' ) . "` WHERE object_type = 'lesson' AND object_id = %d", $lesson ) ) );
		$old = self::current_revisions();

		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload(
			$admin,
			$this->package(
				self::records(
					2,
					array(
						'lesson_body' => '<p>Quelle geändert</p>',
						'text'        => ' (Quelle)',
					)
				),
				'conf-v2'
			)
		);
		$service->validate( $admin, $batch );
		$preview = $service->preview( $admin, $batch );
		self::assertSame( array( 'conflict' => 1 ), $preview['plan']['lessons'] );
		self::assertSame( array( 'update' => 2 ), $preview['plan']['questions'] );
		$items = array_column( array_filter( $preview['items'], static fn( array $i ): bool => 'lessons' === $i['entity'] ), 'action', 'source_id' );
		self::assertSame( array( 'L-1' => 'conflict' ), $items );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );

		self::assertSame( '<p>Lokal überarbeitet</p>', (string) get_post( $lesson )->post_content, 'local wins by default' );
		self::assertSame( 'conflict_kept', (string) $db->scalar( $db->prepare( 'SELECT outcome FROM `' . $db->table( 'ccap_import_item_log' ) . "` WHERE batch_id = %d AND entity = 'lessons' AND source_id = 'L-1'", $batch ) ), 'source change logged' );
		self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT locally_modified FROM `' . $db->table( 'ccap_source_map' ) . "` WHERE object_type = 'lesson' AND object_id = %d", $lesson ) ), 'still marked as local' );
		$new = self::current_revisions();
		self::assertNotSame( $old['Q-1'], $new['Q-1'], 'unaffected objects are updated' );
	}

	/**
	 * IMP-029: a half-written media file is recognised by its checksum and
	 * replaced atomically; an intact file is taken over (I-1). End to end:
	 * the run crashes after the media file was written, the file is cut in
	 * half, and the resumed run writes it again with the right checksum.
	 *
	 * @group IMP-029
	 */
	public function test_IMP_029_media_retry_after_half_written_file(): void {
		// Unit level: store() with an intact, a half-written and no file.
		$data   = 'k14-' . wp_generate_password( 64, true, true ) . str_repeat( 'x', 4096 );
		$sha    = hash( 'sha256', $data );
		$target = ImportMedia::store( $data, $sha, 'k14-half.bin', 'protected' );
		self::assertSame( $sha, hash_file( 'sha256', $target ) );
		self::assertSame( $target, ImportMedia::store( $data, $sha, 'k14-half.bin', 'protected' ), 'intact file is taken over' );
		file_put_contents( $target, substr( $data, 0, 1000 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Simulated half-written file.
		self::assertNotSame( $sha, hash_file( 'sha256', $target ) );
		self::assertSame( $target, ImportMedia::store( $data, $sha, 'k14-half.bin', 'protected' ) );
		self::assertSame( $sha, hash_file( 'sha256', $target ), 'replaced' );
		self::assertSame( array(), (array) glob( dirname( $target ) . '/*.part' ), 'no temporary file left' );

		// End to end.
		$png    = NeutralFixture::media_files()['a1-003.png'];
		$psha   = hash( 'sha256', $png );
		$record = PackageExporter::sealed(
			array(
				'source_id'  => 'MED-K14',
				'path'       => 'media/k14-retry.png',
				'media_type' => 'image',
				'mime_type'  => 'image/png',
				'size'       => strlen( $png ),
				'sha256'     => $psha,
				'alt_text'   => 'Farbfläche',
				'caption'    => '',
				'access'     => 'protected',
				'rights'     => array(
					'status'  => 'own',
					'license' => 'CC0-1.0',
				),
			)
		);
		$file   = ProtectedStorage::dir( 'protected' ) . '/import/' . $psha . '/k14-retry.png';
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
		$batch  = $this->approved( $this->package( self::records( 2, array( 'media' => array( $record ) ) ), 'media', PackageExporter::RECORDS_PER_FILE, array( 'media/k14-retry.png' => $png ) ) );
		$admin  = Actor::user( $this->admin );
		$runner = ImportRunner::current();
		$runner->start( $admin, $batch, false );
		ImportRunner::inject_fault(
			static function ( string $point, int $position ): void {
				if ( 'apply' === $point && 1 === $position ) {
					throw new \RuntimeException( 'simulated crash after the media file' );
				}
			}
		);
		self::assertSame( 'aborted', $this->steps( $batch ) );
		self::assertFileExists( $file, 'the file was written before the crash' );
		self::assertSame( 0, self::object( 'media', 'MED-K14' ), 'the media row was rolled back' );
		file_put_contents( $file, substr( $png, 0, (int) ( strlen( $png ) / 2 ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Simulated half-written file.

		ImportRunner::inject_fault( null );
		$runner->resume( $admin, $batch, false );
		self::assertSame( 'published', $this->steps( $batch ) );
		self::assertSame( $psha, hash_file( 'sha256', $file ), 'checksum recognised, file replaced' );
		self::assertSame( array(), (array) glob( dirname( $file ) . '/*.part' ) );
		$db    = Database::current();
		$media = self::object( 'media', 'MED-K14' );
		self::assertGreaterThan( 0, $media );
		self::assertSame( $psha, (string) $db->scalar( $db->prepare( 'SELECT sha256 FROM `' . $db->table( 'ccap_media' ) . '` WHERE id = %d', $media ) ) );
		self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_media' ) . "` WHERE source_system = %s AND source_id = 'MED-K14'", self::SYSTEM ) ), 'one media row' );
	}

	/**
	 * IMP-030 (E-382): round trip with media and exam blueprints. A
	 * hand-built course is exported (P1): lesson and question media
	 * (protected image, poster, subtitles, a licensed image with rights
	 * metadata), an exam rule with module draw, passing part, form mode,
	 * a setting override and a frozen fixed variant. A medium with
	 * `restricted` rights and a plain-text file are left out and listed in
	 * `export.excluded[]`. The site is emptied of the course (course,
	 * module and lesson deleted; E-382: no UID matching, the import
	 * creates new objects), P1 is imported, and the imported course is
	 * exported again (P2): both packages have the same records and the
	 * same `content_sha256`; P2 imported again plans `unchanged` only.
	 *
	 * @group IMP-030
	 * @group E-382
	 */
	public function test_IMP_030_round_trip(): void {
		$admin  = Actor::user( $this->admin );
		$post   = fn( string $type, string $title, string $content = '', int $parent_id = 0 ): int => (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
				'post_name'    => 'ccap-rt-' . strtolower( wp_generate_password( 6, false, false ) ),
			)
		);
		$course = $post( 'ccap_course', 'Rundreise', '<p>Über den Kurs</p>' );
		$module = $post( 'ccap_module', 'Rundreise Modul' );
		CourseStructure::current()->assign_module( $admin, $course, $module );
		$lesson = $post( 'ccap_lesson', 'Rundreise Lektion', '<p>Lektionstext mit <strong>Betonung</strong></p>', $module );
		$repo   = QuestionRepository::current();
		$qids   = array();
		foreach ( array( 'Erste Rundreise-Frage', 'Zweite Rundreise-Frage' ) as $text ) {
			$id     = $repo->create(
				$admin,
				array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => $text,
					'answers'       => array(
						array(
							'key'     => 'A',
							'text'    => 'richtig',
							'correct' => true,
						),
						array(
							'key'  => 'B',
							'text' => 'falsch',
						),
					),
				)
			);
			$qids[] = $id;
		}

		// Media: image, poster and subtitles of the lesson, a licensed image of the first question.
		$db       = Database::current();
		$library  = MediaLibrary::current();
		$files    = NeutralFixture::media_files();
		$medium   = function ( string $name, string $mime, string $data, string $title ) use ( $admin, $library ): int {
			$upload = wp_upload_bits( $name, null, $data );
			self::assertEmpty( $upload['error'], (string) $upload['error'] );
			$id = (int) wp_insert_attachment(
				array(
					'post_title'     => $title,
					'post_mime_type' => $mime,
					'post_status'    => 'inherit',
				),
				$upload['file']
			);
			return $library->register_attachment( $admin, $id );
		};
		$suffix   = strtolower( wp_generate_password( 6, false, false ) );
		$image    = $medium( 'rt-bild-' . $suffix . '.png', 'image/png', $files['a1-003.png'], 'Rundreise Bild' );
		$poster   = $medium( 'rt-poster-' . $suffix . '.png', 'image/png', $files['a1-003.png'], 'Rundreise Vorschaubild' );
		$captions = $medium( 'rt-untertitel-' . $suffix . '.vtt', 'text/vtt', $files['alpha-04.vtt'], 'Rundreise Untertitel' );
		$licensed = $medium( 'rt-foto-' . $suffix . '.pdf', 'application/pdf', $files['fixture.pdf'], 'Rundreise Blatt' );
		$blocked  = $medium( 'rt-gesperrt-' . $suffix . '.png', 'image/png', $files['a1-003.png'] . 'x', 'Gesperrt' );
		$text     = $medium( 'rt-notiz-' . $suffix . '.txt', 'text/plain', "Notiz\n", 'Notiz' );
		$media    = $db->table( 'ccap_media' );
		$db->execute( $db->prepare( "UPDATE `{$media}` SET alt_text = %s WHERE id = %d", 'Farbfläche mit Beschriftung', $image ) );
		$db->execute(
			$db->prepare(
				"UPDATE `{$media}` SET rights_status = 'licensed', rights_owner = %s, license_reference = %s WHERE id = %d",
				'Bildarchiv Beispiel',
				(string) wp_json_encode(
					array(
						'license'     => 'CC-BY-4.0',
						'attribution' => 'Bild: Bildarchiv Beispiel',
					)
				),
				$licensed
			)
		);
		$db->execute( $db->prepare( "UPDATE `{$media}` SET rights_status = 'restricted' WHERE id = %d", $blocked ) );
		$library->attach( $admin, $image, 'lesson', $lesson, 'image', 1 );
		$library->attach( $admin, $poster, 'lesson', $lesson, 'poster', 2 );
		$library->attach( $admin, $captions, 'lesson', $lesson, 'subtitle', 3 );
		$library->attach( $admin, $blocked, 'lesson', $lesson, 'image', 4 );
		$library->attach( $admin, $text, 'lesson', $lesson, 'attachment', 5 );
		$revision = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $qids[0] ) );
		$library->attach( $admin, $licensed, 'question_revision', $revision, 'download', 1 );
		foreach ( $qids as $id ) {
			$repo->publish( $admin, $id );
			$repo->add_to_pool( $admin, $module, $id );
		}
		self::assertSame( $revision, (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $qids[0] ) ), 'publishing keeps the revision with its media' );

		// Exam rule with a module draw (2 points per question), a labelled part, form mode, an override and a frozen variant.
		$rules = ExamRules::current();
		$rule  = $rules->create(
			$admin,
			$course,
			'Rundreise 1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
				'parts'     => array(
					array(
						'key'       => 'T1',
						'label'     => 'Teil eins',
						'section'   => $module,
						'min_ratio' => array(
							'num' => 1,
							'den' => 2,
						),
					),
				),
			),
			null,
			array( 'overrides' => array( 'exams.max_attempts' => 3 ) )
		);
		$rules->set_blueprint( $admin, $rule, $module, 2, 2 );
		$rules->set_form_mode( $admin, $rule, 'variants' );
		$rules->publish( $admin, $rule );
		$rules->create_variant( $admin, $course, 1, array( $qids[1], $qids[0] ) );

		$first = PackageExporter::collect( $admin, array( $course ) );
		$ids   = static fn( array $records ): array => array_column( $records, 'source_id' );
		$uid   = static fn( int $id ): string => (string) $db->scalar( $db->prepare( "SELECT uid FROM `{$media}` WHERE id = %d", $id ) );
		self::assertEqualsCanonicalizing(
			array(
				array(
					'entity'    => 'media',
					'source_id' => $uid( $blocked ),
					'reason'    => 'restricted',
				),
				array(
					'entity'    => 'media',
					'source_id' => $uid( $text ),
					'reason'    => 'unsupported_type',
				),
			),
			array_values( array_filter( $first['export']['excluded'], static fn( array $e ): bool => 'media' === $e['entity'] ) )
		);
		self::assertCount( 2, $first['export']['excluded'] );
		self::assertSame( array( 1, 1, 1, 2, 4, 1 ), array( count( $first['records']['courses'] ), count( $first['records']['modules'] ), count( $first['records']['lessons'] ), count( $first['records']['questions'] ), count( $first['records']['media'] ), count( $first['records']['blueprints'] ) ) );
		self::assertSame( array( $uid( $image ), $uid( $poster ), $uid( $captions ), $uid( $licensed ) ), $ids( $first['records']['media'] ) );
		self::assertSame(
			array(
				array(
					'media' => $uid( $image ),
					'role'  => 'primary',
					'sort'  => 1,
				),
				array(
					'media' => $uid( $poster ),
					'role'  => 'poster',
					'sort'  => 2,
				),
				array(
					'media' => $uid( $captions ),
					'role'  => 'caption',
					'sort'  => 3,
				),
			),
			$first['records']['lessons'][0]['media'],
			'left-out media drop their links'
		);
		$by_id = array_column( $first['records']['media'], null, 'source_id' );
		self::assertSame( 'captions', $by_id[ $uid( $captions ) ]['media_type'] );
		self::assertSame( 'document', $by_id[ $uid( $licensed ) ]['media_type'] );
		self::assertSame( 'protected', $by_id[ $uid( $image ) ]['access'] );
		self::assertSame( 'Farbfläche mit Beschriftung', $by_id[ $uid( $image ) ]['alt_text'] );
		self::assertTrue( $by_id[ $uid( $poster ) ]['decorative'], 'an image without alternative text is exported as decorative' );
		self::assertSame(
			array(
				'status'      => 'licensed',
				'owner'       => 'Bildarchiv Beispiel',
				'license'     => 'CC-BY-4.0',
				'attribution' => 'Bild: Bildarchiv Beispiel',
			),
			$by_id[ $uid( $licensed ) ]['rights']
		);
		self::assertSame( hash( 'sha256', $files['fixture.pdf'] ), $by_id[ $uid( $licensed ) ]['sha256'] );
		self::assertSame( strlen( $files['fixture.pdf'] ), $by_id[ $uid( $licensed ) ]['size'] );
		self::assertSame(
			array(
				array(
					'media' => $uid( $licensed ),
					'role'  => 'primary',
					'sort'  => 1,
				),
			),
			$first['records']['questions'][0]['media']
		);
		$blueprint = $first['records']['blueprints'][0];
		self::assertSame( $first['records']['courses'][0]['source_id'], $blueprint['course'] );
		self::assertSame( 'published', $blueprint['status'] );
		self::assertSame(
			array(
				array(
					'module' => $first['records']['modules'][0]['source_id'],
					'count'  => 2,
					'points' => 2,
				),
			),
			$blueprint['draws']
		);
		self::assertSame( 'Teil eins', $blueprint['passing_rule']['parts'][0]['label'] );
		self::assertSame(
			array(
				'purpose'   => 'evidence',
				'form_mode' => 'variants',
				'overrides' => array( 'exams.max_attempts' => 3 ),
			),
			$blueprint['settings']
		);
		self::assertSame(
			array(
				array(
					'key'       => '1',
					'questions' => array( $first['records']['questions'][1]['source_id'], $first['records']['questions'][0]['source_id'] ),
				),
			),
			$blueprint['fixed_variants']
		);
		self::assertSame( 1, PackageExporter::schema_minor( $first['records'] ), 'poster role and blueprint extras need schema 1.1' );
		$p1    = PackageExporter::write( $first['records'], 'site-rt', 'Rundreise', $this->dir . '/p1.zip', PackageExporter::RECORDS_PER_FILE, $first['media'], true, $first['export'], $first['warnings'] );
		$check = PackageValidator::validate( $p1 );
		self::assertTrue( $check['ok'], (string) wp_json_encode( $check['errors'] ) );
		self::assertSame( '1.1.0', $check['manifest']['schema_version'] );
		self::assertSame( array(), $check['manifest']['report']['warnings'] );
		self::assertSame( 4, count( array_filter( $check['manifest']['files'], static fn( array $f ): bool => str_starts_with( $f['path'], 'media/' ) ) ), 'four media files in the archive' );

		// Empty the site of the course (stands in for the empty target site;
		// the hand-built questions, media and exam rule stay as orphans, the import creates its own).
		$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_course_module' ) . '` WHERE course_id = %d', $course ) );
		$guard = has_filter( 'pre_delete_post', array( DeletionGuard::class, 'filter' ) );
		if ( false !== $guard ) {
			remove_filter( 'pre_delete_post', array( DeletionGuard::class, 'filter' ), (int) $guard );
		}
		try {
			foreach ( array( $lesson, $module, $course ) as $id ) {
				self::assertNotFalse( wp_delete_post( $id, true ) );
			}
		} finally {
			if ( false !== $guard ) {
				add_filter( 'pre_delete_post', array( DeletionGuard::class, 'filter' ), (int) $guard, 2 );
			}
		}

		$service = ImportService::current();
		$batch   = $service->upload( $admin, $p1 );
		self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
		$plan = $service->preview( $admin, $batch )['plan'];
		foreach ( array( 'courses', 'modules', 'lessons', 'questions', 'media', 'blueprints' ) as $entity ) {
			self::assertSame( array( 'create' ), array_keys( $plan[ $entity ] ), $entity );
		}
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		self::assertSame( 'published', $service->get( $batch )['status'] );
		$imported = self::object( 'course', (string) $first['records']['courses'][0]['source_id'], 'site-rt' );
		self::assertGreaterThan( 0, $imported );
		self::assertNotSame( $course, $imported );
		$new_lesson = self::object( 'lesson', (string) $first['records']['lessons'][0]['source_id'], 'site-rt' );
		self::assertNotSame( $lesson, $new_lesson, 'no UID matching: a new lesson' );
		self::assertSame(
			array( 'image', 'poster', 'subtitle' ),
			array_column( $library->media_of( 'lesson', $new_lesson ), 'role' ),
			'lesson media with their stored roles'
		);
		$new_rule = self::object( 'blueprint', (string) $blueprint['source_id'], 'site-rt' );
		$stored   = (array) $rules->get( $new_rule );
		self::assertSame( 'published', $stored['status'] );
		self::assertSame( $imported, (int) $stored['course_id'] );
		self::assertSame( 'variants', ExamRules::form_mode( $stored ) );
		self::assertSame( 3, $stored['settings_stored']['overrides']['exams.max_attempts'] );
		self::assertSame( array( 'points' => 2 ), json_decode( (string) $stored['blueprints'][0]['rules'], true ) );
		$variants = $rules->variants( $imported, false );
		self::assertCount( 1, $variants );
		self::assertSame( 'published', $variants[0]['status'] );
		self::assertSame( array( self::object( 'question', (string) $first['records']['questions'][1]['source_id'], 'site-rt' ), self::object( 'question', (string) $first['records']['questions'][0]['source_id'], 'site-rt' ) ), $variants[0]['questions'] );

		$second = PackageExporter::collect( $admin, array( $imported ) );
		self::assertSame( array(), $second['export']['excluded'], 'own export: not foreign; left-out media were never imported' );
		$p2       = PackageExporter::write( $second['records'], 'site-rt', 'Rundreise', $this->dir . '/p2.zip', PackageExporter::RECORDS_PER_FILE, $second['media'], true, $second['export'], $second['warnings'] );
		$manifest = static function ( string $file ): array {
			$zip = new \ZipArchive();
			$zip->open( $file, \ZipArchive::RDONLY );
			$out = (array) json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
			$zip->close();
			return $out;
		};
		self::assertSame( (string) wp_json_encode( $first['records'] ), (string) wp_json_encode( $second['records'] ), 'same records after the round trip' );
		self::assertSame( $manifest( $p1 )['content_sha256'], $manifest( $p2 )['content_sha256'] );
		self::assertSame( $manifest( $p1 )['package_id'], $manifest( $p2 )['package_id'] );
		self::assertSame( $manifest( $p1 )['files'], $manifest( $p2 )['files'] );

		$again = $service->upload( $admin, $p2 );
		$service->validate( $admin, $again );
		$plan = $service->preview( $admin, $again )['plan'];
		foreach ( array( 'courses', 'modules', 'lessons', 'questions', 'media', 'blueprints' ) as $entity ) {
			self::assertSame( array( 'unchanged' ), array_keys( $plan[ $entity ] ), $entity );
		}
	}

	/**
	 * IMP-030 (E-382): without schema 1.1 fields the export stays at
	 * schema 1.0 (importable on older sites), and a package that would
	 * exceed the import limits is refused with `ccap_export_too_large`.
	 *
	 * @group IMP-030
	 * @group E-382
	 */
	public function test_IMP_030_schema_minor_and_limits(): void {
		$records = self::records( 1 );
		self::assertSame( 0, PackageExporter::schema_minor( $records ) );
		$file = $this->package( $records, 'minor' );
		self::assertSame( '1.0.0', PackageValidator::validate( $file )['manifest']['schema_version'] );
		$records['lessons'][0]['media'] = array(
			array(
				'media' => 'M-1',
				'role'  => 'poster',
				'sort'  => 1,
			),
		);
		self::assertSame( 1, PackageExporter::schema_minor( $records ) );
		self::assertSame( 1, PackageValidator::SCHEMA_MINOR );
		self::assertSame( 'poster', ImportMedia::role( 'poster', 'image' ), 'schema 1.1: stored roles are taken over' );
		self::assertSame( 'image', ImportMedia::role( 'primary', 'image' ), 'schema 1.0 roles keep their mapping' );
		$this->assertRejected(
			fn() => PackageExporter::write( self::records( 1 ), self::SYSTEM, 'Zu groß', $this->dir . '/big.zip', 1, array_fill_keys( array_map( static fn( int $i ): string => 'media/x/' . $i . '.vtt', range( 1, PackageValidator::MAX_ENTRIES ) ), "WEBVTT\n" ) ),
			'ccap_export_too_large'
		);
		self::assertFileDoesNotExist( $this->dir . '/big.zip' );
	}
}
