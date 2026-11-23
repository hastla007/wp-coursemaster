<?php
/**
 * Delivery of questions in learning mode with a signed token (R0-08 §3.1/§3.4, GM-017/024/025).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Question;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * The payload never contains solution data. The token binds user,
 * question, revision, course, module, materialized order and expiry
 * (TTL 2 h) with an HMAC; every answer or reveal re-checks the token and
 * the learning access *now* (access can end mid-session).
 */
final class QuestionPresenter {

	public const TOKEN_TTL = 7200;

	/**
	 * Constructor.
	 *
	 * @param Database           $db        Database access.
	 * @param QuestionRepository $questions Question bank.
	 */
	public function __construct( private Database $db, private QuestionRepository $questions ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), QuestionRepository::current() );
	}

	/**
	 * Deliver a question of a module pool to a learner.
	 *
	 * @param Actor           $actor       Learner.
	 * @param int             $course_id   Course.
	 * @param int             $module_id   Module.
	 * @param int             $question_id Question.
	 * @param Randomizer|null $randomizer  Injected for tests (INV-A-028).
	 * @return array{question: array<string, mixed>, token: string}
	 * @throws CourseException When not allowed or not deliverable.
	 */
	public function deliver( Actor $actor, int $course_id, int $module_id, int $question_id, ?Randomizer $randomizer = null ): array {
		ContentGate::current()->module( $actor, $course_id, $module_id )->assert();
		$revision = $this->deliverable_revision( $module_id, $question_id );
		ContentGate::current()->question( $actor, $course_id, $module_id, $question_id )->assert();
		$this->assert_not_in_running_exam( $actor->user_id, $question_id );
		$uid      = (string) $this->db->scalar( $this->db->prepare( 'SELECT uid FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $question_id ) );
		$delivery = QuestionTypes::deliver( $revision, $randomizer ?? Randomizer::fresh(), $uid );
		$claims   = array(
			'u'   => $actor->user_id,
			'q'   => $question_id,
			'r'   => (int) $revision['id'],
			'c'   => $course_id,
			'm'   => $module_id,
			'a'   => 0,
			'o'   => $delivery['order'],
			'exp' => $this->db->time() + self::TOKEN_TTL,
		);
		$payload  = $delivery['payload'] + array( 'uid' => $uid );
		return array(
			'question' => $payload,
			'token'    => self::sign( $claims ),
		);
	}

	/**
	 * Deliver an inline question of a lesson (lesson quiz, GM-007).
	 *
	 * @param Actor           $actor       Learner.
	 * @param int             $course_id   Course.
	 * @param int             $lesson_id   Lesson.
	 * @param int             $question_id Question.
	 * @param Randomizer|null $randomizer  Injected for tests.
	 * @return array{question: array<string, mixed>, token: string}
	 * @throws CourseException When not allowed or not deliverable.
	 */
	public function deliver_inline( Actor $actor, int $course_id, int $lesson_id, int $question_id, ?Randomizer $randomizer = null ): array {
		$module_id = (int) wp_get_post_parent_id( $lesson_id );
		ContentGate::current()->lesson( $actor, $course_id, $lesson_id )->assert();
		$revision = $this->inline_revision( $lesson_id, $question_id );
		$this->assert_card( $actor, $course_id, $lesson_id, $question_id, $revision );
		$this->assert_not_in_running_exam( $actor->user_id, $question_id );
		$uid      = (string) $this->db->scalar( $this->db->prepare( 'SELECT uid FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $question_id ) );
		$delivery = QuestionTypes::deliver( $revision, $randomizer ?? Randomizer::fresh(), $uid );
		$claims   = array(
			'u'   => $actor->user_id,
			'q'   => $question_id,
			'r'   => (int) $revision['id'],
			'c'   => $course_id,
			'm'   => $module_id,
			'a'   => $lesson_id,
			'o'   => $delivery['order'],
			'exp' => $this->db->time() + self::TOKEN_TTL,
		);
		return array(
			'question' => $delivery['payload'] + array( 'uid' => $uid ),
			'token'    => self::sign( $claims ),
		);
	}

	/**
	 * Partial access for flashcards (GM-062, AR-06): only the first *n*
	 * cards of a deck with the question limit (E-332); a locked card is
	 * never delivered, so its back side stays hidden.
	 *
	 * @param Actor                $actor     Learner.
	 * @param int                  $course_id Course.
	 * @param int                  $lesson_id Deck lesson.
	 * @param int                  $question  Card.
	 * @param array<string, mixed> $revision  Current revision.
	 * @return void
	 * @throws CourseException `ccap_content_locked` (trial).
	 */
	private function assert_card( Actor $actor, int $course_id, int $lesson_id, int $question, array $revision ): void {
		if ( QuestionTypes::FLASHCARD !== ( $revision['question_type'] ?? '' ) ) {
			return;
		}
		ContentGate::current()->card( $actor, $course_id, $lesson_id, $question, $this->inline_questions( $lesson_id ) )->assert();
	}

	/**
	 * Inline questions of a lesson in order (ccap_activity_question).
	 *
	 * @param int $lesson_id Lesson.
	 * @return list<int>
	 */
	public function inline_questions( int $lesson_id ): array {
		$aq   = $this->db->table( 'ccap_activity_question' );
		$q    = $this->db->table( 'ccap_question' );
		$rows = $this->db->rows( $this->db->prepare( "SELECT aq.question_id FROM `{$aq}` aq JOIN `{$q}` q ON q.id = aq.question_id WHERE aq.activity_id = %d AND q.status = 'published' ORDER BY aq.sort_order, aq.question_id", $lesson_id ) );
		return array_map( static fn( array $row ): int => (int) $row['question_id'], $rows );
	}

	/**
	 * Verify a token for the acting learner and re-check access now.
	 *
	 * @param Actor  $actor Learner.
	 * @param string $token Token.
	 * @return array{u: int, q: int, r: int, c: int, m: int, a: int, o: list<string>, exp: int}
	 * @throws CourseException `ccap_invalid_token` (422), access errors, or 404 when no longer deliverable.
	 */
	public function verify( Actor $actor, string $token ): array {
		$claims = self::unsign( $token );
		if ( null === $claims || $claims['u'] !== $actor->user_id || $actor->user_id <= 0 ) {
			throw new CourseException( 'ccap_invalid_token', 'Invalid question token.', 422 );
		}
		if ( $claims['exp'] < $this->db->time() ) {
			throw new CourseException( 'ccap_content_locked', 'expired', 403 );
		}
		$gate = ContentGate::current();
		if ( $claims['a'] > 0 ) {
			$gate->lesson( $actor, $claims['c'], $claims['a'] )->assert();
			$this->assert_card( $actor, $claims['c'], $claims['a'], $claims['q'], $this->inline_revision( $claims['a'], $claims['q'] ) );
		} else {
			$gate->module( $actor, $claims['c'], $claims['m'] )->assert();
			$this->deliverable_revision( $claims['m'], $claims['q'] );
			$gate->question( $actor, $claims['c'], $claims['m'], $claims['q'] )->assert();
		}
		$this->assert_not_in_running_exam( $actor->user_id, $claims['q'] );
		return $claims;
	}

	/**
	 * Evaluate an answer against the revision and order bound in the token.
	 *
	 * @param Actor  $actor  Learner.
	 * @param string $token  Token.
	 * @param mixed  $answer Answer.
	 * @return array{evaluation: Evaluation, revision_id: int, question_id: int}
	 */
	public function evaluate( Actor $actor, string $token, $answer ): array {
		$claims   = $this->verify( $actor, $token );
		$revision = (array) $this->questions->revision( $claims['r'] );
		return array(
			'evaluation'  => QuestionTypes::evaluate( $revision, $answer, $claims['o'] ),
			'revision_id' => $claims['r'],
			'question_id' => $claims['q'],
		);
	}

	/**
	 * Reveal the model answer (open text) or back side (flashcard).
	 *
	 * @param Actor  $actor Learner.
	 * @param string $token Token.
	 * @return string
	 * @throws CourseException When the type has nothing to reveal.
	 */
	public function reveal( Actor $actor, string $token ): string {
		$claims   = $this->verify( $actor, $token );
		$revision = (array) $this->questions->revision( $claims['r'] );
		if ( QuestionTypes::OPEN_TEXT === $revision['question_type'] ) {
			return (string) ( $revision['answers'][0]['text'] ?? '' );
		}
		if ( QuestionTypes::FLASHCARD === $revision['question_type'] ) {
			return (string) ( $revision['settings']['back'] ?? '' );
		}
		throw new CourseException( 'ccap_invalid_param', 'Nothing to reveal.', 400 );
	}

	/**
	 * Whether explanation texts may be delivered: only with Pro (E-20, E-147).
	 * The Pro add-on answers this filter; Free always gets `false`.
	 *
	 * @return bool
	 */
	public static function explanations_available(): bool {
		return true === apply_filters( 'course_captain_explanations_available', false );
	}

	/**
	 * Published current revision of a question in the module pool.
	 *
	 * @param int $module_id   Module.
	 * @param int $question_id Question.
	 * @return array<string, mixed>
	 * @throws CourseException 404 when not deliverable.
	 */
	private function deliverable_revision( int $module_id, int $question_id ): array {
		if ( ! in_array( $question_id, $this->questions->pool( $module_id ), true ) ) {
			throw new CourseException( 'ccap_not_found', 'Question not deliverable.', 404 );
		}
		$current  = (int) $this->db->scalar( $this->db->prepare( 'SELECT current_revision_id FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $question_id ) );
		$revision = $this->questions->revision( $current );
		if ( null === $revision ) {
			throw new CourseException( 'ccap_not_found', 'Question not deliverable.', 404 );
		}
		return $revision;
	}

	/**
	 * Published current revision of an inline question of a lesson.
	 *
	 * @param int $lesson_id   Lesson.
	 * @param int $question_id Question.
	 * @return array<string, mixed>
	 * @throws CourseException 404 when not deliverable.
	 */
	private function inline_revision( int $lesson_id, int $question_id ): array {
		if ( ! in_array( $question_id, $this->inline_questions( $lesson_id ), true ) ) {
			throw new CourseException( 'ccap_not_found', 'Question not deliverable.', 404 );
		}
		$current  = (int) $this->db->scalar( $this->db->prepare( 'SELECT current_revision_id FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $question_id ) );
		$revision = $this->questions->revision( $current );
		if ( null === $revision || 'published' !== $revision['editorial_status'] ) {
			throw new CourseException( 'ccap_not_found', 'Question not deliverable.', 404 );
		}
		return $revision;
	}

	/**
	 * SEC-016 / R0-08 §1.4 Nr. 5: questions of the learner's running exam are
	 * locked in learning mode (no looking up answers during the exam); without
	 * a time limit at most 24 h after the start (GM-044).
	 *
	 * @param int $user_id     Learner.
	 * @param int $question_id Question.
	 * @return void
	 * @throws CourseException `ccap_content_locked` with reason `exam_running`.
	 */
	private function assert_not_in_running_exam( int $user_id, int $question_id ): void {
		$sessions  = $this->db->table( 'ccap_exam_session' );
		$questions = $this->db->table( 'ccap_exam_session_question' );
		$running   = $this->db->scalar(
			$this->db->prepare(
				"SELECT 1 FROM `{$questions}` sq JOIN `{$sessions}` s ON s.id = sq.session_id WHERE s.user_id = %d AND s.status = 'in_progress' AND sq.question_id = %d AND (s.deadline_at IS NOT NULL OR s.started_at > %s) LIMIT 1",
				$user_id,
				$question_id,
				$this->db->now( -\CourseCaptain\Exam\ExamSessions::UNLIMITED_LOCK )
			)
		);
		if ( null !== $running ) {
			throw new CourseException( 'ccap_content_locked', 'exam_running', 403 );
		}
	}

	/**
	 * HMAC key of the site.
	 *
	 * @return string
	 */
	private static function key(): string {
		return hash_hmac( 'sha256', 'ccap-question-token', wp_salt( 'auth' ) );
	}

	/**
	 * Sign claims.
	 *
	 * @param array<string, mixed> $claims Claims.
	 * @return string
	 */
	private static function sign( array $claims ): string {
		$body = rtrim( strtr( base64_encode( (string) wp_json_encode( $claims ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe token encoding.
		return $body . '.' . hash_hmac( 'sha256', $body, self::key() );
	}

	/**
	 * Check signature and shape.
	 *
	 * @param string $token Token.
	 * @return array{u: int, q: int, r: int, c: int, m: int, a: int, o: list<string>, exp: int}|null
	 */
	private static function unsign( string $token ): ?array {
		$parts = explode( '.', $token );
		if ( 2 !== count( $parts ) || ! hash_equals( hash_hmac( 'sha256', $parts[0], self::key() ), $parts[1] ) ) {
			return null;
		}
		$claims = json_decode( (string) base64_decode( strtr( $parts[0], '-_', '+/' ), true ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- URL-safe token encoding.
		if ( ! is_array( $claims ) ) {
			return null;
		}
		foreach ( array( 'u', 'q', 'r', 'c', 'm', 'exp' ) as $key ) {
			if ( ! isset( $claims[ $key ] ) || ! is_int( $claims[ $key ] ) ) {
				return null;
			}
		}
		if ( ! isset( $claims['o'] ) || ! is_array( $claims['o'] ) ) {
			return null;
		}
		return array(
			'u'   => $claims['u'],
			'q'   => $claims['q'],
			'r'   => $claims['r'],
			'c'   => $claims['c'],
			'm'   => $claims['m'],
			'a'   => isset( $claims['a'] ) && is_int( $claims['a'] ) ? $claims['a'] : 0,
			'o'   => array_values( array_map( 'strval', $claims['o'] ) ),
			'exp' => $claims['exp'],
		);
	}
}
