# R0-35 · WordPress.org- und Distribution-Readiness

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Es gibt keinen Code, keine `readme.txt`, keine Assets, keinen Build und keine Einreichung bei WordPress.org. Diese Datei legt fest, **was** vor einer Veröffentlichung nachgewiesen sein muss. Die Prüfung selbst ist **R1-39**.
**Stand:** 16.11.2026
**Bezug:** TODO R0-35 (Voraussetzung R0-18, R0-24; DoD: „Plugin-Slug, Readme, Lizenz/Assets, Übersetzungen, Support, Paketbuild, Free/Pro-ZIPs und Update/Restore als Releasecheckliste“), Folgetask **R1-39** (Voraussetzung R1-23, R1-24, R1-30, R0-35; DoD: „Free-ZIP Clean-Install, Upgrade, WordPress.org-Readme, i18n, Demo ohne Fremdinhalte, Support-Runbook und Deaktivierung geprüft“), R0-39, R3-18. Konzept Kap. 1 (Free für WordPress.org, keine Lizenzprüfung), Kap. 6 (Slugs, Pro), Kap. 12 Nr. 9 („Releasefähig“), Kap. 23.2 (Release-Gates, insbesondere Nr. 6 und 8), Kap. 23.3 (Kennzahlen nach dem Launch), **Kap. 44** (Muster Form/Workflow „Setup und WordPress.org-Release“: Installations-, Demo-, Readme-, Übersetzungs-, Lizenz-, Update-/Rollback- und Support-Gates; keine fremden Vorlagen), Kap. 45 (License Captain: „reale Release-Evidenz statt Checkbox ohne Test“), Kap. 46.2 (Zeile „Release-/Support-/Wiederherstellungsgates“ → R1-GATE).
**R0-Dokumente:** R0-01 §3 (Quellrechte), R0-02/E §4.1, §4.2, §4.5, §6 (Rechte, WordPress.org-Anforderungen, INV-E-043…047, -090, -096, -116), R0-11 N-08/ER-5, R0-12 Gate C-15, R0-13 (Supportstufen, Diagnosepaket), R0-14 FE-07, R0-15 §3.1, I18N-R-10, I18N-001…014 (E-121), R0-16 §2.1, §2.5, §3.3, §3.4, REC-001…025, R0-17 §4.3 (SEC-045), PRIV-017, R0-18 §2.4, §2.5, §3.1…3.6, R0-20 §1 (Kopfzeilen), D-20-03, OP-20-1, R0-24 §4.1 (DoR), §4.2/4.3 (Zeilen R1-39), R0-36 A8, L1…L5, L12.
**Beschlüsse (16.11.2026):** **E-01** (Clean Room: kein LB-Code wird übernommen), **E-09** (Free über WordPress.org, Pro über License Captain, keine GitHub-Abfrage), **E-13** (SVN-Deploy nur bei grüner CI, Tag auf `main`, manuelle Freigabe), **E-100** (keine gesperrten Pro-Schalter, höchstens Seite „Erweiterungen“), **E-121** (Quellsprache en_US, Deutsch erste Übersetzung), **E-188** (Repository öffentlich spätestens zur ersten WordPress.org-Veröffentlichung), **E-189** (Attestation + Sigstore), **E-193** (Freigabe durch den Auftraggeber, keine Selbstfreigabe). Außerdem genutzt: E-42, E-45, E-89, E-90, E-102, E-142, E-172, E-174, E-184, E-185, E-190, E-195 (PHP 8.3 ersetzt E-57).
**Quellen (nur gelesen, nicht verändert):** LearnBoating `hastla007/learnboating` @ `9f37c77`: `bootspruefung-learning/readme.txt` (93 Z.), `CREDITS.md` (47 Z.), `docs/lizenz.md` (87 Z.), `docs/release-regel.md` (90 Z.), `tools/release/{build-zips.sh, upgrade-check.php}`. Suite: `hastla007/wp-license` @ `c269e07` `docs/RELEASE-READINESS.md` (66 Z.).
## Beschlussstand

Quelle: `ENTSCHEIDUNGEN.md`, Beschlussprotokoll und „Von Claude entschieden“ (16.11.2026). Alle Punkte aus §6 sind entschieden.

| Beschluss | Wirkung in diesem Dokument |
|---|---|
| **E-01 Clean Room** | Kein LB-Code im Paket; kein Herkunftsnachweis für LB-Code in `CREDITS.md`; Guard auf Legacy-Namen ohne Ausnahme. Werkzeuge (`build-zips.sh`, `make-pot`, `upgrade-check`) werden nach Verhalten neu geschrieben (R0-18) |
| **E-45, E-45a** | Externe Medien werden standardmäßig **ohne Klick geladen** (`external_media_mode` = „immer laden“, umschaltbar auf „Klick zum Laden“). Die readme muss die externen Dienste offenlegen (§1.4 „External services“); Hinweis im Setup-Assistenten |
| **E-188** | Repository privat bis zur ersten WordPress.org-Veröffentlichung, dann öffentlich |
| E-193, E-204 | Deploy-Freigabe durch den Auftraggeber; er übernimmt vorerst auch die Reviewer-Rollen; Autor ≠ Reviewer bleibt Pflicht |
| E-205 (D35-1) | Slug vor R1-Start prüfen und früh einreichen; Ersatz `course-captain-lms` |
| **E-206** (D35-2) | WordPress.org-Konto mit 2FA legt der **Auftraggeber** an |
| **E-207** (D35-3) | Präfix **`ccap_`** für CPTs, Caps, Rollen, Cron, Meta **und Tabellen**; vor R1-01 gegen die aktuelle Richtlinie prüfen |
| E-208 (D35-4) | Sprachpaket über translate.wordpress.org, bis zur Freigabe zusätzlich `.mo`/JSON im ZIP |
| E-209 (D35-5) | Erste öffentliche Version 1.0.0 |
| E-210 (D35-6) | Keine Telemetrie |
| **E-211** (D35-7) | Sicherheitsmeldungen über eine **Adresse des Auftraggebers**; Erstreaktion ≤ 72 h |
| **E-212** (D35-8) | **Auftraggeber betreut das wp.org-Forum**; Pro-Support über eigenen Kanal |
| E-213 (D35-9) | Pro unter GPL-2.0-or-later; Lizenzschlüssel regelt Updates und Support |
| E-214 (D35-10) | Live Preview erst ab R2-03 |
| **E-273** | Pro wird in `captain-pro/addons/course-captain-pro` gebaut; Zugriff auf das private Repository `captain-pro` nötig |
| E-195, E-12 | `Requires PHP: 8.3`; Kopfwerte aus der eigenständigen Matrix R0-20 |
| E-10 | Website-Härtung ist Teil des Free-Kerns; Sicherheitsmeldungen dazu sind im Umfang (§3.6) |

**Wissensvorbehalt:** Die Angaben zu WordPress.org (Richtliniennummern, Readme-Grenzen, Asset-Größen, Sprachpaket-Schwelle, 2FA-Pflicht, Einreichungsgrenzen) beruhen auf dem Modellwissen des Verfassers. Ein Abruf von `developer.wordpress.org` war in dieser Sitzung nicht möglich (Proxy lehnt den Host ab). Solche Stellen sind mit **(MW)** markiert und vor R1-39 gegen die dann gültige Fassung zu prüfen (RC-001).

---

## 0. Kurzfassung

- **Free** (`course-captain`) geht ausschließlich über WordPress.org (E-09): GPL-2.0-or-later, kein LB-Code (E-01), kein Updater, kein Lizenzcode, kein Tracking (E-210), Fremdhost-Anfragen nur durch von Autoren eingebettete Medien (Default ohne Klick, E-45a, in der readme offengelegt), keine gesperrten Pro-Schalter (E-100). Der CI-Guard aus R0-18 §2.5 und die Negativliste §3.2 erzwingen das am ZIP.
- **Pro** (`course-captain-pro`) geht nie zu WordPress.org, sondern über den License-Captain-Client mit `Update URI` (R0-36 L3) und signierten ZIPs (E-190). Pro wird in `captain-pro/addons/course-captain-pro` gebaut (E-273) und ist extern blockiert: License-Captain-Gates alle offen (R0-36 L12), Produktanlage E-42 offen, `captain-pro` ungeprüft (Zugriff nötig).
- **Slug** `course-captain` ist ungeprüft (OP-20-1). Der Slug wird erst mit der Freigabe einer vollständigen Einreichung reserviert (MW). Beschluss E-205: vor R1-Start prüfen und früh einreichen, Ersatz `course-captain-lms`.
- **Neu gegenüber LB:** `readme.txt` mit Lizenzkopf und Abschnitt „External services/Privacy“, `LICENSE`, SVN-Assets, translate.wordpress.org statt mitgelieferter deutscher Quelle, `SECURITY.md`, Forum-Regeln, Plugin Check als Gate. LB hat bewusst `Update URI: false` und eine GitHub-Versionsabfrage (`readme.txt:58-61`, `release-regel.md` T117). Beides entfällt in Free.
- **Release-Checkliste** §5: **RC-001…RC-078** (Free, Ziel R1-39) und **RC-090…RC-099** (Pro, Ziel R3-18). Jede Zeile nennt eine Nachweisart; „abgehakt“ heißt Protokoll mit Commit, Lauf-ID bzw. Datum und Prüfer (Muster License Captain `RELEASE-READINESS.md`: „A box is ticked only when the item was verified and the evidence is recorded“).
- **Zehn Entscheidungen** D35-1…D35-10 (§6) sind als E-205…E-214 beschlossen, darunter das Präfix `ccap_` (E-207) und die GPL-Lizenz von Pro (E-213).

---

## 1. WordPress.org-Readiness (Free)

### 1.1 Plugin-Richtlinien und Umsetzung in Course Captain

Nummern nach den „Detailed Plugin Guidelines“ (MW, 18 Richtlinien).

| Nr. | Richtlinie (Kurzform) | Umsetzung in Course Captain | Nachweis |
|---|---|---|---|
| 1 | GPL-kompatibel, alles im Paket | Code, Assets, Schriften und Bibliotheken GPL-2.0-or-later oder kompatibel (Allowlist R0-18 §2.4). Kein LB-Code (E-01, Clean Room) | RC-010…RC-014 |
| 2 | Entwickler verantwortet alle Dateien | jede gebündelte Datei in `CREDITS.md` und SBOM (R0-18 §3.4) | RC-013 |
| 3 | Stabile Version im Verzeichnis | `Stable tag` = `tags/<version>`; GitHub-Release mit demselben Inhalt (`wporg-verify`, R0-18 §3.6 Schritt 8) | RC-052 |
| 4 | Lesbarer Code | keine Obfuskation; minifizierte Bundles nur mit lesbarer Quelle im Paket oder öffentlichem Repository (E-188), Link in `readme.txt` | RC-024 |
| 5 | Kein Trialware | keine Funktionssperre, keine Zeit- oder Mengengrenze, kein Lizenzschlüssel in Free (Konzept Kap. 1; Guard R0-18 §2.5) | RC-020, RC-021 |
| 6 | SaaS erlaubt, wenn echter Dienst | R1 Free nutzt keinen eigenen Dienst | RC-027 |
| 7 | Kein Tracking ohne Einwilligung | keine Telemetrie, keine Nutzungsstatistik, keine Versionsabfrage (LB-Opt-in entfällt; E-210). Kennzahlen aus Kap. 23.3 ohne Telemetrie | RC-025 |
| 8 | Kein ausführbarer Fremdcode, keine Fremd-Updates | kein `Update URI`, kein eigener Updater, keine CDN-Skripte/-Schriften (PRIV-017, FE-07), keine Installation anderer Plugins ohne Nutzeraktion (Partnererkennung nur lesend, E-185) | RC-020, RC-026 |
| 9 | Nichts Illegales oder Irreführendes | Wortliste SEC-045 („betrugssicher“, „DSGVO-konform“ usw.) über `.pot`, `readme.txt`, Doku | RC-036 |
| 10 | Keine Links/Credits ohne Zustimmung | kein „Powered by“ im Frontend, auf Zertifikaten oder der Verifikationsseite; höchstens als Opt-in-Einstellung | RC-028 |
| 11 | Admin nicht kapern | Hinweise nur auf relevanten Screens und ausblendbar; Setup-Assistent überspringbar; keine Weiterleitung nach Sammel-, Netzwerk- oder CLI-Aktivierung; Pro nur auf der Seite „Erweiterungen“ (E-100, N-08) | RC-029, RC-021 |
| 12 | Readme ohne Spam | höchstens 5 Tags (MW), keine Konkurrenznamen als Tags oder Keyword-Listen, sachliche Beschreibung | RC-031 |
| 13 | WordPress-Bibliotheken verwenden | React, jQuery, `wp-*`-Pakete als Abhängigkeit der Skript-Handles, keine gebündelten Kopien (`wp-scripts`-Externals) | RC-023 |
| 14 | SVN nur für Releases | SVN-Commits nur aus `release.yml` Schritt 7 (R0-18 §3.6), keine Entwicklungs-Commits | RC-050 |
| 15 | Version bei jeder Änderung erhöhen | Versionsgleichheit und „geändert, aber Version gleich“ (R0-18 §3.1) | RC-041 |
| 16 | Vollständiges Plugin bei Einreichung | Einreichung früh (E-205), aber nur als vollständiges, funktionsfähiges Plugin (siehe Offene Punkte) | RC-005 |
| 17 | Marken respektieren | Name und Slug ohne fremde Marke („WordPress“, „WP“, „WooCommerce“, LMS-Produktnamen); Abgrenzungstexte ohne Herabsetzung | RC-002 |
| 18 | WordPress.org darf ändern, schließen, Sicherheitsupdates erzwingen | Sicherheitsprozess §3.6 rechnet damit | RC-066 |

### 1.2 Sicherheitsanforderungen des Plugin-Reviews und Plugin Check

Das Review prüft häufig dieselben Punkte (MW). Sie sind bereits in R0-17/R0-18 verankert; hier nur die Zuordnung, damit R1-39 sie gezielt nachweist.

| Anforderung | Umsetzung / Prüfung |
|---|---|
| Ausgabe spät escapen (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`) | PHPCS `WordPress.Security.EscapeOutput` blockierend (R0-18 J-01), Psalm-Taint |
| Eingaben bereinigen und validieren | `WordPress.Security.ValidatedSanitizedInput`, typisierte Settings (Kap. 43) |
| Nonce **und** Capability für jede schreibende Aktion; REST mit echtem `permission_callback` | RBAC-/IDOR-Matrix R0-07, Routen-Snapshot (R0-18 C-7); `__return_true` nur für öffentliche Leserouten mit Begründung |
| SQL nur mit `$wpdb->prepare()` | PHPCS `WordPress.DB.PreparedSQL`, Semgrep |
| Kein Direktaufruf von PHP-Dateien | eigenes `check-abspath` (Verhalten wie LB, neu geschrieben, E-01; INV-E-046) |
| Kein `eval`, keine Kodierungs-Obfuskation, kein `wp-load.php`-Include, keine Heredoc-Ausgabe ohne Escaping | Semgrep-Regeln, Negativliste |
| Netzwerk nur über `wp_remote_*` | Semgrep; R1 Free macht ohnehin keine ausgehenden Aufrufe außer WordPress-Kern |
| Schreiben nur in `uploads/course-captain/` bzw. den privaten Ordner, nie in den Plugin-Ordner (wird beim Update ersetzt) | Integrationstest; R0-16 §2.5 |
| Eindeutige Präfixe für alles im globalen Namensraum | `ccap_` (E-207) für CPTs, Caps, Rollen, Cron, Meta, Tabellen; `course_captain_`/`ccap-` (D-20-03) |
| Skripte und Styles nur über `wp_enqueue_*` (Inline nur `wp_add_inline_script`) | PHPCS `WordPress.WP.EnqueuedResources` |

**Plugin Check** (`wp plugin check`, Kategorien u. a. `plugin_repo`, `security`, `performance`, `accessibility`, `general`; WordPress.org führt ihn bei neuen Einreichungen selbst aus, MW): läuft in J-17 auf dem **entpackten ZIP**, nicht auf dem Repository. **Gate:** 0 Fehler. Warnungen nur mit Eintrag in `tools/qa/plugin-check-allow.txt` (Prüf-ID, Datei, Begründung, CODEOWNERS-Review). Plugin-Check-Version wird gepinnt und je Minor aktualisiert.

### 1.3 Slug, Name und Konto

| Punkt | Festlegung | Status |
|---|---|---|
| Slug / Ordner / Textdomain | `course-captain` (Konzept Kap. 6); Textdomain muss dem Slug entsprechen, sonst greift translate.wordpress.org nicht (MW) | **Verfügbarkeit offen (OP-20-1)** |
| Anzeigename | „Course Captain“ im Plugin-Kopf und in `readme.txt` (`=== Course Captain ===`). WordPress.org leitet den Slug bei der Einreichung aus dem Namen ab; ein vergebener oder geschützter Name führt zu einem anderen Slug (MW) | offen |
| Prüfung | Ohne Einreichung: `https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=course-captain` (Antwort „Plugin not found“ heißt nur „nicht veröffentlicht“, nicht „frei“: geschlossene und eingereichte Plugins belegen den Slug ebenfalls, MW) und Suche im Verzeichnis nach ähnlichen Namen. Sicher ist der Slug erst mit der Freigabe der Einreichung | RC-001, RC-003 |
| Fallback | Wird der Slug nicht vergeben, ändern sich Ordner, Textdomain, `.pot`, CLI-Präfix, Uploads-Ordner und Guard-Muster. Beschlossen: Ersatz `course-captain-lms`, Prüfung vor R1-Start (E-205) | E-205 |
| Konto | Plugin-Eigentümer und Committer sind WordPress.org-Konten. Committer brauchen Zwei-Faktor-Anmeldung und ein separates SVN-Passwort (MW, seit 2024). Das SVN-Passwort liegt nur im CI-Environment `wordpress-org` (R0-18 §3.6). Beschlossen: Der **Auftraggeber** legt das Konto mit 2FA an (E-206) | E-206 |
| Pro | `course-captain-pro` wird nicht bei WordPress.org eingereicht. Der Kopf `Update URI` verhindert, dass WordPress ein gleichnamiges Verzeichnis-Plugin als Update anbietet (Muster `wp-license` `RELEASE-READINESS.md` §1, R0-36 L3) | RC-091 |

### 1.4 `readme.txt` (Quelle Englisch, E-121)

**Kopf** (Werte aus R0-20 §1; LB-Werte aus `readme.txt:1-7` sind keine Zielwerte):

```
=== Course Captain ===
Contributors: <wordpress.org-Benutzername des Auftraggebers, E-206>
Tags: lms, courses, e-learning, quiz, education
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: <X.Y.Z>
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

<Kurzbeschreibung, höchstens 150 Zeichen, ohne Markup (MW)>
```

Regeln: kein `WC requires at least`/`WC tested up to` und kein `Requires Plugins` (R0-20 §1); `Tested up to` nur Hauptversion (`7.1`), Anhebung nach R0-20 §1 (spätestens 30 Tage nach WP-GA, mit PATCH); `Stable tag` nie `trunk`.

**Abschnitte:**

| Abschnitt | Inhalt | Regel |
|---|---|---|
| `== Description ==` | Free-Funktionsumfang nach R0-11 (Free-Spalte), neutrale Positionierung, Hinweis „ohne WooCommerce nutzbar“ | keine gesperrten Pro-Funktionen bewerben; ein sachlicher Satz zu Erweiterungen ist zulässig (E-100); Wortliste SEC-045; keine Konkurrenzvergleiche mit Produktnamen in Tags |
| `== Installation ==` | Verzeichnis-Installation, ZIP-Upload, Mindestversionen, Systemstatus prüfen, Setup-Assistent (leeres Kursgerüst, E-102); Multisite: nur je Site, Netzwerkaktivierung blockiert (E-07, E-49) | Schrittfolge wie LB `readme.txt:41-61`, ohne GitHub-Abfrage und ohne `Update URI: false` |
| `== Frequently Asked Questions ==` | `= Frage =` je Eintrag: Datenablage, Deinstallation (E-89), Backup/Restore (R0-16), WooCommerce/Pro, Übersetzungen, Support-Kanal, Sicherheitsmeldung | nur Fragen, die im Code belegt sind |
| `== Screenshots ==` | nummerierte Liste; Nummer n ↔ `screenshot-n.png` in SVN `assets/` | Bildunterschriften beschreiben den Inhalt (auch für Screenreader) |
| `== Changelog ==` | `= X.Y.Z – JJJJ-MM-TT =`; Pflichtmarker `Schema`, `Backfill`, `Destruktiv (Contract) – Backup vorher`, `Sicherheitsupdate:` (R0-16 §3.3 Nr. 2, R0-18 §3.1) | nur die letzten Versionen; vollständig in `CHANGELOG.md` im Plugin-Ordner (Readme klein halten, MW) |
| `== Upgrade Notice ==` | `= X.Y.Z =` je Version mit Schema-/Backup-/Reihenfolge-Hinweis (U-1, R0-16 §2.1; Muster LB `readme.txt:63-69`) | höchstens 300 Zeichen je Eintrag (MW), Pflicht bei Schema- und Contract-Schritten |
| `== Privacy ==` (eigener Abschnitt) | welche Personendaten das Plugin speichert (Lernstände, Versuche, Grants, Audit), Aufbewahrung (E-44, E-51), Export/Löschung über die WordPress-Datenschutzwerkzeuge, Datenschutz-Textbaustein über `wp_add_privacy_policy_content` (E-04), **keine Telemetrie** | Gegencheck mit R0-17 §2 |
| `== External services ==` (eigener Abschnitt, **Pflicht nach E-45a**) | jeder Fremddienst mit Zweck, Zeitpunkt, übertragenen Daten, Link zu Nutzungsbedingungen und Datenschutz. R1 Free: von Autoren eingebettete Videos (YouTube/Vimeo/Fremdhost) werden **standardmäßig ohne Klick geladen** (`external_media_mode` = „immer laden“, E-45/E-45a); der Abschnitt nennt diese Dienste, die beim Laden übertragenen Daten (u. a. IP-Adresse) und die Umschaltung auf „Klick zum Laden“. Sonst: „Das Plugin ruft keine externen Dienste auf.“ | WordPress.org-Review verlangt diese Offenlegung (MW); Test PRIV-017/FE-07 |
| `== Development ==` (optional) | Link zum öffentlichen Repository (E-188: öffentlich ab der ersten WordPress.org-Veröffentlichung) mit lesbarer Quelle und Build-Anleitung | Richtlinie 4 |

**Validierung:** WordPress.org-Readme-Validator bzw. Plugin-Check-Prüfung `plugin_readme` in J-17; zusätzlich eigenes Skript: Kopfwerte = Plugin-Kopf, Tag-Anzahl ≤ 5, Kurzbeschreibung ≤ 150 Zeichen, Upgrade-Notice ≤ 300 Zeichen, Screenshot-Liste = Anzahl `screenshot-*` in `.wordpress-org/`, Changelog-Eintrag der Version vorhanden.

### 1.5 Assets (SVN `assets/`, Quelle `.wordpress-org/` im Repository)

| Datei | Maß | Format | Pflicht | Hinweis |
|---|---|---|---|---|
| `icon-128x128.png` | 128 × 128 | PNG oder JPG (GIF möglich) | ja | |
| `icon-256x256.png` | 256 × 256 | PNG oder JPG | ja | hochauflösend |
| `icon.svg` | – | SVG | nein | nur zusätzlich zu PNG (Rückfall für ältere Anzeigen, MW) |
| `banner-772x250.png` | 772 × 250 | PNG oder JPG | ja | Text nicht in die unteren Ecken (Name wird überlagert, MW) |
| `banner-1544x500.png` | 1544 × 500 | PNG oder JPG | ja | hochauflösend |
| `banner-772x250-rtl.png` | 772 × 250 | PNG oder JPG | nein | RTL-Variante (D-15-11) |
| `screenshot-1.png` … `screenshot-n.png` | frei, Empfehlung ≥ 1280 px breit, einheitliches Seitenverhältnis | PNG oder JPG | ja (≥ 3: Kurseditor, Lernbereich mobil, Prüfung/Ergebnis) | englische Oberfläche; neutrale Demo, keine LB-/Fachinhalte, keine Personendaten |
| `screenshot-n-de_DE.png`, `banner-…-de_DE.png` | wie oben | wie oben | nein | lokalisierte Varianten mit Locale-Suffix (MW) |
| `blueprints/blueprint.json` | – | JSON | nein | „Live Preview“ über WordPress Playground erst ab R2-03 (E-214) |

Regeln: nur selbst erstellte Grafiken (kein LB-Bildmaterial, keine KI-Fotos aus LB, keine fremden Logos; R0-01 §3, R0-02/E §6.1); eingesetzte Schriften in `CREDITS.md`; Dateigröße klein halten (Grenze bei Einreichung prüfen, MW); `svn:mime-type` beim Deploy setzen (`image/png`, `image/jpeg`, `image/svg+xml`), sonst lädt der Browser die Datei herunter statt sie anzuzeigen (MW). `.wordpress-org/` gehört nicht ins ZIP (R0-18 §3.2). Screenshots entstehen reproduzierbar per Playwright aus der Referenz-Demo (Skript `tools/release/screenshots.ts`), damit sie zur Version passen.

### 1.6 Lizenz, `LICENSE`, `CREDITS.md`, Plugin-Kopf

| Datei | Inhalt | Quelle der Regel |
|---|---|---|
| `course-captain.php` (Kopf) | `Plugin Name`, `Plugin URI`, `Description`, `Version`, `Requires at least: 7.0`, `Requires PHP: 8.3`, `Author`, `Author URI` (Angaben des Auftraggebers, E-206), `License: GPL-2.0-or-later`, `License URI: https://www.gnu.org/licenses/gpl-2.0.html`, `Text Domain: course-captain`, `Domain Path: /languages`. **Nicht:** `Update URI`, `Network`, `Requires Plugins`, `WC …` | R0-02/E §6.2 Nr. 6, R0-18 §2.5, R0-20 §1 |
| Dateikopf-Kommentar | Copyright-Zeile und GPL-Hinweis („either version 2 … or any later version“) | GPL-Anwendungshinweis |
| `LICENSE` | vollständiger Text der GPL v2 (unverändert) im Plugin-Ordner, damit er im ZIP landet (LB `lizenz.md` „Nach der Entscheidung“) | R0-02/E §6.2 Nr. 6 |
| `CREDITS.md` | drei Abschnitte wie LB `CREDITS.md`: „In den ausgelieferten Paketen“ (je Datei: Herkunft, SPDX-Lizenz, Beleg), „Vorausgesetzt“ (WordPress, optional WooCommerce/Suite-Plugins), „Nur zur Entwicklung“. Nach E-01 (Clean Room) gibt es keinen LB-Code und damit keinen Herkunftsnachweis; Legacy-Namen sind auch hier nicht erlaubt (R0-18 §3.2) | INV-E-116, R0-18 §2.4 |
| `composer.json`, `package.json` | `"license": "GPL-2.0-or-later"` (LB: `proprietary`) | R0-01 §3 |
| Rechtefreigabe | E-01 beschlossen (16.11.2026): **Clean Room**, vermerkt in `R0-01-baseline.md` §3; kein LB-Code im ZIP, Guard auf Legacy-Namen | R0-01 §3 |

---

## 2. Übersetzungen

### 2.1 translate.wordpress.org (MW)

- Nach der Freigabe legt WordPress.org ein Übersetzungsprojekt mit vier Teilprojekten an: **Development (trunk)**, **Development Readme**, **Stable (latest release)**, **Stable Readme**. Die Strings werden bei jedem SVN-Commit aus `trunk` bzw. aus dem `Stable tag` gelesen. Der Code muss die Textdomain `course-captain` als Literal verwenden (I18N-001).
- **Sprachpakete** entstehen für eine Locale, sobald ein Schwellwert der Stable-Strings übersetzt und freigegeben ist (MW: 90 %). WordPress installiert sie unter `wp-content/languages/plugins/course-captain-<locale>.mo` plus JSON für Skripte und aktualisiert sie selbst. `load_plugin_textdomain()` ist für Free nicht nötig (Just-in-time-Laden; R0-15 §3.1). Kein Übersetzungsaufruf vor `init` (I18N-014).
- **Freigabe von Übersetzungen** nur durch Translation Editors der Locale (GTE) oder Projekt-Editoren (PTE) für dieses Plugin. Der Hersteller beantragt PTE-Rechte für `de_DE` und `de_DE_formal` beim Polyglots-Team (E-208). Die deutsche Community hat Glossar und Stilregeln (`de_DE` mit „du“, `de_DE_formal` mit „Sie“); Begriffe aus dem Admin-Glossar (R0-30) werden dagegen abgeglichen.

### 2.2 POT-Erzeugung

- Kommando (Verhalten wie LB `tools/i18n/make-pot.sh`, neu geschrieben nach E-01, R0-15 §3.8): `wp i18n make-pot <plugin-dir> languages/course-captain.pot --slug=course-captain --domain=course-captain --exclude=node_modules,vendor,tests,tools,.wordpress-org --headers='{"Report-Msgid-Bugs-To":"https://wordpress.org/support/plugin/course-captain/"}'`; `--skip-audit` **nicht** setzen. Erfasst PHP, JS (inklusive gebaute Bundles) und `block.json`.
- CI: I18N-004 (kein Diff außer Zeitstempel), Audit-Warnungen von WP-CLI (fehlende `translators:`-Kommentare, mehrdeutige Platzhalter) blockieren. Plugin Check meldet dieselben Fehler ebenfalls.
- Die `.pot` liegt im ZIP (`languages/course-captain.pot`, R0-18 §3.2) und ist für Betreiber mit eigenen Übersetzungen gedacht. Die maßgebliche Quelle für translate.wordpress.org ist der Code selbst.

### 2.3 Skript-Übersetzungen

WordPress.org erzeugt die JSON-Dateien je **ausgelieferter** JS-Datei aus den Fundstellen im Code (MW). Deshalb: `wp_set_script_translations( $handle, 'course-captain' )` für jeden Handle mit Texten (I18N-R-10); die gebauten Bundles behalten die Aufrufe `__()`, `_n()`, `_x()`, `sprintf` unverändert (Standard von `wp-scripts`; kein Mangling der i18n-Funktionen); `make-pot` auf dem **entpackten ZIP** muss jeden JS-String mindestens einmal mit einer Fundstelle in einer ausgelieferten Datei unter `build/` finden (RC-034). `make-json --use-map` hilft nur bei mitgelieferten Übersetzungen, nicht bei Sprachpaketen.

### 2.4 Deutsch als erste Übersetzung

Reihenfolge für das erste Release (E-205, E-208):

1. Einreichung des vollständigen RC-ZIPs → Review → Freigabe (Slug fest).
2. Erster SVN-Commit nach `trunk` (ohne Tag; `Stable tag` erst mit Schritt 4). Damit erscheinen die Strings im Teilprojekt „Development“.
3. PTE übersetzt `de_DE` und `de_DE_formal` im Development-Projekt (Import der eigenen `.po` ist für PTE möglich, MW). Prüfung im Kontext auf Staging mit der `.mo` aus dem Export.
4. Tag `vX.Y.Z` → `wporg-deploy` (R0-18 §3.6). Übereinstimmende Strings werden in „Stable“ übernommen.
5. Nachweis: Sprachpaket für `de_DE` ist ausgeliefert (`https://api.wordpress.org/translations/plugins/1.0/?slug=course-captain&version=X.Y.Z` listet `de_DE`); frische Site mit `de_DE` zeigt nach der Installation aus dem Verzeichnis die deutsche Oberfläche (RC-035).

Bis das Sprachpaket freigegeben ist, liegen `de_DE`/`de_DE_formal` als `.mo`/JSON zusätzlich im ZIP (E-208); danach entfallen sie mit dem nächsten Release.

### 2.5 Pro

Pro ist nicht auf translate.wordpress.org. Pro liefert `languages/course-captain-pro-{de_DE,de_DE_formal}.{mo,json}` selbst mit und ruft `load_plugin_textdomain( 'course-captain-pro', false, … )` auf `init` auf (R0-15 §3.1). Gleiche Lints (Domain `course-captain-pro`, I18N-001) im Pro-Repository; Free enthält keine Pro-Strings (Guard R0-18 §2.5).

---

## 3. Paketbuild, Versionierung, Update/Restore, Support, Sicherheit

### 3.1 Paketbuild und ZIP-Inhalt (Build-Details: R0-18 §3)

Build ausschließlich mit `tools/release/build-zips.sh --package free|pro` aus einem Commit (`git archive`), reproduzierbar, mit `SHA256SUMS`, SBOM und Attestierung (R0-18 §3.1, §3.4, §3.5; E-189). Negativliste: R0-18 §3.2. Hier die **Positivliste**, gegen die J-17 prüft:

| Pfad im Free-ZIP | Pflicht | Inhalt |
|---|---|---|
| `course-captain/` | ja | genau ein Wurzelordner |
| `course-captain.php` | ja | Kopf §1.6, Bootstrap |
| `uninstall.php` | ja | Uninstall nach R0-16 §2.5 (Standard ohne Datenlöschung, E-89) |
| `readme.txt` | ja | §1.4 |
| `LICENSE`, `CREDITS.md` | ja | §1.6 |
| `CHANGELOG.md` | ja | vollständiges Änderungsprotokoll mit Markern |
| `languages/course-captain.pot` | ja | §2.2 |
| `languages/course-captain-de_DE{,_formal}.{mo,json}` | bis zur Freigabe des Sprachpakets (E-208) | deutsche Übersetzung als Rückfall |
| `src/` | ja | PHP-Code (`CourseCaptain\`) |
| `build/` | ja | gebaute JS/CSS-Bundles mit `*.asset.php` (Abhängigkeiten auf WordPress-Handles) |
| `assets/src/` oder Link im Readme | ja (eins von beiden) | lesbare Quelle der Bundles (Richtlinie 4); Source Maps nicht im ZIP |
| `blocks/*/block.json` | wenn Blöcke | Block-Metadaten (übersetzbar) |
| `templates/` | wenn vorhanden | überschreibbare Templates |
| `vendor/` | nur wenn Laufzeitabhängigkeiten nötig | `composer install --no-dev --classmap-authoritative`; jede Bibliothek in `CREDITS.md`/SBOM; Präfixierung prüfen, falls die Bibliothek global kollidieren kann |

Grenze: ZIP für die Einreichung ≤ 10 MB (MW); Ziel deutlich darunter (Bundle-Budgets FE-04…06, R0-14).

**Pro-ZIP** (`course-captain-pro/`, gebaut in `captain-pro/addons/course-captain-pro`, E-273, R3-18): `course-captain-pro.php` mit `Update URI` (R0-36 L3) und `Requires Plugins: course-captain` (Vorschlag: WordPress verhindert dann die Aktivierung ohne Free und das Deaktivieren von Free, solange Pro aktiv ist; Abgleich in R0-39), vendorter Lizenzclient im eigenen Namespace (`CourseCaptainPro\Licensing`, R0-36 L2), eigene Sprachdateien (§2.5), `LICENSE` GPL-2.0-or-later (E-213), `CREDITS.md`, `uninstall.php` nur für Pro-Daten (R0-16 §2.5). Pro enthält keinen Free-Code (Gegenprobe R0-18 §2.5).

### 3.2 Versionierung

- **SemVer** `MAJOR.MINOR.PATCH`: PATCH ohne DB-Änderung, MINOR mit Schemaänderung oder neuen Funktionen, MAJOR bei Bruch eines stabilen Vertrags (R0-16 §3.3 Nr. 1, LB `release-regel.md`; Deprecation-Frist E-118). Vorabversionen (`-rc.N`) nur als GitHub-Release, nie nach WordPress.org (R0-18 §3.6 Schritt 7). Startversion **1.0.0** (E-209).
- **Gleich sein müssen:** Plugin-Kopf `Version` = Konstante `CourseCaptain\VERSION` = `readme.txt` `Stable tag` = oberster `CHANGELOG.md`-Eintrag = Git-Tag = SVN-`tags/<version>`. Getrennt geführt: `cc_db_version`, Paketformat, REST, Connectorvertrag, Pro-Version.
- **Kopfzeilen:** `Requires at least: 7.0`, `Requires PHP: 8.3` (E-195; R0-18 angepasst), `Tested up to: 7.1` (R0-20 §1). WordPress verhindert die Aktivierung bei zu alter PHP-/WP-Version selbst. Eine eigene Prüfung beim Laden (vor dem ersten Klassen-Autoload) verhindert zusätzlich einen Fatal, wenn das ZIP manuell auf einem zu alten System landet (RC-047).
- **`Stable tag`-Regel:** zuletzt umstellen; nie auf eine Version mit älterem Schema zurücksetzen (R0-16 §3.3 Nr. 7). Ein fehlerhaftes Release wird nur vorwärts mit PATCH korrigiert.
- **`Tested up to`:** Anhebung allein (ohne Codeänderung) ist laut Richtlinien ohne neue Version erlaubt, wenn nur `readme.txt` in `trunk` und im aktuellen Tag geändert wird (MW). Course Captain hebt trotzdem mit einem PATCH an, weil M-I1 grün sein muss und die Versionsgleichheit sonst bricht (R0-20 §1).

### 3.3 Update- und Restore-Proben (Ausführung R1-23, Abnahme R1-39)

| Probe | Grundlage | Nachweis in R1-39 |
|---|---|---|
| Upgrade vom Release-Artefakt der Vorversion, ohne Reaktivierung | REC-023, R0-16 §2.1, eigenes `upgrade-check` (Verhalten wie LB, neu geschrieben, E-01) | Protokoll J-16 mit Zeilenzahlen vorher/nachher; Systemstatus „Schema aktuell“ |
| Upgrade über den WordPress-Updater aus dem Verzeichnis | neu | Nach dem ersten WordPress.org-Release: Staging mit Vorversion, Update über „Dashboard › Aktualisierungen“ bzw. `wp plugin update course-captain`; gleiches Ergebnis wie J-16. Vor dem ersten Release: Trockenlauf mit lokalem Update-Server-Mock (Transient) |
| Automatische Updates | E-174 | Auto-Update während einer `strict`-Prüfung wird aufgeschoben (≤ 24 h); danach läuft es |
| Abgebrochenes Upgrade | REC-006, REC-007, REC-011 (R1-40) | Verweis auf R1-40-Protokoll |
| Restore gleicher Ort / andere Domain / Kopie | REC-001…REC-003 | Verweis auf R1-23-Protokoll mit RTO |
| Rollback R-A/R-B/R-C | REC-009, REC-025, E-90 | Verweis auf R1-23-Protokoll; Release-Notes nennen die Rollback-Klasse |
| Deaktivieren und Reaktivieren | R0-16 §2.5 | keine Datenänderung, Cron ab- und wieder angemeldet, Lernende sehen nach Reaktivierung denselben Stand |
| Uninstall ohne / mit Datenlöschung | REC-018, REC-019, REC-020 | Verweis auf R1-23; Bestätigung E-89 |
| Verzeichnis-Artefakt = eigenes Artefakt | R0-18 §3.6 Schritt 8 (`wporg-verify`) | Dateiliste und SHA-256 je Datei gleich |

### 3.4 Erstinstallation, Demo, Deaktivierung

- **Clean-Install** aus dem ZIP und (nach Freigabe) aus dem Verzeichnis auf KP-01 (Referenz) und KP-02 (Untergrenze) (R0-20): Aktivierung ohne PHP-Notice (Notice-Gate), Setup-Assistent startet nur bei Einzelaktivierung im Admin und ist überspringbar, Systemstatus ohne Fehler, keine Fremdhost-Anfrage (FE-07; ohne eingebettete Medien).
- **Demo ohne Fremdinhalte:** R1 legt nur ein leeres Kursgerüst an (E-102); keine Beispielfragen, Bilder oder Videos aus LB oder Dritten. Demo-Kurse mit Inhalt kommen erst mit R2-03 und brauchen dann eigene `CREDITS.md`-Zeilen.
- **Koexistenz** mit LB `9f37c77` (E-194) und den Konfliktkombinationen aus R1-30 gehören zu den Voraussetzungen von R1-39 und werden nur referenziert.

### 3.5 Support-Forum-Regeln (WordPress.org)

Das Forum `wordpress.org/support/plugin/course-captain/` ist Stufe 4 aus R0-13 („Herstellersupport“) für **Free**; betreut wird es vom **Auftraggeber** (E-212). Regeln (Forenrichtlinien, MW):

1. **Nie** nach Zugangsdaten, Admin-Konten, Datenbankdumps oder Personendaten fragen; kein Konto auf der Betreiber-Site (E-142).
2. Free-Support findet im Forum statt. Nicht auf bezahlten Support, E-Mail oder externe Ticketsysteme verweisen. **Pro-Fragen** gehören nicht ins Forum (Richtlinie für kommerzielle Produkte, MW) und werden freundlich an den Pro-Kanal verwiesen (E-212).
3. **Diagnose im Forum:** nur die forentaugliche Textfassung des Diagnosepakets (Versionen, Fehlercodes, `request_id`, aktive Plugins, Systemstatus-Ampeln; Domain gehasht, keine Pfade, keine E-Mail-Adressen). Das vollständige Diagnosepaket (R0-13) wird **nicht** öffentlich gepostet → Delta R0-13 (§7).
4. **Sicherheitsfunde** nie im Forum diskutieren. Beiträge mit Details werden gemeldet bzw. an die Moderation gegeben, der Melder wird auf `SECURITY.md` verwiesen.
5. Bewertungen nicht beeinflussen (keine Bitte um 5 Sterne gegen Gegenleistung, keine Antwort-Kampagnen).
6. Antwortvorlagen (Runbook R1-24) für: Erstinstallation, Upgrade/Migration fehlgeschlagen, Restore, Konflikt mit Cache-/Security-Plugin (R0-20 KOMP-Zeilen), Übersetzung fehlt, Pro-Frage, Sicherheitsmeldung.

### 3.6 Sicherheitsmeldeprozess (`SECURITY.md`)

`SECURITY.md` liegt im Repository-Stamm (GitHub zeigt sie unter „Security“); ein kurzer Hinweis plus Link steht in der Readme-FAQ. Gliederung:

| Abschnitt | Inhalt |
|---|---|
| Unterstützte Versionen | nur die aktuelle Minor-Version von Free erhält Sicherheitskorrekturen (WordPress.org liefert nur den `Stable tag` aus); Pro nach License-Captain-Kanal |
| Meldung | an die **Sicherheitsadresse des Auftraggebers** (E-211); zusätzlich privat über GitHub „Report a vulnerability“, sobald das Repository öffentlich ist (E-188); kein öffentliches Issue, kein Forum |
| Inhalt einer Meldung | Version, betroffene Komponente, Schritte, Auswirkung; keine echten Personendaten |
| Fristen | **Erstreaktion ≤ 72 h (beschlossen, E-211)**; Zielwerte (nicht beschlossen): Erstbewertung (CVSS) ≤ 10 Werktage, Korrektur kritisch/hoch ≤ 14 Tage, mittel ≤ 30 Tage, niedrig mit nächstem Release; koordinierte Veröffentlichung spätestens 90 Tage nach Eingang |
| Ablauf | PATCH nur mit der Korrektur (R0-16 §3.3 Nr. 8, LB `release-regel.md` T117); Changelog-Zeile „Sicherheitsupdate:“ ohne Ausnutzungsanleitung; normaler Release-Weg mit beschleunigter Freigabe (E-193); danach GitHub Security Advisory mit CVE (GitHub ist CVE-Vergabestelle für Advisories öffentlicher Repositories, MW) |
| WordPress.org | Bei kritischen Lücken Kontakt zum Plugins-Team, das Updates erzwingen oder das Plugin bis zur Korrektur schließen kann (MW, Richtlinie 18). Kontaktweg im Runbook R1-24 |
| Danksagung | Nennung der Melder nach Zustimmung |
| Umfang | Free einschließlich Härtungsmodul (E-10, R1-45). **Nicht im Umfang:** Befunde in WordPress-Kern oder Fremd-Plugins (Weiterleitung an deren Kanal) |

Pro: eigener Abschnitt in der Pro-Doku; Auslieferung des Fixes über License Captain mit Signatur (E-190).

---

## 4. Pro-Distribution über License Captain

| Punkt | Festlegung | Quelle | Status |
|---|---|---|---|
| Kanal | ausschließlich License-Captain-Client im Pro-Add-on; nie WordPress.org, nie GitHub-Abfrage | E-09, R0-16 §3.4 | beschlossen |
| Produkt | `course-captain-pro`, Präfix `course.` im License-Captain-Katalog | E-42, E-95 | **offen, extern** (fehlt in `PRODUCT_KEYS`) |
| Client | vendorte Kopie mit eigenem Namespace (`export-client.php --namespace=CourseCaptainPro\Licensing --text-domain=course-captain-pro`), `Update URI` Pflicht, Produktschlüssel = Ordner = Textdomain | R0-36 L2, L3 | Vorgabe |
| Abhängigkeit von Free | `core_version` = installierte Free-Version; Server hält Releases mit höherem `requires_core` zurück; Reihenfolge Free → Pro | R0-36 L4, R0-16 §2.1 U-9 | Vorgabe |
| Lizenzende | schaltet nur Pro-Funktionen ab; Free-Verhalten und -Daten unverändert; Grants laufen weiter | R0-36 L5, E-93, E-94 | beschlossen |
| Signatur | License Captain signiert beim Upload, Client prüft vor der Installation; signierte Update-Antwort + SHA-256 + Build-Attestierung (E-272) | E-190, E-272, R0-18 §3.5 | beschlossen, abhängig von License Captain |
| Freigabe | Environment `license-captain` mit Pflicht-Reviewer (Auftraggeber) | E-193, E-204, R0-18 §3.6 | beschlossen |
| Hinweis in Free | nur Seite/Karte „Erweiterungen“, ausblendbar, keine gesperrten Felder | E-100, N-08 | beschlossen |
| Lizenz des Pro-Codes | GPL-2.0-or-later; Lizenzschlüssel regelt Updates und Support | E-213 | beschlossen |
| Build-Ort | `captain-pro/addons/course-captain-pro` (Konzept-Plan); Voraussetzung: Zugriff auf `captain-pro` für Kernel, FeatureRegistry, TARIFE, ARCHITECTURE | E-273 | beschlossen, **Zugriff offen** |
| Release-Readiness des Servers | alle Gates in `wp-license/docs/RELEASE-READINESS.md` offen (Build, Kauf/Verlängerung, **Updates und Produkt-Plugins**, Security inkl. „server signature in the client“, Betrieb, Sign-off); Regel „Do not advertise a capability … before its gate is ticked“ | R0-36 L12 | **externer Blocker für R3-18** |
| Spezifikation der Grenze | Feature-Flags, Upgrade/Downgrade, Ausfall, Lizenzzustände | **R0-39** | siehe R0-39 |

Folge: Pro-Zeilen der Checkliste (RC-090…RC-099) haben das Ziel **R3-18**, nicht R1-39. R1-39 prüft für Pro nur die Free-Seite der Grenze (RC-020, RC-021, RC-022). Pro darf nicht beworben werden (Readme, Erweiterungen-Seite), bevor RC-090…RC-099 abgehakt sind (Regel aus License Captain).

---

## 5. Prüfbare Release-Checkliste (RC-xxx)

**Nachweisarten:** **CI** = blockierender CI-Job mit Lauf-ID (R0-18); **ART** = Prüfung am entpackten Release-ZIP (J-17); **NEG** = Negativtest (absichtlich verletztes Artefakt muss rot werden); **MAN** = manuelle Prüfung mit Protokoll (Datum, Prüfer, Screenshot bzw. Befehlsausgabe); **DOK** = Dokument-Review durch die genannte Rolle (R0-24 §4.4); **EXT** = externer Nachweis (WordPress.org, License Captain, Polyglots). Eine Zeile ist erst abgehakt, wenn der Nachweis im Gate-Protokoll von R1-39 (bzw. R3-18) steht. Bezeichner dreistellig, um Verwechslung mit den Reconcile-Schritten RC-1…RC-7 aus R0-13 zu vermeiden.

**Ziel-Task aller Zeilen RC-001…RC-078: R1-39** (Gate C-15, R1-GATE). Spalte „mit“ nennt die Task, die den Prüfling liefert.

### 5.1 Slug, Konto, Einreichung

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-001 | Fassung der WordPress.org-Richtlinien, Readme- und Asset-Regeln am Prüfdatum gelesen; alle (MW)-Angaben dieses Dokuments bestätigt oder korrigiert | DOK (QA/Release) | R0-35 |
| RC-002 | Name „Course Captain“ und Slug ohne fremde Marke; Markenrecherche dokumentiert | DOK (PO) | – |
| RC-003 | Slug `course-captain` verfügbar (OP-20-1) oder Ersatz `course-captain-lms` nach E-205 umgesetzt (Ordner, Textdomain, `.pot`, Guards) | EXT + MAN | R1-01 |
| RC-004 | WordPress.org-Konto des Auftraggebers mit 2FA (E-206), Committer mit 2FA; SVN-Passwort nur im Environment `wordpress-org` | EXT + MAN | R1-01 |
| RC-005 | Einreichung mit RC-ZIP, das alle Zeilen außer EXT erfüllt; Review-Antworten im Protokoll; Freigabe-Mail archiviert | EXT | – |
| RC-006 | Repository zur ersten WordPress.org-Veröffentlichung öffentlich geschaltet (E-188), Link in `readme.txt`/`Plugin URI` | MAN | R1-39 |

### 5.2 Rechte und Lizenz

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-010 | E-01 (Clean Room) in R0-01 §3 vermerkt; Clean-Room-Erklärung: kein Baustein enthält LB-Code, alle LB-Werkzeuge neu geschrieben | DOK (PO) | R1-01 |
| RC-011 | Plugin-Kopf und `readme.txt` mit `License`/`License URI`; `LICENSE` (GPLv2-Volltext) im ZIP | ART | R1-01 |
| RC-012 | Lizenz-Allowlist grün: keine verbotene Lizenz in `composer licenses --no-dev` / `license-checker --production` | CI (J-09) | R1-01 |
| RC-013 | `CREDITS.md` = SBOM = gebündelte Fremddateien (gleiche Menge) | CI (J-09) + ART | R1-01 |
| RC-014 | Keine LB-Inhalte, -Bilder, -Schriften, Gerätesimulation oder Legacy-Namen im ZIP und in den Assets (R0-18 §3.2) | ART + NEG (Datei mit `bootspruefung` → rot) | R1-01 |

### 5.3 Richtlinien und Sicherheit

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-020 | Free-Guard: kein `Update URI`, kein Updater-Hook, kein Lizenzclient, keine GitHub-Abfrage, keine `course-captain-pro`-Strings | CI (J-10) + ART + NEG (ZIP mit `Update URI:` → rot) | R1-01 |
| RC-021 | Kein gesperrter Pro-Schalter, kein Upsell außerhalb der Seite „Erweiterungen“; Seite ausblendbar (E-100, N-08) | MAN (Durchgang aller Admin-Screens A-01…A-16) + E2E | R1-16 |
| RC-022 | Free ohne Pro, WooCommerce und Suite-Plugins voll nutzbar (Kap. 23.2 Nr. 1) | CI (J-22) | R1-18 |
| RC-023 | Keine gebündelten Kopien von WordPress-Bibliotheken (React, jQuery, `@wordpress/*`) | ART (Bundle-Analyse) | R1-01 |
| RC-024 | Lesbare Quelle für jedes Bundle im ZIP oder im verlinkten öffentlichen Repository; keine Obfuskation | ART + DOK | R1-01 |
| RC-025 | Keine Telemetrie, keine Nutzungs- oder Versionsabfrage (Netzwerkprotokoll Admin + Frontend + Cron 24 h: 0 externe Requests außer WordPress-Kern) | CI (E2E Fremdhost-Erfassung, INV-E-111) | R1-16 |
| RC-026 | Fremdhost-Anfragen im Lernbereich nur für eingebettete Medien, die in „External services“ offengelegt sind (PRIV-017, FE-07); Default „immer laden“ (E-45a); im Modus „Klick zum Laden“ 0 Requests vor dem Klick | CI (E2E, beide Modi) + NEG (Modus „Klick zum Laden“, Kurs mit YouTube-Video ohne Klick → 0 Requests) | R1-16 |
| RC-027 | Abschnitt „External services“ vollständig und deckungsgleich mit der Fremdhost-Erfassung | DOK (SEC) | R1-24 |
| RC-028 | Kein Credit-Link im Frontend, auf Zertifikat oder Verifikationsseite ohne Opt-in | E2E (HTML-Probe) | R1-44 |
| RC-029 | Admin-Hinweise ausblendbar und nur auf relevanten Screens; keine Weiterleitung nach Sammel-/Netzwerk-/CLI-Aktivierung; Assistent überspringbar | E2E + NEG (Sammelaktivierung → keine Weiterleitung) | R1-16 |
| RC-030 | Plugin Check auf dem ZIP: 0 Fehler; Warnungen nur mit Eintrag in der Ausnahmeliste | ART (J-17) | R1-01 |
| RC-031 | Sicherheitsjobs grün auf dem Release-Commit: PHPCS-Security, Psalm-Taint, Semgrep, Dependency-, Secret-Scan (Gate C-13) | CI (J-01, J-06…J-08) | R1-18 |
| RC-032 | Präfixregel `ccap_` nach E-207 eingehalten (statischer Scan aller global registrierten Namen) | CI (KOMP-130-Scan) | R1-01 |

### 5.4 readme.txt, Übersetzungen, Texte

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-033 | `readme.txt` validiert: Kopffelder §1.4, ≤ 5 Tags, Kurzbeschreibung ≤ 150 Zeichen, Upgrade Notice ≤ 300 Zeichen, Screenshot-Liste = Assets, Changelog-Eintrag der Version | ART (Readme-Skript + Plugin Check) + NEG (6 Tags → rot) | R1-24 |
| RC-034 | `.pot` aktuell (I18N-004); `make-pot` auf dem ZIP findet jeden JS-String mit Fundstelle in `build/` | CI + ART | R1-19 |
| RC-035 | Deutsche Oberfläche: `de_DE` und `de_DE_formal` auf translate.wordpress.org übersetzt und freigegeben; Sprachpaket ausgeliefert, bis dahin `.mo`/JSON im ZIP (E-208); frische `de_DE`-Site zeigt deutsche Admin- und Lernoberfläche inklusive JS-Texten | EXT + MAN | R1-19 |
| RC-036 | Wortliste SEC-045: 0 Treffer in `.pot`, `readme.txt`, Doku, Assets-Texten | CI | R1-24 |
| RC-037 | Abschnitte Privacy und FAQ stimmen mit R0-17 (Datenkategorien, Fristen) und R1-24 überein | DOK (SEC, PO) | R1-24 |
| RC-038 | RTL-Probe: Admin und Lernbereich mit RTL-Locale (D-15-11, I18N-007); RTL-Banner vorhanden oder bewusst weggelassen | CI + MAN | R1-19 |

### 5.5 Assets

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-039 | Icon 128/256, Banner 772×250/1544×500, ≥ 3 Screenshots in `.wordpress-org/`; Maße und Formate per Skript geprüft | ART + NEG (Banner 770 px → rot) | R1-24 |
| RC-040 | Screenshots aus der aktuellen Version erzeugt (Skript), neutrale Demo, keine Personendaten, keine Fremdlogos; Rechte jeder Grafik in `CREDITS.md` oder als Eigenleistung vermerkt | MAN + DOK (PO) | R1-24 |

### 5.6 Build, Version, Artefakt

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-041 | Versionsgleichheit Kopf = Konstante = `Stable tag` = CHANGELOG = Tag; „geändert, aber Version gleich“ rot | CI (J-17) + NEG | R1-01 |
| RC-042 | Positivliste §3.1 erfüllt, Negativliste R0-18 §3.2 ohne Treffer | ART + NEG | R1-01 |
| RC-043 | Reproduzierbarkeit: zwei Builds, gleiche Prüfsummen | CI (J-18) | R1-01 |
| RC-044 | `SHA256SUMS`, SBOM, Attestierung und cosign-Bündel vorhanden und verifizierbar (`gh attestation verify`, `cosign verify-blob`) | ART + MAN | R1-01 |
| RC-045 | ZIP-Größe ≤ 10 MB (MW) und Bundle-Budgets FE-04…06 eingehalten | CI | R1-22 |
| RC-046 | `php -l` unter PHP 8.3; Kopfzeilen `Requires PHP: 8.3`, `Requires at least: 7.0`, `Tested up to: 7.1` | ART | R1-01 |
| RC-047 | Zu alte Umgebung (PHP 8.2 bzw. WP 6.9 bei manueller Kopie): kein Fatal, verständlicher Hinweis, keine Schemaänderung | CI (Sonderzeile) + NEG | R1-01 |
| RC-048 | Changelog-Marker stimmen mit den Migrationsschritten überein (Schema/Backfill/Contract) | CI (J-17) + NEG | R1-23 |

### 5.7 Deploy und Verzeichnis

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-050 | SVN-Deploy nur aus `release.yml` mit Freigabe durch den Auftraggeber, keine Selbstfreigabe (E-13, E-193); Trockenlauf gegen Test-Checkout grün | CI (`wporg-deploy`-Trockenlauf) + MAN | R1-01 |
| RC-051 | Reihenfolge `tags/<v>` → `assets/` → `trunk` mit neuem `Stable tag` zuletzt; `svn:mime-type` der Assets gesetzt | CI-Protokoll | R1-01 |
| RC-052 | `wporg-verify`: Verzeichnis-ZIP inhaltsgleich (Dateiliste, SHA-256 je Datei) | CI | R1-01 |
| RC-053 | Verzeichnisseite geprüft: Name, Banner, Icon, Screenshots, Readme-Abschnitte, Sprache Englisch und Deutsch | MAN + EXT | R1-24 |
| RC-054 | Kein `Stable tag` auf älteres Schema (R0-16 §3.3 Nr. 7); Rückzug nur vorwärts per PATCH | CI (preflight) + NEG | R1-23 |

### 5.8 Installation, Update, Restore, Deaktivierung

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-060 | Clean-Install aus dem ZIP auf KP-01 und KP-02: Aktivierung ohne Notice, Systemstatus grün, Smoke/Lernfluss/Import grün (INV-E-090) | CI (J-17, Artefakttest) | R1-18 |
| RC-061 | Clean-Install aus dem Verzeichnis (nach Freigabe) auf frischer `en_US`- und `de_DE`-Site | MAN + EXT | – |
| RC-062 | Demo/Assistent legt nur leeres Kursgerüst an; keine Fremd- oder Fachinhalte (E-102) | E2E + MAN | R1-16 |
| RC-063 | Upgrade-Probe vom Release-Artefakt der Vorversion (REC-023) grün | CI (J-16) | R1-23 |
| RC-064 | Update über den WordPress-Updater (Verzeichnis bzw. Mock vor dem ersten Release) und Auto-Update mit Aufschub bei `strict`-Prüfung (E-174) | MAN + CI | R1-23 |
| RC-065 | Restore-, Rollback- und Uninstall-Proben REC-001…003, 009, 018…020, 025 grün (Protokoll R1-23) | CI + MAN (Verweis) | R1-23 |
| RC-066 | Deaktivieren/Reaktivieren ohne Datenänderung; Cron ab- und wieder angemeldet | CI | R1-23 |
| RC-067 | Release-Notes nennen Rollback-Klasse (R-A/R-B/R-C), Backup-Pflicht und Prüfanleitung der Signatur | DOK (OPS) | R1-24 |

### 5.9 Support und Sicherheitsprozess

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-070 | `SECURITY.md` nach §3.6 im Repository; Sicherheitsadresse des Auftraggebers erreichbar (Testmail innerhalb 72 h beantwortet, E-211); Private Vulnerability Reporting aktiv, sobald das Repository öffentlich ist (E-188) | MAN | R1-24 |
| RC-071 | Probe des Sicherheitsablaufs: fiktive Meldung → privates Advisory → PATCH-Branch → Release-Trockenlauf; Erstreaktion gegen E-211 (≤ 72 h) gemessen | MAN | R1-24 |
| RC-072 | Support-Runbook mit Forum-Regeln §3.5 und Antwortvorlagen; Forum-Betreuung durch den Auftraggeber (E-212) | DOK (OPS, PO) | R1-24 |
| RC-073 | Forentaugliche Diagnose-Textfassung ohne Personendaten, Pfade und Klartext-Domain (Scrubber-Test mit Testdaten) | CI | R1-38 |
| RC-074 | Readme-FAQ nennt Support-Kanal Free, Pro-Kanal (nur Verweis) und Sicherheitsmeldung | ART | R1-24 |

### 5.10 Gate-Abschluss

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-075 | Alle Pflichtjobs der Release-Lane grün auf dem Tag-Commit, keine Quarantäne einer Pflichttest-ID (E-184, R0-18 §4.1) | CI | R1-18 |
| RC-076 | Kap. 23.2 Nr. 6 (Übersetzungen, Datenschutz) und Nr. 8 (Supporthandbuch, Changelog, Security-Disclosure, Lizenzinventar) mit Belegen verknüpft | DOK (QA/Release) | R1-24 |
| RC-077 | Konformitätsbericht Barrierefreiheit der Version veröffentlicht (E-125) | DOK (A11Y) | R1-24 |
| RC-078 | Gate-Protokoll R1-39 vollständig: jede RC-Zeile mit Nachweis, Datum, Prüfer; Abzeichnung durch den Auftraggeber | DOK | R1-39 |

### 5.11 Pro (Ziel R3-18, nicht R1-39)

| ID | Prüfpunkt / Erwartung | Nachweis | mit |
|---|---|---|---|
| RC-090 | E-42 umgesetzt: Produkt `course-captain-pro` mit Präfix `course.` im License Captain vorhanden | EXT | R3-18 |
| RC-091 | Pro-Kopf mit `Update URI`; Gegenprobe: WordPress bietet kein Verzeichnis-Update für den Slug an | ART + MAN | R3-18 |
| RC-092 | Pro-Guard: Client im eigenen Namespace, kein Free-Code kopiert (R0-18 §2.5 Gegenprobe) | CI (`captain-pro`, E-273) | R3-18 |
| RC-093 | Upload, Signatur und Signaturprüfung im Client: falsche Prüfsumme, abgelaufener Link, inaktive Lizenz werden abgelehnt (E-190, E-272; `RELEASE-READINESS.md` §3) | CI + MAN | R3-18 |
| RC-094 | `requires_core`: Pro-Release mit höherer Free-Anforderung wird zurückgehalten, Hinweis im Admin (R0-36 L4) | MAN | R3-18 |
| RC-095 | Lizenzende/Serverausfall: Free-Verhalten und -Daten unverändert, Grants laufen weiter (E-93, R0-36 L5) | CI | R3-18 |
| RC-096 | Pro-Sprachdateien `de_DE`/`de_DE_formal` mitgeliefert und geladen | ART | R3-18 |
| RC-097 | Pro-Uninstall löscht nur Pro-Daten | CI | R3-18 |
| RC-098 | Rollback eines Pro-Releases über License Captain (Release zurückziehen → Vorversion angeboten) | MAN | R3-18 |
| RC-099 | License-Captain-Gates „Updates and product plugins“ und „Security“ in `RELEASE-READINESS.md` abgehakt; vorher keine Pro-Werbung in Free | EXT | R3-18 |

---

## 6. Entscheidungsbedarf – beschlossen

Alle Punkte sind entschieden (`ENTSCHEIDUNGEN.md` §32 und Beschlussprotokoll, 16.11.2026). Außerdem gelten E-01, E-09, E-13, E-45/E-45a, E-100, E-121, E-188, E-189, E-193, E-204, E-273. Technisch festgelegt ohne Auftraggeber-Entscheidung: Positivliste §3.1, Readme-Gliederung §1.4, Asset-Erzeugung per Skript, Plugin-Check-Gate §1.2, Forum-Regeln §3.5 Nr. 1, 3, 4, `Requires Plugins: course-captain` im Pro-Kopf (Vorschlag an R0-39).

| ID | Frage | Beschluss | blockiert |
|---|---|---|---|
| D35-1 → **E-205** | Ersatzslug und Zeitpunkt der Einreichung | Slug vor R1-Start prüfen und **früh einreichen**; Ersatz `course-captain-lms` | R1-01, R1-39 |
| D35-2 → **E-206** | WordPress.org-Konten und Herstellerangaben | **Auftraggeber legt das WordPress.org-Konto mit 2FA an** | RC-004, R1-01 |
| D35-3 → **E-207** | Präfix (`cc_` hat nur 3 Zeichen) | **`ccap_`** für CPTs, Caps, Rollen, Cron, Meta **und Tabellen** (über die Empfehlung hinaus auch Tabellen); vor R1-01 gegen die aktuelle Richtlinie prüfen | R1-01, R0-06, R0-07, R0-19, R0-20 |
| D35-4 → **E-208** | Deutsche Übersetzung im ersten Release | Sprachpaket über translate.wordpress.org, **bis zur Freigabe zusätzlich im ZIP** | RC-035, R1-19 |
| D35-5 → **E-209** | Erste öffentliche Versionsnummer | **1.0.0** | R1-01, R1-39 |
| D35-6 → **E-210** | Telemetrie / Nutzungsstatistik | **Keine** | RC-025, Kap. 23.3 |
| D35-7 → **E-211** | Sicherheitsmeldeprozess | Meldungen über eine **Adresse des Auftraggebers**; **Erstreaktion ≤ 72 h**; Fixes für die aktuelle Minor | RC-070, RC-071, R1-24 |
| D35-8 → **E-212** | Support-Betreuung | **Auftraggeber betreut das wp.org-Forum**; Pro-Support über eigenen Kanal | RC-072, R1-24 |
| D35-9 → **E-213** | Lizenz des Pro-Add-ons | **GPL-2.0-or-later**; Lizenzschlüssel regelt Updates und Support | R0-39, R3-18 |
| D35-10 → **E-214** | Live Preview auf WordPress.org | **Erst ab R2-03** | R2-03 |

### Offene Punkte nach Einarbeitung

- **E-205 „früh einreichen“** und Richtlinie 16 (nur vollständige Plugins, MW) stehen in Spannung: Eine sehr frühe Einreichung mit unvollständigem Plugin riskiert die Ablehnung. Ab wann das Plugin als vollständig gilt, ist vor R1-Start festzulegen (RC-001 prüft die gültige Fassung).
- **E-206** nennt nur das Konto des Auftraggebers; ob ein zweiter Committer bzw. eine Vertretung angelegt wird, ist offen (vgl. E-193: Vertretung vor R1-GATE).
- **E-211** legt nur die Erstreaktion fest; die übrigen Fristen in §3.6 (Erstbewertung, Korrekturfristen, 90-Tage-Veröffentlichung) sind Zielwerte ohne Beschluss. Ein Reaktionsziel für das Forum (Empfehlung: 5 Werktage) ist in E-212 nicht festgelegt.
- **E-45a**: Die Offenlegung in „External services“ muss mit der tatsächlichen Fremdhost-Erfassung (RC-027) übereinstimmen; ob das WordPress.org-Review den Default „immer laden“ ohne Einwilligung akzeptiert, ist offen (MW). Die datenschutzrechtliche Bewertung liegt beim Betreiber (Hinweis in E-45).
- **E-207**: Die Prüfung gegen die aktuelle Review-Richtlinie (vor R1-01) steht aus.
- **E-273**: Zugriff auf `captain-pro` fehlt; RC-090…RC-099 bleiben bis dahin unprüfbar.

---

## 7. Deltas zu anderen Dokumenten

| Dokument | Delta | Stand |
|---|---|---|
| R0-18 §3.1 | `Requires PHP` = **8.3** (E-195 ersetzt E-57) | erledigt |
| R0-18 §3.3 | Plugin-Check-Ausnahmeliste `tools/qa/plugin-check-allow.txt`, Readme-Skript (§1.4), Asset-Maßprüfung (RC-039), `make-pot` auf dem ZIP (RC-034) ergänzen | offen |
| R0-18 §3.6 Schritt 7 | `svn:mime-type` für Assets setzen; Anhebung von `Tested up to` immer per PATCH | offen |
| R0-16 §2.1 U-5 | Verweis E-57 → E-195 | offen |
| R0-13 | forentaugliche Textfassung des Diagnosepakets (RC-073); Forum-Regeln §3.5 als Teil von Stufe 4; Betreuung durch den Auftraggeber (E-212) | offen |
| R0-15 §3.1 | Ablauf §2.4 und E-208; Hinweis zu JS-Fundstellen in `build/` (§2.3) | E-208 eingearbeitet, Rest offen |
| R0-20 D-20-03 | Präfix `ccap_` für CPTs, Caps, Rollen, Cron, Meta und Tabellen (E-207) | erledigt |
| R0-24 §4.2/§4.3 | Zeile R1-39: führendes Dokument R0-35, zusätzliche E-Nummern E-205…E-214; Testkommando bleibt `--group=R1-39` / `@R1-39` | offen |
| ENTSCHEIDUNGEN.md | D35-1…D35-10 als E-205…E-214 | erledigt |

---

## 8. Übergaben

| Ziel | Übergabe |
|---|---|
| R1-01 | Kopfzeilen, `LICENSE`, `CREDITS.md`, `SECURITY.md`, `.wordpress-org/`, Readme-/Asset-Skripte, Plugin-Check-Gate, Präfix `ccap_` (E-207), Slug nach E-205 |
| R1-19 | POT/JS-Übersetzungsregeln §2.2/§2.3, PTE-Ablauf §2.4 |
| R1-23 | Probenliste §3.3 (Protokoll als Eingang für RC-063…RC-066) |
| R1-24 | Readme-Texte, FAQ, Privacy/External services, Support-Runbook mit Forum-Regeln, Sicherheitsablauf, Release-Notes-Vorlage |
| R1-38 | forentaugliche Diagnose-Textfassung |
| **R1-39** | Checkliste RC-001…RC-078 als Gate-Protokoll |
| R0-39 / R3-18 | §4, RC-090…RC-099, `Requires Plugins: course-captain` im Pro-Kopf, E-213, E-273 |

---

## 9. Nachweis

- Gelesen im Planungsrepo: `TODO-WETTBEWERBSANALYSE.md` (R0-35 Z. 187-190, R1-39 Z. 411-414, R0-39, R1-23, R1-24, R1-30, R2-03, R3-18); `konzept.md` Kap. 1, 6, 12 Nr. 9, 23.2, 23.3, 44 (Z. 1378-1393), 45, 46.2; `docs/r0/ENTSCHEIDUNGEN.md` (E-01…E-204 vollständig auf Überschneidungen geprüft; Abschnitt 30 zu D-20-03/OP-20-1); R0-01 §1, §3; R0-02/E §4.1, §4.2, §4.5, §5 (Zeilen `installation-und-updates.md`, `lizenz.md`, `release-regel.md`), §6, §7; R0-15 §3.1, I18N-R-10, I18N-001…014, D-15-01; R0-16 §2.1, §2.2, §2.5, §3.3, §3.4, §4 (REC-001…025), §6; R0-18 vollständig ab §2.4; R0-20 §1, Präfix- und Slug-Zeilen, D-20-01, D-20-03, D-20-11, OP-20-1; R0-24 §4.1, Zeilen R1-39 in §4.2/§4.3, D24-7, T-6; R0-36 A8, L1…L5, L12; R0-13 (Stufe 4, Diagnosepaket); R0-17 §4.3, PRIV-017, CS-01/CS-06; R0-11 N-08, ER-5; R0-12 C-15; R0-14 FE-07.
- LB @ `9f37c77` (nur gelesen): `bootspruefung-learning/readme.txt` vollständig (Kopf :1-7, Installation :41-61 mit `Update URI: false` und GitHub-Abfrage, Upgrade Notice :63-72, Abschnitt „Lizenz“ :89-93), `CREDITS.md` vollständig, `docs/lizenz.md` vollständig, `docs/release-regel.md` vollständig, `tools/release/` (Dateiliste; Inhalte über R0-18 §3.1).
- Suite `wp-license` @ `c269e07` (nur gelesen): `docs/RELEASE-READINESS.md` vollständig (Gates 1-6, Regel „Do not advertise …“, `Update URI`-Begründung in Gate 1).
- Nicht möglich: Abruf von `developer.wordpress.org` (Proxy: `connect_rejected`) → alle (MW)-Stellen ungeprüft (RC-001). Die Slug-Verfügbarkeit (OP-20-1) wurde bewusst nicht online abgefragt; die Prüfung bleibt Auftrag vor R1-01.
- Nicht erfolgt: keine Änderung an anderen Dateien, an LearnBoating oder den Suite-Klonen; keine Implementierung, kein Commit.
