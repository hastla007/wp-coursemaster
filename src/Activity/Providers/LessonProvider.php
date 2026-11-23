<?php
/**
 * Text/block lesson (R0-08 §2.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Completion `manual_confirm` (default) or `view` (opened at least
 * `min_seconds`, measured server-side between two interactions).
 */
final class LessonProvider extends AbstractProvider {

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function type(): string {
		return 'lesson';
	}

	/**
	 * Capabilities.
	 *
	 * @return array<string, bool|string>
	 */
	public function capabilities(): array {
		return array( 'feeds_mastery' => true ) + parent::capabilities();
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
				'min_seconds' => array(
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => 86400,
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
		return array( 'manual_confirm', 'view' );
	}

	/**
	 * Completion.
	 *
	 * @param array<string, mixed> $rule     Rule.
	 * @param array<string, mixed> $progress Progress.
	 * @return string
	 */
	public function evaluate_completion( array $rule, array $progress ): string {
		if ( 'view' === ( $rule['type'] ?? '' ) ) {
			$min = (int) ( $rule['params']['min_seconds'] ?? 0 );
			return ! empty( $progress['started'] ) && (int) ( $progress['view_seconds'] ?? 0 ) >= $min ? 'completed' : self::started_or_not( $progress );
		}
		return self::by_flag( $progress, 'confirmed' );
	}
}
