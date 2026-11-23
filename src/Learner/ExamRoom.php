<?php
/**
 * Server-rendered exam room in the learning area (R1-19, R0-15 §2.2/§2.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Locale;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Rest\RestContract;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Works without JavaScript (forms, PRG); a small inline script only
 * counts the timer down and announces thresholds. Accessibility:
 * - every question is a `fieldset` named by number, text and hint (A11Y-022);
 * - timer `role=timer` with `aria-live=off`, announcements at 10/5/1 min
 *   in one polite status region, "announce remaining time" (A11Y-071);
 * - save status in the same region, errors as `alert` (K-05, A11Y-025);
 * - question navigation lists the state as text (A11Y-007);
 * - submit needs a confirmation naming open and flagged questions (A11Y-074);
 * - expiry is announced as alert on the result (A11Y-073);
 * - open questions: "Show model answer" is a submit button, the
 *   self-assessment two radio buttons (no JavaScript needed, R1-18 F-4);
 * - cancelling asks on a page of its own and returns to the course room
 *   with one message (GM-051).
 * With JavaScript, `assets/js/exam-room.js` saves each change at once and
 * buffers it in localStorage (key = session UID) while offline; the buffer
 * is re-sent with its age (`age_ms`) and removed after the server confirmed
 * it, after submission and on the result page (GM-050, R0-17 T-04, PRIV-018).
 * The server stays authoritative for the time (R0-08 §4.3).
 */
final class ExamRoom {

	public const NONCE = 'ccap_exam_room';

	/**
	 * Question types answerable in this view.
	 *
	 * @var list<string>
	 */
	private const SUPPORTED = array( QuestionTypes::SINGLE, QuestionTypes::TRUE_FALSE, QuestionTypes::MULTI, QuestionTypes::NUMERIC, QuestionTypes::MATCHING, QuestionTypes::OPEN_TEXT );

	/** Transient prefix of the one-time course room message (GM-051). */
	public const FLASH = 'ccap_flash_';

	/**
	 * Announcement thresholds in seconds (A11Y-071).
	 *
	 * @var list<int>
	 */
	private const THRESHOLDS = array( 600, 300, 60 );

	/**
	 * Page title and body of a session.
	 *
	 * @param Actor  $actor   Learner.
	 * @param int    $session Session.
	 * @param string $status  Status code after a form post ('' = none).
	 * @return array{title: string, body: string}
	 * @throws CourseException For foreign or unknown sessions.
	 */
	public static function render( Actor $actor, int $session, string $status = '' ): array {
		$view = ExamSessions::current()->view( $actor, $session );
		$lang = self::content_language( $session );
		$uid  = self::session_uid( $session );
		if ( 'in_progress' !== $view['status'] ) {
			// PRIV-018: no exam buffer survives the end of the attempt.
			return array(
				'title' => __( 'Exam result', 'course-captain' ),
				'body'  => self::result( $view, self::end_reason( $session ) ) . wp_get_inline_script_tag( 'try{window.localStorage.removeItem(' . wp_json_encode( 'ccap_exam_' . $uid ) . ');}catch(e){}' ),
			);
		}
		if ( 'confirm_cancel' === $status ) {
			return array(
				'title' => __( 'Cancel the exam?', 'course-captain' ),
				'body'  => self::cancel_dialog( $session ),
			);
		}
		$items = (array) $view['questions'];
		$total = count( $items );
		$html  = '<div class="ccap-exam-bar">';
		if ( null !== $view['remaining_seconds'] ) {
			$remaining = (int) $view['remaining_seconds'];
			$messages  = array();
			foreach ( self::THRESHOLDS as $threshold ) {
				$minutes                = intdiv( $threshold, 60 );
				$messages[ $threshold ] = sprintf(
					/* translators: %s: number of minutes */
					_n( '%s minute remaining.', '%s minutes remaining.', $minutes, 'course-captain' ),
					number_format_i18n( $minutes )
				);
			}
			$html .= '<p class="ccap-timer-line"><span id="ccap-timer-label">' . esc_html__( 'Time remaining:', 'course-captain' ) . '</span> <span id="ccap-timer" role="timer" aria-live="off" aria-labelledby="ccap-timer-label" dir="ltr" data-remaining="' . (int) $remaining . '"';
			foreach ( $messages as $threshold => $message ) {
				$html .= ' data-t' . (int) $threshold . '="' . esc_attr( $message ) . '"';
			}
			$html .= ' data-expired="' . esc_attr__( 'The time is up. Your answers are being submitted.', 'course-captain' ) . '">' . esc_html( self::clock( $remaining ) ) . '</span>';
			/* translators: %s: remaining time as clock value, e.g. 12:30 */
			$html .= ' <button type="button" class="ccap-button ccap-timer-announce" id="ccap-timer-announce" hidden data-label="' . esc_attr__( 'Remaining time: %s', 'course-captain' ) . '">' . esc_html__( 'Announce remaining time', 'course-captain' ) . '</button></p>';
		}
		$notes = array(
			'saved'    => __( 'Your answers have been saved.', 'course-captain' ),
			'revealed' => __( 'The model answer is shown. Please assess your answer.', 'course-captain' ),
		);
		$html .= '<div id="ccap-exam-live" class="ccap-live" role="status" aria-live="polite">' . esc_html( $notes[ $status ] ?? '' ) . '</div>';
		$html .= '</div>';
		$html .= '<p id="ccap-offline" class="ccap-offline" hidden>' . esc_html__( 'No connection. Your answers are kept on this device and sent as soon as the connection is back.', 'course-captain' ) . '</p>';
		if ( '' !== $status && ! isset( $notes[ $status ] ) ) {
			$html .= '<p class="ccap-error" role="alert">' . esc_html( 'confirm_required' === $status ? __( 'Please confirm that you want to submit the exam.', 'course-captain' ) : RestContract::user_message( $status ) ) . '</p>';
		}

		$open    = 0;
		$flagged = 0;
		$nav     = '<nav aria-label="' . esc_attr__( 'Questions', 'course-captain' ) . '"><ol class="ccap-qnav">';
		foreach ( $items as $item ) {
			$position = (int) $item['position'];
			$open    += $item['answered'] ? 0 : 1;
			$flagged += $item['flagged'] ? 1 : 0;
			$state    = $item['answered'] ? __( 'answered', 'course-captain' ) : __( 'open', 'course-captain' );
			if ( $item['flagged'] ) {
				/* translators: %s: answer state ("answered" or "open") */
				$state = sprintf( __( '%s, marked for review', 'course-captain' ), $state );
			}
			$nav .= '<li><a href="#ccap-q-' . $position . '">' . esc_html(
				sprintf(
					/* translators: %s: question number */
					__( 'Question %s', 'course-captain' ),
					number_format_i18n( $position )
				)
			) . ' <span class="ccap-qstate">(' . esc_html( $state ) . ')</span></a></li>';
		}
		$html .= $nav . '</ol></nav>';

		$html .= '<form method="post" class="ccap-exam-form" action="' . esc_url( LearningArea::url( 'pruefung/' . $session ) ) . '" data-ccap-session="' . esc_attr( $uid ) . '" data-ccap-rest="' . esc_url( rest_url( RestContract::NAMESPACE . '/exam-sessions/' . $session . '/answers/' ) ) . '" data-ccap-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '" data-ccap-synced="' . esc_attr__( 'Connection is back. All answers have been sent.', 'course-captain' ) . '">' . wp_nonce_field( self::NONCE, '_wpnonce', false, false );
		// Implicit submission (Enter in a field) saves; it must never reveal a model answer or cancel.
		$html .= '<button type="submit" class="screen-reader-text" name="ccap_do" value="save" tabindex="-1" aria-hidden="true">' . esc_html__( 'Save answers', 'course-captain' ) . '</button>';
		foreach ( $items as $item ) {
			$html .= self::question( $item, $total, $lang );
		}
		$html .= '<p><button type="submit" class="ccap-button" name="ccap_do" value="save">' . esc_html__( 'Save answers', 'course-captain' ) . '</button></p>';
		$html .= '<section class="ccap-submit" aria-labelledby="ccap-submit-heading"><h2 id="ccap-submit-heading">' . esc_html__( 'Submit the exam', 'course-captain' ) . '</h2><p id="ccap-submit-summary">' . esc_html( self::summary( $open, $flagged ) ) . '</p>';
		$html .= '<p><input type="checkbox" id="ccap-confirm" name="confirm" value="1" aria-describedby="ccap-submit-summary"> <label for="ccap-confirm">' . esc_html__( 'I want to submit my answers now. I cannot change them afterwards.', 'course-captain' ) . '</label></p>';
		$html .= '<p><button type="submit" class="ccap-button" name="ccap_do" value="submit">' . esc_html__( 'Submit exam', 'course-captain' ) . '</button></p></section>';
		$html .= '<section class="ccap-cancel" aria-labelledby="ccap-cancel-heading"><h2 id="ccap-cancel-heading">' . esc_html__( 'Cancel the exam', 'course-captain' ) . '</h2><p id="ccap-cancel-hint">' . esc_html__( 'A cancelled attempt is not evaluated. You will be asked to confirm.', 'course-captain' ) . '</p>';
		$html .= '<p><button type="submit" class="ccap-button" name="ccap_do" value="cancel" aria-describedby="ccap-cancel-hint">' . esc_html__( 'Cancel exam', 'course-captain' ) . '</button></p></section></form>';
		if ( null !== $view['remaining_seconds'] ) {
			$html .= wp_get_inline_script_tag( self::script() );
		}
		wp_enqueue_script( 'ccap-exam-room', plugins_url( 'assets/js/exam-room.js', COURSE_CAPTAIN_FILE ), array(), COURSE_CAPTAIN_VERSION, true );
		return array(
			'title' => __( 'Exam', 'course-captain' ),
			'body'  => $html,
		);
	}

	/**
	 * Handle a form post; returns the status shown after the redirect.
	 *
	 * @param Actor                $actor   Learner.
	 * @param int                  $session Session.
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @return string `saved`, `submitted`, `confirm_required`, `revealed:<position>`,
	 *                `confirm_cancel`, `cancelled` or an error code.
	 */
	public static function handle( Actor $actor, int $session, array $post ): string {
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::NONCE ) ) {
			return 'ccap_invalid_token';
		}
		$sessions = ExamSessions::current();
		try {
			if ( 'cancel_confirmed' === ( $post['ccap_do'] ?? '' ) ) {
				// Confirmation page (GM-051): no answer fields; never touch the stored answers.
				$sessions->cancel( $actor, $session );
				return 'cancelled';
			}
			$view    = $sessions->view( $actor, $session );
			$answers = is_array( $post['answer'] ?? null ) ? $post['answer'] : array();
			$flags   = is_array( $post['flag'] ?? null ) ? $post['flag'] : array();
			if ( 'in_progress' !== $view['status'] ) {
				return 'cancelled' === $view['status'] ? 'ccap_exam_aborted' : 'ccap_exam_already_submitted';
			}
			foreach ( (array) $view['questions'] as $item ) {
				$position = (int) $item['position'];
				$type     = (string) $item['question']['type'];
				if ( in_array( $type, self::SUPPORTED, true ) ) {
					$given  = self::normalize( $type, $answers[ $position ] ?? null );
					$stored = self::normalize( $type, $item['answer'] );
					// Open questions: only after "Show model answer" and with a chosen self-assessment.
					$skip = QuestionTypes::OPEN_TEXT === $type && ( null === $given || empty( $item['revealed'] ) );
					if ( ! $skip && $stored !== $given ) {
						$sessions->save_answer( $actor, $session, $position, $given );
					}
				}
				$flag = isset( $flags[ $position ] );
				if ( $flag !== (bool) $item['flagged'] ) {
					$sessions->flag( $actor, $session, $position, $flag );
				}
			}
			$reveal = (int) ( $post['ccap_reveal'] ?? 0 );
			if ( $reveal > 0 ) {
				$sessions->reveal( $actor, $session, $reveal );
				return 'revealed:' . $reveal;
			}
			if ( 'cancel' === ( $post['ccap_do'] ?? '' ) ) {
				return 'confirm_cancel';
			}
			if ( 'submit' === ( $post['ccap_do'] ?? '' ) ) {
				if ( empty( $post['confirm'] ) ) {
					return 'confirm_required';
				}
				$sessions->submit( $actor, $session );
				return 'submitted';
			}
			return 'saved';
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
	}

	/**
	 * Canonical form of a posted or stored answer.
	 *
	 * @param string $type   Question type.
	 * @param mixed  $answer Answer.
	 * @return mixed
	 */
	private static function normalize( string $type, $answer ) {
		switch ( $type ) {
			case QuestionTypes::MULTI:
				$keys = array_values( array_filter( array_map( 'strval', (array) $answer ), static fn( string $k ): bool => '' !== $k ) );
				sort( $keys );
				return $keys;
			case QuestionTypes::MATCHING:
				$map = array();
				foreach ( (array) $answer as $left => $right ) {
					if ( '' !== (string) $right ) {
						$map[ (string) $left ] = (string) $right;
					}
				}
				ksort( $map );
				return $map;
			case QuestionTypes::NUMERIC:
				return trim( str_replace( ',', '.', is_scalar( $answer ) ? (string) $answer : '' ) );
			case QuestionTypes::OPEN_TEXT:
				// Self-assessment {known: bool}; posted as "1"/"0", stored as JSON boolean.
				if ( ! is_array( $answer ) || ! array_key_exists( 'known', $answer ) || ! in_array( $answer['known'], array( true, false, '1', '0', 1, 0 ), true ) ) {
					return null;
				}
				return array( 'known' => in_array( $answer['known'], array( true, '1', 1 ), true ) );
			default:
				return is_scalar( $answer ) ? (string) $answer : '';
		}
	}

	/**
	 * One question as fieldset.
	 *
	 * @param array<string, mixed> $item  View item.
	 * @param int                  $total Number of questions.
	 * @param string               $lang  Content language.
	 * @return string
	 */
	private static function question( array $item, int $total, string $lang ): string {
		$position = (int) $item['position'];
		$question = (array) $item['question'];
		$type     = (string) $question['type'];
		$id       = 'ccap-q-' . $position;
		$hints    = array(
			QuestionTypes::SINGLE     => __( 'Choose one answer.', 'course-captain' ),
			QuestionTypes::TRUE_FALSE => __( 'Choose one answer.', 'course-captain' ),
			QuestionTypes::MULTI      => __( 'Choose one or more answers.', 'course-captain' ),
			QuestionTypes::NUMERIC    => __( 'Enter a number.', 'course-captain' ),
			QuestionTypes::MATCHING   => __( 'Match each item.', 'course-captain' ),
			QuestionTypes::OPEN_TEXT  => __( 'Answer in your mind, then compare with the model answer.', 'course-captain' ),
		);
		$html     = '<section class="ccap-question" id="' . $id . '" tabindex="-1" data-ccap-position="' . $position . '" data-ccap-type="' . esc_attr( $type ) . '">';
		$html    .= '<fieldset aria-labelledby="' . $id . '-legend ' . $id . '-text"><legend id="' . $id . '-legend">' . esc_html(
			sprintf(
				/* translators: 1: question number, 2: number of questions */
				__( 'Question %1$s of %2$s', 'course-captain' ),
				number_format_i18n( $position ),
				number_format_i18n( $total )
			)
		) . ( isset( $hints[ $type ] ) ? ' <span class="ccap-hint">' . esc_html( $hints[ $type ] ) . '</span>' : '' ) . '</legend>';
		$html   .= '<div class="ccap-qtext" id="' . $id . '-text"' . Locale::content_attributes( $lang ) . '>' . wp_kses_post( (string) $question['text'] ) . '</div>';
		$answer  = $item['answer'];
		$name    = 'answer[' . $position . ']';
		$content = Locale::content_attributes( $lang );
		switch ( $type ) {
			case QuestionTypes::SINGLE:
			case QuestionTypes::TRUE_FALSE:
			case QuestionTypes::MULTI:
				$multi  = QuestionTypes::MULTI === $type;
				$chosen = array_map( 'strval', (array) $answer );
				$html  .= '<ul class="ccap-options"' . $content . '>';
				foreach ( (array) ( $question['options'] ?? array() ) as $option ) {
					$key   = (string) $option['key'];
					$oid   = $id . '-' . sanitize_html_class( $key );
					$html .= '<li><input type="' . ( $multi ? 'checkbox' : 'radio' ) . '" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $chosen, true ), true, false ) . '> <label for="' . esc_attr( $oid ) . '">' . esc_html( (string) $option['text'] ) . '</label></li>';
				}
				$html .= '</ul>';
				break;
			case QuestionTypes::NUMERIC:
				$unit  = (string) ( $question['unit'] ?? '' );
				$label = '' === $unit ? __( 'Your answer', 'course-captain' ) : sprintf(
					/* translators: %s: unit, e.g. "km" */
					__( 'Your answer (in %s)', 'course-captain' ),
					$unit
				);
				$html .= '<p><label for="' . $id . '-input">' . esc_html( $label ) . '</label> <input type="text" inputmode="decimal" autocomplete="off" id="' . $id . '-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $answer ) ? (string) $answer : '' ) . '"></p>';
				break;
			case QuestionTypes::MATCHING:
				$given = (array) $answer;
				$html .= '<ul class="ccap-options"' . $content . '>';
				foreach ( (array) ( $question['left'] ?? array() ) as $left ) {
					$lkey  = (string) $left['key'];
					$sid   = $id . '-' . sanitize_html_class( $lkey );
					$html .= '<li><label for="' . esc_attr( $sid ) . '">' . esc_html( (string) $left['text'] ) . '</label> <select id="' . esc_attr( $sid ) . '" name="' . esc_attr( $name . '[' . $lkey . ']' ) . '"><option value="">' . esc_html__( '— choose —', 'course-captain' ) . '</option>';
					foreach ( (array) ( $question['right'] ?? array() ) as $right ) {
						$html .= '<option value="' . esc_attr( (string) $right['key'] ) . '"' . selected( (string) ( $given[ $lkey ] ?? '' ), (string) $right['key'], false ) . '>' . esc_html( (string) $right['text'] ) . '</option>';
					}
					$html .= '</select></li>';
				}
				$html .= '</ul>';
				break;
			case QuestionTypes::OPEN_TEXT:
				if ( empty( $item['revealed'] ) ) {
					$html .= '<p><button type="submit" class="ccap-button" name="ccap_reveal" value="' . $position . '">' . esc_html__( 'Show model answer', 'course-captain' ) . '</button></p>';
					break;
				}
				$known  = is_array( $answer ) && array_key_exists( 'known', $answer ) ? (bool) $answer['known'] : null;
				$html  .= '<div class="ccap-model-answer" id="' . $id . '-model"><p><strong>' . esc_html__( 'Model answer:', 'course-captain' ) . '</strong></p><div' . $content . '>' . wp_kses_post( (string) ( $item['model_answer'] ?? '' ) ) . '</div></div>';
				$html  .= '<fieldset class="ccap-self" aria-describedby="' . $id . '-model"><legend>' . esc_html__( 'Did you know the answer?', 'course-captain' ) . '</legend><ul class="ccap-options">';
				$choice = array(
					'1' => __( 'Yes, I knew it', 'course-captain' ),
					'0' => __( 'No, I did not know it', 'course-captain' ),
				);
				foreach ( $choice as $value => $label ) {
					$oid   = $id . '-known-' . $value;
					$html .= '<li><input type="radio" id="' . $oid . '" name="' . esc_attr( $name . '[known]' ) . '" value="' . $value . '"' . checked( null !== $known && ( '1' === (string) $value ) === $known, true, false ) . '> <label for="' . $oid . '">' . esc_html( $label ) . '</label></li>';
				}
				$html .= '</ul></fieldset>';
				break;
			default:
				$html .= '<p class="ccap-hint">' . esc_html__( 'This question type cannot be answered in this view yet. You can still submit the exam; the question counts as unanswered.', 'course-captain' ) . '</p>';
		}
		$html .= '</fieldset><p><input type="checkbox" id="' . $id . '-flag" name="flag[' . $position . ']" value="1"' . checked( (bool) $item['flagged'], true, false ) . '> <label for="' . $id . '-flag">' . esc_html__( 'Mark for review', 'course-captain' ) . '</label></p></section>';
		return $html;
	}

	/**
	 * Confirmation text naming open and flagged questions (A11Y-074).
	 *
	 * @param int $open    Unanswered.
	 * @param int $flagged Flagged.
	 * @return string
	 */
	private static function summary( int $open, int $flagged ): string {
		if ( 0 === $open && 0 === $flagged ) {
			return __( 'All questions are answered.', 'course-captain' );
		}
		// Two complete sentences, each translated as a whole (I18N-R-03).
		return sprintf(
			/* translators: %s: number of unanswered questions */
			_n( '%s question is unanswered.', '%s questions are unanswered.', $open, 'course-captain' ),
			number_format_i18n( $open )
		) . ' ' . sprintf(
			/* translators: %s: number of questions marked for review */
			_n( '%s question is marked for review.', '%s questions are marked for review.', $flagged, 'course-captain' ),
			number_format_i18n( $flagged )
		);
	}

	/**
	 * Result after submission or expiry.
	 *
	 * @param array<string, mixed> $view       Session view.
	 * @param string               $end_reason End reason.
	 * @return string
	 */
	private static function result( array $view, string $end_reason ): string {
		$html = '';
		if ( 'expired' === $end_reason ) {
			$html .= '<p role="alert">' . esc_html__( 'The time is up. Your answers have been submitted.', 'course-captain' ) . '</p>';
		}
		if ( 'cancelled' === $view['status'] ) {
			return $html . '<p>' . esc_html__( 'This attempt was cancelled.', 'course-captain' ) . '</p>';
		}
		$result = is_array( $view['result'] ) ? $view['result'] : array();
		if ( 'evaluated' !== $view['status'] || empty( $result['released'] ) ) {
			return $html . '<p>' . esc_html__( 'Your answers have been submitted. The result is not available yet.', 'course-captain' ) . '</p>';
		}
		$html .= '<h2>' . esc_html( ! empty( $result['passed'] ) ? __( 'Passed', 'course-captain' ) : __( 'Not passed', 'course-captain' ) ) . '</h2>';
		$html .= '<p>' . esc_html(
			sprintf(
				/* translators: 1: correct answers, 2: number of questions */
				_n( '%1$s of %2$s question answered correctly.', '%1$s of %2$s questions answered correctly.', (int) $result['total'], 'course-captain' ),
				number_format_i18n( (int) $result['correct'] ),
				number_format_i18n( (int) $result['total'] )
			)
		) . '</p>';
		if ( isset( $result['points_milli'], $result['max_points_milli'] ) ) {
			$html .= '<p>' . esc_html(
				sprintf(
					/* translators: 1: points reached, 2: maximum points */
					__( 'Points: %1$s of %2$s', 'course-captain' ),
					number_format_i18n( $result['points_milli'] / 1000, 1 ),
					number_format_i18n( $result['max_points_milli'] / 1000, 1 )
				)
			) . '</p>';
		}
		$html .= '<ol class="ccap-result-list">';
		foreach ( (array) ( $result['questions'] ?? array() ) as $question ) {
			$html .= '<li>' . esc_html(
				sprintf(
					/* translators: 1: question number, 2: "correct" or "incorrect" */
					__( 'Question %1$s: %2$s', 'course-captain' ),
					number_format_i18n( (int) $question['position'] ),
					! empty( $question['correct'] ) ? __( 'correct', 'course-captain' ) : __( 'incorrect', 'course-captain' )
				)
			) . '</li>';
		}
		return $html . '</ol><p><a href="' . esc_url( LearningArea::url() ) . '">' . esc_html__( 'Back to my learning', 'course-captain' ) . '</a></p>';
	}

	/**
	 * Remaining seconds as clock (`m:ss` or `h:mm:ss`, I18N-R-25).
	 *
	 * @param int $seconds Seconds.
	 * @return string
	 */
	public static function clock( int $seconds ): string {
		$seconds = max( 0, $seconds );
		$h       = intdiv( $seconds, 3600 );
		$m       = intdiv( $seconds % 3600, 60 );
		$s       = $seconds % 60;
		return $h > 0 ? sprintf( '%d:%02d:%02d', $h, $m, $s ) : sprintf( '%d:%02d', $m, $s );
	}

	/**
	 * Confirmation page before cancelling (GM-051): question, consequence,
	 * "Yes, cancel" (POST) and "No, continue" (back to the exam).
	 *
	 * @param int $session Session.
	 * @return string
	 */
	private static function cancel_dialog( int $session ): string {
		$url = LearningArea::url( 'pruefung/' . $session );
		return '<section class="ccap-confirm-cancel" aria-labelledby="ccap-confirm-cancel-text"><p id="ccap-confirm-cancel-text" role="alert">' . esc_html__( 'Do you really want to cancel the exam? The attempt is not evaluated and cannot be continued. Your answers so far are kept.', 'course-captain' ) . '</p>'
			. '<form method="post" action="' . esc_url( $url ) . '">' . wp_nonce_field( self::NONCE, '_wpnonce', false, false ) . '<p><button type="submit" class="ccap-button" name="ccap_do" value="cancel_confirmed">' . esc_html__( 'Yes, cancel the exam', 'course-captain' ) . '</button> '
			. '<a class="ccap-button" href="' . esc_url( $url ) . '">' . esc_html__( 'No, continue the exam', 'course-captain' ) . '</a></p></form></section>';
	}

	/**
	 * Remember the one-time course room message after a cancel (GM-051).
	 *
	 * @param int $user User.
	 * @return void
	 */
	public static function flash_cancelled( int $user ): void {
		set_transient( self::FLASH . $user, 'exam_cancelled', 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * One-time course room message (read once, then removed).
	 *
	 * @param int $user User.
	 * @return string HTML ('' = none).
	 */
	public static function take_flash( int $user ): string {
		if ( $user <= 0 || 'exam_cancelled' !== get_transient( self::FLASH . $user ) ) {
			return '';
		}
		delete_transient( self::FLASH . $user );
		return '<p class="ccap-status ccap-flash" role="status">' . esc_html__( 'The exam was cancelled. The attempt was not evaluated.', 'course-captain' ) . '</p>';
	}

	/**
	 * Course UID of a session (ownership checked by the caller).
	 *
	 * @param int $session Session.
	 * @return string
	 */
	public static function course_uid_of( int $session ): string {
		$db = Database::current();
		return LearningArea::course_uid( (int) $db->scalar( $db->prepare( 'SELECT course_id FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) ) );
	}

	/**
	 * UID of a session (key of the client buffer, R0-17 T-04).
	 *
	 * @param int $session Session (ownership checked by the caller).
	 * @return string
	 */
	private static function session_uid( int $session ): string {
		$db = Database::current();
		return (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
	}

	/**
	 * End reason of a finished session.
	 *
	 * @param int $session Session (ownership checked by the caller).
	 * @return string
	 */
	private static function end_reason( int $session ): string {
		$db = Database::current();
		return (string) $db->scalar( $db->prepare( 'SELECT end_reason FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
	}

	/**
	 * Content language of the exam (course language, A11Y-032).
	 *
	 * @param int $session Session (ownership checked by the caller).
	 * @return string
	 */
	private static function content_language( int $session ): string {
		$db     = Database::current();
		$course = (int) $db->scalar( $db->prepare( 'SELECT r.course_id FROM `' . $db->table( 'ccap_exam_session' ) . '` s JOIN `' . $db->table( 'ccap_exam_rule' ) . '` r ON r.id = s.rule_id WHERE s.id = %d', $session ) );
		return $course > 0 ? CourseStructure::course_language( $course ) : '';
	}

	/**
	 * Published exams of a course for the course room (start form).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return string
	 */
	public static function course_exams( Actor $actor, int $course_id ): string {
		$db    = Database::current();
		$rules = $db->rows( $db->prepare( 'SELECT id, version, time_limit_seconds FROM `' . $db->table( 'ccap_exam_rule' ) . "` WHERE course_id = %d AND status = 'published' ORDER BY id", $course_id ) );
		if ( array() === $rules ) {
			return '';
		}
		$running = $db->rows( $db->prepare( 'SELECT id, rule_id FROM `' . $db->table( 'ccap_exam_session' ) . "` WHERE user_id = %d AND status = 'in_progress'", $actor->user_id ) );
		$running = array_column( $running, 'id', 'rule_id' );
		$html    = '<section class="ccap-exams" aria-labelledby="ccap-exams-heading"><h2 id="ccap-exams-heading">' . esc_html__( 'Exams', 'course-captain' ) . '</h2><ul>';
		foreach ( $rules as $rule ) {
			/* translators: %s: exam version label */
			$label = sprintf( __( 'Exam %s', 'course-captain' ), (string) $rule['version'] );
			$html .= '<li><h3>' . esc_html( $label ) . '</h3>';
			if ( null !== $rule['time_limit_seconds'] ) {
				$minutes = (int) ceil( (int) $rule['time_limit_seconds'] / 60 );
				$html   .= '<p>' . esc_html(
					sprintf(
						/* translators: %s: number of minutes */
						_n( 'Time limit: %s minute', 'Time limit: %s minutes', $minutes, 'course-captain' ),
						number_format_i18n( $minutes )
					)
				) . '</p>';
			}
			if ( isset( $running[ $rule['id'] ] ) ) {
				$html .= '<p><a class="ccap-button" href="' . esc_url( LearningArea::url( 'pruefung/' . (int) $running[ $rule['id'] ] ) ) . '">' . esc_html__( 'Continue exam', 'course-captain' ) . '</a></p>';
			} else {
				$html .= '<form method="post" action=""><input type="hidden" name="ccap_do" value="start_exam"><input type="hidden" name="rule" value="' . (int) $rule['id'] . '"><input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">' . wp_nonce_field( self::NONCE, '_wpnonce', false, false ) . '<button type="submit" class="ccap-button">' . esc_html__( 'Start exam', 'course-captain' ) . '</button></form>';
			}
			$html .= '</li>';
		}
		return $html . '</ul></section>';
	}

	/**
	 * Start an exam from the course room form.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param array<string, mixed> $post      Unslashed POST data.
	 * @return int|string Session ID or error code.
	 */
	public static function start( Actor $actor, int $course_id, array $post ) {
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::NONCE ) ) {
			return 'ccap_invalid_token';
		}
		$db   = Database::current();
		$rule = (int) $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_exam_rule' ) . '` WHERE id = %d AND course_id = %d', (int) ( $post['rule'] ?? 0 ), $course_id ) );
		$key  = (string) ( $post['request_key'] ?? '' );
		if ( $rule <= 0 || 1 !== preg_match( '/^[A-Za-z0-9_-]{8,36}$/', $key ) ) {
			return 'ccap_invalid_param';
		}
		try {
			return ExamSessions::current()->start( $actor, $rule, null, $key );
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
	}

	/**
	 * Timer script: countdown, threshold announcements, announce button.
	 *
	 * @return string
	 */
	private static function script(): string {
		return '(function(){var t=document.getElementById("ccap-timer");if(!t){return;}'
			. 'var live=document.getElementById("ccap-exam-live"),btn=document.getElementById("ccap-timer-announce"),'
			. 'end=Date.now()+1000*parseInt(t.getAttribute("data-remaining"),10),said={};'
			. 'function clock(s){var h=Math.floor(s/3600),m=Math.floor(s%3600/60),r=s%60;return(h?h+":"+(m<10?"0":""):"")+m+":"+(r<10?"0":"")+r;}'
			. 'function say(x){live.textContent="";window.setTimeout(function(){live.textContent=x;},50);}'
			. 'btn.hidden=false;btn.addEventListener("click",function(){say(btn.getAttribute("data-label").replace("%s",t.textContent));});'
			. 'function tick(){var s=Math.max(0,Math.round((end-Date.now())/1000));t.textContent=clock(s);'
			. '[600,300,60].forEach(function(x){var m=t.getAttribute("data-t"+x);if(m&&s<=x&&s>x-5&&!said[x]){said[x]=1;say(m);}});'
			. 'if(s<=0){say(t.getAttribute("data-expired"));window.setTimeout(function(){window.location.reload();},3000);return;}'
			. 'window.setTimeout(tick,1000);}tick();})();';
	}
}
