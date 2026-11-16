<?php
/**
 * Protected posts, pages and sections (R0-05 GM-107…GM-110, GM-116).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * A post or page with `_ccap_protected_course = <course>` shows its text
 * only to users with access to at least one module of the course (or the
 * right to edit the post). The protection covers every output path: the
 * content (late filter, after page builders), excerpts, feeds and the REST
 * API (`content`/`excerpt` emptied, no builder data). A shortcode/block
 * protects single sections. Protected pages are never page-cached
 * (`DONOTCACHEPAGE` + no-cache headers); object/DB caches stay untouched.
 * Decisions are memoized per request and user.
 */
final class ContentProtection {

	public const META      = '_ccap_protected_course';
	public const SHORTCODE = 'course_captain_protected';

	/**
	 * Memo: "user:course" → locked.
	 *
	 * @var array<string, bool>
	 */
	private static array $memo = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'the_content', array( self::class, 'filter_content' ), PHP_INT_MAX );
		add_filter( 'get_the_excerpt', array( self::class, 'filter_excerpt' ), PHP_INT_MAX, 2 );
		add_filter( 'the_content_feed', array( self::class, 'filter_content' ), PHP_INT_MAX );
		add_filter( 'the_excerpt_rss', array( self::class, 'filter_content' ), PHP_INT_MAX );
		add_action( 'rest_api_init', array( self::class, 'register_rest' ) );
		add_action( 'template_redirect', array( self::class, 'no_cache' ), 1 );
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
		add_action( 'course_captain_policy_changed', array( self::class, 'flush' ) );
		add_action( 'set_current_user', array( self::class, 'flush' ) );
	}

	/**
	 * Forget memoized decisions (rule or user change, GM-110).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$memo = array();
	}

	/**
	 * Whether a post is locked for a user.
	 *
	 * @param int $post_id Post.
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function is_locked( int $post_id, int $user_id ): bool {
		$course = (int) get_post_meta( $post_id, self::META, true );
		if ( $course <= 0 ) {
			return false;
		}
		if ( $user_id > 0 && user_can( $user_id, 'edit_post', $post_id ) ) {
			return false;
		}
		return self::course_locked( $course, $user_id );
	}

	/**
	 * Whether the course content is locked for a user (no accessible module).
	 *
	 * @param int $course  Course.
	 * @param int $user_id User.
	 * @return bool
	 */
	public static function course_locked( int $course, int $user_id ): bool {
		$key = $user_id . ':' . $course;
		if ( isset( self::$memo[ $key ] ) ) {
			return self::$memo[ $key ];
		}
		$locked = true;
		if ( $user_id > 0 ) {
			$db      = Database::current();
			$modules = $db->wpdb()->get_col( $db->prepare( 'SELECT module_id FROM `' . $db->table( 'ccap_course_module' ) . '` WHERE course_id = %d ORDER BY sort_order', $course ) );
			$gate    = ContentGate::current();
			foreach ( array_merge( array( 0 ), array_map( 'intval', $modules ) ) as $module ) {
				if ( $gate->module( Actor::user( $user_id ), $course, $module )->allowed() ) {
					$locked = false;
					break;
				}
			}
		}
		self::$memo[ $key ] = $locked;
		return $locked;
	}

	/**
	 * Notice shown instead of protected text.
	 *
	 * @return string
	 */
	public static function notice(): string {
		return '<p class="ccap-locked">' . esc_html__( 'This content is available to course participants.', 'course-captain' ) . '</p>';
	}

	/**
	 * `the_content`, feeds.
	 *
	 * @param mixed $content Content.
	 * @return mixed
	 */
	public static function filter_content( $content ) {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! self::is_locked( (int) $post->ID, get_current_user_id() ) ) {
			return $content;
		}
		return self::notice();
	}

	/**
	 * `get_the_excerpt`: no protected text in excerpts.
	 *
	 * @param mixed $excerpt Excerpt.
	 * @param mixed $post    Post.
	 * @return mixed
	 */
	public static function filter_excerpt( $excerpt, $post = null ) {
		$post = $post instanceof \WP_Post ? $post : get_post();
		if ( ! $post instanceof \WP_Post || ! self::is_locked( (int) $post->ID, get_current_user_id() ) ) {
			return $excerpt;
		}
		return wp_strip_all_tags( self::notice() );
	}

	/**
	 * REST: empty content/excerpt for every public post type.
	 *
	 * @return void
	 */
	public static function register_rest(): void {
		foreach ( get_post_types( array( 'show_in_rest' => true ) ) as $type ) {
			add_filter( 'rest_prepare_' . $type, array( self::class, 'filter_rest' ), PHP_INT_MAX, 2 );
		}
	}

	/**
	 * REST response filter.
	 *
	 * @param mixed $response Response.
	 * @param mixed $post     Post.
	 * @return mixed
	 */
	public static function filter_rest( $response, $post ) {
		if ( ! $response instanceof \WP_REST_Response || ! $post instanceof \WP_Post || ! self::is_locked( (int) $post->ID, get_current_user_id() ) ) {
			return $response;
		}
		$data = (array) $response->get_data();
		foreach ( array( 'content', 'excerpt' ) as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = array(
					'rendered'  => self::notice(),
					'protected' => true,
				);
			}
		}
		unset( $data['meta'], $data['block_data'] );
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Protected pages are not page-cached (GM-116).
	 *
	 * @return void
	 */
	public static function no_cache(): void {
		$post = get_queried_object();
		if ( ! is_singular() || ! $post instanceof \WP_Post || (int) get_post_meta( (int) $post->ID, self::META, true ) <= 0 ) {
			return;
		}
		self::mark_uncacheable();
	}

	/**
	 * `DONOTCACHEPAGE` and no-cache headers.
	 *
	 * @return void
	 */
	public static function mark_uncacheable(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Page-cache convention.
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * `[course_captain_protected course="ID"]…[/course_captain_protected]` (GM-109).
	 *
	 * @param mixed $atts    Attributes.
	 * @param mixed $content Enclosed content.
	 * @param mixed $tag     Shortcode tag.
	 * @return string
	 */
	public static function shortcode( $atts, $content = null, $tag = '' ): string {
		unset( $tag );
		$atts   = shortcode_atts( array( 'course' => 0 ), (array) $atts, self::SHORTCODE );
		$course = (int) $atts['course'];
		self::mark_uncacheable();
		if ( $course <= 0 || self::course_locked( $course, get_current_user_id() ) ) {
			return self::notice();
		}
		return do_shortcode( (string) $content );
	}
}
