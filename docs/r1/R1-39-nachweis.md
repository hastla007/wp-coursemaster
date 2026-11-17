# R1-39 · Distribution- und Erstinstallationsabnahme – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-39):** Free-ZIP Clean-Install, Upgrade, WordPress.org-Readme, i18n, Demo ohne Fremdinhalte, Support-Runbook und Deaktivierung geprüft.
**Quellen:** R0-35 §1.4 (readme), §1.6 (Lizenz), §3.1 (Positivliste), §3.2 (Versionierung), §3.3 (Update-/Restore-Proben), §3.4 (Erstinstallation, Demo, Deaktivierung), §5 (RC-xxx); R0-18 §3.1/§3.2 (Build, Negativliste), J-17. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `tools/release/build-zips.sh`, `tools/release/build-zip.php` | ZIP aus einem Commit (`--ref`) oder dem Arbeitsstand (`--worktree`); Dateien = `git ls-files` ohne `export-ignore`; feste Zeitstempel (`SOURCE_DATE_EPOCH` = Commit-Zeit) → byte-gleiche Builds; `SHA256SUMS` |
| `.gitattributes` | `export-ignore` für Entwicklungsdateien (tests, tools, docs, vendor, Konfigurationen, Planungsdokumente) |
| `tools/release/check-zip.php` | J-17-Prüfungen am ZIP: genau ein Wurzelordner, Positivliste (§3.1), Negativliste (R0-18 §3.2), Altnamen (RC-014), Versionsgleichheit Kopf = Konstante = `Stable tag` = oberster CHANGELOG-Eintrag (§3.2), Kopfzeilen und `Requires`-Gleichheit mit dem Readme, kein `Update URI` (RC-020), Readme-Regeln (≤ 5 Tags, Kurzbeschreibung ≤ 150 Zeichen, Abschnitte, Changelog-Eintrag der Version), GPL-Text (RC-011), `block.json`-Vertrag, `php -l` aller PHP-Dateien, Free-Guard auf dem entpackten Paket, Größe ≤ 10 MB |
| `tools/release/artifact-test.sh` | Artefakttest: ZIP in frisches WordPress (eigene DB) installieren und aktivieren ohne PHP-Notice/Warning, kein Symlink, kein `tests/`; Schema installiert; Deaktivieren ändert keine Tabellen; Reaktivieren; Uninstall ohne Bestätigung behält alle Daten (E-89); seit 17.11.2026 laufen alle WP-CLI-Aufrufe mit `tools/qa/wp-cli-autoload-isolation.php`, und der Test schlägt fehl, wenn eine `CourseCaptain\`-Klasse nicht aus `wp-content/plugins/course-captain/` der Test-Site stammt (B-07) |
| `.github/workflows/ci.yml` | neuer Job **J-17 build-zip** (Build aus `GITHUB_SHA`, `check-zip`, Artefakttest, Upload von ZIP + `SHA256SUMS`) |
| `tests/unit/DistributionTest.php` | Positivfall + Reproduzierbarkeit; **Negativtests**: `tests/`-Datei, `Update URI`, Altname, Versionsabweichung, Syntaxfehler, fehlende `LICENSE`, zweiter Wurzelordner → jeweils rot |

## 2. Ergebnisse (16.11.2026)

| Prüfung | Ergebnis |
|---|---|
| Build `--worktree` | 150 Einträge, 410 KB; zweiter Build byte-gleich (SHA-256 identisch) |
| `check-zip.php` | OK (0 Befunde) |
| Artefakttest (WordPress 7.1.4, PHP 8.3.6, MariaDB 10.11) | Aktivierung ohne PHP-Meldung, Schema `1.0.0+…`, 53 eigene Tabellen, Deaktivierung/Reaktivierung ohne Datenänderung, Uninstall ohne Bestätigung behält 53 Tabellen |
| `DistributionTest` | 2 Tests grün (7 Negativfälle rot wie erwartet) |

**Nachtrag 17.11.2026 (B-07, Autoloader-Isolierung im Artefakttest).** Der Artefakttest vom 16.11.2026 lief noch ohne Isolierung, hat also die Klassen aus `<repo>/src` statt aus dem ZIP ausgeführt; sein Ergebnis belegt den ZIP-Inhalt deshalb nicht vollständig.

| Prüfung | Ergebnis |
|---|---|
| Build `--worktree` + `check-zip.php` | OK (187 Einträge, keine Datei aus `tools/`) |
| Isolierung und Herkunftsprüfung (`tools/qa/wp-cli-autoload-isolation.php`) mit nachgebildetem WP-CLI und dem echten Composer-`ClassLoader`, PHP 8.3.6 | mit Isolierung: Klassen aus dem Plugin-Ordner, Prüfung `OK` (auch bei Classmap-Eintrag auf `<repo>/src`); ohne Isolierung: `FAIL`, Exit 1, Klasse und Datei in `<repo>/src` genannt; Datei fehlt im Plugin: Klasse nicht ladbar statt stillem Rückgriff auf das Repository |
| Artefakttest `tools/release/artifact-test.sh` mit Isolierung (CI-Job J-17 build-zip, Lauf 44032994780, Commit `bdb6cc6`; WordPress 7.1.4, PHP 8.5.12, MariaDB 11.8) | **OK**: `check-zip` OK (187 Einträge); Aktivierung ohne PHP-Meldung, Schema `1.0.0+80cb54804d39`, 53 eigene Tabellen, Deaktivierung/Reaktivierung ohne Datenänderung, Uninstall ohne Bestätigung behält 53 Tabellen; Herkunftsprüfung: Aktivierung lud 22, Uninstall 3 `CourseCaptain\`-Klassen, alle aus `/tmp/ccap-artefact-…/wp-content/plugins/course-captain/`; „autoload guard: 7 WP-CLI calls, all Course Captain classes from the installed plugin“ |

## 3. DoD-Abdeckung

| DoD-Punkt | Nachweis |
|---|---|
| Free-ZIP Clean-Install | Artefakttest (§2) |
| Upgrade | Proben aus R1-23 (`UpgradeProbeTest`, REC-023) und R1-40 (abgebrochenes Upgrade); Upgrade über den WordPress-Updater erst nach dem ersten Verzeichnis-Release (R0-35 §3.3) |
| WordPress.org-Readme | `check-zip` Readme-Regeln; Inhalt in R1-24 korrigiert (Datenschutz, externe Dienste) |
| i18n | `.pot` im Paket und aktuell (J-03); Textdomain-Lint 0; deutsche Übersetzung siehe A-2 |
| Demo ohne Fremdinhalte | R1 enthält keine Demo-Inhalte (E-102); Negativliste/Altnamen-Prüfung |
| Support-Runbook | `docs/user/troubleshooting.md` (R1-24) |
| Deaktivierung | Artefakttest (keine Tabellen-/Datenänderung, Reaktivierung) |

## 4. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | WordPress.org **Plugin Check** (`wp plugin check`) | in dieser Umgebung nicht installierbar (kein Netz für das Plugin); eigene Prüfungen decken Readme/Lizenz/Lint/Free-Guard ab. Plugin-Check-Lauf im CI-Job nachrüsten, sobald die Version gepinnt ist |
| A-2 | `languages/course-captain-de_DE{,_formal}.{mo,json}` (Rückfall bis zum Sprachpaket, E-208) | folgt mit R1-19 (Mehrsprachigkeit) |
| A-3 | `SECURITY.md` (RC-070) | braucht die Sicherheitsadresse des Auftraggebers |
| A-4 | Reproduzierbarer Zweitbuild im frischen Container, SBOM/Attestierung (J-18) | Reproduzierbarkeit lokal belegt; SBOM/Attestierung mit dem Release-Workflow |
| A-5 | Voraussetzung R1-30 (Konfliktmatrix) | Koexistenz-/Konfliktläufe werden in R1-30 nachgewiesen und hier nur referenziert (R0-35 §3.4) |
| A-6 | Version `1.0.0-dev` | Versionsgleichheit geprüft; Umstellung auf `1.0.0` erst beim Release (Stable-tag-Regel) |

Review durch zweite Person vor Merge (AG-05).
