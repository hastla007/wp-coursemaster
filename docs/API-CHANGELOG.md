# API-Changelog (Vertrag v1)

Jeder Eintrag nennt: Version · Art (added/deprecated/removed/security) · Element · Ersatz · Fristende. Ein Eintrag ist Pflicht für jede Änderung an `docs/api/routes.v1.json` oder `docs/api/hooks.v1.json` (R0-19 §1.5).

## 0.1.0 (Vorabversion, noch kein stabiler Vertrag)

- **added** · REST-Namespace `course-captain/v1` mit den Routen aus `docs/api/routes.v1.json` (Lernen, Prüfung R-18…R-24, Autorenoberfläche, Zertifikate)
- **added** · einheitliches Fehlerformat (R0-19 §3.1), `X-Request-Id`, No-Store-Header auf allen Statuscodes
- **added** · Hooks aus `docs/api/hooks.v1.json`; stabil ist nur `course_captain_loaded_v1`
- **added** · Blöcke `course-captain/course-catalog`, `learning-link` und `certificate-verify` (`version` 1.0.0)
- **deprecated** · Shortcode `[course_captain_protected]` · Ersatz `[ccap_protected]` · Fristende: zwei Minor-Releases nach 1.0.0, mindestens 6 Monate
- **changed (vor Release)** · `ccap_answer_rate_limited` → `ccap_rate_limited` mit `details.bucket = answer`
- **changed (vor Release)** · `ccap_exam_finished` → `ccap_exam_already_submitted` bzw. `ccap_exam_aborted`
- **added** · Filter `course_captain_seo_indexable( bool $indexable, int $course )` (experimentell; kann Indexierbarkeit nur einschränken, R0-33 §1.3)
- **added** · `X-Robots-Tag: noindex, nofollow` auf allen Antworten von `course-captain/v1` (R0-33 LK-23)
- **added** · Action `course_captain_migration_fault` (intern, nur mit `COURSE_CAPTAIN_TESTING`; Fehlerinjektion für Migrationstests, R1-40) und WP-CLI `wp course-captain migrate [--status] [--format=json]`
- **added** · Filter `course_captain_hardening_conflicts( array $conflicts )` und `course_captain_hardening_limits( array $limits )` (experimentell, R0-31 §3.4); WP-CLI `wp course-captain hardening status|disable|unlock [--format=json]`; Header `Permissions-Policy` auf eigenen REST-Antworten bei aktivem `site_headers`
- **added** · WP-CLI `wp course-captain status [--format=json]` (Exit 1 bei `fail`), `cache flush [--user=<id>] [--course=<id>]`, `outbox status|retry|discard [--reason=<text>] [--yes]` (interne Abonnenten), `integrations status [--detected] [--format=json]` (nur Partnererkennung), `diagnostics [--output=<datei>]` (R1-38); keine neuen Hooks oder REST-Routen
- **changed (vor Release)** · Filter `course_captain_notification_hold`: Startwert ist jetzt der erkannte Haltemodus (`SiteIdentity::is_held()`, Site-Identität/Restore-Epoche, R1-23) statt `false`; kein neuer Hook. **added** · WP-CLI `wp course-captain identity [status|confirm <same_place|moved|copy>] [--format=json]`
- **added** · Text-/Dateiaufgaben (R1-46, R0-19 R-46, R-54…R-58): `GET /grading/queue` (Header `X-WP-Total`/`X-WP-TotalPages`), `GET /courses/{course}/assignments/{lesson}`, `PUT /courses/{course}/assignments/{lesson}/draft` (`If-Match` optional), `POST /assignment-submissions/{uid}/files` (multipart `file`, `Idempotency-Key` optional, 201/200 bei Wiederholung), `POST /assignment-submissions/{uid}/submit` (Wiederholung 200), `PUT /assignment-submissions/{uid}/grade` (`action: grade|return`, `points_milli`, `feedback`, `reason`; `Idempotency-Key` Pflicht); zusätzlich zum R0-19-Entwurf `GET /assignment-submissions/{uid}` (Eigentümer oder Bewertende) und `DELETE /assignment-submissions/{uid}/files/{media}` (Datei aus dem eigenen Entwurf entfernen). Neue Fehlercodes `ccap_submission_not_editable` (409), `ccap_submission_incomplete` (422), `ccap_submission_file_rejected` (422, `reason: dangerous|type|mime|size|empty|count|scan|upload|no_files`), `ccap_grade_invalid` (422, `reason: points|comment_required|reason_required`)
- **added** · Filter `course_captain_assignment_file_scan( bool|string|WP_Error $clean, string $path, array $meta )` (experimentell; nur `true` lässt eine Abgabedatei in die geschützte Ablage, R0-10 §4.5, E-289)
