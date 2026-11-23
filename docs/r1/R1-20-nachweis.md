# R1-20 · Prüfungsintegrität und Wiederaufnahme – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-20):** Serverzeit/Timeout, Verbindungsabbruch, Doppelabgabe, Browser-Reload, Offline-Grenzen, Replay und Manipulationsschutz getestet.
**Quellen:** R0-08 §6.4 (Zeit, Wiederaufnahme, Offline, mehrere Tabs), §6.5 (Locks/Limits), §6.6 (Fehlercodes), Testanker §6 Ende; R0-19 R-18…R-24 und Fehlerkatalog; R0-17 (Manipulation/Replay). Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Exam/ExamSessions.php` | **Offline-Puffer:** `save_answer(…, $age_ms)`. Der Antwortzeitpunkt ist `jetzt − age`, begrenzt auf die eingefrorene `late_tolerance` und nie vor dem Start. Annahme nur, wenn der Zeitpunkt ≤ Frist + `grace` liegt (Gerätezeit nicht prüfbar, Designgrenze §6.4). **Mehrere Tabs:** ein älterer gepufferter Stand überschreibt keinen neueren (`applied=false`, `saved_at=null`), unter Zeilensperre. **Start-Limit** 40 je Stunde; Fortsetzen und Replay zählen nicht. **Katalogcodes** `ccap_exam_already_submitted`, `ccap_exam_aborted`, `ccap_exam_expired` (auch nach Auswertung beim Zugriff) statt `ccap_exam_finished`. **Wiederaufnahme:** `view` liefert gespeicherte Antworten, Markierungen, aufgedeckte Musterantworten, `server_now` und `deadline_at` |
| `src/Storage/RateLimiter.php` | atomarer Festfenster-Zähler in `ccap_rate_limit` (gehashter Schlüssel), `ccap_rate_limited` 429 mit `bucket` und `retry_after`. Der Smarttrainer nutzt ihn ebenfalls (Bucket `answer`, Katalogcode statt `ccap_answer_rate_limited`) |
| `src/Course/CourseException.php` | optionale maschinenlesbare Details (`data`) |
| `src/Learner/LearnerRoutes.php` | R-18 `POST /courses/{course}/exam-sessions` (201 neu, 200 fortgesetzt; `request_key` Pflicht, Regel nur aus diesem Kurs), R-19 `GET /exam-sessions/{id}`, R-20 `PUT …/answers/{pos}` (`answer`, `age_ms`), R-21 Flag, R-22 Aufdecken, R-23 Abgabe, R-24 Abbruch; `no-store`; Fehlerdetails in `data` |
| `tests/integration/ExamTest.php` | 5 neue Tests (Gruppe `R1-20`) |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **167 / 1 708** · Multisite **176 / 1 761** · Free-Guard 0 · Textdomain-Lint 0.

## 3. Abdeckung der DoD

| DoD | Test |
|---|---|
| Serverzeit/Timeout | `test_R120_offline_buffer_and_tabs` (Grace, Kulanz, Auswertung beim Zugriff), `test_GM_035_expiry`, `test_R120_reload_resume` (`server_now`, `deadline_at`) |
| Verbindungsabbruch, Browser-Reload | `test_R120_reload_resume` (gleicher Versuch, Antworten, Markierung, Musterantwort), `test_R120_rest` (200 statt 201) |
| Offline-Grenzen | `test_R120_offline_buffer_and_tabs`: Alter innerhalb Frist + Grace angenommen; zu spät 410; negatives oder übergroßes Alter begrenzt |
| Doppelabgabe | `test_R120_replay_and_manipulation`, `test_GM_039_submit_and_evaluation`, REST-Doppelabgabe gleiches Ergebnis |
| Replay | gleicher `request_key` nach Abgabe → kein neuer Versuch; Abbruch nach Abgabe 409; Abgabe nach Abbruch 409 |
| Manipulation | fremder Versuch 404 (Ansicht, Antwort, Abgabe, REST), Position 0/99 404, ungültige/mehrfache/injizierte Antworten 422, Aufdecken bei Nicht-Freitext 400, Regel aus fremdem Kurs 404, Start-Limit 429 mit `retry_after` |
| Mehrere Tabs | älterer Stand `applied=false`, neuerer gewinnt |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | `answered_at` sekundengenau | Bei gleicher Sekunde gewinnt die spätere Schreibung (Zeilensperre) |
| A-2 | R-17 `GET /courses/{course}/exam` (Übersicht) und R-25 Review | folgen mit R1-28 (API-Verträge); das Ergebnis liefert R-19/R-23 bereits nach E-147 |
| A-3 | `request_key` | 8–36 Zeichen `[A-Za-z0-9_-]` (Spalte `start_token` char(36), global eindeutig) |
| A-4 | Paralleler Doppel-Submit/Cron und paralleler Start | bestehende Sperren (`FOR UPDATE`, CAS, `NamedLock`) aus R1-08; echter Mehrprozess-Lauf mit R1-42 |
| A-5 | Snapshot-Hash-Prüfung (`ccap_exam_snapshot_invalid`) | Health-Check mit R1-38 |

Review durch zweite Person vor Merge (AG-05).
