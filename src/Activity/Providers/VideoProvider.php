<?php
/**
 * Video/audio lesson (R0-08 §2.4; R0-05 GM-008, GM-011…GM-014).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Completion `manual_confirm` (default, LB parity: the client reports) or
 * `position_ratio` (client self-report, marked as such).
 */
final class VideoProvider extends AbstractProvider {

	/** Maximum length of a transcript text (characters). */
	public const TRANSCRIPT_MAX = 100000;

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function type(): string {
		return 'video';
	}

	/**
	 * Capabilities.
	 *
	 * @return array<string, bool|string>
	 */
	public function capabilities(): array {
		return array(
			'feeds_mastery'          => true,
			'needs_external_consent' => true,
		) + parent::capabilities();
	}

	/**
	 * Settings schema.
	 *
	 * @return array<string, mixed>
	 */
	public function settings_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'provider'         => array(
					'type' => 'string',
					'enum' => array( 'url', 'youtube', 'vimeo', 'media' ),
				),
				'url'              => array(
					'type'   => 'string',
					'format' => 'https-url',
				),
				'media_uid'        => array(
					'type'    => 'string',
					'pattern' => '^[0-9a-f-]{36}$',
				),
				'duration_seconds' => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'subtitles'        => array(
					'type' => 'string',
					'enum' => array( 'off', 'vtt' ),
				),
				'subtitle_url'     => array(
					'type'   => 'string',
					'format' => 'https-url',
				),
				'placeholder'      => array( 'type' => 'boolean' ),
				// SET-CC-18 (A11Y-090): transcript as text and/or link; missing → VAL-144.
				'transcript'       => array(
					'type'      => 'string',
					'maxLength' => self::TRANSCRIPT_MAX,
				),
				'transcript_url'   => array(
					'type'   => 'string',
					'format' => 'https-url',
				),
			),
		);
	}

	/**
	 * Rule types.
	 *
	 * @return list<string>
	 */
	public function completion_rule_types(): array {
		return array( 'manual_confirm', 'position_ratio' );
	}

	/**
	 * Completion.
	 *
	 * @param array<string, mixed> $rule     Rule.
	 * @param array<string, mixed> $progress Progress.
	 * @return string
	 */
	public function evaluate_completion( array $rule, array $progress ): string {
		if ( 'position_ratio' === ( $rule['type'] ?? '' ) ) {
			$duration = (int) ( $progress['duration_seconds'] ?? 0 );
			$ratio    = (float) ( $rule['params']['ratio'] ?? 0.9 );
			return $duration > 0 && (int) ( $progress['position_seconds'] ?? 0 ) >= (int) ceil( $duration * $ratio ) ? 'completed' : self::started_or_not( $progress );
		}
		return self::by_flag( $progress, 'confirmed' );
	}
}
