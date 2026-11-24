# SET-CC Slice „karten-rechte“ – Nachweis

**Stand:** 24.11.2026 · **Branch:** `r4-c` (Basis 69cac5d)
**Punkte:** Eigentümer-Entscheidungen **D2** (B-03, Karteikarten nie in Prüfungen und bewerteten Quizzen) und **D8** (Rechte-Validatorregeln VAL-100…105, Import von Kurs-/Modul-`rights` nach `_ccap_rights`, `source.exported_from_site` beim Export).
**Grundlagen:** R0-22 §„Medium und Rechte“ (VAL-100…105), R0-38 §3.1–3.3, R0-10 §1.2/§1.5/§2.1/§2.2, E-18, E-62.
Der TODO-Haken bleibt beim Koordinator; E-Einträge zu D2/D8 trägt der Koordinator in `docs/r0/ENTSCHEIDUNGEN.md` ein.

## D2 / B-03 · Karteikarten nie in Prüfungen oder bewerteten Quizzen

Eine gemeinsame Stelle: `QuestionRepository::pool_revisions( $module, true )` liefert den auslieferbaren Pool **ohne Karteikarten**; `QuestionRepository::flashcards( $ids )` erkennt Karteikarten (Typ der aktuellen Revision). Karteikarten bleiben im Pool für Karteikarten-Lektionen und Übung.

| Stelle | Verhalten |
|---|---|
| Blueprint-Ziehung `ExamSessions::draw` | zieht nur aus dem wertbaren Pool; zu wenige wertbare Fragen ergeben wie bisher eine verkürzte Prüfung (`reduced`) |
| Feste Bögen `ExamRules` | `variant_candidates` bietet keine Karteikarten an (Prüfungsseite: Hinweis „Flashcards are not listed …“); `create_variant` und `set_variant_questions` lehnen Karteikarten mit `ccap_validation_failed` (`flashcard`) ab; `variant_blockers` meldet `flashcard` (Festschreiben verweigert, eigene Meldung in `ExamsScreen`) |
| Start eines Bogens `ExamSessions::variant` | Bogen mit Karteikarte (Altdaten, Import, Typwechsel) → `422 ccap_exam_not_startable` (`variant_flashcard`) |
| Modulpool-Quizze `QuizPool::eligible` | geprüft: schließt Karteikarten bereits aus (bewertet zusätzlich Freitext); Test `ActivityTest::test_quiz_from_module_pool` |
| Prüfungsseite (Blueprint-Tabelle, Hinweise „Pool too small“) | zählt nur wertbare Fragen |
| Validator | VAL-085/086/087 zählen nur wertbare Fragen. **Neu VAL-099 (Warnung)**: Pool eines Blueprint-Moduls enthält Karteikarten und dadurch zu wenige (`cc.val.exam.pool_flashcards`) oder keine wertbaren Fragen (`cc.val.exam.pool_only_flashcards`, zusätzlich VAL-085-Blocker, weil die Prüfung nicht starten kann). VAL-089 meldet Bögen mit Karteikarte (`cc.val.variant.flashcard`, Blocker) |

VAL-099 liegt im freien Bereich VAL-070…099 von R0-22 (Code bisher unbelegt); der Koordinator sollte ihn in R0-22 nachtragen.
`docs/r1/R1-29/bereitschaft.md`: LE-T06 und UX-035 auf „bereit“, B-03 als behoben (D2) vermerkt; Rauchtest §3 mit dem Release-Kandidaten wiederholen.

## D8 · Rechte-Validator VAL-100…105

`Rights::course_rights_items( $course )` sammelt alles mit Rechten: `_ccap_rights` von Kurs und Modulen, Fragen/Karteikarten der Modulpools (wirksamer Status inkl. Override, Lizenz aus `license_reference`) und Medien (`ccap_media`) an Kurs, Modulen, Lektionen und aktuellen Fragenrevisionen. Je Eintrag: **fremd/eigen** nach R0-10 §2.2 (`Rights::own_system`: `local`/ohne Quelle oder Paket mit `exported_from_site` = Installations-UUID), **Pflichtpfad** (Kurs selbst, Pflichtmodule und alles darin), **abgelaufen** (`valid_until` < heute). `CourseValidator::rights()` wendet die Regeln an (Gruppe `rights`, Fehler → VAL-000):

| Code | Bedingung | Schwere | Meldung |
|---|---|---|---|
| VAL-100 | fremd, `unknown`/`restricted` oder abgelaufen, im Pflichtpfad | Blocker | eine Meldung je Kurs mit Anzahl und Beispiel („… among them "x" in "y"“), `params.items` (max. 20) |
| VAL-101 | wie VAL-100, außerhalb des Pflichtpfads | Warnung | dito, mit Platzhalter-Hinweis „Media not available“ |
| VAL-102 | eigen, `restricted` | Warnung | je Objekt |
| VAL-103 | eigen, `unknown` | Hinweis | je Objekt |
| VAL-104 | Status `review` (eigen oder fremd) | Warnung | je Objekt |
| VAL-105 | `licensed`/`public`, Lizenz verlangt Namensnennung (`Rights::ATTRIBUTION_LICENSES`: CC-BY*, ODC-BY, DL-DE-BY), Attribution leer | Warnung | je Objekt, Feld `attribution` |

**Sprungziel:** jede Meldung trägt `target.page = rights`, `target.course`, `target.url` = `RightsArea::url( $course )` (Bereich „Rights & release“ der Import-Seite). Im Prüfpanel des Kurseditors (`EditorScreens::jump_link`) erscheint mit `ccap_release_rights` und Bearbeitungsrecht der Link „Check rights“, sonst „Please inform the course administration.“ (R0-22).
**Importaktivierung:** VAL-100 wird beim Aktivieren eines Imports als Befund gemeldet, hält den Kurs aber nicht zurück (`ImportRunner::RIGHTS_REPORTED_RULES`, R0-38 §3.2 Schritt 4 – die Rechte wurden bei der Freigabe bestätigt); manuelles Veröffentlichen blockiert. Dafür überspringt `PublishGuard` Statuswechsel, die `CoursePublisher::publish` selbst ausführt (`CoursePublisher::publishing()`), weil diese Aufrufer bereits validiert haben (Autorendienst mit Bestätigung, Importlauf).

## D8 · Import von Kurs-/Modul-`rights` und eigener Export

- **Import:** `ImportService::write_post_rights` schreibt für Kurse und Module `rights` des Datensatzes, sonst `default_rights` des Manifests, in `_ccap_rights` (`Rights::import_meta`, gleiche Bereinigung wie das Formular über `Rights::clean_meta`). Fremdes `own` wird `licensed` mit Herausgeber als Inhaber (E-62, wie bei Fragen). Importierte Metadaten tragen intern `_source = import` und werden vom nächsten Import ersetzt oder entfernt; **lokal gespeicherte Rechte** (Seite „Rights & release“) gewinnen und überleben jeden Reimport. Ohne `rights` und ohne `default_rights` wird nichts gespeichert (Konvention wie bei Fragen: ohne Angabe gilt `own` der Quelle).
- **Paketprüfung:** Kurs-/Modul-`rights` müssen `common.rights` entsprechen (`Rights::valid_package_rights`: gültiger Status, nur bekannte Schlüssel, `https://`-URLs, echtes ISO-Datum, Längen) → sonst Objektfehler `schema`. Manifest: `V5_default_rights` (ungültiges `default_rights`), `V5_exported_from_site` (keine UUID).
- **Export:** `PackageExporter::write( …, $own_export = true )` (Export-Bereich) setzt `source.exported_from_site` = Installations-UUID (`PackageExporter::installation_uuid()`, Option `course_captain_installation_uuid`, wird beim ersten Export angelegt; `RestoreCheck` prüft sie bereits). Testpakete und fremde Werkzeuge ohne das Flag bleiben fremd. Kurs- und Modul-Datensätze exportieren vorhandene `_ccap_rights` als `rights` (Rundreise).
- `ImportService::own_package` nutzt jetzt `Rights::own_manifest` (eine Regel für Import und Validator); `Rights::course_items` (Bereich „Rights & release“) erkennt eigene Reimporte damit ebenfalls als eigen.

## Tests

`tests/integration/CardsRightsTest.php` (Gruppe `SET-CC-karten-rechte`, 5 Tests):
- `test_blueprint_never_draws_flashcards` – fünf Seeds, nie eine Karteikarte; VAL-099 nur bei zu wenigen wertbaren Fragen; VAL-086 zählt Karteikarten nicht; Pool nur aus Karteikarten → VAL-099 + VAL-085.
- `test_variants_reject_flashcards` – Kandidaten, `create_variant`, `set_variant_questions`, `variant_blockers`, VAL-089, Start `variant_flashcard`.
- `test_rights_rules` – VAL-102/103/104/105 für eigene Inhalte und Kursmetadaten, VAL-100 (Pflichtpfad, inkl. abgelaufener Lizenz) und VAL-101 (optionales Modul) für fremde Medien, Sprungziel, Panel mit Link bzw. Hinweis, A11y-Fragment.
- `test_import_course_and_module_rights` – Kurs-`rights` und `default_rights` (E-62) in `_ccap_rights`, lokale Rechte überleben Reimport, VAL-100 bei Aktivierung gemeldet aber nicht gehalten, Formfehler `schema`/`V5_default_rights`.
- `test_own_export_reimport_is_own_content` – `exported_from_site`, UUID stabil, Reimport → `own`, nicht fremd; andere Installation → fremd.

## Offen

- **Auslieferungssperre selbst** (R0-10 §2.2: fremde `unknown`/`restricted` Fragen/Medien nicht an Lernende, Platzhalter) ist nicht Teil dieses Slices: `QuestionRepository::pool_revisions` filtert weiter nicht nach Rechten. Die Meldungstexte VAL-100/101 („not shown to learners“) folgen R0-22; die Sperre gehört zu R1-14/R1-15.
- Inline-Quizze (`source: inline`) mit Karteikarten: Karteikarten werden dort nie bewertet (`AnswerService` lehnt Antworten ab); eine eigene Validatorregel gibt es dafür nicht (Auftrag nannte Blueprint, Bögen, Modulpool-Quizze).
- Feste Bögen aus Paketen mit Karteikarten werden beim Import nicht abgelehnt, sondern über VAL-089 und den Startschutz abgefangen.
- Rechte von **Lektionen** (`_ccap_rights` an Lektionen, R0-10 §2.1) werden weder importiert noch geprüft; VAL-106…109 nicht Teil von D8.
- Eine geänderte `default_rights` wirkt nur auf Kurs-/Modul-Datensätze, die der Import neu schreibt (`create`/`update`), nicht auf unveränderte.
- VAL-099 in R0-22 nachtragen; B-03-Erwähnung in der Zählzeile vom 23.11. (`bereitschaft.md`) ist historisch belassen.
