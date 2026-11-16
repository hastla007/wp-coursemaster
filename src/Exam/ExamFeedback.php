<?php
/**
 * Learner result visibility (R0-27 `exams.feedback`, E-20, E-147, E-229).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Exam;

defined( 'ABSPATH' ) || exit;

/**
 * Pure filter over a stored evaluation, driven by the feedback settings
 * frozen in the snapshot (a later settings change never alters an old
 * attempt):
 *
 * - `visibility = never`: nothing but `released = false`.
 * - otherwise (`always` acts as `after_submit`, VAL-096): passed/failed and,
 *   per question, right or wrong (E-147: always after each submission).
 * - points only with `show_score`.
 * - solution keys only with `show_correct_answers` and, for
 *   `no_attempts_left_or_passed`, only once passed or no attempt is left.
 * - never explanation texts (Free, R0-39 §4).
 */
final class ExamFeedback {

	/**
	 * Filter a stored result.
	 *
	 * @param array<string, mixed>     $result        Stored result.
	 * @param array<string, mixed>     $feedback      Snapshot `feedback`.
	 * @param bool                     $attempts_left Another attempt is possible.
	 * @param array<int, list<string>> $solutions     Correct keys per position (choice types).
	 * @param int                      $evaluation_no Evaluation number.
	 * @return array<string, mixed>
	 */
	public static function filter( array $result, array $feedback, bool $attempts_left, array $solutions, int $evaluation_no ): array {
		if ( 'never' === ( $feedback['feedback_visibility'] ?? 'after_submit' ) ) {
			return array( 'released' => false );
		}
		$passed = ! empty( $result['passed'] );
		$out    = array(
			'released'      => true,
			'passed'        => $passed,
			'evaluation_no' => $evaluation_no,
			'correct'       => (int) ( $result['correct'] ?? 0 ),
			'total'         => (int) ( $result['total'] ?? 0 ),
		);
		$score  = ! empty( $feedback['show_score'] );
		if ( $score ) {
			$out['points_milli']     = (int) ( $result['points_milli'] ?? 0 );
			$out['max_points_milli'] = (int) ( $result['max_points_milli'] ?? 0 );
		}
		$reveal = ! empty( $feedback['show_correct_answers'] )
			&& ( 'after_submit' === ( $feedback['show_correct_answers_when'] ?? '' ) || $passed || ! $attempts_left );
		$items  = array();
		foreach ( (array) ( $result['questions'] ?? array() ) as $q ) {
			$q    = (array) $q;
			$pos  = (int) $q['position'];
			$item = array(
				'position' => $pos,
				'correct'  => ! empty( $q['correct'] ),
			);
			if ( $score ) {
				$item['points_milli']     = (int) $q['points_milli'];
				$item['max_points_milli'] = (int) $q['max_points_milli'];
			}
			if ( $reveal && isset( $solutions[ $pos ] ) ) {
				$item['correct_ids'] = $solutions[ $pos ];
			}
			$items[] = $item;
		}
		$out['questions']         = $items;
		$out['solutions_visible'] = $reveal;
		return $out;
	}
}
