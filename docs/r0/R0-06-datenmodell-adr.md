# R0-06 · ADR Datenmodell Course Captain

**ADR-Nr.:** R0-06 (deckt die Datenanteile von ADR-001 Inhaltsspeicherung, ADR-002 Lern-/Prüfungsdaten, ADR-003 Inhaltsrevisionen ab; Teile von ADR-006 Queue/Outbox)
**Status:** Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet. Es gibt keine Implementierung und keine DDL-Dateien; die exakte DDL entsteht in R1-02.
**Stand:** 16.11.2026
**Bezug:** TODO R0-06, R0-DATA-001, R0-DATA-002 (Teil), ADR-001…003/006, R1-02; Konzept Kap. 9.2, 22.3, 28.2, 28.3, 31.2, 35, 37, 46.2; `docs/r0/R0-02-inventar/A…C`; `docs/r0/R0-36-suite-vertragsdeltas.md` §2.2/2.5; `docs/r0/ENTSCHEIDUNGEN.md` (Beschlussprotokoll und „Von Claude entschieden“, insbesondere E-01, E-07, E-08, E-25/E-25a, E-26, E-27, E-29, E-38, E-44, E-47…E-52, E-168, E-207)
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77` (nur gelesen). Pfade sind relativ zu `bootspruefung-learning/`. Clean Room nach E-01: LB-Belege dokumentieren nur Struktur und Verhalten; „übernehmen“ heißt „Verhalten nachbauen“, nie Code oder DDL kopieren.

**Nachfolger, die auf diesem ADR aufbauen:** R0-07 (Objekt-Scope), R0-08 (Activity/Prüfung), R0-09 (Grant-Automat), R0-10 (Import), R0-14 (Volumen), R0-16 (Backup), R0-23 (Revisionen laufender Kurse), R0-37 (Schema-Upgrade), R1-02 (Storage).

**Lesehinweis:** In §3 bezeichnen `E-01…E-52` **Entitäten** dieses ADR. Beschlüsse aus `ENTSCHEIDUNGEN.md` werden im Text als „Beschluss E-xx“ oder in Klammern mit Bezug auf das Beschlussprotokoll zitiert.

## Beschlussstand

| Beschluss | Wirkung auf dieses ADR |
|---|---|
| E-01 Clean Room | LB-Tabellen in §1/§7 sind nur Befund; keine DDL, kein Code übernommen |
| E-207 Präfix `ccap_` | Tabellen `{$wpdb->prefix}ccap_*`, CPTs, Post-Status, Meta, Caps, Rollen und Cron-Hooks `ccap_*`; Optionen und Transients `course_captain_*` (R0-27 T27, R0-20 D-20-03). Fehlercodes bleiben `cc_*` (siehe Offene Punkte) |
| E-47 (= D-2) | Hybrid freigegeben: Inhalte als CPT, Massendaten/Transaktionen als Tabellen |
| E-48 (= D-1) | Harte FKs nur zwischen `ccap_`-Tabellen (Option B) |
| E-49 (= D-3) | Netzwerkaktivierung in R1 blockiert, Schema je Site |
| E-50 (= D-4) | Kursrevision fixiert nur Struktur und Regeln; Fragenrevisionen erst beim Versuchsstart |
| E-51, E-150, E-243 (= D-5) | Aufbewahrungs-Defaults je Datentyp (§8, §10) |
| E-52 (= D-6) | Lastannahmen A-01…A-18 bestätigt, A-15 = 500 gleichzeitige Prüfungen |
| E-27 Zugang je Modul | `ccap_grant.module_id bigint unsigned NOT NULL DEFAULT 0` (0 = ganzer Kurs) im Grant-Schlüssel; `ccap_group_course.module_id` ebenso; Scopes bleiben zusätzlich (R0-40 Zielpaar `(course, module|0, scopes)`) |
| E-25/E-25a Bundle | Kursbeziehung „enthält“ in R1 Free: neue Tabelle `ccap_course_relation`; ein Grant auf den Bundle-Kurs schaltet die enthaltenen Kurse über den effektiven Zugang frei. Mitgliederrabatt nur im Woo-Adapter (R3.1), kein Kernschema |
| E-26 abhängige Zusatz-Grants | `ccap_grant.depends_on_course_id` (0 = unabhängig), Regel in R0-09 §3.5 |
| E-29, E-117 | `source_revision` im Grant-Schlüssel, kein Wiederaufleben |
| E-38/E-182 | `ccap_outbox_event`/`ccap_outbox_delivery` bilden in Free nur die **interne** Outbox (Mails, Audit, Cache); öffentliche Events, Pull-API und Connector-Registry sind Pro |
| E-39/E-39b | Grant-Quelle `workflow` nur über den Course-Pro-Connector; im Kernschema nur ein weiterer `source_type`-Wert |
| E-31, E-33, E-34, E-53 | Gutschein-Saga (`ccap_source_saga`) und Woo-Produkt-Sync/-Mapping liegen im Pro-Schema (§4.10), nie im Kern |
| E-168 / R1-46 | Neue Tabellen für Text-/Dateiaufgaben: `ccap_assignment_submission`, `ccap_assignment_file`, `ccap_assignment_grade` (§4.4a) |
| E-65, E-78, E-79, E-122 | `ccap_media.access` (Standard `protected`), `ccap_course_staff`, `ccap_learner_lock`, `ccap_accommodation` (§4.10) |
| E-05, E-74, E-75, E-108, E-137 | `ccap_request_idempotency` ist feste Tabelle; `evaluation_no`, `end_reason` in `ccap_exam_session` |
| E-172…E-175 | Migrationszustand in `ccap_migration_step`, Contract nur nach Freigabe (§6, R0-37) |

Alle Nachträge aus Folge-Tasks (Tabelle am Ende) sind in §2–§11 eingearbeitet.

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
  4. Zugangsgranularität Kurs × fachlichem Feature-Code (`navigation_trainer`, `radio_training` …; Inventur B §1.3 Nr. 5) → Beschluss E-27: je Kurs **oder Modul** (`module_id`, 0 = ganzer Kurs) mit neutralen Scopes.
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
- Beschlüsse: E-07 Single-Site offiziell, E-08 InnoDB + JSON-Funktionen Pflicht, E-27 Zugang je Modul (`module_id` im Grant-Schlüssel, Scopes bleiben), E-29 kein Wiederaufleben, E-44 Aufbewahrungsfristen, E-47…E-52 (§11).

---

## 2. Entscheidung – Kurzfassung

1. **Hybrid (ADR-001, Beschluss E-47):** **5 CPTs** für redaktionelle, editorfähige Inhalte (`ccap_course`, `ccap_module`, `ccap_lesson`, `ccap_faq`, `ccap_tip`) plus WP-Mediathek. Alles, was Volumen, Eindeutigkeit, Konkurrenzschutz oder Unveränderlichkeit braucht, kommt in **52 eigene Kern-Tabellen** `{$wpdb->prefix}ccap_*` (40 aus §4.1–§4.7, 12 aus Beschlüssen und Folge-Tasks, §4.4a und §4.10). Das Pro-Add-on bringt eigene Tabellen mit (`ccap_source_saga`, Woo-Adapter-Mapping; §4.10).
2. **Bewertungsrelevante Inhalte sind tabellenbasiert und unveränderlich:** Fragen, Antworten, Prüfungsregeln und Kursrevisionen. WP-Post-Revisionen dienen nur dem Editieren, nie als Nachweisquelle (sie lassen sich kürzen und löschen).
3. **Fremdschlüssel nur zwischen `ccap_`-Tabellen** (Beschluss E-48), nie auf `wp_posts`/`wp_users`. Sie werden nach `dbDelta` idempotent angelegt. Schlägt das fehl, ist das nicht fatal, wird aber in Health/Monitor gemeldet (Verhalten wie LB INV-C-036/048). Beziehungen zu CPTs und Nutzern sichert die Anwendung (Lösch-Hooks plus Orphan-Check).
4. **Eindeutigkeit ist DB-erzwungen**, Idempotenzspalten sind `NOT NULL`. Der Grant-Quellschlüssel ist UNIQUE über `(source_type, source_id, source_line, source_revision, course_id, module_id)` mit `module_id NOT NULL DEFAULT 0` (Beschluss E-27; 0 = ganzer Kurs). `idempotency_key` aus LB entfällt, weil der Quellschlüssel selbst die Idempotenz trägt.
5. **Zustandswechsel mit Compare-and-swap** (`row_version`) und kurzen Transaktionen. Bei Grant-Übergängen schreiben Grant, Grant-Event und Outbox-Eintrag **in derselben Transaktion** (transaktionale Outbox).
6. **Migration:** `dbDelta` nur für additive Strukturänderungen, nummerierte idempotente PHP-Schritte für alles andere, Zustand je Schritt in `ccap_migration_step`. Lock- und Triggermodell nach Pass Captain (nicht wartendes `GET_LOCK` je Site plus Lease mit Fencing, Fingerprint plus einmaliges Cron-Ereignis `ccap_migrate`). Keine Rückmigration; Rollback heißt Restore von DB und Dateien zusammen (Details §6, R0-37).
7. **Zeit:** alle `*_at`-Spalten `datetime` in **UTC, von PHP gesetzt** (`gmdate`). Es gibt keine DB-Defaults `CURRENT_TIMESTAMP` (Befund 7).
8. **Engine/Charset:** `ENGINE=InnoDB` plus `$wpdb->get_charset_collate()` (utf8mb4). Indexlänge ≤ 3072 Byte setzt E-08 voraus (MySQL 8 / MariaDB 10.6+).

---

## 3. Entitätenliste und Mapping (R0-DATA-001)

**Legende:** T = eigene Tabelle, CPT = Custom Post Type + Post-Meta, Opt = Option/Settings, abgel. = abgeleitet (keine eigene Speicherung). Owner = Storage-Owner (Modul, das schreibt). Begründungskürzel: **V** = Volumen, **A** = Abfragen (Joins, Filter, Pagination), **TX** = Transaktion/Konkurrenz/Unique, **R** = Revision/Unveränderlichkeit, **E** = Editor/WP-Nativität (Block-Editor, Suche, REST, Rechte).

### 3.1 Inhalt

| # | Entität (fachneutral) | Quelle LB (`Schema.php:Zeile`) | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-01 | Kurs | `course` (:55) | **CPT `ccap_course`** + Meta | Core/Content | E: Block-Editor, Slug, Suche, SEO (R1-37), WP-REST, Capabilities. V: ≈100 Kurse (22.3). Code = `post_name` (LB: Slug = Code, INV-C-129). `course_type` (`full/combo/addon`) ist fachlich und entfällt; Bundles („enthält“) sind nach Beschluss E-25/E-25a **R1 Free** über `ccap_course_relation` (§4.1). Öffentliche URL nur für Kurse, Slug `kurs` (E-197). |
| E-02 | Kursrevision (veröffentlichter Stand) | – (neu; LB hat keine Kursrevision) | **T `ccap_course_revision`** | Core/Content | R: unveränderlicher Stand, auf den Prüfungen, Fortschritt und Zertifikate verweisen (ADR-003, 35.4). WP-Revisionen sind dafür ungeeignet (kürzbar, löschbar, `wp_revisions_to_keep`). |
| E-03 | Modul / Kapitel | `module` (:69), `video_module` (:290) | **CPT `ccap_module`** | Core/Content | E; V klein. LB trennt Fragenmodul und Videokapitel. Neutral gibt es **ein** Modul mit Lektionen **und** Fragenpool (INV-C-041). Modul-`version` aus LB geht in die Kursrevision. |
| E-04 | Kurs↔Modul (Reihenfolge, Pflicht, Regeln) | `course_module` (:81) | **T `ccap_course_module`** | Core/Content | TX/A: n:m mit Unique und Sortierung. Das lässt sich nicht als `post_parent` abbilden. |
| E-05 | Lektion / Activity | `video_lesson` (:302), `video_lesson_step` (:319) | **CPT `ccap_lesson`**; Modulzuordnung über `post_parent` + `menu_order` | Core/Content + Activity-Provider | E: Inhalt im Block-Editor; Schritte (`video_lesson_step`) werden Blöcke. LB: eine Lektion gehört genau einem Modul (`video_lesson.module_id`), also 1:n. Activity-Typ als Meta `_ccap_activity_type` (Video, Text, Karteikarten, Text-/Dateiaufgabe nach E-168 …; ADR-004/R0-08). |
| E-06 | Herkunft/Provenienz von CPT-Objekten | Makro SRC (`Schema.php:924`) | **T `ccap_source_map`** | Import | TX: `(source_system, source_id)` muss für den Import eindeutig sein. Post-Meta kann das nicht DB-seitig erzwingen. Für Tabellenentitäten liegt SRC als Spalten vor. |
| E-07 | Frage | `question` (:91) | **T `ccap_question`** | Core/Fragenbank | V: 100 000 (22.3). A: Auslieferungs-SQL mit Joins (INV-C-130). TX: Unique Quelle. CPT würde `wp_posts` aufblähen, und Meta-Joins wären langsam. `official_number`/`content_role` werden zu neutraler `reference_no`. |
| E-08 | Fragenrevision | `question_revision` (:105) | **T `ccap_question_revision`** | Core/Fragenbank | R: Versuche verweisen auf `revision_id` (RESTRICT). Hash-basierte Revisionierung (INV-C-111). Fachfelder `source_catalog`, `source_page`, `source_question_id` entfallen. |
| E-09 | Antwortoption | `answer` (:136) | **T `ccap_answer`** | Core/Fragenbank | R: gehört an die Revision. V: ≈4 je Revision. |
| E-10 | Themen-/Kompetenz-Tags | Spalten `topic`, `subtopic`, `competency`, `smart_category`, `scope` in `question_revision` | **T `ccap_question_tag`** | Core/Fragenbank | A: Filter, Smarttrainer-Kategorie, Kompetenz. Feste Spalten sind fachlich geprägt; Konzept 9.2 nennt `ccap_question_tag`. WP-Taxonomien gehen nicht, weil Fragen keine Posts sind. |
| E-11 | Modul↔Frage (Pool) | `module_question` (:145) | **T `ccap_module_question`** | Core/Fragenbank | V/A: Fragenpool je Modul mit Unique. |
| E-12 | Activity↔Frage (Inline-Quiz) | `video_lesson_question` (:332) | **T `ccap_activity_question`** | Activity-Provider | A/TX: Unique je Lektion; FK auf Frage RESTRICT (wie LB). |
| E-13 | Medium | `media` (:154) | **T `ccap_media`** (optional verknüpft mit WP-Anhang über `attachment_id`) | Content/Medien | V/A: Import-Medien mit sha256-Dedupe, Rechte- und Provenienzstatus, Ablage auch außerhalb der Mediathek (INV-C-122/123). Editor-Uploads bleiben WP-Anhänge und bekommen einen `ccap_media`-Datensatz. |
| E-14 | Medienzuordnung | `content_media` (:177) | **T `ccap_content_media`** | Content/Medien | A: polymorph (Revision, Lektion), mit Overrides. |
| E-15 | FAQ / Kurs-FAQ | CPT `bp_faq`, `bp_customer_faq` (Inventur A INV-A-138) | **CPT `ccap_faq`** (ein Typ mit Sichtbarkeit „öffentlich/Teilnehmende“) | Modul FAQ | E: redaktionell, WP-nativ; E-16 (Mechanik R1). |
| E-16 | Lerntipp | CPT `bp_tip` (Inventur D Menü 13) | **CPT `ccap_tip`** | Modul Tipps | E; E-16. |

### 3.2 Prüfung

| # | Entität | Quelle LB | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-17 | Prüfungsregel (AssessmentBlueprint, versioniert) | `exam_rule` (:189) | **T `ccap_exam_rule`** | Prüfung | R/TX: Unique `(course_id, version)`; Sitzungen verweisen darauf. `verified*` entfällt (E-23), dafür Status `draft/published/retired`. Bestehensregel ist Pflicht, kein hartcodiertes 0.75 (INV-C-132). |
| E-18 | Blueprint-Anteil je Modul | `exam_blueprint` (:205) | **T `ccap_exam_blueprint`** | Prüfung | TX: Unique `(rule_id, module_id)`. |
| E-19 | Feste Prüfungsvariante | `exam_paper` (:214) | **T `ccap_exam_variant`** | Prüfung | Neutral für „Bogen“ (INV-C-133). |
| E-20 | Fragen einer Variante | `exam_paper_question` (:225) | **T `ccap_exam_variant_question`** | Prüfung | Unique Position. |
| E-21 | Prüfungsversuch inkl. Snapshot (Attempt + AttemptSnapshot) | `exam_session` (:559) | **T `ccap_exam_session`** | Prüfung | TX: `start_token` unique, Zeilensperre, CAS. R: `snapshot` + `snapshot_hash` unveränderlich (28.3). |
| E-22 | Snapshot-Frage im Versuch (Answer) | `exam_session_question` (:582) | **T `ccap_exam_session_question`** | Prüfung | R: fixiert `revision_id`, `answer_order`. V: ≈4,5 Mio. (§7). |

### 3.3 Lernen und Fortschritt

| # | Entität | Quelle LB | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-23 | Lernantwort (Attempt-Log) | `attempt` (:490) | **T `ccap_attempt`** | Lernen | V: größte Tabelle (§7). TX: Idempotenz-Unique. |
| E-24 | Gewählte Optionen | `attempt_answer` (:508) | **T `ccap_attempt_answer`** | Lernen | Kindzeilen CASCADE. |
| E-25 | Lernstand je Frage (MasteryState) | `progress` (:518) | **T `ccap_question_progress`** | Lernen/Smarttrainer | TX: `SELECT … FOR UPDATE` (`LearningService.php:498`); Unique `(user_id, question_id)`; `algorithm_version` (ADR-005). Trägt auch die Karteikarten-Modi (E-21, `praxis_progress` entfällt). |
| E-26 | Activity-Fortschritt (LearningProgress/Completion) | `video_progress` (:619) | **T `ccap_activity_progress`** | Activity-Provider | V/TX: Unique `(user_id, activity_id)`. Verallgemeinert Video auf alle Activity-Typen. |
| E-27 | Kursfortschritt (aggregiert) | – (neu; Konzept 9.2 `ccap_course_progress`) | **T `ccap_course_progress`** (materialisiert, aus E-23/E-25/E-26 neu aufbaubar) | Lernen | A: „Mein Lernen“ und Reports ohne Volltabellenscan (22.3). Die Detailtabellen bleiben die Wahrheit. |
| E-28 | Kompetenznachweis (CompetencyEvidence) | – (neu) | **abgel.**: bestandene `ccap_exam_session` + `ccap_certificate` + `competency_validity_days` (Beschluss E-73). Eine eigene Tabelle gibt es erst mit manuellen oder externen Nachweisen. | Prüfung | Nichts ohne Beleg erfinden. Die drei Dimensionen bleiben trotzdem getrennt (28.3). |
| E-29 | Favorit | `favorite` (:535) | **T `ccap_favorite`** | Lernen | Unique, INSERT IGNORE (Inventur A §3.1). |
| E-30 | Sammlung | `favorite_collection` (:544) | **T `ccap_collection`** | Lernen | 30 je Kurs (`CollectionService.php:19`). |
| E-31 | Sammlungseintrag | `favorite_collection_item` (:553) | **T `ccap_collection_item`** | Lernen | zusammengesetzter PK (wie LB). |

### 3.4 Zugang, Gruppen, Nachweise

| # | Entität | Quelle LB | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-32 | Grant (Zugangsgrund) | `entitlement` (:641) | **T `ccap_grant`** | Core/Access (System of Record, 31.2) | TX: Unique-Quellschlüssel, CAS, Ablauf-Cron. Woo-Spalten entfallen und kommen in den R3-Adapter (Befund 2). Statt `feature_code` gibt es `module_id` (0 = ganzer Kurs) plus `scopes` (Beschluss E-27). Abhängige Zusatz-Grants über `depends_on_course_id` (E-26). |
| E-33 | Grant-Ereignis (Historie) | – (LB: `meta.manual`/`extended` als JSON in `entitlement.meta` + `audit_log`) | **T `ccap_grant_event`** | Core/Access | R/TX: append-only Übergangsprotokoll, Dedupe externer Events (35.2 „verspätete Ereignisse protokolliert“). |
| E-34 | Zugangsregel (Teilumfang, Drip) | `access_rule` (:667) | **T `ccap_access_rule`** | Core/Access | A: wird je Anfrage komplett gelesen (`RequestMemo access_rules`, Inventur A §3.4); kursübergreifend abgefragt (`ExpiryReminder::freeCourses`). `course_code`/`feature_code` → `course_id`/`module_id`/`scope`. |
| E-35 | Gruppe | `learning_group` (:733) | **T `ccap_group`** | Core/Gruppen | TX: Plätze unter `NamedLock group-seats:<id>` (Inventur B §5.4). Bestellspalten → neutrale Quelle. Manuelle Anlage auch ohne Kauf (fehlt in LB, Beschluss E-17). |
| E-36 | Gruppe↔Kurs | `learning_group.scopes` (JSON `[{course, feature}]`) | **T `ccap_group_course`** | Core/Gruppen | A: „Gruppen eines Kurses“ für die Trainer-Objektrechte (Kap. 36) ohne JSON-Scan; je Modul möglich (E-27). |
| E-37 | Platz / Claim (Seat, Claim) | `learning_group_member` (:753) | **T `ccap_seat`** | Core/Gruppen | TX: einmaliger Claim; Token **nur als Hash** (Befund 3, 35.1). E-Mail ist nur Kommunikation. |
| E-38 | Käuferreferenz (BuyerReference) | `learning_group.owner_user_id`, `order_*` | **Spalten** in `ccap_group` (`owner_user_id`, `source_type`, `source_id`) und `ccap_grant` (`source_*`) | Core/Access | Keine Vollkopie fremder Bestelldaten (Kap. 37 „Dateneigentum“). Woo bleibt Owner. |
| E-39 | Zertifikat inkl. Revision und Verifikationstoken | – (neu; LB hat keine Zertifikatsfunktion, `grep -i certificate` trifft nur TLS/Signatur) | **T `ccap_certificate`** (eine Zeile je Zertifikatsrevision) | Nachweise | R: `corrected` erzeugt eine neue Revision, `revoked` setzt den Status. TX: Unique je Evidenz und Revision; Token-Hash unique (35.4). |
| E-40 | Woo-Produktzuordnung | `product_access` (:680) | **nicht im Core**; eigene Adaptertabelle im Pro-Schema (Owner Woo-Adapter, Name in R3-02); Ziele `(course, module|0, scopes)`, automatische Produktanlage und -pflege (Beschluss E-34, R0-40 §4.5) | Woo-Adapter (Pro, R3.1, E-53) | Konzept 47: kein `wc_item` im Free-Kern (INV-C-045). |

### 3.5 Integration, Betrieb, Datenschutz

| # | Entität | Quelle LB | Ziel | Owner | Begründung |
|---|---|---|---|---|---|
| E-41 | Eingehendes Ereignis (InboxEvent) | – (LB: Transients/Order-Meta für Dispute-Dedupe, Inventur B §5.3) | **T `ccap_inbox_event`** | Integration | TX: Unique `(connector_id, event_id)` (Kap. 37). Retry/Quarantäne (35.2). |
| E-42 | Ausgehendes Ereignis (OutboxEvent) | – (LB: Option `lbvc_pending`, Inventur B §3.2) | **T `ccap_outbox_event`** + **T `ccap_outbox_delivery`** | Integration | TX: im selben Commit wie die Fachänderung. V: Queue in Optionen widerspricht 9.2. In Free nur interne Abonnenten (Beschluss E-38/E-182). |
| E-43 | Job | – | **kein eigener Tabellenvorschlag**: Action Scheduler (falls vorhanden) bzw. WP-Cron; lange Läufe über Import-Batch und Backfill-Cursor | Infrastruktur | ADR-006 entscheidet; Konzept 9.2 „ggf. `ccap_job`“. |
| E-44 | Integrationsverbindung | – | **Opt** (`course_captain_integrations`; Secrets nach E-87) | Integration (Pro; Free nur lesende Partnererkennung, E-185/E-254) | V: wenige Einträge. |
| E-45 | Importlauf | `import_batch` (:434) | **T `ccap_import_batch`** | Import | Zustandsautomat (Inventur C §5.2). |
| E-46 | Import-Protokoll | `import_item_log` (:452) | **T `ccap_import_item_log`** | Import | V: Retention 14/180 Tage (INV-C-121). |
| E-47 | Audit (AuditRecord) | `audit_log` (:465) | **T `ccap_audit_log`** | Infrastruktur | V; append-only. |
| E-48 | Lokaler Override | `override` (:479) | **T `ccap_override`** | Import/Content | Unique `(object_type, object_id, field)`. |
| E-49 | Aufbewahrungsregel (RetentionRule) | Konstanten/Filter (INV-C-121/149) | **Opt** (Settings, E-44) | Datenschutz | keine Tabelle nötig |
| E-50 | Rate-Limit-Bucket | `mobile_rate_limit` (:847) | **T `ccap_rate_limit`** | Infrastruktur | TX: atomar `INSERT … ON DUPLICATE KEY` (INV-C-047). |
| E-51 | Request-Idempotenz (REST) | `mobile_idempotency` (:796) | **T `ccap_request_idempotency`** (feste Tabelle; R0-19 §2.1 legt den Idempotency-Key-Header fest; E-05) | REST | TX: Unique `(user_id, scope, idem_key)`. |
| E-52 | Schema-/Migrationszustand | Optionen `bp_learning_schema_version`, `…_migration_lock`, `…_migration_failed`, `…_fk_issues` | **T `ccap_migration_step`** + **Opt** `course_captain_db_version`, `course_captain_schema_fingerprint`, `course_captain_schema_compat_min`, `course_captain_backfill_open`, `course_captain_fk_issues` | Infrastruktur | §6, R0-37 §4.3 |

**Summe:** 52 Entitäten: 40 in festen Tabellen (E-51 jetzt fest), 5 als CPT, 3 als Option/Setting (E-52 zusätzlich mit Tabelle `ccap_migration_step`), 1 abgeleitet (E-28), 1 als Spalten in anderen Tabellen (E-38), 1 als Infrastruktur ohne eigene Tabelle (E-43) und 1 außerhalb des Core (E-40, Pro/R3). Medien werden zusätzlich als WP-Anhang geführt. Weitere Tabellen aus Beschlüssen und Folge-Tasks: §4.4a und §4.10.

### 3.6 Abwägung CPT vs. Tabelle für Kurs/Modul/Lektion

| Kriterium | CPT (gewählt) | eigene Tabelle (LB-Weg) |
|---|---|---|
| Editor/UX | Block-Editor, Autosave, Vorschau, Papierkorb | eigener Editor nötig |
| Revisionen | WP-Revisionen zum Editieren; der Nachweis steckt in `ccap_course_revision` | alles selbst bauen |
| Suche/SEO/REST/CLI | nativ (`show_in_rest`, Sitemaps, `wp post`) | selbst bauen (R1-37) |
| Rechte | `map_meta_cap` mit `ccap_*`-Caps (R0-07) | selbst bauen |
| Integrität | keine FK auf `wp_posts` → Lösch-Hooks + Orphan-Check | harte FKs möglich |
| Import-Eindeutigkeit | über `ccap_source_map` | Spalten-Unique |
| Volumen | ≈100 Kurse, ≈2 000 Module, ≈20 000 Lektionen (§7): unkritisch, aber WP-Revisionen je CPT begrenzen (`wp_revisions_to_keep`) | – |

---

## 4. Tabellen-Spezifikation (exakte DDL in R1-02)

### 4.0 Konventionen (gelten für alle Tabellen)

- Name `{$wpdb->prefix}ccap_<name>` (Beschluss E-207; Tabellennamen in diesem ADR ohne `$wpdb->prefix`); `ENGINE=InnoDB` + `$wpdb->get_charset_collate()`; `dbDelta`-taugliche DDL (`PRIMARY KEY  (id)` mit zwei Leerzeichen, ein Feld je Zeile, keine FKs im `CREATE`).
- `id bigint(20) unsigned NOT NULL AUTO_INCREMENT`, `PRIMARY KEY (id)`, wenn nichts anderes genannt ist. Verweise auf Posts/Nutzer sind `bigint(20) unsigned`.
- **TS** = `created_at datetime NOT NULL`, `updated_at datetime NOT NULL`. Beide sind UTC und werden von PHP gesetzt; es gibt keinen DB-Default (Befund 7).
- **SRC** (Provenienz, aus LB `sourceColumns()` neutralisiert) = `source_system varchar(32) NOT NULL DEFAULT 'local'`, `source_id varchar(191) NULL`, `source_hash char(71) NULL` (`sha256:`+64), `package_id varchar(64) NULL`, `imported_at datetime NULL`. Mit `UNIQUE source (source_system, source_id)`. `source_id NULL` erlaubt mehrere lokal erstellte Datensätze; LB hat `NOT NULL`, weil dort alles importiert wird.
- **UID** = `uid char(36) NOT NULL` (UUID v4) mit `UNIQUE uid`: stabile externe ID für REST, Export und Events (ADR-003 „stabile IDs“, 30.3). Die interne `id` wird nie öffentlich zugesagt.
- **CAS** = `row_version int unsigned NOT NULL DEFAULT 0`; Statuswechsel als `UPDATE … SET …, row_version = row_version + 1 WHERE id = %d AND row_version = %d` (Kap. 37 Compare-and-swap).
- **Idempotenz-/Unique-Spalten sind `NOT NULL`** (mit `''` bzw. `0` als neutralem Wert), weil MySQL mehrere `NULL` in einem UNIQUE zulässt und Dedupe sonst still versagt. `NULL` steht nur dort, wo mehrere „leere“ Zeilen gewollt sind (z. B. `ccap_grant_event.source_event_id`).
- JSON-Spalten sind `longtext`, validiert in PHP; `JSON_EXTRACT` nur in Export und Löschung (E-08).
- **FK-Spalte** in den Tabellen unten: `→ziel (REGEL)` bedeutet einen harten FK (Beschluss E-48). `→post`/`→user` bedeutet Anwendungsintegrität (§4.9).

### 4.1 Inhalt

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes (Hauptabfrage) | FK / Löschregel |
|---|---|---|---|---|
| `ccap_source_map` | UID (R0-08 Δ-1, für jedes CPT-Objekt), `object_type varchar(32)` (`course/module/lesson/faq/tip`), `object_id bigint`, SRC ohne Unique-Makro, `locally_modified tinyint(1) 0`, `last_batch_id bigint NULL`, `last_hash char(71) NULL` (R0-38); TS | `UNIQUE uid`, `UNIQUE source (source_system, source_id, object_type)`, `UNIQUE object (object_type, object_id)` | – (Uniques decken „Import findet Objekt“ und „Objekt zeigt Herkunft“ ab) | `object_id`→post; beim endgültigen Löschen des Posts mitlöschen |
| `ccap_course_module` | `course_id`, `module_id` (bigint), `sort_order int 0`, `required tinyint(1) 1`, `rules longtext NULL` | `UNIQUE course_module (course_id, module_id)` | `course_sort (course_id, sort_order)`, `module_id` | →post (beide); Entfernen nur bei Entwurf, sonst neue Kursrevision |
| `ccap_course_relation` *(neu, E-25/E-25a)* | `course_id` (Bundle-Kurs), `related_course_id`, `relation_type varchar(16) 'contains'` (R1 nur `contains`), `sort_order int 0`, `created_by bigint NULL`; TS | `UNIQUE course_relation (course_id, related_course_id, relation_type)` | `related (related_course_id, relation_type)` (effektiver Zugang: „welche Bundles enthalten diesen Kurs?“) | →post (beide); Zyklen und Selbstbezug werden beim Speichern abgewiesen; Änderung an veröffentlichten Bundles nur über Kurs veröffentlichen (E-104) |
| `ccap_course_revision` | `course_id`, `revision_no int unsigned`, `status varchar(16)` (`published/superseded/archived`), `structure longtext` (JSON: Module, Lektionen inkl. WP-Revisions-ID und Inhalts-Hash, Regel-IDs, Zugangsregeln), `structure_hash char(71)`, `format_version smallint unsigned`, `published_by bigint NULL`, `published_at datetime` | `UNIQUE course_rev (course_id, revision_no)` | `course_status (course_id, status)` | →post; **nie löschen**, solange Versuche, Fortschritt oder Zertifikate verweisen |
| `ccap_question` | UID, SRC, `reference_no varchar(32) NULL` (statt `official_number`), `language varchar(20)`, `status varchar(16) 'draft'` (`draft/published/archived`), `current_revision_id bigint NULL`, `author_user_id bigint NULL` (Objekt-Scope R0-07); TS | `UNIQUE uid`, `UNIQUE source` | `status`, `current_revision_id`, `reference_no` | nie hart löschen (Archiv, INV-C-108) |
| `ccap_question_revision` | `question_id`, `revision_no int unsigned`, `content_hash char(71)` (LB `source_hash`), `package_id varchar(64) NULL`, `source_version varchar(64) NULL`, `question_type varchar(32)`, `question_text longtext`, `settings longtext NULL` (typspezifisch je Activity-Provider), `feedback_correct`/`feedback_incorrect longtext NULL`, `image_required tinyint(1) 0`, `editorial_status varchar(20) 'draft'` (`draft/review/published/deprecated`), `rights_status varchar(20)` (E-18: `own` für eigene Inhalte), `rights_owner varchar(255) NULL`, `license_reference longtext NULL`, `valid_from`/`valid_to date NULL`, `created_by bigint NULL`, `created_at`, `published_at datetime NULL` | `UNIQUE question_rev (question_id, revision_no)` | `question_hash (question_id, content_hash)`, `publish (question_id, editorial_status, valid_from, valid_to)` (beide aus LB) | `question_id`→`ccap_question` (RESTRICT) |
| `ccap_question_tag` | `revision_id`, `tag_type varchar(32)` (`topic/subtopic/competency/category/scope`), `tag_key varchar(191)`, `label varchar(255)` | `UNIQUE rev_tag (revision_id, tag_type, tag_key)` | `tag (tag_type, tag_key)` | `revision_id`→`ccap_question_revision` (CASCADE) |
| `ccap_answer` | `revision_id`, `answer_key varchar(16)`, `answer_text longtext`, `is_correct tinyint(1) 0`, `sort_order int 0` | `UNIQUE revision_key (revision_id, answer_key)` | – | →`ccap_question_revision` (CASCADE, wie LB) |
| `ccap_module_question` | `module_id`, `question_id`, `sort_order int NULL`, `required tinyint(1) 1` | `UNIQUE module_question (module_id, question_id)` | `question_id` | `question_id`→`ccap_question` (CASCADE, wie LB); `module_id`→post |
| `ccap_activity_question` | `activity_id`, `question_id`, `sort_order int 0` | `UNIQUE activity_question (activity_id, question_id)` | `question_id` | `question_id`→`ccap_question` (RESTRICT, wie LB); `activity_id`→post |
| `ccap_media` | UID, SRC, `attachment_id bigint NULL`, `storage_key varchar(255) NULL`, `package_path varchar(255) NULL`, `media_type varchar(20) 'image'`, `mime_type varchar(100)`, `size_bytes bigint unsigned 0`, `sha256 char(64)`, `access varchar(16) 'protected'` (`public/protected`, R0-10, Beschluss E-65), `alt_text varchar(255) NULL`, `caption`/`description`/`source_text longtext NULL`, `source_url varchar(2048) NULL`, `rights_owner varchar(255) NULL`, `license_reference longtext NULL`, `rights_status varchar(20)`, `status varchar(16) 'active'`; TS | `UNIQUE uid`, `UNIQUE source`, `UNIQUE attachment_id` | `sha256` (Dedupe: eine Datei je SHA-256, eine Medienzeile je Herkunft, R0-38), `rights_status` | `attachment_id`→post (SET NULL per Hook) |
| `ccap_content_media` | `object_type varchar(32)`, `object_id bigint`, `media_id`, `role varchar(32)`, `sort_order int 0`, `caption_override longtext NULL`, `alt_override varchar(255) NULL` | `UNIQUE object_media (object_type, object_id, media_id, role)` | `media_id` | `media_id`→`ccap_media` (RESTRICT) |

*Nicht übernommene LB-Spalten:* `content_role`, `source_catalog`, `source_page`, `source_question_id`, `source_module`, `media.vector_source` (entfällt nach R0-10), `course.course_type` (Bundles über `ccap_course_relation`).

*Fragetypen:* `question_type` deckt nach Beschluss E-22 und E-69 in R1 Free zusätzlich „Zahl mit Toleranz“, Zuordnung, Sortieren und Lückentext ab (typspezifisch in `settings`, R0-08 §3); keine eigene Tabelle. Lösungserklärungen (`feedback_*`) werden immer gespeichert, aber nur mit Pro ausgeliefert (E-20, E-147).

### 4.2 Prüfung

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK / Löschregel |
|---|---|---|---|---|
| `ccap_exam_rule` | UID, SRC, `course_id`, `version varchar(64)`, `status varchar(16) 'draft'` (`draft/published/retired`, E-23), `valid_from`/`valid_until date NULL`, `time_limit_seconds int unsigned NULL` (NULL = ohne Limit), `passing_rule longtext` (Pflicht, kein Default), `settings longtext NULL` (Feedback-Sichtbarkeit E-20, Mischen, Versuchsgrenzen; R0-08/R0-27), `published_by bigint NULL`, `published_at datetime NULL`; TS | `UNIQUE uid`, `UNIQUE source`, `UNIQUE course_version (course_id, version)` | `course_status (course_id, status)` | `course_id`→post; veröffentlichte Regel nie ändern, nur `retired` + neue Version |
| `ccap_exam_blueprint` | `rule_id`, `module_id`, `question_count int unsigned`, `rules longtext NULL` | `UNIQUE rule_module (rule_id, module_id)` | `module_id` | `rule_id`→`ccap_exam_rule` (CASCADE) |
| `ccap_exam_variant` | UID, SRC, `course_id`, `variant_no int unsigned`, `status varchar(16) 'draft'`, `sort_order int 0`; TS | `UNIQUE uid`, `UNIQUE source`, `UNIQUE course_variant (course_id, variant_no)` | – | `course_id`→post |
| `ccap_exam_variant_question` | `variant_id`, `question_id`, `position int unsigned` | `UNIQUE variant_position (variant_id, position)` | `question_id` | `variant_id`→`ccap_exam_variant` (CASCADE), `question_id`→`ccap_question` (RESTRICT) |
| `ccap_exam_session` | UID, `user_id`, `course_id`, `course_revision_id bigint NULL`, `rule_id`, `variant_id bigint NULL`, `activity_uid char(36) NULL` (E-67, R0-08 Δ-2), `mode varchar(16)` (`variant/random/quiz`; `quiz` nach E-68), `form_key varchar(64)` (Variante bzw. `random:<rule>`, R0-21 Δ-21-3), `running tinyint(1)` *generiert* `IF(status = 'in_progress', 1, NULL)`, `status varchar(16)` (`in_progress/submitted/evaluated/cancelled/broken`, R0-08 Δ-5), `end_reason varchar(24) NULL` (u. a. `submitted`, `expired`, `user`, `admin`, `withdrawn` nach E-108, `support_void` nach E-137; E-75), `start_token char(36)`, `snapshot longtext` (Regel, Blueprints, Zeitlimit, Bestehensregel, Seed, Reihenfolge, eingefrorener Zeitfaktor nach E-122), `snapshot_hash char(71)`, `seed bigint unsigned NULL`, `evaluator_version varchar(32)`, `evaluation_no smallint unsigned NOT NULL DEFAULT 0` (E-74), `started_at datetime`, `deadline_at`/`submitted_at`/`evaluated_at datetime NULL`, `points_milli`/`max_points_milli int NULL` (R0-08 Δ-3), `passed tinyint(1) NULL`, `result longtext NULL`, CAS | `UNIQUE uid`, `UNIQUE start_token`, `UNIQUE running_form (user_id, form_key, running)` (R0-21 D21-4, eigene Migration) | `user_course (user_id, course_id, status)`, `status_deadline (status, deadline_at)` (Ablauf-Cron), `rule_id`, `variant_id`, `course_revision_id` | `rule_id`→`ccap_exam_rule` (RESTRICT), `variant_id`→`ccap_exam_variant` (RESTRICT), `course_revision_id`→`ccap_course_revision` (RESTRICT); `user_id`→user (Eraser) |
| `ccap_exam_session_question` | `session_id`, `position int unsigned`, `question_id`, `revision_id`, `answer_order longtext NULL`, `given_answer longtext NULL`, `self_assessed_known tinyint(1) NULL`, `revealed_at datetime NULL`, `is_correct tinyint(1) NULL`, `answered_at datetime NULL`, `flagged tinyint(1) 0` | `UNIQUE session_position (session_id, position)` | `question_id`, `revision_id` | `session_id`→`ccap_exam_session` (CASCADE), `question_id`→`ccap_question` (RESTRICT), `revision_id`→`ccap_question_revision` (RESTRICT) |

**Idempotenz Abgabe:** wie LB über `SELECT … FOR UPDATE` plus Statusprüfung. Eine zweite Abgabe liefert das vorhandene Ergebnis (Inventur A §5.2, `ExamService.php:521, :537`), zusätzlich mit CAS auf `row_version`. Konzept 37 nennt „`attempt_id + submission_revision`“. Eine Prüfung hat genau eine Abgabe je `uid`; Nachbewertungen zählen `evaluation_no` hoch (Beschluss E-74, Oberfläche erst mit dem Gradebook in Pro). Mehrfachabgaben gibt es nur bei Text-/Dateiaufgaben (§4.4a, `submission_no`).

### 4.3 Lernen

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK / Löschregel |
|---|---|---|---|---|
| `ccap_attempt` | `user_id`, `question_id`, `revision_id`, `course_id bigint NULL` (LB `context_ref` = Kurscode), `context varchar(32)` (`learn/lesson/review`), `context_ref varchar(64) NULL`, `is_correct tinyint(1) NULL`, `self_assessment varchar(16) NULL`, `answer_ms int unsigned NULL`, `idempotency_key char(36)`, `correction_of bigint NULL`, `algorithm_version smallint unsigned`, `created_at` | `UNIQUE user_idem (user_id, idempotency_key)` (LB: global `idempotency_key`; der Nutzerbezug verhindert Fremdkollisionen) | `user_question (user_id, question_id)`, `user_created (user_id, created_at)`, `revision_id`, `course_created (course_id, created_at)` (Reports ohne Scan, 22.3) | `question_id`→`ccap_question` (RESTRICT), `revision_id`→`ccap_question_revision` (RESTRICT) |
| `ccap_attempt_answer` | `attempt_id`, `answer_id bigint NULL`, `answer_key varchar(16) NULL`, `free_text longtext NULL`, `position int 0` | – | `attempt_id`, `answer_id` | `attempt_id`→`ccap_attempt` (CASCADE) |
| `ccap_question_progress` | `user_id`, `question_id`, `attempts`/`correct_count`/`wrong_count`/`streak int unsigned 0`, `mastery_score decimal(5,4) 0`, `learn_status varchar(16) 'new'`, `algorithm_version smallint unsigned`, `first_answered_at`/`last_answered_at`/`due_at datetime NULL`, CAS | `UNIQUE user_question (user_id, question_id)` | `question_id`, `user_status (user_id, learn_status)`, **neu** `user_due (user_id, due_at)` (Smarttrainer-Fälligkeitsauswahl; in R0-14 messen) | `question_id`→`ccap_question` (RESTRICT) |
| `ccap_activity_progress` | `user_id`, `activity_id`, `course_id bigint NULL`, `activity_type varchar(32)`, `status varchar(16)` (`started/completed`), `reason_code varchar(32) NULL`, `rule_version smallint unsigned NULL` (R0-08 Δ-6), `position_seconds int unsigned 0`, `data longtext NULL`, `content_hash char(71) NULL` (Stand bei Abschluss), `completed_at datetime NULL`, `last_access_at datetime`, `created_at` | `UNIQUE user_activity (user_id, activity_id)` | `activity_id`, `user_course (user_id, course_id)` | `activity_id`→post (kein Löschen bei vorhandenem Fortschritt) |
| `ccap_course_progress` | `user_id`, `course_id`, `course_revision_id bigint NULL`, `status varchar(16)` (`not_started/in_progress/completed`), `completion_ratio decimal(5,4)`, `mastery_ratio decimal(5,4) NULL`, `activities_done`/`activities_total int unsigned`, `started_at`/`completed_at`/`last_activity_at datetime NULL`, `updated_at`, CAS | `UNIQUE user_course (user_id, course_id)` | `course_status (course_id, status)`, `user_last (user_id, last_activity_at)` | `course_revision_id`→`ccap_course_revision` (RESTRICT); neu aufbaubar (Backfill-Job) |

*Ergänzungen aus R0-23 §8 (Δ-23-1…8):* u. a. `completed_revision_id` und `cycle_no` in `ccap_course_progress`, Post-Status `ccap_staged`/`ccap_archived` (Staging nach E-104, Archiv nach E-108); Details in R0-23. Lernstände rollen nach Beschluss E-103 vorwärts mit Besitzstand.

| `ccap_favorite` | `user_id`, `question_id`, `context varchar(32) 'question'`, `created_at` | `UNIQUE user_question_context (user_id, question_id, context)` | `question_id` | →`ccap_question` (CASCADE, wie LB) |
| `ccap_collection` | `user_id`, `course_id`, `name varchar(80)`, `created_at` | – | `user_course (user_id, course_id)`, `course_id` | `course_id`→post (LB: CASCADE auf Kurs → Hook) |
| `ccap_collection_item` | `collection_id`, `question_id`, `created_at` | **PK `(collection_id, question_id)`** | `question_id` | →`ccap_collection` (CASCADE), →`ccap_question` (CASCADE) |

### 4.4 Zugang und Gruppen

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK / Löschregel |
|---|---|---|---|---|
| `ccap_grant` | UID, `user_id bigint NULL` (NULL nur in `pending_claim` und nach Pseudonymisierung, R0-09 Δ-7), `subject_ref char(64) NULL` (HMAC), `course_id`, **`module_id bigint unsigned NOT NULL DEFAULT 0`** (0 = ganzer Kurs; Beschluss E-27, NOT NULL statt NULL, weil UNIQUE mehrere NULL zulässt, R0-40 Δ40-1), `scopes varchar(64) 'all'` (`view,download,exam`), `depends_on_course_id bigint unsigned NOT NULL DEFAULT 0` (Beschluss E-26, 0 = unabhängig; Regel R0-09 §3.5), `status varchar(24) 'pending_confirmation'` (35.2: `pending_claim/pending_confirmation/active/suspended/expired/revoked/failed`), **Quellschlüssel:** `source_type varchar(32) NOT NULL` (`manual/group_seat` in Free; `woo_order/voucher/membership/workflow` nur über Pro-Connectoren, E-38/E-39b/E-53/E-203), `source_id varchar(191) NOT NULL`, `source_line varchar(64) NOT NULL DEFAULT ''` (Position/Seat/Zielindex), `source_revision int unsigned NOT NULL DEFAULT 1` (E-29, E-117), `source_seq bigint unsigned NOT NULL DEFAULT 0` (R0-09 Δ-4), `source_plugin varchar(64) NULL` (31.2), `seat_id bigint NULL`, `valid_from`/`valid_until datetime NULL`, `valid_until_locked tinyint(1) NOT NULL DEFAULT 0`, `manual_override varchar(16) NULL` (`suspended/approved`), `paid_at`/`chain_anchor_at datetime NULL`, `renewal_mode varchar(16) NOT NULL DEFAULT 'extend'` (R0-09 Δ-2/Δ-3), `revocation_reason varchar(64) NULL`, `granted_by bigint NULL`, `reason longtext NULL`, `meta longtext NULL` (minimal), CAS; TS | `UNIQUE uid`, **`UNIQUE grant_source (source_type, source_id, source_line, source_revision, course_id, module_id)`** = `grant_source_key` (Kap. 35.2/37; utf8mb4 ≈ 1 170 Byte < 3 072) | `user_access (user_id, course_id, module_id, status, valid_until)` (effektiver Zugang), `status_until (status, valid_until)` (Ablauf-Cron), `course_status (course_id, status)`, `chain (user_id, course_id, renewal_mode, chain_anchor_at)` (Restack), `depends (user_id, depends_on_course_id)`, `seat_id`; Lookup nach Quelle über das linke Präfix des Unique | `seat_id`→`ccap_seat` (SET NULL); `user_id`→user (Kontolöschung quellenneutral, R0-09 §7) |
| `ccap_grant_event` | `grant_id`, `event_type varchar(32)`, `from_status`/`to_status varchar(24) NULL`, `reason_code varchar(64)`, `actor_type varchar(16)` (`user/system/connector`), `actor_id bigint NULL`, `source_event_id varchar(191) NULL`, `correlation_id char(36) NULL`, `details longtext NULL`, `occurred_at datetime`, `created_at` | `UNIQUE grant_source_event (grant_id, source_event_id)` (NULL erlaubt für interne Ereignisse) | `grant_created (grant_id, created_at)`, `correlation_id` | →`ccap_grant` (CASCADE; Grants werden nur über die Retention gelöscht) |
| `ccap_access_rule` | `course_id`, `module_id bigint NULL`, `scope varchar(32)`, `access_type varchar(16)` (`free/free_limited/drip`), `limit_type varchar(32) NULL`, `limit_value int NULL`, `valid_from`/`valid_until datetime NULL`; TS | – | `course_scope (course_id, scope)` | →post |
| `ccap_group` | UID, `owner_user_id bigint`, `name varchar(190)`, `seats int unsigned 0`, `status varchar(16) 'active'` (`active/suspended/revoked`), `claim_policy varchar(24) NOT NULL DEFAULT 'transferable'` (`transferable/invited_address_only`, E-28), `valid_from`/`valid_until datetime NULL`, `source_type varchar(32) NOT NULL DEFAULT 'manual'`, `source_id varchar(191) NULL` (NULL = manuell, mehrfach erlaubt), `source_revision int unsigned NOT NULL DEFAULT 1` (Wiedereinsetzung, R0-09 §4.8), `join_token_hash char(64) NULL` (E-112), `reason longtext NULL`, CAS; TS | `UNIQUE uid`, `UNIQUE group_source (source_type, source_id)`, `UNIQUE join_token_hash` | `owner (owner_user_id)`, `status` | – |
| `ccap_group_course` | `group_id`, `course_id`, `module_id bigint unsigned NOT NULL DEFAULT 0` (E-27), `scopes varchar(64) 'all'` | `UNIQUE group_course (group_id, course_id, module_id)` | `course_id` | →`ccap_group` (CASCADE) |
| `ccap_seat` | `group_id`, `user_id bigint NULL`, `active_user bigint unsigned NULL` *generiert* `IF(status = 'active', user_id, NULL)` (STORED), `invite_email varchar(190) NULL` (nur Kommunikation, beim Claim geleert), `status varchar(16) 'invited'` (`invited/active/removed/expired`), `claim_token_hash char(64) NULL`, `claim_expires_at datetime NULL`, `invited_at`/`claimed_at`/`removed_at datetime NULL`; TS | `UNIQUE claim_token_hash`, `UNIQUE active_member (group_id, active_user)` (R0-21 D21-4, eigene Migration) | `group_status (group_id, status)`, `status_invited (status, invited_at)` (Ablauf 30 Tage), `user_id`, `invite_email` | →`ccap_group` (CASCADE) |

Plätze werden unter dem Mutex `group-seats:<group_id>` vergeben (Verhalten LB, Inventur B §5.4; LockService R0-21). „Ein aktiver Platz je Nutzer und Gruppe“ sichert zusätzlich `UNIQUE active_member` über die generierte Spalte; ein Re-Invite nach `removed` bleibt möglich, weil `active_user` dann NULL ist (Test in R1-42).

**Bundle und abhängige Grants (E-25a, E-26):** Ein Grant auf einen Bundle-Kurs erzeugt **keine** Grants je enthaltenem Kurs; der effektive Zugang leitet sie über `ccap_course_relation` ab (R0-09 §3.1). Ein Grant mit `depends_on_course_id ≠ 0` wirkt nur, solange der Lernende effektiven Zugang zum Basiskurs hat (R0-09 §3.5).

### 4.4a Text-/Dateiaufgaben (Beschluss E-168, R1-46)

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK / Löschregel |
|---|---|---|---|---|
| `ccap_assignment_submission` | UID, `user_id`, `course_id`, `activity_id` (Lektion/Activity der Aufgabe), `activity_uid char(36)`, `course_revision_id bigint NULL`, `submission_no smallint unsigned 1` (neue Zeile je erneuter Abgabe nach `returned`), `status varchar(16) 'draft'` (`draft/submitted/returned/graded`, E-168), `text_answer longtext NULL`, `submitted_at`/`returned_at`/`graded_at datetime NULL`, `current_grade_id bigint NULL`, CAS; TS | `UNIQUE uid`, `UNIQUE user_activity_no (user_id, activity_id, submission_no)` | `course_status (course_id, status)` (Bewertungsliste), `activity_status (activity_id, status)` | `activity_id`→post; `course_revision_id`→`ccap_course_revision` (RESTRICT); `user_id`→user (Eraser) |
| `ccap_assignment_file` | `submission_id`, `media_id` (Datei als `ccap_media` mit `access = protected`, Upload-Regeln R0-10), `original_name varchar(255)`, `mime_type varchar(100)`, `size_bytes bigint unsigned`, `sha256 char(64)`, `sort_order int 0`, `created_at` | `UNIQUE submission_media (submission_id, media_id)` | `media_id` | `submission_id`→`ccap_assignment_submission` (CASCADE), `media_id`→`ccap_media` (RESTRICT) |
| `ccap_assignment_grade` | `submission_id`, `evaluation_no smallint unsigned` (append-only, wie E-74), `points_milli int NULL`, `max_points_milli int NULL`, `passed tinyint(1) NULL`, `feedback longtext NULL`, `graded_by bigint`, `graded_at datetime`, `reason longtext NULL` (Pflicht bei Neubewertung), `created_at` | `UNIQUE submission_eval (submission_id, evaluation_no)` | `graded_by` | `submission_id`→`ccap_assignment_submission` (RESTRICT) |

Zustandswechsel `draft → submitted → (returned → neue Zeile `draft`) | graded` per CAS; Bewerten nie an eigener Abgabe (wie R0-19 R-47). Der Golden Master hat keine LB-Quelle (E-168).

### 4.5 Nachweise

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK / Löschregel |
|---|---|---|---|---|
| `ccap_certificate` | `certificate_uid char(36)` (stabil über Revisionen), `revision_no smallint unsigned 1`, `user_id`, `course_id`, `course_revision_id`, `evidence_type varchar(32)` (`exam_session/course_completion`), `evidence_id bigint`, `status varchar(16)` (`issued/corrected/revoked`), `supersedes_id bigint NULL`, `template_version varchar(32)`, `snapshot longtext` (gedruckte Angaben), `snapshot_hash char(71)`, `verify_token_hash char(64)`, `issued_at datetime`, `issued_by bigint NULL`, `revoked_at datetime NULL`, `revoked_by bigint NULL`, `revoke_reason longtext NULL`, `created_at` | `UNIQUE cert_rev (certificate_uid, revision_no)`, `UNIQUE evidence_rev (evidence_type, evidence_id, revision_no)` (Ausstellung idempotent), `UNIQUE verify_token_hash` | `user_course (user_id, course_id)` | `course_revision_id`→`ccap_course_revision` (RESTRICT), `supersedes_id`→`ccap_certificate` (RESTRICT) |

### 4.6 Integration

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK |
|---|---|---|---|---|
| `ccap_inbox_event` | `connector_id varchar(64)`, `event_id varchar(191)`, `contract_version varchar(16)`, `event_type varchar(64)`, `correlation_id char(36) NULL`, `payload longtext` (minimal, ohne Secrets), `payload_hash char(64)`, `status varchar(16)` (`received/processing/done/ignored/failed/quarantined`), `attempts smallint unsigned 0`, `next_attempt_at`/`locked_until datetime NULL`, `lock_owner varchar(64) NULL`, `last_error text NULL`, `occurred_at`/`received_at datetime`, `processed_at datetime NULL` | `UNIQUE connector_event (connector_id, event_id)` | `status_next (status, next_attempt_at)`, `correlation_id` | – |
| `ccap_outbox_event` | `event_uuid char(36)`, `event_type varchar(64)`, `schema_version varchar(16)`, `contract_version varchar(16)`, `object_type varchar(32)`, `object_id char(36)`, `object_version int unsigned`, `course_uid char(36) NULL`, `user_ref varchar(32) NULL`, `actor_type varchar(16)`, `actor_ref varchar(64) NULL`, `correlation_id char(36) NULL`, `causation_id char(36) NULL`, `idempotency_key varchar(191)`, `privacy_tags varchar(191)`, `payload longtext` (Envelope R0-19 §5.1), `created_at` | `UNIQUE event_uuid` | `type_created (event_type, created_at)`, `object (object_type, object_id)`, `created_at` (Retention) | – |

| `ccap_outbox_delivery` | `event_uuid char(36)`, `subscriber_id varchar(64)`, `status varchar(16)` (`pending/dispatching/delivered/failed/dead/skipped`), `attempts smallint unsigned 0`, `next_attempt_at`/`locked_until datetime NULL`, `lock_owner varchar(64) NULL`, `last_error_class varchar(32) NULL`, `last_error text NULL`, `created_at`, `delivered_at datetime NULL` | `UNIQUE event_subscriber (event_uuid, subscriber_id)` | `status_next (status, next_attempt_at)` | `event_uuid`→`ccap_outbox_event.event_uuid` (CASCADE) |

Zustellung je Abonnent nach R0-19 §5.4 (Dead-Letter nach 8 Versuchen). **Free (Beschluss E-38/E-182):** nur interne Abonnenten (Benachrichtigungen, Audit, Cache-Invalidierung); ein Event wird nur gespeichert, wenn mindestens ein interner Abonnent es abonniert; zugestellte Einträge 90 Tage (E-116, E-215), offene höchstens 30 Tage (E-258). **Pro:** externe Abonnenten, Pull-API und Event-Log auch ohne Abonnent 30 Tage (E-119).

### 4.7 Betrieb

| Tabelle | Spalten (Typ) | PK / Uniques | Indizes | FK |
|---|---|---|---|---|
| `ccap_import_batch` | `package_id varchar(64)`, `schema_version varchar(16)`, `source_revision varchar(64) NULL` (LB `source_commit char(40)`), `status varchar(20) 'uploaded'` (Automat R0-10/R0-38, zusätzlich gespeichert `activating`, `aborted`, `superseded`), `manifest longtext`, `stats longtext NULL`, `storage_path varchar(255) NULL`, `error longtext NULL`, `heartbeat_at datetime NULL` (LB: nur in `stats`; als Spalte für die Stale-Abfrage), `created_by`/`approved_by bigint NULL`, `created_at`, `approved_at`/`published_at datetime NULL` | – | `package_id`, `status` | – |
| `ccap_import_item_log` | `batch_id`, `entity varchar(32)`, `source_id varchar(191) NULL`, `action varchar(16)`, `message longtext NULL`, `created_at` | – | `batch_entity (batch_id, entity)`, `action`, `action_created (action, created_at)`, `created_at` (alle LB) | →`ccap_import_batch` (CASCADE) |
| `ccap_audit_log` | `actor_user_id bigint NULL`, `actor_type varchar(16)`, `subject_user_id bigint NULL`, `action varchar(64)`, `reason_code varchar(64) NULL`, `object_type varchar(32) NULL`, `object_id varchar(191) NULL`, `reason longtext NULL`, `context longtext NULL`, `correlation_id char(36) NULL`, `request_id varchar(40) NULL`, `row_hash char(64)` (Hash-Kette, E-139), `created_at` (R0-13 §8) | – | `actor (actor_user_id)`, `subject (subject_user_id)`, `object (object_type, object_id(64))`, `action`, `created_at` | – (append-only) |
| `ccap_override` | `object_type varchar(32)`, `object_id bigint`, `field varchar(32)`, `value varchar(255) NULL`, `reason longtext`, `user_id bigint NULL`, `created_at` | `UNIQUE object_field (object_type, object_id, field)` | – | – |
| `ccap_rate_limit` | `bucket_hash char(64)` **PK**, `window_start int unsigned`, `hits int unsigned 0`, `expires_at datetime` | PK | `expires_at` | – |
| `ccap_request_idempotency` *(fest, R0-19 §2.1)* | `user_id`, `scope varchar(64)`, `idem_key char(36)`, `request_hash char(64)`, `status_code smallint unsigned 0`, `response longtext NULL`, `resource varchar(64) NULL`, `created_at`, `expires_at datetime` (7 Tage) | `UNIQUE user_scope_key (user_id, scope, idem_key)` | `expires_at` | – |

### 4.10 Weitere Tabellen aus Beschlüssen und Folge-Tasks

| Tabelle | Inhalt (Kurz) | Quelle / Beschluss |
|---|---|---|
| `ccap_course_staff` | `course_id`, `user_id`, `role` (`author/trainer`), `assigned_by`, TS; `UNIQUE (course_id, user_id, role)` | R0-07 §8, E-78, E-99 |
| `ccap_learner_lock` | `user_id`, Grund, Zeitraum, gesetzt/aufgehoben von, Audit; aufgehobene Sperren 30 Tage (E-243) | R0-07 §8, E-79, E-144 |
| `ccap_accommodation` | Lernender, Scope Site/Kurs/Regel, Zeitfaktor 100–400 % oder unbegrenzt, gesetzt von, Zeitraum; nur mit Cap `ccap_manage_accommodations` lesbar | R0-15 §2.3, E-122 |
| `ccap_lease` | `name varchar(191)` PK, `owner varchar(64)`, `fence bigint unsigned`, `expires_at`, `heartbeat_at`, `created_at` | R0-21 Δ-21-1, D21-1 |
| `ccap_migration_step` | Schritt, Modul, Art, Status `pending/running/failed/done`, Cursor, Fence, Fehler, Prüfsumme, Zeitstempel | R0-37 §4.3, D37-1 |
| `ccap_import_issue` | Batch, Objekt-ID, Datei, JSON-Pointer, Regel, Schwere, Fehlerklasse, Meldung, Vorschlag; max. 100 000 je Batch | R0-38 §8 |
| `ccap_notification` | höchstens ein Datensatz je Event, Typ und Empfänger (Dedupe der Mails), 90 Tage | R0-27 §3.3, N-1, E-215 |
| *Pro:* `ccap_source_saga` | Zwischenstand Voucher-/Woo-Abläufe (kein Ledger, keine Beträge) | R0-40 §3.2 Δ40-2, E-31, E-53 |
| *Pro:* Woo-Adapter-Mapping | Produkt ↔ `(course, module|0, scopes)`, Sync-Schlüssel, Feld-Fingerprints für den Produkt-Sync | R0-40 §4.5, E-34, R3-02 |

### 4.8 Transaktionsgrenzen (Auszug für R0-DATA-002)

| Operation | Grenze | Schutz | Quelle LB |
|---|---|---|---|
| Lernantwort speichern | 1 TX: `ccap_attempt` + `ccap_attempt_answer` + `ccap_question_progress` (`FOR UPDATE`) | Unique `user_idem`; Rate-Limit `answer\|<id>` | `LearningService.php:214-268, :243, :498` |
| Prüfung starten | `NamedLock exam_start:<user>` + 1 TX: Session + alle Session-Fragen + Snapshot | Unique `start_token`; laufende Sitzung derselben Form wird zurückgegeben | `ExamService.php:183-194, :277` |
| Antwort/Flag/Abgabe | 1 TX je Aktion, `FOR UPDATE` auf Session, CAS `row_version` | Status `running`/`in_progress` als Vorbedingung | `ExamService.php:456, :490, :614` |
| Grant-Übergang | 1 TX: `ccap_grant` (CAS) + `ccap_grant_event` + `ccap_outbox_event` | Unique `grant_source`, `grant_source_event` | neu (LB: `suppress_errors`-Insert, `EntitlementService.php:107-112`) |
| Platz beanspruchen | Lock-Reihenfolge R0-21 §2.4 (M1 → M3), dann 1 TX: Seat + Grants je `ccap_group_course` | Unique `claim_token_hash`, `active_member`; Token-Ablauf | `GroupService.php:236, :346` |
| Aufgabe abgeben/bewerten (E-168) | 1 TX: Submission (CAS) + Dateizeilen bzw. Grade + interne Outbox | Unique `user_activity_no`, `submission_eval` | neu |
| Voucher-/Woo-Saga (Pro) | Lock-Reihenfolge R0-40 §3.3, Saga-Zeile und Grants in getrennten, idempotenten TX | Unique `saga_key`, `grant_source` | neu |
| Zertifikat ausstellen | 1 TX: Certificate + Outbox | Unique `evidence_rev` | neu |
| Inbox verarbeiten | Claim per `UPDATE … SET locked_until/lock_owner WHERE status IN(...) AND (locked_until IS NULL OR locked_until < now)`, dann Fach-TX | Unique `connector_event` | neu |
| Import-Schritt | 1 TX je Schritt; Mediendateien außerhalb (Prüfsumme beim Wiederholen) | Options-Lock + Heartbeat | `ImportService.php:398` (T199/T203) |

### 4.9 Integrität ohne harte FKs auf WP-Tabellen

- `before_delete_post` (nur endgültiges Löschen) für `ccap_course/ccap_module/ccap_lesson` **verweigert** das Löschen, wenn Lerndaten, Grants, Kursrevisionen oder Zertifikate verweisen. Erlaubt sind dann nur Papierkorb oder Archiv. Das entspricht RESTRICT; LB löscht Content nie hart (INV-C-108).
- `delete_user`/`deleted_user` (+ `wpmu_delete_user`): Eraser-Pfad aus LB (INV-C-148/152). Lerndaten werden in einer TX gelöscht, Grants nach quellenneutraler Regel (INV-C-158) geschlossen bzw. für die Retention pseudonymisiert (E-44).
- Health/Monitor: Orphan-Check je `→post`/`→user`-Spalte plus FK-Status (Verhalten LB `Monitor` `foreign_keys`, INV-C-055).
- Lösch-Tombstones außerhalb des Datenbestands werden nach jedem Restore erneut angewendet (Beschluss E-92, E-151).
- **Personendaten-Registry** (Muster LB `USER_DATA_TABLES`, `Schema.php:919`): `ccap_attempt`, `ccap_question_progress`, `ccap_activity_progress`, `ccap_course_progress`, `ccap_favorite`, `ccap_collection`, `ccap_exam_session`, `ccap_grant`, `ccap_seat`, `ccap_certificate`, `ccap_request_idempotency`, `ccap_assignment_submission`, `ccap_learner_lock`, `ccap_accommodation` (besonders schützenswert, R0-17), `ccap_course_staff`, `ccap_notification`; Kindtabellen `ccap_attempt_answer`, `ccap_exam_session_question`, `ccap_collection_item`, `ccap_grant_event`, `ccap_assignment_file`, `ccap_assignment_grade`; mit Personenbezug im Kontext `ccap_audit_log`, `ccap_group` (Owner), `ccap_outbox_event`/`ccap_outbox_delivery` (nur `user_ref`), Pro: `ccap_source_saga`. Export/Eraser-Regeln je Tabelle folgen in R0-17/R0-31.

---

## 5. Revisionsmodell (ADR-003)

| Objekt | Mechanik | Unveränderlich ab | Referenziert von | Quelle |
|---|---|---|---|---|
| **Frage** | `ccap_question` (Identität) + `ccap_question_revision` (Inhalt). Ein neuer `content_hash` erzeugt eine neue Revision, die alte wird `deprecated`. Ein bekannter Hash reaktiviert die alte Revision (Import-Rollback). Redaktionelle Änderung einer veröffentlichten Revision = **neue Revision**. `current_revision_id` zeigt auf die auslieferbare Revision. | `editorial_status = published` (danach ist nur der Wechsel nach `deprecated` erlaubt) | `ccap_attempt`, `ccap_exam_session_question`, `ccap_question_tag`, `ccap_answer`, `ccap_content_media` | INV-C-111, `EntityImporter.php:215-286` |
| **Antwortoptionen, Tags, Medienbezug** | hängen an der Revision, werden nie in place geändert | mit der Revision | Session-Fragen (`answer_order`, `given_answer` = Answer-IDs) | LB `answer.revision_id` |
| **Prüfungsregel** | `ccap_exam_rule` mit `version`; Änderung nach Veröffentlichung = neue Version, alte wird `retired` | `status = published` | `ccap_exam_session.rule_id` (RESTRICT) | LB `course_version` unique |
| **Kurs** | Editieren im CPT (WP-Revisionen nur als Editor-Komfort). **Veröffentlichen** schreibt `ccap_course_revision` mit `structure` (Module, Lektionen inkl. WP-Revisions-ID und Inhalts-Hash, Regel-IDs, Zugangsregeln) und `structure_hash`. | Einfügen (nur `status` ändert sich danach) | `ccap_exam_session`, `ccap_course_progress`, `ccap_certificate` | neu (LB hat kein Pendant) |
| **Prüfungsversuch-Snapshot** | Beim Start werden geschrieben: `snapshot` (Regel inkl. Blueprints, Zeitlimit, Bestehensregel, Feedback-Regel, Seed), alle `ccap_exam_session_question` (Position, `question_id`, `revision_id`, `answer_order`) und `snapshot_hash`. Bewertung nur gegen Snapshot und Revisionen, nie gegen den Live-Stand. | Start (Struktur); Abgabe/Auswertung (Antworten, Ergebnis) | Zertifikat (`evidence_id`) | LB `rule_snapshot` (`ExamService.php:263-276`) + `exam_session_question.revision_id` |
| **Zertifikat** | eine Zeile je Revision; `corrected` = neue Zeile mit `revision_no+1`, `supersedes_id` und neuem Snapshot (die alte Zeile wird `corrected`). `revoked` setzt Status, Akteur, Grund und Zeit (35.4). `snapshot` hält die ausgegebenen Angaben (Name wie gedruckt, Kurstitel, Datum, Ergebnis), damit spätere Profil-/Kursänderungen den Nachweis nicht ändern. | Ausstellung | Verifikation (Token-Hash) | neu |
| **Smarttrainer-Lernstand** | `algorithm_version` in `ccap_question_progress` und `ccap_attempt`. Eine neue Version wirkt nur auf neue Antworten; es gibt keine stille Neubewertung (ADR-005). | – | – | Golden Master R0-05 |

**Schutz der Unveränderlichkeit:** Repository-Ebene (keine Update-Methoden für eingefrorene Spalten) plus Tests (Golden Master: Autorenänderung nach Start ändert kein Ergebnis, TODO „Unveränderliche Prüfungs-Snapshots nachweisen“). DB-Trigger sind verworfen, weil sie das `TRIGGER`-Recht brauchen und das auf Shared Hosting oft fehlt. `snapshot_hash` erlaubt eine nachträgliche Integritätsprüfung.

**Laufende Kurse (Beschluss E-103…E-107):** Neue Kursrevisionen rollen vorwärts mit Besitzstand (E-103); harter Bestandsschutz nur über eine neue Kursausgabe. Änderungen werden über eine nicht öffentliche Staging-Kopie erst mit „Kurs veröffentlichen“ sichtbar (E-104). Details zu Fortschritt bei gelöschten/umsortierten Lektionen: R0-23. Das Datenmodell trägt das über `course_revision_id`/`completed_revision_id` in `ccap_course_progress`.

---

## 6. Migrationsregeln (Vorgabe für R0-37, R1-02, R1-40)

### 6.1 Schema-Versionierung

- `course_captain_db_version` = Kern-Schemaversion (semver) **plus** Hash der Moduldefinitionen. So machen Pro-/Adaptertabellen den Stand nachvollziehbar (Pass-Muster: `wpcap_db_version`, `Installer.php:682-689`, R0-36 §2.2).
- `course_captain_schema_fingerprint` (billig, autoload) = Hash aus Plugin-Version und Moduldefinitionen; nur für den Frontend-Vergleich.
- Abgeschlossene nummerierte Schritte und Backfill-Cursor stehen in der Tabelle `ccap_migration_step` (R0-37 §4.3); die früheren Optionen `course_captain_migrations_applied`, `course_captain_backfill_state`, `course_captain_migration_failed` entfallen. Billige autoload-Signale: `course_captain_db_version`, `course_captain_schema_fingerprint`, `course_captain_schema_compat_min` (Downgrade-Grenze nach E-90), `course_captain_backfill_open`.
- Ist das Schema neuer als der Code, gibt es keine Aktion, nur einen Admin-Hinweis und einen CLI-Fehler (LB `schemaIsNewer`, INV-C-029; Downgrade ohne Restore wird nicht unterstützt).

### 6.2 `dbDelta` vs. eigene Migrationen

| Art der Änderung | Werkzeug | Regel |
|---|---|---|
| Tabelle/Spalte/Index **hinzufügen**, Typ verbreitern | `dbDelta` (deklarativ, LB `Migrator::migrate`) | danach **Drift-Check** gegen `information_schema` inkl. Index-Spaltenfolge (INV-C-033). Fehlt etwas, folgt `RuntimeException` ohne Versionswechsel. |
| Fremdschlüssel | eigener idempotenter Schritt nach `dbDelta` (INV-C-036, Name ≤ 64 Zeichen, sonst md5) | Ein Fehlschlag ist nicht fatal und wird in `course_captain_fk_issues` sowie Health gemeldet. |
| Umbenennen, Löschen, Typ verengen, Unique **nachträglich** hinzufügen, Datenumzug | nummerierte PHP-Schritte `<modul>:NNNN_<name>` | idempotent (prüft Ist-Zustand), protokolliert in `ccap_migration_step`. Bei Unique erst Duplikate bereinigen bzw. melden (Dry-run), dann `ALTER`. |
| Daten-Backfill | Backfill-Job | siehe 6.3 |

`dbDelta` kann keine Spalten löschen oder umbenennen und keine FKs anlegen. Deshalb gilt **Expand/Contract:** neue Spalten sind `NULL`-fähig oder haben einen Default. Der Code einer Version N muss das Schema von N+1 lesen können. Entfernt wird erst in einem späteren Release (frühestens N+2), nachdem der Backfill abgeschlossen und gemeldet ist, und nie automatisch: nur nach Admin-Freigabe mit Backup-Bestätigung oder per CLI-Flag (Beschluss E-172).

### 6.3 Backfill

- Batches (Startwert 500 Zeilen, wie LB CLI `runAll`; Admin/Cron 200) mit Cursor auf `id` in `ccap_migration_step.cursor`. Idempotent (`WHERE neue_spalte IS NULL` bzw. Hash-Vergleich). Wiederaufnahme nach Abbruch; Heartbeat; ein Lauf gleichzeitig (Lease `ccap_lease` mit Fencing, R0-21).
- Die **Schemaversion** wird gesetzt, sobald die Struktur steht. Der **Backfill-Status** ist getrennt. Funktionen, die neue Daten brauchen, prüfen das Backfill-Gate (`course_captain_backfill_open`, R0-37 §3.3) und laufen bis dahin auf dem alten Pfad (Pass deckt nicht-additive Backfills nicht ab; R0-36 §2.5).
- Kandidaten: `ccap_course_progress` (aus Detailtabellen neu aufbaubar), spätere Grant-Revisionen, `algorithm_version`-Einführung (nur Kennzeichnung, keine Neubewertung).
- Dry-run-Modus für Backfills mit Zählbericht (WP-CLI `wp course-captain migrate --dry-run`, R0-19 §4.7).

### 6.4 Trigger und Sperren (Pass-Captain-Muster, R0-36 §2.2/2.5, plus LB)

1. **Admin (`admin_init`), WP-Cron und WP-CLI** vergleichen `course_captain_db_version` voll und migrieren.
2. **Frontend/REST** vergleicht nur `course_captain_schema_fingerprint`. Bei Abweichung wird **einmalig** ein Cron-Ereignis `ccap_migrate` geplant (R0-37 §2). Nur wenn es > 300 s überfällig ist, führt **ein** Frontend-Request das Upgrade aus (Pass `OVERDUE_AFTER`).
3. **Nicht wartendes `GET_LOCK(name, 0)` je Site.** Name = Hash aus `DB_NAME | $wpdb->prefix | 'schema-migration'` (LB `NamedLock::dbName`, Pass `:771-775`). Wer die Sperre nicht bekommt, überspringt und arbeitet mit dem alten Schema weiter. Zusätzlich hält jeder Lauf die Lease `schema-migration` in `ccap_lease` (TTL 1 800 s, Fencing); bei `GET_LOCK = NULL` schützt die Lease allein (R0-37 §3.1).
4. **Doppelprüfung im Lock:** Version direkt aus der DB am Options-Cache vorbei lesen (Pass `stored_db_version()`).
5. Während einer fälligen Migration bekommen schreibende Anfragen an `course-captain/v1` 503 mit `Retry-After: 30` (LB `Maintenance`, INV-C-054).
6. Fehlschlag: Schritt `failed` in `ccap_migration_step`, Backoff 15 min, Admin-Hinweis mit CLI-Befehl, Health `migration=false`; Schreibzugriffe bleiben bis zur Behebung gesperrt, Lesen weiter möglich (Beschluss E-175).
7. Aktivierung: direkt migrieren. Bei Fehler folgt `wp_die` **vor** dem Einplanen der Cron-Ereignisse (LB T146).

### 6.5 Rollback-Grenzen

- **Keine Rückmigration** von Schema N+1 auf N (LB und Pass ebenso). Ein Plugin-Downgrade auf älteren Code ist nur innerhalb des Expand-Fensters sicher (6.2).
- **Rollback = Restore von DB und Dateien als zusammengehöriger Stand**, keine selektive Zusammenführung mehrerer Sicherungen (Pass-Runbook, R0-36 §2.3; Restore-Check LB `BackupCheck`, INV-C-060…063). Details in R0-16.
- Content-Rollback ist **fachlich** und kein Schema-Rollback: Ein neuer Import aus der Paketkopie reaktiviert bekannte Fragen-Hashes (INV-C-097/111).
- Destruktive Contract-Schritte werden im Release-Changelog markiert, brauchen ein vorheriges Backup und laufen nie automatisch (Beschluss E-172). Downgrade um eine Minor ohne Restore nur ohne Contract-Schritt (E-90).

### 6.6 Multisite-Scope

- Tabellen **je Site** über `$wpdb->prefix`; Optionen, Locks, Cron und Fingerprint je Site. Nutzer-IDs gelten nur innerhalb der Site (Kap. 37), Grants sind sitegebunden (35.2).
- Aktivierung je Site. Netzwerkaktivierung blockiert wie LB (INV-C-058 „Netzwerk-Admin abgelehnt“; Beschluss E-49). `wpmu_drop_tables` löscht abhängige Tabellen zuerst (INV-C-007). Health meldet verwaiste Tabellen gelöschter Sites (INV-C-008). Uninstall läuft je Site mit `switch_to_blog` (INV-C-024).
- Wird ein Konto netzweit gelöscht, läuft der Eraser in allen Sites mit Tabellen (INV-C-148/159).
- Ein Upgrade im Netzwerk läuft **pro Site lazy** (Trigger 6.4 je Site), nicht als Netzwerk-Schleife in einem Request. Für die CLI gibt es eine Schleife über `--url`/`wp site list` (R0-37).

---

## 7. Ausgeschlossene Quelltabellen

| LB-Tabelle (`Schema.php:Zeile`) | Grund | Neutraler Ersatz |
|---|---|---|
| `navigation_task` (:233), `navigation_task_course` (:246), `navigation_question` (:254), `navigation_progress` (:599) | Navigationstrainer, fachgebunden (Kap. 26.2; INV-C-040/044) | keiner; neutraler Fragetyp „Zahl mit Toleranz“ in **R1 Free** als `question_type` + `settings` (Beschluss E-22, ohne Code aus NavigationCheck) |
| `praxis_task` (:267), `praxis_progress` (:610) | Praxisaufgaben je Prüfungskategorie (INV-C-040/044; Spalten `exam_reference`, `category`) | **Mechanik** Karteikarte (E-21) als `question_type` in `ccap_question_revision` + Lernstand in `ccap_question_progress`; keine eigene Tabelle |
| `radio_scenario` (:340), `radio_text` (:362), `radio_vocabulary` (:381), `radio_progress` (:629) | Funktraining (INV-C-040/044) | Vokabel-Muster „3 × in Folge“ geht in den Karteikarten-Modus (E-21) |
| `device` (:397), `device_lesson` (:407), `device_step` (:421) | Gerätesimulation (INV-C-040) | keiner |
| `exam_committee` (:696), `exam_guide` (:720) | Prüfungsausschüsse und amtliche Prüfungsanmeldung (INV-C-040, Inventur A §2.6) | keiner |
| `product_access` (:680) | Woo-Produktzuordnung: kein Core (Konzept 47, INV-C-045) | Adaptertabelle im Pro-Schema (R3-02, E-34) |
| `mobile_session` (:772), `mobile_export` (:813), `mobile_web_code` (:830) | App-API nicht in R1 (E-05, E-C-04) | keiner; der Datenschutz-Export nutzt die WP-Core-Werkzeuge (INV-C-156) |

**Summe:** 19 von 55 Quelltabellen sind ausgeschlossen (15 fachlich, 1 R3, 3 App-API). `mobile_idempotency` und `mobile_rate_limit` werden neutral übernommen (E-50/E-51). Die übrigen 36 (einschließlich dieser beiden) werden übernommen, neutralisiert oder zusammengelegt (`module`+`video_module` → CPT `ccap_module`; `video_lesson`+`video_lesson_step` → CPT `ccap_lesson`).

**Ausgeschlossene Spalten in übernommenen Tabellen:** `entitlement.{order_id, order_item_id, product_id, variation_id, sku_snapshot, feature_code, idempotency_key}`, `learning_group.{order_id, order_item_id, product_id, scopes}`, `exam_rule.{verified, verified_by, verified_at}`, `question.{official_number, content_role, source_module}`, `question_revision.{source_catalog, source_page, source_question_id, topic, subtopic, competency, smart_category, scope}` (diese fünf Felder → `ccap_question_tag`), Default `source_system='boatingschool'`.

---

## 8. Datenmengen-Annahmen für R0-14

Basis aus **Konzept 22.3** (Ziel, kein Messwert): 10 000 Lernende, 100 Kurse, 100 000 Fragen. **Alle abgeleiteten Werte sind Annahmen (A-xx)** mit offengelegter Formel; der Auftraggeber hat sie als Messbasis bestätigt (Beschluss E-52), R0-14/R1-22 messen dagegen. Belegte Grenzwerte aus LB stehen ausdrücklich dabei.

| ID | Größe | Annahme | Ergebnis (Zeilen) | Relevanz |
|---|---|---|---|---|
| A-01 | `ccap_question` | 22.3 | 100 000 | Auslieferungs-SQL, Admin-Liste paginiert |
| A-02 | `ccap_question_revision` | Ø 1,5 Revisionen je Frage | 150 000 | `publish`-Index |
| A-03 | `ccap_answer` | Ø 4 je Revision | 600 000 | Batch-Load je 500 (INV-A-026) |
| A-04 | `ccap_question_tag` | Ø 3 je Revision | 450 000 | Filter |
| A-05 | `ccap_media` / `ccap_content_media` | Ø 0,5 Bild je Revision | ≈ 75 000 | sha256-Dedupe |
| A-06 | CPT `ccap_module` / `ccap_lesson` | Ø 20 Module je Kurs, Ø 10 Lektionen je Modul | 2 000 / 20 000 (+ WP-Revisionen, begrenzen) | `wp_posts`-Wachstum |
| A-07 | `ccap_grant` | Ø 2 Kurse je Lernende | 20 000 | effektiver Zugang je Anfrage |
| A-08 | `ccap_grant_event` | Ø 3 Ereignisse je Grant | 60 000 | Historie |
| A-09 | `ccap_attempt` | Ø 2 000 Lernantworten je Lernende | **20 Mio.** (größte Tabelle) | Index `user_question`, `user_created`; Rohantworten 2 Jahre nach Kursende (E-51) |
| A-10 | `ccap_question_progress` | Ø 800 bearbeitete Fragen je Lernende | 8 Mio. | Fälligkeitsauswahl `user_due` |
| A-11 | `ccap_exam_session` | Ø 15 Prüfungsversuche je Lernende | 150 000 | Ablauf-Cron `status_deadline` |
| A-12 | `ccap_exam_session_question` | Ø 30 Fragen je Versuch | 4,5 Mio. | Start-TX schreibt 30 Zeilen |
| A-13 | `ccap_activity_progress` | 20 000 Grants × 200 Lektionen × 50 % begonnen | 2 Mio. | „Mein Lernen“ |
| A-14 | `ccap_course_progress` | = Grants | 20 000 | Dashboard ohne Scan |
| A-15 | gleichzeitige Prüfungen | **500** laufende Sitzungen (Beschluss E-52) | – | Lock `exam_start`, Zeilensperren |
| A-16 | Antwortrate | Obergrenze LB: 60 Antworten je Nutzer und Minute (`LearningService.php:40`) | – | Rate-Limit-Tabelle |
| A-17 | Importpaket | LB-Grenzen: 200 MB, 20 000 Einträge je Paket (INV-C-085); 200/500 Datensätze je Schritt, 64 MB Medien je Schritt (Inventur C §5.2) | – | Batch-Runner R0-38 |
| A-18 | `ccap_audit_log`, `ccap_import_item_log`, Inbox/Outbox | Retention: Audit 4 Jahre, Import-Log 14/180 Tage (E-44), Inbox und zugestellte Outbox 90 Tage, offene Zustellungen 30 Tage (E-116, E-215, E-258) | – | Prune-Jobs je Lauf begrenzt (LB 5 000 / 20 000) |

**Prüfaufträge an R0-14:** p95-Ziele aus 22.3 gegen A-09/A-10/A-12 messen (Smarttrainer-Auswahl, Prüfungsstart, „Mein Lernen“). Bewerten, ob `ccap_attempt` eine Archivierung oder Partitionierung braucht; Partitionierung ist **nicht** vorgesehen, weil `dbDelta` damit nicht umgehen kann. Backup-/Restore-Dauer bei dieser Größenordnung messen (R0-16).

---

## 9. Alternativen (verworfen bzw. nachrangig)

| Alternative | Vorteil | Nachteil | Bewertung |
|---|---|---|---|
| **A1 Alles in Custom Tables** (LB-Weg, Kap. 9.2 „möglich“) | harte FKs überall, ein Persistenzmodell, LB-Code direkt portierbar | eigener Editor, Revisionen, Suche, SEO, REST, Rechte; widerspricht 9.2/37 „WP-native bevorzugen“ | verworfen für Kurs/Modul/Lektion |
| **A2 Alles als CPT + Meta** (inkl. Fragen, Versuche) | maximal WP-nativ | 100 000 Fragen und 20 Mio. Antworten in `wp_posts`/`wp_postmeta`, keine Uniques/CAS, Meta-Joins langsam, Revisionen nicht unveränderlich | verworfen |
| **A3 Hybrid ohne Kursrevisionstabelle** (WP-Revisionen als Nachweis) | weniger Tabellen | WP-Revisionen kürzbar und löschbar, nicht strukturiert, kein Hash | verworfen (ADR-003) |
| **A4 Grant-Idempotenz über `idempotency_key`-Spalte** (LB) | LB-nah | doppelte Wahrheit neben `reference_scope`; in LB nicht unique (Befund 1) | verworfen; der Quell-Unique ist die Idempotenz |
| **A5 Grant je Kurs × Scope als Zeilen** (LB je Feature) | feingranularer Widerruf | Mehrfachzeilen je Quelle, komplexere Unique-Regel | verworfen nach Beschluss E-27: Modul ist eine Schlüsselspalte (`module_id`), Scopes bleiben eine Spalte |
| **A6 Queue in Optionen** (LB `lbvc_pending`) | keine Tabelle | Autoload-/Konkurrenzprobleme, kein Index, widerspricht 9.2 | verworfen |
| **A7 DB-Trigger für Unveränderlichkeit** | DB-erzwungen | `TRIGGER`-Recht oft nicht vorhanden, `dbDelta`-fremd, Backup-Tools | verworfen |
| **FK-Optionen** | A keine FKs / B nur zwischen `ccap_`-Tabellen / C auch auf WP-Tabellen | | B beschlossen (E-48) |

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
- Veröffentlichen eines Kurses bedeutet zusätzlich einen Snapshot-Schritt (`ccap_course_revision`); die Editor-UX muss „Entwurf ≠ veröffentlichte Revision“ erklären (R0-23, R1-04).
- 52 Kern-Tabellen plus Pro-Tabellen und Expand/Contract-Disziplin; das Uninstall-Manifest muss alle Tabellen und Optionen vollständig führen (LB-Lücke INV-C-026).
- `ccap_attempt` braucht den Retention-Job aus E-51 (Rohantworten 2 Jahre nach Kursende, änderbar), sonst wächst sie unbegrenzt.

**Folgearbeiten (nicht Teil dieses ADR):** exakte DDL-Dateien und Drift-Test (R1-02), Zustandsautomaten (R0-08, R0-09), Capability-Mapping je Tabelle (R0-07), Event-Envelope und Outbox-Zustellung (R0-19, ADR-006), Retention je Tabelle (R0-31), Import-Entitäten-Registry (R0-10, R0-38), Upgrade-Tests (R1-40), Race-Tests (R1-42).

---

## 11. Entscheidungen (beschlossen)

Die früheren Entscheidungsbedarfe D-1…D-6 sind im Beschlussprotokoll entschieden:

| Nr. | Frage | Beschluss | Folge in diesem ADR |
|---|---|---|---|
| D-1 | Harte Fremdschlüssel? | **E-48:** nur zwischen `ccap_`-Tabellen (Option B), nie auf `wp_posts`/`wp_users` | §2 Nr. 3, §4 FK-Spalten, §4.9 |
| D-2 | Hybrid-Speicherung | **E-47:** Hybrid | §2 Nr. 1, §3 |
| D-3 | Multisite-Umfang | **E-49:** Netzwerkaktivierung in R1 blockiert; Schema, Locks und Cron je Site (ergänzt E-07) | §6.6, R0-37 §5 |
| D-4 | Kursrevision und Fragenrevisionen | **E-50:** nur Struktur und Regeln; Fragenrevision beim Versuchsstart fixiert | §5 |
| D-5 | Aufbewahrung der Lernhistorie | **E-51:** Lernstände bis Kontolöschung, Versuche/Zertifikate 10 Jahre, Rohantworten 2 Jahre nach Kursende (änderbar, juristische Bestätigung durch Betreiber); Eraser löscht standardmäßig, pseudonymisierte Aufbewahrung nur per Einstellung (E-150) | §8, §10 |
| D-6 | Datenmengen-Ziele | **E-52:** bestätigt (10 000 Lernende, 100 Kurse, 100 000 Fragen, 500 gleichzeitige Prüfungen) | §8 |

**Weitere Beschlüsse mit Schemawirkung:** E-07 (Single-Site), E-08 (InnoDB/JSON, Voraussetzung für Indexlängen und generierte Spalten), E-17 (manuelle Gruppen), E-21 (Karteikarte statt `praxis_*`), E-22/E-69 (Fragetypen R1), E-23 (Regelstatus statt `verified`), E-25/E-25a (Bundle), E-26 (abhängige Grants), E-27 (`module_id`), E-29 (`source_revision`), E-38 (interne Outbox in Free), E-44 (Retention Audit/Grants/Import), E-168 (Aufgaben-Abgaben), E-207 (Präfix).

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

Alle Zeilen sind **eingearbeitet** (Stand 16.11.2026, Spalte „Eingearbeitet in“).

| Datum | Quelle | Änderung am Entwurf | Eingearbeitet in |
|---|---|---|---|
| 16.11.2026 | R0-10 §1, §3, §4 | `ccap_media.vector_source` entfällt; neues Feld `ccap_media.access` (`public`/`protected`); `ccap_import_batch.status` bekommt zusätzlich `activating`, `aborted` (gespeichert, nicht abgeleitet) und `superseded`. Offen für R0-23/R1-15: Wie bleiben Lektionen (CPT) bis zur Aktivierung auf dem alten Stand? | §4.1 (`ccap_media.access`, `ccap_import_batch.status`); Lektions-Staging nach E-104 (R0-23) |
| 16.11.2026 | R0-08 §8.2 | Δ-1 `ccap_source_map.uid` (UNIQUE, für jedes CPT-Objekt); Δ-2 `ccap_exam_session.mode` + `quiz`, Spalte `activity_uid`; Δ-3 Punkte als Tausendstel (`points_milli`); Δ-4 `evaluation_no`; Δ-5 `end_reason` und Statusliste `in_progress/submitted/evaluated/cancelled/broken`; Δ-6 `ccap_activity_progress.reason_code`, `rule_version`; Δ-7 Lernziele als Kurs-Meta in der Kursrevision | §4.1 (`uid`), §4.2 (`ccap_exam_session`), §4.3, §4.1 Fragetypen |
| 16.11.2026 | R0-07 §8 | Neue Tabelle `ccap_course_staff` (Kurs, Nutzer, Rolle autor/trainer, zugewiesen_von, Zeitstempel, UNIQUE Kurs+Nutzer+Rolle); Lernsperre `ccap_learner_lock` (Nutzer, Grund, Zeitraum, Audit). Platzhalter `ccap_manage_content` aus R0-05/R0-10 entspricht `ccap_edit_others_courses` bzw. Meta-Cap `ccap_edit_course` | §4.10 (`ccap_course_staff`, `ccap_learner_lock`); Caps nach E-207 `ccap_*` |
| 16.11.2026 | R0-23 §8 | Δ-23-1…8, u. a. `completed_revision_id`, `cycle_no`, Post-Status `ccap_staged`/`ccap_archived`, `end_reason = withdrawn` (Details in R0-23 §8) | §4.3 Ergänzungen |
| 16.11.2026 | R0-09 §9 | Δ-1…Δ-13 (neue Spalten für Claim-Hash, Ursprungs-Zahldatum für die Kettenordnung, Pseudonymisierung per HMAC u. a.; Details in R0-09 §9) | §4.4 (`ccap_grant`, `ccap_group`, `ccap_seat`) |
| 16.11.2026 | R0-19 §8 | `ccap_request_idempotency` wird feste Tabelle; neue Tabelle `ccap_outbox_delivery` (eine Zeile je Empfänger, Backoff, Dead-Letter nach 8 Versuchen); Spalten von `ccap_outbox_event` ergänzt (`causation_id`, `privacy_tags`) | §3.5, §4.6, §4.7 |
| 16.11.2026 | R0-15 §2.3 | Neue Tabelle `ccap_accommodation` (Lernender, Scope Site/Kurs/Regel, Zeitfaktor oder unbegrenzt, gesetzt_von, Zeitraum; nur mit Cap `ccap_manage_accommodations` lesbar). Datenschutzklasse besonders schützenswert (R0-17) | §4.10 (`ccap_accommodation`) |
| 16.11.2026 | R0-21 §5 | Neue Tabelle `ccap_lease` (Besitzer, Fencing-Token, Heartbeat, Ablauf) für mehrstufige Läufe; UNIQUE über generierte Spalten, wo NULL-Semantik stört; Δ-21-1…8 (Details in R0-21) | §4.10 (`ccap_lease`), §4.2/§4.4 (generierte Spalten) |
| 16.11.2026 | R0-13 §8 | `ccap_audit_log` bekommt `actor_type`, `subject_user_id`, `reason_code`, `correlation_id`, `request_id`, `row_hash` (Hash-Kette); Reset-Journal für Fortschritts-Reset (30 Tage); `end_reason = support_void` | §4.7 (`ccap_audit_log`); Reset-Journal siehe Offene Punkte |
| 16.11.2026 | R0-37 §8 | Neue Tabelle `ccap_migration_step` (Schritt, Status pending/running/failed/done, Cursor, Fencing-Token, Fehler, Zeitstempel); Option `course_captain_schema_compat_min`; Cron-Hook `ccap_migrate` statt `ccap_run_upgrade`; Δ-37-1…8 | §4.10, §6 |
| 16.11.2026 | R0-38 §8 | Neue Tabelle `ccap_import_issue` (Batch, Objekt-ID, Datei, JSON-Pointer, Regel, Schwere, Fehlerklasse, Meldung, Vorschlag; max. 100k je Batch); `ccap_source_map.last_batch_id`, `last_hash`; Medien: eine Datei je SHA-256, eine Medienzeile je Herkunft | §4.1 (`ccap_source_map`, `ccap_media`), §4.10 (`ccap_import_issue`) |
| 16.11.2026 | R0-40 Δ40-1, E-27 | Grant-Ziel `module_id bigint unsigned NOT NULL DEFAULT 0` (0 = ganzer Kurs), weil UNIQUE mehrere NULL erlaubt; neue Tabelle `ccap_source_saga` (Zwischenstand Voucher-/Woo-Abläufe) | §2 Nr. 4, §4.4 (`module_id`), §4.10 (`ccap_source_saga`) |

## Offene Punkte nach Einarbeitung

1. **Präfix der Fehlercodes:** E-207 nennt CPTs, Caps, Rollen, Cron, Meta und Tabellen, aber keine Fehlercodes; ENTSCHEIDUNGEN §19 und R0-19 führen Fehlercodes als `cc_*`, R0-30 verwendet `ccap_setting_invalid`. Hier bleiben Fehlercodes `cc_*`; Klärung vor R1-01 (zusammen mit der E-207-Richtlinienprüfung).
2. **Post-Status und Health-Kennungen:** `ccap_staged`/`ccap_archived` und Site-Health-Test-IDs wurden analog zu E-207 mit `ccap_` benannt; E-207 nennt Post-Status nicht ausdrücklich.
3. **E-26 Abhängigkeitsziel:** Umgesetzt als Abhängigkeit vom effektiven Zugang zum **Basiskurs** (`depends_on_course_id`), nicht von einem einzelnen Grant (R0-04 K-04 nannte `depends_on_grant`). Das folgt dem Beschlusstext „läuft so lange wie der Basiskurs“; Bestätigung durch Architektur/Auftraggeber.
4. **E-27 Text vs. Schema:** Der Beschluss nennt „`module_id` NULL = ganzer Kurs“; umgesetzt ist `NOT NULL DEFAULT 0` (0 = ganzer Kurs), weil UNIQUE mehrere NULL zulässt (R0-40 Δ40-1). Δ40-1 nennt zusätzlich `group_source`; hier liegt `module_id` in `ccap_group_course`, weil eine Gruppe mehrere Ziele hat.
5. **E-168 Tabellen** (`ccap_assignment_*`) sind ein Erstentwurf ohne LB-Vorlage; Feinschnitt (Abgabefristen, Dateigrenzen, Bewertungsrechte) in R0-08/R0-10 und R1-46.
6. **Reset-Journal** (R0-13 §8, E-138, 30 Tage): Speicherort (eigene Tabelle oder Audit-Kontext) ist nirgends festgelegt.
7. **Bundle-Zyklen und verschachtelte Bundles** (`contains` über mehrere Stufen): nicht beschlossen; hier nur eine Stufe, Zyklen abgewiesen.
