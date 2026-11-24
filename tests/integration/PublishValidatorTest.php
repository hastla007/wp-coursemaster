<?php
/**
 * R1-26: course publish validator, acknowledgement, all publish paths (R0-22).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Admin\AuthoringService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Publish\CourseValidator;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-26
 */
final class PublishValidatorTest extends SchemaTestCase {

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
	 * Module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Valid course with one module and one text lesson.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->course = $this->post( 'ccap_course', 'draft', 'Course', 0, 'About the course' );
		$this->module = $this->post( 'ccap_module', 'publish', 'Module', 0, '' );
		PolicyService::flush();
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $this->module );
		$lesson = $this->post( 'ccap_lesson', 'publish', 'Lesson', $this->module, 'Text' );
		ActivityService::current()->define( Actor::user( $this->admin ), $lesson, 'lesson', array() );
	}

	/**
	 * Post.
	 *
	 * @param string $type      Type.
	 * @param string $status    Status.
	 * @param string $title     Title.
	 * @param int    $parent_id Parent.
	 * @param string $content   Content.
	 * @return int
	 */
	private function post( string $type, string $status, string $title, int $parent_id, string $content ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => $status,
				'post_title'   => $title,
				'post_parent'  => $parent_id,
				'post_content' => $content,
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Codes of a report by severity.
	 *
	 * @param array<string, mixed> $report   Report.
	 * @param string               $severity Severity.
	 * @return list<string>
	 */
	private static function codes( array $report, string $severity ): array {
		return array_values( array_unique( array_column( array_filter( $report['issues'], static fn( array $i ): bool => $severity === $i['severity'] ), 'code' ) ) );
	}

	/**
	 * Valid course: no blockers, report format.
	 */
	public function test_valid_course_and_report_format(): void {
		$report = CourseValidator::validate( $this->course, 'manual' );
		self::assertSame( array(), self::codes( $report, 'blocker' ) );
		self::assertSame( array(), self::codes( $report, 'warning' ) );
		self::assertStringStartsWith( 'sha256:', $report['candidate_hash'] );
		self::assertSame( 'manual', $report['context'] );
		self::assertSame( CourseValidator::VERSION, $report['validator_version'] );
		self::assertTrue( CourseValidator::publishable( $report ) );
		AuthoringService::current()->publish( Actor::user( $this->admin ), $this->course, 'minor' );
		self::assertContains( 'VAL-008', self::codes( CourseValidator::validate( $this->course ), 'notice' ), 'unchanged candidate' );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'course_published'" ) );
	}

	/**
	 * Structure blockers VAL-001/003/020/023/026/027 block publishing without override.
	 *
	 * @group PUB-001
	 * @group PUB-002
	 * @group PUB-003
	 */
	public function test_structure_blockers(): void {
		$admin = Actor::user( $this->admin );
		$empty = $this->post( 'ccap_course', 'draft', ' ', 0, '' );
		self::assertSame( array( 'VAL-001', 'VAL-003' ), self::codes( CourseValidator::validate( $empty ), 'blocker' ) );

		$bare = $this->post( 'ccap_module', 'publish', 'Empty module', 0, '' );
		CourseStructure::current()->assign_module( $admin, $this->course, $bare );
		$untyped = $this->post( 'ccap_lesson', 'publish', 'No type', $this->module, 'x' );
		$blank   = $this->post( 'ccap_lesson', 'publish', 'Blank', $this->module, '' );
		ActivityService::current()->define( $admin, $blank, 'lesson', array() );
		$video = $this->post( 'ccap_lesson', 'publish', 'Video', $this->module, '' );
		ActivityService::current()->define( $admin, $video, 'video', array( 'duration_seconds' => 5 ) );
		$codes = self::codes( CourseValidator::validate( $this->course ), 'blocker' );
		foreach ( array( 'VAL-020', 'VAL-023', 'VAL-026', 'VAL-027' ) as $code ) {
			self::assertContains( $code, $codes );
		}
		try {
			AuthoringService::current()->publish( $admin, $this->course, 'minor' );
			self::fail( 'blockers must stop publishing' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
			self::assertSame( 422, $e->status );
		}
		self::assertNull( CoursePublisher::current()->current_revision( $this->course ), 'nothing published' );
		unset( $untyped );
	}

	/**
	 * Question rules VAL-042/043/047/048/052/058 for pool questions.
	 *
	 * @group PUB-006
	 */
	public function test_question_rules(): void {
		$admin = Actor::user( $this->admin );
		$repo  = QuestionRepository::current();
		$make  = function ( array $answers, string $type = 'single_choice', bool $publish = true ) use ( $repo, $admin ): int {
			$id = $repo->create(
				$admin,
				array(
					'question_type' => $type,
					'question_text' => 'Question ' . wp_generate_password( 4, false ),
					'answers'       => $answers,
				)
			);
			if ( $publish ) {
				$repo->publish( $admin, $id );
			}
			$repo->add_to_pool( $admin, $this->module, $id );
			return $id;
		};
		$make(
			array(
				array(
					'key'     => 'A',
					'text'    => 'Same',
					'correct' => true,
				),
				array(
					'key'  => 'B',
					'text' => 'same ',
				),
			)
		);
		$make(
			array(
				array(
					'key'     => 'A',
					'text'    => 'x',
					'correct' => true,
				),
				array(
					'key'     => 'B',
					'text'    => 'y',
					'correct' => true,
				),
				array(
					'key'     => 'C',
					'text'    => 'z',
					'correct' => true,
				),
			),
			'multi_choice'
		);
		$make(
			array(
				array(
					'key'  => 'A',
					'text' => 'x',
				),
				array(
					'key'  => 'B',
					'text' => 'y',
				),
			),
			'single_choice',
			false
		);
		$report = CourseValidator::validate( $this->course );
		self::assertContains( 'VAL-047', self::codes( $report, 'blocker' ), 'contradictory duplicate options' );
		self::assertContains( 'VAL-052', self::codes( $report, 'warning' ), 'all options correct' );
		self::assertContains( 'VAL-058', self::codes( $report, 'warning' ), 'unpublished question in pool' );
	}

	/**
	 * Exam rules VAL-074/079/085/086/087 and prerequisites VAL-120/121.
	 *
	 * @group PUB-014
	 * @group PUB-017
	 * @group PUB-020
	 * @group PUB-022
	 * @group PUB-034
	 */
	public function test_exam_and_prerequisite_rules(): void {
		$admin = Actor::user( $this->admin );
		$rules = ExamRules::current();
		$rule  = $rules->create(
			$admin,
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
		$rules->set_blueprint( $admin, $rule, $this->module, 5 );
		self::assertContains( 'VAL-085', self::codes( CourseValidator::validate( $this->course ), 'blocker' ), 'empty pool' );

		$repo = QuestionRepository::current();
		$q    = $repo->create(
			$admin,
			array(
				'question_type' => 'single_choice',
				'question_text' => 'Pool question',
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
		$repo->publish( $admin, $q );
		$repo->add_to_pool( $admin, $this->module, $q );
		$report = CourseValidator::validate( $this->course );
		self::assertContains( 'VAL-086', self::codes( $report, 'blocker' ), 'evidence exam with too small pool' );
		$issue = array_values( array_filter( $report['issues'], static fn( array $i ): bool => 'VAL-086' === $i['code'] ) )[0];
		self::assertSame(
			array(
				'available' => 1,
				'required'  => 5,
			),
			$issue['params']
		);

		$practice = $rules->create(
			$admin,
			$this->course,
			'p1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			600,
			array( 'purpose' => 'practice' )
		);
		$rules->set_blueprint( $admin, $practice, $this->module, 3 );
		self::assertContains( 'VAL-087', self::codes( CourseValidator::validate( $this->course ), 'warning' ) );

		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_rule' ) . '` SET passing_rule = %s, time_limit_seconds = 90000 WHERE id = %d', '{"min_ratio":{"num":3,"den":2}}', $rule ) );
		$codes = self::codes( CourseValidator::validate( $this->course ), 'blocker' );
		self::assertContains( 'VAL-074', $codes );
		self::assertContains( 'VAL-079', $codes );

		$second = $this->post( 'ccap_module', 'publish', 'Second', 0, '' );
		$this->post( 'ccap_lesson', 'publish', 'L2', $second, 'Text' );
		CourseStructure::current()->assign_module(
			$admin,
			$this->course,
			$second,
			null,
			true,
			(string) wp_json_encode(
				array(
					'prerequisites' => array(
						array(
							'type' => 'completed_module',
							'id'   => $this->module,
						),
					),
				)
			)
		);
		CourseStructure::current()->assign_module(
			$admin,
			$this->course,
			$this->module,
			null,
			true,
			(string) wp_json_encode(
				array(
					'prerequisites' => array(
						array(
							'type' => 'completed_module',
							'id'   => $second,
						),
						array(
							'type' => 'completed_activity',
							'id'   => 999999,
						),
					),
				)
			)
		);
		$codes = self::codes( CourseValidator::validate( $this->course ), 'blocker' );
		self::assertContains( 'VAL-120', $codes, 'cycle' );
		self::assertContains( 'VAL-121', $codes, 'missing activity' );
	}

	/**
	 * Warnings need an acknowledgement per fingerprint (§1.3); a changed problem needs a new one.
	 */
	public function test_acknowledgement(): void {
		$admin = Actor::user( $this->admin );
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module, null, false );
		$report = CourseValidator::validate( $this->course );
		self::assertSame( array( 'VAL-004' ), self::codes( $report, 'warning' ) );
		try {
			AuthoringService::current()->publish( $admin, $this->course, 'minor' );
			self::fail( 'unacknowledged warning' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_unacknowledged', $e->error_code );
		}
		$fingerprints = array_column( $report['issues'], 'fingerprint' );
		self::assertSame( 1, CourseValidator::validate( $this->course, 'publish', $fingerprints )['summary']['acknowledged'] );
		AuthoringService::current()->publish( $admin, $this->course, 'minor', null, $fingerprints );
		self::assertNotNull( CoursePublisher::current()->current_revision( $this->course ) );
		$db  = Database::current();
		$ctx = json_decode( (string) $db->scalar( 'SELECT context FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'course_published' ORDER BY id DESC LIMIT 1" ), true );
		self::assertSame( $fingerprints, $ctx['acknowledged_warnings'] );
		self::assertSame( 0, CourseValidator::validate( $this->course, 'publish', array( 'sha256:other' ) )['summary']['acknowledged'] );
	}

	/**
	 * §2.5: REST validation route and publish with report; editor status change kept as draft.
	 */
	public function test_publish_paths(): void {
		$bare = $this->post( 'ccap_module', 'publish', 'Empty module', 0, '' );
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $bare );
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		wp_set_current_user( $this->admin );
		$report = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/courses/' . $this->course . '/validation' ) );
		self::assertSame( 200, $report->get_status() );
		self::assertGreaterThan( 0, $report->get_data()['summary']['blocker'] );
		$publish = rest_do_request( new \WP_REST_Request( 'POST', '/course-captain/v1/courses/' . $this->course . '/publish' ) );
		self::assertSame( 422, $publish->get_status() );
		self::assertArrayHasKey( 'report', $publish->get_data()['data'] );

		add_filter( 'course_captain_publish_guard_applies', '__return_true' );
		wp_update_post(
			array(
				'ID'          => $this->course,
				'post_status' => 'publish',
			)
		);
		self::assertSame( 'draft', get_post_status( $this->course ), 'editor publish kept as draft' );
		self::assertIsString( get_transient( 'ccap_editor_error_' . $this->admin ) );
		self::assertNull( CoursePublisher::current()->current_revision( $this->course ) );

		CourseStructure::current()->remove_module( Actor::user( $this->admin ), $this->course, $bare );
		wp_update_post(
			array(
				'ID'          => $this->course,
				'post_status' => 'publish',
			)
		);
		self::assertSame( 'publish', get_post_status( $this->course ), 'valid course publishes' );
		remove_filter( 'course_captain_publish_guard_applies', '__return_true' );
	}
}
