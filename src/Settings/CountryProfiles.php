<?php
/**
 * Country profiles DE/AT/CH/EU/OTHER (SET-CC-07, SET-CC-P3, E-346 S8).
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
 * Suggestions per country for settings that already exist: register
 * fields (`<tab>.<field>`) and the WordPress values language, time zone,
 * date/time format and first weekday (`wp.<option>`, R0-27 K43-04). The
 * data comes from `assets/data/country-profiles.json` and can be replaced
 * with the filter `course_captain_country_profiles`.
 *
 * Nothing is applied by itself: a suggestion is only compared
 * ({@see self::compare()}) until the operator applies selected keys
 * ({@see self::apply()}). A `null` value is no suggestion and is skipped;
 * with `open` (owner decision E4/E7) it is shown as open and never
 * applied. `legal` values stay "unreviewed" until `reviewed` is true.
 */
final class CountryProfiles {

	/** Filter replacing the profile data. */
	public const FILTER = 'course_captain_country_profiles';

	/** Profile IDs (the enum of `general.country_profile`). */
	public const IDS = array( 'DE', 'AT', 'CH', 'EU', 'OTHER' );

	/** Register key holding the chosen profile. */
	public const CHOICE_KEY = 'general.country_profile';

	/** Data file relative to the plugin directory. */
	public const SOURCE = 'assets/data/country-profiles.json';

	/** WordPress options a profile may suggest (R0-27 K43-04). */
	public const WP_OPTIONS = array( 'WPLANG', 'timezone_string', 'date_format', 'time_format', 'start_of_week' );

	/** Audit action of an applied profile. */
	public const AUDIT_ACTION = 'country_profile_applied';

	/**
	 * Decoded data file (per request).
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $file = null;

	/**
	 * Profiles after the filter, sanitised: ID => key => entry.
	 *
	 * @return array<string, array<string, array{value: mixed, legal: bool, reviewed: bool, open: string}>>
	 */
	public static function profiles(): array {
		if ( null === self::$file ) {
			$path       = ( defined( 'COURSE_CAPTAIN_DIR' ) ? (string) COURSE_CAPTAIN_DIR : dirname( __DIR__, 2 ) ) . '/' . self::SOURCE;
			$raw        = is_readable( $path ) ? json_decode( (string) file_get_contents( $path ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local data file.
			self::$file = is_array( $raw ) && is_array( $raw['profiles'] ?? null ) ? $raw['profiles'] : array();
		}
		$data = apply_filters( 'course_captain_country_profiles', self::$file );
		$data = is_array( $data ) ? $data : array();
		$out  = array();
		foreach ( self::IDS as $id ) {
			$out[ $id ] = array();
			foreach ( is_array( $data[ $id ] ?? null ) ? $data[ $id ] : array() as $key => $entry ) {
				if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-z]+\.[A-Za-z_]{1,40}$/', $key ) ) {
					continue;
				}
				$entry              = is_array( $entry ) ? $entry : array( 'value' => $entry );
				$open               = isset( $entry['open'] ) && is_string( $entry['open'] ) && 1 === preg_match( '/^E[0-9]{1,3}$/', $entry['open'] ) ? $entry['open'] : '';
				$out[ $id ][ $key ] = array(
					'value'    => $entry['value'] ?? null,
					'legal'    => ! empty( $entry['legal'] ),
					'reviewed' => ! empty( $entry['reviewed'] ),
					'open'     => $open,
				);
			}
		}
		return $out;
	}

	/**
	 * Kind of a profile key: `setting`, `wp`, `open` or '' (not an existing setting).
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function kind( string $key ): string {
		list( $prefix, $name ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		if ( 'wp' === $prefix ) {
			return in_array( $name, self::WP_OPTIONS, true ) ? 'wp' : '';
		}
		if ( 'open' === $prefix ) {
			return 'open';
		}
		$def = SettingsSchema::fields()[ $prefix ][ $name ] ?? null;
		// Only exportable, non-personal fields; never the choice itself.
		if ( ! is_array( $def ) || true !== $def['export'] || in_array( $def['privacy'], array( 'personal', 'secret' ), true ) || self::CHOICE_KEY === $key ) {
			return '';
		}
		return 'setting';
	}

	/**
	 * Before/after comparison of a profile with the current values.
	 *
	 * Rows: `status` `differs` (can be applied), `same`, `open` (owner
	 * decision, never applied) or `unavailable` (`reason`). Entries
	 * without a value and without an owner decision, unknown keys and
	 * invalid values are skipped.
	 *
	 * @param string $id Profile ID.
	 * @return list<array{key: string, kind: string, current: mixed, suggested: mixed, status: string, reason: string, legal: bool, reviewed: bool, open: string}>
	 */
	public static function compare( string $id ): array {
		$profiles = self::profiles();
		$rows     = array();
		foreach ( $profiles[ $id ] ?? array() as $key => $entry ) {
			$kind = self::kind( $key );
			if ( '' === $kind ) {
				continue;
			}
			if ( null === $entry['value'] ) {
				if ( '' === $entry['open'] ) {
					continue;
				}
				$rows[] = array(
					'key'       => $key,
					'kind'      => $kind,
					'current'   => 'open' === $kind ? null : self::current( $key, $kind ),
					'suggested' => null,
					'status'    => 'open',
					'reason'    => '',
					'legal'     => $entry['legal'],
					'reviewed'  => false,
					'open'      => $entry['open'],
				);
				continue;
			}
			if ( 'open' === $kind ) {
				// No setting exists for it (for example the age limit, SET-CC-17).
				continue;
			}
			$suggested = self::normalize( $key, $kind, $entry['value'] );
			if ( null === $suggested ) {
				continue;
			}
			$current = self::current( $key, $kind );
			$reason  = '';
			if ( 'wp.WPLANG' === $key && 'en_US' !== $suggested && ! in_array( $suggested, get_available_languages(), true ) ) {
				$reason = 'language_missing';
			}
			$rows[] = array(
				'key'       => $key,
				'kind'      => $kind,
				'current'   => $current,
				'suggested' => $suggested,
				'status'    => '' !== $reason ? 'unavailable' : ( $current === $suggested ? 'same' : 'differs' ),
				'reason'    => $reason,
				'legal'     => $entry['legal'],
				'reviewed'  => $entry['reviewed'],
				'open'      => '',
			);
		}
		return $rows;
	}

	/**
	 * Fingerprint of the current values a comparison was shown with.
	 *
	 * @param list<array<string, mixed>> $rows Rows of {@see self::compare()}.
	 * @return string
	 */
	public static function fingerprint( array $rows ): string {
		$values = array();
		foreach ( $rows as $row ) {
			$values[ (string) $row['key'] ] = $row['current'];
		}
		return md5( (string) wp_json_encode( $values ) );
	}

	/**
	 * Apply selected suggestions of a profile (only rows with status
	 * `differs`; open, equal and unavailable rows are skipped), record the
	 * chosen profile in `general.country_profile` and write one audit entry.
	 *
	 * @param Actor  $actor Operator.
	 * @param string $id    Profile ID.
	 * @param array  $keys  Selected keys.
	 * @param string $seen  Fingerprint the comparison was shown with ('' = no check).
	 * @phpstan-param list<string> $keys
	 * @return array{result: string, applied: list<string>, skipped: array<string, string>}
	 * @throws CourseException `ccap_not_found`, `ccap_forbidden`.
	 */
	public static function apply( Actor $actor, string $id, array $keys, string $seen = '' ): array {
		if ( ! in_array( $id, self::IDS, true ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown country profile.', 404 );
		}
		if ( ! Settings::allowed( $actor, 'settings' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to change settings.', 403 );
		}
		$rows = self::compare( $id );
		$out  = array(
			'result'  => 'applied',
			'applied' => array(),
			'skipped' => array(),
		);
		if ( '' !== $seen && ! hash_equals( self::fingerprint( $rows ), $seen ) ) {
			$out['result'] = 'conflict';
			return $out;
		}
		$schema  = SettingsSchema::fields();
		$inputs  = array();
		$wp      = array();
		$changed = array();
		$wanted  = array_flip( array_map( 'strval', $keys ) );
		foreach ( $rows as $row ) {
			$key = $row['key'];
			if ( ! isset( $wanted[ $key ] ) ) {
				continue;
			}
			if ( 'differs' !== $row['status'] ) {
				$out['skipped'][ $key ] = $row['status'];
				continue;
			}
			if ( 'wp' === $row['kind'] ) {
				if ( ! $actor->is_system() && ! user_can( $actor->user_id, 'manage_options' ) ) {
					$out['skipped'][ $key ] = 'forbidden';
					continue;
				}
				$wp[ $key ] = $row;
				continue;
			}
			list( $tab, $field ) = explode( '.', $key, 2 );
			if ( ! Settings::allowed( $actor, (string) $schema[ $tab ][ $field ]['cap'] ) ) {
				$out['skipped'][ $key ] = 'forbidden';
				continue;
			}
			// A value that needs a risk confirmation is never applied from here.
			$before = Settings::values( $tab );
			if ( array() !== RiskRules::detect( $tab, $before, array( $field => $row['suggested'] ) + $before ) ) {
				$out['skipped'][ $key ] = 'risk';
				continue;
			}
			$inputs[ $tab ][ $field ] = $row['suggested'];
		}
		list( $choice_tab, $choice_field )      = explode( '.', self::CHOICE_KEY, 2 );
		$previous                               = Settings::get( self::CHOICE_KEY );
		$inputs[ $choice_tab ]                  = $inputs[ $choice_tab ] ?? array();
		$inputs[ $choice_tab ][ $choice_field ] = $id;
		$context                                = array(
			'context' => 'country_profile',
			'profile' => $id,
		);
		foreach ( $inputs as $tab => $input ) {
			$check = Settings::check( $actor, (string) $tab, $input, array_keys( $input ) );
			foreach ( array_keys( $check['errors'] ) as $field ) {
				unset( $input[ $field ] );
				$out['skipped'][ $tab . '.' . $field ] = 'invalid';
			}
			if ( array() === $input ) {
				continue;
			}
			$before = Settings::values( (string) $tab );
			try {
				$saved = Settings::save( $actor, (string) $tab, $input, array_keys( $input ), Settings::rev( (string) $tab ), $context );
			} catch ( CourseException $e ) {
				$out['result'] = 'ccap_settings_conflict' === $e->error_code ? 'conflict' : $e->error_code;
				return $out;
			}
			foreach ( $saved['changed'] as $field ) {
				$key = $tab . '.' . $field;
				if ( self::CHOICE_KEY !== $key ) {
					$out['applied'][] = $key;
				}
				$changed[ $key ] = array(
					'before' => $before[ $field ] ?? null,
					'after'  => Settings::values( (string) $tab )[ $field ] ?? null,
				);
			}
		}
		foreach ( $wp as $key => $row ) {
			self::write_wp( $key, $row['suggested'] );
			$out['applied'][] = $key;
			$changed[ $key ]  = array(
				'before' => $row['current'],
				'after'  => $row['suggested'],
			);
		}
		if ( array() === $changed ) {
			$out['result'] = 'nothing';
			return $out;
		}
		$unreviewed = array();
		foreach ( $rows as $row ) {
			if ( $row['legal'] && ! $row['reviewed'] && in_array( $row['key'], $out['applied'], true ) ) {
				$unreviewed[] = $row['key'];
			}
		}
		AuditLog::write(
			Database::current(),
			$actor,
			self::AUDIT_ACTION,
			'settings',
			$id,
			null,
			array(
				'context'    => 'country_profile',
				'profile'    => $id,
				'previous'   => $previous,
				'changed'    => $changed,
				'skipped'    => $out['skipped'],
				'unreviewed' => $unreviewed,
			) + ( $actor->is_system() ? array( 'actor' => $actor->type ) : array() )
		);
		if ( array() === $out['applied'] ) {
			$out['result'] = 'nothing';
		}
		return $out;
	}

	/**
	 * Current value of a key.
	 *
	 * @param string $key  Key.
	 * @param string $kind Kind.
	 * @return mixed
	 */
	private static function current( string $key, string $kind ) {
		if ( 'setting' === $kind ) {
			return Settings::get( $key );
		}
		$option = substr( $key, 3 );
		switch ( $option ) {
			case 'WPLANG':
				$locale = (string) get_option( 'WPLANG', '' );
				return '' === $locale ? 'en_US' : $locale;
			case 'start_of_week':
				return (int) get_option( 'start_of_week', 0 );
		}
		return (string) get_option( $option, '' );
	}

	/**
	 * Validated suggestion (null = invalid, skipped).
	 *
	 * @param string $key   Key.
	 * @param string $kind  Kind.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	private static function normalize( string $key, string $kind, $value ) {
		if ( 'setting' === $kind ) {
			list( $tab, $field ) = explode( '.', $key, 2 );
			$check               = SettingsSchema::validate( SettingsSchema::fields()[ $tab ][ $field ], $value );
			return $check['ok'] ? $check['value'] : null;
		}
		switch ( substr( $key, 3 ) ) {
			case 'WPLANG':
				return is_string( $value ) && 1 === preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', $value ) ? $value : null;
			case 'timezone_string':
				return is_string( $value ) && in_array( $value, timezone_identifiers_list(), true ) ? $value : null;
			case 'start_of_week':
				return is_int( $value ) && $value >= 0 && $value <= 6 ? $value : null;
			case 'date_format':
			case 'time_format':
				return is_string( $value ) && '' !== $value && strlen( $value ) <= 50 && sanitize_option( substr( $key, 3 ), $value ) === $value ? $value : null;
		}
		return null;
	}

	/**
	 * Write a WordPress option.
	 *
	 * @param string $key   `wp.<option>`.
	 * @param mixed  $value Validated value.
	 * @return void
	 */
	private static function write_wp( string $key, $value ): void {
		$option = substr( $key, 3 );
		if ( 'WPLANG' === $option ) {
			update_option( 'WPLANG', 'en_US' === $value ? '' : (string) $value );
			return;
		}
		update_option( $option, $value );
	}

	/**
	 * Forget the cached data file (tests).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$file = null;
	}
}
