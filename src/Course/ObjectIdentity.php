<?php
/**
 * Stable UUIDs of CPT objects in ccap_source_map (R0-08 §1.2, Δ-1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Every live course, module, lesson, FAQ and tip gets exactly one row in
 * `ccap_source_map` (also local objects, `source_system = 'local'`). The UID
 * never changes and is never released. Lessons additionally carry their own
 * `activity_uid` (E-67). Staging copies get no row (Δ-23-4).
 */
final class ObjectIdentity {

	public const ACTIVITY_UID_META  = '_ccap_activity_uid';
	public const ACTIVITY_TYPE_META = '_ccap_activity_type';

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Register the save hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'wp_after_insert_post', array( self::class, 'on_save' ), 10, 2 );
	}

	/**
	 * `wp_after_insert_post` callback.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 * @return void
	 */
	public static function on_save( $post_id, $post ): void {
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, Relations::POST_TYPES, true ) ) {
			return;
		}
		if ( in_array( $post->post_status, array( 'auto-draft', PostTypes::STATUS_STAGED ), true ) || wp_is_post_revision( (int) $post_id ) ) {
			return;
		}
		( new self( Database::current() ) )->ensure( $post );
	}

	/**
	 * Create the identity row (and the activity UID of a lesson) if missing.
	 *
	 * @param \WP_Post $post Post.
	 * @return string UID.
	 */
	public function ensure( \WP_Post $post ): string {
		$type = self::object_type( $post->post_type );
		$uid  = $this->uid_of( $type, (int) $post->ID );
		if ( null === $uid ) {
			$uid   = $this->db->uuid();
			$table = $this->db->table( 'ccap_source_map' );
			$now   = $this->db->now();
			$this->db->execute(
				$this->db->prepare(
					"INSERT IGNORE INTO `{$table}` (uid, object_type, object_id, source_system, created_at, updated_at) VALUES (%s, %s, %d, 'local', %s, %s)",
					$uid,
					$type,
					(int) $post->ID,
					$now,
					$now
				)
			);
			$uid = (string) $this->uid_of( $type, (int) $post->ID );
		}
		// LOC-001: the course language is fixed at creation from the site locale (R0-25).
		if ( Relations::COURSE_TYPE === $post->post_type && '' === (string) get_post_meta( (int) $post->ID, CourseStructure::LANGUAGE_META, true ) ) {
			add_post_meta( (int) $post->ID, CourseStructure::LANGUAGE_META, CourseStructure::bcp47( get_locale() ), true );
		}
		if ( Relations::LESSON_TYPE === $post->post_type && '' === (string) get_post_meta( (int) $post->ID, self::ACTIVITY_UID_META, true ) ) {
			add_post_meta( (int) $post->ID, self::ACTIVITY_UID_META, $this->db->uuid(), true );
		}
		return $uid;
	}

	/**
	 * UID of an object.
	 *
	 * @param string $type    course|module|lesson|faq|tip.
	 * @param int    $post_id Post ID.
	 * @return string|null
	 */
	public function uid_of( string $type, int $post_id ): ?string {
		$table = $this->db->table( 'ccap_source_map' );
		return $this->db->scalar( $this->db->prepare( "SELECT uid FROM `{$table}` WHERE object_type = %s AND object_id = %d", $type, $post_id ) );
	}

	/**
	 * Post ID of a UID (only for the given type).
	 *
	 * @param string $type Object type.
	 * @param string $uid  UID.
	 * @return int|null
	 */
	public function post_id_of( string $type, string $uid ): ?int {
		$table = $this->db->table( 'ccap_source_map' );
		$value = $this->db->scalar( $this->db->prepare( "SELECT object_id FROM `{$table}` WHERE object_type = %s AND uid = %s", $type, $uid ) );
		return null === $value ? null : (int) $value;
	}

	/**
	 * Object type of a post type (`ccap_course` → `course`).
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function object_type( string $post_type ): string {
		return substr( $post_type, strlen( 'ccap_' ) );
	}
}
