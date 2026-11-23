<?php
/**
 * Object relations B-1…B-9 for policy decisions (R0-07 §3.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only lookups of the current site. Everything is loaded from
 * `$wpdb->prefix` of the current site only (site scope, R0-07 §3.4).
 */
final class Relations {

	public const COURSE_TYPE = 'ccap_course';
	public const MODULE_TYPE = 'ccap_module';
	public const LESSON_TYPE = 'ccap_lesson';
	public const FAQ_TYPE    = 'ccap_faq';
	public const TIP_TYPE    = 'ccap_tip';

	/**
	 * Own post types (E-207).
	 *
	 * @var list<string>
	 */
	public const POST_TYPES = array( self::COURSE_TYPE, self::MODULE_TYPE, self::LESSON_TYPE, self::FAQ_TYPE, self::TIP_TYPE );

	public const STATUS_PUBLISHED = 'published';
	public const STATUS_DRAFT     = 'draft';
	public const STATUS_ARCHIVED  = 'archived';

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Post of an own type, or null.
	 *
	 * @param int         $id   Post ID.
	 * @param string|null $type Required post type.
	 * @return \WP_Post|null
	 */
	public function post( int $id, ?string $type = null ): ?\WP_Post {
		if ( $id <= 0 ) {
			return null;
		}
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, self::POST_TYPES, true ) ) {
			return null;
		}
		if ( null !== $type && $type !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * Lifecycle status of a course post (R0-07 §3.4).
	 *
	 * @param \WP_Post $course Course post.
	 * @return string published|draft|archived
	 */
	public static function course_status( \WP_Post $course ): string {
		if ( 'publish' === $course->post_status ) {
			return self::STATUS_PUBLISHED;
		}
		if ( 'ccap_archived' === $course->post_status || 'trash' === $course->post_status ) {
			return self::STATUS_ARCHIVED;
		}
		return self::STATUS_DRAFT;
	}

	/**
	 * B-1 own course or B-2 assigned author.
	 *
	 * @param int $user_id   User.
	 * @param int $course_id Course.
	 * @return bool
	 */
	public function is_course_author( int $user_id, int $course_id ): bool {
		$course = $this->post( $course_id, self::COURSE_TYPE );
		if ( null === $course || $user_id <= 0 ) {
			return false;
		}
		return (int) $course->post_author === $user_id || $this->has_staff_role( $user_id, $course_id, 'author' );
	}

	/**
	 * B-2 staff assignment with a role (any group scope).
	 *
	 * @param int    $user_id   User.
	 * @param int    $course_id Course.
	 * @param string $role      author|trainer.
	 * @return bool
	 */
	public function has_staff_role( int $user_id, int $course_id, string $role ): bool {
		$table = $this->db->table( 'ccap_course_staff' );
		return null !== $this->db->scalar(
			$this->db->prepare( "SELECT 1 FROM `{$table}` WHERE course_id = %d AND user_id = %d AND role = %s LIMIT 1", $course_id, $user_id, $role )
		);
	}

	/**
	 * Group scopes of a trainer assignment: [] = not assigned, [0] = whole
	 * course, otherwise the assigned group IDs (B-2/B-3).
	 *
	 * @param int $user_id   Trainer.
	 * @param int $course_id Course.
	 * @return list<int>
	 */
	public function trainer_groups( int $user_id, int $course_id ): array {
		$table  = $this->db->table( 'ccap_course_staff' );
		$rows   = $this->db->rows(
			$this->db->prepare( "SELECT group_id FROM `{$table}` WHERE course_id = %d AND user_id = %d AND role = 'trainer'", $course_id, $user_id )
		);
		$groups = array_map( static fn( array $row ): int => (int) $row['group_id'], $rows );
		return in_array( 0, $groups, true ) ? array( 0 ) : $groups;
	}

	/**
	 * B-3: whether a learner is within a trainer's scope for a course.
	 *
	 * @param int $trainer_id Trainer.
	 * @param int $learner_id Learner.
	 * @param int $course_id  Course.
	 * @return bool
	 */
	public function trainer_covers_learner( int $trainer_id, int $learner_id, int $course_id ): bool {
		$groups = $this->trainer_groups( $trainer_id, $course_id );
		if ( array() === $groups ) {
			return false;
		}
		if ( array( 0 ) === $groups ) {
			return $this->learner_in_course( $learner_id, $course_id );
		}
		$seat  = $this->db->table( 'ccap_seat' );
		$in    = implode( ',', array_map( 'intval', $groups ) );
		$found = $this->db->scalar(
			$this->db->prepare( "SELECT 1 FROM `{$seat}` WHERE user_id = %d AND status = 'active' AND group_id IN ({$in}) LIMIT 1", $learner_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		);
		return null !== $found;
	}

	/**
	 * Whether a learner has any grant (any status) or seat for a course.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return bool
	 */
	public function learner_in_course( int $user_id, int $course_id ): bool {
		$grant = $this->db->table( 'ccap_grant' );
		return null !== $this->db->scalar(
			$this->db->prepare( "SELECT 1 FROM `{$grant}` WHERE user_id = %d AND course_id = %d LIMIT 1", $user_id, $course_id )
		);
	}

	/**
	 * Courses a module belongs to (via ccap_course_module).
	 *
	 * @param int $module_id Module.
	 * @return list<int>
	 */
	public function courses_of_module( int $module_id ): array {
		$table = $this->db->table( 'ccap_course_module' );
		return $this->ids( $this->db->prepare( "SELECT course_id FROM `{$table}` WHERE module_id = %d ORDER BY course_id", $module_id ) );
	}

	/**
	 * Courses of a course item (module, lesson, FAQ, tip).
	 *
	 * Lessons hang below a module (`post_parent`), FAQ/tips below a course.
	 *
	 * @param \WP_Post $item Item post.
	 * @return list<int>
	 */
	public function courses_of_item( \WP_Post $item ): array {
		switch ( $item->post_type ) {
			case self::COURSE_TYPE:
				return array( (int) $item->ID );
			case self::MODULE_TYPE:
				return $this->courses_of_module( (int) $item->ID );
			case self::LESSON_TYPE:
				return $this->courses_of_module( (int) $item->post_parent );
			default:
				$parent = $this->post( (int) $item->post_parent, self::COURSE_TYPE );
				return null === $parent ? array() : array( (int) $parent->ID );
		}
	}

	/**
	 * Course of a table-based course item (exam rule, variant, blueprint).
	 *
	 * @param string $type exam_rule|variant|blueprint.
	 * @param int    $id   Row ID.
	 * @return int|null
	 */
	public function course_of_row( string $type, int $id ): ?int {
		if ( 'blueprint' === $type ) {
			$blueprint = $this->db->table( 'ccap_exam_blueprint' );
			$rule      = $this->db->table( 'ccap_exam_rule' );
			$value     = $this->db->scalar( $this->db->prepare( "SELECT r.course_id FROM `{$blueprint}` b JOIN `{$rule}` r ON r.id = b.rule_id WHERE b.id = %d", $id ) );
		} else {
			$table = $this->db->table( 'exam_rule' === $type ? 'ccap_exam_rule' : 'ccap_exam_variant' );
			$value = $this->db->scalar( $this->db->prepare( "SELECT course_id FROM `{$table}` WHERE id = %d", $id ) );
		}
		return null === $value ? null : (int) $value;
	}

	/**
	 * Courses whose pools or inline quizzes use a question.
	 *
	 * @param int $question_id Question.
	 * @return list<int>
	 */
	public function courses_of_question( int $question_id ): array {
		$mq      = $this->db->table( 'ccap_module_question' );
		$cm      = $this->db->table( 'ccap_course_module' );
		$aq      = $this->db->table( 'ccap_activity_question' );
		$posts   = $this->db->wpdb()->posts;
		$courses = $this->ids( $this->db->prepare( "SELECT DISTINCT cm.course_id FROM `{$mq}` mq JOIN `{$cm}` cm ON cm.module_id = mq.module_id WHERE mq.question_id = %d", $question_id ) );
		$inline  = $this->ids( $this->db->prepare( "SELECT DISTINCT cm.course_id FROM `{$aq}` aq JOIN `{$posts}` p ON p.ID = aq.activity_id JOIN `{$cm}` cm ON cm.module_id = p.post_parent WHERE aq.question_id = %d", $question_id ) );
		$all     = array_values( array_unique( array_merge( $courses, $inline ) ) );
		sort( $all );
		return $all;
	}

	/**
	 * Question row.
	 *
	 * @param int $id Question ID.
	 * @return array<string, mixed>|null
	 */
	public function question( int $id ): ?array {
		return $this->row( 'ccap_question', $id );
	}

	/**
	 * Any own table row by primary key.
	 *
	 * @param string $table Table without prefix.
	 * @param int    $id    Primary key.
	 * @return array<string, mixed>|null
	 */
	public function row( string $table, int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}
		$name = $this->db->table( $table );
		return $this->db->row( $this->db->prepare( "SELECT * FROM `{$name}` WHERE id = %d", $id ) );
	}

	/**
	 * Active learner lock (E-79): not lifted, started, not expired.
	 *
	 * @param int $user_id User.
	 * @return bool
	 */
	public function is_locked( int $user_id ): bool {
		$table = $this->db->table( 'ccap_learner_lock' );
		$now   = $this->db->now();
		return null !== $this->db->scalar(
			$this->db->prepare(
				"SELECT 1 FROM `{$table}` WHERE user_id = %d AND lifted_at IS NULL AND valid_from <= %s AND (valid_until IS NULL OR valid_until > %s) LIMIT 1",
				$user_id,
				$now,
				$now
			)
		);
	}

	/**
	 * B-6: effective access to a course or module with a scope, evaluated
	 * against `now`, independent of the expiry cron (R0-07 §3.3, E-27,
	 * E-286, E-287, E-297).
	 *
	 * @param int    $user_id   Learner.
	 * @param int    $course_id Course.
	 * @param int    $module_id Module (0 = whole course).
	 * @param string $scope     view|download|exam.
	 * @param int    $depth     Recursion guard for dependent grants.
	 * @return string `ok` or the reason of the denial (`no_access`, `expired`, `revoked`, `suspended`, `pending`, `failed`).
	 */
	public function effective_access( int $user_id, int $course_id, int $module_id, string $scope, int $depth = 0 ): string {
		if ( $user_id <= 0 ) {
			return 'no_access';
		}
		$grant    = $this->db->table( 'ccap_grant' );
		$relation = $this->db->table( 'ccap_course_relation' );
		// The course itself or any bundle (one level, E-297) that contains it.
		$courses = array_merge(
			array( $course_id ),
			$this->ids( $this->db->prepare( "SELECT course_id FROM `{$relation}` WHERE related_course_id = %d AND relation_type = 'contains'", $course_id ) )
		);
		$in      = implode( ',', array_map( 'intval', $courses ) );
		$rows    = $this->db->rows(
			$this->db->prepare(
				"SELECT course_id, module_id, scopes, status, valid_from, valid_until, depends_on_course_id FROM `{$grant}` WHERE user_id = %d AND course_id IN ({$in})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
				$user_id
			)
		);

		$now    = $this->db->now();
		$reason = 'no_access';
		foreach ( $rows as $row ) {
			$row_module = (int) $row['module_id'];
			// A module grant only covers that module; bundle grants cover whole courses.
			if ( 0 !== $row_module && ( $row_module !== $module_id || (int) $row['course_id'] !== $course_id ) ) {
				continue;
			}
			if ( ! self::scope_matches( (string) $row['scopes'], $scope ) ) {
				continue;
			}
			$status = (string) $row['status'];
			if ( 'active' !== $status ) {
				$reason = self::stronger_reason( $reason, str_starts_with( $status, 'pending' ) ? 'pending' : $status );
				continue;
			}
			if ( null !== $row['valid_from'] && (string) $row['valid_from'] > $now ) {
				$reason = self::stronger_reason( $reason, 'pending' );
				continue;
			}
			if ( null !== $row['valid_until'] && (string) $row['valid_until'] <= $now ) {
				$reason = self::stronger_reason( $reason, 'expired' );
				continue;
			}
			$base = (int) $row['depends_on_course_id'];
			if ( 0 !== $base ) {
				// E-287: depends on the effective access to the base course; the grant itself stays unchanged.
				if ( $depth > 0 || 'ok' !== $this->effective_access( $user_id, $base, 0, 'view', $depth + 1 ) ) {
					$reason = self::stronger_reason( $reason, 'no_access' );
					continue;
				}
			}
			return 'ok';
		}

		if ( 'view' === $scope && $this->free_rule( $course_id, $module_id ) ) {
			return 'ok';
		}
		return $reason;
	}

	/**
	 * Free access rule (`access_type = free`) for the course or module.
	 * Partial access and drip are evaluated by the content gate (R1-14).
	 *
	 * @param int $course_id Course.
	 * @param int $module_id Module.
	 * @return bool
	 */
	public function free_rule( int $course_id, int $module_id ): bool {
		$table = $this->db->table( 'ccap_access_rule' );
		$now   = $this->db->now();
		return null !== $this->db->scalar(
			$this->db->prepare(
				"SELECT 1 FROM `{$table}` WHERE course_id = %d AND access_type = 'free' AND (module_id IS NULL OR module_id = 0 OR module_id = %d) AND (valid_from IS NULL OR valid_from <= %s) AND (valid_until IS NULL OR valid_until > %s) LIMIT 1",
				$course_id,
				$module_id,
				$now,
				$now
			)
		);
	}

	/**
	 * Courses referencing a media item via content assignments.
	 *
	 * @param int $media_id Media ID.
	 * @return list<int>
	 */
	public function courses_of_media( int $media_id ): array {
		$table   = $this->db->table( 'ccap_content_media' );
		$rows    = $this->db->rows( $this->db->prepare( "SELECT object_type, object_id FROM `{$table}` WHERE media_id = %d", $media_id ) );
		$courses = array();
		foreach ( $rows as $row ) {
			$type = (string) $row['object_type'];
			$id   = (int) $row['object_id'];
			if ( in_array( $type, array( 'course', 'module', 'lesson' ), true ) ) {
				$post = $this->post( $id );
				if ( null !== $post ) {
					$courses = array_merge( $courses, $this->courses_of_item( $post ) );
				}
			} elseif ( 'question_revision' === $type ) {
				$revision = $this->row( 'ccap_question_revision', $id );
				if ( null !== $revision ) {
					$courses = array_merge( $courses, $this->courses_of_question( (int) $revision['question_id'] ) );
				}
			}
		}
		$courses = array_values( array_unique( $courses ) );
		sort( $courses );
		return $courses;
	}

	/**
	 * Media row by UID.
	 *
	 * @param string $uid Media UID.
	 * @return array<string, mixed>|null
	 */
	public function media_by_uid( string $uid ): ?array {
		$table = $this->db->table( 'ccap_media' );
		return $this->db->row( $this->db->prepare( "SELECT * FROM `{$table}` WHERE uid = %s", $uid ) );
	}

	/**
	 * Seat or group by a raw claim/join token (stored as SHA-256 only, E-112).
	 *
	 * @param string $token Raw token.
	 * @return array{seat: array<string, mixed>|null, group: array<string, mixed>|null}
	 */
	public function claim_target( string $token ): array {
		$hash  = hash( 'sha256', $token );
		$seats = $this->db->table( 'ccap_seat' );
		$group = $this->db->table( 'ccap_group' );
		$seat  = $this->db->row( $this->db->prepare( "SELECT * FROM `{$seats}` WHERE claim_token_hash = %s", $hash ) );
		if ( null !== $seat ) {
			return array(
				'seat'  => $seat,
				'group' => $this->row( 'ccap_group', (int) $seat['group_id'] ),
			);
		}
		return array(
			'seat'  => null,
			'group' => $this->db->row( $this->db->prepare( "SELECT * FROM `{$group}` WHERE join_token_hash = %s", $hash ) ),
		);
	}

	/**
	 * Number of active seats of a group.
	 *
	 * @param int $group_id Group.
	 * @return int
	 */
	public function active_seats( int $group_id ): int {
		$table = $this->db->table( 'ccap_seat' );
		return (int) $this->db->scalar( $this->db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE group_id = %d AND status = 'active'", $group_id ) );
	}

	/**
	 * Whether a scope list (`all` or comma separated) contains a scope.
	 *
	 * @param string $scopes Stored scopes.
	 * @param string $scope  Requested scope.
	 * @return bool
	 */
	public static function scope_matches( string $scopes, string $scope ): bool {
		if ( 'all' === $scopes ) {
			return true;
		}
		$list = array_map( 'trim', explode( ',', $scopes ) );
		// Downloading or taking an exam implies viewing.
		return in_array( $scope, $list, true ) || ( 'view' === $scope && array() !== array_intersect( array( 'download', 'exam' ), $list ) );
	}

	/**
	 * The more informative denial reason (shown to the learner, P-08).
	 *
	 * @param string $current Current reason.
	 * @param string $next    Candidate.
	 * @return string
	 */
	private static function stronger_reason( string $current, string $next ): string {
		$rank = array(
			'no_access' => 0,
			'failed'    => 1,
			'pending'   => 2,
			'expired'   => 3,
			'revoked'   => 4,
			'suspended' => 5,
		);
		return ( $rank[ $next ] ?? 0 ) > ( $rank[ $current ] ?? 0 ) ? $next : $current;
	}

	/**
	 * Integer column list.
	 *
	 * @param string $sql Prepared SQL with one column.
	 * @return list<int>
	 */
	private function ids( string $sql ): array {
		return array_map( static fn( array $row ): int => (int) reset( $row ), $this->db->rows( $sql ) );
	}
}
