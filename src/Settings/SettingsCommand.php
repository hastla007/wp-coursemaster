<?php
/**
 * WP-CLI `wp course-captain settings list|get|set|export|import|reset`
 * (SET-CC-12, R0-27 SR-5, SET-021, R0-31 PSD-007, EX-6).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Ops\Diagnostics;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\RoleCaps;

defined( 'ABSPATH' ) || exit;

/**
 * Actor `system:cli`; the same services as the admin screens:
 *
 * - `list [--tab=<tab>]`, `get <tab.field>`: `secret` fields are never
 *   readable (`<set>`/`<empty>`).
 * - `set <tab.field> <value>`: value as JSON (`true`, `5`, `{"a":1}`) or
 *   plain text; {@see Settings::check()} and {@see Settings::save()}
 *   (audit `settings_saved`, context `cli`). Risk fields (RiskRules) need
 *   `--confirm`; switching data deletion on needs `--confirm=LÖSCHEN`
 *   (or LOESCHEN/DELETE, SET-CC-30). Without confirmation: exit 1, nothing written, no audit.
 * - `export [--output=<file>] [--include-personal] [--include=roles,…]`:
 *   stdout, or the safe export writer (`0600`, new file, outside the
 *   WordPress directory, EX-6).
 * - `import <file> [--dry-run] [--tabs=…] [--surfaces=…] [--confirm] [--yes]`:
 *   diff preview first; without `--yes` nothing is written; risk fields and
 *   RC-07 roles only with `--confirm` (else skipped and reported).
 * - `reset <tab> --yes` (roles: {@see RoleCaps::reset()}), `reset <tab.field>`
 *   = `set` to the default (risk rules apply).
 *
 * Exit code 0 = ok, 1 = error or refused.
 */
final class SettingsCommand {

	/**
	 * Register the commands when WP-CLI is loaded.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) ) {
			return;
		}
		foreach ( array( 'list', 'get', 'set', 'export', 'import', 'reset' ) as $command ) {
			\WP_CLI::add_command(
				'course-captain settings ' . $command,
				static function ( array $args, array $assoc ) use ( $command ): void {
					self::invoke( $command, $args, $assoc );
				}
			);
		}
	}

	/**
	 * WP-CLI entry point.
	 *
	 * @param string                $command Sub-command.
	 * @param array<int, string>    $args    Positional arguments.
	 * @param array<string, string> $assoc   Options.
	 * @return void
	 */
	public static function invoke( string $command, array $args, array $assoc ): void {
		if ( 'reset' === $command && ! str_contains( (string) ( $args[0] ?? '' ), '.' ) && ! isset( $assoc['yes'] ) ) {
			\WP_CLI::confirm( 'Reset this settings tab to its defaults?' );
			$assoc['yes'] = '';
		}
		if ( 'import' === $command && ! isset( $assoc['yes'] ) && ! isset( $assoc['dry-run'] ) ) {
			$preview = self::execute( 'import', $args, $assoc + array( 'dry-run' => '' ), Actor::cli() );
			foreach ( $preview['lines'] as $line ) {
				\WP_CLI::line( $line );
			}
			if ( 0 !== $preview['exit'] ) {
				\WP_CLI::halt( $preview['exit'] );
			}
			\WP_CLI::confirm( 'Apply this import?' );
			$assoc['yes'] = '';
		}
		$out = self::execute( $command, $args, $assoc, Actor::cli() );
		foreach ( $out['lines'] as $line ) {
			\WP_CLI::line( $line );
		}
		\WP_CLI::halt( $out['exit'] );
	}

	/**
	 * Testable core.
	 *
	 * @param string                $command Sub-command.
	 * @param array<int, string>    $args    Positional arguments.
	 * @param array<string, string> $assoc   Options.
	 * @param Actor                 $actor   Actor (`system:cli` in production).
	 * @return array{exit: int, lines: list<string>}
	 */
	public static function execute( string $command, array $args, array $assoc, Actor $actor ): array {
		$json = 'json' === ( $assoc['format'] ?? 'text' );
		try {
			switch ( $command ) {
				case 'list':
					return self::list_values( (string) ( $assoc['tab'] ?? '' ), $json );
				case 'get':
					list( $tab, $field, $def ) = self::key( (string) ( $args[0] ?? '' ) );
					$value                     = self::readable( $def, Settings::values( $tab )[ $field ] );
					return self::out( 0, $json, array( 'key' => $tab . '.' . $field, 'value' => $value ), array( is_string( $value ) ? $value : (string) wp_json_encode( $value ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short JSON payload.
				case 'set':
					if ( ! array_key_exists( 1, $args ) ) {
						return self::out( 1, $json, array( 'error' => 'ccap_invalid_param' ), array( 'Error: usage: settings set <tab.field> <value>' ) );
					}
					return self::set( $actor, (string) $args[0], self::parse_value( self::key( (string) $args[0] )[2], (string) $args[1] ), $assoc, $json );
				case 'reset':
					return self::reset( $actor, (string) ( $args[0] ?? '' ), $assoc, $json );
				case 'export':
					return self::export( $actor, $assoc );
				case 'import':
					return self::import( $actor, (string) ( $args[0] ?? '' ), $assoc, $json );
			}
		} catch ( CourseException $e ) {
			return self::out( 1, $json, array( 'error' => $e->error_code ), array( 'Error: ' . $e->error_code . ' ' . $e->getMessage() ) );
		}
		return self::out( 1, $json, array( 'error' => 'ccap_not_found' ), array( 'Error: unknown command.' ) );
	}

	/**
	 * `list`.
	 *
	 * @param string $only Tab filter ('' = all).
	 * @param bool   $json JSON output.
	 * @return array{exit: int, lines: list<string>}
	 * @throws CourseException For unknown tabs.
	 */
	private static function list_values( string $only, bool $json ): array {
		$fields = SettingsSchema::fields();
		if ( '' !== $only && ! isset( $fields[ $only ] ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown settings tab.', 404 );
		}
		$locks = EffectiveSettings::locks();
		$rows  = array();
		$lines = array();
		foreach ( $fields as $tab => $schema ) {
			if ( '' !== $only && $tab !== $only ) {
				continue;
			}
			$values = Settings::values( $tab );
			foreach ( $schema as $field => $def ) {
				$key     = $tab . '.' . $field;
				$value   = self::readable( $def, $values[ $field ] );
				$rows[]  = array(
					'key'     => $key,
					'value'   => $value,
					'default' => 'secret' === $def['privacy'] ? null : $def['default'],
					'changed' => $values[ $field ] !== $def['default'],
					'locked'  => isset( $locks[ $key ] ),
				);
				$lines[] = sprintf( '%-48s %s%s%s', $key, is_string( $value ) ? $value : (string) wp_json_encode( $value ), $values[ $field ] !== $def['default'] ? ' (changed)' : '', isset( $locks[ $key ] ) ? ' (locked)' : '' );
			}
		}
		return self::out( 0, $json, $rows, $lines );
	}

	/**
	 * `set` (also `reset <tab.field>`).
	 *
	 * @param Actor                 $actor Actor.
	 * @param string                $key   `tab.field`.
	 * @param mixed                 $value Value.
	 * @param array<string, string> $assoc Options.
	 * @param bool                  $json  JSON output.
	 * @return array{exit: int, lines: list<string>}
	 * @throws CourseException `ccap_invalid_param`, `ccap_forbidden`, `ccap_settings_conflict`.
	 */
	private static function set( Actor $actor, string $key, $value, array $assoc, bool $json ): array {
		list( $tab, $field ) = self::key( $key );
		$input               = array( $field => $value );
		if ( isset( $assoc['confirm'] ) && '' !== (string) $assoc['confirm'] ) {
			// SET-CC-30: the typed word (LÖSCHEN/LOESCHEN/DELETE) confirms switching data deletion on.
			$input[ Settings::CONFIRM_WORD_FIELD ] = (string) $assoc['confirm'];
		}
		$check = Settings::check( $actor, $tab, $input, array( $field ) );
		if ( array() !== $check['errors'] ) {
			$rule = (string) ( $check['errors'][ $field ] ?? reset( $check['errors'] ) );
			$hint = 'confirm_word' === $rule ? ' Add --confirm=LÖSCHEN (or DELETE) to switch data deletion on.' : '';
			return self::out( 1, $json, array( 'error' => 'ccap_validation_failed', 'fields' => $check['errors'] ), array( 'Error: ccap_validation_failed ' . $tab . '.' . $field . ': ' . $rule . '.' . $hint ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short JSON payload.
		}
		$risks = RiskRules::detect( $tab, $check['before'], $check['candidate'] );
		if ( array() !== $risks && ! isset( $assoc['confirm'] ) ) {
			return self::out( 1, $json, array( 'error' => 'ccap_confirmation_required', 'risks' => $risks ), array( 'Error: ccap_confirmation_required ' . implode( ', ', $risks ) . '. This change needs a confirmation; add --confirm.' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short JSON payload.
		}
		$saved = Settings::save(
			$actor,
			$tab,
			$input,
			array( $field ),
			Settings::rev( $tab ),
			array(
				'context'         => 'cli',
				'confirmed_risks' => $risks,
			)
		);
		$value = self::readable( SettingsSchema::fields()[ $tab ][ $field ], Settings::values( $tab )[ $field ] );
		return self::out(
			0,
			$json,
			array(
				'key'     => $tab . '.' . $field,
				'changed' => $saved['changed'],
				'value'   => $value,
			),
			array( array() === $saved['changed'] ? 'Success: unchanged.' : 'Success: ' . $tab . '.' . $field . ' = ' . ( is_string( $value ) ? $value : (string) wp_json_encode( $value ) ) . '.' )
		);
	}

	/**
	 * `reset <tab>` or `reset <tab.field>`.
	 *
	 * @param Actor                 $actor  Actor.
	 * @param string                $target Tab or key.
	 * @param array<string, string> $assoc  Options.
	 * @param bool                  $json   JSON output.
	 * @return array{exit: int, lines: list<string>}
	 * @throws CourseException For unknown tabs/keys or missing rights.
	 */
	private static function reset( Actor $actor, string $target, array $assoc, bool $json ): array {
		if ( str_contains( $target, '.' ) ) {
			list( , , $def ) = self::key( $target );
			return self::set( $actor, $target, $def['default'], $assoc, $json );
		}
		if ( ! isset( $assoc['yes'] ) ) {
			return self::out( 1, $json, array( 'error' => 'ccap_confirmation_required' ), array( 'Error: add --yes to reset the tab.' ) );
		}
		if ( 'roles' === $target ) {
			$changes = RoleCaps::reset( $actor );
			return self::out( 0, $json, $changes, array( 'Success: roles reset (' . count( $changes ) . ' changed). Undo is possible for 30 days.' ) );
		}
		$changed = Settings::reset( $actor, $target );
		return self::out( 0, $json, array( 'changed' => $changed ), array( 'Success: ' . count( $changed ) . ' values reset. Undo is possible for 30 days.' ) );
	}

	/**
	 * `export`.
	 *
	 * @param Actor                 $actor Actor.
	 * @param array<string, string> $assoc Options.
	 * @return array{exit: int, lines: list<string>}
	 * @throws CourseException `ccap_export_target_invalid`.
	 */
	private static function export( Actor $actor, array $assoc ): array {
		$data = SettingsTransfer::export(
			$actor,
			array(
				'include_personal' => isset( $assoc['include-personal'] ),
				'include'          => self::csv( (string) ( $assoc['include'] ?? '' ) ),
				'context'          => 'cli',
			)
		);
		$body = (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( isset( $assoc['output'] ) && '' !== (string) $assoc['output'] ) {
			Diagnostics::write_file( (string) $assoc['output'], $body . "\n" );
			return array(
				'exit'  => 0,
				'lines' => array( 'Success: written ' . strlen( $body ) . ' bytes.' ),
			);
		}
		return array(
			'exit'  => 0,
			'lines' => array( $body ),
		);
	}

	/**
	 * `import` (preview with `--dry-run` or without `--yes`).
	 *
	 * @param Actor                 $actor Actor.
	 * @param string                $path  File.
	 * @param array<string, string> $assoc Options.
	 * @param bool                  $json  JSON output.
	 * @return array{exit: int, lines: list<string>}
	 * @throws CourseException `ccap_settings_import_invalid`, `ccap_settings_version_newer`.
	 */
	private static function import( Actor $actor, string $path, array $assoc, bool $json ): array {
		if ( '' === $path || ! is_readable( $path ) || ! is_file( $path ) ) {
			throw new CourseException( 'ccap_settings_import_invalid', 'File not found or not readable.', 404 );
		}
		if ( (int) filesize( $path ) > SettingsTransfer::MAX_BYTES ) {
			throw new CourseException( 'ccap_settings_import_invalid', 'The file is larger than 256 KB.', 413 );
		}
		$data    = SettingsTransfer::parse( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file named by the operator.
		$apply   = isset( $assoc['yes'] ) && ! isset( $assoc['dry-run'] );
		$options = array(
			'apply'     => $apply,
			'confirmed' => isset( $assoc['confirm'] ) ? array( '*' ) : array(),
			'context'   => 'cli',
		);
		if ( isset( $assoc['tabs'] ) ) {
			$options['tabs'] = self::csv( (string) $assoc['tabs'] );
		}
		if ( isset( $assoc['surfaces'] ) ) {
			$options['surfaces'] = self::csv( (string) $assoc['surfaces'] );
		}
		$report = SettingsTransfer::import( $actor, $data, $options );
		$lines  = in_array( 'other_site', $report['notices'], true ) ? array( 'Note: the file comes from another website.' ) : array();
		if ( array() !== $report['addon'] ) {
			$lines[] = 'Note: ' . count( $report['addon'] ) . ' extension setting(s) left unchanged (' . implode( ', ', $report['addon'] ) . ').';
		}
		foreach ( array_merge( $report['tabs'], $report['surfaces'] ) as $scope => $entry ) {
			$lines[] = '[' . $scope . ']';
			foreach ( $entry['changed'] as $change ) {
				$item    = (string) ( $change['field'] ?? $change['item'] );
				$lines[] = '  ~ ' . ( isset( $change['summary'] ) ? (string) $change['summary'] : $item . ': ' . (string) wp_json_encode( $change['before'] ) . ' -> ' . (string) wp_json_encode( $change['after'] ) ) . ( array() === (array) $change['risks'] ? '' : ' [' . implode( ', ', (array) $change['risks'] ) . ' confirmed]' );
			}
			foreach ( $entry['skipped'] as $skip ) {
				$lines[] = '  - ' . (string) ( $skip['field'] ?? $skip['item'] ) . ': skipped (' . (string) $skip['reason'] . ( isset( $skip['risks'] ) ? ' ' . implode( ', ', (array) $skip['risks'] ) . '; add --confirm' : '' ) . ')';
			}
			foreach ( $entry['errors'] as $item => $rule ) {
				$lines[] = '  ! ' . (string) $item . ': invalid (' . (string) $rule . '); not imported';
			}
		}
		if ( $apply ) {
			$applied = array_merge( $report['applied'], $report['applied_surfaces'] );
			$lines[] = array() === $applied ? 'Success: nothing imported.' : 'Success: imported ' . implode( ', ', $applied ) . '.';
		} else {
			$lines[] = 'Preview only; nothing was written. Add --yes to apply.';
		}
		return self::out( 0, $json, $report, $lines );
	}

	/**
	 * Split a key into tab, field and definition.
	 *
	 * @param string $key `tab.field`.
	 * @return array{0: string, 1: string, 2: array<string, mixed>}
	 * @throws CourseException `ccap_invalid_param` for unknown keys.
	 */
	private static function key( string $key ): array {
		list( $tab, $field ) = array_pad( explode( '.', $key, 2 ), 2, '' );
		$def                 = SettingsSchema::fields()[ $tab ][ $field ] ?? null;
		if ( null === $def ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown setting. Use `wp course-captain settings list`.', 400 );
		}
		return array( $tab, $field, $def );
	}

	/**
	 * Value as shown by the CLI: secrets never (SR-5).
	 *
	 * @param array<string, mixed> $def   Field definition.
	 * @param mixed                $value Value.
	 * @return mixed
	 */
	private static function readable( array $def, $value ) {
		if ( 'secret' === $def['privacy'] ) {
			return ( null === $value || '' === $value || array() === $value ) ? '<empty>' : '<set>';
		}
		return $value;
	}

	/**
	 * CLI value by field type: text types take the argument as is (a JSON
	 * string literal is decoded), booleans accept true/false/yes/no/on/off/1/0,
	 * `null` clears nullable fields, objects/sets/lists are JSON.
	 *
	 * @param array<string, mixed> $def Field definition.
	 * @param string               $raw Raw argument.
	 * @return mixed
	 */
	public static function parse_value( array $def, string $raw ) {
		$trim = trim( $raw );
		if ( ! empty( $def['nullable'] ) && 'null' === $trim ) {
			return null;
		}
		switch ( $def['type'] ) {
			case 'bool':
				$map = array(
					'true'  => true,
					'yes'   => true,
					'on'    => true,
					'1'     => true,
					'false' => false,
					'no'    => false,
					'off'   => false,
					'0'     => false,
				);
				return $map[ strtolower( $trim ) ] ?? $raw;
			case 'int':
			case 'page_id':
				return $trim;
			case 'string':
			case 'text':
			case 'slug':
			case 'email':
			case 'enum':
				$decoded = str_starts_with( $trim, '"' ) ? json_decode( $trim, true ) : null;
				return is_string( $decoded ) ? $decoded : $raw;
		}
		$decoded = json_decode( $raw, true );
		return null === $decoded ? $raw : $decoded;
	}

	/**
	 * Comma list.
	 *
	 * @param string $raw Raw.
	 * @return list<string>
	 */
	private static function csv( string $raw ): array {
		return array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), static fn( string $v ): bool => '' !== $v ) );
	}

	/**
	 * Output as text lines or one JSON line.
	 *
	 * @param int   $code  Exit code.
	 * @param bool  $json  JSON format.
	 * @param mixed $data  Data for JSON.
	 * @param array $lines Text lines.
	 * @phpstan-param list<string> $lines
	 * @return array{exit: int, lines: list<string>}
	 */
	private static function out( int $code, bool $json, $data, array $lines ): array {
		return array(
			'exit'  => $code,
			'lines' => $json ? array( (string) wp_json_encode( $data ) ) : $lines,
		);
	}
}
