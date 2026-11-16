# R0-GATE · Protokoll Gate A (Portierbarkeit, Spezifikation)

**Stand:** 16.11.2026 · **Status:** Entwurf zur Abzeichnung durch den Auftraggeber (E-184, D12-3). Es gibt keine Implementierung, keinen Testlauf und keinen Commit.
**Grundlage:** Gate-A-Kriterien A-1…A-10 aus `R0-12-tests-gates-backlog.md` §3.1; Beschlüsse in `ENTSCHEIDUNGEN.md` (Beschlussprotokoll, „Von Claude entschieden“, Folgeklärungen E-285…E-312); finaler Konsistenz-Durchgang über alle Dateien in `docs/r0/` (16.11.2026).
**Lesehilfe:** Status **erfüllt** = Nachweis liegt vor und das Prüfskript läuft fehlerfrei. Status **offen** = es fehlt eine Handlung (meist des Auftraggebers, siehe §4). „AG-nn“ = Auftraggeber-Aktion (§4), „NF-nn“ = neue Frage (§5), „A-nn/B-nn“ = Abweichung vom LB-Verhalten (§6).

---

## 1. Ergebnis in Kürze

| Größe | Wert |
|---|---:|
| Gate-A-Kriterien erfüllt / offen | 7 / 3 (A-4, A-7, A-9) |
| **Gate A gesamt** | **offen** – nur noch Auftraggeber-Aktionen (AG-05, AG-07, AG-08, AG-11, AG-13, AG-14) und der Bau des Referenz-Fixtures mit Referenzlauf (A-7) |
| Punkte „Offene Punkte nach Einarbeitung“ in R0-01…R0-40 und R0-02/README | 159 |
| … vollständig durch Beschlüsse erledigt und im Haupttext umgesetzt | 75 |
| … teilweise erledigt (Restteil Auftraggeber, technisch oder neue Frage) | 16 |
| … nur Auftraggeber-Aktion | 29 |
| … technisch an eine R1-Task übergeben (kein Beschluss nötig, kein Gate-A-Blocker) | 24 |
| … neue Frage (§5) | 12 |
| … bewusst akzeptiert bzw. redaktionell außerhalb `docs/r0` | 3 |
| Auftraggeber-Aktionen (konsolidiert, §4) | 14 (davon 6 mit Wirkung auf Gate A) |
| Neue Fragen (§5) | 14 (keine blockiert Gate A) |
| Abweichungen vom LB-Verhalten zur Freigabe nach E-55 (§6) | 6 GM-Abweichungen (A-01…A-06) + 19 weitere (B-01…B-19) |

---

## 2. Skriptergebnisse (wörtlich, 16.11.2026, aus dem Repository-Wurzelverzeichnis bzw. `docs/r0`)

**R0-04 §7 – Prüfliste INV-ID → Baustein** (`cd docs/r0 && python3 -I - <<'PY' …`):

```
Inventar 835 | exclude 111 | nicht-exclude 724
zugeordnet 724 | fehlend [] | exclude zugeordnet [] | doppelt []
```

**R0-12 §5 – Testkataloge und Traceability** (auf alle Kataloge in R0-05…R0-40 erweitert; Auszug, vollständige Ausgabe wörtlich in R0-12 §5):

```
Fälle 67 {'ID': 6, 'LEARN': 10, 'ACCESS': 9, 'COMMERCE': 10, 'SUITEFLOW': 10, 'OPS': 10, 'CORE': 12}
R1-Fälle 46 | ohne R1-Task [] | ohne Test []
unbekannte Test-IDs []
Fälle mit Lücke 46
Kataloge 34 | Dokumente mit Tests 29 ['R0-05', 'R0-07', 'R0-09', 'R0-10', 'R0-13', 'R0-14', 'R0-15', 'R0-16', 'R0-17', 'R0-18', 'R0-19', 'R0-20', 'R0-21', 'R0-22', 'R0-23', 'R0-25', 'R0-26', 'R0-27', 'R0-28', 'R0-29', 'R0-30', 'R0-31', 'R0-33', 'R0-34', 'R0-35', 'R0-37', 'R0-38', 'R0-39', 'R0-40']
Katalogtests 1891 {'A11Y': 66, 'ADM': 104, 'BIG': 36, 'CON': 52, 'CRT': 49, 'CT': 12, 'FIN': 65, 'GM': 210, 'GRANT': 33, 'GRP': 48, 'I18N': 15, 'ID': 15, 'IMP': 32, 'INH': 49, 'INT': 52, 'KOMP': 79, 'LIC': 65, 'LOC': 29, 'MED': 34, 'MIG': 27, 'MS': 23, 'NFR': 19, 'NTF': 37, 'PRIV': 33, 'PSD': 65, 'PUB': 62, 'RBAC': 104, 'RC': 72, 'REC': 26, 'REV': 48, 'SA': 1, 'SEAT': 22, 'SEC': 55, 'SEO': 52, 'SET': 91, 'SUP': 44, 'UX': 65}
ausgenommen (Platzhalter ohne Test) ['KOMP-018', 'KOMP-093', 'KOMP-105']
ohne Ziel [] | mit R1-Ziel 1671 | nur R2/R3/R4 220
R1-43 Tests 9 | Fälle ACCESS-09 OPS-03 SUITEFLOW-01 SUITEFLOW-09
R1-44 Tests 34 | Fälle CORE-11 LEARN-08
R1-45 Tests 32 | Fälle –
R1-46 Tests 30 | Fälle –
R1-Tasks ohne Test [] | mit ≤ 2 Tests []
```

**R0-24 §5 – Register, PAR/LB, DoR:**

```
Register 147 | TODO 147 | fehlend [] | überzählig []
erledigt 3 | offen 144 | führend 102 | Duplikat 40 | obsolet 2
führend: abgedeckt 38 | offen 64 | führende Tasks 53
Fehler {} | unbekannte E [] | unbekannte R-Tasks []
§1.4 Zeilen 53 | Abweichungen []
PAR 16 | LB 15 | Lead ungültig [] | BS doppelt [] | R1-BS fehlend [] | INV-Summe falsch [] | INV R1 546 | GM fehlend 0
INV mit R2 42 | nicht in §3 []
DoR-Zeilen 46 / 46 | abweichend [] | nicht ready 0
```

**R0-27 §8 – Settings-Register** (Zusatzprüfung, OP-27-3):

```
Kap. 43.3 Zeilen: 57 | Zuordnungen: 57
Art: {'Feld/Pro': 43, 'Inv': 5, 'Aktion/Objekt': 5, 'WP': 1, 'Seite': 3}
Register: 86 | mit K43: 52 | ohne K43: 34 | Retention: 21 | INV-S: 13
Tests SET/NTF: 91 / 37 | Ziel: {'R1-31': 63, 'R1-36': 27, 'R1-32': 1, 'R1-21': 37}
Mailtypen: 16 | D27: 10
Fehler: keine
```

---

## 3. Gate-A-Kriterien (R0-12 §3.1)

| Nr. | Kriterium | Nachweis (Datei / Abschnitt / Skriptergebnis) | Status |
|---|---|---|---|
| A-1 | Inventur vollständig | `R0-02-inventar/README.md` (835 Einträge, `unknown: 0`, DoD R0-03); R0-04 §7 Skript: `zugeordnet 724 \| fehlend [] \| exclude zugeordnet [] \| doppelt []` (§2) | **erfüllt** |
| A-2 | R1-Grenze eingefroren | R0-04 „Beschlossen“; Kopplungen K-01…K-25 mit Entkopplungsvertrag (R0-04 §3); Widersprüche W-01…W-12 durch Beschluss aufgelöst (R0-04 §4.1); offene Punkte R0-04 erledigt bzw. an Gate D (AG-03, AG-07, NF-10) | **erfüllt** |
| A-3 | Entscheidungen beantwortet | `ENTSCHEIDUNGEN.md`: E-01…E-284 (mit E-25a, E-39b, E-45a) und Folgeklärungen E-285…E-312 beschlossen am 16.11.2026; im Konsistenz-Durchgang in alle Dokumente eingearbeitet (u. a. E-285 Präfix, E-286 Modulzugang, E-288…E-290 Aufgaben, E-293…E-295 Woo/Voucher, E-296 Reset-Journal, E-299 ADR-Nummern, E-311 Integrationsstatus, E-312 externe Medien). Die neuen Fragen §5 betreffen Gate B/C/D, nicht Gate A | **erfüllt** |
| A-4 | R0-Dokumente freigegeben | Alle Dokumente R0-01, R0-02, R0-04…R0-40 liegen vor und tragen „Beschlossen – Beschlüsse eingearbeitet“; R0-03 ist durch R0-02/README erfüllt. **Fehlt:** Reviewer-Abzeichnung (E-204, Vier-Augen-Prinzip) und Abhaken der 40 Tasks R0-01…R0-40 im TODO durch den Auftraggeber | **offen** (AG-05, AG-14) |
| A-5 | Testkataloge vollständig | R0-12 §5: 1891 Katalogtests in 29 Dokumenten, `ohne Ziel []`; alle 23 Vorschläge aus R0-12 §1.3 übernommen (R0-12 §1.3 „Stand“); Kataloge R0-27…R0-40 gezählt; R1-43/R1-44/R1-45/R1-46 mit 9/34/32/30 Tests; MED-029, SEC-054, SEC-055 → R1-46 | **erfüllt** |
| A-6 | Traceability ohne Lücke | R0-12 §5: `R1-Fälle ohne R1-Task []`, `ohne Test []`, `unbekannte Test-IDs []`; jede Lücke in R0-12 §1.1/§1.2 trägt einen Test-, Task- oder E-Verweis (10 Zeilen im Durchgang ergänzt); R0-24 §5: `Fehler {}`, `GM fehlend 0`, `DoR abweichend []` | **erfüllt** |
| A-7 | Ausführbare Referenztests | Abweichungsliste angelegt (R0-05 §5.1, A-01…A-06; Gesamtliste §6). **Fehlt:** Bau von `cc-fixture-neutral-v1` (E-02) und protokollierter Referenzlauf für GM-029…GM-031, GM-065…GM-068, GM-084, GM-131 (E-56, LB-Deploy-Key E-194); schriftliche Freigabe der Abweichungen (E-55) | **offen** (AG-08, AG-13) |
| A-8 | ADRs akzeptiert | ADR-001…003 und ADR-006 (Teil) R0-06; ADR-004/ADR-005 R0-08; ADR-006 R0-19 §5.4; ADR-007 Kopf R0-39; ADR-008 Kopf R0-07 (Verweis-ADR nach R0-24 T-1); ADR-009 Fehlerformat R0-19 §3 und ADR-010 Event-Envelope R0-19 §5.1 (E-299); zusätzlich ADR R0-25 – alle „beschlossen“ | **erfüllt** (10/10; Abzeichnung mit A-4) |
| A-9 | Datenschutz, Sicherheit, Betrieb | R0-13, R0-16, R0-17, R0-21, R0-31 beschlossen; DSFA-Bedarf bewertet (R0-17 §6); Fristen als Defaults (R0-27 §1.4, R0-31 §2, Aufgaben-Abgaben E-289). **Fehlt:** juristische Bestätigung durch den Betreiber zu E-44, E-51 (inkl. E-289), E-92, E-143, E-145, E-150 sowie Bewertung von AR-01 (`always_load`, E-45a) und des Hinweistexts | **offen** (AG-07) |
| A-10 | R3-Vorbereitung | R0-12 §1.1: alle 55 Prozessfälle mit Owner, Zustand, Fehlerpfad und Testskizze; COMMERCE-/SUITEFLOW-Kataloge als Eingangsbedingung von R3-01 (E-186), FIN-Katalog R0-40 (65 Tests) | **erfüllt** |

---

## 4. Auftraggeber-Aktionen

| Nr. | Aktion | Grundlage | Fundstellen (Offene Punkte) | Wirkung auf |
|---|---|---|---|---|
| AG-01 | Zugriff auf das private Repository `captain-pro` (Kernel, FeatureRegistry, TARIFE, ARCHITECTURE) bereitstellen | E-42, E-273 | R0-16 OP-16-1, R0-18, R0-35, R0-36, R0-39 | Gate D (R3-18) |
| AG-02 | Produkt `course-captain-pro` bei License Captain beauftragen; dabei Entitlement-Namen und Zuschnitt `course.events`/`course.connectors` sowie Release-Readiness-Gates festlegen | E-42, E-95 | R0-11 Nr. 4, R0-16 OP-16-2, R0-39 (K-18/K-19) | Gate D (R3-18) |
| AG-03 | Änderungsauftrag an das Workflow-Captain-Konzept (`wp-workflowmaster`) stellen (LMS-Muster mit schreibenden Aktionen über Grant-Quelle `workflow`) | E-39, E-39b | R0-04 Nr. 6, R0-09 Nr. 3, R0-19 Nr. 6, R0-32, R0-34 Nr. 4 | Gate D (R3-17) |
| AG-04 | Abstimmung mit Form Captain zur Abweichung E-38 (Registry nur Pro) und Partneraufträge Form/Review (F10, V2/V10) | E-38, E-40 | R0-32 | Gate D (R3-07) |
| AG-05 | Personen benennen: Vertretung/zweite Person für Reviews und Tag-Freigaben (Vier-Augen-Prinzip, **vor R1-Start**) sowie Vertretung der Deploy-Freigabe (vor R1-GATE) | E-193, E-204 | R0-16 OP-16-4, R0-18, R0-24 §4.4 | **Gate A (A-4)**, Gate B/C |
| AG-06 | Budgetbetrag für die Nutzertests nennen (vor R1-29) | E-162 | R0-26 OP-26-2 | Gate B (R1-29) |
| AG-07 | Juristische Bestätigungen durch den Betreiber: Fristen E-44, E-51 (einschließlich Aufgaben-Abgaben E-289), E-92, E-143, E-145, E-150; Bewertung AR-01 `always_load` und Hinweistext externe Medien; Shop-Rechtspflichten E-33; Transparenz/Einwilligung zu E-232 | E-33, E-44, E-45a, E-51, E-92, E-143, E-145, E-150, E-232, E-289 | R0-04 Nr. 7, R0-10 OP-10-3, R0-13 OP-13-2, R0-17 OP-17-1/2/4, R0-29 OP-29-1, R0-30 OP-30-3, R0-31 OP-31-3/5, R0-35 | **Gate A (A-9)** für die Fristen und AR-01; Gate D für E-33/E-232 |
| AG-08 | Abweichungsliste (§6, R0-05 §5.1) schriftlich freigeben oder einzelne Punkte zurückweisen; klären, ob „Von Claude entschieden“ als Grundlage genügt | E-55 | R0-05 OP-05-1 | **Gate A (A-7)**, Gate B (E-184) |
| AG-09 | WordPress.org-Konto mit 2FA anlegen; Sicherheitsadresse bereitstellen; optional Reaktionsziel für das Forum festlegen | E-206, E-211, E-212 | R0-35 | Gate C (R1-39, RC-004) |
| AG-10 | Arbeitsregel zur PHP-Untergrenze („älteste Version mit ≥ 6 Monaten Restpflege“) bestätigen | E-195 | R0-20 | Gate C (C-14, R1-30) |
| AG-11 | Clean-Room-Prozess organisatorisch festlegen: wer liest LB-Verhalten/Tests, wer implementiert; Trennung dokumentieren | E-01 | R0-01 | **Gate A** (Eingangsbedingung R1-01) |
| AG-12 | Nutzertests: Pilot und Sitzungsdauer bestätigen; Typmix und Aufgabenkonfiguration der Referenzkurse vor `cc-fx-rk-v1` bestätigen | E-163…E-166 | R0-26 OP-26-1/4 | Gate B (R1-29) |
| AG-13 | Lesenden Deploy-Key für `hastla007/learnboating` (Referenzlauf A-7) und die Cloud-VM für Messungen bereitstellen | E-192, E-194 | R0-05 §5 (E-56), R0-14, R0-38 OP-38-3 | **Gate A (A-7)**, Gate C (R1-22, R1-41) |
| AG-14 | R0-Dokumente abzeichnen, die 40 Tasks R0-01…R0-40 im TODO abhaken und dieses Protokoll freigeben (§7) | E-184, D12-3 | R0-12 §3 | **Gate A (A-4)** |

---

## 5. Neue Fragen (keine Produktentscheidung durch Claude)

Die Nummern NF-09, NF-11, NF-14 und NF-17 sind nicht vergeben (im Durchgang als erledigt bzw. technisch erkannt). Keine Frage blockiert Gate A.

| Nr. | Frage | Anlass / Fundstelle | Klärung vor |
|---|---|---|---|
| NF-01 | Nach E-293 entsteht für unbezahlte Woo-Bestellungen kein Grant. LB erlaubt die **Vorabfreigabe** unbezahlter Bestellungen (`approveOrder`, INV-B-011, z. B. Überweisung). Entfällt sie, wird sie als manueller Grant umgesetzt oder als Ausnahme zu E-293 zugelassen? | R0-09 §2.3 (Manuelle Vorabfreigabe), R0-40 §4.2, R0-13 K-Aktion `grant_manual_approved` | Gate D (R3-02) |
| NF-02 | Gutschein-Zieltyp B (Woo-Produkt): E-305 sieht „Woo-Checkout von Voucher Captain“ vor, E-279 „nur über die Course-Einlösung, nicht im Warenkorb“; R0-40 §2.3 spezifiziert Zieltyp B ohne Woo-Bestellung mit Quelle `voucher`. Welche Regel gilt, und welche Grant-Quelle entsteht? | R0-40 Beschlussstand/§2.3, R0-36 Offene Punkte | Gate D (R3-04) |
| NF-03 | Kursabschluss und Kurszertifikat bei Zugang nur zu einzelnen Modulen (E-27) oder über ein Bundle (E-25a): Gilt „alle Pflichtmodule“ unverändert (Lernende mit Modul-Grant schließen den Kurs nie ab), oder gibt es Abschluss/Nachweis für den freigeschalteten Umfang? | R0-08 §5.1, R0-09 Nr. 2, R0-29 OP-29-3 | Gate B (R1-10, R1-44) |
| NF-04 | Grant auf ein Modul, das eine neue Kursrevision entfernt: bleibt er ohne Inhalt, wird er gemeldet oder (als neue Grant-Revision nach E-117) umgehängt? | R0-23 OP-23-2 | Gate B (R1-27) |
| NF-05 | Aufgabe wird durch eine Revision entfernt, Lernende haben einen offenen Entwurf: bleibt er schreibgeschützt lesbar oder verfällt er? | R0-23 OP-23-1 (RV-22) | Gate B (R1-46) |
| NF-06 | Aufgaben: Standard-Abschlussregel `graded` und die Liste „nicht in R1“ (kein Zurückziehen, keine Retakes, keine Fristen/Spätabgabe) bestätigen | R0-08 OP-08-4, §2.5 | Gate B (R1-46) |
| NF-07 | Supportaktionen für Abgaben (Abgabe zurückgeben, Bewertung korrigieren) in den Katalog K-01…K-17 aufnehmen? | R0-13 OP-13-3 | Gate C (R1-38) |
| NF-08 | Edition von `per_option` (Teilpunkte je Option) nach dem didaktischen Review (E-77): Free oder Pro? | R0-11 Nr. 1 | Gate D |
| NF-10 | Gehören die deutschen Ersatztexte der Woo-Widerrufsfunktion (INV-B-134) zum Umfang von E-33? | R0-04 Nr. 3 | Gate D (R3-02) |
| NF-12 | Setzen automatisch angelegte Woo-Produkte für Zusatzmodule (E-34) die Abhängigkeit zum Basiskurs (E-26) selbst? | R0-40 Nr. 5, R0-09 §3.5 | Gate D (R3-02) |
| NF-13 | E-271 („standardmäßig privat“): Darf der Betreiber das im Wirkungsbericht abwählen, und was gilt bei Deinstallation? | R0-39 | Gate D (R3-02) |
| NF-15 | Schalter „Mehrsprachiger Kurs“, der die Validator-Warnung (E-157) abschaltet? | R0-25 | Gate B (R1-26) |
| NF-16 | Eigene Bedienhilfe „alle Sprachversionen als Bundle vergeben“ (E-25a, E-155)? | R0-25 | Gate B (R1-13, R1-32) |
| NF-18 | Zusätzliche Betreiber-Einstellung zum Default bei Gutschein-Storno (D1), über E-35 hinaus? | R0-36 | Gate D (R3-04) |

---

## 6. Abweichungen vom LB-Verhalten (Freigabe nach E-55)

Zusammengetragen aus R0-05 §5.1 (A-01…A-06, mit GM-Bezug) und allen übrigen Dokumenten (B-01…B-19, Verhalten ohne eigene GM-Abweichungszeile). Ohne Freigabe gilt für einen GM-Erwartungswert das LB-Verhalten (R0-05 §5.1). Spalte „Freigabe“ füllt der Auftraggeber aus (☐ frei · ✗ abgelehnt).

| Nr. | Bereich | LB-Verhalten (Beleg) | Course Captain | Grundlage | Fundstelle | Freigabe |
|---|---|---|---|---|---|---|
| A-01 | Externe Medien (GM-013) | Laden erst nach Einwilligung über ein Theme-Global | Default `always_load`, umschaltbar auf `click_to_load`/`link_only` | E-45, E-45a, E-312 (Auftraggeber) | R0-05 §5.1, R0-10 §5 | ☑ freigegeben 16.11.2026 |
| A-02 | Übungsprüfung aus Entwurf (GM-033) | `examRule()` fällt auf die neueste nicht archivierte Regel zurück, auch einen Entwurf (CR:186-216) | Entwürfe nie startbar; Übung nur als veröffentlichte Regel mit `purpose=practice` | E-129, E-23 | R0-05 §5.1 | ☑ freigegeben 16.11.2026 |
| A-03 | Härtung ab Aktivierung (GM-208) | Härtung wirkt ab Aktivierung, je Merkmal per Filter abschaltbar (INV-C-142) | alles aus, bis der Admin im Assistenten oder Tab speichert; danach `auto` | E-260, E-240 | R0-05 §5.1, R0-31 §3.4 | ☑ freigegeben 16.11.2026 |
| A-04 | `Permissions-Policy` (GM-207) | website-weit mit `microphone=(self)` (INV-C-141) | nur auf Course-Captain-Antworten: `camera=(), microphone=(), geolocation=(), payment=()` | E-309 | R0-05 §5.1, R0-31 §3.4 | ☑ freigegeben 16.11.2026 |
| A-05 | Härtungsumfang (§1.21) | auch `/wp/v2/users` für Gäste, Autorenarchive, Benutzer-Sitemap, oEmbed-/Feed-Autor, Anwendungspasswörter nur Redaktion (INV-C-138) | nicht im R1-Modul (R4-Pool) | E-261, E-308 | R0-05 §5.1, R0-04 | ☑ freigegeben 16.11.2026 |
| A-06 | Abschluss zurücknehmen (GM-209) | Lernender kann `completed` generell zurücksetzen (VS:246-247) | nur `manual_confirm`; nachweisbasierte Abschlüsse nie; Reset durch die Kursverwaltung mit Audit | E-70 | R0-05 §5.1, R0-08 §5.1 | ☑ freigegeben 16.11.2026 |
| B-01 | Standardzugang | `default_access = logged_in`: alle Angemeldeten lernen alles (L-W1, INV-D-036) | kein Vollzugang ohne Grant; Einschreibung „manuell“, Selbstanmeldung aus | Konzept 43.3, R0-07 P-01 (ohne eigene E-Nummer) | R0-07 §6 L-W1, R0-11 C-13 | ☑ freigegeben 16.11.2026 |
| B-02 | Zugriffsfilter | `bootspruefung_can_access` kann Zugang erweitern (L-W2) | Filter darf nur einschränken | E-84 | R0-07 §6 L-W2, CT-10 | ☑ freigegeben 16.11.2026 |
| B-03 | Rechte | drei grobe Caps nur für Administrator, `manage_options` für Protokoll/Einstellungen (L-W3, L-W4) | 24 Caps, vier `ccap_`-Rollen, Objektrechte; nur `administrator` erhält Caps | E-78…E-83 | R0-07 §3, §6 | ☑ freigegeben 16.11.2026 |
| B-04 | Daten an Gruppenbesitzer | Besitzer sieht E-Mail-Adressen aktiver Mitglieder (L-W10) | Name/Anzeigename, E-Mail nur maskiert oder per eigener Cap | E-81 | R0-07 §6 L-W10 | ☑ freigegeben 16.11.2026 |
| B-05 | Monitor-Schlüssel | auch als `?key=` (L-W8) | nur Header `X-CC-Monitor-Key` | R0-07 RG-20 (ohne eigene E-Nummer) | R0-07 §6 L-W8, RBAC-094 | ☑ freigegeben 16.11.2026 |
| B-06 | Wiederaufleben nach Refund | automatische Widerrufe leben wieder auf (EPL:155-164, GS:83-91) | kein Wiederaufleben; neuer Kauf = neue Grant-Revision | E-29 | R0-09 §2.3 | ☑ freigegeben 16.11.2026 |
| B-07 | Verlängerung nach Ablauf | `extend()` setzt `expired` → `active` (ESV:376-378) | nie automatisch; Verlängerung nach Ablauf = neue Revision (G-15) | E-29, E-117 | R0-09 §2.3, RBAC-046 | ☑ freigegeben 16.11.2026 |
| B-08 | Manuellen Widerruf aufheben | `releaseManual` springt auf den Stand vor der Sperre zurück, auch nach `revoked` (ESV:387-423) | `revoked` nicht aufhebbar; Aufheben nur für `suspended`/`approved` | E-29 | R0-09 §2.3 | ☑ freigegeben 16.11.2026 |
| B-09 | Kundenwechsel einer Bestellung | Grant wird still umgehängt | alter Grant `revoked`, neue Revision für das neue Konto | E-117 | R0-09, R0-40 §4.2 | ☑ freigegeben 16.11.2026 |
| B-10 | Unbezahlte Woo-Bestellung | Pending-Eintrag bei offener Zahlung (EPL:47-49, :65-67) | kein Grant bis zur Zahlung (Folgefrage NF-01) | E-276, E-293 | R0-09 G-01, R0-40 §4.2 | ☑ freigegeben 16.11.2026 |
| B-11 | Bestellung im Papierkorb/gelöscht | Widerruf (INV-B-107) | keine Änderung, Reconcile-Hinweis | R0-40 RF-15 (technisch, Konzept 35.2) | R0-40 §4.2, R0-09 RF-15 | ☑ freigegeben 16.11.2026 |
| B-12 | Einladungstokens | Klartext `varchar(64)`, 128 Bit Hex | nur `sha256(token)` gespeichert, 256 Bit | E-112 | R0-09 §1.2 | ☑ freigegeben 16.11.2026 |
| B-13 | Paketmedien | öffentlich in `uploads/bootspruefung/media/`, nur die Frage gesperrt (L-W7) | zugangsgebundene Medien standardmäßig geschützt über PHP | E-65 | R0-10 §4, R0-07 L-W7 | ☑ freigegeben 16.11.2026 |
| B-14 | Kulanz und Einstellungen im laufenden Versuch | Kulanz wird bei jeder Prüfung live gelesen | Grace/Kulanz und Settings beim Start im Snapshot eingefroren | E-225, R0-08 §4.3 | R0-08 §4.3, R0-28 §2 | ☑ freigegeben 16.11.2026 |
| B-15 | Lösungserklärungen | per Feature `learning_explanations` freischaltbar (LS:628) | nur mit Pro; Free zeigt richtig/falsch und ggf. die richtige Antwort | E-20, E-147 | R0-08 §4.5, GM-018/GM-197 | ☑ freigegeben 16.11.2026 |
| B-16 | Prüfungsregelstatus | `verified`/`live` (amtliche Prüfung) | `status` + `purpose`, `evidence_eligible`; Modus „offiziell“ entfällt | E-23, E-75 | R0-05 Beschlussstand, R0-08 §4.1 | ☑ freigegeben 16.11.2026 |
| B-17 | Öffentliche Website-FAQ | öffentliche Website-FAQ (`bp_faq`, Shortcode `[bootspruefung_faq]`, INV-D-056) | Website-FAQ entfällt (Theme/Drittplugin); Kurs-FAQ und Tipps bleiben (`ccap_faq`, `ccap_tip`) | E-16 | R0-19 §4.5, R0-04 BS-24 | ☑ freigegeben 16.11.2026 |
| B-18 | Update-Prüfung | eigener Update-Check (RO:73-181) | Free nur über WordPress.org, kein eigener Updater | E-09 | R0-05 §1.21, R0-39 §1 | ☑ freigegeben 16.11.2026 |
| B-19 | Fehlercodes | `UPPER_SNAKE` bzw. `bp_*` | Kleinschreibung mit Präfix `ccap_` (LB-Code als Herkunft in R0-19 §3.2) | E-06, E-285 | R0-19 §3 | ☑ freigegeben 16.11.2026 |

Nicht in dieser Liste: bewusste Abweichungen von **Suite-Mustern** (nicht LB), z. B. E-38 (Registry nur Pro, Form-Muster), E-39/E-39b (Workflow), E-306 (Pro-Jobs nach Lizenzende eingefroren statt pausiert, R0-32 W1); sie sind in R0-32, R0-36 und R0-39 dokumentiert.

---

## 7. Freigabe

Mit der Unterschrift bestätigt der Auftraggeber die Bewertung der Kriterien A-1…A-10, nimmt die Auftraggeber-Aktionen §4 und die neuen Fragen §5 zur Kenntnis und gibt die in §6 mit ☐ → „frei“ markierten Abweichungen schriftlich frei (E-55). Gate A ist erst grün, wenn A-4, A-7 und A-9 erfüllt sind.

| Feld | Eintrag |
|---|---|
| Gate-A-Ergebnis (grün / rot / grün mit Auflagen) | |
| Freigegebene Abweichungen (Nummern) | |
| Abgelehnte Abweichungen (Nummern, Begründung) | |
| Auflagen / Fristen | |
| Technischer Reviewer (Name, Datum) | |
| Auftraggeber (Name, Datum, Unterschrift) | |

## Nachtrag 16.11.2026 – Beschlüsse des Auftraggebers zum Gate-Protokoll

| Punkt | Beschluss |
|---|---|
| AG-08 / E-55 | **Alle 25 Abweichungen A-01…A-06, B-01…B-19 schriftlich freigegeben** (Auftraggeber). |
| NF-03 / E-326 | Kursabschluss und Kurszertifikat nur mit allen Pflichtmodulen; zusätzlich optionaler **Modul-Abschlussnachweis** je Modul für Lernende mit Modul-Zugang (E-27). |
| AG-11 / E-327 | **Clean Room als Spec-Barriere:** Die R1-Implementierung erfolgt in neuen Sessions bzw. Agenten, die nur `docs/r0`, `konzept.md` und `TODO-WETTBEWERBSANALYSE.md` erhalten, ohne Zugriff auf einen learnboating-Klon. R0-Dokumente enthalten Verhaltensbeschreibungen, Pfad:Zeile-Belege und Methodennamen, aber keinen Quellcode. Der lokale LB-Klon der Spezifikations-Session wird gelöscht. Der Referenzlauf (E-56) wird von einer getrennten Prüfrolle ausgeführt, deren Ergebnisse nur als Erwartungswerte zurückfließen. |
| A-7 / E-328 | Bau von `cc-fixture-neutral-v1` und Referenzlauf werden **Eingangskriterium von R1-18**, nicht von Gate A. A-7 gilt für Gate A als erfüllt, soweit Abweichungsliste und Spezifikation vorliegen. |

## Freigabe

| Feld | Wert |
|---|---|
| Gate | **A (R0-GATE)** |
| Entscheidung | **Freigegeben** |
| Durch | Auftraggeber |
| Datum | 16.11.2026 |
| Auflagen | AG-05 zweite Review-Person vor dem ersten Merge in R1 · AG-07 juristische Bestätigung (Fristen E-44/E-51/E-92/E-143/E-145/E-150/E-289, externe Medien E-45a/AR-01) als Eingangskriterium von **R1-GATE** (E-329) · Referenzlauf als Eingangskriterium von R1-18 (E-328) · Spec-Barriere für die R1-Implementierung (E-327) |
| Offene Auftraggeber-Aktionen ohne Gate-A-Wirkung | AG-01…AG-04, AG-06, AG-09, AG-10, AG-12, AG-13 (siehe §5) |
