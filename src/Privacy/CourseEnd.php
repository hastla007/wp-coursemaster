<?php
/**
 * "Course end" as the start of a retention period (E-244, R0-31 D31-6, PSD-013).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Privacy;

use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Course end of one person in one course = the later of
 *
 * - the end of the last grant (an `active`/`suspended` grant without end or
 *   with an end in the future has not ended: no period runs; an ended grant
 *   ends at `valid_until` when it expired, otherwise when it was revoked or
 *   failed, i.e. the earlier of `valid_until` and its last change), and
 * - the last activity in the course (course progress `last_activity_at`,
 *   learning answers, exam attempts, assignment versions).
 *
 * PSD-013: (a) active grant without end: no period; (b) grant expired,
 * last activity before: period from the grant end; (c) course completed
 * while the grant is still active: no period; (d) new grant after expiry:
 * the period rests while the new grant is active.
 *
 * Used by RT-04 (learning answers), RT-05 (raw exam answers) and RT-45
 * (assignment submissions); all read `raw_answers_years_after_course_end`.
 */
final class CourseEnd {

	/** Grant states that have not ended. */
	public const RUNNING = array( 'active', 'suspended' );

	/** Grant states that have ended. */
	public const ENDED = array( 'expired', 'revoked', 'failed' );

	/**
	 * SQL condition (already prepared, no placeholders): the course of the
	 * pair given by the two column expressions ended before `$cutoff`.
	 *
	 * @param Database $db     Database.
	 * @param string   $user   Column expression of the user ID (e.g. `a.user_id`).
	 * @param string   $course Column expression of the course ID (e.g. `a.course_id`).
	 * @param string   $cutoff UTC cutoff (`Y-m-d H:i:s`).
	 * @return string
	 */
	public static function ended_before( Database $db, string $user, string $course, string $cutoff ): string {
		$t       = static fn( string $name ): string => '`' . $db->table( $name ) . '`';
		$running = "'" . implode( "', '", self::RUNNING ) . "'";
		$ended   = "'" . implode( "', '", self::ENDED ) . "'";
		$pair    = static fn( string $alias ): string => "{$alias}.user_id = {$user} AND {$alias}.course_id = {$course}";
		$sql     = 'NOT EXISTS (SELECT 1 FROM ' . $t( 'ccap_grant' ) . ' ce_g WHERE ' . $pair( 'ce_g' )
			. " AND ( (ce_g.status IN ({$running}) AND (ce_g.valid_until IS NULL OR ce_g.valid_until >= %s))"
			. " OR (ce_g.status IN ({$ended}) AND LEAST(COALESCE(ce_g.valid_until, ce_g.updated_at), ce_g.updated_at) >= %s) ) )"
			. ' AND NOT EXISTS (SELECT 1 FROM ' . $t( 'ccap_course_progress' ) . ' ce_p WHERE ' . $pair( 'ce_p' ) . ' AND ce_p.last_activity_at >= %s)'
			. ' AND NOT EXISTS (SELECT 1 FROM ' . $t( 'ccap_attempt' ) . ' ce_a WHERE ' . $pair( 'ce_a' ) . ' AND ce_a.created_at >= %s)'
			. ' AND NOT EXISTS (SELECT 1 FROM ' . $t( 'ccap_exam_session' ) . ' ce_s WHERE ' . $pair( 'ce_s' ) . " AND (ce_s.status = 'in_progress' OR COALESCE(ce_s.evaluated_at, ce_s.submitted_at, ce_s.started_at) >= %s))"
			. ' AND NOT EXISTS (SELECT 1 FROM ' . $t( 'ccap_assignment_submission' ) . ' ce_x WHERE ' . $pair( 'ce_x' ) . ' AND ce_x.updated_at >= %s)';
		return $db->prepare( $sql, ...array_fill( 0, 6, $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names and constant state lists.
	}
}
