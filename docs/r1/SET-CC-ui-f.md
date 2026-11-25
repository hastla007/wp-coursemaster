# Nachweis UI-F (UI-F1, UI-F3, UI-F4, UI-F5, UI-F6)

Stand 23.11.2026, Branch `r2-e` auf main 89fe9ef. Abhaken der TODO-Punkte macht die Koordination.

## UI-F5 · Quiz aus dem Modulpool (h5 L-2)

**Gebaut:** `src/Question/QuizPool.php`. Ein Quiz mit `settings.source = module_pool` zieht je Versuch `settings.count` Fragen (neu im Schema von `QuizProvider`, 1…200, Standard 10) aus dem auslieferbaren Pool des Moduls der Lektion (`QuestionRepository::pool_revisions()`: veröffentlicht, gültig). Zufall nach R0-08 §6: frischer 64-Bit-Seed je Versuch (`Randomizer::fresh()`), abgeleiteter Strom `pool:<lektion>`, Ziehung aus dem gespeicherten Seed reproduzierbar (`QuizPool::pick()`). Lernkarten werden nie gezogen (nicht bewertbar); bei `scored: true` auch keine offenen Fragen (nur Selbsteinschätzung). Die Ziehung `{seed, attempt, ids, drawn_at}` liegt im Aktivitätsfortschritt (`data.pool_draw`), `draw(..., restart: true)` startet einen neuen Versuch mit neuem Seed und setzt die Zähler zurück (Abschluss bleibt monoton).
- `QuestionPresenter::deliver_inline()` legt die Ziehung bei der ersten Auslieferung an, liefert nur gezogene Fragen (sonst 404) und prüft zusätzlich die Pool-Regeln (`ContentGate::question`, Teilzugang); `verify()` ebenso. Neu `quiz_questions(lesson, user)`.
- `AnswerService::feed_lesson_quiz()` zählt über die Ziehung und nur Antworten seit `drawn_at`.
- Validator: VAL-029 blockiert nur einen leeren Pool (`cc.val.quiz.pool_empty`), Pool kleiner als `count` ist ein Hinweis (`cc.val.quiz.pool_small`); Inline-Quiz unverändert.

**Test:** `ActivityTest::test_quiz_from_module_pool` (Validator leer/ok/klein, Lernkarte ausgeschlossen, Reproduzierbarkeit, Ziehung je Versuch, Auslieferung nur gezogener Fragen ohne Lösung, Fortschritt 3/3 → abgeschlossen, neuer Versuch mit neuem Seed, ohne Zugang keine Ziehung).

**Offen:** Die Lernoberfläche zeigt Quiz-Lektionen weiterhin nicht an (h5 L-3, „cannot be used on this page yet“); es gibt keine REST-Route für die Fragenliste/den Neustart eines Lektionsquiz (R-27 `render{}` wäre der Ort). Bewertete Quizze über `ccap_exam_session` (Modus `quiz`, Δ-2) sind nicht Teil dieser Aufgabe.

## UI-F6 · Editor-Kästen nach dem Speichern (h5 L-4, Bug)

**Ursache:** Der Block-Editor speichert die Metaboxen im Hintergrund und lädt sie nicht neu; das Kästchen „Publish a new revision when saving“ blieb angehakt und wurde beim nächsten Speichern erneut gesendet.
**Korrektur:**
- Server: Das Kästchen trägt `base:<veröffentlichte Revision>`; `save_course()` veröffentlicht nur, wenn die Basis noch der aktuellen Revision entspricht, sonst Hinweis „Not published again …“ (Änderungen am Entwurf werden gespeichert). `1` bleibt für Skripte gültig. Zusätzlich verhindert ein Wiedereintrittsschutz, dass das `save_post` aus `CoursePublisher::publish()` (Entwurf → veröffentlicht) `save_course()` verschachtelt erneut ausführt.
- Client (`assets/js/editor-notices.js`): nach jedem Metabox-Speichern wird das Kästchen sofort abgehakt; die AJAX-Aktion `ccap_editor_flash` liefert mit `post_id` die frisch gerenderten Panels (`EditorScreens::fresh_boxes()`, nur mit `edit_post`), die das Skript ersetzt (Fokus bleibt erhalten; DOMParser statt `innerHTML`, Lint C-13).
**Test:** `AuthoringTest::test_publish_box_does_not_republish_and_panels_refresh` (schlägt ohne Korrektur fehl, gegengeprüft: „not published again … 2 is identical to 1“).
**Offen:** kein Browserlauf (`tools/qa/e2e/live-flows.sh`) in dieser Sitzung; `flow-author.js` lädt weiterhin nach jedem Speichern neu und bleibt damit gültig.

## UI-F1 · Besitzer-REST R-33…R-37 und Wartezeit F3.7

**Gebaut:** `src/Learner/GroupOwnerRoutes.php` (aus `LearnerRoutes::routes()` registriert, kein Eingriff in `Plugin.php`), alles über `GroupService` und `GroupsScreen::invite_list()`:
- R-33 `GET /me/groups` (Plätze, Mitglieder maskiert, Link-Status, `invite_budget{limit, used, remaining, next_at, retry_after}`).
- R-34 `PUT /me/groups/{uid}` (`If-Match` → `409 ccap_version_conflict`, `ETag`).
- R-35 `POST /me/groups/{uid}/invite` (`Idempotency-Key` Pflicht, Status je Adresse `invited|already_member|invalid|limited`; voll `409 ccap_group_full`; Budget aufgebraucht `429 ccap_rate_limited`, `bucket: group_invite`, `retry_after`).
- R-36 `POST /me/groups/{uid}/token`, R-37 `DELETE /me/groups/{uid}/members/{seat_id}` (204; Mitglied frei bzw. Einladung zurückgezogen).
- Fremde Gruppen 404, ausgesetzte/beendete 409 `ccap_group_not_active`.
- Einladungsbudget: `NotificationService::group_invite_status()` (gleiche Grenze wie die Versanddrosselung, max(20, 2 × Plätze)/24 h, zählt wartende und versendete Einladungsmails plus Einladungen ohne Mail-Zeile). `GroupsScreen::invite_list()` lädt bei aufgebrauchtem Budget nicht mehr ein (vorher entstand ein Platz ohne Mail) und lädt bereits enthaltene Adressen nicht doppelt ein; die Besitzerseite zeigt „You can send N more invitations …“ bzw. „The invitations for today are used up … in <Dauer>, on <Zeitpunkt>“ (A11Y-Dokumentprüfung im Test).
**Test:** `LearnerUiTest::test_owner_rest_and_invitation_budget`; `ApiContractTest` (Snapshot `docs/api/routes.v1.json` mit `CCAP_UPDATE_SNAPSHOTS=1` aktualisiert, CT-02/03 401 für die neuen Routen).
**Abweichungen (im API-Changelog):** `{member}` = Platz-ID (wie R-32, keine Platz-UID); `bind_to_address` folgt der Gruppeneinstellung (E-28) und wird nur gemeldet; `reason` bei R-37 geprüft, nicht gespeichert. In der Verwaltung zählen „bereits enthalten“ und „Budget“ unter „Without a free seat“.

## UI-F3 · Einstieg „Prüfungsfehler üben“ und „fällig“ per REST

**Gebaut:** `SmartTrainer::entry()` (Modi `due`, `exam_mistakes`) über die vorhandenen Dienste `SmartTrainer::due()` und `ErrorTraining::from_exam()`; R-07 `GET /courses/{course}/next-question` nimmt `mode=due` und `mode=exam_mistakes` (`exam_session` optional = letzter ausgewerteter Versuch). Antwort wie R-07 plus `mode`, `remaining`, `next_due`, `exam_session`. `TrainerView` (fällig) nutzt dieselbe Methode.
**Test:** `LearningListsTest::test_GM_070_071_error_training` (erweitert: letzter/expliziter Versuch, fremder Versuch 404, ungültig 422, nichts fällig → `question: null`, fällig → Frage mit `next_due`, 401 ohne Anmeldung).
**Offen:** kein eigener Lernbereich-Einstieg „Prüfungsfehler üben“ (z. B. auf der Ergebnisseite des Prüfungsraums) – nur REST, wie beauftragt. `due` ist eine Ergänzung der R-07-Modusliste.

## UI-F4 · R0-27 SET-070

Code: Grant-Quelle `free_enrollment` (`Access\SelfEnrollment::SOURCE`), Einstellung `enrollment.self_enrollment`. R0-27 Zeile `enrollment.self_enrollment` und SET-070 auf Quelle `free_enrollment` (E-348) angeglichen; Einstellungsname bleibt. Kein Code geändert, kein Widerspruch zum Code mehr.

## Dateien

Neu: `src/Question/QuizPool.php`, `src/Learner/GroupOwnerRoutes.php`, dieses Dokument.
Geändert: `src/Admin/EditorScreens.php`, `assets/js/editor-notices.js`, `src/Question/QuestionPresenter.php`, `src/Learning/AnswerService.php`, `src/Learning/SmartTrainer.php`, `src/Activity/ActivityProgress.php` (nur Doc-Typ), `src/Activity/Providers/QuizProvider.php`, `src/Publish/CourseValidator.php`, `src/Access/GroupService.php` (`has_address`), `src/Admin/GroupsScreen.php` (`invite_list`), `src/Notification/NotificationService.php` (`group_invite_status`), `src/Learner/GroupsView.php` (`group_id` öffentlich, Budgetanzeige), `src/Learner/LearnerRoutes.php`, `src/Learner/TrainerView.php`, `docs/api/routes.v1.json`, `docs/API-CHANGELOG.md`, `docs/r0/R0-27-settings-register.md`, `docs/user/autoren.md`, `tools/qa/e2e/live-setup.php` (Kommentar), Tests `ActivityTest`, `AuthoringTest`, `LearnerUiTest`, `LearningListsTest`.
Nicht berührt: `Plugin.php`, `AuditView.php`, `LearningArea.php`, `SettingsSchema.php`, `Schema.php`, `languages/*`. Keine neue Audit-Aktion, kein neuer Hook, keine neuen Uninstall-Daten (Fortschrittsdaten und Idempotenzschlüssel liegen in vorhandenen Tabellen).

Neue msgids: 7 (davon 4 mit Plural).
