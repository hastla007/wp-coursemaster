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
 */
final class SettingsTransfer {

	public const MAX_BYTES    = 262144;
	public const PER_HOUR     = 10;
	public const RATE_PREFIX  = 'ccap_settings_import_';
	public const PLACEHOLDERS = array( '<gesetzt>', '<set>', '<leer>', '<empty>', '<konstante>', '<constant>' );

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
	 * @param array<string, mixed> $options `tabs` (list|null), `confirmed` (list of `tab.field`), `apply` (bool).
	 * @return array{notices: list<string>, tabs: array<string, array{changed: list<array<string, mixed>>, skipped: list<array<string, mixed>>, errors: array<string, string>}>, applied: list<string>}
	 * @throws CourseException `ccap_forbidden`, `ccap_rate_limited`.
	 */
	public static function import( Actor $actor, array $data, array $options = array() ): array {
		if ( ! Settings::allowed( $actor, 'settings' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to import settings.', 403 );
		}
		$apply     = ! empty( $options['apply'] );
		$selected  = isset( $options['tabs'] ) && is_array( $options['tabs'] ) ? array_map( 'strval', $options['tabs'] ) : null;
		$confirmed = array_map( 'strval', (array) ( $options['confirmed'] ?? array() ) );
		if ( $apply ) {
			self::rate_limit( $actor );
		}
		$report = array(
			'notices' => array(),
			'tabs'    => array(),
			'applied' => array(),
		);
		if ( (string) ( $data['site_hash'] ?? '' ) !== hash( 'sha256', home_url() ) ) {
			$report['notices'][] = 'other_site';
		}
		$fields_all = SettingsSchema::fields();
		$keys       = array();
		foreach ( (array) $data['tabs'] as $tab => $content ) {
			$tab = (string) $tab;
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
				if ( array() !== $risks && ! in_array( $tab . '.' . $field, $confirmed, true ) ) {
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
				Settings::save( $actor, $tab, $input, array_keys( $input ), Settings::rev( $tab ), array( 'context' => 'import' ) );
				$report['applied'][] = $tab;
				$keys[ $tab ]        = array_keys( $input );
			}
			$report['tabs'][ $tab ] = $entry;
		}
		if ( $apply && array() !== $report['applied'] ) {
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
				)
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
