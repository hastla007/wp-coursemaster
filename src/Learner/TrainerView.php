<?php
/**
 * Smart trainer, mistake training and due reviews in the learning area
 * (R0-11 L-07, L-08, L-10; R0-26 LE-T04/T05/T07; E-342).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Locale;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Rest\RestContract;

defined( 'ABSPATH' ) || exit;

/**
 * `/<area>/kurs/<uid>/ueben` (overview) and `/<area>/kurs/<uid>/ueben/<mode>`
 * with the modes `smart`, `fehler` (last answer wrong, SmartTrainer mode
 * `wrong`) and `faellig` (due reviews). Uses the existing services only:
 * {@see SmartTrainer::next()} delivers a question with a signed token,
 * {@see AnswerService::answer()} evaluates it (idempotency key per form).
 *
 * Works without JavaScript (form, PRG, nonce): the feedback after an
 * answer is kept for the learner for a short time (transient) and shown
 * once on the next page; it names "correct/incorrect", the correct
 * options when the site shows them (E-147) and when the question comes
 * up again. Open questions: "Show model answer" (submit) and the
 * self-assessment as two radio buttons. Accessibility: one fieldset per
 * question named by heading, text and hint; feedback in a `status`
 * region, errors as `alert` (R0-15).
 */
final class TrainerView {

	public const NONCE = 'ccap_trainer';

	/** Transient prefix of the feedback after a form post. */
	private const FLASH = 'ccap_trainer_';

	/**
	 * Modes: path slug → SmartTrainer mode.
	 *
	 * @var array<string, string>
	 */
	public const MODES = array(
		'smart'   => 'smart',
		'fehler'  => 'wrong',
		'faellig' => 'due',
	);

	/**
	 * English aliases of the slugs.
	 *
	 * @var array<string, string>
	 */
	private const ALIASES = array(
		'wrong' => 'fehler',
		'due'   => 'faellig',
	);

	/**
	 * Canonical mode slug or '' when unknown.
	 *
	 * @param string $slug Slug from the path.
	 * @return string
	 */
	public static function mode( string $slug ): string {
		$slug = self::ALIASES[ $slug ] ?? $slug;
		return isset( self::MODES[ $slug ] ) ? $slug : '';
	}

	/**
	 * URL of the overview or a mode.
	 *
	 * @param int    $course_id Course.
	 * @param string $mode      Mode slug ('' = overview).
	 * @return string
	 */
	public static function url( int $course_id, string $mode = '' ): string {
		return LearningArea::url( 'kurs/' . LearningArea::course_uid( $course_id ) . '/ueben' . ( '' === $mode ? '' : '/' . $mode ) );
	}

	/**
	 * Overview: stages, entries to the three modes.
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return array{status: int, title: string, title_attr: string, body: string}
	 * @throws CourseException 404 for unknown courses.
	 */
	public static function overview( Actor $actor, int $course_id ): array {
		$room    = LearnerQueries::current()->course( $actor, $course_id );
		$summary = SmartTrainer::current()->summary( $actor, $course_id );
		$due     = SmartTrainer::current()->due( $actor, $course_id );
		$html    = self::back( $room );
		if ( 0 === (int) $summary['total'] ) {
			$html .= '<p>' . esc_html__( 'There are no practice questions in this course that you can use right now.', 'course-captain' ) . '</p>';
			return self::page( $room, $html );
		}
		$html  .= '<section aria-labelledby="ccap-stages-heading"><h2 id="ccap-stages-heading">' . esc_html__( 'Your learning stages', 'course-captain' ) . '</h2><ul class="ccap-stages">';
		$stages = array(
			/* translators: %s: number of questions */
			'new'      => __( 'Not practised yet: %s', 'course-captain' ),
			/* translators: %s: number of questions */
			'unsure'   => __( 'Unsure: %s', 'course-captain' ),
			/* translators: %s: number of questions */
			'learning' => __( 'Learning: %s', 'course-captain' ),
			/* translators: %s: number of questions */
			'secure'   => __( 'Secure: %s', 'course-captain' ),
			/* translators: %s: number of questions */
			'mastered' => __( 'Consolidated: %s', 'course-captain' ),
		);
		foreach ( $stages as $key => $label ) {
			$html .= '<li>' . esc_html( sprintf( $label, number_format_i18n( (int) $summary[ $key ] ) ) ) . '</li>';
		}
		$html .= '</ul></section>';
		$html .= '<section aria-labelledby="ccap-modes-heading"><h2 id="ccap-modes-heading">' . esc_html__( 'Choose how you want to practise', 'course-captain' ) . '</h2><ul class="ccap-cards">';
		$html .= '<li class="ccap-card"><h3><a href="' . esc_url( self::url( $course_id, 'smart' ) ) . '">' . esc_html__( 'Smart practice', 'course-captain' ) . '</a></h3><p>' . esc_html__( 'The trainer picks the question that helps you most right now: new questions, mistakes and reviews.', 'course-captain' ) . '</p></li>';
		if ( \CourseCaptain\Settings\Modules::course_feature( 'mistake_training', $course_id ) ) {
			$html .= '<li class="ccap-card"><h3><a href="' . esc_url( self::url( $course_id, 'fehler' ) ) . '">' . esc_html__( 'Practise mistakes', 'course-captain' ) . '</a></h3><p>' . esc_html__( 'Only questions you answered incorrectly last time.', 'course-captain' ) . '</p></li>';
		}
		$count = count( $due['ids'] );
		$html .= '<li class="ccap-card"><h3><a href="' . esc_url( self::url( $course_id, 'faellig' ) ) . '">' . esc_html__( 'Due reviews', 'course-captain' ) . '</a></h3><p>' . esc_html(
			0 === $count
				? __( 'No reviews are due today.', 'course-captain' )
				: sprintf(
					/* translators: %s: number of questions */
					_n( '%s question is due for review today.', '%s questions are due for review today.', $count, 'course-captain' ),
					number_format_i18n( $count )
				)
		) . '</p></li></ul></section>';
		return self::page( $room, $html );
	}

	/**
	 * Practice page of a mode: feedback of the last answer, the revealed
	 * model answer of an open question, or the next question.
	 *
	 * @param Actor  $actor     Learner.
	 * @param int    $course_id Course.
	 * @param string $mode      Mode slug.
	 * @param string $status    Status after a form post.
	 * @return array{status: int, title: string, title_attr: string, body: string}
	 * @throws CourseException 404 for unknown courses or modes.
	 */
	public static function render( Actor $actor, int $course_id, string $mode, string $status = '' ): array {
		$room = LearnerQueries::current()->course( $actor, $course_id );
		$mode = self::mode( $mode );
		if ( '' === $mode ) {
			throw new CourseException( 'ccap_not_found', 'Unknown mode.', 404 );
		}
		$lang  = Locale::content_attributes( CourseStructure::course_language( $course_id ) );
		$html  = '<p class="ccap-back"><a href="' . esc_url( self::url( $course_id ) ) . '">' . esc_html__( 'Back to the practice overview', 'course-captain' ) . '</a></p>';
		$flash = self::flash( $actor->user_id, $course_id, $mode );
		if ( 'answered' === $status && null !== $flash && isset( $flash['result'] ) ) {
			self::forget( $actor->user_id );
			return self::page( $room, $html . self::feedback( $flash, $lang ) . '<p><a class="ccap-button" href="' . esc_url( self::url( $course_id, $mode ) ) . '">' . esc_html__( 'Next question', 'course-captain' ) . '</a></p>', self::title( $mode ) );
		}
		if ( '' !== $status && 'answered' !== $status && null !== $flash && isset( $flash['model'] ) ) {
			return self::page( $room, $html . self::error( $status ) . self::form( $course_id, $mode, $flash['question'], (string) $flash['token'], $lang, (string) $flash['model'] ), self::title( $mode ) );
		}
		self::forget( $actor->user_id );
		$next = self::next( $actor, $course_id, $mode );
		if ( null === $next['question'] ) {
			return self::page( $room, $html . self::error( $status ) . '<p role="status">' . esc_html( self::empty_text( $mode, $next['next_due'] ) ) . '</p>', self::title( $mode ) );
		}
		return self::page( $room, $html . self::error( $status ) . self::form( $course_id, $mode, (array) $next['question']['question'], (string) $next['question']['token'], $lang ), self::title( $mode ) );
	}

	/**
	 * Form post of a practice page; returns the status for the redirect.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param string               $mode      Mode slug.
	 * @param array<string, mixed> $post      Unslashed POST data.
	 * @return string `answered`, `revealed` or an error code.
	 */
	public static function handle( Actor $actor, int $course_id, string $mode, array $post ): string {
		$mode = self::mode( $mode );
		if ( '' === $mode || false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::NONCE ) ) {
			return 'ccap_invalid_token';
		}
		$token = (string) ( $post['token'] ?? '' );
		try {
			$claims   = QuestionPresenter::current()->verify( $actor, $token );
			$revision = (array) QuestionRepository::current()->revision( (int) $claims['r'] );
			if ( (int) $claims['c'] !== $course_id ) {
				return 'ccap_invalid_token';
			}
			$type     = (string) ( $revision['question_type'] ?? '' );
			$question = array(
				'type' => $type,
				'text' => (string) ( $revision['question_text'] ?? '' ),
			);
			if ( 'reveal' === ( $post['ccap_do'] ?? '' ) ) {
				if ( QuestionTypes::OPEN_TEXT !== $type ) {
					return 'ccap_invalid_param';
				}
				self::remember(
					$actor->user_id,
					array(
						'course'   => $course_id,
						'mode'     => $mode,
						'token'    => $token,
						'question' => $question,
						'model'    => (string) ( $revision['answers'][0]['text'] ?? '' ),
					)
				);
				return 'revealed';
			}
			$answer = QuestionForm::normalize( $type, $post['answer'] ?? null );
			if ( QuestionTypes::ORDERING === $type && null === $answer ) {
				return 'ordering_incomplete';
			}
			if ( QuestionForm::is_empty( $answer ) ) {
				return 'empty_answer';
			}
			$key    = (string) ( $post['request_key'] ?? '' );
			$result = AnswerService::current()->answer( $actor, $token, $answer, $key, 'learn' );
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
		$correct = array();
		foreach ( (array) ( $result['correct_ids'] ?? array() ) as $id ) {
			foreach ( (array) ( $revision['answers'] ?? array() ) as $option ) {
				if ( is_array( $option ) && (string) ( $option['key'] ?? '' ) === (string) $id ) {
					$correct[] = (string) ( $option['text'] ?? '' );
				}
			}
		}
		self::remember(
			$actor->user_id,
			array(
				'course'   => $course_id,
				'mode'     => $mode,
				'question' => $question,
				'result'   => array(
					'correct'     => ! empty( $result['correct'] ),
					'self'        => QuestionTypes::OPEN_TEXT === $type,
					'correct_txt' => $correct,
					'status'      => (string) ( $result['progress']['status'] ?? '' ),
					'due_at'      => isset( $result['progress']['due_at'] ) ? (string) $result['progress']['due_at'] : null,
				),
			)
		);
		return 'answered';
	}

	/**
	 * Next question of a mode.
	 *
	 * @param Actor  $actor     Learner.
	 * @param int    $course_id Course.
	 * @param string $mode      Mode slug.
	 * @return array{question: array<string, mixed>|null, next_due: string|null}
	 * @throws CourseException Without learning access.
	 */
	private static function next( Actor $actor, int $course_id, string $mode ): array {
		$trainer = SmartTrainer::current();
		if ( 'faellig' === $mode ) {
			$due = $trainer->entry( $actor, $course_id, 'due' );
			return array(
				'question' => $due['question'],
				'next_due' => $due['next_due'],
			);
		}
		return array(
			'question' => $trainer->next( $actor, $course_id, self::MODES[ $mode ] ),
			'next_due' => null,
		);
	}

	/**
	 * Question form.
	 *
	 * @param int                  $course_id Course.
	 * @param string               $mode      Mode slug.
	 * @param array<string, mixed> $question  Delivered payload (or type/text of an open question).
	 * @param string               $token     Question token.
	 * @param string               $lang      Content language attributes.
	 * @param string|null          $model     Revealed model answer of an open question.
	 * @return string
	 */
	private static function form( int $course_id, string $mode, array $question, string $token, string $lang, ?string $model = null ): string {
		$type  = (string) ( $question['type'] ?? '' );
		$hint  = QuestionForm::hint( $type );
		$html  = '<form method="post" class="ccap-trainer-form" action="' . esc_url( self::url( $course_id, $mode ) ) . '">' . wp_nonce_field( self::NONCE, '_wpnonce', false, false );
		$html .= '<input type="hidden" name="token" value="' . esc_attr( $token ) . '"><input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
		$html .= '<section class="ccap-question" id="ccap-q"><fieldset aria-labelledby="ccap-q-legend ccap-q-text"><legend id="ccap-q-legend">' . esc_html__( 'Question', 'course-captain' ) . ( '' !== $hint ? ' <span class="ccap-hint">' . esc_html( $hint ) . '</span>' : '' ) . '</legend>';
		$html .= '<div class="ccap-qtext" id="ccap-q-text"' . $lang . '>' . QuestionForm::text( $question ) . '</div>';
		if ( QuestionTypes::OPEN_TEXT === $type ) {
			if ( null === $model ) {
				return $html . '<p><button type="submit" class="ccap-button" name="ccap_do" value="reveal">' . esc_html__( 'Show model answer', 'course-captain' ) . '</button></p></fieldset></section></form>';
			}
			$html .= '<div class="ccap-model-answer" id="ccap-q-model"><p><strong>' . esc_html__( 'Model answer:', 'course-captain' ) . '</strong></p><div' . $lang . '>' . wp_kses_post( $model ) . '</div></div>';
			$html .= '<fieldset class="ccap-self" aria-describedby="ccap-q-model"><legend>' . esc_html__( 'Did you know the answer?', 'course-captain' ) . '</legend><ul class="ccap-options">';
			$html .= '<li><input type="radio" id="ccap-q-known-1" name="answer[known]" value="1"> <label for="ccap-q-known-1">' . esc_html__( 'Yes, I knew it', 'course-captain' ) . '</label></li>';
			$html .= '<li><input type="radio" id="ccap-q-known-0" name="answer[known]" value="0"> <label for="ccap-q-known-0">' . esc_html__( 'No, I did not know it', 'course-captain' ) . '</label></li></ul></fieldset>';
			return $html . '</fieldset></section><p><button type="submit" class="ccap-button" name="ccap_do" value="answer">' . esc_html__( 'Save self-assessment', 'course-captain' ) . '</button></p></form>';
		}
		$fields = QuestionForm::fields( $question, 'answer', 'ccap-q', null, $lang );
		$html  .= null === $fields ? '<p class="ccap-hint">' . esc_html__( 'This question type cannot be answered here.', 'course-captain' ) . '</p>' : $fields;
		return $html . '</fieldset></section><p><button type="submit" class="ccap-button" name="ccap_do" value="answer">' . esc_html__( 'Check answer', 'course-captain' ) . '</button></p></form>';
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
		$html   = '<section class="ccap-feedback" aria-labelledby="ccap-feedback-heading"><h2 id="ccap-feedback-heading" role="status">';
		if ( ! empty( $result['self'] ) ) {
			$html .= esc_html( ! empty( $result['correct'] ) ? __( 'Saved: you knew the answer.', 'course-captain' ) : __( 'Saved: you did not know the answer yet.', 'course-captain' ) );
		} else {
			$html .= esc_html( ! empty( $result['correct'] ) ? __( 'Correct!', 'course-captain' ) : __( 'Not correct.', 'course-captain' ) );
		}
		$html .= '</h2><div class="ccap-qtext"' . $lang . '>' . QuestionForm::text( (array) $flash['question'] ) . '</div>';
		$right = array_filter( array_map( 'strval', (array) ( $result['correct_txt'] ?? array() ) ) );
		if ( array() !== $right ) {
			$html .= '<p>' . esc_html( _n( 'Correct answer:', 'Correct answers:', count( $right ), 'course-captain' ) ) . '</p><ul' . $lang . '>';
			foreach ( $right as $text ) {
				$html .= '<li>' . esc_html( $text ) . '</li>';
			}
			$html .= '</ul>';
		}
		$html .= '<p>' . esc_html( self::stage_text( (string) ( $result['status'] ?? '' ), $result['due_at'] ?? null ) ) . '</p>';
		return $html . '</section>';
	}

	/**
	 * What happens next with the question (LE-T04: name the repetition).
	 *
	 * @param string      $stage  Learning stage.
	 * @param string|null $due_at Next review (UTC) or null.
	 * @return string
	 */
	private static function stage_text( string $stage, ?string $due_at ): string {
		if ( in_array( $stage, array( 'secure', 'mastered' ), true ) && null !== $due_at && '' !== $due_at ) {
			return sprintf(
				/* translators: %s: date */
				__( 'You know this question well. It comes up again for review on %s.', 'course-captain' ),
				wp_date( (string) get_option( 'date_format' ), (int) strtotime( $due_at . ' UTC' ) )
			);
		}
		if ( 'unsure' === $stage ) {
			return __( 'This question comes up again soon so that you can practise it.', 'course-captain' );
		}
		return __( 'This question comes up again until you have answered it correctly several times.', 'course-captain' );
	}

	/**
	 * Empty state of a mode.
	 *
	 * @param string      $mode     Mode slug.
	 * @param string|null $next_due Next due date (UTC).
	 * @return string
	 */
	private static function empty_text( string $mode, ?string $next_due ): string {
		if ( 'fehler' === $mode ) {
			return __( 'There are no open mistakes. Well done!', 'course-captain' );
		}
		if ( 'faellig' === $mode ) {
			return null === $next_due
				? __( 'All done – no reviews are due.', 'course-captain' )
				: sprintf(
					/* translators: %s: date */
					__( 'All done – the next review is due on %s.', 'course-captain' ),
					wp_date( (string) get_option( 'date_format' ), (int) strtotime( $next_due . ' UTC' ) )
				);
		}
		return __( 'There are no practice questions in this course that you can use right now.', 'course-captain' );
	}

	/**
	 * Error notice after a form post.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function error( string $status ): string {
		if ( '' === $status || in_array( $status, array( 'answered', 'revealed' ), true ) ) {
			return '';
		}
		$texts = array(
			'empty_answer'        => __( 'Please answer the question first.', 'course-captain' ),
			'ordering_incomplete' => __( 'Please give every item its own position.', 'course-captain' ),
			'ccap_invalid_answer' => __( 'Please check your answer: it is not complete or not valid.', 'course-captain' ),
		);
		return '<p class="ccap-error" role="alert">' . esc_html( $texts[ $status ] ?? RestContract::user_message( $status ) ) . '</p>';
	}

	/**
	 * Page title of a mode.
	 *
	 * @param string $mode Mode slug.
	 * @return string
	 */
	private static function title( string $mode ): string {
		$titles = array(
			'smart'   => __( 'Smart practice', 'course-captain' ),
			'fehler'  => __( 'Practise mistakes', 'course-captain' ),
			'faellig' => __( 'Due reviews', 'course-captain' ),
		);
		return $titles[ $mode ] ?? __( 'Practice', 'course-captain' );
	}

	/**
	 * Back link to the course room.
	 *
	 * @param array<string, mixed> $room Course room.
	 * @return string
	 */
	private static function back( array $room ): string {
		return '<p class="ccap-back"><a href="' . esc_url( LearningArea::url( 'kurs/' . LearningArea::course_uid( (int) $room['course_id'] ) ) ) . '">' . esc_html(
			sprintf(
				/* translators: %s: course title */
				__( 'Back to the course: %s', 'course-captain' ),
				(string) $room['title']
			)
		) . '</a></p>';
	}

	/**
	 * Page parts.
	 *
	 * @param array<string, mixed> $room  Course room.
	 * @param string               $body  Body.
	 * @param string               $title Title ('' = practice overview).
	 * @return array{status: int, title: string, title_attr: string, body: string}
	 */
	private static function page( array $room, string $body, string $title = '' ): array {
		return array(
			'status'     => 200,
			'title'      => '' === $title ? sprintf(
				/* translators: %s: course title */
				__( 'Practice: %s', 'course-captain' ),
				(string) $room['title']
			) : $title,
			'title_attr' => '',
			'body'       => $body,
		);
	}

	/**
	 * Stored feedback of this course and mode.
	 *
	 * @param int    $user      Learner.
	 * @param int    $course_id Course.
	 * @param string $mode      Mode slug.
	 * @return array<string, mixed>|null
	 */
	private static function flash( int $user, int $course_id, string $mode ): ?array {
		$data = $user > 0 ? get_transient( self::FLASH . $user ) : false;
		return is_array( $data ) && (int) ( $data['course'] ?? 0 ) === $course_id && ( $data['mode'] ?? '' ) === $mode ? $data : null;
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
