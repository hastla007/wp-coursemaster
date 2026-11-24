<?php
/**
 * Rights lock (R0-10 §2.2) in the learner's own lists: favourites,
 * collections and mistake training (learning mistakes and exam mistakes)
 * neither list nor count foreign questions that are withheld; the marks
 * and answers stay and return with the release.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Import\Rights;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\ErrorTraining;
use CourseCaptain\Learning\Favorites;
use CourseCaptain\Learning\SmartTrainer;
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
 * @group SET-CC-rechte-auslieferung
 */
final class RightsLearningListsTest extends SchemaTestCase {

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
	 * Module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Questions: Q0 own, Q1 foreign (licensed until it is withheld in the test), Q2 own.
	 *
	 * @var list<int>
	 */
	private array $q = array();

	/**
	 * Course with one module and three questions; Q1 comes from a foreign package.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$admin         = Actor::user( $this->admin );
		$this->course  = $this->post( 'ccap_course', 'Rights lists course' );
		$this->module  = $this->post( 'ccap_module', 'Rights lists module' );
		PolicyService::flush();
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module );
		for ( $i = 0; $i < 3; $i++ ) {
			$this->q[] = $this->question();
		}
		CoursePublisher::current()->publish( $admin, $this->course );
		$this->grant();
		$this->foreign( $this->q[1], 'licensed' );
	}

	/**
	 * Reset the current user.
	 */
	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Published post.
	 *
	 * @param string $type  Type.
	 * @param string $title Title.
	 * @return int
	 */
	private function post( string $type, string $title ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => 'About',
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Published single-choice question in the module pool (A right, B wrong).
	 *
	 * @return int
	 */
	private function question(): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$id    = $repo->create(
			$admin,
			array(
				'question_type' => QuestionTypes::SINGLE,
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
			)
		);
		$repo->publish( $admin, $id );
		$repo->add_to_pool( $admin, $this->module, $id );
		return $id;
	}

	/**
	 * Active manual grant of the learner.
	 *
	 * @return void
	 */
	private function grant(): void {
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
		PolicyService::flush();
	}

	/**
	 * Mark a question as imported from a foreign package with a rights status.
	 *
	 * @param int    $id     Question.
	 * @param string $status Rights status of the current revision.
	 * @return void
	 */
	private function foreign( int $id, string $status ): void {
		$db = Database::current();
		$db->insert(
			'ccap_source_map',
			array(
				'uid'           => $db->uuid(),
				'object_type'   => 'question',
				'object_id'     => $id,
				'source_system' => self::FOREIGN,
				'source_id'     => 'question-' . $id,
			),
			array( 'created_at', 'updated_at' )
		);
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_question_revision' ) . '` r JOIN `' . $db->table( 'ccap_question' ) . '` q ON q.current_revision_id = r.id SET r.rights_status = %s WHERE q.id = %d', $status, $id ) );
	}

	/**
	 * Answer a question in the learning mode.
	 *
	 * @param int    $question Question.
	 * @param string $key      Answer key.
	 * @return void
	 */
	private function answer( int $question, string $key ): void {
		$learner = Actor::user( $this->learner );
		$token   = QuestionPresenter::current()->deliver( $learner, $this->course, $this->module, $question, new Randomizer( '1' ) )['token'];
		AnswerService::current()->answer( $learner, $token, array( $key ), wp_generate_uuid4() );
	}

	/**
	 * Evaluated exam attempt of the learner with all three questions answered wrongly.
	 *
	 * @return int Session.
	 */
	private function exam_with_mistakes(): int {
		$db       = Database::current();
		$rule     = ExamRules::current()->create(
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
		$snapshot = '{}';
		$result   = array();
		foreach ( array_keys( $this->q ) as $index ) {
			$result[] = array(
				'position' => $index + 1,
				'correct'  => false,
			);
		}
		$id = $db->insert(
			'ccap_exam_session',
			array(
				'uid'           => $db->uuid(),
				'user_id'       => $this->learner,
				'course_id'     => $this->course,
				'rule_id'       => $rule,
				'mode'          => 'random',
				'form_key'      => 'rule:' . $rule . ':' . wp_generate_password( 4, false ),
				'status'        => 'evaluated',
				'start_token'   => $db->uuid(),
				'snapshot'      => $snapshot,
				'snapshot_hash' => 'sha256:' . hash( 'sha256', $snapshot ),
				'started_at'    => $db->now(),
				'result'        => (string) wp_json_encode( array( 'questions' => $result ) ),
			)
		);
		foreach ( $this->q as $index => $question ) {
			$db->insert(
				'ccap_exam_session_question',
				array(
					'session_id'  => $id,
					'position'    => $index + 1,
					'question_id' => $question,
					'revision_id' => (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) ),
				)
			);
		}
		return $id;
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 * @return void
	 */
	private function rejected( callable $call, string $code ): void {
		try {
			$call();
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code, $e->getMessage() );
			return;
		}
		self::fail( 'expected ' . $code );
	}

	/**
	 * Favourites and collections: a withheld question is neither listed nor
	 * counted nor drawn in the mode `favorites`; marking it or putting it
	 * into a collection answers 404 like the delivery; the mark stays and
	 * the question is back after the release.
	 */
	public function test_favorites_and_collections_hide_withheld_questions(): void {
		$learner = Actor::user( $this->learner );
		$fav     = Favorites::current();
		foreach ( $this->q as $question ) {
			$fav->add( $learner, $this->course, $question );
		}
		$col = $fav->create_collection( $learner, $this->course, 'Review' );
		$fav->assign( $learner, $col, $this->q[1] );
		$fav->assign( $learner, $col, $this->q[2] );
		self::assertEqualsCanonicalizing( $this->q, $fav->question_ids( $learner, $this->course ), 'before the lock all three' );

		Rights::current()->override( Actor::user( $this->admin ), 'question', $this->q[1], 'unknown', 'Contract unclear' );

		self::assertEqualsCanonicalizing( array( $this->q[0], $this->q[2] ), array_column( $fav->list( $learner, $this->course ), 'question_id' ) );
		self::assertEqualsCanonicalizing( array( $this->q[0], $this->q[2] ), $fav->question_ids( $learner, $this->course ) );
		self::assertSame( array( $this->q[2] ), array_column( $fav->list( $learner, $this->course, 'assigned_desc', $col ), 'question_id' ), 'collection without the withheld question' );
		self::assertSame( array( $this->q[2] ), $fav->question_ids( $learner, $this->course, $col ) );
		self::assertFalse( $fav->accessible( $learner, $this->course, $this->q[1] ) );
		self::assertTrue( $fav->accessible( $learner, $this->course, $this->q[0] ) );
		self::assertSame( array( $this->q[2], $this->q[0] ), $fav->accessible_all( $learner, $this->course, array( $this->q[2], $this->q[1], $this->q[0], $this->q[2] ) ), 'batch: order kept, duplicates and withheld removed' );

		$this->rejected( fn() => $fav->add( $learner, $this->course, $this->q[1] ), 'ccap_not_found' );
		$other = $fav->create_collection( $learner, $this->course, 'Other' );
		$this->rejected( fn() => $fav->assign( $learner, $other, $this->q[1] ), 'ccap_not_found' );

		$trainer = SmartTrainer::current();
		for ( $i = 0; $i < 4; $i++ ) {
			$next = $trainer->next( $learner, $this->course, 'favorites' );
			self::assertNotNull( $next );
			self::assertNotSame( $this->q[1], (int) $next['question_id'] );
		}
		self::assertSame( $this->q[2], (int) $trainer->next( $learner, $this->course, 'favorites', array( 'collection' => $col ) )['question_id'] );

		// The mark is kept (no data loss); the release brings the question back into list and collection.
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_favorite' ) . "` WHERE user_id = %d AND question_id = %d AND context = 'question'", $this->learner, $this->q[1] ) ) );
		Rights::current()->override( Actor::user( $this->admin ), 'question', $this->q[1], 'licensed', 'New contract' );
		self::assertEqualsCanonicalizing( $this->q, $fav->question_ids( $learner, $this->course ) );
		self::assertEqualsCanonicalizing( array( $this->q[1], $this->q[2] ), $fav->question_ids( $learner, $this->course, $col ) );
	}

	/**
	 * Mistake training: learning mistakes and exam mistakes leave out a
	 * withheld question, also in the count `remaining` of the entry
	 * "Practise exam mistakes"; the release brings it back.
	 */
	public function test_mistake_training_hides_withheld_questions(): void {
		$learner = Actor::user( $this->learner );
		foreach ( $this->q as $question ) {
			$this->answer( $question, 'B' );
		}
		$session  = $this->exam_with_mistakes();
		$training = ErrorTraining::current();
		self::assertSame( $this->q, $training->wrong( $learner, $this->course ) );
		self::assertSame( $this->q, $training->from_exam( $learner, $session ) );

		Rights::current()->override( Actor::user( $this->admin ), 'question', $this->q[1], 'restricted', 'Must not be shown' );

		$training = ErrorTraining::current();
		self::assertSame( array( $this->q[0], $this->q[2] ), $training->wrong( $learner, $this->course ) );
		self::assertSame( array( $this->q[0], $this->q[2] ), $training->from_exam( $learner, $session ) );

		$trainer = SmartTrainer::current();
		$entry   = $trainer->entry( $learner, $this->course, 'exam_mistakes', array( 'exam_session' => $session ) );
		self::assertSame( 2, $entry['remaining'], 'the withheld question is not counted' );
		self::assertContains( (int) $entry['question']['question_id'], array( $this->q[0], $this->q[2] ) );
		for ( $i = 0; $i < 4; $i++ ) {
			$next = $trainer->next( $learner, $this->course, 'wrong' );
			self::assertNotNull( $next );
			self::assertNotSame( $this->q[1], (int) $next['question_id'] );
		}

		Rights::current()->override( Actor::user( $this->admin ), 'question', $this->q[1], 'licensed', 'New contract' );
		self::assertContains( $this->q[1], ErrorTraining::current()->wrong( $learner, $this->course ) );
		self::assertSame( 3, SmartTrainer::current()->entry( $learner, $this->course, 'exam_mistakes', array( 'exam_session' => $session ) )['remaining'] );
	}
}
