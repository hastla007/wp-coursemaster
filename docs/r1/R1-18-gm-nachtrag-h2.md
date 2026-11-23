# R1-18 · GM-Nachtrag Gruppe h2 (GM-038, 080, 136, 150, 152, 187, 197, 209)

**Stand:** 23.11.2026 · **Basis:** `claude/blissful-curie-tuuf7q` (03820a9) · **Test-DB:** `ccap_test_g5`
**Auftrag:** `partial`-Einträge in `tools/qa/gm-coverage-exceptions.php` vervollständigen. Nicht angefasst: GM-045, GM-154 (E-333), GM-118/125/128, GM-077/160/180.
**Quellen:** R0-05 §1.4 (GM-038), §1.7 (GM-080), §1.11 (GM-136), §1.13 (GM-150, GM-152), §1.19 (GM-187), §1.22 (GM-197), §1.24 (GM-209), §5 A-06; R0-07 M-08, RG-09; R0-08 §5.1, §6.4; R0-10 §1.3 (Erklärungstexte im Paket); R0-19 R-08, R-20, R-28, §2.1, §8 (Fehlerkatalog); R0-39 §2.5, FG-08, G-12; E-20, E-70, E-147, E-268, E-269. Clean Room (E-01): kein LearnBoating-Quelltext gelesen.

## 1. Je GM-ID

| GM | Status | Umsetzung | Test |
|---|---|---|---|
| GM-038 | `partial` bleibt (nur Statuscode, **Freigabe nötig**) | **Fehler behoben:** M-08 (`PolicyService::use_attempt`) sperrte Schreibaktionen schon ab der rohen Frist mit `403 ccap_forbidden`; über REST waren damit weder gepufferte Antworten mit `age_ms` (R0-08 §6.4) noch der Fachcode möglich. Die Frist von M-08 ist jetzt Frist + eingefrorene Kulanz (`late_tolerance_seconds`, dann ist der Versuch ohnehin ausgewertet); den Antwortzeitpunkt gegen Frist + Grace prüft weiter `ExamSessions::mutate` unter Zeilensperre. Alle Schreibaktionen (Antwort, Markierung, Aufdecken) laufen über `mutate` | `ExamTest::test_GM_038_offline_answers_over_rest`: Frist = jetzt − 40 s; `PUT …/answers/2` ohne `age_ms` → `ccap_exam_expired` (Status 410), nichts gespeichert, Versuch läuft; Pos. 1/3/4 mit `age_ms` 60/50/45 s → 200 `applied=true`; GET liefert den gespeicherten Stand; Abgabe wertet Pos. 1, 3, 4 richtig |
| GM-080 | erledigt, Eintrag entfernt | – (Migration vorhanden) | `SchemaTest::test_GM_080_due_at_added_by_migration`: Schema n−1 ohne `due_at` und Index `user_due`, eine Bestandszeile; Migration → Spalte und Index da, keine Drift, `DB_VERSION` gesetzt, Zeile erhalten |
| GM-136 | `partial` bleibt (skaliert, **Freigabe/Lastlauf nötig**) | – | neu `ImportBatchTest::test_GM_136_defects_abort_at_half_and_resume`: 600 Fragen, 3 defekt (Hash), Abbruch bei 50 % (Position 300), Wiederaufnahme → 597 importiert, Report genau 3 Einträge, keine Dubletten. Volumen 20 000 (PKG-LARGE) nur im Lastlauf auf R-M |
| GM-150 | `partial` bleibt (nur Statuscode, **Freigabe nötig**) | `POST /answers`: fehlender/ungültiger Schlüssel → **`428 ccap_idempotency_key_required`** (Katalog R0-19 R-08/§8, bisher `400 ccap_invalid_param`); Schlüssel aus Header `Idempotency-Key` oder Feld `idempotency_key`; Katalogtexte + de_DE | `GoldenMasterGapsTest::test_GM_150_answer_idempotency_key_over_rest` (Fixture, Q-A1-001, `fx-150-1`): ohne Key und mit Nicht-UUID 428, kein Versuch; Header-Key 200; gleicher Key → gleiches Ergebnis, `repeated`, kein 2. Versuch; gleicher Key mit anderer Antwort 409 `ccap_idempotency_conflict`. `ExamTest::test_GM_018…` prüft jetzt den Katalogcode statt `ccap_invalid_param` |
| GM-152 | erledigt, Eintrag entfernt | `ClientIp::rate_key()`/`rate_key_of()`: IPv6 je /64, IPv4 je Adresse; genutzt vom Login-Limit (bisher eigene Logik), Zertifikatsprüfung (REST und Seite) und `/health` (bisher volle IPv6-Adresse) | `CompatibilityTest::test_GM_152_ipv6_prefix_shares_the_limit`: 5 Fehlversuche von `2001:db8::1` sperren `2001:db8::2`, nicht `2001:db8:0:1::1`; XFF eines nicht vertrauenswürdigen Peers ignoriert; hinter `10.0.0.0/8` zählt der rechte nicht vertrauenswürdige XFF-Eintrag |
| GM-187 | `partial` bleibt (manuell, **Lastlauf nötig**) | – | unverändert `PerformanceTest::test_OP_05_06_07_exam_constancy` (Konstanz, Obergrenzen); p95 nach R0-22/NFR-018 mit C-A und 200 Lernenden braucht R-M (R1-22 A-1) |
| GM-197 | `partial` bleibt (Pro-Teil R3-08) | Paketexport (`PackageExporter`) schreibt `revision.feedback_correct`/`feedback_incorrect` (R0-10 §1.3, E-20; vorher gingen die Texte beim Export verloren); leere Texte bleiben weg (Hash unverändert) | `ExamTest::test_GM_197_explanations_stored_exported_never_delivered`: Lernmodus falsch → `correct=false`, `correct_ids=[A]`, kein `explanation`/Text; DB-Texte unverändert; Export enthält beide Texte; Prüfung bestanden mit Restversuchen → Lösungen sichtbar, keine Erklärung. Auslieferung mit Pro/Lizenzende/Add-on aus ist nach R0-39 §2.5 (FG-08, G-12) Sache des Add-ons |
| GM-209 | erledigt, Eintrag entfernt | `ActivityProgress::interact` mit `kind: unconfirm` (R0-19 R-28, E-70): nur ein `manual_confirm`-Abschluss wird zurückgenommen (`started`, `completed_at`/`content_hash` geleert), nachweisbasierte Abschlüsse → `422 ccap_validation_failed`, `reason: evidence_completion`; Reset durch die Kursverwaltung mit Audit besteht (`ProgressReset`, GM-078) | `ActivityTest::test_GM_209_progress_monotonic_over_rest`: `PUT progress` Position 60, `confirm` → `completed`; verspätete Anfrage mit älterem Stand (Position 10) → bleibt `completed`, `completed_at` gleich; `unconfirm` → `started`, Wiederholung idempotent, erneutes `confirm`; Datei mit `downloaded` → `unconfirm` 422, bleibt `completed` |

## 2. Dateien

Geändert: `src/Policy/PolicyService.php` (M-08 Frist + Kulanz), `src/Learning/AnswerService.php`, `src/Learner/LearnerRoutes.php` (Schlüssel aus Header), `src/Rest/RestContract.php` (Katalog `ccap_idempotency_key_required`), `src/Security/ClientIp.php`, `src/Security/Hardening.php`, `src/Certificate/CertificateRoutes.php`, `src/Certificate/CertificatePages.php`, `src/Ops/Monitor.php`, `src/Activity/ActivityProgress.php`, `src/Import/PackageExporter.php`; Tests `tests/integration/{ExamTest,SchemaTest,GoldenMasterGapsTest,CompatibilityTest,ActivityTest,ImportBatchTest}.php`; `tools/qa/gm-coverage-exceptions.php` (GM-080, GM-152, GM-209 entfernt; GM-038, 136, 150, 187, 197 mit präziser Begründung); `languages/course-captain.pot`, `languages/course-captain-de_DE.po/.mo`; `docs/API.md`, `docs/API-CHANGELOG.md`. Keine neuen Routen oder Hooks (Routen-/Hook-Snapshots unverändert).

## 3. Kommandos und Ergebnisse (23.11.2026)

| Kommando | Ergebnis |
|---|---|
| `php8.5 vendor/bin/phpcs -q` | 0 Befunde |
| `php8.5 vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | 0 Fehler |
| `php8.5 vendor/bin/phpunit -c phpunit.xml.dist` | 130 Tests, 2 übersprungen, OK |
| `WP_TESTS_DB_NAME=ccap_test_g5 php8.5 vendor/bin/phpunit -c phpunit-integration.xml.dist` | **abgebrochen** nach ca. 60/471 Tests: Datenträger voll (`errno 135 No more room in record file`, `/` 100 %, 272 MB frei; Ursache außerhalb dieser Gruppe). Gezielte Läufe auf derselben DB alle grün: `ExamTest`+`PolicyTest`+`A11yI18nTest` 53 OK; `CompatibilityTest`+`HardeningTest`+`CertificateTest` 41 OK; `ActivityTest`+`LessonViewTest`+`AssignmentTest`+`ProgressReset` 21 OK; `test_GM_197`+`ImportTest`+`ImportBatchTest` 23 OK; `test_GM_150`/`_018`/`_061` 3 OK; `test_GM_080`/`_168` 2 OK; `test_GM_136` 1 OK |
| `WP_TESTS_DB_NAME=ccap_test_g5 php8.5 vendor/bin/phpunit -c phpunit-integration-multisite.xml.dist` | **nicht gelaufen** (Datenträger voll); muss nachgeholt werden |
| `php8.5 tests/guards/free-guard.php` | 0 Befunde (205 Dateien) |
| `php8.5 tests/guards/textdomain-lint.php` | 0 Probleme (198 Dateien) |
| `php8.5 tools/qa/gm-coverage.php` | 210 IDs, 207 mit Test, 3 Ausnahmen (`r2` 1, `r3` 1, `deviation` 1); missing/stale/invalid/unknown 0 |

POT: `wp i18n make-pot` (CI-Parameter) mit `--skip-block-json`, die sechs Block-Einträge unverändert aus der vorherigen POT angehängt (Download von `block-i18n.json` im Sandbox-Netz gesperrt). `.mo` mit `wp i18n make-mo` neu erzeugt.

## 4. Freigabe nötig / Abweichungen

1. **GM-038 (Statuscode):** R0-05 erwartet `422 reason=ccap_exam_expired`, der verbindliche Fehlerkatalog R0-19 §8 legt `ccap_exam_expired` auf **410** (Spalte LB: „Web 422“). Umgesetzt ist 410; alle anderen Erwartungen sind belegt. Analog zu GM-045 (E-333) braucht die Statusabweichung eine Freigabe (§5.1).
2. **GM-150 (Statuscode):** R0-05 erwartet „ohne Key 422“, R0-19 R-08/§8 nennt `428 ccap_idempotency_key_required`. Umgesetzt ist der Katalogcode (vorher `400 ccap_invalid_param`, das keiner Quelle entsprach). Freigabe der Abweichung von R0-05 nötig.
3. **GM-136:** bewusst skaliert (600/3 defekt statt 20 000); volles Volumen nur im Lastlauf auf R-M (R1-41, R1-22 A-1). Entscheidung, ob der skalierte Nachweis genügt, liegt beim Auftraggeber.
4. **GM-187:** p95-Messung ist ein manueller Lastlauf (R-M, R1-22 A-1); CI deckt nur Konstanz und Obergrenzen ab.
5. **GM-197:** Der Pro-Teil (Auslieferung `explanation` mit `course.explanations`, Lizenzende E-268, Add-on aus E-269) gehört nach R0-39 §2.5/FG-08/G-12 ins Pro-Add-on (R3-08); der Free-Kern darf keine Erklärungen ausliefern. Kein Eingriff im Kern.
6. **Hinweis GM-150:** Eine Wiederholung mit gleichem Schlüssel liefert das gleiche Bewertungsergebnis (`correct`, `points_milli`, `attempts`, `correct_ids`), aber nicht `streak_counted`/`progress` der Erstantwort (die Antwort wird nicht gespeichert, nur der Versuch). R0-19 §2.1 („gespeicherte Antwort“) ließe sich strenger lesen; nicht geändert, keine Produktentscheidung getroffen.
7. **Hinweis M-08:** R0-07 M-08 nennt „Frist nicht abgelaufen“; ausgelegt als Frist + Kulanz, weil R0-08 §6.4 und GM-037/GM-038 das Nachsenden bis Frist + Kulanz verlangen. Die eigentliche Zeitgrenze (Antwortzeitpunkt ≤ Frist + Grace) bleibt serverseitig unter Sperre.
