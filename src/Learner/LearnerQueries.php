<?php
/**
 * Read models of the learning area (R0-19 R-05, R-06, R-26; R0-05 GM-001, GM-091).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Progress\CourseProgress;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Only published courses are listed (GM-001); every lesson carries the
 * gate decision of now (locked lessons show title and reason only). The
 * access state follows GM-091: active, limited (partial access), then
 * suspended > expired > pending > not_granted.
 */
final class LearnerQueries {

	/**
	 * Constructor.
	 *
	 * @param Database    $db   Database access.
	 * @param ContentGate $gate Gate.
	 */
	public function __construct( private Database $db, private ContentGate $gate ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), ContentGate::current() );
	}

	/**
	 * Published courses (catalog).
	 *
	 * @param int    $limit   Max.
	 * @param string $orderby title|date|menu_order.
	 * @return list<\WP_Post>
	 */
	public function catalog( int $limit = 12, string $orderby = 'menu_order' ): array {
		$posts = get_posts(
			array(
				'post_type'        => Relations::COURSE_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => max( 1, min( 100, $limit ) ),
				'orderby'          => in_array( $orderby, array( 'title', 'date', 'menu_order' ), true ) ? array(
					$orderby => 'date' === $orderby ? 'DESC' : 'ASC',
					'title'  => 'ASC',
				) : array( 'title' => 'ASC' ),
				'suppress_filters' => true,
			)
		);
		return array_values( array_filter( $posts, static fn( $p ): bool => $p instanceof \WP_Post && '' === $p->post_password ) );
	}

	/**
	 * "My learning" (R-05): courses with any grant or access, state and progress.
	 *
	 * @param Actor $actor Learner.
	 * @return list<array{course_id: int, course_uid: string, title: string, access: array{state: string, valid_until: string|null}, progress: array{completion_percent: int, status: string}, resume: array{lesson_id: int, title: string}|null}>
	 */
	public function my_courses( Actor $actor ): array {
		if ( $actor->user_id <= 0 ) {
			return array();
		}
		// R0-14 OP-01: a fixed number of queries independent of the number of courses (no N+1).
		$grants = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT course_id, module_id, status, valid_from, valid_until FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE user_id = %d', $actor->user_id ) ) as $row ) {
			$grants[ (int) $row['course_id'] ][] = $row;
		}
		$ids = array_keys( $grants );
		if ( array() === $ids ) {
			return array();
		}
		_prime_post_caches( $ids, false, true );
		$in       = implode( ',', array_map( 'intval', $ids ) );
		$progress = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT course_id, status, completion_ratio, last_activity_at FROM `' . $this->db->table( 'ccap_course_progress' ) . "` WHERE user_id = %d AND course_id IN ({$in})", $actor->user_id ) ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
			$progress[ (int) $row['course_id'] ] = $row;
		}
		$uids = array();
		foreach ( $this->db->rows( 'SELECT object_id, uid FROM `' . $this->db->table( 'ccap_source_map' ) . "` WHERE object_type = 'course' AND object_id IN ({$in})" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
			$uids[ (int) $row['object_id'] ] = (string) $row['uid'];
		}
		$out    = array();
		$latest = null;
		foreach ( $ids as $course_id ) {
			$post = get_post( $course_id );
			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
				continue;
			}
			$access = $this->access_from_rows( $actor, $course_id, $grants[ $course_id ] );
			$row    = $progress[ $course_id ] ?? null;
			$out[]  = array(
				'course_id'  => $course_id,
				'course_uid' => $uids[ $course_id ] ?? (string) ( new ObjectIdentity( $this->db ) )->ensure( $post ),
				'title'      => $post->post_title,
				'access'     => $access,
				'progress'   => array(
					'completion_percent' => null === $row ? 0 : (int) floor( (float) $row['completion_ratio'] * 100 ),
					'status'             => null === $row ? 'not_started' : (string) $row['status'],
				),
				'resume'     => null,
			);
			if ( in_array( $access['state'], array( 'active', 'limited' ), true ) && ( null === $latest || (string) ( $row['last_activity_at'] ?? '' ) > $latest['at'] ) ) {
				$latest = array(
					'course' => $course_id,
					'at'     => (string) ( $row['last_activity_at'] ?? '' ),
				);
			}
		}
		// "Continue" is computed for the most recently used course only (one course room, constant cost).
		if ( null !== $latest ) {
			$resume = $this->resume( $actor, $latest['course'] );
			foreach ( $out as $i => $entry ) {
				if ( $entry['course_id'] === $latest['course'] ) {
					$entry['resume'] = $resume;
					$out[ $i ]       = $entry;
				}
			}
		}
		usort( $out, static fn( array $a, array $b ): int => strcmp( $a['title'], $b['title'] ) );
		return $out;
	}


	/**
	 * Access state of a course (GM-091).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return array{state: string, valid_until: string|null}
	 */
	public function access_state( Actor $actor, int $course_id ): array {
		return $this->access_from_rows( $actor, $course_id, $this->db->rows( $this->db->prepare( 'SELECT module_id, status, valid_from, valid_until FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE user_id = %d AND course_id = %d', $actor->user_id, $course_id ) ) );
	}

	/**
	 * Access state from already loaded grant rows of one course.
	 *
	 * @param Actor                            $actor     Learner.
	 * @param int                              $course_id Course.
	 * @param array<int, array<string, mixed>> $rows      Grant rows (module_id, status, valid_from, valid_until).
	 * @return array{state: string, valid_until: string|null}
	 */
	private function access_from_rows( Actor $actor, int $course_id, array $rows ): array {
		$now       = $this->db->now();
		$found     = array();
		$until     = null;
		$permanent = false;
		foreach ( $rows as $row ) {
			$status = (string) $row['status'];
			if ( 'active' === $status && null !== $row['valid_until'] && (string) $row['valid_until'] <= $now ) {
				$status = 'expired';
			} elseif ( 'active' === $status && null !== $row['valid_from'] && (string) $row['valid_from'] > $now ) {
				$status = 'pending';
			} elseif ( str_starts_with( $status, 'pending' ) ) {
				$status = 'pending';
			}
			if ( 'active' === $status ) {
				$status = 0 === (int) $row['module_id'] ? 'active' : 'limited';
				// The latest end wins; a source without end makes the access permanent (GM-092).
				$permanent = $permanent || null === $row['valid_until'];
				$until     = null === $row['valid_until'] ? $until : max( (string) $until, (string) $row['valid_until'] );
			}
			$found[ $status ] = true;
		}
		foreach ( array( 'active', 'limited', 'suspended', 'expired', 'pending' ) as $state ) {
			if ( isset( $found[ $state ] ) ) {
				return array(
					'state'       => $state,
					'valid_until' => in_array( $state, array( 'active', 'limited' ), true ) && ! $permanent ? $until : null,
				);
			}
		}
		$trial = $this->gate->module( $actor, $course_id, 0, 'view', null );
		return array(
			'state'       => $trial->allowed() ? ( null === $trial->limit ? 'active' : 'limited' ) : 'not_granted',
			'valid_until' => null,
		);
	}

	/**
	 * Course room (R-06/R-26): modules and lessons with gate decisions.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return array{course_id: int, title: string, access: array{state: string, valid_until: string|null}, progress: array<string, mixed>|null, modules: list<array{module_id: int, title: string, locked_reason: string|null, unlock_at: string|null, lessons: list<array{lesson_id: int, title: string, type: string, state: string, locked_reason: string|null}>}>, resume: array{lesson_id: int, title: string}|null}
	 * @throws CourseException 404 for unknown or unpublished courses.
	 */
	public function course( Actor $actor, int $course_id ): array {
		$post  = $this->published_course( $course_id );
		$room  = $this->modules_of( $actor, $course_id );
		$state = $room['states'];
		return array(
			'course_id' => $course_id,
			'title'     => $post->post_title,
			'access'    => $this->access_state( $actor, $course_id ),
			'progress'  => $actor->user_id > 0 ? CourseProgress::current()->get( $actor->user_id, $course_id ) : null,
			'modules'   => $room['modules'],
			'resume'    => $this->resume_from( $room['modules'], $state ),
		);
	}

	/**
	 * Published course post or 404.
	 *
	 * @param int $course_id Course.
	 * @return \WP_Post
	 * @throws CourseException 404 for unknown or unpublished courses.
	 */
	private function published_course( int $course_id ): \WP_Post {
		$post = get_post( $course_id );
		if ( ! $post instanceof \WP_Post || Relations::COURSE_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		return $post;
	}

	/**
	 * Modules and lessons of the course room with gate decisions and the
	 * learner's lesson states. R0-14 OP-01/OP-02: lesson states and the
	 * last opened lesson come from one query for all lessons, module
	 * prerequisites and drip rules from one query per course (gate memo).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return array{modules: list<array{module_id: int, title: string, locked_reason: string|null, unlock_at: string|null, lessons: list<array{lesson_id: int, title: string, type: string, state: string, locked_reason: string|null}>}>, states: array<int, string>}
	 */
	private function modules_of( Actor $actor, int $course_id ): array {
		return PolicyService::instance()->batch( fn(): array => $this->build_modules( $actor, $course_id ) );
	}

	/**
	 * Body of `modules_of()` (inside one policy batch).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return array{modules: list<array{module_id: int, title: string, locked_reason: string|null, unlock_at: string|null, lessons: list<array{lesson_id: int, title: string, type: string, state: string, locked_reason: string|null}>}>, states: array<int, string>}
	 */
	private function build_modules( Actor $actor, int $course_id ): array {
		$found = array();
		$ids   = array();
		// Module order comes with the gate's course query (prerequisites, drip) – no second read.
		$module_ids = array_keys( $this->gate->course_modules( $course_id ) );
		_prime_post_caches( $module_ids, false, false );
		$by_module = CourseStructure::current()->lessons_of( $module_ids );
		foreach ( $module_ids as $id ) {
			$module = get_post( $id );
			if ( ! $module instanceof \WP_Post || 'publish' !== $module->post_status ) {
				continue;
			}
			$lessons = array();
			foreach ( $by_module[ $id ] ?? array() as $lesson ) {
				if ( 'publish' === $lesson->post_status ) {
					$lessons[] = $lesson;
					$ids[]     = (int) $lesson->ID;
				}
			}
			$found[] = array( $id, $module, $lessons );
		}
		$states  = $this->lesson_states( $actor->user_id, $ids );
		$modules = array();
		foreach ( $found as list( $module_id, $module, $posts ) ) {
			$gate    = $this->gate->module( $actor, $course_id, $module_id, 'view', null );
			$lessons = array();
			foreach ( $posts as $lesson ) {
				$lesson_gate = $gate->allowed() ? $this->gate->lesson( $actor, $course_id, (int) $lesson->ID ) : $gate;
				$type        = (string) get_post_meta( (int) $lesson->ID, ObjectIdentity::ACTIVITY_TYPE_META, true );
				$status      = $states[ (int) $lesson->ID ] ?? null;
				$lessons[]   = array(
					'lesson_id'     => (int) $lesson->ID,
					'title'         => $lesson->post_title,
					'type'          => '' === $type ? 'lesson' : $type,
					'state'         => null === $status ? 'not_started' : ( 'completed' === $status ? 'completed' : 'in_progress' ),
					'locked_reason' => $lesson_gate->allowed() ? null : (string) $lesson_gate->reason,
				);
			}
			$modules[] = array(
				'module_id'     => $module_id,
				'title'         => $module->post_title,
				'locked_reason' => $gate->allowed() ? null : (string) $gate->reason,
				'unlock_at'     => $gate->unlock_at,
				'lessons'       => $lessons,
			);
		}
		return array(
			'modules' => $modules,
			'states'  => $states,
		);
	}

	/**
	 * Progress status per lesson, most recently opened first (one query).
	 *
	 * @param int   $user Learner.
	 * @param array $ids  Lessons.
	 * @phpstan-param list<int> $ids
	 * @return array<int, string>
	 */
	private function lesson_states( int $user, array $ids ): array {
		if ( $user <= 0 || array() === $ids ) {
			return array();
		}
		$in  = implode( ',', array_map( 'intval', $ids ) );
		$out = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT activity_id, status FROM `' . $this->db->table( 'ccap_activity_progress' ) . "` WHERE user_id = %d AND activity_id IN ({$in}) ORDER BY last_access_at DESC, id DESC", $user ) ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
			$out[ (int) $row['activity_id'] ] ??= (string) $row['status'];
		}
		return $out;
	}

	/**
	 * Resume target: last opened unfinished lesson, else the first open unlocked one.
	 *
	 * @param Actor                           $actor     Learner.
	 * @param int                             $course_id Course.
	 * @param list<array<string, mixed>>|null $modules Course room modules (computed when null).
	 * @return array{lesson_id: int, title: string}|null
	 */
	public function resume( Actor $actor, int $course_id, ?array $modules = null ): ?array {
		if ( $actor->user_id <= 0 ) {
			return null;
		}
		if ( null === $modules ) {
			$this->published_course( $course_id );
			$room = $this->modules_of( $actor, $course_id );
			return $this->resume_from( $room['modules'], $room['states'] );
		}
		$ids = array();
		foreach ( $modules as $module ) {
			foreach ( (array) $module['lessons'] as $lesson ) {
				$ids[] = (int) $lesson['lesson_id'];
			}
		}
		return $this->resume_from( $modules, $this->lesson_states( $actor->user_id, $ids ) );
	}

	/**
	 * Resume target from the room and the lesson states (ordered by last access).
	 *
	 * @param list<array<string, mixed>> $modules Course room modules.
	 * @param array<int, string>         $states  Lesson states, most recently opened first.
	 * @return array{lesson_id: int, title: string}|null
	 */
	private function resume_from( array $modules, array $states ): ?array {
		$open = array();
		foreach ( $modules as $module ) {
			foreach ( (array) $module['lessons'] as $lesson ) {
				if ( null === $lesson['locked_reason'] && 'completed' !== $lesson['state'] ) {
					$open[ (int) $lesson['lesson_id'] ] = (string) $lesson['title'];
				}
			}
		}
		if ( array() === $open ) {
			return null;
		}
		$id = null;
		foreach ( array_keys( $states ) as $lesson_id ) {
			if ( isset( $open[ $lesson_id ] ) ) {
				$id = $lesson_id;
				break;
			}
		}
		$id = $id ?? (int) array_key_first( $open );
		return array(
			'lesson_id' => $id,
			'title'     => $open[ $id ],
		);
	}

	/**
	 * Course by ID or UID.
	 *
	 * @param string $ref Numeric ID or UUID.
	 * @return int 0 when unknown.
	 */
	public function course_id( string $ref ): int {
		if ( 1 === preg_match( '/^\d+$/', $ref ) ) {
			return (int) $ref;
		}
		return (int) ( new ObjectIdentity( $this->db ) )->post_id_of( 'course', $ref );
	}
}
