# R0-07 · RBAC-/IDOR-Matrix Course Captain (Entwurf)

**Status:** **Entwurf – Freigabe offen.** Task R0-07 ist **nicht** abgehakt. Es gibt keine Implementierung, keine Rollen-/Capability-Registrierung und keinen Testlauf.
**Stand:** 16.11.2026
**Bezug:** TODO R0-07 (DoD: WP-Capabilities, Rollen, Objekt-Scope, REST/Admin/Medien/CLI/Export-Matrix), R0-RBAC-001, R0-RBAC-002, CORE-SPEC-001, ID-01…ID-06 (Konzept 34.1); Konzept Kap. 17, 35.1, 36, 43.2/43.3; `docs/r0/R0-06-datenmodell-adr.md` (Entitäten E-01…E-52, Tabellen §4); `docs/r0/R0-02-inventar/D-admin-frontend-api.md` (§3 Admin-Menü, §4.1 W1–W52, §3.3 admin-post/AJAX, §6.1 Auth), `C-infrastruktur-content.md` (§2.5 Privacy, §2.6/§4.4 CLI, §3.5 Ablage, §4.3 Cron, §4.5 Exporter/Eraser), `A-lernen-pruefung.md` (§2.2 AccessService, §2.5 Gate/geschützte Dateien), `B-commerce.md` (§2.4 Gruppen, §4.1 Webhooks); `docs/r0/R0-05-golden-master.md` (Fixtures §2.7, GM-049/058/151/154); `docs/r0/R0-10-import-medien-sicherheit.md` (§4.3 Auslieferung, R10-D4).
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77` (nur gelesen, nicht verändert). Pfade in Abschnitt 6 sind relativ zu `bootspruefung-learning/`.
**Arbeitsannahmen aus `ENTSCHEIDUNGEN.md` (noch nicht beschlossen):** Annahme **E-05** (nur `course-captain/v1`, Cookie + Nonce, keine Token-Auth in R1), Annahme **E-06** (einheitlicher Fehlerkatalog), Annahme **E-07/E-49** (Single-Site, Netzwerkaktivierung blockiert), Annahme **E-14/E-15** (Drip und Teilumfang in Free), Annahme **E-17** (Gruppenbesitzer verwaltet in Free), Annahme **E-18** (Rechtestatus-Sperre), Annahme **E-19** (Audit-Viewer Free), Annahme **E-21** (Karteikarte statt Praxis), Annahme **E-27** (Scopes `view`, `download`, `exam`), Annahme **E-28** (Option „nur die eingeladene Adresse“), Annahme **E-30** (Kontopflicht beim Gastkauf), Annahme **E-39** (keine schreibenden Workflow-Aktionen in v1), Annahme **E-46** (fail-closed bei Einlösen), Annahme **E-47/E-48** (Hybrid-Speicherung, FKs nur zwischen `cc_`-Tabellen). Weicht ein Beschluss ab, sind die markierten Zeilen anzupassen.

---

## 0. Kurzfassung

| Größe | Wert |
|---|---:|
| Fachrollen (Abschnitt 2) | 8 |
| neue WP-Rollen (`cc_course_manager`, `cc_author`, `cc_trainer`, `cc_support`) | 4 |
| genutzte WP-Kernrollen (`administrator`, `subscriber`) | 2 (nur `administrator` erhält `cc_*`-Caps) |
| primitive Capabilities `cc_*` (Abschnitt 3.1) | 24 |
| Meta-Capabilities mit `map_meta_cap`-Regel (Abschnitt 3.2) | 24 |
| Objektbeziehungen (Abschnitt 3.3) | 9 |
| REST-Routengruppen `course-captain/v1` (Abschnitt 4.1) | 21 (aus W1–W52 + 10 Nebenrouten; W21–W23, W29–W44, W52 entfallen) |
| Admin-Screens / admin-post-AJAX-Aktionen (4.2/4.3) | 24 / 27 |
| IDOR-/RBAC-Negativtests (Abschnitt 5) | 96 (`RBAC-001…RBAC-096`) |
| LB-Abgleich: stärker / schwächer (Abschnitt 6) | 14 / 11 |
| neuer Entscheidungsbedarf (Abschnitt 7) | 7 (`D07-1…D07-7`) |

**Kernaussage:** LearnBoating prüft **Eigentum am eigenen Lernobjekt** (Versuch, Sammlung, Gruppe, Frage-Token) sauber und meist mit 404. Es kennt aber **keine Rollen unterhalb des Administrators**, keine Objektrechte für Inhalte (alles oder nichts über drei grobe Caps) und gewährt mit dem Default `default_access = logged_in` **jedem angemeldeten Konto Vollzugang**. Course Captain übernimmt die Eigentumsprüfungen und ergänzt Rollen, Meta-Caps, Staff-Zuweisungen, Lernsperre und deny-by-default.

---

## 1. Grundsätze (verbindlich für R1-03)

| Nr. | Regel | Beleg |
|---|---|---|
| P-01 | **Deny by default.** Fehlt Objektzuweisung, aktiver Grant, Site-Kontext, Kursstatus oder Connector, wird verweigert. Es gibt keinen Modus „Zugang für alle Angemeldeten“ als Default (Abweichung von LB, Abschnitt 6 L-W1). | Konzept 36 |
| P-02 | Jede Entscheidung = **Capability + Objektbeziehung + Site + Status** (Nutzer, Kurs, Grant, Versuch). Ein zentraler `PolicyService::decide(actor, action, object, context)` liefert `allow` oder `deny` mit Grund. Die WP-Meta-Caps (3.2) sind seine Fassade für `current_user_can()`. | Konzept 36, R1-03 |
| P-03 | **Alle Einstiege nutzen denselben Dienst:** REST, admin-post, AJAX, Block-Editor/Core-REST, Shortcode/Block-Render, `the_content`/Feeds/Suche, Medien-Endpunkt, Upload, Cron, CLI, Webhook, PHP-API, Exporter/Eraser. | Konzept 36 „Prüfpunkte“ |
| P-04 | **Nonce ≠ Recht.** Nonce schützt nur vor CSRF. Jede schreibende Aktion prüft zusätzlich die Cap. REST: `X-WP-Nonce` (`wp_rest`), admin-post/AJAX: Nonce je Aktion **und Objekt-ID** (`cc_<aktion>_<id>`). | Konzept 36, LB D §3.3 |
| P-05 | `manage_options` wird **nie** als LMS-Autorisierung verwendet. Auch Protokoll, Einstellungen und Systemstatus bekommen eigene `cc_*`-Caps. | Konzept 36 |
| P-06 | **WP-Administrator ≠ Inhaltseigentümer.** Der Administrator hat alle `cc_*`-Caps, wird dadurch aber nicht Autor oder Trainer eines Kurses. Lesen fremder Versuchsdaten ist auch für ihn auditpflichtig. | Konzept 36 |
| P-07 | **Rollen addieren keine Objekte.** Wer Autor von Kurs A und Lernender in Kurs B ist, hat Autorenrechte nur auf A und Lernrechte nur auf B. | Konzept 35.1 |
| P-08 | **Fremd = unsichtbar.** Fremde oder nicht sichtbare Objekte ergeben **404 `cc_not_found`**, identisch zu einer nicht existierenden ID (kein Existenz-Orakel). **403 `cc_forbidden`** nur, wenn das Objekt dem Akteur legitim bekannt ist (z. B. veröffentlichter Kurs ohne Grant, eigener Versuch nach Widerruf). **401 `cc_auth_required`** ohne Anmeldung. **423/403 `cc_account_locked`** bei Lernsperre (D07-2). | LB `ExamService.php:1034-1042`, Annahme E-06 |
| P-09 | **Keine Nutzer-ID aus dem Payload** für „eigene“ Aktionen. Die ID kommt aus der Sitzung. Parameter wie `user_id` sind nur in Staff-Routen zulässig und werden dort über Meta-Caps geprüft. | LB `LearnerController.php:24-26` |
| P-10 | **Fremde Rollen bleiben unangetastet.** Aktivierung ergänzt Caps nur bei `administrator` und legt die vier `cc_`-Rollen an. Deaktivierung entfernt nichts, Deinstallation entfernt nur `cc_*`-Caps und die eigenen Rollen (Option, Nutzer behalten ihre übrigen Rollen). | Konzept 17 |
| P-11 | **Änderungen wirken sofort.** Rollen-, Grant-, Staff- und Sperränderungen invalidieren Policy-Memo, Objekt-Cache-Gruppe `cc_policy` und markieren Antworten `no-store` (R1-25). Kein Warten auf Cron. | Konzept 17 |
| P-12 | **Systemakteure** (`system:cron`, `system:cli`, `connector:<id>`) rufen nie `current_user_can()` auf. Sie haben eine feste, je Job/Kommando definierte Erlaubnis (4.6–4.8) und schreiben einen Audit-Eintrag mit `actor_type`. | R0-06 `cc_grant_event.actor_type` |

---

## 2. Fachrollen → WP-Rollen-Defaults

| Fachrolle (Konzept 35.1) | WP-Abbildung (Default) | Woher kommt das Objektrecht? | Was die Rolle **nie** darf |
|---|---|---|---|
| **Lernender** (`learner`) | WP `subscriber` (oder jede Rolle mit `read`); **keine** `cc_*`-Cap (Annahme gemäß Konzept 43.3 „WP Subscriber oder eigene minimal privilegierte Rolle“) | effektiver Grant (`cc_grant` aktiv, Scope, Gültigkeit) oder Free-Regel (`cc_access_rule`) | fremde Versuche, Antworten, Zertifikate, Fortschritte; Inhalte ohne Grant |
| **Käufer** (`buyer`) | keine Rolle; WP-Konto mit `read`. Beziehung über `cc_group.owner_user_id` bzw. `cc_grant.source_*` (R0-06 E-38) | Besitz eines Platz-Pools (Gruppe) aus Kauf oder manueller Anlage | Lernleistungen der Platzinhaber; eigener Lernzugang nur, wenn ihm ein Platz/Grant zugewiesen ist (35.1) |
| **Gruppenverwalter** | Free: identisch mit Gruppenbesitzer (`owner_user_id`, Annahme E-17). Pro: delegierte Verwalter (künftige Relation, D07-1) | eigene bzw. delegierte Gruppe | Fortschritt/Antworten der Mitglieder (nur Platz- und Einladungsstatus); fremde Gruppen |
| **Trainer / Tutor** (`trainer`) | neue Rolle `cc_trainer` (`read`, `cc_view_reports`, `cc_view_learner_details`, `cc_grade_attempts`) | **Staff-Zuweisung** je Kurs, optional eingeschränkt auf Gruppen (`cc_course_staff`, D07-1) | Inhalte bearbeiten (nur mit Zusatz-Cap), Grants ändern, Zertifikate allein widerrufen, eigene Versuche bewerten |
| **Autor** (`course_author`) | neue Rolle `cc_author` (`read`, `upload_files`, `cc_edit_courses`) | `post_author` des `cc_course` oder Staff-Zuweisung `author` | veröffentlichen (ohne `cc_publish_courses`), fremde Kurse, Lernerdaten, Grants, Prüfungsdaten (35.1 „Autorenrechte verleihen keine Prüfungs- oder Bestellverwaltung“) |
| **Kursmanager** (`course_manager`) | neue Rolle `cc_course_manager` (alle fachlichen Caps 1–18, 22, 24; Tabelle 3.1) | Default **alle Kurse der Site** (Konzept 17); kursbezogener Manager siehe D07-3 | Einstellungen, Integrationen/Secrets, Wartung; sich selbst Admin-Caps geben |
| **Support** | neue Rolle `cc_support` (`read`, `cc_view_learner_details`, `cc_lock_learners`, `cc_view_system_status`, `cc_view_audit_log`) | site-weit, **minimiert** (Status, Grants, Gruppenplätze; keine Antworten, Konzept 43.3 „Supportzugriff“) | Versuchsdetails, Grants ändern, Inhalte |
| **Admin** (`site_admin`) | WP `administrator`; erhält bei Aktivierung **alle 24** `cc_*`-Caps (P-06) | Site | Bewerten eigener Versuche (`do_not_allow`, RBAC-032); Lesen fremder Antworten ohne Audit |

**Weitere WP-Rollen:** `editor`, `author`, `contributor`, `shop_manager` erhalten **keine** `cc_*`-Caps automatisch (D07-6). Ein Shop-Manager hat damit keine LMS-Rechte (ID-04 „keine implizite Eskalation über Woo-/Suite-Rolle“). Betreiber können Caps über den Tab „Rollen & Rechte“ oder ein Rollen-Plugin zuweisen; geprüft werden Caps, nie Rollennamen (Konzept 43.3).

---

## 3. Capabilities

### 3.1 Primitive Capabilities (24) und Rollen-Defaults

`A` = administrator, `M` = cc_course_manager, `S` = cc_support, `Au` = cc_author, `T` = cc_trainer. Lernende, Käufer und Gruppenverwalter haben **keine** primitive `cc_*`-Cap.

| # | Slug | Bedeutung (Objektgrenze über Meta-Cap 3.2) | A | M | S | Au | T |
|---|---|---|---|---|---|---|---|
| 1 | `cc_edit_courses` | eigene/zugewiesene Kurse, Module, Lektionen, FAQ, Tipps, Prüfungsregeln und Fragen anlegen und bearbeiten | ✓ | ✓ | – | ✓ | – |
| 2 | `cc_edit_others_courses` | alle Kurse der Site bearbeiten, Entwürfe anderer lesen | ✓ | ✓ | – | – | – |
| 3 | `cc_publish_courses` | Kurs-/Prüfungsregel veröffentlichen, archivieren (Kursrevision, R0-06 E-02) | ✓ | ✓ | – | – | – |
| 4 | `cc_delete_courses` | Kurs/Modul/Lektion in Papierkorb (nur ohne Lernhistorie endgültig) | ✓ | ✓ | – | – | – |
| 5 | `cc_manage_question_bank` | kursübergreifende Fragenbank: Tags, Wiederverwendung, Massenänderung, Fragen ohne Kurs | ✓ | ✓ | – | – | – |
| 6 | `cc_import_content` | Pakete hochladen, prüfen, freigeben, Rollback (Veröffentlichung braucht zusätzlich #3) | ✓ | ✓ | – | – | – |
| 7 | `cc_export_content` | Kurs-/Paketexport (R1-15, R10-D5) | ✓ | ✓ | – | – | – |
| 8 | `cc_release_rights` | Rechtestatus freigeben/sperren, Sammelfreigabe mit Begründung (Annahme E-18) | ✓ | ✓ | – | – | – |
| 9 | `cc_manage_grants` | Grants manuell anlegen, verlängern, aussetzen, widerrufen (Audit, Begründung) | ✓ | ✓ | – | – | – |
| 10 | `cc_manage_access_rules` | Free-/Teilumfang-/Drip-Regeln je Kurs | ✓ | ✓ | – | – | – |
| 11 | `cc_manage_groups` | alle Gruppen anlegen, verwalten, Besitzer ändern (Besitzer selbst brauchen keine Cap) | ✓ | ✓ | – | – | – |
| 12 | `cc_view_reports` | aggregierte Berichte (Kurs, Gruppe) | ✓ | ✓ | – | – | ✓ |
| 13 | `cc_view_learner_details` | personenbezogene Lernübersicht (Status, Fortschritt, Grants, Plätze), minimiert | ✓ | ✓ | ✓ | – | ✓ |
| 14 | `cc_view_attempt_details` | Antworten/Snapshots fremder Versuche lesen (immer Audit, Begründung D07-5) | ✓ | ✓ | – | – | – |
| 15 | `cc_grade_attempts` | offene Antworten bewerten (R1-09) | ✓ | ✓ | – | – | ✓ |
| 16 | `cc_manage_learner_progress` | Fortschritt zurücksetzen, Versuch annullieren (mit Grund) | ✓ | ✓ | – | – | – |
| 17 | `cc_manage_certificates` | Zertifikat ausstellen, korrigieren, widerrufen (CORE-SPEC-006) | ✓ | ✓ | – | – | – |
| 18 | `cc_lock_learners` | Lernsperre setzen/aufheben (D07-2) | ✓ | ✓ | ✓ | – | – |
| 19 | `cc_manage_settings` | Einstellungs-Tabs, Pro-Lizenz | ✓ | – | – | – | – |
| 20 | `cc_manage_integrations` | Connectoren, Webhooks, Secrets | ✓ | – | – | – | – |
| 21 | `cc_view_audit_log` | Protokollansicht (Annahme E-19), Redaction bleibt | ✓ | – | ✓ | – | – |
| 22 | `cc_view_system_status` | Systemstatus, `/status`, Diagnose | ✓ | ✓ | ✓ | – | – |
| 23 | `cc_run_maintenance` | Reparaturaktionen, Migration anstoßen, Speicher-Neuprüfung (R1-38) | ✓ | – | – | – | – |
| 24 | `cc_assign_staff` | Autor-/Trainer-Zuweisungen und `cc_`-Rollen vergeben (nie `administrator`, nie Caps über dem eigenen Satz) | ✓ | ✓ | – | – | – |

**Abgleich mit Konzept 17/36:** `cc_manage_courses` → #1/#2; `cc_manage_question_bank` → #5; `cc_grade_assessments`/`cc_grade_assigned` → #15 + Zuweisung; `cc_manage_enrollments` → #9/#10; `cc_view_course_reports` → #12; `cc_manage_integrations` → #20; `cc_manage_settings` → #19; `cc_manage_certificates` → #17; `cc_view_own_learning`, `cc_manage_own_seats`, `cc_export_personal_data` werden **Meta-Caps** (M-02, M-15, M-23), weil sie keine Rolle, sondern eine Objektbeziehung ausdrücken.

### 3.2 Meta-Capabilities (24) und `map_meta_cap`-Regel

Umsetzung: Filter `map_meta_cap` (Priorität 10). Rückgabe ist eine Liste primitiver Caps oder `['do_not_allow']`. Objektladen und Beziehungsprüfung erledigt der `PolicyService` (Request-Memo, P-11). Für Lernaktionen ist die primitive Cap `read`: Ein Konto ohne Rolle auf der Site hat kein `read` und ist damit automatisch gesperrt (3.4).

| # | Meta-Cap (Argumente) | Regel → primitive Caps | Beziehung |
|---|---|---|---|
| M-01 | `cc_view_course` (course) | veröffentlicht → `read` (Katalog, auch Gast über Block); Entwurf/archiviert → `cc_edit_course` (Vorschau); sonst `do_not_allow` → 404 | – |
| M-02 | `cc_learn` (course, scope=`view\|download\|exam`) | Kurs veröffentlicht **und** nicht gesperrt **und** (effektiver Grant mit Scope, gültig jetzt **oder** Free-Regel/Teilumfang) **und** Drip frei → `read`; sonst `do_not_allow` | eigener Grant |
| M-03 | `cc_edit_course` (course) | eigener/zugewiesener Kurs → `cc_edit_courses`; fremder → `cc_edit_others_courses`; Kurs mit veröffentlichter Revision zusätzlich `cc_publish_courses`, wenn die Änderung sofort live ginge (sonst entsteht ein Entwurf) | eigener Kurs, zugewiesener Kurs (author) |
| M-04 | `cc_publish_course` (course) | M-03 **und** `cc_publish_courses` | wie M-03 |
| M-05 | `cc_delete_course` (course) | M-03 **und** `cc_delete_courses`; mit Lernhistorie nur Archiv | wie M-03 |
| M-06 | `cc_edit_course_item` (module\|lesson\|faq\|tip\|exam_rule\|blueprint\|variant) | Kurs des Objekts auflösen (Modul über `cc_course_module`, Lektion über `post_parent`, Regel über `course_id`) → M-03 für **jeden** Kurs, der das Modul nutzt | eigener Kurs |
| M-07 | `cc_edit_question` (question) | Frage nur in Pools eigener/zugewiesener Kurse **oder** `author_user_id` = Akteur und in keinem fremden Kurs → `cc_edit_courses`; sonst `cc_edit_others_courses` bzw. `cc_manage_question_bank` | eigener Kurs |
| M-08 | `cc_use_attempt` (exam_session) | `user_id` = Akteur **und** Status `running` **und** Frist nicht abgelaufen **und** M-02(`exam`) jetzt → `read`; fremd → `do_not_allow` (404) | eigener Versuch |
| M-09 | `cc_view_attempt` (exam_session\|attempt) | eigener Versuch → `read` (Feedback-Regel E-20 gilt); zugewiesener Kurs/Gruppe und Versuch in Bewertung → `cc_grade_attempts`; sonst → `cc_view_attempt_details` (+ Audit); fremd ohne Beziehung → `do_not_allow` | eigener Versuch, zugewiesener Kurs/Gruppe |
| M-10 | `cc_grade_attempt` (exam_session) | Versuch des Akteurs selbst → `do_not_allow` (auch Admin); zugewiesener Kurs/Gruppe → `cc_grade_attempts`; sonst `cc_grade_attempts` + `cc_edit_others_courses` | zugewiesener Kurs |
| M-11 | `cc_view_learner` (user, course?) | Akteur = user → `read`; Trainer: user hat Grant/Platz in zugewiesenem Kurs bzw. zugewiesener Gruppe → `cc_view_learner_details`; Manager/Support → `cc_view_learner_details`; Gruppenbesitzer → `do_not_allow` | zugewiesener Kurs/Gruppe |
| M-12 | `cc_manage_learner_progress` (user, course) | Akteur = user (eigenen Fortschritt zurücksetzen, falls Kurs erlaubt) → `read`; sonst `cc_manage_learner_progress` | – |
| M-13 | `cc_manage_grant` (grant) | `cc_manage_grants`; Grant fremder Site → `do_not_allow`; Grant mit externer Quelle (Woo/Voucher) → nur Suspend/Notiz, kein Löschen (R0-09) | – |
| M-14 | `cc_create_grant` (course) | `cc_manage_grants` **und** M-01 | – |
| M-15 | `cc_manage_group` (group) | `owner_user_id` = Akteur (Free, Annahme E-17) → `read`; delegierter Verwalter (Pro, D07-1) → `read`; sonst `cc_manage_groups`; Gruppe `revoked` → nur Lesen | eigene Gruppe |
| M-16 | `cc_view_group_progress` (group) | Besitzer → `do_not_allow` (35.1); Trainer mit Gruppenzuweisung → `cc_view_reports`; sonst `cc_view_reports` + Manager-Scope | zugewiesene Gruppe |
| M-17 | `cc_claim_seat` (seat\|join_token) | angemeldet, Token-Hash gültig und nicht abgelaufen, Gruppe aktiv, freier Platz; bei Option „nur eingeladene Adresse“ (Annahme E-28) E-Mail des Kontos = Einladung → `read` | – |
| M-18 | `cc_view_certificate` (certificate) | `user_id` = Akteur → `read`; sonst `cc_manage_certificates` bzw. M-11; öffentliche Verifikation läuft **ohne** Meta-Cap über Token mit Minimaldaten | eigenes Zertifikat |
| M-19 | `cc_manage_certificate` (certificate) | `cc_manage_certificates`; eigenes Zertifikat → `do_not_allow` | – |
| M-20 | `cc_download_file` (media_uid\|attachment) | Objekt geschützt? nein → nicht über diesen Endpunkt (404); ja → M-03 für einen referenzierenden Kurs **oder** M-02(`download` bzw. `view`) für mindestens einen veröffentlichten referenzierenden Kurs; Prüfungsmedien nur Inhaber des laufenden Versuchs (R0-10 §4.3) | eigener Grant |
| M-21 | `cc_manage_import` (import_batch) | `cc_import_content`; Schritt `publish/activate` zusätzlich `cc_publish_courses`; Batch fremder Site → `do_not_allow` | – |
| M-22 | `cc_download_export` (export) | Ersteller = Akteur **und** nicht abgelaufen **und** Recht der Exportart (Inhalt #7, Bericht #12, Grants #9) noch vorhanden; sonst `do_not_allow` | eigener Export |
| M-23 | `cc_export_personal_data` (user) | Akteur = user → `read` (auch bei Lernsperre, Betroffenenrecht); sonst WP-Kern `export_others_personal_data` bzw. `erase_others_personal_data` | – |
| M-24 | `cc_lock_learner` (user) | `cc_lock_learners`; Ziel hat höhere Caps als Akteur (z. B. Admin) → `do_not_allow`; sich selbst → `do_not_allow` | – |

**CPT-Mapping (Block-Editor):** `cc_course`, `cc_module`, `cc_lesson`, `cc_faq`, `cc_tip` registrieren `map_meta_cap => true` und `capabilities` mit `edit_posts → cc_edit_courses`, `edit_others_posts → cc_edit_others_courses`, `publish_posts → cc_publish_courses`, `read_private_posts → cc_edit_others_courses`, `delete_posts → cc_delete_courses`, `delete_others_posts → cc_edit_others_courses`, `create_posts → cc_edit_courses`. WP-Meta-Caps `edit_post`/`delete_post`/`read_post` für diese Typen leitet der Filter auf M-03/M-05/M-06 um, damit Staff-Zuweisungen zählen (WP allein kennt nur `post_author`).

### 3.3 Objektbeziehungen

| Nr. | Beziehung | Datenquelle | Prüfung |
|---|---|---|---|
| B-1 | **eigener Kurs** | `wp_posts.post_author` des `cc_course` | `post_author = actor` |
| B-2 | **zugewiesener Kurs** (Autor/Trainer) | **neu:** `cc_course_staff (course_id, user_id, staff_role, group_id NULL, assigned_by, created_at)`, `UNIQUE (course_id, user_id, staff_role, group_id)` – fehlt in R0-06 (D07-1) | Zeile mit passender `staff_role`, Kurs auf derselben Site |
| B-3 | **zugewiesene Gruppe** (Trainer) | `cc_course_staff.group_id` + `cc_group_course` | Lernender hat aktiven Platz (`cc_seat.status=active`) in dieser Gruppe |
| B-4 | **eigene Gruppe** (Besitzer) | `cc_group.owner_user_id` | `= actor`; Pro: zusätzliche Verwalter-Relation (D07-1) |
| B-5 | **eigener Versuch** | `cc_exam_session.user_id`, `cc_attempt.user_id` | `= actor` |
| B-6 | **eigener Grant / effektiver Zugang** | `cc_grant` (`user_id`, `course_id`, `scopes`, `status=active`, `valid_from ≤ now ≤ valid_until`) ∪ `cc_access_rule` | Laufzeitprüfung gegen `now`, **nicht** abhängig vom Ablauf-Cron (LB `AccessService.php:408-409`) |
| B-7 | **eigener Platz** | `cc_seat.user_id` | `= actor`; Käufer sieht Plätze über B-4, nicht über B-7 |
| B-8 | **eigenes Zertifikat** | `cc_certificate.user_id` | `= actor` |
| B-9 | **eigener Export/Import** | `cc_import_batch.created_by`, Export-Datensatz (R1-15/R1-17) | Ersteller; Import ist site-weit für #6 sichtbar |

### 3.4 Site-Scope und Statusregeln

- **Site:** Alle `cc_`-Tabellen sind je Site (R0-06 §6.6). Der PolicyService lädt Objekte nur aus `$wpdb->prefix` der aktuellen Site. Nach `switch_to_blog()` wird der Memo verworfen. Super-Admins bestehen WP-bedingt jede Cap außer `do_not_allow`. Deshalb sind strukturelle Verbote (Selbstbewertung M-10, Selbstsperre M-24) als `do_not_allow` formuliert. Cross-Site-Identität bleibt R4 (ID-06, Annahme E-07/E-49).
- **„Gesperrter Nutzer“** ist für Tests (Abschnitt 5) dreifach definiert: (a) **Lernsperre** `cc_learner_lock` mit Grund und Audit (D07-2); (b) Konto **ohne Rolle** auf der Site (kein `read`); (c) Multisite `spam`/`deleted` (bedingt). Folgen: alle Lern-Meta-Caps (M-02, M-08, M-17) verweigert; Grants bleiben unverändert (keine Datenänderung, R0-09); eigene Zertifikate sind nicht herunterladbar, die öffentliche Verifikation bleibt; Datenschutzanträge bleiben möglich (M-23).
- **Kursstatus:** Entwurf/archiviert → Lernen und Prüfungsstart 404 (R0-05 GM-00x „Kursstatus“); laufende Versuche eines archivierten Kurses dürfen abgegeben werden (R0-23).
- **Grantstatus:** nur `active` innerhalb `valid_from/valid_until` zählt. `pending_*`, `suspended`, `expired`, `revoked`, `failed` → `cc_content_locked` mit Grund (`pending`, `suspended`, `expired`, `revoked`). Der Lernende sieht seinen eigenen Status (kein 404, P-08).
- **Connector deaktiviert:** PHP-API- und Webhook-Aufrufe dieses Connectors → `forbidden`/`connector_disabled`; bestehende Grants bleiben (Konzept 36).

### 3.5 Rollenwechsel und Eskalationsschutz

1. Caps werden je Anfrage aus der Rolle gelesen. Der Memo wird bei `set_user_role`, `add_user_role`, `remove_user_role`, `profile_update`, `remove_user_from_blog`, Änderungen an `cc_course_staff`, `cc_grant`, `cc_seat`, `cc_learner_lock` verworfen (P-11).
2. Ein gültiges REST-Nonce überlebt eine Rollenänderung. Das ist zulässig, weil die Cap bei jeder Anfrage neu geprüft wird (RBAC-006).
3. `cc_assign_staff` darf nur `cc_author`/`cc_trainer` (Manager zusätzlich `cc_support`) vergeben. `administrator` und `cc_course_manager` vergibt nur, wer `promote_users` **und** alle Caps der Zielrolle besitzt (Filter `editable_roles`). Niemand ändert die eigenen Rollen über CC-Oberflächen.
4. Lernsperre gegen Konten mit höheren Caps als der Akteur ist verboten (M-24).
5. Kontolöschung/Merge: Staff-Zuweisungen werden gelöscht, Gruppenbesitz wird `0` (LB `GroupService.php:444-469`), Versuche bleiben nach Retention (Annahme E-51). Merge nur nach R0-09.
6. Datenschutz-Schutzliste für automatische Kontolöschung (LB `AccountDeletion.php:76-83`) wird um alle Konten mit `cc_*`-Caps ergänzt.

### 3.6 Audit-Pflichten (Minimum)

Auditpflichtig (`cc_audit_log`, Akteur, Objekt, Grund): Lesen fremder Versuchsdetails (M-09 über #14), Bewertung, Grant-Änderung, Lernsperre, Staff-/Rollenzuweisung, Zertifikat ausstellen/korrigieren/widerrufen, Import-Freigabe/Rollback, Rechtefreigabe, Export von Personendaten (Bericht, Grants), Einstellungen, Integrationen, Wartungsaktionen, CLI-Schreibkommandos, Webhook-Ablehnungen (gedrosselt).

---

## 4. Matrix Capability × Oberfläche

Spalten: **Cap** = erforderliche primitive/Meta-Cap; **Scope** = Objektprüfung; **Auth/Nonce** = Authentifizierung und CSRF-Schutz.

### 4.1 REST `course-captain/v1` (Routengruppen, abgeleitet aus LB W1–W52)

Konvention: `permission_callback` prüft Anmeldung, Sperre und primitive Cap. Die Objektprüfung (Meta-Cap) läuft im Callback **vor** jeder Seiteneffekt-Operation über den PolicyService und ergibt 404/403 nach P-08. Alle Antworten `Cache-Control: no-store, private` (LB `LearnerController.php:469-493`). Auth = Cookie + `X-WP-Nonce` (`wp_rest`), Annahme E-05.

| RG | Route(n) (Vorschlag) | aus LB | Cap | Scope-Prüfung | Auth/Nonce |
|---|---|---|---|---|---|
| RG-01 | `GET /status` | W1 | `cc_view_system_status` | – | Cookie+Nonce |
| RG-02 | `GET /imports`, `GET /imports/{batch}`, `POST /imports/{batch}/step` | W2 | M-21 | Batch auf aktueller Site; Status erlaubt Schritt; `publish/activate` nur mit #3 | Cookie+Nonce |
| RG-03 | `GET /me/courses` | W3 | angemeldet, nicht gesperrt | nur Kurse mit eigenem Grant/Free-Regel/Platz; kein `user`-Parameter | Cookie+Nonce |
| RG-04 | `GET /courses/{course}` | W4 | M-01; Features aus M-02 je Scope | Entwurf/Archiv → 404 außer M-03 (Vorschau); Antwort ohne Inhalte gesperrter Scopes | Cookie+Nonce |
| RG-05 | `GET /courses/{course}/next-question` | W5 | M-02(`view`) | `module` ∈ Kurs, `topic` ∈ Kurs, `collection` eigene **und** im Kurs, `exam` = eigener ausgewerteter Versuch im Kurs; sonst 404 | Cookie+Nonce |
| RG-06 | `POST /answers`, `POST /answers/reveal` | W6, W7 | M-02 zur Antwortzeit | Frage-Token HMAC über (user, course, revision, Ablauf) wie LB; Frage gehört nicht zu eigenem laufendem Versuch; Idempotenz-Key | Cookie+Nonce + Token |
| RG-07 | `PUT/DELETE /courses/{course}/favorites/{question}`, `GET /courses/{course}/saved`, `PUT …/saved/{question}`, `POST/PUT/DELETE /courses/{course}/collections[/{id}]` | W8–W12 | M-02(`view`) | Frage im Pool des Kurses **und** auslieferbar; Sammlung eigene **und** im Kurs. **Delta:** Kurs im Pfad auch für Favoriten (LB W8 ohne Kurs, Abschnitt 6 L-W5) | Cookie+Nonce |
| RG-08 | `GET /courses/{course}/exam`, `POST /courses/{course}/exam-sessions` | W13, W14 | M-02(`exam`) | Prüfungsregel veröffentlicht; Variante ∈ Kurs; Teilumfang-Varianten; Start-Lock je Nutzer | Cookie+Nonce |
| RG-09 | `GET /exam-sessions/{s}`, `PUT …/answers/{pos}`, `PUT …/flag`, `POST …/reveal`, `POST …/submit`, `POST …/abort`, `GET /exam-sessions/{s}/review` | W15–W20 | Lesen M-09, Schreiben M-08 | `user_id` = Akteur sonst 404; Grant bei jeder Schreibaktion neu (LB `ExamService.php:1044-1057`); Frist; Position ∈ Versuch | Cookie+Nonce |
| RG-10 | `GET /courses/{course}/lessons`, `GET …/lessons/{lesson}`, `PUT …/lessons/{lesson}/progress`, `GET/PUT …/flashcards[/{card}]` | W26–W28 (Video → Activity), W24/W25 (→ Karteikarte, Annahme E-21) | M-02(`view`) | Lektion ∈ Kurs, veröffentlicht, Rechtestatus frei, Drip frei (LB `VideoService.php:273-283`); Fortschritt nur eigener | Cookie+Nonce |
| RG-11 | `GET /groups/join/{token}`, `POST /groups/join` | W45, W46 | M-17 | Token-Hash, Ablauf, Gruppe aktiv, Platz frei, E-Mail-Bindung (Annahme E-28); Rate-Limit; Vorschau nur maskierte Daten | Cookie+Nonce |
| RG-12 | `GET /me/groups`, `PUT /me/groups/{g}`, `POST …/invite`, `POST …/token`, `DELETE …/members/{m}` | W47–W51 | M-15 | Besitz **vor** der Aktion (LB T170); Mitglied ∈ Gruppe; keine Fortschrittsdaten; E-Mail-Sichtbarkeit D07-4; Einladungsbudget | Cookie+Nonce |
| RG-13 | `GET /faqs` (öffentlich), `GET /courses/{course}/faqs`, `GET /tips` | LB `/faqs`, `/customer-faqs`, `/tips` | öffentlich / M-02(`view`) / angemeldet | Kurs-FAQ nur mit Grant (LB `CustomerFaq.php:157-159`) | öffentlich bzw. Cookie+Nonce |
| RG-14 | `GET /me/certificates`, `GET /certificates/{uid}/pdf`, `GET /certificates/verify/{token}` | neu (LB hat keine Zertifikate) | M-18 / öffentlich | PDF nur Inhaber oder #17; Verifikation nur Token, Minimaldaten (Status, Kurs, Datum, Name nach Einstellung), Rate-Limit | Cookie+Nonce / keine |
| RG-15 | `GET /reports/courses/{course}`, `GET /reports/courses/{course}/learners/{user}`, `GET /reports/groups/{g}` | neu (R1-10, R0-05 GM-079) | #12 bzw. M-11, M-16 | Trainer nur B-2/B-3; Spalten minimiert | Cookie+Nonce |
| RG-16 | `GET /grading/queue?course=`, `PUT /exam-sessions/{s}/grades/{pos}` | neu (R1-09) | M-10 | zugewiesener Kurs/Gruppe; nie eigener Versuch | Cookie+Nonce |
| RG-17 | `POST /me/privacy-requests` | neu (LB admin-post `bp_privacy_request`) | M-23 | nur eigenes Konto; Limit 5/Tag (LB `SelfService.php:82`) | Cookie+Nonce |
| RG-18 | `GET /media/{media_uid}` (+ Rewrite `/cc-file/{uid}/`) | LB `/datei/<id>` (kein REST) | M-20 | R0-10 §4.3: unbekannt/nicht geschützt → 404 | Cookie (+Nonce bei REST) |
| RG-19 | `POST /webhooks/{connector}` (R3) | LB `/webhooks/paypal`, `/webhooks/dispute` | Connector aktiv | **Signatur im `permission_callback`** (HMAC, Zeitstempel ±300 s), Dedupe `cc_inbox_event` | HMAC, keine Cookies |
| RG-20 | `GET /monitor` | LB `/monitor` | Schlüssel | nur Header `X-CC-Monitor-Key`, `hash_equals`; ohne Schlüssel nur Gesamtstatus; **kein** `?key=` (L-W8) | Header |
| RG-21 | WP-Kern `/wp/v2/cc_course`, `cc_module`, `cc_lesson`, `cc_faq`, `cc_tip`, `/wp/v2/media` | LB `/wp/v2/bp_tip`, `bp_faq`, … | CPT-Mapping 3.2 | `context=edit` nur M-03/M-06; `context=view` für `cc_lesson`/`cc_module`: **kein** `content.rendered` ohne M-02 bzw. M-03 (eigener Controller wie LB `TipsPostsController`); Medien ohne Pfad/Dateiname für geschützte Anhänge (LB `ProtectedFiles.php:331`) | Cookie+Nonce |

**Entfallende LB-Routen:** W21–W23 (Navigation), W29–W44 (Funk, Gerätesimulation), W52 (Prüfungsanmeldung) sind `exclude_domain_specific` (Inventur D §4.1). Test: Route existiert nicht (`rest_no_route` 404). Die App-API `boatingschool/v1` entfällt in R1 (Annahme E-05). Nonce-Erneuerung als AJAX `wp_ajax(_nopriv)_cc_rest_nonce` wie LB (`NonceRefresh.php:24-25`), ohne Rechtewirkung.

### 4.2 Admin-Screens (Menü nach Konzept 43.2)

Jede Seite registriert ihre eigene Cap; die Render-Methode prüft erneut (`wp_die` 403, wie LB). Das Hauptmenü zeigt die erste erlaubte Unterseite (WP-Standard).

| Nr. | Screen | LB-Entsprechung (Inventur D §3.1) | Cap Seite | Objekt-Scope in der Seite |
|---|---|---|---|---|
| S-01 | Übersicht | 1 | eine von #1, #12, #13, #22 | Kennzahlen nur aus erlaubten Kursen |
| S-02 | Kurse (CPT-Liste) | 3 | #1 | Liste per `pre_get_posts` auf eigene/zugewiesene Kurse, außer #2 (WP zeigt Autoren sonst fremde Entwürfe) |
| S-03 | Kurs bearbeiten (Block-Editor) | – (LB nur Import) | M-03 | Staff-Zuweisung; Veröffentlichen M-04 |
| S-04 | Module/Lektionen | 3 | M-06 | Kurs des Objekts |
| S-05 | Fragenbank | 4 | #1 | Fragen eigener/zugewiesener Kurse; Rest nur mit #2/#5 |
| S-06 | Quizzes & Prüfungen (Regeln, Blueprints, Varianten) | 6 | M-06; Freigabe M-04 | Kurs der Regel |
| S-07 | Medien (Import-Medien) | 5 | #1 | Medien referenzierter erlaubter Kurse |
| S-08 | Rechte (Rechtestatus) | 7 | #8 | – |
| S-09 | FAQ / Kurs-FAQ / Tipps (CPT) | 9–13 | CPT-Mapping | Kurs-FAQ über Kurs |
| S-10 | Lernende (Liste, Detail) | 14 (Zugänge, Detail) | #13 | Trainer nur B-2/B-3; Support minimiert |
| S-11 | Versuchsdetail | – | M-09 | Audit beim Öffnen; Begründung D07-5 |
| S-12 | Bewertungen | – | #15 | M-10 je Versuch |
| S-13 | Zugänge (Grants) | 14 | Lesen #13, Ändern #9 | M-13 je Grant |
| S-14 | Kostenlose Bereiche / Drip | 15 | #10 | Kurs |
| S-15 | Gruppen | 16 | #11 | alle Gruppen; Besitzer nutzen das Frontend (RG-12) |
| S-16 | Zertifikate | – | Lesen #13, Ändern #17 | M-19 |
| S-17 | Berichte | – | #12 | M-16, B-2/B-3 |
| S-18 | Inhalte importieren (Liste, `?batch=`) | 2 | #6 | M-21 |
| S-19 | Integrationen | – | #20 | Secrets nie im Klartext ausgeben |
| S-20 | Einstellungen (Tabs nach 43.2) | 20 | #19 | Tab „Rollen & Rechte“: #24 (+ `promote_users` für Rollen) |
| S-21 | Protokoll | 19 (`manage_options`) | #21 | Redaction; keine E-Mail/IP (wie LB) |
| S-22 | Systemstatus | 22 | #22; Aktionen #23 | – |
| S-23 | Pro-Lizenz | – | #19 | nur bei aktivem Add-on |
| S-24 | Profil/Benutzer: Box „Kurszuweisungen“ | – | #24 **und** WP `edit_user(u)` | nur Kurse, die der Akteur verwalten darf |

**Entfallend:** Mobile App (21), Prüfungsanmeldung (8), Mitgliederrabatte (17), Preise (18), Kunden-FAQ-Import mit LB-Format (Teile von 11).

### 4.3 admin-post / AJAX

Alle Aktionen: `current_user_can(Cap)` **vor** `check_admin_referer('cc_<aktion>_<objekt-id>')`, dann Meta-Cap auf das Objekt, dann Bestätigungsseite bei Risikoaktionen (Konzept 43.2 UX-Vertrag). Nur POST. Rückleitung per `wp_safe_redirect`.

| Aktion (Vorschlag) | LB-Vorbild (Inventur D §3.3) | Cap / Meta-Cap | Scope |
|---|---|---|---|
| `cc_grant_create` | `bp_access_grant` | M-14 | Nutzer existiert auf Site; Begründung Pflicht |
| `cc_grant_change` (extend/suspend/revoke/release) | `bp_access_change` | M-13 | Grant-ID + Nonce je Grant |
| `cc_access_rule_save` | `bp_free_rule` | #10 + M-01 | Kurs |
| `cc_group_admin` (anlegen, Status, Plätze, Besitzer) | `bp_group_admin` | #11 | Gruppe |
| `cc_staff_assign` / `cc_staff_remove` | – | #24 + M-03 auf den Kurs | Zielrolle ≤ eigene Caps (3.5) |
| `cc_learner_lock` / `cc_learner_unlock` | – | M-24 | Ziel-Nutzer |
| `cc_progress_reset` | – | M-12 | Nutzer + Kurs |
| `cc_certificate_issue` / `_correct` / `_revoke` | – | M-19 / #17 | Grund Pflicht |
| `cc_override` | `bp_override` | M-06 bzw. M-07 je Objekt | Objekt |
| `cc_rights_release` | `bp_rights_release` | #8 | Sammelaktion mit Begründung |
| `cc_rules_release` | `bp_rules_release` | M-04 auf Kurs der Regel | Kurs |
| `cc_import_upload` / `cc_import_server` / `cc_import_url` | `bp_import_upload/server/url` | #6 | Upload-Validierung R0-10; URL nur `wp_safe_remote_get` |
| `cc_import_action` | `bp_import_action` | M-21 | Batch + Nonce je Batch |
| `cc_faq_import`, `cc_faq_page` | `bp_faq_import`, `bp_faq_page` (+`publish_pages`) | #1 (+ WP `publish_pages`) | – |
| `cc_mapping_remove` | `bp_mapping_remove` | #9 | – (Woo-Zuordnung erst R3) |
| `cc_mail_test` | `bp_mail_test` (`manage_options`) | #19 | an eigene Adresse |
| `cc_settings_save` (je Tab) | `options.php`, `bp_security_save` | #19 (Tab Rollen: #24) | – |
| `cc_integration_save` | `bp_dispute_settings` | #20 | Secrets write-only |
| `cc_golive_tick` | `bp_golive_tick` | #19 | – |
| `cc_storage_recheck` | `?bp_storage_recheck` | #23 | – |
| `cc_maintenance_action` | – | #23 | Bestätigung |
| `cc_notice_dismiss` (admin-post + AJAX) | `bp_notice_dismiss` | `read` + signierte Werte | nur eigene Hinweise |
| `cc_privacy_request` | `bp_privacy_request` | M-23 | eigenes Konto |
| `wp_ajax(_nopriv)_cc_rest_nonce` | `bp_rest_nonce` | – | gibt nur Nonce der eigenen Sitzung |
| Metabox „Lernzugang erforderlich“ (`save_post`) | `bp_required_access` | WP `edit_post(id)` **und** M-01 + M-03 auf den Zielkurs | Zielkurs muss editierbar sein |
| Mediathek-Feld „Kurs · Scope“ (`attachment_fields_to_save`) | `ProtectedFiles::saveFields` (T195) | `edit_post(attachment)` **und** M-03 auf Zielkurs | wie LB plus Kursbezug |
| `cc_export_create` (Inhalt/Bericht/Grants) | CLI-Exporte | #7 / #12 / #9 | Scope der Daten wie Bericht |

**Entfallend:** `bp_member_discount`, `bp_shop_catalog`, `bp_shop_prices`, `bp_exam_committee*`, `bp_chart`, `bp_mobile_*`, `bp_learning_set_entitled_only` (der Modus „alle Angemeldeten“ entfällt, P-01).

### 4.4 Block-Editor, CPT und Frontend-Ausgabe

| Oberfläche | Cap | Scope | Schutz gegen Umgehung |
|---|---|---|---|
| CPT-Bearbeitung (`post.php`, Block-Editor, Autosave, Revisionen) | CPT-Mapping → M-03/M-06 | Staff-Zuweisung; Revisions-Endpunkt `/wp/v2/cc_lesson/{id}/revisions` gleicher Check | Vorschau-Links nur für M-03 |
| Block „Inhalt nur mit Zugang“, Shortcode `[cc_restricted course scope]` | Render: M-02 | Kurs/Scope aus Attributen; unbekannter Kurs → gesperrt | Editor-Speichern: Attribut-Kurs muss M-01 bestehen |
| `the_content`, `the_excerpt`, `the_content_feed`, Suche, Sitemaps, oEmbed, REST `content.rendered` | M-02 bzw. Bypass M-03/`edit_post` | Pflichtangabe am Beitrag | Filter früh und spät (LB `ContentGate.php:132`), Auszug nur manuell (`:162`), Page-Builder-Daten verwerfen (`:151-157`), `NoCache` |
| Lernbereich `/lernen/…` (App-Shell) | angemeldet; Daten nur über RG-03…RG-12 | – | Shell enthält keine Kursinhalte |
| Kurskatalog-Block | M-01 | nur veröffentlichte Kurse | Entwürfe nie im Katalog |
| Gate-Hinweis | – | – | nennt nur Kurse, die M-01 bestehen (Entwurfsnamen nie) |

### 4.5 Medien, geschützte Downloads, Upload

| Oberfläche | Cap | Scope | Auth/Schutz |
|---|---|---|---|
| Geschützte Dateien `GET /media/{uid}`, `/cc-file/{uid}/` | M-20 | R0-10 §4.3 Schritt 2 (Kursbezug, Drip, Rechtestatus, Prüfungsmedien) | Cookie; Gast → Login-Redirect; Range; `no-store` |
| Mediathek-Abfrage (`query-attachments`, `/wp/v2/media`) | WP `upload_files` | geschützte Anhänge nur, wenn M-03 für einen referenzierenden Kurs (Filter `ajax_query_attachments_args`, `rest_attachment_query`) | URL umgeschrieben, kein Pfad (LB `ProtectedFiles.php:281`, `:331`) |
| Upload im Editor | WP `upload_files` (Rolle `cc_author` hat es) | – | MIME-/Endungsprüfung R0-10, SVG nur bereinigt oder verboten |
| Schützen/Freigeben eines Anhangs | M-03 auf Zielkurs + `edit_post(attachment)` | Verschieben vor Statuswechsel (LB `ProtectedFiles.php:191`) | Audit |
| Import-Medien (Paket) | über M-21 | – | private Ablage, `.htaccess`/außerhalb Webroot (Inventur C §3.5) |
| Fragen-/Lektionsmedien in Ausgabe | M-02 bzw. M-08 | Speicherklasse nach **R10-D4** (keine neue Entscheidung hier) | – |
| Direktzugriff auf Ablageordner | – | – | 403/404; Health-Check „Speicher privat“ (LB `SystemStatus.php:342`) |

### 4.6 Cron und Hintergrundjobs (Akteur `system:cron`)

| Job (aus LB `Plugin::hourly`, Inventur C §4.3) | Erlaubnis | Regeln |
|---|---|---|
| `cc_exam_expire` | Status `running` → `evaluated(expired)` | nur abgelaufene Sitzungen; keine Nutzeraktion; Ergebnis nur für Inhaber sichtbar |
| `cc_grant_expire` | Status `active` → `expired` | rein kosmetisch: Zugriff ist ab `valid_until` schon verweigert (B-6) |
| `cc_group_invites_expire` | Einladungen `expired`, Adresse nach 30 Tagen löschen | LB `GroupService.php:665-684` |
| `cc_import_retention`, `cc_audit_prune`, `cc_rate_limit_cleanup` | löschen nach Retention (Annahme E-44/E-51) | je Lauf begrenzt (LB 5 000/20 000) |
| `cc_expiry_reminder` (Pro, Annahme E-24) | Mail an Grant-Inhaber | nur eigener Grant; Opt-out respektiert |
| `cc_migrate` | Schema | Lock, nicht über HTTP auslösbar außer #23 |
| `cc_privacy_export_generate` | Export für bestätigten Antrag | nur `request_id` aus WP-Kern; Nutzer aus Antrag, nicht aus Argument |
| `cc_outbox_dispatch` (R3/Pro) | Ereignisse senden | Payload minimal (Konzept 18) |

Allgemein: Jobargumente enthalten nur IDs. Der Job lädt den aktuellen Zustand neu und verwirft unpassende Objekte (andere Site, gelöscht, Status geändert) ohne Fehlerkaskade (RBAC-090). Cron ruft keine REST-Route mit Nutzerkontext auf.

### 4.7 WP-CLI `wp course-captain …` (Akteur `system:cli`)

CLI setzt Shell-Zugang voraus und gilt als Systemakteur. Ohne `--user` prüft CC keine Caps. Mit `--user=<id>` prüft CC die Caps dieses Nutzers (für Supportskripte). Schreibende Kommandos verlangen `--yes` oder eine Rückfrage, schreiben Audit mit `actor_type=cli` und respektieren `--url` (Site).

| Kommando (Inventur C §4.4) | Klassifikation | Regel |
|---|---|---|
| `status`, `backup-check`, `migrate` | reuse | lesend bzw. Lock |
| `import <paket>\|resume\|discard`, `rollback <id> --reason` | reuse | Pfad nur unter erlaubten Wurzeln; `--reason` Pflicht |
| `rights release --reason` | abstract | Sammelfreigabe mit Audit |
| `grants <user>` (LB `access`) | reuse | Ausgabe minimal (Kurs, Status, Gültigkeit, Quelle), keine Antworten |
| `export grants\|rules --output` (neutral, ersetzt LB Tenant-Export) | replace | Safe-Export-Writer: nicht im Webroot, `0600`, `fopen 'x'` (LB `Commands.php:964-1009`) |
| `cleanup-sites` | bedingt (Annahme E-07) | nur Multisite |
| `reconcile` | R3 Woo-Adapter | – |

### 4.8 Webhooks, Connectoren und PHP-API

| Einstieg | Auth | Cap/Scope | Regeln |
|---|---|---|---|
| Eingehende Webhooks (R3, RG-19) | HMAC-Signatur, Zeitstempel, Connector-Secret | Connector aktiv; Aktion nur auf Grants mit eigener `source_type` | Dedupe `UNIQUE (connector_id, event_id)`; Replay → 200 ohne Wirkung; Ablehnung ohne Details |
| PHP-Write-API `course_captain_grant_access_v1`, `_revoke_access_v1`, `_enroll_v1` (Konzept 18) | `context.actor` Pflicht: `connector:<id>` (registriert, aktiv) **oder** Nutzer mit `cc_manage_grants` | nur eigene Quelle (`source_type` des Connectors), Annahme E-39 | Aufruf im Kontext eines normalen Nutzers ohne Cap → `forbidden` (RBAC-047) |
| PHP-Read-API `course_captain_get_enrollment_v1(user_id, …)` | `context.actor` | eigener Nutzer oder M-11 bzw. registrierter Connector mit Lese-Scope | keine E-Mail, keine Antworten (Konzept 18 Payload) |
| Filter `course_captain_can_access` | – | **darf nur einschränken** (D07-7) | Erweiterung nur über Grants mit Quelle |
| Ausgehende Webhooks (Pro) | Secret je Ziel | #20 zum Konfigurieren | Payload minimal, Secrets nie in Export/REST/Diagnose (Konzept 43.4) |

### 4.9 Export und Datenschutz

| Funktion | Cap | Scope | Auth/Nonce |
|---|---|---|---|
| WP-Exporter/Eraser „Course Captain“ (LB `PersonalData.php:47`, `:55`) | WP-Kern `export_others_personal_data` / `erase_others_personal_data` | Daten genau eines Antrags; Geheimnisse ausgelassen (Token-Hashes, Idempotenz) | WP-Kern (Antrag + Bestätigung) |
| Self-Service-Antrag (Block im Lernbereich) | M-23 | eigenes Konto, auch bei Lernsperre | Nonce, Bestätigungsseite mit Knopf (LB `AccountDeletion.php:428`) |
| Download Datenschutz-ZIP | WP-Kern (Zufallsname, Ablauf) | Antragsteller | Link aus Mail |
| Kurs-/Paketexport | #7 + M-03 je Kurs | fremd lizenzierte Inhalte nach R10-D5 | Nonce; Download M-22 |
| Bericht-CSV | #12 + M-16/M-11 | Trainer nur B-2/B-3; Spalten minimiert, keine Antworten | Nonce; M-22 |
| Grant-/Regel-Export | #9 | Site | Nonce oder CLI |
| Zertifikat-PDF | M-18 | eigenes oder #17 | Cookie |
| Audit-Export (Pro) | #21 | Redaction | Nonce |

---

## 5. IDOR- und RBAC-Testkatalog

### 5.1 Fixtures (Ergänzung zu R0-05 §2.7)

Vorhanden aus R0-05: Kurse **C-A**, **C-B** (veröffentlicht), **C-C** (Entwurf), **C-D** (archiviert); Nutzer **U-ADM**, **U-EDT**, **U-AUT** (WP-`author`), **U-TUT** (zugewiesen C-A), **U-SM**, **U-L1** (G-1 auf C-A), **U-L2** (G-2), **U-L3** (Teilumfang), **U-L4** (Drip, G-8/G-9), **U-GO** (Besitzer GRP-1), **U-S1…S4** (Plätze), **U-X** (G-7 auf C-B, abgelaufen); Gruppen **GRP-1** (C-A), **GRP-2** (C-B, manuell).

| ID | Neu für R0-07 | Zweck |
|---|---|---|
| RF-AU2 | `cc_fx_author2`, Rolle `cc_author`, `post_author` von C-B | Autor-Scope |
| RF-TRB | `cc_fx_trainer_b`, `cc_trainer`, Staff C-A nur Gruppe GRP-1 | Gruppen-Scope |
| RF-MGR | `cc_fx_manager2`, `cc_course_manager` | Manager-Grenzen |
| RF-SUP | `cc_fx_support`, `cc_support` | Support-Minimierung |
| RF-BLK | `cc_fx_locked`, `subscriber`, aktiver Grant C-A, Lernsperre gesetzt | gesperrter Nutzer (a) |
| RF-NOROLE | `cc_fx_norole`, ohne Rolle auf der Site, früherer Grant C-A | gesperrter Nutzer (b) |
| RF-S-L1 | laufender Prüfungsversuch von U-L1 in C-A; RF-S-L1E (ausgewertet) | Versuchs-IDOR |
| RF-S-TUT | ausgewerteter Versuch von U-TUT als Lernender in C-A | Selbstbewertung |
| RF-CERT1 | Zertifikat U-L1/C-A, `issued`, Verify-Token VT-1 | Zertifikat |
| RF-FILE-A / RF-FILE-C | geschützte PDF an C-A bzw. an Entwurf C-C | Datei |
| RF-IMP1 | Import-Batch von U-ADM, Status `previewed` | Import |
| RF-EXP1 | Bericht-Export von RF-MGR (C-A) | Export |
| RF-Q-SH | Frage in Pools von C-A **und** C-B | geteilte Frage |
| RF-SITE2 | zweite Site (nur Multisite-Lauf, bedingt) | Site-Scope |

### 5.2 Allgemeine Erwartung jedes Negativtests

1. HTTP-Status und Code nach P-08 (`cc_not_found` 404, `cc_forbidden` 403, `cc_content_locked` 403 mit `reason`, `cc_account_locked`, `cc_auth_required` 401).
2. **Keine Seiteneffekte:** DB-Snapshot der betroffenen Tabellen vor/nach identisch (außer Rate-Limit-Zähler, Audit).
3. Antwortkörper bei „fremd“ und „nicht existent“ byte-gleich bis auf Request-ID (RBAC-096).
4. Kein Leck in Headern, Fehlermeldung, `_links`, `_embedded`, Zeitverhalten über ±50 % (Stichprobe).
5. Audit-Eintrag nur bei Staff-Ablehnungen sicherheitsrelevanter Aktionen (gedrosselt).

### 5.3 Testfälle

Spalte „Var.“: **F** fremde ID, **R** Rollenwechsel/-mangel, **G** gesperrter Nutzer, **A** abgelaufener/inaktiver Grant, **C** CSRF/Nonce, **E** Eskalation. Ziel-Task: primär **R1-03** (Policy, REST, Admin) oder **R1-14** (Gate, Medien, Download, Inhaltsausgabe).

#### Kurs

| Test-ID | Var. | Akteur / Vorbedingung | Aktion (Oberfläche) | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-001 | F | U-X (kein Grant C-A) | `GET /courses/C-A`, dann `GET /courses/C-A/next-question` | 200 mit allen Features `false`, keine Inhalte; zweiter Aufruf 403 `cc_content_locked` | R1-03 | ID-01 |
| RBAC-002 | F | U-L1 | `GET /courses/C-C` (Entwurf), `/courses/C-D` (archiviert), `/courses/999999` | dreimal 404, gleicher Körper | R1-03 | R0-05 Kursstatus |
| RBAC-003 | F | RF-AU2 (Autor C-B) | `POST /wp/v2/cc_course/<C-A>`; `post.php?post=<C-A>&action=edit` | 403 `rest_cannot_edit`; `wp_die` 403; Post unverändert | R1-03 | M-03 |
| RBAC-004 | R | RF-AU2 | eigenen Kurs C-B veröffentlichen (`status=publish`) | 403; Status bleibt Entwurf/Revision unverändert | R1-03 | M-04 |
| RBAC-005 | R | U-TUT (Trainer C-A) | C-A bearbeiten (Core-REST, Editor) | 403 | R1-03 | 35.1 |
| RBAC-006 | R | RF-AU2, gültiges REST-Nonce | Rolle → `subscriber`; gleiche Sitzung `POST /wp/v2/cc_course/<C-B>` | 403 sofort, kein Cache-Treffer | R1-03 | P-11 |
| RBAC-007 | R | U-TUT | Staff-Zuweisung C-A entfernen; `GET /reports/courses/C-A` | 403 sofort | R1-03 | B-2 |
| RBAC-008 | G | RF-BLK | `GET /me/courses`, `GET /courses/C-A/next-question` | `cc_account_locked`; Grant-Zeile unverändert | R1-03 | 3.4 |
| RBAC-009 | A | U-X (G-7 abgelaufen, Ablauf-Cron **nicht** gelaufen) | `GET /courses/C-B/next-question` | 403 `cc_content_locked` `reason=expired`; `/me/courses` zeigt `expired` | R1-03 | B-6 |
| RBAC-010 | F | U-ADM auf Site 1 | `GET /courses/<Kurs-ID von RF-SITE2>` | 404 (bedingt, Annahme E-07) | R1-03 | ID-06 |
| RBAC-011 | E | RF-AU2 | Kurs C-A per Core-REST mit `author=<RF-AU2>` übernehmen; im eigenen Kurs C-B `author` auf U-X umsetzen | beide 403 (Autorwechsel braucht `cc_edit_others_courses`); `post_author` unverändert | R1-03 | B-1, P-06 |

#### Lektion

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-012 | F | U-L1 | `GET /courses/C-A/lessons/<L-B-01>` (Lektion aus C-B) | 404 | R1-03 | LB `VideoService.php:273-283` |
| RBAC-013 | A | U-L3 (Teilumfang) | Lektion jenseits der Freigrenze | 403 `reason=partial_access`; kein Fortschritt | R1-14 | E-15 |
| RBAC-014 | A | U-L4 (Drip gesperrt) | `GET` und `PUT …/progress` auf gesperrte Lektion | 403 `reason=scheduled_release`; keine Zeile in `cc_activity_progress` | R1-14 | E-14 |
| RBAC-015 | F | Gast und U-X | `/wp/v2/cc_lesson/<id>?context=view`, Feed, Suche `?s=`, Sitemap, oEmbed | kein `content.rendered`, kein automatischer Auszug | R1-14 | 4.4 |
| RBAC-016 | F | U-X | Permalink einer Lektion mit Zugangsvorgabe (inkl. Page-Builder-Layout) | Gate-Hinweis ohne Inhalt; Builder-Daten verworfen | R1-14 | LB `ContentGate.php:151` |
| RBAC-017 | F | RF-AU2 | Vorschau-Link einer Entwurfslektion von C-A | 403/404; eigene Entwurfslektion C-B 200 | R1-14 | M-06 |
| RBAC-018 | R | RF-AU2 | Rolle entziehen; Vorschau eigener Entwurfslektion C-B | 404 (kein M-03 mehr) | R1-14 | P-11 |
| RBAC-019 | A | U-L2 | Grant G-2 widerrufen; `PUT /courses/C-A/lessons/<L-A-01>/progress` | 403 `reason=revoked`; Fortschritt unverändert | R1-14 | R0-09 |
| RBAC-020 | G | RF-NOROLE | `GET /courses/C-A/lessons/<L-A-01>` | 403 `cc_account_locked` bzw. 401-gleich (kein `read`) | R1-14 | 3.4 (b) |

#### Frage

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-021 | F | U-L1 (kein Grant C-B) | `PUT /courses/C-A/favorites/<Frage nur in C-B>` | 404; keine Zeile in `cc_favorite` | R1-03 | L-W5 |
| RBAC-022 | F | U-L1 | `POST /answers` mit Frage-Token, das für U-X ausgestellt wurde | 403 `cc_invalid_token`; kein `cc_attempt` | R1-03 | LB `QuestionPresenter.php:155-158` |
| RBAC-023 | F | U-L1 | `next-question?module=<M-B1>` bzw. `?collection=<Sammlung von U-X>` auf C-A | 404; R0-05 GM-058 | R1-03 | RG-05 |
| RBAC-024 | F | RF-AU2 | `cc_edit_question` auf Frage nur in C-A; auf RF-Q-SH (C-A und C-B) | beide 403 (geteilte Frage braucht Recht auf alle Kurse) | R1-03 | M-07 |
| RBAC-025 | R | U-TUT | Frage in C-A bearbeiten (Admin-Fragenbank) | 403 | R1-03 | 35.1 |
| RBAC-026 | R | RF-AU2 | Rolle → `subscriber`; eigene Frage (`author_user_id`) speichern | 403 | R1-03 | M-07 |
| RBAC-027 | A | U-X | Token wird vor `valid_until` ausgegeben, Antwort danach gesendet | 403 `reason=expired`; kein Versuch gezählt | R1-03 | LB `LearningService.php:177-188` |
| RBAC-028 | G | RF-BLK | Token vor Sperre ausgegeben, Antwort nach Sperre | `cc_account_locked`; kein `cc_attempt` | R1-03 | 3.4 |

#### Versuch

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-029 | F | U-X | `GET /exam-sessions/<RF-S-L1>` und `/exam-sessions/999999` | beide 404, gleicher Körper | R1-03 | GM-049 |
| RBAC-030 | F | U-X | `PUT …/answers/1`, `PUT …/flag`, `POST …/reveal`, `POST …/submit`, `POST …/abort` auf RF-S-L1 | jeweils 404; Versuch unverändert | R1-03 | GM-151 |
| RBAC-031 | F | U-L1 | `POST /courses/C-A/exam-sessions` mit `variant=<EP-B-1>` | 404; kein Versuch angelegt | R1-03 | LB `ExamService.php:202-210` |
| RBAC-032 | E | U-TUT, U-ADM | eigenen Versuch bewerten (`PUT /exam-sessions/<RF-S-TUT>/grades/1`) | 403 `do_not_allow`, auch für Admin/Super-Admin | R1-03 | M-10 |
| RBAC-033 | F | U-TUT (C-A) | Versuch eines C-B-Lernenden lesen | 403/404; C-A-Versuch in Bewertung lesbar | R1-03 | GM-154 |
| RBAC-034 | F | RF-TRB (C-A, nur GRP-1) | Versuch von U-L1 (C-A, nicht in GRP-1) lesen | 404 | R1-03 | B-3 |
| RBAC-035 | F | RF-SUP | `GET /exam-sessions/<RF-S-L1E>/review` und S-11 | 403; Lernerübersicht ohne Antworten 200 | R1-03 | Konzept 43.3 |
| RBAC-036 | F | U-GO (Besitzer GRP-1) | Versuch/Fortschritt von U-S1 lesen (`/reports/groups/GRP-1`) | 403 | R1-03 | 35.1 Käufer |
| RBAC-037 | A | U-L2 mit laufendem Versuch | G-2 widerrufen bzw. `valid_until` überschreiten; `PUT …/answers/2` | 403 `reason=revoked/expired`; Versuch bleibt `running` bis Frist, Ergebnis später lesbar | R1-03 | LB `ExamService.php:1044-1057` |
| RBAC-038 | G | RF-BLK mit laufendem Versuch | `POST …/submit` | `cc_account_locked`; Cron wertet nach Frist aus | R1-03 | 3.4 |
| RBAC-039 | R | U-TUT, Bewertungsformular offen | Zuweisung entfernen; Bewertung absenden | 403; keine Bewertung gespeichert | R1-03 | P-11 |
| RBAC-040 | C | U-TUT | Bewertung ohne/mit fremdem Nonce | 403 `rest_cookie_invalid_nonce` | R1-03 | P-04 |

#### Grant

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-041 | F | U-L1 | `GET /me/courses?user_id=<U-X>` | Parameter ignoriert, nur eigene Daten | R1-03 | P-09 |
| RBAC-042 | R | U-TUT | admin-post `cc_grant_create` für C-A | 403 | R1-03 | 36 |
| RBAC-043 | C | RF-MGR | `cc_grant_change` mit Nonce von Grant G-1 auf Grant G-10 | 403; G-10 unverändert | R1-03 | P-04 |
| RBAC-044 | C | RF-MGR | `cc_grant_change` per GET bzw. ohne Nonce | 403/405; keine Änderung | R1-03 | P-04 |
| RBAC-045 | F | RF-AU2 | `cc_grant_change` (revoke) mit manipulierter Grant-ID | 403 | R1-03 | M-13 |
| RBAC-046 | A | RF-MGR | G-7 (abgelaufen) „verlängern“ | neue Revision/Ereignis in `cc_grant_event`, kein stilles Wiederaufleben; Zugriff erst danach | R1-03 | Annahme E-29 |
| RBAC-047 | E | U-L1 (Theme-Formular ruft PHP-API) | `course_captain_grant_access_v1()` im Kontext U-L1 ohne Connector | `forbidden`; kein Grant | R1-03 | 4.8 |
| RBAC-048 | E | registrierter, **deaktivierter** Connector | `course_captain_grant_access_v1` | `connector_disabled`; kein Grant | R1-03 | Annahme E-39 |
| RBAC-049 | R | RF-MGR | Manager-Rolle entziehen; offene Seite S-13 „Widerrufen“ absenden | 403 | R1-03 | P-11 |
| RBAC-050 | G | RF-BLK | Sperre setzen, dann aufheben | Grants unverändert; nach Aufhebung sofort Zugriff | R1-03 | D07-2 |

#### Gruppe

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-051 | F | U-X | `PUT /me/groups/<GRP-1>` (Umbenennen), `POST …/token` | 404; Token unverändert | R1-03 | LB `GroupController.php:109-115` |
| RBAC-052 | F | U-GO | `DELETE /me/groups/<GRP-1>/members/<Mitglied aus GRP-2>` | 404; kein Grant widerrufen | R1-03 | LB `GroupService.php:423` |
| RBAC-053 | F | U-S1 (Mitglied GRP-1) | `POST /me/groups/<GRP-1>/invite` | 404 | R1-03 | M-15 |
| RBAC-054 | F | U-X | persönliche Einladung für U-S2 mit `POST /groups/join` einlösen, Option „nur eingeladene Adresse“ an | 403 `cc_invite_other_account`; Platz unverändert | R1-03 | Annahme E-28 |
| RBAC-055 | A | U-S3 | Beitritt zu GRP-1 nach `valid_until` bzw. `suspended` | 409 `cc_group_not_active`; Mitglieder ohne Zugriff | R1-03 | R0-09 |
| RBAC-056 | G | U-GO gesperrt (RF-BLK-Variante) | `GET /me/groups` | `cc_account_locked`; Plätze der Mitglieder weiter aktiv | R1-03 | 3.4 |
| RBAC-057 | R | U-ADM überträgt GRP-1 an U-S1 | U-GO `PUT /me/groups/<GRP-1>` | 404 sofort | R1-03 | P-11 |
| RBAC-058 | F | RF-TRB | `GET /reports/courses/C-A` | nur Lernende aus GRP-1 | R1-03 | B-3 |
| RBAC-059 | F | U-GO | `GET /me/groups/<GRP-1>` | nur Platz-/Einladungsstatus, keine Fortschritte; E-Mail nach D07-4 | R1-03 | 35.1 |

#### Zertifikat

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-060 | F | U-X | `GET /certificates/<uid RF-CERT1>/pdf` | 404 | R1-03 | M-18 |
| RBAC-061 | F | Gast | `GET /certificates/verify/<VT-1>`; numerische ID statt Token | Minimaldaten; numerische ID → 404; Rate-Limit greift | R1-14 | CORE-SPEC-006 |
| RBAC-062 | R | U-TUT | `cc_certificate_revoke` RF-CERT1 | 403 („bewerten, nicht allein widerrufen“) | R1-03 | 36 |
| RBAC-063 | R | RF-MGR | Manager-Rolle entziehen; Korrektur absenden | 403; keine neue Revision | R1-03 | P-11 |
| RBAC-064 | E | U-L1 mit #17 (Testrolle) | eigenes Zertifikat korrigieren | 403 `do_not_allow` | R1-03 | M-19 |
| RBAC-065 | A | U-L1, Grant G-1 widerrufen/abgelaufen | eigenes Zertifikat-PDF | 200 (Historie bleibt, CORE-Defaults) | R1-03 | 35.4 |
| RBAC-066 | G | RF-BLK mit Zertifikat | PDF-Download; öffentliche Verifikation | PDF `cc_account_locked`; Verifikation 200 | R1-14 | 3.4 |

#### Datei

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-067 | F | U-X | `GET /cc-file/<RF-FILE-A>/` | 403 mit Gate-Hinweis (Kurs öffentlich) | R1-14 | LB `ProtectedFiles.php:361` |
| RBAC-068 | F | U-L1 | `GET /cc-file/<RF-FILE-C>/` (Entwurfskurs) und unbekannte UID | beide 404 | R1-14 | R0-10 §4.3 |
| RBAC-069 | F | Gast | direkter Pfad im geschützten Ordner; `?attachment_id=`; `/wp/v2/media/<id>` | 403/404; REST ohne `source_url`/Pfad | R1-14 | LB `ProtectedFiles.php:331` |
| RBAC-070 | F | RF-AU2 | Mediathek `query-attachments` | RF-FILE-A erscheint nicht (oder nur ohne URL/Pfad) | R1-14 | 4.5 |
| RBAC-071 | E | RF-AU2 | Mediathek-Feld eigener Anhang → Kurs C-A | abgelehnt; Feld unverändert | R1-14 | LB T195 `ProtectedFiles.php:158` |
| RBAC-072 | A | U-X | Datei C-B nach Ablauf G-7, auch mit `Range: bytes=0-99` | 403 (nicht 206) | R1-14 | B-6 |
| RBAC-073 | R | RF-AU2 lädt Datei an C-B hoch | Rolle entziehen; Download | 403 (Bypass M-03 entfällt), kein Grant | R1-14 | P-11 |
| RBAC-074 | G | RF-BLK | Download RF-FILE-A | `cc_account_locked` | R1-14 | 3.4 |
| RBAC-075 | F | U-L2 mit laufendem Versuch, U-X | Medium einer Prüfungsfrage aus dem Versuch abrufen | U-L2 200; U-X 404 | R1-14 | R0-10 §4.3 |
| RBAC-076 | E | RF-AU2 | Upload `x.php.png`, SVG mit Skript | abgelehnt (MED-BAD) | R1-14 | GM-128 |

#### Import

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-077 | R | RF-AU2 | `POST /imports/<RF-IMP1>/step`; S-18 | 403 | R1-03 | M-21 |
| RBAC-078 | F | RF-MGR auf Site 1 | Batch-ID von RF-SITE2 | 404 (bedingt) | R1-03 | 3.4 |
| RBAC-079 | C | RF-MGR | `cc_import_action` mit Nonce eines anderen Batches | 403 | R1-03 | P-04 |
| RBAC-080 | E | Nutzer mit #6 ohne #3 | Schritt `activate/publish` | 403; Vorschau bleibt | R1-03 | M-21 |
| RBAC-081 | E | U-ADM | `cc_import_url` auf `http://127.0.0.1/`, `169.254.169.254`, `http://` | abgelehnt (nur https, `wp_safe_remote_get`) | R1-03 | LB `ImportSources.php:276` |
| RBAC-082 | E | U-ADM | Ablageordner-Name `../x.zip`, Symlink | abgelehnt | R1-03 | LB `ImportSources.php:222-224` |
| RBAC-083 | R | RF-MGR startet Import | Rolle entziehen; nächster Schritt (REST) | 403; Batch bleibt fortsetzbar für Berechtigte | R1-03 | P-11 |
| RBAC-084 | G / A | RF-NOROLE (früher Manager); überholter Batch | Schritt auslösen; Freigabe eines überholten Batches | 403; überholter Batch 409 (`supersededBy`) | R1-03 | R0-10 IMP-019 |

#### Export und Datenschutz

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-085 | F | U-X | Download RF-EXP1 per erratener ID/URL | 404 | R1-03 | M-22 |
| RBAC-086 | F | U-L1 | `POST /me/privacy-requests` mit `user_id=<U-X>` bzw. fremder E-Mail | Antrag nur für eigenes Konto | R1-03 | M-23 |
| RBAC-087 | F | U-TUT | Bericht-CSV C-B; C-A | C-B 403; C-A nur zugewiesene Lernende, keine Antworten/E-Mail nach D07-4 | R1-03 | M-16 |
| RBAC-088 | R | RF-AU2 | Kurs-Export C-A; Zuweisung entfernt → Export eigener C-B | C-A 403; nach Rollenentzug auch C-B 403 | R1-03 | #7 |
| RBAC-089 | A / G | RF-MGR, Export > Ablaufzeit; RF-BLK | Download abgelaufen; RF-BLK stellt Datenschutzantrag | 410 `cc_export_expired`; Antrag erlaubt | R1-03 | M-23 |

#### Querschnitt (Cron, CLI, Webhook, Eskalation)

| Test-ID | Var. | Akteur / Vorbedingung | Aktion | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|---|
| RBAC-090 | F | `system:cron` | Job mit Grant-ID einer anderen Site / gelöschtem Grant | No-op mit Log, kein Fehlerabbruch | R1-03 | 4.6 |
| RBAC-091 | E | RF-MGR | Tab „Rollen & Rechte“: sich selbst `cc_manage_settings` geben; Nutzer zu `administrator` machen | 403; `administrator` nicht wählbar | R1-03 | 3.5 |
| RBAC-092 | E | RF-SUP | RF-MGR bzw. U-ADM sperren; sich selbst sperren | 403 (`do_not_allow`) | R1-03 | M-24 |
| RBAC-093 | E | Webhook ohne/mit falscher Signatur; Replay derselben `event_id` | `POST /webhooks/<connector>` | 401 ohne Details, keine Inbox-Zeile; Replay 200 ohne zweiten Grant | R1-03 | 4.8 |
| RBAC-094 | E | Monitor | `GET /monitor?key=<richtig>` | Schlüssel im Query wird ignoriert → nur Gesamtstatus | R1-03 | L-W8 |
| RBAC-095 | E | Deaktivierung/Deinstallation | Plugin deaktivieren, deinstallieren | Fremdrollen unverändert; `cc_*` nur bei Deinstallation entfernt | R1-03 | P-10 |
| RBAC-096 | F | Messreihe über RBAC-002/029/052/060/068 | „fremd“ vs. „nicht existent“ | Körper byte-gleich (bis Request-ID), Status gleich | R1-03 | P-08 |

**Abdeckung je Objekttyp (F/R/G/A):** Kurs 001–003,010 / 004–007,011 / 008 / 009 · Lektion 012,015–017 / 018 / 020 / 013,014,019 · Frage 021–024 / 025,026 / 028 / 027 · Versuch 029–036 / 039 / 038 / 037 · Grant 041,045 / 042,049 / 050 / 046 · Gruppe 051–054,058,059 / 057 / 056 / 055 · Zertifikat 060,061 / 062,063 / 066 / 065 · Datei 067–070,075 / 073 / 074 / 072 · Import 077–079 / 083 / 084 / 084 (Ersatz: überholter Batch, Grants gibt es dort nicht) · Export 085–087 / 088 / 089 / 089 (Ablauf des Exports).

---

## 6. Abgleich mit LearnBoating (`9f37c77`)

### 6.1 LB stärker oder gleichwertig (übernehmen)

| Nr. | Befund | Pfad:Zeile | Konsequenz CC |
|---|---|---|---|
| L-S1 | Fremde Prüfungssitzung → `NotFound` (kein Orakel) | `src/Exams/Services/ExamService.php:1034-1042` | P-08, RBAC-029 |
| L-S2 | Laufender Versuch prüft Grant und Teilumfang bei jeder Aktion neu (Erstattung während der Prüfung) | `src/Exams/Services/ExamService.php:1044-1057` | M-08, RBAC-037 |
| L-S3 | Prüfungsvariante muss zum Kurs gehören | `src/Exams/Services/ExamService.php:202-210` | RG-08, RBAC-031 |
| L-S4 | Frage-Token HMAC mit Nutzerbindung; Grant-Neuprüfung beim Antworten | `src/Learning/Services/QuestionPresenter.php:151`, `:155-158`; `src/Learning/Services/LearningService.php:177-188` | RG-06, RBAC-022/027 |
| L-S5 | Lektion an Kurs, Veröffentlichung und Rechtestatus gebunden | `src/Trainers/VideoService.php:273-283` | RG-10, RBAC-012 |
| L-S6 | Sammlung nur mit Nutzer **und** Kurs | `src/Learning/Services/CollectionService.php:233-235` | RG-07 |
| L-S7 | Gruppen-REST prüft Besitz **vor** der Aktion; Mitglied an Gruppe gebunden | `src/Rest/Controllers/GroupController.php:103`, `:109-115`; `src/Commerce/GroupService.php:420-425` | RG-12, RBAC-051/052 |
| L-S8 | Zugang wird zur Laufzeit gegen `valid_until` geprüft, nicht nur per Cron | `src/Learning/Services/AccessService.php:408-409` | B-6, RBAC-009 |
| L-S9 | `courseByCode` liefert nur veröffentlichte Kurse | `src/Content/Repositories/ContentRepository.php:44` | M-01 |
| L-S10 | Geschützte Datei: 404 ohne Vorgabe, Login-Redirect, 403, Bypass über `edit_post`; REST-Anhang ohne Pfad | `src/Gate/ProtectedFiles.php:344-371`, `:331` | M-20, RBAC-067/069 |
| L-S11 | Gate-Feld nur mit Content-Cap **und** `edit_post` (T195) | `src/Gate/ProtectedFiles.php:158` | 4.3, RBAC-071 |
| L-S12 | admin-post: Cap vor `check_admin_referer` | `src/Admin/ImportPage.php:597-602`; `src/Admin/AccessPages.php:692-695` | 4.3 |
| L-S13 | Import-URL nur über `wp_safe_remote_get`, Ablageordner mit `realpath`/Symlink-Prüfung | `src/Content/Import/ImportSources.php:276`, `:222-224` | RBAC-081/082 |
| L-S14 | Webhook-HMAC mit `hash_equals`; Datenschutzantrag mit Nonce; Bestätigungsknopf statt GET-Löschung | `src/Commerce/Disputes/DisputeWebhooks.php:523`; `src/Privacy/SelfService.php:123-129`; `src/Privacy/AccountDeletion.php:428` | 4.8, 4.9 |

### 6.2 LB schwächer (nicht übernehmen, CC-Delta)

| Nr. | Befund | Pfad:Zeile | Risiko | CC-Regel / Test |
|---|---|---|---|---|
| L-W1 | Default `default_access = logged_in`: jedes angemeldete Konto hat Vollzugang zu allen Kursen und Features | `src/Settings/Settings.php:21`; `src/Learning/Services/AccessService.php:77-78`, `:115-117` | fail-open nach Installation, Widerspruch zu Konzept 36 | P-01; Modus entfällt; RBAC-001 |
| L-W2 | Filter `bootspruefung_can_access` kann Zugang beliebig **erweitern** | `src/Learning/Services/AccessService.php:80`, `:119` | Drittplugin gewährt Zugriff ohne Grant/Audit | D07-7 (nur einschränken) |
| L-W3 | Nur drei grobe Caps, nur Administrator erhält sie; keine Autor-/Trainer-/Supportrollen, keine Objektrechte | `src/Security/Capabilities.php:12-29` | kein Least Privilege; Redaktion braucht Volladmin | Abschnitte 2/3 |
| L-W4 | `manage_options` für Protokoll und Einstellungen | `src/Admin/AuditPage.php:22`; `src/Admin/Menu.php:72`, `:252` | Konzept 36 verbietet es | P-05, #19/#21 |
| L-W5 | Favorit setzen prüft nur „auslieferbar“, nicht den Kurszugang; Route ohne Kurs | `src/Rest/Controllers/LearnerController.php:86-88`, `:279-283`; `src/Learning/Services/LearningService.php:460-466` | Existenz-Orakel über Frage-IDs, Favoriten ohne Grant | RG-07, RBAC-021 |
| L-W6 | CPT `bp_tip`/`bp_faq`/`bp_customer_faq` mit `map_meta_cap => false`, alle Caps = `manage_bp_content` | `src/Tips/Tips.php:48-51`; `src/Faq/Faq.php:82`; `src/Faq/CustomerFaq.php:106` | keine Objektrechte im Editor | CPT-Mapping 3.2 |
| L-W7 | Paketmedien öffentlich unter vorhersagbarem Paketpfad; `.htaccess` sperrt nur Skripte | `src/Content/Media/MediaStorage.php:62`, `:138-140`, `:166` | Fragenbilder gesperrter/Entwurfs-Kurse ohne Grant abrufbar | R10-D4 (R0-10), RBAC-068 |
| L-W8 | Monitor-Schlüssel auch als `?key=`; ohne Konstante offen | `src/Infrastructure/Monitor.php:43-47`, `:55-59` | Schlüssel in Logs | RG-20, RBAC-094 |
| L-W9 | Keine Konto-/Lernsperre; kein Cache-Reset bei Rollenwechsel im Web (nur Mobile `profile_update`) | `grep` ohne Treffer für Sperre; `src/Mobile/MobileApi.php:64` | „gesperrter Nutzer“ (ID-01) nicht abbildbar | 3.4, 3.5, D07-2 |
| L-W10 | Gruppenbesitzer erhält E-Mail-Adressen auch aktiver Mitglieder | `src/Rest/Controllers/GroupController.php:135` | Datenminimierung (35.1) | D07-4, RBAC-059 |
| L-W11 | Geschützte Datei: 403-Hinweis nennt Kurs und Feature, 404/403 unterscheidet „geschützt“ | `src/Gate/ProtectedFiles.php:352-365` | geringes Orakel | Hinweis nur bei M-01-sichtbaren Kursen; RBAC-068 |

**Neutral (nur festgehalten):** Gruppen-Verwaltung über Service erlaubt zusätzlich `manage_bp_entitlements` (`GroupService.php:756-764`), die REST-Route nur dem Besitzer (`GroupController.php:109-115`). CC trennt das sauber über M-15 (Besitzer im Frontend, #11 im Admin). Schutzliste für automatische Kontolöschung arbeitet mit Rollennamen und Caps (`AccountDeletion.php:76-83`) → in CC um `cc_*` ergänzt (3.5 Nr. 6).

---

## 7. Entscheidungsbedarf (neu, nicht in `ENTSCHEIDUNGEN.md`)

| Nr. | Frage | Optionen | Empfehlung | Wer | Blockiert |
|---|---|---|---|---|---|
| **D07-1** | **Staff-Zuweisung als eigene Tabelle** (`cc_course_staff`), die R0-06 nicht enthält? Ohne sie gibt es keinen „zugewiesenen Kurs“ für Trainer/Autoren (Konzept 17, 36). | a) Tabelle `cc_course_staff (course_id, user_id, staff_role, group_id NULL)`; b) Post-Meta am Kurs (keine Abfrage „Kurse eines Trainers“ ohne Meta-Scan); c) nur `post_author` (kein Trainer-Scope) | **a)**; delegierte Gruppenverwalter (Pro) später als `staff_role = group_manager` mit `group_id`. Delta an R0-06 (Tabelle Nr. 40+1) | Architektur | R0-06, R1-02, R1-03 |
| **D07-2** | **Lernsperre:** eigenes Sperrkennzeichen oder nur WP-Mittel (Rolle entziehen)? | a) `cc_learner_lock` (Grund, Akteur, Zeitpunkt; Audit) zusätzlich zu Rollenentzug; optional Sitzungen beenden; b) nur Rollenentzug; c) Sperre über Grant-Suspendierung aller Grants | **a)** ohne automatische Sitzungsbeendigung (Option). c) verfälscht Grant-Historie | Auftraggeber | R0-09, R1-03, R1-34 |
| **D07-3** | **Kursmanager-Scope:** site-weit oder nur zugewiesene Kurse? | a) site-weit (Konzept 17); b) nur Kurse mit `staff_role = manager`; c) beides, Umschalter | **a) in R1**, b) als Pro-/R2-Option über D07-1 | Auftraggeber | R1-03, R0-29 |
| **D07-4** | **E-Mail-Sichtbarkeit** für Gruppenbesitzer und Trainer (LB zeigt Besitzern alle Mitglieder-Adressen) | a) Besitzer sieht Adresse nur bei offenen Einladungen, nach Claim Name + maskierte Adresse; Trainer Name ohne Adresse; b) wie LB; c) nur Name | **a)** | Auftraggeber (Datenschutz) | R1-13, R1-35, R1-10 |
| **D07-5** | **Pflichtbegründung beim Lesen fremder Versuchsdaten** (Admin/Manager über #14) | a) Begründungsfeld + Audit; b) nur Audit; c) Zeitlich begrenzte Freigabe („Break-glass“) | **a)** für #14; zugewiesene Trainer bei Bewertung ohne Begründung (nur Audit) | Auftraggeber | R1-03, R1-09 |
| **D07-6** | **Default-Rollen bei Aktivierung:** Welche Rollen erhalten `cc_*`-Caps automatisch? (Konzept 43.5 „Standardrollen“) | a) nur `administrator` + vier neue `cc_`-Rollen; b) zusätzlich `editor` → Autor-Caps; c) zusätzlich `shop_manager` → Grants | **a)**; Zuweisung weiterer Rollen bewusst im Tab „Rollen & Rechte“ | Auftraggeber | R1-01, R1-03, R1-34 |
| **D07-7** | **Erweiterungsfilter für Zugriffsentscheidungen:** darf Fremdcode Zugang gewähren? | a) Filter `course_captain_can_access` nur einschränkend; Gewähren nur über Grants mit Quelle; b) wie LB frei erweiterbar; c) kein Filter | **a)** (Audit- und Grant-Vertrag bleiben vollständig) | Architektur | R0-19, R1-03, R1-28 |

**Keine neue Entscheidung, nur Abhängigkeit:** Speicherklasse der Fragen-/Lektionsmedien → **R10-D4** (R0-10); Support ohne Antwortzugriff ist Konzept 43.3; Gastkauf-Claim → Annahme E-30; Einladungsbindung → Annahme E-28; Workflow-Schreibaktionen → Annahme E-39; Retention → Annahme E-44/E-51.

---

## 8. Deltas zu anderen R0-Dokumenten

| Dokument | Delta |
|---|---|
| R0-06 | Tabelle `cc_course_staff` (D07-1); Kennzeichen `cc_learner_lock` als User-Meta oder Tabelle (D07-2); `cc_import_batch.created_by` und Export-Datensatz mit Ersteller und Ablauf (B-9) bestätigen |
| R0-05 | Fixtures RF-* (5.1) ergänzen. Platzhalter `cc_manage_content` (R0-05 U-EDT, R0-10 §4.3) = hier `cc_edit_others_courses` (site-weit) bzw. Meta-Cap `cc_edit_course` (objektbezogen); `cc_edit_course` ist als Meta-Cap M-03 bestätigt |
| R0-10 | Rechteprüfung §4.3 Schritt 2 = M-20; „Wer `cc_manage_content` für das Objekt hat“ → M-03 auf einen referenzierenden Kurs |
| R0-19 | Fehlercodes `cc_account_locked`, `cc_invite_other_account`, `cc_group_not_active`, `cc_export_expired`, `cc_invalid_token` in den Katalog (Annahme E-06) |
| R0-09 | Lernsperre ändert keine Grants; Grant-Verlängerung nach Ablauf als neue Revision (RBAC-046) |
| R0-08 | Bewertungsrecht M-10 inkl. Verbot der Selbstbewertung |

---

## 9. Nachweis

- Capabilities LB: `src/Security/Capabilities.php:12-29` (drei Caps, nur `administrator`); `grep -rn "map_meta_cap\|add_role"` → nur `map_meta_cap => false` in `Tips.php:48`, `Faq.php:82`, `CustomerFaq.php:106`, kein `add_role`.
- Zugriff LB: `AccessService.php:74-83`, `:110-121`, `:408-409`; `Settings.php:21`, `:103`.
- Eigentumsprüfungen LB: `ExamService.php:1034-1057`, `:202-210`; `CollectionService.php:233-235`; `GroupController.php:30-41`, `:101-115`, `:135`; `GroupService.php:420-434`, `:756-764`; `VideoService.php:173-180`, `:236-240`, `:273-283`; `LearningService.php:177-191`, `:460-479`; `QuestionPresenter.php:151-158`.
- Medien/Dateien LB: `ProtectedFiles.php:40`, `:158`, `:281`, `:331`, `:344-371`; `ContentGate.php:104-121`; `MediaStorage.php:62`, `:129-140`, `:164-190`.
- Sonstiges LB: `Monitor.php:30-59`; `NonceRefresh.php:24-25`; `DisputeWebhooks.php:207-208`, `:523`; `SelfService.php:123-129`; `ImportPage.php:597-602`; `AccessPages.php:692-695`; `ImportSources.php:222-224`, `:276`; `AuditPage.php:22`; `Menu.php:72`, `:252`; `AccountDeletion.php:76-83`; Sperre/Rollenwechsel: `grep -rn "set_user_role\|remove_user_role\|ajax_query_attachments\|spam"` ohne Treffer in `src/` (außer `MobileApi.php:64` `profile_update`).
- Routenbasis: Inventur D §4.1 (W1–W52) und Querverweise (`/faqs`, `/customer-faqs`, `/tips`, `/webhooks/*`, `/monitor`).
- Keine Codeänderung an LearnBoating, keine Implementierung, kein Commit.
