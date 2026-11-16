# R0-37 · Schema-Upgrade unter Konkurrenz und Ausfall (Entwurf)

**Status:** **Vorgeschlagen – Freigabe Architektur/Auftraggeber offen.** Task R0-37 ist **nicht** abgehakt. Keine Implementierung, kein Commit.
**Stand:** 16.11.2026
**Bezug:** TODO R0-37 (DoD: Lock-Strategie, Upgrade-Trigger, idempotente dbDelta-/Backfill-Schritte, unterbrochene Migration, Multisite-Scope, Rollback-Grenzen), Ziel-Task **R1-40** (Migration mit Lock und Wiederaufnahme), außerdem R1-02, R1-23, R1-42; `docs/r0/R0-06-datenmodell-adr.md` §6.1–6.6, E-52, D-1, D-3 und alle Nachträge (insb. R0-21: `cc_lease`; R0-21 D21-4: generierte Spalten); `R0-16` §2.1 (Upgrade-Runbook, Abbruchtabelle, U-1…U-9), §2.2 (R-A…R-D), I-1…I-5, REC-006/007/008/011/021/024/025; `R0-21` §2.1 (Mutex/Lease), §2.4 (M0, TTL, Fencing, Verbindungsbindung), §2.5 (`GET_LOCK = NULL`), §2.6 (T-3), CON-012/045; `R0-36` §2.2/2.5 (Pass-Muster); `R0-25` MS-R-01/02/07/08 (Entwurf, ungetrackt); `R0-14` RB-01/02/05/08; `R0-19` §2.1 (`cc_maintenance`), §4.7 (`migrate`), Cron-Tabelle (`cc_migrate`).
**Entscheidungen als Annahme (nicht beschlossen):** E-07 (offiziell Single-Site, Aktivierung je Site korrekt), E-49 (Netzwerkaktivierung blockieren, Schema je Site), **E-90** (Downgrade um eine Minor ohne Restore nur ohne Contract-Schritt).
**Quellen (nur gelesen):** LearnBoating `hastla007/learnboating` @ `9f37c77`, Pfade relativ zu `bootspruefung-learning/`: `src/Plugin.php:75-79` (`activate`, Netzwerk), `:351-520` (`maybeMigrate`, `isCustomerAdminRequest`, `migrationAction`, `scheduleMigration`, `cronMigration`, `runMigration`), `src/Infrastructure/Database/Migrator.php:84-170, 379-398`, `src/Infrastructure/Maintenance.php:15-80`. Pass Captain `wp-passmaster` @ `b2b88ea`: `src/Installer.php:674-791`, `src/Plugin.php:30-37, 105-117`, `docs/OPERATIONS.md:10-16`, `docs/TESTING.md:9-46`.

---

## 0. Kurzfassung

1. **Nur Aktivierung, Admin (`admin_init` ohne Ajax/`admin-post`), WP-Cron (`cc_migrate`) und WP-CLI führen die Migration aus.** `upgrader_process_complete` und Frontend/REST planen nur. Einzige Ausnahme: der Pass-Overdue-Fallback (Cron > 300 s überfällig → **ein** Frontend-Request).
2. **Zwei Sperrschichten je Site:** nicht wartendes `GET_LOCK(name, 0)` (stirbt mit dem Prozess) **und** Lease `cc_lease['schema-migration']` mit Fencing-Token. Der Mutex schließt Parallelläufe aus, die Lease hält im `NULL`-Fall und fängt „Zombies“ nach Verbindungsverlust ab (R0-21 §2.4). Wer die Sperre nicht bekommt, überspringt.
3. **Phasen:** P0 Bootstrap → P1 Preflight → P2 `dbDelta` + Drift-Check → P3 Strukturschritte `NNNN_*` → P4 kleine Datenschritte → **Version setzen** (Wartungsfenster endet) → P5 Backfill online in Batches → P6 Contract nur in späterem Release und nur nach Freigabe.
4. **Statusmodell je Schritt** in einer Tabelle `cc_migration_step` (`pending/running/failed/done`), Statuswechsel und Datenänderung in **einer** TX mit Fencing-Prüfung. Abbruch (Timeout, Fatal, `kill -9`) bedeutet höchstens „Schritt bzw. Batch wiederholen“.
5. **Wartungsfenster W1** (bis die Version gesetzt ist): schreibende Anfragen an `course-captain/v1` → `503 cc_maintenance` + `Retry-After: 30`; lesende laufen; CC-Hintergrundjobs pausieren. **W2** (Backfill): normaler Betrieb, neue Funktionen hinter einem Backfill-Gate.
6. **Multisite:** alles je Site (`$wpdb->prefix`); Netzwerkaktivierung blockiert (E-49); Massenupgrade per CLI mit **einem Prozess je Site** (kein `switch_to_blog`, R0-25 MS-R-07).
7. **Keine Rückmigration.** Downgrade nach E-90 über eine neue Kompatibilitätsmarke `cc_schema_compat_min`; sonst Restore DB + Dateien (R0-16 R-C).
8. **MIG-001…MIG-026** für R1-40; neue Entscheidungen D37-1…D37-5.

---

## 1. Befunde aus den Quellen

| # | Quelle | Befund | Folge für CC |
|---|---|---|---|
| QB-01 | LB `Plugin.php:361-394` | Migration nach Update ohne Reaktivierung; `admin-ajax.php`/`admin-post.php` gelten als **Kundenanfrage** (`isCustomerAdminRequest`), dort nur Cron planen | übernehmen (§2) |
| QB-02 | LB `Plugin.php:436-447` | `migrationAction`: CLI immer `run`; sonst Backoff 15 min nach Fehlschlag (`wait`), auch für Cron; Admin/Cron `run`, Rest `schedule` | übernehmen; Backoff ergänzt um Poison-Regel (§4.6) |
| QB-03 | LB `Plugin.php:467-478` | `wp_schedule_single_event` nur, wenn `wp_next_scheduled` leer → höchstens ein Ereignis | übernehmen (Doppel-Trigger) |
| QB-04 | LB `Migrator.php:84-100` | **Doppelsperre**: `NamedLock` (`GET_LOCK`, 0 s) + Options-`Lock` (TTL); GET_LOCK echt erhalten, Options-Sperre belegt → Besitzer tot → Übernahme (T202) | Muster übernehmen, Options-Sperre → Lease mit Fencing (R0-21 D21-1) |
| QB-05 | LB `Migrator.php:101-104` | `set_time_limit(0)`, `ignore_user_abort(true)` vor langen `ALTER` | übernehmen für Admin/Cron/CLI |
| QB-06 | LB `Migrator.php:107-170` | ein Block: `dbDelta` aller Tabellen → Existenz- und Drift-Check → FKs (nicht fatal) → Version setzen → **Datenreparatur T196 nach dem Versionswechsel, ohne Schrittprotokoll** | Datenschritte bekommen Nummer und Status; Version erst nach P4 (§4) |
| QB-07 | LB `Maintenance.php:15-80` | schreibende REST-Anfragen (nicht GET/HEAD/OPTIONS) 503, `RETRY_AFTER = 30`, stößt Migration an | übernehmen als `cc_maintenance` (R0-19) |
| QB-08 | LB `Plugin.php:75-79` | `activate(networkWide=true)` → `wp_die` | übernehmen (E-49, R0-25 MS-R-01) |
| QB-09 | Pass `Installer.php:699-741` | Frontend vergleicht nur Fingerprint, plant **einmalig** `wpcap_run_upgrade`; nach `OVERDUE_AFTER = 300` s führt ein Frontend-Request aus | übernehmen (R0-06 §6.4 Nr. 2) |
| QB-10 | Pass `Installer.php:751-767` | Doppelprüfung im Lock: Version direkt aus `wp_options`, am Options-Cache vorbei | übernehmen |
| QB-11 | Pass `Installer.php:771-791` | Lockname `md5(prefix | DB_NAME)` → je Site; `GET_LOCK` ≠ `'1'` (auch `NULL`) → überspringen | übernehmen; `NULL` → Lease-Rückfall statt Dauer-Überspringen (R0-21 §2.5) |
| QB-12 | Pass `OPERATIONS.md:16` | „neuer Code gegen altes Schema unkritisch, weil nur additiv“ | gilt für CC **nicht** automatisch → Lese-Kompatibilitätsregel K-2 (§3.4) |
| QB-13 | Pass `Plugin.php:105-117` | Pass **erlaubt** Netzwerkaktivierung (Schleife über Sites in einem Request) | **nicht** übernehmen (E-49); Suite-Drift, nur Hinweis |
| QB-14 | Pass `TESTING.md:9-46` | Multi-Prozess-Startbarriere, Invarianten, Exit-Code 1 | Testanlage für MIG-001/002/020 |
| QB-15 | Lücke beider Quellen | kein Schrittstatus, kein Backfill-Cursor, kein Fencing, kein Schutz bei Verbindungsverlust während eines Laufs (GET_LOCK weg, PHP läuft weiter) | §3, §4 |

---

## 2. Upgrade-Trigger

### 2.1 Übersicht

| Trigger | Prüft | Führt Migration aus? | Begründung |
|---|---|---|---|
| **T-1 Aktivierung** (`register_activation_hook`) | voll | **ja**, sofort, hinter Lock. Fehler → `wp_die` **vor** Cron/Rewrite/Caps (LB T146). Lock belegt → Aktivierung läuft weiter, `cc_migrate` wird geplant, W1 gilt | neuer Code ist geladen; Admin wartet bewusst |
| **T-2 Plugin-Update** (`upgrader_process_complete`, auch Auto-Update) | nur, ob CC im `plugins`-Array ist | **nein.** Löscht den Request-Cache, plant `cc_migrate` (einmalig, QB-03) | Der Callback läuft im Prozess mit dem **alten** Code; die neuen Dateien liegen nur auf der Platte. Migrieren mit altem Code wäre falsch |
| **T-3 `admin_init`** (nicht `wp_doing_ajax()`, nicht `admin-post.php`, nicht `is_network_admin()`) | `cc_schema_fingerprint`, dann `cc_db_version` voll | **ja**, bis Phase P4 bzw. Zeitbudget; Rest per `cc_migrate` | Admin-Seite, kein wartender Kunde (QB-01) |
| **T-4 Frontend, REST, Ajax, `admin-post`** | nur Fingerprint (autoload, billig) | **nein**, plant `cc_migrate` einmalig. **Ausnahme Overdue:** Ereignis > 300 s überfällig → genau **ein** Request (Lock) führt aus, mit `ignore_user_abort` und Zeitbudget | Pass QB-09; Hosts ohne funktionierenden WP-Cron |
| **T-5 WP-Cron `cc_migrate`** (einmalig, R0-19-Name) | voll | **ja**; beachtet Backoff (QB-02); plant sich selbst nach, solange Schritte/Batches offen sind | Standardweg ohne Admin-Besuch |
| **T-6 WP-CLI** `wp course-captain migrate [--dry-run] [--status] [--step=<id>] [--allow-contract] [--all-sites]` (Namen unter R0-19-Vorbehalt) | voll | **ja**, ignoriert Backoff, **kein** Zeitbudget (läuft bis fertig), Exit-Code ≠ 0 bei Fehler | Betreiberweg, Massenupgrade (§5.3) |

**Hookname:** R0-06 §6.4 nennt `cc_run_upgrade`, R0-19 (Cron-Tabelle) `cc_migrate`. Verbindlich ist **`cc_migrate`** (R0-19 ist der Vertrag); Nachtrag an R0-06 (Δ-37-1).

### 2.2 Entscheidungsfunktion (rein, testbar wie LB `migrationAction`)

`MigrationTrigger::action(context, state, now) → run | schedule | wait | none | blocked`

1. `state.network_forced` (CC in `active_sitewide_plugins`) → `blocked` (Haltemodus R0-25 MS-R-02).
2. Schema neuer als Code → `none` + Hinweis; Betrieb nach §6.2 (E-90) oder gesperrt.
3. Fingerprint und Version aktuell, kein Schritt offen → `none`.
4. `context = cli` → `run`.
5. Poison-Schritt (§4.6) → `none` + Hinweis (nur CLI/Admin-Knopf).
6. Backoff aktiv (`last_failed_at + 15 min > now`) → `wait` (plant `cc_migrate` zum Backoff-Ende).
7. `context ∈ {admin, cron, activation}` → `run`.
8. `context = frontend` und `cc_migrate` > 300 s überfällig → `run`, sonst `schedule`.

### 2.3 Zeitbudget je Lauf

- CLI: unbegrenzt. Admin/Cron/Overdue: `set_time_limit(0)` versuchen, Budget trotzdem **min(20 s, `max_execution_time`/2)** (R0-14 RB-02) für **teilbare** Arbeit (Backfill, Datenschritte in Chunks). Ein einzelnes DDL ist nicht teilbar und läuft zu Ende; der Host kann es trotzdem abbrechen → §4.5.
- Nach Ablauf des Budgets: Lease freigeben, `cc_migrate` sofort neu planen (`spawn_cron` erlaubt), Request beendet sich normal.

---

## 3. Sperren und Verhalten paralleler Requests

### 3.1 Sperrschichten je Site

| Schicht | Mechanismus | Zweck | Name |
|---|---|---|---|
| **L-A Mutex** | `SELECT GET_LOCK(name, 0)` (R0-21 M0, nicht wartend) | schließt parallele Läufe aus; endet automatisch mit Prozess/Verbindung | `cc_mig_` + `substr(md5(DB_NAME | $wpdb->prefix | 'schema-migration'), 0, 16)` (≤ 64 Zeichen, Pass QB-11) |
| **L-B Lease** | Zeile `cc_lease` (`name = 'schema-migration'`, `owner`, `fence`, `expires_at`, `heartbeat_at`) per `INSERT IGNORE` bzw. CAS-Übernahme (R0-21 §2.1) | Rückfall bei `GET_LOCK = NULL`; Fencing gegen Zombie-Läufer; sichtbarer Laufzustand für Admin/Health | – |

**Erwerb** (jeder Lauf, jede Invocation):

1. L-A erwerben. `0` → **überspringen** (anderer Lauf aktiv), Request arbeitet weiter (W1-Regeln §3.3).
2. L-A = `1` (echt): Lease erwerben. Ist sie von anderem `owner` belegt, ist dieser tot (er hätte L-A) → **sofortige Übernahme** per CAS, `fence = fence + 1` (LB T202-Muster). Laufender Schritt mit `status = running` wird als unterbrochen behandelt (§4.5).
3. L-A = `NULL` (nicht verfügbar, z. B. ProxySQL/Galera): nur Lease. Übernahme **nur** bei `expires_at < now()`. Zähler `named_lock_unavailable`, Health-Warnung (R0-21 §2.5).
4. **Doppelprüfung im Lock:** `cc_db_version` und Schrittstatus direkt aus der DB lesen (am Options-/Objektcache vorbei, Pass QB-10). Nichts zu tun → freigeben, Ende.
5. Freigabe in `finally`: Lease löschen (nur `WHERE owner = ? AND fence = ?`), dann `RELEASE_LOCK`.

**Lease-Zeiten** (R0-21 §2.4): vor einem DDL `expires_at = now + 1 800 s` (ein `ALTER` kann nicht heartbeaten); in Backfill/Datenschritten Heartbeat je Batch, `expires_at = now + 600 s` (LB `STALE_AFTER`), Heartbeat spätestens nach TTL/3.

**Fencing** (R0-21 §2.4, neu ggü. LB/Pass):

- Jede Status-, Cursor- oder Datenänderung eines Schritts läuft in einer TX, die mit `UPDATE cc_lease SET heartbeat_at=?, expires_at=? WHERE name='schema-migration' AND owner=? AND fence=?` beginnt; `affected_rows ≠ 1` → `ROLLBACK`, Lauf bricht ab (`cc_migration_fenced`, kein Fehlerstatus).
- **Vor jedem DDL** (nicht transaktional, impliziter Commit): Fencing-Update **und** `IS_USED_LOCK(name) = CONNECTION_ID()` (wenn L-A echt). Grund: Nach einem Reconnect (RB-05) ist L-A still verloren, PHP läuft weiter; ein zweiter Läufer kann L-A schon halten (CON-045).
- **Restrisiko DDL:** Zwischen Prüfung und DDL kann ein zweiter Läufer übernehmen (nur bei Lease-Ablauf). Absicherung: DDL-Schritte sind Ist-Zustand-idempotent (§4.2); MySQL serialisiert DDL über Metadata-Locks; „Duplicate column/key“ (1060/1061/1826) gilt als Erfolg, wenn der Ist-Zustand danach dem Soll entspricht.

**Bootstrap-Sonderfall:** `cc_lease` und `cc_migration_step` werden selbst migriert (P0). Bei der Erstinstallation bzw. vor P0 gibt es nur L-A. Ist dort zusätzlich `GET_LOCK = NULL`, greift einmalig eine Options-Sperre `cc_migration_bootstrap_lock` per `INSERT IGNORE` in `wp_options` (LB `Lock`, nicht `add_option`, R0-21 CB-04), TTL 300 s; sie wird nach P0 gelöscht. Uninstall-Manifest (R0-16 REC-020) ergänzen (Δ-37-4).

### 3.2 Zustände für Requests

| Zustand | Erkennung (billig) | Bedeutung |
|---|---|---|
| **W0** aktuell | Fingerprint gleich | Normalbetrieb |
| **W1** Struktur fällig/laufend/fehlgeschlagen | Fingerprint ≠ Code (autoload-Option), Bestätigung `cc_db_version` < Code | Code N+1 läuft gegen Schema N |
| **W2** Backfill offen | `cc_db_version` aktuell, `cc_backfill_open` (autoload, Liste offener Backfill-IDs) nicht leer | Normalbetrieb, Gates geschlossen |
| **WN** Schema neuer als Code | `cc_db_version` > Code | §6.2 |

### 3.3 Verhalten paralleler Requests in W1

| Anfrage | Verhalten |
|---|---|
| REST `course-captain/v1` schreibend (POST/PUT/PATCH/DELETE) | **`503 cc_maintenance`**, `Retry-After: 30`, `details.reason = migration`, `retry_after: 30` (R0-19). Keine Teilwrites. Pläne `cc_migrate` (QB-07) |
| REST lesend (GET/HEAD/OPTIONS) | **läuft** (Regel K-2 §3.4). Bricht ein Lesepfad trotzdem mit SQL-Fehler „unknown column/table“ ab → `503 cc_maintenance` statt 500 (Repository fängt 1054/1146 in W1) |
| CC-Formulare über `admin-ajax.php`/`admin-post.php` (Frontend-Aktionen) | wie REST schreibend: Fehlermeldung „Wartung, bitte in 30 s erneut“; keine Ausführung |
| Admin-Screens von CC | lesend erlaubt; Speichern von CC-Einstellungen/Kursen gesperrt mit Hinweis; Block-Editor-Speichern von CC-CPTs: CC-Metadaten-Hooks schreiben nicht, Post-Speichern wird mit `WP_Error` abgelehnt (nur CC-CPTs) |
| Prüfungen (`saveAnswer`, `submit`, `start`) | 503; Client puffert und wiederholt (R0-14 RB-03/RB-08, LB `Maintenance`). Frist: **D37-3** |
| Eingehende Webhooks/Inbox (R3-Adapter) | Annahme in die bestehende Inbox, wenn deren Struktur unverändert ist; sonst 503 → Absender wiederholt. Verarbeitung pausiert |
| CC-Hintergrundjobs (Outbox-Dispatch, Erinnerungen, Retention, Import/Export, Reconcile) | **pausieren** (prüfen `SchemaState::ready()` vor Lease-Erwerb); keine Zustellung mit halbem Schema |
| Fremde Plugins, WP-Kern | unberührt |
| W1 nach **Fehlschlag** (`failed`) | bleibt W1 (schreibend 503), bis die Migration erfolgreich ist. Kein „Schreiben auf altem Schema“: neuer Code kennt die Invarianten des alten Schemas nicht. Admin-Fehlerhinweis + Health `migration=false` (R0-16 §2.1) |

In **W2** gelten keine Sperren; Funktionen, die Backfill-Daten brauchen, fragen `BackfillGate::done('<id>')` und nutzen bis dahin den alten Pfad (R0-06 §6.3). Neue Schreibzugriffe füllen neue Spalten sofort mit (**Dual-Write**), sodass der Backfill nur Altbestand trifft.

### 3.4 Kompatibilitätsregeln für Code und Schema

- **K-1 (Downgrade, R0-06 §6.2):** Code N liest und schreibt Schema N+1, solange N+1 nur Expand enthält.
- **K-2 (neu, Upgrade-Fenster):** Code N+1 **liest** Schema N fehlerfrei für alle öffentlichen Lesepfade (Lernbereich, „Mein Lernen“, Lektion, geschützte Medien, Zugriffsprüfung). Neue Spalten werden in Lesepfaden erst nach `SchemaState::has('<step>')` verwendet. CI-Probe: Smoke-Suite des neuen Codes gegen das Schema der Vorversion (MIG-016). Ohne K-2 wäre „lesend 200“ in W1 nicht haltbar.
- **K-3:** Zugriffsentscheidungen (K3, R0-21) lesen nie Backfill-Daten vor `BackfillGate::done`.

### 3.5 Wartungshinweise

| Ort | Inhalt | Wer |
|---|---|---|
| Admin (alle Screens, `manage_options` bzw. `cc_manage_settings`) W1 laufend | „Course Captain aktualisiert die Datenbank (Schritt n von m, seit hh:mm). Lernende können lesen, Änderungen sind kurz gesperrt.“ Nicht wegklickbar | Admins |
| Admin W1 fehlgeschlagen | Fehler mit Schritt-ID, gekürzter DB-Meldung (escaped, ohne Pfade/Secrets), nächstem Versuch, CLI-Befehl `wp course-captain migrate`, Link Systemstatus; Knopf „Jetzt erneut versuchen“ (Nonce, ignoriert Backoff) | Admins |
| Admin W2 | Info „Daten werden nachgetragen (x %); Funktion Y ist danach verfügbar“, wegklickbar je Nutzer | Admins |
| Admin WN | Fehler wie LB `schema_newer` bzw. Info bei E-90-Downgrade (§6.2) | Admins |
| Admin Contract offen | „Aufräumschritt bereit; vorher Backup (R0-16 U-7)“ + Knopf (D37-2) | Admins |
| Lernbereich W1 | Banner „Kurze Wartung. Antworten werden zwischengespeichert und automatisch gesendet.“ (Client zeigt Status nach RB-03) | Lernende |
| Site Health / Systemstatus / `/monitor` | Test `cc_migration`: `ok`, `running` (Schritt, Dauer, Heartbeat), `failed`, `blocked` (Poison, Contract), `newer`; `/monitor` → `error` bei `failed` > 30 min | Betrieb |

---

## 4. Migrationsschritte

### 4.1 Phasen und Reihenfolge je Lauf

| Phase | Inhalt | Werkzeug | Ende von W1? |
|---|---|---|---|
| **P0 Bootstrap** | `cc_lease`, `cc_migration_step` anlegen (dbDelta, nur diese zwei) | `dbDelta` | – |
| **P1 Preflight** | InnoDB (`assertInnoDb`), DB-Rechte (`SHOW GRANTS` best effort), MariaDB/MySQL-Version (E-58), WN-Prüfung, offene Contract-Bedingungen, freier Platz als Warnung (R0-16 U-4) | lesend | – |
| **P2 Struktur deklarativ** | `dbDelta` je Tabelle aller aktiven Module (Core, Pro, Adapter) → Existenz- und **Drift-Check** gegen `information_schema` inkl. Index-Spaltenfolge (LB `missingSchemaParts`) | `dbDelta` (nur additiv) | – |
| **P3 Struktur imperativ** | nummerierte Schritte `NNNN_*`: FKs (nicht fatal, `cc_fk_issues`, R0-06 D-1), generierte Spalten + UNIQUE (R0-21 D21-4), nachträgliches UNIQUE nach Duplikatprüfung, Typänderungen | eigene DDL, Ist-Zustand-geprüft | – |
| **P4 kleine Datenschritte** | Reparaturen/Umzüge, die in ≤ 1 Batch passen oder vor dem Versionswechsel nötig sind (LB T196 gehört hierher, nicht hinter die Version) | TX je Chunk | **ja:** danach `cc_db_version`, `cc_schema_fingerprint`, `cc_schema_compat_min` setzen |
| **P5 Backfill** | große Datenumzüge, online, Batches mit Cursor | Backfill-Job | – (W2) |
| **P6 Contract** | Spalten/Tabellen/Indizes entfernen, Typ verengen | eigene DDL | nur mit Freigabe (D37-2); erneut kurzes W1 |

Module: Schritt-IDs sind `<modul>:<NNNN>_<slug>` (`core:0007_seat_active_user`, `pro:0002_…`). Reihenfolge: Core vor Pro vor Adapter; innerhalb eines Moduls aufsteigend; ein Schritt darf `requires: ['core:0007']` deklarieren. Schritte sind nach Release **unveränderlich**; eine Prüfsumme der Schrittdefinition wird in `cc_migration_step.checksum` gespeichert (CI-Test MIG-023).

### 4.2 Idempotenz-Regeln

- **I-M1:** Jeder Schritt hat `isApplied(): bool`, das den **Ist-Zustand** prüft (`information_schema.COLUMNS/STATISTICS/TABLE_CONSTRAINTS`, Zählabfrage), nicht nur den Status. `done` + `isApplied() = false` → Drift-Meldung, kein stilles Wiederholen.
- **I-M2:** `dbDelta` nur additiv: Tabellen, Spalten (`NULL`-fähig oder mit Default), Indizes, Typ verbreitern. Kein `DROP`, kein Umbenennen, keine FKs, keine generierten Spalten, keine `ENUM`-Verengung über `dbDelta` (R0-06 §6.2). CI-Lint auf die DDL-Definitionen.
- **I-M3:** DDL-Schritte: DDL → Ist-Zustand prüfen → Status `done` (eigene TX mit Fencing). Crash zwischen DDL und Status → nächster Lauf: `isApplied() = true` → `done` ohne zweites DDL.
- **I-M4:** Datenschritte: Datenänderung, Cursor und Status in **einer** TX mit Fencing-Update. Kein Datenschritt ohne natürliche Idempotenz (`WHERE neu IS NULL`, Hash-Vergleich, `INSERT … ON DUPLICATE KEY` auf Unique).
- **I-M5:** Nachträgliches UNIQUE: erst Dry-run-Zählung der Duplikate; Duplikate > 0 → Schritt `failed` mit Report (`cc_migration_report`), **kein** automatisches Löschen; Bereinigung ist ein eigener, fachlich definierter Schritt.
- **I-M6:** Kein Schritt hängt an Cron-, Mail- oder Outbox-Nebenwirkungen. Ereignisse aus Migrationen (falls je nötig) entstehen mit deterministischer `event_uuid` aus Schritt-ID + Zeilen-ID.

### 4.3 Statusmodell `cc_migration_step` (Δ-37-2, D37-1)

Spalten (Entwurf): `step_id varchar(96) PK`, `module varchar(32)`, `kind varchar(12)` (`structure/data/backfill/contract`), `status varchar(10)` (`pending/running/failed/done`), `attempts smallint`, `crash_count smallint`, `fence bigint` (Lease-Fence beim letzten Schreiben), `cursor varchar(191) NULL`, `rows_done bigint`, `rows_total bigint NULL`, `checksum char(40)`, `blocked_reason varchar(32) NULL` (`contract_needs_approval`, `backfill_open`, `poison`, `requires`), `last_error text NULL` (gekürzt, ohne Secrets), `started_at/heartbeat_at/finished_at datetime NULL`, `released_in varchar(16)` (Plugin-Version, die den Schritt einführte).

```
pending ──start──▶ running ──ok──▶ done
   ▲                 │  │
   │                 │  └─fehler──▶ failed ──retry (Backoff/CLI/Knopf)──▶ running
   └──reopen (Verify findet Zeilen, §6.2)        │
                     └─crash erkannt (Lease übernommen, status=running)──▶ running (crash_count+1)
                                                 crash_count ≥ 3 ──▶ failed + blocked_reason=poison
```

- `cc_migrations_applied`, `cc_backfill_state` und `cc_migration_failed` (R0-06 §6.1, E-52) entfallen als Optionen; Systemstatus/Health lesen die Tabelle. Beibehalten als **autoload-Optionen** nur die billigen Signale `cc_schema_fingerprint`, `cc_db_version`, `cc_schema_compat_min`, `cc_backfill_open`.
- `blocked_reason` ist eine Sperrangabe auf `pending`/`failed`, kein fünfter Status.

### 4.4 Backfill (P5)

- **Cursor:** aufsteigender PK `id`: `SELECT id … WHERE id > :cursor AND <prädikat> ORDER BY id LIMIT :n FOR UPDATE` (nur existierende Zeilen per PK, R0-21 §2.3), Update, `cursor = max(id)`, `rows_done += k`, Heartbeat/Fencing — **eine TX je Batch**.
- **Batchgröße:** CLI 500, Admin/Cron 200 (R0-06 §6.3); adaptiv halbiert, wenn ein Batch > 2 s dauert oder 1205/1213 auftritt; Lock-Wait im Hintergrund 15 s (R0-21 Δ-21-8); Deadlock 1213 → TX bis 3× wiederholen (RB-04).
- **Ende:** Cursor erreicht `MAX(id)` beim Start → **Verify-Abfrage** `COUNT(*) WHERE <prädikat>` über alles. `0` → `done`, Gate öffnet, ID aus `cc_backfill_open` entfernen. `> 0` (Zeilen unter dem Cursor durch alten Code/Downgrade) → zweiter Durchlauf ab `0`, höchstens 3×, dann `failed` mit Report.
- **Resume:** Abbruch mitten im Batch rollt die TX zurück; der nächste Lauf beginnt am gespeicherten Cursor (höchstens ein Batch doppelt gelesen, nie doppelt geschrieben).
- **Fortsetzung:** Nach dem Zeitbudget plant der Läufer `cc_migrate` sofort neu (QB-03). Ohne Cron: Admin-Besuch oder CLI. Backfills laufen **seriell** (ein Lease); Fortschritt in Admin/Health.
- **Dry-run** (`--dry-run`): zählt betroffene Zeilen und Duplikate je Schritt, führt **kein** DDL und keinen Write aus, schreibt keinen Status; Ausgabe `--format=json` (R0-19 V-09).

### 4.5 Fehlerbehandlung und Wiederaufnahme

| Ereignis | Erkennung | Verhalten | Folgezustand |
|---|---|---|---|
| Transienter DB-Fehler (1213 Deadlock, 1205 Lock-Wait) | Fehlercode | expliziter `ROLLBACK` (T-3), Batch/TX bis 3× wiederholen, dann Lauf beenden ohne `failed` | `running` → nächster Lauf |
| Verbindung weg (2006/2013) | `check_connection()` vor jedem Batch/DDL (RB-05) | Reconnect; L-A gilt als verloren → `IS_USED_LOCK`-Prüfung; fehlt L-A → Lauf beenden | nächster Lauf |
| Fencing verloren | `affected_rows = 0` | `ROLLBACK`, Lauf beenden, **kein** Status schreiben | unverändert (anderer Läufer) |
| Permanenter Fehler (1142 fehlendes Recht, Drift nach `dbDelta`, Duplikate bei UNIQUE, Assertion) | Exception im Schritt | Schritt `failed`, `last_error`, `attempts+1`; Backoff 15 min (nicht CLI); Admin-Hinweis; Health `migration=false` | `failed` |
| **PHP-Fatal** (Error, `E_ERROR`, Speicher) | `register_shutdown_function` + `error_get_last()` | Shutdown-Handler versucht mit eigener Fencing-TX `failed` + Meldung zu schreiben; gelingt das nicht, bleibt `running` | `failed` oder `running` (verwaist) |
| **PHP-Timeout** / FPM `request_terminate_timeout` / `kill -9` / OOM-Killer | kein Handler; L-A fällt mit der Verbindung | nächster Läufer bekommt L-A echt → Lease-Übernahme → `running`-Schritt als **unterbrochen**: `crash_count+1`, Schritt neu beginnen (Ist-Zustand I-M1, Cursor I-M4) | `running` |
| Wiederholter Absturz im selben Schritt | `crash_count ≥ 3` | **Poison:** `failed`, `blocked_reason = poison`; keine automatischen Versuche mehr (Cron/Admin/Overdue); nur CLI oder Admin-Knopf | `failed` |
| Schritt `n` scheitert | – | Schritte `< n` bleiben `done`, `> n` `pending`; **Version wird nicht gesetzt**; W1 bleibt | W1 |

Regel: Ein Lauf schreibt `failed` nur für den Schritt, an dem er gescheitert ist, und nur mit gültigem Fence. Ein Status `running` ohne lebenden Besitzer ist nie ein Fehler, sondern ein Wiederaufnahmepunkt.

### 4.6 Retry-Politik

- Backoff fest 15 min für Admin/Cron/Overdue (LB QB-02); CLI und Admin-Knopf sofort.
- Poison nach 3 Abstürzen bzw. 5 `failed`-Versuchen desselben Schritts ohne Erfolg → nur manuell.
- `failed` eines Strukturschritts blockiert alle folgenden Schritte des Moduls **und** abhängige Module; ein FK-Fehler (nicht fatal) blockiert nicht.

### 4.7 Contract (P6)

- Frühestens in Release N+2 nach dem Expand (R0-06 §6.2), nur wenn der zugehörige Backfill in einem früheren Release `done` ist (R0-16 U-3; Schritt prüft selbst → sonst `blocked_reason = backfill_open`).
- Läuft **nicht** automatisch über Cron/Frontend; Auslöser Admin-Knopf mit Backup-Bestätigung oder CLI `--allow-contract` (D37-2). Bis dahin arbeitet Code N+2 ohne die Entfernung (die Spalte bleibt ungenutzt).
- Erhöht `cc_schema_compat_min` auf die eigene Version (§6.2).

---

## 5. Multisite-Scope

### 5.1 Je Site

- Tabellen, Optionen (`cc_db_version`, Fingerprint, `cc_schema_compat_min`, `cc_backfill_open`), `cc_lease`, `cc_migration_step`, Cron-Ereignis `cc_migrate`, Lockname (`$wpdb->prefix` im Hash) und W1/W2-Zustand gelten **je Site**. Kein Netzwerk-Zustand, keine `site_option`.
- Ein Plugin-Update tauscht die Dateien für **alle** Sites gleichzeitig; jede Site mit aktivem CC ist ab dann in W1, bis ihr eigener Trigger läuft (lazy, R0-06 §6.6, R0-25 MS-R-08). Wenig besuchte Sites bleiben lange in W1 → Runbook-Pflicht: nach Netzwerk-Updates Massenupgrade per CLI (§5.3).
- `upgrader_process_complete` plant `cc_migrate` nur für die **aktuelle** Site (kein `switch_to_blog`-Lauf über das Netz, R0-25 MS-R-07).

### 5.2 Netzwerkaktivierung blockiert (E-49)

- `activate($network_wide = true)` bricht mit `wp_die` ab, bevor irgendetwas geschrieben wird – auch über `wp plugin activate --network` (R0-25 MS-R-01, LB QB-08). Pass erlaubt das (QB-13); bewusst nicht übernommen.
- Erzwungene Netzwerkaktivierung (Eintrag in `active_sitewide_plugins`): Haltemodus, **keine Migration**, kein Cron (R0-25 MS-R-02, D-25-08); `MigrationTrigger` liefert `blocked`.
- Netzwerk-Admin (`is_network_admin()`) ist nie Trigger.

### 5.3 Massenupgrade per CLI

- `wp course-captain migrate --all-sites [--parallel=<1..4>] [--dry-run] [--format=json]` ermittelt die Sites mit aktivem CC (`get_sites` + `active_plugins` der Site) und startet **je Site einen eigenen Prozess** (`WP_CLI::runcommand('course-captain migrate', ['launch' => true])` mit `--url=<site>`), kein `switch_to_blog` im selben Prozess (Caches, `$wpdb->prefix`, Speicher; R0-25 MS-R-07).
- Standard seriell; `--parallel` bis 4 ist sicher, weil Locks je Site gelten, erhöht aber die DB-Last (Shared Hosting: 1).
- Fehler auf einer Site stoppt die anderen nicht. Ausgabe: Tabelle/JSON je Site (`site_id`, `url`, `result: done|skipped_locked|failed|blocked|newer`, Schritt, Dauer). Exit-Code `1`, wenn mindestens eine Site `failed` oder `blocked` ist.
- Gleichwertig dokumentierte Shell-Schleife: `wp site list --field=url | xargs -I{} wp --url={} course-captain migrate`.

---

## 6. Rollback-Grenzen

### 6.1 Keine Rückmigration

- Es gibt **keine** Down-Schritte. Rollback = Restore von DB **und** Dateien als zusammengehöriger Stand (R0-06 §6.5, R0-16 §2.2 R-C, Pass `OPERATIONS.md:43-47`).
- Ein Restore auf einen DB-Stand mitten in einer Migration ist zulässig: verwaiste Lease (`expires_at` abgelaufen oder L-A frei) wird übernommen, `running`-Schritte werden fortgesetzt (MIG-021).
- Verboten (R0-16 §2.1): `cc_db_version` oder `cc_migration_step` von Hand ändern, Tabellen von Hand löschen.

### 6.2 Downgrade-Regel (E-90 als Annahme)

- Neue Option **`cc_schema_compat_min`** (Δ-37-3): niedrigste Code-Version, die dieses Schema lesen **und** schreiben darf. Expand-Release N+1 setzt sie auf N (eine Minor zurück, E-90); ein Contract-Schritt setzt sie auf seine eigene Version.
- Code mit Version `v` startet bei `cc_db_version > v`:
  - `v ≥ cc_schema_compat_min` → **Betrieb normal**, Admin-Info „Schema neuer als Code (Downgrade nach E-90)“, keine Migration, unbekannte Schritte in `cc_migration_step` werden ignoriert (R0-16 R-B, REC-025).
  - sonst → **WN gesperrt:** schreibend `503 cc_maintenance` (`details.reason = schema_newer`), lesend nach K-1 nicht garantiert → Lernbereich zeigt Wartung; Admin-Fehler wie LB `schema_newer` mit Verweis auf Restore (R0-16 R-C); CLI `migrate` Exit-Code ≠ 0 („Downgrade abgelehnt“, R0-19 §4.7).
- **Erneutes Upgrade nach Downgrade:** bekannte Schritte `done` werden übersprungen; jeder `done`-Backfill läuft einmal die **Verify-Abfrage** (§4.4), weil Code N Zeilen ohne neue Spalten geschrieben haben kann → bei Treffern `reopen` auf `pending`, Cursor 0, Gate schließt bis zum Abschluss (MIG-019).
- Pro/Adapter: eigene Schritte zählen über den Moduldefinitions-Hash in `cc_db_version` (R0-06 §6.1); ein Pro-Downgrade folgt derselben Regel je Modul.

---

## 7. Testfälle MIG-xxx (Ziel R1-40)

Werkzeuge: PU = PHPUnit-Integration mit echter MariaDB/MySQL; MP = Multi-Prozess-Läufer mit Startbarriere nach Pass `tests/load/run.php` (QB-14); CLI = WP-CLI im Staging-Compose (R0-16 §3.1); PU-MS = `WP_TESTS_MULTISITE=1`. Fehlerinjektion über einen nur in Tests aktiven Hook `course_captain_migration_fault` (`step`, `point = before_ddl|after_ddl|in_batch|after_batch|before_status`, `kind = throw|fatal|exit|sleep|sigstop`), abgesichert durch Konstante `COURSE_CAPTAIN_TESTING`. „Gleich“ = Zeilenzahl je `cc_*`-Tabelle + Prüfsumme je Tabelle (`CHECKSUM TABLE`) bzw. erwartete Backfill-Werte.

| ID | Szenario | Erwartung | Werkzeug | Ziel | Bezug |
|---|---|---|---|---|---|
| MIG-001 | **Paralleles Upgrade:** Admin, Cron, CLI, Aktivierung (zweite Site) und 50 REST-Requests hinter Startbarriere | genau ein Läufer je Site; übrige `skipped_locked`; jeder Schritt einmal `done`; `cc_migrate` höchstens einmal geplant; kein DDL doppelt (General-Log-Zählung) | MP | R1-40 | REC-007, QB-03/09 |
| MIG-002 | Zwei CLI-`migrate` gleichzeitig auf derselben Site | einer läuft, der andere Exit 0 mit `skipped_locked` | MP/CLI | R1-40 | QB-11 |
| MIG-003 | MIG-001 mit erzwungenem `GET_LOCK = NULL` | Lease allein schließt aus; Health-Warnung; Zähler `named_lock_unavailable` > 0 | MP | R1-40 | R0-21 §2.5, CON-006 |
| MIG-004 | DB-Reconnect während eines Datenschritts (L-A verloren), zweiter Läufer startet | erster scheitert an `IS_USED_LOCK`/Fencing vor dem nächsten Write; kein doppelter Effekt | PU | R1-40 | CON-045, RB-05 |
| MIG-005 | Zombie: Läufer per SIGSTOP angehalten (`NULL`-Modus), Lease läuft ab, zweiter übernimmt, erster läuft weiter | erster `cc_migration_fenced`, schreibt nichts; Schritt genau einmal | MP | R1-40 | CON-012 |
| MIG-006 | `kill -9` in `dbDelta` (P2) | nächster Lauf: L-A frei, Lease übernommen, `dbDelta` + Drift-Check erneut, Erfolg | MP | R1-40 | REC-006a |
| MIG-007 | `kill -9` nach DDL, vor Statuswrite (P3) | `isApplied() = true` → `done` ohne zweites DDL; `crash_count = 1` | MP | R1-40 | I-M3, REC-006b |
| MIG-008 | **Abbruch mitten im Backfill:** `kill -9` innerhalb der Batch-TX bei 40 % | TX zurückgerollt; Fortsetzung ab letztem Cursor; Endwerte gleich Referenzlauf ohne Abbruch; kein Batch doppelt geschrieben | MP | R1-40 | REC-006c, I-M4 |
| MIG-009 | Backfill mit `max_execution_time = 30` und Host-Kill bei 30 s (Cron-Kontext) | Budget 15 s hält; Lauf endet sauber, plant `cc_migrate` neu; ohne Budget (Test-Override) → Verhalten wie MIG-008 | PU/CLI | R1-40 | RB-02 |
| MIG-010 | **Fatal in Schritt n** (`fatal` an `before_status`) | Shutdown-Handler setzt n `failed`; `< n` `done`, `> n` `pending`; Version nicht gesetzt; W1 (schreibend 503, lesend 200); Admin-Hinweis; Backoff 15 min; CLI nach Entfernen der Injektion → n einmal `done`, Version gesetzt | PU | R1-40 | §4.5 |
| MIG-011 | Schritt n stürzt 3× ohne Shutdown-Handler ab (`exit` in Handler unterdrückt / `kill -9`) | `blocked_reason = poison`; Cron/Admin/Overdue versuchen nicht mehr; CLI/Knopf möglich | MP | R1-40 | §4.6 |
| MIG-012 | Permanenter Fehler: DB-Nutzer ohne `ALTER` | `failed` mit 1142; ein Hinweis; schreibend 503, lesend 200; CLI sofort erneut; nach Rechtevergabe Erfolg | PU | R1-40 | REC-008 |
| MIG-013 | **Doppel-Trigger:** `upgrader_process_complete` (alter Code) + `admin_init` im Folgerequest + Cron fällig + Reaktivierung | Update-Callback migriert nicht; genau ein Lauf; ein geplantes `cc_migrate`; keine doppelten Cron-Ereignisse anderer Hooks | PU | R1-40 | §2.1 T-2 |
| MIG-014 | Overdue-Fallback: `DISABLE_WP_CRON`, kein Admin, `cc_migrate` 301 s überfällig, 30 parallele Frontend-Requests | genau ein Frontend-Request führt aus; übrige liefern normal (Lesen) bzw. 503 (Schreiben) | MP | R1-40 | QB-09 |
| MIG-015 | **Migration während laufender Prüfungen:** 20 Sitzungen `in_progress` (davon 5 mit Frist im W1), Update einspielen | `saveAnswer`/`submit` 503 + `Retry-After`; Client puffert; nach W1 gehen Antworten und Abgabe durch; keine Doppelwertung (Idempotenz RB-03); Fristbehandlung nach D37-3; lesend 200 | PU + PW | R1-40 (R1-20) | REC-011 |
| MIG-016 | Lese-Kompatibilität K-2: Smoke-Suite Code N+1 gegen Schema N | alle Lesepfade 200 oder 503 `cc_maintenance`, nie 500 | PU (CI) | R1-40 | §3.4 |
| MIG-017 | Backfill parallel zu neuen Writes (Dual-Write, 10 Schreiber) | Verify = 0; Gate öffnet erst nach `done`; keine Zeile verloren | MP | R1-40 (R1-42) | §4.4 |
| MIG-018 | Contract-Schritt mit offenem Backfill bzw. ohne Freigabe | `blocked_reason = backfill_open` bzw. `contract_needs_approval`; Cron/Frontend lösen nie aus; mit `--allow-contract` und erfülltem Backfill einmal `done`, `cc_schema_compat_min` erhöht | PU | R1-40 | R0-16 U-3, D37-2 |
| MIG-019 | Downgrade E-90: (a) nur Expand, eine Minor; (b) mit Contract; (c) zwei Minor; danach Re-Upgrade | (a) Betrieb normal + Info; (b)(c) WN gesperrt, CLI Exit ≠ 0; Re-Upgrade: keine Schritte doppelt, Verify öffnet Backfill wieder und schließt ihn | PU/CLI | R1-40 (R1-23) | REC-025, §6.2 |
| MIG-020 | Multisite: Netzwerkaktivierung (UI, `--network`) blockiert; zwei Sites migrieren gleichzeitig; `--all-sites` mit einer fehlerhaften Site | keine Spuren bei Netzwerkaktivierung; Locks unabhängig je Präfix; Massenlauf: übrige Sites `done`, Zusammenfassung, Exit 1 | PU-MS/CLI | R1-40 | E-49, MS-001, §5 |
| MIG-021 | Restore eines DB-Snapshots, der mitten in P3/P5 erstellt wurde | Lease verwaist → übernommen; Fortsetzung; Ergebnis gleich Referenz | CLI | R1-40 (R1-23) | §6.1 |
| MIG-022 | `--dry-run` auf veraltetem Schema mit Duplikaten | Report mit Zählern/Duplikaten; `information_schema` und Tabellenprüfsummen unverändert; kein Status geschrieben | PU/CLI | R1-40 | §4.4 |
| MIG-023 | Schrittdefinition nach Release geändert | CI schlägt fehl (Prüfsumme gegen eingecheckte Liste); zur Laufzeit Health-Warnung, kein Rerun | CI | R1-40 | §4.1 |
| MIG-024 | Schema neuer als Code ohne E-90-Deckung | keine Aktion, kein DDL; Hinweis; schreibend 503 | PU | R1-40 | §6.2 |
| MIG-025 | CC-Hintergrundjobs in W1 (Outbox, Erinnerung, Import, Retention fällig) | alle pausieren, keine Zustellung, kein Lease-Erwerb; nach W1 normale Verarbeitung ohne Verlust | PU | R1-40 | §3.3 |
| MIG-026 | `admin-ajax.php`/`admin-post.php`-Requests bei fälliger Migration | führen nie aus, planen nur | PU | R1-40 | QB-01 |

---

## 8. Übergaben und technische Deltas

| Δ | Ziel | Änderung |
|---|---|---|
| Δ-37-1 | R0-06 §6.4 | Cron-Hook heißt `cc_migrate` (R0-19), nicht `cc_run_upgrade`; Datenreparaturen vor dem Versionswechsel (P4), nicht danach (QB-06) |
| Δ-37-2 | R0-06 §4.7, E-52; R0-16 §1.1 | neue Tabelle `cc_migration_step` (§4.3); Optionen `cc_migrations_applied`, `cc_backfill_state`, `cc_migration_failed` entfallen (D37-1) |
| Δ-37-3 | R0-06 §6.1/6.5; R0-16 R-B | neue autoload-Optionen `cc_schema_compat_min`, `cc_backfill_open` |
| Δ-37-4 | R0-16 §2.5 (Uninstall-Manifest) | `cc_migration_bootstrap_lock`, `cc_migrate`, neue Tabelle/Optionen aufnehmen |
| Δ-37-5 | R0-19 §4.7 | `migrate`-Optionen `--status`, `--step`, `--allow-contract`, `--all-sites`, `--parallel`; Ergebniscodes je Site |
| Δ-37-6 | R0-08 | Fristbehandlung bei W1 nach D37-3 einarbeiten (R0-16 REC-011 hatte das offen an R0-08 übergeben) |
| Δ-37-7 | R0-14 RB-02 / R1-02 | Kompatibilitätsregel K-2 als Architekturregel und CI-Job (MIG-016) |
| Δ-37-8 | R1-24 | Betreiber-Runbook „Nach Netzwerk-Update `--all-sites`“, Contract-Freigabe, Poison-Fall |

---

## 9. Entscheidungsbedarf (nur neue Punkte)

Bereits in `ENTSCHEIDUNGEN.md` und hier als Annahme verwendet: E-07, E-49, E-90 (außerdem R0-06 D-1/D-3, R0-21 D21-1/D21-2/D21-4, R0-25 D-25-08). Technisch festgelegt ohne Auftraggeber-Entscheidung (Architektur-Entwurf): Trigger-Matrix §2, Doppelsperre + Fencing §3.1, Phasen §4.1, Idempotenzregeln, Poison-Regel, Massenupgrade je Prozess, Hookname `cc_migrate`.

| ID | Frage | Optionen | Empfehlung | Entscheider | blockiert |
|---|---|---|---|---|---|
| **D37-1** | Speicherort des Migrationszustands | a) Tabelle `cc_migration_step` (Status je Schritt, Cursor, Fence in derselben TX) · b) Optionen wie R0-06/LB (`cc_migrations_applied`, `cc_backfill_state`) | **a)**: Status, Daten und Fencing atomar in einer TX; kein Options-Cache-Problem; Optionen bleiben nur als billige Signale | Architektur | R1-02, R1-40 |
| **D37-2** | Auslösung destruktiver Contract-Schritte | a) nur Admin-Knopf mit Backup-Bestätigung oder CLI `--allow-contract` · b) automatisch wie alle Schritte, sobald der Backfill fertig ist | **a)**: ein Contract macht E-90-Downgrade unmöglich und braucht ein frisches Backup (R0-16 U-7); ein unbeaufsichtigter Cron-Lauf darf das nicht entscheiden | Auftraggeber + Architektur | R1-23, R1-40 |
| **D37-3** | Prüfungsfrist, wenn Schreibzugriffe wegen Migration (W1) bzw. Read-only-Fenster mit 503 abgewiesen wurden | a) Server protokolliert Wartungsfenster (`started_at/ended_at`); für Sitzungen, deren Frist in ein Fenster fällt, gilt Frist = Fensterende + 120 s (höchstens + 30 min), nur für vom Server vor Fristende abgewiesene Schreibversuche · b) Timer pausiert für die Fensterdauer · c) keine Verlängerung | **a)**: nachweisbar serverseitig, nicht von der Client-Uhr abhängig; b) verschiebt alle Sitzungen und verfälscht `strict`-Nachweise stärker; c) bestraft Lernende für Betreiberentscheidungen | Auftraggeber (mit R0-08) | R0-08, R1-08, R1-20, R1-40 |
| **D37-4** | Automatische Plugin-Updates (WP-Auto-Update) während laufender `strict`-Prüfungen | a) Filter `auto_update_plugin` verschiebt Auto-Updates von CC, solange `strict`-Sitzungen `in_progress` sind (höchstens 24 h), manuelle Updates unberührt · b) nicht eingreifen | **a)**: verhindert W1 mitten in Nachweisprüfungen; Sicherheitsupdates verzögern sich höchstens 24 h | Auftraggeber | R1-08, R1-40 |
| **D37-5** | Verhalten bei Poison-Schritt bzw. dauerhaft `failed` (W1 bleibt, schreibend 503) | a) W1 bleibt bis zur Behebung, Monitor `error` nach 30 min · b) nach x Stunden „Notbetrieb“: Schreiben auf altem Schema erlauben | **a)**: neuer Code gegen altes Schema schreibend ist nicht abgesichert (K-1 gilt nur rückwärts); Notbetrieb wäre ein Restore (R0-16 R-C) | Architektur | R1-40, R1-38 |

---

## 10. Nachweis

- LB (`9f37c77`, nur gelesen): `src/Plugin.php:75-79` (Netzwerk-`wp_die`), `:351-394` (`maybeMigrate`, `schemaIsNewer`-Hinweis), `:398-447` (`isCustomerAdminRequest`, `migrationAction`), `:452-491` (`retryAt`, `scheduleMigration` mit `wp_next_scheduled`, `cronMigration`), `:493-520` (`runMigration`, Backoff 15 min, `OPTION_MIGRATION_FAILED`); `src/Infrastructure/Database/Migrator.php:84-100` (GET_LOCK + Options-Lock, T202-Übernahme), `:101-104` (`set_time_limit(0)`, `ignore_user_abort`), `:107-170` (dbDelta, Drift, FKs, Version, T196 nach Version); `src/Infrastructure/Maintenance.php:15-80` (503, `RETRY_AFTER = 30`, nur nicht-GET/HEAD/OPTIONS).
- Pass (`b2b88ea`, nur gelesen): `src/Installer.php:674-677` (`UPGRADE_HOOK`, `OVERDUE_AFTER = 300`), `:699-701` (Fingerprint), `:705-711` (Kontext: REST/Ajax = frontend), `:726-759` (`maybe_upgrade`), `:762-769` (`stored_db_version`), `:772-792` (Lockname, `run_locked`, `GET_LOCK(…,0)`); `src/Plugin.php:30-37, 105-117` (Netzwerkaktivierung erlaubt); `docs/OPERATIONS.md:10-16`; `docs/TESTING.md:9-46`.
- Kursdokumente: R0-06 §6, E-52, Nachträge; R0-16 §2.1/2.2, REC-006…011/025, R16-D6 (= E-90); R0-21 §2.1/2.4/2.5/2.6, D21-1/2/4; R0-36 §2.2/2.5; R0-25 MS-R-01/02/07/08, MS-001/004 (Entwurf, ungetrackt); R0-19 §2.1, §4.7, Cron-Tabelle; R0-14 RB-01…08.
- Keine Änderung an LearnBoating oder den Suite-Klonen, keine Implementierung, kein Commit.
