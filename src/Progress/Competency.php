<?php
/**
 * Derived competency per learner and objective (R0-08 §5.3, E-73).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Progress;

use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * No own table in R1 (E-73): evidence is derived from evaluated attempts
 * with `evidence_eligible = true` and `passed = true`; the objectives a
 * attempt certifies are the `certifies_objectives` frozen in its snapshot.
 * Completion, mastery or purchases never create evidence. States:
 * `evidenced`, `expired` (validity over), `revoked` (attempt voided).
 * Objectives without evidence are reported as `not_evidenced`, never as an
 * estimated ratio.
 *
 * Second source (R1-44): the newest revision of each certificate with its
 * frozen `objectives` and `valid_until`; a revoked certificate is
 * `revoked` and also revokes the evidence of the attempt it was issued
 * from (R0-23 §5.4). Corrected revisions never count on their own.
 */
final class Competency {

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current() );
	}

	/**
	 * States per objective.
	 *
	 * @param int      $user_id    Learner.
	 * @param int      $course_id  Course.
	 * @param array    $objectives Known objectives of the course (reported as `not_evidenced` without evidence).
	 * @param int|null $now        Unix time (tests).
	 * @phpstan-param list<string> $objectives
	 * @return array<string, array{state: string, source?: string, rule_version?: string, evaluated_at?: string, points_milli?: int, max_points_milli?: int, valid_until?: string|null}>
	 */
	public function states( int $user_id, int $course_id, array $objectives = array(), ?int $now = null ): array {
		$now          = $now ?? time();
		$out          = array();
		$certificates = $this->certificates( $user_id, $course_id );
		$revoked      = array();
		foreach ( $certificates as $certificate ) {
			if ( 'revoked' === $certificate['status'] && 'exam_session' === $certificate['evidence_type'] ) {
				$revoked[ (int) $certificate['evidence_id'] ] = true;
			}
		}
		$rows = $this->db->rows(
			$this->db->prepare(
				'SELECT id, uid, status, end_reason, passed, evaluated_at, points_milli, max_points_milli, snapshot FROM `' . $this->db->table( 'ccap_exam_session' ) . "` WHERE user_id = %d AND course_id = %d AND (status = 'evaluated' OR (status = 'cancelled' AND end_reason = 'support_void')) ORDER BY evaluated_at DESC, id DESC",
				$user_id,
				$course_id
			)
		);
		foreach ( $rows as $row ) {
			$snapshot = json_decode( (string) $row['snapshot'], true );
			if ( ! is_array( $snapshot ) || empty( $snapshot['evidence_eligible'] ) ) {
				continue;
			}
			$certifies = (array) ( $snapshot['competency']['certifies_objectives'] ?? array() );
			$voided    = 'cancelled' === $row['status'] || isset( $revoked[ (int) $row['id'] ] );
			if ( ! $voided && '1' !== (string) $row['passed'] ) {
				continue;
			}
			$days  = $snapshot['competency']['validity_days'] ?? null;
			$until = null === $days || null === $row['evaluated_at'] ? null : gmdate( 'Y-m-d H:i:s', (int) strtotime( $row['evaluated_at'] . ' UTC' ) + 86400 * (int) $days );
			foreach ( $certifies as $objective ) {
				$objective = (string) $objective;
				$state     = $voided ? 'revoked' : ( null !== $until && (int) strtotime( $until . ' UTC' ) <= $now ? 'expired' : 'evidenced' );
				$current   = $out[ $objective ]['state'] ?? null;
				// The newest valid evidence wins; revoked/expired never hide a valid one.
				if ( 'evidenced' === $current || ( null !== $current && 'evidenced' !== $state ) ) {
					continue;
				}
				$out[ $objective ] = array(
					'state'            => $state,
					'source'           => (string) $row['uid'],
					'rule_version'     => (string) ( $snapshot['rule']['version'] ?? '' ),
					'evaluated_at'     => (string) $row['evaluated_at'],
					'points_milli'     => (int) $row['points_milli'],
					'max_points_milli' => (int) $row['max_points_milli'],
					'valid_until'      => $until,
				);
			}
		}
		foreach ( $certificates as $certificate ) {
			$snapshot = json_decode( (string) $certificate['snapshot'], true );
			if ( ! is_array( $snapshot ) ) {
				continue;
			}
			$until = isset( $snapshot['valid_until'] ) ? (string) $snapshot['valid_until'] . ' 23:59:59' : null;
			foreach ( (array) ( $snapshot['objectives'] ?? array() ) as $objective ) {
				$objective = (string) ( ( (array) $objective )['uid'] ?? '' );
				if ( '' === $objective ) {
					continue;
				}
				$state   = 'revoked' === $certificate['status'] ? 'revoked' : ( null !== $until && (int) strtotime( $until . ' UTC' ) <= $now ? 'expired' : 'evidenced' );
				$current = $out[ $objective ]['state'] ?? null;
				if ( 'evidenced' === $current || ( null !== $current && 'evidenced' !== $state ) ) {
					continue;
				}
				$evidence          = (array) ( $snapshot['evidence'] ?? array() );
				$out[ $objective ] = array(
					'state'            => $state,
					'source'           => (string) $certificate['certificate_uid'],
					'rule_version'     => (string) ( $evidence['rule_version'] ?? '' ),
					'evaluated_at'     => gmdate( 'Y-m-d H:i:s', (int) strtotime( (string) ( $evidence['evaluated_at'] ?? '' ) ) ),
					'points_milli'     => (int) ( $evidence['points'] ?? 0 ),
					'max_points_milli' => (int) ( $evidence['max_points'] ?? 0 ),
					'valid_until'      => $until,
				);
			}
		}
		foreach ( $objectives as $objective ) {
			if ( ! isset( $out[ $objective ] ) ) {
				$out[ $objective ] = array( 'state' => 'not_evidenced' );
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Newest revision of every certificate of the learner in the course.
	 *
	 * @param int $user_id   Learner.
	 * @param int $course_id Course.
	 * @return list<array<string, mixed>>
	 */
	private function certificates( int $user_id, int $course_id ): array {
		$table = $this->db->table( 'ccap_certificate' );
		return $this->db->rows(
			$this->db->prepare(
				"SELECT c.certificate_uid, c.status, c.evidence_type, c.evidence_id, c.snapshot FROM `{$table}` c WHERE c.user_id = %d AND c.course_id = %d AND c.revision_no = (SELECT MAX(x.revision_no) FROM `{$table}` x WHERE x.certificate_uid = c.certificate_uid) ORDER BY c.issued_at DESC, c.id DESC",
				$user_id,
				$course_id
			)
		);
	}
}
