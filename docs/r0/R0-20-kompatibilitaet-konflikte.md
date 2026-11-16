# R0-20 · WordPress-Kompatibilität und Konfliktmatrix (Entwurf)

**Status:** **Vorgeschlagen, Freigabe durch Auftraggeber und Architektur offen.** Task R0-20 ist **nicht** abgehakt. Es gibt keine Implementierung und kein Testprotokoll. Alle KOMP-Fälle sind Vorgaben für R1-30 (Ausführung) sowie R1-18, R1-25, R1-37 und R1-38.
**Stand:** 16.11.2026
**Bezug:** TODO R0-20 (DoD: „Unterstützte WP-/PHP-/DB-Versionen, Multisite-Grenze, Cache-/Security-/SEO-/Theme-/Woo-Kombinationen und Testinstallationen beschlossen“), Voraussetzungen R0-14 und R0-18, Ziel-Task R1-30 (DoD: „Themes, Objektcache, REST-Security, Mail-/Cache-Plugins und unterstützte Versionen in Matrix getestet; Konflikte dokumentiert“). Konzept Kap. 21 (SEO, Übersetzungsplugins), 23.1 („Multisite explizit nicht unterstützt“), 23.2 (Release-Gates: SEO-/Yoast-Kompatibilität, Koexistenz mit learnboating), Kap. 1/2 („parallel mit learnboating … unterschiedliche Namespaces, Tabellen, REST-Routen, Hooks, Slugs“, `konzept.md:64`). Weitere Bezüge: R0-14 §3 (Startmatrix), D-14-01/02; R0-18 §1.3 (M-Z/M-U/M-M, nightly-Zeilen), J-21/J-22; R0-25 §1 (MS-R-01…13, MS-001…023), Ü-4; R0-21 §3.3/§3.5 (K0…K4, H-X, H-6, H-7), CON-020…047; R0-10 §4 (Apache/nginx/LiteSpeed, MED-006/007); R0-04 Z-5/Z-6, K-07 sowie R0-11 E-53 (Woo erst R3); R0-07 (Rollen, RG-21); R0-19 §1.2 (Präfixe), §4.5/§4.6 (Shortcodes/Blocks).
**Quelle LB:** `hastla007/learnboating` @ `9f37c77`, nur gelesen. Pfade sind relativ zum Repository-Stamm.

**Arbeitsannahmen aus `ENTSCHEIDUNGEN.md` (nicht beschlossen):**

| ID | Annahme | Wirkung hier |
|---|---|---|
| E-07 | offiziell nur Single-Site; die Aktivierung je Site ist technisch korrekt | §2.1 |
| E-08 | InnoDB und JSON-Funktionen Pflicht (MySQL 8 / MariaDB 10.6+) | §1, harte Untergrenze der Systemprüfung |
| E-12 | LB-Matrix als Startwert | §1 |
| E-49 | Netzwerkaktivierung blockiert, Schema je Site | §2.1 |
| E-57 | PHP-Untergrenze 8.2, Lebenszyklus in R0-20 belegen | §1.2, **D-20-01** |
| E-58 | MariaDB 10.11 und MySQL 8.4 in der CI, MySQL 8.0/MariaDB 10.6 best effort | §1.3 (Widerspruch aufgelöst) |
| E-61 | Browser: je zwei letzte Hauptversionen, dazu ESR, iOS und Android | §1.1, unverändert |

---

## 0. Belege aus LearnBoating

| ID | Fundstelle | Befund | Verwendung |
|---|---|---|---|
| LB-K-01 | `bootspruefung-learning/bootspruefung-learning.php:5-12`, `readme.txt:2-7` | `Requires at least: 7.0`, `Tested up to: 7.1`, `Requires PHP: 8.1`, `WC requires at least/tested up to: 11.2` | §1. CC Free bekommt **keine** `WC …`-Kopfzeilen (keine Kopplung in R1) |
| LB-K-02 | `.github/workflows/ci.yml:131-141` | Ziel PHP 8.5/WP 7.1.4/MariaDB 12.3.3; Untergrenze PHP 8.1/WP 7.0.0/MariaDB 10.11 („älteste unterstützte MariaDB-LTS“); MySQL 8.4 nur `experimental` | §1.3 |
| LB-K-03 | `docs/installation-und-updates.md:41-55` („Kompatibilität“) | „MariaDB ab 10.11; MySQL nicht festgelegt, nicht getestet“ | Abweichung: Bei CC ist MySQL 8.4 eine Pflichtachse (R0-14) |
| LB-K-04 | `src/Frontend/NoCache.php:22-23, 111-115` | `DONOTCACHEPAGE`, `litespeed_control_set_nocache`, `X-LiteSpeed-Cache-Control: no-cache` | Page-Cache-Zeilen §2.4 (über R0-21 CB-11) |
| LB-K-05 | `src/Security/Hardening.php:59-65` | Yoast: `wpseo_sitemap_exclude_author`, Filter auf `option_wpseo_titles` (Autorenarchiv noindex) | SEO §2.7. CC ändert **keine** Yoast-Optionen, auch nicht per Options-Filter (E-10: Website-Härtung nicht im Kern) |
| LB-K-06 | `src/Security/Hardening.php:188-199` | Erkennung von Security-Plugins mit eigenem Login-Limit (Wordfence, Solid/iThemes, LLAR, AIOS, Loginizer) | Security §2.6, Systemstatus-Erkennung |
| LB-K-07 | `src/Gate/ContentGate.php:22-25, 72` | Elementor/Beaver Builder: Gate auch über `elementor/frontend/builder_content_data` | Page-Builder §2.8 |
| LB-K-08 | `src/Support/AfterResponse.php:28, 67` | `fastcgi_finish_request` bzw. `litespeed_finish_request`, ohne beides (mod_php) synchron | Webserver §2.2 |
| LB-K-09 | `src/Frontend/Router.php:23, 86-87`; `src/Rest/RestRegistrar.php:17`; `src/Mobile/MobileApi.php:42`; `src/Faq/Faq.php:22`; `src/Tips/Tips.php:18` | LB-Kennungen: Lernbereich `/bootsscheine/`, Query-Var `bp_app`, REST `bootspruefung/v1` und `boatingschool/v1`, CPTs `bp_faq`, `bp_customer_faq`, `bp_tip`, Block `bootspruefung/zugang` | §3: keine Überschneidung mit CC (Koexistenz J-21, E-194) |
| LB-K-10 | `docs/installation-und-updates.md` („Benötigte Plugins“) | Yoast optional, WooCommerce Pflicht für den Shop | CC: beide optional, keine Pflichtabhängigkeit (R0-04 Z-6) |

---

## 1. Versionsmatrix (Vorschlag zur Beschlussfassung)

### 1.1 Stufen

| Stufe | Bedeutung | Nachweis |
|---|---|---|
| **zugesichert** | Supportzusage. Fehler mit CC-Ursache werden als Bug mit Release-Priorität behandelt. | Pflichtzeile der CI (PR oder nightly blockierend, R0-18) **oder** KOMP-Fall mit Status „Pflicht“ in jedem R1-30-Lauf vor einem Minor-Release |
| **getestet** | wird regelmäßig geprüft; Befunde werden dokumentiert; Fehler mit CC-Ursache werden behoben, Fehler mit Fremdursache bekommen einen Doku-/Systemstatus-Hinweis | R1-30-Profil (§4) vor jedem Minor-Release; automatisierbare Profile nightly bzw. wöchentlich als `continue-on-error` |
| **best effort** | kein regelmäßiger Test; Fehlermeldungen werden angenommen, Behebung nach Aufwand | höchstens Smoke-Test |
| **nicht unterstützt** | dokumentierte Grenze; wenn erkennbar, Hinweis im Systemstatus (A-16/R1-38) | Doku, ggf. Negativtest |

Browser bleiben wie in E-61 und R0-18 §1.3 festgelegt. Hier ändert sich nichts.

### 1.2 Matrix

| Achse | Harte Untergrenze (Aktivierung verweigert darunter) | Unterstützt ab | Getestet (Pflicht-CI) | Informativ (nightly, `continue-on-error`) | Begründung |
|---|---|---|---|---|---|
| **PHP** | = „Unterstützt ab“ (Plugin-Kopf `Requires PHP`, WordPress prüft das selbst) | **8.3** (D-20-01; ohne Beschluss 8.2 nach E-57) | 8.3 (M-U), 8.4 (M-M), 8.5 (M-Z) | nächste PHP-Version als Beta/RC (M-I3); 8.2 nur, falls D-20-01 abgelehnt wird | Jede unterstützte PHP-Minor läuft in einer Pflichtzeile. Mit 8.3 hat zum frühestmöglichen R1-Release (nicht vor 2027, R0-GATE ist gesperrt) jede unterstützte Version noch Sicherheitsupdates vom Hersteller (§1.4). Sprachniveau im Code = Untergrenze (PHPCompatibility `testVersion 8.3-`, R0-18 J-05/J-17). Pflicht-Extensions wie in R0-14 §3 |
| **WordPress** | = „Unterstützt ab“ (`Requires at least`) | **7.0** | 7.0.x (M-U), 7.1.x (M-Z, M-M); `Tested up to: 7.1` | nächste Beta/RC (M-I1) | Regel: **aktuelle und vorherige Hauptversion**. Nach einem neuen WP-Major wird `Tested up to` mit dem nächsten CC-Patch angehoben, sobald M-I1 grün ist (spätestens 30 Tage nach WP-GA). Die Untergrenze steigt mit dem nächsten CC-Minor (WordPress pflegt offiziell nur den aktuellen Zweig, ältere Zweige bekommen Sicherheits-Backports ohne Zusage). LB-Startwerte LB-K-01 |
| **MariaDB** | **10.6** (E-08; Systemprüfung: InnoDB, `JSON_VALID`, `utf8mb4`) | **10.11** | 10.11 (M-U), 12.3.x (M-Z) | 11.4 und 11.8 im Wechsel (neue Zeile M-I4), 10.6 (M-I2) | Unterstützt wird nur, was noch Herstellerpflege hat (D-14-02). 10.6 ist nach Modellwissen seit 07/2026 ohne Community-Pflege (§1.4) und läuft deshalb nur „ohne Gewähr“: Die Aktivierung wird nicht verweigert, aber der Systemstatus warnt (D-20-02) |
| **MySQL** | **8.0** (E-08) | **8.4 LTS** | 8.4 (M-M; L5/MIG nightly) | 8.0 (M-I2); nächste MySQL-LTS ab GA (neue Zeile M-I5) | MySQL 8.0 ist nach Modellwissen seit 04/2026 EOL; gleiche Regel wie oben |
| **Verwaltete DB-Varianten** | wie MySQL/MariaDB | – | – | – | Amazon Aurora MySQL, Azure Database for MySQL, Percona Server: **best effort** (Lock-/`GET_LOCK`-Verhalten wie R0-21 RB-04 dokumentiert). Galera-/Group-Replication-Cluster und Lese-Replikate: **best effort** (D21-3). SQLite-Drop-in (`sqlite-database-integration`): **nicht unterstützt** |

**Systemprüfung (R1-01, R1-38):** Unterhalb der harten Untergrenze wird die Aktivierung mit Meldung abgebrochen, bevor irgendetwas geschrieben wird. PHP und WordPress prüft WordPress selbst über die Plugin-Kopfzeilen, die DB-Version prüft CC. Zwischen harter Untergrenze und „Unterstützt ab“ erscheint ein Systemstatus-Eintrag „Empfehlung“ mit dem Text „Version erhält keine Herstellerupdates mehr; Course Captain wird darauf nicht regelmäßig getestet“. Die Anzeige leitet sich aus einer Tabelle im Code ab (Version → Stufe), die mit jedem Minor-Release gepflegt wird.

**Kopfzeilen (J-17-Prüfung, R0-18 §3):** `Requires PHP: 8.3`, `Requires at least: 7.0`, `Tested up to: 7.1`. Kein `Requires Plugins:`, keine `WC requires at least`/`WC tested up to` in R1.

### 1.3 Auflösung des Widerspruchs MariaDB 10.11 (E-58) gegenüber 12.3 (R0-14/R0-18)

Der Widerspruch ist nur scheinbar. E-58 regelt die **Untergrenze** und die **zweite DB-Achse**. R0-14 §3 und R0-18 §1.3 regeln das **Ziel**. Beides ist gewollt und stammt aus LB-K-02, wo 12.3.3 das Zielsystem ist und 10.11 die „älteste unterstützte MariaDB-LTS“. Unvollständig ist nur die Formulierung von E-58, weil sie 12.3 nicht nennt. Klarstellung (Δ-20-1, zur Übernahme in E-58):

> In der PR-CI laufen **MariaDB 12.3.x (Ziel, M-Z)**, **MariaDB 10.11 (Untergrenze, M-U)** und **MySQL 8.4 (zweite Achse, M-M)**. MariaDB 10.6 und MySQL 8.0 laufen nightly informativ (M-I2) und gelten als „ohne Gewähr“ (D-20-02). Die dazwischenliegenden MariaDB-LTS-Versionen 11.4 und 11.8 laufen nightly informativ (M-I4).

**Vorbehalt:** Ist MariaDB 12.3 bei der Verifikation (§1.4) **keine** LTS-Version, gilt als Ziel die neueste LTS-Version (voraussichtlich 11.8). 12.3 läuft dann als informative Zeile weiter. Das betrifft R0-14 R-M und R0-18 M-Z.

### 1.4 Lebenszyklusdaten

> **Kennzeichnung:** Alle Daten in dieser Tabelle stammen **aus Modellwissen** (ohne Webrecherche erstellt). Sie sind **vor dem R1-GATE gegen php.net/supported-versions, mariadb.org (Maintenance Policy/Releases) und dev.mysql.com (Lifecycle/Support Policy) zu verifizieren**. Spätere Releases (PHP nach 8.5, MariaDB 12.x-LTS-Ende, MySQL 9.x-LTS) sind in den Trainingsdaten unsicher und deshalb mit „?“ markiert. Die Verifikation wird mit Datum und Quelle in dieser Tabelle nachgetragen (Spalte „verifiziert“).

| Produkt | Version | Erschienen (GA) | Aktiver Support bis | Sicherheitsupdates / Pflege bis | Stand 16.11.2026 | verifiziert |
|---|---|---|---|---|---|---|
| PHP | 8.1 | 11/2021 | 11/2023 | **31.12.2025** | ausgelaufen | – |
| PHP | 8.2 | 12/2022 | 12/2024 | **31.12.2026** | läuft in ~6 Wochen aus | – |
| PHP | 8.3 | 11/2023 | 12/2025 | **31.12.2027** | nur Sicherheitsupdates | – |
| PHP | 8.4 | 11/2024 | 12/2026 | **31.12.2028** | aktiv | – |
| PHP | 8.5 | 11/2025 | 12/2027 | **31.12.2029** | aktiv | – |
| PHP | nächste (8.6?) | 11/2026? | ? | ? | GA möglicherweise gerade erfolgt → M-I3 | – |
| MariaDB | 10.6 LTS | 07/2021 | – | **07/2026** | ausgelaufen | – |
| MariaDB | 10.11 LTS | 02/2023 | – | **02/2028** | gepflegt | – |
| MariaDB | 11.4 LTS | 05/2024 | – | **05/2029** | gepflegt | – |
| MariaDB | 11.8 LTS | 06/2025 | – | **06/2028?** (Laufzeit bei 11.8 unsicher) | gepflegt | – |
| MariaDB | 12.3 | 2026? | – | ? (**LTS-Status zu verifizieren**, §1.3 Vorbehalt) | LB-Ziel 12.3.3 | – |
| MySQL | 8.0 | 04/2018 | – | **04/2026** | ausgelaufen | – |
| MySQL | 8.4 LTS | 04/2024 | Premier ~04/2029 | Extended **~04/2032** | gepflegt | – |
| MySQL | nächste LTS (9.7?) | 2026? | ? | ? | → M-I5 ab GA | – |
| WordPress | 7.0 / 7.1 | 2026 (LB-K-01, LB-K-02) | nur der aktuelle Zweig offiziell | Backports ohne Zusage | 7.1.x aktuell | – |

**Folgen (bis zur Verifikation vorläufig):** (1) PHP 8.2 als Untergrenze bedeutet beim R1-Release eine Version ohne Herstellerpflege. Deshalb **D-20-01**. (2) Die „best effort“-Zeilen aus E-58 (MySQL 8.0, MariaDB 10.6) sind beim R1-Release bereits EOL. Deshalb **D-20-02**. (3) Vor jedem Minor-Release wird die Tabelle neu bewertet. Die Untergrenze steigt, wenn die älteste unterstützte Version beim geplanten Release-Datum weniger als **6 Monate** Restpflege hat (Regel als Teil von D-20-01).

### 1.5 Δ zu R0-18 §1.3 (bei Annahme von D-20-01/02)

| Zeile | bisher | neu |
|---|---|---|
| M-U | PHP 8.2 | **PHP 8.3** |
| M-M | PHP 8.3 / MySQL 8.4 | **PHP 8.4** / MySQL 8.4 (damit läuft jede unterstützte PHP-Minor in einer Pflichtzeile) |
| M-I1 | PHP 8.4 / WP-Beta | PHP 8.5 / WP-Beta |
| M-I2 | PHP 8.2, MySQL 8.0 / MariaDB 10.6 | PHP 8.3, MySQL 8.0 / MariaDB 10.6 („ohne Gewähr“, D-20-02) |
| **M-I4** (neu) | – | PHP 8.5, WP 7.1, MariaDB 11.4 bzw. 11.8 (im Wechsel nach Wochentag), nightly informativ, Integration |
| **M-I5** (neu, ab GA) | – | PHP 8.5, WP 7.1, nächste MySQL-LTS, nightly informativ |
| **M-L** (neu) | – | OpenLiteSpeed + LSCache, MariaDB 12.3, nightly informativ: Integration-Teilmenge, H-Proben (KOMP-014/031) |
| **M-K** (neu) | – | wöchentlich: automatisierbare Kompatibilitätsprofile KP-01…KP-14 ohne Lizenzplugins (§4), `continue-on-error`, Ergebnisbericht als Artefakt |

---

## 2. Konfliktmatrix

Spalten: **Risiko** h/m/n (hoch/mittel/niedrig, nach Schaden × Wahrscheinlichkeit). **Erwartetes Verhalten** = Soll für CC. **Test** = KOMP-ID (Ausführung R1-30, sofern nicht anders angegeben). **Stufe** nach §1.1. Herstellerspezifische Hook- und Filternamen fremder Plugins stammen **aus Modellwissen** und werden beim ersten R1-30-Lauf gegen die jeweils aktuelle Plugin-Version geprüft (Vermerk „(v)“).

### 2.1 Multisite (Grenze nach R0-25)

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-001 | Netzwerkaktivierung (UI, WP-CLI `--network`, erzwungen per DB) | h | blockiert bzw. Haltemodus, nichts wird geschrieben (MS-R-01/02) | = MS-001…004 (M-MS) | **zugesichert** (Blockade) |
| KOMP-002 | Aktivierung je Site im Netzwerk (Subdirectory) | m | volle Funktion je Site, strikte Isolation (Tabellen, Caps, Cron, Rewrite, Uploads) | = MS-005…021 (M-MS) | **getestet** (Isolation ist Sicherheitspflicht und wird jede Nacht geprüft; Funktion ohne Supportzusage, E-07) |
| KOMP-003 | Multisite als Subdomain bzw. mit Domain-Mapping | m | wie KOMP-002; Cookies und Nonces je Domain, `home_url()` je Site | Smoke in KP-11 (Subdomain-Variante) | best effort |
| KOMP-004 | Multisite und gemeinsamer Redis | h | kein Cache-Treffer über Site-Grenzen | = CON-026, MS-022 | **getestet** |
| KOMP-005 | Netzwerkweite Nutzerverwaltung (Super-Admin, Spam, Löschung) | m | wie MS-016…019 | M-MS | **getestet** |

**Grenze (Doku, readme):** „Multisite: Aktivierung nur je Website. Netzwerkaktivierung wird verhindert. Netzwerkweite Einstellungen, gemeinsame Kurse oder Lernstände über Sites hinweg gibt es nicht.“ (Konzept 23.1, E-07, E-49, NS-01…07.)

### 2.2 Webserver und Proxy

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-010 | Apache 2.4 + mod_php bzw. PHP-FPM, `AllowOverride All` | n | `.htaccess` sperrt `packages`/`protected`; Medienordner ohne Skriptausführung; ohne `fastcgi_finish_request` (mod_php) laufen Nacharbeiten synchron (LB-K-08) | M-Z, MED-006 | **zugesichert** |
| KOMP-011 | Apache mit `AllowOverride None` | h | `.htaccess` wirkt nicht → Health-Check `storage = exposed` (kritisch), Regeltext angezeigt; geschützte Dateien bleiben über die PHP-Route geschützt, wenn sie außerhalb des Webroots liegen | KP-05-Variante; HTTP-Probe wie MED-007 | **getestet** |
| KOMP-012 | nginx + PHP-FPM ohne `.htaccess` | h | Health `exposed` ohne Regel, `ok` mit der angezeigten Regel; X-Accel-Redirect optional (R0-10 §4) | M-N, MED-007 | **zugesichert** |
| KOMP-013 | nginx `fastcgi_cache` | h | H-X-Antworten tragen `X-Accel-Expires: 0` und `no-store`; kein HIT für personalisierte URLs | CON-031 (H-Probe) | **zugesichert** |
| KOMP-014 | LiteSpeed Enterprise / OpenLiteSpeed | m | `.htaccess`-Regeln werden meist ausgewertet, trotzdem Selbsttest; `X-LiteSpeed-Cache-Control: no-cache` auch ohne LSCache-Plugin; `litespeed_finish_request` wird genutzt | M-L (OpenLiteSpeed); LiteSpeed Enterprise manuell bei einem Hoster in KP-04 | **getestet** |
| KOMP-015 | Reverse-Proxy/TLS-Terminierung (Caddy, nginx, Load Balancer) | m | `is_ssl()` korrekt, wenn `X-Forwarded-Proto` von vertrauenswürdigen Proxys kommt (`course_captain_trusted_proxies`); Rate-Limits nach echter Client-IP; ohne Konfiguration wird die Remote-Adresse verwendet (sicherer Standard) | KP-12 | **getestet** |
| KOMP-016 | Permalinks „Einfach“ (Plain) | m | Lernbereich und `/cc-file/` über Query-Fallback erreichbar; Bereitschaftsprüfung zeigt Warnung (D-20-08) | Integration in M-Z (Option umschalten) | **getestet** |
| KOMP-017 | `PATH_INFO`-Permalinks (`/index.php/lernen/`) | n | Links über `home_url()`/Rewrite-API, keine fest kodierten Pfade | Smoke | best effort |
| KOMP-018 | Microsoft IIS, PHP-Built-in-Server im Produktivbetrieb | – | – | – | **nicht unterstützt** (Built-in-Server nur CI, R0-14 §3) |

### 2.3 Objektcache

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-020 | kein persistenter Objektcache | n | Standardpfad, Transient-Fallback für K0/K1 (R0-21 CB-09) | M-Z | **zugesichert** |
| KOMP-021 | Redis-Drop-in (Redis Object Cache) | h | nur K0/K1/K1g/K2 persistent, K3 nie; `blog_id` im Schlüssel; Korrektheit unabhängig vom Cache | M-R, CON-020/021 | **zugesichert** |
| KOMP-022 | Memcached-Drop-in (ohne `flush_group`) | m | Generationsschlüssel statt Gruppen-Flush (CB-09); Werte > 1 MB werden nicht gecacht, sondern neu berechnet | KP-06 | **getestet** |
| KOMP-023 | Object Cache Pro (kommerziell) | m | wie KOMP-021 | KP-03, falls eine Lizenz vorhanden ist (D-20-07) | best effort |
| KOMP-024 | Cache fällt im Betrieb aus bzw. wird während einer Invalidierung neu gestartet | h | Zugriff bleibt korrekt (K3), K2 höchstens 300 s alt | CON-021 | **zugesichert** |
| KOMP-025 | Objektcache über LiteSpeed Cache (Redis/Memcached) | m | wie KOMP-021/022 | KP-04 | **getestet** |

### 2.4 Page-Cache-Plugins und Optimierer

Grundregel aus R0-21 §3.5: Alle H-X-Antworten setzen `no-store, private`, `DONOTCACHEPAGE`, die LiteSpeed-Aktion samt Header, `Surrogate-Control` und `CDN-Cache-Control`. Das gilt für alle Statuscodes und auch für Gäste (H-2/H-3). Bei später Erkennung wird fail-closed gerendert (H-6). Nach Veröffentlichung oder Rückzug folgt ein Purge über `course_captain_cache_purge` (H-7, D-20-05).

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-030 | **WP Rocket** (Page-Cache, Preload, „Cache für eingeloggte Nutzer“) | h | `DONOTCACHEPAGE` wird respektiert; kein Cache-Eintrag für `/lernen/*`, `/cc-file/*`, geschützte Seiten; Preload-Crawler erzeugt keine Einträge (no-store) | KP-03: CON-031-Muster mit zwei Nutzern und Gast; Prüfung des Cache-Verzeichnisses | **getestet** (kommerziell, D-20-07) |
| KOMP-031 | WP Rocket „Delay JS“/„Minify/Combine JS“/„Remove Unused CSS“ | h | Lern-App und Prüfungs-UI starten; eigene Skript-Handles sind von Delay/Combine ausgenommen (D-20-04); ohne Ausschluss: Systemstatus-Hinweis | E2E-Smoke Lernen und Prüfung in KP-03 | **getestet** |
| KOMP-032 | **LiteSpeed Cache** (Page-Cache, „REST-API cachen“, Guest Mode, ESI, JS-Optimierung) | h | `litespeed_control_set_nocache` und Header wirken auf Seiten **und** REST; Guest Mode liefert keinen personalisierten Inhalt; JS-Ausschlüsse wie KOMP-031; Purge über LSCache-Aktion (v) | M-L, KP-04 | **getestet** |
| KOMP-033 | **W3 Total Cache** Page-Cache „Disk: Enhanced“ (statische Auslieferung per Rewrite ohne PHP) | h | Erste Antwort setzt `DONOTCACHEPAGE`, also wird keine statische Datei erzeugt; danach Probe auf eine statische Kopie | KP-06 | **getestet** |
| KOMP-034 | W3TC **Datenbank-Cache** bzw. Objektcache mit Query-Ergebnis-Cache | h | Ein Query-Cache kann K3-Abfragen (Grants, Sperren) veraltet liefern. Systemstatus „kritisch“, Doku-Empfehlung „DB-Cache aus“ (D-20-06) | KP-06: CON-020-Muster mit aktivem DB-Cache | **nicht unterstützt** (mit Erkennung) |
| KOMP-035 | **WP Super Cache** (Simple und Expert/mod_rewrite) | m | `DONOTCACHEPAGE` wird respektiert; „Nicht für bekannte Nutzer cachen“ (Standard an) ist nicht die Schutzgrundlage (H-2) | KP-05 | **getestet** |
| KOMP-036 | Autoptimize, Perfmatters, Asset CleanUp (JS-Optimierer) | m | wie KOMP-031 über deren öffentliche Ausschlussfilter (v) | Smoke | best effort |
| KOMP-037 | Hoster-Caches (Varnish, SiteGround Dynamic Cache, Kinsta, WP Engine, Cloudways Breeze) | h | Header H-X genügen; Varnish „cache everything“ ist der Referenztest | CON-031 (Varnish), sonst Doku | **getestet** (Varnish), übrige best effort |

### 2.5 CDN (Cloudflare)

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-040 | Cloudflare Standard (nur statische Assets gecacht) | n | Assets versioniert (`?ver=`), HTML ungecacht | KP-12 manuell mit echter Zone | **getestet** |
| KOMP-041 | Cloudflare „Cache Everything“ bzw. Cache Rules mit Origin-Headern | h | `CDN-Cache-Control: no-store`/`no-store, private` verhindern Edge-Caching von H-X-Antworten | KP-12 manuell; in der CI stellvertretend Varnish (CON-031/036) | **getestet** |
| KOMP-042 | Cloudflare „Edge-TTL überschreiben“ (ignoriert Origin-Header) bzw. APO ohne Cookie-Bypass | h | **Nicht beherrschbar** durch Header. Doku: Bypass-Regel für `/lernen/*`, `/cc-file/*`, `/wp-json/course-captain/*`, Kursseiten mit Gate. Health-Probe: Antwort mit `cf-cache-status: HIT` auf einer H-X-URL → kritisch | KP-12 manuell (Negativprobe) | **nicht unterstützt** (mit Erkennung) |
| KOMP-043 | Cloudflare Rocket Loader | m | eigene Skripte mit `data-cfasync="false"` (D-20-04); Lern-App startet | KP-12 manuell | **getestet** |
| KOMP-044 | Cloudflare WAF/Bot Fight Mode auf REST-POSTs | m | keine Sonderbehandlung; Doku zu Ausnahmen; der Client meldet 403 ohne JSON verständlich („Anfrage von einer Firewall blockiert“) | KP-12 Smoke | best effort |
| KOMP-045 | Client-IP hinter Cloudflare | m | `CF-Connecting-IP` zählt nur bei konfigurierten vertrauenswürdigen Proxys (KOMP-015) | KP-12 | **getestet** |

### 2.6 Security-Plugins und REST-Sperren

E-10: CC Free begrenzt keine Logins und härtet die Website nicht. Doppelte Sperren wie in LB (LB-K-06) gibt es deshalb nicht. Die CC-eigenen Rate-Limits (Einlösen, Beitreten, Verifikation, Antworten) bleiben bestehen und sind unabhängig.

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-050 | **Wordfence** (Firewall „Extended Protection“, Rate Limiting, Login Security/2FA) | m | Lernen, Prüfung und Import funktionieren mit den Standardeinstellungen. Paket-Upload oder Fragetexte mit Code-Beispielen können WAF-Fehlalarme auslösen; dafür gibt es einen Doku-Abschnitt zu „Learning Mode“/Allowlist. 2FA beeinflusst CC nicht | KP-03 (Wordfence free) | **getestet** |
| KOMP-051 | **Solid Security** (ehem. iThemes) mit „REST API: eingeschränkter Zugriff“, „Hide Backend“ | h | Gäste-Routen (Katalog, Zertifikatsverifikation R-42) können blockiert werden. Die öffentliche Verifikationsseite rendert serverseitig ohne REST (D-20-09). Login-Links nur über `wp_login_url()`. Systemstatus-Hinweis bei erkannter REST-Einschränkung | KP-04 | **getestet** |
| KOMP-052 | generische „REST-API deaktivieren“-Plugins/Snippets (`rest_authentication_errors` für Gäste) | h | Eingeloggte Lernende nicht betroffen; Gäste-Funktionen wie KOMP-051; Health-Check `rest_guest_blocked` | Integration mit Fixture-Snippet | **getestet** |
| KOMP-053 | All-In-One WP Security, Sucuri, Shield | m | wie KOMP-050/052 | Smoke | best effort |
| KOMP-054 | ModSecurity + OWASP CRS beim Hoster | m | JSON-Bodies mit HTML (Fragen-Editor, Import) können 403 auslösen; der Client meldet „Firewall“ (wie KOMP-044); Doku mit Regel-IDs aus dem Testlauf | KP-13 (Container mit CRS, Paranoia-Level 1) | **getestet** |
| KOMP-055 | Rollen-/Cap-Editoren (Members, User Role Editor) | m | `cc_*`-Caps sind sichtbar und zuweisbar; CC prüft Caps, nie Rollennamen (R0-07); Entfernen einer Cap wirkt sofort (P-11) | KP-08 | **getestet** |
| KOMP-056 | Mail-Plugins (WP Mail SMTP, FluentSMTP, Post SMTP) inkl. **Mail-Log** | m | Versand nur über `wp_mail()`; Mail-Logs speichern Mailtexte und damit kurzlebige Einladungs-/Claim-Links (K4) → Systemstatus-Hinweis „Mail-Log aktiv: Links in Einladungen werden mitprotokolliert“; Tokens laufen ab (R0-09) | KP-08 | **getestet** |

### 2.7 SEO-Plugins (Kap. 21; Gate 23.2 Nr. 6)

Regel: Ist ein SEO-Plugin aktiv (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework), gibt CC **keine** eigenen Meta-Description-, Canonical-, OpenGraph- oder JSON-LD-Tags aus. Robots-Angaben setzt CC über `wp_robots` und zusätzlich über die Robots-Filter des Plugins. Schema-Ergänzungen (`Course`, `BreadcrumbList`) gibt es nur über die Schema-API des Plugins und nur, wenn der Betreiber sie aktiviert (R1-37). CC ändert keine Optionen fremder Plugins (LB-K-05).

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-060 | **Yoast SEO** | h | Kursdetailseite: genau ein Canonical, eine Description, ein `og:title`, kein zweiter `Course`-Graph; `/lernen/*`, `/lernen/nachweis/*`, geschützte Lektionen und Gruppen-/Einladungsseiten `noindex` (`wpseo_robots` (v)); Sitemap ohne `cc_module`/`cc_lesson`/`cc_tip`, ohne Entwürfe (`wpseo_sitemap_exclude_post_type` (v)); Breadcrumbs für `cc_course` | KP-03, KP-07: HTML-Parser zählt Tags; Sitemap-Abruf | **zugesichert** |
| KOMP-061 | **Rank Math** | h | wie KOMP-060 über `rank_math/frontend/robots`, `rank_math/sitemap/exclude_post_type`, `rank_math/json_ld` (v) | KP-04 | **getestet** |
| KOMP-062 | **ohne SEO-Plugin** | m | CC gibt für die Kursdetailseite Description, OG und (optional) JSON-LD aus; Canonical von WP-Kern; `noindex` über `wp_robots`; Core-Sitemap ohne nicht öffentliche CPTs (`wp_sitemaps_post_types`) | M-Z (Integration + HTML-Probe) | **zugesichert** |
| KOMP-063 | AIOSEO, SEOPress, The SEO Framework | m | Erkennung → CC-Tags aus; `noindex` über `wp_robots` | Smoke | best effort |

### 2.8 Editor und Page-Builder

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-070 | Block-Editor (Gutenberg) inkl. iframe-Editor, synchronisierte Muster, Template-Parts | m | Blocks `course-captain/*` (apiVersion 3) im Editor und Frontend; Gate in Template-Part/synchronisiertem Block → H-6 fail-closed | M-Z E2E | **zugesichert** |
| KOMP-071 | Classic Editor (Plugin) bzw. klassischer Editor für CC-CPTs | m | Kursbau im eigenen Admin-Screen unverändert; Inhalte von Lektionen im TinyMCE; Shortcodes ersetzen Blocks (R0-19 §4.5) | KP-06 | **getestet** |
| KOMP-072 | **Elementor** (free) inkl. Theme Builder, Shortcode-Widget | h | Gate auch für Elementor-Layoutdaten (LB-K-07-Muster); Shortcode-Widget mit `[course_captain_access]` sperrt serverseitig; Theme-Builder-Vorlage für `cc_course` erlaubt, nicht für `/lernen/` (eigene Shell) | KP-03 | **getestet** |
| KOMP-073 | Elementor „Element Caching“ (Widget-Ausgabe gecacht) | h | gesperrter Inhalt darf nie aus dem Widget-Cache an andere Nutzer gehen. Erwartung: CC-Shortcodes und -Blocks melden sich als nicht cachebar (v); falls das nicht möglich ist, Systemstatus „kritisch“ und Doku | KP-03: zwei Nutzer, ein Gast | **getestet** |
| KOMP-074 | Elementor Pro, Divi, Beaver Builder, Bricks, WPBakery | m | Shortcodes funktionieren, Gate fail-closed (H-6) | Smoke (Lizenzen D-20-07) | best effort |

### 2.9 Themes

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-080 | **Twenty Twenty-Five** (Block-Theme) | m | Lernbereich, Kursdetail, Katalog-Block; Design-Tokens aus `theme.json` mit eigener Rückfallpalette; axe ohne Verstöße | M-Z E2E + axe | **zugesichert** |
| KOMP-081 | klassisches Theme (**Twenty Twenty-One**, R0-14 §3) | m | wie KOMP-080, Templates über `template_include`-Fallback | M-Z-Teilmenge E2E (nightly) | **zugesichert** |
| KOMP-082 | **Astra** | m | globale Button-/Formularstile des Themes überschreiben die Prüfungs-UI nicht (CSS über Layer/Container gekapselt, eigenes Präfix D-20-03); Kontrast bleibt AA (A11Y-042) | KP-03: E2E-Smoke, axe, visueller Vergleich | **getestet** |
| KOMP-083 | **GeneratePress** | m | wie KOMP-082 | KP-04 | **getestet** |
| KOMP-084 | Kadence, OceanWP, Hello Elementor, Divi-Theme | m | wie KOMP-082 | Smoke | best effort |
| KOMP-085 | Themes, die jQuery entfernen oder Skripte global verzögern | m | CC-Frontend braucht kein jQuery; Lern-App lädt als Modul | Smoke | best effort |

### 2.10 Übersetzungsplugins (nur Grenze, R0-25 Ü-4)

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-090 | **WPML** | m | Koexistenz: kein Fatal, keine doppelten Tags, Lernbereich in korrekter Sprache; `cc_`-CPTs **nicht** übersetzbar (Systemstatus-Hinweis, D-25-06) | = LOC-028 in KP-07 (Lizenz D-20-07) | **getestet** (Koexistenz) |
| KOMP-091 | **Polylang** | m | wie KOMP-090 (`pll_is_translated_post_type`) | = LOC-028 in KP-07 | **getestet** (Koexistenz) |
| KOMP-092 | TranslatePress | m | Lernbereich und Lern-REST ausgenommen (Doku) | Smoke | best effort |
| KOMP-093 | Weglot, GTranslate (Proxy-/JS-Übersetzung) | h | Übersetzung der Prüfungstexte verfälscht die Snapshot-Treue | – | **nicht unterstützt** für Lernbereich und Prüfung |

### 2.11 WooCommerce (R1: nur Koexistenz, Adapter erst R3.1)

| ID | Kombination | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|
| KOMP-100 | ohne WooCommerce | n | Normalfall aller CI-Zeilen | J-22 | **zugesichert** |
| KOMP-101 | WooCommerce aktiv (HPOS an und aus, Block-Checkout) | m | CC registriert keine Woo-Hooks, keine „Mein Konto“-Endpunkte (K-07) und lädt keine Assets auf Woo-Seiten; Woo lädt nichts in `/lernen/`; Aktivierungsreihenfolge beliebig; `shop_manager` erhält keine `cc_*`-Caps (D07-6) | KP-08 | **getestet** |
| KOMP-102 | Woo „Admin-Zugang verhindern“ für Rollen ohne `edit_posts` | h | **Konflikt:** `cc_trainer`, `cc_support` und ggf. `cc_author` würden aus `wp-admin` auf „Mein Konto“ umgeleitet, die Admin-Leiste wäre ausgeblendet. Soll: Die CC-Rollen haben Zugang zu ihren CC-Screens (D-20-10) | KP-08: Login als `cc_trainer` → `admin.php?page=course-captain-reports` erreichbar | **zugesichert** (nach D-20-10) |
| KOMP-103 | Action Scheduler doppelt (WooCommerce bündelt ihn, CC nutzt ihn ggf. ebenfalls, R0-19 §4) | m | Es lädt die neueste Kopie; CC-Gruppe `course-captain` bleibt getrennt; Deaktivierung von CC entfernt nur eigene Aktionen | KP-08 | **getestet** |
| KOMP-104 | Woo „Coming soon“-Modus (ganze Site) | n | Gäste sehen auch `/lernen/` nicht, Eingeloggte schon; keine Fehlfunktion | KP-08 Smoke | best effort |
| KOMP-105 | Woo-Bestellungen → Grants | – | – | – | **nicht in R1** (R3.1, E-53) |

### 2.12 Andere LMS und LearnBoating (Koexistenz)

Ziel: Mehrere LMS laufen gleichzeitig ohne Fatal, Notice, Datenänderung oder Namenskollision. Es gibt **keine** Datenübernahme aus anderen LMS (kein R1-Umfang). Bekannte Kennungen fremder LMS stammen aus Modellwissen (v) und werden beim ersten Lauf per `wp post-type list`, `wp role list` und `wp rewrite list` erfasst.

| ID | Kombination | Bekannte Kennungen (v) | Risiko | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|---|---|
| KOMP-110 | **LearnDash** | CPTs `sfwd-courses`, `sfwd-lessons`, `sfwd-topic`, `sfwd-quiz`, `sfwd-question`, `groups`; Slugs `courses`, `lessons`, `topic`, `quizzes`; Rolle `group_leader`; REST `ldlms/v1`, `ldlms/v2` | m | keine Überschneidung; der CC-Kursslug (D-20-11) kollidiert nicht mit `courses`; die Kollisionsprüfung F4.1 erkennt fremde CPT-Slugs | KP-09 (Lizenz D-20-07) | **getestet** |
| KOMP-111 | **Tutor LMS** | CPTs `courses`, `lesson`, `tutor_quiz`, `tutor_assignments`; Rolle `tutor_instructor`; REST `tutor/v1`; Dashboard-Seite | m | wie oben | KP-09 | **getestet** |
| KOMP-112 | **LifterLMS** | CPTs `course`, `lesson`, `llms_quiz`, `llms_membership`; Rollen `instructor`, `instructors_assistant`, `lms_manager`, `student`; REST `llms/v1` | m | wie oben; Rollen-Anzeigenamen dürfen ähnlich sein, Rollenschlüssel nicht | KP-09 | **getestet** |
| KOMP-113 | Sensei LMS, MasterStudy, LearnPress | – | m | wie oben | Smoke | best effort |
| KOMP-114 | **learnboating** @ `9f37c77` | LB-K-09 | m | J-21: keine Kollision bei Tabellen, Optionen, Rollen, Cron, REST, Shortcodes, Textdomains; Uninstall von CC lässt LB unberührt | J-21 (nightly, E-194) | **zugesichert** |

### 2.13 Weitere Umgebungseinflüsse (Kurzliste)

| ID | Kombination | Erwartetes Verhalten | Test | Stufe |
|---|---|---|---|---|
| KOMP-120 | `DISABLE_WP_CRON` ohne System-Cron | Systemstatus-Warnung, Fristen werden beim Zugriff geprüft (R0-14 R-S) | KP-05-Variante | **getestet** |
| KOMP-121 | Backup-/Migrations-Plugins (UpdraftPlus, Duplicator, WP Migrate) | `{prefix}cc_*` ist im Backup enthalten; nach Restore Reconciliation und Haltemodus bei Identitätswechsel (R0-16) | KP-05 Smoke | best effort |
| KOMP-122 | Health Check & Troubleshooting (Troubleshooting-Modus) | CC funktioniert allein mit Standard-Theme; Support-Ablauf R0-13 | Smoke | best effort |

---

## 3. Namenskonflikt-Prüfung

### 3.1 Kennungen und Risiko

Kollisionsbekannte Fremdkennungen stammen aus Modellwissen (v). Zeichenlimits gelten nach WordPress-Kern: CPT ≤ 20, Taxonomie ≤ 32, Option ≤ 191, Transient ≤ 172 Zeichen.

| Fläche | CC-Kennung (R0-19 §1.2 und weitere) | Kollisionsrisiko | Prüfmechanismus | Test |
|---|---|---|---|---|
| Hooks, PHP-Funktionen, Konstanten | `course_captain_*`, `COURSE_CAPTAIN_*`, Namespace `CourseCaptain\` | n | Statischer Scan: jeder `add_action`/`apply_filters`/`do_action` mit eigenem Namen beginnt mit `course_captain_` | KOMP-130 |
| DB-Tabellen | `{prefix}cc_*` (39 + 1, R0-06) | m (`cc_` ist kurz; fremde `{prefix}cc_*`-Tabellen sind denkbar) | Aktivierung: Existiert eine Zieltabelle **ohne** CC-Eigentumsmarke (Tabellenkommentar `course-captain:<schema>`, D-20-03), bricht die Aktivierung mit Meldung ab, ohne etwas zu schreiben. Uninstall löscht nur markierte Tabellen | KOMP-131 |
| CPTs | `cc_course`, `cc_module`, `cc_lesson`, `cc_faq`, `cc_tip` (≤ 20 Zeichen ✓) | n | `post_type_exists()` vor der Registrierung → bei Fremdbelegung Systemstatus „kritisch“ und kein Überschreiben | KOMP-131 |
| CPT-Rewrite-Slugs | `cc_course`: konfigurierbarer Slug, Vorschlag `kurs` (D-20-11); `cc_module`/`cc_lesson`/`cc_tip`: **keine** öffentliche Permalink-Struktur (`publicly_queryable = false`, Darstellung nur im Lernbereich), `cc_faq` nach E-16 | m (`course`/`courses`/`lessons` sind von LifterLMS/Tutor/LearnDash belegt) | F4.1 (R0-11): Kollisionsprüfung gegen Seiten-/Beitrags-Slugs, registrierte CPT-/Taxonomie-Slugs, Kategorie-/Schlagwort-/Autorenbasis und vorhandene Rewrite-Regeln (`$wp_rewrite->wp_rewrite_rules()`); Speichern erst nach Bestätigung | KOMP-132 |
| Lernbereich | `/lernen/` (änderbar, Kap. 43.3), darunter `/lernen/nachweis/<token>` | m (deutsche Sites haben oft eine Seite „lernen“; CC-Regeln mit Priorität `top` würden sie verdecken) | wie oben; Rewrite-Flush nur nach Speichern bzw. Aktivierung, nie in normalen Requests | KOMP-132 |
| Dateiroute | `/cc-file/<uid>/` | n | wie oben | KOMP-132 |
| Query-Vars | Vorschlag `course_captain_app`, `course_captain_path` (statt kurzer `cc_*`-Namen) | m (Query-Vars sind global, kurze Namen kollidieren) | Scan registrierter Query-Vars | KOMP-130 |
| Shortcodes | `[course_captain_access]`, `[course_captain_learning_link]`, `[course_captain_privacy_request]` | n | `shortcode_exists()` vor `add_shortcode` → Systemstatus | KOMP-131 |
| Blocks | Namespace `course-captain/*` (5 Blocks), Script-/Style-Handles `course-captain-*`, Interactivity-Store `course-captain` | n | `WP_Block_Type_Registry::is_registered()` | KOMP-131 |
| REST | `course-captain/v1`; CPT-`rest_base` unter `wp/v2` mit Rechten nach RG-21 | n | Routen-Snapshot (J-12 CT, C-7); Prüfung, dass kein fremder Namespace bedient wird | J-12 |
| Rollen | `cc_course_manager`, `cc_author`, `cc_trainer`, `cc_support` | n (fremd: `group_leader`, `tutor_instructor`, `instructor`, `student`, `lms_manager`) | `get_role()` vor `add_role` → existiert eine Rolle ohne CC-Marke, wird sie **nicht** verändert, Systemstatus „kritisch“ | KOMP-131 |
| Capabilities | `cc_*` | n | Scan; Uninstall entfernt nur eigene Caps (P-10) | J-21 |
| Optionen, Transients, User-Optionen | bisher `cc_*` (R0-19 §1.2) → Vorschlag `course_captain_*` (D-20-03) | **m** (Optionen sind siteweit, Kollisionen überschreiben still; `cc_` ist bei Cookie-Consent- und Credit-Card-Plugins verbreitet, R0-19 §1.2) | Ausnahmeliste und statischer Scan (MS-008-Muster) | KOMP-130 |
| Post-Meta | `_cc_*` (z. B. `_cc_activity_type`) | n (nur an eigenen CPTs) | Scan | KOMP-130 |
| Cron-Hooks, Action-Scheduler-Gruppe | `cc_*` / `course-captain` | n | Liste `CRON_HOOKS`, Deaktivierung trägt nur diese aus | J-21 |
| AJAX/admin-post-Aktionen, Nonce-Aktionen | bisher `cc_*` → Vorschlag `course_captain_*` (D-20-03) | m (`wp_ajax_<action>` ist global) | Scan | KOMP-130 |
| CSS-Klassen, Custom Properties | bisher `cc-` (R0-19 §1.2) → Vorschlag `ccap-` / `--ccap-*` (D-20-03) | **h** (verbreitete Cookie-Banner-Bibliotheken verwenden `cc-window`, `cc-banner`, `cc-btn`, `cc-revoke` bzw. `#cc-main`, `cc--*` (v)) | Stylelint-Regel „Selektorpräfix“; E2E-Fixture mit einem Cookie-Banner-Plugin in KP-03 | KOMP-133 |
| JS-Globale | `window.courseCaptain` (einziges Global) | n | ESLint `no-implicit-globals` | KOMP-130 |
| Plugin-Slug, Textdomain, Upload-Ordner, CLI | `course-captain`, `uploads/course-captain/`, `wp course-captain` | n, **aber:** Verfügbarkeit des Slugs auf WordPress.org ungeprüft | Offener Punkt OP-20-1 | – |
| Fehlercodes | `cc_*` | n (stehen im eigenen REST-Namespace) | – | – |

### 3.2 Tests

| ID | Inhalt | Art | Ziel-Task |
|---|---|---|---|
| KOMP-130 | **Präfix-Scan** (CI, J-05-Erweiterung): Alle registrierten Hooks, Query-Vars, Optionen, Transients, Meta-Schlüssel, AJAX-Aktionen und JS-Globale tragen das Präfix aus §3.1; Ausnahmeliste versioniert | statisch + Laufzeit-Inventar (`wp eval` nach Aktivierung) | R1-01, R1-28 |
| KOMP-131 | **Kollisions-Fixture** `cc-collider` (Test-Plugin, nur CI): registriert vorab `cc_course`, eine Tabelle `{prefix}cc_grant` ohne Marke, Rolle `cc_trainer` mit anderen Caps, Shortcode `course_captain_access`, Block `course-captain/access-gate`. Erwartung: Aktivierung wird bei der Tabelle abgebrochen, ohne etwas zu schreiben; bei den übrigen Kennungen gibt es kein Überschreiben, Systemstatus „kritisch“ mit Fundstelle und keinen Fatal | Integration | R1-01, R1-38 |
| KOMP-132 | **Slug-Kollisionen:** Seite `lernen`, Seite `kurs`, LifterLMS-CPT `course` mit Slug `course`, Kategoriebasis `kurs`. Erwartung: Feldfehler mit Alternativvorschlag (F4.1), kein Flush ohne Bestätigung, bestehende Seite bleibt erreichbar | Integration + E2E | R1-01, R1-16 |
| KOMP-133 | **CSS-Isolation:** Cookie-Banner-Bibliothek und Astra aktiv; die Lern- und Prüfungs-UI zeigt keine fremden Stile auf CC-Elementen und keine CC-Stile auf fremden Elementen (Screenshot-Vergleich) | E2E | R1-19 |
| KOMP-134 | **Mehr-LMS-Inventar:** In KP-09 werden `wp post-type list`, `wp role list`, `wp rewrite list`, REST-Index und Shortcode-Liste vor und nach der CC-Aktivierung verglichen: nur Zugänge mit CC-Präfix, keine Änderung an fremden Einträgen | Integration (Skript) | R1-30 |

---

## 4. Testinstallationen (Profile) für R1-30

Jedes Profil wird als Docker-Compose-Datei nach dem Muster von LB `staging/docker-compose.yml` beschrieben (`tests/compat/profiles/KP-xx/`, Pfad wird in R1-30 bestätigt). Plugin- und Theme-Versionen werden **gepinnt** (Lock-Datei je Profil mit Version und SHA-256). Vor jedem Minor-Release werden sie auf die dann aktuelle Version angehoben und das Ergebnis protokolliert. Kommerzielle Plugins liegen nie im Repository. Sie kommen aus einem privaten Artefaktspeicher des Auftraggebers und laufen nur manuell bzw. in einem privaten Runner (D-20-07). Protokoll je Lauf: Commit, Profil, Versionsliste, KOMP-Ergebnis (bestanden / Befund / nicht ausgeführt mit Grund), Befunde als Issues mit Label `compat`. Ablage `docs/r1/R1-30-kompatibilitaet-<datum>.md`.

| Profil | Stack | Plugins/Theme | Deckt KOMP | Automatisierbar | Rhythmus |
|---|---|---|---|---|---|
| **KP-01 Referenz** | PHP 8.5, WP 7.1, MariaDB 12.3, Apache 2.4 + FPM | Twenty Twenty-Five, keine Plugins | 010, 020, 062, 070, 080, 100 | ja (= M-Z) | PR |
| **KP-02 Untergrenze** | PHP 8.3, WP 7.0, MariaDB 10.11, Apache mod_php | Twenty Twenty-One | 010, 081 | ja (= M-U + E2E-Smoke) | nightly |
| **KP-03 Agentur-Stack** | PHP 8.4, WP 7.1, MySQL 8.4, nginx + FPM, Redis | Astra, Elementor (free; Pro bei Lizenz), Yoast, WP Rocket (Lizenz), Wordfence (free), Cookie-Banner-Plugin, Object Cache Pro (bei Lizenz) | 012, 013, 021, 023, 030, 031, 050, 060, 072, 073, 082, 133 | teilweise (ohne WP Rocket/Elementor Pro) | wöchentlich (M-K) + manuell je Minor |
| **KP-04 LiteSpeed-Hoster** | PHP 8.5, WP 7.1, MariaDB 11.4, OpenLiteSpeed (LiteSpeed Enterprise manuell bei einem Hoster) | GeneratePress, Rank Math, Solid Security, LiteSpeed Cache inkl. Objektcache (Memcached) | 014, 025, 032, 051, 061, 083 | ja (OLS) | wöchentlich + manuell je Minor |
| **KP-05 Shared Hosting (R-S)** | PHP 8.3, WP 7.0, MariaDB 10.11, Apache, Limits nach R0-14 R-S; Varianten `AllowOverride None` und `DISABLE_WP_CRON` | Twenty Twenty-Five, WP Super Cache (Simple + Expert), kein SEO-Plugin | 011, 035, 062, 120, 121 | ja | wöchentlich |
| **KP-06 W3TC/Classic** | PHP 8.4, WP 7.1, MariaDB 12.3, Apache + FPM, Memcached | Classic Editor, W3TC (Page-Cache Disk Enhanced, DB-Cache, Objektcache Memcached), Twenty Twenty-One | 022, 033, 034, 071 | ja | wöchentlich |
| **KP-07 Mehrsprachig** | PHP 8.5, WP 7.1, MariaDB 12.3, Apache | Polylang (CI), WPML (Lizenz, manuell), Yoast, Twenty Twenty-Five, Sprachen de_DE/en_US/ar | 060, 090, 091, LOC-028 | teilweise | wöchentlich (Polylang) + manuell je Minor (WPML) |
| **KP-08 Shop-Koexistenz** | PHP 8.5, WP 7.1, MySQL 8.4, Apache | WooCommerce aktuell (HPOS an/aus, Block-Checkout), WP Mail SMTP + Mail-Log, FluentSMTP (Variante), Members | 055, 056, 101, 102, 103, 104 | ja | wöchentlich |
| **KP-09 LMS-Koexistenz** | PHP 8.4, WP 7.1, MariaDB 12.3, Apache | Tutor LMS (free), LifterLMS (free), Sensei (free), LearnDash (Lizenz, manuell); Aktivierungsreihenfolge CC zuerst bzw. zuletzt | 110…113, 132, 134 | teilweise | wöchentlich + manuell je Minor |
| **KP-10 LB-Koexistenz** | wie M-Z | learnboating @ `9f37c77` (nur installiert) | 114 | ja (= J-21) | nightly |
| **KP-11 Multisite** | PHP 8.5, WP 7.1, MariaDB 12.3, Subdirectory + Subdomain-Variante, Redis geteilt | Twenty Twenty-Five | 001…005 | ja (= M-MS) | nightly |
| **KP-12 Proxy/CDN** | KP-01 hinter Caddy (TLS) + Varnish „cache everything“; manuell: echte Cloudflare-Zone (Standard, Cache Everything, Edge-TTL-Override, Rocket Loader, APO) | – | 015, 037, 040…045 | Varnish ja, Cloudflare manuell | wöchentlich + manuell je Minor |
| **KP-13 WAF** | KP-01 mit Apache + ModSecurity + OWASP CRS (PL 1) | – | 054 | ja | wöchentlich |
| **KP-14 Vorschau** | nächste PHP-Version, nächste WP-Beta/RC, MariaDB 11.8 / nächste MySQL-LTS | Twenty Twenty-Five | Versionsachsen | ja (= M-I1/I3/I4/I5) | nightly, informativ |

**Pflichtumfang je Profil (R1-30):** (1) Aktivierung, Deaktivierung und Reaktivierung ohne Notice (`WP_DEBUG` + `display_errors` off, Log leer). (2) Setup-Assistent bis zur Bereitschaftsprüfung. (3) E2E-Smoke Lernen, Prüfung mit Reload und Zertifikatsverifikation als Gast. (4) CON-031-Probe (zwei Nutzer und ein Gast) auf einer geschützten Lektion. (5) HTML-Tagzählung auf der Kursdetailseite (KOMP-060-Muster). (6) Health-Check-Ergebnis als JSON. (7) die zugeordneten KOMP-Fälle. Kostenbudget: Die automatisierbaren Profile laufen wöchentlich in M-K. R0-18 rechnet für die Nightly-Spur mit ≤ 120 min. M-K läuft deshalb eigenständig am Wochenende und zählt nicht zum Nightly-Budget.

**Abnahme R1-30:** Alle Fälle mit Stufe **zugesichert** sind bestanden. Bei **getestet** ist jeder Fall bestanden oder hat einen Befund mit Doku-/Systemstatus-Hinweis bzw. Fix. Die Verifikation der Lebenszyklusdaten (§1.4) und der mit (v) markierten Fremd-Hooks ist nachgetragen.

---

## 5. Entscheidungsbedarf (neu)

| Nr. | Frage | Optionen | Empfehlung | Ziel |
|---|---|---|---|---|
| **D-20-01** | PHP-Untergrenze (ändert E-57) | a) 8.2 wie E-57 · b) **8.3** · c) 8.4 | **b)**, verbunden mit der Regel „Untergrenze = älteste PHP-Version mit ≥ 6 Monaten Restpflege zum geplanten Release-Datum“ (vor R1-GATE neu bewerten). Begründung: 8.2 hat nach Modellwissen ab 01.01.2027 keine Sicherheitsupdates mehr (§1.4), CC hat noch keinen Installationsbestand, und M-U/M-M/M-Z decken dann 8.3/8.4/8.5 vollständig ab | R1-01, R1-18, R0-18 §1.3 |
| **D-20-02** | Status von MariaDB 10.6 / MySQL 8.0 (präzisiert E-58) | a) best effort wie E-58 · b) **„läuft ohne Gewähr“**: Aktivierung erlaubt (E-08), Systemstatus-Empfehlung, nightly informativ (M-I2), keine Fehlerbehebung nur für diese Versionen · c) Aktivierung verweigern | **b)**, weil beide nach Modellwissen bereits EOL sind, viele Shared-Hoster sie aber noch betreiben; c) würde Interessenten ohne Sicherheitsgewinn ausschließen | R1-01, R1-38 |
| **D-20-03** | Präfix-Deltas zu R0-19 §1.2 (Ergänzung zu D19-1) | a) R0-19 unverändert (`cc_` für Optionen/AJAX, `cc-` für CSS) · b) **`course_captain_` für Optionen, Transients, User-Optionen, AJAX/admin-post, Nonce-Aktionen, Query-Vars; `ccap-`/`--ccap-` für CSS; Tabellen bekommen die Eigentumsmarke `course-captain:<schema>` als Kommentar**; `cc_` bleibt für Tabellen, CPTs, Caps, Rollen, Cron, Meta, Fehlercodes | **b)**: Die gewählten Flächen sind global und ohne Längengrenze. Die CSS-Kollision mit Cookie-Bannern ist konkret (§3.1). Die Tabellenmarke macht Aktivierung und Uninstall sicher (KOMP-131) | R1-01, R1-19, R0-19 |
| **D-20-04** | Ausschlüsse für JS-/CSS-Optimierer (WP Rocket, LiteSpeed Cache, Autoptimize, Cloudflare Rocket Loader) | a) nur Doku · b) **CC registriert über deren öffentliche Filter Ausschlüsse nur für die eigenen Handles und setzt `data-cfasync="false"` an eigenen Skripten**; keine Änderung fremder Optionen; ohne Plugin wirkungslos · c) zusätzlich URL-Ausschlüsse für den Page-Cache in den Plugin-Optionen | **b)**: Defekte Optimierer sind laut LB-/Branchenerfahrung eine häufige Supportursache. b) greift nicht in fremde Konfiguration ein, c) schon | R1-19, R1-25 |
| **D-20-05** | Purge-Integrationen für H-7 in R1 | a) nur `course_captain_cache_purge` + `clean_post_cache` · b) **a) + mitgelieferte, lose gekoppelte Purge-Aufrufe für LiteSpeed Cache und WP Rocket** (`function_exists`/`has_action`-Schutz) · c) b) + W3TC, WP Super Cache, Cloudflare-API | **b)**: Das deckt die beiden verbreitetsten Page-Caches ab. Die Cloudflare-API bräuchte ein Secret (E-87) und gehört nach R2 oder Pro | R1-25 |
| **D-20-06** | Datenbank-/Query-Ergebnis-Caches (W3TC DB-Cache u. ä.) | a) **nicht unterstützt, Systemstatus „kritisch“ mit Doku** · b) Versuch, K3-Abfragen über Filter des Plugins auszunehmen (v) | **a)**, weil K3 nie gecacht werden darf (R0-21) und ein Ausschluss je Plugin-Version brüchig ist. b) erst, wenn R1-30 einen stabilen öffentlichen Filter belegt | R1-25, R1-38 |
| **D-20-07** | Kommerzielle Plugins und Themes im Testplan (WP Rocket, Elementor Pro, WPML, LearnDash, Object Cache Pro) | a) **Lizenzen stellt der Auftraggeber; Lauf nur manuell bzw. in einem privaten Runner vor jedem Minor-Release** · b) nur kostenlose Plugins, kommerzielle bleiben „best effort“ | **a)** für WP Rocket, WPML und LearnDash (stark verbreitet, zugesicherte/getestete Zeilen hängen daran); Elementor Pro und Object Cache Pro → b) | R1-30, Budget Auftraggeber |
| **D-20-08** | Permalinks „Einfach“ (Plain) | a) Bereitschaftsprüfung blockiert die Veröffentlichung · b) **Query-Fallback (`?course_captain_app=1&course_captain_path=…`) funktionsfähig + Warnung in der Bereitschaftsprüfung** | **b)**: Die Funktion bleibt erhalten, schöne URLs sind Empfehlung, keine Pflicht. WooCommerce und WP-Kern arbeiten genauso | R1-16, R1-01 |
| **D-20-09** | Öffentliche Zertifikatsverifikation (L-16) bei gesperrter REST-API für Gäste | a) nur über REST R-42 · b) **Seite `/lernen/nachweis/<token>` serverseitig gerendert, R-42 zusätzlich** | **b)**: Security-Plugins sperren REST für Gäste häufig (KOMP-051/052). Die Verifikation ist eine öffentliche Zusage und darf daran nicht scheitern | R1-16, R1-19, R0-19 R-42 |
| **D-20-10** | WooCommerce-Admin-Sperre für CC-Rollen (Delta zu R0-07) | a) nichts tun, Doku · b) **Die CC-Rollen `cc_trainer`, `cc_support`, `cc_author` erhalten die Cap `view_admin_dashboard`** (die WooCommerce für die Admin-Sperre auswertet (v)) · c) Filter `woocommerce_prevent_admin_access` für Nutzer mit `cc_*`-Caps | **b)**: Die Cap ist ein offizieller Kern-/Woo-Mechanismus und wirkt ohne Woo-Code im Kern (Z-6). c) wäre eine Woo-Kopplung in R1 | R1-03, R0-07 §3 |
| **D-20-11** | Öffentliche URL-Struktur der CPTs | a) alle CC-CPTs mit eigenem Permalink · b) **nur `cc_course` öffentlich, Slug konfigurierbar mit Vorschlag `kurs` (bei Aktivierung fest gespeichert, nicht übersetzt), `cc_module`/`cc_lesson`/`cc_tip` ohne öffentliche URL (nur im Lernbereich)**; `cc_faq` nach E-16 · c) Slug `course` | **b)**: Das hält die Kollisionsfläche mit anderen LMS klein (KOMP-110…112), reduziert Leckwege (RBAC-015) und passt zur SEO-Regel (Kap. 21). Übersetzte Slugs würden die URLs beim Sprachwechsel ändern | R1-04, R1-16, R1-37 |

**Offene Punkte ohne Entscheidung (Aufgaben):**
- **OP-20-1:** Verfügbarkeit des Slugs `course-captain` im WordPress.org-Plugin-Verzeichnis prüfen und reservieren (vor R1-39). Ohne Internetzugriff nicht geprüft.
- **OP-20-2:** Lebenszyklusdaten (§1.4) und die mit (v) markierten Fremd-Hooks/-Kennungen verifizieren, Ergebnis mit Datum und Quelle eintragen (vor R1-GATE).
- **OP-20-3:** Δ-20-1 (Klarstellung E-58) und §1.5 nach Beschluss in R0-14 §3, R0-18 §1.3 und `ENTSCHEIDUNGEN.md` übernehmen.

---

## 6. Zuordnung

| Ergebnis | Konzept | TODO | Weitere |
|---|---|---|---|
| §1 Versionsmatrix | Kap. 23.1 (WordPress), 23.2 | R0-20, R1-18, R1-01 | R0-14 §3, R0-18 §1.3, E-08/12/57/58 |
| §2.1 Multisite | Kap. 23.1 | R1-01, R1-17 | R0-25, E-07/49 |
| §2.2–2.5 Server/Cache/CDN | Kap. 22.3 | R1-25, R1-14, R1-38 | R0-21 §3, R0-10 §4, CON-020…047 |
| §2.6 Security, Mail | Kap. 20 | R1-30 | R0-17, E-10 |
| §2.7 SEO | Kap. 21, 23.2 Nr. 6 | R1-37 | LB-K-05 |
| §2.8/2.9 Builder, Themes | Kap. 10, 16 | R1-19 | R0-15, LB-K-07 |
| §2.10 Übersetzung | Kap. 21 | R1-30 | R0-25 Ü-4, LOC-028 |
| §2.11 WooCommerce | Kap. 7, 32 | R3-02 ff. (Adapter), R1-30 (Koexistenz) | R0-04 Z-5/Z-6/K-07, E-53 |
| §2.12 LMS/LB-Koexistenz | Kap. 2 (`konzept.md:64`), 23.2 Nr. 9 | R1-30, R1-39 | J-21, E-194 |
| §3 Namen | Kap. 13 | R1-01, R1-28 | R0-19 §1.2, §4.5/4.6, R0-07 |
| §4 Profile | – | R1-30 | R0-14 §2.1, R0-18 |
