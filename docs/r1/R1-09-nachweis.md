# R1-09 · Bewertung und Ergebnisanzeige – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-09):** Richtig/Falsch/Teilpunkte nach belegter Regel, Ergebnis-/Retake-UX, Audit.
**Quellen:** R0-08 §3.1, §4.5, §6.2; R0-06 §4.3; R0-05 GM-018; R0-19 R-47 und §5 (`exam.passed`/`exam.failed`/`exam.reevaluated`); R0-27 `exams.feedback`; R0-39 §4; Beschlüsse E-20, E-74, E-147, E-229, E-292; VAL-096. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Learning/AnswerService.php` | Lernantwort in **einer** Transaktion: `ccap_attempt` + `ccap_attempt_answer` + `ccap_question_progress` (`FOR UPDATE`; Versuche, richtig/falsch, Serie, erste/letzte Antwort). Idempotenzschlüssel (UUID) eindeutig je Nutzer (`user_idem`); Wiederholung liefert das gespeicherte Ergebnis mit `repeated = true` und zählt nicht erneut, auch bei parallelem Duplikat (1062 → gespeichertes Ergebnis). Ungültige/leere Eingabe `ccap_invalid_answer`. Ergebnis: richtig, Punkte, Versuche, Lösungsschlüssel nach Lern-Einstellung `show_correct_answers`; **nie** Erklärungen (E-20). Karteikarten zählen erst mit R1-11 |
| `src/Exam/ExamFeedback.php` | Reiner Filter über dem gespeicherten Ergebnis nach den im Snapshot eingefrorenen Feedback-Werten: `never` → nur `released = false`; sonst bestanden/nicht bestanden und richtig/falsch je Frage (E-147); Punkte nur mit `show_score`; Lösungen nur mit `show_correct_answers` und bei `no_attempts_left_or_passed` erst nach Bestehen oder ohne Restversuch; `always` wirkt wie `after_submit` (VAL-096) |
| `src/Exam/ExamSessions.php` | `view`/`submit` liefern nur noch das gefilterte Ergebnis; Bewertung je Frage (`questions[]`), Neutralisieren und manuelle Punkte; `regrade()` (Datenvertrag R-47/E-74, Route und Oberfläche Pro): M-10 (nie eigener Versuch), Pflichtbegründung, Punkte 0…Gewicht, nur `evaluated` (`ccap_exam_conflict`), neue `evaluation_no` per CAS, Audit `attempt_graded`, Outbox `exam.reevaluated`; Abgabe schreibt `exam.passed`/`exam.failed` mit `evaluation_no = 1` |
| `tests/integration/ExamTest.php` | +3 Tests (15 gesamt) |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **102 / 1 058** · Multisite **111 / 1 111** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · `.pot` unverändert (keine neuen übersetzbaren Strings).

## 3. Tests

| ID | Test |
|---|---|
| GM-018 | Doppelte Abgabe mit gleichem Schlüssel zählt einmal (`repeated`, gespeichertes statt neues Ergebnis); zweiter Schlüssel zählt; Fortschrittszähler; keine Erklärung; ungültiger Schlüssel/leere Antwort abgewiesen |
| E-147 / E-20 | Richtig/Falsch je Frage immer sichtbar; Lösungen verborgen bei Restversuch, sichtbar ohne Restversuch; `show_score = false` ohne Punkte; `never` ohne jedes Ergebnis |
| E-74 | Selbstbewertung `ccap_forbidden`; Begründung/Punktgrenzen/Position validiert; Nachbewertung kippt auf bestanden (`evaluation_no` 2), Neutralisieren reduziert Maximum (`evaluation_no` 3), Overrides bleiben; 2 Audit-Einträge; Outbox `exam.failed`, 2× `exam.reevaluated`; laufender Versuch `ccap_exam_conflict` |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Lern-Einstellung `show_correct_answers` | gelesen aus `course_captain_settings_learning`, Standard „ja“; Register und Kurs-Override mit R1-31/R1-33 |
| A-2 | `broken` → `evaluated` (K-07) | Support-Aktion mit R1-38 |
| A-3 | Retake-UX (Anzeige „noch n Versuche“) und Ergebnis-Oberfläche | Daten liegen vor (`solutions_visible`, Versuchsgrenze); Darstellung mit R1-16 |
| A-4 | Mastery (`mastery_score`, `due_at`) | R1-12; R1-09 pflegt nur die Zähler |

## 5. Übergaben

R1-10 (Completion aus Prüfungsergebnis), R1-11 (Karteikarten), R1-12 (Mastery), R1-16 (Oberfläche), R1-21 (N-11 bei `evaluation_no` > 1), R1-38 (K-07). Review durch zweite Person vor Merge (AG-05).
