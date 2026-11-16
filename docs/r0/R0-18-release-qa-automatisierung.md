# R0-18 · Release- und Qualitätsautomatisierung (Entwurf)

**Status:** **Entwurf – Freigabe Architektur/Auftraggeber offen.** Task R0-18 ist **nicht** abgehakt. Es gibt keine Workflow-Datei, kein Skript, keinen CI-Lauf und keinen Commit. Alle Laufzeiten sind Schätzwerte, keine Messwerte.
**Stand:** 16.11.2026
**Bezug:** TODO R0-18 (Voraussetzung R0-05, R0-12; DoD: CI-Matrix, PHP-/JS-Checks, Unit/Integration/E2E, SAST, Dependency-Scan, Paket-Signierung und Releaseartefakte definiert), Folgetasks **R1-18** (GM- und E2E-Abnahme, Gates B/C), **R1-39** (Distribution und Erstinstallation), R1-22, R1-40, R1-42, R0-20, R0-35, R0-39, R3-18; Konzept Kap. 23.1 (Zeile „Release“), **23.2** (Release-Gates); R0-02/E §2.6, §4 (INV-E-043/044/087…097/112/113/116); R0-05 §3 (D-01…D-14); R0-12 §3 (Gates A–D); R0-14 §2, §3, §5 (R-CI, R-M, L1…L5, NFR-001…018); R0-15 §2.4, §3.1, §3.8 (A11Y-100, D-15-05); R0-16 §3.3/§3.4; R0-17 (T-05, SEC-045); R0-36 A8/L1/L12; R0-37 §7 (MIG-001…026).
**Entscheidungen als Annahme (nicht beschlossen):** **E-11** (PHPUnit mit WP-Testsuite, `@playwright/test`), **E-13** (WordPress.org-SVN-Deploy nur bei grüner CI, Tag auf `main`, manueller Freigabe), **E-57** (PHP-Untergrenze 8.2), **E-58** (MariaDB 10.11 und MySQL 8.4 in der CI, MySQL 8.0/MariaDB 10.6 best effort), **E-60** (Zeitbudgets Gate für R1-GATE, Query-/Scan-Gates in jeder CI-Runde), **E-61** (Browser: zwei letzte Hauptversionen Chrome/Edge/Firefox/Safari, Firefox ESR, iOS Safari, Android Chrome), **E-184** (100 % der R1-Pflichttests grün). Außerdem genutzt: E-01 (Codefreigabe LB), E-02, E-09, E-42, E-49, E-55, E-64 (betrifft **Content**-Pakete, nicht das Plugin-ZIP), E-90, E-121.
**Quellen (nur gelesen):** LearnBoating `hastla007/learnboating` @ `9f37c77`: `.github/workflows/ci.yml` (660 Z.), `.github/workflows/release.yml` (86 Z.), `tools/release/build-zips.sh`, `tools/release/upgrade-check.php`, `tools/qa/check-abspath.php`, `tools/i18n/check-domains.php`, `tools/i18n/make-pot.sh`, `bootspruefung-learning/{phpstan.neon.dist, phpcs.xml.dist}`, `bootspruefung-learning/tests/{integration,e2e,phpstan}`, `docs/release-regel.md`. Suite: `wpaffiliatemaster/tests/run.php:332-363` (Guard A2). Code aus LB wird erst nach E-01 übernommen; bis dahin gilt „Verhalten übernehmen, neu schreiben“.

---

## 0. Kurzfassung

- **Zwei Workflows plus zwei Nebenläufe:** `ci.yml` (PR, `main`, nightly, wöchentlich, von `release.yml` aufgerufen), `release.yml` (Tag `v*`), `perf-release.yml` (manuell je Minor auf R-M, E-60) und `pro-release.yml` im Pro-Repository (R3-18).
- **Drei Spuren:** *PR-Spur* (schnell, blockierend, ≤ 20 min Wanduhr), *Nightly-Spur* (volle Matrix, Race/MIG-Mehrprozess, Firefox, nginx, Redis, Multisite, Koexistenz, Voll-Scans), *Release-Spur* (Nightly-Umfang auf dem Tag-Commit, Reproduzierbarkeit, SBOM, Signatur, Staging, manuelle Freigabe, SVN).
- **Neu gegenüber LB** (INV-E-097): SAST (Semgrep + Psalm-Taint), Dependency-Scan (composer/npm audit, Dependabot, OSV), Secret-Scan, Lizenz-Allowlist, Free-Guard „kein Lizenzclient/Updater“, SBOM, Sigstore-Signatur mit Build-Provenienz, Reproduzierbarkeitsprobe, WordPress.org-Plugin-Check, JS/CSS-Lint, axe-Gate, Workflow-Lint, gepinnte Actions.
- **Aus LB übernommen** (nach E-01): ZIP aus Commit (`git archive`), Dateizeit = Commit-Zeit, `zip -X -D`, Versionsgleichheit, „geändert, aber Version gleich“, `check_zip`, `SHA256SUMS`, Artefakttest (ZIP installieren statt Repo-Ordner), Upgrade-Probe von der Vorversion, Notice-Gate, Release nur bei grüner CI und Tag auf `main`.

---

## 1. CI-Pipeline (GitHub Actions)

### 1.1 Grundregeln für alle Workflows

| Nr. | Regel | Herkunft |
|---|---|---|
| G-01 | `permissions: contents: read` als Standard; mehr Rechte nur im einzelnen Job (`id-token: write` nur für Signatur/Attestierung, `contents: write` nur für das Release) | LB ci.yml:7-8, release.yml:12-13 |
| G-02 | Alle Actions per **Commit-SHA** gepinnt (Kommentar mit Version), Docker-Images per Digest, Werkzeuge über `composer.lock`/`package-lock.json`, Playwright- und axe-Version fest. Dependabot aktualisiert die Pins (§2.2) | neu (LB nutzt `@v4`-Tags) |
| G-03 | Nur `pull_request`, **nie** `pull_request_target`; PR-Jobs sehen keine Secrets. Secrets (SVN, License Captain) liegen ausschließlich in geschützten Environments (§3.6) | neu |
| G-04 | `defaults.run.shell: bash` (pipefail) wie LB; kein `grep -q` hinter Pipes | LB ci.yml:10-13, build-zips.sh:26 |
| G-05 | `concurrency: ci-${{ github.ref }}`, `cancel-in-progress: true` für PR; für `main`, Tags und Release `false` | LB release.yml:19-21 |
| G-06 | Jeder Job hat `timeout-minutes` (Budget §4.3 + 50 %) | neu |
| G-07 | **Keine Außenwirkung in Tests** (R0-05 D-09): `pre_http_request` blockt jeden nicht gestubbten Host und lässt den Test fehlschlagen; Mail-Sink; E2E werten Fremdhost-Anfragen, `pageerror` und `requestfailed` als Fehler (FE-07). WordPress-Core, Plugins für Tests und Browser kommen aus Cache bzw. gepinnten Quellen | R0-05 D-09, R0-14 FE-07 |
| G-08 | **Notice-Gate** (R0-05 D-12): PHPUnit mit `failOnWarning`, `failOnNotice`, `failOnDeprecation`, `failOnRisky`; zusätzlich Log-Grep `PHP (Warning\|Deprecated\|Notice\|Fatal)` nach Skript- und E2E-Läufen. Fängt auch `_load_textdomain_just_in_time` (Übersetzung vor `init`, R0-15 §3.1) | LB INV-E-088 |
| G-09 | **DB-Schutz** (R0-05 D-01): Test-Bootstrap bricht ab, wenn `DB_NAME` nicht `test` enthält; je Testfall Transaktion mit Rollback, Szenariotests mit frischer DB | LB GD:10-13 |
| G-10 | Determinismus (R0-05 D-04…D-06): injizierte `Clock` und `Randomizer`, Seed wird im Log ausgegeben; feste Schlüssel `fx-<GM>-<n>` | R0-05 §3 |
| G-11 | Testberichte als JUnit-XML-Artefakt (14 Tage); **keine** personenbezogenen Daten in Artefakten (nur Fixture `cc-fixture-neutral-v1`, E-02) | E-02 |

### 1.2 Trigger

| Trigger | Workflow / Variante | Umfang |
|---|---|---|
| `pull_request` (alle Zweige) | `ci.yml`, `lane=pr` | Jobs mit Spalte „PR“ in §1.4; Matrix-Pflichtzeilen M-Z, M-U, M-M |
| `push` auf `main` | `ci.yml`, `lane=main` | PR-Umfang + Reproduzierbarkeitsprobe, SBOM, Firefox-E2E |
| `schedule` nightly (03:17 UTC, Vorschlag) | `ci.yml`, `lane=nightly` auf `main` | volle Matrix inkl. informativer Zeilen, L5 und MIG-Mehrprozess auf beiden DBs, k6-Smoke, Multisite, Staging, Koexistenz, Voll-Scans (Secrets über die ganze Historie, Dependency-Advisories auch ohne Codeänderung, CodeQL falls verfügbar D18-1) |
| `schedule` wöchentlich (So 04:41 UTC) | `ci.yml`, `lane=weekly` | axe in Firefox/WebKit (A11Y-100), Browser-Vorversionen (D18-4), Lizenz-Vollbericht, Mutation/Coverage-Bericht (nur Information) |
| `push` Tag `v*` | `release.yml` → ruft `ci.yml` mit `lane=release` per `workflow_call` auf | Nightly-Umfang auf genau diesem Commit, danach Release-Kette §3 |
| `workflow_dispatch` | `ci.yml` (Lane wählbar), `perf-release.yml` | Wiederholung, Lastmessung je Minor auf R-M (E-60) |

Änderungen nur unter `docs/**` lösen in PRs nur J-03 (Wortliste), J-04 und den Doku-Lint aus (`paths-ignore` für die übrigen Jobs).

### 1.3 Matrix PHP × WordPress × Datenbank (aus E-57, E-58, R0-14 §3; endgültig in R0-20)

| Zeile | Stufe | PHP | WordPress | DB | Webserver / Cache | Läuft | Inhalt |
|---|---|---|---|---|---|---|---|
| **M-Z** | Ziel | 8.5 | 7.1.x (aktuell) | MariaDB 12.3 | Apache 2.4, Cache aus | PR | Unit, Integration, GM, E2E (Chromium + WebKit, Desktop + Mobil-Viewport), axe, Upgrade, Artefakttest |
| **M-U** | Untergrenze | **8.2** (E-57) | 7.0.0 | **MariaDB 10.11** (E-58) | Built-in-Server, Cache aus | PR | Unit, Integration, GM, Upgrade (ohne E2E) |
| **M-M** | Pflichtachse DB | 8.3 | 7.1.x | **MySQL 8.4** (E-58) | Built-in-Server | PR | Integration, GM, CON-Tests; nightly zusätzlich L5 und MIG-Mehrprozess |
| M-ZF | Ziel | 8.5 | 7.1.x | MariaDB 12.3 | Apache | main, nightly | E2E **Firefox** (R0-14: nightly; Pflicht für B-4 vor dem Gate) |
| M-N | Ziel | 8.5 | 7.1.x | MariaDB 12.3 | **nginx + PHP-FPM**, ohne `.htaccess` | nightly | Integration-Teilmenge, E2E Chromium, NFR-017-HTTP-Probe |
| M-R | Ziel | 8.5 | 7.1.x | MariaDB 12.3 | Apache, **Redis-Drop-in an** | nightly | Integration, CON-Tests, Cache-Flush-Probe (NFR-008-Funktionsteil) |
| M-MS | Ziel | 8.5 | 7.1.x | MariaDB 12.3 | `WP_TESTS_MULTISITE=1` | nightly | MS-001…023, MIG-020, Netzwerkaktivierung blockiert (E-49) |
| M-I1 | informativ | 8.4 | nächste WP-Beta/RC | MariaDB 12.3 | – | nightly, `continue-on-error` | Integration |
| M-I2 | informativ | 8.2 | 7.1.x | MySQL 8.0 / MariaDB 10.6 (best effort, E-58) | – | nightly, `continue-on-error` | Integration |
| M-I3 | informativ | nächste PHP-Entwicklungsversion | 7.1.x | MariaDB 12.3 | – | nightly, `continue-on-error` | Unit, Integration |

Regeln: Pflichtzeilen dürfen nie `continue-on-error` haben. Eine informative Zeile, die vier Nächte in Folge rot ist, erzeugt automatisch ein Issue (Label `compat`). Woo-Achse erst ab R3 (R0-14 §3, D-E-03). `Requires PHP`/`Requires at least` im Plugin-Kopf und `readme.txt` müssen der Untergrenze M-U entsprechen (Prüfung in J-17).

**Browser (E-61) für E2E:** Playwright-Projekte `chromium` (deckt Chrome/Edge-Engine), `msedge` (Stable-Kanal, nightly), `firefox`, `webkit` (Näherung für Safari), Mobil: `webkit` 390×844, `chromium` Android-Emulation 360×800, Reflow 320×640. Grenzen: Playwright liefert je Version **eine** Browser-Engine; echte Safari-, iOS-Safari- und Firefox-ESR-Builds sowie „Vorversion N-1“ deckt es nicht ab → D18-4 und manueller Geräte-Lauf vor dem Release (R0-14 §3, R1-18).

### 1.4 Jobs

Spalten: **PR** = blockiert Merge (Required Check); **N** = nightly; **R** = Release-Lauf. ● = läuft und blockiert, ○ = läuft, informativ, – = läuft nicht.

| Job | Inhalt | Werkzeug (Version gepinnt) | PR | N | R |
|---|---|---|---|---|---|
| **J-01 lint-php** | PHPCS mit **WPCS 3** (`WordPress-Extra` + `WordPress.Security` + `WordPress.DB.PreparedSQL*`, alle **blockierend**, ohne Baseline); Ausnahme nur `WordPress.Files.FileName` für PSR-4-Klassen; `PHPCompatibilityWP` mit `testVersion 8.2-`; `php -l` unter 8.2 und 8.5; `check-abspath` (jede `src`-Datei endet bei Direktaufruf, LB T125); Verbotsmuster `eval`, `create_function`, `extract`, `unserialize` ohne `allowed_classes` | phpcs, WPCS, PHPCompatibilityWP, LB `tools/qa/check-abspath.php` (angepasst) | ● | ● | ● |
| **J-02 lint-js-css** | ESLint (`@wordpress/eslint-plugin` + `eslint-plugin-no-unsanitized`; Regel „kein `innerHTML`/`insertAdjacentHTML` außer in `SafeRichText`“, R0-17 T-05/AC-11); Stylelint (`@wordpress/stylelint-config` + Regel „logische Eigenschaften“, I18N-R-12); Typprüfung, falls TypeScript genutzt wird | eslint, stylelint | ● | ● | ● |
| **J-03 lint-i18n** | Textdomain-Lint (nur `course-captain` in Free, `course-captain-pro` in Pro; Domain als Literal; `.pot` vorhanden); `.pot` aktuell (`wp i18n make-pot` erzeugt keinen Diff, ohne `--skip-audit`); Regex-Lint feste Datumsformate (I18N-005) und Verkettung übersetzter Texte (I18N-R-03); Quellsprache Englisch (E-121, Heuristik: keine Umlaute in Quellstrings); **Wortliste SEC-045** über `.pot`, `readme.txt`, Doku | LB `check-domains.php`/`make-pot.sh` (angepasst), WP-CLI i18n | ● | ● | ● |
| **J-04 lint-workflow** | `actionlint`, `zizmor` (Workflow-Sicherheit: Injection, unpinned Actions, Rechte), `shellcheck` für `tools/**/*.sh`; Doku-Lint (Links) | actionlint, zizmor, shellcheck | ● | ● | ● |
| **J-05 phpstan** | **Stufe 8** ohne Baseline (Vorschlag, §1.5 T-1), `phpVersion` 80200–80500, `szepeviktor/phpstan-wordpress`, `reportUnmatchedIgnoredErrors`; neue `ignoreErrors` nur mit Kommentar und Ticket | PHPStan 2 | ● | ● | ● |
| **J-06 sast** | §2.1 | Semgrep, Psalm `--taint-analysis`, CodeQL (JS) | ● | ● | ● |
| **J-07 deps** | §2.2 | composer audit, npm audit, OSV-Scanner | ● | ● | ● |
| **J-08 secrets** | §2.3 | gitleaks | ● (Diff) | ● (Historie) | ● |
| **J-09 licenses** | §2.4 | composer licenses, license-checker | ● | ● | ● |
| **J-10 free-guard** | §2.5, auf Quelltext; dieselbe Prüfung läuft in J-17 auf dem ZIP | eigenes PHP-Skript (Muster Suite A2) | ● | ● | ● |
| **J-11 unit** | PHPUnit-Unit-Suite ohne WordPress (Domänenlogik: Bewertung, Smarttrainer, Fristen, Zustandsautomaten R0-09) unter PHP 8.2 und 8.5; JS-Unit-Tests (`@wordpress/scripts test-unit-js`); Reihenfolge zufällig mit ausgegebenem Seed (R0-05 D-03) | PHPUnit 11, Jest | ● | ● | ● |
| **J-12 integration** | PHPUnit mit **WP-Testsuite** (E-11) gegen echte DB in M-Z, M-U, M-M (nightly + M-N, M-R, M-MS, M-I*). Kataloge RBAC, SEC, PRIV, GRANT, SEAT, ID, REV, PUB, IMP, MED, CON, REC (Einzelprozess), NTF, CT-Routen-Snapshot (undeklarierte Änderung an REST-Routen = rot, C-7), Lifecycle (Aktivierung, Deaktivierung, Uninstall-Manifest REC-020). Abfragezahl-Konstanz n = 1/20/100 und Scanregel auf Profil `ci` (NFR-002/-003, E-60) als Gruppe `perf-query` | PHPUnit + `wp-phpunit` | ● | ● | ● |
| **J-13 gm** | Golden-Master-Suite GM-001…GM-190 (Gruppe `gm`, Fixture `cc-fixture-neutral-v1` idempotent geladen, R0-05 §2); Abweichungsliste (E-55) wird gelesen: Test mit freigegebener Abweichung prüft das **neue** Soll, ohne Freigabe das LB-Soll. Nightly: zweiter Lauf mit gleichem Seed, Ergebnis-JSON muss byte-gleich sein (D-13); Fixture-Paket-Determinismus (`package_id`, `content_sha256`) | PHPUnit, Fixture-Builder | ● (M-Z, M-U, M-M) | ● | ● |
| **J-14 e2e** | `@playwright/test` (E-11) **mit `--trace off`** (kein Trace-Archiv mit Session-Cookies/Nonces), `video: off`, `screenshot: only-on-failure` (nur synthetische Daten). Pflichtabläufe NFR-016, R0-11 Flow 1–3, Prüfung mit Reload/Netzabbruch, Admin-Import, ohne JavaScript, Tastatur-Referenzlauf A11Y-101; Web-Vitals FE-01…FE-03 (NFR-014) informativ in CI, verbindlich auf R-M. WP läuft im Staging-Compose (R0-16 §3.1) bzw. nginx-Variante | Playwright | ● Chromium + WebKit | ● + Firefox, Edge, Mobil | ● alle Projekte |
| **J-15 a11y-axe** | A11Y-100: axe je Screen L-01…L-21, A-01…A-16, Zustände Z-L/Z-F/Z-G/gefüllt, 1280×900 und 390×844, Hell/Dunkel. Regeln `wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa`: **jeder Verstoß blockiert** (D-15-05); `best-practice` nur Bericht. Ausnahmen nur in `tests/a11y/exceptions.json` (Screen, Regel, Begründung, Ticket, Ablaufdatum; abgelaufene Ausnahme = rot) | `@axe-core/playwright` | ● Chromium | ● | ● (+ Firefox/WebKit) |
| **J-16 migration** | **Upgrade-Probe:** Vorversion = letzter Tag `v*` (`git describe` auf `HEAD^`, LB ci.yml:557-561), vor dem ersten Tag eine eingecheckte Basis-Fixture; Daten anlegen, neues ZIP **ohne Reaktivierung** darüberlegen, Migration auslösen, `upgrade-check before/after` (Zeilenzahl + `CHECKSUM TABLE` je `cc_*`, erwartete Backfill-Werte), Weiterbetrieb (Lernen, Prüfung, Import). PR zusätzlich: MIG-016 (Code N+1 liest Schema N, nie 500), MIG-023 (Prüfsumme der Schrittliste), Einzelprozess-MIG (010, 012, 013, 018, 022, 024…026). Nightly: Mehrprozess-MIG (001…009, 011, 014, 015, 017, 020, 021) auf MariaDB **und** MySQL 8.4, Downgrade MIG-019/REC-025 (E-90) | PHPUnit, MP-Runner, WP-CLI, LB `upgrade-check.php` (angepasst) | ● | ● | ● |
| **J-17 build-zip** | §3.1–3.3: ZIP-Build, Negativliste, Free-Guard auf ZIP, `CREDITS.md`, Versionsgleichheit, **Plugin Check** (`wp plugin check`, 0 Fehler; Warnungen mit Begründung), `readme.txt`-Validierung, `php -l` aller ZIP-Dateien unter 8.2, Bundle-Budgets FE-04…FE-06 (NFR-015), **Artefakttest**: ZIP in frisches WP installieren (kein Symlink, kein `tests/`), Smoke + Lern-/Prüfungs-/Importfluss gegen das installierte Paket (LB INV-E-090), Deaktivierung/Reaktivierung | LB `build-zips.sh` (angepasst), Plugin Check | ● | ● | ● |
| **J-18 repro-sbom** | zweiter, unabhängiger Build im frischen Container mit `SOURCE_DATE_EPOCH` = Commit-Zeit; `SHA256SUMS` beider Builds müssen gleich sein; SBOM (§3.4) | build-zips.sh, CycloneDX | – | ● (+ main) | ● |
| **J-19 perf-smoke** | Profil `ci` (R-CI, **keine Zeiten**): L5-Race (NFR-010/-011) – PR: 3 Wiederholungen je Szenario auf M-Z; nightly/Release: 20 Wiederholungen auf MariaDB und MySQL 8.4. Nightly zusätzlich k6-Smoke L1 mit 10 % Last, 3 min: nur Fehlerquote < 0,1 %, 0 × 5xx, 0 Lock-Wait-Timeouts | PHP-MP-Runner, k6 (dev-only, AGPL, nicht im Paket) | ● (reduziert) | ● | ● |
| **J-20 staging** | Staging-Compose: Erststart, Seiten erreichbar, Neustart wiederholbar, keine PHP-Fehler, Abbau (LB INV-E-093); im Release mit dem Release-ZIP | docker compose | – | ● | ● |
| **J-21 coexistence** | Release-Gate 23.2 Nr. 9: Course Captain und LB-Plugin (fester Commit `9f37c77`, nur installiert, nicht verändert) auf derselben Site aktiv; keine Kollision bei Tabellen, Optionen, Rollen, Cron-Hooks, REST-Namespaces, Shortcodes, Textdomains; Deinstallation von CC lässt LB-Daten unberührt | WP-CLI, PHPUnit | – | ● | ● |
| **J-22 standalone** | B-6: CI-Lauf ohne Woo/Suite/Pro ist der Normalfall aller Zeilen; zusätzlich Prüfung, dass keine Klasse/Funktion aus Woo, Suite oder Pro ohne `class_exists`/`function_exists`-Schutz referenziert wird (PHPStan-Regel ohne Stubs dieser Plugins) | PHPStan-Zusatzkonfiguration | ● | ● | ● |
| **J-23 docs-trace** (Planungsrepo) | R0-12-§5-Skript: `fehlend []`, `doppelt []`, `ohne Ziel = 0`, unbekannte Test-IDs; Eindeutigkeit der E-/D-Nummern | Python | ○ | ○ | – |

`perf-release.yml` (nicht Teil von `ci.yml`): L1…L4 auf R-M mit Profil `default`/`heavy`, drei Läufe, Median der p95, Protokoll nach R0-14 §2.3 Nr. 4. Pflicht je Minor-Release und für R1-GATE (E-60, NFR-001/-004…-009, -014, -018). Bereitstellung von R-M → D18-5.

### 1.5 Technische Festlegungen (ohne Auftraggeber-Entscheidung)

| Nr. | Festlegung | Begründung |
|---|---|---|
| T-1 | PHPStan **Stufe 8** ohne Baseline ab dem ersten Commit; Anhebung auf 9 für Domänen-Namespaces (`Domain\*`) in R1 prüfen | LB Stufe 5 mit 691 Zeilen Baseline (INV-E-112); Greenfield kann strenger starten |
| T-2 | PHPCS: volle WPCS-Regeln **blockierend** (nicht nur Security wie LB), Ausnahme Dateinamensregel | WordPress.org-Review und Plugin Check prüfen WPCS-nahe Regeln; LB-Stil-Abweichung ist Altlast (INV-E-113) |
| T-3 | Psalm nur für Taint-Analyse, nicht als zweiter Typprüfer | Doppelte Typprüfung kostet Pflege ohne Nutzen |
| T-4 | Coverage nur als Bericht (nightly), kein Prozent-Gate; Gate ist die Pflichtmenge der Kataloge (E-184) | Prozentwerte belohnen triviale Tests |
| T-5 | E2E-Server: Staging-Compose (Apache bzw. nginx), Built-in-Server nur für Integration | R0-14 §3 Webserver-Achse |
| T-6 | Free hat keine Composer-Laufzeitabhängigkeiten (eigener Autoloader wie LB); falls doch nötig: `composer install --no-dev --classmap-authoritative`, Präfixierung (Namespace-Scoping) Pflicht | Konflikte mit anderen Plugins; ZIP-Negativliste |

---

## 2. Sicherheit

### 2.1 SAST

| Werkzeug | Umfang | Gate |
|---|---|---|
| **Semgrep** (CLI, Version gepinnt; Regeln im Repo unter `tools/semgrep/`, nicht aus der Registry nachgeladen) | PHP und JS. Eigene CC-Regeln: `$wpdb->query/get_*` mit interpolierter Variable ohne `prepare`; Superglobals ohne `sanitize_*`/`wp_unslash`; `register_rest_route` ohne `permission_callback` oder mit `__return_true` außerhalb der Allowlist öffentlicher Routen (R0-19); Ausgabe ohne `esc_*`; `wp_remote_*` ohne `reject_unsafe_urls` bzw. `wp_safe_remote_*` (SSRF, R0-10); `ZipArchive::extractTo` ohne Pfadprüfung (ZIP-Traversal); `innerHTML`; `unserialize`; `md5`/`rand` für Tokens. Dazu ausgewählte öffentliche PHP-/JS-Regelsätze, lokal gespiegelt | PR: Befunde im Diff mit Schwere ERROR/WARNING blockieren; nightly: Vollscan, neue Befunde → Issue |
| **Psalm `--taint-analysis`** mit WordPress-Stubs | Quellen: `$_GET/$_POST/$_REQUEST/$_COOKIE/$_SERVER`, `WP_REST_Request::get_param`, Importpaket-Inhalte; Senken: SQL, HTML-Ausgabe, Shell, Datei-Includes, Header, `wp_redirect` | PR: jeder neue Taint-Befund blockiert; Unterdrückung nur mit `@psalm-taint-escape`-Begründung im Code |
| **CodeQL** (JavaScript) | Admin-/Lernraum-JS | nur wenn verfügbar (D18-1); dann nightly, Schwere „high“ blockiert Release |
| PHPCS Security (J-01) | `WordPress.Security.*`, `PreparedSQL*` | blockierend |

Schwellen (C-13, „0 offene Befunde hoch“): ERROR/high/critical blockiert PR und Release; medium blockiert das Release, sofern keine dokumentierte Ausnahme (`security/exceptions.yml`: Befund-ID, Begründung, Reviewer, Ablaufdatum ≤ 90 Tage) vorliegt; low = Bericht.

### 2.2 Dependency-Scan

- `composer validate --strict`, `composer audit --locked` (Laufzeit- **und** Entwicklungsabhängigkeiten); `npm ci` + `npm audit --audit-level=high` (getrennt: `--omit=dev` blockierend für ausgelieferten Code; Dev-Abhängigkeiten blockieren bei critical, sonst Bericht).
- **OSV-Scanner** nightly über alle Lockfiles (zweite Datenquelle; findet Advisories auch ohne Codeänderung).
- **Lockfile-Pflicht:** `composer.lock` und `package-lock.json` eingecheckt; CI bricht ab, wenn Manifest und Lock nicht passen.
- **Gebündelte Fremddateien** (z. B. Sanitizer im Lernraum, R0-17 T-05) stehen mit Version und Quelle in `CREDITS.md`; J-07 prüft sie per Paketname/Version gegen OSV.
- **Dependabot** (`.github/dependabot.yml`): Ökosysteme `composer`, `npm`, `github-actions`, `docker` wöchentlich, Sicherheitsupdates sofort; gruppierte Dev-Updates; kein Auto-Merge für Laufzeitabhängigkeiten. Falls Dependabot-Alerts nicht verfügbar sind (D18-1), ersetzt der nightly OSV-Lauf die Benachrichtigung (Issue).
- Fristen: critical ≤ 7 Tage, high ≤ 30 Tage bis Fix oder begründeter Ausnahme; ein offener high-Befund in ausgelieferten Abhängigkeiten sperrt das Release (C-13).

### 2.3 Secret-Scan

- **gitleaks** als gepinntes Binary (Prüfsumme verifiziert, nicht die lizenzpflichtige Action): PR = Diff, nightly = gesamte Historie, Release = Tag-Commit **und** Inhalt der ZIPs.
- Zusatzregeln: `COURSE_CAPTAIN_SECRET_KEY`-Werte, License-Captain-Schlüsselformate, WordPress-Salts in Fixtures, `.env`-Dateien.
- GitHub Secret Scanning mit Push Protection, wenn verfügbar (D18-1).
- Fund in der Historie: Secret sofort rotieren (Runbook R0-13), dann erst bereinigen; Allowlist nur für synthetische Test-Werte mit Kennzeichnung `cc-test-`.

### 2.4 Lizenzprüfung der Abhängigkeiten

Free steht unter **GPL-2.0-or-later** (E-01, WordPress.org).

| Klasse | Lizenzen (SPDX) | Regel |
|---|---|---|
| erlaubt im Paket | GPL-2.0-or-later, GPL-3.0-or-later, LGPL-2.1-or-later, LGPL-3.0-or-later, MIT, ISC, BSD-2-Clause, BSD-3-Clause, 0BSD, Zlib, Unlicense, CC0-1.0, MPL-2.0, Apache-2.0 | Apache-2.0 ist nur mit GPLv3 vereinbar; zulässig, weil „or-later“ die Weitergabe unter GPLv3 erlaubt. Wird in `CREDITS.md` vermerkt |
| erlaubt nur dev (nicht im ZIP) | zusätzlich AGPL-3.0 (k6), weitere OSI-Lizenzen | J-09 bestätigt über die ZIP-Liste, dass nichts davon ausgeliefert wird |
| verboten | proprietär/„UNLICENSED“, unbekannt, SSPL, BUSL, „JSON“ (Good-not-Evil), CC-BY-NC/-ND, GPL-2.0-only-unverträgliche Kombinationen | blockiert PR |
| Medien/Fonts/Icons | CC0, CC-BY-4.0 (mit Namensnennung), OFL-1.1, GPL | Demo ohne Fremdinhalte (R1-39); jede Datei in `CREDITS.md` |

Werkzeuge: `composer licenses --no-dev --format=json`, `license-checker` (npm, `--production --onlyAllow`), Abgleich mit `CREDITS.md` (jede gebündelte Datei hat Eintrag; INV-E-116). Wöchentlicher Vollbericht als Artefakt.

### 2.5 CI-Guard „kein Lizenzclient/Updater in Free“

Grundlage: Suite-Guard A2 (`wpaffiliatemaster/tests/run.php:332-363`), R0-36 A8/L1, R0-16 §3.3 Nr. 6. Läuft in J-10 auf dem Quelltext (`course-captain.php`, `uninstall.php`, `src/`, `assets/`, `blocks/`, Build-Ausgabe) und in J-17 auf dem entpackten ZIP. Treffer = rot.

| Muster | Grund |
|---|---|
| `LicenseClient`, `LicenseManager`, `CourseCaptainPro\Licensing`, `Captain\Licensing` | keine Clientkopie im Free |
| `wplc/v1`, `\bwplc_` | keine Aufrufe/Optionen des Lizenzservers |
| `Update URI\s*:` im Plugin-Kopf | keine eigene Update-Quelle (WordPress.org-Richtlinie 8) |
| `pre_set_site_transient_update_plugins`, `site_transient_update_plugins`, `plugins_api`, `upgrader_pre_download` als Hook | kein eigener Updater |
| `api.github.com`, `github.com/.*/releases` | keine GitHub-Abfrage (E-09; LB INV-C-066 entfällt) |
| `license_key`, `licence_key`, `is_entitled`, `allows_entitlement` | kein Lizenzschlüssel, keine Entitlement-Prüfung (WordPress.org-Richtlinie 5: kein Trialware) |
| `course-captain-pro` als Textdomain in Free | Pro-Strings gehören nicht in Free |

Ausnahmen: Datei `tools/qa/free-guard-allow.txt` (Pfad, Muster, Begründung), Änderung nur per CODEOWNERS-Review. Erwartbar: neutrale Erweiterungs-Hooks, die Pro nutzt (`course_captain_*`), heißen nie `entitlement`/`license`. Gegenprobe im Pro-Repository (R3-18): Pro **muss** `Update URI` und den Client unter eigenem Namespace enthalten und darf keinen Free-Code kopieren.

---

## 3. Release-Artefakte

### 3.1 Reproduzierbarer ZIP-Build (aus LB `build-zips.sh`, nach E-01)

`tools/release/build-zips.sh [--rev <commit>] [--out <ordner>] [--tag vX.Y.Z] [--package free|pro]` – lokal und in der CI dasselbe Skript.

| Schritt | Übernommen aus LB | Änderung für CC |
|---|---|---|
| Quelle | `git archive` eines Commits, nie Arbeitskopie (build-zips.sh:60-69) | unverändert |
| Build der Assets | esbuild-Minify optional, `node --check` (:120-131) | `npm ci` mit Lockfile, Node-Version aus `.nvmrc` gepinnt, `wp-scripts build`; **lesbare Quelle im Paket** (WordPress.org); Source Maps nicht im ZIP |
| Determinismus | Dateizeit = Commit-Zeit, `LC_ALL=C sort`, `TZ=UTC zip -X -D` (:71-75) | zusätzlich `SOURCE_DATE_EPOCH`, `umask 022`, Dateirechte normalisiert (644/755), Build in gepinntem Container-Image |
| Versionsgleichheit | Kopf = Konstante = `Stable tag` = oberster CHANGELOG-Eintrag (:82-94) | + `Requires PHP` = 8.2 / `Requires at least` = M-U, `Tested up to` = M-Z-Hauptversion; getrennte Versionen Core, Pro, `cc_db_version`, Paketformat, REST (R0-16 §3.3 Nr. 1) |
| Tag-Prüfung | Tag = Version; „geändert, aber Version gleich“ gegen vorigen Tag (:96-115) | unverändert |
| CHANGELOG-Marker | – | Pflichtmarker `Schema`, `Backfill`, `Destruktiv (Contract) – Backup vorher`, `Sicherheitsupdate:` werden gegen die tatsächlichen Änderungen geprüft (neue Migrationsschritte ⇒ Marker `Schema`; Contract-Schritt ⇒ Marker `Destruktiv`) |
| Pakete | vier LB-Pakete | `course-captain-<v>.zip` (R1); `course-captain-pro-<v>.zip` im Pro-Repository (R3-18) |
| Begleitdateien | `SHA256SUMS`, `versions.env`, `release-notes.md` (:170-201) | + SBOM, Signaturbündel, Provenienz (§3.4/3.5) |

### 3.2 Negativliste (Prüfung `check_zip`, erweitert)

Ein ZIP ist nur gültig, wenn **genau ein** Ordner `course-captain/` mit `course-captain.php`, `readme.txt`, `uninstall.php`, `CREDITS.md`, `LICENSE` und `languages/course-captain.pot` enthalten ist und **keiner** dieser Treffer vorkommt:

| Klasse | Muster |
|---|---|
| Entwicklerdateien (LB :150-165) | `tests/`, `tools/`, `node_modules/`, `vendor/` mit Dev-Paketen (Abgleich gegen `packages-dev` in `composer.lock`), `.git*`, `composer.json/.lock`, `package(-lock).json`, `phpstan*`, `phpcs*`, `.phpcs*`, `psalm*`, `phpunit.xml*`, `playwright.config.*`, `.editorconfig`, `.nvmrc`, `.DS_Store` |
| Betriebsfremdes | `.env*`, `*.sql`, `*.log`, `*.zip`, `*.map`, `docker-compose*`, `Dockerfile`, `.github/`, `.wordpress-org/` (Assets gehen getrennt nach SVN `assets/`) |
| Dev-only-Code | WP-CLI-Seeder `cc-dev` (R0-14 §2.2), Perf-Sonde `cc-perf-probe` (R0-14 §2.3), Testfixtures `cc-fixture-*`; Fehlerinjektion `course_captain_migration_fault` nur hinter `COURSE_CAPTAIN_TESTING` (R0-37 §7) – Guard prüft, dass jede Fundstelle diese Konstante abfragt |
| Legacy-Namen (INV-E-044, R0-16 §3.3) | `learnboating`, `bootspruefung`, `bp_`, `BP_`, `kurswasser`, `boatingschool`, `lbac_`, `lbvc_` (Groß-/Kleinschreibung egal; Ausnahme: Quellennachweis in `CREDITS.md`, falls E-01 Übernahme mit Nennung verlangt) |
| Lizenz/Updater | Muster aus §2.5 |
| Inhalte | Fremdmedien ohne `CREDITS.md`-Eintrag; Wortliste SEC-045 |

### 3.3 Prüfungen am Artefakt (J-17)

Plugin Check (`wp plugin check course-captain`), `readme.txt`-Validierung (Pflichtabschnitte, `Stable tag`, Upgrade Notice), `php -l` unter PHP 8.2, Installation aus dem ZIP in frisches WordPress mit Smoke/Lernfluss/Import (INV-E-090), Upgrade-Probe mit genau diesem ZIP (J-16), Uninstall-Manifest (REC-020), i18n: `.pot` im ZIP entspricht dem Quelltext; JSON-Übersetzungen für Skripte (`make-json`) erzeugbar.

### 3.4 SBOM und Checksummen

- **SBOM** im Format **CycloneDX 1.6 JSON** für das ausgelieferte ZIP (nicht für das Repository): PHP-Laufzeitabhängigkeiten (`cyclonedx-php-composer --omit=dev`), npm-Laufzeitpakete, die ins Bundle eingehen (`@cyclonedx/cyclonedx-npm --omit dev`), gebündelte Fremddateien aus `CREDITS.md` als manuelle Komponenten. Datei `course-captain-<v>.cdx.json`. J-09 prüft SBOM und `CREDITS.md` auf Gleichstand.
- **`SHA256SUMS`** über ZIP, SBOM und `release-notes.md`; `sha256sum -c` im Workflow (LB release.yml:64).
- Reproduzierbarkeit: zwei unabhängige Builds (J-18) liefern dieselben Prüfsummen, sonst kein Release.

### 3.5 Signatur

| Paket | Verfahren (Vorschlag) | Status |
|---|---|---|
| **Free** | (a) **GitHub Artifact Attestation** (Build-Provenienz nach SLSA v1, `actions/attest-build-provenance`, und SBOM-Attestierung) für ZIP; (b) **Sigstore/cosign keyless** `sign-blob` über `SHA256SUMS` mit GitHub-OIDC-Identität des Release-Workflows, Bündel `SHA256SUMS.sigstore.json`. Prüfanleitung in den Release-Notes (`cosign verify-blob --certificate-identity …`, `gh attestation verify`). WordPress.org prüft keine Signaturen; die Signatur sichert GitHub-Release, direkte Downloads und den Abgleich nach dem SVN-Deploy | D18-2 |
| **Pro** | Signatur nach **License-Captain-Vertrag**: Der Client prüft vor der Installation eine Signatur über das ZIP (Vorschlag Ed25519, öffentlicher Schlüssel im Pro-Client, privater Schlüssel nur im License Captain bzw. in einer geschützten CI-Umgebung). Zusätzlich dieselbe Attestierung wie Free. **Externer Blocker:** License-Captain-Release-Readiness offen (R0-36 L12), `captain-pro` ungeprüft (E-42) | D18-3, R3-18 |

E-64 („keine Paketsignaturen in R1“) betrifft nur **Content-Importpakete** (R0-10) und bleibt davon unberührt.

### 3.6 Release-Workflow und WordPress.org-SVN-Deploy (E-13)

| Schritt | Job | Inhalt | blockiert bei |
|---|---|---|---|
| 1 | `preflight` | Tag `v<version>` liegt auf `main` (`merge-base --is-ancestor`, LB release.yml:45-53); Tag = Plugin-Version; CHANGELOG-Marker; keine offene Sicherheits-Ausnahme mit abgelaufenem Datum | Abweichung |
| 2 | `ci` (`workflow_call`, `lane=release`) | kompletter Nightly-Umfang auf dem Tag-Commit (statt LB „irgendein grüner Lauf“, release.yml:34-43) | ein roter Pflichtjob |
| 3 | `build` ×2 | J-17 + J-18: zwei Builds, gleiche Prüfsummen, SBOM | Abweichung |
| 4 | `sign` | Attestierung, cosign (Rechte `id-token: write`, `attestations: write` nur hier) | Fehler |
| 5 | `staging` | Release-ZIP im Staging-Compose: Neuinstallation **und** Upgrade von der Vorversion, Systemstatus „Schema aktuell“ (E-13 „Staging“, LB release-regel Schritt 4) | Fehler |
| 6 | `github-release` | **Entwurf** mit ZIP, `SHA256SUMS`, SBOM, Signaturbündel, Release-Notes; Vorabversion bei `-` im Tag | – |
| 7 | `wporg-deploy` | Environment **`wordpress-org`**: Pflicht-Reviewer (D18-6), Selbstfreigabe aus, nur Tags `v*` ohne `-`, SVN-Zugang nur hier. Ablauf: ZIP entpacken (genau das signierte Artefakt, nicht der Repo-Stand), `svn` Checkout, (1) `tags/<version>/` anlegen und committen, (2) `assets/` aus `.wordpress-org/`, (3) `trunk/` per `rsync --delete` ersetzen inkl. `readme.txt` mit neuem `Stable tag` – **zuletzt** (R0-16 §3.3 Nr. 5). Kein Deploy, wenn `Stable tag` auf eine Version mit älterem Schema zurückgehen würde (R0-16 §3.3 Nr. 7) | fehlende Freigabe |
| 8 | `wporg-verify` | nach Bereitstellung `downloads.wordpress.org/plugin/course-captain.<version>.zip` laden; Dateiliste und SHA-256 je Datei mit dem eigenen ZIP vergleichen (WordPress.org packt neu, deshalb Inhaltsvergleich) | Abweichung → Alarm, Runbook „Release zurückziehen“ nur vorwärts per PATCH |
| 9 | `publish` | GitHub-Release-Entwurf veröffentlichen | – |

Sicherheitsrelease (R0-16 §3.3 Nr. 8, LB `release-regel.md` T117): gleicher Ablauf, Freigabe beschleunigt, Advisory erst nach Frist veröffentlichen; private Meldung über GitHub Security Advisory.

**Pro (`pro-release.yml`, R3-18):** Schritte 1–6 identisch mit Pro-Guard (§2.5 Gegenprobe), Pro-Matrix inklusive installierter Free-Version `requires_core`, danach Environment `license-captain` (Pflicht-Reviewer) mit Upload über die License-Captain-API, Signatur nach D18-3; nie nach WordPress.org.

---

## 4. Gate-Zuordnung, Flake-Policy, Laufzeitbudget

### 4.1 Welcher Job blockiert welches Gate

Gates nach R0-12 §3; „grün“ = Pflichtzeilen der Matrix mit Protokoll (Commit, Lauf-ID, Datum). Für R1-GATE gilt E-184 (100 %).

| Gate / Kriterium | Blockierende Jobs |
|---|---|
| **A** (R0-GATE): A-5, A-6 Kataloge/Traceability | J-23 (Planungsrepo, informativ bis R0-GATE, danach Pflicht vor jedem Gate-Protokoll) |
| A-7 ausführbare Referenztests | J-13 (Fixture gebaut, Rechenlogik-GM nach E-56) |
| **B-1** Golden Master Lernkern | J-13 |
| B-2/B-3 Revisionen, Publish-Validator | J-12 |
| B-4 Referenz-E2E alle Browser, Desktop + Mobil | J-14 (Release-Lane mit allen Projekten) + manueller Gerätelauf (R1-18) |
| B-5 Kurs ohne Import | J-13 (GM-137), J-14 Flow 1 |
| B-6 Standalone | J-22, alle Matrixzeilen ohne Woo/Suite/Pro |
| B-7/B-8 Prozessfälle, Zertifikatsbasis | J-12, J-14 |
| **C-1** Rechte, IDOR, Sicherheit | J-12 |
| C-2/C-3 Grants/Seats/Identität, Import/Medien | J-12, J-13 |
| C-4 Nebenläufigkeit (MariaDB **und** MySQL 8.4) | J-12 (M-Z, M-M), J-19 (L5 beide DBs) |
| C-5 Recovery | J-12, J-16, J-20 |
| C-6 Nichtfunktionale Ziele | J-12 `perf-query`, J-17 Bundle-Budgets, J-19; **Zeitbudgets nur `perf-release.yml` auf R-M** (E-60) |
| C-7 API-Verträge | J-12 (Routen-Snapshot, CT-Tests) |
| C-8 Betrieb, Migration, Lifecycle | J-16, J-12 (M-MS), J-17 (Uninstall-Manifest) |
| C-9 Datenschutz | J-12 (PRIV) |
| C-10 Accessibility, i18n | J-15, J-03, J-14 (A11Y-101); manuelle Sitzungen R1-29 außerhalb der CI |
| C-12 Benachrichtigungen | J-12 (NTF, Mail-Sink) |
| **C-13** Sicherheitsprüfung | J-06, J-07, J-08, J-09, J-10, J-01 (Security-Regeln) |
| C-14 Kompatibilität | J-12/J-14 alle Pflichtzeilen §1.3; informative Zeilen nicht |
| C-15 Distribution (R1-39) | J-17, J-16, J-18, J-20, `wporg-deploy`-Trockenlauf (SVN gegen Test-Checkout, ohne Commit) |
| C-16 Release-Gates 23.2 Nr. 1–3, 6–9 | J-22 (Nr. 1), J-13/J-14 (Nr. 2, 3), J-15/J-03 (Nr. 6), J-16/J-12 (Nr. 7), J-21 (Nr. 9); Nr. 8 Doku-Review |
| **R1-GATE** | alle obigen Jobs grün auf dem Gate-Commit in der Release-Lane, `perf-release`-Protokoll vorhanden, keine Quarantäne einer Pflichttest-ID (§4.2) |
| **D-1** Regression | gesamte PR- und Nightly-Spur bleibt Pflicht |
| D-6 Pro-Lizenzende, Core-ZIP ohne Lizenzcode | J-10/J-17 (Free-Guard), Pro-Repo-CI (R3-18) |
| **Merge in `main`** | alle PR-●-Jobs (Required Checks, Branch-Schutz, keine Admin-Umgehung) |
| **Release (Tag)** | Release-Lane komplett + Schritte §3.6 |

### 4.2 Flake-Policy

1. **Deterministische Suiten ohne Retry:** J-11, J-12, J-13, J-16, J-19 laufen mit `retries = 0`. Ein sporadischer Fehler gilt als Produkt- oder Testfehler (Race, Uhr, Reihenfolge), nicht als „Flake“.
2. **E2E:** `retries: 1` nur in CI. Ein Test, der erst im zweiten Versuch besteht, wird als `flaky` im Bericht markiert; der Job bleibt grün, erzeugt aber automatisch ein Issue (Label `flaky`, Test-ID, Lauf-ID).
3. **Quarantäne:** Ein Test mit drei `flaky`-Markierungen in 14 Tagen oder zwei roten Läufen ohne Codebezug kommt per Tag `@quarantine` und Eintrag in `tests/quarantine.yml` (Test-ID, Issue, Owner, Ablaufdatum ≤ 14 Tage) aus der PR-Spur; er läuft nightly weiter und blockiert dort nicht. Höchstens **5** Tests gleichzeitig in Quarantäne; darüber blockiert J-14 alle PRs außer Fixes.
4. **Gates:** Ein Gate-Commit darf **keine** Pflichttest-ID in Quarantäne haben (E-184). Abgelaufene Quarantäne = rot.
5. **Infrastrukturfehler** (Runner, Paketspiegel, Docker-Pull): einmal manuell „Re-run failed jobs“ erlaubt, mit Kommentar im PR. Wiederholt sich der Fehler, wird die Quelle gecacht oder gepinnt (G-02, G-07).
6. **Informative Matrixzeilen** zählen nicht als Flake; Regel „vier Nächte rot → Issue“ (§1.3).
7. Kennzahl im Nightly-Bericht: Flake-Rate je Suite (Ziel < 1 % der E2E-Testläufe über 30 Tage).

### 4.3 Laufzeitbudget (Schätzung, R1-Umfang; GitHub-gehostete Runner)

| Spur | Wanduhr (kritischer Pfad) | Runner-Minuten gesamt | Kritischer Pfad |
|---|---|---|---|
| PR | **≤ 20 min** | ≤ 120 | Integration M-Z ≈ 12 min oder E2E Chromium+WebKit ≈ 15 min (Sharding 2×) |
| `main` | ≤ 25 min | ≤ 150 | wie PR + Firefox-E2E, Repro-Build |
| Nightly | ≤ 120 min | ≤ 600 | MIG-Mehrprozess + L5 auf zwei DBs ≈ 60 min, k6-Smoke |
| Wöchentlich | ≤ 90 min | ≤ 250 | axe Firefox/WebKit, Browser-Vorversionen |
| Release (ohne Wartezeit auf Freigabe) | ≤ 150 min | ≤ 700 | Release-Lane = Nightly-Umfang + Staging + Signatur |
| `perf-release` (R-M) | ≤ 6 h | eigene Umgebung | L1–L4 × 3 Läufe, L4 allein 2 h |

Richtwerte je PR-Job (Timeout = Budget + 50 %): Lint J-01…J-04 je ≤ 3 min; PHPStan ≤ 4 min; SAST ≤ 6 min; Deps/Secrets/Lizenzen je ≤ 2 min; Free-Guard ≤ 1 min; Unit ≤ 3 min; Integration je Zeile ≤ 12 min (Parallelisierung nach Testgruppen); GM ≤ 6 min; E2E ≤ 15 min; axe ≤ 8 min; Migration ≤ 8 min; Build + Artefakttest ≤ 8 min; L5 reduziert ≤ 5 min. Überschreitet die PR-Spur 20 min im Median über eine Woche, werden Suiten nach Laufzeit gesplittet oder Teile nach nightly verschoben – **nie** Pflichtkataloge der Gates, nur deren Wiederholungen (z. B. L5-Iterationen).

---

## 5. Entscheidungsbedarf (nur neue Punkte)

Geprüft gegen E-01…E-187. Bereits geregelt und hier nur als Annahme genutzt: E-11, E-13, E-57, E-58, E-60, E-61, E-184 (außerdem E-01, E-02, E-09, E-42, E-49, E-55, E-56, E-64, E-90, E-121). Technisch festgelegt ohne Auftraggeber-Entscheidung: §1.1 G-01…G-11, §1.5 T-1…T-6, SAST-/Dependency-Schwellen §2.1/§2.2, Lizenz-Allowlist §2.4, Negativliste §3.2, Flake-Policy §4.2.

| ID | Frage | Optionen | Empfehlung | Entscheider | blockiert |
|---|---|---|---|---|---|
| **D18-1** | Sichtbarkeit des Quellrepositorys und GitHub-Ausstattung (CodeQL, Secret Scanning mit Push Protection und Dependabot-Alerts sind für private Repositories kostenpflichtig) | a) öffentliches Repository ab R1-Start · b) privat mit GitHub Advanced Security · c) privat ohne Zusatzlizenz, nur die frei nutzbaren Werkzeuge (Semgrep, Psalm, gitleaks, OSV, composer/npm audit) | **c) als Pflichtbasis**, die unabhängig vom Plan funktioniert; **a)** spätestens zur ersten WordPress.org-Veröffentlichung (Free ist ohnehin öffentlich GPL), dann GitHub-Funktionen zusätzlich | Auftraggeber | J-06, J-07, J-08 (Umfang), R0-35 |
| **D18-2** | Signatur der Free-Artefakte | a) GitHub Artifact Attestation + Sigstore/cosign keyless (keine Schlüsselverwaltung) · b) GPG-Schlüssel des Maintainers · c) nur `SHA256SUMS` wie LB | **a)**: prüfbar ohne geheimen Schlüssel, an den Release-Workflow gebunden; b) erzeugt Schlüsselrotation und Verlustrisiko | Auftraggeber + Architektur | `release.yml` Schritt 4, R0-35 |
| **D18-3** | Signatur der Pro-ZIPs, die der Pro-Client vor der Installation prüft | a) License Captain signiert beim Upload (Serverschlüssel), Client prüft · b) CI signiert mit Schlüssel in geschütztem Environment, License Captain speichert und liefert die Signatur aus · c) nur Prüfsumme über TLS | **a)**, falls License Captain das umsetzt (Auftrag analog E-42), sonst **b)**; c) schützt nicht gegen einen kompromittierten Downloadserver | Auftraggeber (mit License-Captain-Verantwortlichem) | R3-18, R0-39 |
| **D18-4** | Abdeckung von E-61 jenseits von Playwright (echtes Safari/iOS, Firefox ESR, jeweils Vorversion N-1) | a) Playwright aktuell (Chromium, Edge, Firefox, WebKit) je PR/nightly; N-1 wöchentlich über die vorige Playwright-Version als Näherung; echtes iOS/Android/Safari manuell vor jedem Minor-Release · b) zusätzlich kommerzielles Geräte-/Browser-Grid wöchentlich und vor dem Release · c) nur Playwright aktuell | **a)** für R1 (keine laufenden Kosten, deckt Engines ab); b) nachrüsten, wenn manuelle Läufe Befunde zeigen, die WebKit nicht findet | Auftraggeber (Kosten) | J-14, B-4, C-14, R1-18 |
| **D18-5** | Bereitstellung der Messumgebung R-M für `perf-release` (E-60) | a) Cloud-VM mit festem Instanztyp, je Lauf per Workflow erzeugt und danach gelöscht · b) dauerhafter selbst gehosteter Runner auf eigener Hardware · c) manuell auf Entwicklerrechner mit Protokoll | **a)**: reproduzierbare Hardwareangabe, keine Dauerkosten, kein dauerhafter Runner mit Repository-Zugriff (Angriffsfläche von b) | Auftraggeber (Kosten) | R1-22, C-6, R1-GATE |
| **D18-6** | Wer gibt den WordPress.org- bzw. License-Captain-Deploy im geschützten Environment frei (E-13 verlangt eine manuelle Freigabe, nennt aber keine Person) | a) Auftraggeber (Vertretung benannt), Selbstfreigabe durch den Tag-Ersteller ausgeschlossen · b) jeder Maintainer · c) zwei Freigaben (Maintainer + Auftraggeber) | **a)**; c) nur für MAJOR-Releases und Releases mit Contract-Schritt (E-172) | Auftraggeber | `release.yml` Schritt 7, R0-35 |
| **D18-7** | Koexistenztest mit dem LB-Plugin in der CI (Release-Gate 23.2 Nr. 9) | a) nightly gegen festen LB-Commit `9f37c77`, Plugin nur installiert, nie verändert oder weitergegeben · b) nur einmalig manuell vor R1-GATE · c) entfällt | **a)**; braucht Lesezugriff der CI auf das LB-Repository (Deploy-Key nur lesend, nur nightly, nicht in PR-Jobs) | Auftraggeber | J-21, C-16 |

---

## 6. Übergaben

| Ziel | Übergabe |
|---|---|
| R0-20 | Matrixzeilen §1.3 als Startwerte; endgültige Versionen und „aktuelle + vorherige Hauptversion“-Regel |
| R0-35 | §3.6 SVN-Ablauf, `.wordpress-org/`-Assets, Plugin Check, readme-Validierung, Signaturhinweis in der Distribution |
| R0-39 / R3-18 | Free-Guard §2.5 und Pro-Gegenprobe; Pro-Signatur D18-3; `pro-release.yml` |
| R1-01 | Repository-Gerüst: `.github/workflows/{ci,release,perf-release}.yml`, `dependabot.yml`, `CODEOWNERS`, `tools/{release,qa,i18n,semgrep}/`, `phpstan.neon.dist`, `phpcs.xml.dist`, `psalm.xml`, `playwright.config.ts`, Branch-Schutz mit Required Checks |
| R1-18 / R1-39 | Gate-Protokoll mit Lauf-IDs der Release-Lane; manueller Gerätelauf |
| R1-22 | `perf-release.yml`, R-M nach D18-5 |
| R1-40 / R1-42 | MP-Runner und Fehlerinjektion für J-16/J-19 |
| R0-13 | Runbooks „Secret geleakt“, „Release zurückziehen (vorwärts)“, „Dependency-Advisory critical“ |

---

## 7. Nachweis

- Gelesen: `TODO-WETTBEWERBSANALYSE.md` (R0-18, R1-18, R1-39, R0-20, R0-35, R0-39, R3-18); `konzept.md` Kap. 23.1/23.2; `docs/r0/ENTSCHEIDUNGEN.md` (E-01…E-187, insbesondere E-11, E-13, E-57, E-58, E-60, E-61, E-64, E-184); R0-02/E §1.8, §2.6, §4 (INV-E-043/044, -087…-097, -112, -113, -116); R0-05 §3, §5; R0-12 §3, §6; R0-14 §2, §3, §5, §6; R0-15 §2.2 (A11Y-100…112), §2.4, §3.1, §3.8; R0-16 §3.3, §3.4, §6; R0-17 (T-05, AC-11, SEC-045); R0-36 A8, L1, L3, L12, §5.3; R0-37 §7–§9.
- LB @ `9f37c77` (nur gelesen): `.github/workflows/ci.yml` (Trigger :3-5, Rechte :7-8, Matrix :130-141, Notice-Grep, Playwright 1.48.2 nur Chromium, Jobs `release-zip` :467-526, `upgrade` :528-593, `static-analysis` :595-620, `staging` :622-660), `.github/workflows/release.yml` (CI-grün-Prüfung :33-42, Tag auf `main` :44-52, esbuild 0.24.2, `sha256sum -c`), `tools/release/build-zips.sh` (vollständig), `tools/qa/check-abspath.php` (Kopf), `phpstan.neon.dist` (Stufe 5, Baseline), `phpcs.xml.dist` (nur Security blockierend), `docs/release-regel.md` (Versionen, Ablauf, Sicherheitsmitteilung T117, Downgrade).
- Suite-Klon `wpaffiliatemaster/tests/run.php:332-363` (Guard A2, Muster und Dateiauswahl).
- Werkzeugnamen und -fähigkeiten (Semgrep, Psalm-Taint, gitleaks, OSV-Scanner, CycloneDX, cosign, GitHub Artifact Attestations, Plugin Check, zizmor) beruhen auf dem Wissensstand des Verfassers und sind bei R1-01 mit den dann aktuellen Versionen zu bestätigen; nichts davon wurde in diesem Repository ausgeführt.
- Nicht erfolgt: keine Änderung an anderen Dateien, an LearnBoating oder an den Suite-Klonen; keine Implementierung, kein CI-Lauf, kein Commit.
