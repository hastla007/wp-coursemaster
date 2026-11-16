# R0-02/R0-03 · Inventar Teil C – Infrastruktur, Content-Import, Querschnitt

**Status:** Inventur und Klassifikation abgeschlossen für Teil C (keine Implementierung, keine Freigabe) · **Stand:** 16.11.2026
**Bezug:** TODO R0-02, R0-03 (Vorarbeit für R0-04, R0-05, R0-06, R0-10, R0-19, R0-21, R0-27, R0-37, R0-38); Konzept Kap. 26, 47, 48; `docs/r0/R0-01-baseline.md`

## 1. Kopf

| Angabe | Wert |
|---|---|
| Bereich | `bootspruefung-learning/{bootspruefung-learning.php, uninstall.php, dropins/, src/Plugin.php, src/functions.php, src/Content, src/Infrastructure, src/Settings, src/Support, src/Audit, src/Security, src/Privacy, src/Cli, src/Extensions}` sowie `schema/v1` und die zugehörigen Tests |
| Quelle | `hastla007/learnboating`, Commit `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (nur gelesen, nicht verändert) |
| Plugin-Version der Quelle | 0.14.0, Schema-Version `1.17.0` (`Migrator::SCHEMA_VERSION`), API-Vertrag 1 (`Plugin::API_CONTRACT`) |
| Abdeckung Quellcode | **100 %**: 49 PHP-Dateien mit 12 374 Zeilen sind vollständig gelesen (Liste 1.1) |
| Abdeckung Schemas | **100 %**: 17 JSON-Schemas mit 876 Zeilen sind vollständig gelesen (Liste 1.2) |
| Abdeckung Tests | Die 14 Testdateien aus Liste 1.3 sind nach Prüfabschnitten (`echo "…"`) und Einzelprüfungen (`$check(…)`) ausgewertet. Zeilenangaben zeigen auf den Beginn des jeweiligen Abschnitts. |
| Zeilenangaben | `Pfad:Zeile` nennt die erste Zeile der Methode bzw. Konstante im genannten Commit. Pfade sind relativ zu `bootspruefung-learning/`. |

Wichtig für Golden-Master-Tests: Die Integrationstests laufen in der CI gegen ein echtes, fachgebundenes Inhaltspaket (`.github/workflows/ci.yml:178`, `PACKAGE_DIR=build/boatingschool-*`). Als Golden Master taugen deshalb nur die **Prüflogik** und die **erwarteten Zustände**. Die Fixtures müssen durch neutrale Referenzkurse ersetzt werden (Konzept 26.3 Nr. 5).

### 1.1 Gelesene PHP-Dateien (Zeilen)

| Datei | Zeilen | Datei | Zeilen |
|---|---:|---|---:|
| `bootspruefung-learning.php` | 49 | `src/Infrastructure/Requirements.php` | 93 |
| `uninstall.php` | 19 | `src/Infrastructure/Uninstaller.php` | 235 |
| `dropins/maintenance.php` | 36 | `src/Infrastructure/UpdateCheck.php` | 313 |
| `dropins/php-error.php` | 35 | `src/Settings/Settings.php` | 116 |
| `src/Plugin.php` | 562 | `src/Support/AccessDenied.php` | 12 |
| `src/functions.php` | 69 | `src/Support/AfterResponse.php` | 93 |
| `src/Content/Import/EntityImporter.php` | 756 | `src/Support/DomainException.php` | 34 |
| `src/Content/Import/ImportService.php` | 752 | `src/Support/Mail.php` | 117 |
| `src/Content/Import/ImportSources.php` | 389 | `src/Support/NotFound.php` | 12 |
| `src/Content/Import/Retention.php` | 171 | `src/Audit/AuditLog.php` | 28 |
| `src/Content/Media/MediaStorage.php` | 606 | `src/Security/Capabilities.php` | 38 |
| `src/Content/Package/Package.php` | 464 | `src/Security/Hardening.php` | 207 |
| `src/Content/Package/SchemaValidator.php` | 261 | `src/Security/KnownDevice.php` | 123 |
| `src/Content/Repositories/ContentCache.php` | 103 | `src/Security/LoginGuard.php` | 504 |
| `src/Content/Repositories/ContentRepository.php` | 269 | `src/Privacy/AccountDeletion.php` | 629 |
| `src/Content/Repositories/Overrides.php` | 116 | `src/Privacy/NetworkAccounts.php` | 94 |
| `src/Content/RightsRelease.php` | 99 | `src/Privacy/PersonalData.php` | 507 |
| `src/Infrastructure/Autoloader.php` | 26 | `src/Privacy/SelfService.php` | 237 |
| `src/Infrastructure/BackupCheck.php` | 321 | `src/Cli/Commands.php` | 1 123 |
| `src/Infrastructure/Cron.php` | 64 | `src/Extensions/Extensions.php` | 101 |
| `src/Infrastructure/Database/Migrator.php` | 400 | `src/Extensions/Tickets.php` | 241 |
| `src/Infrastructure/Database/Schema.php` | 933 | `src/Extensions/Vouchers.php` | 417 |
| `src/Infrastructure/Lock.php` | 138 | `src/Infrastructure/Monitor.php` | 137 |
| `src/Infrastructure/Maintenance.php` | 84 | `src/Infrastructure/NamedLock.php` | 118 |
| `src/Infrastructure/RequestMemo.php` | 123 | | |

### 1.2 Gelesene JSON-Schemas `schema/v1` (Zeilen)

`common` 17 · `manifest` 98 · `courses` 35 · `modules` 33 · `questions` 95 · `media` 29 · `exam_rules` 59 · `exam_papers` 33 · `navigation_tasks` 86 · `praxis_tasks` 31 · `video_modules` 65 · `radio_texts` 32 · `radio_vocabulary` 24 · `radio_practice` 58 · `device_simulation` 54 · `exam_committees` 32 · `exam_guides` 95 (jeweils `*.schema.json`)

### 1.3 Ausgewertete Tests (Zeilen)

| Datei | Zeilen | Bezug zu Teil C |
|---|---:|---|
| `tests/integration/smoke-test.php` | 145 | Aktivierung, Idempotenz, Fremdschlüssel, Datenschutz, Deinstallation |
| `tests/integration/uninstall-phase.php` | 153 | Datenlöschung bei Deinstallation |
| `tests/integration/operations-test.php` | 805 | Locks, Cron, Migration, Retention, Cache, Vertrag, Wartung, Mail, Monitor, BackupCheck |
| `tests/integration/release-ops-test.php` | 242 | UpdateCheck (und SiteHealth aus Teil D) |
| `tests/integration/security-test.php` | 774 | Ablage, Medientypen, Login, Passwort vergessen, Härtung, `insideWebroot` |
| `tests/integration/multisite-test.php` | 156 | Aktivierung je Website, `wpmu_drop_tables`, verwaiste Tabellen, Deinstallation |
| `tests/integration/multisite-setup.php` | 76 | Vorbereitung Multisite |
| `tests/integration/content-flow-test.php` | 566 | Import, Reimport, Rollback, Overrides (plus fachliche Teile) |
| `tests/integration/admin-test.php` | 1 336 | Import-Zustände, Teilschritte, Ablageordner, Link-Import, Rechtefreigabe |
| `tests/integration/account-test.php` | 502 | Kontolöschung, Export, Multisite-Zweige |
| `tests/integration/extensions-test.php` | 530 | Gutschein-, Ticket- und Praxis-Schnittstellen |
| `tests/integration/membership-test.php` | 1 360 | nur NamedLock (450–463), `entitlementExport` (1339), `releaseExamRules` (879) |
| `tests/integration/support/guard.php`, `support/standalone.php` | 13 / 43 | Testschutz (nur Test-DB), eigenständiger Lauf |
| `tests/e2e/admin-import.mjs` | 66 | Browserablauf Paket-Import |

## 2. Funktionstabelle

Legende **Art:** UI · PHP · Daten · API (REST/Hook/CLI) · Cron · Import · Test · Commerce.
**Klassifikation:** `reuse` = Verhalten 1:1 neutral übernehmen (neue Namen) · `abstract` = generischen Kern herauslösen, Fachbezug entfernen · `replace` = anderes Verfahren nötig, Muster bleibt Vorbild · `exclude_domain_specific` = fachgebunden, entfällt (Kap. 26.2).
**Zielnamen** folgen Kap. 26.2: Präfix `cc_`/`course_captain_`, REST `course-captain/v1`, CLI `wp course-captain`.

### 2.1 Bootstrap, Lifecycle, öffentliche PHP-API

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden-Master-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-C-001 | `bootspruefung-learning.php:1` | PHP | Plugin-Kopf, PHP-Guard (`:27`), Composer- oder Fallback-Autoload (`:35`), Aktivierungs- und Deaktivierungs-Hooks (`:43`), Konstanten `BP_LEARNING_*`, `Plugin::boot` | abstract | Der Ablauf ist neutral. Kopfzeilen (Beschreibung mit SBF/SRC…, `WC requires`) sind fachlich bzw. Woo-gebunden. | Bootstrap `course-captain.php` | R1-Free | R1-01, R0-20, R0-35 | smoke-test.php:49 |
| INV-C-002 | `src/Infrastructure/Autoloader.php:14` | PHP | PSR-4-Fallback ohne `vendor/` | reuse | generisch | `CourseCaptain\Infrastructure\Autoloader` | R1-Free | R1-01 | – (indirekt jeder Test) |
| INV-C-003 | `src/Plugin.php:37` | PHP | Singleton `boot`/`instance`, Fabriken `migrator()` (`:61`) und `importService()` (`:68`) | reuse | generisch | Plugin-Kernel/Service-Container | R1-Free | R1-01 | operations-test.php:327 |
| INV-C-004 | `src/Plugin.php:75` | PHP | `activate`: Netzwerkaktivierung blockiert, `Requirements`, Capabilities, Rewrite, Migration (bei Fehler `wp_die`, ohne Cron-Reste), Schedules, `AutoReconcile::activated`, FAQ-, Kunden-FAQ- und Tipps-Startpakete | abstract | Der Kern-Lebenszyklus ist neutral. Woo-Abgleich und fachliche Startinhalte (FAQ-Rubriken, Tipps) gehören nicht in den Kern. | Lifecycle-Service mit Modul-Registry für Aktivierungsschritte | R1-Free (Woo-Teil R3) | R1-01, R1-39 | smoke-test.php:49, :69; multisite-test.php:71 |
| INV-C-005 | `src/Plugin.php:114` | PHP | `deactivate`: Cron austragen, eigene Rewrite-Regeln und Endpunkte vor dem Flush abmelden, keine Datenlöschung | reuse | generisch (Kap. 8 „sichere Deaktivierung“) | Lifecycle `deactivate()` | R1-Free | R1-01, R1-23 | operations-test.php:631 |
| INV-C-006 | `src/Plugin.php:133`, `:144` | Cron | `CRON_HOOKS` und `clearCron()`: alle eigenen WP-Cron-Ereignisse (auch einmalige und solche mit Argumenten) plus Action Scheduler | reuse | generisch | zentrale Cron-Registry `cc_cron_hooks` | R1-Free | R1-17, R1-23 | operations-test.php:646, :657 |
| INV-C-007 | `src/Plugin.php:160` | PHP | `dropTablesOnSiteDelete` (Filter `wpmu_drop_tables`, abhängige Tabellen zuerst) | abstract | neutral, aber Multisite-Umfang offen | Multisite-Adapter | R1-Free (nur bei Multisite-Freigabe) | R0-25, R1-23 | multisite-test.php:103; uninstall-phase.php:143 |
| INV-C-008 | `src/Plugin.php:176` | PHP | `orphanSiteTables`: Plugin-Tabellen gelöschter Websites finden | abstract | wie INV-C-007 | Multisite-Diagnose | R1-Free (bedingt) | R0-25, R1-38 | multisite-test.php:112 |
| INV-C-009 | `src/Plugin.php:200`, `:206`, `:218` | Commerce | „Mein Konto“-Endpunkte `mein-lernen`/`meine-daten` mit WooCommerce-Maske eintragen und abmelden | replace | Free braucht einen eigenen Lernbereich ohne WooCommerce. Woo-Endpunkte gehören in den Woo-Adapter. | Lernbereich-Router (Free) + Woo-„My Account“-Adapter | R1-Free / R3 | R1-16, R3-02 | operations-test.php:659, :667 |
| INV-C-010 | `src/Plugin.php:231` | API | `registerHooks()`: zentrale Verdrahtung aller Module (vollständige Liste in Abschnitt 4.1) | abstract | Fachmodule sind fest verdrahtet (Charts, Tips, FAQ, Disputes, Theme-Filter). Nötig ist eine Modul-Registry. | Modul-Registry mit Feature-Flags | R1-Free | R1-01, R0-19 | – |
| INV-C-011 | `src/Plugin.php:269` | API | Filter `kurswasser_consent_external_hosts` (Videohosts für das Consent-Banner des Theme) | replace | theme- und fachgebunden | neutraler Filter `course_captain_external_media_hosts` | R1-Free | R0-10, R1-07 | – |
| INV-C-012 | `src/Plugin.php:290` | Cron | `ensureSchedules()`: fehlende Läufe neu einplanen (init, Priorität 30; Selbstheilung nach Umzug oder Restore) | reuse | generisch | Cron-Selbstheilung | R1-Free | R1-17, R1-23 | operations-test.php:502 |
| INV-C-013 | `src/Plugin.php:305` | Cron | `hourly()`: isolierte Teilaufgaben `exam_expire`, `entitlement_expire`, `group_invites_expire`, `expiry_reminder_schedule`, `order_reconcile`, `import_retention` sowie der Zeitstempel für den Monitor | abstract | Das Isolationsmuster ist neutral. Die Aufgabenliste ist hartcodiert und enthält Woo-Abgleich. Module sollen sich selbst registrieren. | Wartungs-Scheduler mit registrierbaren Tasks | R1-Free (`order_reconcile` R3) | R1-17 | operations-test.php:80 |
| INV-C-014 | `src/Plugin.php:330` | PHP | `assetVersion()`: Version plus `filemtime` als Cache-Buster | reuse | generisch | Asset-Versionierung | R1-Free | R1-01 | operations-test.php:371 |
| INV-C-015 | `src/Plugin.php:338` | PHP | Textdomain laden | reuse | generisch (neue Domain `course-captain`) | i18n-Bootstrap | R1-Free | R1-19 | – |
| INV-C-016 | `src/Plugin.php:361` | PHP | `maybeMigrate()`: Downgrade-Hinweis (`schemaIsNewer`), Entscheidung über `migrationAction`, Planen oder Ausführen | reuse | generisch, ausgereift | Upgrade-Trigger | R1-Free | R0-37, R1-02, R1-40 | operations-test.php:128, :371 |
| INV-C-017 | `src/Plugin.php:398`, `:415`, `:423` | PHP | `isCustomerAdminRequest` (admin-ajax/admin-post), `isBackgroundRequest`, `backgroundContext` (reine Funktion) | reuse | generisch | Request-Kontext-Service | R1-Free | R1-02, R1-22 | operations-test.php:136, :604 |
| INV-C-018 | `src/Plugin.php:436`, `:452`, `:467`, `:480` | Cron | `migrationAction` (`run`/`schedule`/`wait`), `retryAt` (Backoff 15 min), `scheduleMigration`, `cronMigration` | reuse | generisch; die reine Entscheidungsfunktion lässt sich gut testen | Migrations-Orchestrator | R1-Free | R0-37, R1-40 | operations-test.php:128 |
| INV-C-019 | `src/Plugin.php:493` | PHP | `runMigration()`: Fehlschlag in Option merken, neu planen, Admin-Hinweis | reuse | generisch | Migrations-Orchestrator | R1-Free | R1-40 | operations-test.php:156 |
| INV-C-020 | `src/Plugin.php:523`, `:537` | UI | `migrationNotice`/`migrationNoticeArgs`: Hinweis nur für `manage_options`, mit CLI-Befehl, technische Meldung nur aufklappbar | abstract | Texte und Admin-Slug sind marken- bzw. fachbezogen | Admin-Notice „Upgrade fehlgeschlagen“ | R1-Free | R1-38 | operations-test.php:164 |
| INV-C-021 | `src/functions.php:14` | API | `bootspruefung_faqs()`: Theme-API für FAQs nach Rubrik | replace | Das Muster einer Theme-Template-Funktion bleibt. Rubriken (`SBF-SEE`, `SRC-UBI`) und das FAQ-Modul sind fachgebunden (Teil A). | `course_captain_*`-Template-Tags | R1-Free (nur falls Teil A ein FAQ-Modul übernimmt) | R1-28 | faq-test.php (Teil A) |
| INV-C-022 | `src/functions.php:26` | API | `bootspruefung_learning_contract()` (Version plus Vertragsversion, Filter) | reuse | generisch, entspricht dem Vertragskonzept Kap. 18 | `course_captain_contract()` | R1-Free | R0-19, R1-28, R3-11 | operations-test.php:327 |
| INV-C-023 | `src/functions.php:47` | API | `bootspruefung_learning_compatible($min, $contract)` | reuse | generisch | `course_captain_compatible()` | R1-Free | R0-19, R1-28 | operations-test.php:330 |
| INV-C-024 | `uninstall.php:1`, `src/Infrastructure/Uninstaller.php:69` | PHP | Deinstallation je Website (Multisite: `switch_to_blog`), Daten nur bei `delete_data_on_uninstall`, Nutzer-Meta nur, wenn keine Website ihre Daten behält | reuse | generisch, Opt-in-Löschung | Uninstaller | R1-Free | R1-23 | smoke-test.php:121; multisite-test.php:128 |
| INV-C-025 | `src/Infrastructure/Uninstaller.php:105` | PHP | `uninstallSite`: Cron austragen, Seekarten-Anhänge (`_bp_chart`) löschen, Tabellen und Capabilities entfernen, Tipps/FAQ/Kunden-FAQ samt Terms löschen, Optionen löschen, Shop-Meta löschen, Ordner `media`/`packages`/`protected`/Legacy entfernen | abstract | Seekarten, Tipps, FAQ und Shop-Meta sind fachlich bzw. Woo. Der Ablauf ist neutral. | Uninstaller mit Modul-Beiträgen | R1-Free | R1-23 | uninstall-phase.php:42 |
| INV-C-026 | `src/Infrastructure/Uninstaller.php:41`, `:173`, `:211` | Daten | Exakte Listen für Optionen, Transients und Nutzer-Meta statt `bp_%`-Wildcard; Objekt-Cache leeren | reuse | generisch, schützt fremde Daten. **Lücke:** `bp_entitlement_prune_last` (`PersonalData.php:119`) und `_bp_catalog_fields` (`ShopCatalog.php:301`) werden nicht gelöscht. | Uninstall-Manifest je Modul plus Vollständigkeitstest | R1-Free | R1-23, R1-36 | uninstall-phase.php:104 |
| INV-C-027 | `dropins/maintenance.php:1` | UI | Deutsche Wartungsseite (503, `Retry-After: 120`) zum Kopieren nach `wp-content/` | abstract | Text erwähnt „Prüfungssimulation“ und ist nur deutsch | Betriebsvorlage in der Doku | R1-Free (Doku) | R1-24 | operations-test.php:714 |
| INV-C-028 | `dropins/php-error.php:1` | UI | Deutsche Fehlerseite (500) ohne Fehlerdetails | abstract | wie INV-C-027 | Betriebsvorlage in der Doku | R1-Free (Doku) | R1-24 | operations-test.php:714 |

### 2.2 Datenbank, Migration, Locks, Cron, Betrieb

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden-Master-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-C-029 | `src/Infrastructure/Database/Migrator.php:45`, `:53`, `:66` | Daten | `installedVersion`, `needsMigration`, `schemaIsNewer` (nie auf ein älteres Schema zurück) | reuse | generisch | `cc_schema_version` + Migrator | R1-Free | R0-37, R1-40 | operations-test.php:382 |
| INV-C-030 | `src/Infrastructure/Database/Migrator.php:84` | Daten | `migrate()`: NamedLock (Timeout 0), Options-Lock mit Übernahme toter Sperren, `set_time_limit(0)`, InnoDB-Prüfung, `dbDelta` je Tabelle, Prüfung auf vorhandene Tabellen, Spalten und Indizes, Fremdschlüssel (nicht fatal), Version setzen | reuse | generisch, ausgereift (T135–T138, T202) | Migrator | R1-Free | R0-37, R1-02, R1-40 | smoke-test.php:69; operations-test.php:259 |
| INV-C-031 | `src/Infrastructure/Database/Migrator.php:145` | Daten | Nach der Migration Autoload-Flags für einzelne Optionen setzen (`MobileSettings`, Tipps, Widerruf) | replace | Referenziert Fachoptionen direkt | Autoload-Deklaration im Settings-Register | R1-Free | R1-31 | – |
| INV-C-032 | `src/Infrastructure/Database/Migrator.php:160` | Daten | Einmalige Datenreparatur T196 (Gruppenzugänge mit `0000-00-00`) | replace | Altlast der Quelle. Course Captain speichert NULL von Anfang an sauber. Das Muster „versionierter Backfill“ bleibt. | versionierte Backfill-Schritte | R1-Free | R0-37 | – |
| INV-C-033 | `src/Infrastructure/Database/Migrator.php:186`, `:220`, `:246` | Daten | `missingSchemaParts`, `expectedParts`, `indexColumnList`: Abgleich von Soll-Definition und `information_schema` inklusive Index-Spaltenfolge (T210) | reuse | generisch, erkennt stille dbDelta-Fehler | Schema-Drift-Check | R1-Free | R1-40, R1-38 | operations-test.php:259 |
| INV-C-034 | `src/Infrastructure/Database/Migrator.php:262`, `:283` | Daten | `tableStatus` (Engine, Zeilen), `foreignKeyCount` | reuse | generisch | Diagnose | R1-Free | R1-38 | smoke-test.php:55 |
| INV-C-035 | `src/Infrastructure/Database/Migrator.php:292` | Daten | `dropAll()` mit `FOREIGN_KEY_CHECKS=0` | reuse | generisch | Uninstaller | R1-Free | R1-23 | smoke-test.php:133 |
| INV-C-036 | `src/Infrastructure/Database/Migrator.php:307`, `:356` | Daten | `addForeignKeys` (idempotent, Fehler als Liste), `constraintName` (höchstens 64 Zeichen, sonst md5) | reuse | generisch | Migrator | R1-Free | R0-06, R1-40 | smoke-test.php:61, :76, :80 |
| INV-C-037 | `src/Infrastructure/Database/Migrator.php:366` | Daten | `assertInnoDb` | reuse | generisch | Requirements | R1-Free | R0-20 | – |
| INV-C-038 | `src/Infrastructure/Database/Schema.php:55` (`course` bis `content_media`) | Daten | Content-Kern: Kurs, Modul, Zuordnung, Frage, Revision, Antwort, Modul-Frage, Medien, Medienzuordnung | abstract | Neutraler Kern. Fachfelder entfernen oder umbenennen: `official_number`, `content_role`, `source_catalog`/`source_page`, Default `source_system='boatingschool'`. | `cc_course`, `cc_module`, `cc_question`, `cc_question_revision` … | R1-Free | R0-06, R1-04, R1-06, R1-07 | content-flow-test.php:45 |
| INV-C-039 | `src/Infrastructure/Database/Schema.php:189` (`exam_rule` bis `exam_paper_question`) | Daten | Prüfungsregel (Version, Zeitlimit, `passing_rule`, `verified`), Blueprint je Modul, feste Bögen | abstract | Blueprint und Zeitlimit sind neutral. `verified`, Gültigkeitsfenster und feste Bögen bilden amtliche Prüfungen ab und werden zu „Regelversion/Variante“. | `cc_exam_rule`, `cc_exam_blueprint`, `cc_exam_variant` | R1-Free | R1-08, R0-08 | content-flow-test.php:203 |
| INV-C-040 | `src/Infrastructure/Database/Schema.php:233`, `:267`, `:340`, `:397`, `:696`, `:720` | Daten | `navigation_task*`, `navigation_question`, `praxis_task`, `radio_scenario/text/vocabulary`, `device*`, `exam_committee`, `exam_guide` | exclude_domain_specific | Navigations-, Funk- und Gerätetrainer, Prüfungsausschüsse und Prüfungsanmeldung (Kap. 26.2) | – | exclude | – | – |
| INV-C-041 | `src/Infrastructure/Database/Schema.php:290` (`video_module` bis `video_lesson_question`) | Daten | Videokapitel, Lektion (Provider, Dauer, Transkript, Kapitel), Schritte, Quizfragen je Lektion | abstract | Neutraler Lektionstyp „Video“. `group_label` und Bindung an Kurscodes vereinheitlichen. | Activity-Typ „Video-Lektion“ | R1-Free | R1-07 | content-flow-test.php:260 |
| INV-C-042 | `src/Infrastructure/Database/Schema.php:434`, `:452`, `:465`, `:479` | Daten | `import_batch`, `import_item_log`, `audit_log`, `override` | reuse | generisch | `cc_import_batch`, `cc_import_item_log`, `cc_audit_log`, `cc_override` | R1-Free | R1-15, R1-17 | operations-test.php:196 |
| INV-C-043 | `src/Infrastructure/Database/Schema.php:490` (`attempt` bis `exam_session_question`) | Daten | Versuche (Idempotenz-Schlüssel), Antworten, Fortschritt (Mastery, `due_at`), Favoriten, Sammlungen, Prüfungssitzung (Regel-Snapshot, `start_token`) und deren Fragen | reuse | neutraler Lernkern (Verhalten in Teil A) | `cc_attempt`, `cc_progress`, `cc_exam_session` … | R1-Free | R1-08…R1-12, R0-23 | content-flow-test.php:166, :203 |
| INV-C-044 | `src/Infrastructure/Database/Schema.php:599`, `:610`, `:619`, `:629` | Daten | `navigation_progress`, `praxis_progress`, `radio_progress` / `video_progress` | exclude_domain_specific | Navigations-, Praxis- und Funkfortschritt entfallen. `video_progress` übernimmt INV-C-041. | (`cc_activity_progress` für Video) | exclude / R1-Free | R1-10 | – |
| INV-C-045 | `src/Infrastructure/Database/Schema.php:641`, `:667`, `:680`, `:733`, `:753` | Commerce | `entitlement` (Quelle, `reference_key`, Woo-Felder `order_id`/`order_item_id`/`sku_snapshot`), `access_rule`, `product_access`, `learning_group`, `learning_group_member` | abstract | Neutrale Grants, Regeln und Gruppen sind Kern. Woo-Spalten und `product_access` gehören in den Woo-Adapter (Kap. 47: keine `wc_item` im Free-Kern). | `cc_grant` mit `source_type`/`source_id`, `cc_access_rule`, `cc_group*`; Woo-Mapping R3 | R1-Free / R3 | R0-09, R1-13, R3-02 | membership-test.php (Teil B) |
| INV-C-046 | `src/Infrastructure/Database/Schema.php:772`, `:796`, `:813`, `:830` | API | `mobile_session`, `mobile_idempotency`, `mobile_export`, `mobile_web_code` (App-API `boatingschool/v1`) | abstract | Token-Hash-Sitzungen, Idempotenz und asynchroner Export sind neutral nutzbar, aber nur bei einer App-API (Entscheidung E-C-04, Teil D) | `cc_api_session` … | R3 (nach E-C-04) | R0-19, R3-01 | mobile-api-test.php (Teil D) |
| INV-C-047 | `src/Infrastructure/Database/Schema.php:847` | Daten | `mobile_rate_limit` (Bucket-Hash, festes Fenster, atomar per `INSERT … ON DUPLICATE KEY`) | reuse | generischer Rate-Limit-Speicher, auch für Web-Limits genutzt | `cc_rate_limit` | R1-Free | R0-21, R1-25 | security-test.php:279 |
| INV-C-048 | `src/Infrastructure/Database/Schema.php:871` | Daten | Fremdschlüssel-Politik: Content wird nie hart gelöscht. Lerndaten verweisen mit RESTRICT, Kindzeilen mit CASCADE, `praxis_task.media_id` mit SET NULL. | reuse | Policy ist neutral, die Liste wird mit dem neutralen Schema gekürzt | ADR Datenmodell | R1-Free | R0-06 | smoke-test.php:80 |
| INV-C-049 | `src/Infrastructure/Database/Schema.php:919` | Daten | `USER_DATA_TABLES` (Export und Löschung) | reuse | generisch | Datenschutz-Registry | R1-Free | R1-17 | account-test.php:300 |
| INV-C-050 | `src/Infrastructure/Database/Schema.php:924` | Import | Herkunftsspalten `source_system`, `source_id`, `source_hash`, `package_id`, `status`, `imported_at` | abstract | Provenienz ist neutral. Default `boatingschool` muss weg. | Provenienz-Trait | R1-Free | R0-10, R0-38 | content-flow-test.php:69 |
| INV-C-051 | `src/Infrastructure/Cron.php:24`, `:38`, `:46`, `:58` | Cron | `run`, `guard`, `recordError`, `errors`: Teilaufgaben isoliert, Fehlerprotokoll (höchstens 20) | reuse | generisch | Task-Runner | R1-Free | R1-17, R1-38 | operations-test.php:80 |
| INV-C-052 | `src/Infrastructure/Lock.php:30` | Daten | Atomare Options-Sperre: `INSERT IGNORE`, TTL-Übernahme nur bei unverändertem Wert, Freigabe nur durch den Besitzer, `activeHolder`, Altformate | reuse | generisch | `cc` Lock-Service (lange Läufe) | R1-Free | R0-21, R1-25 | operations-test.php:59 |
| INV-C-053 | `src/Infrastructure/NamedLock.php:42` | Daten | `GET_LOCK`/`RELEASE_LOCK`, wiedereintrittsfähig, `NULL` gilt als gehalten (mit Log), `toPrimary` (HyperDB), `dbName` (sha256 aus DB, Präfix und Name), `with()`, `isReal` | reuse | generisch, für Gruppenbeitritt, Prüfungsstart und Einlösung | `cc` NamedLock (kurze kritische Abschnitte) | R1-Free | R0-21, R1-42 | membership-test.php:450; content-flow-test.php:460; extensions-test.php:197 |
| INV-C-054 | `src/Infrastructure/Maintenance.php:30`, `:59` | API | `rest_pre_dispatch` (Priorität 5): schreibende Anfragen an die Lern-API bekommen während einer fälligen Migration 503 mit `Retry-After: 30`; stößt `spawn_cron` an | reuse | generisch (Namespaces neutralisieren) | Upgrade-Wartungsmodus für `course-captain/v1` | R1-Free | R1-40, R0-37 | operations-test.php:386 |
| INV-C-055 | `src/Infrastructure/Monitor.php:27`, `:66`, `:88`, `:102` | API | `GET bootspruefung/v1/monitor`: Prüfungen `database`, `migration`, `cron` (≤ 3 h), `timezone`, `maintenance` (2 h), `storage`, `foreign_keys`; Details nur mit Schlüssel oder für `manage_options` | reuse | generisch | `GET course-captain/v1/health` | R1-Free | R1-38 | operations-test.php:686 |
| INV-C-056 | `src/Infrastructure/Monitor.php:114`, `:121` | UI | Zeitzonen-Prüfung (Ortszeit mit Sommerzeit) und Admin-Hinweis („Berlin wählen“) | abstract | Der Text ist DE-zentriert und nennt Bestellmails | Locale/Zeitzonen-Diagnose | R1-Free | R0-15, R1-19 | operations-test.php:705 |
| INV-C-057 | `src/Infrastructure/RequestMemo.php:30` | PHP | Zwischenspeicher je lesender REST-Anfrage (GET/HEAD, eigene Namespaces), `scope()`, `forget()` | reuse | generisch | Request-Cache | R1-Free | R0-21, R1-25 | operations-test.php:305 |
| INV-C-058 | `src/Infrastructure/Requirements.php:16`, `:21` | PHP | Mindestversionen PHP 8.1, WP 7.0, Netzwerk-Admin abgelehnt | abstract | Die Werte entscheidet R0-20 | Requirements-Check | R1-Free | R0-20, R1-01 | operations-test.php:333 |
| INV-C-059 | `src/Infrastructure/Requirements.php:52`, `:70`, `:76` | Commerce | WooCommerce-Mindestversion 11.2, Hinweis, HPOS-Status | replace | gehört in den Woo-Adapter | Woo-Adapter-Requirements | R3 | R3-02, R3-09 | woocommerce-test.php:1898 |
| INV-C-060 | `src/Infrastructure/BackupCheck.php:32`, `:47` | PHP | Restore-Prüfung, nur lesend, Gesamtstatus `ok`/`warn`/`fail` | reuse | generisch | Restore-Check | R1-Free | R0-16, R1-23 | operations-test.php:731 |
| INV-C-061 | `src/Infrastructure/BackupCheck.php:61`, `:80`, `:105`, `:117` | PHP | Prüfungen auf Schema, Tabellen und InnoDB, Fremdschlüssel, Ordner-Geheimnisse und Settings | reuse | generisch | Restore-Check | R1-Free | R0-16 | operations-test.php:731 |
| INV-C-062 | `src/Infrastructure/BackupCheck.php:138`, `:151`, `:184` | PHP | Soll-Ordner ohne Nebenwirkung berechnen, Ablage prüfen (Hinweis auf Kopie neben dem Original, T209), aktives Paket mit Paketkopie | reuse | generisch | Restore-Check | R1-Free | R0-16 | operations-test.php:731 |
| INV-C-063 | `src/Infrastructure/BackupCheck.php:204`, `:237` | PHP | Geschützte Dateien (`_bp_protected_path`) und Medien (`storage_key`) vorhanden | reuse | generisch | Restore-Check | R1-Free | R0-16, R1-14 | operations-test.php:731 |
| INV-C-064 | `src/Infrastructure/BackupCheck.php:259` | PHP | Stichprobe Zugänge über `AccessService::can` (ohne Video-/Simulator-SKU) | abstract | SKU-Ausnahmen sind fachlich | Restore-Check „Grants“ | R1-Free | R0-16, R1-13 | operations-test.php:731 |
| INV-C-065 | `src/Infrastructure/BackupCheck.php:298` | Commerce | Bestellungen zu Zugängen vorhanden (HPOS oder Posts) | replace | Woo-Adapter | Woo-Restore-Check | R3 | R3-03 | operations-test.php:731 |
| INV-C-066 | `src/Infrastructure/UpdateCheck.php:45`, `:59`, `:87`, `:107`, `:141`, `:175`, `:183` | Cron | Opt-in-Abfrage der GitHub-Releases (höchstens täglich, https, ohne Website-Daten im User-Agent), Auswertung von Tag, Entwurf/Vorabversion und Sicherheitsmarker | replace | Free kommt über WordPress.org-Updates, Pro über den Lizenzclient. Das Muster „Opt-in, keine Website-Daten“ bleibt. | WordPress.org-Updates / Pro-Updater | R3 (Pro) | R0-35, R0-39, R3-18 | release-ops-test.php:73 |
| INV-C-067 | `src/Infrastructure/UpdateCheck.php:195`, `:209`, `:237`, `:254`, `:282`, `:294` | UI | `available`, Zeile im Systemstatus, Admin-Hinweis (Sicherheitsupdate nur vertagbar), Ausschalten verwirft das Ergebnis, Einstellungsabschnitt „Betrieb“ | replace | wie INV-C-066 | Pro-Update-Hinweis | R3 (Pro) | R3-15, R3-18 | release-ops-test.php:115, :179 |

### 2.3 Settings, Support, Audit

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden-Master-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-C-068 | `src/Settings/Settings.php:12`, `:43`, `:81` | Daten | Eine Option `bp_learning_settings` mit `DEFAULTS`-Merge, `get()` | abstract | Kap. 43 verlangt ein typisiertes Register mit Scope, Capability und Audit | Settings-Register `cc_settings` | R1-Free | R0-27, R1-31 | support/standalone.php:37 |
| INV-C-069 | `src/Settings/Settings.php:86`, `:97` | UI | `register_setting('bp_learning', …)`, `sanitize()`. Achtung: `sanitize()` baut **immer alle** Schlüssel neu aus `$input` auf, ein Teilformular setzt die übrigen Werte auf ihren Standard zurück. | abstract | Pro Feld validieren, fehlende Schlüssel erhalten | Feldverträge und Validatoren | R1-Free | R0-27, R1-31, R1-36 | admin-test.php:866 |
| INV-C-070 | `src/Settings/Settings.php:16` | Daten | `delete_data_on_uninstall` (false) | reuse | generisch | Setting „Daten bei Deinstallation löschen“ | R1-Free | R1-23 | smoke-test.php:128 |
| INV-C-071 | `src/Settings/Settings.php:18` | Daten | `radio_voice_output` (`browser`/`recording`/`off`) | exclude_domain_specific | Funktrainer | – | exclude | – | – |
| INV-C-072 | `src/Settings/Settings.php:21` | Daten | `default_access` (`logged_in`/`entitled_only`) | abstract | Zugangsmodus ohne Shop ist neutral; Begriffe angleichen | Setting „Standardzugang“ | R1-Free | R1-13, R0-27 | membership-test.php (Teil B) |
| INV-C-073 | `src/Settings/Settings.php:23` | Daten | `exam_guide_enabled` (false) | exclude_domain_specific | Prüfungsanmeldung | – | exclude | – | – |
| INV-C-074 | `src/Settings/Settings.php:26`, `:57`, `:69` | Daten | `brand_name` mit Rückfall auf den Website-Namen, dann Platzhalter `XXXX` | abstract | neutral, Platzhalterlogik ist Altlast | Setting „Anzeigename“ | R1-Free | R1-31 | admin-test.php:1320 |
| INV-C-075 | `src/Settings/Settings.php:29`, `:76` | Commerce | `price_note` („inkl. MwSt.“, PAngV) | replace | Preisangaben sind Sache des Shops bzw. Adapters | Woo-Adapter-Setting | R3 | R3-14 | – |
| INV-C-076 | `src/Settings/Settings.php:31` | Daten | `expiry_reminder_enabled` (true), `expiry_reminder_days` (14, 1–90) | abstract | Benachrichtigungen sind neutral (Teil B: ExpiryReminder) | Mail-Setting „Ablauf-Erinnerung“ | R1-Free | R1-21, R1-34 | operations-test.php:502 |
| INV-C-077 | `src/Settings/Settings.php:35` | Daten | `privacy_requests_auto` (true) | reuse | Verhalten neutral; Default ist offen (E-C-05) | Datenschutz-Setting | R1-Free | R1-34, R0-31 | account-test.php:211 |
| INV-C-078 | `src/Settings/Settings.php:37` | Daten | `video_captions` (`off`/`vtt`) | abstract | neutral, in die Video-Activity verschieben | Video-Setting | R1-Free | R1-07, R1-19 | operations-test.php:716 |
| INV-C-079 | `src/Settings/Settings.php:39` | Daten | `update_check_enabled` (false) | replace | siehe INV-C-066 | Pro-Setting | R3 | R3-15 | release-ops-test.php:73 |
| INV-C-080 | `src/Support/AccessDenied.php:10`, `src/Support/NotFound.php:10` | PHP | Fachliche Ausnahmen für 403 und 404 | reuse | generisch | Fehlerklassen | R1-Free | R0-19 | – |
| INV-C-081 | `src/Support/DomainException.php:14` | API | Stabile Fehlercodes (`exam_*`, `answer_rate_limited`, `question_token_expired`) zusätzlich zur Meldung | abstract | Muster neutral, Codekatalog neutral benennen | Fehlercode-Katalog | R1-Free | R0-19, R1-28 | mobile-api-test.php:631 |
| INV-C-082 | `src/Support/AfterResponse.php:26`, `:34`, `:62`, `:82` | PHP | Arbeit nach dem Senden der Antwort (`fastcgi_finish_request`/LiteSpeed), sonst einheitliche Mindestdauer (2 s) gegen Timing-Enumeration | reuse | generisch | Response-Timing-Helper | R1-Free | R0-17 | security-test.php:466 |
| INV-C-083 | `src/Support/Mail.php:28`, `:55`, `:90` | PHP | Absender aus den WooCommerce-Einstellungen, Anbieterfuß (Name, Shop-Adresse, E-Mail), Filter `bootspruefung_mail_footer`, Fuß nur einmal | abstract | Woo-Abhängigkeit entfernen, eigene Absender- und Impressumsangaben | Mail-Service mit Vorlagen | R1-Free | R1-21, R1-34 | operations-test.php:445 |
| INV-C-084 | `src/Audit/AuditLog.php:13` | Daten | `record(action, objectType, objectId, reason, context)` mit festen Formaten (`object_id` als Text) | reuse | generisch | Audit-Service | R1-Free | R1-17, R0-13 | smoke-test.php:119; admin-test.php:494 |

### 2.4 Content: Paket, Validierung, Import, Medien, Auslieferung

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden-Master-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-C-085 | `src/Content/Package/Package.php:16`, `:19` | Import | `ENTITIES`, `SINCE` (Entität ab Schema-Version), Grenzen 200 MB, 20 000 Einträge | abstract | Die Entitätsliste ist überwiegend fachlich | Content-Package-v1, Entitäts-Registry | R1-Free | R0-10, R1-15 | content-flow-test.php:45 |
| INV-C-086 | `src/Content/Package/Package.php:45`, `:87` | Import | `extract`/`inspect`: Inhaltsverzeichnis vor dem Entpacken prüfen (Pfade, Symlinks im Unix-Modus, Kompressionsverhältnis 200 je Datei und 50 gesamt, Manifest), Aufräumen bei Fehler | reuse | generischer ZIP-Schutz | Package-Reader | R1-Free | R0-10, R1-15 | admin-test.php:965; security-test.php:142 |
| INV-C-087 | `src/Content/Package/Package.php:158`, `:194`, `:209`, `:225` | Import | `entryError` (Traversal, Laufwerksbuchstaben, nur `manifest.json`/`data/*.json`/`media/`), `isJunk` (.DS_Store, Thumbs.db, `__MACOSX`), `files`, `inspectDirectory` | reuse | generisch | Package-Reader | R1-Free | R0-10 | admin-test.php:1041 |
| INV-C-088 | `src/Content/Package/Package.php:252`, `:263`, `:285` | Import | `manifest`, `entities`, `validate`: Manifest-Schema, Major-Version 1, Größe und sha256 je Datei, nicht gelistete Dateien, Schemas je Entität, doppelte `source_id` | reuse | generisch | Package-Validator | R1-Free | R0-10, R0-38, R1-15 | content-flow-test.php:45 |
| INV-C-089 | `src/Content/Package/Package.php:349` | Import | `mediaTypeErrors` (Endung, deklarierter und erkannter MIME-Typ) | reuse | generisch | Package-Validator | R1-Free | R0-10 | security-test.php:142 |
| INV-C-090 | `src/Content/Package/Package.php:387` | Import | `referenceErrors`: Querverweise Kurs→Modul→Frage→Medium, Regeln, Bögen, Navigation, Funk, Praxis (höchstens 50 Fehler) | abstract | Mechanik neutral, Prüfregeln je Entität registrieren, Fachentitäten streichen | Referenzprüfung je Entität | R1-Free | R0-10, R0-22 | content-flow-test.php:45 |
| INV-C-091 | `src/Content/Package/SchemaValidator.php:20` | Import | Kleiner JSON-Schema-Validator (2020-12-Teilmenge, `$ref` über Dateien, `if/then`, `contains`); unbekannte Schlüsselwörter brechen ab | reuse | generisch, keine Abhängigkeit | Schema-Validator | R1-Free | R1-15 | – (nur indirekt über content-flow-test.php:45; eigener Unit-Test nötig) |
| INV-C-092 | `schema/v1/common.schema.json`, `manifest.schema.json` | Import | Gemeinsame Definitionen (Hash `sha256:`, Redaktions- und Rechtestatus, Codes) und Manifest (Quelle, Commit, Dateien, Warnungen, `skipped`, `not_converted`) | abstract | `package_id` verlangt `^boatingschool-…`, `sourceFile` verlangt Laravel-Pfade | Content-Package-v1-Manifest | R1-Free | R0-10, R1-15 | content-flow-test.php:45 |
| INV-C-093 | `schema/v1/courses`, `modules`, `questions`, `media`, `exam_rules`, `exam_papers`, `video_modules` | Import | Neutrale Kernentitäten mit Fachresten (`course_type` `full/combo/addon`, `question_type` `chart_task`, `answer_key` `MODEL`, Bogen-ID `^[A-Z0-9-]+#nn`) | abstract | Kern übernehmen, Fachwerte entfernen | Content-Package-v1-Entitäten | R1-Free | R0-10, R1-06, R1-08 | content-flow-test.php:45 |
| INV-C-094 | `schema/v1/navigation_tasks`, `praxis_tasks`, `radio_texts`, `radio_vocabulary`, `radio_practice`, `device_simulation`, `exam_committees`, `exam_guides` | Import | Fachentitäten | exclude_domain_specific | Kap. 26.2 | – | exclude | – | – |
| INV-C-095 | `src/Content/Import/ImportService.php:27` | Import | `PHASES`: Reihenfolge inklusive `*_links` und `archive` | abstract | Phasenmodell neutral, Liste über eine Registry | Import-Pipeline | R1-Free | R0-38, R1-15 | admin-test.php:102 |
| INV-C-096 | `src/Content/Import/ImportService.php:86`, `:119` | Import | `createFromZip` (Temp-Ordner, `rename` oder Kopie), `createFromDirectory` (Ordnerprüfung, Kopie ohne Systemdateien) | reuse | generisch | Import-Quelle | R1-Free | R1-15 | admin-import.mjs; content-flow-test.php:45 |
| INV-C-097 | `src/Content/Import/ImportService.php:139`, `:383` | Import | `rollback` (nur veröffentlicht, Pflichtbegründung, neuer Import aus der Paketkopie), `restoreActive` | reuse | generisch | Rollback | R1-Free | R1-15, R0-16 | content-flow-test.php:115; admin-test.php:102 |
| INV-C-098 | `src/Content/Import/ImportService.php:166`, `:181`, `:195`, `:207`, `:213`, `:222` | Import | `activeBatchId`, `abortedImport` (Mischstand), `lastActivity` (Heartbeat), `isStale` (600 s), `isAborted`, `supersededBy` | reuse | generisch | Import-Zustandsautomat | R1-Free | R0-38 | admin-test.php:71, :102 |
| INV-C-099 | `src/Content/Import/ImportService.php:244` | Import | `validate`: Status `validated`/`failed`, abgelehnte Medien einzeln im Protokoll, Audit | reuse | generisch | Validierungsreport | R1-Free | R0-38, R1-41 | security-test.php:142 |
| INV-C-100 | `src/Content/Import/ImportService.php:275`, `:332` | Import | `preview`: Dry-Run (`create`/`update`/`unchanged`/`archive` je Entität), Zeitbudget mit Fortsetzung, Rechte-Zusammenfassung, `previewProgress` | reuse | generisch | Dry-Run | R1-Free | R0-38, R1-15 | admin-test.php:90 |
| INV-C-101 | `src/Content/Import/ImportService.php:342`, `:357` | Import | `approve` (nur nach Vorschau, nicht veraltet), `discard` (auch abgebrochene; Mischstand merken; Paketdateien löschen) | reuse | generisch | Freigabe-Workflow | R1-Free | R1-15 | admin-test.php:71 |
| INV-C-102 | `src/Content/Import/ImportService.php:398`, `:442` | Import | `runStep`/`step`: Sperre, Prüfung auf neueren Import, nur ein Import gleichzeitig, Zustand nach der Sperre neu lesen (T199), Transaktion je Schritt, Cursor, Heartbeat, Veröffentlichung setzt `bp_learning_active_package`, Cache leeren, Audit | reuse | generisch, Kern der Batch-Verarbeitung | Batch-Runner | R1-Free | R0-38, R1-15, R1-41 | admin-test.php:102, :129 |
| INV-C-103 | `src/Content/Import/ImportService.php:538`, `:555` | Import | `limitBytes` (64 MB je Medienschritt, mindestens ein Datensatz), `runAll` (CLI, 500 je Schritt) | reuse | generisch | Batch-Runner | R1-Free | R1-41 | admin-test.php:146 |
| INV-C-104 | `src/Content/Import/ImportService.php:565`, `:576`, `:584`, `:593`, `:607` | Import | Zugriff auf Batch, Statistik, Liste, Protokoll (gefiltert, paginiert) und Paket | reuse | generisch | Import-Repository | R1-Free | R1-15 | admin-test.php:640 |
| INV-C-105 | `src/Content/Import/ImportService.php:617`, `:639`, `:653` | Import | `createBatch` (aus dem Manifest), `rightsSummary` (feste Fachentitätsliste), `writeLogs` (Mehrfach-INSERT zu je 200) | abstract | `rightsSummary` nennt Fachentitäten | Import-Repository | R1-Free | R0-38 | admin-test.php:151 |
| INV-C-106 | `src/Content/Import/ImportService.php:699`, `:710`, `:719`, `:735`, `:744` | Import | Lock-Besitzer `<id>-<zufall>` (T143), Freigabe nur durch den eigenen Lauf, Lösen verwaister Sperren (nicht, solange ein Schritt läuft, T208), `copyDir`, `removeDir` | reuse | generisch | Batch-Runner | R1-Free | R0-21, R0-38 | admin-test.php:102 |
| INV-C-107 | `src/Content/Import/EntityImporter.php:24`, `:28`, `:70` | Import | `SOURCE_SYSTEM='boatingschool'`, `ARCHIVABLE`, Verteilung nach Entität | abstract | Konstante und Liste sind fachlich | Entity-Handler-Registry | R1-Free | R1-15 | content-flow-test.php:45 |
| INV-C-108 | `src/Content/Import/EntityImporter.php:98`, `:124` | Import | `archiveMissing`/`archiveTable`: fehlende Datensätze nur `source_archived`, nie löschen; Unterobjekte (Teilaufgaben, Lektionen) mit | reuse | generisch, schützt Lernhistorie | Soft-Archive | R1-Free | R0-23, R1-15 | content-flow-test.php:84 |
| INV-C-109 | `src/Content/Import/EntityImporter.php:153`, `:166` | Import | Kurs- und Modul-Upsert | reuse | generisch | Entity-Handler | R1-Free | R1-04 | content-flow-test.php:45 |
| INV-C-110 | `src/Content/Import/EntityImporter.php:177` | Import | Medien: Prüfsumme in Datenbank und Datei vergleichen (T203), atomar speichern | reuse | generisch | Entity-Handler Medien | R1-Free | R1-07 | content-flow-test.php:543 |
| INV-C-111 | `src/Content/Import/EntityImporter.php:215` | Import | Fragen-Revisionierung: neuer Hash ergibt neue Revision mit Antworten und Medien, alte Revision `deprecated`; bekannter Hash reaktiviert die alte Revision (Rollback) | reuse | generisch, Kern für Versuchsbezug | Revisionsmodell | R1-Free | R0-23, R1-06 | content-flow-test.php:69, :115 |
| INV-C-112 | `src/Content/Import/EntityImporter.php:329`, `:341`, `:707` | Import | `moduleLinks`, `courseLinks`, `syncLinks` (differenzieller Abgleich) | reuse | generisch | Link-Sync | R1-Free | R1-04 | content-flow-test.php:84 |
| INV-C-113 | `src/Content/Import/EntityImporter.php:353`, `:378` | Import | Prüfungsregel und Blueprints, feste Bögen (Neuaufbau der Bogenfragen) | abstract | siehe INV-C-039 | Entity-Handler Prüfungsregeln | R1-Free | R1-08 | content-flow-test.php:203 |
| INV-C-114 | `src/Content/Import/EntityImporter.php:404`, `:443`, `:516`, `:535`, `:562`, `:578`, `:598`, `:616` | Import | Navigation, Praxis, Funktexte, Funkpraxis, Gerätesimulation, Prüfungsausschüsse (mit `locallyModified`, `:608`), Prüfungsanleitung, Vokabeln | exclude_domain_specific | Fachentitäten. Das Muster „lokal geändert schützt vor Reimport“ übernimmt INV-C-121. | – | exclude | – | – |
| INV-C-115 | `src/Content/Import/EntityImporter.php:466` | Import | Videokapitel mit Lektionen (IDs bleiben stabil), Schritten und Quizfragen | abstract | siehe INV-C-041 | Entity-Handler Video | R1-Free | R1-07 | content-flow-test.php:260 |
| INV-C-116 | `src/Content/Import/EntityImporter.php:637`, `:662`, `:675`, `:686`, `:744`, `:750` | Import | `upsert`, `action` (Hash-Vergleich, Archiv wird `update`), `find`, `idOf` (Cache, `code` oder `source_id`), `sourceFields`, `insert` mit Fehlerabbruch | reuse | generisch | Entity-Handler-Basis | R1-Free | R1-15 | content-flow-test.php:69 |
| INV-C-117 | `src/Content/Import/ImportSources.php:47`, `:68`, `:92`, `:149` | Import | Ablageordner `import/` im privaten Paketordner (SFTP), einmaliger Umzug aus `uploads/bootspruefung-import/` mit Hinweis | abstract | Ablageordner neutral, Legacy-Umzug entfällt | Drop-Folder-Import | R1-Free | R1-15, R0-10 | admin-test.php:1041 |
| INV-C-118 | `src/Content/Import/ImportSources.php:184`, `:216`, `:240` | Import | `dropItems`, `resolveDrop` (nur Basisname, `realpath` direkt im Ablageordner, keine Links), `removeDropped` | reuse | generisch, Schutz vor Path Traversal | Drop-Folder-Import | R1-Free | R0-10 | admin-test.php:1041 |
| INV-C-119 | `src/Content/Import/ImportSources.php:258`, `:318` | Import | Download per https-Link: `wp_safe_remote_get`, Weiterleitungen selbst prüfen (höchstens 3, nur https), Größe, optionale SHA-256 | reuse | generisch | URL-Import | R1-Free | R0-10, R1-15 | admin-test.php:1160 |
| INV-C-120 | `src/Content/Import/ImportSources.php:327`, `:345`, `:354`, `:363`, `:376` | UI | Upload-Grenze (kleinster Wert aus PHP, WP und Paket), `post_max_size` überschritten, lesbare Upload-Fehler mit Ausweichwegen | reuse | generisch | Upload-UX | R1-Free | R1-15, R1-05 | admin-test.php:965 |
| INV-C-121 | `src/Content/Import/Retention.php:38`, `:62`, `:96`, `:117`, `:149` | Cron | Aufbewahrung: aktives Paket plus 2 veröffentlichte und laufende Importe (30 Tage) behalten, verwaiste Ordner und Downloads nach 24 h löschen, Protokoll `unchanged` nach 14 Tagen und den Rest nach 180 Tagen (höchstens 20 000 Zeilen je Lauf), Sperre 1 h, Filter | reuse | generisch | Import-Retention | R1-Free | R1-17, R0-31 | operations-test.php:196 |
| INV-C-122 | `src/Content/Media/MediaStorage.php:67`, `:84`, `:98`, `:106`, `:115`, `:124`, `:129`, `:138`, `:147` | Daten | Basis-, Privat-, Paket-, Schutz- und Medienordner; `BOOTSPRUEFUNG_PRIVATE_DIR`/Filter `bootspruefung_private_dir` (gilt auch ohne Schreibrecht, T204); URLs | abstract | Mechanik neutral, Konstanten- und Ordnernamen fachlich | Storage-Service | R1-Free | R0-10, R1-14 | security-test.php:70, :109 |
| INV-C-123 | `src/Content/Media/MediaStorage.php:164`, `:193`, `:206`, `:225` | Daten | `store` (atomar über Temp-Datei und sha256), `existsWithHash`, `allowedMedia` (Filter, `DANGEROUS` gesperrt), `mediaTypeError` (versteckte Teile, Doppelendung, MIME deklariert und per `finfo`) | reuse | generisch | Medien-Ablage | R1-Free | R0-10, R1-07 | security-test.php:142 |
| INV-C-124 | `src/Content/Media/MediaStorage.php:261`, `:267`, `:275`, `:291`, `:324`, `:348` | Daten | `targetDir`, `layout`, `resolve`, `relocateAll`, `relocationPending` (nur Optionen), `relocateIfPending` (admin_init) | reuse | generisch | Storage-Layout | R1-Free | R0-10, R0-16 | security-test.php:83 |
| INV-C-125 | `src/Content/Media/MediaStorage.php:360` | Daten | `quarantineMedia`: einmalig nicht erlaubte Dateien aus `media/` in die Quarantäne verschieben | replace | Altlast nach einem Update. Course Captain prüft schon beim Import. | – (Validierung beim Import) | R1-Free | R0-10 | security-test.php:142 |
| INV-C-126 | `src/Content/Media/MediaStorage.php:396`, `:433`, `:443`, `:458`, `:476`, `:507`, `:528`, `:537`, `:553` | Daten | Umzug von Ordnern (rename, sonst Kopie, Quelle erst danach löschen), Wiederholung nach 1 h, Pfade in `_bp_protected_path` umschreiben, `movePath`/`removePath` ohne Symlink-Folgen | reuse | generisch | Storage-Layout | R1-Free | R0-16 | security-test.php:83, :95 |
| INV-C-127 | `src/Content/Media/MediaStorage.php:562`, `:576`, `:581`, `:595` | Daten | Zufallsgeheimnis je Ordner (nie ersetzen), `index.php`, `.htaccess` (privat bzw. Skriptsperre in `media/`, fremde Dateien nicht überschreiben) | reuse | generisch | Storage-Schutz | R1-Free | R0-10, R1-14 | security-test.php:70, :217 |
| INV-C-128 | `src/Content/Repositories/ContentCache.php:31`, `:72` | PHP | `remember` (Object-Cache-Gruppe mit Generation, Transient-Rückfall ohne persistenten Cache, T187), `invalidate` (leert auch `bp_learning_video_hosts` und `ShopCatalog::flushCourseStats`) | reuse | Generisch. Die Kopplung an Shop und Video wird über einen Invalidierungs-Hook gelöst. | Content-Cache + Hook `course_captain_content_changed` | R1-Free | R0-21, R1-25 | operations-test.php:279 |
| INV-C-129 | `src/Content/Repositories/ContentRepository.php:33`, `:42`, `:49`, `:54`, `:60`, `:69` | PHP | Kurse (Cache), `courseByCode`, Slug = Code in Kleinbuchstaben, Module aller Kurse in einer Abfrage | reuse | Generisch. Der Filter `source_system` wird neutral. | Course-Repository | R1-Free | R1-04 | operations-test.php:279 |
| INV-C-130 | `src/Content/Repositories/ContentRepository.php:88`, `:117`, `:135` | PHP | `deliverableQuestionSql`: aktiv, veröffentlicht, erlaubter Typ, Rechte nicht `unknown`/`restricted`, Gültigkeitsfenster, Pflichtbild mit freigegebenem Medium; `questionsForModules` (Cache je Tag), `isDeliverable` | reuse | generische Auslieferungsregel | Delivery-Policy | R1-Free | R0-22, R1-06, R1-14 | content-flow-test.php:152, :166 |
| INV-C-131 | `src/Content/Repositories/ContentRepository.php:140`, `:148`, `:160`, `:181` | PHP | Revision, Antworten (Memo je Anfrage), Medien mit Overrides, `missingRequiredImage` | reuse | generisch | Question-Repository | R1-Free | R1-06 | content-flow-test.php:166 |
| INV-C-132 | `src/Content/Repositories/ContentRepository.php:191` | PHP | `examRule`: veröffentlicht, verifiziert und gültig ergibt „live“, sonst Übungsmodus mit der neuesten Regel; Rückfall `min_correct_ratio 0.75` ist hartcodiert | abstract | Amtliche Verifikation entfällt. Ein hartcodierter Schwellenwert ist nach Kap. 26.4 verboten. | Exam-Rule-Resolver | R1-Free | R1-08, R0-08 | content-flow-test.php:203 |
| INV-C-133 | `src/Content/Repositories/ContentRepository.php:219`, `:228`, `:240`, `:250`, `:256` | PHP | Blueprints, Bögen, Bogenfragen (nur auslieferbare), Anzahl, Fragen je Modul | abstract | „Bögen“ werden neutrale „feste Prüfungsvarianten“ | Exam-Repository | R1-Free | R1-08 | content-flow-test.php:203 |
| INV-C-134 | `src/Content/Repositories/Overrides.php:19`, `:43`, `:52`, `:67`, `:102`, `:112` | Daten | Lokale Overrides (überleben Reimport) mit Pflichtbegründung und Audit; Rechtestatus `unknown/review/licensed/public/restricted`, gesperrt sind `unknown`/`restricted`; `rightsJoin` | reuse | Generisch. Die Typenliste wird mit der Entitätsliste neutral. | Override-Service | R1-Free | R0-10, R1-15 | content-flow-test.php:152; admin-test.php:151 |
| INV-C-135 | `src/Content/RightsRelease.php:39` | Daten | `releaseAll`: alle importierten `unknown` ohne Override per Override auf `licensed` setzen, Dry-Run | abstract | Sammelfreigabe ist neutral. Datierte Betreiberbegründung und Seekartenbezug entfallen. | Sammelfreigabe Rechte | R1-Free | R0-22, R1-15 | admin-test.php:151, :778 |
| INV-C-136 | `src/Content/RightsRelease.php:64` | Daten | `releaseExamRules`: amtliche Regelwerke als verifiziert und veröffentlicht markieren | exclude_domain_specific | Verifikation amtlicher Prüfungsordnungen (Kap. 26.2) | – | exclude | – | membership-test.php:879 |

### 2.5 Sicherheit, Datenschutz

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden-Master-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-C-137 | `src/Security/Capabilities.php:12`, `:18`, `:30` | API | `manage_bp_content`, `import_bp_content`, `manage_bp_entitlements`; an Administrator vergeben, bei Deinstallation entfernen | abstract | Zu grob für die RBAC-Matrix, Präfix fachlich | `cc_*`-Capabilities nach R0-07 | R1-Free | R0-07, R1-03 | smoke-test.php:63, :138 |
| INV-C-138 | `src/Security/Hardening.php:34` | API | Website-weite Härtung: `/wp/v2/users` für Gäste aus, Autorenarchive, oEmbed-Autor, Benutzer-Sitemap (auch Yoast), XML-RPC aus, Anwendungspasswörter nur für Redaktion, Feed-Autor | abstract | Nicht LMS-spezifisch, verändert die ganze Website (E-C-02) | optionales Modul „Website-Härtung“ | R2 (nach E-C-02) | R0-17, R1-30 | security-test.php:550, :594 |
| INV-C-139 | `src/Security/Hardening.php:109` | Commerce | E-Mail-Änderung unter „Mein Konto“ nur mit Passwort | replace | Woo-Kontoseite | Woo-Adapter | R3 | R3-02 | security-test.php:594 |
| INV-C-140 | `src/Security/Hardening.php:130` | API | Registrierungen begrenzen (10 je IP und Stunde) | abstract | siehe E-C-02 | Härtungsmodul | R2 | R0-17 | security-test.php:594 |
| INV-C-141 | `src/Security/Hardening.php:144`, `:162` | API | Sicherheits-Header (`nosniff`, Referrer, `SAMEORIGIN`, Permissions-Policy mit `microphone=(self)` für Funktraining) | abstract | Mikrofon-Freigabe ist fachlich, Header greifen website-weit | Härtungsmodul | R2 | R0-17 | security-test.php:594 |
| INV-C-142 | `src/Security/Hardening.php:173`, `:189` | API | Filter `bootspruefung_hardening` je Merkmal; bekannte Sicherheits-Plugins erkennen (Login-Limit dann aus) | reuse | generisch | Härtungsmodul | R2 | R1-30 | security-test.php:585 |
| INV-C-143 | `src/Security/LoginGuard.php:58`, `:78`, `:122`, `:155`, `:249`, `:272` | API | Login-Limit für alle `wp_authenticate`-Wege: 10 je IP, 5 je Konto+IP, 30 je Konto in 15 min, atomar vorgemerkt (T83), gemeinsame Zähler mit der App | abstract | Teil der Website-Härtung (E-C-02) | Härtungsmodul | R2 | R0-17 | security-test.php:279 |
| INV-C-144 | `src/Security/LoginGuard.php:335`, `:345`, `:350` | API | Dummy-Passwortprüfung gegen Timing-Enumeration, neutrale und Sperr-Meldung | abstract | wie INV-C-143 | Härtungsmodul | R2 | R0-17 | security-test.php:279 |
| INV-C-145 | `src/Security/LoginGuard.php:366`, `:390`, `:428`, `:445`, `:463`, `:491` | API | Passwort vergessen: gleiche Bestätigung für unbekannte Konten, Limits (10 je IP, 3 je Konto+IP, 10 je Konto pro Stunde), Antwort vor dem Mailversand | abstract | wie INV-C-143 | Härtungsmodul | R2 | R0-17 | security-test.php:428, :466 |
| INV-C-146 | `src/Security/KnownDevice.php:36`, `:45`, `:58`, `:72`, `:110` | API | Signiertes Gerätecookie `bp_known_device` (HMAC über Nutzer, Ablauf und Hash des Passwort-Hashs, 180 Tage); umgeht nur IP- und Kontosperre | abstract | wie INV-C-143 | Härtungsmodul | R2 | R0-17 | security-test.php:324; mobile-api-test.php:166 |
| INV-C-147 | `src/Privacy/PersonalData.php:45` | API | Exporter/Eraser `bootspruefung-learning`, `deleted_user`, stündliches `audit_prune` | reuse | generisch | Privacy-Registrierung | R1-Free | R1-17 | smoke-test.php:97 |
| INV-C-148 | `src/Privacy/PersonalData.php:80` | Daten | `deletedUser`: Gruppenplätze freigeben, Lerndaten löschen, Zugänge schließen; Multisite (alle Websites, wenn das Konto netzweit weg ist) | reuse | generisch | User-Deletion-Handler | R1-Free | R1-17, R0-09 | account-test.php:211 |
| INV-C-149 | `src/Privacy/PersonalData.php:100`, `:106`, `:108`, `:130` | Cron | Audit-Aufbewahrung 4 Jahre (höchstens 5 000 je Lauf); Zugänge gelöschter Konten 10 Jahre (§ 147 AO; täglich, je Lauf höchstens 1 000) | abstract | DE-Rechtsfristen müssen als Setting mit Default vorliegen (E-C-06) | Retention-Policy | R1-Free | R0-31, R1-17 | – (kein direkter Test; neuer Test nötig) |
| INV-C-150 | `src/Privacy/PersonalData.php:167`, `:229`, `:237` | Daten | Export-Quellen je Tabelle (Kindtabellen per Unterabfrage, Geheimnisse ausgelassen, Audit inklusive `context.user_id`/`from_user`, Verwaltungsspuren), seitenweise 2 000 | abstract | Mechanik neutral, Tabellenliste aus dem neutralen Schema | Export-Registry | R1-Free | R1-17 | account-test.php:300 |
| INV-C-151 | `src/Privacy/PersonalData.php:281`, `:301`, `:310` | Daten | Zusatzposten im Export: App-Einstellungen, Tickets, Ablauf-Erinnerungen und Widerspruch, Geld-zurück-Garantie | abstract | Geld-zurück ist Woo/Commerce (R3), Tickets sind ein Connector (R3) | Export-Beiträge je Modul | R1-Free / R3 | R1-17, R3-07 | account-test.php:300 |
| INV-C-152 | `src/Privacy/PersonalData.php:366`, `:386`, `:407` | Daten | `erase`/`eraseUser` in einer Transaktion: Kindtabellen zuerst, `USER_DATA_TABLES`, Meta, Gruppen-E-Mail und Token entfernen, Gruppenname neutralisieren, Audit-Kontext pseudonymisieren (`JSON_SET` mit sha256), Hinweise, was bleibt | reuse | generisch | Eraser | R1-Free | R1-17, R0-17 | smoke-test.php:97; account-test.php:139; extensions-test.php:494 |
| INV-C-153 | `src/Privacy/AccountDeletion.php:50`, `:70`, `:76`, `:97` | API | Hooks für bestätigte Anträge; `isProtected` (Admin-, Shop-, Redaktionsrollen und eigene Caps, Filter); Netzwerk-Test-Double | reuse | generisch | Request-Executor | R1-Free | R1-34 | account-test.php:211 |
| INV-C-154 | `src/Privacy/AccountDeletion.php:113`, `:124`, `:135`, `:155`, `:186`, `:203` | Daten | `scopeFor` (`account`/`network`/`site`), `confirmed`, `processDeletion` (alle Eraser, Antrag erledigt, Kontolöschung), `erased`, `deleteAccount` (Pseudonym im Audit) | reuse | generisch; Default ist E-C-05 | Request-Executor | R1-Free | R1-34, R0-25 | account-test.php:211, :375 |
| INV-C-155 | `src/Privacy/AccountDeletion.php:238`, `:267`, `:277`, `:285`, `:291`, `:299` | Commerce | Woo-Bestellungen anonymisieren (nach Woo-Einstellung), steuerrelevante Felder behalten (Filter), Geld-zurück-Meta bereinigen | replace | Woo-Adapter | Woo-Privacy-Adapter | R3 | R3-03 | account-test.php:211 |
| INV-C-156 | `src/Privacy/AccountDeletion.php:317`, `:353`, `:367` | Cron | `runErasers` (seitenweise, höchstens 500), `scheduleExport` (Action Scheduler oder WP-Cron), `processExport` (ZIP wie in den Werkzeugen, Mail, Audit) | reuse | generisch | Request-Executor | R1-Free | R1-17 | account-test.php:163 |
| INV-C-157 | `src/Privacy/AccountDeletion.php:428`, `:461`, `:475`, `:499`, `:520`, `:544`, `:561`, `:589` | UI | Bestätigungsseite mit Knopf (Schutz gegen Link-Scanner, T42), Texte, Mailtexte, Multisite-Hinweise | reuse | generisch, Texte neutralisieren | Privacy-UX | R1-Free | R1-17, R1-21 | account-test.php:211 |
| INV-C-158 | `src/Privacy/AccountDeletion.php:609` | Commerce | `closeEntitlements`: Zugänge ohne Bestellung löschen, solche mit Bestellung als manuell `revoked` markieren (T238) | abstract | `order_id` ist Woo-gebunden, Ziel ist eine quellenneutrale Regel | Grant-Policy bei Kontolöschung | R1-Free | R0-09 | account-test.php:211 |
| INV-C-159 | `src/Privacy/NetworkAccounts.php:14` | PHP | Multisite-Kapsel: Mitgliedschaften, `wpmu_delete_user`/`wp_delete_user`, Websites mit Tabellen, `inSite` | abstract | Umfang hängt an R0-25/E-C-01 | Multisite-Adapter | R1-Free (bedingt) | R0-25 | account-test.php:375 |
| INV-C-160 | `src/Privacy/SelfService.php:20`, `:26`, `:36`, `:51` | Commerce | „Meine Daten“ als WooCommerce-Endpunkt `meine-daten` samt Menüpunkt und Rewrite-Flush | replace | Free braucht eine eigene Seite, Block oder Shortcode im Lernbereich. Woo-Endpunkt nur im Adapter. | Privacy-Self-Service-Block | R1-Free / R3 | R1-16, R1-17 | account-test.php:163 |
| INV-C-161 | `src/Privacy/SelfService.php:66`, `:82` | API | `openRequest`, `request` (5 je Tag und Art, geschützte Konten, erneut senden, `wp_create_user_request`) | reuse | generisch | Privacy-Self-Service | R1-Free | R1-17 | account-test.php:163 |
| INV-C-162 | `src/Privacy/SelfService.php:123`, `:136`, `:211`, `:226` | UI | `handle` (admin-post `bp_privacy_request`, Nonce), `render`, `renderOpen`, `form` | abstract | UI auf den neutralen Lernbereich umstellen | Privacy-Self-Service-UI | R1-Free | R1-16, R1-19 | account-test.php:163 |

### 2.6 WP-CLI

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden-Master-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-C-163 | `src/Cli/Commands.php:18` | API | Registrierung von 15 Unterkommandos unter `wp bootspruefung` | abstract | Namensraum fachlich | `wp course-captain` | R1-Free | R0-19, R1-28 | – |
| INV-C-164 | `src/Cli/Commands.php:46` | API | `status [--format=table\|json]` | reuse | generisch | `wp course-captain status` | R1-Free | R1-38 | – |
| INV-C-165 | `src/Cli/Commands.php:78` | API | `backup-check [--format]`, Exit-Code 1 bei `fail` | reuse | generisch | `wp course-captain backup-check` | R1-Free | R0-16, R1-23 | operations-test.php:731 |
| INV-C-166 | `src/Cli/Commands.php:129`, `:165`, `:188` | Import | `import <paket>\|resume <id>\|discard <id> [--dry-run] [--yes] [--note] [--restore]` mit Rückfrage, die bei „nein“ verwirft | reuse | generisch | `wp course-captain import` | R1-Free | R1-15, R0-38 | – (CLI selbst ungetestet, Service: content-flow-test.php:45) |
| INV-C-167 | `src/Cli/Commands.php:242`, `:264`, `:279`, `:302`, `:312` | Import | `rollback <id> --reason [--yes]`, Vorschau-Tabelle, Freigabe und Lauf mit Fortsetzungs- und Verwerfungsanleitung | reuse | generisch | `wp course-captain rollback` | R1-Free | R1-15 | – (Service: content-flow-test.php:115) |
| INV-C-168 | `src/Cli/Commands.php:353` | Commerce | `reconcile [--apply] [--order] [--since] [--yes]` (Woo-Bestellabgleich) | replace | Woo-Adapter | `wp course-captain woo reconcile` | R3 | R3-03 | woocommerce-test.php (Teil B) |
| INV-C-169 | `src/Cli/Commands.php:430` | Commerce | `shop setup [--dry-run] [--publish] [--overwrite-prices] [--publish-held] [--only] [--yes]` (fester Produktkatalog) | exclude_domain_specific | fachgebundener Produktkatalog (Kap. 26.2) | – | exclude | – | admin-test.php:262 |
| INV-C-170 | `src/Cli/Commands.php:492` | API | `rights release [--reason] [--dry-run] [--exam-rules] [--yes]` | abstract | Sammelfreigabe neutral, `--exam-rules` entfällt | `wp course-captain rights release` | R1-Free | R1-15 | admin-test.php:778 |
| INV-C-171 | `src/Cli/Commands.php:531` | API | `chart status\|upload\|license\|unlicense <code> [<datei>] [--note]` (Seekarten) | exclude_domain_specific | Karten und Spezialmedien | – | exclude | – | – |
| INV-C-172 | `src/Cli/Commands.php:571` | API | `access <user>` (Zugänge eines Nutzers) | reuse | Supportwerkzeug | `wp course-captain grants <user>` | R1-Free | R0-13 | – |
| INV-C-173 | `src/Cli/Commands.php:593` | API | `cleanup-sites [--apply]` (Multisite) | abstract | hängt an E-C-01 | `wp course-captain cleanup-sites` | R1-Free (bedingt) | R0-25 | multisite-test.php:122 |
| INV-C-174 | `src/Cli/Commands.php:627` | API | `migrate` (Downgrade abgelehnt, Fremdschlüssel-Warnungen) | reuse | generisch | `wp course-captain migrate` | R1-Free | R1-40 | operations-test.php:171 |
| INV-C-175 | `src/Cli/Commands.php:655`, `:870`, `:889` | Commerce | `export access-rules <tenant> --output`, `export entitlements <tenant> --output` (Format v2 für das Laravel-Original) | replace | Zielformat und Tenant-Slug sind ein Migrationspfad zum Original. Course Captain braucht einen neutralen Export und Import der Grants und Regeln. | neutraler Grant-/Regel-Export | R1-Free | R1-15, R0-09 | membership-test.php:1339 |
| INV-C-176 | `src/Cli/Commands.php:733` | Commerce | `export course-files <tenant> --media-root --output` (Seekarten und Abschnittsdateien) | exclude_domain_specific | Seekarten- und Abschnittslogik, Laravel-Ziel | – | exclude | – | – |
| INV-C-177 | `src/Cli/Commands.php:1039` | API | `export customer-faqs [--output]` (Laravel-Format) | exclude_domain_specific | Laravel-Migrationspfad und Kunden-FAQ des Originals | – | exclude | – | – |
| INV-C-178 | `src/Cli/Commands.php:942`, `:964`, `:1009` | PHP | `mobileExportUtc`, `insideWebroot` (Webroot, Elternordner, DOCUMENT_ROOT, wp-content, Uploads; nicht auflösbar gilt als Webordner), `writeMobileExport` (`umask 0077`, `fopen 'x'`, keine Symlinks, `0600`) | reuse | generisch, sicherer Exportschreiber | Safe-Export-Writer | R1-Free | R0-17, R1-15 | security-test.php:622 |

### 2.7 Erweiterungsschnittstellen (Gutschein, Tickets, Praxis-Angebote)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden-Master-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-C-179 | `src/Extensions/Extensions.php:21`, `:34` | API | Filter `bootspruefung_user_owns_course` (voller Zugang zum Fragenkatalog laut `AccessService`) | abstract | neutraler Zugangsfilter für Connectoren | Filter `course_captain_user_has_access` | R1-Free (Vertrag) / R3 | R0-19, R3-01 | extensions-test.php:426 |
| INV-C-180 | `src/Extensions/Extensions.php:51`, `:71` | API | Filter `bootspruefung_practice_region` (ISO 3166-2) und `bootspruefung_practice_offers` (höchstens 20, bereinigt) | abstract | „Praxis-Termine“ sind fachlich; neutral wären „externe Termine/Angebote je Kurs“ | Events-Connector-Vertrag | R3 | R3-07 | extensions-test.php:468 |
| INV-C-181 | `src/Extensions/Tickets.php:28`, `:36`, `:69`, `:81`, `:112`, `:136`, `:144`, `:173` | API | Ticket-Ereignisse `issued/redeemed/cancelled` in Nutzer-Meta (höchstens 100, ohne Scanner-ID), Liste je Kurs, Filter `bootspruefung_tickets_for_user` | abstract | Ticket-Connector ist neutral, Bezug „Praxis“ fachlich | Ticket-/Event-Connector | R3 | R3-07 | extensions-test.php:432 |
| INV-C-182 | `src/Extensions/Tickets.php:198`, `:214` | Daten | Export und Löschung der Ticket-Meta | reuse | generisch | Connector-Privacy-Beitrag | R3 | R3-07, R1-17 | extensions-test.php:494 |
| INV-C-183 | `src/Extensions/Vouchers.php:55`, `:63`, `:75`, `:80`, `:86`, `:96` | API | Registrierung, IP-Limit (30 je Stunde über alle Wege, Filter), Code-Hash, `available`, Aktion `bootspruefung_voucher_redeemed` mit Audit | abstract | Vertrag neutral nutzbar, Grenzen übernehmen | Voucher-Connector-Vertrag | R3 | R3-04, R3-05 | extensions-test.php:178 |
| INV-C-184 | `src/Extensions/Vouchers.php:110`, `:171`, `:176`, `:211`, `:223` | Commerce | `redeem`: interne 0-€-Woo-Bestellung je Code-Hash unter NamedLock (10 s), idempotent, Abgleich, Aktion `bootspruefung_voucher_order_created`; `orderForHash` (HPOS oder Postmeta) | replace | Konzept Kap. 32/47: Reserve/Grant/Commit direkt auf Grants, keine `wc_item`-Bindung im Kern. Muster „Lock je Code-Hash plus Idempotenz“ übernehmen. | Voucher-Grant-Service (Reserve/Grant/Commit) | R3 | R3-04, R3-05, R0-40 | extensions-test.php:153, :178; voucher-grant-test.php:142 |
| INV-C-185 | `src/Extensions/Vouchers.php:234`, `:279` | API | `redeemCode` über Filter `bootspruefung_voucher_redeem`, Aktion `…_redeem_failed`, `summary` | abstract | wie INV-C-183 | Voucher-Connector | R3 | R3-04 | extensions-test.php:219 |
| INV-C-186 | `src/Extensions/Vouchers.php:308`, `:337`, `:351` | UI | Web-Einlösung unter „Mein Lernen“ (10 je Nutzer, 30 je IP pro Stunde), admin-post `bp_voucher_redeem`, Formular | abstract | UI in den neutralen Lernbereich | Einlöse-UI | R3 | R3-13 | extensions-test.php:262 |
| INV-C-187 | `src/Extensions/Vouchers.php:382`, `:395` | Commerce | Shop-Gutschein vs. Mitgliederrabatt (Option `bp_learning_voucher_with_member_discount`) | replace | Woo-Warenkorb | Woo-Adapter | R3 | R3-02 | extensions-test.php:303 |

### 2.8 Tests als Bausteine

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden-Master-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-C-188 | `tests/integration/support/guard.php:1`, `support/standalone.php:1` | Test | Abbruch, wenn der DB-Name nicht „test“ enthält; eigenständiger Lauf (aktivieren, migrieren, Settings sichern und zurückschreiben) | reuse | generisch | Test-Harness | R1-Free | R0-18, R1-18 | – |
| INV-C-189 | `tests/integration/smoke-test.php:1` | Test | Lifecycle-Rauchtest (doppelte Aktivierung, InnoDB, Fremdschlüssel, Caps, REST-Status, Privacy, Deinstallation) | reuse | generisch, ohne Fachdaten | Smoke-Suite | R1-Free | R1-18, R1-39 | selbst |
| INV-C-190 | `tests/integration/uninstall-phase.php:1` | Test | Exakte Datenlöschung inklusive Gegenprobe mit „gleich klingenden“ Daten anderer Plugins | reuse | generisch | Uninstall-Suite | R1-Free | R1-23 | selbst |
| INV-C-191 | `tests/integration/operations-test.php:1` | Test | Betriebsregressionen (siehe Spalte GM oben) | abstract | Teile prüfen Fachobjekte (Ablauf-Erinnerung, Tipps, Videohost „sbf“) | Operations-Suite | R1-Free | R1-18, R1-40 | selbst |
| INV-C-192 | `tests/integration/multisite-test.php:1`, `multisite-setup.php:1` | Test | Multisite je Website | abstract | hängt an E-C-01 | Multisite-Suite | R1-Free (bedingt) | R0-25, R1-30 | selbst |
| INV-C-193 | `tests/integration/security-test.php:1` | Test | Ablage, Medientypen, Login, Härtung, Seiten-Cache | abstract | Härtungsteile folgen E-C-02 | Security-Suite | R1-Free / R2 | R1-14, R0-17 | selbst |
| INV-C-194 | `tests/integration/release-ops-test.php:1` | Test | UpdateCheck, SiteHealth | replace | folgt INV-C-066 | Pro-Updater-Tests | R3 | R3-18 | selbst |
| INV-C-195 | `tests/integration/content-flow-test.php:45`, `:69`, `:84`, `:115`, `:152` | Test | Import, Reimport, geändertes Paket, Rollback, Rechte-Override | abstract | Prüflogik neutral, Fixture ist das Fachpaket | Import-Golden-Master mit neutralem Paket | R1-Free | R0-05, R1-15, R1-18 | selbst |
| INV-C-196 | `tests/integration/admin-test.php:71`…`:1213` | Test | Import-Zustände, Teilschritte, Upload-Grenze, Ablageordner, Link-Import | abstract | wie INV-C-195 | Import-Admin-Suite | R1-Free | R1-41, R1-18 | selbst |
| INV-C-197 | `tests/integration/extensions-test.php:1`, `voucher-grant-test.php:1` | Test | Gutschein-, Ticket- und Praxis-Schnittstellen | abstract | Connector-Suite (R3) | Connector-Vertragstests | R3 | R3-05, R3-12 | selbst |
| INV-C-198 | `tests/phpstan/bootstrap.php`, `external-stubs.php`, `wp-cli-stubs.php` | Test | PHPStan-Bootstrap und Stubs | reuse | generisch | Static-Analysis-Setup | R1-Free | R0-18 | – |
| INV-C-199 | `tests/e2e/admin-import.mjs:1` | Test | Browser: Upload, Vorschau, Freigabe, Import (REST-Schritte), Content-Browser | abstract | Fachpaket als Fixture | E2E „Paket importieren“ | R1-Free | R1-18, R1-15 | selbst |

### 2.9 Klassifikationsverteilung (199 Funktionen)

| Klassifikation | Anzahl |
|---|---:|
| reuse | 97 |
| abstract | 71 |
| replace | 20 |
| exclude_domain_specific | 11 |
| unknown | 0 |

## 3. Daten

### 3.1 Datenbankschema (vollständig, `Schema::definitions()`, Präfix `{$wpdb->prefix}bp_`)

Alle Tabellen werden mit `ENGINE=InnoDB {charset_collate}` angelegt (`Schema.php:858`). **SRC** steht für die Herkunftsspalten aus `sourceColumns()` (`Schema.php:924`): `source_system varchar(32) NOT NULL DEFAULT 'boatingschool'`, `source_id varchar(191) NOT NULL`, `source_hash char(71) NULL`, `package_id varchar(64) NULL`, `status varchar(20) NOT NULL DEFAULT 'draft'`, `imported_at datetime NULL`. **TS** steht für `created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`. Alle `id` sind `bigint(20) unsigned NOT NULL AUTO_INCREMENT` mit `PRIMARY KEY (id)`, wenn nichts anderes angegeben ist. Die letzte Spalte gibt die Klassifikation aus Abschnitt 2 an.

| Tabelle | Spalten (Typ, Default) | Uniques | Indizes | Kl. |
|---|---|---|---|---|
| `course` | SRC; `code varchar(64)`, `name varchar(255)`, `description longtext NULL`, `course_type varchar(20) 'full'`, `language varchar(10) 'de-DE'`, `sort_order int 0`; TS | `source(source_system,source_id)`, `code(code)` | `status(status)` | abstract |
| `module` | SRC; `code varchar(64)`, `name varchar(255)`, `description longtext NULL`, `version varchar(64) NULL`, `sort_order int 0`; TS | `source`, `code` | – | abstract |
| `course_module` | `course_id`, `module_id` (bigint), `sort_order int 0`, `required tinyint 1`, `rules longtext NULL` | `course_module(course_id,module_id)` | `module_id` | reuse |
| `question` | SRC; `official_number varchar(32) NULL`, `content_role varchar(64) NULL`, `language varchar(10) 'de-DE'`, `active tinyint 1`, `source_module varchar(64) NULL`, `current_revision_id bigint NULL`; TS | `source` | `role_number(content_role,official_number)`, `current_revision_id` | abstract |
| `question_revision` | `question_id`, `revision_no int`, `source_hash char(71)`, `package_id varchar(64) NULL`, `question_type varchar(32)`, `question_text longtext`, `topic`/`subtopic`/`competency`/`smart_category varchar(255) NULL`, `scope varchar(64) NULL`, `image_required tinyint 0`, `feedback_correct`/`feedback_incorrect longtext NULL`, `source_catalog varchar(255) NULL`, `source_version varchar(64) NULL`, `source_question_id varchar(64) NULL`, `source_page varchar(40) NULL`, `editorial_status varchar(20) 'draft'`, `rights_status varchar(20) 'unknown'`, `rights_owner varchar(255) NULL`, `license_reference longtext NULL`, `valid_from`/`valid_to date NULL`, `created_at` | `question_revision(question_id,revision_no)` | `question_hash(question_id,source_hash)`, `publish(question_id,editorial_status,valid_from,valid_to)` | abstract |
| `answer` | `revision_id`, `answer_key varchar(16)`, `answer_text longtext`, `is_correct tinyint 0`, `sort_order int 0` | `revision_key(revision_id,answer_key)` | – | reuse |
| `module_question` | `module_id`, `question_id`, `sort_order int NULL`, `required tinyint 1` | `module_question(module_id,question_id)` | `question_id` | reuse |
| `media` | SRC; `attachment_id bigint NULL`, `storage_key varchar(255) NULL`, `package_path varchar(255)`, `media_type varchar(20) 'image'`, `mime_type varchar(100)`, `size bigint 0`, `sha256 char(64)`, `alt_text varchar(255) NULL`, `caption`/`description`/`source_text longtext NULL`, `source_url varchar(2048) NULL`, `rights_owner varchar(255) NULL`, `license_reference longtext NULL`, `rights_status varchar(20) 'unknown'`, `vector_source varchar(255) NULL`; TS | `source` | `sha256` | reuse |
| `content_media` | `object_type varchar(32)`, `object_id`, `media_id`, `role varchar(32) 'question'`, `sort_order int 0`, `caption_override longtext NULL`, `alt_override varchar(255) NULL` | `object_media(object_type,object_id,media_id,role)` | `media_id` | reuse |
| `exam_rule` | SRC; `course_id`, `version varchar(64)`, `valid_from`/`valid_until date NULL`, `time_limit_seconds int`, `passing_rule longtext`, `verified tinyint 0`, `verified_by bigint NULL`, `verified_at datetime NULL`; TS | `source`, `course_version(course_id,version)` | – | abstract |
| `exam_blueprint` | `rule_id`, `module_id`, `question_count int`, `rules longtext NULL` | `rule_module(rule_id,module_id)` | `module_id` | reuse |
| `exam_paper` | SRC; `course_id`, `paper_number int`, `sort_order int 0`, `copied_from varchar(191) NULL`; TS | `source`, `course_paper(course_id,paper_number)` | – | abstract |
| `exam_paper_question` | `paper_id`, `question_id`, `position int` | `paper_position(paper_id,position)` | `question_id` | abstract |
| `navigation_task` | SRC; `task_number int`, `scenario_text longtext`, `hint longtext NULL`, `sort_order int 0`, `source_text varchar(2048) NULL`, `source_version varchar(64) NULL`, `rights_status varchar(20) 'unknown'`; TS | `source` | – | exclude |
| `navigation_task_course` | `task_id`, `course_id`, `sort_order int 0` | `task_course(task_id,course_id)` | `course_id` | exclude |
| `navigation_question` | SRC; `task_id`, `question_number int`, `question_text`/`answer_text longtext`, `answer_values longtext NULL`, `sort_order int 0`; TS | `source` | `task_id` | exclude |
| `praxis_task` | SRC; `course_id`, `category varchar(255)`, `subcategory`/`exam_reference varchar(255) NULL`, `task_type varchar(64) NULL`, `question`/`answer longtext`, `explanation longtext NULL`, `media_id bigint NULL`, `source_text varchar(255) NULL`, `source_url varchar(2048) NULL`, `sort_order int 0`, `active tinyint 1`, `rights_status varchar(20) 'unknown'`, `copied_from varchar(191) NULL`; TS | `source` | `course_category(course_id,category(100))`, `media_id` | exclude |
| `video_module` | SRC; `course_id`, `title varchar(255)`, `group_label varchar(255) NULL`, `sort_order int 0`, `rights_status varchar(20) 'unknown'`; TS | `source` | `course_id` | abstract |
| `video_lesson` | SRC; `module_id`, `title varchar(255)`, `video_url varchar(2048) NULL`, `video_media_id bigint NULL`, `provider varchar(32) NULL`, `duration_seconds int NULL`, `is_placeholder tinyint 0`, `transcript`/`chapters longtext NULL`, `sort_order int 0`; TS | `source` | `module_id` | abstract |
| `video_lesson_step` | SRC; `lesson_id`, `title varchar(255)`, `media_id bigint NULL`, `caption longtext NULL`, `alt_text varchar(255) NULL`, `sort_order int 0`; TS | `source` | `lesson_id` | abstract |
| `video_lesson_question` | `lesson_id`, `question_id`, `sort_order int 0` | `lesson_question(lesson_id,question_id)` | `question_id` | abstract |
| `radio_scenario` | SRC; `catalog varchar(16)`, `number int`, `course_codes varchar(255) NULL`, `message_kind varchar(64)`, `scenario`/`task_text`/`model_message`/`checkpoints longtext`, `solution`/`variants`/`critical_errors`/`evaluation_rule longtext NULL`, `language varchar(10) 'de-DE'`, `rights_status varchar(20) 'unknown'`, `sort_order int 0`; TS | `source` | `catalog_number(catalog,number)` | exclude |
| `radio_text` | SRC; `catalog varchar(16)`, `exercise_type varchar(32)`, `number int 0`, `course_codes varchar(255) NULL`, `source_text varchar(255) NULL`, `direction varchar(32) NULL`, `message_kind varchar(64) NULL`, `text_de`/`text_en`/`segments`/`spelling longtext NULL`, `sort_order int 0`, `rights_status varchar(20) 'unknown'`; TS | `source` | – | exclude |
| `radio_vocabulary` | SRC; `catalog varchar(16)`, `category varchar(255) NULL`, `term_de`/`term_en varchar(255)`, `hint varchar(255) NULL`, `course_codes varchar(255) NULL`, `variants longtext NULL`, `audio_media_id bigint NULL`, `sort_order int 0`, `rights_status varchar(20) 'unknown'`; TS | `source` | – | exclude |
| `device` | SRC; `model varchar(64)`, `catalogs varchar(64)`, `data longtext NULL`; TS | `source`, `model(model)` | – | exclude |
| `device_lesson` | SRC; `catalog varchar(16)`, `level int 1`, `title varchar(255)`, `devices longtext`, `start_state longtext NULL`, `scenario_ref varchar(191) NULL`, `model_solution longtext NULL`, `sort_order int 0`; TS | `source` | – | exclude |
| `device_step` | `lesson_id`, `sort_order int 0`, `goal`/`target varchar(255) NULL`, `help_text`/`device_overrides`/`receive_event`/`counterpart_text`/`checks longtext NULL` | `lesson_step(lesson_id,sort_order)` | – | exclude |
| `import_batch` | `package_id varchar(64)`, `schema_version varchar(16)`, `source_commit char(40) NULL`, `status varchar(20) 'uploaded'`, `manifest longtext`, `stats longtext NULL`, `storage_path varchar(255) NULL`, `error longtext NULL`, `created_by`/`approved_by bigint NULL`, `created_at` (Default jetzt), `approved_at`/`published_at datetime NULL` | – | `package_id`, `status` | reuse |
| `import_item_log` | `batch_id`, `entity varchar(32)`, `source_id varchar(191) NULL`, `action varchar(16)`, `message longtext NULL`, `created_at` | – | `batch_entity(batch_id,entity)`, `action`, `action_created(action,created_at)`, `created_at` | reuse |
| `audit_log` | `actor_user_id bigint NULL`, `action varchar(64)`, `object_type varchar(32) NULL`, `object_id varchar(191) NULL`, `reason`/`context longtext NULL`, `created_at` | – | `actor(actor_user_id)`, `object(object_type,object_id(64))`, `action`, `created_at` | reuse |
| `override` | `object_type varchar(32)`, `object_id bigint`, `field varchar(32)`, `value varchar(255) NULL`, `reason longtext`, `user_id bigint NULL`, `created_at` | `object_field(object_type,object_id,field)` | – | reuse |
| `attempt` | `user_id`, `question_id`, `revision_id`, `context varchar(32)`, `context_ref varchar(64) NULL`, `is_correct tinyint NULL`, `self_assessment varchar(16) NULL`, `answer_ms int NULL`, `idempotency_key char(36)`, `correction_of bigint NULL`, `created_at` | `idempotency_key` | `user_question(user_id,question_id)`, `user_created(user_id,created_at)`, `revision_id` | reuse |
| `attempt_answer` | `attempt_id`, `answer_id bigint NULL`, `answer_key varchar(16) NULL`, `free_text longtext NULL`, `position int 0` | – | `attempt_id`, `answer_id` | reuse |
| `progress` | `user_id`, `question_id`, `attempts`/`correct_count`/`wrong_count`/`streak int 0`, `mastery_score decimal(5,4) 0.0000`, `learn_status varchar(16) 'new'`, `first_answered_at`/`last_answered_at`/`due_at datetime NULL` | `user_question(user_id,question_id)` | `question_id`, `user_status(user_id,learn_status)` | reuse |
| `favorite` | `user_id`, `question_id`, `context varchar(32) 'question'`, `created_at` | `user_question_context(user_id,question_id,context)` | `question_id` | reuse |
| `favorite_collection` | `user_id`, `course_id`, `name varchar(80)`, `created_at` | – | `user_course(user_id,course_id)`, `course_id` | reuse |
| `favorite_collection_item` | `collection_id`, `question_id`, `created_at`; **PK** `(collection_id,question_id)` | – | `question_id` | reuse |
| `exam_session` | `user_id`, `course_id`, `rule_id`/`paper_id bigint NULL`, `mode varchar(16)`, `rule_snapshot longtext`, `status varchar(16) 'running'`, `start_token char(36)`, `started_at datetime`, `deadline_at`/`submitted_at datetime NULL`, `score`/`max_score int NULL`, `passed tinyint NULL`, `result longtext NULL` | `start_token` | `user_course(user_id,course_id)`, `status_deadline(status,deadline_at)`, `rule_id`, `paper_id` | reuse |
| `exam_session_question` | `session_id`, `position int`, `question_id`, `revision_id`, `answer_order`/`given_answer longtext NULL`, `self_assessed_known tinyint NULL`, `revealed_at datetime NULL`, `is_correct tinyint NULL`, `answered_at datetime NULL`, `flagged tinyint 0` | `session_position(session_id,position)` | `question_id`, `revision_id` | reuse |
| `navigation_progress` | `user_id`, `navigation_question_id`, `answer_text longtext NULL`, `self_assessment varchar(16) NULL`, `done tinyint 0`, `updated_at` (ON UPDATE) | `user_question(user_id,navigation_question_id)` | `navigation_question_id` | exclude |
| `praxis_progress` | `user_id`, `praxis_task_id`, `status varchar(16) 'open'`, `updated_at` | `user_task(user_id,praxis_task_id)` | `praxis_task_id` | exclude |
| `video_progress` | `user_id`, `lesson_id`, `completed tinyint 0`, `position_seconds int 0`, `last_access_at datetime` (Default jetzt) | `user_lesson(user_id,lesson_id)` | `lesson_id` | abstract |
| `radio_progress` | `user_id`, `item_type varchar(16)`, `item_source_id varchar(191)`, `done tinyint 0`, `streak`/`help_level int 0`, `data longtext NULL`, `updated_at` | `user_item(user_id,item_type,item_source_id)` | – | exclude |
| `entitlement` | `user_id`, `course_code`/`feature_code varchar(64)`, `status varchar(16) 'pending'`, `valid_from`/`valid_until datetime NULL`, `source varchar(16)`, `reference_key varchar(100)`, `order_id`/`order_item_id`/`product_id`/`variation_id bigint NULL`, `sku_snapshot varchar(100) NULL`, `granted_by bigint NULL`, `reason longtext NULL`, `idempotency_key varchar(100) NULL`, `meta longtext NULL`; TS | `reference_scope(reference_key,course_code,feature_code)` | `user_access(user_id,course_code,feature_code,status)`, `order_id`, `order_item_id`, `status_until(status,valid_until)` | abstract |
| `access_rule` | `course_code varchar(64)`, `module_code varchar(64) NULL`, `feature_code varchar(64)`, `access_type varchar(16) 'paid'`, `limit_type varchar(32) NULL`, `limit_value int NULL`, `valid_from`/`valid_until datetime NULL`; TS | – | `course_feature(course_code,feature_code)` | abstract |
| `product_access` | `product_id`, `variation_id bigint 0`, `course_code`/`feature_code varchar(64)`, `duration_days int NULL`, `renewal_mode varchar(16) 'extend'`, `refund_rule varchar(32) 'revoke_on_full_refund'`, `seats int NULL`, `active tinyint 1`; TS | `product_scope(product_id,variation_id,course_code,feature_code)` | `course_feature` | replace (R3) |
| `learning_group` | `owner_user_id`, `name varchar(190)`, `scopes longtext`, `seats int 0`, `status varchar(16) 'active'`, `valid_from`/`valid_until datetime NULL`, `order_id`/`order_item_id`/`product_id bigint NULL`, `join_token varchar(64)`, `reason longtext NULL`; TS | `order_item(order_item_id)`, `join_token` | `owner(owner_user_id)`, `order_id` | abstract |
| `learning_group_member` | `group_id`, `user_id bigint NULL`, `email varchar(190) NULL`, `status varchar(16) 'invited'`, `invite_token varchar(64) NULL`, `invited_at`/`joined_at`/`removed_at datetime NULL`; TS | `invite_token` | `group_status(group_id,status)`, `status_invited(status,invited_at)`, `email`, `user_id` | reuse |
| `exam_committee` | SRC; `commission varchar(190)`, `location_type`/`contact_person`/`street varchar(190) NULL`, `postal_code varchar(20) NULL`, `city varchar(190) NULL`, `phone`/`fax varchar(64) NULL`, `email varchar(190) NULL`, `website`/`office_hours varchar(255) NULL`, `note longtext NULL`, `source_url`/`course_codes varchar(255) NULL`, `sort_order int 0`, `rights_status varchar(20) 'unknown'`, `locally_modified tinyint 0`; TS | `source` | – | exclude |
| `exam_guide` | SRC; `tab varchar(64)`, `title varchar(190)`, `data longtext`, `course_codes varchar(255) NULL`, `sort_order int 0`, `rights_status varchar(20) 'unknown'`; TS | `source` | – | exclude |
| `mobile_session` | `user_id`, `access_hash`/`refresh_hash char(64)`, `prev_refresh_hash char(64) NULL`, `device_name varchar(100) ''`, `device_platform varchar(16) NULL`, `app_version varchar(40) NULL`, `created_at`/`last_used_at datetime`, `rotated_at datetime NULL`, `access_expires_at`/`refresh_expires_at`/`absolute_expires_at datetime`, `revoked_at datetime NULL`, `revoke_reason varchar(32) NULL` | `access_hash`, `refresh_hash` | `prev_refresh_hash`, `user_revoked(user_id,revoked_at)`, `absolute_expires_at` | abstract (E-C-04) |
| `mobile_idempotency` | `user_id`, `scope varchar(64)`, `idem_key char(36)`, `request_hash char(64)`, `status_code smallint unsigned 0`, `response longtext NULL`, `resource varchar(64) NULL`, `created_at`/`expires_at datetime` | `user_scope_key(user_id,scope,idem_key)` | `user_resource(user_id,resource)`, `expires_at` | abstract (E-C-04) |
| `mobile_export` | `user_id`, `public_id char(32)`, `status varchar(16) 'ready'`, `payload longtext NULL`, `size_bytes int unsigned 0`, `created_at datetime`, `started_at datetime NULL`, `expires_at datetime` | `public_id` | `user_id`, `expires_at` | abstract (E-C-04) |
| `mobile_web_code` | `user_id`, `session_id`, `code_hash char(64)`, `verifier_hash char(64) NULL`, `redirect_path varchar(500)`, `created_at`/`expires_at datetime`, `used_at datetime NULL` | `code_hash` | `user_id`, `session_id`, `expires_at` | abstract (E-C-04) |
| `mobile_rate_limit` | `bucket_hash char(64)` **PK**, `window_start int unsigned`, `hits int unsigned 0`, `expires_at datetime` | – | `expires_at` | reuse |

**Fremdschlüssel** (`Schema::foreignKeys()`, `Schema.php:871`; Name `{prefix}bpfk_{tabelle}_{spalte}` bzw. md5-gekürzt, Ziel immer `id`):
CASCADE: `course_module.course_id→course`, `course_module.module_id→module`, `answer.revision_id→question_revision`, `module_question.module_id→module`, `module_question.question_id→question`, `exam_blueprint.rule_id→exam_rule`, `exam_paper_question.paper_id→exam_paper`, `navigation_task_course.task_id→navigation_task`, `navigation_task_course.course_id→course`, `video_lesson_step.lesson_id→video_lesson`, `video_lesson_question.lesson_id→video_lesson`, `device_step.lesson_id→device_lesson`, `import_item_log.batch_id→import_batch`, `attempt_answer.attempt_id→attempt`, `favorite.question_id→question`, `favorite_collection.course_id→course`, `favorite_collection_item.collection_id→favorite_collection`, `favorite_collection_item.question_id→question`, `exam_session_question.session_id→exam_session`, `learning_group_member.group_id→learning_group`.
RESTRICT: `question_revision.question_id→question`, `content_media.media_id→media`, `exam_rule.course_id→course`, `exam_blueprint.module_id→module`, `exam_paper.course_id→course`, `exam_paper_question.question_id→question`, `navigation_question.task_id→navigation_task`, `praxis_task.course_id→course`, `video_module.course_id→course`, `video_lesson.module_id→video_module`, `video_lesson_question.question_id→question`, `attempt.question_id→question`, `attempt.revision_id→question_revision`, `progress.question_id→question`, `exam_session.course_id→course`, `exam_session_question.question_id→question`, `exam_session_question.revision_id→question_revision`, `navigation_progress.navigation_question_id→navigation_question`, `praxis_progress.praxis_task_id→praxis_task`, `video_progress.lesson_id→video_lesson`.
SET NULL: `praxis_task.media_id→media`. Insgesamt 41 Schlüssel.

**Listen:** `USER_DATA_TABLES` (`Schema.php:919`): `attempt, progress, favorite, favorite_collection, exam_session, navigation_progress, praxis_progress, video_progress, radio_progress, mobile_session, mobile_idempotency, mobile_export, mobile_web_code`. `PersonalData::CHILD_TABLES` (`PersonalData.php:26`): `attempt_answer`, `exam_session_question`, `favorite_collection_item`. `PersonalData::NO_PERSONAL_DATA_TABLES` (`PersonalData.php:36`).

**Migrationen:** Es gibt keine einzelnen Migrationsdateien. Stattdessen läuft `dbDelta` deklarativ auf alle Definitionen, danach folgen Fremdschlüssel, Autoload-Korrekturen und die Datenreparatur T196 (`Migrator.php:84`). Die Versionshistorie steht nur im Kommentar: 1.15.0 (Indizes `created_at` auf `audit_log` und `import_item_log`), 1.16.0 (Index `learning_group.order_id`), 1.17.0 (T196, `Migrator.php:19`).

### 3.2 Options-Keys

**`bp_learning_settings`** (`Settings::OPTION`, eine Option, `register_setting('bp_learning', …)`, `show_in_rest=false`; Werte nach `Settings.php:14` und `:97`):

| Schlüssel | Default | Sanitize-Regel | Klassifikation |
|---|---|---|---|
| `delete_data_on_uninstall` | `false` | `!empty` | reuse |
| `radio_voice_output` | `'browser'` | `browser`/`recording`/`off`, sonst `browser` | exclude |
| `default_access` | `'logged_in'` | `logged_in`/`entitled_only`, sonst `logged_in` | abstract |
| `exam_guide_enabled` | `false` | `!empty` | exclude |
| `brand_name` | `''` | `sanitize_text_field`, höchstens 60 Zeichen, `XXXX` wird zu `''` | abstract |
| `price_note` | `'inkl. MwSt.'` | `sanitize_text_field`, höchstens 80 Zeichen | replace (R3) |
| `expiry_reminder_enabled` | `true` | fehlt der Schlüssel, gilt der Default | abstract |
| `expiry_reminder_days` | `14` | Ganzzahl > 0, höchstens 90, sonst Default | abstract |
| `privacy_requests_auto` | `true` | fehlt der Schlüssel, gilt der Default | reuse (Default → E-C-05) |
| `video_captions` | `'off'` | `off`/`vtt` | abstract |
| `update_check_enabled` | `false` | `!empty` | replace (R3) |

**Weitere eigene Optionen** (Autoload laut Code):

| Option | Inhalt / Default | Autoload | Quelle |
|---|---|---|---|
| `bp_learning_schema_version` | z. B. `1.17.0` | ja | `Migrator.php:26`, `:143` |
| `bp_learning_migration_lock` | Sperrzeile `<Besitzer>\|<Zeit>`, TTL 1800 s | off (INSERT IGNORE) | `Migrator.php:27`, `Lock.php:103` |
| `bp_learning_fk_issues` | `[Name => Meldung]`, fehlt = alles da | nein | `Migrator.php:33` |
| `bp_learning_migration_failed` | `{at, message(≤500)}` | nein | `Plugin.php:347`, `:512` |
| `bp_learning_cron_errors` | Liste `{task,item,message,at}`, höchstens 20 | nein | `Cron.php:19` |
| `bp_cron_last_hourly` | Unix-Zeit des letzten Stundenlaufs | nein | `Monitor.php:24`, `Plugin.php:310` |
| `bp_learning_import_lock` | Sperre `<batch>-<zufall>\|<zeit>`, TTL 900 s | off | `ImportService.php:48` |
| `bp_learning_retention_lock` | Sperre, TTL 3600 s | off | `Retention.php:40` |
| `bp_learning_active_package` | `package_id` des aktiven Pakets | ja | `ImportService.php:524` |
| `bp_learning_packages_suffix` | Geheimnis aus 24 Zeichen | nein | `MediaStorage.php:32`, `:569` |
| `bp_learning_protected_suffix` | Geheimnis aus 24 Zeichen | nein | `MediaStorage.php:33` |
| `bp_learning_storage_layout` | `{packages, protected, failed{kind:ts}, errors{kind:msg}, media_checked}` | ja | `MediaStorage.php:35`, `:453` |
| `bp_learning_import_drop_moved` | `{at, moved[], left[]}` | nein | `ImportSources.php:33` |
| `bp_learning_content_generation` | Generation des Transient-Cache | ja | `ContentCache.php:24` |
| `bp_learning_update_check` | `{checked_at, latest, url, security, error}` | nein | `UpdateCheck.php:31` |
| `bp_learning_login_dummy_hash` | Passwort-Hash für die Dummy-Prüfung | nein | `LoginGuard.php:56` |
| `bp_entitlement_prune_last` | Unix-Zeit des letzten vollständigen Laufs | nein | `PersonalData.php:119`, **wird bei der Deinstallation nicht gelöscht** |
| `bp_privacy_endpoint_version` | `'1'` (Rewrite-Stand „meine-daten“) | ja | `SelfService.php:23` |
| `bp_learning_voucher_with_member_discount` | `'yes'` erlaubt die Kombination; wird hier nur gelesen | – | `Vouchers.php:41` |
| `bp_learning_rewrite_version` (`Router::REWRITE_OPTION`) | wird bei Aktivierung und Deaktivierung gelöscht | – | `Plugin.php:92`, `:125` (Router: Teil D) |

Gelesene fremde Optionen: `timezone_string`, `date_format`, `woocommerce_email_from_address`, `woocommerce_email_from_name`, `woocommerce_store_address`, `woocommerce_store_address_2`, `woocommerce_store_postcode`, `woocommerce_store_city`, `woocommerce_erasure_request_removes_order_data`. Autoload-Korrekturen in der Migration betreffen `MobileSettings::OPTION`, `bp_learning_withdrawal_autoenabled`, `Tips::SEEDED_OPTION` und `Tips::VERSION_OPTION` (`Migrator.php:145`).

**Deinstallation** (`Uninstaller.php:41`): Präfixe `bp_learning_`, `bp_dispute_lock_`, `bp_voucher_grant_`; feste Namen `bp_mail_test`, `bp_cron_last_hourly`, `bp_privacy_endpoint_version`.

### 3.3 Meta, Cookies, Konstanten

| Art | Schlüssel | Bedeutung | Quelle |
|---|---|---|---|
| Nutzer-Meta | `_bp_tickets` | Ticketliste (höchstens 100) | `Tickets.php:22` |
| Nutzer-Meta | `_bp_ticket_id` (mehrfach) | Suchschlüssel je Ticket | `Tickets.php:23` |
| Nutzer-Meta (Fremdklassen, beim Löschen erfasst) | `bp_app_settings`, `bp_expiry_reminders`, `bp_expiry_reminder_optout`, `bp_notices_dismissed`, `bp_money_back_used`, `bp_money_back_downloads` | App-Einstellungen, Erinnerungen, Widerspruch, Admin-Hinweise, Garantie | `Uninstaller.php:63` |
| Post-Meta (Anhang) | `_bp_protected_path` | absoluter Pfad einer geschützten Datei | `MediaStorage.php:463`, `BackupCheck.php:209` |
| Post-Meta (Anhang) | `_bp_chart` | Seekarten-Code (exclude) | `Uninstaller.php:116` |
| Post-Meta (Produkt) | `_bp_catalog_price`, `_bp_catalog_status`, `_bp_manual_status`, `_bp_price_history` | Katalog-Merkwerte; werden gelöscht. `_bp_catalog_fields` wird **nicht** gelöscht. | `Uninstaller.php:141` |
| Post-Meta (`user_request`) | `_bp_account_deleted`, `_bp_account_removed_from_site` | Zeitpunkt der Kontolöschung bzw. Entfernung von dieser Website | `AccountDeletion.php:45`, `:47` |
| Post-Meta (`user_request`, Core) | `_export_data_grouped` (temporär), `_export_file_name` | Exportablauf | `AccountDeletion.php:411`, `:414` |
| Bestell-Meta | `_bp_voucher_code_hash`, `_bp_voucher_order`, `_bp_paid_by_voucher` | Gutschein-Einlösung | `Vouchers.php:35` |
| Bestell-Meta | `_bp_money_back_request` (`user_id` wird beim Anonymisieren entfernt) | Garantie-Anfrage | `AccountDeletion.php:299` |
| Cookie | `bp_known_device` | `v1.<id>.<ablauf>.<hmac>`, HttpOnly, SameSite=Lax, 180 Tage | `KnownDevice.php:25` |
| Konstante | `BP_LEARNING_FILE`, `BP_LEARNING_DIR`, `BP_LEARNING_VERSION` | Bootstrap | `bootspruefung-learning.php:24`, `:47` |
| Konstante (wp-config) | `BOOTSPRUEFUNG_PRIVATE_DIR`, `BOOTSPRUEFUNG_MONITOR_KEY`, `BOOTSPRUEFUNG_UPDATE_SOURCE` | privater Ordner, Monitor-Schlüssel, Update-Quelle | `MediaStorage.php:108`, `Monitor.php:52`, `UpdateCheck.php:61` |

### 3.4 Transients und Object-Cache

| Schlüssel | TTL | Zweck | Quelle |
|---|---|---|---|
| `bp_learning_cc_<md5(key:generation)>` | 1 Tag | Teure Inhaltsabfragen ohne persistenten Object-Cache; `invalidate()` löscht sie per SQL | `ContentCache.php:43` |
| `bp_learning_video_hosts` | – | Videohosts (wird beim Invalidieren gelöscht) | `ContentCache.php:84` |
| `bp_privacy_notice_<uid>` | 10 min | Rückmeldung „Meine Daten“ | `SelfService.php:131` |
| `bp_voucher_notice_<uid>` | 10 min | Rückmeldung zur Gutschein-Einlösung | `Vouchers.php:345` |
| Object-Cache-Gruppe `bootspruefung` | 1 Tag | Einträge `<key>:<generation>`, Schlüssel `generation` | `ContentCache.php:19` |
| Deinstallation | – | Transient-Präfixe `bp_learning_`, `bp_money_back_notice_`, `bp_price_errors_`, `bp_price_input_`, `bp_catalog_skipped_`, `bp_privacy_notice_`, `bp_voucher_notice_`, `bp_protect_notice_`, `bp_oss_totals_`, `bp_paypal_token_`, `bp_webhook_rejected_`, `bp_dispute_`, `bp_rl_`; fest: `bp_paypal_verify_calls` | `Uninstaller.php:51` |

### 3.5 Upload- und Ablageverzeichnisse

| Pfad | Inhalt | Schutz | Quelle |
|---|---|---|---|
| `uploads/bootspruefung/media/<storage_key>` | ausgelieferte Medien (öffentlich) | erlaubte Typen, `.htaccess` sperrt Skripte, `index.php` | `MediaStorage.php:129` |
| `<privat>/packages-<geheim>/batch-<id>/` | Paketkopie je Import (Rollback-Ziel) | Geheimnis, `.htaccess` „Require all denied“, `index.php` | `ImportService.php:676` |
| `<privat>/packages-<geheim>/upload-<zufall>/` | Zwischenordner beim Entpacken | wie oben; Waisen nach 24 h gelöscht | `ImportService.php:89`, `Retention.php:129` |
| `<privat>/packages-<geheim>/download-<zufall>.zip` | Link-Download | wie oben; nach 24 h gelöscht | `ImportSources.php:268`, `Retention.php:139` |
| `<privat>/packages-<geheim>/import/` | Ablageordner (SFTP) | wie oben | `ImportSources.php:57` |
| `<privat>/packages-<geheim>/quarantine/` | nicht erlaubte Altmedien | wie oben | `MediaStorage.php:377` |
| `<privat>/protected-<geheim>/` | geschützte Dateien (Auslieferung nur über PHP) | wie oben | `MediaStorage.php:124` |
| `<privat>` | `BOOTSPRUEFUNG_PRIVATE_DIR` bzw. Filter, sonst `uploads/bootspruefung` | außerhalb des Webroots möglich | `MediaStorage.php:84` |
| Legacy | `uploads/bootspruefung/packages/`, `uploads/bootspruefung-import/` | werden verschoben bzw. bei der Deinstallation gelöscht | `MediaStorage.php:433`, `ImportSources.php:30` |
| Core | `wp_privacy_exports_dir()` | Datenschutz-Export-ZIP | `AccountDeletion.php:382` |

## 4. Schnittstellen

### 4.1 Hooks aus `Plugin::registerHooks()` (`src/Plugin.php:231`, vollständig)

| Zeile | Hook / Aufruf | Callback | Priorität |
|---|---|---|---|
| 233 | `init` | `loadTextDomain` | 10 |
| 234 | `plugins_loaded` | `maybeMigrate` | 5 |
| 235 | `bp_learning_migrate` | `Cron::run('migrate', cronMigration)` | 10 |
| 238 | `admin_init` | `Settings::register` | 10 |
| 240 | Filter `wpmu_drop_tables` | `dropTablesOnSiteDelete` (2 Argumente) | 10 |
| 242 | `Compatibility::register($file)` | Woo-HPOS- und Block-Kompatibilität (Teil B) | – |
| 243 | `plugins_loaded` | `WooCommerce\Integration::register` | 20 |
| 245 | `VoucherGrants::register()` | Grant-Vertrag v1 (Teil B) | – |
| 246 | `(new Menu($this))->register()` | Admin (Teil D) | – |
| 247 | `(new RestRegistrar())->register()` | REST (Teil D) | – |
| 248 | `RequestMemo::register()` | `rest_request_before_callbacks`/`rest_request_after_callbacks` (10, 3 Argumente) | – |
| 250 | `Maintenance::register()` | Filter `rest_pre_dispatch` → `guard` (5, 3 Argumente) | – |
| 251 | `MailStatus::register()` | Teil D | – |
| 252 | `Monitor::register()` | `rest_api_init` → Route `/monitor`; `admin_notices` → `timezoneNotice` | – |
| 253 | `UpdateCheck::register()` | `bp_learning_hourly` → `maybeRun` (20); `admin_notices` → `notice` (20); `update_option_bp_learning_settings` → `settingsChanged` (10, 2 Argumente) | – |
| 254 | `SiteHealth::register()` | Teil D | – |
| 255 | `(new Router($this))->register()` | Teil D | – |
| 256 | `ReturnAfterLogin::register()` | Teil D | – |
| 257 | `bp_learning_hourly` | `Plugin::hourly` | 10 |
| 259 | `init` | `ensureSchedules` | 30 |
| 260 | `bp_learning_reconcile` | `Cron::guard('order_reconcile', AutoReconcile::run)` | 10 |
| 261 | `bp_learning_daily` | `ExpiryReminder::run` | 10 |
| 262 | `bp_learning_expiry_reminder_continue` | `ExpiryReminder::resume` (2 Argumente) | 10 |
| 263 | `(new ReminderOptOut())->register()` | Teil B | – |
| 264 | `PersonalData::register()` | siehe 4.5 | – |
| 266 | `Extensions::register()` | `Vouchers::register`, `Tickets::register`, Filter `bootspruefung_user_owns_course` (10, 3 Argumente) | – |
| 267 | `Hardening::register()` | siehe 4.2 | – |
| 269 | Filter `kurswasser_consent_external_hosts` | `VideoService::externalHosts` hinzufügen | 10 |
| 270–276 | `ContentGate`, `ProtectedFiles`, `Charts`, `Tips`, `Faq`, `CustomerFaq`, `DisputeWebhooks` → `register()` | Teile A, B, D | – |
| 278–280 | nur mit `WP_CLI` | `Commands::register()` | – |

Aktivierung und Deaktivierung: `register_activation_hook` → `Plugin::activate`, `register_deactivation_hook` → `Plugin::deactivate` (`bootspruefung-learning.php:43`).

### 4.2 Weitere WordPress-Hooks im Bereich C

| Datei:Zeile | Hook | Zweck |
|---|---|---|
| `Hardening.php:36` | Filter `rest_endpoints` | `/wp/v2/users` ohne `edit_posts` entfernen |
| `Hardening.php:45` | `template_redirect` (1) | Autorenarchiv mit 301 auf die Startseite |
| `Hardening.php:51` | Filter `oembed_response_data` (20) | Autor entfernen |
| `Hardening.php:58` | Filter `wp_sitemaps_add_provider` (10, 2) | keine Benutzer-Sitemap |
| `Hardening.php:61`, `:62` | Filter `wpseo_sitemap_exclude_author` (99), `option_wpseo_titles` (99) | Yoast-Autoren |
| `Hardening.php:72`, `:73`, `:74` | Filter `xmlrpc_enabled` (99), `xmlrpc_methods` (99), `wp_headers` | XML-RPC und Pingback aus |
| `Hardening.php:83` | `lostpassword_post` (99, 2) | `LoginGuard::lostPassword` |
| `Hardening.php:87` | Filter `wp_is_application_passwords_available_for_user` (10, 2) | nur Redaktion |
| `Hardening.php:90` | `woocommerce_save_account_details_errors` (10, 2) | Passwort für E-Mail-Wechsel |
| `Hardening.php:92` | Filter `the_author` (99) | Feed-Autor |
| `Hardening.php:94`, `:95` | Filter `woocommerce_registration_errors`, `registration_errors` (5, 3) | Registrierungslimit |
| `Hardening.php:97` | `send_headers` | Sicherheits-Header |
| `Hardening.php:101` | `admin_init` | `MediaStorage::relocateIfPending` |
| `Hardening.php:102` | `bp_learning_hourly` | `Cron::guard('media_relocate', MediaStorage::relocateAll)` |
| `LoginGuard.php:60`, `:61` | Filter `authenticate` (1 und 100, 3) | Vormerken bzw. Auswerten |
| `LoginGuard.php:65`, `:66`, `:67` | Filter `woocommerce_lost_password_confirmation_message`, `wp_login_errors`, `shake_error_codes` | Hinweise |
| `LoginGuard.php:69`, `:70` | Filter `lostpassword_errors` (PHP_INT_MAX, 2), `lostpassword_post` (PHP_INT_MAX, 2) | frühe Antwort |
| `LoginGuard.php:468`, `:472` | Filter `wp_redirect` (PHP_INT_MAX), dynamisch | Weiterleitung unterdrücken bzw. strecken |
| `KnownDevice.php:30`–`:32` | `wp_login` (10, 2), `after_password_reset`, `woocommerce_customer_reset_password` | Gerätecookie |
| `AfterResponse.php:42` | `shutdown` (0) | verschobene Arbeit |
| `SelfService.php:28`–`:33` | `woocommerce_get_query_vars`, `woocommerce_account_menu_items` (30), `woocommerce_account_meine-daten_endpoint`, `woocommerce_endpoint_meine-daten_title`, `admin_post_bp_privacy_request`, `init` (99) | „Meine Daten“ |
| `Vouchers.php:57`–`:59` | `bootspruefung_voucher_redeemed` (10, 4), `admin_post_bp_voucher_redeem`, Filter `bootspruefung_coupon_allow_with_member_discount` (10, 3) | Gutschein |
| `Tickets.php:30`–`:32` | `bootspruefung_ticket_issued` (10, 4), `…_redeemed` (10, 3), `…_cancelled` (10, 2) | Tickets |

### 4.3 Cron-Ereignisse

| Hook | Intervall | Eingeplant von | Handler (Teilaufgaben) |
|---|---|---|---|
| `bp_learning_hourly` (`Cron::HOURLY`) | `hourly`, erster Lauf `time()+3600` | `ensureSchedules` (`Plugin.php:290`; Aktivierung und jede Anfrage auf `init`/30) | `Plugin::hourly`: `exam_expire`, `entitlement_expire`, `group_invites_expire`, `expiry_reminder_schedule`, `order_reconcile`, `import_retention`; dazu `update_check` (20, `UpdateCheck.php:47`), `media_relocate` (`Hardening.php:102`), `audit_prune` (`PersonalData.php:70`), `mobile_cleanup` (`Mobile/MobileApi.php:54`, Teil D) |
| `bp_learning_daily` (`ExpiryReminder::HOOK`) | `daily`, `time()+3600` | `ExpiryReminder::schedule` (`Commerce/ExpiryReminder.php:63`) | `ExpiryReminder::run` |
| `bp_learning_expiry_reminder_continue` | einmalig `+60 s`, Argumente `[now, afterUserId]` | `ExpiryReminder.php:239` | `ExpiryReminder::resume` |
| `bp_learning_reconcile` | einmalig `+60 s` | `AutoReconcile.php:226` | `AutoReconcile::run` (guard) |
| `bp_learning_migrate` | einmalig zu `retryAt()` (sofort oder nach 15 min Backoff) | `Plugin::scheduleMigration` (`Plugin.php:467`) | `cronMigration` |
| `bp_mobile_export_generate` | Action Scheduler async (Gruppe `bootspruefung-learning`), sonst einmalig `time()` | `PrivacyController.php:176` (Teil D) | App-Export |
| `bp_privacy_export_generate` | Action Scheduler async (Gruppe `bootspruefung-learning`), sonst einmalig `time()`, Argument `[requestId]` | `AccountDeletion::scheduleExport` (`AccountDeletion.php:353`) | `processExport` |

Alle sieben Hooks stehen in `Plugin::CRON_HOOKS` (`Plugin.php:133`) und werden bei Deaktivierung und Deinstallation ausgetragen, auch im Action Scheduler.

### 4.4 WP-CLI (`wp bootspruefung …`, `Commands.php:18`)

| Kommando | Argumente | Zeile | Kl. |
|---|---|---|---|
| `status` | `[--format=table\|json]` | 46 | reuse |
| `backup-check` | `[--format=table\|json]`; Exit 1 bei `fail` | 78 | reuse |
| `migrate` | – (Fehler bei neuerem Schema oder belegter Sperre) | 627 | reuse |
| `import` | `<paket>` (ZIP oder Ordner) bzw. `resume <id>` / `discard <id>`; `[--dry-run] [--yes] [--note=<text>] [--restore]` | 129 | reuse |
| `rollback` | `<import-id> --reason=<text> [--yes]` | 242 | reuse |
| `reconcile` | `[--apply] [--order=<id>] [--since=<YYYY-MM-DD>] [--yes]` | 353 | replace (R3) |
| `access` | `<user>` (ID, Login oder E-Mail) | 571 | reuse |
| `shop setup` | `[--dry-run] [--publish] [--overwrite-prices=<skus\|all>] [--publish-held=<skus\|all>] [--only=products\|free] [--yes]` | 430 | exclude |
| `rights release` | `[--reason=<text>] [--dry-run] [--exam-rules] [--yes]` | 492 | abstract |
| `export access-rules` | `<tenant-slug> --output=<datei>` | 655 | replace |
| `export course-files` | `<tenant-slug> --media-root=<dir> --output=<datei>` | 733 | exclude |
| `export entitlements` | `<tenant-slug> --output=<datei>` | 870 | replace |
| `export customer-faqs` | `[--output=<datei>]` (sonst stdout) | 1039 | exclude |
| `chart` | `<status\|upload\|license\|unlicense> [<code>=D49] [<datei>] [--note=<text>]` | 531 | exclude |
| `cleanup-sites` | `[--apply]` (mit Rückfrage) | 593 | abstract |

Kleiner Befund: Der Docblock „Führt ausstehende Datenbankmigrationen aus.“ (`Commands.php:583`) steht vor `cleanupSites`, `migrate()` (`:627`) hat keinen eigenen Docblock. In der WP-CLI-Hilfe fehlt deshalb die Beschreibung von `migrate`.

### 4.5 Datenschutz: Exporter und Eraser

| Registrierung | Schlüssel / Name | Verhalten | Quelle |
|---|---|---|---|
| `wp_privacy_personal_data_exporters` | `bootspruefung-learning` / „Bootsprüfung – Lernstand“ | Eine Tabelle pro Seite, 2 000 Zeilen je Seite; Geheimnisse ausgelassen (`idempotency_key`, `join_token`, `invite_token`, Token-Hashes, `request_hash`, `response`, `public_id`, `payload`, `code_hash`, `verifier_hash`); Seite 1 zusätzlich mit App-Einstellungen, Tickets, Erinnerungen und Garantie | `PersonalData.php:47`, `:237` |
| `wp_privacy_personal_data_erasers` | `bootspruefung-learning` / „Bootsprüfung – Lernstand“ | Ohne Konto werden nur Gruppeneinladungen bereinigt. Mit Konto: Transaktion, Kindtabellen, `USER_DATA_TABLES`, Meta, Gruppen, Audit-Pseudonymisierung. `items_retained` meldet Zugänge, eigene Gruppen, Widerspruch, Verwaltungsspuren und Audit. | `PersonalData.php:55`, `:366` |
| `deleted_user` | – | Plätze freigeben, löschen, Zugänge schließen (Multisite: alle Websites mit Tabellen) | `PersonalData.php:65` |
| `user_request_action_confirmed` (20) | – | Löschantrag sofort ausführen, Export einplanen (`privacy_requests_auto`) | `AccountDeletion.php:53` |
| `wp_privacy_personal_data_erased` (20) | – | Woo-Bestellungen anonymisieren, Konto löschen (nicht bei geschützten Konten) | `AccountDeletion.php:54` |
| `login_form_confirmaction` (1) | – | Knopfseite statt sofortiger Löschung | `AccountDeletion.php:59` |
| Filter `user_request_action_confirmed_message`, `user_request_action_email_content`, `user_erasure_fulfillment_email_content` (20) | – | Texte | `AccountDeletion.php:56`, `:60`, `:62` |
| `woocommerce_privacy_remove_order_personal_data` / Filter `…_props` (99) | – | Garantie-Meta bereinigen, Steuerfelder behalten | `AccountDeletion.php:64`, `:67` |

### 4.6 REST, admin-post, Konstanten

- `GET /wp-json/bootspruefung/v1/monitor` (`Monitor.php:30`), `permission_callback` = Schlüssel-Abgleich (`X-Monitor-Key` oder `?key=`, `hash_equals`) bzw. offen ohne Konstante (nur Gesamtstatus). Antwort `{status: ok\|error, checks?}`, Status 200 oder 503, `Cache-Control: no-store`. Hinweis: Ein Schlüssel in `?key=` landet in Server-Logs.
- Wartungs-Guard für alle schreibenden Anfragen an `/bootspruefung/v1` und `/boatingschool/v1` (`Maintenance.php:59`).
- `admin-post.php?action=bp_privacy_request` (Nonce `bp_privacy_request`, `SelfService.php:123`), `action=bp_voucher_redeem` (Nonce `bp_voucher_redeem`, `Vouchers.php:337`).

### 4.7 Eigene Hooks und Filter (`do_action`/`apply_filters`)

| Name | Art | Parameter | Quelle |
|---|---|---|---|
| `bootspruefung_learning_contract` | Filter | `{version, contract}` (nur Tests) | `functions.php:50` |
| `bootspruefung_named_lock_timeout` | Filter | Sekunden (5), Name | `NamedLock.php:51` |
| `bootspruefung_update_source` | Filter | URL (nur https) | `UpdateCheck.php:62` |
| `bootspruefung_private_dir` | Filter | absoluter Pfad | `MediaStorage.php:110` |
| `bootspruefung_media_allowed_types` | Filter | Endung → MIME-Liste (ohne `DANGEROUS`) | `MediaStorage.php:209` |
| `bootspruefung_import_keep_packages` | Filter | Anzahl (2) | `Retention.php:72` |
| `bootspruefung_import_log_unchanged_days` / `bootspruefung_import_log_days` | Filter | Tage (14 / 180) | `Retention.php:154`, `:155` |
| `bootspruefung_hardening` | Filter | bool, Merkmal | `Hardening.php:182` |
| `bootspruefung_security_headers` | Filter | Header-Array | `Hardening.php:164` |
| `bootspruefung_login_limits` | Filter | `{ip:10, login_ip:5, login:30}` | `LoginGuard.php:82` |
| `bootspruefung_login_locked` | Action | Login, IP | `LoginGuard.php:286`, `:298`, `:308`, `:318` |
| `bootspruefung_defer_after_response` | Filter | bool | `AfterResponse.php:30` |
| `bootspruefung_neutral_response_seconds` | Filter | Sekunden (2, 0–10) | `AfterResponse.php:75` |
| `bootspruefung_mail_footer` | Filter | Fuß, Empfänger, Betreff | `Mail.php:80` |
| `bootspruefung_account_deletion_protected` | Filter | bool, `WP_User` | `AccountDeletion.php:91` |
| `bootspruefung_retained_order_fields` | Filter | Feldliste | `AccountDeletion.php:279` |
| `bootspruefung_entitlement_retention_years` | Filter | Jahre (10) | `PersonalData.php:136` |
| `bootspruefung_before_erase_user` | Action | userId | `PersonalData.php:415` |
| `bootspruefung_user_owns_course` | Filter (konsumiert) | bool, userId, courseCode | `Extensions.php:25` |
| `bootspruefung_practice_region` | Filter | Region, userId, courseCode | `Extensions.php:60` |
| `bootspruefung_practice_offers` | Filter | Angebote, courseCode, Region | `Extensions.php:81` |
| `bootspruefung_tickets_for_user` | Filter | null oder Liste, userId | `Tickets.php:123` |
| `bootspruefung_ticket_issued`/`_redeemed`/`_cancelled` | Action (konsumiert) | siehe 4.2 | `Tickets.php:30` |
| `bootspruefung_voucher_redeem` | Filter | null, Array oder WP_Error; Code, userId | `Vouchers.php:248` |
| `bootspruefung_voucher_redeem_per_ip` | Filter | Grenze (30) | `Vouchers.php:66` |
| `bootspruefung_voucher_redeemed` | Action (konsumiert) | Code, userId, productId, args | `Vouchers.php:57` |
| `bootspruefung_voucher_order_created` | Action | orderId, userId, productId, args | `Vouchers.php:165` |
| `bootspruefung_voucher_redeem_failed` | Action | Code, userId, WP_Error | `Vouchers.php:268` |
| `bootspruefung_coupon_allow_with_member_discount` | Filter (konsumiert) | allow, coupon, cart | `Vouchers.php:59` |
| `wp_privacy_personal_data_erased` | Action (Core, selbst ausgelöst) | requestId | `AccountDeletion.php:180` |

Alle Namen beginnen mit `bootspruefung_`. Nach Kap. 26.2 werden sie zu `course_captain_*` und mit Version und Deprecation-Policy beschrieben (R0-19).

## 5. Zustände und Locks

### 5.1 Migrationsablauf

1. `plugins_loaded`/5 ruft `maybeMigrate()` auf (`Plugin.php:361`). Ist das Schema neuer als das Plugin, erscheint nur ein Admin-Hinweis, es wird nichts migriert (`schemaIsNewer`).
2. `needsMigration()` ist wahr, wenn keine oder eine ältere `bp_learning_schema_version` gespeichert ist.
3. `migrationAction(cli, admin, cron, failed, now, ajax)` (`Plugin.php:436`) entscheidet: CLI ergibt immer `run`. Innerhalb des Backoffs (`failed.at + 900 s`) ergibt sich `wait`. Cron und Admin (ohne admin-ajax/admin-post) ergeben `run`. Besucheranfragen ergeben `schedule` (einmaliges Ereignis `bp_learning_migrate`).
4. Während die Migration fällig ist, bekommen schreibende Anfragen an die Lern-API 503 mit `Retry-After: 30` und lösen `spawn_cron` aus (`Maintenance.php:59`).
5. `Migrator::migrate()` (`:84`): neueres Schema wird zum No-op; `NamedLock('schema-migration', 0)` wird geholt, sonst `false`. Ist die Options-Sperre belegt und gilt `GET_LOCK` als echt, wird die tote Sperre übernommen (T202). Dann `set_time_limit(0)`, `ignore_user_abort`, InnoDB-Prüfung, `dbDelta` je Tabelle mit gesammelten Fehlern, Prüfung „Tabelle fehlt“ und `missingSchemaParts`, jeweils mit `RuntimeException` ohne Versionswechsel. Fremdschlüssel werden angelegt, Probleme landen in `bp_learning_fk_issues` (nicht fatal). Danach Version setzen, Autoload-Korrekturen, Reparatur T196. Zum Schluss beide Sperren freigeben.
6. `runMigration()` (`Plugin.php:493`): Ein Fehler wird als `bp_learning_migration_failed {at, message}` gespeichert, nach dem Backoff neu geplant und als Admin-Hinweis (`migration_failed`) gezeigt. Bei Erfolg wird die Option gelöscht.
7. Aktivierung: `migrate()` direkt; bei `RuntimeException` folgt `wp_die` vor `ensureSchedules` (keine Cron-Reste, T146).
8. Manuell: `wp bootspruefung migrate`; Diagnose über `BackupCheck::schema`, Monitor-Prüfung `migration`, Systemstatus.

### 5.2 Import-Zustände (`ImportService`)

```
uploaded ──validate──▶ validated ──preview (ggf. Teilschritte)──▶ previewed ──approve──▶ approved ──runStep──▶ importing ──…──▶ published
   │                     │                                          │                                 │
   └──▶ failed           └─(neue Vorschau setzt previewed→validated)┘                                 ├─ Fehler im Schritt: error gesetzt  ┐ „abgebrochen“
                                                                                                       └─ kein Heartbeat > 600 s (stale)  ┘ (isAborted)
discard: aus jedem Zustand außer published und laufendem importing ──▶ discarded (bei abgebrochenem Import zusätzlich stats.aborted_at = Mischstand)
```

- **Status-Spalte:** `uploaded`, `validated`, `failed`, `previewed`, `approved`, `importing`, `published`, `discarded` (`ImportService.php:17`).
- **`stats`-Schlüssel:** `cursor{phase,offset}`, `actions{phase:{create,update,unchanged,archive}}`, `heartbeat`, `preview` (Zusammenfassung plus `_rights`), `preview_at`, `preview_progress{entity,offset,summary,started_at}`, `rollback_of`, `rollback_reason`, `aborted_at`.
- **Phasen:** `courses → modules → media → questions → module_links → course_links → exam_rules → exam_papers → navigation_tasks → praxis_tasks → video_modules → radio_texts → radio_vocabulary → radio_practice → device_simulation → exam_committees → exam_guides → archive` (`:27`).
- **Grenzen:** 200 Datensätze je Schritt (Admin) bzw. 500 (CLI), Medien höchstens 64 MB je Schritt, Vorschau-Budget 20 s, Prüfung alle 50 Datensätze.
- **Invarianten:** nur ein lebender `importing` gleichzeitig (`:407`); ein inzwischen veröffentlichter neuerer Import macht Vorschau und Freigabe ungültig (`supersededBy`); der Zustand wird nach der Sperre neu gelesen (T199); jeder Schritt läuft in `START TRANSACTION … COMMIT/ROLLBACK`. Achtung: Mediendateien werden außerhalb der Transaktion geschrieben, die Prüfsumme wird beim Wiederholen erneut verglichen (T203). Nach jedem Schritt wird `ContentCache::invalidate` aufgerufen.
- **Recovery:** `resume` (CLI, Admin) setzt einen `approved`/`importing`-Import fort; `discard` ist möglich, wenn der Import abgebrochen ist und kein lebender Schritt mehr die Sperre hält (T208); `restoreActive`/`rollback` legen einen neuen Import aus der Paketkopie an, der den normalen Prüf- und Freigabeweg durchläuft.
- **Protokoll-Aktionen** (`import_item_log.action`): `create`, `update`, `unchanged`, `archive`, `rejected` (Medientyp), `error`.

### 5.3 Sperren

| Sperre | Mechanismus | Name | TTL / Wartezeit | Besitzer | Nutzer |
|---|---|---|---|---|---|
| Migration (primär) | `GET_LOCK` (`NamedLock`) | `schema-migration` | 0 s warten, endet mit der Verbindung | Verbindung | `Migrator.php:89` |
| Migration (Rückfall) | Optionszeile (`Lock`) | `bp_learning_migration_lock` | 1 800 s | zufällig | `Migrator.php:379` |
| Import | Optionszeile | `bp_learning_import_lock` | 900 s (Stale-Grenze 600 s) | `<batch>-<zufall12>` | `ImportService.php:699` |
| Retention | Optionszeile | `bp_learning_retention_lock` | 3 600 s | zufällig | `Retention.php:40` |
| Gutscheincode | `GET_LOCK` | `voucher:<sha256(code)>` | 10 s warten | Verbindung | `Vouchers.php:133` |
| Weitere NamedLock-Nutzer (andere Teile) | `GET_LOCK` | Gruppenbeitritt, Prüfungsstart, Geld-zurück | Standard 5 s | Verbindung | Kommentar `NamedLock.php:25` |

`NamedLock` (`NamedLock.php:42`): Der Name in der Datenbank ist `bp:` + 40 Hexzeichen aus `sha256(DB_NAME|prefix|name)`. Die Sperre ist wiedereintrittsfähig (Zähler). Liefert `GET_LOCK` `NULL`, gilt die Sperre als gehalten und `isReal` ist falsch (die Aufrufer prüfen fachlich nach). Bei `0` ist die Sperre belegt. Vor dem Sperren leitet `toPrimary()` Lesezugriffe auf den Primärserver (HyperDB/LudicrousDB).
`Lock` (`Lock.php:30`): `INSERT IGNORE` mit dem Wert `<owner>|<time>`. Eine abgelaufene Sperre wird nur per `DELETE … AND option_value = <alt>` übernommen. `release` löscht nur `LIKE '<owner>|%'`. Danach werden `alloptions`/`notoptions` im Cache bereinigt. Altformate `<zeit>` und `<id>:<zeit>` werden noch verstanden.

### 5.4 Rate-Limits (Speicher `bp_mobile_rate_limit`, `Mobile/RateLimiter`)

| Zähler (Bucket) | Grenze | Fenster | Quelle |
|---|---|---|---|
| `login-ip\|<ip>` | 10 Fehlversuche | 15 min | `LoginGuard.php:85` |
| `login-email-ip\|<id>\|<ip>` | 5 | 15 min | `LoginGuard.php:86` |
| `login-email\|<id>` | 30 | 15 min | `LoginGuard.php:87` |
| `forgot-ip\|<client>` | 10 | 1 h | `LoginGuard.php:406` |
| `forgot-email-ip\|<email>\|<client>` | 3 | 1 h | `LoginGuard.php:369` |
| `forgot-email\|<email>` | 10 | 1 h | `LoginGuard.php:370` |
| `register-ip\|<client>` | 10 | 1 h | `Hardening.php:135` |
| `lbvc-redeem-ip\|<client>` | 30 (Filter) | 1 h | `Vouchers.php:77` |
| `redeem\|<userId>` | 10 | 1 h | `Vouchers.php:316` |
| `web-privacy\|<type>\|<userId>` | 5 | 1 Tag | `SelfService.php:93` |

Login-Zähler werden vorgemerkt (`take`) und bei Erfolg zurückgenommen (`release`). Bei einem bekannten Gerät gilt nur der Zähler Konto+IP (`LoginGuard::scope`). Ein Passwort-Reset setzt die Kontozähler zurück.

### 5.5 Fehlercodes und Ergebniswerte

| Bereich | Codes / Werte | Quelle |
|---|---|---|
| Login | `bp_login_failed`, `bp_login_locked`, `bp_reset_hint` (Meldung), `bp_email_change_password`, `bp_register_limit` | `LoginGuard.php:347`, `:352`; `Hardening.php:122`, `:137` |
| Gutschein | `bp_voucher_invalid`, `bp_voucher_no_shop`, `bp_voucher_product`, `bp_voucher_busy`, `bp_voucher_used`, `bp_voucher_order`; Web-Ergebnis `invalid`/`rate_limited`/`redeemed`/`already_redeemed` | `Vouchers.php:114`–`:206`, `:308` |
| Update-Prüfung | `bp_update_none`, `bp_update_http`, `bp_update_json`, `bp_update_pre`, `bp_update_tag` | `UpdateCheck.php:144`–`:158` |
| Wartung | Web `bp_maintenance` (503, `retry_after` 30); App `MAINTENANCE` (Problem-Response) | `Maintenance.php:77` |
| Fachliche Ausnahme | `exam_expired`, `exam_finished`, `exam_answer_locked`, `exam_not_startable`, `exam_start_busy`, `answer_rate_limited`, `exam_start_rate_limited`, `question_token_expired`; `AccessDenied` (403), `NotFound` (404) | `DomainException.php:16` |
| Datenschutz | `SelfService::request` → `created`/`resent`/`confirmed`/`protected`/`limited`/`error`; `processDeletion` → `completed`/`protected`/`failed`/`skipped`; Export-Fehlergrund `export_unavailable`, `exporter_invalid:<key>`, `exporter_pages:<key>`, `export_file`, `export_mail` | `SelfService.php:82`; `AccountDeletion.php:155`, `:376` |
| Restore/Monitor | `ok`/`warn`/`fail`; CLI-Exit 1 bei `fail`; Monitor 200 oder 503 | `BackupCheck.php:47`; `Monitor.php:95` |
| Migration | Admin-Hinweise `schema_newer`, `migration_failed`; Migrationsaktion `run`/`schedule`/`wait` | `Plugin.php:368`, `:553` |
| Audit-Aktionen im Bereich C | `import_uploaded`, `import_validated`, `import_validation_failed`, `import_approved`, `import_started`, `import_published`, `import_rolled_back`, `import_rollback_requested`, `import_discarded`, `import_retention`, `override_<feld>`, `personal_data_erased`, `account_deletion_skipped`, `account_deletion_failed`, `account_deleted`, `account_removed_from_site`, `privacy_export_failed`, `privacy_export_sent`, `entitlements_closed_account_deleted`, `voucher_redeemed`, `voucher_redeem_failed`, `voucher_redeem_skipped`, `privacy_orphan_site_tables_dropped` | jeweilige `AuditLog::record`-Aufrufe |

### 5.6 Recovery-Mechanismen (Überblick)

| Fehlerfall | Verhalten | Quelle |
|---|---|---|
| Migration scheitert | Option `migration_failed`, Backoff 15 min, Neuplanung, Admin-Hinweis mit CLI-Befehl, Monitor `migration=false`, Schreib-API 503 | `Plugin.php:493`; `Maintenance.php:59` |
| Migrationslauf stirbt (Timeout im ALTER) | `GET_LOCK` endet mit der Verbindung; der nächste Lauf übernimmt die verwaiste Options-Sperre | `Migrator.php:92`, `:386` |
| Fremdschlüssel nicht anlegbar | gemeldet, Version trotzdem gesetzt; Monitor `foreign_keys` (nur Hinweis) | `Migrator.php:134` |
| Downgrade ohne DB-Rücksicherung | keine Rückmigration, Hinweis, CLI-Fehler | `Plugin.php:365`; `Commands.php:630` |
| Teilaufgabe im Cron wirft | protokolliert (`bp_learning_cron_errors`), übrige Aufgaben laufen weiter; Monitor `maintenance` 2 h | `Cron.php:24`; `Monitor.php:102` |
| Cron-Option verloren | `ensureSchedules` auf jeder Anfrage | `Plugin.php:290` |
| Import abgebrochen oder hängend | `isAborted`; fortsetzen, verwerfen (Mischstand) oder `restoreActive`; Retention schützt das Rollback-Ziel | `ImportService.php:213`, `:357`, `:383`; `Retention.php:87` |
| Mediendatei halb überschrieben | Prüfsummenvergleich beim nächsten Import, atomares Ersetzen | `EntityImporter.php:201`; `MediaStorage.php:181` |
| Ordnerumzug scheitert | alter Ordner bleibt in Gebrauch, Fehler im Layout, neuer Versuch nach 1 h | `MediaStorage.php:396` |
| Restore aus einem Backup | `backup-check` (nur lesend) über Schema, Tabellen, Geheimnisse, Ordner, Paketkopie, Dateien, Medien, Zugänge, Bestellungen | `BackupCheck.php:32` |
| `GET_LOCK` nicht verfügbar | gilt als gehalten, Log; Aufrufer prüfen fachlich nach | `NamedLock.php:54` |

## 6. Entscheidungsbedarf (Auftraggeber)

| Nr. | Frage | Befund im Code | Empfehlung |
|---|---|---|---|
| E-C-01 | Soll Course Captain **Multisite** unterstützen? | Die Referenz unterstützt Aktivierung je Website (Netzwerkaktivierung blockiert), eigene Tabellen je Website, `wpmu_drop_tables`, verwaiste Tabellen, Kontolöschung je Website oder netzweit. Code und Tests existieren (INV-C-007/008/159/173/192). R0-25 sieht Multisite bisher als „Nichtumfang“. | In R1 **Single-Site offiziell unterstützen**. Den Multisite-Code (je Website, Netzwerk blockiert) trotzdem übernehmen, weil er wenig kostet und Datenlecks bei gelöschten Websites verhindert. Multisite als „funktioniert, nicht beworben“ dokumentieren, bis R1-30 bestanden ist. |
| E-C-02 | Gehört **website-weite Härtung** (Login-Limit, XML-RPC aus, Benutzer-Sitemap, Autorenarchive, Sicherheits-Header, Registrierungslimit, Gerätecookie) in ein LMS-Plugin? | Standardmäßig an (`Hardening::enabled`), abschaltbar per Filter. Bei bekannten Sicherheits-Plugins nimmt sich nur das Login-Limit zurück (INV-C-138…146). | **Nicht im Free-Kern aktiv.** Die LMS-eigenen Limits (Einlösung, Datenschutzanträge, Prüfungsstart) bleiben im Kern. Die Website-Härtung wird ein optionales Modul (R2), standardmäßig aus, mit Erkennung von Sicherheits-Plugins. Grund: Konflikte mit Sicherheits-Plugins und WordPress.org-Richtlinien gegen fachfremde Eingriffe. |
| E-C-03 | Wie erfahren Betreiber von **Updates**? | Opt-in-Abfrage der GitHub-Releases, weil die Referenz nicht über WordPress.org verteilt wird (INV-C-066/067). | **Free: nur WordPress.org-Updates**, keinen eigenen Update-Check mitliefern. **Pro: Updates über den Lizenzclient** (R0-39, R3-18). Das Muster der Referenz bleibt Vorbild: opt-in, keine Website-Daten, Sicherheitsupdates hervorgehoben. |
| E-C-04 | Gibt es eine **eigene App-/Mobile-API** mit Gerätesitzungen, Idempotenz, asynchronem Export und Web-Übergabe? | 4 Tabellen `mobile_*` plus Rate-Limit-Speicher (INV-C-046/047); die Logik liegt in Teil D. | Nicht in R1. Den **generischen Rate-Limit-Speicher** (`cc_rate_limit`) und das Idempotenz-Muster für die Web-REST-API in R1 übernehmen. Eine App-API erst nach eigener Produktentscheidung (R3/R4) einplanen. |
| E-C-05 | Sollen bestätigte Datenschutzanträge **automatisch das WordPress-Konto löschen**? | Default `privacy_requests_auto = true`: Nach der Bestätigung laufen alle Eraser, danach `wp_delete_user` (geschützte Rollen ausgenommen, INV-C-077/153/154). | Für ein universelles LMS **Default aus** (WordPress-Standard: Betreiber führt in den Werkzeugen aus), im Setup-Assistenten als Opt-in mit klarer Erklärung anbieten. Die Lerndaten-Löschung über den Eraser bleibt immer aktiv. |
| E-C-06 | Welche **Aufbewahrungsfristen** gelten als Default? | Fest bzw. per Filter: Audit 4 Jahre, Zugänge gelöschter Konten 10 Jahre (§ 147 AO), Import-Protokoll 14/180 Tage, 2 Paketkopien plus aktives Paket, verwaiste Uploads 24 h (INV-C-121/149). | Die Werte als **Settings mit genau diesen Defaults** übernehmen (DE/EU-tauglich), Filter beibehalten, im Datenschutz-Tab erklären. Hinweis: Das ist keine Rechtsberatung. Die Fristen sollte der Betreiber mit seiner Beratung bestätigen (R0-31). |
| E-C-07 | Sollen Rechte- und Provenienzstatus (`unknown` sperrt die Auslieferung) auch für **selbst erstellte** Inhalte gelten? | In der Referenz kommt jeder Inhalt per Import mit `rights_status`. `unknown`/`restricted` blockieren die Auslieferung, eine Sammelfreigabe ist nötig (INV-C-130/134/135). | Rechte und Provenienz **nur für importierte bzw. fremde Medien** verpflichtend. Eigene Inhalte bekommen beim Anlegen den Status „eigen/lizenziert“. Sonst wäre jeder neu erstellte Kurs zunächst unsichtbar. |
| E-C-08 | Welche **Mindestanforderungen an die Datenbank** gelten? | InnoDB ist Pflicht (`assertInnoDb`). Export und Löschung nutzen `JSON_EXTRACT`/`JSON_SET` (MySQL ≥ 5.7 bzw. MariaDB ≥ 10.2). PHP 8.1 und WP 7.0 sind Referenzwerte (INV-C-037/058). | **InnoDB und JSON-Funktionen als Pflicht** in R0-20 festschreiben (die Datenintegrität hängt an Transaktionen und Fremdschlüsseln). Die PHP- und WP-Mindestversion getrennt nach der Testmatrix entscheiden. |

**Für R0-04/R0-06 festgehalten (keine Entscheidung nötig, sondern Befund):** Die Deinstallation löscht `bp_entitlement_prune_last` und `_bp_catalog_fields` nicht (INV-C-026). Für Course Captain bedeutet das: ein Uninstall-Manifest je Modul plus ein Vollständigkeitstest gegen alle `update_option`- und `update_*_meta`-Aufrufe. Außerdem gilt: `Settings::sanitize()` setzt bei Teilformularen die nicht gesendeten Schlüssel zurück (INV-C-069), und `ContentRepository::examRule` hat den hartcodierten Schwellenwert 0.75 (INV-C-132, Kap. 26.4).
