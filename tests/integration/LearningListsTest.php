<?php
/**
 * R1-11: flashcards, favorites/collections, error training (R0-05 PAR-05/06, E-21).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\ErrorTraining;
use CourseCaptain\Learning\Favorites;
use CourseCaptain\Learning\Flashcards;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-11
 */
final class LearningListsTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner with full grant.
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
	 * Modules M1, M2.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Pool questions of M1 (3) and M2 (1).
	 *
	 * @var array<int, list<int>>
	 */
	private array $pool = array();

	/**
	 * Course with two modules and pool questions.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course', 0 );
		PolicyService::flush();
		foreach ( array( 3, 1 ) as $count ) {
			$module          = $this->post( 'ccap_module', 0 );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $module );
			for ( $i = 0; $i < $count; $i++ ) {
				$id = $this->question( QuestionTypes::SINGLE );
				QuestionRepository::current()->add_to_pool( Actor::user( $this->admin ), $module, $id );
				$this->pool[ $module ][] = $id;
			}
		}
		$this->grant( $this->learner, 0 );
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
	 * Published question.
	 *
	 * @param string $type Type.
	 * @return int
	 */
	private function question( string $type ): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$data  = QuestionTypes::FLASHCARD === $type
			? array(
				'question_type' => $type,
				'question_text' => 'Front ' . wp_generate_password( 4, false ),
				'settings'      => array( 'back' => 'Back' ),
			)
			: array(
				'question_type' => $type,
				'question_text' => 'Q ' . wp_generate_password( 4, false ),
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
		return $id;
	}

	/**
	 * Active grant.
	 *
	 * @param int $user   User.
	 * @param int $module Module (0 = course).
	 */
	private function grant( int $user, int $module ): void {
		$db = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $this->course,
				'module_id'   => $module,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
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
	 * Flashcard lesson with cards.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param int                  $cards    Card count.
	 * @return array{0: int, 1: list<int>}
	 */
	private function deck( array $settings, int $cards ): array {
		$lesson = $this->post( 'ccap_lesson', $this->modules[0] );
		ActivityService::current()->define( Actor::user( $this->admin ), $lesson, 'flashcard', $settings, array( 'type' => 'all_known' ) );
		$ids = array();
		for ( $i = 1; $i <= $cards; $i++ ) {
			$id = $this->question( QuestionTypes::FLASHCARD );
			Database::current()->insert(
				'ccap_activity_question',
				array(
					'activity_id' => $lesson,
					'question_id' => $id,
					'sort_order'  => $i,
				)
			);
			$ids[] = $id;
		}
		return array( $lesson, $ids );
	}

	/**
	 * Inline token.
	 *
	 * @param int $lesson Lesson.
	 * @param int $card   Card.
	 * @return string
	 */
	private function token( int $lesson, int $card ): string {
		return QuestionPresenter::current()->deliver_inline( Actor::user( $this->learner ), $this->course, $lesson, $card )['token'];
	}

	/**
	 * GM-062: mode A self-assessment; all known completes the activity.
	 *
	 * @group GM-062
	 */
	public function test_GM_062_self_assessment(): void {
		list( $lesson, $cards ) = $this->deck( array( 'mode' => 'self_assessment' ), 2 );
		$learner                = Actor::user( $this->learner );
		$fc                     = Flashcards::current();
		$this->assertRejected( fn() => $fc->set_state( $learner, $this->token( $lesson, $cards[0] ), 'mastered', wp_generate_uuid4() ), 'ccap_invalid_param' );
		$this->assertRejected( fn() => $fc->assess( $learner, $this->token( $lesson, $cards[0] ), true, wp_generate_uuid4() ), 'ccap_invalid_param' );

		self::assertSame( 'practice', $fc->set_state( $learner, $this->token( $lesson, $cards[0] ), 'practice', wp_generate_uuid4() )['state'] );
		self::assertSame( 'known', $fc->set_state( $learner, $this->token( $lesson, $cards[0] ), 'known', wp_generate_uuid4() )['state'] );
		self::assertSame( 'started', ActivityProgress::current()->state( $this->learner, $lesson )['status'] );
		$fc->set_state( $learner, $this->token( $lesson, $cards[1] ), 'known', wp_generate_uuid4() );
		self::assertSame( 'completed', ActivityProgress::current()->state( $this->learner, $lesson )['status'] );
		self::assertSame(
			array(
				'total' => 2,
				'known' => 2,
			),
			$fc->counts( $this->learner, $lesson )
		);

		// A forged interaction cannot claim card counts.
		list( $other, $more ) = $this->deck( array( 'mode' => 'self_assessment' ), 1 );
		unset( $more );
		self::assertNotSame( 'completed', ActivityProgress::current()->interact( $learner, $this->course, $other, 'cards', array( 'total' => 0 ) )['status'] );
	}

	/**
	 * GM-063: mode B streak; double answers and stale streaks ignored; known stays known.
	 *
	 * @group GM-063
	 */
	public function test_GM_063_streak(): void {
		list( $lesson, $cards ) = $this->deck(
			array(
				'mode'         => 'streak',
				'known_streak' => 3,
			),
			1
		);
		$learner                = Actor::user( $this->learner );
		$fc                     = Flashcards::current();
		$token                  = $this->token( $lesson, $cards[0] );

		self::assertSame( 1, $fc->assess( $learner, $token, true, wp_generate_uuid4() )['streak'] );
		self::assertSame( 0, $fc->assess( $learner, $token, false, wp_generate_uuid4() )['streak'], 'unknown resets' );
		$key = wp_generate_uuid4();
		self::assertTrue( $fc->assess( $learner, $token, true, $key )['applied'] );
		$double = $fc->assess( $learner, $token, true, $key );
		self::assertFalse( $double['applied'] );
		self::assertSame( 1, $double['streak'] );
		self::assertFalse( $fc->assess( $learner, $token, true, wp_generate_uuid4(), 0 )['applied'], 'stale streak' );
		self::assertSame( 'practice', $fc->assess( $learner, $token, true, wp_generate_uuid4(), 1 )['state'] );
		self::assertSame( 'known', $fc->assess( $learner, $token, true, wp_generate_uuid4() )['state'], 'learned after 3 in a row' );
		self::assertSame( 'known', $fc->assess( $learner, $token, false, wp_generate_uuid4() )['state'], 'stays learned' );
		self::assertSame( 'completed', ActivityProgress::current()->state( $this->learner, $lesson )['status'] );
	}

	/**
	 * GM-055…GM-060: favorites, collections, scope, cascades, sorting.
	 *
	 * @group GM-055
	 * @group GM-056
	 * @group GM-057
	 * @group GM-058
	 * @group GM-059
	 * @group GM-060
	 */
	public function test_GM_055_060_favorites_and_collections(): void {
		$learner              = Actor::user( $this->learner );
		$fav                  = Favorites::current();
		list( $q1, $q2, $q3 ) = $this->pool[ $this->modules[0] ];
		foreach ( array( $q1, $q2 ) as $q ) {
			$fav->add( $learner, $this->course, $q );
		}
		$fav->add( $learner, $this->course, $q1 );
		self::assertSame( array( $q2, $q1 ), $fav->question_ids( $learner, $this->course ) );

		$col = $fav->create_collection( $learner, $this->course, '  Wiederholung  ' );
		$db  = Database::current();
		self::assertSame( 'Wiederholung', $db->scalar( $db->prepare( 'SELECT name FROM `' . $db->table( 'ccap_collection' ) . '` WHERE id = %d', $col ) ) );
		$this->assertRejected( fn() => $fav->create_collection( $learner, $this->course, 'wiederholung' ), 'ccap_duplicate' );
		$this->assertRejected( fn() => $fav->create_collection( $learner, $this->course, '   ' ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $fav->create_collection( $learner, $this->course, str_repeat( 'x', 61 ) ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $fav->assign( $learner, $col, $q3 ), 'ccap_not_favorite' );

		$fav->assign( $learner, $col, $q1 );
		$list = array_column( $fav->list( $learner, $this->course ), 'collections', 'question_id' );
		self::assertSame( array( $col ), $list[ $q1 ] );
		self::assertSame( array(), $list[ $q2 ] );
		self::assertSame( array( $q1 ), $fav->question_ids( $learner, $this->course, $col ) );

		// GM-058: foreign collection 404, question outside the course 404, locked module rejected.
		$other = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertRejected( fn() => $fav->list( $other, $this->course, 'saved_desc', $col ), 'ccap_not_found' );
		$this->assertRejected( fn() => $fav->assign( $other, $col, $q1 ), 'ccap_not_found' );
		$this->assertRejected( fn() => $fav->add( $learner, $this->course, $this->question( QuestionTypes::SINGLE ) ), 'ccap_not_found' );
		$partial = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->grant( $partial, $this->modules[0] );
		$fav->add( Actor::user( $partial ), $this->course, $q1 );
		try {
			$fav->add( Actor::user( $partial ), $this->course, $this->pool[ $this->modules[1] ][0] );
			self::fail( 'locked module' );
		} catch ( CourseException $e ) {
			self::assertSame( 403, $e->status );
		}

		// GM-060: sorting deterministic, zero dates last, invalid parameters 422.
		$fav->add( $learner, $this->course, $q3 );
		$table = $db->table( 'ccap_favorite' );
		$db->execute( $db->prepare( "UPDATE `{$table}` SET created_at = %s WHERE user_id = %d AND question_id IN (%d, %d)", '2026-11-10 10:00:00', $this->learner, $q2, $q3 ) );
		$db->execute( $db->prepare( "UPDATE `{$table}` SET created_at = %s WHERE user_id = %d AND question_id = %d", '0000-00-00 00:00:00', $this->learner, $q1 ) );
		self::assertSame( array( $q3, $q2, $q1 ), array_column( $fav->list( $learner, $this->course ), 'question_id' ) );
		self::assertSame( array( $q2, $q3, $q1 ), array_column( $fav->list( $learner, $this->course, 'saved_asc' ), 'question_id' ) );
		$this->assertRejected( fn() => $fav->list( $learner, $this->course, 'assigned_desc' ), 'ccap_invalid_param' );
		$this->assertRejected( fn() => $fav->list( $learner, $this->course, 'random' ), 'ccap_invalid_param' );
		self::assertSame( array( $q1 ), array_column( $fav->list( $learner, $this->course, 'assigned_asc', $col ), 'question_id' ) );

		// GM-059: unfavorite removes from collections; deleting a collection keeps favorites.
		$fav->remove( $learner, $q1 );
		self::assertSame( array(), $fav->question_ids( $learner, $this->course, $col ) );
		$fav->assign( $learner, $col, $q2 );
		$fav->delete_collection( $learner, $col );
		self::assertSame( '0', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_collection_item' ) . '`' ) );
		self::assertContains( $q2, $fav->question_ids( $learner, $this->course ) );

		// Limit 30 per course.
		for ( $i = 1; $i <= Favorites::MAX_COLLECTIONS; $i++ ) {
			$fav->create_collection( $learner, $this->course, 'C' . $i );
		}
		$this->assertRejected( fn() => $fav->create_collection( $learner, $this->course, 'one more' ), 'ccap_limit_reached' );
	}

	/**
	 * GM-070 / GM-071: wrong answers set; wrong questions of an own evaluated exam.
	 *
	 * @group GM-070
	 * @group GM-071
	 */
	public function test_GM_070_071_error_training(): void {
		$learner         = Actor::user( $this->learner );
		list( $q1, $q2 ) = $this->pool[ $this->modules[0] ];
		$answer          = function ( int $question, string $key ) use ( $learner ): void {
			$token = QuestionPresenter::current()->deliver( $learner, $this->course, $this->modules[0], $question, new Randomizer( '1' ) )['token'];
			AnswerService::current()->answer( $learner, $token, array( $key ), wp_generate_uuid4() );
		};
		$answer( $q1, 'B' );
		$answer( $q2, 'A' );
		$training = ErrorTraining::current();
		self::assertSame( array( $q1 ), $training->wrong( $learner, $this->course ) );
		$answer( $q1, 'A' );
		self::assertSame( array(), $training->wrong( $learner, $this->course ) );

		$rule      = ExamRules::current()->create(
			Actor::user( $this->admin ),
			$this->course,
			'v1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			)
		);
		$db        = Database::current();
		$session   = function ( string $status, int $user ) use ( $db, $rule, $q1, $q2 ): int {
			$snapshot = '{}';
			$id       = $db->insert(
				'ccap_exam_session',
				array(
					'uid'           => $db->uuid(),
					'user_id'       => $user,
					'course_id'     => $this->course,
					'rule_id'       => $rule,
					'mode'          => 'random',
					'form_key'      => 'rule:' . $rule . ':' . wp_generate_password( 4, false ),
					'status'        => $status,
					'start_token'   => $db->uuid(),
					'snapshot'      => $snapshot,
					'snapshot_hash' => 'sha256:' . hash( 'sha256', $snapshot ),
					'started_at'    => $db->now(),
					'result'        => (string) wp_json_encode(
						array(
							'questions' => array(
								array(
									'position' => 1,
									'correct'  => false,
								),
								array(
									'position' => 2,
									'correct'  => true,
								),
							),
						)
					),
				)
			);
			foreach ( array(
				1 => $q1,
				2 => $q2,
			) as $position => $question ) {
				$db->insert(
					'ccap_exam_session_question',
					array(
						'session_id'  => $id,
						'position'    => $position,
						'question_id' => $question,
						'revision_id' => (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) ),
					)
				);
			}
			return $id;
		};
		$evaluated = $session( 'evaluated', $this->learner );
		self::assertSame( array( $q1 ), $training->from_exam( $learner, $evaluated ) );
		$foreign = $session( 'evaluated', (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertRejected( fn() => $training->from_exam( $learner, $foreign ), 'ccap_not_found' );

		// UI-F3: own REST entries "Practise exam mistakes" and "Due" (R0-19 R-07 `mode`).
		wp_set_current_user( $this->learner );
		$next   = function ( array $params ): \WP_REST_Response {
			$request = new \WP_REST_Request( 'GET', '/course-captain/v1/courses/' . $this->course . '/next-question' );
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
			return rest_do_request( $request );
		};
		$latest = $next( array( 'mode' => 'exam_mistakes' ) );
		self::assertSame( 200, $latest->get_status() );
		self::assertSame( array( $q1, 'exam_mistakes', 1, $evaluated ), array( $latest->get_data()['question_id'], $latest->get_data()['mode'], $latest->get_data()['remaining'], $latest->get_data()['exam_session'] ), 'latest evaluated attempt' );
		self::assertIsString( $latest->get_data()['token'] );
		self::assertStringNotContainsString( '"correct"', (string) wp_json_encode( $latest->get_data()['question'] ), 'no solutions' );
		self::assertSame(
			$q1,
			$next(
				array(
					'mode'         => 'exam_mistakes',
					'exam_session' => (string) $evaluated,
				)
			)->get_data()['question_id']
		);
		self::assertSame(
			array( 404, 'ccap_not_found' ),
			array(
				$next(
					array(
						'mode'         => 'exam_mistakes',
						'exam_session' => (string) $foreign,
					)
				)->get_status(),
				$next(
					array(
						'mode'         => 'exam_mistakes',
						'exam_session' => (string) $foreign,
					)
				)->get_data()['code'],
			),
			'foreign attempt'
		);
		self::assertSame(
			422,
			$next(
				array(
					'mode'         => 'exam_mistakes',
					'exam_session' => 'x1',
				)
			)->get_status()
		);
		self::assertSame( 422, $next( array( 'mode' => 'nonsense' ) )->get_status() );

		$due = $next( array( 'mode' => 'due' ) )->get_data();
		self::assertSame( array( null, 'due', 0 ), array( $due['question'], $due['mode'], $due['remaining'] ), 'nothing due yet' );
		$progress = $db->table( 'ccap_question_progress' );
		$db->execute( $db->prepare( "UPDATE `{$progress}` SET learn_status = 'secure', due_at = %s WHERE user_id = %d AND question_id = %d", '2020-01-01 00:00:00', $this->learner, $q2 ) );
		$db->execute( $db->prepare( "UPDATE `{$progress}` SET learn_status = 'mastered', due_at = %s WHERE user_id = %d AND question_id = %d", '2099-01-01 00:00:00', $this->learner, $q1 ) );
		$due = $next( array( 'mode' => 'due' ) )->get_data();
		self::assertSame( array( $q2, 1, '2099-01-01 00:00:00' ), array( $due['question_id'], $due['remaining'], $due['next_due'] ) );
		self::assertSame( 'no-store', substr( (string) $next( array( 'mode' => 'due' ) )->get_headers()['Cache-Control'], -8 ) );
		wp_set_current_user( 0 );
		self::assertSame( 401, $next( array( 'mode' => 'due' ) )->get_status() );

		$running = $session( 'in_progress', $this->learner );
		$this->assertRejected( fn() => $training->from_exam( $learner, $running ), 'ccap_exam_running' );
	}
}
