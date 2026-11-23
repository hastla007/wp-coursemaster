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
 * unique per user and recorded in `ccap_request_idempotency` (scope
 * `answer`, R0-19 §2.1): a repeated request returns the stored result
 * (`repeated = true`) and counts once; the same key with another answer
 * is a conflict (409); a request still in progress answers 409 until its
 * entry is orphaned (> 300 s). Explanation texts are never part
 * of the Free result (E-20).
 */
final class AnswerService {

	/** Scope of learning answers in `ccap_request_idempotency` (R0-19 §2.1). */
	public const IDEMPOTENCY_SCOPE = 'answer';

	/** An entry "in progress" older than this is orphaned and taken over (R0-19 §2.1, GM-061). */
	public const ORPHANED_AFTER = 300;

	/** Retention of idempotency entries in days (R0-19 §2.1). */
	public const IDEMPOTENCY_DAYS = 7;

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
	 * @throws \Throwable StorageException on non-duplicate storage errors and any other error
	 *                    while storing (the key is released and the error rethrown).
	 */
	public function answer( Actor $actor, string $token, $answer, string $idempotency_key, string $context = 'learn' ): array {
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/', $idempotency_key ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid idempotency key.', 400 );
		}
		$claims = $this->presenter->verify( $actor, $token );
		$hash   = hash( 'sha256', $token . "\n" . wp_json_encode( $answer ) );
		if ( ! $this->claim( $actor->user_id, $idempotency_key, $hash ) ) {
			$stored = $this->existing( $actor->user_id, $idempotency_key );
			if ( null !== $stored ) {
				return $this->result( $actor->user_id, $stored, true );
			}
		}
		$progress = null;
		$points   = null;
		try {
			$stored = $this->existing( $actor->user_id, $idempotency_key );
			if ( null !== $stored ) {
				// Answer stored before the key was recorded (older rows, crash after commit).
				$this->complete( $actor->user_id, $idempotency_key, (int) $stored['id'] );
				return $this->result( $actor->user_id, $stored, true );
			}
			$attempt = $this->store( $actor, $token, $answer, $idempotency_key, $context, $claims, $progress, $points );
		} catch ( \Throwable $e ) {
			$this->release( $actor->user_id, $idempotency_key );
			throw $e;
		}
		if ( null === $attempt ) {
			// Parallel duplicate: the other request won; return its result.
			$stored = (array) $this->existing( $actor->user_id, $idempotency_key );
			$this->complete( $actor->user_id, $idempotency_key, (int) ( $stored['id'] ?? 0 ) );
			return $this->result( $actor->user_id, $stored, true );
		}
		$this->complete( $actor->user_id, $idempotency_key, $attempt );
		$row    = (array) $this->db->row( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_attempt' ) . '` WHERE id = %d', $attempt ) );
		$result = $this->result( $actor->user_id, $row, false, $points );
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
	 * Evaluate and store one answer in one transaction.
	 *
	 * @param Actor                     $actor           Learner.
	 * @param string                    $token           Question token.
	 * @param mixed                     $answer          Answer.
	 * @param string                    $idempotency_key Client UUID.
	 * @param string                    $context         learn|lesson|review.
	 * @param array<string, mixed>      $claims          Verified token claims.
	 * @param array<string, mixed>|null $progress   Out: question progress.
	 * @param int|null                  $points          Out: points of this evaluation.
	 * @return int|null Attempt ID; null when a parallel request stored the same key.
	 * @throws CourseException `ccap_invalid_answer`, `ccap_invalid_param`.
	 * @throws StorageException On non-duplicate storage errors.
	 */
	private function store( Actor $actor, string $token, $answer, string $idempotency_key, string $context, array $claims, ?array &$progress, ?int &$points ): ?int {
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
		SmartTrainer::ensure_row( $this->db, $actor->user_id, (int) $claims['q'] );
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
			return null;
		}
		$points = $eval->points_milli;
		return $attempt;
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
	 * Learning feedback: `show_correct_answers` (default true, R0-08 §4.5),
	 * only effective while `visibility` is not `never` (R0-27 SET-064, B-5).
	 *
	 * @return bool
	 */
	private static function show_correct_answers(): bool {
		$feedback = \CourseCaptain\Settings\Settings::get( 'learning.feedback' );
		if ( ! is_array( $feedback ) ) {
			return true;
		}
		return 'never' !== ( $feedback['visibility'] ?? 'always' ) && false !== ( $feedback['show_correct_answers'] ?? true );
	}

	/**
	 * Claim an idempotency key (R0-19 §2.1, GM-061/GM-150): new key → own it;
	 * same key, other request → `409 ccap_idempotency_conflict`; same key
	 * still in progress → `409 ccap_request_in_progress` (`retry_after` 2);
	 * an entry in progress for more than 300 s is orphaned and taken over.
	 *
	 * @param int    $user Learner.
	 * @param string $key  Client key.
	 * @param string $hash Request hash (token + answer).
	 * @return bool True = this request processes the key; false = already completed.
	 * @throws CourseException 409 codes above.
	 */
	private function claim( int $user, string $key, string $hash ): bool {
		$table    = $this->db->table( 'ccap_request_idempotency' );
		$now      = SmartTrainer::now();
		$inserted = $this->db->execute(
			$this->db->prepare(
				"INSERT IGNORE INTO `{$table}` (user_id, scope, idem_key, request_hash, status_code, created_at, expires_at) VALUES (%d, %s, %s, %s, 0, %s, %s)",
				$user,
				self::IDEMPOTENCY_SCOPE,
				$key,
				$hash,
				SmartTrainer::sql( $now ),
				SmartTrainer::sql( $now + self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS )
			)
		);
		if ( 1 === $inserted ) {
			return true;
		}
		$row = (array) $this->db->row( $this->db->prepare( "SELECT id, request_hash, status_code, created_at FROM `{$table}` WHERE user_id = %d AND scope = %s AND idem_key = %s", $user, self::IDEMPOTENCY_SCOPE, $key ) );
		if ( ! hash_equals( (string) ( $row['request_hash'] ?? '' ), $hash ) ) {
			throw new CourseException( 'ccap_idempotency_conflict', 'The request key was used for another request.', 409 );
		}
		if ( 0 !== (int) ( $row['status_code'] ?? 0 ) ) {
			return false;
		}
		$created = (int) strtotime( (string) $row['created_at'] . ' UTC' );
		if ( $now - $created > self::ORPHANED_AFTER ) {
			$taken = $this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET created_at = %s WHERE id = %d AND status_code = 0 AND created_at = %s", SmartTrainer::sql( $now ), (int) $row['id'], (string) $row['created_at'] ) );
			if ( 1 === $taken ) {
				return true;
			}
		}
		throw new CourseException( 'ccap_request_in_progress', 'The request is still in progress.', 409, array( 'retry_after' => 2 ) );
	}

	/**
	 * Mark a key as completed with its attempt.
	 *
	 * @param int    $user    Learner.
	 * @param string $key     Client key.
	 * @param int    $attempt Attempt ID.
	 * @return void
	 */
	private function complete( int $user, string $key, int $attempt ): void {
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_request_idempotency' ) . '` SET status_code = 200, resource = %s WHERE user_id = %d AND scope = %s AND idem_key = %s', 'attempt:' . $attempt, $user, self::IDEMPOTENCY_SCOPE, $key ) );
	}

	/**
	 * Release a key after a rejected request (the client may retry with it).
	 *
	 * @param int    $user Learner.
	 * @param string $key  Client key.
	 * @return void
	 */
	private function release( int $user, string $key ): void {
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_request_idempotency' ) . '` WHERE user_id = %d AND scope = %s AND idem_key = %s AND status_code = 0', $user, self::IDEMPOTENCY_SCOPE, $key ) );
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
