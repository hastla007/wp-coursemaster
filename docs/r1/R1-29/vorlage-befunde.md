# R1-29 · Befundregister (Vorlage)

> Nach R0-26 §4.3 (Befundvorlage) und §4.4 (Schweregrade). Führung als Tabelle in `befunde.csv` (eine Zeile je Befund) oder als Issue mit Label `ux-r1-29`. Ausführliche Befunde zusätzlich im Block-Format unten. Die Vorbereitungsbefunde B-01 … B-07 aus `bereitschaft.md` §2 werden vor Runde 1 als UXB-001 … UXB-007 übernommen oder als erledigt markiert.

## Spalten von `befunde.csv`

| Spalte | Inhalt |
|---|---|
| `id` | `UXB-<nnn>`, fortlaufend, nie wiederverwendet |
| `titel` | Kurztitel |
| `schweregrad` | `S1` · `S2` · `S3` · `S4` (Triage, bei Uneinigkeit die höhere Stufe) |
| `haeufigkeit` | `<k> von <n>` Personen der Rolle |
| `rolle` | Autor · Lernende · Lernende-AT · GV · Admin · Experte |
| `aufgabe` | z. B. `AU-T05` |
| `testfall` | z. B. `UX-005` |
| `screen` | z. B. `A-07`, `L-12` |
| `flow_fehlerpfad` | z. B. `Flow 1, F1.1` |
| `umgebung` | z. B. `AT-01 NVDA 2026.3 / Firefox ESR 140` |
| `beobachtung` | was passiert ist, kurzes Zitat |
| `erwartung` | Soll laut R0-11/R0-15/R0-22/R0-26 |
| `wcag_a11y` | z. B. `2.4.11 / A11Y-006` oder `–` |
| `belege` | Zeitmarke Aufzeichnung, Screenshot-Datei (ohne Personendaten) |
| `empfehlung` | Vorschlag zur Behebung |
| `zustaendig` | Task, z. B. `R1-16` |
| `status` | `offen` · `behoben (<Commit>)` · `Nachtest bestanden (<Sitzung>)` · `akzeptiert (<Entscheid>)` |
| `regressionstest` | automatisierter Test bzw. Eintrag in der Checkliste A11Y-102/103 (Pflicht für behobene S1/S2, R0-26 §4.4) |

## Schweregrade (R0-26 §4.4, Kurzfassung)

| Stufe | Ein Kriterium genügt | Pflicht |
|---|---|---|
| **S1 blockierend** | ★-Aufgabe für ≥ 1 Person unlösbar (bei Hilfstechnik: S-A11Y verletzt); kritischer Fehler; WCAG-2.2-A/AA-Verstoß in Flow 1, 2 oder 4 mit Stufe Z/E; S-02, S-06 oder S-08 verfehlt; Funktionsfehler (z. B. falsche Bewertung) | vor R1-GATE beheben **und** im Nachtest bestätigen; keine Akzeptanz |
| **S2 schwer** | Kernaufgabe nur mit Hilfe/großem Umweg bei ≥ 2 Personen; S-01, S-03, S-04, S-05, S-07, S-09 oder S-10 verfehlt; WCAG-AA außerhalb kritischer Flows; Stufe-B-Kombination blockiert ★-Aufgabe | soll vor R1-GATE behoben sein; Ausnahme nur mit dokumentierter Akzeptanz des Auftraggebers, Workaround in der Doku, Zieltermin erstes Patch-Release, Eintrag im Konformitätsbericht; **nie** bei WCAG-Bezug in Lernenden-Flows |
| **S3 mittel** | Verzögerung/Verwirrung/Bedienfehler mit Selbstkorrektur bei ≥ 2 Personen; Einzelbefund ohne Datenrisiko; JAWS ohne NVDA/VO-Gegenstück | Backlog (R1-Task oder R2), im Bericht nennen |
| **S4 gering** | Kosmetik, Textvorschläge, Wünsche | Backlog |

Ein Befund einer einzelnen Person wird nicht automatisch abgestuft; maßgeblich sind Folge und Kriterium.

## Block-Format für ausführliche Befunde

```markdown
### UXB-<nnn> · <Kurztitel>
- Schweregrad: S1 | S2 | S3 | S4 · Häufigkeit: <k> von <n> Personen (Rolle)
- Aufgabe/Testfall: <AU-T05 / UX-005> · Screen: <A-07> · Flow/Fehlerpfad: <Flow 1, F1.1>
- Umgebung: <AT-01 / BR-07>, Version(en)
- Beobachtung: <was passiert ist, Zitat>
- Erwartung: <Soll laut R0-11/R0-15/R0-22>
- WCAG/A11Y-ID: <2.4.11 / A11Y-006 | –>
- Belege: <Zeitmarke Aufzeichnung, Screenshot ohne Personendaten>
- Vorschlag: <…> · Zuständig: <Task R1-xx> · Status: offen | behoben (<Commit>) | Nachtest bestanden (<Sitzung>) | akzeptiert (<Entscheid>)
```
