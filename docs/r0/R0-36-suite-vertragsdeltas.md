# R0-36 · Suite-Vertragsdeltas Pass / Voucher / Affiliate / License (+ Captain Pro)

**Status:** Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet (Contract-Tests erst mit Course-Code möglich) · **Stand:** 16.11.2026
**Bezug:** TODO R0-36 (Voraussetzung R0-32, R0-19), Konzept Kap. 6, 8, 18, 30, 31, 32, 45; liefert Eingaben für **R0-37**, **R0-39**, **R0-40**, R1-40…42, R3-04/05, R3-18/19
**Vorgänger:** [R0-32-suite-traceability.md](R0-32-suite-traceability.md) (Form/Workflow/Review, gleiche Legende)

## Beschlussstand

Quelle: `ENTSCHEIDUNGEN.md`, Beschlussprotokoll und „Von Claude entschieden“ (16.11.2026). Die Fragen D1–D5 aus §7.1 sind entschieden.

| Beschluss | Wirkung in diesem Dokument |
|---|---|
| E-35 (D1) | Gutschein-Storno nach Einlösung entzieht **nur den Voucher-Grant**; Lernhistorie bleibt |
| E-36 (D2) | Ein Gutscheincode **pro Gruppenplatz** |
| E-37 (D3) | Course meldet in v1 **keine** Verkäufe an Affiliate Captain |
| E-07 (D4) | Offiziell nur Single-Site; die **Pro-Lizenz zählt je Site** (`network => false`) |
| E-42 (D5), E-95 | Produkt `course-captain-pro` bei License Captain **vor R3-18** beauftragen, nach Neuzuschnitt der Entitlement-Schlüssel (E-95); Zugriff auf `captain-pro` für die Prüfung nötig |
| **E-273** | **Pro-Add-on wird in `captain-pro/addons/course-captain-pro` gebaut** (Konzept-Plan); Voraussetzung: Zugriff auf das private Repository `captain-pro` für Kernel, FeatureRegistry, TARIFE, ARCHITECTURE (§6) |
| E-53, E-31 | WooCommerce- und Voucher-Connectoren sind Pro; Gutscheinziel kann ein neutrales Course-Captain-Zugangsangebot **oder** ein WooCommerce-Produkt sein (§3.3) |
| E-38 | Öffentliche Events, Read-API und Connector-Registry sind Pro; Free hat nur die interne Outbox |
| E-93, E-94, E-213, E-272 | Bei Lizenzende laufen Grants/Widerrufe weiter, Konfiguration wirkt eingefroren; Pro unter GPL-2.0-or-later; signierte Update-Antwort + SHA-256 + Build-Attestierung |

## 1. Quellbasis

| Repository | Produkt | Commit (voll) | Datum | Code? | Plugin-Kopf |
|---|---|---|---|---|---|
| `hastla007/wp-passmaster` | Pass/Ticket Captain | `b2b88ea961332743301b07c76bbdc41f3a0456c5` | 15.11.2026 23:30 | **ja** (206 PHP-Dateien ohne `vendor/`) | `pass-captain.php:6-8`: Version 1.4.0, WP ≥ 6.4, PHP ≥ 8.2 |
| `hastla007/wp-couponmaster` | Voucher Captain | `2d84943f84a1c8389ddf66fa6987ae3e06318852` | 15.11.2026 17:49 | **ja** (112) | `voucher-captain.php:6-10, 22`: Version 1.1.0, WP ≥ 6.4, PHP ≥ 8.1, **`Requires Plugins: woocommerce`**, WC ≥ 8.9 |
| `hastla007/wpaffiliatemaster` | Affiliate Captain | `3b7130ed9a96730822f173967bc566b08bc5b503` | 16.11.2026 06:36 | **ja** (192) | `affiliate-captain.php:6-8, 41`: Version 0.14.0, WP ≥ 6.3, PHP ≥ 7.4, `WPAC_CONTRACT_VERSION = '1.3.0'` |
| `hastla007/wp-license` | License Captain (Server + Client-Bibliothek) | `c269e07814851df191ffbf79c6897ebd7f372412` | 15.11.2026 20:34 | **ja** (366) | Client-Bibliothek `src/Client/*.php`, `VERSION = '2.0.2'` (`LicenseManager.php:31`) |
| `hastla007/captain-pro` | Captain Pro (Add-on-Kernel) | – | – | **nicht zugänglich** (privat) | – |

Alle Aussagen unten mit `Datei:Zeile` beziehen sich auf diese Commits. Weil hier **Code** vorliegt, sind die genannten Funktionen/Hooks/Routen reale Verträge des jeweiligen Stands – keine Kompatibilitätszusage für spätere Versionen (Kap. 30: vor Implementierung erneut prüfen). Keines der vier Repos erwähnt Course Captain (Suche `course captain|course-captain|course_captain|coursemaster`: 0 Treffer); LMS-Bezüge sind generisch („unlock a course“) oder LearnBoating (`wp-couponmaster:SCOPE.md:16`, `API.md:65, 107-116`).

---

## 2. Pass Captain (`wp-passmaster@b2b88ea`)

### 2.1 Matrix

| # | Quelle | Inhalt / Muster | Relevanz | Abdeckung konzept.md / TODO | Delta | Nichtumfang |
|---|---|---|---|---|---|---|
| P1 | `docs/OPERATIONS.md:10-16`; Code `src/Installer.php:674-790` | **Schema-Upgrade-Lock** (Details 2.2) | hoch | Kap. 45; R0-37, R1-40 | Konkrete Lock-/Trigger-Regeln in R0-37 übernehmen (2.2). | – |
| P2 | `docs/OPERATIONS.md:3-8, 43-51` | **Upgrade-/Restore-Runbook** (Details 2.3) | hoch | Kap. 22, 45; R0-16, R0-37 | Course-Runbook fehlt noch; Punkte 2.3 übernehmen. | Kassen-/Fiskalhinweise (`:53-60`) |
| P3 | `docs/TESTING.md:9-93`; `tests/load/run.php`, `tests/load/http.php`; CI `.github/workflows/ci.yml:56-60` | **Echte DB-Race-Tests** mit N Prozessen hinter Startbarriere, Invarianten, Deadlock-Zählung (Details 2.4) | hoch | Kap. 45; R1-42, R3-05 | Course-Lastprofil analog definieren (2.4). | Scanner-/Check-in-Szenarien |
| P4 | `CHANGELOG.md:167-168`; `src/Admission/Engine.php:128-134` | Zwei reale Nebenläufigkeitsfehler: (a) serverseitig zurückgerollte Transaktion → Folgeanweisungen liefen im Autocommit ohne Sperre weiter (Überverkauf); (b) `FOR UPDATE` auf **noch nicht existierende** Zeile + späteres `INSERT … ON DUPLICATE KEY` → Gap-Lock-Deadlock, Fehler nicht geprüft → falsches „eingelassen“ | hoch | – (nicht in konzept.md) | R0-21/R1-42: Regel „nach Deadlock/Lock-Timeout gesamte Operation abbrechen, nie im Autocommit weiterlaufen; kein `FOR UPDATE` auf fehlende Zeilen, stattdessen Elternzeile sperren oder Unique-Insert“ – betrifft Enrollment/Seat/Attempt-Anlage. | – |
| P5 | `docs/HOOKS.md:4-6, 22-31, 137-142` | Nur Hooks mit Suffix `_v1` sind Drittanbieter-Verträge; `wpcap_tickets_issued`, `wpcap_ticket_checked_in`, `wpcap_ticket_revoked`, `wpcap_refund_completed` sind **interne** Hooks. Versionierte Verträge: `wp_pass_captain_connectors_v1`, `…_process_job_v1`, `…_voucher_provider_v1`, `…_ticket_service_v1`, `…_checkin_service_v1` | hoch | Kap. 8, 18.2 („Pass-Captain-Teilnahme bestätigt“), 30.2 | **Kein versionierter PHP-Ereignisvertrag** für Check-in/Teilnahme/Ticket-Widerruf. Ein Course-Pass-Connector hätte heute nur interne Hooks oder die externen Webhooks. Partneraufgabe (Fremdrepo); deckt sich mit `wp-workflowmaster:konzept.md:1060` und `wp-reviewmaster:konzept.md:815`. | – |
| P6 | `src/Integrations/ConnectorInterface.php:5-9`, `src/Integrations/ConnectorRegistry.php:8-21`; `docs/EXTENSIONS.md:3` | Connector-Vertrag (`key/version/capabilities/deliver`), Freigabe über Option `wpcap_enabled_connectors` | mittel | Kap. 18.1 | **Befund:** `ConnectorRegistry::dispatch()` hat im Repo **keinen Aufrufer** (Suche `ConnectorRegistry` außerhalb `vendor/`: nur die Klassendefinition). Der von Workflow (`konzept.md:137`) und Review (`konzept.md:466`) als Vorbild zitierte Connector-Weg liefert derzeit keine Ereignisse. | – |
| P7 | `docs/WEBHOOKS.md:20-31, 89-94` | Signierte Webhooks `order.paid/cancelled/refunded`, `ticket.issued/checked_in/revoked`, `occurrence.*`; Signatur `t=…,v1=HMAC`; **Lücke:** Vollerstattung im Zahlungsanbieter-Dashboard erzeugt nur `ticket.revoked`, kein `order.refunded` (`:28-31`) | mittel | Kap. 30.3 Transport (Cross-Site nur signiert) | Für Course: Ticketwiderruf (nicht `order.refunded`) ist das verlässliche Widerrufssignal. | Outbound-Webhook-Verwaltung |
| P8 | `docs/EXTENSIONS.md:9-21`; `src/Integrations/AffiliateCaptain/Adapter.php:7-8, 279-292` | Pass meldet eigene Verkäufe an Affiliate (`source = pass-captain`), **überspringt WooCommerce-Bestellungen** (Filter `wpcap_affiliate_skip_channels`, Standard `woocommerce`), weil Affiliate Woo selbst bucht | hoch (Muster) | Kap. 30.4 Rezept 6 | Siehe Affiliate-Konsequenzen (4.3). | – |
| P9 | `docs/EXTENSIONS.md:5` | „Derzeit gibt es keinen Lizenzserver, Update-Service, kommerziellen Feature-Schalter …“ | keine | – | Quellinterne Drift zu `wp-license:docs/ENTITLEMENTS.md:55` (`pass-captain-pro`), siehe 7.2. | – |

### 2.2 (d) Schema-Upgrade-Lock – realer Ablauf

- **Trigger:** Admin (`admin_init`), WP-Cron und WP-CLI vergleichen die volle Schema-Version (`wpcap_db_version` = Kernversion + Hash der Modulschemata, `Installer.php:682-689`); Frontend/REST vergleicht nur einen billigen Fingerabdruck (`Installer.php:699-701`, Option `wpcap_schema_fingerprint`) und plant bei Abweichung **einmalig** das Cron-Ereignis `wpcap_run_upgrade` (`:674, 734-741`). Nur wenn dieses Ereignis > 300 s überfällig ist (`OVERDUE_AFTER`, `:677`), führt **ein** Frontend-Request das Upgrade aus.
- **Lock:** `run_locked()` nutzt `SELECT GET_LOCK(name, 0)` – **nicht wartend**; wer die Sperre nicht bekommt, überspringt das Upgrade und arbeitet mit dem alten Schema weiter (`:778-791`). Lockname je Site/Präfix + DB-Name (`:771-775`), also Multisite-Scope pro Site. Sperre endet automatisch bei Prozessabbruch (`OPERATIONS.md:14`).
- **Doppelprüfung im Lock:** nach Erhalt der Sperre wird die Version **direkt aus der Datenbank** am Options-Cache vorbei gelesen (`stored_db_version()`, `:760-767`), weil ein paralleler Request das Upgrade schon beendet haben kann (`:751-753`).
- **Grenze:** funktioniert nur, weil Migrationen ausschließlich **additiv** sind (Tabellen/Spalten/Indizes, `dbDelta`, keine Löschung; `OPERATIONS.md:7, 16`). Destruktive Backfills sind damit nicht abgedeckt.

### 2.3 (d) Restore-Runbook – Inhalt

1. Vor Upgrade: Staging mit identischen Versionen, vollständiges Datei- **und** DB-Backup, Sicherungszeitpunkt + Plugin- + Schemaversion notieren, danach Kernabläufe prüfen (`OPERATIONS.md:3-8`).
2. Restore: Dateien und Datenbank **als zusammengehörigen Stand**; DB-Restore allein kann mit neuerem Code inkompatibel sein; bei Multisite Netzwerkaktivierung und Präfixe einbeziehen; danach Cron-Ereignisse kontrollieren (`:43-45`).
3. Keine selektive Zusammenführung aus mehreren Sicherungszeitpunkten; Zahlungsanbieter und Integrationen separat auf denselben externen Transaktionsstand abgleichen (`:47`).
4. Release-Kandidat erst nach Upgrade-/Restore-Test mit realistischen Daten, doppelten Webhooks, Mailausfall, Multisite (`:51`).

### 2.4 (d) Race-Tests – übertragbare Testanlage

- `tests/load/run.php`: Koordinator legt Daten an, startet N PHP-Prozesse mit je eigener DB-Verbindung hinter einer **Startbarriere**; Profile `ci`/default/`heavy`; Exit-Code 1 bei verletzter Invariante (`TESTING.md:11-32`). Szenarien mit Invarianten „kein Überverkauf“, „je Idempotency-Key genau eine Bestellung“, „jedes Event genau einmal“ (`:24-28`), plus Variante über echte REST-API (`http.php`, `:34-46`).
- CI läuft das `ci`-Profil als Regressionstest (`ci.yml:56-60`). **Quellinterne Drift:** `TESTING.md:85-93` beschreibt den Check-in-Deadlock noch als offen und CI als `continue-on-error`; Code (`Engine.php:131-134`), `CHANGELOG.md:168` und `ci.yml` zeigen ihn als behoben bzw. ohne `continue-on-error`.

### 2.5 Konsequenz für R0-37 (Schema-Upgrade) und R1-40/R1-42

- R0-37 übernimmt: Trigger nur Admin/Cron/CLI, Frontend höchstens Fingerprint + einmaliges Cron-Ereignis mit Overdue-Fallback; nicht wartendes `GET_LOCK` je Site; Versions-Doppelprüfung direkt aus der DB im Lock; Upgrade-Schritte idempotent.
- **Zusätzlich zu Pass** entscheiden: Course-Backfills (Grants, Fortschritt) sind nicht rein additiv – Batch-Cursor, Wiederaufnahme und „alter Code gegen neues Schema“ müssen eigens geregelt werden (Pass deckt das nicht ab).
- R1-42 übernimmt die Multi-Prozess-Barriere, Invarianten je Szenario (Enrollment, Seat-Claim, Attempt-Start/Submit, Completion, Zertifikat) und die Deadlock-Lehre P4.

### 2.6 Konsequenz für R0-40 (finanzielle Ereignisse)

- Pass bucht Ticket-/Bestell-Geld selbst und meldet eigene Verkäufe selbst an Affiliate (P8). Ein Course-Pass-Connector darf **keine** Verkaufs- oder Provisionsmeldung erzeugen und keinen eigenen Refund-Betrag führen.
- Widerrufssignal für einen ticketbasierten Grant ist der **Ticketwiderruf** (`wpcap_ticket_revoked`/`ticket.revoked`), nicht `order.refunded` (P7-Lücke). Ein Check-in ist kein Abschluss (Kap. 30.2/31.1) – siehe Widerspruch X5.
- Ohne versionierten Pass-Ereignisvertrag (P5) und mit unverdrahteter Connector-Registry (P6) ist ein Pass-Connector **nicht freigabefähig**; R3-07 muss das als Blocker führen.

---

## 3. Voucher Captain (`wp-couponmaster@2d84943`)

### 3.1 Matrix

| # | Quelle | Inhalt / Muster | Relevanz | Abdeckung konzept.md / TODO | Delta | Nichtumfang |
|---|---|---|---|---|---|---|
| C1 | `API.md:1-35`; `src/API/V1/VoucherService.php`; `src/Plugin.php:22, 51` | **`vcap()->api_v1`**: `lookup`, `reserve($code,$amount,WC_Order)`, `commit($code,WC_Order)`, `release`, Codes `vcap_not_found`, `vcap_invalid_redemption`, `vcap_balance_only`, `vcap_balance_conflict`, `vcap_reservation_missing`; Idempotenz-Scope = **Order-ID + Voucher-ID**, kein eigener Key (`API.md:35`) | mittel | Kap. 45 nennt „in-process PHP v1, Reserve/Commit/Release“ | **Nicht** der Kurs-Einlöseweg (braucht eine gespeicherte `WC_Order`). Für Kurse gilt C2. Kap. 45 präzisieren (X3). | – |
| C2 | `API.md:50-103`; `src/API/V1/RedemptionService.php:72-541`; `src/Plugin.php:23, 52, 84` | **`vcap()->redemptions`** (seit 1.1.0) – Einlösung **ohne** Woo-Bestellung, ausdrücklich „to unlock a course“ (`API.md:52`): `check`, `reserve`, `get`, `commit`, `release`, `refund` (Details 3.2) | hoch | Kap. 30.2 Voucher-Zeile, 32.2 B, 30.4 Rezept 4; R3-04, R3-05 | Fehlercode-Mapping, Key-/Target-Format, TTL – siehe 3.3. | Guthaben-Ledger |
| C3 | `API.md:71-83`; `src/API/V1/RestController.php:21, 42-106, 138, 196` | REST `vcap/v1/redemptions` (check, create, get, commit, release); Auth: Nonce bzw. Application Passwords, anonym → 401; Filter `vcap_rest_can_redeem`, `vcap_rest_can_access_redemption` | mittel | Kap. 30.3 Transport | Course nutzt lokal PHP; REST nur, falls Lernende im Browser direkt einlösen sollen (E-Entscheidung nicht nötig, R3-13 UX). | – |
| C4 | `CONNECTORS.md:7-25`; `API.md:122-139` | Bausteine: eigener Typ `vcap_voucher_types`, eigener Kanal `vcap_redemption_channels` + `vcap_redemption_channel_for_target`, Regeln `vcap_redemption_allowed`, `vcap_redemption_target_available`, Ereignisse `vcap_voucher_redeemed`, `vcap_redemption_released`, `vcap_voucher_event` | hoch | Kap. 32.2 B („Kurs-Einlösekanal“) | Course-Kanal und Kurs-Typ festlegen; Kanal **ohne** `shop`, damit derselbe Code nicht parallel im Warenkorb zählt (3.3). | – |
| C5 | `CONNECTORS.md:27-46`; Referenz `examples/vcap-example-connector/vcap-example-connector.php:63-140`; CI `tests/example-connector-integration.php` | Verbindlicher Ablauf und Fehler-/Retry-Tabelle (Details 3.2) | hoch | Kap. 31.3 Pkt. 1, 32.2 B | Course-Saga gegen diese Tabelle schreiben (R3-04). | – |
| C6 | `CONNECTORS.md:48-56`; `src/Issuer.php:259, 294, 349`; `src/Settings.php:21`; `src/VoucherRepository.php:345` | Refund-Matrix: Kauf erstattet → Voucher `void` (Einstellung `vcap_void_on_refund`, Standard an); nach Commit bleibt Einlösung committed, Connector entscheidet **Behalten oder Widerruf** über `vcap_voucher_event`/`voided`; Teilrefund des Kaufs ändert nichts; committete Einlösungen werden nie automatisch rückgängig gemacht | hoch | Kap. 31.2 (Refund wirkt auf betroffenen Grant), 32.2 | Produktentscheidung D1. | – |
| C7 | `CONNECTORS.md:58-65` | Gruppenpakete: **pro Platz** (empfohlen) oder pro Paket | hoch | Kap. 32.2 B („Einheiten … ausdrücklich festlegen“) | Entschieden: ein Code pro Platz (E-36). | – |
| C8 | `CONNECTORS.md:76-82` | Versionierung: öffentliche API = `api_v1`, `redemptions`, `vcap/v1`, `VCAP\Contracts\*`, dokumentierte `vcap_`-Hooks/Meta; innerhalb v1 nur additiv; Breaking → `api_v2` parallel ≥ 2 Minor-Releases + 6 Monate; Prüfung `defined('VCAP_VERSION') && version_compare(VCAP_VERSION,'1.1.0','>=')` + `function_exists('vcap')` | hoch | Kap. 8 Handshake, 30.3; R3-11, R3-12 | Course-Kompatibilitätsmatrix: Mindestversion 1.1.0 eintragen. | Tabellenzugriff |
| C9 | `STATE_MACHINE.md:1-66` | Zustände Voucher (`scheduled/active/reserved/redeemed/expired/void`) und externe Einlösung (`reserved → committed | released`, TTL → released); Atomik per `SELECT … FOR UPDATE` + Journal + `COMMIT` | hoch | Kap. 31.2/31.3 | Course-Grant-Zustände `pending/active/revoked` auf diese Zustände abbilden (3.3). | – |
| C10 | `STATE_MACHINE.md:61-66`; `tests/parallel-checkout.php:1-67`; `tests/redemption-integration.php:109-110` | Mehrprozess-Paralleltest **nur für Shop-Checkout-Guthaben**; Einlöse-API nur sequentiell auf Idempotenz/Konflikt geprüft | hoch | R3-05 | Course muss parallele `reserve`/Grant/`commit` mit demselben und mit verschiedenen Keys selbst testen. | – |
| C11 | `voucher-captain.php:9`; `API.md:3`; `RedemptionService.php:130, 219`; `src/Plugin.php:125-136` | Voucher setzt **WooCommerce** voraus (Plugin-Header; Action Scheduler, `wc_get_price_decimals`) und **InnoDB** (sonst `vcap_temporary_failure`, `RedemptionService.php:141-143`) | hoch | Kap. 32.1/32.3 („Voucher allein“) | Widerspruch X1. | – |
| C12 | `API.md:151-169`; `COMPATIBILITY.md:41-55` | Voucher↔Pass über `wp_pass_captain_voucher_provider_v1`/`…_ticket_service_v1`, Release-Gate „allein / allein / beide“ | mittel (Muster) | Kap. 32.3 R3.3 | Course-Gate analog als Matrix „Course+Voucher / Course+Woo / beide / keiner“ – bereits R3-06. | Ticket-Gutscheine |

### 3.2 (a) Reserve / Commit / Release – reale Funktionen und Hooks

| Schritt | Aufruf (PHP) | Verhalten laut Code | Hooks |
|---|---|---|---|
| Vorprüfung | `vcap()->redemptions->check( $code, ['target'=>…,'amount'=>…] )` (`RedemptionService.php:72`) | reserviert nichts; zählt ungültige Codes zum Rate-Limit (`:75-80`) | – |
| Reservieren | `->reserve( $code, ['key','target','amount','user_id','email','ttl_minutes','meta'] )` (`:106`) | Key-Pflicht nach `KEY_PATTERN` `/^[A-Za-z0-9][A-Za-z0-9._:\-]{7,63}$/` (`:35, 109-110`); bekannter Key wird **vor** dem Voucher-Lookup beantwortet (`:120-136`); gleicher Key + anderer Request-Hash (Voucher/Target/Betrag/User) → `vcap_idempotency_conflict`; Transaktion mit `SELECT … FOR UPDATE` auf die Voucher-Zeile (`:150-154`); TTL = `ttl_minutes` (nur PHP, max. 60 Tage) oder Option `vcap_redemption_ttl` (Standard 15 min) (`:147`); Ablauf über Action Scheduler `vcap_release_expired_redemption` (`:219`) | `vcap_redemption_reserved` (`:228`); Filter `vcap_redemption_allowed` (`:616`), `vcap_redemption_target_available` (`:631`) |
| Bestätigen | `->commit( $key )` (`:239`) | Zustandsübergang mit gleicher Sperrreihenfolge (Voucher-, dann Einlösezeile, `:461-463`); Wiederholung liefert das Ergebnis erneut; während Reservierung storniert → `vcap_cancelled` (`:480`) | `vcap_voucher_redeemed` **genau einmal** (`:531`) |
| Freigeben | `->release( $key )` (`:250`) | gibt Wert zurück; idempotent | `vcap_redemption_released( $data, $expired )` (`:540`) |
| Zustand | `->get( $key )` (`:388`) | `vcap_reservation_missing`, wenn unbekannt | – |
| Rückgabe nach Commit | `->refund( $key, $amount, $refund_key )` (`:269`) | idempotent je `$refund_key`; nur committed (`vcap_not_committed`, `:293`); gedeckelt (`vcap_refund_exceeded`, `:326`) | `vcap_redemption_refunded` (`:378`) |
| Storno des Kaufs | (Voucher-intern) | Voucher → `void` bei Erstattung, Einstellung `vcap_void_on_refund` (`Settings.php:21`, `Issuer.php:259`) | `vcap_voucher_event( $voucher_id, 'voided', … )` (`VoucherRepository.php:345`) |

**Fehlercodes der Einlöse-API** (`API.md:85-102`, im Code `RedemptionService.php:75-777`): `vcap_invalid_request` 400 · `vcap_not_allowed` 401/403 · `vcap_not_found` 404 · `vcap_reservation_missing` 404/409 · `vcap_already_redeemed` 409 · `vcap_reserved` 409 · `vcap_insufficient_balance` 409 · `vcap_not_active` 409 · `vcap_target_unavailable` 409 · `vcap_idempotency_conflict` 409 · `vcap_expired`/`vcap_cancelled` 410 · `vcap_not_redeemable` 422 · `vcap_rate_limited` 429 · `vcap_temporary_failure` 503; zusätzlich nur im Refund-Pfad `vcap_not_committed`, `vcap_refund_exceeded` (`API.md:149`).

**Idempotenz:** Aufrufer vergibt den Key und speichert ihn **vor** `reserve()` (`API.md:65`, `CONNECTORS.md:29`). Grant beim Aufrufer idempotent über den gespeicherten Key (`CONNECTORS.md:31`). Retry-Jobs lesen `get( $key )` und setzen fort: `reserved` ⇒ liefern + commit, `committed` ⇒ Grant sicherstellen, `released` ⇒ Grant entziehen (`CONNECTORS.md:46`).

**Identität:** PHP darf `user_id` übergeben, sonst eingeloggter Nutzer; Gast-`email` nur, wenn vom Aufrufer verifiziert; Guthaben-Typ `credit` nie über diese API; REST ignoriert `user_id` (`API.md:67-69`).

### 3.3 Konsequenz für R0-40 (finanzielle Ereignisse)

1. **Grant-Quelle:** `source = voucher`, `source_reference = redemption_key`. Nach E-31 gibt es zwei Zieltypen (neutrales Course-Captain-Zugangsangebot oder WooCommerce-Produkt); die folgenden Punkte beschreiben den Course-Zieltyp, den Woo-Produkt-Zieltyp spezifizieren R0-40 und R3-04. Key-Schema z. B. `course-captain:grant:{grant_id}` (erfüllt `KEY_PATTERN`), vor `reserve()` im Course-Grant (`pending`) persistiert. Target z. B. `course-captain:course:{course_id}` bzw. `…:seat:{…}`; eigener Kanal `course-captain` über `vcap_redemption_channel_for_target`.
2. **Zustandsabbildung:** `reserve` ok → Grant `pending`; Grant aktiv geschrieben → `commit`; `commit` ok → Grant bleibt `active`; `vcap_reservation_missing`/`vcap_cancelled` beim Commit → Grant `revoked` (Grund `voucher_reservation_lost`/`voucher_cancelled`) + `release`; `vcap_already_redeemed` beim Reserve → Erfolg nur, wenn Course den Grant zu **diesem** Key hält (`CONNECTORS.md:43`).
3. **Fehlercode-Mapping auf Kap. 18** (`forbidden`, `invalid_scope`, `duplicate`, `not_found`, `incompatible_contract`, `retryable`): `vcap_temporary_failure`→`retryable` (gleicher Key); `vcap_not_allowed`→`forbidden`; `vcap_not_found`/`vcap_reservation_missing`→`not_found`; `vcap_idempotency_conflict`→**neuer Code nötig** (Konflikt ≠ Duplikat); `vcap_reserved`/`vcap_already_redeemed`/`vcap_insufficient_balance`/`vcap_expired`/`vcap_cancelled`/`vcap_not_redeemable`/`vcap_target_unavailable`/`vcap_rate_limited` haben **kein** Pendant in Kap. 18 → R0-19 muss einen Durchreichmechanismus (`code` + `partner_code`) festlegen.
4. **TTL:** Reservierungsdauer > längstes Course-Retry-Fenster (`CONNECTORS.md:46`); PHP-Parameter `ttl_minutes` nutzen statt globale Option zu ändern.
5. **Kein doppelter Grant:** `vcap_voucher_redeemed` ist nur Audit-/Folgeereignis, **kein** Grant-Auslöser (Grant entsteht vor `commit`).
6. **Woo + Voucher gleichzeitig (Kap. 32.2 Doppelquellen):** Wird ein Voucher im Warenkorb als Zahlungsmittel/Rabatt für ein Kursprodukt verwendet, kommt der Grant ausschließlich aus der Woo-Bestellposition; der Voucher-Connector ist nicht beteiligt. Kurs-Voucher-Typ daher nur mit Kanal `course-captain`, nicht `shop` (`API.md:134-139`).
7. **Refund:** Kauf vor Einlösung erstattet → nichts zu tun. Nach Einlösung `voided` → nur den Voucher-Grant entziehen, Lernhistorie bleibt (E-35). Kurszugang wird in Course entzogen → **kein automatisches** `refund()` (Voucher-Wert zurück nur als bewusste Betreiberaktion, `CONNECTORS.md:56`).
8. **Keine Provisionsmeldung** aus der Einlösung (siehe 4.3).
9. **Tests (R3-05/R3-19):** parallele `reserve` mit gleichem Key aus zwei Prozessen, verschiedene Keys auf denselben Einmalgutschein, Ablauf der TTL zwischen Grant und Commit, `voided` während Reservierung, Ausfall InnoDB (`vcap_temporary_failure`) – C10 zeigt, dass Voucher selbst diese Fälle nicht mehrprozessig testet.

---

## 4. Affiliate Captain (`wpaffiliatemaster@3b7130e`)

### 4.1 Matrix

| # | Quelle | Inhalt / Muster | Relevanz | Abdeckung konzept.md / TODO | Delta | Nichtumfang |
|---|---|---|---|---|---|---|
| A1 | `docs/CONTRACT.md:1-13`; `affiliate-captain.php:41` | Vertrag 1.3.0; gleiche Major kompatibel, sonst `incompatible_contract` (Code `src/Sales/ContractApi.php:409, 550, 607`); nie Tabellen; Beträge Integer Minor Units + ISO-4217; alle Aufrufe idempotent; erst nach `wpac_loaded` | hoch | Kap. 30.2 Affiliate-Zeile, 31.5 | Keines. | Provisionsregeln |
| A2 | `docs/CONTRACT.md:15-71`; `src/functions.php:48, 60, 74` | `wpac_record_sale()`, `wpac_record_refund()`, `wpac_record_reinstatement()` bzw. `do_action('wpac_v1_record_sale', …)` (`:56`); Buchung höchstens einmal je `source` + `order_id`, optional `idempotency_key` (`:63`); Rückgabe `created|duplicate|unattributed` (`:69`); paralleler Request wird über In-flight-Marker abgefangen (`ContractApi.php:124`) | hoch | Kap. 30.2, 30.4 Rezept 6 | Nur relevant, falls Course jemals eigene Verkaufsquelle wird (in v1 nein, E-37). | – |
| A3 | `docs/CONTRACT.md:82-91` | Quellanforderungen: stabile `order_id`/`item_id`/`refund_id`; netto nach Rabatt, **Gutscheine/Geschenkkarten als Zahlung anteilig abziehen, Geschenkkartenkäufe nicht melden** (`:85`) | hoch | – | Bestätigt: Voucher-Einlösung ist kein Verkauf. | – |
| A4 | `src/Integrations/WooCommerce.php:26, 52-79` | Affiliate bucht **WooCommerce-Bestellungen selbst** (`SOURCE = 'woocommerce'`; Hooks `woocommerce_payment_complete`, `…_status_processing/completed`, `…_order_refunded`, Storno/Chargeback/Löschung) | hoch | Kap. 30.2 („bei WooCommerce-Verkauf keine doppelte Meldung aus Course Captain“) | Bestätigt; Muster wie Pass (P8). | – |
| A5 | `wp-couponmaster:src/Compat/AffiliateCaptain.php:40, 113-127`; `docs/CONTRACT.md:356` | Voucher ordnet Code-Kampagnen Partnern zu (`wpac_coupon_affiliate`, Prio 20); **Provision auf den Gutschein-Kauf** standardmäßig ja (`vcap_affiliate_commission_on_voucher_sales`, Standard `true`) | hoch | – | Kurs per Gutschein: Provision fällt beim Gutscheinkauf in Woo an; Course meldet bei Einlösung nichts. | – |
| A6 | `docs/CONTRACT.md:146-170` | Filter u. a. `wpac_commission_base` (0 schließt aus), `wpac_hold_release`, `wpac_hold_until`, `wpac_wc_sale_payload`, `wpac_registered_integrations`; `docs/HANDBOOK.md:209`: Haltefrist kann „until a course has taken place“ verlängert werden | mittel | – | Optional: Course-Pro könnte für Woo-Kurskäufe `wpac_wc_sale_payload`/`hold_until` setzen (z. B. Widerrufsfrist). Nicht R3.1-Pflicht. | – |
| A7 | `docs/CONTRACT.md:181-208` | Ereignisse (`wpac_commission_created/adjusted/…`) feuern erst nach DB-Commit, ohne Kundendaten, „kein Ersatz für die idempotenten Funktionen“ (`:208`) | mittel | Kap. 18 (nach Commit) | Keines. | – |
| A8 | `docs/CONTRACT.md:246-283`; `tests/run.php:332-363` | Add-ons als eigene Plugins mit eigenem `Update URI`/Updater/Lizenz; **Kern ohne Lizenzprüfung**; Ablauf einer Add-on-Lizenz betrifft nie Buchungen/Daten des Kerns (`:276`); CI-Test **A2** bricht, sobald `LicenseClient`, `wplc/v1`, `wplc_`, `Update URI`, `pre_set_site_transient_update_plugins`, `site_transient_update_plugins`, `plugins_api`, `entitlement`, `license_key` im Kern auftauchen | hoch | Kap. 6, 45; R0-39, R3-18 | Vorlage für Course-Free-CI-Guard (5.3). | – |
| A9 | `docs/IMPORT-EXPORT.md:5-15` | Partnerimport: Dry-run (CLI `--dry-run`, Admin immer erst Prüfung), Zeilenreport, Batches à ~15 s mit Fortsetzen, unterbrochener Batch wird vervollständigt statt als „existiert“ gemeldet | hoch | Kap. 45; R0-38, R1-41 | Keines über R0-38 hinaus. | Partner-CSV |
| A10 | `docs/CONTRACT.md:358-360` | Geplante Review-Anbindung read-only, nicht Teil von 1.3 | keine | – | – | – |

### 4.2 (b) Affiliate-Vertrag: externe Verkaufsquellen und doppelte Provision

- **Externe Verkaufsquelle** = Plugin mit eigener Bestellung (Pass `source = pass-captain`, Connector-Add-ons). Sie meldet Verkauf/Refund/Reinstatement über A2 mit stabilen IDs; Idempotenz serverseitig je `source`+`order_id` bzw. `refund_id` (`CONTRACT.md:12, 63, 84`).
- **Doppelvermeidung im Bestand:** (1) Affiliate bucht Woo selbst (A4); (2) andere Quellen überspringen Woo-Bestellungen (Pass `Adapter.php:7-8, 291`); (3) Geschenkkarten-/Gutscheinkäufe werden je nach Einstellung beim Kauf verprovisioniert, Einlösung als Zahlung senkt die Basis (A3, A5); (4) Voucher-Kampagnen-Zuordnung hat Vorrang-Regel (Prio 20 nach direkter Verknüpfung).

### 4.3 Konsequenz für R0-40 (finanzielle Ereignisse)

1. **Course Captain meldet in v1 keine Verkäufe an Affiliate.** Begründung: Kein eigener Checkout in v1 (`konzept.md:146`); Woo-Kurskäufe bucht Affiliate selbst (A4); Gutschein-Kurse sind beim Gutscheinkauf verprovisioniert (A5) – eine Course-Meldung wäre in allen Pfaden eine Doppelprovision.
2. Lern-/Prüfungsereignisse lösen nie Provisionen aus (Kap. 18.2 bestätigt).
3. Woo-Refund/Storno eines Kurses: Course widerruft nur den Grant; Provisionskorrektur macht Affiliate aus dem Woo-Refund selbst – Course sendet **kein** `wpac_record_refund`.
4. Falls später eine eigenständige Course-Verkaufsquelle entsteht (in v1 ausgeschlossen, E-37): `source = course-captain`, `order_id`/`item_id`/`refund_id` stabil, `net_minor` ganzzahlig, `contract` mit Major 1, Rückgabe `duplicate` als Erfolg werten, Registrierung über `wpac_registered_integrations`; Woo-Kanal wie Pass überspringen.
5. R3-19-Fixture „doppelte Kauf-/Affiliate-Events“: Woo-Kauf eines Kurses + Course-Grant-Event + Voucher-Einlösung → genau **eine** Provision (aus Woo bzw. Gutscheinkauf), null Meldungen aus Course.

---

## 5. License Captain (`wp-license@c269e07`)

### 5.1 Matrix

| # | Quelle | Inhalt / Muster | Relevanz | Abdeckung konzept.md / TODO | Delta | Nichtumfang |
|---|---|---|---|---|---|---|
| L1 | `docs/CLIENT-INTEGRATION.md:5-7`; `docs/CLIENT-REPOS.md:3-15`; `docs/ENTITLEMENTS.md:5-11` | Kernplugins frei, ungated, für WordPress.org; **keine** Bibliothek, kein Entitlement-Check, kein eigener Updater, kein Serveraufruf im Kern; nur das Premium-Add-on trägt den Client | hoch | Kap. 6, 45; R0-39 | Keines in der Sache; Course-CI-Guard fehlt (5.3). | Lizenzserver-Verkauf |
| L2 | `docs/CLIENT-INTEGRATION.md:11-44`; `bin/export-client.php`; `src/Client/` | Bibliothek (`LicenseManager`, `LicenseStatus`, `Updater`, `LicenseClient`, `OptionStore`, `Messages`), PHP 7.4/WP 6.5, ohne WooCommerce; **empfohlen: Kopie mit eigenem Namespace** via `php bin/export-client.php --target=… --namespace=… --text-domain=…`; unveränderte Kopie nur für Experimente (erste geladene Kopie gewinnt) | hoch | Kap. 6 („eigene Namespaces … vendorten Client“) | Namespace für Course Pro festlegen (z. B. `CourseCaptainPro\Licensing`) – R0-39. | – |
| L3 | `docs/CLIENT-INTEGRATION.md:46-125` | Minimal-Add-on: `license-config.php`, `register()` im Hauptfile, `uninstall()`; **`Update URI` Pflicht** (`:121`); `version` = `Version:`-Header; Produktschlüssel = Add-on-Ordner = Textdomain, nie Schlüssel des Free-Kerns; Optionspräfix aus Produktschlüssel | hoch | Kap. 6 (Produktschlüssel `course-captain-pro`) | Keines. | – |
| L4 | `docs/CLIENT-INTEGRATION.md:140-162` | Settings u. a. `core_version` (`:155`): Server hält Releases zurück, deren `requires_core` höher ist (`docs/UPDATES.md:71`); `settings_parent=false` + `render_settings()` für eigene Seite; `network` | hoch | Kap. 6 („Core-Versioncheck“, Kursmenü-Unterseite „Pro-Lizenz“) | `core_version` = Course-Free-Version setzen; Lizenzseite als Course-Untermenü via `settings_parent=false` (R0-39). | – |
| L5 | `docs/CLIENT-INTEGRATION.md:164-202` | `is_entitled()` liest nur eine Option, sendet nichts, **fail closed** (`:169`); Zustände `unlicensed/active/grace/expired/suspended/revoked/inactive/deactivated/site_changed/stale/misconfigured`; „An expired or missing license only switches premium features off … never change what the free core plugin does“ (`:202`) | hoch | Kap. 6 (Lizenzablauf), 30.4 Rezept 7; R3-15 | Course-UI-Texte/Verhalten je Zustand in R0-39 tabellieren. | – |
| L6 | `docs/CLIENT-INTEGRATION.md:204-253`; `docs/ENTITLEMENTS.md:33-42` | Signierter Snapshot (HMAC + Ed25519), Offline-Fenster = signiertes `cache_expires_at` (14 Tage, nie über `access_until`, nicht auf `paid_until` kürzen); **nur eine signierte Antwort entzieht Rechte** (`:214`); Backoff 15 min → 1 Tag | hoch | Kap. 6 (14 Tage, Ed25519) | Keines. | – |
| L7 | `docs/CLIENT-INTEGRATION.md:287-304` | **Multisite-Modus vor dem ersten Release festlegen** (`:289`), Wechsel später = Neuaktivierung bei allen Kunden | hoch | Kap. 6 schweigt; Multisite Kap. 30.1 R4 | Produktentscheidung D4. | – |
| L8 | `docs/CLIENT-INTEGRATION.md:306-317` | Updater: `update_plugins_{host}`, Platzhalter-Paket, Download mit SHA-256-Prüfung, Ordnerprüfung; ohne Lizenz sichtbar „Update verfügbar, nicht automatisch“ | hoch | Kap. 6 (Update) | Keines. | – |
| L9 | `docs/CLIENT-INTEGRATION.md:340-348` | Gesendete Daten: `site_url`, `installation_id`, `product_key`, `product_version`, `wp_version`, `php_version`, `environment`, bei Aktivierung Lizenzschlüssel; Installationsgeheimnis im Klartext in `wp_options` | hoch | Kap. 6 („keine … Kursteilnehmerdaten an den Lizenzserver“), Kap. 20 | Datenschutzhinweis Course Pro aus `privacy_text()` übernehmen; Backup-Hinweis zum Installationsgeheimnis in R0-16. | – |
| L10 | `src/Catalog/EntitlementCatalog.php:129-146`; `docs/ENTITLEMENTS.md:48-62` | Bekannte Produktschlüssel nur `voucher-captain-pro`, `pass-captain-pro`, `affiliate-captain-pro`; weitere über Filter `wplc_known_product_keys`; Präfixregel nennt nur `affiliate.`, `voucher.`, `pass.` (`:62`); Add-ons „live in hastla007/captain-pro“ (`:50`) | hoch | Kap. 6 („im License-Captain-Produktkatalog neu anzulegen“, Präfix `course.`) | **Partnerseitig fehlt** `course-captain-pro` ⇒ `course` (Code oder Filter im Shop) und die Präfixregel `course.` – separater Auftrag (D5). | – |
| L11 | `docs/TODO.md:70, 149, 711, 720` | T-131 „Kein Produkt-Plugin spricht bisher mit dem Server“; T-159 Add-ons in `captain-pro`; vorbereitete Katalogeinträge nur für `form-captain-pro`, `review-captain-pro`, `workflow-captain-pro` – **kein** Course | hoch (Lücke) | Kap. 6 („Vor Release zwingend …“) | Course fehlt in der License-Planung (D5). | – |
| L12 | `docs/RELEASE-READINESS.md:1-66`; `docs/CLIENT-REPOS.md:36-38` | Go/No-go-Gates (Build, Kauf/Verlängerung, Updates/Produkt-Plugins, Security, Betrieb, Sign-off) – **alle offen**; „No add-on exists yet … has not run in a real add-on against a real shop“ | hoch | Kap. 45 („reale Release-Evidenz“); R0-35, R3-18 | R3-18 hängt an einem nicht freigegebenen Lizenzserver – als externer Blocker führen. | – |

### 5.2 (c) Wie ein Pro-Add-on lizenziert und aktualisiert wird (realer Ablauf)

1. **Build:** Client mit `bin/export-client.php` unter eigenem Namespace und eigener Textdomain ins Add-on kopieren (`CLIENT-INTEGRATION.md:27-33`); Update der Bibliothek = Export erneut ausführen und Add-on releasen, kein geteiltes Paket (`CLIENT-REPOS.md:32-34`).
2. **Konfiguration:** `product_key`, `version`, `server_url` (stabiler eigener Hostname), gepinnte Ed25519-`public_keys`, `account_url`, optional `core_version`, `network`, `settings_parent` (`:51-65, 140-162`).
3. **Header:** `Update URI` mit Host des Lizenzservers (Pflicht, `:121`); Version = Header.
4. **Laufzeit:** `register()` hängt Lizenzseite, Notices, Tages-Check (Cron, Catch-up nach 36 h), Datenschutztext und Updater ein (`:117, 319-325`); Features erst auf `init` per `is_entitled()` laden (`:94-104`).
5. **Updates:** WordPress fragt über `update_plugins_{host}`; Download nur mit gültiger Lizenz, SHA-256 geprüft (`:306-317`).
6. **Deinstallation:** `uninstall()` gibt Aktivierung frei und löscht Lizenzdaten (`:336-338`).
7. **Server:** Produktschlüssel und Entitlements im Shop-Katalog; Präfix je Produkt (L10).

**Was Free NICHT enthalten darf** (Quelle L1 + Guard A8): `LicenseClient`/`LicenseManager`-Kopie, Aufrufe an `wplc/v1`, `wplc_`-Präfixe, `Update URI`-Header, eigene Hooks auf `pre_set_site_transient_update_plugins`, `site_transient_update_plugins`, `plugins_api`, Entitlement-Abfragen, Lizenzschlüssel-Handling, Lizenzseite.

### 5.3 Konsequenz für R0-39 (Lizenzclient-Grenze)

- **Free (`course-captain`):** CI-Guard nach Vorbild `wpaffiliatemaster:tests/run.php:332-363` (Musterliste aus 5.2) plus Test „Free macht keine ausgehenden Requests“ (Vorbild ZF6, `CONTRACT.md:278`). Free kennt keine Entitlement-Keys; Pro erweitert nur über öffentliche Course-Hooks (Kap. 18).
- **Pro (`course-captain-pro`):** Client 2.0.x per Export unter eigenem Namespace; `Update URI`; `core_version` = Course-Free-Version (Release-Sperre über `requires_core`); `is_entitled()` erst ab `init`; Lizenzseite als Course-Untermenü (`settings_parent=false`); Entitlement-Keys mit Präfix `course.` (Kap. 6).
- **Ablauf/Ausfall:** Verhalten je `LicenseStatus`-Zustand; Offline-Fenster nur aus signiertem `cache_expires_at`; nur signierte Antworten entziehen Rechte; geplante Pro-Jobs pausieren (siehe R0-32 W1); nie Free-Daten, Grants oder Nachweise löschen/verbergen (L5).
- **Multisite:** Lizenz **pro Site** (`network => false`, E-07).
- **Partnerabhängigkeit:** `course-captain-pro` fehlt in `EntitlementCatalog::PRODUCT_KEYS` und in der License-TODO (L10/L11) – der Auftrag an License Captain bzw. Captain Pro ist beschlossen (E-42, vor R3-18, nach Neuzuschnitt E-95); bis er umgesetzt ist, ist R3-18 nicht abschließbar.
- **Nicht verifizierbar:** Kernel, FeatureRegistry, Build-Registry und `TARIFE.md` in `captain-pro` (Abschnitt 6). Pro wird dort gebaut (E-273); der Zugriff ist Voraussetzung.

---

## 6. Captain Pro (`hastla007/captain-pro`) – nicht zugänglich

| Behauptung in konzept.md | Stelle | Prüfstatus |
|---|---|---|
| Pro-Ort `captain-pro/addons/course-captain-pro/`, Verzeichnisbaum | `konzept.md:8, 157, 247-248` | **beschlossen (E-273)**, Inhalt aber **nicht prüfbar** (Zugriff fehlt); indirekt gestützt: `wp-license:docs/ENTITLEMENTS.md:50` und `docs/TODO.md:149` (T-159: Add-ons, Lizenzclient-Kopie, Kernel, Build, CI in `captain-pro`) |
| Tarife `single/business/agency` laut `captain-pro/docs/TARIFE.md` | `konzept.md:166` | **nicht prüfbar**; License nennt als Beispiel `solo, business, agency` (`ENTITLEMENTS.md:58`), License-TODO für Workflow „Single/Business/Agency“ (`docs/TODO.md:720`) |
| Add-on-Kernel, FeatureRegistry, ZIP-Auslieferung | `konzept.md:206` | **nicht prüfbar** |
| Kap. 45: `captain-pro/docs/ARCHITECTURE.md` (isolierte Clientbibliotheken, Free-Daten bei Lizenzende unangetastet) | `konzept.md:1400` (Quellenliste Kap. 45) | **nicht prüfbar**; inhaltlich deckungsgleich mit License L1/L2/L5 |

**Konsequenz für R0-39:** Der Build-Ort ist beschlossen (E-273). Bis zum Zugang zu `captain-pro` gilt der License-Client-Vertrag (Abschnitt 5) als einzig belegte Grundlage. Kernel-spezifische Annahmen aus Kap. 6/8 (FeatureRegistry, Build-Registry, gemeinsamer Kernel) sind als **offene Annahmen** zu führen, nicht als Vertrag.

---

## 7. Entscheidungsbedarf und quellinterne Drift

### 7.1 Entscheidungsbedarf – beschlossen

| ID | Frage | Beschluss | Quelle |
|---|---|---|---|
| D1 → **E-35** | Gutschein wird **nach** der Kurseinlösung storniert: Kurszugang behalten oder entziehen? | **Nur den Voucher-Grant entziehen**; andere Grants, Lernhistorie und Nachweise bleiben | `wp-couponmaster:CONNECTORS.md:54`; Kap. 31.2 |
| D2 → **E-36** | Gruppen-/Mehrplatzgutscheine: ein Code pro Platz oder pro Paket? | **Ein Code pro Gruppenplatz** | `CONNECTORS.md:60-65`; Kap. 32.2 B |
| D3 → **E-37** | Eigene Verkaufsquelle für Affiliate? | **Nein in v1**; Course meldet keine Verkäufe an Affiliate Captain | `konzept.md:146`; Affiliate A4/A5 |
| D4 → **E-07** | Course Pro auf Multisite: Lizenz pro Site oder pro Netzwerk? | **Pro Site** (offiziell nur Single-Site; Pro-Lizenz zählt je Site) | `wp-license:docs/CLIENT-INTEGRATION.md:287-304` |
| D5 → **E-42**, **E-273** | Auftrag an License Captain/Captain Pro für `course-captain-pro` – und wann? | Produkt **vor R3-18** beauftragen, nach Neuzuschnitt der Entitlement-Schlüssel (E-95); Pro wird in `captain-pro/addons/course-captain-pro` gebaut (E-273); Zugriff auf `captain-pro` für die Prüfung nötig | `EntitlementCatalog.php:129-133`; `ENTITLEMENTS.md:62`; `docs/TODO.md:711, 720` |

**Offene Punkte nach Einarbeitung**

- **Zugriff auf `captain-pro`** (E-42, E-273) fehlt weiterhin; §6 bleibt bis dahin unprüfbar.
- **E-31** (Gutscheinziel auch WooCommerce-Produkt) steht in Spannung zu §3.3 Nr. 6 (Kurs-Voucher nur Kanal `course-captain`, nicht `shop`); wie der Woo-Produkt-Zieltyp ohne Doppel-Grant abgebildet wird, legen R0-40/R3-04 fest.
- D1 sah zusätzlich eine Betreiber-Einstellung mit diesem Default vor; E-35 nennt sie nicht.
- R0-32 W1 empfiehlt, geplante Pro-Jobs bei Lizenzablauf zu pausieren; E-94 sagt „Konfiguration wirkt eingefroren weiter“. Ob geplante Pro-Jobs (z. B. Kampagnen-Drip, Erinnerungen) damit weiterlaufen oder pausieren, ist in R0-39 zu klären.

### 7.2 Quellinterne Drift (keine Course-Entscheidung, aber bei R3 zu beachten)

- Pass `docs/TESTING.md:85-93` vs. Code/CHANGELOG/CI (Deadlock behoben, kein `continue-on-error`) – siehe 2.4.
- Pass `docs/EXTENSIONS.md:5` („kein Lizenzserver“) vs. License `ENTITLEMENTS.md:55` (`pass-captain-pro`).
- Affiliate `docs/CONTRACT.md:356` („ab Voucher Captain 1.2.0“) vs. Voucher `VCAP_VERSION = '1.1.0'` (`voucher-captain.php:22`), obwohl `src/Compat/AffiliateCaptain.php` bereits vorhanden ist.
- License `CLIENT-REPOS.md:23` („in its own repository“) vs. `ENTITLEMENTS.md:50`/`TODO.md:149` (alle Add-ons in `captain-pro`). Für Course entschieden: `captain-pro` (E-273).

## 8. Widersprüche zu konzept.md

| ID | Stelle in konzept.md | Widerspruch | Beleg |
|---|---|---|---|
| X1 | Kap. 32.1 („Ein Voucher-Connector darf WooCommerce nicht stillschweigend voraussetzen …“), 32.3 R3.3 („Voucher allein“) | Voucher Captain **selbst** setzt WooCommerce zwingend voraus; „Voucher allein“ kann nur „ohne Course-Woo-Connector“, nicht „ohne WooCommerce“ bedeuten. Der direkte Kurs-Einlösekanal existiert, läuft aber in einer Woo-Installation. | `wp-couponmaster:voucher-captain.php:9`; `API.md:3`; `RedemptionService.php:130, 219` |
| X2 | Kap. 30.2 Affiliate-Zeile („Bei eigenständigem Course-Verkauf optional … Sale-/Refund-Meldung“) | Kap. 5 schließt einen eigenen Checkout für v1 aus; Woo- und Gutscheinpfade sind bei Affiliate bereits abgedeckt. Die Option ist in v1 gegenstandslos und birgt Doppelprovision. | `konzept.md:146`; Affiliate A4/A5 |
| X3 | Kap. 45 (Voucher „`API.md`: in-process PHP v1, Reserve/Commit/Release, idempotente Einlösung“) | Vermengt zwei APIs: `api_v1` ist bestellgebunden (`WC_Order`, kein eigener Key); der Kurs-Weg ist `vcap()->redemptions` mit Aufrufer-Key und eigenen Fehlercodes. | `API.md:5-15, 35` vs. `:50-103` |
| X4 | Kap. 18 Fehlercodes (`forbidden, invalid_scope, duplicate, not_found, incompatible_contract, retryable`) | Reichen für den realen Voucher-Vertrag nicht (Konflikt, abgelaufen, storniert, Rate-Limit, Ziel nicht verfügbar). | 3.3 Pkt. 3 |
| X5 | Kap. 18.2 Zeile „Pass-Captain-Teilnahme bestätigt → **Kurs-/Workshopabschluss** oder Grant“ | **Innerer** Widerspruch zu Kap. 30.2 („Check-in ist nicht automatisch … Kursabschluss“) und 31.1/31.6 („gewähren höchstens Zugang“). Zudem gibt es keinen versionierten Pass-Teilnahmevertrag (P5) und die Connector-Registry ist unverdrahtet (P6). | `wp-passmaster:docs/HOOKS.md:4-6`; `ConnectorRegistry.php` ohne Aufrufer |
| X6 | Kap. 6 Tarife „gemäß tatsächlich dokumentiertem Captain-Pro-Stand“ | Nicht verifizierbar (captain-pro privat); License nennt abweichend `solo` als Beispiel-Plankey. Keine Kollision der Funktionsmatrix, aber Plan-IDs vor Katalogpflege abgleichen. | `wp-license:docs/ENTITLEMENTS.md:58` |
| X7 | Kap. 6 „Lizenzprodukt `course-captain-pro` (im License-Captain-Produktkatalog neu anzulegen)“ / Kap. 30.2 License-Zeile | Kein Widerspruch in der Sache, aber License-Planung kennt Course nicht (nur Voucher/Pass/Affiliate + Form/Review/Workflow vorbereitet); Präfixregel schließt `course.` derzeit aus. | L10, L11 |

## 9. Nachweis und Grenzen

- Gelesen: die zitierten Dokumente und Codestellen der vier Repos (nur lesend; keine Datei der Suite verändert). Keine Tests ausgeführt; Aussagen zu CI/Tests beziehen sich auf vorhandene Testdateien und Workflow-Definitionen, nicht auf eigene Läufe.
- Nicht geprüft: vollständige Hook-Liste der vier Plugins, REST-Schemata im Detail, `captain-pro`.
- D1–D5 sind beschlossen (E-35, E-36, E-37, E-07, E-42; Build-Ort E-273). Die Konsequenzen 2.5/2.6, 3.3, 4.3, 5.3 sind in R0-37, R0-39, R0-40 (und R0-19 für Fehlercodes) zu übernehmen.
