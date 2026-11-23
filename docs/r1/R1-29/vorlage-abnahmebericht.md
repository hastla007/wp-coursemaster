# R1-29 · Abnahmebericht Nutzertests (Vorlage)

> Nach R0-26 §4.2 Schritt 9 und §5.5 (UX-091). Ergebnis geht in **R1-GATE** (Gates B/C, Konzept 28.6). Es werden **keine** erfundenen Werte berichtet: Fehlende Werte stehen als „nicht gemessen“ (R0-26 §7). Freigabe nur durch den Auftraggeber.

**Stand:** <Datum> · **Build:** <Plugin-Version, Commit> · **Paket:** cc-fx-rk-v1 (<Commit>) · **Runden:** Pilot <Datum>, Runde 1 <Zeitraum>, Runde 2 <Zeitraum>
**Moderation:** <Kürzel, extern/intern> · **Triage:** <Produkt, Entwicklung, A11Y-Fachperson>

## 1. Zusammenfassung

- Abnahme empfohlen: ja | nein | mit Auflagen
- Offene S1: <Anzahl> (Bedingung: 0) · offene S2: <Anzahl> (davon akzeptiert: <Anzahl>, je mit Entscheid)
- Wichtigste Erkenntnisse (höchstens 5 Punkte): <…>

## 2. Durchführung

| Rolle | geplant (E-163) | durchgeführt Runde 1 | Runde 2 | Abweichung / Grund |
|---|---|---|---|---|
| Autor | 6 (2 je RK, SOLL 1 mit Tastatur/Lupe) | | | |
| Lernende ohne Hilfstechnik | 5 (3 mobil, 2 Desktop) | | | |
| Lernende mit Hilfstechnik | 4 (Profile a–d) | | | |
| Gruppenverwaltung | 5 (3 unmoderiert) | | | |
| Admin | 5 (1 Sitzung en_US) | | | |
| Expertendurchläufe | AT-01 … AT-09 | | | |

Bereitschaft zum Zeitpunkt der Runde (aus `bereitschaft.md`, gegen den Release-Kandidaten neu geprüft): <Tabelle oder Verweis; nicht durchführbare Aufgaben mit Grund>

Geräte- und Browserabdeckung (R0-26 §3.2, BR-01 … BR-09; AT-01 … AT-10 mit Versionen): <Tabelle>

## 3. Schwellen (R0-26 §2.5)

| Nr. | Kriterium | Schwelle | Ist (Runde 1) | Ist (Runde 2) | erfüllt | Befund bei Verfehlung |
|---|---|---|---|---|---|---|
| S-01 | Erfolg je Kernaufgabe ohne Hilfstechnik | ≥ 80 % (n = 5: 4 von 5; n = 6: 5 von 6) | je Aufgabe: Anhang A | | | S2 |
| S-02 | Erfolg je kritischer Aufgabe ★ | ≥ 80 % **und** 0 kritische Fehler | | | | S1 |
| S-03 | Gesamterfolg je Rolle | ≥ 85 % | | | | S2 |
| S-04 | SUS Mittelwert je Rolle | ≥ 70, höchstens 1 Person < 50 | | | | S2 |
| S-05 | SEQ Median je Kernaufgabe | ≥ 5,0; kritische Lernenden-Aufgaben ≥ 5,5 | | | | S2 |
| S-06 | Referenzkurs veröffentlicht (AU-GES) | Median ≤ 90 min, keine Person > 120 min, jede Version `check-rk` grün | | | | S1 |
| S-07 | Setup-Assistent (AD-T01) | Median ≤ 10 min | | | | S2 |
| S-08 | Wiederaufnahme Prüfung (LE-T06) | Median ≤ 1 min, 0 verlorene Antworten | | | | S1 |
| S-09 | Ende-zu-Ende Lernende (LE-GES) | Desktop ≤ 60 min, mobil ≤ 70 min (Median, ohne Lesezeit) | | | | S2 |
| S-10 | GV-GES / AD-GES | ≤ 15 min / ≤ 30 min Median | | | | S2 |
| S-A11Y | Hilfstechnik je Person | jede ★-Aufgabe ohne sehende Hilfe; SEQ-Median ≥ 4,5 | je Person: Anhang B | | | S1 |
| S-EXP | Expertendurchläufe A11Y-102/103 | keine offenen S1 | | | | S1 |

Änderungen an S-06 … S-10 nach Benchmark/Pilot (einmalig, vor Runde 1 eingefroren, E-166-Werte nicht gelockert): <keine | Änderung mit Begründung und Entscheid des Auftraggebers>

## 4. Testfälle UX-xxx

| ID | Aufgabe | ★ | Ergebnis (Quote, Median-Zeit, SEQ) | Befunde | Status |
|---|---|---|---|---|---|
| UX-001 … UX-017 | Autor | | | | |
| UX-020 … UX-038 | Lernende | | | | |
| UX-050 … UX-067 | GV/Admin | | | | |
| UX-070 … UX-081 | Hilfstechnik/Experten | | | | |
| UX-090 | Praxis-Benchmark (Baseline, keine Schwelle) | – | Zeit je AU-Teilaufgabe, alle drei RK, einer mit 50 manuellen Fragen | | |
| UX-091 | dieser Bericht | – | 0 offene S1; S2 behoben oder akzeptiert | | |

## 5. Befundliste mit Status

Aus `befunde.csv` (alle S1/S2 einzeln, S3/S4 zusammengefasst):

| ID | Titel | Stufe | Häufigkeit | Aufgabe/Testfall | Status | Nachtest | Regressionstest |
|---|---|---|---|---|---|---|---|
| | | | | | | | |

**Akzeptierte S2** (nur mit Entscheid des Auftraggebers, Workaround in der Doku, Zieltermin erstes Patch-Release, Eintrag im Konformitätsbericht E-125; nie WCAG-Bezug in Lernenden-Flows): <…>

## 6. Baselines für das Messkonzept (R0-26 §7)

| Kennzahl | Baseline aus | Wert | Messmethode |
|---|---|---|---|
| Zeit bis zur Kursveröffentlichung | UX-013, UX-090 | | Median/Maximum AU-GES, Benchmark je Abschnitt |
| Quote erfolgreicher Kurs-Erstellungen | UX-013 | | Anteil `check-rk` grün |
| Anteil abgeschlossener Setup-Assistenten | UX-060/061 | | Erfolgsquote AD-T01/T02 |
| Kursabschluss Referenzkurs | UX-033 | | Erfolgsquote LE-GES |
| Fehlerquote Prüfungsabgabe | UX-026, UX-032 | | kritische Fehler je Abgabe |
| Lernsession-Fortsetzungsrate | UX-030 | | Erfolg und Zeit LE-T10 |
| Job-Wiederherstellung | UX-064 | | Erfolgsquote AD-T05 |
| Supportlast | alle | | H1/H2 je Aufgabe |

Konversion Free → Pro wird nicht gemessen.

## 7. Datenschutz

Einwilligungen vollständig: ja/nein · Aufzeichnungen gespeichert unter <Ort>, Löschtermin <R1-GATE + 6 Monate> (E-167) · Protokolle pseudonym im Repo · R0-17-Bewertung der Hilfstechnik-Angaben: <Datum, Ergebnis>

## 8. Freigabe

| Rolle | Name/Kürzel | Datum | Entscheidung |
|---|---|---|---|
| Auftraggeber | | | freigegeben | nicht freigegeben | mit Auflagen |
| Review (zweite Person, AG-05) | | | |

## Anhang

- A: Erfolg, Zeit, SEQ je Aufgabe und Person (aus `protokoll-aufgaben.csv`)
- B: S-A11Y je Person mit Hilfstechnik
- C: Protokolle `protokolle/P-*.md`
- D: Expertendurchläufe je Kombination
