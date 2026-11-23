# SET-CC Slice „autor-ui-2“: UI-F8 Teil 2 und Folgeaufgaben aus den Merges

Stand 23.11.2026, Branch `r3-e` (Basis 0081aef). Nachweis je Punkt; die TODO wird vom Koordinator abgehakt (hier nicht angekreuzt).

UI-F8 (TODO-Text): „… Tags und Sammelbearbeitung der Fragenbank, …, Zugänge aussetzen/verlängern, Zugangsregeln, Fortschritt zurücksetzen, …, Schalter „dekorativ“ im Block-Editor.“ Teil 1 (feste Prüfungsbögen, Import per Link/Ordner, „Rechte & Freigabe“, „Kurs-Export“, Typwechsel) liegt in einer anderen Sitzung und ist hier nicht enthalten.

## UI-F8 · Fragenbank: Schlagwörter und Sammelbearbeitung

**Gebaut:**
- Dienst `src/Question/QuestionBulk.php` (keine zweite Revisionslogik, alles über `QuestionRepository`):
  - Schlagwort-Arten nach R0-06 E-10: `topic`, `subtopic`, `competency`, `smart_category` (vom Smarttrainer gelesen), `scope`. Eingabe `art: Bezeichnung`, ohne bekannte Art = Thema; Schlüssel = `sanitize_title`.
  - `change_tags()`: Schlagwörter sind Teil des Inhalts-Hashes → **Revisionsmodell**: Entwurf wird an Ort und Stelle geändert, eine veröffentlichte Revision nie; es entsteht eine neue Revision. Ohne andere offene Änderungen wird sie auf Wunsch sofort veröffentlicht (Schlagwörter ändern nichts, was Lernende sehen); bei offenem Entwurf landet die Änderung im Entwurf, nichts anderes wird veröffentlicht. Rechte- und Gültigkeitsspalten (`image_required`, `rights_*`, `license_reference`, `valid_from/to`) werden übernommen (dafür schreibt `QuestionRepository::insert_revision()` diese Spalten jetzt, wenn sie in den Daten stehen).
  - `apply()`: Sammelaktionen `tag_add`, `tag_remove`, `pool_add`, `pool_move` (aus den anderen bearbeitbaren Modulen entfernen), `deprecate`. Höchstens 200 Fragen je Lauf; jede Frage einzeln geprüft (M-07, M-06 fürs Modul), Fehler stoppen die anderen nicht.
  - „Deprecate“ = **archivieren** (Fragenstatus `archived`, nie hart löschen, INV-C-108). Der Revisionsstatus `deprecated` bleibt den ersetzten Revisionen vorbehalten (R0-06 §5); Pool- und Archivänderungen erzeugen keine Revision.
  - Audit: neu `question_tags_changed` (hinzugefügt/entfernt, Revision, veröffentlicht ja/nein); Pool weiter `question_pool_changed`, Archiv `question_archived` (Kontext `bulk`).
- `src/Admin/QuestionsScreen.php`: Liste mit Spalte „Schlagwörter“, Filter „Schlagwort“ (nur Schlagwörter der neuesten Revisionen), Auswahlkästchen je Zeile (Label „Auswählen: …“), Sammelformular (fieldset/legend, Aktion, Schlagwort, Zielmodul, „sofort veröffentlichen“, Bestätigung fürs Archivieren), Ergebniszeilen mit `_n()`; Filter bleiben nach dem Absenden erhalten. Editor: Feld „Schlagwörter“ (eine pro Zeile).
- **Folgefehler behoben:** Speichern im Frageneditor hat bisher die Schlagwörter der Revision verworfen (`QuestionForm::from_post` kennt keine Tags). Jetzt: Feld vorhanden → Feldinhalt; Feld fehlt → Schlagwörter der neuesten Revision bleiben.

**Tests:** `tests/integration/AutorUi2Test.php::test_tag_parsing`, `::test_editor_tags_and_revisions`, `::test_bulk_edit_and_tag_filter` (neue Revision, alte `deprecated`, Entwurf an Ort und Stelle, offener Entwurf nicht veröffentlicht, Filter, Verschieben, Archiv mit Bestätigung, Rechte, Nonce, Audit, A11y-DOM), `::test_tag_change_keeps_revision_columns`.

## UI-F8 · Zugänge aussetzen, fortsetzen, verlängern

**Gebaut** (`src/Admin/LearnersScreen.php`, „Lernende“ = Einschreibungsbildschirm von E-342):
- Zugangsliste: Link „Zugang von … aussetzen, fortsetzen oder verlängern“ → Detailansicht `&grant=<id>` mit Status/Enddatum und Formularen je Zustand (aktiv: aussetzen, verlängern; ausgesetzt: fortsetzen mit Hinweis, ob die Aussetzung von Hand oder von der Quelle kam). „Zugang beenden“ bleibt in der Liste.
- Ops `suspend`, `resume`, `extend`: Begründung Pflicht; Zugang muss zum Kurs gehören; Recht `MANAGE_GRANT` (operation `change`). Ausführung über `GrantService::transition()` (manuell, setzt/löscht `manual_override`, Outbox, CAS) bzw. `GrantService::extend()` (G-14, nur aktive Zugänge, `valid_until_locked`). Verlängerung nur mit Datum in der Zukunft (sonst `ccap_date_invalid`; Beenden bleibt „Zugang beenden“).
- Audit: neu `grant.suspended_manually`, `grant.resumed_manually` (mit Begründung, betroffene Person); Verlängern schreibt wie bisher `grant.extended` (im Dienst).

**Tests:** `::test_suspend_resume_extend` (Pflichtgrund, fremder Kurs, ohne Recht 404 ohne Existenzhinweis, Zustände, ausgesetzt nicht verlängerbar, Datum, Audit mit Begründung, A11y-DOM).

## UI-F8 · Zugangsregeln je Kurs (R0-09)

**Gebaut:**
- Dienst `src/Access/AccessRules.php`: liest/schreibt die Zeilen von `ccap_access_rule`, die `ContentGate` auswertet: `free` (Kurs oder Modul, optional Zeitfenster UTC), `drip` (eine Regel je Modul, 0–3650 Tage, leer = entfernen), Teilzugang über `TrialLimits::set()` (keine zweite Implementierung), Entfernen einer Regel. Recht: Kurs bearbeiten (M-05) **und** primitive Fähigkeit `ccap_manage_access_rules`; Modul muss zum Kurs gehören. Jede Änderung: Audit `access_rule_changed` (neu), `PolicyService::flush()`, `course_captain_policy_changed`.
- Oberfläche `src/Admin/AccessRulesPanel.php` (eigene admin-post-Aktion `ccap_access_rules_admin`, registriert in `LearnersScreen::register()`), erreichbar über „Zugangsregeln dieses Kurses“ in der Lernendenliste (`&view=rules`): Regeltabelle (gilt für, Regel, Wert, Gültigkeit), Formulare „Freier Zugang“, „Zeitversetzte Module (Drip)“, „Teilzugang (Probe)“ (vier Arten), „Regel entfernen“ mit Bestätigung. Ohne Recht nur lesend.

**Tests:** `::test_access_rules_panel` (Rechte, Eingabeprüfung, fremdes Modul, eine Drip-Regel je Modul, Zeitfenster, Teilzugang, Gate wirkt sofort `drip`, Entfernen mit Bestätigung, Audit, A11y-DOM).

## UI-F8 · Fortschritt zurücksetzen

**Gebaut:** Lernenden-Detail (`&learner=`) mit Formular „Fortschritt zurücksetzen“, nur mit `MANAGE_PROGRESS` (#16, M-12) und nicht für sich selbst:
- Erklärt vorab, was neu beginnt und was bleibt; Begründung Pflicht; **getipptes Bestätigungswort** `ZURÜCKSETZEN` oder `RESET` (Groß-/Kleinschreibung egal, Muster wie das Deinstallations-Wort SET-CC-30).
- Ausführung über den bestehenden Dienst `ProgressReset::reset_course()` (Journal `ccap_reset_journal`, 30 Tage, E-138; Audit `progress_reset` mit Zeilenzahlen) – **nichts wird still gelöscht**.
- R0-23 §3.3 / E-106: Bei einem Reset durch die Kursverwaltung bleiben die Lernstände je Frage unverändert. `reset_course()` hat dafür den neuen Parameter `$questions` (Vorgabe `true` = bisheriges Verhalten für bestehende Aufrufer/GM-078); die Oberfläche setzt ihn nur, wenn „Auch die Lernstufen der Fragen … zurücksetzen“ angekreuzt ist. Der Audit-Kontext nennt `questions`.

**Tests:** `::test_progress_reset` (Pflichtgrund, falsches Wort, ohne Recht, Journal, Audit, Fragenlernstände bleiben bzw. werden mit Häkchen zurückgesetzt, Erfolgsmeldung, A11y-DOM). Bestehender `GoldenMasterLearningTest::test_GM_078_progress_reset` unverändert grün.

## UI-F8 · Schalter „dekorativ“ im Block-Editor (VAL-140/141, E-349)

**Gebaut:** `src/Publish/DecorativeImages.php` + `assets/js/image-decorative.js` (kein Build, ES5 mit `wp.*`-Globals; das Repo hat keinen JS-Build).
- Block-Filter auf `core/image`: Attribut `ccapDecorative` (auch serverseitig über `register_block_type_args`), Umschalter „Dekoratives Bild“ in der Seitenleiste (Panel „Barrierefreiheit“); Einschalten leert den Alternativtext. Texte kommen per `wp_localize_script` aus PHP (also aus den .po-Dateien). Geladen nur im Editor von Kursen, Modulen, Lektionen.
- Gespeichertes Block-Markup bleibt das des Kernblocks (keine Blockvalidierungsfehler); der Marker steht im Blockkommentar.
- Kursprüfung: `CourseValidator::images_without_alt()` ruft zuerst `DecorativeImages::mark()` auf: Bilder in Bildblöcken mit `ccapDecorative: true` und leerem Alt bekommen `data-ccap-decorative="1"` und zählen damit als dekorativ (E-349). Leerer Alt **ohne** Schalter bleibt Blocker (E-131); für Pflichtbilder einer Frage (VAL-141) reicht „dekorativ“ weiterhin nicht.
- Frontend: Filter `render_block_core/image` setzt `alt=""` und `data-ccap-decorative="1"`, solange kein Alternativtext eingetragen ist.

**Tests:** `::test_decorative_images` (mark/zählen, beschriebenes Bild bleibt beschrieben, nur der markierte Block, Render-Filter, Blockattribut, VAL-140 einer Lektion mit/ohne Schalter).

## Lernbereich: Quiz-Lektionen (h5 L-3)

**Gebaut:** `src/Learner/QuizView.php`, eingebunden in `LessonView` (Ersatz für „kann auf dieser Seite noch nicht genutzt werden“).
- Eine Frage nach der anderen in Quiz-Reihenfolge: feste Inline-Fragen oder die Ziehung des Lernenden aus dem Modulpool (`QuizPool::draw()`, UI-F5). Auslieferung über `QuestionPresenter::deliver_inline()` (Gate, Teilzugang, Sperre bei laufender Prüfung, signiertes Token), Bewertung über `AnswerService::answer()` (idempotent, Kontext `lesson`) – keine eigene Bewertungslogik.
- Rückmeldung nach jeder Antwort (PRG, kurzlebiges Transient), Fortschritt „Beantwortet: x von n, richtig: y“, nach der letzten Frage **Ergebnis** „x von n richtig (p %)“. Abschluss über die Abschlussregel des Quiz-Anbieters (GM-010); der Abschlussbereich nennt die Regel (`attempted`, `all_correct_once`, `passed`).
- Pool-Quiz: „Neuen Durchgang mit neuen Fragen starten“ (neuer Seed, neue Ziehung, Zählung beginnt neu; Abschluss bleibt monoton). Offene Fragen: Musterantwort zeigen, dann Selbsteinschätzung.
- `AnswerService::quiz_answers()` (neu, öffentlich): Antworten des aktuellen Durchgangs je Frage; `feed_lesson_quiz()` nutzt dieselbe Abfrage (gleiche Zahl an Abfragen wie vorher).
- Ohne JavaScript, Nonce der Lektion, Fragen als fieldset mit Name aus Nummer, Hinweis und Text, Rückmeldung `role=status`, Fehler `role=alert`.

**Tests:** `::test_quiz_lesson_without_js` (Pool-Quiz mit 2 Fragen: Anzeige, leere Antwort, richtig/falsch, Ergebnis 50 %, Abschluss, neuer Durchgang, ungültiges Token, A11y-DOM).

## Lernbereich: „Prüfungsfehler üben“

**Gebaut:** Modus `pruefungsfehler` (Alias `exam`) in `src/Learner/TrainerView.php` über `SmartTrainer::entry( …, 'exam_mistakes' )`; Karte in der Übungsübersicht und Link im Kursraum (`LearningArea::practice()`). Leerer Zustand unterscheidet „noch keine ausgewertete Prüfung“ und „keine Fehler“; Hinweis, dass Üben die Lernstufe ändert, nicht das Prüfungsergebnis. Antworten laufen wie in den anderen Modi (normales Lern-Token).

**Tests:** `::test_practise_exam_mistakes` (Link, leerer Zustand, falsche Frage der letzten ausgewerteten Prüfung, Antwort, A11y-DOM).

## Validierung und Formular

- **Transkript als Formularfeld:** `src/Admin/LessonMediaFields.php`, im Aktivitäts-Panel der Lektion (`EditorScreens::render_activity()`): Textfeld „Transkripttext“ und URL-Feld „Link zum Transkript (https)“ mit Hilfetext; das JSON-Feld zeigt `transcript`/`transcript_url` nicht mehr. Beim Speichern gewinnen die Felder für Video-Lektionen (leer = entfernt); das Anbieterschema prüft weiter (http-Link wird abgewiesen).
- **VAL-142** in `CourseValidator`: Video ohne Untertitelspur = Hinweis, mit `display.require_captions` = Blocker. Spur = `subtitles: vtt` mit https-`subtitle_url` oder Medium der Lektion mit Rolle `subtitle`. **Lesart:** Eingebettete YouTube/Vimeo-Videos bringen ihre Untertitel vom Dienst mit, die die Prüfung nicht lesen kann → immer Hinweis („dort einschalten“), nie Blocker. Platzhalter-Videos werden nicht geprüft. Audio-Aktivitäten gibt es im Kern nicht.

**Tests:** `::test_transcript_fields_and_captions_rule`.

## Geteilte Dateien / Schnittstellen

- Geändert: `src/Admin/QuestionsScreen.php`, `src/Admin/LearnersScreen.php`, `src/Admin/EditorScreens.php` (Registrierung `DecorativeImages`, Transkriptfelder), `src/Learner/LessonView.php`, `src/Learner/LearningArea.php` (ein Link), `src/Learner/TrainerView.php`, `src/Learning/AnswerService.php`, `src/Progress/ProgressReset.php` (optionaler Parameter), `src/Publish/CourseValidator.php`, `src/Question/QuestionRepository.php` (Spalten beim Einfügen), `src/Ops/AuditView.php` (4 neue Aktionen).
- Nicht geändert: `Plugin.php`, `Schema.php`, `SettingsSchema.php`. Keine neuen REST-Routen und keine neuen Plugin-Hooks (nur Kern-Filter `register_block_type_args`, `render_block_core/image` werden genutzt) → kein API-Changelog-Eintrag. Keine neuen Daten → kein `UninstallManifest`-Eintrag.
- i18n: 116 neue msgids, in `course-captain-de_DE.po` (du) und `course-captain-de_DE_formal.po` (Sie) übersetzt, .mo neu gebaut; POT nicht neu erzeugt (Koordinator).

## Offen

- Undo des Fortschritts-Resets aus dem Journal (E-138) hat weiterhin keine Oberfläche (Journal wird geschrieben; Wiederherstellen fehlt als Dienst).
- Quiz-Regel `passed`: kein Code setzt bisher das Flag `passed` für Quizze (bewertete Quizze über `cc_exam_session` Modus `quiz`, E-68, nicht gebaut); die Lektion nennt die Regel nur.
- Karteikarten-Lektionen im Lernbereich weiter ohne Oberfläche („kann noch nicht genutzt werden“).
- Untertitel als Formularfeld (`subtitles`/`subtitle_url`) weiter nur über das JSON-Feld; nur das Transkript wurde umgestellt (Auftrag).
- VAL-143 (VTT-Prüfung) nicht gebaut.
