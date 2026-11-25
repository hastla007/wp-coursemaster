# R1-GATE · Protokoll Gates B und C (Entwurf)

**Stand:** 25.11.2026 (Fortschreibung nach den Beschlüssen E-383…E-392; Arbeitsbeginn 23.11.2026) · **Status:** **Entwurf**, nicht zur Abzeichnung reif. Werte mit `<…>` werden beim finalen Lauf eingesetzt; Kriterien mit „läuft“ oder „wartet auf“ sind offen.
**Code-Stand:** Integrationsstand `225d57b` (Beschlüsse E-383…E-392) + Slice `gate-klaerungen` (Umsetzung E-392, Nachweis `docs/r1/SET-CC-gate-klaerungen.md`); erster Entwurf auf `claude/blissful-curie-tuuf7q` @ `8ac511f`
**Grundlage:** Gate-Kriterien B-1…B-10 und C-1…C-17 aus `docs/r0/R0-12-tests-gates-backlog.md` §3.2/§3.3 und die R1-GATE-Regel unter §3.3 („Gate B **und** Gate C, alle 46 R1-Tasks abgehakt, 100 % der Pflichttests grün (E-184), keine offenen Fehler kritisch/hoch, Freigabe protokolliert“); technischer Stand aus `docs/r1/R1-18-nachweis.md` §6 und §9 (Stand 23.11.2026); Auflagen aus `docs/r0/R0-GATE-protokoll.md` (Freigabe); Beschlüsse in `docs/r0/ENTSCHEIDUNGEN.md` bis E-392.
**Aufbau:** wie `docs/r0/R0-GATE-protokoll.md`. **Lesehilfe:** **erfüllt** = automatisierter Nachweis grün **mit** CI-Protokoll (Commit, Lauf-ID, Datum; R0-12 §3 Regel (a)); **technisch erfüllt** = alle Pflichttests vorhanden und lokal grün, CI-Lauf-ID für das Gate fehlt noch; **teilweise** = Teil der Pflichtmenge offen; **offen** = Handlung fehlt; **wartet auf …** = Handlung einer genannten Person außerhalb der Entwicklung.

---

## 1. Ergebnis in Kürze

| Größe | Wert (Stand 25.11.2026) |
|---|---|
| Gate-B-Kriterien erfüllt / technisch erfüllt / teilweise / offen | 0 / 7 (B-1, B-2, B-3, B-5, B-6, B-8, B-10) / 2 (B-4: nur Praxis-Benchmark; B-9: VAL-037…039, Importformat) / 1 (B-7: Zuordnung prüfen) |
| Gate-C-Kriterien erfüllt / technisch erfüllt / teilweise / offen | 0 / 10 (C-1, C-2, C-3, C-4, C-5, C-7, C-8, C-9, C-12, C-17) / 3 (C-6, C-14, C-15) / 4 (C-10, C-11, C-13, C-16) |
| R1-Tasks abgehakt | 44 von 46 (offen: R1-18, R1-29) |
| Reviews durch zweite Person | kein menschliches Zweitreview; für den ersten Merge (PR #3) gilt die einmalige Ausnahme E-339 – **wartet auf AG-05** |
| GM-Rückverfolgung | 210 IDs, 207 mit Test (davon 13 teilweise), Ausnahmen: GM-077 (R2), GM-160 (R3), GM-180 (Abweichung freigegeben, E-334) |
| Eingangskriterien aus dem R0-GATE | AG-05 offen · AG-07 (E-329) offen · Referenzlauf E-328 offen (Prüfrolle: Inhaberin selbst, E-390) |
| Offene Klärungen §7 | keine; alle am 25.11.2026 entschieden (E-392), Code-Anteile umgesetzt |
| **R1-GATE gesamt** | **offen** – technisch nahe am Ziel; offen sind vor allem Handlungen außerhalb der Entwicklung (§6) und die manuellen Gate-Teile |

---

## 2. Skriptergebnisse

**Lokaler Stand 23.11.2026** (aus `R1-18-nachweis.md` §9.1, Integrationsstand `bfc51c4`; PHP 8.5, WP 7.1, MariaDB):

```
Unit 130 (2 übersprungen) · Integration 464 (31 übersprungen, pcntl) · Multisite 477 (31 übersprungen)
phpcs 0 · PHPStan L8 0 · Free-Guard 0 (205 Dateien) · Textdomain-Lint 0 (198 Dateien)
gm-coverage: 210 IDs, 207 mit Test, 3 Ausnahmen (r2 1, r3 1, deviation 1), missing/stale/invalid/unknown 0
```

**Für das Gate einzusetzen (finaler Lauf):**

| Lauf | Commit | CI-Lauf-ID / Datum | Ergebnis |
|---|---|---|---|
| CI `ci.yml` vollständig (J-01…J-12, old-runtime, build-zip; Matrix MariaDB 12.3, MySQL 8.4, MySQL 9.7, Multisite; pcntl-Fälle MIG-/CON-) | `<sha>` | `<id>`, `<datum>` | `<grün/rot>` |
| `php tools/qa/gm-coverage.php` | `<sha>` | – | `<Ausgabe>` |
| `tools/qa/e2e/live-smoke.sh` | `<sha>` | – | `<OK>` |
| `tools/release/build-zip.php` + `check-zip.php` + `artifact-test.sh` | `<sha>` | – | `<OK>` |
| Referenzlauf E-328 | – | Protokoll `E-328-referenzlauf-protokoll.md` | `<läuft / wartet>` |

---

## 3. Gate B – Free-Lernfluss (R0-12 §3.2)

| Nr. | Kriterium (Pflichtmenge) | Nachweis | Stand (fortgeschrieben 25.11.2026) | Status |
|---|---|---|---|---|
| B-1 | Golden Master Lernkern GM-001…GM-093, GM-137…GM-144 – 100 % grün oder freigegebene Abweichung | `GoldenMasterTest` u. a.; `gm-coverage`; R1-18 §9.2 | alle GM-IDs mit Test; GM-077 R2 (E-05). Rechenlogik-Fälle GM-029…031, 065…068, 084 zusätzlich vom Referenzlauf abhängig; den Referenzlauf führt die Inhaberin selbst durch (E-390) | **technisch erfüllt**; wartet auf Referenzlauf (E-328, Inhaberin) und CI-Lauf-ID |
| B-2 | Revisionen laufender Kurse REV-001…REV-046 | R1-27-Nachweis, R1-08/R1-26/R1-44 | Suiten grün; Vollständigkeit des Katalogs nicht Teil des R1-18-Laufs (R1-18 §6) | **technisch erfüllt** (Katalogabgleich `<offen>`) |
| B-3 | Publish-Validator PUB-001…PUB-052, Blocker nicht übersteuerbar (E-128) | R1-26-Nachweis | Suiten grün; Alt-Text-Regel vorhanden: `CourseValidator` VAL-140 (Bild ohne Alternativtext in Kurs, Modul, Lektion) und VAL-141 (lösungsrelevantes Fragenbild ohne Beschreibung), beide Blocker; der Fragen-Editor veröffentlicht ohne Alternativtext nicht (E-349, R1-29 B-05 behoben) | **technisch erfüllt** |
| B-4 | Referenz-E2E Flow 1/2/3 in Chromium, Firefox, WebKit, Desktop und Mobil; Praxis-Benchmark (GM-187); Referenzkurs mit Aufgabe | `live-smoke.sh`, `live-flows.sh`, `settings-a11y.sh`; `docs/r1/SET-CC-ui-f7.md` | Browser-Matrix grün (UI-F7, 24.11.2026): Flow 1 (Autor), Flow 2 (Desktop und Telefon), Prüfungsraum, Flow 3 (Gruppe) und A11Y-Seiten (axe-core 4.14.1, 0 Verstöße) in Chromium, Firefox und WebKit, je Desktop und Telefon 390 px; Referenzkurs mit Aufgabe; automatisierte Benchmark-Grundlinie (`bench-report.js`). **Offen nur der Praxis-Benchmark mit Person (GM-187, UX-090)** | **teilweise** – wartet auf Benchmark-Person (W-18) |
| B-5 | Kurs ohne Import (GM-137, Release-Gate 23.2 Nr. 2) | R1-18 g4 (Struktur-Fingerabdruck) | mit Test | **technisch erfüllt** |
| B-6 | Standalone SA-001, CT-09, NFR-017, PRIV-017; CI ohne Woo/Suite/Pro | CI, Free-Guard 0 | erfüllt laut R1-18 §6 | **technisch erfüllt** |
| B-7 | Prozessfälle mit Gate „B“ bzw. „B/C“ (R0-12 §1) | R0-12 §1.1/§1.2 | Zuordnung Fall → Test für den finalen Stand nicht neu ausgewertet | **offen** (Auswertung `<läuft>`) |
| B-8 | Zertifikatsbasis CERT-001…006, SEC-034, PRIV-025, LOC-020…022 | R1-44, R1-35 | Suiten grün; Vorlagentabelle Δ-7, `revision_kind` Δ-10 offen (R1-35) | **technisch erfüllt** (Δ-7/Δ-10 `<Pflicht?>`) |
| B-9 | Text-/Dateiaufgaben R1-46: Workflow, Upload-Sicherheit, Bewertung, Privacy | R1-46-Nachweis | Dienste/REST grün; Oberflächen vorhanden (Abgabe im Lernbereich `AssignmentView`, Bewertungsbereich `GradingArea`; Flow 3 reicht ein und bewertet). Weiter offen: Publish-Regeln VAL-037…039 (kein Code im `CourseValidator`) und das Importformat für Aufgaben; Aufgaben-Einstellungen nur als JSON (AU-T12) | **teilweise** |
| B-10 | Neue Fragetypen (E-22, E-69): Validierung, Bewertung, Barrierefreiheit | R1-06, R1-19 | Bewertung grün; Prüfungsraum ohne JS beantwortet alle Prüfungstypen (`ExamRoom::SUPPORTED`: Einfach-/Mehrfachauswahl, Wahr/Falsch, Zahl, Zuordnung, Reihenfolge, Lückentext, Freitext; Karteikarten nie in Prüfungen, E-351); A11Y-Seiten in allen drei Browsern ohne axe-Verstoß (UI-F7) | **technisch erfüllt** |

## 4. Gate C – Betriebsreife (R0-12 §3.3)

| Nr. | Kriterium (Pflichtmenge) | Nachweis | Stand (fortgeschrieben 25.11.2026) | Status |
|---|---|---|---|---|
| C-1 | Rechte, IDOR, Sicherheit: RBAC-001…097, SEC ohne R3-Fälle, CT-02, CT-10, SUP-001…008 | R1-03, R1-28, Policy-/RBAC-Suiten | grün; F-7 (GM-154 404 statt 403) freigegeben (E-333) | **technisch erfüllt** |
| C-2 | Grants, Seats, Identität, Gate (GM-094…117, GRANT, SEAT, ID) | R1-13, R1-14, R1-18 g3 | 24 von 24 GM mit Test | **technisch erfüllt** |
| C-3 | Import und Medien IMP-001…032, MED-001…026, GM-118…136 | R1-15, R1-41, R1-18 g4 | alle mit Test; Medien- und Blueprint-Import gebaut (E-389, E-382; `R1-18-gm-nachtrag-h1.md`, `GoldenMasterMediaTest`), GM-118/125/128 vollständig; GM-136 im Vollvolumen gelaufen und skaliert in der Suite (E-338) | **technisch erfüllt** |
| C-4 | Nebenläufigkeit und Cache CON-001…048, NFR-010…012 auf MariaDB **und** MySQL 8.4 | R1-42, R1-25; CI-Matrix | lokal grün; pcntl-Fälle nur in der CI | **technisch erfüllt**; CI-Lauf-ID mit MySQL 8.4 `<einsetzen>` |
| C-5 | Recovery REC-001…026, PRIV-010/011 | R1-23, R1-40 | grün; Lösch-Tombstones (PRIV-010 Rest, E-92) offen – siehe AG-07 J-10 | **technisch erfüllt** bis auf Tombstones (`<Pflichtfall?>`) |
| C-6 | Nichtfunktionale Ziele NFR-001…019, GM-187/188 auf R-M bzw. R-S; Zeitbudgets sind Gate (E-60) | R1-22 | Tests vorhanden; Query-Budgets: Überschreitungen als Abweichung freigegeben, die aktuellen Werte sind die Regressionsgrenze (E-388; heute über Budget nur noch OP-04 16 statt 8 und OP-06 8 kalt statt 6, Liste in `R1-22-nachweis.md` A-5); k6-Profile L1–L4 und Messprotokoll NFR-018 auf R-M **nicht gelaufen** | **teilweise** – wartet auf Cloud-VM (AG-13) |
| C-7 | API-Verträge CT-01…12, GM-145…155, SEC-002, SEC-039 | R1-28, R1-18 g2/h2 | 11 von 11 GM mit Test (GM-150/152/154 teilweise; E-338 Statuscodes) | **technisch erfüllt** |
| C-8 | Betrieb, Migration, Lifecycle, Support (GM-156…186 ohne 159/160, GM-189/190, SUP-009…042, MS-001…023) | R1-17, R1-23, R1-38, R1-40, R1-18 g5 | alle mit Test außer GM-180 (Abweichung freigegeben, E-334) | **technisch erfüllt** |
| C-9 | Datenschutz PRIV-001…030 | R1-17, R1-34, R1-18 g5 | Suiten grün; inhaltliche Abweichungen bei Fristen siehe AG-07 (J-02, J-05…J-07, J-10, J-15) | **technisch erfüllt**; **wartet auf AG-07** |
| C-10 | Accessibility, i18n, Locale: automatisierte A11Y/I18N/LOC grün; Screenreader-Sitzungen NVDA und VoiceOver (E-126); 0 S1 | R1-19 (axe 0, Tastatur), R1-29 | automatisiert grün; **Screenreader-Sitzungen nicht durchgeführt**; axe nicht als CI-Gate | **offen** – wartet auf R1-29 (Expertendurchläufe, Nutzertests) |
| C-11 | Usability: UX-Katalog R0-26, Schwellen E-166, Teilnehmerzahl E-163, Nachtest | `R1-29-usability-testkit.md`, `docs/r1/R1-29/` | vorbereitet, **nicht durchgeführt**; Oberflächen aller ★-Aufgaben vorhanden (E-391, W-12); Restlücken nur bei Nicht-★-Aufgaben (W-12) | **offen** – wartet auf Inhaberin (U-1…U-10) |
| C-12 | Benachrichtigungen NTF-001…007, PRIV-021, LOC-016…019, SEC-040 | R1-21 | grün | **technisch erfüllt** |
| C-13 | Sicherheitsprüfung: SAST/Dependency-Scan nach R0-18 ohne offenen Befund „hoch“; Maßnahmen R0-17 | CI J-07 `composer audit --locked` | Dependency-Scan vorhanden; **kein SAST-Lauf** in der CI | **offen** – wartet auf SAST-Lauf und Bewertung (SEC-Reviewer, AG-05) |
| C-14 | Kompatibilität: Matrix R0-20, Konflikttests R1-30, Security-Plugin-Matrix | R1-30 | CI-/Stub-Tests grün; **Profile KP-01…14** (Docker, echte Plugins, Redis/Varnish/Cloudflare, Browser) und Koexistenz J-21 gegen das Original (Deploy-Key) **offen** | **teilweise** – wartet auf Umgebung und AG-13 |
| C-15 | Distribution R1-39: Clean-Install, Upgrade REC-023, Readme, SEC-045, Deaktivierung | R1-39 | ZIP-Build/-Prüfung/Artefakttest grün; **WordPress.org Plugin Check** nicht gelaufen; `SECURITY.md` ohne Sicherheitsadresse (AG-09); SBOM/Attestierung mit Release-Workflow | **teilweise** – wartet auf AG-09 und Plugin-Check-Lauf |
| C-16 | Release-Gates Konzept 23.2 Nr. 1–3 und 6–9 | – | nicht zusammengeführt ausgewertet | **offen** (`<Auswertung>`) |
| C-17 | Website-Härtung R1-45 | R1-45 | grün (PSD-039…056/065) | **technisch erfüllt** |

---

## 5. Weitere R1-GATE-Bedingungen

| Bedingung | Quelle | Stand | Status |
|---|---|---|---|
| Alle 46 R1-Tasks abgehakt | R0-12 §3.3 | 44 / 46; R1-18 wartet auf Referenzlauf (Inhaberin, E-390) und Review; R1-29 wartet auf Durchführung | **offen** |
| 100 % der Pflichttests grün, Ausnahmen nur per E-Nummer oder freigegebener Abweichung | E-184 | lokal grün; CI-Lauf `<id>` | **technisch erfüllt** bis auf CI-Lauf |
| Keine offenen Fehler „kritisch/hoch“ | R0-12 §3.3 | `<Befundliste einschätzen>`; erledigt bzw. entschieden: R1-18 F-5 (Medien-/Blueprint-Import, E-389), R1-22 B-3 (E-388), R1-29 B-02/B-05 (behoben); offen: AG-07 | **offen** |
| AG-05: zweite Review-Person, Vertretung Deploy-Freigabe | R0-GATE-Auflage, E-193, E-204, E-339 | nicht benannt; E-339 nur für PR #3 | **wartet auf Inhaberin** – Vorlage `AG-05-reviewer-benennung.md` |
| AG-07: juristische Bestätigung Fristen und externe Medien | E-329 | nicht erfolgt | **wartet auf Inhaberin/Rechtsberatung** – Vorlage `AG-07-rechtspruefung.md` |
| Referenzlauf E-328 (Eingang R1-18) | E-328, E-56 | nicht erfolgt; Prüfrolle: die Inhaberin führt den Lauf selbst durch (Variante B, E-390) | **wartet auf Deploy-Key (AG-13) und den Lauf der Inhaberin** – Vorlage `E-328-referenzlauf-protokoll.md` |
| Gate-Protokollierung mit Datum, technischem Reviewer und Auftraggeberin | R0-12 §3 (d), D12-3 | – | **offen** (setzt AG-05 voraus) |

---

## 6. Wartet auf … (konsolidiert, mit Zuständigkeit)

| Nr. | Punkt | Wartet auf | Zuständig | Wirkung auf | Vorlage |
|---|---|---|---|---|---|
| W-01 | Reviewer je Rolle + Vertretung, Vertretung Deploy-Freigabe; GitHub-Einrichtung | Benennung | **Inhaberin** | Merges, alle Tasks, Gate-Abzeichnung | `AG-05-reviewer-benennung.md` |
| W-02 | Nachholreview der 44 abgehakten Tasks + Review R1-18/R1-29 | Durchführung | **benannte Reviewer** (nach W-01) | alle Tasks | `AG-05-reviewer-benennung.md` §5 |
| W-03 | Juristische Bestätigung Fristen E-44/E-51/E-92/E-143/E-145/E-150/E-289 und externe Medien E-45a/AR-01 | Prüfung, Unterschrift | **Inhaberin → Rechtsberatung/Datenschutz** | C-9, R1-GATE (E-329) | `AG-07-rechtspruefung.md` |
| W-04 | Behebung der Fristabweichungen (Löschläufe RT-05/06/12/13, Bezug RT-04, Tombstones, änderbare Fristen, Textvorschlag, Knopf „Nach Klick laden“), soweit W-03 sie vor R1-GATE verlangt | Ergebnis W-03, dann Umsetzung | **Entwicklung** | C-5, C-9 | `AG-07-rechtspruefung.md` §6 |
| W-05 | Lesender Deploy-Key `hastla007/learnboating` | Bereitstellung | **Inhaberin** (AG-13) | Referenzlauf, J-21 (C-14) | `E-328-referenzlauf-protokoll.md` §4 |
| W-06 | Prüfrolle + Vertretung, Variante A/B | – | **entschieden (E-390, 25.11.2026):** Variante B, die Inhaberin führt den Referenzlauf selbst durch | R1-18 (E-328), B-1 | `E-328-referenzlauf-protokoll.md` §2/§3 |
| W-07 | Referenzlauf durchführen und protokollieren | Lauf (nach W-05) | **Inhaberin** als Prüfrolle (E-390) | R1-18, B-1 | `E-328-referenzlauf-protokoll.md` |
| W-08 | Entscheidungen zu Abweichungen aus dem Referenzlauf (E-55) | Freigabe | **Inhaberin** | B-1 | `E-328-referenzlauf-protokoll.md` §8 |
| W-09 | Medien- und Blueprint-Import (GM-118/125/128) umsetzen **oder** als Teilumfang freigeben | – | **entschieden (E-389):** wird gebaut, keine Teilumfangsfreigabe; **erledigt** (Import gebaut, `R1-18-gm-nachtrag-h1.md`) | C-3, R1-18 | R1-18-Nachweis §9.2 |
| W-10 | Usability-Abnahme: Umfang, Budget (AG-06), Einwilligung/Speicherort, Moderation, Staging, SUS-Fassung, Referenzkurse (AG-12), Pilot, Rekrutierung, Abnahmebericht | Entscheidungen, Beauftragung | **Inhaberin** | C-10, C-11, R1-29 | `R1-29-usability-testkit.md` §1 |
| W-11 | Sitzungen Runde 1/2 und Expertendurchläufe (NVDA, VoiceOver) | Durchführung | **Moderation, A11Y-Fachperson** (nach W-10) | C-10, C-11 | `R1-29-usability-testkit.md` |
| W-12 | Fehlende Oberflächen für ★-Aufgaben und Flow 1/3 | – | **entschieden (E-391):** bauen. **★-Oberflächen vorhanden** (im Code geprüft 25.11.2026, siehe W-12-Prüfung unten); Restlücken nur bei Nicht-★-Aufgaben | B-4, B-9, B-10, C-11 | `R1-29/bereitschaft.md` §1 |
| W-13 | Cloud-VM für Messungen (k6 L1–L4, NFR-018 auf R-M; M-01…M-16 Großimport) | Bereitstellung | **Inhaberin** (AG-13) | C-6 | R1-22-, R1-41-Nachweis |
| W-14 | Entscheidung zu den überschrittenen Query-Budgets OP-01/03/04/05/06 (beheben oder als Abweichung freigeben) | – | **entschieden (E-388):** als Abweichung freigegeben, aktuelle Werte sind die Regressionsgrenze | C-6 | R1-22-Nachweis B-3, A-5 |
| W-15 | Sicherheitsadresse für `SECURITY.md`; WordPress.org-Konto mit 2FA | Bereitstellung | **Inhaberin** (AG-09) | C-15 | R1-39-Nachweis A-3 |
| W-16 | WordPress.org Plugin Check als CI-Lauf; SAST-Lauf mit Bewertung | Umsetzung, Bewertung | **Entwicklung**; Bewertung **SEC-Reviewer** (nach W-01) | C-13, C-15 | R0-18 |
| W-17 | Kompatibilitätsprofile KP-01…14 (Docker, echte Plugins, Redis/Varnish/Cloudflare, Browser) | Umgebung, Lauf | **Entwicklung/Betrieb**; kommerzielle Plugins nur best effort (E-196) | C-14 | R1-30-Nachweis |
| W-18 | Praxis-Benchmark UX-090 mit Person (Browser-Matrix Firefox/WebKit/Mobil und Flow 1/3 **erledigt**, `SET-CC-ui-f7.md`) | Benchmark durch projektfremde Fachperson | Benchmark-Person **Inhaberin** benennen | B-4 | R0-26 UX-090 |
| W-19 | PHP-Untergrenze-Regel bestätigen | Bestätigung | **Inhaberin** (AG-10) | C-14 | R0-GATE §4 |
| W-20 | Offene Klärungen aus R1-18 §9.3 (§7 unten) | – | **entschieden (E-392):** alle Vorschläge übernommen; Code-Anteile umgesetzt (Slice `gate-klaerungen`), `backup-check`-Rest als Folgeaufgabe | R1-18, Gate | §7 |
| W-21 | Gate-Protokoll abzeichnen | Unterschrift | **technischer Reviewer** (nach W-01) und **Inhaberin** | R1-GATE | §9 |

**W-12-Prüfung (25.11.2026, Code gegen `R1-29/bereitschaft.md` §1):** Die ★-Aufgaben und ihre Oberflächen:

| ★-Aufgabe | Oberfläche (Datei) | im Code |
|---|---|---|
| AU-T07 Prüfung „20 aus 50“ anlegen | Seite „Quizzes & exams“ (`src/Admin/ExamsScreen.php`) | vorhanden |
| AU-T09 Veröffentlichen mit Prüfkasten | Prüfkasten „Publish revision“; Alt-Text-Regel VAL-140/141 (`src/Publish/CourseValidator.php`) | vorhanden |
| AU-T13 Abgabe bewerten | Bewertungsbereich (`src/Admin/GradingArea.php`) | vorhanden |
| LE-T01 Selbstanmeldung | Knopf „Enroll“ im Kursraum (`src/Learner/LearningArea.php` → `src/Access/SelfEnrollment.php`) | vorhanden |
| LE-T04 Smarttrainer (mit LE-T05/T07) | Routen `ueben`, `fehler`, `faellig` (`src/Learner/TrainerView.php`) | vorhanden |
| LE-T06 Prüfung ablegen | Prüfungsraum ohne JS, alle Prüfungstypen (`src/Learner/ExamRoom.php`, `SUPPORTED`) | vorhanden |
| LE-T08 Ergebnis und Nachweis | Ergebnis, Nachweisliste, Verifikation (`src/Certificate/CertificatePages.php`) | vorhanden |
| LE-T10 Weiterlernen, mobil | „Continue: …“ in „Mein Lernen“; 320/390 px ohne horizontales Scrollen (UI-F7) | vorhanden |
| LE-T11 Abgabe einreichen (mit LE-T12) | Abgabe im Lernbereich (`src/Learner/AssignmentView.php`) | vorhanden |
| Flow 3 (GV-T01…T07) | Besitzer-Oberfläche „My groups“ (`src/Learner/GroupsView.php`), Beitrittslink (`src/Learner/Invitation.php`; REST R-31/R-32 in `src/Learner/LearnerRoutes.php`) | vorhanden |

**Restlücken (keine ★-Aufgabe, ehrlich geführt):** AU-T01 kein Feld „Lernziele“ im Kurs-Editor (nur Beschreibungstext); AU-T02 Struktur nur über Auswahl und Positionszahlen, kein Ziehen/Strukturbaum; AU-T03 Video/Datei weiter als JSON-Einstellungen in der Metabox „Activity“ (nur die Abschrift hat eigene Felder, `LessonMediaFields`); AU-T12 Aufgaben-Einstellungen als JSON; UX-012 kein Zwischenspeichern im Fragen-Editor und in der Metabox; LE-T09 Datenauskunft nicht im Lernbereich verlinkt (Kurzcode-Seite nötig); UX-075 Anzeige „inkl. Zeitausgleich“ vor dem Prüfungsstart fehlt (A11Y-070). AU-T03/AU-T12 sind mit Hilfstechnik (UX-074) nicht sinnvoll prüfbar. Ob diese Lücken vor R1-29 geschlossen werden, entscheidet E-391 nicht (es nennt nur die ★-Aufgaben).

---

## 7. Klärungen (aus `R1-18-nachweis.md` §9.3; alle entschieden am 25.11.2026, E-392)

| Herkunft | Punkt | Vorschlag | Entscheidung |
|---|---|---|---|
| g2 A-5 | Abmelden leert den Prüfungspuffer im Browser (localStorage) nicht aktiv (PRIV-018); der Puffer enthält keine Lösungen | als bekannte Grenze dokumentieren oder beim Abmelden leeren | ☒ beheben (E-392): umgesetzt mit `ExamBufferPurge` (R1-18 h3, `e5b37a7`): `wp_logout` setzt das Marker-Cookie, ein Inline-Skript auf der Login-Seite und im Frontend leert alle `ccap_exam_*` in localStorage/sessionStorage; Test `BackupCheckH3Test::test_PRIV_018_logout_empties_exam_buffer`, Browser-E2E `tools/qa/e2e/exam-room.js` (Schritt PRIV-018) |
| g3 H-3/H-4 | `backup-check` deckt nur Teile von R0-16 §1.4 ab | Rest als Folgeaufgabe | ☒ ok, Folgeaufgabe (E-392) |
| g3 H-5 | Einladungsseite minimal, REST R-31/R-32 fehlen | mit der Besitzer-Oberfläche (W-12) | ☒ ok (E-392); erledigt: Besitzer-Oberfläche `GroupsView`, Beitritt `Invitation`, REST R-31/R-32 in `LearnerRoutes` |
| g4 | Option `course_captain_installation_uuid` wird nie gesetzt; jedes Paket außer `local` gilt als fremd (`own` → `licensed`) | setzen bei Aktivierung | ☒ beheben (E-392): `InstallationUuid::ensure()` bei Aktivierung und für bestehende Installationen beim nächsten Admin-Aufruf; ein vorhandener Wert wird nie ersetzt; Test `LifecycleTest::test_E_392_installation_uuid_on_activation_and_upgrade` |
| g5 A-1 | GM-190: Gäste erhalten 401 (R-38) | so lassen | ☒ ok (E-392) |
| g5 A-2 | Routennamen des Datenexports selbst gewählt (R0-19 nennt nur Codes) | so lassen, API-Changelog | ☒ ok (E-392) |
| g5 A-3 | Löschung nach Bestätigung nur mit Opt-in E-43 | so lassen (konform) | ☒ ok (E-392) |
| Umgebung | Zertifikats-/Import-Hashes hängen von `intl`/`Normalizer` ab | `intl` als Systemvoraussetzung dokumentieren oder Fallback angleichen | ☒ dokumentieren (E-392): `readme.txt`, `docs/user/installation.md` §1; Prüfung `php_intl` im Systemstatus und im Website-Zustand (Warnung, keine Aktivierungssperre); Test `CompatibilityTest::test_E_392_intl_requirement_check` |
| R1-18 §7 | Antwort-IDs `<frage>-a…e` (R0-05 §2.3) gegen Schlüssel `^([A-Z]|MODEL)$` (R0-08 §3.1); umgesetzt positionsweise beim Import | so lassen | ☒ ok (E-392) |
| R1-03 A-1 | Kursmanager vergibt Support trotz #21 | klären | ☒ nur Administratoren vergeben die Support-Rolle (E-363, Inhaberin 24.11.2026) |

**Entschieden am 25.11.2026 (Inhaberin, E-383…E-392):** SET-CC-03/05/07/08/17 (E-383…E-387), Query-Budgets (E-388, W-14), Medien-/Blueprint-Import (E-389, W-09), Prüfrolle Referenzlauf (E-390, W-06), Oberflächen vor R1-29 (E-391, W-12), Klärungen §7 (E-392, W-20).

**Entschieden am 24.11.2026 (Inhaberin, E-350…E-368):** GM-178 Ablehnung (E-362), GM-180 erledigt (E-364), RT-23 Kette über Pseudonym (E-361), Paketkopien GM-131 vs. R0-10 §3.6 (E-350), Support-Rolle nur durch Administratoren (E-363), Login-Limit-Bestätigung RC-14 (E-358), Slug-Kollision harter Fehler (E-359), Wiedereinsetzung beendeter Gruppen (E-360), Karteikarten nie in Prüfungen B-03 (E-351), Debug-Fenster 7 Tage (E-352), Rollen-Export Opt-in/RC-07 (E-365), Setup-Assistent Schritt 7 ohne leere Prüfung (E-366), Multisite-Löschung nur auf der Site (E-367); R1 noch nicht freigegeben, keine R2-Arbeit (E-368). Umsetzung offen: E-350, E-351 (Ausschluss in Prüfungen und Validatorhinweis; das Modulpool-Quiz schließt Karteikarten bereits aus), E-357, E-360, E-361.

## 8. Abweichungen (Stand)

| Nr. | Inhalt | Beschluss | Status |
|---|---|---|---|
| A-01…A-06, B-01…B-19 | Abweichungen vom Original aus R0 | E-55a | freigegeben 16.11.2026 |
| F-1 / F-2 | Prüfungsteile über Modulgruppen; Teilumfang je Art | E-331, E-332 | nachgebaut |
| F-6 / F-7 | Join-Token nur als Hash; 409 `ccap_exam_start_busy`; fremder Kurs 404 | E-333 | freigegeben |
| GM-180 | kein Einstellungsschlüssel `default_access`; Standard-Zugangsdauer `enrollment.default_access_days` im Tab Einschreibung | E-334, E-364 | freigegeben, erledigt (24.11.2026) |
| GM-068 | Nachrüstung `due_at` nur im Migrationswerkzeug | E-335 | entschieden |
| GM-038/GM-150/GM-136 | Statuscodes nach R0-19; GM-136 skaliert | E-338 | entschieden |
| GM-118/125/128 | Medien-/Blueprint-Import fehlte | E-389 | gebaut (`R1-18-gm-nachtrag-h1.md`), keine Abweichung nötig |
| R1-22 B-3 | absolute Query-Budgets überschritten (heute OP-04, OP-06) | E-388 | freigegeben 25.11.2026; aktuelle Werte sind die Regressionsgrenze (`R1-22-nachweis.md` A-5) |
| neu aus Referenzlauf | `<…>` | `<E-…>` | `<…>` |

## 9. Freigabe

| Feld | Eintrag |
|---|---|
| Gate B (grün / rot / grün mit Auflagen) | |
| Gate C (grün / rot / grün mit Auflagen) | |
| **R1-GATE** (grün / rot / grün mit Auflagen) | |
| Finaler Commit und CI-Lauf-ID | |
| Freigegebene Abweichungen (Nummern) | |
| Akzeptierte S2 aus R1-29 (Nummern, Begründung; nie mit WCAG-Bezug in Lernenden-Flows, E-171) | |
| Auflagen / Fristen | |
| Technischer Reviewer (Name, Datum) | |
| Auftraggeberin (Name, Datum, Unterschrift) | |

Erst nach dieser Unterschrift wird R1-GATE in `TODO-WETTBEWERBSANALYSE.md` abgehakt und die Löschfrist der Usability-Aufzeichnungen (6 Monate nach R1-GATE, E-167) beginnt.
