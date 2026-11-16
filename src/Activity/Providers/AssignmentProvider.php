<?php
/**
 * Text/file assignment (E-168, R0-08 §2.5); behaviour in R1-46.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Settings and completion are defined here so courses can be authored;
 * submission and grading follow in R1-46.
 */
final class AssignmentProvider extends AbstractProvider {

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function type(): string {
		return 'assignment';
	}

	/**
	 * Capabilities.
	 *
	 * @return array<string, bool|string>
	 */
	public function capabilities(): array {
		return array(
			'gradable'           => true,
			'preview_limit_type' => 'none',
		) + parent::capabilities();
	}

	/**
	 * Settings schema (§2.5).
	 *
	 * @return array<string, mixed>
	 */
	public function settings_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => array( 'submission_type', 'max_points' ),
			'properties'           => array(
				'submission_type' => array(
					'type' => 'string',
					'enum' => array( 'text', 'file', 'text_and_file' ),
				),
				'max_points'      => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'passing_points'  => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'rubric_text'     => array( 'type' => 'string' ),
				'text_limits'     => array( 'type' => 'object' ),
				'file_rules'      => array( 'type' => 'object' ),
			),
		);
	}

	/**
	 * Rule types.
	 *
	 * @return list<string>
	 */
	public function completion_rule_types(): array {
		return array( 'graded', 'submitted', 'passed' );
	}

	/**
	 * Completion.
	 *
	 * @param array<string, mixed> $rule     Rule.
	 * @param array<string, mixed> $progress Progress.
	 * @return string
	 */
	public function evaluate_completion( array $rule, array $progress ): string {
		$flag = array(
			'submitted' => 'submitted',
			'passed'    => 'passed',
		)[ (string) ( $rule['type'] ?? '' ) ] ?? 'graded';
		return self::by_flag( $progress, $flag );
	}
}
