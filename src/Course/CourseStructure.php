<?php
/**
 * Course structure: modules, lessons, order, language (R0-08 §1.1–§1.3, R0-25).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Draft structure of a course. Learners always see the structure of the
 * published course revision (CoursePublisher), never this draft.
 */
final class CourseStructure {

	public const LANGUAGE_META = '_ccap_language';
	public const SORT_STEP     = 10;

	/**
	 * Constructor.
	 *
	 * @param Database      $db       Database access.
	 * @param PolicyService $policy   Policy.
	 * @param Relations     $relations Relations.
	 */
	public function __construct( private Database $db, private PolicyService $policy, private Relations $relations ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, PolicyService::instance(), new Relations( $db ) );
	}

	/**
	 * Add a module to a course or update its position/required flag.
	 *
	 * @param Actor       $actor      Actor.
	 * @param int         $course_id  Course.
	 * @param int         $module_id  Module.
	 * @param int|null    $sort_order Position; null = append (gap values 10, 20, …).
	 * @param bool        $required   Required module.
	 * @param string|null $rules      Rules JSON (prerequisites etc.).
	 * @return void
	 * @throws CourseException When not allowed or the objects do not exist.
	 */
	public function assign_module( Actor $actor, int $course_id, int $module_id, ?int $sort_order = null, bool $required = true, ?string $rules = null ): void {
		$this->authorize( $actor, $course_id );
		if ( null === $this->relations->post( $module_id, Relations::MODULE_TYPE ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown module.', 404 );
		}
		if ( null !== $rules && null === json_decode( $rules, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Rules must be JSON.', 400 );
		}
		$table = $this->db->table( 'ccap_course_module' );
		if ( null === $sort_order ) {
			$max        = $this->db->scalar( $this->db->prepare( "SELECT MAX(sort_order) FROM `{$table}` WHERE course_id = %d", $course_id ) );
			$sort_order = ( null === $max ? 0 : (int) $max ) + self::SORT_STEP;
		}
		$this->db->execute(
			$this->db->prepare(
				"INSERT INTO `{$table}` (course_id, module_id, sort_order, required, rules) VALUES (%d, %d, %d, %d, %s) ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), required = VALUES(required), rules = VALUES(rules)",
				$course_id,
				$module_id,
				$sort_order,
				$required ? 1 : 0,
				(string) $rules
			)
		);
		if ( null === $rules ) {
			$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET rules = NULL WHERE course_id = %d AND module_id = %d", $course_id, $module_id ) );
		}
		\CourseCaptain\Cache\StructureCache::invalidate();
	}

	/**
	 * Remove a module from the draft structure. Published learners keep the
	 * structure of their revision until the next publication (R0-23).
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @param int   $module_id Module.
	 * @return void
	 */
	public function remove_module( Actor $actor, int $course_id, int $module_id ): void {
		$this->authorize( $actor, $course_id );
		$table = $this->db->table( 'ccap_course_module' );
		$this->db->execute( $this->db->prepare( "DELETE FROM `{$table}` WHERE course_id = %d AND module_id = %d", $course_id, $module_id ) );
		\CourseCaptain\Cache\StructureCache::invalidate();
	}

	/**
	 * Modules of a course in order (sort_order, then ID for ties).
	 *
	 * @param int $course_id Course.
	 * @return list<array{module_id: int, sort_order: int, required: bool, rules: string|null}>
	 */
	public function modules( int $course_id ): array {
		$table = $this->db->table( 'ccap_course_module' );
		$rows  = $this->db->rows( $this->db->prepare( "SELECT module_id, sort_order, required, rules FROM `{$table}` WHERE course_id = %d ORDER BY sort_order, module_id", $course_id ) );
		$out   = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'module_id'  => (int) $row['module_id'],
				'sort_order' => (int) $row['sort_order'],
				'required'   => '1' === (string) $row['required'],
				'rules'      => null === $row['rules'] ? null : (string) $row['rules'],
			);
		}
		return $out;
	}

	/**
	 * Live lessons of a module in order (menu_order, then ID).
	 *
	 * @param int $module_id Module.
	 * @return list<\WP_Post>
	 */
	public function lessons( int $module_id ): array {
		$posts = get_posts(
			array(
				'post_type'        => Relations::LESSON_TYPE,
				'post_parent'      => $module_id,
				'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'orderby'          => array(
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				),
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		);
		return array_values( array_filter( $posts, static fn( $post ): bool => $post instanceof \WP_Post ) );
	}

	/**
	 * Language of a course (BCP 47), default from the site locale (R0-25, LOC-001).
	 *
	 * @param int $course_id Course.
	 * @return string
	 */
	public static function course_language( int $course_id ): string {
		$own = (string) get_post_meta( $course_id, self::LANGUAGE_META, true );
		return '' !== $own ? $own : self::bcp47( get_locale() );
	}

	/**
	 * Effective language of any course object: own value, else the course
	 * (lessons via their module's first course in ID order).
	 *
	 * @param \WP_Post $post Course object.
	 * @return string
	 */
	public function effective_language( \WP_Post $post ): string {
		$own = (string) get_post_meta( (int) $post->ID, self::LANGUAGE_META, true );
		if ( '' !== $own ) {
			return $own;
		}
		$courses = $this->relations->courses_of_item( $post );
		return array() === $courses ? self::bcp47( get_locale() ) : self::course_language( $courses[0] );
	}

	/**
	 * WordPress locale → BCP 47 (`de_DE` → `de-DE`).
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function bcp47( string $locale ): string {
		return str_replace( '_', '-', $locale );
	}

	/**
	 * M-03 on the course or exception.
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @return void
	 * @throws CourseException When denied.
	 */
	private function authorize( Actor $actor, int $course_id ): void {
		$decision = $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $course_id ) );
		CourseException::unless_allowed( $decision, 'Not allowed to edit this course.' );
	}
}
