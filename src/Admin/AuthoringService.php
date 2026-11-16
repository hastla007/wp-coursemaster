<?php
/**
 * Authoring operations behind the editor screens and REST routes (R1-05).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * One service for the classic screens and the REST routes, so both paths
 * share validation and policy decisions (no UI-only checks):
 *
 * - structure of a course (modules with order and "required"), replaced
 *   as a whole after validation (no duplicates, known modules, integer
 *   positions); the live draft changes, learners keep their revision;
 * - activity definition of a lesson (type, settings, completion rule);
 * - duplicate a module or lesson as a draft with `_ccap_copied_from_uid`
 *   and a fresh activity UID;
 * - publish a course with a change class (patch|minor) and a note.
 */
final class AuthoringService {

	public const COPIED_FROM_META = '_ccap_copied_from_uid';

	/**
	 * Constructor.
	 *
	 * @param Database      $db     Database access.
	 * @param PolicyService $policy Policy.
	 */
	public function __construct( private Database $db, private PolicyService $policy ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), PolicyService::instance() );
	}

	/**
	 * Structure of a course for the editor.
	 *
	 * @param Actor $actor     Author.
	 * @param int   $course_id Course.
	 * @return list<array{module_id: int, title: string, sort_order: int, required: bool, lessons: int}>
	 */
	public function structure( Actor $actor, int $course_id ): array {
		$this->authorize_course( $actor, $course_id );
		$out = array();
		foreach ( CourseStructure::current()->modules( $course_id ) as $row ) {
			$out[] = array(
				'module_id'  => $row['module_id'],
				'title'      => (string) get_the_title( $row['module_id'] ),
				'sort_order' => $row['sort_order'],
				'required'   => $row['required'],
				'lessons'    => count( CourseStructure::current()->lessons( $row['module_id'] ) ),
			);
		}
		return $out;
	}

	/**
	 * Replace the module list of a course.
	 *
	 * @param Actor $actor     Author.
	 * @param int   $course_id Course.
	 * @param array $items     Items `{module_id, sort_order?, required?}` in order.
	 * @phpstan-param list<array<string, mixed>> $items
	 * @return list<array{module_id: int, title: string, sort_order: int, required: bool, lessons: int}>
	 * @throws CourseException `ccap_validation_failed` with the offending entries.
	 */
	public function update_structure( Actor $actor, int $course_id, array $items ): array {
		$this->authorize_course( $actor, $course_id );
		$relations = new Relations( $this->db );
		$errors    = array();
		$seen      = array();
		$clean     = array();
		foreach ( array_values( $items ) as $i => $item ) {
			$module = isset( $item['module_id'] ) && is_numeric( $item['module_id'] ) ? (int) $item['module_id'] : 0;
			if ( $module <= 0 || null === $relations->post( $module, Relations::MODULE_TYPE ) ) {
				$errors[] = 'item_' . $i . ':unknown_module';
				continue;
			}
			if ( isset( $seen[ $module ] ) ) {
				$errors[] = 'item_' . $i . ':duplicate';
				continue;
			}
			$sort = $item['sort_order'] ?? ( ( $i + 1 ) * CourseStructure::SORT_STEP );
			if ( ! is_int( $sort ) && ! ( is_string( $sort ) && 1 === preg_match( '/^\d{1,9}$/', $sort ) ) ) {
				$errors[] = 'item_' . $i . ':sort_order';
				continue;
			}
			$seen[ $module ] = true;
			$clean[]         = array(
				'module_id'  => $module,
				'sort_order' => (int) $sort,
				'required'   => ! array_key_exists( 'required', $item ) || in_array( $item['required'], array( true, 1, '1', 'true', 'on' ), true ),
			);
		}
		if ( array() !== $errors ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', $errors ) ), 422 );
		}
		$structure = CourseStructure::current();
		$current   = array_column( $structure->modules( $course_id ), null, 'module_id' );
		foreach ( $clean as $item ) {
			$rules = $current[ $item['module_id'] ]['rules'] ?? null;
			$structure->assign_module( $actor, $course_id, $item['module_id'], $item['sort_order'], $item['required'], $rules );
		}
		foreach ( array_diff( array_keys( $current ), array_keys( $seen ) ) as $removed ) {
			$structure->remove_module( $actor, $course_id, (int) $removed );
		}
		return $this->structure( $actor, $course_id );
	}

	/**
	 * Define the activity of a lesson.
	 *
	 * @param Actor                $actor     Author.
	 * @param int                  $lesson_id Lesson.
	 * @param string               $type      Type.
	 * @param array<string, mixed> $settings  Settings.
	 * @param array<string, mixed> $rule      Completion rule.
	 * @return array{type: string, settings: array<string, mixed>, rule: array<string, mixed>}
	 */
	public function define_activity( Actor $actor, int $lesson_id, string $type, array $settings, array $rule = array() ): array {
		ActivityService::current()->define( $actor, $lesson_id, $type, $settings, $rule );
		return ActivityService::definition( $lesson_id );
	}

	/**
	 * Duplicate a module or lesson as a draft (`copied_from_uid`, new activity UID).
	 *
	 * @param Actor $actor   Author.
	 * @param int   $post_id Module or lesson.
	 * @return int New post ID.
	 * @throws CourseException 404 for other types, 403 without rights.
	 */
	public function duplicate( Actor $actor, int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, array( Relations::MODULE_TYPE, Relations::LESSON_TYPE ), true ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown item.', 404 );
		}
		$type = get_post_type_object( $post->post_type );
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'edit_post', $post_id ) || null === $type || ! user_can( $actor->user_id, (string) $type->cap->create_posts ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to duplicate.', 403 );
		}
		$uid  = ( new ObjectIdentity( $this->db ) )->ensure( $post );
		$copy = wp_insert_post(
			array(
				'post_type'    => $post->post_type,
				'post_status'  => 'draft',
				/* translators: %s: original title */
				'post_title'   => sprintf( __( '%s (copy)', 'course-captain' ), $post->post_title ),
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
				'post_parent'  => $post->post_parent,
				'menu_order'   => $post->menu_order,
				'post_author'  => $actor->user_id,
			),
			true
		);
		if ( is_wp_error( $copy ) ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( $copy->get_error_message() ), 422 );
		}
		add_post_meta( (int) $copy, self::COPIED_FROM_META, $uid, true );
		if ( Relations::LESSON_TYPE === $post->post_type ) {
			$definition = ActivityService::definition( $post_id );
			if ( '' !== $definition['type'] ) {
				ActivityService::current()->define( $actor, (int) $copy, $definition['type'], $definition['settings'], $definition['rule'] );
			}
		}
		return (int) $copy;
	}

	/**
	 * Publish a course revision.
	 *
	 * @param Actor       $actor        Author.
	 * @param int         $course_id    Course.
	 * @param string      $change_class patch|minor.
	 * @param string|null $notes        Note for learners.
	 * @return int Revision ID.
	 */
	public function publish( Actor $actor, int $course_id, string $change_class, ?string $notes = null ): int {
		$notes = null === $notes ? null : sanitize_textarea_field( $notes );
		return CoursePublisher::current()->publish( $actor, $course_id, $change_class, '' === $notes ? null : $notes );
	}

	/**
	 * Edit right on a course (M-03), 404 for unknown courses.
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @return void
	 */
	private function authorize_course( Actor $actor, int $course_id ): void {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $course_id ) ), 'Not allowed to edit this course.' );
	}
}
