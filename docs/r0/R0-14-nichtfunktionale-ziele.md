# R0-14 · Nichtfunktionale Qualitätsziele

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Alle Zahlen sind **Zielwerte**, keine Messwerte. Es gibt weder Implementierung noch Lasttest. Endgültige Grenzwerte gibt R1-22 erst nach dem ersten reproduzierbaren Lauf frei (Konzept 22.3: „endgültige Grenzwerte nach reproduzierbarem Lasttest freigeben“).
**Stand:** 16.11.2026
**Bezug:** TODO R0-14 (Voraussetzung R0-02, R0-06), Ziel-Tasks R1-18, R1-22, R1-42; Konzept Kap. 22.1, 22.3, 23, 41; `docs/r0/R0-06-datenmodell-adr.md` §9 (A-01…A-18, D-6) und §7 (Sperren); `docs/r0/R0-02-inventar/E-theme-tools-content-docs.md` (INV-E-081, -087, -101, -111, -112, D-E-02, D-E-03); `docs/r0/R0-36-suite-vertragsdeltas.md` §2.4/2.5 (Pass-Lastprofile, Race-Tests); `docs/r0/ENTSCHEIDUNGEN.md` E-08, E-11, E-12, E-52, E-58…E-61, E-195 (beschlossen).
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77`, nur gelesen. Pfade sind relativ zum Repository-Stamm.
**Abgrenzung:** Die Plattform-Matrix legt R0-20 eigenständig fest (E-12: LB-Werte sind **kein** Startwert). R0-14 übernimmt sie in §3 und legt fest, wie gemessen wird. Barrierefreiheit gehört zu R0-15, Backup-/Restore-Dauer zu R0-16. Sicherheitsgrenzen (Rate-Limits, fail-open/closed) stehen in R0-17/R0-21 und E-46.

## Beschlussstand

Quelle: `ENTSCHEIDUNGEN.md`, Beschlussprotokoll und „Von Claude entschieden“ (16.11.2026).

| Beschluss | Wirkung in diesem Dokument |
|---|---|
| **E-01 Clean Room** | Kein LB-Code wird übernommen. §0 enthält nur Verhaltens- und Messbelege; Seeder, Sonde, Lasttest-Runner und Compose-Dateien werden neu geschrieben |
| **E-12** | Plattformmatrix eigenständig (R0-20), LB-Werte nicht als Startwert; §3 übernimmt die Festlegung aus R0-20 |
| **E-195** (ersetzt E-57/D-14-01) | PHP-Untergrenze **8.3**, getestet 8.3/8.4/8.5; vor R1-GATE auf php.net verifizieren |
| **E-58** (D-14-02) | CI: MariaDB 10.11 und 12.3 (bzw. 11.8 LTS), MySQL 8.4; MySQL 8.0/MariaDB 10.6 best effort; E-08 bleibt technische Mindestanforderung |
| E-59 (D-14-05) | Dokumentierte Shared-Hosting-Klasse „bis 50 gleichzeitige Prüfungen“ (Messung in R1-22) |
| E-60 (D-14-07) | Zeitbudgets sind Release-Gate für R1-GATE |
| **E-61** (D-14-08) | Browser: letzte zwei Hauptversionen Chrome, Edge, Firefox, Safari + Firefox ESR, iOS Safari, Android Chrome |
| E-191 | Playwright je PR/nightly; echtes iOS/Android/Safari manuell vor jedem Minor-Release |
| E-192 | Cloud-VM für die Performance-Messung (R-M) wird gestellt |
| E-52 | Lastannahmen bestätigt (10 000 Lernende, 100 Kurse, 100 000 Fragen, 500 gleichzeitige Prüfungen) |
| E-08, E-11 | InnoDB/JSON Pflicht; PHPUnit + WP-Testsuite und Playwright |
| **E-45, E-45a** | Externe Medien werden standardmäßig **ohne Klick geladen** (`external_media_mode` = „immer laden“); FE-07 gilt deshalb nur im Modus „Klick zum Laden“ |
| D-14-03, D-14-04, D-14-06 | Architekturfestlegungen (ENTSCHEIDUNGEN §11): kürzere Lock-Wartezeiten, InnoDB-FULLTEXT, k6 + PHP-Race-Runner |
| E-207 | Präfix `ccap_` für Tabellen, CPTs, Caps, Rollen, Cron, Meta; `ccap_…`-Tabellennamen hier sind Arbeitsnamen (`ccap_…` in der Umsetzung) |

---

## 0. Belege aus LearnBoating (nur Belege, kein Startwert – E-01, E-12)

| Nr. | Beleg (LB @ 9f37c77) | Inhalt | Verwendung hier |
|---|---|---|---|
| LB-NF-01 | `bootspruefung-learning/bootspruefung-learning.php` (Kopf), `readme.txt` | `Requires at least: 7.0`, `Tested up to: 7.1`, `Requires PHP: 8.1`; WooCommerce keine Pflicht | Matrix §3 |
| LB-NF-02 | `.github/workflows/ci.yml:130-141` (Job `plugin`, Matrix) | Ziel PHP 8.5/WP 7.1.4/MariaDB 12.3.3 (E2E), Untergrenze PHP 8.1/WP 7.0.0/MariaDB 10.11, informativ PHP 8.3/MySQL 8.4 (`experimental`), Extensions `mysqli, intl` (`zip` in Import-Jobs) | Matrix §3 |
| LB-NF-03 | `staging/docker-compose.yml:24,40,59,115`, `staging/php.ini` | MariaDB 12.3.3, `wordpress:7.1.4-php8.5-apache`, WP-CLI-Container, Caddy 2.10; `memory_limit 512M`, `max_execution_time 300`, Upload 64 MB | Referenzumgebung §2.1. Die Staging-Limits sind **großzügiger als Shared Hosting**, siehe §4.2 |
| LB-NF-04 | `ci.yml:315` | E2E gegen den eingebauten PHP-Server (`php -S … router.php`) | Webserver-Achse §3 |
| LB-NF-05 | `bootspruefung-learning/tests/e2e/*.mjs` | Playwright 1.48.2, **nur Chromium**; Viewports 1200×900 bzw. 1280×900 (Desktop) und 390×844 (Mobil) | Browser-Achse §3 |
| LB-NF-06 | `bootspruefung-learning/assets/build/app.js` / `app.css` | Lernraum ohne Build-Schritt und ohne Framework: 259 274 B (66 699 B gzip -9) bzw. 142 080 B (24 906 B gzip -9) | JS/CSS-Budget §1.2 |
| LB-NF-07 | `src/Learning/Services/LearningService.php:40` | Rate-Limit 60 Antworten je Nutzer und Minute (A-16) | Lastprofil §2.2 |
| LB-NF-08 | `src/Exams/Services/ExamService.php:41-47, 181-194` | `GRACE_SECONDS = 5`, `START_LOCK_WAIT = 10` s (NamedLock je Nutzer), `STARTS_PER_HOUR = 40`; bei nicht verfügbarem `GET_LOCK` geht es ohne Sperre weiter | Robustheit §4 |
| LB-NF-09 | `src/Content/Import/ImportService.php:54-81` | `STALE_AFTER 600`, `LOCK_TTL 900`, `MEDIA_STEP_BYTES 64 MB`, `PREVIEW_STEP_SECONDS 20`; `wp_raise_memory_limit('admin')`, `@set_time_limit(300)` | Import-Budget §1.1, §4.2 |
| LB-NF-10 | `src/Commerce/ExpiryReminder.php:40-41, 227-233` | Zeitbudget je Cron-Lauf 20 s, höchstens `max_execution_time / 2` | Job-Zeitbudget §4.2 |
| LB-NF-11 | `src/Infrastructure/Monitor.php:25`, `src/Admin/SiteHealth.php:64-93`, `src/Admin/SystemStatus.php:278-288` | Cron gilt nach 3 h ohne Lauf als ausgefallen; Hinweis bei `DISABLE_WP_CRON` | Cron-Ausfall §4.1 |
| LB-NF-12 | `src/Infrastructure/NamedLock.php:11-21` | `GET_LOCK` auf der WP-Verbindung, Name je DB und Präfix, Sonderfall HyperDB/LudicrousDB | Lock-Verhalten §4.1 |
| LB-NF-13 | `src/` (42 Aufrufe `wp_cache_*`/`wp_using_ext_object_cache`) | Der Code nutzt den Objekt-Cache, die CI läuft aber **ohne** persistenten Cache | Objekt-Cache-Achse §3 |
| LB-NF-14 | Pass `tests/load/run.php`, `http.php`; `docs/TESTING.md:9-93` (über R0-36 P3, §2.4) | N Prozesse hinter einer Startbarriere, Profile `ci`/`default`/`heavy`, Invarianten, Deadlock-Zählung | Race-Profil L5 §2.2 |

In LB selbst gibt es **keinen** Lasttest und keine Performance-Messung. Die Budgets unten sind daher neu und nicht aus LB übernommen. Die LB-Werte oben dienen nur als Vergleich; maßgeblich sind §1–§3 (E-12).

---

## 1. Performancebudgets

### 1.1 Serverseitig je Kernoperation

**Geltungsbereich:** Referenzumgebung **R-M** (§2.1), Datenprofil `default` (A-01…A-18), Lastprofil **L1** (Normalbetrieb), OPcache an, `WP_DEBUG` aus, **ohne** persistenten Objekt-Cache, solange in der Zeile nichts anderes steht. Mit Objekt-Cache darf kein Wert schlechter werden (NFR-008).

**Definitionen**
- **Serverzeit:** von `$_SERVER['REQUEST_TIME_FLOAT']` bis `shutdown`. Die Test-Sonde (§2.3) gibt sie im Header `Server-Timing` aus. Zur Kontrolle dient k6 `http_req_waiting`.
- **Queries:** Datenbankabfragen, die Query Monitor bzw. die Sonde der Komponente „Plugin: course-captain“ zuordnet. Die Gesamtzahl je Anfrage ist höchstens dieses Budget plus die WP-Grundlast, die einmal in einer leeren Anfrage derselben Route gemessen wird. **Konstanzregel:** Die Query-Zahl hängt nicht von der Zahl der Listenelemente ab (kein N+1). Geprüft wird das mit n = 1, 20 und 100.
- **Speicher:** `memory_get_peak_usage(true)` minus die Grundlast derselben leeren Route.
- **Kein Volltabellenscan:** Bei `EXPLAIN` hat keine Abfrage auf eine Tabelle mit mehr als 10 000 Zeilen `type = ALL`. `Using filesort` ist nur bei ≤ 1 000 Zeilen erlaubt.

| ID | Kernoperation (Annahme Route) | Datenbasis | p50 | p95 | max. Queries (Plugin) | Speicher Δ | Hinweise / Bezug |
|---|---|---|---|---|---|---|---|
| OP-01 | Kursübersicht / „Mein Lernen“ (Katalog 20 je Seite + eigene Kurse mit Fortschritt) | 100 Kurse, 20 000 Grants (A-07), `ccap_course_progress` (A-14) | 150 ms | 500 ms mit warmem Objekt-Cache, 700 ms ohne | 15 | ≤ 16 MB | Konzept 22.3 „Kurs-Hub p95 < 500 ms bei warmem Cache“; Fortschritt nur aus `ccap_course_progress`, kein Aggregat über `ccap_activity_progress` |
| OP-02 | Lektion laden (Inhalt, Navigation, Aktivitätsstatus) | 20 000 Lektionen (A-06), 2 Mio. `ccap_activity_progress` (A-13) | 120 ms | 350 ms | 12 | ≤ 16 MB | Modul-/Lektionsbaum mit einer Abfrage je Ebene, nicht je Knoten |
| OP-03 | Smarttrainer `next` (Auswahl der nächsten Frage) | 8 Mio. `ccap_question_progress` (A-10), 20 Mio. `ccap_attempt` (A-09) | 80 ms | 300 ms | 10 | ≤ 8 MB | Konzept 22.3 „typische Lernfrage p95 < 300 ms“; Index `user_due`, „zuletzt beantwortet“ ohne Scan von `ccap_attempt` |
| OP-04 | Smarttrainer `answer` (Bewertung + Fortschritt) | wie OP-03 | 80 ms | 300 ms | 8 (≤ 4 Schreibzugriffe in einer TX) | ≤ 8 MB | Rate-Limit LB-NF-07 bleibt; Write idempotent (R0-21) |
| OP-05 | Prüfungsstart (Sitzung + 30 Session-Fragen + Snapshot) | 150 000 Sitzungen (A-11), 4,5 Mio. Session-Fragen (A-12) | 250 ms | 800 ms; unter L2 1 500 ms | 15 (Session-Fragen als **ein** mehrzeiliges `INSERT`) | ≤ 16 MB | Sperre je Nutzer wie LB-NF-08, Wartezeit siehe §4.1/D-14-03 |
| OP-06 | `saveAnswer` (Antwort im laufenden Versuch speichern) | wie OP-05 | 60 ms | 250 ms; unter L2 400 ms | 6 | ≤ 4 MB | Upsert je (Sitzung, Frage), Sequenznummer gegen veraltete Writes (§4.1) |
| OP-07 | `submit` + Bewertung (30 Fragen) | wie OP-05 | 300 ms | 1 000 ms; unter L2 1 500 ms | 20, keine Abfrage je Frage | ≤ 16 MB | Bewertung im Speicher aus dem Snapshot; Doppelabgabe gibt dasselbe Ergebnis zurück |
| OP-08 | Fortschritt (Kurs-Dashboard Lernende, Statistik je Modul) | A-10, A-13, A-14 | 100 ms | 400 ms | 12 | ≤ 8 MB | „keine Volltabellenscans für Dashboard-KPIs“ (22.3) |
| OP-09 | Admin-Kursliste (100 Kurse, 20 je Seite, Spalten Lernende/Fragen/Status) | 100 Kurse, 20 000 Grants | 300 ms | 800 ms | 20 (Zähler gebündelt oder vorberechnet) | ≤ 32 MB | Zählerspalten nicht je Zeile abfragen |
| OP-10 | Fragenbank-Suche (Text + Filter Kurs/Modul/Tag/Typ/Status, 50 je Seite) | 100 000 Fragen, 150 000 Revisionen, 450 000 Tags (A-01, A-02, A-04) | 300 ms | 1 000 ms | 6 | ≤ 32 MB | Textsuche über Index, siehe D-14-04; `LIKE '%…%'` auf 150 000 Revisionen verletzt die Scanregel; Gesamtzahl darf ab 10 000 Treffern geschätzt sein |
| OP-11 | Import 10 000 Fragen (Paket mit ≈ 5 000 Bildern ≈ 0,5 je Frage, A-05) | Grenzen A-17 | Schritt ≤ 10 s | Schritt ≤ 20 s und ≤ `max_execution_time / 2` | ≤ 8 je Datensatz gemittelt, Batch ≤ 500 Datensätze (A-17) | ≤ 96 MB | Gesamtdauer R-M ≤ 15 min (Dry-run + Commit), R-S ≤ 45 min; Schrittzeit wie LB-NF-09/-10 |
| OP-12 | Export 10 000 Fragen (Paket/CSV) | wie OP-11 | Schritt ≤ 10 s | Schritt ≤ 20 s | ≤ 10 je Batch à 500 | ≤ 64 MB, gestreamt in eine Datei, nie komplett im Speicher | Gesamtdauer R-M ≤ 5 min, paginiert (22.3) |

Für Lastprofil **L2** gelten die Werte mit dem Zusatz „unter L2“. Für alle anderen Operationen dürfen die p95-Werte unter L2 und L3 um höchstens 30 % über den L1-Werten liegen (NFR-005, NFR-006).

### 1.2 Frontend (Lernraum, Prüfung, Admin)

| ID | Kennzahl | Ziel | Messung | Bezug |
|---|---|---|---|---|
| FE-01 | LCP | ≤ 2,5 s (p75) für Kursübersicht, Lektion, Prüfungsseite | Lab: Playwright + Lighthouse-Mobilprofil (4× CPU-Drosselung, „Slow 4G“), Viewport 390×844; Desktop 1280×900 ohne Drosselung ≤ 1,5 s | Core-Web-Vitals-Schwelle „gut“ |
| FE-02 | INP | ≤ 200 ms (p75); Antwortklick → sichtbare Auswahl ≤ 100 ms, unabhängig von der Serverantwort | Playwright mit `web-vitals` im Test-Build (Event Timing); Lab-Ersatz TBT ≤ 200 ms | Prüfung bleibt bei langsamem Netz bedienbar |
| FE-03 | CLS | ≤ 0,1 | wie FE-01, auch beim Nachladen der nächsten Frage und bei Fehlermeldungen | – |
| FE-04 | JS-Bundle Lernraum (initial, je Seite) | ≤ 75 KB gzip | Build-Report in der CI, Abbruch bei Überschreitung | LB-NF-06: 66,7 KB gzip ohne Framework |
| FE-05 | CSS Lernraum | ≤ 30 KB gzip | wie FE-04 | LB-NF-06: 24,9 KB gzip |
| FE-06 | JS je Admin-Screen (ohne WP-Core-Pakete wie `wp-element`) | ≤ 150 KB gzip, Editor-Screens ≤ 250 KB gzip | wie FE-04 | Editor-Technik offen (R0-11/R0-22) |
| FE-07 | Fremdhosts | Im Modus „Klick zum Laden“: 0 Anfragen an fremde Hosts vor dem Klick. Im Default „immer laden“ (E-45a): nur die in der readme offengelegten externen Dienste der eingebetteten Medien, keine weiteren Fremdhosts | Fremdhost-Erfassung im E2E (INV-E-111), beide Modi | E-45, E-45a |
| FE-08 | Anfragen je Interaktion | Smarttrainer: ≤ 1 REST-Aufruf je Antwort (`answer` liefert die nächste Frage mit) oder ≤ 2 parallel; Prüfung: `saveAnswer` gebündelt, höchstens 1 je 2 s und Frage | Netzwerkprotokoll E2E | Grundlage für die Raten in L1/L2 |

---

## 2. Lastprofile, Werkzeuge, Messverfahren

### 2.1 Referenzumgebungen

| Kürzel | Zweck | Definition |
|---|---|---|
| **R-M** | Budgetmessung (§1.1) | Eigene Docker-Compose-Definition (Aufbau wie LB-NF-03 beobachtet, ohne Übernahme, E-01) auf einer gestellten Cloud-VM mit festem Instanztyp (E-192), auf **festen** Ressourcen: App-Container 4 vCPU / 8 GB, PHP 8.5-FPM mit 16 Workern, OPcache an; DB-Container 4 vCPU / 8 GB, MariaDB 12.3.x (bzw. 11.8 LTS, falls 12.3 keine LTS ist; E-58), `innodb_buffer_pool_size = 4G`, sonst Standardwerte; nginx 1.x **oder** Apache 2.4 (gemessen wird die Ziel-Kombination aus §3); Lastgenerator auf eigenem Host bzw. eigenen Kernen, RTT ≤ 1 ms. Hardware (CPU-Modell, Speicher, Datenträger) wird im Protokoll festgehalten. |
| **R-S** | Shared-Hosting-Nachbildung (Funktion, Limits §4.2) | 1 vCPU / 2 GB, 4 PHP-FPM-Worker, `memory_limit 128M`, `max_execution_time 30`, `upload_max_filesize 8M`, `max_input_vars 1000`, kein persistenter Objekt-Cache, `DISABLE_WP_CRON` ohne System-Cron als eigene Variante. Hier gelten **keine** Latenzbudgets, sondern Zeitlimits, Speicher und Kapazität (D-14-05). |
| **R-CI** | Regression in der CI | GitHub-Runner, Datenprofil `ci`. Geprüft werden nur Query-Zahlen, die Scanregel und Invarianten, **keine** Zeiten, weil Runner-Zeiten nicht reproduzierbar sind. |

### 2.2 Profile

**Datenprofile** (D-6/E-52): `ci` = 1/10 von `default`; `default` = A-01…A-18; `heavy` = `default` × 2,5 (wie Pass). Angelegt werden sie mit einem dev-only WP-CLI-Seeder `wp cc-dev seed --profile=<ci|default|heavy> --seed=<n>`. Er ist deterministisch, schreibt per Batch-Insert direkt in `ccap_`-Tabellen und CPTs, verteilt die Zeitstempel über 24 Monate (Fälligkeiten realistisch) und gehört **nicht** ins Release-ZIP. Für R1-22 wird die Dauer des Seedings protokolliert, als Hinweis für R0-16.

| Profil | Szenario | Last (Annahme, Formel offengelegt) | Dauer | Werkzeug | Bestanden, wenn |
|---|---|---|---|---|---|
| **L1 Normal** | Lernbetrieb in der Spitzenstunde | 300 gleichzeitig aktive Lernende (3 % von 10 000), Denkzeit 10–20 s → ≈ 20 Antworten/s. Mix: 45 % `answer`, 45 % `next` (bzw. mitgeliefert, FE-08), 5 % Lektion, 3 % Kursübersicht, 2 % Fortschritt. Dazu 20 laufende Prüfungen, 2 Admins (Liste, Suche) | 2 min Warm-up + 15 min Messung | k6 (`constant-arrival-rate` je Szenario), Login-Cookies/Nonces vorab per WP-CLI erzeugt | Budgets §1.1, Fehlerquote < 0,1 % |
| **L2 Prüfungsspitze** | 500 gleichzeitige Prüfungsversuche (A-15) | Start von 500 Nutzern innerhalb von 120 s (`ramping-arrival-rate`, Spitze ≈ 8 Starts/s) und 5 % Doppelklick-Starts. Je Versuch 30 Fragen, `saveAnswer` alle 20–40 s → ≈ 17/s, 30 % ändern eine Antwort. 90 % geben in den letzten 60 s ab (Spitze ≈ 8 Abgaben/s), 10 % laufen in den Timeout und werden ohne Cron abgeschlossen (§4.1). Parallel L1 mit 50 % Last | Zeitlimit der Prüfung 30 min + 5 min | k6 | NFR-005; Invarianten: genau 1 laufende Sitzung je Nutzer und Form, keine verlorene Antwort (letzte bestätigte Sequenz = gespeicherter Stand), jede Sitzung genau einmal bewertet |
| **L3 Import parallel** | Admin importiert 10 000 Fragen (OP-11), dazu ein Export (OP-12), während L1 mit 100 % läuft | wie L1 + 1 Import + 1 Export | bis Import fertig, mindestens 15 min | k6 für L1, Import über die Admin-Schritt-API (k6-Szenario) **und** zusätzlich per WP-CLI | NFR-006: L1-p95 höchstens 30 % schlechter, 0 Lock-Wait-Timeouts in Lernanfragen, Import vollständig und idempotent wiederholbar |
| **L4 Dauerlauf** | Job-Backlog und Speicherdrift (R1-22 „Job-Backlog“) | L1 mit 50 %, dazu Cron-Jobs (Ablauf, Outbox, Retention) mit einem künstlichen Rückstand von 50 000 Outbox-Einträgen und 5 000 abgelaufenen Sitzungen | 2 h | k6 + System-Cron alle 60 s | Rückstand innerhalb von 60 min abgebaut, kein Lauf über seinem Zeitbudget, PHP-FPM-RSS ohne Wachstum über 10 % |
| **L5 Race** | Datenintegrität (R1-42) | N = 50 PHP-Prozesse mit eigener DB-Verbindung hinter einer Startbarriere (Muster LB-NF-14). Szenarien: Enrollment gleicher Nutzer/Kurs, Seat-Claim auf den letzten Platz, Attempt-Start gleicher Nutzer, `saveAnswer` und `submit` gleichzeitig, doppelte Completion, Zertifikat | je Szenario 20 Wiederholungen; Profil `ci` in der CI, `heavy` vor dem Release | eigener PHP-Runner (Verhalten wie `tests/load/run.php`, neu geschrieben) + Variante über echte REST-API | Unique-Invarianten nie verletzt, Deadlocks gezählt und nach Retry aufgelöst, Exit-Code 1 bei Verletzung |

**Werkzeugwahl:** k6 für HTTP-Last, weil es Szenarien, Ankunftsraten und Schwellen (`thresholds` → Exit-Code) bietet. `wrk` ist nur für Einzel-Endpunkt-Mikrotests zugelassen. Für L5 nimmt man einen PHP-Mehrprozess-Runner, weil HTTP-Last keine exakte Gleichzeitigkeit herstellt. k6 ist reines Entwicklerwerkzeug (AGPL-3.0, nicht im Paket; Eintrag im Werkzeuginventar wie INV-E Zeile 533).

### 2.3 Messverfahren

1. **Funktionsmessung (Queries, Speicher, Scanregel):** Jede Operation wird einzeln auf R-M mit Profil `default` gemessen. Dabei laufen Query Monitor **oder** eine dev-only Sonde (`mu-plugins/cc-perf-probe.php`, `SAVEQUERIES`, Zuordnung per Backtrace), die Query-Zahl, Query-Zeit, Peak-Speicher und Serverzeit als `Server-Timing` und als JSON-Zeile ins Debug-Log schreibt. Jede Abfrage der Operation bekommt automatisiert ein `EXPLAIN` (Scanregel §1.1). Ergebnis ist ein Query-Katalog je Operation, Grundlage für die Index-Analyse in R1-22.
2. **Lastmessung:** Sonde und Query Monitor sind **aus**, weil sie die Zeiten verfälschen. Es zählen nur `Server-Timing` (minimal, ohne `SAVEQUERIES`) und k6-Werte. Ablauf: Kaltstart → Warm-up 2 min → Messfenster. **3 Wiederholungen**; gilt der Median der p95-Werte; Streuung > 15 % zwischen Läufen macht den Lauf ungültig.
3. **DB-Seite:** `performance_schema`/`sys` (MariaDB/MySQL) bzw. Slow-Query-Log mit `long_query_time = 0.1` während der Läufe; Deadlock-Zähler (`Innodb_deadlocks` bzw. `SHOW ENGINE INNODB STATUS`), `Innodb_row_lock_waits`, `Innodb_row_lock_time_max` vor und nach jedem Lauf.
4. **Protokoll:** Commit-Hash, Matrix-Zeile (§3), Hardware, Datenprofil + Seed, k6-Skript-Version, Rohdaten (k6 JSON-Summary), Query-Katalog, DB-Zähler. Ablage unter `docs/r1/R1-22-lastprotokoll-<datum>.md` (Pfad wird in R1-22 bestätigt).
5. **CI-Gates (R-CI):** Query-Zahlen (Konstanzregel n = 1/20/100) und Scanregel als PHPUnit-Tests gegen echte DB (E-11); Bundle-Größen FE-04…FE-06; L5 im Profil `ci`. Zeitbudgets sind **kein** CI-Gate, nur Release-Gate auf R-M.

---

## 3. Unterstützungsmatrix (Festlegung nach R0-20)

Stufen: **Ziel** = voller Lauf mit Integration + E2E + Lastmessung; **Untergrenze** = Integration, ohne E2E; **informativ** = läuft mit, darf fehlschlagen (`continue-on-error`). Die Werte sind nach E-12 eigenständig festgelegt (R0-20 §1.2); die LB-Spalte ist nur Vergleich.

| Achse | Ziel | Untergrenze | Informativ | LB-Wert (nur Vergleich, E-12) | Anmerkung / Abweichung |
|---|---|---|---|---|---|
| PHP | 8.5 | **8.3** (E-195); 8.4 als zweite Pflichtzeile | nächste PHP-Version (Beta/RC) | Ziel 8.5, Untergrenze 8.1, informativ 8.3 (LB-NF-01/-02) | Extensions Pflicht: `mysqli`, `json`, `mbstring`; `intl` und `zip` empfohlen. Ohne `zip` wird der Paketimport mit Hinweis abgeschaltet, ohne `intl` gibt es den Fallback für Sortierung und Normalisierung (INV-E-029) |
| WordPress | 7.1.x (aktuell) | 7.0.0 | nächste Beta/RC | `Requires at least 7.0`, `Tested up to 7.1`, CI 7.1.4/7.0.0 (LB-NF-01/-02) | Regel: aktuelle + vorherige Hauptversion; Multisite-Netzwerkaktivierung blockiert (E-49) |
| Datenbank | MariaDB 12.3.x (bzw. 11.8 LTS, E-58) | MariaDB 10.11 | 11.4/11.8; 10.6 best effort (E-58) | MariaDB 12.3.3/10.11 (LB-NF-02/-03) | E-08: InnoDB + JSON-Funktionen Pflicht. In MariaDB ist `JSON` ein Alias für `LONGTEXT` mit `JSON_VALID`, deshalb keine Indizes auf JSON-Ausdrücke und kein `->>`-Operator (portabel bleiben). `utf8mb4`, InnoDB-FULLTEXT verfügbar |
| Datenbank (2. Achse) | MySQL 8.4 LTS | – | MySQL 8.0 nur „best effort“ (E-58) | MySQL 8.4 nur informativ (LB-NF-02) | **Abweichung:** MySQL 8.4 wird für CC Pflichtachse (Integration + L5), weil viele Hoster MySQL betreiben; Unterschiede bei Deadlock-/Lock-Verhalten und `GET_LOCK` sind für R1-42 relevant |
| Webserver | Apache 2.4 (mod_php oder FPM) **und** nginx + PHP-FPM | – | LiteSpeed/OpenLiteSpeed | Apache (Staging), PHP-Built-in-Server (CI), Caddy als TLS-Proxy (LB-NF-03/-04) | **Neu:** nginx-Achse. Schutz von Import-/Exportdateien darf **nicht** von `.htaccess` abhängen (Dateien außerhalb des Webroots bzw. nur über authentifizierte Downloads); Built-in-Server nur für die CI |
| Objekt-Cache | aus (Pflicht, Budgets §1.1) **und** Redis-Drop-in an | – | Memcached | CI nur ohne persistenten Cache, obwohl der Code `wp_cache_*` nutzt (LB-NF-13) | **Neu:** Achse „Cache an“ mit Integration + L1. Korrektheit darf nie vom Cache abhängen; Cache-Schlüssel nach 22.3 (Site, User, Kurs, Revision, Access-Scope) |
| HTTPS/Proxy | hinter Reverse-Proxy (Caddy/nginx) | direkt | – | Caddy-Profil `https` (LB-NF-03) | `is_ssl()`/`X-Forwarded-*` korrekt behandeln |
| Theme | ein Block-Theme (Twenty Twenty-Five) und ein Classic-Theme | – | weitere | `twentytwentyfive` + `kurswasser` (LB-Theme, fachgebunden, entfällt; INV-E-057) | Classic-Theme neutral wählen (z. B. Twenty Twenty-One) |
| Browser Desktop | Chrome/Edge, Firefox, Safari: je die 2 letzten Hauptversionen; Firefox ESR (E-61) | – | – | E2E nur Chromium (LB-NF-05) | **Neu:** E2E auf Chromium, Firefox und WebKit mit `@playwright/test` (E-11); Viewport 1280×900 (LB) |
| Browser Mobil | Safari iOS (2 letzte iOS-Hauptversionen), Chrome Android (aktuell) (E-61) | – | Samsung Internet | Mobil-Viewport 390×844 nur in Chromium (LB-NF-05) | Playwright-WebKit 390×844 und Chromium-Android-Emulation 360×800; Prüfungsabläufe zusätzlich manuell auf einem echten iOS- und Android-Gerät sowie Safari vor jedem Minor-Release (E-191, R1-18) |
| Ohne JavaScript | Inhalte lesbar, verständlicher Hinweis statt Lern-/Prüfungsfunktion | – | – | – | Konzept 23.1 „fehlendes JS“ |
| WooCommerce | – (erst ab R3) | – | – | WC 11.2.2 / 11.3 Beta (LB-NF-02) | D-E-03 |

**CI-Umfang:** In jedem PR laufen Ziel (PHP 8.5/WP 7.1/MariaDB 12.3/Apache/Cache aus, Chromium + WebKit), Untergrenze (PHP 8.3/WP 7.0/MariaDB 10.11) und MySQL 8.4 mit PHP 8.4 (R0-20 §1.5). Nightly kommen Firefox, nginx, Redis an, L5 `ci` und alle informativen Zeilen dazu.

---

## 4. Verfügbarkeit und Robustheit

### 4.1 Fehlerfälle und gefordertes Verhalten

| ID | Fehlerfall | Gefordertes Verhalten | Test |
|---|---|---|---|
| RB-01 | **Cron fällt aus** (`DISABLE_WP_CRON` ohne System-Cron, kein Traffic, hängender Lauf) | Keine fachliche Korrektheit hängt am Cron. Prüfungsfristen werden **bei jedem Zugriff** durchgesetzt: `saveAnswer` nach Frist + 5 s (LB-NF-08) wird abgewiesen, der nächste Lese- oder Schreibzugriff schließt die Sitzung ab und bewertet sie (lazy). Zugang ergibt sich beim Lesen aus `valid_until`, nicht aus einem Cron-Statuswechsel. Cron erledigt nur Benachrichtigungen, Outbox, Retention und das Aufräumen verwaister Sitzungen, jeweils mit Nachholung und begrenzter Menge je Lauf. Nach 3 h ohne Lauf (LB-NF-11) zeigen Site Health und Systemstatus eine Warnung mit Hinweis auf System-Cron | R-S-Variante ohne Cron: L2-Teilmenge „Timeout ohne Abgabe“ → alle Sitzungen beim nächsten Zugriff korrekt bewertet; L4-Nachholung |
| RB-02 | **PHP-Timeout / Abbruch eines Schritts** (Import, Export, Migration, Cron) | Jeder mehrstufige Lauf hat einen Cursor und Checkpoints. Abbruch bedeutet höchstens „Schritt wiederholen“, nie halbe Datensätze (TX je Batch). Lock mit Lease/Heartbeat; verwaiste Sperre nach `STALE_AFTER` (LB 600 s) übernehmbar. Zeitbudget je Schritt ≤ min(20 s, `max_execution_time` / 2) (LB-NF-10) | R-S mit `max_execution_time 30`: Import 10 000 Fragen läuft durch; künstlicher `kill -9` des Workers mitten im Batch → Resume ohne Duplikat |
| RB-03 | **Client-Timeout / Netzabbruch** (Lernende) | `answer` und `saveAnswer` sind idempotent (Client-Idempotency-Key bzw. Sequenznummer je Frage; ältere Sequenz überschreibt nie eine neuere). `submit` ist idempotent: die zweite Abgabe liefert dasselbe Ergebnis. Client wiederholt mit Backoff (1, 2, 4 s, höchstens 5 Versuche) und zeigt den Speicherzustand an („gespeichert / wird gespeichert / offline“). Details in R1-20 | E2E mit Playwright-Netzabbruch; L2 mit 2 % absichtlich wiederholten Requests |
| RB-04 | **DB-Lock-Wait / Deadlock** | Kurze Transaktionen mit fester Sperrreihenfolge (in R1-42 dokumentiert). Deadlock (MySQL-Fehler 1213): automatische Wiederholung der ganzen TX, höchstens 3×, Jitter 50–200 ms. Lock-Wait-Timeout (1205): keine Teil-Writes, Fehlercode `ccap_db_busy`, HTTP 503 mit `Retry-After: 2`, Client wiederholt (RB-03). Interaktive TX setzen `innodb_lock_wait_timeout` auf Sitzungsebene auf 5 s statt Server-Standard 50 s (D-14-03). Named Locks (`GET_LOCK`, LB-NF-12) warten in interaktiven Pfaden höchstens 2 s, LB nutzt 10 s (D-14-03). Ist `GET_LOCK` nicht verfügbar, sichert eine Unique-Invariante ab (wie LB-NF-08) | L2 und L5: 0 verletzte Invarianten; Deadlocks protokolliert und durch Retry aufgelöst; `ccap_db_busy` < 0,1 % der Requests unter L2 |
| RB-05 | **DB-Verbindung weg** („server has gone away“ in langen Läufen) | `$wpdb->check_connection()` vor jedem Batch; ein Schritt beginnt neu, Named Locks werden nach Reconnect neu erworben (verbindungsgebunden) | Integration: DB-Neustart während des Imports |
| RB-06 | **Objekt-Cache fällt aus oder wird geleert** | Nur langsamer, nie falsch. Keine Berechtigung, kein Zähler und kein Rate-Limit-Zustand mit Korrektheitsbedeutung **nur** im Cache (Rate-Limit-Verhalten nach E-46) | Redis-Achse: `FLUSHALL` und Stopp während L1 |
| RB-07 | **Speicher/Platz voll**, Upload zu groß | Import prüft vor dem Commit Größe, Anzahl (A-17) und freien Platz; Abbruch mit verständlichem Report ohne Teilimport | R-S mit `upload_max_filesize 8M` |
| RB-08 | **Überlast** (PHP-Worker belegt) | Kein Endpunkt hält einen Worker > 5 s im Warten (Locks siehe RB-04). Der Prüfungs-Client zeigt eine Warteanzeige statt eines Fehlers und speichert lokal zwischen, bis der Server bestätigt (R1-20) | L2 auf R-S über der Kapazitätsgrenze (D-14-05): kein Datenverlust, sauberes 503 |

### 4.2 Grenzwerte Shared Hosting (R-S)

| Limit | Annahme Shared Hosting | Anforderung an CC |
|---|---|---|
| `memory_limit` | 128 MB; WP-Standard `WP_MEMORY_LIMIT` 40 MB Frontend, `WP_MAX_MEMORY_LIMIT` 256 MB Admin | Alle Frontend-Operationen OP-01…OP-08 laufen mit 128 MB. Import und Export laufen mit 256 MB nach `wp_raise_memory_limit('admin')` (wie LB-NF-09); bei kleinerem Limit wird die Batchgröße automatisch reduziert, statt abzubrechen |
| `max_execution_time` | 30 s; `set_time_limit` oft gesperrt | Kein Request braucht > 20 s. LB-Staging (300 s, LB-NF-03) ist **kein** Maßstab |
| `upload_max_filesize` / `post_max_size` | 8–64 MB | Größere Pakete per Chunk-Upload oder per WP-CLI/Server-Pfad (R0-10/R0-38) |
| `max_input_vars` | 1 000 | Editoren senden JSON per REST statt großer Formulare |
| PHP-Worker | 2–8 | Kapazitätsangabe statt Latenzbudget (D-14-05) |
| Cron | WP-Cron über Besuche, oft unzuverlässig | RB-01 |
| DB | geteilt, keine Rechte für `SET GLOBAL`, kein `performance_schema` | Nur Sitzungsvariablen; Diagnose über die eigene Sonde/Systemstatus |

---

## 5. Abnahmekriterien NFR-xxx

| ID | Kriterium (messbar) | Messverfahren | Ziel-Task |
|---|---|---|---|
| NFR-001 | OP-01…OP-10 halten p50/p95 aus §1.1 unter L1 auf R-M mit Profil `default` | §2.3 Nr. 2, 3 Läufe, Median der p95 | R1-22 |
| NFR-002 | OP-01…OP-10 halten das Query-Budget und die Konstanzregel (n = 1/20/100) | §2.3 Nr. 1; CI-Gate auf R-CI | R1-22 (CI dauerhaft) |
| NFR-003 | 0 Abfragen mit `type = ALL` auf Tabellen > 10 000 Zeilen im Query-Katalog aller OP; Indexliste mit `EXPLAIN` belegt | §2.3 Nr. 1, Profil `default` und `heavy` | R1-22 |
| NFR-004 | Speicher-Δ je OP innerhalb §1.1; OP-01…OP-08 laufen auf R-S mit `memory_limit 128M` ohne Fatal Error | Sonde + R-S-Lauf | R1-22 |
| NFR-005 | L2: 500 Versuche gestartet, OP-05/06/07 innerhalb der Werte „unter L2“, Fehlerquote < 0,1 %, 0 verlorene Antworten, genau 1 laufende Sitzung je Nutzer und Form, jede Sitzung genau einmal bewertet | k6 + Invarianten-Prüfskript nach dem Lauf | R1-22, R1-42 |
| NFR-006 | L3: Import 10 000 Fragen ≤ 15 min auf R-M, L1-p95 höchstens 30 % schlechter als NFR-001, 0 Lock-Wait-Timeouts in Lernanfragen; zweiter Import desselben Pakets erzeugt 0 neue Datensätze | k6 + Import-Report | R1-22 (mit R1-41) |
| NFR-007 | OP-11/OP-12 auf R-S (`max_execution_time 30`, `memory_limit 256M`): kein Timeout, Export ≤ 64 MB Speicher-Δ, Import nach `kill -9` ohne Duplikat fortsetzbar | R-S-Lauf, RB-02 | R1-22 |
| NFR-008 | Mit Redis-Objekt-Cache ist kein p95 schlechter als ohne; Cache-Flush während L1 erzeugt 0 fachliche Fehler | L1 zweimal (Cache an/aus), RB-06 | R1-22 |
| NFR-009 | L4: Rückstand (50 000 Outbox, 5 000 abgelaufene Sitzungen) in ≤ 60 min abgebaut, kein Cron-Lauf über dem Zeitbudget, RSS-Drift < 10 % in 2 h | L4-Protokoll | R1-22 |
| NFR-010 | L5: alle Race-Szenarien (Enrollment, Seat-Claim, Attempt-Start, saveAnswer/submit, Completion, Zertifikat) mit N = 50 und 20 Wiederholungen ohne verletzte Unique-Invariante, auf MariaDB **und** MySQL 8.4 | PHP-Mehrprozess-Runner, Exit-Code | R1-42 |
| NFR-011 | Deadlocks und Lock-Waits werden je Szenario gezählt und protokolliert; jeder Deadlock wird durch höchstens 3 Retries aufgelöst; `ccap_db_busy` < 0,1 % unter L2; keine Teil-Writes nach 1205 | DB-Zähler (§2.3 Nr. 3) + Anwendungslog | R1-42 |
| NFR-012 | Sperrreihenfolge je schreibender Operation als Tabelle dokumentiert und per Code-Review gegen den Quelltext geprüft | Review-Protokoll | R1-42 |
| NFR-013 | Cron-Ausfall (RB-01): ohne jeden Cron-Lauf werden alle abgelaufenen Prüfungen beim nächsten Zugriff korrekt bewertet und abgelaufene Zugänge sofort gesperrt; Site Health warnt nach 3 h | R-S-Variante ohne Cron, E2E | R1-18 (fachlich), R1-22 (unter Last) |
| NFR-014 | FE-01…FE-03 (LCP ≤ 2,5 s mobil, INP ≤ 200 ms, CLS ≤ 0,1) auf Kursübersicht, Lektion, Smarttrainer, Prüfung in Chromium und WebKit | Playwright + Lighthouse, Profil `ci` | R1-18 |
| NFR-015 | FE-04…FE-06 Bundle-Budgets und FE-07 (Fremdhosts je `external_media_mode`, E-45a) als CI-Gate grün | Build-Report, E2E | R1-18 |
| NFR-016 | E2E-Pflichtabläufe (Lernen, Smarttrainer, Prüfung inkl. Reload/Netzabbruch, Admin-Import) grün in allen **Ziel**-Zeilen von §3 (Chromium, Firefox, WebKit; Desktop + Mobil-Viewport); Integration grün in allen **Untergrenze**-Zeilen | CI-Matrix | R1-18 |
| NFR-017 | Plugin aktivierbar und lauffähig ohne `zip`/`intl` (mit Hinweis) und unter nginx ohne `.htaccess`; Import-/Exportdateien unter nginx nicht anonym abrufbar | Integration + HTTP-Probe | R1-18 |
| NFR-018 | Jeder Lasttestlauf ist nach §2.3 Nr. 4 protokolliert und mit demselben Seed reproduzierbar (Streuung < 15 %) | Protokoll-Review | R1-22 |

---

## 6. Entscheidungsbedarf – beschlossen

Alle Punkte sind entschieden (`ENTSCHEIDUNGEN.md` §11/§30 und Beschlussprotokoll, 16.11.2026). Die D-Nummern bleiben als Verweise im Text stehen.

| ID | Frage | Beschluss | Betrifft |
|---|---|---|---|
| D-14-01 → E-57 → **E-195** | PHP-Untergrenze | **PHP 8.3**, getestet 8.3/8.4/8.5; vor R1-GATE auf php.net verifizieren (E-57 ersetzt) | R0-20, R1-18 |
| D-14-02 → **E-58** | DB-Versionen | CI: **MariaDB 10.11 und 12.3 (bzw. 11.8 LTS), MySQL 8.4**; MySQL 8.0/MariaDB 10.6 best effort; E-08 bleibt technische Mindestanforderung | R0-20, R1-42 |
| D-14-03 | Wartezeiten bei Sperren | Architekturfestlegung: `GET_LOCK` ≤ 2 s, `innodb_lock_wait_timeout` = 5 s auf Sitzungsebene, Retry nach §4.1 | R1-20, R1-42 |
| D-14-04 | Textsuche in der Fragenbank | Architekturfestlegung: InnoDB-`FULLTEXT`; eigene Suchtabelle nur, falls NFR-001 verfehlt wird | R0-06, R1-22 |
| D-14-05 → **E-59** | Kapazitätsaussage Shared Hosting | Dokumentierte Klasse „bis 50 gleichzeitige Prüfungen“ (Messung in R1-22); darüber VPS empfohlen | R1-22, R1-24 |
| D-14-06 | Lasttest-Werkzeuge | Architekturfestlegung: k6 + eigener PHP-Mehrprozess-Runner | R0-18, R1-22, R1-42 |
| D-14-07 → **E-60** | Zeitbudgets als Release-Gate | Ja für R1-GATE; danach Query-/Scan-Gates in jeder CI und Zeitmessung je Minor-Release | R1-18, R1-GATE |
| D-14-08 → **E-61** | Browser-Supportregel | Letzte zwei Hauptversionen Chrome, Edge, Firefox, Safari + Firefox ESR, iOS Safari, Android Chrome; echte Geräte manuell vor jedem Minor-Release (E-191) | R0-20, R1-19 |

### Offene Punkte nach Einarbeitung

- E-59 nennt nur „bis 50 gleichzeitige Prüfungen“; die Zusatzangabe „50 aktive Lernende“ aus D-14-05 steht im Beschluss nicht und bleibt Annahme bis R1-22.
- Ob MariaDB 12.3 eine LTS-Version ist (sonst Ziel 11.8), wird bei der Verifikation in R0-20 §1.4 geklärt; davon hängt R-M ab.
- FE-07 im Default „immer laden“ (E-45a): Welche Fremdhosts die eingebetteten Player zusätzlich nachladen, ist erst im E2E-Lauf feststellbar; die readme-Offenlegung (R0-35) muss dazu passen.
