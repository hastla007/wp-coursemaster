<?php
/**
 * Change of a question's type with a compatibility check (UI-F8, R0-08 §3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Question;

defined( 'ABSPATH' ) || exit;

/**
 * Pure planning step: maps the content of a revision to another type and
 * names what is kept and what would be lost. Text, feedback and tags
 * always stay. Options of the choice types map onto each other; the
 * solution (correct options, model answer, card back, target number,
 * pairs, items, gaps) becomes the model answer or card back; ordering
 * items and choice options map onto each other. The result is a new
 * revision (the caller never changes an existing one, so published exam
 * snapshots keep their revision); `complete` says whether it would pass
 * the type validation or still needs editing before publishing.
 */
final class TypeChange {

	/**
	 * Codes of the parts that can be kept or lost.
	 */
	public const PARTS = array( 'text', 'feedback', 'tags', 'options', 'correct', 'solution', 'order', 'numeric', 'pairs', 'gaps', 'scoring', 'shuffle' );

	private const CHOICE = array( QuestionTypes::SINGLE, QuestionTypes::MULTI, QuestionTypes::TRUE_FALSE );

	/**
	 * Plan a type change.
	 *
	 * @param array<string, mixed> $revision Current revision (shape of QuestionRepository::revision()).
	 * @param string               $to       Target type.
	 * @return array{data: array<string, mixed>, kept: list<string>, lost: list<string>, complete: bool}
	 * @throws \InvalidArgumentException For unknown or unchanged types.
	 */
	public static function plan( array $revision, string $to ): array {
		$from = (string) ( $revision['question_type'] ?? '' );
		if ( ! in_array( $to, QuestionTypes::ALL, true ) || $to === $from ) {
			throw new \InvalidArgumentException( 'type' );
		}
		$settings = (array) ( $revision['settings'] ?? array() );
		$answers  = array_values( (array) ( $revision['answers'] ?? array() ) );
		usort( $answers, static fn( array $a, array $b ): int => (int) ( $a['sort'] ?? 0 ) <=> (int) ( $b['sort'] ?? 0 ) );
		$kept = array( 'text' );
		$lost = array();
		if ( '' !== trim( (string) ( $revision['feedback_correct'] ?? '' ) . (string) ( $revision['feedback_incorrect'] ?? '' ) ) ) {
			$kept[] = 'feedback';
		}
		if ( array() !== (array) ( $revision['tags'] ?? array() ) ) {
			$kept[] = 'tags';
		}
		$data     = array(
			'question_type'      => $to,
			'question_text'      => (string) ( $revision['question_text'] ?? '' ),
			'settings'           => array(),
			'answers'            => array(),
			'feedback_correct'   => $revision['feedback_correct'] ?? null,
			'feedback_incorrect' => $revision['feedback_incorrect'] ?? null,
			'tags'               => array_values( (array) ( $revision['tags'] ?? array() ) ),
		);
		$options  = self::options( $from, $answers, $settings );
		$solution = self::solution( $from, $answers, $settings );
		$has      = static fn( string $part ): bool => self::carries( $from, $part, $answers, $settings );

		switch ( $to ) {
			case QuestionTypes::SINGLE:
			case QuestionTypes::MULTI:
				if ( in_array( $from, self::CHOICE, true ) || QuestionTypes::ORDERING === $from ) {
					$correct = array_map( static fn( array $o ): bool => $o['correct'], $options );
					if ( QuestionTypes::SINGLE === $to && count( array_filter( $correct ) ) > 1 ) {
						// Only the first correct option stays correct.
						$first   = (int) array_search( true, $correct, true );
						$correct = array_map( static fn( int $i ): bool => $i === $first, array_keys( $correct ) );
						$lost[]  = 'correct';
					}
					$data['answers'] = self::keyed( array_column( $options, 'text' ), $correct );
					$kept[]          = 'options';
					if ( QuestionTypes::ORDERING === $from ) {
						$lost[] = 'order';
					} elseif ( count( array_filter( $correct ) ) > 0 ) {
						$kept[] = 'correct';
					}
				} elseif ( array() !== $solution ) {
					// The solution becomes the correct option(s); wrong options must be added.
					$data['answers'] = self::keyed( QuestionTypes::SINGLE === $to ? array( implode( ' ', $solution ) ) : $solution, array_fill( 0, QuestionTypes::SINGLE === $to ? 1 : count( $solution ), true ) );
					$kept[]          = 'solution';
				}
				if ( in_array( $from, array( QuestionTypes::SINGLE, QuestionTypes::MULTI ), true ) && array_key_exists( 'shuffle', $settings ) ) {
					$data['settings']['shuffle'] = (bool) $settings['shuffle'];
					$kept[]                      = 'shuffle';
				}
				break;
			case QuestionTypes::TRUE_FALSE:
				$truth           = self::truth( $options );
				$data['answers'] = array(
					array(
						'key'     => 'T',
						'text'    => __( 'True', 'course-captain' ),
						'correct' => true === $truth,
						'sort'    => 0,
					),
					array(
						'key'     => 'F',
						'text'    => __( 'False', 'course-captain' ),
						'correct' => false === $truth,
						'sort'    => 1,
					),
				);
				if ( null !== $truth ) {
					$kept[] = 'correct';
				}
				break;
			case QuestionTypes::OPEN_TEXT:
				$data['answers'] = array(
					array(
						'key'     => 'MODEL',
						'text'    => implode( "\n", $solution ),
						'correct' => true,
						'sort'    => 0,
					),
				);
				if ( array() !== $solution ) {
					$kept[] = 'solution';
				}
				break;
			case QuestionTypes::FLASHCARD:
				$data['settings']['back'] = implode( "\n", $solution );
				if ( array() !== $solution ) {
					$kept[] = 'solution';
				}
				break;
			case QuestionTypes::NUMERIC:
				$target = null;
				foreach ( $solution as $text ) {
					$value = str_replace( ',', '.', trim( (string) preg_replace( '/[^0-9.,+-].*$/u', '', trim( $text ) ) ) );
					if ( null !== QuestionTypes::to_micro( $value ) ) {
						$target = $value;
						break;
					}
				}
				$data['settings'] = array(
					'target'         => (string) $target,
					'tolerance_type' => 'absolute',
					'tolerance'      => '0',
					'unit'           => '',
				);
				if ( null !== $target ) {
					$kept[] = 'solution';
				}
				break;
			case QuestionTypes::ORDERING:
				$data['settings']['items'] = array_map( static fn( array $o ): array => array( 'text' => $o['text'] ), $options );
				if ( array() !== $options ) {
					$kept[] = 'options';
				}
				break;
			case QuestionTypes::MATCHING:
			case QuestionTypes::CLOZE:
				// No other type carries pairs or gaps.
				break;
		}
		// Everything the source type carried and the target did not take is lost.
		foreach ( array( 'options', 'correct', 'solution', 'order', 'numeric', 'pairs', 'gaps', 'scoring', 'shuffle' ) as $part ) {
			if ( $has( $part ) && ! in_array( $part, $kept, true ) && ! self::covered( $part, $kept ) ) {
				$lost[] = $part;
			}
		}
		if ( QuestionTypes::PROPORTIONAL_PENALTY === ( $settings['scoring'] ?? null ) && in_array( $to, array( QuestionTypes::MULTI, QuestionTypes::MATCHING, QuestionTypes::CLOZE ), true ) && $has( 'scoring' ) ) {
			$data['settings']['scoring'] = QuestionTypes::PROPORTIONAL_PENALTY;
			$lost                        = array_values( array_diff( $lost, array( 'scoring' ) ) );
			$kept[]                      = 'scoring';
		}
		return array(
			'data'     => $data,
			'kept'     => array_values( array_unique( $kept ) ),
			'lost'     => array_values( array_unique( $lost ) ),
			'complete' => array() === QuestionTypes::validate( $data ),
		);
	}

	/**
	 * Whether a kept part already covers a source part (e.g. the solution
	 * of a choice question lives on in its kept correct options).
	 *
	 * @param string $part Part.
	 * @param array  $kept Kept parts.
	 * @phpstan-param list<string> $kept
	 * @return bool
	 */
	private static function covered( string $part, array $kept ): bool {
		return ( 'solution' === $part && in_array( 'correct', $kept, true ) ) || ( 'correct' === $part && in_array( 'solution', $kept, true ) );
	}

	/**
	 * Whether the source revision carries a part.
	 *
	 * @param string                     $from     Source type.
	 * @param string                     $part     Part.
	 * @param list<array<string, mixed>> $answers Answers.
	 * @param array<string, mixed>       $settings Settings.
	 * @return bool
	 */
	private static function carries( string $from, string $part, array $answers, array $settings ): bool {
		$choice = in_array( $from, self::CHOICE, true );
		switch ( $part ) {
			case 'options':
				return ( $choice && QuestionTypes::TRUE_FALSE !== $from && array() !== $answers ) || ( QuestionTypes::ORDERING === $from && array() !== (array) ( $settings['items'] ?? array() ) );
			case 'correct':
				return $choice && array() !== array_filter( $answers, static fn( array $a ): bool => ! empty( $a['correct'] ) );
			case 'solution':
				return ( QuestionTypes::OPEN_TEXT === $from && '' !== trim( (string) ( $answers[0]['text'] ?? '' ) ) ) || ( QuestionTypes::FLASHCARD === $from && '' !== trim( (string) ( $settings['back'] ?? '' ) ) );
			case 'order':
				return QuestionTypes::ORDERING === $from && array() !== (array) ( $settings['items'] ?? array() );
			case 'numeric':
				return QuestionTypes::NUMERIC === $from && '' !== trim( (string) ( $settings['target'] ?? '' ) );
			case 'pairs':
				return QuestionTypes::MATCHING === $from && array() !== (array) ( $settings['pairs'] ?? array() );
			case 'gaps':
				return QuestionTypes::CLOZE === $from && array() !== (array) ( $settings['gaps'] ?? array() );
			case 'scoring':
				return QuestionTypes::PROPORTIONAL_PENALTY === ( $settings['scoring'] ?? null );
			case 'shuffle':
				return in_array( $from, array( QuestionTypes::SINGLE, QuestionTypes::MULTI ), true ) && array_key_exists( 'shuffle', $settings );
		}
		return false;
	}

	/**
	 * Options of the source as text with correct flag.
	 *
	 * @param string                     $from     Source type.
	 * @param list<array<string, mixed>> $answers  Answers.
	 * @param array<string, mixed>       $settings Settings.
	 * @return list<array{text: string, correct: bool}>
	 */
	private static function options( string $from, array $answers, array $settings ): array {
		if ( in_array( $from, self::CHOICE, true ) ) {
			return array_map(
				static fn( array $a ): array => array(
					'text'    => (string) $a['text'],
					'correct' => ! empty( $a['correct'] ),
				),
				$answers
			);
		}
		if ( QuestionTypes::ORDERING === $from ) {
			return array_values(
				array_map(
					static fn( $i ): array => array(
						'text'    => (string) ( is_array( $i ) ? ( $i['text'] ?? '' ) : $i ),
						'correct' => false,
					),
					(array) ( $settings['items'] ?? array() )
				)
			);
		}
		return array();
	}

	/**
	 * Solution of the source as text lines.
	 *
	 * @param string                     $from     Source type.
	 * @param list<array<string, mixed>> $answers  Answers.
	 * @param array<string, mixed>       $settings Settings.
	 * @return list<string>
	 */
	private static function solution( string $from, array $answers, array $settings ): array {
		$lines = array();
		switch ( $from ) {
			case QuestionTypes::SINGLE:
			case QuestionTypes::MULTI:
			case QuestionTypes::TRUE_FALSE:
				foreach ( $answers as $a ) {
					if ( ! empty( $a['correct'] ) ) {
						$lines[] = (string) $a['text'];
					}
				}
				break;
			case QuestionTypes::OPEN_TEXT:
				$lines[] = (string) ( $answers[0]['text'] ?? '' );
				break;
			case QuestionTypes::FLASHCARD:
				$lines[] = (string) ( $settings['back'] ?? '' );
				break;
			case QuestionTypes::NUMERIC:
				$lines[] = trim( (string) ( $settings['target'] ?? '' ) . ' ' . (string) ( $settings['unit'] ?? '' ) );
				break;
			case QuestionTypes::MATCHING:
				foreach ( (array) ( $settings['pairs'] ?? array() ) as $pair ) {
					$lines[] = (string) ( $pair['left'] ?? '' ) . ' – ' . (string) ( $pair['right'] ?? '' );
				}
				break;
			case QuestionTypes::ORDERING:
				foreach ( (array) ( $settings['items'] ?? array() ) as $i => $item ) {
					$lines[] = ( (int) $i + 1 ) . '. ' . (string) ( is_array( $item ) ? ( $item['text'] ?? '' ) : $item );
				}
				break;
			case QuestionTypes::CLOZE:
				foreach ( (array) ( $settings['gaps'] ?? array() ) as $gap ) {
					$lines[] = '[' . (string) ( $gap['id'] ?? '' ) . '] ' . implode( ' | ', array_map( 'strval', (array) ( $gap['accepted'] ?? array() ) ) );
				}
				break;
		}
		return array_values( array_filter( array_map( 'trim', $lines ), static fn( string $l ): bool => '' !== $l ) );
	}

	/**
	 * Truth value of a choice question with two options "true"/"false".
	 *
	 * @param list<array{text: string, correct: bool}> $options Options.
	 * @return bool|null Null when it cannot be told.
	 */
	private static function truth( array $options ): ?bool {
		if ( 2 !== count( $options ) || 1 !== count( array_filter( array_column( $options, 'correct' ) ) ) ) {
			return null;
		}
		$true  = array( 'true', 'wahr', 'richtig', 'ja', 'yes', mb_strtolower( __( 'True', 'course-captain' ) ) );
		$false = array( 'false', 'falsch', 'nein', 'no', mb_strtolower( __( 'False', 'course-captain' ) ) );
		foreach ( $options as $option ) {
			if ( ! $option['correct'] ) {
				continue;
			}
			$text = mb_strtolower( trim( wp_strip_all_tags( $option['text'] ) ) );
			if ( in_array( $text, $true, true ) ) {
				return true;
			}
			if ( in_array( $text, $false, true ) ) {
				return false;
			}
		}
		return null;
	}

	/**
	 * Options with keys A, B, … .
	 *
	 * @param array $texts   Texts.
	 * @param array $correct Correct flags.
	 * @phpstan-param list<string> $texts
	 * @phpstan-param list<bool> $correct
	 * @return list<array{key: string, text: string, correct: bool, sort: int}>
	 */
	private static function keyed( array $texts, array $correct ): array {
		$out = array();
		foreach ( array_slice( array_values( $texts ), 0, QuestionTypes::MAX_OPTIONS ) as $i => $text ) {
			$out[] = array(
				'key'     => range( 'A', 'Z' )[ $i ],
				'text'    => $text,
				'correct' => ! empty( $correct[ $i ] ),
				'sort'    => $i,
			);
		}
		return $out;
	}
}
