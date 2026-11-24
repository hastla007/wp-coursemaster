<?php
/**
 * R1-06: question bank, revisions, pools and delivery tokens.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;

/**
 * Real tables; R0-06 §5, R0-08 §3, R0-05 GM-017/023/024/025.
 *
 * @group R1-06
 */
final class QuestionBankTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner with a grant on the course.
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
	 * Module of the course.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Grant ID of the learner.
	 *
	 * @var int
	 */
	private int $grant;

	/**
	 * Activate, build course with module and a learner grant.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'C-A',
				'post_author' => $this->admin,
			)
		);
		$this->module  = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_module',
				'post_status' => 'publish',
				'post_title'  => 'M-A1',
				'post_author' => $this->admin,
			)
		);
		PolicyService::flush();
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $this->module );
		$db          = Database::current();
		$this->grant = $db->insert(
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
	 * Single-choice data.
	 *
	 * @param string $text Text.
	 * @return array<string, mixed>
	 */
	private static function single( string $text = 'Q-A1-001' ): array {
		return array(
			'question_type'    => QuestionTypes::SINGLE,
			'question_text'    => $text,
			'feedback_correct' => 'Explanation',
			'answers'          => array(
				array(
					'key'     => 'A',
					'text'    => 'right',
					'correct' => true,
				),
				array(
					'key'  => 'B',
					'text' => 'wrong 1',
				),
				array(
					'key'  => 'C',
					'text' => 'wrong 2',
				),
			),
			'tags'             => array(
				array(
					'type'  => 'topic',
					'key'   => 't-a1-a',
					'label' => 'Topic a',
				),
			),
		);
	}

	/**
	 * Create, publish and pool a question.
	 *
	 * @param array<string, mixed> $data Data.
	 * @return int
	 */
	private function published( array $data ): int {
		$repo     = QuestionRepository::current();
		$admin    = Actor::user( $this->admin );
		$question = $repo->create( $admin, $data );
		$repo->publish( $admin, $question );
		$repo->add_to_pool( $admin, $this->module, $question );
		return $question;
	}

	/**
	 * R0-06 §5: draft edited in place; published revision immutable → new
	 * revision; publishing deprecates the old one; known hash reactivates.
	 */
	public function test_revisions(): void {
		$repo     = QuestionRepository::current();
		$admin    = Actor::user( $this->admin );
		$question = $repo->create( $admin, self::single() );
		$draft    = $repo->save( $admin, $question, self::single( 'Q edited' ) );
		self::assertSame( 1, $repo->revision( $draft )['revision_no'], 'draft updated in place' );
		self::assertSame( 'Q edited', $repo->revision( $draft )['question_text'] );

		$first = $repo->publish( $admin, $question );
		self::assertSame( $draft, $first );
		$hash_before = $repo->revision( $first )['content_hash'];

		$second = $repo->save( $admin, $question, self::single( 'Q v2' ), 'substantive' );
		self::assertNotSame( $first, $second );
		self::assertSame( 2, $repo->revision( $second )['revision_no'] );
		self::assertSame( 'Q edited', $repo->revision( $first )['question_text'], 'published revision unchanged' );
		self::assertSame( $hash_before, $repo->revision( $first )['content_hash'] );

		$repo->publish( $admin, $question );
		self::assertSame( 'deprecated', $repo->revision( $first )['editorial_status'] );
		self::assertSame( 'published', $repo->revision( $second )['editorial_status'] );

		self::assertSame( $first, $repo->save( $admin, $question, self::single( 'Q edited' ) ), 'known hash → existing revision' );
		self::assertSame( 'topic', $repo->revision( $second )['tags'][0]['type'] );
	}

	/**
	 * Publishing validates the type rules (R0-08 §3.1).
	 */
	public function test_publish_validation(): void {
		$repo                          = QuestionRepository::current();
		$admin                         = Actor::user( $this->admin );
		$data                          = self::single();
		$data['answers'][1]['correct'] = true;
		$question                      = $repo->create( $admin, $data );
		try {
			$repo->publish( $admin, $question );
			self::fail( 'two correct answers on single choice' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
			self::assertStringContainsString( 'exactly_one_correct', $e->getMessage() );
		}
	}

	/**
	 * GM-023 / R0-08 §1.3: pool order NULL last, then ID; drafts and archived never delivered.
	 *
	 * @group GM-023
	 */
	public function test_GM_023_pool(): void {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$q1    = $this->published( self::single( 'one' ) );
		$q2    = $this->published( self::single( 'two' ) );
		$q3    = $this->published( self::single( 'three' ) );
		$repo->add_to_pool( $admin, $this->module, $q3, 5 );
		$draft = $repo->create( $admin, self::single( 'draft' ) );
		$repo->add_to_pool( $admin, $this->module, $draft );
		self::assertSame( array( $q3, $q1, $q2 ), $repo->pool( $this->module ) );

		$repo->archive( $admin, $q1 );
		self::assertSame( array( $q3, $q2 ), $repo->pool( $this->module ) );
	}

	/**
	 * GM-017 / GM-024 / GM-025: no solution in the payload; tampered or
	 * foreign token rejected; TTL 2 h; access end and archived question block.
	 *
	 * @group GM-017
	 * @group GM-024
	 * @group GM-025
	 * @group SEC-017
	 */
	public function test_GM_017_024_025_delivery_and_token(): void {
		$question  = $this->published( self::single() );
		$presenter = QuestionPresenter::current();
		$learner   = Actor::user( $this->learner );

		$delivery = $presenter->deliver( $learner, $this->course, $this->module, $question, new Randomizer( '20300115' ) );
		$json     = (string) wp_json_encode( $delivery['question'] );
		self::assertStringNotContainsString( 'correct', $json );
		self::assertStringNotContainsString( 'Explanation', $json, 'E-20: no explanation in Free' );
		self::assertFalse( QuestionPresenter::explanations_available() );

		$result = $presenter->evaluate( $learner, $delivery['token'], array( 'A' ) );
		self::assertTrue( $result['evaluation']->correct() );

		$other = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		foreach ( array( $delivery['token'] . 'x', 'garbage' ) as $bad ) {
			$this->assertRejected( fn() => $presenter->verify( $learner, $bad ), 'ccap_invalid_token' );
		}
		$this->assertRejected( fn() => $presenter->verify( Actor::user( $other ), $delivery['token'] ), 'ccap_invalid_token' );

		$parts  = explode( '.', $delivery['token'] );
		$claims = json_decode( (string) base64_decode( strtr( $parts[0], '-_', '+/' ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		self::assertEqualsWithDelta( QuestionPresenter::TOKEN_TTL, $claims['exp'] - time(), 2, 'TTL 7200 s (clock may tick under load)' );

		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . "` SET status = 'revoked' WHERE id = %d", $this->grant ) );
		do_action( 'course_captain_policy_changed' );
		$this->assertRejected( fn() => $presenter->verify( $learner, $delivery['token'] ), 'ccap_content_locked' );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . "` SET status = 'active' WHERE id = %d", $this->grant ) );
		do_action( 'course_captain_policy_changed' );

		QuestionRepository::current()->archive( Actor::user( $this->admin ), $question );
		$this->assertRejected( fn() => $presenter->verify( $learner, $delivery['token'] ), 'ccap_not_found' );
	}

	/**
	 * Open text reveal (GM-020 part) and learner without access.
	 *
	 * @group GM-020
	 */
	public function test_GM_020_reveal_and_no_access(): void {
		$question  = $this->published(
			array(
				'question_type' => QuestionTypes::OPEN_TEXT,
				'question_text' => 'Q-B1-010',
				'answers'       => array(
					array(
						'key'     => 'MODEL',
						'text'    => 'Model answer',
						'correct' => true,
					),
				),
			)
		);
		$presenter = QuestionPresenter::current();
		$delivery  = $presenter->deliver( Actor::user( $this->learner ), $this->course, $this->module, $question );
		self::assertSame( 'Model answer', $presenter->reveal( Actor::user( $this->learner ), $delivery['token'] ) );
		self::assertTrue( $presenter->evaluate( Actor::user( $this->learner ), $delivery['token'], array( 'known' => true ) )['evaluation']->correct() );

		$stranger = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertRejected( fn() => $presenter->deliver( Actor::user( $stranger ), $this->course, $this->module, $question ), 'ccap_content_locked' );
	}

	/**
	 * SEC-016: questions of a running exam are locked in learning mode.
	 *
	 * @group SEC-016
	 */
	public function test_SEC_016_running_exam_locks_questions(): void {
		$question = $this->published( self::single() );
		$db       = Database::current();
		$rule     = $db->insert(
			'ccap_exam_rule',
			array(
				'uid'          => $db->uuid(),
				'course_id'    => $this->course,
				'version'      => '1',
				'status'       => 'published',
				'passing_rule' => '{}',
			),
			array( 'created_at', 'updated_at' )
		);
		$session  = $db->insert(
			'ccap_exam_session',
			array(
				'uid'           => $db->uuid(),
				'user_id'       => $this->learner,
				'course_id'     => $this->course,
				'rule_id'       => $rule,
				'mode'          => 'random',
				'form_key'      => 'random:' . $rule,
				'status'        => 'in_progress',
				'start_token'   => $db->uuid(),
				'snapshot'      => '{}',
				'snapshot_hash' => 'sha256:' . str_repeat( '0', 64 ),
				'started_at'    => $db->now(),
			)
		);
		$revision = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) );
		$db->insert(
			'ccap_exam_session_question',
			array(
				'session_id'  => $session,
				'position'    => 1,
				'question_id' => $question,
				'revision_id' => $revision,
			)
		);
		$this->assertRejected( fn() => QuestionPresenter::current()->deliver( Actor::user( $this->learner ), $this->course, $this->module, $question ), 'ccap_content_locked' );

		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . "` SET status = 'submitted' WHERE id = %d", $session ) );
		self::assertArrayHasKey( 'token', QuestionPresenter::current()->deliver( Actor::user( $this->learner ), $this->course, $this->module, $question ) );
	}

	/**
	 * Authors only edit questions of their own courses (M-07).
	 */
	public function test_author_scope(): void {
		$question = $this->published( self::single() );
		$author   = (int) self::factory()->user->create( array( 'role' => Roles::AUTHOR ) );
		$this->assertRejected( fn() => QuestionRepository::current()->save( Actor::user( $author ), $question, self::single( 'hijack' ) ), 'ccap_not_found' );
		$own = QuestionRepository::current()->create( Actor::user( $author ), self::single( 'own' ) );
		self::assertGreaterThan( 0, QuestionRepository::current()->save( Actor::user( $author ), $own, self::single( 'own 2' ) ) );
	}

	/**
	 * Expect a CourseException with a code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Expected code.
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
