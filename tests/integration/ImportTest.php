<?php
/**
 * R1-15: package validation, dry run, import, activation, archive, conflicts, rollback, export.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\PostTypes;
use CourseCaptain\Import\Canonical;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Import\PackageValidator;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Storage\Database;

/**
 * Real tables and archives.
 *
 * @group R1-15
 */
final class ImportTest extends SchemaTestCase {

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
		$this->dir   = get_temp_dir() . 'ccap-import-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->dir );
		PolicyService::flush();
	}

	/**
	 * Fixture records (version 1 or 2).
	 *
	 * @param int $version Version.
	 * @return array<string, list<array<string, mixed>>>
	 */
	private static function records( int $version = 1 ): array {
		$q       = static fn( string $id, string $text ): array => PackageExporter::sealed(
			array(
				'source_id' => $id,
				'revision'  => array(
					'question_type' => 'single_choice',
					'text_html'     => $text,
					'settings'      => (object) array(),
				),
				'answers'   => array(
					array(
						'key'       => 'A',
						'text_html' => 'right',
						'correct'   => true,
					),
					array(
						'key'       => 'B',
						'text_html' => 'wrong',
						'correct'   => false,
					),
				),
				'tags'      => array(),
			)
		);
		$lessons = array(
			PackageExporter::sealed(
				array(
					'source_id'     => 'L-1',
					'title'         => 'Lesson 1',
					'body_html'     => '<p>Text</p><script>alert(1)</script><a href="#" onclick="x()">Link</a>',
					'activity_type' => 'lesson',
					'settings'      => (object) array(),
				)
			),
		);
		if ( 1 === $version ) {
			$lessons[] = PackageExporter::sealed(
				array(
					'source_id'     => 'L-2',
					'title'         => 'Lesson 2',
					'body_html'     => '<p>Second</p>',
					'activity_type' => 'lesson',
					'settings'      => (object) array(),
				)
			);
		}
		return array(
			'questions' => array( $q( 'Q-1', 1 === $version ? 'What is A?' : 'What is A (corrected)?' ), $q( 'Q-2', 'Second question' ) ),
			'lessons'   => $lessons,
			'modules'   => array(
				PackageExporter::sealed(
					array(
						'source_id'     => 'M-1',
						'title'         => 'Module 1',
						'summary'       => '',
						'lessons'       => array_map(
							static fn( array $l, int $i ): array => array(
								'lesson' => $l['source_id'],
								'sort'   => $i,
							),
							$lessons,
							array_keys( $lessons )
						),
						'question_pool' => array(
							array(
								'question' => 'Q-1',
								'sort'     => 1,
							),
							array(
								'question' => 'Q-2',
								'sort'     => 2,
							),
						),
					)
				),
			),
			'courses'   => array(
				PackageExporter::sealed(
					array(
						'source_id'        => 'C-1',
						'title'            => 'Course 1',
						'slug'             => 'course-one',
						'summary'          => '',
						'description_html' => '<p>About</p>',
						'modules'          => array(
							array(
								'module'   => 'M-1',
								'sort'     => 10,
								'required' => true,
							),
						),
					)
				),
			),
		);
	}

	/**
	 * Run the whole path for a package; returns the batch.
	 *
	 * @param string $file    Package.
	 * @param bool   $partial Partial.
	 * @return int
	 */
	private function import( string $file, bool $partial = false ): int {
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $file );
		self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
		self::assertSame( 'previewed', $service->preview( $admin, $batch, $partial )['status'] );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		return $batch;
	}

	/**
	 * Object of a source ID.
	 *
	 * @param string $type   Type.
	 * @param string $source Source.
	 * @return int
	 */
	private static function object( string $type, string $source ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT object_id FROM `' . $db->table( 'ccap_source_map' ) . "` WHERE source_system = 'fixture-a' AND object_type = %s AND source_id = %s", $type, $source ) );
	}

	/**
	 * Write a raw zip.
	 *
	 * @param array<string, string> $entries Entries.
	 * @return string
	 */
	private function zip( array $entries ): string {
		$file = $this->dir . '/raw-' . wp_generate_password( 6, false ) . '.zip';
		$zip  = new \ZipArchive();
		$zip->open( $file, \ZipArchive::CREATE );
		foreach ( $entries as $name => $data ) {
			$zip->addFromString( $name, $data );
		}
		$zip->close();
		return $file;
	}

	/**
	 * V1–V5 and `requires`.
	 */
	public function test_validation(): void {
		$good = PackageExporter::write( self::records(), 'fixture-a', 'Fixture', $this->dir . '/good.zip' );
		self::assertTrue( PackageValidator::validate( $good )['ok'] );
		$rules = static fn( string $file ): array => array_column( PackageValidator::validate( $file )['errors'], 'rule' );

		file_put_contents( $this->dir . '/not.zip', 'plain text' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		self::assertSame( array( 'V1_archive' ), $rules( $this->dir . '/not.zip' ) );

		$zip = new \ZipArchive();
		$zip->open( $good );
		$manifest = (string) $zip->getFromName( 'manifest.json' );
		$zip->close();
		self::assertContains(
			'V3_path',
			$rules(
				$this->zip(
					array(
						'manifest.json' => $manifest,
						'../evil.php'   => 'x',
					)
				)
			)
		);
		self::assertContains(
			'V3_entry',
			$rules(
				$this->zip(
					array(
						'manifest.json'   => $manifest,
						'other/file.json' => '{}',
					)
				)
			)
		);
		self::assertContains( 'V5_file_missing', $rules( $this->zip( array( 'manifest.json' => $manifest ) ) ) );

		$data       = array( 'data/questions/000001.json' => '[]' );
		$m          = json_decode( $manifest, true );
		$m['files'] = array(
			array(
				'path'      => 'data/questions/000001.json',
				'size'      => 2,
				'mime_type' => 'application/json',
				'sha256'    => hash( 'sha256', 'xx' ),
			),
		);
		self::assertContains( 'V5_checksum', $rules( $this->zip( array( 'manifest.json' => (string) wp_json_encode( $m ) ) + $data ) ) );
		self::assertContains(
			'V5_unlisted',
			$rules(
				$this->zip(
					array(
						'manifest.json'              => $manifest,
						'data/questions/000009.json' => '[]',
					) + $this->entries( $good )
				)
			)
		);

		$m            = json_decode( $manifest, true );
		$m['files'][] = array(
			'path'      => 'media/x.php.png',
			'size'      => 3,
			'mime_type' => 'image/png',
			'sha256'    => hash( 'sha256', 'png' ),
		);
		self::assertContains(
			'V4_dangerous',
			$rules(
				$this->zip(
					array(
						'manifest.json'   => (string) wp_json_encode( $m ),
						'media/x.php.png' => 'png',
					) + $this->entries( $good )
				)
			)
		);

		$m                   = json_decode( $manifest, true );
		$m['format']         = 'other';
		$m['schema_version'] = '2.0.0';
		$broken              = $rules( $this->zip( array( 'manifest.json' => (string) wp_json_encode( $m ) ) + $this->entries( $good ) ) );
		self::assertContains( 'V5_format', $broken );
		self::assertContains( 'V5_major', $broken );

		$m                               = json_decode( $manifest, true );
		$m['requires']['question_types'] = array( 'pro_hotspot' );
		$service                         = ImportService::current();
		$batch                           = $service->upload( Actor::user( $this->admin ), $this->zip( array( 'manifest.json' => (string) wp_json_encode( $m ) ) + $this->entries( $good ) ) );
		self::assertSame( 'failed', $service->validate( Actor::user( $this->admin ), $batch )['status'], 'IMP-021' );

		$subscriber = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->expectException( CourseException::class );
		$service->upload( $subscriber, $good );
	}

	/**
	 * Data entries of a package.
	 *
	 * @param string $file Package.
	 * @return array<string, string>
	 */
	private function entries( string $file ): array {
		$zip = new \ZipArchive();
		$zip->open( $file );
		$out   = array();
		$count = $zip->count();
		for ( $i = 0; $i < $count; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( 'manifest.json' !== $name ) {
				$out[ $name ] = (string) $zip->getFromName( $name );
			}
		}
		$zip->close();
		return $out;
	}

	/**
	 * Full path: create → unchanged → update/archive/conflict → rollback.
	 */
	public function test_import_lifecycle(): void {
		$v1    = PackageExporter::write( self::records( 1 ), 'fixture-a', 'Fixture', $this->dir . '/v1.zip' );
		$first = $this->import( $v1 );
		$batch = (array) ImportService::current()->get( $first );
		self::assertSame( 'published', $batch['status'] );
		$course = self::object( 'course', 'C-1' );
		self::assertSame( 'publish', get_post_status( $course ) );
		self::assertNotNull( CoursePublisher::current()->current_revision( $course ) );
		self::assertSame( array( self::object( 'module', 'M-1' ) ), array_column( CourseStructure::current()->modules( $course ), 'module_id' ) );
		$lesson = get_post( self::object( 'lesson', 'L-1' ) );
		self::assertSame( self::object( 'module', 'M-1' ), (int) $lesson->post_parent );
		self::assertStringNotContainsString( '<script', $lesson->post_content, 'HTML filter' );
		self::assertStringNotContainsString( 'onclick', $lesson->post_content );
		$q1  = self::object( 'question', 'Q-1' );
		$db  = Database::current();
		$rev = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q1 ) );
		self::assertSame( 'What is A?', QuestionRepository::current()->revision( $rev )['question_text'] );
		self::assertSame( 'published', $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q1 ) ) );

		// Same package again: everything unchanged, no new course revision.
		$revision = (int) CoursePublisher::current()->current_revision( $course )['id'];
		$service  = ImportService::current();
		$admin    = Actor::user( $this->admin );
		$again    = $service->upload( $admin, $v1 );
		$service->validate( $admin, $again );
		$plan = $service->preview( $admin, $again )['plan'];
		self::assertSame( array( 'unchanged' => 2 ), $plan['questions'] );
		self::assertSame( array( 'unchanged' => 1 ), $plan['courses'] );
		$service->approve( $admin, $again );
		$service->run( $admin, $again );
		self::assertSame( $revision, (int) CoursePublisher::current()->current_revision( $course )['id'] );
		self::assertSame( 'superseded', ImportService::current()->get( $first )['status'] );

		// v2: question changed, lesson 2 removed, module edited locally → conflict.
		wp_update_post(
			array(
				'ID'         => self::object( 'module', 'M-1' ),
				'post_title' => 'Module 1 (local)',
			)
		);
		$records                          = self::records( 2 );
		$records['modules'][0]['summary'] = 'changed upstream';
		$records['modules'][0]            = PackageExporter::sealed( array_diff_key( $records['modules'][0], array( 'hash' => 1 ) ) );
		$v2                               = PackageExporter::write( $records, 'fixture-a', 'Fixture v2', $this->dir . '/v2.zip' );
		$b2                               = $service->upload( $admin, $v2 );
		$service->validate( $admin, $b2 );
		$plan = $service->preview( $admin, $b2 )['plan'];
		self::assertSame( 1, $plan['questions']['update'] );
		self::assertSame(
			array(
				'unchanged' => 1,
				'archive'   => 1,
			),
			$plan['lessons']
		);
		self::assertSame( array( 'conflict' => 1 ), $plan['modules'] );
		$service->approve( $admin, $b2 );
		$service->run( $admin, $b2 );
		self::assertSame( PostTypes::STATUS_ARCHIVED, get_post_status( self::object( 'lesson', 'L-2' ) ), 'archived, not deleted' );
		self::assertSame( 'Module 1 (local)', get_the_title( self::object( 'module', 'M-1' ) ), 'local change kept' );
		$rev2 = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q1 ) );
		self::assertNotSame( $rev, $rev2 );
		self::assertSame( 'What is A (corrected)?', QuestionRepository::current()->revision( $rev2 )['question_text'] );

		// Rollback to v1: new batch through the normal path; old hash active, lesson restored.
		$this->assertRejected( fn() => $service->rollback( $admin, $first, '  ' ), 'ccap_validation_failed' );
		$rb = $service->rollback( $admin, $first, 'Wrong correction' );
		self::assertSame( 'uploaded', $service->get( $rb )['status'] );
		$service->validate( $admin, $rb );
		$service->preview( $admin, $rb );
		$service->approve( $admin, $rb );
		$service->run( $admin, $rb );
		$rev3 = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q1 ) );
		self::assertSame( $rev, $rev3, 'known hash reactivated' );
		self::assertSame( 'publish', get_post_status( self::object( 'lesson', 'L-2' ) ) );
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'import_rollback_started'" ) );
	}

	/**
	 * V6 object errors, threshold, state machine.
	 */
	public function test_object_errors_and_states(): void {
		$records                            = self::records();
		$records['modules'][0]['lessons'][] = array(
			'lesson' => 'L-missing',
			'sort'   => 9,
		);
		$records['modules'][0]              = PackageExporter::sealed( array_diff_key( $records['modules'][0], array( 'hash' => 1 ) ) );
		$records['questions'][1]['hash']    = 'sha256:' . str_repeat( '0', 64 );
		$file                               = PackageExporter::write( $records, 'fixture-a', 'Broken', $this->dir . '/broken.zip' );
		$service                            = ImportService::current();
		$admin                              = Actor::user( $this->admin );
		$batch                              = $service->upload( $admin, $file );
		$this->assertRejected( fn() => $service->approve( $admin, $batch ), 'ccap_import_state' );
		$service->validate( $admin, $batch );
		$preview = $service->preview( $admin, $batch );
		self::assertSame( 'previewed', $preview['status'], 'object errors below the threshold' );
		$rules = array_column( array_filter( $preview['items'], static fn( array $i ): bool => null !== $i['rule'] ), 'rule', 'source_id' );
		self::assertSame( 'hash_mismatch', $rules['Q-2'] );
		self::assertSame( 'dependency_failed', $rules['M-1'], 'module references the broken question' );
		self::assertSame( 'dependency_failed', $rules['C-1'] );
		$db = Database::current();
		self::assertSame( '3', (string) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_import_issue' ) . '` WHERE batch_id = %d', $batch ) ) );
		self::assertSame( 0, self::object( 'course', 'C-1' ), 'dry run writes nothing' );
		$service->discard( $admin, $batch );
		self::assertSame( 'discarded', $service->get( $batch )['status'] );
		$this->assertRejected( fn() => $service->preview( $admin, $batch ), 'ccap_import_state' );
	}

	/**
	 * Export: package of an authored course validates and contains no learner data.
	 */
	public function test_export(): void {
		$admin  = Actor::user( $this->admin );
		$course = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Authored',
			)
		);
		$module = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_module',
				'post_status' => 'publish',
				'post_title'  => 'M',
			)
		);
		CourseStructure::current()->assign_module( $admin, $course, $module );
		wp_insert_post(
			array(
				'post_type'   => 'ccap_lesson',
				'post_status' => 'publish',
				'post_title'  => 'L',
				'post_parent' => $module,
			)
		);
		$records = PackageExporter::records( $admin, array( $course ) );
		self::assertSame( array( 1, 1, 1 ), array( count( $records['courses'] ), count( $records['modules'] ), count( $records['lessons'] ) ) );
		self::assertSame( Canonical::hash( $records['courses'][0] ), $records['courses'][0]['hash'] );
		$json = (string) wp_json_encode( $records );
		foreach ( array( 'user_id', 'grant', 'attempt', 'email' ) as $forbidden ) {
			self::assertStringNotContainsString( $forbidden, $json );
		}
		$file = PackageExporter::write( $records, 'site-export', 'Export', $this->dir . '/export.zip' );
		self::assertTrue( PackageValidator::validate( $file )['ok'] );
		try {
			PackageExporter::records( Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) ), array( $course ) );
			self::fail( 'no rights' );
		} catch ( CourseException $e ) {
			self::assertContains( $e->status, array( 403, 404 ), 'no existence oracle' );
		}
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
}
