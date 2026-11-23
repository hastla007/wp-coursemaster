<?php
/**
 * R1-25: cache classes, generations, read protocol, after-commit invalidation, HTTP no-store (R0-21).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Cache\CacheStore;
use CourseCaptain\Event\Outbox;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use InvalidArgumentException;

/**
 * Real tables, WordPress object cache (non-persistent in the suite).
 *
 * @group R1-25
 */
final class CacheTest extends SchemaTestCase {

	/**
	 * Setup.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
	}

	/**
	 * CON-028: K2 needs a user scope; values never cross users.
	 *
	 * @group CON-028
	 */
	public function test_CON_028_user_scope(): void {
		self::assertSame( 'A', CacheStore::for_user( 11, 5, 'overview', static fn() => 'A' ) );
		self::assertSame( 'B', CacheStore::for_user( 12, 5, 'overview', static fn() => 'B' ) );
		self::assertSame( 'A', CacheStore::for_user( 11, 5, 'overview', static fn() => 'other' ), 'hit for A' );
		$this->expectException( InvalidArgumentException::class );
		CacheStore::for_user( 0, 5, 'overview', static fn() => 'x' );
	}

	/**
	 * K2 TTL bound and immutable K1 without generation.
	 */
	public function test_ttl_and_k1(): void {
		self::assertSame( 1, CacheStore::shared( 'qrev:1', static fn() => 1 ) );
		self::assertSame( 1, CacheStore::shared( 'qrev:1', static fn() => 2 ), 'K1 needs no invalidation' );
		self::assertStringNotContainsString( ':g', CacheStore::key( 'k1', 'shared', 'qrev:1', array() ) );
		$this->expectException( InvalidArgumentException::class );
		CacheStore::for_user( 1, 1, 'report', static fn() => 1, 901 );
	}

	/**
	 * CON-024: a writer bumping between generation read and cache write makes the stale key unreachable.
	 *
	 * @group CON-024
	 */
	public function test_CON_024_set_after_invalidate(): void {
		$stale = CacheStore::for_user(
			21,
			7,
			'progress',
			static function (): string {
				CacheStore::bump_user( 21 ); // Concurrent writer commits while the reader computes.
				return 'old';
			}
		);
		self::assertSame( 'old', $stale );
		self::assertSame( 'new', CacheStore::for_user( 21, 7, 'progress', static fn() => 'new' ), 'stored under the old generation' );
	}

	/**
	 * T-5: bumps run after commit and are dropped on rollback.
	 */
	public function test_bump_after_commit_only(): void {
		$db     = Database::current();
		$before = CacheStore::user_gen( 31 );
		$db->transaction(
			function () use ( $before ): void {
				CacheStore::bump_user( 31 );
				Database::current()->transaction( static fn() => CacheStore::bump_course( 99 ) );
				self::assertSame( $before, CacheStore::user_gen( 31 ), 'not before commit (also from another wrapper)' );
				self::assertTrue( Database::current()->in_transaction(), 'transaction state is per connection' );
			}
		);
		$after = CacheStore::user_gen( 31 );
		self::assertNotSame( $before, $after );
		try {
			$db->transaction(
				static function (): void {
					CacheStore::bump_user( 31 );
					throw new \RuntimeException( 'rollback' );
				}
			);
		} catch ( \RuntimeException $e ) {
			unset( $e );
		}
		self::assertSame( $after, CacheStore::user_gen( 31 ), 'dropped on rollback' );
	}

	/**
	 * CON-027: evicted generation token → miss, never a stale value.
	 *
	 * @group CON-027
	 */
	public function test_CON_027_evicted_generation(): void {
		CacheStore::for_user( 41, 3, 'x', static fn() => 'v1' );
		wp_cache_delete( 'gen:user:' . get_current_blog_id() . ':41', CacheStore::GROUP );
		self::assertSame( 'v2', CacheStore::for_user( 41, 3, 'x', static fn() => 'v2' ) );

		$course = (int) self::factory()->post->create( array( 'post_type' => 'ccap_course' ) );
		CacheStore::shared( 'structure', static fn() => 's1', $course );
		CacheStore::bump_course( $course );
		wp_cache_delete( 'gen:course:' . get_current_blog_id() . ':' . $course, CacheStore::GROUP );
		self::assertSame( 's2', CacheStore::shared( 'structure', static fn() => 's2', $course ), 'option/meta keeps the bumped token' );
	}

	/**
	 * CON-026: blog ID in every key.
	 *
	 * @group CON-026
	 */
	public function test_CON_026_site_in_key(): void {
		self::assertStringContainsString( ':' . get_current_blog_id() . ':k2:', CacheStore::key( 'k2', 'u1', 'x', array( 'a' ) ) );
	}

	/**
	 * Invalidation matrix: user events (Outbox meta), course publish purge (H-7), content generation.
	 *
	 * @group CON-047
	 */
	public function test_invalidation_matrix(): void {
		$db  = Database::current();
		$gen = CacheStore::user_gen( 51 );
		$db->transaction( static fn( Database $d ) => Outbox::record( $d, 'access.changed', 'grant', $d->uuid(), 1, array(), array( 'user_id' => 51 ) ) );
		self::assertNotSame( $gen, CacheStore::user_gen( 51 ), 'user event bumps user_gen' );

		$purged = array();
		add_action(
			'course_captain_cache_purge',
			static function ( array $p ) use ( &$purged ): void {
				$purged[] = $p;
			}
		);
		$course = (int) self::factory()->post->create( array( 'post_type' => 'ccap_course' ) );
		$cgen   = CacheStore::course_gen( $course );
		CacheStore::bump_course( $course, true );
		self::assertNotSame( $cgen, CacheStore::course_gen( $course ) );
		self::assertSame( 'course', $purged[0]['scope'] );
		self::assertSame( array( $course ), $purged[0]['ids'] );

		$content = CacheStore::content_gen();
		CacheStore::bump_content();
		self::assertNotSame( $content, CacheStore::content_gen() );
		self::assertSame( CacheStore::content_gen(), get_option( CacheStore::OPTION_GEN ) );

		$role = CacheStore::user_gen( 52 );
		do_action( 'set_user_role', 52, 'subscriber', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		self::assertNotSame( $role, CacheStore::user_gen( 52 ), 'role change' );
	}

	/**
	 * K1 question revisions: drafts never cached, status change after publish visible.
	 */
	public function test_question_revision_k1(): void {
		$admin = Actor::user( (int) self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$repo  = QuestionRepository::current();
		$data  = array(
			'question_type' => QuestionTypes::SINGLE,
			'question_text' => 'Q1',
			'answers'       => array(
				array(
					'key'     => 'A',
					'text'    => 'a',
					'correct' => true,
				),
				array(
					'key'  => 'B',
					'text' => 'b',
				),
			),
		);
		$id    = $repo->create( $admin, $data );
		$db    = Database::current();
		$draft = (int) $db->scalar( $db->prepare( 'SELECT MAX(id) FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE question_id = %d', $id ) );
		self::assertSame( 'draft', $repo->revision( $draft )['editorial_status'] );
		self::assertFalse( wp_cache_get( CacheStore::key( 'k1', 'shared', 'qrev:' . $draft, array() ), CacheStore::GROUP ), 'drafts are not cached' );
		$first = $repo->publish( $admin, $id );
		self::assertSame( 'published', $repo->revision( $first )['editorial_status'] );
		self::assertIsArray( wp_cache_get( CacheStore::key( 'k1', 'shared', 'qrev:' . $first, array() ), CacheStore::GROUP ) );

		$data['question_text'] = 'Q2';
		$repo->save( $admin, $id, $data );
		$second = $repo->publish( $admin, $id );
		self::assertNotSame( $first, $second );
		self::assertSame( 'deprecated', $repo->revision( $first )['editorial_status'], 'no stale status after publish' );
		self::assertSame( 'Q1', $repo->revision( $first )['question_text'] );
	}

	/**
	 * CON-034 / H-3: every status of the namespace carries the H-X headers.
	 *
	 * @group CON-034
	 */
	public function test_CON_034_headers_on_errors(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		foreach ( array( '/course-captain/v1/me/courses', '/course-captain/v1/exam-sessions/999999' ) as $route ) {
			$request  = new \WP_REST_Request( 'GET', $route );
			$response = apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
			self::assertContains( $response->get_status(), array( 401, 404 ) );
			$headers = $response->get_headers();
			self::assertSame( 'no-store, private', $headers['Cache-Control'] );
			self::assertSame( 'no-store', $headers['Surrogate-Control'] );
			self::assertSame( 'no-store', $headers['CDN-Cache-Control'] );
			self::assertSame( '0', $headers['X-Accel-Expires'] );
		}
		$request  = new \WP_REST_Request( 'GET', '/wp/v2/types' );
		$response = apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
		self::assertArrayNotHasKey( 'Surrogate-Control', $response->get_headers(), 'foreign routes untouched' );
	}
}
