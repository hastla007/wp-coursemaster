<?php
/**
 * Settings import (R0-27 §2.5, R0-31 §4.2 IM-1…IM-5, SET-016, PSD-058/059).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Same validator as the forms. Per tab atomic with an undo snapshot; risk
 * fields need the same confirmation as in the UI (`tab.field` in
 * `confirmed`), otherwise they are skipped and reported (IM-1).
 * Environment-bound fields (`export` false or `never_env`), secrets and
 * placeholders are ignored (IM-3, SH-6); a foreign `site_hash` is only a
 * notice (IM-4). Newer `settings_version` or another format is rejected.
 * Rate limit 10 imports per account and hour, fail-closed.
 *
 * SET-CC-15: besides `tabs` the file carries one top-level section per
 * {@see TransferProvider} (built in: `locks`, `roles`; mail templates plug
 * in through `course_captain_settings_transfer_providers`). Sections are
 * previewed as a diff like tabs, selected separately (`surfaces`) and
 * applied atomically per section; confirmations are `<key>.<item>`, a
 * trailing `*` confirms every item of a tab/section, a bare `*` all.
 *
 * SET-CC-01: add-on fields (`pro.*`, R0-27 SR-1) belong to the add-on. The
 * core never writes, drops or rewrites them: a `pro` section is handed
 * unchanged to the transfer provider with the key `pro` when the add-on
 * registered one; otherwise the section, `pro.*` tabs and `pro.*` keys
 * inside core tabs are left untouched and listed under `addon` (notice
 * `addon_kept`) instead of being reported as unknown.
 */
final class SettingsTransfer {

	public const MAX_BYTES    = 262144;
	public const PER_HOUR     = 10;
	public const RATE_PREFIX  = 'ccap_settings_import_';
	public const PLACEHOLDERS = array( '<gesetzt>', '<set>', '<leer>', '<empty>', '<konstante>', '<constant>' );
	public const PROVIDERS    = 'course_captain_settings_transfer_providers';
	public const ADDON_KEY    = 'pro';

	/**
	 * Head keys of the file a provider may not use.
	 *
	 * @var list<string>
	 */
	private const RESERVED = array( 'format', 'format_version', 'plugin_version', 'settings_version', 'exported_at', 'site_hash', 'tabs', 'notices' );

	/**
	 * Registered transfer surfaces by key (built in: locks, roles).
	 *
	 * @return array<string, TransferProvider>
	 */
	public static function providers(): array {
		$list = array( new LocksTransfer(), new RolesTransfer() );
		/**
		 * Extra surfaces of the settings file (e.g. mail templates, R0-27 §2.5).
		 *
		 * @since 1.0.0
		 * @param list<TransferProvider> $list Providers.
		 */
		$filtered = apply_filters( 'course_captain_settings_transfer_providers', $list );
		$out      = array();
		foreach ( is_array( $filtered ) ? $filtered : $list as $provider ) {
			if ( ! $provider instanceof TransferProvider ) {
				continue;
			}
			$key = $provider->key();
			if ( 1 !== preg_match( '/^[a-z0-9_]{1,40}$/', $key ) || in_array( $key, self::RESERVED, true ) || isset( $out[ $key ] ) ) {
				continue;
			}
			$out[ $key ] = $provider;
		}
		return $out;
	}

	/**
	 * Export with all surfaces (R0-27 §2.5, EX-1…EX-5): tabs as in
	 * {@see Settings::export()}, plus every provider the actor may export;
	 * opt-in providers only when named in `include`. Audited without values.
	 *
	 * @param Actor                $actor   Admin or `system:cli`.
	 * @param array<string, mixed> $options `include_personal` (bool), `include` (list of provider keys), `context` (settings|cli).
	 * @return array<string, mixed>
	 * @throws CourseException `ccap_forbidden`.
	 */
	public static function export( Actor $actor, array $options = array() ): array {
		$data     = Settings::export( $actor, ! empty( $options['include_personal'] ) );
		$include  = array_map( 'strval', (array) ( $options['include'] ?? array() ) );
		$surfaces = array();
		foreach ( self::providers() as $key => $provider ) {
			if ( ( $provider->opt_in() && ! in_array( $key, $include, true ) ) || ! $provider->can_export( $actor ) ) {
				continue;
			}
			$data[ $key ] = $provider->export( $actor );
			$surfaces[]   = $key;
		}
		AuditLog::write(
			Database::current(),
			$actor,
			'settings_exported',
			'settings',
			'export',
			null,
			array(
				'surfaces'         => $surfaces,
				'include_personal' => ! empty( $options['include_personal'] ),
				'context'          => (string) ( $options['context'] ?? 'settings' ),
			) + ( $actor->is_system() ? array( 'actor' => $actor->type ) : array() )
		);
		return $data;
	}

	/**
	 * Whether `<scope>.<item>` is confirmed (exact, `<scope>.*` or `*`).
	 *
	 * @param array  $confirmed Confirmations.
	 * @param string $scope     Tab or provider key.
	 * @param string $item      Field or item.
	 * @phpstan-param list<string> $confirmed
	 * @return bool
	 */
	public static function is_confirmed( array $confirmed, string $scope, string $item ): bool {
		return in_array( '*', $confirmed, true ) || in_array( $scope . '.*', $confirmed, true ) || in_array( $scope . '.' . $item, $confirmed, true );
	}

	/**
	 * Parse an uploaded file.
	 *
	 * @param string $raw Raw JSON.
	 * @return array<string, mixed>
	 * @throws CourseException `ccap_settings_import_invalid` (format), `ccap_settings_version_newer`.
	 */
	public static function parse( string $raw ): array {
		if ( strlen( $raw ) > self::MAX_BYTES ) {
			throw new CourseException( 'ccap_settings_import_invalid', 'The file is larger than 256 KB.', 413 );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || 'course-captain-settings' !== ( $data['format'] ?? '' ) || 1 !== (int) ( $data['format_version'] ?? 0 ) || ! is_array( $data['tabs'] ?? null ) ) {
			throw new CourseException( 'ccap_settings_import_invalid', 'Not a Course Captain settings file.', 422 );
		}
		if ( (int) ( $data['settings_version'] ?? 0 ) > SettingsSchema::VERSION ) {
			throw new CourseException( 'ccap_settings_version_newer', 'The file comes from a newer version.', 422 );
		}
		return $data;
	}

	/**
	 * Preview or apply an import.
	 *
	 * @param Actor                $actor   Admin.
	 * @param array<string, mixed> $data    Parsed file.
	 * @param array<string, mixed> $options `tabs` (list|null), `surfaces` (list|null), `confirmed` (list of `tab.field` / `key.item`, `*` wildcards), `apply` (bool), `context` (audit, default `import`).
	 * @return array{notices: list<string>, tabs: array<string, array{changed: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: array<string, string>}>, applied: list<string>, surfaces: array<string, array{label: string, changed: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: array<string, string>}>, applied_surfaces: list<string>, addon: list<string>}
	 * @throws CourseException `ccap_forbidden`, `ccap_rate_limited`.
	 */
	public static function import( Actor $actor, array $data, array $options = array() ): array {
		if ( ! Settings::allowed( $actor, 'settings' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to import settings.', 403 );
		}
		$apply     = ! empty( $options['apply'] );
		$selected  = isset( $options['tabs'] ) && is_array( $options['tabs'] ) ? array_map( 'strval', $options['tabs'] ) : null;
		$confirmed = array_values( array_map( 'strval', (array) ( $options['confirmed'] ?? array() ) ) );
		$surfaces  = isset( $options['surfaces'] ) && is_array( $options['surfaces'] ) ? array_map( 'strval', $options['surfaces'] ) : null;
		if ( $apply ) {
			self::rate_limit( $actor );
		}
		$report = array(
			'notices'          => array(),
			'tabs'             => array(),
			'applied'          => array(),
			'surfaces'         => array(),
			'applied_surfaces' => array(),
			'addon'            => array(),
		);
		// Register v4 (SET-CC-02): values of moved keys in older files go to their new place.
		$data = SettingsSchema::upgrade_file( $data, static fn( string $key ): array => (array) Settings::get( $key ) );
		if ( (string) ( $data['site_hash'] ?? '' ) !== hash( 'sha256', home_url() ) ) {
			$report['notices'][] = 'other_site';
		}
		$fields_all = SettingsSchema::fields();
		$keys       = array();
		foreach ( (array) $data['tabs'] as $tab => $content ) {
			$tab = (string) $tab;
			if ( self::ADDON_KEY === $tab || str_starts_with( $tab, self::ADDON_KEY . '.' ) ) {
				foreach ( array_keys( is_array( $content['values'] ?? null ) ? $content['values'] : array() ) as $field ) {
					$report['addon'][] = ( self::ADDON_KEY === $tab ? '' : $tab . '.' ) . (string) $field;
				}
				continue;
			}
			if ( ! isset( $fields_all[ $tab ] ) || ( null !== $selected && ! in_array( $tab, $selected, true ) ) ) {
				continue;
			}
			$schema = $fields_all[ $tab ];
			$values = is_array( $content['values'] ?? null ) ? $content['values'] : array();
			$before = Settings::values( $tab );
			$entry  = array(
				'changed' => array(),
				'skipped' => array(),
				'errors'  => array(),
			);
			$input  = array();
			foreach ( $values as $field => $value ) {
				$field = (string) $field;
				$def   = $schema[ $field ] ?? null;
				if ( str_starts_with( $field, self::ADDON_KEY . '.' ) ) {
					$report['addon'][] = $field;
					continue;
				}
				if ( null === $def ) {
					$entry['skipped'][] = array(
						'field'  => $field,
						'reason' => 'unknown',
					);
					continue;
				}
				if ( true !== $def['export'] ) {
					$entry['skipped'][] = array(
						'field'  => $field,
						'reason' => 'environment',
					);
					continue;
				}
				if ( 'secret' === $def['privacy'] || ( is_string( $value ) && in_array( $value, self::PLACEHOLDERS, true ) ) ) {
					$entry['skipped'][] = array(
						'field'  => $field,
						'reason' => 'secret',
					);
					continue;
				}
				if ( ! Settings::allowed( $actor, (string) $def['cap'] ) ) {
					$entry['skipped'][] = array(
						'field'  => $field,
						'reason' => 'forbidden',
					);
					continue;
				}
				$check = SettingsSchema::validate( $def, $value );
				if ( ! $check['ok'] ) {
					$entry['errors'][ $field ] = SettingsRules::invalid_rule( $def, $value );
					continue;
				}
				if ( $check['value'] === $before[ $field ] ) {
					continue;
				}
				$risks = RiskRules::detect( $tab, $before, array_merge( $before, array( $field => $check['value'] ) ) );
				if ( array() !== $risks && ! self::is_confirmed( $confirmed, $tab, $field ) ) {
					$entry['skipped'][] = array(
						'field'  => $field,
						'reason' => 'confirmation',
						'risks'  => $risks,
					);
					continue;
				}
				$input[ $field ]    = $check['value'];
				$personal           = ! in_array( $def['privacy'], array( 'public', 'internal' ), true );
				$entry['changed'][] = array(
					'field'  => $field,
					'before' => $personal ? 'changed' : $before[ $field ],
					'after'  => $personal ? 'changed' : $check['value'],
					'risks'  => $risks,
				);
			}
			if ( array() === $entry['errors'] && array() !== $input ) {
				$semantic        = Settings::check( $actor, $tab, $input, array_keys( $input ) )['errors'];
				$entry['errors'] = $semantic;
			}
			if ( $apply && array() === $entry['errors'] && array() !== $input ) {
				update_option(
					Settings::UNDO_PREFIX . $tab,
					array(
						'values'     => $before,
						'expires_at' => time() + Settings::UNDO_TTL,
					),
					false
				);
				Settings::save( $actor, $tab, $input, array_keys( $input ), Settings::rev( $tab ), array( 'context' => (string) ( $options['context'] ?? 'import' ) ) );
				$report['applied'][] = $tab;
				$keys[ $tab ]        = array_keys( $input );
			}
			$report['tabs'][ $tab ] = $entry;
		}
		$surface_keys = array();
		$providers    = self::providers();
		if ( ! isset( $providers[ self::ADDON_KEY ] ) && is_array( $data[ self::ADDON_KEY ] ?? null ) ) {
			foreach ( array_keys( $data[ self::ADDON_KEY ] ) as $item ) {
				$report['addon'][] = (string) $item;
			}
		}
		$report['addon'] = array_values( array_unique( $report['addon'] ) );
		if ( array() !== $report['addon'] ) {
			$report['notices'][] = 'addon_kept';
		}
		foreach ( $providers as $key => $provider ) {
			if ( ! is_array( $data[ $key ] ?? null ) || ( null !== $surfaces && ! in_array( $key, $surfaces, true ) ) ) {
				continue;
			}
			$mine = array();
			foreach ( $confirmed as $entry ) {
				if ( '*' === $entry || str_starts_with( $entry, $key . '.' ) ) {
					$mine[] = '*' === $entry ? '*' : substr( $entry, strlen( $key ) + 1 );
				}
			}
			$plan = $provider->plan( $actor, $data[ $key ], $mine );
			if ( $apply && array() === $plan['errors'] && array() !== $plan['changed'] ) {
				$done = $provider->apply( $actor, $data[ $key ], $mine );
				if ( array() !== $done ) {
					$report['applied_surfaces'][] = $key;
					$surface_keys[ $key ]         = $done;
				}
			}
			$report['surfaces'][ $key ] = array( 'label' => $provider->label() ) + $plan;
		}
		if ( $apply && ( array() !== $report['applied'] || array() !== $report['applied_surfaces'] ) ) {
			AuditLog::write(
				Database::current(),
				$actor,
				'settings_imported',
				'settings',
				'import',
				null,
				array(
					'tabs'       => $report['applied'],
					'keys'       => $keys,
					'other_site' => in_array( 'other_site', $report['notices'], true ),
				) + ( array() === $surface_keys ? array() : array( 'surfaces' => $surface_keys ) ) + ( isset( $options['context'] ) ? array( 'context' => (string) $options['context'] ) : array() ) + ( $actor->is_system() ? array( 'actor' => $actor->type ) : array() )
			);
		}
		return $report;
	}

	/**
	 * 10 imports per account and hour, fail-closed (R0-17 §3.4).
	 *
	 * @param Actor $actor Actor.
	 * @return void
	 * @throws CourseException `ccap_rate_limited`.
	 */
	private static function rate_limit( Actor $actor ): void {
		if ( $actor->is_system() ) {
			return;
		}
		$key   = self::RATE_PREFIX . $actor->user_id;
		$state = get_transient( $key );
		$state = is_array( $state ) ? $state : array();
		$now   = time();
		$state = array_values( array_filter( array_map( 'intval', $state ), static fn( int $t ): bool => $t > $now - HOUR_IN_SECONDS ) );
		if ( count( $state ) >= self::PER_HOUR ) {
			throw new CourseException( 'ccap_rate_limited', 'At most ten settings imports per hour.', 429 );
		}
		$state[] = $now;
		if ( ! set_transient( $key, $state, HOUR_IN_SECONDS ) && false === get_transient( $key ) ) {
			throw new CourseException( 'ccap_rate_limited', 'Rate limit storage unavailable.', 503 );
		}
	}
}
