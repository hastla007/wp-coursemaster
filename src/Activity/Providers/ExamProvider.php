<?php
/**
 * Exam activity (R0-08 §2.4, §4, §6).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Completion `passed` (default) or `evaluated`. Exam answers never change
 * the mastery state.
 */
final class ExamProvider extends AbstractProvider {

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function type(): string {
		return 'exam';
	}

	/**
	 * Capabilities.
	 *
	 * @return array<string, bool|string>
	 */
	public function capabilities(): array {
		return array(
			'gradable'           => true,
			'produces_evidence'  => true,
			'access_scope'       => 'exam',
			'preview_limit_type' => 'first_variants',
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
			'required'             => array( 'rule_uid' ),
			'properties'           => array(
				'rule_uid' => array(
					'type'    => 'string',
					'pattern' => '^[0-9a-f-]{36}$',
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
		return array( 'passed', 'evaluated' );
	}

	/**
	 * Completion.
	 *
	 * @param array<string, mixed> $rule     Rule.
	 * @param array<string, mixed> $progress Progress.
	 * @return string
	 */
	public function evaluate_completion( array $rule, array $progress ): string {
		$flag = 'evaluated' === ( $rule['type'] ?? '' ) ? 'evaluated' : 'passed';
		return self::by_flag( $progress, $flag );
	}
}
