# R1-12 · Smarttrainer und Wiederholungsplanung – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-12):** Belegte Algorithmen/Intervalle als neutrale Regeln; deterministische Tests.
**Quellen:** R0-08 §5.2 (`smarttrainer.v1`, ADR-005, Eigenheiten G-1…G-5), R0-05 PAR-06 (GM-065…GM-073) und GM-004. Clean Room (E-01: Regeln aus der neutralen Beschreibung, kein Quelltext).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Learning/SmartTrainer.php` | Feste v1-Parameter (Modi `smart/new/wrong/favorites`, 60 Antworten/min, 300 s Abstand, 3 zuletzt gesehene, Serie 3, 20 h, 7/21/60 Tage). **Fortschrittsupdate** in der Antwort-Transaktion (`INSERT IGNORE` + `FOR UPDATE`): falsch → `unsure`, Serie 0, `due_at` NULL; richtig in `secure`/`mastered` zählt nur fällig (→ `mastered`, Intervall nach Serie); sonst zählt nur mit Abstand (erste Antwort, ≥ 300 s, oder Antwort auf andere Frage seitdem, kursübergreifend), ab Serie 3 `secure` mit +20 h; `mastery_score` nach Formel, 4 Stellen. **Auswahl:** Kandidaten der zugänglichen Module in Modul-/Pool-Reihenfolge, ohne Karteikarten und ohne Fragen laufender Prüfungen (G-5), Themenfilter `COALESCE(smart_category, topic)`, `only_ids`, Favoriten/Sammlung; Modusfilter; Punktwerte 1000 / 800 + 10·min(falsch, 10) / 700 / Basis mit ·0,1 bzw. +150 (schwaches Thema < 0,6 über die Kandidatenmenge); die 3 jüngsten Fragen der letzten 2 h nur als Rückfall; Gewinner strikt `>` → deterministisch; Begründung, Fortschritts-Payload, signiertes Token. **Rate-Limit** atomar in `ccap_rate_limit` (429 `ccap_answer_rate_limited`). **Aggregat** je Stufe, `percent`/`secure_percent` (ohne Daten `null`, nie 0 %), `due`, Themen ab 2 Themen. **Themen** je Modul (GM-004). Injizierbare Uhr für Tests |
| `src/Learning/AnswerService.php` | nutzt das v1-Update (ersetzt die R1-09-Zähler), Rate-Limit vor dem Schreiben, `created_at` aus der Trainer-Uhr, Antwort mit `streak_counted` und Fortschritt; Karteikarten abgewiesen (eigene Zustände, R1-11); Kursaggregat wird bei Wechsel von/zu `mastered` neu berechnet |
| `tests/integration/SmartTrainerTest.php` | 6 Tests, feste Uhr `T0` |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **118 / 1 223** · Multisite **127 / 1 276** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0.

## 3. Tests

| ID | Test |
|---|---|
| GM-065 | 1. Antwort falsch → `unsure`; die nächsten 12 alle „noch nie gesehen“, keine Doppelung; `wrong` liefert sofort die falsche; ohne neue: falsche zuerst mit Grund `last_wrong`; `new` leer → `null` |
| GM-066 / G-3 | Serie 1-2-3 → `secure`, `due_at = T + 20 h`; weitere richtige am selben Tag zählt nicht, erhöht aber `attempts`; Summary `secure=1, mastered=0, percent=0, secure_percent=100, due=0`; ohne Daten `percent = null` |
| GM-067 | nach 21 h `due=1`; neue vor fälliger; fällige mit Grund `review_new_day`; → `mastered`, +7 d; nicht fällig zählt nicht; Serie 5 → +21 d; ≥ 6 → 60 d; falsch → `unsure`, `due_at` NULL, Serie 0 |
| G-1 / G-2 | Antwort auf andere Frage dazwischen zählt; ohne Abstand nicht; richtig nach `unsure` ohne Abstand → `learning`, Serie 0, weiter „zuletzt falsch“; `mastery_score` 0,8000 |
| GM-069 | zweiter Tab: `streak_counted = false`, Serie 1; eben beantwortete Frage nicht sofort wieder, nur als Rückfall |
| GM-072 | Modul-Grant: 4 Ziehungen nur aus dem freigeschalteten Modul; ohne Zugang 403 |
| GM-004 | Themen 12 + 12 = Modulumfang; Lernen nach Thema; unbekanntes Thema → `null`; unbekannter Modus abgewiesen |
| GM-073 | 59 vorbelegt: 60. Antwort ok, 61. → 429; Zähler in der Tabelle, keine Option; nächste Minute wieder möglich |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Teilumfang „nur die ersten *n* Fragen je Modul“ und Drip-Sperren in der Kandidatenmenge | Content-Gate R1-14 (dann in `candidates()` eingehängt); heute: Modulzugang |
| A-2 | Altbestand ohne `due_at` (GM-068) | nur über Migrationswerkzeug (R1-40, E-03), Zweig nicht portiert (INV-A-009) |
| A-3 | Gezielt üben mit 422/404/403-Varianten (GM-074) | HTTP-Schicht R1-28; Dienst nimmt `only_ids` |
| A-4 | Begründungstexte (i18n) | Schlüssel (`never_seen`, `last_wrong`, `review_new_day`, `review_due`, `weak_topic`, `practice`); Texte mit R1-16 |
| A-5 | Antwort-Idempotenz aus dem Token-Hash (LB) | R0-06 macht den Schlüssel nutzerbezogen; Client-UUID wie in R1-09 |

## 5. Übergaben

R1-14 (Teilumfang/Drip in Kandidaten), R1-16 (Texte, Anzeige), R1-28 (HTTP), R1-40 (GM-068), R1-42 (parallele Antworten). Review durch zweite Person vor Merge (AG-05).
