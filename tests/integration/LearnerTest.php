<?php
/**
 * R1-16: learning area, read models, learner REST, blocks (R0-05 GM-001, GM-091, GM-143 part).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearnerQueries;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-16
 */
final class LearnerTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Course C-A.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Modules.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Lessons of M1.
	 *
	 * @var list<int>
	 */
	private array $lessons = array();

	/**
	 * Fixture: C-A published with two modules, learner with grant.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course', 'publish', 'C-A' );
		$admin         = Actor::user( $this->admin );
		foreach ( array( 'M1', 'M2' ) as $title ) {
			$module          = $this->post( 'ccap_module', 'publish', $title );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module );
		}
		foreach ( array( 'L1', 'L2' ) as $i => $title ) {
			$lesson = $this->post( 'ccap_lesson', 'publish', $title, $this->modules[0], $i );
			ActivityService::current()->define( $admin, $lesson, 'lesson', array() );
			$this->lessons[] = $lesson;
		}
		CoursePublisher::current()->publish( $admin, $this->course );
		$this->grant( $this->learner, 'active' );
	}

	/**
	 * Reset clocks.
	 */
	public function tear_down(): void {
		ContentGate::set_now( null );
		parent::tear_down();
	}

	/**
	 * Post.
	 *
	 * @param string $type      Type.
	 * @param string $status    Status.
	 * @param string $title     Title.
	 * @param int    $parent_id Parent.
	 * @param int    $order     Menu order.
	 * @return int
	 */
	private function post( string $type, string $status, string $title, int $parent_id = 0, int $order = 0 ): int {
		return (int) wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => $status,
				'post_title'  => $title,
				'post_parent' => $parent_id,
				'menu_order'  => $order,
				'post_author' => $this->admin,
			)
		);
	}

	/**
	 * Grant helper.
	 *
	 * @param int                  $user   User.
	 * @param string               $status Status.
	 * @param array<string, mixed> $extra  Columns.
	 */
	private function grant( int $user, string $status, array $extra = array() ): void {
		$db = Database::current();
		$db->insert(
			'ccap_grant',
			$extra + array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $this->course,
				'status'      => $status,
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
	}

	/**
	 * UID of the course.
	 *
	 * @return string
	 */
	private function uid(): string {
		return ( new ObjectIdentity( Database::current() ) )->ensure( get_post( $this->course ) );
	}

	/**
	 * GM-001: only published courses in catalog and block.
	 *
	 * @group GM-001
	 */
	public function test_GM_001_catalog(): void {
		$b = $this->post( 'ccap_course', 'publish', 'C-B' );
		$this->post( 'ccap_course', 'draft', 'C-C' );
		$this->post( 'ccap_course', 'ccap_archived', 'C-D' );
		self::assertSame( array( 'C-A', 'C-B' ), array_map( static fn( \WP_Post $p ): string => $p->post_title, LearnerQueries::current()->catalog( 10, 'title' ) ) );
		wp_set_current_user( 0 );
		$html = LearningArea::render_catalog( array( 'orderBy' => 'title' ) );
		self::assertStringContainsString( 'C-B', $html );
		self::assertStringNotContainsString( 'C-C', $html );
		self::assertStringNotContainsString( 'C-D', $html );
		wp_set_current_user( $this->learner );
		$only = LearningArea::render_catalog( array( 'showOnlyEnrolled' => true ) );
		self::assertStringContainsString( 'C-A', $only );
		self::assertStringNotContainsString( 'C-B', $only );
		unset( $b );
		$registry = \WP_Block_Type_Registry::get_instance();
		self::assertTrue( $registry->is_registered( 'course-captain/course-catalog' ) );
		self::assertSame( 3, $registry->get_registered( 'course-captain/learning-link' )->api_version );
	}

	/**
	 * GM-091: suspended > expired > pending; module grant → limited; trial → limited.
	 *
	 * @group GM-091
	 */
	public function test_GM_091_access_states(): void {
		$q     = LearnerQueries::current();
		$state = fn( int $user ): string => $q->access_state( Actor::user( $user ), $this->course )['state'];
		self::assertSame( 'active', $state( $this->learner ) );

		$user = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		self::assertSame( 'not_granted', $state( $user ) );
		$this->grant( $user, 'pending_payment' );
		self::assertSame( 'pending', $state( $user ) );
		$this->grant( $user, 'active', array( 'valid_until' => '2001-01-01 00:00:00' ) );
		self::assertSame( 'expired', $state( $user ), 'an expired active grant does not count' );
		$this->grant( $user, 'suspended' );
		self::assertSame( 'suspended', $state( $user ) );

		$module = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->grant( $module, 'active', array( 'module_id' => $this->modules[0] ) );
		self::assertSame( 'limited', $state( $module ) );

		$trial = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Database::current()->insert(
			'ccap_access_rule',
			array(
				'course_id'   => $this->course,
				'scope'       => 'view',
				'access_type' => 'free_limited',
				'limit_value' => 1,
			)
		);
		PolicyService::flush();
		self::assertSame( 'limited', $state( $trial ) );
	}

	/**
	 * My learning, resume, course room with lock reasons.
	 */
	public function test_my_learning_resume_and_room(): void {
		$actor = Actor::user( $this->learner );
		$mine  = LearnerQueries::current()->my_courses( $actor );
		self::assertCount( 1, $mine );
		self::assertSame( $this->lessons[0], $mine[0]['resume']['lesson_id'], 'first open lesson' );
		ActivityProgress::current()->interact( $actor, $this->course, $this->lessons[0], 'confirm' );
		$mine = LearnerQueries::current()->my_courses( $actor );
		self::assertSame( $this->lessons[1], $mine[0]['resume']['lesson_id'] );
		self::assertSame( 50, $mine[0]['progress']['completion_percent'] );

		Database::current()->insert(
			'ccap_access_rule',
			array(
				'course_id'   => $this->course,
				'module_id'   => $this->modules[1],
				'scope'       => 'view',
				'access_type' => 'drip',
				'limit_value' => 30,
			)
		);
		PolicyService::flush();
		$room = LearnerQueries::current()->course( $actor, $this->course );
		self::assertNull( $room['modules'][0]['locked_reason'] );
		self::assertSame( 'drip', $room['modules'][1]['locked_reason'] );
		self::assertNotNull( $room['modules'][1]['unlock_at'] );
		self::assertSame( array( 'completed', 'not_started' ), array_column( $room['modules'][0]['lessons'], 'state' ) );
	}

	/**
	 * Server-rendered pages: landmarks, skip link, aria-current, labels, viewport (GM-143 part).
	 *
	 * @group GM-143
	 */
	public function test_pages(): void {
		$actor = Actor::user( $this->learner );
		ActivityProgress::current()->interact( $actor, $this->course, $this->lessons[0], 'confirm' );
		$home = LearningArea::page( $actor, '' );
		self::assertStringContainsString( 'name="viewport"', $home );
		self::assertStringContainsString( 'class="ccap-skip" href="#ccap-main"', $home );
		self::assertStringContainsString( 'C-A', $home );
		self::assertStringContainsString( 'L2', $home, 'continue link' );

		$room = LearningArea::page( $actor, 'kurs/' . $this->uid() );
		$dump = (string) getenv( 'CCAP_DUMP_DIR' );
		if ( '' !== $dump ) {
			file_put_contents( $dump . '/room.html', $room ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Browser check (R1-16b).
			file_put_contents( $dump . '/home.html', $home ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Browser check (R1-16b).
		}
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( $room );
		libxml_clear_errors();
		$xpath = new \DOMXPath( $doc );
		self::assertSame( 1, $xpath->query( '//main[@id="ccap-main"]' )->length );
		self::assertSame( 1, $xpath->query( '//nav[@aria-label]' )->length );
		self::assertSame( 1, $xpath->query( '//a[@aria-current="step"]' )->length );
		self::assertStringContainsString( 'L2', (string) $xpath->query( '//a[@aria-current="step"]' )->item( 0 )->textContent );
		self::assertSame( 1, $xpath->query( '//h1' )->length );
		preg_match( '#<style>(.*?)</style>#s', $room, $css );
		self::assertSame( 0, preg_match( '/(?<![-\w])width:\s*(?!1px)\d+(px|rem|em)/', $css[1] ), 'no fixed widths in own CSS' );
		self::assertSame( 1, substr_count( $room, '<title>' ), 'one document title' );
		self::assertStringContainsString( '<title>C-A', $room );

		$stranger = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertStringContainsString( 'No access', LearningArea::page( $stranger, 'kurs/' . $this->uid() ) );
		self::assertStringContainsString( 'not available', LearningArea::page( $actor, 'kurs/00000000-0000-0000-0000-000000000000' ) );
	}

	/**
	 * Learner REST: 401, 200 with no-store, 404, trainer and progress.
	 */
	public function test_rest(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$call = static function ( string $method, string $route, array $body = array() ): \WP_REST_Response {
			$request = new \WP_REST_Request( $method, '/course-captain/v1' . $route );
			foreach ( $body as $key => $value ) {
				$request->set_param( $key, $value );
			}
			return rest_do_request( $request );
		};
		wp_set_current_user( 0 );
		self::assertSame( 401, $call( 'GET', '/me/courses' )->get_status() );

		wp_set_current_user( $this->learner );
		$mine = $call( 'GET', '/me/courses' );
		self::assertSame( 200, $mine->get_status() );
		self::assertSame( 'private, no-store', $mine->get_headers()['Cache-Control'] );
		self::assertSame( $this->uid(), $mine->get_data()[0]['course_uid'] );
		self::assertSame( 'C-A', $call( 'GET', '/courses/' . $this->uid() )->get_data()['title'] );
		self::assertSame( 404, $call( 'GET', '/courses/99999999' )->get_status() );
		self::assertCount( 2, $call( 'GET', '/courses/' . $this->course . '/lessons' )->get_data() );
		self::assertSame( 'completed', $call( 'PUT', '/courses/' . $this->course . '/lessons/' . $this->lessons[0] . '/progress', array( 'kind' => 'confirm' ) )->get_data()['status'] );

		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$q     = $repo->create(
			$admin,
			array(
				'question_type' => 'single_choice',
				'question_text' => 'Q',
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
		$repo->add_to_pool( $admin, $this->modules[0], $q );
		$next = $call( 'GET', '/courses/' . $this->course . '/next-question' )->get_data();
		self::assertSame( $q, $next['question_id'] );
		$answer = $call(
			'POST',
			'/answers',
			array(
				'token'           => $next['token'],
				'answer'          => array( 'A' ),
				'idempotency_key' => wp_generate_uuid4(),
			)
		);
		self::assertTrue( $answer->get_data()['correct'] );
		self::assertTrue( $call( 'PUT', '/courses/' . $this->course . '/favorites/' . $q )->get_data()['favorite'] );
		self::assertSame( 422, $call( 'GET', '/courses/' . $this->course . '/next-question', array( 'mode' => 'nope' ) )->get_status() );
	}

	/**
	 * Shortcodes `[ccap_learning_link]` and `[ccap_access]`.
	 */
	public function test_shortcodes(): void {
		$this->set_permalink_structure( '/%postname%/' );
		self::assertStringContainsString( '/lernen/', do_shortcode( '[ccap_learning_link label="Los"]' ) );
		self::assertStringContainsString( '>Los<', do_shortcode( '[ccap_learning_link label="Los"]' ) );
		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertStringNotContainsString( 'Inside', do_shortcode( '[ccap_access course="' . $this->uid() . '" message="Bitte buchen"]Inside[/ccap_access]' ) );
		self::assertStringContainsString( 'Bitte buchen', do_shortcode( '[ccap_access course="' . $this->uid() . '" message="Bitte buchen"]Inside[/ccap_access]' ) );
		wp_set_current_user( $this->learner );
		self::assertStringContainsString( 'Inside', do_shortcode( '[ccap_access course="' . $this->uid() . '"]Inside[/ccap_access]' ) );
	}
}
