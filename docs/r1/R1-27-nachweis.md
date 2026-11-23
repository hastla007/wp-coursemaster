# R1-27 · Kursänderungen während laufender Lernprozesse – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-27):** Versuche bleiben revisionsfest, Fortschritt bleibt konsistent, archivierte Inhalte und Zertifikate nachvollziehbar.
**Quellen:** R0-23 §1.2 (P-1…P-7), §2.3 (RV-01…RV-21), §3.2 (Umstellung am sicheren Punkt, Backfill), §5 (Zertifikate), §7 (REV-001…048); Beschluss E-103 (Vorwärtsrollen mit Besitzstand). Clean Room (E-01).

## 1. Änderungen

| Datei | Inhalt |
|---|---|
| `src/Progress/CourseProgress.php` | **Vorwärtsrollen mit Besitzstand:** `recalculate()` erkennt eine Lernstand-Revision ≠ veröffentlichte Revision und stellt per CAS um (sicherer Punkt = nächster Zugriff bzw. Backfill). Abgeschlossene Activities bleiben über stabile Lektions-IDs abgeschlossen (RV-07). Entfernte Activities zählen nicht mehr, ihre Zeilen bleiben als Historie (RV-08). Neue Pflicht-Activities erhöhen den Nenner. Ein Kursabschluss bleibt ein Ereignis seiner Revision (`completed_revision_id` unverändert, RV-09). Fällt die letzte offene Pflicht-Activity weg, folgt der Abschluss in der neuen Revision mit `reason_code = revision_recalc` im Ereignis `course.completed` (RV-10) |
| `src/Course/CoursePublisher.php` | Backfill nach jeder Veröffentlichung (`progress_rebuild:<kurs>` über `ccap_task_continue`, §3.2 Schritt 3) |
| `src/Exam/ExamSessions.php` | Abschnittsnamen (Modultitel) im Snapshot eingefroren (`section_labels`) und im Ergebnis je Abschnitt als `title` (RV-01, REV-021) |
| `tests/integration/ProgressTest.php` | R1-10-Test „Revision eingefroren“ durch REV-010 ersetzt (alte Annahme widersprach E-103); neue Tests REV-001, REV-003, REV-004, REV-010, Backfill |
| `tests/integration/ExamTest.php` | REV-021 |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **90** · Integration **255** · Multisite **265** · Free-Guard 0 · Textdomain-Lint 0.

## 3. Abdeckung REV-Fälle

| Fall | Test |
|---|---|
| REV-001 Inhalt geändert, Abschluss bleibt | `ProgressTest::test_REV_001_content_change_keeps_completion` |
| REV-003 offene Pflichtlektion entfernt → Abschluss `revision_recalc` | `test_REV_003_removed_open_lesson_completes_course` |
| REV-004 abgeschlossene Lektion entfernt, Historie bleibt | `test_REV_004_removed_completed_lesson` |
| REV-010 neue Pflicht-Activity (A in Arbeit, B abgeschlossen) | `test_revision_rolls_forward_with_vested_rights`, `test_REV_010_completed_course_stays_completed` |
| REV-037 Backfill, idempotent | `test_backfill_after_publish` (Mehrprozess-Teil mit R1-42) |
| REV-013 Voraussetzung auf entfernte Activity → Publish abgelehnt | R1-26 `PublishValidatorTest` (VAL-121) |
| REV-016 Frage korrigiert während laufender Prüfung | `ExamTest::test_author_change_after_start_changes_nothing` (R1-08) |
| REV-020 Kulanz/Feedback im laufenden Versuch | Snapshot-Tests R1-08/R1-09 (`timing`, `feedback` eingefroren) |
| REV-021 Modul umbenannt | `ExamTest::test_REV_021_section_labels_frozen` |
| REV-023 Refund im laufenden Versuch | `ExamTest::test_GRANT_027_revoked_during_exam` |
| REV-024/025 Versuchszähler, bestandene alte Version | `ExamTest::test_GM_210_attempts_and_cooldown`, Competency-Tests R1-10 |
| REV-045/046 Zertifikat Korrektur/Widerruf, unverändert durch Revisionen | R1-44 `CertificateTest` (CRT-Fälle, Snapshot-Hash) |

## 4. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Staging-Kopie für Lektionen veröffentlichter Kurse (E-104, Variante C, REV-039…044) | nicht gebaut: braucht Post-Status `ccap_staged`-Kopien mit Übernahme in einer TX je Kurs. Bis dahin gilt: Lektionsänderungen wirken nach dem Speichern; der Kurs-Snapshot (Revision) friert Struktur und Regeln ein. **Klärung/Folgeaufgabe** |
| A-2 | `recompletion_required` (RV-07 Ausnahme, REV-002), Badges/Hinweise „aktualisiert/neu seit Abschluss“ (P-7), `rendered_revision_id` (REV-036) | Datenfelder und Oberfläche fehlen; Folge mit R1-16-Erweiterung bzw. R1-33 |
| A-3 | Drip-/Voraussetzungs-Besitzstand (RV-14/RV-15, REV-006/011/012) | ContentGate berechnet live; Besitzstand für begonnene Activities folgt |
| A-4 | Kursrückzug mit Auslauf (RV-04, REV-026), Lesezugriff auf Historie (RV-20, REV-027) | Archivierung sperrt bereits Lernen und neue Starts (R1-04/R1-14); `withdrawn` nach 24 h für Versuche ohne Limit folgt |
| A-5 | Race-Fälle REV-030…032, REV-038 | mit dem Mehrprozess-Runner R1-42 |
| A-6 | `reason_code` als Spalte in `ccap_course_progress` | nicht als Schemaänderung (Migration); im Ereignis `course.completed` mitgeführt |

Review durch zweite Person vor Merge (AG-05).
