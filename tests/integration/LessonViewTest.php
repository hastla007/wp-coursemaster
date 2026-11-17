<?php
/**
 * R1-29 B-01: lesson view in the learning area (route, gate, completion form, links).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearnerQueries;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Learner\LessonView;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

/**
 * Real tables; C-A with M1 (L1 manual_confirm, L2 view rule) and M2 (L3).
 *
 * @group R1-16
 * @group B-01
 */
final class LessonViewTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Enrolled learner.
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
	 * Modules.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Lessons L1, L2 (M1), L3 (M2).
	 *
	 * @var list<int>
	 */
	private array $lessons = array();

	/**
	 * Fixture.
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
		$l1 = $this->post( 'ccap_lesson', 'publish', 'L1', $this->modules[0], 0, 'Lesson one text.' );
		ActivityService::current()->define( $admin, $l1, 'lesson', array(), array( 'type' => 'manual_confirm' ) );
		$l2 = $this->post( 'ccap_lesson', 'publish', 'L2', $this->modules[0], 1, 'Lesson two text.' );
		ActivityService::current()->define(
			$admin,
			$l2,
			'lesson',
			array(),
			array(
				'type'   => 'view',
				'params' => array( 'min_seconds' => 60 ),
			)
		);
		$l3 = $this->post( 'ccap_lesson', 'publish', 'L3', $this->modules[1], 0, 'Lesson three text.' );
		ActivityService::current()->define( $admin, $l3, 'lesson', array() );
		$this->lessons = array( $l1, $l2, $l3 );
		CoursePublisher::current()->publish( $admin, $this->course );
		$this->grant( $this->learner );
		wp_set_current_user( $this->learner );
	}

	/**
	 * Reset clock and permalinks.
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
	 * @param string $content   Content.
	 * @return int
	 */
	private function post( string $type, string $status, string $title, int $parent_id = 0, int $order = 0, string $content = '' ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => $status,
				'post_title'   => $title,
				'post_content' => $content,
				'post_parent'  => $parent_id,
				'menu_order'   => $order,
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Active manual grant.
	 *
	 * @param int $user User.
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
	 * Drip rule locking M2 for 30 days.
	 */
	private function drip_m2(): void {
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
	}

	/**
	 * Course UID.
	 *
	 * @return string
	 */
	private function uid(): string {
		return ( new ObjectIdentity( Database::current() ) )->ensure( get_post( $this->course ) );
	}

	/**
	 * Lesson path below the area.
	 *
	 * @param int $lesson Lesson.
	 * @return string
	 */
	private function path( int $lesson ): string {
		return 'kurs/' . $this->uid() . '/lektion/' . $lesson;
	}

	/**
	 * DOM of a page.
	 *
	 * @param string $html HTML.
	 * @return \DOMXPath
	 */
	private static function dom( string $html ): \DOMXPath {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( $html );
		libxml_clear_errors();
		return new \DOMXPath( $doc );
	}

	/**
	 * POST data for a lesson form.
	 *
	 * @param int    $lesson Lesson.
	 * @param string $action `ccap_do`.
	 * @return array<string, mixed>
	 */
	private static function form( int $lesson, string $action ): array {
		return array(
			'_wpnonce' => wp_create_nonce( LessonView::NONCE . ':' . $lesson ),
			'ccap_do'  => $action,
		);
	}

	/**
	 * Course room and "Continue" link to the learning-area lesson route, never to the non-public permalink.
	 */
	public function test_course_room_and_continue_links(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$actor = Actor::user( $this->learner );
		$this->drip_m2();
		$room  = self::dom( LearningArea::page( $actor, 'kurs/' . $this->uid() ) );
		$links = $room->query( '//nav//ol[@class="ccap-lessons"]//a' );
		self::assertSame( 2, $links->length, 'L1 and L2 linked, L3 locked (drip)' );
		self::assertSame( LearningArea::lesson_url( $this->uid(), $this->lessons[0] ), $links->item( 0 )->getAttribute( 'href' ) );
		self::assertSame( home_url( '/lernen/' . $this->path( $this->lessons[1] ) ), $links->item( 1 )->getAttribute( 'href' ) );
		self::assertSame( 0, $room->query( '//li[@id="lesson-' . $this->lessons[2] . '"]//a' )->length, 'locked lesson without link' );
		self::assertStringNotContainsString( 'post_type=ccap_lesson', LearningArea::page( $actor, 'kurs/' . $this->uid() ) );

		$home     = self::dom( LearningArea::page( $actor, '' ) );
		$continue = $home->query( '//a[contains(@class,"ccap-button")]' );
		self::assertSame( 1, $continue->length );
		self::assertSame( LearningArea::lesson_url( $this->uid(), $this->lessons[0] ), $continue->item( 0 )->getAttribute( 'href' ) );
		self::assertStringContainsString( 'L1', $continue->item( 0 )->textContent );
	}

	/**
	 * The link target routes to the lesson (pretty and plain permalinks) and answers 200 with content and R0-15 structure.
	 */
	public function test_lesson_link_target_200_for_enrolled_learner(): void {
		$actor = Actor::user( $this->learner );
		foreach ( array( '/%postname%/', '' ) as $structure ) {
			$this->set_permalink_structure( $structure );
			LearningArea::rewrite();
			flush_rewrite_rules( false );
			$this->go_to( LearningArea::lesson_url( $this->uid(), $this->lessons[0] ) );
			self::assertSame( $this->path( $this->lessons[0] ), trim( (string) get_query_var( LearningArea::QUERY_VAR ), '/' ), 'route for "' . $structure . '"' );
			self::assertSame( 200, LearningArea::resolve( $actor, (string) get_query_var( LearningArea::QUERY_VAR ) )['status'] );
		}

		add_filter( 'the_content', array( self::class, 'mark_content' ) );
		$html = LearningArea::page( $actor, $this->path( $this->lessons[0] ) );
		remove_filter( 'the_content', array( self::class, 'mark_content' ) );
		$xpath = self::dom( $html );
		self::assertSame( 1, $xpath->query( '//h1' )->length );
		self::assertSame( 'L1', trim( $xpath->query( '//h1' )->item( 0 )->textContent ) );
		self::assertSame( 1, $xpath->query( '//main[@id="ccap-main"]' )->length );
		self::assertStringContainsString( 'class="ccap-skip" href="#ccap-main"', $html );
		self::assertStringContainsString( 'Lesson one text.', $html );
		self::assertStringContainsString( 'ccap-filtered', $html, 'content runs through the_content' );
		self::assertStringContainsString( 'Status: not completed yet', $html );
		self::assertSame( 1, $xpath->query( '//form[@method="post"]//button[@value="complete"]' )->length );
		self::assertSame( 1, $xpath->query( '//nav[@aria-label="Lessons"]//a[@rel="next"]' )->length, 'next lesson' );
		self::assertSame( 0, $xpath->query( '//a[@rel="prev"]' )->length );
		self::assertSame( 1, substr_count( $html, '<title>' ) );
		self::assertSame( 'in_progress', LearnerQueries::current()->course( $actor, $this->course )['modules'][0]['lessons'][0]['state'], 'opening counts as access' );
	}

	/**
	 * Marker filter.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function mark_content( $content ): string {
		return '<span class="ccap-filtered"></span>' . $content;
	}

	/**
	 * Locked lessons answer 403 with the reason as text; foreign lessons and unknown courses 404.
	 */
	public function test_locked_lesson_403_and_unknown_404(): void {
		$actor = Actor::user( $this->learner );
		$this->drip_m2();
		$page = LearningArea::resolve( $actor, $this->path( $this->lessons[2] ) );
		self::assertSame( 403, $page['status'] );
		self::assertStringContainsString( 'Available from', $page['body'] );
		self::assertStringNotContainsString( 'Lesson three text.', $page['body'] );
		self::assertStringNotContainsString( '<form', $page['body'] );
		self::assertNull( ActivityProgress::current()->state( $this->learner, $this->lessons[2] ), 'no progress row for a locked lesson' );

		$stranger = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$page     = LearningArea::resolve( $stranger, $this->path( $this->lessons[0] ) );
		self::assertSame( 403, $page['status'] );
		self::assertStringContainsString( 'Locked: no access', $page['body'] );
		self::assertStringNotContainsString( 'Lesson one text.', $page['body'] );

		$foreign = $this->post( 'ccap_lesson', 'publish', 'Elsewhere', 0, 0, 'Foreign text.' );
		$page    = LearningArea::resolve( $actor, $this->path( $foreign ) );
		self::assertSame( 404, $page['status'] );
		self::assertStringContainsString( 'This lesson is not available.', $page['body'] );
		self::assertSame( 404, LearningArea::resolve( $actor, 'kurs/00000000-0000-0000-0000-000000000000/lektion/' . $this->lessons[0] )['status'] );
		self::assertSame( 200, LearningArea::resolve( $actor, 'kurs/' . $this->uid() )['status'] );
	}

	/**
	 * `ContentProtection` applies to the lesson text.
	 */
	public function test_content_protection_applies(): void {
		$other = $this->post( 'ccap_course', 'publish', 'C-B' );
		update_post_meta( $this->lessons[0], ContentProtection::META, $other );
		ContentProtection::flush();
		$html = LearningArea::page( Actor::user( $this->learner ), $this->path( $this->lessons[0] ) );
		self::assertStringNotContainsString( 'Lesson one text.', $html );
		self::assertStringContainsString( 'This content is available to course participants.', $html );
	}

	/**
	 * `manual_confirm` via the server-side form (PRG, nonce), status as text, "Continue" moves on.
	 */
	public function test_completion_via_form(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$actor = Actor::user( $this->learner );
		$path  = $this->path( $this->lessons[0] );

		$bad = LearningArea::handle_post( $actor, $path, array( '_wpnonce' => 'nope' ) + self::form( $this->lessons[0], 'complete' ) );
		self::assertStringContainsString( 'ccap_status=ccap_invalid_token', $bad );
		self::assertNotSame( 'completed', ActivityProgress::current()->state( $this->learner, $this->lessons[0] )['status'] ?? null );
		self::assertStringContainsString( 'ccap_status=ccap_invalid_param', LearningArea::handle_post( $actor, $path, self::form( $this->lessons[0], 'view' ) ), 'wrong rule' );

		$target = LearningArea::handle_post( $actor, $path, self::form( $this->lessons[0], 'complete' ) );
		self::assertSame( add_query_arg( 'ccap_status', 'completed', LearningArea::lesson_url( $this->uid(), $this->lessons[0] ) ) . '#ccap-main', $target );
		self::assertSame( 'completed', ActivityProgress::current()->state( $this->learner, $this->lessons[0] )['status'] );

		$html  = LearningArea::page( $actor, $path, 'completed' );
		$xpath = self::dom( $html );
		self::assertStringContainsString( 'Lesson completed.', (string) $xpath->query( '//*[@role="status"]' )->item( 0 )->textContent );
		self::assertStringContainsString( 'Status: completed', $html );
		self::assertSame( 0, $xpath->query( '//button[@value="complete"]' )->length, 'no second completion' );

		$mine = LearnerQueries::current()->my_courses( $actor );
		self::assertSame( $this->lessons[1], $mine[0]['resume']['lesson_id'] );
		self::assertStringContainsString( LearningArea::lesson_url( $this->uid(), $this->lessons[1] ), LearningArea::my_learning( $actor ) );

		// Locked lessons cannot be completed by posting directly.
		$this->drip_m2();
		$locked = LearningArea::handle_post( $actor, $this->path( $this->lessons[2] ), self::form( $this->lessons[2], 'complete' ) );
		self::assertStringContainsString( 'ccap_status=ccap_content_locked', $locked );
		self::assertNull( ActivityProgress::current()->state( $this->learner, $this->lessons[2] ) );
	}

	/**
	 * `view` rule: signed opening time, minimum time enforced server-side.
	 */
	public function test_view_rule_form(): void {
		$actor = Actor::user( $this->learner );
		$path  = $this->path( $this->lessons[1] );
		$xpath = self::dom( LearningArea::page( $actor, $path ) );
		self::assertSame( 1, $xpath->query( '//button[@value="view"][@aria-describedby="ccap-view-hint"]' )->length );
		$opened = (int) $xpath->query( '//input[@name="opened"]' )->item( 0 )->getAttribute( 'value' );
		$sig    = $xpath->query( '//input[@name="opened_sig"]' )->item( 0 )->getAttribute( 'value' );

		$early = LearningArea::handle_post( $actor, $path, self::form( $this->lessons[1], 'view' ) + compact( 'opened' ) + array( 'opened_sig' => $sig ) );
		self::assertStringContainsString( 'ccap_status=view_too_short', $early );
		$forged = LearningArea::handle_post( $actor, $path, self::form( $this->lessons[1], 'view' ) + array( 'opened' => $opened - 120 ) + array( 'opened_sig' => $sig ) );
		self::assertStringContainsString( 'ccap_status=ccap_invalid_token', $forged, 'backdating breaks the signature' );
		self::assertNotSame( 'completed', ActivityProgress::current()->state( $this->learner, $this->lessons[1] )['status'] );

		$sign = new \ReflectionMethod( LessonView::class, 'sign' );
		$past = time() - 120;
		$done = LearningArea::handle_post( $actor, $path, self::form( $this->lessons[1], 'view' ) + array( 'opened' => $past ) + array( 'opened_sig' => $sign->invoke( null, $actor, $this->lessons[1], $past ) ) );
		self::assertStringContainsString( 'ccap_status=completed', $done );
		self::assertSame( 'completed', ActivityProgress::current()->state( $this->learner, $this->lessons[1] )['status'] );
	}
}
