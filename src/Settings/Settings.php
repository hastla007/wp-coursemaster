<?php
/**
 * Settings storage: one option per tab (R0-27 §2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Option `course_captain_settings_<tab>` =
 * `{_schema, _rev, _updated_at, _updated_by, values}`.
 *
 * - Read (S-10): stored value, per field the default when missing or of
 *   the wrong type; never a fatal.
 * - Save (S-1…S-8): only fields in `_fields[]` and the tab schema; a missing
 *   key means "unchanged"; caps per field; all-or-nothing validation;
 *   `_rev` conflict detection; first save materializes all defaults;
 *   side effects; audit `settings_saved` (public/internal with before/after,
 *   personal only "changed", secret never; system actors add `actor`, e.g.
 *   `system:cli`, SR-5); nothing written without change.
 * - Foreign writers (S-9) are rejected when the structure is invalid.
 * - Migration (§2.3): unknown keys of older versions are dropped; newer
 *   schemas pass unknown keys through (downgrade, E-90).
 * - Reset per tab with a one-generation undo (30 days); export without
 *   secrets, personal fields only on opt-in.
 */
final class Settings {

	public const OPTION_PREFIX  = 'course_captain_settings_';
	public const VERSION_OPTION = 'course_captain_settings_version';
	public const UNDO_PREFIX    = 'course_captain_settings_undo_';
	public const UNDO_TTL       = 2592000;
	public const CAP            = 'ccap_manage_settings';

	/**
	 * Input key carrying the typed confirmation word when data deletion is
	 * switched on (SET-CC-30, suite standard S10; replaces the site name of E-89).
	 */
	public const CONFIRM_WORD_FIELD = '_confirm_delete';

	/**
	 * Input key carrying the reason for extending a retention period beyond
	 * its default (E-245, R0-31 RR-3/PSD-015); stored in the audit entry.
	 */
	public const RETENTION_REASON_FIELD = '_retention_reason';

	/** Maximum length of that reason (characters). */
	public const RETENTION_REASON_MAX = 500;

	/**
	 * Accepted confirmation words (compared case-insensitively).
	 *
	 * @var list<string>
	 */
	public const CONFIRM_WORDS      = array( 'LÖSCHEN', 'LOESCHEN', 'DELETE' );
	public const CONFIRM_SLUG_FIELD = '_confirm_slug_conflict';

	/**
	 * Guard against recursion in the foreign-writer filter.
	 *
	 * @var bool
	 */
	private static bool $writing = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			add_filter( 'pre_update_option_' . self::OPTION_PREFIX . $tab, array( self::class, 'guard_foreign_write' ), 10, 3 );
		}
	}

	/**
	 * Read `<tab>.<field>`.
	 *
	 * @param string $key Register key.
	 * @return mixed
	 * @throws CourseException For unknown keys.
	 */
	public static function get( string $key ) {
		list( $tab, $field ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		$fields              = SettingsSchema::fields();
		if ( ! isset( $fields[ $tab ][ $field ] ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown setting.', 400 );
		}
		return self::values( $tab )[ $field ];
	}

	/**
	 * All values of a tab (stored, else per-field default).
	 *
	 * @param string $tab Tab.
	 * @return array<string, mixed>
	 */
	public static function values( string $tab ): array {
		$stored = self::stored( $tab );
		$out    = array();
		foreach ( SettingsSchema::fields()[ $tab ] ?? array() as $field => $def ) {
			if ( array_key_exists( $field, $stored['values'] ) ) {
				$check = SettingsSchema::validate( $def, $stored['values'][ $field ] );
				if ( $check['ok'] ) {
					$out[ $field ] = $check['value'];
					continue;
				}
			}
			$out[ $field ] = $def['default'];
		}
		return $out;
	}

	/**
	 * Current revision of a tab.
	 *
	 * @param string $tab Tab.
	 * @return int
	 */
	public static function rev( string $tab ): int {
		return self::stored( $tab )['_rev'];
	}

	/**
	 * Validate a partial save without writing (S-2…S-5): the candidate of
	 * the tab and the field errors as `field => rule` (rules from
	 * {@see SettingsRules}).
	 *
	 * @param Actor                $actor  Admin.
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $input  Submitted values.
	 * @param array                $fields Rendered fields (`_fields[]`).
	 * @phpstan-param list<string> $fields
	 * @return array{before: array<string, mixed>, candidate: array<string, mixed>, errors: array<string, string>, changed: list<string>, slug: array<string, array{conflicts: mixed, suggestion: string}>}
	 * @throws CourseException `ccap_not_found`, `ccap_forbidden`.
	 */
	public static function check( Actor $actor, string $tab, array $input, array $fields ): array {
		$schema = self::tab_schema( $tab );
		if ( ! self::can( $actor, 'settings' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change settings.', 403 );
		}
		$before    = self::values( $tab );
		$candidate = $before;
		$errors    = array();
		$touched   = array();
		foreach ( array_values( array_unique( $fields ) ) as $field ) {
			if ( ! isset( $schema[ $field ] ) || ! array_key_exists( $field, $input ) ) {
				continue;
			}
			if ( ! self::can( $actor, (string) $schema[ $field ]['cap'] ) ) {
				continue;
			}
			$check = SettingsSchema::validate( $schema[ $field ], $input[ $field ] );
			if ( ! $check['ok'] ) {
				$errors[ $field ] = SettingsRules::invalid_rule( $schema[ $field ], $input[ $field ] );
				continue;
			}
			// SET-CC-30 (S10): switching data deletion on needs a typed word; switching off needs nothing.
			if ( 'general' === $tab && UninstallManifest::DELETE_DATA_KEY === $field && true === $check['value'] && true !== $before[ $field ] && ! self::deletion_confirmed( $input ) ) {
				$errors[ $field ] = 'confirm_word';
				continue;
			}
			$candidate[ $field ] = $check['value'];
			$touched[]           = $field;
		}
		// E-245: a retention period above its default needs a reason (stored in the audit entry).
		if ( 'privacy' === $tab && in_array( 'retention', $touched, true ) && ! isset( $errors['retention'] ) && array() !== \CourseCaptain\Privacy\RetentionPolicy::extended( (array) $before['retention'], (array) $candidate['retention'] ) && null === self::retention_reason( $input ) ) {
			$errors['retention'] = 'retention_reason';
		}
		$errors += SettingsRules::check( $actor, $tab, $candidate, $before, $touched );
		// F4.1 / KOMP-132: a changed public slug that is already in use needs a confirmation.
		$slug_conflicts = array();
		if ( 'general' === $tab && empty( $input[ self::CONFIRM_SLUG_FIELD ] ) ) {
			foreach ( array( 'learning_area_slug', 'course_slug' ) as $field ) {
				// A hard collision from SettingsRules (page/foreign CPT) keeps its rule; the conflict list is added as data.
				$hard = isset( $errors[ $field ] ) && in_array( $errors[ $field ], array( 'collision', 'foreign_cpt' ), true );
				if ( ( isset( $errors[ $field ] ) && ! $hard ) || ! isset( $candidate[ $field ], $before[ $field ] ) || $candidate[ $field ] === $before[ $field ] ) {
					continue;
				}
				$found = \CourseCaptain\Ops\Compatibility::slug_conflicts( (string) $candidate[ $field ], $field );
				if ( array() !== $found ) {
					$errors[ $field ]         = $hard ? $errors[ $field ] : 'slug_conflict';
					$slug_conflicts[ $field ] = array(
						'conflicts'  => $found,
						'suggestion' => \CourseCaptain\Ops\Compatibility::slug_suggestion( (string) $candidate[ $field ], $field ),
					);
				}
			}
		}
		$changed = array_keys( array_filter( $candidate, static fn( $value, $key ) => $value !== $before[ $key ], ARRAY_FILTER_USE_BOTH ) );
		return array(
			'before'    => $before,
			'candidate' => $candidate,
			'errors'    => $errors,
			'changed'   => $changed,
			'slug'      => $slug_conflicts,
		);
	}

	/**
	 * Partial save of a tab.
	 *
	 * `$context` is added to the audit entry: `context` (`settings`,
	 * `setup`, `import`, `country_profile`), `step` (setup step key),
	 * `profile` (country profile ID, SET-CC-07) and `confirmed_risks`
	 * (RC numbers, R0-30 D-8). Risk tokens are enforced by the admin
	 * entry points ({@see RiskConfirm}); this service trusts its caller.
	 * A retention period extended beyond its default (E-245) puts the
	 * reason from `_retention_reason` into the audit column `reason` and
	 * the keys into `retention_extended`.
	 *
	 * @param Actor                $actor   Admin.
	 * @param string               $tab     Tab.
	 * @param array<string, mixed> $input   Submitted values.
	 * @param array                $fields  Rendered fields (`_fields[]`).
	 * @param int                  $rev     `_rev` the form was rendered with.
	 * @param array<string, mixed> $context Audit context.
	 * @phpstan-param list<string> $fields
	 * @return array{changed: list<string>, rev: int}
	 * @throws CourseException `ccap_forbidden`, `ccap_validation_failed` (field list, `data.fields` = field => rule), `ccap_settings_conflict`.
	 */
	public static function save( Actor $actor, string $tab, array $input, array $fields, int $rev, array $context = array() ): array {
		$schema = self::tab_schema( $tab );
		if ( ! self::can( $actor, 'settings' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change settings.', 403 );
		}
		$stored = self::stored( $tab );
		if ( $rev !== $stored['_rev'] ) {
			throw new CourseException( 'ccap_settings_conflict', 'Settings were changed in the meantime.', 409, array( 'rev' => $stored['_rev'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
		}
		$result = self::check( $actor, $tab, $input, $fields );
		if ( array() !== $result['errors'] ) {
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', array_keys( $result['errors'] ) ) ), 422, array_merge( array( 'fields' => $result['errors'] ), array() === $result['slug'] ? array() : array( 'slug_conflicts' => $result['slug'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
		}
		$before    = $result['before'];
		$candidate = $result['candidate'];
		$changed   = $result['changed'];
		if ( array() === $changed && 0 !== $stored['_rev'] ) {
			return array(
				'changed' => array(),
				'rev'     => $stored['_rev'],
			);
		}
		if ( 'privacy' === $tab && in_array( 'retention', $changed, true ) ) {
			$extended = \CourseCaptain\Privacy\RetentionPolicy::extended( (array) $before['retention'], (array) $candidate['retention'] );
			if ( array() !== $extended ) {
				$context['retention_extended'] = $extended;
				$context['reason']             = (string) self::retention_reason( $input );
			}
		}
		$new_rev = self::write( $tab, $candidate + $stored['passthrough'], $actor, $stored['_rev'] );
		self::side_effects( $schema, $changed );
		if ( array() !== $changed ) {
			self::audit( $actor, 'settings_saved', $tab, $schema, $changed, $before, $candidate, $context );
		}
		return array(
			'changed' => $changed,
			'rev'     => $new_rev,
		);
	}

	/**
	 * Display name and time of the last writer of a tab (T-7).
	 *
	 * @param string $tab Tab.
	 * @return array{user_id: int, at: string}
	 */
	public static function last_writer( string $tab ): array {
		$raw = get_option( self::OPTION_PREFIX . $tab );
		return array(
			'user_id' => is_array( $raw ) ? (int) ( $raw['_updated_by'] ?? 0 ) : 0,
			'at'      => is_array( $raw ) ? (string) ( $raw['_updated_at'] ?? '' ) : '',
		);
	}

	/**
	 * Whether the undo slot of a tab is filled and not expired.
	 *
	 * @param string $tab Tab.
	 * @return int Expiry timestamp, 0 when there is nothing to undo.
	 */
	public static function undo_until( string $tab ): int {
		$undo = get_option( self::UNDO_PREFIX . $tab );
		$exp  = is_array( $undo ) ? (int) ( $undo['expires_at'] ?? 0 ) : 0;
		return $exp >= time() && is_array( $undo['values'] ?? null ) ? $exp : 0;
	}

	/**
	 * Whether the actor may see/change fields of a cap class.
	 *
	 * @param Actor  $actor Actor.
	 * @param string $cap   settings|admin.
	 * @return bool
	 */
	public static function allowed( Actor $actor, string $cap ): bool {
		return self::can( $actor, $cap );
	}

	/**
	 * Whether the input carries a confirmation word for the data deletion:
	 * LÖSCHEN, LOESCHEN or DELETE in any letter case (SET-CC-30, server side).
	 *
	 * @param array<string, mixed> $input Submitted values.
	 * @return bool
	 */
	public static function deletion_confirmed( array $input ): bool {
		$typed = $input[ self::CONFIRM_WORD_FIELD ] ?? null;
		return is_string( $typed ) && self::is_confirm_word( $typed );
	}

	/**
	 * Reason for extending a retention period (E-245): trimmed plain text,
	 * null when missing or longer than {@see self::RETENTION_REASON_MAX}.
	 *
	 * @param array<string, mixed> $input Submitted values.
	 * @return string|null
	 */
	public static function retention_reason( array $input ): ?string {
		$raw    = $input[ self::RETENTION_REASON_FIELD ] ?? null;
		$reason = is_string( $raw ) ? trim( sanitize_textarea_field( $raw ) ) : '';
		return '' === $reason || mb_strlen( $reason ) > self::RETENTION_REASON_MAX ? null : $reason;
	}

	/**
	 * Whether a text is one of the confirmation words (case-insensitive,
	 * surrounding spaces ignored, composed and decomposed umlauts alike).
	 *
	 * @param string $typed Typed text.
	 * @return bool
	 */
	public static function is_confirm_word( string $typed ): bool {
		$typed = trim( $typed );
		if ( class_exists( '\Normalizer' ) ) {
			$typed = (string) \Normalizer::normalize( $typed, \Normalizer::FORM_C );
		}
		return in_array( mb_strtoupper( $typed, 'UTF-8' ), self::CONFIRM_WORDS, true );
	}

	/**
	 * Reset a tab to its defaults; the previous state goes to the undo slot.
	 *
	 * @param Actor  $actor Admin.
	 * @param string $tab   Tab.
	 * @return list<string> Changed keys.
	 * @throws CourseException When not allowed.
	 */
	public static function reset( Actor $actor, string $tab ): array {
		$schema = self::tab_schema( $tab );
		if ( ! self::can( $actor, 'settings' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change settings.', 403 );
		}
		$before = self::values( $tab );
		$after  = $before;
		foreach ( $schema as $field => $def ) {
			if ( self::can( $actor, (string) $def['cap'] ) ) {
				$after[ $field ] = $def['default'];
			}
		}
		update_option(
			self::UNDO_PREFIX . $tab,
			array(
				'values'     => $before,
				'expires_at' => time() + self::UNDO_TTL,
			),
			false
		);
		$changed = array_keys( array_filter( $after, static fn( $value, $key ) => $value !== $before[ $key ], ARRAY_FILTER_USE_BOTH ) );
		self::write( $tab, $after + self::stored( $tab )['passthrough'], $actor, self::rev( $tab ) );
		self::side_effects( $schema, $changed );
		self::audit( $actor, 'settings_reset', $tab, $schema, $changed, $before, $after );
		return $changed;
	}

	/**
	 * Retention sub-keys that an undo of the privacy tab would extend
	 * beyond their default (E-245): the undo restores the old values, so it
	 * needs a reason like any other extension. Empty for other tabs and
	 * when there is nothing to undo.
	 *
	 * @param string $tab Tab.
	 * @return list<string>
	 */
	public static function undo_extended( string $tab ): array {
		if ( 'privacy' !== $tab || 0 === self::undo_until( $tab ) ) {
			return array();
		}
		$undo = (array) get_option( self::UNDO_PREFIX . $tab );
		if ( ! is_array( $undo['values']['retention'] ?? null ) ) {
			return array();
		}
		return \CourseCaptain\Privacy\RetentionPolicy::extended( (array) ( self::values( $tab )['retention'] ?? array() ), (array) $undo['values']['retention'] );
	}

	/**
	 * Undo the last reset/import of a tab (one generation, 30 days).
	 *
	 * When the undo of the privacy tab restores retention periods above
	 * their default (E-245, {@see self::undo_extended()}), it needs a reason
	 * in `_retention_reason`; the reason goes to the audit column `reason`,
	 * the keys to `retention_extended`.
	 *
	 * @param Actor                $actor Admin.
	 * @param string               $tab   Tab.
	 * @param array<string, mixed> $input Submitted values (`_retention_reason`).
	 * @return bool Restored.
	 * @throws CourseException When not allowed; `ccap_validation_failed` (`data.fields.retention` = `retention_reason`) without a needed reason.
	 */
	public static function undo( Actor $actor, string $tab, array $input = array() ): bool {
		$schema = self::tab_schema( $tab );
		if ( ! self::can( $actor, 'settings' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change settings.', 403 );
		}
		$undo = get_option( self::UNDO_PREFIX . $tab );
		if ( ! is_array( $undo ) || (int) ( $undo['expires_at'] ?? 0 ) < time() || ! is_array( $undo['values'] ?? null ) ) {
			return false;
		}
		$context  = array( 'context' => 'undo' );
		$extended = self::undo_extended( $tab );
		if ( array() !== $extended ) {
			$reason = self::retention_reason( $input );
			if ( null === $reason ) {
				throw new CourseException( 'ccap_validation_failed', 'retention', 422, array( 'fields' => array( 'retention' => 'retention_reason' ) ) );
			}
			$context['retention_extended'] = $extended;
			$context['reason']             = $reason;
		}
		$before = self::values( $tab );
		self::write( $tab, (array) $undo['values'], $actor, self::rev( $tab ) );
		delete_option( self::UNDO_PREFIX . $tab );
		$after   = self::values( $tab );
		$changed = array_keys( array_filter( $after, static fn( $value, $key ) => $value !== $before[ $key ], ARRAY_FILTER_USE_BOTH ) );
		self::side_effects( $schema, $changed );
		self::audit( $actor, 'settings_saved', $tab, $schema, $changed, $before, $after, $context );
		return true;
	}

	/**
	 * Migrate after a plugin update (admin request): only when stored tabs
	 * carry an older register version (SET-CC-13: v1 → v2 keeps every
	 * stored value; new keys read their defaults, which equal the former
	 * constants).
	 *
	 * @return list<string> Migrated tabs.
	 */
	public static function maybe_migrate(): array {
		$version = get_option( self::VERSION_OPTION );
		if ( false === $version || (int) $version >= SettingsSchema::VERSION ) {
			return array();
		}
		return self::migrate();
	}

	/**
	 * Migrate all tabs to the current schema (idempotent, §2.3).
	 *
	 * @return list<string> Migrated tabs.
	 */
	public static function migrate(): array {
		$migrated = self::move_keys();
		foreach ( array_keys( SettingsSchema::TABS ) as $tab ) {
			$raw = get_option( self::OPTION_PREFIX . $tab );
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$schema_version = (int) ( $raw['_schema'] ?? 0 );
			if ( $schema_version >= SettingsSchema::VERSION ) {
				continue;
			}
			// v0 (unstructured or older): keep known, valid keys; drop unknown ones (GM-181).
			$values = is_array( $raw['values'] ?? null ) ? $raw['values'] : array_diff_key( $raw, array_flip( array( '_schema', '_rev', '_updated_at', '_updated_by' ) ) );
			$known  = array();
			foreach ( SettingsSchema::fields()[ $tab ] as $field => $def ) {
				if ( array_key_exists( $field, $values ) && SettingsSchema::validate( $def, $values[ $field ] )['ok'] ) {
					$known[ $field ] = SettingsSchema::validate( $def, $values[ $field ] )['value'];
				}
			}
			self::$writing = true;
			try {
				update_option(
					self::OPTION_PREFIX . $tab,
					array(
						'_schema'     => SettingsSchema::VERSION,
						'_rev'        => (int) ( $raw['_rev'] ?? 0 ),
						'_updated_at' => gmdate( 'c' ),
						'_updated_by' => 0,
						'values'      => $known,
					),
					SettingsSchema::TABS[ $tab ]
				);
			} finally {
				self::$writing = false;
			}
			$migrated[] = $tab;
		}
		$migrated = array_values( array_unique( $migrated ) );
		// SET-009: correct autoload flags of existing tab options from the register (§2.1 rule 3).
		$flags = array();
		foreach ( SettingsSchema::TABS as $tab => $autoload ) {
			if ( false !== get_option( self::OPTION_PREFIX . $tab ) ) {
				$flags[ self::OPTION_PREFIX . $tab ] = $autoload;
			}
		}
		if ( array() !== $flags && function_exists( 'wp_set_option_autoload_values' ) ) {
			wp_set_option_autoload_values( $flags );
		}
		// A fresh install writes nothing; the first save sets the version.
		if ( array() !== $migrated || false !== get_option( self::VERSION_OPTION ) ) {
			update_option( self::VERSION_OPTION, SettingsSchema::VERSION, true );
		}
		return $migrated;
	}

	/**
	 * Moved keys (register v4, {@see SettingsSchema::MOVED}): the stored,
	 * valid value of a tab older than the moving version goes into the
	 * object of its new tab before the old tab drops the key. Idempotent:
	 * a tab at the moving version or newer is never read again.
	 *
	 * @return list<string> Tabs written.
	 */
	private static function move_keys(): array {
		$fields = SettingsSchema::fields();
		$carry  = array();
		foreach ( SettingsSchema::MOVED as $from => $move ) {
			list( $to, $since )    = $move;
			list( $ftab, $ffield ) = explode( '.', $from, 2 );
			$raw                   = get_option( self::OPTION_PREFIX . $ftab );
			if ( ! is_array( $raw ) || (int) ( $raw['_schema'] ?? 0 ) >= $since ) {
				continue;
			}
			$values = is_array( $raw['values'] ?? null ) ? $raw['values'] : $raw;
			if ( ! array_key_exists( $ffield, $values ) ) {
				continue;
			}
			list( $ttab, $tfield, $tsub ) = explode( '.', $to, 3 );
			$def                          = $fields[ $ttab ][ $tfield ]['fields'][ $tsub ] ?? null;
			$check                        = null === $def ? array(
				'ok'    => false,
				'value' => null,
			) : SettingsSchema::validate( $def, $values[ $ffield ] );
			if ( $check['ok'] ) {
				$carry[ $ttab ][ $tfield ][ $tsub ] = $check['value'];
			}
		}
		$written = array();
		foreach ( $carry as $tab => $objects ) {
			$raw    = get_option( self::OPTION_PREFIX . $tab );
			$raw    = is_array( $raw ) ? $raw : array(
				'_schema' => SettingsSchema::VERSION,
				'_rev'    => 0,
				'values'  => array(),
			);
			$values = is_array( $raw['values'] ?? null ) ? $raw['values'] : array_diff_key( $raw, array_flip( array( '_schema', '_rev', '_updated_at', '_updated_by' ) ) );
			foreach ( $objects as $field => $subs ) {
				$base             = is_array( $values[ $field ] ?? null ) ? $values[ $field ] : (array) $fields[ $tab ][ $field ]['default'];
				$values[ $field ] = array_merge( $base, $subs );
			}
			self::$writing = true;
			try {
				update_option(
					self::OPTION_PREFIX . $tab,
					array(
						'_schema'     => (int) ( $raw['_schema'] ?? 0 ),
						'_rev'        => (int) ( $raw['_rev'] ?? 0 ),
						'_updated_at' => gmdate( 'c' ),
						'_updated_by' => 0,
						'values'      => $values,
					),
					SettingsSchema::TABS[ $tab ]
				);
			} finally {
				self::$writing = false;
			}
			$written[] = $tab;
		}
		return $written;
	}

	/**
	 * Export (R0-27 §2.5): public/internal; personal only on opt-in; secret never.
	 *
	 * @param Actor $actor           Admin.
	 * @param bool  $include_personal Opt-in for personal fields.
	 * @return array<string, mixed>
	 * @throws CourseException When not allowed.
	 */
	public static function export( Actor $actor, bool $include_personal = false ): array {
		if ( ! self::can( $actor, 'settings' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to export settings.', 403 );
		}
		$tabs = array();
		foreach ( SettingsSchema::fields() as $tab => $schema ) {
			$values = array();
			foreach ( self::values( $tab ) as $field => $value ) {
				$def = $schema[ $field ];
				if ( true !== $def['export'] || 'secret' === $def['privacy'] || ( 'personal' === $def['privacy'] && ! $include_personal ) ) {
					continue;
				}
				$values[ $field ] = $value;
			}
			$tabs[ $tab ] = array(
				'_schema' => SettingsSchema::VERSION,
				'values'  => $values,
			);
		}
		return array(
			'format'           => 'course-captain-settings',
			'format_version'   => 1,
			'plugin_version'   => defined( 'COURSE_CAPTAIN_VERSION' ) ? (string) constant( 'COURSE_CAPTAIN_VERSION' ) : '',
			'settings_version' => SettingsSchema::VERSION,
			'exported_at'      => gmdate( 'c' ),
			'site_hash'        => hash( 'sha256', home_url() ),
			'tabs'             => $tabs,
		);
	}

	/**
	 * S-9: foreign writes must keep a valid structure.
	 *
	 * @param mixed  $value     New value.
	 * @param mixed  $old_value Old value.
	 * @param string $option    Option name.
	 * @return mixed
	 */
	public static function guard_foreign_write( $value, $old_value, $option = '' ) {
		if ( self::$writing ) {
			return $value;
		}
		$tab    = substr( (string) $option, strlen( self::OPTION_PREFIX ) );
		$schema = SettingsSchema::fields()[ $tab ] ?? array();
		if ( ! is_array( $value ) || ! is_array( $value['values'] ?? null ) ) {
			return $old_value;
		}
		foreach ( $value['values'] as $field => $raw ) {
			if ( ! isset( $schema[ $field ] ) || ! SettingsSchema::validate( $schema[ $field ], $raw )['ok'] ) {
				return $old_value;
			}
		}
		return $value;
	}

	/**
	 * Whether the actor has the right of a field class.
	 *
	 * @param Actor  $actor Actor.
	 * @param string $cap   settings|admin.
	 * @return bool
	 */
	private static function can( Actor $actor, string $cap ): bool {
		if ( $actor->is_system() ) {
			return true;
		}
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, self::CAP ) ) {
			return false;
		}
		return 'admin' !== $cap || user_can( $actor->user_id, 'manage_options' );
	}

	/**
	 * Parsed option.
	 *
	 * @param string $tab Tab.
	 * @return array{_schema: int, _rev: int, values: array<string, mixed>, passthrough: array<string, mixed>}
	 */
	private static function stored( string $tab ): array {
		$raw    = get_option( self::OPTION_PREFIX . $tab );
		$raw    = is_array( $raw ) ? $raw : array();
		$values = is_array( $raw['values'] ?? null ) ? $raw['values'] : array();
		$schema = (int) ( $raw['_schema'] ?? SettingsSchema::VERSION );
		// Downgrade (E-90): keys of a newer schema are kept unchanged.
		$passthrough = $schema > SettingsSchema::VERSION ? array_diff_key( $values, SettingsSchema::fields()[ $tab ] ?? array() ) : array();
		return array(
			'_schema'     => $schema,
			'_rev'        => (int) ( $raw['_rev'] ?? 0 ),
			'values'      => $values,
			'passthrough' => $passthrough,
		);
	}

	/**
	 * Write one tab atomically.
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $values Values.
	 * @param Actor                $actor  Writer.
	 * @param int                  $rev    Previous revision.
	 * @return int New revision.
	 */
	private static function write( string $tab, array $values, Actor $actor, int $rev ): int {
		self::$writing = true;
		try {
			update_option(
				self::OPTION_PREFIX . $tab,
				array(
					'_schema'     => max( SettingsSchema::VERSION, self::stored( $tab )['_schema'] ),
					'_rev'        => $rev + 1,
					'_updated_at' => gmdate( 'c' ),
					'_updated_by' => $actor->user_id,
					'values'      => $values,
				),
				SettingsSchema::TABS[ $tab ]
			);
		} finally {
			self::$writing = false;
		}
		if ( false === get_option( self::VERSION_OPTION ) ) {
			update_option( self::VERSION_OPTION, SettingsSchema::VERSION, true );
		}
		return $rev + 1;
	}

	/**
	 * Side effects in fixed order (S-8).
	 *
	 * @param array<string, array<string, mixed>> $schema  Tab schema.
	 * @param array                               $changed Changed keys.
	 * @phpstan-param list<string> $changed
	 * @return void
	 */
	private static function side_effects( array $schema, array $changed ): void {
		$effects = array();
		foreach ( $changed as $field ) {
			$effects = array_merge( $effects, (array) $schema[ $field ]['side_effects'] );
		}
		if ( in_array( 'rewrite_flush', $effects, true ) ) {
			delete_option( Plugin::REWRITE_VERSION_OPTION );
		}
		if ( array() !== $changed ) {
			\CourseCaptain\Cache\CacheStore::bump_content();
			do_action( 'course_captain_policy_changed' );
		}
	}

	/**
	 * Audit with privacy classes (SR-6).
	 *
	 * @param Actor                               $actor   Actor.
	 * @param string                              $action  Action.
	 * @param string                              $tab     Tab.
	 * @param array<string, array<string, mixed>> $schema  Schema.
	 * @param array                               $changed Changed keys.
	 * @param array<string, mixed>                $before  Before.
	 * @param array<string, mixed>                $after   After.
	 * @param array<string, mixed>                $context Extra audit context (`context`, `step`, `confirmed_risks`).
	 * @phpstan-param list<string> $changed
	 * @return void
	 */
	private static function audit( Actor $actor, string $action, string $tab, array $schema, array $changed, array $before, array $after, array $context = array() ): void {
		$diff = array();
		foreach ( $changed as $field ) {
			$privacy        = (string) $schema[ $field ]['privacy'];
			$diff[ $field ] = in_array( $privacy, array( 'public', 'internal' ), true )
				? array(
					'before' => $before[ $field ],
					'after'  => $after[ $field ],
				)
				: 'changed';
		}
		AuditLog::write(
			Database::current(),
			$actor,
			$action,
			'settings',
			$tab,
			isset( $context['reason'] ) && '' !== $context['reason'] ? (string) $context['reason'] : null,
			array(
				'tab'     => $tab,
				'changed' => $diff,
			) + array_intersect_key( $context, array_flip( array( 'context', 'step', 'confirmed_risks', 'profile', 'retention_extended' ) ) ) + ( $actor->is_system() ? array( 'actor' => $actor->type ) : array() )
		);
	}

	/**
	 * Schema of a known tab.
	 *
	 * @param string $tab Tab.
	 * @return array<string, array<string, mixed>>
	 * @throws CourseException For unknown tabs.
	 */
	private static function tab_schema( string $tab ): array {
		$schema = SettingsSchema::fields()[ $tab ] ?? null;
		if ( null === $schema ) {
			throw new CourseException( 'ccap_not_found', 'Unknown settings tab.', 404 );
		}
		return $schema;
	}
}
