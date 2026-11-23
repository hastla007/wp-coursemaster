# R1-18 · GM-Nachtrag Gruppe g1

**Stand:** 23.11.2026 · **Basis:** `claude/blissful-curie-tuuf7q` @ `1322ec4` · **GM-IDs:** GM-006, GM-010, GM-068, GM-074, GM-075, GM-078, GM-079
**Quellen:** R0-05 §1.1, §1.2, §1.6, §1.7, §2; R0-08 §5.1/§5.2; R0-07 RG-15, B-2/B-3, M-12; R0-13 K-11; R0-21 K1g; E-03, E-70, E-138. Clean Room (E-01): kein LearnBoating-Code gelesen.

Alle sieben Einträge sind aus `tools/qa/gm-coverage-exceptions.php` entfernt. Neue Tests: `tests/integration/GoldenMasterLearningTest.php` (Fixture `cc-fixture-neutral-v1`, 5 Tests) und `tests/integration/LearningStagesTest.php` (eigener Kleinkurs mit injizierter Uhr, 2 Tests), beide `@group R1-18` und je GM-ID `@group GM-xxx`.

## Je GM-ID

| GM-ID | Umsetzung | Test |
|---|---|---|
| GM-006 | **Neu** `src/Cache/StructureCache.php`: alle Kurse (jeder Status außer Papierkorb) mit Modulen in Strukturreihenfolge, genau zwei Abfragen, K1g unter der Inhaltsgeneration (keine Zugangsentscheidung im Cache). `invalidate()` erneuert die Generation nach dem Commit; Hooks `save_post`/`before_delete_post` (nur Kurs/Modul, `CacheInvalidation`) und `CourseStructure::assign_module()/remove_module()` (damit auch Import) invalidieren | `test_GM_006_structure_cache`: ≤ 2 Abfragen beim Laden, 0 beim Treffer, gleiche Felder/Reihenfolge wie frisch geladen, C-A…C-D mit Status und Modulreihenfolge; Änderung ohne Hooks bleibt bis `invalidate()` unsichtbar; Speichern eines Moduls und Strukturänderung invalidieren; nach `wp_cache_flush()` neu geladen |
| GM-010 | **Befund F-3 behoben** (`src/Learning/AnswerService.php`): Antworten auf Lektionsquiz-Token laufen immer mit `context = lesson`; danach werden `answered`/`correct`/`total` über die veröffentlichten Inline-Fragen in den Activity-Fortschritt geschrieben (`ActivityProgress::record_evidence`), die Regel des `QuizProvider` schließt die Lektion ab (monoton), die Kursfortschrittsberechnung folgt über den bestehenden Hook | `test_GM_010_lesson_quiz_completes_lesson`: L-A-05-Quiz (Q-A1-010…012), Versuch `context=lesson`, `context_ref=lesson:<id>`, nach der 3. Antwort `completed` (`attempted`), wiederholter Idempotenzschlüssel zählt einmal, Antwort außerhalb der Lektion speist das Quiz nicht |
| GM-068 | **Neu** `src/Learning/LegacyDueBackfill.php` (Backfill-Schritt `core:0100_question_progress_due_at` nach R0-37): `secure`/`mastered` ohne `due_at` behalten die Stufe und bekommen `letzte Antwort + 20 h` bzw. `+ 7 d`; idempotent über das Prädikat. **Nicht** in `Migrator::core_backfills()` eingetragen (siehe Abweichung A-1) | `test_GM_068_legacy_rows_without_due_at`: 2 Altzeilen `mastered` (T0 − 8 d, T0 − 1 d, Rückdatierung nur hier, D-04) → `due_at = last + 7 d`, Stufe bleibt; `mastered=2, percent=100, due=1`; fällige Frage wird mit `review_due` vorgelegt; richtige Antwort zählt (+21 d); `secure` → +20 h, `learning` unverändert; zweiter Lauf 0 Zeilen |
| GM-074 | **Neu** `SmartTrainer::practice()` und REST `GET /courses/{course}/practice?question=…[&module=…]`: Parameter 422 `ccap_invalid_param`, Frage nicht im Kurs 404 `ccap_not_found`, danach Gate der Frage: Teilumfang 403 `ccap_content_locked` (`trial`), Drip 403 (`drip`); Auslieferung mit normalem Lern-Token, die Antwort schreibt denselben Lernstand | `test_GM_074_targeted_practice`: Q-A1-005 für U-L1 (Antwort → `attempts 1`, `learning`, Summary zählt sie); fehlend/`abc`/0/ungültiges bzw. fremdes Modul → 422; Q-B1-001 in C-A → 404; U-L3 mit AR-01: A1-002 frei, A1-005 → 403 `trial`; AR-04: A2-002 → 403 `drip`; REST 422/200/404 |
| GM-075 | `SmartTrainer::summary()` liefert zusätzlich `completed` (= gefestigte Fragen); sonst unverändert | `test_GM_075_stage_counts`: 10 Fragen 4/1/2/2/1 → Summe 10, `secure=2`, `secure_percent=20`, `due=1`, `completed=1` (`percent=10`); Stufen ausschließlich über Antworten mit Uhr erzeugt |
| GM-078 | **Neu** `src/Progress/ProgressReset.php` (R0-13 K-11): löscht in einer Transaktion Kurs-, Activity- und Fragenfortschritt (Fragen der Modulpools und Inline-Fragen der Kurslektionen) eines Lernenden für einen Kurs; Prüfungssitzungen, Versuche und Zertifikate bleiben; Reset-Journal mit Vorzustand (Undo E-138 folgt mit R1-38); Audit `progress_reset` (Umfang, Zeilen, Begründung); Recht `ccap_manage_learner_progress` mit Pflichtbegründung, Selbstreset ohne Begründung | `test_GM_078_progress_reset`: U-L1 mit `secure`/`learning`, abgeschlossener Lektion und ausgewerteter Prüfung (EP-A-1) → 0 Fortschrittszeilen C-A, Prüfungshistorie und Versuche erhalten, anderer Lernender unberührt, Audit/Journal geprüft; ohne Begründung 422, U-TUT 403, unbekannter Kurs 404; Selbstreset ohne Begründung |
| GM-079 | **Neu** `src/Progress/CourseReport.php` und REST `GET /reports/courses/{course}` (R0-07 RG-15): je Lernendem mit Fortschritt im Kurs eine Zeile mit Stufenzählung (wie GM-075) und Kursfortschritt, ohne E-Mail; Recht `ccap_view_reports`, Kursverwaltung/Admin alle, Trainer nur zugewiesene Kurse (B-2) bzw. Gruppen (B-3), sonst 403 `ccap_forbidden` | `test_GM_079_course_report`: C-A mit U-L1, U-L2, U-L3 → 3 Zeilen, Stufen = Summary je Person, Summe = Gesamtzahl, U-L3 nur Freiumfang (9); U-TUT sieht C-A, C-B 403; Lernende 403; REST 200/403 |

Weitere Dateien: `src/Learner/LearnerRoutes.php` (zwei Routen), `src/Ops/AuditView.php` (Aktion `progress_reset`, Bereich `learner`), `src/Activity/ActivityProgress.php` (Docblock), `docs/api/routes.v1.json` + `docs/API-CHANGELOG.md`, `languages/course-captain.pot` (neu erzeugt), `languages/course-captain-de_DE.po/.mo` („Lernfortschritt zurückgesetzt“).

## Abweichungen / Freigabe nötig

| Nr. | Thema | Stand |
|---|---|---|
| A-1 | GM-068: R0-08 §5.2 portiert den Altbestandszweig nur, „falls ein Migrationswerkzeug (E-03) ihn braucht“. Der Backfill-Schritt existiert und ist getestet, ist aber **nicht** in `Migrator::core_backfills()` registriert (Course Captain schreibt solche Zeilen selbst nie; eine Core-Registrierung würde jede Installation in die Backfill-Phase W2 bringen). | Entscheidung nötig: Core-Registrierung oder nur Migrationswerkzeug |
| A-2 | GM-074: R0-05 nennt die Quellcodes `partial_access`/`scheduled_release`; Course Captain antwortet einheitlich 403 `ccap_content_locked` mit Grund `trial`/`drip` (R0-08 §1.4, neutrale Codes). | Abbildung, keine Verhaltensabweichung |
| A-3 | GM-010: L-A-05 ist laut R0-05 §2.2 „Video + Quiz“; eine Lektion trägt in Course Captain genau eine Activity. Der Test legt das Quiz (Q-A1-010…012) als eigene Lektion in einem optionalen Modul LM-QZ an (Nenner GM-009 bleibt 8). | Abbildung; Fixture-Beschreibung ggf. anpassen |
| A-4 | GM-075: `completed` ist im Aggregat neu und gleich `mastered`. | Feldname aus R0-05 übernommen |
| A-5 | GM-078: Fragenfortschritt ist je Frage, nicht je Kurs gespeichert; geteilte Fragen anderer Kurse werden beim Kurs-Reset mit zurückgesetzt. Undo aus dem Journal ist R1-38 (E-138). | Hinweis |

## Kommandos und Ergebnisse (23.11.2026)

| Kommando | Ergebnis |
|---|---|
| `php8.5 vendor/bin/phpcs -q` | 0 Befunde |
| `php8.5 vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | 0 Fehler |
| `php8.5 vendor/bin/phpunit -c phpunit.xml.dist` | 120 Tests / 2 891 Assertions, 2 übersprungen |
| `WP_TESTS_DB_NAME=ccap_test_g1 php8.5 vendor/bin/phpunit -c phpunit-integration.xml.dist` | **430 Tests / 19 165 Assertions**, 31 übersprungen (pcntl) |
| `WP_TESTS_DB_NAME=ccap_test_g1 php8.5 vendor/bin/phpunit -c phpunit-integration-multisite.xml.dist` | **443 Tests / 15 435 Assertions**, 31 übersprungen (pcntl) |
| `php8.5 tests/guards/free-guard.php` | 186 Dateien, 0 Befunde |
| `php8.5 tests/guards/textdomain-lint.php` | 180 Dateien, 0 Probleme |
| `php8.5 tools/qa/gm-coverage.php` | 210 IDs, 178 mit Test, 32 Ausnahmen (29 `open`), missing/stale/invalid/unknown 0 |
| `wp i18n make-pot` (ohne Netz mit `--skip-block-json`, Block-Einträge aus der bisherigen POT übernommen) | POT neu erzeugt; enthält auch bisher fehlende Texte aus `LessonView`/`LearningArea` |

Hinweis Arbeitsumgebung: Ein symbolisch verlinktes `vendor/` lädt über den Composer-Autoloader die Klassen des Hauptcheckouts. Im Worktree wurden deshalb `vendor/composer`, `vendor/bin`, `vendor/autoload.php` und `vendor/phpstan` kopiert (nicht eingecheckt).
