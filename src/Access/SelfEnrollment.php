<?php
/**
 * Free self-enrollment from the learning area (R0-11 L-02/Flow 2 F2.1,
 * R0-27 SET-070, R0-28 #19/INH-023/024, E-267, E-342 B-02).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Self-enrollment always creates a grant (INV-S-11): source
 * `free_enrollment`, `source_id` = learner ID, a new `source_revision`
 * after an ended grant of the same source (no revival, E-29). The
 * setting `enrollment.self_enrollment` is read at the time of the
 * request (class L, course override wins). Only published courses,
 * only logged-in learners with a role on the site and no learner lock.
 * A learner who already has full access gets no second grant; a
 * repeated request returns the existing grant (idempotent). Duration
 * from `enrollment.default_access_days` (empty = permanent).
 */
final class SelfEnrollment {

	public const SOURCE = 'free_enrollment';

	/**
	 * Constructor.
	 *
	 * @param Database     $db        Database access.
	 * @param Relations    $relations Relations.
	 * @param GrantService $grants    Grants.
	 */
	public function __construct( private Database $db, private Relations $relations, private GrantService $grants ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, new Relations( $db ), GrantService::current() );
	}

	/**
	 * Whether self-enrollment is switched on for a published course.
	 *
	 * @param int $course_id Course.
	 * @return bool
	 */
	public function open( int $course_id ): bool {
		$post = $this->relations->post( $course_id, Relations::COURSE_TYPE );
		return null !== $post && Relations::STATUS_PUBLISHED === Relations::course_status( $post ) && true === EffectiveSettings::for_course( 'enrollment.self_enrollment', $course_id );
	}

	/**
	 * Whether the learner already has learning access to the whole course.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return bool
	 */
	public function enrolled( Actor $actor, int $course_id ): bool {
		return $actor->user_id > 0 && 'ok' === $this->relations->effective_access( $actor->user_id, $course_id, 0, 'view' );
	}

	/**
	 * Enroll the learner.
	 *
	 * @param Actor $actor     Learner (self).
	 * @param int   $course_id Course.
	 * @return array{grant_id: int, created: bool}
	 * @throws CourseException `ccap_auth_required`, `ccap_account_locked`, `ccap_not_found`, `ccap_forbidden` (`reason: self_enrollment_off`).
	 */
	public function enroll( Actor $actor, int $course_id ): array {
		if ( $actor->user_id <= 0 ) {
			throw new CourseException( 'ccap_auth_required', 'Login required.', 401 );
		}
		if ( ! user_can( $actor->user_id, 'read' ) || $this->relations->is_locked( $actor->user_id ) ) {
			throw new CourseException( 'ccap_account_locked', 'Learning access is locked.', 403 );
		}
		$post = $this->relations->post( $course_id, Relations::COURSE_TYPE );
		if ( null === $post || Relations::STATUS_PUBLISHED !== Relations::course_status( $post ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		$table  = $this->db->table( 'ccap_grant' );
		$source = (string) $actor->user_id;
		// A running grant of this source is returned (also a suspended one: no way around a suspension).
		$existing = $this->db->row( $this->db->prepare( "SELECT id FROM `{$table}` WHERE source_type = %s AND source_id = %s AND course_id = %d AND module_id = 0 AND (status IN ('pending_claim', 'pending_confirmation', 'suspended') OR (status = 'active' AND (valid_until IS NULL OR valid_until > %s))) ORDER BY source_revision DESC LIMIT 1", self::SOURCE, $source, $course_id, $this->db->now() ) );
		if ( null !== $existing ) {
			return array(
				'grant_id' => (int) $existing['id'],
				'created'  => false,
			);
		}
		if ( $this->enrolled( $actor, $course_id ) ) {
			return array(
				'grant_id' => 0,
				'created'  => false,
			);
		}
		if ( ! $this->open( $course_id ) ) {
			throw new CourseException( 'ccap_forbidden', 'Self-enrollment is not available for this course.', 403, array( 'reason' => 'self_enrollment_off' ) );
		}
		$revision = 1 + (int) $this->db->scalar( $this->db->prepare( "SELECT MAX(source_revision) FROM `{$table}` WHERE source_type = %s AND source_id = %s AND course_id = %d AND module_id = 0", self::SOURCE, $source, $course_id ) );
		$days     = EffectiveSettings::for_course( 'enrollment.default_access_days', $course_id );
		$result   = $this->grants->create(
			array(
				'user_id'         => $actor->user_id,
				'course_id'       => $course_id,
				'module_id'       => 0,
				'scopes'          => 'all',
				'source_type'     => self::SOURCE,
				'source_id'       => $source,
				'source_revision' => $revision,
				'status'          => GrantTransitions::ACTIVE,
				'duration_days'   => is_int( $days ) && $days > 0 ? $days : null,
				'renewal_mode'    => 'parallel',
			),
			$actor,
			'self_enrolled'
		);
		if ( $result['created'] ) {
			AuditLog::write( $this->db, $actor, 'grant.self_enrolled', 'grant', (string) $result['grant_id'], null, array( 'course_id' => $course_id ), $actor->user_id );
		}
		PolicyService::flush();
		return $result;
	}
}
