# R1-36 · Admin-Settings-Regression und Migrationstests – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-d`
**DoD (TODO R1-36):** Reset, invalid inputs, Downgrade, WP-Werte-Vererbung, Multisite-Grenzen und Security/Accessibility getestet.
**Quellen:** R0-27 §2.1 (Autoload-Budget), §2.3 (Migration, Default-Wechsel, Downgrade E-90), §2.4 (Reset), §2.5 (Export/Import), §2.6 (Multisite), §4.1 (SET-001…SET-023); R0-31 §4 (EX-1…EX-6, IM-1…IM-5, RS-1…RS-5), §5 (PSD-057…PSD-064); R1-31-Nachweis A-5/A-6 (Übergaben Import, Autoload). Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Settings/SettingsTransfer.php` | Import: Datei ≤ 256 KB, Format/`format_version` geprüft, neuere `settings_version` abgelehnt; Vorschau ohne Schreiben; je Tab atomar mit Undo-Snapshot (IM-5) und Audit `settings_saved` (`context=import`) plus `settings_imported` (Tabs, Schlüssel, keine Werte); Risiko-Felder nur mit Bestätigung `tab.field`, sonst übersprungen und im Bericht (IM-1/IM-2, gleiche Erkennung wie die UI); umgebungsgebundene Felder, Secrets und Platzhalter ignoriert (IM-3, SH-6); fremder `site_hash` nur Hinweis (IM-4); ungültiger Wert → Tab nicht importiert; Rate-Limit 10/h je Konto, fail-closed |
| `src/Admin/SettingsBackup.php` | versteckte Seite „Einstellungen sichern“ (#19, Link im Kopf der Einstellungen): Export als Browser-Download ohne Serverkopie (EX-6) mit Opt-in für `personal`; Import mit Vorschau (Diff je Tab, übersprungene Felder mit Grund, je Risiko-Feld ein beschriftetes Bestätigungskästchen mit Dialogtext), Tab-Auswahl, Übernahme |
| `src/Settings/SettingsSchema.php` | **geteilt:** `export` kennt `never_env` (EX-2) für `privacy.trusted_proxies` und `advanced.file_delivery` |
| `src/Settings/Settings.php` | **geteilt:** Export überspringt alles außer `export === true`; `migrate()` korrigiert die Autoload-Flags vorhandener Tab-Optionen aus dem Register (SET-009, §2.1 Regel 3) |
| `src/Kernel/Plugin.php`, `src/Admin/SettingsScreen.php`, `src/Ops/AuditView.php` | **geteilt:** Registrierung, Link „Einstellungen sichern“, Audit-Ereignis `settings_imported` |
| `tests/integration/SettingsRegressionTest.php` | 14 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler · PHPStan 0 · Unit **104** · Integration **317** · Multisite **330** · Free-Guard 0 · Textdomain-Lint 0 · keine neuen Hooks/Routen.

## 3. Tests

| Test | Fälle |
|---|---|
| `test_SET_001_schema` | SET-001: Pflichtschlüssel je Feld, jeder Default besteht den eigenen Validator unverändert |
| `test_SET_002_005_partial_save` | SET-002/003/005: Teilformular, Kästchen aus = `false`, fehlendes Feld unverändert, fremde/unbekannte Schlüssel verworfen, andere Tabs bytegleich |
| `test_SET_004_008_013_invalid_conflict_broken` | SET-004 (9 ungültige Eingaben über 8 Tabs, `data.fields`, nichts gespeichert), SET-008 (Konflikt), SET-013 (kaputte Option → Defaults, ungültiger Fremdschreiber abgewiesen) |
| `test_SET_009_autoload_budget` | SET-009/ADM-062: Maximalwerte, Autoload-Flags nach §2.1, Summe ≤ 4 KB, Härtung ≤ 0,5 KB, Migration korrigiert falsches Flag |
| `test_SET_011_012_defaults_and_downgrade` | SET-011 (erstes Speichern materialisiert alle Defaults), SET-012 (neueres Schema: Passthrough beim Speichern und Reset, keine Rückmigration) |
| `test_SET_014_reset_scope` | SET-014/RS-2: nur der Tab, andere Tabs unverändert, kein globales Reset, Undo, Audit |
| `test_PSD_057_064_export` | PSD-057 (kein personal ohne Opt-in, EX-2-Felder fehlen, kein Rollen-Tab, Härtung als Modus), PSD-064/EX-5 (Kopf ohne URL, Login, E-Mail), RS-4 (kein Undo im Export) |
| `test_SET_016_PSD_058_059_import` | SET-016/PSD-058/PSD-059: andere Site, Vorschau schreibt nichts, Gründe `confirmation`/`environment`/`secret`, bestätigtes Feld übernommen, unbestätigtes nicht, Undo-Snapshot, Audit; falsches Format, zu groß, neuere Version; Recht #19; 11. Import → `ccap_rate_limited` |
| `test_SET_016_import_screen` | Nonce, Vorschau-Transient, Bestätigungskästchen je Risiko-Feld, Tab-Auswahl, abgelaufene Vorschau, kein Zugriff ohne #19 |
| `test_SET_023_wordpress_inheritance` | SET-023: kein Register-Feld dupliziert WP-Werte; leer/`null` = WordPress erben; Hilfetext sichtbar |
| `test_SET_018_multisite_boundaries` | SET-018 (nur Multisite): Werte und Setup-Zustand je Site, keine Netzwerkoptionen |
| `test_SET_020_manifest` | SET-020/PSD-061: alle Tab-Optionen, Undo-Slots (inkl. Rollen), Version und Setup-Zustand im Uninstall-Manifest |

Ergänzend aus R1-32/R1-34: ADM-072/075/077/079 (Formularebene), PSD-004/006/008/010, PSD-034…037.

## 4. Security-/Accessibility-Review (Selbstprüfung)

- Import nutzt denselben Validator, dieselbe Risiko-Erkennung und dieselben Feldrechte wie die Formulare; umgebungsgebundene Felder sind weder exportier- noch importierbar.
- Upload nur per admin-post mit Nonce und #19; Datei wird nicht gespeichert (nur 10 min im Nutzer-Transient für die Vorschau).
- Vorschau als Liste mit `fieldset`/`legend` je Tab und beschrifteten Kästchen.

## 5. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | WP-CLI `settings get|set|export|import|reset`, CLI-Export `0600` außerhalb des Webroots (PSD-007, PSD-033, PSD-063, SET-021) | CLI-Befehle mit R1-28; Dienste (`Settings`, `SettingsTransfer`) sind dafür vorbereitet |
| A-2 | Import des Rollen-Tabs und der Mailvorlagen (Opt-in) | nicht im Exportformat; Rollen-Import mit Hochrisiko-Bestätigung **Klärung** **Entschieden 24.11.2026: Opt-in-Export, Import mit RC-07 beibehalten (E-365).** |
| A-3 | Seiten-IDs als `{id, slug, title}` im Export (§2.5) | Export enthält die ID; Import übernimmt sie nur, wenn die Seite die Seitenregeln besteht (F-10…F-12), sonst Fehler im Bericht |
| A-4 | Retention-Hinweis im Dateikopf (EX-4), PSD-060 Fristverkürzung | `privacy.retention` fehlt im Register (R1-17) |
| A-5 | Playwright/Screenreader (SET-022, PSD-005) | Struktur per PHPUnit; Browserläufe mit R1-19/R1-29 |
| A-6 | Default-Wechsel einer künftigen Version (SET-011) | durch Materialisierung beim ersten Speichern abgesichert; Migrationsfunktion entsteht mit dem ersten echten Default-Wechsel |

Review durch zweite Person vor Merge (AG-05).
