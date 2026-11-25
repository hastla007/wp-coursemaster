# SET-CC Slice „rechte-auslieferung“ – Nachweis

**Stand:** 24.11.2026 · **Branch:** `r6-c` (Basis 6c2f244)
**Punkte:** Lücken aus `SET-CC-karten-rechte.md` (D2/D8): Auslieferungssperre für fremde Inhalte mit ungeklärten/gesperrten Rechten, Lektionsrechte, Karteikarten in importierten festen Varianten, `default_rights` bei unveränderten Datensätzen.
**Grundlagen:** R0-10 §1.2, §2.1, §2.2, §4.4; R0-21 §3.1, §3.7, §4; R0-22 VAL-100…105; R1-14, R1-15; E-18, E-62.
Den TODO-Haken setzt der Koordinator.

## 1 · Auslieferungssperre (R0-10 §2.2)

**Zentrale Stelle:** `src/Access/RightsGate.php`. Die Regel steht in `Rights::withheld()`: Nur fremde Inhalte werden gesperrt, wenn ihr Status `unknown` oder `restricted` ist oder `valid_until` abgelaufen ist. Eigene Inhalte werden nie gesperrt (E-18).
- „Fremd“ für Fragen, Karteikarten und Lektionen: `Rights::own_system()`, also `local` oder ein Reimport des eigenen Exports. Für Medien gilt neu `Rights::media_foreign()`: Lokal hochgeladene Medien zählen als fremd, sobald ihr Status nicht `own` ist (R0-10 §2.2). Der Validator (`course_rights_items`) nutzt dieselbe Regel.
- Fragen: Maßgeblich ist der wirksame Status (Override, sonst aktuelle Revision) mit der Lizenz der aktuellen Revision. Abhängigkeitsregel: Eine Frage mit `image_required` ist gesperrt, wenn alle verknüpften Medien gesperrt sind.
- Lektionen: Maßgeblich ist `_ccap_rights` der Lektion.

| Auslieferungsstelle | Verhalten |
|---|---|
| `QuestionRepository::pool_revisions()` / `pool()` / `pool_member()` | lassen gesperrte Fragen weg; der neue Parameter `$withheld = true` zeigt alles (für Redaktion, Strukturprüfsumme, Export, VAL-020) |
| `QuestionPresenter::deliver`, `deliver_inline`, `verify` | `assert_rights()` → 404 „not deliverable“. Das greift auch bei vorbefülltem Pool-Check (Smarttrainer) und bei Tokens, die vor der Rechteänderung ausgestellt wurden. Antworten und Aufdecken laufen über `verify` |
| `QuestionPresenter::quiz_questions()` (Lektionsquiz, Quiz aus dem Modulpool, auch bei schon gezogenen Durchgängen) | gesperrte Fragen werden weder gelistet noch gezählt. Das gilt auch für die Abschlussberechnung (`AnswerService`) |
| `QuizPool::eligible` | über `pool_revisions` |
| Karteikarten-Decks | neues `QuestionPresenter::deck()`; `Flashcards::counts()` (Abschluss) zählt nur auslieferbare Karten |
| Smarttrainer (`candidates`, also next/summary/due/topics), `practice` | eine Abfrage über alle Kandidaten; `practice` → 404 über den Presenter |
| Validator und Prüfungsseite (VAL-020/029/085/086/099, „Pool too small“) | zählen bewusst mit `$withheld = true`: Rechtefolgen meldet nur VAL-100/101. Sonst würde ein Import, der VAL-100 nur melden soll, über die Poolregeln doch zurückgehalten |
| Prüfungsziehung (Blueprint) | über `pool_revisions(…, true)`; zu wenige Fragen → verkürzte Prüfung wie bisher |
| Feste Variante | Start → `422 ccap_exam_not_startable` (`variant_rights`); `variant_candidates` bietet gesperrte Fragen nicht an |
| Laufende Versuche | unverändert: Snapshot und eingefrorene Revisionen werden nicht neu geprüft (Test) |
| Geschützte Dateien `ProtectedFiles::prepare` | gesperrt → 404 wie bei unbekannter Datei (kein Hinweis, kein Pfad). Wer einen der Kurse bearbeiten darf, ist ausgenommen; `listing()` zeigt Lernenden keine gesperrten Dateien |
| Lektionsansicht `LessonView` | Gesperrte Lektion: bleibt in der Liste, Lernende sehen „This content is not available at the moment.“ (`role=status`), keine Aktivität, kein Abschluss. Gesperrtes Medium (Datei/Video `media`): Platzhalter „Media not available“, kein Download-Knopf, keine UID; `handle('download')` → `ccap_not_found` |
| Autoren/Admins | Vorschau (`PreviewScreen`): Hinweis statt Frage („Question n: Not shown to learners: …“, Grund unknown/restricted/expired), Anzahl der verborgenen Fragen, Hinweis an gesperrten Lektionen und Lektionsmedien; eingebettete gesperrte Medien im Lektionstext erscheinen wie bei Lernenden als Platzhalter. In der Lektionsansicht sehen Bearbeitende den Grund statt des Platzhalters (`RightsGate::exempt` = `edit_post` am Kurs) |

**Abfragebudget (R0-14):** `RightsGate::columns()` und `absorb()` hängen die Rechtespalten an Abfragen an, die ohnehin laufen: Pool (`pool_revisions`, `pool_member`) und Kandidaten des Smarttrainers. Ein Pool-Treffer gilt im Presenter als rechtegeprüft. OP-03 und OP-05 bleiben so in ihren Grenzen (PerformanceTest grün).

**Golden-Master-Fixture:** Die Fixture hat Q-A3-010 und FC-A-07 fremd mit Status `unknown` (R0-05 §2.3); seit der Sperre bekommen Lernende sie nicht mehr. GM-028…031, 049, 052, 062/063, 078, 079 und F4 erwarten aber den vollständigen Kurs C-A. Deshalb gibt `NeutralFixture::release_rights()` die beiden Inhalte als `licensed`-Override frei, aufgerufen in `GoldenMasterTest`, `GoldenMasterLearningTest` und `GoldenMasterGapsTest`. Die Import-Golden-Master GM-123/GM-127 (`GoldenMasterImportTest`) behalten den importierten Status. **Koordinator:** Bitte in R0-05 vermerken, dass die Lern- und Prüfungs-GMs eine Freigabe voraussetzen.

**Caching (R0-21):** Das Urteil ist eine K3-Entscheidung. Es liegt nur im Memo der Gate-Instanz (eine Operation) bzw. im ContentGate-Memo, nie in einem persistenten Cache; eine Rechteänderung wirkt also ab dem nächsten Aufruf. `RightsGate::changed()` erhöht nach dem Commit die `course_gen` der betroffenen Kurse (Matrix §3.7). Aufgerufen wird es bei: Override, Paket- und Kursfreigabe (`Rights::write`-Aufrufer), `set_course_meta`, `import_meta` (nur wenn sich etwas ändert), Import von Fragenrechten (`write_rights`, löscht zusätzlich die K1-Kopie `qrev:<id>`, weil die Rechte in der Revisionszeile stehen) und Import von Medien.

## 2 · Lektionsrechte `_ccap_rights`

- Import: Das Feld `rights` des Lektionsdatensatzes geht über `Rights::import_meta` (dieselbe Bereinigung `clean_meta`) in `_ccap_rights`. `default_rights` gilt laut R0-10 §1.2 nur für Kurs/Modul/Blueprint, nicht für Lektionen. Die Paketprüfung behandelt Lektions-`rights` wie Kurs/Modul (`valid_package_rights` → Objektfehler `schema`).
- Export: Der Lektionsdatensatz trägt vorhandene Rechte als `rights` (ohne internes `_source`).
- Validator: `Rights::course_rights_items` liefert Einträge vom Typ `lesson` (Pflichtpfad über das Modul, fremd über `source_map`). VAL-100…105 wirken damit auch auf Lektionen.

## 3 · Importierte feste Varianten mit Karteikarten (D2)

`ImportService::variant_has_card()`: Ein Blueprint-Datensatz mit fester Variante wird zum Objektfehler `variant_flashcard` (Code `IMP-V6.01`, eigene Meldung und Vorschlag in `ImportReport`), wenn die Variante eine dieser Karten enthält:
- eine Frage des Pakets mit Typ `flashcard`,
- eine Quell-ID der Entität `flashcards`,
- eine bereits importierte Frage, deren aktuelle Revision eine Karteikarte ist.

Der Startschutz `variant_flashcard` und VAL-089 bleiben als zweite Linie.

## 4 · Geänderte `default_rights` bei unveränderten Datensätzen

Die Planung markiert unveränderte Kurs-/Modul-Datensätze ohne eigenes `rights` mit `defaults`. `apply_item` schreibt für sie die Standardrechte des aktuellen Manifests über `import_meta`. Ohne `default_rights` wird der importierte Standard entfernt. Lokal gespeicherte Rechte (`_source` ≠ `import`) bleiben. Die Annahme in `CardsRightsTest` („unveränderter Datensatz behält seine importierten Rechte“) beschrieb genau diese Lücke und wurde angepasst.

## Tests

`tests/integration/RightsDeliveryTest.php` (Gruppe `SET-CC-rechte-auslieferung`, 7 Tests):
- `test_pool_trainer_and_delivery_withhold_foreign_content`: Gate-Urteile (unknown, expired, Bildabhängigkeit, restricted-Karte; eigenes `unknown` und gültige Lizenz frei), Pool/Member, Auslieferung 404, Smarttrainer (next/summary/practice). **Cache:** `course_gen` ändert sich bei Override, Freigabe und Kursrechten; ein altes Token wird sofort abgelehnt.
- `test_exam_draw_and_running_attempt`: Variantenkandidaten, Ziehung nur auslieferbarer Fragen, laufender Versuch behält die Frage nach einer Rechteänderung, neuer Versuch zieht sie nicht, feste Variante `variant_rights`.
- `test_quiz_pool_and_flashcard_deck`: Quiz aus dem Modulpool (auch mit vorher gezogenem Durchgang), `deliver_inline` 404, Deck/Zählung ohne gesperrte Karte.
- `test_lesson_view_media_route_and_preview`: Lernende sehen den Platzhalter, Autoren den Hinweis (gesperrte Lektion, Datei, lokal hochgeladenes Video mit `unknown`), Download verweigert, Dateiroute 404 bzw. für Bearbeitende freigegeben, Dateiliste, Vorschau mit Hinweisen, VAL-100 mit `lesson:<id>`, A11y-Fragmente, `course_gen` bei importierten Lektionsrechten.
- `test_lesson_rights_import_and_export`: Import, Rundreise im Export, keine `default_rights` für Lektionen, Formfehler `schema`.
- `test_imported_variant_with_flashcard_is_rejected`: Fragetyp `flashcard`, `flashcards`-Entität, Gegenprobe ohne Karte; Code und Meldung.
- `test_changed_default_rights_reach_unchanged_records`: Modul folgt dem neuen Standard (Aktion `unchanged`), lokale Kursrechte bleiben; ohne Standard wird er entfernt.

`tests/integration/RightsLearningListsTest.php` (gleiche Gruppe, 2 Tests, Nachtrag 24.11.2026):
- `test_favorites_and_collections_hide_withheld_questions`: Liste, Sammlung, Modus `favorites` ohne gesperrte Frage; Markieren/Einsortieren 404; Markierung bleibt, Freigabe bringt sie zurück.
- `test_mistake_training_hides_withheld_questions`: Lernfehler, Prüfungsfehler, `remaining` und Modus `wrong` ohne gesperrte Frage; nach der Freigabe wieder enthalten.

i18n: 10 neue msgids (eine davon mit `_n`), Übersetzungen in `de_DE` (du) und `de_DE_formal` (Sie), .mo neu erzeugt.

## Offen / Abweichungen

- **Abhängigkeitsregel:** Gesperrt wird nur, wenn verknüpfte Pflichtmedien vorhanden und alle gesperrt sind. Fehlende Bilder bleiben Sache des Validators (VAL-141).
- ~~**Bilder im Lektionstext**~~ **Erledigt (24.11.2026):** `Learner\WithheldInlineMedia` ersetzt in der Lektionsansicht nach `the_content` eingebettete gesperrte Medien (`<img>`, `<video>`/`<audio>`/`<source>`, `<object>`/`<embed>`/`<iframe>`, auch `srcset`/`poster`; URLs `/cc-file/<uid>/` und `?course_captain_file=<uid>`) durch den Platzhalter „Media not available“ (ohne UID, ohne Pfad); Links auf gesperrte Dateien verlieren den Link, der Text bleibt. Bearbeitende sehen den Grund (`RightsGate::notice`). Freigegebene und eigene Medien bleiben unverändert; ohne Datei-URL im Text keine Abfrage. Tests: `tests/unit/WithheldInlineMediaTest.php`, `tests/integration/RightsLessonImagesTest.php`. **Vorschau (Inhaberentscheidung 25.11.2026, erledigt):** `PreviewScreen::html` wendet denselben Filter im Lernenden-Modus an (`PreviewScreen::learner_text`): Der Lektionstext zeigt in der Vorschau genau das, was Lernende sehen (Platzhalter „Media not available“, Link ohne Ziel); den Grund nennt der Hinweis über der Lektion. Tests: `tests/unit/PreviewWithheldMediaTest.php`, `RightsLessonImagesTest::test_withheld_embedded_media_become_placeholder` (Abschnitt Vorschau).
- **Export (R0-10 §6.1):** ~~Der Ausschluss fremder Inhalte mit `export.excluded[]` ist nicht Teil dieses Slices.~~ **Erledigt (24.11.2026):** `PackageExporter::collect()` lässt fremde Lektionen/Fragen/Karten (Quelle nicht eigen, `Rights::own_system()`) und von der Auslieferungssperre gesperrte Inhalte (`unknown`, `restricted`, `expired`, gesperrtes Pflichtbild) standardmäßig weg, listet sie im Manifest unter `export {course_ids[], included_foreign, excluded[] {entity, source_id, reason}}` (Grund `foreign` oder der Sperrgrund) und entfernt die Verweise (Modul-Lektionen, Fragenpool ohne Lücken in `sort`), damit das Paket gültig bleibt. Opt-in „Fremde Inhalte einschließen“ im Exportbereich nur mit Bestätigungskästchen (`ccap_export_confirm_foreign`) und Audit `course_export_foreign_included`. Kurse und Module bleiben immer im Export (Container mit Rechte-Metadaten). Medien exportiert der Exporter weiterhin nicht. Test `RightsDeliveryTest::test_IMP_031_export_excludes_foreign_content` (IMP-031, E-66); `test_lesson_rights_import_and_export` nutzt für die Rundreise jetzt das Opt-in.
- ~~**Favoriten und Fehlertraining** listen nur IDs. Gesperrte Fragen bleiben dort sichtbar, werden aber nicht ausgeliefert (404 über den Presenter).~~ **Erledigt (24.11.2026):** `Favorites::list()`/`question_ids()` (auch je Sammlung) und `ErrorTraining::wrong()`/`from_exam()` lassen gesperrte Fragen weg; sie zählen auch nicht in `remaining` des Einstiegs „Prüfungsfehler üben“. Markieren und Einsortieren einer gesperrten Frage antwortet 404 wie die Auslieferung. Die Markierung und die Antworten bleiben gespeichert; nach der Freigabe ist die Frage wieder in Liste und Sammlung (`Favorites::accessible_all()`, Rechte in einer Abfrage je Liste). Test `tests/integration/RightsLearningListsTest.php`.
- Kurs- und Modul-`_ccap_rights` bleiben Metadaten für VAL-100…105. Sie sperren keine ganzen Kurse oder Module.
- Lokal geleerte Kurs-/Modulrechte (die Meta wird gelöscht) kann ein späterer Import wie bisher wieder mit importierten Standardrechten füllen.
- Eine gesperrte Pflichtlektion kann nicht abgeschlossen werden. Manuelles Veröffentlichen verhindert VAL-100 (Blocker); bei der Importaktivierung wird es nur gemeldet.

## Ergebnisse (24.11.2026, nach der Plattenvoll-Störung neu gelaufen)

phpcs 0 Fehler · PHPStan 0 · Unit **141 / 3 123** · Integration **626 / 21 228** (3 übersprungen) · Multisite **639 / 40 726** (3 übersprungen) · Free-Guard 0 · Textdomain-Lint 0 · check-address 0.
