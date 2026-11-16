# R0-06 · ADR Datenmodell Course Captain (Entwurf)

**ADR-Nr.:** R0-06 (deckt die Datenanteile von ADR-001 Inhaltsspeicherung, ADR-002 Lern-/Prüfungsdaten, ADR-003 Inhaltsrevisionen ab; Teile von ADR-006 Queue/Outbox)
**Status:** **Vorgeschlagen – Freigabe Auftraggeber/Architektur offen.** Task R0-06 ist **nicht** abgehakt. Es gibt keine Implementierung und keine DDL-Dateien.
**Stand:** 16.11.2026
**Bezug:** TODO R0-06, R0-DATA-001, R0-DATA-002 (Teil), ADR-001…003/006, R1-02; Konzept Kap. 9.2, 22.3, 28.2, 28.3, 31.2, 35, 37, 46.2; `docs/r0/R0-02-inventar/A…C`; `docs/r0/R0-36-suite-vertragsdeltas.md` §2.2/2.5; `docs/r0/ENTSCHEIDUNGEN.md` (E-07, E-08, E-27, E-29, E-44)
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77` (nur gelesen). Pfade sind relativ zu `bootspruefung-learning/`.

**Nachfolger, die auf diesem ADR aufbauen:** R0-07 (Objekt-Scope), R0-08 (Activity/Prüfung), R0-09 (Grant-Automat), R0-10 (Import), R0-14 (Volumen), R0-16 (Backup), R0-23 (Revisionen laufender Kurse), R0-37 (Schema-Upgrade), R1-02 (Storage).

---

## 1. Kontext

### 1.1 Was die Quelle tatsächlich hat (Codebeleg)

- `src/Infrastructure/Database/Schema.php::definitions()` (`:49`) definiert **55 Tabellen** mit Präfix `{$wpdb->prefix}bp_` (Zählung der Schlüssel `'<name>' => "` von `:55` bis `:847`; Inventur C §3.1 listet ebenfalls 55 Zeilen). *Hinweis: Der Auftrag nennt 56 Tabellen; Code und Inventur zeigen 55. Die 41 Fremdschlüssel sind bestätigt (`Schema::foreignKeys()`, `:871`).*
- Alle Tabellen: `ENGINE=InnoDB {charset_collate}` (`Schema.php:858`); InnoDB wird geprüft (`Migrator::assertInnoDb`, `Migrator.php:366`).
- Inhalte liegen **vollständig in eigenen Tabellen** (Kurs, Modul, Frage, Revision, Medien, Video-Lektion). Nur FAQ (`bp_faq`, `bp_customer_faq`) und Tipps (`bp_tip`) sind CPTs (Inventur A INV-A-138, Inventur D Menü 9–13).
- Migration: **deklarativ per `dbDelta`**, danach Fremdschlüssel (nicht fatal, `bp_learning_fk_issues`), Drift-Prüfung gegen `information_schema`, eine Datenreparatur (T196). Es gibt keine einzelnen Migrationsdateien (Inventur C §3.1 „Migrationen“, §5.1).
- Fremdschlüssel-Politik (`Schema.php:865-870`): „Content wird nie hart gelöscht (source_archived); Lerndaten verweisen deshalb mit RESTRICT, reine Kindzeilen mit CASCADE“. 20 CASCADE, 20 RESTRICT, 1 SET NULL.
- **Befunde, die das Zielmodell ändern müssen:**
  1. `bp_entitlement.idempotency_key` ist **nicht unique**; eindeutig ist nur `reference_scope(reference_key, course_code, feature_code)` (Inventur B §1.3 Nr. 4, `Schema.php:662`).
  2. Grants tragen Woo-Spalten (`order_id`, `order_item_id`, `product_id`, `variation_id`, `sku_snapshot`) und `reference_key` = `wc_item:<id>` (Inventur B §1.3 Nr. 2).
  3. Gruppen entstehen nur aus Bestellpositionen; `learning_group.join_token` und `learning_group_member.invite_token` sind **im Klartext** gespeichert (`varchar(64)`, unique). Konzept 35.1 verlangt „sicher gehashtes Token“.
  4. Zugangsgranularität Kurs × fachlichem Feature-Code (`navigation_trainer`, `radio_training` …; Inventur B §1.3 Nr. 5) → E-27: je Kurs mit neutralen Scopes.
  5. Fachfelder im Content-Kern: `official_number`, `content_role`, `source_catalog`, `source_page`, `exam_rule.verified*`, Default `source_system='boatingschool'` (INV-C-038/039/050).
  6. Warteschlangen in Optionen statt Tabellen (`lbvc_pending`, `bp_learning_reconcile_retry`; Inventur B §3.2). Es gibt keine Inbox/Outbox.
  7. Zeitstempel-Defaults `DEFAULT CURRENT_TIMESTAMP` (Makro TS) hängen an der `time_zone` der DB-Sitzung, nicht zwingend UTC.
  8. Es gibt **keine Zertifikate**, keinen Kurs- oder Lektionsfortschritt außer `video_progress` und **keine Kursrevision**. Revisioniert sind nur Fragen (`question_revision`, INV-C-111).

### 1.2 Vorgaben aus dem Konzept

- Kap. 9.2: Hybrid; CPT/Block-Editor für Kurse und Lektionen, eigene Tabellen für Fragen, Beziehungen, Lernstände, Prüfungs-Snapshots und Freischaltungen; keine großen JSON-Blobs in `wp_options`.
- Kap. 28.3: unveränderliche Prüfungssnapshots, Idempotenz, Statusautomaten, Completion ≠ Mastery ≠ Assessment.
- Kap. 31.2/35.2: ein Grant je Quellberechtigung, eindeutiger `grant_source_key` (Quelle + Objekt + Position/Seat + Revision, kurs- und sitegebunden); `revoked` ist endgültig je Quellrevision.
- Kap. 35.4: Zertifikat referenziert Lernenden, Kurs-/Inhaltsrevision, Evidenz-Snapshot und Templateversion; Zustände `issued/corrected/revoked`.
- Kap. 37: Entitätenliste, UTC, Eindeutigkeiten (`grant_source_key`, `event_id + connector_id`, `attempt_id + submission_revision`, `certificate_id`, einmalige Claims), Compare-and-swap, Backfill, Dry-run, Rollback/Forward-Fix.
- Kap. 22.3: Zielvolumen 10 000 Lernende, 100 Kurse, 100 000 Fragen.
- Entscheidungen (Empfehlungen, noch nicht beschlossen): E-07 Single-Site offiziell, E-08 InnoDB + JSON-Funktionen Pflicht, E-27 Grant je Kurs mit Scopes, E-29 kein Wiederaufleben, E-44 Aufbewahrungsfristen.

---

## 2. Entscheidung (Vorschlag) – Kurzfassung

1. **Hybrid (ADR-001):** **5 CPTs** für redaktionelle, editorfähige Inhalte (`cc_course`, `cc_module`, `cc_lesson`, `cc_faq`, `cc_tip`) plus WP-Mediathek. Alles, was Volumen, Eindeutigkeit, Konkurrenzschutz oder Unveränderlichkeit braucht, kommt in **39 eigene Tabellen** `{$wpdb->prefix}cc_*`. Dazu kommt 1 bedingte Tabelle (`cc_request_idempotency`, nur bei Beschluss in R0-19).
2. **Bewertungsrelevante Inhalte sind tabellenbasiert und unveränderlich:** Fragen, Antworten, Prüfungsregeln und Kursrevisionen. WP-Post-Revisionen dienen nur dem Editieren, nie als Nachweisquelle (sie lassen sich kürzen und löschen).
3. **Fremdschlüssel nur zwischen `cc_`-Tabellen**, nie auf `wp_posts`/`wp_users`. Sie werden nach `dbDelta` idempotent angelegt. Schlägt das fehl, ist das nicht fatal, wird aber in Health/Monitor gemeldet (LB-Muster INV-C-036/048). Beziehungen zu CPTs und Nutzern sichert die Anwendung (Lösch-Hooks plus Orphan-Check). → Entscheidungsbedarf D-1.
4. **Eindeutigkeit ist DB-erzwungen**, Idempotenzspalten sind `NOT NULL`. Der Grant-Quellschlüssel ist UNIQUE über `(source_type, source_id, source_line, source_revision, course_id)`. `idempotency_key` aus LB entfällt, weil der Quellschlüssel selbst die Idempotenz trägt.
5. **Zustandswechsel mit Compare-and-swap** (`row_version`) und kurzen Transaktionen. Bei Grant-Übergängen schreiben Grant, Grant-Event und Outbox-Eintrag **in derselben Transaktion** (transaktionale Outbox).
6. **Migration:** `dbDelta` nur für additive Strukturänderungen, nummerierte idempotente PHP-Schritte für alles andere. Lock- und Triggermodell nach Pass Captain (nicht wartendes `GET_LOCK` je Site, Fingerprint plus einmaliges Cron-Ereignis). Keine Rückmigration; Rollback heißt Restore von DB und Dateien zusammen (Details §6).
7. **Zeit:** alle `*_at`-Spalten `datetime` in **UTC, von PHP gesetzt** (`gmdate`). Es gibt keine DB-Defaults `CURRENT_TIMESTAMP` (Befund 7).
8. **Engine/Charset:** `ENGINE=InnoDB` plus `$wpdb->get_charset_collate()` (utf8mb4). Indexlänge ≤ 3072 Byte setzt E-08 voraus (MySQL 8 / MariaDB 10.6+).

---

## 3. Entitätenliste und Mapping (R0-DATA-001)

**Legende:** T = eigene Tabelle, CPT = Custom Post Type + Post-Meta, Opt = Option/Settings, abgel. = abgeleitet (keine eigene Speicherung). Owner = Storage-Owner (Modul, das schreibt). Begründungskürzel: **V** = Volumen, **A** = Abfragen (Joins, Filter, Pagination), **TX** = Transaktion/Konkurrenz/Unique, **R** = Revision/Unveränderlichkeit, **E** = Editor/WP-Nativität (Block-Editor, Suche, REST, Rechte).

### 3.1 Inhalt

| # | Entität (fachneutral) | Quelle LB (`Schema.php:Zeile`) | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-01 | Kurs | `course` (:55) | **CPT `cc_course`** + Meta | Core/Content | E: Block-Editor, Slug, Suche, SEO (R1-37), WP-REST, Capabilities. V: ≈100 Kurse (22.3). Code = `post_name` (LB: Slug = Code, INV-C-129). `course_type` (`full/combo/addon`) ist fachlich, Bundles kommen in R2 (E-25). |
| E-02 | Kursrevision (veröffentlichter Stand) | – (neu; LB hat keine Kursrevision) | **T `cc_course_revision`** | Core/Content | R: unveränderlicher Stand, auf den Prüfungen, Fortschritt und Zertifikate verweisen (ADR-003, 35.4). WP-Revisionen sind dafür ungeeignet (kürzbar, löschbar, `wp_revisions_to_keep`). |
| E-03 | Modul / Kapitel | `module` (:69), `video_module` (:290) | **CPT `cc_module`** | Core/Content | E; V klein. LB trennt Fragenmodul und Videokapitel. Neutral gibt es **ein** Modul mit Lektionen **und** Fragenpool (INV-C-041). Modul-`version` aus LB geht in die Kursrevision. |
| E-04 | Kurs↔Modul (Reihenfolge, Pflicht, Regeln) | `course_module` (:81) | **T `cc_course_module`** | Core/Content | TX/A: n:m mit Unique und Sortierung. Das lässt sich nicht als `post_parent` abbilden. |
| E-05 | Lektion / Activity | `video_lesson` (:302), `video_lesson_step` (:319) | **CPT `cc_lesson`**; Modulzuordnung über `post_parent` + `menu_order` | Core/Content + Activity-Provider | E: Inhalt im Block-Editor; Schritte (`video_lesson_step`) werden Blöcke. LB: eine Lektion gehört genau einem Modul (`video_lesson.module_id`), also 1:n. Activity-Typ als Meta `_cc_activity_type` (Video, Text, Karteikarten …; ADR-004/R0-08). |
| E-06 | Herkunft/Provenienz von CPT-Objekten | Makro SRC (`Schema.php:924`) | **T `cc_source_map`** | Import | TX: `(source_system, source_id)` muss für den Import eindeutig sein. Post-Meta kann das nicht DB-seitig erzwingen. Für Tabellenentitäten liegt SRC als Spalten vor. |
| E-07 | Frage | `question` (:91) | **T `cc_question`** | Core/Fragenbank | V: 100 000 (22.3). A: Auslieferungs-SQL mit Joins (INV-C-130). TX: Unique Quelle. CPT würde `wp_posts` aufblähen, und Meta-Joins wären langsam. `official_number`/`content_role` werden zu neutraler `reference_no`. |
| E-08 | Fragenrevision | `question_revision` (:105) | **T `cc_question_revision`** | Core/Fragenbank | R: Versuche verweisen auf `revision_id` (RESTRICT). Hash-basierte Revisionierung (INV-C-111). Fachfelder `source_catalog`, `source_page`, `source_question_id` entfallen. |
| E-09 | Antwortoption | `answer` (:136) | **T `cc_answer`** | Core/Fragenbank | R: gehört an die Revision. V: ≈4 je Revision. |
| E-10 | Themen-/Kompetenz-Tags | Spalten `topic`, `subtopic`, `competency`, `smart_category`, `scope` in `question_revision` | **T `cc_question_tag`** | Core/Fragenbank | A: Filter, Smarttrainer-Kategorie, Kompetenz. Feste Spalten sind fachlich geprägt; Konzept 9.2 nennt `cc_question_tag`. WP-Taxonomien gehen nicht, weil Fragen keine Posts sind. |
| E-11 | Modul↔Frage (Pool) | `module_question` (:145) | **T `cc_module_question`** | Core/Fragenbank | V/A: Fragenpool je Modul mit Unique. |
| E-12 | Activity↔Frage (Inline-Quiz) | `video_lesson_question` (:332) | **T `cc_activity_question`** | Activity-Provider | A/TX: Unique je Lektion; FK auf Frage RESTRICT (wie LB). |
| E-13 | Medium | `media` (:154) | **T `cc_media`** (optional verknüpft mit WP-Anhang über `attachment_id`) | Content/Medien | V/A: Import-Medien mit sha256-Dedupe, Rechte- und Provenienzstatus, Ablage auch außerhalb der Mediathek (INV-C-122/123). Editor-Uploads bleiben WP-Anhänge und bekommen einen `cc_media`-Datensatz. |
| E-14 | Medienzuordnung | `content_media` (:177) | **T `cc_content_media`** | Content/Medien | A: polymorph (Revision, Lektion), mit Overrides. |
| E-15 | FAQ / Kurs-FAQ | CPT `bp_faq`, `bp_customer_faq` (Inventur A INV-A-138) | **CPT `cc_faq`** (Vorschlag: ein Typ mit Sichtbarkeit „öffentlich/Teilnehmende“) | Modul FAQ | E: redaktionell, WP-nativ; E-16 (Mechanik R1). |
| E-16 | Lerntipp | CPT `bp_tip` (Inventur D Menü 13) | **CPT `cc_tip`** | Modul Tipps | E; E-16. |

### 3.2 Prüfung

| # | Entität | Quelle LB | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-17 | Prüfungsregel (AssessmentBlueprint, versioniert) | `exam_rule` (:189) | **T `cc_exam_rule`** | Prüfung | R/TX: Unique `(course_id, version)`; Sitzungen verweisen darauf. `verified*` entfällt (E-23), dafür Status `draft/published/retired`. Bestehensregel ist Pflicht, kein hartcodiertes 0.75 (INV-C-132). |
| E-18 | Blueprint-Anteil je Modul | `exam_blueprint` (:205) | **T `cc_exam_blueprint`** | Prüfung | TX: Unique `(rule_id, module_id)`. |
| E-19 | Feste Prüfungsvariante | `exam_paper` (:214) | **T `cc_exam_variant`** | Prüfung | Neutral für „Bogen“ (INV-C-133). |
| E-20 | Fragen einer Variante | `exam_paper_question` (:225) | **T `cc_exam_variant_question`** | Prüfung | Unique Position. |
| E-21 | Prüfungsversuch inkl. Snapshot (Attempt + AttemptSnapshot) | `exam_session` (:559) | **T `cc_exam_session`** | Prüfung | TX: `start_token` unique, Zeilensperre, CAS. R: `snapshot` + `snapshot_hash` unveränderlich (28.3). |
| E-22 | Snapshot-Frage im Versuch (Answer) | `exam_session_question` (:582) | **T `cc_exam_session_question`** | Prüfung | R: fixiert `revision_id`, `answer_order`. V: ≈4,5 Mio. (§7). |

### 3.3 Lernen und Fortschritt

| # | Entität | Quelle LB | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-23 | Lernantwort (Attempt-Log) | `attempt` (:490) | **T `cc_attempt`** | Lernen | V: größte Tabelle (§7). TX: Idempotenz-Unique. |
| E-24 | Gewählte Optionen | `attempt_answer` (:508) | **T `cc_attempt_answer`** | Lernen | Kindzeilen CASCADE. |
| E-25 | Lernstand je Frage (MasteryState) | `progress` (:518) | **T `cc_question_progress`** | Lernen/Smarttrainer | TX: `SELECT … FOR UPDATE` (`LearningService.php:498`); Unique `(user_id, question_id)`; `algorithm_version` (ADR-005). Trägt auch die Karteikarten-Modi (E-21, `praxis_progress` entfällt). |
| E-26 | Activity-Fortschritt (LearningProgress/Completion) | `video_progress` (:619) | **T `cc_activity_progress`** | Activity-Provider | V/TX: Unique `(user_id, activity_id)`. Verallgemeinert Video auf alle Activity-Typen. |
| E-27 | Kursfortschritt (aggregiert) | – (neu; Konzept 9.2 `cc_course_progress`) | **T `cc_course_progress`** (materialisiert, aus E-23/E-25/E-26 neu aufbaubar) | Lernen | A: „Mein Lernen“ und Reports ohne Volltabellenscan (22.3). Die Detailtabellen bleiben die Wahrheit. |
| E-28 | Kompetenznachweis (CompetencyEvidence) | – (neu) | **abgel.**: bestandene `cc_exam_session` + `cc_certificate`. Eine eigene Tabelle gibt es erst, wenn R0-08 manuelle oder externe Nachweise beschließt. | Prüfung | Nichts ohne Beleg erfinden. Die drei Dimensionen bleiben trotzdem getrennt (28.3). |
| E-29 | Favorit | `favorite` (:535) | **T `cc_favorite`** | Lernen | Unique, INSERT IGNORE (Inventur A §3.1). |
| E-30 | Sammlung | `favorite_collection` (:544) | **T `cc_collection`** | Lernen | 30 je Kurs (`CollectionService.php:19`). |
| E-31 | Sammlungseintrag | `favorite_collection_item` (:553) | **T `cc_collection_item`** | Lernen | zusammengesetzter PK (wie LB). |

### 3.4 Zugang, Gruppen, Nachweise

| # | Entität | Quelle LB | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-32 | Grant (Zugangsgrund) | `entitlement` (:641) | **T `cc_grant`** | Core/Access (System of Record, 31.2) | TX: Unique-Quellschlüssel, CAS, Ablauf-Cron. Woo-Spalten entfallen und kommen in den R3-Adapter (Befund 2). Statt `feature_code` gibt es `scopes` (E-27). |
| E-33 | Grant-Ereignis (Historie) | – (LB: `meta.manual`/`extended` als JSON in `entitlement.meta` + `audit_log`) | **T `cc_grant_event`** | Core/Access | R/TX: append-only Übergangsprotokoll, Dedupe externer Events (35.2 „verspätete Ereignisse protokolliert“). |
| E-34 | Zugangsregel (Teilumfang, Drip) | `access_rule` (:667) | **T `cc_access_rule`** | Core/Access | A: wird je Anfrage komplett gelesen (`RequestMemo access_rules`, Inventur A §3.4); kursübergreifend abgefragt (`ExpiryReminder::freeCourses`). `course_code`/`feature_code` → `course_id`/`scope`. |
| E-35 | Gruppe | `learning_group` (:733) | **T `cc_group`** | Core/Gruppen | TX: Plätze unter `NamedLock group-seats:<id>` (Inventur B §5.4). Bestellspalten → neutrale Quelle. Manuelle Anlage (fehlt in LB, E-17). |
| E-36 | Gruppe↔Kurs | `learning_group.scopes` (JSON `[{course, feature}]`) | **T `cc_group_course`** | Core/Gruppen | A: „Gruppen eines Kurses“ für die Trainer-Objektrechte (Kap. 36) ohne JSON-Scan. |
| E-37 | Platz / Claim (Seat, Claim) | `learning_group_member` (:753) | **T `cc_seat`** | Core/Gruppen | TX: einmaliger Claim; Token **nur als Hash** (Befund 3, 35.1). E-Mail ist nur Kommunikation. |
| E-38 | Käuferreferenz (BuyerReference) | `learning_group.owner_user_id`, `order_*` | **Spalten** in `cc_group` (`owner_user_id`, `source_type`, `source_id`) und `cc_grant` (`source_*`) | Core/Access | Keine Vollkopie fremder Bestelldaten (Kap. 37 „Dateneigentum“). Woo bleibt Owner. |
| E-39 | Zertifikat inkl. Revision und Verifikationstoken | – (neu; LB hat keine Zertifikatsfunktion, `grep -i certificate` trifft nur TLS/Signatur) | **T `cc_certificate`** (eine Zeile je Zertifikatsrevision) | Nachweise | R: `corrected` erzeugt eine neue Revision, `revoked` setzt den Status. TX: Unique je Evidenz und Revision; Token-Hash unique (35.4). |
| E-40 | Woo-Produktzuordnung | `product_access` (:680) | **nicht im Core**; eigene Adaptertabelle in R3 (Owner Woo-Adapter, Name in R3-02) | Woo-Adapter (R3) | Konzept 47: kein `wc_item` im Free-Kern (INV-C-045). |

### 3.5 Integration, Betrieb, Datenschutz

| # | Entität | Quelle LB | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-41 | Eingehendes Ereignis (InboxEvent) | – (LB: Transients/Order-Meta für Dispute-Dedupe, Inventur B §5.3) | **T `cc_inbox_event`** | Integration | TX: Unique `(connector_id, event_id)` (Kap. 37). Retry/Quarantäne (35.2). |
| E-42 | Ausgehendes Ereignis (OutboxEvent) | – (LB: Option `lbvc_pending`, Inventur B §3.2) | **T `cc_outbox_event`** | Integration | TX: im selben Commit wie die Fachänderung. V: Queue in Optionen widerspricht 9.2. |
| E-43 | Job | – | **kein eigener Tabellenvorschlag**: Action Scheduler (falls vorhanden) bzw. WP-Cron; lange Läufe über Import-Batch und Backfill-Cursor | Infrastruktur | ADR-006 entscheidet; Konzept 9.2 „ggf. `cc_job`“. |
| E-44 | Integrationsverbindung | – | **Opt** (`cc_integrations`; Secrets nach R0-17/R0-21) | Integration | V: wenige Einträge. |
| E-45 | Importlauf | `import_batch` (:434) | **T `cc_import_batch`** | Import | Zustandsautomat (Inventur C §5.2). |
| E-46 | Import-Protokoll | `import_item_log` (:452) | **T `cc_import_item_log`** | Import | V: Retention 14/180 Tage (INV-C-121). |
| E-47 | Audit (AuditRecord) | `audit_log` (:465) | **T `cc_audit_log`** | Infrastruktur | V; append-only. |
| E-48 | Lokaler Override | `override` (:479) | **T `cc_override`** | Import/Content | Unique `(object_type, object_id, field)`. |
| E-49 | Aufbewahrungsregel (RetentionRule) | Konstanten/Filter (INV-C-121/149) | **Opt** (Settings, E-44) | Datenschutz | keine Tabelle nötig |
| E-50 | Rate-Limit-Bucket | `mobile_rate_limit` (:847) | **T `cc_rate_limit`** | Infrastruktur | TX: atomar `INSERT … ON DUPLICATE KEY` (INV-C-047). |
| E-51 | Request-Idempotenz (REST) | `mobile_idempotency` (:796) | **T `cc_request_idempotency`** – **bedingt** (nur wenn R0-19 einen Idempotency-Key-Header für die Web-REST beschließt; E-05) | REST | TX: Unique `(user_id, scope, idem_key)`. |
| E-52 | Schema-/Migrationszustand | Optionen `bp_learning_schema_version`, `…_migration_lock`, `…_migration_failed`, `…_fk_issues` | **Opt** `cc_db_version`, `cc_schema_fingerprint`, `cc_migrations_applied`, `cc_backfill_state`, `cc_migration_failed`, `cc_fk_issues` | Infrastruktur | §6 |

**Summe:** 52 Entitäten: 39 in festen Tabellen, 1 in einer bedingten Tabelle (E-51), 5 als CPT, 3 als Option/Setting, 1 abgeleitet (E-28), 1 als Spalten in anderen Tabellen (E-38), 1 als Infrastruktur ohne eigene Tabelle (E-43) und 1 außerhalb des Core (E-40, R3). Medien werden zusätzlich als WP-Anhang geführt.

### 3.6 Abwägung CPT vs. Tabelle für Kurs/Modul/Lektion

| Kriterium | CPT (gewählt) | eigene Tabelle (LB-Weg) |
|---|---|---|
| Editor/UX | Block-Editor, Autosave, Vorschau, Papierkorb | eigener Editor nötig |
| Revisionen | WP-Revisionen zum Editieren; der Nachweis steckt in `cc_course_revision` | alles selbst bauen |
| Suche/SEO/REST/CLI | nativ (`show_in_rest`, Sitemaps, `wp post`) | selbst bauen (R1-37) |
| Rechte | `map_meta_cap` mit `cc_*`-Caps (R0-07) | selbst bauen |
| Integrität | keine FK auf `wp_posts` → Lösch-Hooks + Orphan-Check | harte FKs möglich |
| Import-Eindeutigkeit | über `cc_source_map` | Spalten-Unique |
| Volumen | ≈100 Kurse, ≈2 000 Module, ≈20 000 Lektionen (§7): unkritisch, aber WP-Revisionen je CPT begrenzen (`wp_revisions_to_keep`) | – |

---

## 4. Tabellen-Spezifikation (Entwurf)

### 4.0 Konventionen (gelten für alle Tabellen)

- Name `{$wpdb->prefix}cc_<name>`; `ENGINE=InnoDB` + `$wpdb->get_charset_collate()`; `dbDelta`-taugliche DDL (`PRIMARY KEY  (id)` mit zwei Leerzeichen, ein Feld je Zeile, keine FKs im `CREATE`).
- `id bigint(20) unsigned NOT NULL AUTO_INCREMENT`, `PRIMARY KEY (id)`, wenn nichts anderes genannt ist. Verweise auf Posts/Nutzer sind `bigint(20) unsigned`.
- **TS** = `created_at datetime NOT NULL`, `updated_at datetime NOT NULL`. Beide sind UTC und werden von PHP gesetzt; es gibt keinen DB-Default (Befund 7).
- **SRC** (Provenienz, aus LB `sourceColumns()` neutralisiert) = `source_system varchar(32) NOT NULL DEFAULT 'local'`, `source_id varchar(191) NULL`, `source_hash char(71) NULL` (`sha256:`+64), `package_id varchar(64) NULL`, `imported_at datetime NULL`. Mit `UNIQUE source (source_system, source_id)`. `source_id NULL` erlaubt mehrere lokal erstellte Datensätze; LB hat `NOT NULL`, weil dort alles importiert wird.
- **UID** = `uid char(36) NOT NULL` (UUID v4) mit `UNIQUE uid`: stabile externe ID für REST, Export und Events (ADR-003 „stabile IDs“, 30.3). Die interne `id` wird nie öffentlich zugesagt.
- **CAS** = `row_version int unsigned NOT NULL DEFAULT 0`; Statuswechsel als `UPDATE … SET …, row_version = row_version + 1 WHERE id = %d AND row_version = %d` (Kap. 37 Compare-and-swap).
- **Idempotenz-/Unique-Spalten sind `NOT NULL`** (mit `''` bzw. `0` als neutralem Wert), weil MySQL mehrere `NULL` in einem UNIQUE zulässt und Dedupe sonst still versagt. `NULL` steht nur dort, wo mehrere „leere“ Zeilen gewollt sind (z. B. `cc_grant_event.source_event_id`).
- JSON-Spalten sind `longtext`, validiert in PHP; `JSON_EXTRACT` nur in Export und Löschung (E-08).
- **FK-Spalte** in den Tabellen unten: `→ziel (REGEL)` bedeutet einen harten FK nach Option D-1/B. `→post`/`→user` bedeutet Anwendungsintegrität (§4.9).

### 4.1 Inhalt

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes (Hauptabfrage) | FK / Löschregel |
|---|---|---|---|---|
| `cc_source_map` | `object_type varchar(32)` (`course/module/lesson/faq/tip`), `object_id bigint`, SRC ohne Unique-Makro, `locally_modified tinyint(1) 0`; TS | `UNIQUE source (source_system, source_id, object_type)`, `UNIQUE object (object_type, object_id)` | – (Uniques decken „Import findet Objekt“ und „Objekt zeigt Herkunft“ ab) | `object_id`→post; beim endgültigen Löschen des Posts mitlöschen |
| `cc_course_module` | `course_id`, `module_id` (bigint), `sort_order int 0`, `required tinyint(1) 1`, `rules longtext NULL` | `UNIQUE course_module (course_id, module_id)` | `course_sort (course_id, sort_order)`, `module_id` | →post (beide); Entfernen nur bei Entwurf, sonst neue Kursrevision |
| `cc_course_revision` | `course_id`, `revision_no int unsigned`, `status varchar(16)` (`published/superseded/archived`), `structure longtext` (JSON: Module, Lektionen inkl. WP-Revisions-ID und Inhalts-Hash, Regel-IDs, Zugangsregeln), `structure_hash char(71)`, `format_version smallint unsigned`, `published_by bigint NULL`, `published_at datetime` | `UNIQUE course_rev (course_id, revision_no)` | `course_status (course_id, status)` | →post; **nie löschen**, solange Versuche, Fortschritt oder Zertifikate verweisen |
| `cc_question` | UID, SRC, `reference_no varchar(32) NULL` (statt `official_number`), `language varchar(20)`, `status varchar(16) 'draft'` (`draft/published/archived`), `current_revision_id bigint NULL`, `author_user_id bigint NULL` (Objekt-Scope R0-07); TS | `UNIQUE uid`, `UNIQUE source` | `status`, `current_revision_id`, `reference_no` | nie hart löschen (Archiv, INV-C-108) |
| `cc_question_revision` | `question_id`, `revision_no int unsigned`, `content_hash char(71)` (LB `source_hash`), `package_id varchar(64) NULL`, `source_version varchar(64) NULL`, `question_type varchar(32)`, `question_text longtext`, `settings longtext NULL` (typspezifisch je Activity-Provider), `feedback_correct`/`feedback_incorrect longtext NULL`, `image_required tinyint(1) 0`, `editorial_status varchar(20) 'draft'` (`draft/review/published/deprecated`), `rights_status varchar(20)` (E-18: `own` für eigene Inhalte), `rights_owner varchar(255) NULL`, `license_reference longtext NULL`, `valid_from`/`valid_to date NULL`, `created_by bigint NULL`, `created_at`, `published_at datetime NULL` | `UNIQUE question_rev (question_id, revision_no)` | `question_hash (question_id, content_hash)`, `publish (question_id, editorial_status, valid_from, valid_to)` (beide aus LB) | `question_id`→`cc_question` (RESTRICT) |
| `cc_question_tag` | `revision_id`, `tag_type varchar(32)` (`topic/subtopic/competency/category/scope`), `tag_key varchar(191)`, `label varchar(255)` | `UNIQUE rev_tag (revision_id, tag_type, tag_key)` | `tag (tag_type, tag_key)` | `revision_id`→`cc_question_revision` (CASCADE) |
| `cc_answer` | `revision_id`, `answer_key varchar(16)`, `answer_text longtext`, `is_correct tinyint(1) 0`, `sort_order int 0` | `UNIQUE revision_key (revision_id, answer_key)` | – | →`cc_question_revision` (CASCADE, wie LB) |
| `cc_module_question` | `module_id`, `question_id`, `sort_order int NULL`, `required tinyint(1) 1` | `UNIQUE module_question (module_id, question_id)` | `question_id` | `question_id`→`cc_question` (CASCADE, wie LB); `module_id`→post |
| `cc_activity_question` | `activity_id`, `question_id`, `sort_order int 0` | `UNIQUE activity_question (activity_id, question_id)` | `question_id` | `question_id`→`cc_question` (RESTRICT, wie LB); `activity_id`→post |
| `cc_media` | UID, SRC, `attachment_id bigint NULL`, `storage_key varchar(255) NULL`, `package_path varchar(255) NULL`, `media_type varchar(20) 'image'`, `mime_type varchar(100)`, `size_bytes bigint unsigned 0`, `sha256 char(64)`, `alt_text varchar(255) NULL`, `caption`/`description`/`source_text longtext NULL`, `source_url varchar(2048) NULL`, `rights_owner varchar(255) NULL`, `license_reference longtext NULL`, `rights_status varchar(20)`, `status varchar(16) 'active'`; TS | `UNIQUE uid`, `UNIQUE source`, `UNIQUE attachment_id` | `sha256` (Dedupe), `rights_status` | `attachment_id`→post (SET NULL per Hook) |
| `cc_content_media` | `object_type varchar(32)`, `object_id bigint`, `media_id`, `role varchar(32)`, `sort_order int 0`, `caption_override longtext NULL`, `alt_override varchar(255) NULL` | `UNIQUE object_media (object_type, object_id, media_id, role)` | `media_id` | `media_id`→`cc_media` (RESTRICT) |

*Nicht übernommene LB-Spalten:* `content_role`, `source_catalog`, `source_page`, `source_question_id`, `source_module`, `media.vector_source` (in der Inventur nicht als neutral begründet; Prüfung in R0-10), `course.course_type`.

### 4.2 Prüfung

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK / Löschregel |
|---|---|---|---|---|
| `cc_exam_rule` | UID, SRC, `course_id`, `version varchar(64)`, `status varchar(16) 'draft'` (`draft/published/retired`, E-23), `valid_from`/`valid_until date NULL`, `time_limit_seconds int unsigned NULL` (NULL = ohne Limit), `passing_rule longtext` (Pflicht, kein Default), `settings longtext NULL` (Feedback-Sichtbarkeit E-20, Mischen, Versuchsgrenzen; R0-08/R0-27), `published_by bigint NULL`, `published_at datetime NULL`; TS | `UNIQUE uid`, `UNIQUE source`, `UNIQUE course_version (course_id, version)` | `course_status (course_id, status)` | `course_id`→post; veröffentlichte Regel nie ändern, nur `retired` + neue Version |
| `cc_exam_blueprint` | `rule_id`, `module_id`, `question_count int unsigned`, `rules longtext NULL` | `UNIQUE rule_module (rule_id, module_id)` | `module_id` | `rule_id`→`cc_exam_rule` (CASCADE) |
| `cc_exam_variant` | UID, SRC, `course_id`, `variant_no int unsigned`, `status varchar(16) 'draft'`, `sort_order int 0`; TS | `UNIQUE uid`, `UNIQUE source`, `UNIQUE course_variant (course_id, variant_no)` | – | `course_id`→post |
| `cc_exam_variant_question` | `variant_id`, `question_id`, `position int unsigned` | `UNIQUE variant_position (variant_id, position)` | `question_id` | `variant_id`→`cc_exam_variant` (CASCADE), `question_id`→`cc_question` (RESTRICT) |
| `cc_exam_session` | UID, `user_id`, `course_id`, `course_revision_id bigint NULL`, `rule_id`, `variant_id bigint NULL`, `mode varchar(16)` (`variant/random`), `status varchar(16)` (Automat R0-08; LB `running/evaluated/aborted/error`, Konzept 28.3 `created/in_progress/submitted/evaluated/expired/cancelled`), `start_token char(36)`, `snapshot longtext` (Regel, Blueprints, Zeitlimit, Bestehensregel, Seed, Reihenfolge; LB `rule_snapshot`, `ExamService.php:263-276`), `snapshot_hash char(71)`, `seed bigint unsigned NULL`, `evaluator_version varchar(32)`, `started_at datetime`, `deadline_at`/`submitted_at`/`evaluated_at datetime NULL`, `score`/`max_score int NULL`, `passed tinyint(1) NULL`, `result longtext NULL`, CAS | `UNIQUE uid`, `UNIQUE start_token` | `user_course (user_id, course_id, status)`, `status_deadline (status, deadline_at)` (Ablauf-Cron), `rule_id`, `variant_id`, `course_revision_id` | `rule_id`→`cc_exam_rule` (RESTRICT), `variant_id`→`cc_exam_variant` (RESTRICT), `course_revision_id`→`cc_course_revision` (RESTRICT); `user_id`→user (Eraser) |
| `cc_exam_session_question` | `session_id`, `position int unsigned`, `question_id`, `revision_id`, `answer_order longtext NULL`, `given_answer longtext NULL`, `self_assessed_known tinyint(1) NULL`, `revealed_at datetime NULL`, `is_correct tinyint(1) NULL`, `answered_at datetime NULL`, `flagged tinyint(1) 0` | `UNIQUE session_position (session_id, position)` | `question_id`, `revision_id` | `session_id`→`cc_exam_session` (CASCADE), `question_id`→`cc_question` (RESTRICT), `revision_id`→`cc_question_revision` (RESTRICT) |

**Idempotenz Abgabe:** wie LB über `SELECT … FOR UPDATE` plus Statusprüfung. Eine zweite Abgabe liefert das vorhandene Ergebnis (Inventur A §5.2, `ExamService.php:521, :537`), zusätzlich mit CAS auf `row_version`. Konzept 37 nennt „`attempt_id + submission_revision`“. Solange R0-08 keine Mehrfachabgabe (Teilabgaben, Nachbewertung) beschließt, entspricht das genau einer Abgabe je `uid`. Eine eigene Spalte `submission_no` wird erst mit diesem Beschluss ergänzt.

### 4.3 Lernen

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK / Löschregel |
|---|---|---|---|---|
| `cc_attempt` | `user_id`, `question_id`, `revision_id`, `course_id bigint NULL` (LB `context_ref` = Kurscode), `context varchar(32)` (`learn/lesson/review`), `context_ref varchar(64) NULL`, `is_correct tinyint(1) NULL`, `self_assessment varchar(16) NULL`, `answer_ms int unsigned NULL`, `idempotency_key char(36)`, `correction_of bigint NULL`, `algorithm_version smallint unsigned`, `created_at` | `UNIQUE user_idem (user_id, idempotency_key)` (LB: global `idempotency_key`; der Nutzerbezug verhindert Fremdkollisionen) | `user_question (user_id, question_id)`, `user_created (user_id, created_at)`, `revision_id`, `course_created (course_id, created_at)` (Reports ohne Scan, 22.3) | `question_id`→`cc_question` (RESTRICT), `revision_id`→`cc_question_revision` (RESTRICT) |
| `cc_attempt_answer` | `attempt_id`, `answer_id bigint NULL`, `answer_key varchar(16) NULL`, `free_text longtext NULL`, `position int 0` | – | `attempt_id`, `answer_id` | `attempt_id`→`cc_attempt` (CASCADE) |
| `cc_question_progress` | `user_id`, `question_id`, `attempts`/`correct_count`/`wrong_count`/`streak int unsigned 0`, `mastery_score decimal(5,4) 0`, `learn_status varchar(16) 'new'`, `algorithm_version smallint unsigned`, `first_answered_at`/`last_answered_at`/`due_at datetime NULL`, CAS | `UNIQUE user_question (user_id, question_id)` | `question_id`, `user_status (user_id, learn_status)`, **neu** `user_due (user_id, due_at)` (Smarttrainer-Fälligkeitsauswahl; in R0-14 messen) | `question_id`→`cc_question` (RESTRICT) |
| `cc_activity_progress` | `user_id`, `activity_id`, `course_id bigint NULL`, `activity_type varchar(32)`, `status varchar(16)` (`started/completed`), `position_seconds int unsigned 0`, `data longtext NULL`, `content_hash char(71) NULL` (Stand bei Abschluss), `completed_at datetime NULL`, `last_access_at datetime`, `created_at` | `UNIQUE user_activity (user_id, activity_id)` | `activity_id`, `user_course (user_id, course_id)` | `activity_id`→post (kein Löschen bei vorhandenem Fortschritt) |
| `cc_course_progress` | `user_id`, `course_id`, `course_revision_id bigint NULL`, `status varchar(16)` (`not_started/in_progress/completed`), `completion_ratio decimal(5,4)`, `mastery_ratio decimal(5,4) NULL`, `activities_done`/`activities_total int unsigned`, `started_at`/`completed_at`/`last_activity_at datetime NULL`, `updated_at`, CAS | `UNIQUE user_course (user_id, course_id)` | `course_status (course_id, status)`, `user_last (user_id, last_activity_at)` | `course_revision_id`→`cc_course_revision` (RESTRICT); neu aufbaubar (Backfill-Job) |
| `cc_favorite` | `user_id`, `question_id`, `context varchar(32) 'question'`, `created_at` | `UNIQUE user_question_context (user_id, question_id, context)` | `question_id` | →`cc_question` (CASCADE, wie LB) |
| `cc_collection` | `user_id`, `course_id`, `name varchar(80)`, `created_at` | – | `user_course (user_id, course_id)`, `course_id` | `course_id`→post (LB: CASCADE auf Kurs → Hook) |
| `cc_collection_item` | `collection_id`, `question_id`, `created_at` | **PK `(collection_id, question_id)`** | `question_id` | →`cc_collection` (CASCADE), →`cc_question` (CASCADE) |

### 4.4 Zugang und Gruppen

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK / Löschregel |
|---|---|---|---|---|
| `cc_grant` | UID, `user_id bigint NULL` (NULL nur in `pending_claim`), `course_id`, `scopes varchar(64) 'all'` (E-27: `view,download,exam`), `status varchar(24) 'pending_confirmation'` (35.2: `pending_claim/pending_confirmation/active/suspended/expired/revoked/failed`), **Quellschlüssel:** `source_type varchar(32) NOT NULL` (`manual/group_seat/woo_order_item/voucher/…`), `source_id varchar(191) NOT NULL`, `source_line varchar(64) NOT NULL DEFAULT ''` (Position/Seat), `source_revision int unsigned NOT NULL DEFAULT 1` (E-29), `source_plugin varchar(64) NULL` (31.2), `seat_id bigint NULL`, `valid_from`/`valid_until datetime NULL`, `revocation_reason varchar(64) NULL`, `granted_by bigint NULL`, `reason longtext NULL`, `meta longtext NULL` (minimal, z. B. `duration_days`, `renewal_mode` aus LB `meta`), CAS; TS | `UNIQUE uid`, **`UNIQUE grant_source (source_type, source_id, source_line, source_revision, course_id)`** = `grant_source_key` (Kap. 35.2/37; utf8mb4 ≈ 1 160 Byte < 3 072) | `user_access (user_id, course_id, status, valid_until)` (effektiver Zugang), `status_until (status, valid_until)` (Ablauf-Cron, LB), `course_status (course_id, status)`, `seat_id`; Lookup nach Quelle über das linke Präfix des Unique | `seat_id`→`cc_seat` (SET NULL); `user_id`→user (Kontolöschung nach INV-C-158 quellenneutral) |
| `cc_grant_event` | `grant_id`, `event_type varchar(32)`, `from_status`/`to_status varchar(24) NULL`, `reason_code varchar(64)`, `actor_type varchar(16)` (`user/system/connector`), `actor_id bigint NULL`, `source_event_id varchar(191) NULL`, `correlation_id char(36) NULL`, `details longtext NULL`, `occurred_at datetime`, `created_at` | `UNIQUE grant_source_event (grant_id, source_event_id)` (NULL erlaubt für interne Ereignisse) | `grant_created (grant_id, created_at)`, `correlation_id` | →`cc_grant` (CASCADE; Grants werden nur über die Retention gelöscht) |
| `cc_access_rule` | `course_id`, `module_id bigint NULL`, `scope varchar(32)`, `access_type varchar(16)` (`free/free_limited/drip`), `limit_type varchar(32) NULL`, `limit_value int NULL`, `valid_from`/`valid_until datetime NULL`; TS | – | `course_scope (course_id, scope)` | →post |
| `cc_group` | UID, `owner_user_id bigint`, `name varchar(190)`, `seats int unsigned 0`, `status varchar(16) 'active'`, `valid_from`/`valid_until datetime NULL`, `source_type varchar(32) NOT NULL DEFAULT 'manual'`, `source_id varchar(191) NULL` (NULL = manuell, mehrfach erlaubt), `join_token_hash char(64) NULL`, `reason longtext NULL`; TS | `UNIQUE uid`, `UNIQUE group_source (source_type, source_id)` (LB `order_item` unique), `UNIQUE join_token_hash` | `owner (owner_user_id)`, `status` | – |
| `cc_group_course` | `group_id`, `course_id`, `scopes varchar(64) 'all'` | `UNIQUE group_course (group_id, course_id)` | `course_id` | →`cc_group` (CASCADE) |
| `cc_seat` | `group_id`, `user_id bigint NULL`, `invite_email varchar(190) NULL` (nur Kommunikation), `status varchar(16) 'invited'` (`invited/active/removed/expired`), `claim_token_hash char(64) NULL`, `claim_expires_at datetime NULL`, `invited_at`/`claimed_at`/`removed_at datetime NULL`; TS | `UNIQUE claim_token_hash` | `group_status (group_id, status)`, `status_invited (status, invited_at)` (Ablauf 30 Tage, LB), `user_id`, `invite_email` | →`cc_group` (CASCADE, wie LB) |

Plätze werden unter `NamedLock group-seats:<group_id>` vergeben (LB, Inventur B §5.4). Eine DB-Unique-Grenze „ein aktiver Platz je Nutzer und Gruppe“ gibt es in LB nicht; ein Re-Invite nach `removed` muss möglich bleiben. Diese Regel bleibt deshalb anwendungsseitig unter dem Lock (Test in R1-42).

### 4.5 Nachweise

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK / Löschregel |
|---|---|---|---|---|
| `cc_certificate` | `certificate_uid char(36)` (stabil über Revisionen), `revision_no smallint unsigned 1`, `user_id`, `course_id`, `course_revision_id`, `evidence_type varchar(32)` (`exam_session/course_completion`), `evidence_id bigint`, `status varchar(16)` (`issued/corrected/revoked`), `supersedes_id bigint NULL`, `template_version varchar(32)`, `snapshot longtext` (gedruckte Angaben), `snapshot_hash char(71)`, `verify_token_hash char(64)`, `issued_at datetime`, `issued_by bigint NULL`, `revoked_at datetime NULL`, `revoked_by bigint NULL`, `revoke_reason longtext NULL`, `created_at` | `UNIQUE cert_rev (certificate_uid, revision_no)`, `UNIQUE evidence_rev (evidence_type, evidence_id, revision_no)` (Ausstellung idempotent), `UNIQUE verify_token_hash` | `user_course (user_id, course_id)` | `course_revision_id`→`cc_course_revision` (RESTRICT), `supersedes_id`→`cc_certificate` (RESTRICT) |

### 4.6 Integration

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK |
|---|---|---|---|---|
| `cc_inbox_event` | `connector_id varchar(64)`, `event_id varchar(191)`, `contract_version varchar(16)`, `event_type varchar(64)`, `correlation_id char(36) NULL`, `payload longtext` (minimal, ohne Secrets), `payload_hash char(64)`, `status varchar(16)` (`received/processing/done/ignored/failed/quarantined`), `attempts smallint unsigned 0`, `next_attempt_at`/`locked_until datetime NULL`, `lock_owner varchar(64) NULL`, `last_error text NULL`, `occurred_at`/`received_at datetime`, `processed_at datetime NULL` | `UNIQUE connector_event (connector_id, event_id)` | `status_next (status, next_attempt_at)`, `correlation_id` | – |
| `cc_outbox_event` | `event_uuid char(36)`, `event_type varchar(64)`, `schema_version varchar(16)`, `aggregate_type varchar(32)`, `aggregate_id bigint`, `correlation_id char(36) NULL`, `payload longtext`, `status varchar(16)` (`pending/dispatching/delivered/failed/dead`), `attempts smallint unsigned 0`, `next_attempt_at`/`locked_until datetime NULL`, `lock_owner varchar(64) NULL`, `last_error text NULL`, `created_at`, `delivered_at datetime NULL` | `UNIQUE event_uuid` | `status_next (status, next_attempt_at)`, `aggregate (aggregate_type, aggregate_id)` | – |

Zustellung an mehrere Empfänger (eine Zeile je Abonnent) entscheidet ADR-006/R0-19. Bis dahin gilt eine Zeile je Ereignis.

### 4.7 Betrieb

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK |
|---|---|---|---|---|
| `cc_import_batch` | `package_id varchar(64)`, `schema_version varchar(16)`, `source_revision varchar(64) NULL` (LB `source_commit char(40)`), `status varchar(20) 'uploaded'`, `manifest longtext`, `stats longtext NULL`, `storage_path varchar(255) NULL`, `error longtext NULL`, `heartbeat_at datetime NULL` (LB: nur in `stats`; als Spalte für die Stale-Abfrage), `created_by`/`approved_by bigint NULL`, `created_at`, `approved_at`/`published_at datetime NULL` | – | `package_id`, `status` | – |
| `cc_import_item_log` | `batch_id`, `entity varchar(32)`, `source_id varchar(191) NULL`, `action varchar(16)`, `message longtext NULL`, `created_at` | – | `batch_entity (batch_id, entity)`, `action`, `action_created (action, created_at)`, `created_at` (alle LB) | →`cc_import_batch` (CASCADE) |
| `cc_audit_log` | `actor_user_id bigint NULL`, `action varchar(64)`, `object_type varchar(32) NULL`, `object_id varchar(191) NULL`, `reason longtext NULL`, `context longtext NULL`, `created_at` | – | `actor (actor_user_id)`, `object (object_type, object_id(64))`, `action`, `created_at` (alle LB) | – (append-only) |
| `cc_override` | `object_type varchar(32)`, `object_id bigint`, `field varchar(32)`, `value varchar(255) NULL`, `reason longtext`, `user_id bigint NULL`, `created_at` | `UNIQUE object_field (object_type, object_id, field)` | – | – |
| `cc_rate_limit` | `bucket_hash char(64)` **PK**, `window_start int unsigned`, `hits int unsigned 0`, `expires_at datetime` | PK | `expires_at` | – |
| `cc_request_idempotency` *(bedingt, E-51)* | `user_id`, `scope varchar(64)`, `idem_key char(36)`, `request_hash char(64)`, `status_code smallint unsigned 0`, `response longtext NULL`, `resource varchar(64) NULL`, `created_at`, `expires_at datetime` | `UNIQUE user_scope_key (user_id, scope, idem_key)` | `expires_at` | – |

### 4.8 Transaktionsgrenzen (Auszug für R0-DATA-002)

| Operation | Grenze | Schutz | Quelle LB |
|---|---|---|---|
| Lernantwort speichern | 1 TX: `cc_attempt` + `cc_attempt_answer` + `cc_question_progress` (`FOR UPDATE`) | Unique `user_idem`; Rate-Limit `answer\|<id>` | `LearningService.php:214-268, :243, :498` |
| Prüfung starten | `NamedLock exam_start:<user>` + 1 TX: Session + alle Session-Fragen + Snapshot | Unique `start_token`; laufende Sitzung derselben Form wird zurückgegeben | `ExamService.php:183-194, :277` |
| Antwort/Flag/Abgabe | 1 TX je Aktion, `FOR UPDATE` auf Session, CAS `row_version` | Status `running`/`in_progress` als Vorbedingung | `ExamService.php:456, :490, :614` |
| Grant-Übergang | 1 TX: `cc_grant` (CAS) + `cc_grant_event` + `cc_outbox_event` | Unique `grant_source`, `grant_source_event` | neu (LB: `suppress_errors`-Insert, `EntitlementService.php:107-112`) |
| Platz beanspruchen | `NamedLock group-seats:<id>` + 1 TX: Seat + Grant | Unique `claim_token_hash`; Token-Ablauf | `GroupService.php:236, :346` |
| Zertifikat ausstellen | 1 TX: Certificate + Outbox | Unique `evidence_rev` | neu |
| Inbox verarbeiten | Claim per `UPDATE … SET locked_until/lock_owner WHERE status IN(...) AND (locked_until IS NULL OR locked_until < now)`, dann Fach-TX | Unique `connector_event` | neu |
| Import-Schritt | 1 TX je Schritt; Mediendateien außerhalb (Prüfsumme beim Wiederholen) | Options-Lock + Heartbeat | `ImportService.php:398` (T199/T203) |

### 4.9 Integrität ohne harte FKs auf WP-Tabellen

- `before_delete_post` (nur endgültiges Löschen) für `cc_course/cc_module/cc_lesson` **verweigert** das Löschen, wenn Lerndaten, Grants, Kursrevisionen oder Zertifikate verweisen. Erlaubt sind dann nur Papierkorb oder Archiv. Das entspricht RESTRICT; LB löscht Content nie hart (INV-C-108).
- `delete_user`/`deleted_user` (+ `wpmu_delete_user`): Eraser-Pfad aus LB (INV-C-148/152). Lerndaten werden in einer TX gelöscht, Grants nach quellenneutraler Regel (INV-C-158) geschlossen bzw. für die Retention pseudonymisiert (E-44).
- Health/Monitor: Orphan-Check je `→post`/`→user`-Spalte plus FK-Status (LB `Monitor` `foreign_keys`, INV-C-055).
- **Personendaten-Registry** (LB `USER_DATA_TABLES`, `Schema.php:919`): `cc_attempt`, `cc_question_progress`, `cc_activity_progress`, `cc_course_progress`, `cc_favorite`, `cc_collection`, `cc_exam_session`, `cc_grant`, `cc_seat`, `cc_certificate`, `cc_request_idempotency`; Kindtabellen `cc_attempt_answer`, `cc_exam_session_question`, `cc_collection_item`, `cc_grant_event`; mit Personenbezug im Kontext `cc_audit_log`, `cc_group` (Owner). Export/Eraser-Regeln je Tabelle folgen in R0-17/R0-31.

---

## 5. Revisionsmodell (ADR-003)

| Objekt | Mechanik | Unveränderlich ab | Referenziert von | Quelle |
|---|---|---|---|---|
| **Frage** | `cc_question` (Identität) + `cc_question_revision` (Inhalt). Ein neuer `content_hash` erzeugt eine neue Revision, die alte wird `deprecated`. Ein bekannter Hash reaktiviert die alte Revision (Import-Rollback). Redaktionelle Änderung einer veröffentlichten Revision = **neue Revision**. `current_revision_id` zeigt auf die auslieferbare Revision. | `editorial_status = published` (danach ist nur der Wechsel nach `deprecated` erlaubt) | `cc_attempt`, `cc_exam_session_question`, `cc_question_tag`, `cc_answer`, `cc_content_media` | INV-C-111, `EntityImporter.php:215-286` |
| **Antwortoptionen, Tags, Medienbezug** | hängen an der Revision, werden nie in place geändert | mit der Revision | Session-Fragen (`answer_order`, `given_answer` = Answer-IDs) | LB `answer.revision_id` |
| **Prüfungsregel** | `cc_exam_rule` mit `version`; Änderung nach Veröffentlichung = neue Version, alte wird `retired` | `status = published` | `cc_exam_session.rule_id` (RESTRICT) | LB `course_version` unique |
| **Kurs** | Editieren im CPT (WP-Revisionen nur als Editor-Komfort). **Veröffentlichen** schreibt `cc_course_revision` mit `structure` (Module, Lektionen inkl. WP-Revisions-ID und Inhalts-Hash, Regel-IDs, Zugangsregeln) und `structure_hash`. | Einfügen (nur `status` ändert sich danach) | `cc_exam_session`, `cc_course_progress`, `cc_certificate` | neu (LB hat kein Pendant) |
| **Prüfungsversuch-Snapshot** | Beim Start werden geschrieben: `snapshot` (Regel inkl. Blueprints, Zeitlimit, Bestehensregel, Feedback-Regel, Seed), alle `cc_exam_session_question` (Position, `question_id`, `revision_id`, `answer_order`) und `snapshot_hash`. Bewertung nur gegen Snapshot und Revisionen, nie gegen den Live-Stand. | Start (Struktur); Abgabe/Auswertung (Antworten, Ergebnis) | Zertifikat (`evidence_id`) | LB `rule_snapshot` (`ExamService.php:263-276`) + `exam_session_question.revision_id` |
| **Zertifikat** | eine Zeile je Revision; `corrected` = neue Zeile mit `revision_no+1`, `supersedes_id` und neuem Snapshot (die alte Zeile wird `corrected`). `revoked` setzt Status, Akteur, Grund und Zeit (35.4). `snapshot` hält die ausgegebenen Angaben (Name wie gedruckt, Kurstitel, Datum, Ergebnis), damit spätere Profil-/Kursänderungen den Nachweis nicht ändern. | Ausstellung | Verifikation (Token-Hash) | neu |
| **Smarttrainer-Lernstand** | `algorithm_version` in `cc_question_progress` und `cc_attempt`. Eine neue Version wirkt nur auf neue Antworten; es gibt keine stille Neubewertung (ADR-005). | – | – | Golden Master R0-05 |

**Schutz der Unveränderlichkeit:** Repository-Ebene (keine Update-Methoden für eingefrorene Spalten) plus Tests (Golden Master: Autorenänderung nach Start ändert kein Ergebnis, TODO „Unveränderliche Prüfungs-Snapshots nachweisen“). DB-Trigger sind verworfen, weil sie das `TRIGGER`-Recht brauchen und das auf Shared Hosting oft fehlt. `snapshot_hash` erlaubt eine nachträgliche Integritätsprüfung.

**Offen in R0-23:** ob eine neue Kursrevision laufende Lernende umstellt oder bei ihrer Revision belässt (Grandfathering), und wie Fortschritt bei gelöschten oder umsortierten Lektionen gezählt wird. Das Datenmodell trägt beides über `course_revision_id` in `cc_course_progress`.

---

## 6. Migrationsregeln (Vorgabe für R0-37, R1-02, R1-40)

### 6.1 Schema-Versionierung

- `cc_db_version` = Kern-Schemaversion (semver) **plus** Hash der Moduldefinitionen. So machen Pro-/Adaptertabellen den Stand nachvollziehbar (Pass-Muster: `wpcap_db_version`, `Installer.php:682-689`, R0-36 §2.2).
- `cc_schema_fingerprint` (billig, autoload) = Hash aus Plugin-Version und Moduldefinitionen; nur für den Frontend-Vergleich.
- `cc_migrations_applied` = Liste der abgeschlossenen nummerierten Schritte; `cc_backfill_state` = Cursor je Backfill.
- Ist das Schema neuer als der Code, gibt es keine Aktion, nur einen Admin-Hinweis und einen CLI-Fehler (LB `schemaIsNewer`, INV-C-029; Downgrade ohne Restore wird nicht unterstützt).

### 6.2 `dbDelta` vs. eigene Migrationen

| Art der Änderung | Werkzeug | Regel |
|---|---|---|
| Tabelle/Spalte/Index **hinzufügen**, Typ verbreitern | `dbDelta` (deklarativ, LB `Migrator::migrate`) | danach **Drift-Check** gegen `information_schema` inkl. Index-Spaltenfolge (INV-C-033). Fehlt etwas, folgt `RuntimeException` ohne Versionswechsel. |
| Fremdschlüssel | eigener idempotenter Schritt nach `dbDelta` (INV-C-036, Name ≤ 64 Zeichen, sonst md5) | Ein Fehlschlag ist nicht fatal und wird in `cc_fk_issues` sowie Health gemeldet. |
| Umbenennen, Löschen, Typ verengen, Unique **nachträglich** hinzufügen, Datenumzug | nummerierte PHP-Schritte `NNNN_<name>` | idempotent (prüft Ist-Zustand), protokolliert in `cc_migrations_applied`. Bei Unique erst Duplikate bereinigen bzw. melden (Dry-run), dann `ALTER`. |
| Daten-Backfill | Backfill-Job | siehe 6.3 |

`dbDelta` kann keine Spalten löschen oder umbenennen und keine FKs anlegen. Deshalb gilt **Expand/Contract:** neue Spalten sind `NULL`-fähig oder haben einen Default. Der Code einer Version N muss das Schema von N+1 lesen können. Entfernt wird erst in einem späteren Release (frühestens N+2), nachdem der Backfill abgeschlossen und gemeldet ist.

### 6.3 Backfill

- Batches (Startwert 500 Zeilen, wie LB CLI `runAll`; Admin/Cron 200) mit Cursor auf `id` in `cc_backfill_state`. Idempotent (`WHERE neue_spalte IS NULL` bzw. Hash-Vergleich). Wiederaufnahme nach Abbruch; Heartbeat; ein Lauf gleichzeitig (Options-Lock mit TTL, LB `Lock`).
- Die **Schemaversion** wird gesetzt, sobald die Struktur steht. Der **Backfill-Status** ist getrennt. Funktionen, die neue Daten brauchen, prüfen `cc_backfill_state` und laufen bis dahin auf dem alten Pfad (Pass deckt nicht-additive Backfills nicht ab; R0-36 §2.5).
- Kandidaten: `cc_course_progress` (aus Detailtabellen neu aufbaubar), spätere Grant-Revisionen, `algorithm_version`-Einführung (nur Kennzeichnung, keine Neubewertung).
- Dry-run-Modus für Backfills mit Zählbericht (WP-CLI `wp course-captain migrate --dry-run`; der Name steht unter R0-19-Vorbehalt).

### 6.4 Trigger und Sperren (Pass-Captain-Muster, R0-36 §2.2/2.5, plus LB)

1. **Admin (`admin_init`), WP-Cron und WP-CLI** vergleichen `cc_db_version` voll und migrieren.
2. **Frontend/REST** vergleicht nur `cc_schema_fingerprint`. Bei Abweichung wird **einmalig** ein Cron-Ereignis `cc_run_upgrade` geplant. Nur wenn es > 300 s überfällig ist, führt **ein** Frontend-Request das Upgrade aus (Pass `OVERDUE_AFTER`).
3. **Nicht wartendes `GET_LOCK(name, 0)` je Site.** Name = Hash aus `DB_NAME | $wpdb->prefix | 'schema-migration'` (LB `NamedLock::dbName`, Pass `:771-775`). Wer die Sperre nicht bekommt, überspringt und arbeitet mit dem alten Schema weiter. Rückfall auf eine Options-Sperre mit TTL 1 800 s und Übernahme toter Sperren (LB `Lock`, T202), falls `GET_LOCK` nicht verfügbar ist (`NULL`).
4. **Doppelprüfung im Lock:** Version direkt aus der DB am Options-Cache vorbei lesen (Pass `stored_db_version()`).
5. Während einer fälligen Migration bekommen schreibende Anfragen an `course-captain/v1` 503 mit `Retry-After: 30` (LB `Maintenance`, INV-C-054).
6. Fehlschlag: `cc_migration_failed {at, message}`, Backoff 15 min, Admin-Hinweis mit CLI-Befehl, Health `migration=false` (LB `runMigration`, INV-C-019).
7. Aktivierung: direkt migrieren. Bei Fehler folgt `wp_die` **vor** dem Einplanen der Cron-Ereignisse (LB T146).

### 6.5 Rollback-Grenzen

- **Keine Rückmigration** von Schema N+1 auf N (LB und Pass ebenso). Ein Plugin-Downgrade auf älteren Code ist nur innerhalb des Expand-Fensters sicher (6.2).
- **Rollback = Restore von DB und Dateien als zusammengehöriger Stand**, keine selektive Zusammenführung mehrerer Sicherungen (Pass-Runbook, R0-36 §2.3; Restore-Check LB `BackupCheck`, INV-C-060…063). Details in R0-16.
- Content-Rollback ist **fachlich** und kein Schema-Rollback: Ein neuer Import aus der Paketkopie reaktiviert bekannte Fragen-Hashes (INV-C-097/111).
- Destruktive Contract-Schritte werden im Release-Changelog markiert und brauchen ein vorheriges Backup (Admin-Hinweis).

### 6.6 Multisite-Scope

- Tabellen **je Site** über `$wpdb->prefix`; Optionen, Locks, Cron und Fingerprint je Site. Nutzer-IDs gelten nur innerhalb der Site (Kap. 37), Grants sind sitegebunden (35.2).
- Aktivierung je Site. Netzwerkaktivierung blockiert wie LB (INV-C-058 „Netzwerk-Admin abgelehnt“) bis zur Entscheidung D-3. `wpmu_drop_tables` löscht abhängige Tabellen zuerst (INV-C-007). Health meldet verwaiste Tabellen gelöschter Sites (INV-C-008). Uninstall läuft je Site mit `switch_to_blog` (INV-C-024).
- Wird ein Konto netzweit gelöscht, läuft der Eraser in allen Sites mit Tabellen (INV-C-148/159).
- Ein Upgrade im Netzwerk läuft **pro Site lazy** (Trigger 6.4 je Site), nicht als Netzwerk-Schleife in einem Request. Für die CLI gibt es eine Schleife über `--url`/`wp site list` (R0-37).

---

## 7. Ausgeschlossene Quelltabellen

| LB-Tabelle (`Schema.php:Zeile`) | Grund | Neutraler Ersatz |
|---|---|---|
| `navigation_task` (:233), `navigation_task_course` (:246), `navigation_question` (:254), `navigation_progress` (:599) | Navigationstrainer, fachgebunden (Kap. 26.2; INV-C-040/044) | keiner in R1 (E-22: Toleranz-Fragetyp höchstens R2/Pro als `question_type` + `settings`) |
| `praxis_task` (:267), `praxis_progress` (:610) | Praxisaufgaben je Prüfungskategorie (INV-C-040/044; Spalten `exam_reference`, `category`) | **Mechanik** Karteikarte (E-21) als `question_type` in `cc_question_revision` + Lernstand in `cc_question_progress`; keine eigene Tabelle |
| `radio_scenario` (:340), `radio_text` (:362), `radio_vocabulary` (:381), `radio_progress` (:629) | Funktraining (INV-C-040/044) | Vokabel-Muster „3 × in Folge“ geht in den Karteikarten-Modus (E-21) |
| `device` (:397), `device_lesson` (:407), `device_step` (:421) | Gerätesimulation (INV-C-040) | keiner |
| `exam_committee` (:696), `exam_guide` (:720) | Prüfungsausschüsse und amtliche Prüfungsanmeldung (INV-C-040, Inventur A §2.6) | keiner |
| `product_access` (:680) | Woo-Produktzuordnung: kein Core (Konzept 47, INV-C-045) | Adaptertabelle in R3-02 |
| `mobile_session` (:772), `mobile_export` (:813), `mobile_web_code` (:830) | App-API nicht in R1 (E-05, E-C-04) | keiner; der Datenschutz-Export nutzt die WP-Core-Werkzeuge (INV-C-156) |

**Summe:** 19 von 55 Quelltabellen sind ausgeschlossen (15 fachlich, 1 R3, 3 App-API). `mobile_idempotency` und `mobile_rate_limit` werden neutral übernommen (E-50/E-51). Die übrigen 36 (einschließlich dieser beiden) werden übernommen, neutralisiert oder zusammengelegt (`module`+`video_module` → CPT `cc_module`; `video_lesson`+`video_lesson_step` → CPT `cc_lesson`).

**Ausgeschlossene Spalten in übernommenen Tabellen:** `entitlement.{order_id, order_item_id, product_id, variation_id, sku_snapshot, feature_code, idempotency_key}`, `learning_group.{order_id, order_item_id, product_id, scopes}`, `exam_rule.{verified, verified_by, verified_at}`, `question.{official_number, content_role, source_module}`, `question_revision.{source_catalog, source_page, source_question_id, topic, subtopic, competency, smart_category, scope}` (diese fünf Felder → `cc_question_tag`), Default `source_system='boatingschool'`.

---

## 8. Datenmengen-Annahmen für R0-14

Basis aus **Konzept 22.3** (Ziel, kein Messwert): 10 000 Lernende, 100 Kurse, 100 000 Fragen. **Alle abgeleiteten Werte sind Annahmen (A-xx)** mit offengelegter Formel; R0-14 bestätigt oder ersetzt sie und legt das Lastprofil fest. Belegte Grenzwerte aus LB stehen ausdrücklich dabei.

| ID | Größe | Annahme | Ergebnis (Zeilen) | Relevanz |
|---|---|---|---|---|
| A-01 | `cc_question` | 22.3 | 100 000 | Auslieferungs-SQL, Admin-Liste paginiert |
| A-02 | `cc_question_revision` | Ø 1,5 Revisionen je Frage | 150 000 | `publish`-Index |
| A-03 | `cc_answer` | Ø 4 je Revision | 600 000 | Batch-Load je 500 (INV-A-026) |
| A-04 | `cc_question_tag` | Ø 3 je Revision | 450 000 | Filter |
| A-05 | `cc_media` / `cc_content_media` | Ø 0,5 Bild je Revision | ≈ 75 000 | sha256-Dedupe |
| A-06 | CPT `cc_module` / `cc_lesson` | Ø 20 Module je Kurs, Ø 10 Lektionen je Modul | 2 000 / 20 000 (+ WP-Revisionen, begrenzen) | `wp_posts`-Wachstum |
| A-07 | `cc_grant` | Ø 2 Kurse je Lernende | 20 000 | effektiver Zugang je Anfrage |
| A-08 | `cc_grant_event` | Ø 3 Ereignisse je Grant | 60 000 | Historie |
| A-09 | `cc_attempt` | Ø 2 000 Lernantworten je Lernende | **20 Mio.** (größte Tabelle) | Index `user_question`, `user_created`; Retention (D-5) |
| A-10 | `cc_question_progress` | Ø 800 bearbeitete Fragen je Lernende | 8 Mio. | Fälligkeitsauswahl `user_due` |
| A-11 | `cc_exam_session` | Ø 15 Prüfungsversuche je Lernende | 150 000 | Ablauf-Cron `status_deadline` |
| A-12 | `cc_exam_session_question` | Ø 30 Fragen je Versuch | 4,5 Mio. | Start-TX schreibt 30 Zeilen |
| A-13 | `cc_activity_progress` | 20 000 Grants × 200 Lektionen × 50 % begonnen | 2 Mio. | „Mein Lernen“ |
| A-14 | `cc_course_progress` | = Grants | 20 000 | Dashboard ohne Scan |
| A-15 | gleichzeitige Prüfungen | **offen** – R0-14 legt fest (Vorschlag Lastprofil: 500 laufende Sitzungen) | – | Lock `exam_start`, Zeilensperren |
| A-16 | Antwortrate | Obergrenze LB: 60 Antworten je Nutzer und Minute (`LearningService.php:40`) | – | Rate-Limit-Tabelle |
| A-17 | Importpaket | LB-Grenzen: 200 MB, 20 000 Einträge je Paket (INV-C-085); 200/500 Datensätze je Schritt, 64 MB Medien je Schritt (Inventur C §5.2) | – | Batch-Runner R0-38 |
| A-18 | `cc_audit_log`, `cc_import_item_log`, Inbox/Outbox | **offen**; Retention: Audit 4 Jahre, Import-Log 14/180 Tage (E-44) | – | Prune-Jobs je Lauf begrenzt (LB 5 000 / 20 000) |

**Prüfaufträge an R0-14:** p95-Ziele aus 22.3 gegen A-09/A-10/A-12 messen (Smarttrainer-Auswahl, Prüfungsstart, „Mein Lernen“). Bewerten, ob `cc_attempt` eine Archivierung oder Partitionierung braucht; Partitionierung ist **nicht** vorgesehen, weil `dbDelta` damit nicht umgehen kann. Backup-/Restore-Dauer bei dieser Größenordnung messen (R0-16).

---

## 9. Alternativen (verworfen bzw. nachrangig)

| Alternative | Vorteil | Nachteil | Bewertung |
|---|---|---|---|
| **A1 Alles in Custom Tables** (LB-Weg, Kap. 9.2 „möglich“) | harte FKs überall, ein Persistenzmodell, LB-Code direkt portierbar | eigener Editor, Revisionen, Suche, SEO, REST, Rechte; widerspricht 9.2/37 „WP-native bevorzugen“ | verworfen für Kurs/Modul/Lektion |
| **A2 Alles als CPT + Meta** (inkl. Fragen, Versuche) | maximal WP-nativ | 100 000 Fragen und 20 Mio. Antworten in `wp_posts`/`wp_postmeta`, keine Uniques/CAS, Meta-Joins langsam, Revisionen nicht unveränderlich | verworfen |
| **A3 Hybrid ohne Kursrevisionstabelle** (WP-Revisionen als Nachweis) | weniger Tabellen | WP-Revisionen kürzbar und löschbar, nicht strukturiert, kein Hash | verworfen (ADR-003) |
| **A4 Grant-Idempotenz über `idempotency_key`-Spalte** (LB) | LB-nah | doppelte Wahrheit neben `reference_scope`; in LB nicht unique (Befund 1) | verworfen; der Quell-Unique ist die Idempotenz |
| **A5 Grant je Kurs × Scope als Zeilen** (LB je Feature) | feingranularer Widerruf | Mehrfachzeilen je Quelle, komplexere Unique-Regel | nachrangig; hängt an E-27 |
| **A6 Queue in Optionen** (LB `lbvc_pending`) | keine Tabelle | Autoload-/Konkurrenzprobleme, kein Index, widerspricht 9.2 | verworfen |
| **A7 DB-Trigger für Unveränderlichkeit** | DB-erzwungen | `TRIGGER`-Recht oft nicht vorhanden, `dbDelta`-fremd, Backup-Tools | verworfen |
| **FK-Optionen** | siehe D-1 | | |

---

## 10. Konsequenzen

**Positiv**
- Bewertungs- und Nachweisdaten sind revisionsfest und DB-seitig eindeutig (CORE-03/04/06/07/11 testbar über Uniques und Events).
- Kurse, Module und Lektionen bekommen Block-Editor, Suche, SEO und WP-REST ohne Eigenbau.
- Woo/Voucher bleiben vollständig außerhalb des Core-Schemas; R1 läuft standalone.
- Migration und Lock folgen erprobten Mustern (LB T135–T210, Pass `Installer.php`).

**Negativ / Aufwand**
- Integrität zu CPTs und Nutzern ist **anwendungsseitig**: Lösch-Hooks, Orphan-Check und Tests sind Pflicht (R1-02, R1-38).
- Zwei Persistenzwelten (Post-API und `$wpdb`-Repositories), dadurch zwei Cache-Invalidierungspfade (Content-Generation, INV-C-128).
- Veröffentlichen eines Kurses bedeutet zusätzlich einen Snapshot-Schritt (`cc_course_revision`); die Editor-UX muss „Entwurf ≠ veröffentlichte Revision“ erklären (R0-23, R1-04).
- 39 Tabellen (+1 bedingt) plus Expand/Contract-Disziplin; das Uninstall-Manifest muss alle Tabellen und Optionen vollständig führen (LB-Lücke INV-C-026).
- `cc_attempt` wächst unbegrenzt, solange D-5 offen ist.

**Folgearbeiten (nicht Teil dieses ADR):** exakte DDL-Dateien und Drift-Test (R1-02), Zustandsautomaten (R0-08, R0-09), Capability-Mapping je Tabelle (R0-07), Event-Envelope und Outbox-Zustellung (R0-19, ADR-006), Retention je Tabelle (R0-31), Import-Entitäten-Registry (R0-10, R0-38), Upgrade-Tests (R1-40), Race-Tests (R1-42).

---

## 11. Entscheidungsbedarf (nur echte, noch nicht in `ENTSCHEIDUNGEN.md` beschlossene Punkte)

| Nr. | Frage | Optionen | Empfehlung | Wer | Blockiert |
|---|---|---|---|---|---|
| **D-1** | **Harte Fremdschlüssel?** | **A** keine FKs (WordPress-üblich): einfach, keine Probleme mit Restore-Reihenfolge, Hosting-Tools oder `dbDelta`; dafür nur Anwendungsintegrität und stille Waisen möglich. **B** FKs nur zwischen `cc_`-Tabellen (LB-Muster, nicht fatal + Monitor): DB schützt Lern-/Prüfungshistorie (RESTRICT) und räumt Kindzeilen auf (CASCADE); Kosten: zusätzlicher Migrationsschritt, `FOREIGN_KEY_CHECKS=0` bei Uninstall/Restore, Lock-Wartezeiten bei Kaskaden. **C** zusätzlich FKs auf `wp_posts`/`wp_users`: bricht WP-Core-Löschpfade und Engine-Mischungen. | **B**: Die Liste in §4 umfasst 30 FKs (gegenüber 41 in LB). C ist ausgeschlossen. | Architektur | R1-02, R0-37 |
| **D-2** | **Hybrid-Speicherung freigeben** (ADR-001): Kurs/Modul/Lektion als CPT, Rest als Tabelle, Kursrevision als eigene Tabelle | Hybrid (§2) / A1 / A2 | **Hybrid** | Auftraggeber + Architektur | R0-08, R0-10, R1-04 |
| **D-3** | **Multisite-Umfang** konkret für das Schema: Netzwerkaktivierung blockieren (LB) oder erlauben (Tabellen je Site, Upgrade lazy je Site)? E-07 sagt nur „offiziell Single-Site, Aktivierung je Site korrekt“. | blockieren / erlauben ohne Support / offiziell | **Netzwerkaktivierung in R1 blockieren** (LB-Verhalten, getestet); Schema und Locks sind trotzdem je Site, sodass eine spätere Freigabe keine Migration braucht | Auftraggeber | R0-25, R0-37 |
| **D-4** | **Kursrevision und Fragenrevisionen:** Pinnt eine veröffentlichte Kursrevision auch die Fragenrevisionen, oder nur Struktur und Regeln (Fragenrevision erst beim Versuchsstart fixiert)? | nur Struktur / Struktur + Fragenrevisionen | **nur Struktur + Regeln**: Fragenkorrekturen wirken ohne Kurs-Neuveröffentlichung, Prüfungen bleiben über den Versuchssnapshot revisionsfest | Auftraggeber (Didaktik) + Architektur; Feinschnitt R0-23 | R0-23, R1-04 |
| **D-5** | **Aufbewahrung der Lernhistorie** (`cc_attempt`, `cc_question_progress`, `cc_exam_session*`): E-44 nennt Audit, Zugänge und Import-Log, **nicht** Lern- und Prüfungsdaten; 35.4 verbietet unbegrenzte Aufbewahrung als stillen Default | unbegrenzt bis Konto-/Datenschutzlöschung / Frist je Datentyp (z. B. Antwort-Log kürzer als Prüfungsergebnis) / Aggregation alter Antworten | **Frist je Datentyp als Setting.** Prüfungsergebnisse und Zertifikatsevidenz bleiben, solange ein Nachweis existiert. Für Roh-Antwortlogs eine Aggregation zu `cc_question_progress` nach Betreiberfrist. Werte mit der Rechtsberatung des Betreibers (R0-31). | Auftraggeber | R0-31, R0-14, R1-17 |
| **D-6** | **Datenmengen-Ziele** A-01…A-18 als Lastprofil für R0-14 bestätigen, insbesondere A-09 (20 Mio. Antworten) und A-15 (gleichzeitige Prüfungen) | bestätigen / andere Werte | **bestätigen** als Profil `default`, dazu ein `heavy`-Profil (×2,5) wie Pass-Lasttest | Auftraggeber | R0-14 |

**Bereits in `ENTSCHEIDUNGEN.md`, hier nur Abhängigkeit:** E-07 (Multisite), E-08 (InnoDB/JSON, Voraussetzung für Indexlängen in `grant_source`), E-27 (Scopes → Spalte `scopes` statt Zeilen je Feature), E-29 (`source_revision` im Unique), E-05 (bedingte Tabelle `cc_request_idempotency`, App-Tabellen ausgeschlossen), E-17 (manuelle Gruppen), E-21 (Karteikarte statt `praxis_*`), E-23 (Regelstatus statt `verified`), E-44 (Retention Audit/Grants/Import).

---

## 12. Nachweis

- Tabellenzahl: `grep -nE "^\s*'[a-z_]+' => \"" src/Infrastructure/Database/Schema.php` → 55 Treffer (`:55`…`:847`); Inventur C §3.1 hat 55 Tabellenzeilen.
- Fremdschlüssel: `Schema::foreignKeys()` `:871-916`, 41 Einträge (20 CASCADE, 20 RESTRICT, 1 SET NULL).
- `entitlement` (`:641-666`): `UNIQUE reference_scope`, `idempotency_key varchar(100) NULL` ohne Unique; `attempt` (`:490-507`): `UNIQUE idempotency_key`.
- Zertifikate in LB: `grep -rli "certificate\|zertifikat" --include=*.php` trifft nur TLS-/Signaturzertifikate (`Admin/SystemStatus.php:254`, `Mobile/MobileSettings.php:126`) und Inhaltsdaten, keine Zertifikatsfunktion.
- Fragenrevisionierung: `Content/Import/EntityImporter.php:241-264` (`deprecated` bei neuer Revision).
- Pass-Muster: `docs/r0/R0-36-suite-vertragsdeltas.md` §2.2, §2.5 (Quelle `wp-passmaster` @ `b2b88ea`, `src/Installer.php:674-790`).
- Keine Codeänderung an LearnBoating, keine Implementierung, kein Commit.

## Nachträge aus Folge-Tasks

| Datum | Quelle | Änderung am Entwurf |
|---|---|---|
| 16.11.2026 | R0-10 §1, §3, §4 | `cc_media.vector_source` entfällt; neues Feld `cc_media.access` (`public`/`protected`); `cc_import_batch.status` bekommt zusätzlich `activating`, `aborted` (gespeichert, nicht abgeleitet) und `superseded`. Offen für R0-23/R1-15: Wie bleiben Lektionen (CPT) bis zur Aktivierung auf dem alten Stand? |
| 16.11.2026 | R0-08 §8.2 | Δ-1 `cc_source_map.uid` (UNIQUE, für jedes CPT-Objekt); Δ-2 `cc_exam_session.mode` + `quiz`, Spalte `activity_uid`; Δ-3 Punkte als Tausendstel (`points_milli`); Δ-4 `evaluation_no`; Δ-5 `end_reason` und Statusliste `in_progress/submitted/evaluated/cancelled/broken`; Δ-6 `cc_activity_progress.reason_code`, `rule_version`; Δ-7 Lernziele als Kurs-Meta in der Kursrevision |
