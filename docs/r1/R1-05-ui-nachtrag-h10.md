# R1-05 · UI-Nachtrag Gruppe h10 (E-342: Autoren-Oberflächen für die ★-Aufgaben)

**Stand:** 23.11.2026 · **Basis:** `claude/blissful-curie-tuuf7q` (b2abae6) · **Test-DB:** `ccap_test_h10`
**Auftrag:** Beschluss E-342 (R1-29 W-12): die für AU-T04/T05/T06/T07/T08/T10/T11/T13 fehlenden Autoren-Oberflächen nachbauen, nach R0-30 (Menübaum, Regeln M-1…M-7), R0-11 (A-04, A-07, A-08, A-09, A-13, Flow 1), R0-08 §3/§4 (Fragetypen, Prüfungsregeln, E-331), R0-10/R0-38 (Import), R0-22 (VAL-140/141, E-131), R0-15 (A11Y-022, A11Y-110, Autorenwerkzeug 11.8), R0-26/`docs/r1/R1-29/skript-autor.md`. Nicht angefasst: `tools/qa/e2e/` (Gruppe h5), Lernbereich (`src/Learner/*`).
**Clean Room (E-01/E-327):** nur `docs/r0`, `docs/r1`, Konzept und bestehender Code; kein LearnBoating-Quelltext.

## 1. Oberflächen

Alle Seiten sind WordPress-Admin-Seiten unter „Course Captain“ (R0-30 §1.2), arbeiten **ohne JavaScript** (jede Aktion ist ein `admin-post`-Formular, mehrteilige Eingaben werden in Schritten bzw. mit Zahlen-/Textfeldern erfasst), prüfen die Cap beim Registrieren und beim Rendern (M-2), tragen je Operation und Objekt einen eigenen Nonce, schreiben über die vorhandenen Dienste (Policy M-xx, Zustandsautomaten) und protokollieren Änderungen im Audit-Log. Rückmeldungen sind Text in `role="status"` bzw. `role="alert"`, Fehler verlinken auf das Feld; Eingaben bleiben nach Fehlern zehn Minuten erhalten (INV-D-025). Jedes Feld hat ein sichtbares Label, Gruppen sind `fieldset`/`legend`, Tabellen haben `caption`/`scope`.

| Oberfläche | Ort / Cap | Inhalt | Aufgabe |
|---|---|---|---|
| **Fragenbank** (A-07) | `admin.php?page=course-captain-questions`, `ccap_edit_courses` (#1), Objekt M-07 | Liste mit Filtern (Modul, Typ, Status, Text) und Kennzeichen „unveröffentlichte Änderungen“; „Frage hinzufügen“ in zwei Schritten (Typ wählen → nur dessen Felder); Editor für **alle neun neutralen Typen** (Einfach-/Mehrfachauswahl inkl. Mischen und Teilpunkte, Wahr/Falsch, Freitext mit Musterantwort, Karteikarte, Zahl mit Toleranz und Einheit, Zuordnung mit Distraktoren, Reihenfolge, Lückentext mit Groß-/Kleinschreibung); Rückmeldung richtig/falsch; **Bild mit Alt-Text-Regel** (Alt-Text, „nur Dekoration“, „nur mit Bild lösbar“); Modul-Pool (hinzufügen/entfernen, Position); Lernenden-Vorschau ohne Lösungsdaten; Versionsliste; Archivieren mit Bestätigung | AU-T04, AU-T05, AU-T10 |
| **Quizze & Prüfungen › Prüfungen** (A-08) | `admin.php?page=course-captain-exams&area=exams`, Seite `ccap_menu_exams` ⇐ #1 ∨ #15, Bereich #1 | Prüfungen je Kurs; „Prüfungsentwurf anlegen“; ein Editorformular: Name, **Bestehensgrenze in Prozent** (gespeichert als gekürzter Bruch), **Fragen je Modul** (Anzahl, Punkte, Poolgröße und „Fragenpool zu klein (n von m)“), **Teilgrenzen über Modulgruppen (E-331)**, Zeitlimit und Versuche (Vorgabe der Website / keins bzw. unbegrenzt / Wert), Reihenfolge der Fragen, Sichtbarkeit nach der Abgabe; Liste „Vor dem Veröffentlichen“; Veröffentlichen; veröffentlichte Regeln nur lesend + „Neue Version anlegen“ (Kopie als Entwurf); Tabelle „Wirksame Einstellungen“ mit Quelle | AU-T07 |
| **Quizze & Prüfungen › Bewertung** | `…&area=grading`, `ccap_grade_attempts` (#15) oder #1; Objekt M-10a | Warteschlange der eigenen offenen Abgaben (älteste zuerst); Detail mit Text, Dateien, Bewertungshinweis, früheren Bewertungen; ein Formular mit „Bewertung speichern“ und „Zur Überarbeitung zurückgeben“ (Kommentar Pflicht), Neubewertung mit Pflichtbegründung; Idempotenzschlüssel je Formular; Meldung nennt den **neuen Status** und was die lernende Person sieht (S-02) | AU-T13 |
| **Inhalte importieren › Pakete** (A-13) | `admin.php?page=course-captain-import`, Seite `ccap_menu_import` ⇐ #6 ∨ #7 ∨ #8, Bereich #6 (M-21) | Upload (ZIP, Größenhinweis, Teilpaket) → Formatprüfung V1–V6 und **Probelauf** sofort; Importseite mit Plan je Inhaltsart und Aktion, Objektfehlern, häufigsten Problemen, **Fehlerbericht als CSV/JSON** (REST-Export mit Cookie-Nonce); „Freigeben und importieren“ (ohne `ccap_publish_courses` nur Freigabe), „Verwerfen“ mit Bestätigung, **Rücksprung** mit Pflichtbegründung (neuer Import mit eigenem Probelauf); für importierte Pakete Einstieg in „Ungeklärte Rechte freigeben“ (vorhandenes zweistufiges Formular) | AU-T06 |
| **Lernende › Zugänge** (A-09) | `admin.php?page=course-captain-learners`, `ccap_view_learner_details` (#13); Vergabe M-14, Beenden M-13 | Kurswahl; Zugangsliste (maskierte Adresse E-81, Umfang „ganzer Kurs“/„Modul X“, Status, Gültigkeit, Quelle, Fortschritt in %); „Zugang geben“ für den ganzen Kurs **oder ausgewählte Module (je Modul ein Grant, E-27)**, Gültigkeit, Pflichtbegründung, idempotent je Formular; „Zugang beenden“ mit Begründung und Bestätigung; Fortschrittsansicht je lernender Person (Modul → Lektion, Status, Abschluss) | AU-T11 |
| **Vorschau als Lernende** | versteckte Seite `admin.php?page=course-captain-preview&course=<id>` (R0-30 §1.3), `edit_post` am Kurs; Links: Zeilenaktion in der Kursliste, Meta-Box „Revision veröffentlichen“, Veröffentlichen-Box | aktueller Entwurf: Beschreibung, Module/Lektionen in Reihenfolge mit Text (`the_content`) und Aktivitätsart, Fragen je Modul als deaktivierte Formulare ohne Lösungsdaten (A11Y-022-Form), Prüfungen mit Kurzbeschreibung; **schreibt nichts** (kein Fortschritt, keine Versuche, keine Tokens) | AU-T08 |
| **Alt-Text-Regel B-05** | `CourseValidator` Gruppe `media` | **VAL-140** (Blocker): Bilder ohne Alt-Text in Kursbeschreibung, Lektionen und Fragetexten sowie Kursmedien (`ccap_media.alt_text IS NULL`) von Kurs, Modul, Lektion, Fragenrevision; `alt=""` zählt nur mit ausdrücklicher Kennzeichnung als dekorativ (`data-ccap-decorative="1"`, `role="presentation"`/`"none"`, `aria-hidden="true"`); **VAL-141** (Blocker): Frage mit `image_required`, deren Bild nicht in Worten beschrieben ist („dekorativ“ reicht nicht). Der Frageneditor verhindert das Veröffentlichen einer Frage ohne Alt-Text (Entwurf bleibt, Meldung mit Sprungmarke) | AU-T09 |

Störfall UX-012: Frage- und Prüfungsformular sichern ungespeicherte Texteingaben zusätzlich im Browser (`localStorage`, progressive Verbesserung) und bieten sie nach Sitzungs- oder Netzverlust wieder an; ohne JavaScript bleibt das Formular voll nutzbar.

Dienst-Ergänzungen: `ExamRules::update_draft()` (Name und Bestehensregel eines Entwurfs, gleiche Prüfung wie `create()`), `ExamRules::remove_blueprint()`; neue Audit-Aktionen `question_saved`, `question_published`, `question_archived`, `question_pool_changed`, `exam_rule_saved`, `exam_rule_published`, `grant.revoked_manually` (mit Beschriftung im Protokoll-Viewer, GM-139).

## 2. Tests

`tests/integration/AuthoringScreensTest.php` (`@group R1-05`, `@group E-342`), 11 Tests, jede Oberfläche über ihr testbares `process()`/`html()`:

| Test | Prüft |
|---|---|
| `test_question_editor_all_types_rights_and_nonce` | Abonnent → `ccap_forbidden` und Seite ohne Inhalt; falscher Nonce und fremde Nonce-Gruppe → `ccap_invalid_nonce`; alle neun Typen speichern + veröffentlichen, Typvalidierung leer, im Pool; Dezimalkomma; Audit; Liste/Filter; Zwei-Schritt-Formular zeigt nur Felder des Typs |
| `test_question_validation_and_error_guidance` | unvollständige Frage → Entwurf, Meldung „Markiere genau eine Antwort als richtig.“ als `role="alert"` mit erhaltenem Text; unbekannter Typ legt nichts an; Typwechsel einer bestehenden Frage abgewiesen |
| `test_published_question_edit_creates_revision` | Hinweis „neue Version, laufende Versuche behalten …“ und Art der Änderung; Speichern → neue Entwurfsrevision, alte unverändert; Veröffentlichen → alte `deprecated`; Archivieren nur mit Bestätigung, Audit |
| `test_question_image_alt_text_rule` | Bild ohne Alt-Text → `ccap_alt_missing`, Medium `alt_text NULL`, VAL-140 blockiert den Kurs; mit Alt-Text veröffentlicht, VAL-140 weg; dekorativ zulässig; `image_required` + dekorativ → abgewiesen, VAL-141 |
| `test_lesson_image_alt_rule` | VAL-140 je Lektion mit Anzahl; Zählung fehlend/leer/dekorativ/beschrieben |
| `test_exam_editor_save_publish_and_new_version` | Rechte; ungültige Grenze; Fehler in Teil 1 → nichts gespeichert, Meldung auf der Seite; Speichern: 60 % = 3/5, Modulgruppe als `sections`, 45 min, 3 Versuche, Mischen, Feedback; Veröffentlichen bei leerem Pool → „pool too small (0 of 2“; nach Fragen veröffentlicht, danach `ccap_rule_frozen`; neue Version kopiert Regel, Blueprint, Zeit |
| `test_grading_area` | Bereiche je Rolle (Trainer nur „Bewertung“, M-2), Meta-Cap; Warteschlange; ungültige/zu hohe Punkte; keine Selbstbewertung; falscher Nonce; bewerten → `graded` + Statusmeldung; zurückgeben ohne Kommentar → Meldung, mit Kommentar → `returned`; Warteschlange leer |
| `test_import_screen` | Rechte; fehlende/zu große/keine ZIP-Datei; Upload → Probelauf ohne Inhalte, Seite mit Plan und CSV-Link; Freigeben und importieren → `published`, Audit; Verwerfen nur mit Bestätigung; Rücksprung ohne/mit Begründung → neuer Import im Probelauf; „Jetzt importieren“ ohne Freigabe → `ccap_import_state` |
| `test_learners_screen` | Rechte; unbekanntes Konto, fehlende Begründung, keine Module; zwei Modul-Grants (E-27), idempotent; Liste mit maskierter Adresse und Fortschritt; Detail; Beenden nur mit Bestätigung → `revoked`, Audit; falscher Nonce |
| `test_preview_as_learner` | Entwurf mit Modulen, Lektionen, Frage als deaktiviertes Formular; keine Lösungsdaten; keine Fortschrittszeile; fremde Person ohne Inhalt; Zeilenaktion |
| `test_ui_helpers` | Prozent ↔ Bruch (60 → 3/5, „66,5 %“ → 133/200, ungültige Werte), Maskierung |

## 3. Dateien

Neu: `src/Admin/{AdminUi,QuestionForm,QuestionsScreen,ExamsScreen,GradingArea,ImportScreen,LearnersScreen,PreviewScreen}.php`, `tests/integration/AuthoringScreensTest.php`, dieses Dokument.
Geändert: `src/Publish/CourseValidator.php` (VAL-140/141), `src/Exam/ExamRules.php` (`update_draft`, `remove_blueprint`), `src/Admin/EditorScreens.php` (Vorschau-Link), `src/Kernel/Plugin.php` (Registrierung), `src/Ops/AuditView.php` (Audit-Beschriftungen), `languages/course-captain.pot` (make-pot mit `--skip-block-json`, die sechs `block.json`-Einträge aus der alten POT angehängt), `languages/course-captain-de_DE.po/.mo` (395 neue Texte, du-Form, „“).
Keine neuen REST-Routen oder Hooks (API-Snapshots unverändert).

## 4. Kommandos und Ergebnisse (23.11.2026)

| Kommando | Ergebnis |
|---|---|
| `php8.5 vendor/bin/phpcs -q` | 0 Befunde |
| `php8.5 vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | 0 Fehler |
| `php8.5 vendor/bin/phpunit -c phpunit.xml.dist` | 130 Tests, 2 übersprungen, OK |
| `WP_TESTS_DB_NAME=ccap_test_h10 php8.5 vendor/bin/phpunit -c phpunit-integration.xml.dist` | 482 Tests, 34 807 Assertions, 31 übersprungen (pcntl), OK |
| `WP_TESTS_DB_NAME=ccap_test_h10 php8.5 vendor/bin/phpunit -c phpunit-integration-multisite.xml.dist` | 495 Tests, 21 280 Assertions, 31 übersprungen, OK |
| `php8.5 tests/guards/free-guard.php` | 213 Dateien, 0 Befunde |
| `php8.5 tests/guards/textdomain-lint.php` | 206 Dateien, 0 Probleme |
| `php8.5 tools/qa/gm-coverage.php` | 210 IDs, 207 getestet, 3 Ausnahmen (unverändert), 0 fehlend |
| `wp i18n make-pot … --skip-block-json` + `wp i18n make-mo` | POT aktualisiert (keine neuen Übersetzer-Kommentar-Warnungen), de_DE vollständig (0 fehlende Einträge) |

## 5. Freigabe nötig / offene Punkte

1. **Usability-Kit, AU-T09-Blocker 1:** `tools/usability/lib/Builder.php:399` setzt beim absichtlich fehlenden Alt-Text `alt="" role="presentation"`; nach HTML-Semantik (und `Kit.php:199`) ist das Bild damit **als dekorativ gekennzeichnet**, VAL-140 meldet es zu Recht nicht. Damit der eingebaute Fehler erkannt wird, muss das Kit `role="presentation"` weglassen (und `check-rk` das Bild als fehlend zählen). Nicht geändert, weil Kit-Inhalt (Entscheidung der Kit-Verantwortlichen).
2. **Kennzeichnung „dekorativ“:** Es gibt kein Feld `decorative` in `ccap_media`; umgesetzt ist `alt_text = ''` = dekorativ, `NULL` = fehlt (R0-22 Quick-Fix „setzt `alt=""` ausdrücklich“), im HTML `data-ccap-decorative`/`role`/`aria-hidden`. Folge: Bilder aus dem Block-Editor ohne Alt-Text (`alt=""` ohne Kennzeichnung) sind Blocker (E-131); der Block-Editor bietet keinen Schalter „dekorativ“. Bitte bestätigen.
3. **Fragebild im Fragetext:** Die Lernansichten zeigen nur den Fragetext; das Bild wird daher als `<figure class="ccap-question-image">` vor den Text geschrieben und zusätzlich als Medium der Revision (`ccap_content_media`, Rolle `image`) verknüpft. Ein eigenes Bildfeld im Lern-Payload wäre eine Vertragsänderung (R0-19).
4. **Nicht gebaut (außerhalb der ★-Aufgaben):** feste Prüfungsbögen (Varianten), Sammelbearbeitung und Tags in der Fragenbank, Erklärungsfeld (Pro, E-20), Bewertung offener Prüfungsantworten (R2-02), Import per Link/Ordner, Bereiche „Rechte & Freigabe“ und „Kurs-Export“ als eigene Seiten, Zugänge aussetzen/verlängern und Zugangsregeln (A-09), Fortschritt zurücksetzen aus der Oberfläche, Drag-and-drop. Der Typ einer bestehenden Frage ist im Editor nicht änderbar (neue Frage anlegen).
5. **UX-012 (Autosave):** Schutz nur im Browser (`localStorage`, Texteingaben); ein serverseitiger Entwurfsspeicher ist nicht spezifiziert.
