# R1-18 · GM-Nachtrag Gruppe g6 – Beschlüsse E-331 und E-332

**Bezug:** `docs/r0/ENTSCHEIDUNGEN.md` E-331 (Befund F-1) und E-332 (Befund F-2) aus `docs/r1/R1-18-nachweis.md` §5; R0-05 §1.4/§1.8/§2.4/§2.8, R0-08 §1.4/§4.1/§6.3, R0-22 (VAL-075, VAL-092), R0-27 §1.6. Clean-Room: nur Projektdokumente und bestehender Code (E-01, E-327).

Statt Abweichungen wurden beide Beschlüsse nachgebaut. Damit entfallen die Teilabdeckungen (`partial`) von GM-015, GM-029 und GM-082 in `tools/qa/gm-coverage-exceptions.php`; offene (`open`) Einträge hatte die Gruppe nicht.

## 1. E-331 (F-1): Prüfungsteile über Modulgruppen

**Datenvertrag** (`passing_rule.parts`, R0-08 §4.1): Ein Teil hat `sections` (Liste von Blueprint-Modulen, Modulgruppe) **oder** wie bisher `section` (ein Modul), dazu `min_ratio`, optional `key` (Standard `P1`, `P2`, …) und `label`. Bestehende Regeln „ein Teil = ein Modul“ bleiben unverändert gültig. Ungültige Teile (leere oder doppelte Modulliste, `section` und `sections` zugleich, Quote außerhalb 0 < num ≤ den, doppelter Schlüssel) lehnt `ExamRules::create()` mit `ccap_validation_failed` ab; eine so gespeicherte Regel ist nicht startbar.

**Snapshot** (R0-08 §6.3): neu `parts` (Teile mit Modulgruppe und beim Start eingefrorener Bezeichnung; ohne `label` die Modultitel mit „ + “) und `question_parts` (Position → Teilschlüssel; erster passender Teil in Teilreihenfolge). `format_version` bleibt 2 (nur zusätzliche Schlüssel).

**Bewertung:** Teilergebnisse werden über `question_parts` gebildet (Snapshot-Vorrang, GM-031); Snapshots ohne diese Schlüssel (vor E-331) werden wie bisher über die Abschnitte der eingefrorenen Bestehensregel bewertet. Bestanden ⇔ Gesamtquote **und** jeder Teil; leerer Teil nach `empty_part_policy` (Standard `pass`). Das Ergebnis enthält `parts` je Teil (`points`, `max`, `correct`, `total`, `empty`, `passed`); `sections` bleibt.

**Ergebnisanzeige:** `ExamFeedback` gibt `parts` aus (Punkte nur mit `show_score`); der Prüfungsraum (`ExamRoom`) zeigt „Prüfungsteile“ mit „P2 (…): 17 von 23 richtig, nicht bestanden“.

**Validator** (R0-22): VAL-075 (Blocker) für ungültige Teile und für Teile mit Modulen außerhalb des Blueprints, VAL-092 (Warnung) für Blueprint-Module ohne Teil.

**Golden Master:** ER-A hat jetzt P1 (M-A1 ≥ 5/7, alte Form `section`) und P2 (M-A2 + M-A3 ≥ 18/23, Form `sections`) wie R0-05 §2.4.

## 2. E-332 (F-2): eigene Grenze je Art im kostenlosen Teilumfang

**Datenvertrag** (R0-08 §1.4 Nr. 2, R0-06 `ccap_access_rule`): eine `free_limited`-Zeile nennt ihre Art in `limit_type`: `first_questions` (Fragen je Modul; Karteikarten sind Fragen vom Typ `flashcard` und zählen hier), `first_lessons` (Lektionen je Modul), `first_variants` (feste Prüfungsbögen), `first_files` (geschützte Dateien des Kurses). Mehrere Regeln derselben Art: die größte Grenze gilt. Die Grenzen sind Objektfelder am Kurs/Modul ohne globalen Default (R0-27 §1.6); Schreib-API `TrialLimits::set()` (Recht `EDIT_COURSE`), Lesen `TrialLimits::limits()`.

**Gate:** `ContentGate` wertet je Art aus (`module(…, $kind)`, `lesson()` → Lektionen, `question()` → Fragen, `exam()` → Bögen, `file()` → Dateien, neu `card()` für Karteikarten: nur die ersten *n* Karten eines Stapels, gesperrte Karten werden nie ausgeliefert, also ohne Rückseite; Status-Schreiben prüft erneut). Kursraum, Zugangsstatus („Teilumfang“) und Inhaltsschutz akzeptieren eine Regel beliebiger Art.

**Migration bestehender Einzelgrenze:** Altzeilen (`limit_type` `first_items` oder NULL) gelten zur Laufzeit weiter für alle Arten (Rückwärtskompatibilität). Der Online-Backfill `core:0004_access_rule_kind_limits` (`AccessRuleKindsBackfill`, R0-37 §4.4, in `Migrator::core_backfills()`) teilt jede Altzeile in vier Zeilen mit gleichem Wert und Gültigkeitsfenster; die Altzeile wird zur Fragenzeile und behält ihre ID. Natürliche Idempotenz: geteilte Zeilen fallen aus `pending_where()`. Der effektive Zugang ändert sich nicht (im Test vorher/nachher verglichen).

**Golden Master:** AR-01 = Fragen 3, AR-02 = Bögen 2, AR-03 = Lektionen 2, AR-06 = Karteikarten/Fragen 5, AR-07 = Dateien 1 (`NeutralFixture::rule()`), gleichzeitig wirksam.

**Hinweis (keine Abweichung, zur Kenntnis):** E-332 nennt vier Arten; Karteikarten haben keine eigene Art und zählen als Fragen. AR-01 (3 Fragen je Modul) und AR-06 (5 Karten) sind damit dieselbe Art; gleichzeitig gesetzt gilt die größere Zahl für Fragen und Karten. Soll es eine fünfte Art „Karteikarten“ geben, ist das eine neue Auftraggeber-Entscheidung.

## 3. Je GM-ID

| GM-ID | Status | Umgesetzt / getestet | Tests (`tests/integration/GoldenMasterTest.php`, Klasse `@group R1-18`) |
|---|---|---|---|
| GM-029 | `partial` → **Test** | Snapshot `question_parts` {P1: 7, P2: 23}, `parts` mit Modulgruppe, eingefrorene Bezeichnung; ER-A ohne VAL-075/VAL-092; Datenvertrag und Validator | `test_GM_029_parts_over_module_groups`, `test_E331_part_contract_and_validator` (+ bestehend `test_GM_028_029_er_a`) |
| GM-030 | Test (erweitert) | (a) P1 4/7 nicht bestanden, P2 23/23 bestanden → nicht bestanden; (b) 24/30 ≥ 23/30, P1 7/7, **P2 17/23 < 18 → nicht bestanden**; Lernenden-Ergebnis und Prüfungsraum zeigen die Teile | `test_GM_030_part_p2_over_module_group` (+ bestehend `test_GM_030`) |
| GM-031 | Test (erweitert) | Snapshot alle → P2: P1 `total=0, empty=true, passed=true`, P2 27/30 bestanden, gesamt bestanden; Altsnapshot ohne `parts`/`question_parts` wird über die Abschnitte bewertet | `test_GM_031_snapshot_parts_take_precedence` (+ bestehend `test_GM_031`) |
| GM-082 | `partial` → **Test** | AR-01/02/03 gleichzeitig (3/2/2): ohne Regel der Art kein Zugriff, 3 Fragen je Modul, Bögen 1–2 frei, Bogen 3 und Zufallsprüfung gesperrt, Start abgewiesen, 2 Lektionen; G-3 hebt alles auf (`limit=null`); Migration der Einzelgrenze | `test_GM_082` (an E-332 angepasst, s. u.), `test_E332_GM_082_015_062_kind_limits_at_once`, `test_E332_legacy_single_limit_migration` |
| GM-015 | `partial` → **Test** | AR-07 (1 Datei) gleichzeitig mit AR-02/03/06: 3 gelistet, 1 frei (vorher galt die größte Grenze 5 für alle) | `test_E332_GM_082_015_062_kind_limits_at_once` (+ bestehend `test_GM_015_downloads`) |
| GM-062 | Test (erweitert) | AR-06: Karten 1–5 von FC-A frei mit Rückseite, Karte 6 gesperrt; Status für eine nach Senken der Grenze gesperrte Karte abgewiesen (`ccap_content_locked`), andere Arten bleiben | `test_E332_GM_082_015_062_kind_limits_at_once` |

**Angepasster Bestandstest:** `test_GM_082` prüfte bisher das alte Verhalten „AR-02 allein öffnet auch 2 Fragen und 2 Lektionen“ (eine Grenze für alle Arten). Nach E-332 ist das falsch; der Test prüft jetzt den R0-05-Fall AR-01/02/03 gleichzeitig mit mehr Zusicherungen (nicht abgeschwächt). Das alte Verhalten bleibt für Altzeilen `first_items` abgedeckt (`test_E332_legacy_single_limit_migration`, `ContentGateTest::test_partial_access`, `LearnerTest`).

## 4. Dateien

- neu: `src/Access/TrialLimits.php`, `src/Access/AccessRuleKindsBackfill.php`, dieses Dokument
- geändert: `src/Access/ContentGate.php`, `src/Access/ContentProtection.php`, `src/Learner/LearnerQueries.php`, `src/Question/QuestionPresenter.php`, `src/Storage/Migrator.php`, `src/Exam/ExamRules.php`, `src/Exam/ExamSessions.php`, `src/Exam/ExamFeedback.php`, `src/Learner/ExamRoom.php`, `src/Publish/CourseValidator.php`
- Tests/Fixture: `tests/integration/GoldenMasterTest.php`, `tests/integration/SchemaTest.php` (Schrittliste), `tests/support/Fixtures/NeutralFixture.php` (ER-A mit P2, AR-Regeln je Art)
- QA/Doku: `tools/qa/gm-coverage-exceptions.php` (GM-015, GM-029, GM-082 aus `partial` entfernt), `docs/r1/R1-18-nachweis.md` (F-1/F-2 behoben, Matrixzeilen), `docs/r0/R0-08…` (§1.4, §4.1, §6.3, Nachtrag), `docs/r0/R0-22…` (Nachtrag VAL-075/092), `docs/r0/R0-27…` (§1.6)
- i18n: `languages/course-captain.pot` neu erzeugt (enthält auch bisher fehlende Zeichenketten aus `LessonView`/`LearningArea`), `languages/course-captain-de_DE.po/.mo` um die neuen Zeichenketten ergänzt

## 5. Kommandos und Ergebnisse (23.11.2026, PHP 8.5, DB `ccap_test_g6`)

| Kommando | Ergebnis |
|---|---|
| `php8.5 vendor/bin/phpcs -q` | 0 Befunde |
| `php8.5 vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | No errors (Level 8) |
| `php8.5 vendor/bin/phpunit -c phpunit.xml.dist` | OK, 120 Tests, 2 889 Zusicherungen, 2 übersprungen |
| `WP_TESTS_DB_NAME=ccap_test_g6 php8.5 vendor/bin/phpunit -c phpunit-integration.xml.dist` | OK, 429 Tests, 13 595 Zusicherungen, 31 übersprungen (pcntl, läuft in der CI) |
| `WP_TESTS_DB_NAME=ccap_test_g6 php8.5 vendor/bin/phpunit -c phpunit-integration-multisite.xml.dist` | OK, 442 Tests, 19 911 Zusicherungen, 31 übersprungen |
| `… --filter GoldenMasterTest` | OK, 31 Tests (davon 6 neu/angepasst für g6) |
| `php8.5 tests/guards/free-guard.php` | 184 Dateien, 0 Befunde |
| `php8.5 tests/guards/textdomain-lint.php` | 178 Dateien, 0 Probleme |
| `php8.5 tools/qa/gm-coverage.php` | 210 IDs; getestet 171; Ausnahmen 39 (open 36, r2 1, r3 1, deviation 1); Exit 0 |
| `wp i18n make-pot …` (wie CI J-03) | POT aktuell (Diff ohne Creation-Date leer) |

Bestandstest `SchemaTest::test_GM_163_activation_creates_complete_schema` erwartet jetzt zusätzlich den Schritt `core:0004_access_rule_kind_limits` (Aktivierung legt den Backfill-Schritt an).

## 6. Abweichungen

Keine. Freigabebedarf: keiner; zur Kenntnis nur der Hinweis in §2 (Karteikarten als Art „Fragen“). Der Referenzlauf des Originals für GM-029…GM-031 (E-56/E-328) bleibt wie in `R1-18-nachweis.md` §7 eine getrennte Prüfrolle.
