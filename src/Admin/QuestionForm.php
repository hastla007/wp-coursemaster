<?php
/**
 * Question editor form ↔ revision data (A-07, R0-08 §3.1, R0-15 A11Y-022).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Question\QuestionTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the revision data of {@see QuestionTypes} from the POST fields of
 * the editor and renders the type-specific fields. Only the fields of the
 * chosen type are shown (the type is chosen in a first step, so the form
 * works without JavaScript). Every field has a visible label; groups are
 * `fieldset`s with a `legend`.
 *
 * Question image (B-05, R0-22 VAL-140/141): the image is part of the
 * question text as `<figure class="ccap-question-image"><img …></figure>`
 * so the learner views render it with the text; `alt=""` is written only
 * when the author ticks "decorative" (then `data-ccap-decorative="1"`).
 */
final class QuestionForm {

	/**
	 * Marker class of the editor-managed question image.
	 */
	public const IMAGE_CLASS = 'ccap-question-image';

	/**
	 * Type labels (visible text, no codes).
	 *
	 * @return array<string, string>
	 */
	public static function type_labels(): array {
		return array(
			QuestionTypes::SINGLE     => __( 'One correct answer', 'course-captain' ),
			QuestionTypes::MULTI      => __( 'Several correct answers', 'course-captain' ),
			QuestionTypes::TRUE_FALSE => __( 'True or false', 'course-captain' ),
			QuestionTypes::OPEN_TEXT  => __( 'Free text with model answer', 'course-captain' ),
			QuestionTypes::FLASHCARD  => __( 'Flashcard', 'course-captain' ),
			QuestionTypes::NUMERIC    => __( 'Number with tolerance', 'course-captain' ),
			QuestionTypes::MATCHING   => __( 'Matching', 'course-captain' ),
			QuestionTypes::ORDERING   => __( 'Ordering', 'course-captain' ),
			QuestionTypes::CLOZE      => __( 'Fill in the blanks', 'course-captain' ),
		);
	}

	/**
	 * Revision data from the submitted form.
	 *
	 * @param array<string, mixed> $post Unslashed POST data.
	 * @return array<string, mixed> `question_type`, `question_text`, `settings`, `answers`, `feedback_*`, `image_required`, plus `image` (attachment, alt, decorative) for the screen.
	 */
	public static function from_post( array $post ): array {
		$type     = sanitize_key( (string) ( $post['question_type'] ?? '' ) );
		$settings = array();
		$answers  = array();
		$text     = trim( wp_kses_post( (string) ( $post['question_text'] ?? '' ) ) );
		$lines    = static function ( $value ): array {
			$parts = preg_split( '/\R/u', (string) $value );
			return array_values( array_filter( array_map( 'trim', false === $parts ? array() : $parts ), static fn( string $l ): bool => '' !== $l ) );
		};
		switch ( $type ) {
			case QuestionTypes::SINGLE:
			case QuestionTypes::MULTI:
				$letters = range( 'A', 'Z' );
				$i       = 0;
				$correct = QuestionTypes::SINGLE === $type ? (string) ( $post['correct_single'] ?? '' ) : '';
				foreach ( array_values( (array) ( $post['answers'] ?? array() ) ) as $row => $answer ) {
					$answer = (array) $answer;
					$label  = trim( sanitize_text_field( (string) ( $answer['text'] ?? '' ) ) );
					if ( '' === $label || $i >= count( $letters ) ) {
						continue;
					}
					$answers[] = array(
						'key'     => $letters[ $i ],
						'text'    => $label,
						'correct' => QuestionTypes::SINGLE === $type ? (string) $row === $correct : ! empty( $answer['correct'] ),
						'sort'    => $i,
					);
					++$i;
				}
				if ( empty( $post['shuffle'] ) ) {
					$settings['shuffle'] = false;
				}
				break;
			case QuestionTypes::TRUE_FALSE:
				$true      = 'true' === (string) ( $post['tf_correct'] ?? '' );
				$false     = 'false' === (string) ( $post['tf_correct'] ?? '' );
				$answers[] = array(
					'key'     => 'T',
					'text'    => __( 'True', 'course-captain' ),
					'correct' => $true,
					'sort'    => 0,
				);
				$answers[] = array(
					'key'     => 'F',
					'text'    => __( 'False', 'course-captain' ),
					'correct' => $false,
					'sort'    => 1,
				);
				break;
			case QuestionTypes::OPEN_TEXT:
				$answers[] = array(
					'key'     => 'MODEL',
					'text'    => trim( sanitize_textarea_field( (string) ( $post['model_answer'] ?? '' ) ) ),
					'correct' => true,
					'sort'    => 0,
				);
				break;
			case QuestionTypes::FLASHCARD:
				$settings['back'] = trim( sanitize_textarea_field( (string) ( $post['back'] ?? '' ) ) );
				break;
			case QuestionTypes::NUMERIC:
				$settings = array(
					'target'         => str_replace( ',', '.', trim( sanitize_text_field( (string) ( $post['target'] ?? '' ) ) ) ),
					'tolerance_type' => 'relative' === ( $post['tolerance_type'] ?? '' ) ? 'relative' : 'absolute',
					'tolerance'      => str_replace( ',', '.', trim( sanitize_text_field( (string) ( $post['tolerance'] ?? '' ) ) ) ),
					'unit'           => trim( sanitize_text_field( (string) ( $post['unit'] ?? '' ) ) ),
				);
				break;
			case QuestionTypes::MATCHING:
				$pairs = array();
				foreach ( (array) ( $post['pairs'] ?? array() ) as $pair ) {
					$pair  = (array) $pair;
					$left  = trim( sanitize_text_field( (string) ( $pair['left'] ?? '' ) ) );
					$right = trim( sanitize_text_field( (string) ( $pair['right'] ?? '' ) ) );
					if ( '' !== $left || '' !== $right ) {
						$pairs[] = array(
							'left'  => $left,
							'right' => $right,
						);
					}
				}
				$settings['pairs'] = $pairs;
				$distractors       = $lines( sanitize_textarea_field( (string) ( $post['distractors'] ?? '' ) ) );
				if ( array() !== $distractors ) {
					$settings['distractors'] = $distractors;
				}
				break;
			case QuestionTypes::ORDERING:
				$settings['items'] = array_map( static fn( string $t ): array => array( 'text' => $t ), $lines( sanitize_textarea_field( (string) ( $post['items'] ?? '' ) ) ) );
				break;
			case QuestionTypes::CLOZE:
				$gaps = array();
				foreach ( (array) ( $post['gaps'] ?? array() ) as $gap ) {
					$gap      = (array) $gap;
					$id       = trim( sanitize_text_field( (string) ( $gap['id'] ?? '' ) ) );
					$accepted = array_values( array_filter( array_map( 'trim', explode( '|', sanitize_text_field( (string) ( $gap['accepted'] ?? '' ) ) ) ), static fn( string $a ): bool => '' !== $a ) );
					if ( '' !== $id || array() !== $accepted ) {
						$gaps[] = array(
							'id'       => $id,
							'accepted' => $accepted,
						);
					}
				}
				$settings['gaps'] = $gaps;
				if ( ! empty( $post['case_sensitive'] ) ) {
					$settings['case_sensitive'] = true;
				}
				break;
		}
		if ( in_array( $type, array( QuestionTypes::MULTI, QuestionTypes::MATCHING, QuestionTypes::CLOZE ), true ) && QuestionTypes::PROPORTIONAL_PENALTY === ( $post['scoring'] ?? '' ) ) {
			$settings['scoring'] = QuestionTypes::PROPORTIONAL_PENALTY;
		}
		$image = array(
			'attachment' => absint( $post['image_id'] ?? 0 ),
			'alt'        => trim( sanitize_text_field( (string) ( $post['image_alt'] ?? '' ) ) ),
			'decorative' => ! empty( $post['image_decorative'] ),
		);
		$fc    = trim( wp_kses_post( (string) ( $post['feedback_correct'] ?? '' ) ) );
		$fi    = trim( wp_kses_post( (string) ( $post['feedback_incorrect'] ?? '' ) ) );
		return array(
			'question_type'      => $type,
			'question_text'      => self::compose_text( $text, $image ),
			'settings'           => $settings,
			'answers'            => $answers,
			'feedback_correct'   => '' === $fc ? null : $fc,
			'feedback_incorrect' => '' === $fi ? null : $fi,
			'image_required'     => ! empty( $post['image_required'] ) && $image['attachment'] > 0,
			'image'              => $image,
		);
	}

	/**
	 * Field errors before saving (labels for the author, R0-08 §3.1 plus
	 * the alt-text rule of the editor, A11Y-110).
	 *
	 * @param array<string, mixed> $data Data from {@see from_post()}.
	 * @return array<string, string> Field ID → message.
	 */
	public static function field_errors( array $data ): array {
		$errors = array();
		if ( ! in_array( $data['question_type'], QuestionTypes::ALL, true ) ) {
			$errors['ccap-q-type'] = __( 'Choose a question type.', 'course-captain' );
			return $errors;
		}
		$image = (array) $data['image'];
		if ( $image['attachment'] > 0 ) {
			if ( ! wp_attachment_is_image( (int) $image['attachment'] ) ) {
				$errors['ccap-q-image'] = __( 'The chosen file is not an image.', 'course-captain' );
			} elseif ( '' === $image['alt'] && ( ! $image['decorative'] || ! empty( $data['image_required'] ) ) ) {
				$errors['ccap-q-image-alt'] = empty( $data['image_required'] )
					? __( 'Describe the image in the alternative text or mark it as decorative.', 'course-captain' )
					: __( 'The question cannot be solved without the image: describe it in the alternative text.', 'course-captain' );
			}
		}
		return $errors;
	}

	/**
	 * Messages for the type validation codes of {@see QuestionTypes::validate()}.
	 *
	 * @param array $codes Codes.
	 * @phpstan-param list<string> $codes
	 * @return list<string>
	 */
	public static function messages( array $codes ): array {
		$texts = array(
			'text_missing'         => __( 'Enter the question text.', 'course-captain' ),
			'scoring_not_allowed'  => __( 'Partial points are not possible for this question type.', 'course-captain' ),
			'answers_count'        => __( 'Enter at least two answers (at most 26).', 'course-captain' ),
			'exactly_one_correct'  => __( 'Mark exactly one answer as correct.', 'course-captain' ),
			'at_least_one_correct' => __( 'Mark at least one answer as correct.', 'course-captain' ),
			'answer_key_invalid'   => __( 'The answers could not be numbered. Remove empty rows and save again.', 'course-captain' ),
			'answer_key_duplicate' => __( 'The answers could not be numbered. Remove empty rows and save again.', 'course-captain' ),
			'true_false_shape'     => __( 'Choose whether the statement is true or false.', 'course-captain' ),
			'model_answer'         => __( 'Enter a model answer.', 'course-captain' ),
			'back_missing'         => __( 'Enter the back of the card.', 'course-captain' ),
			'target_invalid'       => __( 'Enter the correct value as a number, for example 12.5.', 'course-captain' ),
			'tolerance_invalid'    => __( 'Enter the allowed deviation as a number of at least 0 (relative: at most 100 %).', 'course-captain' ),
			'pairs_count'          => __( 'Enter at least two pairs (at most 26 entries on the right).', 'course-captain' ),
			'pairs_duplicate'      => __( 'Every pair needs a left entry, and left entries must differ.', 'course-captain' ),
			'items_count'          => __( 'Enter at least two entries in the correct order (at most 26).', 'course-captain' ),
			'items_duplicate'      => __( 'The entries must differ from each other.', 'course-captain' ),
			'gaps_invalid'         => __( 'Enter at least one blank; each blank needs its own label.', 'course-captain' ),
			'gap_answers'          => __( 'Enter at least one accepted answer for every blank.', 'course-captain' ),
			'unknown_type'         => __( 'Choose a question type.', 'course-captain' ),
		);
		return array_values( array_unique( array_map( static fn( string $c ): string => $texts[ $c ] ?? $c, $codes ) ) );
	}

	/**
	 * Question text with the editor-managed image in front.
	 *
	 * @param string                                                $text  Text without image.
	 * @param array{attachment: int, alt: string, decorative: bool} $image Image.
	 * @return string
	 */
	public static function compose_text( string $text, array $image ): string {
		if ( $image['attachment'] <= 0 ) {
			return $text;
		}
		$src = (string) wp_get_attachment_url( $image['attachment'] );
		$alt = $image['decorative'] && '' === $image['alt'] ? '' : $image['alt'];
		$img = '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" data-ccap-attachment="' . (int) $image['attachment'] . '"' . ( '' === $alt && $image['decorative'] ? ' data-ccap-decorative="1"' : '' ) . '>';
		return '<figure class="' . self::IMAGE_CLASS . '">' . $img . '</figure>' . $text;
	}

	/**
	 * Split a stored question text into the plain text and the editor image.
	 *
	 * @param string $html Stored question text.
	 * @return array{text: string, image: array{attachment: int, alt: string, decorative: bool}}
	 */
	public static function split_text( string $html ): array {
		$image = array(
			'attachment' => 0,
			'alt'        => '',
			'decorative' => false,
		);
		if ( 1 === preg_match( '#^<figure class="' . self::IMAGE_CLASS . '">(<img[^>]*>)</figure>#', $html, $m ) ) {
			$tag   = $m[1];
			$html  = substr( $html, strlen( $m[0] ) );
			$image = array(
				'attachment' => 1 === preg_match( '/data-ccap-attachment="(\d+)"/', $tag, $a ) ? (int) $a[1] : 0,
				'alt'        => 1 === preg_match( '/alt="([^"]*)"/', $tag, $b ) ? html_entity_decode( $b[1], ENT_QUOTES, 'UTF-8' ) : '',
				'decorative' => str_contains( $tag, 'data-ccap-decorative="1"' ),
			);
		}
		return array(
			'text'  => $html,
			'image' => $image,
		);
	}

	/**
	 * Type-specific fields.
	 *
	 * @param string               $type     Type.
	 * @param array<string, mixed> $revision Revision data (may be empty).
	 * @return string
	 */
	public static function type_fields( string $type, array $revision ): string {
		$settings = (array) ( $revision['settings'] ?? array() );
		$answers  = array_values( (array) ( $revision['answers'] ?? array() ) );
		$html     = '';
		switch ( $type ) {
			case QuestionTypes::SINGLE:
			case QuestionTypes::MULTI:
				$single = QuestionTypes::SINGLE === $type;
				$rows   = min( QuestionTypes::MAX_OPTIONS, max( 4, count( $answers ) + 2 ) );
				$html  .= '<fieldset class="ccap-q-answers" id="ccap-q-answers"><legend>' . esc_html( $single ? __( 'Answers (mark the one correct answer)', 'course-captain' ) : __( 'Answers (mark every correct answer)', 'course-captain' ) ) . '</legend>';
				$html  .= '<p class="description">' . esc_html__( 'Empty rows are ignored. The order here is the order learners see when answers are not shuffled.', 'course-captain' ) . '</p>';
				$html  .= '<table class="widefat"><thead><tr><th scope="col">' . esc_html__( 'Answer', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Correct', 'course-captain' ) . '</th></tr></thead><tbody>';
				for ( $i = 0; $i < $rows; $i++ ) {
					$answer = (array) ( $answers[ $i ] ?? array() );
					$id     = 'ccap-q-answer-' . $i;
					/* translators: %d: answer number */
					$label = sprintf( __( 'Answer %d', 'course-captain' ), $i + 1 );
					$html .= '<tr><td><label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><input id="' . esc_attr( $id ) . '" type="text" class="large-text" name="answers[' . $i . '][text]" value="' . esc_attr( (string) ( $answer['text'] ?? '' ) ) . '"></td><td>';
					if ( $single ) {
						$html .= '<label><input type="radio" name="correct_single" value="' . $i . '"' . checked( ! empty( $answer['correct'] ), true, false ) . '> ' . esc_html( sprintf( /* translators: %d: answer number */ __( 'Answer %d is correct', 'course-captain' ), $i + 1 ) ) . '</label>';
					} else {
						$html .= '<label><input type="checkbox" name="answers[' . $i . '][correct]" value="1"' . checked( ! empty( $answer['correct'] ), true, false ) . '> ' . esc_html( sprintf( /* translators: %d: answer number */ __( 'Answer %d is correct', 'course-captain' ), $i + 1 ) ) . '</label>';
					}
					$html .= '</td></tr>';
				}
				$html .= '</tbody></table>';
				$html .= '<p><label><input type="checkbox" name="shuffle" value="1"' . checked( false !== ( $settings['shuffle'] ?? true ), true, false ) . '> ' . esc_html__( 'Shuffle the answers for every learner', 'course-captain' ) . '</label></p></fieldset>';
				break;
			case QuestionTypes::TRUE_FALSE:
				$correct = '';
				foreach ( $answers as $answer ) {
					if ( ! empty( $answer['correct'] ) ) {
						$correct = 'T' === ( $answer['key'] ?? '' ) ? 'true' : 'false';
					}
				}
				$html .= '<fieldset id="ccap-q-answers"><legend>' . esc_html__( 'The statement is', 'course-captain' ) . '</legend>';
				$html .= '<label><input type="radio" name="tf_correct" value="true"' . checked( 'true', $correct, false ) . '> ' . esc_html__( 'true', 'course-captain' ) . '</label><br>';
				$html .= '<label><input type="radio" name="tf_correct" value="false"' . checked( 'false', $correct, false ) . '> ' . esc_html__( 'false', 'course-captain' ) . '</label></fieldset>';
				break;
			case QuestionTypes::OPEN_TEXT:
				$html .= '<p><label for="ccap-q-model">' . esc_html__( 'Model answer (shown to learners after answering)', 'course-captain' ) . '</label><textarea id="ccap-q-model" name="model_answer" rows="4" class="large-text">' . esc_textarea( (string) ( $answers[0]['text'] ?? '' ) ) . '</textarea></p>';
				break;
			case QuestionTypes::FLASHCARD:
				$html .= '<p><label for="ccap-q-back">' . esc_html__( 'Back of the card', 'course-captain' ) . '</label><textarea id="ccap-q-back" name="back" rows="4" class="large-text">' . esc_textarea( (string) ( $settings['back'] ?? '' ) ) . '</textarea></p>';
				break;
			case QuestionTypes::NUMERIC:
				$kind  = (string) ( $settings['tolerance_type'] ?? 'absolute' );
				$html .= '<fieldset id="ccap-q-answers"><legend>' . esc_html__( 'Correct value', 'course-captain' ) . '</legend>';
				$html .= '<p><label for="ccap-q-target">' . esc_html__( 'Correct value (number, decimal point or comma)', 'course-captain' ) . '</label><br><input id="ccap-q-target" type="text" inputmode="decimal" name="target" value="' . esc_attr( (string) ( $settings['target'] ?? '' ) ) . '"></p>';
				$html .= '<p><label for="ccap-q-tolerance">' . esc_html__( 'Allowed deviation', 'course-captain' ) . '</label><br><input id="ccap-q-tolerance" type="text" inputmode="decimal" name="tolerance" value="' . esc_attr( (string) ( $settings['tolerance'] ?? '0' ) ) . '"> ';
				$html .= '<label for="ccap-q-tolerance-type" class="screen-reader-text">' . esc_html__( 'Kind of deviation', 'course-captain' ) . '</label><select id="ccap-q-tolerance-type" name="tolerance_type"><option value="absolute"' . selected( $kind, 'absolute', false ) . '>' . esc_html__( 'in the unit (absolute)', 'course-captain' ) . '</option><option value="relative"' . selected( $kind, 'relative', false ) . '>' . esc_html__( 'in percent (relative)', 'course-captain' ) . '</option></select></p>';
				$html .= '<p><label for="ccap-q-unit">' . esc_html__( 'Unit (optional, shown next to the input)', 'course-captain' ) . '</label><br><input id="ccap-q-unit" type="text" name="unit" value="' . esc_attr( (string) ( $settings['unit'] ?? '' ) ) . '"></p></fieldset>';
				break;
			case QuestionTypes::MATCHING:
				$pairs = array_values( (array) ( $settings['pairs'] ?? array() ) );
				$rows  = min( QuestionTypes::MAX_OPTIONS, max( 3, count( $pairs ) + 2 ) );
				$html .= '<fieldset id="ccap-q-answers"><legend>' . esc_html__( 'Pairs (left entry and its match on the right)', 'course-captain' ) . '</legend><table class="widefat"><thead><tr><th scope="col">' . esc_html__( 'Left', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Right (match)', 'course-captain' ) . '</th></tr></thead><tbody>';
				for ( $i = 0; $i < $rows; $i++ ) {
					$pair  = (array) ( $pairs[ $i ] ?? array() );
					$html .= '<tr><td><label class="screen-reader-text" for="ccap-q-left-' . $i . '">' . esc_html( sprintf( /* translators: %d: pair number */ __( 'Pair %d left', 'course-captain' ), $i + 1 ) ) . '</label><input id="ccap-q-left-' . $i . '" type="text" class="regular-text" name="pairs[' . $i . '][left]" value="' . esc_attr( (string) ( $pair['left'] ?? '' ) ) . '"></td>';
					$html .= '<td><label class="screen-reader-text" for="ccap-q-right-' . $i . '">' . esc_html( sprintf( /* translators: %d: pair number */ __( 'Pair %d right', 'course-captain' ), $i + 1 ) ) . '</label><input id="ccap-q-right-' . $i . '" type="text" class="regular-text" name="pairs[' . $i . '][right]" value="' . esc_attr( (string) ( $pair['right'] ?? '' ) ) . '"></td></tr>';
				}
				$html .= '</tbody></table>';
				$html .= '<p><label for="ccap-q-distractors">' . esc_html__( 'Extra wrong entries on the right (optional, one per line)', 'course-captain' ) . '</label><textarea id="ccap-q-distractors" name="distractors" rows="3" class="large-text">' . esc_textarea( implode( "\n", array_map( 'strval', (array) ( $settings['distractors'] ?? array() ) ) ) ) . '</textarea></p></fieldset>';
				break;
			case QuestionTypes::ORDERING:
				$items = array_map( static fn( $i ): string => (string) ( is_array( $i ) ? ( $i['text'] ?? '' ) : $i ), (array) ( $settings['items'] ?? array() ) );
				$html .= '<p><label for="ccap-q-items">' . esc_html__( 'Entries in the correct order (one per line; learners get them shuffled)', 'course-captain' ) . '</label><textarea id="ccap-q-items" name="items" rows="6" class="large-text">' . esc_textarea( implode( "\n", $items ) ) . '</textarea></p>';
				break;
			case QuestionTypes::CLOZE:
				$gaps  = array_values( (array) ( $settings['gaps'] ?? array() ) );
				$rows  = max( 3, count( $gaps ) + 1 );
				$html .= '<fieldset id="ccap-q-answers"><legend>' . esc_html__( 'Blanks', 'course-captain' ) . '</legend><p class="description">' . esc_html__( 'Mark each blank in the question text with its label in square brackets, for example [1]. Separate several accepted answers with a vertical bar |.', 'course-captain' ) . '</p><table class="widefat"><thead><tr><th scope="col">' . esc_html__( 'Label', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Accepted answers', 'course-captain' ) . '</th></tr></thead><tbody>';
				for ( $i = 0; $i < $rows; $i++ ) {
					$gap   = (array) ( $gaps[ $i ] ?? array() );
					$html .= '<tr><td><label class="screen-reader-text" for="ccap-q-gap-' . $i . '">' . esc_html( sprintf( /* translators: %d: blank number */ __( 'Blank %d label', 'course-captain' ), $i + 1 ) ) . '</label><input id="ccap-q-gap-' . $i . '" type="text" size="6" name="gaps[' . $i . '][id]" value="' . esc_attr( (string) ( $gap['id'] ?? ( 0 === $i && array() === $gaps ? '1' : '' ) ) ) . '"></td>';
					$html .= '<td><label class="screen-reader-text" for="ccap-q-gap-acc-' . $i . '">' . esc_html( sprintf( /* translators: %d: blank number */ __( 'Blank %d accepted answers', 'course-captain' ), $i + 1 ) ) . '</label><input id="ccap-q-gap-acc-' . $i . '" type="text" class="large-text" name="gaps[' . $i . '][accepted]" value="' . esc_attr( implode( '|', array_map( 'strval', (array) ( $gap['accepted'] ?? array() ) ) ) ) . '"></td></tr>';
				}
				$html .= '</tbody></table><p><label><input type="checkbox" name="case_sensitive" value="1"' . checked( ! empty( $settings['case_sensitive'] ), true, false ) . '> ' . esc_html__( 'Upper and lower case must match', 'course-captain' ) . '</label></p></fieldset>';
				break;
		}
		if ( in_array( $type, array( QuestionTypes::MULTI, QuestionTypes::MATCHING, QuestionTypes::CLOZE ), true ) ) {
			$scoring = (string) ( $settings['scoring'] ?? QuestionTypes::ALL_OR_NOTHING );
			$html   .= '<p><label for="ccap-q-scoring">' . esc_html__( 'Points', 'course-captain' ) . '</label><br><select id="ccap-q-scoring" name="scoring"><option value="' . esc_attr( QuestionTypes::ALL_OR_NOTHING ) . '"' . selected( $scoring, QuestionTypes::ALL_OR_NOTHING, false ) . '>' . esc_html__( 'Full points only when everything is correct', 'course-captain' ) . '</option><option value="' . esc_attr( QuestionTypes::PROPORTIONAL_PENALTY ) . '"' . selected( $scoring, QuestionTypes::PROPORTIONAL_PENALTY, false ) . '>' . esc_html__( 'Partial points (wrong choices are deducted)', 'course-captain' ) . '</option></select></p>';
		}
		return $html;
	}
}
