# R1-10 · Fortschritt, Completion, Mastery, Competency – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-10):** Getrennte Zustände, Resume, Revisionsschutz, Datenschutz.
**Quellen:** R0-08 §5 (§5.1 Completion, §5.2 Aggregat, §5.3 Competency), R0-06 `ccap_course_progress`, R0-05 GM-009, R0-19 §4.8 (`ccap_task_continue`) und §5 (`course.completed`); Beschlüsse E-27, E-28, E-70, E-73, E-286. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Progress/CourseProgress.php` | Materialisierte Kurs-Completion gegen die **Struktur der Kursrevision des Lernenden** (beim ersten Schreiben eingefroren, nie gegen Live-Beiträge): Modul fertig ⇔ alle Pflicht-Activities fertig; Kurs fertig ⇔ alle Pflichtmodule fertig (unabhängig vom Zugang); `completion_ratio` = erledigte / Pflicht-Activities der **zugänglichen** Module (E-286), ohne Platzhalter, nicht verfügbare Provider und Regel `none` (GM-009), abgerundet auf 4 Stellen; monoton (`completed_revision_id`, `completed_at`; E-70); `course.completed` genau einmal je Zyklus (Idempotenzschlüssel); Erklärung je Modul (zugänglich, x/y, fertig); `mastery_ratio` = gefestigte / Fragen der freigeschalteten Module ohne Karteikarten (§5.2 `summaryFrom`), `null` ohne Fragen; Neuaufbau in Batches (`rebuild`, Task `progress_rebuild:<kurs>` über `ccap_task_continue`, +60 s) |
| `src/Progress/Competency.php` | Abgeleitete Kompetenz ohne eigene Tabelle (E-73): nur bewertete, bestandene Versuche mit `evidence_eligible`; Ziele aus `certifies_objectives` im Snapshot; Zustände `evidenced`/`expired`/`revoked` (`support_void`)/`not_evidenced`; Gültigkeit aus `validity_days`; Belegdaten (Quelle, Regelversion, Zeit, Punkte, gültig bis); nie eine geschätzte Quote |
| `src/Activity/ActivityProgress.php` | Aktion `course_captain_activity_progress_changed` nach jeder Interaktion; **Fix:** Lektionen ohne Typ nutzen den `lesson`-Provider (vorher nie abschließbar, eingefroren aber als `lesson`) |
| `src/Exam/ExamRules.php`, `src/Exam/ExamSessions.php` | Einstellungen `certifies_objectives`, `competency_validity_days`, im Snapshot unter `competency` eingefroren; **Fix:** `evidence_eligible` in den Exam-Events aus dem Snapshot statt aus einem nicht vorhandenen Feld |
| `src/Kernel/Plugin.php` | Registrierung |
| `tests/integration/ProgressTest.php` | 6 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **108 / 1 096** · Multisite **117 / 1 149** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0.

## 3. Tests

| ID | Test |
|---|---|
| GM-009 | Nenner 4 (Platzhalter und optionales Modul ausgenommen); Hook rechnet nach Interaktion; 0,25 → abgeschlossen ohne optionales Modul; `completed_revision_id`; `course.completed` genau einmal; Zugangsverlust setzt Abschluss nicht zurück |
| E-286 | Modul-Grant: Nenner nur zugängliche Module, Quote 1,0, Kurs bleibt `in_progress` |
| Revisionsschutz | neue Kursrevision ändert Nenner bestehender Lernender nicht; neue Lernende starten auf neuer Revision |
| §5.2 | Completion erzeugt keine Mastery; 1 von 2 gefestigt → 0,5 |
| §5.3 | Übung/verkürzt und nicht bestanden kein Nachweis; Ablauf nach Gültigkeit; annulliert → `revoked`; keine Quote |
| Neuaufbau | gelöschte Aggregate per Batch und per `ccap_task_continue` wiederhergestellt; fremde Tasks ignoriert |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Optionale Kurs-Abschlussregel (z. B. `passed(exam)`) | mit R1-33 (Kurs-Overrides); bis dahin genügt der Abschluss aller Pflichtmodule |
| A-2 | Prüfungs-Activity → `ccap_activity_progress` (`passed`/`evaluated`) | der Start erhält die `activity_uid` mit der Lektionsansicht (R1-16); Datenfeld vorhanden |
| A-3 | Umstellung auf neue Revisionen (Vorwärtsrollen) | R1-27 (R0-23) |
| A-4 | `mastery_ratio` hängt an `learn_status = mastered` | Stufen setzt der Smarttrainer (R1-12), der danach `recalculate` auslöst |
| A-5 | Zertifikat als zweite Nachweisquelle | R1-44 |
| A-6 | Reset durch Kursverwaltung mit Audit (E-70), Datenschutz-Export/Löschung der Fortschrittstabellen | R1-17 bzw. R1-38; Daten sind je Nutzer adressierbar (`user_id`) |

## 5. Übergaben

R1-12 (Stufen → Mastery), R1-16 (Anzeige „x von y freigeschalteten Modulen“, Resume), R1-17 (Export/Löschung), R1-27 (Revisionswechsel), R1-33 (Kursregel), R1-44 (Zertifikate). Review durch zweite Person vor Merge (AG-05).
