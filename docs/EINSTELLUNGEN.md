# Einstellungen – Handbuch für Betreiber

<!-- Automatisch erzeugt aus dem Einstellungsregister (src/Settings/SettingsSchema.php, src/Admin/SettingsFields.php, src/Settings/EffectiveSettings.php). Nicht von Hand ändern: `php8.5 tools/docs/settings-doc.php --write`. Der Unit-Test SettingsDocTest prüft, dass diese Datei aktuell ist. -->

Dieses Handbuch listet jede Einstellung von Course Captain mit Standardwert, erlaubten Werten, Recht, Datenklasse und Exportverhalten. Bezeichnungen und Hilfetexte stehen in der Quellsprache der Oberfläche (Englisch); in der Oberfläche erscheinen sie übersetzt.

Registerversion (`settings_version`): 1.

## Legende

- **Schlüssel**: `<tab>.<feld>`; so heißt die Einstellung in WP-CLI, im Export und im Änderungsprotokoll.
- **Recht**: `settings` = Recht „Einstellungen verwalten“ (`ccap_manage_settings`); `admin` = zusätzlich Administrator (`manage_options`).
- **Datenklasse**: `public`/`internal` erscheinen im Protokoll mit Vorher/Nachher; `personal` nur als „geändert“ und im Export nur mit Opt-in; `secret` nie lesbar.
- **Export**: `ja` = in der Einstellungsdatei; `nein` = nie exportiert oder importiert (umgebungsgebunden).
- **Übersteuerbar**: Ebenen, die abweichen dürfen (Kurs, Aktivität, Prüfung, Gruppe, Medium); „sperrbar“ = eine Sperre für alle Kurse ist möglich (Einstellungen → Sperren und Kurs-Übersteuerungen).

## Inhalt

- [General (`general`)](#tab-general)
- [Course display (`display`)](#tab-display)
- [Learning (`learning`)](#tab-learning)
- [Exams (`exams`)](#tab-exams)
- [Enrollment (`enrollment`)](#tab-enrollment)
- [Roles & permissions (`roles`)](#tab-roles)
- [Certificates (`certificates`)](#tab-certificates)
- [Emails (`emails`)](#tab-emails)
- [Privacy & security (`privacy`)](#tab-privacy)
- [Website hardening (`hardening`)](#tab-hardening)
- [Advanced (`advanced`)](#tab-advanced)
- [Sperren und Übersteuerungen](#sperren-und-übersteuerungen)
- [WP-CLI](#wp-cli)
- [Export und Import](#export-und-import)

<a id="tab-general"></a>
## General (`general`)

> Addresses, pages and basic values for the learning area.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `general.learning_area_slug` | Address of the learning area | slug | `"lernen"` | settings | internal | ja | – |
| `general.course_slug` | Address of single courses | slug | `"kurs"` | settings | internal | ja | – |
| `general.catalog_page_id` | Course catalogue page | page_id | `0` | settings | internal | ja | – |
| `general.learner_home_page_id` | Start page for learners | page_id | `0` | settings | internal | ja | – |
| `general.learning_area_title` | Title of the learning area | string (max. 60) | `""` | settings | internal | ja | – |
| `general.use_profile_locale` | Language for learners | bool, null | `null` | settings | internal | ja | – |
| `general.catalog_group_language_versions` | Group language versions in the catalogue | bool | `false` | settings | internal | ja | – |
| `general.catalog_indexing` | Search engines | enum: `public_catalog`, `noindex` | `"public_catalog"` | settings | internal | ja | course (sperrbar) |
| `general.delete_data_on_uninstall` | Remove all data when the plugin is deleted | bool | `false` | admin | internal | nein | – |

**Hilfetexte**

- `general.learning_area_slug` – Learners find their courses, exams and certificates here. Old addresses keep redirecting permanently. Allowed: lower-case letters, digits, hyphens.
- `general.course_slug` – Stands in front of the name of every course.
- `general.catalog_page_id` – Page with the “Course catalogue” block. Course Captain never overwrites its content.
- `general.learner_home_page_id` – Learners land here via “My learning”. If the page disappears later, Course Captain uses the learning area and reports it in the system status.
- `general.learning_area_title` – Leave empty to use the website title.
- `general.use_profile_locale` – Automatic: learners see the learning area in their profile language as soon as more than one language is installed.
- `general.catalog_group_language_versions` – Shows a course and its translations as one card with a language choice.
- `general.catalog_indexing` – Public catalogue and course pages may appear in search engines. Learning area, lessons, certificates and invitations never appear.
- `general.delete_data_on_uninstall` – Deactivating never deletes anything. Importing settings never takes over this setting. To switch it on, type the name of this website below.

<a id="tab-display"></a>
## Course display (`display`)

> How courses appear in the theme. Course Captain uses the colours and fonts of the theme as long as you change nothing.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `display.container_width` | Width of the learning area | enum: `theme`, `wide`, `full` | `"theme"` | settings | internal | ja | course (sperrbar) |
| `display.breadcrumbs` | Show breadcrumb navigation | bool | `true` | settings | internal | ja | course (sperrbar) |
| `display.catalog_sort` | Order in the catalogue | enum: `manual`, `title`, `newest` | `"manual"` | settings | internal | ja | – |
| `display.card_elements` | Content of course cards | set: `cover`, `summary`, `lesson_count`, `progress` | `["cover","summary","lesson_count","progress"]` | settings | internal | ja | – |
| `display.require_captions` | Require captions for videos | bool | `false` | settings | internal | ja | – |
| `display.require_transcripts` | Require transcripts for videos | bool | `false` | settings | internal | ja | – |
| `display.accessibility_page_id` | Accessibility statement | page_id | `0` | settings | internal | ja | – |
| `display.logo_id` | Logo (media ID) | int 0–… | `0` | settings | internal | nein | – |
| `display.accent_color` | Accent colour | color | `""` | settings | internal | ja | – |
| `display.button_labels` | button_labels | object {enroll, complete, start_exam, submit_exam} | `{"enroll":"","complete":"","start_exam":"","submit_exam":""}` | settings | internal | ja | – |

**Hilfetexte**

- `display.container_width` – Theme uses the content width of your theme; the other options widen only the learning area.
- `display.breadcrumbs` – Shows the path course › module › lesson above the content.
- `display.catalog_sort` – With equal position, courses are sorted by title.
- `display.card_elements` – Without a selection, the card shows only the title.
- `display.require_captions` – Courses with videos without captions can then not be published. Missing alternative texts always block.
- `display.require_transcripts` – The course check always reports videos without a transcript. With this box ticked, such courses can no longer be published. Enter the transcript as text or link in the video lesson.
- `display.accessibility_page_id` – Page with your accessibility statement. The learning area links to it at the bottom of every page.
- `display.logo_id` – ID of an image in the media library, shown at the top of the learning area with the website name as alternative text; 0 = no logo. Not taken over by a settings import.
- `display.accent_color` – For buttons in the learning area, written as #rrggbb. Buttons get white text, so the colour needs a contrast of at least 4.5:1 to white; lighter colours are rejected. Leave empty for the neutral default.
- `display.button_labels` – `enroll`: Button “Enroll for free” – Own label in all languages; leave empty for the translated default text. `complete`: Button “Mark as completed” – Own label in all languages; leave empty for the translated default text. `start_exam`: Button “Start exam” – Own label in all languages; leave empty for the translated default text. `submit_exam`: Button “Submit exam” – Own label in all languages; leave empty for the translated default text.

<a id="tab-learning"></a>
## Learning (`learning`)

> Defaults for practice mode and course completion. Single courses can deviate.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `learning.course_completion_default` | A course counts as completed when … | enum: `required_modules`, `required_modules_and_exam` | `"required_modules"` | settings | internal | ja | course (sperrbar) |
| `learning.mastery_algorithm` | Repetition strategy | enum: `smarttrainer.v1` | `"smarttrainer.v1"` | settings | internal | ja | course (sperrbar) |
| `learning.favorites_enabled` | Allow favourites | bool | `true` | settings | internal | ja | course (sperrbar) |
| `learning.mistake_training_enabled` | Offer mistake training | bool | `true` | settings | internal | ja | course (sperrbar) |
| `learning.assignment_file_types` | Allowed file types for submissions | set: `pdf`, `docx`, `odt`, `txt`, `jpg`, `png` | `["pdf","docx","odt","txt","jpg","png"]` | settings | internal | ja | – |
| `learning.assignment_max_file_mb` | Maximum size per file | int 1–100 | `20` | settings | internal | ja | – |
| `learning.assignment_max_files` | Maximum number of files per submission | int 1–20 | `5` | settings | internal | ja | – |
| `learning.feedback` | Feedback while learning | object {visibility, show_correct_answers} | `{"visibility":"always","show_correct_answers":true}` | settings | internal | ja | course, activity (sperrbar) |

**Hilfetexte**

- `learning.course_completion_default` – Takes effect with the next publication of a course.
- `learning.mastery_algorithm` – Decides which questions come back while practising. A change only affects new answers.
- `learning.favorites_enabled` – Favourites are private; trainers and reports never see them.
- `learning.mistake_training_enabled` – Learners can practise the questions they answered wrongly.
- `learning.assignment_file_types` – Single assignments may only allow fewer types. Without a selection, learners cannot upload files, only text.
- `learning.assignment_max_file_mb` – Single assignments may only set a smaller limit. The upload limit of the server can be lower.
- `learning.assignment_max_files` – Single assignments may only set a smaller number.
- `learning.feedback` – `visibility`: Feedback while practising – Shows right or wrong and the correct answer. `show_correct_answers`: Show the correct answer – Only effective when feedback is shown.

<a id="tab-exams"></a>
## Exams (`exams`)

> These values are defaults for exam rules published afterwards. Published rules and running attempts do not change.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `exams.max_attempts` | Attempts per exam | int 1–100, null | `null` | settings | internal | ja | exam_rule (sperrbar) |
| `exams.cooldown_seconds` | Waiting time between attempts | int 0–2592000 | `0` | settings | internal | ja | exam_rule (sperrbar) |
| `exams.count_cancelled` | Cancelled attempts count | bool | `true` | settings | internal | ja | exam_rule (sperrbar) |
| `exams.time_limit_minutes` | Time limit (suggestion) | int 1–1440, null | `null` | settings | internal | ja | exam_rule (sperrbar) |
| `exams.timer_mode` | Timer mode | enum: `strict`, `practice` | `"strict"` | settings | internal | ja | exam_rule (sperrbar) |
| `exams.late_tolerance_seconds` | Tolerance for connection loss | int 0–600 | `120` | settings | internal | ja | exam_rule (sperrbar) |
| `exams.passing_ratio_proposal` | Passing threshold (suggestion) | object {num, den} | `{"num":7,"den":10}` | settings | internal | ja | – |
| `exams.shuffle_questions` | Shuffle questions (pool exams) | enum: `none`, `within_section`, `all` | `"within_section"` | settings | internal | ja | exam_rule |
| `exams.shuffle_answers` | Shuffle answers | bool | `true` | settings | internal | ja | exam_rule (sperrbar) |
| `exams.feedback` | Exam feedback | object {visibility, show_score, show_correct_answers, show_correct_answers_when} | `{"visibility":"after_submit","show_score":true,"show_correct_answers":true,"show_correct_answers_when":"no_attempts_left_or_passed"}` | settings | internal | ja | exam_rule (sperrbar) |
| `exams.auto_submit_inactive_days` | Submit automatically when inactive | int 1–365, null | `null` | settings | internal | ja | exam_rule (sperrbar) |

**Hilfetexte**

- `exams.max_attempts` – Counted per learner and exam; frozen when an attempt starts.
- `exams.cooldown_seconds` – Interrupted attempts can be continued at any time.
- `exams.count_cancelled` – Only effective with an attempt limit. Attempts voided by support or ended by a course withdrawal never count.
- `exams.time_limit_minutes` – The server time is decisive.
- `exams.timer_mode` – Practice: learners may hide and extend the timer; the result does not count as evidence.
- `exams.late_tolerance_seconds` – Answers arriving up to this time after the end are still counted.
- `exams.passing_ratio_proposal` – Suggestion for new exams. There is no official default; every exam stores its own threshold.
- `exams.shuffle_questions` – Fixed question sheets always keep their order.
- `exams.shuffle_answers` – Answers marked as fixed (for example “All of the above”) keep their place.
- `exams.feedback` – `visibility`: Result feedback – Whether learners see a result after submitting. `show_score`: Show points – Only effective when result feedback is shown. `show_correct_answers`: Show correct answers – Only effective when result feedback is shown. `show_correct_answers_when`: When correct answers appear – After every submission learners always see which questions were right or wrong. Showing solutions directly makes question pools exploitable across repeated attempts.
- `exams.auto_submit_inactive_days` – Only for attempts without time limit. Learners are warned beforehand.

<a id="tab-enrollment"></a>
## Enrollment (`enrollment`)

> How learners get into a course. Access always comes from an enrollment; guests see at most the free part of a course.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `enrollment.self_enrollment` | Enrollment | bool | `false` | settings | internal | ja | course (sperrbar) |
| `enrollment.default_access_days` | Duration of a new access | int 1–3650, null | `null` | settings | internal | ja | course (sperrbar) |
| `enrollment.groups_enabled` | Use groups with seats | bool | `false` | settings | internal | ja | course (sperrbar) |
| `enrollment.invite_valid_days` | Validity of invitations | int 1–365 | `30` | settings | internal | ja | group |
| `enrollment.claim_policy` | Who may accept an invitation? | enum: `transferable`, `invited_address_only` | `"transferable"` | settings | internal | ja | group (sperrbar) |
| `enrollment.pending_grant_timeout_hours` | Waiting enrollments count as failed after | int 1–168 | `24` | settings | internal | ja | – |
| `enrollment.source_disabled_policy` | When an access source disappears | enum: `keep`, `keep_and_flag` | `"keep"` | settings | internal | ja | – |

**Hilfetexte**

- `enrollment.self_enrollment` – Applies to all courses without an own setting. Selling via a shop is set up later under Integrations.
- `enrollment.default_access_days` – Applies to new enrollments. Existing access stays unchanged. Duration of the access, not of the data.
- `enrollment.groups_enabled` – For companies, clubs or classes. Switching off only prevents new groups; existing groups, seats and access stay.
- `enrollment.invite_valid_days` – Applies to new invitations; single groups can be shorter.
- `enrollment.claim_policy` – An email address does not prove who someone is.
- `enrollment.pending_grant_timeout_hours` – Applies to enrollments from external sources that were not confirmed in time.
- `enrollment.source_disabled_policy` – Access is never withdrawn automatically.

<a id="tab-roles"></a>
## Roles & permissions (`roles`)

> Which role may do what in Course Captain. Learners need no permissions: course access only comes from enrollments.

Eigener Speicher (WordPress-Rollen), nicht Teil des Registers. Export nur mit eigenem Opt-in (Abschnitt `roles` der Einstellungsdatei), Import nur durch Administratoren; Hochrisiko-Rechte brauchen eine Bestätigung. WP-CLI: `wp course-captain settings reset roles --yes`.

<a id="tab-certificates"></a>
## Certificates (`certificates`)

> Defaults for certificates and the public verification page.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `certificates.auto_issue` | Issue certificates automatically | bool | `false` | settings | internal | ja | course (sperrbar) |
| `certificates.holder_name_policy` | Name on the public verification page | enum: `never`, `learner_opt_in` | `"learner_opt_in"` | settings | internal | ja | – |
| `certificates.base_template` | Certificate template | object {issuer_name, footer_text, logo_id, signature_label} | `{"issuer_name":"","footer_text":"","logo_id":0,"signature_label":""}` | settings | internal | ja | course (sperrbar) |

**Hilfetexte**

- `certificates.auto_issue` – Applies to courses without an own setting that have a completion rule. Issued certificates do not change.
- `certificates.holder_name_policy` – Email addresses never appear there.
- `certificates.base_template` – `issuer_name`: Issuer – Leave empty to use the website title. Changes create a new template version; issued certificates keep theirs. `footer_text`: Footer – Text at the bottom of every certificate. `logo_id`: Logo (media ID) – ID of an image in the media library; 0 = no logo. `signature_label`: Signature line – Text below the signature area, for example a role.

<a id="tab-emails"></a>
## Emails (`emails`)

> Sender, format and which messages Course Captain sends via WordPress.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `emails.from_name` | Sender name (optional) | string (max. 100) | `""` | settings | personal | ja | – |
| `emails.from_address` | Sender address (optional) | email | `""` | settings | personal | ja | – |
| `emails.footer_text` | Footer of all Course Captain mails | text (max. 1000) | `""` | settings | personal | ja | – |
| `emails.send_format` | Format | enum: `multipart`, `text_only` | `"multipart"` | settings | internal | ja | – |
| `emails.types` | Which messages are sent | object {access_granted, seat_claimed, course_completed, certificate_issued, access_transferred, grading_completed, access_expired, assignment_submitted, assignment_returned, assignment_graded} | `{"access_granted":true,"seat_claimed":true,"course_completed":true,"certificate_issued":true,"access_transferred":true,"grading_completed":true,"access_expired":false,"assignment_submitted":true,"assignment_returned":true,"assignment_graded":true}` | settings | internal | ja | – |

**Hilfetexte**

- `emails.from_name` – Leave empty to use the WordPress default.
- `emails.from_address` – Leave empty to use the WordPress default. An address of your own domain lands in spam less often.
- `emails.footer_text` – Allowed: simple formatting and links.
- `emails.send_format` – Text only is read best by some mail programs and screen readers.
- `emails.types` – Learners can unsubscribe from informational messages themselves. Required messages (important for access or rights) cannot be switched off and are not listed.

<a id="tab-privacy"></a>
## Privacy & security (`privacy`)

> Data requests, retention, external media and network settings.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `privacy.auto_export_on_confirm` | Provide data export automatically after a confirmed request | bool | `true` | settings | internal | ja | – |
| `privacy.auto_erase_on_confirm` | Delete the account automatically after a confirmed erasure request | bool | `false` | settings | internal | ja | – |
| `privacy.backup_max_age_days` | Maximum age of your backups | int 1–3650 | `90` | settings | internal | ja | – |
| `privacy.external_media_mode` | External videos (for example YouTube, Vimeo) | enum: `always_load`, `click_to_load`, `link_only` | `"always_load"` | settings | internal | ja | media |
| `privacy.consent_category` | Category in the consent tool | enum: `marketing`, `statistics`, `preferences`, `functional` | `"marketing"` | settings | internal | ja | – |
| `privacy.external_media_remember` | Learners may choose “Always load” | bool | `true` | settings | internal | ja | – |
| `privacy.trusted_proxies` | Trusted proxies | ip_list (max. 50) | `[]` | admin | internal | nein | – |
| `privacy.show_avatars` | Show profile pictures (Gravatar) | bool | `false` | settings | internal | ja | – |
| `privacy.csp_mode` | Content Security Policy for Course Captain pages | enum: `enforce`, `report_only` | `"enforce"` | settings | internal | ja | – |

**Hilfetexte**

- `privacy.auto_export_on_confirm` – The person receives the download link at their confirmed address.
- `privacy.auto_erase_on_confirm` – Accounts with administrative permissions are excluded.
- `privacy.backup_max_age_days` – So that deleted people do not reappear after a restore, Course Captain remembers deletions this long plus 30 days.
- `privacy.external_media_mode` – By default the learning area loads external videos immediately. The browser of your learners then transfers at least the IP address to the video service without prior consent. Check with your privacy advisor whether you want this. With “Load after click” the connection is only made after a click or a consent in your consent tool.
- `privacy.consent_category` – Only effective with “Load after click”: Course Captain asks for consent in this category via the WP Consent API.
- `privacy.external_media_remember` – Only effective with “Load after click”. The box is never pre-ticked for learners.
- `privacy.trusted_proxies` – One address or range per line. Only enter addresses if your website runs behind your own proxy or load balancer.
- `privacy.show_avatars` – Hashed email addresses are transferred to Gravatar.
- `privacy.csp_mode` – Applies to the learning area including lessons and the exam room. Only scripts that WordPress loads in the regular way may run; frames only from this website and the known video services. Report only should be used only briefly, to find conflicts with the theme or other plugins in the browser console.

<a id="tab-hardening"></a>
## Website hardening (`hardening`)

> Protection of the whole website, not only the learning area. Functions a security plugin already handles pause automatically.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `hardening.login_limit` | Login limit | enum: `auto`, `force`, `off` | `"off"` | admin | internal | ja | – |
| `hardening.reset_limit` | Limit for “Lost password” | enum: `auto`, `force`, `off` | `"off"` | admin | internal | ja | – |
| `hardening.registration_limit` | Limit for registrations | enum: `auto`, `force`, `off` | `"off"` | admin | internal | ja | – |
| `hardening.known_device` | Exempt known devices from address lockouts | bool | `true` | admin | internal | ja | – |
| `hardening.xmlrpc` | XML-RPC | enum: `auto`, `force`, `off` | `"off"` | admin | internal | ja | – |
| `hardening.site_headers` | Security headers | enum: `auto`, `force`, `off` | `"off"` | admin | internal | ja | – |

**Hilfetexte**

- `hardening.login_limit` – Blocks further login attempts after too many failures. Pauses automatically while a security plugin has its own login lockout.
- `hardening.reset_limit` – Limits requests per address and account. The answer is the same for known and unknown accounts.
- `hardening.registration_limit` – Limits new accounts per address and hour. Only effective while anyone can register.
- `hardening.known_device` – A signed, technically necessary cookie recognises devices on which the person already logged in successfully.
- `hardening.xmlrpc` – Old interface for external editors and pingbacks; often used for attacks.
- `hardening.site_headers` – nosniff, Referrer-Policy, embedding only on this website and a Permissions-Policy for Course Captain responses. Headers the server already sends are not sent twice.

<a id="tab-advanced"></a>
## Advanced (`advanced`)

> Technical settings for imports, file delivery and background jobs.

| Schlüssel | Bezeichnung | Typ / erlaubte Werte | Standard | Recht | Datenklasse | Export | Übersteuerbar |
|---|---|---|---|---|---|---|---|
| `advanced.import_execution` | Run imports | enum: `background`, `browser` | `"background"` | settings | internal | ja | – |
| `advanced.file_delivery` | Delivery of protected files | enum: `php`, `x_sendfile`, `x_accel_redirect` | `"php"` | settings | internal | nein | – |
| `advanced.media_delete_grace_days` | Remove deleted media permanently after | int 1–90 | `7` | settings | internal | ja | – |
| `advanced.cron_stale_warning_minutes` | Warn when background jobs did not run for longer than | int 60–1440 | `180` | settings | internal | ja | – |
| `advanced.log_level` | Log level | enum: `error`, `warning`, `info`, `debug` | `"warning"` | settings | internal | nein | – |
| `advanced.error_log_days` | Keep error logs for | int 1–365 | `30` | settings | internal | ja | – |
| `advanced.debug_file_days` | Keep debug logs for | int 1–30 | `7` | settings | internal | ja | – |

**Hilfetexte**

- `advanced.import_execution` – WP-CLI is always possible.
- `advanced.file_delivery` – PHP works everywhere. The server methods are faster for large files.
- `advanced.media_delete_grace_days` – Unreferenced protected files are deleted after this grace period.
- `advanced.cron_stale_warning_minutes` – The system status and Site Health warn after this time.
- `advanced.log_level` – Course Captain writes log files to the protected folder, never into the web root. “Info” and “Debug” write much more and fall back to “Warnings and errors” automatically after 7 days. Logs never contain answers, passwords or tokens.
- `advanced.error_log_days` – Log files with errors and warnings are deleted after this time.
- `advanced.debug_file_days` – Log files with info and debug entries are deleted after this time (at most 30 days).

## Sperren und Übersteuerungen

Rangfolge: Sperre der Website > Prüfung/Aktivität > Kurs (Gruppe, Medium) > Website-Wert > eingebauter Standard. Eine Sperre lässt gespeicherte Kurs- und Prüfungswerte ruhen; nach dem Entsperren gelten sie wieder. Die Seite „Sperren und Kurs-Übersteuerungen“ zeigt je Einstellung die Anzahl eigener Werte, sperrt nach einem Bestätigungsschritt mit der Zahl der betroffenen Kurse und Prüfungen und setzt ausgewählte eigene Werte gesammelt auf „erben“ zurück (veröffentlichte Prüfungen bleiben unverändert).

| Schlüssel | Bezeichnung | Ebenen | Sperrbar | Wirksam |
|---|---|---|---|---|
| `general.catalog_indexing` | Search engine indexing | course | ja | applies immediately |
| `display.container_width` | Content width | course | ja | applies immediately |
| `display.breadcrumbs` | Breadcrumbs | course | ja | applies immediately |
| `learning.course_completion_default` | Course completion rule | course | ja | applies from the next publication |
| `learning.mastery_algorithm` | Mastery algorithm | course | ja | applies from the next publication |
| `learning.favorites_enabled` | Favorites | course | ja | applies immediately |
| `learning.mistake_training_enabled` | Mistake training | course | ja | applies immediately |
| `learning.feedback` | Feedback while learning | course, activity | ja | applies immediately |
| `exams.max_attempts` | Maximum attempts | exam_rule | ja | applies from the next attempt start |
| `exams.cooldown_seconds` | Waiting time after a failed attempt (seconds) | exam_rule | ja | applies from the next attempt start |
| `exams.count_cancelled` | Cancelled attempts count | exam_rule | ja | applies from the next attempt start |
| `exams.time_limit_minutes` | Time limit (minutes) | exam_rule | ja | applies from the next attempt start |
| `exams.timer_mode` | Timer mode | exam_rule | ja | applies from the next attempt start |
| `exams.late_tolerance_seconds` | Late tolerance (seconds) | exam_rule | ja | applies from the next attempt start |
| `exams.shuffle_questions` | Shuffle questions | exam_rule | nein | applies from the next attempt start |
| `exams.shuffle_answers` | Shuffle answers | exam_rule | ja | applies from the next attempt start |
| `exams.feedback` | Exam feedback | exam_rule | ja | applies from the next attempt start |
| `exams.auto_submit_inactive_days` | Submit inactive attempts after (days) | exam_rule | ja | applies from the next attempt start |
| `enrollment.self_enrollment` | Self-enrollment | course | ja | applies immediately |
| `enrollment.default_access_days` | Access duration (days) | course | ja | applies to new access and invitations |
| `enrollment.groups_enabled` | Groups | course | ja | applies immediately |
| `certificates.auto_issue` | Issue certificates automatically | course | ja | applies from the next completion |
| `certificates.base_template` | Certificate template | course | ja | applies to new certificates |
| `enrollment.claim_policy` | Who may redeem an invitation | group | ja | applies to new access and invitations |
| `enrollment.invite_valid_days` | Invitation valid for (days) | group | nein | applies to new access and invitations |
| `privacy.external_media_mode` | External media | media | nein | applies immediately |

## WP-CLI

Akteur ist `system:cli`; jede Änderung läuft durch dieselbe Prüfung wie das Formular und steht im Änderungsprotokoll (Kontext `cli`).

```
wp course-captain settings list [--tab=<tab>] [--format=json]
wp course-captain settings get <tab.feld> [--format=json]
wp course-captain settings set <tab.feld> <wert> [--confirm] [--format=json]
wp course-captain settings export [--output=<datei>] [--include-personal] [--include=roles]
wp course-captain settings import <datei> [--dry-run] [--tabs=<a,b>] [--surfaces=<roles,locks>] [--confirm] [--yes]
wp course-captain settings reset <tab> --yes | reset <tab.feld> [--confirm]
```

- Werte: Text wie eingegeben, Ja/Nein als `true`/`false`, Listen und Objekte als JSON (`'{"visibility":"after_submit"}'`).
- Änderungen, die in der Oberfläche einen Bestätigungsdialog zeigen, brauchen `--confirm`; das Einschalten der Datenlöschung beim Entfernen des Plugins braucht `--confirm=<Name der Website>`. Ohne Bestätigung: Exit-Code 1, nichts gespeichert.
- `export --output` schreibt nur neue Dateien außerhalb des WordPress-Verzeichnisses mit Rechten `0600`.
- `import` zeigt zuerst die Unterschiede; ohne `--yes` wird nichts geschrieben.

## Export und Import

Die Einstellungsdatei (`format: course-captain-settings`, Version 1) enthält die Tabs und je Fläche einen eigenen Abschnitt: `locks` (Sperren, immer), `roles` (Rollen, nur mit Opt-in). Weitere Flächen (z. B. Mailvorlagen) melden sich über den Filter `course_captain_settings_transfer_providers` an. Der Import zeigt je Tab und Fläche eine Vorschau der Unterschiede, wendet ausgewählte Bereiche einzeln und atomar an und legt für Tabs und Rollen einen Rückgängig-Stand für 30 Tage an. Nie enthalten: Geheimnisse, umgebungsgebundene Felder (Spalte Export = nein), Kurs- und Prüfungs-Übersteuerungen.

