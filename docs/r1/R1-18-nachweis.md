# R1-18 · Golden-Master- und E2E-Abnahme – Teilnachweis (Fixture und GM-Suite)

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v` · **Status: in Arbeit (nicht abgehakt)**
**Eingangskriterium (E-328):** `cc-fixture-neutral-v1` gebaut **und** Referenzlauf des Originals für Rechenlogik-Fälle (E-56) durch eine getrennte Prüfrolle protokolliert.
**Quellen:** R0-05 §2 (Fixture), §1 (GM-Katalog), D-03 (keine Testreihenfolge). Clean Room (E-01): synthetische, rechtefreie Inhalte, keine Originalinhalte.

## 1. Erledigt

| Datei | Inhalt |
|---|---|
| `tests/support/Fixtures/NeutralFixture.php` | deterministische Pakete PKG-v1/PKG-v2: C-A „Kurs Alpha“ (M-A1 24 mit Themen T-A1-a/b je 12, M-A2 16, M-A3 10; Lektionsmodule LM-A1…3 mit L-A-01…09, L-A-06 Platzhalter), C-B „Kurs Beta“ (M-B1 12, M-B2 8, L-B-01/02); Fragetypen und Bereiche nach §2.3, Q-A2-015/Q-A3-009 alle Optionen richtig, richtige Antwort immer an erster Stelle; PKG-v2 nur Q-A1-007 „(korrigiert)“; idempotenter Loader (Nutzer `cc_fx_*`@fixture.test, Import PKG-v1, C-C Entwurf, C-D archiviert, G-1/G-2/G-7 relativ zu T0, AR-01) |
| `tools/fixtures/README.md` | Beschreibung |
| `tests/integration/GoldenMasterTest.php` | 6 Tests auf der Fixture: Zählungen (70 veröffentlichte Fragen, Pools 24/16/10/12/8) und Idempotenz; GM-001 (`[cc-a, cc-b]`); GM-009 (Nenner 8); GM-022 (alle 5 richtig, 4 von 5 falsch); GM-004 (12 + 12); AR-01 (3 je Fragenmodul); GM-086 (abgelaufen bleibt abgelaufen); PKG-v2 (69 unverändert, 1 Update, Lektionen unverändert, neuer Text aktiv) |
| `src/Import/ImportService.php` | **Fix aus der Fixture:** Paket-Antwortschlüssel wie `A1-001-a` werden positionsweise auf das interne Format `^([A-Z]|MODEL)$` (R0-08 §3.1) abgebildet |

Ergebnisse 16.11.2026: phpcs 0 · PHPStan L8 0 · Unit 85 · Integration **156 / 1 593** · Multisite **165 / 1 646** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0.

## 2. Offen bis zum Abhaken

| Punkt | Zuständig |
|---|---|
| Referenzlauf des Originals für die Rechenlogik-Fälle (E-56) mit LB-Format-Zwilling | getrennte Prüfrolle (E-328), nicht durch die Umsetzung |
| Medien MED-001…005, MED-BAD-1…6, Downloads DL-A-1…3, Seiten PAGE-1/2, Prüfungsregeln ER-A/ER-B/EP-A-1…3, Karteikarten FC-A/FC-B, weitere Nutzer/Regeln AR-02…08, Gruppen | Erweiterung der Fixture zusammen mit R1-41 (Medien-/Blueprint-Import) |
| Vollständiger GM-Durchlauf GM-001…GM-196 auf der Fixture und E2E gegen laufende Site; Gates B/C | nach Abschluss der übrigen R1-Aufgaben |
| Klärung: Antwort-IDs `<frage>-a…e` (R0-05 §2.3) vs. Schlüssel `^([A-Z]|MODEL)$` (R0-08 §3.1) | Auftraggeber; umgesetzt: Abbildung beim Import |

Review durch zweite Person vor Merge (AG-05).
