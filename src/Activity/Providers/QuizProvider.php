<?php
/**
 * Practice quiz (R0-08 §2.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Completion `attempted` (every question answered once, default),
 * `passed`, `all_correct_once`.
 */
final class QuizProvider extends AbstractProvider {

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function type(): string {
		return 'quiz';
	}

	/**
	 * Capabilities.
	 *
	 * @return array<string, bool|string>
	 */
	public function capabilities(): array {
		return array(
			'gradable'           => true,
			'feeds_mastery'      => true,
			'preview_limit_type' => 'questions_per_section',
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
				'source'             => array(
					'type' => 'string',
					'enum' => array( 'inline', 'module_pool' ),
				),
				'scored'             => array( 'type' => 'boolean' ),
				'counts_as_evidence' => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * Rule types.
	 *
	 * @return list<string>
	 */
	public function completion_rule_types(): array {
		return array( 'attempted', 'passed', 'all_correct_once' );
	}

	/**
	 * Completion.
	 *
	 * @param array<string, mixed> $rule     Rule.
	 * @param array<string, mixed> $progress Progress (`answered`, `correct`, `total`, `passed`).
	 * @return string
	 */
	public function evaluate_completion( array $rule, array $progress ): string {
		$total = (int) ( $progress['total'] ?? 0 );
		switch ( $rule['type'] ?? 'attempted' ) {
			case 'passed':
				return self::by_flag( $progress, 'passed' );
			case 'all_correct_once':
				return $total > 0 && (int) ( $progress['correct'] ?? 0 ) >= $total ? 'completed' : self::started_or_not( $progress );
			default:
				return $total > 0 && (int) ( $progress['answered'] ?? 0 ) >= $total ? 'completed' : self::started_or_not( $progress );
		}
	}
}
