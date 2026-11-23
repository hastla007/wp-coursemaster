<?php
/**
 * Cached course and module index (R0-21 K1g; R0-05 GM-006).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Cache;

use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * All courses (any status except trash) with their modules in structure
 * order, loaded with exactly two queries and cached under the content
 * generation (K1g, no user context). The index carries no access decision
 * (K3 never goes through the cache): callers check access per learner.
 * Invalidation: course/module posts saved, deleted or moved, structure
 * changes (`CourseStructure`), imports (they use the same paths) and
 * {@see self::invalidate()} (after commit, T-5).
 */
final class StructureCache {

	public const CACHE_ID = 'structure:all';

	/**
	 * Courses with modules (cached).
	 *
	 * @return list<array{course_id: int, slug: string, title: string, status: string, menu_order: int, modules: list<array{module_id: int, slug: string, title: string, status: string, sort_order: int, required: bool}>}>
	 */
	public static function all(): array {
		$value = CacheStore::shared( self::CACHE_ID, array( self::class, 'load' ), null );
		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			return self::load();
		}
		/**
		 * Cached index (stored by load()).
		 *
		 * @var list<array{course_id: int, slug: string, title: string, status: string, menu_order: int, modules: list<array{module_id: int, slug: string, title: string, status: string, sort_order: int, required: bool}>}> $value
		 */
		return $value;
	}

	/**
	 * Load from the database: one query for the courses, one for all modules.
	 *
	 * @return list<array{course_id: int, slug: string, title: string, status: string, menu_order: int, modules: list<array{module_id: int, slug: string, title: string, status: string, sort_order: int, required: bool}>}>
	 */
	public static function load(): array {
		$db      = Database::current();
		$posts   = $db->wpdb()->posts;
		$courses = $db->rows(
			$db->prepare(
				"SELECT ID, post_name, post_title, post_status, menu_order FROM `{$posts}` WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft', 'inherit') ORDER BY menu_order, ID",
				Relations::COURSE_TYPE
			)
		);
		$links   = $db->rows(
			$db->prepare(
				'SELECT cm.course_id, cm.module_id, cm.sort_order, cm.required, p.post_name, p.post_title, p.post_status FROM `' . $db->table( 'ccap_course_module' ) . "` cm JOIN `{$posts}` p ON p.ID = cm.module_id AND p.post_type = %s ORDER BY cm.course_id, cm.sort_order, cm.module_id",
				Relations::MODULE_TYPE
			)
		);
		$modules = array();
		foreach ( $links as $row ) {
			$modules[ (int) $row['course_id'] ][] = array(
				'module_id'  => (int) $row['module_id'],
				'slug'       => (string) $row['post_name'],
				'title'      => (string) $row['post_title'],
				'status'     => (string) $row['post_status'],
				'sort_order' => (int) $row['sort_order'],
				'required'   => '1' === (string) $row['required'],
			);
		}
		$out = array();
		foreach ( $courses as $row ) {
			$id    = (int) $row['ID'];
			$out[] = array(
				'course_id'  => $id,
				'slug'       => (string) $row['post_name'],
				'title'      => (string) $row['post_title'],
				'status'     => (string) $row['post_status'],
				'menu_order' => (int) $row['menu_order'],
				'modules'    => $modules[ $id ] ?? array(),
			);
		}
		return $out;
	}

	/**
	 * Drop the index (new content generation after commit).
	 *
	 * @return void
	 */
	public static function invalidate(): void {
		CacheStore::bump_content();
	}

	/**
	 * Post hook: invalidate for course and module posts only.
	 *
	 * @param mixed $post_id Post ID.
	 * @return void
	 */
	public static function post_changed( $post_id ): void {
		$type = get_post_type( (int) $post_id );
		if ( Relations::COURSE_TYPE === $type || Relations::MODULE_TYPE === $type ) {
			self::invalidate();
		}
	}
}
