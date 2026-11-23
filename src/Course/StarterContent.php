<?php
/**
 * Starter content seeder (R0-05 GM-189; E-16: mechanism in R1, texts with R2-03).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Lease;

defined( 'ABSPATH' ) || exit;

/**
 * Seeds editorial starter entries (course FAQ) idempotently:
 * - runs only in admin, cron and CLI requests, never in visitor requests
 *   (frontend, REST, Ajax);
 * - runs under the lease `starter-content` (a second run skips) and always
 *   releases it;
 * - every entry is identified by its stable key in `ccap_source_map`
 *   (`source_system = starter`, `source_id = <key>`): a second run creates no
 *   duplicate, a changed definition updates the entry, an entry edited on
 *   the site (post differs from what the seeder wrote) is left alone.
 * R1 ships no starter texts ({@see self::ITEMS} is empty, E-16); R2-03 adds them.
 */
final class StarterContent {

	public const SOURCE       = 'starter';
	public const LEASE        = 'starter-content';
	public const LEASE_TTL    = 300;
	public const STATE_OPTION = 'course_captain_starter_state';
	public const CONTEXTS     = array( 'admin', 'cron', 'cli' );

	/**
	 * Starter entries shipped with the plugin (none in R1, E-16).
	 *
	 * Shape: `key`, `course` (post ID), `question`, `answer`, `order`.
	 *
	 * @var list<array{key: string, course: int, question: string, answer: string, order?: int}>
	 */
	public const ITEMS = array();

	/**
	 * Automatic trigger (admin_init, hourly cron): only when the shipped set changed.
	 *
	 * @param string $context admin|cron|cli|frontend|rest|ajax.
	 * @return array<string, int|string>|null Result, or null when nothing to do.
	 */
	public static function maybe_run( string $context ): ?array {
		$items = self::items();
		if ( array() === $items ) {
			return null;
		}
		$version = hash( 'sha256', (string) wp_json_encode( $items ) );
		$state   = get_option( self::STATE_OPTION );
		if ( is_array( $state ) && ( $state['version'] ?? '' ) === $version ) {
			return null;
		}
		$result = self::seed( $items, $context );
		if ( 'done' === $result['status'] ) {
			update_option(
				self::STATE_OPTION,
				array(
					'version' => $version,
					'run_at'  => time(),
				),
				false
			);
		}
		return $result;
	}

	/**
	 * Shipped entries.
	 *
	 * @return list<array{key: string, course: int, question: string, answer: string, order?: int}>
	 */
	private static function items(): array {
		return self::ITEMS;
	}

	/**
	 * Seed a set of entries.
	 *
	 * @param list<array{key: string, course: int, question: string, answer: string, order?: int}> $items   Entries.
	 * @param string                                                                               $context Request context.
	 * @return array{status: string, created: int, updated: int, unchanged: int, skipped: int}
	 */
	public static function seed( array $items, string $context ): array {
		$result = array(
			'status'    => 'done',
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'skipped'   => 0,
		);
		if ( ! in_array( $context, self::CONTEXTS, true ) ) {
			$result['status'] = 'skipped_context';
			return $result;
		}
		$db    = Database::current();
		$lease = Lease::acquire( $db, self::LEASE, self::LEASE_TTL, false );
		if ( null === $lease ) {
			$result['status'] = 'skipped_locked';
			return $result;
		}
		try {
			foreach ( $items as $item ) {
				$outcome = self::seed_one( $db, $item );
				++$result[ $outcome ];
			}
		} finally {
			$lease->release();
		}
		/**
		 * Counters.
		 *
		 * @var array{status: string, created: int, updated: int, unchanged: int, skipped: int} $result
		 */
		return $result;
	}

	/**
	 * Request context of the current request.
	 *
	 * @return string
	 */
	public static function context(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return 'cli';
		}
		if ( wp_doing_cron() ) {
			return 'cron';
		}
		if ( wp_doing_ajax() ) {
			return 'ajax';
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return 'rest';
		}
		return is_admin() ? 'admin' : 'frontend';
	}

	/**
	 * Seed one entry.
	 *
	 * @param Database                                                                       $db   Database.
	 * @param array{key: string, course: int, question: string, answer: string, order?: int} $item Entry.
	 * @return string created|updated|unchanged|skipped
	 */
	private static function seed_one( Database $db, array $item ): string {
		$key    = (string) $item['key'];
		$course = get_post( (int) $item['course'] );
		if ( '' === $key || ! $course instanceof \WP_Post || Relations::COURSE_TYPE !== $course->post_type ) {
			return 'skipped';
		}
		$post_data  = array(
			'post_type'    => Relations::FAQ_TYPE,
			'post_status'  => 'publish',
			'post_parent'  => (int) $course->ID,
			'post_title'   => (string) $item['question'],
			'post_content' => (string) $item['answer'],
			'menu_order'   => (int) ( $item['order'] ?? 0 ),
		);
		$definition = 'sha256:' . hash( 'sha256', (string) wp_json_encode( $post_data ) );
		$map        = $db->table( 'ccap_source_map' );
		$known      = $db->rows( $db->prepare( "SELECT object_id, source_hash, last_hash FROM `{$map}` WHERE source_system = %s AND source_id = %s AND object_type = 'faq'", self::SOURCE, $key ) );
		$existing   = array() === $known ? null : get_post( (int) $known[0]['object_id'] );
		if ( $existing instanceof \WP_Post && Relations::FAQ_TYPE === $existing->post_type ) {
			if ( (string) $known[0]['source_hash'] === $definition ) {
				return 'unchanged';
			}
			if ( self::post_hash( $existing ) !== (string) $known[0]['last_hash'] ) {
				return 'skipped';
			}
			$id = wp_update_post( array( 'ID' => (int) $existing->ID ) + $post_data, true );
		} else {
			if ( array() !== $known ) {
				$db->execute( $db->prepare( "DELETE FROM `{$map}` WHERE source_system = %s AND source_id = %s AND object_type = 'faq'", self::SOURCE, $key ) );
			}
			$id = wp_insert_post( $post_data, true );
		}
		$post = is_wp_error( $id ) ? null : get_post( (int) $id );
		if ( ! $post instanceof \WP_Post ) {
			return 'skipped';
		}
		( new ObjectIdentity( $db ) )->ensure( $post );
		$db->execute(
			$db->prepare(
				"UPDATE `{$map}` SET source_system = %s, source_id = %s, source_hash = %s, last_hash = %s, imported_at = %s, updated_at = %s WHERE object_type = 'faq' AND object_id = %d",
				self::SOURCE,
				$key,
				$definition,
				self::post_hash( $post ),
				$db->now(),
				$db->now(),
				(int) $post->ID
			)
		);
		return $existing instanceof \WP_Post ? 'updated' : 'created';
	}

	/**
	 * Hash of the stored entry (detects edits on the site).
	 *
	 * @param \WP_Post $post FAQ post.
	 * @return string
	 */
	private static function post_hash( \WP_Post $post ): string {
		return 'sha256:' . hash( 'sha256', (string) wp_json_encode( array( $post->post_title, $post->post_content, (int) $post->post_parent, (int) $post->menu_order ) ) );
	}
}
