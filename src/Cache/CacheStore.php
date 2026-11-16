<?php
/**
 * Generation-versioned object cache for K1/K1g/K2 data (R0-21 §3.1–§3.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Cache;

use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Schema;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Rules:
 * - K3 (access/policy decisions) and K4 (secrets) never go through here.
 * - Keys: `cc:v<schema>:<blog>:<class>:<scope>:<id>:g<gens>`; group
 *   `course_captain` plus the blog ID in the key (CON-026).
 * - Generations are random tokens; a missing token yields a new one, i.e. a
 *   miss and never a stale hit (CON-027). `content_gen` is backed by an option
 *   and `course_gen` by post meta, `user_gen` lives in the cache only.
 * - Read protocol (CON-024): read generations first, then the cache, compute
 *   on a miss and store under the key built from the generations read first.
 * - Bumps run after the commit of the surrounding transaction (T-5).
 */
final class CacheStore {

	public const GROUP       = 'course_captain';
	public const OPTION_GEN  = 'course_captain_cache_generation';
	public const META_GEN    = '_ccap_cache_gen';
	public const TTL_K1      = 86400;
	public const TTL_K2      = 300;
	public const TTL_REPORTS = 900;

	/**
	 * Shared value of an immutable revision (K1) or with course/content generation (K1g).
	 *
	 * @param string   $id      Identifier, e.g. `qrev:12`.
	 * @param callable $compute Computes the value on a miss (no user context!).
	 * @param int|null $course  Course for `course_gen`; null = `content_gen`; 0 = immutable (K1).
	 * @param int      $ttl     TTL in seconds.
	 * @return mixed
	 */
	public static function shared( string $id, callable $compute, ?int $course = 0, int $ttl = self::TTL_K1 ) {
		if ( 0 === $course ) {
			return self::remember( self::key( 'k1', 'shared', $id, array() ), $compute, $ttl );
		}
		$gens = null === $course ? array( self::content_gen() ) : array( self::course_gen( $course ) );
		return self::remember( self::key( 'k1g', 'shared', $id, $gens ), $compute, $ttl );
	}

	/**
	 * User-scoped, non-authoritative value (K2) with user and course generation.
	 *
	 * @param int      $user    User (required, CON-028).
	 * @param int      $course  Course (0 = content generation).
	 * @param string   $id      Identifier.
	 * @param callable $compute Computes the value on a miss.
	 * @param int      $ttl     TTL in seconds (≤ 900).
	 * @return mixed
	 * @throws InvalidArgumentException Without user or with a too long TTL.
	 */
	public static function for_user( int $user, int $course, string $id, callable $compute, int $ttl = self::TTL_K2 ) {
		if ( $user <= 0 ) {
			throw new InvalidArgumentException( 'K2 values need a user scope.' );
		}
		if ( $ttl > self::TTL_REPORTS ) {
			throw new InvalidArgumentException( 'K2 TTL above 900 s.' );
		}
		$gens = array( self::user_gen( $user ), $course > 0 ? self::course_gen( $course ) : self::content_gen() );
		return self::remember( self::key( 'k2', 'u' . $user . ':course:' . $course, $id, $gens ), $compute, $ttl );
	}

	/**
	 * Cache key.
	 *
	 * @param string        $tier  Class k1|k1g|k2.
	 * @param string        $scope Scope.
	 * @param string        $id    ID.
	 * @param array<string> $gens  Generations read before the cache.
	 * @return string
	 */
	public static function key( string $tier, string $scope, string $id, array $gens ): string {
		$key = 'cc:v' . Schema::VERSION . ':' . get_current_blog_id() . ':' . $tier . ':' . $scope . ':' . $id;
		return array() === $gens ? $key : $key . ':g' . implode( '.', $gens );
	}

	/**
	 * Read protocol with jittered TTL.
	 *
	 * @param string   $key     Key (generations already resolved).
	 * @param callable $compute Compute.
	 * @param int      $ttl     TTL.
	 * @return mixed
	 */
	private static function remember( string $key, callable $compute, int $ttl ) {
		$hit = wp_cache_get( $key, self::GROUP );
		if ( is_array( $hit ) && array_key_exists( 'v', $hit ) ) {
			return $hit['v'];
		}
		$value = $compute();
		wp_cache_set( $key, array( 'v' => $value ), self::GROUP, max( 1, (int) round( $ttl * ( 0.9 + wp_rand( 0, 200 ) / 1000 ) ) ) );
		return $value;
	}

	/**
	 * Global content generation.
	 *
	 * @return string
	 */
	public static function content_gen(): string {
		$gen = wp_cache_get( 'gen:content:' . get_current_blog_id(), self::GROUP );
		if ( is_string( $gen ) && '' !== $gen ) {
			return $gen;
		}
		$stored = get_option( self::OPTION_GEN );
		$gen    = is_string( $stored ) && '' !== $stored ? $stored : self::token();
		wp_cache_set( 'gen:content:' . get_current_blog_id(), $gen, self::GROUP );
		return $gen;
	}

	/**
	 * Course generation.
	 *
	 * @param int $course Course.
	 * @return string
	 */
	public static function course_gen( int $course ): string {
		$key = 'gen:course:' . get_current_blog_id() . ':' . $course;
		$gen = wp_cache_get( $key, self::GROUP );
		if ( is_string( $gen ) && '' !== $gen ) {
			return $gen;
		}
		$stored = get_post_meta( $course, self::META_GEN, true );
		$gen    = is_string( $stored ) && '' !== $stored ? $stored : self::token();
		wp_cache_set( $key, $gen, self::GROUP );
		return $gen;
	}

	/**
	 * User generation (cache only; eviction means a miss).
	 *
	 * @param int $user User.
	 * @return string
	 */
	public static function user_gen( int $user ): string {
		$key = 'gen:user:' . get_current_blog_id() . ':' . $user;
		$gen = wp_cache_get( $key, self::GROUP );
		if ( is_string( $gen ) && '' !== $gen ) {
			return $gen;
		}
		$gen = self::token();
		wp_cache_set( $key, $gen, self::GROUP );
		return $gen;
	}

	/**
	 * New content generation after commit.
	 *
	 * @return void
	 */
	public static function bump_content(): void {
		self::after_commit(
			static function (): void {
				$gen = self::token();
				update_option( self::OPTION_GEN, $gen, true );
				wp_cache_set( 'gen:content:' . get_current_blog_id(), $gen, self::GROUP );
			}
		);
	}

	/**
	 * New course generation after commit (+ page-cache purge, H-7).
	 *
	 * @param int  $course Course.
	 * @param bool $purge  Fire `course_captain_cache_purge` for public pages.
	 * @return void
	 */
	public static function bump_course( int $course, bool $purge = false ): void {
		if ( $course <= 0 ) {
			return;
		}
		self::after_commit(
			static function () use ( $course, $purge ): void {
				$gen = self::token();
				update_post_meta( $course, self::META_GEN, $gen );
				wp_cache_set( 'gen:course:' . get_current_blog_id() . ':' . $course, $gen, self::GROUP );
				if ( $purge ) {
					clean_post_cache( $course );
					$url = get_permalink( $course );
					/**
					 * Purge public pages of a course from page caches (R0-21 H-7).
					 *
					 * @param array{scope: string, ids: list<int>, urls: list<string>} $purge Scope, IDs, URLs.
					 */
					do_action(
						'course_captain_cache_purge',
						array(
							'scope' => 'course',
							'ids'   => array( $course ),
							'urls'  => false === $url ? array() : array( $url ),
						)
					);
				}
			}
		);
	}

	/**
	 * New user generation after commit.
	 *
	 * @param int $user User.
	 * @return void
	 */
	public static function bump_user( int $user ): void {
		if ( $user <= 0 ) {
			return;
		}
		self::after_commit(
			static function () use ( $user ): void {
				wp_cache_set( 'gen:user:' . get_current_blog_id() . ':' . $user, self::token(), self::GROUP );
			}
		);
	}

	/**
	 * Defer to the commit of the current transaction.
	 *
	 * @param callable $callback Callback.
	 * @return void
	 */
	private static function after_commit( callable $callback ): void {
		Database::current()->after_commit( $callback );
	}

	/**
	 * Random generation token.
	 *
	 * @return string
	 */
	private static function token(): string {
		return substr( bin2hex( random_bytes( 6 ) ), 0, 12 );
	}
}
