<?php
/**
 * R1-04: course, module and lesson model (R0-08 §1, R0-06 §5, R0-33 §1.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseRelations;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Course\PostTypes;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Storage\Database;

/**
 * Course model against real tables.
 *
 * @group R1-04
 */
final class CourseModelTest extends SchemaTestCase {

	/**
	 * Administrator.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Course author (own course B only).
	 *
	 * @var int
	 */
	private int $author;

	/**
	 * Activate and create users.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->author = (int) self::factory()->user->create( array( 'role' => Roles::AUTHOR ) );
		PolicyService::flush();
	}

	/**
	 * New post of an own type.
	 *
	 * @param string $type      Post type.
	 * @param string $status    Status.
	 * @param int    $parent_id Parent.
	 * @param int    $order     Menu order.
	 * @param int    $author    Author (default admin).
	 * @return int
	 */
	private function post( string $type, string $status = 'publish', int $parent_id = 0, int $order = 0, int $author = 0 ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => $status,
				'post_parent'  => $parent_id,
				'menu_order'   => $order,
				'post_author'  => 0 === $author ? $this->admin : $author,
				'post_title'   => $type . ' ' . wp_generate_password( 4, false ),
				'post_content' => 'content',
			)
		);
	}

	/**
	 * R0-33 §1.4: only the course is public; no archives; own statuses.
	 */
	public function test_post_type_registration(): void {
		$course = get_post_type_object( 'ccap_course' );
		self::assertNotNull( $course );
		self::assertTrue( $course->public );
		self::assertFalse( $course->has_archive );
		self::assertSame( 'kurs', $course->rewrite['slug'] ?? null );
		foreach ( array( 'ccap_module', 'ccap_lesson', 'ccap_faq', 'ccap_tip' ) as $type ) {
			$object = get_post_type_object( $type );
			self::assertNotNull( $object, $type );
			self::assertFalse( $object->public, $type );
			self::assertFalse( $object->publicly_queryable, $type );
			self::assertTrue( $object->show_in_rest, $type );
			self::assertSame( 'ccap_edit_courses', $object->cap->edit_posts, $type );
		}
		self::assertNotNull( get_post_status_object( PostTypes::STATUS_ARCHIVED ) );
		self::assertFalse( get_post_status_object( PostTypes::STATUS_ARCHIVED )->public );
		self::assertNotNull( get_post_status_object( PostTypes::STATUS_STAGED ) );
	}

	/**
	 * R0-08 §1.2 / Δ-1 / E-67: one stable UID per live object, separate activity UID.
	 */
	public function test_stable_uids(): void {
		$identity = new ObjectIdentity( Database::current() );
		$course   = $this->post( 'ccap_course' );
		$module   = $this->post( 'ccap_module' );
		$lesson   = $this->post( 'ccap_lesson', 'publish', $module );
		$staged   = $this->post( 'ccap_lesson', PostTypes::STATUS_STAGED, $module );

		$uid = $identity->uid_of( 'course', $course );
		self::assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', (string) $uid );
		self::assertNotNull( $identity->uid_of( 'module', $module ) );
		$lesson_uid   = (string) $identity->uid_of( 'lesson', $lesson );
		$activity_uid = (string) get_post_meta( $lesson, ObjectIdentity::ACTIVITY_UID_META, true );
		self::assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', $activity_uid );
		self::assertNotSame( $lesson_uid, $activity_uid );
		self::assertNull( $identity->uid_of( 'lesson', $staged ), 'staging copies get no identity' );

		wp_update_post(
			array(
				'ID'         => $course,
				'post_title' => 'Renamed',
			)
		);
		self::assertSame( $uid, $identity->uid_of( 'course', $course ) );
		self::assertSame( $course, $identity->post_id_of( 'course', (string) $uid ) );
	}

	/**
	 * GM-001 / GM-003: catalog shows only published courses; draft and
	 * archive are neither learnable nor visible, preview only with M-03.
	 *
	 * @group GM-001
	 * @group GM-003
	 */
	public function test_GM_001_GM_003_catalog_and_status(): void {
		$course_a = $this->post( 'ccap_course' );
		$course_b = $this->post( 'ccap_course' );
		$course_c = $this->post( 'ccap_course', 'draft' );
		$course_d = $this->post( 'ccap_course' );
		CoursePublisher::current()->archive( Actor::user( $this->admin ), $course_d );

		$query = new \WP_Query(
			array(
				'post_type' => 'ccap_course',
				'orderby'   => 'ID',
				'order'     => 'ASC',
				'fields'    => 'ids',
			)
		);
		self::assertSame( array( $course_a, $course_b ), array_map( 'intval', $query->posts ) );

		$learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$policy  = PolicyService::instance();
		foreach ( array( $course_c, $course_d ) as $course ) {
			self::assertSame( 'ccap_not_found', $policy->decide( Actor::user( $learner ), PolicyService::LEARN, array( $course ) )->code );
			self::assertFalse( $policy->decide( Actor::user( $learner ), PolicyService::VIEW_COURSE, array( $course ) )->allowed );
			self::assertTrue( $policy->decide( Actor::user( $this->admin ), PolicyService::VIEW_COURSE, array( $course ) )->allowed, 'preview with M-03' );
		}
	}

	/**
	 * R0-08 §1.3: explicit order with gap values; ties resolved by ID; draft structure.
	 */
	public function test_module_assignment_and_order(): void {
		$structure = CourseStructure::current();
		$admin     = Actor::user( $this->admin );
		$course    = $this->post( 'ccap_course', 'draft' );
		$m1        = $this->post( 'ccap_module' );
		$m2        = $this->post( 'ccap_module' );
		$m3        = $this->post( 'ccap_module' );

		$structure->assign_module( $admin, $course, $m1 );
		$structure->assign_module( $admin, $course, $m2, null, false );
		$structure->assign_module( $admin, $course, $m3, 10 );
		self::assertSame( array( $m1, $m3, $m2 ), array_column( $structure->modules( $course ), 'module_id' ), 'tie on 10 → lower ID first' );
		self::assertSame( array( 10, 10, 20 ), array_column( $structure->modules( $course ), 'sort_order' ) );
		self::assertFalse( $structure->modules( $course )[2]['required'] );

		$structure->assign_module( $admin, $course, $m1, 30 );
		self::assertSame( array( $m3, $m2, $m1 ), array_column( $structure->modules( $course ), 'module_id' ) );
		$structure->remove_module( $admin, $course, $m2 );
		self::assertSame( array( $m3, $m1 ), array_column( $structure->modules( $course ), 'module_id' ) );

		$l2 = $this->post( 'ccap_lesson', 'publish', $m1, 20 );
		$l1 = $this->post( 'ccap_lesson', 'publish', $m1, 10 );
		self::assertSame( array( $l1, $l2 ), wp_list_pluck( $structure->lessons( $m1 ), 'ID' ) );

		try {
			$structure->assign_module( Actor::user( $this->author ), $course, $m2 );
			self::fail( 'foreign course' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_not_found', $e->error_code );
		}
	}

	/**
	 * R0-06 §5 / R0-08 §7.1: publishing freezes the structure; unchanged
	 * publish is idempotent; a change creates a new revision; old revisions
	 * never change.
	 */
	public function test_publish_creates_immutable_revisions(): void {
		$publisher = CoursePublisher::current();
		$structure = CourseStructure::current();
		$admin     = Actor::user( $this->admin );
		$course    = $this->post( 'ccap_course', 'draft' );
		$module    = $this->post( 'ccap_module' );
		$l1        = $this->post( 'ccap_lesson', 'publish', $module, 10 );
		$l2        = $this->post( 'ccap_lesson', 'publish', $module, 20 );
		$this->post( 'ccap_lesson', 'draft', $module, 30 );
		$structure->assign_module( $admin, $course, $module );

		$first = $publisher->publish( $admin, $course, 'minor', 'Initial' );
		self::assertSame( 'publish', get_post_status( $course ) );
		$row  = $publisher->current_revision( $course );
		$data = json_decode( (string) $row['structure'], true );
		self::assertSame( 1, (int) $row['revision_no'] );
		self::assertSame( 1, $data['format_version'] );
		self::assertSame( array( $l1, $l2 ), array_column( $data['modules'][0]['lessons'], 'lesson_id' ), 'draft lessons are not published' );
		self::assertSame( 'sha256:' . hash( 'sha256', (string) $row['structure'] ), $row['structure_hash'] );
		self::assertSame( $first, (int) get_post_meta( $course, CoursePublisher::CURRENT_REVISION, true ) );

		self::assertSame( $first, $publisher->publish( $admin, $course ), 'unchanged structure → no new revision' );

		wp_update_post(
			array(
				'ID'         => $l2,
				'menu_order' => 5,
			)
		);
		$second = $publisher->publish( $admin, $course, 'patch' );
		self::assertNotSame( $first, $second );
		$db    = Database::current();
		$table = $db->table( 'ccap_course_revision' );
		$old   = $db->row( $db->prepare( "SELECT status, structure FROM `{$table}` WHERE id = %d", $first ) );
		self::assertSame( 'superseded', $old['status'] );
		self::assertSame( (string) $row['structure'], (string) $old['structure'], 'old revision unchanged' );
		$new = json_decode( (string) $publisher->current_revision( $course )['structure'], true );
		self::assertSame( array( $l2, $l1 ), array_column( $new['modules'][0]['lessons'], 'lesson_id' ) );

		try {
			$publisher->publish( Actor::user( $this->author ), $course );
			self::fail( 'author without publish right' );
		} catch ( CourseException $e ) {
			self::assertContains( $e->error_code, array( 'ccap_not_found', 'ccap_forbidden' ) );
		}
	}

	/**
	 * Archive: not learnable any more; revision archived; publishing again reactivates.
	 */
	public function test_archive_and_republish(): void {
		$publisher = CoursePublisher::current();
		$admin     = Actor::user( $this->admin );
		$course    = $this->post( 'ccap_course' );
		$publisher->archive( $admin, $course );
		self::assertSame( PostTypes::STATUS_ARCHIVED, get_post_status( $course ) );
		self::assertNull( $publisher->current_revision( $course ) );

		$publisher->publish( $admin, $course );
		self::assertSame( 'publish', get_post_status( $course ) );
		self::assertSame( 2, (int) $publisher->current_revision( $course )['revision_no'] );
	}

	/**
	 * R0-06 §4.9: no permanent deletion under history; trash stays possible.
	 */
	public function test_deletion_guard(): void {
		$free = $this->post( 'ccap_course', 'draft' );
		self::assertNotFalse( wp_delete_post( $free, true ) );
		self::assertNull( get_post( $free ) );

		$course = $this->post( 'ccap_course' );
		$module = $this->post( 'ccap_module' );
		$lesson = $this->post( 'ccap_lesson', 'publish', $module );
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $course, $module );

		self::assertFalse( wp_delete_post( $course, true ) );
		self::assertFalse( wp_delete_post( $lesson, true ), 'lesson of a course with revision' );
		self::assertNotNull( get_post( $course ) );
		self::assertNotFalse( wp_trash_post( $course ) );
	}

	/**
	 * E-25a / E-297: bundles contain courses, one level, no self reference.
	 */
	public function test_bundle_relations(): void {
		$relations = CourseRelations::current();
		$admin     = Actor::user( $this->admin );
		$bundle    = $this->post( 'ccap_course' );
		$c1        = $this->post( 'ccap_course' );
		$c2        = $this->post( 'ccap_course' );
		$outer     = $this->post( 'ccap_course' );

		$relations->add( $admin, $bundle, $c1 );
		$relations->add( $admin, $bundle, $c2 );
		$relations->add( $admin, $bundle, $c1 );
		self::assertSame( array( $c1, $c2 ), $relations->contained( $bundle ) );
		self::assertSame( array( $bundle ), $relations->bundles_of( $c1 ) );

		foreach ( array( array( $bundle, $bundle ), array( $outer, $bundle ), array( $c1, $c2 ) ) as $pair ) {
			try {
				$relations->add( $admin, $pair[0], $pair[1] );
				self::fail( 'invalid relation accepted' );
			} catch ( CourseException $e ) {
				self::assertSame( 'ccap_bundle_invalid', $e->error_code );
			}
		}
		$relations->remove( $admin, $bundle, $c2 );
		self::assertSame( array( $c1 ), $relations->contained( $bundle ) );
	}

	/**
	 * LOC-001: course language fixed from the site locale at creation;
	 * lessons inherit it.
	 *
	 * @group LOC-001
	 */
	public function test_LOC_001_language(): void {
		$locale = static fn(): string => 'de_DE';
		add_filter( 'locale', $locale );
		$course = $this->post( 'ccap_course' );
		$module = $this->post( 'ccap_module' );
		$lesson = $this->post( 'ccap_lesson', 'publish', $module );
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $course, $module );
		remove_filter( 'locale', $locale );

		self::assertSame( 'de-DE', get_post_meta( $course, CourseStructure::LANGUAGE_META, true ) );
		self::assertSame( 'de-DE', CourseStructure::current()->effective_language( get_post( $lesson ) ) );
		self::assertSame( 'de-DE', CourseStructure::course_language( $course ), 'site locale change does not move the course' );
	}
}
