<?php
/**
 * R1-14: content gate, protected files, protected pages (R0-08 §1.4, R0-10 §4; R0-05).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Access\ProtectedFiles;
use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-14
 */
final class ContentGateTest extends SchemaTestCase {

	private const T0 = 1794830400;

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
	 * Modules M1, M2.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Pool per module (3 questions each).
	 *
	 * @var array<int, list<int>>
	 */
	private array $pool = array();

	/**
	 * Lessons per module (3 each).
	 *
	 * @var array<int, list<int>>
	 */
	private array $lessons = array();

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->course = $this->post( 'ccap_course', 0 );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		foreach ( array( 1, 2 ) as $n ) {
			$module          = $this->post( 'ccap_module', 0 );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module );
			for ( $i = 0; $i < 3; $i++ ) {
				$q = QuestionRepository::current()->create(
					$admin,
					array(
						'question_type' => QuestionTypes::SINGLE,
						'question_text' => 'Q' . $n . $i . wp_generate_password( 4, false ),
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
						'sort_order'  => $i + 1,
					)
				);
				$this->pool[ $module ][] = $q;
				$lesson                  = (int) wp_insert_post(
					array(
						'post_type'   => 'ccap_lesson',
						'post_status' => 'publish',
						'post_parent' => $module,
						'menu_order'  => $i,
						'post_title'  => 'L' . $i,
						'post_author' => $this->admin,
					)
				);
				ActivityService::current()->define( $admin, $lesson, 'lesson', array() );
				$this->lessons[ $module ][] = $lesson;
			}
		}
	}

	/**
	 * Reset clocks.
	 */
	public function tear_down(): void {
		ContentGate::set_now( null );
		SmartTrainer::set_now( null );
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
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
				'post_title'   => $type,
				'post_content' => 'Secret course text',
			)
		);
	}

	/**
	 * Learner with an optional grant.
	 *
	 * @param bool        $grant      With course grant.
	 * @param string|null $valid_from Grant start.
	 * @return int
	 */
	private function learner( bool $grant = true, ?string $valid_from = null ): int {
		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		if ( $grant ) {
			$db = Database::current();
			$db->insert(
				'ccap_grant',
				array(
					'uid'         => $db->uuid(),
					'user_id'     => $user,
					'course_id'   => $this->course,
					'status'      => 'active',
					'source_type' => 'manual',
					'source_id'   => $db->uuid(),
					'valid_from'  => $valid_from,
				),
				array( 'created_at', 'updated_at' )
			);
		}
		PolicyService::flush();
		return $user;
	}

	/**
	 * Access rule.
	 *
	 * @param string   $type   free|free_limited|drip.
	 * @param int|null $module Module.
	 * @param int|null $value  Limit value.
	 * @return int
	 */
	private function rule( string $type, ?int $module, ?int $value ): int {
		$id = Database::current()->insert(
			'ccap_access_rule',
			array(
				'course_id'   => $this->course,
				'module_id'   => $module,
				'scope'       => 'view',
				'access_type' => $type,
				'limit_type'  => 'free_limited' === $type ? 'first_items' : null,
				'limit_value' => $value,
			)
		);
		PolicyService::flush();
		ContentProtection::flush();
		return $id;
	}

	/**
	 * Expect a CourseException code (and reason).
	 *
	 * @param callable    $call   Call.
	 * @param string      $code   Code.
	 * @param string|null $reason Message (gate reason).
	 */
	private function assertRejected( callable $call, string $code, ?string $reason = null ): void {
		try {
			$call();
			self::fail( 'expected ' . $code );
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code );
			if ( null !== $reason ) {
				self::assertSame( $reason, $e->getMessage() );
			}
		}
	}

	/**
	 * §1.4 Nr. 3: drip relative to the access start, max of the rules, unlock_at.
	 */
	public function test_drip(): void {
		$user = $this->learner( true, gmdate( 'Y-m-d H:i:s', self::T0 ) );
		$this->rule( 'drip', $this->modules[1], 2 );
		$this->rule( 'drip', $this->modules[1], 3 );
		ContentGate::set_now( self::T0 + 86400 );
		$gate = ContentGate::current()->module( Actor::user( $user ), $this->course, $this->modules[1] );
		self::assertSame( array( 'locked', 'drip', gmdate( 'Y-m-d H:i:s', self::T0 + 3 * 86400 ) ), array( $gate->state, $gate->reason, $gate->unlock_at ) );
		self::assertTrue( ContentGate::current()->module( Actor::user( $user ), $this->course, $this->modules[0] )->allowed() );
		$this->assertRejected( fn() => QuestionPresenter::current()->deliver( Actor::user( $user ), $this->course, $this->modules[1], $this->pool[ $this->modules[1] ][0] ), 'ccap_content_locked', 'drip' );
		self::assertNotContains( $this->pool[ $this->modules[1] ][0], array_column( array( SmartTrainer::current()->next( Actor::user( $user ), $this->course ) ), 'question_id' ) );

		ContentGate::set_now( self::T0 + 3 * 86400 + 1 );
		self::assertTrue( ContentGate::current()->module( Actor::user( $user ), $this->course, $this->modules[1] )->allowed() );
	}

	/**
	 * §1.4 Nr. 2: partial access without grant — first n questions, lessons, fixed variants only.
	 */
	public function test_partial_access(): void {
		$user = $this->learner( false );
		$this->rule( 'free_limited', null, 2 );
		$actor = Actor::user( $user );
		$gate  = ContentGate::current();
		$m1    = $this->modules[0];
		self::assertSame( 2, $gate->module( $actor, $this->course, $m1 )->limit );
		self::assertTrue( $gate->question( $actor, $this->course, $m1, $this->pool[ $m1 ][1] )->allowed() );
		self::assertSame( 'trial', $gate->question( $actor, $this->course, $m1, $this->pool[ $m1 ][2] )->reason );
		QuestionPresenter::current()->deliver( $actor, $this->course, $m1, $this->pool[ $m1 ][0] );
		$this->assertRejected( fn() => QuestionPresenter::current()->deliver( $actor, $this->course, $m1, $this->pool[ $m1 ][2] ), 'ccap_content_locked', 'trial' );
		$this->assertRejected( fn() => ActivityProgress::current()->interact( $actor, $this->course, $this->lessons[ $m1 ][2], 'confirm' ), 'ccap_content_locked', 'trial' );
		self::assertSame( 'started', ActivityProgress::current()->interact( $actor, $this->course, $this->lessons[ $m1 ][0], 'view' )['status'] );

		SmartTrainer::set_now( self::T0 );
		$allowed = array_merge( array_slice( $this->pool[ $m1 ], 0, 2 ), array_slice( $this->pool[ $this->modules[1] ], 0, 2 ) );
		$summary = SmartTrainer::current()->summary( $actor, $this->course );
		self::assertSame( 4, $summary['total'] );
		self::assertContains( SmartTrainer::current()->next( $actor, $this->course )['question_id'], $allowed );

		self::assertSame( 'trial', $gate->exam( $actor, $this->course, $this->modules )->reason, 'no random exam' );
		self::assertTrue( $gate->exam( $actor, $this->course, $this->modules, 2 )->allowed() );
		self::assertFalse( $gate->exam( $actor, $this->course, $this->modules, 3 )->allowed() );

		$stranger = Actor::user( 0 );
		self::assertSame( 'no_access', $gate->module( $stranger, $this->course, $m1 )->reason, 'logged out' );
	}

	/**
	 * §1.4 Nr. 4: prerequisites with `requires`.
	 */
	public function test_prerequisites(): void {
		$user = $this->learner();
		Database::current()->execute(
			Database::current()->prepare(
				'UPDATE `' . Database::current()->table( 'ccap_course_module' ) . '` SET rules = %s WHERE course_id = %d AND module_id = %d',
				(string) wp_json_encode(
					array(
						'prerequisites' => array(
							array(
								'type' => 'completed_module',
								'id'   => $this->modules[0],
							),
						),
					)
				),
				$this->course,
				$this->modules[1]
			)
		);
		$gate = ContentGate::current()->module( Actor::user( $user ), $this->course, $this->modules[1] );
		self::assertSame( array( 'prerequisite', array( 'completed_module:' . $this->modules[0] ) ), array( $gate->reason, $gate->requires ) );
		foreach ( $this->lessons[ $this->modules[0] ] as $lesson ) {
			ActivityProgress::current()->interact( Actor::user( $user ), $this->course, $lesson, 'confirm' );
		}
		self::assertTrue( ContentGate::current()->module( Actor::user( $user ), $this->course, $this->modules[1] )->allowed() );
		self::assertSame( array( 'unlocked', null ), array_values( array_intersect_key( ContentGate::current()->module( Actor::user( $user ), $this->course, $this->modules[1] )->to_array(), array_flip( array( 'state', 'reason' ) ) ) ) );
	}

	/**
	 * GM-015 / GM-016 / GM-111 / GM-112: protected storage and delivery.
	 *
	 * @group GM-015
	 * @group GM-016
	 * @group GM-111
	 * @group GM-112
	 */
	public function test_protected_files(): void {
		$admin  = Actor::user( $this->admin );
		$uids   = array();
		$attach = array();
		foreach ( array( 'Übersicht "A"', 'Zwei', 'Drei' ) as $i => $title ) {
			$upload = wp_upload_bits( 'file-' . $i . '-' . wp_generate_password( 4, false ) . '.pdf', null, "%PDF-1.4\n" . str_repeat( 'x', 100 ) );
			$id     = (int) wp_insert_attachment(
				array(
					'post_title'     => $title,
					'post_mime_type' => 'application/pdf',
					'post_status'    => 'inherit',
				),
				$upload['file']
			);
			$media  = MediaLibrary::current()->register_attachment( $admin, $id );
			MediaLibrary::current()->attach( $admin, $media, 'course', $this->course, 'download', $i );
			$path     = ProtectedStorage::protect_attachment( $id, $media );
			$uids[]   = (string) Database::current()->scalar( Database::current()->prepare( 'SELECT uid FROM `' . Database::current()->table( 'ccap_media' ) . '` WHERE id = %d', $media ) );
			$attach[] = $id;
			self::assertTrue( ProtectedStorage::is_protected_path( $path ) );
			self::assertFileDoesNotExist( $upload['file'] );
		}
		$dir = ProtectedStorage::dir( 'protected' );
		self::assertStringContainsString( ProtectedStorage::secret(), $dir, 'GM-112: secret in folder name' );
		self::assertStringContainsString( 'Require all denied', (string) file_get_contents( $dir . '/.htaccess' ) );
		self::assertStringContainsString( 'Deny from all', (string) file_get_contents( $dir . '/.htaccess' ) );
		self::assertFileExists( $dir . '/index.php' );
		self::assertStringContainsString( 'svgz?', (string) file_get_contents( ProtectedStorage::dir( 'media' ) . '/.htaccess' ) );
		self::assertStringEndsWith( '/cc-file/' . $uids[0] . '/', (string) wp_get_attachment_url( $attach[0] ), 'GM-111: URL via the check' );

		// GM-015: partial access lists all, only the first is free.
		$trial = Actor::user( $this->learner( false ) );
		$this->rule( 'free_limited', null, 1 );
		$files = ProtectedFiles::current();
		self::assertSame( array( false, true, true ), array_column( $files->listing( $trial, $this->course ), 'locked' ) );
		self::assertSame( 200, $files->prepare( $trial, $uids[0] )['status'] );
		$this->assertRejected( fn() => $files->prepare( $trial, $uids[1] ), 'ccap_content_locked' );
		$full = Actor::user( $this->learner() );
		self::assertSame( array( false, false, false ), array_column( $files->listing( $full, $this->course ), 'locked' ) );
		$this->assertRejected( fn() => $files->prepare( $full, wp_generate_uuid4() ), 'ccap_not_found' );

		// GM-016: both forms, correctly encoded.
		$response = $files->prepare( $full, $uids[0] );
		self::assertSame( 'inline; filename="Ubersicht \\"A\\".pdf"; filename*=UTF-8\'\'%C3%9Cbersicht%20%22A%22.pdf', $response['headers']['Content-Disposition'] );
		self::assertSame( 'nosniff', $response['headers']['X-Content-Type-Options'] );
		self::assertStringContainsString( 'no-store', $response['headers']['Cache-Control'] );
		self::assertArrayNotHasKey( 'Content-Security-Policy', $response['headers'], 'PDF without sandbox' );

		// Ranges.
		$size = $response['length'];
		$part = $files->prepare( $full, $uids[0], 'bytes=0-9' );
		self::assertSame( array( 206, 10, 'bytes 0-9/' . $size ), array( $part['status'], $part['length'], $part['headers']['Content-Range'] ) );
		self::assertSame( 416, $files->prepare( $full, $uids[0], 'bytes=99999-' )['status'] );
		self::assertSame( 200, $files->prepare( $full, $uids[0], 'bytes=0-1,5-6' )['status'], 'multiple ranges → whole file' );
		self::assertSame( array( $size - 5, $size - 1 ), ProtectedFiles::range( 'bytes=-5', $size ) );
		self::assertStringContainsString( 'attachment;', ProtectedFiles::content_disposition( 'x.zip' ) );
		self::assertSame( 'application/octet-stream', ProtectedFiles::headers( 'x.zip', 'application/zip', 1 )['Content-Type'] );

		// Unprotect moves back.
		ProtectedStorage::unprotect_attachment( $attach[2] );
		self::assertFalse( ProtectedStorage::is_protected_path( (string) get_attached_file( $attach[2] ) ) );
		self::assertFileExists( (string) get_attached_file( $attach[2] ) );
	}

	/**
	 * GM-107 / GM-108 / GM-109 / GM-110: protected page, bypass paths, section, memo.
	 *
	 * @group GM-107
	 * @group GM-108
	 * @group GM-109
	 * @group GM-110
	 */
	public function test_protected_pages(): void {
		$page = (int) wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Page 1',
				'post_content' => 'Secret page text',
				'post_excerpt' => 'Secret excerpt',
			)
		);
		update_post_meta( $page, ContentProtection::META, $this->course );
		$outsider = $this->learner( false );
		$member   = $this->learner();
		$render   = function ( int $user, string $filter ) use ( $page ): string {
			wp_set_current_user( $user );
			$GLOBALS['post'] = get_post( $page ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test context.
			return (string) apply_filters( $filter, 'Secret page text' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filters under test.
		};
		self::assertStringNotContainsString( 'Secret', $render( $outsider, 'the_content' ) );
		self::assertStringContainsString( 'Secret', $render( $member, 'the_content' ) );
		self::assertStringContainsString( 'Secret', $render( $this->admin, 'the_content' ), 'editor' );
		self::assertStringNotContainsString( 'Secret', $render( $outsider, 'the_content_feed' ) );
		wp_set_current_user( $outsider );
		self::assertStringNotContainsString( 'Secret', (string) apply_filters( 'get_the_excerpt', 'Secret excerpt', get_post( $page ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.

		wp_set_current_user( 0 );
		ContentProtection::flush();
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$rest = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/pages/' . $page ) );
		self::assertStringNotContainsString( 'Secret', (string) wp_json_encode( $rest->get_data() ) );

		// GM-109: section.
		wp_set_current_user( $outsider );
		self::assertStringNotContainsString( 'Hidden part', do_shortcode( '[ccap_protected course="' . $this->course . '"]Hidden part[/ccap_protected]' ) );
		wp_set_current_user( $member );
		self::assertStringContainsString( 'Hidden part', do_shortcode( '[ccap_protected course="' . $this->course . '"]Hidden part[/ccap_protected]' ) );

		// GM-110: a new free rule frees at once (memo flushed).
		$this->rule( 'free', null, null );
		self::assertStringContainsString( 'Secret', $render( $outsider, 'the_content' ) );
	}

	/**
	 * §1.4 Nr. 5: a running exam locks its questions; E-286 exam needs all modules.
	 */
	public function test_exam_running_and_exam_gate(): void {
		$user = $this->learner();
		$db   = Database::current();
		$rule = \CourseCaptain\Exam\ExamRules::current()->create(
			Actor::user( $this->admin ),
			$this->course,
			'v1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			600
		);
		\CourseCaptain\Exam\ExamRules::current()->set_blueprint( Actor::user( $this->admin ), $rule, $this->modules[0], 1 );
		\CourseCaptain\Exam\ExamRules::current()->publish( Actor::user( $this->admin ), $rule );
		$session  = \CourseCaptain\Exam\ExamSessions::current()->start( Actor::user( $user ), $rule, null, wp_generate_uuid4() );
		$question = (int) $db->scalar( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d', $session ) );
		self::assertSame( 'exam_running', ContentGate::current()->question( Actor::user( $user ), $this->course, $this->modules[0], $question )->reason );

		$this->rule( 'drip', $this->modules[1], 5 );
		$exam = ContentGate::current()->exam( Actor::user( $user ), $this->course, $this->modules );
		self::assertSame( array( 'drip', array( 'module:' . $this->modules[1] ) ), array( $exam->reason, $exam->requires ) );
	}
}
