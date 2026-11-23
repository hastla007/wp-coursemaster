<?php
/**
 * Answer fields of a delivered question for server-rendered forms
 * (exam room, smart trainer; R0-15 §2.2, R0-08 §3.1/§3.4, E-342).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Question\QuestionTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Every type is answerable without JavaScript:
 * - choice types as radio buttons/checkboxes, numbers as text field;
 * - matching: one labelled select per left item;
 * - ordering: one labelled select "position 1…n" per item (the delivered
 *   order is shown; the answer is the item keys sorted by position);
 * - cloze: the markers `{{<gap id>}}` in the text become visible numbered
 *   gaps, every gap gets a labelled text field below the text.
 * Field names: `<name>` (choice/number), `<name>[]` (multi),
 * `<name>[<key>]` (matching, ordering, cloze). No solution data is ever
 * needed (R0-08 §3.4: the payload carries none).
 */
final class QuestionForm {

	/**
	 * Types this form can render.
	 *
	 * @var list<string>
	 */
	public const TYPES = array( QuestionTypes::SINGLE, QuestionTypes::TRUE_FALSE, QuestionTypes::MULTI, QuestionTypes::NUMERIC, QuestionTypes::MATCHING, QuestionTypes::ORDERING, QuestionTypes::CLOZE );

	/**
	 * Short hint per type (part of the fieldset name, A11Y-022).
	 *
	 * @param string $type Question type.
	 * @return string
	 */
	public static function hint( string $type ): string {
		$hints = array(
			QuestionTypes::SINGLE     => __( 'Choose one answer.', 'course-captain' ),
			QuestionTypes::TRUE_FALSE => __( 'Choose one answer.', 'course-captain' ),
			QuestionTypes::MULTI      => __( 'Choose one or more answers.', 'course-captain' ),
			QuestionTypes::NUMERIC    => __( 'Enter a number.', 'course-captain' ),
			QuestionTypes::MATCHING   => __( 'Match each item.', 'course-captain' ),
			QuestionTypes::ORDERING   => __( 'Give every item its position in the correct order.', 'course-captain' ),
			QuestionTypes::CLOZE      => __( 'Fill in the gaps.', 'course-captain' ),
			QuestionTypes::OPEN_TEXT  => __( 'Answer in your mind, then compare with the model answer.', 'course-captain' ),
		);
		return $hints[ $type ] ?? '';
	}

	/**
	 * Question text (sanitized); cloze markers become numbered gaps.
	 *
	 * @param array<string, mixed> $question Delivered question payload.
	 * @return string HTML.
	 */
	public static function text( array $question ): string {
		$html = wp_kses_post( (string) ( $question['text'] ?? '' ) );
		if ( QuestionTypes::CLOZE !== ( $question['type'] ?? '' ) ) {
			return $html;
		}
		foreach ( array_values( (array) ( $question['gaps'] ?? array() ) ) as $i => $gap ) {
			$mark = '<span class="ccap-gap">' . esc_html( self::gap_label( $i + 1 ) ) . '</span>';
			$html = str_replace( '{{' . esc_html( (string) $gap ) . '}}', $mark, $html );
		}
		return $html;
	}

	/**
	 * Answer fields, or null when the type is not supported here.
	 *
	 * @param array<string, mixed> $question Delivered question payload (`type`, `options`, `left`/`right`, `items`, `gaps`, `unit`).
	 * @param string               $name     Field name, e.g. `answer[3]`.
	 * @param string               $id       ID prefix (unique on the page).
	 * @param mixed                $answer   Stored answer (null = none).
	 * @param string               $content  Content language attributes for option texts.
	 * @return string|null
	 */
	public static function fields( array $question, string $name, string $id, $answer, string $content = '' ): ?string {
		$type = (string) ( $question['type'] ?? '' );
		switch ( $type ) {
			case QuestionTypes::SINGLE:
			case QuestionTypes::TRUE_FALSE:
			case QuestionTypes::MULTI:
				$multi  = QuestionTypes::MULTI === $type;
				$chosen = array_map( 'strval', (array) $answer );
				$html   = '<ul class="ccap-options"' . $content . '>';
				foreach ( (array) ( $question['options'] ?? array() ) as $option ) {
					$key   = (string) $option['key'];
					$oid   = $id . '-' . sanitize_html_class( $key );
					$html .= '<li><input type="' . ( $multi ? 'checkbox' : 'radio' ) . '" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name . ( $multi ? '[]' : '' ) ) . '" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $chosen, true ), true, false ) . '> <label for="' . esc_attr( $oid ) . '">' . esc_html( (string) $option['text'] ) . '</label></li>';
				}
				return $html . '</ul>';
			case QuestionTypes::NUMERIC:
				$unit  = (string) ( $question['unit'] ?? '' );
				$label = '' === $unit ? __( 'Your answer', 'course-captain' ) : sprintf(
					/* translators: %s: unit, e.g. "km" */
					__( 'Your answer (in %s)', 'course-captain' ),
					$unit
				);
				return '<p><label for="' . esc_attr( $id ) . '-input">' . esc_html( $label ) . '</label> <input type="text" inputmode="decimal" autocomplete="off" id="' . esc_attr( $id ) . '-input" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $answer ) ? (string) $answer : '' ) . '"></p>';
			case QuestionTypes::MATCHING:
				$given = (array) $answer;
				$html  = '<ul class="ccap-options"' . $content . '>';
				foreach ( (array) ( $question['left'] ?? array() ) as $left ) {
					$lkey  = (string) $left['key'];
					$sid   = $id . '-' . sanitize_html_class( $lkey );
					$html .= '<li><label for="' . esc_attr( $sid ) . '">' . esc_html( (string) $left['text'] ) . '</label> <select id="' . esc_attr( $sid ) . '" name="' . esc_attr( $name . '[' . $lkey . ']' ) . '"><option value="">' . esc_html__( '— choose —', 'course-captain' ) . '</option>';
					foreach ( (array) ( $question['right'] ?? array() ) as $right ) {
						$html .= '<option value="' . esc_attr( (string) $right['key'] ) . '"' . selected( (string) ( $given[ $lkey ] ?? '' ), (string) $right['key'], false ) . '>' . esc_html( (string) $right['text'] ) . '</option>';
					}
					$html .= '</select></li>';
				}
				return $html . '</ul>';
			case QuestionTypes::ORDERING:
				$items = array_values( (array) ( $question['items'] ?? array() ) );
				$count = count( $items );
				$rank  = array();
				foreach ( self::ordering_list( $answer ) as $i => $key ) {
					$rank[ $key ] = $i + 1;
				}
				$html = '<ol class="ccap-options ccap-ordering"' . $content . '>';
				foreach ( $items as $item ) {
					$key   = (string) $item['key'];
					$sid   = $id . '-' . sanitize_html_class( $key );
					$html .= '<li><label for="' . esc_attr( $sid ) . '">' . esc_html( (string) $item['text'] ) . '</label> <select id="' . esc_attr( $sid ) . '" name="' . esc_attr( $name . '[' . $key . ']' ) . '"><option value="">' . esc_html__( '— position —', 'course-captain' ) . '</option>';
					for ( $p = 1; $p <= $count; $p++ ) {
						$html .= '<option value="' . $p . '"' . selected( $rank[ $key ] ?? 0, $p, false ) . '>' . esc_html(
							sprintf(
								/* translators: 1: position, 2: number of items */
								__( 'Position %1$s of %2$s', 'course-captain' ),
								number_format_i18n( $p ),
								number_format_i18n( $count )
							)
						) . '</option>';
					}
					$html .= '</select></li>';
				}
				return $html . '</ol>';
			case QuestionTypes::CLOZE:
				$given = is_array( $answer ) ? $answer : array();
				$html  = '<ul class="ccap-options ccap-cloze">';
				foreach ( array_values( (array) ( $question['gaps'] ?? array() ) ) as $i => $gap ) {
					$gap   = (string) $gap;
					$sid   = $id . '-gap-' . sanitize_html_class( $gap );
					$html .= '<li><label for="' . esc_attr( $sid ) . '">' . esc_html( self::gap_label( $i + 1 ) ) . '</label> <input type="text" autocomplete="off" id="' . esc_attr( $sid ) . '" name="' . esc_attr( $name . '[' . $gap . ']' ) . '" value="' . esc_attr( is_scalar( $given[ $gap ] ?? null ) ? (string) $given[ $gap ] : '' ) . '"></li>';
				}
				return $html . '</ul>';
			default:
				return null;
		}
	}

	/**
	 * Canonical form of a posted or stored answer. Ordering: a stored list
	 * stays as is; posted positions become the key list, all empty becomes
	 * `[]` (unanswered); incomplete or repeated positions give null.
	 *
	 * @param string $type   Question type.
	 * @param mixed  $answer Answer.
	 * @return mixed
	 */
	public static function normalize( string $type, $answer ) {
		switch ( $type ) {
			case QuestionTypes::MULTI:
				$keys = array_values( array_filter( array_map( 'strval', (array) $answer ), static fn( string $k ): bool => '' !== $k ) );
				sort( $keys );
				return $keys;
			case QuestionTypes::MATCHING:
				$map = array();
				foreach ( (array) $answer as $left => $right ) {
					if ( is_scalar( $right ) && '' !== (string) $right ) {
						$map[ (string) $left ] = (string) $right;
					}
				}
				ksort( $map );
				return $map;
			case QuestionTypes::ORDERING:
				if ( ! is_array( $answer ) || array() === $answer ) {
					return array();
				}
				if ( array_is_list( $answer ) ) {
					return array_map( 'strval', $answer );
				}
				$ranks = array();
				foreach ( $answer as $key => $rank ) {
					$rank = is_scalar( $rank ) ? trim( (string) $rank ) : '';
					if ( '' !== $rank ) {
						$ranks[ (string) $key ] = $rank;
					}
				}
				if ( array() === $ranks ) {
					return array();
				}
				$expected = range( 1, count( $answer ) );
				$given    = array_map( 'intval', array_values( $ranks ) );
				sort( $given );
				if ( count( $ranks ) !== count( $answer ) || $given !== $expected ) {
					return null;
				}
				asort( $ranks, SORT_NUMERIC );
				return array_map( 'strval', array_keys( $ranks ) );
			case QuestionTypes::CLOZE:
				$map = array();
				foreach ( (array) $answer as $gap => $text ) {
					$text = is_scalar( $text ) ? trim( (string) $text ) : '';
					if ( '' !== $text ) {
						$map[ (string) $gap ] = $text;
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
	 * Whether a normalized answer is empty (nothing chosen or entered).
	 *
	 * @param mixed $normalized Normalized answer.
	 * @return bool
	 */
	public static function is_empty( $normalized ): bool {
		return null === $normalized || '' === $normalized || array() === $normalized;
	}

	/**
	 * Stored ordering answer as key list.
	 *
	 * @param mixed $answer Stored answer.
	 * @return list<string>
	 */
	private static function ordering_list( $answer ): array {
		return is_array( $answer ) && array_is_list( $answer ) ? array_map( 'strval', $answer ) : array();
	}

	/**
	 * "Gap n".
	 *
	 * @param int $number Number.
	 * @return string
	 */
	private static function gap_label( int $number ): string {
		/* translators: %s: number of the gap */
		return sprintf( __( 'Gap %s', 'course-captain' ), number_format_i18n( $number ) );
	}
}
