<?php
/**
 * Area "Grading" of "Quizzes & exams": assignment submissions (R1-46, R0-08 §2.6, UX-017).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Assignment\AssignmentService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Queue of open submissions the grader may grade (M-10a, oldest first),
 * detail with text, files, rubric and earlier evaluations, and one form
 * with two clearly named actions: "Save grade" (status `graded`) or
 * "Return for revision" (status `returned`, comment required). The result
 * notice names the new status and what the learner sees next, so nobody
 * believes a submission is graded while it is still `submitted` (S-02).
 * Grading and returning are audited by {@see AssignmentService}; every
 * form carries an idempotency key (double submit = one evaluation).
 */
final class GradingArea {

	public const ACTION = 'ccap_grading_admin';
	public const CAP    = 'ccap_grade_attempts';

	/**
	 * Admin-post entry point.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out  = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process().
		$args = array(
			'page'        => ExamsScreen::SLUG,
			'area'        => 'grading',
			'ccap_result' => $out['result'],
		);
		if ( 'ok' !== $out['result'] ) {
			$args['submission'] = $out['submission'];
		} else {
			$args['ccap_status'] = $out['status'];
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Testable core of the handler.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Grader.
	 * @return array{op: string, submission: string, result: string, status: string}
	 */
	public static function process( array $post, int $user_id ): array {
		$op  = sanitize_key( (string) ( $post['op'] ?? '' ) );
		$uid = (string) ( $post['submission'] ?? '' );
		$uid = 1 === preg_match( '/^[0-9a-f-]{36}$/', $uid ) ? $uid : '';
		$out = array(
			'op'         => $op,
			'submission' => $uid,
			'result'     => 'ok',
			'status'     => '',
		);
		if ( ! in_array( $op, array( 'grade', 'return' ), true ) || '' === $uid ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( 'grading' !== ( $post['nonce_op'] ?? '' ) || ! AdminUi::verify( self::ACTION, $post ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		$points = trim( (string) ( $post['points'] ?? '' ) );
		if ( 'grade' === $op && ! ctype_digit( $points ) ) {
			$out['result'] = 'ccap_points_invalid';
			self::keep( $user_id, $post );
			return $out;
		}
		try {
			$result        = AssignmentService::current()->grade(
				Actor::user( $user_id ),
				$uid,
				$op,
				'grade' === $op ? (int) $points * 1000 : null,
				(string) ( $post['feedback'] ?? '' ),
				(string) ( $post['reason'] ?? '' ),
				(string) ( $post['request_key'] ?? '' )
			);
			$out['status'] = (string) $result['status'];
		} catch ( CourseException $e ) {
			$out['result'] = 'ccap_grade_invalid' === $e->error_code ? 'ccap_grade_invalid_' . sanitize_key( (string) ( $e->data['reason'] ?? '' ) ) : $e->error_code;
			self::keep( $user_id, $post );
		}
		return $out;
	}

	/**
	 * Keep the entries after an error (INV-D-025).
	 *
	 * @param int                  $user_id User.
	 * @param array<string, mixed> $post    POST.
	 * @return void
	 */
	private static function keep( int $user_id, array $post ): void {
		set_transient( 'ccap_grading_form_' . $user_id, array_intersect_key( $post, array_flip( array( 'submission', 'points', 'feedback', 'reason' ) ) ), 600 );
	}

	/**
	 * Area HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Grader.
	 * @return string
	 */
	public static function html( array $request, int $user_id ): string {
		$code   = sanitize_key( (string) ( $request['ccap_result'] ?? '' ) );
		$status = sanitize_key( (string) ( $request['ccap_status'] ?? '' ) );
		$ok     = 'returned' === $status
			? __( 'Returned for revision. Status now: returned. The learner sees your comment and can hand in a revised version.', 'course-captain' )
			: __( 'Graded. Status now: graded. The learner sees the points and your feedback.', 'course-captain' );
		$html   = AdminUi::notice(
			$code,
			array(
				'ccap_points_invalid'                 => __( 'Not graded: enter the points as a whole number.', 'course-captain' ),
				'ccap_grade_invalid_points'           => __( 'Not graded: the points must be between 0 and the maximum.', 'course-captain' ),
				'ccap_grade_invalid_reason_required'  => __( 'Not graded: a new evaluation needs a reason.', 'course-captain' ),
				'ccap_grade_invalid_comment_required' => __( 'Not returned: write a comment that tells the learner what to revise.', 'course-captain' ),
				'ccap_version_conflict'               => __( 'Nothing changed: this submission was already graded or returned in the meantime.', 'course-captain' ),
			),
			$ok
		);
		$uid    = (string) ( $request['submission'] ?? '' );
		if ( 1 === preg_match( '/^[0-9a-f-]{36}$/', $uid ) ) {
			return $html . self::detail( $uid, $user_id );
		}
		return $html . self::queue( $request, $user_id );
	}

	/**
	 * Open submissions.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Grader.
	 * @return string
	 */
	private static function queue( array $request, int $user_id ): string {
		$course = absint( $request['course'] ?? 0 );
		$page   = max( 1, absint( $request['paged'] ?? 1 ) );
		try {
			$queue = AssignmentService::current()->queue( Actor::user( $user_id ), $course > 0 ? $course : null, $page, 20 );
		} catch ( CourseException $e ) {
			return '<p>' . esc_html__( 'You are not allowed to grade submissions.', 'course-captain' ) . '</p>';
		}
		$html = '<h2>' . esc_html__( 'Submissions waiting for grading', 'course-captain' ) . '</h2>';
		/* translators: %d: open submissions */
		$waiting = sprintf( _n( '%d submission is waiting.', '%d submissions are waiting.', $queue['total'], 'course-captain' ), $queue['total'] );
		$html   .= '<p role="status">' . esc_html( $waiting ) . '</p>';
		$html   .= '<table class="widefat striped"><caption class="screen-reader-text">' . esc_html__( 'Submissions waiting for grading', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Learner', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Assignment', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Course', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Handed in (UTC)', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Version', 'course-captain' ) . '</th></tr></thead><tbody>';
		if ( array() === $queue['items'] ) {
			$html .= '<tr><td colspan="5">' . esc_html__( 'Nothing to grade right now.', 'course-captain' ) . '</td></tr>';
		}
		foreach ( $queue['items'] as $item ) {
			$url   = add_query_arg(
				array(
					'page'       => ExamsScreen::SLUG,
					'area'       => 'grading',
					'submission' => $item['submission_uid'],
				),
				admin_url( 'admin.php' )
			);
			$name  = '' !== $item['learner']['display_name'] ? $item['learner']['display_name'] : '#' . $item['learner']['id'];
			$html .= '<tr><th scope="row"><a href="' . esc_url( $url ) . '">' . esc_html( sprintf( /* translators: %s: learner */ __( 'Grade submission of %s', 'course-captain' ), $name ) ) . '</a></th><td>' . esc_html( $item['lesson_title'] ) . '</td><td>' . esc_html( (string) get_the_title( $item['course_id'] ) ) . '</td><td>' . esc_html( (string) $item['submitted_at'] ) . '</td><td>' . (int) $item['submission_no'] . '</td></tr>';
		}
		$html .= '</tbody></table>';
		if ( $queue['pages'] > 1 ) {
			$html .= '<nav aria-label="' . esc_attr__( 'Submission pages', 'course-captain' ) . '"><p>';
			for ( $p = 1; $p <= $queue['pages']; $p++ ) {
				$html .= $p === $page ? '<span aria-current="page">' . $p . '</span> ' : '<a href="' . esc_url( add_query_arg( array( 'page' => ExamsScreen::SLUG, 'area' => 'grading', 'paged' => $p, 'course' => $course ), admin_url( 'admin.php' ) ) ) . '">' . $p . '</a> '; // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short URL args.
			}
			$html .= '</p></nav>';
		}
		return $html;
	}

	/**
	 * Submission detail with the grading form.
	 *
	 * @param string $uid     Submission.
	 * @param int    $user_id Grader.
	 * @return string
	 */
	private static function detail( string $uid, int $user_id ): string {
		$back = '<p><a href="' . esc_url(
			add_query_arg(
				array(
					'page' => ExamsScreen::SLUG,
					'area' => 'grading',
				),
				admin_url( 'admin.php' )
			)
		) . '">' . esc_html__( 'Back to the submissions', 'course-captain' ) . '</a></p>';
		try {
			$sub = AssignmentService::current()->detail( Actor::user( $user_id ), $uid );
		} catch ( CourseException $e ) {
			return $back . '<p>' . esc_html__( 'This submission does not exist or you may not grade it.', 'course-captain' ) . '</p>';
		}
		$grading = (array) ( $sub['grading'] ?? array() );
		if ( array() === $grading ) {
			return $back . '<p>' . esc_html__( 'This submission does not exist or you may not grade it.', 'course-captain' ) . '</p>';
		}
		$labels = array(
			'submitted' => __( 'waiting for grading', 'course-captain' ),
			'graded'    => __( 'graded', 'course-captain' ),
			'returned'  => __( 'returned for revision', 'course-captain' ),
			'draft'     => __( 'draft (not handed in)', 'course-captain' ),
		);
		$name   = (string) ( $grading['learner']['display_name'] ?? '' );
		$html   = $back . '<h2>' . esc_html( sprintf( /* translators: 1: assignment, 2: learner */ __( '%1$s – submission of %2$s', 'course-captain' ), (string) get_the_title( (int) $grading['lesson_id'] ), $name ) ) . '</h2>';
		$html  .= '<p>' . esc_html( sprintf( /* translators: 1: status, 2: version, 3: date */ __( 'Status: %1$s · Version %2$d · handed in %3$s (UTC)', 'course-captain' ), $labels[ $sub['status'] ] ?? $sub['status'], (int) $sub['submission_no'], (string) $sub['submitted_at'] ) ) . '</p>';
		if ( '' !== (string) $grading['rubric_text'] ) {
			$html .= '<h3>' . esc_html__( 'Grading guide', 'course-captain' ) . '</h3><div class="ccap-rubric">' . wpautop( esc_html( (string) $grading['rubric_text'] ) ) . '</div>';
		}
		$html .= '<h3>' . esc_html__( 'Answer', 'course-captain' ) . '</h3><div class="ccap-submission-text">' . ( '' === $sub['text'] ? '<p>' . esc_html__( '(no text)', 'course-captain' ) . '</p>' : wpautop( esc_html( (string) $sub['text'] ) ) ) . '</div>';
		if ( array() !== $sub['files'] ) {
			$html .= '<h3>' . esc_html__( 'Files', 'course-captain' ) . '</h3><ul>';
			foreach ( $sub['files'] as $file ) {
				$html .= '<li><a href="' . esc_url( (string) $file['url'] ) . '">' . esc_html( sprintf( /* translators: 1: file name, 2: size in KB */ __( '%1$s (%2$s KB)', 'course-captain' ), (string) $file['name'], number_format_i18n( max( 1, (int) ceil( (int) $file['size_bytes'] / 1024 ) ) ) ) ) . '</a></li>';
			}
			$html .= '</ul>';
		}
		foreach ( (array) $sub['evaluations'] as $eval ) {
			$html .= '<p>' . esc_html( sprintf( /* translators: 1: evaluation number, 2: points, 3: maximum */ __( 'Evaluation %1$d: %2$s of %3$s points', 'course-captain' ), (int) $eval['evaluation_no'], number_format_i18n( (int) $eval['points_milli'] / 1000 ), number_format_i18n( (int) $eval['max_points_milli'] / 1000 ) ) ) . '</p>';
		}
		if ( ! in_array( $sub['status'], array( 'submitted', 'graded' ), true ) ) {
			return $html;
		}
		$kept = get_transient( 'ccap_grading_form_' . $user_id );
		$kept = is_array( $kept ) && ( $kept['submission'] ?? '' ) === $uid ? $kept : array();
		if ( array() !== $kept ) {
			delete_transient( 'ccap_grading_form_' . $user_id );
		}
		$max     = (int) $grading['max_points'];
		$regrade = 'graded' === $sub['status'];
		$fields  = '<input type="hidden" name="submission" value="' . esc_attr( $uid ) . '"><input type="hidden" name="request_key" value="' . esc_attr( AdminUi::request_key() ) . '">';
		$fields .= '<h3>' . esc_html( $regrade ? __( 'New evaluation', 'course-captain' ) : __( 'Your evaluation', 'course-captain' ) ) . '</h3>';
		$fields .= '<p><label for="ccap-g-points">' . esc_html( sprintf( /* translators: %d: maximum points */ __( 'Points (0 to %d)', 'course-captain' ), $max ) ) . '</label><br><input id="ccap-g-points" type="number" min="0" max="' . $max . '" step="1" name="points" value="' . esc_attr( (string) ( $kept['points'] ?? '' ) ) . '"' . ( null !== $grading['passing_points'] ? ' aria-describedby="ccap-g-pass"' : '' ) . '>';
		if ( null !== $grading['passing_points'] ) {
			$fields .= ' <span id="ccap-g-pass" class="description">' . esc_html( sprintf( /* translators: %d: points needed to pass */ __( 'Passed from %d points.', 'course-captain' ), (int) $grading['passing_points'] ) ) . '</span>';
		}
		$fields .= '</p><p><label for="ccap-g-feedback">' . esc_html__( 'Feedback or comment for the learner', 'course-captain' ) . '</label><textarea id="ccap-g-feedback" name="feedback" rows="5" class="large-text" aria-describedby="ccap-g-feedback-help">' . esc_textarea( (string) ( $kept['feedback'] ?? '' ) ) . '</textarea><span id="ccap-g-feedback-help" class="description">' . esc_html__( 'Required when you return the submission: say what to revise.', 'course-captain' ) . '</span></p>';
		if ( $regrade ) {
			$fields .= '<p><label for="ccap-g-reason">' . esc_html__( 'Reason for the new evaluation (required, recorded)', 'course-captain' ) . '</label><input id="ccap-g-reason" type="text" class="large-text" name="reason" value="' . esc_attr( (string) ( $kept['reason'] ?? '' ) ) . '"></p>';
		}
		$fields .= '<p><button type="submit" name="op" value="grade" class="button button-primary">' . esc_html__( 'Save grade', 'course-captain' ) . '</button>';
		if ( ! $regrade ) {
			$fields .= ' <button type="submit" name="op" value="return" class="button">' . esc_html__( 'Return for revision', 'course-captain' ) . '</button>';
		}
		$fields .= '</p>';
		return $html . AdminUi::form_multi( self::ACTION, 'grading', 0, $fields );
	}
}
