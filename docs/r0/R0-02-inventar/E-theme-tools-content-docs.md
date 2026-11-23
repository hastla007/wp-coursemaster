# R0-02/R0-03 · Inventar Teil E – Theme, Werkzeuge, Schemas, Staging, Content-Snapshot, Doku, Testinfrastruktur, CI

**Status:** Inventur (R0-02) und Klassifikation (R0-03) für Teil E; **keine** Freigabe, kein Implementierungsstart · **Stand:** 16.11.2026
**Bezug:** TODO R0-02, R0-03 (Vorarbeit für R0-04, R0-05, R0-10, R0-13, R0-15–R0-18, R0-20, R0-33, R0-35, R0-37, R0-38), Konzept Kap. 26, 39, 47, 48; LB-AUDIT-003/-004
**Quelle:** `hastla007/learnboating`, Commit `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (wie R0-01). Referenz nur gelesen, nicht verändert.

Klassifikation gemäß R0-03: `reuse` (Verhalten/Muster praktisch unverändert neutral übernehmbar, Code nur bei geklärten Rechten, siehe Kap. 6), `abstract` (generischer Kern unter fachspezifischer Hülle; neu und neutral benannt), `replace` (Bedarf besteht, Course Captain löst ihn anders, z. B. WordPress-Bordmittel oder eigener Vertrag), `exclude_domain_specific` (boots-, funk-, navigations-, amtsprüfungs-, betreiber- oder rechtsraumspezifisch; entfällt nach Konzept 26.1/26.2 und 39.2). Kein Eintrag ist `unknown`.

„Code übernehmen“ bedeutet in diesem Dokument nie eine Kopie vor der Lizenzentscheidung E-01 (R0-01) bzw. D-E-01 unten. Bis dahin gilt `reuse` nur für Verhalten, Prüfregeln und Testfälle.

---

## 1. Bereich und Abdeckung

Gezählt mit `find <pfad> -type f | wc -l` (Dateien), Textzeilen aller Nicht-Binärdateien (`grep -Il .` → `cat | wc -l`) und PHP-Zeilen separat. Bilder, Schriften und XLSX zählen als Dateien ohne Zeilen.

### 1.1 Gesamtübersicht

| Pfad | Dateien | davon Text | Textzeilen | PHP-Zeilen | Abdeckung in diesem Dokument |
|---|---:|---:|---:|---:|---|
| `themes/kurswasser/` | 216 | 171 | 26 953 | 10 106 | vollständig dateiweise bzw. je Ordner (1.2) |
| `tools/` (alle Unterordner) | 42 | 42 | 9 303 | 4 441 | vollständig dateiweise (1.3) |
| `schema/` | 18 | 18 | 928 | 0 | vollständig dateiweise (1.4, Kap. 3) |
| `staging/` | 8 | 8 | 483 | 13 | vollständig dateiweise (1.5) |
| `content-source/boatingschool/` | 630 | 393 | 47 152 | 21 673 | Ordnerzusammenfassung (1.6), strukturell |
| `docs/` (inkl. `mobile-api/`) | 37 | 37 | 12 310 | 325 | vollständig, je Datei eine Zeile (Kap. 5) |
| `README.md`, `konzept-wp-lernplugin.md`, `CREDITS.md`, `.gitignore` | 4 | 4 | 176 + 598 + 55 + 8 | 0 | vollständig |
| `bootspruefung-learning/tests/` | 41 | 41 | 14 536 | 13 600 | Infrastruktur dateiweise (1.7); fachliche Testinhalte → Teile A–D |
| `bootspruefung-learning/{phpstan.neon.dist, phpstan-baseline.neon, phpcs.xml.dist, phpcs-baseline.xml, composer.json}` | 5 | 5 | – | – | nur QA-Konfiguration (Zeilen der Baselines: 691 bzw. 189) |
| `.github/workflows/` | 2 | 2 | 746 | 0 | vollständig je Job (1.8, Kap. 4) |

Nicht in Teil E: `bootspruefung-learning/src`, `assets`, `templates`, `languages`, `dropins`, `uninstall.php`, `schema/` des Plugins (Kopie, siehe INV-E-019), die beiden Connector-Ordner (Teile A–D laut R0-01-Modulkarte).

### 1.2 `themes/kurswasser/` (216 Dateien)

| Unterpfad | Dateien | Zeilen | Inhalt |
|---|---:|---:|---|
| Stamm: `style.css`, `theme.json`, `functions.php`, `README.md`, `CHANGELOG.md`, `screenshot.jpg` | 6 | 16 / 464 / 452 / 329 / 57 / – | Kopf (GPL-2.0-or-later, Version 2.1.0), Design-Tokens, Bootstrap, Doku |
| `inc/` | 11 | 6 408 | `consent`, `content-pages`, `content-yoast`, `courses`, `learning`, `legal`, `offers`, `operator`, `seo`, `setup`, `setup-shop` |
| `patterns/` | 11 | 275 | Block-Muster (hero, usp, courses, features, steps, popular, groups, guarantee, guide-tiles, faq, cta) |
| `templates/` | 18 | 187 | Block-Vorlagen (front-page, course, landing, page-*, Shop-Vorlagen, 404 …) |
| `parts/` | 3 | 61 | header, header-checkout, footer |
| `legal/` | 5 | 1 207 | Rechtstext-Vorlagen (agb, datenschutz, impressum, kontakt, widerruf) |
| `assets/` | 69 | 1 433 (Text) | css 2, fonts 3 (Manrope + OFL), icons 19 SVG, images 3 (Hero) + `images/courses/` 39 (KI-Fotos + `IMAGE-RIGHTS.md`), js 3 |
| `content/` | 77 | 8 370 | `pages.php` (Manifest, 35 Seiten), `pages/` 36 (35 HTML + `.gitkeep`), `src/` 40 (35 Markdown + 5 `_moderation/`) |
| `tests/` | 7 | 1 810 | content-pages, setup-yoast, setup-shop, legal, learning-compat (PHP), demo-setup.php, screenshots.mjs |
| `tools/` | 8 | 2 866 | `content_build.py` (2 124), `test_content_build.py` (488), 6 Fixtures |
| `languages/` | 1 | 3 018 | `kurswasser.pot` |

### 1.3 `tools/` (42 Dateien, 9 303 Zeilen)

| Unterpfad | Dateien | Zeilen | Inhalt |
|---|---:|---:|---|
| `content-build/` | 26 | 6 469 | `bin/` 3 (build-package, navigation-values, video-lessons), `src/` 14 (PackageBuilder 1 217, PackageWriter 189, XlsxReader 198, NavigationAnswerValues 174, SourceLiterals 133, SourceInfo 55, Csv 44, Report 37, Text 37, Json 20, `Concerns/` 4 = 892), `tests/PackageTest.php` 481, `golden-master/compare.php` 537, `data/` 2, `composer.json/.lock`, `phpunit.xml.dist`, `README.md` 88, `.phpunit.result.cache` (0 Byte) |
| `release/` | 2 | 297 | `build-zips.sh` 202, `upgrade-check.php` 95 |
| `i18n/` | 2 | 116 | `check-domains.php` 95, `make-pot.sh` 21 |
| `qa/` | 1 | 35 | `check-abspath.php` |
| `pdf-build/` | 1 | 812 | `build-pdfs.mjs` |
| `product-images/` | 1 | 185 | `generate.py` |
| `course-images/` | 2 | 127 | `README.md`, `make_placeholders.py` |
| `maps/` | 1 | 74 | `make_de_states.py` |
| `device-sim/` | 4 | 1 054 | Tailwind-Build (`package.json`, Lock, Config, `input.css`) |
| Stamm | 2 | 134 | `sync-source.sh` 114, `sync-device-sim.sh` 20 |

### 1.4 `schema/` (18 Dateien)

`README.md` (52) und `v1/` mit 17 JSON-Schemas (Draft 2020-12): common 17, manifest 98, courses 35, modules 33, questions 95, media 29, exam_rules 59, exam_papers 33, video_modules 65, praxis_tasks 31, navigation_tasks 86, radio_texts 32, radio_vocabulary 24, radio_practice 58, device_simulation 54, exam_committees 32, exam_guides 95. `diff -rq schema bootspruefung-learning/schema` meldet nur `README.md` als Unterschied: Das Plugin enthält eine identische Kopie von `v1/`.

### 1.5 `staging/` (8 Dateien)

`README.md` 167, `docker-compose.yml` 131 (Dienste db, wordpress, setup, cron, mailpit, caddy), `setup.sh` 122, `.env.example` 35, `Caddyfile` 7, `php.ini` 6, `mu-plugins/staging-mail.php` 13, `.gitignore` 2.

### 1.6 `content-source/boatingschool/` (630 Dateien, Ordnerzusammenfassung)

Unveränderter Snapshot von `hastla007/boatingschool@136a243af9268aeaa8d0184347820617ec95f76b` (`SOURCE.md`; 628 Dateien in `SHA256SUMS`, dazu `SOURCE.md` und `SHA256SUMS` selbst).

| Ordner | Dateien | Zeilen | Inhalt |
|---|---:|---:|---|
| Stamm | 3 | – | `README.md` (311), `SOURCE.md`, `SHA256SUMS` |
| `app/Models/` (+ `Concerns/`) | 55 + 3 | – | Eloquent-Modelle (Liste in INV-E-150 bis -156) |
| `app/Support/` | 7 | – | ComboExamGroups, ComboVideoGroups, FunkPraxis, FunkTraining, Geraetesimulation, NavigationAnswer, VideoCourseLessons |
| `app/Console/Commands/` | 2 | – | ImportContentQuestions, ImportContentFeedback |
| `app/` gesamt | 67 | 5 786 | – |
| `database/migrations/` | 143 | 8 416 | 69 `Schema::create`-Tabellen plus Härtungs-/Korrekturmigrationen |
| `database/seeders/` (+ `Concerns/`) | 43 + 1 | 4 791 | Kurs-, Modul-, Prüfungsbogen-, Video-, Funk-, Praxis-, Ausschuss-Seeder; `SeedsLessonQuizOnce` |
| `database/data/` | 321 | 12 897 (Text) | 32 Datendateien (CSV, PHP-Arrays, `zentrale_fragenbasis.xlsx`), Bilder: `elwis_bilder/` 153 (9,5 MB), `praxistrainer_bilder/` 68, `praxistrainer_bilder_svg/` 68 |
| `resources/js/` | 37 | 14 268 | `funkgeraet.js` und `funkgeraet/` (app, channels, devices 11, ui 9, tests 12 …) |
| `public/images/courses/` | 15 | – | Kursbilder (WebP) |

### 1.7 Testinfrastruktur `bootspruefung-learning/tests/` (41 Dateien)

| Pfad | Dateien | Zeilen | In Teil E inventarisiert |
|---|---:|---:|---|
| `integration/support/` | 3 | 100 | `guard.php`, `standalone.php`, `FakeOrders.php` |
| `integration/*-setup.php`, `uninstall-phase.php` | 3 | 263 | `woocommerce-setup.php`, `multisite-setup.php`, `uninstall-phase.php` |
| `integration/smoke-test.php` | 1 | 145 | Lifecycle-Rauchtest (Infrastruktur-Grundlage aller Läufe) |
| `integration/*-test.php` (übrige 18) | 18 | 12 555 | **nur Runner-Muster**; Inhalte → Teile A–D |
| `e2e/setup.php`, `router.php`, `*-setup.php` (4) | 6 | 343 | vollständig |
| `e2e/*.mjs` (7 Abläufe) | 7 | 936 | **nur Runner-Muster**; Inhalte → Teile A–D |
| `phpstan/` | 3 | 194 | `bootstrap.php`, `external-stubs.php`, `wp-cli-stubs.php` |

26 Testskripte (Plugin-, Theme- und Release-Tests) nutzen dasselbe Runner-Muster `$check = static function (bool $ok, string $label)` mit Zählung der Fehler und Exit-Code, ohne PHPUnit (`grep -l '$check = static function'`).

### 1.8 CI `.github/workflows/` (2 Dateien)

`ci.yml` (660 Zeilen; Jobs `converter`, `affiliate-connector`, `golden-master`, `plugin` (Matrix), `voucher-captain`, `multisite`, `release-zip`, `upgrade`, `static-analysis`, `staging`) und `release.yml` (86 Zeilen; Job `zips`).

---

## 2. Funktionstabelle

Spalten wie beauftragt. „Task-ID“ nennt nur IDs, die es in `TODO-WETTBEWERBSANALYSE.md` gibt; „–“ heißt: keine passende Task (dann ist der Eintrag `exclude` oder in der Begründung erklärt). „Testbezug“ nennt den vorhandenen Quelltest bzw. „keiner“.

### 2.1 Content-Paketformat (`schema/`)

| ID | Quellpfad | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Testbezug |
|---|---|---|---|---|---|---|---|---|---|
| INV-E-001 | `schema/README.md` | Doku | Paketaufbau, Regeln für stabile IDs, Hash, Versionen, Status, Rechte, Manifest | abstract | Regelwerk ist neutral; Beispiele, ID-Präfixe und Entitätsliste sind fachlich | Spezifikation „Course-Content-Package v1“ | R1-Free | R0-10, R1-15, LB-09, LB-13 | `tools/content-build/tests/PackageTest.php` |
| INV-E-002 | `schema/v1/manifest.schema.json` | Daten | Manifest: `package_id`, `schema_version`, `converter_version`, `source` (Repo/Branch/Commit), `content_sha256`, `languages`, `catalogs`, `counts`, `files[]` mit SHA-256/MIME/Größe, `warnings`, `skipped`, `not_converted` | abstract | Provenienz, Integrität und Berichtsfelder sind neutral; `package_id`-Muster `^boatingschool-…`, `catalogs` und `$id` `schema.learnboating.invalid` sind quellgebunden | Manifest mit Herkunft, Prüfsummen, Validierungsreport | R1-Free | R0-10, R0-38, R1-15, R1-41, LB-09 | `testManifestListsEveryFileWithChecksum` |
| INV-E-003 | `schema/v1/common.schema.json` | Daten | Gemeinsame Typen: `hash` (`sha256:`+64 hex), `editorialStatus` (draft/review/approved/published/deprecated), `rightsStatus` (unknown/review/licensed/public/restricted), Kurs-/Modulcode-Muster, `sourceFile` | abstract | Status- und Rechte-Enums sind neutral; `sourceFile` `^(database\|app\|resources\|public)/` ist Laravel-Annahme | gemeinsame Typen: Revisionshash, Redaktions- und Rechtestatus | R1-Free | R0-10, R0-22, LB-09 | `testFileMatchesSchema` |
| INV-E-004 | `schema/v1/courses.schema.json` | Daten | Kurs mit `code`, `name`, `course_type` (full/combo/addon), `language`, `status`, Modulreihenfolge mit `required` | abstract | Kurs→Modul-Zuordnung neutral; `combo`/`addon` bilden Führerschein-Kombis/Ergänzungen ab | Kurs mit geordneten Pflicht-/Wahlmodulen; Bundle/Aufbaukurs als generische Beziehung | R1-Free | R1-04, PAR-01 | `testReferencesResolve` |
| INV-E-005 | `schema/v1/modules.schema.json` | Daten | Modul mit Fragenzuordnung (`question_source_id`, `sort_order`, `required`) | reuse | Strukturell fachneutral | Modul/Themenblock mit Fragenpool | R1-Free | R1-04, R1-06 | `testReferencesResolve` |
| INV-E-006 | `schema/v1/questions.schema.json` | Daten | Frage + aktuelle Revision: Typ (`single_choice`, `multi_choice`, `open_text` mit `MODEL`, `chart_task`), Text, Thema/Unterthema, `competency`, `smart_category`, Feedback richtig/falsch, Quelle, Rechtefelder, Gültigkeit, Antworten, Medien | abstract | Single/Multi/Open-Text, Feedback, Kompetenz, Rechte und Gültigkeit sind neutral; `official_number`, `content_role`, `source.catalog/page`, `chart_task` sind amtlich/nautisch | Frage-Revision mit Typ-Provider; `chart_task` entfällt | R1-Free | R1-06, R0-08, LB-10, PAR-03 | `testChartTasksAreNotPublishedAndNotInPapers`, `testModelSolutionsPassTheirOwnChecks` |
| INV-E-007 | `schema/v1/media.schema.json` | Daten | Medium mit Pfad (Muster verbietet `..`), Typ (image/audio/video/document), Alt-Text, Bildunterschrift, Quelle/URL, Rechteinhaber, Lizenzreferenz, Rechtestatus, MIME, Größe, SHA-256 | abstract | Rechte- und Barrierefreiheitsfelder plus Traversal-Schutz sind neutral; ID-Präfixe `elwis/`, `praxistrainer/` fachlich | Medienobjekt mit Lizenz/Provenienz, Alt-Text, Prüfsumme | R1-Free | R0-10, R0-22, R1-07, R1-14, LB-04 | `testManifestListsEveryFileWithChecksum` |
| INV-E-008 | `schema/v1/exam_rules.schema.json` | Daten | Prüfungsregelwerk: Version, Gültigkeit, `time_limit_seconds`, `passing_rule` (`min_correct_ratio`, Teile mit `min_correct` von `of`), `verified`, Blueprints (Modul, Fragenanzahl) | abstract | Zeitlimit, Bestehensgrenzen je Teil und Blueprint sind generisch; konkrete amtliche Werte entfallen | Prüfungs-Blueprint mit Teilgrenzen und Version | R1-Free | R0-08, R1-08, R1-09, LB-14, PAR-04 | `testCombosAreVerifiedBecauseTheyMatchTheirSingleRules`, `testNoExamCanBePassedByTickingEveryOption` |
| INV-E-009 | `schema/v1/exam_papers.schema.json` | Daten | Feste Prüfungsbögen (`<Kurs>#<Nr>`, Positionen, `copied_from`) | abstract | Feste Fragensätze sind generisch („feste Prüfungsform“), amtliche Bögen sind nur Daten | feste Prüfungsform neben Zufalls-Blueprint | R1-Free | R1-08, LB-14 | `testReferencesResolve` |
| INV-E-010 | `schema/v1/video_modules.schema.json` | Daten | Videokapitel → Lektionen (`video_url` nur https, `provider` url/youtube/vimeo/media, Dauer, Platzhalter-Flag) → Schritte (Bild) → Quizfragen | abstract | Lektion mit Medium, Schrittgalerie und Quiz ist neutral; `group_label`, Kurscodes fachlich | Lektion/Activity „Video mit Schritten und Quiz“ | R1-Free | R1-07, PAR-02 | `testVideoUrlsFile` |
| INV-E-011 | `schema/v1/praxis_tasks.schema.json` | Daten | Praxistrainer-Karten: Frage, Antwort, Erklärung, Bild, Kategorie, `exam_reference`, Rechte | abstract | Frage/Antwort/Erklärung-Karte ist eine generische Karteikarte; Kategorien und `exam_reference` sind amtlich | Karteikarte (Vorder-/Rückseite, Erklärung, Medium) | R1-Free | R1-11, PAR-05 | `testAddonCoursesGetTheRegionalCardsOfTheirTargetLicence` (fachlich) |
| INV-E-012 | `schema/v1/navigation_tasks.schema.json` | Daten | Navigationsaufgaben mit Teilaufgaben und ab 1.4 `answer_values` (Größe, Einheit, Toleranz) | exclude_domain_specific | Kartenaufgaben und Toleranzen für Kurs/Peilung/Distanz sind nautisch (Konzept 26.2 „Kartenaufgaben entfällt“) | – (ggf. später generischer Zahlenfragetyp, eigene Freigabe) | exclude | – | `testNavigationAnswerValuesFollowOfficialResults` |
| INV-E-013 | `schema/v1/radio_texts.schema.json` | Daten | SRC-Übersetzungstexte, UBI-Aufnahme/-Abgabe | exclude_domain_specific | Funkausbildung | – | exclude | – | `testFileMatchesSchema` |
| INV-E-014 | `schema/v1/radio_vocabulary.schema.json` | Daten | Funkvokabeln, Buchstabiertafel | exclude_domain_specific | Funkausbildung; Vokabelkarten neutral über INV-E-011 abbildbar | – | exclude | – | `testFileMatchesSchema` |
| INV-E-015 | `schema/v1/radio_practice.schema.json` | Daten | Funkpraxis-Szenarien mit Musterlösung und Prüfpunkten | exclude_domain_specific | Spezialtrainer (Konzept 26.2 „Dialog-/Kommunikationstrainer entfällt“) | – | exclude | – | `testFileMatchesSchema` |
| INV-E-016 | `schema/v1/device_simulation.schema.json` | Daten | Geräte, Übungsschiff, Lektionen, Prüfungsreihen der Gerätesimulation | exclude_domain_specific | Gerätesimulation (Konzept 26.2) | – | exclude | – | `testFileMatchesSchema` |
| INV-E-017 | `schema/v1/exam_committees.schema.json` | Daten | Prüfungsausschüsse (DMYV-Liste) | exclude_domain_specific | Amtliche Prüfungsstellen (Konzept 39.2) | – | exclude | – | `testFileMatchesSchema` |
| INV-E-018 | `schema/v1/exam_guides.schema.json` | Daten | Anleitungen zur Prüfungsanmeldung (Gebühr, Vorlauf, Mindestalter, Unterlagen …) | exclude_domain_specific | Amtliche Prüfungsanmeldung | – | exclude | – | `testExamGuidesCoverEveryCourse` |
| INV-E-019 | `bootspruefung-learning/schema/v1` + CI-Schritt „Plugin-Schemata identisch mit schema/“ (`diff -r schema/v1 bootspruefung-learning/schema/v1`) | Build | Eine Schemaquelle, Plugin-Kopie per CI auf Gleichheit geprüft | reuse | Neutrales QA-Muster gegen Schemadrift | CI-Check „Schemas im Plugin = Spezifikation“ | R1-Free | R0-18, R1-15 | CI `plugin` |
| INV-E-020 | `schema/README.md` (Regel „Versionen … fehlende Dateien eines 1.0-Pakets gelten als leer“) | Import | Abwärtskompatible Minor-Versionen des Paketformats | reuse | Neutrale Kompatibilitätsregel | Versionsregel Paketformat (Minor additiv, fehlende Entitäten = leer) | R1-Free | R0-10, R0-19 | keiner (nur Doku; Plugin-Seite → Teil C) |

### 2.2 Content-Konverter (`tools/content-build/`)

| ID | Quellpfad | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Testbezug |
|---|---|---|---|---|---|---|---|---|---|
| INV-E-021 | `tools/content-build/bin/build-package.php` | Build | CLI `--out/--previous/--no-zip/--source`; prüft Snapshot gegen `SHA256SUMS`, baut in `.package-tmp` und benennt erst danach um | abstract | Atomarer Build und Integritätsprüfung neutral; Quelle immer `content-source/boatingschool` | Paket-CLI/SDK (Validieren, Bauen, Vergleichen) als Entwicklerwerkzeug | R2 | R0-10, R1-15, R2-03 | `PackageTest.php` |
| INV-E-022 | `tools/content-build/src/PackageBuilder.php` | Import | Nachbau von `php artisan db:seed`: feste `MODULES`/`COURSES` (SBF, SRC, UBI, SKN, FKN), XLSX-Modulmap, Fragenpool, Feedback, Bögen, Regelwerke, Bilder, Navigation, Praxis, Textkorrekturen | exclude_domain_specific | Durchgehend fach- und quellgebunden (Laravel-Seeder, amtliche Kataloge) | – | exclude | – | `PackageTest.php` (14 fachliche Fälle) |
| INV-E-023 | `PackageBuilder::comboMatchesSingles`, `COMBO_VERIFIED_FROM`, `ADDON_AS_FULL` | Import | Kombi-Regelwerk gilt als verifiziert, wenn es teilweise aus verifizierten Einzelregelwerken besteht | exclude_domain_specific | Amtliche Kombiprüfungen; generische Prüfungsfreigabe läuft über R0-22 | – | exclude | – | `testCombosAreVerified…`, `testComboCheckRejectsDeviatingParts` |
| INV-E-024 | `tools/content-build/src/PackageWriter.php` | Build | Schreibt `manifest.json`, `data/<entität>.json`, Medien; Zähler, Datei-Prüfsummen, ZIP; `compareWithPrevious` → Warnung `source_removed` | abstract | Export/Paketierung neutral; `ENTITY_FILES` enthält Fachentitäten | Paket-Writer für Export und Vorlagen | R1-Free | R1-15, R2-03, PAR-09 | `testBuildIsDeterministicAndDetectsNoRemovals` |
| INV-E-025 | `tools/content-build/src/Report.php` | Import | Sammelt `warn(code, entity, source_id, message)` und `skip(entity, file, line, source_id, reason)` | reuse | Neutrales Validierungsreport-Modell (Zeile/Objekt, Grund) | Validierungsreport für Dry-run | R1-Free | R0-38, R1-41 | indirekt über Manifest-Tests |
| INV-E-026 | `tools/content-build/src/Json.php` | Build | Feste JSON-Kodierung (Grundlage der Hashes) | reuse | Deterministische Serialisierung ist Voraussetzung für Hash-Vergleich | kanonische JSON-Serialisierung | R1-Free | R0-10, R1-15 | `testBuildIsDeterministic…` |
| INV-E-027 | `tools/content-build/src/Csv.php` | Import | CSV lesen (Standard-Trenner `;`) | abstract | Neutral, aber ohne Encoding-/Injection-Regeln | CSV-Import/-Export mit Formel-Injection-Schutz | R1-Free | R1-15, R1-41 | keiner direkt |
| INV-E-028 | `tools/content-build/src/XlsxReader.php` | Import | XLSX ohne Bibliothek lesen (`ZipArchive` + `SimpleXML`, Shared Strings, Rich Text, Zahlen) | abstract | Neutraler, abhängigkeitsfreier Leser; im Plugin bräuchte er ZIP-Bomb-/XXE-Grenzen | optionaler XLSX-Fragenimport | R2 | R1-15, R0-38 | indirekt (Fragenzahlen-Test) |
| INV-E-029 | `tools/content-build/src/Text.php` | Import | Trimmen, Unicode-NFC, `nullable`, `bool` | reuse | Neutrale Normalisierung vor dem Hashen | Textnormalisierung beim Import | R1-Free | R1-15 | indirekt |
| INV-E-030 | `tools/content-build/src/SourceInfo.php` | Import | `SOURCE.md` lesen, Snapshot gegen `SHA256SUMS` prüfen | abstract | Provenienz- und Integritätsprüfung neutral; Format an `SOURCE.md` gebunden | Provenienzprüfung für Importquellen | R1-Free | R0-10, LB-09 | `testSnapshotMatchesChecksums` |
| INV-E-031 | `tools/content-build/src/SourceLiterals.php` | Import | Liest Konstanten/Arrays aus PHP-Seedern per Tokenizer, ohne Ausführung | exclude_domain_specific | An Laravel-Quelldateien gebunden; das Prinzip „fremden Code nie ausführen“ geht in R0-10 ein | – | exclude | R0-10 (nur Prinzip) | indirekt |
| INV-E-032 | `tools/content-build/src/NavigationAnswerValues.php`, `bin/navigation-values.php`, `data/navigation_answer_values.json` | Import | Lösungswerte und Toleranzen für Navigationsteilaufgaben pflegen, prüfen, Prüftabelle | exclude_domain_specific | Nautische Kartenaufgabe | – | exclude | – | `testNavigationAnswerValues*`, `testInvalidNavigationOverlayStopsTheBuild` |
| INV-E-033 | `tools/content-build/src/Concerns/BuildsVideoCourses.php` | Import | Videokurse aus Seeder-Literalen nachbilden (Kopien, Einschübe, Quiz einmal je Lektion) | exclude_domain_specific | Seeder-Nachbau; Zielmodell neutral über INV-E-010 | – | exclude | – | `testVideoUrlsFile` |
| INV-E-034 | `tools/content-build/src/Concerns/BuildsRadioTraining.php`, `BuildsRadioPractice.php` | Import | Funktexte, Vokabeln, Funkpraxis (über `App\Support\FunkPraxis`) | exclude_domain_specific | Funkausbildung | – | exclude | – | Golden Master |
| INV-E-035 | `tools/content-build/src/Concerns/BuildsExamRegistration.php` | Import | Prüfungsausschüsse und Anmeldeanleitungen | exclude_domain_specific | Amtliche Prüfungsanmeldung | – | exclude | – | `testExamGuidesCoverEveryCourse` |
| INV-E-036 | `tools/content-build/bin/video-lessons.php`, `data/video_urls.json` (`{}`) | Import | Liste aller Videolektionen als Vorlage für eine URL-Pflegedatei | replace | Video-URLs pflegt Course Captain im Lektionseditor bzw. im Import, nicht in einer Zusatzdatei | Lektionseditor/Import-Mapping | R1-Free | R1-05, R1-07 | `testVideoUrlsFile` |
| INV-E-037 | `tools/content-build/tests/PackageTest.php` (neutrale Fälle) | Test | Schema-Konformität aller Dateien, eindeutige IDs, Querverweise, Manifest-Prüfsummen, Determinismus, „keine Prüfung durch Ankreuzen aller Optionen bestehbar“ | abstract | Prüffälle sind neutral; Fixture ist der Bootskatalog | Validator-Testsuite mit neutralem Referenzpaket | R1-Free | R0-05, R1-15, R1-18, LB-AUDIT-006 | selbst |
| INV-E-038 | `tools/content-build/tests/PackageTest.php` (fachliche Fälle) | Test | Fragenzahlen am Commit, Prüfungsanleitungen, Feedback-Bereinigung, UBI-Ergänzung, Navigation, Kombis, Ergänzungskarten | exclude_domain_specific | Fachdaten | – | exclude | – | selbst |
| INV-E-039 | `tools/content-build/golden-master/compare.php` | Test | Paket Feld für Feld gegen frisch geseedete Laravel-PostgreSQL-DB vergleichen | exclude_domain_specific | Script an Laravel/PostgreSQL und Bootsdaten gebunden; die **Methode** „Golden Master gegen Referenzsystem“ ist Input für R0-05 (Kap. 4.3) | – (Methode → R0-05) | exclude | R0-05 (Methode) | CI `golden-master` |
| INV-E-040 | `tools/content-build/composer.json`, `composer.lock`, `phpunit.xml.dist` | Build | PHP ≥ 8.1 mit `intl`, `json`, `simplexml`, `zip`; dev: PHPUnit ^10.5, opis/json-schema ^2.3; `failOnWarning`, `failOnRisky` | reuse | Neutrale Werkzeugkette für Schema-Tests | Dev-Toolchain Schema-Validierung | R1-Free | R0-18 | CI `converter` |
| INV-E-041 | `tools/content-build/.phpunit.result.cache` | Build | leere Cache-Datei, trotz Eintrag in `.gitignore` eingecheckt | exclude_domain_specific | Artefakt ohne Funktion | – | exclude | – | – |
| INV-E-042 | `tools/content-build/README.md` | Doku | Bedienung, bewusste Abweichungen, Golden-Master-Ablauf | exclude_domain_specific | Konverter-Doku zur Bootsquelle; Ablauf in Kap. 4 übernommen | – | exclude | – | – |

### 2.3 Release, i18n, QA und Hilfswerkzeuge (`tools/`)

| ID | Quellpfad | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Testbezug |
|---|---|---|---|---|---|---|---|---|---|
| INV-E-043 | `tools/release/build-zips.sh` | Build | ZIPs aus einem Commit (`git archive`), Entwicklerdateien entfernen, reproduzierbar (Dateizeit = Commit-Zeit, sortiert, `zip -X -D`), Versionsgleichheit Kopf/Konstante/`Stable tag`/CHANGELOG, `--tag` prüft „geändert, aber gleiche Version“, verbotene Muster, `SHA256SUMS`, `versions.env`, `release-notes.md` aus CHANGELOG, optional esbuild-Minify mit `node --check` | abstract | Ablauf neutral; Paketnamen, vier Pakete und Konstanten fachgebunden | Release-Build für `course-captain` (Free) und später Pro-ZIP | R1-Free | R0-18, R0-35, R1-39, R3-18 | CI `release-zip` |
| INV-E-044 | `build-zips.sh`, Funktion `check_zip` | Build | Genau ein Ordner je ZIP, Hauptdatei vorhanden, keine `tests/tools/vendor/node_modules/.git*`, keine Composer-/PHPStan-/PHPCS-/npm-Dateien, kein `content/src`, `_moderation`, `*.zip` | reuse | Neutrale Paketprüfung; deckt Konzept 26.3 Schritt 6 (Negativprüfung) teilweise ab | ZIP-Negativprüfung, erweitert um Legacy-Namen (`learnboating`, `bootspruefung`, `bp_`) | R1-Free | R0-18, R0-35, R1-39 | CI `release-zip` |
| INV-E-045 | `tools/release/upgrade-check.php` | Test | `before`: Version, Schema, Zeilenzahl je Tabelle; `after`: neue Version ohne Reaktivierung laden, Migration, Tabellen/FKs vorhanden, keine Zeilen verloren | abstract | Upgrade-Probe neutral; Klassen `Bootspruefung\…` gebunden | Upgrade-/Datenerhalt-Probe | R1-Free | R0-37, R1-23, R1-40 | CI `upgrade` |
| INV-E-046 | `tools/i18n/check-domains.php` | Build | Jeder Übersetzungsaufruf trägt eine feste Text-Domain; je Paket aktuelle `.pot` | reuse | Neutral; Domainliste ersetzen (`course-captain`, `course-captain-pro`) | i18n-Lint | R1-Free | R0-15, R1-19, R0-35 | CI `static-analysis` |
| INV-E-047 | `tools/i18n/make-pot.sh` | Build | `wp i18n make-pot` je Paket, Ausschlüsse, leere Header, `--skip-theme-json`; Hinweis `make-json` für JS | abstract | Neutral bis auf Paketnamen | POT-/JSON-Erzeugung im Release | R1-Free | R0-15, R1-19, R0-35 | über `check-domains.php` |
| INV-E-048 | `tools/qa/check-abspath.php` | Build | Jede PHP-Datei unter `src/` beendet sich bei Direktaufruf (`defined('ABSPATH') \|\| exit;`) | reuse | Neutrale WordPress-Härtung | QA-Check Direktaufruf | R1-Free | R0-18, R0-35 | CI `static-analysis` |
| INV-E-049 | `tools/pdf-build/build-pdfs.mjs` (Arten `navigation`, `navigation-solutions`, `vocabulary`, `samples`, `texts`, `spelling`) | Build | Druck-PDFs für Navigation und Funk | exclude_domain_specific | Fachinhalte | – | exclude | – | keiner |
| INV-E-050 | `tools/pdf-build/build-pdfs.mjs` (Arten `exam`, `exam-key`) | Build | Druckbare Prüfungsbögen ohne Lösung bzw. mit Lösungsschlüssel aus einem Paket (Playwright/Chromium) | abstract | Druckexport eines Fragenbogens ist neutral; im Original ein Offline-Werkzeug, keine Plugin-Funktion, daher kein R1-Paritätsumfang | optionaler Druck-/PDF-Export von Prüfungsformen | R3 | – | keiner |
| INV-E-051 | `tools/product-images/generate.py` | Build | Shop-Produktbilder (Marine-Verlauf, Pillow, DejaVu) | exclude_domain_specific | Branding und Shopkatalog | – | exclude | – | keiner |
| INV-E-052 | `tools/course-images/README.md`, `make_placeholders.py` | Build | Kursfoto-Bildplätze, abgeleitete Ausschnitte | exclude_domain_specific | Bootsmotive, KI-Bilder | – | exclude | – | keiner |
| INV-E-053 | `tools/maps/make_de_states.py` | Build | Bundesländer-Karte für Prüfungsausschüsse (Natural Earth) | exclude_domain_specific | Prüfungsausschuss-Karte | – | exclude | – | keiner |
| INV-E-054 | `tools/device-sim/*`, `tools/sync-device-sim.sh` | Build | Gerätesimulation aus dem Snapshot kopieren, `SHA256SUMS`/`SOURCE.txt`, Tailwind-CSS bauen | exclude_domain_specific | Gerätesimulation; Lizenz ungeklärt | – | exclude | – | CI-Schritt „Gerätesimulation entspricht dem Snapshot“ |
| INV-E-055 | `tools/sync-source.sh` | Import | Content-Snapshot eines Quellcommits unverändert übernehmen (Pfadliste, `SOURCE.md`, `SHA256SUMS`) | exclude_domain_specific | An `hastla007/boatingschool` gebunden; Prinzip „unveränderter Snapshot mit Provenienz“ geht in R0-10 ein | – | exclude | R0-10 (nur Prinzip) | `testSnapshotMatchesChecksums` |

### 2.4 Theme `themes/kurswasser/`

| ID | Quellpfad | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Testbezug |
|---|---|---|---|---|---|---|---|---|---|
| INV-E-056 | `style.css`, `theme.json`, `templates/` (18), `parts/` (3), `screenshot.jpg` | UI | Block-Theme (FSE) „Kurswasser 2.0“: Startseite, Kurs-, Shop-, Checkout-, Rechtstext-Vorlagen | exclude_domain_specific | Konzept 26.2: fachgebundene Themes entfallen; Course Captain liefert kein Theme | – | exclude | – | Screenshots (`tests/screenshots.mjs`) |
| INV-E-057 | `theme.json` Palette-Slug `primary` (README „Das Lern-Plugin übernimmt die Akzentfarbe automatisch“) | UI | Plugin übernimmt Akzentfarbe aus dem aktiven Theme | abstract | Theme-Integration über `theme.json`-Presets ist neutral | Lernoberfläche erbt Farben/Typo aus globalen Styles mit Fallback | R1-Free | R1-16, R1-19, R0-15 | Browser-Tests mit `twentytwentyfive` und `kurswasser` (CI-Matrix) |
| INV-E-058 | `inc/learning.php` (`kurswasser_learning_compatible`, `kurswasser_learning_version`, Admin-Hinweis), Filter `bootspruefung_learning_contract` | API | Versionsvertrag Theme ↔ Plugin: bei inkompatiblem Plugin keine Plugin-Klassen nutzen, Rückfallausgabe, Hinweis | abstract | Neutrales Muster für Themes/Add-ons | öffentlicher Contract-Version-Check (`course_captain_contract`) | R1-Free | R0-19, R1-28, R3-11 | `tests/learning-compat-test.php` |
| INV-E-059 | `inc/consent.php`, `assets/js/consent.js`, `assets/css/consent.css`, Shortcode `kurswasser_consent_settings` | UI | Eigenes Cookie-Banner (notwendig/statistik/extern), Filter `kurswasser_consent_external_hosts`, Cookie `kw_consent`, seiten-cache-fest, steuert Woo-Bestellzuordnung | replace | Bedarf (LB-06) neutral; ein Plugin soll kein Website-Banner ausliefern, sondern vorhandene Einwilligung respektieren und sonst „Klick zum Laden“ anbieten | Consent-Adapter (Klick-zum-Laden als Standard, optionale Anbindung an ein Consent-Plugin) | R1-Free | R1-07, R1-34, R0-31, LB-06 | E2E `learning-flow.mjs` (externe Hosts); Teil D |
| INV-E-060 | `inc/content-pages.php` (Sync-Mechanik: `kurswasser_content_hash`, `_kw_content_hash`, Fingerprint, `kurswasser_content_auto_sync` per WP-Cron, „Alles übernehmen“ nur mit Bestätigung, WP-CLI `content-pages status/sync`) | PHP | Vorlagenseiten anlegen/aktualisieren, ohne Betreiber-Änderungen zu überschreiben | abstract | Prüfsummen-Schutz für bearbeitete Inhalte ist neutral | Setup legt Pflichtseiten (z. B. Lernbereich, Kurskatalog) an; Vorlagenupdate nur für unveränderte Seiten | R1-Free | R1-32, R1-39, R2-03 | `tests/content-pages-test.php` |
| INV-E-061 | `content/pages.php`, `content/pages/*.html` (35), `content/src/*.md` (35) + `_moderation/` (5) | Daten | 35 Marketing-/Ratgeberseiten zum Bootsführerschein | exclude_domain_specific | Fachinhalte, Werbetexte | – | exclude | – | `content-pages-test.php` |
| INV-E-062 | `tools/content_build.py`, `tools/test_content_build.py`, `tools/fixtures/` (6) | Build | Markdown mit Front-Matter → Gutenberg-Block-Markup + Manifest; `--check` | exclude_domain_specific | Redaktionswerkzeug für die Theme-Seiten; Course Captain bearbeitet Inhalte im Block-Editor | – | exclude | – | CI `python3 -m unittest discover -s themes/kurswasser/tools` |
| INV-E-063 | `inc/seo.php` (Metabox „SEO (Kurswasser)“, Titel/Description/Canonical/noindex, JSON-LD, `kurswasser_seo_shop_private_ids`, Rückfall bei Yoast/Rank Math) | PHP | Eigene SEO-Ausgabe nur ohne SEO-Plugin; private Seiten aus Sitemaps | abstract | Rückfall-Logik und Ausschluss privater Seiten sind neutral | Kurs-SEO: öffentliche/private Grenzen, Sitemap-Ausschluss, Fallback ohne SEO-Plugin | R1-Free | R0-33, R1-37 | `tests/content-pages-test.php` |
| INV-E-064 | `inc/content-yoast.php` (Yoast-Felder schreiben, `Indexable_Post_Watcher::build_indexable`, `WPSEO_Sitemaps_Cache::invalidate_post`) | PHP | Seiten-SEO direkt in Yoast-Felder, Indexables neu bauen | abstract | Yoast-Interop neutral; Kurswasser-Felder fachgebunden | Yoast-Kompatibilität für Kurs-/Katalogseiten | R1-Free | R0-33, R1-37, R1-30 | `tests/setup-yoast-test.php` (Yoast als Stub) |
| INV-E-065 | `inc/setup.php` (Assistent: Plugin-Status, Installation per `plugins_api`/`Plugin_Upgrader` mit `install_plugins`/`activate_plugins` + Nonce, `DISALLOW_FILE_MODS`-Hinweis, Plugin-ZIP nur lokal/https mit SHA-256, Warnung zu ZIPs im Theme-Ordner; WP-CLI `kurswasser setup plugins`) | UI | Einrichtungs-Assistent nach Theme-Aktivierung | abstract | Rechte-, Nonce- und Rückfallregeln neutral; Installation fremder ZIPs braucht ein WordPress.org-Plugin nicht | Setup-Assistent mit Rechteprüfung, optionaler Partner-Plugin-Erkennung | R1-Free | R1-32, R0-30, FLOW-005 | `tests/setup-yoast-test.php` |
| INV-E-066 | `inc/setup-shop.php` (Shop-Einrichtung: Land, MwSt.-Weg oss/own/small, EU-Steuertabelle `KURSWASSER_EU_VAT_RATES`, Produkte als Entwurf, Schnupper-Paket; WP-CLI `kurswasser setup shop`) | Commerce | WooCommerce-Grundkonfiguration durch das Theme | replace | Shop- und Steuerkonfiguration gehört zu WooCommerce/Betreiber; Course Captain richtet in R3 nur Produkt↔Kurs-Zuordnung ein | R3-Woo-Connector-Setup (nur Mapping, keine Steuerkonfiguration) | R3 | R3-02, R3-14, R3-FIRST-001 | `tests/setup-shop-test.php` |
| INV-E-067 | `inc/courses.php` (Kurs-Infoseiten, Kacheln `[kurswasser_course_tiles]`, `[kurswasser_course]`, Kurs-FAQ, Prüfungsablauf, Simulationsabschnitt) | UI | Kursmarketing für 14 Bootskurse | replace | Kurskatalog und Kursseiten sind Bedarf, aber Course Captain liefert sie selbst (Block/Shortcode) statt über ein Theme | Kurskatalog-/Kurskarte-Block | R1-Free | R1-16, R1-28, PAR-01 | `content-pages-test.php` |
| INV-E-068 | `inc/offers.php`, `assets/js/offers.js` (`[kurswasser_offers]`, `[kurswasser_purchase_info]`, Ersparnis, „Jetzt starten“ → Kasse) | Commerce | Angebotsreiter mit Woo-Preisen und Vergleichspreisen | replace | Verkaufsdarstellung gehört in den Woo-Connector bzw. zu WooCommerce | Woo-Connector: Kurs-Kaufbutton/Preis aus Woo | R3 | R3-02, R3-13 | Teil B (Woo-Tests) |
| INV-E-069 | `inc/legal.php`, `legal/*.html` (5 Vorlagen, 82 Platzhalter), WP-CLI `kurswasser legal` | Doku | Entwürfe für Impressum, Datenschutz, AGB, Widerruf, Kontakt | replace | Rechtstexte sind rechtsraum- und betreiberspezifisch; ein Plugin liefert höchstens Datenschutz-Textbausteine über WordPress-Bordmittel | Datenschutzhinweise per `wp_add_privacy_policy_content` | R1-Free | R1-17, R0-17 | `tests/legal-test.php` |
| INV-E-070 | `inc/operator.php` (Betreiberangaben, Platzhalter `[[Betreiber: …]]`, Mail-Fußzeile) | PHP | Betreiberdaten einmal pflegen und in Vorlagen/Mails ersetzen | replace | Absender-/Fußzeilenangaben gehören in die Mail-Einstellungen des Plugins | Mail-Einstellungen (Absender, Fußzeile) | R1-Free | R1-21, R1-34 | `tests/legal-test.php` |
| INV-E-071 | `functions.php` (Cache-Version, Icon-Masken-CSS mit Transient, `#kw:`-Linkziele, Shortcodes `kurswasser_account/brand/year/products`, Widerrufslink) | PHP | Theme-Helfer und Shortcodes | exclude_domain_specific | Theme-intern und markengebunden | – | exclude | – | `content-pages-test.php` |
| INV-E-072 | `functions.php` (`kurswasser_request_string(s)`) | PHP | Request-Parameter typisiert und bereinigt lesen | reuse | Neutrales Sicherheitsmuster | zentrale Request-Sanitizer | R1-Free | R1-03, R0-RBAC-002 | indirekt |
| INV-E-073 | `patterns/*.php` (11) | UI | Block-Muster der Startseite | exclude_domain_specific | Marketing mit Bootsbezug | – | exclude | – | keiner |
| INV-E-074 | `assets/fonts/manrope-*.woff2`, `OFL-Manrope.txt` | UI | Lokale Schrift (OFL-1.1) | exclude_domain_specific | Theme-Branding; Plugin erbt Schrift vom aktiven Theme | – | exclude | – | – |
| INV-E-075 | `assets/icons/*.svg` (19), `assets/images/hero-boat.*` (3), `assets/images/courses/*` (38 Bilder + `IMAGE-RIGHTS.md`) | UI | Symbole, Hero-Foto, KI-Kursfotos | exclude_domain_specific | Bootsmotive; Herkunft von Hero und Icons laut `CREDITS.md` nicht belegt | – | exclude | – | – |
| INV-E-076 | `assets/js/content-pages.js`, `assets/css/theme.css` | UI | Interaktion und Stile der Theme-Seiten | exclude_domain_specific | Theme-spezifisch | – | exclude | – | Screenshots |
| INV-E-077 | `languages/kurswasser.pot` | Build | Übersetzungsvorlage des Themes | exclude_domain_specific | Theme-Strings; Methode in INV-E-046/-047 | – | exclude | – | `check-domains.php` |
| INV-E-078 | `tests/demo-setup.php`, `tests/screenshots.mjs` | Test | Demo-Stand anlegen; Screenshots Desktop/Mobil als CI-Artefakt | abstract | Screenshot-Artefakte pro Release sind ein neutrales Abnahmemittel | visuelle Abnahme-Artefakte (Admin- und Lernflows) | R1-Free | R0-26, R1-29 | CI-Schritt „Theme Kurswasser – Demo-Stand und Screenshots“ |
| INV-E-079 | `tests/setup-yoast-test.php` (Plugin-Installation aus Dummy-ZIP, Yoast als Stub) | Test | Interop-Test mit simuliertem Fremd-Plugin | abstract | Neutrales Muster „Partner-Plugin simulieren“ | Interop-Tests gegen Stubs (Yoast, Consent, Cache) | R1-Free | R1-30, R0-20 | selbst |
| INV-E-080 | `README.md`, `CHANGELOG.md` (Theme) | Doku | Theme-Doku, Versionen | exclude_domain_specific | Theme entfällt | – | exclude | – | – |

### 2.5 Staging (`staging/`)

| ID | Quellpfad | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Testbezug |
|---|---|---|---|---|---|---|---|---|---|
| INV-E-081 | `staging/docker-compose.yml` | Build | Zielsystem: MariaDB 12.3.3, WordPress 7.1.4/PHP 8.5 Apache, WP-CLI-Setup-Container, Cron-Container, Mailpit v1.27, Caddy 2.10 (Profil `https`), `IMAGE_REGISTRY` umschaltbar | abstract | Aufbau neutral; Woo und Plugin/Theme-Pfade fachgebunden | Staging-/Abnahmeumgebung `course-captain` (ohne Pflicht-Woo; Woo-Profil für R3) | R1-Free | R0-16, R0-20, R1-23 | CI `staging` |
| INV-E-082 | `staging/setup.sh` | Build | Idempotente Einrichtung per WP-CLI (Installation, Sprache, Permalinks, `blog_public 0`, Woo-Grundwerte, Plugin/Theme aktivieren, `wp bootspruefung migrate`, Paket bauen und importieren, Demo-Konto, Rechte freigeben) | abstract | Wiederholbarkeit und Reihenfolge neutral; Befehle und Inhalte fachgebunden | Staging-Setup mit neutralem Demo-Paket | R1-Free | R0-16, R1-23, R1-39 | CI `staging` (Schritt „Neustart (Einrichtung wiederholbar)“) |
| INV-E-083 | `staging/mu-plugins/staging-mail.php` | PHP | Alle Mails an Mailpit, Absender `staging@example.invalid` | reuse | Neutral | Mail-Abfang in Test/Staging | R1-Free | R1-21, R0-16 | E2E `account-flow.mjs` (eigenes MU-Plugin, Teil D) |
| INV-E-084 | `staging/Caddyfile`, `staging/php.ini` | Build | HTTPS-Proxy mit `X-Robots-Tag: noindex, nofollow`; Upload 64M, `memory_limit 512M`, `expose_php Off` | reuse | Neutral | Staging-Härtung | R1-Free | R0-16 | – |
| INV-E-085 | `staging/.env.example`, `staging/.gitignore` | Build | Geheimnisse nur in nicht eingecheckter `.env` | reuse | Neutrales Secret-Handling | Secret-Regel für Staging | R1-Free | R0-16, R0-31 | – |
| INV-E-086 | `staging/README.md` | Doku | Start lokal/Server, HTTPS, Abnahme-Checkliste | abstract | Ablauf neutral, Checkliste fachlich | Staging-Runbook | R1-Free | R0-16 | – |

### 2.6 CI (`.github/workflows/`)

| ID | Quellpfad | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Testbezug |
|---|---|---|---|---|---|---|---|---|---|
| INV-E-087 | `ci.yml` Job `plugin` (Matrix) | Build | PHP 8.5/WP 7.1.4/WC 11.2.2/MariaDB 12.3.3 (Ziel, E2E), WC 11.3 Beta (informativ), PHP 8.1/WP 7.0.0/MariaDB 10.11 (Untergrenze), PHP 8.3/MySQL 8.4 (informativ); WordPress aus `johnpbloch/wordpress-core`, Plugin per Symlink; Integration und Playwright | abstract | Matrixprinzip (Ziel/Untergrenze/informativ) neutral; Werte sind keine CC-Zielwerte (R0-20) | CI-Matrix `course-captain` ohne Woo-Pflicht, Woo-Achse ab R3 | R1-Free | R0-18, R0-20, R1-30 | selbst |
| INV-E-088 | `ci.yml`, Muster `grep -iE "PHP (Warning\|Deprecated\|Notice\|Fatal)"` nach jedem Lauf | Build | PHP-Warnungen lassen die CI scheitern | reuse | Neutral | „Keine PHP-Notices“-Gate | R1-Free | R0-18 | selbst |
| INV-E-089 | `ci.yml` Job `static-analysis` | Build | `check-domains.php`, `check-abspath.php`, PHPStan Stufe 5 mit Baseline, PHPCS-Sicherheitsregeln blockierend, Stilregeln nur informativ | abstract | Werkzeuge neutral; Course Captain startet ohne Baseline | statische Analyse (PHPStan, WPCS Security) | R1-Free | R0-18, R0-35 | selbst |
| INV-E-090 | `ci.yml` Job `release-zip` | Build | ZIPs bauen, statt Repo-Ordnern die ZIPs installieren, Basistests gegen das installierte Paket | reuse | Neutral: testet das ausgelieferte Artefakt | Artefakt-Test vor Release | R1-Free | R0-18, R0-35, R1-39 | selbst |
| INV-E-091 | `ci.yml` Job `upgrade` | Build | Vorversion = letzter Tag (`git describe`), sonst fester Commit `004e728`; Daten anlegen, neue ZIP darüber, `upgrade-check.php`, Weiterbetrieb testen | reuse | Neutral | Upgrade-Gate von Vorversion | R1-Free | R0-37, R1-23, R1-40 | selbst |
| INV-E-092 | `ci.yml` Job `multisite` | Build | Frische Installation in Multisite umwandeln, `multisite-test.php` | abstract | Konzept 23.1: „Multisite explizit nicht unterstützt“; Test bleibt als Negativ-/Grenztest sinnvoll | Multisite-Grenztest (Aktivierung, Site-Löschung) | R1-Free | R0-25, R0-20 | selbst |
| INV-E-093 | `ci.yml` Job `staging` | Build | `docker compose up`, Exit-Code Setup, Seiten erreichbar, Neustart wiederholbar, keine PHP-Fehler, Abbau | reuse | Neutral | Staging-Rauchtest in CI | R1-Free | R0-16, R0-18 | selbst |
| INV-E-094 | `ci.yml` Jobs `converter`, `golden-master` | Build | Konverter-Tests (PHP 8.3) und Golden Master gegen Laravel/PostgreSQL 16 | exclude_domain_specific | An die Bootsquelle gebunden; Methode → R0-05 | – | exclude | R0-05 (Methode) | selbst |
| INV-E-095 | `ci.yml` Jobs `affiliate-connector`, `voucher-captain` | Build | Connector-Syntax/-Test (PHP 8.1/8.5); Voucher Captain aus externem Repo zu festem Commit, informativ | abstract | Muster „Partner zu fester Version, informativ“ neutral | Suite-Contract-CI je Connector | R3 | R3-06, R3-09, R3-11 | selbst |
| INV-E-096 | `.github/workflows/release.yml` | Build | Tag `v*` → CI für Commit grün (`gh run list`)? Tag auf `main`? esbuild 0.24.2, `build-zips.sh --tag`, `sha256sum -c`, Artefakt 90 Tage, GitHub-Release (Vorabversion bei `-`) | abstract | Gates neutral; Ziel ist bei CC zusätzlich WordPress.org (SVN) | Release-Pipeline mit Gates; WordPress.org-Deploy ergänzen | R1-Free | R0-18, R0-35 | selbst |
| INV-E-097 | (fehlt in `ci.yml`/`release.yml`) | Build | Kein SAST außer PHPCS-Sicherheitsregeln, kein Dependency-Scan, keine Paket-Signatur (nur `SHA256SUMS`) | replace | R0-18 verlangt SAST, Dependency-Scan, Signierung: in der Quelle nicht vorhanden, also neu festlegen | SAST/Dependency-Scan/Signatur in der CI | R1-Free | R0-18, R3-18 | keiner |

### 2.7 Testinfrastruktur (`bootspruefung-learning/tests/`, QA-Konfiguration)

| ID | Quellpfad | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Testbezug |
|---|---|---|---|---|---|---|---|---|---|
| INV-E-098 | `tests/integration/support/guard.php` | Test | Abbruch, wenn `DB_NAME` nicht „test“ enthält; Ausnahme `BP_ALLOW_DESTRUCTIVE_TESTS=1` | reuse | Neutraler Schutz vor Tests gegen Live-Daten | Test-DB-Guard | R1-Free | R0-12, R0-18 | in allen Integrationstests |
| INV-E-099 | `tests/integration/support/standalone.php` | Test | Plugin bei Bedarf aktivieren und Test in neuem Prozess neu starten, fällige Migration nachholen, Einstellungen sichern und am Ende wiederherstellen | abstract | Testisolation neutral; Klassen `Bootspruefung\…` | Test-Bootstrap mit Isolation | R1-Free | R0-12, R1-18 | `content-flow-test.php`, `navigation-check-test.php` |
| INV-E-100 | `tests/integration/support/FakeOrders.php` | Test | Testbestellungen über das Interface `OrderSource` statt WooCommerce | abstract | Neutrales Muster: Grant-Quelle als Interface mit Fake | Fake-Grant-Source für R1-Tests ohne Woo | R1-Free | R1-13, R0-09, R3-02 | `commerce-test.php` (Teil B) |
| INV-E-101 | Runner-Muster aller PHP-Testskripte (`$check`-Closure, `ok/FAIL`-Ausgabe, Exit-Code; `WP_DIR` → `wp-load.php`) | Test | Integrationstests als Skripte gegen echtes WordPress + MariaDB, ohne PHPUnit | replace | Echte DB-Läufe sind neutral wertvoll; ohne Testframework fehlen Isolation, Berichte, Filter, Coverage → Entscheidung D-E-02 | PHPUnit mit WordPress-Testsuite + echte DB; Skripte als Szenario-Suite | R1-Free | R0-12, R0-18 | 26 Skripte |
| INV-E-102 | `tests/integration/smoke-test.php` | Test | WordPress bei Bedarf installieren, Plugin zweimal aktivieren (Idempotenz), Tabellen/InnoDB/FKs/Capabilities/REST, Datenschutz-Löschung, Deinstallation mit und ohne Datenlöschung | abstract | Lifecycle-Prüffälle neutral | Lifecycle-Smoke | R1-Free | R1-01, R1-23, R0-18 | selbst |
| INV-E-103 | `tests/integration/uninstall-phase.php` | Test | Deinstallation in frischem Prozess; prüft, dass genau Plugin-Daten (Optionen, Transients, Nutzer-Meta, Cron, Beiträge im Papierkorb) verschwinden und ähnlich benannte fremde Daten bleiben | abstract | Neutraler Prüffall | Uninstall-Probe | R1-Free | R1-23, R1-01 | `smoke-test.php` |
| INV-E-104 | `tests/integration/multisite-setup.php` | Test | Testinstallation in Multisite (Unterverzeichnisse) umwandeln | abstract | Siehe INV-E-092 | Multisite-Grenztest-Setup | R1-Free | R0-25 | `multisite-test.php` (Teil C) |
| INV-E-105 | `tests/integration/woocommerce-setup.php` | Test | WooCommerce aktivieren/deaktivieren (`WOO_DEACTIVATE=1`) | abstract | Neutral, aber nur für Connector-Tests | Woo-Test-Setup | R3 | R3-02, R3-09 | Woo-Tests (Teil B) |
| INV-E-106 | `tests/e2e/setup.php` | Test | `siteurl/home`, Permalinks, Block-Theme wählen (`BP_THEME`, Standard `twentytwentyfive`), Testnutzer `lerner1`/`admin` | abstract | Neutral; Markenname „Testmarke“ und Pfad `/bootsscheine/` fachlich | E2E-Setup | R1-Free | R1-18, R0-26 | alle E2E-Abläufe |
| INV-E-107 | `tests/e2e/router.php` | Test | Router für `php -S` (Dateien direkt, sonst `index.php`) | reuse | Neutral | E2E-Server ohne Webserver | R1-Free | R1-18 | alle E2E-Abläufe |
| INV-E-108 | `tests/e2e/account-setup.php`, `membership-setup.php` | Test | Testdaten für Konto- bzw. Mitgliedschaftsablauf, `reset` | abstract | Neutral (Konto, geschützte Inhalte) | E2E-Fixtures Konto/Zugriff | R1-Free | R1-14, R1-17, R1-18 | `account-flow.mjs`, `membership-flow.mjs` |
| INV-E-109 | `tests/e2e/checkout-setup.php` | Test | Testdaten Kaufablauf | abstract | Woo-gebunden | E2E-Fixtures Woo-Kauf | R3 | R3-02, R3-13 | `checkout-flow.mjs` |
| INV-E-110 | `tests/e2e/guide-setup.php` | Test | Testdaten Prüfungsanmeldung | exclude_domain_specific | Amtliche Prüfungsanmeldung | – | exclude | – | `guide-flow.mjs` |
| INV-E-111 | Runner-Muster der E2E-Abläufe (`*.mjs`: Playwright 1.48.2 ohne Test-Runner, `node:assert`, Fehlersammlung `pageerror`/`console`/`requestfailed`, externe Hosts erfasst, Screenshots per `BP_SHOTS`) | Test | Browser-Abläufe als Node-Skripte | abstract | Fehler- und Fremdhost-Erfassung neutral; ohne `@playwright/test` fehlen Parallelisierung, Traces, Wiederholungen → D-E-02 | Playwright-Test-Suite mit Fremdhost- und Konsolenfehler-Gate | R1-Free | R1-18, R0-26, R1-29 | 7 Abläufe |
| INV-E-112 | `tests/phpstan/bootstrap.php`, `external-stubs.php`, `wp-cli-stubs.php`; `phpstan.neon.dist` (Stufe 5, PHP 8.1–8.5, Theme und Add-ons eingeschlossen, `reportUnmatchedIgnoredErrors`), `phpstan-baseline.neon` (691 Zeilen) | Build | Statische Analyse mit WP-/Woo-Stubs und Platzhaltern für Voucher/Affiliate Captain | abstract | Konfiguration neutral; Baseline ist Altlast | PHPStan-Konfiguration ohne Baseline, höhere Stufe | R1-Free | R0-18 | CI `static-analysis` |
| INV-E-113 | `phpcs.xml.dist` (nur `WordPress.Security.*`, `WordPress.DB.PreparedSQL*` blockierend), `phpcs-baseline.xml` (189 Zeilen), `composer.json` Skripte `phpstan`/`phpcs`/`phpcs-style` | Build | WPCS-Sicherheitsregeln als Gate, Stil nur Information | abstract | Neutral; für WordPress.org ist voller WPCS-Lauf empfehlenswert | PHPCS-Gate (Security blockierend, Stil-Entscheidung in R0-18) | R1-Free | R0-18, R0-35 | CI `static-analysis` |

### 2.8 Repository-Dokumente im Stamm

| ID | Quellpfad | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Testbezug |
|---|---|---|---|---|---|---|---|---|---|
| INV-E-114 | `README.md` | Doku | Repo-Karte, Befehle (Build, Import/Rollback/Reconcile per WP-CLI, Tests, Staging), Membership-, Härtungs-, Consent-Hinweise | abstract | Struktur neutral; Inhalte fachlich. Quelle der LB-01…LB-08, LB-15 | Entwickler-README `course-captain` | R1-Free | R1-24, LB-01…LB-08 | – |
| INV-E-115 | `konzept-wp-lernplugin.md` | Doku | Technisches Konzept: Paketformat, Importregeln, Content-Feldliste, WP-Datenmodell, Lernmodi, WooCommerce-Spezifikation, Sicherheit, Umsetzungsstand | abstract | Paketformat (3.2/3.3), Datenmodell (5) und Status-/Erstattungsmatrix (7.5) sind neutrale Referenz; Kap. 4.6–4.9 und 6.4 fachlich | Referenz für R0-06, R0-10, CORE-SPEC-005 | R1-Free | R0-06, R0-10, LB-13, LB-14 | – |
| INV-E-116 | `CREDITS.md` | Doku | Register der Fremdbestandteile mit Lizenz und Beleg; Kopie im Plugin-ZIP | reuse | Neutrales Muster | Herkunfts-/Lizenzinventar im Paket | R1-Free | R0-35, R0-01 | `build-zips.sh` prüft Vorhandensein im ZIP |
| INV-E-117 | `.gitignore` | Build | Ausschlüsse für Build, vendor, Caches, ZIPs im Theme-Ordner | reuse | Neutral | Repo-Hygiene | R1-Free | R0-18 | – |

### 2.9 `content-source/boatingschool/` (strukturell; Datenformat-Referenz)

| ID | Quellpfad | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Testbezug |
|---|---|---|---|---|---|---|---|---|---|
| INV-E-118 | `SOURCE.md`, `SHA256SUMS`, `README.md` | Doku | Provenienz (Repo, Ref, Commit, Datum, Dateizahl), Prüfsummen, bewusste Auslassungen (Demo-/Mandanten-/Nutzerdaten) | abstract | Provenienzprotokoll ist neutral; Inhalt quellgebunden | Provenienzprotokoll je Importquelle | R1-Free | R0-10, LB-09 | `testSnapshotMatchesChecksums` |
| INV-E-119 | `database/data/` (321 Dateien: Fragenkataloge CSV/PHP/XLSX, Prüfungsbögen, Praxis-/ELWIS-Bilder, Funkmaterial, Ausschüsse) | Daten | Fachinhalte | exclude_domain_specific | Fachinhalte, Rechte ungeklärt (`CREDITS.md`) | – | exclude | – | Golden Master |
| INV-E-120 | `database/seeders/` (43) | Import | Kurse, Module, Bögen, Videokurse, Funk, Praxis, Ausschüsse, Regelwerke seeden | exclude_domain_specific | Fachdaten | – | exclude | – | Golden Master |
| INV-E-121 | `database/seeders/Concerns/SeedsLessonQuizOnce.php`, Migration `…000501_create_seed_marker_table` | Import | Quiz je Lektion nur einmal seeden; Seed-Marker | abstract | Idempotentes Einspielen von Startinhalten ist neutral | idempotente Demo-/Vorlagen-Installation | R2 | R2-03 | – |
| INV-E-122 | `app/Support/` (7) | PHP | Kombi-Gruppen, Funktraining/-praxis, Gerätesimulation, Navigationsantworten, Videokurs-Lektionen | exclude_domain_specific | Fachlogik | – | exclude | – | `resources/js/funkgeraet/tests` (Quelle) |
| INV-E-123 | `app/Console/Commands/ImportContentQuestions.php`, `ImportContentFeedback.php` | Import | Laravel-Import des zentralen Fragenkatalogs und des Feedbacks | exclude_domain_specific | Quell-Laufzeit; Zielimport ist R1-15 | – | exclude | – | – |
| INV-E-124 | `resources/js/funkgeraet.js`, `resources/js/funkgeraet/` (36) | UI | Funkgeräte-Simulation inkl. Tests | exclude_domain_specific | Gerätesimulation, Lizenz ungeklärt | – | exclude | – | `resources/js/funkgeraet/tests` |
| INV-E-125 | `public/images/courses/` (15) | UI | Kursbilder | exclude_domain_specific | Bootsmotive, Rechte ungeklärt | – | exclude | – | – |
| INV-E-126 | Migrationen `…000020_create_content_tables`, `…000030_create_module_and_course_tables`, Modelle `ContentQuestion`, `ContentQuestionRevision`, `ContentAnswer`, `Module`, `CourseDefinition`, `MediaAsset` (Tabellen `content_question`, `content_question_revision`, `content_answer`, `module`, `module_content`, `course_definition`, `course_module`, `media_asset`, `question_media`) | Daten | Revisionierte Fragen, Module, Kurse, Medien | abstract | Datenformat-Referenz für R0-06; Spalten wie `smartmodus_kategorie`, amtliche Nummern fachlich | CC-Inhaltsmodell (Kurs/Modul/Frage-Revision/Medium) | R1-Free | R0-06, R0-DATA-001, R1-04, R1-06 | Golden Master |
| INV-E-127 | Migration `…000475_add_one_published_revision_constraint` | Daten | Höchstens eine veröffentlichte Revision je Frage (DB-Constraint) | abstract | Neutrale Invariante | Unique-Regel „eine aktive Revision“ | R1-Free | R0-06, R0-23 | – |
| INV-E-128 | Migrationen `…000050_create_exam_tables`, `…000110_create_exam_paper_tables`, Modelle `ExamRuleSet`, `ExamBlueprint`, `ExamPaper`, `ExamPaperQuestion`, `ExamSession`, `ExamSessionQuestion`, `Attempt` | Daten | Regelwerke, Blueprints, Bögen, Prüfungssitzungen mit Fragen-Snapshot, Versuche | abstract | Datenformat-Referenz; Regelwerksinhalte amtlich | Prüfungs-Blueprint, Versuch und Snapshot | R1-Free | R0-08, R1-08, R1-20, PAR-04 | Golden Master |
| INV-E-129 | Migrationen `…000460`/`…000466` (ein laufender Prüfungsversuch je Bogen bzw. Kurs), `…000470` (`revealed_at`), `…000478`/`…000479` (`response_time_ms` bigint, Idempotenz über `started_at_ms`) | Daten | Integritätsregeln für Prüfungen und Antworten | abstract | Neutrale Invarianten gegen Doppelstart und Doppelabgabe | Unique-/Idempotenzregeln Prüfung | R1-Free | R0-21, R1-20, R1-42 | – |
| INV-E-130 | Migration `…000040_create_entitlement_and_learning_tables`, Modelle `Entitlement`, `Progress`, `Favorite`, `FavoriteCollection`, `FavoriteCollectionItem`, `VideoProgress`; `…000450_drop_next_review_at_from_progress_table`, `…000575_add_entitlement_expiry_reminder` | Daten | Zugänge, Lernstand, Favoriten/Sammlungen, Videofortschritt, Ablauf-Erinnerung | abstract | Datenformat-Referenz; Mandantenbezug ersetzen | Grant-, Fortschritts- und Favoritenmodell | R1-Free | R0-06, R0-09, R1-10, R1-11, R1-13 | – |
| INV-E-131 | Migrationen `…000080`/`…000090`/`…000330`/`…000500`, Modelle `VideoModule`, `VideoLesson`, `VideoLessonStep` | Daten | Videokapitel, Lektionen, Schritte, Lektionsquiz | abstract | Wie INV-E-010 | Lektion/Activity-Modell | R1-Free | R1-07 | – |
| INV-E-132 | Modelle `PraxisTask`, `PraxisProgress` (`…000140_create_praxis_trainer_tables`, `…000578_add_active_to_praxis_task`) | Daten | Karteikarten und Kartenfortschritt | abstract | Wie INV-E-011; Inhalte fachlich | Karteikarten-Fortschritt | R1-Free | R1-11, PAR-05 | – |
| INV-E-133 | Modell `AuditLog` (`…000060`), `…000458_set_null_on_delete_for_actor_reference_foreign_keys`, `…000459_grant_audit_log_pseudonymization_update` | Daten | Audit-Log; Akteur bei Löschung auf NULL; Pseudonymisierung | abstract | Neutrale Datenschutzregeln für Audit | Audit-Log mit Pseudonymisierung | R1-Free | R0-13, R0-17, R1-17 | – |
| INV-E-134 | Modelle `Tip`, `TipCategory` (`…000300`, `…000310`), `PlatformAnnouncement` (`…000584`), `TenantAnnouncement` (`…000582`) | Daten | Lerntipps, Ankündigungen | abstract | Neutrale Lernbegleit-Inhalte (Plugin-Seite → Teil A) | Tipps/Ankündigungen | R1-Free | R1-21, R1-16 | – |
| INV-E-135 | Modelle `Tenant`, `TenantUser`, `TenantBranding`, `TenantInvitation`, `TenantCourseDisabled`, `TenantCourseLocation`, `TenantAddon`, Concern `BelongsToTenant`; Migrationen `…000070_enable_row_level_security`, `…000456`, `…000457`, `…000461`, `…000468`, `…000471`, `…000476`, `…000483`, `…000493` | Daten | Mandantenfähigkeit mit PostgreSQL-Row-Level-Security | replace | Course Captain ist Single-Site (R0-25); RLS gibt es in WordPress/MySQL nicht; Ersatz durch Objekt-Scope-Prüfung im Policy-Service | Policy-Service mit Objekt-Scope (R0-07) | R1-Free | R0-07, R0-25, R1-03 | – |
| INV-E-136 | `TenantInvitation` (`…000455`), `…000581_add_reminded_at_to_tenant_user` | Daten | Einladungen mit Erinnerung | abstract | Einladungs-/Claim-Kern neutral | Gruppen-Einladung/Claim | R1-Free | R0-09, R1-13, CORE-SPEC-003 | – |
| INV-E-137 | Modelle `Coupon`, `Product`, `ProductPurchase`, `CourseWebshopLink`, `AddonTemplate`, `CoursePromoBanner`, Concern `HasDiscountedPriceLabel`; Migrationen `…000210`, `…000250`–`…000270`, `…000350`–`…000400`, `…000435`–`…000438`, `…000452`, `…000453`, `…000467`, `…000473`, `…000574` | Commerce | Eigene Gutscheine, Produkte, Käufe, Webshop-Links, Rabatt-Banner | replace | Commerce liegt bei WooCommerce/Voucher Captain (R3), nicht im Free-Kern | Woo-/Voucher-Connector | R3 | R3-02, R3-04, R0-40 | – |
| INV-E-138 | `…000453_add_coupon_redemption_unique_indexes` | Daten | Eindeutige Einlösung (Unique-Index) | abstract | Idempotenz-Invariante neutral | Unique-Regel Grant-Quelle (`source_type`+`source_id`) | R1-Free | R0-21, CORE-SPEC-004 | – |
| INV-E-139 | Modelle `WillkommensboxOrder` (`…000437`, `…000442`, `…000443`) | Commerce | Versand einer Willkommensbox | exclude_domain_specific | Betreiberspezifisches Zusatzangebot | – | exclude | – | – |
| INV-E-140 | Modelle `NavigationTask`, `NavigationQuestion`, `NavigationProgress` (`…000100`, `…000120`, `…000577`) | Daten | Navigationsaufgaben und Fortschritt | exclude_domain_specific | Nautisch | – | exclude | – | – |
| INV-E-141 | Modelle `PraxisFormTemplate`, `PraxisFormSubmission`, `PraxisExamAppointment`, `Pruefungsausschuss`, `CourseExamRegistration` (`…000410`–`…000447`, `…000480`) | Daten | Praxisformulare, Prüfungstermine, Ausschüsse, Prüfungsanmeldung | exclude_domain_specific | Amtliche Prüfung/Bootsschulbetrieb | – | exclude | – | – |
| INV-E-142 | Modell `RadioTrainingProgress` (`…000479_create_radio_training_progress_table`), `…000565_allow_device_simulation_progress` | Daten | Funk- und Simulationsfortschritt | exclude_domain_specific | Funk/Gerätesimulation | – | exclude | – | – |
| INV-E-143 | Modell `InstructorCourse` (`…000583_add_team_two_factor_and_instructor_courses`) | Daten | Trainer ↔ Kurs-Zuordnung, Team-2FA | abstract | Mehrere Trainer je Kurs ist neutral (P1-Register) | Trainer-Kurs-Zuordnung | R3 | R0-07 (Rollen); P1 „Mehrere Trainer je Kurs“ (ohne ID) | – |
| INV-E-144 | Modell `User` (`…000170`, `…000200`, `…000434`, `…000454`, `…000469`, `…000472`, `…000576`) | Daten | Profil, Superadmin, Geburtsdatum, ausstehende E-Mail, 2FA | replace | WordPress-Nutzer und Capabilities ersetzen das Laravel-Nutzermodell; Geburtsdatum fachlich (Mindestalter) | WP-User + Capabilities | R1-Free | R0-07, R1-03 | – |
| INV-E-145 | Modell `PlatformSetting` (`…000220`, `…000580_add_mail_templates_to_platform_setting`) | Daten | Plattform-Einstellungen, Mailvorlagen | replace | Ersetzt durch Settings-Register (R0-27) und WP-Optionen | Settings-Register | R1-Free | R0-27, R1-21, R1-31 | – |
| INV-E-146 | Migrationen `0001_01_01_000000…000002` (users, cache, jobs), `…000464_add_missing_foreign_key_indexes`, `…000463_normalize_media_asset_storage_path_to_relative` | Daten | Laravel-Basis, FK-Indizes, relative Medienpfade | abstract | FK-Indizes und relative Pfade sind neutrale Regeln; Laravel-Basistabellen entfallen | Indexregel je FK; relative Speicherpfade | R1-Free | R0-06, R0-16 | – |
| INV-E-147 | Inhaltskorrektur-Migrationen (z. B. `…000477_fix_skn_question_8…`, `…000482`, `…000486`–`…000492`, `…000562`–`…000572`, `…000465`, `…000474_verify_ubi…`, `…000477_verify_sbf…`, `…000561`, `…000564`) | Daten | Text-/Zuordnungskorrekturen am Fachkatalog, Regelwerksverifikation | exclude_domain_specific | Fachdaten | – | exclude | – | – |
| INV-E-148 | Concern `HasCompositePrimaryKey` | PHP | Zusammengesetzte Primärschlüssel in Eloquent | replace | ORM-spezifisch; CC nutzt `$wpdb`/dbDelta mit eigenem Schlüsselkonzept | Schlüsselstrategie im Datenmodell-ADR | R1-Free | R0-06 | – |

### 2.10 Zusammenfassung Klassifikation

| Klassifikation | Anzahl |
|---|---:|
| reuse | 22 |
| abstract | 65 |
| replace | 14 |
| exclude_domain_specific | 47 |
| **gesamt** | **148** |

(Gezählt über die Spalte „Klassifikation“ der Tabellen 2.1–2.9; Kommando in Kap. 7, Nachweis.)

---

## 3. Content-Paketformat

### 3.1 Aufbau und Manifest

Quelle: `schema/README.md`, `schema/v1/manifest.schema.json`, `tools/content-build/src/PackageWriter.php`.

```text
<package_id>/
  manifest.json
  data/<entität>.json   (15 Dateien, PackageWriter::ENTITY_FILES)
  media/…
<package_id>.zip        (gleiche Struktur)
```

| Manifestfeld | Bedeutung | Bewertung für Course Captain |
|---|---|---|
| `package_id` | `boatingschool-<commit7>-<content12>`; identischer Inhalt → gleiche ID | Prinzip übernehmen (inhaltsadressiert), Präfix neutral |
| `schema_version` (`1.x.y`), `converter_version` | Format- und Werkzeugversion (aktuell `1.4.0`/`0.3.0`, `PackageBuilder.php`) | übernehmen |
| `source` (`repository`, `branch`, `commit_sha` 40 hex, `commit_date`) | Herkunft | übernehmen, aber auch für Nicht-Git-Quellen erlauben (Autor, Werkzeug) |
| `content_sha256`, `files[]` (`path`, `size`, `mime_type`, `sha256`) | Integrität jeder Datei | übernehmen |
| `previous_package_id` | Vorgänger für Diff (`--previous`) | übernehmen |
| `languages`, `catalogs[]` | Sprache; Fragenkataloge mit Version | `languages` übernehmen; `catalogs` → neutrales „Fragenpool/Version“ |
| `counts` | Zähler je Entität | übernehmen |
| `warnings[]` (`code`, `entity`, `source_id`, `message`), `skipped[]` (`entity`, `source_file`, `line`, `source_id`, `reason`), `not_converted[]` | Validierungsbericht | übernehmen; Basis für Dry-run-Bericht R0-38 |

### 3.2 Übergreifende Regeln (aus `schema/README.md`)

| Regel | neutral? | Bemerkung |
|---|---|---|
| Stabile `source_id` je Entität, über Neubauten gleich | ja | Kern für revisionsfesten Import (LB-09) |
| `hash` = `sha256:` + SHA-256 des Datensatz-JSON ohne `hash`; gleicher Hash = unverändert | ja | setzt kanonisches JSON voraus (INV-E-026) |
| Revisionen entstehen erst beim Import (neuer Hash → neue Revision, alte bleibt für Versuche) | ja | Grundlage für Prüfungs-Snapshots (R0-23) |
| `editorial_status` (draft/review/approved/published/deprecated) | ja | Veröffentlichungsfreigabe R0-22 |
| `rights_status` (unknown/review/licensed/public/restricted) entscheidet über öffentliche Auslieferung | ja | R0-10, R0-22 („nicht lizenzierte Medien“ als Publish-Blocker) |
| Minor-Versionen additiv, fehlende Dateien = leer | ja | INV-E-020 |
| Medienpfad `^media/…` ohne `..` | ja | Traversal-Schutz schon im Schema; ZIP-Prüfung beim Import zusätzlich nötig (Teil C, `ImportService`) |
| ID-Schemata `SBF-SEE-0001`, `<Kurs>#<Nr>`, `<Kurs>@<Version>`, `NAV-01`, `elwis/<datei>`, `TXT-SRC-01`, `VOC-…` | nein | durch neutrale ID-Regeln ersetzen |
| `$id`-Domain `schema.learnboating.invalid` | nein | Legacy-Name; in CC verboten (Konzept 26.1) |

### 3.3 Entitätstypen

| Entität (`data/…json`) | ab Version | fachneutral? | Klassifikation | Ziel in Course Captain |
|---|---|---|---|---|
| `courses` | 1.0 | ja (ohne `combo`/`addon`-Semantik) | abstract | Kurs |
| `modules` | 1.0 | ja | reuse | Modul/Abschnitt mit Fragenpool |
| `questions` (inkl. Revision, Antworten, Medienbezug) | 1.0 | ja für `single_choice`, `multi_choice`, `open_text`; nein für `chart_task` | abstract | Frage + Revision + Fragetyp-Provider |
| `media` | 1.0 | ja | abstract | Medium mit Rechten/Provenienz |
| `exam_rules` (inkl. Blueprints) | 1.0 | ja (Struktur); amtliche Werte nein | abstract | Prüfungs-Blueprint |
| `exam_papers` | 1.0 | ja (Struktur) | abstract | feste Prüfungsform |
| `praxis_tasks` | 1.0 | ja als Karteikarte; Kategorien/`exam_reference` nein | abstract | Karteikarte |
| `video_modules` (Lektionen, Schritte, Quiz) | 1.1 | ja | abstract | Lektion/Activity |
| `navigation_tasks` (+ `answer_values` 1.4) | 1.0/1.4 | nein (Seekarte) | exclude_domain_specific | – |
| `radio_texts`, `radio_vocabulary` | 1.1 | nein (Funk) | exclude_domain_specific | – (Vokabeln ggf. als Karteikarten neu anlegen) |
| `radio_practice` | 1.2 | nein (Funkdialoge) | exclude_domain_specific | – |
| `device_simulation` | 1.2 | nein (Gerätesimulation) | exclude_domain_specific | – |
| `exam_committees` | 1.3 | nein (Prüfungsausschüsse) | exclude_domain_specific | – |
| `exam_guides` | 1.3 | nein (Prüfungsanmeldung) | exclude_domain_specific | – |

**Ergebnis für R0-10:** Das LB-Format v1 ist eine gute Vorlage für ein neues „Course-Content-Package v1“ mit acht neutralen Entitäten (Kurs, Modul, Frage/Revision/Antwort, Medium, Blueprint, feste Prüfungsform, Karteikarte, Lektion) sowie dem Manifest. Sieben Entitäten fallen weg. Course Captain braucht dafür ein eigenes Schema mit neutralem `$id`, neutralen ID-Regeln und ohne `catalogs`/`official_number`/`content_role`. Eine Importkompatibilität zu LB-Paketen ist eine eigene Entscheidung (D-E-05).

**Konverter:** Nur die Grundbausteine sind neutral (`PackageWriter`, `Report`, `Json`, `Text`, `Csv`, `XlsxReader`, `SourceInfo`). `PackageBuilder` und alle `Concerns/` bilden die Laravel-Seeder der Bootsquelle nach und entfallen.

---

## 4. Build, Release, QA (Input für R0-05, R0-18, R0-35)

### 4.1 Release und ZIP-Build

| Baustein | Quelle | Verhalten | Übernahme |
|---|---|---|---|
| ZIP-Build aus Commit | `tools/release/build-zips.sh` | `git archive` eines Commits (nie Arbeitskopie), Entwicklerdateien entfernen, reproduzierbar, `SHA256SUMS`, `versions.env`, Release-Notes aus CHANGELOG | abstract (INV-E-043) |
| Versionsgleichheit | ebd. | Kopf `Version:` = `Plugin::VERSION` = `readme.txt` `Stable tag:` = oberster CHANGELOG-Eintrag | reuse als Regel |
| „Geändert, aber gleiche Version“ | ebd. `--tag` | vergleicht Ordner mit vorigem Tag | reuse |
| ZIP-Negativprüfung | ebd. `check_zip` | verbotene Dateien, genau ein Ordner, Hauptdatei | reuse, erweitern um Legacy-Namen (Konzept 26.3 Schritt 6) |
| Minify | ebd. + `release.yml` (esbuild 0.24.2) | `app.js`/`app.css` im ZIP minifiziert, Repo lesbar | für WordPress.org nur mit beigelegter oder verlinkter lesbarer Quelle (Kap. 6) |
| Release-Gates | `release.yml` | CI des Commits grün, Tag auf `main`, `sha256sum -c` | reuse; WordPress.org-SVN-Deploy fehlt |
| Release-Regel | `docs/release-regel.md` | SemVer-Regeln (PATCH ohne DB-Änderung, MINOR mit Schema, MAJOR bei Vertragsbruch), Sicherheitsupdate-Ablauf, Downgrade nur mit Backup | abstract |
| Update-Probe | `tools/release/upgrade-check.php`, CI `upgrade` | Daten vor/nach Update, Migration ohne Reaktivierung | reuse |

**Lücken gegenüber R0-18:** kein SAST außer PHPCS-Sicherheitsregeln, kein Dependency-Scan, keine Signatur, keine JS-Lints/-Unit-Tests für die Plugin-Assets in der CI (Quelltests der Gerätesimulation laufen nicht in LB; `sync-device-sim.sh` schließt `*.test.js` aus). → INV-E-097.

### 4.2 i18n und QA

| Werkzeug | Kommando | Übernahme |
|---|---|---|
| Text-Domain-Lint | `php tools/i18n/check-domains.php` | reuse (Domains `course-captain`, `course-captain-pro`) |
| POT-Erzeugung | `bash tools/i18n/make-pot.sh` (oder `WP="php /pfad/wp-cli.phar" bash tools/i18n/make-pot.sh`); JS: `wp i18n make-json <languages> --no-purge` | abstract |
| Direktaufruf-Schutz | `php tools/qa/check-abspath.php` | reuse |
| PHPStan | `cd bootspruefung-learning && composer install && composer phpstan` | abstract (ohne Baseline) |
| PHPCS (Sicherheit blockierend) | `composer phpcs`; Übersicht `composer phpcs-style` | abstract |
| PHP-Syntax | `find <pfad> -name '*.php' -print0 \| xargs -0 -n1 php -l` | reuse |
| Notice-Gate | `grep -iE "PHP (Warning\|Deprecated\|Notice\|Fatal)" <log>` | reuse |

### 4.3 Test-Runner und Ausführung (Belege: `README.md`, `ci.yml`)

Voraussetzung für alle Integrationstests: WordPress mit `wp-config.php` gegen eine Datenbank, deren Name „test“ enthält (`support/guard.php`), Plugin unter `wp-content/plugins/bootspruefung-learning` (CI: Symlink).

```bash
# Konverter (PHPUnit 10)
cd tools/content-build && composer install && vendor/bin/phpunit
php tools/content-build/bin/build-package.php [--out=build] [--previous=<paket>] [--no-zip] [--source=<snapshot>]

# Golden Master (nur Bootsquelle; Laravel-Kopie mit PostgreSQL 16 frisch geseedet)
BS_DSN="pgsql:host=127.0.0.1;dbname=boatingschool" BS_USER=… BS_PASSWORD=… [BS_LARAVEL_DIR=…] \
  php tools/content-build/golden-master/compare.php build/<package_id>

# Plugin-Integration (Beispiele; vollständige Liste in ci.yml, Job "plugin")
WP_DIR=/pfad/zu/wordpress php bootspruefung-learning/tests/integration/smoke-test.php
WP_DIR=/pfad/zu/wordpress PACKAGE_DIR=build/<package_id> php bootspruefung-learning/tests/integration/content-flow-test.php
WP_DIR=/pfad/zu/wordpress php bootspruefung-learning/tests/integration/woocommerce-setup.php   # vor Woo-Tests
WP_DIR=/pfad/zu/wordpress php bootspruefung-learning/tests/integration/multisite-setup.php && \
  WP_DIR=/pfad/zu/wordpress php bootspruefung-learning/tests/integration/multisite-test.php

# Browser (Playwright 1.48.2, ohne Test-Runner)
npm install --no-save --prefix "$TMP/pw" playwright@1.48.2 && "$TMP/pw/node_modules/.bin/playwright" install --with-deps chromium
WP_DIR=/pfad/zu/wordpress php bootspruefung-learning/tests/e2e/setup.php
cp bootspruefung-learning/tests/e2e/router.php /pfad/zu/wordpress/router.php
(cd /pfad/zu/wordpress && php -S 127.0.0.1:8099 router.php &)
NODE_PATH="$TMP/pw/node_modules" BP_USER=lerner1 BP_PASS=Test-Passwort-123 node bootspruefung-learning/tests/e2e/learning-flow.mjs

# Theme
python3 -m unittest discover -s themes/kurswasser/tools -p 'test_*.py' -v
WP_DIR=/pfad/zu/wordpress php themes/kurswasser/tests/learning-compat-test.php
BP_URL=http://127.0.0.1:8099 BP_SHOTS=/tmp/shots node themes/kurswasser/tests/screenshots.mjs

# Release und Update
tools/release/build-zips.sh [--rev <commit>] [--out <ordner>] [--tag vX.Y.Z]
(cd build/release && sha256sum -c SHA256SUMS)
WP_DIR=… php tools/release/upgrade-check.php before <stand.json>   # mit Vorversion
WP_DIR=… php tools/release/upgrade-check.php after  <stand.json>   # nach Einspielen der neuen ZIP

# Staging
cd staging && cp .env.example .env && docker compose up -d && docker compose logs -f setup
```

### 4.4 Input für R0-05 (Golden Master)

- Die **Methode** der Quelle ist ein Vergleich Feld für Feld gegen ein frisch aufgebautes Referenzsystem (`golden-master/compare.php`), mit dokumentierten, beim Vergleich angewendeten Abweichungen (README: „Golden-Master-Abgleich wendet diese Abweichungen auf die Laravel-Seite an“). Für Course Captain ist das Referenzsystem das LB-Plugin selbst, nicht Laravel.
- Wiederverwendbare neutrale Prüffälle für Fixtures: Schema-Konformität, eindeutige IDs, Referenzauflösung, Prüfsummen, Determinismus, „keine Prüfung durch Ankreuzen aller Optionen bestehbar“ (`testNoExamCanBePassedByTickingEveryOption`), „unveröffentlichte Typen nicht in Prüfungen“ (`testChartTasksAreNotPublishedAndNotInPapers`, als Muster „Entwurf nie in Prüfungsform“).
- Upgrade-Golden-Master: `upgrade-check.php` (Zeilenzahlen je Tabelle vor/nach).
- Für neutrale Fixtures muss ein synthetisches Paket im LB-Format gebaut werden, damit das LB-Plugin Referenzergebnisse ohne Bootsinhalte liefert → D-E-08.

### 4.5 Input für R0-35 (Distribution)

Belegt vorhanden: reproduzierbares ZIP, `SHA256SUMS`, CHANGELOG-Pflicht, `CREDITS.md` im ZIP, Text-Domain-Lint, POT, Direktaufruf-Schutz, Artefakt-Test, Update-Test, Staging. Fehlt für WordPress.org: `readme.txt` mit Lizenzkopf (LB-Plugin hat keinen, `docs/lizenz.md`), `LICENSE`-Datei, SVN-Deploy und Assets (Banner/Icon), Prüfung gegen die Plugin-Richtlinien (lesbarer Code bei Minify, keine eigene Update-Quelle).

---

## 5. Betriebsdokumentation (`docs/`)

„Übertragbar“ = fachneutrale Erkenntnisse, die Course Captain als Vorlage nutzen kann (Inhalt immer neu und neutral formulieren). Spalte „Klass.“ wie in Kap. 2.

| Datei | Inhalt | übertragbar | Klass. | betroffene R0-/R1-Task |
|---|---|---|---|---|
| `admin-bereich.md` | Bedienung der Admin-Seiten: Laufzeit neuer Zugänge, Protokoll, Fehler/Ergebnis/Tempo bei Zugängen und Paketen, Rechte-/Regelwerksfreigabe, Begriffe, Menü und Barrierefreiheit | ja (Muster) | abstract | R0-30, R1-24, R1-38 |
| `anwalt-pruefung.md` | Texte zur Rechtsprüfung (Ablauf-Erinnerung, „Lieferung digital“, Angleichung der Rechtstexte) | nein | exclude_domain_specific | – (Hinweis R0-17) |
| `backup-restore.md` | Was zu sichern ist (Tabellen, Optionen, absolute Pfade in der DB, Geheimnisse im Dump, private Ordner außerhalb Webroot, `wp-config.php`), Rhythmus, Restore an anderen Ort, `wp bootspruefung backup-check`, Übung 13.11.2026 | ja | abstract | **R0-16**, R1-23 |
| `barrierefreiheit-oesterreich.md` | BFSG-Anwendbarkeit, axe-core-4.10-Prüfung (WCAG 2.0/2.1 A/AA, 22 Seitenaufrufe, Desktop/390 px, 0 Verstöße nach Korrektur), Entwurf Barrierefreiheitserklärung, österreichische Rechtstexte | teilweise: Prüfmethode ja, Rechtsfragen nein | abstract | **R0-15**, R0-26, R1-19 |
| `betreiber-schritte.md` | Betreiber-To-dos vor/nach Livegang (Zahlungs-Sandbox, Staging-Abnahme, Upload-Grenze, Rechte freigeben, Seekarte, Produktkatalog, Widerruf, Hero-Fotos, Umzug Laravel, Abos, Prüfungsanmeldung) | nein (Struktur „Was gibt es / Was tun / Woran erkennbar“ ja) | exclude_domain_specific | R1-24 (nur Muster) |
| `betrieb-entscheidungen.md` | Aufbewahrung Paketkopien (aktiv + 2), Importe in Arbeit 30 Tage, Protokoll 14/180 Tage, Audit-Log 4 Jahre, Index auf `created_at` | ja | reuse | **R0-16**, R0-31, R0-38, R1-17 |
| `datenschutz-organisation.md` | Entwurf Verzeichnis der Verarbeitungstätigkeiten (Konto, Bestellung, Lernstand, Prüfung, Gruppen, Erinnerung, App-Sitzungen, Datenschutzanfragen, Rate-Limits, Audit, Partnerprogramm), mit Codebelegen | ja (Gliederung, Datenkategorien) | abstract | **R0-17**, R0-31, R1-17 |
| `fachliche-bestaetigungen.md` | Fachaussagen zu Gebühren, Segel-/SKN-/FKN-Regeln, Mindestalter | nein | exclude_domain_specific | – |
| `glossar.md` | Feste Admin-Begriffe, Regeln für Admin-Texte (im Admin aufklappbar, `Admin/Glossary.php`) | ja (Prinzip) | abstract | R0-30, R1-24, SEAM-004 |
| `inhaltsseiten-entwuerfe.md` | Offene Punkte der 16 Entwurfsseiten | nein | exclude_domain_specific | – |
| `installation-und-updates.md` | Pakete, Kompatibilität, Erstinstallation, Updates, Downgrade, Pakete bauen, CI-Jobs vor Release | ja | abstract | **R0-35**, R0-18, R1-39 |
| `kunden-faq-freigabe.md` | Freigabe von 16 Kunden-FAQs | nein | exclude_domain_specific | – |
| `kursgutscheine.md` | PHP-Vertrag `bootspruefung_voucher_grants_v1`, Regeln (Erstattung, Widerruf), App-Route, Fehlercodes und Nutzertexte, CI-Abnahme | ja (Vertragsform, Fehlercodekatalog) | abstract | R0-40, R3-04, R3-05, SUITE-104 |
| `livegang-drehbuch.md` | Staging → Live: Voraussetzungen, Serverbetrieb, Ablauf mit Echtgeld-Testkauf, Rückfallplan, Nachbereitung | ja (Runbook-Form) | abstract | **R0-16**, R1-39 |
| `lizenz.md` | Lizenzangaben aller Pakete, Herkunft Gerätesimulation, offene Fragen, Änderungsliste | ja (als Befund) | reuse | **R0-01**, **R0-35** (Kap. 6) |
| `mailversand.md` | Welche Mails das System schickt, SPF/DKIM/DMARC-Schritte beim Hoster, Ergebnis festhalten | ja | abstract | R1-21, R0-16 |
| `mobile-api/README.md` | App-API `boatingschool/v1`: Architektur `src/Mobile/` (Bearer-Token, Rotation, Web-Session-Übergabe, Idempotency-Key, Rate-Limiter, Problem-Format), gleiche Services wie Web | ja (Muster); API selbst fachgebunden | abstract | R0-19, R0-34 (Plugin-Code → Teil D) |
| `mobile-api/openapi.yaml` | OpenAPI 3.1, 78 `operationId`, 70 Pfade (Auth, Me, Kurse, Trainer, Versuche, Favoriten, Prüfungen, Videos, Navigation, Praxis, Funk, Tipps, Gutscheine, Gruppen, Export, Dateien); CI validiert mit `openapi-spec-validator` | teilweise: Kurs-/Prüfungs-/Favoriten-/Export-Pfade neutral, Navigation/Funk/Praxis nein | abstract | R0-19, R1-28 |
| `mobile-api/konzept-original.md`, `openapi-concept-original.yaml`, `plugin-concept-original/` (README.txt, PHP 325 Zeilen) | Unterlagen des Auftraggebers zur App-API („nur Referenz“); Konzept-Plugin mit `License: GPLv2 or later` | nein (historisch) | exclude_domain_specific | – |
| `offene-punkte.md` | Stand der T-Nummern, Prüfrunden, Audit-Panels, Staging-Abnahme | teilweise (Audit-Befunde als Prüfliste) | abstract | R0-12, R0-24 |
| `pdfs.md` | PDF-Generator: Aufruf, Arten, Probelauf, Upload und Kurszuordnung | nein (Upload-Weg über Mediathek ja) | exclude_domain_specific | – |
| `preise-pruefung.md` | Rechenhilfe Preise und Gruppenpakete | nein | exclude_domain_specific | – |
| `quellrepo-hinweis-simulator-aria.md` | ARIA-Werte für Drehknöpfe der Gerätesimulation | nein | exclude_domain_specific | – |
| `rechnungen-steuer.md` | Rechnungen, Storno, B2B, E-Rechnung, Schweiz, OSS; Rechnungs-Plugin nötig | nein (Commerce bei Woo) | exclude_domain_specific | – (R3-Kontext) |
| `rechtstexte-platzhalter.md` | 82 Platzhalter der Rechtstext-Vorlagen | nein | exclude_domain_specific | – |
| `release-regel.md` | Versionsschema, Ablauf einer Auslieferung, Migration ohne Reaktivierung (503 „Wartung“ für schreibende Lern-API), Sicherheitsmitteilung mit Opt-in-Versionsprüfung, Downgrade | ja | abstract | **R0-18**, **R0-35**, R0-37, R1-23 |
| `schnittstellen-erweiterungen.md` | Hooks/Filter für Gutschein- und Ticket-Plugins (`bootspruefung_voucher_*`, `bootspruefung_user_owns_course`, `bootspruefung_ticket_*`, `bootspruefung_tickets_for_user` …) | ja (Vertragsform); Namen fachgebunden | abstract | R0-19, R3-01, R3-04, R3-07, LB-11, LB-12 |
| `server-einrichtung.md` | Apache vs. nginx, Versionen, nginx-Regeln für private Ordner | ja | abstract | R0-16, R0-20, R1-14 |
| `sicherheit-entscheidungen.md` | Login-Grenzen (10/IP, 5/Konto+IP, 30/Konto in 15 min, Rückzug bei Sicherheits-Plugins, race-freie Zählung, keine Aufzählung über Antwortzeit), SVG-Import, 2FA, Zahlungskonflikte, geschützte Seiten/Page-Builder, Limits, Ablageordner, Admin-Hinweise | ja | abstract | **R0-17**, R0-31, R1-14, R1-20 |
| `steuer-fragen.md` | Fragen an die Steuerberatung | nein | exclude_domain_specific | – |
| `support-ablaeufe.md` | Grundregeln (Identität nur über Konto-Adresse, nie Passwörter/Ausweis anfordern, Zusagen als Bestellnotiz), Abläufe Widerruf, Garantie, Datenschutzanfragen, Zugangsprobleme, Login-Sperre | teilweise: Grundregeln, Datenschutz, Zugang, Login-Sperre ja; Widerruf/Garantie nein | abstract | **R0-13**, R1-24 |
| `theme-livegang.md` | Kurswasser als Live-Theme, Inhaltsseiten bei Theme-Updates, Rückweg | nein (Theme entfällt) | exclude_domain_specific | – |
| `uebersetzung.md` | Übersetzbarkeit Plugin/Theme/Add-ons, Ausgangssprache de_DE, de_AT/de_CH nur über Dateien, Vorlagen aktualisieren | ja | reuse | **R0-15**, R1-19 |
| `videos-untertitel.md` | 449 Platzhaltervideos, echte Videos und Untertitel bereitstellen | teilweise (Untertitelpflicht ja) | abstract | R0-15, R1-07 |

Zählung: 31 Markdown-Dateien im Ordner `docs/` plus 6 Dateien in `docs/mobile-api/` = 37.

---

## 6. Rechte und Lizenzen

Quellen: `CREDITS.md` (Stand 13.11.2026), `docs/lizenz.md` (To-do T93), Dateiköpfe. Keine Rechtsberatung.

### 6.1 Befund je Bestandteil in Teil E

| Bestandteil | Lizenz laut Repository | Beleg | Folge für Course Captain |
|---|---|---|---|
| Theme `kurswasser` (Code) | GPL-2.0-or-later | `themes/kurswasser/style.css` | Code darf unter GPL weiterverwendet werden; Muster aus `inc/setup.php`, `inc/seo.php`, `inc/consent.php` sind nutzbar. Course Captain übernimmt aber ohnehin kein Theme |
| Rechtstext-Vorlagen `themes/kurswasser/legal/` | Teil des GPL-Themes; inhaltlich vom Anwalt zu prüfen | `docs/anwalt-pruefung.md` | nicht übernehmen (INV-E-069) |
| Schrift Manrope | SIL OFL 1.1 | `assets/fonts/OFL-Manrope.txt` | nicht benötigt |
| Kursfotos (KI, OpenAI) | Nutzungsbedingungen von OpenAI, keine Exklusivität | `assets/images/courses/IMAGE-RIGHTS.md` | nicht übernehmen |
| Hero-Foto, 19 Icons | **Herkunft nicht belegt** | `CREDITS.md` | nicht übernehmen |
| Lern-Plugin, Werkzeuge (`tools/`), Schemas, Staging, Doku, Tests, CI | **keine Lizenzangabe**; `tools/content-build/composer.json` und Plugin-`composer.json`: `"license": "proprietary"`; keine `LICENSE`-Datei im Repository | `docs/lizenz.md` | Ohne Lizenz sind alle Rechte vorbehalten. Code (z. B. `build-zips.sh`, `check-domains.php`, `PackageWriter.php`) darf erst nach Freigabe durch den Urheber kopiert werden → D-E-01 / E-01 |
| Content-Snapshot `content-source/boatingschool/` | **ungeklärt** (Quelle ohne LICENSE; `composer.json` der Quelle „MIT“ aus der Laravel-Vorlage) | `docs/lizenz.md`, `SOURCE.md` | nicht übernehmen, auch nicht als Testfixture in einem öffentlichen Repo |
| Gerätesimulation (JS) | **ungeklärt** | `CREDITS.md`, `docs/lizenz.md` | nicht übernehmen |
| Bundesländer-Umrisse (Natural Earth) | gemeinfrei | `CREDITS.md` | nicht benötigt |
| Produktbilder (Pillow, DejaVu) | DejaVu: Bitstream-Vera-Lizenz mit Ergänzungen | `CREDITS.md` | nicht benötigt |
| `docs/mobile-api/plugin-concept-original/` | `License: GPLv2 or later` (README.txt) | Datei | historisch, nicht benötigt |
| Entwicklungswerkzeuge (PHPUnit, opis/json-schema, PHPStan, WPCS, PHPCSUtils, Playwright, Tailwind, openapi-spec-validator) | BSD-3-Clause, MIT, Apache-2.0, LGPL-3.0-or-later | `CREDITS.md`, Lock-Dateien | Nur zur Entwicklung, nicht im Paket; keine Lizenzlast für das ZIP |

### 6.2 Bedeutung für eine GPL-Veröffentlichung auf WordPress.org

1. **Course-Captain-Code muss GPL-kompatibel sein** (Konzept Kap. 1: Free als eigenständiges Plugin für WordPress.org; Kap. 6: Slug `course-captain`). Aus Teil E kann bis zur Freigabe des Urhebers nur GPL-Theme-Code sicher wiederverwendet werden. Alles andere ist als Spezifikation zu lesen und neu zu schreiben (Clean-Room), oder der Urheber gibt es frei.
2. **Urheber:** Plugin-Kopf `Author: hastla007`, Theme-Kopf `Theme URI: https://github.com/hastla007/learnboating`; Course Captain liegt laut R0-01 ebenfalls unter `hastla007`. Eine Freigabe ist daher voraussichtlich einfach, muss aber schriftlich im Repository stehen (z. B. Erklärung in `docs/r0/` und `CREDITS`). Ob weitere Beitragende Rechte haben, ist im Repository nicht belegt.
3. **Keine Inhalte, Bilder, Schriften oder die Gerätesimulation** aus LB in Course-Captain-Pakete; das deckt sich mit Konzept 26.1/26.4 und vermeidet alle ungeklärten Rechte.
4. **Minifizierte Assets:** LB minifiziert `app.js`/`app.css` nur im ZIP. Für WordPress.org muss lesbarer Quellcode im Paket liegen oder öffentlich verlinkt sein (Plugin-Richtlinien; vor R0-35 gegen die aktuelle Fassung prüfen).
5. **Update- und Versionsprüfung:** LB fragt optional `api.github.com` nach neuen Releases (`docs/release-regel.md`, Standard aus). Ein WordPress.org-Plugin bekommt Updates über WordPress.org; eine eigene Update-Quelle ist dort nicht vorgesehen, externe Abfragen brauchen ausdrückliches Opt-in. Für Free also ersetzen (keine eigene Abfrage), für Pro über License Captain (Konzept Kap. 6).
6. **Lizenzdateien:** Pflicht sind `License:`/`License URI:` im Plugin-Kopf und in `readme.txt` sowie eine `LICENSE`-Datei. Das `CREDITS.md`-Muster (INV-E-116) wird übernommen, auch wenn es für das Free-ZIP voraussichtlich keine Fremdbestandteile listet.
7. **Legacy-Namen:** `learnboating`, `bootspruefung`, `boatingschool`, `bp_`, `kurswasser`, `schema.learnboating.invalid` dürfen nur in R0-/Herkunftsdokumenten stehen (Konzept 26.1). Die ZIP-Prüfung (INV-E-044) wird dafür um eine Negativliste erweitert.

---

## 7. Entscheidungsbedarf

Nur Punkte, die der Auftraggeber entscheiden muss. Technische Details, die die Konzept-Kapitel schon regeln, stehen nicht hier.

| ID | Frage | Optionen | Empfehlung | blockiert |
|---|---|---|---|---|
| D-E-01 | Darf Code aus learnboating (ohne Lizenzangabe bzw. `proprietary`), z. B. `tools/release/build-zips.sh`, `tools/i18n/check-domains.php`, `tools/qa/check-abspath.php`, `PackageWriter.php`, Testsupport, in Course Captain unter GPL-2.0-or-later übernommen werden? (Teil-E-Ausprägung von E-01 aus R0-01) | a) Urheber gibt Plugin, Werkzeuge, Tests und CI schriftlich unter GPL-2.0-or-later frei · b) nur Verhalten übernehmen, Code neu schreiben | **a)**, als datierte Erklärung des Urhebers im Repository; Inhalte, Bilder und Gerätesimulation ausdrücklich ausgenommen | R0-35, Code-Wiederverwendung in R1 |
| D-E-02 | Test-Runner für Course Captain | a) LB-Muster weiterführen (PHP-Skripte gegen echte DB, Playwright ohne Runner) · b) PHPUnit mit WordPress-Testsuite plus `@playwright/test`, LB-Prüffälle als Szenarien | **b)**, dabei Test-DB-Guard, Prozessisolation, Notice-Gate und Fremdhost-Erfassung aus LB übernehmen (INV-E-098, -099, -088, -111) | R0-18, R1-18 |
| D-E-03 | Unterstützte Plattformen als Startwert für R0-20 | LB-Matrix übernehmen (Ziel PHP 8.5/WP 7.1.4/MariaDB 12.3.3; Untergrenze PHP 8.1/WP 7.0.0/MariaDB 10.11; MySQL 8.4 informativ) oder eigene Werte | LB-Matrix als **Startwert** für die CI-Matrix übernehmen, WooCommerce-Achse erst ab R3; endgültige Werte in R0-20 beschließen | R0-20, R0-18 |
| D-E-04 | Einwilligung für externe Medien in Free, wenn kein Consent-Plugin aktiv ist | a) immer „Klick zum Laden“ · b) eigenes Banner · c) ohne Schutz laden | **a)** als Standard; vorhandenes Consent-Plugin optional anbinden; kein eigenes Banner (INV-E-059) | R0-31, R1-34 |
| D-E-05 | Soll Course Captain Pakete im LB-Format v1 (`boatingschool-*`) importieren können (Umzugspfad für bestehende LB-Betreiber)? | a) nein, nur eigenes Format · b) separates Migrationswerkzeug außerhalb des WordPress.org-Pakets · c) Importer im Core | **b)**, wenn ein Umzug gewünscht ist, sonst a); nie im Core (Legacy-Namen, Fachentitäten, Konzept 26.1) | R0-10, R1-15 |
| D-E-06 | Liefert Course Captain Rechtstext- oder Betreibervorlagen (wie `themes/kurswasser/legal/`)? | a) nein, nur Datenschutz-Textbausteine über WordPress-Bordmittel · b) neutrale Vorlagen | **a)**; Rechtstexte sind rechtsraum- und betreiberspezifisch (INV-E-069) | R0-17, R1-17 |
| D-E-07 | Werden Staging (Docker) und Release-Pipeline als Teil des Course-Captain-Repos geführt, inkl. WordPress.org-SVN-Deploy aus der CI? | a) ja, Staging + CI-Deploy · b) Staging ja, Deploy manuell · c) beides manuell | **a)**, mit den LB-Gates „CI grün“ und „Tag auf main“ (INV-E-096) und manueller Freigabe vor dem SVN-Schritt | R0-16, R0-18, R0-35 |
| D-E-08 | Darf das LB-Plugin mit **synthetischen neutralen** Paketen (LB-Format, keine Bootsinhalte) als Referenzsystem für Golden-Master-Fixtures laufen? Oder auch mit echten LB-Inhalten, nur intern? | a) nur synthetisch · b) auch echte Inhalte intern, nicht im Repo | **a)**; echte Inhalte haben ungeklärte Rechte (`CREDITS.md`) und dürfen nicht in Fixtures oder öffentliche CI-Logs gelangen | R0-05, R1-18 |

---

## Nachweis

- Quellcommit: `git -C <lb> log -1 --format=%H` → `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd`.
- Zählung: `find <pfad> -type f | wc -l`; Textzeilen `find <pfad> -type f -print0 | xargs -0 grep -Il . | xargs cat | wc -l`; PHP-Zeilen `find <pfad> -name '*.php' -print0 | xargs -0 cat | wc -l`.
- Schemagleichheit: `diff -rq schema bootspruefung-learning/schema` (nur `README.md`).
- Tabellen der Quelle: `grep -ho "Schema::create('[a-z_]*'" content-source/boatingschool/database/migrations/*.php | sort -u` (69 Tabellen).
- API-Umfang: `grep -c operationId docs/mobile-api/openapi.yaml` (78); Pfade `grep -E '^  /' …` (70).
- Klassifikationszählung: `grep -E '^\| INV-E-' E-theme-tools-content-docs.md | awk -F' \\| ' '{print $5}' | sort | uniq -c` → reuse 22, abstract 65, replace 14, exclude_domain_specific 47.
- Gelesen: alle Dateien in `tools/` (Kopf und Funktionssignaturen, Release-/QA-/i18n-Skripte vollständig), alle 17 Schemas (Pflichtfelder/Enums/Muster), `staging/` vollständig, Theme-README vollständig und alle `inc/`-Funktionslisten, Testsupport vollständig, `ci.yml`/`release.yml` vollständig, `CREDITS.md`, `docs/lizenz.md`, Root-`README.md` vollständig, Überschriften und Einleitung aller 37 `docs/`-Dateien, Abschnitte aus `backup-restore.md`, `release-regel.md`, `installation-und-updates.md`, `support-ablaeufe.md`, `sicherheit-entscheidungen.md`, `betrieb-entscheidungen.md`, `barrierefreiheit-oesterreich.md`. `content-source/` strukturell (Dateilisten, Tabellen, `SOURCE.md`), nicht zeilenweise.
- learnboating wurde nicht verändert; in Course Captain wurde nur diese Datei angelegt.
