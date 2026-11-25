<?php
/**
 * R0-10 §6 catalogue gaps for protected media: MED-001…MED-004,
 * MED-015…MED-017 (R1-14) and the owner decisions E-373…E-375, E-377.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Access\ProtectedFiles;
use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;

/**
 * Real tables; files in the protected folder.
 *
 * @group R1-14
 */
final class MediaCatalogGapsTest extends SchemaTestCase {

	/**
	 * 1×1 PNG.
	 */
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Course A (modules M1, M2) and course B (one module).
	 *
	 * @var array<string, int>
	 */
	private array $ids = array();

	/**
	 * Fixture: course A with M1/M2 and a lesson in M2, course B with one module.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		foreach ( array( 'A', 'B' ) as $course ) {
			$this->ids[ $course ] = $this->post( 'ccap_course', 0 );
		}
		foreach ( array(
			'M1' => 'A',
			'M2' => 'A',
			'MB' => 'B',
		) as $module => $course ) {
			$this->ids[ $module ] = $this->post( 'ccap_module', 0 );
			CourseStructure::current()->assign_module( $admin, $this->ids[ $course ], $this->ids[ $module ] );
		}
		$this->ids['L2'] = $this->post( 'ccap_lesson', $this->ids['M2'] );
	}

	/**
	 * Reset clock, filters and request globals.
	 */
	public function tear_down(): void {
		ContentGate::set_now( null );
		remove_all_filters( 'course_captain_protected_file_sendfile' );
		unset( $_SERVER['HTTP_ACCEPT'] );
		parent::tear_down();
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
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_parent' => $parent_id,
				'post_author' => $this->admin,
				'post_title'  => $type,
			)
		);
	}

	/**
	 * Learner with an optional grant.
	 *
	 * @param int|null    $course      Course of the grant (null = none).
	 * @param int         $module      Module of the grant (0 = whole course).
	 * @param string|null $valid_until Grant end (UTC).
	 * @return int
	 */
	private function learner( ?int $course, int $module = 0, ?string $valid_until = null ): int {
		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		if ( null !== $course ) {
			$db = Database::current();
			$db->insert(
				'ccap_grant',
				array(
					'uid'         => $db->uuid(),
					'user_id'     => $user,
					'course_id'   => $course,
					'module_id'   => $module,
					'status'      => 'active',
					'source_type' => 'manual',
					'source_id'   => $db->uuid(),
					'valid_until' => $valid_until,
				),
				array( 'created_at', 'updated_at' )
			);
		}
		PolicyService::flush();
		return $user;
	}

	/**
	 * Protected PDF attached to an object.
	 *
	 * @param string $object_type course|module|lesson.
	 * @param int    $object_id   Object.
	 * @return array{uid: string, media: int, attachment: int, path: string}
	 */
	private function protected_pdf( string $object_type, int $object_id ): array {
		$admin  = Actor::user( $this->admin );
		$upload = wp_upload_bits( 'med-' . wp_generate_password( 6, false ) . '.pdf', null, "%PDF-1.4\n" . str_repeat( 'Fixture-Dokument ', 40 ) . "\n%%EOF\n" );
		$id     = (int) wp_insert_attachment(
			array(
				'post_title'     => 'Arbeitsblatt',
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		$media  = MediaLibrary::current()->register_attachment( $admin, $id );
		MediaLibrary::current()->attach( $admin, $media, $object_type, $object_id, 'download' );
		$path = ProtectedStorage::protect_attachment( $id, $media );
		PolicyService::flush();
		return array(
			'uid'        => (string) Database::current()->scalar( Database::current()->prepare( 'SELECT uid FROM `' . Database::current()->table( 'ccap_media' ) . '` WHERE id = %d', $media ) ),
			'media'      => $media,
			'attachment' => $id,
			'path'       => $path,
		);
	}

	/**
	 * Expect a rejection; returns the exception.
	 *
	 * @param callable $call   Call.
	 * @param string   $code   Error code.
	 * @param int      $status HTTP status.
	 * @return CourseException
	 */
	private function rejected( callable $call, string $code, int $status ): CourseException {
		try {
			$call();
		} catch ( CourseException $e ) {
			self::assertSame( array( $code, $status ), array( $e->error_code, $e->status ) );
			return $e;
		}
		self::fail( 'expected ' . $code );
	}

	/**
	 * Run `serve()` with foreign output in open buffers; returns the body
	 * that would reach the client (MED-015).
	 *
	 * @param Actor       $actor Actor.
	 * @param string      $uid   Media UID.
	 * @param string|null $range Range header.
	 * @return string
	 */
	private function served( Actor $actor, string $uid, ?string $range = null ): string {
		$level = ob_get_level();
		ob_start(); // Stands for the client connection.
		ob_start();
		echo 'Warning: output of another plugin';
		ob_start();
		echo "\n<!-- theme debug -->\n";
		try {
			ProtectedFiles::current()->serve( $actor, $uid, $range, $level + 1 );
		} finally {
			while ( ob_get_level() > $level + 1 ) {
				ob_end_clean();
			}
			$body = (string) ob_get_clean();
		}
		return $body;
	}

	/**
	 * MED-001 / E-373: protected file without login: 401 (auth required)
	 * without content or path; the endpoint sends a browser request to the
	 * login with the file URL as return address, REST/JSON requests keep
	 * the 401; an unknown UID stays 404.
	 *
	 * @group MED-001
	 * @group E-373
	 */
	public function test_MED_001_without_login(): void {
		$file  = $this->protected_pdf( 'course', $this->ids['A'] );
		$guest = Actor::user( 0 );
		$e     = $this->rejected( fn() => ProtectedFiles::current()->prepare( $guest, $file['uid'] ), 'ccap_auth_required', 401 );
		self::assertStringNotContainsString( ProtectedStorage::secret(), $e->getMessage() . wp_json_encode( $e->data ) );
		self::assertStringNotContainsString( basename( $file['path'] ), $e->getMessage() . wp_json_encode( $e->data ) );
		$this->rejected( fn() => ProtectedFiles::current()->prepare( $guest, wp_generate_uuid4() ), 'ccap_not_found', 404 );

		// No byte of the file reaches the client.
		$level = ob_get_level();
		ob_start();
		try {
			ProtectedFiles::current()->serve( $guest, $file['uid'], null );
			self::fail( 'guest must be rejected' );
		} catch ( CourseException $e ) {
			self::assertSame( 401, $e->status );
		} finally {
			while ( ob_get_level() > $level + 1 ) {
				ob_end_clean();
			}
			self::assertSame( '', (string) ob_get_clean() );
		}

		// The file itself was not touched (still only in the protected folder).
		self::assertTrue( ProtectedStorage::is_protected_path( $file['path'] ) );

		// E-373: browser → login with return URL (only the media UID), JSON/REST → 401, other denials never redirect.
		$login = (string) ProtectedFiles::login_redirect( 401, $file['uid'], false );
		self::assertSame( wp_login_url( ProtectedFiles::url( $file['uid'] ) ), $login );
		self::assertStringContainsString( 'redirect_to=' . rawurlencode( ProtectedFiles::url( $file['uid'] ) ), $login );
		self::assertStringNotContainsString( ProtectedStorage::secret(), $login );
		self::assertStringNotContainsString( basename( $file['path'] ), $login );
		self::assertSame( 1, substr_count( $login, $file['uid'] ), 'UID only inside the return URL' );
		self::assertNull( ProtectedFiles::login_redirect( 401, $file['uid'], true ), 'REST/JSON keeps 401' );
		self::assertNull( ProtectedFiles::login_redirect( 403, $file['uid'], false ) );
		self::assertNull( ProtectedFiles::login_redirect( 404, $file['uid'], false ) );
		self::assertNull( ProtectedFiles::login_redirect( 401, '../' . basename( $file['path'] ), false ) );
		if ( ! defined( 'REST_REQUEST' ) && ! wp_doing_ajax() ) {
			$_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
			self::assertFalse( ProtectedFiles::wants_json(), 'browser request' );
		}
		$_SERVER['HTTP_ACCEPT'] = 'application/json';
		self::assertTrue( ProtectedFiles::wants_json(), 'Accept: application/json' );
	}

	/**
	 * MED-002: logged in without grant, with an expired grant or with a
	 * grant for another module of the same course (E-27) → 403 without
	 * path; unknown or malformed UID → 404. The module grant of the
	 * module that uses the file is enough (E-27, R0-10 §4.3); it does not
	 * cover files of the whole course.
	 *
	 * @group MED-002
	 */
	public function test_MED_002_without_grant_expired_other_module(): void {
		$lesson_file = $this->protected_pdf( 'lesson', $this->ids['L2'] );
		$course_file = $this->protected_pdf( 'course', $this->ids['A'] );
		$files       = ProtectedFiles::current();
		$cases       = array(
			'no grant'     => $this->learner( null ),
			'expired'      => $this->learner( $this->ids['A'], 0, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ),
			'other module' => $this->learner( $this->ids['A'], $this->ids['M1'] ),
		);
		foreach ( $cases as $label => $user ) {
			foreach ( array( $lesson_file, $course_file ) as $file ) {
				$e    = $this->rejected( fn() => $files->prepare( Actor::user( $user ), $file['uid'] ), 'ccap_content_locked', 403 );
				$text = $e->getMessage() . wp_json_encode( $e->data );
				self::assertStringNotContainsString( ProtectedStorage::secret(), $text, $label . ': no path' );
				self::assertStringNotContainsString( basename( $file['path'] ), $text, $label . ': no file name' );
			}
		}
		$any = Actor::user( $cases['no grant'] );
		$this->rejected( fn() => $files->prepare( $any, wp_generate_uuid4() ), 'ccap_not_found', 404 );
		$this->rejected( fn() => $files->prepare( $any, '../' . basename( $lesson_file['path'] ) ), 'ccap_not_found', 404 );

		// E-27: a grant for M2 opens the lesson file of M2, not the course file.
		$module_learner = Actor::user( $this->learner( $this->ids['A'], $this->ids['M2'] ) );
		self::assertSame( 200, $files->prepare( $module_learner, $lesson_file['uid'] )['status'] );
		$this->rejected( fn() => $files->prepare( $module_learner, $course_file['uid'] ), 'ccap_content_locked', 403 );

		// A valid course grant opens both.
		$full = Actor::user( $this->learner( $this->ids['A'], 0, gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) ) );
		self::assertSame( array( 200, 200 ), array( $files->prepare( $full, $lesson_file['uid'] )['status'], $files->prepare( $full, $course_file['uid'] )['status'] ) );
	}

	/**
	 * MED-003: a grant for course B does not open a file used only in
	 * course A (IDOR), neither directly nor with course B as context; B's
	 * file listing does not show it.
	 *
	 * @group MED-003
	 */
	public function test_MED_003_grant_for_other_course(): void {
		$file  = $this->protected_pdf( 'course', $this->ids['A'] );
		$own   = $this->protected_pdf( 'course', $this->ids['B'] );
		$files = ProtectedFiles::current();
		$b     = Actor::user( $this->learner( $this->ids['B'] ) );
		self::assertSame( 200, $files->prepare( $b, $own['uid'] )['status'], 'own course file' );
		$this->rejected( fn() => $files->prepare( $b, $file['uid'] ), 'ccap_content_locked', 403 );
		$this->rejected( fn() => $files->prepare( $b, $file['uid'], null, $this->ids['B'] ), 'ccap_forbidden', 403 );
		self::assertSame( array( $own['uid'] ), array_column( $files->listing( $b, $this->ids['B'] ), 'uid' ) );

		// Module grant in B, file in a module of A.
		$module_file = $this->protected_pdf( 'module', $this->ids['M1'] );
		$mb          = Actor::user( $this->learner( $this->ids['B'], $this->ids['MB'] ) );
		$this->rejected( fn() => $files->prepare( $mb, $module_file['uid'] ), 'ccap_content_locked', 403 );
		$this->rejected( fn() => $files->prepare( $mb, $module_file['uid'], null, $this->ids['B'] ), 'ccap_forbidden', 403 );
	}

	/**
	 * MED-015: output of other plugins in open buffers before the file is
	 * discarded; the body is byte-identical (SHA-256), also for a range.
	 *
	 * @group MED-015
	 */
	public function test_MED_015_foreign_output_buffers(): void {
		$file = $this->protected_pdf( 'course', $this->ids['A'] );
		$full = Actor::user( $this->learner( $this->ids['A'] ) );
		$body = $this->served( $full, $file['uid'] );
		self::assertSame( hash_file( 'sha256', $file['path'] ), hash( 'sha256', $body ) );
		self::assertStringNotContainsString( 'another plugin', $body );
		self::assertSame( substr( (string) file_get_contents( $file['path'] ), 5, 10 ), $this->served( $full, $file['uid'], 'bytes=5-14' ) );
	}

	/**
	 * MED-017: protecting an image attachment moves all generated sizes
	 * (and the original of a scaled image); no size URL points to a public
	 * file any more.
	 *
	 * @group MED-017
	 */
	public function test_MED_017_image_sizes_move_along(): void {
		$admin  = Actor::user( $this->admin );
		$name   = 'med017-' . wp_generate_password( 6, false, false );
		$png    = (string) base64_decode( self::PNG ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Test fixture.
		$upload = wp_upload_bits( $name . '-scaled.png', null, $png );
		$dir    = dirname( $upload['file'] );
		$sizes  = array(
			'thumbnail' => $name . '-150x150.png',
			'medium'    => $name . '-300x300.png',
		);
		foreach ( array_merge( array_values( $sizes ), array( $name . '.png' ) ) as $extra ) {
			file_put_contents( $dir . '/' . $extra, $png ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		}
		$id   = (int) wp_insert_attachment(
			array(
				'post_title'     => 'Bild',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		$meta = array(
			'width'          => 600,
			'height'         => 600,
			'file'           => _wp_relative_upload_path( $upload['file'] ),
			'sizes'          => array(),
			'original_image' => $name . '.png',
		);
		foreach ( $sizes as $size => $file ) {
			$px                     = 'thumbnail' === $size ? 150 : 300;
			$meta['sizes'][ $size ] = array(
				'file'      => $file,
				'width'     => $px,
				'height'    => $px,
				'mime-type' => 'image/png',
			);
		}
		wp_update_attachment_metadata( $id, $meta );
		$public = array_merge( array( $upload['file'], $dir . '/' . $name . '.png' ), array_map( fn( string $f ): string => $dir . '/' . $f, array_values( $sizes ) ) );
		foreach ( $public as $path ) {
			self::assertFileExists( $path, 'precondition: public file' );
		}

		$media = MediaLibrary::current()->register_attachment( $admin, $id );
		$main  = ProtectedStorage::protect_attachment( $id, $media );
		self::assertTrue( ProtectedStorage::is_protected_path( $main ) );
		foreach ( $public as $path ) {
			self::assertFileDoesNotExist( $path, 'moved out of the public folder: ' . basename( $path ) );
			self::assertFileExists( dirname( $main ) . '/' . basename( $path ), 'in the protected folder: ' . basename( $path ) );
		}

		// Every URL WordPress builds for the image (sizes, srcset, original) reaches no public file.
		$uploads = wp_upload_dir( null, false );
		$urls    = array( (string) wp_get_attachment_url( $id ) );
		foreach ( array( 'thumbnail', 'medium', 'full' ) as $size ) {
			$src = wp_get_attachment_image_src( $id, $size );
			if ( is_array( $src ) ) {
				$urls[] = (string) $src[0];
			}
		}
		foreach ( explode( ',', (string) wp_get_attachment_image_srcset( $id, 'medium' ) ) as $candidate ) {
			$urls[] = (string) strtok( trim( $candidate ), ' ' );
		}
		$urls[] = (string) wp_get_original_image_url( $id );
		$urls   = array_values( array_filter( array_unique( $urls ) ) );
		self::assertNotEmpty( $urls );
		foreach ( $urls as $url ) {
			if ( ! str_starts_with( $url, (string) $uploads['baseurl'] ) ) {
				continue;
			}
			$path = (string) $uploads['basedir'] . substr( strtok( $url, '?' ), strlen( (string) $uploads['baseurl'] ) );
			self::assertTrue( ! is_file( $path ) || ProtectedStorage::is_protected_path( $path ), 'no public file behind ' . $url );
		}
		self::assertStringContainsString( ProtectedFiles::url( (string) Database::current()->scalar( Database::current()->prepare( 'SELECT uid FROM `' . Database::current()->table( 'ccap_media' ) . '` WHERE id = %d', $media ) ) ), (string) wp_get_attachment_url( $id ) );

		// Unprotect brings every size back.
		ProtectedStorage::unprotect_attachment( $id );
		foreach ( $public as $path ) {
			self::assertFileExists( $path, 'moved back: ' . basename( $path ) );
		}
	}

	/**
	 * Drip rule for a module of course A.
	 *
	 * @param int $module Module.
	 * @param int $days   Days after the access start.
	 * @return void
	 */
	private function drip( int $module, int $days ): void {
		Database::current()->insert(
			'ccap_access_rule',
			array(
				'course_id'   => $this->ids['A'],
				'module_id'   => $module,
				'scope'       => 'view',
				'access_type' => 'drip',
				'limit_value' => $days,
			)
		);
		PolicyService::flush();
	}

	/**
	 * E-374: drip applies to protected files. A file whose every module is
	 * drip-locked stays locked (`ccap_content_locked`, reason `drip`), also
	 * with a course grant; one open module, a course-level link or the
	 * editorial side opens it; after the unlock date it is delivered.
	 *
	 * @group E-374
	 */
	public function test_E_374_drip_locks_protected_files(): void {
		$this->drip( $this->ids['M2'], 5 );
		$lesson_file = $this->protected_pdf( 'lesson', $this->ids['L2'] );
		$module_file = $this->protected_pdf( 'module', $this->ids['M2'] );
		$shared      = $this->protected_pdf( 'module', $this->ids['M2'] );
		MediaLibrary::current()->attach( Actor::user( $this->admin ), $shared['media'], 'module', $this->ids['M1'], 'download' );
		$course_file = $this->protected_pdf( 'course', $this->ids['A'] );
		$full        = Actor::user( $this->learner( $this->ids['A'] ) );
		$module      = Actor::user( $this->learner( $this->ids['A'], $this->ids['M2'] ) );
		foreach ( array(
			'course grant' => $full,
			'module grant' => $module,
		) as $label => $actor ) {
			foreach ( array( $lesson_file, $module_file ) as $file ) {
				$e = $this->rejected( fn() => ProtectedFiles::current()->prepare( $actor, $file['uid'] ), 'ccap_content_locked', 403 );
				self::assertSame( 'drip', $e->getMessage(), $label );
				self::assertStringNotContainsString( basename( $file['path'] ), $e->getMessage() . wp_json_encode( $e->data ), $label . ': no file name' );
			}
		}
		$files = ProtectedFiles::current();
		self::assertSame( 200, $files->prepare( $full, $shared['uid'] )['status'], 'M1 without drip opens the shared file' );
		self::assertSame( 200, $files->prepare( $full, $course_file['uid'] )['status'], 'course files belong to no module' );
		self::assertSame( 200, $files->prepare( Actor::user( $this->admin ), $lesson_file['uid'] )['status'], 'editors are not drip-locked' );
		$this->rejected( fn() => $files->prepare( $module, $shared['uid'] ), 'ccap_content_locked', 403 );
		$this->rejected( fn() => $files->prepare( Actor::user( $this->learner( null ) ), $lesson_file['uid'] ), 'ccap_content_locked', 403 );

		// After the unlock date the files are delivered.
		ContentGate::set_now( time() + 6 * DAY_IN_SECONDS );
		PolicyService::flush();
		foreach ( array( $full, $module ) as $actor ) {
			self::assertSame( array( 200, 200, 200 ), array( ProtectedFiles::current()->prepare( $actor, $lesson_file['uid'] )['status'], ProtectedFiles::current()->prepare( $actor, $module_file['uid'] )['status'], ProtectedFiles::current()->prepare( $actor, $shared['uid'] )['status'] ) );
		}
	}

	/**
	 * Published single-choice question in the pool of a module.
	 *
	 * @param int $module Module.
	 * @return int Question ID.
	 */
	private function pool_question( int $module ): int {
		$admin = Actor::user( $this->admin );
		$q     = QuestionRepository::current()->create(
			$admin,
			array(
				'question_type' => QuestionTypes::SINGLE,
				'question_text' => 'Frage ' . wp_generate_password( 6, false ),
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
		QuestionRepository::current()->publish( $admin, $q );
		Database::current()->insert(
			'ccap_module_question',
			array(
				'module_id'   => $module,
				'question_id' => $q,
				'sort_order'  => 1,
			)
		);
		return $q;
	}

	/**
	 * Protected PDF linked to the current revision of a question.
	 *
	 * @param int $question Question.
	 * @return array{uid: string, media: int, attachment: int, path: string}
	 */
	private function question_pdf( int $question ): array {
		$db   = Database::current();
		$file = $this->protected_pdf( 'course', $this->ids['B'] );
		$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_content_media' ) . "` WHERE media_id = %d AND object_type = 'course'", $file['media'] ) );
		$db->insert(
			'ccap_content_media',
			array(
				'object_type' => 'question_revision',
				'object_id'   => (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) ),
				'media_id'    => $file['media'],
				'role'        => 'attachment',
				'sort_order'  => 0,
			)
		);
		PolicyService::flush();
		return $file;
	}

	/**
	 * MED-004 / E-375: media of exam questions. The learner with a running
	 * attempt containing the question (snapshot revision) gets the medium,
	 * even after the grant ended; a foreign learner without access gets
	 * 403; learners with course access keep the practice-mode access;
	 * after submit the attempt no longer opens anything (the result view
	 * shows no question content); a medium of a question outside the
	 * attempt is not opened by the attempt.
	 *
	 * @group MED-004
	 * @group E-375
	 */
	public function test_MED_004_exam_question_media_only_for_running_attempt(): void {
		$question = $this->pool_question( $this->ids['M1'] );
		$other    = $this->pool_question( $this->ids['M2'] );
		$file     = $this->question_pdf( $question );
		$outside  = $this->question_pdf( $other );
		$admin    = Actor::user( $this->admin );
		$rule     = ExamRules::current()->create(
			$admin,
			$this->ids['A'],
			'v1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			600
		);
		ExamRules::current()->set_blueprint( $admin, $rule, $this->ids['M1'], 1 );
		ExamRules::current()->publish( $admin, $rule );
		$owner_id = $this->learner( $this->ids['A'], 0, gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ) );
		$owner    = Actor::user( $owner_id );
		$session  = ExamSessions::current()->start( $owner, $rule, null, wp_generate_uuid4() );
		$foreign  = Actor::user( $this->learner( null ) );
		$practice = Actor::user( $this->learner( $this->ids['A'] ) );
		$files    = ProtectedFiles::current();

		self::assertSame( 200, $files->prepare( $owner, $file['uid'] )['status'], 'own running attempt' );
		$e = $this->rejected( fn() => $files->prepare( $foreign, $file['uid'] ), 'ccap_content_locked', 403 );
		self::assertStringNotContainsString( basename( $file['path'] ), $e->getMessage() . wp_json_encode( $e->data ) );
		self::assertSame( 200, $files->prepare( $practice, $file['uid'] )['status'], 'practice mode: normal course access' );

		// The grant ends during the attempt: the running attempt keeps working, only for its question.
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . '` SET valid_until = %s WHERE user_id = %d', gmdate( 'Y-m-d H:i:s', time() - 60 ), $owner_id ) );
		PolicyService::flush();
		self::assertSame( 200, ProtectedFiles::current()->prepare( $owner, $file['uid'] )['status'], 'running attempt keeps working' );
		$this->rejected( fn() => ProtectedFiles::current()->prepare( $owner, $outside['uid'] ), 'ccap_content_locked', 403 );

		// After submit: no attempt access any more.
		ExamSessions::current()->submit( $owner, $session );
		PolicyService::flush();
		$this->rejected( fn() => ProtectedFiles::current()->prepare( $owner, $file['uid'] ), 'ccap_content_locked', 403 );
		self::assertSame( 200, ProtectedFiles::current()->prepare( $practice, $file['uid'] )['status'] );
	}

	/**
	 * MED-016 / E-377: the filter `course_captain_protected_file_sendfile`
	 * gets null, the absolute path and the media row; returned headers are
	 * sent with the content headers and PHP sends no body (200, no length
	 * or range); invalid headers and content headers from the filter are
	 * dropped; without rights the filter never runs; default streams.
	 *
	 * @group MED-016
	 * @group E-377
	 */
	public function test_MED_016_sendfile_filter(): void {
		$file  = $this->protected_pdf( 'course', $this->ids['A'] );
		$full  = Actor::user( $this->learner( $this->ids['A'] ) );
		$calls = array();

		$default = ProtectedFiles::current()->response( $full, $file['uid'] );
		self::assertTrue( $default['stream'], 'default: PHP streaming' );
		self::assertSame( hash_file( 'sha256', $file['path'] ), hash( 'sha256', $this->served( $full, $file['uid'] ) ) );

		add_filter(
			'course_captain_protected_file_sendfile',
			function ( $headers, $path, $media ) use ( &$calls ) {
				$calls[] = array( $headers, $path, $media['uid'] ?? null );
				return array(
					'X-Accel-Redirect' => '/protected/' . basename( (string) $path ),
					'Content-Type'     => 'text/html',
					'X-Evil'           => "a\r\nSet-Cookie: x=1",
					'Bad Name'         => 'x',
				);
			},
			10,
			3
		);
		foreach ( array( null, 'bytes=0-9' ) as $range ) {
			$response = ProtectedFiles::current()->response( $full, $file['uid'], $range );
			self::assertFalse( $response['stream'] );
			self::assertSame( 200, $response['status'] );
			self::assertSame( '/protected/' . basename( $file['path'] ), $response['headers']['X-Accel-Redirect'] );
			self::assertSame( 'application/pdf', $response['headers']['Content-Type'], 'content headers stay' );
			self::assertSame( 'nosniff', $response['headers']['X-Content-Type-Options'] );
			self::assertStringContainsString( 'no-store', $response['headers']['Cache-Control'] );
			self::assertStringContainsString( 'inline;', $response['headers']['Content-Disposition'] );
			self::assertArrayNotHasKey( 'Content-Length', $response['headers'] );
			self::assertArrayNotHasKey( 'Content-Range', $response['headers'] );
			self::assertArrayNotHasKey( 'X-Evil', $response['headers'] );
			self::assertArrayNotHasKey( 'Bad Name', $response['headers'] );
		}
		self::assertSame( array( null, $file['path'], $file['uid'] ), $calls[0] );
		self::assertSame( '', $this->served( $full, $file['uid'] ), 'PHP sends no body' );

		// Without rights the filter is never asked.
		$before = count( $calls );
		$this->rejected( fn() => ProtectedFiles::current()->response( Actor::user( $this->learner( null ) ), $file['uid'] ), 'ccap_content_locked', 403 );
		$this->rejected( fn() => ProtectedFiles::current()->response( Actor::user( 0 ), $file['uid'] ), 'ccap_auth_required', 401 );
		self::assertCount( $before, $calls );

		// Only invalid headers → PHP streaming.
		remove_all_filters( 'course_captain_protected_file_sendfile' );
		add_filter( 'course_captain_protected_file_sendfile', static fn() => array( 'X-Sendfile' => "x\ny" ) );
		self::assertTrue( ProtectedFiles::current()->response( $full, $file['uid'] )['stream'] );
		remove_all_filters( 'course_captain_protected_file_sendfile' );
		add_filter( 'course_captain_protected_file_sendfile', static fn( $headers, $path ) => array( 'X-Sendfile' => $path ), 10, 2 );
		self::assertSame( $file['path'], ProtectedFiles::current()->response( $full, $file['uid'] )['headers']['X-Sendfile'] );
	}
}
