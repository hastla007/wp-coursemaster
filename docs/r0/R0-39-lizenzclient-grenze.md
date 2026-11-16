# R0-39 · Free-/Pro-Lizenzclient-Grenze (Entwurf)

**Stand:** 16.11.2026 · **Status:** Entwurf, nicht abgenommen; Task R0-39 ist **nicht** abgehakt · **Bezug:** TODO R0-39 (DoD: „Core ohne Lizenzbibliothek, Updater, Lizenzserverzugriff; Add-on eigener Build/Namespace, Feature-Flags, Upgrade/Downgrade, Lizenzende und Ausfallverhalten spezifiziert“), Voraussetzungen R0-11, R0-19, R0-29; Folgetasks **R3-08**, **R3-15**, **R3-18** (Free-Teil der Grenze zusätzlich im Gate **R1-39**, R0-35 RC-020…022); Konzept Kap. 6, 7, 45; `R0-36` §5 (L1…L12), `R0-11` §1.2/§1.5/Flow 5, `R0-29` §3, `R0-18` §2.5/§3.5, `R0-19` §1.6/§4.2, `R0-35` §4/§5.11, `R0-16` U-9, `R0-27` (`pro.*`-Settings, SET-076/104/128).

**Quellvertrag (nur gelesen, nicht verändert):** `wp-license@c269e07` – `docs/CLIENT-INTEGRATION.md` (zitiert als **CI:Zeile**), `docs/ENTITLEMENTS.md` (**EN:**), `docs/UPDATES.md` (**UP:**), `docs/RELEASE-READINESS.md` (**RR:**), `bin/export-client.php`, `src/Catalog/EntitlementCatalog.php:129-133` (`PRODUCT_KEYS`), `examples/premium-addon-starter/README.md`; Free-Guard-Vorbild `wpaffiliatemaster/tests/run.php:332-363` (**A2**). `captain-pro` ist **nicht zugänglich**; alle Kernel-/Buildregistry-Aussagen aus Kap. 6 bleiben offene Annahmen (R0-36 §6).

**Verbindlich (Beschlussprotokoll 16.11.2026):** **E-01** Clean Room (kein LB-Code; LB-`learning_explanations`/`explanation_locked` dient nur als Verhaltensbefund). **E-09** Free über WordPress.org, Pro über License Captain. **E-20** Lösungserklärungen sind ein eigenes Pro-Feature; Free speichert die Texte trotzdem (Import/Export, kein Datenverlust bei Pro-Ende); Entitlement-Zuschnitt E-95 anpassen. **E-33/E-34** deutsche Shop-Rechtspflichten, PayPal/Klarna-Webhooks und automatisch gepflegte Woo-Produkte liegen im Woo-Adapter (Pro, R3.1).

**Arbeitsannahmen (Empfehlungen, noch nicht beschlossen):** **E-42** (Produkt `course-captain-pro`/Präfix `course.` vor R3-18 beauftragen), **E-53** (Woo-/Voucher-Connectoren Pro), **E-93** (nach Lizenzende laufen Grants und Widerrufe aus bestehenden Zuordnungen weiter), **E-94** (Lizenzende: Pro-Konfiguration wirkt eingefroren; Add-on deaktiviert: Free-Verhalten, Daten bleiben), **E-95** (Entitlements neu zuschneiden), **E-190** (Pro-ZIP-Signatur durch License Captain), **E-207** (Präfix `ccap_`), **E-213** (Pro unter GPL-2.0-or-later); außerdem E-07/E-49 (Single-Site), E-85 (Haltemodus), E-89 (Uninstall-Bestätigung), E-90 (Minor-Downgrade), E-100 (keine Pro-Schalter in Free).

**Normative Sprache:** **MUSS** = Vertragsbestandteil, **SOLL** = Standard, **KANN** = optional. Kein Code, keine Tests ausgeführt, kein Commit.

---

## 0. Kurzfassung

| Thema | Ergebnis |
|---|---|
| Free-Grenze | 12 prüfbare Regeln **FG-01…FG-12**; statischer CI-Guard (Quelltext J-10 und entpacktes ZIP J-17) mit 14 Musterklassen, dazu ein Laufzeit-Guard „Free sendet keine Requests“ (§1). |
| Pro-Add-on | Eigener Ordner/Slug/Textdomain/Produktschlüssel `course-captain-pro`, Namespace `CourseCaptainPro\`, Client per `export-client.php` unter `CourseCaptainPro\Licensing`, `Update URI` Pflicht, `Requires Plugins: course-captain`, `core_version` als Closure (§2). |
| Feature-Registry | **17 Entitlement-Schlüssel** nach E-95 und E-20 (neu: `course.explanations`); `drip_content`, `certificates`, `groups` entfallen, weil sie Free-Funktionen abdecken (§2.4). |
| Zustandsmodell | Elf Client-Zustände von License Captain auf **drei Verhaltensklassen** abgebildet: **V** (voll), **E** (eingefroren nach E-93/E-94), **A** (Add-on aus, Free-Fallback). „Server down“ ist **kein** Zustand: Der signierte Snapshot gilt bis `cache_expires_at` (≤ 14 Tage), danach `stale` → E (§3). |
| Wichtigste Korrektur | R0-11 §1.5/Flow 5 und R0-29 §3.1 (L2) setzen `grace` mit „Server nicht erreichbar“ gleich. Im Quellvertrag bedeutet `grace` **Zahlung überfällig, Zugang bis `access_until`** (CI:188). Offline bleibt der zuletzt signierte Zustand bestehen (CI:208, :214). Deltas in §8. |
| Datenintegrität | Fünf Invarianten **DI-1…DI-5**, Übergangstabelle für 16 Übergänge (Upgrade, Update, Downgrade, Deaktivierung, Deinstallation, Reaktivierung, Umzug, Staging, Restore) (§4). |
| Externe Blocker | 9 Blocker **XB-1…XB-9**; E-42 ist über die Shop-Filter `wplc_known_product_keys`/`wplc_entitlement_catalog` ohne Codeänderung am License-Server lösbar, braucht aber trotzdem Katalogpflege und einen Auftrag. E-190 trifft den realen Mechanismus nicht wörtlich (§5). |
| Tests | **LIC-001…LIC-086** (65 Fälle) mit Ziel R3-08 (20), R3-15 (5) und R3-18 (32); den Free-Teil prüft zusätzlich R1-39 (8 nur dort, 2 doppelt) (§6). |
| Entscheidungen | 7 neue Punkte **D39-1…D39-7** (§7). |

---

## 1. Harte Grenze Free (`course-captain`)

### 1.1 Regeln

| Nr. | Regel (MUSS) | Prüfung | Beleg |
|---|---|---|---|
| **FG-01** | Free enthält **keine Lizenzbibliothek**: keine Kopie von `LicenseManager`, `LicenseClient`, `LicenseStatus`, `Updater`, `OptionStore` und `Messages` aus `wp-license/src/Client`, auch nicht unter anderem Namespace, und keine eigene Implementierung desselben Zwecks. | Guard G-1, G-2 | CI:7, EN:5-11, R0-36 L1 |
| **FG-02** | Free hat **keinen Updater**: kein `Update URI`-Kopf, keine Hooks auf `pre_set_site_transient_update_plugins`, `site_transient_update_plugins`, `update_plugins_{host}`, `plugins_api`, `upgrader_pre_download`, `upgrader_source_selection`, keine GitHub-Release-Abfrage. Updates kommen nur über WordPress.org. | G-4…G-6 | E-09, WordPress.org-Richtlinie 8 (MW), R0-18 §2.5 |
| **FG-03** | Free greift **nie auf einen Lizenzserver zu**: keine Route `wplc/v1`, keine Optionen, Hooks, Cron-Events oder Konstanten mit `wplc_`/`WPLC_`, kein Host eines Lizenzservers. | G-3, Laufzeit-Guard LG | R0-36 L1, A2 |
| **FG-04** | Free fragt **nie ein Entitlement oder einen Lizenzstatus ab**, weder direkt noch indirekt: kein `is_entitled`, `allows_entitlement`, `entitlement(`, kein `license_key`/`licence_key`, kein Lesen von `course_captain_pro_license*`, kein `class_exists`/`function_exists` auf `CourseCaptainPro\…` oder `course_captain_pro_*`. | G-7, G-8, G-9 | EN:9, ER-2 (R0-11) |
| **FG-05** | Free-Verhalten hängt **nie von Pro** ab. Eine Free-Funktion verhält sich mit Pro (V, E, A) und ohne Pro gleich (ER-1). Pro verändert Free nur über die Erweiterungspunkte aus R0-19 §4.2 (`course_captain_register_extensions` mit Slots, `…_register_connectors_v1`, `…_register_activity_providers`) und über die PHP-API v1. | Prüfsumme DI-1, LIC-041/043 | R0-19 §1.6, §4.2; R0-29 Grundregel |
| **FG-06** | Free zeigt **keine Lizenzinformation** an. Lizenzbezogene Texte (Banner „Pro abgelaufen“, Systemstatus „Pro aktiv“, R0-29 Z-22) liefert ausschließlich Pro über die Slots `diagnostics`, `readinessChecks` und `settingsTabs`. Läuft kein Pro-Code, zeigt Free höchstens den neutralen Hinweis aus FG-11. | Review + LIC-012 | E-100, ER-5 |
| **FG-07** | Free verwendet das Wort **„entitlement“ nicht** (Code, Optionen, Hooks, Fehlercodes, REST-Felder). Lernzugänge heißen `grant`. Das erlaubt den breiten A2-Guard `/entitlement/i` ohne Ausnahmeliste. Folge: R0-19 §5.8 `entitlement_revision` → `grant_revision` (Delta Δ-4). | G-7 | A2 (`run.php:340`), Kap. 6/45 |
| **FG-08** | Free enthält **keine Pro-Strings**: keine Textdomain `course-captain-pro`, keine Erklärungs-Auslieferung (E-20, §2.5), keine gesperrten Pro-Felder. Erlaubt ist der Produktname „Course Captain Pro“ auf der ausblendbaren Seite „Erweiterungen“ (E-100, N-08). | G-10 | E-20, E-100 |
| **FG-09** | Free sendet **keine ausgehenden HTTP-Requests** an Dritte, außer an ausdrücklich konfigurierte Ziele (z. B. Mail über `wp_mail`). Ausgehende Webhooks sind Pro (`course.webhooks`). | Laufzeit-Guard LG | R0-36 §5.3 (ZF6-Vorbild), E-210 |
| **FG-10** | Free-Uninstall löscht **nur eigene Namen**: exakte Optionsnamen aus dem Uninstall-Manifest bzw. `course_captain_settings_*`, nie das Muster `course_captain_%`. Begründung: Der License-Client legt mit `option_prefix = course_captain_pro` die Optionen `course_captain_pro_license`, `…_license_state`, `…_license_pending`, `…_update_meta` an (CI:124, :206, :261, :310); ein Platzhalter-Löschen würde Aktivierung und Snapshot zerstören. | LIC-063 | R0-16, E-89 |
| **FG-11** | Free führt eine **neutrale, persistente Erweiterungsliste** (`course_captain_extensions_seen`: Slug, Name, Version, „hat Daten“, zuletzt gesehen). Eine Erweiterung trägt sich über die Registry ein; Free speichert **keinen Lizenzstatus**. Damit kann Free ohne laufenden Pro-Code „Erweiterung inaktiv, Daten vorhanden“ anzeigen (R0-29 Z-22, L4/L5). | LIC-012 | ER-5, R0-29 §3.2 |
| **FG-12** | Free kennt Pro-Tabellen nicht namentlich. Neutrale Free-Felder, die nur Pro beschreibt (z. B. `cc_group.parent_group_id`, Delegationen, R0-29 Δ-1/GR-3), sind ohne Pro **wirkungslos, nicht gelöscht**. | DI-1, R0-29 GRP-045/046 | R0-29 GR-3 |

### 1.2 Statischer CI-Guard (Free)

Erweitert R0-18 §2.5 (dort 7 Musterzeilen) um G-7 (breit wie A2), G-8, G-9, G-11 und G-12. Läuft in **J-10** auf `course-captain.php`, `uninstall.php`, `src/`, `assets/`, `blocks/`, `templates/`, `languages/*.pot` und der Build-Ausgabe, in **J-17** auf dem entpackten Release-ZIP. Dateitypen: `php`, `js`, `json`, `pot`, `txt`. Treffer = rot.

| ID | Muster (PCRE) | Grund |
|---|---|---|
| G-1 | `/\b(LicenseClient\|LicenseManager\|LicenseStatus\|OptionStore)\b/` | keine Clientkopie (FG-01) |
| G-2 | `/WPLC\\\\Client\|CourseCaptainPro\\\\Licensing\|Captain\\\\Licensing/` | kein vendorter Client (FG-01) |
| G-3 | `#wplc/v1#`, `/\bwplc_/i`, `/\bWPLC_[A-Z_]+/` | kein Lizenzserver (FG-03) |
| G-4 | `/Update URI\s*:/i` | keine eigene Update-Quelle (FG-02) |
| G-5 | `/pre_set_site_transient_update_plugins\|['"]site_transient_update_plugins['"]\|update_plugins_\|['"]plugins_api['"]\|upgrader_pre_download\|upgrader_source_selection/` | kein Updater (FG-02) |
| G-6 | `#api\.github\.com\|github\.com/[^'"]+/releases#` | keine GitHub-Abfrage (E-09) |
| G-7 | `/entitlement/i` | kein Entitlement (FG-04, FG-07; wie A2) |
| G-8 | `/license_key\|licence_key\|is_entitled\|license_status\|cache_expires_at/i` | kein Lizenzschlüssel, kein Status (FG-04) |
| G-9 | `/CourseCaptainPro\\\\\|course_captain_pro_\|COURSE_CAPTAIN_PRO_/` | kein Probing des Add-ons (FG-04) |
| G-10 | `/['"]course-captain-pro['"]\s*\)/` (Textdomain-Argument) | keine Pro-Strings (FG-08) |
| G-11 | `/delete_option\s*\(\s*['"]course_captain_pro/`, `/LIKE\s+['"]course_captain_%/i` | Uninstall greift nicht auf Pro-Optionen (FG-10) |
| G-12 | `/feedback_explanation\|show_explanations/` außerhalb von `src/Domain/Question/` (Speicher, Import, Export) | Erklärungen werden in Free nur gespeichert, nie ausgeliefert (E-20, §2.5) |
| G-13 | Plugin-Kopf von `course-captain.php` enthält **kein** `Requires Plugins` | Free hängt von nichts ab |
| G-14 | Legacy- und Negativliste aus R0-18 §3.2 | unverändert |

**Ausnahmen:** nur über `tools/qa/free-guard-allow.txt` (Pfad, Muster-ID, Begründung, Ablaufdatum), Änderung nur mit CODEOWNERS-Review (Security). Für G-7 ist **keine** Ausnahme zulässig (FG-07). Neutrale Erweiterungs-Hooks heißen `course_captain_*` und nie `*_license_*`/`*_entitlement*`.

**Selbsttest des Guards (LIC-010):** Ein Fixture-Verzeichnis mit je einer Treffer-Datei pro Muster muss 14 Treffer liefern; ein sauberes Fixture null. So bleibt der Guard nicht stillschweigend wirkungslos, etwa nach einer Pfadänderung.

### 1.3 Laufzeit-Guard (Free)

- **LG-1:** In PHPUnit-Integration und Playwright-E2E (R1) hängt ein Test-MU-Plugin an `pre_http_request` und **schlägt fehl** bei jedem Request, der nicht auf der Allowlist steht (leer; WordPress-Core-eigene Update- und Übersetzungsabfragen sind über `WP_HTTP_BLOCK_EXTERNAL` blockiert und zählen nicht). Abgedeckt: Aktivierung, Setup-Assistent, Kurs anlegen/veröffentlichen, Lernfluss, Prüfung, Zertifikat, Import, Cron-Läufe `cc_hourly`/`cc_migrate`, Uninstall.
- **LG-2:** Dieselbe Suite läuft mit installiertem, aber **nicht aktiviertem** und mit **aktiviertem, unlizenziertem** Pro-Add-on. Free-Ergebnisse (Statuscodes, Antworten ohne Zeitstempel und IDs, Free-Prüfsumme DI-1) bleiben gleich (FG-05).

---

## 2. Pro-Add-on (`course-captain-pro`)

### 2.1 Identität und Build

| Punkt | Festlegung (MUSS, wo nicht anders angegeben) | Beleg |
|---|---|---|
| Produktschlüssel = Ordner = Textdomain | `course-captain-pro`; nie der Schlüssel des Free-Kerns | CI:123, Kap. 6 |
| PHP-Namespace | `CourseCaptainPro\` (Features), Client unter `CourseCaptainPro\Licensing` | R0-35 §4 |
| Client-Import | `php bin/export-client.php --target=course-captain-pro/includes/licensing --namespace='CourseCaptainPro\Licensing' --text-domain=course-captain-pro` im Build aus einem **gepinnten** `wp-license`-Commit (aktuell Client `2.0.2`). Ergebnis wird committet, nie von Hand editiert. Bibliotheks-Update = Export erneut ausführen und Pro releasen | CI:29-36, `export-client.php:3-15`, R0-36 L2 |
| Unveränderte Kopie | verboten (nur für Experimente gedacht; die zuerst geladene Kopie gewinnt) | CI:38-44 |
| Optionspräfix | `course_captain_pro` (Default aus dem Produktschlüssel); Pro-Settings im Register als `pro.*` (R0-27) gespeichert unter `course_captain_pro_settings_*` | CI:124, R0-27 |
| Tabellen, Caps, Cron | eigene Präfixe `ccap_pro_*` (Tabellen), Cron-Hooks `ccap_pro_*` (E-207-Annahme); der License-Client legt zusätzlich `course_captain_pro_license_check` an | E-207, CI:323 |
| REST | eigener Namespace `course-captain-pro/v1`; Fehlercode `cc_pro_license_required` (403) gehört in den **Pro**-Fehlerkatalog, nicht in den Free-Katalog R0-19 §3 (Δ-5) | R0-11 F5.5 |
| Plugin-Kopf | `Plugin Name: Course Captain Pro`, `Version` = `version` in `license-config.php` (sonst lehnt der Release-Server ab), `Requires at least`/`Requires PHP` = Free-Minimum (R0-20), `Text Domain: course-captain-pro`, **`Update URI: https://<license-host>/course-captain-pro`**, **`Requires Plugins: course-captain`** (Slug nach E-205), `License: GPL-2.0-or-later` (E-213) | CI:119-125, Starter-README |
| ZIP | genau ein Ordner `course-captain-pro/`; Release über `wp wplc release create --product=course-captain-pro …` oder Manifest (`tools/create-release-manifest.php`), `requires_core` = niedrigste unterstützte Free-Version (§2.6) | CI:361-365, UP:127-166 |
| Build-Ort | offen: Kap. 6 nennt `captain-pro/addons/course-captain-pro/`, License `CLIENT-REPOS.md:23` ein eigenes Repository → **D39-6** | R0-36 §6/§7.2 |
| Multisite | `network => false` (je Site, R0-36 D4); offiziell Single-Site (E-07) | CI:287-304 |

### 2.2 Laden und Abhängigkeit von Free

1. **Ladereihenfolge:** WordPress sortiert `active_plugins` als Zeichenkette; `course-captain-pro/…` steht **vor** `course-captain/…` (`-` < `/`). Pro wird also **vor** Free geladen. Pro MUSS daher beim Laden der Hauptdatei nur `LicenseManager::register()` aufrufen und darf weder Free-Klassen noch Free-Funktionen berühren.
2. **Handshake:** Pro registriert Features erst in `course_captain_loaded_v1` (`plugins_loaded` 20, R0-19 §1.6) bzw. für Slots in `course_captain_register_extensions` (`init` 5). Vorher prüft Pro: `function_exists('course_captain_contract_version')`, `version_compare(course_captain_contract_version(), …)` gegen den eigenen `contractRange` (z. B. `>=1.0 <2.0`), `COURSE_CAPTAIN_VERSION >= CORE_MIN` und `course_captain_supports()` für jedes benötigte Merkmal (z. B. `grants.write.v1`, `events.v1`).
3. **Fehlschlag (R0-11 F5.1):** Ist Free nicht aktiv, zu alt oder außerhalb des Vertragsbereichs, lädt Pro **keine** Feature-Module. Es bleiben nur die Lizenzseite und ein Admin-Hinweis mit der nötigen Version. Pro wirft keinen Fatal Error; die Free-Daten bleiben unberührt.
4. **`core_version`:** Pro übergibt eine **Closure**, die `defined('COURSE_CAPTAIN_VERSION') ? COURSE_CAPTAIN_VERSION : ''` liefert. Sie wird erst beim Update-Check ausgewertet, also nach dem Laden von Free (Punkt 1). Der Server hält Releases mit höherem `requires_core` zurück und nennt den Grund in der Plugin-Zeile (CI:155, :317; UP:54, :149).
5. **`Requires Plugins`:** verhindert in WordPress ab 6.5 die Aktivierung von Pro ohne Free und das Deaktivieren bzw. Löschen von Free, solange Pro aktiv ist (MW). WP-CLI, FTP oder ein Restore können das umgehen; Punkt 3 bleibt deshalb Pflicht.
6. **Lizenzabfrage:** `is_entitled()` erst ab `init` (CI:94-105); es liest eine Option und sendet nichts (CI:169). Kein Lizenzaufruf im Frontend-Request (Kap. 6).

### 2.3 Lizenzseite, Hinweise, Datenschutz

- **Ort:** Untermenü „Pro-Lizenz“ im Course-Captain-Menü (`settings_parent => false`, eigene Seite ruft `render_settings()`, `settings_url` gesetzt; Hook `course_captain_admin_menu_built_v1`) (CI:156-157, R0-27 K43-52/SET-128).
- **Recht:** `manage_options` (Library-Default). Die Lizenz ist eine Site-Einstellung; `cc_*`-Caps reichen nicht (E-83: nur `administrator` erhält Caps).
- **Hinweise** kommen aus der Library (`grace`, `expired`, `suspended`, `revoked`, `inactive`, `deactivated`, `site_changed`, `stale`, `misconfigured`, Server länger als 3 Tage nicht erreichbar, CI:322). Zusätzlich zeigt Pro ein **Course-spezifisches Banner** in Pro-Bereichen (Z-P, R0-29 GR-6) und einen Eintrag im Slot `diagnostics` mit der Liste der eingefrorenen Regeln.
- **Datenschutz:** `privacy_text()` der Library wird übernommen und um den Satz ergänzt: „Es werden keine Kurs-, Lern-, Prüfungs- oder Teilnehmerdaten gesendet.“ Gesendet werden nur die Felder aus CI:346. Prüfung: LIC-085.
- **Konfigurationswerte** (`server_url` mit Konstante `COURSE_CAPTAIN_PRO_LICENSE_SERVER`, `public_keys` gepinnt, `account_url`, `update_uri`, `core_version`, `channel = stable`) stehen in `license-config.php`, die Hauptdatei und `uninstall.php` gemeinsam nutzen (CI:50-66). Release-Builds definieren **nie** `COURSE_CAPTAIN_PRO_LICENSE_DEV` (CI:136; Pro-Guard PG-5).

### 2.4 Feature-Registry und Entitlement-Schlüssel (E-95, E-20)

**Registry (Pro-intern, kein Free-Vertrag):** `CourseCaptainPro\Features\Registry` mit einem Eintrag je Feature: `id`, `entitlement_key`, `module` (Klasse), `requires_core_features[]` (für `course_captain_supports()`), `settings_tab`, `frozen_policy` (Verweis auf die Zeile in §3.3), `owns_tables[]`, `uninstall_manifest`. Regeln:

- **R-1** Jeder Schlüssel gehört zu **genau einem** Feature (Kap. 6). Ein Schlüssel wird nie für anderes Verhalten wiederverwendet (EN:62).
- **R-2** Kein Schlüssel deckt eine Free-Funktion ab (ER-2). Deshalb entfallen `course.drip_content` (einfacher Drip ist Free, E-14), `course.certificates` (Basisnachweis ist Free, E-96) und `course.groups` (Gruppen sind Free, E-17).
- **R-3** Freigabe nur über `is_entitled($key)` (fail closed: fehlender Schlüssel, falscher Typ oder `access=false` bedeutet „nicht freigegeben“, EN:31). Nie über `license_status`, Planname oder Label (CI:200, EN:31).
- **R-4** **Modul laden ≠ Feature freigeben.** Ein Modul wird geladen, wenn (a) `is_entitled($key)` gilt **oder** (b) Pro-Daten dieses Features existieren (Konfiguration, Zuordnungen, laufende Jobs). Im Fall (b) läuft es im eingefrorenen Modus E (§3). Der eingefrorene Modus hängt also an **vorhandenen Daten** und nicht an einer gemerkten, unsignierten Schlüsselliste: Bei `access=false` ist `entitlements` leer (EN:15), und eine lokal gemerkte Liste wäre manipulierbar.
- **R-5** Nur implementierte Schlüssel kommen in den Katalog (Kap. 6). Die Reihenfolge der Freischaltung folgt den R3-Tasks.
- **R-6** Alle Schlüssel sind in **jedem Plan** freigegeben (Kap. 6). Entfernt ein Planwechsel einen Schlüssel (Katalogpflege, EN:63), gilt für dieses Feature E.

| Nr. | Schlüssel | Feature (Pro) | Ersetzt / neu | Free-Gegenstück (bleibt) | Task |
|---|---|---|---|---|---|
| K-01 | `course.explanations` | Auslieferung der Lösungserklärungen (Lernmodus, Prüfungsergebnis) nach E-20 und E-147; Einstellungen `pro.explanations.*` | **neu** (E-20) | Richtig/falsch, richtige Antwort nach E-147; Speichern, Import und Export der Texte | R3-08 |
| K-02 | `course.advanced_paths` | kursübergreifende Lernpfade | Kap. 6 | kursinterne Voraussetzungen (E-101) | R3-08 |
| K-03 | `course.drip_campaigns` | absolute Termine, Kampagnen, Gruppen-Drip | ersetzt `drip_content` | relativer Drip (E-14) | R3-08 |
| K-04 | `course.advanced_assessment` | Pro-Fragetypen, Rubriken, Fragezweige, Szenarien, Retake-Programme, `per_option`, `after_release`, Tutorbewertung, Druck-/PDF-Prüfungsbögen | Kap. 6 | Fragetypen R1/R2, Prüfungsregeln | R3-08 |
| K-05 | `course.gradebook` | Gradebook, manuelle Noten, Nachbewertungs-UI (E-74) | **neu** (N-03) | Datenvertrag der Nachbewertung (E-74) | R3-08 |
| K-06 | `course.certificate_designer` | Designer, mehrere Vorlagen, getaggtes PDF, Sammel-Neuausstellung, mehrsprachige Vorlagen | ersetzt `certificates` | Basisnachweis, Verifikation (E-96) | R3-08 |
| K-07 | `course.groups_advanced` | Delegierte, Untergruppen, Umbuchen, Massenimport/-aktionen, Gruppenberichte | ersetzt `groups` | Gruppen und Besitzer-Selbstverwaltung (E-17) | R3-08 |
| K-08 | `course.reminders` | Ablauf- und Lernerinnerungen (E-24) | **neu** | Status- und Ablaufanzeige, Mail „Zugang abgelaufen“ (E-217) | R3-08 |
| K-09 | `course.analytics` | Kohorten, Zeitreihen, gespeicherte Auswertungen, Berichts-CSV | Kap. 6 | Berichte Basic (E-98) | R3-08 |
| K-10 | `course.commerce` | Woo-Adapter R3.1 einschließlich **E-33** (Widerrufsbutton, Bestellknopf, Preisangaben, Rechtstexte in Mails, OSS, PayPal-/Klarna-Webhooks), **E-34** (Auto-Produkte und Sync), E-25a (Mitgliederrabatt); Voucher-Adapter R3.2 (E-31, zwei Zieltypen) | Kap. 6 | neutrale Grant-API, GrantSourceAdapter | R3-02…R3-06 |
| K-11 | `course.suite_integrations` | Connectoren Workflow/Form/Review/Pass/Affiliate/License (R3.4) | Kap. 6 | Events, Read-API, Connector-Registry (E-38) | R3-07 |
| K-12 | `course.webhooks` | ausgehende Webhooks | Kap. 6 | Outbox, Events (E-38) | R3-08 |
| K-13 | `course.custom_activities` | zusätzliche Activity-Typen | Kap. 6 | Basis-Activities | R3-08 |
| K-14 | `course.frontend_builder` | Frontend-Kurs-Builder | **neu** (N-03) | Admin-Editor | R3-08 |
| K-15 | `course.multi_instructor` | mehrere Trainer je Kurs | **neu** (N-03) | Einzel-Eigentümer, `cc_trainer` (E-99) | R3-08 |
| K-16 | `course.advanced_roles` | erweiterte Adminrollen, delegierte Rechte | **neu** (N-03) | Rollen R1 (R0-07) | R3-08 |
| K-17 | `course.content_workflows` | geplante Veröffentlichung, mehrstufige Freigabe | **neu** (N-03) | Publish-Workflow R1 (R0-22) | R3-08 |

Format nach `EntitlementCatalog::KEY_PATTERN` (`^[a-zA-Z0-9_.-]{1,120}$`) und EN:61 (Kleinbuchstaben, snake_case nach dem Präfix). Werte: `true` (Feature) oder nichts. Zahlenlimits sind nicht vorgesehen; Site-Limits sind Lizenzlimits und keine Features (EN:22-25, :65).

### 2.5 Erklärungen (E-20) technisch

- **Speicher (Free):** Erklärungstexte (`feedback_correct`/`feedback_incorrect`, R0-08 §4.5) bleiben Teil der Free-Fragenrevision, des Snapshots, des Imports und des Exports. Ein Bearbeiten in Free darf sie nie verwerfen (E-20, DI-1).
- **Auslieferung (nur Pro):** Free-Antworten (REST R-xx des Lernmodus und des Ergebnisses, R0-19) enthalten **kein** Erklärungsfeld, auch kein leeres und kein `explanation_locked` (E-100; LB-Befund wird nicht übernommen). Pro ergänzt `explanation` über den Slot `responseFields`, wenn K-01 nach §3.3 aktiv ist und die eingefrorenen Werte `feedback_visibility`/`show_explanations` des Versuchs-Snapshots es erlauben (E-147).
- **Einstellung:** `show_explanations` wandert aus dem Free-Register in das Pro-Register (`pro.explanations.show`, Kurs/Prüfungsregel überschreibbar; Δ-3). Free behält `feedback_visibility`, `show_score` und `show_correct_answers`.
- **Editor:** Sichtbarkeit des Erklärungsfelds im Free-Frageneditor → **D39-2**. Verhalten bei Lizenzende → **D39-1**.

### 2.6 Aktivierung, Deaktivierung, Kompatibilitätsfenster

| Vorgang | Verhalten (MUSS) | Beleg |
|---|---|---|
| Plugin aktivieren | Pro legt eigene Tabellen an (`ccap_pro_*`, eigene Schema-Version `course_captain_pro_db_version`, Migration nach R0-37-Muster). **Keine** Migration von Free-Daten. Ohne Lizenz ist nur die Lizenzseite sichtbar. | R0-11 Flow 5 |
| Lizenz aktivieren | `activate($key, $environment)` mit Wiederverwendung, Pending-Installation und Staging-Eltern (CI:255-277). Ohne gültige signierte Antwort keine Freischaltung (R0-11 F5.3). Danach werden Module nach R-4 geladen. Ein Seiten-Reload genügt. | CI:257-263 |
| Lizenz freigeben („von dieser Site entfernen“) | `deactivate()`, lokal gelöscht erst nach signierter Bestätigung (CI:285). Danach Zustand `deactivated` → E | CI:283-285 |
| Plugin deaktivieren | **Wirkungsbericht** vor der Bestätigung (R0-11 Flow 5): betroffene Pfade, Drip-Kampagnen, Pro-Activities, offene Tutorbewertungen, Connector-Zuordnungen, **Auto-Produkte (E-34) → D39-4**. Danach: Pro-Cron ausgetragen (`course_captain_pro_license_check` entfernt die Library selbst, CI:323), laufende Pro-Jobs pausiert (Cursor bleibt), Free-Fallback A. Die Aktivierung auf dem Server bleibt bestehen. | R0-29 Z-10/Z-20 |
| Plugin deinstallieren | Uninstall-Seite mit Option „Pro-Daten löschen“ (Standard **aus**, E-89-Muster mit Site-Namen). Immer: `LicenseManager::uninstall()` (gibt die Aktivierung frei und löscht Lizenzdaten, CI:336-338). Pro-Daten nur auf ausdrücklichen Wunsch (L5D). Änderungen an Free-Tabellen (R0-29 Z-03/Z-04) **nur über Free-Domänendienste**. Ist Free dabei nicht aktiv, löscht Pro nur eigene Tabellen und hinterlegt in `course_captain_extensions_seen` einen Bereinigungsauftrag, den Free beim nächsten Laden über seine Dienste ausführt (FG-11, FG-12). | R0-29 §3.1, CI:336 |
| Kompatibilitätsfenster | Pro-Release N gibt `requires_core` und `contractRange` an. Pro nutzt experimentelle Slots (R0-19 §1.3), deshalb werden Free-Minor und Pro gemeinsam getestet. Welche Free-Versionen unterstützt werden → **D39-7**. | R0-16 U-9, UP:149 |

### 2.7 Pro-CI-Guard (Gegenprobe im Pro-Repository, R3-18)

| ID | Prüfung | Beleg |
|---|---|---|
| PG-1 | Kopf hat `Update URI`; der Host ist der stabile Lizenz-Host und nicht `wordpress.org`. `Version` = `license-config.php` `version` | CI:121-122 |
| PG-2 | Client liegt unter `includes/licensing/`, Namespace `CourseCaptainPro\Licensing`, kein `WPLC\Client` (Prüfung wie `export-client.php`), `LicenseManager::VERSION` = gepinnte Version, Dateien byteweise gleich dem Export aus dem gepinnten Commit | `export-client.php`, CI:29-44 |
| PG-3 | Kein Free-Code kopiert: kein `namespace CourseCaptain\` (ohne `Pro`), keine Free-Klassen- oder Funktionsdeklarationen `course_captain_*_v1` | R0-18 §2.5 |
| PG-4 | Keine direkten Schreibzugriffe auf Free-Tabellen (`$wpdb->insert\|update\|delete\|query` auf `cc_*`/`ccap_*` außer `ccap_pro_*`); Schreiben nur über PHP-API v1 und die Dienste, die `course_captain_loaded_v1` übergibt (R0-29 CRT-040 verallgemeinert) | R0-29 Grundregel |
| PG-5 | Kein `define( 'COURSE_CAPTAIN_PRO_LICENSE_DEV'` und kein Filter `course_captain_pro_license_public_keys` im Release-Build; `public_keys` nicht leer | CI:136 |
| PG-6 | Kein `wp_remote_*` außerhalb von `includes/licensing/`, Webhook-Zustellung und Connector-Transport (Allowlist) | §2.3 |
| PG-7 | Kein `is_entitled()` vor `init` und kein Lizenzaufruf im Frontend-Pfad (statisch: Aufrufer-Analyse; dynamisch: LIC-026) | CI:94-105 |
| PG-8 | Jeder Registry-Schlüssel steht in §2.4 und im Katalog-Export; keine Schlüssel doppelt | R-1, R-5 |

---

## 3. Zustandsmodell Lizenz × Verhalten

### 3.1 Abbildung der Client-Zustände (CI:182-198)

„Server down“ und „offline“ sind **keine** Zustände. Die Library hält den letzten signierten Zustand bis zum signierten `cache_expires_at` (14 Tage nach der Antwort, nie über `access_until`, CI:208, EN:35-44). Fehler ohne Signatur entziehen keine Rechte (`keep`, CI:214-221). Nach Ablauf des Fensters ist der Zustand `stale`.

| Client-Zustand | `access` | Bedeutung (Quelle) | Klasse | Hinweis in Pro |
|---|---|---|---|---|
| `active` | ja | bezahlt (CI:187) | **V** | – |
| `grace` | ja | Verlängerung überfällig, Zugang bis `access_until` (CI:188) | **V** | Library-Hinweis mit `account_url`; Course-Banner „Verlängerung offen bis …“ |
| *(offline ≤ 14 d)* | wie zuletzt signiert | Server nicht erreichbar, Snapshot gültig | wie zuletzt (V) | ab Tag 3 Library-Hinweis mit Abschaltdatum (CI:322) |
| `stale` | nein | Offline-Fenster aufgebraucht oder Snapshot ungültig bzw. manipuliert (CI:195, :207) | **E** | „Lizenz konnte nicht geprüft werden“; Neuprüfung per Cron alle ≤ 15 min (CI:209) |
| `expired` | nein | Zahlungs- und Gnadenfrist vorbei (CI:189) | **E** | Z-P-Banner, Verlängerungslink |
| `suspended` | nein | vom Shop gesperrt (CI:190) | **E** (→ D39-3) | Banner ohne Kauflink |
| `revoked` | nein | endgültig beendet (CI:191) | **E** (→ D39-3) | Banner |
| `inactive` | nein | `pending` u. a. (CI:192) | **E** | Banner |
| `deactivated` | nein | Aktivierung signiert freigegeben (CI:193) | **E** | Schlüssel neu eingeben |
| `site_changed` | nein | andere Adresse (Kopie/Umzug), **kein Request** (CI:194, :273) | **E** + Free-Haltemodus (E-85) | „Diese Adresse aktivieren / Umzug übertragen“ |
| `unlicensed` | nein | nie aktiviert (CI:186) | **E**, falls Pro-Daten existieren, sonst nur Lizenzseite | Dashboard- und Plugin-Hinweis (CI:322) |
| `misconfigured` | nein | Server, Produktschlüssel oder Public Key unbrauchbar (CI:196) | **E** | Diagnose, Supportfall |
| *Add-on deaktiviert/deinstalliert* | – | kein Pro-Code | **A** | Free-Hinweis FG-11 |
| *Free fehlt/inkompatibel* | – | §2.2 Punkt 3 | **A** (Pro lädt keine Module) | Pro-Hinweis mit Version |

### 3.2 Verhaltensklassen

| Klasse | Pro-Code läuft | Neue Pro-Konfiguration/-Zuordnungen | Bestehende Pro-Konfiguration | Käuferschützende Vorgänge (E-93) | Pro-Jobs | Pro-Daten |
|---|---|---|---|---|---|---|
| **V** | ja | ja | wirkt | laufen | laufen | lesen/schreiben |
| **E** | ja | **gesperrt** (403 `cc_pro_license_required`, Eingaben bleiben im Formular, R0-11 F5.5) | **wirkt eingefroren weiter** (E-94) | **laufen weiter** (Grant nach Zahlung, Widerruf nach Erstattung, Gutscheineinlösung, Reconcile, Abschluss offener Tutorbewertungen) | laufende enden regulär, neue nur, wenn sie zu E-93 gehören | lesen und exportieren |
| **A** | nein | – | wirkt nicht; Free-Fallback (E-94) | keine (keine Hooks), Nachholen per Reconcile nach Reaktivierung | pausiert (Cursor bleibt) | bleiben unverändert in der DB |

Für alle Klassen gilt: Free-Funktionen, Lernhistorie, Grants, Versuche, ausgestellte Zertifikate und Verifikation bleiben unverändert (CI:202, EN:11, CORE-10). Pro-Daten werden durch keinen Zustandswechsel gelöscht. Übergang E → V erfolgt ohne Neuaktivierung bei der nächsten signierten Antwort (täglicher Check oder „Jetzt prüfen“).

### 3.3 Verhalten je Pro-Funktion

Ergänzt R0-11 §1.5 (Spalten dort: `expired` gesperrt/bleibt, ohne Add-on) und R0-29 §3.2 um die Klassen und die neuen Schlüssel. Wo R0-11/R0-29 eine Zeile bereits vollständig regeln, steht hier nur der Verweis.

| Schlüssel | V | E (eingefroren) | A (Add-on aus) |
|---|---|---|---|
| K-01 `explanations` | ausgeliefert nach Snapshot-Einstellung | → **D39-1** (Empfehlung: weiter ausliefern, Einstellungen gesperrt) | nicht ausgeliefert; Texte bleiben gespeichert, Free zeigt richtig/falsch und Lösung nach E-147 |
| K-02 `advanced_paths` | voll | Pfade lesbar, Regeln wirken, Ändern 403 | Pfadsperren entfallen, Zugang folgt den Grants (R0-11) |
| K-03 `drip_campaigns` | voll | Regeln wirken eingefroren | nur Free-Drip; bereits freie Module bleiben frei (R0-29 Z-06) |
| K-04 `advanced_assessment` | voll | Pro-Fragen bleiben spielbar, laufende Versuche enden regulär; neue Pro-Fragen und Veröffentlichen mit neuen Pro-Typen 403 | Pro-Fragen übersprungen bzw. Prüfung `cc_exam_not_startable` (R0-11) |
| K-05 `gradebook` | voll | lesend, CSV des letzten Stands; offene Bewertungen dürfen abgeschlossen werden (E-93) | unsichtbar, Daten bleiben |
| K-06 `certificate_designer` | voll | Ausstellung mit zuletzt veröffentlichter Vorlage; PDF-Download bleibt (R0-29 Z-14/Z-16) | Basisvorlage, Fallback-Renderer (R0-29 Z-13) |
| K-07 `groups_advanced` | voll | Delegierte lesen, schreiben 403 (R0-29 Z-03) | Delegationen wirkungslos, Untergruppen eigenständig (R0-29 Z-03/Z-04) |
| K-08 `reminders` | Versand | **kein neuer Versand** (Erinnerung ist kein käuferschützender Vorgang); Abmeldelinks bleiben gültig | kein Versand; Abmeldeseite bleibt Free-Route |
| K-09 `analytics` | voll | letzter Stand lesbar und exportierbar | unsichtbar; Berichte Basic bleiben |
| K-10 `commerce` | voll | **E-93:** bestehende Zuordnungen erzeugen Grants und Widerrufe, Reconcile läuft. **E-33-Rechtspflichten laufen vollständig weiter**, solange Auto-Produkte kaufbar sind (sonst verkauft der Shop ohne Pflichtangaben). **E-34-Sync:** keine neuen Produkte, keine Preis- oder Zuordnungsänderung; Produkte bleiben wie sie sind | keine Hooks, keine Rechtspflicht-Ausgabe → Auto-Produkte nach **D39-4**; nach Reaktivierung Reconcile ohne Duplikate (CORE-12) |
| K-11 `suite_integrations` | voll | bestehende Verbindungen laufen, Mapping-Änderungen 403 | Connector `inactive`, Free-Events laufen (E-38) |
| K-12 `webhooks` | Zustellung | **Zustellung pausiert**, Outbox puffert bis E-258 (30 Tage), Protokoll lesbar | keine Zustellung |
| K-13 `custom_activities` | voll | vorhandene nutzbar, Fortschritt zählt | „Inhalt derzeit nicht verfügbar“, nicht abschlusspflichtig (R0-11) |
| K-14 `frontend_builder` | voll | Frontend-Bearbeitung 403, Admin-Editor (Free) bleibt | nicht vorhanden |
| K-15 `multi_instructor` | voll | Zuordnungen lesbar, keine neuen | Zusatztrainer verlieren Objektrechte, Audit (R0-11) |
| K-16 `advanced_roles` | voll | vorhandene Rollen wirken, keine Änderung | Pro-Caps wirkungslos, Rollen bleiben registriert-los; Free-Rollen unverändert |
| K-17 `content_workflows` | voll | geplante Veröffentlichungen laufen (Inhalt ist schon freigegeben); neue Planungen 403 | geplante Veröffentlichungen pausieren; Free-Publish manuell |
| Updater | Updates | „Update verfügbar, nicht automatisch“ mit Grund (CI:317) | – |

**Sonderfälle:**
- **Offline-Ende mitten im Request:** Die Klasse wird einmal je Request bestimmt und gilt für den ganzen Request; ein Speichervorgang wird nicht halb ausgeführt.
- **`site_changed` (Staging-Kopie):** E, und zusätzlich gilt der Free-Haltemodus (E-85, `course_captain_hold_mode_changed`). Pro-Webhooks und Commerce-Sync liefern nichts aus, solange er aktiv ist.
- **Restore eines alten Backups:** Der Snapshot aus dem Backup gilt nur bis zu seinem signierten Fenster. `fetched_at` aus der Zukunft macht ihn ungültig (CI:209). Danach E bis zur nächsten signierten Antwort.
- **Uhrmanipulation, Datenbankänderung am Snapshot:** `stale` → E, nie V (CI:207-210).

---

## 4. Upgrade, Downgrade, Deinstallation, Reaktivierung – Datenintegrität

### 4.1 Invarianten

| Nr. | Invariante (MUSS) | Messung |
|---|---|---|
| **DI-1** | Free-Prüfsumme (R0-29 §3.3: `cc_group`, `cc_group_course`, `cc_seat`, `cc_grant`, `cc_certificate`, `cc_certificate_template`, `cc_course_staff`, erweitert um `cc_question`-Revisionen inkl. Erklärungstexte und `cc_attempt`) bleibt über jeden Übergang in §4.2 gleich. Ausnahme ist nur die dokumentierte L5D-Differenz. | sha256 vor/nach |
| **DI-2** | Pro schreibt Free-Daten nur über Free-Domänendienste und die PHP-API v1. | PG-4, LIC-024 |
| **DI-3** | Kein Lizenz- oder Pluginwechsel löscht Daten. Löschen gibt es nur über die bestätigte Uninstall-Option (L5D). | LIC-060…066 |
| **DI-4** | Nach Reaktivierung gibt es keine Nachholmails, keine doppelten Grants oder Revisionen und keine rückwirkende Sperre freigeschalteter Module (R0-29 §3.3). | LIC-067/068 |
| **DI-5** | Free-Optionen `course_captain_settings_*` und Pro-Optionen `course_captain_pro_*` sind getrennt; kein Uninstall löscht die des anderen. | LIC-063/064 |

### 4.2 Übergänge

| Nr. | Übergang | Erwartung | Test |
|---|---|---|---|
| T-01 | Free → Pro installiert, unlizenziert | Pro-Tabellen angelegt, nur Lizenzseite; Free unverändert (DI-1) | LIC-060 |
| T-02 | unlizenziert → `active` | Module nach R-4; Pro-UI über Registries; keine Free-Migration | LIC-040 |
| T-03 | Pro-Update über License Captain | Download mit SHA-256 aus signierter Antwort, Ordnerprüfung (CI:313-314); Pro-Migration eigener Tabellen; Free unberührt | LIC-030/031 |
| T-04 | Pro-Update verlangt neueres Free (`requires_core`) | Server hält zurück, Plugin-Zeile nennt Grund; Reihenfolge Free → Pro (R0-16 U-9) | LIC-032 |
| T-05 | Free-Update, Pro außerhalb `contractRange` | Pro lädt keine Module (A), Hinweis; Free läuft; nach Pro-Update wieder V/E | LIC-033 |
| T-06 | Free-Minor-Downgrade (E-90) unter Pro-Minimum | wie T-05 | LIC-034 |
| T-07 | Pro-Rollback (Release zurückgezogen) | installierte Version bleibt; Feed bietet Vorversion nur per `target_version` an; ein Pro-Schema-Downgrade ist nicht vorgesehen. Pro-Migrationen sind additiv nach R0-37 | LIC-035 |
| T-08 | V → E (Ablauf, Sperre, Offline > 14 d) | §3.2/§3.3; DI-1 | LIC-041…050 |
| T-09 | E → V (Verlängerung) | ohne Neuaktivierung; Jobs setzen fort; keine Nachholmails | LIC-051 |
| T-10 | V/E → A (Plugin deaktiviert) | Wirkungsbericht; Fallback; Jobs pausiert; D39-4 | LIC-061 |
| T-11 | A → V/E (wieder aktiviert) | Reconcile Commerce ohne Duplikate; Jobs fortsetzen oder verwerfen | LIC-067 |
| T-12 | Deinstallation ohne Löschen (L5) | Aktivierung freigegeben (`uninstall()`), Pro-Daten bleiben, Free-Hinweis „Daten vorhanden“ (FG-11) | LIC-062 |
| T-13 | Deinstallation mit Löschen (L5D) | nur Pro-Daten und dokumentierte Free-Differenz über Free-Dienste; bei inaktivem Free Bereinigungsauftrag (§2.6) | LIC-064/065 |
| T-14 | Neuinstallation nach L5/L5D (L6/L6D) | findet Tabellen (L6) bzw. startet leer (L6D); Lizenz neu aktivieren | LIC-066 |
| T-15 | Site-Umzug / Staging-Kopie | `site_changed` → E + Haltemodus; Kopie beendet nie die Live-Aktivierung (CI:273); `move()` nur mit Bestätigung (CI:281) | LIC-052/053 |
| T-16 | Free deaktiviert bzw. gelöscht trotz Pro (WP-CLI/FTP) | Pro → A ohne Fatal; nach Rückkehr von Free wieder V/E; DI-1 | LIC-069 |

---

## 5. Externe Abhängigkeiten (Blocker für R3-18)

| Nr. | Abhängigkeit | Stand im Quellvertrag | Folge / Vorschlag | Bezug |
|---|---|---|---|---|
| **XB-1** | Produkt `course-captain-pro` im License Captain (E-42) | `PRODUCT_KEYS` kennt nur `voucher-`, `pass-`, `affiliate-captain-pro` (`EntitlementCatalog.php:129-133`). Weitere Produkte sind über den Filter `wplc_known_product_keys` möglich (`:146`); Präfixregel EN:61 nennt `course.` nicht | Ohne Server-Code lösbar: Shop-MU-Plugin mit `wplc_known_product_keys` (`course-captain-pro => course`) und `wplc_entitlement_catalog` (17 Schlüssel). Trotzdem Auftrag an License Captain: Doku EN:48-62 und `PRODUCT_KEYS` ergänzen, damit der Eintrag kein Sonderweg bleibt | E-42, R0-36 L10/L11 |
| **XB-2** | Katalog der Entitlements nach E-95 | kein Course-Eintrag; License-TODO bereitet nur Form/Review/Workflow vor | Liste §2.4 ist Eingabe für den Auftrag; nur implementierte Schlüssel (R-5) | E-95, R0-36 L11 |
| **XB-3** | Signatur der Pro-ZIPs (E-190) | Kein „Signieren beim Upload“. Der Server liefert eine **Ed25519-signierte** Update-Antwort mit `sha256`; der Client vergleicht den Download damit (UP:7-10, CI:313). Der Release-Datensatz ist nach der Veröffentlichung unveränderlich (UP:157) | Die Integrität ist kryptografisch gleichwertig gesichert, sofern Upload bzw. Manifest vertrauenswürdig sind. E-190 wörtlich ist nicht erfüllbar → **D39-5** | R0-18 §3.5, D18-3 |
| **XB-4** | Release-Readiness License Captain | Gates 3 „Updates and product plugins“ (T-131, T-134, T-135) und 4 „Security“ (u. a. T-134 „server signature in the client“) offen; „Do not advertise a capability … before its gate is ticked“ (RR:29-44, :64-66) | Keine Pro-Werbung (Erweiterungen-Seite, Readme) vor Abschluss; RC-099 | R0-35 RC-099 |
| **XB-5** | Zugriff auf `captain-pro` | nicht zugänglich: Build-Registry, Kernel, FeatureRegistry, `TARIFE.md` ungeprüft | §2 stützt sich nur auf den License-Client-Vertrag. Hat `captain-pro` einen gemeinsamen Kernel, wird §2.1/§2.4 darauf abgebildet (D39-6) | R0-36 §6 |
| **XB-6** | Stabiler Lizenz-Hostname, Ed25519-Public-Key, `account_url` | im Starter nur Platzhalter | vor dem ersten Pro-Build vom Betreiber zu liefern; der Hostname ist in jedes ZIP eingebrannt (CI:130) | CI:126-138 |
| **XB-7** | Plan-Schlüssel | Kap. 6 `single/business/agency`, License-Beispiel `solo` (EN:58) | Abgleich vor der Katalogpflege; für die Course-Logik ohne Belang (R-6) | R0-36 X6 |
| **XB-8** | WordPress.org-Slug des Free-Kerns | ungeprüft (E-205) | `Requires Plugins` und `core-plugin-slug` hängen daran | E-205 |
| **XB-9** | Ungetestete Bereiche der Library | „a real add-on in a real shop over real HTTP and TLS, WP-Cron timing on a live host, PHP without sodium“ nicht getestet (CI:405) | LIC-080…082 als Course-eigene E2E gegen einen Test-Shop | CI:354-370, :405 |

---

## 6. Testfälle

**Typen:** S = statisch/CI-Guard, U = Unit, I = Integration (WP-Testsuite, Lizenzserver-Stub mit signierten Fixtures), E = E2E (Playwright, echter Test-Shop mit License Captain), M = manuell. **Testhilfe:** signierte Fixtures wie im Starter („server-generated signed fixtures“), `clock`-/`transport`-Testdoubles der Library (CI:161-162), Fixture **FX-LIC** aus R0-29 §3.2, erweitert um FX-LIC-X (Frage mit Erklärung, Woo-Auto-Produkt, Webhook-Endpunkt, Erinnerungskampagne).

### 6.1 Free-Grenze (Ziel R1-39 bzw. R3-18/R3-15, je Zeile)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| LIC-001 | S | Guard G-1…G-14 auf Free-Quelltext | 0 Treffer | R1-39, R3-18 | §1.2 |
| LIC-002 | S | Guard auf entpacktem Free-ZIP | 0 Treffer; ZIP enthält keinen Ordner `licensing/` | R1-39, R3-18 | J-17 |
| LIC-003 | S | Free-Plugin-Kopf | kein `Update URI`, kein `Requires Plugins` | R1-39 | G-4, G-13 |
| LIC-004 | S | Free-`.pot` | keine Textdomain `course-captain-pro`, kein Wort „license key“ | R1-39 | G-10 |
| LIC-005 | I | Laufzeit-Guard LG-1 über die volle Free-Suite | kein ausgehender Request | R1-39 | FG-09 |
| LIC-006 | I | LG-2: Pro installiert/inaktiv, aktiv/unlizenziert | Free-Ergebnisse und DI-1 identisch zu L0 | R3-18 | FG-05 |
| LIC-007 | I | Free-Hooks auf Updater-Filter | `has_filter` für G-5-Hooks liefert für Free-Callbacks nichts | R1-39 | FG-02 |
| LIC-008 | I | Free-Optionen nach Aktivierung | keine Option `wplc_*`, `*_license*` | R1-39 | FG-03 |
| LIC-009 | U | Free-REST-Antworten Lernmodus/Ergebnis | kein Schlüssel `explanation*`, kein `explanation_locked` | R1-39 | §2.5 |
| LIC-010 | S | Guard-Selbsttest mit Treffer-Fixtures | 14 Treffer; sauberes Fixture 0 | R1-39 | §1.2 |
| LIC-011 | S | Allowlist-Eintrag für G-7 | CI rot („G-7 nicht ausnahmefähig“) | R1-39 | FG-07 |
| LIC-012 | I | Free mit `course_captain_extensions_seen`-Eintrag, Pro inaktiv | neutraler Hinweis „Erweiterung inaktiv, Daten vorhanden“, keine Lizenzangabe, kein Kauflink | R3-15 | FG-06, FG-11 |

### 6.2 Pro-Build, Client, Registry (Ziel R3-18)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| LIC-020 | S | PG-1…PG-8 im Pro-Repository | grün | R3-18 | §2.7 |
| LIC-021 | S | Client-Export reproduzierbar | erneuter Export aus gepinntem Commit = committete Dateien | R3-18 | PG-2 |
| LIC-022 | I | Voucher Captain Pro (Client älter, eigener Namespace) und Course Captain Pro parallel aktiv | keine „Cannot declare class“, je eigene `VERSION`, eigene Optionen, eigener Cron, Fehler eines Add-ons ohne Wirkung auf das andere (R0-11 F5.8) | R3-18 | CI:23-27 |
| LIC-023 | I | Ladereihenfolge: Pro vor Free geladen | kein Zugriff auf Free vor `course_captain_loaded_v1`; keine Notice | R3-18 | §2.2 |
| LIC-024 | S/I | Pro schreibt Free-Daten | nur über PHP-API/Dienste; direkter SQL-Schreibzugriff auf Free-Tabellen fehlt | R3-18 | DI-2 |
| LIC-025 | U | `is_entitled` für unbekannten Schlüssel, Zahl 0, String, `access=false` | false (fail closed) | R3-18 | R-3, EN:31 |
| LIC-026 | I | Frontend-Request (Lernmodus) mit Pro aktiv | 0 Requests an den Lizenz-Host, ≤ 1 Optionslesung je Request | R3-18 | §2.2 Pkt. 6 |
| LIC-027 | U | Registry: Modul laden bei (a) Schlüssel, (b) nur vorhandene Pro-Daten, (c) keins | (a) V, (b) E, (c) nicht geladen | R3-08 | R-4 |
| LIC-028 | U | Registry ohne doppelten Schlüssel, alle Schlüssel nach `KEY_PATTERN` | grün | R3-18 | R-1 |
| LIC-029 | M | Lizenzseite als Course-Untermenü, Rechte | nur `manage_options`; Schlüssel und Installationsgeheimnis nie ausgegeben | R3-15 | §2.3, CI:321 |
| LIC-030 | E | Pro-Update über die Pluginliste | installiert, SHA-256 geprüft | R3-18 | T-03, RC-093 |
| LIC-031 | I | Update mit falscher Prüfsumme, abgelaufenem Link, inaktiver Lizenz | `wplc_update_checksum` bzw. abgelehnt; alte Version bleibt | R3-18 | RR:32 |
| LIC-032 | I | Release mit `requires_core` > Free | zurückgehalten, Grund in der Plugin-Zeile | R3-18 | T-04, RC-094 |
| LIC-033 | I | Free-Update außerhalb `contractRange` | Pro A mit Hinweis, Free voll funktionsfähig | R3-18 | T-05, F5.1 |
| LIC-034 | I | Free-Minor-Downgrade unter Pro-Minimum | wie LIC-033 | R3-18 | T-06 |
| LIC-035 | M | Pro-Release zurückziehen | Feed fällt auf Vorversion; installierte bleibt; kein Datenverlust | R3-18 | T-07, RC-098 |

### 6.3 Zustände × Verhalten (Ziel R3-08, Settings-Teil R3-15)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| LIC-040 | I | unlizenziert → `active` mit FX-LIC-X | alle 17 Module V, Pro-Tabs per Registry | R3-08 | T-02 |
| LIC-041 | I | `active` → `expired` | Klasse E je Zeile §3.3; DI-1 gleich | R3-08 | T-08 |
| LIC-042 | I | `grace` (Zahlung überfällig) | V, Banner mit Datum `access_until` | R3-08 | §3.1 |
| LIC-043 | I | Server antwortet nicht (Timeout, 5xx, WAF-HTML) an Tag 1, 3, 13 | V; ab Tag 3 Hinweis mit Abschaltdatum; Backoff 15 min … 1 d | R3-08 | CI:221-223, :322 |
| LIC-044 | I | Offline Tag 15 (nach `cache_expires_at`) | `stale` → E, ohne Fehlermeldung an Lernende (R0-29 GRP-065) | R3-08 | §3.1 |
| LIC-045 | I | Snapshot in der DB editiert / falsche Signatur | `stale` → E, Diagnoseeintrag (R0-11 F5.4) | R3-08 | CI:207 |
| LIC-046 | I | unsigniertes 403/401 `site_mismatch` | keine Rechteänderung (`keep`) | R3-08 | CI:214-221 |
| LIC-047 | I | signiert `suspended`, `revoked` | E (bzw. nach D39-3) | R3-08 | §3.1 |
| LIC-048 | I | E: neue Woo-Bestellung bezahlt, Erstattung, Gutscheineinlösung auf bestehender Zuordnung | Grant bzw. Widerruf wie in V; Rechtspflicht-Ausgaben (E-33) unverändert | R3-08 | E-93, K-10 |
| LIC-049 | I | E: neue Zuordnung, Preisänderung am Kurs, neues Modul (E-34-Sync) | 403 bzw. kein Sync; Produkt unverändert | R3-08 | K-10 |
| LIC-050 | I | E: Webhooks und Erinnerungen | Webhook-Zustellung pausiert, Outbox puffert; keine Erinnerung; Abmeldelink 200 | R3-08 | K-08, K-12 |
| LIC-051 | I | E → V durch Verlängerung | ohne Neuaktivierung; Outbox stellt zu; keine Nachhol-Erinnerungen | R3-08 | T-09, DI-4 |
| LIC-052 | I | DB-Kopie auf Staging-Host | `site_changed`, kein Request mit kopierten Zugangsdaten, E + Haltemodus | R3-08 | T-15, CI:273 |
| LIC-053 | M | Staging aktivieren (`staging_parent_installation_id`), Umzug mit Bestätigung | Staging zählt nicht als Live-Site; Umzug übernimmt die Aktivierung | R3-18 | CI:274, :281 |
| LIC-054 | I | Erklärungen in V, E, A | V: ausgeliefert; E: nach D39-1; A: nicht ausgeliefert, Texte vorhanden, Export enthält sie | R3-08 | K-01, E-20 |
| LIC-055 | I | Laufender Versuch, Lizenz wechselt mitten im Versuch V → E | Ergebnis nach Snapshot-Einstellung; keine 500er | R3-08 | §3.3 Sonderfälle |
| LIC-056 | I | Pro-Settings in V/E/A (`pro.*`) | V editierbar; E lesbar, Speichern 403 mit erhaltenen Eingaben; A unsichtbar, Werte erhalten | R3-15 | SET-076, SET-104 |
| LIC-057 | I | Pro-Settings-Export/-Import in E | Export enthält `pro.*`; Import 403 | R3-15 | R0-27 |
| LIC-058 | I | Planwechsel entfernt einen Schlüssel | nur dieses Feature E, übrige V | R3-08 | R-6 |
| LIC-059 | I | Uhr zurückgestellt / `fetched_at` in der Zukunft | Snapshot ungültig → E; Recheck ≤ 15 min | R3-08 | CI:209 |

### 6.4 Upgrade, Downgrade, Deinstallation, Reaktivierung (Ziel R3-15/R3-18)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| LIC-060 | I | Pro installieren auf Free mit Bestand | DI-1 gleich; nur Pro-Tabellen neu | R3-18 | T-01 |
| LIC-061 | E | Pro deaktivieren | Wirkungsbericht inkl. Auto-Produkte; danach A; Jobs pausiert; DI-1 | R3-15 | T-10, D39-4 |
| LIC-062 | I | Pro deinstallieren ohne Löschen | Server-Aktivierung freigegeben; `course_captain_pro_license*` gelöscht; Pro-Tabellen bleiben; Free-Hinweis | R3-18 | T-12 |
| LIC-063 | I | Free deinstallieren (mit Datenlöschung) bei vorhandenem Pro | `course_captain_pro_*` unverändert | R3-18 | FG-10, DI-5 |
| LIC-064 | I | Pro deinstallieren mit Löschen, Free aktiv | Pro-Daten weg; Free-Differenz genau wie R0-29 L5D; Bericht zählt sie | R3-18 | T-13 |
| LIC-065 | I | Pro deinstallieren mit Löschen, Free inaktiv | nur Pro-Tabellen gelöscht; Bereinigungsauftrag; Free führt ihn beim nächsten Laden aus | R3-18 | §2.6 |
| LIC-066 | I | Neuinstallation nach L5 bzw. L5D | L6: alter Stand bearbeitbar nach Aktivierung; L6D: leer | R3-18 | T-14 |
| LIC-067 | I | A → V nach Bestellungen während A | Reconcile erzeugt fehlende Grants einmal, keine Duplikate, keine Nachholmails | R3-08 | T-11, DI-4 |
| LIC-068 | I/E | Sequenz V → E → A → L5 → L6 (FX-LIC-X) | Prüfsummen P1 = P3 = P5 = P6 (R0-29 §3.3, erweitert DI-1) | R3-08 | §4.1 |
| LIC-069 | I | Free per WP-CLI deaktiviert, Pro aktiv | Pro A ohne Fatal; Free wieder aktiv → V/E | R3-18 | T-16 |

### 6.5 Mehrere Add-ons, Betrieb, Datenschutz (Ziel R3-18)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| LIC-080 | E | Quick-Start-Ablauf aus CI:354-370 gegen einen echten Test-Shop (Kauf → Schlüssel → Aktivierung → Update) | erfolgreich, über echtes HTTPS/TLS | R3-18 | XB-9 |
| LIC-081 | E | WP-Cron aus; Admin-Seitenaufruf nach 36 h | Nachhol-Check läuft | R3-18 | CI:323 |
| LIC-082 | I | PHP ohne ext-sodium (sodium_compat) bzw. ohne Ed25519 | sodium_compat: V mit Memo; ohne: `stale`, fail closed, Free unverändert | R3-18 | CI:207 |
| LIC-083 | I | drei Captain-Add-ons, Lizenz-Host eines Add-ons gesperrt | nur dieses Add-on betroffen | R3-18 | F5.8 |
| LIC-084 | I | `COURSE_CAPTAIN_PRO_LICENSE_DEV` nicht definiert, Filter `…_public_keys` von Fremdplugin | ignoriert, gepinnte Schlüssel gelten | R3-18 | CI:136 |
| LIC-085 | I | Mitschnitt aller Requests an den Lizenz-Host während Aktivierung, Check, Update | nur Felder aus CI:346; keine Kurs-, Nutzer-, Prüfungs- oder E-Mail-Daten | R3-18 | §2.3, Kap. 6 |
| LIC-086 | M | Datenschutzleitfaden enthält Pro-Absatz mit Course-Ergänzung | vorhanden | R3-18 | §2.3 |

**Zählung:** 12 + 16 + 20 + 10 + 7 = **65 Fälle**. Ziel R3-08: 20, R3-15: 5, R3-18: 32, nur R1-39: 8 (LIC-001/002 zählen bei R3-18 und R1-39). LIC-030/031/035 decken zugleich RC-093/098 ab.

---

## 7. Entscheidungsbedarf (nur neue, geprüft gegen E-01…E-259)

Als Annahme genutzt und **nicht** erneut gestellt: E-07, E-09, E-20, E-33, E-34, E-42, E-53, E-85, E-89, E-90, E-93, E-94, E-95, E-100, E-147, E-190, E-205, E-207, E-213, E-258. Technisch festgelegt ohne Auftraggeber: FG-01…FG-12, Guard-Muster, R-1…R-6, Klassenabbildung §3.1 (außer D39-3), DI-1…DI-5, Optionspräfixe, Ladereihenfolge.

| ID | Frage | Optionen | Empfehlung | Entscheider | Blockiert |
|---|---|---|---|---|---|
| **D39-1** | **Erklärungen (E-20) bei Lizenzende.** E-94 lässt Pro-Konfiguration eingefroren weiterwirken. Gilt das auch für die Auslieferung der Erklärungen an Lernende? | a) weiter ausliefern (E), nur Einstellungen gesperrt; aus erst bei deaktiviertem Add-on · b) ab `expired` nicht mehr ausliefern · c) nur für Versuche, die vor dem Ablauf gestartet wurden | **a)**: passt zu E-94 und ER-4; Lernende verlieren nichts mitten im Kurs; die Verlängerung bleibt für Änderungen, Updates und Support nötig. Kommerziell abwägen: b) erhöht den Verlängerungsdruck, trifft aber Lernende | Auftraggeber (PO) | R3-08, K-01 |
| **D39-2** | **Erklärungsfeld im Free-Frageneditor** (Free speichert, zeigt aber nicht an) | a) Feld in Free ausgeblendet, Texte bleiben erhalten (Import/Export); Pro blendet es per Registry ein · b) Feld sichtbar mit neutralem Hinweis „wird Lernenden nicht angezeigt“ · c) sichtbar ohne Hinweis | **a)**: kein toter Schalter (E-100, ER-5); b) wirkt wie Upselling, c) täuscht Autoren | Auftraggeber + UX | R1-06, R3-08, R0-30 |
| **D39-3** | **`suspended`/`revoked` (Sperre/Widerruf durch den Shop, z. B. Chargeback, Missbrauch)** wie `expired` behandeln? | a) wie `expired` (E): Konfiguration eingefroren, E-93-Vorgänge laufen · b) E-93-Vorgänge laufen, übrige Pro-Konfiguration fällt wie A auf Free zurück · c) wie A, auch ohne E-93 | **a)**: Käuferschutz und Datenregel der Library („never change what the free core plugin does“, CI:202) hängen nicht vom Grund ab; c) lässt Endkunden ohne Zugang | Auftraggeber | R3-08 |
| **D39-4** | **Automatisch angelegte Woo-Produkte (E-34) bei Deaktivierung/Deinstallation von Pro.** Ohne Pro-Code gibt es keine Grants und keine Rechtspflicht-Ausgaben (E-33); die Produkte blieben trotzdem kaufbar | a) im Wirkungsbericht Standardaktion „Produkte auf ‚privat‘ setzen“ (vorheriger Status in Produkt-Meta), bei Reaktivierung zurücksetzen und Reconcile · b) nur warnen, Produkte bleiben kaufbar · c) immer automatisch privat ohne Wahl | **a)**: verhindert Zahlung ohne Zugang und ohne Pflichtangaben; Betreiber kann bewusst abwählen. Schreiben nur über die WooCommerce-API, kein SQL | Auftraggeber + Recht | R3-02, R3-03, R3-15 |
| **D39-5** | **E-190 und der reale Mechanismus.** License Captain signiert nicht das ZIP, sondern die Update-Antwort mit `sha256` (Ed25519); der Release-Datensatz ist unveränderlich | a) signierte Antwort + SHA-256 gilt als Erfüllung von E-190; zusätzlich Build-Attestierung (Sigstore/GitHub, wie Free E-189) für die Herkunft · b) zusätzlich eine abgesetzte Ed25519-Signatur über das ZIP aus der CI, die der Client prüft (braucht Client-Erweiterung im License Captain, D18-3 b) · c) nur SHA-256 | **a)**: kryptografisch gleichwertig, ohne Eingriff in die Library; b) nur, wenn License Captain es ohnehin für alle Add-ons baut | Auftraggeber + Security | R3-18, RC-093 |
| **D39-6** | **Build-Ort des Pro-Add-ons** (Kap. 6: `captain-pro/addons/course-captain-pro/`; License `CLIENT-REPOS.md:23`: eigenes Repository) | a) `captain-pro`, sobald Zugriff besteht; bis dahin Entwicklung gegen den Starter-Vertrag · b) eigenes Repository `course-captain-pro` mit Starter-Struktur · c) im Free-Repository (verboten wegen FG-01) | **a)**, falls `captain-pro` einen gemeinsamen Build mit Pro-Guard bietet; sonst **b)**. Spätestens vor R3-08 festlegen | Auftraggeber + Architektur | R3-18, XB-5 |
| **D39-7** | **Kompatibilitätsfenster Pro ↔ Free** | a) Pro N unterstützt die aktuelle und die vorige Free-Minor (passend zu E-90); `requires_core` = vorige Minor; Free-Minor-Release erst nach grünem Pro-Kompatibilitätslauf · b) nur exakt die aktuelle Free-Minor · c) gesamtes Free-Major ohne Prüfung | **a)**: Pro nutzt experimentelle Slots; b) erzwingt gleichzeitige Updates, c) ist bei experimentellen Slots nicht haltbar | Architektur + Auftraggeber | R3-11, R3-18, R1-28 |

---

## 8. Deltas zu anderen Dokumenten (mit deren Freigabe einzuarbeiten)

| Nr. | Dokument | Änderung |
|---|---|---|
| Δ-1 | R0-11 §1.5 Absatz „Lizenzzustände“, Flow 5 „Ablauf“ | `grace` = Zahlung überfällig, nicht „Server nicht erreichbar“. Offline bleibt der zuletzt signierte Zustand bis `cache_expires_at`, danach `stale`. `invalid` heißt im Client `stale`/`misconfigured`; Zustandsliste nach §3.1 |
| Δ-2 | R0-29 §3.1 Zeile L2 | „Grace (Server nicht erreichbar)“ → „Offline ≤ 14 d (Snapshot gültig)“; zusätzlich Zeile `grace` (Zahlung) mit Verhalten wie L1. GRP-065 entsprechend |
| Δ-3 | R0-08 §4.5, R0-27 | `show_explanations` aus Free-Register entfernen → `pro.explanations.show` (E-20); Free behält Speicher und Export der Texte |
| Δ-4 | R0-19 §5.8 (`DesiredGrant.grantSourceKey`), Konzept Kap. 7/31.2 („Enrollment/Entitlement-Service“) | `entitlement_revision` → `grant_revision`; im Free-Code kein Wort „entitlement“ (FG-07) |
| Δ-5 | R0-19 §3 / R0-11 F5.5 | `cc_pro_license_required` im Pro-Fehlerkatalog, nicht im Free-Katalog |
| Δ-6 | R0-18 §2.5 | Musterliste durch G-1…G-14 ersetzen (u. a. G-7 breit wie A2, ohne Ausnahme), Guard-Selbsttest LIC-010 |
| Δ-7 | R0-11 §1.3/N-03, Konzept Kap. 6 „Vorgesehene Pro-Entitlements“ | Liste K-01…K-17; `drip_content`, `certificates`, `groups` streichen; `course.explanations` ergänzen |
| Δ-8 | R0-35 §4 Zeile „Spezifikation der Grenze“ | Verweis auf dieses Dokument; RC-090…099 um LIC-Bezüge ergänzen |
| Δ-9 | R0-16 (Uninstall-Manifest) | FG-10: Free löscht nur exakte Namen bzw. `course_captain_settings_*` |
| Δ-10 | R0-29 §3.1 L5D | Pro-Uninstall bei inaktivem Free: Bereinigungsauftrag statt direkter Änderung an Free-Tabellen (§2.6) |

---

## 9. Nachweis

- Gelesen: TODO R0-39/R3-08/R3-15/R3-18; Konzept Kap. 6 (und Kap. 7, 31.2 auszugsweise); R0-11 §1.1–1.5, Flow 5, §4, §5; R0-29 §0, §3, §4.3, §4.6, §5; R0-18 §2.5, §3.2, §3.5, §5; R0-19 §0, §1.6, §4.1–4.3; R0-35 §4, §5.11, §6; R0-36 §5; `ENTSCHEIDUNGEN.md` (Beschlussprotokoll und E-42, E-53, E-93…E-95, E-190, E-207, E-213, Abschnitte 15–38).
- Quellvertrag `wp-license@c269e07`: `docs/CLIENT-INTEGRATION.md` (vollständig), `docs/ENTITLEMENTS.md:1-70`, `docs/UPDATES.md` (Auszüge), `docs/RELEASE-READINESS.md` (vollständig), `bin/export-client.php:1-60`, `src/Catalog/EntitlementCatalog.php:26, 129-154`, `examples/premium-addon-starter/README.md:1-60`; `wpaffiliatemaster/tests/run.php:332-363`.
- Nicht geprüft: `captain-pro` (kein Zugriff); WordPress-Verhalten zu `Requires Plugins` und zur Sortierung von `active_plugins` beruht auf Modellwissen (MW) und ist in LIC-023/LIC-069 zu bestätigen.
- Kein Code, keine Tests ausgeführt, kein Commit.
