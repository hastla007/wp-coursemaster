# E-328 · Referenzlauf des Originals – Ablauf und Protokollvorlage

**Stand:** 23.11.2026 · **Code-Stand Course Captain:** `claude/blissful-curie-tuuf7q` @ `8ac511f`
**Zweck:** Eingangskriterium von **R1-18** (E-328): Referenzlauf des Originals für die Fälle mit Rechenlogik (**E-56**) durch eine **getrennte Prüfrolle** (E-327), protokolliert gegen die Erwartungswerte aus R0-05. Diese Datei beschreibt, wer was tut, und enthält die Protokollvorlage mit Vergleichstabelle. Sie wird von der Prüfrolle ausgefüllt.
**Betroffene GM-Fälle (9):** GM-029, GM-030, GM-031, GM-065, GM-066, GM-067, GM-068, GM-084, GM-131 (R0-05 §1.4, §1.6, §1.8, §1.11; Liste in E-56/EGM-02 und R0-12 §3.1 A-7).
**Status:** **nicht durchgeführt.** Diese Datei ersetzt oder simuliert den Lauf nicht (R1-18-Nachweis §7). Die Course-Captain-Seite hat für alle 9 Fälle eigene Tests (§6, Spalte „Ist CC“); das ersetzt den Referenzlauf nicht.
**Grundlage:** E-01, E-02, E-55, E-56, E-194, E-327, E-328, E-331, E-333, E-335; R0-05 §0 (Kürzel der Quelltests), §1, §2 (Fixture), §3 (Determinismus, D-04 Uhr), §5.1 (Abweichungsliste); R0-GATE-Protokoll §4 AG-13. Clean Room: Diese Datei wurde ohne Zugriff auf den Quelltext des Originals geschrieben; alle Angaben zum Original stammen aus R0-05.

---

## 1. Regeln (verbindlich, E-01/E-56/E-327)

1. **Nur Ausführen.** Die Prüfrolle führt das Original aus (Testlauf bzw. Bedienung) und liest **Ergebnisse** ab. Sie kopiert keinen Code, keine Testdateien und keine Inhalte des Originals in das Course-Captain-Repository, in Tickets oder in Chats mit der Implementierung.
2. **Getrennte Rolle.** Die Prüfrolle ist **nicht** an der Implementierung von Course Captain beteiligt und arbeitet in einer eigenen Umgebung (eigene VM bzw. eigener Container, eigener Klon des Originals). Kein Klon des Originals im Course-Captain-Arbeitsbereich und in keiner Implementierungssitzung (E-327).
3. **Rückfluss nur als Erwartungswerte.** Zurück fließt ausschließlich das ausgefüllte Protokoll (§5, §6): beobachtete Werte, „bestätigt/abweichend“, Abweichungsbeschreibung in eigenen Worten. **Nicht** zurück: Quelltext, Stacktraces mit Codezeilen, Dateiauszüge, Screenshots von Code.
4. **Keine Weitergabe.** Der Deploy-Key ist lesend, wird nur für diesen Lauf (und den nächtlichen Koexistenztest nach E-194) genutzt, nicht weitergegeben und nach dem Lauf in der Prüfumgebung gelöscht.
5. **Abweichungen** zwischen Original und Erwartungswert werden nicht von der Prüfrolle bewertet. Die Inhaberin entscheidet nach E-55, ob der Erwartungswert angepasst oder die Abweichung freigegeben wird.

## 2. Beteiligte

| Rolle | Aufgabe | Person (eintragen) |
|---|---|---|
| Inhaberin (Auftraggeberin) | Deploy-Key bereitstellen (AG-13), Prüfrolle benennen, Variante wählen (§3), Ergebnis abzeichnen, Abweichungen nach E-55 entscheiden | |
| Prüfrolle | Umgebung aufbauen, Lauf ausführen, Protokoll ausfüllen, Key löschen | |
| Vertretung Prüfrolle | wie oben | |
| Koordination Course Captain | nimmt das ausgefüllte Protokoll entgegen, trägt es in `docs/r1/R1-18-nachweis.md` §7 ein, gleicht mit den CC-Tests ab (§6 Spalte „Ist CC“) | |

## 3. Entscheidung vor dem Lauf: Variante

E-56 verlangt einen Lauf „mit synthetischem Paket im LB-Format“ (LB-Format-Zwilling, R0-05 §2.1). Ein solcher Zwilling existiert im Course-Captain-Repository **nicht**; die Implementierung darf ihn ohne Kenntnis des Originalformats nicht bauen (E-327). Daher zwei Varianten:

| Variante | Inhalt | Vorteil | Nachteil |
|---|---|---|---|
| **A – Quelltests ausführen** | Die Prüfrolle führt die in R0-05 je GM genannten Integrationstests des Originals aus (Kürzel CF, LR, CO, OP; R0-05 §0) und meldet je genannter Prüfzeile „bestanden/nicht bestanden“ und die ausgegebenen Zahlen | ohne eigenen Datenbau, schnell, deterministisch | prüft mit den Daten des Originals, nicht mit der neutralen Fixture; Zahlenwerte weichen deshalb ab (R0-05 §0: „Zahlenwerte gelten neu“) |
| **B – LB-Format-Zwilling** | Die Prüfrolle baut aus der **neutralen** Fixture (R0-05 §2: C-A, M-A1…M-A3, ER-A, EP-A-1, U-L1, U-L3, G-4…G-6) ein Paket im Format des Originals, importiert es, stellt die Ausgangslagen her und liest die Werte ab | vergleicht exakt mit den Erwartungswerten der Fixture | Aufwand; einige Ausgangslagen (GM-031 Snapshot-Manipulation, GM-068 Altbestand, GM-084 Quellereignisse) brauchen Eingriffe in die Datenbank oder die Uhr |

**Vorschlag:** **A für alle 9 Fälle** (Pflicht) und **B für GM-029, GM-030, GM-065, GM-066, GM-067** (die Fälle, deren Zahlenwerte direkt aus der Fixture folgen). Wo B nicht herstellbar ist: „n. d.“ mit Grund.

Entscheidung: ☐ A ☐ B ☐ A + B wie vorgeschlagen ☐ anders: ______ · Inhaberin, Datum: ______

## 4. Voraussetzungen

| Nr. | Voraussetzung | Zuständig | Erledigt |
|---|---|---|---|
| V-1 | **Lesender Deploy-Key** für `hastla007/learnboating` (AG-13, E-194), nur für die Prüfumgebung | Inhaberin | ☐ |
| V-2 | Prüfrolle und Vertretung benannt (§2), nicht an der CC-Implementierung beteiligt | Inhaberin | ☐ |
| V-3 | Eigene Prüfumgebung (VM/Container) ohne Course-Captain-Arbeitsbereich; PHP, Datenbank und WordPress in den Versionen, die die CI des Originals für Commit `9f37c77` vorgibt | Prüfrolle | ☐ |
| V-4 | Variante gewählt (§3) | Inhaberin | ☐ |
| V-5 | Für Variante B: Fixture-Beschreibung R0-05 §2 und §3 (öffentliche Doku, kein Code) liegt der Prüfrolle vor | Koordination | ☐ |
| V-6 | Feste Uhrzeitbasis: T0 = 2030-01-15T10:00:00Z (R0-05 §2.1). Das Original hat keine injizierbare Uhr und verschiebt gespeicherte Zeitstempel (R0-05 D-04); Toleranzen der Originaltests (z. B. `abs(…) < 60`) im Protokoll angeben | Prüfrolle | ☐ |

## 5. Ablauf

| Schritt | Handlung | Ergebnis im Protokoll |
|---|---|---|
| 1 | Umgebung aufbauen; Original mit dem Deploy-Key klonen, **Commit `9f37c77` auschecken**, Abhängigkeiten nach der CI des Originals installieren | Commit-Hash, PHP-/WP-/DB-Version, Datum |
| 2 | Testumgebung des Originals so starten, wie dessen CI es tut (R0-05 §0: Integrationstests laufen gegen ein Paket über `PACKAGE_DIR`; der Commerce-Test läuft ohne Woo über den Test-Adapter des Originals) | Kommando (ohne Code), Exit-Status |
| 3 (A) | Je GM die Testdatei ausführen, die in §6 Spalte „Beleg Original“ steht; Ausgabe der genannten Prüfzeilen ablesen | je Prüfzeile: Label (wörtlich, wie ausgegeben), bestanden/nicht bestanden, ausgegebene Werte |
| 3 (B) | Zwilling importieren, Ausgangslage je GM nach §6 herstellen, Aktion ausführen, Werte über Oberfläche, REST oder WP-CLI ablesen | je GM: beobachtete Werte in der Form der Spalte „Erwartung“ |
| 4 | Vergleich je GM: „bestätigt“ (Original verhält sich wie die Erwartung beschreibt), „abweichend“ (in eigenen Worten, was anders ist) oder „n. d.“ (mit Grund) | Spalte „Ist Original“, „Ergebnis“ |
| 5 | Lauf zweimal wiederholen (Determinismus); Unterschiede notieren | Lauf 1/2 gleich? |
| 6 | Deploy-Key und Klon in der Prüfumgebung löschen | Datum |
| 7 | Protokoll an die Koordination und die Inhaberin; Unterschriften §8 | – |

## 6. Vergleichstabelle Erwartung / Ist

Erwartung wörtlich aus R0-05 §1 (Fixture-Kürzel nach R0-05 §2). „Beleg Original“ = Testdatei und Zeile laut R0-05 (Kürzel §0: CF = `integration/content-flow-test.php`, LR = `integration/learning-review-test.php`, CO = `integration/commerce-test.php`, OP = `integration/operations-test.php`). „Ist CC“ = Course-Captain-Test (Stand `8ac511f`, alle grün laut R1-18-Nachweis §9.1).

| GM | Thema | Fixture / Ausgangslage | Erwartung (R0-05) | Beleg Original | Bekannte Abweichung CC (freigegeben) | Ist CC (Test) | Ist Original (A) | Ist Original (B) | Ergebnis |
|---|---|---|---|---|---|---|---|---|---|
| GM-029 | Blueprint/Snapshot | ER-A (M-A1 7, M-A2 13, M-A3 10; P1/P2) | Zählung `{M-A1:7, M-A2:13, M-A3:10}`; `question_parts` `{P1:7, P2:23}`; nachweisfähig (`live` im Original), `reduced=false` | CF:218, CF:434 | Feldname `evidence_eligible` statt `live` (E-23); Teile über Modulgruppen (E-331) | `GoldenMasterTest::test_GM_028_029_er_a`, `test_GM_029_parts_over_module_groups`; `ExamTest::test_E331_part_contract_and_validator` | | | ☐ bestätigt ☐ abweichend ☐ n. d. |
| GM-030 | Teilgrenze | ER-A; die ersten 3 P1-Fragen falsch, Rest richtig | 27/30; P1 4/7 < 5 → nicht bestanden; P2 23/23 bestanden; gesamt `passed=false` | CF:234, CF:235 | – | `GoldenMasterTest::test_GM_030`, `test_GM_030_part_p2_over_module_group` | | | ☐ bestätigt ☐ abweichend ☐ n. d. |
| GM-031 | Snapshot-Vorrang | ER-A-Sitzung, Snapshot: alle Fragen → P2; alle richtig | P1 `total=0, empty=true, passed=true`; P2 30/30; `passed=true` | CF:442 | Feedback-Anzeige nach E-20/E-147 (betrifft nicht die Wertung) | `GoldenMasterTest::test_GM_031`, `test_GM_031_snapshot_parts_take_precedence` | | | ☐ bestätigt ☐ abweichend ☐ n. d. |
| GM-065 | Smarttrainer-Reihenfolge | U-L1, M-A1 (24 Fragen ≥ 20) | 1. Antwort falsch → `unsure`; die nächsten 12 alle „noch nie gesehen“, keine Doppelung; Modus `wrong` liefert die falsche sofort; ohne neue: falsche zuerst, Grund „zuletzt falsch“ | LR:112, :126, :127, :129, :131 | – | `SmartTrainerTest::test_GM_065_order` | | | ☐ bestätigt ☐ abweichend ☐ n. d. |
| GM-066 | Stufen | Frage aus GM-065 | Serie `[1,2,3]`, `secure`; `due_at = T + 20 h`; weitere richtige Antwort am selben Tag zählt nicht; Summary `secure=1, mastered=0, percent=0, secure_percent=100, due=0` | LR:146, :148, :152, :154 | – | `SmartTrainerTest::test_GM_066_067_stages_and_intervals` | | | ☐ bestätigt ☐ abweichend ☐ n. d. |
| GM-067 | Intervalle | Uhr T0 + 21 h, dann fällig | `due=1`; neue vor fälliger; fällige `secure` vor nicht fälligen; anderer Tag → `mastered`, due + 7 d; nicht fällig zählt nicht; fällig richtig → + 21 d; falsch → `unsure`, `due_at=null`, Serie 0 | LR:158, :161, :163, :166, :168, :172, :174, :177 | – | `SmartTrainerTest::test_GM_066_067_stages_and_intervals` | | | ☐ bestätigt ☐ abweichend ☐ n. d. |
| GM-068 | Altbestand | 2 Zeilen `mastered`, zuletzt T0 − 8 d bzw. T0 − 1 d, ohne `due_at` | `mastered=2, percent=100, due=1`; `dueAt = last + 7 d`; fällige Frage wird vorgelegt | LR:186, :187, :189 | Nachrüstung `due_at` nur im Migrationswerkzeug (E-335) | `LearningStagesTest::test_GM_068_legacy_rows_without_due_at` | | | ☐ bestätigt ☐ abweichend ☐ n. d. |
| GM-084 | Laufzeit, Verlängerung, parallel | G-4 (30 d), G-5 (`extend`), G-6 (`parallel`) für U-L3 | G-4 endet T0 + 30 d; G-5 beginnt an dessen Ende; G-6 beginnt T0; Übersicht 1 Eintrag mit spätestem Ende | CO:183, :184, :189, :191, :219 (Original ohne Woo über seinen Test-Adapter) | Quellereignisse über den neutralen Adapter FakeSource statt Bestellungen (R0-05 §2.8) | `AccessTest::test_GRANT_012_chain_and_restack` | | | ☐ bestätigt ☐ abweichend ☐ n. d. |
| GM-131 | Aufbewahrung Import | 4 Paketkopien, Uhr + 200 d | aktives Paket + jüngstes Rollback-Ziel + laufender Import bleiben; ältere gelöscht; Protokoll `unchanged` 14 d / Änderungen 180 d | OP:224, :228, :242, :243, :244, :245, :246 | Fristen als Defaults nach E-44 (juristische Bestätigung AG-07) | `PrivacyOpsTest::test_retention` | | | ☐ bestätigt ☐ abweichend ☐ n. d. |

Hinweis zu GM-029/GM-030: Die Erwartung verlangt zwei Teile (P1 = M-A1; P2 = M-A2 + M-A3). Nach R0-05 §2.4 gilt zusätzlich „P2 ≥ 18/23“. Variante A prüft das mit den Daten des Originals; die Prüfrolle notiert, ob das Original Teile über mehrere Module bildet (Grundlage von E-331).

## 7. Protokollkopf (auszufüllen)

```markdown
# Referenzlauf E-328 · Protokoll
- Datum: <JJJJ-MM-TT> · Dauer: <h>
- Prüfrolle: <Name> · Vertretung: <Name> · nicht an der CC-Implementierung beteiligt: ja
- Variante: A | B | A+B
- Original: hastla007/learnboating @ 9f37c77 (Hash ausgegeben: <…>)
- Umgebung: <OS>, PHP <…>, WordPress <…>, Datenbank <…> · Uhrbasis/Toleranz: <…>
- Kommandos (ohne Code, nur Aufrufe): <…>
- Lauf 1 und Lauf 2 identisch: ja | nein (<Unterschiede>)
- Deploy-Key und Klon gelöscht am: <Datum>
- Rückfluss geprüft (kein Code, keine Codeauszüge, keine Inhalte des Originals): ja
```

Je GM eine Zeile in §6 ausfüllen. Bei „abweichend“ zusätzlich:

```markdown
### Abweichung GM-<nnn>
- Beobachtet (eigene Worte, Werte): <…>
- Erwartung laut R0-05: <…>
- Reproduzierbar: ja | nein
- Vorschlag an die Inhaberin (E-55): Erwartungswert anpassen | Abweichung freigeben | klären
```

## 8. Abschluss und Unterschriften

| Feld | Eintrag |
|---|---|
| Ergebnis | ☐ alle 9 GM bestätigt ☐ bestätigt mit Abweichungen (Nummern): ______ ☐ nicht durchführbar (Grund): ______ |
| Prüfrolle (Name, Datum, Unterschrift) – bestätigt: nur ausgeführt, kein Code übernommen | |
| Inhaberin (Name, Datum, Unterschrift) – Ergebnis zur Kenntnis, Entscheidungen zu Abweichungen nach E-55 | |
| Koordination (Datum) – eingetragen in `R1-18-nachweis.md` §7 und `R1-GATE-protokoll-entwurf.md` | |

Mit der Unterschrift ist das Eingangskriterium E-328 von R1-18 erfüllt (sofern alle Abweichungen entschieden sind). Abweichungen, die die Inhaberin nicht freigibt, werden als Befund an R1-08/R1-09/R1-12/R1-13/R1-15 zurückgegeben.

## 9. Wartet auf …

| Punkt | Wartet auf | Zuständig |
|---|---|---|
| Lesender Deploy-Key `hastla007/learnboating` (AG-13, E-194) | Bereitstellung | Inhaberin |
| Benennung Prüfrolle + Vertretung | Benennung | Inhaberin |
| Wahl der Variante (§3) | Entscheidung | Inhaberin |
| Prüfumgebung (VM/Container getrennt vom CC-Arbeitsbereich; dieselbe Cloud-VM wie für Messungen nach AG-13 möglich, aber eigener Nutzer/Ordner) | Bereitstellung | Inhaberin / Prüfrolle |
| Durchführung und Protokoll | Lauf | Prüfrolle |
| Entscheidung zu Abweichungen (E-55) | Freigabe | Inhaberin |
