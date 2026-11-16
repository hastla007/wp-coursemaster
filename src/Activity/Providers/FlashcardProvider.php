<?php
/**
 * Flashcard set (E-21, R0-08 §2.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Modes `self_assessment` and `streak` (default streak 3); completion
 * `all_known` (default), `all_streak`, `ratio`.
 */
final class FlashcardProvider extends AbstractProvider {

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function type(): string {
		return 'flashcard';
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
				'mode'         => array(
					'type' => 'string',
					'enum' => array( 'self_assessment', 'streak' ),
				),
				'known_streak' => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 10,
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
		return array( 'all_known', 'all_streak', 'ratio' );
	}

	/**
	 * Completion from card counts (`known`, `total`).
	 *
	 * @param array<string, mixed> $rule     Rule.
	 * @param array<string, mixed> $progress Progress.
	 * @return string
	 */
	public function evaluate_completion( array $rule, array $progress ): string {
		$total = (int) ( $progress['total'] ?? 0 );
		$known = (int) ( $progress['known'] ?? 0 );
		if ( 'ratio' === ( $rule['type'] ?? '' ) ) {
			$needed = (int) ceil( $total * (float) ( $rule['params']['ratio'] ?? 1.0 ) );
			return $total > 0 && $known >= $needed ? 'completed' : self::started_or_not( $progress );
		}
		return $total > 0 && $known >= $total ? 'completed' : self::started_or_not( $progress );
	}
}
