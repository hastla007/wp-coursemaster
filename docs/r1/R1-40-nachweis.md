# R1-40 · Datenbankmigration mit Lock und Wiederaufnahme – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-b`
**DoD (TODO R1-40):** Parallele Admin/Cron/CLI-Upgradeversuche, Crash, Retry, Backfill und Restore ohne doppelte Schemaaktionen oder Datenverlust getestet.
**Quellen:** R0-37 §2.1–2.3 (Trigger, Entscheidungsfunktion, Zeitbudget), §3.1 (L-A/L-B, Fencing, Bootstrap), §3.3 (W1/W2), §4.3–4.6 (Statusmodell, Backfill, Fehlerbehandlung, Retry/Poison), §6.1 (Restore), §7 (MIG-001…027); R0-21 §2.4/§2.5 (`ccap_lease` mit Fencing, `GET_LOCK = NULL`); R0-06 §6.4; R1-02-Nachweis O-1/O-2. Clean Room (E-01): LB/Pass nur als Verhaltensbeleg aus R0-37, kein Quelltext gelesen oder übernommen.

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Storage/Migrator.php` | Lauf in `run_structure()` (P2/P3 + Versionswechsel) und `run_backfills()` (P5) aufgeteilt. Neu: Zeitbudget je Kontext, Status `partial` (Budget/transient → `ccap_migrate` sofort neu geplant) und `poison`; Poison-Sperre für automatische Läufe, CLI = manueller Lauf; Shutdown-Handler `on_shutdown()`/`handle_fatal()` (PHP-Fatal → laufender Schritt `failed`, nur mit gültigem Fence, Meldung ohne Pfade); unerwartete Exceptions markieren nur den aktuellen Schritt `failed`; Fehlerinjektionspunkte; Strukturphase läuft auch, wenn ein Strukturschritt offen ist (unterbrochener Lauf bei aktueller Version); Bootstrap-Options-Sperre gilt, bis **beide** Bootstrap-Tabellen existieren; Zähler `course_captain_named_lock_unavailable`; `report()` für Health/CLI (Zustand `ok/pending/running/backfill/failed/blocked/newer`, Poison, Prüfsummenabweichung, Lease); Testnähte `with_budget()`, `with_batch_size()`, `without_named_lock()` (`@internal`), Backfill-Liste per Konstruktor (Kern: leer) |
| `src/Storage/StepLog.php` | `start()` erkennt verwaiste `running`-Zeilen (`crash_count + 1`), ab 3 Abstürzen `failed` + `blocked_reason = poison` statt Start; manueller Lauf setzt Poison und Zähler zurück; Cursor/Fortschritt bleiben beim Neustart erhalten. `failed()` setzt Poison ab 5 Versuchen. Neu `progress()` (Cursor + `rows_done` mit Fence-Prüfung), `set_total()`, `pause()` (Budget → `pending`, kein Absturz) |
| `src/Storage/Backfiller.php` | P5-Engine: Cursor auf PK, **eine TX je Batch** mit Lease-Heartbeat/Fencing, `SELECT … FOR UPDATE`, Datenänderung und Cursor-Schreiben; 1205/1213 bis 3× wiederholt, danach `pending` ohne `failed`; adaptive Halbierung bei Batch > 2 s; Verify-Abfrage am Ende, weitere Durchläufe ab Cursor 0, nach 3 Durchläufen `failed` mit Zähler; Batchgröße CLI 500 / sonst 200; Cursorformat `<durchlauf>:<id>` |
| `src/Storage/BackfillStep.php` | Schnittstelle eines Backfill-Schritts (ID, Modul, Tabelle, Prädikat der offenen Zeilen, `apply()`, Prüfsumme) |
| `src/Storage/BackfillGate.php` | `done()` (erst wahr, wenn Fingerprint aktuell und ID nicht in `course_captain_backfill_open`), `open()`, `close()`; Gate schließt vor der Strukturphase eines Upgrades |
| `src/Storage/MigrationBudget.php` | CLI unbegrenzt, sonst min(20 s, `max_execution_time`/2) |
| `src/Storage/MigrationFault.php` | Hook `course_captain_migration_fault` (`step`, `point` = `before_ddl/after_ddl/in_batch/after_batch/before_status`), nur bei `COURSE_CAPTAIN_TESTING === true` |
| `src/Ops/MigrateCommand.php` | `wp course-captain migrate [--status] [--format=json]`: CLI-Kontext ohne Budget/Backoff, Exit 0 bei `done/none/skipped_locked`, sonst 1; `--status` nur lesend; testbarer Kern `execute()` |
| `src/Ops/HealthChecks.php` | Site-Health-Test `course_captain_migration` (kritisch bei `failed`/`blocked`/`newer`; Hinweis bei `pending`/`running`/`backfill`, Prüfsummenabweichung, Läufen ohne `GET_LOCK`) |
| `src/Kernel/Plugin.php` | `schema_ready()`; `ccap_outbox_dispatch` pausiert in W1 (Nachplanung +30 s statt Zustellung); Registrierung des CLI-Kommandos |
| `src/Kernel/CronHooks.php` | `run_hourly()` pausiert in W1 (keine neuen Hooks, `CronHooks::ALL` unverändert) |
| `src/Kernel/UninstallManifest.php` | Option `course_captain_named_lock_unavailable` |
| `tests/integration/bootstrap.php` | Konstante `COURSE_CAPTAIN_TESTING` |
| `tests/integration/MigrationResumeTest.php` | 21 Tests; Mehrprozessfälle per `pcntl_fork` mit eigener DB-Verbindung je Prozess und Startbarriere, harte Abbrüche per `SIGKILL` |
| `tests/integration/MigrationMultisiteTest.php` | MIG-020 (Sperren je Site) |
| `tests/integration/ProbeBackfill.php` | Test-Backfill auf eigener Probe-Tabelle (`val = src * 2`, Schreibzähler je Zeile) |
| `tests/unit/MigrationBudgetTest.php` | Budget je Kontext, Cursorformat |

Keine neue Tabelle, kein neuer Cron-Hook, keine neue Audit-Aktion, kein neuer Einstellungsschlüssel.

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler (1 bekannte Warnung `ImportTest.php:318`) · PHPStan L8 0 · Unit **90** · Integration **229 / 2 408** · Multisite **239 / 2 469** · Free-Guard 0 · Textdomain-Lint 0. `MigrationResumeTest` dreimal hintereinander grün (je ~24 s).

## 3. Tests

| ID | Test | Umfang |
|---|---|---|
| MIG-001 / MIG-002 | `test_MIG_001_parallel_triggers_migrate_exactly_once` | 8 Prozesse hinter Startbarriere: Admin, Cron, Aktivierung, 2× CLI, 3× Frontend. Genau ein `done`, vier `skipped_locked`, beide CLI-Läufe Exit 0, DDL-Zählung (`CREATE/ALTER TABLE` je Prozess) nur beim Gewinner, jeder Schritt genau ein Versuch mehr, `crash_count = 0`, `ccap_migrate` höchstens einmal, Lease freigegeben |
| MIG-003 | `test_MIG_003_lease_alone_excludes_without_named_lock` | Erstinstallation, 4 Prozesse ohne `GET_LOCK`: Bootstrap-Options-Sperre + Lease schließen aus, genau ein Läufer, kein DDL außerhalb, Schema vollständig ohne Drift, Sperroption entfernt, Zähler > 0, Health-Hinweis |
| MIG-004 | `test_MIG_004_lost_named_lock_stops_before_next_ddl` | Named Lock geht mitten im Lauf verloren und gehört einer zweiten Verbindung → `fenced`, kein weiteres DDL, Schritt bleibt `running` (Wiederaufnahmepunkt), Version unverändert; nächster Lauf vollendet, `crash_count = 1` |
| MIG-005 | `test_MIG_005_zombie_runner_is_fenced_after_takeover` | Zwei Prozesse ohne `GET_LOCK`: erster pausiert in P2, Lease läuft ab (Uhr + 1 h im zweiten), zweiter übernimmt (Fence + 1) und vollendet; erster wacht auf → `fenced`, 0 DDL danach, Seat-Schritt genau einmal |
| MIG-006 | `test_MIG_006_kill_during_dbdelta_resumes` | `SIGKILL` beim 10. `CREATE TABLE` einer Erstinstallation; nächster Lauf übernimmt die Lease, wiederholt `dbDelta`, Schema/FKs vollständig, `crash_count = 1` |
| MIG-007 | `test_MIG_007_kill_after_ddl_marks_done_without_second_ddl` | `SIGKILL` nach DDL vor Statuswrite (P3); nächster Lauf: kein `ALTER` auf `ccap_seat`, Schritt `done`, `crash_count = 1` |
| MIG-008 | `test_MIG_008_kill_inside_backfill_batch_resumes_at_cursor`, `test_deadlock_in_batch_is_retried_then_paused` | 1 000 Zeilen, `SIGKILL` in der 4. Batch-TX: Cursor `0:300`, Batch 4 zurückgerollt; Fortsetzung → alle Werte korrekt, **jede Zeile genau einmal geschrieben**, `crash_count = 1`, Gate offen. Deadlock 1213: Wiederholung in der TX, nach 3 Versuchen `pending` statt `failed`, kein Doppelschreiben |
| MIG-009 | `test_MIG_009_time_budget_splits_backfill_over_runs`, Unit `MigrationBudgetTest` | Budget aufgebraucht → je Lauf ein Batch, `partial`, Schritt `pending`, `ccap_migrate` neu geplant; W2: Schreiben nicht gesperrt, Frontend plant nur, Gate geschlossen, Health-Hinweis; 6 Läufe (5 Batches + Verify), kein Absturzzähler; Budgetformel |
| MIG-010 | `test_MIG_010_fatal_in_step_marks_failed_and_keeps_w1` | Fatal (Speicher) in Schritt 0002 vor Statuswrite: Shutdown-Handler → `failed` ohne Pfad, Lease frei; 0000/0001 `done`, 0003 nicht gelaufen, Version alt, W1 (schreibend 503, lesend durch), Admin/Cron `wait` (Backoff), Health kritisch; CLI vollendet, Health gut |
| MIG-011 | `test_MIG_011_repeated_crash_poisons_step`, `test_five_failed_attempts_poison_step` | 3× `SIGKILL` im selben Schritt → nächster Lauf setzt `poison`; Cron/Admin/Frontend `none`, kein Versuch; Health kritisch mit Schritt-ID; CLI vollendet und löscht die Marke. 5 fehlgeschlagene Versuche → Poison |
| MIG-012 | `test_MIG_012_permanent_error_fails_step_and_cli_retries` | Fehler 1142 (simuliert per Fehlerinjektion) → Schritt `failed` mit DB-Meldung, W1, Cron wartet bis Backoff-Ende, CLI ignoriert Backoff (Exit 1 solange Ursache besteht), danach Exit 0 |
| MIG-013 | `test_MIG_013_double_trigger_runs_once` | `upgrader_process_complete` 2× (nur Planung, ein Ereignis), `admin_init` migriert, fälliger Cron und Reaktivierung ändern nichts; jeder Schritt genau einmal; keine doppelten Cron-Ereignisse |
| MIG-017 | `test_MIG_017_verify_pass_catches_rows_below_cursor` | Während des Backfills schreibt „alter Code“ eine Zeile unter dem Cursor und neuer Code eine darüber → zweiter Durchlauf, keine Zeile verloren; dauernd wiederkehrende Zeile → `failed` „Verify found 1 rows after 3 passes“, Gate bleibt zu |
| MIG-020 | `MigrationMultisiteTest::test_MIG_020_locks_are_independent_per_site` | Site 1 gesperrt → Site 2 migriert trotzdem, Site 1 `skipped_locked` und unverändert; nach Freigabe `done` |
| MIG-021 | `test_MIG_021_restore_of_mid_migration_snapshot_continues` | Snapshot (Schrittprotokoll, verwaiste Lease, Daten, Optionen) mitten in P5; nach Abschluss Restore des Snapshots → Übernahme der Lease, Ergebnis identisch mit Referenz, keine Zeile doppelt geschrieben |
| MIG-022 (Teil) | `test_cli_status_reports_without_writing` | `--status` schreibt nichts (Schritte/Lease unverändert), Exit 1 bei fälliger Migration, 0 bei `ok`; `--format=json` |
| MIG-023 (Laufzeit) | `test_MIG_023_changed_definition_is_reported_not_rerun` | geänderte Prüfsumme eines `done`-Schritts → `report()`/Health-Hinweis, kein erneuter Lauf |
| MIG-024 | `test_MIG_024_newer_schema_is_never_touched` | kein DDL, CLI Exit 1 `newer`, Health kritisch, schreibend 503 |
| MIG-025 | `test_MIG_025_background_jobs_pause_in_w1` | stündliche Wartung und Outbox-Zustellung pausieren in W1 (Outbox nachgeplant), laufen nach W1 |
| MIG-026 | `test_MIG_026_ajax_and_admin_post_only_schedule` | `admin-ajax`/`admin-post` migrieren nie, nur ein geplantes `ccap_migrate` |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Mehrprozess-Läufer (R0-37 „MP“) | `pcntl_fork` in PHPUnit statt eigenem Lastskript: jeder Kindprozess öffnet eine eigene Verbindung, ersetzt `$wpdb`, wartet an einer Dateibarriere und endet per `SIGKILL` (kein Destruktor berührt die geerbte Verbindung). Test wird übersprungen, wenn `pcntl`/`posix` fehlen. 50 REST-Requests (MIG-001) auf 3 Frontend-Prozesse reduziert; die Frontend-Logik ist prozessunabhängig |
| A-2 | Gewinner-Verzögerung | Der Prozess mit den Sperren schläft 1,5 s am Punkt `before_ddl` von P2, damit alle anderen garantiert kollidieren (sonst könnte ein später Start nur `none` liefern) |
| A-3 | DDL-Zählung | über den `query`-Filter (`CREATE/ALTER TABLE` je Prozess) statt General-Log; Ergebnis gleichwertig für „kein DDL doppelt“ |
| A-4 | PHP-Fatal (MIG-010) | Ein echter Fatal im Kindprozess würde WP-/PHPUnit-Shutdown-Funktionen ausführen und die geerbte Verbindung schließen. Der Test ruft daher den registrierten Handler `handle_fatal()` mit der Struktur von `error_get_last()` auf und beendet den Prozess per `SIGKILL`; die Registrierung per `register_shutdown_function` ist Code, nicht getestet |
| A-5 | Fehler 1142 (MIG-012) | Rechteentzug ist im Testsystem nicht zuverlässig möglich; Fehler wird per `course_captain_migration_fault` mit Fehlercode 1142 injiziert |
| A-6 | Verbindungsverlust (MIG-004) | simuliert durch `RELEASE_LOCK` auf der eigenen und `GET_LOCK` auf einer zweiten Verbindung; `check_connection()`/Reconnect selbst (RB-05) nicht eingebaut |
| A-7 | Kern-Backfills | Schema 1.0.0 hat keinen Backfill-Schritt (`Migrator::core_backfills()` leer); die Engine wird mit `ProbeBackfill` auf einer Probe-Tabelle bewiesen. Registrierung künftiger Schritte im Code (keine öffentliche Filter-API) |
| A-8 | Zustand „neuer Backfill ohne Versionswechsel“ | Ein registrierter Backfill ohne Schrittzeile zählt als offen (`steps_open`), damit ein Release, das nur einen Backfill bringt, ihn auch ausführt |
| A-9 | W1-Planung nach Fehlschlag | Der 503-Pfad (GM-149) plant `ccap_migrate` sofort; der Cron-Lauf entscheidet dann `wait` und plant auf das Backoff-Ende. Unverändert übernommen |
| A-10 | Backfill-Lease-TTL | Heartbeat je Batch mit 600 s; die Statusschreibungen setzen weiterhin 1 800 s (gemeinsamer Guard des Schrittprotokolls) |
| A-11 | Aktivierung bei Poison | `run_on_activation()` bricht wie bei `failed` ab (Meldung mit CLI-Hinweis) |

### Nicht gebaut (bewusst, offen)

| Nr. | Punkt | Ziel |
|---|---|---|
| O-1 | P4-Datenschritte, Contract P6 (MIG-018, E-172, `--allow-contract`), Downgrade/Re-Upgrade mit Verify-`reopen` (MIG-019) | sobald es Schritte gibt (frühestens Schema 1.1/1.2) |
| O-2 | CLI `--dry-run` (MIG-022 voll), `--step`, `--all-sites`/`--parallel` (MIG-020 Massenlauf, Netzwerkaktivierungs-Teil liegt in MS-001/MS-002) | R1-28 / R1-24 |
| O-3 | Fristverlängerung E-173 und Prüfungs-Endpunkte in W1 (MIG-015), Auto-Update-Aufschub E-174 (MIG-027), Overdue-Fallback mit 30 parallelen Frontend-Requests (MIG-014; Entscheidung bereits durch Unit-Tests der Entscheidungsfunktion belegt) | R1-08/R1-20, R1-23 |
| O-4 | K-2-Smoke-Suite „Code N+1 gegen Schema N“ (MIG-016), CI-Prüfsummenliste (MIG-023 CI-Teil) | sobald eine zweite Schemaversion existiert |
| O-5 | `ccap_task_continue`-Ketten in W1: nicht pausiert, weil ein übersprungenes Ereignis die Kette beenden würde; Admin-Hinweise W1/W2 und Knopf „Jetzt erneut versuchen“ | R1-38 |

### Klärungsfragen

1. Soll eine Aktivierung bei einem Poison-Schritt wirklich abbrechen (A-11) oder mit Admin-Hinweis weiterlaufen (W1 bleibt bestehen)?
2. Reicht der Fork-basierte Mehrprozesstest als Nachweis für MIG-001/003/005, oder wird zusätzlich ein Staging-Lauf mit echtem WP-CLI und HTTP-Last (R0-16 §3.1) verlangt?
3. Soll `--status` bei laufendem Backfill (`backfill`/`running`) Exit 0 liefern (umgesetzt) oder einen eigenen Code?

Review durch zweite Person vor Merge (AG-05).
