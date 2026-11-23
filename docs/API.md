# Course Captain – API-Referenz (Vertrag v1)

**Vertragsversion:** `COURSE_CAPTAIN_CONTRACT_VERSION` (aktuell `0.1.0`, mit dem R1-Release `1.0.0`). Abrufbar über `course_captain_contract_version()`. Funktionsprüfung über `course_captain_supports( $feature )`; unbekannte Schlüssel → `false`.
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
| `POST` | `/courses/(?P<course>[0-9a-f-]+)/exam-sessions` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `DELETE` | `/courses/(?P<course>[0-9a-f-]+)/favorites/(?P<question>\d+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/courses/(?P<course>[0-9a-f-]+)/favorites/(?P<question>\d+)` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)/lessons` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `PUT` | `/courses/(?P<course>[0-9a-f-]+)/lessons/(?P<lesson>\d+)/progress` | angemeldet (Cookie + `X-WP-Nonce`) | – |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)/next-question` | angemeldet (Cookie + `X-WP-Nonce`) | – |
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
| `POST` | `/me/privacy-requests` | angemeldet (Cookie + `X-WP-Nonce`) | – (`type`: `export`\|`erase`; 202 `{status: created\|resent\|confirmed}`, 5/Tag, Bucket `privacy`) |
| `POST` | `/me/data-exports` | angemeldet (Cookie + `X-WP-Nonce`) | – (202 `{uid, status: pending, created_at, expires_at}`) |
| `GET` | `/me/data-exports/(?P<uid>[0-9a-f-]{36})` | angemeldet, nur Eigentümer (Cookie + `X-WP-Nonce`) | – (200 mit `data`; `409 ccap_export_not_ready`, `410 ccap_export_expired`/`ccap_export_failed`) |
| `GET` | `/courses/(?P<course>[0-9a-f-]+)/faqs` | angemeldet, Zugang zum Kurs (Cookie + `X-WP-Nonce`) | – (Liste `{uid, question, answer_html, category}`, nur veröffentlichte Einträge) |
| `GET` | `/status` | angemeldet, `ccap_view_system_status` | – |
| `GET` | `/health`, `/monitor` | öffentlich (Klasse K); Einzelprüfungen nur mit Header `X-CC-Monitor-Key` oder `ccap_view_system_status` | – |

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
| `course_captain_cache_purge` | action |
| `course_captain_can_access` | filter |
| `course_captain_certificate_fallback_rendered` | action |
| `course_captain_certificate_renderers` | filter |
| `course_captain_explanations_available` | filter |
| `course_captain_external_access_sources` | filter |
| `course_captain_hardening_conflicts` | filter |
| `course_captain_hardening_limits` | filter |
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
| `course_captain_risk_text` | filter |
| `course_captain_seo_indexable` | filter |
| `course_captain_settings_risks` | filter |
| `course_captain_settings_roles_html` | filter |
| `course_captain_settings_roles_save` | filter |
| `course_captain_settings_roles_tab` | filter |
| `course_captain_settings_rules` | filter |
| `course_captain_trusted_media_hosts` | filter |
| `course_captain_uninstall` | action |

Wichtige Verträge:
- `course_captain_loaded_v1` (Action, stabil; Argument `CourseCaptain\Contract\Api`). Erweiterungen registrieren sich erst danach.
- `course_captain_cache_purge` (Action; `{scope, ids, urls}` nach Veröffentlichung oder Archivierung eines Kurses).
- `course_captain_internal_subscribers` (interne Outbox-Abonnenten; intern).
- `course_captain_can_access` (Filter bei jeder Lernzugriffsentscheidung M-02; `bool $allowed, int $user_id, string $course_uid, string $scope, Decision $decision`; darf nur einschränken, E-84).

## 5. Shortcodes und Blöcke

| Kennung | Art | Hinweis |
|---|---|---|
| `[ccap_protected course="ID"]…[/ccap_protected]` | Shortcode | geschützter Abschnitt; `[course_captain_protected]` ist ein veralteter Alias |
| `[ccap_learning_link]` | Shortcode | Link in den Lernbereich |
| `[ccap_access]` | Shortcode | Zugangsstatus |
| `[ccap_privacy_request]` | Shortcode | Self-Service-Formular für Export-/Löschantrag (angemeldet; Bestätigung per Mail, GET zeigt nur den Bestätigungsknopf) |
| `course-captain/course-catalog` | Block (`apiVersion` 3, `version` 1.0.0) | dynamisch |
| `course-captain/learning-link` | Block (`apiVersion` 3, `version` 1.0.0) | dynamisch |
| `course-captain/certificate-verify` | Block (`apiVersion` 3, `version` 1.0.0) | Verifikationsformular |

## 6. Geplant (nicht Teil des aktuellen Vertrags)

- `Idempotency-Key`-Header (CT-04)
- JSON-Schema je Route mit `x-cc-stability`
- UUID-Pfadparameter für Autorenrouten (derzeit numerische IDs)
- WP-CLI `wp course-captain …`
- PHP-API `course_captain_<verb>_<objekt>_v1()`
- öffentliche Events und Webhooks (Pro, R3-01/R3-12)
