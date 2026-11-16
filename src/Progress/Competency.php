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
 * estimated ratio. Certificates as a second source follow with R1-44.
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
		$now  = $now ?? time();
		$out  = array();
		$rows = $this->db->rows(
			$this->db->prepare(
				'SELECT uid, status, end_reason, passed, evaluated_at, points_milli, max_points_milli, snapshot FROM `' . $this->db->table( 'ccap_exam_session' ) . "` WHERE user_id = %d AND course_id = %d AND (status = 'evaluated' OR (status = 'cancelled' AND end_reason = 'support_void')) ORDER BY evaluated_at DESC, id DESC",
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
			$voided    = 'cancelled' === $row['status'];
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
		foreach ( $objectives as $objective ) {
			if ( ! isset( $out[ $objective ] ) ) {
				$out[ $objective ] = array( 'state' => 'not_evidenced' );
			}
		}
		ksort( $out );
		return $out;
	}
}
