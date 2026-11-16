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
 *   personal only "changed", secret never); nothing written without change.
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

	/** Input key carrying the typed site name when data deletion is switched on (E-89). */
	public const CONFIRM_SITE_FIELD = '_confirm_site_name';

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
	 * @return array{before: array<string, mixed>, candidate: array<string, mixed>, errors: array<string, string>, changed: list<string>}
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
			// E-89: switching data deletion on needs the typed site name (R0-27 §1.3, R0-16 §2.5).
			if ( 'general' === $tab && UninstallManifest::DELETE_DATA_KEY === $field && true === $check['value'] && true !== $before[ $field ] && ! self::site_name_confirmed( $input ) ) {
				$errors[ $field ] = 'site_name';
				continue;
			}
			$candidate[ $field ] = $check['value'];
			$touched[]           = $field;
		}
		$errors += SettingsRules::check( $actor, $tab, $candidate, $before, $touched );
		$changed = array_keys( array_filter( $candidate, static fn( $value, $key ) => $value !== $before[ $key ], ARRAY_FILTER_USE_BOTH ) );
		return array(
			'before'    => $before,
			'candidate' => $candidate,
			'errors'    => $errors,
			'changed'   => $changed,
		);
	}

	/**
	 * Partial save of a tab.
	 *
	 * `$context` is added to the audit entry: `context` (`settings`,
	 * `setup`, `import`), `step` (setup step key) and `confirmed_risks`
	 * (RC numbers, R0-30 D-8). Risk tokens are enforced by the admin
	 * entry points ({@see RiskConfirm}); this service trusts its caller.
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
			throw new CourseException( 'ccap_validation_failed', esc_html( implode( ',', array_keys( $result['errors'] ) ) ), 422, array( 'fields' => $result['errors'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Data for the caller, not output.
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
	 * Whether the input carries the exact site name (E-89 confirmation).
	 *
	 * @param array<string, mixed> $input Submitted values.
	 * @return bool
	 */
	public static function site_name_confirmed( array $input ): bool {
		$typed = $input[ self::CONFIRM_SITE_FIELD ] ?? null;
		if ( ! is_string( $typed ) ) {
			return false;
		}
		$name = trim( wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );
		if ( '' === $name ) {
			$name = (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST );
		}
		return '' !== $name && trim( $typed ) === $name;
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
	 * Undo the last reset/import of a tab (one generation, 30 days).
	 *
	 * @param Actor  $actor Admin.
	 * @param string $tab   Tab.
	 * @return bool Restored.
	 * @throws CourseException When not allowed.
	 */
	public static function undo( Actor $actor, string $tab ): bool {
		$schema = self::tab_schema( $tab );
		if ( ! self::can( $actor, 'settings' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change settings.', 403 );
		}
		$undo = get_option( self::UNDO_PREFIX . $tab );
		if ( ! is_array( $undo ) || (int) ( $undo['expires_at'] ?? 0 ) < time() || ! is_array( $undo['values'] ?? null ) ) {
			return false;
		}
		$before = self::values( $tab );
		self::write( $tab, (array) $undo['values'], $actor, self::rev( $tab ) );
		delete_option( self::UNDO_PREFIX . $tab );
		$after   = self::values( $tab );
		$changed = array_keys( array_filter( $after, static fn( $value, $key ) => $value !== $before[ $key ], ARRAY_FILTER_USE_BOTH ) );
		self::side_effects( $schema, $changed );
		self::audit( $actor, 'settings_saved', $tab, $schema, $changed, $before, $after );
		return true;
	}

	/**
	 * Migrate all tabs to the current schema (idempotent, §2.3).
	 *
	 * @return list<string> Migrated tabs.
	 */
	public static function migrate(): array {
		$migrated = array();
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
		// A fresh install writes nothing; the first save sets the version.
		if ( array() !== $migrated || false !== get_option( self::VERSION_OPTION ) ) {
			update_option( self::VERSION_OPTION, SettingsSchema::VERSION, true );
		}
		return $migrated;
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
				if ( empty( $def['export'] ) || 'secret' === $def['privacy'] || ( 'personal' === $def['privacy'] && ! $include_personal ) ) {
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
			null,
			array(
				'tab'     => $tab,
				'changed' => $diff,
			) + array_intersect_key( $context, array_flip( array( 'context', 'step', 'confirmed_risks' ) ) )
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
