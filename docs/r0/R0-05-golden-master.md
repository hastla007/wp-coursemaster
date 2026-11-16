# R0-05 · Golden-Master-Fixtures und Regressionskatalog

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Es gibt keine Implementierung und keinen Testlauf. · **Stand:** 16.11.2026
**Bezug:** TODO R0-05 (Voraussetzung R0-03), R0-PAR-003, LB-AUDIT-006, R1-18; Konzept Kap. 23.1/23.2, 26.3 Nr. 5, 27.4, 38 (PAR-01…PAR-12), 39 (LB-01…LB-15); Inventur `docs/r0/R0-02-inventar/A…E` (Spalte „Golden-Master-Kandidat“)
**Quelle:** `hastla007/learnboating` @ `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd`. Ich habe nur gelesen und nichts verändert. Testpfade sind relativ zu `bootspruefung-learning/tests/`, `PT` und `UC` relativ zur Repository-Wurzel. LB-Tests sind **Verhaltensreferenz**, keine Code-Vorlage (E-01).

### Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). In dieses Dokument eingearbeitet:

| Beschluss | Wirkung hier |
|---|---|
| **E-01** Clean Room | Alle GM-Tests, Fixtures und Hilfsskripte entstehen neu. Die Quelltests (CF, LR, MS …) belegen nur das erwartete Verhalten; kein LB-Testcode, keine LB-Fixture und kein LB-Skript wird kopiert. „Übernehmen“ in §3 heißt „Muster nachbauen“ (§0, §3) |
| **E-02** | nur synthetische, neutrale Testpakete (§2.1) |
| **E-55** (EGM-01) | Abweichungen vom LB-Verhalten gibt der Auftraggeber schriftlich über die **Abweichungsliste §5.1** frei; GM-Erwartungswerte ändern sich nur mit Eintrag dort |
| **E-56** (EGM-02), E-194 | Referenzlauf des LB-Plugins **nur durch Ausführen** (kein Lesen oder Kopieren von Code) mit einem synthetischen Paket im LB-Format, nur für Fälle mit Rechenlogik (GM-029…GM-031, GM-065…GM-068, GM-084, GM-131); in der CI über den lesenden Deploy-Key gegen Commit `9f37c77` (§2.1, §5) |
| **E-11** | Runner PHPUnit mit WP-Testsuite und Playwright (D-01, D-09) |
| **E-20**, E-95, E-147, E-268, E-269 | Lösungserklärungen nur mit Pro (`course.explanations`). Grant G-1 ohne Bereich `explanations` (§2.8); GM-018 prüft Free (richtig/falsch, richtige Antwort nach Einstellung, kein Erklärungstext); neu **GM-197** (Free/Pro/Lizenzende/Add-on aus) |
| **E-22** | Fragetyp `numeric_tolerance` in R1 Free: **GM-191** (neu, ohne LB-Quelle), Fixture PKG-X1 (§2.9) |
| **E-69** | `matching`, `ordering`, `cloze` in R1 Free: **GM-192…GM-194** (neu, ohne LB-Quelle) |
| **E-168** | Text-/Dateiaufgaben mit Bewertung in R1 (R1-46), Zustandsautomat `draft/submitted/returned/graded` nach R0-08 §2.5: **GM-195/GM-196** (neu, ohne LB-Quelle). Die frühere R2-Fixture-Erweiterung `v1.1` entfällt; die Aufgaben stehen in PKG-X1 (§2.2, §2.9) |
| **E-25a** | Bundle (ein Grant schaltet mehrere Kurse frei) in R1 Free: **GM-198** (Kern ohne Woo; LB kennt Bundles nur in der Woo-Statuslogik, §1.21) |
| **E-26** | abhängige Zusatz-Grants in R1: **GM-199** (neu) |
| **E-27** | Zugang je Modul (`module_id`, ganzer Kurs = 0 nach E-286): **GM-200** (neu) |
| **E-23**, E-129, E-75 | Prüfungsregeln `status` + `purpose` statt `verified`; `evidence_eligible` statt `live` (GM-029, GM-032, GM-033, §2.4); Entwürfe nie als Übung startbar (GM-033); Ablauf = `evaluated` + `end_reason = expired` (GM-035) |
| **E-45/E-45a** | `privacy.external_media_mode`: Default `always_load`, umschaltbar auf `click_to_load`; GM-013 prüft beide Modi; die Fixture setzt `click_to_load`, damit E2E keine Fremdverbindung aufbauen (§2.8, D-09) |
| **E-10**, E-239, E-260, E-261, E-307…E-309 | Website-Härtung im Kern (R1-45): SE:279–631 ist nicht mehr ausgeschlossen; GM-Fälle **GM-201…GM-208** in §1.23 (Belege über Inventur C INV-C-138, -140…-146). Nicht in R1 nach E-261/E-308: User-Enumeration-Schutz, Autorenarchive, Benutzer-Sitemap, Anwendungspasswörter, Feed-Autor (§1.21, R4-Pool). Abweichungen A-03…A-05 (§5.1) |
| **E-286** | `module_id NOT NULL DEFAULT 0` (0 = ganzer Kurs); Scopes `view/download/exam` bleiben innerhalb eines Moduls – die Bereichsangaben der Fixture-Grants (§2.8) gelten unverändert; GM-200 prüft zusätzlich den Fortschrittsnenner |
| **E-288**, **E-290** | Aufgaben bewerten Trainer, Autor und Kursmanager des Kurses (GM-195); Benachrichtigungen N-14…N-16 werden in R0-27 NTF-035…NTF-037 geprüft, nicht im GM |
| **E-16** | FAQ/Tipps-Mechanik in R1 Free: §1.20 gilt unbedingt |
| **E-21** | Karteikarten: ein Typ mit zwei Modi (GM-062/GM-063, §2.5) |
| **E-232** | Fortschrittseinsicht für Gruppenbesitzer nur in Pro (Opt-in): nicht im R1-Golden-Master (§1.21) |
| **E-05, E-07/E-49, E-09, E-18, E-43, E-44** | ersetzen die Inventur-Verweise E-C-04, E-C-01, E-C-03, E-C-07, E-C-05, E-C-06 |
| **E-184** | R1-GATE verlangt 100 % grüne R1-Pflichttests; Ausnahmen nur per E-Nummer oder freigegebener GM-Abweichung (§5.1) |
| **E-207** | Präfix `ccap_` für CPTs, Caps, Rollen, Cron, Meta und Tabellen; Optionen `course_captain_*`. Alle Plugin-Bezeichner in diesem Dokument sind umgestellt. Fixture-Logins `cc_fx_…` und Datenschlüssel `cc-fx-…` bleiben (Testdaten, keine Plugin-Bezeichner) |

## 0. Zweck, Methode und Lesehilfe

Ein **Golden Master (GM)** ist hier eine fachneutral formulierte Prüfaussage. Sie stützt sich auf einen vorhandenen LearnBoating-Test (Verhaltensreferenz), beschreibt eine neutrale Fixture aus `cc-fixture-neutral-v1` und nennt ein konkretes erwartetes Ergebnis. Course Captain muss dieses Ergebnis in R1 reproduzieren (R1-18). Die Quelltests laufen gegen das fachgebundene Boots-Paket (`PACKAGE_DIR=build/boatingschool-*`, `.github/workflows/ci.yml:178`) und gegen Kurscodes wie `SBF-SEE`. Übernommen werden deshalb **nur Prüflogik und Zustandsübergänge**, und zwar als Verhaltensbeschreibung: Der Testcode entsteht neu (E-01). Zahlenwerte gelten neu und stammen aus der neutralen Fixture (Konzept 26.3 Nr. 5; Inventur A Z. 64, C Z. 18, D Z. 111).

Arbeitsweise: Für jede Zeile habe ich die Testdatei geöffnet und die Zeile des jeweiligen `$check(…)` bzw. `step(…)` belegt. Die Labels habe ich pro Datei mit Zeilennummer extrahiert und die Kernabschnitte vollständig gelesen: `learning-review-test.php` komplett; aus `content-flow-test.php` Z. 1–135 und 166–500; aus `membership-test.php` Z. 100–240 und 400–480; aus `voucher-grant-test.php` Z. 148–168. Wo nur eine Fundstelle im Quellcode existiert und kein Test, steht in der Spalte „Lücke“ **ja**.

**Kürzel der Quelltests**

| Kürzel | Datei | | Kürzel | Datei |
|---|---|---|---|---|
| CF | `integration/content-flow-test.php` | | OP | `integration/operations-test.php` |
| LR | `integration/learning-review-test.php` | | SE | `integration/security-test.php` |
| MS | `integration/membership-test.php` | | AD | `integration/admin-test.php` |
| CO | `integration/commerce-test.php` (läuft ohne Woo über `support/FakeOrders.php`, ci.yml:216) | | AC | `integration/account-test.php` (CI mit Woo, ci.yml:271-272) |
| VG | `integration/voucher-grant-test.php` (CI mit Woo, ci.yml:264-265) | | SM | `integration/smoke-test.php` |
| MA | `integration/mobile-api-test.php` (App-API; laut E-05 nicht R1, nur Verhaltensreferenz) | | UP | `integration/uninstall-phase.php` |
| NC | `integration/navigation-check-test.php` (nur Abschnitt „Prüfungsfragen markieren“ neutral) | | MU | `integration/multisite-test.php` |
| RO | `integration/release-ops-test.php` | | FQ | `integration/faq-test.php` |
| EX | `integration/extensions-test.php` | | ST / GD | `integration/support/standalone.php` / `support/guard.php` |
| E2E-L | `e2e/learning-flow.mjs` | | E2E-A | `e2e/admin-import.mjs` |
| E2E-M | `e2e/membership-flow.mjs` (+ `e2e/membership-setup.php`) | | E2E-K | `e2e/account-flow.mjs` |
| PT | `tools/content-build/tests/PackageTest.php` | | UC | `tools/release/upgrade-check.php` |

Eine Angabe wie `CF:207` bezeichnet die Zeile der Prüfung. `CF:203§` bezeichnet die Abschnittsüberschrift (`echo "…"`). Fixture-Kürzel wie `C-A`, `U-L1`, `ER-A` sind in Abschnitt 2 definiert. Fehlercodes haben das neutrale Präfix `ccap_`. Die Quellcodes (`exam_expired`, `bp_forbidden` …) sind nur Referenz.

**Lücke:** „nein“ heißt, ein Quelltest belegt die Aussage. „teilweise“ heißt, ein Quelltest deckt nur einen Teil ab (z. B. nur App-API oder nur den Positivfall). „ja“ heißt, es gibt keinen Quelltest und der Test muss neu entstehen.

---

## 1. Golden-Master-Katalog je neutralem Bereich

### 1.1 PAR-01 · Kurskatalog, Kursstruktur, Freigabe

| Bereich | Quelltest(s) | Prüfaussage (neutral) | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Katalog | CF:179 (§ CF:166), E2E-L:37 | Angemeldete Person sieht genau die veröffentlichten Kurse | F-BASE (`default_access=logged_in`), U-L1, C-A/C-B veröffentlicht, C-C Entwurf, C-D archiviert | HTTP 200, Liste = `[cc-a, cc-b]` (2 Einträge); C-C/C-D fehlen | GM-001 | R1-04, R1-16 | teilweise (Quelle kennt nur importierte Kurse; Entwurf/Archiv nicht geprüft) |
| Katalog | CF:412 | Katalog ohne Anmeldung | Gast | 401 `ccap_auth_required` | GM-002 | R1-28 | nein |
| Kursstatus | – | Entwurf/Archiv nicht lern- oder startbar, Direktaufruf ohne Leck | C-C, C-D, U-L1 mit Grant `all` auf C-C | Lernen/Prüfungsstart 404 `ccap_not_found`; Vorschau nur mit Cap `ccap_edit_course` | GM-003 | R1-04, R1-26 | **ja** |
| Themen | MS:156, MS:160, MS:162 | Themen ergeben zusammen den Modulumfang; Lernen nach Thema; unbekanntes Thema | M-A1 mit T-A1-a (12) und T-A1-b (12) | Σ Themen = 24 = Modulumfang; nur T-A1-a-Fragen; unbekanntes Thema → `null` | GM-004 | R1-04, R1-12 | nein |
| Kursliste | CF:503 | Fortschritt in der Kursliste = Fortschritt in den Kursdaten | U-L1 nach GM-065 | beide Werte identisch | GM-005 | R1-10, R1-25 | nein |
| Cache | OP:290, :295, :299, :303, :316, :318, :324 | Kurse und Module gecacht, Invalidierung nach Import/Override, kein veralteter Zugang | C-A…C-D (7 Module) | ≤ 2 Abfragen für alle Kurse+Module; gleiche Felder/Reihenfolge; nach `invalidate()` und Cache-Flush neu geladen | GM-006 | R1-25, R1-22 | nein |

### 1.2 PAR-02 · Lektionen, Medien, Videos, Downloads

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Lektion+Quiz | CF:289 (§ CF:260) | Lektionsquiz wird ohne Lösung ausgeliefert | L-A-05 (3 Quizfragen) | kein `is_correct`/`correct_ids` | GM-007 | R1-07 | nein |
| Video | CF:291, CF:300 | Platzhalter-Lektion liefert keine externe URL, echte Lektion ihre URL | L-A-06 (Platzhalter), L-A-04 (MED-002) | `video_url = null` bzw. `https://media.fixture.test/cc/alpha-04.mp4` | GM-008 | R1-07 | nein |
| Abschluss-Nenner | CF:303 | Nenner der Lektions-Completion ohne Platzhalter und archivierte Lektionen | 10 Lektionen C-A (L-A-06 Platzhalter, L-A-10 archiviert) | Nenner = 8 (die Quelle nutzt ihn als Garantie-Nenner; neutral = Completion) | GM-009 | R1-10 | nein |
| Lektionsquiz | CF:310, CF:313 | Quiz im Lektionskontext ausgewertet, Lektion abgeschlossen | L-A-05 | Versuch mit `context=lesson`; Lektion `completed` | GM-010 | R1-07, R1-10 | nein |
| Sehfortschritt | MA:691, MA:694 | Position speichern; Update ohne Position lässt sie stehen | L-A-04 | `position_seconds=42` bleibt nach Update ohne Feld 42 | GM-011 | R1-07 | teilweise (nur App-API) |
| Fremdhosts | MS:1305, :1306, :1309, :1316, :1320 (§ MS:1303) | Eigene Domain/Subdomain/relativ ≠ fremd; fremder Host erkannt; Filter-Allowlist; Liste für Consent | Site `fixture.test`; MED-005 `videos.example.test`; `cdn.fixture.test` | fremd = `{videos.example.test}`; nach Allowlist leer; nach Reset leer | GM-012 | R1-07, R1-34 | nein |
| Externe Medien (E-45a) | – (D-E-04 → E-45/E-45a) | Default „immer laden“ bindet direkt ein; im Modus „Klick zum Laden“ lädt das Medium erst nach Klick | L-A-07 mit MED-005; `privacy.external_media_mode` = `always_load` bzw. `click_to_load` | `always_load`: Einbettung sofort, genau 1 Request an `videos.example.test` (für diesen Fall im E2E-Fremdhost-Gate ausdrücklich erlaubt); `click_to_load`: vor dem Klick 0 Requests, nach dem Klick 1 | GM-013 | R1-07, R1-34 | **ja** (E2E-L:15-19/198 protokolliert Fremdhosts nur, schlägt nicht fehl) |
| Untertitel | OP:719, :726, :728 (§ OP:716) | Standard ohne Untertitel; `vtt` nur HTTPS; Einstellung nur `off`/`vtt` | MED-002 + MED-003 | off → keine Spur; vtt → `…/alpha-04.vtt`; ungültiger Wert → `off` | GM-014 | R1-07 | nein |
| Downloads | MS:349, :352, :353, :354, :357 (§ MS:339) | Ohne Zugang gelistet, aber gesperrt; im Teilumfang ist die erste Datei frei; Vollzugang alle; Kursdaten enthalten die Liste | DL-A-1…3, AR-07 | U-L3: 3 gelistet/1 frei, Auslieferung nur DL-A-1; U-L1: 3 frei | GM-015 | R1-14 | nein |
| Dateiauslieferung | OP:780, OP:781 | `Content-Disposition` mit `filename` und `filename*=UTF-8` | DL-A-3 `Übersicht "A".pdf` | beide Formen, korrekt kodiert; Web und API gleich | GM-016 | R1-14 | nein |

### 1.3 PAR-03 · Fragenbank, Quiz, Auswertung

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Auslieferung | CF:182, MA:417 | Frage ohne Lösung | Q-A1-001 | JSON ohne `is_correct` | GM-017 | R1-06 | nein |
| Auswertung | CF:188, CF:190 | Falsche Antwort ausgewertet, Lösung danach sichtbar; Doppel-Submit zählt einfach; ohne Pro kein Erklärungstext (E-20) | Q-A1-001, Key `fx-017-1` zweimal | `correct=false`, `correct_ids=[A1-001-b]`, kein Feld `explanation`; 2. Aufruf `repeated=true`, `attempts=1` | GM-018 | R1-09, R1-28 | nein |
| Bewertungsregeln | Code `src/Learning/Services/QuestionPresenter.php:177` (INV-A-025), indirekt CF:234 | single = genau eine richtige; multi = exakte Menge; open = Selbsteinschätzung | Q-A1-001 (single), Q-A2-001 (multi, 5 Opt., 2 richtig), Q-B1-010 (open) | multi exakt → richtig; Teilmenge → falsch; Obermenge → falsch; open `known=true` → richtig, `false` → falsch | GM-019 | R1-06, R1-09 | teilweise (Teil-/Obermenge ohne direkten Test) |
| Offene Frage (Lernen) | CF:257 | Musterantwort nach Aufdecken, Selbsteinschätzung | Q-B1-010 | `reveal` 200, `model_answer ≠ ''`; `known=true` → `correct=true` | GM-020 | R1-06 | nein |
| Offene Frage (Prüfung) | Code `src/Exams/Services/ExamService.php:435` (`EXAM_ANSWER_LOCKED`); MA:583 nur Vorbedingung | Ohne Aufdecken keine Selbsteinschätzung | EP-B-1, Pos. 1 = Q-B1-010 | ohne reveal 422 `ccap_exam_answer_locked`; nach reveal 200 | GM-021 | R1-08 | **ja** |
| Anti-Ankreuzen | PT:435 | Keine Prüfung durch Ankreuzen aller Optionen bestehbar | Q-A2-015, Q-A3-009 (alle Optionen richtig) | je Bogen und Blueprint: freie Fragen (≤ 2) < erforderliche richtige (23) | GM-022 | R1-26, R1-15 | nein (Quelle prüft im Paketbau; CC im Publish-Validator) |
| Entwurf | PT:226 | Entwurf/unveröffentlichter Typ nie in einer Prüfung | Q-A3-011 (Entwurf) | nicht in EP-A-*, nicht im Zufallspool | GM-023 | R1-08, R1-26 | nein |
| Token | CF:198, CF:388, MA:454 | Manipuliertes oder fremdes Frage-Token abgewiesen | Token von U-L1 + `x`; Token von U-L1 benutzt durch U-X | 422 `ccap_invalid_token` | GM-024 | R1-06, R1-28 | nein |
| Token-Lebenszyklus | CF:472, CF:480, CF:488 | TTL 2 h; nach Zugangsende gesperrt; Frage nicht mehr auslieferbar | Q-A1-020; Zugang entzogen; Override `restricted` | TTL = 7200 s; Antwort/Aufdecken 403; restricted 404 | GM-025 | R1-06, R1-14 | nein |
| Antwortreihenfolge | – (INV-A-028, `QuestionPresenter.php:209` mit `random_int`) | Gemischte Reihenfolge stabil je Token/Snapshot und unverzerrt | Seed 20300115, Q-A1-001 | Resume/Reload zeigt identische Reihenfolge; Position der richtigen Antwort über 400 Ziehungen gleichverteilt (χ², p > 0,01) | GM-026 | R1-06, R1-08 | **ja** |

### 1.4 PAR-04 · Prüfungen, Versuche, Snapshot, Bewertung

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Start/Resume | CF:207 (§ CF:203), MA:579 | Start und Fortsetzen derselben Zufallsprüfung | ER-A, U-L1 | 201; zweiter Start liefert dieselbe `session_id` | GM-027 | R1-08 | nein |
| Laufend | CF:209, E2E-L:111-112 | Laufende Prüfung ohne Lösungen | ER-A | 30 Fragen ohne `is_correct`/`correct_ids`/`explanation`; UI 0 Feedback-Elemente | GM-028 | R1-08 | nein |
| Blueprint/Snapshot | CF:218, CF:434 | Zusammensetzung nach Blueprint; Teilzuordnung im Snapshot | ER-A (A1:7, A2:13, A3:10; P1/P2) | Zählung `{M-A1:7, M-A2:13, M-A3:10}`; `question_parts` `{P1:7, P2:23}`; `evidence_eligible=true` (LB-Feld `live`), `reduced=false` | GM-029 | R1-08 | nein |
| Teilgrenze | CF:234, CF:235 | Gesamtquote erreicht, Teilgrenze verfehlt → nicht bestanden | ER-A; die ersten 3 P1-Fragen falsch, Rest richtig | 27/30; P1 4/7 < 5 nicht bestanden; P2 23/23 bestanden; gesamt `passed=false` | GM-030 | R1-09 | nein |
| Snapshot-Vorrang | CF:442 | Wertung folgt dem Snapshot; ein leerer Teil fällt nicht automatisch durch | ER-A-Sitzung, Snapshot: alle → P2; alle richtig | P1 `total=0, empty=true, passed=true`; P2 30/30; `passed=true` | GM-031 | R1-09, R1-33 | nein |
| Pool zu klein | CF:450 | Verkürzte Prüfung ist nie nachweisfähig | Blueprint M-A1 temporär 100 000 | `reduced=true`, `evidence_eligible=false` | GM-032 | R1-08 (E-23) | nein |
| Übungsprüfung | CF:252, E2E-L:179 | Übungsprüfung ist eine veröffentlichte Regel mit `purpose=practice` und nie nachweisfähig; eine Regel im Entwurf ist für Lernende nie startbar | ER-B (`purpose=practice`); ER-A als Entwurf v2 ohne veröffentlichte Version | ER-B: `evidence_eligible=false`, Kennzeichen „Übung“; Entwurf: Start 422 `ccap_exam_not_startable` (Grund `rule_unavailable`) | GM-033 | R1-08 (E-23, E-129) | teilweise (Quelle: `verified=false` → Übung; die Entwurfssperre ist eine Abweichung, §5.1 A-02) |
| Fester Bogen | CF:242 | Fester Bogen mit fester Fragenliste | EP-A-1 | `mode=paper`, 30 Fragen in Fixture-Reihenfolge | GM-034 | R1-08 | nein |
| Ablauf | CF:246, LR:286 | Abgelaufene Prüfung wird nach der Kulanz automatisch ausgewertet | ER-A, `deadline = T0 − 180 s` | `status=evaluated`, `end_reason=expired` (E-75); späte Antwort `ccap_exam_expired`/`ccap_exam_finished` | GM-035 | R1-08, R1-20 | nein |
| Offline idempotent | LR:206, LR:209 (§ LR:198) | Dieselbe Antwort zweimal = gleicher Stand; ein älterer Stand überschreibt keinen neueren | Pos. 1 zweimal; Pos. 2 `age_ms` 0 vs 60 000 | beide `applied=true`, gleiche `selected_ids`; der ältere `applied=false` und liefert den gespeicherten Stand | GM-036 | R1-20 | nein |
| Frist/Kulanz | LR:213, :214, :221, :228, :238, :242 | Nach Fristende Restzeit 0; Antwort ohne Gerätezeit abgewiesen; strenger Modus; Nachzügler innerhalb der Kulanz | `deadline = T0 − 60 s`, Kulanz 120 s | `running`, `remaining=0`, Fragen bleiben gesperrt; ohne `age_ms` → `ccap_exam_expired`, nichts gespeichert; Markierung → `ccap_exam_expired`; Kulanz 0 → abgewiesen; `age_ms=90000` → angenommen, `answered_at ≤ T0−85 s` | GM-037 | R1-20 | nein |
| Offline REST | LR:255, :257, :264 | REST liefert Fachcode und gespeicherten Stand; Abgabe wertet nachgesendete Antworten | wie GM-037 | 422 `reason=ccap_exam_expired`; mit `age_ms` 200 `applied=true`; Abgabe: Pos. 1, 3, 4 richtig | GM-038 | R1-20, R1-28 | nein |
| Nach Abgabe | CF:237, MA:594, :618, :620, :622, LR:312 | Keine Änderungen nach Abgabe; Abgabe idempotent; Ergebnis erst nach Abgabe | ER-A | Antwort/Markierung → 409 `ccap_exam_already_submitted`, nichts gespeichert; 2. Abgabe gleiches Ergebnis; Ergebnis vor Abgabe 409 | GM-039 | R1-08 | nein |
| Abbruch | LR:299, :301, :302, :303, :310, :312, :319 (§ LR:288) | Abbruch ohne Wertung | ER-A mit 1 Antwort | `aborted`, `score=null`, `submitted_at` gesetzt; nicht im Verlauf; Fragen sofort frei; Antwort bleibt gespeichert; weitere Antwort → Fehler; erneuter Abbruch/Abgabe ändert nichts; U-X kann nicht abbrechen | GM-040 | R1-08 | nein |
| Abbruch-Sonderfälle | LR:333, LR:338 | Abgebrochener Versuch verdrängt nicht das Bogenergebnis; abgelaufene Sitzung wird beim Abbruch gewertet | EP-A-1 zweimal; ER-A mit `deadline=T0−3 h` | `last` = gewerteter Versuch; Abbruch → `evaluated` | GM-041 | R1-08 | nein |
| Zugangsende | LR:384, LR:386 (§ LR:368), MA:671, MA:673 | Zugangsende sperrt die laufende Prüfung auch bei aktivem Teilumfang; Abgabe bleibt möglich | U-L2 (G-2 während der Prüfung widerrufen), AR-02 aktiv | weitere Antwort 403 `ccap_content_locked`; Abgabe → `evaluated` | GM-042 | R1-20, R1-13 | nein |
| Sperre im Lernen | CF:212, LR:293, LR:302, MA:1527, MA:1529 | Fragen einer laufenden Prüfung sind im Lernmodus gesperrt | ER-A läuft | Lernmodus liefert keine Prüfungsfrage; gezielter Abruf 403 `reason=running_exam`; nach Abgabe/Abbruch frei | GM-043 | R1-08, R1-12 | nein |
| Ohne Zeitlimit | CF:455 | Ohne Zeitlimit Fragensperre höchstens 24 h | ER-B (ohne Limit), Start T0−1 h bzw. T0−2 d | gesperrt bzw. frei | GM-044 | R1-08 | nein |
| Startsperre | CF:470 | Paralleler Start per Named Lock verhindert Doppelsitzung | zweite DB-Verbindung hält Lock; Timeout-Filter 1 s | 422 (Retry-Hinweis, CC: `ccap_busy_retry`), keine neue Sitzung | GM-045 | R1-20, R1-42 | nein |
| Start-Limit | – (Code `ExamService.php:196`, INV-A-039) | Höchstens 40 neue Sitzungen je Person und Stunde; Fortsetzen zählt nicht | U-L1, 40 Start/Abbruch-Paare in der Clock-Stunde | 41. Start → 429 `ccap_rate_limited` mit `retry_after > 0`; Fortsetzen der laufenden Sitzung erlaubt; nach +1 h wieder frei | GM-046 | R1-20 | **ja** |
| Markieren | NC:235, :238, :242, :245, :247, :255, :257 (§ NC:230); MA:597-613, :623; E2E-L:120-137 | Frage markieren/entfernen, idempotent, Resume, Ergebnis, Sperre nach Abgabe | EP-A-1 | `flagged_count` 1→2, Wiederholung idempotent; fehlendes Feld 400/422; unbekannte Position 404; nach Reload erhalten; Ergebnis zeigt Markierungen; nach Abgabe 422/409 | GM-047 | R1-08, R1-16 | nein |
| Drip/Bogen | CF:424, CF:427 | Drip gilt auch für feste Bögen | AR-05 (Drip 30 d auf M-A2), EP-A-1 enthält M-A2 | `locked=true`, `lock_reason=drip`, `unlock_at > T0`; Start 403; ohne Regel frei, `unlock_at=null` | GM-048 | R1-08, R1-13 | nein |
| IDOR | CF:386, MA:588, :590, :609 | Fremde Prüfung nicht sichtbar oder änderbar | U-X auf die Sitzung von U-L1 | 404 | GM-049 | R1-03, R1-28 | nein |
| Offline-UI | E2E-L:139-153, E2E-K:51-72 | Antwort ohne Netz gepuffert, Hinweis sichtbar, nachgesendet | Browser offline während Pos. 5 | 1 localStorage-Eintrag, nach Bestätigung 0; Hinweis mobil `position:fixed`; 4 Antworten auf dem Server | GM-050 | R1-16, R1-20 | nein |
| Abbruch-UI | E2E-K:74-84 | Rückfrage, Rückkehr zur Übersicht, Bestätigung einmal | ER-A | Rückfrage-Text vorhanden; Startknopf wieder da; 0 doppelte Flash-Meldungen | GM-051 | R1-16 | nein |
| Revisionswechsel | – (R1-27) | Import einer neuen Revision während einer laufenden Prüfung | PKG-v2 (Q-A1-007 rev2) importiert, während die Sitzung Q-A1-007 rev1 enthält | Sitzung zeigt und bewertet rev1; neue Sitzung nutzt rev2 | GM-052 | R1-08, R1-27 | **ja** |
| Doppelabgabe parallel | – (Quelle nur sequenziell, LR:312) | Zwei gleichzeitige Abgaben | 2 Prozesse, synchronisierter Start (Muster VG:150-166) | genau eine Auswertung; beide Antworten gleich; 1 Audit-Eintrag | GM-053 | R1-20 | **ja** |
| Gerätezeit-Grenzen | – (INV-A-045 „begrenzt auf [Start…]“, kein Test) | `age_ms` größer als die Laufzeit seit Start | Sitzung seit 60 s, `age_ms=600000` | `answered_at` = `started_at` (geklemmt), nie davor | GM-054 | R1-20 | **ja** |

### 1.5 PAR-05 · Favoriten, Sammlungen, Karteikarten

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Merkliste | CF:201, E2E-L:92-96 | Favorit setzen, Lernen im Modus Favoriten | Q-A1-001 | Modus `favorites` liefert Q-A1-001; Zähler ≥ 1 | GM-055 | R1-11 | nein |
| Sammlung anlegen | MS:167, :168, :169, :171 (§ MS:164) | Name bereinigt; Dublette ohne Beachtung der Schreibweise; leer abgewiesen; nur Favoriten einsortierbar | U-L3, Name `"  Wiederholung  "` | Name `Wiederholung`; `wiederholung` abgewiesen; `"   "` abgewiesen; Nicht-Favorit abgewiesen | GM-056 | R1-11 | nein |
| Übersicht/Lernen | MS:176, MS:178 | Übersicht Favoriten mit Sammlungen; Lernen nach Sammlung | 2 Favoriten, Q1 in COL-1 | Q1 `collections=[COL-1]`, Q2 `[]`, `count=1`; `next(favorites, COL-1)` = Q1 | GM-057 | R1-11 | nein |
| Grenzen/IDOR | MS:180, MS:181, MA:494, MA:496 | Frage außerhalb des Teilumfangs; fremde Sammlung | AR-01; COL-1 durch U-X | abgewiesen; 404 | GM-058 | R1-11, R1-03 | nein |
| Kaskaden | MS:183, MS:186, MA:545 | Entfavorisieren entfernt aus Sammlungen; Löschen der Sammlung entfernt Zuordnungen, Favorit bleibt | COL-1 mit Q1 | `questionIds(COL-1)=[]`; 0 Zuordnungen; Favorit Q1 bleibt | GM-059 | R1-11 | nein |
| Sortierung | MA:511, :515, :517, :521, :523, :525, :530, :540 | `saved_at` absteigend (Standard), aufsteigend; `assigned_at` nur mit Sammlung; stabil; Nulldatum am Ende | 3 Favoriten (2 mit gleichem Zeitstempel, 1 Nulldatum) | Reihenfolge deterministisch nach (Zeit, Frage-ID); ungültige Parameter 422 | GM-060 | R1-11 | teilweise (nur App-API) |
| Idempotenz | MA:469, :475, :478, :481, :483 | Wiederholung mit Key; „in Arbeit“; anderer Inhalt; verwaister Eintrag | Key `fx-061-1` | idempotent; 409 `ccap_request_in_progress` (Alter 5 s); 409 `ccap_idempotency_key_reused`; nach > 5 min übernommen | GM-061 | R1-28 | nein |
| Karteikarten Modus A | CF:280; MS:204, MS:205 | Selbsteinschätzung `open/practice/known`; Teilumfang | FC-A-01…08; AR-06 (5 frei) | Status `known` gespeichert; 5 offen, gesperrte ohne Rückseite; Status für gesperrte Karte abgewiesen | GM-062 | R1-11 (E-21) | nein (Quelle: Praxiskarten, Mechanik abstrahiert) |
| Karteikarten Modus B | CF:326, CF:508 | „Gelernt nach 3 × gewusst in Folge“; Doppelantwort ignoriert | FC-B-01…05 (Modus Serie) | nach 3 × gewusst `learned`; zweite Antwort binnen Sekunden bzw. mit veralteter Serie ignoriert | GM-063 | R1-11 (E-21) | nein (Quelle: Funk-Vokabeln, Mechanik abstrahiert) |
| Karte aus Paket entfernt | CF:114, CF:133 | Entfernte Karte wird archiviert und nicht gelöscht; Rollback reaktiviert | PKG-v2 ohne FC-A-08 | `source_archived`; nach Rollback aktiv | GM-064 | R1-15 | nein |

### 1.6 PAR-06 · Fehlertraining und Smarttrainer

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Reihenfolge | LR:112, :126, :127, :129, :131 (§ LR:109) | Erst neue, dann falsche, dann fällige Fragen; Modus „falsch“ sofort | U-L1, M-A1 (24 Fragen ≥ 20) | 1. Antwort falsch → `unsure`; die nächsten 12 alle „noch nie gesehen“, keine Doppelung; `wrong` liefert die falsche sofort; ohne neue: falsche zuerst, Grund „zuletzt falsch“ | GM-065 | R1-12 | nein |
| Stufen | LR:146, :148, :152, :154 (§ LR:133) | 3 richtig in Folge = „sicher heute“, nicht gefestigt | Frage aus GM-065 | Serie `[1,2,3]`, `secure`; `due_at = T + 20 h`; weitere richtige am selben Tag zählt nicht; Summary `secure=1, mastered=0, percent=0, secure_percent=100, due=0` | GM-066 | R1-12, R1-10 | nein |
| Intervalle | LR:158, :161, :163, :166, :168, :172, :174, :177 | Konsolidierung an anderem Tag; wachsende Abstände; falsch setzt zurück | Clock T0+21 h, dann fällig | `due=1`; neue vor fälliger; fällige `secure` vor nicht fälligen; anderer Tag → `mastered`, due +7 d; nicht fällig zählt nicht; fällig richtig → +21 d; falsch → `unsure`, `due_at=null`, Serie 0 | GM-067 | R1-12 | nein |
| Bestand | LR:186, :187, :189 (§ LR:179) | Gefestigter Altbestand ohne `due_at` bleibt gefestigt | 2 Zeilen `mastered`, zuletzt T0−8 d bzw. T0−1 d | `mastered=2, percent=100, due=1`; `dueAt = last + 7 d`; fällige Frage wird vorgelegt | GM-068 | R1-12, R1-40 | nein |
| Abstand/Tabs | CF:498, CF:499 | Zweiter Tab erhöht die Serie nicht; eben beantwortete Frage kommt nicht sofort wieder | 2 Tokens derselben Frage | Serie 1, zweite `streak_counted=false`; nächste Frage ≠ gerade beantwortete | GM-069 | R1-12 | nein |
| Modus „falsch“ | CF:194, CF:196 | Fehlertraining liefert die falsche Frage; richtige Antwort → in Bearbeitung | Q aus GM-018, Clock +10 min | Frage wird geliefert; danach `learning`, Serie 1 | GM-070 | R1-11 | nein |
| Fehler aus Prüfung | MA:617, MA:627; Code `Rest/Controllers/LearnerController.php:255`, `ExamService.php:562` | Falsch beantwortete Fragen einer ausgewerteten Prüfung als Übungsmenge | EP-A-1 ausgewertet mit k = 4 falschen | `wrong_question_ids` = 4 IDs; Trainer liefert nur daraus; Web-Pfad, fremde Prüfung → 404, laufende Prüfung → 409 | GM-071 | R1-11 | teilweise (nur App-API, nur Positivfall; Inventur A INV-A-051 „–“ ist zu korrigieren) |
| Freigabegrenzen | MS:145, MS:229 | Smarttrainer nur im Teilumfang und ohne gesperrte Module | AR-01, AR-04 | 4 Ziehungen ⊆ erlaubte Fragen; keine aus M-A2 | GM-072 | R1-12, R1-13 | nein |
| Rate-Limit | LR:362, :364, :366 (§ LR:345) | Höchstens 60 Antworten je Minute, atomarer Zähler | U-L1 mit 59 Vorbelegungen | 61. Antwort → `ccap_answer_rate_limited`; Zähler in der Tabelle, keine Option; nach Reset wieder möglich | GM-073 | R1-12, R1-42 | nein |
| Gezielt üben | MA:1444, :1446, :1447, :1460, :1465, :1472, :1491, :1494, :1518 | Bestimmte Frage üben, mit Validierung und Sperren | Q-A1-005; Q-B1-001 (anderer Kurs) | fehlende/inkonsistente Parameter 422; fremder Kurs 404; Antwort zählt im selben Lernstand; Teilumfang 403 `partial_access`; Drip 403 `scheduled_release` | GM-074 | R1-12 | teilweise (nur App-API) |

### 1.7 PAR-07 · Lernfortschritt und Completion

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Zählung | LR:154, LR:196 | Stufen summieren sich zur Gesamtzahl | 10 Fragen: 4 neu, 1 unsicher, 2 in Bearbeitung, 2 sicher heute, 1 gefestigt | Summe = 10; `secure=2`, `secure_percent=20`, `due=1`, `completed=1` | GM-075 | R1-10 | nein |
| Teilumfang | MS:138 | Kursfortschritt zählt nur den Freiumfang | AR-01 (3 je Modul) | `limit=3`, `available=3` je Modul, `catalog > 3` | GM-076 | R1-10, R1-13 | nein |
| Kanalgleichheit | MA:435, MA:448 | Zweite Schnittstelle nutzt denselben Lernstand | U-L1 | dieselben Tabellen, identische Kennzahlen | GM-077 | R1-10 | nein |
| Reset | – (PAR-07 „Reset“) | Fortschritt eines Kurses zurücksetzen | U-L1 nach GM-067 | Fortschritt C-A = 0 Zeilen; Prüfungshistorie gemäß R0-08 erhalten; Audit-Eintrag | GM-078 | R1-10 | **ja** |
| Bericht | – (PAR-07 „Berichte“) | Fortschrittsbericht je Kurs für berechtigte Rollen | C-A mit U-L1, U-L2, U-L3 | 3 Zeilen mit Kennzahlen aus GM-075; U-TUT nur für C-A | GM-079 | R1-10, R1-03 | **ja** |
| Schema | LR:56 | Spalte `due_at` per Migration | Schema n−1 | Spalte vorhanden, Version gesetzt | GM-080 | R1-40 | nein |

### 1.8 PAR-08 · Zugang, Drip, Teilumfang, Grants (standalone, ohne Woo)

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Standardzugang | CF:391, CF:394 (§ CF:382) | „Nur mit Zugang“ sperrt; freie Regel öffnet | `default_access=entitled_only`, AR-08 | ohne Grant 403; mit AR-08 200 | GM-081 | R1-13 | nein |
| Teilumfang | MS:134, :136, :191, :192, :193, :209, :210, :221 (§ MS:133) | Teilumfang je Bereich; Vollzugang hebt ihn auf | AR-01/02/03, G-3 | ohne Regel kein Zugriff; 3 je Modul; Bögen 1–2 frei, Zufallsprüfung gesperrt, gesperrter Start abgewiesen; 2 Lektionen; nach G-3 `limit=null` | GM-082 | R1-13 | nein |
| Drip | MS:227, :228, :231, :235, :236, :239 (§ MS:223) | Zeitgesteuerte Freigabe je Modul/Kapitel ab Zugangsbeginn bzw. Registrierung | AR-04 (M-A2, 7 d), AR-05 (Lektionsmodul LM-A2, 30 d), U-L4 (registriert T0−40 d) | `unlock_at = Beginn + 7 d`, gesperrtes Modul abgewiesen; nach +8 d frei; Kapitel gesperrt; U-L4 frei (Beginn = Registrierung) | GM-083 | R1-13 | nein |
| Laufzeit | CO:183, :184, :189, :191, :219 (§ CO:178) | Laufzeit, Verlängerung `extend`, Modus `parallel`, Übersicht | G-4 (30 d), G-5 (extend), G-6 (parallel) | G-4 endet T0+30 d; G-5 beginnt an dessen Ende; G-6 beginnt T0; Übersicht 1 Eintrag mit spätestem Ende | GM-084 | R1-13 | nein |
| Mehrfachquelle | CO:234 (§ CO:227) | Endet eine von zwei Quellen, bleibt der Zugang | 2 Grants gleichen Umfangs, einer widerrufen | Zugriff bleibt | GM-085 | R1-13 | nein |
| Ablauf | CO:207, :208, :210 | Cron markiert abgelaufene Grants; Abgleich belebt sie nicht | G-7 (`valid_until = T0−1 d`) | Zugriff gesperrt, `expired`; nach Abgleich weiter `expired` | GM-086 | R1-13, R1-17 | nein |
| Manuelle Aktionen | CO:237, :238, :240, :256, :258, :260, :267, :269, :271, :273 (§ CO:236) | Aussetzen, Begründungspflicht, Enddatum, dauerhaft, Widerruf, Audit | G-1 | ohne Begründung abgewiesen; Enddatum in der Vergangenheit abgewiesen; `suspended` und Aufhebung; dauerhaft; `revoked`; jede Änderung im Audit | GM-087 | R1-13 | nein |
| Grant-API Idempotenz | VG:144, :145, :146, :147, :166, :168 | Gleicher Schlüssel → gleicher Grant; Konflikt; Wettlauf zweier Prozesse | FakeSource, Key `fx:grant:0001`, U-L3 | 1 Grant; anderer Nutzer → `ccap_idempotency_conflict`; ungültiger Key → `ccap_invalid_request`; 2 Prozesse → genau 1 Grant, der andere Aufruf erhält ihn oder `ccap_temporary_failure` | GM-088 | R1-13 (LB-11 Kern) | nein (Quelle: Woo-Produkt als Ziel; Kern neutral) |
| Grant-API Regeln | VG:172, :175, :178, :181, :182, :183, :184, :188, :189, :191, :193, :194 | Verlängerung, parallel, dauerhaft, bestehender Zugang, Widerruf nach Regel | FakeSource-Ziel 365 d bzw. 30 d bzw. ohne Dauer | Anschluss am Ende; Beginn sofort; dauerhaft; bei bereits dauerhaftem Zugang `ccap_access_exists` und nichts angelegt; ohne Nutzer `ccap_user_required`; Widerruf `revoked`, Verlängerung rückt vor; Regel „behalten“ | GM-089 | R1-13 | nein |
| Grant-Audit | VG:199, :202, :204 | Audit und Suche nach Referenz | wie GM-088 | Audit mit Nutzer, Quelle, Referenz, Zeit; Suche findet den Grant | GM-090 | R1-13, R1-17 | nein |
| Zugangsstatus | MS:1039, :1041, :1043, :1045, :1047, :1048, :1073, :1075 (§ MS:1034) | Statusanzeige: ausstehend/abgelaufen/gesperrt/teilweise | G-8 pending, G-7 expired, G-9 suspended, AR-01 | Priorität `suspended` > `expired`; abgelaufene Sperre zählt nicht; Teilumfang → `limited` | GM-091 | R1-13, R1-16 | nein (Kaufbezug R3) |
| Quellen kombiniert | – | Manuell + Gruppe + frei gleichzeitig | U-S1 mit G-10 (manuell C-A `question_bank`) und Platz in GRP-1 | Vereinigung der Bereiche; spätestes Ende gewinnt; Entzug einer Quelle lässt die anderen | GM-092 | R1-13 | **ja** |
| Öffentlicher Filter | EX:427, :428, :429 (§ EX:426) | Filter „besitzt Kurs“ | U-L1, U-L3, Gast | `true`; `false`; `false`; Vorgabe `true` anderer Plugins bleibt | GM-093 | R1-28 | nein |

### 1.9 Gruppen, Seats, Claim

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Anlage | MS:416, :420, :423, :425, :426, :427 (§ MS:400) | Gruppe entsteht idempotent; Vorschau schreibt nichts; Verwalter ohne Einzelzugang | GRP-1 über FakeSource-Ereignis | vor „aktiv“ keine Gruppe; Vorschau ohne Schreiben; 3 Plätze, 365 d; Verwalter ohne Einzelgrant; Wiederholung ohne 2. Gruppe | GM-094 | R1-13 | teilweise (Quelle nur aus Bestellung, Inventur B Z. 95) |
| Beitritt | MS:430, :440, :441, :442, :443, :447; E2E-M:47-56 | Beitrittslink, Fehlerseiten, Gast, Doppelbeitritt | JT-1 (Join-Token GRP-1), U-S1, U-S2 | Link beschreibt die Gruppe; kaputter Link verständliche Meldung; Gast-API 401, Seite mit Hinweis; zweimal U-S2 belegt nur 1 Platz; `freeSeats=1`; Kurs voll frei | GM-095 | R1-13 | nein |
| Parallelität | MS:456, :457, :460, :478, :479 | Platzvergabe unter Named Lock; Rückfall ohne GET_LOCK | zweite DB-Verbindung hält Lock, Timeout 0 | Beitritt abgewiesen, kein Platz belegt; Einladung übersprungen, 0 Mails; NULL-Lock → weiter ohne Sperre (protokolliert) | GM-096 | R1-42 | nein |
| Kapazität/Rechte | MS:490, :492, :493, :494 | Laufzeit der Gruppe; letzter Platz; voll; nur Verwalter | GRP-1, U-S3, U-S4 | Grant endet mit der Gruppe; Einladung reserviert den letzten Platz, Rest übersprungen; voll → abgewiesen; U-S1 kann nicht verwalten | GM-097 | R1-13, R1-03 | nein |
| Persönliche Einladung | MS:502, :505, :508, :510 | Vorschau ohne Adresse; Annahme; Platz freigeben | INV-1 (`einladung@fixture.test`) | Vorschau ohne Adresse; angenommen; Freigabe widerruft den Grant, Platz frei; neu belegbar | GM-098 | R1-13 | nein |
| Ablauf/Cron | MS:527, :530, :531, :534, :537, :545, :548, :549, :551 | Offene Einladung belegt Platz; Ablauf 30 d; Adresslöschung | INV-2 (T0−31 d), INV-3 (entfernt) | offen belegt; abgelaufen nicht, Link ungültig; Cron → `expired`; Adresse T0+30 d gelöscht; entfernt → Adresse sofort weg | GM-099 | R1-13, R1-17 (E-44) | nein |
| Aussetzen/Wiederaufleben | MS:555, :558, :561, :563, :566, :567, :571, :574, :577, :580 | Gruppe und Plätze folgen dem Quellstatus; manuelle Entscheidung hat Vorrang | FakeSource-Ereignisse suspend/resume/end/reinstate | alle Plätze ausgesetzt; Wiederaufleben nur aktiver, entfernte bleiben draußen; manuelle Aussetzung/Widerruf bleibt; beendete Gruppe ohne Beitritt | GM-100 | R1-13 (Auslöser Shop: R3) | nein |
| Platzreduktion | MS:588, :596, :598 | Weniger Plätze: zuletzt Beigetretene verlieren zuerst | GRP-1 mit 6 Plätzen, 4 belegt, Reduktion auf 3 | 3 bleiben, letztes Mitglied verliert den Platz | GM-101 | R1-13 | nein |
| Kontolöschung | MS:616, :618, :621, :622; AC:253 | Mitglied bzw. Verwalter löscht sein Konto | U-S2, U-GO | Platz frei, Grants des Platzes widerrufen, Adresse weg; Gruppe bleibt ohne Verwalter, Betreiber verwaltet | GM-102 | R1-13, R1-17 | nein |
| Privacy | MS:628, :638, :645, :648, :649 | Export/Löschung von Mitgliedschaften und Einladungen | GRP-1, INV-1 | Name ohne Links/Adressen; Export ohne Tokens; Einladung ohne Konto exportierbar und löschbar | GM-103 | R1-17 | nein |
| Rückkehr | MS:653, :654, :657, :659 | Nach Login/Registrierung zurück zur Einladung | JT-1 | Rücksprung auf die Einladung; fremdes Ziel ignoriert | GM-104 | R1-13, R1-16 | nein |
| Verwaltungs-UI | E2E-M:58-72 | Verwalter sieht Mitglieder; Entzug sperrt den Kurs | GRP-1 | Mitgliederliste; nach Entzug sind die Inhalte gesperrt | GM-105 | R1-35 | nein |
| Manuelle Anlage | – (Inventur B Z. 95) | Gruppe ohne Kauf durch den Admin | GRP-2 (C-B, 5 Plätze, 90 d) | gleiche Zustände wie GM-094…GM-105, ohne Commerce | GM-106 | R1-13, R1-35 | **ja** |

### 1.10 Content-Gate und geschützte Dateien

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Seite | MS:253, :254, :255 (§ MS:241) | Geschützte Seite | PAGE-1 (geschützt für C-A) | U-L3 Hinweis; U-L1 Inhalt; Redakteur Inhalt | GM-107 | R1-14 | nein |
| Umgehungswege | MS:259, :260, :263, :266, :270, :273, :278, :279 | Builder-Rückschreiben, Feed, Auszug, REST | PAGE-1, Filter auf Priorität 1 | bleibt gesperrt; keine Layoutdaten; Feed, Auszug und `/wp/v2` ohne geschützten Text | GM-108 | R1-14 | nein |
| Abschnitt | MS:281, :283, :286 | Shortcode/Block sperrt einen Abschnitt | PAGE-2 | gesperrt; mit Zugang sichtbar | GM-109 | R1-14, R1-28 | nein |
| Performance/Memo | MS:307, :308, :310, :312, :314, :315 | 10 gesperrte Beiträge mit begrenzten Abfragen; Memo-Invalidierung | POST-01…10 | ≤ 100 Abfragen; Nutzerwechsel verwirft Memo; Regel entfernt → sofort frei | GM-110 | R1-14, R1-22, R1-25 | nein |
| Datei | MS:331, :332, :333, :334, :336 (§ MS:323) | Anhang geschützt ausliefern | MED-004 als Anhang | in den Schutzordner verschoben, `.htaccess`; Mediathek-URL geht über die Prüfung; Aufheben → zurück | GM-111 | R1-14 | nein |
| Ablage | SE:73, :74, :75, :77, :78 (§ SE:70) | Ordner mit Geheimnis, Skriptsperre | leere Site | Ordnername enthält Geheimnis; kein erratbarer Pfad; `.htaccess` (2.2/2.4) + `index.php`; Medienordner sperrt php/html/svg | GM-112 | R1-14 | nein |
| Umzug/Rückfall | SE:91, :93, :100, :101, :102, :104, :106 | Altordner verschieben; Rückfall ohne Datenverlust | Altlayout | vollständig verschoben; bei Fehler alter Ordner aktiv; Retry erst nach Wartezeit | GM-113 | R1-14, R1-40 | nein |
| Privater Ordner | SE:115, :121, :122, :123, :132, :137 (§ SE:109) | Ablage außerhalb des Webroots | Konstante `COURSE_CAPTAIN_PRIVATE_DIR` | keine öffentliche URL; Selbsttest „privat“; Rückweg schreibt Pfade zurück | GM-114 | R1-14 | nein |
| Selbsttest | SE:237, :238, :239, :241, :260, :266, :270, :275 (§ SE:217) | Direktabruf der Schutzordner prüfen | HTTP-Stub `pre_http_request` | 200 → kritisch mit nginx-Regel; 403 → grün; keine Antwort → gelb; Ergebnis zwischengespeichert | GM-115 | R1-14, R1-38 | nein |
| Seiten-Cache | SE:735, :743, :744, :746, :747, :748, :753, :757 (§ SE:675) | Persönliche/geschützte Seiten nicht cachen | PAGE-1, PAGE-2 | normale Seite cachebar; geschützte: `DONOTCACHEPAGE` + nocache-Header; Objekt-/DB-Cache bleiben | GM-116 | R1-14, R1-25 | nein |
| Restore-Check | OP:738, :739, :746, :747, :755 (§ OP:731) | Prüfung nach Wiederherstellung | Site nach Restore, MED-004 entfernt | nur lesend; fremder Ordner gemeldet, bleibt; fehlende geschützte Datei gemeldet | GM-117 | R1-23 | nein |

### 1.11 PAR-09 · Import, Export, Rollback

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Import | CF:47, :54, :58, :60-67 (§ CF:45); E2E-A:23-38 | Prüfen → Vorschau → Freigabe → Import; ohne Freigabe kein Lauf | PKG-v1 | `validated`; `runStep` ohne Freigabe → Fehler; `published`; Zählungen = Manifest (2 Kurse, 5 Module, 71 Fragen inkl. 1 Entwurf, 4 Mediendateien, 2 Regelwerke, 4 Bögen, 13 Karteikarten, 12 Lektionen) | GM-118 | R1-15 | nein |
| Reimport | CF:78, CF:82 (§ CF:69) | Identisches Paket ändert nichts | PKG-v1 erneut | 0 Änderungen, 0 neue Revisionen | GM-119 | R1-15 | nein |
| Änderung | CF:108, :113, :114 (§ CF:84) | Geändertes Paket: Revision, Archivierung | PKG-v2 | Vorschau `update=1` (Q-A1-007), `archive=1` (FC-A-08); rev2 `published`, rev1 `deprecated`; Karte `source_archived` | GM-120 | R1-15 | nein |
| Rollback | CF:122, :126, :131, :132, :133, :134, :135 (§ CF:115) | Rollback mit Begründung, ohne neue Revision | v2 → v1 | ohne Begründung abgewiesen; Vorschau 1 Frage/1 Karte; keine neue Revision; rev1 aktiv, rev2 `deprecated`; Karte aktiv; PKG-v1 aktiv; Audit | GM-121 | R1-15 | nein |
| ZIP-Sicherheit | CF:150; AD:1000, :1005, :1013, :1016 | Traversal, Symlink, fremde Dateien vor dem Entpacken abgewiesen | PKG-BAD-TRAV, -LINK, -FOREIGN | abgewiesen, keine Teilentpackung | GM-122 | R1-15 | nein |
| Rechte-Override | CF:155, :156, :163 (§ CF:152) | Override mit Begründung und Audit | Q-A3-010 (`unknown`) | gespeichert + Audit; ohne Begründung abgewiesen | GM-123 | R1-15 (E-18) | nein |
| Veraltete Vorschau | AD:81, :82, :83, :84, :85, :88 (§ AD:71) | Ältere Vorschau/Freigabe überschreibt kein Neueres | 2 Importe von PKG-v2/PKG-v1 | älterer als veraltet erkannt; Freigabe abgewiesen; Start verweigert; verwerfbar | GM-124 | R1-15 | nein |
| Teilschritte | AD:98, :99, :148 | Vorschau in Teilschritten; Medien nach Bytes | PKG-v1 | Teilschritte = Ergebnis am Stück; `previewed`; Medien-Schritt ≥ 1 Datei | GM-125 | R1-41 | nein |
| Abbruch/Mischstand | AD:107, :108, :112, :116, :117, :120, :122, :126, :127, :135, :137, :144 (§ AD:102, AD:129) | Hängender Import, Mischstand, Wiederherstellung | Import nach Schritt 1, Clock +11 min | laufend nicht verwerfbar; zweiter wartet; > 10 min hängend; Mischstand vermerkt; Rollback auf das aktive Paket; danach kein Mischstand; Fehler außerhalb der Transaktion sofort verwerfbar | GM-126 | R1-15 | nein |
| Sammelfreigabe | AD:170, :171, :172, :173, :781, :783, :786, :788, :791; E2E-A:51-61 | Rechte gesammelt freigeben mit Rückfrage | Q-A3-010, FC-A-07 `unknown`, 1× `restricted`, 1× archiviert | `restricted` bleibt; archivierte ausgenommen; `unknown` freigegeben; Rückfrage nennt die Anzahl; ohne Bestätigung keine Ausführung; Rücksprung ohne JS | GM-127 | R1-15, R1-05 | nein |
| Medientypen | SE:149, :150, :151, :152, :153, :154, :155, :162, :170, :206, :207, :209 (§ SE:142) | Nur erlaubte Dateitypen | MED-BAD-1…6 | PNG erlaubt; `.php`, `x.php.png`, SVG/HTML, `.htaccess`, PHP-Inhalt, MIME-Abweichung abgelehnt; Altbestand in Quarantäne; Protokolleintrag genau einmal | GM-128 | R1-15, R1-07 | nein |
| Upload/Ablage | AD:1020, :1021, :1023, :1024, :1027, :1032, :1075, :1077, :1080, :1141, :1143, :1154, :1156 | Upload-Grenze, Ablageordner, Systemdateien | PKG-v1 mit `.DS_Store`, `__MACOSX/` | Grenze = kleinster Wert; Systemdateien ignoriert, nicht entpackt; übernommener Ordner gelöscht, abgelehnter bleibt | GM-129 | R1-15 | nein |
| Link-Import | AD:1185, :1188, :1189, :1190, :1193, :1195, :1197, :1201 (§ AD:1160) | Download nur https, ≤ 3 Weiterleitungen, SHA-256 | HTTP-Stub | 4. Weiterleitung/http/interne Adresse → Abbruch; passende Prüfsumme übernommen, falsche verworfen; keine Temp-Dateien | GM-130 | R1-15 | nein |
| Aufbewahrung | OP:224, :228, :242, :243, :244, :245, :246 (§ OP:196) | Paketkopien und Protokoll aufräumen | 4 Paketkopien, Clock +200 d | aktives Paket + jüngstes Rollback-Ziel + laufender Import bleiben; ältere gelöscht; Protokoll `unchanged` 14 d / Änderungen 180 d | GM-131 | R1-15, R1-17 (E-44) | nein |
| Paket-Determinismus | PT:122, PT:130, PT:198, PT:212 | Eindeutige IDs, auflösbare Referenzen, Prüfsummen, reproduzierbarer Build | PKG-v1 zweimal gebaut | identische `package_id`/`content_sha256`; 0 Dubletten; 0 offene Referenzen | GM-132 | R1-15 | nein |
| Content-Export | – (PAR-09 „Export“) | Kursexport und Reimport auf einer leeren Site | C-A | Struktur-Hash und Zählungen identisch | GM-133 | R1-15 | **ja** |
| CLI | – (INV-C-163…172) | Import/Rollback/Status über CLI | PKG-v1/v2 | gleiche Zustände wie GM-118/GM-121; `--dry-run` schreibt nichts | GM-134 | R1-15, R1-38 | **ja** |
| Schema-Validator | – (INV-C-091) | JSON-Schema-Teilmenge als Unit | Schemafälle SV-01…10 | unbekannte Schlüsselwörter brechen ab; `$ref` über Dateien; `if/then`, `contains` | GM-135 | R1-15 | **ja** |
| Großimport | – (R1-41; Quelle nur AD:98 Teilschritte) | Batch mit Fehlerreport und Wiederaufnahme | PKG-LARGE (20 000 Fragen synthetisch, 3 defekt) | 19 997 importiert, Report mit 3 Einträgen; Abbruch bei 50 % → Wiederaufnahme ohne Dubletten | GM-136 | R1-41 | **ja** |

### 1.12 PAR-10 · Admin-Autorenworkflow und Darstellung

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Kursbau ohne Import | – (Release-Gate 23.2; Quelle hat keinen Editor) | Admin legt C-A vollständig von Hand an | Spezifikation C-A | Struktur-Hash = Hash des importierten C-A | GM-137 | R1-05, R1-26 | **ja** |
| Audit-Ansicht | AD:508, :511, :513, :517, :519, :521, :523, :524, :525, :528, :534, :538, :562 (§ AD:494) | Verlauf je Zugang/Gruppe im Klartext, Filter, Rechte | G-1 mit 3 Änderungen | Klartext statt JSON; Filter Nutzer/Bereich/Ereignis/Zeitraum/ID; seitenweise; nur Admin; keine E-Mail im Klartext | GM-138 | R1-17, R1-05 | nein |
| Ereignisnamen | AD:612, :613, :614, :630 (§ AD:568) | Jede Audit-Aktion hat eine Bezeichnung und einen Bereich | Registry | 0 unbekannte Ereignisse | GM-139 | R1-17 | nein |
| Hinweise | AD:1227, :1232, :1233, :1234, :1245, :1247, :1251, :1254, :1255, :1261, :1263, :1264, :1265, :1278 | Gebündelte, signierte, vertagbare Hinweise | 3 Hinweise (1 Fehler) | Sammelhinweis, Fehler zuerst; Fehler nur vertagbar; je Nutzer; Signatur nur für eigenen Nutzer; ≤ 50 Meta-Einträge | GM-140 | R1-05, R1-38 | nein |
| A11y Admin | AD:876, :882, :883, :933, :953 (§ AD:866) | Kontrast, Beschriftung, Text statt Symbol | Settings-/Listen-Screens | Kontrast ≥ 4,5:1; 0 unbeschriftete Felder; kein ✓/✗ ohne Text | GM-141 | R1-19, R1-05 | nein |
| Listen-Abfragen | AD:747, AD:771 | Abfragezahl unabhängig von der Zeilenzahl | 4 vs. 24 Grants; 2 vs. 12 Audit-Zeilen | gleiche Abfragezahl | GM-142 | R1-22 | nein |
| Lernraum mobil | E2E-L:73-86, E2E-L:189-197 | 390 px: Rückmeldung sichtbar, Fokus, kein Overflow, keine JS-Fehler | U-L1 | Rückmeldung über der Leiste; Fokus „Nächste Frage“; kein horizontales Scrollen; `errors=[]` | GM-143 | R1-16, R1-19 | nein |
| Publish-Validator | – (R1-26; Quelle nur im Paketbau, PT) | Veröffentlichung fehlerhafter Kurse verweigern | C-C (Modul ohne Fragen, Blueprint > Pool) | Veröffentlichen verweigert mit 2 benannten Fehlern | GM-144 | R1-26 | **ja** |

### 1.13 PAR-11 · REST/AJAX, Fehlercodes, Idempotenz, Rechte

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Fehlerhülle | MA:140, :141, :639, :640, :647; LR:255; Code `LearnerController.php:458-485` (INV-D-090) | Fehlercode aus dem Code abgeleitet, nicht aus dem Text; stabile Abbildung | – | Forbidden → 403 `ccap_forbidden`; NotFound → 404; Fachfehler → 422 mit `reason`; Rate → 429 + `retry_after`; sonst 500 ohne Details; `Cache-Control: no-store, private` | GM-145 | R1-28 | nein |
| Status | SM:91, :92, :95 (§ SM:87) | Status-Endpunkt nur für Admin | U-ADM, Gast | 200 mit aktuellem Schema; Gast 401/403 | GM-146 | R1-28, R1-38 | nein |
| Health | SE:631; MA:137, :138 | Health ohne Versionsleck | Gast | 200 ohne Plugin-Version, `no-store` | GM-147 | R1-38 | nein |
| Monitor | OP:692, :696, :699, :702 (§ OP:686) | Anonym nur Gesamtstatus; Cron-Stillstand | Clock +3 h 1 min ohne Lauf | anonym 200 Gesamtstatus; Admin Einzelprüfungen; Stillstand 503 | GM-148 | R1-38 | nein |
| Wartung | OP:393, :394, :396, :398, :400, :409 (§ OP:386) | Schreibende Anfragen während fälliger Migration | Schema n−1 | schreibend 503 + `Retry-After`; Migration als Cron angestoßen; lesend ok; fremde Schnittstellen unberührt; danach normal | GM-149 | R1-02, R1-40 | nein |
| Idempotency-Key | MA:427, :437, :440, :442 | Antwortschlüssel-Semantik | Q-A1-001, Key `fx-150-1` | ohne Key 422; gleicher Key → gleiches Ergebnis, kein 2. Versuch; gleicher Key mit anderer Nutzlast 409; ein Token zählt einmal | GM-150 | R1-28 | nein |
| IDOR | MA:456, MA:458; MS:181 | Fremde Versuche/Sitzungen/Sammlungen | U-X | 404 | GM-151 | R1-03 | nein |
| Client-IP | SE:665, :672; MA:1818, :1820, :1822, :1823, :1824, :1826, :1828 | IPv6 je /64; X-Forwarded-For nur von eingetragenen Proxys | IPs `2001:db8::1/::2`, Proxy `10.0.0.0/8` | gleiches /64 teilt das Limit; nicht vertrauenswürdige XFF ignoriert; rechter, nicht vertrauenswürdiger Eintrag zählt | GM-152 | R1-28, R1-34 | nein |
| Caps-Basis | SM:66, SM:138; AD:534, AD:562; RO:141; FQ:350 | Caps bei Aktivierung, Entfernung bei Uninstall; Admin-only-Seiten | U-ADM, U-EDT, U-L1 | Admin hat alle `ccap_*`-Caps; nach Uninstall entfernt; Protokoll und Hinweise nur mit Recht | GM-153 | R1-03 | nein |
| Tutor-Scope | – (Konzept 23.1 „Tutor fremder Kurs“) | Tutor sieht nur zugewiesene Kurse | U-TUT (C-A) | C-A-Versuche lesbar; C-B 403 `ccap_forbidden`; Bearbeiten von C-B 403 | GM-154 | R1-03 | **ja** |
| Vertrag | MA:1105 (§ MA:1075) | Routen = Spezifikation | OpenAPI von CC | keine Route nur im Code bzw. nur im Vertrag | GM-155 | R1-28 | nein |

### 1.14 Cron und Wartung

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Isolierte Teilaufgaben | OP:89, :90, :119, :120, :121, :122, :123 (§ OP:80) | Fehler einer Teilaufgabe bricht den Lauf nicht ab | Sitzung mit leerem Snapshot, defekte Sitzung, werfende Teilaufgabe | Lauf endet; Fehler protokolliert; leerer Snapshot ausgewertet; defekte Sitzung `error`; Grant-Ablauf lief trotzdem | GM-156 | R1-17 | nein |
| Selbstheilung | OP:504, :508, :511 (§ OP:502) | Fehlende Läufe neu einplanen | Cron-Einträge gelöscht | neu geplant, keine Doppelplanung | GM-157 | R1-17 | nein |
| Aktivieren/Deaktivieren | OP:645, :646, :657, :660, :661, :667, :675, :676, :678 (§ OP:631) | Cron-Events und Endpunkte im Lebenszyklus | – | Deaktivierung trägt alle eigenen Events aus (auch einmalige); Reaktivierung plant neu, Endpunkte sofort erreichbar | GM-158 | R1-01 | nein |
| Zugangsende-Mail | MS:979, :985, :986, :990, :992, :996, :1012; MS:1233, :1234, :1247, :1257, :1258, :1261, :1271, :1276, :1287, :1289; OP:567, :578, :580, :581, :585, :589 | Erinnerung gebündelt, je Zugang einmal, Stapel/Zeitbudget/Sperre | 3 Personen mit Ablauf T0+10 d, 1 unbefristet | 1 Mail je Person, gebündelt; keine Mail bei unbefristetem Zugang; Stapel 50 / 20 s; Fortsetzung; bei gehaltener Sperre keine Mail; Fehler bei einem Empfänger bricht nicht ab | GM-159 | R1-21, R1-17 | nein (Texte mit Kaufbezug R3) |
| Abmeldelink | MS:1111, :1115, :1116, :1118, :1125, :1140, :1149, :1158, :1168; E2E-K:87-99 | Signierter Ein-Klick-Widerspruch | U-L1 | `List-Unsubscribe` + One-Click; GET zeigt Knopf; POST mit falscher Signatur ändert nichts; nach Widerspruch keine Mail | GM-160 | R1-21 | nein |
| Zählerbereinigung | MA:1867, MA:1868, MA:1871 | Rate-Limit-Fenster aufräumen | abgelaufene Fenster | stündlicher Cron löscht sie | GM-161 | R1-17 | nein |
| Audit-Retention | – (INV-C-149) | Audit-Einträge nach Frist löschen | 6 000 Einträge T0−5 J., 10 T0−1 J. | 1. Lauf löscht 5 000, 2. Lauf 1 000; die jungen 10 bleiben | GM-162 | R1-17 (E-44) | **ja** |

### 1.15 PAR-12 · Migration, Locks, Upgrade

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Erstaktivierung | SM:51, :52, :57, :59, :61, :62, :66 (§ SM:49) | Schema vollständig angelegt | leere Test-DB | alle Tabellen, InnoDB, FK-Anzahl = Soll, Schemaversion, Caps | GM-163 | R1-02 | nein |
| Idempotenz | SM:71, :72, :74, :75, :76, :78, :84 (§ SM:69, SM:80) | Deaktivieren/Reaktivieren/Migrieren idempotent; FK greift | – | Tabellen bleiben; keine doppelten FK; `dbDelta` meldet nichts; Antwort ohne Revision abgewiesen | GM-164 | R1-02 | nein |
| Options-Sperre | OP:66, :68, :70, :71, :73, :75, :77 (§ OP:59) | Atomare Sperre mit Übernahme toter Sperren | – | 2. Lauf erhält keine Sperre; Fremde können nicht freigeben; tote Sperre wird nach TTL übernommen | GM-165 | R1-40 | nein |
| Migrationsort | OP:129-139, :156, :157, :158, :161 (§ OP:128) | Keine Migration in Besucheranfragen; Backoff | Schema n−1, 1 erzwungener Fehlschlag | Frontend/AJAX nur planen; Admin/Cron/CLI ausführen; Backoff 15 min; danach erfolgreich, Fehler gelöscht | GM-166 | R1-40 | nein |
| Fehlerhinweis | OP:166, :170, :171, :172, :191 (§ OP:164) | Ein Hinweis, maskiert, nur für Admin | erzwungener Fehler | genau 1 Hinweis; DB-Meldung maskiert; nur `manage_options` | GM-167 | R1-40 | nein |
| Indizes | OP:261, :262, :268, :269 (§ OP:259) | Fehlende Indizes per Migration ergänzen | Schema n−1 ohne Index | Indizes vorhanden | GM-168 | R1-02 | nein |
| Downgrade | OP:382, OP:384 (§ OP:371) | Neueres Schema: keine Rückmigration | Schema n+1 | Hinweis statt Wartungssperre; aktuelles Schema → nichts | GM-169 | R1-23 | nein |
| Upgrade-GM | UC:1-95; CI `ci.yml:566-591` | Update von der Vorversion ohne Datenverlust | Vorversion + Lernstand aus GM-065/GM-027 | gleiche Zeilenzahl je Tabelle, Schema aktuell, FK vollständig, kein Notice im Log | GM-170 | R1-23, R1-40 | nein |
| Wiederaufnahme | – (R1-40; Quelle hat Lock OP:59 und Backoff OP:128, aber keinen Abbruch mitten in einer Migration) | Abgebrochene Migration läuft ab dem letzten Schritt weiter | Abbruch nach Schritt k | Fortsetzung ab k+1, keine Dubletten, Version erst am Ende | GM-171 | R1-40 | **ja** |

### 1.16 Privacy (Export, Löschung)

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Lerndaten | SM:113, :115, :118, :119 (§ SM:97) | Export und Löschung über die WP-Privacy-Hooks | U-L1 mit Fortschritt + Favorit | Export enthält Gruppen `cc-progress`, `cc-favorite`; Löschung `items_removed=true`; 0 Restzeilen; Audit `personal_data_erased` | GM-172 | R1-17 | nein |
| Vollständigkeit | AC:348, :350, :352, :353, :355, :357, :361, :362, :366, :367, :369 (§ AC:300) | Alle personenbezogenen Tabellen eingestuft und exportiert | alle CC-Tabellen befüllt für U-L1 und U-X | keine Daten von U-X; Prüfungsfragen mit Antwort/Markierung/Zeit; Sammlungen; keine Hashes/Tokens; jede Tabelle eingestuft; Löschung lässt fremde Daten | GM-173 | R1-17 | nein |
| Selbstbedienung | AC:167, :173, :177, :178, :185, :187, :193, :204, :208, :231, :236, :240, :246; E2E-K:102-126 | Export-/Löschantrag mit Bestätigung; Löschung erst per POST | U-L1, U-SM | Antrag + Mail; zweiter Antrag kein neuer; GET löscht nicht; POST löscht; Verwaltungsrolle ohne Selbstlöschung | GM-174 | R1-17 (E-43; Woo-Teile R3) | nein |
| Asynchroner Export | MA:1540, :1543, :1545, :1548, :1564, :1566, :1569, :1580, :1591 | Export im Hintergrund | U-L1 | `pending` → `ready`; vor Fertigstellung 409; nur Eigentümer; abgelaufen gelöscht; Fehler → `failed`/410 | GM-175 | R1-17 (E-05) | nein |
| Datenschutztext | RO:224, :226, :227, :231, :233, :234 (§ RO:223) | Vorschlagstext über WP-Bordmittel | Settings Default | Abschnitt vorhanden; Fristen genannt; 0 externe Links | GM-176 | R1-17, R1-34 | nein |
| Kontolöschung | AC:252, :253, :254, :259, :262 | Lerndaten, Platz, Grants bei Löschung | U-S2 mit Platz in GRP-1 | Lerndaten weg; Platz frei; Grants beendet, Abgleich belebt nicht; Audit ohne Klartext-Kennung | GM-177 | R1-17, R1-13 | nein |

### 1.17 Settings

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Defaults/Sanitize | ST:36-43; MS:1013 | Option mit Default-Merge; Grenzen | leere Option | Defaults gesetzt; `reminder_days` 500 → 90, leer → 14; `false` speicherbar | GM-178 | R1-31 | nein |
| Seite | AD:900, :905, :907, :909, :933 | Bereiche, Schlüssel stabil, Teilspeichern | Settings mit 2 Gruppen | Bereiche vorhanden; Option-Schlüssel unverändert; andere Gruppe unverändert; alle Felder beschriftet | GM-179 | R1-32, R1-36 | nein |
| Wirkung Zugang | CF:391; `e2e/setup.php:50` | `default_access` steuert den Zugang | `logged_in` vs. `entitled_only` | 200 vs. 403 für U-L3 | GM-180 | R1-31 | nein |
| Migration | – (R1-31/R1-36) | Settings-Schema n−1 → n | Option v0 mit 1 unbekanntem Schlüssel | Werte übernommen; unbekannter verworfen; Speichern nur mit `manage_options` | GM-181 | R1-31, R1-36 | **ja** |
| Medien/Privacy | OP:728; AC:289 | Untertitel-Werte; automatische Privacy-Anträge aus | `video_captions=xyz`; `privacy_requests_auto=false` | `off`; bestätigter Antrag wartet auf die Verwaltung | GM-182 | R1-34 | nein |

### 1.18 Lifecycle und Uninstall

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Standard | SM:128 (§ SM:121) | Uninstall behält standardmäßig die Daten | `delete_data_on_uninstall=false` | Tabellen bleiben | GM-183 | R1-23 | nein |
| Mit Löschung | SM:133, :135, :138, :139; UP:104-117, :126 | Vollständige Entfernung eigener Daten, fremde bleiben | `delete_data=true`, fremde Option `ccap_learning_x_other` | alle Tabellen/Optionen/Transients/Nutzer-Meta/Cron/Caps/Schemaversion weg; gleich klingende fremde Daten bleiben | GM-184 | R1-23 | nein |
| Manifest | – (INV-C-026: `bp_entitlement_prune_last`, `_bp_catalog_fields` bleiben in der Quelle liegen) | Alles, was geschrieben wird, wird auch gelöscht | Schreib-Registry aller Optionen/Meta | Differenz Registry − Löschliste = ∅ | GM-185 | R1-23 | **ja** (Quelle hat den Fehler, UP prüft nur Präfixe) |
| Multisite | MU:85, :95, :107, :121, :126, :142, :146, :147, :148, :152, :153; UP:143 | je Website, `wpmu_drop_tables`, verwaiste Tabellen | 3 Websites | wie in der Quelle | GM-186 | R1-23 | nein (E-07: offiziell nur Single-Site, Netzwerkaktivierung blockiert nach E-49; der Test sichert nur das saubere Entfernen je Website) |

### 1.19 Querschnitt: Performance und Parallelität (Konzept 23.1, 27.4)

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Referenzfall | – (Konzept 27.4: 10 Lektionen, 50 Fragen, 1 Prüfung) | p95 der Kernanfragen | C-A, 200 Lernende synthetisch | Grenzwerte aus R0-22/R1-22, Abfragen je Anfrage konstant | GM-187 | R1-22 | **ja** |
| Parallele Antworten | – (Konzept 23.1 „parallele Antworten/Grants“; Quelle nur sequenziell CF:498) | 2 Prozesse beantworten dieselbe Frage mit verschiedenen Keys | U-L1, Q-A1-003 | genau 1 Serienerhöhung, 2 Versuche protokolliert, keine Deadlocks | GM-188 | R1-42 | **ja** |

### 1.20 Starter-Inhalte/FAQ (E-16: Mechanik R1 Free)

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Seeder | FQ:63, :128, :144, :204; OP:605, :606, :607, :624, :627, :628 | Starter-Inhalte idempotent, nicht in Besucheranfragen, unter Sperre | 5 neutrale FAQ FX-FAQ-1…5 | 1 Import; Update-Lauf ohne Doppel; Besucheranfrage importiert nicht; Sperre freigegeben | GM-189 | R1-24 | nein |
| Cache/REST/Gate | FQ:259, :262, :265, :311, :336, :341 | Änderungen sofort sichtbar; Gäste ohne Entwürfe; Gate im Block | FX-FAQ-3 mit Gate-Block | sofort aktualisiert; Gast ohne Entwurf; Gast Hinweis statt Abschnitt | GM-190 | R1-24 | nein |

**Summe:** 210 GM-Tests (GM-001…GM-190, GM-191…GM-200 in §1.22, GM-201…GM-208 in §1.23, GM-209/GM-210 in §1.24). 34 davon brauchen einen neuen Test (Lücke **ja**: GM-003, 013, 021, 026, 046, 052–054, 078, 079, 092, 106, 133–137, 144, 154, 162, 171, 181, 185, 187, 188, 191–196, 198–200), 9 sind teilweise belegt (GM-001, 011, 019, 033, 060, 071, 074, 094, 197).

### 1.21 Bewusst nicht in den R1-Golden-Master (Herkunft für die Vollständigkeit)

| Quelle | Inhalt | Grund | Ziel |
|---|---|---|---|
| CO:69–176, :291, :295 | Woo-Statuslogik (BACS, Dispute, Erstattung, Bundle, Storno, Gateway-Filter) | Shop-Auslöser | R3.1 (Kernzustände über GM-084…GM-089 abgedeckt; Bundle-Kern ohne Woo in R1: GM-198, E-25a; Mitgliederrabatt R3.1, E-25a) |
| VG:80–125 | Vertrag `voucher_grants_v1`, Zielprüfung gegen Woo-Produkt | Voucher-Adapter | R3.2 (Kern GM-088…GM-090) |
| `woocommerce-test.php`, `e2e/checkout-*`, EX:153–422, AC:107–161, MS:661–825, AD:179–443 | Checkout, Gutschein, Geld-zurück-Garantie, Katalog/Preise | Commerce | R3 |
| CF:260–380 (bis auf GM-007…GM-010), NC (bis auf § NC:230), `e2e/trainers-flow.mjs`, MS:831–886, MS:887–943 | Navigation, Funk, Gerätesimulation, Seekarte, Kombi-/Ergänzungsempfehlungen | `exclude_domain_specific` (Kap. 39.2) | – |
| `exam-guide-test.php`, `e2e/guide-*` | Prüfungsanmeldung/-leitfaden | fachgebunden | – |
| MA (Anmeldung, Token-Rotation, Universal Links, Rate-Limit Login) | App-API | E-05 (keine App-API in R1) | R2 |
| SE:279–631 (Teil) | Website-weite Härtung: `/wp/v2/users` für Gäste, Autorenarchive, oEmbed-/Feed-Autor, Benutzer-Sitemap (auch Yoast), Anwendungspasswörter nur Redaktion (INV-C-138), E-Mail-Wechsel mit Passwort im Woo-Konto (INV-C-139) | E-261/E-308: nicht im R1-Härtungsmodul (Enumeration, Anwendungspasswörter → R4-Pool); INV-C-139 Woo-Adapter. Der übrige Abschnitt ist in §1.23 katalogisiert (GM-201…GM-208) | R4-Pool bzw. R3 |
| RO:73–181 | eigener Update-Check | E-09: Free nur WordPress.org | entfällt |
| – | Fortschrittseinsicht für Gruppenbesitzer/Delegierte | E-232: nur Pro als Opt-in-Gruppeneinstellung | R3-08 (R0-29 GRP-041) |
| – | Gradebook, Tutor-Bewertung von Prüfungen | E-74, E-99: Oberfläche erst mit dem Gradebook (Pro); Aufgabenbewertung R1-46 ist in GM-195 enthalten | R3 |
| MS:1325–1347 | Export für das Laravel-Original | Quellsystem-spezifisch | entfällt |

### 1.22 Neue Funktionen ohne LB-Quelle (E-20, E-22, E-25a, E-26, E-27, E-69, E-168)

Für diese Funktionen gibt es **keinen** LearnBoating-Quelltest und meist kein LB-Verhalten. Sie sind als **neu (ohne LB-Quelle)** gekennzeichnet; ihre Erwartungswerte folgen aus R0-08 (§1.4, §2.5, §3.1, §3.2, §4.5), R0-09 und R0-10 §4.5, nicht aus einem Referenzlauf (E-56 gilt nicht). Fixture: Zusatzpaket PKG-X1 (§2.9), das die Zählungen von PKG-v1 nicht verändert.

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Zahl mit Toleranz (E-22) | – (neu, ohne LB-Quelle; kein Code aus `NavigationCheck`) | Absolute und relative Toleranz, Grenze inklusive, Dezimaltrennzeichen Komma und Punkt, ungültige Eingabe ist nicht „falsch“, Auslieferung ohne Sollwert | Q-E1-001 (Soll 12,5, Toleranz absolut 0,1, Einheit „cm“), Q-E1-002 (Soll 200, Toleranz relativ 5 %) | Q-E1-001: `12,4` und `12.6` richtig, `12,39` falsch, `12,45` = `12.45`; `12,5 cm`, `1.234,5`, leer → 422 `ccap_invalid_answer` bzw. im Versuch `ccap_exam_invalid_answer`, nichts gespeichert. Q-E1-002: `210` richtig, `210,01` und `189,99` falsch. Token/Snapshot ohne Soll und Toleranz; richtige Antwort zählt im Smarttrainer wie eine Auswahlfrage (`correct` = volle Punktzahl) | GM-191 | R1-06, R1-09 | **ja** (neu) |
| Zuordnung (E-69) | – (neu, ohne LB-Quelle) | Paare mit Distraktor; `all_or_nothing` und `proportional_penalty` (E-77); rechte Seite gemischt und im Snapshot stabil | Q-E1-003 (3 Paare + 1 Distraktor, `all_or_nothing`), Q-E1-004 (3 Paare, `proportional_penalty`), Seed `20300115` | Q-E1-003: 3/3 → 1000 Tausendstel, 2/3 → 0. Q-E1-004: 2 richtig + 1 falsch → 333, 2 richtig + 1 leer → 666, Distraktor zugeordnet zählt als falsch. Reload zeigt dieselbe Reihenfolge; Auslieferung ohne Paarliste | GM-192 | R1-06, R1-09 | **ja** (neu) |
| Sortieren (E-69) | – (neu, ohne LB-Quelle) | Nur exakte Reihenfolge ist richtig (R1 nur `all_or_nothing`); ausgelieferte Reihenfolge ist nie die Lösung | Q-E1-005 (4 Elemente), Q-E1-006 (2 Elemente) | genaue Reihenfolge → richtig; eine Vertauschung → falsch; `scoring=proportional_penalty` → VAL-056 (R0-22); bei Q-E1-006 ist die Auslieferung über 50 Seeds nie die Lösungsreihenfolge | GM-193 | R1-06, R1-09 | **ja** (neu) |
| Lückentext (E-69) | – (neu, ohne LB-Quelle) | Lücken je mit Liste akzeptierter Antworten, Normalisierung (trim, NFC, Groß-/Kleinschreibung, Leerzeichen-Kollaps), Teilpunkte je Lücke | Q-E1-007 (2 Lücken: {„Beispiel“}, {„Konzept“, „Begriff“}; `all_or_nothing`), Q-E1-008 (gleich, `proportional_penalty`) | Q-E1-007: `" beispiel "` + `Begriff` → richtig; eine Lücke falsch → 0. Q-E1-008: 1 richtig + 1 falsch → 0, 1 richtig + 1 leer → 500. Keine semantische Bewertung; Auslieferung ohne akzeptierte Antworten | GM-194 | R1-06, R1-09 | **ja** (neu) |
| Aufgabe: Zustandsautomat (E-168) | – (neu, ohne LB-Quelle) | `draft → submitted → returned → submitted → graded` nach R0-08 §2.5; Bearbeitung nur in `draft`/`returned`; Rückgabe mit Pflichtkommentar; Bewertung gegen den eingereichten Stand; Nachbewertung als neue Auswertung | L-E-01 (Textaufgabe, max. 10 Punkte, Grenze 6), U-L1, Bewerter U-TUT (Trainer C-E) | Entwurf speichern (2×) bleibt `draft`; Einreichen → `submitted`, `submission_no=1`, Inhalts-Hash fest; Änderung danach 409 `ccap_submission_not_editable`; Rückgabe ohne Kommentar 422, mit Kommentar `returned`; erneutes Einreichen `submission_no=2`, Abgabe 1 bleibt lesbar; Bewertung 7/10 → `graded`, `passed=true`, Activity `completed`; Punkte > 10 → 422; zweite Bewertung nur mit Begründung → `evaluation_no=2`, Audit; gleicher `request_key` beim Einreichen → keine Doppelung; U-X → 404 | GM-195 | R1-46, R1-10 | **ja** (neu) |
| Aufgabe: Dateien und Rechte (E-168) | – (neu, ohne LB-Quelle) | Upload nur in den eigenen Entwurf, Allowlist/MIME/Größe nach R0-10 §4.5, geschützte Auslieferung, Abruf durch Bewertende auditiert, Privacy | L-E-02 (Dateiaufgabe, Allowlist `pdf`, `png`, Limit 2 MB), MED-004, MED-BAD-1…6 | PDF 1 MB angenommen; `x.php.png`, SVG, `.htaccess`, MIME-Abweichung, 3 MB abgewiesen; Upload nach `submitted` 409; Datei nur über die geschützte Route (`Content-Disposition: attachment`, `nosniff`); U-X 404, U-TUT 200 mit Audit; Privacy-Export enthält Abgabe und Dateimetadaten, Eraser entfernt die Datei | GM-196 | R1-46, R1-14, R1-17 | **ja** (neu) |
| Lösungserklärungen (E-20) | Code LS:628 (`explanation_locked` ohne Feature `learning_explanations`); kein Test | Free liefert nie Erklärungstexte; Pro liefert sie; Lizenzende friert ein (E-268); Add-on deaktiviert blendet aus (E-269); Text bleibt gespeichert und exportierbar; Lösungsanzeige nach E-147 | Q-A1-001 mit `feedback_incorrect`; Entitlement-Stub `course.explanations` an/aus/abgelaufen; EP-A-1 mit `max_attempts=3` | Free: Lernmodus `correct`, `correct_ids`, kein `explanation`; Pro aktiv: `explanation` = Text der Token-Revision; Lizenz abgelaufen: weiter ausgeliefert; Add-on deaktiviert: nicht ausgeliefert, DB-Wert unverändert; Export enthält den Text in allen Fällen; Prüfung nach Abgabe mit Restversuchen nur richtig/falsch, ohne Restversuche oder bestanden zusätzlich die richtige Antwort | GM-197 | R1-09, R1-15 (Pro-Teil R3-08) | teilweise (LB sperrt per Feature, ohne Test) |
| Bundle (E-25a) | – (Kern neu; LB nur Woo-Bundle, §1.21) | Ein Grant schaltet mehrere Kurse frei; Widerruf entzieht alle; andere Quellen bleiben | U-L6 mit BND-1 (FakeSource, Ziele C-A und C-B), zusätzlich G-16 (manuell, C-B) | C-A und C-B frei; Widerruf BND-1 → C-A gesperrt, C-B bleibt über G-16; ein Audit-Eintrag je Quellereignis mit beiden Zielen | GM-198 | R1-13 | **ja** (neu) |
| Abhängiger Zusatz-Grant (E-26) | – (neu, ohne LB-Quelle) | Zusatzmodul läuft so lange wie der Basis-Grant | U-L5: G-14 (C-B, 30 d), G-15 (C-A, `module_id` = M-A3, abhängig von G-14) | M-A3 frei, M-A1 403 `no_access`; G-14 ausgesetzt → M-A3 gesperrt, fortgesetzt → frei; Clock +31 d (G-14 abgelaufen) → M-A3 gesperrt; Verlängerung von G-14 per `extend` vor Ablauf verlängert die Wirkung von G-15 ohne Änderung an G-15 | GM-199 | R1-13 | **ja** (neu) |
| Zugang je Modul (E-27) | – (neu; LB vergibt je Kurs × Feature) | Freischaltprüfung je Modul; Grant ohne Modul = ganzer Kurs | U-L7 mit G-12 (C-A, `module_id` = M-A2); U-L1 mit G-1 (ganzer Kurs) | U-L7: Fragen, Lektionen und Downloads aus M-A2 frei, aus M-A1/M-A3 403 `no_access`; Smarttrainer zieht nur aus M-A2; Zufallsprüfung ER-A (Blueprint über M-A1…A3) nicht startbar (`no_access`, `requires` nennt die gesperrten Module, E-286); `completion_ratio` von U-L7 hat als Nenner nur die Pflicht-Activities von M-A2, Anzeige „1 von 3 freigeschalteten Modulen“ (E-286); U-L1 unverändert frei | GM-200 | R1-13, R1-12 | **ja** (neu) |

---

### 1.23 Website-Härtung (R1-45; E-10, E-239, E-260, E-261, E-307…E-309)

Belege sind die Zeilen der Inventur C (`R0-02-inventar/C-infrastruktur-content.md`, INV-C-138, INV-C-140…INV-C-146, §4.2 Hook-Tabelle) mit ihren Quelltest-Verweisen (SE:279, :324, :428, :466, :585, :594). Der Abschnitt SE:279–631 war früher ausgeschlossen und ist in R0-05 nicht zeilengenau ausgewertet; die Zeilenbelege je `$check` werden beim Referenzlauf (E-56 gilt nicht, keine Rechenlogik) bzw. in R1-45 nachgetragen. Daher „teilweise“ in der Spalte Lücke. Erwartungswerte folgen dem LB-Verhalten, soweit §5.1 keine Abweichung führt; Grenzen nach R0-31 §3.4. Fixture: F-BASE, U-L1, U-ADM, Gast; Härtung im Tab bestätigt (`hardening.confirmed_at` gesetzt), sonst gilt GM-208.

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Login-Limit | SE:279 (INV-C-143, `LoginGuard.php:58…272`) | Fehlversuche über alle `wp_authenticate`-Wege mit gemeinsamen Zählern, atomar vorgemerkt | U-L1, IP A und B; `wp-login`, XML-RPC, REST Basic | 11. Fehlversuch von IP A (beliebige Konten) gesperrt; 6. Fehlversuch U-L1 von IP B gesperrt (Konto+IP); 31. Fehlversuch U-L1 über mehrere IPs in 15 min gesperrt (Konto); Zähler gelten wegübergreifend; nach Fensterende frei | GM-201 | R1-45 | teilweise |
| Neutrale Antwort | SE:279 (INV-C-144, `LoginGuard.php:335…350`) | Kein Existenzorakel beim Login | bekanntes Konto U-L1, unbekanntes Konto `nobody` | gleiche Meldung für falsches Passwort und unbekanntes Konto; Dummy-Passwortprüfung für unbekannte Konten (Antwortzeit vergleichbar); Sperrmeldung neutral | GM-202 | R1-45 | teilweise |
| Passwort vergessen | SE:428, :466 (INV-C-145, `LoginGuard.php:366…491`) | Gleiche Bestätigung, Limits, Antwort vor dem Mailversand | U-L1, `nobody` | unbekanntes Konto: gleiche Bestätigung wie bekanntes, keine Mail; 11. Anfrage je IP, 4. je Konto+IP, 11. je Konto in 1 h abgewiesen (gleiche Bestätigung); Antwort steht vor dem Mailversand | GM-203 | R1-45 | teilweise |
| Bekanntes Gerät | SE:324 (INV-C-146, `KnownDevice.php:36…110`) | Signiertes Gerätecookie umgeht nur IP- und Konto+IP-Sperre | U-L1 mit gültigem Gerätecookie; IP A gesperrt | Anmeldung mit richtigem Passwort trotz IP-Sperre möglich; Kontosperre (30/15 min) gilt weiter; Cookie nach Passwortänderung ungültig; Laufzeit 180 Tage; Cookiename nach E-285 (`ccap_`), Inhalt ohne Klartext-Personenbezug (PRIV-033) | GM-204 | R1-45 | teilweise |
| Registrierungslimit | SE:594 (INV-C-140, `Hardening.php:130`, Hooks `:94`, `:95`) | Registrierungen je IP begrenzen | `users_can_register = 1`, Gast, IP A | 11. Registrierung von IP A in 1 h abgewiesen; bei `users_can_register = 0` wirkungslos | GM-205 | R1-45 | teilweise |
| XML-RPC | INV-C-138 (`Hardening.php:72…74`), SE:594 | XML-RPC und Pingback aus | Gast; U-L1 | authentifizierte XML-RPC-Methoden abgewiesen (Filter `xmlrpc_enabled`), `pingback.ping` und `system.multicall` entfernt, Header `X-Pingback` fehlt | GM-206 | R1-45 | teilweise |
| Security-Header | SE:594 (INV-C-141, `Hardening.php:144`, `:162`) | Sicherheits-Header setzen | Startseite, Lernbereich `/lernen/` | website-weit `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `X-Frame-Options: SAMEORIGIN`; `Permissions-Policy` nur auf Course-Captain-Antworten mit `camera=(), microphone=(), geolocation=(), payment=()` (**A-04**, E-309); kein Header doppelt, wenn Server oder anderes Plugin ihn bereits setzt (E-307) | GM-207 | R1-45 | teilweise |
| Erkennung Security-Plugins | SE:585 (INV-C-142, `Hardening.php:173`, `:189`) | Bekanntes Sicherheits-Plugin erkannt → Login-Limit zieht sich zurück; Merkmal einzeln abschaltbar | Stub-Plugin mit Kennung aus der Konfliktliste (R0-20 §2.6); Merkmal per Einstellung aus | Login-Limit `held` (keine Hooks, keine Zähler), übrige Merkmale aktiv; abgeschaltetes Merkmal wirkt nicht. Vor der ersten Bestätigung durch den Admin wirkt **kein** Merkmal (**A-03**, E-260) | GM-208 | R1-45 | teilweise |

### 1.24 Aus R0-12 §1.3 übernommene Fälle (früher Vorschläge „GM-191/GM-192“)

Die Nummern GM-191…GM-193 sind in §1.22 für E-22/E-69 vergeben; die Vorschläge aus R0-12 §1.3 heißen deshalb GM-209 und GM-210. Der dritte Vorschlag (Kombikurs) ist durch GM-198 abgedeckt.

| Bereich | Quelltest(s) | Prüfaussage | Fixture | Erwartetes Ergebnis | GM-ID | Ziel-Task | Lücke |
|---|---|---|---|---|---|---|---|
| Fortschritt-Monotonie (E-70) | – (Vorschlag R0-12 §1.3; LB-Code VS:246-247 erlaubt das Zurücksetzen von `completed` generell, kein Quelltest) | Verspätete Anfrage mit älterem Stand nach neuerem | U-L1, L-A-01 | `PUT progress` mit älterem Stand nach neuerem: Stand geht nie zurück; `completed` nur bei `manual_confirm` durch den Lernenden zurücknehmbar (**A-06**, E-70) | GM-209 | R1-10 | **ja** |
| Sperrfrist (`cooldown_seconds`) | – (Vorschlag R0-12 §1.3; Vertrag R0-08 §4.4) | Neuer Start erst nach Ablauf der Sperrfrist | ER-A mit `cooldown_seconds = 3600`, U-L1 mit einem nicht bestandenen Versuch | Start vor Ablauf abgewiesen mit Restzeit, danach erlaubt; Fortsetzen eines laufenden Versuchs nicht betroffen | GM-210 | R1-08, R1-20 | **ja** |

## 2. Neutrales Referenz-Fixture-Paket `cc-fixture-neutral-v1`

### 2.1 Grundsätze

- **Rechtefrei und synthetisch:** Alle Texte sind generischer Platzhaltertext („Beispielfrage A1-001: Welche Aussage trifft auf Konzept 1 zu?“). Medien erzeugt ein Skript deterministisch (`tools/fixtures/build-media`, vorgeschlagen), Lizenz CC0-1.0, Urheber „Course Captain Fixtures“. **Keine LearnBoating-Inhalte, keine Prüfungskataloge, keine Bilder aus `database/data/`** (Inventur E INV-E-119, E-02).
- **Stabile Schlüssel statt DB-IDs:** Tests adressieren Objekte über `source_id`, Slug oder Login. Numerische Auto-Increment-IDs werden nie hart kodiert (die Quelle nutzt dafür Kurscodes und `source_id`, z. B. CF:104, LR:180).
- **Namensraum:** Alle Fixture-Objekte haben das Präfix `cc-fx-` (Daten) bzw. `cc_fx_` (Logins). Domains sind `fixture.test` und `example.test` (RFC 2606/6761).
- **Zeitbasis:** `T0 = 2030-01-15T10:00:00Z`. Alle Zeitangaben sind relativ zu T0 (Abschnitt 3).
- **Format:** Course-Captain-Paketformat v1 (R0-10). Zusätzlich entsteht ein LB-Format-Zwilling für den Referenzlauf (E-56: nur Fälle mit Rechenlogik; das LB-Plugin wird nur ausgeführt, E-01).

### 2.2 Kurse, Module, Lektionen

| ID | `source_id` / Slug | Status | Module (Fragen) | Lektionen | Regelwerk/Bögen |
|---|---|---|---|---|---|
| C-A | `cc-fx-course-a` / `cc-a` „Kurs Alpha“ | veröffentlicht | M-A1 (24; Themen T-A1-a 12, T-A1-b 12), M-A2 (16), M-A3 (10) = **50** | 10 (L-A-01…10) in 3 Lektionsmodulen LM-A1 (4), LM-A2 (3), LM-A3 (3) | ER-A, EP-A-1…3 |
| C-B | `cc-fx-course-b` / `cc-b` „Kurs Beta“ | veröffentlicht | M-B1 (12), M-B2 (8) = **20** | 2 (L-B-01, L-B-02) | ER-B (nicht verifiziert, ohne Zeitlimit), EP-B-1 |
| C-C | `cc-fx-course-c` / `cc-c` „Kurs Gamma“ | Entwurf, absichtlich fehlerhaft: M-C1 ohne Fragen, Blueprint 10 > Pool 0 | M-C1 (0) | 0 | ER-C (ungültig) |
| C-D | `cc-fx-course-d` / `cc-d` „Kurs Delta“ | archiviert | M-D1 (0, verweist auf M-A3) | 0 | – |

Lektionen C-A: L-A-01…03, L-A-08, L-A-09 Text; L-A-04 Video intern (MED-002 + MED-003 Untertitel); L-A-05 Video + Quiz (3 Fragen aus M-A1: Q-A1-010…012); L-A-06 Video-Platzhalter (keine URL); L-A-07 Video extern (MED-005); L-A-10 archiviert. Downloads DL-A-1 `uebersicht.pdf`, DL-A-2 `arbeitsblatt.pdf`, DL-A-3 `Übersicht "A".pdf` (alle MED-004-Kopien). Seiten: PAGE-1 (ganz geschützt für C-A), PAGE-2 (Block- und Shortcode-Abschnitt), POST-01…10 (geschützte Beiträge für GM-110).

Eine Aufgabenabgabe (Konzept 27.4 „eine Aufgabe“) hat **kein** LearnBoating-Vorbild. Nach E-168 kommt sie in R1 (R1-46) und ist mit GM-195/GM-196 als **neu, ohne LB-Quelle** Teil des R1-Golden-Masters. Die Fixture steht im Zusatzpaket PKG-X1 (§2.9); die frühere R2-Erweiterung `v1.1` entfällt.

### 2.3 Fragen und Fragetypen

In der Quelle belegt sind drei neutrale Typen: `single_choice`, `multi_choice`, `open_text` (INV-A-025; LB-10 `chart_task` ist ausgeschlossen). Beschlossen für R1 Free und ohne LB-Quelle sind zusätzlich `numeric_tolerance` (E-22) sowie `matching`, `ordering`, `cloze` (E-69); sie stehen im Zusatzpaket PKG-X1 (§2.9), damit die Zählungen dieses Pakets unverändert bleiben.

| Kurs/Modul | single (4 Opt., 1 richtig) | multi (5 Opt., 2–3 richtig) | open_text (Musterantwort) | Sonderfälle |
|---|---|---|---|---|
| M-A1 (24) | Q-A1-001…016 | Q-A1-017…021 | Q-A1-022…024 | Q-A1-003 mit Bild MED-001; Q-A1-007 mit **rev1/rev2** (rev2 nur in PKG-v2); Q-A1-024 per Override `restricted` in GM-025 |
| M-A2 (16) | Q-A2-002…010 | Q-A2-001, Q-A2-011…015 | Q-A2-016 | Q-A2-015: **alle 5 Optionen richtig** (GM-022) |
| M-A3 (10) | Q-A3-001…005 | Q-A3-006…009 | Q-A3-010 | Q-A3-009: alle Optionen richtig; Q-A3-010 `rights_status=unknown`; **Q-A3-011 Entwurf** (zusätzlich, nicht im Pool) |
| M-B1 (12) | Q-B1-001…006 | Q-B1-007…009 | Q-B1-010…012 | – |
| M-B2 (8) | Q-B2-001…004 | Q-B2-005…007 | Q-B2-008 | – |

Summe veröffentlicht: 70 (C-A 50, C-B 20) + 1 Entwurf. Antwort-IDs: `<frage>-a…e`. Die richtige Antwort steht in der Quelldatei **immer** auf `a` (z. B. `A1-001-a`), die Auslieferung mischt. Damit ist GM-026 (Gleichverteilung) prüfbar. Jede Auswahlfrage hat eine Erklärung (`explanation`). Nach E-20 wird sie nur mit Pro (`course.explanations`) ausgeliefert; der Golden Master prüft Free ohne Erklärung und Pro über einen Entitlement-Stub (GM-197).

### 2.4 Prüfungsblueprints und Bögen

| ID | Kurs | Zusammensetzung | Zeit | Bestehensregel | Kennzeichen |
|---|---|---|---|---|---|
| ER-A | C-A | M-A1 7 (Teil P1), M-A2 13 + M-A3 10 (Teil P2) = 30 | 3 600 s, Kulanz 120 s | Gesamtquote ≥ 0,75 (≥ 23/30) **und** P1 ≥ 5/7 **und** P2 ≥ 18/23 | `status=published`, `purpose=exam` → nachweisfähig (`evidence_eligible`, E-23) |
| EP-A-1…3 | C-A | je 30 feste Fragen, gleiche Teile; EP-A-1 = A1-001…007, A2-001…013, A3-001…010 | wie ER-A | wie ER-A | EP-A-1 enthält Q-A1-007 und M-A2 (für GM-048/GM-052) |
| ER-B | C-B | M-B1 6, M-B2 4 = 10 | **ohne** Zeitlimit | ≥ 0,7 | `status=published`, `purpose=practice` → Übung, nie nachweisfähig (E-23, E-129) |
| EP-B-1 | C-B | 10 feste Fragen, Pos. 1 = Q-B1-010 (offen) | ohne | ≥ 0,7 | – |
| ER-C | C-C | M-C1 10 | 600 s | – | absichtlich ungültig (GM-144) |

### 2.5 Karteikarten

FC-A-01…08 (C-A, Modus „Selbsteinschätzung“ `open/practice/known`; FC-A-07 `rights_status=unknown`, FC-A-08 fehlt in PKG-v2). FC-B-01…05 (C-A, Modus „Serie bis gelernt“, Schwelle 3). Beide Modi sind beschlossen (E-21).

### 2.6 Medien (synthetisch, CC0)

| ID | Datei | Erzeugung | Zweck |
|---|---|---|---|
| MED-001 | `a1-003.png` | 64×64 PNG, Farbfläche `#336699`, Text „A1-003“ | Fragebild |
| MED-002 | `alpha-04.mp4` | 2 s schwarz, 320×180, H.264, stumm | internes Video |
| MED-003 | `alpha-04.vtt` | WebVTT mit 1 Cue „Beispiel“ | Untertitel |
| MED-004 | `fixture.pdf` | 1 Seite „Fixture-Dokument“ | Download/geschützte Datei |
| MED-005 | `https://videos.example.test/cc/alpha-07.mp4` | URL ohne Datei | Fremdhost/Consent |
| MED-BAD-1…6 | `x.php`, `x.php.png`, `x.svg` (mit `<script>`), `.htaccess`, `x.png` (PHP-Inhalt), `y.png` (MIME `text/html`) | erzeugt | GM-128 |

Pakete: **PKG-v1** (Basis mit C-A und C-B; C-C und C-D legt der Fixture-Loader direkt im Status Entwurf bzw. archiviert an; `package_id = cc-fx-neutral-v1-000000000001`); **PKG-v2** (deterministisch aus v1 abgeleitet: Q-A1-007 rev2 mit Textzusatz „(korrigiert)“, FC-A-08 entfernt; `package_id = …-000000000002`; Muster CF:85-103 mit festem Suffix `-00000000c0de`, CF:97); **PKG-BAD-TRAV** (`../evil.txt`), **PKG-BAD-LINK** (Symlink), **PKG-BAD-FOREIGN** (fremde Datei im Wurzelordner), **PKG-SYS** (mit `.DS_Store`, `Thumbs.db`, `__MACOSX/`), **PKG-LARGE** (20 000 generierte Fragen, 3 mit defekter Referenz).

### 2.7 Nutzer und Rollen

| ID | Login | Rolle | Zweck |
|---|---|---|---|
| U-ADM | `cc_fx_admin` | administrator | Verwaltung, Import, Audit |
| U-EDT | `cc_fx_editor` | editor + `ccap_manage_content` | Redaktion, Gate-Bypass |
| U-AUT | `cc_fx_author` | author | darf keine CC-Inhalte (Muster SE:656) |
| U-TUT | `cc_fx_tutor` | CC-Tutor, zugewiesen nur C-A | GM-079, GM-154 |
| U-SM | `cc_fx_manager` | Verwaltungsrolle ohne Selbstlöschung | GM-174 |
| U-L1 | `cc_fx_lea` | subscriber | Vollzugang C-A (G-1) |
| U-L2 | `cc_fx_ole` | subscriber | G-2, Widerruf in der Prüfung |
| U-L3 | `cc_fx_free` | subscriber | ohne Grant, Teilumfang |
| U-L4 | `cc_fx_newcomer` | subscriber, `user_registered = T0−40 d` | Drip ab Registrierung |
| U-GO | `cc_fx_group_owner` | subscriber | Verwalter GRP-1 |
| U-S1…S4 | `cc_fx_seat_1…4` | subscriber | Plätze |
| U-X | `cc_fx_other` | subscriber | IDOR |
| Gast | – | – | Anonym |

E-Mail-Adressen: `<login>@fixture.test`. Passwörter werden pro Lauf zufällig erzeugt und nie ausgegeben; nur die E2E-Konten haben feste Testpasswörter wie im Muster `e2e/setup.php:26-29`.

### 2.8 Zugriffsregeln, Grants, Gruppen

| ID | Art | Definition |
|---|---|---|
| F-BASE | Setting | `default_access=logged_in`; `F-STRICT` = `entitled_only`; `privacy.external_media_mode=click_to_load` (bewusst abweichend vom Produkt-Default `always_load`, damit E2E keine Fremdverbindung aufbauen; GM-013 setzt beide Werte selbst) |
| AR-01 | free_limited | C-A `question_bank`, 3 Fragen je Modul |
| AR-02 | free_limited | C-A `exam`, 2 Bögen (EP-A-1, EP-A-2) |
| AR-03 | free_limited | C-A `lessons`, 2 Lektionen |
| AR-04 | drip | C-A M-A2, 7 Tage nach Zugangsbeginn |
| AR-05 | drip | C-A LM-A2 bzw. M-A2-Bögen, 30 Tage |
| AR-06 | free_limited | C-A Karteikarten, 5 |
| AR-07 | free_limited | C-A Downloads, 1 |
| AR-08 | free | C-A `question_bank` unbegrenzt |
| G-1 | manuell | U-L1, C-A ganzer Kurs (`module_id` leer), `[question_bank, exam]`, unbefristet, Begründung „Fixture“; kein Bereich `explanations` mehr (Erklärungen sind Pro, E-20/E-95) |
| G-2 | manuell | U-L2, C-A `[question_bank, exam]`, wird in GM-042 widerrufen |
| G-3 | manuell | U-L3, C-A `all` (für „Vollzugang hebt Teilumfang auf“) |
| G-4 / G-5 / G-6 | FakeSource | U-L3: 30 d ab T0 / `extend` 30 d / `parallel` 30 d |
| G-7 | manuell | U-X, C-B, `valid_until = T0−1 d` |
| G-8 / G-9 | FakeSource | `pending` bzw. `suspended` (U-L4) |
| G-10 | manuell | U-S1, C-A `question_bank`, bis T0+60 d |
| GRP-1 | Gruppe | Verwalter U-GO, C-A `[question_bank, exam]`, 3 Plätze, 365 d, Join-Token JT-1 = `cc-fx-join-0001` |
| GRP-2 | Gruppe (manuell) | U-ADM legt für C-B an, 5 Plätze, 90 d |
| INV-1 / INV-2 / INV-3 | Einladung | `einladung@fixture.test` offen / T0−31 d abgelaufen / entfernt |
| COL-1 | Sammlung | U-L3, C-A, „Wiederholung“ |

**FakeSource** ist der neutrale Testadapter, der Quellereignisse (`activate`, `suspend`, `resume`, `end`, `reinstate`, `reduce_seats`) erzeugt. Er ersetzt `support/FakeOrders.php` (44 Zeilen), mit dem die Quelle Woo-freie Commerce-Tests ermöglicht (CO:23, CO:61, MS:410). FakeSource wird neu geschrieben (E-01).

Die Bereichsangaben (`question_bank`, `exam`, `lessons` …) der Grants und Regeln stehen unter Vorbehalt von OP-05-2 (Scopes neben `module_id`, E-27).

### 2.9 Zusatzpaket PKG-X1 (neue Funktionen ohne LB-Quelle)

PKG-X1 (`package_id = cc-fx-neutral-v1-x00000000001`) wird nur von den Tests in §1.22 geladen (eigener, idempotenter Loader-Schritt nach PKG-v1). Es ändert keine Zählung aus §2.2–§2.8 (GM-001, GM-006, GM-118 bleiben gültig).

| ID | Inhalt |
|---|---|
| C-E | `cc-fx-course-e` / `cc-e` „Kurs Epsilon“, veröffentlicht; Module M-E1 (8 Fragen), M-E2 (2 Aufgaben-Lektionen); Prüfungsregel ER-E: 8 Fragen aus M-E1, ohne Zeitlimit, Gesamtquote ≥ 0,5, `status=published`, `purpose=exam` |
| Q-E1-001…002 | `numeric_tolerance` (Werte in GM-191) |
| Q-E1-003…004 | `matching` (Werte in GM-192) |
| Q-E1-005…006 | `ordering` (Werte in GM-193) |
| Q-E1-007…008 | `cloze` (Werte in GM-194) |
| L-E-01 | Aufgabe (`assignment`), Abgabeart `text`, max. 10 Punkte, Bestehensgrenze 6, Abschlussregel `graded` |
| L-E-02 | Aufgabe, Abgabeart `file`, Allowlist `pdf`, `png`, Limit 2 MB, max. 10 Punkte |
| U-TUT | zusätzlich Trainer für C-E (Bewertungsrecht für Aufgaben, R0-08 §2.5) |
| U-L5 `cc_fx_addon` | G-14 (manuell, C-B ganzer Kurs, 30 d), G-15 (C-A, `module_id` = M-A3, abhängig von G-14, E-26) |
| U-L6 `cc_fx_bundle` | BND-1 (FakeSource-Bundle-Grant, Ziele C-A und C-B, E-25a), G-16 (manuell, C-B) |
| U-L7 `cc_fx_module` | G-12 (manuell, C-A, `module_id` = M-A2, E-27) |
| U-L1 | G-11 (manuell, C-E ganzer Kurs) |

---

## 3. Determinismus-Regeln (belegt an den Quellmustern)

„Übernehmen“ heißt in dieser Tabelle: Das Muster wird nach der Beschreibung neu implementiert (E-01); kein LB-Testcode wird kopiert.

| Nr. | Regel für Course Captain | Muster in der Quelle | Abweichung/Verbesserung |
|---|---|---|---|
| D-01 | **DB-Isolation:** Tests laufen nur gegen eine DB, deren Name `test` enthält; destruktive Ausnahme nur per Umgebungsvariable | GD:10-13 (`DB_NAME` enthält „test“, sonst `exit(2)`; `BP_ALLOW_DESTRUCTIVE_TESTS=1`) | Übernehmen; zusätzlich je Testfall eine Transaktion mit Rollback (WP-Testsuite, E-11), Szenariotests mit frischer DB |
| D-02 | **Settings-Isolation:** vorher sichern, Defaults setzen, im Shutdown zurückschreiben | ST:36-43 (`register_shutdown_function`); LR:58-59 und :397 | Übernehmen, aber als Fixture-Loader-Schritt statt pro Testdatei |
| D-03 | **Kein Testreihenfolge-Zwang:** jeder GM lädt seine Fixture selbst und idempotent | Gegenbeispiel LR:20 („Voraussetzung: content-flow-test.php“); CI-Reihenfolge ci.yml:172-240 | Ändern: Fixture-Loader `cc-fixture-neutral-v1` (Import PKG-v1, Nutzer, Regeln) ist idempotent; Tests sind einzeln lauffähig (wie ST:19-28 für die Aktivierung) |
| D-04 | **Eingefrorene Zeit:** injizierbare `Clock`, Start T0; Vorspulen nur über die Clock | Die Quelle hat keine Clock (`EntitlementService::now()` = `current_time`, `src/Commerce/EntitlementService.php:525`) und verschiebt deshalb gespeicherte Zeitstempel: LR:156-157, :169-170, :200, :211; CF:186-187 (−600 s), :246; MS:231 (`valid_from −8 d`), :239 (`user_registered −40 d`); mit Toleranzen `abs(…) < 60` LR:148, :166, :174 | Ändern: exakte Gleichheit statt Toleranz. DB-Rückdatierung nur für Altbestandsfälle (GM-068) |
| D-05 | **Seed für Zufall:** injizierbarer `Randomizer`; produktiv CSPRNG, im Test Seed `20300115` bzw. `crc32(GM-ID)` | Quelle nutzt unseedbares `random_int` (`QuestionPresenter.php:209-215`, `ExamService.php:247`). Die Tests umgehen das mit reihenfolgeunabhängigen Prüfungen: Zählung je Modul CF:213-218, richtige Antwort-IDs je Revision aus der DB CF:184-185, :224-226, LR:84-85 | Beides: Assertions bleiben reihenfolgeunabhängig (robust); zusätzlich prüft GM-026 mit Seed Reproduzierbarkeit und Gleichverteilung |
| D-06 | **Feste Schlüssel:** Logins, Idempotency-Keys und Paket-IDs sind fest | Gegenbeispiele: `'flow'.wp_rand(1000, 9999)` CF:167; `wp_generate_uuid4()` CF:186, :195. Positiv: fester Suffix `-00000000c0de` CF:97, fester Key `learnboating:race:0001` VG:150 | Ändern: Keys `fx-<GM>-<n>` |
| D-07 | **Bereinigung über Namensraum:** Löschen über Präfix bzw. ID-Bereich, Nutzer neu anlegen | MS:105-112 (`order_id ≥ 910000`, `product_id ≥ 991000`); LR:60-67 (Nutzer löschen und neu anlegen); LR:389-396 | Übernehmen mit Präfix `cc_fx_`/`cc-fx-` |
| D-08 | **Parallelität reproduzierbar:** zweite DB-Verbindung hält `GET_LOCK`; Lock-Timeout per Filter; echte Prozesse mit synchronisiertem Startzeitpunkt | CF:458-467 (mysqli hält Lock, Filter `…_named_lock_timeout` auf 1 s); MS:450-460 (zweites `wpdb`, Timeout 0); VG:150-166 (`proc_open`, gemeinsamer `startAt = microtime + 2 s`) | Übernehmen; Filter `ccap_named_lock_timeout` |
| D-09 | **Keine Außenwirkung:** Mails abfangen, HTTP stubben, Fremdhosts im Browser erfassen und als Fehler werten | `pre_wp_mail` MS:402-407, :973; `pre_http_request` RO:12, :67 (auch AD, SE); E2E-L:14-19 (`pageerror`, `requestfailed`, `external`) | Verschärfen: Fremdhost-Treffer lassen die E2E fehlschlagen (Quelle protokolliert nur, E2E-L:198). Einzige Ausnahme: GM-013 im Modus `always_load` erwartet genau den Host `videos.example.test` |
| D-10 | **Rate-Limiter je Test zurücksetzen** | LR:86 (`RateLimiter::clear`), LR:349, :391 | Übernehmen (Clock-basiert) |
| D-11 | **Prozess-Isolation für Lebenszyklus:** Aktivierung/Uninstall in eigenem Prozess | ST:19-28 (Neustart nach Aktivierung); SM:133 (`passthru` für `uninstall-phase.php`) | Übernehmen |
| D-12 | **Notice-Gate:** jede PHP-Warning/Notice/Deprecation lässt den Lauf fehlschlagen | ci.yml:173, :179, :183 … (`grep -iE "PHP (Warning\|Deprecated\|Notice\|Fatal)"`) | Übernehmen, auch in PHPUnit (`failOnWarning`) |
| D-13 | **Paket-Determinismus:** gleicher Input ergibt gleiche `package_id`/`content_sha256` | PT:212-224 | Übernehmen für den CC-Fixture-Builder |
| D-14 | **Abfragezählung als Regressionswert** | MS:308 (≤ 100 für 10 Beiträge), OP:290/:317, AD:747/:771 | Übernehmen; Grenzwerte als GM-Erwartung festschreiben |

---

## 4. Lückenliste (Verhalten ohne Quelltest)

| Nr. | Verhalten | Beleg im Code / Anforderung | GM | Neuer Test | Ziel-Task |
|---|---|---|---|---|---|
| L-01 | Prüfungsstart-Limit 40/h (`EXAM_START_RATE_LIMITED`, 429 + `retry_after`) | `Exams/Services/ExamService.php:196-197`; `LearnerController.php:483-485`; INV-A-039 | GM-046 | Integration | R1-20 |
| L-02 | `wrongQuestionIds()`: nur über die App-API und positiv getestet (MA:617, MA:627); Web-Route und Negativfälle fehlen. **Korrektur an Inventur A, INV-A-051** („–“ ist unzutreffend) | `ExamService.php:562`, `LearnerController.php:255`, `Mobile/Controllers/LearningController.php:67` | GM-071 | Integration (Web, IDOR, laufend) | R1-11 |
| L-03 | Offene Frage in der Prüfung: Selbsteinschätzung ohne Aufdecken gesperrt (`EXAM_ANSWER_LOCKED`) | `ExamService.php:435`; MA:583/:658 nur als Vorbedingung | GM-021 | Integration | R1-08 |
| L-04 | Antwortmischung (Stabilität, Verteilung) | `QuestionPresenter.php:209`, INV-A-028 | GM-026 | Unit + Integration | R1-06 |
| L-05 | Teil-/Obermenge bei Mehrfachauswahl | `QuestionPresenter.php:177` | GM-019 | Unit | R1-09 |
| L-06 | Kurs-Entwurf/-Archiv: Sichtbarkeit und Startbarkeit | Quelle kennt nur importierte Kurse | GM-001, GM-003 | Integration | R1-04 |
| L-07 | Externe Medien in beiden Modi (Default `always_load`, Option `click_to_load`, E-45a) | E-45/E-45a; Quelle erkennt Hosts nur (MS:1305ff.) | GM-013 | E2E | R1-07 |
| L-08 | Revisionswechsel während einer laufenden Prüfung | R1-27 | GM-052 | Integration | R1-27 |
| L-09 | Parallele Doppelabgabe; parallele Antworten | Konzept 23.1, R1-20; Quelle nur sequenziell | GM-053, GM-188 | Integration (`proc_open`) | R1-20, R1-42 |
| L-10 | Klemmung der Gerätezeit (`age_ms` > Laufzeit) | INV-A-045 | GM-054 | Integration | R1-20 |
| L-11 | Fortschritt zurücksetzen; Fortschrittsbericht | PAR-07 | GM-078, GM-079 | Integration | R1-10 |
| L-12 | Mehrere Grant-Quellen gleichzeitig (manuell + Gruppe + frei) | R1-13 DoD „Mehrfachzugang“ | GM-092 | Integration | R1-13 |
| L-13 | Gruppe ohne Kauf anlegen | Inventur B Z. 95 (`GroupService::syncItem` nur aus Bestellposition) | GM-106 | Integration + E2E | R1-13, R1-35 |
| L-14 | Content-Export und Reimport | PAR-09 | GM-133 | Integration | R1-15 |
| L-15 | CLI (import/rollback/status/access) | INV-C-163…172 | GM-134 | CLI-Integration | R1-15 |
| L-16 | JSON-Schema-Validator als Unit | INV-C-091 | GM-135 | Unit | R1-15 |
| L-17 | Großimport mit Fehlerreport/Wiederaufnahme | R1-41 | GM-136 | Last/Integration | R1-41 |
| L-18 | Kursbau ohne Import (Editor) | Release-Gate 23.2; Quelle hat keinen Editor | GM-137 | E2E | R1-05 |
| L-19 | Publish-Validator im Produkt | R1-26; Quelle nur im Paketbau (PT:226, :435) | GM-144 | Integration | R1-26 |
| L-20 | Tutor-/Autor-Scope je Kurs | Konzept 23.1; die Quelle kennt nur Admin, Content-Cap und Kunde | GM-154 | Integration | R1-03 |
| L-21 | Audit-Retention 4 J./10 J. | INV-C-149 | GM-162 | Integration | R1-17 |
| L-22 | Abbruch und Wiederaufnahme einer Migration | R1-40 | GM-171 | Integration | R1-40 |
| L-23 | Settings-Migration | R1-31, R1-36 | GM-181 | Integration | R1-31 |
| L-24 | Vollständigkeit des Uninstall-Manifests (Quellfehler `bp_entitlement_prune_last`, `_bp_catalog_fields`) | INV-C-026 | GM-185 | Integration | R1-23 |
| L-25 | Performance-Referenzfall (10/50/1) | Konzept 27.4, R1-22 | GM-187 | Last | R1-22 |
| L-26 | Fremdhost-Gate in E2E als harter Fehler | E2E-L:198 protokolliert nur | GM-013, alle E2E | E2E-Rahmen | R1-18 |
| L-27 | Sehfortschritt, gezieltes Üben, Favoriten-Sortierung, asynchroner Export im Web-Pfad (Quelle nur App-API) | MA:691, :1430§, :500§, :1532§ | GM-011, GM-060, GM-074, GM-175 | Integration (Web-REST) | R1-07, R1-11, R1-12, R1-17 |
| L-28 | Fragetyp „Zahl mit Toleranz“ (neu, ohne LB-Quelle) | E-22, R0-08 §3.1 | GM-191 | Unit + Integration | R1-06, R1-09 |
| L-29 | Fragetypen Zuordnung, Sortieren, Lückentext (neu, ohne LB-Quelle) | E-69, R0-08 §3.1/§3.2 | GM-192…GM-194 | Unit + Integration | R1-06, R1-09 |
| L-30 | Aufgaben-Zustandsautomat mit Bewertung (neu, ohne LB-Quelle) | E-168, R0-08 §2.5 | GM-195 | Integration + E2E | R1-46 |
| L-31 | Datei-Abgaben: Upload-Sicherheit, Auslieferung, Privacy (neu) | E-168, R0-10 §4.5 | GM-196 | Integration | R1-46, R1-14 |
| L-32 | Erklärungen nur mit Pro; Lizenzende/Add-on aus (neu im Kern) | E-20, E-268, E-269; LS:628 | GM-197 | Integration | R1-09 |
| L-33 | Bundle-Grant ohne Woo (neu im Kern) | E-25a | GM-198 | Integration | R1-13 |
| L-34 | Abhängiger Zusatz-Grant (neu) | E-26, R0-09 | GM-199 | Integration | R1-13 |
| L-35 | Zugang je Modul (neu) | E-27, R0-08 §1.4 | GM-200 | Integration | R1-13 |
| L-36 | Website-Härtung (Login-/Reset-Limit, bekanntes Gerät, XML-RPC, Security-Header) | E-10, E-261; SE:279–631 | noch keine GM-ID (OP-05-3) | Integration + E2E | R1-45 |

Außerhalb des GM-Umfangs, aber festzuhalten: Screenreader-/Tastatur-E2E für den Lernraum fehlen in der Quelle. Belegt sind nur `aria-pressed` (E2E-L:122-127), Fokus (E2E-L:86) und die Labels/Kontraste im Admin (AD:876ff.). Diese Tests gehören zu R1-19 und sind nicht Teil der Parität.

---

## 5. Entscheidungsbedarf (beschlossen)

Die beiden Punkte dieses Dokuments sind entschieden und als E-Nummern in `ENTSCHEIDUNGEN.md` geführt:

| Früher | Beschluss | Inhalt | Wirkung hier |
|---|---|---|---|
| EGM-01 | **E-55** (Auftraggeber) | Bewusste Abweichungen vom LB-Verhalten gibt der Auftraggeber schriftlich frei, über die Abweichungsliste §5.1 | Ein GM-Erwartungswert wird nur mit Eintrag in §5.1 (Datum, Begründung, Freigabe) geändert, nie stillschweigend; Gate R1-18, E-184 |
| EGM-02 | **E-56** (Auftraggeber) | Referenzlauf des LB-Plugins nur ausführen, kein Code; nur für Fälle mit Rechenlogik | GM-029…GM-031, GM-065…GM-068, GM-084, GM-131 werden zusätzlich gegen einen LB-Lauf mit LB-Format-Zwilling geprüft (E-02, E-194). Für GM-191…GM-200 gilt E-56 nicht (keine LB-Quelle) |

Abhängigkeiten auf Beschlüsse (früher Inventur-Kürzel): D-E-02 → **E-11** (alle GM); D-E-04 → **E-45/E-45a** (GM-013); D-E-08 → **E-02**; E-A-01 → **E-20**, E-147 (GM-018, GM-031, GM-197); E-A-02 → **E-21** (GM-062/GM-063); E-A-03 → **E-22** (GM-191); E-A-04 → **E-23**, E-129 (GM-032/GM-033); E-A-05 → **E-16** (GM-189/GM-190); E-C-01 → **E-07**, E-49 (GM-186); E-C-02 → **E-10** (OP-05-3); E-C-04 → **E-05** (GM-011/GM-060/GM-074/GM-175, nur der Pfad); E-C-05 → **E-43** (GM-174); E-C-06 → **E-44** (GM-099/GM-131/GM-162); E-C-07 → **E-18** (GM-123/GM-127).

### 5.1 Abweichungsliste (E-55)

Jede bewusste Abweichung eines GM-Erwartungswerts vom belegten LB-Verhalten steht hier. Ein Eintrag ohne Freigabe ist nicht wirksam; der GM prüft dann das LB-Verhalten.

| Nr. | GM | LB-Verhalten (Beleg) | Course Captain | Grundlage / Freigabe | Datum |
|---|---|---|---|---|---|
| A-01 | GM-013 | externe Medien erst nach Einwilligung über ein Theme-Global (R0-10 §5) | Default `always_load`, Option `click_to_load` | Beschluss **E-45/E-45a** (Auftraggeber) | 16.11.2026 |
| A-02 | GM-033 | Rückfall von `examRule()` auf die neueste nicht archivierte Regel, auch einen Entwurf, als Übung (CR:186-216) | Entwürfe nie startbar; Übung nur als veröffentlichte Regel mit `purpose=practice` | Beschluss **E-129** (Claude im Auftrag), E-23 | 16.11.2026 |
| A-03 | GM-208 | Härtung wirkt ab Aktivierung (je Merkmal per Filter `bootspruefung_hardening` abschaltbar, INV-C-142) | alle Merkmale aus, bis der Admin im Setup-Assistenten oder Tab speichert; danach je Merkmal `auto` | Beschluss **E-260**, E-240 (Claude im Auftrag) | 16.11.2026 |
| A-04 | GM-207 | `Permissions-Policy` website-weit mit `microphone=(self)` für das Funktraining (INV-C-141) | `Permissions-Policy` nur auf Course-Captain-Antworten mit `camera=(), microphone=(), geolocation=(), payment=()` | Beschluss **E-309** (Claude im Auftrag); Mikrofon-Freigabe war fachlich (`exclude_domain_specific`) | 16.11.2026 |
| A-05 | – (§1.21) | Härtung umfasst auch `/wp/v2/users` für Gäste, Autorenarchive, Benutzer-Sitemap, oEmbed-/Feed-Autor, Anwendungspasswörter nur Redaktion (INV-C-138) | nicht im R1-Härtungsmodul; R4-Pool | Beschluss **E-261**, **E-308** (Claude im Auftrag) | 16.11.2026 |
| A-06 | GM-209 | Lernender kann `completed` generell zurücksetzen (LB-Code VS:246-247 laut R0-08 §5.1) | nur `manual_confirm` durch den Lernenden; nachweisbasierte Abschlüsse nie; Reset durch die Kursverwaltung mit Audit | Beschluss **E-70** (Claude im Auftrag) | 16.11.2026 |

### 5.2 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-05-1 | Genügt eine „Von Claude entschieden“-Festlegung (z. B. E-129, A-02) als schriftliche Freigabe nach E-55, oder muss der Auftraggeber den Eintrag in §5.1 gesondert abzeichnen? | E-55 verlangt „schriftlich durch den Auftraggeber“; der Auftrag an Claude deckt das nicht ausdrücklich ab | Auftraggeber → **Auftraggeber-Aktion (Blocker für Gate A)**: schriftliche Freigabe der Abweichungsliste §5.1 (A-01…A-06) im Freigabefeld des Gate-Protokolls (E-55, Gate A-7), AG-08 |
| OP-05-2 | Bleiben Bereichs-Scopes (`question_bank`, `exam`, `lessons` …) neben `module_id` bestehen? | E-27 sagt „Zugang je Modul statt je Kurs mit Scopes“; ob Scopes ganz entfallen, legen R0-06/R0-09 fest. Die Fixture-Grants in §2.8 sind bis dahin unverändert | Architektur (R0-06/R0-09) → **erledigt durch E-286** (Scopes bleiben innerhalb eines Moduls; Fixture-Grants §2.8 unverändert) |
| OP-05-3 | GM-Katalog für das Härtungsmodul R1-45 (Login-/Reset-Limit, bekanntes Gerät, XML-RPC, Security-Header) aus SE:279–631 | Quellabschnitt in R0-05 nie zeilengenau ausgewertet (früher ausgeschlossen); Nachtrag nötig | R1-45 / R1-18 → umgesetzt: **GM-201…GM-208** in §1.23 aus den LB-Belegen der Inventur C (E-10, E-261, E-307…E-309); Abweichungen A-03…A-05 |
| OP-05-4 | Kursfortschritt bei Zugang nur zu einzelnen Modulen (Nenner von `completion_ratio`, GM-076-Analogie) | weder E-27 noch R0-08 legen den Nenner für Modul-Grants fest; GM-200 prüft deshalb nur den Zugang | Architektur (R0-08 §5.1) → **erledigt durch E-286** (Nenner = Pflicht-Activities der zugänglichen Module; in GM-200 ergänzt) |
| OP-05-5 | Benachrichtigung bei Rückgabe/Bewertung einer Aufgabe (R1-46 „Benachrichtigung“) | Mailkatalog R0-27 und E-136 nennen die Fälle nicht; GM-195 prüft deshalb keine Mail | R0-27 / R1-21 → **erledigt durch E-290** (N-14…N-16 in R0-27, geprüft durch NTF-035…NTF-037) |

---

## 6. Abnahmeverknüpfung (R0-PAR-003, LB-AUDIT-006)

| PAR | GM | Rechte-Tests | Performance | Migration | R1-Akzeptanz |
|---|---|---|---|---|---|
| PAR-01 | GM-001…006 | GM-002, GM-003 | GM-006 | – | R1-04, R1-16, R1-26 |
| PAR-02 | GM-007…016 | GM-015 | – | GM-113 | R1-07, R1-14 |
| PAR-03 | GM-017…026 | GM-024, GM-025 | – | – | R1-06, R1-09 |
| PAR-04 | GM-027…054 | GM-042, GM-049 | GM-045 | GM-052 | R1-08, R1-09, R1-20 |
| PAR-05 | GM-055…064 | GM-058 | – | GM-064 | R1-11 |
| PAR-06 | GM-065…074 | GM-071, GM-072 | GM-073 | GM-068 | R1-11, R1-12 |
| PAR-07 | GM-075…080 | GM-079 | – | GM-080 | R1-10 |
| PAR-08 (+Gruppen, Gate) | GM-081…117 | GM-097, GM-107…109 | GM-096, GM-110 | GM-113 | R1-13, R1-14, R1-35 |
| PAR-09 | GM-118…136 | GM-122, GM-128 | GM-125, GM-136 | GM-120, GM-121 | R1-15, R1-41 |
| PAR-10 | GM-137…144 | GM-138 | GM-142 | – | R1-05, R1-26, R1-36 |
| PAR-11 | GM-145…162 | GM-151…154 | GM-148 | GM-149 | R1-03, R1-17, R1-28, R1-38 |
| PAR-12 | GM-163…171, GM-178…186 | GM-153, GM-181 | – | GM-163…171 | R1-02, R1-23, R1-31, R1-40 |
| PAR-13 · Privacy (BS-19) | GM-172…177 | GM-173, GM-174 | – | – | R1-17 |
| PAR-14 · SEO/Consent (BS-23) | GM-012, GM-013 | – | – | – | R1-34, R1-37 |
| PAR-15 · Kurs-FAQ/Tipps (BS-24) | GM-189, GM-190 | – | – | – | R1-14, R1-28 |
| Querschnitt | GM-187, GM-188 | – | GM-187 | – | R1-22, R1-42 |
| Neue Funktionen (E-20, E-22, E-25a, E-26, E-27, E-69, E-168; ohne LB-Quelle) | GM-191…GM-200 | GM-195, GM-196, GM-200 | – | – | R1-06, R1-09, R1-13, R1-46 |
| PAR-16 · Website-Härtung (BS-27, E-10) | GM-201…GM-208 | GM-202, GM-204 | – | – | R1-45 |

LB-Bezug: LB-02 → GM-081…083; LB-03 → GM-094…106; LB-04 → GM-107…116; LB-06 → GM-012/013; LB-07/LB-13 → GM-118…136; LB-08 → Gesamtkatalog; LB-09 → GM-120/123/132; LB-10 → GM-017…023; LB-11 (Kern) → GM-088…090; LB-14 → GM-027…034, GM-052. LB-05 (Härtung, E-10) → GM-201…208 (§1.23); LB-01/12/15 → nicht R1 (Abschnitt 1.21).

## Nachweis

- Quellcommit: `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (laut Inventur A/E). Das Referenzverzeichnis habe ich nur gelesen.
- Zeilenbelege: Labels mit Zeilennummer per Skript aus allen `tests/integration/*.php` ausgelesen (Zeile des `$check(`-Aufrufs bzw. der `echo`-Abschnittsüberschrift). E2E-Schritte per `grep -n "step(\|assert"`. Vollständig gelesen: LR (400 Z.), ST, GD; abschnittsweise: CF 1–135 und 166–500, MS 100–240 und 400–480, VG 148–168, SM 97–139, AD 896–910, MA 612–632, MS 1010–1014.
- Codebelege für Lücken: `grep -rn "wrongQuestionIds\|starts_per_hour\|EXAM_START_RATE\|EXAM_ANSWER_LOCKED\|shuffle\|random_int" bootspruefung-learning/{src,tests}`. In `tests/` gibt es keinen Treffer für das Start-Limit und den Answer-Lock; `wrong_question_ids` kommt nur in MA:617/:627 vor.
- CI-Reihenfolge und Woo-Abhängigkeit: `.github/workflows/ci.yml:172-341` (CO und MS laufen vor `woocommerce-setup.php` Z. 248, also ohne Woo; VG/AC/EX laufen mit Woo).
- In Course Captain habe ich nur diese Datei angelegt. Es gibt keinen Commit.
