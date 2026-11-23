# R1-22 · Nachtrag h9 – Knopf „Video laden“ und Query-Budgets OP-01…OP-07

**Stand:** 23.11.2026 · **Basis:** `claude/blissful-curie-tuuf7q` @ `8e32698` · **Gruppe:** h9
**Quellen:** E-45/E-45a, E-246, E-312; R0-10 §5.1–§5.3, MED-021…MED-024, MED-031; R0-31 MC-1…MC-8, PSD-022…PSD-028; R0-27 SET-114; R0-17 §6.5 (AR-01); `docs/r1/gate/AG-07-rechtspruefung.md` J-14; R0-14 §1.1 (OP-01…OP-07, Konstanzregel), §2.3; R0-21; R1-22-Nachweis B-3. Clean Room (E-01/E-327).

## 1. Externe Medien (Auftrag 1)

### 1.1 Was die Spezifikation verlangt

| Punkt | Quelle | Vorher | Jetzt |
|---|---|---|---|
| YouTube/Vimeo im Standard `always_load` **eingebettet**, datensparsam (`youtube-nocookie.com`, Vimeo `dnt=1`) | R0-10 §5.1/§5.2.1, MED-031, PSD-022 (a), R0-31 MC-1/MC-2 | nur Link „Video auf … ansehen“ (AG-07 J-14 Hinweis 2) | `iframe` mit Embed-URL (`ExternalMedia::embed_url()`), Titel „Video auf YouTube“, `referrerpolicy=strict-origin-when-cross-origin`, kein `preconnect`; Link zum Dienst bleibt darunter |
| `click_to_load`: Platzhalter ohne Fremdverbindung, Dienstname, Hinweistext mit Link | R0-10 §5.2.2 Nr. 1, MC-1a, MED-021 | nur Satz „… erst nach deiner Zustimmung geladen“, **kein Knopf** (AG-07 J-14 Abweichung 1) | Platzhalter mit Dienstname, Satz zur Übertragung („Beim Laden des Videos werden Daten wie deine IP-Adresse an YouTube übertragen.“), Link zur Datenschutzerklärung von YouTube/Vimeo, Länge (falls `duration_seconds`) |
| Knopf „Video laden“, lädt **nur dieses** Medium | §5.2.2 Nr. 2, MED-022 | fehlte | `<button type="button">Video laden</button>` mit `aria-describedby` auf den Hinweis; Klick erzeugt `iframe` bzw. `<video>`, Fokus wandert auf den Player, Statusmeldung in `role="status"` |
| Kästchen „Externe Medien von <Dienst> immer laden“, nie vorangehakt, `localStorage` je Dienst, abschaltbar | §5.2.2 Nr. 2, MC-3, PSD-023, SET-114 | fehlte | nur bei `privacy.external_media_remember = true`; nie vorangehakt; Wahl in `localStorage` `ccap_external_media` (`{"youtube":true}`, ohne Personenbezug); Setting aus → kein Kästchen, gespeicherte Wahl wird ignoriert |
| Widerruf | MC-4 | fehlte | am automatisch geladenen Video Knopf „Externe Medien von <Dienst> nicht mehr automatisch laden“ (löscht die Wahl sofort) |
| Untertitel erst mit dem Video | §5.2.2 Nr. 3, MED-022 | – | `<track>` wird erst beim Laden angehängt |
| Ohne JavaScript nur Link | §5.2.2 Nr. 4, MC-7, PSD-027 | – | Knopfbereich ist `hidden`, das Skript blendet ihn ein; ohne JS bleibt der Link „Video auf <Dienst> ansehen“ |
| Consent-Adapter | §5.3, MC-5, MED-024 | serverseitig `wp_has_consent()` | zusätzlich im Browser: `wp_has_consent(<Kategorie>)`, `window.courseCaptain.consent.register(fn)`, Ereignisse `course-captain:consent-changed` und `wp_listen_for_consent_change` |
| Gleiches HTML für alle | MC-8, PSD-028 | – | Platzhalter enthält keine nutzerbezogenen Daten; Entscheidung im Browser |

### 1.2 Dateien

| Datei | Inhalt |
|---|---|
| `src/Activity/ExternalMedia.php` | `embed_url()` (YouTube: `watch?v=`, `youtu.be/`, `/embed/`, `/shorts/`, `/live/`, nocookie; Vimeo: `vimeo.com/<id>`, `player.vimeo.com/video/<id>`; ID-Prüfung), `service()` (Dienstname, Link Datenschutzerklärung); `video_view()` liefert zusätzlich `embed_url`, `watch_url`, `pending` (was nach dem Klick geladen wird). Bestehende Schlüssel unverändert |
| `src/Learner/LessonView.php` | Einbettung, Platzhalter mit Knopf/Kästchen/Link, Skript nur bei Bedarf |
| `assets/js/external-media.js` | Progressive Enhancement (ohne Build, ~6 KB) |
| `src/Learner/LearningArea.php` | CSS (16:9-Player, Platzhalter, Kästchen 24 px) |
| `readme.txt` | „External services“: YouTube und Vimeo mit Anbieter, Daten, Nutzungsbedingungen, Datenschutzerklärung (R0-10 §5.2 Transparenz) |
| `docs/user/datenschutz.md` §4 | Betreiberdoku ergänzt |
| `docs/r1/gate/AG-07-rechtspruefung.md` J-14 | Code-Stand nachgezogen (Abweichung 1 und Hinweis 2 erledigt) |
| `languages/*` | 9 neue Texte, de_DE übersetzt, `.mo` neu |
| `tests/integration/ExternalMediaViewTest.php` | 6 Tests (`@group AG-07`, `E-45a`, MED-021/022/023/031, PSD-022/023/027, SET-114) |
| `tools/qa/e2e/external-media.js` | Browserprüfung (Playwright, 25 Prüfpunkte, ohne echtes Netz) |

### 1.3 Abweichungen / offen

- **MC-4 Konto-Knopf** „Gespeicherte Freigaben für externe Medien zurücksetzen“ (L-20): Es gibt noch keine Konto-Seite im Lernbereich. Widerruf ist am geladenen Video möglich; der Konto-Knopf folgt mit L-20.
- **Lokales Posterbild** (§5.2.2 Nr. 1): Platzhalter ist eine neutrale Fläche ohne Bild; ein eigenes Poster-Medium gibt es im Aktivitätsschema nicht.
- **Provider-Registry** `course_captain_external_media_providers` und Hostliste `course_captain_external_media_hosts` (§5.1): nicht Teil dieses Auftrags, weiter offen.
- Der Default bleibt `always_load` (E-45a); die Rechtsfrage (a)–(d) in AG-07 J-14 bleibt bei der Prüfung. Frage (d) („Knopf vor R1-GATE nötig?“) ist technisch erledigt.

## 2. Query-Budgets (Auftrag 2)

### 2.1 Messung

Profil `ci`, MariaDB (lokal), `tests/integration/PerformanceTest.php`. **Neu: kalt gemessen** – vor jeder Operation `PolicyService::flush()` und `wp_cache_flush()`, also wie eine neue Anfrage ohne persistenten Objekt-Cache (R0-14 §1.1 „ohne persistenten Objekt-Cache“). Die Werte vom 16.11. waren warm (Policy-Memo und Revisions-Cache aus der vorherigen Operation), also zu niedrig.

| OP | Budget R0-14 | 16.11. (warm) | 23.11. kalt | Status |
|---|---|---|---|---|
| OP-01 Mein Lernen (1/5/20 Kurse) | 15 | 21/21/21 | **9/9/9** | ✓ |
| OP-01 Kursraum (1 Modul/3 Lektionen vs. 4 Module/48 Lektionen) | Konstanz | (je Lektion 1–3, je Modul 3 Abfragen) | **8 = 8** | ✓ neu geprüft |
| OP-03 Smarttrainer `next` | 10 | 23 | **10** (1 wie 4 Module) | ✓ |
| OP-04 Smarttrainer `answer` | 8 (≤ 4 Schreibzugriffe in der TX) | 26–28 | **16** (7 Schreibzugriffe, 3 davon in der TX) | **Freigabe nötig** |
| OP-05 Prüfungsstart (5/30 Fragen) | 15 | 22/22–23 | **15/15** | ✓ |
| OP-06 `saveAnswer` | 6 | 12 | **8** kalt, **6** mit Revision im Objekt-Cache | **Freigabe nötig** |
| OP-07 `submit` | 20 | 9 | **10** (kalt vorher 69: eine Revisionsabfrage je Frage) | ✓ |
| NFR-003 Scanregel | 0 | 0 in 28 SELECTs | 0 in 15 SELECTs | ✓ |

### 2.2 Ursachen und Behebung

| Nr. | Ursache | Behebung | Datei |
|---|---|---|---|
| B-4 | Kursraum: Lektionsstatus je Lektion, Lektionen je Modul, Drip-Regel und Voraussetzungen je Modul **und** je Lektion erneut (N+1 in Modulen/Lektionen); Kursraum für „Weiterlernen“ berechnete Zugang, Fortschritt und „Weiterlernen“ doppelt | eine Abfrage für Status und letzte Lektion aller Lektionen, `CourseStructure::lessons_of()` (eine Abfrage für alle Module), Modulliste, Voraussetzungen und stärkste Drip-Regel in **einer** Abfrage je Kurs (`ContentGate::course_modules()`), „Weiterlernen“ ohne Zugangs-/Fortschrittsabfragen | `LearnerQueries`, `CourseStructure`, `ContentGate` |
| B-5 | Gate ohne Gedächtnis: Modulentscheidung, Drip, Voraussetzungen, laufende Prüfung, Pool je Aufruf neu, `deliver`/`verify` riefen das Gate zwei- bis dreimal | Operations-Memo in `ContentGate` (eine Instanz je Operation); `QuestionPresenter::with_gate()` teilt das Gate innerhalb einer Operation (Smarttrainer: Kandidaten + Auslieferung; Antwort: `verify` + `evaluate`) | `ContentGate`, `QuestionPresenter`, `SmartTrainer`, `AnswerService` |
| B-6 | Smarttrainer: Kandidaten und Gate je Modul (N+1); laufende Prüfung doppelt; Pool und aktuelle Revision der Gewinnerfrage erneut | eine Kandidatenabfrage für alle freigegebenen Module; Kandidaten liefern aktuelle Revision, UID und Auslieferbarkeit (gleiche Bedingungen wie `pool()`); `QuestionRepository::pool_member()` (Pool + Revision + UID in einer Abfrage) | `SmartTrainer`, `QuestionPresenter`, `QuestionRepository` |
| B-7 | Policy: Bündel und Grants in zwei Abfragen; Sperre zweimal je Entscheidung; je Modul eine Grant- und Modulzuordnungsabfrage | Grants inkl. Bündel in **einer** Abfrage; Sperre im Policy-Memo (gleiche Lebensdauer, P-11); `PolicyService::batch()` teilt Grants und Modulzuordnung für alle Module eines Kurses (Smarttrainer, Kursraum, Prüfungsgate) | `Relations`, `PolicyService` |
| B-8 | Rate-Limit: INSERT + SELECT | ein Statement, Zähler über `LAST_INSERT_ID(expr)` | `RateLimiter` |
| B-9 | Antwort: Versuch erneut gelesen, `attempts` erneut gelesen, Vorab-Prüfung auf vorhandenen Versuch auch bei neuem Schlüssel, `SHOW FULL COLUMNS` durch `$wpdb->insert()`, eine INSERT je gewählter Option | Ergebnis aus den geschriebenen Werten; Vorab-Prüfung nur bei übernommenem/abgeschlossenem Schlüssel (Altbestand fängt der Unique-Key `user_idem` ab → bestehender Duplikatpfad); `Database::insert_values()` (vorbereitetes INSERT ohne Metadatenabfrage); Optionen als ein mehrzeiliges INSERT | `AnswerService`, `Database` |
| B-10 | Prüfungsstart: Ablauf, laufende Form und Start-Token in drei Abfragen; Regel und Blueprints getrennt; Pool und aktuelle Revisionen getrennt; Antworten und Tags getrennt | eine Abfrage für Ablauf/Form/Token; Regel mit Blueprints per JOIN; `pool_revisions()`; Antworten und Tags per `UNION ALL` | `ExamSessions`, `ExamRules`, `QuestionRepository` |
| B-11 | `saveAnswer`/`submit`: Sitzung bis zu viermal gelesen (Ablauf, Policy zweimal, Besitz); Sitzung und Position mit zwei `FOR UPDATE` | eine Lesung, die Policy entscheidet auf derselben Zeile (`PolicyService::with_row()`); Sitzung und Position mit **einem** `SELECT … FOR UPDATE` (JOIN) | `ExamSessions`, `Relations`, `PolicyService` |
| B-12 | `submit` ohne Objekt-Cache: eine Revisionsabfrage je Frage (69 statt 9) – vom warmen Cache im alten Test verdeckt | Revisionen aller Sitzungsfragen gebündelt (`revisions()`) | `ExamSessions` |

Fehlende Indizes: keine gefunden; die neuen Abfragen laufen über `course_module (course_id, …)`, `access_rule (course_id, …)`, `grant (user_id, …)`, `exam_session (user_id …)`; Scanregel NFR-003 grün.

### 2.3 Tests

`tests/integration/PerformanceTest.php` (verschärft, nichts entfernt):
- kalte Messung je Operation (`cold()`), Obergrenzen gesenkt (OP-01 23→15, OP-03 25→10, OP-04 30→16, OP-05 25→15, OP-06 13→8, OP-07 10→10 jetzt kalt), zusätzlich Prüfung gegen das R0-14-Budget für OP-01/03/05/07 und OP-06 mit Revision im Cache;
- neu `test_OP_01_course_room_constancy` (1×3 vs. 4×12 Lektionen), `test_OP_03_trainer_constancy_over_modules` (1 vs. 4 Module), OP-05 Konstanz 5 = 30 Fragen (vorher ±2), OP-04 höchstens 7 Schreibzugriffe.

### 2.4 Freigabe nötig

| Nr. | Budget | Stand | Warum nicht ohne Entscheidung | Optionen |
|---|---|---|---|---|
| F-1 | **OP-04** 8 Abfragen, ≤ 4 Schreibzugriffe in der TX | 16 kalt; davon 7 Schreibzugriffe (Idempotenz anlegen + abschließen, Rate-Limit, Fortschrittszeile sicherstellen, Versuch, Antwortoption, Fortschritt) und 9 Lesezugriffe (Policy 3, Gate 2, Revision 2, Pool/Revision 1, `FOR UPDATE` Fortschritt 1). In der TX: 3 Schreibzugriffe (✓) | Idempotenz-Tabelle (R0-19 §2.1, 2 Writes), Rate-Limit in der DB (LB-NF-07 „bleibt“), Zugriffsprüfung je Anfrage (R0-07 P-11) und das Schema `ccap_attempt` + `ccap_attempt_answer` sind beschlossen; zusammen liegen sie über 8 | (a) Budget auf 16 (kalt) bzw. 12 (mit Objekt-Cache) anheben; (b) Infrastruktur-Abfragen (Idempotenz, Rate-Limit) aus dem Budget nehmen → 13; (c) Schema/Ablauf ändern (Antwortoptionen als JSON im Versuch, Rate-Limit im Objekt-Cache) |
| F-2 | **OP-06** 6 Abfragen | 8 kalt, 6 mit Revision im Objekt-Cache | `saveAnswer` bewertet sofort (`is_correct`) und braucht dafür die Revision (2 Abfragen); die Revision steht nicht im Snapshot | (a) Budget „6 mit Objekt-Cache, 8 ohne“; (b) Bewertung erst bei `submit` (ändert `is_correct` während des Versuchs); (c) Antwortdaten der Revisionen in den Snapshot (größerer Snapshot) |

## 3. Kommandos und Ergebnisse (23.11.2026, DB `ccap_test_h9` / `ccap_test_h9_ms` / `ccap_test_h9_perf`)

| Kommando | Ergebnis |
|---|---|
| `php8.5 vendor/bin/phpcs -q` | 0 Fehler, 0 Warnungen |
| `php8.5 vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | No errors |
| `php8.5 vendor/bin/phpunit -c phpunit.xml.dist` | 130 Tests, 2 942 Assertions, 2 übersprungen, grün |
| `WP_TESTS_DB_NAME=ccap_test_h9 php8.5 vendor/bin/phpunit -c phpunit-integration.xml.dist` | 479 Tests, 27 649 Assertions, 31 übersprungen (pcntl), grün |
| `WP_TESTS_DB_NAME=ccap_test_h9_ms php8.5 vendor/bin/phpunit -c phpunit-integration-multisite.xml.dist` | 492 Tests, 10 817 Assertions, 31 übersprungen, grün |
| `CCAP_PERF_REPORT=… phpunit … PerformanceTest.php` | 7 Tests grün; Werte in §2.1 |
| `CCAP_DUMP_DIR=… phpunit … ExternalMediaViewTest.php` + `node tools/qa/e2e/external-media.js <dir>` | 6 Tests grün; Browser 25/25 Prüfpunkte |
| `php8.5 tests/guards/free-guard.php` | 0 Funde |
| `php8.5 tests/guards/textdomain-lint.php` | 0 Probleme |
| `php8.5 tools/qa/gm-coverage.php` | 210 IDs, 207 getestet, 3 Ausnahmen (unverändert) |
| POT: `wp i18n make-pot … --skip-block-json` + 6 block.json-Einträge aus der alten POT | 9 neue msgids, keine entfernt |

Hinweis: Die Race-Tests mit pcntl (R1-42, u. a. parallele `saveAnswer` mit dem neuen gemeinsamen `SELECT … FOR UPDATE` für Sitzung und Position) laufen nur in der CI; lokal übersprungen.
