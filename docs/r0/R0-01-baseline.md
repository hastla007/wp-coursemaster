# R0-01 · Repository- und Versionsbasis

**Status:** erledigt (reine Bestandsaufnahme, keine Entscheidung) · **Stand:** 16.11.2026
**Bezug:** TODO R0-01, Konzept Kap. 2, 39, 46.2, 47.1 (Schritt 1), 48

## 1. Fixierte Versionsbasis

| Repository | Rolle | Branch | Commit | Commit-Datum |
|---|---|---|---|---|
| `hastla007/learnboating` | Referenz, nur lesen | Standardbranch (`main`) | `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` | 15.11.2026 10:37 +0100 |
| `hastla007/wp-coursemaster` | Course Captain (Planung) | `main` → Arbeitsbranch | `5af78c390693f9d8b38407dea8d14517ee5cf422` | 15.11.2026 23:58 +0100 |

**Course-Captain-Stand:** Nur Dokumente (`README.md`, `konzept.md`, `TODO-WETTBEWERBSANALYSE.md`), **kein Code**. 48 Commits, alle `docs:`.

**Erfassung:** Shallow-Clone (`--depth=1`) des learnboating-Standardbranches, nur lesend. learnboating wurde nicht verändert. Alle R0-Inventurdokumente (`docs/r0/R0-02-inventar/`) beziehen sich auf genau diesen Commit. Wenn sich learnboating ändert, muss das Delta ab diesem Commit neu geprüft werden.

**Plugin-Kopf der Referenz** (`bootspruefung-learning/bootspruefung-learning.php`): Version 0.14.0, `Requires at least: 7.0`, `Requires PHP: 8.1`, `WC requires at least: 11.2`, Textdomain `bootspruefung-learning`, PSR-4 `Bootspruefung\Learning\` → `src/`. Diese Werte sind **keine** automatisch beschlossenen Course-Captain-Zielwerte (→ R0-20).

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
| Plugin `bootspruefung-learning` | keine `License:`-Zeile; `composer.json`: `proprietary` | Gleicher Urheber (`hastla007`). Für eine Portierung in ein GPL-Plugin auf WordPress.org braucht es eine **ausdrückliche Lizenzentscheidung des Urhebers** → ENTSCHEIDUNG E-01 |
| Connectors Affiliate/Voucher | `proprietary` | wie oben; betrifft nur R3 |
| Theme `kurswasser` | GPL-2.0-or-later | wiederverwendbar mit GPL-Hinweis; Theme ist kein Bestandteil des Plugins |
| Gerätesimulation (`assets/device-sim/`) | **ungeklärt** (Quelle `boatingschool@136a243` ohne LICENSE) | ohnehin `exclude_domain_specific`; keine Übernahme |
| Inhalte (Fragen, Medien, Prüfungsregeln) | **ungeklärt** | keine Übernahme; Course Captain liefert keine Inhalte aus |
| Kursfotos (KI-erzeugt), Produktbilder, Manrope (OFL), DE-Karten (gemeinfrei) | wie in CREDITS | nicht benötigt; ggf. nur Manrope (OFL-1.1 ist GPL-kompatibel) |
| Drittbibliotheken | keine gebündelt (kein `vendor/` im ZIP) | keine Lizenzlast aus Abhängigkeiten |

## 4. Nachweis

- Kommando: `git clone --depth=1 https://github.com/hastla007/learnboating.git` und dann `git log -1 --format='%H %cd'`
- Zählung: `find <pfad> -name '*.php' | xargs cat | wc -l` je Ordner
- Anforderungsbezug: Konzept 46.2 („Vollständige LearnBoating-Codeparität“), 47.1 Schritt 1, LB-AUDIT-001
