<?php
/**
 * Transcript of a video lesson as form fields in the lesson activity panel
 * (UI-F8 follow-up of SET-CC-18, A11Y-090, VAL-144).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Activity\Providers\VideoProvider;

defined( 'ABSPATH' ) || exit;

/**
 * `transcript` (text) and `transcript_url` (https link) were only reachable
 * through the settings JSON. The panel now shows them as a labelled
 * textarea and URL field; the JSON field no longer lists them. On save the
 * fields win over the JSON for video lessons (empty = removed); the
 * provider schema still validates the result (an http link is refused
 * like before).
 */
final class LessonMediaFields {

	/** Settings keys handled here. */
	public const KEYS = array( 'transcript', 'transcript_url' );

	/**
	 * Fields HTML.
	 *
	 * @param string               $type     Activity type.
	 * @param array<string, mixed> $settings Settings.
	 * @return string
	 */
	public static function html( string $type, array $settings ): string {
		$html  = '<fieldset class="ccap-transcript-fields"><legend>' . esc_html__( 'Transcript (video lessons)', 'course-captain' ) . '</legend>';
		$html .= '<p id="ccap-transcript-help" class="description">' . esc_html( 'video' === $type ? __( 'Enter the spoken text of the video, a link to it, or both. Without a transcript the course check shows a notice (or blocks publishing if the website requires transcripts).', 'course-captain' ) : __( 'Only used when the activity type is "video".', 'course-captain' ) ) . '</p>';
		$html .= '<p><label for="ccap-transcript-text">' . esc_html__( 'Transcript text', 'course-captain' ) . '</label><textarea id="ccap-transcript-text" name="ccap_transcript" rows="6" class="widefat" maxlength="' . (int) VideoProvider::TRANSCRIPT_MAX . '" aria-describedby="ccap-transcript-help">' . esc_textarea( (string) ( $settings['transcript'] ?? '' ) ) . '</textarea></p>';
		$html .= '<p><label for="ccap-transcript-url">' . esc_html__( 'Link to the transcript (https)', 'course-captain' ) . '</label><input id="ccap-transcript-url" type="url" name="ccap_transcript_url" class="widefat" value="' . esc_attr( (string) ( $settings['transcript_url'] ?? '' ) ) . '" aria-describedby="ccap-transcript-help"></p>';
		return $html . '</fieldset>';
	}

	/**
	 * Settings with the posted transcript fields applied (video lessons only).
	 *
	 * @param string               $type     Activity type.
	 * @param array<string, mixed> $settings Settings from the JSON field.
	 * @param array<string, mixed> $post     Unslashed POST data.
	 * @return array<string, mixed>
	 */
	public static function merge( string $type, array $settings, array $post ): array {
		if ( 'video' !== $type || ! array_key_exists( 'ccap_transcript', $post ) ) {
			return $settings;
		}
		$text = mb_substr( trim( sanitize_textarea_field( (string) $post['ccap_transcript'] ) ), 0, VideoProvider::TRANSCRIPT_MAX );
		$url  = trim( sanitize_text_field( (string) ( $post['ccap_transcript_url'] ?? '' ) ) );
		unset( $settings['transcript'], $settings['transcript_url'] );
		if ( '' !== $text ) {
			$settings['transcript'] = $text;
		}
		if ( '' !== $url ) {
			$settings['transcript_url'] = $url;
		}
		return $settings;
	}
}
