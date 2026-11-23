<?php
/**
 * R1-07: activities, video rules, progress, lesson quiz, media.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Activity\ExternalMedia;
use CourseCaptain\Activity\MediaLibrary;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;

/**
 * Real tables; R0-08 §2, R0-05 GM-007/008/011/012/013/014.
 *
 * @group R1-07
 */
final class ActivityTest extends SchemaTestCase {

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
	 * Build course, module and learner grant.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course', 0 );
		$this->module  = $this->post( 'ccap_module', 0 );
		PolicyService::flush();
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $this->module );
		$db = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $this->learner,
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Post type.
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
				'post_content' => 'content',
			)
		);
	}

	/**
	 * Activity definition, validation, freezing and type lock (R0-08 §2.3).
	 */
	public function test_define_freeze_and_type_lock(): void {
		$service = ActivityService::current();
		$admin   = Actor::user( $this->admin );
		$lesson  = $this->post( 'ccap_lesson', $this->module );
		$service->define(
			$admin,
			$lesson,
			'video',
			array(
				'provider'         => 'url',
				'url'              => 'https://media.fixture.test/cc/alpha-04.mp4',
				'duration_seconds' => 100,
			)
		);
		try {
			$service->define( $admin, $lesson, 'video', array( 'url' => 'http://insecure.test/x.mp4' ) );
			self::fail( 'http URL accepted' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
		}

		CoursePublisher::current()->publish( $admin, $this->course );
		$structure = json_decode( (string) CoursePublisher::current()->current_revision( $this->course )['structure'], true );
		$frozen    = $structure['modules'][0]['lessons'][0]['activity'];
		self::assertSame( 'video', $frozen['type'] );
		self::assertSame( '1.0.0', $frozen['contract_version'] );
		self::assertSame( 'manual_confirm', $frozen['completion_rule']['type'] );
		self::assertStringStartsWith( 'sha256:', $frozen['settings_hash'] );

		try {
			$service->define( $admin, $lesson, 'lesson', array() );
			self::fail( 'type change after publication' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_activity_type_locked', $e->error_code );
		}
	}

	/**
	 * GM-008 / GM-012 / GM-013 / GM-014: video URL, foreign hosts, external media mode, subtitles.
	 *
	 * @group GM-008
	 * @group GM-012
	 * @group GM-013
	 * @group GM-014
	 */
	public function test_GM_008_012_013_014_video_view(): void {
		$placeholder = ExternalMedia::video_view(
			array(
				'placeholder' => true,
				'url'         => 'https://media.fixture.test/x.mp4',
			)
		);
		self::assertNull( $placeholder['video_url'] );

		$site    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$own     = 'https://' . $site . '/cc/alpha-04.mp4';
		$sub     = 'https://cdn.' . $site . '/a.mp4';
		$foreign = 'https://videos.example.test/v.mp4';
		self::assertSame( array( 'videos.example.test' ), ExternalMedia::foreign_hosts( array( $own, $sub, '/relative.mp4', $foreign ) ) );
		$allow = static fn(): array => array( 'videos.example.test' );
		add_filter( 'course_captain_trusted_media_hosts', $allow );
		self::assertSame( array(), ExternalMedia::foreign_hosts( array( $foreign ) ) );
		remove_filter( 'course_captain_trusted_media_hosts', $allow );
		self::assertSame( array( 'videos.example.test' ), ExternalMedia::foreign_hosts( array( $foreign ) ), 'after reset' );

		self::assertSame( ExternalMedia::ALWAYS_LOAD, ExternalMedia::mode(), 'default E-45a' );
		self::assertSame( $foreign, ExternalMedia::video_view( array( 'url' => $foreign ) )['video_url'] );
		$blocked = ExternalMedia::video_view(
			array(
				'url'          => $foreign,
				'subtitles'    => 'vtt',
				'subtitle_url' => 'https://videos.example.test/v.vtt',
			),
			false,
			ExternalMedia::CLICK_TO_LOAD
		);
		self::assertNull( $blocked['video_url'] );
		self::assertNull( $blocked['subtitles_url'], 'subtitles only after the click' );
		self::assertTrue( $blocked['consent_required'] );
		self::assertSame( $foreign, ExternalMedia::video_view( array( 'url' => $foreign ), true, ExternalMedia::CLICK_TO_LOAD )['video_url'] );
		self::assertSame( $own, ExternalMedia::video_view( array( 'url' => $own ), false, ExternalMedia::CLICK_TO_LOAD )['video_url'], 'own host needs no consent' );

		self::assertNull( ExternalMedia::video_view( array( 'url' => $own ) )['subtitles_url'], 'default off' );
		self::assertSame(
			'https://' . $site . '/a.vtt',
			ExternalMedia::video_view(
				array(
					'url'          => $own,
					'subtitles'    => 'vtt',
					'subtitle_url' => 'https://' . $site . '/a.vtt',
				)
			)['subtitles_url']
		);
		self::assertNull(
			ExternalMedia::video_view(
				array(
					'url'          => $own,
					'subtitles'    => 'vtt',
					'subtitle_url' => 'http://' . $site . '/a.vtt',
				)
			)['subtitles_url'],
			'vtt only https'
		);
	}

	/**
	 * GM-011 and completion: position kept without field; manual confirm
	 * completes; completion is monotonic; no access → no progress.
	 *
	 * @group GM-011
	 * @group GM-209
	 */
	public function test_GM_011_progress(): void {
		$lesson = $this->post( 'ccap_lesson', $this->module );
		ActivityService::current()->define( Actor::user( $this->admin ), $lesson, 'video', array( 'duration_seconds' => 100 ) );
		$progress = ActivityProgress::current();
		$learner  = Actor::user( $this->learner );

		self::assertSame( 42, (int) $progress->interact( $learner, $this->course, $lesson, 'position', array( 'position_seconds' => 42 ) )['position_seconds'] );
		$state = $progress->interact( $learner, $this->course, $lesson, 'position' );
		self::assertSame( 42, (int) $state['position_seconds'] );
		self::assertSame( 'started', $state['status'] );

		$state = $progress->interact( $learner, $this->course, $lesson, 'confirm' );
		self::assertSame( 'completed', $state['status'] );
		self::assertSame( 'manual_confirm', $state['reason_code'] );
		self::assertNotNull( $state['completed_at'] );
		self::assertSame( 'completed', $progress->interact( $learner, $this->course, $lesson, 'position', array( 'position_seconds' => 1 ) )['status'], 'monotonic' );

		$stranger = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		try {
			$progress->interact( Actor::user( $stranger ), $this->course, $lesson, 'confirm' );
			self::fail( 'progress without access' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_content_locked', $e->error_code );
		}
		self::assertNull( $progress->state( $stranger, $lesson ) );
	}

	/**
	 * GM-209 (E-70, A-06): `PUT progress` over REST. A late request with an
	 * older state after a newer one never moves the state back; `completed`
	 * can only be taken back by the learner for `manual_confirm`
	 * (`unconfirm`), an evidence-based completion (`downloaded`) never.
	 *
	 * @group R1-18
	 * @group GM-209
	 */
	public function test_GM_209_progress_monotonic_over_rest(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$admin  = Actor::user( $this->admin );
		$manual = $this->post( 'ccap_lesson', $this->module );
		ActivityService::current()->define( $admin, $manual, 'lesson', array(), array( 'type' => 'manual_confirm' ) );
		$file = $this->post( 'ccap_lesson', $this->module );
		ActivityService::current()->define( $admin, $file, 'file', array( 'media_uid' => wp_generate_uuid4() ), array( 'type' => 'downloaded' ) );
		$put      = function ( int $lesson, string $kind, array $payload = array() ): \WP_REST_Response {
			$request = new \WP_REST_Request( 'PUT', '/course-captain/v1/courses/' . $this->course . '/lessons/' . $lesson . '/progress' );
			$request->set_param( 'kind', $kind );
			$request->set_param( 'payload', $payload );
			return rest_do_request( $request );
		};
		$progress = ActivityProgress::current();
		wp_set_current_user( $this->learner );

		self::assertSame( 200, $put( $manual, 'position', array( 'position_seconds' => 60 ) )->get_status() );
		$done = $put( $manual, 'confirm' );
		self::assertSame( 200, $done->get_status() );
		self::assertSame( 'completed', $done->get_data()['status'] );
		$completed_at = $progress->state( $this->learner, $manual )['completed_at'];
		self::assertNotNull( $completed_at );

		// Late request carrying the older state (position 10, nothing confirmed yet).
		$late = $put( $manual, 'position', array( 'position_seconds' => 10 ) );
		self::assertSame( 200, $late->get_status() );
		self::assertSame( 'completed', $late->get_data()['status'], 'state never goes back' );
		self::assertSame( 'manual_confirm', $late->get_data()['reason_code'] );
		self::assertSame( $completed_at, $progress->state( $this->learner, $manual )['completed_at'] );

		// The learner takes back the own manual confirmation.
		$back = $put( $manual, 'unconfirm' );
		self::assertSame( 200, $back->get_status() );
		self::assertSame( 'started', $back->get_data()['status'] );
		self::assertNull( $back->get_data()['completed_at'] );
		self::assertNull( $back->get_data()['content_hash'] );
		self::assertSame( 'started', $put( $manual, 'unconfirm' )->get_data()['status'], 'repeat changes nothing' );
		self::assertSame( 'completed', $put( $manual, 'confirm' )->get_data()['status'], 'confirm again' );

		// Evidence-based completion stays.
		self::assertSame( 'completed', $put( $file, 'download' )->get_data()['status'] );
		$denied = $put( $file, 'unconfirm' );
		self::assertSame( 422, $denied->get_status() );
		self::assertSame( 'ccap_validation_failed', $denied->as_error()->get_error_code() );
		$state = $progress->state( $this->learner, $file );
		self::assertSame( 'completed', $state['status'] );
		self::assertSame( 'downloaded', $state['reason_code'] );
		self::assertNotNull( $state['completed_at'] );
		self::assertSame( 'completed', $put( $file, 'position', array( 'position_seconds' => 0 ) )->get_data()['status'] );
	}

	/**
	 * GM-007: lesson quiz delivered without solution; evaluation through the token.
	 *
	 * @group GM-007
	 */
	public function test_GM_007_lesson_quiz(): void {
		$lesson = $this->post( 'ccap_lesson', $this->module );
		$repo   = QuestionRepository::current();
		$admin  = Actor::user( $this->admin );
		$db     = Database::current();
		$ids    = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$question = $repo->create(
				$admin,
				array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => 'Quiz ' . $i,
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
				)
			);
			$repo->publish( $admin, $question );
			$db->insert(
				'ccap_activity_question',
				array(
					'activity_id' => $lesson,
					'question_id' => $question,
					'sort_order'  => $i,
				)
			);
			$ids[] = $question;
		}
		$presenter = QuestionPresenter::current();
		self::assertSame( $ids, $presenter->inline_questions( $lesson ) );
		$delivery = $presenter->deliver_inline( Actor::user( $this->learner ), $this->course, $lesson, $ids[0] );
		self::assertStringNotContainsString( 'correct', (string) wp_json_encode( $delivery['question'] ) );
		self::assertTrue( $presenter->evaluate( Actor::user( $this->learner ), $delivery['token'], array( 'A' ) )['evaluation']->correct() );
		try {
			$presenter->deliver_inline( Actor::user( $this->learner ), $this->course, $this->post( 'ccap_lesson', $this->module ), $ids[0] );
			self::fail( 'question of another lesson' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_not_found', $e->error_code );
		}
	}

	/**
	 * UI-F5 (h5 L-2, R0-08 §2.4/§6): a quiz with `source: module_pool` draws
	 * `count` questions per attempt from the module pool with a seed per
	 * attempt (reproducible, stream `pool:<lesson>`), never flashcards; the
	 * draw is delivered and fed into the quiz progress; a new attempt draws
	 * again; the validator blocks only an empty pool (VAL-029).
	 *
	 * @group UI-F5
	 */
	public function test_quiz_from_module_pool(): void {
		$lesson = $this->post( 'ccap_lesson', $this->module );
		$admin  = Actor::user( $this->admin );
		$repo   = QuestionRepository::current();
		$db     = Database::current();
		ActivityService::current()->define(
			$admin,
			$lesson,
			'quiz',
			array(
				'source' => 'module_pool',
				'count'  => 3,
			)
		);
		try {
			ActivityService::current()->define( $admin, $lesson, 'quiz', array( 'count' => 0 ) );
			self::fail( 'count below 1' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
		}
		$codes = static fn( int $course ): array => array_map( static fn( array $i ): string => $i['severity'] . ':' . $i['message_key'], array_values( array_filter( \CourseCaptain\Publish\CourseValidator::validate( $course, 'live' )['issues'], static fn( array $i ): bool => 'VAL-029' === $i['code'] ) ) );
		self::assertSame( array( 'blocker:cc.val.quiz.pool_empty' ), $codes( $this->course ) );

		$ids = array();
		for ( $i = 1; $i <= 6; $i++ ) {
			$card     = 6 === $i;
			$question = $repo->create(
				$admin,
				$card ? array(
					'question_type' => QuestionTypes::FLASHCARD,
					'question_text' => 'Card',
					'settings'      => array( 'back' => 'Back' ),
				) : array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => 'Pool ' . $i,
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
				)
			);
			$repo->publish( $admin, $question );
			$db->insert(
				'ccap_module_question',
				array(
					'module_id'   => $this->module,
					'question_id' => $question,
					'sort_order'  => $i,
				)
			);
			if ( ! $card ) {
				$ids[] = $question;
			}
		}
		$pool = \CourseCaptain\Question\QuizPool::current();
		self::assertSame( $ids, $pool->eligible( $lesson ), 'flashcards are never drawn' );
		self::assertSame( array(), $codes( $this->course ), 'pool large enough' );
		ActivityService::current()->define(
			$admin,
			$lesson,
			'quiz',
			array(
				'source' => 'module_pool',
				'count'  => 9,
			)
		);
		self::assertSame( array( 'notice:cc.val.quiz.pool_small' ), $codes( $this->course ), 'smaller pool: a note only' );
		ActivityService::current()->define(
			$admin,
			$lesson,
			'quiz',
			array(
				'source' => 'module_pool',
				'count'  => 3,
			)
		);

		// Reproducible from the seed; another seed gives another order.
		$picked = \CourseCaptain\Question\QuizPool::pick( $ids, '12345', 3, $lesson );
		self::assertCount( 3, $picked );
		self::assertSame( $picked, \CourseCaptain\Question\QuizPool::pick( $ids, '12345', 3, $lesson ) );
		self::assertSame( array(), array_diff( $picked, $ids ) );

		$learner   = Actor::user( $this->learner );
		$presenter = QuestionPresenter::current();
		self::assertSame( array(), $presenter->quiz_questions( $lesson, $this->learner ), 'no draw before the first delivery' );
		$draw = $pool->draw( $learner, $this->course, $lesson, false, new \CourseCaptain\Question\Randomizer( '12345' ) );
		self::assertSame( array( '12345', 1, $picked ), array( $draw['seed'], $draw['attempt'], $draw['ids'] ) );
		self::assertSame( $draw, $pool->draw( $learner, $this->course, $lesson ), 'same attempt keeps its draw' );
		self::assertSame( $picked, $presenter->quiz_questions( $lesson, $this->learner ) );

		$outside = array_values( array_diff( $ids, $picked ) )[0];
		try {
			$presenter->deliver_inline( $learner, $this->course, $lesson, $outside );
			self::fail( 'question outside the draw' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_not_found', $e->error_code );
		}
		foreach ( $picked as $question ) {
			$delivery = $presenter->deliver_inline( $learner, $this->course, $lesson, $question );
			self::assertStringNotContainsString( 'correct', (string) wp_json_encode( $delivery['question'] ) );
			\CourseCaptain\Learning\AnswerService::current()->answer( $learner, $delivery['token'], array( 'A' ), wp_generate_uuid4() );
		}
		$state = ActivityProgress::current()->state( $this->learner, $lesson );
		$data  = json_decode( (string) $state['data'], true );
		self::assertSame( array( 3, 3, 3 ), array( $data['answered'], $data['correct'], $data['total'] ) );
		self::assertSame( 'completed', $state['status'], 'attempted: every drawn question answered' );

		// New attempt: new seed, counters start again, completion stays (monotonic).
		$again = $pool->draw( $learner, $this->course, $lesson, true, new \CourseCaptain\Question\Randomizer( '999' ) );
		self::assertSame( array( '999', 2 ), array( $again['seed'], $again['attempt'] ) );
		self::assertSame( \CourseCaptain\Question\QuizPool::pick( $ids, '999', 3, $lesson ), $again['ids'] );
		$state = ActivityProgress::current()->state( $this->learner, $lesson );
		self::assertSame( 0, json_decode( (string) $state['data'], true )['answered'] );
		self::assertSame( 'completed', $state['status'] );

		// No access, no draw.
		try {
			$pool->draw( Actor::user( (int) self::factory()->user->create() ), $this->course, $lesson );
			self::fail( 'draw without access' );
		} catch ( CourseException $e ) {
			self::assertContains( $e->error_code, array( 'ccap_forbidden', 'ccap_content_locked', 'ccap_not_found' ) );
		}
	}

	/**
	 * Media: register attachment idempotently (protected default), attach to a lesson.
	 */
	public function test_media_library(): void {
		$attachment = (int) self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$library    = MediaLibrary::current();
		$admin      = Actor::user( $this->admin );
		$media      = $library->register_attachment( $admin, $attachment );
		self::assertSame( $media, $library->register_attachment( $admin, $attachment ), 'idempotent' );
		$lesson = $this->post( 'ccap_lesson', $this->module );
		$library->attach( $admin, $media, 'lesson', $lesson, 'image' );
		$list = $library->media_of( 'lesson', $lesson );
		self::assertCount( 1, $list );
		self::assertSame( 'protected', $list[0]['access'] );
		self::assertSame( 'image/jpeg', $list[0]['mime_type'] );

		try {
			$library->register_attachment( Actor::user( $this->learner ), $attachment );
			self::fail( 'learner registered media' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_forbidden', $e->error_code );
		}
		wp_delete_attachment( $attachment, true );
	}
}
