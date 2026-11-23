# R1-30 · Konflikt- und Interoperabilitätstests – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-f` · **Commit:** siehe `git log` (Commit „feat(r1-30): …“)
**DoD (TODO R1-30):** Themes, Objektcache, REST-Security, Mail-/Cache-Plugins und unterstützte Versionen in Matrix getestet; Konflikte dokumentiert.
**Spezifikation:** `docs/r0/R0-20-kompatibilitaet-konflikte.md` (§2 KOMP-Matrix, §3 KOMP-130…134, §4 Profile).
**Clean Room (E-01):** Kein Code aus `hastla007/learnboating` oder aus fremden Plugins. Die Fixtures unter `tests/support/compat/` bilden nur **Namen** der öffentlichen Oberfläche nach (Konstanten, Klassen, Funktionen, Optionen, Filter). Alle fremden Hook-, Options-, Klassen- und Konstantennamen stammen aus Modellwissen und sind mit **(v)** markiert: Sie werden beim ersten Lauf der Profile gegen die aktuelle Plugin-Version geprüft (OP-20-2).

---

## 1. Umgebung und Grenzen dieses Laufs

| Punkt | Stand |
|---|---|
| Stack | PHP 8.3, WordPress 7.1.4 (`roots/wordpress-no-content`), `wp-phpunit` 7.1.3, MariaDB 10.11 (Datenbank `ccap_test_g`), Single-Site und Multisite-Suite |
| Kein Internet | Fremde Plugins und Themes können nicht geladen werden. Statt echter Plugins laufen **Stub-Tests** (Abschnitt 2) |
| Kein Docker, kein Browser | Die Profile KP-01…KP-14 (§4 der Spezifikation) laufen hier **nicht**. Sie sind unten als offen geführt. Es werden **keine** Profilergebnisse behauptet |
| Kommerzielle Plugins | nach E-196 ohne Lizenz: höchstens „best effort“ |

**Vorschlag Profilablage (bestätigt den Pfad aus R0-20 §4):** `tests/compat/profiles/KP-xx/` mit je `docker-compose.yml`, `plugins.lock` (Slug, Version, SHA-256), `run.sh` (Pflichtumfang §4 Nr. 0–7 als WP-CLI-/HTTP-Proben) und Ergebnisprotokoll `docs/r1/R1-30-kompatibilitaet-<datum>.md`. Die Stub-Tests dieses Laufs sind so geschnitten, dass `run.sh` sie mit echten Plugins wiederholen kann: Die Erwartungen (Systemstatus-ID und -Stufe, Hook-Zustand, Header) sind dieselben.

## 2. Stub-Fixtures (`tests/support/compat/`)

| Datei | Inhalt |
|---|---|
| `ForeignPlugin.php` | Ein „Plugin“: setzt Marker über `Compatibility::$stubs`, registriert Hooks/Optionen/Rollen/Typen in `setup`, entfernt sie in `teardown`; feuert `activated_plugin`/`deactivated_plugin` wie WordPress. `deactivate_all()` im Tear-down |
| `ForeignPlugins.php` | Katalog: Security-Plugins (Wordfence, Solid Security, LLAR, AIOS, Loginizer), Jetpack, XML-RPC-Sperre, Header-Plugin, REST-Gästesperre (Snippet, Solid-Variante), SEO (Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework), Mail (WP Mail SMTP, FluentSMTP, Post SMTP, WP Mail Logging), WooCommerce inkl. `shop_manager` und Emulation der Admin-Sperre, Polylang (`pll_is_translated_post_type` über `pll_get_post_types`), Elementor (Element-Caching-Option), `DISABLE_WP_CRON` an/aus, Tutor-LMS-/LifterLMS-Kennungen |
| `w3tc-dbcache-stub.php` | nur der Klassenname `W3TC\DbCache_Wpdb` (v) als Unterklasse von `wpdb` |
| `UnknownDropIn.php` | unbekannte `wpdb`-Unterklasse (Negativfall KOMP-034) |

**Warum ein Marker-Seam:** Konstanten und Klassen lassen sich in einem PHP-Prozess nicht wieder entfernen. Jede Erkennung fremder Plugins liest deshalb über `CourseCaptain\Ops\Compatibility::present()/constant()/has_class()/has_function()/call()`. Ohne Stub gilt das echte `defined()`/`class_exists()`/`function_exists()`. Dadurch sind Aktivieren **und** Deaktivieren („auto greift wieder“) im selben Lauf prüfbar.

## 3. Produktionsänderungen (Lücken aus der Spezifikation geschlossen)

| Datei | Änderung | KOMP |
|---|---|---|
| `src/Ops/Compatibility.php` (neu) | Marker-Seam; Erkennungskataloge (Security, SEO, DB-Query-Cache, Mail-Log, Polylang, Elementor); Kollisionsprüfung (CPT, Rolle, Shortcode, Block); Slug-Kollisionsprüfung F4.1 mit Alternativvorschlag; Registrierhelfer `add_shortcode()`/`register_block()` ohne Überschreiben; Systemstatus-Checks `name_collisions`, `slug_conflicts`, `rest_guest_blocked`, `db_query_cache`, `mail_log`, `wp_cron`, `permalinks`, `translation`, `element_cache`; Site-Health-Test `course_captain_compatibility`; Filter `pll_get_post_types` (v) nimmt die `ccap_`-Typen aus der Übersetzung (D-25-06) | 016, 034, 051, 052, 056, 073, 091, 120, 131, 132 |
| `src/Ops/OpsStatus.php` | Bericht enthält die Kompatibilitäts-Checks und den Härtungsstatus (`hardening`: welche Funktion wegen welchen Plugins ruht) | 057, alle Checks |
| `src/Security/Hardening.php` | Erkennung über den Seam; XML-RPC: eigener Callback `xmlrpc_off` statt `__return_false`, Erkennung einer fremden XML-RPC-Regel → `held:other_plugin`; Permissions-Policy wird auf CC-REST-Antworten **nicht** gesetzt, wenn ein anderes Plugin sie bereits gesetzt hat (E-307); Client-IP über `ClientIp` | 057, 058, 059, 015 |
| `src/Security/ClientIp.php` (neu) | `privacy.trusted_proxies` wird jetzt ausgewertet: ohne Konfiguration nur `REMOTE_ADDR`; von vertrauenswürdigen Proxys `CF-Connecting-IP`, dann `X-Forwarded-For` von rechts; `X-Forwarded-Proto: https` → `is_ssl()` korrekt. IPv4/IPv6-CIDR | 015, 045 |
| `src/Certificate/CertificateRoutes.php`, `CertificatePages.php` | Rate-Limit-Schlüssel über `ClientIp::get()` | 015, 045 |
| `src/Access/ContentProtection.php` | geschützte HTML-Antworten senden zusätzlich zu `DONOTCACHEPAGE` die H-X-Header (`no-store, private`, `X-LiteSpeed-Cache-Control`, `X-Accel-Expires: 0`, `Surrogate-Control`, `CDN-Cache-Control`) und die Aktion `litespeed_control_set_nocache` (v); Elementor-Layoutdaten geschützter Beiträge werden über `elementor/frontend/builder_content_data` (v) verworfen | 013, 014, 030–037, 041, 072 |
| `src/Course/PostTypes.php` | Eigentumsmarke `ccap_owner` am Post-Type-Objekt; ein fremder Typ gleichen Namens wird nicht überschrieben | 131 |
| `src/Policy/Roles.php` | `view_admin_dashboard` für alle vier CC-Rollen (D-20-10); fremde Rolle gleichen Namens (ohne `ccap_*`-Cap) bleibt unverändert; `Roles::owned()`, `Roles::BASE_CAPS` | 102, 131 |
| `src/Learner/LearningArea.php`, `src/Access/ProtectedFiles.php` | Query-Vars umbenannt nach D-20-03: `ccap_learn` → `course_captain_app`, `ccap_file` → `course_captain_file` (Befund F-01); Plain-Permalinks: Links als `?course_captain_app=…` bzw. `?course_captain_file=…`; Shortcodes/Blocks über die Kollisionshelfer | 016, 130, 131 |
| `src/Kernel/Plugin.php` | `REWRITE_VERSION` 4 → 5 (neue Query-Vars); `ClientIp::apply_forwarded_https()` beim Boot; `Compatibility::register()` | 015, 130 |
| `src/Settings/Settings.php` | F4.1: geänderter Lernbereich-/Kursslug mit Kollision → `ccap_validation_failed` mit `data.slug_conflicts[field] = {conflicts, suggestion}`; Speichern (und Rewrite-Flush) erst mit `_confirm_slug_conflict` | 132 |
| `src/Seo/CourseSeo.php` | `mode()` erkennt zusätzlich AIOSEO, SEOPress, The SEO Framework (`other`) → kein eigenes JSON-LD | 063 |
| `src/Ops/IntegrationStatus.php` | Partner-Probe liest über den Seam (WooCommerce-Stub sichtbar) | 101 |

Keine neuen REST-Routen, keine neuen `course_captain_*`-Hooks (Snapshots CT-01/CT-05 unverändert), keine neuen Audit-Aktionen, keine neuen eigenen Optionen (UninstallManifest unverändert). Angepasste Bestandstests: `HardeningTest` (eigener XML-RPC-Callback), `ContentGateTest`/`LearnerTest` (URL-Erwartung mit gesetzter Permalink-Struktur; Plain-Fallback zusätzlich geprüft), `KernelContractTest` (Ausnahmeliste für drei fremde/Core-Hooks), `ManifestCoverageTest` (Registrierung über `register_type`), `PolicyUnitTest` (`view_admin_dashboard`).

## 4. Tests

| Datei | Tests |
|---|---|
| `tests/integration/CompatibilityTest.php` | `test_KOMP_057_security_plugins_auto_retreat`, `test_KOMP_058_xmlrpc_double_block`, `test_KOMP_059_header_precedence`, `test_KOMP_052_rest_guest_blocked`, `test_KOMP_034_db_query_cache`, `test_KOMP_056_mail_log`, `test_KOMP_060_063_seo_plugins`, `test_KOMP_101_102_woocommerce`, `test_KOMP_091_polylang`, `test_KOMP_073_elementor_element_cache`, `test_KOMP_072_elementor_layout_gate`, `test_KOMP_120_wp_cron_disabled`, `test_KOMP_016_plain_permalinks`, `test_KOMP_015_045_trusted_proxies`, `test_KOMP_03x_page_cache_signals`, `test_site_health_compatibility` |
| `tests/integration/CompatibilityNamesTest.php` | `test_KOMP_131_collider`, `test_KOMP_132_slug_collisions`, `test_KOMP_130_runtime_prefix_inventory`, `test_KOMP_134_multi_lms_inventory` |

Gruppe `R1-30` plus je Fall `KOMP-xxx`: `vendor/bin/phpunit -c phpunit-integration.xml.dist --group R1-30`. Beide Klassen laufen auch in der Multisite-Suite (Hauptsite).

**Gates dieses Commits:** `phpcs -q` 0 Fehler (bekannte Warnung `ImportTest.php:318`); PHPStan Stufe 8 0 Fehler; Unit-Suite grün; Integration Single-Site grün; Integration Multisite grün; `free-guard`, `free-guard --self-test`, `textdomain-lint` ohne Befund.

Hinweis PHPStan: In diesem Worktree ist `vendor/` teilweise auf `/home/user/wp-coursemaster/vendor` verlinkt; `vendor/bin/phpstan` lädt dann zwei Composer-Autoloader. Ausgeführt wurde deshalb `php vendor/phpstan/phpstan/phpstan analyse -c <worktree>/phpstan.neon.dist` aus einem Verzeichnis ohne `vendor/` (gleiche Konfiguration, gleiche Stufe).

## 5. Matrix aller KOMP-Fälle

Legende **Status:** **CI** = bestanden in der CI-Suite ohne fremdes Plugin · **Stub** = bestanden als Stub-Test (fremde Oberfläche emuliert, Namen (v)) · **offen KP-xx** = manuell bzw. im Profil auszuführen, hier nicht gelaufen · **n. u. + Erkennung** = nicht unterstützt, Erkennung im Systemstatus geprüft · **Doku** = nur dokumentierte Grenze.

### 5.1 Multisite (§2.1)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-001 | zugesichert | **CI** | `MultisiteTest` MS-001/MS-004 (`@group KOMP-001`), `NetworkGuardTest`, `LifecycleTest`, `tests/integration/cli/ms-002-network-activate.sh` | – |
| KOMP-002 | getestet | **CI** (Isolation) | `MultisiteTest`/`SchemaMultisiteTest` MS-005/012/014/015; Multisite-Suite inkl. `CompatibilityTest` | Subdirectory-Isolation in der Suite; Funktionsumfang je Site ohne Zusage (E-07) |
| KOMP-003 | best effort | offen KP-11 | – | Subdomain/Domain-Mapping braucht echte Hostnamen |
| KOMP-004 | getestet | **CI** (Schlüssel) / offen KP-11 | `CacheTest::test_CON_026_site_in_key` | `blog_id` im Schlüssel geprüft; geteilter Redis nur im Profil |
| KOMP-005 | getestet | **CI** teilweise | Multisite-Suite (MS-014/015) | MS-016…019 vollständig im Profil KP-11 |

### 5.2 Webserver und Proxy (§2.2)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-010 | zugesichert | **CI** (Regeln) / offen KP-01/02 | `ContentGateTest::test_protected_files` (GM-112: `.htaccess` mit `Require all denied`/`Deny from all`, `index.php`), `HealthChecks::test_storage` | Apache-Ausführung selbst nur im Profil |
| KOMP-011 | getestet | offen KP-05 (Variante) | – | HTTP-Probe gegen `AllowOverride None` braucht Apache |
| KOMP-012 | zugesichert | offen KP-03 | Hinweistext in `test_storage` | nginx-Regel nur im Profil prüfbar |
| KOMP-013 | zugesichert | **Stub** (Header) / offen KP-03 | `test_KOMP_03x_page_cache_signals` (`X-Accel-Expires: 0`, `no-store`), `CacheTest::test_CON_034_headers_on_errors` (REST, alle Statuscodes) | **F-03:** geschützte HTML-Seiten sendeten bisher nur `nocache_headers()` → H-X-Header ergänzt |
| KOMP-014 | getestet | **Stub** (Header/Aktion) / offen M-L, KP-04 | `test_KOMP_03x_page_cache_signals` | `litespeed_finish_request` nicht Teil dieses Laufs |
| KOMP-015 | getestet | **Stub** | `test_KOMP_015_045_trusted_proxies` | **F-08:** Einstellung `privacy.trusted_proxies` existierte, wurde aber nirgends ausgewertet → `ClientIp` |
| KOMP-016 | getestet | **CI** | `test_KOMP_016_plain_permalinks`, `ContentGateTest::test_protected_files` (Fallback) | **F-02:** Links in den Lernbereich und auf `/cc-file/` waren bei Plain-Permalinks nicht erreichbar → Query-Fallback; Systemstatus `permalinks` = Warnung (Bereitschaftsprüfung gibt es im Code noch nicht, die Warnung steht im Systemstatus) |
| KOMP-017 | best effort | offen (Smoke) | – | Links laufen über `home_url()` |
| KOMP-018 | nicht unterstützt | Doku | – | – |

### 5.3 Objektcache (§2.3)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-020 | zugesichert | **CI** | `CacheTest` (CON-024/027/028/047, TTL/K1) ohne persistenten Cache | – |
| KOMP-021 | zugesichert | offen M-R / KP-03 | – | CON-020/021 brauchen Redis-Drop-in; nicht im Container |
| KOMP-022 | getestet | offen KP-06 | `CacheTest::test_CON_027_evicted_generation` (Generationsschlüssel) | Memcached nicht verfügbar |
| KOMP-023 | best effort | offen (keine Lizenz) | – | E-196 |
| KOMP-024 | zugesichert | **CI** teilweise | `CacheTest::test_CON_027_evicted_generation`, `test_bump_after_commit_only` | Neustart eines echten Cache-Servers nur im Profil |
| KOMP-025 | getestet | offen KP-04 | – | – |

### 5.4 Page-Cache und Optimierer (§2.4)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-030 | best effort | **Stub** (Header-Muster) | `test_KOMP_03x_page_cache_signals` | WP Rocket selbst ohne Lizenz (E-196) |
| KOMP-031 | best effort | offen (keine Lizenz) | – | Ausschlüsse eigener Handles (D-20-04) nicht Teil von R1-30 |
| KOMP-032 | getestet | **Stub** / offen M-L, KP-04 | `test_KOMP_03x_page_cache_signals` (Aktion `litespeed_control_set_nocache` (v) + Header), REST: `CacheTest::test_CON_034_headers_on_errors` | F-03 |
| KOMP-033 | getestet | **Stub** (DONOTCACHEPAGE) / offen KP-06 | `test_KOMP_03x_page_cache_signals` | statische Kopie nur im Profil prüfbar |
| KOMP-034 | nicht unterstützt | **n. u. + Erkennung** (Stub) | `test_KOMP_034_db_query_cache` | Systemstatus `db_query_cache` = **kritisch** bei `W3TC\DbCache_Wpdb` (v); unbekannter Drop-in = Hinweis |
| KOMP-035 | getestet | **Stub** / offen KP-05 | `test_KOMP_03x_page_cache_signals` | – |
| KOMP-036 | best effort | offen (Smoke) | – | – |
| KOMP-037 | getestet (Varnish) | **Stub** (Header) / offen KP-12 | `test_KOMP_03x_page_cache_signals`, `CacheTest::test_CON_034_headers_on_errors` | CON-031 braucht Varnish |

### 5.5 CDN (§2.5)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-040 | getestet | offen KP-12 (manuell) | – | echte Zone nötig |
| KOMP-041 | getestet | **Stub** (Header) / offen KP-12 | `test_KOMP_03x_page_cache_signals` (`CDN-Cache-Control: no-store`) | – |
| KOMP-042 | nicht unterstützt | offen KP-12 (Negativprobe) | – | Health-Probe `cf-cache-status` nicht umgesetzt (braucht ausgehende Probe; LG-1 verbietet HTTP in Tests) |
| KOMP-043 | getestet | offen KP-12 | – | `data-cfasync` gehört zu R1-19 |
| KOMP-044 | best effort | offen KP-12 | – | – |
| KOMP-045 | getestet | **Stub** | `test_KOMP_015_045_trusted_proxies` | `CF-Connecting-IP` nur von vertrauenswürdigen Proxys |

### 5.6 Security-Plugins, REST-Sperren, Mail (§2.6)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-050 | getestet | offen KP-03 | Erkennung Wordfence in `test_KOMP_057_*` | WAF-Fehlalarme nur mit echtem Wordfence |
| KOMP-051 | getestet | **Stub** / offen KP-04 | `test_KOMP_052_rest_guest_blocked` (Solid-Variante) | Verifikationsseite serverseitig ohne REST (D-20-09) geprüft |
| KOMP-052 | getestet | **Stub** | `test_KOMP_052_rest_guest_blocked` | **F-11:** Health-Check `rest_guest_blocked` fehlte → ergänzt (Warnung), Lernende nicht betroffen |
| KOMP-053 | best effort | Stub (Erkennung AIOS) | `test_KOMP_057_*` | Sucuri/Shield nur Erkennung Shield (v) im Katalog |
| KOMP-054 | getestet | offen KP-13 | – | – |
| KOMP-055 | getestet | **CI** teilweise / offen KP-08 | `PolicyTest::test_RBAC_097_roles_and_defaults`; CC prüft Caps, nie Rollennamen | Members/URE nur im Profil |
| KOMP-056 | getestet | **Stub** | `test_KOMP_056_mail_log` | **F-11:** Hinweis „Mail-Log aktiv“ fehlte → `mail_log` (WP Mail SMTP, FluentSMTP, Post SMTP, WP Mail Logging; (v)) |
| **KOMP-057** | **zugesichert (Pflicht)** | **Stub** / offen KP-03, KP-04 | `test_KOMP_057_security_plugins_auto_retreat` | alle fünf Plugins der LB-K-06-Liste: Login-/Reset-/Registrierungslimit `held:<plugin>`, keine CC-Hooks, kein CC-Lock nach 12 Fehlversuchen, Systemstatus `hardening` nennt Funktion und Plugin, nach Deaktivierung wieder `active`; `force` überstimmt. **F-14:** Vorschlag „aus“ im Setup-Assistenten (E-260) nicht prüfbar – es gibt im Code noch keinen Assistenten |
| **KOMP-058** | **zugesichert (Pflicht)** | **Stub** / offen KP-03, KP-04 | `test_KOMP_058_xmlrpc_double_block` | **F-10:** fremde XML-RPC-Regel wurde nicht erkannt → `held:other_plugin`; Jetpack → `held:jetpack`; `force` mit fremder Sperre ohne Fehler; Admin-Schalter `off` |
| **KOMP-059** | **zugesichert (Pflicht)** | **Stub** / offen KP-03, KP-04, KP-13 | `test_KOMP_059_header_precedence`, `HardeningTest::test_PSD_051_site_headers` | **F-04:** Permissions-Policy überschrieb einen fremden Wert auf CC-REST-Antworten (E-307) → behoben; kein Header doppelt, Groß-/Kleinschreibung egal; nicht auf fremden Routen (E-309). Grenze: Header, die der Webserver erst nach PHP setzt oder ein Plugin auf `send_headers` nach Priorität 99, sieht CC nicht → Header-Probe in KP-13 |

### 5.7 SEO (§2.7)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-060 | zugesichert | **Stub** / offen KP-03, KP-07 | `test_KOMP_060_063_seo_plugins`, `SeoTest` (SEO-001…005, 009, 010, 013, 025) | kein zweiter `Course`-Graph; keine Filter auf Yoast-Optionen (LB-K-05). Tagzählung im HTML nur im Profil |
| KOMP-061 | getestet | **Stub** / offen KP-04 | wie oben, Rank-Math-Filter (v) in `SeoTest::test_course_robots_and_plugin_filters` | – |
| KOMP-062 | zugesichert | **CI** | `SeoTest` (Robots, Sitemaps, JSON-LD) | – |
| KOMP-063 | best effort | **Stub** | `test_KOMP_060_063_seo_plugins` | **F-09:** AIOSEO/SEOPress/The SEO Framework wurden nicht erkannt → Modus `other` |

### 5.8 Editor und Page-Builder (§2.8)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-070 | zugesichert | offen M-Z E2E | `ApiContractTest::test_shortcodes_and_blocks` (apiVersion 3, registriert) | Editor-E2E braucht Browser |
| KOMP-071 | getestet | offen KP-06 | Shortcodes vorhanden (`ApiContractTest`) | – |
| KOMP-072 | getestet | **Stub** / offen KP-03 | `test_KOMP_072_elementor_layout_gate` | **F-12:** Elementor-Layoutdaten außerhalb `the_content` waren nicht gesperrt → Filter `elementor/frontend/builder_content_data` (v) |
| KOMP-073 | getestet | **Stub** (Erkennung) / offen KP-03 | `test_KOMP_073_elementor_element_cache`, `test_site_health_compatibility` | Ein öffentliches „nicht cachebar“-Signal für Widgets ist nicht bekannt → nach Spezifikation Systemstatus `element_cache`: aktiv = **kritisch**, Standard/unklar = Warnung (Option `elementor_experiment-e_element_cache` (v)) |
| KOMP-074 | best effort | offen (keine Lizenzen) | – | – |

### 5.9 Themes (§2.9)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-080 | zugesichert | offen M-Z E2E + axe | – | Twenty Twenty-Five liegt im Testkern, E2E/axe braucht Browser |
| KOMP-081 | zugesichert | offen M-Z E2E | – | – |
| KOMP-082 | getestet | offen KP-03 | – | Astra nicht verfügbar |
| KOMP-083 | getestet | offen KP-04 | – | – |
| KOMP-084 | best effort | offen (Smoke) | – | – |
| KOMP-085 | best effort | offen (Smoke) | `test_KOMP_101_102_woocommerce`: CC reiht im Frontend keine Assets ein | – |

### 5.10 Übersetzung (§2.10)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-090 | best effort | offen (keine Lizenz) | – | – |
| KOMP-091 | getestet | **Stub** / offen KP-07 (LOC-028) | `test_KOMP_091_polylang` | `pll_get_post_types` (v) nimmt die fünf `ccap_`-Typen heraus; Systemstatus `translation` (Hinweis, Warnung wenn doch übersetzbar) |
| KOMP-092 | best effort | offen (Smoke) | – | – |
| KOMP-093 | nicht unterstützt | Doku | – | – |

### 5.11 WooCommerce (§2.11)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-100 | zugesichert | **CI** | gesamte Suite ohne Woo | – |
| KOMP-101 | getestet | **Stub** / offen KP-08 | `test_KOMP_101_102_woocommerce` | keine CC-Callbacks auf `woocommerce_*`/`wc_*`, keine CC-Assets im Frontend, `shop_manager` ohne `ccap_*` (D07-6), Integrationsstatus nur lesend. HPOS/Block-Checkout nur im Profil |
| KOMP-102 | zugesichert | **Stub** / offen KP-08 | `test_KOMP_101_102_woocommerce` | **F-07:** CC-Rollen hatten kein `view_admin_dashboard` (D-20-10) → ergänzt für alle vier Rollen; Admin-Sperre emuliert (v) |
| KOMP-103 | getestet | offen KP-08 | – | CC nutzt den Action Scheduler in R1 nicht (WP-Cron) |
| KOMP-104 | best effort | offen KP-08 | – | – |
| KOMP-105 | nicht in R1 | – | – | R3.1 (E-53) |

### 5.12 Andere LMS und LearnBoating (§2.12)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-110 | best effort | **Stub** (Slug) | `test_KOMP_132_slug_collisions`: `kurs` frei neben `courses`/`course` | LearnDash ohne Lizenz |
| KOMP-111 | getestet | **Stub** / offen KP-09 | `test_KOMP_134_multi_lms_inventory` | Tutor-Kennungen (v) unverändert, keine Kollision |
| KOMP-112 | getestet | **Stub** / offen KP-09 | wie oben | LifterLMS-Rollen/CPT `course` (v) unverändert |
| KOMP-113 | best effort | offen (Smoke) | – | – |
| KOMP-114 | zugesichert | **Stub** (Namen) / offen J-21 (KP-10) | `test_KOMP_134_multi_lms_inventory` (LB-K-09-Kennungen überschneiden sich nicht) | echter Koexistenzlauf gegen LB `9f37c77` braucht den lesenden Deploy-Key (E-194) |

### 5.13 Weitere Umgebungseinflüsse (§2.13)

| ID | Stufe | Status | Test / Fundstelle | Befund |
|---|---|---|---|---|
| KOMP-120 | getestet | **Stub** | `test_KOMP_120_wp_cron_disabled` | **F-11:** Systemstatus `wp_cron` (Warnung ohne Lauf in 3 h, Hinweis mit Server-Cron); abgelaufener Zugang wird ohne Cron-Lauf beim Zugriff verweigert. **F-13:** Die WP-Testsuite definiert selbst `DISABLE_WP_CRON` → im Testlauf ist `wp_cron` ohne Stub eine Warnung (erwartet) |
| KOMP-121 | best effort | offen KP-05 | Restore-/Identitätsfälle in `RecoveryTest`, `UninstallProbeTest` (R1-23) | Backup-Plugins selbst nur Smoke |
| KOMP-122 | best effort | offen (Smoke) | – | – |

### 5.14 Namenskonflikte (§3)

| ID | Status | Test / Fundstelle | Befund |
|---|---|---|---|
| KOMP-130 | **CI** | statisch `KernelContractTest::test_KOMP_130_prefix_scan`; Laufzeit `test_KOMP_130_runtime_prefix_inventory` (Query-Vars, AJAX/admin-post-Aktionen, geschriebene Optionen/Transients, Post-Meta, Cron) | **F-01:** Query-Vars `ccap_learn`/`ccap_file` verletzten D-20-03 → `course_captain_app`/`course_captain_file`. Ausnahmen im statischen Scan: `litespeed_control_set_nocache`, `rest_authentication_errors`, `xmlrpc_enabled` (fremde/Core-Hooks, nur gefeuert bzw. gelesen). JS-Global/ESLint und CSS-Präfix: offen (R1-19, KOMP-133) |
| KOMP-131 | **CI** (Tabelle) + **Stub** (Collider) | `SchemaTest` (`@group KOMP-131`, Tabelle ohne Marke bricht Aktivierung ab); `test_KOMP_131_collider` (CPT `ccap_course`, Rolle `ccap_trainer`, Shortcode `ccap_access`, Block `course-captain/learning-link`) | **F-05:** CPT/Rolle/Shortcode/Block wurden bisher ohne Prüfung registriert (Block doppelt → `_doing_it_wrong`, Shortcode/CPT überschrieben) → kein Überschreiben, Systemstatus `name_collisions` = kritisch mit Fundstellen, kein Fatal |
| KOMP-132 | **CI** | `test_KOMP_132_slug_collisions` (Seite, CPT `course`/`courses`, Kategoriebasis, eigener zweiter Slug) | **F-06:** F4.1 fehlte → Feldfehler mit Alternativvorschlag, kein Flush ohne Bestätigung, Seite bleibt erreichbar; Systemstatus `slug_conflicts` |
| KOMP-133 | offen KP-03 (E2E) | – | Screenshot-Vergleich braucht Browser (R1-19) |
| KOMP-134 | **Stub** / offen KP-09 | `test_KOMP_134_multi_lms_inventory` (beide Aktivierungsreihenfolgen; Post-Typen, Rollen, Shortcodes, REST-Routen, Rewrite-Regeln) | – |

### 5.15 Versionsmatrix (§1)

| Achse | Status | Test / Fundstelle |
|---|---|---|
| PHP 8.3 (Untergrenze) | **CI** in diesem Lauf (PHP 8.3) | `RequirementsTest::test_RC_046_lower_bound_passes`, `PluginHeaderTest::test_RC_046_plugin_header` |
| PHP 8.4 / 8.5 | offen (M-M/M-Z-Zeilen, hier kein zweites PHP) | – |
| WordPress 7.1.4 | **CI** in diesem Lauf | `LifecycleTest::test_live_environment_meets_requirements` |
| WordPress 7.0.x | offen (M-U) | – |
| MariaDB 10.11 | **CI** in diesem Lauf | gesamte Integrationssuite |
| MariaDB 12.3/11.8, MySQL 8.4 | offen (M-Z/M-M) | – |
| MariaDB 10.6 / MySQL 8.0 (best effort, Systemstatus-Empfehlung) | **CI** (Bewertung) | `RequirementsTest` (Stufe „best effort“ aus R1-01) |
| Lebenszyklusdaten §1.4 | offen (OP-20-2, ohne Internet nicht verifizierbar) | – |

## 6. Profile KP-01…KP-14

| Profil | Status | Bemerkung |
|---|---|---|
| KP-01 Referenz | teilweise durch die CI-Suite abgedeckt (PHP 8.3 statt 8.5, MariaDB 10.11 statt 12.3, ohne Apache) | Pflichtumfang Nr. 1–7 offen |
| KP-02 … KP-14 | **offen** | kein Docker, kein Internet. Ablagevorschlag `tests/compat/profiles/KP-xx/` (Abschnitt 1). Pflichtfälle KOMP-057…059 sind als Stub-Tests vorhanden und müssen in KP-03/KP-04/KP-13 mit den echten Plugins wiederholt werden |

## 7. Befunde (Zusammenfassung)

| Nr. | Befund | Ergebnis |
|---|---|---|
| F-01 | Query-Vars ohne `course_captain_`-Präfix | behoben (umbenannt, `REWRITE_VERSION` 5) |
| F-02 | Plain-Permalinks: Lernbereich/Dateien nicht erreichbar | behoben (Query-Fallback, Warnung) |
| F-03 | Geschützte HTML-Seiten ohne LiteSpeed-/CDN-/Surrogate-Signale | behoben |
| F-04 | Permissions-Policy überschrieb fremden Wert (E-307) | behoben |
| F-05 | CPT/Rolle/Shortcode/Block-Kollisionen nicht abgefangen | behoben + Systemstatus kritisch |
| F-06 | Slug-Kollisionsprüfung F4.1 fehlte | behoben |
| F-07 | `view_admin_dashboard` für CC-Rollen fehlte (D-20-10) | behoben |
| F-08 | `trusted_proxies` ohne Wirkung | behoben (`ClientIp`) |
| F-09 | SEO-Erkennung nur Yoast/Rank Math | behoben |
| F-10 | fremde XML-RPC-Regel nicht erkannt | behoben |
| F-11 | Systemstatus-Checks `rest_guest_blocked`, `db_query_cache`, `mail_log`, `wp_cron`, `permalinks`, `translation`, `element_cache` fehlten | ergänzt |
| F-12 | Elementor-Layoutdaten nicht gesperrt | behoben |
| F-13 | Testsuite definiert `DISABLE_WP_CRON` | dokumentiert (erwartete Warnung) |
| F-14 | Setup-Assistent (E-260) existiert noch nicht | offen, gehört zur Assistenten-Aufgabe |
| F-15 | Fremde XML-RPC-Regeln, die erst nach `init` (Prio 1) registriert werden, sieht die Erkennung nicht; dann wirken beide Sperren (ohne Fehler, KOMP-058) | dokumentierte Grenze |
| F-16 | R0-20 §3.1 nennt 5 Blocks, im Code sind 3 registriert | Hinweis an R1-19, nicht R1-30 |
| F-17 | Alle fremden Namen (v) | Verifikation beim ersten Profillauf (OP-20-2) |
