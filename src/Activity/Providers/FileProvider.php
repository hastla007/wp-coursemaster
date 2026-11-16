<?php
/**
 * Protected download (R0-08 §2.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Completion `downloaded` (server-logged successful download) or `manual_confirm`.
 */
final class FileProvider extends AbstractProvider {

	/**
	 * Type.
	 *
	 * @return string
	 */
	public function type(): string {
		return 'file';
	}

	/**
	 * Capabilities.
	 *
	 * @return array<string, bool|string>
	 */
	public function capabilities(): array {
		return array( 'access_scope' => 'download' ) + parent::capabilities();
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
			'required'             => array( 'media_uid' ),
			'properties'           => array(
				'media_uid' => array(
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
		return array( 'downloaded', 'manual_confirm' );
	}

	/**
	 * Completion.
	 *
	 * @param array<string, mixed> $rule     Rule.
	 * @param array<string, mixed> $progress Progress.
	 * @return string
	 */
	public function evaluate_completion( array $rule, array $progress ): string {
		$flag = 'manual_confirm' === ( $rule['type'] ?? '' ) ? 'confirmed' : 'downloaded';
		return self::by_flag( $progress, $flag );
	}
}
