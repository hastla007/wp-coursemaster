# R1-19 · Mehrsprachigkeit und Accessibility – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-b`
**DoD (TODO R1-19):** Lernraum/Admin per Tastatur bedienbar, übersetzbare Strings, Locale/RTL/Screenreader- und Responsive-Tests grün.
**Quellen:** R0-15 (K-01…K-18, A11Y-001…112, I18N-R-01…34, I18N-001…015), R0-25 §3.0/§3.1/§3.2 (LOC-001…029, D-25-06/-07/-10, Ü-4), E-121, E-124, E-208. Clean Room (E-01): kein Code aus LearnBoating; Lint, DOM-Prüfer und Browser-Prüfung sind neu geschrieben.

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `languages/course-captain-de_DE.po`, `.mo` | Deutsche Übertragung (informelles „du“, 815 Einträge inkl. 23 Pluralformen, `Plural-Forms: nplurals=2`), vollständig; `.mo` mit `wp i18n make-mo` erzeugt. Liegt im Release-ZIP (E-208, schließt R1-39 A-2 für `de_DE`) |
| `src/Kernel/Locale.php` | **Varianten-Rückfall D-25-07** über `load_translation_file` und `lang_dir_for_domain` (nur Domain `course-captain`): `de_AT`/`de_CH`/`de_CH_informal`/`de_DE_formal` → `de_DE`, `pt_PT` ↔ `pt_BR`, `nl_BE` → `nl_NL`, `es_*` → `es_ES`, `fr_*` → `fr_FR`, sonst erste Datei gleicher Sprache, sonst Quelltexte `en_US`; auch für die mitgelieferte Datei, wenn der Plugin-Ordner nicht unter `WP_PLUGIN_DIR` liegt. `resolve()` (aufgelöste Locale), `ui_tag()`, `ui_attributes()` (`lang`/`dir` der aufgelösten UI-Locale), `content_attributes()` (Inhaltssprache abweichend → `lang`, ggf. `dir`, A11Y-032), `dir()` (LOC-003, auch Schrift-Subtags wie `ku-Arab`), `switch_to_user()` (Kette §3.1: Profilsprache → Site-Sprache, LOC-013), `in_learning_locale()` (E-124, Einstellung `general.use_profile_locale`, Standard an bei > 1 installierter Sprache), `percent()` (I18N-R-27, Muster `%s%%`) |
| `src/Learner/LearningArea.php` | Lernbereich in der Lernbereich-Locale (E-124/D-25-10, Profilsprache gewinnt auch gegen ein Übersetzungsplugin); `<html lang dir>` aus der aufgelösten Locale; Kurs-, Modul- und Lektionstitel mit Inhaltssprache; Prozent über `Locale::percent()`; CSS nur mit logischen Eigenschaften (RTL ohne zweite Datei, I18N-R-12), Sprunglink per `clip-path` statt `left:-999px` (kein waagerechtes Scrollen in RTL), `:focus-visible` für alle Bedienelemente, `prefers-reduced-motion`, `forced-colors`, `scroll-padding` gegen verdeckten Fokus; Formular-Posts (PRG) für Prüfungsstart und Prüfungsraum |
| `src/Learner/ExamRoom.php` | **Prüfungsraum ohne JS** `/<lernbereich>/pruefung/<id>` (R1-16 A-1): je Frage `fieldset` mit `legend` „Frage n von m“ + Hinweis, per `aria-labelledby` mit dem Fragetext verbunden (A11Y-022); Single/Wahr-Falsch als Radiogruppe, Multi als Checkboxen, Zahl mit Einheit im Label, Zuordnung als beschriftete `select`; Timer `role=timer` `aria-live=off` `dir=ltr`, Ansagen bei 10/5/1 min und Knopf „Verbleibende Zeit ansagen“ über **eine** `role=status`-Region, die auch „Antworten gespeichert“ meldet; Fehler als `role=alert` mit Katalogtext (K-05, I18N-R-08); Fragennavigation als benannte `nav` mit Status als Text (A11Y-007); Abgabe nur mit Bestätigung, die offene und markierte Fragen nennt (A11Y-074); Ergebnis mit `h2` bestanden/nicht bestanden und Text je Frage; Ablauf als `role=alert` (A11Y-073); Kursraum zeigt veröffentlichte Prüfungen mit Zeitlimit (`_n`) und Start-/Fortsetzen-Formular. Der Server bleibt maßgeblich (bestehende `ExamSessions`-Dienste, keine neue REST-Route) |
| `src/Rest/RestContract.php` | `_locale=user` → `message`/`detail`/`title` in der Profilsprache (LOC-015), Code unverändert; `user_message()` für serverseitige Seiten |
| `src/Notification/NotificationService.php`, `src/Ops/OpsScreen.php`, `src/Ops/OpsStatus.php`, `src/Publish/CourseValidator.php` | Mengen-Sätze mit `__()` → `_n()` + `number_format_i18n()` (I18N-R-04/-26): fehlgeschlagene Mails, zurückgehaltene Nachrichten, unzustellbare Ereignisse, wartende Zustellungen, Partner-Plugins, „nur n von m Fragen“ |
| `src/Admin/IdentityNotice.php` | `margin-right` → `margin-inline-end` |
| `tests/guards/textdomain-lint.php` | erweitert (I18N-001): Textargumente nur als ein Literal (I18N-R-02/-03), Platzhalter nur mit `translators:`-Kommentar (I18N-R-06), `wp_date()`/`date_i18n()` mit festem Anzeigeformat verboten (I18N-005, ISO erlaubt), physische `left`/`right`-CSS-Eigenschaften verboten (I18N-R-12) |
| `tests/support/A11yDom.php` | DOM-Prüfer (DOMDocument): Labels je Formularfeld, zugängliche Namen von Links/Knöpfen, positiver `tabindex`, `img` ohne `alt`, kaputte `aria-labelledby/-describedby/-controls`, doppelte IDs (ohne versteckte Nonce-Felder), Tabellen ohne `th`, `th` ohne `scope`, `fieldset` ohne `legend`; für Dokumente zusätzlich `lang`, genau ein `main`/`h1`/`title`, benannte `nav`, keine übersprungenen Überschriftenebenen |
| `tests/integration/A11yI18nTest.php` | 9 Tests (Gruppe `R1-19`) |
| `tests/unit/FreeGuardTest.php` | `test_i18n_static_scan` (Negativ-/Positivfälle der Lint-Erweiterung) |
| `tools/qa/e2e/learning-area-a11y.js` | Browser-Prüfung (Chromium/Playwright) der gerenderten Seiten: Sprunglink, Tab-Lauf, Fokus, Reflow, RTL, reduzierte Bewegung, Forced Colors, Radiogruppe per Pfeiltaste, Timer-Ansagen mit Fake-Clock, optional axe-core |
| `tools/release/check-zip.php` | Positivliste um `languages/course-captain-de_DE.mo` ergänzt |
| `docs/API-CHANGELOG.md` | `_locale=user` |

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler (1 bekannte Warnung `ImportTest.php:318`) · PHPStan L8 0 · Unit **107** · Integration **349** · Multisite **361** · Free-Guard 0 · Textdomain-Lint 0 (157 Dateien) · `make-pot` ohne Audit-Warnungen · `.po` deckt alle 815 Einträge der aktuellen Quelltexte ab (Platzhalter/`{{…}}`/HTML je Eintrag automatisch gegengeprüft) · Release: `build-zip.php --worktree` + `check-zip.php` **OK (168 Einträge)**, `de_DE.mo`/`.po` im ZIP.

**Browser-Prüfung** (`CCAP_DUMP_DIR=<dir> … --filter A11yI18nTest`, dann `AXE_PATH=…/axe.min.js node tools/qa/e2e/learning-area-a11y.js <dir>`), Chromium 1280×900, 390 und 320 px, Seiten „Mein Lernbereich“, Kursraum, Prüfungsraum, Kursraum in RTL: `ok: true`; Tab-Lauf 4/5/18/5 Stopps in DOM-Reihenfolge, jeder Stopp mit Fokusrahmen ≥ 2 px und nicht von der klebenden Timerleiste verdeckt; Sprunglink zuerst, sichtbar, setzt Fokus auf `main`; kein waagerechtes Scrollen bei 320/390 px (auch RTL); keine Animationen bei `reducedMotion`, Fokusrahmen in `forcedColors`; Pfeiltaste wechselt in der Radiogruppe; Ansageknopf → „Remaining time: 10:00“, Fake-Clock → „5 minutes remaining.“; **axe-core 4.10.2 (wcag2a/aa, 21a/aa, 22aa): 0 Verstöße** auf allen vier Seiten. axe-core wurde für den Lauf lokal aus der npm-Registry geladen (nicht im Repository; es gibt noch keine `package.json`). Die bestehende Prüfung `learning-area-mobile.js` (R1-16) bleibt grün.

## 3. Abdeckung

Legende: **erledigt** = in diesem Task umgesetzt und automatisch geprüft · **vorhanden** = von einem früheren Task umgesetzt und getestet · **teilweise** · **offen** (mit Grund) · **R1-29** = manuell mit Hilfstechnik/Nutzertest · **n/a** = in Free ohne betroffene Oberfläche.

### 3.1 A11Y

| ID | Status | Nachweis / Grund |
|---|---|---|
| A11Y-001 | erledigt (Lernbereich), teilweise (Admin) | Lernbereich inkl. Prüfungsstart, Antworten, Markieren, Abgabe ohne Maus und ohne JS: `test_A11Y_exam_flow_without_javascript`, Browser-Tab-Lauf. Admin: eigene Screens nur native Formularelemente mit Label (`test_A11Y_admin_screens_and_blocks`); echter Tastaturlauf im WP-Admin braucht eine laufende Site (R1-18-E2E) |
| A11Y-002 | erledigt | Browser: Tab-Reihenfolge = DOM-Reihenfolge, Sprunglink zuerst → `main`; kein positiver `tabindex` (DOM-Prüfer) |
| A11Y-003 | erledigt (Lernbereich) | `:focus-visible` 3 px; Browser: jeder Stopp `outline` ≥ 2 px, auch in `forced-colors`. Kontrast des Rahmens (#1d4ed8 auf Weiß ≈ 6,7:1) nicht automatisch gemessen |
| A11Y-004 | n/a / R1-29 | Lernbereich ohne Dialog/iframe; Block-Editor ist Core |
| A11Y-005 | erledigt | keine Einzeltasten-Kürzel vorhanden |
| A11Y-006 | erledigt (1280 px), teilweise | Browser prüft Überdeckung durch die klebende Timerleiste; `scroll-padding-block-start`; bei niedriger Höhe wird die Leiste statisch. 390/320 px nicht auf Überdeckung geprüft |
| A11Y-007 | teilweise | Status je Position als Text in einer benannten `nav` (Test). Roving-Tabindex mit Pfeiltasten (APG) offen: ohne JS ist eine Linkliste das robuste Muster; folgt mit einer JS-Lern-App |
| A11Y-008 | vorhanden | Curriculum mit Positionsfeld statt Ziehen (R1-05); Labels per DOM-Prüfer |
| A11Y-009 | teilweise | Abgabebestätigung per Checkbox (Tastatur, Test); A-15-Warnung „ungespeicherte Änderungen“ gibt es in Free noch nicht |
| A11Y-010 | erledigt | gesamter Lernbereich und Prüfungsraum ohne JS (Integrationstest) |
| A11Y-020 | erledigt | genau ein `main`, `header`, benannte `nav`, Sprunglink (DOM-Prüfer + axe `landmark-*`/`region` 0 Verstöße); NVDA/VO-Landmarkenliste → R1-29 |
| A11Y-021 | n/a | keine SPA; jede Seite hat eigenen `<title>` „Seite – Website“ und genau eine `h1` |
| A11Y-022 | erledigt | `fieldset`/`legend`, Name „Question 1 of 4 Choose one answer. <Fragetext>“, Radiogruppen (Test + Browser) |
| A11Y-023 | offen | Karteikarte/Freitext-Aufdecken haben noch keine Lernbereich-Oberfläche (Freitext, Reihenfolge, Lückentext im Prüfungsraum mit Hinweis, zählen als unbeantwortet) |
| A11Y-024 | offen | Lernmodus/Smarttrainer nur per REST, keine Oberfläche mit Feedback-Region |
| A11Y-025 | teilweise | „Antworten gespeichert“ in der einzigen polite-Region (Test); „Offline – wird nachgesendet“ braucht einen JS-Client |
| A11Y-026 | erledigt | `progress` mit Label + Prozenttext (I18N-R-27) |
| A11Y-027 | R1-29 | Zustände sind Text (Sperrgrund, Zugang); Vorlesen manuell |
| A11Y-028 | erledigt | alle eigenen Admin-Tabellen mit `th scope` (DOM-Prüfer); `aria-sort` n/a (keine sortierbaren Spalten) |
| A11Y-029 | n/a | keine Diagramme in Free |
| A11Y-030 | teilweise / R1-29 | Ergebnis als `h2` + Text; Ansage manuell |
| A11Y-031 | vorhanden | Validierungsübersicht `role=status` (Test prüft Vorhandensein); NVDA → R1-29 |
| A11Y-032 | erledigt | `<html lang dir>` = aufgelöste UI-Locale; Titel/Fragen mit Inhaltssprache (`test_I18N_007_rtl`, `test_LOC_012…`); axe `html-has-lang`/`valid-lang` 0 |
| A11Y-040 | erledigt (Lernbereich) | axe `color-contrast` 0 Verstöße; Admin nutzt WP-Core-Farben, Browserlauf im Admin mit R1-18 |
| A11Y-041 | offen | Nicht-Text-Kontrast (Rahmen #8c8f94) manuell mit Kontrastprüfer → R1-29 |
| A11Y-042 | n/a | keine Design-Tokens in Free (R0-27) |
| A11Y-043 | teilweise | Fokusrahmen in `forced-colors` geprüft; Antwortauswahl/Timer nur visuell → R1-29 |
| A11Y-044 | n/a | kein dunkles Schema |
| A11Y-050 | erledigt | 320 und 390 px ohne waagerechtes Scrollen, auch RTL (Browser) |
| A11Y-051, -054 | R1-29 | manuell |
| A11Y-052 | offen | Textabstands-Injektion nicht automatisiert |
| A11Y-053 | teilweise | Timerleiste wird bei Höhe < 32rem statisch (CSS); 320×256 nicht im Browser geprüft |
| A11Y-055 | n/a | keine Tooltips im eigenen UI |
| A11Y-060 | erledigt | `prefers-reduced-motion`; Browser `getAnimations()` leer |
| A11Y-061, -062 | erledigt | keine automatisch bewegten Inhalte/Blinken (Timer ist Prüfungsbestandteil, keine Animation) |
| A11Y-063, -090 | vorhanden / teilweise | Untertitel-URL und Consent nach Klick (`ActivityTest::test_GM_008_012_013_014_video_view`); eigener Player nicht vorhanden → R1-29 |
| A11Y-070 | vorhanden | Zeitausgleich im Snapshot (`EffectiveSettingsTest::test_INH_016_017_018_time_limit`); Anzeige „inkl. Zeitausgleich“ vor dem Start offen |
| A11Y-071 | erledigt (ohne Ausblenden) | `role=timer` `aria-live=off`, 10/5/1-min-Ansagen (`_n`), Ansageknopf; Browser mit Fake-Clock. „Sichtbare Restzeit ausblendbar“ offen |
| A11Y-072 | offen | Verlängern im `practice`-Modus braucht eine Server-Aktion (R1-08/R1-20) |
| A11Y-073 | erledigt | Ergebnis nach Ablauf mit `role=alert` „Die Zeit ist abgelaufen …“ (Test); Client lädt bei 0 neu |
| A11Y-074 | erledigt | Bestätigung nennt offene und markierte Fragen; ohne Häkchen `role=alert` (Test) |
| A11Y-075 | teilweise | abgelaufene Nonce → verständliche Meldung (`ccap_invalid_token`), Antworten des einen Posts gehen verloren; Puffer braucht JS-Client |
| A11Y-076 | offen | Inaktivitäts-Abgabe-Hinweis |
| A11Y-077 | erledigt | Statusmeldungen serverseitig, bleiben bis zur nächsten Aktion |
| A11Y-080 | offen | eigene Admin-Formulare melden Fehler als Hinweis, ohne `aria-invalid` am Feld |
| A11Y-081 | erledigt | Fehlertext aus dem Katalog, nie nur der Code (`RestContract::user_message`, Test) |
| A11Y-082 | teilweise | „Kurs nicht verfügbar“/fremder Versuch als `alert` mit Weg zurück (Kopfzeile); eigene Wartungsseite folgt |
| A11Y-083 | n/a | kein Setup-Assistent in Free bisher |
| A11Y-084 | vorhanden | Bestätigungsfelder in Gruppen/Systemstatus beschriftet (DOM-Prüfer) |
| A11Y-085 | R1-29 | |
| A11Y-091, -110, -111, -112 | offen | Alt-Text-/Untertitel-Regeln im Publish-Validator und Roundtrip noch nicht umgesetzt (R1-26/R1-15) |
| A11Y-092 | offen | Download-Linktexte mit Typ/Größe |
| A11Y-093 | vorhanden | Consent-Platzhalter nur im Modus „Klick zum Laden“ (ActivityTest) |
| A11Y-095 | vorhanden | Mail-HTML mit `lang`/`dir`, Klartextversion (R1-21 NTF-019/-022); Empfängersprache `test_I18N_013…` |
| A11Y-096 | vorhanden | Druckansicht Zertifikat (R1-35) |
| A11Y-100 | teilweise | axe 0 Verstöße lokal auf 4 Seiten; CI-Gate offen, weil axe-core noch keine Repo-Abhängigkeit ist (keine `package.json`) und Admin-Screens eine laufende Site brauchen |
| A11Y-101 | teilweise | Tastaturlauf nur Chromium; Firefox/WebKit offen (nicht installiert) |
| A11Y-102…104 | R1-29 | **NVDA (Firefox/Chrome) und VoiceOver (macOS/iOS) mit echter Hilfstechnik sind manuell und für den Auftraggeber offen** |

### 3.2 LOC

| ID | Status | Nachweis / Grund |
|---|---|---|
| LOC-001, -002 | vorhanden | R1-04 (`CourseModelTest`) |
| LOC-003 | erledigt | `test_LOC_003_direction` |
| LOC-004 | erledigt | `content_attributes('en')` → `lang="en"`; Fragecontainer im Prüfungsraum mit Kurssprache |
| LOC-005 | erledigt | arabischer Kurs: Titel/Module `lang="ar" dir="rtl"`, UI bleibt LTR; Timer `dir="ltr"` (`test_I18N_007_rtl`) |
| LOC-006…011 | vorhanden / andere Tasks | R1-26, R1-04, R1-05, R1-10, R1-08 |
| LOC-012 | erledigt | Profil `de_DE` + Einstellung an → deutsch, `lang="de-DE"`; aus → englisch; Profil `de_AT` → `lang="de-DE"` (`test_LOC_012…`, `test_de_DE_translation_and_variant_fallback`) |
| LOC-013 | erledigt | Profil `fr_FR` ohne Datei → nächstes Glied Site-Sprache; `resolve('fr_FR') = en_US` |
| LOC-014 | offen | Polylang nicht offline verfügbar (R1-30); Verhalten „Profilsprache gewinnt“ ist umgesetzt, weil der Wechsel unabhängig von `determine_locale()` erfolgt |
| LOC-015 | erledigt | `test_LOC_015_rest_error_locale` |
| LOC-016…019 | vorhanden + erledigt | R1-21 (`NotificationTest::test_NTF_019_020_locales`); Werkstext in Empfängersprache mit echter `.mo`: `test_I18N_013_mail_in_recipient_locale` |
| LOC-020…022 | vorhanden | R1-35 |
| LOC-023…026 | andere Tasks | R1-37, R1-15 |
| LOC-027 | offen | Systemstatus-Hinweis für Übersetzungsplugins (Ü-4) mit R1-38/R1-30 |
| LOC-028 | offen | R1-30 |
| LOC-029 | n/a | keine JS-Bundles mit `wp.i18n`; Timertexte werden serverseitig übersetzt und als `data-*` übergeben, daher keine JSON-Übersetzungen (`make-json` nicht nötig) |

### 3.3 I18N

| ID | Status | Nachweis / Grund |
|---|---|---|
| I18N-001 | erledigt | Lint erweitert, CI-Schritt besteht |
| I18N-002 | erledigt | phpcs `WordPress.WP.I18n` 0 |
| I18N-003 | n/a | keine JS-Quellen mit Übersetzungsaufrufen |
| I18N-004 | übergeben | POT wird vom Integrator neu erzeugt; `make-pot` ohne Audit-Warnungen |
| I18N-005 | erledigt | Lint-Regel feste Datumsformate |
| I18N-006 | offen | Pseudo-Locale-Generator |
| I18N-007 | erledigt | RTL serverseitig + Browser + axe (RTL-Seite) |
| I18N-008 | offen | keine `pl_PL`/`ru_RU`-Testdatei; alle Mengen über `_n()` (Lint/Review) |
| I18N-009 | andere Tasks | Zeitzonen/Drip (R1-10) |
| I18N-010 | teilweise | `de_DE`: `1.234 %` (Test); `en_US`/`fr_FR` nicht getestet |
| I18N-011 | erledigt | wie LOC-012 |
| I18N-012 | n/a | wie LOC-029 |
| I18N-013 | erledigt | Mail an `de_DE`-Konto deutsch, `en_US` englisch, Locale danach zurückgesetzt |
| I18N-014 | erledigt | Übersetzungen erst ab `init`; die WP-Testsuite schlägt bei `_doing_it_wrong` (auch `_load_textdomain_just_in_time`) fehl, alle Suiten grün |
| I18N-015 | R1-29 | Übersetzer-Review `de_DE` und `de_DE_formal` (Sie-Form noch nicht erstellt; Rückfall `de_DE_formal` → `de_DE`) |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | RTL-Stylesheet | Statt `-rtl.css` per `wp_style_add_data( 'rtl', 'replace' )` nur logische CSS-Eigenschaften (R0-15 I18N-R-12 bevorzugt das); es gibt kein registriertes Plugin-Stylesheet, die Stile sind Inline-Teil der eigenen Seiten. Der Lint verbietet physische `left`/`right` |
| A-2 | Locale-Wechsel in Tests | Die Testinstallation hat keine Core-Sprachpakete; der Test trägt die Locales per Reflection in `WP_Locale_Switcher` ein. In Produktion wechselt WordPress nur zu installierten Sprachen |
| A-3 | Prüfungsraum-Typen | Ohne JS beantwortbar: Single, Wahr/Falsch, Multi, Zahl, Zuordnung. Freitext (Aufdecken), Reihenfolge, Lückentext, Karteikarte mit Hinweis „in dieser Ansicht noch nicht“ |
| A-4 | `de_DE_formal` | nicht erstellt; Rückfall auf `de_DE` (D-25-07) |
| A-5 | Mengen in „Label: n“-Form | übrige `%d`-Platzhalter in Zählern („Betroffene Einträge: %d“, Seitenzahlen) bleiben; Satzformen mit Mengen wurden auf `_n()` umgestellt |

## 5. Übergaben

R1-29 (NVDA/VoiceOver, Kontrast Nicht-Text, Zoom/Ausrichtung, Übersetzer-Review), R1-18 (Admin-Tastatur-/axe-Lauf auf laufender Site), R1-30 (Polylang, LOC-014/-028), R1-26 (A11Y-110/-111), R1-38 (LOC-027), R0-35/R1-39 (axe-core als feste Abhängigkeit im CI, `de_DE_formal`). Review durch zweite Person vor Merge (AG-05).
