<?php
/**
 * Activity definition of a lesson (E-67: one activity per lesson in R1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * Stores type, settings and completion rule as lesson meta (draft);
 * publishing freezes them into the course revision. The type of a
 * published activity cannot change (R0-08 §2.3).
 */
final class ActivityService {

	public const SETTINGS_META = '_ccap_activity_settings';
	public const RULE_META     = '_ccap_completion_rule';

	/**
	 * Constructor.
	 *
	 * @param ActivityRegistry $registry Registry.
	 * @param PolicyService    $policy   Policy.
	 */
	public function __construct( private ActivityRegistry $registry, private PolicyService $policy ) {
	}

	/**
	 * Instance for the request.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( ActivityRegistry::instance(), PolicyService::instance() );
	}

	/**
	 * Define the activity of a lesson.
	 *
	 * @param Actor                $actor     Actor (M-06).
	 * @param int                  $lesson_id Lesson.
	 * @param string               $type      Activity type.
	 * @param array<string, mixed> $settings  Settings.
	 * @param array<string, mixed> $rule      Completion rule (empty = provider default).
	 * @return void
	 * @throws CourseException On invalid input, type change after publication or missing rights.
	 */
	public function define( Actor $actor, int $lesson_id, string $type, array $settings, array $rule = array() ): void {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::EDIT_COURSE_ITEM, array( $lesson_id, 'lesson' ) ), 'Not allowed to edit this lesson.' );
		$provider = $this->registry->get( $type );
		if ( null === $provider ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown activity type.', 400 );
		}
		$errors = SettingsSchema::validate( $settings, $provider->settings_schema() );
		if ( array() !== $errors ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( '; ', $errors ) ), 422 );
		}
		$current = (string) get_post_meta( $lesson_id, ObjectIdentity::ACTIVITY_TYPE_META, true );
		if ( '' !== $current && $current !== $type && 'publish' === get_post_status( $lesson_id ) && $this->in_published_revision( $lesson_id ) ) {
			throw new CourseException( 'ccap_activity_type_locked', 'The type of a published activity cannot change.', 409 );
		}
		update_post_meta( $lesson_id, ObjectIdentity::ACTIVITY_TYPE_META, $type );
		update_post_meta( $lesson_id, self::SETTINGS_META, wp_slash( (string) wp_json_encode( $settings ) ) );
		update_post_meta( $lesson_id, self::RULE_META, wp_slash( (string) wp_json_encode( ActivityRegistry::normalize_rule( $provider, $rule ) ) ) );
	}

	/**
	 * Draft definition of a lesson.
	 *
	 * @param int $lesson_id Lesson.
	 * @return array{type: string, settings: array<string, mixed>, rule: array<string, mixed>}
	 */
	public static function definition( int $lesson_id ): array {
		$settings = json_decode( (string) get_post_meta( $lesson_id, self::SETTINGS_META, true ), true );
		$rule     = json_decode( (string) get_post_meta( $lesson_id, self::RULE_META, true ), true );
		return array(
			'type'     => (string) get_post_meta( $lesson_id, ObjectIdentity::ACTIVITY_TYPE_META, true ),
			'settings' => is_array( $settings ) ? $settings : array(),
			'rule'     => is_array( $rule ) ? $rule : array(),
		);
	}

	/**
	 * Whether any published course revision contains the lesson.
	 *
	 * @param int $lesson_id Lesson.
	 * @return bool
	 */
	private function in_published_revision( int $lesson_id ): bool {
		$post = get_post( $lesson_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}
		$relations = new Relations( \CourseCaptain\Storage\Database::current() );
		foreach ( $relations->courses_of_item( $post ) as $course ) {
			if ( '' !== (string) get_post_meta( $course, '_ccap_current_revision_id', true ) ) {
				return true;
			}
		}
		return false;
	}
}
