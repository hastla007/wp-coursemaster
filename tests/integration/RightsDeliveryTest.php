<?php
/**
 * Delivery lock for foreign content with unclear or restricted rights
 * (R0-10 §2.2, R1-14/R1-15), lesson rights, flashcards in imported fixed
 * variants (D2) and `default_rights` on unchanged records (D8 follow-up).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ProtectedFiles;
use CourseCaptain\Access\RightsGate;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Admin\PreviewScreen;
use CourseCaptain\Cache\CacheStore;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Import\Rights;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LessonView;
use CourseCaptain\Learning\Flashcards;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Publish\CourseValidator;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\QuizPool;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables.
 *
 * @group SET-CC-rechte-auslieferung
 */
final class RightsDeliveryTest extends SchemaTestCase {

	private const FOREIGN = 'fixture-foreign';

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner with grant.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Published course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Required module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Questions: Q0 foreign unknown, Q1 foreign licensed but expired, Q2 own
	 * with override `unknown` (E-18: delivered), Q3 foreign licensed (valid),
	 * Q4 own but needs its image and the only image is foreign `unknown`.
	 *
	 * @var list<int>
	 */
	private array $q = array();

	/**
	 * Flashcards: C0 foreign restricted, C1 own.
	 *
	 * @var list<int>
	 */
	private array $c = array();

	/**
	 * Scratch directory.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Course with one module, five questions and two cards.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$admin         = Actor::user( $this->admin );
		$this->course  = $this->post( 'ccap_course', 'Rights course' );
		$this->module  = $this->post( 'ccap_module', 'Rights module' );
		PolicyService::flush();
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module );
		for ( $i = 0; $i < 5; $i++ ) {
			$this->q[] = $this->question( $this->module, QuestionTypes::SINGLE );
		}
		$this->c[] = $this->question( $this->module, QuestionTypes::FLASHCARD );
		$this->c[] = $this->question( $this->module, QuestionTypes::FLASHCARD );
		CoursePublisher::current()->publish( $admin, $this->course );
		$this->grant( $this->learner );

		$this->foreign( 'question', $this->q[0], 'unknown' );
		$this->foreign( 'question', $this->q[1], 'licensed', array( 'valid_until' => '2001-01-01' ) );
		Rights::current()->override( $admin, 'question', $this->q[2], 'unknown', 'Own photo, contract missing' );
		$this->foreign( 'question', $this->q[3], 'licensed', array( 'valid_until' => '2099-12-31' ) );
		$db  = Database::current();
		$rev = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $this->q[4] ) );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_question_revision' ) . '` SET image_required = 1 WHERE id = %d', $rev ) );
		$this->media( 'question_revision', $rev, self::FOREIGN, 'unknown' );
		$this->foreign( 'flashcard', $this->c[0], 'restricted' );

		$this->dir = get_temp_dir() . 'ccap-ra-' . wp_generate_password( 6, false, false );
		wp_mkdir_p( $this->dir );
	}

	/**
	 * Remove scratch files.
	 */
	public function tear_down(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test cleanup.
		}
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Published post.
	 *
	 * @param string $type    Type.
	 * @param string $title   Title.
	 * @param int    $parent_id Parent.
	 * @param string $content Content.
	 * @return int
	 */
	private function post( string $type, string $title, int $parent_id = 0, string $content = 'About' ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Published question or flashcard in a pool.
	 *
	 * @param int    $module Module.
	 * @param string $type   Type.
	 * @return int
	 */
	private function question( int $module, string $type ): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$data  = QuestionTypes::FLASHCARD === $type
			? array(
				'question_type' => $type,
				'question_text' => 'Card ' . wp_generate_password( 6, false ),
				'settings'      => array( 'back' => 'Back' ),
			)
			: array(
				'question_type' => $type,
				'question_text' => 'Question ' . wp_generate_password( 6, false ),
				'answers'       => array(
					array(
						'key'     => 'A',
						'text'    => 'right',
						'correct' => true,
					),
					array(
						'key'  => 'B',
						'text' => 'wrong',
					),
				),
			);
		$id    = $repo->create( $admin, $data );
		$repo->publish( $admin, $id );
		$repo->add_to_pool( $admin, $module, $id );
		return $id;
	}

	/**
	 * Active manual grant.
	 *
	 * @param int $user User.
	 * @return void
	 */
	private function grant( int $user ): void {
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
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
	}

	/**
	 * Mark a question or card as imported from a foreign package with rights.
	 *
	 * @param string               $type    `question` or `flashcard`.
	 * @param int                  $id      Question.
	 * @param string               $status  Rights status of the revision.
	 * @param array<string, mixed> $license Licence fields.
	 * @return void
	 */
	private function foreign( string $type, int $id, string $status, array $license = array() ): void {
		$db = Database::current();
		$db->insert(
			'ccap_source_map',
			array(
				'uid'           => $db->uuid(),
				'object_type'   => $type,
				'object_id'     => $id,
				'source_system' => self::FOREIGN,
				'source_id'     => $type . '-' . $id,
			),
			array( 'created_at', 'updated_at' )
		);
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_question_revision' ) . '` r JOIN `' . $db->table( 'ccap_question' ) . '` q ON q.current_revision_id = r.id SET r.rights_status = %s, r.license_reference = %s WHERE q.id = %d', $status, array() === $license ? '' : (string) wp_json_encode( $license ), $id ) );
	}

	/**
	 * Protected media row linked to an object.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object.
	 * @param string $system      Source system.
	 * @param string $status      Rights status.
	 * @return array{id: int, uid: string}
	 */
	private function media( string $object_type, int $object_id, string $system, string $status ): array {
		$db    = Database::current();
		$uid   = $db->uuid();
		$media = $db->insert(
			'ccap_media',
			array(
				'uid'           => $uid,
				'source_system' => $system,
				'source_id'     => 'local' === $system ? null : 'MED-' . wp_generate_password( 6, false ),
				'package_path'  => 'media/file-' . $status . '.pdf',
				'media_type'    => 'document',
				'mime_type'     => 'application/pdf',
				'sha256'        => hash( 'sha256', wp_generate_password( 12, false ) ),
				'access'        => 'protected',
				'rights_status' => $status,
			),
			array( 'created_at', 'updated_at' )
		);
		$db->insert(
			'ccap_content_media',
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'media_id'    => $media,
				'role'        => 'attachment',
			)
		);
		return array(
			'id'  => $media,
			'uid' => $uid,
		);
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 * @return CourseException
	 */
	private function rejected( callable $call, string $code ): CourseException {
		try {
			$call();
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code, $e->getMessage() );
			return $e;
		}
		self::fail( 'expected ' . $code );
	}

	/**
	 * Question text of the current revision.
	 *
	 * @param int $id Question.
	 * @return string
	 */
	private static function text( int $id ): string {
		$db = Database::current();
		return (string) $db->scalar( $db->prepare( 'SELECT r.question_text FROM `' . $db->table( 'ccap_question' ) . '` q JOIN `' . $db->table( 'ccap_question_revision' ) . '` r ON r.id = q.current_revision_id WHERE q.id = %d', $id ) );
	}

	/**
	 * R0-10 §2.2: the central gate and the module pool, smart trainer and
	 * question delivery never hand withheld foreign questions to learners;
	 * own content is never withheld (E-18).
	 */
	public function test_pool_trainer_and_delivery_withhold_foreign_content(): void {
		$gate = RightsGate::current();
		self::assertSame(
			array(
				$this->q[0] => 'unknown',
				$this->q[1] => 'expired',
				$this->q[4] => 'unknown',
				$this->c[0] => 'restricted',
			),
			$gate->questions( array_merge( $this->q, $this->c ) ),
			'foreign unknown, expired licence, image dependency, foreign restricted card; own unknown (Q2) and valid licence (Q3) are delivered'
		);
		self::assertNull( $gate->question( $this->q[2] ) );

		$repo = QuestionRepository::current();
		self::assertSame( array( $this->q[2], $this->q[3], $this->c[1] ), $repo->pool( $this->module ) );
		self::assertSame( array( $this->q[2], $this->q[3] ), array_keys( $repo->pool_revisions( $this->module, true ) ) );
		self::assertCount( 7, $repo->pool( $this->module, true ), 'the editorial side still sees everything' );
		self::assertNull( $repo->pool_member( $this->module, $this->q[0] ) );
		self::assertNotNull( $repo->pool_member( $this->module, $this->q[3] ) );

		$learner   = Actor::user( $this->learner );
		$presenter = QuestionPresenter::current();
		$this->rejected( fn() => $presenter->deliver( $learner, $this->course, $this->module, $this->q[0] ), 'ccap_not_found' );
		$this->rejected( fn() => $presenter->deliver( $learner, $this->course, $this->module, $this->q[1] ), 'ccap_not_found' );
		self::assertArrayHasKey( 'token', $presenter->deliver( $learner, $this->course, $this->module, $this->q[2] ) );

		// Smart trainer: never a withheld question, also not in the counts.
		$trainer = SmartTrainer::current();
		self::assertSame( 2, $trainer->summary( $learner, $this->course )['total'] );
		for ( $i = 0; $i < 6; $i++ ) {
			$next = $trainer->next( $learner, $this->course );
			self::assertNotNull( $next );
			self::assertContains( (int) $next['question_id'], array( $this->q[2], $this->q[3] ) );
		}
		$this->rejected( fn() => $trainer->practice( $learner, $this->course, array( 'question' => $this->q[0] ) ), 'ccap_not_found' );

		// R0-21: a rights change bumps the course generation and applies at once (no stale verdict, also for old tokens).
		$token = $presenter->deliver( $learner, $this->course, $this->module, $this->q[3] )['token'];
		$gen   = CacheStore::course_gen( $this->course );
		Rights::current()->override( Actor::user( $this->admin ), 'question', $this->q[3], 'restricted', 'Licence revoked' );
		self::assertNotSame( $gen, CacheStore::course_gen( $this->course ), 'course generation renewed' );
		$this->rejected( fn() => QuestionPresenter::current()->verify( $learner, $token ), 'ccap_not_found' );
		self::assertSame( array( $this->q[2] ), array_keys( $repo->pool_revisions( $this->module, true ) ) );
		self::assertSame( 1, $trainer->summary( $learner, $this->course )['total'] );

		// Release: the course generation changes again and the question is back.
		$gen = CacheStore::course_gen( $this->course );
		Rights::current()->override( Actor::user( $this->admin ), 'question', $this->q[3], 'licensed', 'New contract' );
		self::assertNotSame( $gen, CacheStore::course_gen( $this->course ) );
		self::assertSame( array( $this->q[2], $this->q[3] ), array_keys( $repo->pool_revisions( $this->module, true ) ) );
		$gen = CacheStore::course_gen( $this->course );
		self::assertSame( array(), Rights::current()->set_course_meta( Actor::user( $this->admin ), $this->course, array( 'status' => 'licensed' ), 'Contract' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertNotSame( $gen, CacheStore::course_gen( $this->course ), 'course rights saved: generation renewed' );
	}

	/**
	 * Exam draw and fixed variants leave out withheld content; a running
	 * attempt keeps its snapshot.
	 */
	public function test_exam_draw_and_running_attempt(): void {
		$rules   = ExamRules::current();
		$admin   = Actor::user( $this->admin );
		$learner = Actor::user( $this->learner );
		$db      = Database::current();
		self::assertSame( array( $this->q[2], $this->q[3] ), array_keys( ExamRules::variant_candidates( $this->course ) ), 'withheld questions are not offered for variants' );
		$variant = $rules->create_variant( $admin, $this->course, 1, array( $this->q[2], $this->q[3] ) );
		$rule    = $rules->create(
			$admin,
			$this->course,
			'draw',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			600
		);
		$rules->set_blueprint( $admin, $rule, $this->module, 5 );
		$rules->publish( $admin, $rule );
		$sessions = ExamSessions::current();
		$drawn    = static function ( int $session ) use ( $db ): array {
			$ids = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d', $session ) ) );
			sort( $ids );
			return $ids;
		};
		$session  = $sessions->start( $learner, $rule, null, wp_generate_uuid4(), new Randomizer( '7' ) );
		self::assertSame( array( $this->q[2], $this->q[3] ), $drawn( $session ), 'only deliverable, scorable questions' );

		// The rights change during the attempt: the running attempt keeps its questions.
		Rights::current()->override( $admin, 'question', $this->q[3], 'unknown', 'Licence unclear' );
		$view = $sessions->view( $learner, $session );
		self::assertCount( 2, $view['questions'] );
		self::assertContains( self::text( $this->q[3] ), array_map( static fn( array $i ): string => (string) $i['question']['text'], $view['questions'] ) );
		$sessions->cancel( $learner, $session );

		// A new attempt no longer draws it.
		$session = $sessions->start( $learner, $rule, null, wp_generate_uuid4(), new Randomizer( '8' ) );
		self::assertSame( array( $this->q[2] ), $drawn( $session ) );
		$sessions->cancel( $learner, $session );

		// A fixed variant with withheld content cannot start.
		$fixed = $rules->create(
			$admin,
			$this->course,
			'fixed',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			600
		);
		$rules->set_blueprint( $admin, $fixed, $this->module, 1 );
		$rules->set_form_mode( $admin, $fixed, 'variants' );
		$rules->publish( $admin, $fixed );

		$e = $this->rejected( fn() => $sessions->start( $learner, $fixed, $variant, wp_generate_uuid4(), new Randomizer( '9' ) ), 'ccap_exam_not_startable' );
		self::assertSame( 'variant_rights', $e->getMessage() );
	}

	/**
	 * Quizzes from the module pool and flashcard decks leave out withheld
	 * content, also in a draw made before the rights changed.
	 */
	public function test_quiz_pool_and_flashcard_deck(): void {
		$admin   = Actor::user( $this->admin );
		$learner = Actor::user( $this->learner );
		$quiz    = $this->post( 'ccap_lesson', 'Pool quiz', $this->module );
		ActivityService::current()->define(
			$admin,
			$quiz,
			'quiz',
			array(
				'source' => 'module_pool',
				'count'  => 5,
			)
		);
		self::assertSame( array( $this->q[2], $this->q[3] ), array_values( array_map( 'intval', QuizPool::current()->eligible( $quiz ) ) ) );
		QuizPool::current()->draw( $learner, $this->course, $quiz );
		$presenter = QuestionPresenter::current();
		$ids       = $presenter->quiz_questions( $quiz, $this->learner );
		sort( $ids );
		self::assertSame( array( $this->q[2], $this->q[3] ), $ids );

		Rights::current()->override( $admin, 'question', $this->q[3], 'restricted', 'Must not be shown' );
		self::assertSame( array( $this->q[2] ), $presenter->quiz_questions( $quiz, $this->learner ), 'the stored draw no longer lists it' );
		$this->rejected( fn() => $presenter->deliver_inline( $learner, $this->course, $quiz, $this->q[3] ), 'ccap_not_found' );

		// Flashcard deck: the foreign restricted card is neither counted nor delivered.
		$deck = $this->post( 'ccap_lesson', 'Deck', $this->module );
		$db   = Database::current();
		foreach ( $this->c as $i => $card ) {
			$db->insert(
				'ccap_activity_question',
				array(
					'activity_id' => $deck,
					'question_id' => $card,
					'sort_order'  => $i,
				)
			);
		}
		self::assertSame( array( $this->c[1] ), $presenter->deck( $deck ) );
		self::assertSame( 1, Flashcards::current()->counts( $this->learner, $deck )['total'] );
		self::assertSame( $this->c, $presenter->inline_questions( $deck ), 'the editorial list keeps the card' );
	}

	/**
	 * Lessons with withheld rights and withheld media: learners get a
	 * neutral placeholder, editors a notice; the file route answers 404.
	 */
	public function test_lesson_view_media_route_and_preview(): void {
		$admin = Actor::user( $this->admin );
		$this->grant( $this->admin );
		$plain = $this->post( 'ccap_lesson', 'Foreign lesson', $this->module, 'Secret foreign text.' );
		ActivityService::current()->define( $admin, $plain, 'lesson', array() );
		$db = Database::current();
		$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_source_map' ) . "` WHERE object_type = 'lesson' AND object_id = %d", $plain ) );
		$db->insert(
			'ccap_source_map',
			array(
				'uid'           => $db->uuid(),
				'object_type'   => 'lesson',
				'object_id'     => $plain,
				'source_system' => self::FOREIGN,
				'source_id'     => 'L-1',
			),
			array( 'created_at', 'updated_at' )
		);
		$gen = CacheStore::course_gen( $this->course );
		self::assertTrue( Rights::import_meta( $plain, array( 'status' => 'unknown' ) ) );
		self::assertNotSame( $gen, CacheStore::course_gen( $this->course ), 'imported lesson rights renew the course generation' );
		$file_lesson = $this->post( 'ccap_lesson', 'Handout', $this->module, 'Handout text.' );
		$file        = $this->media( 'lesson', $file_lesson, self::FOREIGN, 'restricted' );
		ActivityService::current()->define( $admin, $file_lesson, 'file', array( 'media_uid' => $file['uid'] ) );
		$video_lesson = $this->post( 'ccap_lesson', 'Video', $this->module, 'Video text.' );
		$video        = $this->media( 'lesson', $video_lesson, 'local', 'unknown' );
		ActivityService::current()->define(
			$admin,
			$video_lesson,
			'video',
			array(
				'provider'  => 'media',
				'media_uid' => $video['uid'],
			)
		);
		CoursePublisher::current()->publish( $admin, $this->course );

		$learner = Actor::user( $this->learner );
		$page    = LessonView::render( $learner, $this->course, $plain );
		self::assertSame( 200, $page['status'] );
		self::assertStringContainsString( 'This content is not available at the moment.', $page['body'] );
		self::assertStringNotContainsString( 'Secret foreign text.', $page['body'] );
		self::assertSame( array(), A11yDom::fragment_problems( $page['body'] ) );
		$author = LessonView::render( $admin, $this->course, $plain );
		self::assertStringContainsString( 'Not shown to learners: the rights of this imported content are unclear.', $author['body'] );
		self::assertStringNotContainsString( 'Secret foreign text.', $author['body'] );

		$page = LessonView::render( $learner, $this->course, $file_lesson );
		self::assertStringContainsString( 'Handout text.', $page['body'], 'the lesson stays, only the medium is replaced' );
		self::assertStringContainsString( 'Media not available', $page['body'] );
		self::assertStringNotContainsString( 'value="download"', $page['body'] );
		self::assertStringNotContainsString( $file['uid'], $page['body'] );
		self::assertSame( array(), A11yDom::fragment_problems( $page['body'] ) );
		self::assertStringContainsString( 'must not be shown', LessonView::render( $admin, $this->course, $file_lesson )['body'] );
		$page = LessonView::render( $learner, $this->course, $video_lesson );
		self::assertStringContainsString( 'Media not available', $page['body'], 'a local upload marked unknown counts as foreign (R0-10 §2.2)' );
		self::assertStringNotContainsString( $video['uid'], $page['body'] );
		wp_set_current_user( $this->learner );
		$post = array(
			'_wpnonce' => wp_create_nonce( LessonView::NONCE . ':' . $file_lesson ),
			'ccap_do'  => 'download',
		);
		self::assertSame( 'ccap_not_found', LessonView::handle( $learner, $this->course, $file_lesson, $post )['status'] );

		// Protected file route: withheld → 404 like an unknown file; editors pass the rights check.
		$files = ProtectedFiles::current();
		$e     = $this->rejected( fn() => $files->prepare( $learner, $file['uid'] ), 'ccap_not_found' );
		self::assertSame( 'Unknown file.', $e->getMessage() );
		$e = $this->rejected( fn() => $files->prepare( $admin, $file['uid'] ), 'ccap_not_found' );
		self::assertSame( 'File not available.', $e->getMessage(), 'editor: rights check passed, only the fixture has no file' );
		$own = $this->media( 'course', $this->course, 'local', 'own' );
		$bad = $this->media( 'course', $this->course, self::FOREIGN, 'unknown' );
		self::assertSame( array( $own['uid'] ), array_column( $files->listing( $learner, $this->course ), 'uid' ) );
		self::assertSame( array( $own['uid'], $bad['uid'] ), array_column( $files->listing( $admin, $this->course ), 'uid' ) );

		// Preview as learner: notices instead of the withheld content.
		$html = PreviewScreen::html( $this->course, $this->admin );
		self::assertStringContainsString( 'Not shown to learners: the rights of this imported content are unclear.', $html );
		self::assertStringContainsString( 'Not shown to learners: the licence of this imported content has expired.', $html );
		self::assertStringContainsString( '4 imported questions are not shown to learners because of their rights.', $html );
		self::assertStringContainsString( 'Learners see &quot;Media not available&quot; in place of a medium of this lesson.', $html );
		self::assertStringNotContainsString( self::text( $this->q[0] ), $html );
		self::assertStringContainsString( self::text( $this->q[3] ), $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );

		// Validator: the lesson rights are checked (VAL-100 in the required path).
		$items = CourseValidator::validate( $this->course )['issues'];
		$found = array_values( array_filter( $items, static fn( array $i ): bool => 'VAL-100' === $i['code'] ) );
		self::assertContains( 'lesson:' . $plain, $found[0]['params']['items'] );
	}

	/**
	 * Package with a course, a module (lesson + pool) and optional extras.
	 *
	 * @param string                    $system   Source system.
	 * @param array<string, mixed>|null $lesson   Lesson rights.
	 * @param array<string, mixed>|null $defaults `default_rights`.
	 * @param array<string, mixed>      $extra    Extra entity records.
	 * @return string
	 */
	private function package( string $system, ?array $lesson, ?array $defaults = null, array $extra = array() ): string {
		$lesson_record = array(
			'source_id'     => 'L-1',
			'title'         => 'Imported lesson',
			'body_html'     => '<p>Imported text</p>',
			'activity_type' => 'lesson',
			'settings'      => (object) array(),
		);
		if ( null !== $lesson ) {
			$lesson_record['rights'] = $lesson;
		}
		$question = static fn( string $id, string $type ): array => PackageExporter::sealed(
			array(
				'source_id' => $id,
				'revision'  => array(
					'question_type' => $type,
					'text_html'     => 'Imported ' . $id,
					'settings'      => QuestionTypes::FLASHCARD === $type ? array( 'back' => 'Back' ) : (object) array(),
				),
				'answers'   => QuestionTypes::FLASHCARD === $type ? array() : array(
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
		$records  = array(
			'questions' => array( $question( 'Q-1', 'single_choice' ), $question( 'Q-C', QuestionTypes::FLASHCARD ) ),
			'lessons'   => array( PackageExporter::sealed( $lesson_record ) ),
			'modules'   => array(
				PackageExporter::sealed(
					array(
						'source_id'     => 'M-1',
						'title'         => 'Imported module',
						'summary'       => '',
						'lessons'       => array(
							array(
								'lesson' => 'L-1',
								'sort'   => 1,
							),
						),
						'question_pool' => array(
							array(
								'question' => 'Q-1',
								'sort'     => 1,
							),
						),
					)
				),
			),
			'courses'   => array(
				PackageExporter::sealed(
					array(
						'source_id'        => 'C-1',
						'title'            => 'Imported course',
						'slug'             => 'imported-' . $system,
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
		) + $extra;
		$file     = PackageExporter::write( $records, $system, 'Fixture', $this->dir . '/' . $system . '-' . wp_generate_password( 4, false ) . '.zip' );
		if ( null !== $defaults ) {
			$zip = new \ZipArchive();
			$zip->open( $file );
			$manifest                   = (array) json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
			$manifest['default_rights'] = $defaults;
			$manifest['publisher']      = array( 'name' => 'Harbour Press' );
			$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			$zip->close();
		}
		return $file;
	}

	/**
	 * Run an import to the end.
	 *
	 * @param string $file Package.
	 * @return int Batch.
	 */
	private function import( string $file ): int {
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $file );
		self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
		self::assertSame( 'previewed', $service->preview( $admin, $batch )['status'] );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		return $batch;
	}

	/**
	 * Object of a source ID.
	 *
	 * @param string $system Source system.
	 * @param string $type   Type.
	 * @param string $source Source ID.
	 * @return int
	 */
	private static function object( string $system, string $type, string $source ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT object_id FROM `' . $db->table( 'ccap_source_map' ) . '` WHERE source_system = %s AND object_type = %s AND source_id = %s', $system, $type, $source ) );
	}

	/**
	 * Rules of the preview items with a rule, by source ID.
	 *
	 * @param string $file Package.
	 * @return array<string, string>
	 */
	private function preview_rules( string $file ): array {
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $file );
		$service->validate( $admin, $batch );
		$rules = array_column( array_filter( $service->preview( $admin, $batch )['items'], static fn( array $i ): bool => null !== $i['rule'] ), 'rule', 'source_id' );
		$service->discard( $admin, $batch );
		return $rules;
	}

	/**
	 * Lesson `rights`: imported into `_ccap_rights` (shared cleaning),
	 * checked by the package scan, withheld for learners, exported again.
	 */
	public function test_lesson_rights_import_and_export(): void {
		$this->import(
			$this->package(
				'fixture-lr',
				array(
					'status'      => 'unknown',
					'owner'       => 'Somebody',
					'source_url'  => 'https://example.com/source',
					'valid_until' => '2030-01-01',
				)
			)
		);
		$lesson = self::object( 'fixture-lr', 'lesson', 'L-1' );
		self::assertGreaterThan( 0, $lesson );
		self::assertSame(
			array(
				'status'      => 'unknown',
				'owner'       => 'Somebody',
				'source_url'  => 'https://example.com/source',
				'valid_until' => '2030-01-01',
			),
			Rights::course_meta( $lesson )
		);
		self::assertSame( 'unknown', RightsGate::current()->post( $lesson ) );
		$course = self::object( 'fixture-lr', 'course', 'C-1' );
		$val    = array_values( array_filter( CourseValidator::validate( $course )['issues'], static fn( array $i ): bool => 'VAL-100' === $i['code'] ) );
		self::assertSame( array( 'lesson:' . $lesson ), $val[0]['params']['items'] );

		// Export carries the lesson rights again (round trip).
		$records = PackageExporter::records( Actor::user( $this->admin ), array( $course ) );
		self::assertSame( 'unknown', $records['lessons'][0]['rights']['status'] ?? null );
		self::assertArrayNotHasKey( '_source', $records['lessons'][0]['rights'] );

		// Without lesson rights no metadata and no `default_rights` for lessons (R0-10 §1.2).
		$this->import( $this->package( 'fixture-lr2', null, array( 'status' => 'licensed' ) ) );
		self::assertSame( array(), Rights::course_meta( self::object( 'fixture-lr2', 'lesson', 'L-1' ) ) );
		self::assertSame( 'licensed', Rights::course_meta( self::object( 'fixture-lr2', 'module', 'M-1' ) )['status'] ?? '' );

		// Invalid lesson rights: record error `schema`.
		self::assertSame( 'schema', $this->preview_rules( $this->package( 'fixture-lr3', array( 'status' => 'mine' ) ) )['L-1'] ?? '' );
	}

	/**
	 * D2: an imported fixed variant holding a flashcard is a record error.
	 */
	public function test_imported_variant_with_flashcard_is_rejected(): void {
		$blueprint = static fn( array $questions ): array => array(
			'blueprints' => array(
				PackageExporter::sealed(
					array(
						'source_id'      => 'B-1',
						'course'         => 'C-1',
						'version'        => 'v1',
						'status'         => 'published',
						'passing_rule'   => array(
							'min_ratio' => array(
								'num' => 1,
								'den' => 2,
							),
						),
						'draws'          => array(
							array(
								'module' => 'M-1',
								'count'  => 1,
							),
						),
						'fixed_variants' => array(
							array(
								'key'       => 'A',
								'questions' => $questions,
							),
						),
					)
				),
			),
		);
		$rules     = $this->preview_rules( $this->package( 'fixture-vf', null, null, $blueprint( array( 'Q-1', 'Q-C' ) ) ) );
		self::assertSame( 'variant_flashcard', $rules['B-1'] ?? '' );
		self::assertSame( array( 'IMP-V6.01' ), array( \CourseCaptain\Import\ImportReport::code( 'variant_flashcard' ) ) );
		self::assertStringContainsString( 'Flashcards are never part of exams', \CourseCaptain\Import\ImportReport::message( 'cc.imp.variant_flashcard', 'IMP-V6.01', array() )[0] );

		// A card of the `flashcards` entity is rejected the same way.
		$cards = array(
			'flashcards' => array(
				PackageExporter::sealed(
					array(
						'source_id'  => 'F-1',
						'front_html' => 'Front',
						'back_html'  => 'Back',
						'mode'       => 'self_assessment',
						'tags'       => array(),
					)
				),
			),
		);
		self::assertSame( 'variant_flashcard', $this->preview_rules( $this->package( 'fixture-vf2', null, null, $blueprint( array( 'Q-1', 'F-1' ) ) + $cards ) )['B-1'] ?? '' );

		// Without a card the variant is fine.
		self::assertArrayNotHasKey( 'B-1', $this->preview_rules( $this->package( 'fixture-vf3', null, null, $blueprint( array( 'Q-1' ) ) ) ) );
	}

	/**
	 * D8 follow-up: a changed `default_rights` reaches unchanged course and
	 * module records that still carry imported rights; local rights stay.
	 */
	public function test_changed_default_rights_reach_unchanged_records(): void {
		$file = $this->package( 'fixture-dr', null, array( 'status' => 'licensed' ) );
		$this->import( $file );
		$course = self::object( 'fixture-dr', 'course', 'C-1' );
		$module = self::object( 'fixture-dr', 'module', 'M-1' );
		self::assertSame( 'licensed', Rights::course_meta( $module )['status'] ?? '' );
		self::assertSame( 'licensed', Rights::course_meta( $course )['status'] ?? '' );
		self::assertSame( array(), Rights::current()->set_course_meta( Actor::user( $this->admin ), $course, array( 'status' => 'public' ), 'Checked locally' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.

		// Same records, new manifest default: the module follows, the locally saved course rights stay.
		$copy = $this->dir . '/second.zip';
		copy( $file, $copy );
		$zip = new \ZipArchive();
		$zip->open( $copy );
		$manifest                   = (array) json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		$manifest['default_rights'] = array( 'status' => 'unknown' );
		$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$zip->close();
		$batch = $this->import( $copy );
		$db    = Database::current();
		self::assertSame( 'unchanged', (string) $db->scalar( $db->prepare( 'SELECT action FROM `' . $db->table( 'ccap_import_item_log' ) . "` WHERE batch_id = %d AND entity = 'modules'", $batch ) ) );
		self::assertSame( 'unknown', Rights::course_meta( $module )['status'] ?? '' );
		self::assertSame( array( 'status' => 'public' ), Rights::course_meta( $course ) );

		// Without `default_rights` the imported default is removed again.
		$third = $this->dir . '/third.zip';
		copy( $file, $third );
		$zip = new \ZipArchive();
		$zip->open( $third );
		$manifest = (array) json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		unset( $manifest['default_rights'] );
		$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$zip->close();
		$this->import( $third );
		self::assertSame( array(), Rights::course_meta( $module ) );
	}
}
