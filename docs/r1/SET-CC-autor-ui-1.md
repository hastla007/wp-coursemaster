# SET-CC Slice „autor-ui-1“: UI-F8 Teil 1

Stand 23.11.2026, Branch `r3-d` (Basis 0081aef). Nachweis je Teil; die TODO wird vom Koordinator abgehakt. UI-F8 ist damit **nicht** vollständig: Teil 1 deckt feste Prüfungsbögen, Kurs-Export, Import per Link/Ordner, „Rechte & Freigabe“ und den Typwechsel ab. Offen für Teil 2: Tags und Sammelbearbeitung der Fragenbank, Zugänge aussetzen/verlängern, Zugangsregeln, Fortschritt zurücksetzen, Schalter „dekorativ“ im Block-Editor.

Alle Oberflächen sind reine admin-post-Formulare (ohne JS bedienbar), jede Aktion mit Nonce je Operation und Objekt (`AdminUi::form`), Rechteprüfung in der Oberfläche und im Dienst, Fehler als `ccap_*`-Code mit Klartext in `role=alert`, Erfolg in `role=status`. Keine neuen REST-Routen oder Hooks (Snapshots unverändert); Eintrag in `docs/API-CHANGELOG.md` für die admin-post-Aktionen und die Startregel.

## 1. Feste Prüfungsbögen (Varianten)

**Gebaut:**
- `ExamRules` (Dienst): `form_mode()`/`set_form_mode()` (Regel-Einstellung `form_mode` = `random`|`variants`, nur im Entwurf; veröffentlichte Regeln bleiben unverändert, `new_version` übernimmt den Modus), `variants()`, `variant_candidates()` (Fragen aus den Modulpools des Kurses), `create_variant_draft()` (nächste freie Nummer je Kurs), `set_variant_questions()` (nur Entwurf, nur Fragen des Kurses), `variant_blockers()` (leer/nicht auslieferbar – gleiche Bedingung wie der Start), `freeze_variant()` (Entwurf → `published`, Recht „Kurs veröffentlichen“), `retire_variant()` (→ `retired`). Rechte über `PolicyService::EDIT_COURSE_ITEM` mit Typ `variant` (vorhanden); eingefrorene Bögen → `409 ccap_variant_frozen`. Tabellen `ccap_exam_variant`/`ccap_exam_variant_question` unverändert (R0-08).
- `ExamsScreen`: Auswahl „Fragenauswahl: Zufallsziehung / feste Prüfungsbögen“ im Entwurfseditor; Abschnitt „Feste Prüfungsbögen“ auf jeder Prüfungsseite (Bögen gehören zum Kurs): Liste mit Status und Fragen (geordnete Liste), Entwurf mit Positionsfeld je Poolfrage (leer = nicht enthalten), Festschreiben, Außer-Dienst-Stellen/Verwerfen mit Bestätigung. Ops `variant_create|variant_save|variant_freeze|variant_retire`, Audit `exam_variant_saved|frozen|retired`. Veröffentlichen einer Regel im Modus „feste Bögen“ verlangt mindestens einen festgeschriebenen Bogen statt eines Blueprints.
- Lernende: `ExamRoom::course_exams` bietet im Modus „feste Bögen“ die festgeschriebenen Bögen als Radiogruppe (fieldset/legend) an, ohne Bogen den Hinweis „kein Prüfungsbogen verfügbar“; `ExamRoom::start` reicht `variant` weiter. `ExamSessions::start`: `variants` ohne Bogen bzw. `random` mit Bogen → `422 ccap_exam_not_startable` (`variant_required`/`variant_not_offered`); Bestandsregeln ohne `form_mode` wie bisher. Laufende/abgeschlossene Versuche behalten ihren Snapshot (Bogen-Status wird nur gelesen).

**Tests:** `tests/integration/AuthorUiF8Test.php::test_fixed_exam_forms` (Modus, Nonce/Rechte, leerer Bogen, Positionen, fremde Frage abgewiesen, Festschreiben, eingefroren unveränderlich, Veröffentlichen ohne Blueprint, Kursraum-Auswahl + Start ohne/mit Bogen, Außer-Dienst-Stellen mit Bestätigung, A11y-DOM), `::test_form_mode_random_and_legacy`.

## 2. Seite „Kurs-Export“

**Gebaut:** `src/Admin/ExportArea.php`, Bereich `export` der Seite „Inhalte importieren“ (Cap `ccap_export_content`, M-2). Kurse wählen (Kästchen), Optionen: Pakettitel, Name der Quelle (`source.system`, Vorgabe aus dem Website-Host), Datensätze je Datendatei (1…5000). Erzeugung über `PackageExporter::records()`/`write()` (Rechte M-04 je Kurs im Exporter), Download als `application/zip` per admin-post `ccap_course_export`, Datei danach gelöscht; Audit `course_exported`.

**Tests:** `::test_course_export` (Rechte, Nonce, ohne Kurs, ungültige Option, Manifest/Quellname, Aufteilung je Datei, Audit, Rundlauf durch die Importprüfung, A11y).

**Offen:** Medien sind im Exporter noch nicht enthalten (`records()` sammelt keine Medien); die Rechtesperre beim Export (R0-10 §2.2) ist im Exporter nicht umgesetzt; `source.exported_from_site` schreibt der Exporter nicht, ein Reimport auf derselben Website gilt daher als fremd (E-62).

## 3. Import per Link/Ordner in der Oberfläche

**Gebaut:** `ImportScreen` Ops `link` und `folder` über die vorhandenen `ImportSources::from_url()` (https/443, keine Zugangsdaten in der URL, keine internen Hostnamen, jede aufgelöste Adresse öffentlich via `public_ip()`, IP-Pinning, ≤ 3 Weiterleitungen, Paketlimit, optionale SHA-256) bzw. `from_drop()` (nur Basisname, Realpfad direkt im geschützten Ablageordner, keine Links; übernommene Pakete werden gelöscht). Beide enden im selben Ablauf wie der Upload (`check_and_preview`: Formatprüfung → Probelauf → Freigabe). Die Ordnerliste zeigt nur ZIP-Dateien des Ablageordners. Klartexte für `ccap_link_*` und `ccap_package_too_large`. `process()` nimmt optional eine `ImportSources`-Instanz (Tests).

**Tests:** `::test_import_by_link_and_folder` (http, leer, private Auflösung, IP-Literal 127.0.0.1, falsche Prüfsumme, Rechte, Erfolg bis `previewed`; Ordner: `../`, fehlend, Erfolg + Datei entfernt, A11y).

## 4. Seite „Rechte & Freigabe“ je Kurs

**Gebaut:** `src/Admin/RightsArea.php`, Bereich `rights` (Cap `ccap_release_rights` + Bearbeitungsrecht am Kurs).
- Rechteangaben des Kurses: Post-Meta `_ccap_rights` (JSON, Felder nach R0-10 §1.5/§2.1: `status`, `owner`, `license`, `license_url`, `attribution`, `source_url`, `valid_until`, `note`); Prüfung (Status aus `Rights::STATUSES`, nur https-Adressen, Datum), Pflichtgrund, Audit `course_rights_saved` mit Vorher/Nachher; Feldfehler mit `aria-invalid`/`aria-describedby`. Ohne gespeicherte Angaben: Herkunft des importierten Kurses (Quelle, Herausgeber, `default_rights` des Pakets) als Vorbelegung (`Rights::package_rights`). Meta in `UninstallManifest::POST_META`.
- Freigabestand: Fragen/Karteikarten der Modulpools je Rechtestatus, eigen/importiert, Zahl der für Lernende gesperrten (fremd ∧ `unknown|restricted`) und der in Prüfung befindlichen (Wortlaut VAL-100/VAL-104).
- „Ungeklärte Rechte dieses Kurses freigeben“: Vorschau-Zahl + Bestätigung der Zahl + Pflichtgrund → `licensed`-Overrides (`Rights::course_release`, Audit `rights_course_release`; geänderte Zahl → `ccap_confirmation_required` mit neuer Zahl).
- Einzelne Inhalte (zusätzlich `ccap_import_content`): Status je Inhalt über das vorhandene `Rights::override` (Pflichtgrund, Audit, übersteht Reimporte); nur Inhalte dieses Kurses.

**Tests:** `::test_rights_and_release` (Import mit `unknown`, Zusammenfassung, eigene Inhalte nie gesperrt, Herkunft, Seite, Rechte, veraltete Zahl, ohne Grund, Freigabe, Kursrechte ungültig/gültig mit Fehleranzeige, Einzelstatus inkl. fremder Inhalt abgewiesen, A11y). Bereiche zusätzlich in `A11yI18nTest::test_A11Y_admin_screens_and_blocks`.

**Offen:** Der Publish-Validator hat noch keine Regeln VAL-100…105 und keine Hooks dafür; die Seite ist als Sprungziel („A-13 › Rechte & Freigabe“) vorbereitet, die Validator-Regeln selbst sind nicht Teil dieses Slices. Die Kurs-Meta `_ccap_rights` wird vom Import noch nicht geschrieben (Paketformat-Feld `rights` an Kurs/Modul wird ignoriert).

## 5. Typwechsel einer Frage

**Gebaut:** `src/Question/TypeChange.php` (reine Planung): Text, Rückmeldungen und Schlagwörter bleiben; Optionen der Auswahltypen werden übertragen (Mehrfach → Einfach behält nur die erste richtige Markierung = Verlust), Wahr/Falsch erkennt „True/False/Wahr/Falsch…“, die Lösung (richtige Optionen, Musterantwort, Kartenrückseite, Zahl, Paare, Reihenfolge, Lücken) wird zur Musterantwort/Rückseite, Reihenfolge ↔ Optionen, Zahl aus Lösungstext; alles nicht Übertragene steht als Verlust in der Liste; `complete` = besteht die Typprüfung. `QuestionRepository::add_revision()` legt **immer** eine neue Entwurfsrevision an (keine bestehende Revision wird geändert; Bildpflicht und Rechtestatus werden übernommen).
- `QuestionsScreen`: Abschnitt „Fragetyp ändern“ im Editor (GET „Änderung prüfen“ zeigt Übernommen/Verloren in `role=status`/`role=alert`), POST `op=change_type` verlangt bei Verlust das Bestätigungskästchen (`ccap_confirmation_required`, Rücksprung mit `change_to`), Audit `question_type_changed` (vorher/nachher/Verluste). Die veröffentlichte Revision bleibt aktuell, bis die neue veröffentlicht wird.

**Tests:** `::test_type_change_plan` (Einfach/Mehrfach/Freitext/Karte/Wahr-Falsch/Reihenfolge/Zahl/Lückentext, gleicher Typ abgewiesen), `::test_type_change_keeps_exam_snapshots` (laufender Prüfungsversuch: `revision_id`, Snapshot und Revisionszeile bitgleich vor/nach Typwechsel und nach Veröffentlichen; Rechte, gleicher Typ, Bestätigung, A11y).

## Sonstiges

- Neue Audit-Aktionen in `AuditView::registry()`: `exam_variant_saved`, `exam_variant_frozen`, `exam_variant_retired`, `question_type_changed`, `course_exported`, `course_rights_saved`, `rights_course_release` (`::test_areas_and_registry`).
- i18n: 147 neue msgids, in `course-captain-de_DE.po` (du) und `course-captain-de_DE_formal.po` (Sie) übersetzt, .mo neu gebaut; `check-address` 0 Befunde, textdomain-lint 0. POT nicht neu erzeugt (Koordinator).
- Geänderte gemeinsame Dateien: `src/Ops/AuditView.php`, `src/Kernel/UninstallManifest.php`, `src/Learner/ExamRoom.php`, `src/Exam/ExamSessions.php`, `src/Exam/ExamRules.php`, `src/Import/Rights.php`, `src/Admin/ImportScreen.php`, `src/Admin/ExamsScreen.php`, `src/Admin/QuestionsScreen.php`, `src/Question/QuestionRepository.php`, `tests/integration/A11yI18nTest.php`. Nicht geändert: `Plugin.php` (die neuen admin-post-Handler registriert `ImportScreen::register()`), `Schema.php`, `SettingsSchema.php`, `LearningArea.php`.
