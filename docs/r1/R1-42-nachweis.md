# R1-42 · Parallelitäts- und Datenintegritäts-Lastprofil – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-42):** Gleichzeitige Enrollment-/Seat-/Attempt-/Completion-Operationen gegen echte DB, Unique-Invarianten, Deadlock-/Retry- und Messprotokoll.
**Quellen:** R0-21 §2.3 (R-1…R-8), §2.6 (T-1…T-5), §5.1–§5.3 (CON-001…CON-050); R0-14 RB-04, NFR-010…NFR-012. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `tests/support/RaceRunner.php` | Mehrprozess-Runner: ein `pcntl_fork` je Job, eigene DB-Verbindung je Kind, gemeinsame Startbarriere, JSON-Ergebnis mit Laufzeit und Deadlock-Differenz, Ende per SIGKILL. Skalierung über `CCAP_RACE_N` / `CCAP_RACE_REPEAT`, Messprotokoll (JSON-Zeilen) über `CCAP_RACE_REPORT` |
| `tests/integration/ConcurrencyTest.php` | 10 Szenarien (CON-001/002/005/008/011/015/016/019/040/042/043) |
| `src/Storage/Database.php` | **T-2:** Deadlock (1213) wiederholt die ganze äußere Transaktion bis zu 3× mit Jitter. **T-3:** Lock-Wait (1205) ohne Wiederholung. Beide enden mit `503 ccap_db_busy` und `retry_after: 2`; nach Rollback keine After-Commit-Callbacks (T-5). Zähler `Database::deadlocks()` für die Messung |
| `src/Learning/SmartTrainer.php`, `AnswerService.php`, `Flashcards.php`, `src/Progress/CourseProgress.php` | **R-2:** Fortschrittszeilen (`ccap_question_progress`, `ccap_course_progress`) werden **vor** der Fachtransaktion per `INSERT IGNORE` angelegt; in der Transaktion folgt nur `FOR UPDATE` per Unique-Schlüssel. Behebt den gemessenen S→X-Deadlock bei parallelen Erstzugriffen |
| `src/Storage/Backfiller.php` | `ccap_db_busy` aus der Transaktion zählt als vorübergehender Fehler (Schritt pausiert, nicht fehlgeschlagen) |
| `src/Policy/PolicyService.php` | `reset_instance()` (intern) für Prozesse mit neuer Verbindung (Runner) |

## 2. Befunde und Korrekturen

| Nr. | Befund (Messung) | Korrektur |
|---|---|---|
| B-1 | CON-008/CON-019 bei N=20: einzelne Anfragen enden nach drei Deadlocks mit `ccap_db_busy` (S→X-Upgrade bei `INSERT IGNORE` + `FOR UPDATE` in einer Transaktion) | R-2 umgesetzt (siehe oben); danach 0 Deadlocks bei N=20 und N=50 |
| B-2 | Deadlocks wurden nicht wiederholt (RB-04 verlangt Wiederholung der ganzen TX) | T-2/T-3 in `Database::transaction()` |

## 3. Messprotokoll (16.11.2026, MariaDB 10.11.14, Testumgebung lokal, N = 50 Prozesse, 2 Wiederholungen bei CON-001/002)

| Szenario | N | Ergebnis | Deadlocks | Wandzeit ms | p95 ms |
|---|---|---|---|---|---|
| CON-001 letzter Platz (Gruppenlink) | 50 | 1 × ok, 49 × `ccap_group_full`; genau 1 aktiver Seat | 0 | 326–367 | 223–240 |
| CON-002 gleiches Konto, zwei Tabs | 2 | 2 × ok mit gleicher Seat-ID, 1 Seat | 0 | 30–32 | 24 |
| CON-005 Prüfungsstarts, verschiedene Keys | 50 | 50 × gleiche Session-ID, 1 Session `in_progress` | 0 | 329 | 211 |
| CON-008 erste Antworten, gleiche Frage | 50 | 50 × ok, 1 Fortschrittszeile, 50 Versuche | 0 | 452 | 359 |
| CON-011 zwei Veröffentlichungen | 2 | 2 × ok, gleiche Revision, genau +1 Revision | 1 (per Retry gelöst) | 31 | 22 |
| CON-015 Rate-Limit-Zähler | 50 | Zähler = 50 | 0 | 149 | 50 |
| CON-016 paralleles Submit | 50 | identische Ergebnisse, `evaluation_no = 1`, 1 Ereignis | 0 | 208 | 120 |
| CON-019 doppelter Abschluss | 50 | 1 Activity-Zeile, 1 Kursfortschritt | 0 | 391 | 274 |
| CON-040 echter Deadlock A→B/B→A | 2 | beide committet, Endstand korrekt | 1 (per Retry gelöst) | 636 | 629 |

Standardlauf in der Suite: N = 6…12 je Szenario (Laufzeit < 20 s); Volllauf: `CCAP_RACE_N=50 CCAP_RACE_REPEAT=20 CCAP_RACE_REPORT=<datei> WP_TESTS_DB_NAME=… vendor/bin/phpunit -c phpunit-integration.xml.dist --filter ConcurrencyTest`.

## 4. Sperrreihenfolge (NFR-012, Code-Review)

| Operation | Reihenfolge | Fundstelle |
|---|---|---|
| Gruppenbeitritt/Claim | `NamedLock group-seats:<gruppe>` → TX: `ccap_seat` (CAS-UPDATE per PK/Token) → `ccap_grant` → `ccap_grant_event` → `ccap_outbox_event` | `GroupService::locked()`, `claim()` |
| Grant anlegen | `NamedLock grant-chain:<user>:<kurs>` (2 s) → TX: `ccap_grant` → `ccap_grant_event` → `ccap_outbox_event` | `GrantService::create()` |
| Prüfungsstart | `NamedLock exam_start:<user>` → TX: `ccap_exam_session` (Insert, Unique `start_token`) → `ccap_exam_session_question` | `ExamSessions::start()` |
| Antwort/Flag/Submit | TX: `ccap_exam_session` `FOR UPDATE` (PK) → `ccap_exam_session_question` `FOR UPDATE` (Unique session+position) → CAS `row_version` | `ExamSessions::mutate()`, `finish()` |
| Lernantwort | `INSERT IGNORE ccap_question_progress` (Autocommit, R-2) → TX: `ccap_attempt` (Unique user+idem) → `ccap_attempt_answer` → `ccap_question_progress` `FOR UPDATE` (Unique) | `AnswerService::answer()` |
| Abschluss | `INSERT IGNORE ccap_activity_progress` → CAS-UPDATE → `INSERT IGNORE ccap_course_progress` → TX: `ccap_course_progress` `FOR UPDATE` (Unique) → `ccap_outbox_event` | `ActivityProgress::interact()`, `CourseProgress::recalculate()` |
| Kurs veröffentlichen | TX: `ccap_course_revision` `FOR UPDATE` je Kurs → Insert neue Revision | `CoursePublisher::write_revision()` (siehe A-2) |

## 5. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | MySQL 8.4 als zweite Datenbank (NFR-010) | in dieser Umgebung nur MariaDB 10.11 verfügbar; Runner ist datenbankneutral, MySQL-Lauf in der CI-Matrix (R1-39) |
| A-2 | CON-011 erwartet für den zweiten Autor `ccap_publish_busy` | beide Aufrufe liefern idempotent dieselbe neue Revision (gleicher Struktur-Hash); `FOR UPDATE` über `course_id` (Nicht-Unique, R-3) erzeugt gelegentlich einen per Retry gelösten Deadlock. Umstellung auf eine Kurs-Sperre ist ein Folgeschritt |
| A-3 | CON-003/004/010/013/014/017/018/049/050, CON-041 Sweep je Anweisung, CON-044 Sitzungs-`innodb_lock_wait_timeout = 5 s`, CON-045 Reconnect | Runner steht; Szenarien folgen (Aufgaben CON-049/050 mit R1-46) |
| A-4 | 20 Wiederholungen je Szenario | per `CCAP_RACE_REPEAT=20` ausführbar; in der Suite 1–2 Wiederholungen aus Laufzeitgründen |
| A-5 | Named-Lock-Wartezeit interaktiv 2 s (D-14-03) | Prüfungsstart nutzt weiter 10 s (R0-08 §6.5); Widerspruch R0-08 ↔ R0-14 zur **Klärung** |

Review durch zweite Person vor Merge (AG-05).
