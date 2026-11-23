# R1-18 · Golden-Master- und E2E-Abnahme – Nachweis (technischer Teil)

**Stand:** 17.11.2026 · **Branch:** `r1-par-c` · **Status: technischer Teil umgesetzt, Aufgabe nicht abgehakt** (offen: Referenzlauf durch die getrennte Prüfrolle, offene GM-Lücken §5, Review AG-05)
**Eingangskriterium (E-328):** `cc-fixture-neutral-v1` gebaut **und** Referenzlauf des Originals für Rechenlogik-Fälle (E-56) durch eine getrennte Prüfrolle protokolliert. Der erste Teil ist erfüllt, der zweite **nicht** (§6).
**Quellen:** R0-05 §1 (GM-Katalog GM-001…GM-210), §2 (Fixture), §3 (D-03: keine Testreihenfolge), §5.1; R0-12 §3.2/§3.3 (Gates B/C). Clean Room (E-01/E-02): alle Inhalte, Medien und Skripte sind synthetisch und neu; kein LearnBoating-Code, keine LearnBoating-Inhalte.

## 1. Fixture `cc-fixture-neutral-v1` (R0-05 §2)

| Teil | Umsetzung (`tests/support/Fixtures/NeutralFixture.php`) |
|---|---|
| Kurse, Module, Lektionen, Fragen | PKG-v1/PKG-v2 wie bisher (70 veröffentlichte Fragen, Pools 24/16/10/12/8, Themen T-A1-a/b); L-A-04 jetzt mit MED-002/MED-003 (`https://media.fixture.test/cc/alpha-04.mp4`/`.vtt`), L-A-07 mit MED-005 (`https://videos.example.test/cc/alpha-07.mp4`) |
| C-C / C-D | C-C Entwurf mit M-C1 ohne Fragen und Entwurfsregel ER-C (Blueprint 10 > Pool 0, 600 s); C-D archiviert |
| Nutzer §2.7 | zusätzlich U-TUT `cc_fx_tutor` (Rolle `ccap_trainer`, Trainer nur für C-A), U-SM `cc_fx_manager` (`ccap_course_manager`), U-S1…S4 `cc_fx_seat_1…4`; U-L4 registriert T0 − 40 d |
| Prüfungen §2.4 | ER-A (C-A, M-A1 7 / M-A2 13 / M-A3 10, 3 600 s, Kulanz 120 s, ≥ 23/30 und Teil P1 = M-A1 ≥ 5/7, veröffentlicht, nachweisfähig); EP-A-1 (A1-001…007, A2-001…013, A3-001…010), EP-A-2, EP-A-3 (deterministisch, je 30); ER-B (C-B, M-B1 6 / M-B2 4, ohne Zeitlimit, ≥ 7/10, `purpose=practice`); EP-B-1 (Pos. 1 = Q-B1-010) |
| Medien §2.6 | MED-001 PNG (GD, 64×64 `#336699`, „A1-003“), MED-002 MP4 (2 s schwarz, 320×180, H.264, gebaut von `tools/fixtures/build-media.sh`, SHA-256 `2e447110…9caa3`, eingecheckt), MED-003 WebVTT, MED-004 PDF (erzeugt, gültige xref); als Kursmedien registriert (Upload-Ordner `cc-fixture-neutral-v1`) |
| Downloads | DL-A-1 `uebersicht`, DL-A-2 `arbeitsblatt`, DL-A-3 `Übersicht "A"` (MED-004-Kopien), in dieser Reihenfolge an C-A |
| Seiten | PAGE-1 ganz geschützt für C-A (`_ccap_protected_course`), PAGE-2 mit Absatzblock und geschütztem Shortcode-Abschnitt |
| Grants, Gruppen §2.8 | G-1, G-2, G-7 wie bisher; G-10 (U-S1, C-A `view`, bis T0 + 60 d, über `GrantService::grant_manual`); GRP-1 (Verwalter U-GO, C-A `view,exam`, 3 Plätze, 365 d), GRP-2 (U-ADM, C-B, 5 Plätze, 90 d) über `GroupService::create`; deterministische Request-Keys `NeutralFixture::uuid()` |
| Bedarfsschritte | `rule('AR-02'…'AR-08')`, `grant('G-3')`, `flashcards()` (FC-A-01…08 Modus Selbsteinschätzung, FC-B-01…05 Modus Serie/Schwelle 3, im optionalen Modul LM-FC, damit der Nenner von GM-009 bei 8 bleibt), `bad_media()` (MED-BAD-1…6). Sie ändern den Zugang der Basisnutzer und laufen deshalb in der Transaktion des jeweiligen Tests (D-03) |

Der Loader bleibt idempotent (`test_fixture_counts_and_idempotence` vergleicht zwei Läufe) und nutzt die Dienste (ExamRules, MediaLibrary, GrantService, GroupService, ActivityService, QuestionRepository, CourseStructure), weil der Import (R1-41) keine Medien, Blueprints oder Karteikarten übernimmt.

## 2. GM-Suite auf der Fixture (`tests/integration/GoldenMasterTest.php`, 25 Tests)

Neu: Fixture-Erweiterung; GM-002 (Gast 401 `ccap_auth_required`); GM-005; GM-015 (mit AR-07); GM-028/GM-029 (ER-A: 30 Fragen ohne Lösung, `{M-A1 7, M-A2 13, M-A3 10}`, nachweisfähig); GM-030 (EP-A-1, 27/30, P1 4/7 → nicht bestanden); GM-031 (Snapshot alles → M-A2, P1 leer → bestanden 30/30); GM-033 (ER-B Übung, Entwurf v2 nicht startbar); GM-048/GM-083 (Drip AR-04/AR-05 auf Modul, Kapitel und festen Bogen, +8 d frei, U-L4 ab Registrierung); GM-049/GM-151 (IDOR 404); GM-052 (PKG-v2 während laufender Prüfung: Versuch behält rev1, neuer Versuch rev2); GM-062/GM-063 (FC-A/FC-B); GM-081 (AR-08); GM-082 (AR-02, G-3 hebt auf); GM-118 (Pfad, Lauf ohne Freigabe 409); GM-119 (identisches PKG-v1: 0 Änderungen, 0 neue Revisionen); GM-128 (Upload-Prüfung mit MED-BAD-1…6); GM-132 (reproduzierbarer Paketbau, 0 Dubletten, 0 offene Referenzen); GM-144 (C-C nicht veröffentlichbar, ≥ 2 Blocker). Bestehend: GM-001, GM-004, GM-009, GM-022, GM-086, PKG-v2 (jetzt auch `@group GM-120`, AR-01 als GM-076).

## 3. GM-Traceability (`tools/qa/gm-coverage.php`, `tests/unit/GmCoverageTest.php`)

- Das Skript liest alle GM-IDs aus den Katalogtabellen R0-05 §1 (**210 IDs, GM-001…GM-210**; der Katalog reicht über GM-196 hinaus bis GM-210) und sammelt `@group GM-xxx` aus `tests/` und `tools/qa/e2e/`.
- Jede ID ohne Test braucht einen Eintrag in der versionierten Ausnahmeliste `tools/qa/gm-coverage-exceptions.php` mit Kategorie (`open`, `deviation`, `r2`, `r3`, `human`), Bezug und Begründung. Der Unit-Test schlägt fehl bei fehlenden IDs, veralteten Einträgen (Ausnahme trotz Test, `partial` ohne Test), ungültigen Einträgen und unbekannten IDs.
- Bestehende Tests, die eine GM-Aussage nachweislich prüfen, tragen jetzt die passende `@group` (u. a. ExamTest, ConcurrencyTest, AccessTest, GroupCertificateAdminTest, ImportTest, ImportBatchTest, HardeningTest, PolicyTest, MigrationResumeTest, SchemaTest, Uninstall-/Upgrade-Proben). Wo ein Test die Aussage nur teilweise prüft, steht das unter `partial` und in der Matrix (Anhang).
- Stand: **171 mit Test** (davon 17 teilweise), **39 Ausnahmen**: 36 `open`, 1 `deviation` (GM-180), 1 `r2` (GM-077, App-API E-05), 1 `r3` (GM-160, Pro E-24). `missing 0, stale 0, invalid 0, unknown 0`.
- Ausführen: `php tools/qa/gm-coverage.php` (Exit 1 bei Lücken), Matrix: `php tools/qa/gm-coverage.php --matrix`, einzelne GM: `vendor/bin/phpunit -c phpunit-integration.xml.dist --group GM-030`.

## 4. E2E gegen eine laufende Site (`tools/qa/e2e/live-smoke.sh`)

Machbar und umgesetzt. Das Skript kopiert WordPress aus `vendor/roots/wordpress-no-content`, verlinkt das Plugin aus dem Arbeitsbaum, installiert mit Präfix `ccape2e_` in der angegebenen Test-DB (Name muss `test` enthalten, D-01; nur Tabellen mit diesem Präfix werden angelegt und gelöscht), aktiviert das Plugin, lädt die Fixture (`tools/qa/e2e/live-setup.php`: feste E2E-Passwortvergabe nur für `cc_fx_lea`, `certificates.auto_issue` an), startet `php -S` und fährt `tools/qa/e2e/live-smoke.js` in Chromium:

1. Anmeldung `cc_fx_lea` über `wp-login.php`, REST-Nonce;
2. „Mein Lernen“ listet „Kurs Alpha“ → Kursraum (Module, L-A-01, Startformular ER-A);
3. Lektion L-A-01 abschließen (`PUT …/progress`, `confirm`);
4. Lernfrage: Auslieferung ohne Lösung, Antwort richtig ausgewertet;
5. Prüfung ER-A: Start über das Formular, 30 Fragen ohne Feedback (GM-028), Antworten speichern, Reload behält die Antworten, Kursraum bietet „Continue exam“ für denselben Versuch (GM-027), offene Fragen über die Prüfungs-API (reveal + Selbsteinschätzung, siehe Befund F-4), Bestätigung und Abgabe → „Passed“;
6. Outbox einmal ausgeführt (wie WP-Cron) → Zertifikat in `/me/certificates`, Zertifikatsseite mit Prüflink;
7. Gast in neuem Kontext: Prüfseite 200 mit Kurs, ohne Login/Adresse; falscher Token zeigt nichts;
8. „Mein Lernen“ zeigt Fortschritt > 0; Server-Log ohne PHP-Fehler/Warnungen.

Ergebnis 17.11.2026: dreimal hintereinander `live-smoke: OK` (Tabellen danach entfernt). Die gedumpten E2E-Prüfungen (`learning-area-a11y.js`, `learning-area-mobile.js`) bleiben unverändert. Nicht abgedeckt: Firefox/WebKit, Mobil-Viewport live, R0-11 Flow 1 (Autor) und Flow 3 (Gruppenverwalter), Praxis-Benchmark (Gate B-4).

## 5. Befunde aus dem Durchlauf

| Nr. | Befund | Wirkung / Vorschlag |
|---|---|---|
| F-1 | Prüfungsteile entstehen je Blueprint-Modul (`section` = Modul). Teil P2 aus R0-05 §2.4 (M-A2 + M-A3 ≥ 18/23) ist nicht abbildbar; ER-A prüft deshalb Gesamtquote und P1 | Abweichung nach §5.1 freigeben oder Teile über mehrere Module ermöglichen (R1-08/R1-09); Referenzlauf GM-029…GM-031 berücksichtigen |
| F-2 | `free_limited` kennt **eine** Grenze je Kurs/Modul für Fragen, Lektionen, Bögen und Dateien; AR-02/03/06/07 mit unterschiedlichen Zahlen schließen sich aus (die größte gilt) | Fixture setzt die Regeln einzeln je Test; Abweichung freigeben oder Bereichsgrenzen einführen (R1-13/R1-14) |
| F-3 | Antworten eines Lektionsquiz speisen den Fortschritt des Quiz-Providers (`answered`) nicht; ein Quiz schließt die Lektion nicht ab (GM-010) | Lücke R1-07/R1-10 |
| F-4 | Offene Fragen sind im Prüfungsraum ohne JavaScript nicht beantwortbar (kein Aufdecken/Selbsteinschätzung im Formular); sie zählen sonst als unbeantwortet | Lücke R1-16 (auch A11Y-Flows); E2E nutzt die Prüfungs-API |
| F-5 | Kein Import für Medien, Blueprints und Karteikarten; PKG-v1 enthält Q-A3-011 (Entwurf) nicht; `package_id` wird aus dem Inhalt gebildet (`cc-fx-neutral-v1-<sha12>`), nicht `…-000000000001` | GM-064, GM-118 (Zählungen), GM-120 (Kartenteil), GM-125/GM-128 (Medien) bleiben teilweise/offen |
| F-6 | Join-Token JT-1 `cc-fx-join-0001` ist nicht setzbar (Token werden erzeugt und nur gehasht gespeichert) | bewusst; Tests erzeugen den Link über `rotate_link()` |
| F-7 | GM-045: paralleler Start liefert 409 `ccap_exam_start_busy` statt 422 `ccap_busy_retry`; GM-154: fremder Kurs 404 statt 403 (CT-02) | Codes in §5.1 bzw. im R1-GATE klären |
| F-8 | Keine REST-Routen für Status/Health/Monitor (GM-146…GM-148); R1-38 liefert CLI-Status und Site Health | Abweichungskandidat §5.1 |

## 6. Gates B/C (R0-12 §3.2/§3.3) – technischer Stand

| Kriterium | Stand |
|---|---|
| B-1 Golden Master Lernkern (GM-001…093, 137…144) | 84 von 101 mit Test; offen: GM-006, 010, 050, 051, 061, 064, 068, 074, 075, 078, 079, 089, 090, 092, 093, 137; GM-077 R2 (E-05). **Nicht erfüllt** |
| B-4 Referenz-E2E | Flow 2 (Lernender lernt, besteht, erhält Nachweis) live in Chromium Desktop erfüllt; Firefox/WebKit/Mobil, Flow 1 und Flow 3, Praxis-Benchmark offen. **Teilweise** |
| B-5 Kurs ohne Import (GM-137) | offen |
| B-6 Standalone | CI ohne Woo/Suite/Pro; Free-Guard 0 Befunde; Live-Site ohne weitere Plugins. Technisch erfüllt (SA-001/CT-09/NFR-017/PRIV-017 in ihren Tests) |
| B-2, B-3, B-8, B-9, B-10 | Tests der jeweiligen R1-Aufgaben grün (REV-, PUB-/VAL-, CRT-, Aufgaben-, Fragetyp-Tests); GM-191…GM-196 mit Test. Vollständigkeit der Kataloge ist nicht Teil dieses Laufs |
| C-2 Grants/Seats/Gate (GM-094…117) | 19 von 24; offen GM-104, 113, 114, 115, 117. **Nicht erfüllt** |
| C-3 Import/Medien (GM-118…136) | 14 von 19; offen GM-123, 127, 129, 130, 135; GM-118/120/125/128/136 teilweise. **Nicht erfüllt** |
| C-6 Nichtfunktional (GM-187/188) | beide mit Test; Messprotokoll NFR-018 (p95) ist ein manueller Lauf. **Teilweise** |
| C-7 API-Verträge (GM-145…155) | 8 von 11; offen GM-146…148 (F-8). **Nicht erfüllt** |
| C-8 Betrieb (GM-156…186 ohne 159/160, GM-189/190) | 23 von 31; offen GM-162, 167, 174, 175, 176, 189, 190; GM-180 Abweichung (R1-31 A-2). **Nicht erfüllt** |
| C-1, C-4, C-5, C-9, C-12, C-17 | Suiten der R1-Aufgaben grün (RBAC, CON, REC, PRIV, NTF, PSD/Härtung; GM-201…208 mit Test) |
| C-10, C-11, C-13, C-14, C-15, C-16 | brauchen manuelle Sitzungen, Usability-Abnahme (R1-29), SAST-/Kompatibilitätsläufe bzw. Freigaben; nicht Gegenstand dieses Laufs |

R1-GATE verlangt 100 % grüne Pflichttests oder freigegebene Abweichungen (E-184). Mit 36 offenen GM-Fällen und den Befunden F-1…F-8 sind Gate B und Gate C **technisch nicht bestanden**.

## 7. Offen bis zum Abhaken (nicht durch die Umsetzung zu erledigen)

| Punkt | Zuständig |
|---|---|
| **Referenzlauf des Originals für die Rechenlogik-Fälle (E-56, E-328)**: GM-029…GM-031, GM-065…GM-068, GM-084, GM-131 mit LB-Format-Zwilling, nur durch Ausführen (E-01); Protokoll der Ergebnisse gegen die Erwartungswerte. Dieser Lauf ist **nicht** erfolgt und wird hier nicht ersetzt oder nachgebildet | getrennte Prüfrolle |
| Klärung Antwort-IDs `<frage>-a…e` (R0-05 §2.3) gegen Schlüssel `^([A-Z]|MODEL)$` (R0-08 §3.1); umgesetzt ist die positionsweise Abbildung beim Import | Auftraggeber |
| Freigabe oder Behebung der Abweichungen F-1, F-2, F-7, F-8 und GM-180 über §5.1 (E-55) | Auftraggeber |
| Schließen der 36 `open`-Fälle der Ausnahmeliste (Ziel-Tasks dort angegeben) | jeweilige R1-Aufgabe |
| Review durch zweite Person vor Merge | AG-05 |

## 8. Ergebnisse 17.11.2026

phpcs 0 Fehler (1 bekannte Warnung `ImportTest.php`, durch zwei Docblock-Zeilen jetzt Zeile 320) · PHPStan L8 0 · Unit 109 / 2 892 · Integration **417 / 14 823** · Multisite **430 / 13 612** · Textdomain-Lint 0 · Free-Guard 0 · `gm-coverage` 0 Lücken · `live-smoke.sh` OK. Neue Nutzertexte: keine. Neue REST-Routen, Hooks oder Audit-Aktionen: keine.

## Anhang · GM-Matrix (`php tools/qa/gm-coverage.php --matrix`)

| GM-ID | Status | Test / Grund |
|---|---|---|
| GM-001 | Test | `GoldenMasterTest.php::test_GM_001`, `CourseModelTest.php::test_GM_001_GM_003_catalog_and_status`, `LearnerTest.php::test_GM_001_catalog` |
| GM-002 | Test | `GoldenMasterTest.php::test_GM_002` |
| GM-003 | Test | `CourseModelTest.php::test_GM_001_GM_003_catalog_and_status` |
| GM-004 | Test | `GoldenMasterTest.php::test_GM_022_and_GM_004`, `SmartTrainerTest.php::test_GM_072_scope_and_GM_004_topics` |
| GM-005 | Test | `GoldenMasterTest.php::test_GM_005` |
| GM-006 | open | R1-25: No test for "≤ 2 queries for all courses and modules" and reload after invalidate(); the cache layer itself is covered by CacheTest (K1/K2). |
| GM-007 | Test | `ActivityTest.php::test_GM_007_lesson_quiz` |
| GM-008 | Test | `ActivityTest.php::test_GM_008_012_013_014_video_view` |
| GM-009 | Test | `ProgressTest.php::test_GM_009_completion`, `GoldenMasterTest.php::test_GM_009` |
| GM-010 | open | R1-07/R1-10: Finding: answers of a lesson quiz do not feed the QuizProvider progress (`answered`), so a lesson is not completed by its quiz; only the completion rule is unit-tested (ActivityUnitTest). |
| GM-011 | Test | `ActivityTest.php::test_GM_011_progress` |
| GM-012 | Test | `ActivityTest.php::test_GM_008_012_013_014_video_view` |
| GM-013 | Test | `ActivityTest.php::test_GM_008_012_013_014_video_view` |
| GM-014 | Test | `ActivityTest.php::test_GM_008_012_013_014_video_view` |
| GM-015 | Test (teilweise) | `GoldenMasterTest.php::test_GM_015_downloads`, `ContentGateTest.php::test_protected_files` – AR-07 is an on-demand rule; Course Captain has one free_limited limit for every area. |
| GM-016 | Test | `ContentGateTest.php::test_protected_files` |
| GM-017 | Test | `QuestionBankTest.php::test_GM_017_024_025_delivery_and_token`, `QuestionTypesTest.php::test_GM_017_GM_026_delivery_and_distribution` |
| GM-018 | Test | `ExamTest.php::test_GM_018_learning_answer_idempotent` |
| GM-019 | Test | `QuestionTypesTest.php::test_GM_019_choice_and_open` |
| GM-020 | Test | `QuestionBankTest.php::test_GM_020_reveal_and_no_access` |
| GM-021 | Test | `ExamTest.php::test_GM_021_047_open_question_and_flags` |
| GM-022 | Test | `GoldenMasterTest.php::test_GM_022_and_GM_004`, `QuestionTypesTest.php::test_GM_022_ticking_everything_never_wins` |
| GM-023 | Test | `QuestionBankTest.php::test_GM_023_pool` |
| GM-024 | Test | `QuestionBankTest.php::test_GM_017_024_025_delivery_and_token` |
| GM-025 | Test | `QuestionBankTest.php::test_GM_017_024_025_delivery_and_token` |
| GM-026 | Test | `QuestionTypesTest.php::test_GM_017_GM_026_delivery_and_distribution` |
| GM-027 | Test | `ExamTest.php::test_GM_027_028_029_start_resume_and_composition`, `live-smoke.js::exam started, answered, reloaded and submitted` |
| GM-028 | Test | `GoldenMasterTest.php::test_GM_028_029_er_a`, `ExamTest.php::test_GM_027_028_029_start_resume_and_composition`, `live-smoke.js::exam started, answered, reloaded and submitted` |
| GM-029 | Test (teilweise) | `GoldenMasterTest.php::test_GM_028_029_er_a`, `ExamTest.php::test_GM_027_028_029_start_resume_and_composition` – Part P2 (M-A2 + M-A3 ≥ 18/23) has no counterpart: one section per blueprint module (finding). |
| GM-030 | Test | `GoldenMasterTest.php::test_GM_030` |
| GM-031 | Test | `GoldenMasterTest.php::test_GM_031` |
| GM-032 | Test | `ExamTest.php::test_GM_032_033_reduced_practice_draft` |
| GM-033 | Test | `GoldenMasterTest.php::test_GM_033`, `ExamTest.php::test_GM_032_033_reduced_practice_draft` |
| GM-034 | Test | `ExamTest.php::test_GM_034_variant` |
| GM-035 | Test | `ExamTest.php::test_GM_035_expiry` |
| GM-036 | Test | `ExamTest.php::test_R120_offline_buffer_and_tabs` |
| GM-037 | Test | `ExamTest.php::test_R120_offline_buffer_and_tabs` |
| GM-038 | Test (teilweise) | `ExamTest.php::test_R120_rest` – REST save with age_ms; 422 reason `ccap_exam_expired` over REST not asserted. |
| GM-039 | Test | `ExamTest.php::test_GM_039_submit_and_evaluation` |
| GM-040 | Test | `ExamTest.php::test_GM_040_041_cancel` |
| GM-041 | Test | `ExamTest.php::test_GM_040_041_cancel` |
| GM-042 | Test | `ExamTest.php::test_GRANT_027_revoked_during_exam` |
| GM-043 | Test | `ExamTest.php::test_GM_043_044_learning_lock` |
| GM-044 | Test | `ExamTest.php::test_GM_043_044_learning_lock` |
| GM-045 | Test (teilweise) | `ConcurrencyTest.php::test_CON_005_parallel_starts` – Code `ccap_exam_start_busy` (409) instead of `ccap_busy_retry` (422). |
| GM-046 | Test | `ExamTest.php::test_R120_start_rate_limit` |
| GM-047 | Test | `ExamTest.php::test_GM_021_047_open_question_and_flags` |
| GM-048 | Test | `GoldenMasterTest.php::test_GM_048_083_drip` |
| GM-049 | Test | `GoldenMasterTest.php::test_GM_049_151_idor` |
| GM-050 | open | R1-16/R1-20: Browser case (offline buffer in localStorage, notice, re-send); the server side is GM-036/GM-037 (ExamTest::test_R120_offline_buffer_and_tabs). Needs a JS exam client E2E. |
| GM-051 | open | R1-16: Browser case (abort dialog, single flash message); the server side is GM-040 (ExamTest::test_GM_040_041_cancel). |
| GM-052 | Test | `GoldenMasterTest.php::test_GM_052` |
| GM-053 | Test | `ConcurrencyTest.php::test_CON_016_parallel_submit` |
| GM-054 | Test | `ExamTest.php::test_R120_offline_buffer_and_tabs` |
| GM-055 | Test | `LearningListsTest.php::test_GM_055_060_favorites_and_collections` |
| GM-056 | Test | `LearningListsTest.php::test_GM_055_060_favorites_and_collections` |
| GM-057 | Test | `LearningListsTest.php::test_GM_055_060_favorites_and_collections` |
| GM-058 | Test | `LearningListsTest.php::test_GM_055_060_favorites_and_collections` |
| GM-059 | Test | `LearningListsTest.php::test_GM_055_060_favorites_and_collections` |
| GM-060 | Test | `LearningListsTest.php::test_GM_055_060_favorites_and_collections` |
| GM-061 | open | R1-28: 409 `ccap_request_in_progress` / `ccap_idempotency_key_reused` exist only for assignments and certificates, not for learning answers; orphaned entries after 5 min untested. |
| GM-062 | Test | `GoldenMasterTest.php::test_GM_062_063_flashcards`, `LearningListsTest.php::test_GM_062_self_assessment` |
| GM-063 | Test | `GoldenMasterTest.php::test_GM_062_063_flashcards`, `LearningListsTest.php::test_GM_063_streak` |
| GM-064 | open | R1-15/R1-41: No flashcard import (the runner imports courses, modules, lessons, questions), so a card removed from a package cannot become `source_archived`. |
| GM-065 | Test | `SmartTrainerTest.php::test_GM_065_order` |
| GM-066 | Test | `SmartTrainerTest.php::test_GM_066_067_stages_and_intervals` |
| GM-067 | Test | `SmartTrainerTest.php::test_GM_066_067_stages_and_intervals` |
| GM-068 | open | R1-12/R1-40: Legacy rows without `due_at` (mastered stays mastered, due = last + 7 d) untested. |
| GM-069 | Test | `SmartTrainerTest.php::test_GM_069_tabs_and_recent` |
| GM-070 | Test | `LearningListsTest.php::test_GM_070_071_error_training` |
| GM-071 | Test | `LearningListsTest.php::test_GM_070_071_error_training` |
| GM-072 | Test | `SmartTrainerTest.php::test_GM_072_scope_and_GM_004_topics` |
| GM-073 | Test | `SmartTrainerTest.php::test_GM_073_rate_limit` |
| GM-074 | open | R1-12: Targeted practice of one question with 422/404/403 variants untested. |
| GM-075 | open | R1-10: Stage counts summing up to the total (secure/due/completed) untested. |
| GM-076 | Test | `GoldenMasterTest.php::test_partial_access_and_GM_086` |
| GM-077 | r2 | E-05: Second channel is the app API (no app API in R1). |
| GM-078 | open | R1-10: Course progress reset with kept exam history and audit untested. |
| GM-079 | open | R1-10/R1-03: Progress report per course for authorised roles (U-TUT only C-A) untested. |
| GM-080 | Test (teilweise) | `SchemaTest.php::test_GM_163_activation_creates_complete_schema` – Generic schema completeness check (includes `due_at`). |
| GM-081 | Test | `GoldenMasterTest.php::test_GM_081` |
| GM-082 | Test (teilweise) | `GoldenMasterTest.php::test_GM_082` – One limit for questions, lessons and variants (AR-02 = AR-03). |
| GM-083 | Test | `GoldenMasterTest.php::test_GM_048_083_drip` |
| GM-084 | Test | `AccessTest.php::test_GRANT_012_chain_and_restack` |
| GM-085 | Test | `AccessTest.php::test_GRANT_010_no_access_change_with_second_source` |
| GM-086 | Test | `GoldenMasterTest.php::test_partial_access_and_GM_086` |
| GM-087 | Test | `AccessTest.php::test_GRANT_002_003_manual` |
| GM-088 | Test | `AccessTest.php::test_GRANT_004_006_source_idempotency` |
| GM-089 | open | R1-13: Grant API rules `ccap_access_exists` / `ccap_user_required` do not exist; extend/parallel chains are GM-084. |
| GM-090 | open | R1-13/R1-17: Grant audit search by reference untested. |
| GM-091 | Test | `LearnerTest.php::test_GM_091_access_states` |
| GM-092 | open | R1-13: Manual + group + free rule at the same time (union of scopes, latest end) untested. |
| GM-093 | open | R1-28: No public filter "owns course" in the hook snapshot. |
| GM-094 | Test | `AccessTest.php::test_SEAT_001_021_create` |
| GM-095 | Test | `AccessTest.php::test_group_link_full_rotation_and_status` |
| GM-096 | Test | `ConcurrencyTest.php::test_CON_001_002_last_seat` |
| GM-097 | Test | `GroupCertificateAdminTest.php::test_GRP_006_007_validity_and_status` |
| GM-098 | Test | `AccessTest.php::test_SEAT_002_005_invitations` |
| GM-099 | Test | `AccessTest.php::test_SEAT_007_015_expiry_and_address_binding` |
| GM-100 | Test | `GroupCertificateAdminTest.php::test_GRP_006_007_validity_and_status` |
| GM-101 | Test | `AccessTest.php::test_SEAT_009_010_remove_and_reduce` |
| GM-102 | Test | `PrivacyOpsTest.php::test_GM_102_103_seats_and_invitations` |
| GM-103 | Test | `PrivacyOpsTest.php::test_GM_102_103_seats_and_invitations` |
| GM-104 | open | R1-13/R1-16: Return to the invitation after login/registration untested. |
| GM-105 | Test | `GroupCertificateAdminTest.php::test_GRP_008_009_010_018_owner_policy_members` |
| GM-106 | Test | `GroupCertificateAdminTest.php::test_GRP_001_002_003_create` |
| GM-107 | Test | `ContentGateTest.php::test_protected_pages` |
| GM-108 | Test | `ContentGateTest.php::test_protected_pages` |
| GM-109 | Test | `ContentGateTest.php::test_protected_pages` |
| GM-110 | Test | `ContentGateTest.php::test_protected_pages` |
| GM-111 | Test | `ContentGateTest.php::test_protected_files` |
| GM-112 | Test | `ContentGateTest.php::test_protected_files` |
| GM-113 | open | R1-14/R1-40: Moving the legacy protected folder with fallback untested. |
| GM-114 | open | R1-14: Private folder outside the web root (self test "private", way back) untested. |
| GM-115 | open | R1-14/R1-38: HTTP self test of the protected folders (200 critical, 403 green, no answer yellow) untested. |
| GM-116 | Test | `CompatibilityTest.php::test_KOMP_03x_page_cache_signals` |
| GM-117 | open | R1-23: Read-only restore check of protected files untested. |
| GM-118 | Test (teilweise) | `GoldenMasterTest.php::test_GM_118` – Path and state machine on PKG-v2; manifest counts differ from R0-05 (no media/blueprint/flashcard import, no draft Q-A3-011 in PKG-v1). |
| GM-119 | Test | `GoldenMasterTest.php::test_GM_119` |
| GM-120 | Test (teilweise) | `GoldenMasterTest.php::test_pkg_v2` – Question part only; card archive (FC-A-08) needs flashcard import (GM-064). |
| GM-121 | Test | `ImportTest.php::test_import_lifecycle` |
| GM-122 | Test | `ImportTest.php::test_validation` |
| GM-123 | open | R1-15: Rights override with reason and audit untested. |
| GM-124 | Test | `ImportBatchTest.php::test_BIG_037_resume_prechecks` |
| GM-125 | Test (teilweise) | `ImportBatchTest.php::test_BIG_020_chunked_commit_and_idempotent_reimport` – Chunked steps; no media step (no media import). |
| GM-126 | Test | `ImportBatchTest.php::test_BIG_030_cancel_and_discard` |
| GM-127 | open | R1-15/R1-05: Bulk rights release with confirmation untested. |
| GM-128 | Test (teilweise) | `GoldenMasterTest.php::test_GM_128_bad_media` – Upload validator with MED-BAD-1…6; no media import, no legacy quarantine. |
| GM-129 | open | R1-15: Upload limit = smallest value and system files (PKG-SYS) untested. |
| GM-130 | open | R1-15: Link import (https, ≤ 3 redirects, SHA-256) not implemented. |
| GM-131 | Test | `PrivacyOpsTest.php::test_retention` |
| GM-132 | Test | `GoldenMasterTest.php::test_GM_132` |
| GM-133 | Test | `ImportTest.php::test_export` |
| GM-134 | Test | `ImportBatchTest.php::test_cli_run_status_and_report` |
| GM-135 | open | R1-15: JSON schema subset validator unit cases ($ref over files, if/then, contains) untested. |
| GM-136 | Test (teilweise) | `ImportBatchTest.php::test_BIG_023_crash_resume_and_lease`, `ImportBatchTest.php::test_BIG_035_object_isolation_and_threshold` – Scaled to 5 000/1 500 questions (R1-41). |
| GM-137 | open | R1-05/R1-26: Hand-built C-A with the structure hash of the imported C-A untested. |
| GM-138 | Test | `PrivacyOpsTest.php::test_GM_138_139_audit_view` |
| GM-139 | Test | `PrivacyOpsTest.php::test_GM_138_139_audit_view` |
| GM-140 | Test | `AuthoringTest.php::test_GM_140_notices` |
| GM-141 | Test | `AuthoringTest.php::test_editor_screens`, `A11yI18nTest.php::test_A11Y_admin_screens_and_blocks` |
| GM-142 | Test | `PerformanceTest.php::test_OP_01_my_learning_constancy` |
| GM-143 | Test | `LearnerTest.php::test_pages` |
| GM-144 | Test | `GoldenMasterTest.php::test_GM_144` |
| GM-145 | Test | `ApiContractTest.php::test_CT_02_03_anonymous_and_error_format`, `ApiContractTest.php::test_rate_limit_problem` |
| GM-146 | open | R1-28/R1-38: No REST status route; R1-38 provides CLI status and Site Health (deviation candidate for §5.1). |
| GM-147 | open | R1-38: No REST health route without version (deviation candidate for §5.1). |
| GM-148 | open | R1-38: No anonymous monitor route / cron stall 503 (deviation candidate for §5.1). |
| GM-149 | Test | `SchemaTest.php::test_GM_149_writes_get_503_while_migration_is_due` |
| GM-150 | Test (teilweise) | `ExamTest.php::test_GM_018_learning_answer_idempotent` – Learning answers; HTTP 422 without key not asserted. |
| GM-151 | Test | `GoldenMasterTest.php::test_GM_049_151_idor` |
| GM-152 | Test (teilweise) | `CompatibilityTest.php::test_KOMP_015_045_trusted_proxies` – Trusted proxies; IPv6 /64 bucket not asserted. |
| GM-153 | Test | `PolicyTest.php::test_RBAC_095_roles_on_deactivate_and_uninstall`, `LifecycleTest.php::test_GM_158_activation_deactivation_reactivation` |
| GM-154 | Test (teilweise) | `PolicyTest.php::test_RBAC_032_to_036_staff_on_attempts` – C-B answers 404 (CT-02 "foreign = invisible") instead of 403. |
| GM-155 | Test | `ApiContractTest.php::test_CT_01_route_snapshot` |
| GM-156 | Test | `PrivacyOpsTest.php::test_GM_156_157_maintenance_and_health` |
| GM-157 | Test | `PrivacyOpsTest.php::test_GM_156_157_maintenance_and_health` |
| GM-158 | Test | `UpgradeProbeTest.php::test_REC_018_deactivate_uninstall_reinstall_keeps_everything`, `LifecycleTest.php::test_GM_158_activation_deactivation_reactivation`, `KernelContractTest.php::test_GM_158_cron_hook_registry` |
| GM-159 | Test | `NotificationTest.php::test_NTF_026_batches` |
| GM-160 | r3 | E-24, R0-12 C-8: Signed one-click unsubscribe is Pro (R3-15); N-01 unsubscribe headers are tested in NotificationTest. |
| GM-161 | Test | `PrivacyOpsTest.php::test_retention` |
| GM-162 | open | R1-17: Audit retention in batches (5 000 then 1 000, young 10 stay) untested. |
| GM-163 | Test | `SchemaTest.php::test_GM_163_activation_creates_complete_schema` |
| GM-164 | Test | `UpgradeProbeTest.php::test_REC_023_upgrade_from_previous_release_keeps_data`, `SchemaTest.php::test_GM_164_reactivation_and_rerun_are_idempotent` |
| GM-165 | Test | `SchemaTest.php::test_second_runner_skips_while_locked`, `SchemaTest.php::test_lease_fencing` |
| GM-166 | Test | `MigrationResumeTest.php::test_MIG_010_fatal_in_step_marks_failed_and_keeps_w1`, `MigrationResumeTest.php::test_MIG_026_ajax_and_admin_post_only_schedule` |
| GM-167 | open | R1-40: Exactly one masked admin notice for migration errors untested. |
| GM-168 | Test | `SchemaTest.php::test_GM_168_missing_index_is_restored_by_migration` |
| GM-169 | Test | `MigrationResumeTest.php::test_MIG_024_newer_schema_is_never_touched`, `SchemaTest.php::test_newer_schema_is_left_alone` |
| GM-170 | Test | `UpgradeProbeTest.php::test_REC_023_upgrade_from_previous_release_keeps_data` |
| GM-171 | Test | `MigrationResumeTest.php::test_MIG_008_kill_inside_backfill_batch_resumes_at_cursor` |
| GM-172 | Test | `PrivacyOpsTest.php::test_GM_172_export_and_erase` |
| GM-173 | Test | `PrivacyOpsTest.php::test_GM_173_classification` |
| GM-174 | open | R1-17: Self-service export/erasure requests (GET never erases) untested. |
| GM-175 | open | R1-17: Asynchronous export (pending → ready, 409, owner only, expiry) untested. |
| GM-176 | open | R1-17/R1-34: Privacy policy text (section, periods, 0 external links) untested. |
| GM-177 | Test | `PrivacyOpsTest.php::test_GM_102_103_seats_and_invitations` |
| GM-178 | Test | `SettingsTest.php::test_GM_178_defaults_and_limits` |
| GM-179 | Test | `SettingsRegressionTest.php::test_SET_002_005_partial_save`, `AdminSettingsTest.php::test_ADM_070_tabs_and_navigation` |
| GM-180 | deviation | R1-31 A-2: No `default_access` key in the R0-27 register; access comes from grants and `free` rules (GM-081). Needs the client decision (§5.1). |
| GM-181 | Test | `SettingsTest.php::test_GM_181_migration_guard_downgrade` |
| GM-182 | Test | `PrivacySettingsTest.php::test_SET_110_confirmed_requests` |
| GM-183 | Test | `UninstallTest.php::test_REC_018_default_uninstall_keeps_data` |
| GM-184 | Test | `UninstallProbeTest.php::test_REC_019_confirmed_deletion_removes_exactly_the_manifest` |
| GM-185 | Test | `ManifestCoverageTest.php::test_REC_020_every_used_table_is_in_the_manifest` |
| GM-186 | Test | `RecoveryMultisiteTest.php::test_REC_019_multisite_deletes_only_on_agreeing_sites` |
| GM-187 | Test (teilweise) | `PerformanceTest.php::test_OP_05_06_07_exam_constancy` – Query constancy; p95 measurement protocol (NFR-018) is a manual run. |
| GM-188 | Test | `ConcurrencyTest.php::test_CON_008_first_progress_row`, `SmartTrainerTest.php::test_GM_069_tabs_and_recent` |
| GM-189 | open | R1-24 (E-16): Starter content/FAQ seeder mechanics not implemented. |
| GM-190 | open | R1-24 (E-16): FAQ cache/REST/gate block not implemented. |
| GM-191 | Test | `QuestionTypesTest.php::test_GM_191_numeric_tolerance` |
| GM-192 | Test | `QuestionTypesTest.php::test_GM_192_matching` |
| GM-193 | Test | `QuestionTypesTest.php::test_GM_193_ordering` |
| GM-194 | Test | `QuestionTypesTest.php::test_GM_194_cloze` |
| GM-195 | Test | `AssignmentTest.php::test_GM_195_state_machine` |
| GM-196 | Test | `AssignmentTest.php::test_GM_196_files_rights_and_privacy` |
| GM-197 | Test (teilweise) | `ExamTest.php::test_GM_018_learning_answer_idempotent`, `ExamTest.php::test_E147_feedback_visibility` – Free part; Pro/licence/add-on part is R3. |
| GM-198 | Test | `PolicyTest.php::test_bundle_grants_access_to_contained_courses` |
| GM-199 | Test | `PolicyTest.php::test_RBAC_098_099_module_and_dependent_grants` |
| GM-200 | Test | `ProgressTest.php::test_E286_partial_access`, `PolicyTest.php::test_RBAC_098_099_module_and_dependent_grants` |
| GM-201 | Test | `HardeningTest.php::test_PSD_039_040_login_limits` |
| GM-202 | Test | `HardeningTest.php::test_PSD_039_040_login_limits` |
| GM-203 | Test | `HardeningTest.php::test_PSD_043_reset_limit` |
| GM-204 | Test | `HardeningTest.php::test_PSD_046_known_device` |
| GM-205 | Test | `HardeningTest.php::test_PSD_053_registration` |
| GM-206 | Test | `HardeningTest.php::test_PSD_050_xmlrpc` |
| GM-207 | Test | `HardeningTest.php::test_PSD_051_site_headers` |
| GM-208 | Test | `CompatibilityTest.php::test_KOMP_057_security_plugins_auto_retreat`, `HardeningTest.php::test_PSD_065_inactive_until_confirmed`, `HardeningTest.php::test_PSD_047_048_conflicts` |
| GM-209 | Test (teilweise) | `ActivityTest.php::test_GM_011_progress` – Monotonic completion; PUT with an older progress state not asserted. |
| GM-210 | Test | `ExamTest.php::test_GM_210_attempts_and_cooldown` |
