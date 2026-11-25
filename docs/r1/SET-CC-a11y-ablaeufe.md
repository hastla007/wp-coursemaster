# Nachweis SET-CC-21, SET-CC-P5, SET-CC-P2, UI-F9 (Slice „a11y-ablaeufe“, Branch `r3-f`)

Stand 23.11.2026, Basis 0081aef. TODO-Häkchen setzt die Koordination.

## SET-CC-21 · A11Y-Abnahme der Einstellungsseite

**Prüflauf:** `tools/qa/e2e/settings-a11y.sh [db]` (frische WordPress-Site aus `vendor/roots/wordpress-no-content`, Plugin aus dem Arbeitsbaum, `cc-fixture-neutral-v1`, `php -S` über `tools/release/php-min.sh` = PHP 8.5, Chromium über Playwright, Tabellenpräfix `ccapset_`) → `tools/qa/e2e/settings-a11y.js`. Gemeinsame Prüfungen in `tools/qa/e2e/a11y-checks.js` (auch von `flow-a11y.js` genutzt). axe-core 4.10.2 über `AXE_PATH` (aus dem npm-Register in den Scratchpad geladen, nicht im Repository).

Abgedeckte Seiten (40 Prüfpunkte): alle 10 Tabs, je Tab der Fehlerzustand (ein ungültiger Wert wird abgeschickt), alle 8 Schritte des Setup-Assistenten plus Fehlerzustand (Adresse `wp-admin`), Mailtexte (Übersicht, Editor, Fehlerzustand mit unbekanntem Platzhalter), Sperren und Kurs-Übersteuerungen (Übersicht, Sperrseite), Einstellungen sichern, Änderungsprotokoll-Ansicht „Einstellungen“, Kasten „Settings for this course“ im Block-Editor, Lernbereich (Mein Lernen, Kursraum, Lektion) mobil.

Geprüft je Seite: Überschriften ohne Sprung und genau eine h1, zugängliche Namen, eindeutige IDs, Label je Steuerelement, alle `aria-describedby`-Ziele vorhanden, kein positiver `tabindex`, Tastaturlauf (sichtbarer Fokus an jeder Station, Reihenfolge folgt dem DOM, kein Fokus außerhalb der Breite), axe-core (wcag2a/aa, 21a/aa, 22aa), kein horizontales Scrollen bei 320 und 390 px. Fehlerzustand: Zusammenfassung `role="alert"` **fokussiert**, Links auf die Felder, Feld mit `aria-invalid="true"` und `aria-describedby` auf einen sichtbaren „Error:“-Text.

**Befunde und Behebung (erster Lauf 1/37 grün → 40/40 grün):**

| Befund | Ursache | Behebung |
|---|---|---|
| Fehlerzusammenfassung nach dem Speichern nicht fokussiert (alle Tabs, Assistent, Mailtexte) | WordPress verschiebt `.notice`-Elemente nach seinem eigenen ready-Handler; Verschieben verliert den Fokus. Auf Mailtexten/Sperren/Sicherung war `admin-settings.js` gar nicht geladen | `assets/js/admin-settings.js` fokussiert nach `load` erneut, wenn der Fokus noch auf `body` liegt; `AdminUi::settings_assets()` lädt Skript und Layoutregeln auf Einstellungen, Assistent, Mailtexten, Sperren, Sicherung und Protokoll |
| Horizontales Scrollen bei 320/390 px (Tab Allgemein und Assistent: Seitenauswahl 400 px; Mailtexte/Sicherung: Datei-Feld; Sperren: 571-px-Tabelle; Protokoll: Tabelle) | feste Breiten | `AdminUi::SETTINGS_CSS` (`max-inline-size:100%` für Auswahl/Datei/Text; nur logische Eigenschaften); breite Tabellen in `AdminUi::scroll_table()`/`scroll_open()`: eigener, fokussierbarer, benannter Bereich mit `overflow-x:auto` (WCAG 1.4.10, 2.1.1) |
| axe `link-in-text-block` (Mailtext-Übersicht, 16 Links) | Links in Tabellenzellen ohne Unterstreichung | Unterstreichung für `td a` |
| axe `target-size` (Protokoll-Filter, 23 px hoch) | `<input>` ohne `type` bekommt die Admin-Stile nicht | `type="text"` in `AuditScreen` |
| axe `target-size` auf „Groups“ (Kurs-Kästchen im Formular „Create group“, gefunden im Lauf `live-flows.sh`/`flow-a11y.js`) | Kästchen nur durch `<br>` getrennt, Abstand < 24 px | ein Absatz je Wahl (`GroupsScreen`) |
| doppelte `id="_wpnonce"` | `wp_nonce_field()` (WordPress-Kern) | kein A11Y-Befund (versteckte Eingaben, nicht im Barrierefreiheitsbaum, nicht referenziert); die ID-Prüfung lässt `input[type=hidden]` aus, dokumentiert in `a11y-checks.js` |

DOM-Checks mit `tests/support/A11yDom.php` für alle Einstellungsflächen: `tests/integration/SettingsFieldContractTest::test_settings_surfaces_pass_the_dom_checks` (Assistent alle Schritte, Mailtexte, Editor, Sperren inkl. Scroll-Bereich, Sicherung, Protokoll-Ansicht).

## SET-CC-P5 · Bedienung, Sprache, Barrierefreiheit

- **Hilfetext und Fehlermeldung an jedem Feld (automatisch):** `tests/integration/SettingsFieldContractTest::test_every_field_has_help_and_a_field_level_error` läuft über das Register (`SettingsSchema` via `SettingsScreen::all_keys`) und den UI-Katalog (`SettingsFields`): Label ≠ Schlüssel, Hilfetext > 20 Zeichen und gerendert; dann je Feld ein ungültiger Wert über den echten Formularweg (`process_save` → `html`): Ergebnis `invalid`, `<p id="…-error">Error: …`, `aria-invalid` + `aria-describedby`, Link in der Zusammenfassung, keine DOM-Probleme. Für Werte, die eine Person tippen kann, darf die Meldung **nicht** die allgemeine „check the value“ sein; Auswahlfelder (nur durch manipulierte Anfrage ungültig) bekommen die neue Meldung „choose one of the offered options“; Felder ohne möglichen Fehlwert (Kontrollkästchen, Ja/Nein) werden mit erzwungenem Fehler auf Markup geprüft. 801 Zusicherungen.
- **Gefundene Fehler, behoben:**
  - Teilfelder von Objekten (`hardening.login_limits.*`, `learning.review_intervals.*`, `display.button_labels.*`) zeigten „Error: …: check the value.“ an **allen** Teilfeldern. Jetzt bestimmt `SettingsRules::invalid_part()` das erste ungültige Teilfeld; `SettingsForm::with_params()` setzt dessen Regel (z. B. `range` mit eigenen Grenzen) und `params.sub`; `SettingsForm::error_for()`/`error_key()` markieren nur dieses Teilfeld und verlinken es in der Zusammenfassung (Tabs und Assistent). Test `test_object_part_error_is_marked_at_the_part`.
  - `display.logo_id` (nur Minimum): „enter a whole number from 0 to .“ → neue Meldung „enter a whole number of at least 0“.
- **Mobil 320/390 px ohne horizontales Scrollen:** Browserlauf (alle Einstellungsseiten, Assistent, Lernbereich Mein Lernen/Kursraum/Lektion) – nach den Korrekturen 0 px überall.
- **Hinweiszeilen ausblendbar:** **übersprungen** – SET-CC-01 (Hinweiszeilen, Andockpunkt `course_captain_settings_register_fields_v1`) ist in diesem Stand nicht vorhanden (kein Code dazu in `src/`). Nach dem Merge von SET-CC-01 in `settings-a11y.js` ergänzen (Ausblenden per Tastatur, Fokus danach, Zustand je Person).
- Neue Texte: 2 msgids (s. u.), de_DE „du“ und de_DE_formal „Sie“ ergänzt, `.mo` neu gebaut, `check-address` 0 Befunde, textdomain-lint 0.

## SET-CC-P2 · Abläufe von Anfang bis Ende

`tests/integration/SettingsFlowsTest.php` (Gruppe SET-CC-P2), über die Dienste (`SelfEnrollment`, `ActivityProgress`, `ExamSessions`, `OutboxDispatcher`, `CertificateService`, `GrantService`, `Relations`):

1. **Minimalwerte + Doppelklicks:** Zugang 1 Tag (genau 86 400 s), 1 Versuch, Abklingzeit 0, automatische Ausstellung. Einschreiben ×2 (gleicher Zugang), Lektion ×2, Prüfungsstart ×2 mit gleichem Schlüssel (gleicher Versuch), Antworten ×2, Abgabe ×2 (gleiches Ergebnis, eine Auswertung), Abbrechen danach `ccap_exam_already_submitted`, Outbox ×2 (je Nachweis genau ein Zertifikat), zweiter Start `ccap_exam_attempts_exhausted`, auch nachdem der globale Wert auf 100 steigt (Regel friert bei Veröffentlichung ein).
2. **Zugangsdauer:** 0 und 3651 → Regel `range`; leer → unbefristet; Maximum 3650 Tage; Ablauf auf die Sekunde unabhängig von der Zeitzone (Pacific/Tongatapu, Europe/Vienna; Dienste mit fester Uhr); Verlängerung; Ablaufjob ×2 (zweiter Lauf 0); Verlängern nach Ablauf `ccap_conflict`; erneutes Einschreiben → neue Revision.
3. **Widersprüchliche Kombinationen:** Selbstanmeldung aus (`ccap_forbidden`, `self_enrollment_off`); Selbstanmeldung an + Kurs geschlossen (`ccap_enrollment_closed`); Zertifikatsmodul aus + automatische Ausstellung an → kein Zertifikat; 1 Versuch + abgebrochene zählen nicht → Neustart möglich; Abbrechen ×2.
4. **Fristen über Mitternacht/Jahreswechsel:** Ausstellungs- und Gültigkeitsdatum in UTC, Europe/Vienna und Pacific/Tongatapu (UTC+13) über den 31.12.; Sommerzeitwechsel Wien (März/Oktober); „heute“ für die Gültigkeit; „Available from …“ (Drip) im Kalender der Website; Prüfung mit 10 Minuten ab 23:55 UTC am 31.12. (Frist 2027-01-01 00:05:00, Auswertung nach Frist, Wiederholung 0).
5. **Zertifikat mit Gültigkeit**, bestanden 23:30 UTC am 31.12. in Wien → `valid_until` 2028-01-01.

**Behobener Fehler (Zeitzone):** Die Gültigkeit eines Zertifikats wurde als UTC-Datum berechnet (`gmdate`) und mit dem UTC-„heute“ verglichen, das Ausstellungsdatum aber im Kalender der Website angezeigt (`site_date`). In Wien zeigte ein am 1.1. (lokal) ausgestelltes Zertifikat mit 365 Tagen „gültig bis 31.12.“; in UTC+13 galt ein Zertifikat am Morgen nach dem letzten Gültigkeitstag noch als gültig. Neu: `CertificateService::site_date_after()` (lokales Ausstellungsdatum + Kalendertage) und `site_today()` für `status_of()` und `recertify_hint()`. Bereits ausgestellte Zertifikate behalten ihr gespeichertes Datum (Snapshot unverändert); bei Site-Zeitzone UTC ändert sich nichts (bestehende Tests unverändert grün).

**Beobachtung, kein Fehler:** Kursabschluss vor der Prüfung ergibt zwei Zertifikate (Abschluss und Prüfung, je Nachweis eines); umgekehrt unterdrückt ein gültiges Zertifikat das Abschluss-Zertifikat (`auto_issue`, CRT-001). Zeiträume von Zugängen und Drip sind exakte 86 400-s-Tage in UTC (gewollt, gleich in jeder Zeitzone).

**Werkzeug:** `tools/qa/e2e/flow-author.js` wartet nach dem Speichern der Metaboxen zusätzlich auf die Antwort `ccap_editor_flash` (UI-F6 ersetzt die Kästen danach); vorher ging eine schnell danach getroffene Auswahl im Test verloren. Für Personen bleibt ein Fenster von einer Anfrage, in dem eine Auswahl im Kasten durch das Neuzeichnen ersetzt wird (offen, s. u.).

## UI-F9 · Usability-Testkit neu bewertet

`docs/r1/R1-29/bereitschaft.md` neu bewertet (Matrix je Aufgabe mit Spalte „17.11.“, Belege aus Code, Tests und Browserläufen; §2 Befundstatus; §3a Läufe; §4 Empfehlung). Ergebnis: 0 statt 18 Zeilen blockiert; teilweise bleiben AU-T01/T02/T03/T12, UX-012, LE-T06/UX-035 (B-03 Karteikarten in Blueprint-Prüfungen), LE-T09 (Datenauskunft nur per Kurzcode-Seite), UX-075, UX-070…074, UX-078. Die Usability-Site (`setup-site.sh`, §2.1/§2.2) wurde nicht neu aufgebaut (eigene DB `ccap_usab…` nötig) – Auflage vor Runde 1.

## Tests und Gates

Alle auf PHP 8.5.12, MariaDB 10.11, Testdatenbank `ccap_test_g`:

- `phpcs -q`: 0 Fehler, 0 Warnungen · `phpstan analyse`: keine Fehler (aus einem anderen Arbeitsverzeichnis mit `-c phpstan.neon.dist` gestartet, weil `vendor/` geteilt ist) · Unit: 141 Tests, 3073 Zusicherungen grün.
- Integration einfach: 550 Tests, 34 659 Zusicherungen, 3 übersprungen, grün (Lauf vor der letzten Markup-Änderung in `GroupsScreen`; danach `GroupCertificateAdminTest`, `A11yI18nTest`, `AuthoringScreensTest`, `LearnerUiTest`, `SettingsFieldContractTest`, `SettingsFlowsTest` erneut grün: 42 Tests).
- Integration Multisite: 563 Tests, 1 Fehlschlag `MigrationResumeTest::test_MIG_003_lease_alone_excludes_without_named_lock` (Zeitabhängigkeit paralleler Prozesse unter Last durch parallele Suiten anderer Sitzungen auf demselben Server; berührt keinen geänderten Code) – einzeln zweimal wiederholt: 21 Tests grün.
- Neue Tests: `SettingsFieldContractTest` (3 Tests, 801 Zusicherungen), `SettingsFlowsTest` (5 Tests).
- `free-guard`: 0 Befunde · `textdomain-lint`: 0 · `check-address`: 0 (de_DE 2491, de_DE_formal 2491 Übersetzungen).
- Browser: `settings-a11y.sh` 40/40 OK (axe-core 4.10.2), `live-smoke.sh` OK, `live-flows.sh` OK (Flows 1–3, mobil, A11Y, Benchmark; axe-core 4.10.2), keine PHP-Meldungen im Serverprotokoll.

## Geänderte Dateien

- Code: `assets/js/admin-settings.js`, `src/Admin/AdminUi.php`, `src/Admin/SettingsForm.php`, `src/Admin/SettingsFields.php`, `src/Admin/SettingsScreen.php`, `src/Admin/SetupWizard.php`, `src/Admin/MailTextsScreen.php`, `src/Admin/SettingLocksScreen.php`, `src/Admin/SettingsBackup.php`, `src/Ops/AuditScreen.php`, `src/Admin/GroupsScreen.php`, `src/Settings/SettingsRules.php`, `src/Certificate/CertificateService.php`.
- Tests/Werkzeuge: `tests/integration/SettingsFieldContractTest.php`, `tests/integration/SettingsFlowsTest.php`, `tools/qa/e2e/a11y-checks.js`, `tools/qa/e2e/settings-a11y.js`, `tools/qa/e2e/settings-a11y.sh`, `tools/qa/e2e/flow-a11y.js` (nutzt `a11y-checks.js`), `tools/qa/e2e/flow-author.js`.
- Übersetzung: `languages/course-captain-de_DE(.po|.mo)`, `languages/course-captain-de_DE_formal(.po|.mo)` – 2 neue msgids: „Error: %s: choose one of the offered options.“, „Error: %1$s: enter a whole number of at least %2$s.“ (`.pot` nicht neu erzeugt).
- Doku: `docs/r1/R1-29/bereitschaft.md`, dieser Nachweis.
- Keine neuen REST-Routen, Hooks, Audit-Aktionen, Registerfelder oder Hilfetexte (Settings-Doku unverändert).

## Offen

- Hinweiszeilen ausblendbar (P5) prüfen, sobald SET-CC-01 gemergt ist.
- B-03 (Karteikarten in Blueprint-Prüfungen) – Produktentscheidung. **Entschieden 24.11.2026: immer ausgeschlossen (E-351).**
- UI-F6-Kästen: Auswahl während des Neuzeichnens nach dem Speichern geht verloren (ein Anfrage-Fenster); Vorschlag: Kasten bis zur Antwort `aria-busy` und Steuerelemente sperren.
- Firefox/WebKit und Hilfstechnik-Durchläufe mit Personen bleiben bei UI-F7/R1-29.
