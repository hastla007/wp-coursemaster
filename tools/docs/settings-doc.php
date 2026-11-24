<?php
/**
 * SET-CC-29: operator handbook `docs/EINSTELLUNGEN.md` generated from the
 * settings register (SettingsSchema, SettingsFields, EffectiveSettings).
 *
 * Usage:
 *   php8.5 tools/docs/settings-doc.php            print the handbook
 *   php8.5 tools/docs/settings-doc.php --write    write docs/EINSTELLUNGEN.md
 *   php8.5 tools/docs/settings-doc.php --check    exit 1 when the file is stale
 *
 * Runs without WordPress: translation and filter functions are stubbed, so
 * the labels are the English source strings of the admin screens (the
 * translations come from the language packs). Output is deterministic.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', sys_get_temp_dir() . '/ccap-settings-doc/' );
	}
	if ( ! function_exists( '__' ) ) {
		/**
		 * Stub.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		function __( $text ) {
			return $text;
		}
	}
	if ( ! function_exists( '_x' ) ) {
		/**
		 * Stub.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		function _x( $text ) {
			return $text;
		}
	}
	if ( ! function_exists( '_n' ) ) {
		/**
		 * Stub.
		 *
		 * @param string $single Singular.
		 * @param string $plural Plural.
		 * @param int    $n      Count.
		 * @return string
		 */
		function _n( $single, $plural, $n ) {
			return 1 === (int) $n ? $single : $plural;
		}
	}
	if ( ! function_exists( 'apply_filters' ) ) {
		/**
		 * Stub: filters keep their default.
		 *
		 * @param string $hook  Hook.
		 * @param mixed  $value Value.
		 * @return mixed
		 */
		function apply_filters( $hook, $value ) {
			unset( $hook );
			return $value;
		}
	}
	require_once dirname( __DIR__, 2 ) . '/src/autoload.php';
}

namespace CourseCaptain\Tools\Docs {

	use CourseCaptain\Admin\SettingsFields;
	use CourseCaptain\Settings\EffectiveSettings;
	use CourseCaptain\Settings\SettingsSchema;

	/**
	 * Markdown builder.
	 */
	final class SettingsDoc {

		public const TARGET = 'docs/EINSTELLUNGEN.md';

		/**
		 * The complete handbook.
		 *
		 * @return string
		 */
		public static function build(): string {
			$fields = SettingsSchema::fields();
			$over   = SettingsSchema::overridable();
			$out    = array();
			$out[]  = '# Einstellungen – Handbuch für Betreiber';
			$out[]  = '';
			$out[]  = '<!-- Automatisch erzeugt aus dem Einstellungsregister (src/Settings/SettingsSchema.php, src/Admin/SettingsFields.php, src/Settings/EffectiveSettings.php). Nicht von Hand ändern: `php8.5 tools/docs/settings-doc.php --write`. Der Unit-Test SettingsDocTest prüft, dass diese Datei aktuell ist. -->';
			$out[]  = '';
			$out[]  = 'Dieses Handbuch listet jede Einstellung von Course Captain mit Standardwert, erlaubten Werten, Recht, Datenklasse und Exportverhalten. Bezeichnungen und Hilfetexte stehen in der Quellsprache der Oberfläche (Englisch); in der Oberfläche erscheinen sie übersetzt.';
			$out[]  = '';
			$out[]  = sprintf( 'Registerversion (`settings_version`): %d.', SettingsSchema::VERSION );
			$out[]  = '';
			$out[]  = '## Legende';
			$out[]  = '';
			$out[]  = '- **Schlüssel**: `<tab>.<feld>`; so heißt die Einstellung in WP-CLI, im Export und im Änderungsprotokoll.';
			$out[]  = '- **Recht**: `settings` = Recht „Einstellungen verwalten“ (`ccap_manage_settings`); `admin` = zusätzlich Administrator (`manage_options`).';
			$out[]  = '- **Datenklasse**: `public`/`internal` erscheinen im Protokoll mit Vorher/Nachher; `personal` nur als „geändert“ und im Export nur mit Opt-in; `secret` nie lesbar.';
			$out[]  = '- **Export**: `ja` = in der Einstellungsdatei; `nein` = nie exportiert oder importiert (umgebungsgebunden).';
			$out[]  = '- **Übersteuerbar**: Ebenen, die abweichen dürfen (Kurs, Aktivität, Prüfung, Gruppe, Medium); „sperrbar“ = eine Sperre für alle Kurse ist möglich (Einstellungen → Sperren und Kurs-Übersteuerungen).';
			$out[]  = '';
			$out[]  = '## Inhalt';
			$out[]  = '';
			foreach ( SettingsFields::TAB_ORDER as $tab ) {
				$out[] = sprintf( '- [%s (`%s`)](#%s)', SettingsFields::tab_label( $tab ), $tab, self::anchor( $tab ) );
			}
			$out[] = '- [Aufbewahrungsfristen](#aufbewahrungsfristen)';
			$out[] = '- [Sperren und Übersteuerungen](#sperren-und-übersteuerungen)';
			$out[] = '- [WP-CLI](#wp-cli)';
			$out[] = '- [Export und Import](#export-und-import)';
			$out[] = '';
			foreach ( SettingsFields::TAB_ORDER as $tab ) {
				$out[] = sprintf( '<a id="%s"></a>', self::anchor( $tab ) );
				$out[] = sprintf( '## %s (`%s`)', SettingsFields::tab_label( $tab ), $tab );
				$out[] = '';
				$intro = SettingsFields::tab_intro( $tab );
				if ( '' !== $intro ) {
					$out[] = '> ' . $intro;
					$out[] = '';
				}
				if ( ! isset( $fields[ $tab ] ) ) {
					$out[] = 'Eigener Speicher (WordPress-Rollen), nicht Teil des Registers. Export nur mit eigenem Opt-in (Abschnitt `roles` der Einstellungsdatei), Import nur durch Administratoren; Hochrisiko-Rechte brauchen eine Bestätigung. WP-CLI: `wp course-captain settings reset roles --yes`.';
					$out[] = '';
					continue;
				}
				$out[] = '| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |';
				$out[] = '|---|---|---|---|---|---|---|---|';
				$help  = array();
				foreach ( $fields[ $tab ] as $field => $def ) {
					$key    = $tab . '.' . $field;
					$ui     = SettingsFields::ui( $tab, $field );
					$label  = $ui['label'] === $field ? ( EffectiveSettings::labels()[ $key ] ?? $field ) : $ui['label'];
					$out[]  = sprintf(
						'| `%s` | %s | %s | %s | %s | %s | %s | %s |',
						$key,
						self::cell( $label ),
						self::cell( self::type( $def ) ),
						self::cell( '`' . self::json( $def['default'] ) . '`' ),
						$def['cap'],
						$def['privacy'],
						true === $def['export'] ? 'ja' : 'nein',
						self::cell( self::scopes( $over[ $key ] ?? null ) )
					);
					$text = trim( $ui['help'] );
					foreach ( (array) ( $def['fields'] ?? array() ) as $sub => $unused ) {
						unset( $unused );
						$sub_ui = SettingsFields::ui( $tab, $field . '.' . (string) $sub );
						if ( $sub_ui['label'] !== $field . '.' . (string) $sub ) {
							$text .= ( '' === $text ? '' : ' ' ) . '`' . (string) $sub . '`: ' . $sub_ui['label'] . ( '' === $sub_ui['help'] ? '' : ' – ' . $sub_ui['help'] );
						}
					}
					if ( '' !== $text ) {
						$help[] = sprintf( '- `%s` – %s', $key, self::line( $text ) );
					}
				}
				$out[] = '';
				if ( array() !== $help ) {
					$out[] = '**Hilfetexte**';
					$out[] = '';
					array_push( $out, ...$help );
					$out[] = '';
				}
			}
			$out[] = '## Aufbewahrungsfristen';
			$out[] = '';
			$out[] = 'Unterschlüssel von `privacy.retention` (R0-27 §1.4, SET-CC-02). **Rechtsprüfung:** Alle Werte sind änderbare Vorschläge und keine Rechtsberatung; der Betreiber lässt sie rechtlich prüfen (E-44, E-51). Die Standardwerte entsprechen den bisher fest eingebauten Fristen. Verkürzen (oder Einschalten von `inactive_learning_data_years`) braucht eine Bestätigung, die zeigt, was der nächste stündliche Lauf löscht (RC-23); der Tab zeigt dieselbe Vorschau als Tabelle. Der Filter `course_captain_retention_days( $value, $key )` gewinnt über die Einstellung (Wert in der Einheit des Unterschlüssels; ungültige Werte werden ignoriert). `error_log_days` und `debug_file_days` hießen bis Registerversion 3 `advanced.error_log_days` und `advanced.debug_file_days`; Migration und Import älterer Dateien übernehmen die Werte.';
			$out[] = '';
			$out[] = '| Unterschlüssel | Bezeichnung | Standard | Bereich | Einheit |';
			$out[] = '|---|---|---|---|---|';
			foreach ( \CourseCaptain\Privacy\RetentionPolicy::KEYS as $sub => $meta ) {
				$out[] = sprintf(
					'| `%s` | %s | %s | %s | %s |',
					$sub,
					self::cell( SettingsFields::ui( 'privacy', 'retention.' . $sub )['label'] ),
					'`' . self::json( $meta['default'] ) . '`',
					isset( $meta['min'] ) ? $meta['min'] . '–' . $meta['max'] . ( empty( $meta['nullable'] ) ? '' : ', null = aus' ) : 'fest',
					array(
						'years' => 'Jahre',
						'days'  => 'Tage',
						'count' => 'Anzahl',
						'fixed' => '–',
					)[ $meta['unit'] ]
				);
			}
			$out[] = '';
			$out[] = '## Sperren und Übersteuerungen';
			$out[] = '';
			$out[] = 'Rangfolge: Sperre der Website > Prüfung/Aktivität > Kurs (Gruppe, Medium) > Website-Wert > eingebauter Standard. Eine Sperre lässt gespeicherte Kurs- und Prüfungswerte ruhen; nach dem Entsperren gelten sie wieder. Die Seite „Sperren und Kurs-Übersteuerungen“ zeigt je Einstellung die Anzahl eigener Werte, sperrt nach einem Bestätigungsschritt mit der Zahl der betroffenen Kurse und Prüfungen und setzt ausgewählte eigene Werte gesammelt auf „erben“ zurück (veröffentlichte Prüfungen bleiben unverändert).';
			$out[] = '';
			$out[] = '| Schlüssel | Bezeichnung | Ebenen | Sperrbar | Wirksam |';
			$out[] = '|---|---|---|---|---|';
			foreach ( $over as $key => $meta ) {
				$out[] = sprintf( '| `%s` | %s | %s | %s | %s |', $key, self::cell( EffectiveSettings::labels()[ $key ] ?? $key ), self::cell( implode( ', ', $meta['scopes'] ) ), empty( $meta['lockable'] ) ? 'nein' : 'ja', self::cell( EffectiveSettings::class_label( $meta['class'] ) ) );
			}
			$out[] = '';
			$out[] = '## WP-CLI';
			$out[] = '';
			$out[] = 'Akteur ist `system:cli`; jede Änderung läuft durch dieselbe Prüfung wie das Formular und steht im Änderungsprotokoll (Kontext `cli`).';
			$out[] = '';
			$out[] = '```';
			$out[] = 'wp course-captain settings list [--tab=<tab>] [--format=json]';
			$out[] = 'wp course-captain settings get <tab.feld> [--format=json]';
			$out[] = 'wp course-captain settings set <tab.feld> <wert> [--confirm] [--format=json]';
			$out[] = 'wp course-captain settings export [--output=<datei>] [--include-personal] [--include=roles]';
			$out[] = 'wp course-captain settings import <datei> [--dry-run] [--tabs=<a,b>] [--surfaces=<roles,locks>] [--confirm] [--yes]';
			$out[] = 'wp course-captain settings reset <tab> --yes | reset <tab.feld> [--confirm]';
			$out[] = 'wp course-captain settings get|set|reset <tab.feld.unterschlüssel> …   (z. B. privacy.retention.audit_log_years 5)';
			$out[] = 'wp course-captain settings retention [--format=json]   (Vorschau des nächsten Aufbewahrungslaufs, löscht nichts)';
			$out[] = '```';
			$out[] = '';
			$out[] = '- Werte: Text wie eingegeben, Ja/Nein als `true`/`false`, Listen und Objekte als JSON (`\'{"visibility":"after_submit"}\'`).';
			$out[] = '- Änderungen, die in der Oberfläche einen Bestätigungsdialog zeigen, brauchen `--confirm`; das Einschalten der Datenlöschung beim Entfernen des Plugins braucht `--confirm=<Name der Website>`. Ohne Bestätigung: Exit-Code 1, nichts gespeichert.';
			$out[] = '- `export --output` schreibt nur neue Dateien außerhalb des WordPress-Verzeichnisses mit Rechten `0600`.';
			$out[] = '- `import` zeigt zuerst die Unterschiede; ohne `--yes` wird nichts geschrieben.';
			$out[] = '';
			$out[] = '## Export und Import';
			$out[] = '';
			$out[] = 'Die Einstellungsdatei (`format: course-captain-settings`, Version 1) enthält die Tabs und je Fläche einen eigenen Abschnitt: `locks` (Sperren, immer), `roles` (Rollen, nur mit Opt-in). Weitere Flächen (z. B. Mailvorlagen) melden sich über den Filter `course_captain_settings_transfer_providers` an. Der Import zeigt je Tab und Fläche eine Vorschau der Unterschiede, wendet ausgewählte Bereiche einzeln und atomar an und legt für Tabs und Rollen einen Rückgängig-Stand für 30 Tage an. Nie enthalten: Geheimnisse, umgebungsgebundene Felder (Spalte Export = nein), Kurs- und Prüfungs-Übersteuerungen.';
			$out[] = '';
			return implode( "\n", $out );
		}

		/**
		 * Type and allowed values.
		 *
		 * @param array<string, mixed> $def Definition.
		 * @return string
		 */
		private static function type( array $def ): string {
			$type = (string) ( $def['type'] ?? '' );
			switch ( $type ) {
				case 'enum':
				case 'set':
					$text = $type . ': ' . implode( ', ', array_map( static fn( $v ): string => '`' . (string) $v . '`', (array) $def['values'] ) );
					break;
				case 'int':
					$text = 'int' . ( isset( $def['min'] ) || isset( $def['max'] ) ? ' ' . ( $def['min'] ?? '…' ) . '–' . ( $def['max'] ?? '…' ) : '' );
					break;
				case 'string':
				case 'text':
					$text = $type . ' (max. ' . (int) ( $def['max'] ?? 255 ) . ')';
					break;
				case 'object':
					$text = 'object {' . implode( ', ', array_map( 'strval', array_keys( (array) ( $def['fields'] ?? array() ) ) ) ) . '}';
					break;
				case 'ip_list':
					$text = 'ip_list (max. ' . (int) ( $def['max'] ?? 50 ) . ')';
					break;
				default:
					$text = $type;
			}
			return $text . ( empty( $def['nullable'] ) ? '' : ', null' );
		}

		/**
		 * Override scopes text.
		 *
		 * @param array<string, mixed>|null $meta Metadata.
		 * @return string
		 */
		private static function scopes( ?array $meta ): string {
			if ( null === $meta ) {
				return '–';
			}
			return implode( ', ', (array) $meta['scopes'] ) . ( empty( $meta['lockable'] ) ? '' : ' (sperrbar)' );
		}

		/**
		 * JSON of a default.
		 *
		 * @param mixed $value Value.
		 * @return string
		 */
		private static function json( $value ): string {
			return (string) json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		/**
		 * Table cell (pipes escaped, one line).
		 *
		 * @param string $text Text.
		 * @return string
		 */
		private static function cell( string $text ): string {
			return str_replace( '|', '\|', self::line( $text ) );
		}

		/**
		 * One line.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		private static function line( string $text ): string {
			return trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		}

		/**
		 * Anchor of a tab section.
		 *
		 * @param string $tab Tab.
		 * @return string
		 */
		private static function anchor( string $tab ): string {
			return 'tab-' . $tab;
		}
	}

	if ( realpath( (string) ( $_SERVER['SCRIPT_FILENAME'] ?? '' ) ) === realpath( __FILE__ ) ) {
		$root    = dirname( __DIR__, 2 );
		$target  = $root . '/' . SettingsDoc::TARGET;
		$content = SettingsDoc::build() . "\n";
		$mode    = $argv[1] ?? '';
		if ( '--write' === $mode ) {
			file_put_contents( $target, $content );
			fwrite( STDOUT, 'written ' . SettingsDoc::TARGET . "\n" );
			exit( 0 );
		}
		if ( '--check' === $mode ) {
			$current = is_file( $target ) ? (string) file_get_contents( $target ) : '';
			if ( $current !== $content ) {
				fwrite( STDERR, SettingsDoc::TARGET . " is out of date with the settings register. Run: php8.5 tools/docs/settings-doc.php --write\n" );
				exit( 1 );
			}
			fwrite( STDOUT, SettingsDoc::TARGET . " is up to date.\n" );
			exit( 0 );
		}
		fwrite( STDOUT, $content );
		exit( 0 );
	}
}
