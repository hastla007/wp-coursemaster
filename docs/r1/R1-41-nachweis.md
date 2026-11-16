# R1-41 · Großimport-Batchverarbeitung mit Fehlerreport – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-b`
**DoD (TODO R1-41):** Dry-run vor Commit, Fortschritt/Resume, pro Objekt validierte Fehler, idempotenter Reimport, Rollback und Rechteprüfung E2E.
**Quellen:** R0-38 §2 (Report, Regelkatalog `IMP-…`, Export), §3.4 (Idempotenz), §4 (Phasen, Spool, Chunk/Schritt/AIMD, Cursor, Lease, Timeout/Resume, Ausführungswege, Fortschritt), §5 (Verwerfen, Teilaktivierung, Fehlerklassen, Fehlerschwelle, Aktivierung je Kurs), §7 (BIG-xxx); E-176, E-177, E-178, E-179, E-180, E-181; R0-10 §1.1 (≤ 5 000 Datensätze je Teildatei), §3.4–§3.6; R0-21 §2.4 (Lease/Fencing, CON-012); R0-23 §4 (Aktivierung in einer Transaktion je Kurs); R1-26-Nachweis A-2 (Import-Aktivierung über den Kursvalidator); R1-15-Nachweis A-1. Clean Room (E-01): LearnBoating nur als Verhaltensreferenz aus R0-38 §1, kein Code übernommen.

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Import/ImportRunner.php` | **neu.** Ein Runner für Hintergrund (`ccap_task_continue` mit `[import:<id>, cursor]`, Action Scheduler falls vorhanden, sonst WP-Cron), Browser-Schritt (REST), WP-CLI und den synchronen `ImportService::run()`. Phasen `plan` (Paket einmal gestreamt in privaten NDJSON-Spool, SHA-256 im Cursor) → `apply` (Chunks aus dem Spool) → Status `activating` → `questions` (Revisionen umschalten) → `archive` → `courses` (Validator, eine Transaktion je Kurs, Blocker → zurückgestellt) → `finalize` (`published`, Vorgänger `superseded`, Audit). Chunk = eine Transaktion mit Batch-Zeile `FOR UPDATE`, Lease-Heartbeat (Fencing), Objekten je Savepoint (Objektfehler isoliert, Abhängige `skipped_dependency`), Item-Log, Befunden und Cursor. Deadlock/Lock-Wait (auch als `ccap_db_busy` der wiederholenden Transaktionsschicht): Chunk-Wiederholung 3× mit Jitter. Schritt-Budget min(20 s, `max_execution_time`/2), CLI/synchron unbegrenzt; Abbruch bei 75 % Speicher; adaptive Chunkgröße (AIMD 1…500, im Cursor). Lease `import` (eine je Site, TTL 900 s); zweiter Schritt → `busy`. Fehlerschwelle max(100, 2 % der Datensätze) nur für Commit-Fehler (IMP-B1.05). Abbruch an der Chunkgrenze (IMP-B1.09), Resume mit Vorprüfungen (IMP-B1.06/B1.07), automatische Wiederaufnahme nach T-Fehlern 1/5/15 min und Watchdog für hängende Läufe (höchstens 3, dann `aborted`), Rechteprüfung des Freigebenden vor jedem Hintergrundschritt (IMP-B1.10), Verwerfen-Aufräumen (vom Batch neu angelegte Objekte samt Source-Map) |
| `src/Import/ImportReport.php` | **neu.** Befundzeilen (`ccap_import_issue`) mit stabilem Regelcode (`IMP-V6.01/.02/.03/.06`, `IMP-B1.02…B1.11`, `VAL-…`), Schwere, Klasse F/O/W/T/N, Aktion, Phase, Paketdatei `data/<entität>/NNNNNN.json#<index>` und Fingerabdruck ohne Batch-ID (Dry-run und Commit gleich); Blockinsert; Keyset-Lesen; Zusammenfassung (Aktionen, Ergebnisse, Schwere, Klasse, Top-10-Regeln, zurückgestellte Kurse, Dry-run-Statistik); Export JSON (Kopf `report_version` 1, Seiten über `after`/`per_page`) und CSV (UTF-8 mit BOM, RFC 4180, CRLF, Zellen mit `= + - @ Tab CR` → `'`-Präfix); Meldung und Vorschlag beim Lesen übersetzt; keine Lernendendaten, keine Serverpfade, keine Rohfehlertexte (nur Fehlercodes) |
| `src/Import/ImportRoutes.php` | **neu.** `GET /imports/{id}`, `POST /imports/{id}/step`, `POST /imports/{id}/cancel`, `POST /imports/{id}/resume`, `GET /imports/{id}/report?format=json|csv` (Filter `severity`, `class`, `rule`, `phase`, `entity`); `private, no-store`; CSV wird über `rest_pre_serve_request` roh ausgeliefert |
| `src/Import/ImportCommand.php` | **neu.** `wp course-captain import status|run|cancel|report <id> [--format=csv|json] [--output=<datei>]` als `--user`; `run` startet/setzt fort/führt weiter ohne Zeitbudget (E-178) |
| `src/Import/ImportStopException.php`, `src/Import/LeaseLostException.php` | **neu.** Fataler Stopp mit Regel bzw. verlorene Lease (Chunk zurückgerollt) |
| `src/Import/ImportService.php` | Dry-run streamt das Paket Datei für Datei (`scan()`, nur ID-Mengen im Speicher statt aller Datensätze) und speichert Statistik (`records`, `files`, `package_bytes`, `object_errors`, `rules`, `threshold`, `duration_ms`, `peak_memory`, `records_per_s`, `estimate_commit`); Befunde mit `IMP-`-Codes und Klasse `O` im Blockinsert, höchstens 100 000; `run()` nutzt den Runner; `apply_item()`/`archive_object()` statt Gesamtanwendung; Archivierung erst in der Aktivierung; Source-Map in zwei JOIN-Abfragen statt einer Statusabfrage je Zeile; `discard()` räumt abgebrochene Läufe auf und ist nach Aktivierungsbeginn gesperrt; registriert Runner, Routen und CLI |
| `src/Import/PackageExporter.php` | Teildateien höchstens 5 000 Datensätze (`RECORDS_PER_FILE`, optional kleiner) statt einer Datei je Entität |
| `src/Kernel/CronHooks.php` | `ccap_hourly` ruft den Import-Watchdog (isoliert); `CronHooks::ALL` unverändert |
| `src/Ops/AuditView.php` | Audit-Aktionen `import_resumed`, `import_auto_resumed`, `import_cancel_requested`, `import_threshold_exceeded`, `import_course_held`, `import_discarded`, `import_report_exported` |
| `docs/api/routes.v1.json`, `docs/API-CHANGELOG.md` | Snapshot der fünf Routen, Changelog-Zeile; keine neuen Hooks |
| `tests/integration/ImportBatchTest.php` | **neu.** 12 Tests (`@group R1-41`, BIG-IDs) |

Keine Schemaänderung: Cursor, Spool-Name/-Prüfsumme, Abbruchwunsch, zurückgestellte Kurse und Wiederaufnahmezähler liegen in `ccap_import_batch.stats.run` (JSON); die Fragenrevision je Objekt im `message` des Item-Logs. Keine neuen Optionen, Meta-Schlüssel, Tabellen oder Hooks.

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler (bekannte Warnung `ImportTest.php:318`) · PHPStan L8 0 · Unit **104 / 2 817** · Integration **300 / 9 987** · Multisite **312 / 11 108** · Free-Guard 0 · Textdomain-Lint 0. `.pot` nicht neu erzeugt (neue Texte in `ImportReport`, `AuditView`).

## 3. Tests (`tests/integration/ImportBatchTest.php`)

| Test | Spec-ID | Inhalt |
|---|---|---|
| Dry-run großes Paket | BIG-001 | 5 000 Fragen in 5 Teildateien (+ Lektion/Modul/Kurs): Matrix `create` 5 000/1, `records` 5 003, `files` 8, Schwelle 101, Durchsatz und Schätzung > 0; Zeilenzahlen aller Inhaltstabellen vorher = nachher |
| Chunk-Commit + Reimport | BIG-020, BIG-022, BIG-011 | 1 500 Fragen, je Schritt genau ein Chunk (Budget 0): > 5 Schritte, Fortschritt monoton, Chunkgröße wächst (AIMD) und bleibt ≤ 500, 100 %, 1 500 Fragen veröffentlicht, Kurs veröffentlicht, 1 503 Item-Log-Zeilen, keine doppelte Source-Map; gleiches Paket erneut: alles `unchanged`, 0 neue Zeilen in 8 Inhaltstabellen, Vorgänger `superseded` |
| Absturz, Lease | BIG-023, BIG-024, BIG-027 | fremde lebende Lease → `busy`, nichts geschrieben; abgelaufene Lease übernommen; Absturz in Position 120 → `aborted`, Cursor = geschriebene Objekte, abgebrochener Chunk hinterlässt nichts; Resume → 300 Fragen, 0 Duplikate, 303 Item-Log-Zeilen, Audit `import_resumed` |
| Deadlock | BIG-026 | Deadlock (1213) und danach `ccap_db_busy` (503) im selben Chunk → wiederholt, kein Objektfehler, Ergebnis vollständig |
| Objekt-Isolation, Schwelle | E-176, BIG-035 | eine Frage scheitert beim Schreiben → Savepoint zurückgerollt, 39 von 40 veröffentlicht, Befund `IMP-B1.04`/`error`/`O`/`commit` mit `{"code":"ccap_conflict"}`, `failed_objects` 1; alle Schreibvorgänge scheitern → `aborted` mit `IMP-B1.05` (Klasse F), Audit `import_threshold_exceeded` |
| Validator hält Kurs zurück | BIG-031, R1-26 A-2, E-179 | Kurs 2 mit leerem Modul: VAL-Blocker im Report (`phase = activate`), Kurs 2 bleibt Entwurf, Kurs 1 aktiviert, `held_courses`, Audit `import_course_held`, JSON-Export mit Schwerefilter und Meldung |
| Absturz zwischen Kursen | BIG-032 | Kurs 1 aktiviert und konsistent, Kurs 2 unverändert; Verwerfen abgelehnt (409); Resume → `activating` → `published`; Kurs 1 genau eine Revision |
| Abbruch und Verwerfen | BIG-030, BIG-016 | Abbruch bei laufendem Schritt nur vorgemerkt, nächster Schritt stoppt an der Chunkgrenze (`IMP-B1.09`); Verwerfen bei lebender Lease 409 `ccap_import_busy`, danach Fragen, Revisionen und Source-Map wieder auf dem Vorher-Stand, Item-Log `discarded`, Audits |
| Resume-Vorprüfungen | BIG-037, BIG-038 | veränderter Spool → 409 `IMP-B1.07`; neuerer aktivierter Import derselben Quelle → 409 `IMP-B1.06` |
| Hintergrund, Watchdog | E-177, E-181, R0-38 §4.6 | Start plant `ccap_task_continue` `[import:<id>, 0]`; Kette ohne angemeldeten Nutzer bis `published`; Freigebender ohne Rechte → `aborted` `IMP-B1.10`; hängender Lauf: drei automatische Wiederaufnahmen (Audit `import_auto_resumed`), vierte → `aborted` `IMP-B1.02` |
| Report und REST | BIG-002, BIG-003, BIG-004, BIG-005 | Codes `IMP-V6.06`, `IMP-V6.03`, `IMP-B1.08` je Objekt; CSV mit BOM, Kopfzeile, CRLF, 4 Befunde, `'=HYPERLINK` neutralisiert, keine Serverpfade; REST-JSON-Seiten mit `next`, Klassenfilter, `private, no-store`; CSV roh mit BOM; Fortschritt ohne `storage_path`; Abonnent 403, unbekannter Batch 404, anonym 401; Audit `import_report_exported` |
| WP-CLI | E-178 | `run` ohne `--user` Exit 1; `run` → `published`; `status` 100 %; `report --format=json --output` mit Zusammenfassung; `cancel` auf veröffentlichtem Batch Exit 1 |

Bestehende Tests `ImportTest` (R1-15) und `GoldenMasterTest` laufen unverändert über den neuen Runner.

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Δ-38-1/-3 (Spalten `cursor`, `mode`, `cancel_requested`, `spool_sha256`, `retry_of`, `partial`) | ohne Schemaänderung im JSON `stats.run`; der Cursor wird trotzdem nur in der Chunk-Transaktion unter Zeilensperre geschrieben. Eigene Spalten mit der nächsten Migration (Index auf Status bleibt ausreichend) |
| A-2 | Planphase | ein Durchlauf ohne Zeitscheiben (Streaming, nur ID-Mengen und Source-Map im Speicher, also O(Anzahl IDs)); Dry-run ebenfalls in einem Request. Zeitscheiben für `plan`/Dry-run und Messung M-01…M-16 auf R-M: Folgeaufgabe (OP-38-3) |
| A-3 | Medien, Blueprints, Flashcards (R1-15 A-2) | nicht gebaut; deshalb wird VAL-027 (Video ohne Quelle) bei der Aktivierung nur als Warnung gemeldet und hält den Kurs nicht zurück (`MEDIA_PENDING_RULES`) |
| A-4 | Reihenfolge der Aktivierung | Fragen → Archivierung → Kurse (statt Kurse → Fragenzeiger), damit Validator und Kursrevision die neuen Fragen und die archivierten Lektionen sehen |
| A-5 | Verwerfen | entfernt nur vom Batch **neu angelegte** Objekte (Fragen samt Revisionen, Posts, Kurs-Modul-Zeilen, Source-Map). Aktualisierungen von Lektionen/Modulen werden wie in R1-15 A-3 bereits in `importing` geschrieben und bleiben; Fragenänderungen sind nur unveröffentlichte Entwürfe. Archivierung erfolgt erst in der Aktivierung, muss also nicht rückgängig gemacht werden |
| A-6 | Fehlerschwelle (OP-38-2) | fest max(100, ⌈2 %⌉) der Datensätze des Laufs, nicht je Phase; Setting `advanced.import_error_threshold` und Filter folgen mit dem Settings-Register |
| A-7 | Befundgrenze 100 000 | Dry-run speichert höchstens 100 000 Zeilen und gilt darüber als `failed`; kein Sammelbefund je Regel |
| A-8 | Report | ohne `uid`/`title` (keine Inhaltsauszüge) und ohne JSON-Pointer (`path` leer, Index in `file`); NDJSON-Format, Konfliktentscheidung je Objekt (`keep_local/take_source`), Gruppenansicht und Admin-Oberfläche (Fortschrittsbalken, „Im Browser fortsetzen“) nicht gebaut; CSV wird in `php://temp` (≤ 2 MB im Speicher) erzeugt statt direkt gestreamt |
| A-9 | Kind-Batch „Fehlgeschlagene wiederholen“ (§5.4), Paketserien (E-310), Rechte-Statistik je Entität | nicht gebaut; Datenfehler werden über den idempotenten Reimport eines korrigierten Pakets behoben |
| A-10 | Speicherpflege | `wp_cache_flush_runtime()` nur mit persistentem Objekt-Cache; Caches im Chunk geschriebener Posts werden nach Rollback geleert |
| A-12 | „Rechteprüfung E2E“ (DoD) | umgesetzt als Berechtigungsprüfung über alle Wege (REST 401/403/404, Policy je Schritt, Freigebender im Hintergrund). Das Lizenz-/Rechtemodell der Inhalte (R0-10 §2, IMP-R1.xx, BIG-010/BIG-021) fehlt weiterhin (R1-15 A-4) und ist nicht Teil dieses Commits |
| A-11 | Neue Regelcodes | `IMP-B1.10` (Freigebender ohne Rechte) und `IMP-B1.11` (unerwarteter Fehler) ergänzen den Katalog R0-38 §2.3 |

## 5. Offene Klärungen

- Soll die Kursvalidierung schon im Dry-run (Kontext `import_dry_run`, „aktivierbar ja/nein“) laufen? Dafür bräuchte es eine Staging-Kopie (R0-23 §4, E-104), die R1-27 liefert.
- Darf der Watchdog Läufe automatisch fortsetzen, deren Freigebender inzwischen keine Rechte mehr hat? Umgesetzt: nein, der nächste Hintergrundschritt bricht mit IMP-B1.10 ab.

Review durch zweite Person vor Merge (AG-05).
