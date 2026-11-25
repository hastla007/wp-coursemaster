# R1-29 · Bereitschaft der Referenzaufgaben (Neubewertung 23.11.2026, UI-F9; vorher 17.11.2026, Commit 6f6f05f)

**Zweck:** R0-26 §4.2 Schritt 2 verlangt eine abgenommene Testumgebung, bevor Personen eingeladen werden. Diese Tabelle zeigt je Referenzaufgabe, ob die **Oberfläche** sie heute zulässt. Eine Aufgabe, die nur über REST, WP-CLI oder PHP-Dienste geht, ist für einen Nutzertest **nicht bereit**: Die Person würde an der fehlenden Oberfläche scheitern, und der Befund wäre bekannt, bevor die Sitzung beginnt.

**Grundlage der Neubewertung (UI-F9, Stand Branch `r3-f` auf 0081aef):** Code-Lesung der seit 17.11. gebauten Oberflächen (E-342/E-348/E-349: `src/Admin/QuestionsScreen.php`, `ExamsScreen.php`, `ImportScreen.php`, `PreviewScreen.php`, `LearnersScreen.php`, `GradingArea.php`, `src/Learner/TrainerView.php`, `AssignmentView.php`, `GroupsView.php`, `Invitation.php`, `LessonView.php`, `ExamRoom.php`, `src/Access/SelfEnrollment.php`), die zugehörigen Integrationstests und Browserläufe in diesem Container (§3a): `tools/qa/e2e/live-smoke.sh` (Flow 2, Prüfungsraum), `tools/qa/e2e/live-flows.sh` (Flows 1–3, mobil, A11Y) und `tools/qa/e2e/settings-a11y.sh` (Einstellungen, Assistent, SET-CC-21). Die Usability-Site selbst (`tools/usability/setup-site.sh`, §2.1/§2.2 von `R1-29-vorbereitung.md`) wurde **nicht** neu aufgebaut: Sie braucht eine eigene Datenbank `ccap_usab…`, diese Sitzung darf nur ihre Testdatenbank benutzen. Die Werkzeuge laufen auf PHP 8.5 (`setup-site.sh` lädt `tools/release/php-min.sh`), `seeder.php` und `lib/*.php` sind syntaktisch sauber auf PHP 8.5; ein Seed-Lauf gegen den Release-Kandidaten bleibt vor Runde 1 Pflicht (Preflight).

Legende: **bereit** = per Oberfläche lösbar · **teilweise** = lösbar mit bekannter Einschränkung (Spalte „Einschränkung“) · **blockiert** = keine Oberfläche; nicht in Runde 1 aufnehmen. Spalte „17.11.“ = Stand der ersten Bewertung.

## 1. Matrix je Aufgabe

### Autor (Flow 1)

| ID | Stand | 17.11. | Beleg / Einschränkung |
|---|---|---|---|
| AU-T01 | teilweise | teilweise | Kurs im Block-Editor anlegbar (Flow 1 „menu offers Add course“); **weiterhin kein Feld „Lernziele“** – Lernziele nur im Beschreibungstext |
| AU-T02 | teilweise | teilweise | Module über die Metabox „Modules“ (Auswahl + Positionszahlen, `EditorScreens::render_structure`), Lektionen über „Module“ in der Metabox „Activity“; kein Ziehen, kein Strukturbaum (A11Y-008 nur über Zahlenfelder) |
| AU-T03 | teilweise | teilweise | Video/Datei/Untertitel weiterhin als **JSON-Einstellungen** in der Metabox „Activity“ (`ccap_activity_settings`); Medien-UID muss bekannt sein. Für Personen ohne Technikwissen praktisch nicht lösbar – Aufgabe nur mit vorbereiteter Medien-UID auf der Karte |
| AU-T04 | **bereit** | blockiert | Fragenbank „Question bank“ mit Fragen-Editor (`QuestionsScreen`, `QuestionForm`, alle Typen außer Pro), Speichern als Entwurf/Veröffentlichen; `AuthoringScreensTest` |
| AU-T05 | **bereit** | blockiert | wie AU-T04; Bild ohne Alternativtext wird nicht veröffentlicht („Saved as draft, not published: the image needs an alternative text“, `ccap_alt_missing`) |
| AU-T06 | **bereit** | blockiert | Seite „Import content“ (`ImportScreen`): ZIP hochladen → Formatprüfung V1–V6 und Probelauf → Freigabe → Lauf, Rückgängig mit Grund. Import per Link/Ordner fehlt in der Oberfläche (UI-F8), für die Aufgabe nicht nötig |
| AU-T07 ★ | **bereit** | blockiert | Seite „Quizzes & exams“ (`ExamsScreen`): Fragen je Modul (Blueprint mit lieferbarem Pool), Teilgrenzen (`parts`), Zeit, Veröffentlichen; Flow 1 legt „20 aus 50“ noch über `live-setup.php bank` an (Zeitmessung getrennt) |
| AU-T08 | **bereit** | teilweise | Kasten „Settings for this course“ (Zugang, Selbstanmeldung); **Vorschau „als Lernende“** (`PreviewScreen`, Zeilenaktion); Selbstanmeldung hat jetzt das Lernenden-Gegenstück (LE-T01) |
| AU-T09 ★ | **bereit** | teilweise | Prüfkasten „Publish revision“ mit Sprunglinks (Flow 1 „publishing too early is refused …“); **Alt-Text-Regel VAL-140/141** im Validator (B-05 behoben); Blueprint-Fehler über den Prüfungs-Editor erzeugbar |
| AU-T10 | **bereit** | blockiert | Fragen-Editor: veröffentlichte Frage ändern erzeugt eine neue Revision; Hinweis „New exam attempts use this version; running attempts keep theirs.“ |
| AU-T11 | **bereit** | blockiert | Seite „Learners“ (`LearnersScreen`): manueller Zugang (ganzer Kurs oder Module, Grund Pflicht), Fortschritt je Person, Zugang beenden mit Bestätigung, CSV |
| AU-T12 | teilweise | teilweise | Aktivitätstyp „assignment“ wählbar, Einstellungen (Dateitypen, Größe, Bewertung) aber weiterhin als **JSON** in der Metabox „Activity“; Website-Grenzen im Tab „Learning“ (SET-CC-09) |
| AU-T13 ★ | **bereit** | blockiert | Bewertungsbereich (`GradingArea`): Warteschlange, Detail, Bewertung und Rückgabe; Flow 3 bewertet eine Abgabe als Gruppenverwaltung |
| UX-012 Störfall | teilweise | teilweise | WordPress-Autosave im Block-Editor; Fragen-Editor und Metabox-JSON werden nicht zwischengespeichert (Warnung bei ungespeicherten Änderungen nur auf den Einstellungsseiten) |

### Lernende (Flow 2)

| ID | Stand | 17.11. | Beleg / Einschränkung |
|---|---|---|---|
| LE-T01 ★ | **bereit** | blockiert | Selbstanmeldung im Kursraum („Enroll“, `LearningArea` → `SelfEnrollment::enroll`, idempotent bei Doppelklick, „Einschreibung geschlossen“ E-108); `LearnerUiTest`, `SettingsFlowsTest` (SET-CC-P2). B-02 behoben. Voraussetzung: Selbstanmeldung im Tab „Enrollment“ bzw. im Kurs-Kasten an |
| LE-T02 | bereit | bereit | Lektion über die Lernbereichsroute, Abschluss ohne JS; Rauchtest §3a „lesson L-A-01 completed“ |
| LE-T03 | **bereit** | blockiert | Video im Lernbereich (`LessonView::player`: natives `<video>` mit `<track kind="subtitles">`, Abschrift darunter SET-CC-18; extern „Klick zum Laden“ E-45a, `external-media.js`, `ExternalMediaViewTest`). Abhängig von AU-T03 nur beim Anlegen |
| LE-T04 ★, LE-T05, LE-T07 | **bereit** | blockiert | Smarttrainer, Fehlertraining („Prüfungsfehler üben“) und fällige Wiederholungen im Lernbereich (`TrainerView`, Routen `ueben`, `fehler`, `faellig`; UI-F3) |
| LE-T06 ★ | **bereit** | teilweise | Prüfungsraum ohne JS: alle Typen beantwortbar inkl. Freitext (Musterantwort + Selbsteinschätzung), Reihenfolge, Lückentext, Zuordnung, Zahl (`ExamRoom::SUPPORTED`); Rauchtest: Abbruchdialog GM-051, Offline-Puffer GM-050. B-03 behoben (Entscheidung D2, 24.11.2026): Karteikarten werden nie in Prüfungen gezogen – Blueprint-Ziehung (`ExamSessions::draw` über `QuestionRepository::pool_revisions(…, true)`), feste Bögen lehnen Karteikarten ab (`ExamRules::create_variant`/`set_variant_questions`/`variant_blockers`, Start `variant_flashcard`), Modulpool-Quizze wie bisher (`QuizPool::eligible`); Validator VAL-099 warnt bei Pools nur aus Karteikarten bzw. zu wenigen wertbaren Fragen; `CardsRightsTest` (SET-CC-karten-rechte). Rauchtest §3 mit dem Release-Kandidaten wiederholen (Prüfung 1 des Seeds zieht dann keine Karteikarte mehr) |
| LE-T08 ★ | bereit | bereit | Ergebnis, Nachweisliste, Nachweisansicht, öffentliche Verifikation (Rauchtest §3a „certificate issued …“, „guest verifies …“) |
| LE-T09 | teilweise | blockiert | Datenauskunft per Selbstbedienung (`SelfService`, Kurzcode `[ccap_privacy_request]`, REST R-48 `/me/data-exports`), **nicht im Lernbereich verlinkt**: Betreiber muss eine Seite mit dem Kurzcode anlegen; Drehbuch nennt diese Seite |
| LE-T10 ★ | bereit | bereit | „Continue: <Lektion>“; mobil ohne horizontales Scrollen bei 320/390 px (settings-a11y.js, Lernbereich) und Flow 2 in Telefonemulation (`live-flows.sh`) |
| LE-T11 ★, LE-T12 | **bereit** | blockiert | Abgabe-Oberfläche im Lernbereich (`AssignmentView`: Entwurf, Datei/Text, Einreichen, Rückgabe lesen und überarbeiten); `LearnerUiTest` (`assignment_save`); Flow 3 reicht ein |
| UX-031 | bereit | bereit | Sperrgründe als Text („Available from …“ im Kalender der Website, SET-CC-P2 prüft Wien/UTC+13 über Jahreswechsel) |
| UX-032 | bereit | bereit | Ablaufmeldung und Ergebnis im Prüfungsraum |
| UX-035 | **bereit** | teilweise | wie LE-T06 |
| UX-075 | teilweise | teilweise | Zeitausgleich wirkt (`ccap_accommodation`, `EffectiveSettings`); Anzeige „inkl. Zeitausgleich“ vor dem Start fehlt weiterhin (A11Y-070) |

### Gruppenverwaltung (Flow 3) und Admin (Flow 4)

| ID | Stand | 17.11. | Beleg / Einschränkung |
|---|---|---|---|
| GV-T01 … GV-T03, GV-T05, GV-T06 | **bereit** | blockiert | Besitzer-Oberfläche L-18 im Lernbereich (`GroupsView`, „My groups“): freie Plätze, Einladen per Adresse, Gruppenlink (einmal angezeigt), „nur eingeladene Adresse“ (E-28), Link erneuern, Platz freigeben mit Bestätigung; Wartezeit des Einladungsbudgets (UI-F1) |
| GV-T04, GV-T07 | **bereit** | blockiert | Beitrittslink `?ccap_claim=` wird im Lernbereich ausgewertet (`Invitation`, `LearningArea` Zeile mit `Invitation::token`), Fehlerfälle „voll“/„abgelaufen“ mit neutraler Meldung (`ccap_claim_expired`, `ccap_claim_used`) |
| AD-T01, AD-T02 | bereit | bereit | Setup-Assistent mit Wiedereinstieg; SET-CC-21: alle 8 Schritte und der Fehlerzustand ohne A11Y-Befund (§3a) |
| AD-T03 | bereit | bereit | Bereitschaftsprüfung; für den Dateischutz-Fehler nginx-Staging ohne Regel nötig |
| AD-T04 | bereit | bereit | Einstellungs-Tabs (10), Warnung bei ungespeicherten Änderungen; Fehlerzusammenfassung wird nach dem Speichern wieder fokussiert (SET-CC-21-Befund behoben) |
| AD-T05 | bereit | bereit | Systemstatus › Hintergrundaufgaben mit Retry |
| AD-T06 | bereit | bereit | Admin-Seite „Groups“ und Besitzer-Oberfläche |

### Hilfstechnik und Expertendurchläufe

| ID | Stand | 17.11. | Hinweis |
|---|---|---|---|
| UX-070 … UX-073 (Flow 2 mit AT) | teilweise | teilweise | jetzt fast alle Lernenden-Aufgaben prüfbar; offen bleiben LE-T09 (Kurzcode-Seite) und UX-075 (Anzeige Zeitausgleich) |
| UX-074 (Flow 1 mit AT) | teilweise | teilweise | AU-T01/T02/T04–T11/T13 prüfbar; AU-T03/T12 (JSON) mit Hilfstechnik nicht sinnvoll |
| UX-076/077 Expertendurchläufe | bereit | bereit für vorhandene Seiten | Lernbereich, Prüfungsraum, Nachweise, Setup, Einstellungen (SET-CC-21), Systemstatus, Gruppen, Fragenbank, Prüfungen, Import, Bewertung |
| UX-078 Mails | teilweise | teilweise | Mailtexte je Sprache bearbeitbar (SET-CC-04); Mailprogramm-Test braucht echte Zustellung an ein Testpostfach |
| UX-079 Druckansicht Nachweis | bereit | bereit | |
| UX-080 externe Medien „Klick zum Laden“ | **bereit** | blockiert | `LessonView` + `ExternalMedia`, Browserlauf `external-media.js` |
| UX-081 Upload/Bewertung | **bereit** | blockiert | `AssignmentView` + `GradingArea` |

**Zählung:** 17.11.: 18 Zeilen blockiert. 23.11.: 0 blockiert; teilweise bleiben AU-T01, AU-T02, AU-T03, AU-T12, UX-012, LE-T06/UX-035 (B-03), LE-T09, UX-075, UX-070…074, UX-078.

## 2. Befunde aus der Vorbereitung (vor Runde 1 zu klären)

| Nr. | Befund | Schwere (Vorschlag nach R0-26 §4.4) | Beleg |
|---|---|---|---|
| B-01 | Lektionslinks im Kursraum führen auf 404; Lernende erreichen keine Lektion | S1 (kritische Aufgabe LE-T02/LE-T10 unlösbar) | Rauchtest §3, `LearningArea::course_room()` nutzt `get_permalink()` eines nicht öffentlichen Typs. **Behoben (17.11.2026):** Lernbereichsroute `kurs/<uid>/lektion/<id>` mit `LessonView`; Rauchtest-Nachlauf §3 grün |
| B-02 | Keine Selbstanmeldung (UX-020 ★) | S1 | kein Handler für `free_enrollment` im Lernbereich. **Behoben (UI-F2/E-342, geprüft 23.11.2026):** Knopf im Kursraum, `SelfEnrollment::enroll`, Doppelklick liefert denselben Zugang (`SettingsFlowsTest`) |
| B-03 | Fragen aus einem Modulpool mit **Karteikarten** werden in Blueprint-Prüfungen gezogen und sind dort nicht beantwortbar (RK-1-Abschlussquiz zieht 20 aus 50 inkl. 5 Karteikarten) | zu klären (Produkt): Karteikarten aus Prüfungspools ausschließen oder Drehbuch ändern | Rauchtest §3; im deterministischen Seed enthält Prüfung 1 eine Karteikarte (Position 3) sowie Reihenfolge (13, 15) und Lückentext (20). Stand 23.11.2026: offen. **Behoben 24.11.2026 (Entscheidung D2):** Karteikarten sind von Prüfungen und bewerteten Quizzen immer ausgeschlossen (Blueprint, feste Bögen, Modulpool-Quizze), Validator-Warnung VAL-099; Nachweis `docs/r1/SET-CC-karten-rechte.md` |
| B-04 | Freitext/Reihenfolge/Lückentext im Prüfungsraum nicht beantwortbar; RK-2-Prüfung enthält diese Typen | S2 bei Prüfungen mit diesen Typen | R1-19 A11Y-023, Rauchtest. **Behoben (geprüft 23.11.2026):** `ExamRoom::SUPPORTED` umfasst Freitext (Musterantwort + Selbsteinschätzung), Reihenfolge, Lückentext; nur Karteikarten nicht (B-03) |
| B-05 | Alt-Text-Regel fehlt im Veröffentlichungs-Validator; AU-T09-Blocker 1 wird nicht erkannt | S1 für AU-T09 (UX-009 ★) | R1-19 A11Y-091 offen. **Behoben (E-349, geprüft 23.11.2026):** `CourseValidator` VAL-140/141 (Blocker), Fragen-Editor verweigert Veröffentlichung ohne Alternativtext |
| B-06 | Ohne WordPress-Sprachpaket `de_DE` bleibt der Lernbereich englisch (Testsprache E-169) | Umgebung | Seed-Lauf in diesem Container (kein Download möglich). **Stand 23.11.2026:** Plugin bringt `de_DE` („du“) und `de_DE_formal` („Sie“) vollständig mit (SET-CC-14); für WordPress-eigene Texte (Anmeldung, Admin-Rahmen) bleibt das Sprachpaket nötig – Preflight |
| B-07 | `vendor/bin/wp` lädt `CourseCaptain\` aus `<repo>/src` statt aus dem installierten Plugin; betrifft auch `tools/release/artifact-test.sh` (J-17 prüft dann nicht den ZIP-Code) | Werkzeug | `isolate-autoload.php`, Kontrollzeile im Seed-Lauf; Artefakttest seit 17.11.2026 isoliert und mit Herkunftsprüfung (`tools/qa/wp-cli-autoload-isolation.php`) |

## 3. Rauchtest gegen die Referenz-Site (17.11.2026)

Lokaler Lauf mit `php -S 127.0.0.1:8099` auf `/tmp/ccap-usab-referenz` (Konten mit gesetztem Testpasswort, nur für diesen Test):

| Prüfung | Ergebnis |
|---|---|
| `/kurse/` anonym | 200, drei Kurse mit Link „Open … in the learning area“ |
| `/lernen/` anonym | 302 auf die Anmeldung mit Rücksprung |
| `cc_fx_ux_p01` → „Mein Lernen“ | 200, RK-2 und RK-3 „Access active“, „Continue: Zellen, Zeilen, Spalten“ |
| Kursraum RK-3 | 200, Modul 2 „Available from November 18, 2026“, Lektionen „Locked: not yet released“ (UX-031) |
| Lektionslink aus dem Kursraum | **404** (B-01) |
| `cc_fx_ux_pruefung` → `/lernen/pruefung/1` | 200, 10 Fragen „answered“, 2 „marked for review“; Bild mit Alt-Text; Freitext/Karteikarte „cannot be answered in this view yet“ |
| `cc_fx_ux_abgelaufen` → `/lernen/pruefung/2` | 200, „The time is up. Your answers have been submitted. Not passed …“ |
| `cc_fx_ux_bestanden` → Nachweise | 200, Nachweis „Valid“, Prüf-Link; Prüf-Link anonym 200 „Certificate verification Valid“ |
| `scenario sitzungen-beenden` | danach `/lernen/` → 302 auf die Anmeldung (UX-012-Störfall wirkt) |

**Nachlauf B-01 (17.11.2026, Stand `claude/vigilant-sagan-wzdo1a`, Site neu aufgebaut mit `--worktree`):** `cc_fx_ux_p01` → „Continue: Zellen, Zeilen, Spalten“ → `/lernen/kurs/<uid>/lektion/36` 200 mit „Mark as completed“; alle 15 verlinkten Lektionen in RK-2/RK-3 200, keine `post_type=ccap_lesson`-Links mehr; die 5 Drip-gesperrten Lektionen in RK-3 direkt aufgerufen 403 „Available from November 18, 2026“; Formular-POST → 302 auf `?ccap_status=completed`, danach „Lesson completed.“ und „Status: completed“; Lektion anonym 302 auf die Anmeldung.

Die englischen Texte im Rauchtest folgen aus B-06.

## 3a. Browserläufe zur Neubewertung (23.11.2026, Branch `r3-f`, PHP 8.5.12, WordPress 7.1.4, MariaDB 10.11, Chromium)

| Lauf | Ergebnis |
|---|---|
| `tools/qa/e2e/live-smoke.sh` | OK: Anmeldung, Mein Lernen, Kursraum, Lektion abgeschlossen, Lernfrage, Prüfung starten/beantworten/neu laden/abgeben, Nachweis ausgestellt und gelistet, Gast-Verifikation; Prüfungsraum GM-050 (Offline-Puffer), GM-051 (Abbruchdialog), PRIV-018 |
| `tools/qa/e2e/live-flows.sh` mit axe-core 4.10.2 | OK: Flow 1 (Autor baut den Referenzkurs im Admin, Validator mit Sprunglinks, Revision 1), Flow 2 Desktop und Telefonemulation, Prüfungsraum mobil, Flow 3 (Gruppenverwaltung lädt ein, Person tritt über den Link bei, lernt, reicht Aufgabe ein, wird bewertet, Prüfung 20 aus 50, Nachweis), A11Y Kernseiten (L-04…L-16, Editor-Kästen, Gruppen), Benchmark. Zwei Befunde dieses Laufs behoben: Flow-1-Warten auf das Neuzeichnen der Kästen (UI-F6), axe `target-size` der Kurs-Kästchen auf „Groups“ |
| `tools/qa/e2e/settings-a11y.sh` mit axe-core 4.10.2 | OK 40/40 (10 Tabs + Fehlerzustände, Assistent 8 Schritte + Fehlerzustand, Mailtexte, Sperren, Sicherung, Protokoll-Ansicht, Kurs-Kasten, Lernbereich bei 320/390 px); Befunde und Behebung in `docs/r1/SET-CC-a11y-ablaeufe.md` |

## 4. Empfehlung für den Ablauf (R0-26 §4.2)

Stand 23.11.2026 (UI-F9): Die Oberflächen der ★-Aufgaben UX-007, UX-017, UX-020, UX-023, UX-036, UX-051 und UX-053 sind vorhanden; B-01, B-02, B-04, B-05 sind behoben. Damit ist die Bedingung aus der ersten Bewertung für Runde 1 erfüllt, mit diesen Auflagen:

1. **Vor Runde 1:** Usability-Site mit `tools/usability/setup-site.sh --profil referenz` gegen den Release-Kandidaten neu aufbauen (eigene Datenbank `ccap_usab…`, PHP 8.5) und den Rauchtest §3 wiederholen; B-03 ist entschieden und behoben (D2), Drehbuch RK-1 braucht keine Sonderregel mehr; für LE-T09 eine Seite mit `[ccap_privacy_request]` anlegen und im Drehbuch nennen; WordPress-Sprachpaket `de_DE` installieren (B-06).
2. **In Runde 1 mit Einschränkung aufnehmen** (Befund wird erwartet, Aufgabe trotzdem beobachten): AU-T03 und AU-T12 (JSON-Einstellungen), AU-T01 (kein Lernziel-Feld), AU-T02 (kein Ziehen), UX-075 (Anzeige Zeitausgleich).
3. Vor jeder Runde diese Tabelle gegen den Release-Kandidaten neu prüfen und in `vorlage-abnahmebericht.md` §2 übernehmen.
