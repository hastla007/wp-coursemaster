<?php
/**
 * R1-37: course SEO and public/private boundaries (R0-33 SEO-0xx).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Seo\CourseSeo;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Policy\Actor;

/**
 * Robots, sitemaps, search, plugin filters, JSON-LD.
 *
 * @group R1-37
 */
final class SeoTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_option( 'blog_public', '1' );
		CourseSeo::flush();
	}

	/**
	 * Course post.
	 *
	 * @param string               $status Status.
	 * @param array<string, mixed> $meta   Meta.
	 * @return int
	 */
	private function course( string $status = 'publish', array $meta = array() ): int {
		$id = (int) self::factory()->post->create(
			array(
				'post_type'    => 'ccap_course',
				'post_status'  => $status,
				'post_title'   => 'Course ' . wp_generate_password( 4, false ),
				'post_excerpt' => 'Public summary',
				'post_content' => 'CANARY-BODY',
			)
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		CourseSeo::flush();
		return $id;
	}

	/**
	 * Robots directives for the current query.
	 *
	 * @return array<string, mixed>
	 */
	private static function robots(): array {
		return apply_filters( 'wp_robots', array( 'max-image-preview' => 'large' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
	}

	/**
	 * SEO-001 / SEO-002 / SEO-004 / SEO-005: indexability resolution (§1.3).
	 *
	 * @group SEO-001
	 * @group SEO-002
	 * @group SEO-004
	 * @group SEO-005
	 */
	public function test_indexability(): void {
		$public   = $this->course();
		$unlisted = $this->course( 'publish', array( CourseSeo::META_LISTING => 'unlisted' ) );
		$noindex  = $this->course( 'publish', array( CourseSeo::META_INDEX => 'noindex' ) );
		$override = $this->course( 'publish', array( CourseSeo::META_INDEX => 'index' ) );
		$draft    = $this->course( 'draft' );
		self::assertTrue( CourseSeo::indexable( $public ) );
		self::assertFalse( CourseSeo::indexable( $unlisted ) );
		self::assertFalse( CourseSeo::indexable( $noindex ) );
		self::assertFalse( CourseSeo::indexable( $draft ) );

		Settings::save( Actor::user( $this->admin ), 'general', array( 'catalog_indexing' => 'noindex' ), array( 'catalog_indexing' ), Settings::rev( 'general' ) );
		CourseSeo::flush();
		self::assertFalse( CourseSeo::indexable( $public ), 'global noindex' );
		self::assertTrue( CourseSeo::indexable( $override ), 'course override index (SEO-002)' );

		update_option( 'blog_public', '0' );
		CourseSeo::flush();
		self::assertFalse( CourseSeo::indexable( $override ), 'blog_public = 0 wins (SEO-004)' );

		update_option( 'blog_public', '1' );
		add_filter( 'course_captain_seo_indexable', '__return_false' );
		CourseSeo::flush();
		self::assertFalse( CourseSeo::indexable( $override ), 'filter restricts' );
		remove_filter( 'course_captain_seo_indexable', '__return_false' );
	}

	/**
	 * SEO-001 / SEO-003 / SEO-005: robots on course pages; CC only restricts (SG-03).
	 *
	 * @group SEO-001
	 * @group SEO-003
	 * @group SEO-005
	 */
	public function test_course_robots_and_plugin_filters(): void {
		$public   = $this->course();
		$unlisted = $this->course( 'publish', array( CourseSeo::META_LISTING => 'unlisted' ) );

		$this->go_to( get_permalink( $public ) );
		self::assertTrue( is_singular( 'ccap_course' ) );
		self::assertArrayNotHasKey( 'noindex', self::robots(), 'indexable course untouched' );
		self::assertSame( 'noindex, follow', CourseSeo::plugin_robots( 'noindex, follow' ), 'plugin noindex is never lifted (SG-03)' );
		self::assertSame( 'index, follow', CourseSeo::plugin_robots( 'index, follow' ) );

		$this->go_to( get_permalink( $unlisted ) );
		$robots = self::robots();
		self::assertTrue( $robots['noindex'] );
		self::assertTrue( $robots['follow'], 'S-N keeps follow' );
		self::assertSame( 'noindex, follow', CourseSeo::plugin_robots( 'index, follow' ), 'Yoast string restricted' );
		self::assertSame( 'noindex', CourseSeo::rank_math_robots( array( 'index' => 'index' ) )['index'], 'Rank Math restricted' );
	}

	/**
	 * SEO-009: private learning area – noindex, nofollow; no canonical; no OG presenters.
	 *
	 * @group SEO-009
	 */
	public function test_private_area(): void {
		set_query_var( LearningArea::QUERY_VAR, 'kurs/x' );
		$robots = self::robots();
		self::assertTrue( $robots['noindex'] );
		self::assertTrue( $robots['nofollow'] );
		self::assertArrayNotHasKey( 'max-image-preview', $robots );
		self::assertSame( 'noindex, nofollow', CourseSeo::plugin_robots( 'index, follow' ) );
		self::assertFalse( CourseSeo::plugin_canonical( 'https://example.org/x' ) );
		$og        = new class() {};
		$presenter = array( $og, new \ArrayObject() );
		self::assertCount( 2, CourseSeo::yoast_presenters( $presenter ), 'anonymous classes kept' );
		CourseSeo::headers();
		self::assertFalse( has_action( 'wp_head', 'rel_canonical' ), 'no canonical' );
		set_query_var( LearningArea::QUERY_VAR, null );
		add_action( 'wp_head', 'rel_canonical' );
	}

	/**
	 * SEO-010 / SEO-013: sitemaps (core, Yoast, Rank Math) only list indexable courses.
	 *
	 * @group SEO-010
	 * @group SEO-013
	 */
	public function test_sitemaps(): void {
		$public   = $this->course();
		$unlisted = $this->course( 'publish', array( CourseSeo::META_LISTING => 'unlisted' ) );
		$types    = CourseSeo::sitemap_post_types(
			array(
				'post'        => 1,
				'ccap_course' => 1,
				'ccap_lesson' => 1,
				'ccap_module' => 1,
			)
		);
		self::assertSame( array( 'post', 'ccap_course' ), array_keys( $types ) );
		$args = CourseSeo::sitemap_query_args( array(), 'ccap_course' );
		self::assertContains( $unlisted, $args['post__not_in'] );
		self::assertNotContains( $public, $args['post__not_in'] );
		self::assertTrue( CourseSeo::plugin_exclude_type( false, 'ccap_lesson' ) );
		self::assertFalse( CourseSeo::plugin_exclude_type( false, 'ccap_course' ) );
		self::assertContains( $unlisted, CourseSeo::excluded_ids( array( 5 ) ) );
		self::assertContains( (string) $unlisted, explode( ',', CourseSeo::rank_math_excluded( '5' ) ) );

		$provider = wp_sitemaps_get_server()->registry->get_provider( 'posts' );
		$subtypes = array_keys( $provider->get_object_subtypes() );
		self::assertNotContains( 'ccap_lesson', $subtypes );
		self::assertNotContains( 'ccap_module', $subtypes );
	}

	/**
	 * SEO-030 / SEO-031 / SEO-034: search and REST lists skip unlisted courses.
	 *
	 * @group SEO-030
	 * @group SEO-031
	 * @group SEO-034
	 */
	public function test_search_and_rest(): void {
		$public   = $this->course();
		$unlisted = $this->course( 'publish', array( CourseSeo::META_LISTING => 'unlisted' ) );
		$this->go_to( home_url( '/?s=Course' ) );
		$found = wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' );
		self::assertContains( $public, $found );
		self::assertNotContains( $unlisted, $found );
		$args = CourseSeo::rest_search( array(), null );
		self::assertContains( $unlisted, $args['post__not_in'] );
		wp_set_current_user( 0 );
		self::assertContains( $unlisted, CourseSeo::rest_course_list( array(), null )['post__not_in'] );
		wp_set_current_user( $this->admin );
		self::assertArrayNotHasKey( 'post__not_in', CourseSeo::rest_course_list( array(), null ), 'editors see unlisted' );
	}

	/**
	 * SEO-038 (LK-09): oEmbed never for lessons/modules or unlisted courses.
	 *
	 * @group SEO-038
	 */
	public function test_oembed(): void {
		$lesson = (int) self::factory()->post->create( array( 'post_type' => 'ccap_lesson' ) );
		self::assertSame( 0, CourseSeo::oembed( $lesson ) );
		$public = $this->course();
		self::assertSame( $public, CourseSeo::oembed( $public ) );
		self::assertSame( 0, CourseSeo::oembed( $this->course( 'publish', array( CourseSeo::META_LISTING => 'unlisted' ) ) ) );
		$post = (int) self::factory()->post->create();
		self::assertSame( $post, CourseSeo::oembed( $post ) );
	}

	/**
	 * §2 JSON-LD fallback: public fields only, never on non-indexable courses.
	 *
	 * @group SEO-025
	 */
	public function test_json_ld(): void {
		$public = $this->course( 'publish', array( '_ccap_language' => 'de_AT' ) );
		$schema = CourseSeo::schema( $public );
		self::assertSame( 'Course', $schema['@type'] );
		self::assertSame( 'Public summary', $schema['description'] );
		self::assertSame( 'de-AT', $schema['inLanguage'] );
		self::assertStringNotContainsString( 'CANARY', (string) wp_json_encode( $schema ) );
		self::assertArrayNotHasKey( 'aggregateRating', $schema );

		$this->go_to( get_permalink( $public ) );
		ob_start();
		CourseSeo::json_ld();
		self::assertStringContainsString( '"@type":"Course"', (string) ob_get_clean() );

		$this->go_to( get_permalink( $this->course( 'publish', array( CourseSeo::META_INDEX => 'noindex' ) ) ) );
		ob_start();
		CourseSeo::json_ld();
		self::assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * SEO-048 (LK-23): REST answers of the namespace carry X-Robots-Tag on all statuses.
	 *
	 * @group SEO-048
	 */
	public function test_rest_robots_header(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$request  = new \WP_REST_Request( 'GET', '/course-captain/v1/me/courses' );
		$response = apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertSame( 'noindex, nofollow', $response->get_headers()['X-Robots-Tag'] );
	}
}
