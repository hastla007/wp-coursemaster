# R1-06 · Fragenbank und neutrale Fragetypen – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-06):** Single/Multi/Open/weitere belegte Typen, Revisionen, Medien und Feedback.
**DoR (R0-24):** `CourseCaptain\Question` (Typ-Registry, Revisionen); Tests GM-024, GM-025, SEC-016 (PAR-03: GM-017…GM-026); Kommando `composer test -- --group=R1-06`.
**Quellen:** R0-08 §1.3, §1.4 Nr. 5, §3.1–§3.4; R0-06 §4.1, §5; R0-05 §1.22; Beschlüsse E-20, E-21, E-22, E-50, E-69, E-77, E-147. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Question/QuestionTypes.php` | 9 Typen: `single_choice`, `true_false`, `multi_choice`, `open_text`, `flashcard`, `numeric_tolerance` (E-22), `matching`, `ordering`, `cloze` (E-69); `chart_task` ausgeschlossen (LB-10). Veröffentlichungsvalidierung je Typ, Auslieferung ohne Lösungsdaten, Bewertung in Tausendstelpunkten; `all_or_nothing` (Standard) und `proportional_penalty` nur für multi/matching/cloze; `per_option` in R1 abgewiesen (E-77); Festkomma für Zahlen (×10⁶), Lückentext-Normalisierung (trim, NFC, Leerzeichen, Groß-/Kleinschreibung je Frage); ungültige Eingabe ≠ falsch; leere Eingabe = unbeantwortet |
| `src/Question/Evaluation.php` | Ergebnis (`valid`, `answered`, `points_milli`, `max_milli`, `correct()`) |
| `src/Question/Randomizer.php` | Seed-basierte Ströme `HMAC-SHA256(seed, purpose|counter)`, Fisher-Yates mit Rejection Sampling, `randomizer_version` 1; feste Optionen (`pinned`), `true_false` ungemischt, Sortieren nie in Lösungsreihenfolge, Zuordnung mischt nur rechts |
| `src/Question/QuestionRepository.php` | Identität + Revisionen: Entwurf wird überschrieben, veröffentlichte Revision nie (Änderung = neue Revision, `change_class`), bekannter Hash → vorhandene Revision, Veröffentlichen validiert und setzt den Vorgänger auf `deprecated`, Archiv statt Löschen; Antworten und Tags je Revision; Modul-Pool mit Reihenfolge (NULL zuletzt, dann ID); Pool liefert nur veröffentlichte, gültige Fragen (GM-023); Rechte über M-07/M-06 |
| `src/Question/QuestionPresenter.php` | Auslieferung im Lernmodus mit HMAC-signiertem Token (Nutzer, Frage, Revision, Kurs, Modul, Reihenfolge, Ablauf 2 h); jede Antwort/Aufdecken prüft Token **und** Zugang erneut; Fragen der laufenden Prüfung gesperrt (SEC-016); Erklärungstexte nur mit Pro (Filter `course_captain_explanations_available`, Standard `false`, E-20) |
| `src/Policy/PolicyService.php` | Fehlerkorrektur: `flush()` leert den Cache, statt die Instanz zu verwerfen (Dienste mit gehaltener Referenz sahen sonst veraltete Entscheidungen) |
| `tests/unit/QuestionTypesTest.php`, `tests/integration/QuestionBankTest.php` | 9 + 7 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **82 / 2 626** · Integration **63 / 766** · Multisite **72 / 819** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · `.pot` neu erzeugt.

## 3. Tests

| ID | Test |
|---|---|
| GM-017 | `QuestionTypesTest::test_GM_017_GM_026_*`, `QuestionBankTest::test_GM_017_024_025_*` – Auslieferung ohne `correct`/Erklärung |
| GM-019 | `test_GM_019_choice_and_open` (Teil-/Obermenge falsch) |
| GM-020 | `test_GM_020_reveal_and_no_access` (Musterantwort, Selbsteinschätzung) |
| GM-022 | `test_GM_022_ticking_everything_never_wins` (Anker je Strategie; Publish-Validator-Teil R1-26) |
| GM-023 | `test_GM_023_pool` (Pool-Teil; Prüfungsteil R1-08) |
| GM-024 | manipuliertes, fremdes, kaputtes Token → `ccap_invalid_token` |
| GM-025 | TTL 7 200 s; nach Widerruf `ccap_content_locked`; archivierte Frage 404 |
| GM-026 | Reproduzierbarkeit je Seed, χ²-Gleichverteilung über 400 Ziehungen |
| GM-191…GM-194 | Zahl mit Toleranz, Zuordnung, Sortieren, Lückentext mit den Fällen aus R0-05 §1.22 |
| SEC-016 | `test_SEC_016_running_exam_locks_questions` |
| R0-06 §5 | `test_revisions`, `test_publish_validation`, `test_author_scope` |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Gewicht je Frage | Standard 1 000 Tausendstel; Gewichtung aus der Prüfungsregel kommt mit R1-08 |
| A-2 | Bekannter Hash beim Speichern | liefert die vorhandene Revision zurück; aktiv wird sie erst durch Veröffentlichen (Import-Rollback in R1-15) |
| A-3 | Lernmodus-Mischung | frischer Seed je Auslieferung (CSPRNG), Reihenfolge im Token |
| A-4 | Medienzuordnung der Revision | Tabelle `ccap_content_media` vorhanden; Pflege/Auslieferung mit R1-07 (Lernmedien) |
| A-5 | Antwort-Endpunkt, Idempotenz, Speichern in `ccap_attempt`, Smarttrainer | R1-09/R1-11/R1-12; dieser Task liefert Bewertung und Token |

## 5. Übergaben

GM-018 (Doppel-Submit, Ausgabe) → R1-09/R1-28; GM-021 (offene Frage in der Prüfung) → R1-08; GM-004 (Themen) → R1-12; Medien → R1-07; Review durch zweite Person vor Merge (AG-05).
