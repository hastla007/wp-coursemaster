<?php
/**
 * Registry of activity providers (R0-08 §2.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity;

use CourseCaptain\Activity\Providers\AssignmentProvider;
use CourseCaptain\Activity\Providers\ExamProvider;
use CourseCaptain\Activity\Providers\FileProvider;
use CourseCaptain\Activity\Providers\FlashcardProvider;
use CourseCaptain\Activity\Providers\LessonProvider;
use CourseCaptain\Activity\Providers\QuizProvider;
use CourseCaptain\Activity\Providers\VideoProvider;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * A duplicate type is an error, never a silent override. Extensions
 * register on `course_captain_register_activities`.
 */
final class ActivityRegistry {

	/**
	 * Providers by type.
	 *
	 * @var array<string, ActivityProvider>
	 */
	private array $providers = array();

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Registry with the core providers and the extension hook fired once.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = self::with_core_providers();
			/**
			 * Register additional activity providers.
			 *
			 * @since 1.0.0
			 * @param ActivityRegistry $registry Registry.
			 */
			do_action( 'course_captain_register_activities', self::$instance );
		}
		return self::$instance;
	}

	/**
	 * Registry with the seven R1 core providers only.
	 *
	 * @return self
	 */
	public static function with_core_providers(): self {
		$registry = new self();
		foreach ( array( new LessonProvider(), new VideoProvider(), new FileProvider(), new QuizProvider(), new ExamProvider(), new FlashcardProvider(), new AssignmentProvider() ) as $provider ) {
			$registry->register( $provider );
		}
		return $registry;
	}

	/**
	 * Register a provider.
	 *
	 * @param ActivityProvider $provider Provider.
	 * @return void
	 * @throws InvalidArgumentException For invalid or duplicate types.
	 */
	public function register( ActivityProvider $provider ): void {
		$type = $provider->type();
		if ( 1 !== preg_match( '/^[a-z0-9_]{1,32}$/', $type ) ) {
			throw new InvalidArgumentException( 'Invalid activity type.' );
		}
		if ( isset( $this->providers[ $type ] ) ) {
			throw new InvalidArgumentException( 'Activity type already registered.' );
		}
		$this->providers[ $type ] = $provider;
	}

	/**
	 * Provider of a type.
	 *
	 * @param string $type Type.
	 * @return ActivityProvider|null
	 */
	public function get( string $type ): ?ActivityProvider {
		return $this->providers[ $type ] ?? null;
	}

	/**
	 * All providers.
	 *
	 * @return array<string, ActivityProvider>
	 */
	public function all(): array {
		return $this->providers;
	}

	/**
	 * Validate settings of a type against its schema.
	 *
	 * @param string               $type     Type.
	 * @param array<string, mixed> $settings Settings.
	 * @return list<string> Errors (unknown type → `unknown_type`).
	 */
	public function validate_settings( string $type, array $settings ): array {
		$provider = $this->get( $type );
		if ( null === $provider ) {
			return array( 'unknown_type' );
		}
		return SettingsSchema::validate( $settings, $provider->settings_schema() );
	}

	/**
	 * Frozen definition for the course revision (R0-08 §2.3, §7.1).
	 *
	 * @param string               $type     Type.
	 * @param array<string, mixed> $settings Settings.
	 * @param array<string, mixed> $rule     Completion rule.
	 * @return array<string, mixed>
	 */
	public function freeze( string $type, array $settings, array $rule ): array {
		$provider = $this->get( $type );
		if ( null === $provider ) {
			return array(
				'type'      => $type,
				'available' => false,
			);
		}
		$rule = self::normalize_rule( $provider, $rule );
		return array(
			'type'             => $type,
			'available'        => true,
			'contract_version' => $provider->contract_version(),
			'settings_version' => $provider->settings_version(),
			'settings_hash'    => 'sha256:' . hash( 'sha256', (string) wp_json_encode( $settings ) ),
			'completion_rule'  => $rule,
		);
	}

	/**
	 * Completion rule with the provider default when empty or unsupported.
	 *
	 * @param ActivityProvider     $provider Provider.
	 * @param array<string, mixed> $rule     Rule.
	 * @return array{type: string, params: array<string, mixed>, rule_version: int}
	 */
	public static function normalize_rule( ActivityProvider $provider, array $rule ): array {
		$types = $provider->completion_rule_types();
		$type  = (string) ( $rule['type'] ?? '' );
		if ( ! in_array( $type, $types, true ) ) {
			$type = $types[0] ?? 'none';
			$rule = array();
		}
		return array(
			'type'         => $type,
			'params'       => is_array( $rule['params'] ?? null ) ? $rule['params'] : array(),
			'rule_version' => 1,
		);
	}
}
