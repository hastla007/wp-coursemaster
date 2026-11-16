<?php
/**
 * Course SEO and public/private boundaries (R0-33).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Seo;

use CourseCaptain\Policy\Relations;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One indexability function (§1.3) feeds robots, sitemaps (core, Yoast,
 * Rank Math), search, REST search and JSON-LD (SEO-040). Course Captain only
 * ever restricts (SG-03): it never removes a `noindex` set by WordPress, the
 * SEO plugin or the operator. Private areas (S-P) get `noindex, nofollow`
 * as meta and header, no canonical and no Open Graph; lessons, modules,
 * tips and FAQs have no public address (S-0).
 */
final class CourseSeo {

	public const META_LISTING = '_ccap_listing';
	public const META_INDEX   = '_ccap_seo_index';

	/**
	 * Request memo of indexability per course.
	 *
	 * @var array<int, bool>
	 */
	private static array $memo = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'wp_robots', array( self::class, 'robots' ), 99 );
		add_action( 'template_redirect', array( self::class, 'headers' ), 4 );
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'sitemap_query_args' ), 10, 2 );
		add_action( 'pre_get_posts', array( self::class, 'search' ) );
		add_filter( 'rest_post_search_query', array( self::class, 'rest_search' ), 10, 2 );
		add_filter( 'rest_ccap_course_query', array( self::class, 'rest_course_list' ), 10, 2 );
		add_filter( 'oembed_request_post_id', array( self::class, 'oembed' ), 10, 1 );
		add_action( 'wp_head', array( self::class, 'json_ld' ), 30 );
		add_action( 'course_captain_policy_changed', array( self::class, 'flush' ) );
		// Yoast SEO (§3.3) – inert without the plugin.
		add_filter( 'wpseo_robots', array( self::class, 'plugin_robots' ) );
		add_filter( 'wpseo_canonical', array( self::class, 'plugin_canonical' ) );
		add_filter( 'wpseo_sitemap_exclude_post_type', array( self::class, 'plugin_exclude_type' ), 10, 2 );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( self::class, 'excluded_ids' ) );
		add_filter( 'wpseo_frontend_presenters', array( self::class, 'yoast_presenters' ) );
		// Rank Math (§3.4).
		add_filter( 'rank_math/frontend/robots', array( self::class, 'rank_math_robots' ) );
		add_filter( 'rank_math/frontend/canonical', array( self::class, 'plugin_canonical' ) );
		add_filter( 'rank_math/sitemap/exclude_post_type', array( self::class, 'plugin_exclude_type' ), 10, 2 );
		add_filter( 'rank_math/sitemap/posts_to_exclude', array( self::class, 'rank_math_excluded' ) );
	}

	/**
	 * Forget memoized decisions.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$memo = array();
	}

	/**
	 * §1.3 `course_indexable`.
	 *
	 * @param int $course Course post ID.
	 * @return bool
	 */
	public static function indexable( int $course ): bool {
		if ( isset( self::$memo[ $course ] ) ) {
			return self::$memo[ $course ];
		}
		$post = get_post( $course );
		$ok   = $post instanceof \WP_Post
			&& Relations::COURSE_TYPE === $post->post_type
			&& 'publish' === $post->post_status
			&& empty( $post->post_password )
			&& self::listed( $course )
			&& '1' === (string) get_option( 'blog_public', '1' );
		if ( $ok ) {
			$own = (string) get_post_meta( $course, self::META_INDEX, true );
			$ok  = 'index' === $own || ( 'noindex' !== $own && 'public_catalog' === self::catalog_indexing() );
		}
		/**
		 * Restrict indexability of a course (only `false` has an effect beyond core rules).
		 *
		 * @param bool $indexable Indexable.
		 * @param int  $course    Course post ID.
		 */
		$ok                    = $ok && (bool) apply_filters( 'course_captain_seo_indexable', true, $course );
		self::$memo[ $course ] = $ok;
		return $ok;
	}

	/**
	 * Listed in catalog, search and REST lists (E-249).
	 *
	 * @param int $course Course.
	 * @return bool
	 */
	public static function listed( int $course ): bool {
		return 'unlisted' !== (string) get_post_meta( $course, self::META_LISTING, true );
	}

	/**
	 * Global catalog setting.
	 *
	 * @return string
	 */
	private static function catalog_indexing(): string {
		try {
			return (string) Settings::get( 'general.catalog_indexing' );
		} catch ( \Throwable $e ) {
			unset( $e );
			return 'public_catalog';
		}
	}

	/**
	 * Private learning area (S-P) on this request.
	 *
	 * @return bool
	 */
	public static function private_context(): bool {
		$path = get_query_var( LearningArea::QUERY_VAR, null );
		return null !== $path && false !== $path;
	}

	/**
	 * Non-indexable course on a singular request.
	 *
	 * @return bool
	 */
	private static function restricted_course(): bool {
		if ( ! is_singular( Relations::COURSE_TYPE ) ) {
			return false;
		}
		return ! self::indexable( (int) get_queried_object_id() );
	}

	/**
	 * `wp_robots`: only adds restrictions (SG-03).
	 *
	 * @param array<string, bool|string> $robots Directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( self::private_context() ) {
			unset( $robots['index'], $robots['follow'], $robots['max-image-preview'] );
			return array(
				'noindex'  => true,
				'nofollow' => true,
			) + $robots;
		}
		if ( self::restricted_course() ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			if ( empty( $robots['nofollow'] ) ) {
				$robots['follow'] = true;
			}
		}
		return $robots;
	}

	/**
	 * Header and canonical removal for private areas (S-P: meta **and** header, no canonical).
	 *
	 * @return void
	 */
	public static function headers(): void {
		if ( ! self::private_context() ) {
			return;
		}
		if ( ! headers_sent() ) {
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
	}

	/**
	 * Core sitemaps: only courses among own types.
	 *
	 * @param array<string, mixed> $types Post types.
	 * @return array<string, mixed>
	 */
	public static function sitemap_post_types( array $types ): array {
		foreach ( array_keys( $types ) as $type ) {
			if ( 0 === strpos( (string) $type, 'ccap_' ) && Relations::COURSE_TYPE !== $type ) {
				unset( $types[ $type ] );
			}
		}
		return $types;
	}

	/**
	 * Core sitemap query: drop non-indexable courses.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @param string               $type Post type.
	 * @return array<string, mixed>
	 */
	public static function sitemap_query_args( array $args, string $type ): array {
		if ( Relations::COURSE_TYPE === $type ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), self::excluded_ids( array() ) );
		}
		return $args;
	}

	/**
	 * IDs of published courses that are not indexable.
	 *
	 * @param mixed $ids Existing IDs.
	 * @return list<int>
	 */
	public static function excluded_ids( $ids ): array {
		$out = array_map( 'intval', (array) $ids );
		foreach ( get_posts(
			array(
				'post_type'        => Relations::COURSE_TYPE,
				'post_status'      => 'publish',
				'fields'           => 'ids',
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		) as $id ) {
			if ( ! self::indexable( (int) $id ) ) {
				$out[] = (int) $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Unlisted/archived courses never appear in the front-end search (LK-01).
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public static function search( $query ): void {
		if ( ! $query instanceof \WP_Query || is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}
		$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), self::unlisted_ids() ) );
	}

	/**
	 * REST `/wp/v2/search` (LK-02).
	 *
	 * @param array<string, mixed> $args    Query args.
	 * @param mixed                $request Request.
	 * @return array<string, mixed>
	 */
	public static function rest_search( array $args, $request ): array {
		unset( $request );
		$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), self::unlisted_ids() );
		return $args;
	}

	/**
	 * REST course list: unlisted courses only for editors (LK-05).
	 *
	 * @param array<string, mixed> $args    Query args.
	 * @param mixed                $request Request.
	 * @return array<string, mixed>
	 */
	public static function rest_course_list( array $args, $request ): array {
		unset( $request );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), self::unlisted_ids() );
		}
		return $args;
	}

	/**
	 * Unlisted course IDs.
	 *
	 * @return list<int>
	 */
	private static function unlisted_ids(): array {
		return array_values(
			array_map(
				'intval',
				get_posts(
					array(
						'post_type'        => Relations::COURSE_TYPE,
						'post_status'      => 'any',
						'fields'           => 'ids',
						'numberposts'      => -1,
						'suppress_filters' => true,
						'meta_key'         => self::META_LISTING, // phpcs:ignore WordPress.DB.SlowDBQuery -- Small set, admin-controlled flag.
					'meta_value'           => 'unlisted', // phpcs:ignore WordPress.DB.SlowDBQuery -- See above.
					)
				)
			)
		);
	}

	/**
	 * Embeds (oEmbed): only indexable or listed courses; never lessons, modules, tips, FAQs (LK-09).
	 *
	 * @param mixed $post_id Post ID.
	 * @return int
	 */
	public static function oembed( $post_id ): int {
		$post = get_post( (int) $post_id );
		if ( $post instanceof \WP_Post && 0 === strpos( $post->post_type, 'ccap_' ) && ( Relations::COURSE_TYPE !== $post->post_type || ! self::listed( (int) $post->ID ) ) ) {
			return 0;
		}
		return (int) $post_id;
	}

	/**
	 * Active SEO plugin mode (§3.1).
	 *
	 * AIOSEO, SEOPress and The SEO Framework count as `other` (KOMP-063):
	 * Course Captain then prints no own tags; robots stay on `wp_robots`.
	 *
	 * @return string yoast|rank_math|other|none
	 */
	public static function mode(): string {
		$plugin = \CourseCaptain\Ops\Compatibility::seo_plugin();
		if ( null === $plugin ) {
			return 'none';
		}
		return in_array( $plugin, array( 'yoast', 'rank_math' ), true ) ? $plugin : 'other';
	}

	/**
	 * Fallback `Course` JSON-LD without SEO plugin, only on indexable course pages (§2, §3.5).
	 *
	 * @return void
	 */
	public static function json_ld(): void {
		if ( 'none' !== self::mode() || ! is_singular( Relations::COURSE_TYPE ) ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( ! self::indexable( $id ) ) {
			return;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( self::schema( $id ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with HEX_TAG.
	}

	/**
	 * Schema.org `Course` from public fields only (no lesson content, no counts, no ratings).
	 *
	 * @param int $course Course.
	 * @return array<string, mixed>
	 */
	public static function schema( int $course ): array {
		$post        = get_post( $course );
		$description = $post instanceof \WP_Post && '' !== trim( $post->post_excerpt ) ? wp_strip_all_tags( $post->post_excerpt ) : '';
		$data        = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Course',
			'name'     => wp_strip_all_tags( get_the_title( $course ) ),
			'url'      => (string) get_permalink( $course ),
			'provider' => array(
				'@type' => 'Organization',
				'name'  => wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
				'url'   => home_url( '/' ),
			),
		);
		if ( '' !== $description ) {
			$data['description'] = $description;
		}
		$language = (string) get_post_meta( $course, '_ccap_language', true );
		if ( '' !== $language ) {
			$data['inLanguage'] = str_replace( '_', '-', $language );
		}
		return $data;
	}

	/**
	 * Yoast/Rank-Math robots string: restrict only.
	 *
	 * @param mixed $robots Robots string.
	 * @return mixed
	 */
	public static function plugin_robots( $robots ) {
		if ( self::private_context() ) {
			return 'noindex, nofollow';
		}
		if ( self::restricted_course() && is_string( $robots ) ) {
			$parts = array_filter( array_map( 'trim', explode( ',', $robots ) ), static fn( string $p ): bool => '' !== $p && 'index' !== $p && 'noindex' !== $p );
			return implode( ', ', array_merge( array( 'noindex' ), $parts ) );
		}
		return $robots;
	}

	/**
	 * Rank Math robots array.
	 *
	 * @param mixed $robots Robots map.
	 * @return mixed
	 */
	public static function rank_math_robots( $robots ) {
		if ( ! is_array( $robots ) ) {
			return $robots;
		}
		if ( self::private_context() ) {
			return array(
				'index'  => 'noindex',
				'follow' => 'nofollow',
			) + $robots;
		}
		if ( self::restricted_course() ) {
			$robots['index'] = 'noindex';
		}
		return $robots;
	}

	/**
	 * No canonical in private areas.
	 *
	 * @param mixed $canonical Canonical URL.
	 * @return mixed
	 */
	public static function plugin_canonical( $canonical ) {
		return self::private_context() ? false : $canonical;
	}

	/**
	 * Plugin sitemaps: exclude every own type except courses.
	 *
	 * @param mixed $exclude Exclude flag.
	 * @param mixed $type    Post type.
	 * @return mixed
	 */
	public static function plugin_exclude_type( $exclude, $type ) {
		if ( is_string( $type ) && 0 === strpos( $type, 'ccap_' ) && Relations::COURSE_TYPE !== $type ) {
			return true;
		}
		return $exclude;
	}

	/**
	 * Rank Math: comma list of excluded IDs.
	 *
	 * @param mixed $ids Comma list.
	 * @return string
	 */
	public static function rank_math_excluded( $ids ): string {
		$current = array_filter( array_map( 'intval', explode( ',', (string) $ids ) ) );
		return implode( ',', self::excluded_ids( $current ) );
	}

	/**
	 * Yoast presenters: drop Open Graph/Twitter/canonical/schema in private areas.
	 *
	 * @param mixed $presenters Presenter objects.
	 * @return mixed
	 */
	public static function yoast_presenters( $presenters ) {
		if ( ! self::private_context() || ! is_array( $presenters ) ) {
			return $presenters;
		}
		return array_values(
			array_filter(
				$presenters,
				static function ( $presenter ): bool {
					$class = is_object( $presenter ) ? get_class( $presenter ) : '';
					return 1 !== preg_match( '/Open_Graph|Twitter|Canonical|Schema|Slack/i', $class );
				}
			)
		);
	}
}
