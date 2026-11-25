# Nachweis E-373, E-374, E-375, E-377 (Slice „dateien-entscheidungen“, Branch `r8-c`)

Stand 25.11.2026, Basis 47e704f. Inhaber-Entscheidungen vom 25.11.2026 zu geschützten Dateien (R0-10 §4). TODO-Häkchen setzt die Koordination.

Alle Änderungen liegen im Auslieferungsdienst `src/Access/ProtectedFiles.php`. Neu in `src/Access/ContentGate.php` ist nur die Abfrage `running_exam_media()`. Drip und Modul-Gate sind die bestehenden (`ContentGate::module()`, `GateDecision::assert()`). Neue übersetzbare Texte gibt es nicht.

## E-373 · MED-001: Nicht angemeldet → Anmeldung mit Rücksprung

- `ProtectedFiles::on_template_redirect()`: eine Ablehnung mit 401 bei einem Browser-Aufruf leitet per `wp_safe_redirect( wp_login_url( ProtectedFiles::url( $uid ) ) )` weiter (302, `nocache_headers`). Die Rücksprungadresse ist die geprüfte Datei-URL. Sie enthält nur die Media-UID, nie einen Pfad, Dateinamen oder das Ordnergeheimnis.
- Keine Weiterleitung, sondern weiter `401` ohne Inhalt: REST (`REST_REQUEST`), AJAX und JSON-Anfragen (`wp_is_json_request()`, also `Accept`/`Content-Type: application/json`). Statt 401 leiten auch andere Statuscodes (403, 404) nie weiter. Eine fehlerhafte UID führt ebenfalls nie zu einer Weiterleitung.
- Testbar über `ProtectedFiles::login_redirect( int $status, string $uid, bool $json )` und `ProtectedFiles::wants_json()`.
- Test: `MediaCatalogGapsTest::test_MED_001_without_login` (ergänzt um Weiterleitung, Rücksprung, keinen Pfad, UID genau einmal, JSON/403/404/ungültige UID → keine Weiterleitung, `Accept`-Erkennung).

## E-374 · Drip für geschützte Dateien

- Wenn die Policy (M-20, `download_file`) die Datei für Lernende erlaubt, prüft `ProtectedFiles::assert_drip()` zusätzlich Folgendes:
  - Eine Datei, die der Kurs selbst verwendet (Link `course`), ist bei Kurszugang offen. Kursmedien gehören zu keinem Modul.
  - Sonst muss mindestens ein Modul, das die Datei verwendet (`Relations::modules_of_media()`), das Modul-Gate (Scope `download`) bestehen.
  - Sind alle zugänglichen Module per Drip gesperrt, wird die bestehende Sperre geworfen (`403 ccap_content_locked`, Grund `drip`).
- Ein Kurs-Grant umgeht Drip nicht. Voraussetzungen (`prerequisite`) sperren Dateien weiterhin nicht: Die Entscheidung nennt nur Drip.
- Redaktion (M-03 `EDIT_COURSE` auf einem verwendenden Kurs) ist ausgenommen.
- Test: `MediaCatalogGapsTest::test_E_374_drip_locks_protected_files`:
  - Lektions- und Moduldatei eines Drip-Moduls sind mit Kurs-Grant und mit Modul-Grant gesperrt (`drip`, ohne Dateiname).
  - Eine Datei, die zusätzlich ein Modul ohne Drip verwendet, ist offen. Das gilt auch mit Modul-Grant, solange nur das gesperrte Modul zugänglich ist.
  - Kursdatei und Administration sind offen.
  - Nach dem Freigabedatum (`ContentGate::set_now`) sind alle Dateien offen.

## E-375 · MED-004: Medien in Prüfungsfragen

- Wer einen laufenden Versuch hat, dessen Snapshot eine Fragenrevision mit diesem Medium enthält (`ccap_exam_session_question.revision_id` → `ccap_content_media`), bekommt das Medium. Das gilt auch, wenn der Grant während des Versuchs endet.
  - „Laufend“ wird wie bei der Prüfungssperre im Lernmodus bestimmt (`ContentGate::running_exam_questions()`): `in_progress` mit Frist, ohne Frist höchstens 24 h.
  - Diese Ausnahme gilt nie für Gäste, gesperrte Konten (`ccap_account_locked`) und auch nicht über eine Einschränkung durch `course_captain_can_access` (`filtered`).
- Alle anderen brauchen den normalen Kurszugang wie im Übungsmodus, einschließlich Drip.
- Nach der Abgabe öffnet der Versuch nichts mehr. Die Ergebnisansicht (`ExamFeedback`, `ExamRoom::result`) zeigt keine Frageninhalte, daher gibt es keine Feedback-Regel, die Medien freigeben würde.
- Test: `MediaCatalogGapsTest::test_MED_004_exam_question_media_only_for_running_attempt`:
  - eigener laufender Versuch → 200
  - fremd ohne Zugang → 403 (ohne Dateiname)
  - Übungszugang mit Kurs-Grant → 200
  - Grant abgelaufen, Versuch läuft → 200 für die Frage des Versuchs, 403 für eine Frage außerhalb
  - nach der Abgabe → 403, Übungszugang weiter 200

## E-377 · MED-016: Filter `course_captain_protected_file_sendfile`

- `ProtectedFiles::response()` wird erst nach der Rechteprüfung aufgerufen und ruft den Filter mit `null`, dem absoluten Pfad und der `ccap_media`-Zeile auf.
- Gibt der Filter gültige Header zurück:
  - Status 200, Inhalts-Header plus Filter-Header, ohne `Content-Length`/`Content-Range`. Bereiche und Länge beantwortet der Webserver.
  - `serve()` sendet keinen Inhalt (`stream = false`).
- Header mit ungültigen Namen, Werten mit CR/LF/NUL und Inhalts-Header (`Content-Type`, `Content-Disposition`, `Cache-Control`, CSP, `nosniff`) aus dem Filter werden verworfen. Bleibt kein gültiger Header übrig, streamt PHP wie bisher.
- Der Filter gilt auch für Aufgaben-Dateien. Es gibt keine Einstellung.
- Doku:
  - `docs/API.md`: Zeile in der Hook-Tabelle und Vertragszeile „experimentell“
  - `docs/user/entwickler.md`: Hook-Tabelle
  - `docs/API-CHANGELOG.md`
  - Snapshot `docs/api/hooks.v1.json` (`CCAP_UPDATE_SNAPSHOTS=1` ApiContractTest)
- Test: `MediaCatalogGapsTest::test_MED_016_sendfile_filter`:
  - Standard streamt (SHA-256 gleich).
  - Mit Filter: `X-Accel-Redirect` gesetzt, Inhalts-Header bleiben, keine Länge und kein Bereich, auch bei `Range`. Der Inhalts-Header und die Header mit CR/LF bzw. ungültigem Namen aus dem Filter sind verworfen.
  - Filterargumente `null`, Pfad und Media-UID; der Body ist leer.
  - Ohne Recht (403/401) wird der Filter nicht aufgerufen.
  - Nur ungültige Header → Streaming.
  - `X-Sendfile` mit Pfad.

## Offen

- R0-10 §4.2 nennt noch „optional per Einstellung/Filter“ und §4.3 „Medien in Prüfungsfragen sind während eines laufenden Versuchs nur für dessen Inhaber abrufbar“. R0-10 wurde hier nicht angepasst (Koordination).
- Siehe Rückfragen im Bericht (E-375 Auslegung „Prüfungsfrage“).
