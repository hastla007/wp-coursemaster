# R1-18 · GM-Nachtrag Gruppe g2

**Stand:** 23.11.2026 · **Basis:** `claude/blissful-curie-tuuf7q` @ `1322ec4` · **Test-DB:** `ccap_test_g2`
**Umfang:** GM-050, GM-051, GM-061, GM-093, GM-146, GM-147, GM-148 und Befund F-4 aus `docs/r1/R1-18-nachweis.md` §5. Clean Room (E-01): nur R0-Dokumente, Inventur und vorhandener Code; kein LearnBoating-Code.

## 1. Je GM-ID

| GM-ID | Umsetzung | Test |
|---|---|---|
| **F-4** (R1-16, A11Y-Flows) | Offene Fragen sind im Prüfungsraum ohne JavaScript beantwortbar: Knopf „Show model answer“ (`ccap_reveal=<Position>`, speichert vorher die übrigen Antworten), danach Musterantwort und Selbsteinschätzung als Radiogruppe in eigenem `fieldset` (`answer[<Position>][known]` = 1/0). Selbsteinschätzung vor dem Aufdecken wird ignoriert; ein Post ohne Auswahl lässt die gespeicherte Einschätzung stehen. Ein unsichtbarer Standard-Speicherknopf am Formularanfang sorgt dafür, dass Enter in einem Feld nie aufdeckt oder abbricht. Nach dem Aufdecken springt die Seite zur Frage und meldet es im Statusbereich | `GoldenMasterGapsTest::test_F4_open_question_without_javascript`; `live-smoke.js` beantwortet die offenen Fragen jetzt über das Formular statt über die Prüfungs-API und prüft vor der Abgabe, dass alle 30 Fragen beantwortet sind |
| **GM-050** Offline-UI | `assets/js/exam-room.js` (progressive Verbesserung): jede Änderung wird sofort per `PUT /exam-sessions/{id}/answers/{pos}` gespeichert. Ohne Netz bleibt sie in localStorage unter `ccap_exam_<Session-UID>` (R0-17 T-04: nur eigene Antworten, keine Lösungen), ein Hinweis (`position:fixed`) erscheint und wird im Statusbereich angesagt; beim Ereignis `online`, beim nächsten Laden und alle 15 s wird mit `age_ms` nachgesendet. Ein Eintrag wird erst nach Serverantwort entfernt (401/403/429/5xx bleiben im Puffer, andere 4xx verwirft der Client, der Server ist maßgeblich). Ergebnisseite und Abbruch entfernen den Puffer (PRIV-018) | `tools/qa/e2e/exam-room.js` Schritt „GM-050 …“ (Chromium, Telefon-Viewport 390×844): 4 Antworten online → Server 4; offline 5. Antwort → 1 localStorage-Eintrag, Hinweis sichtbar und `position:fixed`, Server weiter 4; online → 0 Einträge, Hinweis weg, Server 5; Reload zeigt den Serverstand |
| **GM-051** Abbruch-UI | Abschnitt „Cancel the exam“ mit Knopf im Prüfungsformular (speichert vorher); Rückfrage auf eigener Seite („Do you really want to cancel the exam? …“, „Yes, cancel the exam“ / „No, continue the exam“); Bestätigung bricht ab (GM-040 unverändert: Antworten bleiben), leitet in den Kursraum und zeigt dort genau einmal „The exam was cancelled. …“ (einmalige Meldung über Transient `ccap_flash_<Nutzer>`, 10 min) | `GoldenMasterGapsTest::test_GM_051_cancel_dialog` (Rückfrage ändert nichts, Abbruch, Antwort bleibt, Startknopf wieder da, Meldung 1× und nach erneutem Aufruf 0×, Pufferlöschung auf der Ergebnisseite); `exam-room.js` Schritt „GM-051 …“ im Browser inkl. Reload ohne Doppelmeldung |
| **GM-061** Idempotenz | `AnswerService::answer` führt den `idempotency_key` in `ccap_request_idempotency` (Scope `answer`, Hash aus Token + Antwort, 7 Tage): gleicher Key + gleiche Antwort → gespeichertes Ergebnis (`repeated`); anderer Inhalt → `409 ccap_idempotency_conflict`; Eintrag „in Arbeit“ → `409 ccap_request_in_progress` mit `retry_after: 2`; älter als 300 s → verwaist und übernommen (CAS auf `created_at`). Abgelehnte Anfragen geben den Key frei | `GoldenMasterGapsTest::test_GM_061_learning_answer_idempotency` (Dienst und REST inkl. `Retry-After: 2`, Alter 5 s / 301 s, Freigabe nach 422) |
| **GM-093** Öffentlicher Filter | Filter `course_captain_can_access( bool $allowed, int $user_id, string $course_uid, string $scope, Decision $decision )` nach R0-19 §4.3 (LB-Herkunft „user owns course“) bei jeder M-02-Entscheidung, auch für Gäste und gesperrte Konten. Nur einschränkend (E-84): `false` → `ccap_content_locked`/`filtered`; `true` auf einer Ablehnung wird ignoriert und einmal je Anfrage als `access_filter_ignored` auditiert (Bereich „access“). Im Hook-Snapshot `docs/api/hooks.v1.json` | `GoldenMasterGapsTest::test_GM_093_can_access_filter`: U-L1 `true`, U-L3 `false`, Gast `false`, Kurs-UID und Scope übergeben; durchgereichtes `true` anderer Plugins lässt U-L1 frei; `true` für U-L3 wirkungslos + Audit; `false` sperrt U-L1; Snapshot-Eintrag |
| **GM-146** Status | `GET /status` (R0-19 R-01, `ccap_view_system_status`): `plugin_version`, `contract_version`, `schema_version`, `outbound_mode`, `hold_mode`, `checks[{id, status ok|warn|fail, message}]` aus `OpsStatus` | `test_GM_146_status_route`: Gast 401 `ccap_auth_required`, Lernende 403 `ccap_forbidden`, Admin 200 mit aktuellem Schema (= `Migrator::report()['code_version']`) |
| **GM-147** Health | `GET /health` (= `GET /monitor`, R-50, öffentlich, Klasse K): anonym nur `{status}`; keine Version, `no-store` | `test_GM_147_health_without_version`: beide Routen 200, Körper genau `{"status":"ok"}`, kein „version“ in Körper/Headern, `?key=` ignoriert (SEC-035) |
| **GM-148** Monitor | Einzelprüfungen H-02 (fällig/fehlerhaft), H-04 (Tabellen), H-07 (Wartung ≤ 3 h; frisch geplant = ok), H-10 (keine gescheiterte Aufgabe in 2 h), H-14 (geschützter Ordner), H-26 (Haltemodus); Ausfall einer davon → 503 `{status:error}` (R0-13 §2.1). Details nur mit Header `X-CC-Monitor-Key` = `COURSE_CAPTAIN_MONITOR_KEY` (`hash_equals`) oder für #22. 60 Aufrufe je IP/Minute, fail-open | `test_GM_148_monitor_and_cron_stall`: Header-Schlüssel → `checks`, falscher/fehlender Schlüssel und Lernende → nur Gesamtstatus, Admin → `checks`; Clock + 3 h 1 min ohne Lauf → Admin 503 mit H-07 `fail`, Gast 503 `{status:error}`; neuer Lauf → 200 |

## 2. Dateien

| Datei | Änderung |
|---|---|
| `src/Learning/AnswerService.php` | Idempotenz über `ccap_request_idempotency` (claim/complete/release), Speichern in `store()` ausgelagert |
| `src/Policy/PolicyService.php` | Filter `course_captain_can_access` in M-02 (`filtered()`), Audit ignorierter Erweiterungen |
| `src/Ops/AuditView.php` | Aktion `access_filter_ignored` im Register |
| `src/Ops/Monitor.php` (neu), `src/Kernel/Plugin.php` | Routen `/status`, `/health`, `/monitor` |
| `src/Learner/ExamRoom.php`, `src/Learner/LearningArea.php` | offene Fragen ohne JS, Abbruch-Rückfrage, einmalige Meldung, Offline-Hinweis, Datenattribute für den Client, Pufferlöschung, CSS |
| `assets/js/exam-room.js` (neu) | Speichern je Änderung, Offline-Puffer |
| `tests/integration/GoldenMasterGapsTest.php` (neu) | 7 Tests (Gruppe `R1-18`) |
| `tests/integration/ExamTest.php` | `test_GM_018…`: gleicher Key mit **anderer** Antwort erwartet jetzt `ccap_idempotency_conflict` statt des gespeicherten Ergebnisses (R0-19 §2.1, GM-150, GM-061); die Wiederholung mit gleicher Antwort bleibt geprüft. Verschärfung, keine Abschwächung |
| `tests/integration/ApiContractTest.php` | `/health`, `/monitor` als dokumentierte öffentliche Routen |
| `tools/qa/e2e/exam-room.js` (neu), `live-smoke.sh`, `live-smoke.js`, `live-setup.php` | Browser-Fälle GM-050/GM-051 mit U-L2 (`cc_fx_ole`, E2E-Passwort), offene Fragen im Smoke über das Formular |
| `tools/qa/gm-coverage-exceptions.php` | GM-050, -051, -061, -093, -146, -147, -148 entfernt |
| `docs/api/routes.v1.json`, `docs/api/hooks.v1.json`, `docs/API.md`, `docs/API-CHANGELOG.md` | Snapshot und Doku |
| `languages/course-captain.pot` | neu erzeugt (17 neue Texte; zusätzlich 31 bereits vorhandene, bisher fehlende Texte aus LessonView/Audit) |

## 3. Kommandos und Ergebnisse (23.11.2026)

| Kommando | Ergebnis |
|---|---|
| `php8.5 vendor/bin/phpcs -q` | 0 Fehler |
| `php8.5 vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | 0 Fehler |
| `php8.5 vendor/bin/phpunit -c phpunit.xml.dist` | 120 Tests, 2 übersprungen, 0 Fehler |
| `WP_TESTS_DB_NAME=ccap_test_g2 php8.5 vendor/bin/phpunit -c phpunit-integration.xml.dist` | 430 Tests, 10 067 Assertions, 31 übersprungen (pcntl), 0 Fehler |
| `WP_TESTS_DB_NAME=ccap_test_g2 php8.5 vendor/bin/phpunit -c phpunit-integration-multisite.xml.dist` | 443 Tests, 31 übersprungen (pcntl), 0 Fehler |
| `php8.5 tests/guards/free-guard.php` | 184 Dateien, 0 Befunde |
| `php8.5 tests/guards/textdomain-lint.php` | 177 Dateien, 0 Probleme |
| `wp i18n make-pot …` (Parameter wie CI J-03) | POT aktualisiert |
| `php8.5 tools/qa/gm-coverage.php` | 210 IDs; 178 mit Test; 32 Ausnahmen (29 open, 1 deviation, 1 r2, 1 r3); missing/stale/invalid/unknown 0 |
| `tools/qa/e2e/live-smoke.sh ccap_test_g2` | dreimal `live-smoke: OK` (Smoke + GM-050 + GM-051) |

Hinweise zur lokalen Ausführung im Worktree: `vendor/composer`, `vendor/bin`, `vendor/phpstan` und `vendor/autoload.php` sind echte Kopien (Rest verlinkt), damit Composers PSR-4-Zuordnung auf den Worktree-`src/` zeigt (geprüft mit `findFile('CourseCaptain\\Kernel\\Plugin')`). `make-pot` lief offline mit den mitgelieferten `block-i18n.json`-Schemata (Ergebnis inkl. der 6 `block.json`-Einträge, identisch zum bisherigen Ende). Für `live-smoke.sh` muss `php` die Variable `CCAP_E2E_PASS` in den Docker-Container durchreichen und `php -S` mit `--init` laufen (lokaler Wrapper, nicht eingecheckt).

## 4. Abweichungen und offene Punkte

| Nr. | Thema | Stand |
|---|---|---|
| A-1 | GM-061 nennt `ccap_idempotency_key_reused`; R0-19 §8 (Delta an R0-05) ersetzt den Code durch `ccap_idempotency_conflict`. Umgesetzt nach R0-19 | keine Freigabe nötig (Beschluss in R0-19) |
| A-2 | R0-13 §2.1 nennt den Monitor `GET /health` mit Header `X-Monitor-Key`, R0-19 R-50 `GET /monitor` mit `X-CC-Monitor-Key` (R0-17 T-58 verweist auf den R0-19-Namen). Umgesetzt: beide Pfade mit derselben Antwort, Header `X-CC-Monitor-Key` | Klärung der Dokumente empfohlen; kein Produktentscheid getroffen |
| A-3 | GM-093 „besitzt Kurs“: R0-19 §4.3 führt die LB-Herkunft unter `course_captain_can_access`; die Inventur INV-C-179 schlug einen konsumierten Filter `course_captain_user_has_access` vor. Umgesetzt nach R0-19 (angewendeter, nur einschränkender Filter) | keine Freigabe nötig |
| A-4 | H-32 (REST-Loopback) ist im Monitor implizit (die Route antwortet); H-07 bleibt im Statusbild S-22/CLI als Warnung wie bisher | – |
| A-5 | Abmelden leert den Prüfungspuffer nicht aktiv (PRIV-018 „nach Logout“); der Puffer ist an die Session-UID gebunden, wird nach Abgabe/Abbruch/Ergebnis gelöscht und enthält keine Lösungen | offen für R1-20/R1-16 |
| A-6 | Deutsche Übersetzung (`de_DE.po/.mo`) der 17 neuen Texte nicht ergänzt | Integrator (R1-19-Ablauf) |

