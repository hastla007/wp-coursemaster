<?php
/**
 * Quiz lessons in the learning area without JavaScript (UI-F8 learner gap,
 * h5 L-3; R0-08 §2.4, GM-007/GM-010; UI-F5 module pool).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\QuizPool;
use CourseCaptain\Rest\RestContract;

defined( 'ABSPATH' ) || exit;

/**
 * One question at a time inside the lesson page, in quiz order (fixed
 * inline questions, or the learner's draw from the module pool via
 * {@see QuizPool::draw()}). Delivery and evaluation use the existing
 * services only: {@see QuestionPresenter::deliver_inline()} (gate,
 * partial access, running exam lock, signed token) and
 * {@see AnswerService::answer()} (idempotent, lesson context); the
 * answer feeds the quiz progress and the provider's completion rule
 * completes the lesson (GM-010).
 *
 * After an answer the feedback is shown once (PRG with a short-lived
 * transient); when every question is answered the score is shown
 * (correct of total, percent). A pool quiz offers "new attempt" (new seed,
 * new draw). Open questions: "Show model answer", then self-assessment.
 * Accessibility like the trainer: fieldset named by heading, text and
 * hint; feedback in a `status` region, errors as `alert`.
 */
final class QuizView {

	/** Transient prefix of the feedback after a form post. */
	private const FLASH = 'ccap_quiz_';

	/** Form actions (`ccap_do`). */
	public const ACTIONS = array( 'quiz_answer', 'quiz_reveal', 'quiz_restart' );

	/**
	 * Quiz section of a lesson page.
	 *
	 * @param Actor  $actor     Learner.
	 * @param int    $course_id Course.
	 * @param int    $lesson_id Quiz lesson.
	 * @param string $lang      Content language attributes.
	 * @param string $status    Status after a form post.
	 * @return string
	 */
	public static function section( Actor $actor, int $course_id, int $lesson_id, string $lang, string $status ): string {
		$html = '<section class="ccap-activity ccap-quiz" aria-labelledby="ccap-activity-heading"><h2 id="ccap-activity-heading">' . esc_html__( 'Quiz', 'course-captain' ) . '</h2>';
		try {
			if ( QuizPool::is_pool( $lesson_id ) ) {
				QuizPool::current()->draw( $actor, $course_id, $lesson_id );
			}
			$ids = QuestionPresenter::current()->quiz_questions( $lesson_id, $actor->user_id );
		} catch ( CourseException $e ) {
			return $html . '<p role="alert">' . esc_html( RestContract::user_message( $e->error_code ) ) . '</p></section>';
		}
		if ( array() === $ids ) {
			return $html . '<p>' . esc_html__( 'This quiz has no questions yet.', 'course-captain' ) . '</p></section>';
		}
		$answers = AnswerService::current()->quiz_answers( $actor->user_id, $lesson_id, $ids );
		$total   = count( $ids );
		$done    = count( array_intersect_key( $answers, array_flip( $ids ) ) );
		$correct = count( array_filter( array_intersect_key( $answers, array_flip( $ids ) ) ) );
		$flash   = self::flash( $actor->user_id, $lesson_id );
		if ( 'quiz_answered' === $status && null !== $flash && isset( $flash['result'] ) ) {
			self::forget( $actor->user_id );
			$html .= self::feedback( $flash, $lang );
		}
		$html .= '<p class="ccap-quiz-progress">' . esc_html(
			sprintf(
				/* translators: 1: answered questions, 2: all questions, 3: correctly answered questions */
				_n( 'Answered: %1$s of %2$s question, correct: %3$s', 'Answered: %1$s of %2$s questions, correct: %3$s', $total, 'course-captain' ),
				number_format_i18n( $done ),
				number_format_i18n( $total ),
				number_format_i18n( $correct )
			)
		) . '</p>';
		$next = null;
		foreach ( $ids as $id ) {
			if ( ! isset( $answers[ $id ] ) ) {
				$next = $id;
				break;
			}
		}
		if ( null === $next ) {
			return $html . self::result( $course_id, $lesson_id, $correct, $total ) . '</section>';
		}
		if ( '' !== $status && 'quiz_answered' !== $status && null !== $flash && isset( $flash['model'] ) ) {
			return $html . self::form( $course_id, $lesson_id, (array) $flash['question'], (string) $flash['token'], $lang, (int) array_search( $next, $ids, true ) + 1, $total, (string) $flash['model'] ) . '</section>';
		}
		try {
			$delivery = QuestionPresenter::current()->deliver_inline( $actor, $course_id, $lesson_id, $next );
		} catch ( CourseException $e ) {
			return $html . '<p role="alert">' . esc_html( RestContract::user_message( $e->error_code ) ) . '</p></section>';
		}
		return $html . self::form( $course_id, $lesson_id, (array) $delivery['question'], (string) $delivery['token'], $lang, (int) array_search( $next, $ids, true ) + 1, $total ) . '</section>';
	}

	/**
	 * Form post of the quiz; returns the status for the redirect.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param int                  $lesson_id Quiz lesson.
	 * @param string               $action    One of {@see self::ACTIONS}.
	 * @param array<string, mixed> $post      Unslashed POST data (nonce checked by the lesson view).
	 * @return string `quiz_answered`, `quiz_revealed`, `quiz_restarted` or an error code.
	 */
	public static function handle( Actor $actor, int $course_id, int $lesson_id, string $action, array $post ): string {
		if ( 'quiz' !== ActivityService::definition( $lesson_id )['type'] || ! in_array( $action, self::ACTIONS, true ) ) {
			return 'ccap_invalid_param';
		}
		try {
			if ( 'quiz_restart' === $action ) {
				if ( ! QuizPool::is_pool( $lesson_id ) ) {
					return 'ccap_invalid_param';
				}
				QuizPool::current()->draw( $actor, $course_id, $lesson_id, true );
				self::forget( $actor->user_id );
				return 'quiz_restarted';
			}
			$token  = (string) ( $post['token'] ?? '' );
			$claims = QuestionPresenter::current()->verify( $actor, $token );
			if ( (int) $claims['c'] !== $course_id || (int) $claims['a'] !== $lesson_id ) {
				return 'ccap_invalid_token';
			}
			$revision = (array) QuestionRepository::current()->revision( (int) $claims['r'] );
			$type     = (string) ( $revision['question_type'] ?? '' );
			$question = array(
				'type' => $type,
				'text' => (string) ( $revision['question_text'] ?? '' ),
			);
			if ( 'quiz_reveal' === $action ) {
				if ( QuestionTypes::OPEN_TEXT !== $type ) {
					return 'ccap_invalid_param';
				}
				self::remember(
					$actor->user_id,
					array(
						'lesson'   => $lesson_id,
						'token'    => $token,
						'question' => $question,
						'model'    => (string) ( $revision['answers'][0]['text'] ?? '' ),
					)
				);
				return 'quiz_revealed';
			}
			$answer = QuestionForm::normalize( $type, $post['answer'] ?? null );
			if ( QuestionTypes::ORDERING === $type && null === $answer ) {
				return 'ordering_incomplete';
			}
			if ( QuestionForm::is_empty( $answer ) ) {
				return 'empty_answer';
			}
			$result = AnswerService::current()->answer( $actor, $token, $answer, (string) ( $post['request_key'] ?? '' ), 'lesson' );
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
		$right = array();
		foreach ( (array) ( $result['correct_ids'] ?? array() ) as $id ) {
			foreach ( (array) ( $revision['answers'] ?? array() ) as $option ) {
				if ( is_array( $option ) && (string) ( $option['key'] ?? '' ) === (string) $id ) {
					$right[] = (string) ( $option['text'] ?? '' );
				}
			}
		}
		self::remember(
			$actor->user_id,
			array(
				'lesson'   => $lesson_id,
				'question' => $question,
				'result'   => array(
					'correct'     => ! empty( $result['correct'] ),
					'self'        => QuestionTypes::OPEN_TEXT === $type,
					'correct_txt' => $right,
				),
			)
		);
		return 'quiz_answered';
	}

	/**
	 * Text of a quiz status after the redirect, or null when not a quiz status.
	 *
	 * @param string $status Status.
	 * @return array{ok: bool, text: string}|null
	 */
	public static function message( string $status ): ?array {
		$texts = array(
			'quiz_restarted'      => array( true, __( 'A new attempt has started with newly drawn questions.', 'course-captain' ) ),
			'empty_answer'        => array( false, __( 'Please answer the question first.', 'course-captain' ) ),
			'ordering_incomplete' => array( false, __( 'Please give every item its own position.', 'course-captain' ) ),
			'ccap_invalid_answer' => array( false, __( 'Please check your answer: it is not complete or not valid.', 'course-captain' ) ),
		);
		if ( in_array( $status, array( 'quiz_answered', 'quiz_revealed' ), true ) ) {
			return array(
				'ok'   => true,
				'text' => '',
			);
		}
		return isset( $texts[ $status ] ) ? array(
			'ok'   => $texts[ $status ][0],
			'text' => $texts[ $status ][1],
		) : null;
	}

	/**
	 * Question form.
	 *
	 * @param int                  $course_id Course.
	 * @param int                  $lesson_id Lesson.
	 * @param array<string, mixed> $question  Delivered payload (or type/text of an open question).
	 * @param string               $token     Question token.
	 * @param string               $lang      Content language attributes.
	 * @param int                  $position  Position of the question (1-based).
	 * @param int                  $total     Questions in the quiz.
	 * @param string|null          $model     Revealed model answer of an open question.
	 * @return string
	 */
	private static function form( int $course_id, int $lesson_id, array $question, string $token, string $lang, int $position, int $total, ?string $model = null ): string {
		$type  = (string) ( $question['type'] ?? '' );
		$hint  = QuestionForm::hint( $type );
		$html  = '<form method="post" class="ccap-quiz-form" action="' . esc_url( LearningArea::lesson_url( LearningArea::course_uid( $course_id ), $lesson_id ) ) . '">' . wp_nonce_field( LessonView::NONCE . ':' . $lesson_id, '_wpnonce', false, false );
		$html .= '<input type="hidden" name="token" value="' . esc_attr( $token ) . '"><input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
		/* translators: 1: position, 2: number of questions */
		$legend = sprintf( __( 'Question %1$s of %2$s', 'course-captain' ), number_format_i18n( $position ), number_format_i18n( $total ) );
		$html  .= '<section class="ccap-question" id="ccap-q"><fieldset aria-labelledby="ccap-q-legend ccap-q-text"><legend id="ccap-q-legend">' . esc_html( $legend ) . ( '' !== $hint ? ' <span class="ccap-hint">' . esc_html( $hint ) . '</span>' : '' ) . '</legend>';
		$html  .= '<div class="ccap-qtext" id="ccap-q-text"' . $lang . '>' . QuestionForm::text( $question ) . '</div>';
		if ( QuestionTypes::OPEN_TEXT === $type ) {
			if ( null === $model ) {
				return $html . '<p><button type="submit" class="ccap-button" name="ccap_do" value="quiz_reveal">' . esc_html__( 'Show model answer', 'course-captain' ) . '</button></p></fieldset></section></form>';
			}
			$html .= '<div class="ccap-model-answer" id="ccap-q-model"><p><strong>' . esc_html__( 'Model answer:', 'course-captain' ) . '</strong></p><div' . $lang . '>' . wp_kses_post( $model ) . '</div></div>';
			$html .= '<fieldset class="ccap-self" aria-describedby="ccap-q-model"><legend>' . esc_html__( 'Did you know the answer?', 'course-captain' ) . '</legend><ul class="ccap-options">';
			$html .= '<li><input type="radio" id="ccap-q-known-1" name="answer[known]" value="1"> <label for="ccap-q-known-1">' . esc_html__( 'Yes, I knew it', 'course-captain' ) . '</label></li>';
			$html .= '<li><input type="radio" id="ccap-q-known-0" name="answer[known]" value="0"> <label for="ccap-q-known-0">' . esc_html__( 'No, I did not know it', 'course-captain' ) . '</label></li></ul></fieldset>';
			return $html . '</fieldset></section><p><button type="submit" class="ccap-button" name="ccap_do" value="quiz_answer">' . esc_html__( 'Save self-assessment', 'course-captain' ) . '</button></p></form>';
		}
		$fields = QuestionForm::fields( $question, 'answer', 'ccap-q', null, $lang );
		$html  .= null === $fields ? '<p class="ccap-hint">' . esc_html__( 'This question type cannot be answered here.', 'course-captain' ) . '</p>' : $fields;
		return $html . '</fieldset></section><p><button type="submit" class="ccap-button" name="ccap_do" value="quiz_answer">' . esc_html__( 'Check answer', 'course-captain' ) . '</button></p></form>';
	}

	/**
	 * Feedback after an answer (status region).
	 *
	 * @param array<string, mixed> $flash Stored feedback.
	 * @param string               $lang  Content language attributes.
	 * @return string
	 */
	private static function feedback( array $flash, string $lang ): string {
		$result = (array) $flash['result'];
		$html   = '<div class="ccap-feedback"><p class="ccap-status" role="status"><strong>';
		if ( ! empty( $result['self'] ) ) {
			$html .= esc_html( ! empty( $result['correct'] ) ? __( 'Saved: you knew the answer.', 'course-captain' ) : __( 'Saved: you did not know the answer yet.', 'course-captain' ) );
		} else {
			$html .= esc_html( ! empty( $result['correct'] ) ? __( 'Correct!', 'course-captain' ) : __( 'Not correct.', 'course-captain' ) );
		}
		$html .= '</strong></p><div class="ccap-qtext"' . $lang . '>' . QuestionForm::text( (array) $flash['question'] ) . '</div>';
		$right = array_filter( array_map( 'strval', (array) ( $result['correct_txt'] ?? array() ) ) );
		if ( array() !== $right ) {
			$html .= '<p>' . esc_html( _n( 'Correct answer:', 'Correct answers:', count( $right ), 'course-captain' ) ) . '</p><ul' . $lang . '>';
			foreach ( $right as $text ) {
				$html .= '<li>' . esc_html( $text ) . '</li>';
			}
			$html .= '</ul>';
		}
		return $html . '</div>';
	}

	/**
	 * Score when every question is answered.
	 *
	 * @param int $course_id Course.
	 * @param int $lesson_id Lesson.
	 * @param int $correct   Correctly answered questions.
	 * @param int $total     Questions.
	 * @return string
	 */
	private static function result( int $course_id, int $lesson_id, int $correct, int $total ): string {
		$percent = $total > 0 ? (int) floor( 100 * $correct / $total ) : 0;
		$html    = '<p class="ccap-quiz-result">' . esc_html(
			sprintf(
				/* translators: 1: correctly answered questions, 2: all questions, 3: percent */
				_n( 'You answered all questions. Score: %1$s of %2$s question correct (%3$s %%).', 'You answered all questions. Score: %1$s of %2$s questions correct (%3$s %%).', $total, 'course-captain' ),
				number_format_i18n( $correct ),
				number_format_i18n( $total ),
				number_format_i18n( $percent )
			)
		) . '</p>';
		if ( QuizPool::is_pool( $lesson_id ) ) {
			$html .= '<form method="post" action="' . esc_url( LearningArea::lesson_url( LearningArea::course_uid( $course_id ), $lesson_id ) ) . '">' . wp_nonce_field( LessonView::NONCE . ':' . $lesson_id, '_wpnonce', false, false ) . '<p><button type="submit" class="ccap-button" name="ccap_do" value="quiz_restart">' . esc_html__( 'Start a new attempt with new questions', 'course-captain' ) . '</button></p></form>';
		}
		return $html;
	}

	/**
	 * Stored feedback of this lesson.
	 *
	 * @param int $user      Learner.
	 * @param int $lesson_id Lesson.
	 * @return array<string, mixed>|null
	 */
	private static function flash( int $user, int $lesson_id ): ?array {
		$data = $user > 0 ? get_transient( self::FLASH . $user ) : false;
		return is_array( $data ) && (int) ( $data['lesson'] ?? 0 ) === $lesson_id ? $data : null;
	}

	/**
	 * Store feedback (30 minutes).
	 *
	 * @param int                  $user Learner.
	 * @param array<string, mixed> $data Data.
	 * @return void
	 */
	private static function remember( int $user, array $data ): void {
		set_transient( self::FLASH . $user, $data, 30 * MINUTE_IN_SECONDS );
	}

	/**
	 * Drop stored feedback.
	 *
	 * @param int $user Learner.
	 * @return void
	 */
	private static function forget( int $user ): void {
		if ( $user > 0 ) {
			delete_transient( self::FLASH . $user );
		}
	}
}
