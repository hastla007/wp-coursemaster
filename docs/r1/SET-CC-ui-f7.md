# Nachweis UI-F7 · Browser-Matrix (Firefox/WebKit, Admin-Flows mobil, axe-core)

Stand 24.11.2026, Branch `r5-e` auf 9f6eaa8. TODO-Häkchen setzt die Koordination.

**Ergebnis:** Alle Flows und A11Y-Prüfungen laufen auf Chromium, Firefox und WebKit grün, jeweils am Desktop und als Telefon mit 390 px, axe-core 4.14.1 eingeschlossen. Dabei wurden zwei echte Fehler in der mobilen Verwaltung gefunden und behoben (siehe unten). **Offen bleibt der Praxis-Benchmark mit Person (UX-090).** Er braucht eine reale Testperson und lässt sich nicht automatisieren (siehe §5).

## 1. Umgebung

| Komponente | Version |
|---|---|
| Playwright | 1.63 (vorinstalliert, `/opt/node-tools/node_modules/playwright`; Browser in `/opt/pw-browsers`) |
| Chromium | 153.0.8010.12 (chromium-1243) |
| Firefox | 155.0 (firefox-1543) |
| WebKit | 26.6 (webkit-2359) |
| axe-core | 4.14.1 (`npm pack axe-core@4.14.1` in den Scratchpad, `AXE_PATH` auf `axe.min.js`; nicht im Repository) |
| Node | 22.23.4 |
| PHP / WordPress / DB | PHP 8.5.12 (`php -S`, 4 Worker) · WordPress 7.1.4 aus `vendor/roots/wordpress-no-content` · MariaDB 10.11.14, Testdatenbank `ccap_test_e` |

Für WebKit fehlten zunächst Host-Bibliotheken. `playwright install-deps webkit` brach ab, weil `apt-get update` nur am PPA `ondrej/php` scheiterte (dessen Label hatte sich zu „PPA for PHP (deprecated)“ geändert). Nach `apt-get update --allow-releaseinfo-change` installierte `install-deps webkit` alle Pakete (Exit 0). Kein Paket fehlte, und WebKit startet.

## 2. Werkzeug (Parametrisierung)

- `tools/qa/e2e/lib.js`:
  - `CCAP_E2E_BROWSER` wählt `chromium`, `firefox` oder `webkit` (Standard `chromium`); andere Werte brechen ab.
  - `launch()` startet den gewählten Browser.
  - `phone()` liefert ein Telefon mit 390 × 844 px auf Basis der Playwright-Gerätebeschreibungen: Chromium `Pixel 7`, WebKit `iPhone 13` (beide mobil mit Touch). Firefox hat keine mobile Emulation (`isMobile`). Dort gelten Viewport, Touch, `deviceScaleFactor` 3 und ein Firefox-für-Android-UA mit der gestarteten Version.
  - `contextOptions()` liefert mit `CCAP_E2E_DEVICE=mobile` das Telefon, sonst die bisherigen Desktop-Werte.
  - Weitere Helfer: `where()` (Beschriftung), `shot()` (Bildschirmfotos nach `CCAP_E2E_SHOTS`, nur wenn gesetzt) und `submitLogin()`.
- Alle Skripte starten über `launch()`: `flow-author`, `live-smoke`, `exam-room`, `flow-group`, `flow-a11y`, `bench-report`, `settings-a11y`, `external-media`, `learning-area-a11y` und `learning-area-mobile`. `live-smoke`, `exam-room` und `settings-a11y` (Lernbereich) nutzen `phone()` statt fester Chromium-Optionen. Das Telefon ist jetzt 390 px statt bisher Pixel 7 mit 412 px.
- `live-flows.sh`:
  - Mit `CCAP_E2E_DEVICE=mobile` laufen Flow 1 (Autor im Block-Editor), Flow 3 (Administrator, Gruppenverwalter, Mitglied) und die A11Y-Seiten (Verwaltung und Lernbereich) auf dem Telefon.
  - Flow 2 läuft immer am Desktop und auf dem Telefon, der Prüfungsraum immer auf dem Telefon, der Benchmark am Desktop.
  - `CCAP_E2E_PREFIX` ändert das Tabellenpräfix, damit Läufe parallel möglich sind.
- `settings-a11y.sh`: `CCAP_E2E_DEVICE=mobile` prüft alle Verwaltungsseiten auf dem Telefon. Die Prüfungen auf 320/390 px ohne horizontales Scrollen bleiben zusätzlich bestehen.
- Neue Prüfungen:
  - `flow-a11y.js` prüft auch die Nicht-Lernbereichsseiten bei 390 px auf horizontales Scrollen (Zertifikat, Verifikation, Editor-Kästen, Gruppen; WCAG 1.4.10).
  - `focusWalk` misst nach zwei Frames, damit das Scrollen in den Sichtbereich abgeschlossen ist. Er endet, wenn der Fokus das Dokument verlässt: Firefox behält dann das letzte Element als `activeElement`, was vorher als „tab order goes backwards“ gezählt wurde.
  - Fehlerzustand: Die Prüfung wartet bis zu 2 s auf den Fokus der Zusammenfassung. `admin-settings.js` setzt ihn in einem Task nach `load`.

## 3. Befunde und Behebung

| # | Befund | Browser | Ursache | Behebung | Regressionstest |
|---|---|---|---|---|---|
| 1 | Kasten „Settings for this course“ im Block-Editor: Felder (Aussteller, Fußzeile, Signatur) liegen bei 390 px außerhalb des Bildschirms, Fokus außerhalb der Breite (WCAG 1.4.10/2.4.7) | alle | `fieldset` hat standardmäßig `min-inline-size: min-content`; `regular-text` ist 25em (400 px) breit, also wird der Fieldset 402 px breit | `AdminUi::EDITOR_CSS` (logische Eigenschaften) über `EditorScreens::editor_notices()` als Inline-Stil `ccap-editor-boxes` in den Meta-Boxen des Plugins | `AuthoringTest::test_save_error_survives_the_background_meta_box_page` (Stil eingereiht, Regeln enthalten); Browser: `settings-a11y` mobil „course editor: settings box“ |
| 2 | Verwaltung „Groups“: Tabellen „All groups“ (544 px) und „Members and invitations“ scrollen bei 390 px die ganze Seite. In der Chromium-Telefonemulation verschob das den Layout-Viewport, sodass „Create group“ nicht mehr anklickbar war (Flow 3 mobil) | alle (Klickfehler nur Chromium mobil) | breite `widefat`-Tabellen ohne eigenen Scrollbereich; Layoutregeln der Einstellungsseiten fehlten auf „Groups“ | beide Tabellen in `AdminUi::scroll_open()` (fokussierbarer, benannter Bereich, WCAG 1.4.10/2.1.1); `GroupsScreen` lädt `AdminUi::settings_style()` | `GroupCertificateAdminTest::test_GRP_008_009_010_018_owner_policy_members` (Scrollbereiche benannt); Browser: `flow-a11y` „A-10 groups“ bei 390 px |
| 3 | Seitenkästen (Kurs: „Publish revision“ mit der Prüfliste) auf dem Telefon nicht sichtbar | alle | Block-Editor: Seitenkästen liegen in der Einstellungs-Seitenleiste, die auf schmalen Bildschirmen geschlossen ist | kein Fehler (Kernverhalten; eine Person öffnet „Settings“). `flow-author.js` öffnet die Seitenleiste auf dem Telefon wie eine Person | Flow 1 mobil |
| 4 | WebKit verwirft gelegentlich ein Formular-Absenden: Das `submit`-Ereignis feuert, aber keine Anfrage verlässt den Browser. Etwa 1 von 30 Anmeldungen bzw. Speichervorgängen, auch mit Enter. Zuerst gesehen als Zeitüberschreitung bei Anmeldung und „Save changes“ | WebKit | kommt nur vor, wenn direkt nach `load` abgeschickt wird, während noch Unterressourcen laden (z. B. lädt die Kern-Anmeldeseite `zxcvbn` asynchron); mit JavaScript aus 0/40; nach Warten auf Netzwerkruhe 0 von 100 bzw. 0 von 50 | Testumgebung, kein Plugin-Fehler (eine Person schickt nicht Millisekunden nach dem Laden ab). `lib.js` wartet in WebKit nach jeder Navigation der Skripte auf Netzwerkruhe (höchstens 5 s) | ganze WebKit-Matrix grün (§4) |
| 5 | WebKit-Editor meldet „access control checks“ für `wp-compression-test` und `global-styles` | WebKit | Kernanfragen, die beim Seitenwechsel abgebrochen werden | keiner (Kern, wird von `flow-author.js` als „core, not failing“ geführt) | – |

axe-core 4.14.1 meldete in keiner Seite, keinem Browser und keinem Viewport einen Verstoß (Tags wcag2a/aa, wcag21a/aa, wcag22aa; jeder Schweregrad zählt als Fehlschlag). Es gab also keine ernsten oder kritischen A11Y-Verstöße.

## 4. Läufe und Ergebnisse

Abschließender Lauf: alle zwölf Läufe mit dem Stand dieses Commits, je drei parallel (Tabellenpräfixe `ccapf??_`/`ccaps??_`, eigene Ports). `live-flows.sh ccap_test_e` bzw. `settings-a11y.sh ccap_test_e` mit `AXE_PATH` gesetzt. Jeder Lauf beendet sich mit `e2e_check_log`; im Serverprotokoll stand keine PHP-Meldung.

**`live-flows.sh`** (Gerät für Flow 1/3/A11Y laut Spalte; Flow 2 immer Desktop und Telefon 390 px, Prüfungsraum Telefon, Benchmark Desktop):

| Browser | Gerät | Flow 1 Autor | Flow 2 Desktop | Flow 2 Telefon | Prüfungsraum | Flow 3 Gruppe | A11Y (11 Seiten, axe) | Benchmark |
|---|---|---|---|---|---|---|---|---|
| Chromium | Desktop | OK | OK | OK | OK | OK | 11/11 | OK |
| Chromium | Telefon 390 px | OK | OK | OK | OK | OK | 11/11 | OK |
| Firefox | Desktop | OK | OK | OK | OK | OK | 11/11 | OK |
| Firefox | Telefon 390 px | OK | OK | OK | OK | OK | 11/11 | OK |
| WebKit | Desktop | OK | OK | OK | OK | OK | 11/11 | OK |
| WebKit | Telefon 390 px | OK | OK | OK | OK | OK | 11/11 | OK |

A11Y-Seiten: L-04 My learning, L-05 Kursraum, L-06 Lektion (Text, Aufgabe, Prüfung), L-15 Zertifikat, L-16 Verifikation (Gast), A-04 Lektionseditor „Activity“, Kurseditor „Modules“ und „Publish revision“, A-10 Groups. Im Lernbereich wird zusätzlich geprüft: Sprunglink zuerst, sichtbarer Fokus an jeder Station und kein horizontales Scrollen bei 320 px.

**`settings-a11y.sh`** (40 Prüfpunkte: 10 Tabs, 10 Fehlerzustände, 8 Assistentenschritte und ein Fehlerzustand, Mailtexte mit Übersicht, Editor und Fehlerzustand, Sperren mit Übersicht und Sperrseite, Sicherung, Protokoll, Kurseditor-Kasten, Lernbereich mobil ×3):

| Browser | Desktop 1280 px | Telefon 390 px |
|---|---|---|
| Chromium | 40/40 | 40/40 |
| Firefox | 40/40 | 40/40 |
| WebKit | 40/40 | 40/40 |

**Verlauf (nicht geschönt):**
- Erste Läufe vor den Korrekturen:
  - Firefox Telefon `settings-a11y` 15/40: 24× „tab order goes backwards“ als Artefakt des Fokuslaufs, dazu Befund 1.
  - Chromium Telefon 39/40 (Befund 1), WebKit Desktop 39/40 (Fokus-Wartezeit).
  - Flow 1 Telefon in Firefox und WebKit (Befund 3), Flow 3 Telefon in Chromium (Befund 2).
  - Einzelne WebKit-Zeitüberschreitungen beim Absenden (Befund 4).
- Zweiter vollständiger Lauf: 10 von 12 grün. Einmal Firefox Telefon (Anmeldung im A11Y-Schritt, Zeitüberschreitung), einmal WebKit Desktop (Kern-Fehler „WebKit encountered an internal error“ bei `page.goto`). In der Einzelwiederholung war Firefox grün. WebKit scheiterte erneut an einem verworfenen Absenden; die Untersuchung führte zu Befund 4.
- Nach Befund 4 waren im dritten vollständigen Lauf alle zwölf Läufe grün.
- Die einmalige Firefox-Zeitüberschreitung unter paralleler Last wird als Beobachtung geführt; im dritten Lauf trat sie nicht auf.

Bildschirmfotos (219 Seiten, je Browser/Gerät, nicht im Repository): `/tmp/claude-0/-home-user/6bf04d71-1f31-5a07-b498-209f8ffb4aad/scratchpad/ui-f7/shots/` (`flows/`, `settings/`); Protokolle und JSON-Berichte: `…/scratchpad/ui-f7/runs/`.

## 5. Offen

- **Praxis-Benchmark mit Person (UX-090): offen.** Er braucht eine reale Person, die den Referenzkurs anlegt und lernt (Zeit, Fehler, Rückfragen). Die automatisierten Zeiten (`bench-report.js`) sind nur die Grundlinie und ersetzen ihn nicht. Er gehört zu den Autorentests mit Personen (R1-29).
- Screenreader-Durchläufe mit Fachperson (R1-29 W-11) gehören nicht zu UI-F7.
- Der Firefox „mobil“ ist eine Näherung: Viewport, Touch und UA, ohne mobile Layout-Engine. Ein echtes Android-Firefox und iOS-Safari (WebKit-Engine, nicht dasselbe Produkt) sind nicht geprüft.
- WebKit-Befund 4 betrifft nur die Testumgebung. Ob Safari gleich reagiert, ist nicht geprüft; für Personen ist es nicht beobachtbar.

## 6. Tests und Gates

PHP 8.5.12, MariaDB 10.11.14, Testdatenbank `ccap_test_e`:
- `phpcs -q`: 0 Befunde · `phpstan analyse`: keine Fehler · `textdomain-lint`: 0 Befunde (logische CSS-Eigenschaften)
- Unit: 141 Tests, 3083 Zusicherungen, grün
- Integration (einfach): 586 Tests, 21 486 Zusicherungen, 3 übersprungen, grün
- Neue bzw. erweiterte Zusicherungen: `AuthoringTest` (Stil der Meta-Boxen), `GroupCertificateAdminTest` (Scrollbereiche der Gruppentabellen)

## 7. Geänderte Dateien

- Code: `src/Admin/AdminUi.php` (`EDITOR_CSS`), `src/Admin/EditorScreens.php` (Stil der Meta-Boxen), `src/Admin/GroupsScreen.php` (Scrollbereiche, Layoutregeln).
- Tests: `tests/integration/AuthoringTest.php`, `tests/integration/GroupCertificateAdminTest.php`.
- Werkzeug: `tools/qa/e2e/lib.js`, `a11y-checks.js`, `flow-author.js`, `flow-group.js`, `flow-a11y.js`, `live-smoke.js`, `exam-room.js`, `bench-report.js`, `settings-a11y.js`, `external-media.js`, `learning-area-a11y.js`, `learning-area-mobile.js`, `live-flows.sh`, `settings-a11y.sh`.
- Keine neuen msgids, REST-Routen, Hooks oder Einstellungen.
