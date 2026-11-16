# Entwicklerübersicht: REST, Hooks, Ereignisse, Fehler

> **Normative Referenz:** Die verbindliche API-Dokumentation mit Schemas, Stabilitätsstufen und Änderungsprotokoll entsteht mit R1-28 in [`docs/API.md`](../API.md) und [`docs/API-CHANGELOG.md`](../API-CHANGELOG.md). Diese Seite ist eine Übersicht über den **implementierten** Stand (geprüft gegen `src/` am 16.11.2026) und verweist für Details dorthin. Bei Widersprüchen gilt `docs/API.md`. Vertragsquelle ist R0-19.

## 1. Versionshandshake

- Vertragsversion: `COURSE_CAPTAIN_CONTRACT_VERSION` = `0.1.0` (1.0.0 mit dem R1-Release). Funktionen `course_captain_contract_version()` und `course_captain_supports( string $feature )`; unbekannte Features ergeben `false`.
- Erweiterungen registrieren sich auf `course_captain_loaded_v1` (Argument: `CourseCaptain\Contract\Api`). Im Haltemodus und bei fehlenden Voraussetzungen feuert die Aktion nicht.

## 2. REST-API `course-captain/v1`

Authentifizierung: WordPress-Cookie mit `X-WP-Nonce` (`wp_rest`) oder Application Passwords. Alle Routen außer der Zertifikatsprüfung verlangen eine Anmeldung (sonst `401 ccap_auth_required`); Objektrechte prüft der Dienst (404 für fremd und nicht existent). Kurse in Lernendenrouten akzeptieren ID oder UID. Antworten senden `no-store, private` plus Proxy-/CDN-Header (§5 in [troubleshooting.md](troubleshooting.md)).

### 2.1 Lernende (R0-19-Nummer in Klammern)

| Methode und Pfad | Parameter | Antwort / Besonderheiten |
|---|---|---|
| `GET /me/courses` (R-05) | – | Kurse mit Zugangsstatus, Fortschritt, Fortsetzen-Ziel |
| `GET /courses/{course}` (R-06) | – | Kursraum: Module, Lektionen, Sperrgrund, `unlock_at` |
| `GET /courses/{course}/lessons` (R-26) | – | flache Lektionsliste |
| `GET /courses/{course}/next-question` (R-07) | `mode` (`smart`, `new`, `wrong`, `favorites`), `topic`, `collection` | nächste Frage mit signiertem Token oder `{question: null}`; unbekannter Modus 422 |
| `POST /answers` (R-08) | `token`, `answer`, `idempotency_key` (UUID) | Bewertung, Fortschritt, ggf. Lösung; Wiederholung mit gleichem Schlüssel liefert `repeated: true`; 429 bei > 60/min |
| `PUT /courses/{course}/lessons/{lesson}/progress` (R-28) | `kind` (`position`, `confirm`, `view`, `download`, `cards`), `payload` | Abschlussstand der Activity |
| `PUT` / `DELETE /courses/{course}/favorites/{question}` (R-10/11) | – | `{favorite: true\|false}` |
| `POST /courses/{course}/exam-sessions` (R-18) | `rule_uid` (oder `rule`), `variant`, `request_key` (8–36 Zeichen `[A-Za-z0-9_-]`, Pflicht) | 201 neuer bzw. 200 laufender Versuch; 429 bei > 40 Starts/h |
| `GET /exam-sessions/{session}` (R-19) | – | Fragen ohne Lösung, gespeicherte Antworten, Markierungen, `server_now`, `deadline_at`; nach Auswertung gefiltertes Ergebnis |
| `PUT /exam-sessions/{session}/answers/{position}` (R-20) | `answer`, `age_ms` (Offline-Puffer) | `{applied, saved_at}`; älterer Stand `applied=false`; zu spät 410 |
| `PUT …/answers/{position}/flag` (R-21) | `flagged` | `{flagged}` |
| `POST …/answers/{position}/reveal` (R-22) | – | `{model_answer}` (nur Freitext) |
| `POST /exam-sessions/{session}/submit` (R-23) | – | gefiltertes Ergebnis; idempotent |
| `POST /exam-sessions/{session}/abort` (R-24) | – | `{status}`; nach Abgabe 409 |
| `GET /me/certificates` (R-40) | – | eigene Nachweise mit `view_url` (kein `pdf_url`) |
| `PUT /me/certificates/{uid}/visibility` (Δ-9) | `holder_name_public` (bool, Pflicht) | `{holder_name_public}`; Policy `never` → 403 |
| `GET /certificates/verify/{token}` (R-42) | – | **öffentlich**; Minimaldaten; 30/min je IP; `X-Robots-Tag: noindex` |

### 2.2 Autorenschaft

| Methode und Pfad | Parameter | Antwort |
|---|---|---|
| `GET /courses/{id}/structure` | – | Module mit Position, Pflicht |
| `PUT /courses/{id}/structure` | `modules[]` (Pflicht) | alles oder nichts; 422 bei unbekannt/doppelt |
| `GET /courses/{id}/validation` | `acknowledged[]` | Validator-Report (`summary`, `issues[]` mit `code`, `severity`, `fingerprint`, `target` …) |
| `POST /courses/{id}/publish` | `change_class` (`patch`, `minor`), `notes`, `acknowledged[]` | `{revision_id}`; 422 `ccap_validation_failed` bzw. 409 `ccap_validation_unacknowledged` mit `data.report` |
| `PUT /lessons/{id}/activity` | `type`, `settings`, `rule` | Activity-Definition |
| `POST /items/{id}/duplicate` | – | 201 `{id}` (Modul/Lektion) |

### 2.3 Admin-post-Aktionen (kein REST)

`ccap_duplicate`, `ccap_notice` (Hinweise ausblenden/vertagen), `ccap_certificate_issue` (`type`, `evidence_id`, `reason`), `ccap_certificate_correct` (`uid`, `reason`, `request_key`, `holder_name`/`course_title`/`refresh_evidence`), `ccap_certificate_revoke` (`uid`, `category`, `reason`). Alle mit Nonce gleichen Namens.

### 2.4 Weitere Einstiege

- Lernbereich: `/<lernbereich>/`, `kurs/<uid>`, `nachweise/`, `nachweise/<uid>`, `nachweis/<token>`, `benachrichtigungen/`.
- Geschützte Dateien: `/cc-file/<media_uid>/` (Range-Unterstützung, `private, no-store`).
- Shortcodes: `[ccap_learning_link]`, `[ccap_access course="…"]…[/ccap_access]`, `[course_captain_protected course="…"]…[/course_captain_protected]`. Mit R1-28 heißt Letzterer `[ccap_protected course="ID"]…[/ccap_protected]`; `[course_captain_protected]` bleibt als veralteter Alias (E-285).
- Blöcke (`apiVersion 3`, dynamisch): `course-captain/course-catalog`, `course-captain/learning-link`, `course-captain/certificate-verify`.

### 2.5 Geplant (nicht vorhanden)

R-01 `/status`, R-02…R-04 Importe, R-09 Aufdecken im Lernmodus, R-12…R-16 Sammlungen per REST, R-17 Prüfungsübersicht, R-25 Review, R-27 Lektionsansicht, R-29/30 Karteikarten, R-31…R-37 Gruppen, R-38/39 FAQ/Tipps, R-43…R-46 Berichte, R-48 Datenschutzanträge, R-49 `/media/{media}`, R-50 `/monitor`, R-54…R-58 Aufgaben (R1-28, R1-35, R1-38, R1-46). WP-CLI `wp course-captain …` (R1-28/R1-38/R1-40). Pro-Routen (PDF, Webhooks, Events-Pull) sind nicht Teil von Free.

**Bekannte Abweichungen vom Vertrag R0-19** (Angleichung mit R1-28): `POST /answers` nimmt `answer` + `idempotency_key` statt `answer_ids[]`/`known`/`response_ms`; R-20 nimmt `answer` statt `answer_ids[]`; R-07 kennt die Modi `topic`/`module`/`collection`/`exam_mistakes` als eigene Werte nicht (Thema und Sammlung sind Parameter).

## 3. Fehlerformat

Heute: `WP_Error` mit `code` (`ccap_*`), `message` und `data.status`; Lernendenrouten ergänzen fachliche Details in `data` (z. B. `retry_after`, `bucket`), die Publish-Route `data.report`.

Mit R1-28 (parallel umgesetzt) tragen alle Fehler in `course-captain/v1` die Felder nach R0-19 §3.1: `code`, `message`, `data.status`, `type` (`urn:course-captain:problem:…`), `title`, `status`, `detail`, `request_id` (auch Header `X-Request-Id`), `reason`, `retry_after` (auch Header `Retry-After`), `errors`, `details`. Abgemeldete Anfragen erhalten `401 ccap_auth_required`. Der Fehlerkatalog mit allen Codes steht in [troubleshooting.md](troubleshooting.md) §2 und normativ in `docs/API.md`.

## 4. Hooks und Filter

**Öffentliche Erweiterungspunkte** (Präfix `course_captain_`):

| Name | Art | Argumente | Zweck |
|---|---|---|---|
| `course_captain_loaded_v1` | Action | `Api $api` | Plugin bereit |
| `course_captain_register_activities` | Action | `ActivityRegistry $registry` | eigene Activity-Typen (Vertrag `ActivityProvider`) |
| `course_captain_activity_progress_changed` | Action | `int $user_id, int $course_id, int $lesson_id` | nach jeder Lerninteraktion |
| `course_captain_outbox_dead_lettered` | Action | `int $delivery_id, string $subscriber_id` | Zustellung endgültig gescheitert |
| `course_captain_certificate_fallback_rendered` | Action | `string $kind, string $certificate_uid` | Diagnose: unbekannte Vorlagenart, Standardlayout genutzt |
| `course_captain_uninstall` | Action | `UninstallManifest $manifest` | während der Deinstallation |
| `course_captain_cache_purge` | Action | `array{scope, ids, urls} $purge` | nach Veröffentlichen/Archivieren eines Kurses: öffentliche Seiten aus Page-Caches entfernen |
| `course_captain_policy_changed` | Action | – | Rechte-/Zugangsänderung (leert Memo); auslösen, wenn eigene Regeln Zugriff ändern |
| `course_captain_certificate_renderers` | Filter | `array $renderers` | weitere Zertifikats-Renderer |
| `course_captain_trusted_media_hosts` | Filter | `string[] $hosts` | Hosts, die nicht als fremd gelten |
| `course_captain_media_mime_types` | Filter | `array $mimes` | MIME-Allowlist der Lernmedien |
| `course_captain_private_dir` | Filter | `string $dir` | Basisordner der geschützten Ablage (wie `COURSE_CAPTAIN_PRIVATE_DIR`) |
| `course_captain_mail_template_locales` | Filter | `string[] $locales` | erlaubte Vorlagensprachen |
| `course_captain_outbound_mode` | Filter | `array $resolved` | Ausgangsmodus (Tests, MU-Plugins) |
| `course_captain_notification_hold` | Filter | `bool $hold` | Mails zurückhalten (Anschluss Haltemodus) |
| `course_captain_publish_guard_applies` | Filter | `bool $applies` | ob der Statuswechsel-Schutz greift |
| `course_captain_explanations_available` | Filter | `bool` (Standard `false`) | Erklärungstexte (nur Pro) |
| `course_captain_allow_unmoved_protected_file` | Filter | `bool, string $path` | Auslieferung noch nicht verschobener Dateien |

**Intern** (keine stabile Schnittstelle, nur für Kernabonnenten): `course_captain_internal_subscribers`, `course_captain_internal_subscriber_handlers`.

Stabilitätsstufen (`@api`, `@since`) und Deprecation-Fristen: R0-19 §1.3/§1.5, Liste in `docs/API.md`. Öffentlicher Ereignis-Hook `course_captain_event_v1` und Lese-API (z. B. `course_captain_verify_certificate_v1`) sind Pro (E-38).

## 5. Interne Ereignisse (Outbox)

Envelope v1 in `ccap_outbox_event`, nur gespeichert, wenn ein interner Abonnent existiert. Kernabonnenten: `cache`, `notification`, `certificates`. In Free gibt es keine öffentlichen Ereignisse, Webhooks oder Connectoren.

| Bereich | Typen |
|---|---|
| Zugang | `grant.created`, `grant.activated`, `grant.manual_created`, `grant.suspended`, `grant.resumed`, `grant.extended`, `grant.rescheduled`, `grant.expired`, `grant.revoked`, `grant.failed`, `grant.retried`, `access.changed` |
| Gruppen | `group.created`, `group.seats_changed`, `group.status_changed`, `group.token_rotated`, `seat.invited`, `seat.claimed`, `seat.removed` |
| Lernen/Prüfung | `course.completed`, `exam.passed`, `exam.failed`, `exam.reevaluated` |
| Zertifikate | `certificate.issued`, `certificate.corrected`, `certificate.revoked` |
| Betrieb | `outbox.replayed` |

Zustellung: Lease 60 s, ≤ 100 je Lauf, Reihenfolge je Objekt, Backoff bis 8 Versuche, dann `dead`.

## 6. Konstanten

| Konstante | Wirkung |
|---|---|
| `COURSE_CAPTAIN_PRIVATE_DIR` | Basis der geschützten Ablage (außerhalb des Webroots empfohlen) |
| `COURSE_CAPTAIN_OUTBOUND` | `on` / `catch` / `off` (Mails und interne Zustellungen) |
| `COURSE_CAPTAIN_MAIL_CATCH_ADDRESS` | Fangadresse im Modus `catch` |
| `COURSE_CAPTAIN_VERSION`, `COURSE_CAPTAIN_CONTRACT_VERSION`, `COURSE_CAPTAIN_MIN_PHP`, `COURSE_CAPTAIN_MIN_WP` | nur lesen |

Geplant: `COURSE_CAPTAIN_SECRET_KEY`, `COURSE_CAPTAIN_DEBUG`, `COURSE_CAPTAIN_HARDENING` (R1-38/R1-45).

## 7. Entwicklung und QA

`composer lint`, `composer analyse` (PHPStan Stufe 8), `composer test` (Unit), `composer test:integration`, `composer test:integration-ms`, `composer guard:free` (keine Updater/Lizenzclients in Free), `php tests/guards/textdomain-lint.php`. Präfixe: `ccap_` für Rollen, Rechte, Tabellen, Cron und Meta; `course_captain_` für Optionen und Hooks; Textdomain `course-captain`.
