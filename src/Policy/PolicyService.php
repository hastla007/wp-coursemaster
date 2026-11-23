<?php
/**
 * Central policy service (R0-07 P-02, §3.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * `decide(actor, action, args, context)` → Decision.
 *
 * Every decision = capability + object relation + site + status. Deny by
 * default; foreign or invisible objects are 404 (no existence oracle);
 * structural bans (self-grading, self-lock, own certificate) are
 * `do_not_allow` even for administrators and super admins.
 *
 * The WordPress meta capabilities (MetaCaps) are only a facade.
 */
final class PolicyService {

	public const VIEW_COURSE          = 'ccap_view_course';
	public const LEARN                = 'ccap_learn';
	public const EDIT_COURSE          = 'ccap_edit_course';
	public const PUBLISH_COURSE       = 'ccap_publish_course';
	public const DELETE_COURSE        = 'ccap_delete_course';
	public const EDIT_COURSE_ITEM     = 'ccap_edit_course_item';
	public const EDIT_QUESTION        = 'ccap_edit_question';
	public const USE_ATTEMPT          = 'ccap_use_attempt';
	public const VIEW_ATTEMPT         = 'ccap_view_attempt';
	public const GRADE_ATTEMPT        = 'ccap_grade_attempt';
	public const GRADE_SUBMISSION     = 'ccap_grade_submission';
	public const VIEW_LEARNER         = 'ccap_view_learner';
	public const MANAGE_PROGRESS      = 'ccap_manage_learner_progress';
	public const MANAGE_GRANT         = 'ccap_manage_grant';
	public const CREATE_GRANT         = 'ccap_create_grant';
	public const MANAGE_GROUP         = 'ccap_manage_group';
	public const VIEW_GROUP_PROGRESS  = 'ccap_view_group_progress';
	public const CLAIM_SEAT           = 'ccap_claim_seat';
	public const VIEW_CERTIFICATE     = 'ccap_view_certificate';
	public const MANAGE_CERTIFICATE   = 'ccap_manage_certificate';
	public const DOWNLOAD_FILE        = 'ccap_download_file';
	public const MANAGE_IMPORT        = 'ccap_manage_import';
	public const DOWNLOAD_EXPORT      = 'ccap_download_export';
	public const EXPORT_PERSONAL_DATA = 'ccap_export_personal_data';
	public const LOCK_LEARNER         = 'ccap_lock_learner';

	/**
	 * All meta capabilities M-01…M-24 (incl. M-10a).
	 *
	 * Note: `ccap_manage_learner_progress` is both a primitive capability
	 * (#16) and the name of M-12; the meta form is only used with arguments.
	 *
	 * @var list<string>
	 */
	public const META = array(
		self::VIEW_COURSE,
		self::LEARN,
		self::EDIT_COURSE,
		self::PUBLISH_COURSE,
		self::DELETE_COURSE,
		self::EDIT_COURSE_ITEM,
		self::EDIT_QUESTION,
		self::USE_ATTEMPT,
		self::VIEW_ATTEMPT,
		self::GRADE_ATTEMPT,
		self::GRADE_SUBMISSION,
		self::VIEW_LEARNER,
		self::MANAGE_PROGRESS,
		self::MANAGE_GRANT,
		self::CREATE_GRANT,
		self::MANAGE_GROUP,
		self::VIEW_GROUP_PROGRESS,
		self::CLAIM_SEAT,
		self::VIEW_CERTIFICATE,
		self::MANAGE_CERTIFICATE,
		self::DOWNLOAD_FILE,
		self::MANAGE_IMPORT,
		self::DOWNLOAD_EXPORT,
		self::EXPORT_PERSONAL_DATA,
		self::LOCK_LEARNER,
	);

	/**
	 * Request memo (P-11: flushed by Invalidation).
	 *
	 * @var array<string, Decision>
	 */
	private array $memo = array();

	/**
	 * Shared instance for the request.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Ignored widenings of `course_captain_can_access` already audited in this request.
	 *
	 * @var array<string, true>
	 */
	private static array $widening_logged = array();

	/**
	 * Constructor.
	 *
	 * @param Relations $relations Object relations.
	 */
	public function __construct( private Relations $relations ) {
	}

	/**
	 * Instance of the request (current site).
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self( new Relations( Database::current() ) );
		}
		return self::$instance;
	}

	/**
	 * Drop the request instance so the next call binds to the current `$wpdb`
	 * (forked test processes with their own connection).
	 *
	 * @internal
	 * @return void
	 */
	public static function reset_instance(): void {
		self::$instance = null;
	}

	/**
	 * Forget all memoized decisions (P-11, `switch_to_blog`).
	 *
	 * @return void
	 */
	public static function flush(): void {
		// Keep the instance: services hold a reference and must see the flush too.
		// Relations read `$wpdb->prefix` live, so `switch_to_blog` needs no new instance.
		if ( null !== self::$instance ) {
			self::$instance->memo = array();
		}
	}

	/**
	 * Decide.
	 *
	 * @param Actor                $actor   Who asks.
	 * @param string               $action  Meta capability (META).
	 * @param array                $args    Object IDs as for `current_user_can()`.
	 * @phpstan-param list<mixed> $args
	 * @param array<string, mixed> $context Extra context (e.g. `scope`, `module`, `reason`, `live`, `step`, `write`).
	 * @return Decision
	 */
	public function decide( Actor $actor, string $action, array $args = array(), array $context = array() ): Decision {
		$key = $actor->key() . '|' . $action . '|' . wp_json_encode( $args ) . '|' . wp_json_encode( $context );
		if ( ! isset( $this->memo[ $key ] ) ) {
			$this->memo[ $key ] = $this->evaluate( $actor, $action, $args, $context );
		}
		return $this->memo[ $key ];
	}

	/**
	 * Evaluate without memo.
	 *
	 * @param Actor                $actor   Actor.
	 * @param string               $action  Action.
	 * @param array                $args    Arguments.
	 * @phpstan-param list<mixed> $args
	 * @param array<string, mixed> $context Context.
	 * @return Decision
	 */
	private function evaluate( Actor $actor, string $action, array $args, array $context ): Decision {
		if ( $actor->is_system() ) {
			return SystemPermissions::decide( $actor, $action );
		}
		$user = $actor->user_id;
		$id   = (int) ( $args[0] ?? 0 );

		switch ( $action ) {
			case self::VIEW_COURSE:
				return $this->view_course( $user, $id );
			case self::LEARN:
				return $this->learn( $user, $id, (int) ( $context['module'] ?? $args[1] ?? 0 ), (string) ( $context['scope'] ?? $args[2] ?? 'view' ) );
			case self::EDIT_COURSE:
				return $this->edit_course( $user, $id, ! empty( $context['live'] ) );
			case self::PUBLISH_COURSE:
				return $this->with_extra( $user, $this->edit_course_caps( $user, $id ), array( 'ccap_publish_courses' ) );
			case self::DELETE_COURSE:
				return $this->delete_course( $user, $id, ! empty( $context['permanent'] ) );
			case self::EDIT_COURSE_ITEM:
				return $this->edit_course_item( $user, (string) ( $args[1] ?? $context['type'] ?? '' ), $id );
			case self::EDIT_QUESTION:
				return $this->edit_question( $user, $id );
			case self::USE_ATTEMPT:
				return $this->use_attempt( $user, $id );
			case self::VIEW_ATTEMPT:
				return $this->view_attempt( $user, $id, (string) ( $context['type'] ?? 'exam_session' ), (string) ( $context['reason'] ?? '' ) );
			case self::GRADE_ATTEMPT:
				return $this->grade_attempt( $user, $id );
			case self::GRADE_SUBMISSION:
				return $this->grade_submission( $user, $id );
			case self::VIEW_LEARNER:
				return $this->view_learner( $user, $id, (int) ( $args[1] ?? $context['course'] ?? 0 ) );
			case self::MANAGE_PROGRESS:
				return $user > 0 && $user === $id ? $this->require( $user, array( 'read' ) ) : $this->require( $user, array( 'ccap_manage_learner_progress' ), Decision::FORBIDDEN );
			case self::MANAGE_GRANT:
				return $this->manage_grant( $user, $id, (string) ( $context['operation'] ?? 'change' ) );
			case self::CREATE_GRANT:
				return $this->create_grant( $user, $id );
			case self::MANAGE_GROUP:
				return $this->manage_group( $user, $id, ! empty( $context['write'] ) );
			case self::VIEW_GROUP_PROGRESS:
				return $this->view_group_progress( $user, $id );
			case self::CLAIM_SEAT:
				return $this->claim_seat( $user, (string) ( $context['token'] ?? $args[0] ?? '' ) );
			case self::VIEW_CERTIFICATE:
				return $this->view_certificate( $user, $id );
			case self::MANAGE_CERTIFICATE:
				return $this->manage_certificate( $user, $id );
			case self::DOWNLOAD_FILE:
				return $this->download_file( $user, (string) ( $args[0] ?? '' ) );
			case self::MANAGE_IMPORT:
				return $this->manage_import( $user, $id, (string) ( $context['step'] ?? '' ) );
			case self::DOWNLOAD_EXPORT:
				return $this->download_export( $user, $context );
			case self::EXPORT_PERSONAL_DATA:
				return $this->export_personal_data( $user, $id );
			case self::LOCK_LEARNER:
				return $this->lock_learner( $user, $id );
		}
		return Decision::not_found();
	}

	/**
	 * M-01.
	 *
	 * @param int $user   User.
	 * @param int $course Course.
	 * @return Decision
	 */
	private function view_course( int $user, int $course ): Decision {
		$post = $this->relations->post( $course, Relations::COURSE_TYPE );
		if ( null === $post ) {
			return Decision::not_found();
		}
		if ( Relations::STATUS_PUBLISHED === Relations::course_status( $post ) ) {
			// `exist` is held by every visitor, including guests (catalog).
			return Decision::allow( array( 'exist' ) );
		}
		return $this->require( $user, $this->edit_course_caps( $user, $course ) );
	}

	/**
	 * M-02: learning access to a course or module with a scope.
	 *
	 * @param int    $user   User.
	 * @param int    $course Course.
	 * @param int    $module Module (0 = whole course).
	 * @param string $scope  view|download|exam.
	 * @return Decision
	 */
	private function learn( int $user, int $course, int $module, string $scope ): Decision {
		$post = $this->relations->post( $course, Relations::COURSE_TYPE );
		if ( null === $post || Relations::STATUS_PUBLISHED !== Relations::course_status( $post ) ) {
			return Decision::not_found();
		}
		$gate = $this->learner_gate( $user );
		if ( null !== $gate ) {
			return $this->filtered( $user, $course, $scope, $gate );
		}
		if ( 0 !== $module && ! in_array( $course, $this->relations->courses_of_module( $module ), true ) ) {
			return Decision::not_found();
		}
		$access = $this->relations->effective_access( $user, $course, $module, $scope );
		if ( 'ok' !== $access ) {
			return $this->filtered( $user, $course, $scope, Decision::deny( Decision::CONTENT_LOCKED, $access ) );
		}
		return $this->filtered( $user, $course, $scope, Decision::allow( array( 'read' ) ) );
	}

	/**
	 * Public filter `course_captain_can_access` on every M-02 decision
	 * (R0-19 §4.3, LB "user owns course", GM-093). It may only restrict:
	 * `false` turns an allowed decision into `ccap_content_locked`
	 * (reason `filtered`); `true` on a denied decision is ignored and
	 * audited once per request (E-84).
	 *
	 * @param int      $user     User (0 = guest).
	 * @param int      $course   Course.
	 * @param string   $scope    view|download|exam.
	 * @param Decision $decision Decision before the filter.
	 * @return Decision
	 */
	private function filtered( int $user, int $course, string $scope, Decision $decision ): Decision {
		if ( ! has_filter( 'course_captain_can_access' ) ) {
			return $decision;
		}
		$uid = (string) ( new ObjectIdentity( Database::current() ) )->uid_of( 'course', $course );
		/**
		 * Restrict learning access to a course (only restrictive, E-84).
		 *
		 * @since 1.0.0
		 * @param bool     $allowed  Decision of Course Captain (grants, lock, login).
		 * @param int      $user_id  User (0 = guest).
		 * @param string   $course   Course UID.
		 * @param string   $scope    view|download|exam.
		 * @param Decision $decision Full decision (code, reason).
		 */
		$allowed = (bool) apply_filters( 'course_captain_can_access', $decision->allowed, $user, $uid, $scope, $decision );
		if ( $decision->allowed && ! $allowed ) {
			return Decision::deny( Decision::CONTENT_LOCKED, 'filtered' );
		}
		if ( ! $decision->allowed && $allowed ) {
			$key = $user . ':' . $course . ':' . $scope;
			if ( ! isset( self::$widening_logged[ $key ] ) ) {
				self::$widening_logged[ $key ] = true;
				AuditLog::write( Database::current(), Actor::user( $user ), 'access_filter_ignored', 'course', (string) $course, 'only_restrictive', array( 'scope' => $scope ), $user > 0 ? $user : null );
			}
		}
		return $decision;
	}

	/**
	 * Common learner preconditions: logged in, member of the site, not locked.
	 *
	 * @param int $user User.
	 * @return Decision|null Denial or null.
	 */
	private function learner_gate( int $user ): ?Decision {
		if ( $user <= 0 ) {
			return Decision::deny( Decision::AUTH_REQUIRED );
		}
		if ( ! user_can( $user, 'read' ) ) {
			// No role on this site (R0-07 §3.4 b).
			return Decision::deny( Decision::ACCOUNT_LOCKED, 'no_role' );
		}
		if ( $this->relations->is_locked( $user ) ) {
			return Decision::deny( Decision::ACCOUNT_LOCKED, 'locked' );
		}
		return null;
	}

	/**
	 * Primitive caps of M-03 (empty when the course does not exist).
	 *
	 * @param int $user   User.
	 * @param int $course Course.
	 * @return list<string>
	 */
	private function edit_course_caps( int $user, int $course ): array {
		if ( null === $this->relations->post( $course, Relations::COURSE_TYPE ) ) {
			return array();
		}
		return $this->relations->is_course_author( $user, $course ) ? array( 'ccap_edit_courses' ) : array( 'ccap_edit_others_courses' );
	}

	/**
	 * M-03.
	 *
	 * @param int  $user   User.
	 * @param int  $course Course.
	 * @param bool $live   The change would go live immediately.
	 * @return Decision
	 */
	private function edit_course( int $user, int $course, bool $live ): Decision {
		$caps = $this->edit_course_caps( $user, $course );
		if ( $live ) {
			$caps[] = 'ccap_publish_courses';
		}
		return $this->require( $user, $caps );
	}

	/**
	 * M-05.
	 *
	 * @param int  $user      User.
	 * @param int  $course    Course.
	 * @param bool $permanent Permanent deletion instead of trash/archive.
	 * @return Decision
	 */
	private function delete_course( int $user, int $course, bool $permanent ): Decision {
		$decision = $this->with_extra( $user, $this->edit_course_caps( $user, $course ), array( 'ccap_delete_courses' ) );
		if ( $decision->allowed && $permanent && $this->has_history( $course ) ) {
			return Decision::deny( Decision::FORBIDDEN, 'has_history' );
		}
		return $decision;
	}

	/**
	 * Whether learning data, grants, revisions or certificates reference a course.
	 *
	 * @param int $course Course.
	 * @return bool
	 */
	private function has_history( int $course ): bool {
		$db = Database::current();
		foreach ( array( 'ccap_course_revision', 'ccap_grant', 'ccap_course_progress', 'ccap_certificate', 'ccap_exam_session' ) as $table ) {
			$name = $db->table( $table );
			if ( null !== $db->scalar( $db->prepare( "SELECT 1 FROM `{$name}` WHERE course_id = %d LIMIT 1", $course ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * M-06: rights on every course that uses the item.
	 *
	 * @param int    $user User.
	 * @param string $type module|lesson|faq|tip|exam_rule|blueprint|variant.
	 * @param int    $id   Item ID.
	 * @return Decision
	 */
	private function edit_course_item( int $user, string $type, int $id ): Decision {
		if ( in_array( $type, array( 'exam_rule', 'blueprint', 'variant' ), true ) ) {
			$course  = $this->relations->course_of_row( $type, $id );
			$courses = null === $course ? null : array( $course );
		} else {
			$post    = $this->relations->post( $id );
			$courses = null === $post ? null : $this->relations->courses_of_item( $post );
			if ( null !== $post && array() === $courses ) {
				// Item not used by any course yet: owner edits, others need the site-wide right.
				return $this->require( $user, (int) $post->post_author === $user ? array( 'ccap_edit_courses' ) : array( 'ccap_edit_others_courses' ) );
			}
		}
		if ( null === $courses || array() === $courses ) {
			return Decision::not_found();
		}
		$caps = array();
		foreach ( $courses as $course ) {
			$caps = array_merge( $caps, $this->edit_course_caps( $user, $course ) );
		}
		return $this->require( $user, $caps );
	}

	/**
	 * M-07: a shared question needs rights on all its courses.
	 *
	 * @param int $user     User.
	 * @param int $question Question.
	 * @return Decision
	 */
	private function edit_question( int $user, int $question ): Decision {
		$row = $this->relations->question( $question );
		if ( null === $row ) {
			return Decision::not_found();
		}
		$courses = $this->relations->courses_of_question( $question );
		if ( array() === $courses ) {
			return $this->require( $user, (int) $row['author_user_id'] === $user ? array( 'ccap_edit_courses' ) : array( 'ccap_manage_question_bank' ) );
		}
		foreach ( $courses as $course ) {
			if ( ! $this->relations->is_course_author( $user, $course ) ) {
				return $this->require( $user, array( 'ccap_edit_others_courses' ) );
			}
		}
		return $this->require( $user, array( 'ccap_edit_courses' ) );
	}

	/**
	 * M-08: own running attempt within its deadline and learning access now.
	 * The deadline of a write is deadline + frozen late tolerance (R0-08
	 * §6.4, GM-037/GM-038): buffered offline answers and the submit are
	 * accepted until then; ExamSessions checks the bounded answer time
	 * against deadline + grace and answers `ccap_exam_expired` (catalog).
	 *
	 * @param int $user    User.
	 * @param int $session Exam session.
	 * @return Decision
	 */
	private function use_attempt( int $user, int $session ): Decision {
		$row = $this->relations->row( 'ccap_exam_session', $session );
		if ( null === $row || (int) $row['user_id'] !== $user || $user <= 0 ) {
			return Decision::not_found();
		}
		$gate = $this->learner_gate( $user );
		if ( null !== $gate ) {
			return $gate;
		}
		if ( 'in_progress' !== $row['status'] ) {
			return Decision::deny( Decision::FORBIDDEN, 'not_running' );
		}
		if ( null !== $row['deadline_at'] ) {
			$snapshot  = json_decode( (string) ( $row['snapshot'] ?? '' ), true );
			$tolerance = is_array( $snapshot ) ? (int) ( $snapshot['timing']['late_tolerance_seconds'] ?? 120 ) : 120;
			if ( (int) strtotime( (string) $row['deadline_at'] . ' UTC' ) + max( 0, $tolerance ) <= Database::current()->time() ) {
				return Decision::deny( Decision::FORBIDDEN, 'deadline_passed' );
			}
		}
		return $this->learn( $user, (int) $row['course_id'], 0, 'exam' );
	}

	/**
	 * M-09.
	 *
	 * @param int    $user   User.
	 * @param int    $id     Exam session or learning attempt.
	 * @param string $type   exam_session|attempt.
	 * @param string $reason Justification for reading foreign details (E-82).
	 * @return Decision
	 */
	private function view_attempt( int $user, int $id, string $type, string $reason ): Decision {
		$row = $this->relations->row( 'attempt' === $type ? 'ccap_attempt' : 'ccap_exam_session', $id );
		if ( null === $row ) {
			return Decision::not_found();
		}
		$owner  = (int) $row['user_id'];
		$course = (int) ( $row['course_id'] ?? 0 );
		if ( $owner === $user && $user > 0 ) {
			return $this->require( $user, array( 'read' ) );
		}
		$in_review = 'exam_session' === $type && in_array( $row['status'], array( 'submitted', 'evaluated' ), true );
		if ( $in_review && $this->relations->trainer_covers_learner( $user, $owner, $course ) && user_can( $user, 'ccap_grade_attempts' ) ) {
			return Decision::allow( array( 'ccap_grade_attempts' ) );
		}
		if ( ! user_can( $user, 'ccap_view_attempt_details' ) ) {
			return Decision::not_found();
		}
		if ( '' === trim( $reason ) ) {
			return Decision::deny( Decision::FORBIDDEN, 'reason_required' );
		}
		return Decision::allow( array( 'ccap_view_attempt_details' ), true );
	}

	/**
	 * M-10: never the own attempt.
	 *
	 * @param int $user    User.
	 * @param int $session Exam session.
	 * @return Decision
	 */
	private function grade_attempt( int $user, int $session ): Decision {
		$row = $this->relations->row( 'ccap_exam_session', $session );
		if ( null === $row ) {
			return Decision::not_found();
		}
		if ( (int) $row['user_id'] === $user ) {
			return Decision::deny( Decision::FORBIDDEN, 'self_grading' );
		}
		if ( $this->relations->trainer_covers_learner( $user, (int) $row['user_id'], (int) $row['course_id'] ) ) {
			return $this->require( $user, array( 'ccap_grade_attempts' ) );
		}
		return $this->require( $user, array( 'ccap_grade_attempts', 'ccap_edit_others_courses' ) );
	}

	/**
	 * M-10a (E-288): trainer, author or course manager of the course.
	 *
	 * @param int $user       User.
	 * @param int $submission Assignment submission.
	 * @return Decision
	 */
	private function grade_submission( int $user, int $submission ): Decision {
		$row = $this->relations->row( 'ccap_assignment_submission', $submission );
		if ( null === $row ) {
			return Decision::not_found();
		}
		if ( (int) $row['user_id'] === $user ) {
			return Decision::deny( Decision::FORBIDDEN, 'self_grading' );
		}
		$course = (int) $row['course_id'];
		if ( $this->relations->trainer_covers_learner( $user, (int) $row['user_id'], $course ) && user_can( $user, 'ccap_grade_attempts' ) ) {
			return Decision::allow( array( 'ccap_grade_attempts' ), true );
		}
		if ( $this->relations->is_course_author( $user, $course ) && user_can( $user, 'ccap_edit_courses' ) ) {
			return Decision::allow( array( 'ccap_edit_courses' ), true );
		}
		$decision = $this->require( $user, array( 'ccap_grade_attempts', 'ccap_edit_others_courses' ) );
		return $decision->allowed ? Decision::allow( $decision->caps, true ) : $decision;
	}

	/**
	 * M-11: self, assigned trainer, site-wide staff; never the group owner.
	 *
	 * @param int $user    User.
	 * @param int $learner Learner.
	 * @param int $course  Course (0 = any).
	 * @return Decision
	 */
	private function view_learner( int $user, int $learner, int $course ): Decision {
		if ( $learner <= 0 || false === get_userdata( $learner ) ) {
			return Decision::not_found();
		}
		if ( $learner === $user ) {
			return $this->require( $user, array( 'read' ) );
		}
		if ( self::has_site_wide_learner_scope( $user ) ) {
			return Decision::allow( array( 'ccap_view_learner_details' ) );
		}
		if ( user_can( $user, 'ccap_view_learner_details' ) ) {
			$courses = 0 === $course ? $this->trainer_courses( $user ) : array( $course );
			foreach ( $courses as $candidate ) {
				if ( $this->relations->trainer_covers_learner( $user, $learner, $candidate ) ) {
					return Decision::allow( array( 'ccap_view_learner_details' ) );
				}
			}
		}
		return Decision::not_found();
	}

	/**
	 * Site-wide learner scope (course manager, support, admin – E-80):
	 * learner details plus a site-wide management right. Trainers only
	 * see learners of their assigned courses/groups.
	 *
	 * @param int $user User.
	 * @return bool
	 */
	public static function has_site_wide_learner_scope( int $user ): bool {
		return user_can( $user, 'ccap_view_learner_details' ) && ( user_can( $user, 'ccap_manage_grants' ) || user_can( $user, 'ccap_lock_learners' ) );
	}

	/**
	 * Courses a user is assigned to as trainer.
	 *
	 * @param int $user User.
	 * @return list<int>
	 */
	private function trainer_courses( int $user ): array {
		$db    = Database::current();
		$table = $db->table( 'ccap_course_staff' );
		$rows  = $db->rows( $db->prepare( "SELECT DISTINCT course_id FROM `{$table}` WHERE user_id = %d AND role = 'trainer'", $user ) );
		return array_map( static fn( array $row ): int => (int) $row['course_id'], $rows );
	}

	/**
	 * M-13.
	 *
	 * @param int    $user      User.
	 * @param int    $grant     Grant.
	 * @param string $operation change|delete.
	 * @return Decision
	 */
	private function manage_grant( int $user, int $grant, string $operation ): Decision {
		$row = $this->relations->row( 'ccap_grant', $grant );
		if ( null === $row ) {
			return Decision::not_found();
		}
		$decision = $this->require( $user, array( 'ccap_manage_grants' ) );
		if ( $decision->allowed && 'delete' === $operation && ! in_array( $row['source_type'], array( 'manual', 'group_seat' ), true ) ) {
			// Grants of external sources can only be suspended or annotated (R0-09).
			return Decision::deny( Decision::FORBIDDEN, 'external_source' );
		}
		return $decision;
	}

	/**
	 * M-14.
	 *
	 * @param int $user   User.
	 * @param int $course Course.
	 * @return Decision
	 */
	private function create_grant( int $user, int $course ): Decision {
		$view = $this->view_course( $user, $course );
		if ( ! $view->allowed ) {
			return $view;
		}
		return $this->require( $user, array( 'ccap_manage_grants' ), Decision::FORBIDDEN );
	}

	/**
	 * M-15.
	 *
	 * @param int  $user  User.
	 * @param int  $group Group.
	 * @param bool $write Changing request.
	 * @return Decision
	 */
	private function manage_group( int $user, int $group, bool $write ): Decision {
		$row = $this->relations->row( 'ccap_group', $group );
		if ( null === $row ) {
			return Decision::not_found();
		}
		if ( $user > 0 && (int) $row['owner_user_id'] === $user ) {
			$gate = $this->learner_gate( $user );
			if ( null !== $gate ) {
				return $gate;
			}
			$decision = Decision::allow( array( 'read' ) );
		} elseif ( user_can( $user, 'ccap_manage_groups' ) ) {
			$decision = Decision::allow( array( 'ccap_manage_groups' ) );
		} else {
			return Decision::not_found();
		}
		if ( $write && 'revoked' === $row['status'] ) {
			return Decision::deny( Decision::FORBIDDEN, 'group_revoked' );
		}
		return $decision;
	}

	/**
	 * M-16: never the group owner in Free (E-232 is Pro).
	 *
	 * @param int $user  User.
	 * @param int $group Group.
	 * @return Decision
	 */
	private function view_group_progress( int $user, int $group ): Decision {
		$row = $this->relations->row( 'ccap_group', $group );
		if ( null === $row ) {
			return Decision::not_found();
		}
		if ( (int) $row['owner_user_id'] === $user && ! user_can( $user, 'ccap_manage_groups' ) ) {
			return Decision::deny( Decision::FORBIDDEN, 'owner_no_progress' );
		}
		$db     = Database::current();
		$staff  = $db->table( 'ccap_course_staff' );
		$direct = $db->scalar( $db->prepare( "SELECT 1 FROM `{$staff}` WHERE user_id = %d AND role = 'trainer' AND group_id = %d LIMIT 1", $user, $group ) );
		if ( null !== $direct ) {
			return $this->require( $user, array( 'ccap_view_reports' ), Decision::FORBIDDEN );
		}
		return $this->require( $user, array( 'ccap_view_reports', 'ccap_manage_groups' ), Decision::FORBIDDEN );
	}

	/**
	 * M-17: token valid, group active, free seat, optional address binding (E-28).
	 *
	 * @param int    $user  User.
	 * @param string $token Raw claim or join token.
	 * @return Decision
	 */
	private function claim_seat( int $user, string $token ): Decision {
		$gate = $this->learner_gate( $user );
		if ( null !== $gate ) {
			return $gate;
		}
		if ( '' === $token ) {
			return Decision::deny( Decision::FORBIDDEN, 'invalid_token' );
		}
		$target = $this->relations->claim_target( $token );
		$group  = $target['group'];
		if ( null === $group ) {
			return Decision::deny( Decision::FORBIDDEN, 'invalid_token' );
		}
		$now = Database::current()->now();
		if ( 'active' !== $group['status'] || ( null !== $group['valid_until'] && (string) $group['valid_until'] <= $now ) || ( null !== $group['valid_from'] && (string) $group['valid_from'] > $now ) ) {
			return Decision::deny( Decision::FORBIDDEN, 'group_not_active' );
		}
		$seat = $target['seat'];
		if ( null !== $seat ) {
			if ( 'active' === $seat['status'] && (int) $seat['user_id'] === $user ) {
				return Decision::allow( array( 'read' ) );
			}
			if ( 'invited' !== $seat['status'] ) {
				return Decision::deny( Decision::FORBIDDEN, 'claim_used' );
			}
			if ( null !== $seat['claim_expires_at'] && (string) $seat['claim_expires_at'] <= $now ) {
				return Decision::deny( Decision::FORBIDDEN, 'claim_expired' );
			}
			if ( 'invited_address_only' === $group['claim_policy'] ) {
				$account = get_userdata( $user );
				if ( false === $account || 0 !== strcasecmp( (string) $account->user_email, (string) $seat['invite_email'] ) ) {
					return Decision::deny( Decision::FORBIDDEN, 'invite_other_account' );
				}
			}
			return Decision::allow( array( 'read' ) );
		}
		if ( $this->relations->active_seats( (int) $group['id'] ) >= (int) $group['seats'] ) {
			return Decision::deny( Decision::FORBIDDEN, 'group_full' );
		}
		return Decision::allow( array( 'read' ) );
	}

	/**
	 * M-18: owner (unless locked), certificate managers, or learner viewers.
	 *
	 * @param int $user        User.
	 * @param int $certificate Certificate row.
	 * @return Decision
	 */
	private function view_certificate( int $user, int $certificate ): Decision {
		$row = $this->relations->row( 'ccap_certificate', $certificate );
		if ( null === $row ) {
			return Decision::not_found();
		}
		if ( (int) $row['user_id'] === $user && $user > 0 ) {
			// History stays readable after the grant ended (35.4); a lock blocks the download.
			return $this->learner_gate( $user ) ?? Decision::allow( array( 'read' ) );
		}
		if ( user_can( $user, 'ccap_manage_certificates' ) ) {
			return Decision::allow( array( 'ccap_manage_certificates' ) );
		}
		return $this->view_learner( $user, (int) $row['user_id'], (int) $row['course_id'] );
	}

	/**
	 * M-19: never the own certificate.
	 *
	 * @param int $user        User.
	 * @param int $certificate Certificate row.
	 * @return Decision
	 */
	private function manage_certificate( int $user, int $certificate ): Decision {
		$row = $this->relations->row( 'ccap_certificate', $certificate );
		if ( null === $row ) {
			return Decision::not_found();
		}
		if ( (int) $row['user_id'] === $user ) {
			return Decision::deny( Decision::FORBIDDEN, 'own_certificate' );
		}
		return $this->require( $user, array( 'ccap_manage_certificates' ), Decision::FORBIDDEN );
	}

	/**
	 * M-20: protected file via an editable course or learning access to a
	 * published referencing course (exam-only media: R1-14).
	 *
	 * @param int    $user User.
	 * @param string $uid  Media UID.
	 * @return Decision
	 */
	private function download_file( int $user, string $uid ): Decision {
		$media = '' === $uid ? null : $this->relations->media_by_uid( $uid );
		if ( null === $media || 'protected' !== $media['access'] || 'active' !== $media['status'] ) {
			return Decision::not_found();
		}
		$courses = $this->relations->courses_of_media( (int) $media['id'] );
		$visible = false;
		$denial  = null;
		foreach ( $courses as $course ) {
			$edit = $this->require( $user, $this->edit_course_caps( $user, $course ) );
			if ( $edit->allowed ) {
				return $edit;
			}
			$post = $this->relations->post( $course, Relations::COURSE_TYPE );
			if ( null === $post || Relations::STATUS_PUBLISHED !== Relations::course_status( $post ) ) {
				continue;
			}
			$visible = true;
			$learn   = $this->learn( $user, $course, 0, 'download' );
			if ( $learn->allowed ) {
				return $learn;
			}
			$denial = $denial ?? $learn;
		}
		return $visible && null !== $denial ? $denial : Decision::not_found();
	}

	/**
	 * M-21.
	 *
	 * @param int    $user  User.
	 * @param int    $batch Import batch.
	 * @param string $step  Requested step.
	 * @return Decision
	 */
	private function manage_import( int $user, int $batch, string $step ): Decision {
		if ( null === $this->relations->row( 'ccap_import_batch', $batch ) ) {
			return Decision::not_found();
		}
		$caps = array( 'ccap_import_content' );
		if ( in_array( $step, array( 'publish', 'activate' ), true ) ) {
			$caps[] = 'ccap_publish_courses';
		}
		return $this->require( $user, $caps, Decision::FORBIDDEN );
	}

	/**
	 * M-22. The export record (R1-15/R1-17) is passed in the context:
	 * `created_by`, `expires_at` (UTC), `kind` (content|report|grants).
	 *
	 * @param int                  $user    User.
	 * @param array<string, mixed> $context Export record.
	 * @return Decision
	 */
	private function download_export( int $user, array $context ): Decision {
		if ( (int) ( $context['created_by'] ?? 0 ) !== $user || $user <= 0 ) {
			return Decision::not_found();
		}
		$expires = (string) ( $context['expires_at'] ?? '' );
		if ( '' === $expires || $expires <= Database::current()->now() ) {
			return Decision::deny( Decision::FORBIDDEN, 'export_expired' );
		}
		$map = array(
			'content' => 'ccap_export_content',
			'report'  => 'ccap_view_reports',
			'grants'  => 'ccap_manage_grants',
		);
		$cap = $map[ (string) ( $context['kind'] ?? '' ) ] ?? null;
		return null === $cap ? Decision::not_found() : $this->require( $user, array( $cap ), Decision::FORBIDDEN );
	}

	/**
	 * M-23: always for the own account (data subject rights, also when locked).
	 *
	 * @param int $user    User.
	 * @param int $subject Data subject.
	 * @return Decision
	 */
	private function export_personal_data( int $user, int $subject ): Decision {
		if ( $user > 0 && $user === $subject ) {
			return Decision::allow( array( 'exist' ) );
		}
		return $this->require( $user, array( 'export_others_personal_data' ), Decision::FORBIDDEN );
	}

	/**
	 * M-24: never oneself, never someone with more rights.
	 *
	 * @param int $user   User.
	 * @param int $target Target user.
	 * @return Decision
	 */
	private function lock_learner( int $user, int $target ): Decision {
		$account = $target > 0 ? get_userdata( $target ) : false;
		if ( false === $account ) {
			return Decision::not_found();
		}
		if ( $target === $user ) {
			return Decision::deny( Decision::FORBIDDEN, 'self_lock' );
		}
		foreach ( self::sensitive_caps() as $cap ) {
			if ( user_can( $target, $cap ) && ! user_can( $user, $cap ) ) {
				return Decision::deny( Decision::FORBIDDEN, 'higher_privileges' );
			}
		}
		return $this->require( $user, array( 'ccap_lock_learners' ), Decision::FORBIDDEN );
	}

	/**
	 * Capabilities compared for escalation checks.
	 *
	 * @return list<string>
	 */
	public static function sensitive_caps(): array {
		return array_merge( \CourseCaptain\Kernel\Capabilities::PRIMITIVE, array( 'manage_options', 'promote_users', 'manage_network' ) );
	}

	/**
	 * Allow when the user holds all caps; else deny with the given code.
	 *
	 * @param int    $user User.
	 * @param array  $caps Primitive caps; empty = object missing.
	 * @phpstan-param list<string> $caps
	 * @param string $code Denial code.
	 * @return Decision
	 */
	private function require( int $user, array $caps, string $code = Decision::NOT_FOUND ): Decision {
		if ( array() === $caps || $user <= 0 ) {
			return array() === $caps ? Decision::not_found() : Decision::deny( Decision::AUTH_REQUIRED );
		}
		foreach ( $caps as $cap ) {
			if ( ! user_can( $user, $cap ) ) {
				return Decision::deny( $code );
			}
		}
		return Decision::allow( $caps );
	}

	/**
	 * M-03 caps plus extra caps; the object must exist.
	 *
	 * @param int   $user  User.
	 * @param array $caps  Base caps (empty = object missing).
	 * @param array $extra Extra caps.
	 * @phpstan-param list<string> $caps
	 * @phpstan-param list<string> $extra
	 * @return Decision
	 */
	private function with_extra( int $user, array $caps, array $extra ): Decision {
		if ( array() === $caps ) {
			return Decision::not_found();
		}
		$base = $this->require( $user, $caps );
		if ( ! $base->allowed ) {
			return $base;
		}
		return $this->require( $user, array_merge( $caps, $extra ), Decision::FORBIDDEN );
	}
}
