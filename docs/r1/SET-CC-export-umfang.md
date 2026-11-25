# SET-CC export-umfang · E-382 / IMP-030

Inhaber-Entscheidung E-382 (25.11.2026): Der Kurs-Export enthält Medien und Prüfungsvorlagen (R0-10 §6.1); ein Reimport gleicht nicht per UID ab.

## Gebaut

| ID | Umsetzung |
|---|---|
| IMP-030 Medien | `PackageExporter::collect()` sammelt die aktiven Medien aus `ccap_content_media` an Lektionen (`lesson`) und an der aktuellen Fragenrevision (`question_revision`), also Bilder, Videos, Downloads, Vorschaubilder und Untertitel (`subtitle`). Je Medium ein Datensatz in `data/media/*.json` (`source_id` = Quellzuordnung, sonst UID; `path` = `media/<sha256-12>/<name>`, `title`, `media_type`, `mime_type`, `size`, `sha256`, `alt_text`, `decorative` bei Bild ohne Alternativtext, `caption`, `description`, `access`, `rights` aus `rights_status`/`rights_owner`/`license_reference`). Größe und SHA-256 kommen aus der Datei, die Datei wird beim Schreiben gestreamt (`ZipArchive::addFile`, ohne erneute Kompression). Lektionen und Fragen tragen `media[] {media, role, sort}` nur, wenn Medien verknüpft sind; die Hashes von Datensätzen ohne Medien bleiben also gleich. Fragen mit Pflichtbild tragen `revision.image_required`. |
| IMP-031 Rechte | Medien folgen derselben Ausschlussregel wie Lektionen und Fragen: Die Auslieferungssperre (`unknown`, `restricted`, `expired`) oder eine fremde Quelle (`foreign`) führen zu einem Eintrag in `export.excluded[]`, und die Verknüpfung entfällt; mit der bestätigten Option „fremde Inhalte einschließen“ kommen sie mit. Immer ausgeschlossen werden Dateitypen außerhalb der V4-Liste bzw. Dateien, deren Inhalt nicht zum Typ passt (`unsupported_type`), und fehlende Dateien (`file_missing`). Fehlt einer Frage ihr Pflichtbild, wird sie mit `media_excluded` ausgeschlossen, weil sie sonst beim Import als `missing_reference` scheitern würde. |
| Größengrenzen | Medien über 200 MB (`MAX_ZIP`, Medien unkomprimiert) oder über 20 000 Einträge: `ccap_export_too_large` bereits beim Sammeln. Zusätzlich prüft `write()` die entpackte Summe gegen `MAX_UNPACKED`, die Eintragszahl und die fertige ZIP-Größe; ist das Paket zu groß, wird die Datei gelöscht. Die Fehlermeldung im Exportformular gibt es in du- und Sie-Form. |
| Blueprints | Je Kurs werden die Regeln mit Status `draft` und `published` (nicht `retired`) zu `blueprints`-Datensätzen: `course`, `version`, `status`, `time_limit_s` (Spalte), `passing_rule` (`min_ratio`, `parts[] {key, modules[], min_correct, of, label?}`, `empty_part_policy?`), `draws[] {module, count, points?}`, `settings {purpose, form_mode?, overrides?}`. Eingefrorene (veröffentlichte) feste Bögen hängen am Blueprint, aus dem sie importiert wurden (Präfix `<blueprint>#`), sonst an der veröffentlichten Regel bzw. dem neuesten Entwurf; Schlüssel ist die Bogennummer. Regeln oder Bögen, die nicht importierbar wären (Modul/Frage nicht im Paket, ungültige Bestehensregel, `ImportBlueprints::record_error`), werden weggelassen und in `report.warnings[]` genannt. |
| Schema 1.1 | `PackageValidator::SCHEMA_MINOR = 1`. Additive Felder: `draws[].points`, `parts[].label`, `empty_part_policy`, `settings.form_mode`, `settings.overrides` (validiert über `EffectiveSettings::validate_override(…, 'exam_rule')`), leeres `draws` bei vorhandenen `fixed_variants` und gespeicherte Medienrollen (`image`, `video`, `subtitle`, `poster`, `download`), die `ImportMedia::role()` unverändert übernimmt. Die 1.0-Rollen behalten ihre Zuordnung. `PackageExporter::schema_minor()` setzt 1.1.0 nur, wenn ein Datensatz diese Felder nutzt, sonst bleibt es bei 1.0.0, damit ältere Websites solche Pakete weiter importieren können. |
| Import | `ImportBlueprints` liest die Felder aus 1.1 (Punkte je Ziehung über `set_blueprint`, Formmodus über `set_form_mode`, Overrides über `create`; Schlüssel, die auf der Zielsite gesperrt sind, werden verworfen, denn dort gilt die Sperre ohnehin, E-225). Label und Leerteil-Regel stehen in der gespeicherten Bestehensregel. Der Medienimport war vorhanden (MED-Wiederholung, V3/V4). |
| Kein UID-Abgleich | Die Zuordnung beim Reimport läuft nur über `ccap_source_map` (unverändert). Der Test leert die Site und prüft, dass Lektion und Kurs neu angelegt werden. Die frühere „Frage an die Leitung“ im IMP-030-Test ist damit durch E-382 beantwortet. |

## Tests

- `tests/integration/ImportCatalogGaps2Test.php::test_IMP_030_round_trip` (erweitert, Gruppen IMP-030 und E-382). Der Kurs hat Lektionsbild, Vorschaubild, Untertitel, ein lizenziertes PDF an einer Frage, ein gesperrtes Bild (`restricted`) und eine `.txt`-Datei (`unsupported_type`), dazu eine veröffentlichte Prüfungsregel mit Teil-Label, 2 Punkten je Frage, `form_mode = variants`, `exams.max_attempts = 3` und einem eingefrorenen Bogen. Ablauf: Export P1 (gültig, 1.1.0, 4 Mediendateien), Site leeren, Import (alle sechs Entitäten `create`; Rollen, Regel, Overrides, Punkte und Bogen geprüft), Export P2. Ergebnis: gleiche Datensätze, gleiche `content_sha256`, gleiche `package_id` und Dateiliste; ein Reimport von P2 plant nur `unchanged`.
- `test_IMP_030_schema_minor_and_limits`: Ohne Zusatzfelder wird 1.0.0 geschrieben, mit Rolle `poster` 1.1. Die Rollenzuordnung bleibt für 1.0 gleich und übernimmt in 1.1 die gespeicherte Rolle. Ein zu großes Paket ergibt `ccap_export_too_large`, und es bleibt keine Datei zurück.

## Offen / Grenzen

- Medien an Kurs und Modul (`object_type` `course`/`module`, im Format `cover_media`) werden nicht exportiert, weil der Import sie nicht verknüpft. `caption_override`/`alt_override` der Verknüpfung kennt das Format nicht.
- Lösungsmedien (Rolle `solution`, E-376/MED-005) gibt es im Code noch nicht. Sobald `MediaLibrary::ROLES` sie enthält, exportiert und importiert der Export sie unverändert als 1.1-Rolle.
- Bögen im Entwurf werden nicht exportiert (nur eingefrorene). Nicht im Format: Regel-Einstellungen außerhalb von `purpose`/`form_mode`/`overrides` (`grace_seconds`, `open_text_in_score`, `competency_validity_days`, `certifies_objectives`), `valid_from/to` (im Code ungenutzt) und Overrides von Regeln aus der Zeit vor R1-33 (ohne `_format`).
- Bilder ohne Alternativtext gehen als `decorative: true` hinaus und erzeugen keine Paketwarnung.
- Bei einer Verknüpfung `attachment` an einer Untertiteldatei wird beim Import nach 1.0-Regel `subtitle` daraus.
- Fragenrechte (`revision.rights`) exportiert der Export weiterhin nicht. Das liegt außerhalb dieser Aufgabe.

## Gates (25.11.2026, DB `ccap_test_g`)

- `phpcs -q`: 0 Befunde · `phpstan analyse` (aus anderem Arbeitsverzeichnis mit `-c phpstan.neon.dist`, weil `vendor/` geteilt ist): keine Fehler
- Unit: 175 Tests, 3327 Zusicherungen grün
- Integration: 706 Tests, davon 2 rot und einzeln grün: GM-118 (Datei im geteilten Uploads-Ordner `vendor/roots/…`, den parallele Läufe anderer Worktrees teilen) und MIG-003 (DDL-Zählung). Die betroffenen Klassen und alle berührten Tests (ImportCatalogGaps2, RightsDelivery, ApiContract, AuthorUiF8) laufen einzeln grün.
- Multisite: 719 Tests grün (3 übersprungen)
- free-guard 0 · textdomain-lint 0 · `check-address` 0 · `settings-doc --check` aktuell · ApiContractTest grün (keine Routen oder Hooks geändert)
- Neue msgids: 3 (eine neu, zwei geänderte Texte im Exportformular), in du- und Sie-Form übersetzt
