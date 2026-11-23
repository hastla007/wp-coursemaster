<?php
/**
 * Inheritance resolver global → course → exam rule (R0-28 §1.3, T28-6).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Seo\CourseSeo;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * The single source for runtime, snapshot, preview and notice (T28-6).
 *
 * - Priority: site lock > exam rule/activity > course (group, media) >
 *   global > schema default (§1.3 steps 1–4). "Inherit" is a missing
 *   key, never a stored sentinel; `null` stays a value (INH-002).
 * - Leaf objects (`learning.feedback`, `exams.feedback`,
 *   `certificates.base_template`) merge sub-keys across levels.
 * - Constraints after the choice: monotonic keys keep the global value
 *   when an override is looser (C-1, `chain: stricter_global`); a locked
 *   key keeps the override stored, it only rests (§1.6).
 * - Course overrides live in the draft meta `_ccap_settings`
 *   (`{_schema, overrides}`) and are frozen into the course revision
 *   (`structure.settings`, T28-3); they take effect with the next
 *   publication. `general.catalog_indexing` keeps its live meta
 *   `_ccap_seo_index` (R1-37).
 * - Locks are kept in `course_captain_setting_locks`
 *   (`{key: {reason, by, since}}`); setting/removing needs #19.
 */
final class EffectiveSettings {

	public const LOCK_OPTION = 'course_captain_setting_locks';
	public const COURSE_META = '_ccap_settings';
	public const UNDO_META   = '_ccap_settings_undo';
	public const SCHEMA      = 1;
	public const UNDO_TTL    = 2592000;
	public const INHERIT     = 'inherit';

	/**
	 * Stricter order of `privacy.external_media_mode` (C-1).
	 *
	 * @var list<string>
	 */
	private const MEDIA_ORDER = array( 'always_load', 'click_to_load', 'link_only' );

	/**
	 * Translated labels of the overridable keys (UI contract §1.7).
	 *
	 * @return array<string, string>
	 */
	public static function labels(): array {
		return array(
			'general.catalog_indexing'           => __( 'Search engine indexing', 'course-captain' ),
			'display.container_width'            => __( 'Content width', 'course-captain' ),
			'display.breadcrumbs'                => __( 'Breadcrumbs', 'course-captain' ),
			'learning.course_completion_default' => __( 'Course completion rule', 'course-captain' ),
			'learning.mastery_algorithm'         => __( 'Mastery algorithm', 'course-captain' ),
			'learning.favorites_enabled'         => __( 'Favorites', 'course-captain' ),
			'learning.mistake_training_enabled'  => __( 'Mistake training', 'course-captain' ),
			'learning.feedback'                  => __( 'Feedback while learning', 'course-captain' ),
			'exams.max_attempts'                 => __( 'Maximum attempts', 'course-captain' ),
			'exams.cooldown_seconds'             => __( 'Waiting time after a failed attempt (seconds)', 'course-captain' ),
			'exams.count_cancelled'              => __( 'Cancelled attempts count', 'course-captain' ),
			'exams.time_limit_minutes'           => __( 'Time limit (minutes)', 'course-captain' ),
			'exams.timer_mode'                   => __( 'Timer mode', 'course-captain' ),
			'exams.late_tolerance_seconds'       => __( 'Late tolerance (seconds)', 'course-captain' ),
			'exams.shuffle_questions'            => __( 'Shuffle questions', 'course-captain' ),
			'exams.shuffle_answers'              => __( 'Shuffle answers', 'course-captain' ),
			'exams.feedback'                     => __( 'Exam feedback', 'course-captain' ),
			'exams.auto_submit_inactive_days'    => __( 'Submit inactive attempts after (days)', 'course-captain' ),
			'enrollment.self_enrollment'         => __( 'Self-enrollment', 'course-captain' ),
			'enrollment.default_access_days'     => __( 'Access duration (days)', 'course-captain' ),
			'enrollment.groups_enabled'          => __( 'Groups', 'course-captain' ),
			'certificates.auto_issue'            => __( 'Issue certificates automatically', 'course-captain' ),
			'certificates.base_template'         => __( 'Certificate template', 'course-captain' ),
			'enrollment.claim_policy'            => __( 'Who may redeem an invitation', 'course-captain' ),
			'enrollment.invite_valid_days'       => __( 'Invitation valid for (days)', 'course-captain' ),
			'privacy.external_media_mode'        => __( 'External media', 'course-captain' ),
		);
	}

	/**
	 * Text for a resolution class (panel column "applies").
	 *
	 * @param string $resolve_class L|K|R|G|E|A.
	 * @return string
	 */
	public static function class_label( string $resolve_class ): string {
		$labels = array(
			'L' => __( 'applies immediately', 'course-captain' ),
			'K' => __( 'applies from the next publication', 'course-captain' ),
			'R' => __( 'applies from the next attempt start', 'course-captain' ),
			'G' => __( 'applies to new access and invitations', 'course-captain' ),
			'E' => __( 'applies from the next completion', 'course-captain' ),
			'A' => __( 'applies to new certificates', 'course-captain' ),
		);
		return $labels[ $resolve_class ] ?? $resolve_class;
	}

	/**
	 * Text for a source (panel column "source").
	 *
	 * @param string $source Source.
	 * @return string
	 */
	public static function source_label( string $source ): string {
		$labels = array(
			'global_lock'    => __( 'set by the website', 'course-captain' ),
			'exam_rule'      => __( 'this exam', 'course-captain' ),
			'activity'       => __( 'this activity', 'course-captain' ),
			'course'         => __( 'this course', 'course-captain' ),
			'group'          => __( 'this group', 'course-captain' ),
			'media'          => __( 'this media item', 'course-captain' ),
			'global'         => __( 'website default', 'course-captain' ),
			'schema_default' => __( 'built-in default', 'course-captain' ),
			'proposal'       => __( 'proposal', 'course-captain' ),
			'unknown'        => __( 'unknown', 'course-captain' ),
		);
		return $labels[ $source ] ?? $source;
	}

	/**
	 * Resolve one key.
	 *
	 * Context: `rule` = `{overrides?: array, resolved?: array<string, array{v: mixed, src: string}>}`,
	 * `activity`, `course`, `group` = override maps, `media` = value,
	 * `ignore_locks` = true when materializing a rule (locks apply live).
	 *
	 * @param string               $key     Register key.
	 * @param array<string, mixed> $context Context.
	 * @return array{key: string, value: mixed, source: string, locked: bool, resting: bool, class: string, chain: list<string>}
	 * @throws CourseException For unknown keys.
	 */
	public static function resolve( string $key, array $context = array() ): array {
		$field  = self::field( $key );
		$meta   = SettingsSchema::overridable()[ $key ] ?? null;
		$global = self::global_value( $key );
		$out    = array(
			'key'     => $key,
			'value'   => $global['value'],
			'source'  => $global['source'],
			'locked'  => false,
			'resting' => false,
			'class'   => null === $meta ? 'L' : $meta['class'],
			'chain'   => array( $global['source'] ),
		);
		if ( null === $meta ) {
			return $out;
		}
		$leaf   = 'leaf' === $meta['unit'];
		$levels = array();
		foreach ( array( 'group', 'course', 'activity' ) as $level ) {
			if ( isset( $context[ $level ] ) && is_array( $context[ $level ] ) && array_key_exists( $key, $context[ $level ] ) ) {
				$levels[] = array( $level, $context[ $level ][ $key ] );
			}
		}
		if ( array_key_exists( 'media', $context ) && 'privacy.external_media_mode' === $key && null !== $context['media'] ) {
			$levels[] = array( 'media', $context['media'] );
		}
		$rule = isset( $context['rule'] ) && is_array( $context['rule'] ) ? $context['rule'] : array();
		if ( isset( $rule['resolved'] ) && is_array( $rule['resolved'] ) && isset( $rule['resolved'][ $key ] ) && is_array( $rule['resolved'][ $key ] ) ) {
			// A published rule carries its materialized value (§1.3 class R).
			$levels = array( array( (string) ( $rule['resolved'][ $key ]['src'] ?? 'exam_rule' ), $rule['resolved'][ $key ]['v'] ?? null ) );
			$leaf   = false;
		} elseif ( isset( $rule['overrides'] ) && is_array( $rule['overrides'] ) && array_key_exists( $key, $rule['overrides'] ) ) {
			$levels[] = array( 'exam_rule', $rule['overrides'][ $key ] );
		}
		$value  = $global['value'];
		$source = $global['source'];
		foreach ( $levels as $level ) {
			if ( $leaf && is_array( $value ) && is_array( $level[1] ) ) {
				$value = array_replace( $value, array_intersect_key( $level[1], (array) $field['fields'] ) );
			} else {
				$value = $level[1];
			}
			$source         = $level[0];
			$out['chain'][] = $level[0];
		}
		$has_override = array() !== $levels && ! in_array( $source, array( 'global', 'schema_default' ), true );
		if ( $meta['lockable'] && empty( $context['ignore_locks'] ) && isset( self::locks()[ $key ] ) ) {
			$out['locked']  = true;
			$out['resting'] = $has_override;
			$out['value']   = $global['value'];
			$out['source']  = 'global_lock';
			$out['chain'][] = 'global_lock';
			return $out;
		}
		if ( $has_override && isset( $meta['monotonic'] ) && ! self::within_monotonic( $meta['monotonic'], $value, $global['value'] ) ) {
			$out['resting'] = true;
			$out['chain'][] = 'stricter_global';
			return $out;
		}
		$out['value']  = $value;
		$out['source'] = $source;
		return $out;
	}

	/**
	 * Validate an override value for a scope (R0-28 §1.1 MUSS).
	 *
	 * @param string $key   Register key.
	 * @param mixed  $value Raw value.
	 * @param string $scope course|activity|exam_rule|group|media.
	 * @return array{ok: bool, value: mixed, code: string}
	 */
	public static function validate_override( string $key, $value, string $scope ): array {
		$bad  = static fn( string $code ): array => array(
			'ok'    => false,
			'value' => null,
			'code'  => $code,
		);
		$meta = SettingsSchema::overridable()[ $key ] ?? null;
		if ( null === $meta || ! in_array( $scope, $meta['scopes'], true ) ) {
			return $bad( 'ccap_setting_not_overridable' );
		}
		$field = self::field( $key );
		if ( 'leaf' === $meta['unit'] ) {
			if ( ! is_array( $value ) || array() === $value || array() !== array_diff_key( $value, (array) $field['fields'] ) ) {
				return $bad( 'ccap_validation_failed' );
			}
			$clean = array();
			foreach ( $value as $leaf => $raw ) {
				$check = SettingsSchema::validate( $field['fields'][ $leaf ], $raw );
				if ( ! $check['ok'] ) {
					return $bad( 'ccap_validation_failed' );
				}
				$clean[ $leaf ] = $check['value'];
			}
			ksort( $clean );
			return array(
				'ok'    => true,
				'value' => $clean,
				'code'  => '',
			);
		}
		$check = SettingsSchema::validate( $field, $value );
		if ( ! $check['ok'] ) {
			return $bad( 'ccap_validation_failed' );
		}
		if ( isset( $meta['monotonic'] ) && ! self::within_monotonic( $meta['monotonic'], $check['value'], self::global_value( $key )['value'] ) ) {
			return $bad( 'ccap_setting_not_stricter' );
		}
		return array(
			'ok'    => true,
			'value' => $check['value'],
			'code'  => '',
		);
	}

	/**
	 * Active locks.
	 *
	 * @return array<string, array{reason: string, by: int, since: string}>
	 */
	public static function locks(): array {
		$raw = get_option( self::LOCK_OPTION, array() );
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $key => $lock ) {
			if ( is_string( $key ) && is_array( $lock ) && ! empty( SettingsSchema::overridable()[ $key ]['lockable'] ) ) {
				$out[ $key ] = array(
					'reason' => (string) ( $lock['reason'] ?? '' ),
					'by'     => (int) ( $lock['by'] ?? 0 ),
					'since'  => (string) ( $lock['since'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Lock a key to the global value (§1.6). Overrides are kept and rest.
	 *
	 * @param Actor  $actor  Settings manager (#19).
	 * @param string $key    Lockable key.
	 * @param string $reason Reason ≤ 200 characters, no personal data.
	 * @return int Number of resting overrides.
	 * @throws CourseException `ccap_forbidden`, `ccap_validation_failed`, `ccap_setting_lock_conflict`.
	 */
	public static function lock( Actor $actor, string $key, string $reason ): int {
		self::assert_settings_manager( $actor );
		if ( empty( SettingsSchema::overridable()[ $key ]['lockable'] ) ) {
			throw new CourseException( 'ccap_validation_failed', 'key', 422 );
		}
		$reason = sanitize_text_field( $reason );
		if ( mb_strlen( $reason ) > 200 ) {
			throw new CourseException( 'ccap_validation_failed', 'reason', 422 );
		}
		// Preflight (§1.6, K-4): a lock value that would make a published object invalid is refused.
		if ( 'exams.timer_mode' === $key && 'practice' === self::global_value( $key )['value'] ) {
			$conflicts = self::evidence_rules();
			if ( array() !== $conflicts ) {
				throw new CourseException( 'ccap_setting_lock_conflict', esc_html( implode( ',', $conflicts ) ), 409 );
			}
		}
		$locks         = self::locks();
		$locks[ $key ] = array(
			'reason' => $reason,
			'by'     => $actor->user_id,
			'since'  => gmdate( 'c' ),
		);
		update_option( self::LOCK_OPTION, $locks, true );
		$resting = self::count_overrides( $key );
		self::audit(
			$actor,
			'settings_lock_set',
			'settings',
			$key,
			array(
				'key'     => $key,
				'value'   => self::global_value( $key )['value'],
				'reason'  => $reason,
				'resting' => $resting,
			)
		);
		do_action( 'course_captain_policy_changed' );
		return $resting;
	}

	/**
	 * Remove a lock; resting overrides apply again at the times of their class.
	 *
	 * @param Actor  $actor Settings manager (#19).
	 * @param string $key   Key.
	 * @return int Number of reactivated overrides.
	 * @throws CourseException `ccap_forbidden`.
	 */
	public static function unlock( Actor $actor, string $key ): int {
		self::assert_settings_manager( $actor );
		$locks = self::locks();
		if ( ! isset( $locks[ $key ] ) ) {
			return 0;
		}
		unset( $locks[ $key ] );
		update_option( self::LOCK_OPTION, $locks, true );
		$count = self::count_overrides( $key );
		self::audit(
			$actor,
			'settings_lock_removed',
			'settings',
			$key,
			array(
				'key'         => $key,
				'reactivated' => $count,
			)
		);
		do_action( 'course_captain_policy_changed' );
		return $count;
	}

	/**
	 * Draft overrides of a course (`_ccap_settings` plus the live SEO meta).
	 *
	 * @param int $course Course.
	 * @return array<string, mixed>
	 */
	public static function course_overrides( int $course ): array {
		$meta      = get_post_meta( $course, self::COURSE_META, true );
		$overrides = is_array( $meta ) && is_array( $meta['overrides'] ?? null ) ? $meta['overrides'] : array();
		$seo       = (string) get_post_meta( $course, CourseSeo::META_INDEX, true );
		unset( $overrides['general.catalog_indexing'] );
		if ( 'index' === $seo || 'noindex' === $seo ) {
			$overrides['general.catalog_indexing'] = 'index' === $seo ? 'public_catalog' : 'noindex';
		}
		ksort( $overrides );
		return $overrides;
	}

	/**
	 * Overrides frozen in the current course revision (draft before the first publication).
	 *
	 * @param int $course Course.
	 * @return array<string, mixed>
	 */
	public static function published_overrides( int $course ): array {
		$frozen = self::revision_settings( $course );
		if ( null === $frozen ) {
			return self::course_overrides( $course );
		}
		$overrides = is_array( $frozen['overrides'] ?? null ) ? $frozen['overrides'] : array();
		// The SEO switch stays live (R1-37 meta, class L).
		unset( $overrides['general.catalog_indexing'] );
		return array_intersect_key( self::course_overrides( $course ), array( 'general.catalog_indexing' => true ) ) + $overrides;
	}

	/**
	 * `settings` block of the current course revision.
	 *
	 * @param int $course Course.
	 * @return array<string, mixed>|null
	 */
	public static function revision_settings( int $course ): ?array {
		$row = CoursePublisher::current()->current_revision( $course );
		if ( null === $row ) {
			return null;
		}
		$structure = json_decode( (string) $row['structure'], true );
		return is_array( $structure ) && is_array( $structure['settings'] ?? null ) ? $structure['settings'] : array();
	}

	/**
	 * Effective value for a published course (runtime: classes L, G, E, A).
	 * K values come from the revision's `resolved` block.
	 *
	 * @param string $key    Key.
	 * @param int    $course Course.
	 * @return mixed
	 */
	public static function for_course( string $key, int $course ) {
		$meta = SettingsSchema::overridable()[ $key ] ?? null;
		if ( null !== $meta && 'K' === $meta['class'] ) {
			$frozen = self::revision_settings( $course );
			if ( is_array( $frozen ) && isset( $frozen['resolved'][ $key ] ) && is_array( $frozen['resolved'][ $key ] ) && ! isset( self::locks()[ $key ] ) ) {
				return $frozen['resolved'][ $key ]['v'] ?? null;
			}
		}
		return self::resolve( $key, array( 'course' => $course > 0 ? self::published_overrides( $course ) : array() ) )['value'];
	}

	/**
	 * Settings block frozen at publication (T28-3): overrides and, for
	 * class K, the resolved value with its source.
	 *
	 * @param int $course Course.
	 * @return array{schema: int, overrides: array<string, mixed>, resolved: array<string, array{v: mixed, src: string}>}
	 */
	public static function freeze_course( int $course ): array {
		$overrides = self::course_overrides( $course );
		$resolved  = array();
		foreach ( SettingsSchema::overridable() as $key => $meta ) {
			if ( 'K' === $meta['class'] && in_array( 'course', $meta['scopes'], true ) ) {
				$r                = self::resolve( $key, array( 'course' => $overrides ) );
				$resolved[ $key ] = array(
					'v'   => $r['value'],
					'src' => $r['source'],
				);
			}
		}
		return array(
			'schema'    => self::SCHEMA,
			'overrides' => $overrides,
			'resolved'  => $resolved,
		);
	}

	/**
	 * Save course overrides (§1.7, K-2). `inherit` removes an override.
	 * Unknown or non-course keys reject the whole request; locked keys are
	 * refused one by one while the other keys are saved.
	 *
	 * @param Actor                $actor   Course manager (M-03).
	 * @param int                  $course  Course.
	 * @param array<string, mixed> $changes Key → value or `inherit`.
	 * @return array{saved: list<string>, rejected: array<string, string>}
	 * @throws CourseException `ccap_not_found`, `ccap_forbidden`, `ccap_validation_failed`, `ccap_setting_locked`.
	 */
	public static function save_course( Actor $actor, int $course, array $changes ): array {
		self::assert_course_editor( $actor, $course );
		$overrides = self::course_overrides( $course );
		$locks     = self::locks();
		$errors    = array();
		$rejected  = array();
		$next      = $overrides;
		foreach ( $changes as $key => $value ) {
			$key = (string) $key;
			if ( self::INHERIT === $value ) {
				if ( ! isset( SettingsSchema::overridable()[ $key ] ) || ! in_array( 'course', SettingsSchema::overridable()[ $key ]['scopes'], true ) ) {
					$errors[] = $key;
				}
				unset( $next[ $key ] );
				continue;
			}
			$check = self::validate_override( $key, $value, 'course' );
			if ( ! $check['ok'] ) {
				$errors[] = $key;
				continue;
			}
			if ( isset( $locks[ $key ] ) && ( ! array_key_exists( $key, $overrides ) || $overrides[ $key ] !== $check['value'] ) ) {
				$rejected[ $key ] = 'ccap_setting_locked';
				continue;
			}
			$next[ $key ] = $check['value'];
		}
		if ( array() !== $errors ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', $errors ) ), 422 );
		}
		ksort( $next );
		$saved = array_values(
			array_unique(
				array_merge(
					array_keys( array_diff_key( $overrides, $next ) ),
					array_keys( array_filter( $next, static fn( $v, $k ) => ! array_key_exists( $k, $overrides ) || $overrides[ $k ] !== $v, ARRAY_FILTER_USE_BOTH ) )
				)
			)
		);
		if ( array() === $saved && array() !== $rejected ) {
			throw new CourseException( 'ccap_setting_locked', esc_html( implode( ',', array_keys( $rejected ) ) ), 422 );
		}
		if ( array() !== $saved ) {
			self::write_course( $course, $next );
			self::audit(
				$actor,
				'course_settings_saved',
				'course',
				(string) $course,
				array(
					'keys'     => $saved,
					'rejected' => array_keys( $rejected ),
				)
			);
		}
		return array(
			'saved'    => $saved,
			'rejected' => $rejected,
		);
	}

	/**
	 * Reset all overrides of a course to "inherit" with a 30-day undo (§4.1).
	 *
	 * @param Actor $actor  Course manager (M-03).
	 * @param int   $course Course.
	 * @return list<string> Removed keys.
	 * @throws CourseException When not allowed.
	 */
	public static function reset_course( Actor $actor, int $course ): array {
		self::assert_course_editor( $actor, $course );
		$overrides = self::course_overrides( $course );
		if ( array() === $overrides ) {
			return array();
		}
		update_post_meta(
			$course,
			self::UNDO_META,
			array(
				'overrides'  => $overrides,
				'expires_at' => time() + self::UNDO_TTL,
			)
		);
		self::write_course( $course, array() );
		self::audit(
			$actor,
			'course_settings_reset',
			'course',
			(string) $course,
			array(
				'keys'  => array_keys( $overrides ),
				'count' => count( $overrides ),
			)
		);
		return array_keys( $overrides );
	}

	/**
	 * Undo the last course reset (one generation, 30 days).
	 *
	 * @param Actor $actor  Course manager (M-03).
	 * @param int   $course Course.
	 * @return bool Restored.
	 * @throws CourseException When not allowed.
	 */
	public static function undo_course( Actor $actor, int $course ): bool {
		self::assert_course_editor( $actor, $course );
		$undo = get_post_meta( $course, self::UNDO_META, true );
		if ( ! is_array( $undo ) || (int) ( $undo['expires_at'] ?? 0 ) < time() || ! is_array( $undo['overrides'] ?? null ) ) {
			return false;
		}
		self::write_course( $course, $undo['overrides'] );
		delete_post_meta( $course, self::UNDO_META );
		self::audit(
			$actor,
			'course_settings_saved',
			'course',
			(string) $course,
			array(
				'keys' => array_keys( $undo['overrides'] ),
				'undo' => true,
			)
		);
		return true;
	}

	/**
	 * Panel "Effective settings" of a course (§1.7): every course-scope key
	 * with draft value, published value, source, class and lock state.
	 *
	 * @param int $course Course.
	 * @return list<array{key: string, label: string, class: string, applies: string, value: mixed, published: mixed, source: string, locked: bool, lock_reason: string, resting: bool, overridden: bool, override: mixed, default: mixed}>
	 */
	public static function course_panel( int $course ): array {
		$draft     = self::course_overrides( $course );
		$published = self::revision_settings( $course );
		$locks     = self::locks();
		$labels    = self::labels();
		$rows      = array();
		foreach ( SettingsSchema::overridable() as $key => $meta ) {
			if ( ! in_array( 'course', $meta['scopes'], true ) ) {
				continue;
			}
			$now    = self::resolve( $key, array( 'course' => $draft ) );
			$rows[] = array(
				'key'         => $key,
				'label'       => $labels[ $key ] ?? $key,
				'class'       => $meta['class'],
				'applies'     => self::class_label( $meta['class'] ),
				'value'       => $now['value'],
				'published'   => null === $published ? null : self::for_course( $key, $course ),
				'source'      => $now['source'],
				'locked'      => $now['locked'],
				'lock_reason' => $locks[ $key ]['reason'] ?? '',
				'resting'     => $now['resting'],
				'overridden'  => array_key_exists( $key, $draft ),
				'override'    => $draft[ $key ] ?? null,
				'default'     => self::global_value( $key )['value'],
			);
		}
		return $rows;
	}

	/**
	 * Accommodation for an attempt start (§1.3 step 6): the most specific
	 * valid assignment rule > course > site; never shown in results (E-122).
	 *
	 * @param Database $db     Database.
	 * @param int      $user   Learner.
	 * @param int      $course Course.
	 * @param int      $rule   Exam rule.
	 * @param int      $now    Start time (UTC timestamp).
	 * @return array{factor_pct: int|null, unlimited: bool, scope: string}|null
	 */
	public static function accommodation( Database $db, int $user, int $course, int $rule, int $now ): ?array {
		$rows = $db->rows(
			$db->prepare(
				'SELECT scope_type, scope_id, time_factor_pct, unlimited FROM `' . $db->table( 'ccap_accommodation' ) . "` WHERE user_id = %d AND (valid_until IS NULL OR valid_until >= %s) AND ((scope_type = 'exam_rule' AND scope_id = %d) OR (scope_type = 'course' AND scope_id = %d) OR scope_type = 'site')",
				$user,
				gmdate( 'Y-m-d H:i:s', $now ),
				$rule,
				$course
			)
		);
		$rank = array(
			'exam_rule' => 3,
			'course'    => 2,
			'site'      => 1,
		);
		usort( $rows, static fn( array $a, array $b ): int => ( $rank[ $b['scope_type'] ] ?? 0 ) <=> ( $rank[ $a['scope_type'] ] ?? 0 ) );
		$best = $rows[0] ?? null;
		if ( null === $best ) {
			return null;
		}
		$factor = null === $best['time_factor_pct'] ? null : max( 100, min( 400, (int) $best['time_factor_pct'] ) );
		return array(
			'factor_pct' => $factor,
			'unlimited'  => ! empty( $best['unlimited'] ),
			'scope'      => (string) $best['scope_type'],
		);
	}

	/**
	 * Global value of a key, or the schema default when the option lacks it (§1.3 step 4).
	 *
	 * @param string $key Key.
	 * @return array{value: mixed, source: string}
	 */
	public static function global_value( string $key ): array {
		list( $tab, $field ) = explode( '.', $key, 2 );
		$raw                 = get_option( Settings::OPTION_PREFIX . $tab );
		$stored              = is_array( $raw ) && is_array( $raw['values'] ?? null ) && array_key_exists( $field, $raw['values'] );
		return array(
			'value'  => Settings::get( $key ),
			'source' => $stored || false === $raw ? 'global' : 'schema_default',
		);
	}

	/**
	 * Field definition of a key.
	 *
	 * @param string $key Key.
	 * @return array<string, mixed>
	 * @throws CourseException For unknown keys.
	 */
	public static function field( string $key ): array {
		list( $tab, $field ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		$fields              = SettingsSchema::fields();
		if ( ! isset( $fields[ $tab ][ $field ] ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown setting.', 400 );
		}
		return $fields[ $tab ][ $field ];
	}

	/**
	 * Whether a value respects a monotonic rule against the global value.
	 *
	 * @param string $rule   shorter|stricter.
	 * @param mixed  $value  Override.
	 * @param mixed  $site   Global value.
	 * @return bool
	 */
	private static function within_monotonic( string $rule, $value, $site ): bool {
		if ( 'shorter' === $rule ) {
			return is_int( $value ) && is_int( $site ) && $value <= $site;
		}
		$pos_value  = array_search( $value, self::MEDIA_ORDER, true );
		$pos_global = array_search( $site, self::MEDIA_ORDER, true );
		return false !== $pos_value && false !== $pos_global && $pos_value >= $pos_global;
	}

	/**
	 * Write the draft overrides (catalog indexing goes to the live SEO meta).
	 *
	 * @param int                  $course    Course.
	 * @param array<string, mixed> $overrides Overrides.
	 * @return void
	 */
	private static function write_course( int $course, array $overrides ): void {
		if ( array_key_exists( 'general.catalog_indexing', $overrides ) ) {
			update_post_meta( $course, CourseSeo::META_INDEX, 'noindex' === $overrides['general.catalog_indexing'] ? 'noindex' : 'index' );
		} else {
			delete_post_meta( $course, CourseSeo::META_INDEX );
		}
		unset( $overrides['general.catalog_indexing'] );
		ksort( $overrides );
		if ( array() === $overrides ) {
			delete_post_meta( $course, self::COURSE_META );
		} else {
			update_post_meta(
				$course,
				self::COURSE_META,
				array(
					'_schema'   => self::SCHEMA,
					'overrides' => $overrides,
				)
			);
		}
		\CourseCaptain\Cache\CacheStore::bump_course( $course );
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * Number of stored overrides of a key (courses and exam rules).
	 *
	 * @param string $key Key.
	 * @return int
	 */
	public static function count_overrides( string $key ): int {
		$scopes = SettingsSchema::overridable()[ $key ]['scopes'] ?? array();
		$count  = 0;
		if ( in_array( 'course', $scopes, true ) ) {
			$courses = get_posts(
				array(
					'post_type'   => Relations::COURSE_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			);
			foreach ( $courses as $course ) {
				$count += array_key_exists( $key, self::course_overrides( (int) $course ) ) ? 1 : 0;
			}
		}
		if ( in_array( 'exam_rule', $scopes, true ) ) {
			$db = Database::current();
			foreach ( $db->rows( 'SELECT settings FROM `' . $db->table( 'ccap_exam_rule' ) . "` WHERE status IN ('draft', 'published')" ) as $row ) {
				$settings = json_decode( (string) $row['settings'], true );
				$own      = is_array( $settings ) ? (array) ( $settings['overrides'] ?? array() ) : array();
				$count   += array_key_exists( $key, $own ) ? 1 : 0;
			}
		}
		return $count;
	}

	/**
	 * Published or draft evidence rules (lock preflight for `timer_mode`).
	 *
	 * @return list<string> Rule UIDs.
	 */
	private static function evidence_rules(): array {
		$db  = Database::current();
		$out = array();
		foreach ( $db->rows( 'SELECT uid, settings FROM `' . $db->table( 'ccap_exam_rule' ) . "` WHERE status = 'published'" ) as $row ) {
			$settings = json_decode( (string) $row['settings'], true );
			if ( 'practice' !== ( is_array( $settings ) ? ( $settings['purpose'] ?? 'evidence' ) : 'evidence' ) ) {
				$out[] = (string) $row['uid'];
			}
		}
		return $out;
	}

	/**
	 * Require #19.
	 *
	 * @param Actor $actor Actor.
	 * @return void
	 * @throws CourseException `ccap_forbidden`.
	 */
	private static function assert_settings_manager( Actor $actor ): void {
		if ( ! $actor->is_system() && ( $actor->user_id <= 0 || ! user_can( $actor->user_id, Settings::CAP ) ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change settings.', 403 );
		}
	}

	/**
	 * Require M-03 on an existing course (404 without leak, R0-07).
	 *
	 * @param Actor $actor  Actor.
	 * @param int   $course Course.
	 * @return void
	 * @throws CourseException `ccap_not_found`, `ccap_forbidden`.
	 */
	private static function assert_course_editor( Actor $actor, int $course ): void {
		$post = get_post( $course );
		if ( ! $post instanceof \WP_Post || Relations::COURSE_TYPE !== $post->post_type ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::EDIT_COURSE, array( $course ) ), 'Not allowed to edit this course.' );
	}

	/**
	 * Audit entry.
	 *
	 * @param Actor                $actor   Actor.
	 * @param string               $action  Action.
	 * @param string               $type    Object type.
	 * @param string               $id      Object ID.
	 * @param array<string, mixed> $context Context.
	 * @return void
	 */
	private static function audit( Actor $actor, string $action, string $type, string $id, array $context ): void {
		AuditLog::write( Database::current(), $actor, $action, $type, $id, null, $context );
	}
}
