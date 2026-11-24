# Nachweis Kap. 28 „Drei getrennte Fortschrittsdimensionen“ (Slice „kompetenz“, Branch `r6-b`)

Stand 24.11.2026, Basis 6c2f244. TODO-Häkchen setzt die Koordination (`TODO-WETTBEWERBSANALYSE.md` Z. 710, Duplikat-Anteil Z. 668).

Offen war laut Stand-Vermerk: „Competency hat weder REST-Ausgabe noch Anzeige (nur Dienst `Progress\Competency`).“ Completion und Mastery hatten API und UI. Jetzt haben alle drei Dimensionen eine gemeinsame REST-Ausgabe und eine gemeinsame Anzeige mit Regeltext.

## Gebaut

### Lesemodell `src/Progress/ProgressDimensions.php` (keine eigene Fachlogik)

- **Completion** aus `ccap_course_progress` (`CourseProgress::get`): `status`, `percent`, `done`/`total` (Pflicht-Activities), `last_activity_at`, `rule_version` (`CourseProgress::RULE_VERSION`).
- **Mastery** aus `SmartTrainer::summary()` (dieselbe Quelle wie Trainer-Bericht und Übungsübersicht): Stufenzählung, `percent` (bei 0 beantworteten Fragen `null` mit „Noch keine Daten“, nie 0 %, R0-08 §5.2), `algorithm_version` `smarttrainer.v1`.
- **Competency** aus `Competency::states()` (E-73, abgeleitet, keine Tabelle). Bekannte Lernziele eines Kurses: `certifies_objectives` der veröffentlichten Prüfungsregeln plus `competency:`-Schlagworte der veröffentlichten Fragen in den Kursmodulen (Titel = Schlagwort-Label). Ziele, die nur noch im Nachweis stehen (ältere Regelversion, Zertifikat), werden weiter gezeigt. `Competency::states()` liefert zusätzlich `source_type` (`exam_session`|`certificate`).
- Je Dimension ein übersetzter Werttext (`summary`) und die Regel (`rule`, „Warum dieser Wert“); je Lernziel ein Erklärtext nach R0-08 §5.3 („Nachgewiesen durch eine bestandene Prüfung (Regelversion …) am … mit … von … Punkten. Gültig bis …“ / „Abgelaufen am …“ / „Widerrufen …“ / „Nicht nachgewiesen …“). REST und beide Oberflächen zeigen dieselben Texte.

### REST (R0-19-Konventionen, Namespace `course-captain/v1`, nur lesend)

| Route | Zugriff | Fehler |
|---|---|---|
| `GET /courses/{course}/progress` | angemeldet, nur eigene Daten, veröffentlichter Kurs (ID oder UID) | `401 ccap_auth_required`, `404 ccap_not_found` |
| `GET /reports/courses/{course}/learners/{user}/progress` | M-11 über `PolicyService::VIEW_LEARNER` (selbst, zugewiesener Trainer im Gruppenumfang, websiteweites Personal; nie Gruppenbesitzer) | `401 ccap_auth_required`, `404 ccap_not_found` (auch für Personen außerhalb des Umfangs – kein 403-Orakel) |

- `Cache-Control: private, no-store` (über `LearnerRoutes::run`, zusätzlich die No-Store-Header von `RestContract`).
- Fehlerkatalog: keine neuen Codes nötig; beide Codes stehen im Katalog (`RestContract::title()`/`messages()`), die Routen nennen sie im Changelog.
- Snapshot `docs/api/routes.v1.json` mit `CCAP_UPDATE_SNAPSHOTS=1` neu geschrieben (2 Routen), `docs/API.md` nur zwei Tabellenzeilen ergänzt, `docs/API-CHANGELOG.md` ein Eintrag mit Antwortschema.
- Zeiten RFC 3339 UTC, Punkte in Tausendsteln.

### Oberfläche

- **Lernbereich, Kursansicht** (`LearningArea` → `ProgressPanel`): Abschnitt „Fortschrittsübersicht“ (h2) mit drei getrennten Unterabschnitten (h3) „Bearbeitet“, „Beherrscht“, „Kompetenznachweis“, je Werttext und „Warum dieser Wert:“; Lernziele als Tabelle mit Caption und `th scope`, Zustand als Wort (nicht nur Farbe). Erscheint, sobald die Person Zugang hat oder hatte (nicht bei `not_granted`).
- **Trainer-Ansicht je Lernendem** (`Lernende` → Kurs → Person, `LearnersScreen::learner_detail`): derselbe Abschnitt (h3/h4) über `ProgressDimensions::build()`; Rechte wie bisher über `VIEW_LEARNER`.
- Nur logische Eigenschaften, keine neuen Steuerelemente, kein JavaScript.

## Tests

`tests/integration/ProgressDimensionsTest.php` (`@group K28-710`, `@group SET-CC-kompetenz`), 4 Tests:

- `test_values_match_the_services`: Werte der REST-Antwort = `CourseProgress::get`, `SmartTrainer::summary`, `Competency::states` (Quelle, Regelversion, Datum, Punkte, Gültigkeit); bekannte Ziele aus Regel und Frage-Schlagwort; zweite Person ohne Daten („Noch keine Daten“, `percent: null`); Trainer-Route liefert denselben Inhalt; Kurs per UID; `no-store`.
- `test_rbac_and_idor`: Gast 401 (beide Routen); fremder `user`-Parameter auf der eigenen Route wirkungslos; Lernende → andere Person 404; selbst über Berichtsroute 200; Trainer nicht zugewiesen 404, anderer Kurs zugewiesen 404, Gruppenumfang ohne Platz 404, zugewiesen 200; Admin 200; unbekannte Person/Kurs 404; unveröffentlichter Kurs eigene Route 404, Personal 200; `PUT` → 404 (nur lesend).
- `test_evidence_frozen_after_course_change`: nach neuer Regelversion mit anderem Ziel, archivierter Schlagwort-Frage und entferntem Modul bleibt der Nachweis in der REST-Ausgabe identisch (Quelle, Regelversion, Datum, Punkte, Gültigkeit der alten Version); neues Ziel „nicht nachgewiesen“.
- `test_ui_learner_room_and_trainer_view`: `A11yDom::document_problems` (Kursansicht) und `A11yDom::fragment_problems` (Trainer-Ansicht) leer; drei Überschriften, drei Regeltexte, Zustand als Text; kein Abschnitt ohne Zugang.

Angepasst: `ExamSnapshotImmutabilityTest::test_competency_evidence_frozen_with_the_snapshot` (erwartet `source_type`).

## Übersetzungen

28 neue msgids (25 Einzeltexte, 3 mit Plural) in `languages/course-captain-de_DE.po` und `languages/course-captain-de_DE_formal.po`; Texte ohne Anrede, daher in beiden Dateien gleich. `.mo` neu gebaut, `tools/i18n/check-address.php` 0 Befunde, `textdomain-lint` 0.

## Bewertung Z. 710 / Z. 668

- **Z. 710** („Bearbeitung, Beherrschung und Kompetenznachweis samt erklärbaren Regeln, API und UI spezifizieren“): Der offene Teil (Competency ohne REST und Anzeige) ist geschlossen; alle drei Dimensionen haben getrennte Werte, erklärbare Regeln, API und UI. **Kann abgehakt werden.**
- **Z. 668** (Kompetenzmatrix/Lernziele): Der Anteil „Offen wie Z. 710“ ist erledigt. **Offen bleibt** der Matrix-Editor (Lernziele mit Titel/Gültigkeit als eigenes Kursobjekt bearbeiten; heute nur `certifies_objectives` der Prüfungsregel und `competency:`-Schlagworte, R4-01). Z. 668 daher **nicht** abhaken; Stand-Vermerk auf „offen: Matrix-Editor (R4-01)“ kürzen.

## Noch offen / Hinweise

- Kein Matrix-Editor und keine Lernziel-Titel als eigenes Objekt (Titel = Schlagwort-Label oder UID).
- R0-19 §2.3 nennt die zwei neuen Routen noch nicht (keine Änderung an R0-Dokumenten in diesem Slice); Vorschlag: R-59/R-60 nachtragen. R-44 (`GET /reports/courses/{course}/learners/{user}`) bleibt unverändert unimplementiert.
- Manuelle/externe Nachweise weiter R2/Pro (E-73).
