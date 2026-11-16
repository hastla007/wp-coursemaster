# R1-22 · Skalierung und Lasttests – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-22):** Große Kurs-/Fragenmengen, parallele Prüfungen, Query-/Index-Analyse, Job-Backlog und Performancebudgets nachgewiesen.
**Quellen:** R0-14 §1.1 (OP-01…OP-12, Query-Budgets, Konstanzregel, Scanregel), §2.2/§2.3 (Profile, Messverfahren, R-CI-Gates), §4.1 RB-01…RB-08, §5 NFR-001…NFR-019. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `tests/integration/PerformanceTest.php` | R-CI-Gates nach R0-14 §2.3 Nr. 5: Query-Zähler je Operation (eigene Tabellen), **Konstanzregel** (OP-01 mit 1/5/20 Kursen, OP-05…07 mit 5/30 Fragen), **Scanregel** (EXPLAIN aller eigenen SELECTs der Hot Paths auf befüllten Tabellen: 20 000 Fortschrittszeilen, 5 000 Grants), **Job-Rückstand** (2 000 Outbox-Ereignisse). Regressionsgrenzen je OP; Messprotokoll per `CCAP_PERF_REPORT` |
| `src/Learner/LearnerQueries.php` | **OP-01 ohne N+1:** Grants, Beiträge (`_prime_post_caches`), Fortschritt und UIDs je eine Abfrage für alle Kurse; „Weiterlernen“ nur für den zuletzt aktiven Kurs (ein Kursraum statt je Kurs einer) |
| `src/Exam/ExamSessions.php`, `src/Question/QuestionRepository.php` | **OP-05 ohne Abfrage je Frage:** aktuelle Revisionen in einer Abfrage, `QuestionRepository::revisions()` lädt Revisionen, Antworten und Tags mit drei Abfragen (füllt den K1-Cache), Sessionfragen als **ein** mehrzeiliges `INSERT` |
| R1-42 (`tests/integration/ConcurrencyTest.php`, `RaceRunner`) | parallele Prüfungsstarts/-abgaben (CON-005/016) mit N = 50 als Lastanteil „parallele Prüfungen“ |

## 2. Messprotokoll (16.11.2026, Profil `ci`, MariaDB 10.11.14, PHP 8.3.6, lokale Testumgebung – keine Zeitbudgets, R0-14 §2.1 R-CI)

| OP | Eigene Queries vorher | nachher | Budget R0-14 | Konstanz | Bewertung |
|---|---|---|---|---|---|
| OP-01 Mein Lernen | 22 / 106 / 421 (1/5/20 Kurse) | **21 / 21 / 21** | 15 | ✓ | N+1 behoben; 6 über Budget (Kursraum für „Weiterlernen“) |
| OP-03 Smarttrainer `next` | 23 | 23 | 10 | – | über Budget (B-3) |
| OP-04 Smarttrainer `answer` | 26–28 | 26–28 | 8 | – | über Budget (B-3) |
| OP-05 Prüfungsstart | 44 / 152 (5/30 Fragen) | **22 / 22–23** | 15 | ✓ | Abfrage je Frage behoben; 7 über Budget |
| OP-06 `saveAnswer` | 12 / 12 | 12 / 12 | 6 | ✓ | über Budget (B-3) |
| OP-07 `submit` | 9 / 9 | 9 / 9 | 20 | ✓ | im Budget, keine Abfrage je Frage |
| NFR-003 Scanregel | – | 0 Volltabellenscans in 28 geprüften SELECTs | 0 | – | ✓ |
| NFR-009 Job-Rückstand | – | 2 000 Ereignisse in 20 Läufen, längster Lauf 0,08–0,13 s | ≤ 60 min, Lauf ≤ 20 s | – | ✓ |
| NFR-010/011 (R1-42) | – | alle Race-Szenarien N = 50 ohne verletzte Invariante, 0 `ccap_db_busy` | – | – | ✓ (siehe R1-42) |

## 3. Befunde

| Nr. | Befund | Status |
|---|---|---|
| B-1 | OP-01 skalierte linear mit der Kurszahl (≈ 21 Queries je Kurs) | behoben |
| B-2 | OP-05 ≈ 4 Queries je Prüfungsfrage, Einzel-INSERTs | behoben |
| B-3 | Absolute Query-Budgets für OP-01/03/04/05/06 überschritten (u. a. Policy-/Gate-Prüfungen je Aufruf, Rate-Limit-Zähler, Fortschritts-Upsert) | als Regressionsgrenze im Test fixiert; Optimierung (Request-Memo für Gate-Entscheidungen, gebündelte Policy-Abfragen) als Folgeaufgabe vor R1-GATE |

## 4. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Lastprofile L1–L4 mit k6 auf R-M (Zeitbudgets p50/p95, NFR-001/004/005/006/008) | R-M (Cloud-VM, E-192) steht hier nicht zur Verfügung; die R-CI-Gates (Queries, Konstanz, Scan, Rückstand, Races) laufen in der Suite. k6-Skripte und Seeder `wp cc-dev seed` folgen mit der R-M-Bereitstellung |
| A-2 | Datenprofil `default`/`heavy` (Millionen Zeilen) | Scanregel mit befüllten Tabellen im Profil `ci`-Maßstab geprüft; Volllauf auf R-M |
| A-3 | Redis-Achse (NFR-008), R-S-Grenzen (NFR-007), Cron-Ausfall unter Last (NFR-013) | Cache-Korrektheit in R1-25; Funktionsnachweise Cron-frei in R1-08/R1-20; Lastvarianten auf R-M/R-S |
| A-4 | Import 10 000 Fragen (OP-11, NFR-006) | mit R1-41 (Großimport) |

Review durch zweite Person vor Merge (AG-05).
