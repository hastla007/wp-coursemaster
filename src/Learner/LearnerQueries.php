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
		$ids = array_map(
			'intval',
			$this->db->wpdb()->get_col( $this->db->prepare( 'SELECT DISTINCT course_id FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE user_id = %d', $actor->user_id ) )
		);
		$out = array();
		foreach ( $ids as $course_id ) {
			$post = get_post( $course_id );
			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
				continue;
			}
			$access   = $this->access_state( $actor, $course_id );
			$progress = CourseProgress::current()->get( $actor->user_id, $course_id );
			$out[]    = array(
				'course_id'  => $course_id,
				'course_uid' => (string) ( new ObjectIdentity( $this->db ) )->ensure( $post ),
				'title'      => $post->post_title,
				'access'     => $access,
				'progress'   => array(
					'completion_percent' => null === $progress ? 0 : (int) floor( (float) $progress['completion_ratio'] * 100 ),
					'status'             => null === $progress ? 'not_started' : (string) $progress['status'],
				),
				'resume'     => in_array( $access['state'], array( 'active', 'limited' ), true ) ? $this->resume( $actor, $course_id ) : null,
			);
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
		$rows  = $this->db->rows( $this->db->prepare( 'SELECT module_id, status, valid_from, valid_until FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE user_id = %d AND course_id = %d', $actor->user_id, $course_id ) );
		$now   = $this->db->now();
		$found = array();
		$until = null;
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
				$until  = null === $row['valid_until'] ? $until : max( (string) $until, (string) $row['valid_until'] );
			}
			$found[ $status ] = true;
		}
		foreach ( array( 'active', 'limited', 'suspended', 'expired', 'pending' ) as $state ) {
			if ( isset( $found[ $state ] ) ) {
				return array(
					'state'       => $state,
					'valid_until' => in_array( $state, array( 'active', 'limited' ), true ) ? $until : null,
				);
			}
		}
		$trial = $this->gate->module( $actor, $course_id, 0 );
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
		$post = get_post( $course_id );
		if ( ! $post instanceof \WP_Post || Relations::COURSE_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		$modules = array();
		foreach ( CourseStructure::current()->modules( $course_id ) as $link ) {
			$module = get_post( $link['module_id'] );
			if ( ! $module instanceof \WP_Post || 'publish' !== $module->post_status ) {
				continue;
			}
			$gate    = $this->gate->module( $actor, $course_id, $link['module_id'] );
			$lessons = array();
			foreach ( CourseStructure::current()->lessons( $link['module_id'] ) as $lesson ) {
				if ( 'publish' !== $lesson->post_status ) {
					continue;
				}
				$lesson_gate = $gate->allowed() ? $this->gate->lesson( $actor, $course_id, (int) $lesson->ID ) : $gate;
				$type        = (string) get_post_meta( (int) $lesson->ID, ObjectIdentity::ACTIVITY_TYPE_META, true );
				$lessons[]   = array(
					'lesson_id'     => (int) $lesson->ID,
					'title'         => $lesson->post_title,
					'type'          => '' === $type ? 'lesson' : $type,
					'state'         => $this->lesson_state( $actor->user_id, (int) $lesson->ID ),
					'locked_reason' => $lesson_gate->allowed() ? null : (string) $lesson_gate->reason,
				);
			}
			$modules[] = array(
				'module_id'     => $link['module_id'],
				'title'         => $module->post_title,
				'locked_reason' => $gate->allowed() ? null : (string) $gate->reason,
				'unlock_at'     => $gate->unlock_at,
				'lessons'       => $lessons,
			);
		}
		return array(
			'course_id' => $course_id,
			'title'     => $post->post_title,
			'access'    => $this->access_state( $actor, $course_id ),
			'progress'  => $actor->user_id > 0 ? CourseProgress::current()->get( $actor->user_id, $course_id ) : null,
			'modules'   => $modules,
			'resume'    => $this->resume( $actor, $course_id, $modules ),
		);
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
		$modules = $modules ?? $this->course( $actor, $course_id )['modules'];
		$open    = array();
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
		$ids  = implode( ',', array_keys( $open ) );
		$last = $this->db->scalar( $this->db->prepare( 'SELECT activity_id FROM `' . $this->db->table( 'ccap_activity_progress' ) . "` WHERE user_id = %d AND activity_id IN ({$ids}) ORDER BY last_access_at DESC, id DESC LIMIT 1", $actor->user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		$id   = null === $last ? (int) array_key_first( $open ) : (int) $last;
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

	/**
	 * Completion state of a lesson for a learner.
	 *
	 * @param int $user   Learner.
	 * @param int $lesson Lesson.
	 * @return string not_started|in_progress|completed
	 */
	private function lesson_state( int $user, int $lesson ): string {
		if ( $user <= 0 ) {
			return 'not_started';
		}
		$status = $this->db->scalar( $this->db->prepare( 'SELECT status FROM `' . $this->db->table( 'ccap_activity_progress' ) . '` WHERE user_id = %d AND activity_id = %d', $user, $lesson ) );
		return null === $status ? 'not_started' : ( 'completed' === $status ? 'completed' : 'in_progress' );
	}
}
