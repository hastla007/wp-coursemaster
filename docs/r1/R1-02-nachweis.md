# R1-02 · Storage, Migration und Repositories – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v` (setzt auf `claude/blissful-curie-tuuf7q` @ `a0dab57` auf)
**DoD (TODO R1-02):** Schema versioniert; sichere Install-/Upgrade-/Backfill-Tests; keine verlorenen Lernstände.
**DoR (R0-24 §4.2/4.3):** `CourseCaptain\Storage` (Schema, Migrator, Repositories); Tests GM-163, GM-164, GM-168, MIG-001 (Teil), GM-149 (R0-12 §3); Kommando `composer test -- --group=R1-02`.
**Clean Room (E-01/E-327):** Kein Zugriff auf den LB-Quelltext. Quellen: `docs/r0/R0-06`, `R0-37`, `R0-21`, `R0-23`, `R0-09`, `R0-15`, `R0-27`, `R0-38`, `R0-20`, `R0-05`, `R0-12`, `R0-24`.
**Vorab geprüft:** Die Arbeit aus R1-01 wurde vollständig nachgeprüft (alle Kommandos aus R1-01-Nachweis §2 lokal wiederholt, identische Ergebnisse).

---

## 1. Dateien

| Datei | Inhalt | Quelle |
|---|---|---|
| `src/Storage/Schema.php` | Einzige Quelle aller **53 Kern-Tabellen** (`dbDelta`-fähig, InnoDB, utf8mb4, Eigentumskommentar `course-captain:core`), 37 Fremdschlüssel nur zwischen `ccap_`-Tabellen, 2 generierte Spalten mit UNIQUE, Personendaten-Registry, `db_version()` = Semver + Definitions-Hash, `fingerprint()` | R0-06 §2, §4, §4.9, §6.1; E-48; R0-21 D21-4; R0-20 D-20-03 |
| `src/Storage/Database.php` | Zugriffsschicht für alle Repositories: Tabellenname je Site, UTC-Zeit aus PHP, UUID v4, Transaktionen mit explizitem Rollback (verschachtelt = gleiche TX), `compare_and_swap()` auf `row_version`, jeder DB-Fehler → `StorageException` | R0-06 §2 Nr. 5/7, §4.0; R0-21 T-3 |
| `src/Storage/StorageException.php`, `MigrationFencedException.php` | Fehlerklasse mit MySQL-Fehlernummer (1060/1061/1205/1213/1054/1146), gekürzte Meldung ohne SQL-Daten | R0-37 §4.5, R0-17 |
| `src/Storage/SchemaInspector.php` | Ist-Zustand über `information_schema` (Tabellen, Engine, Kommentar, Spalten, Indizes inkl. Spaltenfolge, FKs) und **Drift-Check** | R0-06 §6.2, R0-37 P2, I-M1 |
| `src/Storage/NamedLock.php` | `GET_LOCK(name, 0)` je Site, Name `ccap_mig_<md5-16>`, `IS_USED_LOCK = CONNECTION_ID()` vor DDL | R0-37 §3.1 L-A |
| `src/Storage/Lease.php` | Lease in `ccap_lease` mit Fencing-Token: `INSERT IGNORE`, Übernahme nur mit Named Lock oder nach Ablauf, Heartbeat/Freigabe nur mit passendem Fence | R0-21 §2.1/§2.4, R0-37 L-B |
| `src/Storage/StepLog.php` | Repository für `ccap_migration_step` (`pending/running/failed/done`, `attempts`, `crash_count`, Prüfsumme, `released_in`); jeder Statuswechsel nur mit gültiger Lease | R0-37 §4.3 |
| `src/Storage/MigrationTrigger.php` | reine Entscheidungsfunktion `run/schedule/wait/none/blocked` (Regeln 1–8) | R0-37 §2.2 |
| `src/Storage/Migrator.php` | Phasen P0 (Bootstrap-Tabellen) → P1 (Schema neuer als Code → keine Aktion) → P2 (`dbDelta` + Drift) → P3 (generierte Spalten + UNIQUE, FKs nicht fatal in `course_captain_fk_issues`) → Version/Fingerprint/`compat_min`. Trigger T-1…T-5, Overdue-Fallback 300 s, Backoff 15 min, Options-Sperre für den Bootstrap ohne `GET_LOCK`, Doppelprüfung der Version am Cache vorbei, REST-Wartungsfilter (`503 ccap_maintenance`, `Retry-After: 30` für schreibende Anfragen an `course-captain/v1`) | R0-37 §2–§4, R0-06 §6.4, GM-149 |
| `src/Infrastructure/Migration/MigrationRunner.php`, `NullMigrationRunner.php` | Schnittstelle um `run_cron()`, `on_request()`, `conflicting_tables()` erweitert | R0-37 T-4/T-5, KOMP-131 |
| `src/Kernel/Plugin.php` | echter `Migrator` statt Null-Runner; `ccap_migrate`-Handler; T-4 auf `init` (Frontend/REST/Ajax/admin-post, nicht Cron); Test-Naht `override_migration_runner()` (`@internal`) | R0-37 §2.1 |
| `src/Kernel/Activator.php` | **KOMP-131 / R1-01 O-7:** Gleichnamige Tabelle ohne Eigentumsmarke → Abbruch per `wp_die` vor jedem Schreibzugriff | R0-20 §3.2 |
| `src/Kernel/UninstallManifest.php`, `Uninstaller.php` | Tabellenliste aus `Schema::drop_order()`; Löschung (nur bei bestätigtem Flag) entfernt nur Tabellen mit Marke `course-captain:core`, Kinder zuerst; Option `course_captain_migration_bootstrap_lock` ergänzt | R0-16 §2.5, Δ-37-4, KOMP-131 |
| `languages/course-captain.pot` | neu erzeugt | J-03 |
| `tests/unit/SchemaTest.php`, `tests/unit/MigrationTriggerTest.php` | DDL-Hygiene (kein `CURRENT_TIMESTAMP`, keine FKs/generierten Spalten im `CREATE`, Integer mit Anzeigebreite, UNIQUE-Spalten `NOT NULL`, FK-Typen gleich, Eltern vor Kindern, FK-Name ≤ 64), Entscheidungstabelle R0-37 §2.2 | – |
| `tests/integration/SchemaTestCase.php`, `SchemaTest.php`, `SchemaMultisiteTest.php` | echte Tabellen (die WP-Testsuite würde sonst temporäre Tabellen anlegen, die `information_schema` nicht zeigt); Aufräumen inkl. `COMMIT`, weil DDL die Testtransaktion beendet | – |

## 2. Kommandos und Ergebnisse (lokal, 16.11.2026)

Umgebung: PHP 8.3.6, MariaDB 10.11.14, WordPress 7.1.4, WP-Testsuite 7.1.3, PHPUnit 9.6.39.

| Kommando | Ergebnis |
|---|---|
| `vendor/bin/phpcs` / Bootstrap-Compat | 0 Fehler, 0 Warnungen |
| `vendor/bin/phpstan analyse` | Stufe 8: No errors (eine begründete Ignore-Zeile in `Database::prepare()`: Tabellennamen aus Konstanten, Werte als Platzhalter) |
| `composer test` | **OK (68 Tests, 2 369 Assertions)** |
| `composer test -- --group=R1-02` | OK (19 Tests) |
| `composer test:integration` | **OK (25 Tests, 314 Assertions)**, viermal mit wechselndem Seed |
| `composer test:integration-ms` | **OK (34 Tests, 367 Assertions)**, dreimal mit wechselndem Seed |
| `composer test:cli-ms002` | MS-002: OK |
| `php tests/guards/free-guard.php` | 30 Dateien, 0 Treffer |
| `php tests/guards/textdomain-lint.php` | 28 Dateien, 0 Probleme |

## 3. Test-IDs

| ID | Test | Umfang in R1-02 |
|---|---|---|
| GM-163 | `SchemaTest::test_GM_163_activation_creates_complete_schema` | voll: alle Tabellen, InnoDB, Eigentumsmarke, FK-Anzahl = Soll, Drift leer, Version/Fingerprint/compat_min, generierte Spalten, Caps, Schrittstatus |
| GM-164 | `SchemaTest::test_GM_164_reactivation_and_rerun_are_idempotent` | voll: Deaktivieren/Reaktivieren/erzwungener Lauf → `dbDelta` meldet nichts, keine doppelten FKs; Antwort ohne existierende Revision vom FK abgewiesen |
| GM-168 | `SchemaTest::test_GM_168_missing_index_is_restored_by_migration` | voll |
| GM-149 | `SchemaTest::test_GM_149_writes_get_503_while_migration_is_due` | REST-Teil voll (503 + `Retry-After`, lesend 200, `ccap_migrate` einmal geplant, danach normal); Formulare/Ajax folgen mit den ersten Endpunkten (R1-05 ff.) |
| KOMP-131 | `SchemaTest::test_KOMP_131_foreign_table_blocks_activation_without_writes` | Tabellen voll (CPT/Shortcode-Teil mit R1-04/R1-28) |
| MS-005 | `SchemaMultisiteTest::test_MS_005_schema_is_created_and_removed_per_site` | Tabellenteil (in R1-01 offen) jetzt voll |
| REC-019 | `SchemaTest::test_REC_019_uninstall_drops_tables_only_when_confirmed` | Tabellenteil vorgezogen; Dateien/Neuinstallation in R1-23 |
| MIG-001 (Teil) | `SchemaTest::test_second_runner_skips_while_locked`, `test_lease_fencing` | zweite Verbindung hält den Named Lock → `skipped_locked`; Fencing. Vollprobe mit Startbarriere/50 Requests in **R1-40** |
| R0-21 D21-4 | `SchemaTest::test_one_active_seat_per_user_and_group` | voll für `ccap_seat` |
| R0-06 §2 Nr. 5 | `SchemaTest::test_compare_and_swap_and_rollback` | CAS und Rollback der Repository-Basis |
| R0-06 §6.1 WN | `SchemaTest::test_newer_schema_is_left_alone` | voll |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung | Begründung |
|---|---|---|---|
| A-1 | Tabellenzahl | **53** statt 52 (R0-06 §2) bzw. „39 + 1“ (R0-20) | Zählung der in R0-06 §4.1–§4.7 (42), §4.4a (3) und §4.10 (8 Kern) aufgeführten Tabellen; die Zahl in §2 stammt aus einem früheren Stand. Dokumente bei Gelegenheit angleichen |
| A-2 | `ccap_privacy_tombstone` | **nicht** angelegt | R0-17 §5.5 übergibt sie an R0-06, dort ist sie nicht eingearbeitet; Ziel-Task R1-17 legt sie per eigenem Schritt an |
| A-3 | Spalten ohne exakte Vorgabe | `ccap_learner_lock` (Grund-Code, Freitext, Zeitraum, gesetzt/aufgehoben von), `ccap_reset_journal` (Vorzustand JSON, Akteur, Rolle, Audit-Referenz, Rücknahme) nach den Stichworten in R0-06 §4.10/R0-07/R0-13 | additive Ergänzung später möglich (Expand) |
| A-4 | Zusätzliche Indizes | Index auf jede FK-Spalte ohne passendes linkes Präfix (`attempt.question_id`, `course_progress.course_revision_id`/`completed_revision_id`, `certificate.course_revision_id`/`supersedes_id`, `assignment_submission.course_revision_id`) | InnoDB würde sonst unbenannte Indizes anlegen; so bleibt der Drift-Check eindeutig |
| A-5 | FK auf `ccap_activity_progress.course_revision_id` (Δ-23-7) | kein FK | Δ-23-7 nennt keine Löschregel (anders als Δ-23-1) |
| A-6 | FK-Namen | `ccapfk_` + md5(Präfix + Tabelle + Spalte) | FK-Namen sind je Datenbank eindeutig; mehrere Sites teilen eine DB |
| A-7 | Spaltennamen | in Backticks (`cursor` ist reserviert) | Spaltenname aus R0-37 §4.3 beibehalten |
| A-8 | `db_version`-Format | `<semver>+<12 hex>` | R0-06 §6.1 „Semver plus Hash“ |
| A-9 | Fehlernummern | aus der Fehlermeldung abgeleitet | `$wpdb` stellt die Nummer nicht öffentlich bereit; WPCS verbietet `mysqli_errno` |
| A-10 | Pseudo-Schritt `core:0000_tables` | `dbDelta`-Phase wird in `ccap_migration_step` protokolliert | Backoff/Health brauchen einen Fehlerzeitpunkt auch für Drift |
| A-11 | Testisolation | Integrations-Basisklasse setzt den Null-Runner, Schema-Tests den echten Migrator | DDL beendet die Testtransaktion; Lifecycle-Tests bleiben unverändert aussagekräftig |
| A-12 | Altoptionen im Manifest | `course_captain_lock_migration`, `course_captain_migration_failed` bleiben im Uninstall-Manifest | R0-37 ersetzt sie durch die Tabelle; Löschen nicht vorhandener Optionen ist harmlos, Bereinigung mit R1-31 |

## 5. Offen / Übergaben

| Nr. | Punkt | Ziel |
|---|---|---|
| O-1 | Absturzbehandlung (Shutdown-Handler, `crash_count ≥ 3` → Poison), Zeitbudget, Backfill-Engine (P5) mit Cursor/Batches, Contract (P6), Fristverlängerung E-173, Vollprobe MIG-001…027 | R1-40 |
| O-2 | `wp course-captain migrate [--dry-run] [--status] …` | R1-40 / R1-28 |
| O-3 | Admin-Hinweise W1/W2/WN, Site-Health-Test `ccap_migration`, Systemstatus der FK-Probleme | R1-38 |
| O-4 | W1-Sperre für `admin-ajax`/`admin-post`-Formulare und CPT-Speichern | mit den ersten Endpunkten (R1-05 ff.) |
| O-5 | Konkrete Fach-Repositories (Kurs, Frage, Grant …) | jeweils in R1-04 ff. auf Basis von `Database` |
| O-6 | Review durch zweite Person vor Merge (AG-05) | Auftraggeber |
