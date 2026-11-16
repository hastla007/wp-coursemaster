# R0-38 · Großimporte mit Dry-run, Batches und Resume

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Es gibt keine Implementierung und keinen Code. Dieses Dokument ist die Spezifikation für **R1-41** (und ergänzt R1-15).
**Stand:** 16.11.2026
**Bezug:** TODO R0-38 (Voraussetzung R0-10, R0-16, R0-21; DoD: Validierungsreport pro Zeile/Objekt, Rechte/Provenienz, deduplizierter Import, Batch-Cursor, Timeout/Resume, Rollback, Fehlerklassen), Ziel-Task **R1-41** (Voraussetzung R1-15); `R0-10-import-medien-sicherheit.md` (§1 Paketformat, §2 Rechte, §3.2 V1–V6, §3.3 Dry-run, §3.4 Automat, §3.5 Batch-Kurzvertrag, §3.6 Rollback, §3.7 Konflikte, IMP-001…032), `R0-16-backup-recovery-deployment.md` (I-1…I-5), `R0-21-cache-nebenlaeufigkeit.md` (§2.4 Lease/Fencing, M0, CON-012), `R0-14-nichtfunktionale-ziele.md` (OP-11, L3, R-M, R-S, RB-01…RB-07, NFR-006/-007), `R0-23-revisionen-laufender-kurse.md` (§4 Staging-Kopie), `R0-22-kursvalidierung-publish.md` (§1.2 Report, Kontext `import_dry_run`), `R0-36-suite-vertragsdeltas.md` (Zeile A9), `R0-06-datenmodell-adr.md` (§4.7 `ccap_import_batch`, `ccap_import_item_log`, `ccap_source_map`, A-01/A-05/A-17).
**Quellen (nur gelesen, nicht verändert):** LearnBoating `hastla007/learnboating` @ `9f37c77`, Pfad `bootspruefung-learning/src/Content/Import/ImportService.php` (**IS**); Suite `wpaffiliatemaster/docs/IMPORT-EXPORT.md` (**AFF**). Die Quellen sind Verhaltensreferenz; kein Code wird übernommen (E-01). „übernommen“ in §1 heißt: Verhalten wird nach dieser Spezifikation neu implementiert.

### Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). Die früheren Fragen R38-D1…D6 sind als **E-176…E-181** entschieden (§9). Eingearbeitet:

| Beschluss | Wirkung hier |
|---|---|
| **E-01** Clean Room | IS- und AFF-Befunde sind Verhaltensreferenz; Runner, Lease, Spool usw. werden neu gebaut |
| **E-62, E-63, E-64, E-65, E-66** | wie angenommen: `own` aus Fremdpaket → `licensed`; lokal gewinnt mit Wahl je Objekt; keine Signaturen; geschützte Medien; Fremdinhalte im Export nur per Opt-in (§3) |
| **E-104** | nicht öffentliche Staging-Kopie, Übernahme in einer Transaktion je Kurs (§5.1) |
| **E-176…E-181** | Objekt-Isolation; Hintergrund als Standardweg; große Pakete über Ablageordner/WP-CLI mit höheren Grenzen; Kurse mit Blockern zurückstellen; Fehlerschwelle `max(100, 2 %)`; automatische Wiederaufnahme nach Vorprüfungen |
| **E-152**, E-46 | Rate-Limits für Import-URL/Export fail-closed (R0-17 §3.4) |
| **E-02, E-44, E-50** | synthetische Fixtures; Fristen Import-Protokoll 14/180 Tage; Fragenrevision erst beim Versuchsstart fixiert |
| **E-207** | Präfix `ccap_` für Tabellen, Cron-Hooks, Meta; Optionen `course_captain_*` (im Text umgestellt) |


**Normative Sprache:** **MUSS** = Vertragsbestandteil; **SOLL** = Standard, begründete Abweichung erlaubt; **KANN** = optional.

---

## 0. Kurzfassung

1. **Ein Report-Format für Dry-run, Commit und Wiederholung:** je Objekt und Befund eine Zeile mit Objekt-ID, Datei/JSON-Pfad, Regelcode (`IMP-…` für Paket/Import, `VAL-…` aus R0-22), Schwere, Meldung und Vorschlag. Export als CSV (RFC 4180, gestreamt, CSV-Injection-sicher) und JSON. Dazu eine Zusammenfassung je Entität, Aktion, Regel und Rechtestatus.
2. **Fehler bleiben am Objekt.** LB bricht beim ersten fehlerhaften Datensatz den ganzen Import ab (IS `step` `:494-501`). Course Captain unterscheidet vier Klassen: **F** fatal (Paket/Lauf), **O** Objekt (nur dieses Objekt und seine Abhängigen fallen aus), **W** Warnung (importiert, gemeldet), **T** transient (automatisch wiederholt). Eine Fehlerschwelle hebt systematische O-Fehler auf F (§5.3).
3. **Deduplizierung auf drei Ebenen:** Identität über `(source_system, entity, source_id)` → `ccap_source_map` → stabile `uid`; Inhalt über den Datensatz-Hash (`unchanged`, bekannte Revision reaktivieren); Mediendateien über SHA-256 (eine Datei, aber getrennte Medienzeilen je Herkunft, damit Rechte nicht verschmelzen). Konflikt „lokal geändert“ nach E-63.
4. **Schritte und Chunks:** Ein *Schritt* ist ein Request bzw. Job mit Zeitbudget, ein *Chunk* ist eine kurze DB-Transaktion (Ziel ≤ 1 s) und schreibt Daten, Cursor und Lease-Heartbeat zusammen. Die Chunkgröße passt sich an Zeit und Speicher an (AIMD). Daten werden aus einem während der Validierung angelegten NDJSON-Spool mit Offset-Index gelesen; der Speicherbedarf hängt also von der Chunkgröße ab, nicht von der Paketgröße (Delta zu IS `:479-481`).
5. **Lease mit Fencing** (R0-21), Stale nach 600 s, Resume ab dem letzten committeten Chunk. Drei Ausführungswege mit demselben Runner: Hintergrund (Action Scheduler, sonst WP-Cron-Kette), Browser-Polling und WP-CLI. Wechsel zwischen ihnen ist mitten im Lauf erlaubt.
6. **Rollback:** Vor der Aktivierung heißt Rollback „Verwerfen“: alle nicht aktivierten Artefakte des Batches werden entfernt. Nach der Aktivierung legt ein Rollback einen neuen Import aus der Paketkopie an (R0-10 §3.6). **Wiederholen nur fehlgeschlagener Objekte** ist ein Teilimport aus derselben Paketkopie mit Objektfilter, ohne neuen Automatenzustand.
7. **Grenzwerte je Quelle** (Admin-Upload wie R0-10, Ablageordner/CLI höher) und **Paketserien** für große Medienbestände; Messgrößen M-01…M-16 als Startwerte für R1-41; Testfälle **BIG-001…BIG-040**; sechs beschlossene Entscheidungen **E-176…E-181**.

---

## 1. Quellenbefunde

| ID | Quelle | Befund | Folge für Course Captain |
|---|---|---|---|
| Q-01 | IS `runStep` `:398-440` | Statusprüfung `approved/importing`; `assertNotSuperseded`; anderer lebender Import blockiert; Sperre; **Zustand nach der Sperre neu lesen** (T199); `finally` Cache leeren + Sperre lösen | übernommen (§4.4); Sperre wird Lease mit Fencing |
| Q-02 | IS `step` `:442-535` | Cursor `{phase, offset}` im JSON `stats`; Heartbeat **vor** der Arbeit (`:449`); **eine** TX je Schritt (`:466`, `:494`); bei jedem `Throwable` `ROLLBACK`, Fehler am Batch, Import gilt als abgebrochen (`:495-501`) | Cursor als eigene Spalte, TX je Chunk; Objektfehler isoliert statt Abbruch (§5.3) |
| Q-03 | IS `step` `:479-481` | `$package->entities($entity)` lädt **alle** Datensätze einer Entität, danach `array_slice` | Speicher O(n): für 100 000 Fragen ungeeignet → Spool mit Offset-Index (§4.2) |
| Q-04 | IS `limitBytes` `:538-553` | Medien je Schritt ≤ 64 MB, **mindestens ein** Datensatz | übernommen als Fortschrittsgarantie (§4.3) |
| Q-05 | IS `runAll` `:555-563` | CLI: Schleife über `runStep(…, 500)` ohne Zeitbudget | CLI nutzt denselben Runner mit Chunk-Budget (§4.6) |
| Q-06 | IS `preview` `:275-330` | Zeitscheibe 20 s, Budgetprüfung alle 50 Datensätze, Fortschritt `{entity, offset, summary}`; neue Vorschau setzt `previewed → validated` zurück | übernommen; Dry-run schreibt zusätzlich den Objekt-Report (§2) |
| Q-07 | IS `previewProgress` `:332-340` | Prozent = Entitätsindex / Entitätenzahl, also grob (bei 100 000 Fragen in einer Entität lange „0 %“) | Fortschritt nach gewichteten Arbeitseinheiten (§4.7) |
| Q-08 | IS `logs` `:593-605` | Protokoll je Objekt (`entity, source_id, action, message`), Filter nach Aktion, `LIMIT/OFFSET` | Report mit Schwere/Regel/Pfad, Keyset-Paginierung statt `OFFSET` (§2.4) |
| Q-09 | IS `acquireLock`/`releaseLock` `:699-717` | Besitzer `<batch>-<zufall>`, TTL 900 s > Stale 600 s (T208); Freigabe nur durch Besitzer | übernommen in `ccap_lease` (R0-21 D21-1) |
| Q-10 | IS `releaseAbandonedLock` `:719-733` | Verwerfen abgelehnt, solange ein Schritt die Sperre hält (jünger als TTL); sonst Options-Zeile löschen + Cache leeren | übernommen als Lease-Übernahme per CAS (§4.4, BIG-016) |
| Q-11 | AFF `:8`, `:13` | CLI `--dry-run`; im Admin **immer** zuerst Prüfung; jede Zeile mit Ergebnis bzw. Gründen; fehlerhafte Zeilen übersprungen, Rest importiert; Nebenbefunde als Hinweis mit Tipp; Vorhandenes als „übersprungen“, nicht als Fehler (Reimport möglich); Admin ≤ 2 000 Zeilen/5 MB, CLI ohne Grenze | Objekt-Isolation (E-176), Vorschlagsspalte, Grenzwerte je Quelle (§6.1) |
| Q-12 | AFF `:15` | Batches ≈ 15 s, Seite setzt selbst fort, Fortsetzen-Knopf; **ein im unterbrochenen Batch angelegtes Objekt wird vervollständigt statt als „existiert“ gemeldet**; ein Import je Site; geprüfte Datei bis zum nächsten Import bzw. ≤ 1 Tag geschützt aufbewahrt | Resume-Regel „angefangen = vervollständigen“ (§4.5), ein Import je Site (§4.4) |

---

## 2. Validierungsreport

### 2.1 Wann er entsteht

| Lauf | Inhalt | Schreibt Inhalte? |
|---|---|---|
| **Validierung** (V1–V5, R0-10 §3.2) | Paket- und Dateibefunde (`IMP-V1…V5`) | nein; legt nur Spool und Report an |
| **Dry-run** (V6 + Diff + Validator) | je Datensatz: geplante Aktion (`create/update/unchanged/archive/conflict/rejected`), `IMP-V6`, `IMP-R`, `IMP-D`, dazu `VAL-…` aus R0-22 mit `context = import_dry_run` (R0-22 §1.5) | nein (MUSS; DB-Prüfsumme vor/nach gleich, IMP-017) |
| **Commit** | tatsächliches Ergebnis je Objekt (`outcome`), Laufzeitfehler `IMP-B…` | ja |
| **Aktivierung** | `VAL`-Blocker/Warnungen je Kurs mit `context = publish` (R0-22 §2.4) | ja (Zeiger, Staging-Übernahme) |
| **Wiederholung** (§5.4) | neue Zeilen mit `attempt = n` | ja |

Alle Läufe schreiben in **dieselbe** Struktur. Ein Befund aus dem Dry-run und derselbe Befund im Commit haben denselben Fingerabdruck (wie R0-22 §1.3), damit „bekannt aus Dry-run“ und „neu im Commit“ unterscheidbar sind.

### 2.2 Zeilenformat

Eine Zeile = ein Befund an einem Objekt. Ein Objekt ohne Befund erscheint im **Objektprotokoll** (`ccap_import_item_log`, eine Zeile je Objekt und Versuch), aber nicht im Befundreport.

| Feld | Typ | Inhalt | Beispiel |
|---|---|---|---|
| `seq` | int | laufende Nummer im Batch (Keyset für Paginierung) | `48211` |
| `object.entity` | enum | `manifest`, `file`, `course`, `module`, `lesson`, `question`, `flashcard`, `media`, `blueprint`, `link` | `question` |
| `object.source_id` | string ≤ 191 | Objekt-ID im Paket; bei Paketbefunden `null` | `q-nav-00412` |
| `object.uid` | uuid \| null | lokale stabile ID, falls bereits zugeordnet (§3.3) | `0b6e…` |
| `object.title` | string ≤ 120 | gekürzter Titel/Fragetext (Textauszug, ohne HTML) | „Welche Lichter …“ |
| `file` | string | Paketdatei und Datensatzindex | `data/questions/000009.json#1412` |
| `path` | JSON-Pointer (RFC 6901) | Feld im Datensatz; leer bei Objektbefund | `/revision/answers/2/text_html` |
| `rule` | string | Regelcode `IMP-<Gruppe><n>.<nn>` (§2.3) oder `VAL-nnn` (R0-22) | `IMP-V6.03` |
| `severity` | enum | `fatal`, `error`, `blocker`, `warning`, `notice`. `blocker` nur für `VAL`-Regeln (wirkt auf die Aktivierung, §5.3) | `error` |
| `class` | enum | Fehlerklasse `F`/`O`/`W`/`T`/`N` (§5.3) | `O` |
| `action` | enum | geplante (Dry-run) bzw. erfolgte (Commit) Aktion | `rejected` |
| `message_key`, `params` | string, object | i18n-Schlüssel und Parameter | `cc.imp.ref_unknown`, `{ref:"m-17", entity:"media"}` |
| `message` | string | serverseitig übersetzte Meldung (Sprache des Ausführenden), Objekt + Problem | „Die Frage verweist auf das Medium m-17, das im Paket und auf der Site fehlt.“ |
| `suggestion` | string \| null | konkreter nächster Schritt; nie eine fachliche Entscheidung (wie R0-22 §1.2 `fixes`) | „Medium m-17 ins Paket aufnehmen oder Verweis entfernen; danach ‚Fehlgeschlagene wiederholen‘.“ |
| `decision` | enum \| null | nur Konflikte: `keep_local` (Standard, E-63) / `take_source` | `keep_local` |
| `phase`, `attempt` | string, int | Phase (§4.1) und Versuch (§5.4) | `questions`, `1` |
| `fingerprint` | `sha256:` | über `rule | entity | source_id | path | normalisierte params` | – |

Meldungen folgen R0-22 §1.1 (Alltagssprache, keine Tabellennamen). Regelcode und `source_id` stehen im UI klein als Supportangabe.

### 2.3 Regelkatalog `IMP-…` (Paket und Import)

Die Codes heißen `IMP-<Gruppe><n>.<nn>` und sind damit von den **Testfall**-IDs `IMP-001…` in R0-10 §6.2 unterscheidbar. Codes sind stabil; entfallene werden nicht neu vergeben.

| Code | Bedingung | Klasse | Wirkung |
|---|---|---|---|
| IMP-V1.01 / .02 / .03 | kein gültiges/leeres ZIP · Einträge über Limit · Größe über Limit (§6.1) | F | Batch `failed` |
| IMP-V2.01 / .02 | Kompressionsverhältnis zu hoch · Byte-Zähler beim Entpacken überschritten | F | `failed`, Zielordner entfernt |
| IMP-V3.01 / .02 / .03 | unzulässiger Pfad · Symlink · unerwartete Datei | F | `failed` |
| IMP-V4.01 / .02 / .03 | Endung nicht erlaubt · gefährliche innere Endung · MIME-Abweichung (deklariert/`finfo`) | O | Medium `rejected`; Abhängige nach §5.3 |
| IMP-V4.04 | Mediendatei über Typlimit (§6.1) | O | Medium `rejected` |
| IMP-V5.01 / .02 | Manifest-Schema verletzt · Major/Minor nicht unterstützt | F | `failed` |
| IMP-V5.03 / .04 / .05 | Datei fehlt bzw. Größe/SHA-256 falsch · nicht gelistete Datei · `content_sha256` falsch | F | `failed` (Integrität betrifft das ganze Paket, R0-10 V5) |
| IMP-V6.01 | Datensatz verletzt das Entitätsschema | O | Objekt `rejected` |
| IMP-V6.02 | doppelte `source_id` innerhalb einer Entität | F | `failed` (Identität mehrdeutig) |
| IMP-V6.03 | Referenz weder im Paket noch in `ccap_source_map` derselben `source.system` | O | Objekt `rejected` bzw. Verweis entfernt (§5.3) |
| IMP-V6.04 | `requires` nicht erfüllt (Fragetyp/Provider fehlt) | F | `failed` (IMP-021) |
| IMP-V6.05 | HTML-Filter hat Inhalt entfernt (Anzahl Elemente/Attribute in `params`) | W | importiert gefiltert |
| IMP-V6.06 | Datensatz-`hash` passt nicht zum kanonischen Datensatz | O | `rejected` (sonst falsche Deduplizierung) |
| IMP-V6.07 | Feldlimit überschritten (§6.1) | O | `rejected` |
| IMP-V6.08 | `media.access` passt nicht zum Kursbezug | W | wird `protected` (E-65) |
| IMP-V6.09 | Referenzzyklus (z. B. Modul in sich selbst) | O | alle Objekte im Zyklus `rejected` |
| IMP-R1.01 | Rechte fehlen und kein `default_rights` | W | fremd → `unknown` (gesperrt, R0-10 §2.2) |
| IMP-R1.02 | `own` aus fremdem Paket | N | gespeichert als `licensed`, `owner` = Herausgeber (E-62) |
| IMP-R1.03 | fremder Inhalt `unknown`/`restricted`/`valid_until` abgelaufen | W | importiert, Auslieferung gesperrt; Zählwert in der Zusammenfassung |
| IMP-R1.04 | lokaler Rechte-Override vorhanden | N | Override bleibt (R0-10 §3.7) |
| IMP-D1.01 | Quelle **und** lokal geändert | W | `conflict`, Standard `keep_local` (E-63) |
| IMP-D1.02 | offene Editor-Staging-Kopie der Lektion | W | `conflict` (R0-23 §4.2 Nr. 6, E-104) |
| IMP-D1.03 | im Paket fehlendes, lokal geändertes Objekt soll archiviert werden | W | `conflict`, Standard: nicht archivieren |
| IMP-D1.04 | gleicher Inhaltshash wie ein Objekt anderer Herkunft | N | „mögliches Duplikat“, kein Zusammenführen |
| IMP-D1.05 | `source_id` eines eigenen Exports (`exported_from_site` = diese Site) zeigt auf eine `uid`, die hier nicht existiert oder anderer Herkunft ist | O | `rejected` |
| IMP-B1.01 | freier Platz/Speicher reicht nicht (RB-07) | F | Lauf `aborted` vor dem Schreiben |
| IMP-B1.02 | Lease verloren/Fencing gescheitert (CON-012) | T | Chunk zurückgerollt, Lauf endet ohne Schreiben |
| IMP-B1.03 | DB-Fehler (Deadlock 1213, Lock-Wait 1205, „gone away“) | T | Chunk-Wiederholung (RB-04/RB-05) |
| IMP-B1.04 | Schreibfehler am Objekt (Constraint, Mediendatei nicht schreibbar) | O | Objekt `failed` |
| IMP-B1.05 | Fehlerschwelle überschritten (§5.3) | F | Lauf `aborted` |
| IMP-B1.06 | anderer Import inzwischen aktiviert (`supersededBy`, IS `:222-240`) | F | Lauf `aborted`, nur Verwerfen |
| IMP-B1.07 | Paketkopie fehlt oder Spool-Prüfsumme weicht ab | F | `aborted`; Resume nur nach erneuter Validierung |
| IMP-B1.08 | Objekt hängt von einem fehlgeschlagenen Objekt ab | O | `skipped_dependency` |
| IMP-B1.09 | Abbruch durch Bedienung | F | `aborted` (`reason = cancelled`) |

`VAL`-Regeln (R0-22) erscheinen unverändert mit ihrer Schwere (`blocker/warning/notice`). Im Import legen Blocker kein Objekt still, sondern verhindern nur die **Aktivierung** des betroffenen Kurses (§5.3).

### 2.4 Speicherung, Ansicht, Export

- **Speicher (Δ-38-1):** neue Tabelle `ccap_import_issue (id PK, batch_id, attempt, phase, entity, source_id NULL, uid NULL, file_ref, path, rule, severity, class, action, message_key, params JSON, decision NULL, fingerprint, created_at)`, Indizes `(batch_id, id)`, `(batch_id, severity, id)`, `(batch_id, rule)`, `(batch_id, entity, source_id)`, FK → `ccap_import_batch` CASCADE. Die Meldung wird beim Lesen übersetzt, nicht gespeichert. `ccap_import_item_log` bekommt `attempt`, `outcome` (`ok/failed/skipped_dependency/conflict_kept`) und `object_id` (für Verwerfen, §5.1).
- **Grenze:** höchstens 100 000 Befundzeilen je Batch (Filter). Danach entsteht IMP-B1.05 bzw. in der Validierung ein Sammelbefund „weitere n Befunde nicht gespeichert“ mit Zählwert je Regel; der Dry-run gilt dann als `failed` (ein Paket mit > 100 000 Befunden ist nicht sinnvoll prüfbar). Delta zu R0-10 §1.4 („höchstens 50 Fehler, dann Abbruch“): Die 50er-Grenze gilt nur noch für die **Bildschirm-Kurzliste** fataler Befunde.
- **Ansicht (S-18):** Filter nach Schwere, Klasse, Entität, Regel, Aktion, Text in `source_id`/Titel; Keyset-Paginierung über `seq` (50 je Seite). Gruppenansicht „gleiche Regel“ mit Zählwert und den ersten zehn Objekten. Konfliktzeilen haben die Auswahl `keep_local/take_source` je Objekt und als Sammelaktion für die gefilterte Menge (Audit, E-63).
- **Export:** authentifizierte Route `GET /course-captain/v1/imports/{batch}/report?format=csv|json&severity=&rule=&attempt=` (M-21, Nonce), gestreamt in Blöcken à 1 000 Zeilen, ohne Gesamtzwischenspeicher.
  - **CSV:** UTF-8 mit BOM (Excel), Trennzeichen Komma, RFC 4180, Zeilenende CRLF. Spalten: `seq,severity,class,rule,entity,source_id,uid,title,file,path,action,message,suggestion,decision,phase,attempt`. Zellen, die mit `=`, `+`, `-`, `@`, Tab oder CR beginnen, bekommen ein vorangestelltes `'` (CSV-Injection).
  - **JSON:** Kopf `{batch_id, package_id, source_system, mode, report_version: "1", generated_at, summary}` und `issues[]` im Format §2.2; als NDJSON mit `?format=ndjson` für sehr große Reports.
  - WP-CLI: `wp course-captain import report <id> --format=csv|json --output=<datei>`.
- **Aufbewahrung:** wie Import-Protokoll (E-44: 14 Tage für `unchanged`, sonst 180 Tage). Der Report enthält keine Lernendendaten, nur `created_by/approved_by` am Batch.

### 2.5 Zusammenfassung

Kopf jedes Reports und der Freigabeseite:

| Block | Inhalt |
|---|---|
| Paket | `package_id`, `source.system`, `source.revision`, Herausgeber, `schema_version`, Größe, Dateizahl, eigen/fremd |
| Aktionen | Matrix Entität × `create/update/unchanged/archive/conflict/rejected/skipped_dependency` |
| Befunde | Zählwerte je Schwere und Klasse; Top 10 Regeln mit Anzahl |
| Rechte | Entität × Rechtestatus (IS `rightsSummary` `:639-650`); Zahl der Inhalte, die nach dem Import gesperrt wären; Zahl gesetzter Overrides |
| Konflikte | Anzahl je Art (D1.01–D1.03), davon `take_source` gewählt |
| Kurse | je Kurs: Aktivierbar ja/nein (VAL-Blocker), Warnungen, betroffene Lernende und laufende Versuche (Zählwerte, R0-10 §3.3) |
| Ressourcen | Speicherbedarf neuer Medien gegen freien Platz; geschätzte Dauer je Ausführungsweg (aus Durchsatz des Dry-runs, §4.7) |
| Freigabebedingungen | offene F-Befunde = keine Freigabe; O-Befunde > 0 verlangen die Bestätigung „n Objekte werden ausgelassen“; fremde Inhalte verlangen die Rechtebestätigung (R0-10 §2.2) |

---

## 3. Rechte, Provenienz und Deduplizierung

### 3.1 Provenienz je Objekt

Jedes importierte Objekt MUSS auf seine Quelle zurückführbar sein:

| Angabe | Speicherort | Herkunft |
|---|---|---|
| `source_system`, `source_id`, `object_type` | `ccap_source_map` (UNIQUE `source`) bzw. Spalten der Tabellenentität (R0-06 SRC) | Manifest `source.system`, Datensatz |
| `uid` | `ccap_source_map.uid` (R0-08 Δ-1) bzw. Tabellenspalte | §3.3 |
| `last_batch_id`, `last_hash` (Δ-38-2) | `ccap_source_map` | letzter Batch, der das Objekt geschrieben hat; Hash des Datensatzes |
| `package_id`, `source.revision` | `ccap_import_batch` | Manifest |
| `locally_modified` | `ccap_source_map` (R0-06 §4.7) | Editor setzt beim Speichern; Tabellenentität: Revision mit `created_by` ≠ Importlauf |
| Rechte | Revision bzw. Medium bzw. `_ccap_rights` (R0-10 §2.1) | Datensatz → `default_rights` → leer (= `unknown` bei fremd) |

Staging-Kopien bekommen **keine** Zeile in `ccap_source_map` (R0-23 Δ-23-4); die Zuordnung hängt am Live-Objekt, die Kopie trägt `_ccap_stage_batch`.

### 3.2 Rechteprüfung im Ablauf

1. **Dry-run:** Rechte werden je Objekt aufgelöst (Datensatz → `default_rights`), E-62 angewandt und mit bestehenden Overrides verglichen. Befunde `IMP-R1.xx`.
2. **Freigabe:** Rechtebestätigung (Audit `import_approved`), wenn fremde Inhalte enthalten sind; Zählwerte aus §2.5 werden in den Audit-Eintrag übernommen.
3. **Commit:** Rechte werden mit der Revision geschrieben (neuer Hash bei Rechteänderung, R0-10 §2.1). Overrides überleben.
4. **Aktivierung:** R0-22 VAL-100…105 je Kurs. Gesperrte Pflichtmedien machen Fragen unauslieferbar (R0-10 §2.2) und erscheinen als Kursbefund.
5. **E2E-Pflicht (R1-41 DoD):** Ein Lernender sieht nach der Aktivierung keinen fremden `unknown`-Inhalt (BIG-021).

### 3.3 Stabile UID und Identität

- Neue Objekte bekommen eine **zufällige UUIDv4** als `uid`, in **derselben** TX wie die `ccap_source_map`-Zeile. Eine deterministische UID (v5 aus Herkunft) ist nicht nötig: Resume und Reimport finden das Objekt über `(source_system, source_id, object_type)`.
- **Eigener Export** (`exported_from_site` = Installations-UUID, R0-10 §2.2/§6.1): `source_id` ist die `uid`. Der Importer ordnet direkt über `uid` zu, auch ohne `ccap_source_map`-Zeile (lokal erstellte Objekte haben `source_system = local`). Passt die `uid` nicht → IMP-D1.05.
- **Angefangene Objekte** (AFF `:15`): Wurde ein Objekt in einem committeten Chunk angelegt, ist es über `ccap_source_map` auffindbar und wird beim Resume als `update`/`unchanged` behandelt. Medien, deren Datei geschrieben, deren Zeile aber nicht committet wurde, werden über SHA-256 erkannt und übernommen (I-1, T203).

### 3.4 Deduplizierung

| Ebene | Schlüssel | Regel |
|---|---|---|
| Identität | `(source_system, object_type, source_id)` | gefunden → `update`/`unchanged`/`conflict`; nicht gefunden → `create` |
| Inhalt | Datensatz-`hash` gegen `ccap_source_map.last_hash` bzw. Revisions-Hash | gleich → `unchanged`, **kein Schreiben** außer Objektprotokoll; bei Fragen: Hash einer älteren Revision → diese reaktivieren (R0-10 §3.6) |
| Mediendatei | `sha256` des Inhalts | gleiche Datei wird **einmal** gespeichert (`storage_key` geteilt, R0-06 Zeile A-05). Eine Medien**zeile** gibt es je Herkunft, weil Rechte, Alt-Text und `access` je Quelle verschieden sein können. Ohne diese Trennung würde die Rechtesperre einer Quelle das Medium einer anderen treffen |
| Paketintern | `source_id` doppelt | IMP-V6.02 fatal |
| Paketintern/quellübergreifend | gleicher Inhaltshash, andere `source_id` bzw. andere Herkunft | IMP-D1.04 Hinweis; **nie** automatisch zusammenführen |

**Idempotenz (MUSS):** Ein zweiter Import desselben Pakets erzeugt 0 neue Datensätze, 0 neue Revisionen und 0 neue Mediendateien; alle Objekte sind `unchanged` (NFR-006, BIG-011).

### 3.5 Konflikte (E-63)

| Quelle geändert | lokal geändert | Aktion | Standard |
|---|---|---|---|
| nein | nein | `unchanged` | – |
| ja | nein | `update` | – |
| nein | ja | `unchanged` (lokaler Stand bleibt) | – |
| ja | ja | `conflict` (IMP-D1.01) | `keep_local`; Quelländerung im Report |
| ja | Editor-Staging-Kopie offen | `conflict` (IMP-D1.02) | Kopie bleibt; `take_source` ersetzt sie (R0-23 §4.2 Nr. 6) |
| fehlt im Paket | ja | `conflict` (IMP-D1.03) | nicht archivieren |

- Entscheidungen werden je Objekt am Batch gespeichert (`ccap_import_issue.decision`), auditiert (`import_conflict_decided`, mit Anzahl bei Sammelaktion) und gelten nur für **diesen** Batch.
- Eine neue Vorschau übernimmt Entscheidungen, deren Fingerabdruck gleich bleibt; bei geändertem Quell- oder Lokalstand wird neu gefragt.
- `take_source` setzt `locally_modified = 0`. Lokale Overrides (`rights_status` u. a.) bleiben auch dann bestehen.

---

## 4. Batch-Verarbeitung

### 4.1 Phasen und Arbeitseinheiten

```text
Validierung:  unpack → v1_v5 → spool                      (Zeitscheiben, kein Inhalt geschrieben)
Dry-run:      analyse[entität…] → archive_plan → summary   (Zeitscheiben, kein Inhalt geschrieben)
Commit:       media → courses → modules → lessons → questions → flashcards → blueprints
              → module_links → course_links → archive      (Chunks, R0-10 §3.5)
Aktivierung:  precheck → activate[kurs…] → questions_pointer → finalize
```

Jede Phase hat eine bekannte Zahl Arbeitseinheiten (Datensätze aus `manifest.counts`, bei `media` zusätzlich Bytes). Phasen kommen aus einer Registry, damit Add-ons Entitäten ergänzen können (R0-10 §3.5).

### 4.2 Lesen ohne Volllast: Spool

- **Validierung** liest jede Teildatei `data/<entität>/NNNNNN.json` (≤ 5 000 Datensätze, ≤ 16 MB, R0-10 §1.1) **einmal**, prüft Schema/Hash und schreibt je Entität eine NDJSON-Datei `work/<entität>.ndjson` (kanonisch, ein Datensatz je Zeile) plus Index `work/<entität>.idx` (Byte-Offset jedes 100. Datensatzes). Spool liegt im privaten Paketordner des Batches (R0-10 §4.1) und hat eine eigene SHA-256 im Batch (IMP-B1.07).
- **Dry-run, Commit und Wiederholung** lesen per `fseek` ab dem Cursor nur die benötigten Zeilen. Speicher hängt von der Chunkgröße ab, nicht von N (Delta zu Q-03).
- Liegt `memory_limit` nach `wp_raise_memory_limit('admin')` unter 256 MB und ist eine Teildatei > 8 MB, bricht die Validierung mit IMP-V1.03 und dem Hinweis „Paket mit kleineren Teildateien erzeugen oder per WP-CLI importieren“ ab. Generatoren SOLLEN Teildateien ≤ 4 MB schreiben (Δ-38-4 an R0-10).

### 4.3 Chunk, Schritt und adaptive Größe

- **Chunk** = eine DB-Transaktion: Datensätze schreiben, Objektprotokoll und Befunde schreiben, **Cursor** fortschreiben, **Lease bestätigen** (Fencing-`UPDATE`, R0-21 §2.4). Alles oder nichts. Ziel 0,5–1 s, harte Obergrenze 5 s (lange TX belasten Lernanfragen unter L3).
- **Schritt** = ein Request bzw. ein Job: beliebig viele Chunks, bis das Zeitbudget erreicht ist. Budget `T = min(20 s, max_execution_time / 2, Einstellung)`; Admin-Ziel 10 s (OP-11 p50). CLI: `T` = 60 s je Schritt (nur für Heartbeat-Rhythmus und Speicherbereinigung), sonst ohne Zeitgrenze.
- **Adaptiv (AIMD):** Start `n = 50` Datensätze je Chunk (Medien: 16 MB). Nach jedem Chunk:
  - Dauer < 0,5 s **und** Speicher < 50 % des Limits → `n = min(2n, n_max)` (`n_max` 500, Medien 64 MB je Schritt wie IS `:60`);
  - Dauer > 1,5 s **oder** Speicher > 70 % → `n = max(⌊n/2⌋, 1)`;
  - vor dem nächsten Chunk: Restbudget < 1,5 × letzte Chunkdauer **oder** Speicher > 75 % → Schritt beenden.
  - `n` wird im Cursor gespeichert (Warmstart nach Resume). Mindestens ein Datensatz je Chunk (Fortschrittsgarantie, IS `limitBytes`).
- **Speicherpflege je Chunk:** `wp_suspend_cache_addition(true)`, `wp_defer_term_counting(true)`, `wp_cache_flush_runtime()` nach jedem Chunk (nur Laufzeit-Cache, nicht persistent), `$wpdb->flush()`, keine `SAVEQUERIES`. Gemessen wird `memory_get_usage(true)` gegen das wirksame Limit.
- **Medien** werden **vor** dem Chunk-Commit außerhalb der TX geschrieben (Temp-Datei, SHA-256, `rename`; I-1, I-2). Ein Chunk-Rollback hinterlässt höchstens eine unreferenzierte Datei, die der Retention-Job nach Karenz entfernt (I-3).

### 4.4 Cursor und Lease

**Cursor** (Δ-38-3: Spalte `ccap_import_batch.cursor` JSON statt in `stats`):

```json
{ "mode": "commit", "attempt": 1, "phase": "questions", "offset": 48200,
  "spool_line": 48200, "chunk_size": 200, "fence": 7,
  "done_units": 61230, "total_units": 162400, "updated_at": "2026-11-16T10:12:03Z" }
```

- Der Cursor wird **nur** in der Chunk-TX geschrieben. Damit ist „Datensatz geschrieben“ ⇔ „Cursor dahinter“ (exactly-once je Chunk).
- **Lease** `import` (eine je Site, R0-21 M0; Delta zu R0-21-Text `import:<batch>`: der Name ist global, der Batch steht im `owner`, weil wie bei IS `:407-412` nur ein Import zur Zeit laufen darf). `owner = <batch>-<zufall12>`, TTL 900 s, Heartbeat in jedem Chunk, spätestens nach TTL/3. Übernahme nur bei abgelaufenem `expires_at` per CAS mit `fence + 1`. Ein „Zombie“-Worker scheitert am Fencing und schreibt nichts (CON-012).
- Nach dem Lease-Erwerb wird der Batch **neu gelesen** (T199, Q-01). Ein Dry-run eines anderen Batches darf parallel laufen, weil er nichts schreibt; er hält Lease `import-preview:<batch>`.
- **Stale:** `heartbeat_at` älter als 600 s und Lease abgelaufen → Status `aborted` (`reason = stale`). Gesetzt von jedem Leser: Admin-Liste, nächster Schritt, Watchdog in `ccap_hourly`, Health-Check.

### 4.5 Timeout und Resume

| Ereignis | Zustand danach | Resume-Verhalten |
|---|---|---|
| PHP-Timeout/Fatal mitten im Chunk | offene TX von MySQL zurückgerollt; Cursor auf letztem Chunk | ab Cursor; höchstens ein Chunk wird wiederholt |
| `kill -9` des CLI-Workers | wie oben; Lease läuft ab | nach Ablauf (≤ 900 s) übernehmbar; `--force-takeover` nur, wenn `heartbeat_at` > 600 s alt |
| Mediendatei geschrieben, Chunk nicht committet | Datei ohne Zeile | Datei per SHA-256 erkannt und übernommen (I-1, IMP-029) |
| DB-Verbindung weg | Chunk abgebrochen | `$wpdb->check_connection()`, Chunk neu (RB-05) |
| Deadlock/Lock-Wait | Chunk zurückgerollt | TX-Wiederholung 3× mit Jitter 50–200 ms (RB-04); danach Schritt endet mit T-Befund |
| Browser-Tab geschlossen (Polling) | kein Schritt mehr | Hintergrund-Runner übernimmt (§4.6), sonst Knopf „Fortsetzen“ |
| Abbruch durch Bedienung | `cancel_requested` am Batch | Runner stoppt an der nächsten Chunkgrenze → `aborted (cancelled)` |

**Vor jedem Resume MUSS** geprüft werden: Batch nicht `superseded` (IMP-B1.06), Paketkopie und Spool vorhanden und Prüfsumme gleich (IMP-B1.07), Plugin-Schema-Version unverändert (sonst erneut validieren), `requires` weiterhin erfüllt. **Automatische Wiederaufnahme** im Hintergrund nach T-Befunden: höchstens 3 Versuche mit 1, 5 und 15 min Abstand, danach bleibt der Batch `aborted` und Site Health meldet ihn (E-181).

### 4.6 Ausführungswege

Alle Wege rufen denselben `ImportRunner::step(batch, budget)` unter derselben Lease auf. Ein im Browser begonnener Lauf kann per CLI fortgesetzt werden und umgekehrt.

| Weg | Ablauf | Stärken | Schwächen | Einsatz |
|---|---|---|---|---|
| **H – Hintergrund** | `as_enqueue_async_action('ccap_import_step', [batch_id], 'course-captain')`, wenn Action Scheduler vorhanden; sonst `wp_schedule_single_event(time(), 'ccap_import_step', [id])` + `spawn_cron()`. Jeder Schritt plant den nächsten selbst; Watchdog in `ccap_hourly`. Argumente nur IDs (R0-19) | läuft ohne offenen Tab; ein Ablauf für Admin | WP-Cron ohne Besucher/mit `DISABLE_WP_CRON` ohne System-Cron steht (RB-01); Loopback auf manchen Hosts gesperrt | Standard (E-177) |
| **B – Browser-Polling** | Admin-Seite ruft `POST /imports/{id}/step` (RG-02, Nonce) in Schleife, Antwort = Fortschritt | sofort, unabhängig von Cron | endet mit dem Tab; Worker belegt je Schritt | Rückfall, wenn H in 60 s keinen Heartbeat liefert („Im Browser fortsetzen“) |
| **C – WP-CLI** | `wp course-captain import <zip|dir> [--dry-run] [--approve --confirm-rights] [--run] [--resume=<id>] [--retry-failed=<id>] [--max-chunk=<n>] [--report=<datei>]`; Ausführung als Nutzer mit `ccap_import_content` (`--user`) | kein Request-Timeout, höhere Grenzen (§6.1), Fortschrittsbalken | Shell-Zugang nötig | empfohlen ab 20 000 Objekten oder > 200 MB |

- Nie auf Frontend-Anfragen von Besuchern (Konzept 9.4). WP-Cron läuft in einem eigenen Request (`wp-cron.php`), nicht im Seitenaufruf.
- **Freigabe und Aktivierung** bleiben an die Berechtigung des Freigebenden gebunden: Der Batch speichert `approved_by`; Hintergrundjobs prüfen beim Aktivieren, ob dieser Nutzer weiterhin `ccap_import_content` + `ccap_publish_courses` hat (M-21), sonst `aborted` mit Befund.

### 4.7 Fortschrittsanzeige

- `GET /imports/{id}` liefert kompakt: `status, mode, phase, done_units, total_units, percent, rate_per_min, eta_s, chunk_size, counts{ok, failed, conflict, warning}, last_heartbeat_at, runner (H/B/C), attempt`. Ziel ≤ 5 Queries, p95 ≤ 100 ms (M-15).
- `percent` = gewichtete Einheiten (Datensätze; Medien nach Bytes; Aktivierung je Kurs) statt Entitätsindex (Delta zu Q-07). `eta` aus gleitendem Mittel der letzten zehn Chunks.
- Polling alle 2 s, bei verdecktem Tab (`visibilitychange`) alle 10 s. Kein Heartbeat seit 60 s → Hinweis „Hintergrund läuft nicht“ mit Knopf „Im Browser fortsetzen“.
- Barrierefreiheit (R0-15): Fortschritt als `<progress>` mit Text; `aria-live="polite"` höchstens alle 10 s und bei Phasenwechsel bzw. Fehler.
- Die Admin-Liste zeigt laufende und abgebrochene Importe mit Index auf `status` (R0-10 §3.4 `aborted` gespeichert).

---

## 5. Rollback, Abbruch, Fehlerklassen, Wiederholen

### 5.1 Rollback vor der Aktivierung (= Verwerfen)

Bis `activating` ist nichts für Lernende sichtbar (I-5, E-104). „Verwerfen“ aus `aborted`, `approved` oder davor:

1. Lease übernehmen; ein lebender Schritt (Lease jünger als TTL) blockiert das Verwerfen (Q-10, IMP-023).
2. Staging-Kopien mit `_ccap_stage_batch = <id>` löschen (R0-23 §4.2 Nr. 5).
3. Objekte, die **dieser** Batch neu angelegt hat (`ccap_import_item_log.object_id` mit `action = create`, nie aktiviert), samt `ccap_source_map`-Zeilen und nicht aktivierten Fragenrevisionen physisch entfernen. Sie waren nie sichtbar, kein Versuch referenziert sie.
4. Neue Mediendateien verlieren ihre Referenz und fallen unter die Retention mit 7 Tagen Karenz (I-3).
5. Status `discarded`, Audit `import_discarded` mit Zählwerten. Ablauf in Chunks (resumable), weil 100 000 Objekte nicht in eine TX passen.

### 5.2 Rollback nach der Aktivierung

- Wie R0-10 §3.6: neuer Import aus der aufbewahrten Paketkopie des Zielstands, mit Pflichtbegründung, normalem Dry-run und Freigabe. Alte Hashes werden reaktiviert, später hinzugekommene Inhalte `archived`, nichts gelöscht.
- Seit dem Import lokal geänderte Objekte erscheinen als Konflikt (E-63, lokal gewinnt).
- Laufende Versuche bleiben auf ihren Revisionen und Snapshots (R0-23).
- **Teilaktivierung:** Wurde die Aktivierung nach Kurs k unterbrochen, bleiben aktivierte Kurse in sich konsistent (TX je Kurs, I-5). Der Cursor zeigt auf Kurs k+1. Resume aktiviert den Rest; Verwerfen ist dann nicht mehr möglich, nur Rollback.

### 5.3 Fehlerklassen und Wirkung

| Klasse | Beispiele | Wirkung im Dry-run | Wirkung im Commit | Wirkung auf Aktivierung |
|---|---|---|---|---|
| **F fatal** | V1–V3, V5, V6.02, V6.04, B1.01, B1.05–B1.07, B1.09 | Batch `failed`, keine Freigabe | Lauf `aborted`, kein weiterer Chunk; Cursor bleibt | keine |
| **O Objekt** | V4, V6.01/.03/.06/.07/.09, D1.05, B1.04, B1.08 | Objekt `rejected`, Abhängige `skipped_dependency` | Objekt `failed`, Batch läuft weiter | betroffener Kurs nur, wenn der Validator ohne Blocker ist |
| **W Warnung** | V6.05/.08, R1.01/.03, D1.01–.03, VAL-Warnungen | gemeldet | importiert | VAL-Warnungen bei bereits veröffentlichten Kursen per Fingerabdruck bestätigen (R0-22 §1.3) |
| **T transient** | B1.02, B1.03 | Zeitscheibe wiederholt | Chunk wiederholt (§4.5), zählt nicht als Objektfehler | – |
| **N Hinweis** | R1.02, R1.04, D1.04, VAL-Hinweise | gemeldet | – | – |

**Abhängigkeiten bei O-Fehlern:**

| Ausgefallenes Objekt | Abhängiges Objekt | Wirkung |
|---|---|---|
| Medium | Frage mit `image_required` | Frage `skipped_dependency` |
| Medium | Lektion (inline/attachment), Frage ohne Pflichtbild | Verweis entfällt, Platzhalter, W-Befund |
| Frage | Modul-Fragenpool, Lektionsquiz | Verweis entfällt, W-Befund; Blueprint mit fester Variante, die die Frage enthält → `skipped_dependency` |
| Lektion | Modul (`lessons[]`) | Verweis entfällt; Pflichtlektion → Kurs bekommt VAL-Befund (Aktivierung prüft) |
| Modul | Kurs | Verweis entfällt; Kurs wird nur aktiviert, wenn der Validator keinen Blocker meldet |

**Aktivierung je Kurs:** `precheck` lässt den Validator (R0-22, `context = publish`) für alle betroffenen Kurse ohne Schreibzugriff laufen. Kurse mit Blockern werden **zurückgestellt** (Staging bleibt, Report nennt die Blocker), die übrigen aktiviert (E-179). Fragen sind kursunabhängig (E-50): Ihre Zeiger werden für alle erfolgreich importierten Fragen umgestellt (`questions_pointer`, chunkweise je 5 000 per `UPDATE … JOIN`, M-07). Gemeinsam genutzte Module werden mit dem ersten aktivierten Kurs übernommen; der Report weist darauf hin (R0-23 §4).

**Fehlerschwelle (Schutz vor systematischen Fehlern):** Übersteigen die O-Fehler im Commit `max(100, 2 % der Objekte der Phase)` und lagen sie im Dry-run nicht vor, wird der Lauf mit IMP-B1.05 angehalten (`aborted`). Bereits im Dry-run bekannte und bestätigte O-Fehler zählen nicht (E-180).

**Endzustand:** `published` mit `stats.failed_objects`, `stats.held_courses`. Die Admin-Liste zeigt „abgeschlossen mit Auslassungen“. Kein neuer Automatenzustand (R0-10 §3.4 bleibt gültig).

### 5.4 Wiederholen nur fehlgeschlagener Objekte

- **„Fehlgeschlagene wiederholen“** legt einen **Kind-Batch** an: `retry_of = <id>`, dieselbe Paketkopie, Objektfilter = alle `source_id` mit `outcome ∈ {failed, skipped_dependency}` plus Kurse aus `held_courses`, `partial = true` (keine Archivierung).
- Der Kind-Batch durchläuft den normalen Weg (Dry-run nur für die gefilterte Menge, Freigabe, Commit, Aktivierung der betroffenen Kurse). `supersededBy` gilt zwischen Eltern und Kind nicht.
- Sinnvoll bei Umgebungsursachen (Platz voll, Fragetyp-Add-on inzwischen aktiv, Konflikt jetzt entschieden, Rechte-Override gesetzt). Bei **Datenfehlern** im Paket hilft nur ein korrigiertes Paket: Der Reimport ist idempotent, unveränderte Objekte kosten nur den Hashvergleich (M-05).
- WP-CLI: `wp course-captain import retry <id> [--approve --confirm-rights] [--run]`.
- Audit: `import_retry_created`, `import_retry_published` mit Bezug auf den Eltern-Batch.

### 5.5 Audit (Ergänzung zu R0-10 §3.8)

Neu: `import_cancel_requested`, `import_conflict_decided` (Einzeln/Sammel mit Anzahl), `import_auto_resumed` (mit Versuch), `import_threshold_exceeded`, `import_course_held`, `import_retry_created`, `import_retry_published`, `import_report_exported` (Format, Filter).

---

## 6. Grenzwerte und Messgrößen

### 6.1 Grenzwerte (Startwerte, Filter `course_captain_import_limits`)

| Grenze | Admin-Upload | Ablageordner / HTTPS-Link | WP-CLI (ZIP oder Ordner) | Herkunft |
|---|---|---|---|---|
| ZIP-Größe | min(PHP-, WP-Limit, 200 MB) | 2 GB | 4 GB | R0-10 V1 (200 MB) für Upload; höher nur ohne Request-Upload |
| entpackt | 800 MB | 4 GB | 8 GB | R0-10 V1 |
| Einträge im ZIP | 20 000 | 200 000 | 400 000 | R0-10 V1, A-17 |
| Datensätze je Paket (alle Entitäten) | 50 000 | 250 000 | 250 000 | neu; deckt A-01 (100 000 Fragen) + Medien-Metadaten ab |
| Datensätze/Größe je Teildatei | 5 000 / 16 MB (SOLL 4 MB) | wie links | wie links | R0-10 §1.1, §4.2 |
| Einzelmedium | Bild 20 MB, Untertitel 1 MB, Dokument 50 MB, Audio 100 MB, Video 512 MB | wie links | Video 2 GB | neu (IMP-V4.04) |
| Medien je Schritt | 64 MB | 64 MB | 256 MB | IS `:60` |
| HTML-Feld | 256 KB | wie links | wie links | neu (IMP-V6.07) |
| Antworten je Frage / Tags je Objekt | 50 / 50 | wie links | wie links | neu |
| Befundzeilen je Batch | 100 000 | wie links | wie links | §2.4 |
| freier Platz | ≥ entpackt + Spool + neue Medien × 1,1 | wie links | wie links | RB-07 |

Die Admin-Seite zeigt bei Überschreitung den Ausweichweg („Ablageordner oder WP-CLI verwenden“, wie R0-10 §3.1).

**Paketserien (zusätzlicher technischer Weg, nicht Teil von E-178):** Große Medienbestände dürfen auf mehrere Pakete derselben `source.system` verteilt werden: zuerst Medienpakete (`partial`), dann das Inhaltspaket, das über `ccap_source_map` auf bereits importierte Medien verweist (R0-10 §1.4 „Teilpakete“). So bleiben 2 GB Medien auch im Admin-Upload möglich (10 × 200 MB). Wahl zwischen höheren Grenzen und Serien: E-178.

### 6.2 Messgrößen für R1-41

Gemessen auf **R-M** (R0-14 §2.1) mit synthetischem Paket **BIG-P1**: 100 000 Fragen (single/multi/true_false gemischt, Ø 4 Antworten, Ø 3 Tags), 50 000 Bilder (Ø 40 KB ≈ 2 GB), 2 000 Lektionen, 200 Module, 20 Kurse, 20 Blueprints; erzeugt von einem dev-only Generator `wp cc-dev make-package --profile=big --seed=<n>` (nicht im Release-ZIP, Muster R0-14 §2.2 Seeder). Datenbestand vorher: Profil `default`. Alle Werte sind **Startwerte**; R1-41 misst und ersetzt sie (wie E-59).

| ID | Messgröße | Ziel R-M | Bemerkung |
|---|---|---|---|
| M-01 | Validierung + Spool BIG-P1 | ≤ 10 min (CLI), ≤ 15 min (H) | linear aus OP-11 |
| M-02 | Dry-run BIG-P1 | ≤ 20 min (H), ≤ 15 min (C) | Dry-run 10 000 ≤ 2 min |
| M-03 | Commit BIG-P1 (ohne Aktivierung) | ≤ 45 min (H), ≤ 30 min (C) | Hochrechnung OP-11 wäre 150 min für Dry-run+Commit; Ziel ist schneller durch Chunks/Spool |
| M-04 | Aktivierung BIG-P1 gesamt / längste TX | ≤ 60 s / ≤ 5 s | I-5; `questions_pointer` je 5 000 |
| M-05 | Reimport unverändert BIG-P1 (Dry-run + Commit) | ≤ 15 min, 0 neue Zeilen in Inhaltstabellen | Idempotenz, NFR-006 |
| M-06 | Peak-Speicher je Schritt | Admin/H ≤ 192 MB bei Limit 256 MB; C ≤ 256 MB, **unabhängig von N** (Δ < 10 % zwischen 10 000 und 100 000) | §4.2 |
| M-07 | Queries je Datensatz (gemittelt) | ≤ 8 (OP-11) | Query-Sonde R0-14 §2.3 |
| M-08 | Schrittdauer p95 (H/B) | ≤ 20 s und ≤ `max_execution_time / 2` | RB-02 |
| M-09 | Chunk-TX p95 / max | ≤ 1 s / ≤ 5 s | §4.3 |
| M-10 | L1-p95 während M-03 (Lastprofil L3 mit BIG-P1) | höchstens +30 % gegenüber NFR-001, 0 Lock-Wait-Timeouts in Lernanfragen | NFR-006 |
| M-11 | Verlorene Arbeit nach `kill -9` | ≤ 1 Chunk, 0 Duplikate | RB-02, NFR-007 |
| M-12 | Zeit bis Stale-Erkennung und Resume | ≤ 600 s + 1 Watchdog-Takt | §4.4 |
| M-13 | Mediendurchsatz (Kopie + SHA-256, geschützter Ordner) | ≥ 20 MB/s | 2 GB ≈ 2 min |
| M-14 | Report-Export 100 000 Befunde CSV | ≤ 60 s, ≤ 32 MB Speicher | gestreamt |
| M-15 | Fortschritts-Endpunkt | p95 ≤ 100 ms, ≤ 5 Queries | §4.7 |
| M-16 | R-S (`max_execution_time 30`, 256 MB): 10 000 Fragen | ≤ 45 min ohne Timeout (OP-11); BIG-P1 auf R-S nur informativ, Doku empfiehlt CLI | NFR-007 |

---

## 7. Testfälle BIG-xxx

Fixtures synthetisch und neutral (E-02). Paket BIG-P1 aus §6.2; BIG-P0 = 10 000 Fragen (OP-11). „IMP-0xx“ verweist auf R0-10 §6.2.

| ID | Fall | Erwartung | Ziel-Task |
|---|---|---|---|
| BIG-001 | Dry-run BIG-P1, Report-Zusammenfassung | Matrix Entität × Aktion stimmt mit Generator-Sollwerten; DB-Prüfsumme vor/nach gleich | R1-41 |
| BIG-002 | Paket mit je einem Fehler pro IMP-Regel (O/W/N) | genau eine Befundzeile je Regel mit korrektem `rule`, `severity`, `class`, `file`, `path`; Meldung und Vorschlag übersetzt | R1-41 |
| BIG-003 | Report-Export CSV und JSON/NDJSON, gefiltert nach Schwere | Spalten wie §2.4; Zeilenzahl = gefilterte Befunde; Unicode/Umlaute korrekt; Excel öffnet die Datei | R1-41 |
| BIG-004 | Befundtext beginnt mit `=HYPERLINK(` bzw. `+`, `-`, `@` | Zelle mit `'` präfixiert | R1-41 |
| BIG-005 | Report-Route ohne `ccap_import_content`, Batch fremder Site (Multisite), ohne Nonce | 403 bzw. 404, kein Inhalt (M-21) | R1-41 |
| BIG-006 | 150 000 Befunde | 100 000 gespeichert + Sammelbefund; Dry-run `failed`; UI-Kurzliste ≤ 50 fatale Befunde | R1-41 |
| BIG-007 | Paginierung Report-Ansicht Seite 1 und Seite 2 000 | gleiche Antwortzeit (Keyset), ≤ 300 ms | R1-41 |
| BIG-008 | Befund aus Dry-run tritt im Commit erneut auf | gleicher Fingerabdruck, als „bekannt“ markiert | R1-41 |
| BIG-010 | Erstimport BIG-P0 mit 30 % fremden Inhalten ohne Rechte | `unknown` gesetzt, Zusammenfassung nennt Zahl gesperrter Inhalte; Freigabe ohne Rechtebestätigung abgelehnt | R1-41 (R1-15) |
| BIG-011 | Zweiter Import desselben Pakets | alle `unchanged`; 0 neue Zeilen in Inhalts-/Medientabellen und 0 neue Dateien | R1-41 |
| BIG-012 | Paket v2: 1 000 Fragen geändert, 500 entfernt, 200 neu | `update 1000 / archive 500 / create 200`, Rest `unchanged`; entfernte `archived`, nicht gelöscht | R1-41 |
| BIG-013 | Frage lokal geändert + in v2 geändert; Lektion mit offener Editor-Kopie | `conflict` (D1.01/D1.02), Standard `keep_local`; Sammelaktion `take_source` für Filtermenge wirkt, Audit mit Anzahl | R1-41 (E-63, E-104) |
| BIG-014 | Gleiches Bild in zwei Paketen verschiedener Herkunft, eines `restricted` | eine Datei, zwei Medienzeilen; Sperre trifft nur die fremde Zeile | R1-41 |
| BIG-015 | Reimport eigener Export auf derselben Site | Zuordnung über `uid`; `unchanged`; Rechte bleiben `own` | R1-41 |
| BIG-016 | Verwerfen, während ein Schritt die Lease hält; danach erneut | erst abgelehnt, nach Schrittende erlaubt (Q-10, IMP-023) | R1-41 |
| BIG-020 | Commit BIG-P1 im Hintergrund (Action Scheduler) | fertig innerhalb M-03; Fortschritt monoton, ETA plausibel (±30 % nach 10 %) | R1-41 |
| BIG-021 | Wie BIG-020, ohne Action Scheduler (WP-Cron) und mit `DISABLE_WP_CRON` ohne System-Cron | WP-Cron: fertig; ohne Cron: nach 60 s Hinweis „Hintergrund läuft nicht“, „Im Browser fortsetzen“ beendet den Import; nach Aktivierung sieht ein Lernender keinen gesperrten Inhalt (E2E) | R1-41 |
| BIG-022 | Browser-Polling, Tab nach 40 % geschlossen, später CLI `--resume` | CLI setzt ab Cursor fort; Ergebnis identisch zu einem Durchlauf ohne Unterbrechung | R1-41 |
| BIG-023 | `kill -9` des CLI-Workers in Phase `questions` und `media` | ≤ 1 Chunk wiederholt, 0 Duplikate (UNIQUE-Prüfung), Mediendatei per SHA-256 übernommen | R1-41 (R1-42) |
| BIG-024 | SIGSTOP Worker A > TTL, Worker B übernimmt, A wird fortgesetzt | A scheitert am Fencing, schreibt nichts (CON-012) | R1-42 (R1-41) |
| BIG-025 | DB-Neustart während Commit | T-Befund, Chunk wiederholt; automatische Wiederaufnahme ≤ 3 Versuche; danach `aborted` + Site Health | R1-41 |
| BIG-026 | Deadlock-Injektion in jedem 50. Chunk | TX-Wiederholung, keine O-Fehler, Ergebnis vollständig | R1-41 (R1-42) |
| BIG-027 | Zwei parallele `step`-Requests auf denselben Batch und ein Dry-run eines anderen Batches | genau einer schreibt, Cursor nicht doppelt; Dry-run läuft parallel ohne Schreibzugriff | R1-41 (IMP-020) |
| BIG-028 | `memory_limit 128M` (R-S-Variante) | Chunkgröße sinkt adaptiv; kein Fatal; Teildatei > 8 MB → IMP-V1.03 mit Hinweis | R1-41 |
| BIG-029 | Speicher-Peak bei BIG-P0 und BIG-P1 vergleichen | M-06 erfüllt (Δ < 10 %) | R1-41 |
| BIG-030 | Abbruch durch Bedienung bei 60 % Commit, danach Verwerfen | Stopp an Chunkgrenze; Verwerfen entfernt Staging-Kopien, neu angelegte Objekte, `ccap_source_map`-Zeilen; Lernende sahen nie etwas Neues (DB-Prüfsumme sichtbarer Stand gleich) | R1-41 |
| BIG-031 | Aktivierung mit 20 Kursen, Kurs 7 hat VAL-Blocker (leeres Modul wegen O-Fehler) | Kurs 7 zurückgestellt und im Report; 19 Kurse aktiviert; Fragenzeiger umgestellt | R1-41 (R1-26) |
| BIG-032 | Prozessabbruch zwischen Kurs 10 und 11 der Aktivierung | Kurse 1–10 konsistent neu, 11–20 konsistent alt; Resume aktiviert den Rest; Verwerfen abgelehnt, Rollback angeboten | R1-41 |
| BIG-033 | Rollback nach Aktivierung von v2 auf v1 | neuer Batch; v1-Hashes aktiv, v2-Neuzugänge `archived`; laufender Prüfungsversuch bewertet gegen Snapshot | R1-41 (R1-15, R1-27) |
| BIG-034 | 300 Medien mit falschem MIME (O) in BIG-P0 | 300 `rejected`, abhängige Pflichtbild-Fragen `skipped_dependency`, Rest importiert | R1-41 |
| BIG-035 | Fehlerschwelle: im Commit (nicht im Dry-run) scheitern > 2 % Schreibvorgänge (simulierter Constraint) | IMP-B1.05, `aborted`, Audit `import_threshold_exceeded` | R1-41 |
| BIG-036 | „Fehlgeschlagene wiederholen“ nach behobener Ursache (Platz frei, Add-on aktiviert) | Kind-Batch nur mit den fehlgeschlagenen Objekten + zurückgestellten Kursen; danach 0 offene Fehler; Eltern-Batch nicht `superseded` | R1-41 |
| BIG-037 | Resume nach Plugin-Update mit neuer Schema-Version bzw. nach Löschen der Paketkopie | erneute Validierung verlangt bzw. IMP-B1.07 | R1-41 |
| BIG-038 | Anderer Import wird aktiviert, während dieser `aborted` ist | Resume abgelehnt (IMP-B1.06), nur Verwerfen | R1-41 (IMP-019) |
| BIG-039 | L3 mit BIG-P1 statt BIG-P0 | M-10 erfüllt | R1-42 (R1-41, R1-22) |
| BIG-040 | Paketserie: 10 Medienpakete à 200 MB per Admin-Upload, dann Inhaltspaket | Verweise über `ccap_source_map` aufgelöst; keine Archivierung durch Teilpakete | R1-41 |

---

## 8. Deltas an andere Dokumente

| Δ | Ziel | Änderung |
|---|---|---|
| Δ-38-1 | R0-06 §4.7 | neue Tabelle `ccap_import_issue` (§2.4); `ccap_import_item_log` + `attempt`, `outcome`, `object_id` |
| Δ-38-2 | R0-06 `ccap_source_map` | + `last_batch_id bigint NULL`, `last_hash char(71) NULL` |
| Δ-38-3 | R0-06 `ccap_import_batch` | + `cursor longtext NULL`, `mode varchar(16)`, `retry_of bigint NULL`, `partial tinyint(1)`, `cancel_requested tinyint(1)`, `spool_sha256 char(64) NULL` |
| Δ-38-4 | R0-10 §1.1/§3.2 | Teildateien SOLL ≤ 4 MB; V6-Fehler sind Objektfehler statt `failed` (außer V6.02/V6.04); „50 Fehler“ nur noch Bildschirm-Kurzliste; Grenzwerte je Quelle §6.1 – **eingearbeitet** (R0-10 §1.1, §1.4, §3.1, §3.2, IMP-015) |
| Δ-38-5 | R0-21 §3 (M0) | Lease-Name `import` global je Site, Batch im `owner`; zusätzlich `import-preview:<batch>` für parallele Dry-runs |
| Δ-38-6 | R0-10 §3.8 | Audit-Ereignisse aus §5.5 – **eingearbeitet** (Verweis in R0-10 §3.8) |

---

## 9. Entscheidungen (beschlossen)

Alle Fragen sind entschieden (`ENTSCHEIDUNGEN.md` §27, „Von Claude entschieden“, 16.11.2026); Empfehlung übernommen. Genutzt außerdem: E-02, E-44, E-50, E-62…E-66, E-104, E-152.

| Frühere ID | Beschluss | Inhalt | Wirkt in | Tasks |
|---|---|---|---|---|
| R38-D1 | **E-176** | Fehler am einzelnen Objekt isolieren; der Rest läuft weiter, Abhängige werden mitmarkiert; Identitäts- und Integritätsfehler bleiben fatal | §5.3, BIG-0xx | R1-41, R1-15 |
| R38-D2 | **E-177** | Standard Hintergrund (Action Scheduler bzw. WP-Cron) mit Fortschrittsanzeige; Browser-Polling und WP-CLI als Alternativen, mitten im Lauf wechselbar | §4.6, §4.7 | R1-41 |
| R38-D3 | **E-178** | Pakete über 200 MB über Ablageordner auf dem Server oder WP-CLI mit höheren Grenzwerten | §6.1 | R1-41, R0-10 |
| R38-D4 | **E-179** | Kurse mit Publish-Blocker zurückstellen, übrige aktivieren; Anzeige schon im Dry-run | §5.3 | R1-41, R1-26 |
| R38-D5 | **E-180** | Fehlerschwelle `max(100 Fehler, 2 % der Objekte)` je Phase, dann Anhalten (Default, R0-27 `advanced.import_error_threshold`) | §5.3 | R1-41 |
| R38-D6 | **E-181** | automatische Wiederaufnahme nach Vorprüfungen (Lease abgelaufen, Paketkopie unverändert), bis zu 3 Versuche (1/5/15 min), dann `aborted` + Site Health | §4.5 | R1-41 |

### 9.1 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-38-1 | Paketserien (§6.1) als zusätzlicher Weg: E-178 nennt nur Ablageordner/WP-CLI; die frühere Empfehlung c) umfasste beides | Bestätigung, ob Paketserien in R1 Pflicht sind | Architektur (R1-41) |
| OP-38-2 | Fehlerschwelle: R0-38 sah einen Filter vor, R0-27 führt ein Setting `advanced.import_error_threshold` | beides vereinbar (Setting mit Filter-Grenze); Festlegung in R1-41 | Architektur |
| OP-38-3 | Messwerte M-01…M-16 sind Startwerte | Messung in R1-41 auf R-M (Cloud-VM nach E-192) | R1-41 |

---

## 10. Nachweis

- Gelesen (LB @ `9f37c77`, nur lesend): `bootspruefung-learning/src/Content/Import/ImportService.php` (`:27-63` Konstanten, `:275-330` `preview`, `:332-340` `previewProgress`, `:398-440` `runStep`, `:442-535` `step`, `:538-553` `limitBytes`, `:555-563` `runAll`, `:593-605` `logs`, `:699-733` Sperren inkl. `releaseAbandonedLock`).
- Gelesen (Suite): `wpaffiliatemaster/docs/IMPORT-EXPORT.md` (`:8`, `:13`, `:15`, `:98`).
- Planungsdokumente: TODO R0-38, R1-41; R0-10 §1–§3, §6.2, §7; R0-16 I-1…I-5, REC-014; R0-21 §2.4, M0, D21-1, CON-012; R0-14 OP-11, L3, R-M/R-S, RB-01…RB-07, NFR-006/-007; R0-23 §4, E-104; R0-22 §1.2, §1.5, §2.4; R0-36 Zeile A9; R0-06 §4.7, A-01/A-05/A-17; `ENTSCHEIDUNGEN.md` E-62…E-66, E-104.
- Keine Änderung an LearnBoating oder der Suite, keine Implementierung, kein Commit.
