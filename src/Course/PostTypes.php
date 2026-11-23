<?php
/**
 * Registration of the own post types and post statuses (R0-33 §1.4, R0-08 §1.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Ops\Compatibility;
use CourseCaptain\Policy\MetaCaps;
use CourseCaptain\Policy\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * Five CPTs. Only the course has a public URL (E-197, slug `kurs` from
 * `course_captain_settings_general.values.course_slug`); modules, lessons,
 * FAQ and tips are editor-only. No archives, no comments, no feeds.
 */
final class PostTypes {

	public const DEFAULT_COURSE_SLUG = 'kurs';
	public const STATUS_ARCHIVED     = 'ccap_archived';
	public const STATUS_STAGED       = 'ccap_staged';

	/**
	 * `init` callback.
	 *
	 * @return void
	 */
	public static function register(): void {
		$caps = MetaCaps::post_type_capabilities();
		$base = array(
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_rest'        => true,
			'map_meta_cap'        => true,
			'capabilities'        => $caps,
			'supports'            => array( 'title', 'editor', 'revisions', 'author' ),
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => false,
			// Ownership marker (KOMP-131): a foreign type of the same name is never overwritten.
			'ccap_owner'          => true,
		);

		self::register_type(
			Relations::COURSE_TYPE,
			array(
				'labels'              => self::labels( __( 'Courses', 'course-captain' ), __( 'Course', 'course-captain' ), __( 'Add course', 'course-captain' ) ),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
				'rewrite'             => array(
					'slug'       => self::course_slug(),
					'with_front' => false,
					'feeds'      => false,
				),
				'query_var'           => Relations::COURSE_TYPE,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author' ),
			) + $base
		);
		self::register_type(
			Relations::MODULE_TYPE,
			array(
				'labels'       => self::labels( __( 'Modules', 'course-captain' ), __( 'Module', 'course-captain' ), __( 'Add module', 'course-captain' ) ),
				'hierarchical' => false,
			) + $base
		);
		self::register_type(
			Relations::LESSON_TYPE,
			array(
				'labels'   => self::labels( __( 'Lessons', 'course-captain' ), __( 'Lesson', 'course-captain' ), __( 'Add lesson', 'course-captain' ) ),
				'supports' => array( 'title', 'editor', 'revisions', 'author', 'page-attributes' ),
			) + $base
		);
		self::register_type( Relations::FAQ_TYPE, array( 'labels' => self::labels( __( 'Course FAQs', 'course-captain' ), __( 'Course FAQ', 'course-captain' ) ) ) + $base );
		self::register_type( Relations::TIP_TYPE, array( 'labels' => self::labels( __( 'Learning tips', 'course-captain' ), __( 'Learning tip', 'course-captain' ) ) ) + $base );

		register_post_status(
			self::STATUS_ARCHIVED,
			array(
				'label'                     => _x( 'Archived', 'post status', 'course-captain' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of items */
				'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'course-captain' ),
			)
		);
		register_post_status(
			self::STATUS_STAGED,
			array(
				'label'                     => _x( 'Staged', 'post status', 'course-captain' ),
				'public'                    => false,
				'internal'                  => true,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
			)
		);
	}

	/**
	 * Register unless a foreign type holds the slug (KOMP-131).
	 *
	 * @param string               $type Post type.
	 * @param array<string, mixed> $args Arguments.
	 * @phpstan-param lowercase-string&non-empty-string $type
	 * @return void
	 */
	private static function register_type( string $type, array $args ): void {
		if ( Compatibility::post_type_available( $type ) ) {
			register_post_type( $type, $args );
		}
	}

	/**
	 * Public course slug (validated on save by R1-31; here only a safe fallback).
	 *
	 * @return string
	 */
	public static function course_slug(): string {
		$general = get_option( 'course_captain_settings_general', array() );
		$slug    = is_array( $general ) && isset( $general['values']['course_slug'] ) ? (string) $general['values']['course_slug'] : '';
		return 1 === preg_match( '/^[a-z0-9][a-z0-9-]{0,48}[a-z0-9]$/', $slug ) ? $slug : self::DEFAULT_COURSE_SLUG;
	}

	/**
	 * Minimal label set.
	 *
	 * @param string $plural   Plural name.
	 * @param string $singular Singular name.
	 * @param string $add      "Add …" label (menu entry and editor title), empty = WordPress default.
	 * @return array<string, string>
	 */
	private static function labels( string $plural, string $singular, string $add = '' ): array {
		$labels = array(
			'name'          => $plural,
			'singular_name' => $singular,
			'menu_name'     => $plural,
		);
		if ( '' !== $add ) {
			$labels['add_new_item'] = $add;
		}
		return $labels;
	}
}
