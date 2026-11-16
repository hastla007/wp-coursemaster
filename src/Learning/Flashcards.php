<?php
/**
 * Flashcard states (R0-08 §2.4, E-21; R0-05 GM-062, GM-063).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learning;

use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\StorageException;

defined( 'ABSPATH' ) || exit;

/**
 * One card type with two modes (E-21). The card state lives in
 * `ccap_question_progress` with its own status range `open` / `practice` /
 * `known` (never the smart trainer stages):
 *
 * - `self_assessment`: the learner sets any state directly;
 * - `streak`: `known_streak` (default 3) "known" in a row make the card
 *   `known`, "unknown" resets the streak; once known, it stays known.
 *
 * Cards are addressed through the signed inline token of the flashcard
 * lesson, so locked cards (partial access, drip) cannot be set. A client
 * idempotency key protects against double clicks; a stale
 * `expected_streak` is ignored (`applied = false`).
 */
final class Flashcards {

	public const ALGORITHM_VERSION = 1;
	public const STATES            = array( 'open', 'practice', 'known' );

	/**
	 * Constructor.
	 *
	 * @param Database           $db        Database access.
	 * @param QuestionPresenter  $presenter Presenter.
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
	 * Mode A: set a state.
	 *
	 * @param Actor  $actor           Learner.
	 * @param string $token           Inline card token.
	 * @param string $state           open|practice|known.
	 * @param string $idempotency_key Client UUID.
	 * @return array{state: string, streak: int, applied: bool}
	 * @throws CourseException For invalid input or wrong mode.
	 */
	public function set_state( Actor $actor, string $token, string $state, string $idempotency_key ): array {
		if ( ! in_array( $state, self::STATES, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown card state.', 400 );
		}
		$card = $this->card( $actor, $token, 'self_assessment' );
		return $this->write( $actor, $card, $idempotency_key, 'known' === $state ? 'known' : 'unknown', static fn( array $row ): array => array( $state, (int) $row['streak'] ) );
	}

	/**
	 * Mode B: self-assessment "known" / "unknown".
	 *
	 * @param Actor    $actor           Learner.
	 * @param string   $token           Inline card token.
	 * @param bool     $known           Known.
	 * @param string   $idempotency_key Client UUID.
	 * @param int|null $expected_streak Streak the client saw (stale → ignored).
	 * @return array{state: string, streak: int, applied: bool}
	 * @throws CourseException For invalid input or wrong mode.
	 */
	public function assess( Actor $actor, string $token, bool $known, string $idempotency_key, ?int $expected_streak = null ): array {
		$card   = $this->card( $actor, $token, 'streak' );
		$needed = max( 1, (int) ( $card['settings']['known_streak'] ?? 3 ) );
		return $this->write(
			$actor,
			$card,
			$idempotency_key,
			$known ? 'known' : 'unknown',
			static function ( array $row ) use ( $known, $needed, $expected_streak ): ?array {
				$streak = (int) $row['streak'];
				if ( null !== $expected_streak && $expected_streak !== $streak ) {
					return null;
				}
				if ( 'known' === $row['learn_status'] ) {
					return array( 'known', $known ? $streak + 1 : $streak );
				}
				$streak = $known ? $streak + 1 : 0;
				return array( $streak >= $needed ? 'known' : 'practice', $streak );
			}
		);
	}

	/**
	 * Card counts of a flashcard lesson for a learner.
	 *
	 * @param int $user_id   Learner.
	 * @param int $lesson_id Flashcard lesson.
	 * @return array{total: int, known: int}
	 */
	public function counts( int $user_id, int $lesson_id ): array {
		$cards = $this->presenter->inline_questions( $lesson_id );
		if ( array() === $cards ) {
			return array(
				'total' => 0,
				'known' => 0,
			);
		}
		$ids   = implode( ',', array_map( 'intval', $cards ) );
		$known = (int) $this->db->scalar( $this->db->prepare( 'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_question_progress' ) . "` WHERE user_id = %d AND learn_status = 'known' AND question_id IN ({$ids})", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
		return array(
			'total' => count( $cards ),
			'known' => $known,
		);
	}

	/**
	 * Verified card of a flashcard lesson in the expected mode.
	 *
	 * @param Actor  $actor Learner.
	 * @param string $token Token.
	 * @param string $mode  Expected mode.
	 * @return array{claims: array<string, mixed>, settings: array<string, mixed>}
	 * @throws CourseException For tokens that are not flashcards of a flashcard lesson.
	 */
	private function card( Actor $actor, string $token, string $mode ): array {
		$claims = $this->presenter->verify( $actor, $token );
		$lesson = (int) $claims['a'];
		$def    = $lesson > 0 ? ActivityService::definition( $lesson ) : array(
			'type'     => '',
			'settings' => array(),
		);
		$rev    = (array) $this->questions->revision( (int) $claims['r'] );
		if ( 'flashcard' !== $def['type'] || QuestionTypes::FLASHCARD !== ( $rev['question_type'] ?? '' ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Not a flashcard.', 400 );
		}
		if ( ( $def['settings']['mode'] ?? 'self_assessment' ) !== $mode ) {
			throw new CourseException( 'ccap_invalid_param', 'Wrong flashcard mode.', 400 );
		}
		return array(
			'claims'   => $claims,
			'settings' => $def['settings'],
		);
	}

	/**
	 * Idempotent locked write.
	 *
	 * @param Actor                $actor  Learner.
	 * @param array<string, mixed> $card   Card.
	 * @param string               $key    Idempotency key.
	 * @param string               $answer known|unknown.
	 * @param callable             $next   fn(progress row): array{0: string, 1: int}|null.
	 * @return array{state: string, streak: int, applied: bool}
	 * @throws CourseException For invalid keys.
	 * @throws StorageException On non-duplicate storage errors.
	 */
	private function write( Actor $actor, array $card, string $key, string $answer, callable $next ): array {
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/', $key ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid idempotency key.', 400 );
		}
		$claims   = $card['claims'];
		$question = (int) $claims['q'];
		$table    = $this->db->table( 'ccap_question_progress' );
		try {
			$out = $this->db->transaction(
				function ( Database $db ) use ( $actor, $claims, $question, $table, $key, $answer, $next ): array {
					$db->execute( $db->prepare( "INSERT IGNORE INTO `{$table}` (user_id, question_id, algorithm_version, learn_status) VALUES (%d, %d, %d, 'open')", $actor->user_id, $question, self::ALGORITHM_VERSION ) );
					$row = (array) $db->row( $db->prepare( "SELECT * FROM `{$table}` WHERE user_id = %d AND question_id = %d FOR UPDATE", $actor->user_id, $question ) );
					if ( null !== $this->attempt( $actor->user_id, $key ) ) {
						return self::state( $row, false );
					}
					$quiet = $db->wpdb()->suppress_errors( true );
					try {
						$db->insert(
							'ccap_attempt',
							array(
								'user_id'           => $actor->user_id,
								'question_id'       => $question,
								'revision_id'       => (int) $claims['r'],
								'course_id'         => (int) $claims['c'],
								'context'           => 'flashcard',
								'context_ref'       => 'lesson:' . (int) $claims['a'],
								'self_assessment'   => $answer,
								'idempotency_key'   => $key,
								'algorithm_version' => self::ALGORITHM_VERSION,
							),
							array( 'created_at' )
						);
					} finally {
						$db->wpdb()->suppress_errors( $quiet );
					}
					$result = $next( $row );
					if ( null === $result ) {
						return self::state( $row, false );
					}
					$now = $db->now();
					$db->execute(
						$db->prepare(
							"UPDATE `{$table}` SET learn_status = %s, streak = %d, attempts = attempts + 1, last_answered_at = %s, first_answered_at = COALESCE(first_answered_at, %s), row_version = row_version + 1 WHERE id = %d",
							$result[0],
							$result[1],
							$now,
							$now,
							(int) $row['id']
						)
					);
					return array(
						'state'   => $result[0],
						'streak'  => $result[1],
						'applied' => true,
					);
				}
			);
		} catch ( StorageException $e ) {
			if ( ! $e->is_duplicate() ) {
				throw $e;
			}
			$out = self::state( (array) $this->db->row( $this->db->prepare( "SELECT * FROM `{$table}` WHERE user_id = %d AND question_id = %d", $actor->user_id, $question ) ), false );
		}
		if ( $out['applied'] ) {
			ActivityProgress::current()->interact( $actor, (int) $claims['c'], (int) $claims['a'], 'cards' );
		}
		return $out;
	}

	/**
	 * Stored attempt for a key.
	 *
	 * @param int    $user Learner.
	 * @param string $key  Key.
	 * @return array<string, mixed>|null
	 */
	private function attempt( int $user, string $key ): ?array {
		return $this->db->row( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_attempt' ) . '` WHERE user_id = %d AND idempotency_key = %s', $user, $key ) );
	}

	/**
	 * State payload from a row.
	 *
	 * @param array<string, mixed> $row     Progress row.
	 * @param bool                 $applied Applied.
	 * @return array{state: string, streak: int, applied: bool}
	 */
	private static function state( array $row, bool $applied ): array {
		return array(
			'state'   => (string) ( $row['learn_status'] ?? 'open' ),
			'streak'  => (int) ( $row['streak'] ?? 0 ),
			'applied' => $applied,
		);
	}
}
