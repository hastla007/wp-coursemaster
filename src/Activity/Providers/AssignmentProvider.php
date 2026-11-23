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
 * Settings schema and completion rules; the submission workflow lives in
 * `CourseCaptain\Assignment\AssignmentService` (R1-46). Settings are frozen
 * per submission at `submit` (R0-08 §2.5, R0-23 RV-22).
 */
final class AssignmentProvider extends AbstractProvider {

	/** Global submission allowlist (R0-10 §4.5, E-289); svg/html/js/php never. */
	public const FILE_TYPES = array( 'pdf', 'docx', 'odt', 'txt', 'jpg', 'png' );

	/** Text defaults (OP-08-3, technical in R1-46): no minimum, 20 000 characters. */
	public const TEXT_DEFAULT_MAX = 20000;

	/** Absolute upper bound of `text_limits` (characters). */
	public const TEXT_HARD_MAX = 200000;

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
				'instructions'    => array( 'type' => 'string' ),
				'rubric_text'     => array( 'type' => 'string' ),
				'text_limits'     => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'min_chars' => array(
							'type'    => 'integer',
							'minimum' => 0,
							'maximum' => self::TEXT_HARD_MAX,
						),
						'max_chars' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => self::TEXT_HARD_MAX,
						),
					),
				),
				'file_rules'      => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'allowlist' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
								'enum' => self::FILE_TYPES,
							),
						),
						'max_files' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 20,
						),
						'max_bytes' => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
					),
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
