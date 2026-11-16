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
