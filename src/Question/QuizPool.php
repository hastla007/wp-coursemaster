<?php
/**
 * Practice quiz drawn from the module pool (R0-08 §2.4 "feste Fragen oder
 * Modulpool", §6 Randomizer; UI-F5, h5 L-2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Question;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * A quiz with `settings.source = module_pool` has no own question list:
 * each learner gets `settings.count` questions (default 10, at most 200)
 * drawn from the deliverable pool of the lesson's module. The draw uses a
 * fresh 64-bit seed per attempt and the derived stream `pool:<lesson>`
 * (R0-08 §6 MUSS), so it is reproducible from the stored seed. The draw
 * (`seed`, `attempt`, `ids`, `drawn_at`) is kept in the learner's activity
 * progress (`pool_draw`); a new attempt draws again with a new seed.
 *
 * Flashcards are never drawn (they have no answer to evaluate); a scored
 * quiz (`settings.scored`) also leaves out open questions, which are only
 * self-assessed. Unpublished, expired or not yet valid questions are not in
 * the deliverable pool ({@see QuestionRepository::pool_revisions()}).
 */
final class QuizPool {

	public const SOURCE        = 'module_pool';
	public const DEFAULT_COUNT = 10;
	public const MAX_COUNT     = 200;

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
	 * Whether a lesson is a quiz drawn from the module pool.
	 *
	 * @param int $lesson_id Lesson.
	 * @return bool
	 */
	public static function is_pool( int $lesson_id ): bool {
		$definition = ActivityService::definition( $lesson_id );
		return 'quiz' === $definition['type'] && self::SOURCE === ( $definition['settings']['source'] ?? '' );
	}

	/**
	 * Number of questions per attempt from the settings.
	 *
	 * @param array<string, mixed> $settings Quiz settings.
	 * @return int
	 */
	public static function count_setting( array $settings ): int {
		$count = $settings['count'] ?? self::DEFAULT_COUNT;
		return is_int( $count ) ? max( 1, min( self::MAX_COUNT, $count ) ) : self::DEFAULT_COUNT;
	}

	/**
	 * Questions of the module pool this quiz may draw from, in pool order
	 * (without foreign content withheld for its rights, R0-10 §2.2).
	 *
	 * @param int  $lesson_id Lesson.
	 * @param bool $withheld  Keep withheld questions (structure check of the validator; rights are VAL-100/101).
	 * @return list<int>
	 */
	public function eligible( int $lesson_id, bool $withheld = false ): array {
		$module = (int) wp_get_post_parent_id( $lesson_id );
		if ( $module <= 0 ) {
			return array();
		}
		$revisions = $this->questions->pool_revisions( $module, false, $withheld );
		if ( array() === $revisions ) {
			return array();
		}
		$excluded = array( QuestionTypes::FLASHCARD );
		if ( true === ( ActivityService::definition( $lesson_id )['settings']['scored'] ?? false ) ) {
			$excluded[] = QuestionTypes::OPEN_TEXT;
		}
		$list  = implode( ',', array_map( 'intval', array_values( $revisions ) ) );
		$types = array();
		foreach ( $this->db->rows( 'SELECT id, question_type FROM `' . $this->db->table( 'ccap_question_revision' ) . "` WHERE id IN ({$list})" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list.
			$types[ (int) $row['id'] ] = (string) $row['question_type'];
		}
		$out = array();
		foreach ( $revisions as $question => $revision ) {
			if ( isset( $types[ $revision ] ) && ! in_array( $types[ $revision ], $excluded, true ) ) {
				$out[] = (int) $question;
			}
		}
		return $out;
	}

	/**
	 * Deterministic draw: shuffle the eligible questions with the seed and
	 * take the first `count`.
	 *
	 * @param array  $eligible  Eligible questions.
	 * @phpstan-param list<int> $eligible
	 * @param string $seed      Seed of the attempt.
	 * @param int    $count     Questions per attempt.
	 * @param int    $lesson_id Lesson (stream name).
	 * @return list<int>
	 */
	public static function pick( array $eligible, string $seed, int $count, int $lesson_id ): array {
		$shuffled = ( new Randomizer( $seed ) )->shuffle( $eligible, 'pool:' . $lesson_id );
		return array_values( array_slice( $shuffled, 0, max( 0, $count ) ) );
	}

	/**
	 * The learner's current draw, if any.
	 *
	 * @param int $user_id   Learner.
	 * @param int $lesson_id Lesson.
	 * @return array{seed: string, attempt: int, ids: list<int>, drawn_at: string}|null
	 */
	public function current_draw( int $user_id, int $lesson_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		$row  = ActivityProgress::current()->state( $user_id, $lesson_id );
		$data = null === $row ? null : json_decode( (string) ( $row['data'] ?? '' ), true );
		$draw = is_array( $data ) ? ( $data['pool_draw'] ?? null ) : null;
		if ( ! is_array( $draw ) || ! is_array( $draw['ids'] ?? null ) ) {
			return null;
		}
		return array(
			'seed'     => (string) ( $draw['seed'] ?? '' ),
			'attempt'  => (int) ( $draw['attempt'] ?? 1 ),
			'ids'      => array_values( array_map( 'intval', $draw['ids'] ) ),
			'drawn_at' => (string) ( $draw['drawn_at'] ?? '' ),
		);
	}

	/**
	 * The learner's draw: the current one, or a new one on the first call,
	 * when the current one is empty, or with `$restart` (new attempt, new seed).
	 *
	 * @param Actor           $actor      Learner.
	 * @param int             $course_id  Course.
	 * @param int             $lesson_id  Lesson.
	 * @param bool            $restart    Start a new attempt.
	 * @param Randomizer|null $randomizer Seed source (tests).
	 * @return array{seed: string, attempt: int, ids: list<int>, drawn_at: string}
	 * @throws \CourseCaptain\Course\CourseException Access errors of the lesson.
	 */
	public function draw( Actor $actor, int $course_id, int $lesson_id, bool $restart = false, ?Randomizer $randomizer = null ): array {
		ContentGate::current()->lesson( $actor, $course_id, $lesson_id )->assert();
		$current = $this->current_draw( $actor->user_id, $lesson_id );
		if ( null !== $current && array() !== $current['ids'] && ! $restart ) {
			return $current;
		}
		$seed = ( $randomizer ?? Randomizer::fresh() )->seed();
		$draw = array(
			'seed'     => $seed,
			'attempt'  => null === $current ? 1 : $current['attempt'] + ( $restart ? 1 : 0 ),
			'ids'      => self::pick( $this->eligible( $lesson_id ), $seed, self::count_setting( ActivityService::definition( $lesson_id )['settings'] ), $lesson_id ),
			// Same clock as the answers (`ccap_attempt.created_at`), which count from here.
			'drawn_at' => \CourseCaptain\Learning\SmartTrainer::sql( \CourseCaptain\Learning\SmartTrainer::now() ),
		);
		ActivityProgress::current()->record_evidence(
			$actor->user_id,
			$course_id,
			$lesson_id,
			array(
				'pool_draw' => $draw,
				'answered'  => 0,
				'correct'   => 0,
				'total'     => count( $draw['ids'] ),
			)
		);
		return $draw;
	}
}
