<?php
/**
 * WordPress capability facade of the policy service (R0-07 §3.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * `map_meta_cap` filter: own meta capabilities and the post meta caps of
 * the own post types are answered by the PolicyService. Primitive
 * capabilities and foreign meta caps pass through unchanged.
 */
final class MetaCaps {

	/**
	 * Register the filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'map_meta_cap', array( self::class, 'map' ), 10, 4 );
	}

	/**
	 * `map_meta_cap` callback.
	 *
	 * @param mixed  $caps    Primitive caps WordPress computed.
	 * @param string $cap     Requested capability.
	 * @param int    $user_id User.
	 * @param mixed  $args    Arguments.
	 * @return mixed
	 */
	public static function map( $caps, $cap, $user_id, $args ) {
		$cap  = (string) $cap;
		$args = is_array( $args ) ? array_values( $args ) : array();

		if ( in_array( $cap, array( 'edit_post', 'delete_post', 'read_post', 'publish_post' ), true ) ) {
			return self::map_post_cap( $caps, $cap, (int) $user_id, $args );
		}
		if ( ! in_array( $cap, PolicyService::META, true ) ) {
			return $caps;
		}
		// `ccap_manage_learner_progress` without object is the primitive cap #16.
		if ( PolicyService::MANAGE_PROGRESS === $cap && array() === $args ) {
			return $caps;
		}
		$context = array();
		if ( isset( $args[1] ) && is_array( $args[1] ) ) {
			$context = $args[1];
			$args    = array( $args[0] );
		}
		$decision = PolicyService::instance()->decide( Actor::user( (int) $user_id ), $cap, $args, $context );
		return $decision->caps;
	}

	/**
	 * Post meta caps of the own post types (staff assignments count, not
	 * only `post_author`).
	 *
	 * @param mixed  $caps    WordPress result.
	 * @param string $cap     edit_post|delete_post|read_post|publish_post.
	 * @param int    $user_id User.
	 * @param array  $args    Arguments (post ID first).
	 * @phpstan-param list<mixed> $args
	 * @return mixed
	 */
	private static function map_post_cap( $caps, string $cap, int $user_id, array $args ) {
		$post = get_post( (int) ( $args[0] ?? 0 ) );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, Relations::POST_TYPES, true ) ) {
			return $caps;
		}
		$policy = PolicyService::instance();
		$actor  = Actor::user( $user_id );
		if ( 'read_post' === $cap && 'publish' === $post->post_status ) {
			// Public visibility of content is decided by the content gate (R1-14).
			return array( 'exist' );
		}
		if ( Relations::COURSE_TYPE === $post->post_type ) {
			$action = array(
				'edit_post'    => PolicyService::EDIT_COURSE,
				'read_post'    => PolicyService::EDIT_COURSE,
				'delete_post'  => PolicyService::DELETE_COURSE,
				'publish_post' => PolicyService::PUBLISH_COURSE,
			)[ $cap ];
			return $policy->decide( $actor, $action, array( (int) $post->ID ) )->caps;
		}
		$type     = substr( $post->post_type, strlen( 'ccap_' ) );
		$decision = $policy->decide( $actor, PolicyService::EDIT_COURSE_ITEM, array( (int) $post->ID, $type ) );
		if ( ! $decision->allowed ) {
			return $decision->caps;
		}
		$extra = array(
			'delete_post'  => array( 'ccap_delete_courses' ),
			'publish_post' => array( 'ccap_publish_courses' ),
		)[ $cap ] ?? array();
		return array_values( array_unique( array_merge( $decision->caps, $extra ) ) );
	}

	/**
	 * `capabilities` argument for `register_post_type` of the own types
	 * (R0-07 §3.2 CPT mapping; used from R1-04 on).
	 *
	 * @return array<string, string>
	 */
	public static function post_type_capabilities(): array {
		return array(
			'edit_posts'             => 'ccap_edit_courses',
			'edit_others_posts'      => 'ccap_edit_others_courses',
			'edit_published_posts'   => 'ccap_edit_courses',
			'edit_private_posts'     => 'ccap_edit_others_courses',
			'publish_posts'          => 'ccap_publish_courses',
			'read_private_posts'     => 'ccap_edit_others_courses',
			'delete_posts'           => 'ccap_delete_courses',
			'delete_others_posts'    => 'ccap_edit_others_courses',
			'delete_published_posts' => 'ccap_delete_courses',
			'delete_private_posts'   => 'ccap_edit_others_courses',
			'create_posts'           => 'ccap_edit_courses',
		);
	}
}
