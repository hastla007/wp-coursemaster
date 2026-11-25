<?php
/**
 * R0-10 §6 catalogue gaps for protected media: MED-001…MED-003,
 * MED-015, MED-017 (R1-14).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ProtectedFiles;
use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
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
	 * MED-001: protected file without login: 401 (auth required) without
	 * content or path; the endpoint maps the 401; an unknown UID stays 404.
	 *
	 * @group MED-001
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
}
