# R1-31 · Settings-Storage, Migration und Berechtigungen – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-31):** Settings-Register als eine Quelle, Speicherung je Tab, Rechte, Migration, Reset/Undo, Export ohne Secrets.
**Quellen:** R0-27 §1.1 (SR-1…SR-8), §1.3 (Register), §2.1–§2.6 (S-1…S-10, Migration, Reset, Export), R0-05 GM-178, GM-181; Inventur INV-C-069. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Settings/SettingsSchema.php` | Register der Free-Kernfelder je Tab (general, display, learning, exams, enrollment, certificates, emails, privacy, hardening, advanced) mit Typ, Default (genau einmal), Recht (#19, admin-only), Datenschutzklasse, Nebenwirkungen, Exportflag; Autoload je Tab nach §2.1; Validator für bool, int (Grenzen, nullable), enum, set, slug, string/text (Länge), email, page_id, IP/CIDR-Liste, object (Unterfelder) |
| `src/Settings/Settings.php` | Option je Tab `{_schema, _rev, _updated_at, _updated_by, values}`; **Lesen** mit feldweisem Default bei fehlendem/typfremdem Wert (S-10); **Speichern** als Teil-Update nur für gerenderte Felder (fehlender Schlüssel = unverändert, INV-C-069), Fremdschlüssel verworfen, Recht je Feld, alles oder nichts, `_rev`-Konflikt (`ccap_settings_conflict`), erstes Speichern materialisiert alle Felder, ohne Änderung kein Schreiben; Nebenwirkungen (Rewrite-Flush, Cache-Generation, Policy-Flush); Audit `settings_saved`/`settings_reset` mit Vorher/Nachher nur für public/internal, personal nur „changed“, secret nie; **Reset** je Tab mit Undo (eine Generation, 30 Tage); **Migration** v0 → v1 idempotent (unbekannte/ungültige Schlüssel verworfen, GM-181), Neuinstallation schreibt nichts; **Downgrade**-Passthrough unbekannter Schlüssel eines neueren Schemas (E-90); **Export** im Format `course-captain-settings` v1 ohne Secrets, personal nur mit Opt-in, `site_hash` statt URL; **Fremdschreiber**-Schutz über `pre_update_option_*` (S-9) |
| `src/Kernel/Activator.php`, `src/Kernel/Plugin.php` | Migration bei Aktivierung, Registrierung des Schreibschutzes |
| `src/Learning/AnswerService.php` | liest `learning.feedback` über den Dienst |
| `tests/integration/SettingsTest.php` | 5 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **129 / 1 341** · Multisite **138 / 1 394** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0.

## 3. Tests

| ID | Test |
|---|---|
| GM-178 | Defaults ohne Option; Grenzverletzung (500 Tage) abgewiesen, nichts gespeichert; `false` speicherbar; erstes Speichern materialisiert alle Felder; kaputter Wert → Default (S-10); unbekannter Schlüssel → Fehler |
| S-2…S-6 | Teil-Update behält andere Felder, Fremdschlüssel nicht gespeichert, alles oder nichts, veraltete `_rev` → Konflikt, Abonnent `ccap_forbidden`, Settings-Verwalter ohne `manage_options` kann `delete_data_on_uninstall` nicht ändern, unveränderter Wert schreibt nicht |
| SR-6 / S-8 | Slug-Änderung plant Rewrite-Flush; Audit mit Vorher/Nachher; Absendername nur „changed“ ohne Klartext |
| §2.4 / §2.5 | Reset mit Liste, Undo stellt wieder her, nur eine Generation; Export ohne personal/Uninstall-Schalter, mit Opt-in personal; `site_hash`, keine URL |
| GM-181 / S-9 / E-90 | Migration übernimmt gültige bekannte Werte, verwirft unbekannte/ungültige, idempotent, Version gesetzt; ungültiger Fremdschreiber abgewiesen; neueres Schema: unbekannter Schlüssel bleibt, `_schema` bleibt, `_rev + 1` |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | GM-178 erwartet LB-Klemmung (`500 → 90`) | R0-27 S-5 (verbindlich für R1-31) verlangt Ablehnung „alles oder nichts“ mit feldnahem Fehler; umgesetzt nach R0-27. Klärung im R1-Gate dokumentiert |
| A-2 | GM-180 `default_access` (`logged_in`/`entitled_only`) | kein Schlüssel im Register R0-27 §1.3; Zugang über Grants/`free`-Regeln (R1-13/R1-14). Klärung im R1-Gate |
| A-3 | Admin-Oberfläche (`admin-post.php?action=ccap_settings_save`, Nonce je Tab, Formular-Transient, Risiko-Bestätigungstoken S-7) | R1-32 (Setup/Tabs) auf diesem Dienst |
| A-4 | Rollen-Tab, Mailvorlagen, `privacy.retention`-Unterschlüssel, Zertifikatsvorlage, Hardening-Zeitstempel | R1-34, R1-21, R1-17, R1-44, R1-45 |
| A-5 | Import (Diff-Vorschau, Tab-Auswahl, Rate-Limit), WP-CLI `settings get|set|export|import|reset` | R1-36 bzw. R1-28 |
| A-6 | Autoload-Budget ≤ 4 KB (SET-009), Autoload-Korrektur bestehender Installationen | R1-36 (Regression) |
| A-7 | Kurs-/Prüfungs-Overrides | R1-33 |

## 5. Übergaben

R1-32 (Formulare), R1-33 (Overrides), R1-34 (Rollen/Datenschutz/Medien/Mail), R1-36 (Import, Regression, Autoload). Review durch zweite Person vor Merge (AG-05).
