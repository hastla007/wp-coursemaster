<?php
/**
 * R1-05: authoring service, REST routes, editor screens, notices (R0-05 GM-140, GM-141).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Admin\AuthoringService;
use CourseCaptain\Admin\EditorScreens;
use CourseCaptain\Admin\Notices;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-05
 */
final class AuthoringTest extends SchemaTestCase {

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
	 * Modules.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->course = $this->post( 'ccap_course', 'draft' );
		foreach ( array( 'M-A', 'M-B', 'M-C' ) as $title ) {
			$module          = $this->post( 'ccap_module', 'publish', $title );
			$this->modules[] = $module;
			// Every module carries a valid text lesson, so courses pass the publish validator (R1-26).
			$text = $this->post( 'ccap_lesson', 'publish', 'Intro ' . $title, $module );
			update_post_meta( $text, '_ccap_activity_type', 'lesson' );
		}
		PolicyService::flush();
		Notices::reset();
	}

	/**
	 * Post.
	 *
	 * @param string $type   Type.
	 * @param string $status Status.
	 * @param string $title  Title.
	 * @param int    $parent_id Parent.
	 * @return int
	 */
	private function post( string $type, string $status, string $title = 'X', int $parent_id = 0 ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => $status,
				'post_title'   => $title,
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
				'post_content' => 'Body',
			)
		);
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
	 * REST request helper.
	 *
	 * @param string               $method Method.
	 * @param string               $route  Route.
	 * @param array<string, mixed> $body   Body.
	 * @return \WP_REST_Response
	 */
	private function rest( string $method, string $route, array $body = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/course-captain/v1' . $route );
		foreach ( $body as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	/**
	 * Structure: order, required, remove, validation, rights.
	 */
	public function test_structure_editing(): void {
		$service           = AuthoringService::current();
		$admin             = Actor::user( $this->admin );
		list( $a, $b, $c ) = $this->modules;
		$rows              = $service->update_structure(
			$admin,
			$this->course,
			array(
				array( 'module_id' => $b ),
				array(
					'module_id' => $a,
					'required'  => '0',
				),
			)
		);
		self::assertSame( array( $b, $a ), array_column( $rows, 'module_id' ) );
		self::assertSame( array( true, false ), array_column( $rows, 'required' ) );

		$rows = $service->update_structure(
			$admin,
			$this->course,
			array(
				array(
					'module_id'  => $a,
					'sort_order' => '5',
				),
				array(
					'module_id'  => $c,
					'sort_order' => 50,
				),
			)
		);
		self::assertSame( array( $a, $c ), array_column( $rows, 'module_id' ), 'B removed, order by position' );

		$this->assertRejected( fn() => $service->update_structure( $admin, $this->course, array( array( 'module_id' => 999999 ) ) ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => $service->update_structure( $admin, $this->course, array( array( 'module_id' => $a ), array( 'module_id' => $a ) ) ), 'ccap_validation_failed' );
		$this->assertRejected(
			fn() => $service->update_structure(
				$admin,
				$this->course,
				array(
					array(
						'module_id'  => $a,
						'sort_order' => 'x',
					),
				)
			),
			'ccap_validation_failed'
		);
		self::assertSame( array( $a, $c ), array_column( CourseStructure::current()->modules( $this->course ), 'module_id' ), 'failed validation changes nothing' );

		$subscriber = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		try {
			$service->update_structure( $subscriber, $this->course, array() );
			self::fail( 'no rights' );
		} catch ( CourseException $e ) {
			self::assertContains( $e->status, array( 403, 404 ) );
		}
	}

	/**
	 * Duplicate with copied_from_uid and a fresh activity UID; publish with change class.
	 */
	public function test_duplicate_and_publish(): void {
		$admin  = Actor::user( $this->admin );
		$lesson = $this->post( 'ccap_lesson', 'publish', 'Lesson 1', $this->modules[0] );
		ActivityService::current()->define(
			$admin,
			$lesson,
			'video',
			array(
				'duration_seconds' => 60,
				'url'              => 'https://videos.example.test/lesson-1.mp4',
			)
		);
		$copy = AuthoringService::current()->duplicate( $admin, $lesson );
		$post = get_post( $copy );
		self::assertSame( array( 'draft', 'Lesson 1 (copy)', $this->modules[0] ), array( $post->post_status, $post->post_title, (int) $post->post_parent ) );
		self::assertSame( ( new ObjectIdentity( Database::current() ) )->ensure( get_post( $lesson ) ), get_post_meta( $copy, AuthoringService::COPIED_FROM_META, true ) );
		self::assertSame( 'video', ActivityService::definition( $copy )['type'] );
		self::assertNotSame( get_post_meta( $lesson, ObjectIdentity::ACTIVITY_UID_META, true ), get_post_meta( $copy, ObjectIdentity::ACTIVITY_UID_META, true ) );
		$this->assertRejected( fn() => AuthoringService::current()->duplicate( $admin, $this->course ), 'ccap_not_found' );

		AuthoringService::current()->update_structure( $admin, $this->course, array( array( 'module_id' => $this->modules[0] ) ) );
		$this->assertRejected( fn() => AuthoringService::current()->publish( $admin, $this->course, 'major' ), 'ccap_invalid_param' );
		AuthoringService::current()->publish( $admin, $this->course, 'patch', '<b>Fixed typo</b>' );
		$revision = CoursePublisher::current()->current_revision( $this->course );
		self::assertSame( 'patch', $revision['change_class'] );
		self::assertStringNotContainsString( '<b>', (string) $revision['notes'] );
		self::assertSame( 'publish', get_post_status( $this->course ) );
	}

	/**
	 * HTTP variants: 401 logged out, 403/404 without rights, 422 validation, 200/201 success.
	 */
	public function test_rest_routes(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		wp_set_current_user( 0 );
		self::assertSame( 401, $this->rest( 'GET', '/courses/' . $this->course . '/structure' )->get_status() );

		wp_set_current_user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertContains( $this->rest( 'GET', '/courses/' . $this->course . '/structure' )->get_status(), array( 403, 404 ) );

		wp_set_current_user( $this->admin );
		$ok = $this->rest( 'PUT', '/courses/' . $this->course . '/structure', array( 'modules' => array( array( 'module_id' => $this->modules[1] ) ) ) );
		self::assertSame( 200, $ok->get_status() );
		self::assertSame( $this->modules[1], $ok->get_data()[0]['module_id'] );
		$bad = $this->rest( 'PUT', '/courses/' . $this->course . '/structure', array( 'modules' => array( array( 'module_id' => 0 ) ) ) );
		self::assertSame( array( 422, 'ccap_validation_failed' ), array( $bad->get_status(), $bad->get_data()['code'] ) );

		$lesson = $this->post( 'ccap_lesson', 'draft', 'L', $this->modules[1] );
		self::assertSame( 400, $this->rest( 'PUT', '/lessons/' . $lesson . '/activity', array( 'type' => 'nonsense' ) )->get_status() );
		self::assertSame( 'lesson', $this->rest( 'PUT', '/lessons/' . $lesson . '/activity', array( 'type' => 'lesson' ) )->get_data()['type'] );
		$dup = $this->rest( 'POST', '/items/' . $lesson . '/duplicate' );
		self::assertSame( 201, $dup->get_status() );
		self::assertGreaterThan( $lesson, $dup->get_data()['id'] );
		self::assertSame( 200, $this->rest( 'POST', '/courses/' . $this->course . '/publish', array( 'change_class' => 'minor' ) )->get_status() );
	}

	/**
	 * Screens: every field labelled (GM-141 / A11Y), saving through the form with nonce.
	 *
	 * @group GM-141
	 */
	public function test_editor_screens(): void {
		wp_set_current_user( $this->admin );
		AuthoringService::current()->update_structure( Actor::user( $this->admin ), $this->course, array( array( 'module_id' => $this->modules[0] ) ) );
		ob_start();
		EditorScreens::render_structure( get_post( $this->course ) );
		EditorScreens::render_publish( get_post( $this->course ) );
		$lesson = $this->post( 'ccap_lesson', 'draft', 'L', $this->modules[0] );
		EditorScreens::render_activity( get_post( $lesson ) );
		$html = (string) ob_get_clean();
		self::assertSame( array(), self::unlabelled( $html ), 'every visible field has a label' );
		self::assertDoesNotMatchRegularExpression( '/[✓✗✔✘]/u', $html, 'text instead of symbols' );

		$_POST = array(
			'ccap_structure_nonce'  => wp_create_nonce( 'ccap_course_structure_' . $this->course ),
			'ccap_modules'          => array(
				array(
					'module_id'  => (string) $this->modules[0],
					'sort_order' => '20',
					'required'   => '0',
				),
			),
			'ccap_add_module'       => (string) $this->modules[2],
			'ccap_publish_revision' => '1',
			'ccap_change_class'     => 'minor',
		);
		EditorScreens::save_course( $this->course, get_post( $this->course ) );
		$modules = CourseStructure::current()->modules( $this->course );
		self::assertSame( array( $this->modules[0], $this->modules[2] ), array_column( $modules, 'module_id' ) );
		self::assertFalse( $modules[0]['required'] );
		self::assertNotNull( CoursePublisher::current()->current_revision( $this->course ) );

		$_POST = array(
			'ccap_activity_nonce'    => wp_create_nonce( 'ccap_lesson_activity_' . $lesson ),
			'ccap_activity_type'     => 'video',
			'ccap_activity_settings' => '{"duration_seconds": 30}',
			'ccap_activity_rule'     => '',
		);
		EditorScreens::save_lesson( $lesson, get_post( $lesson ) );
		self::assertSame( 30, ActivityService::definition( $lesson )['settings']['duration_seconds'] );

		$_POST['ccap_activity_settings'] = '{broken';
		EditorScreens::save_lesson( $lesson, get_post( $lesson ) );
		self::assertIsString( get_transient( 'ccap_editor_error_' . $this->admin ), 'error kept for the next screen' );

		$_POST = array( 'ccap_structure_nonce' => 'forged' );
		EditorScreens::save_course( $this->course, get_post( $this->course ) );
		self::assertCount( 2, CourseStructure::current()->modules( $this->course ), 'forged nonce ignored' );
		$_POST = array();

		$actions = EditorScreens::row_actions( array(), get_post( $lesson ) );
		self::assertStringContainsString( 'ccap_duplicate', $actions['ccap_duplicate'] );
		self::assertSame( array(), EditorScreens::row_actions( array(), get_post( $this->course ) ) );
	}

	/**
	 * R1-18 B-4 (Flow 1 in the browser): a lesson is put into a module in the
	 * lesson editor (labelled select, saved with the panel nonce); the module
	 * list counts it; unknown modules and missing rights are rejected; the
	 * validation panel links blockers to the editor of the module/lesson.
	 *
	 * @group R1-18
	 */
	public function test_lesson_module_and_validation_jump_links(): void {
		wp_set_current_user( $this->admin );
		$lesson = $this->post( 'ccap_lesson', 'draft', 'Loose lesson' );
		update_post_meta( $lesson, '_ccap_activity_type', 'lesson' );
		ob_start();
		EditorScreens::render_activity( get_post( $lesson ) );
		$html = (string) ob_get_clean();
		self::assertStringContainsString( '<label for="ccap-lesson-module">Module</label>', $html );
		self::assertStringContainsString( '<option value="' . $this->modules[1] . '">M-B</option>', $html );
		self::assertSame( array(), self::unlabelled( $html ) );

		$_POST = array(
			'ccap_activity_nonce'    => wp_create_nonce( 'ccap_lesson_activity_' . $lesson ),
			'ccap_lesson_module'     => (string) $this->modules[1],
			'ccap_activity_type'     => 'lesson',
			'ccap_activity_settings' => '{}',
			'ccap_activity_rule'     => '',
		);
		EditorScreens::save_lesson( $lesson, get_post( $lesson ) );
		self::assertSame( $this->modules[1], (int) get_post( $lesson )->post_parent );
		self::assertContains( $lesson, array_map( static fn( \WP_Post $p ): int => (int) $p->ID, CourseStructure::current()->lessons( $this->modules[1] ) ) );
		ob_start();
		EditorScreens::render_activity( get_post( $lesson ) );
		self::assertStringContainsString( '<option value="' . $this->modules[1] . '" selected=\'selected\'>M-B</option>', (string) ob_get_clean() );

		self::assertFalse( get_transient( 'ccap_editor_error_' . $this->admin ), 'first save without error' );
		$_POST['ccap_lesson_module'] = '999999';
		EditorScreens::save_lesson( $lesson, get_post( $lesson ) );
		self::assertSame( $this->modules[1], (int) get_post( $lesson )->post_parent, 'unknown module ignored' );
		self::assertIsString( get_transient( 'ccap_editor_error_' . $this->admin ) );
		$_POST = array(
			'ccap_activity_nonce' => 'forged',
			'ccap_lesson_module'  => (string) $this->modules[2],
		);
		EditorScreens::save_lesson( $lesson, get_post( $lesson ) );
		self::assertSame( $this->modules[1], (int) get_post( $lesson )->post_parent, 'forged nonce ignored' );
		$_POST = array();

		$subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertRejected( fn() => AuthoringService::current()->move_lesson( Actor::user( $subscriber ), $lesson, $this->modules[2] ), 'ccap_forbidden' );
		$this->assertRejected( fn() => AuthoringService::current()->move_lesson( Actor::user( $this->admin ), $this->modules[0], $this->modules[2] ), 'ccap_not_found' );
		AuthoringService::current()->move_lesson( Actor::user( $this->admin ), $lesson, $this->modules[2] );
		self::assertSame( $this->modules[2], (int) get_post( $lesson )->post_parent );

		// Empty module → blocker VAL-020 with a link to the module editor (F1.1).
		$empty = $this->post( 'ccap_module', 'publish', 'Empty module' );
		AuthoringService::current()->update_structure( Actor::user( $this->admin ), $this->course, array( array( 'module_id' => $this->modules[0] ), array( 'module_id' => $empty ) ) );
		$panel = EditorScreens::validation_panel( $this->course );
		self::assertStringContainsString( '(VAL-020)', $panel );
		self::assertStringContainsString( 'href="' . esc_url( (string) get_edit_post_link( $empty, 'raw' ) ) . '" class="ccap-validation-jump">Edit &quot;Empty module&quot;</a>', $panel );
		wp_set_current_user( $subscriber );
		self::assertStringNotContainsString( 'ccap-validation-jump', EditorScreens::validation_panel( $this->course ), 'no link without the edit right' );
	}

	/**
	 * R1-18 B-4 (found by the Flow 1 browser run): the block editor posts the
	 * module form of the first page load again on every save. A stale form
	 * must not drop a module added by an earlier save (F1.4 "kein stiller
	 * Verlust"); only its "Add module" is applied, with a hint.
	 *
	 * @group R1-18
	 */
	public function test_stale_module_form_keeps_added_modules(): void {
		wp_set_current_user( $this->admin );
		ob_start();
		EditorScreens::render_structure( get_post( $this->course ) );
		$html = (string) ob_get_clean();
		self::assertSame( 1, preg_match( '/name="ccap_structure_rev" value="([0-9a-f]{32})"/', $html, $m ) );
		$stale = array(
			'ccap_structure_nonce' => wp_create_nonce( 'ccap_course_structure_' . $this->course ),
			'ccap_structure_rev'   => $m[1],
		);
		$_POST = $stale + array( 'ccap_add_module' => (string) $this->modules[0] );
		EditorScreens::save_course( $this->course, get_post( $this->course ) );
		self::assertSame( array( $this->modules[0] ), array_column( CourseStructure::current()->modules( $this->course ), 'module_id' ) );
		self::assertFalse( get_transient( 'ccap_editor_error_' . $this->admin ), 'first save is current' );

		// Second save from the same page load: no rows posted, still the first add selected plus a new one.
		$_POST = $stale + array( 'ccap_add_module' => (string) $this->modules[1] );
		EditorScreens::save_course( $this->course, get_post( $this->course ) );
		self::assertSame( array( $this->modules[0], $this->modules[1] ), array_column( CourseStructure::current()->modules( $this->course ), 'module_id' ), 'module of the first save kept' );
		self::assertStringContainsString( 'reload the page', (string) get_transient( 'ccap_editor_error_' . $this->admin ) );

		// Stale rows (remove) are not applied; the add already present is not doubled.
		$_POST = $stale + array(
			'ccap_add_module' => (string) $this->modules[0],
			'ccap_modules'    => array(
				array(
					'module_id' => (string) $this->modules[0],
					'remove'    => '1',
				),
			),
		);
		EditorScreens::save_course( $this->course, get_post( $this->course ) );
		self::assertSame( array( $this->modules[0], $this->modules[1] ), array_column( CourseStructure::current()->modules( $this->course ), 'module_id' ) );

		// A fresh form applies its rows again.
		ob_start();
		EditorScreens::render_structure( get_post( $this->course ) );
		preg_match( '/name="ccap_structure_rev" value="([0-9a-f]{32})"/', (string) ob_get_clean(), $m );
		$_POST = array(
			'ccap_structure_nonce' => $stale['ccap_structure_nonce'],
			'ccap_structure_rev'   => $m[1],
			'ccap_modules'         => array(
				array( 'module_id' => (string) $this->modules[0] ),
				array(
					'module_id' => (string) $this->modules[1],
					'remove'    => '1',
				),
			),
		);
		EditorScreens::save_course( $this->course, get_post( $this->course ) );
		self::assertSame( array( $this->modules[0] ), array_column( CourseStructure::current()->modules( $this->course ), 'module_id' ) );
		$_POST = array();
	}

	/**
	 * UI-F6 (h5 L-4): the block editor saves the meta boxes again without
	 * reloading them. A "Publish a new revision when saving" box still
	 * ticked from an earlier save must not publish once more; after the save
	 * the panels come back from the stored state with the box unticked, the
	 * new revision and the current module list (AJAX `ccap_editor_flash`).
	 *
	 * @group UI-F6
	 */
	public function test_publish_box_does_not_republish_and_panels_refresh(): void {
		wp_set_current_user( $this->admin );
		AuthoringService::current()->update_structure( Actor::user( $this->admin ), $this->course, array( array( 'module_id' => $this->modules[0] ) ) );
		ob_start();
		EditorScreens::render_publish( get_post( $this->course ) );
		$html = (string) ob_get_clean();
		self::assertSame( 1, preg_match( '/name="ccap_publish_revision" value="(base:0)"/', $html, $m ), 'box names the revision it was rendered for' );
		$form  = array(
			'ccap_structure_nonce'  => wp_create_nonce( 'ccap_course_structure_' . $this->course ),
			'ccap_modules'          => array( array( 'module_id' => (string) $this->modules[0] ) ),
			'ccap_add_module'       => (string) $this->modules[2],
			'ccap_publish_revision' => $m[1],
			'ccap_change_class'     => 'minor',
		);
		$_POST = $form;
		EditorScreens::save_course( $this->course, get_post( $this->course ) );
		$first = CoursePublisher::current()->current_revision( $this->course );
		self::assertNotNull( $first );
		self::assertFalse( get_transient( 'ccap_editor_error_' . $this->admin ) );

		// Second save from the same page load with a further change (box still ticked):
		// the change is saved to the draft, but no new revision is published; a hint instead.
		$form['ccap_add_module'] = '0';
		$form['ccap_modules']    = array(
			array( 'module_id' => (string) $this->modules[0] ),
			array(
				'module_id'  => (string) $this->modules[2],
				'sort_order' => '99',
			),
		);
		$_POST                   = $form;
		EditorScreens::save_course( $this->course, get_post( $this->course ) );
		self::assertSame( (int) $first['revision_no'], (int) CoursePublisher::current()->current_revision( $this->course )['revision_no'], 'not published again' );
		self::assertStringContainsString( 'already published', (string) EditorScreens::pull_flash() );
		self::assertSame( 99, CourseStructure::current()->modules( $this->course )[1]['sort_order'], 'draft change saved' );

		// Fresh panels after the save: box unticked for the new revision, module list current.
		$boxes = EditorScreens::fresh_boxes( $this->course );
		self::assertSame( array( 'ccap-course-structure', 'ccap-course-publish' ), array_keys( $boxes ) );
		self::assertStringContainsString( 'name="ccap_publish_revision" value="base:' . (int) $first['revision_no'] . '">', $boxes['ccap-course-publish'], 'unticked, new base' );
		self::assertStringContainsString( 'Published revision: ' . (int) $first['revision_no'], $boxes['ccap-course-publish'] );
		self::assertStringContainsString( 'name="ccap_modules[1][module_id]" value="' . $this->modules[2] . '"', $boxes['ccap-course-structure'] );
		self::assertStringContainsString( 'name="ccap_modules[1][sort_order]" value="99"', $boxes['ccap-course-structure'] );
		self::assertSame( array(), self::unlabelled( implode( '', $boxes ) ) );

		// Ticked again on the fresh panel: publishes the next revision.
		$_POST = array( 'ccap_publish_revision' => 'base:' . (int) $first['revision_no'] ) + $form;
		EditorScreens::save_course( $this->course, get_post( $this->course ) );
		self::assertSame( (int) $first['revision_no'] + 1, (int) CoursePublisher::current()->current_revision( $this->course )['revision_no'], (string) EditorScreens::pull_flash() );
		$_POST = array();

		// Lesson panel; nothing for posts the viewer may not edit.
		$lesson = $this->post( 'ccap_lesson', 'draft', 'L', $this->modules[0] );
		self::assertSame( array( 'ccap-lesson-activity' ), array_keys( EditorScreens::fresh_boxes( $lesson ) ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertSame( array(), EditorScreens::fresh_boxes( $this->course ) );
		self::assertSame( array(), EditorScreens::fresh_boxes( 0 ) );
	}

	/**
	 * R1-18 B-4 (found by the Flow 1 browser run): the block editor follows the
	 * redirect of its meta box save in the background (fetch); that page must
	 * not consume the "Not saved" message, the next real page view shows it.
	 *
	 * @group R1-18
	 */
	public function test_save_error_survives_the_background_meta_box_page(): void {
		wp_set_current_user( $this->admin );
		$key = 'ccap_editor_error_' . $this->admin;
		set_transient( $key, 'Not saved: x', 600 );
		$_SERVER['HTTP_SEC_FETCH_MODE'] = 'cors';
		EditorScreens::flash();
		self::assertSame( 'Not saved: x', get_transient( $key ), 'kept for the next page view' );
		self::assertArrayNotHasKey( 'editor_save', Notices::visible( $this->admin ) );
		$_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate';
		EditorScreens::flash();
		self::assertFalse( get_transient( $key ) );
		self::assertArrayHasKey( 'editor_save', Notices::visible( $this->admin ) );
		unset( $_SERVER['HTTP_SEC_FETCH_MODE'] );
		set_transient( $key, 'Not saved: y', 600 );
		EditorScreens::flash();
		self::assertFalse( get_transient( $key ), 'without the header (older clients) as before' );

		// Block editor: the message becomes an editor notice (classic notices are hidden there).
		set_current_screen( 'ccap_lesson' );
		set_transient( $key, 'Not saved: z', 600 );
		$_SERVER['HTTP_SEC_FETCH_MODE'] = 'cors';
		EditorScreens::editor_notices();
		self::assertTrue( wp_script_is( 'ccap-editor-notices', 'enqueued' ) );
		self::assertStringContainsString( '"message":""', (string) wp_scripts()->get_data( 'ccap-editor-notices', 'data' ), 'background page: nothing taken' );
		self::assertSame( 'Not saved: z', get_transient( $key ) );
		wp_dequeue_script( 'ccap-editor-notices' );
		wp_deregister_script( 'ccap-editor-notices' );
		$_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate';
		EditorScreens::editor_notices();
		self::assertStringContainsString( '"message":"Not saved: z"', (string) wp_scripts()->get_data( 'ccap-editor-notices', 'data' ) );
		self::assertFalse( get_transient( $key ) );
		set_transient( $key, 'Not saved: after save', 600 );
		self::assertSame( 'Not saved: after save', EditorScreens::pull_flash(), 'what the AJAX pull returns after a meta box save' );
		self::assertSame( '', EditorScreens::pull_flash() );
		wp_dequeue_script( 'ccap-editor-notices' );
		wp_deregister_script( 'ccap-editor-notices' );
		unset( $_SERVER['HTTP_SEC_FETCH_MODE'] );
		set_current_screen( 'front' );
	}

	/**
	 * R1-18 B-4 (found by the Flow 1 browser run): a Course Manager without
	 * the core `edit_posts` may open "Add course/module/lesson"
	 * (post-new.php); WordPress resolves the page through the plugin menu.
	 *
	 * @group R1-18
	 */
	public function test_course_manager_may_open_the_add_screens(): void {
		global $menu, $submenu, $_wp_menu_nopriv, $_wp_submenu_nopriv, $pagenow, $typenow, $plugin_page, $_wp_real_parent_file, $_registered_pages, $_parent_pages, $admin_page_hooks, $parent_file;
		require_once ABSPATH . 'wp-admin/includes/admin.php';
		$manager = (int) self::factory()->user->create( array( 'role' => 'ccap_course_manager' ) );
		wp_set_current_user( $manager );
		set_current_screen( 'dashboard' );
		$menu                 = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Admin menu globals of the request under test.
		$submenu              = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$_wp_menu_nopriv      = array();
		$_wp_submenu_nopriv   = array();
		$_wp_real_parent_file = array();
		$_registered_pages    = array();
		$_parent_pages        = array();
		$admin_page_hooks     = array();
		$plugin_page          = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$parent_file          = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$pagenow              = 'post-new.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$typenow              = 'ccap_course'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		require ABSPATH . 'wp-admin/menu.php';
		foreach ( array( 'ccap_course', 'ccap_module', 'ccap_lesson' ) as $type ) {
			$typenow = $type; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			self::assertTrue( user_can_access_admin_page(), 'post-new.php?post_type=' . $type );
		}
		$typenow = 'post'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		self::assertFalse( user_can_access_admin_page(), 'core posts stay closed (the check itself works)' );
		$titles = array_column( (array) ( $submenu[ $_wp_real_parent_file[ EditorScreens::MENU ] ?? EditorScreens::MENU ] ?? array() ), 0 );
		self::assertContains( 'Add course', $titles );
		set_current_screen( 'front' );
	}

	/**
	 * GM-140: bundled notices, errors first, errors only snoozable, per user, ≤ 50 entries.
	 *
	 * @group GM-140
	 */
	public function test_GM_140_notices(): void {
		$user = $this->admin;
		Notices::add( 'b_info', 'info', 'Info text' );
		Notices::add( 'a_warning', 'warning', 'Warning text' );
		Notices::add( 'z_error', 'error', 'Error text' );
		self::assertSame( array( 'z_error', 'a_warning', 'b_info' ), array_keys( Notices::visible( $user ) ) );

		Notices::hide( $user, 'b_info', true );
		Notices::hide( $user, 'z_error', true, 1000 );
		self::assertSame( array( 'a_warning' ), array_keys( Notices::visible( $user, 2000 ) ) );
		self::assertSame( array( 'z_error', 'a_warning' ), array_keys( Notices::visible( $user, 1000 + Notices::SNOOZE ) ), 'error returns' );

		$other = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		self::assertCount( 3, Notices::visible( $other ), 'per user' );

		for ( $i = 0; $i < 60; $i++ ) {
			Notices::hide( $user, 'n' . $i, true );
		}
		self::assertLessThanOrEqual( Notices::MAX, count( (array) get_user_meta( $user, Notices::META, true ) ) );

		wp_set_current_user( $other );
		ob_start();
		Notices::render();
		$html = (string) ob_get_clean();
		self::assertSame( 1, substr_count( $html, 'class="notice ' ), 'one bundle' );
		self::assertLessThan( strpos( $html, 'Warning text' ), strpos( $html, 'Error text' ) );
		self::assertStringContainsString( 'Error:', $html, 'text label' );
		self::assertStringContainsString( '_wpnonce=', $html );
	}

	/**
	 * IDs of inputs/selects/textareas without a label.
	 *
	 * @param string $html HTML.
	 * @return list<string>
	 */
	private static function unlabelled( string $html ): array {
		$doc = new \DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();
		$labels = array();
		foreach ( $doc->getElementsByTagName( 'label' ) as $label ) {
			$labels[] = $label->getAttribute( 'for' );
		}
		$missing = array();
		foreach ( array( 'input', 'select', 'textarea' ) as $tag ) {
			foreach ( $doc->getElementsByTagName( $tag ) as $field ) {
				if ( 'hidden' === $field->getAttribute( 'type' ) ) {
					continue;
				}
				$wrapped = false;
				for ( $node = $field->parentNode; null !== $node; $node = $node->parentNode ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
					if ( 'label' === $node->nodeName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
						$wrapped = true;
						break;
					}
				}
				if ( ! $wrapped && ! in_array( $field->getAttribute( 'id' ), $labels, true ) ) {
					$missing[] = $tag . '#' . $field->getAttribute( 'id' ) . '[' . $field->getAttribute( 'name' ) . ']';
				}
			}
		}
		return $missing;
	}
}
