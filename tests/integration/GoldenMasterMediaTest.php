<?php
/**
 * R1-18: Golden-Master media and blueprint import on `cc-fixture-neutral-v1`
 * (R0-05 GM-118, GM-125, GM-128; R0-10 §1, §3.2, §3.5, §4.1; R0-38 §4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Import\ImportRunner;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\MediaQuarantine;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

/**
 * Each test loads the fixture itself (idempotent, D-03). Packages that must
 * be imported from scratch use their own `source.system`, so every record
 * is a `create` next to the loaded fixture.
 *
 * @group R1-18
 */
final class GoldenMasterMediaTest extends SchemaTestCase {

	/**
	 * Loaded fixture.
	 *
	 * @var array{users: array<string, int>, courses: array<string, int>, modules: array<string, int>, rules: array<string, int>, variants: array<string, int>, media: array<string, int>, pages: array<string, int>, groups: array<string, int>}
	 */
	private array $fx;

	/**
	 * Scratch directory.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Files created in the storage folders by a test.
	 *
	 * @var list<string>
	 */
	private array $cleanup = array();

	/**
	 * Load the fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->dir = get_temp_dir() . 'ccap-fx-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->dir );
		$this->fx = NeutralFixture::load( $this->dir );
	}

	/**
	 * Reset test hooks and remove scratch files.
	 */
	public function tear_down(): void {
		ImportRunner::media_chunk_bytes( null );
		foreach ( array_reverse( $this->cleanup ) as $path ) {
			if ( is_link( $path ) || is_file( $path ) ) {
				wp_delete_file( $path );
			} elseif ( is_dir( $path ) ) {
				@rmdir( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
			}
		}
		parent::tear_down();
	}

	/**
	 * GM-118 on PKG-v1: validated → run step and approval before the dry run
	 * refused → start, run step and run without approval refused →
	 * approved → published; the imported objects equal the manifest counts
	 * (2 courses, 5 question modules + 3 lesson modules, 71 questions incl.
	 * 1 draft, 4 media files, 2 rule sets, 4 fixed variants, 13 flashcards,
	 * 12 lessons incl. the archived L-A-10).
	 *
	 * @group GM-118
	 */
	public function test_GM_118_package_path_and_counts(): void {
		$system  = 'cc-fx-gm-118';
		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$file    = NeutralFixture::package( 1, $this->dir . '/pkg-v1-gm118.zip', $system );
		$batch   = $service->upload( $admin, $file );
		self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
		foreach ( array( fn() => ImportRunner::current()->step( $admin, $batch, 0.0 ), fn() => $service->approve( $admin, $batch ) ) as $call ) {
			try {
				$call();
				self::fail( 'run step or approval before the dry run' );
			} catch ( CourseException $e ) {
				self::assertSame( array( 409, 'ccap_import_state' ), array( $e->status, $e->error_code ) );
			}
		}
		self::assertSame( 0, (int) Database::current()->scalar( Database::current()->prepare( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_import_item_log' ) . '` WHERE batch_id = %d', $batch ) ), 'nothing written' );
		$manifest = self::manifest( $file );
		self::assertSame(
			array(
				'blueprints' => 2,
				'courses'    => 2,
				'flashcards' => 13,
				'lessons'    => 12,
				'media'      => 4,
				'modules'    => 8,
				'questions'  => 71,
			),
			self::sorted( $manifest['counts'] )
		);
		self::assertCount( 4, array_filter( $manifest['files'], static fn( array $f ): bool => str_starts_with( $f['path'], 'media/' ) ), '4 media files in the package' );

		$preview = $service->preview( $admin, $batch );
		self::assertSame( 'previewed', $preview['status'] );
		$created = array();
		foreach ( $preview['plan'] as $entity => $actions ) {
			$created[ $entity ] = $actions['create'] ?? 0;
		}
		self::assertSame( self::sorted( $manifest['counts'] ), self::sorted( $created ), 'dry run: every record is a create' );

		// No run without approval (R0-10 §3.4: runStep only from approved/importing),
		// neither the start, nor a single run step, nor the synchronous run.
		foreach ( array(
			fn() => ImportRunner::current()->start( $admin, $batch, false ),
			fn() => ImportRunner::current()->step( $admin, $batch, 0.0 ),
			fn() => ImportRunner::current()->step( $admin, $batch, null ),
			fn() => $service->run( $admin, $batch ),
		) as $call ) {
			try {
				$call();
				self::fail( 'run without approval' );
			} catch ( CourseException $e ) {
				self::assertSame( array( 409, 'ccap_import_state' ), array( $e->status, $e->error_code ) );
			}
		}
		self::assertSame( 'previewed', $service->get( $batch )['status'] );

		$service->approve( $admin, $batch );
		$summary = $service->run( $admin, $batch );
		self::assertSame( 'published', $service->get( $batch )['status'] );
		foreach ( $manifest['counts'] as $entity => $count ) {
			self::assertSame( array( 'create' => $count ), $summary[ $entity ], $entity );
		}

		$db    = Database::current();
		$num   = static fn( string $sql, ...$args ): int => (int) $db->scalar( $db->prepare( $sql, ...$args ) );
		$map   = $db->table( 'ccap_source_map' );
		$types = array();
		foreach ( $db->rows( $db->prepare( "SELECT object_type, COUNT(*) AS n FROM `{$map}` WHERE source_system = %s GROUP BY object_type", $system ) ) as $row ) {
			$types[ (string) $row['object_type'] ] = (int) $row['n'];
		}
		self::assertSame(
			array(
				'blueprint' => 2,
				'course'    => 2,
				'flashcard' => 13,
				'lesson'    => 12,
				'media'     => 4,
				'module'    => 8,
				'question'  => 71,
			),
			self::sorted( $types )
		);

		// Questions: 70 published + Q-A3-011 draft; 13 cards.
		$q = $db->table( 'ccap_question' );
		self::assertSame( 70, $num( "SELECT COUNT(*) FROM `{$q}` q JOIN `{$map}` m ON m.object_id = q.id AND m.object_type = 'question' WHERE m.source_system = %s AND q.status = 'published'", $system ) );
		self::assertSame( 13, $num( "SELECT COUNT(*) FROM `{$q}` q JOIN `{$map}` m ON m.object_id = q.id AND m.object_type = 'flashcard' WHERE m.source_system = %s AND q.status = 'published'", $system ) );

		// Modules: 5 with a question pool (M-A1…M-A3, M-B1, M-B2) and 3 lesson modules (LM-A1…LM-A3).
		$pool = $db->table( 'ccap_module_question' );
		self::assertSame( 5, $num( "SELECT COUNT(DISTINCT p.module_id) FROM `{$pool}` p JOIN `{$map}` m ON m.object_id = p.module_id AND m.object_type = 'module' WHERE m.source_system = %s", $system ) );

		// Lessons: 11 published, L-A-10 archived (R0-05 §2.2).
		$posts    = $db->wpdb()->posts;
		$statuses = array();
		foreach ( $db->rows( $db->prepare( "SELECT p.post_status, COUNT(*) AS n FROM `{$posts}` p JOIN `{$map}` m ON m.object_id = p.ID AND m.object_type = 'lesson' WHERE m.source_system = %s GROUP BY p.post_status", $system ) ) as $row ) {
			$statuses[ (string) $row['post_status'] ] = (int) $row['n'];
		}
		self::assertSame(
			array(
				'ccap_archived' => 1,
				'publish'       => 11,
			),
			self::sorted( $statuses )
		);
		self::assertSame( 2, $num( "SELECT COUNT(*) FROM `{$posts}` p JOIN `{$map}` m ON m.object_id = p.ID AND m.object_type = 'course' WHERE m.source_system = %s AND p.post_status = 'publish'", $system ) );

		// Media: 4 rows, files present with their checksum, protected ones only in the protected folder.
		$media = $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_media' ) . "` WHERE source_system = %s AND status = 'active' ORDER BY source_id", $system ) );
		self::assertSame( array( 'cc-fx-med-001', 'cc-fx-med-002', 'cc-fx-med-003', 'cc-fx-med-004' ), array_column( $media, 'source_id' ) );
		$files = NeutralFixture::media_files();
		foreach ( $media as $row ) {
			$path = (string) get_attached_file( (int) $row['attachment_id'], true );
			self::assertFileExists( $path );
			self::assertSame( (string) $row['sha256'], hash_file( 'sha256', $path ) );
			self::assertSame( hash( 'sha256', $files[ wp_basename( (string) $row['package_path'] ) ] ), (string) $row['sha256'] );
			self::assertSame( 'protected' === $row['access'], ProtectedStorage::is_protected_path( $path ), (string) $row['source_id'] );
			self::assertSame( 'licensed', (string) $row['rights_status'], 'foreign own → licensed (E-62)' );
		}
		self::assertSame( array( 'public', 'protected', 'protected', 'protected' ), array_column( $media, 'access' ) );
		// MED-001 is the image of Q-A1-003; MED-002/MED-003 belong to L-A-04.
		$cm = $db->table( 'ccap_content_media' );
		self::assertSame( 3, $num( "SELECT COUNT(*) FROM `{$cm}` c JOIN `" . $db->table( 'ccap_media' ) . '` m ON m.id = c.media_id WHERE m.source_system = %s', $system ) );

		// Rules: ER-A (3 600 s, 7/13/10) and ER-B (practice, no limit) published; 4 fixed variants.
		$rules = array();
		foreach ( $db->rows( $db->prepare( 'SELECT id, version, status FROM `' . $db->table( 'ccap_exam_rule' ) . '` WHERE source_system = %s ORDER BY version', $system ) ) as $row ) {
			$rules[ (string) $row['version'] ] = (array) ExamRules::current()->get( (int) $row['id'] );
		}
		self::assertSame( array( 'cc-fx-er-a', 'cc-fx-er-b' ), array_keys( $rules ) );
		self::assertSame( array( 'published', 'published' ), array_column( $rules, 'status' ) );
		self::assertSame( 3600, (int) $rules['cc-fx-er-a']['time_limit_seconds'] );
		self::assertSame( array( 7, 13, 10 ), array_map( static fn( array $b ): int => (int) $b['question_count'], (array) $rules['cc-fx-er-a']['blueprints'] ) );
		self::assertSame( 'practice', $rules['cc-fx-er-b']['settings_stored']['purpose'] );
		$variants = $db->rows( $db->prepare( 'SELECT v.source_id, v.status, COUNT(q.id) AS n FROM `' . $db->table( 'ccap_exam_variant' ) . '` v JOIN `' . $db->table( 'ccap_exam_variant_question' ) . '` q ON q.variant_id = v.id WHERE v.source_system = %s GROUP BY v.id ORDER BY v.source_id', $system ) );
		self::assertSame( array( 'cc-fx-er-a#EP-A-1', 'cc-fx-er-a#EP-A-2', 'cc-fx-er-a#EP-A-3', 'cc-fx-er-b#EP-B-1' ), array_column( $variants, 'source_id' ) );
		self::assertSame( array( '30', '30', '30', '10' ), array_map( 'strval', array_column( $variants, 'n' ) ) );
		self::assertSame( array( 'published' ), array_values( array_unique( array_column( $variants, 'status' ) ) ) );
	}

	/**
	 * GM-125 on PKG-v1: the dry run in partial steps gives the same result as
	 * at once and ends in `previewed`; the commit writes media by bytes with
	 * at least one file per chunk.
	 *
	 * @group GM-125
	 */
	public function test_GM_125_steps_equal_whole_and_media_by_bytes(): void {
		$system  = 'cc-fx-gm-125';
		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$file    = NeutralFixture::package( 1, $this->dir . '/pkg-v1-gm125.zip', $system );

		$whole = $service->upload( $admin, $file );
		$service->validate( $admin, $whole );
		$at_once = $service->preview( $admin, $whole );

		$parted = $service->upload( $admin, $file );
		$service->validate( $admin, $parted );
		$steps = array();
		$count = 0;
		do {
			$step    = $service->preview_step( $admin, $parted, 0.0 );
			$steps[] = $step;
			if ( ! $step['done'] ) {
				self::assertSame( 'validated', $service->get( $parted )['status'], 'no approval while the dry run is in progress' );
				self::assertNotEmpty( ( (array) json_decode( (string) $service->get( $parted )['stats'], true ) )['preview_progress'] ?? null );
			}
			++$count;
		} while ( ! $step['done'] && $count < 100 );
		self::assertGreaterThan( 7, count( $steps ), 'one unit per step with a zero budget' );
		$units = array_column( $steps, 'units_done' );
		self::assertSame( range( 1, count( $steps ) ), $units, 'at least one unit per step, monotonic' );
		self::assertSame( 'previewed', $step['status'] );
		self::assertSame( 'previewed', $service->get( $parted )['status'] );
		self::assertSame( $at_once['plan'], $step['plan'] );
		self::assertSame( $at_once['items'], $step['items'] );
		foreach ( array( 'records', 'items', 'files', 'object_errors', 'issues_stored', 'rules', 'threshold' ) as $key ) {
			self::assertSame( $at_once['stats'][ $key ], $step['stats'][ $key ], $key );
		}
		self::assertSame( 4, $step['plan']['media']['create'] );

		// Commit: media chunks by bytes; a limit below one file still writes one file per chunk.
		$service->approve( $admin, $parted );
		ImportRunner::media_chunk_bytes( 1 );
		$runner = ImportRunner::current();
		$runner->start( $admin, $parted, false );
		$plan = $runner->step( $admin, $parted, 0.0 );
		self::assertSame( 'apply', $plan['phase'] );
		self::assertSame( 0, $plan['done'] );
		$done = 0;
		$db   = Database::current();
		$log  = $db->table( 'ccap_import_item_log' );
		foreach ( range( 1, 4 ) as $i ) {
			$progress = $runner->step( $admin, $parted, 0.0 );
			self::assertSame( $i, (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$log}` WHERE batch_id = %d AND entity = 'media'", $parted ) ), 'one media file in chunk ' . $i );
			if ( $i < 4 ) {
				self::assertSame( 1, $progress['done'] - $done, 'the next file would exceed the byte limit' );
			} else {
				self::assertGreaterThan( 1, $progress['done'] - $done, 'records after the last file continue in the same chunk' );
			}
			$done = $progress['done'];
		}
		for ( $i = 0; $i < 200 && 'progress' === $progress['outcome']; $i++ ) {
			$progress = $runner->step( $admin, $parted, 0.0 );
		}
		self::assertSame( 'published', $progress['status'] );
		self::assertSame( 4, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_media' ) . "` WHERE source_system = %s AND status = 'active'", $system ) ) );

		// Same package once more: media unchanged, no new rows or files.
		$before = (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_media' ) . '`' );
		$again  = $service->upload( $admin, $file );
		$service->validate( $admin, $again );
		$plan = $service->preview( $admin, $again )['plan'];
		self::assertSame( array( 'unchanged' => 4 ), $plan['media'] );
		self::assertSame( array( 'unchanged' => 2 ), $plan['blueprints'] );
		$service->approve( $admin, $again );
		$service->run( $admin, $again );
		self::assertSame( $before, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_media' ) . '`' ) );
	}

	/**
	 * GM-128 with MED-BAD-1…6: the PNG is imported; `.php`, `x.php.png`,
	 * SVG, PHP content and a MIME mismatch are rejected as objects (one log
	 * entry each, nothing stored, an image-required question is skipped);
	 * `.htaccess` fails the package (hidden path, V3); legacy files in the
	 * public media folder go to the quarantine once, with exactly one log
	 * entry.
	 *
	 * @group GM-128
	 * @group RBAC-076
	 */
	public function test_GM_128_media_types_and_quarantine(): void {
		$system  = 'cc-fx-gm-128';
		$service = ImportService::current();
		$admin   = Actor::user( $this->fx['users']['admin'] );
		$png     = NeutralFixture::media_files()['a1-003.png'];
		$bad     = NeutralFixture::bad_media( $this->dir );
		$media   = array( 'media/a1-003.png' => $png );
		$records = array( self::media_record( 'cc-fx-med-001', 'media/a1-003.png', $png, 'image/png' ) );
		foreach ( array( 'MED-BAD-1', 'MED-BAD-2', 'MED-BAD-3', 'MED-BAD-5', 'MED-BAD-6' ) as $key ) {
			$path           = 'media/' . $bad[ $key ]['name'];
			$data           = (string) file_get_contents( $bad[ $key ]['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Scratch file.
			$media[ $path ] = array(
				'data' => $data,
				'mime' => $bad[ $key ]['mime'],
			);
			$records[]      = self::media_record( 'cc-fx-' . strtolower( $key ), $path, $data, $bad[ $key ]['mime'] );
		}
		$question  = static fn( string $id, string $media, bool $required ): array => PackageExporter::sealed(
			array(
				'source_id' => $id,
				'revision'  => array(
					'question_type'  => 'single_choice',
					'text_html'      => 'Frage ' . $id,
					'settings'       => array(),
					'image_required' => $required,
				),
				'answers'   => array(
					array(
						'key'       => 'A',
						'text_html' => 'Ja',
						'correct'   => true,
					),
					array(
						'key'       => 'B',
						'text_html' => 'Nein',
						'correct'   => false,
					),
				),
				'media'     => array(
					array(
						'media' => $media,
						'role'  => 'primary',
					),
				),
			)
		);
		$file      = PackageExporter::write(
			array(
				'media'     => $records,
				'questions' => array(
					$question( 'cc-fx-q-gm128-ok', 'cc-fx-med-001', true ),
					$question( 'cc-fx-q-gm128-required', 'cc-fx-med-bad-2', true ),
					$question( 'cc-fx-q-gm128-optional', 'cc-fx-med-bad-5', false ),
				),
			),
			$system,
			'GM-128',
			$this->dir . '/gm128.zip',
			PackageExporter::RECORDS_PER_FILE,
			$media
		);
		$batch     = $service->upload( $admin, $file );
		$validated = $service->validate( $admin, $batch );
		self::assertSame( 'validated', $validated['status'], 'V4 is an object error (R0-38 class O)' );
		self::assertSame( array(), $validated['errors'] );
		self::assertSame(
			array(
				'media/x.php'     => 'V4_dangerous',
				'media/x.php.png' => 'V4_dangerous',
				'media/x.png'     => 'V4_mime_detected',
				'media/x.svg'     => 'V4_dangerous',
				'media/y.png'     => 'V4_mime_declared',
			),
			self::sorted( $validated['rejected'] )
		);

		$preview = $service->preview( $admin, $batch );
		self::assertSame( 'previewed', $preview['status'] );
		self::assertSame(
			array(
				'create' => 1,
				'error'  => 5,
			),
			$preview['plan']['media']
		);
		self::assertSame(
			array(
				'create' => 2,
				'error'  => 1,
			),
			$preview['plan']['questions'],
			'the image-required question depends on the rejected medium'
		);
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		self::assertSame( 'published', $service->get( $batch )['status'] );

		$db     = Database::current();
		$issues = $db->table( 'ccap_import_issue' );
		$rows   = $db->rows( $db->prepare( "SELECT source_id, rule, class, action FROM `{$issues}` WHERE batch_id = %d AND entity = 'media' ORDER BY source_id", $batch ) );
		self::assertSame( array( 'cc-fx-med-bad-1', 'cc-fx-med-bad-2', 'cc-fx-med-bad-3', 'cc-fx-med-bad-5', 'cc-fx-med-bad-6' ), array_column( $rows, 'source_id' ), 'exactly one log entry per rejected file' );
		self::assertSame( array( 'IMP-V4.02', 'IMP-V4.02', 'IMP-V4.02', 'IMP-V4.03', 'IMP-V4.03' ), array_column( $rows, 'rule' ) );
		self::assertSame( array( 'O' ), array_values( array_unique( array_column( $rows, 'class' ) ) ) );
		self::assertSame( array( 'rejected' ), array_values( array_unique( array_column( $rows, 'action' ) ) ) );
		$stored = $db->rows( $db->prepare( 'SELECT source_id, attachment_id FROM `' . $db->table( 'ccap_media' ) . '` WHERE source_system = %s', $system ) );
		self::assertSame( array( 'cc-fx-med-001' ), array_column( $stored, 'source_id' ) );
		foreach ( array( ProtectedStorage::dir( 'media' ), ProtectedStorage::dir( 'protected' ) . '/import' ) as $folder ) {
			foreach ( self::files( $folder ) as $path ) {
				self::assertNotContains( wp_basename( $path ), array( 'x.php', 'x.php.png', 'x.svg', 'x.png', 'y.png' ), 'rejected file never stored' );
			}
		}
		self::assertNotNull( NeutralFixture::object_in( $system, 'question', 'cc-fx-q-gm128-optional' ), 'reference to a rejected medium drops' );
		self::assertNull( NeutralFixture::object_in( $system, 'question', 'cc-fx-q-gm128-required' ) );

		// MED-BAD-4 `.htaccess`: hidden path, the whole package fails (V3).
		$htaccess = PackageExporter::write(
			array( 'media' => array( self::media_record( 'cc-fx-med-bad-4', 'media/.htaccess', "Options +ExecCGI\n", 'text/plain' ) ) ),
			$system,
			'GM-128 htaccess',
			$this->dir . '/gm128-htaccess.zip',
			PackageExporter::RECORDS_PER_FILE,
			array(
				'media/.htaccess' => array(
					'data' => "Options +ExecCGI\n",
					'mime' => 'text/plain',
				),
			)
		);
		$failed   = $service->upload( $admin, $htaccess );
		$result   = $service->validate( $admin, $failed );
		self::assertSame( 'failed', $result['status'] );
		self::assertContains( 'V3_path', array_column( $result['errors'], 'rule' ) );

		// Legacy files in the public media folder: quarantined once, one log entry.
		$legacy = ProtectedStorage::dir( 'media' ) . '/legacy-' . strtolower( wp_generate_password( 6, false, false ) );
		wp_mkdir_p( $legacy );
		$this->cleanup[] = $legacy;
		file_put_contents( $legacy . '/a1-003.png', $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Scratch file.
		$this->cleanup[] = $legacy . '/a1-003.png';
		foreach ( $bad as $file_def ) {
			copy( $file_def['path'], $legacy . '/' . $file_def['name'] );
		}
		delete_option( MediaQuarantine::OPTION );
		$audit  = static fn(): int => (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'media_quarantined'" );
		$before = $audit();
		$result = (array) MediaQuarantine::run_once();
		$prefix = wp_basename( $legacy ) . '/';
		$moved  = array_values( array_filter( $result['moved'], static fn( string $p ): bool => str_starts_with( $p, $prefix ) ) );
		sort( $moved );
		self::assertSame( array_map( static fn( string $n ): string => $prefix . $n, array( '.htaccess', 'x.php', 'x.php.png', 'x.png', 'x.svg', 'y.png' ) ), $moved );
		foreach ( $moved as $relative ) {
			$this->cleanup[] = ProtectedStorage::dir( 'packages' ) . '/quarantine/' . $relative;
			self::assertFileExists( ProtectedStorage::dir( 'packages' ) . '/quarantine/' . $relative );
			self::assertFileDoesNotExist( ProtectedStorage::dir( 'media' ) . '/' . $relative );
		}
		$this->cleanup[] = ProtectedStorage::dir( 'packages' ) . '/quarantine/' . wp_basename( $legacy );
		self::assertFileExists( $legacy . '/a1-003.png', 'allowed file stays' );
		self::assertFileExists( ProtectedStorage::dir( 'media' ) . '/.htaccess', 'own guard stays' );
		self::assertSame( $before + 1, $audit(), 'one log entry' );
		self::assertNull( MediaQuarantine::run_once(), 'runs once' );
		self::assertSame( $before + 1, $audit(), 'still one log entry' );
	}

	/**
	 * Media record with hash.
	 *
	 * @param string $id   Source ID.
	 * @param string $path Package path.
	 * @param string $data Content.
	 * @param string $mime Declared MIME type.
	 * @return array<string, mixed>
	 */
	private static function media_record( string $id, string $path, string $data, string $mime ): array {
		return PackageExporter::sealed(
			array(
				'source_id'  => $id,
				'path'       => $path,
				'media_type' => str_starts_with( $mime, 'image/' ) ? 'image' : 'document',
				'mime_type'  => $mime,
				'size'       => strlen( $data ),
				'sha256'     => hash( 'sha256', $data ),
				'alt_text'   => 'Bild ' . $id,
				'access'     => 'protected',
				'rights'     => array( 'status' => 'own' ),
			)
		);
	}

	/**
	 * Manifest of a package.
	 *
	 * @param string $file Package.
	 * @return array<string, mixed>
	 */
	private static function manifest( string $file ): array {
		$zip = new \ZipArchive();
		self::assertTrue( $zip->open( $file ) );
		$data = (array) json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		$zip->close();
		return $data;
	}

	/**
	 * Array sorted by key.
	 *
	 * @param array<string, mixed> $values Values.
	 * @return array<string, mixed>
	 */
	private static function sorted( array $values ): array {
		ksort( $values );
		return $values;
	}

	/**
	 * Files below a folder.
	 *
	 * @param string $dir Folder.
	 * @return list<string>
	 */
	private static function files( string $dir ): array {
		if ( ! is_dir( $dir ) ) {
			return array();
		}
		$out = array();
		foreach ( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $entry ) {
			$out[] = (string) $entry;
		}
		return $out;
	}
}
