<?php
/**
 * Learning-mode answers (R0-06 §4.3/§4.8, R0-08 §3.1, R0-05 GM-018).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learning;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\StorageException;

defined( 'ABSPATH' ) || exit;

/**
 * One transaction: `ccap_attempt` + `ccap_attempt_answer` +
 * `ccap_question_progress` (`FOR UPDATE`). The client idempotency key is
 * unique per user; a repeated request returns the stored result
 * (`repeated = true`) and counts once. Explanation texts are never part
 * of the Free result (E-20).
 */
final class AnswerService {

	/**
	 * Constructor.
	 *
	 * @param Database           $db        Database access.
	 * @param QuestionPresenter  $presenter Presenter (token + access).
	 * @param QuestionRepository $questions Questions.
	 */
	public function __construct( private Database $db, private QuestionPresenter $presenter, private QuestionRepository $questions ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), QuestionPresenter::current(), QuestionRepository::current() );
	}

	/**
	 * Answer a delivered question.
	 *
	 * @param Actor  $actor           Learner.
	 * @param string $token           Question token.
	 * @param mixed  $answer          Answer.
	 * @param string $idempotency_key Client UUID.
	 * @param string $context         learn|lesson|review.
	 * @return array{correct: bool, points_milli: int, repeated: bool, attempts: int, correct_ids?: list<string>, streak_counted?: bool|null, progress?: array<string, mixed>}
	 * @throws CourseException `ccap_invalid_answer` for invalid or empty input.
	 * @throws StorageException On non-duplicate storage errors.
	 */
	public function answer( Actor $actor, string $token, $answer, string $idempotency_key, string $context = 'learn' ): array {
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/', $idempotency_key ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid idempotency key.', 400 );
		}
		$claims = $this->presenter->verify( $actor, $token );
		$stored = $this->existing( $actor->user_id, $idempotency_key );
		if ( null !== $stored ) {
			return $this->result( $actor->user_id, $stored, true );
		}
		$evaluated = $this->presenter->evaluate( $actor, $token, $answer );
		$eval      = $evaluated['evaluation'];
		if ( ! $eval->valid || ! $eval->answered ) {
			throw new CourseException( 'ccap_invalid_answer', 'Invalid answer.', 422 );
		}
		$revision = (array) $this->questions->revision( $claims['r'] );
		if ( QuestionTypes::FLASHCARD === $revision['question_type'] ) {
			throw new CourseException( 'ccap_invalid_param', 'Flashcards use their own states.', 400 );
		}
		$correct  = $eval->correct();
		$progress = null;
		SmartTrainer::current()->rate_limit( $actor->user_id );
		try {
			$attempt = $this->db->transaction(
				function ( Database $db ) use ( $actor, $claims, $answer, $idempotency_key, $context, $correct, $revision, &$progress ): int {
					$quiet = $db->wpdb()->suppress_errors( true );
					try {
						$id = $db->insert(
							'ccap_attempt',
							array(
								'user_id'           => $actor->user_id,
								'question_id'       => $claims['q'],
								'revision_id'       => $claims['r'],
								'course_id'         => $claims['c'],
								'context'           => in_array( $context, array( 'learn', 'lesson', 'review' ), true ) ? $context : 'learn',
								'context_ref'       => $claims['a'] > 0 ? 'lesson:' . $claims['a'] : 'module:' . $claims['m'],
								'is_correct'        => null === $correct ? null : ( $correct ? 1 : 0 ),
								'self_assessment'   => QuestionTypes::OPEN_TEXT === $revision['question_type'] && is_array( $answer ) ? ( empty( $answer['known'] ) ? 'unknown' : 'known' ) : null,
								'idempotency_key'   => $idempotency_key,
								'algorithm_version' => SmartTrainer::ALGORITHM_VERSION,
								'created_at'        => SmartTrainer::sql( SmartTrainer::now() ),
							)
						);
					} finally {
						$db->wpdb()->suppress_errors( $quiet );
					}
					foreach ( self::answer_rows( $answer ) as $i => $row ) {
						$db->insert(
							'ccap_attempt_answer',
							$row + array(
								'attempt_id' => $id,
								'position'   => $i,
							)
						);
					}
					if ( null !== $correct ) {
						$progress = SmartTrainer::apply( $db, $actor->user_id, $claims['q'], $correct );
					}
					return $id;
				}
			);
		} catch ( StorageException $e ) {
			if ( ! $e->is_duplicate() ) {
				throw $e;
			}
			// Parallel duplicate: the other request won; return its result.
			return $this->result( $actor->user_id, (array) $this->existing( $actor->user_id, $idempotency_key ), true );
		}
		$row    = (array) $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_attempt' ) . '` WHERE id = %d', $attempt ) );
		$result = $this->result( $actor->user_id, $row, false, $eval->points_milli );
		if ( null !== $progress ) {
			$result['streak_counted'] = $progress['streak_counted'];
			$result['progress']       = array(
				'status'   => $progress['status'],
				'streak'   => $progress['streak'],
				'attempts' => $progress['attempts'],
				'due_at'   => $progress['due_at'],
			);
			if ( ( 'mastered' === $progress['status'] ) !== ( 'mastered' === $progress['previous'] ) && $claims['c'] > 0 ) {
				\CourseCaptain\Progress\CourseProgress::current()->recalculate( $actor->user_id, $claims['c'] );
			}
		}
		return $result;
	}

	/**
	 * Result payload (GM-018): correctness, solution keys per learning
	 * feedback setting, never explanation texts in Free.
	 *
	 * @param int                  $user     Learner.
	 * @param array<string, mixed> $attempt  Attempt row.
	 * @param bool                 $repeated Repeated request.
	 * @param int|null             $points   Points of this evaluation.
	 * @return array{correct: bool, points_milli: int, repeated: bool, attempts: int, correct_ids?: list<string>}
	 */
	private function result( int $user, array $attempt, bool $repeated, ?int $points = null ): array {
		$revision = (array) $this->questions->revision( (int) $attempt['revision_id'] );
		$attempts = (int) $this->db->scalar( $this->db->prepare( 'SELECT attempts FROM `' . $this->db->table( 'ccap_question_progress' ) . '` WHERE user_id = %d AND question_id = %d', $user, (int) $attempt['question_id'] ) );
		$correct  = '1' === (string) $attempt['is_correct'];
		$result   = array(
			'correct'      => $correct,
			'points_milli' => $points ?? ( $correct ? QuestionTypes::DEFAULT_WEIGHT : 0 ),
			'repeated'     => $repeated,
			'attempts'     => $attempts,
		);
		if ( in_array( $revision['question_type'], array( QuestionTypes::SINGLE, QuestionTypes::MULTI, QuestionTypes::TRUE_FALSE ), true ) && self::show_correct_answers() ) {
			$ids = array();
			foreach ( (array) $revision['answers'] as $a ) {
				if ( is_array( $a ) && ! empty( $a['correct'] ) ) {
					$ids[] = (string) $a['key'];
				}
			}
			$result['correct_ids'] = $ids;
		}
		return $result;
	}

	/**
	 * Learning feedback setting `show_correct_answers` (default true, R0-08 §4.5).
	 *
	 * @return bool
	 */
	private static function show_correct_answers(): bool {
		$learning = get_option( 'course_captain_settings_learning', array() );
		$value    = is_array( $learning ) ? ( $learning['values']['feedback']['show_correct_answers'] ?? true ) : true;
		return false !== $value;
	}

	/**
	 * Stored attempt for an idempotency key.
	 *
	 * @param int    $user Learner.
	 * @param string $key  Key.
	 * @return array<string, mixed>|null
	 */
	private function existing( int $user, string $key ): ?array {
		return $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_attempt' ) . '` WHERE user_id = %d AND idempotency_key = %s', $user, $key ) );
	}

	/**
	 * Rows of `ccap_attempt_answer` for an answer.
	 *
	 * @param mixed $answer Answer.
	 * @return list<array<string, mixed>>
	 */
	private static function answer_rows( $answer ): array {
		if ( is_array( $answer ) && array_is_list( $answer ) ) {
			return array_map( static fn( $key ): array => array( 'answer_key' => substr( (string) $key, 0, 16 ) ), $answer );
		}
		return array( array( 'free_text' => (string) wp_json_encode( $answer ) ) );
	}
}
