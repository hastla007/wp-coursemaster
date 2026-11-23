<?php
/**
 * Neutral question types of R1 (R0-08 §3.1–§3.4, E-21, E-22, E-69, E-77).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Question;

defined( 'ABSPATH' ) || exit;

/**
 * Validation, delivery without solution data and deterministic evaluation
 * per type. A revision is an array:
 * `question_type`, `question_text`, `settings` (array), `answers`
 * (list of `key`, `text`, `correct`, `sort`, optional `pinned`).
 *
 * Points are integers in thousandths. Scoring strategies: `all_or_nothing`
 * (default) for every type, `proportional_penalty` only for `multi_choice`,
 * `matching` and `cloze`; `per_option` is not allowed in R1 (E-77).
 */
final class QuestionTypes {

	public const SINGLE     = 'single_choice';
	public const TRUE_FALSE = 'true_false';
	public const MULTI      = 'multi_choice';
	public const OPEN_TEXT  = 'open_text';
	public const FLASHCARD  = 'flashcard';
	public const NUMERIC    = 'numeric_tolerance';
	public const MATCHING   = 'matching';
	public const ORDERING   = 'ordering';
	public const CLOZE      = 'cloze';

	/**
	 * All R1 types.
	 *
	 * @var list<string>
	 */
	public const ALL = array( self::SINGLE, self::TRUE_FALSE, self::MULTI, self::OPEN_TEXT, self::FLASHCARD, self::NUMERIC, self::MATCHING, self::ORDERING, self::CLOZE );

	public const ALL_OR_NOTHING       = 'all_or_nothing';
	public const PROPORTIONAL_PENALTY = 'proportional_penalty';
	public const DEFAULT_WEIGHT       = 1000;
	public const MAX_OPTIONS          = 26;
	private const MICRO               = 1000000;

	/**
	 * Types that allow proportional scoring.
	 *
	 * @var list<string>
	 */
	private const PARTIAL_TYPES = array( self::MULTI, self::MATCHING, self::CLOZE );

	/**
	 * Validation before publishing; returns error codes (empty = valid).
	 *
	 * @param array<string, mixed> $revision Revision data.
	 * @return list<string>
	 */
	public static function validate( array $revision ): array {
		$type     = (string) ( $revision['question_type'] ?? '' );
		$settings = self::settings( $revision );
		$answers  = self::answers( $revision );
		$errors   = array();
		if ( ! in_array( $type, self::ALL, true ) ) {
			return array( 'unknown_type' );
		}
		if ( '' === trim( (string) ( $revision['question_text'] ?? '' ) ) ) {
			$errors[] = 'text_missing';
		}
		$scoring = (string) ( $settings['scoring'] ?? self::ALL_OR_NOTHING );
		if ( self::ALL_OR_NOTHING !== $scoring && ! ( self::PROPORTIONAL_PENALTY === $scoring && in_array( $type, self::PARTIAL_TYPES, true ) ) ) {
			$errors[] = 'scoring_not_allowed';
		}
		$correct = count( array_filter( $answers, static fn( array $a ): bool => ! empty( $a['correct'] ) ) );
		switch ( $type ) {
			case self::SINGLE:
			case self::MULTI:
				if ( count( $answers ) < 2 || count( $answers ) > self::MAX_OPTIONS ) {
					$errors[] = 'answers_count';
				}
				if ( self::SINGLE === $type && 1 !== $correct ) {
					$errors[] = 'exactly_one_correct';
				}
				if ( self::MULTI === $type && $correct < 1 ) {
					$errors[] = 'at_least_one_correct';
				}
				$errors = array_merge( $errors, self::key_errors( $answers ) );
				break;
			case self::TRUE_FALSE:
				if ( 2 !== count( $answers ) || 1 !== $correct ) {
					$errors[] = 'true_false_shape';
				}
				break;
			case self::OPEN_TEXT:
				if ( 1 !== count( $answers ) || 'MODEL' !== ( $answers[0]['key'] ?? '' ) || '' === trim( (string) ( $answers[0]['text'] ?? '' ) ) ) {
					$errors[] = 'model_answer';
				}
				break;
			case self::FLASHCARD:
				if ( '' === trim( (string) ( $settings['back'] ?? '' ) ) ) {
					$errors[] = 'back_missing';
				}
				break;
			case self::NUMERIC:
				if ( null === self::to_micro( (string) ( $settings['target'] ?? '' ) ) ) {
					$errors[] = 'target_invalid';
				}
				$tolerance = self::to_micro( (string) ( $settings['tolerance'] ?? '' ) );
				$kind      = (string) ( $settings['tolerance_type'] ?? '' );
				if ( null === $tolerance || $tolerance < 0 || ! in_array( $kind, array( 'absolute', 'relative' ), true ) || ( 'relative' === $kind && $tolerance > 100 * self::MICRO ) ) {
					$errors[] = 'tolerance_invalid';
				}
				break;
			case self::MATCHING:
				$pairs = self::list_of( $settings['pairs'] ?? array() );
				$right = count( $pairs ) + count( self::list_of( $settings['distractors'] ?? array() ) );
				if ( count( $pairs ) < 2 || count( $pairs ) > self::MAX_OPTIONS || $right > self::MAX_OPTIONS ) {
					$errors[] = 'pairs_count';
				}
				$left = array_map( static fn( $p ): string => self::normalize( (string) ( $p['left'] ?? '' ), false ), $pairs );
				if ( count( $left ) !== count( array_unique( $left ) ) || in_array( '', $left, true ) ) {
					$errors[] = 'pairs_duplicate';
				}
				break;
			case self::ORDERING:
				$items = array_map( static fn( $i ): string => self::normalize( (string) ( $i['text'] ?? '' ), false ), self::list_of( $settings['items'] ?? array() ) );
				if ( count( $items ) < 2 || count( $items ) > self::MAX_OPTIONS ) {
					$errors[] = 'items_count';
				}
				if ( count( $items ) !== count( array_unique( $items ) ) || in_array( '', $items, true ) ) {
					$errors[] = 'items_duplicate';
				}
				break;
			case self::CLOZE:
				$gaps = self::list_of( $settings['gaps'] ?? array() );
				$ids  = array_map( static fn( $g ): string => (string) ( $g['id'] ?? '' ), $gaps );
				if ( array() === $gaps || count( $ids ) !== count( array_unique( $ids ) ) || in_array( '', $ids, true ) ) {
					$errors[] = 'gaps_invalid';
				}
				foreach ( $gaps as $gap ) {
					$accepted = array_filter( self::list_of( $gap['accepted'] ?? array() ), static fn( $a ): bool => '' !== trim( (string) $a ) );
					if ( array() === $accepted || count( $accepted ) !== count( self::list_of( $gap['accepted'] ?? array() ) ) ) {
						$errors[] = 'gap_answers';
						break;
					}
				}
				break;
		}
		return array_values( array_unique( $errors ) );
	}

	/**
	 * Public payload for learners: never solution data. `order` holds the
	 * materialized order (option keys, right keys or item keys); it is
	 * created with the randomizer when missing.
	 *
	 * @param array<string, mixed> $revision   Revision data.
	 * @param Randomizer           $randomizer Randomizer.
	 * @param string               $stream     Stream suffix (question UID).
	 * @return array{payload: array<string, mixed>, order: array<int, string>}
	 */
	public static function deliver( array $revision, Randomizer $randomizer, string $stream ): array {
		$type     = (string) $revision['question_type'];
		$settings = self::settings( $revision );
		$payload  = array(
			'type' => $type,
			'text' => (string) $revision['question_text'],
		);
		$order    = array();
		switch ( $type ) {
			case self::SINGLE:
			case self::MULTI:
			case self::TRUE_FALSE:
				$answers = self::answers( $revision );
				usort( $answers, array( self::class, 'compare_options' ) );
				$keys = array_map( static fn( array $a ): string => (string) $a['key'], $answers );
				if ( self::TRUE_FALSE !== $type && false !== ( $settings['shuffle'] ?? true ) ) {
					$keys = self::shuffle_unpinned( $answers, $randomizer, 'answers:' . $stream );
				}
				$order = $keys;
				$texts = array_column( $answers, 'text', 'key' );
				foreach ( $order as $key ) {
					$payload['options'][] = array(
						'key'  => $key,
						'text' => (string) $texts[ $key ],
					);
				}
				break;
			case self::NUMERIC:
				$payload['unit'] = (string) ( $settings['unit'] ?? '' );
				break;
			case self::MATCHING:
				$pairs = self::list_of( $settings['pairs'] ?? array() );
				$right = array();
				foreach ( $pairs as $i => $pair ) {
					$payload['left'][]         = array(
						'key'  => 'L' . ( $i + 1 ),
						'text' => (string) $pair['left'],
					);
					$right[ 'R' . ( $i + 1 ) ] = (string) $pair['right'];
				}
				foreach ( self::list_of( $settings['distractors'] ?? array() ) as $i => $distractor ) {
					$right[ 'D' . ( $i + 1 ) ] = (string) $distractor;
				}
				$order = $randomizer->shuffle( array_map( 'strval', array_keys( $right ) ), 'answers:' . $stream );
				foreach ( $order as $key ) {
					$payload['right'][] = array(
						'key'  => $key,
						'text' => $right[ $key ],
					);
				}
				break;
			case self::ORDERING:
				$items    = self::list_of( $settings['items'] ?? array() );
				$solution = array_map( static fn( int $i ): string => 'I' . ( $i + 1 ), array_keys( $items ) );
				$order    = $randomizer->shuffle( $solution, 'answers:' . $stream );
				if ( $order === $solution ) {
					// Never deliver the solution order: rotate by one (R0-08 §3.4).
					$first   = (string) array_shift( $order );
					$order[] = $first;
				}
				foreach ( $order as $key ) {
					$payload['items'][] = array(
						'key'  => $key,
						'text' => (string) $items[ (int) substr( $key, 1 ) - 1 ]['text'],
					);
				}
				break;
			case self::CLOZE:
				$payload['gaps'] = array_map( static fn( $g ): string => (string) $g['id'], self::list_of( $settings['gaps'] ?? array() ) );
				break;
		}
		return array(
			'payload' => $payload,
			'order'   => $order,
		);
	}

	/**
	 * Evaluate an answer.
	 *
	 * @param array<string, mixed> $revision Revision data.
	 * @param mixed                $answer   Answer (format per type).
	 * @param array                $order    Delivered keys (allowed options).
	 * @phpstan-param list<string> $order
	 * @param int                  $weight   Points in thousandths.
	 * @param string|null          $scoring  Override from the exam rule.
	 * @return Evaluation
	 */
	public static function evaluate( array $revision, $answer, array $order = array(), int $weight = self::DEFAULT_WEIGHT, ?string $scoring = null ): Evaluation {
		$type     = (string) $revision['question_type'];
		$settings = self::settings( $revision );
		$scoring  = $scoring ?? (string) ( $settings['scoring'] ?? self::ALL_OR_NOTHING );
		if ( ! in_array( $type, self::PARTIAL_TYPES, true ) ) {
			$scoring = self::ALL_OR_NOTHING;
		}
		switch ( $type ) {
			case self::SINGLE:
			case self::TRUE_FALSE:
			case self::MULTI:
				return self::evaluate_choice( $type, self::answers( $revision ), $answer, $order, $weight, $scoring );
			case self::OPEN_TEXT:
				if ( ! is_array( $answer ) || ! array_key_exists( 'known', $answer ) || ! is_bool( $answer['known'] ) ) {
					return Evaluation::invalid();
				}
				return Evaluation::binary( $answer['known'], $weight );
			case self::FLASHCARD:
				return new Evaluation( true, true, 0, 0 );
			case self::NUMERIC:
				return self::evaluate_numeric( $settings, $answer, $weight );
			case self::MATCHING:
				return self::evaluate_matching( $settings, $answer, $weight, $scoring );
			case self::ORDERING:
				return self::evaluate_ordering( $settings, $answer, $weight );
			case self::CLOZE:
				return self::evaluate_cloze( $settings, $answer, $weight, $scoring );
		}
		return Evaluation::invalid();
	}

	/**
	 * Choice types (QP-Parität: exact set; proportional for multi).
	 *
	 * @param string                     $type    Type.
	 * @param list<array<string, mixed>> $answers Options.
	 * @param mixed                      $answer  Chosen keys.
	 * @param array                      $order   Allowed keys.
	 * @phpstan-param list<string> $order
	 * @param int                        $weight  Weight.
	 * @param string                     $scoring Scoring.
	 * @return Evaluation
	 */
	private static function evaluate_choice( string $type, array $answers, $answer, array $order, int $weight, string $scoring ): Evaluation {
		$chosen = is_array( $answer ) ? array_values( array_unique( array_map( 'strval', $answer ) ) ) : ( is_string( $answer ) && '' !== $answer ? array( $answer ) : array() );
		if ( array() === $chosen ) {
			return Evaluation::empty_answer( $weight );
		}
		$allowed = array() === $order ? array_map( static fn( array $a ): string => (string) $a['key'], $answers ) : $order;
		if ( array() !== array_diff( $chosen, $allowed ) || ( self::MULTI !== $type && 1 !== count( $chosen ) ) ) {
			return Evaluation::invalid();
		}
		$right = array_map( static fn( array $a ): string => (string) $a['key'], array_values( array_filter( $answers, static fn( array $a ): bool => ! empty( $a['correct'] ) ) ) );
		sort( $chosen );
		sort( $right );
		if ( self::PROPORTIONAL_PENALTY === $scoring ) {
			$r = count( array_intersect( $chosen, $right ) );
			return self::partial( $r, count( $chosen ) - $r, count( $right ), $weight );
		}
		return Evaluation::binary( $chosen === $right, $weight );
	}

	/**
	 * Number with tolerance (E-22): fixed point, inclusive bound.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param mixed                $answer   Input string.
	 * @param int                  $weight   Weight.
	 * @return Evaluation
	 */
	private static function evaluate_numeric( array $settings, $answer, int $weight ): Evaluation {
		if ( null === $answer || ( is_string( $answer ) && '' === trim( $answer ) ) ) {
			return Evaluation::empty_answer( $weight );
		}
		$value = is_string( $answer ) || is_int( $answer ) ? self::to_micro( (string) $answer ) : null;
		if ( null === $value ) {
			return Evaluation::invalid();
		}
		$target    = (int) self::to_micro( (string) $settings['target'] );
		$tolerance = (int) self::to_micro( (string) $settings['tolerance'] );
		$diff      = abs( $value - $target );
		$ok        = 'relative' === $settings['tolerance_type']
			? $diff * 100 * self::MICRO <= abs( $target ) * $tolerance
			: $diff <= $tolerance;
		return Evaluation::binary( $ok, $weight );
	}

	/**
	 * Matching (E-69): map left key → right key.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param mixed                $answer   Assignment.
	 * @param int                  $weight   Weight.
	 * @param string               $scoring  Scoring.
	 * @return Evaluation
	 */
	private static function evaluate_matching( array $settings, $answer, int $weight, string $scoring ): Evaluation {
		$pairs = self::list_of( $settings['pairs'] ?? array() );
		$count = count( $pairs );
		if ( ! is_array( $answer ) ) {
			return Evaluation::invalid();
		}
		$valid_right = array();
		foreach ( array_keys( $pairs ) as $i ) {
			$valid_right[] = 'R' . ( $i + 1 );
		}
		foreach ( array_keys( self::list_of( $settings['distractors'] ?? array() ) ) as $i ) {
			$valid_right[] = 'D' . ( $i + 1 );
		}
		$used = array();
		$r    = 0;
		$f    = 0;
		foreach ( $answer as $left => $right ) {
			$right = (string) $right;
			if ( '' === $right ) {
				continue;
			}
			$index = 1 === preg_match( '/^L(\d+)$/', (string) $left, $m ) ? (int) $m[1] : 0;
			if ( $index < 1 || $index > $count || ! in_array( $right, $valid_right, true ) || isset( $used[ $right ] ) ) {
				return Evaluation::invalid();
			}
			$used[ $right ] = true;
			if ( 'R' . $index === $right ) {
				++$r;
			} else {
				++$f;
			}
		}
		if ( 0 === $r + $f ) {
			return Evaluation::empty_answer( $weight );
		}
		return self::PROPORTIONAL_PENALTY === $scoring ? self::partial( $r, $f, $count, $weight ) : Evaluation::binary( $r === $count, $weight );
	}

	/**
	 * Ordering: only the exact order is correct.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param mixed                $answer   List of item keys.
	 * @param int                  $weight   Weight.
	 * @return Evaluation
	 */
	private static function evaluate_ordering( array $settings, $answer, int $weight ): Evaluation {
		$count    = count( self::list_of( $settings['items'] ?? array() ) );
		$solution = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$solution[] = 'I' . $i;
		}
		if ( ! is_array( $answer ) || array() === $answer ) {
			return is_array( $answer ) ? Evaluation::empty_answer( $weight ) : Evaluation::invalid();
		}
		$given  = array_values( array_map( 'strval', $answer ) );
		$sorted = $given;
		sort( $sorted );
		$expect = $solution;
		sort( $expect );
		if ( $sorted !== $expect ) {
			return Evaluation::invalid();
		}
		return Evaluation::binary( $given === $solution, $weight );
	}

	/**
	 * Cloze (E-69): normalized match per gap.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param mixed                $answer   Gap ID → input.
	 * @param int                  $weight   Weight.
	 * @param string               $scoring  Scoring.
	 * @return Evaluation
	 */
	private static function evaluate_cloze( array $settings, $answer, int $weight, string $scoring ): Evaluation {
		if ( ! is_array( $answer ) ) {
			return Evaluation::invalid();
		}
		$case = ! empty( $settings['case_sensitive'] );
		$gaps = self::list_of( $settings['gaps'] ?? array() );
		$ids  = array_map( static fn( $g ): string => (string) $g['id'], $gaps );
		if ( array() !== array_diff( array_map( 'strval', array_keys( $answer ) ), $ids ) ) {
			return Evaluation::invalid();
		}
		$r = 0;
		$f = 0;
		foreach ( $gaps as $gap ) {
			$input = self::normalize( (string) ( $answer[ (string) $gap['id'] ] ?? '' ), $case );
			if ( '' === $input ) {
				continue;
			}
			$accepted = array_map( static fn( $a ): string => self::normalize( (string) $a, $case ), self::list_of( $gap['accepted'] ) );
			if ( in_array( $input, $accepted, true ) ) {
				++$r;
			} else {
				++$f;
			}
		}
		if ( 0 === $r + $f ) {
			return Evaluation::empty_answer( $weight );
		}
		$total = count( $gaps );
		return self::PROPORTIONAL_PENALTY === $scoring ? self::partial( $r, $f, $total, $weight ) : Evaluation::binary( $total === $r, $weight );
	}

	/**
	 * `proportional_penalty`: w · max(0, (r − f) / c), floored to thousandths.
	 *
	 * @param int $r      Right.
	 * @param int $f      Wrong.
	 * @param int $c      Count of correct items.
	 * @param int $weight Weight.
	 * @return Evaluation
	 */
	private static function partial( int $r, int $f, int $c, int $weight ): Evaluation {
		$points = $c <= 0 ? 0 : intdiv( $weight * max( 0, $r - $f ), $c );
		return new Evaluation( true, true, $points, $weight );
	}

	/**
	 * Decimal string → integer millionths; null when invalid (R0-08 §3.1:
	 * sign, digits, one separator `,` or `.`, at most 6 decimals, nothing else).
	 *
	 * @param string $value Input.
	 * @return int|null
	 */
	public static function to_micro( string $value ): ?int {
		$value = trim( $value );
		if ( 1 !== preg_match( '/^([+-]?)(\d{1,12})(?:[.,](\d{1,6}))?$/', $value, $m ) ) {
			return null;
		}
		$micro = (int) $m[2] * self::MICRO + (int) str_pad( $m[3] ?? '', 6, '0' );
		return '-' === $m[1] ? -$micro : $micro;
	}

	/**
	 * Cloze/ordering normalization: trim, NFC, collapsed whitespace, case.
	 *
	 * @param string $text           Text.
	 * @param bool   $case_sensitive Keep case.
	 * @return string
	 */
	public static function normalize( string $text, bool $case_sensitive ): string {
		if ( class_exists( '\Normalizer' ) ) {
			$text = (string) \Normalizer::normalize( $text, \Normalizer::FORM_C );
		}
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		return $case_sensitive ? $text : mb_strtolower( $text, 'UTF-8' );
	}

	/**
	 * Option order: author `sort`, then key.
	 *
	 * @param array<string, mixed> $a Option.
	 * @param array<string, mixed> $b Option.
	 * @return int
	 */
	public static function compare_options( array $a, array $b ): int {
		$by_sort = (int) ( $a['sort'] ?? 0 ) <=> (int) ( $b['sort'] ?? 0 );
		return 0 !== $by_sort ? $by_sort : strcmp( (string) $a['key'], (string) $b['key'] );
	}

	/**
	 * Shuffle option keys; pinned options keep their position.
	 *
	 * @param list<array<string, mixed>> $answers    Options in author order.
	 * @param Randomizer                 $randomizer Randomizer.
	 * @param string                     $purpose    Stream.
	 * @return list<string>
	 */
	private static function shuffle_unpinned( array $answers, Randomizer $randomizer, string $purpose ): array {
		$free = array();
		foreach ( $answers as $answer ) {
			if ( empty( $answer['pinned'] ) ) {
				$free[] = (string) $answer['key'];
			}
		}
		$free = $randomizer->shuffle( $free, $purpose );
		$out  = array();
		foreach ( $answers as $answer ) {
			$out[] = empty( $answer['pinned'] ) ? (string) array_shift( $free ) : (string) $answer['key'];
		}
		return $out;
	}

	/**
	 * Settings of a revision.
	 *
	 * @param array<string, mixed> $revision Revision.
	 * @return array<string, mixed>
	 */
	private static function settings( array $revision ): array {
		$settings = $revision['settings'] ?? array();
		return is_array( $settings ) ? $settings : array();
	}

	/**
	 * Options of a revision.
	 *
	 * @param array<string, mixed> $revision Revision.
	 * @return list<array<string, mixed>>
	 */
	private static function answers( array $revision ): array {
		return self::list_of( $revision['answers'] ?? array() );
	}

	/**
	 * Duplicate/invalid option keys (`^[A-Z]$`).
	 *
	 * @param list<array<string, mixed>> $answers Options.
	 * @return list<string>
	 */
	private static function key_errors( array $answers ): array {
		$keys = array_map( static fn( array $a ): string => (string) ( $a['key'] ?? '' ), $answers );
		foreach ( $keys as $key ) {
			if ( 1 !== preg_match( '/^[A-Z]$/', $key ) ) {
				return array( 'answer_key_invalid' );
			}
		}
		return count( $keys ) === count( array_unique( $keys ) ) ? array() : array( 'answer_key_duplicate' );
	}

	/**
	 * A list (array values) or empty list.
	 *
	 * @param mixed $value Value.
	 * @return list<mixed>
	 */
	private static function list_of( $value ): array {
		return is_array( $value ) ? array_values( $value ) : array();
	}
}
