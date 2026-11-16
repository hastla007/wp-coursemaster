# R0-34 · Integrationsstatus und Support-Vertrag

**Status:** Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet. Task R0-34 ist nach Review abhakbar. Es gibt keine Implementierung und keinen Code. Dieses Dokument ist die Spezifikation für **R1-38** (lesende Anzeige, Reparatur interner Zustellungen, Diagnose), **R3-10** (Monitoring, Support-Recovery, Alarmierung) und **R3-16** (Providerstatus-Anzeige) und schließt FLOW-005 (Partnererkennung) auf Vertragsebene.
**Stand:** 16.11.2026
**Bezug:** TODO R0-34 (Voraussetzung R0-19, R0-21, R0-27; DoD: „Provider-ID/Version/Contract, letzter Erfolg, Queue, Correlation-ID, Fehlercode, Retry-Rechte und Datenschutzklasse spezifiziert“), R1-38, R3-10, R3-16, FLOW-005; `konzept.md` Kap. 30.3 (Integrationszustände, Admin-Anzeige, Transport) und Kap. 33 (Suite-Erkennung, 8 Zustände, kein Ersatz für Freigabe); `R0-19` §1.6 (Handshake), §3 (Fehlerkatalog), §5.4 (Outbox/Zustellung), §5.7 (Registry), §6 (8 Zustände, Fehlerklassen, Übergänge); `R0-21` §2 (Lease M0, CAS, R-6); `R0-27` SR-2 (Privacy-Klassen der Felder), INV-S-08, §2.5; `R0-13` §1.2, §2.1 H-27/H-28/H-34, §2.2, §3, §4; `R0-17` §2 (DK-01…DK-14); `R0-32` F5, F10, V4, W6, W9, W13, W18; `R0-36` §1, C8.

## Beschlussstand

Die Annahmen und die Fragen D34-1…D34-8 sind entschieden (`ENTSCHEIDUNGEN.md`, Beschlussprotokoll und „Von Claude entschieden“, 16.11.2026); D34-1, D34-2, D34-3, D34-5, D34-6, D34-8 = **E-254…E-259**. D34-4 und D34-7 sind im Register nicht geführt (Offene Punkte). **Abweichend von den früheren Annahmen** gilt:

| Beschluss | Wirkung in diesem Dokument |
|---|---|
| **E-38** | Öffentliche Course-Events, Read-API und **Connector-Registry sind Pro**. In R1 stammen die Daten der Seite „Integrationen“ allein aus dem Partnerkatalog (§1.3, Erkennung); alle Felder mit Phase „R3“ sowie `ConnectorRegistry::status()` gibt es erst mit Pro. Die interne Outbox des Free-Kerns (R1-43, E-182) ist kein Connector und erscheint nicht als Provider |
| **E-39**, E-39b | Schreibende Workflow-Aktionen in v1 über die Grant-Quelle `workflow` im Course-Pro-Connector; bis zur Anpassung des Workflow-Captain-Konzepts bleibt `workflow-captain` im Katalog `active_incompatible/no_published_contract` |
| E-40, E-41, E-53 | Connectoren in Course Pro, erst wenn Partner-Registries existieren; Woo/Voucher Pro |
| E-185, **E-254** | Unterseite „Integrationen“ schon in R1, nur lesend; H-34 im Systemstatus als Zusammenfassung |
| E-255 | Lesen mit #22 (`ccap_view_system_status`): Admin, Kursmanager, `ccap_support` |
| E-256 | Erkannte Partner mit Hinweis „kein Vertrag verfügbar“; nicht installierte nicht bewerben (E-100) |
| E-257 | Alarm-Mail bei `failed` (R3), gebündelt höchstens einmal je 24 h |
| **E-258** | Offene Zustellungen höchstens **30 Tage**, danach Dead-Letter mit Grund `retention` (statt 90 Tage nach der früheren Empfehlung) |
| E-259 | Übernahme einer Course-Aktion durch einen Partner erst in R3, mit sichtbarem Hinweis und Abschaltmöglichkeit |
| E-119, E-120 | Event-Speicherung ohne Abonnent und Freigabe je Event-Klasse gelten nur mit Pro |
| **E-207** | Präfix `ccap_` für Tabellen, Caps, Rollen und Cron (`ccap_integrations`, `ccap_outbox_delivery`, `ccap_support`, `ccap_hourly` …); Fehlercodes und Nonce-Aktionen ebenfalls `ccap_` (**E-285**) |
**Normative Sprache:** **MUSS** = Vertragsbestandteil; **SOLL** = Standard, begründete Abweichung erlaubt; **KANN** = optional. Präfixe: `ccap_` für Tabellen, Caps, Rollen und Cron (E-207); Fehlercodes und Nonce-Aktionen ebenfalls `ccap_` (E-285).

---

## 0. Kurzfassung

1. **Ein Datensatz je Provider** (`IntegrationStatus`, §2): Partnererkennung, Connector, Vertragsversionen, einer der **8 Zustände aus R0-19 §6** mit `state_reason`, letzter Erfolg und Fehler (Fehlerklasse, `ccap_*`-Code, Partnercode, Fehler-ID, Correlation-ID), Queue-Zähler aus `ccap_outbox_delivery`, Dead-Letter, Eingang, Datenschutzklasse und erlaubte Aktionen des aktuellen Betrachters. In R1 sind nur die Erkennungsfelder befüllt; alles andere ist `null` und wird erst durch einen registrierten Connector (R3) gefüllt.
2. **R1 = lesend** (E-185): Unterseite „Integrationen“ (E-202) zeigt erkannte Partner aus einem festen **Partnerkatalog** (§1.3) mit Zustand, Version und Kompatibilität, **ohne** Knöpfe zum Einrichten, Testen oder Reparieren. Systemstatus H-34 fasst zusammen. Es gibt keine Installation, keine Aktivierung, keine Upselling-Hinweise (K-23, E-100).
3. **R3 = Aktionen**: Verbindung einrichten/deaktivieren, testen, **Retry**, **Replay**, **Pause/Fortsetzen**, **Dead-Letter verwerfen**, Event-Klassen freigeben. Jede Aktion hat Capability, Obergrenze, Pflichtbegründung (wo nötig), Audit, Idempotenz-Schlüssel und CAS auf den Zustellzeilen (§4). Dieselben Aktionen Retry und Dead-Letter verwerfen gelten schon in **R1** für die **internen** Abonnenten (z. B. `notification`) im Betriebsdashboard R1-38.
4. **Nichts wird gelöscht:** „Verwerfen“ setzt eine Zustellung auf `skipped` (`skip_reason = discarded`); solange das Event im Log liegt, ist ein Replay möglich. Replay erzeugt **nie** neue `event_id`s.
5. **Diagnose je Integration** (§5): Abschnitt `integrations[]` im Diagnosepaket aus R0-13 §2.2, nach Allowlist, ohne Payloads, Personenreferenzen im Klartext, Secrets, Endpunkt-URLs oder Partner-Antworttexte.
6. **Keine Cross-Plugin-Datenzugriffe** (R3-16): Course zeigt nur, was Course gesendet, empfangen und geprüft hat; Partnerzustand nur über dokumentierte öffentliche Versions-/Status-APIs.
7. **INT-001…INT-052** (§6) für R1-38, R3-10, R3-16; **D34-1…D34-8** (§7) beschlossen als E-254…E-259 (D34-4, D34-7 ohne Registereintrag).

---

## 1. Begriffe, Abgrenzung, Partnerkatalog

### 1.1 Begriffe

| Begriff | Bedeutung | Quelle |
|---|---|---|
| **Partner** | anderes Plugin auf derselben Site (WooCommerce, Voucher Captain …), erkannt über eine dokumentierte Versions-API | Kap. 33, R0-19 §1.6 |
| **Connector** | Code, der den Datenaustausch mit einem Partner umsetzt und sich in der `ConnectorRegistry` registriert (Course Pro oder separates Plugin, E-40, E-53) | R0-19 §5.7 |
| **Provider** | die **Zeile** auf der Seite „Integrationen“: ein Partner mit 0…1 Connector. `provider_id` = Connector-ID, sonst Katalog-ID des Partners. Ein Connector ohne Partner (z. B. ausgehende Webhooks, Pro) ist ebenfalls ein Provider | neu |
| **Interner Abonnent** | Kernmodul, das als `EventSubscriber` an der Outbox hängt (R1: `notification`, R0-27 T27-7). Kein Provider, erscheint nicht auf „Integrationen“, sondern im Betriebsdashboard (R1-38) | R0-27 §3 |
| **Zustellung** | Zeile in `ccap_outbox_delivery` (`event_uuid`, `subscriber_id`, Status `pending/dispatching/delivered/failed/dead/skipped`) | R0-19 §5.4 Nr. 3 |
| **Dead-Letter** | Zustellung im Status `dead` (nach 8 Versuchen oder sofort bei permanentem Fehler nach `failed`-Regel R0-19 §5.4 Nr. 4) | R0-19 §5.4 |

### 1.2 Was dieses Dokument nicht regelt

- Die 8 Zustände, ihre Erkennung und Grundübergänge (R0-19 §6) – hier nur Ableitungsregeln, Gründe, Texte und Aktionen.
- Envelope, Eventkatalog, Backoff-Folge (R0-19 §5.1–§5.4).
- Fachliche Reparaturen an Grants, Versuchen, Importen (R0-13 §3, K-01…K-17).
- Partnerseitige Statusseiten und gemeinsames Suite-Dashboard (Kap. 33 Entscheidung 1, FLOW-006).
- Finanzielle Ereignisse (R0-40).

### 1.3 Partnerkatalog R1 (lesend)

Course Captain Free bringt einen **statischen Katalog** mit. Er enthält nur die Erkennungsprobe und die geprüfte Mindestversion, **keine** Austauschlogik. Die Probe läuft nur gegen dokumentierte öffentliche Konstanten/Funktionen, nie gegen den Plugin-Slug allein (Kap. 33). Slug und `get_plugins()` dienen **nur** der Unterscheidung `not_installed` ↔ `inactive`.

| Katalog-ID | Partner | Erkennungsprobe (aktiv + Version) | Kompatibel, wenn … | Belegstand | Max. Zustand in R1 |
|---|---|---|---|---|---|
| `woocommerce` | WooCommerce | `defined('WC_VERSION') && function_exists('WC')` | `WC_VERSION` im Fenster der Plattformmatrix (R0-20) | R0-20 | `active_compatible` |
| `voucher-captain` | Voucher Captain | `defined('VCAP_VERSION') && function_exists('vcap')` | `version_compare(VCAP_VERSION, '1.1.0', '>=')` | R0-36 C8, `voucher-captain.php:22` @ `2d84943` | `active_compatible` |
| `affiliate-captain` | Affiliate Captain | `defined('WPAC_CONTRACT_VERSION')` | Vertrags-Major `1` | R0-36 §1 (`1.3.0` @ `3b7130e`) | `active_compatible`, Grund `no_exchange_planned` (E-37) |
| `pass-captain` | Pass Captain | **offen**: dokumentierte Versionskonstante in R0-36 nicht belegt (nur `_v1`-Hooks, P5) | – | R0-36 P5/P6 | `active_incompatible`, Grund `no_published_contract` (E-256) |
| `form-captain`, `workflow-captain`, `review-captain` | Form/Workflow/Review Captain | Konstanten nur im Partnerkonzept (z. B. `FORM_CAPTAIN_CONTRACT_VERSION`), kein Code | – | R0-32 F10 | `active_incompatible`, Grund `no_published_contract` (E-256) |

**Regeln:** Der Katalog wird je Release geprüft (R3-09/R3-11) und nur per Release geändert, nie per Filter erweitert (sonst könnte Drittcode Kompatibilität vortäuschen). Course Captain Pro selbst ist kein Provider; es erscheint auf der Seite „Pro-Lizenz“ (S-23). Ein in R3 registrierter Connector **ersetzt** die Katalogregel „kompatibel, wenn …“ durch seine `contractRange()` und `detectPartner()` (R0-19 §1.6); die Katalog-ID bleibt die `provider_id`, wenn der Connector dieselbe ID trägt (MUSS für `woocommerce`, `voucher-captain`).

---

## 2. Datenvertrag je Provider

### 2.1 Felder `IntegrationStatus` v1

Phase: **R1** = in R1 befüllt; **R3** = erst mit registriertem Connector, vorher `null`. Sichtbar: **L** = jeder mit Lesecap (E-255), **A** = nur mit #20 `ccap_manage_integrations`.

| Feld | Typ | Phase | Sichtbar | Bedeutung / Regel |
|---|---|---|---|---|
| `provider_id` | string `[a-z0-9-]{1,64}` | R1 | L | stabile ID (§1.1); Pfadparameter `{connector}` (R0-19 §2.1) |
| `label` | string (übersetzt) | R1 | L | Anzeigename |
| `kind` | enum `partner\|connector\|partner_connector` | R1 | L | nur Katalog / nur Connector / beides |
| `partner.detected` | enum `missing\|installed\|active` | R1 | L | Rohergebnis der Probe |
| `partner.version` | SemVer \| null | R1 | L | gefundene Plugin-Version |
| `partner.contract_version` | SemVer \| null | R1 | L | vom Partner veröffentlichte Vertragsversion (z. B. `WPAC_CONTRACT_VERSION`), sonst `null` |
| `partner.required` | string | R1 | L | benötigte Version/Vertrag als Bereich, z. B. `>=1.1.0` |
| `connector.id` / `connector.version` | string / SemVer \| null | R3 | L | `Connector::id()`, `version()` |
| `connector.contract_range` | SemVer-Bereich \| null | R3 | L | `contractRange()` gegen Course-Vertrag |
| `connector.capabilities[]` | list | R3 | L | aus R0-19 §5.7 (`event_subscriber`, `grant_source` …) |
| `connector.edition` | enum `free\|pro\|third_party` | R3 | L | Herkunft des Connectors |
| `course.contract_version` | SemVer | R1 | L | `COURSE_CAPTAIN_CONTRACT_VERSION` (V-01) |
| `state` | enum der 8 Zustände (R0-19 §6) | R1 | L | `not_installed`, `inactive`, `active_incompatible`, `active_compatible`, `needs_configuration`, `connected`, `degraded`, `failed` |
| `state_reason` | enum (§2.2) | R1 | L | genau ein Grund je Zustand |
| `state_since` | RFC 3339 UTC | R1 | L | letzter Zustandswechsel |
| `checked_at` | RFC 3339 UTC | R1 | L | letzte Erkennung/Prüfung |
| `opt_in` | `{enabled: bool, enabled_at, enabled_by_role}` | R3 | L | Betreiberfreigabe (Kap. 33); Akteur nur als Rolle (wie E-145) |
| `paused` | `{since, by_role, reason_code}` \| null | R3 | L | Notfall-Pause (§4, R0-32 W6) |
| `last_success` | `{at, kind: test\|delivery\|inbound}` \| null | R3 | L | letzte erfolgreiche Kommunikation (Kap. 30.3) |
| `last_failure` | Objekt \| null | R3 | L | `{at, error_class, error_code, partner_code, error_id, correlation_id, event_type?, retryable}` |
| `last_failure.error_class` | enum (R0-19 §6, 12 Werte einschließlich `retention`) | R3 | L | `incompatible_contract` … `internal` |
| `last_failure.error_code` | `ccap_*` | R3 | L | Code aus R0-19 §3 |
| `last_failure.partner_code` | string `^[a-z0-9_.:-]{1,64}$` \| `<redacted>` | R3 | L | Original-Code des Partners; alles, was das Muster verletzt, wird ersetzt |
| `last_failure.error_id` | string | R3 | L | `delivery:<id>` bzw. `request_id` (GR-7); Suchschlüssel für Log/Audit |
| `last_failure.correlation_id` | UUID | R3 | L | aus dem Envelope bzw. dem Vorgang (R0-19 §5.1) |
| `queue` | Objekt | R3 | L | `{pending, dispatching, retry_wait, oldest_pending_at, next_attempt_at, stale_leases}`; `retry_wait` = `failed` mit `next_attempt_at` in der Zukunft; Zähler gekappt bei 10 000 (Anzeige „> 10 000“) |
| `dead_letter` | Objekt | R3 | L | `{count, oldest_at, newest_at, by_error_class{}}`; enthält `retention` für offene Zustellungen nach 30 Tagen (E-258, R0-19 §5.4 Nr. 2) |
| `skipped` | Objekt | R3 | A | `{count_30d, by_reason{discarded, disabled, hold_mode, privacy_not_granted}}`; abgelaufene offene Zustellungen zählen nicht hier, sondern als `dead` mit `by_error_class.retention` (E-258) |
| `inbound` | Objekt \| null | R3 | L | nur bei Webhook/Callback: `{accepted_24h, duplicate_24h, rejected_24h, last_rejected_class}` aus `ccap_inbox_event` und `webhook_rejected` |
| `privacy.class` | enum (§2.4) | R1 | L | höchste Datenschutzklasse des Austauschs |
| `privacy.required_tags[]` / `granted_tags[]` / `missing_tags[]` | list | R3 | L | `requiredPrivacyTags()` gegen Freigaben nach E-120 |
| `outbound_mode` / `hold_mode` | enum / bool | R1 | L | aus H-25/H-26 (E-85/E-86); wirken auf alle Provider |
| `ownership_claims[]` | list \| null | R3 | L | angenommene Zuständigkeitsübernahmen (z. B. Workflow übernimmt Mail-Typ X, R0-32 W18), `{scope, since, state: proposed\|accepted\|revoked}`; E-259 |
| `secrets` | map `name → <gesetzt>\|<leer>\|<konstante>` | R3 | A | **nie** Werte (E-87, R0-27 SR-2 `secret`) |
| `actions[]` | list | R1 | L | für **diesen Betrachter und diesen Zustand** erlaubte Aktionen (§4.1) mit `{id, enabled, disabled_reason?}`; R1 immer leer |
| `help_url` | URL | R1 | L | Doku-Anker, kein Shop-/Upgrade-Link (E-100) |
| `schema_version` | `1.0` | R1 | L | Version dieses Datenvertrags (additiv erweiterbar wie Events) |

**Nie im Datensatz:** Event-Payloads, `user_ref`/E-Mail/Namen, Endpunkt-URLs mit Pfad oder Query, Secrets, Partner-Antworttexte, Stacktraces, Pfade.

### 2.2 `state_reason` (Enum, additiv erweiterbar)

| Zustand | erlaubte Gründe |
|---|---|
| `not_installed` | `not_found` |
| `inactive` | `plugin_inactive` |
| `active_incompatible` | `version_too_low`, `version_too_high`, `contract_major_mismatch`, `no_published_contract`, `connector_contract_mismatch` |
| `active_compatible` | `not_enabled`, `connector_missing`, `no_exchange_planned` |
| `needs_configuration` | `missing_setting`, `missing_secret`, `missing_mapping`, `privacy_not_granted` |
| `connected` | `ok` |
| `degraded` | `retries_pending`, `dead_letters_present`, `queue_stale`, `paused_by_admin`, `hold_mode`, `outbound_catch`, `license_inactive`, `rate_limited` |
| `failed` | `auth_failed`, `dead_letter_threshold`, `health_check_failed`, `permanent_rejection` |

### 2.3 Ableitungsregeln (präzisiert gegenüber R0-19 §6)

| Nr. | Regel |
|---|---|
| AB-1 | Erkennung läuft bei jedem Aufruf der Seite „Integrationen“/Systemstatus (Request-Memo) und stündlich in `ccap_hourly`. Sie ist **lesend** gegenüber dem Partner (keine Option schreiben, keine Partner-Hooks auslösen). |
| AB-2 | Vorrang bei mehreren zutreffenden Gründen: `failed` > `degraded` > `needs_configuration` > `connected`; innerhalb von `degraded`: `paused_by_admin` > `hold_mode` > `outbound_catch` > `license_inactive` > `dead_letters_present` > `queue_stale` > `retries_pending` > `rate_limited`. |
| AB-3 | `degraded/retries_pending`: mindestens eine Zustellung `failed` mit offenem Backoff. `queue_stale`: älteste `pending`-Zustellung > 15 min **oder** `dispatching` mit abgelaufenem `locked_until` (H-28, RC-5). |
| AB-4 | `degraded/dead_letters_present`: ≥ 1 `dead`, Schwelle für `failed` nicht erreicht. **`failed/dead_letter_threshold`**: ≥ 3 neue `dead` in 60 min (**E-311**, nicht schon beim ersten) **oder** 3 aufeinanderfolgende fehlgeschlagene `healthCheck()` (D34-4). |
| AB-5 | `failed/auth_failed`: Fehlerklasse `auth_failed` bei Zustellung oder Test. `failed/permanent_rejection`: Partner lehnt alle Zustellungen eines Laufs permanent ab (`DeliveryResult::fail` ≥ 20 in Folge). |
| AB-6 | Zustand `failed` stoppt den Dispatcher für diesen Abonnenten (Zustellungen bleiben `pending`). Rückkehr nach `connected` nur nach erfolgreichem Test (IA-02); vorhandene Dead-Letter bleiben sichtbar und halten den Zustand danach auf `degraded/dead_letters_present`, bis sie neu zugestellt oder verworfen sind. |
| AB-7 | Pause (IA-05) ist kein eigener Zustand: `degraded/paused_by_admin`. Der Dispatcher überspringt pausierte Abonnenten im Claim (`WHERE subscriber_id NOT IN paused`), Events und Zustellzeilen entstehen weiter. |
| AB-8 | Halte-/Ausgangsmodus (E-85/E-86) setzt alle `connected` Provider auf `degraded/hold_mode` bzw. `outbound_catch`; Aktionen, die Zustellung auslösen, bleiben erlaubt, wirken aber erst nach Bestätigung des Haltemodus (K-17). |
| AB-9 | Zustandswechsel schreiben per CAS die Statuszeile (§2.6), feuern `course_captain_connector_status_changed` und schreiben Audit `integration_status_changed` (Akteur `system`, ohne Begründung), höchstens **ein** Eintrag je Provider und Zustand in 10 min (Flattern dämpfen; Zwischenwechsel werden gezählt). |
| AB-10 | Multisite: Status gilt je Site (E-07); kein netzweiter Status. |

### 2.4 Datenschutzklasse

Die Klasse beschreibt, **welche Personendaten der Austausch maximal berührt**. Sie wird aus Fähigkeiten und `privacy_tags` (R0-19 §5.1) abgeleitet; angezeigt wird die höchste.

| Klasse | Bedingung | Datenkategorien (R0-17 §2) | Anzeige-Text |
|---|---|---|---|
| `IK-0 technisch` | nur Erkennung, Health-Check ohne Personendaten (alle Provider in R1) | – | „Keine personenbezogenen Daten. Course Captain prüft nur Version und Vertrag.“ |
| `IK-1 Zugang` | `personal_ref`, `access_state`; `grant_source` | DK-06, DK-07 (Referenzen), DK-01 pseudonym | „Pseudonyme Nutzerkennung und Zugangsstatus.“ |
| `IK-2 Lernfortschritt` | `learning_progress` freigegeben | DK-03 | „Lernfortschritt (Abschlüsse, Meilensteine), pseudonym.“ |
| `IK-3 Ergebnis/Nachweis` | `assessment_result` oder `certificate` freigegeben | DK-04, DK-05 | „Prüfungsergebnisse bzw. Zertifikatsstatus, pseudonym.“ |
| `IK-4 Kontakt` | Fähigkeit `recipient_resolver` aktiv (Klardaten zur Versandzeit, R0-19 §4.1, E-41) | DK-01 im Klartext | „Name und E-Mail-Adresse zum Versandzeitpunkt.“ |

Die Klasse erscheint auf der Karte, im Freigabedialog (IA-01/IA-08) und im Diagnosepaket. Eine Erhöhung (neue Freigabe, neue Fähigkeit nach Connector-Update) **MUSS** erneut bestätigt werden; bis dahin `needs_configuration/privacy_not_granted` für die neuen Tags (bestehende Abos laufen weiter).

### 2.5 Retry-Recht und Capabilities

| Recht | Cap (R0-07 §3.1) | Rollen (Default) | Phase |
|---|---|---|---|
| Status lesen (L-Felder) | #22 `ccap_view_system_status` (E-255) | Admin, Kursmanager, `ccap_support` | R1 |
| A-Felder lesen, Secrets-Status, Freigaben ändern, einrichten, deaktivieren, testen | #20 `ccap_manage_integrations` | Admin | R3 |
| Retry einzelner Zustellungen, Pause/Fortsetzen | #20 | Admin | R3 (intern: R1 mit #23) |
| Replay, Dead-Letter verwerfen (Massenaktionen) | #20 **und** #23 `ccap_run_maintenance` | Admin | R3 (intern: R1 mit #23) |
| Interne Abonnenten (R1-38): Retry, Verwerfen | #23 (wie K-16) | Admin | R1 |
| CLI `wp course-captain outbox …` / `integrations …` | Systemakteur; mit `--user` dessen Caps | – | R1 (intern), R3 (Provider) |

Connectoren selbst haben **kein** Retry-Recht über fremde Abonnenten; `DeliveryResult::retry()` wirkt nur auf die eigene Zustellung.

### 2.6 Speicherung, Zugriff, Nebenläufigkeit

- **Statuszeile** je Provider in `ccap_integrations` (bereits für Secrets vorgesehen, E-87): neue Spalten `state`, `state_reason`, `state_since`, `checked_at`, `opt_in_*`, `paused_*`, `last_success_*`, `last_failure_*` (JSON), `row_version` (Delta R0-06). Schreiben nur per CAS (R0-21 §2.1); Erkennungsläufe ohne Änderung schreiben nur `checked_at` (höchstens alle 5 min).
- **Zähler** (`queue`, `dead_letter`, `skipped`, `inbound`) werden beim Lesen aus `ccap_outbox_delivery`/`ccap_inbox_event` über Indizes `(subscriber_id, status, next_attempt_at)` berechnet, gekappt bei 10 000. Sie dürfen bis 60 s alt sein (Kennzahl, R0-21 §4); `state`, `paused` und `actions[]` werden vor jeder Aktion **frisch** gelesen.
- **Zugriffswege** (gleicher Datensatz, gleiche Sichtbarkeitsregeln): PHP `ConnectorRegistry::status()` (R0-19 §5.7), CLI `wp course-captain integrations status [--format=json]` (R0-19 §4.7), REST **neu** `GET /integrations` und `GET /integrations/{connector}` (#22, `no-store, private`; Delta R0-19 §2.3) sowie Systemstatus H-34.
- **Keine Cross-Plugin-Daten:** Kein Feld stammt aus Partner-Tabellen, -Optionen oder -Logs. Partnerseitige Statuswerte nur, wenn der Partner eine dokumentierte öffentliche Status-API anbietet (heute keiner, R0-32 F10); dann als eigener, gekennzeichneter Block `partner_reported` (R3-16).

### 2.7 Beispiel (R3, `degraded`)

```json
{
  "schema_version": "1.0",
  "provider_id": "voucher-captain",
  "label": "Voucher Captain",
  "kind": "partner_connector",
  "partner": { "detected": "active", "version": "1.1.0", "contract_version": null, "required": ">=1.1.0" },
  "connector": { "id": "voucher-captain", "version": "1.0.0", "contract_range": ">=1.0 <2.0",
                 "capabilities": ["grant_source", "code_redeemer", "event_subscriber"], "edition": "pro" },
  "course": { "contract_version": "1.0.0" },
  "state": "degraded",
  "state_reason": "dead_letters_present",
  "state_since": "2026-11-16T08:12:00Z",
  "checked_at": "2026-11-16T09:30:00Z",
  "opt_in": { "enabled": true, "enabled_at": "2026-11-02T10:00:00Z", "enabled_by_role": "administrator" },
  "paused": null,
  "last_success": { "at": "2026-11-16T09:29:41Z", "kind": "delivery" },
  "last_failure": { "at": "2026-11-16T08:12:00Z", "error_class": "validation", "error_code": "ccap_partner_error",
                    "partner_code": "vcap_invalid_request", "error_id": "delivery:48213",
                    "correlation_id": "c0a8012e-7f3b-4a1c-9d2e-5b6a7c8d9e0f", "event_type": "grant.revoked", "retryable": false },
  "queue": { "pending": 3, "dispatching": 0, "retry_wait": 1, "oldest_pending_at": "2026-11-16T09:28:00Z",
             "next_attempt_at": "2026-11-16T09:45:00Z", "stale_leases": 0 },
  "dead_letter": { "count": 2, "oldest_at": "2026-11-16T08:12:00Z", "newest_at": "2026-11-16T08:40:00Z",
                   "by_error_class": { "validation": 2 } },
  "privacy": { "class": "IK-1", "required_tags": ["personal_ref", "access_state"],
               "granted_tags": ["personal_ref", "access_state"], "missing_tags": [] },
  "outbound_mode": "on", "hold_mode": false,
  "ownership_claims": null,
  "actions": [ { "id": "retry", "enabled": true }, { "id": "replay", "enabled": true },
               { "id": "pause", "enabled": true }, { "id": "discard_dead", "enabled": true },
               { "id": "test", "enabled": true } ],
  "help_url": "https://…/docs/integrationen/voucher-captain"
}
```

---

## 3. Oberfläche „Integrationen“

### 3.1 Ort und Navigation

- **Course-Captain-Hauptmenü › Integrationen** (S-19, E-202). In R1 vorhanden, aber nur lesend (E-185, E-254). Systemstatus H-34 zeigt je Provider eine Zeile mit Zustand und Link auf die Karte.
- Sichtbar mit #22 (E-255). Ohne #22 kein Menüpunkt, Direktaufruf `403` (RBAC wie R0-07 S-19).
- Kein gemeinsames Suite-Menü vor R3-16 (E-202); keine Einbindung in fremde Menüs.

### 3.2 Aufbau

1. **Kopf:** Kurztext „Hier sehen Sie, welche Plugins Course Captain erkennt und ob ein Datenaustausch eingerichtet ist. Course Captain installiert oder aktiviert keine anderen Plugins.“ Darunter Banner bei Haltemodus/Ausgangsmodus (H-25/H-26) mit Link zum Systemstatus.
2. **Liste** (Tabelle mit Kartenansicht < 782 px): Name · Zustand (Text + Symbol, nie nur Farbe) · Version gefunden / benötigt · Vertrag · Datenschutzklasse · letzte erfolgreiche Kommunikation · offene Zustellungen · Dead-Letter · letzte Fehler-ID. In R1 nur die ersten fünf Spalten.
3. **Filter:** „Nur erkannte“ (Standard) / „Alle bekannten“ (zeigt auch `not_installed`, E-256).
4. **Detailansicht** je Provider (R3; in R1 nur Abschnitte a und b): a) Version & Vertrag, b) Erkennung & Kompatibilität mit Begründung, c) Freigabe & Datenschutz (Klasse, Event-Klassen nach E-120), d) Zustellung (Queue, nächster Versuch), e) Dead-Letter-Liste, f) letzte Fehler (20), g) Aktionen, h) Objekthistorie aus dem Audit (50).
5. **Dead-Letter-Liste** (e): Spalten `event_id` (gekürzt, kopierbar), `event_type`, `object_type`, Versuche, Fehlerklasse, `ccap_*`-Code, Partnercode, erster/letzter Versuch, `correlation_id`. **Kein** Payload, **kein** `user_ref`; Spalte „Person betroffen: ja/nein“, Link zur Lernenden-Detailansicht nur mit #13. Mehrfachauswahl für IA-04/IA-06, höchstens 1 000 je Aktion.

### 3.3 Zustände und Texte

Texte sind Vorschläge für die Übersetzungsbasis (de_DE, R0-15); Platzhalter in `{}`.

| Zustand (Grund) | Badge | Kurztext | Handlungshinweis | Aktionen R1 | Aktionen R3 |
|---|---|---|---|---|---|
| `not_installed` | „Nicht installiert“ | „{Partner} ist auf dieser Website nicht installiert.“ | „Course Captain funktioniert auch ohne dieses Plugin.“ + Hilfe-Link | – | – |
| `inactive` | „Deaktiviert“ | „{Partner} ist installiert, aber nicht aktiv.“ | „Bestehende Zugänge bleiben erhalten. Zum Aktivieren nutzen Sie die Plugin-Verwaltung.“ | – | Deaktivieren der Verbindung (falls freigegeben) |
| `active_incompatible` (`version_too_low`) | „Nicht kompatibel“ | „Gefunden: {Partner} {gefunden}. Benötigt: {benötigt}.“ | „Aktualisieren Sie {Partner}. Bis dahin tauscht Course Captain keine Daten aus.“ | – | Test (nach Update) |
| `active_incompatible` (`contract_major_mismatch`, `connector_contract_mismatch`) | „Nicht kompatibel“ | „Vertragsversion {gefunden} passt nicht zu {benötigt}.“ | „Der Austausch ist angehalten. Offene Ereignisse bleiben erhalten.“ | – | Test |
| `active_incompatible` (`no_published_contract`) | „Kein Vertrag“ | „{Partner} ist aktiv. Für dieses Plugin gibt es noch keinen abgestimmten Austauschvertrag.“ | „Es findet kein Datenaustausch statt.“ | – | – |
| `active_compatible` (`connector_missing`) | „Erkannt“ | „{Partner} {Version} ist aktiv und kompatibel.“ | „Für einen Datenaustausch ist ein Connector nötig. Derzeit ist keiner installiert.“ (kein Kauf-/Upgrade-Link, E-100) | – | – |
| `active_compatible` (`not_enabled`) | „Bereit zur Einrichtung“ | „{Partner} ist kompatibel. Es werden noch keine Daten ausgetauscht.“ | „Prüfen Sie Datenschutzklasse und Ereignisse, bevor Sie die Verbindung einrichten.“ | – | Einrichten (IA-01) |
| `active_compatible` (`no_exchange_planned`) | „Erkannt“ | „{Partner} ist aktiv. Course Captain sendet keine Daten an dieses Plugin.“ | Hilfe-Link zur Zuständigkeit (z. B. E-37) | – | – |
| `needs_configuration` | „Einrichtung unvollständig“ | „Es fehlt: {fehlende Angaben}.“ | „Ergänzen Sie die Angaben und testen Sie die Verbindung.“ | – | Einstellungen, Test, Deaktivieren |
| `connected` | „Verbunden“ | „Letzte erfolgreiche Übertragung: {Zeit}.“ | – | – | Test, Pause, Deaktivieren, Replay |
| `degraded` (`retries_pending`/`queue_stale`) | „Verzögert“ | „{n} Ereignisse warten. Nächster Versuch: {Zeit}.“ | „Course Captain wiederholt automatisch. Eingreifen ist nur nötig, wenn das länger anhält.“ | – | Retry, Pause, Test |
| `degraded` (`dead_letters_present`) | „Teilweise fehlgeschlagen“ | „{n} Ereignisse konnten endgültig nicht übertragen werden (Fehler-ID {id}).“ | „Beheben Sie die Ursache, dann senden Sie erneut oder verwerfen die Ereignisse.“ | – | Retry, Verwerfen, Replay, Test |
| `degraded` (`paused_by_admin`) | „Angehalten“ | „Angehalten seit {Zeit} von {Rolle}. {n} Ereignisse warten.“ | „Ereignisse älter als {Frist} können verloren gehen (E-258).“ | – | Fortsetzen |
| `degraded` (`hold_mode`/`outbound_catch`) | „Zurückgehalten“ | „Der Ausgang ist für die ganze Website angehalten.“ | Link zum Systemstatus (K-17) | – | – |
| `degraded` (`license_inactive`) | „Eingeschränkt“ | „Die Lizenz für diesen Connector ist nicht aktiv. Bestehende Zugänge bleiben erhalten.“ | Link „Pro-Lizenz“ (nur wenn Pro aktiv) | – | – |
| `failed` (`auth_failed`) | „Fehler“ | „Anmeldung bei {Partner} fehlgeschlagen (Fehler-ID {id}).“ | „Prüfen oder erneuern Sie den Schlüssel und testen Sie danach.“ | – | Einstellungen, Test, Pause, Deaktivieren |
| `failed` (übrige) | „Fehler“ | „Übertragung gestoppt: {Fehlerklasse} (Fehler-ID {id}, Vorgang {correlation_id}).“ | „Geben Sie dem Support die Fehler-ID oder ein Diagnosepaket dieser Integration.“ | – | Test, Retry, Verwerfen, Diagnose |

**Allgemein:** Fehlerklassen erscheinen übersetzt („Zeitüberschreitung“, „Netzwerkfehler“, „Partner meldet Fehler“ …), der Code steht klein daneben. Zeiten relativ + absolut mit Zeitzonenkennung (E-124). Live-Aktualisierung per Polling höchstens alle 30 s, angekündigt über `aria-live="polite"` nur bei Zustandswechsel (R0-15).

### 3.4 Alarmierung (R3-10)

| Kanal | Auslöser | Inhalt | Phase |
|---|---|---|---|
| Admin-Hinweis (dismissible je Zustand) | Wechsel nach `failed`; `dead_letters_present` > 24 h | Provider, Zustand, Fehler-ID, Link | R3 |
| Site Health / H-34 | `degraded` = warn, `failed` = warn (R0-13) | wie Liste | R1 (Erkennung), R3 |
| Monitor `GET /health` | `failed` als Einzelprüfung (nur mit Schlüssel/#22), **kein** 503-Grund | `id`, `status` | R3 |
| Mail an Site-Admin | E-257 | nur Provider, Zustand, Fehler-ID, Link; **keine** Personendaten, keine Payloads | R3 |
| Action `course_captain_connector_status_changed` | jeder Wechsel | für eigene Alarmierung des Betreibers | R1 |

---

## 4. Support-Aktionen

### 4.1 Katalog

Gemeinsamer Ablauf wie R0-13 §3 (Cap + Meta-Cap, Nonce je Aktion und Provider `ccap_integration_<aktion>_<provider>`, Bestätigung mit Wirkung in Klartext und Anzahl, Begründung, eine TX je Schritt, Audit synchron in derselben TX, fail-closed).

| Nr. | Aktion | Vorbedingung (Zustand) | Cap | Begründung | Wirkung | Obergrenze | Audit | Phase |
|---|---|---|---|---|---|---|---|---|
| IA-01 | **Verbindung einrichten** (Opt-in) | `active_compatible/not_enabled` | #20 | nein (Dialog zeigt Datenschutzklasse, Bestätigung Pflicht) | `opt_in.enabled = true`; ab jetzt Zustellzeilen für neue Events; optional Backfill = IA-03 | – | `integration_enabled` | R3 |
| IA-02 | **Verbindung testen** | jeder Zustand ab `active_compatible` mit Connector | #20 | nein | `healthCheck()` ohne Personendaten, Timeout 10 s; Erfolg kann `failed → connected` bewirken (AB-6) | 10 je Provider und Stunde (`ccap_rate_limited`, Bucket `integration_action`) | `integration_tested` (Ergebnis, Fehlerklasse) | R3 |
| IA-03 | **Replay** (erneut zustellen ab Zeitpunkt/Typ) | `connected`/`degraded`, nicht `paused`, Lease frei | #20 + #23 | ja | für Events im Log ab `--since` (max. Retention, E-119) und passenden Typen: fehlende Zustellzeilen anlegen (`INSERT IGNORE`, UNIQUE `(event_uuid, subscriber_id)`), vorhandene `delivered/skipped/dead` → `pending`; **gleiche `event_id`**; Freigaben (E-120) neu geprüft | 10 000 Zustellungen je Lauf, ein Lauf je Provider gleichzeitig; bereits zugestellte nur mit ausdrücklichem Flag (Häkchen „Auch erfolgreich zugestellte erneut senden“ bzw. CLI `--include-delivered`), Pflichtbegründung und Audit (**E-311**, D34-7) | `deliveries_replayed` (Zeitraum, Typen, Anzahl neu/zurückgesetzt, Lauf-`correlation_id`) | R3 |
| IA-04 | **Retry** (Einzelne/ausgewählte Zustellungen) | Zustellung `failed` oder `dead`; Provider nicht `active_incompatible` | #20 | nein | Status → `pending`, `next_attempt_at = jetzt`, neuer Backoff-Zyklus (8 Versuche) | 5 manuelle Retries je Zustellung (`manual_retries`), danach nur Verwerfen; 1 000 Zeilen je Aktion | `delivery_retried` (Anzahl, IDs-Hash) | R3; intern R1 (#23) |
| IA-05 | **Pause / Fortsetzen** | Pause: `connected`/`degraded`/`failed`; Fortsetzen: `paused` | #20 | Pause: ja; Fortsetzen: nein | Pause: Dispatcher überspringt Abonnenten (AB-7); laufende `dispatching` dürfen enden; Fortsetzen: normaler Claim | Pause ohne Ende; Erinnerung nach 7 Tagen (Admin-Hinweis) | `integration_paused` / `integration_resumed` | R3 |
| IA-06 | **Dead-Letter verwerfen** | Zustellung `dead` (einzeln, Auswahl oder „alle mit Fehlerklasse X“) | #20 + #23 | ja | `dead → skipped`, `skip_reason = discarded`; Event bleibt im Log; Replay bleibt bis Retention-Ende möglich | 1 000 je Aktion; „alle“ nur bis 10 000 mit erneuter Bestätigung | `dead_letter_discarded` (Anzahl, Fehlerklassen) | R3; intern R1 (#23, = K-16) |
| IA-07 | **Verbindung deaktivieren** | `opt_in.enabled` | #20 | ja | keine neuen Zustellzeilen; offene `pending` → `skipped` (`skip_reason = disabled`); bestehende Grants der Quelle bleiben (R0-19 §5.7, R0-32 V4) | – | `integration_disabled` | R3 |
| IA-08 | **Event-Klassen freigeben/entziehen** (E-120) | Connector mit `event_subscriber` | #20 | Entzug: nein; Freigabe: Bestätigung | ändert `granted_tags`; neue Zustellzeilen nur für freigegebene Tags; bereits erzeugte bleiben | – | `integration_event_classes_changed` (vorher/nachher) | R3 |
| IA-09 | **Secret rotieren** | Connector mit Secret | #20 | nein | zwei gültige Secrets während Übergang (R0-19 §5.7) | – | `integration_secret_rotated` (vorhanden) | R3 |

**Nicht vorgesehen:** Zustellungen oder Events löschen, Payload bearbeiten, Events neu erzeugen, `event_id` ändern, Partner-Plugins installieren/aktivieren/konfigurieren, Partnerdaten lesen.

### 4.2 Idempotenz und Nebenläufigkeit

| Nr. | Regel |
|---|---|
| ID-1 | Jede Aktion trägt einen `request_key` (UUID aus dem Formular bzw. `--request-key` in der CLI) in `ccap_request_idempotency` mit Scope `integration_action:<provider>`; gleicher Key + gleicher Inhalt → gleiches Ergebnis ohne zweite Wirkung; gleicher Key + anderer Inhalt → `409 ccap_idempotency_conflict` (R0-19 §2.1). |
| ID-2 | Zusätzlich natürliche Idempotenz: Retry auf `pending` → `unchanged`; Pause auf pausiert → `unchanged`; Verwerfen auf `skipped` → `unchanged` (Ergebniscodes R0-19 §3.1). |
| ID-3 | Zeilenwechsel per CAS: `UPDATE ccap_outbox_delivery SET status='pending', … WHERE id IN (…) AND subscriber_id=? AND status IN ('failed','dead')` bzw. `… status='dead'` für IA-06; gezählt wird `affected_rows`, nicht die Auswahl. Eine Zeile, die der Dispatcher inzwischen geclaimt hat (`dispatching`), wird nicht verändert und im Ergebnis als „übersprungen“ gemeldet. |
| ID-4 | IA-03 und IA-06 (Massen) laufen als Lease `replay:<provider>` bzw. `discard:<provider>` (Rang M0, R0-21 §2.4), TTL 900 s, Schritte zu 500 Zeilen in eigener TX mit Fencing; belegt → `409 ccap_resource_busy` (`reason: lock`). Abbruch und Wiederaufnahme über Cursor `ccap_outbox_event.id`. |
| ID-5 | Replay erzeugt keine neuen Events und keine neuen `event_id`s; Consumer deduplizieren über `event_id` (R0-19 §5.4 Nr. 5). Die Lauf-`correlation_id` steht im Audit und im `DeliveryContext`, nicht im Envelope. |
| ID-6 | Keine externen Aufrufe in TX oder unter Mutex (R0-21 R-6); IA-02 ruft `healthCheck()` außerhalb der TX und schreibt das Ergebnis danach per CAS. |
| ID-7 | Aktion nicht zulässig im aktuellen Zustand (z. B. Retry bei `active_incompatible`, Replay während Pause) → `409 ccap_integration_state_invalid` (neu, Muster `ccap_import_state_invalid`; Delta R0-19 §3.2) mit `details.state`, `details.state_reason`. |

### 4.3 Audit

Neue Aktionen im Bereich **settings/integrations** (R0-13 §4.1, bisher 4): `integration_enabled`, `integration_disabled`, `integration_tested`, `integration_paused`, `integration_resumed`, `integration_event_classes_changed`, `integration_status_changed`; im Bereich **ops**: `delivery_retried`, `deliveries_replayed`, `dead_letter_discarded`. Summe +10 (88 → 98, Delta R0-13).

- Felder nach R0-13 §4.2: `actor_type` (`user`, `system:cli`, `system:cron` für AB-9), `object_type = integration`, `object_id = provider_id`, `reason_code` (Katalog: `partner_outage`, `partner_fixed`, `data_error`, `privacy_concern`, `maintenance`, `other`), `reason`, `correlation_id`, `request_id`.
- `context` nur Allowlist: Zähler, Fehlerklassen, Zeitraum, Typenliste, Zustände vorher/nachher, SHA-256 über die sortierte Liste betroffener Zustellungs-IDs. **Keine** `event_id`-Listen über 20 Einträge, keine Payloads, keine `user_ref`.
- Begründungspflicht (B): IA-03, IA-05 (Pause), IA-06, IA-07.

### 4.4 Schutzregeln

| Nr. | Regel |
|---|---|
| SR-1 | R1 zeigt keine Aktionsknöpfe auf „Integrationen“; Aktionsrouten für Provider sind in R1 **nicht registriert** (kein toter Endpunkt, Antwort `404 ccap_feature_unavailable`). |
| SR-2 | Replay und Retry prüfen Freigaben (E-120) und Opt-in **zum Zeitpunkt der Zustellung** erneut; entzogene Tags → `skipped/privacy_not_granted`. |
| SR-3 | Im Haltemodus (E-85) sind Aktionen erlaubt, aber Zustellungen bleiben `pending`; der Bestätigungsdialog sagt das. |
| SR-4 | Aktionen auf einen Provider eines anderen Abonnenten-Namens sind unmöglich: jede Aktion filtert `subscriber_id = provider_id`; fremde Zustellungs-IDs in der Auswahl → `404 ccap_not_found` für die ganze Aktion (kein Existenz-Orakel). |
| SR-5 | Pausiert der Betreiber länger als die Event-Retention, gilt E-258 (offene Zustellungen höchstens 30 Tage, danach Dead-Letter mit Grund `retention`); die Karte zeigt die Restfrist der ältesten wartenden Zustellung. |
| SR-6 | Kein Lernender wird über Integrationsaktionen benachrichtigt (wie Reconcile, R0-13 K-14); Folgemails entstehen nur aus dem fachlichen Ablauf beim Partner bzw. Abonnenten. |

---

## 5. Diagnose-Export je Integration

Erweiterung des Diagnosepakets aus R0-13 §2.2 (gleiche Erzeugung, Vorschau, Einmal-Link, Audit `diagnostics_exported`). Neue Option „Nur Integration {provider}“ (S-19-Detail und `wp course-captain diagnostics --integration=<id>`); dann enthält das Paket `manifest`, `versions`, `environment`, `health` (nur H-25, H-26, H-28, H-34) und **einen** Eintrag `integrations[]`. Ohne Option enthält das Gesamtpaket alle Provider.

| Feld in `integrations[]` | Inhalt | Redaktion |
|---|---|---|
| `provider_id`, `kind`, `label` | wie §2.1 | – |
| `partner`, `connector`, `course` | Versionen, Vertragsbereiche, Fähigkeiten, Edition | – |
| `state`, `state_reason`, `state_since`, `checked_at`, `paused`, `opt_in.enabled` | wie §2.1 | Rollen statt Personen |
| `privacy` | Klasse, `required/granted/missing_tags` | – |
| `queue`, `dead_letter`, `skipped`, `inbound` | Zähler | – |
| `last_success`, `last_failure` | wie §2.1 | `partner_code` nach Muster, sonst `<redacted>` |
| `failures[]` | letzte 50 Zustellungen `failed`/`dead` im Zeitraum: `delivery_id`, `event_id`, `event_type`, `schema_version`, `object_type`, `object_id` **als HMAC**, `attempts`, `manual_retries`, `error_class`, `error_code`, `partner_code`, `first_attempt_at`, `last_attempt_at`, `correlation_id` | `last_error`-Freitext nur nach Scrubber (R0-13 §2.2) und auf 200 Zeichen gekürzt; `user_ref` entfällt, nur `has_person: bool` |
| `status_history[]` | letzte 20 Zustandswechsel (Zeit, von, nach, Grund) | – |
| `actions_history[]` | letzte 20 Integrationsaktionen aus dem Audit: Aktion, Zeit, `actor_type`, Rolle, `reason_code`, Zähler | **kein** Begründungs-Freitext |
| `config` | Settings des Connectors mit Privacy-Klasse `public`/`internal`; `secret` → `<gesetzt>/<leer>/<konstante>`; `personal` → `<gesetzt>` | R0-27 SR-2 |
| `endpoint` (nur Webhook/Cross-Site) | Schema (`https` ja/nein), Host **als HMAC**, Port-Standard ja/nein, Signaturverfahren, Anzahl gültiger Secrets (1/2) | nie Pfad, Query, Host im Klartext |

**Nie im Integrationsteil:** Payloads, Envelopes, `user_ref`/User-IDs im Klartext, E-Mail, Namen, Antwort-Bodies des Partners, HTTP-Header, Secrets, Signaturen, Token, Lizenzschlüssel, Bestell-/Gutschein-Codes, absolute Pfade.

**Größe:** Integrationsteil höchstens 1 MB je Provider; `failures[]` wird zuerst gekürzt. Pseudonyme mit dem `diag_salt` des Pakets (R0-13 §2.2), also nur innerhalb des Pakets vergleichbar. Zum gemeinsamen Nachverfolgen nennt der Betreiber `error_id` oder `correlation_id` (zufällig, ohne Personenbezug).

---

## 6. Testfälle INT-xxx

`P` = positiv, `N` = negativ. Negativtests erwarten wie R0-07 §5.2: 403/404 ohne Datenleck, keine Fachwirkung, kein Audit außer gedrosselter Ablehnung. Ziel-Tasks: **R1-38** (R1 lesend, interne Zustellungen, Diagnose), **R3-10** (Aktionen, Monitoring, Alarmierung), **R3-16** (Providerstatus-Anzeige, Rechte, keine Cross-Plugin-Zugriffe).

| ID | Art | Fall | Erwartung | Ziel-Task | Bezug |
|---|---|---|---|---|---|
| INT-001 | P | R1, kein Partner installiert, Filter „Nur erkannte“ | leere Liste mit Erklärtext; Filter „Alle bekannten“ zeigt Katalog mit `not_installed` | R1-38 | §1.3, E-256 |
| INT-002 | P | Voucher Captain 1.1.0 aktiv, kein Connector | `active_compatible/connector_missing`, Version 1.1.0, benötigt `>=1.1.0`, Klasse IK-0, keine Aktionsknöpfe, kein Upgrade-Link | R1-38 | §3.3, E-100 |
| INT-003 | N | Voucher Captain 1.0.x aktiv | `active_incompatible/version_too_low`, Text „Gefunden … Benötigt …“; kein Fatal | R1-38 | R0-19 CT-08 |
| INT-004 | N | Plugin mit Slug `voucher-captain` aktiv, aber ohne `VCAP_VERSION`/`vcap()` | **nicht** `active_compatible`; `active_incompatible/no_published_contract` | R1-38 | Kap. 33 „kein Slug allein“ |
| INT-005 | P | Partner installiert, deaktiviert | `inactive/plugin_inactive`; bestehende Grants unverändert; Free-Lernen läuft | R1-38 | R0-19 CT-09 |
| INT-006 | P | Form/Workflow/Review-Plugin-Attrappe aktiv | `active_incompatible/no_published_contract`, Text „kein abgestimmter Austauschvertrag“ | R1-38 | R0-32 F10 |
| INT-007 | N | Erkennungslauf | keine Schreibzugriffe auf Partner-Optionen/-Tabellen (Query-Log), keine Partner-Hooks ausgelöst | R1-38, R3-16 | AB-1 |
| INT-008 | N | R1: POST auf eine Provider-Aktionsroute bzw. `admin-post` mit gültiger Nonce | `404 ccap_feature_unavailable`; keine Wirkung | R1-38 | SR-1, E-185 |
| INT-009 | P | `ccap_support` öffnet „Integrationen“ | lesbar (L-Felder); A-Felder (`secrets`, `skipped`) fehlen im HTML und in `GET /integrations` | R1-38 | §2.5, E-255 |
| INT-010 | N | Editor/Shop-Manager ohne `ccap_*`-Caps ruft Seite und `GET /integrations` | 403 bzw. kein Menüpunkt | R1-38 | R0-07 S-19 |
| INT-011 | P | Systemstatus H-34 | eine Zeile je erkanntem Provider; `degraded`/`failed` = warn | R1-38 | R0-13 H-34 |
| INT-012 | P | Interner Abonnent `notification`: Zustellung `dead`, Admin (#23) „erneut ausführen“ | Status `pending`, gleiche `event_id`, genau eine Mail nach Erfolg (NTF-008), Audit `delivery_retried` | R1-38 | IA-04, R0-27 NTF-008 |
| INT-013 | N | Kursmanager (ohne #23) versucht Retry/Verwerfen einer internen Zustellung | 403, keine Zustandsänderung | R1-38 | §2.5 |
| INT-014 | P | Interne Dead-Letter verwerfen mit Begründung | `skipped/discarded`, Event bleibt im Log, Audit `dead_letter_discarded` mit Zähler | R1-38 | IA-06, K-16 |
| INT-015 | N | Verwerfen ohne Begründung bzw. Freitext < 10 Zeichen | Feldfehler, keine Wirkung | R1-38, R3-10 | R0-13 §3 Schritt 3 |
| INT-016 | P | Doppelklick „Retry“ (gleicher `request_key`) | eine Wirkung, zweite Antwort identisch, ein Audit-Eintrag | R1-38, R3-10 | ID-1 |
| INT-017 | N | gleicher `request_key`, andere Auswahl | `409 ccap_idempotency_conflict` | R1-38, R3-10 | ID-1 |
| INT-018 | P | Diagnosepaket „Nur Integration X“ | genau ein Eintrag `integrations[]`, Felder nach §5, Vorschau zeigt Redaktion | R1-38 | §5 |
| INT-019 | N | Diagnose: bekannte Canaries (E-Mail, Secret, Webhook-URL mit Pfad/Query, Bearer-Token, `user:42`, Gutscheincode, Partner-Antworttext) in `last_error`, Settings und Endpunkt | 0 Treffer im Paket; Host nur als HMAC (analog SUP-017) | R1-38, R3-10 | §5 |
| INT-020 | P | `GET /integrations` | Antwort entspricht Schema `IntegrationStatus` v1, `Cache-Control: no-store, private` | R1-38 | §2.6 |
| INT-021 | P | Connector registriert, `contractRange` passt, Opt-in fehlt | `active_compatible/not_enabled`; keine Zustellzeilen für neue Events | R3-16 | R0-19 §6 |
| INT-022 | P | IA-01 Einrichten | Dialog zeigt Datenschutzklasse; danach Zustellzeilen; Audit `integration_enabled` | R3-10 | IA-01, §2.4 |
| INT-023 | N | Connector-Update fordert zusätzlich `assessment_result` | `needs_configuration/privacy_not_granted` für neue Tags, bestehende Abos laufen weiter; keine Zustellung mit nicht freigegebenem Tag | R3-16 | §2.4, E-120 |
| INT-024 | P | Zustellung scheitert mit `timeout` | `degraded/retries_pending`, `last_failure` mit Klasse, Code, Fehler-ID, Correlation-ID; nächster Versuch nach Backoff | R3-10 | AB-3 |
| INT-025 | P | 8 Fehlversuche | Zustellung `dead`, Provider `degraded/dead_letters_present`, Action `course_captain_outbox_dead_lettered` | R3-10 | R0-19 §5.4 |
| INT-026 | P | 3 neue `dead` in 60 min (2 neue: weiter `degraded/dead_letters_present`) | `failed/dead_letter_threshold`, Dispatcher stoppt für diesen Abonnenten, Admin-Hinweis | R3-10 | AB-4, D34-4 |
| INT-027 | P | `auth_failed` bei Zustellung | sofort `failed/auth_failed`; Text mit Hinweis auf Schlüssel | R3-10 | AB-5 |
| INT-028 | P | `failed`, IA-02 Test erfolgreich, 2 Dead-Letter offen | `degraded/dead_letters_present`, Dispatcher läuft wieder | R3-10 | AB-6 |
| INT-029 | N | IA-02 öfter als 10× je Stunde | `429 ccap_rate_limited` (`bucket: integration_action`) | R3-10 | IA-02 |
| INT-030 | P | IA-04 Retry eines `dead` | `pending`, gleiche `event_id`, Partner erhält Event, Consumer-Wirkung einmal | R3-10 | IA-04, ID-5 |
| INT-031 | N | 6. manueller Retry derselben Zustellung | abgelehnt (`ccap_integration_state_invalid`, `reason` manual_retry_limit), nur Verwerfen angeboten | R3-10 | IA-04 |
| INT-032 | N | Retry während Dispatcher die Zeile hält (`dispatching`) | Zeile unverändert, im Ergebnis „übersprungen“ | R3-10 | ID-3 |
| INT-033 | P | IA-03 Replay ab Datum nach Opt-in (Backfill) | fehlende Zustellzeilen angelegt, keine Duplikate (UNIQUE), keine neuen `event_id`s | R3-10 | IA-03 |
| INT-034 | N | Replay mit Datum älter als Retention | auf Retention-Grenze begrenzt, Hinweis in Ergebnis | R3-10 | IA-03, E-119 |
| INT-035 | N | zwei Replays desselben Providers parallel | zweiter `409 ccap_resource_busy` (`reason: lock`) | R3-10 | ID-4 |
| INT-036 | P | Replay-Worker stirbt nach Schritt 3, Lease läuft ab | Übernahme mit `fence + 1`, Fortsetzung ab Cursor, keine doppelten Zeilen; Zombie schreibt nichts | R3-10 | ID-4, R0-21 CON-012 |
| INT-037 | N | Replay schließt Events mit entzogenem Tag ein | diese Zustellungen `skipped/privacy_not_granted` | R3-10 | SR-2 |
| INT-038 | P | IA-05 Pause | `degraded/paused_by_admin`; Events und Zustellzeilen entstehen weiter, nichts wird zugestellt; Fortsetzen stellt in `object_version`-Reihenfolge zu | R3-10 | AB-7 |
| INT-039 | N | Pause ohne Begründung; Replay während Pause | Feldfehler bzw. `409 ccap_integration_state_invalid` | R3-10 | IA-05, ID-7 |
| INT-040 | P | Pause > 7 Tage | Admin-Erinnerung; Karte zeigt Restfrist der ältesten wartenden Zustellung | R3-10 | SR-5, E-258 |
| INT-041 | P | IA-06 Verwerfen „alle mit Fehlerklasse `validation`“ (1 200 Zeilen) | Bestätigung mit Anzahl; 1 200 → `skipped`, Replay danach möglich | R3-10 | IA-06 |
| INT-042 | N | Auswahl enthält Zustellungs-ID eines anderen Providers | ganze Aktion `404 ccap_not_found`, keine Teilwirkung | R3-10, R3-16 | SR-4 |
| INT-043 | P | IA-07 Deaktivieren | offene `pending` → `skipped/disabled`, Grants der Quelle unverändert, Zustand `active_compatible/not_enabled` | R3-10 | IA-07, R0-32 V4 |
| INT-044 | P | Haltemodus aktiv (E-85) | alle verbundenen Provider `degraded/hold_mode`; Retry erlaubt, Zustellung erst nach K-17 | R3-10 | AB-8, SR-3 |
| INT-045 | P | Zustand flattert 30× in 10 min zwischen `connected` und `degraded` | höchstens ein Audit `integration_status_changed` je Zustand und 10 min, Zähler im Kontext | R3-10 | AB-9 |
| INT-046 | P | Alarmierung bei Wechsel nach `failed` | Admin-Hinweis; Mail nach E-257 gebündelt höchstens 1 je 24 h (E-257), ohne Personendaten | R3-10 | §3.4 |
| INT-047 | N | `ccap_support` versucht IA-02…IA-09 | 403; Knöpfe fehlen | R3-16 | §2.5 |
| INT-048 | N | Admin mit #20, ohne #23, versucht Replay/Verwerfen | 403; Retry und Pause erlaubt | R3-10 | §2.5 |
| INT-049 | P | Dead-Letter-Liste | kein Payload, kein `user_ref`; „Person betroffen“ ja/nein; Link zur Person nur mit #13 | R3-16 | §3.2 |
| INT-050 | N | Statusseite rendern | keine Abfrage auf Partner-Tabellen oder -Optionen (Query-Log), `partner_reported` fehlt ohne dokumentierte Partner-API | R3-16 | §2.6 |
| INT-051 | P | Partner-Update auf inkompatiblen Major | `active_incompatible/contract_major_mismatch`, nur betroffene Zustellungen pausiert, andere Provider unberührt | R3-16 | R0-19 §6, R0-32 W15 |
| INT-052 | P | Statusdatensatz über PHP, CLI `--format=json`, REST und H-34 | gleiche Werte und gleiche Sichtbarkeitsregeln auf allen Wegen | R3-16 | §2.6 |

Summe: 52 (R1-38: 20, R3-10: 29 inkl. Doppelzuordnung, R3-16: 9 inkl. Doppelzuordnung).

---

## 7. Entscheidungsbedarf (beschlossen)

| Nr. | Frage | Beschluss |
|---|---|---|
| **D34-1** | Ort der lesenden R1-Anzeige | **E-254:** Unterseite „Integrationen“ schon in R1, nur lesend; H-34 als Zusammenfassung (Option a) |
| **D34-2** | Lese-Cap für „Integrationen“ | **E-255:** auch `ccap_support` und Kursmanager lesend; umgesetzt über #22, Aktionen nur #20 (Option a) |
| **D34-3** | Partner ohne veröffentlichten Vertrag, nicht installierte Partner | **E-256:** erkannte Partner anzeigen mit Hinweis „kein Vertrag verfügbar“; nicht installierte nicht bewerben (Option a) |
| **D34-4** | Schwelle für `failed` | **E-311:** `failed` nach 3 Dead-Lettern innerhalb 1 h, nicht beim ersten (AB-4, INT-026) |
| **D34-5** | Alarm-Mail an Admins bei `failed` (R3) | **E-257:** ja, gebündelt höchstens einmal je 24 h |
| **D34-6** | Wartende Zustellungen und Retention | **E-258:** offene Zustellungen höchstens 30 Tage, danach Dead-Letter mit Grund `retention` (abweichend von Option a, 90 Tage) |
| **D34-7** | Replay bereits erfolgreich zugestellter Events | **E-311:** nur mit ausdrücklichem Flag, Begründung und Audit (IA-03, R0-19 §5.4 Nr. 7) |
| **D34-8** | Ownership-Claim durch Partner | **E-259:** erst in R3, nur mit sichtbarem Hinweis im Admin und Abschaltmöglichkeit (Option a) |

### Offene Punkte nach Einarbeitung

1. **D34-4 und D34-7** stehen nicht in `ENTSCHEIDUNGEN.md`; beide betreffen nur R3 (R3-10). Die Spezifikation folgt bis zu einem Eintrag der damaligen Empfehlung a); vor R3-10 ins Register aufnehmen.
2. **E-258 und das Feldschema:** E-258 verlangt nach 30 Tagen einen Dead-Letter mit Grund `retention`; §2.1 führt dafür bisher `skipped.by_reason.retention_expired`. Status (`dead` statt `skipped`) und Grundname sind in R0-19 §5.4/R0-06 anzugleichen.
3. **Registry in R1:** Nach E-38 gibt es in R1 keine `ConnectorRegistry`; die Zugriffswege in §2.6 (PHP `ConnectorRegistry::status()`) gelten ab Pro. Ob R1 eine schmale interne Status-API für den Partnerkatalog bereitstellt, ist in R0-19 festzulegen.
4. **E-39b** setzt eine Änderung am Workflow-Captain-Konzept voraus; bis dahin bleibt `workflow-captain` ohne Vertrag.

**Weitere Abhängigkeiten (beschlossen):** 8 Zustände → R0-19 §6; Freigabe je Event-Klasse → E-120; Halte-/Ausgangsmodus → E-85/E-86/E-88; Secrets → E-87; Connector-Ort/Edition → E-40/E-53; Hersteller nur über Diagnosepaket → E-142; Fehler-/Debug-Fristen → E-143; kein Upselling → E-100; kanonisches Zugangs-Event `enrollment.created` → bereits R0-19 §5.3 (R0-32 W9); Empfänger-Resolver → E-41, R0-19 §4.1.

---

## 8. Deltas zu anderen Dokumenten (mit deren Freigabe einzuarbeiten)

| Dokument | Delta |
|---|---|
| R0-06 | `ccap_integrations` um Statusspalten und `row_version` (§2.6); `ccap_outbox_delivery` um `manual_retries`, `skip_reason` (`discarded`, `disabled`, `hold_mode`, `privacy_not_granted`), `discarded_at`; Ablauf nach 30 Tagen als `dead` mit `last_error_class = retention` (E-258) – in R0-06 §4.6 eingearbeitet; Index `(subscriber_id, status, next_attempt_at)`. |
| R0-07 | S-19 lesend mit #22 (E-255); neue Nonce-Aktionen `ccap_integration_{enable,disable,test,replay,retry,pause,resume,discard,event_classes}`; Massenaktionen #20 + #23. |
| R0-13 | 10 neue Audit-Aktionen (§4.3, 88 → 98); H-34 mit Grund und Link; Diagnosepaket-Option „Nur Integration“ und Abschnitt `integrations[]` (§5); RC-5 „zurück in `queued`“ → `pending` (Statusnamen R0-19 §5.4); K-16 gilt für interne Outbox-Abonnenten wie IA-04/IA-06. |
| R0-19 | §2.3 neue Routen `GET /integrations`, `GET /integrations/{connector}` (R1, #22) und R3-Aktionsrouten `POST /integrations/{connector}/{test,replay,retry,pause,resume,discard}` (**P**-Idempotenz); §3.2 neuer Code `ccap_integration_state_invalid` (409), `ccap_rate_limited` Bucket `integration_action`; §4.7 CLI `integrations pause\|resume\|enable\|disable`, `outbox discard <ids> --reason`, `diagnostics --integration`; §6 Ableitungsregeln AB-2…AB-9 und `failed`-Schwelle nach E-311 (in R0-19 §6 eingearbeitet); `state_reason`-Enum §2.2. |
| R0-27 | Nach E-257: Mailtyp `admin_integration_failed` (Klasse `informational`, Empfänger Site-Admin) in §3.2; nach E-258: Frist für offene Zustellungen 30 Tage (Default und Höchstwert; Schlüssel `privacy.retention.outbox_open_days`). |
| R0-12 | OPS-06 „Integrationsstatus-Anzeige fehlt“ → INT-001…INT-020; R3-10/R3-16 Testzuordnung INT-021…INT-052. |
| R0-24 | Zeilen 767 (SUITE-004) und 859 (FLOW-005, TODO-Stand nach Einfügung von R1-45/R1-46) auf R0-34 verweisen; R1-38 DoR „R0-34 fehlt“ → vorhanden (in R0-24 eingearbeitet). |
| konzept.md | Kap. 30.3 „Integrationszustände“ verweist auf R0-19 §6 und R0-34 §3.3; Kap. 33 „Diagnose, Hilfe und Datenschutzinformation“ → §2.4, §5. |

---

## 9. Nachweis

- Gelesen (Planung): `TODO-WETTBEWERBSANALYSE.md` (R0-34, R1-38, R3-10, R3-16, FLOW-005), `konzept.md` Kap. 30.3, 33; `docs/r0/ENTSCHEIDUNGEN.md` (Beschlussprotokoll E-01…E-20, Empfehlungen bis E-224); `R0-19` §1.1, §1.6, §2.3, §3, §4.2, §4.7, §4.8, §5.1–§5.8, §6–§9; `R0-21` §0, §2; `R0-27` §1.1, §1.2, §1.6, §2.5, §3; `R0-13` §0–§4, §6–§8; `R0-17` §2; `R0-32` §3–§9; `R0-36` §1, §2.1, §5.1, §7; `R0-07` §3.1, S-19/S-22; `R0-12` OPS-06; `R0-24` Zeilen 115, 162, 438.
- Partnerproben stützen sich ausschließlich auf die in R0-36 §1 mit Commit belegten Angaben; Pass-Captain-Probe und Form/Workflow/Review sind bewusst offen (E-256).
- Kein Code, keine Tests ausführbar (keine Implementierung). Beschlüsse vom 16.11.2026 eingearbeitet; Abhaken von R0-34 nach Review und Übernahme der Deltas (§8).
