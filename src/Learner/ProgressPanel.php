<?php
/**
 * Three progress dimensions as HTML (learning area and trainer view).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the read model of `Progress\ProgressDimensions` (no own logic):
 * one section per dimension, each with its value text and the rule
 * ("why this value"). States are words, never colour only (R0-15).
 */
final class ProgressPanel {

	/**
	 * Panel HTML.
	 *
	 * @param array<string, mixed> $dimensions Result of `ProgressDimensions::build()`.
	 * @param string               $id         Unique ID prefix.
	 * @param int                  $level      Heading level of the panel (2…5).
	 * @return string
	 */
	public static function html( array $dimensions, string $id, int $level = 2 ): string {
		$level = max( 2, min( 5, $level ) );
		$sub   = $level + 1;
		$id    = sanitize_html_class( $id );
		$html  = '<section class="ccap-dimensions" aria-labelledby="' . esc_attr( $id ) . '-heading"><h' . $level . ' id="' . esc_attr( $id ) . '-heading">' . esc_html__( 'Progress overview', 'course-captain' ) . '</h' . $level . '>';
		$html .= '<p>' . esc_html__( 'Worked through, mastered and evidenced are counted separately; none of them changes the others.', 'course-captain' ) . '</p>';
		$parts = array(
			'completion' => __( 'Worked through', 'course-captain' ),
			'mastery'    => __( 'Mastered', 'course-captain' ),
			'competency' => __( 'Competency evidence', 'course-captain' ),
		);
		foreach ( $parts as $key => $title ) {
			$part  = (array) ( $dimensions[ $key ] ?? array() );
			$base  = $id . '-' . $key;
			$html .= '<section class="ccap-dimension ccap-dimension-' . esc_attr( $key ) . '" aria-labelledby="' . esc_attr( $base ) . '"><h' . $sub . ' id="' . esc_attr( $base ) . '">' . esc_html( $title ) . '</h' . $sub . '>';
			$html .= '<p class="ccap-dimension-value">' . esc_html( (string) ( $part['summary'] ?? '' ) ) . '</p>';
			if ( 'competency' === $key ) {
				$html .= self::objectives( array_values( (array) ( $part['objectives'] ?? array() ) ) );
			}
			$html .= '<p class="ccap-dimension-rule"><strong>' . esc_html__( 'Why this value:', 'course-captain' ) . '</strong> ' . esc_html( (string) ( $part['rule'] ?? '' ) ) . '</p></section>';
		}
		return $html . '</section>';
	}

	/**
	 * Objective table.
	 *
	 * @param array<int, mixed> $objectives Objectives.
	 * @return string
	 */
	private static function objectives( array $objectives ): string {
		if ( array() === $objectives ) {
			return '';
		}
		$html = '<table class="ccap-objectives widefat striped"><caption>' . esc_html__( 'Learning objectives and evidence', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Learning objective', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Evidence', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( $objectives as $objective ) {
			$objective = (array) $objective;
			$html     .= '<tr><th scope="row" dir="auto">' . esc_html( (string) ( $objective['title'] ?? '' ) ) . '</th><td class="ccap-objective-' . esc_attr( (string) ( $objective['state'] ?? '' ) ) . '">' . esc_html( (string) ( $objective['state_label'] ?? '' ) ) . '</td><td>' . esc_html( (string) ( $objective['summary'] ?? '' ) ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}
}
