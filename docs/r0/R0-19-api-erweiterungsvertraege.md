# R0-19 · Öffentliche API- und Erweiterungsverträge

**Status:** Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet. Es gibt keine Implementierung; alle Namen, Signaturen und Schemas sind Spezifikation, kein Code im Repository.
**Stand:** 16.11.2026
**ADR-Bezug:** ADR-006 (Outbox-Zustellung, von R0-06 §4.6 an R0-19 übergeben), ADR-007 (Fehlerformat, neu), ADR-008 (Event-Envelope v1, neu).
**Bezug:** TODO R0-19 (DoD: Hooks, Filter, REST, Shortcodes, Blocks, CLI, Events mit Versionierung, Auth, Fehlercodes, Deprecation), R1-28, R3-01, R3-12, SUITE-003; Konzept Kap. 8 (Adapter-Handshake), 13 (Präfixe, `konzept.md:257`), 18, 18.1, 18.2, 30.2–30.4, 31, 33; Inventur `R0-02-inventar/D-admin-frontend-api.md` (§4.1 W1–W52, §4.2 M1–M78, §5.1, §6), `C-infrastruktur-content.md` (§2.6/§4.4 CLI, §4.3 Cron, §4.7 Hooks), `A-lernen-pruefung.md` (§4.2 Hooks, §4.3 Shortcodes/Blocks, §5.6 Fehler), `B-commerce.md` (§2.5 Grant-Vertrag, §4.4 Hooks, §5.1 Grant-Zustände); `R0-04-r1-r3-grenze.md` (K-01…K-25); `R0-05-golden-master.md` (Fehlercodes in Erwartungswerten); `R0-06-datenmodell-adr.md` (§4.6 Outbox/Inbox, `ccap_request_idempotency`); `R0-07-rbac-idor-matrix.md` (RG-01…RG-21, Caps, Meta-Caps M-01…M-24); `R0-08-kurs-activity-pruefungsvertraege.md` (§2 Activity-Provider, §6.6 Fehlercodes, §7.3 Versionskennungen); `R0-16-backup-recovery-deployment.md` (Haltemodus, CLI-Namen); `R0-32-suite-traceability.md` (§6 Envelope-Abgleich, W2/W5/W10/W13/W14, F2, F6, V3, V6); `R0-36-suite-vertragsdeltas.md` (§3 Voucher `redemptions`, §4 Affiliate, X4).
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (nur gelesen, nicht verändert). Pfade relativ zu `bootspruefung-learning/`. Zusätzlich gelesen: `src/Mobile/Problem.php` (Fehlerobjekt der App-API), `src/Learning/Services/QuestionPresenter.php:145-170` (Frage-Token).

**Normative Sprache:** **MUSS** = Vertragsbestandteil; **SOLL** = Standard, begründete Abweichung erlaubt; **KANN** = optional.

**Beschlüsse (verbindlich):** **E-01** (Clean Room: LB-Routen, -Hooks und -Fehlercodes sind Verhaltensbelege, kein Code wird übernommen), **E-05** (nur `course-captain/v1`, Cookie + Nonce, keine Token-Auth in R1), **E-06** (einheitlicher Fehlercode-Katalog), **E-38** (öffentliche Course-Events, Read-API und Connector-Registry sind **Pro**; Free behält nur die interne Outbox), **E-39/E-39b** (schreibende Workflow-Aktionen über Grant-Quelle `workflow` im Course-Pro-Connector), **E-118…E-120** (Deprecation, Event-Log, Freigabe je Event-Klasse), **E-207** (Präfix `ccap_`). Außerdem: E-07/E-49, E-10/E-239 (Härtung im Kern), E-16, E-17, E-20/E-147 (Erklärungen nur Pro), E-21, E-25a (Bundle), E-26, E-27 (Zugang je Modul), E-28, E-29, E-32, E-33 (Shop-Recht/Dispute im Woo-Adapter), E-37, E-45/E-45a, E-46, E-53, E-74, E-84, E-85/E-86, E-87, E-96 (PDF nur Pro), E-168 (Aufgaben-Abgaben R1), E-182, E-185/E-254, E-231.

## Beschlussstand

| Beschluss | Änderung in diesem Dokument |
|---|---|
| E-38, E-182, E-185, E-254 | §5 neu geschnitten: interne Outbox in Free; öffentliche Hooks `course_captain_event_v1`/Aliase, EventSubscriber, Connector-Registry, Pull-API (R-53, `get_events_v1`) und lesende Partner-API sind Pro (Entitlements `course.events`, `course.connectors`, E-95). Free zeigt die Partnererkennung nur lesend (§6) |
| E-118 (= D19-2) | Deprecation-Frist §1.5 bestätigt |
| E-119 (= D19-3) | Event-Log ohne Abonnent 30 Tage – nur mit Pro (§5.4) |
| E-120 (= D19-4) | Freigabe personenbezogener Event-Klassen je Connector – Pro (§5.7) |
| D19-1, D19-5, D19-6 | technisch festgelegt (ENTSCHEIDUNGEN §19): Hooks/Funktionen `course_captain_*`, `enroll_v1` entfällt, Codes klein, `application/json` |
| E-207, D-20-03, **E-285** | §1.2: Tabellen, CPTs, Meta, Caps, Rollen, Cron `ccap_*`; Optionen, Transients, Query-Vars, öffentliche Hooks/Funktionen `course_captain_*`; CSS `ccap-`; Fehlercodes, AJAX-/admin-post-Aktionen, Nonces, Shortcodes, Cache-Gruppe `ccap_*` (E-285) |
| E-39/E-39b | Quelle `workflow` nur über den Course-Pro-Connector (§4.1, §5.8) |
| E-27, E-25a, E-26 | Grant-Payload und Events mit `module_uid`; `depends_on_course_uid`; Bundle-Zugang abgeleitet (§4.1, §5.3, §5.8) |
| E-168 | neue Routen R-54…R-58 für Text-/Dateiaufgaben (R1-46), Bewertungsliste R-46 |
| E-20/E-147, E-96, E-231, E-74 | Erklärungstexte nur Pro (R-08/R-09/R-22/R-25); PDF R-41 Pro; Gruppenbericht R-45 Pro; Prüfungs-Nachbewertung R-47 mit Gradebook (Pro) |
| E-10, E-33 | Härtungs-Hooks im Kern (R1-45); Shop-Recht, PayPal/Klarna-Dispute im Woo-Adapter (§4.4, R0-40 §4.4) |
| E-45/E-45a | Filter für externe Medien wirkt im Modus „Klick zum Laden“ (§4.3) |

Die Nachträge und die Deltas §8 sind eingearbeitet bzw. mit Status versehen.

---

## 0. Kurzfassung

| Größe | Wert |
|---|---:|
| Vertragsflächen mit Versionskennung (§1.1) | 11 |
| REST-Routen `course-captain/v1` R1 Free (§2.3) | 52 (47 aus dem Entwurf + 5 für Aufgaben, E-168); 6 Routen nur mit Pro (R-41, R-45, R-47, R-51…R-53) |
| davon aus LB W1–W52 abgeleitet / aus LB-Nebenrouten / neu | 34 Routen aus 32 W-Nummern / 4 / 12 |
| entfallende LB-Routen | W21–W23, W29–W44, W52 (20), `/faqs`, 2 Zahlungs-Webhooks, M1–M78 als eigene API (E-05) |
| Fehlercodes im Katalog (§3) | 46 R1 + 7 für R3 reserviert |
| PHP-API-Funktionen (§4.1) | 14 (`enroll_v1` entfällt, D19-5) |
| Actions/Filter (§4.2–4.4) | 13 Actions (4 davon nur Pro) · 25 Filter; rund 45 LB-Hooks entfallen oder werden durch PHP-API/Registry ersetzt |
| Shortcodes / Blocks (§4.5, §4.6) | 3 / 5 |
| WP-CLI-Unterkommandos `wp course-captain …` (§4.7) | 15 R1 (8 aus LB übernommen, 2 ersetzt, 5 neu) + `woo reconcile` R3; von 15 LB-Kommandos entfallen 4 |
| Cron-/Action-Scheduler-Hooks (§4.8) | 5 R1 + 3 Pro/R3 |
| Event-Typen v1 (§5.3) | 27 (24 stabil, 3 experimentell) |
| Integrationszustände (§6) | 8 |
| Entscheidungen (§7) | D19-1…D19-6 entschieden (E-118…E-120, ENTSCHEIDUNGEN §19) |

**Kernaussagen:**
1. **Ein Envelope v1** (§5.1): Struktur aus Kap. 30.3 (Typ + Schemaversion + Objektversion + Payload getrennt), ergänzt um `idempotency_key` und `user_ref` aus Kap. 18 sowie `privacy_tags` und `causation_id` aus dem Suite-Abgleich (R0-32 W5/W13). Die Hooknamen aus Kap. 18 bleiben als lokale Aliase bestehen.
2. **Eine Statusliste** (§6): die acht Zustände aus Kap. 33 (Produktentscheidung, jünger, Obermenge). `incompatible` aus Kap. 30.3 heißt `active_incompatible`; `incompatible_contract` ist **Fehlerklasse**, kein Zustand.
3. **Ein Fehlerformat** (§3): LB-App-Problemobjekt (`type/title/status/detail/code/request_id` + `errors/details/retry_after`), WordPress-kompatibel (`code/message/data.status`), Codes `ccap_*` in Kleinschreibung.
4. **Präfixe:** öffentliche Hooks, PHP-Funktionen, Optionen, Transients und Query-Vars `course_captain_*`; Tabellen, CPTs, Meta, Caps, Rollen, Cron-Hooks, Fehlercodes, AJAX-/admin-post-Aktionen, Nonces, Shortcodes und Cache-Gruppe `ccap_*` (E-207, E-285); CSS `ccap-` (§1.2).
5. **Edition (E-38):** Free hat die interne Outbox und alle Lern-/Verwaltungsrouten; öffentliche Events, Read-API für Partner und Connector-Registry sind Pro.

---

## 1. Versionierungs- und Deprecation-Policy

### 1.1 Vertragsflächen und Versionskennungen

| # | Fläche | Name / Präfix | Versionskennung | Wo die Version steht |
|---|---|---|---|---|
| V-01 | **Gesamtvertrag** | Konstante `COURSE_CAPTAIN_CONTRACT_VERSION` (`'1.0.0'` mit R1-Release) | SemVer | PHP-Konstante, Funktion `course_captain_contract_version()`, `GET /status`, jedes Event (`contract_version`) |
| V-02 | REST | Namespace `course-captain/v1` | Major im Namespace | URL; Minor/Patch über V-01 |
| V-03 | PHP-API (Funktionen) | `course_captain_<verb>_<objekt>_v1()` | Major im Namen | Funktionsname |
| V-04 | Actions/Filter | `course_captain_*`; versionierte Verträge mit Suffix `_v1` | Major im Namen (nur bei Objekt-/Array-Parametern, sonst `@since`) | Hookname, PHPDoc `@since` |
| V-05 | Events | `event_type` (`<aggregat>.<ereignis>`) | `schema_version` je Typ (`MAJOR.MINOR`) | Envelope |
| V-06 | Fehlercodes | `ccap_*` | gehört zu V-01 | REST-Body, `WP_Error::get_error_code()` |
| V-07 | Blocks | `course-captain/<name>` | `block.json` `version` + `deprecated`-Einträge | `block.json` |
| V-08 | Shortcodes | `[ccap_<name>]` (E-285) | gehört zu V-01 | Doku |
| V-09 | WP-CLI | `wp course-captain <kommando>` | gehört zu V-01; Ausgabeformat `--format=json` ist Vertrag | `wp help course-captain` |
| V-10 | Activity-Provider | `contractVersion()` je Provider (R0-08 §2.3) | SemVer je Provider | Provider-Klasse, eingefrorene Definition |
| V-11 | Connector (Pro, E-38) | `contractRange()` je Connector (§5.7) | SemVer-Bereich | Registry, Integrationsstatus |

**Plugin-Version ≠ Vertragsversion.** `Version:` im Plugin-Kopf folgt SemVer für das Produkt. `COURSE_CAPTAIN_CONTRACT_VERSION` ändert sich nur, wenn sich ein öffentlicher Vertrag ändert: neue Route/Hook/Feld → Minor; Fehlerbehebung ohne Vertragsänderung → kein Sprung; Bruch → Major (neuer Namespace `v2`, neue `_v2`-Hooks, parallel zu v1). Ein Plugin-Patch-Release darf den Vertrag nie ändern.

### 1.2 Präfix-Festlegung (beschlossen)

| Kennung | Präfix | Begründung |
|---|---|---|
| Öffentliche Actions/Filter, PHP-Funktionen, Konstanten | `course_captain_` / `COURSE_CAPTAIN_` | Konzept Kap. 13/18, R0-07 §4.8, ENTSCHEIDUNGEN §19 (D19-1). Ein kurzes Präfix im **globalen** Hook-Namensraum ist kollisionsanfällig |
| Optionen, Transients, Query-Vars | `course_captain_` | R0-20 D-20-03, R0-27 T27 (Optionsnamen `course_captain_*`), E-285 |
| Tabellen (`{$wpdb->prefix}ccap_*`), CPTs, Taxonomien, Post-Status, Meta, Capabilities, Rollen, Cron-Hooks | `ccap_` | Beschluss E-207 (WordPress.org-Review verlangt mindestens 4 Zeichen; vor R1-01 gegen die aktuelle Richtlinie prüfen) |
| CSS-Klassen | `ccap-` | R0-20 D-20-03 |
| Fehlercodes, AJAX-/admin-post-Aktionen, Nonce-Aktionen, Shortcodes, Cache-Gruppe | `ccap_` | **E-285** (präzisiert E-207/E-06; ersetzt für AJAX/admin-post und Nonces die Festlegung D-20-03) |
| PHP-Namensraum | `CourseCaptain\` | Kap. 13 |
| REST / Blocks / CLI | `course-captain/v1`, `course-captain/<block>`, `wp course-captain` | Kap. 13, Kap. 26.2 |

### 1.3 Stabilitätsstufen

| Stufe | Kennzeichen | Zusage | Wer darf es nutzen |
|---|---|---|---|
| **stabil** | PHPDoc `@since x.y.z` + `@api`; REST-Schema `x-cc-stability: stable`; in `docs/API.md` (R1-28) | Änderungen nur additiv; Entfernen/Umbenennen nur nach §1.5 | alle, auch Suite-Connectoren |
| **experimentell** | `@since` + `@experimental`; Schema `x-cc-stability: experimental`; Name ohne `_v1`-Suffix | darf in jedem **Minor** brechen, mit Changelog-Eintrag; nie von Suite-Connectoren vorausgesetzt | eigene Module, Pro, Projekt-Connectoren auf eigenes Risiko |
| **intern** | `@internal`; Schema `x-cc-stability: internal`; nicht in `docs/API.md` | keine Zusage | nur Course Captain selbst (Admin-UI, Lern-App) |
| **veraltet** | `@deprecated x.y.z Use …`; REST-Header (§1.5) | funktioniert unverändert bis Ablauf der Frist | Bestandsnutzer |

Ein Element wechselt von experimentell nach stabil frühestens nach einem Minor-Release ohne Änderung und mit Contract-Test (R1-28). Hooks mit `_v1`-Suffix sind immer stabil.

### 1.4 Was ein Bruch ist (Major oder Deprecation-Pflicht)

Bruch: Route, Methode, Hook, Funktion, Shortcode, Block, Attribut, CLI-Kommando oder Option entfernen/umbenennen; Pflichtparameter hinzufügen; Typ oder Bedeutung eines Felds ändern; Enum-Wert entfernen; HTTP-Status oder Fehlercode für denselben Fall ändern; Hook-Zeitpunkt ändern (z. B. vor statt nach Commit); Filter-Rückgabetyp ändern; Event-Feld entfernen; Berechtigung **lockern**.

Kein Bruch (Minor): neue optionale Parameter, neue Antwortfelder, neue Enum-Werte **in Antworten** (Clients MÜSSEN unbekannte Werte tolerieren), neue Fehlercodes für **neue** Fälle, neue Routen/Hooks/Events, Berechtigung **verschärfen** aus Sicherheitsgründen (Changelog-Pflicht).

### 1.5 Deprecation-Fristen und Mechanik

**Frist:** Ein stabiles Element bleibt nach seiner Deprecation **mindestens zwei Minor-Releases und mindestens sechs Monate** funktionsfähig, je nachdem, was später endet (Muster Voucher Captain `CONNECTORS.md:76-82`, R0-36 C8). Ein Major-Namespace (`v1` neben `v2`) bleibt **mindestens zwölf Monate** parallel. Ausnahme nur bei Sicherheitslücken: sofortige Verschärfung mit Changelog und Admin-Hinweis (D19-2).

| Fläche | Mechanik |
|---|---|
| Filter | Alter Name über `apply_filters_deprecated( $alt, $args, $version, $neu )` weiterreichen; das ruft `_deprecated_hook()` nur, wenn jemand den alten Filter nutzt |
| Action | `do_action_deprecated( $alt, $args, $version, $neu )` (ruft `_deprecated_hook()`) |
| PHP-Funktion | Wrapper mit `_deprecated_function( __FUNCTION__, $version, $neu )`; Argument: `_deprecated_argument()` |
| REST-Route/-Feld | Antwort-Header `Deprecation: @<unix-zeit>` (RFC 9745), `Sunset: <HTTP-Datum>` (RFC 8594), `Link: <doc-url>; rel="deprecation"`; Feld im Schema `deprecated: true`; Nutzung wird je Route gezählt und im Systemstatus gezeigt |
| Fehlercode | Codes werden **nie** neu vergeben. Ein ersetzter Code wird bis Fristende weiter gesendet; der neue Code kommt erst mit dem nächsten Major. Feinere Unterscheidung nur über `reason`/`details` |
| Block | Name ändert sich nie. Attribut-Umbenennung über `deprecated`-Eintrag mit `migrate()` in der Block-Registrierung; Wegfall eines Blocks = Transform auf Nachfolger + Server-Render des alten Namens bis Fristende |
| Shortcode | Alter Tag bleibt als Alias registriert und meldet einmal je Anfrage `_deprecated_function( '[alt]', $version, '[neu]' )` (nur bei `WP_DEBUG`) |
| WP-CLI | Alter Name bleibt als Alias mit `WP_CLI::warning()`; JSON-Felder wie REST |
| Event | Neue `schema_version` (Minor additiv, Major = neuer Typ-Major parallel: Abonnent wählt Major in seiner Subscription, §5.7); alter Major wird bis Fristende parallel erzeugt |
| Option/Konstante | Lesen über Migrationsschritt (R0-37), alter Name wird ein Minor lang gespiegelt |

**Changelog:** jede Vertragsänderung in `docs/API-CHANGELOG.md` (anzulegen in R1-28) mit Version, Art (added/deprecated/removed/security), Ersatz und Fristende. Release-Gate (R1-28/R3-12): Contract-Tests gegen Snapshot der Route-/Hook-/Event-Schemas schlagen bei nicht deklarierter Änderung fehl.

### 1.6 Versionshandshake

- `do_action( 'course_captain_loaded_v1', CourseCaptain\Contract\Api $api )` auf `plugins_loaded` (Priorität 20), **nach** Migration und Registry-Aufbau. Erst danach dürfen Erweiterungen registrieren (Kap. 18).
- `course_captain_contract_version(): string` und `course_captain_supports( string $feature ): bool` (Feature-Schlüssel z. B. `events.v1`, `grants.write.v1`, `outbox.subscriber`, `certificates`); unbekannte Schlüssel → `false`. Ohne Pro liefern `events.v1`, `grants.write.v1` und `outbox.subscriber` `false` (E-38).
- Connectoren (Pro, E-38) deklarieren `contractRange()` (z. B. `>=1.0 <2.0`). Passt der Bereich nicht, registriert die Registry den Connector als `active_incompatible` mit Fehlerklasse `incompatible_contract` (§6) und ruft ihn nie auf; kein Fatal (Kap. 18, 33).
- Partner-Erkennung nur über dokumentierte Versions-APIs (z. B. `defined( 'VCAP_VERSION' ) && function_exists( 'vcap' )`, R0-36 C8), **nie** über den Plugin-Slug allein (Kap. 33).

---

## 2. REST-Routen R1

### 2.1 Konventionen (gelten für jede Route)

| Thema | Regel | LB-Herkunft |
|---|---|---|
| Namespace | `/wp-json/course-captain/v1/…`; keine zweite App-API, kein Tenant-Header (E-05) | INV-D-089; M1–M78 entfallen |
| Auth | **C** = WP-Login-Cookie + `X-WP-Nonce` (`wp_rest`). Nutzer-ID nur aus der Sitzung (R0-07 P-09). Nonce-Erneuerung per AJAX `ccap_rest_nonce` (E-285), Client wiederholt einmal bei `rest_cookie_invalid_nonce`. **Ö** = öffentlich (Rate-Limit), **K** = Monitor-Schlüssel im Header, **H** = HMAC-Signatur | D §6.1, NonceRefresh.php |
| Autorisierung | `permission_callback` prüft Anmeldung, Lernsperre und primitive Cap; Objektprüfung (Meta-Cap) im Callback **vor** jedem Seiteneffekt über den PolicyService (R0-07 §4.1). Nie `__return_true` außer bei **Ö**-Routen mit Kommentar | R0-07 P-02/P-03 |
| Fremd = unsichtbar | fremde oder nicht sichtbare Objekte → `404 ccap_not_found` (R0-07 P-08) | ExamService.php:1034-1042 |
| IDs | Pfadparameter sind UUID v4 (`uid`), Muster `[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}` (R0-08 §1.2). Ausnahmen: `{position}` (Ganzzahl ≥ 1), `{token}` (Einladungs-/Verifikationstoken), `{connector}` (`[a-z0-9-]{1,64}`). Post-IDs, Slugs und LB-Kurscodes sind **kein** Vertrag | LB: `code` `^[A-Za-z0-9-]+$` |
| Request-Schema | `args` mit JSON-Schema, unbekannte Felder werden verworfen; Schemafehler liefert WordPress selbst (`rest_invalid_param`/`rest_missing_callback_param`, 400) | LearnerController args |
| Response-Schema | jede Route liefert `get_item_schema()`/`schema`; Zeiten RFC 3339 UTC (`2026-11-16T09:30:00Z`); Punkte als Tausendstel-Ganzzahl (R0-08 Δ-3) | – |
| Idempotenz | Header `Idempotency-Key: <UUID v4>`. **P** = Pflicht, **O** = optional, **N** = natürlich idempotent (PUT/DELETE auf Zustand, GET). Speicherung in `ccap_request_idempotency (user_id, scope, idem_key)` mit `request_hash`, 7 Tage; Eintrag „in Arbeit“ > 300 s gilt als verwaist. Gleicher Key + gleicher Hash → gespeicherte Antwort (gleicher Status); gleicher Key + anderer Hash → `409 ccap_idempotency_conflict`; „in Arbeit“ → `409 ccap_request_in_progress` mit `retry_after: 2`. Damit wird die **bedingte** Tabelle aus R0-06 zur festen Tabelle (Delta §8) | Idempotency.php:24-27, :140; INV-D-094 |
| Nebenläufigkeit | Umbenennen/Ändern von Sammlungen und Gruppen KANN `If-Match: "<row_version>"` senden; Abweichung → `409 ccap_version_conflict`. Antwort trägt `ETag` | M14 `expected_version`/If-Match |
| Pagination | `page` (≥ 1), `per_page` (1–100, Standard 20); Header `X-WP-Total`, `X-WP-TotalPages` | M24 |
| Caching | `Cache-Control: no-store, private` für alle Antworten außer `R-42` (`no-store`, ohne `private`) | LearnerController.php:469-493 |
| Korrelation | jede Antwort trägt `X-Request-Id: req_<32 hex>`; derselbe Wert steht im Fehlerkörper und im Audit | Problem.php:23-26 |
| Wartung | schreibende Anfragen während Migration/Read-only-Fenster → `503 ccap_maintenance`, `Retry-After: 30` (R0-16, Read-only-Fenster „B“) | Maintenance.php:59-77 |
| Rate-Limits | Buckets je Zweck (§4.3 `course_captain_rate_limits`); Überschreitung → `429 ccap_rate_limited` mit `Retry-After` und `details.bucket`; Zählerspeicher defekt: Einlösen/Einladen/Verifikation fail-closed (`503 ccap_temporarily_unavailable`, `reason: limiter_unavailable`), Lern-/Antwort-Limits fail-open (E-46) | RateLimiter.php:115-120 |

**Implizite Fehler jeder Route** (werden in §2.3 nicht wiederholt): `401 ccap_auth_required` (außer **Ö**), `403 rest_cookie_invalid_nonce` (WP-Kern, durchgereicht), `403 ccap_account_locked`, `400 rest_invalid_param`, `500 ccap_internal_error`; bei schreibenden Routen zusätzlich `503 ccap_maintenance`, `503 ccap_db_busy`.

### 2.2 Abgeleitete Routengruppen

Gruppenbildung, Caps und Scope-Prüfungen stammen aus **R0-07 §4.1 (RG-01…RG-21)**; diese Tabelle ergänzt Request/Response, Idempotenz und Fehlercodes. Cap-Spalte: primitive Cap (#-Nummer aus R0-07 §3.1) oder Meta-Cap (M-xx aus R0-07 §3.2).

### 2.3 Routen-Tabelle

| # | Methode Pfad | LB | RG | Auth | Cap / Scope | Request (Kurz) | Response (Kurz) | Idem. | Fehlercodes (zusätzlich zu §2.1) |
|---|---|---|---|---|---|---|---|---|---|
| R-01 | `GET /status` | W1 | RG-01 | C | `ccap_view_system_status` | – | `{plugin_version, contract_version, schema_version, outbound_mode, hold_mode, checks[{id, status: ok\|warn\|fail, message}]}` | N | `ccap_forbidden` |
| R-02 | `GET /imports` | neu | RG-02 | C | M-21 | `page, per_page, status?` | Liste `{uid, status, created_by, created_at, counts}` | N | `ccap_forbidden` |
| R-03 | `GET /imports/{batch}` | neu | RG-02 | C | M-21 | – | `{uid, status, progress{processed,total}, report_url?, errors[]}` | N | `ccap_not_found` |
| R-04 | `POST /imports/{batch}/step` | W2 | RG-02 | C | M-21; `publish/activate` zusätzlich #3 | `{action: validate\|preview\|approve\|import\|activate\|discard}` | `{status, processed, total, done: bool}`; 250 Einträge je Schritt (LB) | N (Batch-Lock, Fortsetzung statt Wiederholung) | `ccap_import_failed` 409, `ccap_import_state_invalid` 409, `ccap_resource_busy` 409 |
| R-05 | `GET /me/courses` | W3 | RG-03 | C | angemeldet | – | Liste `{course_uid, title, permalink, access{state: active\|pending\|suspended\|expired\|limited\|not_granted, valid_until, scopes[], modules[]?, via_bundle?}, progress{completion_percent, mastery_percent}, next{type, ref}}` (`modules` nur bei Modul-Grants, E-27; `via_bundle` = Bundle-Kurs-UID, E-25a); **ohne** Shop-/Ticket-/Garantiefelder (K-16) | N | – |
| R-06 | `GET /courses/{course}` | W4 | RG-04 | C | M-01; Features aus M-02 je Scope | – | `{course_uid, revision_no, title, modules[{uid, title, access{state, scopes[]}, lessons[{uid, title, type, locked_reason?}]}], access, progress, exam_summary, downloads[{media_uid, title}], contains[{course_uid, title}]?}`; Zugang je Modul (E-27), enthaltene Kurse bei Bundles (E-25a); Felder gesperrter Scopes/Module leer. Erweiterungsfelder nur über Response-Registry mit eigenem Schema (K-16) | N | `ccap_not_found` |
| R-07 | `GET /courses/{course}/next-question` | W5 | RG-05 | C | M-02(`view`) | `mode` (`smart\|new\|wrong\|topic\|module\|favorites\|collection\|exam_mistakes`, Standard `smart`), `module?`, `topic?` (≤190), `collection?`, `exam_session?` | `{question{uid, revision, type, text, answers[{id, text}], multi}, token, context, progress}`; **keine** Lösungen | N | `ccap_not_found`, `ccap_content_locked` (`reason: running_exam` u. a.) |
| R-08 | `POST /answers` | W6 | RG-06 | C | M-02 zur Antwortzeit | `{token (≤2000), answer_ids[] (≤26), known: bool\|null, response_ms}` | `{correct, correct_ids[]` (nach Einstellung, E-147)`, explanation?` (nur Pro, E-20)`, learn_status, due_at}` | **P** | `ccap_invalid_token` 422, `ccap_question_token_expired` 422, `ccap_validation_failed` 422, `ccap_content_locked` 403, `ccap_rate_limited` 429 (`bucket: answer`), `ccap_idempotency_key_required` 428, `ccap_idempotency_conflict` 409, `ccap_request_in_progress` 409 |
| R-09 | `POST /answers/reveal` | W7 | RG-06 | C | M-02 | `{token}` | `{correct_ids[], explanation?}` (Erklärung nur Pro, E-20); zählt als „nicht gewusst“ nach LB-Verhalten | N | `ccap_invalid_token`, `ccap_question_token_expired` |
| R-10 | `PUT /courses/{course}/favorites/{question}` | W8 | RG-07 | C | M-02(`view`) | – | `{favorite: true}` | N | `ccap_not_found` |
| R-11 | `DELETE /courses/{course}/favorites/{question}` | W8 | RG-07 | C | M-02(`view`) | – | `{favorite: false}` | N | `ccap_not_found` |
| R-12 | `GET /courses/{course}/saved` | W9 | RG-07 | C | M-02(`view`) | `collection?, sort (saved_at\|assigned_at), order` (aus M34) | Liste `{question_uid, saved_at, collections[]}` | N | `ccap_not_found` |
| R-13 | `PUT /courses/{course}/saved/{question}` | W10 | RG-07 | C | M-02(`view`) | `{favorite: bool, collections[] (≤ MAX)}` | `{favorite, collections[]}` | N | `ccap_not_found`, `ccap_validation_failed` |
| R-14 | `POST /courses/{course}/collections` | W11 | RG-07 | C | M-02(`view`) | `{name (≤200)}` | 201 `{uid, name, row_version}` | **O** | `ccap_validation_failed` (Limit erreicht) |
| R-15 | `PUT /courses/{course}/collections/{collection}` | W12 | RG-07 | C | M-02(`view`), eigene Sammlung | `{name}`; `If-Match` optional | `{uid, name, row_version}` | N | `ccap_not_found`, `ccap_version_conflict` |
| R-16 | `DELETE /courses/{course}/collections/{collection}` | W12 | RG-07 | C | wie R-15 | – | 204 | N | `ccap_not_found` |
| R-17 | `GET /courses/{course}/exam` | W13 | RG-08 | C | M-02(`exam`) | – | `{rules[{uid, version, title, time_limit_s, reduced}], variants[], attempts{used, max, cooldown_until}, running_session?}` | N | `ccap_not_found`, `ccap_content_locked` |
| R-18 | `POST /courses/{course}/exam-sessions` | W14 | RG-08 | C | M-02(`exam`) | `{rule_uid?, variant_uid?, activity_uid?}` | 201 `{session}` (neuer Versuch) bzw. 200 `{session}` (laufender Versuch wird fortgesetzt) | **P** (R0-08 `request_key`) | `ccap_exam_not_startable` 422, `ccap_exam_start_busy` 409, `ccap_exam_attempts_exhausted` 422, `ccap_rate_limited` 429 (`bucket: exam_start`) |
| R-19 | `GET /exam-sessions/{session}` | W15 | RG-09 | C | M-09 | – | `{uid, status, mode, started_at, deadline_at, server_now, questions[{position, uid, text, answers[], answer_ids[], flagged}]}` | N | `ccap_not_found` |
| R-20 | `PUT /exam-sessions/{session}/answers/{position}` | W16 | RG-09 | C | M-08 | `{answer_ids[], known?, age_ms, answered_at}` | `{applied: bool, saved_at}`; `applied=false` bei älterem Stand (LB) | N (Stand-Vergleich über `age_ms`) | `ccap_exam_expired` 410, `ccap_exam_already_submitted` 409, `ccap_exam_aborted` 409, `ccap_exam_answer_locked` 422, `ccap_exam_invalid_answer` 422, `ccap_exam_conflict` 409 |
| R-21 | `PUT /exam-sessions/{session}/answers/{position}/flag` | W17 | RG-09 | C | M-08 | `{flagged: bool}` | `{flagged}` | N | wie R-20 ohne `ccap_exam_invalid_answer` |
| R-22 | `POST /exam-sessions/{session}/answers/{position}/reveal` | W18 | RG-09 | C | M-08, nur wenn Regel Aufdecken erlaubt | – | `{correct_ids[], explanation?}` (nur Pro, E-20); Antwort danach gesperrt | N | wie R-21, `ccap_forbidden` (Regel erlaubt es nicht) |
| R-23 | `POST /exam-sessions/{session}/submit` | W19 | RG-09 | C | M-08 (Abgabe auch nach Zugangsende, R0-05) | – | `{status: evaluated, result{points_milli, max_points_milli, passed, parts[]}}`; zweite Abgabe liefert dasselbe Ergebnis (200) | N | `ccap_exam_aborted` 409, `ccap_exam_conflict` 409 |
| R-24 | `POST /exam-sessions/{session}/abort` | W20 | RG-09 | C | M-08 | – | `{status: cancelled}`; Wiederholung 200 | N | `ccap_exam_already_submitted` 409 |
| R-25 | `GET /exam-sessions/{session}/review` | neu (M50) | RG-09 | C | M-09; Sichtbarkeit nach E-147, Erklärungen nur Pro (E-20) | – | `{result, items[{position, correct, points_milli, correct_ids?, explanation?}]}` | N | `ccap_exam_not_submitted` 409, `ccap_not_found` |
| R-26 | `GET /courses/{course}/lessons` | W26 | RG-10 | C | M-02(`view`) | – | Liste `{uid, activity_uid, type, title, completion{state, reason_code}, locked_reason?}` | N | `ccap_not_found` |
| R-27 | `GET /courses/{course}/lessons/{lesson}` | W27 | RG-10 | C | M-02(`view`) | – | `{uid, activity_uid, type, render{…} (RendersActivity, R0-08 §2.2), progress}` | N | `ccap_not_found`, `ccap_content_locked` (`reason: scheduled_release\|rights_unclear\|partial_access`) |
| R-28 | `PUT /courses/{course}/lessons/{lesson}/progress` | W28 | RG-10 | C | M-02(`view`) | `{kind: position\|confirm\|unconfirm\|card_status, payload}` (R0-08 `Interaction`) | `{completion{state, reason_code, rule_version}}` | **P** | `ccap_validation_failed` (z. B. `unconfirm` bei nachweisbasiertem Abschluss, E-70), `ccap_content_locked` |
| R-29 | `GET /courses/{course}/flashcards` | W24 | RG-10 | C | M-02(`view`) | `set?` | Liste `{card_uid, status: open\|practice\|known, streak}` | N | `ccap_not_found` |
| R-30 | `PUT /courses/{course}/flashcards/{card}` | W25 | RG-10 | C | M-02(`view`) | Modus `self_assessment`: `{status}`; Modus `streak`: `{known: bool}` (E-21) | `{status, streak}` | **P** (Doppelklick-Schutz) | `ccap_validation_failed` |
| R-31 | `GET /groups/join/{token}` | W45 | RG-11 | C | M-17 (Vorschau) | – | `{group{name}, course_titles[], seats_free: bool, bound_to_other_address: bool}`; nur maskierte Daten | N | `ccap_invalid_token` 422, `ccap_invite_expired` 410, `ccap_rate_limited` 429 |
| R-32 | `POST /groups/join` | W46 | RG-11 | C | M-17 | `{token}` (`^[gi][0-9a-f]{32}$`, LB) | `{seat_uid, group_uid, status: active}`; bereits Mitglied → 200 gleiche Antwort | N | `ccap_invalid_token` 422, `ccap_invite_expired` 410, `ccap_invite_other_account` 403, `ccap_group_not_active` 409, `ccap_group_full` 409, `ccap_rate_limited` 429 |
| R-33 | `GET /me/groups` | W47 | RG-12 | C | B-4 eigene Gruppen | – | Liste `{uid, name, status, seats{total, used}, invite_link_expires_at, members[{member_uid, display_name, email_masked, status}]}` (E-81) | N | – |
| R-34 | `PUT /me/groups/{group}` | W48 | RG-12 | C | M-15 | `{name (≤190)}`; `If-Match` optional | `{uid, name, row_version}` | N | `ccap_not_found`, `ccap_version_conflict`, `ccap_group_not_active` |
| R-35 | `POST /me/groups/{group}/invite` | W49 | RG-12 | C | M-15 | `{emails[] (≤200), bind_to_address: bool}` | `{results[{email_masked, status: invited\|already_member\|invalid\|limited}]}` | **P** | `ccap_group_not_active`, `ccap_group_full`, `ccap_rate_limited` (`bucket: group_invite`) |
| R-36 | `POST /me/groups/{group}/token` | W50 | RG-12 | C | M-15 | – | `{invite_url, expires_at}`; alter Link ungültig | **O** | `ccap_group_not_active` |
| R-37 | `DELETE /me/groups/{group}/members/{member}` | W51 | RG-12 | C | M-15, Mitglied ∈ Gruppe | `{reason? (≤500)}` | 204; Platz frei, Grant des Mitglieds `revoked` (`group_member_removed`) | N | `ccap_not_found` |
| R-38 | `GET /courses/{course}/faqs` | LB `/customer-faqs` | RG-13 | C | M-02(`view`) | `category?` | Liste `{uid, question, answer_html, category}` | N | `ccap_not_found`, `ccap_content_locked` |
| R-39 | `GET /tips` | LB `/tips` | RG-13 | C | angemeldet | `category?, page, per_page` | Liste `{uid, title, html, category}` | N | – |
| R-40 | `GET /me/certificates` | neu | RG-14 | C | B-8 | – | Liste `{uid, course_uid, revision_no, status: valid\|corrected\|revoked, issued_at, valid_until, view_url, pdf_url?}` (HTML-/Druckansicht in Free, `pdf_url` nur Pro, E-96) | N | – |
| *R-41* | `GET /certificates/{certificate}/pdf` | neu | RG-14 | C | M-18 | – | `application/pdf` (Snapshot, R0-08 §7.2, getaggt nach E-127) | N | `ccap_not_found` — **Pro** (E-96) |
| R-42 | `GET /certificates/verify/{token}` | neu | RG-14 | **Ö** | Token | – | `{status: valid\|revoked\|corrected\|expired, course_title, issued_at, holder_name?}` (Felder nach Datenschutz-Einstellung) | N | `ccap_not_found` (unbekannt, kein Orakel), `ccap_rate_limited` (`bucket: verify`, fail-closed) |
| R-43 | `GET /reports/courses/{course}` | neu | RG-15 | C | #12 + Scope | `group?` (Gruppenfilter im Basisbericht, E-231), `format?` (`csv`, E-98) | Aggregat `{learners, completion_rate, pass_rate, …}` | N | `ccap_not_found` |
| R-44 | `GET /reports/courses/{course}/learners/{user}` | neu | RG-15 | C | M-11 | – | `{display_name, access, completion, attempts[{uid, passed, evaluated_at}]}`; keine Antworten | N | `ccap_not_found` |
| *R-45* | `GET /reports/groups/{group}` | neu | RG-15 | C | M-16 | – | Aggregat je Mitglied minimiert | N | `ccap_not_found` — **Pro** (eigene Gruppenberichte, E-231; Fortschrittseinsicht für Besitzer nur Opt-in, E-232) |
| R-46 | `GET /grading/queue` | neu | RG-16 | C | M-10 | `course, page, per_page` | Liste offener Abgaben `{type: assignment, submission_uid, submitted_at}` (E-168); mit Pro-Gradebook zusätzlich `{type: exam_answer, session_uid, position}` | N | `ccap_not_found` |
| *R-47* | `PUT /exam-sessions/{session}/grades/{position}` | neu | RG-16 | C | M-10 (nie eigener Versuch) | `{points_milli, comment?}` | `{evaluation_no, result}`; neue Auswertungsrevision (E-74; Datenvertrag ab R1) | **P** | `ccap_forbidden`, `ccap_validation_failed`, `ccap_exam_conflict` — **Pro** (Gradebook, E-74, E-99) |
| R-48 | `POST /me/privacy-requests` | neu (LB admin-post `bp_privacy_request`) | RG-17 | C | M-23 (auch bei Lernsperre) | `{type: export\|erase}` | 202 `{status: created\|resent\|confirmed}` (LB `SelfService.php:82`) | **O** | `ccap_rate_limited` (`bucket: privacy`, 5/Tag) |
| R-49 | `GET /media/{media}` | LB `/datei/<id>` | RG-18 | C | M-20 | `Range`/`If-Range` | Datei (200/206), `Content-Disposition`, `X-Content-Type-Options: nosniff` | N | `ccap_not_found`, `ccap_range_not_satisfiable` 416 |
| R-50 | `GET /monitor` | LB `/monitor` | RG-20 | **K** | Header `X-CC-Monitor-Key` (`hash_equals`), **kein** `?key=` | – | `{status: ok\|error, checks?}` (Details nur mit Schlüssel); 200/503 | N | – |
| *R-51* | `POST /webhooks/{connector}` | LB `/webhooks/dispute` (Muster) | RG-19 | **H** | Connector aktiv; Signatur im `permission_callback` | Connector-Schema; Header `X-CC-Signature: t=<unix>,v1=<hex>`, `X-CC-Event-Id` | 202 `{status: accepted\|duplicate}` | N (Dedupe `ccap_inbox_event`) | `ccap_signature_invalid` 401, `ccap_connector_disabled` 403, `ccap_incompatible_contract` 409 — **R3** (R3-01) |
| *R-52* | `POST /me/codes/redeem` | M73–M75, K-14 | – | C | angemeldet; Connector-Fähigkeit `code_redeemer` | `{code (≤300), course_uid?}` | `{status: redeemed\|pending, grant_uid?, course_uid}` | **P** | `ccap_code_*` (§3.4), `ccap_rate_limited` (fail-closed) — **R3** (R3-04); ohne registrierten Redeemer nicht registriert |
| *R-53* | `GET /events` | neu | – | C | `ccap_manage_integrations` | `after (event_id), types[], limit (≤500)` | `{events[envelope], next}` (Pull/Backfill, §5.5) | N | – — **Pro/R3** (R3-01, E-38) |
| R-54 | `GET /courses/{course}/assignments/{lesson}` | neu (E-168) | RG-10 | C | M-02(`view`) für das Modul der Lektion | – | `{assignment{uid, type: text\|file\|text_file, max_files, allowed_types[]}, submissions[{uid, submission_no, status: draft\|submitted\|returned\|graded, submitted_at, grade?{points_milli, max_points_milli, passed, feedback}}]}` | N | `ccap_not_found`, `ccap_content_locked` |
| R-55 | `PUT /courses/{course}/assignments/{lesson}/draft` | neu (E-168) | RG-10 | C | M-02(`view`) | `{text? , submission_uid?}`; `If-Match` optional | `{submission_uid, status: draft, row_version}`; legt bei Bedarf die nächste `submission_no` an (nach `returned`) | N | `ccap_validation_failed` (`reason: not_editable`), `ccap_version_conflict`, `ccap_content_locked` |
| R-56 | `POST /assignment-submissions/{submission}/files` | neu (E-168) | RG-10 | C | eigene Abgabe im Status `draft` | `multipart/form-data` (Upload-Regeln R0-10) | 201 `{file{media_uid, name, size_bytes}}` | **P** | `ccap_validation_failed` (Typ, Größe, Anzahl), `ccap_not_found`, `ccap_rate_limited` |
| R-57 | `POST /assignment-submissions/{submission}/submit` | neu (E-168) | RG-10 | C | eigene Abgabe | – | `{status: submitted, submitted_at}`; Wiederholung 200 | N | `ccap_validation_failed` (`reason: empty`), `ccap_version_conflict` |
| R-58 | `PUT /assignment-submissions/{submission}/grade` | neu (E-168) | RG-16 | C | M-10 (nie eigene Abgabe) | `{action: grade\|return, points_milli?, feedback?, reason?}` | `{status: graded\|returned, evaluation_no}`; Neubewertung = neue `evaluation_no` mit Pflichtbegründung | **P** | `ccap_forbidden`, `ccap_validation_failed`, `ccap_version_conflict` |

**Entfallende LB-Routen (Test: `404 rest_no_route`):** W21–W23 (Navigation; Ersatz ist der neutrale Fragetyp „Zahl mit Toleranz“, E-22), W29–W44 (Funk, Gerätesimulation), W52 (Prüfungsanmeldung) – `exclude_domain_specific` (Inventur D §4.1); öffentliche `/faqs` (INV-D-056, Website-FAQ ist Theme-Sache, E-16); `/webhooks/paypal`, `/webhooks/dispute` im Kern – das Verhalten wird nach E-33 im Woo-Adapter (Pro, R3.1) neu gebaut (R-51, R0-40 §4.4); App-API `boatingschool/v1` M1–M78 als eigene API (E-05). Übernommene App-Muster: Problem-Fehlerobjekt (§3), Idempotency-Key-Header (M23, M32), If-Match (M14), Range-Auslieferung (M17), Rate-Limit-Speicher.

**Admin-Oberfläche:** Grants, Zugangsregeln, Einstellungen und Rechtefreigaben laufen in R1 über admin-post/AJAX (R0-07 §4.3), nicht über öffentliche REST-Routen. Werden dafür REST-Routen eingeführt, sind sie `x-cc-stability: internal`.

---

## 3. Einheitlicher Fehlerkatalog (ADR-007, Beschluss E-06)

### 3.1 Format

LB-App-Format (`src/Mobile/Problem.php`: `type, title, status, detail, code, message, request_id` + optional `errors, details, retry_after`) in einer **WordPress-kompatiblen** Hülle. Eine Fehlerantwort MUSS so aussehen:

```json
{
  "code": "ccap_content_locked",
  "message": "Dieser Inhalt ist erst nach Ablauf der Freischaltfrist verfügbar.",
  "data": { "status": 403 },
  "type": "urn:course-captain:problem:content-locked",
  "title": "Noch gesperrt",
  "status": 403,
  "detail": "Dieser Inhalt ist erst nach Ablauf der Freischaltfrist verfügbar.",
  "request_id": "req_3f2a…",
  "reason": "scheduled_release",
  "retry_after": null,
  "errors": null,
  "details": { "available_at": "2026-12-01T00:00:00Z" }
}
```

| Feld | Pflicht | Regel |
|---|---|---|
| `code` | ja | stabiler Code aus §3.2–3.4; **einziges** maschinenlesbares Unterscheidungsmerkmal neben `reason` |
| `message` / `detail` | ja | übersetzter, nutzerverständlicher Text; nie technische Details, SQL, Pfade, Stacktraces |
| `data.status` / `status` | ja | HTTP-Status (doppelt für `wp.apiFetch` und RFC-9457-Clients) |
| `type` | ja | `urn:course-captain:problem:<code ohne ccap_, _ → ->` |
| `title` | ja | kurzer, übersetzter Titel je Code |
| `request_id` | ja | wie Header `X-Request-Id` |
| `reason` | nein | Unterfall aus fester, dokumentierter Liste je Code (Enum, additiv erweiterbar) |
| `retry_after` | bei 409-retryable, 429, 503 | Sekunden; zusätzlich Header `Retry-After` |
| `errors` | bei `ccap_validation_failed` | `{feld: [{code, message}]}` |
| `details` | nein | codespezifische, dokumentierte Zusatzdaten; nie personenbezogene Daten Dritter |

**Content-Type:** `application/json; charset=UTF-8` (nicht `application/problem+json`), damit WordPress-Kern, `wp.apiFetch` und Caching-Plugins unverändert arbeiten (D19-6). **Codes in Kleinschreibung mit `ccap_`-Präfix** statt LB-`UPPER_SNAKE`: WordPress-Fehlercodes sind klein (`rest_forbidden`), das Präfix ist nach E-285 einheitlich `ccap_`; der LB-Code steht in §3.2 als Herkunft. Durchgereichte WP-Kerncodes (`rest_*`) bleiben unverändert.

**PHP-API und Hooks** (§4.1) verwenden dieselben Codes: Fehler als `WP_Error( code, message, [ 'status' => int, 'retryable' => bool, 'reason' => ?string, 'partner_code' => ?string, 'request_id' => string ] )`. Erwartete **Erfolgsvarianten** (Duplikat einer idempotenten Anfrage, „nichts zu tun“) sind **kein** Fehler, sondern ein Ergebnis mit `success: true, code: created|duplicate|updated|unchanged` (Klärung R0-32 F2b; Kap. 18 `duplicate` wird damit Ergebniscode).

### 3.2 Katalog R1

`R` = automatisch wiederholbar (gleicher Idempotency-Key), `–` = Klärung nötig.

| Code | HTTP | Bedeutung | `reason` (Enum) | R | LB-Herkunft (Web / App / Kap. 18) |
|---|---|---|---|---|---|
| **Allgemein** | | | | | |
| `ccap_auth_required` | 401 | nicht angemeldet | – | – | `bp_login_required` / `UNAUTHENTICATED` |
| `ccap_forbidden` | 403 | Objekt legitim bekannt, Aktion nicht erlaubt (R0-07 P-08) | `capability`, `rule`, `self_action` | – | `bp_forbidden` / – / `forbidden` |
| `ccap_account_locked` | 403 | Lernsperre oder Konto ohne Rolle auf der Site (E-79) | `learner_lock`, `no_role` | – | – (neu) |
| `ccap_content_locked` | 403 | Inhalt existiert und ist bekannt, aber derzeit nicht zugänglich | `not_granted`, `pending`, `suspended`, `expired`, `revoked`, `partial_access`, `scheduled_release`, `running_exam`, `rights_unclear`, `prerequisite` | – | – / `ENTITLEMENT_REQUIRED`, `…_EXPIRED`, `…_SUSPENDED`, `CONTENT_LOCKED` |
| `ccap_not_found` | 404 | unbekannt **oder fremd** (kein Existenz-Orakel) | – | – | `bp_not_found` / `RESOURCE_NOT_FOUND` / `not_found` |
| `ccap_validation_failed` | 422 | fachliche Validierung (nach Schemaprüfung) | – (Feldfehler in `errors`) | – | `bp_invalid` / `VALIDATION_FAILED` |
| `ccap_invalid_scope` | 422 | Scope/Ziel ungültig (z. B. Kurs nicht veröffentlicht, Scope oder Modul unbekannt, Abhängigkeit unzulässig) | `course_not_published`, `scope_unknown`, `module_unknown`, `dependency_invalid` | – | – / – / `invalid_scope` |
| `ccap_invalid_token` | 422 | Frage- oder Einladungstoken manipuliert, fremd oder falsches Format | `signature`, `other_account`, `format` | – | `RuntimeException` (QuestionPresenter.php:157-163) |
| `ccap_idempotency_key_required` | 428 | Pflicht-Header `Idempotency-Key` fehlt oder ist keine UUID | – | – | Text „Ungültiger Idempotency-Key.“ |
| `ccap_idempotency_conflict` | 409 | gleicher Key, anderer Inhalt bzw. anderer Nutzer/anderes Ziel | – | – | – / `IDEMPOTENCY_KEY_REUSED`; `lb_idempotency_conflict`; `vcap_idempotency_conflict` |
| `ccap_request_in_progress` | 409 | gleiche Anfrage läuft noch | – | R (`retry_after: 2`) | – / `REQUEST_IN_PROGRESS` |
| `ccap_version_conflict` | 409 | `If-Match` passt nicht mehr | – | – | – / `VERSION_CONFLICT` |
| `ccap_resource_busy` | 409 | Sperre belegt (Import, Gruppe, Grant) | `lock` | R | `bp_voucher_busy` (Muster) |
| `ccap_rate_limited` | 429 | Limit erreicht | – (`details.bucket`: `answer`, `exam_start`, `group_invite`, `group_join`, `privacy`, `verify`, `code_redeem`) | R (`retry_after`) | `bp_rate_limited` / `RATE_LIMITED`; `answer_rate_limited`, `exam_start_rate_limited` |
| `ccap_maintenance` | 503 | Migration oder Read-only-Fenster | `migration`, `read_only` | R (`retry_after: 30`) | `bp_maintenance` / `MAINTENANCE` |
| `ccap_db_busy` | 503 | Deadlock/Lock-Wait nach 3 Wiederholungen (R0-14 NFR-011) | – | R | – (neu) |
| `ccap_temporarily_unavailable` | 503 | vorübergehender Fehler, Partner oder Zählerspeicher nicht erreichbar | `limiter_unavailable`, `partner_unavailable`, `hold_mode` | R | – / `TEMPORARY_ERROR` / `retryable`; `vcap_temporary_failure` |
| `ccap_internal_error` | 500 | unerwarteter Fehler, ohne Details | – | – | `bp_internal` / `INTERNAL_ERROR` |
| `ccap_range_not_satisfiable` | 416 | ungültiger Byte-Bereich | – | – | – / `RANGE_NOT_SATISFIABLE` |
| **Lernen** | | | | | |
| `ccap_question_token_expired` | 422 | Frage-Token älter als 2 h | – | – | `question_token_expired` (Web 422, App 409) |
| **Prüfung** (R0-08 §6.6, harmonisiert) | | | | | |
| `ccap_exam_not_startable` | 422 | keine gültige Regel, Variante unvollständig, keine Fragen | `no_rule`, `variant_incomplete`, `no_questions` | – | `exam_not_startable` / `EXAM_NOT_STARTABLE` |
| `ccap_exam_start_busy` | 409 | Startsperre belegt | – | R | `exam_start_busy` |
| `ccap_exam_attempts_exhausted` | 422 | `max_attempts` erreicht oder Sperrfrist | `max_attempts`, `cooldown` | – (`retry_after` bei `cooldown`) | – (neu) |
| `ccap_exam_expired` | 410 | Aktion nach Frist + Kulanz | – | – | `exam_expired` (Web 422) / `EXAM_EXPIRED` 410 |
| `ccap_exam_already_submitted` | 409 | Versuch bereits abgegeben/ausgewertet | – | – | `exam_finished` / `EXAM_ALREADY_SUBMITTED` |
| `ccap_exam_aborted` | 409 | Versuch abgebrochen | – | – | `exam_finished` / `EXAM_ABORTED` |
| `ccap_exam_not_submitted` | 409 | Ergebnis/Review vor Abgabe | – | – | – / `EXAM_NOT_SUBMITTED` |
| `ccap_exam_answer_locked` | 422 | Antwort nach Aufdecken gesperrt | – | – | `exam_answer_locked` / `EXAM_ANSWER_LOCKED` 409 |
| `ccap_exam_invalid_answer` | 422 | ungültige/leere Auswahl, falsche Anzahl | – | – | Text-`RuntimeException` |
| `ccap_exam_conflict` | 409 | CAS-Konflikt nach Wiederholung | – | R | – (neu) |
| `ccap_exam_snapshot_invalid` | 500 | Snapshot-Hash passt nicht; Health-Meldung | – | – | – (neu) |
| **Gruppen und Einladungen** | | | | | |
| `ccap_invite_expired` | 410 | Einladung oder Beitrittslink abgelaufen | – | – | Mitgliedsstatus `expired` |
| `ccap_invite_other_account` | 403 | Einladung an eine andere Adresse gebunden (E-28) | – | – | – / `VOUCHER_NOT_ALLOWED` (Muster) |
| `ccap_group_not_active` | 409 | Gruppe ausgesetzt/widerrufen/abgelaufen | `suspended`, `revoked`, `expired` | – | – / `GROUP_NOT_ACTIVE` |
| `ccap_group_full` | 409 | kein freier Platz | – | – | – / `ACCESS_NOT_POSSIBLE` |
| **Import** | | | | | |
| `ccap_import_failed` | 409 | Importschritt fehlgeschlagen (Bericht im Batch) | – | – | `bp_import_failed` |
| `ccap_import_state_invalid` | 409 | Aktion im aktuellen Batch-Status nicht erlaubt | – | – | – (neu, ImportPage-Zustände) |
| **Export und Datenschutz** | | | | | |
| `ccap_export_not_ready` | 409 | Export wird erstellt | – | R | – / `EXPORT_NOT_READY` |
| `ccap_export_expired` | 410 | Download abgelaufen | – | – | – / `EXPORT_EXPIRED` |
| `ccap_export_failed` | 410 | Export fehlgeschlagen | – | – | – / `EXPORT_FAILED` |
| **Integration / PHP-API** (Kap. 18 erweitert, R0-36 X4) | | | | | |
| `ccap_incompatible_contract` | 409 | Vertragsversion des Aufrufers/Connectors passt nicht | – | – | – / – / `incompatible_contract` |
| `ccap_connector_disabled` | 403 | Connector nicht freigegeben oder deaktiviert | – | – | – (R0-07 §3.4) |
| `ccap_source_not_allowed` | 403 | Connector schreibt auf fremde Grant-Quelle (z. B. Workflow-Connector auf Nicht-`workflow`-Grant, E-39b, R0-07 §4.8) | – | – | – (neu) |
| `ccap_signature_invalid` | 401 | Webhook-Signatur, Zeitstempel (±300 s) oder Secret ungültig | – | – | `DisputeWebhooks` 401 |
| `ccap_partner_error` | 502 | Partner meldet fachlichen Fehler ohne CC-Gegenstück; Original in `partner_code` | – | je `retryable` | – (neu, R0-36 §3.3 Nr. 3) |
| `ccap_feature_unavailable` | 404 | Funktion nicht aktiv (Modul/Pro/Connector fehlt, z. B. Pro-Routen ohne Pro); bewusst wie „nicht vorhanden“ | – | – | – / `COURSE_VOUCHERS_UNAVAILABLE` (503) |
| **Ergebniscodes (kein Fehler)** | 200/201 | `created`, `duplicate`, `updated`, `unchanged` im Ergebnisobjekt der PHP-API | – | – | Kap. 18 `duplicate`; Affiliate `created\|duplicate` |

Summe R1: 46 Fehlercodes (ohne Ergebniscodes und durchgereichte `rest_*`).

### 3.3 Abbildungsregeln

1. Ausnahmen im Code (`AccessDenied`, `NotFound`, `DomainException`) werden **über den Code**, nie über den Text abgebildet (LB `BaseController.php:247-268`, R0-05 Fehlerhülle).
2. Ein neuer fachlicher Unterfall bekommt zuerst einen `reason`-Wert; ein neuer Code nur, wenn Clients anders reagieren müssen (anderer HTTP-Status oder anderes Retry-Verhalten).
3. Jeder Code ist in **genau einer** HTTP-Klasse. Der Status ändert sich nur per Major (§1.4).
4. Kein Code verrät die Existenz fremder Objekte (`ccap_not_found` statt `ccap_forbidden`, R0-07 P-08); öffentliche Routen (R-42) antworten bei unbekanntem und widerrufenem Token unterschiedlich nur in `status`, nicht im Fehlerweg.

### 3.4 Für R3 reservierte Codes (Einlösung, R3-04; Abbildung auf `vcap()->redemptions`)

| Code | HTTP | Bedeutung | Abbildung Voucher Captain (R0-36 §3.2) | LB-App |
|---|---|---|---|---|
| `ccap_code_invalid` | 422 | Code unbekannt, ungültig oder nicht einlösbar (neutral, keine Existenzauskunft, K-14); `reason`: `unknown\|not_redeemable\|insufficient_balance\|invalid_request` (R0-40 Δ40-4) | `vcap_not_found`, `vcap_not_redeemable`, `vcap_invalid_request` | `COUPON_INVALID`, `VOUCHER_INVALID`, `VOUCHER_NOT_FOR_COURSES` |
| `ccap_code_expired` | 410 | Code abgelaufen oder storniert; `reason`: `expired\|voided` | `vcap_expired`, `vcap_cancelled` | `VOUCHER_EXPIRED` |
| `ccap_code_not_yet_valid` | 422 | noch nicht gültig | `vcap_not_active` | `VOUCHER_NOT_YET_VALID` |
| `ccap_code_already_used` | 409 | bereits eingelöst (Erfolg nur, wenn Course den Grant zu **diesem** Key hält → Ergebnis `duplicate`) | `vcap_already_redeemed` | `VOUCHER_REDEEMED` |
| `ccap_code_in_progress` | 409 | Code gerade reserviert | `vcap_reserved` | `VOUCHER_RESERVED` |
| `ccap_code_wrong_target` | 422 | Code gilt für anderen Kurs | `vcap_target_unavailable` | `VOUCHER_WRONG_COURSE` |
| `ccap_code_not_allowed` | 403 | Code gehört zu anderem Konto | `vcap_not_allowed` | `VOUCHER_NOT_ALLOWED` |

Weitere Partnerfehler: `vcap_temporary_failure` → `ccap_temporarily_unavailable` (`reason: partner_unavailable`, R); `vcap_rate_limited` → `ccap_rate_limited` (`bucket: code_redeem`); `vcap_reservation_missing` → `ccap_not_found` (Grant → `revoked`, Grund `voucher_reservation_lost`); `vcap_idempotency_conflict` → `ccap_idempotency_conflict`; alles Übrige → `ccap_partner_error` mit `partner_code`.

---

## 4. PHP-API, Hooks, Shortcodes, Blocks, CLI, Cron

### 4.1 PHP-API v1 (Commands/Queries, Kap. 18 + 30.3)

**Edition (E-38):** Die PHP-API v1 ist die Schnittstelle für Connectoren und Partner. In Free sind nur `course_captain_contract_version()` und `course_captain_supports()` verfügbar; alle übrigen Funktionen registriert der Kern erst mit Pro (Connector-Registry). Der Kern selbst nutzt intern den GrantService und die Read-Modelle, nicht diese Funktionen.

Alle Funktionen existieren erst nach `course_captain_loaded_v1` (vorher: `function_exists()` = `false`). Gemeinsamer Parameter `$context = [ 'actor' => 'connector:<id>'|int $user_id, 'correlation_id' => ?uuid, 'causation_id' => ?uuid, 'request_id' => ?string ]`; `actor` ist Pflicht (R0-07 §4.8). Seiteneffektklasse (R0-32 W14): **L** = lesend, **W** = schreibend reversibel, **I** = schreibend mit externer Wirkung.

| Funktion | Kap. 30.3 | Kl. | Berechtigung (R0-07) | Idempotenz | Ergebnis | Stab./Phase |
|---|---|---|---|---|---|---|
| `course_captain_contract_version(): string` | – | L | – | – | `'1.0.0'` | stabil, R1 |
| `course_captain_supports( string $feature ): bool` | – | L | – | – | bool | stabil, R1 |
| `course_captain_get_course_v1( string $course_uid, string $scope = 'public', array $context = [] )` | `get_course_context` | L | `public`: jeder (nur veröffentlicht; Felder `course_uid, title, permalink, status`, R0-32 F6); `structure`: M-03 oder Connector mit Lese-Scope | – | Array \| `WP_Error` | stabil, Pro |
| `course_captain_get_enrollment_v1( int $user_id, string $course_uid, array $context )` | `get_enrollment_status` | L | Akteur = Nutzer, M-11 oder Connector mit Lese-Scope | – | `{state, scopes[], valid_until, grants[{grant_uid, source_type, status}]}` | stabil, Pro |
| `course_captain_get_completion_v1( int $user_id, string $course_uid, array $context )` | `verify_completion` | L | wie oben | – | `{state, completed_at, completion_uid, revision_no, evidence[]}` | stabil, Pro |
| `course_captain_verify_certificate_v1( string $certificate_uid_or_token, array $context = [] )` | `verify_certificate` | L | Token: jeder (Minimaldaten wie R-42); UID: M-18 | – | `{status, course_uid, issued_at, revision_no}` | stabil, Pro |
| `course_captain_preview_grant_v1( array $payload, array $context )` | – | L | wie Grant | – | `{existing[], effective_from, effective_until}` (LB `VoucherGrants::check`, INV-B-057) | stabil, Pro |
| `course_captain_grant_access_v1( array $payload, array $context )` | `enroll_learner` | W | Connector mit Fähigkeit `grant_source` für **eigene** `source_type`, oder Nutzer mit `ccap_manage_grants` | **Pflicht** `idempotency_key` (≤ 64, `^[A-Za-z0-9][A-Za-z0-9._:\-]{7,63}$`); eindeutig je `grant_source_key` (K-01) | `{success, code: created\|duplicate\|updated, grant_uid, status, request_id}` \| `WP_Error` | stabil, Pro (Aufrufer R3) |
| `course_captain_revoke_access_v1( string $source_type, string $source_reference, array $context )` | `revoke_enrollment` | W | nur eigene `source_type` (E-39) bzw. `ccap_manage_grants` | natürlich (Zielzustand) | `{success, code: updated\|unchanged, grant_uids[]}` | stabil, Pro |
| `course_captain_suspend_grant_v1( string $source_type, string $source_reference, array $context )` | – | W | wie revoke | natürlich | wie revoke | stabil, Pro (LB `bootspruefung_suspend_order`, INV-B-108) |
| `course_captain_resume_grant_v1( string $source_type, string $source_reference, array $context )` | – | W | wie revoke; nie für `revoked` (E-29) | natürlich | wie revoke | stabil, Pro (LB `…_reactivate_order`) |
| `course_captain_get_grant_v1( string $source_type, string $source_reference, array $context )` | – | L | wie revoke | – | Grant-Liste | stabil, Pro (INV-B-059) |
| `course_captain_get_events_v1( ?string $after_event_id, array $filter, array $context )` | „nachholbare Abfrage“ | L | Connector mit Abo für die Typen bzw. `ccap_manage_integrations` | – | `{events[], next}` (§5.5) | stabil, Pro |
| `course_captain_resolve_recipient( string $user_ref, string $purpose, array $context )` (ab Stabilisierung `_v1`) | – | L | registrierter Connector mit Fähigkeit `recipient_resolver`-Nutzung | – | `{display_name, email, locale}` nur zur Versandzeit, nie gespeichert (R0-32 W13, V3, E-41) | experimentell, R3 |
| ~~`course_captain_enroll_v1`~~ | – | – | – | – | **entfällt** (D19-5): „Enrollment“ ist der abgeleitete Zustand aus Grants (Kap. 31.2); Einschreiben = `grant_access_v1` mit `source_type` des Aufrufers | – |

**Grant-Payload (`grant_access_v1`):** `course_uid` (Pflicht), `module_uid?` (leer = ganzer Kurs, E-27), `depends_on_course_uid?` (E-26), `user_id` (Pflicht; Gastkauf erst nach Kontozuordnung, E-30), `source_type` (`[a-z0-9_]{1,32}`, = Connector-Quelle), `source_reference` (≤ 191, z. B. `order:123:item:7`, `redemption:<key>`), `source_line?`, `idempotency_key`, `scopes[]` (`view`, `download`, `exam`; Standard alle, E-27), `status` (`active`\|`pending`), `valid_from?`, `valid_until?`, `reason` (Code), `meta` (nur Schlüssel mit Präfix `<source_type>.`, ≤ 4 KB, nie PII/Secrets). Validierung: `ccap_invalid_scope` (auch `module_unknown`, `dependency_invalid`), `ccap_not_found` (Kurs/Nutzer), `ccap_source_not_allowed` (Workflow nur `source_type = workflow`, E-39b), `ccap_idempotency_conflict` (gleicher Key, anderer Nutzer/Kurs, LB INV-B-058).

### 4.2 Lebenszyklus- und Registry-Actions

| Name | Art | Parameter | Zeitpunkt | Stabilität | Phase | Herkunft |
|---|---|---|---|---|---|---|
| `course_captain_loaded_v1` | Action | `Api $api` | `plugins_loaded` 20, nach Migration | stabil | R1 | Kap. 18; LB Filter `bootspruefung_learning_contract` (nur Tests) |
| `course_captain_register_connectors_v1` | Action | `ConnectorRegistry $registry` | direkt nach `loaded_v1` | stabil | Pro (E-38) | Kap. 18.1; Form `form_captain_connectors_v1` (R0-32 F5) |
| `course_captain_register_activity_providers` | Action | `ActivityRegistry $registry` | nach `loaded_v1` | experimentell (stabil ab R2) | R1 | R0-08 ADR-004 |
| `course_captain_register_extensions` | Action | `Extensions $ext` mit Slots `settingsTabs`, `diagnostics`, `readinessChecks`, `responseFields`, `hubTiles`, `grantListColumns`, `accessOffers`, `privacyContributions`, `uninstallManifest`, `tasks`, `placeholders` | `init` 5 | experimentell (Slots einzeln stabil ab R1-28) | R1 | K-03, K-05, K-09, K-11, K-12, K-16, K-17, K-18 |
| `course_captain_admin_menu_built_v1` | Action | `string $parent_slug` | `admin_menu` 20, nach eigenem Menü | stabil | R1 | R0-32 F2(a) |
| `course_captain_event_v1` | Action | `array $envelope` | nach Commit, im selben Request (`shutdown` wenn nötig), **best effort** (§5.4) | stabil | Pro (öffentliche Events, E-38) | Kap. 18 |
| `course_captain_<typ>_v1` (z. B. `course_captain_course_completed_v1`) | Action | `array $envelope` | wie oben, je **stabilem** Event-Typ (Punkte → `_`); experimentelle Typen nur über `course_captain_event_v1` | stabil | Pro (E-38) | Kap. 18 Hooknamen (Alias, §5.2) |
| `course_captain_before_erase_user` | Action | `int $user_id` | vor Eraser-Transaktion | stabil | R1 | `bootspruefung_before_erase_user` |
| `course_captain_connector_status_changed` | Action | `string $connector_id, string $from, string $to, ?string $error_class` | nach Statuswechsel (§6) | stabil | Pro (E-38) | neu (Kap. 31.4) |
| `course_captain_outbox_dead_lettered` | Action | `string $event_id, string $subscriber_id, string $error_class` | Zustellung endgültig gescheitert | stabil | R1 | neu (Kap. 18 Dead-Letter) |
| `course_captain_hold_mode_changed` | Action | `bool $active, string $reason` | Site-Identität geändert/bestätigt | stabil | R1 | R0-16 (E-85) |
| `course_captain_deactivate` | Action | – | Plugin-Deaktivierung, vor Cron-Austrag | stabil | R1 | LB `Plugin::CRON_HOOKS` |
| `course_captain_uninstall` | Action | `array $manifest` | Uninstall nach Bestätigung (E-89) | stabil | R1 | INV-C-026 |

### 4.3 Filter

| Name | Parameter (erster = Rückgabe) | Zeitpunkt | Stabilität | Phase | LB-Herkunft |
|---|---|---|---|---|---|
| `course_captain_can_access` | `bool $allowed, int $user_id, string $course_uid, string $scope, AccessDecision $decision` | jede Zugriffsentscheidung (PolicyService M-02) | stabil; **darf nur einschränken** (`true` bei vorherigem `false` wird ignoriert und protokolliert, E-84) | R1 | `bootspruefung_can_access`, `bootspruefung_user_owns_course` |
| `course_captain_can_download_file` | `bool $allowed, int $user_id, string $media_uid, AccessDecision $decision` | geschützte Auslieferung M-20 | stabil; nur einschränkend | R1 | `bootspruefung_protected_file_allowed` |
| `course_captain_exam_late_tolerance` | `int $seconds (0–600), string $rule_uid` | Versuchsstart (Wert wird eingefroren) | stabil | R1 | `bootspruefung_exam_late_tolerance` |
| `course_captain_exam_starts_per_hour` | `int $limit (40), int $user_id` | Versuchsstart | stabil | R1 | `bootspruefung_exam_starts_per_hour` |
| `course_captain_rate_limits` | `array $buckets` (`bucket => [limit, window_s, fail_mode]`; `fail_mode` für `group_join`, `code_redeem`, `verify`, `privacy` nicht änderbar) | `init` | stabil | R1 | `bootspruefung_voucher_redeem_per_ip`, LoginGuard-Grenzen (Muster) |
| `course_captain_trusted_proxies` | `array $cidrs` | Client-IP-Ermittlung | stabil | R1 | App-Option `bp_learning_mobile` (K-25) |
| `course_captain_named_lock_timeout` | `int $seconds (5), string $name` | DB-Lock | stabil | R1 | `bootspruefung_named_lock_timeout` |
| `course_captain_video_first_party_hosts` | `array $hosts` | Video-Render | stabil | R1 | `bootspruefung_video_first_party_hosts` |
| `course_captain_video_captions_url` | `string $url, string $lesson_uid` | Video-Render | stabil | R1 | `bootspruefung_video_captions_url` |
| `course_captain_external_media_hosts` | `array $hosts` (wirkt im Modus „Klick zum Laden“; Default nach E-45a „immer laden“) | Render | stabil | R1 | `kurswasser_consent_external_hosts` (K-13) |
| `course_captain_protected_file_sendfile` | `false\|'x-sendfile'\|'x-accel-redirect'` | Auslieferung | stabil | R1 | `bootspruefung_protected_file_sendfile` |
| `course_captain_private_dir` | `string $abs_path` | Medienablage | stabil | R1 | `bootspruefung_private_dir` |
| `course_captain_media_allowed_types` | `array $ext_to_mime` (gefährliche Typen werden **nach** dem Filter entfernt) | Upload/Import | stabil | R1 | `bootspruefung_media_allowed_types` |
| `course_captain_import_keep_packages` | `int (2)` | Import-Retention | stabil | R1 | `bootspruefung_import_keep_packages` |
| `course_captain_import_log_days` / `…_unchanged_days` | `int (180 / 14)` | Import-Retention | stabil (Default aus Setting, E-44) | R1 | `bootspruefung_import_log_days`, `…_unchanged_days` |
| `course_captain_grant_retention_years` | `int (10)` | Retention | stabil | R1 | `bootspruefung_entitlement_retention_years` |
| `course_captain_outbox_retention_days` | `int (30)` | Event-Log-Retention ohne Abonnent (E-119) | stabil | Pro | neu |
| `course_captain_internal_outbox_retention_days` | `int (90)` | zugestellte interne Outbox-Einträge (E-116, E-215) | stabil | R1 | neu |
| `course_captain_mail_footer` | `string $footer, string $recipient, string $subject` | Mailversand | stabil | R1 | `bootspruefung_mail_footer` (K-08) |
| `course_captain_account_deletion_protected` | `bool, WP_User` | Kontolöschung (E-43) | stabil | R1 | `bootspruefung_account_deletion_protected` |
| `course_captain_placeholders` | `array $placeholders, string $context` | Textausgabe FAQ/Mail | stabil | R1 | `bootspruefung_faq_tokens` (K-12) |
| `course_captain_defer_after_response` / `course_captain_neutral_response_seconds` | `bool` / `int (2, 0–10)` | Antwort-Timing (kein Konto-Orakel) | intern | R1 | `bootspruefung_defer_after_response`, `…_neutral_response_seconds` |
| `course_captain_expiry_reminder_batch_size` | `int` | Erinnerungslauf | stabil | Pro/R3 (E-24) | `bp_learning_expiry_reminder_batch_size` |

### 4.4 Entfallende oder ersetzte LB-Hooks

| LB-Hook | Ersatz | Grund |
|---|---|---|
| `bootspruefung_entitlements_changed`, `…_voucher_granted`, `…_voucher_revoked` | Events `grant.*`/`enrollment.*` + `course_captain_event_v1` | K-06 neutrale Domain-Events |
| `bootspruefung_suspend_order`, `…_reactivate_order` | `course_captain_suspend_grant_v1`, `…_resume_grant_v1` | INV-B-108 versionierte Commands |
| `bootspruefung_voucher_grants_v1` (Filter liefert Service) | PHP-API §4.1 | Funktionen statt Service-Filter |
| `bootspruefung_voucher_redeem`, `…_redeemed`, `…_redeem_failed`, `…_order_created`, `…_redeem_per_ip`, `bootspruefung_course_voucher_redeemer_v1` | Connector-Fähigkeit `code_redeemer` (R3-04), Rate-Limit-Bucket `code_redeem` | K-14, INV-C-183…186 |
| `bootspruefung_tickets_for_user`, `…_ticket_issued/_redeemed/_cancelled`, `…_practice_region`, `…_practice_offers` | Connector-Registry (R3-07) | INV-C-180/181 |
| `bootspruefung_shop_offer_url` | Slot `accessOffers` (`AccessOfferProvider`) | K-05 |
| `bootspruefung_order_access_target`, `…_require_digital_consent`, `…_retained_order_fields`, `…_member_discount`, `…_cart_*`, `…_hide_coupon_field`, `…_gateway_profiles` | Woo-Adapter (Pro, R3-02); Mitgliederrabatt nach E-25a, Zustimmungs-Gate nach E-33 | K-01/K-02, E-25a, E-33, E-53 |
| `bootspruefung_money_back_*`, `…_guarantor` | entfällt; Kern sendet nur `course.usage_recorded` | E-32 |
| `…_legal_texts_email_pages`, `…_oss_threshold`, `…_purchase_info_lines`, `…_paypal_*` | Woo-Adapter (Pro, R3.1): Verhalten nach E-33 neu gebaut (Clean Room), eigene Adapter-Hooks | E-33 |
| `bootspruefung_login_limits`, `…_login_locked`, `…_hardening`, `…_security_headers` | Härtungsmodul im Free-Kern (R1-45), eigene `course_captain_hardening_*`-Hooks dort; Notausschalter `COURSE_CAPTAIN_HARDENING` | E-10, E-239…E-242, E-260…E-263 |
| `bootspruefung_update_source` | entfällt | E-09 |
| `bootspruefung_mobile_user_allowed` | entfällt | E-05 |
| `bootspruefung_course_relations` | Kursbeziehung `contains` im Kern (R1 Free, `ccap_course_relation`); kein Filter-Ersatz | E-25, E-25a |
| `bootspruefung_sim_offers` | entfällt | exclude_domain_specific |

### 4.5 Shortcodes

| Shortcode | Attribute | Ausgabe | Stab./Phase | LB-Herkunft |
|---|---|---|---|---|
| `[ccap_access course="<uid>" scope="view\|download\|exam\|any" message=""]…[/ccap_access]` | `course` (UUID, Pflicht), `scope` (Standard `any`), `message` (Text, escaped) | Inhalt nur bei M-02; sonst Hinweis + Angebot aus Slot `accessOffers` bzw. Login/Kontakt (INV-B-064); Feeds/Excerpt immer gesperrt | stabil, R1 | `[bootspruefung_zugang kurs funktion hinweis]` (ContentGate.php:74) |
| `[ccap_learning_link label=""]` | `label` | Knopf „Zum Lernbereich“ (`/lernen/`) | stabil, R1 | `[bootspruefung]` (Router.php:125) |
| `[ccap_privacy_request]` | – | Self-Service-Formular (R-48) | stabil, R1 | admin-post `bp_privacy_request`, Woo-Endpunkt „Meine Daten“ (K-07) |

Entfallen: `[bootspruefung_faq]` (Website-FAQ, E-16/INV-D-056). `[bootspruefung_vertrag_widerrufen]` entfällt im Kern; der Widerrufsbutton kommt nach E-33 aus dem Woo-Adapter (Pro, R3.1).

### 4.6 Blocks (`block.json`, `apiVersion: 3`, `textdomain: course-captain`, dynamisch mit `render.php`)

| Block | Attribute (Typ, Standard) | Supports / Kontext | Stab./Phase | LB-Herkunft |
|---|---|---|---|---|
| `course-captain/access-gate` | `courseUid` (string, –), `scope` (enum `view\|download\|exam\|any`, `any`), `message` (string, ``) | InnerBlocks; `html: false`; Ausgabe wie Shortcode | stabil, R1 | Block `bootspruefung/zugang` (`course`, `feature`, `message`; ContentGate.php:215-251) |
| `course-captain/learning-link` | `label` (string), `showWhenLoggedOut` (bool, `true`) | `align`, `color`, `spacing` | stabil, R1 | `[bootspruefung]` |
| `course-captain/course-catalog` | `perPage` (int, 12), `orderBy` (enum `title\|date\|menu_order`, `menu_order`), `showProgress` (bool, `true`), `showOnlyEnrolled` (bool, `false`) | `align`; Gäste sehen nur veröffentlichte Kurse | experimentell, R1 | Kurskatalog-Block (BS-17, INV-E-057/067) |
| `course-captain/privacy-request` | – | – | stabil, R1 | K-07 |
| `course-captain/certificate-verify` | `showHolderName` (bool, aus Datenschutz-Einstellung, nur einschränkend) | ruft R-42 | experimentell, R1 | neu (RG-14) |

Block-Namen sind unveränderlich (§1.5). Editor-Auswahl speichert die **UUID**, nie die Post-ID (R0-08 §1.2).

### 4.7 WP-CLI `wp course-captain …`

Akteur `system:cli` (R0-07 §4.7); `--user=<id>` prüft Caps dieses Nutzers; schreibende Kommandos verlangen `--yes` oder Rückfrage und schreiben Audit; `--format=table|json|csv|yaml` (JSON-Felder sind Vertrag); Exit-Code 0 = ok, 1 = Fehler/`fail`, 2 = Warnung (`warn`) nur bei `backup-check`/`preflight`.

| Kommando | Argumente | Stab./Phase | LB-Herkunft (Inventur C §4.4) |
|---|---|---|---|
| `status` | `[--format]` | stabil, R1 | `status` |
| `backup-check` | `[--format]`; Exit 1 bei `fail` | stabil, R1 | `backup-check` |
| `preflight` | `[--format]` (Update-/Restore-Vorprüfung, R0-16) | stabil, R1 | neu (R0-16) |
| `migrate` | `[--dry-run] [--status] [--step=<id>] [--allow-contract] [--all-sites [--parallel=<1..4>]]`; Downgrade abgelehnt (R0-37 Δ-37-5) | stabil, R1 | `migrate` |
| `maintenance` | `on [--read-only] \| off \| status` | stabil, R1 | neu (R0-16 „B“), LB Maintenance-Guard |
| `identity` | `status \| confirm \| --new` (Site-Identität, Haltemodus) | stabil, R1 | neu (R0-16, E-85) |
| `import` | `<paket> \| resume <id> \| discard <id>` `[--dry-run] [--yes] [--note=<text>] [--restore]` | stabil, R1 | `import` |
| `rollback` | `<import-uid> --reason=<text> [--yes]` | stabil, R1 | `rollback` |
| `rights release` | `--reason=<text> [--dry-run] [--yes]` (ohne `--exam-rules`) | stabil, R1 | `rights release` |
| `grants` | `<user> [--course=<uid>] [--format]` (Ausgabe minimal) | stabil, R1 | `access` |
| `export grants` | `--output=<datei> [--since=<datum>]` (Safe-Export-Writer) | stabil, R1 | `export entitlements <tenant>` (replace) |
| `export rules` | `--output=<datei>` | stabil, R1 | `export access-rules <tenant>` (replace) |
| `outbox` | `status \| list [--status] \| retry <event-id> [--subscriber] \| replay --since=<datum> --subscriber=<id> \| purge --before=<datum>` | stabil, R1 für interne Abonnenten; externe Abonnenten nur Pro | neu (Kap. 18 Outbox, R3-12 Replay) |
| `integrations` | `status [--format]` (R1, nur Partnererkennung, E-185) `\| test <connector>` (Pro) | stabil | neu (Kap. 31.4 „Verbindung prüfen“) |
| `cleanup-sites` | `[--apply]` (nur Multisite, E-07) | bedingt, R1 | `cleanup-sites` |
| `woo reconcile` | `[--apply] [--order=<id>] [--since=<datum>] [--yes]` | stabil, **Pro/R3** (vom Woo-Adapter registriert) | `reconcile` (replace) |
| `woo …` weitere | vom Adapter | R3 | – |

Entfallen: `shop setup`, `chart`, `export course-files`, `export customer-faqs` (exclude_domain_specific). Zählung: 15 R1-Kommandos inkl. `cleanup-sites`, ohne `woo`.

### 4.8 Cron- und Hintergrund-Hooks

Alle Hooks stehen in einer Liste `CRON_HOOKS` und werden bei Deaktivierung/Deinstallation ausgetragen, auch im Action Scheduler (Gruppe `course-captain`), wie LB `Plugin::CRON_HOOKS`. Argumente enthalten nur IDs; der Job lädt den Zustand neu (R0-07 §4.6). Im Haltemodus (E-85) laufen nur interne Aufgaben, keine Zustellung.

| Hook | Takt | Aufgaben / Zweck | Stab./Phase | LB-Herkunft |
|---|---|---|---|---|
| `ccap_hourly` | stündlich (Task-Runner, Registry-Slot `tasks`) | `exam_expire`, `grant_expire`, `group_invites_expire`, `import_retention`, `audit_prune`, `rate_limit_cleanup`, `idempotency_cleanup`, `outbox_retention`, `pending_grant_alert`; je Aufgabe Fehlerzähler (`Cron::recordError`-Muster) | intern (Name stabil) | `bp_learning_hourly` |
| `ccap_outbox_dispatch` | Action Scheduler async bzw. WP-Cron einmalig `+0 s` nach Commit; Sicherheitsnetz in `ccap_hourly` | Zustellung an Abonnenten (§5.4; Free: interne, Pro: zusätzlich externe) | intern | neu (R0-06 E-42) |
| `ccap_task_continue` | einmalig `+60 s`, Argumente `[task, cursor]` | Fortsetzung begrenzter Batches | intern | `bp_learning_expiry_reminder_continue` (Muster) |
| `ccap_migrate` | einmalig, Backoff 15 min | Schema-Migration | intern | `bp_learning_migrate` |
| `ccap_privacy_export_generate` | Action Scheduler async, Argument `[request_id]` | Datenexport | intern | `bp_privacy_export_generate` |
| `ccap_daily` | täglich | Ablauf-Erinnerung (E-24) | intern | **Pro/R3**, `bp_learning_daily` |
| `ccap_woo_reconcile` | einmalig nach Aktivierung, dann stündlich | Bestellabgleich | intern | **R3** (Adapter), `bp_learning_reconcile` (K-09) |
| `ccap_voucher_retry` | stündlich | offene Einlösungen fortsetzen (`get($key)`-Ablauf, R0-36 §3.2) | intern | **R3** (Adapter), `lbvc_retry` |

Entfallen: `bp_mobile_export_generate` (E-05), `update_check`/`media_relocate` (E-09/E-10 bzw. R0-10).

---

## 5. Events, Outbox und Connector-Verträge (SUITE-003, R3-01, R3-12)

**Edition (Beschluss E-38, E-182):**

| Baustein | Free (R1-43 „interne Outbox“) | Pro (R3) |
|---|---|---|
| Envelope v1, Eventkatalog, Erzeugung im selben Commit (§5.1–§5.4 Nr. 1) | ja | ja |
| Zustellung an **interne** Abonnenten (Benachrichtigungen, Audit, Cache K-06) | ja | ja |
| Lokaler Hook `course_captain_event_v1` + Aliase (§5.2) | nein | ja |
| Externe `EventSubscriber`, Connector-Registry, Handshake (§5.7) | nein | ja |
| Pull-API (`get_events_v1`, R-53), Event-Log ohne Abonnent 30 Tage (E-119) | nein | ja |
| Freigabe personenbezogener Event-Klassen je Connector (E-120) | – | ja |
| Partnererkennung (Anzeige) | ja, nur lesend (E-185, E-254, E-256) | mit Aktionen |


### 5.1 Event-Envelope v1 (ADR-008) – Konfliktauflösung Kap. 18 vs. Kap. 30.3

**Festlegung:** Es gibt genau **ein** Envelope v1. Basis ist die Struktur aus **Kap. 30.3**; aus **Kap. 18** kommen `idempotency_key`, `user_ref` und die Hooknamen (als Aliase); aus dem Suite-Abgleich (R0-32 §6, W5, W13) kommen `privacy_tags` und `causation_id`.

**Begründung:**
1. Kap. 30.3 trennt Kopf und fachliche Nutzlast (`payload`), benennt Typ (`event_type`) und Schemaversion getrennt und führt die **Objektversion** – nötig für Reihenfolge, Replay und Duplikaterkennung (Kap. 31.3 Nr. 2). Kap. 18 vermischt Kopf- und Fachfelder (`course_id`, `source`, `reference_id`) und ist an ein Ereignis je Hook gebunden.
2. Kap. 30.3 ist der jüngere, ausdrücklich suiteweite Vertrag (SUITE-003); seine Namen liegen näher an Workflow (`schema_version`, `object_type/object_id`) und Review (`event_type`, `object_type/object_id`) → weniger Abbildungsaufwand (§5.6).
3. Kap. 18 liefert zwei Felder, die 30.3 fehlen und die Kap. 31.3 verlangt: einen **fachlichen** Idempotenzschlüssel (stabil auch bei erneuter Erzeugung eines Events, z. B. Backfill) und eine Personenreferenz ohne Klardaten.
4. Beiden fehlen Datenschutzklasse und Kausalität (R0-32 Befund); ohne `causation_id` sind Schleifen Workflow → Course → Workflow nicht erkennbar (R0-32 W5).
5. `source_object_id` (30.3) wird `object_id`, weil „source“ im Grant-Modell bereits die **Zugangsquelle** bezeichnet (Kap. 31.2 `source_plugin/source_type`); die Grant-Quelle aus Kap. 18 (`source`, `reference_id`) steht im Payload der `grant.*`-Events.
6. `event_version` (Kap. 18) und `schema_version` (30.3) bezeichnen dasselbe; es bleibt `schema_version`, getrennt von `contract_version` (Gesamtvertrag V-01).

| Feld | Typ | Pflicht | Bedeutung | aus |
|---|---|---|---|---|
| `event_id` | UUID v4 | ja | eindeutig je Erzeugung; = `ccap_outbox_event.event_uuid`; Consumer deduplizieren hierüber | 18, 30.3 |
| `event_type` | string `^[a-z]+(\.[a-z_]+)+$`, ≤ 64 | ja | §5.3 | 30.3 |
| `schema_version` | `MAJOR.MINOR` | ja | Payload-Schema dieses Typs | 30.3 (18: `event_version`) |
| `contract_version` | SemVer | ja | `COURSE_CAPTAIN_CONTRACT_VERSION` bei Erzeugung | neu (Workflow `contract_version`) |
| `source_plugin` / `source_version` | string | ja | `course-captain` / Plugin-Version | Workflow §16.2 |
| `site_id` | int | ja | `get_current_blog_id()`; nur lokal gültig (E-07) | 18, 30.3 |
| `occurred_at` | RFC 3339 UTC, ms | ja | Zeitpunkt des fachlichen Commits | 18, 30.3 |
| `object_type` | enum `grant\|enrollment\|lesson\|exam_session\|course_completion\|certificate\|seat\|course` | ja | betroffenes Aggregat | 30.3 (`source_object_id`) |
| `object_id` | string (UUID) | ja | `uid` des Aggregats | 30.3 |
| `object_version` | int ≥ 1 | ja | monoton je Objekt (Grant-Revision, `evaluation_no`, Zertifikat-`revision_no` …); Consumer verwerfen ältere Stände | 30.3 |
| `course_uid` | UUID \| null | ja | Kursbezug (Filterfeld) | 18 (`course_id`) |
| `user_ref` | `user:<id>` \| null | ja | betroffene Person, pseudonym; Klardaten nur über Resolver `course_captain_resolve_recipient` (§4.1) | 18 |
| `actor_type` / `actor_ref` | `user\|system\|connector` / `user:<id>`, `system:cron`, `connector:<id>` | ja | Auslöser; `actor_ref` = null, wenn nicht zulässig | 30.3 (`actor_id`) |
| `correlation_id` | UUID | ja | Vorgang über Schritte hinweg (Kap. 31.3 Nr. 4) | 18, 30.3 |
| `causation_id` | UUID \| null | ja | `event_id`/Run-ID, die dieses Ereignis ausgelöst hat (Schleifenschutz) | R0-32 W5 (`causal_run_id`) |
| `idempotency_key` | string ≤ 191 | ja | fachlicher Schlüssel, z. B. `course.completed:<completion_uid>:<object_version>`; gleiche fachliche Tatsache → gleicher Schlüssel | 18 |
| `privacy_tags` | string[] | ja | aus `personal_ref`, `learning_progress`, `assessment_result`, `access_state`, `certificate`; steuert Abo-Freigabe (E-120, Pro) | Workflow `privacy_tags` |
| `payload` | object | ja | nur IDs, Status, Zeitpunkte, Zahlen; **nie** WP-User-Objekt, E-Mail, Name, Antworten, Tokens, Zahlungsdaten, Secrets | 18, 30.3 |

**Beispiel:**

```json
{
  "event_id": "8c1f0a8e-5b5e-4c8e-9a43-2f1d8b7c6e11",
  "event_type": "course.completed",
  "schema_version": "1.0",
  "contract_version": "1.0.0",
  "source_plugin": "course-captain",
  "source_version": "1.0.0",
  "site_id": 1,
  "occurred_at": "2026-11-16T09:30:12.345Z",
  "object_type": "course_completion",
  "object_id": "0f6d1c7e-2a1b-4d8e-8f00-6c2b9d1e4a77",
  "object_version": 1,
  "course_uid": "3b2a6f1e-9c4d-4e5f-8a7b-1c2d3e4f5a6b",
  "user_ref": "user:42",
  "actor_type": "user",
  "actor_ref": "user:42",
  "correlation_id": "c0a8012e-7f3b-4a1c-9d2e-5b6a7c8d9e0f",
  "causation_id": "1e2d3c4b-5a69-4788-9a0b-c1d2e3f4a5b6",
  "idempotency_key": "course.completed:0f6d1c7e-2a1b-4d8e-8f00-6c2b9d1e4a77:1",
  "privacy_tags": ["personal_ref", "learning_progress"],
  "payload": {
    "completed_at": "2026-11-16T09:30:12Z",
    "course_revision_no": 3,
    "rule_version": 1,
    "experience_id": "course-captain:1:course_completion:0f6d1c7e-2a1b-4d8e-8f00-6c2b9d1e4a77",
    "evidence": [{ "type": "exam_session", "ref": "9a8b7c6d-5e4f-4a3b-8c2d-1e0f9a8b7c6d" }]
  }
}
```

**Schema-Evolution (R3-12):** Minor (`1.0 → 1.1`) nur additiv; Consumer MÜSSEN unbekannte Felder ignorieren. Major (`2.0`) = paralleler Typ-Major: Der Abonnent wählt in seiner Subscription den Major; Course erzeugt beide, bis die Frist (§1.5) abläuft. Envelope-Felder selbst ändern sich nur mit dem Vertrags-Major.

### 5.2 Abbildung der Altnamen

| Kap. 18 (Hook) | Kap. 30.2 | v1 `event_type` | lokaler Alias-Hook |
|---|---|---|---|
| `course_captain_enrollment_created_v1` | `enrollment.created` | `enrollment.created` | `course_captain_enrollment_created_v1` |
| `course_captain_enrollment_revoked_v1` | `enrollment.revoked` | `enrollment.revoked` (+ `enrollment.expired`, `.suspended`, `.resumed`) | `course_captain_enrollment_revoked_v1` |
| `course_captain_lesson_completed_v1` | `lesson.completed` | `lesson.completed` | `course_captain_lesson_completed_v1` |
| `course_captain_assessment_submitted_v1` | `assessment.submitted` | `exam.submitted` | `course_captain_exam_submitted_v1` |
| `course_captain_assessment_passed_v1` | `assessment.passed` | `exam.passed` | `course_captain_exam_passed_v1` |
| – | `assessment.failed` | `exam.failed` | `course_captain_exam_failed_v1` |
| `course_captain_course_completed_v1` | `course.completed` | `course.completed` | `course_captain_course_completed_v1` |
| `course_captain_certificate_issued_v1` | `certificate.issued` | `certificate.issued` | `course_captain_certificate_issued_v1` |
| – | `certificate.revoked` | `certificate.revoked` | `course_captain_certificate_revoked_v1` |

`assessment.*` heißt `exam.*`, weil auch bewertete Quizze über denselben Versuchsautomaten laufen (E-68) und `payload.mode` (`exam`\|`quiz`) unterscheidet; der Begriff deckt sich mit Routen (`exam-sessions`) und Fehlercodes (`ccap_exam_*`).

### 5.3 Eventkatalog v1

Alle Events entstehen **im selben Commit** wie die Fachänderung (Outbox, §5.4). Gemeinsame Payload-Felder werden nicht wiederholt; `object_*` siehe Spalte „Objekt“.

| `event_type` | Auslöser (nach Commit von …) | Objekt | Payload (zusätzlich) | `privacy_tags` | Stabilität / Phase |
|---|---|---|---|---|---|
| `grant.created` | neuer Grant (jede Quelle) | grant | `grant_uid, source_type, source_reference, status, scopes[], valid_from, valid_until, reason` | `personal_ref, access_state` | stabil, R1 |
| `grant.activated` | `pending → active` | grant | `from, reason` | dto. | stabil, R1 |
| `grant.suspended` / `grant.resumed` | `active ↔ suspended` | grant | `from, reason` | dto. | stabil, R1 |
| `grant.extended` | `valid_until` geändert (neue Revision) | grant | `valid_until_old, valid_until` | dto. | stabil, R1 |
| `grant.expired` | Ablauf erreicht (Cron, kosmetisch, R0-07 §4.6) | grant | `expired_at` | dto. | stabil, R1 |
| `grant.revoked` | Widerruf (Refund, Storno, manuell, Platzverlust) | grant | `from, reason, source_reference` | dto. | stabil, R1 |
| `grant.failed` | Grant konnte nicht aktiviert werden (z. B. Identität ungeklärt) | grant | `reason` | dto. | stabil, R1 |
| `enrollment.created` | erster effektiver Zugang zu einem Kurs (aus ≥ 1 aktivem Grant) | enrollment | `scopes[], granted_by: [grant_uid]` | dto. | stabil, R1 |
| `enrollment.suspended` / `enrollment.resumed` | alle verbleibenden Grants ausgesetzt / wieder aktiv | enrollment | `reason` | dto. | stabil, R1 |
| `enrollment.expired` / `enrollment.revoked` | letzter effektiver Zugang endet (Ablauf bzw. Widerruf); bei mehreren Grants erst nach dem letzten (Kap. 31.2) | enrollment | `reason, last_grant_uid` | dto. | stabil, R1 |
| `lesson.completed` | Activity-Abschluss (R0-08 §5.1) | lesson | `lesson_uid, activity_uid, reason_code, rule_version, content_hash` | `personal_ref, learning_progress` | stabil, R1 |
| `lesson.completion_reset` | Rücknahme `manual_confirm` durch Lernenden oder Reset durch Kursverwaltung (E-70) | lesson | `lesson_uid, activity_uid, by: learner\|staff` | dto. | stabil, R1 |
| `exam.submitted` | Abgabe oder Fristablauf → `submitted` | exam_session | `session_uid, mode, rule_uid, rule_version, end_reason: submitted\|expired` | `personal_ref, assessment_result` | stabil, R1 |
| `exam.passed` / `exam.failed` | Auswertung (`evaluated`), genau eines von beiden je `evaluation_no` | exam_session | `session_uid, mode, points_milli, max_points_milli, evaluation_no, evidence_eligible` | dto. | stabil, R1 |
| `exam.reevaluated` | Nachbewertung (E-74) mit neuer `evaluation_no` | exam_session | `evaluation_no, passed_old, passed` | dto. | experimentell, R1 (Datenvertrag) |
| `course.completed` | Kursabschluss nach Kursregel | course_completion | `completed_at, course_revision_no, rule_version, experience_id, evidence[]` | `personal_ref, learning_progress` | stabil, R1 |
| `course.completion_reset` | Abschluss durch Kursverwaltung zurückgesetzt (Audit) – Widerrufsereignis für Review (R0-32 V6) | course_completion | `reason` | dto. | stabil, R1 |
| `course.usage_recorded` | neutrales Nutzungsereignis (E-32): erster Zugriff, Fortschrittsschwelle 25/50/75 %, erster Download je Datei | course | `kind: first_access\|progress_milestone\|file_download, value, media_uid?` | `personal_ref, learning_progress` | stabil, R1 |
| `certificate.issued` | Ausstellung (R0-08 §7.2) | certificate | `certificate_uid, revision_no, evidence_type, evidence_ref, valid_until` | `personal_ref, certificate` | stabil, R1 |
| `certificate.corrected` | neue Revision (`supersedes`) | certificate | `revision_no, supersedes_revision_no` | dto. | stabil, R1 |
| `certificate.revoked` | Widerruf (Akteur, Grund) | certificate | `reason` | dto. | stabil, R1 |
| `seat.claimed` / `seat.released` | Gruppenplatz belegt / frei (E-17) | seat | `group_uid, seat_uid, reason` | `personal_ref, access_state` | experimentell, R1 |

**Nicht als Event (Datensparsamkeit):** einzelne Lernantworten, Smarttrainer-Stände, Prüfungsstart, Antworten in Prüfungen, Video-Positionen (Workflow-Muster W17: „Fortschritt nicht je Item“). `exam.started` kann ein späteres Minor ergänzen, wenn ein Rezept es verlangt.

**Kanonisches Zugangs-Event (R0-32 W9):** Für Onboarding-Rezepte gilt `enrollment.created` (einmal je effektivem Zugang), **nicht** `grant.created` (kann je Quelle mehrfach auftreten).

### 5.4 Outbox, Zustellung und Idempotenz (ADR-006)

1. **Gleiche Transaktion:** Fachänderung + `ccap_grant_event`/Revision + `ccap_outbox_event` in **einem** Commit (R0-06 §4.8). Kein Event ohne Zustand, kein Zustand ohne Event.
2. **Event-Log:** `ccap_outbox_event` speichert jedes Event **einmal**. **Free:** nur, wenn mindestens ein interner Abonnent den Typ abonniert; zugestellte Einträge 90 Tage (`course_captain_internal_outbox_retention_days`, E-116/E-215). **Pro:** unabhängig von Abonnenten, Aufbewahrung ohne Abonnent `course_captain_outbox_retention_days` (Standard 30 Tage, E-119); das ermöglicht Pull (`get_events_v1`, R-53), Backfill und Replay. Offene Zustellungen höchstens 30 Tage, dann Dead-Letter: Status `dead`, `last_error_class = retention` (E-258; Grundfelder wie R0-06 §4.6 und R0-34 §2.1).
3. **Zustellung je Abonnent:** neue Tabelle `ccap_outbox_delivery` (`event_uuid`, `subscriber_id`, `status` `pending/dispatching/delivered/failed/dead/skipped`, `attempts`, `next_attempt_at`, `locked_until`, `lock_owner`, `last_error_class`, `last_error`, `delivered_at`; `UNIQUE (event_uuid, subscriber_id)`). Zustellstatus und Versuche wandern damit aus `ccap_outbox_event` (Delta R0-06, §8). Zeilen entstehen beim Commit nur für **aktive** Abonnenten mit passendem Typ und freigegebenen `privacy_tags` (interne Abonnenten gelten als freigegeben; externe nach E-120).
4. **Dispatcher** `ccap_outbox_dispatch`: Lease über `locked_until` (60 s) + `lock_owner`; Zustellung in `object_version`-Reihenfolge je (Abonnent, Objekt); höchstens 100 Zustellungen je Lauf. Backoff 1 min, 5 min, 15 min, 1 h, 6 h, 24 h, 24 h, 24 h → nach **8** Versuchen `dead` (Form-Muster R0-32 F5), Action `course_captain_outbox_dead_lettered`, Admin-Hinweis. Permanente Fehler (`ccap_validation_failed`, `ccap_incompatible_contract`, `ccap_forbidden`) gehen sofort auf `failed` (Kap. 31.3 Nr. 5).
5. **Garantie:** at-least-once je Abonnent. Consumer MÜSSEN über `event_id` deduplizieren (Kap. 18) und `object_version` prüfen. `idempotency_key` erlaubt Dedupe auch nach erneuter Erzeugung (Backfill).
6. **Lokaler Hook** `course_captain_event_v1` (+ Alias je Typ, nur Pro): feuert **nach Commit im selben Request**, at-most-once, **keine Zustellgarantie** (Kap. 30.3 „einmaliges `do_action` ist keine Zustellgarantie“). Für Caches/UI. Zuverlässige Consumer registrieren sich als `EventSubscriber` (§5.7, Pro). Die Cache-Invalidierung des Kerns läuft synchron in `afterCommit` (R0-21 §3.7), nicht über diesen Hook.
7. **Replay:** `wp course-captain outbox retry|replay` bzw. Admin-Aktion setzt Zustellungen zurück auf `pending` mit **gleicher** `event_id`; nie neue IDs für dieselbe Tatsache. Replay prüft Rechte (`ccap_manage_integrations`) und Abo-Freigabe neu. Bereits **zugestellte** Events (`delivered`) werden nur mit ausdrücklichem Flag (CLI `--include-delivered`, Admin-Häkchen), Pflichtbegründung und Audit erneut gesendet (**E-311**).
8. **Haltemodus / Ausgangsmodus:** Bei geänderter Site-Identität (E-85) und bei `COURSE_CAPTAIN_OUTBOUND = catch|off` (E-86) entstehen Events weiter, Zustellungen bleiben `pending` bzw. `skipped`; nach Restore werden Zustellungen mit `created_at` vor dem Erkennungszeitpunkt dem Admin zur Entscheidung vorgelegt, Standard „verwerfen“ (E-88, R0-16 Recovery-Schritt 9).
9. **Eingang:** Webhooks und Partner-Callbacks deduplizieren über `ccap_inbox_event (connector_id, event_id)`; Replay → `202 duplicate` ohne Wirkung (R0-07 §4.8).
10. **REST-Idempotenz** (§2.1) und **Grant-Idempotenz** (`grant_source_key` UNIQUE, K-01) sind getrennte Ebenen; ein Retry mit gleichem Schlüssel erzeugt weder neuen Grant noch neues Event.

### 5.5 Pull-Abfrage (nachholbare Abfrage, Pro)

`course_captain_get_events_v1( $after_event_id, [ 'types' => [...], 'course_uid' => ?, 'limit' => ≤500 ] )` liefert Events in Commit-Reihenfolge (Sortierschlüssel `ccap_outbox_event.id`, nicht `occurred_at`) ab dem Cursor; Cursor älter als die Retention → `ccap_not_found` mit `reason: cursor_expired`, der Consumer muss per Read-API neu abgleichen. Gleiche Filter auf `privacy_tags` wie bei Zustellung.

### 5.6 Abbildung auf Partner-Envelopes (nur Dokumentation, Partnernamen sind Entwürfe)

| v1 | Workflow §16.2 | Review §7.4 |
|---|---|---|
| `event_id` | `event_id` | `event_id` |
| `event_type` | `event_key` | `event_type` |
| `schema_version` / `contract_version` | `schema_version` / `contract_version` | `contract` |
| `source_plugin` / `source_version` | `source_plugin` / `source_version` | `source` |
| `occurred_at` | `occurred_at_utc` | `occurred_at` |
| `object_type` / `object_id` | `object_type` / `object_ref` | `object_type` / `object_id` |
| `user_ref` | – (Resolver) | `recipient_ref` |
| `actor_ref` | `actor_user_id` | – |
| `correlation_id` / `causation_id` | `correlation_id` / `causal_run_id` | – |
| `privacy_tags` | `privacy_tags` | `consent`/`communication_allowed` **nicht** von Course (E-41) |
| `payload.experience_id` (`course.completed`) | – | `experience_id` |

Die Abbildung ist Aufgabe des jeweiligen Connectors (Course Pro oder separates Plugin, E-40), nicht des Kerns.

### 5.7 Connector-Registry (Pro, Beschluss E-38)

```php
<?php
declare(strict_types=1);

namespace CourseCaptain\Connector\Contract;

interface ConnectorRegistry
{
    /** Doppelte ID ist ein Fehler (WP_Error ccap_validation_failed), kein stilles Überschreiben. */
    public function register(Connector $connector): void;
    public function get(string $id): ?Connector;
    /** @return array<string, Connector> */
    public function all(): array;
    public function status(string $id): IntegrationStatus;
}

interface Connector
{
    /** Stabile ID [a-z0-9-]{1,64}, z. B. "woocommerce", "voucher-captain", "workflow-captain". */
    public function id(): string;
    public function label(): string;
    /** SemVer des Connector-Codes. */
    public function version(): string;
    /** Unterstützter Course-Vertragsbereich, z. B. ">=1.0 <2.0" (§1.6). */
    public function contractRange(): string;
    /** Partnererkennung nur über dokumentierte Versions-APIs (Kap. 33). */
    public function detectPartner(): PartnerDetection;
    /** @return list<string> aus: event_subscriber, grant_source, code_redeemer, recipient_resolver, context_provider, access_offer_provider */
    public function capabilities(): array;
    /** Pflichteinstellungen fehlen? Verbindungstest ohne personenbezogene Daten. */
    public function healthCheck(): HealthResult;
    /** Beiträge zu Export/Eraser/Uninstall (K-18). */
    public function privacyContributions(): array;
}

interface EventSubscriber
{
    /** @return array<string, string> event_type => akzeptierter schema-Major, z. B. ["course.completed" => "1"] */
    public function subscribedEvents(): array;
    /** @return list<string> privacy_tags, die dieser Abonnent erhalten darf (Betreiberfreigabe je Klasse und Connector, E-120) */
    public function requiredPrivacyTags(): array;
    /** Timeout ≤ 10 s; idempotent über $envelope['event_id']. */
    public function handle(array $envelope, DeliveryContext $context): DeliveryResult;
}

/** DeliveryResult::delivered() | ::retry(string $errorClass, ?int $retryAfter) | ::fail(string $errorClass, string $message) | ::skip(string $reason) */
```

**Regeln:** Registry und Connectoren gibt es nur mit Pro (E-38); Connectoren zu Form/Workflow/Review erst, wenn die Partner-Registries existieren (E-40). Registrierung nur in `course_captain_register_connectors_v1`; ein Connector ist erst nach **expliziter Betreiberfreigabe** aktiv (Kap. 33, Zustand `connected`); Deaktivieren nimmt nichts zurück (R0-32 V4); bestehende Grants der Quelle bleiben. Connectoren lesen nie Course-Tabellen (Kap. 30.3), sondern nur PHP-API/Events. Secrets nach E-87. Ausgehende Webhooks (Pro) sind ein mitgelieferter `EventSubscriber` mit HMAC `X-CC-Signature: t=<unix>,v1=<hex(hmac_sha256(secret, t + "." + body))>`, Header `X-CC-Event-Id`, `X-CC-Delivery-Id`, Toleranz ±300 s, Schlüsselrotation mit zwei gültigen Secrets, Egress-Allowlist, kein Redirect-Folgen (Kap. 18.1, 30.3 Transport).

### 5.8 GrantSourceAdapter (K-02)

```php
<?php
declare(strict_types=1);

namespace CourseCaptain\Grant\Contract;

/** Liest eine externe Zugangsquelle (Woo-Bestellung, Voucher-Einlösung, Fake). Nie schreibend. */
interface GrantSourceAdapter
{
    /** = source_type der erzeugten Grants, [a-z0-9_]{1,32}, z. B. "woocommerce", "voucher", "fake". */
    public function sourceType(): string;
    public function contractVersion(): string;
    /** Aktueller Zustand eines Quellobjekts; null = existiert nicht (mehr). */
    public function snapshot(string $sourceReference): ?SourceSnapshot;
    /** Geänderte Quellobjekte seit Cursor (Backfill, Reconcile); begrenzt. */
    public function list(SourceCursor $cursor, int $limit): SourcePage;
}

/** Übersetzt einen Quellzustand in Soll-Grants. Reine Funktion, testbar ohne Shop. */
interface SourceStatusMapper
{
    public function desiredGrants(SourceSnapshot $snapshot): DesiredGrantSet;
}
```

| Werteobjekt | Felder |
|---|---|
| `SourceSnapshot` | `sourceType`, `sourceReference`, `sourceStatus` (roh, z. B. `processing`), `sourceRevision` (Version/Hash des Quellobjekts), `observedAt`, `learner` (`userId\|null`, `identityState: verified\|unverified`), `lines[]` (`lineRef`, `frozenTargets[]` (`courseUid`, `moduleUid\|0`, `scopes[]`, `dependsOnCourseUid?`), `seats`, `validFrom?`, `validUntil?`, `refundState: none\|partial\|full`), `consentState?` (E-33), `disputeState: none\|open\|won\|lost` (E-33, R0-40 Δ40-5) |
| `DesiredGrant` | `grantSourceKey` (= `source_type + source_id + source_line + source_revision + course + module`, K-01, E-27), `courseUid`, `moduleUid\|0`, `dependsOnCourseUid?`, `userId`, `scopes[]`, `targetStatus` (`active\|pending\|suspended\|revoked`), `reasonCode`, `validFrom?`, `validUntil?` |
| `DesiredGrantSet` | `grants[]`, `noChange: bool` (unbekannter Quellstatus ⇒ `noChange = true`, **nichts ändern**, K-02), `warnings[]` (z. B. `no_account`, einmal je Anfrage protokolliert, LB T243) |

**GrantReconciler (Kern, R1):** vergleicht `DesiredGrantSet` mit `ccap_grant` (Quelle + Referenz) und wendet Unterschiede über den GrantService an (CAS, `ccap_grant_event`, Outbox im selben Commit). Regeln: manuelle Entscheidung (`meta.manual`) wird nur durch `revoked` übersteuert (LB `EntitlementPlanner.php:150-154`); `revoked → active` nie automatisch (E-29, neue Revision statt Wiederaufleben); `expired → active` nur über `extend` (LB); Widerruf wirkt nur auf Grants **dieser** Quelle (Kap. 31.2). Auslöser: Partner-Hook im Adapter (Push) → `snapshot()` → Reconcile; Sicherheitsnetz `list()` im Task `ccap_woo_reconcile` bzw. `ccap_voucher_retry` (Pull). R1 liefert `FakeSourceAdapter` + Mapper als Testquelle (INV-B-169, R0-05 GM „Grant-API Idempotenz“). Echte Adapter (Woo, Voucher, Membership, Workflow nach E-39/E-39b) sind Pro. Registrierung als Connector-Fähigkeit `grant_source`; der Adapter darf `grant_access_v1` nur mit seiner `sourceType()` aufrufen (`ccap_source_not_allowed`).

---

## 6. Integrationsstatus (eine Liste; Konflikt Kap. 30.3 vs. Kap. 33 aufgelöst)

**Festlegung:** Es gilt die Liste aus **Kap. 33** mit acht Zuständen. **Edition:** Free erkennt und zeigt nur `not_installed`, `inactive`, `active_incompatible` und `active_compatible` (Unterseite „Integrationen“, nur lesend, E-185/E-254/E-256); `needs_configuration`, `connected`, `degraded`, `failed` gibt es nur mit Pro (E-38). **Begründung:** Kap. 33 ist eine ausdrückliche **Produktentscheidung** des Auftraggebers, jünger als Kap. 30.3 und eine Obermenge: Sie unterscheidet „aktiv und kompatibel, aber nicht freigegeben“ (`active_compatible`) von „verbunden“ (`connected`) – genau die Lücke, die Kap. 33 schließt („Erkennung ist kein Ersatz für die explizite Freigabe“). Der Zustand `incompatible` aus 30.3 wird `active_incompatible` (ein **inaktives** Plugin kann nicht sinnvoll als inkompatibel geprüft werden). `incompatible_contract` (Kap. 18, Partner Form/Review/Affiliate) ist die **Fehlerklasse** zu diesem Zustand, kein eigener Zustand.

| Zustand | Bedeutung | Erkennung | Datenaustausch | Admin-Anzeige (Kap. 31.4) |
|---|---|---|---|---|
| `not_installed` | Partner-Plugin nicht vorhanden | dokumentierte Versions-API fehlt | nein | Erklärung, Hilfe-Link; **keine** Installation (K-23) |
| `inactive` | installiert, nicht aktiv | `get_plugins()` kennt es, API fehlt | nein | „Partner deaktiviert“; bestehende Grants bleiben |
| `active_incompatible` | aktiv, Version oder Vertrag außerhalb `contractRange`/Mindestversion | Versions-API, Handshake §1.6 | nein, Connector wird nie aufgerufen | gefundene vs. benötigte Version, Fehlerklasse `incompatible_contract` |
| `active_compatible` | aktiv und kompatibel, vom Betreiber **nicht** freigegeben | Handshake ok, keine Freigabe | nein (auch keine Events/Abos) | „Verbindung einrichten“ (Opt-in je Site) |
| `needs_configuration` | freigegeben, Pflichtangaben fehlen (Mapping, Secret) | `healthCheck()` | nein | fehlende Angaben |
| `connected` | freigegeben, letzter Test bzw. letzte Zustellung erfolgreich | `healthCheck()`, Zustellstatistik | ja | Version, Vertrag, letzte erfolgreiche Kommunikation, Test-/Retry-Aktion |
| `degraded` | verbunden, aber temporäre Fehler (Retries offen, Lizenz für Pro-Adapter inaktiv, Haltemodus) | Zustellungen `failed`/Backoff, `reason` | eingeschränkt; Events bleiben `pending` | letzte Fehlerklasse, offene Zustellungen, nächster Versuch |
| `failed` | dauerhafter Fehler (≥ 3 Dead-Letter innerhalb 1 h, nicht schon beim ersten – **E-311**; Authentifizierung; permanente Ablehnung) | `dead`-Zustellungen, `healthCheck()` | nein, bis Admin-Aktion | Fehlerklasse, Fehler-ID, Reparaturweg |

**Fehlerklassen** (`last_error_class`, datensparsam, ohne Payload/Secrets): `incompatible_contract`, `not_configured`, `auth_failed`, `timeout`, `network`, `rate_limited`, `partner_error`, `validation`, `license_inactive`, `hold_mode`, `internal`, `retention` (offene Zustellung nach 30 Tagen, E-258; Status `dead`).

**Übergänge:** `not_installed ↔ inactive ↔ (active_incompatible | active_compatible)` aus der Erkennung (bei jedem Admin-Seitenaufruf und stündlich); `active_compatible → needs_configuration → connected` nur durch Betreiberaktion; `connected ↔ degraded` automatisch; `degraded → failed` nach 3 Dead-Lettern innerhalb 1 h (E-311) oder Auth-Fehler; ein einzelner Dead-Letter hält den Provider auf `degraded`; `failed → connected` nur nach erfolgreichem Test. Plugin-Update des Partners → Neuprüfung; Wechsel nach `active_incompatible` pausiert nur die betroffenen Zustellungen (R0-32 W15). Jeder Wechsel feuert `course_captain_connector_status_changed` und schreibt Audit.

**Redaktionelle Folge:** Kap. 30.3 „Integrationszustände“ auf diese Liste verweisen; ENTSCHEIDUNGEN §7 nennt für diesen Widerspruch R0-34 – die Auflösung liegt jetzt hier, R0-34 übernimmt nur UX/Diagnose.

---

## 7. Entscheidungen (beschlossen)

| Nr. | Frage | Beschluss |
|---|---|---|
| D19-1 | Präfix der öffentlichen Hooks | Hooks/Funktionen `course_captain_*` (ENTSCHEIDUNGEN §19, technisch festgelegt); interne Kennungen nach E-207 `ccap_*` (§1.2) |
| D19-2 | Deprecation-Frist | **E-118:** ≥ 2 Minor-Releases **und** ≥ 6 Monate; Major-Namespace 12 Monate parallel; Sicherheitsausnahme mit Changelog |
| D19-3 | Event-Log ohne Abonnenten | **E-119:** 30 Tage – gilt nur mit Pro (E-38); Free speichert nur die interne Outbox |
| D19-4 | Freigabe personenbezogener Event-Klassen | **E-120:** eigene Betreiberfreigabe je Klasse und Connector (Pro) |
| D19-5 | `course_captain_enroll_v1` streichen | gestrichen (ENTSCHEIDUNGEN §19); Zugang nur über `grant_access_v1` |
| D19-6 | Fehlerformat-Feinschnitt | Codes klein mit `ccap_`-Präfix (E-285), `application/json` (ENTSCHEIDUNGEN §19, präzisiert E-06) |

**Weitere Beschlüsse:** E-38 (Edition §5), E-39/E-39b (Workflow-Schreibaktionen im Pro-Connector), E-37 (keine Affiliate-Meldung), E-30 (Kontopflicht), E-84 (Zugriffsfilter nur einschränkend), E-85/E-86/E-88 (Haltemodus/Ausgang), E-87 (Secrets), E-40 (Ort der Suite-Connectoren), E-41 (Empfänger-Resolver).

---

## 8. Deltas zu anderen Dokumenten (technisch, mit deren Freigabe einzuarbeiten)

| Dokument | Delta | Status |
|---|---|---|
| R0-06 | `ccap_request_idempotency` ist **nicht mehr bedingt** (Idempotency-Key-Header beschlossen, §2.1). Neue Tabelle `ccap_outbox_delivery` (§5.4 Nr. 3); `ccap_outbox_event` verliert `status/attempts/next_attempt_at/locked_until/lock_owner/last_error/delivered_at` und erhält `object_type`, `object_id char(36)`, `object_version`, `course_uid`, `user_ref`, `idempotency_key`, `privacy_tags`, `causation_id`, `contract_version` (bzw. Envelope als JSON + indizierte Spalten `event_type`, `object_id`, `created_at`). | eingearbeitet (R0-06 §4.6, §4.7) |
| R0-05 | `ccap_idempotency_key_reused` → `ccap_idempotency_conflict`; 41. Prüfungsstart → `ccap_rate_limited` mit `details.bucket = exam_start` (bereits so), Antwort-Limit ebenso `bucket = answer`. | an R0-05 übergeben |
| R0-07 | RBAC-022 (fremdes Frage-Token): `422 ccap_invalid_token` (`reason: other_account`) statt 403 – deckt sich mit R0-05 GM-024 und LB (`RuntimeException` → 422). `ccap_account_locked` fest **403** (nicht 423). Webhook-Signaturheader `X-CC-Signature` (§5.7). | an R0-07 übergeben |
| R0-08 §6.6 | `ccap_exam_finished` → `ccap_exam_already_submitted` bzw. `ccap_exam_aborted` (LB-App-Parität); `ccap_exam_start_rate_limited` und `ccap_answer_rate_limited` → `ccap_rate_limited` mit `bucket`; übrige Codes unverändert übernommen. | an R0-08 übergeben |
| R0-16 | CLI-Namen `maintenance`, `preflight`, `backup-check`, `identity`, `export grants`, `outbox` bestätigt (§4.7). | an R0-16 übergeben |
| konzept.md | Kap. 18: Envelope-Feldliste durch Verweis auf R0-19 §5.1 ersetzen, `course_captain_enroll_v1` streichen (D19-5), Fehlercodes auf §3 verweisen; Kap. 30.3: Integrationszustände durch §6 ersetzen; Kap. 30.2 Workflow-Zeile: `assessment.*` → `exam.*`. | redaktionell offen (Konzept) |

---

## 9. Contract-Test-Anker (für R1-28 und R3-12)

Ziel-Spalte nach E-38/E-182 je Anker gesetzt: Prüfungen der **internen** Outbox (Envelope-Validierung, Transaktion, Dead-Letter) zielen auf **R1-43**; öffentliche Events, Handshake, Webhooks und Schema-Evolution für Partner sind Pro (**R3-01**, **R3-12**); die lesende Partnererkennung (CT-09) prüft R1-38 (E-185, E-254).

| Anker | Prüfung | Ziel |
|---|---|---|
| CT-01 | Snapshot aller Routen (Pfad, Methode, `args`-Schema, Response-Schema, `x-cc-stability`) gegen `docs/api/routes.v1.json`; nicht deklarierte Änderung bricht CI | R1-28 |
| CT-02 | Jede private Route ohne Cookie → 401 `ccap_auth_required`; ohne Nonce → 403 `rest_cookie_invalid_nonce`; fremdes Objekt → 404 `ccap_not_found` (R0-07 §5) | R1-28 |
| CT-03 | Fehlerkörper enthält alle Pflichtfelder aus §3.1; `X-Request-Id` = `request_id`; keine Pfade/SQL im Text | R1-28 |
| CT-04 | Idempotenz: gleicher Key → identische Antwort; anderer Body → 409; parallel → 409 `ccap_request_in_progress` | R1-28 |
| CT-05 | Jeder Hook aus §4.2/§4.3 existiert mit dokumentierter Signatur; veraltete Hooks melden `_deprecated_hook` genau dann, wenn genutzt | R1-28 |
| CT-06 | Jeder Event-Typ: Payload validiert gegen JSON-Schema (`schema/events/<type>/<major>.json`); keine verbotenen Felder (E-Mail, Name, Antworten) – Negativtest per Schlüssel-Blacklist | R1-43, R3-12 |
| CT-07 | Outbox: Fachänderung mit Rollback erzeugt kein Event; Doppelzustellung → Consumer-Wirkung einmal; 8 Fehlversuche → `dead`; Replay behält `event_id` | R1-43 |
| CT-08 | Handshake: Connector mit `contractRange` `>=2.0` → `active_incompatible`, wird nie aufgerufen, kein Fatal | R3-12 |
| CT-09 | Partner deaktiviert/fehlend → `inactive`/`not_installed`, Free-Lernen unverändert (Kap. 31.6) | R1-38, R3-12 |
| CT-10 | `course_captain_can_access` liefert `true` nach Kern-`false` → bleibt verweigert, Audit-Eintrag (E-84) | R1-28 |
| CT-11 | Webhook: falsche Signatur, Zeitstempel > 300 s, Replay derselben `event_id` → 401/401/202 `duplicate` ohne Wirkung | R3-01, R3-12 |
| CT-12 | Schema-Evolution: Consumer mit Major 1 erhält nach Einführung von Major 2 weiter Major-1-Events bis Fristende | R3-12 |

---

## 10. Nachweis

- Quelle: Clone `…/scratchpad/lb` @ `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (`git log -1`), nur gelesen. Direkt geprüft: `src/Mobile/Problem.php` (Felder, Codes, `request_id`-Format), `src/Learning/Services/QuestionPresenter.php:145-170` (Token-Fehler sind `RuntimeException`, Ablauf `DomainException`).
- Abgeleitet aus: Inventur D §4.1 (W1–W52), §4.2 (M1–M78), §5.1, §6.1–6.4; Inventur C §2.6, §4.3, §4.4, §4.7, §5.5; Inventur A §4.2, §4.3, §5.6; Inventur B §2.5, §4.4, §4.6, §5.1, §5.3; R0-04 K-01…K-25; R0-07 §3, §4; R0-08 §2, §6.6, §7.3; R0-16; R0-32 §6–§9; R0-36 §3–§4, §8.
- Konzept gelesen: Kap. 8 (Adapter-Handshake), 13 (Präfixe), 18, 18.1, 18.2, 30.1–30.5, 31.1–31.7, 32, 33.
- Kein Code, keine Tests ausführbar (keine Implementierung). Beschlüsse D19-1…D19-6 und E-38 sind eingearbeitet (16.11.2026); Deltas §8 mit Status.

## Nachträge aus Folge-Tasks

| Datum | Quelle | Änderung | Status |
|---|---|---|---|
| 16.11.2026 | R0-20 D-20-03 | §1.2: CSS-Präfix `ccap-`; Optionen, AJAX-Aktionen und Query-Vars `course_captain_`. Tabellen, Caps und Cron-Hooks nach E-207 `ccap_`; Fehlercodes `ccap_` | eingearbeitet (§1.2) |
| 16.11.2026 | R0-37 | Cron-Hook für Migration heißt `ccap_migrate` | eingearbeitet (§4.8) |

## Offene Punkte nach Einarbeitung

1. **Präfix der Fehlercodes und Nonces:** E-207 nennt sie nicht; hier Fehlercodes `ccap_*` (ENTSCHEIDUNGEN §19), Nonce-Aktionen `course_captain_` wie AJAX-Aktionen. R0-30 verwendet `ccap_setting_invalid` – vor R1-01 vereinheitlichen.
2. **Umfang „Read-API“ in E-38:** Hier als lesende Partner-PHP-API (`get_*_v1`, `verify_certificate_v1`) und Pull-Route R-53 ausgelegt; die Lern- und Verwaltungsrouten in §2.3 bleiben Free. Ob die schreibende PHP-API (`grant_access_v1` usw.) für Drittplugins ohne Pro gesperrt sein soll, regelt E-38 nicht ausdrücklich (hier: nur mit Pro).
3. **Routen für Text-/Dateiaufgaben (R-54…R-58)** und deren Fehler-`reason`-Werte sind ein Erstentwurf; Feinschnitt in R1-46 mit R0-08/R0-10. Ob Events `assignment.submitted`/`assignment.graded` in den Katalog §5.3 gehören (z. B. für Benachrichtigungen), ist nicht beschlossen.
4. **ADR-Nummern:** R0-24 §6.2 verlangt, ADR-007/008 wegen Kollision mit Kap. 28.2 umzubenennen; neue Nummern sind nicht festgelegt.
5. **Prüfungs-Nachbewertung R-47:** E-74 legt den Datenvertrag in R1, die Oberfläche in Pro fest; hier ist auch die REST-Route Pro. Bestätigung nötig.
6. **E-39b** setzt die Änderung am Workflow-Captain-Konzept voraus (dort ist das LMS-Muster ausgeschlossen).
