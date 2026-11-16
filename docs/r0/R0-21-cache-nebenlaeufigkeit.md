# R0-21 · Cache-, Nebenläufigkeits- und Konsistenzregeln

**Status:** Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet. Es gibt weder Implementierung noch Tests. Alle Zeitwerte sind Zielwerte; R1-42 misst sie und gibt sie frei.
**Stand:** 16.11.2026
**Bezug:** TODO R0-21 (Voraussetzung R0-06, R0-09), Ziel-Tasks **R1-25** (Cache- und Zustandskonsistenz) und **R1-42** (Parallelitäts-/Integritäts-Lastprofil); `docs/r0/R0-06-datenmodell-adr.md` §4.0 (CAS `row_version`), §4.8 (Transaktionsgrenzen), §6.4 (Migrations-Lock), Nachträge; `R0-09` §2.4 (Grant-TX), §3.4 (`access.changed`), §6 (L1…L4, R-01…R-13); `R0-08` §6.5 (Versuch-Locks), Zeile `start`/`saveAnswer`/`submit`; `R0-14` RB-01…RB-08, NFR-008/010/011/012, D-14-03; `R0-23` §6 (Nebenläufigkeit Revisionen); `R0-36` P4, §2.4/2.5 (Pass-Lehren); `R0-07` P-11 (Policy-Memo); `R0-19` §2.1 (Caching, Idempotenz, `ccap_db_busy`); `R0-10` §4 (geschützte Medien, R10-D4); `R0-02-inventar/B` §5 (Lock-Liste), Befund 6, INV-B-161/175; `R0-02-inventar/C` INV-C-052/053/057/128, §3.4.
**Beschlüsse (verbindlich):** E-01 (Clean Room: LB- und Pass-Stellen sind Verhaltensbelege, kein Code wird übernommen), E-10 (Website-Härtung im Kern), E-46/E-152 (Rate-Limit-Speicher: Login, Reset, Registrierung, Claim, Einlösen, Einladungen, Einladungsversand, Datenschutzanträge, Import/Export fail-closed; Lernen fail-open; bekannte Geräte dürfen weiter einloggen), E-79 (Lernsperre `ccap_learner_lock`), E-113 (Abgabe nach Zugangsverlust wird gewertet), E-07/E-49 (Multisite je Site), E-198 (DB-Query-Caches nicht unterstützt), E-207 (Präfix `ccap_`).

## Beschlussstand

| Beschluss | Änderung in diesem Dokument |
|---|---|
| E-01 | LB/Pass-Locks (`NamedLock`, Options-`Lock`) werden nicht portiert, sondern als Verhalten im neuen `LockService` nachgebaut |
| E-10, E-46, E-152, E-263 | fail-closed-Liste in §2.2/§4 erweitert (Registrierung, Claim, Einladungen, Datenschutzanträge, Import/Export); bekannte Geräte dürfen bei Ausfall des Zählers weiter einloggen |
| E-27, E-25a, E-26 | Zugangsentscheidung (K3) je Nutzer × Kurs × Modul; Invalidierung bei Änderung von Kursbeziehungen (Bundle) und Basiszugang (§3.7) |
| E-38, E-182 | lokaler Hook `course_captain_event_v1` in T-5 nur mit Pro; die Kern-Invalidierung läuft immer synchron in `afterCommit` |
| E-168 | Abgabe und Bewertung von Aufgaben (CAS, nie stale) in §2.2 und §4 |
| E-198 | DB-Query-Caches nicht unterstützt, Systemstatus-Warnung (§3.1) |
| E-207 | Tabellen `ccap_lease`, `ccap_rate_limit` usw.; Transients/Optionen `course_captain_*` |
| E-215, E-258 | Outbox-Batches und Retention-Leases nach den beschlossenen Fristen |

Die technischen Festlegungen D21-1…D21-6 (§6) sind übernommen; die Nachträge sind eingearbeitet.
**Quellen:** LearnBoating `hastla007/learnboating` @ `9f37c77` (Pfade relativ zu `bootspruefung-learning/`), Pass Captain `wp-passmaster` @ `b2b88ea`. Beide nur gelesen.
**Abgrenzung:** Rate-Limit-Grenzwerte → R0-17/R0-31; Plattform-/Cache-Plugin-Matrix → R0-20; Schema-Upgrade-Ablauf → R0-37 (hier nur Lock-Rang); Ereignis-Envelope → R0-19.

---

## 0. Kurzfassung

1. **Eine Lock-Infrastruktur** `LockService` mit genau zwei Betriebsarten: **Mutex** (`GET_LOCK`, verbindungsgebunden, für kurze kritische Abschnitte innerhalb eines Requests) und **Lease** (eigene Tabelle `ccap_lease`, über Requests hinweg, mit Besitzer, Heartbeat und Fencing). LB hat drei Varianten (`NamedLock`, Options-`Lock`, GET_LOCK-Kopie im Voucher-Connector); diese entfallen.
2. **Entscheidungsregel je Operation** (§2.2): Einmal-Anlage → **UNIQUE**; Zustandswechsel einer bestehenden Zeile → **CAS**; Lesen-Ändern-Schreiben einer bestehenden Zeile mit Logik → **Row-Lock** (`FOR UPDATE` nur per PK/Unique auf **existierende** Zeilen) + CAS; Invariante über mehrere Zeilen → **Mutex** auf das Aggregat + DB-Guard (Unique/Nachzählung). Jede Invariante hat einen DB-Guard, der auch ohne `GET_LOCK` hält.
3. **Globale Lock-Reihenfolge** M0 (Lease) → M1 Lernender → M2 Quelle → M3 Gruppe → M4 Kette → M5 Kurs-Publish → M6 Versuchsstart → DB-TX (Zeilen: Eltern vor Kind, feste Tabellenfolge, Schlüssel aufsteigend). Kein Lock in offener TX. Verstöße wirft der `LockService` in Debug/Test.
4. **Fehlerregel (Pass P4):** Nach 1213/1205 oder irgendeinem Statement-Fehler die **ganze Operation** abbrechen (`ROLLBACK`), nie im Autocommit weiterlaufen; 1213 → komplette Wiederholung (max. 3, Jitter), 1205 → `503 ccap_db_busy`. Kein `FOR UPDATE` auf fehlende Zeilen, kein Aufräumen in interaktiven TX.
5. **Cache-Klassen K0…K4** (§3.1): Berechtigungs- und Entscheidungsdaten (K3) liegen **nie** in einem persistenten Cache, nur im Request-Memo. Persistenter Objektcache nur für unveränderliche (Revisions-ID im Schlüssel) oder generationsversionierte Daten. Alle personalisierten Antworten `no-store, private` + Page-Cache-/CDN-Signale; was zu spät erkannt wird, rendert **fail-closed**.
6. **Stale reads** (§4): nie bei Zugriffsprüfung, Prüfungsaktionen, Downloads, Claims, Zertifikatsausstellung, Rechten und Sperren; erlaubt (≤ 5 min, read-your-writes) für Fortschrittsübersichten und Kennzahlen.
7. **CON-001…CON-052** (§5) als Negativtests für R1-25/R1-42; technische Festlegungen D21-1…D21-6 übernommen (§6).

---

## 1. Befunde aus den Quellen

| Nr. | Beleg | Befund | Folge für CC |
|---|---|---|---|
| CB-01 | LB `src/Infrastructure/NamedLock.php:42-68` | `GET_LOCK`; Rückgabe `NULL` (nicht verfügbar) gilt als **gehalten**, nur Log; wiedereintrittsfähig (`$held`-Zähler) | übernehmen, aber §2.5: Invariante muss ohne Lock per DB-Guard halten; NULL wird gezählt und in Site Health gemeldet |
| CB-02 | `NamedLock.php:50-51` | Standard-Wartezeit 5 s per Filter; `ExamService` nutzt 10 s (`START_LOCK_WAIT`, R0-14 LB-NF-08) | Wartezeiten nach Klasse (§2.4), D-14-03 |
| CB-03 | `NamedLock.php:102-116` | `toPrimary()` (HyperDB/LudicrousDB), Name `sha256(DB_NAME\|prefix\|name)` gekürzt | übernehmen (Multisite: Präfix je Site trennt) |
| CB-04 | LB `src/Infrastructure/Lock.php:1-40` | Options-Zeile mit `INSERT IGNORE`, TTL-Übernahme nur bei unverändertem Wert, Freigabe nur durch Besitzer; ausdrücklich **nicht** `add_option` (notoptions-Cache) | Muster für **Lease**, aber eigene Tabelle (D21-1) und zusätzlich Fencing |
| CB-05 | Voucher `Connector.php:611-655` (Inventur B INV-B-161) | dritte, eigene GET_LOCK-Kopie | entfällt; ein `LockService` (B Befund 6, Pflicht) |
| CB-06 | Inventur B §5 Lock-Tabelle | Options-Locks für Voucher-Grant (30 s), Dispute (60 s), Reminder (15 min), Reconcile (30 min); NamedLock für Seats, Geld-zurück | kurze fachliche Locks → Mutex; Läufe → Lease |
| CB-07 | LB `src/Infrastructure/RequestMemo.php:9-17, 37-56, 87-111` | Memo nur bei GET/HEAD in eigenen Namespaces oder in `scope()`; nach Request verworfen; **kein** Verwerfen bei `set_current_user`; Schlüssel teils global (`access_rules`, `AccessService.php:459`), teils mit Nutzer (`entitled:<user>`, `:395`; `access_start:<user>\|…`, `:317`) | übernehmen + Pflicht-Nutzerscope und Flush-Ereignisse (§3.2) |
| CB-08 | LB `src/Gate/ContentGate.php:28-32, 51` | eigenes statisches Memo je Beitrag × Nutzer, Flush bei `set_current_user` und `bootspruefung_entitlements_changed` | in das eine Request-Memo überführen |
| CB-09 | LB `src/Content/Repositories/ContentCache.php:14-53` (INV-C-128) | Objektcache-Gruppe mit Generation im Schlüssel (auch für Drop-ins ohne `flush_group`), Transient-Rückfall ohne persistenten Cache, TTL 1 Tag | übernehmen für K0/K1 (§3.3) |
| CB-10 | LB: kein `wp_cache_*` in `AccessService`/`EntitlementService` | Zugang wird nur im Request-Memo gehalten, nie persistent | als Regel festschreiben (K3) |
| CB-11 | LB `src/Frontend/NoCache.php:11-26, 98-120`; Aufrufer `Router.php:148`, `ContentGate.php:138, 207, 243` | `DONOTCACHEPAGE` (nicht `DONOTCACHEOBJECT/DB`), `nocache_headers()`, LiteSpeed-Aktion + `X-LiteSpeed-Cache-Control`; bei REST/Ajax nur Header. Wird ein geschützter Block **spät** erkannt (`headers_sent()`), fehlen die Header – nur die Konstante wirkt | übernehmen; Lücke schließen: fail-closed Rendering (§3.5 H-6) |
| CB-12 | LB `src/Rest/Controllers/LearnerController.php:469-476` | `Cache-Control: no-store, private` nur im Erfolgszweig von `wrap()`; Fehlerantworten (403/404) ohne eigenen Header | CC setzt Header für **alle** Statuscodes des Namespace (§3.5 H-3) |
| CB-13 | LB `src/Gate/ProtectedFiles.php:363, 379, 390`; `Mobile/WebSession.php:239-242` | geschützte Dateien `private, no-store`; Web-Session zusätzlich `Referrer-Policy: no-referrer`, `X-Robots-Tag` | übernehmen; R10-D4 für Bilder |
| CB-14 | LB `Rest/NonceRefresh.php:30` | Nonce-Erneuerung mit `nocache_headers()` | Muster für öffentliche Seiten mit Interaktion |
| CB-15 | Pass `CHANGELOG.md:167` (R0-36 P4a) | nach serverseitigem Rollback (Deadlock/Timeout) liefen Folgeanweisungen im **Autocommit** ohne Sperre → Überverkauf | Regel T-3 (§2.6) |
| CB-16 | Pass `CHANGELOG.md:168`, `src/Admission/Engine.php:128-134`, `docs/TESTING.md:86-93` (R0-36 P4b) | `FOR UPDATE` auf noch **nicht existierende** Zeile + späteres Upsert → Gap-Lock-Deadlock; Fehler ungeprüft → falsches „eingelassen“ | Regel R-1/R-2 (§2.3) |
| CB-17 | Pass `src/Orders.php:164-167` | Aufräumen abgelaufener Reservierungen **innerhalb** der Kapazitätssperre war Deadlock-Quelle; jetzt ignoriert der Kapazitätscheck Abgelaufenes, Cleanup macht ein Job | Regel R-5 |
| CB-18 | Pass `tests/load/run.php`, `TESTING.md:9-46` (R0-36 §2.4) | N Prozesse hinter Startbarriere, Invarianten, Deadlock-Zählung, Profile `ci/default/heavy` | Testanlage für §5 (Art P) |

---

## 2. Einheitliche Lock-Strategie

### 2.1 Bausteine (abschließend)

| Baustein | Technik | Lebensdauer | Einsatz | Nicht einsetzen für |
|---|---|---|---|---|
| **UNIQUE** | Unique-Index + `INSERT`; bei Duplicate-Key vorhandene Zeile lesen und vergleichen (gleich → idempotente Antwort, sonst `ccap_idempotency_conflict`) | dauerhaft | Einmal-Anlage: Grant je Quelle, Inbox-Event, Idempotency-Key, Zertifikat je Evidenz, Aktivitätsabschluss, Start-Token, Kursrevision | Zählgrenzen („höchstens n“) |
| **CAS** | `UPDATE … SET …, row_version = row_version + 1 WHERE id = ? AND row_version = ? [AND status = ?]`; `affected_rows = 1` | eine Anweisung | Statuswechsel bestehender Zeilen (Grant, Seat-Claim, Session-Status, Kursfortschritt, Lease-Heartbeat) | Anlage; Mehrzeilen-Invarianten |
| **Row-Lock** | `SELECT … FOR UPDATE` **nur** per PK/Unique-Gleichheit auf existierende Zeilen, innerhalb einer kurzen TX | TX | Lesen-Ändern-Schreiben mit Logik auf einer Elternzeile (Session + Session-Frage bei `saveAnswer`, Lernfortschritt je Frage) | Bereichs-/Nicht-Unique-Prädikate (Gap-Locks), fehlende Zeilen |
| **Mutex** | `LockService::mutex(name, wait)` → `GET_LOCK` auf der Primärverbindung | bis Freigabe bzw. Verbindungsende | Invariante über mehrere Zeilen/Tabellen eines Aggregats (Plätze einer Gruppe, Laufzeitkette, Versuchsstart je Nutzer, Kurs-Publish, Quell-Diff) | Arbeit über mehrere Requests |
| **Lease** | `LockService::lease(name, ttl, owner)` → Zeile in `ccap_lease` (`name` PK, `owner`, `fence bigint`, `expires_at`, `heartbeat_at`) per `INSERT IGNORE` / CAS-Übernahme | über Requests; TTL + Heartbeat | mehrstufige Läufe: Migration, Import, Export, Retention, Reconcile, Erinnerungs-/Outbox-Batches | interaktive Pfade |

Der Begriff „Option-Lock“ und jede eigene `GET_LOCK`-Kopie entfallen. Fremde Add-ons (Suite) nutzen den `LockService` über den Erweiterungsvertrag (R0-19), nicht eigene Locks auf CC-Tabellen.

### 2.2 Entscheidungsregel je Operation

Prüfreihenfolge (erste zutreffende Zeile bestimmt den **primären** Schutz; der **DB-Guard** ist immer Pflicht):

1. Entsteht genau eine Zeile höchstens einmal? → **UNIQUE**.
2. Wechselt eine bestehende Zeile ihren Zustand ohne fremde Zeilen zu lesen? → **CAS**.
3. Muss eine bestehende Zeile gelesen, fachlich ausgewertet und zurückgeschrieben werden? → **Row-Lock** + CAS.
4. Hängt die Invariante an mehreren Zeilen (Zählung, Überlappung, „genau eine laufende …“)? → **Mutex** auf das Aggregat **plus** DB-Guard in der TX (Unique über generierte Spalte, Nachzählung nach Insert mit Rückbau, CAS auf Elternzeile).
5. Läuft die Arbeit über mehrere Requests? → **Lease** + idempotente Schritte (Cursor, TX je Schritt).

| Operation | Primär | DB-Guard (hält auch ohne `GET_LOCK`) | Quelle |
|---|---|---|---|
| Grant aus Quelle anlegen | M2 `grant-source:<type>:<id>` (Diff-Planer) | UNIQUE `grant_source` | R0-09 §2.4 |
| Grant-Übergang | CAS | `status`-Vorbedingung, UNIQUE `grant_source_event` | R0-09 §2.4 |
| Inbox-Event annehmen | UNIQUE `connector_event` | – | R0-06 §4.6 |
| Inbox/Outbox-Claim | CAS (`locked_until/lock_owner`) | `WHERE status IN (…)` | R0-06 §4.8 |
| Gruppenlink-Join (letzter Platz) | M3 `group-seats:<group>` | Nachzählung nach Insert in derselben TX, bei Überbuchung Rückbau; **plus** CAS `ccap_group.row_version` beim Belegen | R0-09 R-01, INV-B-043 |
| Persönliche Einladung einlösen | CAS (bedingtes `UPDATE … WHERE status='invited'`) | UNIQUE `claim_token_hash` | R0-09 R-03 |
| Doppel-Join desselben Kontos | M3 | Unique `active_member (group_id, active_user)` über generierte Spalte (Δ-21-2, = R0-09 Δ-5) | R0-09 R-02 |
| Laufzeit verlängern/Restack | M4 `grant-chain:<user>:<course>` | CAS je Grant | R0-09 R-11 |
| Prüfung/Quiz starten | M6 `exam_start:<user>` | UNIQUE `start_token`; Unique `running_form (user_id, form_key, running)` über generierte Spalte (Δ-21-3) | R0-08 `start` |
| `saveAnswer`/`flag`/`reveal` | Row-Lock Session → Session-Frage | CAS `row_version`; Sequenz „älter überschreibt nie neuer“ | R0-08 |
| `submit`/`cancel`/Lazy-Ablauf | Row-Lock Session | CAS `WHERE status='in_progress'` | R0-08, RB-01 |
| Lernantwort (Smarttrainer) | Row-Lock `ccap_question_progress` (Zeile vorab angelegt, R-2) | UNIQUE `user_idem` | R0-06 §4.8 |
| Aktivitätsabschluss | UNIQUE `user_activity` | CAS `ccap_course_progress` | R0-23 §6 |
| Aufgabe speichern/abgeben (E-168) | CAS auf `ccap_assignment_submission` (`WHERE status = 'draft'`) | UNIQUE `user_activity_no` für die nächste `submission_no` | R0-06 §4.4a |
| Aufgabe bewerten/zurückgeben (E-168) | Row-Lock Submission + CAS | UNIQUE `submission_eval` (`evaluation_no`) | R0-06 §4.4a |
| Kursbeziehung (Bundle) ändern (E-25a) | M5 `course_publish:<bundle>` | UNIQUE `course_relation`; Zyklusprüfung in der TX | R0-06 §4.1 |
| Zertifikat ausstellen | UNIQUE `evidence_rev` | – | R0-09 R-06 |
| Idempotency-Key (REST) | UNIQUE `user_scope_key` | „in Arbeit“ > 300 s verwaist | R0-19 §2.1 |
| Kurs veröffentlichen/Staging aktivieren | M5 `course_publish:<course>` (nicht wartend) | UNIQUE `course_rev` | R0-23 REV-038 |
| Kontolöschung/Übertragung | M1 `learner:<user>` (bei zwei Konten aufsteigend) | Kontoprüfung in jeder Fach-TX | R0-09 R-12 |
| Migration | M0 Mutex `schema-migration` (Wartezeit 0) **und** Lease `schema-migration` mit Fencing; vor jedem DDL Prüfung, ob der Lauf die Sperre noch hält | Versionsprüfung direkt aus DB im Lock | R0-37 §3.1 |
| Import/Export/Retention/Reconcile/Batches | M0 Lease je Lauf (Import: eine Lease `import` je Site, Batch-ID im `owner`, R0-38) | TX je Schritt, Cursor, Prüfsummen | R0-14 RB-02 |
| Ablauf-Cron Grant | CAS `WHERE status='active' AND valid_until < :now AND row_version=?` | – | R0-09 R-10 |
| Rate-Limit-Zähler | atomares `INSERT … ON DUPLICATE KEY UPDATE hits = hits + 1` in `ccap_rate_limit` | PK `bucket_hash` | R0-06 §4.7, E-46, E-152 |

### 2.3 Regeln für Row-Locks und TX-Inhalt

- **R-1** Kein `SELECT … FOR UPDATE` (und kein `LOCK IN SHARE MODE`) auf Zeilen, die fehlen können (Pass P4b). Stattdessen: Elternzeile sperren, Unique-Insert mit Duplicate-Behandlung oder R-2.
- **R-2** Muss eine Zeile vor dem Sperren existieren (z. B. `ccap_question_progress`, `ccap_course_progress`), wird sie **vor** der Fach-TX per `INSERT IGNORE` im Autocommit angelegt (inhaltsneutral, idempotent); erst dann `START TRANSACTION` + `FOR UPDATE` per PK. LB macht beides in einer TX (R0-08 Z. 429, LS:497-498); mit ≥ 3 gleichzeitigen Erstanlagen ist dort ein S→X-Upgrade-Deadlock möglich.
- **R-3** Sperrprädikate nur per PK oder vollständigem Unique-Schlüssel mit Gleichheit; keine Bereichs-, `IN`-Listen über Nicht-Unique-Indizes oder `ORDER BY … LIMIT … FOR UPDATE` in interaktiven Pfaden. Hintergrund-Claims (Inbox/Outbox) nutzen CAS-`UPDATE … LIMIT n` mit `lock_owner` und lesen danach die eigenen Zeilen.
- **R-4** Zeilenreihenfolge in der TX: Elternzeile vor Kindzeile; Tabellenfolge `ccap_group` → `ccap_seat` → `ccap_grant` → `ccap_grant_event` → `ccap_outbox_event`; Prüfung `ccap_exam_session` → `ccap_exam_session_question` (Position aufsteigend); Fortschritt `ccap_activity_progress` → `ccap_course_progress`; Revisionen ID aufsteigend (R0-23 REV-032). Mehrere Zeilen derselben Tabelle immer nach PK aufsteigend.
- **R-5** Keine Haushaltsarbeit (Aufräumen abgelaufener Reservierungen/Sitzungen, Retention) in interaktiven TX (Pass CB-17). Prüfungen ignorieren Abgelaufenes per Prädikat; Cron räumt auf.
- **R-6** Keine externen Aufrufe (HTTP, Mail, Webhook) in TX oder unter Mutex. Folgen laufen über die Outbox (in derselben TX geschrieben) oder nach Commit.
- **R-7** Kein Lesen vor dem Mutex in derselben TX: Mutex → `START TRANSACTION` → erstes `SELECT`. So beginnt der REPEATABLE-READ-Snapshot erst nach dem Lock und sieht den Stand des Vorgängers.
- **R-8** Zeitvergleiche nutzen **eine** Uhr: PHP `Clock` (UTC) als Parameter (`valid_until < :now`), nie `NOW()` der DB gemischt mit PHP-Zeit (R0-06 Befund 7: TS kommen aus PHP).

### 2.4 Globale Lock-Reihenfolge und Wartezeiten

| Rang | Name | Art | Wartezeit interaktiv | Wartezeit Hintergrund | Bei Belegt |
|---|---|---|---|---|---|
| M0 | `schema-migration`; Leases `schema-migration`, `import` (je Site, Batch-ID im `owner`), `export:<job>`, `retention`, `reconcile:<connector>` (Pro), `batch:<job>` | Mutex 0 s / Lease | – | 0 s (nicht wartend) | überspringen bzw. `ccap_job_busy` |
| M1 | `learner:<user>` | Mutex | 2 s | 10 s | `409 ccap_account_busy` |
| M2 | `grant-source:<type>:<id>` | Mutex | 2 s | 10 s | Inbox-Retry / `409 ccap_conflict` |
| M3 | `group-seats:<group>` | Mutex | 2 s | 10 s | `409 ccap_group_busy` (`retry_after: 2`) |
| M4 | `grant-chain:<user>:<course>` (Kette je Ziel; bei Modul-Grants gilt der Kurs als Schlüssel, damit Ganzkurs- und Modulketten nicht parallel rechnen) | Mutex | 2 s | 10 s | `409 ccap_conflict` |
| M5 | `course_publish:<course>` | Mutex | 0 s | 0 s | `409 ccap_publish_busy` |
| M6 | `exam_start:<user>` | Mutex | 2 s (LB 10 s, D-14-03) | – | `409 ccap_exam_busy` |
| DB | TX mit `innodb_lock_wait_timeout` = 5 s (Sitzung, interaktiv) bzw. 15 s (Hintergrund) | Row-Locks nach R-4 | – | – | 1205 → §2.6 |

- **Reihenfolge (MUSS):** M0 → M1 → M2 → M3 → M4 → M5 → M6 → DB-TX. Gleicher Rang mit mehreren Schlüsseln: Schlüssel aufsteigend (z. B. zwei Konten bei M1, mehrere Quellen bei M2). Wiedereintritt (gleicher Name) ist erlaubt. Kein Mutex wird in einer offenen TX erworben (R0-09 §6.1).
- **Durchsetzung:** Der `LockService` führt einen Stapel gehaltener Ränge je Request. Erwerb eines niedrigeren Rangs als des höchsten gehaltenen oder Erwerb bei offener TX (`$wpdb`-TX-Zähler des `TxRunner`) → `LogicException` bei `WP_DEBUG`/Tests, sonst Log + Zähler `lock_order_violation` (CON-007).
- **Gesamtbudget:** Summe aller Wartezeiten eines interaktiven Requests ≤ 5 s (RB-08). Ein Request erwirbt höchstens drei Mutex-Ränge.
- **Lease-Werte:** TTL je Job (Migration 1 800 s, Import 900 s/Stale 600 s, Retention 3 600 s, Reconcile 1 800 s, Batch-Mails 900 s – LB-Werte, Inventur B/C); Heartbeat spätestens nach TTL/3; Übernahme nur bei abgelaufenem `expires_at` per CAS, dabei `fence = fence + 1`.
- **Fencing (neu gegenüber LB):** Jeder Schritt eines Lease-Laufs bestätigt in **seiner** TX per `UPDATE ccap_lease SET heartbeat_at=?, expires_at=? WHERE name=? AND owner=? AND fence=?` (`affected_rows = 1`), sonst Rollback und Abbruch. Ein „Zombie“-Worker nach Übernahme schreibt so nichts mehr (CON-012).
- **Verbindungsbindung:** Mutex stirbt mit der DB-Verbindung. Nach Reconnect (RB-05) gelten alle Mutex als verloren; der `LockService` erkennt das über `IS_USED_LOCK(name) = CONNECTION_ID()` vor dem Commit langer Schritte und bricht ab.

### 2.5 `GET_LOCK` nicht verfügbar (`NULL`)

LB läuft ohne Sperre weiter (CB-01). CC übernimmt das **nur**, weil jede Zeile in §2.2 einen DB-Guard hat:

- `NULL` → Operation läuft weiter, Zähler `named_lock_unavailable` + Log (einmal je Request); Site Health/Systemstatus warnt („Datenbank-Sperren nicht verfügbar; Parallelschutz nur über Datenbank-Eindeutigkeit“).
- Für M0 (Migration) Rückfall auf Lease (R0-06 §6.4 Nr. 3).
- Abnahme: Alle P-Tests aus §5 laufen zusätzlich mit erzwungenem `NULL` (Filter `course_captain_lock_force_unavailable`, nur in Tests) und müssen dieselben Invarianten halten; erlaubt ist nur mehr `409`/Rückbau (CON-006). Alternative „hart abbrechen“ → D21-2.

### 2.6 Transaktionen, Deadlock und Lock-Wait

Alle schreibenden Fachoperationen laufen über einen `TxRunner::run(callable $op, Policy $p)`:

- **T-1** `START TRANSACTION`; jede Anweisung wird geprüft (`false`/`$wpdb->last_error`). Jeder Fehler → Ausnahme → `ROLLBACK` → kein weiterer Befehl dieser Operation.
- **T-2** **1213 Deadlock:** InnoDB hat die TX schon zurückgerollt. `TxRunner` wiederholt **die ganze Operation** inklusive aller Lesezugriffe und Guard-Entscheidungen (nicht ab der fehlgeschlagenen Anweisung), höchstens 3-mal, Jitter 50–200 ms; danach `503 ccap_db_busy` (`Retry-After: 2`). Voraussetzung: Die Operation hat bis zum Commit keine Seiteneffekte außerhalb der DB (R-6).
- **T-3** **1205 Lock-Wait-Timeout:** Mit Standard `innodb_rollback_on_timeout = OFF` rollt InnoDB nur die **Anweisung** zurück, die TX bleibt offen. `TxRunner` sendet explizit `ROLLBACK`, wiederholt **nicht** serverseitig (Worker-Budget) und antwortet `503 ccap_db_busy`; Client-Retry nach RB-03. Nie im Autocommit weiterlaufen (Pass P4a).
- **T-4** Nach `ROLLBACK` prüft der `TxRunner`, dass keine TX mehr offen ist (`@@in_transaction` auf MySQL 8 bzw. eigener Zähler auf MariaDB); geschachtelte `run()`-Aufrufe sind flach (innerer Aufruf läuft in der äußeren TX, Retry nur außen).
- **T-5** Nach `COMMIT`: `afterCommit`-Callbacks (Cache-Invalidierung §3.7; mit Pro zusätzlich der lokale Hook `course_captain_event_v1`, R0-19 §5.4 Nr. 6, E-38). Nach Rollback werden sie verworfen.
- **T-6** Deadlock-/Lock-Wait-Zähler je Operation (NFR-011) werden über die `TxRunner`-Telemetrie erfasst, nicht über `performance_schema` (R-S ohne Rechte, R0-14 §4.2).
- **T-7** CAS-Fehlschlag (0 Zeilen) ist **kein** DB-Fehler: neu lesen, Guard erneut, höchstens 3 Versuche, dann `409 ccap_conflict`/`ccap_exam_conflict` (R0-09 §2.4, R0-08).

---

## 3. Cache-Schichten

### 3.1 Datenklassen

| Klasse | Inhalt (Beispiele) | Grundregel |
|---|---|---|
| **K0** öffentlich, nutzerunabhängig | Katalogdaten veröffentlichter Kurse, Kursbild, öffentliche Landingpage ohne Personalisierung, versionierte JS/CSS | darf in alle Schichten inkl. Page-Cache/CDN |
| **K1** Inhalt je **unveränderlicher** Revision | Kursstruktur je `course_revision_id`, Frage je `question_revision_id` (inkl. Lösungen – nur serverseitig), Fragenpool-IDs je Revision, Medien-Metadaten | Objektcache mit Revisions-ID im Schlüssel; braucht keine Invalidierung, nur TTL gegen Speicherwachstum; nie in HTTP-Antworten an Unberechtigte |
| **K1g** Inhalt mit Generation | „aktuelle veröffentlichte Revision je Kurs“, Kurskennzahlen (Verhalten LB `courseStats`, INV-B-095), Video-Hosts, Zugangsregeln/Drip-Regeln je Kurs, Kursbeziehungen `contains` (E-25a) | Objektcache/Transient mit Kurs- bzw. Inhaltsgeneration |
| **K2** nutzerbezogen, nicht autoritativ | Fortschrittsübersicht „Mein Lernen“, Prozentwerte, Dashboard-Zähler, Gruppenübersicht für Besitzer, Trainerberichte | Objektcache nur mit Nutzer-Scope + Nutzer- und Kursgeneration + TTL ≤ 300 s (Berichte ≤ 900 s) |
| **K3** Entscheidung/Berechtigung | Grants, effektiver Zugang je Kurs × Modul (E-27) inkl. Bundle- und Abhängigkeitsauflösung (E-25a, E-26), Scopes, Drip-Freigabe-Ergebnis, Caps/Meta-Caps, Staff-Zuordnung, Lernsperre, Sessionstatus/Frist, Seat-Status, Idempotency-Ergebnisse, Rate-Limit-Zähler | **nur** Request-Memo; nie persistent, nie Transient (RB-06, CB-10) |
| **K4** Geheimnisse | Claim-/Join-/Verifikationstoken (Klartext), Nonces, Lösungen einer laufenden Prüfung im Client | nie in einen Cache; Tokens nur als Hash in der DB (E-112) |

DB-Query-Caches (z. B. Datenbankcache von Cache-Plugins) werden nicht unterstützt; der Systemstatus warnt, weil sie Zugriffsentscheidungen veralten lassen können (Beschluss E-198). WordPress-Core-Caches (User-Meta/`wp_capabilities`, `alloptions`, Post-Cache) bleiben in Verantwortung des Core; CC ändert Rollen und Optionen nur über Core-APIs, damit `clean_user_cache`/`wp_cache_delete` greifen. Direkte SQL-Änderungen an Core-Tabellen sind verboten.

### 3.2 Request-Memo (L-M)

- **Was:** K1, K1g, K2, K3 für die Dauer eines Requests (Policy-Memo R0-07 P-11, `entitled:*`, Zugangsregeln, ContentGate-Ergebnis).
- **Aktiv:** GET/HEAD in `course-captain/v1` und in expliziten `scope()`-Blöcken (Frontend-Render eines Lernbereichs, Block-Render). Aus in schreibenden Requests, Cron, CLI und Tests (LB CB-07).
- **Schlüssel (MUSS):** `<blog_id>:<bereich>:<user_id|shared>:<…>`. Der Memo-Builder hat zwei Methoden `forUser(int $userId, …)` und `shared(…)`; `shared` ist nur für Werte erlaubt, deren Berechnung keinen Nutzerkontext liest (Review-Regel + CON-020).
- **Verwerfen bei:** `set_current_user` (fehlt in LB, CB-07), `switch_to_blog`/`restore_current_blog`, jedem eigenen Commit (`afterCommit`, auch in GET mit Lazy-Write: Prüfungsablauf RB-01, Grant-Ablaufanzeige), den Core-Hooks aus R0-07 Z. 159 (`set_user_role`, `add_user_role`, `remove_user_role`, `profile_update`, `remove_user_from_blog`) sowie Änderungen an `ccap_course_staff`, `ccap_grant`, `ccap_seat`, `ccap_learner_lock`.
- **Invalidierung:** entfällt sonst (Request-Ende).

### 3.3 Objektcache (L-O nicht persistent, L-P persistent)

- **Nicht persistent** (WP-Standard, nur Request): verhält sich wie L-M; dieselben Schlüssel- und Flush-Regeln. CC verlässt sich nie darauf, dass ein Wert über den Request hinaus lebt.
- **Persistent** (Redis/Memcached-Drop-in, `wp_using_ext_object_cache()`): erlaubt **nur** K0, K1, K1g, K2. Gruppe `course_captain` (nicht global, damit Multisite trennt; zusätzlich `blog_id` im Schlüssel, weil manche Drop-ins Gruppen falsch globalisieren).
- **Schlüssel:** `cc:v<schema>:<blog>:<klasse>:<scope>:<id…>:g<gen…>`
  - K1: `…:k1:shared:qrev:<question_revision_id>` – keine Generation.
  - K1g: `…:k1g:shared:course:<course_id>:g<course_gen>` bzw. `…:g<content_gen>`.
  - K2: `…:k2:u<user_id>:course:<course_id>:g<user_gen>.<course_gen>`.
- **Generationen:** `content_gen` (global, LB `OPTION_GENERATION`), `course_gen:<course>`, `user_gen:<user>`. Gespeichert als zufälliges Token im Objektcache **und** für `content_gen`/`course_gen` als Option (LB-Muster); fehlt das Token im Cache, wird ein neues erzeugt (→ Fehltreffer, nie falscher Treffer). Erhöhen = neues Zufallstoken setzen (kein `wp_cache_incr`, das nicht jeder Drop-in atomar kann).
- **Leseprotokoll (MUSS, gegen Set-after-Invalidate):** 1. Generationen lesen, 2. Cache lesen, 3. bei Fehltreffer DB lesen, 4. unter dem **in Schritt 1 gelesenen** Schlüssel schreiben. Ein Writer, der zwischen 1 und 4 committet und die Generation erhöht, macht den alten Schlüssel unerreichbar (CON-024).
- **TTL:** K1 86 400 s; K1g 86 400 s; K2 300 s (Berichte 900 s). Zufallsjitter ±10 % gegen Stampede; teure K1g-Werte (Kurskennzahlen) mit „einer rechnet“-Schutz über `wp_cache_add`-Marker (kein Mutex).
- **Ausfall/Flush:** nur langsamer, nie falsch (RB-06, NFR-008).

### 3.4 Transients (L-T)

- Ohne persistenten Objektcache liegen Transients in `wp_options`, mit Objektcache im Cache (können jederzeit fehlen). Deshalb nur:
  - Rückfall für K1g ohne persistenten Cache (Verhalten LB `ContentCache`, CB-09), Schlüssel mit Generation; Aufräumen per Cron (Präfix `course_captain_cache_`, E-207/D-20-03) und Uninstall-Manifest (INV-C-026).
  - Kurzlebige Hinweise je Nutzer (`course_captain_notice_<user>_<zweck>`, 10 min) ohne fachliche Bedeutung.
  - Pro/R3: externe API-Tokens (Adapter), Health-Ergebnisse (z. B. Speicher-Selbsttest 12 h/1 h, R0-10).
- **Verboten:** K2 mit Fortschrittszahlen (würde `wp_options` mit Nutzerzeilen füllen), K3, Rate-Limit-Zähler (→ `ccap_rate_limit`, E-46), Deduplizierung mit Korrektheitsbedeutung (LB Dispute-Dedupe per Transient 90 Tage → im Woo-Adapter über Inbox-Unique, E-33, E-116).

### 3.5 HTTP: Page-Cache, Reverse-Proxy/CDN

**Routenklassen**

| Klasse | Routen | Header |
|---|---|---|
| **H-P** öffentlich | Katalog-/Landingpages ohne geschützten Inhalt, öffentliche Kursvorschau, versionierte Assets | Standard des Betreibers; Assets `max-age=31536000, immutable` mit Versionsparameter |
| **H-X** personalisiert/geschützt | Lernbereich, Lektionen/Seiten mit Gate (ganz, Block, Shortcode), Prüfung, „Mein Lernen“, Gruppen-/Einladungsseiten (`/…/gruppe/<token>`), Abmelde-/Widerrufsseiten, alle Antworten in `course-captain/v1` (alle Statuscodes), `wp/v2`-Antworten für CC-Inhaltstypen, eigene `admin-ajax`-Aktionen, geschützte Medien, Zertifikat-PDF | `Cache-Control: no-store, private` (+ `nocache_headers()`), `DONOTCACHEPAGE` (nur Seitenaufruf, nicht `DONOTCACHEOBJECT/DB`, CB-11), LiteSpeed-Aktion + `X-LiteSpeed-Cache-Control: no-cache`, `X-Accel-Expires: 0` (nginx `fastcgi_cache`), `Surrogate-Control: no-store`, `CDN-Cache-Control: no-store` |
| **H-T** Token-Seiten | Einladung, Gruppenlink, Verifikation (R-42) | wie H-X; R-42 ohne `private` (R0-19); zusätzlich `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow` (LB WebSession, CB-13) |

**Regeln**

- **H-1** Entscheidung vor der Ausgabe: `template_redirect` Priorität 0 klassifiziert Router-Seiten und Beiträge mit Gate-Meta/Block/Shortcode (LB `NoCache::detect`), zusätzlich `rest_post_dispatch` für den eigenen Namespace und CC-Inhaltstypen.
- **H-2** Personalisierte Antworten hängen **nicht** von Cookies als Cache-Schlüssel ab: Viele Page-Caches umgehen eingeloggte Nutzer über das `wordpress_logged_in_*`-Cookie, CDNs mit „Cache Everything“ aber nicht. Die H-X-Header gelten deshalb auch für Gäste (Teaser), damit ein Gast-Teaser nicht später Käufern ausgeliefert wird und umgekehrt. Kein `Vary: Cookie` als Schutzmittel.
- **H-3** Header für **alle** Statuscodes des Namespace (200, 304, 401, 403, 404, 409, 429, 503), nicht nur im Erfolgszweig (CB-12). Fehler werden sonst von CDNs negativ gecacht.
- **H-4** Keine `ETag`/`Last-Modified`-Revalidierung personalisierter Antworten; das `ETag` aus R0-19 (If-Match) dient nur der Nebenläufigkeit und steht neben `no-store`.
- **H-5** Personalisierte Fragmente auf H-P-Seiten (Button „Weiterlernen“ statt „Kaufen“, Fortschrittsbalken im Katalog) werden clientseitig über eine H-X-REST-Route nachgeladen oder machen die Seite zu H-X. Nonces kommen auf H-P-Seiten nur über eine Nonce-Route (LB `NonceRefresh`, CB-14).
- **H-6** **Fail-closed bei später Erkennung:** Rendert ein geschützter Block/Shortcode, nachdem Header gesendet sind und die Anfrage nicht als H-X markiert war (Template-Part, Widget, synchronisierter Block, Page-Builder-Daten), setzt er `DONOTCACHEPAGE` (wirkt für Output-Buffer-Caches noch) und gibt **nur den Teaser** aus, auch für Berechtigte; Log `late_gate_detected` + Admin-Hinweis mit Fundstelle (Lücke CB-11).
- **H-7** Purge statt Personalisierung: Nach Veröffentlichung/Rückzug/Preisänderung eines Kurses (Ereignis nach Commit) ruft CC `clean_post_cache` und die Aktion `course_captain_cache_purge` (`{scope: course, ids, urls}`) auf; Integrationen für verbreitete Page-Caches sind optional (R0-20-Matrix), keine harte Abhängigkeit.
- **H-8** Geschützte Medien: `private, no-store`; nur Bilder optional `private, max-age=300` + `ETag` (R10-D4). Kein Medium einer geschützten Klasse unter öffentlicher Upload-URL.

### 3.6 Browser (L-B)

- **B-1** `no-store` verhindert Speicherung im HTTP-Cache. Für bfcache/Zurück-Taste: Seiten des Lernbereichs prüfen bei `pageshow` mit `event.persisted` die Sitzung (Heartbeat/Nonce-Route) und laden neu, wenn Nutzer abweicht oder abgemeldet ist (gemeinsame Rechner).
- **B-2** Lokale Zwischenspeicher (Prüfungs-Autosave RB-08, Smarttrainer-Warteschlange) unter Schlüssel `cc:<blog>:<user_id>:<session_uid>`; beim Laden wird der Eigentümer gegen die Server-Nutzer-ID geprüft, fremde Einträge gelöscht; Löschen nach `submit`, nach Bestätigung durch den Server und beim CC-Logout-Link. `Clear-Site-Data` → D21-5.
- **B-3** Kein Service Worker in R1. Spätere PWA/App (Mobil) darf H-X-Routen nicht cachen.
- **B-4** Clientzustand ist nie autoritativ: Frist, Zugang und Ergebnis kommen vom Server (R0-08).

### 3.7 Invalidierungsmatrix

Ausgeführt **synchron** in `afterCommit` der auslösenden TX (P-11 „sofort“), nicht über die Outbox. Request-Memo wird in allen Zeilen verworfen. HTTP ist bei H-X nie betroffen (nichts gespeichert).

| Ereignis | `user_gen` | `course_gen` | `content_gen` | Weiteres |
|---|---|---|---|---|
| Grant angelegt/aktiviert/verlängert (auch Modul-, Bundle- und abhängige Grants) | betroffener Nutzer | – | – | K3 ohnehin ungecacht; Bundle/Abhängigkeit werden beim Lesen aufgelöst |
| Refund/Teilrefund/Chargeback/Storno/Widerruf/Suspend | betroffener Nutzer | – | – | laufende Prüfung: nächste Schreibaktion prüft neu (R0-09 R-05) |
| Grant-Ablauf (zeitbasiert) | – (kein Ereignis) | – | – | Zugang wird beim Lesen aus `valid_until` berechnet (RB-01); K2-Anzeige höchstens TTL alt |
| Seat entfernt, Gruppe gesperrt/abgelaufen, Platzreduktion | alle betroffenen Mitglieder + Besitzer | – | – | Besitzer-Dashboard |
| Kursrevision veröffentlicht, Staging aktiviert, Kurs zurückgezogen/archiviert | – | Kurs | – | H-7 Purge der öffentlichen Kursseite |
| Frage korrigiert/archiviert (neue Fragenrevision) | – | alle Kurse mit der Frage | – | K1 der alten Revision bleibt gültig (unveränderlich) |
| Rechtestatus/Auslieferungssperre Medium (E-18) | – | betroffene Kurse | – | Medienroute prüft live (K3) |
| Zugangs-/Drip-Regel geändert | – | Kurs | – | – |
| Kursbeziehung `contains` geändert (Bundle veröffentlicht, E-25a) | – | Bundle-Kurs und alle enthaltenen Kurse | – | K2 der Lernenden mit Bundle-Grant höchstens TTL alt; Zugang (K3) sofort |
| Aufgabe abgegeben/bewertet/zurückgegeben (E-168) | Lernender | – | – | Bewertungsliste (K2) der Trainer mit TTL |
| Rolle/Caps geändert, Staff-Zuordnung, Nutzer von Site entfernt | betroffener Nutzer | – | – | Core `clean_user_cache` |
| Lernsperre gesetzt/aufgehoben (E-79) | betroffener Nutzer | – | – | nächster Request `403 ccap_account_locked` |
| Fortschritt/Abschluss/Prüfungsergebnis | Nutzer | – | – | Gruppen-/Trainer-K2 mit TTL (eventual) |
| Zertifikat ausgestellt/korrigiert/widerrufen | Nutzer | – | – | R-42 ist `no-store` |
| Konto gelöscht/Daten gelöscht (Eraser) | Nutzer (neues Token, alte Schlüssel verwaist) | – | – | Transient-Hinweise des Nutzers löschen |
| Import aktiviert, Plugin-Update/Migration | – | betroffene Kurse | ja | `v<schema>` im Schlüssel |
| Manuell (Support) | – | – | ja | `wp course-captain cache flush [--user= --course=]` |

---

## 4. Stale-read-Verhalten

| Bereich | Erlaubte Veraltung | Lesequelle | Begründung |
|---|---|---|---|
| Zugriffsprüfung Lektion/Medium/Download | **nie** | DB (Primärserver, D21-3) + Request-Memo | Refund/Sperre wirkt sofort (P-11, CON-030) |
| Prüfung/Quiz: `start`, `saveAnswer`, `flag`, `reveal`, `submit`, Lazy-Ablauf | **nie** | DB in TX mit Row-Lock (Session) | Frist, Status, Zugang (R0-09 R-05, E-113) |
| Lernantwort Smarttrainer | **nie** für Token/Revision/Zugang | DB in TX | RV-06 (R0-23) |
| Seat-Claim, Einladung, Gruppenstatus beim Join | **nie** | DB unter M3 | R-01…R-13 |
| Zertifikatsausstellung, Verifikation R-42 | **nie** | DB | Nachweis/Widerruf |
| Rechte, Staff, Lernsperre, Admin-Aktionen | **nie** | Core + DB, Memo nur im Request | R0-07 P-11 |
| Rate-Limits | **nie** (fail-Verhalten nach E-46/E-152) | `ccap_rate_limit` | E-46, E-152 |
| Aufgaben-Abgabe und -Bewertung (E-168) | **nie** | DB in TX (CAS) | Status und Bewertung sind Nachweisdaten |
| Seitenaufbau aus vielen Prüfungen („Mein Lernen“-Badges, Navigation) | innerhalb **eines** Requests konsistent | Request-Memo | eine Momentaufnahme je Seite |
| Fortschrittsübersicht, Prozentwerte, „zuletzt gelernt“ | ≤ 300 s für **fremde** Änderungen; eigene Änderungen sofort sichtbar (read-your-writes über `user_gen`) | K2 | Konzept: nur Anzeige; Zugang hängt nicht daran |
| Gruppen-Besitzer-Dashboard (Belegung, Fortschritt der Mitglieder) | ≤ 300 s; Belegungszahl beim **Handeln** (Einladen) live unter M3 | K2 | Anzeige vs. Entscheidung getrennt |
| Trainer-/Admin-Berichte, Kurskennzahlen (Fragenzahl, Videos) | ≤ 900 s bzw. bis Generation | K2/K1g | – |
| Öffentlicher Katalog (H-P) | bis Page-Cache-Purge (H-7) bzw. Betreiber-TTL | Page-Cache | Inhalt nicht personalisiert |
| Geschütztes Bild im eigenen Browser | ≤ 300 s nach Refund (R10-D4, Option) | Browser-Cache `private` | bereits ausgeliefert, nur eigenes Gerät |
| Lese-Replikat (HyperDB) | nur für K0/K1/K2-Abfragen | Replikat | D21-3 |

Zusatzregeln:
- **S-1** Eine Entscheidung (K3) wird nie aus einem K2-Wert abgeleitet (z. B. „Kurs abgeschlossen“ aus der gecachten Übersicht für Zertifikat oder Freischaltung).
- **S-2** Lazy-Writes in GET (Prüfungsablauf, RB-01) laufen über `TxRunner`; danach Memo-Flush, damit dieselbe Antwort den neuen Stand zeigt.
- **S-3** Out-of-order-Quellen (Webhooks) werden über `source_seq` entschieden (R0-09 R-04), nicht über Ankunftszeit.

---

## 5. Negativtests CON-xxx

Art: U = Unit, I = Integration (WP-Testsuite, echte DB), P = Paralleltest (PHP-Mehrprozess-Runner mit Startbarriere, CB-18; N = 50, 20 Wiederholungen, MariaDB **und** MySQL 8.4, NFR-010), H = HTTP-Probe gegen echten Page-Cache/Proxy (Docker: nginx `fastcgi_cache`, Varnish „cache everything“, ein Cache-Plugin aus R0-20), E = E2E (Playwright).

### 5.1 Locks, Races, Unique

| ID | Szenario | Erwartung | Art | Ziel | Bezug |
|---|---|---|---|---|---|
| CON-001 | 50 parallele Gruppenlink-Joins auf den **letzten** Platz | genau 1 aktiver Seat + 1 Grant-Satz; 49 × `ccap_group_full`/`ccap_group_busy`; Belegung ≤ Plätze | P | R1-42 | R-01 |
| CON-002 | Dasselbe Konto joint in 2 Tabs gleichzeitig | 1 Seat; zweite Antwort = bestehende Mitgliedschaft | P | R1-42 | R-02, Δ-21-2 |
| CON-003 | Persönliche Einladung von 2 Konten gleichzeitig eingelöst | 1 Gewinner, anderer `ccap_claim_used` | P | R1-42 | R-03 |
| CON-004 | Platzreduktion parallel zu 20 Joins | nach Abschluss Belegung ≤ neue Platzzahl | P | R1-42 | R-09 |
| CON-005 | 20 parallele Prüfungsstarts desselben Nutzers mit **verschiedenen** `request_key` | genau 1 Session `in_progress` je Form; alle Antworten liefern dieselbe `session_uid` | P | R1-42 | R0-08 `start`, Δ-21-3 |
| CON-006 | CON-001, -002, -005 mit erzwungenem `GET_LOCK = NULL` | dieselben Invarianten; nur zusätzliche 409/Rückbauten; Zähler `named_lock_unavailable` > 0; Site-Health-Warnung | P | R1-42 | §2.5 |
| CON-007 | Code erwirbt M3 und danach M1 bzw. Mutex in offener TX | `LogicException` (Test/Debug); in Produktion Log + Zähler, Operation läuft | U | R1-42 | §2.4 |
| CON-008 | Erstanlage `ccap_question_progress` durch 50 parallele erste Antworten auf dieselbe Frage | 1 Zeile; alle Antworten gezählt nach Idempotenzregel; 0 Deadlocks dank R-2 | P | R1-42 | R-1, R-2 |
| CON-009 | Statischer Check: `FOR UPDATE`/`LOCK IN SHARE MODE` nur mit PK-/Unique-Gleichheit und nur nach Insert-Vorab bzw. auf Elternzeilen | CI-Regel (Query-Katalog/Grep + Review-Liste NFR-012) ohne Fund | U | R1-42 | R-1, R-3 |
| CON-010 | `paid` und `refunded` desselben Quellobjekts parallel und vertauscht | 1 Grant; Endzustand = höchste `source_seq`; kein Wiederaufleben | P | R1-42 | R-04 |
| CON-011 | Zwei Veröffentlichungen desselben Kurses gleichzeitig | eine erfolgreich, zweite `ccap_publish_busy`; 1 neue Kursrevision | P | R1-42 | REV-038 |
| CON-012 | Import-Worker wird angehalten (SIGSTOP), Lease läuft ab, zweiter Worker übernimmt; erster wird fortgesetzt | erster Worker scheitert am Fencing (`affected_rows = 0`), schreibt keinen Schritt; kein Duplikat | I | R1-42 | §2.4 Fencing, RB-02 |
| CON-013 | Ablauf-Cron und manuelle Verlängerung parallel | nie „aktiv mit `valid_until` in der Vergangenheit“ | P | R1-42 | R-10 |
| CON-014 | Kontolöschung parallel zu Join/Grant | kein Seat/Grant auf gelöschtem Konto | P | R1-42 | R-12 |
| CON-015 | Doppelte Rate-Limit-Zählung unter 100 parallelen Logins | Zählerstand = Requestzahl (±0); Speicher weg → Login, Reset, Registrierung, Claim, Einlösen, Einladungen, Datenschutzanträge fail-closed, Lernen fail-open; bekanntes Gerät darf einloggen | P | R1-42 | E-46, E-152, E-263 |
| CON-049 | Aufgabe zweimal parallel abgeben (zwei Tabs) | genau ein Übergang `draft → submitted`; zweite Antwort 200 mit gleichem Stand | P | R1-42 | E-168 |
| CON-050 | Zwei Trainer bewerten dieselbe Abgabe gleichzeitig | eine `evaluation_no` je Bewertung, keine verlorene Bewertung; zweiter erhält `ccap_version_conflict` oder schreibt Revision + 1 | P | R1-42 | E-168, E-74 |

### 5.2 Doppel-Submit und Idempotenz

| ID | Szenario | Erwartung | Art | Ziel | Bezug |
|---|---|---|---|---|---|
| CON-016 | `submit` derselben Session 10× parallel (Doppelklick, zwei Tabs, Retry) | genau 1 Bewertung (`evaluation_no = 1`), alle Antworten identisches Ergebnis; höchstens 1 Zertifikat; 1 Outbox-Event | P | R1-42 | R0-08 `submit`, R-06 |
| CON-017 | `submit` parallel zu `saveAnswer` | entweder Antwort vor Bewertung gespeichert und gewertet oder abgewiesen (`ccap_exam_not_running`); nie gespeichert-aber-nicht-gewertet nach `evaluated` | P | R1-42 | R0-08 |
| CON-018 | Gleicher `Idempotency-Key`, gleicher Body, parallel | eine Ausführung; zweite `409 ccap_request_in_progress` oder gespeicherte Antwort; gleicher Key + anderer Body → `409 ccap_idempotency_conflict` | P | R1-42 | R0-19 §2.1 |
| CON-019 | Aktivitätsabschluss doppelt (zwei Geräte) | 1 `ccap_activity_progress`; Kursfortschritt einmal erhöht | P | R1-42 | REV-036 |

### 5.3 Deadlock- und Fehlerinjektion

| ID | Szenario | Erwartung | Art | Ziel | Bezug |
|---|---|---|---|---|---|
| CON-040 | Echter Deadlock: Testoperation sperrt Zeilen A→B, zweiter Prozess B→A (nur Test-Fixture) | Opfer wird vom `TxRunner` vollständig wiederholt; Endstand korrekt; Deadlock gezählt | P | R1-42 | T-2, NFR-011 |
| CON-041 | Injektion 1213 an Anweisung *k* für jedes *k* jeder schreibenden Fachoperation (Sweep über Fault-Hook im `TxRunner`) | Ergebnis entweder vollständig committet (nach Retry) oder nichts; **keine** Anweisung nach dem Fehler im Autocommit ausgeführt (Query-Log) | I | R1-42 | T-1, Pass P4a |
| CON-042 | Injektion 1205 an Anweisung *k* (Sweep) | expliziter `ROLLBACK`; 0 Teil-Writes; `503 ccap_db_busy` mit `Retry-After: 2`; keine offene TX danach | I | R1-42 | T-3 |
| CON-043 | 3 aufeinanderfolgende 1213 | nach 3 Retries `503 ccap_db_busy`; keine After-Commit-Callbacks (Cache/Hook) ausgelöst | I | R1-42 | T-2, T-5 |
| CON-044 | Zeile per zweiter Verbindung > 5 s gesperrt, interaktiver Request wartet | Abbruch nach ≤ 5 s (Sitzungs-`innodb_lock_wait_timeout`), Worker frei | I | R1-42 | D-14-03, RB-08 |
| CON-045 | DB-Reconnect mitten in einem Lease-Schritt mit Mutex | Mutex gilt als verloren, Schritt bricht vor Commit ab und wird wiederholt | I | R1-42 | RB-05, §2.4 |
| CON-046 | L5-Lastlauf aller P-Szenarien | Deadlocks und Lock-Waits je Szenario protokolliert; jeder Deadlock durch ≤ 3 Retries gelöst; `ccap_db_busy` < 0,1 % | P | R1-42 | NFR-010/011 |

### 5.4 Objektcache und Request-Memo

| ID | Szenario | Erwartung | Art | Ziel | Bezug |
|---|---|---|---|---|---|
| CON-020 | **Stale access nach Refund mit Redis:** Käufer lädt Lektion (Caches warm), Refund-Webhook in anderem Prozess, nächster Request desselben Käufers | sofort Teaser/`403 ccap_content_locked`, Medienroute 403, `saveAnswer` 403; kein Cache-Treffer für Zugang | I | R1-25 | K3, P-11 |
| CON-021 | wie CON-020, aber Redis wird **während** des Refunds gestoppt (Invalidierung schlägt fehl) und danach wieder gestartet | weiterhin Zugang verweigert (K3 nie gecacht); K2-Übersicht höchstens 300 s alt | I | R1-25 | RB-06 |
| CON-022 | Rolle entzogen / Lernsperre gesetzt in Prozess A; Prozess B mit derselben Sitzung | nächster Request 403 (`ccap_account_locked` bzw. Cap fehlt) ohne Wartezeit | I | R1-25 | RBAC-006, E-79 |
| CON-023 | Kursrevision veröffentlicht; Lernender mit gewarmtem K1g-Cache | alle sehen die neue Revision (Vorwärtsrollen mit Besitzstand, E-103); alte K1-Einträge unverändert gültig | I | R1-25 | R0-23, E-103 |
| CON-051 | Bundle-Grant widerrufen (Redis aktiv), Lernender ruft Lektion eines enthaltenen Kurses auf | sofort `403 ccap_content_locked`, kein Cache-Treffer | I | R1-25 | E-25a, K3 |
| CON-052 | Basis-Grant läuft ab, abhängiger Zusatz-Grant ist gecacht angezeigt | Zugriff auf das Zusatzmodul sofort verweigert; K2 höchstens 300 s alt | I | R1-25 | E-26, K3 |
| CON-024 | **Set-after-Invalidate:** Leser liest alte DB-Werte, künstliche Pause, Schreiber committet und erhöht Generation, Leser schreibt Cache | nachfolgende Requests sehen neuen Stand (Leser hat unter alter Generation geschrieben) | I | R1-25 | §3.3 Leseprotokoll |
| CON-025 | `wp_set_current_user` innerhalb eines GET-Requests (Mobile-WebSession, Impersonation-Plugin) | Memo nach Wechsel leer; Antwort enthält keine Daten des ersten Nutzers | I | R1-25 | §3.2, CB-07 |
| CON-026 | Zwei Sites eines Multisite-Netzes teilen Redis; gleiche Kurs-/Nutzer-IDs | kein Treffer über Sitegrenze | I | R1-25 | §3.3 |
| CON-027 | Drop-in ohne `wp_cache_flush_group`; Generation aus Cache verdrängt | nur Fehltreffer, nie alter Wert | I | R1-25 | CB-09 |
| CON-028 | Zwei Nutzer, gleicher Kurs, K2-Übersicht gewarmt für Nutzer A | Nutzer B erhält eigene Werte; Schlüssel ohne `u<id>` für K2 schlägt im Builder fehl | U/I | R1-25 | §3.2 `forUser` |
| CON-029 | Objektcache-`FLUSHALL` und Stopp während L1 | 0 fachliche Fehler, p95 nach NFR-008 | P | R1-25 | NFR-008 |
| CON-030 | Lazy-Ablauf einer Prüfung in GET; dieselbe Antwort und nächste Übersicht | zeigen `evaluated`; kein Memo-Rest „in_progress“ | I | R1-25 | S-2, RB-01 |

### 5.5 HTTP, Page-Cache, CDN, Browser

| ID | Szenario | Erwartung | Art | Ziel | Bezug |
|---|---|---|---|---|---|
| CON-031 | **Page-Cache liefert fremde Lektion:** Käufer A ruft geschützte Lektion auf (Cache warm), danach Gast und Nichtkäufer B dieselbe URL über nginx-`fastcgi_cache`, Varnish „cache everything“ (ignoriert Cookies) und ein Cache-Plugin | B und Gast erhalten nie A's Inhalt oder A's Namen/Nonce; Antworten tragen H-X-Header; kein `X-Cache: HIT` | H | R1-25 | §3.5 H-1/H-2 |
| CON-032 | Umgekehrt: Gast-Teaser zuerst, dann Käufer | Käufer erhält Volltext (kein gecachter Teaser) | H | R1-25 | H-2 |
| CON-033 | Geschützter Block nur in Template-Part/Widget/synchronisiertem Block (nicht in `post_content`) | Seite nicht gecacht **oder** Block rendert fail-closed Teaser; Log `late_gate_detected` | H/I | R1-25 | H-6 |
| CON-034 | REST 403/404/409/429/503 im eigenen Namespace, anschließend berechtigter Request über Proxy | alle Fehler mit `no-store`; berechtigter Request bekommt 200 (kein negativer Cache-Treffer) | H | R1-25 | H-3, CB-12 |
| CON-035 | Einladungs-/Gruppenlinkseite mit Token | H-T-Header inkl. `Referrer-Policy: no-referrer`; externe Ressourcen erhalten kein Token im Referer | H/E | R1-25 | H-T, D09-1 |
| CON-036 | Geschützte Mediendatei nach Refund über CDN-Proxy | 403; keine CDN-Kopie (`no-store`/`Surrogate-Control`) | H | R1-25 | H-8, R10-D4 |
| CON-037 | Zertifikat widerrufen; Verifikation über Proxy direkt vorher und nachher | nachher sofort `revoked` | H | R1-25, R1-44 | R-42 |
| CON-038 | Öffentliche Katalogseite (H-P) als eingeloggter Käufer gerendert und vom Cache gespeichert | gespeicherte Seite enthält keine personalisierten CC-Fragmente/Nonces (H-5) | H | R1-25 | H-5 |
| CON-039 | Gemeinsamer Rechner: A sieht Lektion, meldet sich ab, Zurück-Taste; danach B meldet sich an | A's Lektion nicht sichtbar (bfcache-Reload, B-1); B sieht keine lokalen Prüfungsentwürfe von A (B-2) | E | R1-25 | §3.6 |
| CON-047 | Kurs veröffentlicht/zurückgezogen; öffentliche Kursseite im Page-Cache | `course_captain_cache_purge` ausgelöst; nach Purge neuer Stand | H | R1-25 | H-7 |
| CON-048 | Uhrabweichung PHP ↔ DB (DB-Zeitzone/`NOW()` um 2 min verschoben) | Prüfungsfrist und Grant-Ablauf unverändert korrekt (nur PHP-`Clock`) | I | R1-42 | R-8 |

---

## 6. Technische Festlegungen (übernommen)

Die Punkte stehen nicht im Beschlussprotokoll; sie sind Architekturfestlegungen ohne Produktfolge (wie D-14-03/04/06). Mit der Freigabe dieses Dokuments gilt jeweils die **Empfehlung** als festgelegt; R0-37 baut bereits auf D21-1, D21-2 und D21-4 auf.

| ID | Frage | Optionen | Festlegung | Betrifft |
|---|---|---|---|---|
| D21-1 | Speicherort der Lease-Sperren | a) `wp_options`-Zeile wie LB (`Lock`, `autoload=off`) · b) eigene Tabelle `ccap_lease` mit `fence` | **b)**: kein Konflikt mit `alloptions`/`notoptions`-Caches und Options-Drop-ins, Fencing-Spalte, sauber im Uninstall/Restore; Δ-21-1 an R0-06 | R0-06, R1-02, R0-37 |
| D21-2 | Verhalten bei `GET_LOCK = NULL` | a) weiter mit DB-Guard (LB) · b) Operation abbrechen (`503`) | **a)** + Health-Warnung + CON-006 als Pflicht; b) würde Hosts mit Proxy-SQL/Galera ohne `GET_LOCK` unbenutzbar machen | R1-42, R0-20 |
| D21-3 | Lese-Replikate (HyperDB/LudicrousDB) für Zugriffsprüfungen | a) K3 immer vom Primärserver (`toPrimary` vor Policy/Grant-Lesen) · b) Replikat mit Lag-Grenze | **a)**; nur K0/K1/K2 vom Replikat. Offiziell nur „best effort“-Support für Replikat-Setups (R0-20); DB-Query-Caches nicht unterstützt (E-198) | R1-03, R1-25 |
| D21-4 | Unique über generierte Spalten für „genau ein aktiver Platz je Nutzer/Gruppe“ und „genau eine laufende Session je Nutzer/Form“ | a) generierte Spalte (`… IF(status = 'active', user_id, NULL)` bzw. `IF(status = 'in_progress', 1, NULL)`) + UNIQUE · b) nur Mutex + Nachzählung | **a)** (MariaDB ≥ 10.2/MySQL ≥ 5.7, passt zu E-08); `dbDelta` kann generierte Spalten nicht zuverlässig → eigene Migration (R0-06 §6.2); Δ-21-2/-3 | R0-06, R0-37, R1-42 |
| D21-5 | `Clear-Site-Data` beim Abmelden | a) keiner; CC löscht nur eigene lokale Schlüssel (B-2) · b) `"cache"` · c) `"cache", "storage"` | **a)**: b)/c) wirken auf die ganze Origin und damit auf Theme/andere Plugins | R1-16, R1-20 |
| D21-6 | TTL der K2-Anzeigen | a) 300 s / Berichte 900 s · b) 60 s · c) ohne TTL, nur Generation | **a)**; mit read-your-writes durch `user_gen` sieht der Nutzer eigene Änderungen sofort; Wert nach R1-22-Messung anpassen | R1-25, R1-22 |

### 6.1 Technische Deltas

| Δ | Dokument | Änderung | Status |
|---|---|---|---|
| Δ-21-1 | R0-06 §4.7 | neue Tabelle `ccap_lease (name varchar(191) PK, owner varchar(64), fence bigint unsigned, expires_at datetime, heartbeat_at datetime, created_at)`; Options-Sperren entfallen (D21-1) | eingearbeitet (R0-06 §4.10) |
| Δ-21-2 | R0-06 §4.4 / R0-09 Δ-5 | `ccap_seat.active_user` (generiert) + UNIQUE `active_member (group_id, active_user)` – aus „optional“ wird Pflicht (D21-4) | eingearbeitet (R0-06 §4.4) |
| Δ-21-3 | R0-06 §4.2 / R0-08 | `ccap_exam_session.form_key` (Variante bzw. `random:<rule>`), `running` (generiert) + UNIQUE `running_form (user_id, form_key, running)` | eingearbeitet (R0-06 §4.2) |
| Δ-21-4 | R0-08 §6.5 | `exam_start`-Wartezeit 10 s → 2 s (D-14-03); Fallback bei `NULL` über Δ-21-3 statt „Prüfung auf laufende Versuche“ allein | an R0-08 übergeben |
| Δ-21-5 | R0-08 Z. 429 / R0-06 §4.8 | Lernfortschrittszeile per `INSERT IGNORE` **vor** der TX (R-2) | an R0-08/R0-06 §4.8 übergeben |
| Δ-21-6 | R0-09 §6.1 | Ränge M0, M5, M6 ergänzt; Reihenfolge nach §2.4 | eingearbeitet (Rangfolge §2.4; R0-09 §6.1 verweist darauf) |
| Δ-21-7 | R0-19 §2.1 Caching | „alle Statuscodes“ + CDN-/Proxy-Header (H-X) + H-T-Header; neue Aktion `course_captain_cache_purge` (Erweiterungsvertrag) | an R0-19 übergeben (§2.1 Caching) |
| Δ-21-8 | R0-14 RB-04 | Lock-Wait im Hintergrund 15 s; 1205 → expliziter `ROLLBACK` (T-3) | an R0-14 übergeben |

---

## 7. Nachweis

- LB gelesen: `src/Infrastructure/NamedLock.php` (vollständig, 118 Z.), `Lock.php:1-40`, `RequestMemo.php` (vollständig, 123 Z.), `Frontend/NoCache.php` (vollständig), `Gate/ContentGate.php:1-51` + Aufrufe `NoCache::mark` (`grep -rn "NoCache::mark" src`: `ContentGate.php:138, 207, 243`, `Router.php:148`, `WithdrawalFunction.php:212`), `Rest/Controllers/LearnerController.php:465-480`, `Gate/ProtectedFiles.php:353-390`, `Mobile/WebSession.php:239-242`, `Content/Repositories/ContentCache.php:14-53`; `grep -rn "RequestMemo::get" src` (`AccessService.php:317, 395, 459`, `ProductAccessRepository.php:155`, `WooCommerce/Integration.php:425`); `grep -rn "wp_cache_\|set_transient\|get_transient" src` (kein Treffer in `AccessService`/`EntitlementService`).
- Pass gelesen: `src/Orders.php:160-175`, `CHANGELOG.md:167-168`, `docs/TESTING.md:30, 57, 80-93`; `grep -rn "FOR UPDATE" src` (u. a. `Refunds.php`, `Waitlists.php`, `Orders.php`).
- Keine Änderung an LB oder Pass, keine Implementierung, kein Commit.

## Nachträge aus Folge-Tasks

| Datum | Quelle | Änderung | Status |
|---|---|---|---|
| 16.11.2026 | R0-38 §8 | Lease-Name für Importe ist global `import` je Site; die Batch-ID steht im `owner` | eingearbeitet (§2.2, §2.4) |
| 16.11.2026 | R0-37 | Lease `migration` je Site mit Fencing; vor jedem DDL Prüfung, ob der Lauf die Sperre noch hält | eingearbeitet (§2.2, §2.4) als Lease `schema-migration` (Name nach R0-37 §3.1) |

## Offene Punkte nach Einarbeitung

1. **D21-1…D21-6** stehen nicht im Beschlussprotokoll; sie gelten mit diesem Dokument als Architekturfestlegung (Empfehlung). Bestätigung durch die Architektur bei R1-25/R1-42. → **technisch, an R1-25/R1-42 übergeben** (kein Auftraggeber-Beschluss, kein Blocker für Gate A): Architekturbestätigung bei Taskbeginn
2. **Lease-Name Migration:** Der Nachtrag nennt `migration`, R0-37 §3.1 `schema-migration`; hier gilt `schema-migration`. → **erledigt durch E-300** (`schema-migration`)
3. **M4 bei Modul-Grants (E-27):** Schlüssel `grant-chain:<user>:<course>` deckt auch Modulketten ab (gröber, aber deadlockfrei); feinere Sperre je Modul ist nicht nötig, solange R1-42 keine Engpässe misst. → **technisch, an R1-42 übergeben** (kein Auftraggeber-Beschluss, kein Blocker für Gate A): Messung; Modulsemantik nach E-286 unverändert
4. **Bekannte Geräte bei fail-closed (E-46/E-263):** Wie das Geräte-Cookie ohne Zählerspeicher geprüft wird, regelt R0-31/R1-45, nicht dieses Dokument. → **technisch, an R1-45 übergeben** (kein Auftraggeber-Beschluss, kein Blocker für Gate A): R0-31 §3.4 HM-5, GM-204
