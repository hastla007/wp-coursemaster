# Course Captain – API-Referenz (Vertrag v1)

**Vertragsversion:** `COURSE_CAPTAIN_CONTRACT_VERSION` (aktuell `0.1.0`, mit dem R1-Release `1.0.0`). Abrufbar über `course_captain_contract_version()`. Funktionsprüfung über `course_captain_supports( $feature )`; unbekannte Schlüssel → `false`. Bekannte Merkmale: `certificates`, `settings.fields.v1`, `settings.hints.v1`.
**Quellen:** R0-19 (Versionierung §1, REST §2, Fehlerkatalog §3, Hooks §4), Contract-Tests `tests/integration/ApiContractTest.php`, Snapshots `docs/api/routes.v1.json` und `docs/api/hooks.v1.json`.
**Änderungen:** `docs/API-CHANGELOG.md`. Eine nicht deklarierte Änderung an Routen oder Hooks lässt den Contract-Test fehlschlagen (CT-01, CT-05). Snapshot neu schreiben: Testsuite einmal mit `CCAP_UPDATE_SNAPSHOTS=1` laufen lassen und den Changelog ergänzen.

## 1. Stabilität und Deprecation

- **Stabil** sind `_v1`-Hooks und der REST-Namespace `course-captain/v1`: Änderungen nur additiv. Entfernen oder Umbenennen frühestens nach zwei Minor-Releases **und** sechs Monaten (R0-19 §1.5).
- **Veraltete REST-Routen** antworten mit `Deprecation: @<unix>`, `Sunset: <HTTP-Datum>` und `Link: <doku>; rel="deprecation"`.
- **Veraltete Shortcodes** bleiben als Alias und melden `_deprecated_function` (sichtbar nur mit `WP_DEBUG`).
- **Alle übrigen Hooks** gelten bis zur Freigabe als experimentell oder intern.

## 2. REST (`/wp-json/course-captain/v1`)

**Konventionen:**
- Authentifizierung über WP-Cookie + `X-WP-Nonce` (`wp_rest`).
- Fremde oder unbekannte Objekte → `404 ccap_not_found`.
- Jede Antwort trägt `X-Request-Id`, `Cache-Control: no-store, private`, `Surrogate-Control: no-store`, `CDN-Cache-Control: no-store`, `X-Accel-Expires: 0` und `X-LiteSpeed-Cache-Control: no-cache`.
- Zeiten im Format RFC 3339 UTC; Punkte als Tausendstel.

| Methode | Pfad | Zugriff | Schema-Parameter (* Pflicht) |
|---|---|---|---|
| `POST` | `/answers` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/assignment-submissions/(?P<uid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/assignment-submissions/(?P<uid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/files` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `DELETE` | `/assignment-submissions/(?P<uid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/files/(?P<media>[0-9a-f-]+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/assignment-submissions/(?P<uid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/grade` | angemeldet (Cookie + `X-WP-Nonce`) | `action`*, `feedback`, `points_milli`, `reason` |
| `POST` | `/assignment-submissions/(?P<uid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/submit` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/certificates/verify/(?P<token>[0-9a-zA-Z]{1,64})` | öffentlich | – |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)/assignments/(?P<lesson>\d+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/courses/(?P<course>[0-9a-f-]+)/assignments/(?P<lesson>\d+)/draft` | angemeldet (Cookie + `X-WP-Nonce`) | `submission_uid`, `text` |
| `POST` | `/courses/(?P<course>[0-9a-f-]+)/enrollment` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/courses/(?P<course>[0-9a-f-]+)/exam-sessions` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)/faqs` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `DELETE` | `/courses/(?P<course>[0-9a-f-]+)/favorites/(?P<question>\d+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/courses/(?P<course>[0-9a-f-]+)/favorites/(?P<question>\d+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)/lessons` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/courses/(?P<course>[0-9a-f-]+)/lessons/(?P<lesson>\d+)/progress` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)/next-question` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)/practice` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)/progress` | angemeldet (Cookie + `X-WP-Nonce`), nur eigene Daten | – |
| `POST` | `/courses/(?P<id>\d+)/publish` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/courses/(?P<id>\d+)/structure` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/courses/(?P<id>\d+)/structure` | angemeldet (Cookie + `X-WP-Nonce`) | `modules`* |
| `GET` | `/courses/(?P<id>\d+)/validation` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/exam-sessions/(?P<session>\d+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/exam-sessions/(?P<session>\d+)/abort` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/exam-sessions/(?P<session>\d+)/answers/(?P<position>\d+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/exam-sessions/(?P<session>\d+)/answers/(?P<position>\d+)/flag` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/exam-sessions/(?P<session>\d+)/answers/(?P<position>\d+)/reveal` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/exam-sessions/(?P<session>\d+)/submit` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/grading/queue` | angemeldet (Cookie + `X-WP-Nonce`) | `course`, `page`, `per_page` |
| `POST` | `/groups/join` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/groups/join/(?P<token>[A-Za-z0-9_-]{1,64})` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/health` | öffentlich | – |
| `GET` | `/imports/(?P<id>\d+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/imports/(?P<id>\d+)/cancel` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/imports/(?P<id>\d+)/report` | angemeldet (Cookie + `X-WP-Nonce`) | `after`, `class`, `entity`, `format`, `per_page`, `phase`, `rule`, `severity` |
| `POST` | `/imports/(?P<id>\d+)/resume` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/imports/(?P<id>\d+)/step` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/items/(?P<id>\d+)/duplicate` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/lessons/(?P<id>\d+)/activity` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/me/certificates` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/me/certificates/(?P<uid>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/visibility` | angemeldet (Cookie + `X-WP-Nonce`) | `holder_name_public`* |
| `GET` | `/me/courses` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/me/data-exports` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/me/data-exports/(?P<uid>[0-9a-f-]{36})` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/me/groups` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/me/groups/(?P<uid>[0-9a-f-]{36})` | angemeldet (Cookie + `X-WP-Nonce`) | `name`* |
| `POST` | `/me/groups/(?P<uid>[0-9a-f-]{36})/invite` | angemeldet (Cookie + `X-WP-Nonce`) | `bind_to_address`, `emails`* |
| `DELETE` | `/me/groups/(?P<uid>[0-9a-f-]{36})/members/(?P<member>\d+)` | angemeldet (Cookie + `X-WP-Nonce`) | `reason` |
| `POST` | `/me/groups/(?P<uid>[0-9a-f-]{36})/token` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `POST` | `/me/privacy-requests` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/monitor` | öffentlich | – |
| `GET` | `/reports/courses/(?P<course>[0-9a-f-]+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/reports/courses/(?P<course>[0-9a-f-]+)/learners/(?P<user>\d+)/progress` | angemeldet (Cookie + `X-WP-Nonce`), M-11 (selbst, zugewiesene Trainer, websiteweites Personal) | – |
| `GET` | `/status` | angemeldet (Cookie + `X-WP-Nonce`) | – |

**Modulschalter (SET-CC-24):** Ist `certificates.enabled` aus, fehlen `GET /me/certificates` und `PUT /me/certificates/{uid}/visibility` (`404 rest_no_route`); `GET /certificates/verify/{token}` bleibt. Ist `learning.assignments_enabled` aus, fehlen die Abgaberouten (`/courses/{course}/assignments/…`, `/assignment-submissions/…`, `/grading/queue`). Fortschritt einer Lektion eines ausgeschalteten Moduls → `404 ccap_module_disabled`. Gespeicherte Daten bleiben unverändert.

Fachliche Parameter ohne Schema (z. B. `answer`, `age_ms`, `request_key`, `acknowledged[]`) prüft der jeweilige Dienst. Details stehen in den Nachweisen `docs/r1/R1-*-nachweis.md`.

## 3. Fehlerformat (R0-19 §3.1)

```json
{
  "code": "ccap_not_found",
  "message": "The requested item was not found.",
  "data": { "status": 404 },
  "type": "urn:course-captain:problem:not-found",
  "title": "Not found",
  "status": 404,
  "detail": "The requested item was not found.",
  "request_id": "req_<32 hex>",
  "reason": null,
  "retry_after": null,
  "errors": null,
  "details": null
}
```

- **Texte:** `message` und `detail` sind übersetzte Katalogtexte ohne technische Details. WordPress-Kerncodes (`rest_*`) werden durchgereicht.
- **Nicht angemeldet:** `401 ccap_auth_required`, auch wenn der Kern `rest_forbidden` liefert. Pflichtparameter prüft WordPress vor der Berechtigung, deshalb können anonyme Aufrufe ohne Pflichtparameter `400 rest_missing_callback_param` liefern.
- **Wiederholen:** `retry_after` bei wiederholbarem 409, bei 429 und 503, zusätzlich als Header `Retry-After`. Rate-Limits liefern `details.bucket` (`answer`, `exam_start`, …).
- **Kurs-Validator:** `422 ccap_validation_failed` bzw. `409 ccap_validation_unacknowledged`, der Report steht in `details.report`.

## 4. Hooks (Präfix `course_captain_`)

| Hook | Art |
|---|---|
| `course_captain_account_deletion_protected` | filter |
| `course_captain_activity_progress_changed` | action |
| `course_captain_allow_unmoved_protected_file` | filter |
| `course_captain_assignment_file_scan` | filter |
| `course_captain_business_rule` | filter |
| `course_captain_cache_purge` | action |
| `course_captain_can_access` | filter |
| `course_captain_certificate_fallback_rendered` | action |
| `course_captain_certificate_renderers` | filter |
| `course_captain_country_profiles` | filter |
| `course_captain_csp_frame_hosts` | filter |
| `course_captain_explanations_available` | filter |
| `course_captain_external_access_sources` | filter |
| `course_captain_hardening_conflicts` | filter |
| `course_captain_hardening_limits` | filter |
| `course_captain_import_package_limit` | filter |
| `course_captain_internal_subscriber_handlers` | filter |
| `course_captain_internal_subscribers` | filter |
| `course_captain_loaded_v1` | action |
| `course_captain_mail_template_locales` | filter |
| `course_captain_media_mime_types` | filter |
| `course_captain_migration_fault` | action |
| `course_captain_notification_hold` | filter |
| `course_captain_outbound_mode` | filter |
| `course_captain_outbox_dead_lettered` | action |
| `course_captain_policy_changed` | action |
| `course_captain_private_dir` | filter |
| `course_captain_publish_guard_applies` | filter |
| `course_captain_register_activities` | action |
| `course_captain_retention_days` | filter |
| `course_captain_risk_text` | filter |
| `course_captain_seo_indexable` | filter |
| `course_captain_settings_hints_v1` | filter |
| `course_captain_settings_register_fields_v1` | filter |
| `course_captain_settings_risks` | filter |
| `course_captain_settings_roles_html` | filter |
| `course_captain_settings_roles_save` | filter |
| `course_captain_settings_roles_tab` | filter |
| `course_captain_settings_rules` | filter |
| `course_captain_settings_transfer_providers` | filter |
| `course_captain_trusted_media_hosts` | filter |
| `course_captain_uninstall` | action |

Wichtige Verträge:
- `course_captain_loaded_v1` (Action, stabil; Argument `CourseCaptain\Contract\Api`). Erweiterungen registrieren sich erst danach.
- `course_captain_cache_purge` (Action; `{scope, ids, urls}` nach Veröffentlichung oder Archivierung eines Kurses).
- `course_captain_internal_subscribers` (interne Outbox-Abonnenten; intern).
- `course_captain_settings_register_fields_v1` (Filter, stabil, SET-CC-01; Merkmal `settings.fields.v1`): Andockpunkt für Add-on-Felder des Einstellungsregisters. Rückgabe `array<string, array>` mit Schlüsseln `pro.<tab>.<feld>[.<unterfeld>]` (Kleinbuchstaben, Ziffern, `_`) und derselben Felddefinition wie im Kern (`type` aus `bool|int|page_id|enum|set|slug|string|text|color|email|url|ip_list|host_list|object`, `default`, optional `cap` `settings|admin`, `privacy` `public|internal|personal|secret`, `export` `true|false|never_env`, Typregeln `min`, `max`, `nullable`, `values`, `fields`, `strict`). Verworfen werden: andere Präfixe (Kernfelder sind nie ersetzbar), unbekannte Typen, ein `default`, der die eigene Prüfung nicht besteht, Objekte/Closures irgendwo in der Definition; `side_effects` werden geleert. Gelesen über `SettingsSchema::addon_fields()` (Ergebnis mit zusätzlichem `tab`). Der Kern speichert, zeigt, exportiert oder löscht diese Werte **nie** (Speicher, Oberfläche und Lizenzlogik bleiben im Add-on, R0-39).
- `course_captain_settings_hints_v1` (Filter, stabil, SET-CC-01, E-346 S1/S5; Merkmal `settings.hints.v1`): Hinweiszeilen am Ende eines Einstellungs-Tabs. Liste von `{id, tab, title, text, link}`; `id` `[a-z0-9_]{1,40}` (erste Registrierung gewinnt, max. 100), `tab` einer der Tabs der Einstellungsseite (auch `roles`), `title` ≤ 80 und `text` ≤ 300 Zeichen (reiner Text, bereits übersetzt), `link` leer, `https://…` oder eine Seite dieser Verwaltung. Alle anderen Schlüssel werden verworfen, Einträge mit Nicht-Text-Werten ganz. Die Kernliste ist leer (S5). Darstellung als graue Textzeile ohne Feld mit „Ausblenden“ (admin-post `ccap_settings_hint_dismiss`, Nonce `ccap_settings_hint_dismiss_<id>`); ausgeblendete IDs je Person und Website in der User-Option `course_captain_hints_dismissed` (Deinstallations-Manifest).
- Einstellungsdatei und Add-on-Felder (SET-CC-01): Ein Abschnitt `pro` gehört dem Add-on (eigener `TransferProvider` mit Schlüssel `pro` über `course_captain_settings_transfer_providers`) und wird ihm unverändert übergeben. Ohne diesen Anbieter lässt der Import den Abschnitt, Tabs `pro.<tab>` und `pro.*`-Schlüssel in Kern-Tabs unberührt und meldet sie unter `addon` (Hinweis `addon_kept`) statt als „unbekannt“.
- `course_captain_can_access` (Filter bei jeder Lernzugriffsentscheidung M-02; `bool $allowed, int $user_id, string $course_uid, string $scope, Decision $decision`; darf nur einschränken, E-84).
- `course_captain_settings_transfer_providers` (Filter, experimentell; `list<CourseCaptain\Settings\TransferProvider>`; zusätzliche Abschnitte der Einstellungsdatei neben `tabs`, z. B. Mailvorlagen; eingebaut `locks` und `roles`). Einstellungs-CLI: `wp course-captain settings list|get|set|export|import|reset` (Handbuch `docs/EINSTELLUNGEN.md`).
- `course_captain_country_profiles` (Filter, experimentell, SET-CC-07): Länderprofile `DE`, `AT`, `CH`, `EU`, `OTHER` aus `assets/data/country-profiles.json`; Rückgabe `array<ID, array<schlüssel, {value, legal?, reviewed?, open?}>>`. Schlüssel: bestehende Registerfelder `<tab>.<feld>` (nur exportierbare, nicht personenbezogene; nie `general.country_profile`) oder WordPress-Werte `wp.WPLANG`, `wp.timezone_string`, `wp.date_format`, `wp.time_format`, `wp.start_of_week`; `open.<name>` nur als Platzhalter mit `value: null` und `open: "E<n>"` (offene Inhaber-Entscheidung). `value: null` ohne `open` = kein Vorschlag (übersprungen). `legal: true` ohne `reviewed: true` erscheint als „ungeprüft“ mit Hinweis Rechtsprüfung. Vorschläge werden nie still übernommen: Vergleich Vorher/Nachher in Setup-Schritt 1 und Tab Allgemein, Übernahme je Wert oder alle (admin-post `ccap_country_profile_apply`, Nonce, `ccap_manage_settings`; WordPress-Werte nur mit `manage_options`; Werte mit Risiko-Bestätigung werden übersprungen), Audit `country_profile_applied` (Vorher/Nachher je Schlüssel), gewähltes Profil in `general.country_profile` (Export/Import).
- `course_captain_csp_frame_hosts` (Filter, experimentell; `list<string> $hosts` ohne Schema: weitere Hosts für `frame-src` der Content-Security-Policy des Lernbereichs, SET-CC-10; ungültige Hostnamen werden verworfen).

Einstellungen dieses Standes (Register `SettingsSchema`, SET-CC-04/-09/-10/-16/-18/-22/-23): `display.logo_id` (int, nicht exportiert), `display.accent_color` (`#rrggbb` oder leer; Kontrast zu Weiß ≥ 4,5:1, sonst Feldfehler `contrast`), `display.button_labels` (`enroll`, `complete`, `start_exam`, `submit_exam`, je ≤ 40 Zeichen, leer = übersetzter Standard), `display.require_transcripts` (bool, Standard aus; VAL-144 Hinweis bzw. Blocker), `display.accessibility_page_id` (Seite, Link im Fuß des Lernbereichs), `advanced.log_level` (`error|warning|info|debug`, Standard `warning`, `info`/`debug` fallen nach 7 Tagen zurück, nie exportiert), `advanced.error_log_days` (1–365, 30), `advanced.debug_file_days` (1–30, 7). `privacy.csp_mode` (`enforce|report_only`) wirkt jetzt als Header `Content-Security-Policy` bzw. `Content-Security-Policy-Report-Only` auf allen Seiten des Lernbereichs. Videoaktivität: neue Einstellungen `transcript` (Text ≤ 100 000 Zeichen) und `transcript_url` (https). Mailtexte: Admin-Seite `admin.php?page=course-captain-mail-texts` (admin-post `ccap_mail_text`, `ccap_mail_text_export`), Exportformat `{format: "course-captain-mail-templates", format_version: 1, templates: {<typ>: {<locale>: {subject, body_html, body_text}}}}`.

Einstellungen SET-CC-06/-08 (Register `SettingsSchema`, keine REST-Routen, keine neuen Hooks): Feldtyp `url` (absolute `http`/`https`-Adresse mit Host, ≤ 2000 Zeichen, ohne Leerzeichen und ohne Zugangsdaten; leer erlaubt; Feldfehler `url`), auch für Add-on-Felder. `general.legal_links` (Objekt `imprint`, `terms`, `withdrawal`, `privacy`, je `url`, Standard leer, Markierung `legal_review`; keines ist Pflicht; `privacy` leer = Datenschutzseite von WordPress): Links im Fuß des Lernbereichs (`<nav aria-label>` mit Liste) und im Fuß aller Mails, nur wenn gesetzt; Mail-Platzhalter `{{imprint_url}}`, `{{terms_url}}`, `{{withdrawal_url}}` für alle Mailarten, `{{privacy_policy_url}}` folgt jetzt `general.legal_links.privacy`; nicht gesetzte Links werden zu leerem Text. Zertifikate: `certificates.default_validity_days` (1–3650 oder `null` = unbegrenzt; nur Nachweise ohne eigene Gültigkeit), `certificates.expiry_notice_days` (1–365, 30; Hinweis „recertify“ in `GET /me/certificates`), `certificates.paper_format` (`a4|letter`), `certificates.language` (`site|learner|fixed`) und `certificates.fixed_locale` (installierte Sprache, Feldfehler `locale`). Neue Zertifikate speichern `valid_until`, `locale.ui` und das optionale Snapshot-Objekt `layout {paper, language}` (Snapshot `format_version` bleibt 1); ausgestellte Zertifikate ändern sich nicht.

## 5. Shortcodes und Blöcke

| Kennung | Art | Hinweis |
|---|---|---|
| `[ccap_protected course="ID"]…[/ccap_protected]` | Shortcode | geschützter Abschnitt; `[course_captain_protected]` ist ein veralteter Alias |
| `[ccap_learning_link]` | Shortcode | Link in den Lernbereich |
| `[ccap_access]` | Shortcode | Zugangsstatus |
| `[ccap_privacy_request]` | Shortcode | Self-Service-Formular für Export-/Löschantrag (angemeldet; Bestätigung per Mail, GET zeigt nur den Bestätigungsknopf) |
| `course-captain/course-catalog` | Block (`apiVersion` 3, `version` 1.1.0) | dynamisch; `orderBy` leer (Vorgabe) = Website-Einstellung `display.catalog_sort`, Karteninhalt nach `display.card_elements` |
| `course-captain/learning-link` | Block (`apiVersion` 3, `version` 1.0.0) | dynamisch |
| `course-captain/certificate-verify` | Block (`apiVersion` 3, `version` 1.0.0) | Verifikationsformular |

## 6. Geplant (nicht Teil des aktuellen Vertrags)

- `Idempotency-Key`-Header (CT-04)
- JSON-Schema je Route mit `x-cc-stability`
- UUID-Pfadparameter für Autorenrouten (derzeit numerische IDs)
- WP-CLI `wp course-captain …`
- PHP-API `course_captain_<verb>_<objekt>_v1()`
- öffentliche Events und Webhooks (Pro, R3-01/R3-12)
