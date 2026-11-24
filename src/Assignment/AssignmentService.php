<?php
/**
 * Text/file assignments: submission workflow and grading
 * (E-168, R0-08 §2.5, §4.6, §7.1; R0-23 RV-22; R0-05 GM-195, GM-196).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Assignment;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Activity\Providers\AssignmentProvider;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Event\Outbox;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\RateLimiter;
use CourseCaptain\Storage\StorageException;

defined( 'ABSPATH' ) || exit;

/**
 * One chain per learner and activity; versions via `submission_no`.
 * `draft`/`returned` are editable; editing or submitting a `returned`
 * version starts version n+1 (copy of text and files), the returned one
 * stays readable. `submit` freezes `content_hash`, `settings_hash` and the
 * settings themselves, so grading uses the state at submission (RV-22).
 * Grading is append-only (`evaluation_no`); a regrade needs a reason and
 * never takes back a reached completion (R0-08 §5.1). Learners only touch
 * their own chain (foreign = 404); graders need M-10a (E-288, never the
 * own submission). Events `assignment.*` go to the internal outbox only
 * (E-290).
 */
final class AssignmentService {

	public const SCOPE_GRADE      = 'assignment_grade';
	public const SCOPE_UPLOAD     = 'assignment_upload';
	public const IDEMPOTENCY_DAYS = 7;
	public const UPLOADS_PER_HOUR = 30;
	public const QUEUE_SCAN_LIMIT = 2000;

	/**
	 * Constructor.
	 *
	 * @param Database      $db        Database access.
	 * @param PolicyService $policy    Policy.
	 * @param Relations     $relations Relations.
	 * @param ContentGate   $gate      Content gate.
	 */
	public function __construct( private Database $db, private PolicyService $policy, private Relations $relations, private ContentGate $gate ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, PolicyService::instance(), new Relations( $db ), ContentGate::current() );
	}

	// ---------------------------------------------------------------------
	// Learner.
	// ---------------------------------------------------------------------

	/**
	 * R-54: task and the learner's own versions. Without access the
	 * versions stay readable (results remain visible); without versions
	 * the content gate decides.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param int   $lesson_id Lesson (activity).
	 * @return array<string, mixed>
	 * @throws CourseException 401/403/404.
	 */
	public function view( Actor $actor, int $course_id, int $lesson_id ): array {
		self::require_login( $actor );
		$lesson = $this->relations->post( $lesson_id, Relations::LESSON_TYPE );
		$rows   = null === $lesson ? array() : $this->chain( $actor->user_id, $lesson_id );
		$own    = array_values( array_filter( $rows, static fn( array $r ): bool => (int) $r['course_id'] === $course_id ) );
		if ( null === $lesson || ( array() === $own && ! $this->in_course( $course_id, $lesson ) ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown lesson.', 404 );
		}
		$settings = $this->settings( $lesson_id );
		if ( array() === $own ) {
			if ( null === $settings ) {
				throw new CourseException( 'ccap_not_found', 'No assignment.', 404 );
			}
			$this->gate->lesson( $actor, $course_id, $lesson_id )->assert();
		}
		$active   = null !== $settings && $this->active( $course_id, $lesson );
		$editable = $active && $this->gate->lesson( $actor, $course_id, $lesson_id )->allowed() && ( array() === $own || in_array( (string) end( $own )['status'], array( 'draft', 'returned' ), true ) );
		$settings = $settings ?? array();
		$rules    = AssignmentFiles::rules( $settings );
		$limits   = self::text_limits( $settings );
		return array(
			'assignment'  => array(
				'uid'             => (string) get_post_meta( $lesson_id, \CourseCaptain\Course\ObjectIdentity::ACTIVITY_UID_META, true ),
				'lesson_id'       => $lesson_id,
				'title'           => wp_strip_all_tags( $lesson->post_title ),
				'type'            => (string) ( $settings['submission_type'] ?? 'text' ),
				'instructions'    => (string) ( $settings['instructions'] ?? '' ),
				'max_points'      => (int) ( $settings['max_points'] ?? 0 ),
				'passing_points'  => isset( $settings['passing_points'] ) ? (int) $settings['passing_points'] : null,
				'min_chars'       => $limits['min'],
				'max_chars'       => $limits['max'],
				'max_files'       => $rules['max_files'],
				'max_bytes'       => $rules['max_bytes'],
				'allowed_types'   => $rules['allowlist'],
				'available'       => $active,
				'completion_rule' => (string) ( ActivityService::definition( $lesson_id )['rule']['type'] ?? 'graded' ),
			),
			'editable'    => $editable,
			'submissions' => array_map( fn( array $r ): array => $this->present( $r, false ), $own ),
		);
	}

	/**
	 * R-55: save the draft text (creates the chain on first save; after
	 * `returned` the next version). `If-Match` is checked against the
	 * edited version's `row_version`.
	 *
	 * @param Actor       $actor          Learner.
	 * @param int         $course_id      Course.
	 * @param int         $lesson_id      Lesson.
	 * @param string|null $text           New text (null = unchanged).
	 * @param string|null $submission_uid Version the client edits (optional).
	 * @param int|null    $if_match       Expected `row_version`.
	 * @return array{submission_uid: string, submission_no: int, status: string, row_version: int}
	 * @throws CourseException 404/409/422/403.
	 */
	public function save_draft( Actor $actor, int $course_id, int $lesson_id, ?string $text, ?string $submission_uid = null, ?int $if_match = null ): array {
		self::require_login( $actor );
		$lesson   = $this->editable_lesson( $actor, $course_id, $lesson_id );
		$settings = (array) $this->settings( $lesson_id );
		if ( null !== $text ) {
			$text = self::clean_text( $text );
			if ( mb_strlen( $text ) > self::text_limits( $settings )['max'] ) {
				throw new CourseException( 'ccap_validation_failed', 'Text too long.', 422, array( 'reason' => 'text_too_long' ) );
			}
		}
		$row = $this->db->transaction(
			function () use ( $actor, $course_id, $lesson, $text, $submission_uid, $if_match ): array {
				$latest = $this->latest_for_update( $actor->user_id, (int) $lesson->ID );
				if ( null !== $submission_uid && '' !== $submission_uid && ( null === $latest || ! $this->is_own_version( $actor->user_id, (int) $lesson->ID, $submission_uid ) ) ) {
					throw new CourseException( 'ccap_not_found', 'Unknown submission.', 404 );
				}
				$created = null === $latest || 'returned' === $latest['status'];
				$target  = $this->editable_row( $actor->user_id, $course_id, (int) $lesson->ID, $latest );
				if ( ! $created && null !== $if_match && $if_match !== (int) $target['row_version'] ) {
					throw new CourseException( 'ccap_version_conflict', 'Version changed.', 409 );
				}
				if ( null !== $text ) {
					$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_assignment_submission' ) . "` SET text_answer = %s, row_version = row_version + 1, updated_at = %s WHERE id = %d AND status = 'draft'", $text, $this->db->now(), (int) $target['id'] ) );
				}
				return (array) $this->row( (int) $target['id'] );
			}
		);
		return array(
			'submission_uid' => (string) $row['uid'],
			'submission_no'  => (int) $row['submission_no'],
			'status'         => (string) $row['status'],
			'row_version'    => (int) $row['row_version'],
		);
	}

	/**
	 * R-56: upload a file into the own draft (R0-10 §4.5).
	 *
	 * @param Actor       $actor          Learner.
	 * @param string      $submission_uid Own submission (draft or returned).
	 * @param string      $tmp_path       Uploaded temporary file.
	 * @param string      $name           Original file name.
	 * @param string      $declared       Declared MIME type.
	 * @param string|null $request_key    Idempotency key.
	 * @return array<string, mixed> `submission_uid`, `file{media_uid, name, size_bytes, mime_type}`, `replayed` (a replay returns the stored response).
	 * @throws CourseException 404/409/422/429.
	 */
	public function add_file( Actor $actor, string $submission_uid, string $tmp_path, string $name, string $declared, ?string $request_key = null ): array {
		self::require_login( $actor );
		$row       = $this->own( $actor, $submission_uid );
		$course_id = (int) $row['course_id'];
		$lesson_id = (int) $row['activity_id'];
		$this->editable_lesson( $actor, $course_id, $lesson_id );
		$settings = (array) $this->settings( $lesson_id );
		if ( 'text' === ( $settings['submission_type'] ?? 'text' ) ) {
			throw new CourseException( 'ccap_submission_file_rejected', 'This task takes no files.', 422, array( 'reason' => 'no_files' ) );
		}
		$name = self::clean_name( $name );
		$sha  = is_file( $tmp_path ) ? (string) hash_file( 'sha256', $tmp_path ) : '';
		$hash = hash( 'sha256', $submission_uid . '|' . $name . '|' . $sha );
		$key  = null === $request_key ? '' : substr( $request_key, 0, 36 );
		if ( '' !== $key ) {
			$stored = $this->db->transaction( fn(): ?array => $this->idempotent( $actor, self::SCOPE_UPLOAD, $key, $hash ) );
			if ( null !== $stored ) {
				return $stored + array( 'replayed' => true );
			}
		}
		$rules = AssignmentFiles::rules( $settings );
		try {
			$this->rate_limit( $actor->user_id, $lesson_id );
			$mime = AssignmentFiles::check( $tmp_path, $name, $declared, $rules );
		} catch ( CourseException $e ) {
			$this->forget_key( $actor, self::SCOPE_UPLOAD, $key );
			throw $e;
		}
		try {
			$result = $this->db->transaction(
				function () use ( $actor, $course_id, $lesson_id, $tmp_path, $name, $mime, $sha, $rules, $key ): array {
					$latest = $this->latest_for_update( $actor->user_id, $lesson_id );
					$target = $this->editable_row( $actor->user_id, $course_id, $lesson_id, $latest );
					$files  = $this->db->table( 'ccap_assignment_file' );
					$count  = (int) $this->db->scalar( $this->db->prepare( "SELECT COUNT(*) FROM `{$files}` WHERE submission_id = %d", (int) $target['id'] ) );
					if ( $count >= $rules['max_files'] ) {
						$details = array(
							'reason'    => 'count',
							'max_files' => $rules['max_files'],
						);
						throw new CourseException( 'ccap_submission_file_rejected', 'Too many files.', 422, $details ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Machine details for the entry point.
					}
					$size = (int) filesize( $tmp_path );
					AssignmentFiles::scan(
						$tmp_path,
						array(
							'name'           => $name,
							'mime'           => $mime,
							'size_bytes'     => $size,
							'sha256'         => $sha,
							'submission_uid' => (string) $target['uid'],
						)
					);
					$storage  = AssignmentFiles::store( $tmp_path, (string) $target['uid'] );
					$uid      = $this->db->uuid();
					$media_id = $this->db->insert(
						'ccap_media',
						array(
							'uid'           => $uid,
							'source_system' => AssignmentFiles::SOURCE,
							'storage_key'   => $storage,
							'media_type'    => 'assignment',
							'mime_type'     => $mime,
							'size_bytes'    => $size,
							'sha256'        => $sha,
							'access'        => 'protected',
						),
						array( 'created_at', 'updated_at' )
					);
					$this->db->insert(
						'ccap_assignment_file',
						array(
							'submission_id' => (int) $target['id'],
							'media_id'      => $media_id,
							'original_name' => $name,
							'mime_type'     => $mime,
							'size_bytes'    => $size,
							'sha256'        => $sha,
							'sort_order'    => $count,
						),
						array( 'created_at' )
					);
					$this->touch( (int) $target['id'] );
					$out = array(
						'submission_uid' => (string) $target['uid'],
						'file'           => array(
							'media_uid'  => $uid,
							'name'       => $name,
							'size_bytes' => $size,
							'mime_type'  => $mime,
						),
					);
					$this->remember_key( $actor, self::SCOPE_UPLOAD, $key, $out, $uid );
					return $out;
				}
			);
		} catch ( CourseException $e ) {
			$this->forget_key( $actor, self::SCOPE_UPLOAD, $key );
			throw $e;
		}
		return $result + array( 'replayed' => false );
	}

	/**
	 * Remove a file from the own draft (addition to R-56).
	 *
	 * @param Actor  $actor          Learner.
	 * @param string $submission_uid Own draft.
	 * @param string $media_uid      File.
	 * @return array{submission_uid: string, removed: bool}
	 * @throws CourseException 404/409.
	 */
	public function remove_file( Actor $actor, string $submission_uid, string $media_uid ): array {
		self::require_login( $actor );
		$row = $this->own( $actor, $submission_uid );
		$this->editable_lesson( $actor, (int) $row['course_id'], (int) $row['activity_id'] );
		return $this->db->transaction(
			function () use ( $actor, $row, $media_uid ): array {
				$latest = $this->latest_for_update( $actor->user_id, (int) $row['activity_id'] );
				$target = $this->editable_row( $actor->user_id, (int) $row['course_id'], (int) $row['activity_id'], $latest );
				$media  = (int) $this->db->scalar( $this->db->prepare( 'SELECT f.media_id FROM `' . $this->db->table( 'ccap_assignment_file' ) . '` f JOIN `' . $this->db->table( 'ccap_media' ) . '` m ON m.id = f.media_id WHERE f.submission_id = %d AND m.uid = %s', (int) $target['id'], $media_uid ) );
				if ( $media <= 0 ) {
					throw new CourseException( 'ccap_not_found', 'Unknown file.', 404 );
				}
				$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_assignment_file' ) . '` WHERE submission_id = %d AND media_id = %d', (int) $target['id'], $media ) );
				AssignmentFiles::delete_media_if_unused( $this->db, $media );
				$this->touch( (int) $target['id'] );
				return array(
					'submission_uid' => (string) $target['uid'],
					'removed'        => true,
				);
			}
		);
	}

	/**
	 * R-57: submit. A repeated call on a submitted version returns the
	 * same result (200).
	 *
	 * @param Actor  $actor          Learner.
	 * @param string $submission_uid Own version.
	 * @return array{submission_uid: string, submission_no: int, status: string, submitted_at: string|null, content_hash: string|null, replayed: bool}
	 * @throws CourseException 404/409/422/403.
	 */
	public function submit( Actor $actor, string $submission_uid ): array {
		self::require_login( $actor );
		$row = $this->own( $actor, $submission_uid );
		if ( in_array( $row['status'], array( 'submitted', 'graded' ), true ) ) {
			return self::submit_result( $row, true );
		}
		$course_id = (int) $row['course_id'];
		$lesson_id = (int) $row['activity_id'];
		$this->editable_lesson( $actor, $course_id, $lesson_id );
		$settings               = (array) $this->settings( $lesson_id );
		$out                    = $this->db->transaction(
			function () use ( $actor, $course_id, $lesson_id, $settings, $submission_uid ): array {
				$latest = $this->latest_for_update( $actor->user_id, $lesson_id );
				if ( null !== $latest && $submission_uid !== (string) $latest['uid'] && in_array( $latest['status'], array( 'submitted', 'graded' ), true ) && (int) $latest['submission_no'] > 1 ) {
					return array( self::submit_result( $latest, true ), false );
				}
				$target = $this->editable_row( $actor->user_id, $course_id, $lesson_id, $latest );
				$files  = $this->files_of( (int) $target['id'] );
				$text   = trim( (string) $target['text_answer'] );
				$type   = (string) ( $settings['submission_type'] ?? 'text' );
				$limits = self::text_limits( $settings );
				$rules  = AssignmentFiles::rules( $settings );
				if ( 'file' !== $type && ( '' === $text || mb_strlen( $text ) < $limits['min'] ) ) {
					throw new CourseException( 'ccap_submission_incomplete', 'Text missing.', 422, array( 'reason' => '' === $text ? 'empty' : 'text_too_short' ) );
				}
				if ( 'text' !== $type && array() === $files ) {
					throw new CourseException( 'ccap_submission_incomplete', 'File missing.', 422, array( 'reason' => 'empty' ) );
				}
				if ( mb_strlen( $text ) > $limits['max'] ) {
					throw new CourseException( 'ccap_validation_failed', 'Text too long.', 422, array( 'reason' => 'text_too_long' ) );
				}
				// Drafts follow the current settings (RV-22 / P-3): stored files must still fit.
				if ( count( $files ) > $rules['max_files'] || ( 'text' === $type && array() !== $files ) ) {
					throw new CourseException( 'ccap_validation_failed', 'Files do not fit the task.', 422, array( 'reason' => 'files_not_allowed' ) );
				}
				foreach ( $files as $file ) {
					if ( ! in_array( AssignmentFiles::extension( (string) $file['original_name'] ), $rules['allowlist'], true ) || (int) $file['size_bytes'] > $rules['max_bytes'] ) {
						throw new CourseException( 'ccap_validation_failed', 'Files do not fit the task.', 422, array( 'reason' => 'files_not_allowed' ) );
					}
				}
				$content = self::content_hash( (string) $target['text_answer'], $files );
				$now     = $this->db->now();
				$table   = $this->db->table( 'ccap_assignment_submission' );
				$json    = (string) wp_json_encode( $settings );
				$rev     = $this->revision_of( $actor->user_id, $course_id );
				$changed = $this->db->execute(
					$this->db->prepare(
						"UPDATE `{$table}` SET status = 'submitted', content_hash = %s, settings_hash = %s, settings = %s, course_revision_id = " . ( null === $rev ? 'NULL' : (string) (int) $rev ) . ", submitted_at = %s, updated_at = %s, row_version = row_version + 1 WHERE id = %d AND status = 'draft'", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Integer or NULL.
						$content,
						'sha256:' . hash( 'sha256', $json ),
						$json,
						$now,
						$now,
						(int) $target['id']
					)
				);
				if ( 1 !== $changed ) {
					throw new CourseException( 'ccap_version_conflict', 'Submission changed.', 409 );
				}
				$fresh = (array) $this->row( (int) $target['id'] );
				$this->event( 'assignment.submitted', $fresh, array(), $actor );
				return array( self::submit_result( $fresh, false ), true );
			}
		);
		list( $result, $fresh ) = $out;
		if ( $fresh ) {
			ActivityProgress::current()->record_evidence( $actor->user_id, $course_id, $lesson_id, array( 'submitted' => true ) );
		}
		return $result;
	}

	// ---------------------------------------------------------------------
	// Graders.
	// ---------------------------------------------------------------------

	/**
	 * R-46: open submissions the actor may grade (M-10a), oldest first.
	 *
	 * @param Actor    $actor     Grader.
	 * @param int|null $course_id Course filter.
	 * @param int      $page      Page.
	 * @param int      $per_page  Page size (1–100).
	 * @return array{items: list<array<string, mixed>>, total: int, pages: int}
	 * @throws CourseException 401.
	 */
	public function queue( Actor $actor, ?int $course_id, int $page = 1, int $per_page = 20 ): array {
		self::require_login( $actor );
		$table = $this->db->table( 'ccap_assignment_submission' );
		$where = null === $course_id ? '' : $this->db->prepare( ' AND course_id = %d', $course_id );
		$rows  = $this->db->rows( $this->db->prepare( "SELECT * FROM `{$table}` WHERE status = 'submitted'{$where} ORDER BY submitted_at, id LIMIT %d", self::QUEUE_SCAN_LIMIT ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Condition prepared above.
		$open  = array_values( array_filter( $rows, fn( array $r ): bool => $this->policy->decide( $actor, PolicyService::GRADE_SUBMISSION, array( (int) $r['id'] ) )->allowed ) );
		$per   = max( 1, min( 100, $per_page ) );
		$page  = max( 1, $page );
		$items = array();
		foreach ( array_slice( $open, ( $page - 1 ) * $per, $per ) as $row ) {
			$user    = get_userdata( (int) $row['user_id'] );
			$items[] = array(
				'type'           => 'assignment',
				'submission_uid' => (string) $row['uid'],
				'submission_no'  => (int) $row['submission_no'],
				'submitted_at'   => self::rfc( $row['submitted_at'] ),
				'course_id'      => (int) $row['course_id'],
				'lesson_id'      => (int) $row['activity_id'],
				'lesson_title'   => wp_strip_all_tags( (string) get_post_field( 'post_title', (int) $row['activity_id'], 'raw' ) ),
				'learner'        => array(
					'id'           => (int) $row['user_id'],
					'display_name' => $user instanceof \WP_User ? (string) $user->display_name : '',
				),
			);
		}
		return array(
			'items' => $items,
			'total' => count( $open ),
			'pages' => (int) max( 1, ceil( count( $open ) / $per ) ),
		);
	}

	/**
	 * One version for its owner or a grader (graders also see the rubric
	 * and the frozen settings).
	 *
	 * @param Actor  $actor          Owner or grader.
	 * @param string $submission_uid Version.
	 * @return array<string, mixed>
	 * @throws CourseException 401/404.
	 */
	public function detail( Actor $actor, string $submission_uid ): array {
		self::require_login( $actor );
		$row = $this->by_uid( $submission_uid );
		if ( null === $row ) {
			throw new CourseException( 'ccap_not_found', 'Unknown submission.', 404 );
		}
		if ( (int) $row['user_id'] === $actor->user_id ) {
			return $this->present( $row, false );
		}
		if ( ! $this->policy->decide( $actor, PolicyService::GRADE_SUBMISSION, array( (int) $row['id'] ) )->allowed ) {
			throw new CourseException( 'ccap_not_found', 'Unknown submission.', 404 );
		}
		return $this->present( $row, true );
	}

	/**
	 * R-58: `grade` (first or new evaluation) or `return` (with comment).
	 *
	 * @param Actor    $actor          Grader (M-10a).
	 * @param string   $submission_uid Version.
	 * @param string   $action         grade|return.
	 * @param int|null $points_milli   Points in thousandths (whole points).
	 * @param string   $feedback       Feedback (grade) or comment (return, mandatory).
	 * @param string   $reason         Reason (mandatory for a regrade).
	 * @param string   $request_key    Idempotency key (mandatory).
	 * @return array<string, mixed> `submission_uid`, `status`, `evaluation_no`, `points_milli`, `passed`, `replayed` (a replay returns the stored response).
	 * @throws CourseException 400/403/404/409/422.
	 */
	public function grade( Actor $actor, string $submission_uid, string $action, ?int $points_milli, string $feedback, string $reason, string $request_key ): array {
		self::require_login( $actor );
		$row = $this->by_uid( $submission_uid );
		if ( null === $row ) {
			throw new CourseException( 'ccap_not_found', 'Unknown submission.', 404 );
		}
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::GRADE_SUBMISSION, array( (int) $row['id'] ) ), 'Not allowed to grade this submission.' );
		if ( ! in_array( $action, array( 'grade', 'return' ), true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown action.', 400, array( 'reason' => 'action' ) );
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9_-]{8,36}$/', $request_key ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Idempotency key required.', 400, array( 'reason' => 'idempotency_key' ) );
		}
		$feedback                = trim( sanitize_textarea_field( $feedback ) );
		$reason                  = trim( sanitize_textarea_field( $reason ) );
		$hash                    = hash( 'sha256', (string) wp_json_encode( array( $submission_uid, $action, $points_milli, $feedback, $reason ) ) );
		$out                     = $this->db->transaction(
			function () use ( $actor, $row, $action, $points_milli, $feedback, $reason, $request_key, $hash ): array {
				$stored = $this->idempotent( $actor, self::SCOPE_GRADE, $request_key, $hash );
				if ( null !== $stored ) {
					return array( $stored + array( 'replayed' => true ), false );
				}
				$table   = $this->db->table( 'ccap_assignment_submission' );
				$current = (array) $this->db->row( $this->db->prepare( "SELECT * FROM `{$table}` WHERE id = %d FOR UPDATE", (int) $row['id'] ) );
				$result  = 'return' === $action ? $this->do_return( $actor, $current, $feedback ) : $this->do_grade( $actor, $current, $points_milli, $feedback, $reason );
				$this->remember_key( $actor, self::SCOPE_GRADE, $request_key, $result, (string) $current['uid'] );
				return array( $result + array( 'replayed' => false ), 'graded' === $result['status'] );
			}
		);
		list( $result, $graded ) = $out;
		if ( $graded ) {
			$flags = array( 'graded' => true );
			if ( true === $result['passed'] ) {
				$flags['passed'] = true;
			}
			ActivityProgress::current()->record_evidence( (int) $row['user_id'], (int) $row['course_id'], (int) $row['activity_id'], $flags );
		}
		return $result;
	}

	/**
	 * Return for revision (comment mandatory).
	 *
	 * @param Actor                $actor   Grader.
	 * @param array<string, mixed> $row     Locked row.
	 * @param string               $comment Comment.
	 * @return array{submission_uid: string, status: string, evaluation_no: null, points_milli: null, passed: null}
	 * @throws CourseException 409/422.
	 */
	private function do_return( Actor $actor, array $row, string $comment ): array {
		if ( 'submitted' !== $row['status'] ) {
			throw new CourseException( 'ccap_version_conflict', 'Only submitted versions can be returned.', 409, array( 'reason' => 'state' ) );
		}
		if ( '' === $comment ) {
			throw new CourseException( 'ccap_grade_invalid', 'Comment required.', 422, array( 'reason' => 'comment_required' ) );
		}
		$now = $this->db->now();
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_assignment_submission' ) . "` SET status = 'returned', return_comment = %s, returned_at = %s, updated_at = %s, row_version = row_version + 1 WHERE id = %d AND status = 'submitted'", $comment, $now, $now, (int) $row['id'] ) );
		AuditLog::write( $this->db, $actor, 'assignment_returned', 'assignment_submission', (string) $row['uid'], null, array( 'submission_no' => (int) $row['submission_no'] ), (int) $row['user_id'] );
		$this->event( 'assignment.returned', (array) $this->row( (int) $row['id'] ), array(), $actor );
		return array(
			'submission_uid' => (string) $row['uid'],
			'status'         => 'returned',
			'evaluation_no'  => null,
			'points_milli'   => null,
			'passed'         => null,
		);
	}

	/**
	 * First evaluation or regrade (append-only, E-74).
	 *
	 * @param Actor                $actor        Grader.
	 * @param array<string, mixed> $row          Locked row.
	 * @param int|null             $points_milli Points.
	 * @param string               $feedback     Feedback.
	 * @param string               $reason       Reason (regrade).
	 * @return array{submission_uid: string, status: string, evaluation_no: int, points_milli: int, passed: bool|null}
	 * @throws CourseException 409/422.
	 */
	private function do_grade( Actor $actor, array $row, ?int $points_milli, string $feedback, string $reason ): array {
		if ( ! in_array( $row['status'], array( 'submitted', 'graded' ), true ) ) {
			throw new CourseException( 'ccap_version_conflict', 'Only submitted versions can be graded.', 409, array( 'reason' => 'state' ) );
		}
		$settings = json_decode( (string) $row['settings'], true );
		$settings = is_array( $settings ) ? $settings : array();
		$max      = (int) ( $settings['max_points'] ?? 0 ) * 1000;
		if ( null === $points_milli || $points_milli < 0 || $points_milli > $max || 0 !== $points_milli % 1000 ) {
			throw new CourseException( 'ccap_grade_invalid', 'Points out of range.', 422, array( 'reason' => 'points' ) );
		}
		$regrade = 'graded' === $row['status'];
		if ( $regrade && '' === $reason ) {
			throw new CourseException( 'ccap_grade_invalid', 'Reason required.', 422, array( 'reason' => 'reason_required' ) );
		}
		$grades = $this->db->table( 'ccap_assignment_grade' );
		$no     = 1 + (int) $this->db->scalar( $this->db->prepare( "SELECT MAX(evaluation_no) FROM `{$grades}` WHERE submission_id = %d", (int) $row['id'] ) );
		$pass   = isset( $settings['passing_points'] ) ? $points_milli >= (int) $settings['passing_points'] * 1000 : null;
		$now    = $this->db->now();
		$id     = $this->db->insert(
			'ccap_assignment_grade',
			array(
				'submission_id'    => (int) $row['id'],
				'evaluation_no'    => $no,
				'points_milli'     => $points_milli,
				'max_points_milli' => $max,
				'passed'           => null === $pass ? null : (int) $pass,
				'feedback'         => '' === $feedback ? null : $feedback,
				'graded_by'        => $actor->user_id,
				'graded_at'        => $now,
				'reason'           => '' === $reason ? null : $reason,
			),
			array( 'created_at' )
		);
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_assignment_submission' ) . "` SET status = 'graded', graded_at = %s, current_grade_id = %d, updated_at = %s, row_version = row_version + 1 WHERE id = %d", $now, $id, $now, (int) $row['id'] ) );
		AuditLog::write(
			$this->db,
			$actor,
			$regrade ? 'assignment_regraded' : 'assignment_graded',
			'assignment_submission',
			(string) $row['uid'],
			'' === $reason ? null : $reason,
			array(
				'evaluation_no' => $no,
				'points_milli'  => $points_milli,
				'passed'        => $pass,
			),
			(int) $row['user_id']
		);
		$this->event( 'assignment.graded', (array) $this->row( (int) $row['id'] ), array( 'evaluation_no' => $no ), $actor );
		return array(
			'submission_uid' => (string) $row['uid'],
			'status'         => 'graded',
			'evaluation_no'  => $no,
			'points_milli'   => $points_milli,
			'passed'         => $pass,
		);
	}

	// ---------------------------------------------------------------------
	// Privacy and retention (R0-31 RT-45, R0-10 §4.5, MED-030).
	// ---------------------------------------------------------------------

	/**
	 * Export items: versions with text and return comment, file metadata, evaluations.
	 *
	 * @param Database $db      Database.
	 * @param int      $user_id Learner.
	 * @return list<array<string, mixed>>
	 */
	public static function export_items( Database $db, int $user_id ): array {
		$out  = array();
		$item = static function ( string $group, string $label, string $id, array $values ): array {
			$data = array();
			foreach ( $values as $name => $value ) {
				$data[] = array(
					'name'  => (string) $name,
					'value' => null === $value ? '' : (string) $value,
				);
			}
			return array(
				'group_id'    => $group,
				'group_label' => $label,
				'item_id'     => $id,
				'data'        => $data,
			);
		};
		$subs = $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_assignment_submission' ) . '` WHERE user_id = %d ORDER BY id LIMIT 5000', $user_id ) );
		foreach ( $subs as $row ) {
			$sid   = (int) $row['id'];
			$out[] = $item(
				'cc-submissions',
				'Assignment submissions',
				'cc-submissions-' . $sid,
				array(
					'uid'            => $row['uid'],
					'course_id'      => $row['course_id'],
					'activity_id'    => $row['activity_id'],
					'submission_no'  => $row['submission_no'],
					'status'         => $row['status'],
					'text_answer'    => $row['text_answer'],
					'submitted_at'   => $row['submitted_at'],
					'returned_at'    => $row['returned_at'],
					'return_comment' => $row['return_comment'],
					'graded_at'      => $row['graded_at'],
				)
			);
			foreach ( $db->rows( $db->prepare( 'SELECT id, original_name, mime_type, size_bytes, sha256, created_at FROM `' . $db->table( 'ccap_assignment_file' ) . '` WHERE submission_id = %d ORDER BY sort_order, id', $sid ) ) as $file ) {
				$out[] = $item(
					'cc-submission-files',
					'Assignment files',
					'cc-submission-files-' . (int) $file['id'],
					array(
						'submission_uid' => $row['uid'],
						'original_name'  => $file['original_name'],
						'mime_type'      => $file['mime_type'],
						'size_bytes'     => $file['size_bytes'],
						'sha256'         => $file['sha256'],
						'created_at'     => $file['created_at'],
					)
				);
			}
			foreach ( $db->rows( $db->prepare( 'SELECT id, evaluation_no, points_milli, max_points_milli, passed, feedback, reason, graded_at FROM `' . $db->table( 'ccap_assignment_grade' ) . '` WHERE submission_id = %d ORDER BY evaluation_no', $sid ) ) as $grade ) {
				$out[] = $item(
					'cc-submission-grades',
					'Assignment evaluations',
					'cc-submission-grades-' . (int) $grade['id'],
					array(
						'submission_uid'   => $row['uid'],
						'evaluation_no'    => $grade['evaluation_no'],
						'points_milli'     => $grade['points_milli'],
						'max_points_milli' => $grade['max_points_milli'],
						'passed'           => $grade['passed'],
						'feedback'         => $grade['feedback'],
						'reason'           => $grade['reason'],
						'graded_at'        => $grade['graded_at'],
					)
				);
			}
		}
		return $out;
	}

	/**
	 * Erase a person's submissions incl. files (B-4); as grader the person is anonymised.
	 *
	 * @param Database $db      Database (in transaction).
	 * @param int      $user_id User.
	 * @return int Rows affected.
	 */
	public static function erase( Database $db, int $user_id ): int {
		$ids = array_map( 'intval', array_column( $db->rows( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_assignment_submission' ) . '` WHERE user_id = %d', $user_id ) ), 'id' ) );
		$n   = self::delete_submissions( $db, $ids );
		return $n + $db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_assignment_grade' ) . '` SET graded_by = 0 WHERE graded_by = %d', $user_id ) );
	}

	/**
	 * RT-45: chains whose course ended more than `$days` ago (course end =
	 * later of the newest active grant end and the last activity, E-244)
	 * are deleted with their files. Versions waiting for grading stay.
	 *
	 * @param Database $db  Database.
	 * @param string   $cut UTC cutoff (`Y-m-d H:i:s`).
	 * @param bool     $dry Only count (retention preview, SET-CC-02).
	 * @return int Deleted (or due) versions.
	 */
	public static function expire_retention( Database $db, string $cut, bool $dry = false ): int {
		$subs     = $db->table( 'ccap_assignment_submission' );
		$grants   = $db->table( 'ccap_grant' );
		$progress = $db->table( 'ccap_course_progress' );
		$pairs    = $db->rows(
			$db->prepare(
				"SELECT s.user_id, s.course_id FROM `{$subs}` s GROUP BY s.user_id, s.course_id HAVING MAX(s.updated_at) < %s AND SUM(s.status = 'submitted') = 0",
				$cut
			)
		);
		$ids      = array();
		foreach ( $pairs as $pair ) {
			$user   = (int) $pair['user_id'];
			$course = (int) $pair['course_id'];
			$open   = $db->scalar( $db->prepare( "SELECT 1 FROM `{$grants}` WHERE user_id = %d AND course_id = %d AND status = 'active' AND (valid_until IS NULL OR valid_until >= %s) LIMIT 1", $user, $course, $cut ) );
			$recent = $db->scalar( $db->prepare( "SELECT 1 FROM `{$progress}` WHERE user_id = %d AND course_id = %d AND last_activity_at >= %s LIMIT 1", $user, $course, $cut ) );
			if ( null !== $open || null !== $recent ) {
				continue;
			}
			$ids = array_merge( $ids, array_map( 'intval', array_column( $db->rows( $db->prepare( "SELECT id FROM `{$subs}` WHERE user_id = %d AND course_id = %d", $user, $course ) ), 'id' ) ) );
		}
		$before = count( $ids );
		if ( ! $dry ) {
			self::delete_submissions( $db, $ids );
		}
		return $before;
	}

	/**
	 * Delete versions with evaluations, file rows, media rows and files.
	 *
	 * @param Database $db  Database.
	 * @param array    $ids Submission IDs.
	 * @phpstan-param list<int> $ids
	 * @return int Rows affected.
	 */
	private static function delete_submissions( Database $db, array $ids ): int {
		if ( array() === $ids ) {
			return 0;
		}
		$in    = implode( ',', array_map( 'intval', $ids ) );
		$uids  = array_column( $db->rows( 'SELECT uid FROM `' . $db->table( 'ccap_assignment_submission' ) . "` WHERE id IN ({$in})" ), 'uid' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		$media = array_map( 'intval', array_column( $db->rows( 'SELECT DISTINCT media_id FROM `' . $db->table( 'ccap_assignment_file' ) . "` WHERE submission_id IN ({$in})" ), 'media_id' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		$n     = $db->execute( 'DELETE FROM `' . $db->table( 'ccap_assignment_grade' ) . "` WHERE submission_id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		$n    += $db->execute( 'DELETE FROM `' . $db->table( 'ccap_assignment_file' ) . "` WHERE submission_id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		$n    += $db->execute( 'DELETE FROM `' . $db->table( 'ccap_assignment_submission' ) . "` WHERE id IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		foreach ( $media as $media_id ) {
			$n += AssignmentFiles::delete_media_if_unused( $db, $media_id );
		}
		foreach ( $uids as $uid ) {
			AssignmentFiles::remove_folder( (string) $uid );
		}
		return $n;
	}

	// ---------------------------------------------------------------------
	// Internals.
	// ---------------------------------------------------------------------

	/**
	 * Logged in (401).
	 *
	 * @param Actor $actor Actor.
	 * @return void
	 * @throws CourseException 401.
	 */
	private static function require_login( Actor $actor ): void {
		if ( $actor->user_id <= 0 ) {
			throw new CourseException( 'ccap_auth_required', 'Login required.', 401 );
		}
	}

	/**
	 * Lesson that may be edited now: own access (content gate), assignment
	 * still part of the course (else read-only, REV-048).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @param int   $lesson_id Lesson.
	 * @return \WP_Post
	 * @throws CourseException 404/403/409.
	 */
	private function editable_lesson( Actor $actor, int $course_id, int $lesson_id ): \WP_Post {
		$lesson = $this->relations->post( $lesson_id, Relations::LESSON_TYPE );
		$exists = null !== $lesson && array() !== $this->chain( $actor->user_id, $lesson_id );
		if ( null === $lesson || ( ! $exists && ( ! $this->in_course( $course_id, $lesson ) || null === $this->settings( $lesson_id ) ) ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown assignment.', 404 );
		}
		if ( null === $this->settings( $lesson_id ) || ! $this->active( $course_id, $lesson ) ) {
			throw new CourseException( 'ccap_submission_not_editable', 'The assignment is no longer part of the course.', 409, array( 'reason' => 'removed' ) );
		}
		$this->gate->lesson( $actor, $course_id, $lesson_id )->assert();
		return $lesson;
	}

	/**
	 * Whether the lesson belongs to the course structure.
	 *
	 * @param int      $course_id Course.
	 * @param \WP_Post $lesson    Lesson.
	 * @return bool
	 */
	private function in_course( int $course_id, \WP_Post $lesson ): bool {
		return in_array( $course_id, $this->relations->courses_of_item( $lesson ), true );
	}

	/**
	 * Assignment still offered: published lesson in the course and, once a
	 * revision exists, in the current revision (RV-22).
	 *
	 * @param int      $course_id Course.
	 * @param \WP_Post $lesson    Lesson.
	 * @return bool
	 */
	private function active( int $course_id, \WP_Post $lesson ): bool {
		if ( 'publish' !== $lesson->post_status || ! $this->in_course( $course_id, $lesson ) ) {
			return false;
		}
		$revision = CoursePublisher::current()->current_revision( $course_id );
		if ( null === $revision ) {
			return true;
		}
		$structure = json_decode( (string) $revision['structure'], true );
		foreach ( (array) ( is_array( $structure ) ? ( $structure['modules'] ?? array() ) : array() ) as $module ) {
			foreach ( (array) ( $module['lessons'] ?? array() ) as $item ) {
				if ( (int) ( $item['lesson_id'] ?? 0 ) === (int) $lesson->ID ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Current assignment settings of a lesson, or null when it is no assignment.
	 *
	 * @param int $lesson_id Lesson.
	 * @return array<string, mixed>|null
	 */
	private function settings( int $lesson_id ): ?array {
		$definition = ActivityService::definition( $lesson_id );
		return 'assignment' === $definition['type'] ? $definition['settings'] : null;
	}

	/**
	 * Text limits (OP-08-3 defaults).
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array{min: int, max: int}
	 */
	private static function text_limits( array $settings ): array {
		$limits = is_array( $settings['text_limits'] ?? null ) ? $settings['text_limits'] : array();
		$max    = min( AssignmentProvider::TEXT_HARD_MAX, max( 1, (int) ( $limits['max_chars'] ?? AssignmentProvider::TEXT_DEFAULT_MAX ) ) );
		return array(
			'min' => min( $max, max( 0, (int) ( $limits['min_chars'] ?? 0 ) ) ),
			'max' => $max,
		);
	}

	/**
	 * Versions of the chain, oldest first.
	 *
	 * @param int $user_id   Learner.
	 * @param int $lesson_id Lesson.
	 * @return list<array<string, mixed>>
	 */
	private function chain( int $user_id, int $lesson_id ): array {
		return $this->db->rows( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_assignment_submission' ) . '` WHERE user_id = %d AND activity_id = %d ORDER BY submission_no', $user_id, $lesson_id ) );
	}

	/**
	 * Whether a UID is a version of the own chain.
	 *
	 * @param int    $user_id   Learner.
	 * @param int    $lesson_id Lesson.
	 * @param string $uid       UID.
	 * @return bool
	 */
	private function is_own_version( int $user_id, int $lesson_id, string $uid ): bool {
		return null !== $this->db->scalar( $this->db->prepare( 'SELECT 1 FROM `' . $this->db->table( 'ccap_assignment_submission' ) . '` WHERE user_id = %d AND activity_id = %d AND uid = %s', $user_id, $lesson_id, $uid ) );
	}

	/**
	 * Newest version, locked.
	 *
	 * @param int $user_id   Learner.
	 * @param int $lesson_id Lesson.
	 * @return array<string, mixed>|null
	 */
	private function latest_for_update( int $user_id, int $lesson_id ): ?array {
		return $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_assignment_submission' ) . '` WHERE user_id = %d AND activity_id = %d ORDER BY submission_no DESC LIMIT 1 FOR UPDATE', $user_id, $lesson_id ) );
	}

	/**
	 * Editable version: the draft, a new chain, or version n+1 after a return.
	 *
	 * @param int                       $user_id   Learner.
	 * @param int                       $course_id Course.
	 * @param int                       $lesson_id Lesson.
	 * @param array<string, mixed>|null $latest    Newest version (locked).
	 * @return array<string, mixed>
	 * @throws CourseException 409 when the newest version is submitted or graded.
	 * @throws StorageException On database errors other than a duplicate.
	 */
	private function editable_row( int $user_id, int $course_id, int $lesson_id, ?array $latest ): array {
		if ( null !== $latest && 'draft' === $latest['status'] ) {
			return $latest;
		}
		if ( null !== $latest && 'returned' !== $latest['status'] ) {
			throw new CourseException( 'ccap_submission_not_editable', 'The submission can no longer be changed.', 409, array( 'reason' => (string) $latest['status'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Machine state for the entry point.
		}
		$quiet = $this->db->wpdb()->suppress_errors( true );
		try {
			$id = $this->db->insert(
				'ccap_assignment_submission',
				array(
					'uid'           => $this->db->uuid(),
					'user_id'       => $user_id,
					'course_id'     => null === $latest ? $course_id : (int) $latest['course_id'],
					'activity_id'   => $lesson_id,
					'activity_uid'  => (string) get_post_meta( $lesson_id, \CourseCaptain\Course\ObjectIdentity::ACTIVITY_UID_META, true ),
					'submission_no' => null === $latest ? 1 : (int) $latest['submission_no'] + 1,
					'status'        => 'draft',
					'text_answer'   => null === $latest ? null : $latest['text_answer'],
				),
				array( 'created_at', 'updated_at' )
			);
		} catch ( StorageException $e ) {
			if ( ! $e->is_duplicate() ) {
				throw $e;
			}
			$fresh = $this->latest_for_update( $user_id, $lesson_id );
			if ( null === $fresh || 'draft' !== $fresh['status'] ) {
				throw new CourseException( 'ccap_version_conflict', 'Submission changed.', 409 );
			}
			return $fresh;
		} finally {
			$this->db->wpdb()->suppress_errors( $quiet );
		}
		if ( null !== $latest ) {
			// Version n+1 starts with the returned content; the media are shared, not copied.
			$files = $this->db->table( 'ccap_assignment_file' );
			$this->db->execute( $this->db->prepare( "INSERT INTO `{$files}` (submission_id, media_id, original_name, mime_type, size_bytes, sha256, sort_order, created_at) SELECT %d, media_id, original_name, mime_type, size_bytes, sha256, sort_order, created_at FROM `{$files}` WHERE submission_id = %d", $id, (int) $latest['id'] ) );
		}
		return (array) $this->row( $id );
	}

	/**
	 * Bump `row_version` after a file change.
	 *
	 * @param int $id Submission.
	 * @return void
	 */
	private function touch( int $id ): void {
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_assignment_submission' ) . '` SET row_version = row_version + 1, updated_at = %s WHERE id = %d', $this->db->now(), $id ) );
	}

	/**
	 * Row by ID.
	 *
	 * @param int $id ID.
	 * @return array<string, mixed>|null
	 */
	private function row( int $id ): ?array {
		return $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_assignment_submission' ) . '` WHERE id = %d', $id ) );
	}

	/**
	 * Row by UID.
	 *
	 * @param string $uid UID.
	 * @return array<string, mixed>|null
	 */
	private function by_uid( string $uid ): ?array {
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/', $uid ) ) {
			return null;
		}
		return $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_assignment_submission' ) . '` WHERE uid = %s', $uid ) );
	}

	/**
	 * Own version or 404 (no existence oracle).
	 *
	 * @param Actor  $actor Learner.
	 * @param string $uid   UID.
	 * @return array<string, mixed>
	 * @throws CourseException 404.
	 */
	private function own( Actor $actor, string $uid ): array {
		$row = $this->by_uid( $uid );
		if ( null === $row || (int) $row['user_id'] !== $actor->user_id ) {
			throw new CourseException( 'ccap_not_found', 'Unknown submission.', 404 );
		}
		return $row;
	}

	/**
	 * Files of a version.
	 *
	 * @param int $submission_id Version.
	 * @return list<array<string, mixed>>
	 */
	private function files_of( int $submission_id ): array {
		return $this->db->rows( $this->db->prepare( 'SELECT f.*, m.uid AS media_uid FROM `' . $this->db->table( 'ccap_assignment_file' ) . '` f JOIN `' . $this->db->table( 'ccap_media' ) . '` m ON m.id = f.media_id WHERE f.submission_id = %d ORDER BY f.sort_order, f.id', $submission_id ) );
	}

	/**
	 * `content_hash`: text plus SHA-256 per file in order (§2.5).
	 *
	 * @param string                     $text  Text.
	 * @param list<array<string, mixed>> $files Files.
	 * @return string
	 */
	private static function content_hash( string $text, array $files ): string {
		return 'sha256:' . hash( 'sha256', (string) wp_json_encode( array( $text, array_column( $files, 'sha256' ) ) ) );
	}

	/**
	 * Revision the learner works on (course progress, else the current one).
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return int|null
	 */
	private function revision_of( int $user_id, int $course_id ): ?int {
		$own = (int) $this->db->scalar( $this->db->prepare( 'SELECT course_revision_id FROM `' . $this->db->table( 'ccap_course_progress' ) . '` WHERE user_id = %d AND course_id = %d', $user_id, $course_id ) );
		if ( $own > 0 ) {
			return $own;
		}
		$current = CoursePublisher::current()->current_revision( $course_id );
		return null === $current ? null : (int) $current['id'];
	}

	/**
	 * Submit result.
	 *
	 * @param array<string, mixed> $row      Row.
	 * @param bool                 $replayed Repetition.
	 * @return array{submission_uid: string, submission_no: int, status: string, submitted_at: string|null, content_hash: string|null, replayed: bool}
	 */
	private static function submit_result( array $row, bool $replayed ): array {
		return array(
			'submission_uid' => (string) $row['uid'],
			'submission_no'  => (int) $row['submission_no'],
			'status'         => (string) $row['status'],
			'submitted_at'   => self::rfc( $row['submitted_at'] ),
			'content_hash'   => null === $row['content_hash'] ? null : (string) $row['content_hash'],
			'replayed'       => $replayed,
		);
	}

	/**
	 * Public form of a version.
	 *
	 * @param array<string, mixed> $row    Row.
	 * @param bool                 $grader Grader view (rubric, frozen settings, learner).
	 * @return array<string, mixed>
	 */
	private function present( array $row, bool $grader ): array {
		$grades = 'graded' === $row['status'] ? $this->db->rows( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_assignment_grade' ) . '` WHERE submission_id = %d ORDER BY evaluation_no', (int) $row['id'] ) ) : array();
		$evals  = array_map(
			static fn( array $g ): array => array(
				'evaluation_no'    => (int) $g['evaluation_no'],
				'points_milli'     => null === $g['points_milli'] ? null : (int) $g['points_milli'],
				'max_points_milli' => null === $g['max_points_milli'] ? null : (int) $g['max_points_milli'],
				'passed'           => null === $g['passed'] ? null : (bool) $g['passed'],
				'feedback'         => (string) ( $g['feedback'] ?? '' ),
				'reason'           => null === $g['reason'] ? null : (string) $g['reason'],
				'graded_at'        => self::rfc( $g['graded_at'] ),
			),
			$grades
		);
		$out    = array(
			'uid'            => (string) $row['uid'],
			'submission_no'  => (int) $row['submission_no'],
			'status'         => (string) $row['status'],
			'text'           => (string) ( $row['text_answer'] ?? '' ),
			'files'          => array_map(
				static fn( array $f ): array => array(
					'media_uid'  => (string) $f['media_uid'],
					'name'       => (string) $f['original_name'],
					'mime_type'  => (string) $f['mime_type'],
					'size_bytes' => (int) $f['size_bytes'],
					'url'        => home_url( '/cc-file/' . $f['media_uid'] . '/' ),
				),
				$this->files_of( (int) $row['id'] )
			),
			'submitted_at'   => self::rfc( $row['submitted_at'] ),
			'returned_at'    => self::rfc( $row['returned_at'] ),
			'return_comment' => null === $row['return_comment'] ? null : (string) $row['return_comment'],
			'graded_at'      => self::rfc( $row['graded_at'] ),
			'content_hash'   => null === $row['content_hash'] ? null : (string) $row['content_hash'],
			'row_version'    => (int) $row['row_version'],
			'grade'          => array() === $evals ? null : end( $evals ),
			'evaluations'    => $evals,
		);
		if ( $grader ) {
			$settings       = json_decode( (string) $row['settings'], true );
			$settings       = is_array( $settings ) ? $settings : array();
			$user           = get_userdata( (int) $row['user_id'] );
			$out['grading'] = array(
				'course_id'      => (int) $row['course_id'],
				'lesson_id'      => (int) $row['activity_id'],
				'learner'        => array(
					'id'           => (int) $row['user_id'],
					'display_name' => $user instanceof \WP_User ? (string) $user->display_name : '',
				),
				'settings_hash'  => null === $row['settings_hash'] ? null : (string) $row['settings_hash'],
				'max_points'     => (int) ( $settings['max_points'] ?? 0 ),
				'passing_points' => isset( $settings['passing_points'] ) ? (int) $settings['passing_points'] : null,
				'rubric_text'    => (string) ( $settings['rubric_text'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Internal outbox event (E-290): no content, no points.
	 *
	 * @param string               $type  Event type.
	 * @param array<string, mixed> $row   Fresh row.
	 * @param array<string, mixed> $extra Extra payload.
	 * @param Actor                $actor Actor.
	 * @return void
	 */
	private function event( string $type, array $row, array $extra, Actor $actor ): void {
		$course_uid = (string) $this->db->scalar( $this->db->prepare( 'SELECT uid FROM `' . $this->db->table( 'ccap_source_map' ) . "` WHERE object_type = 'course' AND object_id = %d", (int) $row['course_id'] ) );
		Outbox::record(
			$this->db,
			$type,
			'assignment_submission',
			(string) $row['uid'],
			(int) $row['row_version'],
			array(
				'submission_uid' => (string) $row['uid'],
				'submission_no'  => (int) $row['submission_no'],
				'course_id'      => (int) $row['course_id'],
			) + $extra,
			array(
				'course_uid'   => '' === $course_uid ? null : $course_uid,
				'user_id'      => (int) $row['user_id'],
				'actor_type'   => 'user',
				'actor_ref'    => 'user:' . $actor->user_id,
				'privacy_tags' => array( 'personal_ref', 'assessment_result' ),
			)
		);
	}

	/**
	 * Upload rate limit per learner and task; fail-open on counter errors (E-46).
	 *
	 * @param int $user_id   Learner.
	 * @param int $lesson_id Task.
	 * @return void
	 * @throws CourseException 429.
	 */
	private function rate_limit( int $user_id, int $lesson_id ): void {
		try {
			RateLimiter::hit( $this->db, 'assignment_upload:' . $lesson_id, $user_id, 3600, self::UPLOADS_PER_HOUR, $this->db->time() );
		} catch ( StorageException $e ) {
			return;
		} catch ( CourseException $e ) {
			throw new CourseException( 'ccap_rate_limited', 'Rate limit reached.', 429, array( 'bucket' => 'assignment_upload' ) + array_intersect_key( $e->data, array( 'retry_after' => true ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Machine details for the entry point.
		}
	}

	/**
	 * Idempotency (R0-19 §2.1): stored response or null for a new request.
	 *
	 * @param Actor  $actor Actor.
	 * @param string $scope Scope.
	 * @param string $key   Key.
	 * @param string $hash  Request hash.
	 * @return array<string, mixed>|null
	 * @throws CourseException `ccap_idempotency_conflict`, `ccap_request_in_progress`.
	 */
	private function idempotent( Actor $actor, string $scope, string $key, string $hash ): ?array {
		$table    = $this->db->table( 'ccap_request_idempotency' );
		$inserted = $this->db->execute(
			$this->db->prepare(
				"INSERT IGNORE INTO `{$table}` (user_id, scope, idem_key, request_hash, status_code, created_at, expires_at) VALUES (%d, %s, %s, %s, 0, %s, %s)",
				$actor->user_id,
				$scope,
				$key,
				$hash,
				$this->db->now(),
				$this->db->now( self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS )
			)
		);
		if ( 1 === $inserted ) {
			return null;
		}
		$row = (array) $this->db->row( $this->db->prepare( "SELECT request_hash, status_code, response FROM `{$table}` WHERE user_id = %d AND scope = %s AND idem_key = %s", $actor->user_id, $scope, $key ) );
		if ( ! hash_equals( (string) ( $row['request_hash'] ?? '' ), $hash ) ) {
			throw new CourseException( 'ccap_idempotency_conflict', 'The request key was used for another request.', 409 );
		}
		$response = json_decode( (string) ( $row['response'] ?? '' ), true );
		if ( 200 !== (int) ( $row['status_code'] ?? 0 ) || ! is_array( $response ) ) {
			throw new CourseException( 'ccap_request_in_progress', 'The request is still in progress.', 409, array( 'retry_after' => 2 ) );
		}
		return $response;
	}

	/**
	 * Store the response of a key.
	 *
	 * @param Actor                $actor    Actor.
	 * @param string               $scope    Scope.
	 * @param string               $key      Key ('' = none).
	 * @param array<string, mixed> $response Response.
	 * @param string               $resource_uid Resource UID.
	 * @return void
	 */
	private function remember_key( Actor $actor, string $scope, string $key, array $response, string $resource_uid ): void {
		if ( '' === $key ) {
			return;
		}
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_request_idempotency' ) . '` SET status_code = 200, response = %s, resource = %s WHERE user_id = %d AND scope = %s AND idem_key = %s', (string) wp_json_encode( $response ), $resource_uid, $actor->user_id, $scope, $key ) );
	}

	/**
	 * Release a key after a rejected request (the client may retry with it).
	 *
	 * @param Actor  $actor Actor.
	 * @param string $scope Scope.
	 * @param string $key   Key ('' = none).
	 * @return void
	 */
	private function forget_key( Actor $actor, string $scope, string $key ): void {
		if ( '' === $key ) {
			return;
		}
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_request_idempotency' ) . '` WHERE user_id = %d AND scope = %s AND idem_key = %s AND status_code = 0', $actor->user_id, $scope, $key ) );
	}

	/**
	 * Plain text without control characters (line breaks kept).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function clean_text( string $text ): string {
		$text = wp_check_invalid_utf8( $text );
		return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace( "\r\n", "\n", $text ) );
	}

	/**
	 * File name as metadata (no path, no control characters, at most 255 bytes).
	 *
	 * @param string $name Name.
	 * @return string
	 */
	private static function clean_name( string $name ): string {
		$name = sanitize_text_field( wp_basename( str_replace( '\\', '/', $name ) ) );
		$name = '' === $name ? 'file' : $name;
		return mb_strcut( $name, 0, 255, 'UTF-8' );
	}

	/**
	 * RFC 3339 UTC or null.
	 *
	 * @param mixed $datetime MySQL UTC datetime.
	 * @return string|null
	 */
	private static function rfc( $datetime ): ?string {
		if ( null === $datetime || '' === $datetime ) {
			return null;
		}
		$time = strtotime( (string) $datetime . ' UTC' );
		return false === $time ? null : gmdate( 'Y-m-d\TH:i:s\Z', $time );
	}
}
