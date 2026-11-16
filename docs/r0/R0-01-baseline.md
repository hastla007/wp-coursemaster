# R0-01 · Repository- und Versionsbasis

**Status:** Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet · **Stand:** 16.11.2026
**Bezug:** TODO R0-01, Konzept Kap. 2, 39, 46.2, 47.1 (Schritt 1), 48; `ENTSCHEIDUNGEN.md` (Beschlussprotokoll)

## Beschlussstand

| Beschluss | Wirkung in diesem Dokument |
|---|---|
| **E-01 – Nein, Clean Room** | **Kein Code aus learnboating wird übernommen** (Plugin, Connectors, Theme, Tools, Schemas, Tests). Course Captain implementiert nur das **Verhalten** nach Spezifikation neu. Die Freigabezeile für eine GPL-Übernahme entfällt (§3) |
| E-02 | Golden-Master-Tests nur mit synthetischen, neutralen Testpaketen, keine LB-Inhalte |
| E-03 | Ein Import alter LB-Pakete höchstens als separates Migrationswerkzeug, nie im Core |
| E-56 | Referenzlauf des LB-Plugins für Fälle mit Rechenlogik: nur Ausführen, kein Code |
| **E-12** | Die Werte des LB-Plugin-Kopfs (§1) sind **kein** Startwert; die Plattformmatrix wird eigenständig in R0-20 festgelegt |
| E-195 | PHP-Untergrenze für Course Captain 8.3 (LB: 8.1) |
| E-194 | Der fixierte Commit `9f37c77` bleibt Basis der Verhaltensinventur **und** des nightly Koexistenztests in der CI (lesender Deploy-Key; LB wird nur installiert, nie verändert oder weitergegeben) |
| E-188 | Das Course-Captain-Repository bleibt privat bis zur ersten WordPress.org-Veröffentlichung |

## 1. Fixierte Versionsbasis

| Repository | Rolle | Branch | Commit | Commit-Datum |
|---|---|---|---|---|
| `hastla007/learnboating` | Referenz, nur lesen | Standardbranch (`main`) | `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` | 15.11.2026 10:37 +0100 |
| `hastla007/wp-coursemaster` | Course Captain (Planung) | `main` → Arbeitsbranch | `5af78c390693f9d8b38407dea8d14517ee5cf422` | 15.11.2026 23:58 +0100 |

**Course-Captain-Stand:** Nur Dokumente (`README.md`, `konzept.md`, `TODO-WETTBEWERBSANALYSE.md`), **kein Code**. 48 Commits, alle `docs:`.

**Erfassung:** Shallow-Clone (`--depth=1`) des learnboating-Standardbranches, nur lesend. learnboating wurde nicht verändert. Alle R0-Inventurdokumente (`docs/r0/R0-02-inventar/`) beziehen sich auf genau diesen Commit; sie beschreiben Verhalten, nicht zu übernehmenden Code (E-01). Derselbe Commit ist Ziel des nightly Koexistenztests (E-194). Wenn sich learnboating ändert, muss das Delta ab diesem Commit neu geprüft werden.

**Plugin-Kopf der Referenz** (`bootspruefung-learning/bootspruefung-learning.php`): Version 0.14.0, `Requires at least: 7.0`, `Requires PHP: 8.1`, `WC requires at least: 11.2`, Textdomain `bootspruefung-learning`, PSR-4 `Bootspruefung\Learning\` → `src/`. Diese Werte sind **keine** Course-Captain-Zielwerte und auch kein Startwert: Die Plattformmatrix legt R0-20 eigenständig fest (E-12), die PHP-Untergrenze ist 8.3 (E-195).

## 2. Verzeichnis- und Modulkarte (Referenz)

| Pfad | Dateien | PHP-Zeilen | Inhalt | Inventur-Teil |
|---|---|---|---|---|
| `bootspruefung-learning/src/Learning` | 4 | 1 829 | Smarttrainer, Zugriff, Fragenauslieferung | A |
| `bootspruefung-learning/src/Exams` | 1 | 1 086 | Prüfungssimulation | A |
| `bootspruefung-learning/src/Trainers` | 8 | 2 437 | Spezialtrainer (überwiegend fachspezifisch) | A |
| `bootspruefung-learning/src/Gate` | 5 | 1 096 | Freischalt-/Zugangstor | A |
| `bootspruefung-learning/src/ExamGuide` | 2 | 365 | Prüfungsanmeldungs-Infos | A |
| `bootspruefung-learning/src/Tips`, `src/Faq` | 19 | 3 220 | Lerntipps, FAQ | A |
| `bootspruefung-learning/src/Commerce` (+ `WooCommerce/`) | 45 | 12 275 | Entitlements, Gruppen, Woo-Integration, Rechnungen | B |
| `learnboating-affiliate-connector/` | 7 | 583 | Add-on Partnerprogramm | B |
| `learnboating-voucher-connector/` | 9 | 1 517 | Add-on Kursgutschein | B |
| `bootspruefung-learning/src/Content` | 11 | 3 986 | Import, Medien, Content-Repository | C |
| `bootspruefung-learning/src/Infrastructure` | 13 | 2 985 | Locks, Request-Memo, Migrationen | C |
| `src/Plugin.php`, `src/functions.php`, `uninstall.php`, `dropins/` | 5 | 631+ | Bootstrap, Lifecycle | C |
| `src/{Settings,Support,Audit,Security,Privacy,Cli,Extensions}` | 19 | 4 433 | Querschnitt | C |
| `bootspruefung-learning/schema/v1` | 17 | – | JSON-Schemas Content-Paket | C/E |
| `bootspruefung-learning/src/Admin` | 19 | 6 711 | Admin-Screens | D |
| `bootspruefung-learning/src/Frontend`, `src/Rest` | 12 | 2 186 | Lernerscreens, REST | D |
| `bootspruefung-learning/src/Mobile` | 31 | 7 051 | Mobile-API | D |
| `bootspruefung-learning/templates`, `assets` | 2 / ~30 JS+CSS | – | Templates, Frontend-JS, Gerätesimulation | D |
| `bootspruefung-learning/tests` | 41 | – | 22 Integration, 13 E2E, PHPStan | A–D (fachlich), E (Infrastruktur) |
| `themes/kurswasser` | 216 | 10 106 | Theme (GPL-2.0+) | E |
| `tools/` | ~50 | ~4 400 | Content-Build, Release, i18n, QA | E |
| `content-source/boatingschool` | 630 | 21 673 | Laravel-Snapshot der Inhalte (fachspezifisch) | E |
| `schema/`, `staging/`, `docs/` | 63 | – | Schemas, Staging, Betriebsdoku (31 MD + mobile-api) | E |

**Summe:** 559 PHP-Dateien mit rund 103 000 Zeilen. Davon gehören rund 56 000 zum Plugin, zu den Connectors und zum Theme. Die übrigen Zeilen sind Laravel-Quellen und Werkzeuge.

## 3. Quellrechte (Befund, keine Rechtsberatung)

Quellen: `CREDITS.md`, `docs/lizenz.md` (dort To-do T93, „Lizenz legt der Betreiber fest“).

| Bestandteil | Lizenzangabe heute | Folge für Course Captain |
|---|---|---|
| Plugin `bootspruefung-learning` | keine `License:`-Zeile; `composer.json`: `proprietary` | Gleicher Urheber (`hastla007`). **Beschluss E-01: keine Freigabe, Clean Room.** Kein Code wird portiert; Course Captain baut das Verhalten nach Spezifikation neu |
| Connectors Affiliate/Voucher | `proprietary` | wie oben (E-01, keine Übernahme); betrifft nur R3 |
| Theme `kurswasser` | GPL-2.0-or-later | Lizenzrechtlich wiederverwendbar, wird nach E-01 aber **nicht** übernommen; Theme ist ohnehin kein Bestandteil des Plugins |
| Gerätesimulation (`assets/device-sim/`) | **ungeklärt** (Quelle `boatingschool@136a243` ohne LICENSE) | ohnehin `exclude_domain_specific`; keine Übernahme |
| Inhalte (Fragen, Medien, Prüfungsregeln) | **ungeklärt** | keine Übernahme; Course Captain liefert keine Inhalte aus |
| Kursfotos (KI-erzeugt), Produktbilder, Manrope (OFL), DE-Karten (gemeinfrei) | wie in CREDITS | nicht benötigt; Manrope (OFL-1.1, GPL-kompatibel) käme höchstens aus der Originalquelle, nicht aus dem LB-Repository (E-01) |
| Drittbibliotheken | keine gebündelt (kein `vendor/` im ZIP) | keine Lizenzlast aus Abhängigkeiten |
| LB-Werkzeuge (`tools/`, z. B. `build-zips.sh`, `check-domains.php`, `make-pot.sh`), Schemas, Tests | wie Plugin (`proprietary`) | **nicht kopiert** (E-01); Course Captain schreibt sie nach Verhaltensspezifikation neu (R0-15 §3.8, R0-18) |

**Beschluss (E-01, Auftraggeber, 16.11.2026):** Nein – Clean Room. Kein Code aus learnboating wird übernommen; nur Verhalten wird nach Spezifikation neu implementiert. „reuse/abstract“ in der Inventur bedeutet ab jetzt „Verhalten nachbauen“, nicht „Code kopieren“ (siehe `R0-02-inventar/README.md`). Folgebeschlüsse mit Clean-Room-Bezug: E-22 (Zahl-mit-Toleranz-Fragetyp ohne Code aus NavigationCheck), E-33 (Shop-Rechtspflichten als Neuimplementierung nach Verhalten), E-56 (Referenzlauf nur Ausführen).

## 4. Nachweis

- Kommando: `git clone --depth=1 https://github.com/hastla007/learnboating.git` und dann `git log -1 --format='%H %cd'`
- Zählung: `find <pfad> -name '*.php' | xargs cat | wc -l` je Ordner
- Anforderungsbezug: Konzept 46.2 („Vollständige LearnBoating-Codeparität“; nach E-01 als **Verhaltensparität** zu lesen), 47.1 Schritt 1, LB-AUDIT-001

## 5. Offene Punkte nach Einarbeitung

- Konzept 46.2 spricht von „Codeparität“; nach E-01 gilt Verhaltensparität. Die redaktionelle Anpassung des Konzepts ist nicht Teil dieses Dokuments.
- Wie der Clean-Room-Prozess organisatorisch abgesichert wird (wer liest LB-Code, wer implementiert), ist im Beschluss nicht festgelegt.
