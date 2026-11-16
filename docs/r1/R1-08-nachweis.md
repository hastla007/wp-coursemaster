# R1-08 · Prüfungsblueprints und Versuche – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-08):** Prüfungsregeln, Blueprints, Varianten, Versuchsautomat mit Snapshot.
**Quellen:** R0-08 §4.1–§4.4, §6.1–§6.6, §7.1; R0-06 §4.2; R0-05 PAR-04 (GM-021…GM-048, GM-210); Beschlüsse E-23, E-71, E-74, E-75, E-108, E-113, E-122, E-123, E-129, E-286. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Exam/ExamRules.php` | Regel mit Pflicht-Bestehensregel `min_ratio {num, den}` (kein Standardwert, INV-C-132), Status `draft → published → retired` (veröffentlichte Regeln unveränderlich, `ccap_rule_frozen`), Blueprint je Modul (Anzahl, Punkte 1–100), feste Varianten, Einstellungen (Zweck evidence/practice, Versuchspolitik, Feedback, Mischen, Grace 5 s, Kulanz 120 s, Timer-Modus), Veröffentlichen mit M-04 |
| `src/Exam/ExamSessions.php` | Start unter `exam_start:<user>` (10 s): Zugang mit Scope `exam` zu Kurs **und** allen Blueprint-/Variantenmodulen (E-286), nur veröffentlichte gültige Regel (`ccap_exam_not_startable`), abgelaufene Versuche vorher werten, laufenden Versuch derselben Form bzw. gleichen `request_key` zurückgeben, Versuchsgrenze und Sperrfrist; Ziehung je Blueprint aus dem Pool (Seed-Strom `pool:<modul>`, gruppiert nach Abschnitt), verkürzt bei zu kleinem Pool; Antwortreihenfolge materialisiert; Snapshot (Regel-Hash, Nachweisfähigkeit, reduziert/Übung, Timing inkl. Grace/Kulanz, Bestehensregel, Seed, Punkte/Wertung/Abschnitt je Position, Feedback, Versuchspolitik, Bewertungsversion) + `snapshot_hash`, alles in **einer** Transaktion; Ansicht ohne Lösungen; Antworten/Markieren/Aufdecken mit `FOR UPDATE` + CAS, Frist + Grace, offene Fragen erst nach Aufdecken, leere Eingabe nimmt zurück, ungültige → `ccap_exam_invalid_answer`; Abgabe idempotent; Abbruch (abgelaufene Versuche werden stattdessen gewertet); Fristablauf-Job; Bewertung nur gegen Snapshot und eingefrorene Fragenrevisionen, Festkomma-Vergleich `points · den ≥ num · max`, optionale Teilgrenzen je Abschnitt; defekter Snapshot → `broken` |
| `src/Question/QuestionPresenter.php` | Prüfungssperre im Lernmodus ohne Zeitlimit höchstens 24 h (GM-044) |
| `src/Kernel/CronHooks.php` | stündlich: Fristablauf |
| `tests/integration/ExamTest.php` | 12 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **99 / 1 010** · Multisite **108 / 1 063** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · `.pot` neu.

## 3. Tests

| ID | Test |
|---|---|
| INV-C-132 | Regel ohne Bestehensregel abgewiesen; veröffentlichte Regel eingefroren; neue Version setzt alte auf `retired` |
| GM-027 / 028 / 029 | Start und Fortsetzen; keine Lösungen; Zusammensetzung nach Blueprint; Snapshot-Hash, Grace/Kulanz eingefroren |
| GM-032 / 033 | verkürzt und Übung nie nachweisfähig; Entwurf nicht startbar |
| GM-034 | feste Variante in Positionsreihenfolge |
| GM-039 | Bewertung, idempotente Abgabe, keine Änderung danach |
| R0-08 §7.1 | Autorenänderung nach Start ändert das Ergebnis nicht |
| GM-040 / 041 | Abbruch ohne Wertung, Wiederholung ohne Wirkung, fremd 404; abgelaufener Versuch wird beim Abbruch gewertet |
| GM-035 | Grace, späte Antwort `ccap_exam_expired`, Auswertung nach Kulanz |
| GM-021 / 047 | offene Frage erst nach Aufdecken; Markierungen idempotent, unbekannte Position 404 |
| GM-043 / 044 | Prüfungsfragen im Lernmodus gesperrt, ohne Limit höchstens 24 h |
| GRANT-027 | nach Widerruf keine Antworten, Abgabe wird gewertet (E-113) |
| GM-210 | Versuchsgrenze und Sperrfrist |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Erklärungsschalter `show_explanations` | nicht in den Free-Standardwerten (Free-Guard G-12); Pro ergänzt ihn |
| A-2 | Teilgrenzen | je Abschnitt (Blueprint-Modul) als `min_ratio`; die LB-Neuberechnung mehrteiliger Regeln (R0-08 §4.1) folgt mit R1-20, falls Prüfungen mit Teilen genutzt werden |
| A-3 | Nachteilsausgleich, Timer-Modus `practice`, Start-Rate-Limit 40/h, Offline-Kulanz mit Gerätezeit (`age_ms`) | R1-20 (Prüfungsintegrität/Wiederaufnahme) |
| A-4 | Ergebnisanzeige mit Feedback-Regeln, Nachbewertung (`evaluation_no > 1`) | R1-09 |
| A-5 | Drip/Teilumfang beim Start (GM-048) | R1-14 (Content-Gate) |
| A-6 | `shuffle_answers = false` | Antwortreihenfolge bleibt leer (Autorenreihenfolge bei der Auswertung erlaubt alle Optionen); die Ansicht zeigt dann die gemischte Auslieferung – Anpassung mit R1-16 |

## 5. Übergaben

R1-09 (Ergebnis/Feedback), R1-14 (Drip/Teilumfang), R1-20 (Integrität), R1-27 (GM-052 Revisionsimport während Versuch), R1-42 (Parallelstart). Review durch zweite Person vor Merge (AG-05).
