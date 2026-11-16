<?php
/**
 * Publish and archive courses; immutable course revisions (R0-06 §5, R0-08 §7.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Activity\ActivityRegistry;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Publishing writes a new row in `ccap_course_revision` with the frozen
 * structure (modules, lessons incl. WP revision ID and content hash,
 * order, required flags, rules, exam rule versions, access rules) and its
 * hash. Question revisions are not frozen here (E-50). A revision is never
 * changed afterwards, only its status (`published` → `superseded` |
 * `archived`). Publishing an unchanged structure creates no new revision.
 */
final class CoursePublisher {

	public const FORMAT_VERSION    = 1;
	public const CURRENT_REVISION  = '_ccap_current_revision_id';
	public const CHANGE_CLASSES    = array( 'patch', 'minor' );
	private const REVISION_PUBLISH = 'published';

	/**
	 * Re-entrance guard for the status hooks.
	 *
	 * @var bool
	 */
	private static bool $busy = false;

	/**
	 * Constructor.
	 *
	 * @param Database        $db        Database access.
	 * @param PolicyService   $policy    Policy.
	 * @param CourseStructure $structure Draft structure.
	 * @param ObjectIdentity  $identity  UIDs.
	 */
	public function __construct(
		private Database $db,
		private PolicyService $policy,
		private CourseStructure $structure,
		private ObjectIdentity $identity,
	) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, PolicyService::instance(), CourseStructure::current(), new ObjectIdentity( $db ) );
	}

	/**
	 * Register hooks: publishing or archiving through the block editor or
	 * WP-CLI also writes the revision (WordPress already checked the cap).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 10, 3 );
	}

	/**
	 * `transition_post_status` callback.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 * @return void
	 */
	public static function on_transition( $new_status, $old_status, $post ): void {
		if ( self::$busy || ! $post instanceof \WP_Post || Relations::COURSE_TYPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		if ( 'publish' === $new_status ) {
			self::current()->write_revision( (int) $post->ID, get_current_user_id(), 'minor', null );
		} elseif ( PostTypes::STATUS_ARCHIVED === $new_status ) {
			self::current()->mark_current( (int) $post->ID, 'archived' );
		}
	}

	/**
	 * Publish a course (M-04).
	 *
	 * @param Actor       $actor        Actor.
	 * @param int         $course_id    Course.
	 * @param string      $change_class patch|minor (Δ-23-2).
	 * @param string|null $notes        Author note shown to learners.
	 * @return int Revision ID.
	 * @throws CourseException When not allowed or invalid.
	 */
	public function publish( Actor $actor, int $course_id, string $change_class = 'minor', ?string $notes = null ): int {
		$this->authorize( $actor, $course_id );
		if ( ! in_array( $change_class, self::CHANGE_CLASSES, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown change class.', 400 );
		}
		$revision = $this->write_revision( $course_id, $actor->user_id, $change_class, $notes );
		if ( 'publish' !== get_post_status( $course_id ) ) {
			self::$busy = true;
			try {
				wp_update_post(
					array(
						'ID'          => $course_id,
						'post_status' => 'publish',
					)
				);
			} finally {
				self::$busy = false;
			}
		}
		return $revision;
	}

	/**
	 * Archive a course: not learnable or startable any more; running
	 * attempts may still be submitted (R0-23 RV-04).
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @return void
	 */
	public function archive( Actor $actor, int $course_id ): void {
		$this->authorize( $actor, $course_id );
		self::$busy = true;
		try {
			wp_update_post(
				array(
					'ID'          => $course_id,
					'post_status' => PostTypes::STATUS_ARCHIVED,
				)
			);
		} finally {
			self::$busy = false;
		}
		$this->mark_current( $course_id, 'archived' );
		\CourseCaptain\Cache\CacheStore::bump_course( $course_id, true );
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * Write a new revision if the structure changed; returns the current
	 * published revision ID.
	 *
	 * @param int         $course_id    Course.
	 * @param int         $user_id      Publishing user (0 = system).
	 * @param string      $change_class patch|minor.
	 * @param string|null $notes        Note.
	 * @return int
	 */
	public function write_revision( int $course_id, int $user_id, string $change_class, ?string $notes ): int {
		$structure = $this->build_structure( $course_id );
		$json      = (string) wp_json_encode( $structure );
		$hash      = 'sha256:' . hash( 'sha256', $json );
		$table     = $this->db->table( 'ccap_course_revision' );

		$revision = $this->db->transaction(
			function ( Database $db ) use ( $table, $course_id, $json, $hash, $user_id, $change_class, $notes ): int {
				$rows   = $db->rows( $db->prepare( "SELECT id, revision_no, status, structure_hash FROM `{$table}` WHERE course_id = %d ORDER BY revision_no DESC FOR UPDATE", $course_id ) );
				$latest = $rows[0] ?? null;
				if ( null !== $latest && self::REVISION_PUBLISH === $latest['status'] && $hash === $latest['structure_hash'] ) {
					return (int) $latest['id'];
				}
				$db->execute( $db->prepare( "UPDATE `{$table}` SET status = 'superseded' WHERE course_id = %d AND status IN ('published', 'archived')", $course_id ) );
				return $db->insert(
					'ccap_course_revision',
					array(
						'course_id'      => $course_id,
						'revision_no'    => null === $latest ? 1 : (int) $latest['revision_no'] + 1,
						'status'         => self::REVISION_PUBLISH,
						'change_class'   => $change_class,
						'notes'          => $notes,
						'structure'      => $json,
						'structure_hash' => $hash,
						'format_version' => self::FORMAT_VERSION,
						'published_by'   => 0 === $user_id ? null : $user_id,
						'published_at'   => $db->now(),
					)
				);
			}
		);
		update_post_meta( $course_id, self::CURRENT_REVISION, $revision );
		\CourseCaptain\Cache\CacheStore::bump_course( $course_id, true );
		do_action( 'course_captain_policy_changed' );
		return $revision;
	}

	/**
	 * Current published revision row of a course.
	 *
	 * @param int $course_id Course.
	 * @return array<string, mixed>|null
	 */
	public function current_revision( int $course_id ): ?array {
		$table = $this->db->table( 'ccap_course_revision' );
		return $this->db->row( $this->db->prepare( "SELECT * FROM `{$table}` WHERE course_id = %d AND status = 'published' ORDER BY revision_no DESC LIMIT 1", $course_id ) );
	}

	/**
	 * Set the status of the newest revision.
	 *
	 * @param int    $course_id Course.
	 * @param string $status    New status.
	 * @return void
	 */
	private function mark_current( int $course_id, string $status ): void {
		$table = $this->db->table( 'ccap_course_revision' );
		$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET status = %s WHERE course_id = %d AND status = 'published'", $status, $course_id ) );
	}

	/**
	 * The structure frozen at publication (format_version 1).
	 *
	 * @param int $course_id Course.
	 * @return array<string, mixed>
	 * @throws CourseException For unknown courses.
	 */
	public function build_structure( int $course_id ): array {
		$course = get_post( $course_id );
		if ( ! $course instanceof \WP_Post || Relations::COURSE_TYPE !== $course->post_type ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		$modules = array();
		foreach ( $this->structure->modules( $course_id ) as $assignment ) {
			$module = get_post( $assignment['module_id'] );
			if ( ! $module instanceof \WP_Post || 'publish' !== $module->post_status ) {
				continue;
			}
			$lessons = array();
			foreach ( $this->structure->lessons( $assignment['module_id'] ) as $lesson ) {
				if ( 'publish' !== $lesson->post_status ) {
					continue;
				}
				$lessons[] = array(
					'uid'            => $this->identity->ensure( $lesson ),
					'lesson_id'      => (int) $lesson->ID,
					'activity_uid'   => (string) get_post_meta( (int) $lesson->ID, ObjectIdentity::ACTIVITY_UID_META, true ),
					'activity_type'  => (string) get_post_meta( (int) $lesson->ID, ObjectIdentity::ACTIVITY_TYPE_META, true ),
					'activity'       => self::frozen_activity( (int) $lesson->ID ),
					'menu_order'     => (int) $lesson->menu_order,
					'required'       => true,
					'wp_revision_id' => self::latest_wp_revision( $lesson ),
					'content_hash'   => 'sha256:' . hash( 'sha256', $lesson->post_title . "\n" . $lesson->post_content ),
				);
			}
			$modules[] = array(
				'uid'        => $this->identity->ensure( $module ),
				'module_id'  => (int) $module->ID,
				'sort_order' => $assignment['sort_order'],
				'required'   => $assignment['required'],
				'rules'      => null === $assignment['rules'] ? null : json_decode( $assignment['rules'], true ),
				'lessons'    => $lessons,
			);
		}

		$rules_table  = $this->db->table( 'ccap_exam_rule' );
		$access_table = $this->db->table( 'ccap_access_rule' );
		$exam_rules   = $this->db->rows( $this->db->prepare( "SELECT id, uid, version FROM `{$rules_table}` WHERE course_id = %d AND status = 'published' ORDER BY id", $course_id ) );
		$access_rules = $this->db->rows( $this->db->prepare( "SELECT module_id, scope, access_type, limit_type, limit_value, valid_from, valid_until FROM `{$access_table}` WHERE course_id = %d ORDER BY id", $course_id ) );

		return array(
			'format_version' => self::FORMAT_VERSION,
			'course'         => array(
				'uid'      => $this->identity->ensure( $course ),
				'language' => CourseStructure::course_language( $course_id ),
			),
			'modules'        => $modules,
			'exam_rules'     => $exam_rules,
			'access_rules'   => $access_rules,
		);
	}

	/**
	 * Frozen activity definition (contract/settings version, settings hash,
	 * completion rule; R0-08 §2.3). Lessons without a type count as `lesson`.
	 *
	 * @param int $lesson_id Lesson.
	 * @return array<string, mixed>
	 */
	private static function frozen_activity( int $lesson_id ): array {
		$definition = ActivityService::definition( $lesson_id );
		$type       = '' === $definition['type'] ? 'lesson' : $definition['type'];
		return ActivityRegistry::instance()->freeze( $type, $definition['settings'], $definition['rule'] );
	}

	/**
	 * ID of the newest WordPress revision of a post (0 if none).
	 *
	 * @param \WP_Post $post Post.
	 * @return int
	 */
	private static function latest_wp_revision( \WP_Post $post ): int {
		$revisions = wp_get_post_revisions(
			$post,
			array(
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		$first     = reset( $revisions );
		if ( $first instanceof \WP_Post ) {
			return (int) $first->ID;
		}
		return false === $first ? 0 : (int) $first;
	}

	/**
	 * M-04 or exception.
	 *
	 * @param Actor $actor     Actor.
	 * @param int   $course_id Course.
	 * @return void
	 * @throws CourseException When denied.
	 */
	private function authorize( Actor $actor, int $course_id ): void {
		$decision = $this->policy->decide( $actor, PolicyService::PUBLISH_COURSE, array( $course_id ) );
		CourseException::unless_allowed( $decision, 'Not allowed to publish this course.' );
	}
}
