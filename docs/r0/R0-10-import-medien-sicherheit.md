# R0-10 · Content-Import, Paketformat und Medien-Sicherheit

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Es gibt keine Implementierung, keine Schemadateien und keinen Code.
**Stand:** 16.11.2026
**Bezug:** TODO R0-10 (DoD: Paketformat, Rechte, Dry-run, Revisionen, Rollback, Protected Files, Consent), Zuarbeit für R0-38, R1-07, R1-14, R1-15, R1-41; Konzept Kap. 9.3, 9.4, 20, 26, 47 (Zeile `ImportService.php`), LB-04/06/09/13 (Kap. 38-Tabelle); `docs/r0/R0-06-datenmodell-adr.md` (§3.1, §4.1, §4.7, §5, §6.5); `docs/r0/R0-02-inventar/C-infrastruktur-content.md` (§2.4, §3.5, §5.2, §5.3); `docs/r0/R0-02-inventar/E-theme-tools-content-docs.md` (§2.1, §3, INV-E-059)
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77` (nur gelesen). Pfade relativ zu `bootspruefung-learning/`, sofern nicht `schema/`, `tools/` oder `themes/` vorangestellt ist. LB-Belege sind **Verhaltensreferenz**, keine Code-Vorlage (E-01).

### Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). In dieses Dokument eingearbeitet:

| Beschluss | Wirkung hier |
|---|---|
| **E-01** Clean Room | Kein Code aus LB. Wo LB-Klassen genannt sind (`SchemaValidator`, `ImportService`, `MediaStorage`, `ProtectedFiles` …), wird nur das beschriebene Verhalten nach dieser Spezifikation neu implementiert; „wie LB“ heißt „gleiches Verhalten“ |
| **E-03** | LB-Pakete nie im Core, höchstens separates Migrationswerkzeug (§1.7, IMP-004) |
| **E-18** | Rechtestatus-Sperre in R1 Free, nur für importierte/fremde Inhalte (§2.2) |
| **E-20**, E-95, E-147, E-268, E-269 | Erklärungstexte (`feedback_correct`, `feedback_incorrect`, `explanation_html`) werden auch in Free importiert, gespeichert und exportiert, aber nur mit Pro (`course.explanations`) ausgeliefert; Free zeigt richtig/falsch und nach Einstellung die richtige Antwort (§1.3, §4.3) |
| **E-27** | Zugang je Modul statt Scopes: Medienprüfung über den effektiven Grant des Moduls bzw. Kurses (`module_id = 0` = ganzer Kurs, E-286) (§4.3, MED-002/003) |
| **E-45/E-45a**, E-246 | Externe Medien: Einstellung `privacy.external_media_mode`, **Default „immer laden“**, umschaltbar auf „Klick zum Laden“; Consent-Adapter und Kategorie wirken nur im Modus „Klick zum Laden“; Offenlegung der Dienste in der readme und Hinweis im Setup (§5). Das Datenschutzrisiko ist als bewusst akzeptiertes Betreiberrisiko in R0-17 dokumentiert |
| **E-46**, E-152 | Medienroute fail-open (Lernen); Import/Export fail-closed (§3.1, §4.3) |
| **E-62** (R10-D1) | `own` aus fremdem Paket wird als `licensed` gespeichert (§2.2) |
| **E-63** (R10-D2) | Reimport: lokal gewinnt, Wahl je Objekt im Dry-run (§3.7) |
| **E-64** (R10-D3) | Keine Paketsignaturen in R1, `signatures[]` reserviert (§1.2) |
| **E-65** (R10-D4) | Zugangsgebundene Medien standardmäßig geschützt über PHP (§4.1, §4.3) |
| **E-66** (R10-D5) | Fremd lizenzierte Inhalte im Export nur per Opt-in (§6.1) |
| **E-168** | Text-/Dateiaufgaben mit Bewertung in R1: Upload-Sicherheit für Lernenden-Abgaben (§4.5) |
| **E-288** | Abgaben bewerten und abrufen dürfen Trainer, Autor und Kursmanager des Kurses (§4.5) |
| **E-289** | Abgabe-Allowlist pdf, docx, odt, txt, jpg, png; max. 20 MB je Datei, 5 Dateien je Abgabe (änderbar); kein eingebauter Virenscan, Hook für Scanner-Plugins; Aufbewahrung wie Rohantworten (E-51) (§4.5, MED-033/034) |
| **E-312** | Widerspruch E-45a ↔ Konzept Kap. 43.3 („Consent vor Abruf“): **E-45a gilt**; §5 bleibt wie beschlossen, Kap. 43.3 wird redaktionell angepasst |
| **E-176…E-181** | Großimport-Regeln (Objektfehler, Hintergrund, Ablageordner, Fehlerschwelle, Wiederaufnahme) nach R0-38 |
| **E-207** | Präfix `ccap_` für CPTs, Caps, Rollen, Cron, Meta und Tabellen; Optionen `course_captain_*`. Alle Bezeichner in diesem Dokument sind umgestellt |
| **E-02, E-05, E-14, E-21, E-23, E-44** | unverändert genutzt (synthetische Fixtures, Cookie+Nonce, einfacher Drip, Karteikarten, Prüfungsstatus, Fristen) |

---

## 0. Kurzfassung

1. **Neues, neutrales Paketformat `cc-package` v1** (ZIP mit `manifest.json`, `data/<entität>/NNNNNN.json`, `media/…`). Übernommen werden (als Verhalten, E-01) die bewährten LB-Prinzipien: stabile `source_id`, Datensatz-Hash `sha256:`, Prüfsumme und Größe je Datei im Manifest, Validierungsbericht, Minor-Versionen additiv. Gestrichen werden alle Fachentitäten, `boatingschool-…`-IDs, `catalogs`, `official_number` und Laravel-Pfade. Neu sind: Rechte als Objekt je Datensatz, `requires` (benötigte Fragetypen/Provider) und geteilte Entitätsdateien für Großimporte.
2. **Rechte je Inhalt und Medium:** sechs Status (`own`, `licensed`, `public`, `review`, `unknown`, `restricted`). Die **Auslieferungssperre** (`unknown`, `restricted`) greift nur für **nicht eigene** Inhalte (E-18). Lokal erstellte Inhalte sind standardmäßig `own` und werden nie durch den Rechtestatus gesperrt.
3. **Importablauf** wie LB `ImportService`, belegter Zustandsautomat `uploaded → validated|failed → previewed → approved → importing → published` (+ `discarded`, „abgebrochen“). Course Captain ergänzt eine **Aktivierungsphase**: Lernende sehen bis zur Aktivierung den alten Stand (LB zeigt bei Abbruch einen Mischstand).
4. **Geschützte Dateien** (E-65) liegen in einem privaten Ordner (bevorzugt außerhalb des Webroots), werden nur über eine PHP-Route mit Rechteprüfung, Range-Unterstützung und optional X-Sendfile/X-Accel-Redirect ausgeliefert. Ein Selbsttest im Health-Check erkennt Fehlkonfiguration, z. B. Nginx ohne Sperrregel. **Delta zu LB:** LB legt Paketmedien in einen **öffentlichen** Ordner. Course Captain legt zugangsgebundene Paketmedien standardmäßig geschützt ab. Dasselbe gilt für Datei-Abgaben in Aufgaben (E-168, §4.5).
5. **Externe Medien** werden nach Beschluss E-45/E-45a standardmäßig **direkt geladen** (`privacy.external_media_mode = always_load`). Der Betreiber kann auf „Klick zum Laden“ umstellen; dann lädt ein Medium erst nach Klick oder vorhandener Einwilligung über einen Adapter (WP Consent API, Filter, JS-Ereignis). Course Captain hängt an **keinem** Theme-Global, LB dagegen an `window.kwConsent`. Die genutzten externen Dienste werden in der readme offengelegt; der Setup-Assistent weist auf die Umschaltung hin.
6. **Export** eines Kurses als `cc-package` v1 ohne Lerndaten. Fremd lizenzierte Inhalte sind standardmäßig ausgeschlossen. Testfälle IMP-001…IMP-032, MED-001…MED-034 (MED-027…030, MED-033/034: Datei-Abgaben, E-168, E-289).

---

## 1. Paketformat `cc-package` v1

### 1.1 Aufbau

```text
<package_id>.zip
  manifest.json                     Pflicht, oberste Ebene
  data/
    courses/000001.json             JSON-Array von Datensätzen, je Datei ≤ 5 000 Datensätze und ≤ 16 MB (SOLL ≤ 4 MB, R0-38)
    modules/000001.json
    lessons/000001.json
    questions/000001.json …         (100 000 Fragen → ≥ 20 Dateien)
    media/000001.json               Metadaten der Medien
    blueprints/000001.json          Prüfungs-Blueprints inkl. fester Varianten
    flashcards/000001.json
  media/<pfad>/<datei>.<ext>        Binärdateien; Pfad = media[].path
```

- **Delta zu LB:** LB erlaubt nur `data/[a-z_]+\.json` (`Package.php:164`), also eine Datei je Entität. Bei 100 000 Fragen (R0-06 A-01) wird `questions.json` zu groß für `json_decode` im Speicher. Course Captain teilt deshalb auf; der Reader lädt je Batch genau eine Datei (R0-38).
- Erlaubte Einträge: `manifest.json`, `data/<entität>/<6 Ziffern>.json`, `media/…`. Alles andere lehnt die Prüfung ab (LB `entryError`, `Package.php:158-186`). Systemdateien (`.DS_Store`, `Thumbs.db`, `__MACOSX/`) werden still übersprungen (LB `isJunk`, `:194`).
- **Keine ausführbaren Inhalte:** kein PHP, JS, HTML-Datei oder SVG im Paket. HTML gibt es nur als Feldinhalt (Lektion, Frage), der beim Import gefiltert wird (§1.4). Fremden Code nie ausführen (Konzept 9.3; Prinzip aus `tools/content-build/src/SourceLiterals.php`, INV-E-031).

### 1.2 Manifest

| Feld | Pflicht | Inhalt | LB-Referenz (`schema/v1/manifest.schema.json`) |
|---|---|---|---|
| `format` | ja | Konstante `"cc-package"` | – (neu; unterscheidet sofort vom LB-Format) |
| `schema_version` | ja | `1.MINOR.PATCH` | gleich (`^1\.[0-9]+\.[0-9]+$`) |
| `package_id` | ja | `^[a-z0-9][a-z0-9-]{1,40}-[0-9a-f]{12}$`: Herausgeber-Slug + die ersten 12 Hex-Zeichen von `content_sha256`. Gleicher Inhalt ergibt dieselbe ID | `^boatingschool-<commit7>-<content12>$` (fachgebunden) |
| `title`, `description` | `title` ja | Anzeige im Importdialog | – |
| `created_at` | ja | UTC, RFC 3339 | `built_at` |
| `generator` | ja | `{name, version}` des Werkzeugs (Course-Captain-Export, SDK, Fremdwerkzeug) | `converter_version` |
| `source` | ja | **Provenienz:** `{system, revision, repository?, commit_sha?, exported_from_site?}`. `system` ist ein stabiler Slug (`^[a-z0-9][a-z0-9._-]{1,31}$`) und wird zu `source_system` in `ccap_source_map` bzw. SRC (R0-06 §4.0). Git ist optional | `source{repository, branch, commit_sha (40 hex, Pflicht), commit_date}` |
| `publisher` | nein | `{name, url}`. Organisation, **keine** personenbezogenen Kontaktdaten erforderlich | – |
| `previous_package_id` | ja (`null` erlaubt) | Vorgänger für Diff/Warnung `source_removed` | gleich |
| `languages` | ja | BCP-47-Codes | gleich |
| `requires` | ja | `{core: ">=1.0", question_types: [...], activity_providers: [...]}`: Ist ein Typ nicht verfügbar (z. B. Pro-Typ ohne Pro), bricht die Validierung ab (IMP-021) | – (LB kennt feste Typen) |
| `default_rights` | nein | Rechteobjekt (§2.1) für Kurs/Modul/Blueprint ohne eigenes `rights` | – |
| `counts` | ja | Datensätze je Entität | gleich |
| `content_sha256` | ja | SHA-256 über die nach `path` sortierte Liste `path\tsha256\n` aller `files` | gleich (Berechnung in `PackageWriter.php`; Formel in CC festschreiben) |
| `files[]` | ja | `{path, size, mime_type, sha256}` **jeder** Datei außer `manifest.json` | gleich |
| `report` | ja | `{warnings[], skipped[], not_converted[]}` des erzeugenden Werkzeugs (`code`, `entity`, `source_id`, `message` bzw. `source_file`, `line`, `reason`) | flach: `warnings`, `skipped`, `not_converted` |
| `export` | nur bei Export | `{course_ids[], included_foreign: bool, excluded[]: {entity, source_id, reason}}` | – |
| `signatures[]` | nein | **reserviert**, in v1 nicht ausgewertet (E-64) | – |

Nicht übernommen: `catalogs[]` (amtliche Fragenkataloge), `$id` `schema.learnboating.invalid`, Pflicht-Commit.

### 1.3 Entitätsdateien

**Gemeinsame Felder** aller Datensätze: `source_id` (stabil über Neubauten, 1–191 Zeichen, eindeutig je Entität im Paket), `hash` (`sha256:` + 64 hex über den kanonischen Datensatz ohne `hash`), `language`, `editorial_status` (`draft/review/approved/published/deprecated`, LB `common.schema.json`), bei inhaltstragenden Entitäten `rights` (§2.1). Verweise laufen **nur** über `source_id`, nie über Datenbank-IDs.

| Datei | Zielspeicher (R0-06) | Fachneutrale Felder (Auszug) | LB-Referenz | Gestrichen gegenüber LB |
|---|---|---|---|---|
| `courses` | CPT `ccap_course` + `ccap_source_map` | `title`, `slug`, `summary`, `description_html`, `cover_media`, `modules[] {module, sort, required}`, `settings {feedback_visibility (E-147), simple_drip (E-14)}` | `courses.schema.json` | `code`-Muster `^[A-Z0-9-]+$` als Pflicht-ID, `course_type full/combo/addon` |
| `modules` | CPT `ccap_module` + `ccap_course_module` | `title`, `summary`, `lessons[] {lesson, sort, required}`, `question_pool[] {question, sort, required}`, `unlock {after_days?}` | `modules.schema.json` (+ Videokapitel aus `video_modules`) | getrennte Frage-/Videomodule (INV-C-041) |
| `lessons` | CPT `ccap_lesson` | `title`, `body_html`, `media[] {media, role (primary/inline/attachment/caption), sort}`, `external_media? {provider, url, title, duration_s, captions_media?}`, `steps[] {media, caption}`, `quiz[] {question}`, `duration_s` | `video_modules.schema.json` (Lektion, Schritte, Quiz) | `group_label`, Kurscodes, `is_placeholder` (wird `editorial_status=draft`) |
| `questions` | `ccap_question` + `ccap_question_revision`, `ccap_answer`, `ccap_question_tag`, `ccap_content_media` | `reference_no?`, `revision {question_type (Provider-Key), text_html, settings, feedback_correct, feedback_incorrect, image_required, valid_from, valid_to, rights}`, `answers[] {key, text_html, correct, sort}`, `tags[] {type, key, label}`, `media[]` | `questions.schema.json` | `official_number`, `content_role`, `source.catalog/page`, `chart_task`, `MODEL`-Antwortschlüssel, feste `topic/competency/smart_category`-Spalten (werden `tags`, R0-06 E-10) |
| `media` | `ccap_media` (+ Datei in Ablage, §4) | `path` (`^media/[^.]…`, kein `..`), `media_type` (`image/audio/video/document/captions`), `mime_type`, `size`, `sha256`, `alt_text`, `caption`, `description`, `access` (`public/protected`, §4.1), `rights` | `media.schema.json` | `vector_source` (Seekarten-Bezug; R0-06 §4.1 verweist hierher: **entfällt**), ID-Präfixe `elwis/`, `praxistrainer/` |
| `blueprints` | `ccap_exam_rule`, `ccap_exam_variant` | `title`, `version`, `valid_from/to`, `time_limit_s`, `passing_rule {min_ratio?, parts[] {key, min_correct, of}}`, `draws[] {module? / tag?, count}`, `fixed_variants[] {key, questions[]}`, `status` (`draft/published`, E-23) | `exam_rules.schema.json`, `exam_papers.schema.json` | `verified`, amtliche Werte, Bogen-ID `<Kurs>#<Nr>`; **kein** Default-Schwellenwert (Kap. 26.4, INV-C-132) |
| `flashcards` | `ccap_question_revision` mit `question_type=flashcard` (R0-06 §7, E-21) | `front_html`, `back_html`, `explanation_html`, `media[]`, `mode` (`self_assessment/streak_3`), `tags[]`, `rights` | `praxis_tasks.schema.json` | Kategorie, `exam_reference` |

**Erklärungstexte (E-20):** `feedback_correct`, `feedback_incorrect` (Fragen) und `explanation_html` (Karteikarten) sind Teil des Formats und werden in Free vollständig importiert, gespeichert und exportiert (kein Datenverlust bei Pro-Ende). Ausgeliefert werden sie nur mit Pro (`course.explanations`, E-95); nach Lizenzende eingefroren weiter (E-268). Der Free-Editor blendet das Feld aus, vorhandene Texte bleiben (E-269).

**Nie im Paket:** Lernende, Abgaben zu Aufgaben (§4.5), Grants, Gruppen, Bestellungen, Versuche, Fortschritt, Zertifikate, Audit, Einstellungen außerhalb des Kurses (Konzept 20; Exporter/Eraser bleiben getrennt).

### 1.4 Übergreifende Regeln

| Regel | Festlegung | Beleg/Herkunft |
|---|---|---|
| Kanonisches JSON | UTF-8, Unicode NFC, getrimmte Texte; Serialisierung nach RFC 8785 (JCS); `hash` wird ohne das Feld `hash` gebildet | LB `tools/content-build/src/Json.php`, `Text.php` (INV-E-026/029). Gleichheit mit LB-Hashes wird nicht gefordert |
| Gleicher Hash = unverändert | Import-Aktion `unchanged`, keine Revision | LB `EntityImporter::action` (`:662`, INV-C-116) |
| HTML-Felder | Beim Import über die Allowlist von `wp_kses_post` ohne `iframe`, `script`, `style`-Attribute, `on*`-Attribute, `javascript:`/`data:`-URLs. Eingebettete Fremdinhalte nur über `external_media` (§5) | Konzept 9.4/20 (XSS bei Import) |
| URLs im Paket | nur `https:`. Externe Medien nur von Hosts eines registrierten Providers (§5.1) oder aus einer Betreiber-Allowlist. Der Server ruft beim Import **keine** URL aus Datensätzen ab (SSRF) | Konzept 9.4; LB `video_url` nur https (`video_modules.schema.json`) |
| Referenzprüfung | jede `source_id`-Referenz muss im Paket **oder** über `ccap_source_map` mit demselben `source.system` in der Site existieren (Teilpakete). Fehler sind Objektfehler (E-176): das betroffene Objekt und Abhängige werden markiert, der Rest läuft weiter; die Bildschirmliste zeigt höchstens 50, der vollständige Report liegt in `ccap_import_issue`; Abbruch erst ab der Fehlerschwelle max(100, 2 %) (E-180, R0-38) | LB `referenceErrors` (`Package.php:387`), nur paketintern |
| Fehlende Entität | eine in einer älteren Minor-Version fehlende Entität gilt als leer, **nicht** als „alles archivieren“ | LB `schema/README.md`, `Package::SINCE` (`:19`), INV-E-020 |
| Archivierung | im Paket fehlende, früher aus derselben `source.system` importierte Datensätze werden `archived`, nie gelöscht. Abschaltbar je Import („Teilpaket“) | LB `archiveMissing` (`EntityImporter.php:98`, INV-C-108) |

### 1.5 JSON-Schema-Skizze (2020-12, Teilmenge wie beim LB-`SchemaValidator`)

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "urn:course-captain:package:v1:manifest",
  "type": "object",
  "additionalProperties": false,
  "required": ["format","schema_version","package_id","title","created_at","generator","source",
               "previous_package_id","languages","requires","counts","content_sha256","files","report"],
  "properties": {
    "format": {"const": "cc-package"},
    "schema_version": {"type": "string", "pattern": "^1\\.[0-9]+\\.[0-9]+$"},
    "package_id": {"type": "string", "pattern": "^[a-z0-9][a-z0-9-]{1,40}-[0-9a-f]{12}$"},
    "source": {"type": "object", "additionalProperties": false, "required": ["system","revision"],
      "properties": {
        "system": {"type": "string", "pattern": "^[a-z0-9][a-z0-9._-]{1,31}$"},
        "revision": {"type": "string", "maxLength": 64},
        "repository": {"type": ["string","null"]},
        "commit_sha": {"type": ["string","null"], "pattern": "^[0-9a-f]{40}$"},
        "exported_from_site": {"type": ["string","null"], "format": "uuid"}}},
    "requires": {"type": "object", "required": ["core","question_types"],
      "properties": {"core": {"type": "string"},
        "question_types": {"type": "array", "items": {"type": "string", "pattern": "^[a-z0-9_.-]+$"}},
        "activity_providers": {"type": "array", "items": {"type": "string"}}}},
    "files": {"type": "array", "items": {"type": "object", "additionalProperties": false,
      "required": ["path","size","mime_type","sha256"],
      "properties": {
        "path": {"type": "string", "pattern": "^(data/[a-z_]+/[0-9]{6}\\.json|media/[^./][^\\\\]*)$",
                 "not": {"pattern": "(^|/)\\.\\.?(/|$)"}},
        "size": {"type": "integer", "minimum": 0},
        "mime_type": {"type": "string", "pattern": "^[a-z]+/[a-z0-9.+-]+$"},
        "sha256": {"$ref": "common.schema.json#/$defs/sha256"}}}}
  }
}
```

```json
{
  "$id": "urn:course-captain:package:v1:common",
  "$defs": {
    "sha256": {"type": "string", "pattern": "^[0-9a-f]{64}$"},
    "hash": {"type": "string", "pattern": "^sha256:[0-9a-f]{64}$"},
    "sourceId": {"type": "string", "minLength": 1, "maxLength": 191, "pattern": "^[A-Za-z0-9][A-Za-z0-9._:/-]*$"},
    "rightsStatus": {"enum": ["own","licensed","public","review","unknown","restricted"]},
    "rights": {"type": "object", "additionalProperties": false, "required": ["status"],
      "properties": {
        "status": {"$ref": "#/$defs/rightsStatus"},
        "owner": {"type": ["string","null"], "maxLength": 255},
        "license": {"type": ["string","null"], "maxLength": 64},
        "license_url": {"type": ["string","null"], "pattern": "^https://"},
        "attribution": {"type": ["string","null"]},
        "source_url": {"type": ["string","null"], "pattern": "^https://"},
        "valid_until": {"type": ["string","null"], "format": "date"},
        "note": {"type": ["string","null"]}}}
  }
}
```

`data/media/*.json` (Auszug): `required: [source_id, hash, path, media_type, mime_type, size, sha256, alt_text, access, rights]`; `if media_type = image then alt_text` darf `""` nur mit `decorative: true` sein (Barrierefreiheit, R0-15).

Die Schemas liegen in **einer** Quelle im Repository; das Plugin enthält eine Kopie, deren Gleichheit die CI prüft (LB-Muster INV-E-019). Der Validator wird nach dem Verhalten des LB-`SchemaValidator` neu implementiert (E-01; 2020-12-Teilmenge, `$ref` über Dateien, unbekannte Schlüsselwörter brechen ab; INV-C-091), in eigenem Namespace mit eigenem Unit-Test.

### 1.6 Versionierung

| Ebene | Regel |
|---|---|
| `schema_version` MAJOR | Der Importer akzeptiert nur Major 1 (LB `Package::validate`, `:299`). Major 2 braucht einen eigenen Leser und einen Konverter 1→2 |
| MINOR | nur additiv: neue optionale Felder, neue Entitätsdateien. Ein Importer der Version 1.n liest jedes Paket 1.m mit m ≤ n. Ist m > n, bricht die Validierung ab und meldet „Plugin aktualisieren“. Ein unbekanntes Feld ist ein Fehler (`additionalProperties: false`, wie LB) |
| PATCH | nur Korrekturen an Beschreibung oder Mustern, ohne Bedeutungsänderung |
| Werkzeugversion | `generator.version`, unabhängig von `schema_version` |
| Pluginseitig | `format_version` in `ccap_course_revision` und `schema_version` in `ccap_import_batch` (R0-06 §4.1/§4.7). Core-, DB-, Paket- und REST-Version werden getrennt geführt (Konzept 22.2) |

### 1.7 Abgrenzung zum LB-Format (E-03)

| Punkt | LB-Format v1 (`schema/v1`, `boatingschool-*`) | `cc-package` v1 |
|---|---|---|
| Erkennung | kein `format`-Feld, `$id` `schema.learnboating.invalid` | `format: "cc-package"`, URN-`$id` |
| Entitäten | 15, davon 7 fachgebunden (Navigation, Funk, Gerätesimulation, Prüfungsausschüsse, -anleitungen; INV-C-094, INV-E-012…018) | 7 neutrale (§1.3) |
| Provenienz | Git-Commit Pflicht | `source.system` + `revision`, Git optional |
| Rechte | flache Felder `rights_owner`, `license_reference`, `rights_status` (5 Werte) | Objekt `rights` mit `own` und Lizenz-URL |
| Dateien | eine JSON-Datei je Entität | geteilte Dateien je Entität |
| Typen | feste Fragetypen inkl. `chart_task` | Provider-Key und `requires` |
| Import im Core | – | **nur** `cc-package`. Erkennt der Importer ein LB-Manifest (`package_id` `^boatingschool-`), meldet er „Fremdformat, bitte Migrationswerkzeug verwenden“ (IMP-004). Ein Migrationswerkzeug LB→`cc-package` wäre ein separates Werkzeug außerhalb des WordPress.org-ZIPs und ist **nicht** Teil von R1 |

---

## 2. Rechte- und Lizenzmodell

### 2.1 Felder je Inhalt und Medium

Rechte sind Teil der **Revision** bzw. des Mediums. Damit erzeugt eine Rechteänderung im Paket einen neuen Hash und eine nachvollziehbare Revision:

| Objekt | Speicher (R0-06) | Felder |
|---|---|---|
| Frage, Karteikarte | `ccap_question_revision.rights_status`, `rights_owner`, `license_reference` (JSON: `license`, `license_url`, `attribution`, `source_url`, `valid_until`, `note`) | §1.5 `rights` |
| Medium | `ccap_media.rights_status`, `rights_owner`, `license_reference`, `source_url` | §1.5 `rights` |
| Lektion, Kurs, Modul | Post-Meta `_ccap_rights` (JSON) + `ccap_source_map` | §1.5 `rights` (Kurs/Modul optional, Rückfall `default_rights`) |
| Lokaler Override | `ccap_override (object_type, object_id, field='rights_status')`, Pflichtbegründung, Audit | LB `Overrides.php:19-112` (INV-C-134) |
| Herkunft | `source_system` / `ccap_source_map.source_system`; `local` = lokal erstellt | R0-06 §4.0 SRC |

### 2.2 Status und Auslieferung

**„Eigen“** heißt: `source_system = 'local'`, oder `source.exported_from_site` ist die Installations-UUID dieser Site (Reimport des eigenen Exports). Alles andere ist **fremd/importiert**. Lokal hochgeladene Medien sind standardmäßig `own`. Die Redaktion kann sie auf einen anderen Status setzen, z. B. wenn es ein Fremdbild ist. Ab dann gelten sie als fremd.

| Status | Bedeutung | Eigen: Auslieferung | Fremd: Auslieferung | Publish-Validator (R0-22) |
|---|---|---|---|---|
| `own` | Rechte beim Betreiber | ja | – (im Import wird `own` als `licensed` mit `owner` = Herausgeber gespeichert, Hinweis im Dry-run; E-62) | ok |
| `licensed` | Lizenz liegt vor | ja | ja | ok; Attribution anzeigen, falls gesetzt |
| `public` | gemeinfrei/freie Lizenz | ja | ja | ok; Attribution Pflicht, wenn die Lizenz sie verlangt |
| `review` | in Prüfung | ja | ja (wie LB: nicht sperrend) | Warnung |
| `unknown` | ungeklärt | ja (E-18: keine Sperre für eigene) | **gesperrt** | Warnung bzw. Blocker „n Inhalte gesperrt“ |
| `restricted` | darf nicht gezeigt werden | ja, nur mit Warnung im Editor | **gesperrt** | Blocker für fremde, Warnung für eigene |

- **Sperrwirkung** gilt für jede Auslieferung an Lernende: REST-Lernrouten, Block, Shortcode, Medienroute (§4.4), Prüfungsauswahl und Export (§6). Admin-Vorschau, Import-Dry-run und Redaktion sind ausgenommen. Die Sperrliste entspricht LB `Overrides::BLOCKING_RIGHTS = ['unknown','restricted']` (`Overrides.php:34`); die Ausnahme für eigene Inhalte ist neu (E-18).
- **Abhängigkeiten** (wie LB `deliverableQuestionSql`, `ContentRepository.php:88-104`): Eine Frage mit `image_required` ist gesperrt, wenn kein freigegebenes Pflichtmedium existiert. Eine Lektion bleibt sichtbar; an der Stelle eines gesperrten Mediums steht ein neutraler Platzhalter („Medium nicht verfügbar“), es gibt keinen Fehler und keinen Dateipfad.
- **`valid_until`** abgelaufen → wie `restricted` für fremde Inhalte. Neu gegenüber LB; dort gibt es Gültigkeit nur für Fragen.
- **Sammelfreigabe:** „alle importierten `unknown` dieses Pakets auf `licensed`“ als Override mit Pflichtbegründung, Dry-run und Audit. Das ist LB `RightsRelease::releaseAll` (`RightsRelease.php:39`, INV-C-135) ohne Seekartenbezug.
- **Rechte-Zusammenfassung** im Dry-run je Entität und Status. Das ist LB `rightsSummary` (`ImportService.php:639-650`) mit registrierter statt fester Entitätsliste.
- **Freigabe** eines Imports verlangt die Bestätigung „Ich habe die Nutzungsrechte für die als fremd markierten Inhalte geprüft“ (Audit). Rechtsberatung wird nicht zugesagt (Konzept 20).

---

## 3. Importablauf

### 3.1 Quellen

| Quelle | Regel | LB-Beleg |
|---|---|---|
| Upload (Admin) | Limit = kleinster Wert aus PHP (`upload_max_filesize`, `post_max_size`), WP (`wp_max_upload_size`) und Paketlimit; verständliche Fehler mit Ausweichweg. Pakete über 200 MB nur über Ablageordner oder WP-CLI mit höheren Grenzen (E-178, Grenzwerte je Quelle R0-38 §6.1) | `ImportSources.php:327-376` (INV-C-120) |
| Ablageordner (SFTP) | `<privat>/packages-<geheim>/import/`; nur Basisname, `realpath` muss direkt im Ordner liegen, keine Links; nach Übernahme löschen | `ImportSources.php:47`, `:216` `resolveDrop` (INV-C-117/118) |
| HTTPS-Link | `wp_safe_remote_get`, nur Port 443, IP-Pinning gegen DNS-Rebinding, keine privaten/link-lokalen Ziele (R0-17 §3), höchstens 3 Weiterleitungen, jede selbst geprüft und nur https, Größenlimit, optional erwartete SHA-256; Datei im privaten Ordner, nach 24 h gelöscht | `ImportSources.php:258` `download` (INV-C-119) |
| WP-CLI | `wp course-captain import <zip|dir> [--dry-run] [--approve] [--resume=<id>]`; gleiche Prüfungen | `ImportService::createFromDirectory` (`:119`), `runAll` (`:555`) |

Capability `ccap_import_content` (R0-07). Ein Import ist nie über eine öffentliche oder nicht authentifizierte Route möglich. Rate-Limits für Import/Export sind fail-closed (E-152).

### 3.2 Validierung (vor jedem Schreibzugriff auf Inhalte)

| Stufe | Prüfung | Default-Grenze (Filter) | LB-Beleg |
|---|---|---|---|
| V1 Archiv | gültiges ZIP, nicht leer, ≤ 20 000 Einträge, ZIP ≤ 200 MB, entpackt ≤ 800 MB | LB-Werte | `Package.php:20-21`, `:87-150` |
| V2 ZIP-Bombe | je Datei > 1 MB: Verhältnis ≤ 200; gesamt > 50 MB: Verhältnis ≤ 50; `comp_size ≤ 0` abgelehnt. **Zusätzlich**: beim Entpacken Bytes zählen und bei Überschreitung abbrechen, weil die Größenangaben im Verzeichnis gefälscht sein können | LB `MAX_RATIO`, `MAX_TOTAL_RATIO` (`:34`, `:37`, `:137`, `:146`); Byte-Zähler ist neu |
| V3 Pfade | kein `/`-Anfang, kein `\`, kein `..`, kein NUL, kein Laufwerksbuchstabe, keine versteckten Segmente; Unix-Symlinks im ZIP-Attribut abgelehnt; nach dem Entpacken erneute Symlink-Suche; Entpacken nur in einen frischen Zufallsordner im privaten Bereich, Aufräumen bei Fehler | `entryError` (`:158`), Symlink (`:127`, `:72-78`), `extract` (`:45`), `createFromZip` (`ImportService.php:86-101`) |
| V4 Medientyp | Endung aus Allowlist; keine gefährliche innere Endung (`x.php.png`); deklarierter MIME passt zur Endung; per `finfo` erkannter MIME passt. Allowlist v1: png, jpg/jpeg, gif, webp, mp3, m4a, mp4, webm, pdf, **vtt** (neu, Untertitel). Nie: svg, html, js, php …; die Sperrliste lässt sich per Filter nicht aufheben | `MediaStorage::ALLOWED_MEDIA` (`:44`), `DANGEROUS` (`:58`), `allowedMedia` (`:206`), `mediaTypeError` (`:225`) |
| V5 Integrität | Manifest-Schema; Major 1; jede Datei vorhanden, Größe und SHA-256 stimmen; keine nicht gelistete Datei; `content_sha256` stimmt (neu) | `Package::validate` (`:285-325`) |
| V6 Inhalt | Schema je Entitätsdatei; doppelte `source_id`; Referenzen (§1.4); `requires` erfüllt; HTML-Filter-Diff (wie viel entfernt wurde → Warnung); `media.access` mit Kursbezug konsistent | `:327-339`, `referenceErrors` (`:387`) |

Ergebnis: Batch `validated` oder `failed`. V1–V5 betreffen das ganze Paket (`failed`); Fehler in V6 sind **Objektfehler** (E-176): das Objekt und seine Abhängigen werden markiert, der Batch bleibt `validated`, solange die Fehlerschwelle (E-180) nicht erreicht ist; Details in `ccap_import_issue` (R0-38). Abgelehnte Medien stehen einzeln im Protokoll (`action=rejected`), Verhalten wie LB `validate` (`ImportService.php:244-266`).

### 3.3 Dry-run-Bericht

Der Dry-run schreibt nichts. Er nutzt denselben Entity-Handler im Modus `dryRun` (LB `new EntityImporter(…, true)`, `ImportService.php:294`) und läuft in Zeitscheiben (LB 20 s, Prüfung alle 50 Datensätze, `:63`, `:304`). Inhalt:

- je Entität `create / update / unchanged / archive / conflict / rejected / error`. `conflict` ist neu (§3.7);
- je Objekt eine Zeile mit `source_id`, Aktion, Fehlercode und Meldung, paginiert und als CSV exportierbar (R0-38, Fehlerklassen dort);
- Rechte-Zusammenfassung und Zahl der Inhalte, die nach dem Import gesperrt wären (§2.2);
- betroffene laufende Prüfungsversuche und Lernende (Zählwerte; Versuche bleiben auf ihren Revisionen, §3.6);
- `requires`, Speicherbedarf (Summe neuer Mediendateien) gegen freien Platz im Zielordner (neu).

Vorschau und Freigabe werden ungültig, sobald danach ein anderer Import veröffentlicht wurde (`supersededBy`, `:222-240`). Eine neue Vorschau setzt `previewed → validated` zurück (`:286-291`).

### 3.4 Zustandsautomat

**Beleg LB** (`ImportService.php:15-22`, Statusliste `:17`; Inventur C §5.2):

```text
uploaded ─validate─▶ validated ─preview*─▶ previewed ─approve─▶ approved ─runStep─▶ importing ─…─▶ published
   └─▶ failed           ▲  (neue Vorschau)  │                                        │
                        └───────────────────┘                     Fehler (error) oder kein Heartbeat > 600 s
                                                                   = „abgebrochen“ (isAborted, :213)
discard: aus jedem Zustand außer published und lebendem importing → discarded (+ stats.aborted_at bei Abbruch, :357-381)
```

Belegte Invarianten: Freigabe nur aus `previewed` (`:345`); `runStep` nur aus `approved/importing` (`:401`); nur ein lebender Import (`:407-412`); Options-Sperre mit Besitzer `<batch>-<zufall>`, TTL 900 s > Stale-Grenze 600 s (`:48-57`, `:699-735`); Zustand nach der Sperre neu lesen (T199, `:416-423`); eine Transaktion je Schritt (`:466`, `:494`, `:496`); Heartbeat vor der Arbeit (`:449`); veröffentlichen setzt `bp_learning_active_package` und schreibt Audit (`:517-526`); Medien bis 64 MB je Schritt (`:60`).

**Course-Captain-Automat** (Statuswerte für `ccap_import_batch.status`):

```text
uploaded → validating → validated | failed
validated → previewing → previewed            (Zeitscheiben; previewing = preview_progress gesetzt)
previewed → approved                           (Rechtebestätigung, Audit)
approved → importing                           (Batches unter Lock, Cursor, Heartbeat)
importing → activating → published             (NEU: Aktivierung, siehe unten)
importing|activating + error/stale → aborted   (NEU als echter Status statt abgeleitet)
aborted → importing (resume) | discarded | Rollback-Import
{uploaded…approved, aborted} → discarded
published → superseded                         (NEU: wenn ein späterer Import derselben source.system aktiviert wird)
```

- **`aborted` als gespeicherter Status** statt nur abgeleitet (LB `isAborted`): Health-Check und Admin-Liste filtern dann per Index. Die Stale-Erkennung bleibt (`heartbeat_at`-Spalte, R0-06 §4.7).
- **Aktivierung (neu):** Die Phasen bis `importing` legen neue Fragenrevisionen und Medien an, **ohne** `ccap_question.current_revision_id` umzustellen und ohne eine neue `ccap_course_revision` zu veröffentlichen. Die Phase `activating` stellt in **einer** Transaktion je Kurs die Zeiger um, veröffentlicht die Kursrevision und setzt das aktive Paket. Lernende sehen bis dahin den alten Stand. Ein Abbruch hinterlässt keinen sichtbaren Mischstand, im Gegensatz zu LB, wo `stats.aborted_at` einen Mischstand markiert (`:370-374`).
- **Offener Punkt (technisch, R0-23/R1-15):** CPT-Lektionen haben keinen Revisionszeiger, der dem bei Fragen entspricht. Zwei Wege sind möglich: a) Lektionsänderungen erst in `activating` schreiben, chunkweise mit Kurs-Wartungshinweis; b) Auslieferung über die in `ccap_course_revision.structure` gespeicherte WP-Revisions-ID. Die Wahl hängt an R0-23 und an `wp_revisions_to_keep`.

### 3.5 Batch-Import (Kurzvertrag; Details R0-38/R1-41)

Phasenreihenfolge über eine Registry: `media → courses → modules → lessons → questions → flashcards → blueprints → module_links → course_links → archive → activate`. Bei LB liegt `media` nach `modules` (`PHASES`, `:27-46`); das hat keine Abhängigkeit, Medien zuerst vereinfacht Referenzen. Je Schritt ≤ 200 Datensätze (Admin) bzw. 500 (CLI) und ≤ 64 MB Medien, mindestens ein Datensatz (LB `limitBytes`, `:538`). Medien werden außerhalb der Transaktion atomar geschrieben (Temp-Datei, SHA-256 prüfen, `rename`). Bei Wiederholung wird die Datei erneut gegen die Prüfsumme geprüft (T203, `MediaStorage::store` `:164-191`, `EntityImporter.php:201-208`). Medien-Dedupe über `ccap_media.sha256` (R0-06 E-13). Ausführung standardmäßig im Hintergrund über Action Scheduler, sonst WP-Cron; Browser-Polling und WP-CLI als Alternativen, mitten im Lauf wechselbar (E-177); automatische Wiederaufnahme nach Vorprüfungen (E-181); nie auf Frontend-Anfragen (Konzept 9.4).

### 3.6 Revisionen und Rollback

| Objekt | Regel | Beleg |
|---|---|---|
| Frage/Karteikarte | neuer Hash → neue Revision, alte wird `deprecated` (erst in `activating`); bekannter Hash → alte Revision wird reaktiviert | `EntityImporter.php:215-286` (INV-C-111), R0-06 §5 |
| Kurs | neue `ccap_course_revision` je Aktivierung, nur wenn sich `structure_hash` ändert | R0-06 §4.1/§5 |
| Medium | neuer `sha256` → neuer `storage_key`; die alte Datei bleibt, solange eine Revision sie referenziert (Aufräumen per Retention-Job) | neu (LB überschreibt am selben Schlüssel, `MediaStorage::store` `:164-191`) |
| Laufende Versuche | bleiben auf `revision_id` und Snapshot, nie Live-Stand | R0-06 §5 Prüfungsversuch-Snapshot |
| **Rollback** | nur auf einen `published`/`superseded`-Stand, Pflichtbegründung. Er legt einen **neuen** Import aus der aufbewahrten Paketkopie an, der den normalen Weg (Validierung, Dry-run, Freigabe) durchläuft. Ergebnis: alte Hashes wieder aktiv, später hinzugekommene Inhalte archiviert, nichts gelöscht | `rollback` (`:139-164`), `restoreActive` (`:383`), INV-C-097 |
| Aufbewahrung Paketkopien | aktives Paket + 2 weitere veröffentlichte; Importe in Arbeit 30 Tage; Zwischen- und Download-Ordner 24 h; Protokoll `unchanged` 14 Tage, sonst 180 Tage (E-44) | `Retention.php:26-30` (INV-C-121) |

Schema-Rollback gibt es nicht. Content-Rollback ist fachlich (R0-06 §6.5).

### 3.7 Konfliktpolitik bei lokalen Änderungen

Konzept 9.3 verlangt eine ausdrückliche Politik. LB schützt lokal geänderte Datensätze nur für eine Fachentität (`locallyModified`, `EntityImporter.php:608`, INV-C-114) und für Overrides (überleben den Reimport, INV-C-134). Beschluss **E-63**:

- `ccap_source_map.locally_modified = 1` (R0-06 §4.1) wird gesetzt, sobald die Redaktion ein importiertes Objekt ändert. Für Tabellen-Entitäten gilt: lokale Revision mit `created_by` ≠ Import.
- Dry-run meldet `conflict`, wenn Quelle **und** lokal geändert sind. Standard: **lokal gewinnt** (Objekt wird übersprungen, Quelländerung protokolliert). In der Freigabe kann die Redaktion je Objekt oder pauschal „Quelle übernehmen“ wählen (Audit).
- Overrides (`rights_status` u. a.) überleben jeden Reimport.

### 3.8 Audit

Ereignisse: `import_uploaded`, `import_validated`/`_validation_failed`, `import_previewed`, `import_approved` (inkl. Rechtebestätigung), `import_started`, `import_aborted`, `import_resumed`, `import_activated`, `import_discarded`, `import_rollback_requested`, `import_rolled_back`, `rights_override_set`, `rights_bulk_release`. Weitere Ereignisse für Großimporte (Resume, Teilwiederholung, Zurückstellen) in R0-38 §5.5. LB-Referenz: `AuditLog::record` an den Stellen `:160`, `:261`, `:354`, `:376`, `:458`, `:526`, `:633`.

---

## 4. Geschützte Dateien

### 4.1 Speicherklassen

| Klasse | Inhalt | Ort (Default) | Direktabruf |
|---|---|---|---|
| `public` | Kursbild, öffentliche Vorschau, Medien mit `access=public` | `uploads/course-captain/media/<key>` | ja (nur erlaubte Typen; Skriptsperre per `.htaccess`) |
| `protected` | zugangsgebundene Medien (Lektionsvideos, PDFs, Bilder in Fragen und Lösungen), geschützte Mediathek-Anhänge | `<privat>/protected-<geheim>/<id>/` | **nein**, nur über §4.4 |
| `packages` | Paketkopien je Batch (enthalten Lösungen), Upload-Zwischenordner, Downloads, Ablageordner `import/`, Exporte | `<privat>/packages-<geheim>/…` | **nein**, auch nicht über PHP (Exporte nur über §6) |

- `<privat>` = Konstante `COURSE_CAPTAIN_PRIVATE_DIR` bzw. Filter `course_captain_private_dir` (absoluter Pfad, **bevorzugt außerhalb des Webroots**), sonst `uploads/course-captain`. Ist ein privater Ordner eingestellt, gilt er immer. Er fällt nie still auf `uploads/` zurück; ein Schreibfehler ist sichtbar und der Health-Check warnt (LB T204, `MediaStorage::privateBaseDir` `:84`, `privateDirProblem` `:98`).
- Ordnernamen enthalten ein Zufallsgeheimnis aus einer Option, das nie ersetzt wird; dazu `index.php`. Fremde `.htaccess` werden nicht überschrieben (LB `:562-610`, INV-C-127). Das Geheimnis ist **Verteidigung in der Tiefe**, kein Ersatz für die Serverregel.
- **Delta zu LB:** LB schreibt alle Paketmedien in den öffentlichen Ordner `uploads/bootspruefung/media/` (`MediaStorage.php:12-15`, `:127-134`, `store` `:164`). Dort ist eine Datei per URL abrufbar, auch wenn die zugehörige Frage durch Rechte oder fehlenden Zugang gesperrt ist; der Rechtestatus wirkt in LB nur auf die Fragenauslieferung (`ContentRepository.php:88-104`). Course Captain legt Paketmedien standardmäßig als `protected` ab (E-65). Medien mit `rights` gesperrt oder `access=protected` liegen **nie** in `public`.
- Mediathek-Anhänge, die geschützt werden, werden **samt aller Bildgrößen/Vorschaubilder** in den geschützten Ordner verschoben; `wp_get_attachment_url` und `get_attached_file` werden umgebogen, die REST-Antwort des Anhangs entschärft. Gelingt das Verschieben nicht, gibt es einen sichtbaren Fehler; der Schutz gilt dann als nicht aktiv (LB `ProtectedFiles::protect` `:191-238`, Filter `:34-41`, `derivedFiles` `:307`).

### 4.2 Webserver-Regeln

| Server | Schutz `packages`/`protected` | Schutz `public` vor Skripten |
|---|---|---|
| Apache 2.4/2.2 | Course Captain schreibt `.htaccess` mit `Require all denied` und `<IfModule !mod_authz_core.c>`-Weiche (LB `HTACCESS_PRIVATE`, `:60`) | `.htaccess` mit `FilesMatch` für php/phtml/phar/html/svg/js … (LB `HTACCESS_MEDIA`, `:62`) |
| Nginx | **`.htaccess` wirkt nicht.** Course Captain zeigt die passende Regel für den tatsächlichen Upload-Pfad im Systemstatus an, z. B. `location ~* ^/wp-content/uploads/course-captain/(packages|protected) { deny all; }`, sowie eine `location` gegen Skripte im Medienordner. Empfehlung: privater Ordner außerhalb des Webroots | LB `SystemStatus::nginxRule` (`:329-337`) |
| LiteSpeed/OpenLiteSpeed | wertet `.htaccess` meist aus; trotzdem Selbsttest | wie Apache |
| IIS/sonstige | keine automatische Regel; nur privater Ordner außerhalb des Webroots oder manuelle Regel, Selbsttest Pflicht | – |
| X-Sendfile / X-Accel-Redirect | optional per Einstellung/Filter: PHP prüft die Rechte und setzt den Header; Nginx braucht dazu eine `internal`-Location. Ohne Konfiguration streamt PHP selbst | LB Filter `bootspruefung_protected_file_sendfile` (`ProtectedFiles.php:391-394`) |

### 4.3 Auslieferung über PHP

Route: `GET /wp-json/course-captain/v1/media/<media_uid>` (REST, `permission_callback`) und ein schlanker Rewrite-Endpunkt `/cc-file/<media_uid>/` für `<video>`/`<img>`/PDF. Beide nutzen denselben Dienst `ProtectedMediaService::serve()`.

1. **Auflösen:** nur über `media_uid` (UUID), nie über Pfad oder Dateiname. Unbekannt oder nicht `protected` → 404 (keine Existenzauskunft).
2. **Rechte:** Wer `ccap_manage_content` für das Objekt hat, darf immer. Sonst gilt: eingeloggt (sonst Login-Redirect bzw. REST 401), effektiver Grant (E-27: je Modul, `module_id` NULL = ganzer Kurs; keine Scopes) für **mindestens ein** veröffentlichtes Modul, das das Medium referenziert (`ccap_content_media` → Lektion/Frage → Modul → Kurs), Drip-Freigabe erfüllt und Rechtestatus nicht gesperrt (§2.2). Medien in Prüfungsfragen sind während eines laufenden Versuchs nur für dessen Inhaber abrufbar. Lösungsmedien erst, wenn die Feedback-Regel es erlaubt (E-147, R0-08); Medien, die nur in Erklärungstexten stehen, nur mit Pro (E-20). LB-Referenz: `serve` (`ProtectedFiles.php:344-372`), `DownloadService::allowed` (`DownloadService.php:55`).
3. **Pfadsicherheit:** `realpath` der Datei muss unter dem geschützten Basisordner liegen; keine Symlinks.
4. **Header:** Ausgabepuffer leeren (T105); `nocache_headers`; `Cache-Control: private, no-store` (bei Bildern optional `private, max-age=300` plus `ETag`, E-65); `X-Content-Type-Options: nosniff`; `Content-Security-Policy: sandbox` außer bei PDF; `Content-Disposition` mit ASCII-Ersatz und `filename*=UTF-8''` (RFC 6266/8187); inline nur für PDF, Rasterbilder, Audio, Video und VTT, alles andere als `application/octet-stream` (LB `stream` `:374-400`, `inlineMime` `:402`, `contentDisposition` `:425`).
5. **Range-Requests (Pflicht für Video/Audio-Seeking und PDF-Viewer):** `Accept-Ranges: bytes`; genau ein Bereich (`bytes=a-b`, `a-`, `-n`) → 206 mit `Content-Range`; nicht erfüllbar → 416 mit `Content-Range: bytes */<size>`; Mehrfachbereiche → ganze Datei mit 200; `If-Range` mit `ETag`/`Last-Modified` korrekt auswerten; Streaming in Blöcken (z. B. 1 MB), `set_time_limit`, Abbruch bei `connection_aborted()`. **Delta zu LB:** LB kann Range nur in der App-API (`Mobile/Controllers/FileController.php:19-22`, `:44-70`, `parseRange` `:84`, `stream` `:118`). Die Web-Route liefert immer die ganze Datei per `readfile` (`ProtectedFiles.php:382`, `:393`). Downloadzählung nur beim Abruf ab Byte 0 (LB `FileController.php:38-42`).
6. **Rate-Limit** je Nutzer und Medium (Schutz vor Massendownload); Zählerausfall → fail-open für Lernmedien (E-46).
7. **Keine Signatur-URLs in R1.** Cookie-Session und Same-Origin genügen für `<video>`/`<img>`. Externe Clients erst mit Token-Verfahren (E-05).

### 4.4 Fehlkonfigurations-Check (Health/Monitor)

| Prüfung | Verfahren | Ergebnis | LB-Beleg |
|---|---|---|---|
| Ordner außerhalb Webroot? | `publicUrl(dir)` = `null` → `private` | good | `SiteHealth::storageState` (`:131-153`), `MediaStorage::publicUrl` (`:147`) |
| Direktabruf gesperrt? | Je Ordner (`packages`, `protected`, `import`) eine harmlose Testdatei mit Zufallstoken anlegen, per `wp_remote_get` (3 s, keine Weiterleitung) abrufen, Datei löschen. 2xx **und** Token im Body → `exposed` (kritisch). Ergebnis 12 h im Cache, bei `unknown` 1 h | ok/exposed/unknown | `SystemStatus::storageSelfTest` (`:216-238`), `probeDir` (`:241-268`) |
| Skriptausführung im `public`-Ordner? | neu: Testdatei `*.txt` mit Endung, die gesperrt sein müsste (z. B. `.html`), abrufen → 403/404 erwartet | ok/exposed | – (LB prüft nur, ob `.htaccess` vorhanden ist, `security-test.php`) |
| Privater Ordner erreichbar/beschreibbar | `privateDirProblem`, letzter Ordnerumzug ohne Fehler | storage = ok/false | `Monitor.php:81-82` |
| Dateien vorhanden | Stichprobe bzw. CLI-Vollprüfung: jede `ccap_media`-Datei vorhanden, Größe/SHA-256 stimmen | Restore-Check | `BackupCheck.php:204-237` (INV-C-063) |

Anzeige in Site Health (`course_captain_storage`: kritisch bei `exposed`, Empfehlung bei `unknown`/`unchecked`) mit der passenden Nginx-Regel, außerdem im Monitor-Endpunkt `GET course-captain/v1/health` als Check `storage` (Details nur mit `manage_options` oder Schlüssel im Header, **nicht** `?key=`, weil der Schlüssel sonst in Server-Logs landet; INV-C-055, Inventur C §4.6). Reparaturaktion „Erneut prüfen“ (R1-38).

### 4.5 Datei-Abgaben in Aufgaben (E-168)

Mit E-168 kommen Text- und Dateiaufgaben mit Bewertung (Workflow `draft → submitted → returned → graded`) in R1. Lernende laden damit erstmals Dateien hoch. Es gelten dieselben Grundsätze wie für Paketmedien, mit diesen Festlegungen:

| Punkt | Regel |
|---|---|
| Weg | nur authentifizierte REST-Route mit Cookie + Nonce (E-05), Upload nur für den eigenen Entwurf (`draft` bzw. `returned`) einer Aufgabe, für die ein effektiver Grant des Moduls besteht (E-27); nach `submitted` keine Änderung mehr |
| Typprüfung | wie V4 (§3.2): Endung aus Allowlist, keine gefährliche innere Endung, deklarierter und per `finfo` erkannter MIME passen zur Endung; svg/html/js/php nie, auch nicht per Filter. Abgabe-Allowlist (global, **E-289**): `pdf`, `docx`, `odt`, `txt`, `jpg`/`jpeg`, `png`; die Allowlist je Aufgabe ist eine Teilmenge davon |
| Größe | Default **20 MB je Datei** und **5 Dateien je Abgabe** (E-289, änderbar); wirksam ist der kleinste Wert aus PHP-/WP-Limit und eingestelltem Limit; verständliche Fehlermeldung |
| Virenscan | kein eingebauter Scanner (E-289); Filter-Hook `course_captain_assignment_file_scan` vor dem Übergang in die geschützte Ablage, Befund → Datei abgelehnt (`ccap_submission_file_rejected`, `reason: scan`) |
| Ablage | Speicherklasse `protected` (§4.1) in eigenem Unterordner je Abgabe, Dateiname nur als Metadatum, Speicherschlüssel zufällig; nie in der WP-Mediathek, nie in `public` |
| Auslieferung | nur über `ProtectedMediaService::serve()` (§4.3) mit `Content-Disposition: attachment` außer Rasterbild/PDF, `nosniff` und `CSP: sandbox`; berechtigt sind die abgebende Person und Personen mit Bewertungsrecht für den Kurs (Trainer, Autor, Kursmanager des Kurses nach **E-288**, Cap #15 R0-07); jeder fremde Abruf wird mit Akteur auditiert (vgl. E-82) |
| Rate-Limit | je Nutzer und Aufgabe; Zählerausfall → fail-open wie Lernen (E-46) |
| Datenschutz | Abgaben sind Lerndaten: Exporter und Eraser (R1-17) erfassen sie, Löschung entfernt die Dateien; Backup-Hinweis R0-16 |
| Aufbewahrung | wie Rohantworten: 2 Jahre nach Kursende (**E-289**, E-51; Kursende nach E-244), änderbar; juristische Bestätigung der Frist durch den Betreiber (E-51) |
| Testfälle | MED-027…MED-030, MED-033, MED-034 |

---

## 5. Externe Medien und Einwilligung

### 5.1 Provider-Modell

- Externe Medien sind nur über `lesson.external_media {provider, url, …}` bzw. den Editor-Block erlaubt, nie als `iframe` im HTML (§1.4).
- **Provider-Registry** (Filter `course_captain_external_media_providers`): `youtube` (Einbettung über `youtube-nocookie.com`), `vimeo` (`dnt=1`), `video_url` (beliebige https-Datei auf einem Fremdhost). Jeder Provider liefert erlaubte Hosts, URL-Normalisierung, Embed-URL und Datenschutzhinweis-Text (Dienstname, Anbieter, Link zur Datenschutzerklärung).
- **Eigene Hosts** (Site-Host, Subdomains, Filter `course_captain_first_party_media_hosts`) gelten nicht als extern (LB `VideoService::externalHost`, `:105-119`, Filter `bootspruefung_video_first_party_hosts`).
- Hostliste aller externen Medien über den neutralen Filter `course_captain_external_media_hosts` für Consent-Plugins. LB koppelt das an das Theme-Filter `kurswasser_consent_external_hosts` (`Plugin.php:269`, INV-C-011) und speichert sie unbefristet als Transient (`VideoService.php:127-150`). Course Captain berechnet die Liste beim Import/Speichern und invalidiert sie mit `course_captain_content_changed`.

### 5.2 Darstellungsmodi (E-45/E-45a)

Einstellung `privacy.external_media_mode` (global, Kurs nur strenger; R0-27, R0-31):

| Wert | Bedeutung | Standard |
|---|---|---|
| `always_load` | „Immer laden“: Einbettung wird direkt ausgeliefert, ohne Klick und ohne Einwilligungsabfrage | **ja** (E-45a) |
| `click_to_load` | „Klick zum Laden“ (§5.2.2) mit Consent-Adapter (§5.3) | nein, umschaltbar |
| `link_only` | nur Link „Auf <Dienst> ansehen“, nie Einbettung | nein (strengste Stufe) |

**Transparenz (Pflicht in jedem Modus):** Die readme (`readme.txt`) legt alle Provider der Registry mit Dienstname, Anbieter, übertragenen Daten und Link zu Nutzungsbedingungen/Datenschutzerklärung offen (WordPress.org-Richtlinie, E-45). Der Setup-Assistent zeigt im Schritt Datenschutz den Hinweis, dass im Standard beim Aufruf einer Lektion mit externem Video Daten (mindestens IP-Adresse) an den Dienst übertragen werden, und bietet die Umschaltung auf „Klick zum Laden“ an (R0-30). Die Datenschutz-Textbausteine (E-04) nennen die Dienste. Die Rechtsbewertung und die Wahl des Modus liegen beim Betreiber; das Risiko ist in R0-17 als bewusst akzeptiertes Betreiberrisiko dokumentiert.

#### 5.2.1 „Immer laden“ (Standard)

1. Der Server rendert die Einbettung direkt (`iframe` über die Embed-URL des Providers). Datenminimierung bleibt Pflicht: `youtube-nocookie.com`, Vimeo mit `dnt=1`, kein `preconnect`/`dns-prefetch` zu Fremdhosts außerhalb der Einbettung selbst.
2. Consent-Adapter und `privacy.consent_category` werden in diesem Modus **nicht** ausgewertet (E-246); `privacy.external_media_remember` ist ohne Wirkung.
3. Ohne JavaScript erscheint ebenfalls die Einbettung bzw. bei `video_url` das `<video>`-Element mit der Fremd-URL.
4. Fortschritt (`position`, `completed`) wird auch für externe Videos gespeichert.

#### 5.2.2 „Klick zum Laden“ (Betreiberwahl)

1. Der Server rendert **ohne** Verbindung zum Fremdhost: kein `iframe`/`src`, kein `<track>`, kein Fremd-Vorschaubild, kein `preconnect`/`dns-prefetch`. Als Platzhalter dient ein lokales Posterbild (eigenes Medium oder neutrale Grafik), dazu Titel, Dauer, Dienstname und Hinweistext „Beim Abspielen werden Daten an <Dienst> übertragen“ mit Link.
2. Ein Klick auf „Video laden“ lädt **nur dieses** Medium. Optionales Kästchen „Externe Medien von <Dienst> immer laden“ (nie vorangehakt) speichert die Wahl im Browser (funktionaler Speicher ohne Personenbezug, abschaltbar über `privacy.external_media_remember`, R0-31).
3. Untertitelspur erst zusammen mit dem Video anhängen (LB-Verhalten T176: eine Standardspur lädt der Browser sonst sofort, `assets/build/app.js:3088-3098`).
4. Ohne JavaScript erscheint ein Link „Auf <Dienst> ansehen“, keine Einbettung.
5. Fortschritt (`position`, `completed`) wird auch für externe Videos gespeichert; die Provider-API (Player-Ereignisse) erst nach dem Laden.

### 5.3 Consent-Adapter ohne Theme-Zwang (nur Modus `click_to_load`)

**LB-Befund:** Die Lernoberfläche fragt `window.kwConsent.has('extern')` bzw. `grant('extern')` des Kurswasser-Themes ab und fällt sonst auf `localStorage 'bp_external_media'` zurück (`assets/build/app.js:3069-3079`). Das Banner selbst ist Theme-Code (`themes/kurswasser/inc/consent.php`, `assets/js/consent.js`, INV-E-059). Ohne dieses Theme gibt es keine Anbindung an ein vorhandenes Consent-Tool.

**Course Captain** (wirkt nur, wenn der Betreiber `click_to_load` gewählt hat; E-246):

| Reihenfolge | Quelle | Umsetzung |
|---|---|---|
| 1 | WP Consent API (falls aktiv) | Server/JS: `wp_has_consent(<privacy.consent_category>)`, Default `marketing` (E-246); Registrierung als konformes Plugin |
| 2 | Filter/Ereignis | PHP-Filter `course_captain_external_media_consent( bool|null $consent, string $provider )`; JS: `window.courseCaptain.consent.register(fn)` und Ereignis `course-captain:consent-changed`, damit Consent-Plugins oder Themes ohne Kopplung an einen Namen anbinden können |
| 3 | Fallback | „Klick zum Laden“ je Medium (§5.2.2) |

- Kein eigenes Cookie-Banner (E-45; die Website-Härtung nach E-10 umfasst kein Banner). Kein Theme-Global als Pflicht.
- Seiten-Cache-fest: Im Modus `click_to_load` fällt die Entscheidung im Browser; der Server liefert allen denselben Platzhalter (Muster aus `themes/kurswasser/inc/consent.php:1-14`).
- Widerruf: Ändert sich die Einwilligung im Consent-Tool auf „nein“, werden bereits geladene Einbettungen beim nächsten Seitenaufruf nicht mehr automatisch geladen.

---

## 6. Export und Testfälle

### 6.1 Kurs-Export als Paket

- **Umfang:** ein oder mehrere Kurse mit allen referenzierten Modulen, Lektionen, Fragen (aktuelle veröffentlichte Revision, optional Entwürfe), Karteikarten, Blueprints und Medien. Erzeugt wird ein gültiges `cc-package` v1, das dieselbe Validierung besteht (Round-Trip, IMP-030).
- **Provenienz:** `source.system` = `local` der Quellsite → Export setzt `source.system = "site-<installations-uuid-8>"` und `exported_from_site` = Installations-UUID. `source_id` = stabile `uid` des Objekts (UUID), nicht die DB-ID. Ein Reimport auf derselben Site wird damit als eigen erkannt (§2.2) und aktualisiert dieselben Objekte.
- **Rechte:** Alle Rechtefelder werden mitgeschrieben. Fremde Inhalte (§2.2) und Medien mit `restricted` bzw. abgelaufenem `valid_until` sind standardmäßig **ausgeschlossen** und stehen in `export.excluded[]` (E-66). Die Option „fremde Inhalte einschließen“ verlangt eine Bestätigung plus Audit.
- **Ablauf:** als Batch-Job (Cursor, Resume) im Ordner `packages`; Medien werden gestreamt kopiert, nicht in den Speicher geladen; ZIP wird erst nach Abschluss umbenannt (atomar wie LB `build-package.php`, INV-E-021). Download nur über eine authentifizierte Route mit `ccap_export_content` und Range-Unterstützung; Löschung nach 24 h (Retention).
- **Deterministisch:** gleicher Inhalt → gleiche Dateien, gleicher `content_sha256`, gleiche `package_id` (LB `testBuildIsDeterministicAndDetectsNoRemovals`, INV-E-024).
- **Nie exportiert:** Lerndaten, Grants, Nutzer, Bestellungen, Audit (§1.3). Lerner-Datenexport läuft über den WP-Privacy-Exporter (R1-17).

### 6.2 Testfälle

Fixtures ausschließlich synthetisch und neutral (E-02, Konzept 26.3). LB-Tests sind Vorlagen, keine Fixtures.

| ID | Fall | Erwartung | LB-Referenz | Ziel-Task |
|---|---|---|---|---|
| IMP-001 | gültiges Minimalpaket (1 Kurs, 1 Modul, 1 Lektion, 3 Fragen, 1 Bild) | `validated`, Dry-run `create` je Entität | `content-flow-test.php:45` | R1-15 |
| IMP-002 | ZIP ohne `manifest.json` / leeres ZIP / kein ZIP | `failed`, klare Meldung, nichts entpackt | `admin-test.php:965` | R1-15 |
| IMP-003 | Manifest mit `format` ≠ `cc-package` oder `schema_version 2.0.0` | abgelehnt, „nicht unterstützt“ | `Package.php:298` | R1-15 |
| IMP-004 | LB-Paket (`boatingschool-…`) | abgelehnt mit Hinweis „Migrationswerkzeug“ (E-03) | – | R1-15 |
| IMP-005 | Minor höher als Plugin (1.9 gegen 1.0) | abgelehnt, „Plugin aktualisieren“ | – | R1-15 |
| IMP-006 | ZIP-Bombe: Datei 1 GB mit Verhältnis > 200 | vor dem Entpacken abgelehnt | `security-test.php:142` | R1-15 |
| IMP-007 | gefälschte Größenangabe im Zentralverzeichnis | Abbruch durch Byte-Zähler beim Entpacken, Zielordner entfernt | – (neu) | R1-15 |
| IMP-008 | > 20 000 Einträge / ZIP > 200 MB / entpackt > 800 MB | abgelehnt | `Package.php:92-147` | R1-15 |
| IMP-009 | Path Traversal: `../x`, `/etc/x`, `C:\x`, `data\..\x`, NUL im Namen, `media/.hidden` | abgelehnt, keine Datei außerhalb des Zielordners | `Package.php:158` | R1-15 |
| IMP-010 | Symlink im ZIP (Unix-Attribut) und Symlink im Paketordner (CLI) | abgelehnt | `Package.php:127`, `:235` | R1-15 |
| IMP-011 | unerwartete Datei (`evil.php` auf oberster Ebene, `data/x.txt`) | abgelehnt | `Package.php:163-168` | R1-15 |
| IMP-012 | `media/a.php.png`, `media/a.svg`, `media/a.html` | abgelehnt (`rejected` im Protokoll) | `security-test.php:142` | R1-15, R1-07 |
| IMP-013 | `media/a.png` mit PHP-Inhalt (finfo ≠ image/png) bzw. deklariertem MIME `image/jpeg` | abgelehnt | `MediaStorage.php:225-256` | R1-15 |
| IMP-014 | Prüfsumme/Größe falsch, nicht gelistete Datei, `content_sha256` falsch | `failed` | `Package.php:306-322` | R1-15 |
| IMP-015 | doppelte `source_id`, kaputte Referenz (Modul → unbekannte Frage) | Objektfehler je betroffenem Objekt samt Abhängigen (E-176), Rest importierbar; Bildschirmliste höchstens 50, vollständiger Report in `ccap_import_issue`; ab Fehlerschwelle (E-180) `failed` | `Package.php:330-339`, `:387` | R1-15 |
| IMP-016 | HTML mit `<script>`, `onerror=`, `javascript:`-Link, `<iframe>` | importiert ohne die Teile, Warnung im Dry-run | – (neu) | R1-15 |
| IMP-017 | Dry-run auf bestehendem Stand: geänderte, unveränderte und fehlende Datensätze | `update/unchanged/archive` korrekt, **keine** Schreibzugriffe (DB-Prüfsumme vor/nach gleich) | `admin-test.php:90` | R1-15, R1-41 |
| IMP-018 | Dry-run in Zeitscheiben (Budget 0,1 s) | fortsetzbar, Ergebnis identisch zur Einzelausführung | `ImportService.php:304` | R1-41 |
| IMP-019 | Freigabe ohne Vorschau / nach neuerem veröffentlichten Import | abgelehnt (`supersededBy`) | `admin-test.php:71` | R1-15 |
| IMP-020 | zwei parallele `runStep` desselben und eines anderen Batches | genau einer arbeitet, Cursor nicht doppelt | `admin-test.php:102`, `:129` | R1-41 (R1-42) |
| IMP-021 | `requires.question_types` enthält nicht installierten Typ | `failed` | – (neu) | R1-15 |
| IMP-022 | Abbruch mitten im Import (Fatal simuliert) → kein Heartbeat > 600 s | `aborted`; Lernende sehen alten Stand; `resume` beendet korrekt | `admin-test.php:102` | R1-41 |
| IMP-023 | abgebrochenen Import verwerfen, solange ein Schritt die Sperre hält | abgelehnt; danach erlaubt, Sperre freigegeben | T208, `ImportService.php:719` | R1-41 |
| IMP-024 | Aktivierung: Fragenzeiger und Kursrevision wechseln in einer Transaktion | vor `activating` 0 sichtbare Änderungen, danach alle | – (neu) | R1-15 |
| IMP-025 | Frage geändert → neue Revision; laufender Prüfungsversuch | Versuch bewertet gegen alte Revision | `content-flow-test.php:69` | R1-15 (R0-23) |
| IMP-026 | Rollback auf vorletzten Stand mit Begründung | neuer Batch, alte Hashes aktiv, neue Inhalte `archived`, nichts gelöscht | `content-flow-test.php:115` | R1-15 |
| IMP-027 | Rollback ohne Begründung / auf aktives Paket ohne Abbruch / Paketkopie gelöscht | jeweils abgelehnt | `ImportService.php:142-155` | R1-15 |
| IMP-028 | lokal geänderte Lektion + geänderte Quelle | `conflict`; Standard lokal gewinnt; „Quelle übernehmen“ wirkt und wird auditiert | – (neu, E-63) | R1-15 |
| IMP-029 | Medien-Wiederholung nach halb geschriebener Datei | Prüfsumme erkannt, Datei atomar ersetzt | `content-flow-test.php:543` | R1-15 |
| IMP-030 | Round-Trip: Export → Import auf leerer Site → Export | Pakete identisch (`content_sha256`), Reimport auf Quellsite = `unchanged` | `PackageTest` Determinismus | R1-15 |
| IMP-031 | Export mit fremd lizenzierter Frage und `restricted`-Bild | ausgeschlossen, in `export.excluded[]`; mit Opt-in enthalten + Audit | – (neu) | R1-15 |
| IMP-032 | Großimport 100 000 Fragen / 2 GB Medien in Teildateien | kein PHP-Timeout, Speicher < Budget, Fortschritt/Resume (Grenzwerte aus R0-14) | `admin-test.php:146` | R1-41 |
| MED-001 | geschützte Datei ohne Login | Login-Redirect bzw. REST 401; kein Inhalt | `security-test.php` (Gate) | R1-14 |
| MED-002 | eingeloggt ohne Grant / Grant abgelaufen / Grant nur für ein anderes Modul desselben Kurses (E-27) | 403 ohne Pfad; bei unbekannter ID 404 | `ProtectedFiles.php:361-364` | R1-14 |
| MED-003 | mit Grant für Kurs B, Medium nur in Kurs A (IDOR) | 403 | – (neu) | R1-14 |
| MED-004 | Medium einer Prüfungsfrage während fremden bzw. eigenen Versuchs | fremd 403, eigen 200 | – (neu) | R1-14 (R0-08) |
| MED-005 | Lösungsmedium vor Freigabe der Feedback-Regel | 403 | – (neu) | R1-14 |
| MED-006 | Direkt-URL auf `protected-<geheim>/…` und `packages-<geheim>/…` (Apache) | 403 | `security-test.php:70` | R1-14 |
| MED-007 | Nginx-Testserver ohne Regel | Health `exposed` (kritisch), Regeltext angezeigt; mit Regel `ok` | `SystemStatus.php:241` | R1-14, R1-38 |
| MED-008 | privater Ordner außerhalb Webroot | Health `private`, keine URL berechenbar | `SiteHealth.php:131` | R1-38 |
| MED-009 | eingestellter privater Ordner nicht beschreibbar | Schreibfehler sichtbar, **kein** Rückfall nach `uploads/`, Health warnt | T204, `MediaStorage.php:84-100` | R1-14 |
| MED-010 | Range `bytes=0-99`, `bytes=100-`, `bytes=-50` | 206, korrekte `Content-Range`/`Content-Length`, Bytes stimmen | `FileController.php:44-70` | R1-14 |
| MED-011 | Range jenseits Dateiende / ungültig / mehrere Bereiche | 416 mit `bytes */size` / 200 ganze Datei | `FileController.php:49` | R1-14 |
| MED-012 | `If-Range` mit veraltetem ETag | 200 ganze Datei | – | R1-14 |
| MED-013 | Video-Seeking im Browser (E2E) | Sprung ohne Volldownload | – | R1-07, R1-18 |
| MED-014 | Header: `nosniff`, `no-store`, `sandbox` (nicht PDF), Dateiname mit Umlaut/Anführungszeichen/CRLF | korrekt kodiert, keine Header-Injection | `ProtectedFiles.php:374-433` | R1-14 |
| MED-015 | Ausgabe anderer Plugins im Puffer vor der Datei | Datei unverändert (SHA-256 gleich) | T105 | R1-14 |
| MED-016 | X-Accel-Redirect aktiviert | PHP sendet nur Header; ohne Recht kein Header | Filter `:391` | R1-14 |
| MED-017 | Anhang schützen: Bildgrößen werden mit verschoben | keine öffentliche Größen-URL mehr erreichbar | `ProtectedFiles.php:222-235` | R1-14 |
| MED-018 | importiertes Medium `unknown`, fremd | Platzhalter statt Medium; Frage mit Pflichtbild nicht auslieferbar | `content-flow-test.php:152`, `:166` | R1-14 (R0-22) |
| MED-019 | eigenes Medium `unknown` | ausgeliefert (E-18), Warnung im Editor | – (neu) | R1-14 |
| MED-020 | Sammelfreigabe mit Dry-run und Begründung | Overrides gesetzt, Audit; ohne Begründung abgelehnt | `admin-test.php:151` | R1-15 |
| MED-021 | Modus `click_to_load`: externes Video ohne Einwilligung | HTML ohne Verbindung zum Fremdhost (kein `iframe`, `track`, Poster, `preconnect`); Netzwerkprotokoll ohne Fremdhost | E2E `learning-flow.mjs` (Fremdhost-Erfassung, INV-E-111) | R1-07, R1-34 |
| MED-022 | Modus `click_to_load`: Klick zum Laden | nur dieses Video lädt; Untertitel erst danach | `app.js:3088-3098` (T176) | R1-07 |
| MED-023 | Modus `click_to_load`: Kästchen „immer laden“ gesetzt, danach Seitenaufruf | lädt direkt; Widerruf in Einstellungen wirkt | `app.js:3069-3079` | R1-07, R1-34 |
| MED-024 | Modus `click_to_load`: WP Consent API aktiv mit/ohne Einwilligung | lädt bzw. Platzhalter, ohne Theme-Global | – (neu) | R1-34, R1-30 |
| MED-025 | Standard-Theme (z. B. Twenty Twenty-Five) ohne Consent-Plugin | beide Modi funktionieren, keine JS-Fehler | – (neu, Kap. 26) | R1-30 |
| MED-026 | Paket-URL für externes Medium auf nicht registriertem Host / `http:` | Validierungsfehler; Server ruft die URL nie ab | – (neu) | R1-15 |
| MED-027 | Abgabe-Upload `a.php.pdf`, `a.svg`, PDF mit falschem MIME | abgelehnt, nichts gespeichert | – (neu, E-168) | R1-46 |
| MED-028 | Abgabe einer anderen Person per `media_uid` abrufen (IDOR), ohne Bewertungsrecht | 403 bzw. 404 ohne Existenzauskunft | – (neu, E-168) | R1-14, R1-46 |
| MED-029 | Upload nach `submitted` / ohne Grant des Moduls | abgelehnt | – (neu, E-168, E-27) | R1-46, R1-14 |
| MED-030 | Eraser für Lernenden mit Abgaben | Dateien und Metadaten entfernt, Audit ohne Inhalt | – (neu, E-168) | R1-17 |
| MED-031 | Standard `always_load`, Lektion mit YouTube-Video | Einbettung über `youtube-nocookie.com` direkt geladen; Consent API wird nicht abgefragt (E-45a, E-246) | – (neu) | R1-07 |
| MED-032 | readme und Setup | readme nennt alle registrierten externen Dienste; Setup-Schritt Datenschutz zeigt Hinweis und Umschalter | – (neu, E-45a) | R1-32, R1-39 |
| MED-033 | Abgabe mit 6 Dateien; Datei mit 21 MB; `.docx`, `.odt`, `.txt` mit passendem MIME; `.zip` | 6. Datei und 21-MB-Datei abgelehnt mit Feldmeldung; docx/odt/txt angenommen; zip abgelehnt (E-289) | – (neu, E-289) | R1-46 |
| MED-034 | Scanner-Plugin meldet über `course_captain_assignment_file_scan` einen Befund; ohne Scanner-Plugin | Datei abgelehnt, nichts in der geschützten Ablage; ohne Plugin läuft der Upload normal | – (neu, E-289) | R1-46 |

---

## 7. Entscheidungen (beschlossen)

Alle Fragen dieses Dokuments sind entschieden (`ENTSCHEIDUNGEN.md`, 16.11.2026). Genutzt außerdem: E-02, E-05, E-10, E-14, E-21, E-23, E-44, E-46.

| Frühere ID | Beschluss | Inhalt | Wirkt in | Tasks |
|---|---|---|---|---|
| R10-D1 | **E-62** | `own` aus fremdem Paket wird als `licensed` (mit `owner` = Herausgeber, Hinweis im Dry-run) gespeichert | §2.2 | R1-15, R0-22 |
| R10-D2 | **E-63** | Reimport: lokal gewinnt; Übernahme der Quelle je Objekt im Dry-run (Audit) | §3.7, IMP-028 | R1-15, R0-38 |
| R10-D3 | **E-64** | keine Paketsignaturen in R1, `signatures[]` nur reserviert | §1.2 | R1-15 |
| R10-D4 | **E-65** | zugangsgebundene Medien standardmäßig `protected` über PHP; `public` nur Kursbild/Vorschau; Bilder mit kurzem `private`-Cache + ETag | §4.1, §4.3 | R1-07, R1-14, R0-14 |
| R10-D5 | **E-66** | fremd lizenzierte Inhalte im Export nur per Opt-in mit Bestätigung und Audit | §6.1, IMP-031 | R1-15 |
| – | **E-03**, **E-18** | wie angenommen | §1.7, §2.2 | R1-15 |
| – | **E-45/E-45a**, E-246 | externe Medien Default `always_load`, umschaltbar auf `click_to_load`; Consent nur dort; readme-Offenlegung, Setup-Hinweis | §5 | R1-07, R1-32, R1-34, R1-39 |
| – | **E-20**, E-268, E-269 | Erklärungstexte nur mit Pro ausgeliefert, in Free gespeichert/importiert/exportiert | §1.3, §4.3 | R1-15, R3 |
| – | **E-27** | Medienzugriff je Modul | §4.3 | R1-14 |
| – | **E-168** | Datei-Abgaben in Aufgaben in R1 | §4.5 | R1-46, R1-14, R1-17 |
| – | **E-01**, **E-207** | Clean Room; Präfix `ccap_`/`course_captain_*` | ganzes Dokument | alle |

### 7.1 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-10-1 | Lektionsrevisionen in der Aktivierungsphase (§3.4: Weg a oder b) | technische Wahl, hängt an R0-23 und `wp_revisions_to_keep` | Architektur (R0-23/R1-15) |
| OP-10-2 | Allowlist und Größenlimit für Datei-Abgaben (§4.5): konkrete Dateitypen und Default-Größe | E-168 legt nur die Funktion fest; Werte fehlen in R0-27 | Architektur, dann R0-27 |
| OP-10-3 | Aufbewahrung von Datei-Abgaben: Gilt E-51 „Rohantworten 2 Jahre“ auch für Dateien? | Zuordnung ist hier angenommen, nicht beschlossen; juristische Bestätigung durch den Betreiber (E-51) | Betreiber |
| OP-10-4 | Wer bewertet Aufgaben in R1 (Cap/Rolle)? E-99 legt Tutor-Bewertungsfunktionen in das Gradebook (Pro), E-168 verlangt Bewertung in R1 | Widerspruch zwischen E-99 und E-168 nicht aufgelöst | Auftraggeber/R0-07 |
| OP-10-5 | Virenscan für Abgaben (z. B. per Filter-Hook) | in keinem Beschluss geregelt | Architektur |
| OP-10-6 | Untertitel und Poster im Modus `always_load`: Datenminimierung über die Einbettung hinaus | E-45a regelt nur den Modus | Architektur (R1-07) |

---

## 8. Nachweis

- Gelesen (LB @ `9f37c77`, nur lesend): `src/Content/Import/ImportService.php` (`:15-65`, `:84-466`, `:494-526`, `:639-650`), `src/Content/Package/Package.php` (`:16-37`, `:45-339`, `:349-400`), `src/Content/Media/MediaStorage.php` (`:1-260`), `src/Content/Import/EntityImporter.php` (`:177-213`), `src/Content/Repositories/ContentRepository.php` (`:85-104`), `src/Content/Repositories/Overrides.php` (`:15-50`), `src/Content/Import/Retention.php` (`:17-30`), `src/Content/Import/ImportSources.php` (Funktionsköpfe), `src/Gate/ProtectedFiles.php` (`:30-47`, `:191-240`, `:281-292`, `:344-433`), `src/Mobile/Controllers/FileController.php` (`:15-80`), `src/Admin/SiteHealth.php` (`:131-235`), `src/Admin/SystemStatus.php` (`:205-275`, `:326-361`), `src/Infrastructure/Monitor.php` (`:79-92`), `src/Trainers/VideoService.php` (`:105-150`), `src/Plugin.php:269`, `assets/build/app.js:3060-3120`, `themes/kurswasser/inc/consent.php:1-30`, `schema/v1/manifest|media|common.schema.json`.
- Range in LB: `grep -rn "HTTP_RANGE\|Accept-Ranges"` in `src/` trifft nur `Mobile/Controllers/FileController.php` und `PrivacyController.php` (dort `Accept-Ranges: none`), nicht die Web-Route `Gate/ProtectedFiles.php`.
- `kwConsent` in LB: nur in `assets/build/app.js` (Plugin) und `themes/kurswasser/` (Theme).
- Öffentliche Paketmedien in LB: `MediaStorage.php:12` („media/<key> ausgelieferte Medien (öffentlich …)“), `mediaDir()` unter `baseDir()` = `uploads/bootspruefung` (`:64-67`, `:127-134`).
- Keine Änderung an LearnBoating, keine Implementierung. Kein Code aus LB übernommen (E-01).

## Nachträge aus Folge-Tasks (eingearbeitet)

| Datum | Quelle | Änderung |
|---|---|---|
| 16.11.2026 | R0-17 §3 | Import per URL: IP-Pinning gegen DNS-Rebinding, nur Port 443, keine privaten/link-lokalen Ziele – **eingearbeitet** in §3.1 |
| 16.11.2026 | R0-38 Δ-38, E-176 | Fehler in Validierungsstufe V6 sind **Objektfehler** statt `failed` für den ganzen Batch; die Grenze von 50 Fehlern gilt nur noch für die Bildschirmliste, der vollständige Report liegt in `ccap_import_issue` – **eingearbeitet** in §1.4, §3.2 |
