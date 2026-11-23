<?php
/**
 * Core hooks of the invalidation matrix and HTTP no-store for the namespace (R0-21 §3.5, §3.7).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Role/cap changes and site membership drop K2 of the user; every response of
 * `course-captain/v1` (all status codes, H-3) carries the H-X headers.
 */
final class CacheInvalidation {

	public const USER_HOOKS = array(
		'set_user_role',
		'add_user_role',
		'remove_user_role',
		'profile_update',
		'remove_user_from_blog',
		'add_user_to_blog',
		'deleted_user',
	);

	/**
	 * H-X headers (CDN, proxy and LiteSpeed signals).
	 *
	 * @var array<string, string>
	 */
	public const NO_STORE = array(
		'Cache-Control'             => 'no-store, private',
		'X-LiteSpeed-Cache-Control' => 'no-cache',
		'X-Accel-Expires'           => '0',
		'Surrogate-Control'         => 'no-store',
		'CDN-Cache-Control'         => 'no-store',
		'X-Robots-Tag'              => 'noindex, nofollow',
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( self::USER_HOOKS as $hook ) {
			add_action( $hook, array( self::class, 'user_changed' ), 10, 1 );
		}
		add_filter( 'rest_post_dispatch', array( self::class, 'rest_headers' ), 99, 3 );
		// Course/module index (GM-006): saved, trashed, restored or deleted posts.
		add_action( 'save_post', array( StructureCache::class, 'post_changed' ), 10, 1 );
		add_action( 'before_delete_post', array( StructureCache::class, 'post_changed' ), 10, 1 );
	}

	/**
	 * First hook argument is the user ID.
	 *
	 * @param mixed $user_id User ID.
	 * @return void
	 */
	public static function user_changed( $user_id ): void {
		CacheStore::bump_user( (int) $user_id );
	}

	/**
	 * No-store on every response of the own namespace and CC content types.
	 *
	 * @param mixed            $response Response.
	 * @param mixed            $server   Server.
	 * @param \WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function rest_headers( $response, $server, $request ) {
		unset( $server );
		if ( ! $response instanceof \WP_HTTP_Response || ! $request instanceof \WP_REST_Request ) {
			return $response;
		}
		$route = $request->get_route();
		if ( 0 === strpos( $route, '/course-captain/' ) || 1 === preg_match( '#^/wp/v2/ccap_#', $route ) ) {
			foreach ( self::NO_STORE as $name => $value ) {
				$response->header( $name, $value );
			}
		}
		return $response;
	}
}
