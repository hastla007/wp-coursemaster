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
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Publish\CourseValidator;
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
	 * Put a lesson into a module (R0-11 Flow 1 "Modul anlegen → Lektion
	 * anlegen"; lessons hang below their module via `post_parent`). The
	 * live draft changes; learners keep their published revision. The
	 * order inside the module stays the lesson's "Order" (menu_order).
	 *
	 * @param Actor $actor     Author.
	 * @param int   $lesson_id Lesson.
	 * @param int   $module_id Module.
	 * @return void
	 * @throws CourseException 404 for an unknown lesson or module, 403 without the edit right on both.
	 */
	public function move_lesson( Actor $actor, int $lesson_id, int $module_id ): void {
		$relations = new Relations( $this->db );
		$lesson    = $relations->post( $lesson_id, Relations::LESSON_TYPE );
		$module    = $relations->post( $module_id, Relations::MODULE_TYPE );
		if ( null === $lesson || null === $module ) {
			throw new CourseException( 'ccap_not_found', 'Unknown lesson or module.', 404 );
		}
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'edit_post', $lesson_id ) || ! user_can( $actor->user_id, 'edit_post', $module_id ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to move this lesson.', 403 );
		}
		if ( (int) $lesson->post_parent === $module_id ) {
			return;
		}
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_parent' => $module_id ), array( 'ID' => $lesson_id ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Parent only; a nested wp_update_post() would re-run the save hooks of the editor request.
		clean_post_cache( $lesson_id );
		\CourseCaptain\Cache\StructureCache::invalidate();
		PolicyService::flush();
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
	 * Publish a course revision through the validator (R0-22 §2.4/§2.5):
	 * blockers stop publishing, every warning needs an acknowledgement of
	 * its fingerprint; the acknowledged fingerprints are audited.
	 *
	 * @param Actor       $actor        Author.
	 * @param int         $course_id    Course.
	 * @param string      $change_class patch|minor.
	 * @param string|null $notes        Note for learners.
	 * @param array       $acknowledged Acknowledged warning fingerprints.
	 * @phpstan-param list<string> $acknowledged
	 * @return int Revision ID.
	 * @throws CourseException `ccap_validation_failed` (422) or `ccap_validation_unacknowledged` (409).
	 */
	public function publish( Actor $actor, int $course_id, string $change_class, ?string $notes = null, array $acknowledged = array() ): int {
		$this->authorize_course( $actor, $course_id );
		if ( ! in_array( $change_class, CoursePublisher::CHANGE_CLASSES, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown change class.', 400 );
		}
		$report = CourseValidator::validate( $course_id, 'publish', $acknowledged );
		if ( $report['summary']['blocker'] > 0 ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', array_unique( array_column( array_filter( $report['issues'], static fn( array $i ): bool => 'blocker' === $i['severity'] ), 'code' ) ) ) ), 422 );
		}
		if ( ! CourseValidator::publishable( $report ) ) {
			throw new CourseException( 'ccap_validation_unacknowledged', 'warnings', 409 );
		}
		$notes    = null === $notes ? null : sanitize_textarea_field( $notes );
		$revision = CoursePublisher::current()->publish( $actor, $course_id, $change_class, '' === $notes ? null : $notes );
		AuditLog::write(
			$this->db,
			$actor,
			'course_published',
			'course',
			$report['course_uid'],
			null,
			array(
				'revision_id'           => $revision,
				'change_class'          => $change_class,
				'acknowledged_warnings' => array_values( array_column( array_filter( $report['issues'], static fn( array $i ): bool => ! empty( $i['acknowledged'] ) ), 'fingerprint' ) ),
			)
		);
		return $revision;
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
