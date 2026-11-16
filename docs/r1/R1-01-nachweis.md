# R1-01 · Plugin-Bootstrap und Lifecycle – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/blissful-curie-tuuf7q` · **Commit:** erfolgt durch den Koordinator
**DoD (TODO R1-01):** Plugin aktivierbar/deaktivierbar, Mindestversionen, Upgrade-/Uninstall-Policy, keine Suite-Pflicht.
**DoR (R0-24 §4.2/4.3):** `CourseCaptain\Kernel` (Plugin, Requirements, Activator), `uninstall.php`; Tests MS-001…MS-003, GM-158, KOMP-001, SA-001*; Kommando `composer test -- --group=R1-01`.
**Clean Room (E-01/E-327):** Kein Zugriff auf den Quelltext von `hastla007/learnboating`. Quellen waren ausschließlich `TODO-WETTBEWERBSANALYSE.md`, `konzept.md`, `docs/r0/*` und `docs/r0/ENTSCHEIDUNGEN.md`. LB-Pfadangaben in diesen Dokumenten wurden nur als Verhaltensbelege gelesen.

---

## 1. Dateien

### Plugin (wird ausgeliefert)

| Datei | Inhalt | Quelle |
|---|---|---|
| `course-captain.php` | Plugin-Kopf (Requires PHP 8.3, Requires at least 7.0, GPL-2.0-or-later, Text Domain `course-captain`, Domain Path; **ohne** `Update URI`, `Network`, `Requires Plugins`, `WC …`). Konstanten `COURSE_CAPTAIN_*` und `CourseCaptain\VERSION`. PHP-Prüfung in alter Syntax vor dem ersten Autoload: Admin-Hinweis statt Fatal, Aktivierung per `wp_die` abgebrochen | R0-35 §1.6/§3.2, R0-20 §1.2, RC-047 |
| `uninstall.php` | ruft `Uninstaller::run()` auf; tut auf zu altem PHP nichts | R0-16 §2.5 |
| `src/autoload.php` | eigener PSR-4-Autoloader `CourseCaptain\` → `src/` (keine Composer-Laufzeitabhängigkeit) | R0-18 T-6 |
| `src/Kernel/Plugin.php` | Boot auf `plugins_loaded` (Prio 20): Laufzeitprüfung PHP/WP, Filter für den Netzwerk-Aktionslink, Haltemodus, danach Textdomain, Rewrite-Flush einmalig, Trigger T-2/T-3, Cron-Handler, `course_captain_loaded_v1` mit `Contract\Api`. Statische Einstiege `activate()`/`deactivate()` | R0-19 §1.6, R0-37 §2.1 |
| `src/Kernel/Activator.php` | Reihenfolge: Netzwerkblockade → Systemprüfung (PHP, WP, DB) → (Fehlerseite schreibt nie) → Migration T-1 → Caps → Cron → Rewrite-Flush anfordern. Jeder Abbruch per `wp_die` vor dem ersten Schreibzugriff | MS-R-01, R0-20 §1.2, R0-37 T-1 |
| `src/Kernel/Requirements.php`, `RequirementFailure.php`, `EnvironmentSnapshot.php` | reine Bewertung der Plattformmatrix: PHP ≥ 8.3, WP ≥ 7.0, MariaDB ≥ 10.6 / MySQL ≥ 8.0 (hart), Stufe „best effort“ unter MariaDB 10.11 / MySQL 8.4, InnoDB, `JSON_VALID`, utf8mb4. Fehlercodes `ccap_requirement_*`; Meldungen werden erst bei Ausgabe übersetzt | E-08, E-58, E-195, E-285 |
| `src/Kernel/NetworkGuard.php` | Blockade der Netzwerkaktivierung (UI, WP-CLI `--network`, Fehlerseite im Netzwerk-Admin), Erkennung erzwungener Netzwerkaktivierung, Entfernen von „Netzwerkweit aktivieren“ | E-49, MS-R-01/02 |
| `src/Kernel/HoldMode.php` | Haltemodus-Gerüst; Grund `network_forced` erkannt, Grund `site_identity` (E-85) reserviert | MS-R-02, D-25-08 |
| `src/Kernel/Capabilities.php` | 24 primitive `ccap_*`-Caps als Konstante, Aktivierungsrolle nur `administrator`, Protokoll in `course_captain_role_grants` | R0-07 §3.1, E-83, R0-27 §2.1 |
| `src/Kernel/CronHooks.php` | Liste der 5 R1-Cron-Hooks; Deaktivierung/Uninstall tragen alle Ereignisse aus (auch einmalige, auch Action Scheduler) | R0-19 §4.8, GM-158 |
| `src/Kernel/UninstallManifest.php`, `Uninstaller.php` | exaktes Manifest (Optionen aus R0-27 §2.1/§2.7 und R0-37 §4.3; dynamische Familien als exakte Namen ausgeschrieben), kein `LIKE`/Wildcard; Standard: Daten bleiben; Löschung nur bei `course_captain_settings_general.values.delete_data_on_uninstall === true`; Multisite je Site mit `switch_to_blog`/`finally`; Action `course_captain_uninstall` | R0-16 §2.5, R0-39 FG-10, MS-R-07/13, R0-19 |
| `src/Infrastructure/Migration/MigrationRunner.php`, `NullMigrationRunner.php` | leerer Erweiterungspunkt für T-1/T-2/T-3 (Schema folgt in R1-02, Lock/Schritte in R1-40) | R0-37 §2.1 |
| `src/Contract/Api.php`, `src/functions.php` | Versionshandshake `contract_version()`, `supports()` (unbekannt → `false`), Funktionen `course_captain_contract_version()`/`course_captain_supports()` | R0-19 §1.1/§1.6 |
| `readme.txt`, `LICENSE` (GPLv2), `CREDITS.md`, `CHANGELOG.md`, `languages/course-captain.pot` | Distribution, englische Quelle | R0-35 §1.4–§1.6, E-121 |

### Repo-Gerüst und Prüfwerkzeuge (nicht im ZIP)

| Datei | Inhalt |
|---|---|
| `composer.json`, `composer.lock` | dev: PHPUnit 9.6, Brain Monkey, Yoast Polyfills, WPCS 3.4, PHPCompatibilityWP, PHPStan 2 + phpstan-wordpress + wordpress-stubs 7.1, WordPress-Kern 7.1.4 (`roots/wordpress-no-content`), `wp-phpunit` 7.1.3, WP-CLI (i18n, extension, core, config, entity). Skripte `lint`, `analyse`, `test`, `test:integration`, `test:integration-ms`, `test:cli-ms002`, `guard:*` |
| `phpcs.xml.dist` | volles `WordPress`-Regelwerk, einzige Ausnahme `WordPress.Files.FileName`; PHPCompatibilityWP `8.3-`; Präfixe `course_captain`, `ccap`, `CourseCaptain`; Textdomain; Test-Ausnahmen nur für PHPUnit-Pflichtnamen und Testkonfiguration |
| `tools/qa/phpcs-bootstrap-compat.xml` | Hauptdatei muss ab PHP 7.0 parsebar sein (RC-047) |
| `phpstan.neon.dist`, `tools/qa/phpstan-bootstrap.php` | Stufe 8, keine Baseline, `phpVersion` 80300–80599, `reportUnmatchedIgnoredErrors` |
| `phpunit.xml.dist`, `phpunit-integration.xml.dist`, `phpunit-integration-multisite.xml.dist` | Zufallsreihenfolge (Seed im Log), Notices/Warnings/Deprecations als Fehler (G-08), Gruppen `ms-required`/`ms-excluded` |
| `tests/unit/*` | Unit-Suite ohne WordPress (Brain Monkey) |
| `tests/integration/*` | WP-Testsuite; `wp-tests-config.php` liest `WP_TESTS_DB_*`, bricht ab, wenn der DB-Name kein `test` enthält (G-09); jeder ausgehende HTTP-Request wird blockiert und lässt den Test fehlschlagen (LG-1/G-07) |
| `tests/integration/cli/ms-002-network-activate.sh` | MS-002 über WP-CLI gegen die Multisite-Testinstallation |
| `tests/guards/free-guard.php` (+ `fixtures/free-guard/`), `tools/qa/free-guard-allow.txt` | Free-Guard G-1…G-14 (R0-39 §1.2) mit Allowlist (Pfad, ID, Grund, Ablauf; G-7 nie zulässig) und Selbsttest |
| `tests/guards/textdomain-lint.php` | J-03: Domain als Literal `course-captain`, englische Quellstrings, `.pot` vorhanden |
| `tests/support/StorageKeyScanner.php` | statischer Scan der Options-/Transient-/Meta-/Cron-Schlüssel (REC-020, KOMP-130) |
| `.github/workflows/ci.yml`, `.github/dependabot.yml` | Jobs J-01, J-03, J-05, J-07, J-10, J-11 (PHP 8.3/8.5), J-12 (M-Z, M-U, M-M je Lauf; M-MS nightly inkl. MS-002); Actions per Commit-SHA gepinnt, `permissions: contents: read`, Concurrency, Timeouts |

---

## 2. Kommandos und Ergebnisse (lokal, 16.11.2026)

Umgebung: PHP 8.3.6, MariaDB 10.11.14 (per `apt-get install mariadb-server`, DB `ccap_test`), WordPress 7.1.4, WP-Testsuite 7.1.3, PHPUnit 9.6.39.

| Kommando | Ergebnis |
|---|---|
| `composer install` | ok (lokal mit `--prefer-source`, siehe §5 Blocker B-1) |
| `composer validate --strict` | valid |
| `composer audit --locked` | keine Advisories |
| `vendor/bin/phpcs` | 0 Fehler, 0 Warnungen |
| `vendor/bin/phpcs --standard=tools/qa/phpcs-bootstrap-compat.xml` | 0 Fehler |
| `php -l` (alle ausgelieferten PHP-Dateien) | 0 Fehler |
| `vendor/bin/phpstan analyse` | Stufe 8: **No errors** |
| `composer test` bzw. `composer test -- --group=R1-01` | **OK (49 Tests, 355 Assertions)** |
| `composer test:integration` (Single-Site) | **OK (14 Tests, 93 Assertions)**, mehrfach mit wechselndem Seed |
| `composer test:integration-ms` (Multisite) | **OK (22 Tests, 139 Assertions)** = 8 Multisite-Tests + 14 Single-Site-Tests im Multisite-Modus |
| `composer test:cli-ms002` | **MS-002: OK** (Exit 1 der Netzwerkaktivierung, keine Spuren auf 2 Sites); Gegenprobe ohne `--network` meldet die Spuren korrekt |
| `php tests/guards/free-guard.php --self-test` | OK (14 Guard-IDs je 1 Treffer, sauberes Fixture 0) |
| `php tests/guards/free-guard.php` | 20 Dateien, 0 Treffer, 0 Allowlist-Fehler |
| `php tests/guards/textdomain-lint.php` | 18 Dateien, 0 Probleme |
| `wp i18n make-pot` gegen eingecheckte `.pot` | kein Diff (außer `POT-Creation-Date`) |

---

## 3. Test-IDs

Alle Testklassen tragen `@group R1-01`, jeder Test mit Katalog-ID zusätzlich `@group <ID>` und die ID im Methodennamen.

| ID | Quelle | Test(s) | Umfang in R1-01 |
|---|---|---|---|
| MS-001 | R0-25 §4.2 | `MultisiteTest::test_MS_001_network_activation_is_blocked_before_any_write`, `…_error_scrape_in_network_admin_is_blocked`; Unit `NetworkGuardTest::test_MS_001_*` | voll |
| MS-002 | R0-25 §4.2 | `tests/integration/cli/ms-002-network-activate.sh` | voll |
| MS-003 | R0-25 §4.2 | `MultisiteTest::test_MS_003_*`, `NetworkGuardTest::test_MS_003_*` | Filter (Grenze siehe §4 A-8) |
| MS-004 | R0-25 §4.2 | `MultisiteTest::test_MS_004_forced_network_activation_hold_mode`, `NetworkGuardTest::test_MS_004_*` | voll |
| MS-005 | R0-25 §4.2 | `MultisiteTest::test_MS_005_*` | Caps, Cron, Optionen; Tabellen folgen mit R1-02 |
| MS-012 | R0-25 §4.2 | `MultisiteTest::test_MS_012_*` | voll |
| MS-014 | R0-25 §4.2 | `MultisiteTest::test_MS_014_*` | voll (ohne Migration, die es noch nicht gibt) |
| MS-015 | R0-25 §4.2 (Ziel R1-23) | `MultisiteTest::test_MS_015_*` | Entscheidungsteil vorgezogen |
| KOMP-001 | R0-20 §2.1 | = MS-001…004 | voll |
| GM-158 | R0-05 | `LifecycleTest::test_GM_158_*`, `KernelContractTest::test_GM_158_*` | Cron-Lebenszyklus; „Endpunkte sofort erreichbar“ folgt mit den ersten Routen |
| RC-046 / RC-011 / LIC-003 | R0-35 §5, R0-39 | `PluginHeaderTest::test_RC_046_plugin_header`, `RequirementsTest::test_RC_046_*` | Kopf, Lizenz, kein `Update URI`/`Requires Plugins` |
| RC-047 | R0-35 §5.6 | `RequirementsTest::test_RC_047_*`, `LifecycleTest::test_RC_047_*`, Bootstrap-Compat-PHPCS | Logik + Abbruch ohne Schreibzugriff; echter Lauf auf PHP 8.2 offen (§5) |
| RC-041 | R0-35 §5.6 | `PluginHeaderTest::test_RC_041_*` | Kopf = Konstante = Stable tag = CHANGELOG; Tag-Vergleich folgt mit `release.yml` |
| RC-014 / RC-020 | R0-35 §5.2/5.3 | `FreeGuardTest::test_RC_014_*`, `test_LIC_001_*` | Quelltext + Negativproben; ZIP-Prüfung folgt mit J-17 |
| RC-032 / KOMP-130 | R0-35, R0-20 §3.2 | `KernelContractTest::test_KOMP_130_prefix_scan` | statischer Teil; Laufzeit-Inventar (`wp eval`) mit R1-28 |
| LIC-001, LIC-010, LIC-011 | R0-39 §5 | `FreeGuardTest` | voll |
| LIC-007, LIC-008 | R0-39 §5 | `LifecycleTest::test_LIC_007_*`, `test_LIC_008_*` | voll für den R1-01-Umfang |
| LIC-063 | R0-39 §5 (Ziel R3-18) | `UninstallManifestTest::test_LIC_063_*`, `UninstallTest::test_REC_019_confirmed_deletion_*` | Free-Seite (Pro-Optionen bleiben) |
| REC-018, REC-019 | R0-16 §4 (Ziel R1-23) | `UninstallTest`, `UninstallManifestTest::test_REC_019_*` | Entscheidungs- und Optionsteil vorgezogen; Tabellen/Dateien/Neuinstallation in R1-23 |
| REC-020 | R0-16 §4 (Ziel R1-23) | `UninstallManifestTest::test_REC_020_*` (inkl. Negativtest) | voll für Optionen/Cron |
| SA-001* | R0-12 §1.3 | `LifecycleTest::test_LIC_008_*` + LG-1 in jedem Integrationstest | Teil: keine ausgehenden Requests bei Aktivierung/Deaktivierung/Uninstall; E2E-Flows mit R1-18 |

Weitere Tests ohne Katalog-ID: Idempotenz der Aktivierung, Abbruch bei fehlschlagender Migration vor Caps/Cron (R0-37 T-1), Fehlerseite schreibt nichts, Rewrite-Flush, Trigger T-2/T-3, Datenbankmatrix (11 Fälle), Handshake, `ABSPATH`-Schutz jeder `src`-Datei, Allowlist-Ablauf, Textdomain-Lint.

---

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung | Begründung |
|---|---|---|---|
| A-1 | PHPUnit-Version | **PHPUnit 9.6** für Unit und Integration statt 11 (R0-18 J-11) | Die WP-Testsuite 7.1 ruft `PHPUnit\Util\Test::parseTestMethodAnnotations()` auf, das es ab PHPUnit 10 nicht mehr gibt (in diesem Lauf mit 11.5 reproduziert). Eine Version für beide Suiten; Gruppen als Docblock-`@group`. Wechsel, sobald die WP-Testsuite PHPUnit ≥ 10 trägt |
| A-2 | Autoload | eigener PSR-4-Autoloader im Plugin, Composer-PSR-4 mit gleicher Abbildung nur für Entwicklung/Tests | R0-18 T-6 (keine Composer-Laufzeitabhängigkeiten, kein `vendor/` im ZIP) präzisiert den Auftrag „PSR-4 via Composer“ |
| A-3 | Versionen | Plugin `1.0.0-dev` (Startversion 1.0.0 nach E-209 erst beim Release), `COURSE_CAPTAIN_CONTRACT_VERSION` `0.1.0` (1.0.0 mit dem R1-Release, R0-19 V-01) | Vorabstand; `Stable tag` gleich Kopf, damit die Gleichheitsprüfung greift |
| A-4 | Netzwerk-Fehlerseite | Erkennung über `action=error_scrape`, nicht über `WP_SANDBOX_SCRAPING` | WordPress 7.1 definiert `WP_SANDBOX_SCRAPING` bei **jeder** Aktivierung (`activate_plugin()` → `plugin_sandbox_scrape()`); ein Test hat gezeigt, dass die Konstante als Signal jede spätere Aktivierung im Prozess leer laufen ließe |
| A-5 | Datenlöschung | Löschung nur bei striktem `true` in `course_captain_settings_general.values.delete_data_on_uninstall`; `"1"`, `1` usw. löschen nicht | Bestätigungsdialog (Site-Name, Backup-Hinweis, Audit, E-89) gehört zu R1-31/R1-36; Uninstall vertraut nur dem dort gespeicherten Wert (fail closed) |
| A-6 | Manifest | enthält bereits alle in R0-27 §2.1/§2.7, R0-37 §4.3 und R0-39 FG-11 benannten Optionen; Familien `…_settings_undo_<tab>` (11 Tabs) und `…_mail_template_<typ>` (16 Mailtypen R0-27 §3.2) als exakte Namen | FG-10 verlangt exakte Namen; kein `LIKE` im Uninstaller |
| A-7 | Multisite-Uninstall | Schleife über alle Sites (es gibt noch keine `ccap_`-Tabellen als Kriterium, MS-R-13) | Einschränkung auf Sites mit Tabellen kommt mit R1-02/R1-23 |
| A-8 | Link „Netzwerkweit aktivieren“ | Filter `network_admin_plugin_action_links_<basename>` wird registriert; er wirkt nur, wenn der Plugin-Code im Netzwerk-Admin geladen ist (z. B. auf der Hauptsite aktiv) | Ein nicht geladenes Plugin kann die Liste nicht beeinflussen; die Blockade per `wp_die` greift in jedem Fall |
| A-9 | Haltemodus | kein `course_captain_loaded_v1`, keine Cron-Handler, kein Migrations-Trigger, keine Rewrite-Regeln; nur Hinweise für Netzwerk-Admin (`manage_network_plugins`) und Site-Admins (`activate_plugins`) | MS-R-02; Erweiterungen sollen ohne Registry/Migration nicht starten |
| A-10 | DB-Prüfung | volle DB-Prüfung nur bei Aktivierung; je Request nur PHP/WP (billig, ohne Übersetzung vor `init`) | R0-20 §1.2 (harte Untergrenze bei Aktivierung); Systemstatus-Stufe über `Requirements::database_support_level()` für R1-38 |
| A-11 | Fehlercodes | neu: `ccap_requirement_php|wordpress|database|innodb|json|utf8mb4` | stehen nicht im Katalog R0-19 §3 → Ergänzung in R1-28 |
| A-12 | `ccap_hourly` | wird bei Aktivierung eingeplant, Handler bis zum Task-Runner leer | macht GM-158 (Austragen/Neuplanen) prüfbar |
| A-13 | Rewrite | Aktivierung löscht `course_captain_rewrite_version` → einmaliger `flush_rewrite_rules(false)` beim nächsten `init`; Deaktivierung löscht `rewrite_rules` | R0-20 §3.1 „Flush nur nach Aktivierung/Speichern“, MS-R-10 |
| A-14 | Rollen | die vier `ccap_`-Rollen werden **nicht** angelegt | Auftrag: Mapping/Rollen in R1-03 (R0-07 P-10 nennt sie für die Aktivierung) |
| A-15 | Kopf-/Readme-Angaben | `Author`, `Author URI`, `Contributors` sind Platzhalter | Angaben des Auftraggebers (E-206) fehlen |
| A-16 | CI-Zeile M-Z | `mariadb:12.3` | E-58; LTS-Status nach R0-20 §1.3 noch zu verifizieren (sonst 11.8) |

---

## 5. Blocker, offene Punkte

| Nr. | Punkt | Wirkung / nächster Schritt |
|---|---|---|
| B-1 | Lokale Umgebung: Die GitHub-API (`api.github.com/repos/<fremdes Repo>/zipball`) ist für diese Session gesperrt. `composer install` lief deshalb mit `--prefer-source` und `use-github-api=false` (eigenes `COMPOSER_HOME` im Scratchpad); `phpstan/phpstan` hat keine Source-Angabe und wurde aus dem Git-Tag 2.3.6 (Commit = Lock-Referenz `00cedeb…`) in den lokalen Composer-Cache gelegt | betrifft nur diese Session; CI nutzt normale Dists. Kein Einfluss auf `composer.lock` |
| B-2 | Download des WP-CLI-Phar wurde abgelehnt | WP-CLI als Composer-Dev-Abhängigkeit (`vendor/bin/wp`) |
| O-1 | Lokal nur PHP 8.3.6, MariaDB 10.11, WP 7.1.4 geprüft | PHP 8.4/8.5, MySQL 8.4, MariaDB 12.3, WP 7.0.8 laufen erst in der CI (M-Z, M-M, M-U); erster CI-Lauf steht aus |
| O-2 | RC-047 auf echtem PHP 8.2 / WP 6.9 | hier nur über Logik-Tests und PHPCompatibility (Hauptdatei ab 7.0 parsebar); CI-Sonderzeile mit PHP 8.2 ergänzen |
| O-3 | E-207 „vor R1-01 gegen aktuelle Richtlinie prüfen“ | offline nicht prüfbar (developer.wordpress.org über den Proxy gesperrt); Präfix `ccap_` unverändert umgesetzt → Auftraggeber/Koordinator |
| O-4 | Docker-Images der CI sind per Tag, nicht per Digest gepinnt (G-02) | Digests beim ersten CI-Lauf eintragen; Dependabot `docker` ergänzen |
| O-5 | Noch nicht angelegt (R0-18 §6, R1-01-Übergabe): `release.yml`, `perf-release.yml`, `CODEOWNERS` (Benutzername des Auftraggebers fehlt, E-204), `psalm.xml`, `playwright.config.ts`, `tools/{release,i18n,semgrep}/`, Jobs J-02, J-04, J-06, J-08, J-09, J-13…J-23, Branch-Schutz | gehören zu den Tasks mit den jeweiligen Inhalten (u. a. R1-18, R1-39); Branch-Schutz ist eine Repo-Einstellung |
| O-6 | M-MS-Matrixzeile startet ihren DB-Service auch bei PRs, die Schritte werden übersprungen | Matrixzeilen lassen sich nicht per Job-`if` filtern; bei Bedarf eigener Nightly-Job |
| O-7 | Tabellen-Eigentumsprüfung KOMP-131 und Kollisionsprüfungen bei Aktivierung | mit den ersten Tabellen/CPTs (R1-02 ff.) im Activator vor der Migration ergänzen |
| O-8 | `.pot` enthält Zeilenreferenzen | nach Codeänderungen neu erzeugen; CI vergleicht ohne `POT-Creation-Date` |
