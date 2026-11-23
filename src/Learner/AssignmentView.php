<?php
/**
 * Submission form of a text/file assignment in the lesson view
 * (R0-11 L-22, R1-46, R0-26 LE-T11/T12, E-342).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Assignment\AssignmentRoutes;
use CourseCaptain\Assignment\AssignmentService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Uses the R1-46 services only ({@see AssignmentService}: view, draft,
 * file, submit). Works without JavaScript: one multipart form with
 * "Save draft" and "Submit" (submitting needs a confirmation checkbox;
 * the text and a chosen file are saved first, so nothing typed is lost).
 * A returned version shows the comment; editing it starts the next
 * version. Grades and feedback are shown for every version.
 * Accessibility: labelled text field and file field with their limits as
 * description, status text instead of colour only (R0-15).
 */
final class AssignmentView {

	/** Name of the file field. */
	public const FILE_FIELD = 'assignment_file';

	/**
	 * Section of the lesson page.
	 *
	 * @param Actor  $actor     Learner.
	 * @param int    $course_id Course.
	 * @param int    $lesson_id Lesson.
	 * @param string $lang      Content language attributes.
	 * @return string
	 */
	public static function section( Actor $actor, int $course_id, int $lesson_id, string $lang ): string {
		$html = '<section class="ccap-activity ccap-assignment" aria-labelledby="ccap-activity-heading"><h2 id="ccap-activity-heading">' . esc_html__( 'Assignment', 'course-captain' ) . '</h2>';
		try {
			$view = AssignmentService::current()->view( $actor, $course_id, $lesson_id );
		} catch ( CourseException $e ) {
			return $html . '<p>' . esc_html( \CourseCaptain\Rest\RestContract::user_message( $e->error_code ) ) . '</p></section>';
		}
		$task     = (array) $view['assignment'];
		$versions = (array) $view['submissions'];
		$latest   = array() === $versions ? null : (array) end( $versions );
		if ( '' !== (string) $task['instructions'] ) {
			$html .= '<div class="ccap-instructions"' . $lang . '>' . wpautop( esc_html( (string) $task['instructions'] ) ) . '</div>';
		}
		$html .= self::requirements( $task );
		$html .= '<h3>' . esc_html__( 'Status', 'course-captain' ) . '</h3><p class="ccap-assignment-state">' . esc_html( self::state( $latest ) ) . '</p>';
		if ( null !== $latest ) {
			$html .= self::details( $latest, $lang );
		}
		if ( ! $task['available'] ) {
			$html .= '<p>' . esc_html__( 'This assignment is no longer part of the course. You can still read your submissions.', 'course-captain' ) . '</p>';
		} elseif ( ! empty( $view['editable'] ) ) {
			$html .= self::form( $course_id, $lesson_id, $task, $latest );
		}
		if ( count( $versions ) > 1 ) {
			$html .= '<h3>' . esc_html__( 'Earlier versions', 'course-captain' ) . '</h3><ol class="ccap-versions">';
			foreach ( array_slice( $versions, 0, -1 ) as $version ) {
				$version = (array) $version;
				$html   .= '<li><p>' . esc_html(
					sprintf(
						/* translators: 1: version number, 2: status text */
						__( 'Version %1$s: %2$s', 'course-captain' ),
						number_format_i18n( (int) $version['submission_no'] ),
						self::state( $version )
					)
				) . '</p>' . self::details( $version, $lang ) . '</li>';
			}
			$html .= '</ol>';
		}
		return $html . '</section>';
	}

	/**
	 * Handle a form post of the assignment; returns the status for the redirect.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param int                  $lesson_id Lesson.
	 * @param string               $action    `assignment_save` or `assignment_submit`.
	 * @param array<string, mixed> $post      Unslashed POST data (nonce checked by the caller).
	 * @param array<string, mixed> $files     Uploaded files (`$_FILES`).
	 * @return string
	 */
	public static function handle( Actor $actor, int $course_id, int $lesson_id, string $action, array $post, array $files ): string {
		$service = AssignmentService::current();
		try {
			$remove = (string) ( $post['ccap_remove'] ?? '' );
			$text   = array_key_exists( 'assignment_text', $post ) && is_string( $post['assignment_text'] ) ? $post['assignment_text'] : null;
			$draft  = $service->save_draft( $actor, $course_id, $lesson_id, $text );
			$uid    = $draft['submission_uid'];
			if ( '' !== $remove ) {
				$service->remove_file( $actor, $uid, $remove );
				return 'file_removed';
			}
			$file = $files[ self::FILE_FIELD ] ?? null;
			if ( is_array( $file ) && UPLOAD_ERR_NO_FILE !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
				$error = (int) $file['error'];
				if ( UPLOAD_ERR_OK !== $error ) {
					return in_array( $error, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ? 'asg_size' : 'asg_upload';
				}
				$tmp = (string) ( $file['tmp_name'] ?? '' );
				if ( ! AssignmentRoutes::is_upload( $tmp ) ) {
					return 'asg_upload';
				}
				$service->add_file( $actor, $uid, $tmp, (string) ( $file['name'] ?? '' ), (string) ( $file['type'] ?? '' ), isset( $post['request_key'] ) && is_string( $post['request_key'] ) ? $post['request_key'] : null );
			}
			if ( 'assignment_submit' !== $action ) {
				return 'draft_saved';
			}
			if ( empty( $post['confirm_submit'] ) ) {
				return 'submit_confirm_required';
			}
			$service->submit( $actor, $uid );
			return 'submitted';
		} catch ( CourseException $e ) {
			$reason = (string) ( $e->data['reason'] ?? '' );
			$known  = array( 'text_too_short', 'text_too_long', 'empty', 'type', 'mime', 'size', 'count', 'dangerous', 'scan', 'no_files', 'files_not_allowed' );
			return in_array( $reason, $known, true ) ? 'asg_' . $reason : $e->error_code;
		}
	}

	/**
	 * Messages for the statuses of {@see self::handle()} (null = not ours).
	 *
	 * @param string $status Status.
	 * @return array{text: string, ok: bool}|null
	 */
	public static function message( string $status ): ?array {
		$ok = array(
			'draft_saved'  => __( 'Your draft has been saved. It is not submitted yet.', 'course-captain' ),
			'file_removed' => __( 'The file has been removed from your draft.', 'course-captain' ),
			'submitted'    => __( 'Your assignment has been submitted. You cannot change it any more.', 'course-captain' ),
		);
		if ( isset( $ok[ $status ] ) ) {
			return array(
				'text' => $ok[ $status ],
				'ok'   => true,
			);
		}
		$errors = array(
			'submit_confirm_required' => __( 'Your draft has been saved. To submit it, please confirm that you are finished.', 'course-captain' ),
			'asg_text_too_short'      => __( 'Your text is too short. Please check the minimum number of characters.', 'course-captain' ),
			'asg_text_too_long'       => __( 'Your text is too long. Please check the maximum number of characters.', 'course-captain' ),
			'asg_empty'               => __( 'Please add the required text or file before submitting.', 'course-captain' ),
			'asg_type'                => __( 'This file type is not allowed for this assignment.', 'course-captain' ),
			'asg_mime'                => __( 'The content of the file does not match its type.', 'course-captain' ),
			'asg_size'                => __( 'The file is too large.', 'course-captain' ),
			'asg_count'               => __( 'You have already added the maximum number of files.', 'course-captain' ),
			'asg_dangerous'           => __( 'This file type is not allowed for security reasons.', 'course-captain' ),
			'asg_scan'                => __( 'The file was rejected by the security check.', 'course-captain' ),
			'asg_no_files'            => __( 'This assignment does not take files.', 'course-captain' ),
			'asg_files_not_allowed'   => __( 'Your files do not fit the assignment any more. Please remove them and add suitable files.', 'course-captain' ),
			'asg_upload'              => __( 'The file could not be uploaded. Please try again.', 'course-captain' ),
		);
		return isset( $errors[ $status ] ) ? array(
			'text' => $errors[ $status ],
			'ok'   => false,
		) : null;
	}

	/**
	 * Requirements as text.
	 *
	 * @param array<string, mixed> $task Task.
	 * @return string
	 */
	private static function requirements( array $task ): string {
		$items = array();
		$type  = (string) $task['type'];
		if ( 'file' !== $type ) {
			$items[] = (int) $task['min_chars'] > 0
				? sprintf(
					/* translators: 1: minimum, 2: maximum number of characters */
					__( 'Text: %1$s to %2$s characters', 'course-captain' ),
					number_format_i18n( (int) $task['min_chars'] ),
					number_format_i18n( (int) $task['max_chars'] )
				)
				: sprintf(
					/* translators: %s: maximum number of characters */
					__( 'Text: up to %s characters', 'course-captain' ),
					number_format_i18n( (int) $task['max_chars'] )
				);
		}
		if ( 'text' !== $type ) {
			$items[] = self::file_hint( $task );
		}
		if ( (int) $task['max_points'] > 0 ) {
			$items[] = null === $task['passing_points']
				? sprintf(
					/* translators: %s: points */
					__( 'Maximum points: %s', 'course-captain' ),
					number_format_i18n( (int) $task['max_points'] )
				)
				: sprintf(
					/* translators: 1: maximum points, 2: points needed to pass */
					__( 'Maximum points: %1$s, passed from %2$s points', 'course-captain' ),
					number_format_i18n( (int) $task['max_points'] ),
					number_format_i18n( (int) $task['passing_points'] )
				);
		}
		$html = '<h3>' . esc_html__( 'What to hand in', 'course-captain' ) . '</h3><ul>';
		foreach ( $items as $item ) {
			$html .= '<li>' . esc_html( $item ) . '</li>';
		}
		return $html . '</ul>';
	}

	/**
	 * File limits as one sentence.
	 *
	 * @param array<string, mixed> $task Task.
	 * @return string
	 */
	private static function file_hint( array $task ): string {
		return sprintf(
			/* translators: 1: number of files, 2: file types, 3: size, e.g. "10 MB" */
			__( 'Files: up to %1$s, types %2$s, each at most %3$s', 'course-captain' ),
			number_format_i18n( (int) $task['max_files'] ),
			implode( ', ', array_map( 'strtoupper', array_map( 'strval', (array) $task['allowed_types'] ) ) ),
			(string) size_format( (int) $task['max_bytes'] )
		);
	}

	/**
	 * Status of a version as text.
	 *
	 * @param array<string, mixed>|null $version Version.
	 * @return string
	 */
	private static function state( ?array $version ): string {
		if ( null === $version ) {
			return __( 'Not started yet.', 'course-captain' );
		}
		switch ( (string) $version['status'] ) {
			case 'draft':
				return __( 'Draft – not submitted yet.', 'course-captain' );
			case 'submitted':
				return sprintf(
					/* translators: %s: date */
					__( 'Submitted on %s. You cannot change it any more; it is waiting for grading.', 'course-captain' ),
					self::date( $version['submitted_at'] ?? null )
				);
			case 'returned':
				return sprintf(
					/* translators: %s: date */
					__( 'Returned on %s with a comment. Please revise it and submit it again.', 'course-captain' ),
					self::date( $version['returned_at'] ?? null )
				);
			case 'graded':
				return sprintf(
					/* translators: %s: date */
					__( 'Graded on %s.', 'course-captain' ),
					self::date( $version['graded_at'] ?? null )
				);
			default:
				return (string) $version['status'];
		}
	}

	/**
	 * Comment, grade and files of a version.
	 *
	 * @param array<string, mixed> $version Version.
	 * @param string               $lang    Content language attributes.
	 * @return string
	 */
	private static function details( array $version, string $lang ): string {
		$html = '';
		if ( null !== $version['return_comment'] && '' !== (string) $version['return_comment'] ) {
			$html .= '<div class="ccap-return-comment"><p><strong>' . esc_html__( 'Comment from the grader:', 'course-captain' ) . '</strong></p>' . wpautop( esc_html( (string) $version['return_comment'] ) ) . '</div>';
		}
		$grade = is_array( $version['grade'] ?? null ) ? $version['grade'] : null;
		if ( null !== $grade ) {
			if ( null !== $grade['points_milli'] && null !== $grade['max_points_milli'] ) {
				$html .= '<p>' . esc_html(
					sprintf(
						/* translators: 1: points reached, 2: maximum points */
						__( 'Points: %1$s of %2$s', 'course-captain' ),
						number_format_i18n( $grade['points_milli'] / 1000, 1 ),
						number_format_i18n( $grade['max_points_milli'] / 1000, 1 )
					)
				) . '</p>';
			}
			if ( null !== $grade['passed'] ) {
				$html .= '<p>' . esc_html( $grade['passed'] ? __( 'Passed', 'course-captain' ) : __( 'Not passed', 'course-captain' ) ) . '</p>';
			}
			if ( '' !== (string) $grade['feedback'] ) {
				$html .= '<div class="ccap-grade-feedback"><p><strong>' . esc_html__( 'Feedback:', 'course-captain' ) . '</strong></p>' . wpautop( esc_html( (string) $grade['feedback'] ) ) . '</div>';
			}
		}
		if ( 'draft' !== $version['status'] && '' !== (string) $version['text'] ) {
			$html .= '<details><summary>' . esc_html__( 'Your text', 'course-captain' ) . '</summary><div' . $lang . '>' . wpautop( esc_html( (string) $version['text'] ) ) . '</div></details>';
		}
		if ( 'draft' !== $version['status'] && array() !== (array) $version['files'] ) {
			$html .= '<ul class="ccap-files">';
			foreach ( (array) $version['files'] as $file ) {
				$html .= '<li><a href="' . esc_url( (string) $file['url'] ) . '">' . esc_html( (string) $file['name'] ) . '</a> (' . esc_html( (string) size_format( (int) $file['size_bytes'] ) ) . ')</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	/**
	 * Edit form (draft or revision of a returned version).
	 *
	 * @param int                       $course_id Course.
	 * @param int                       $lesson_id Lesson.
	 * @param array<string, mixed>      $task      Task.
	 * @param array<string, mixed>|null $latest    Latest version.
	 * @return string
	 */
	private static function form( int $course_id, int $lesson_id, array $task, ?array $latest ): string {
		$type  = (string) $task['type'];
		$html  = '<h3 id="ccap-assignment-form-heading">' . esc_html( null !== $latest && 'returned' === $latest['status'] ? __( 'Revise your submission', 'course-captain' ) : __( 'Your submission', 'course-captain' ) ) . '</h3>';
		$html .= '<form method="post" enctype="multipart/form-data" aria-labelledby="ccap-assignment-form-heading" action="' . esc_url( LearningArea::lesson_url( LearningArea::course_uid( $course_id ), $lesson_id ) ) . '">' . wp_nonce_field( LessonView::NONCE . ':' . $lesson_id, '_wpnonce', false, false );
		// Remove buttons carry no `ccap_do`; the default action (also for implicit submission) is "save draft".
		$html .= '<input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '"><input type="hidden" name="ccap_do" value="assignment_save">';
		$html .= '<button type="submit" class="screen-reader-text" name="ccap_do" value="assignment_save" tabindex="-1" aria-hidden="true">' . esc_html__( 'Save draft', 'course-captain' ) . '</button>';
		if ( 'file' !== $type ) {
			$text = null === $latest ? '' : (string) $latest['text'];
			$hint = (int) $task['min_chars'] > 0
				? sprintf(
					/* translators: 1: minimum, 2: maximum number of characters */
					__( 'Between %1$s and %2$s characters.', 'course-captain' ),
					number_format_i18n( (int) $task['min_chars'] ),
					number_format_i18n( (int) $task['max_chars'] )
				)
				: sprintf(
					/* translators: %s: maximum number of characters */
					__( 'Up to %s characters.', 'course-captain' ),
					number_format_i18n( (int) $task['max_chars'] )
				);
			$html .= '<p><label for="ccap-assignment-text">' . esc_html__( 'Your answer', 'course-captain' ) . '</label><br><textarea id="ccap-assignment-text" name="assignment_text" rows="10" cols="60" maxlength="' . (int) $task['max_chars'] . '" aria-describedby="ccap-assignment-text-hint">' . esc_textarea( $text ) . '</textarea><br><span id="ccap-assignment-text-hint">' . esc_html( $hint ) . '</span></p>';
		}
		if ( 'text' !== $type ) {
			$files = null === $latest ? array() : (array) $latest['files'];
			if ( array() !== $files ) {
				$html .= '<p id="ccap-assignment-files-heading">' . esc_html__( 'Files in this version:', 'course-captain' ) . '</p><ul class="ccap-files" aria-labelledby="ccap-assignment-files-heading">';
				foreach ( $files as $file ) {
					$html .= '<li><a href="' . esc_url( (string) $file['url'] ) . '">' . esc_html( (string) $file['name'] ) . '</a> (' . esc_html( (string) size_format( (int) $file['size_bytes'] ) ) . ') <button type="submit" class="ccap-button" name="ccap_remove" value="' . esc_attr( (string) $file['media_uid'] ) . '">' . esc_html(
						sprintf(
							/* translators: %s: file name */
							__( 'Remove %s', 'course-captain' ),
							(string) $file['name']
						)
					) . '</button></li>';
				}
				$html .= '</ul>';
			}
			$html .= '<p><label for="ccap-assignment-file">' . esc_html__( 'Add a file', 'course-captain' ) . '</label><br><input type="file" id="ccap-assignment-file" name="' . esc_attr( self::FILE_FIELD ) . '" accept="' . esc_attr( implode( ',', array_map( static fn( $t ): string => '.' . (string) $t, (array) $task['allowed_types'] ) ) ) . '" aria-describedby="ccap-assignment-file-hint"><br><span id="ccap-assignment-file-hint">' . esc_html( self::file_hint( $task ) ) . '</span></p>';
		}
		$html .= '<p><button type="submit" class="ccap-button" name="ccap_do" value="assignment_save">' . esc_html__( 'Save draft', 'course-captain' ) . '</button></p>';
		$html .= '<fieldset class="ccap-submit"><legend>' . esc_html__( 'Submit', 'course-captain' ) . '</legend><p><input type="checkbox" id="ccap-assignment-confirm" name="confirm_submit" value="1"> <label for="ccap-assignment-confirm">' . esc_html__( 'I am finished. I know that I cannot change my submission afterwards.', 'course-captain' ) . '</label></p>';
		return $html . '<p><button type="submit" class="ccap-button" name="ccap_do" value="assignment_submit">' . esc_html__( 'Submit assignment', 'course-captain' ) . '</button></p></fieldset></form>';
	}

	/**
	 * Date of an RFC 3339 value in the site format.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function date( $value ): string {
		$time = is_string( $value ) ? strtotime( $value ) : false;
		return false === $time ? '–' : (string) wp_date( (string) get_option( 'date_format' ), $time );
	}
}
