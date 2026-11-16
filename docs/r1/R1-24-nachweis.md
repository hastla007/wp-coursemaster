# R1-24 · Dokumentation, Onboarding und Support – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-c`
**DoD (TODO R1-24):** Installationsguide, Admin-/Autoren-/Lernendenhilfe, Troubleshooting, Privacy-Hinweise, API- und Release-Doku fertig.
**Quellen:** R0-13 (Supportstufen §1, Grundregeln GR-1…7, Runbooks §5 RB-SUP-1…8, Fristenempfehlung §5.1), R0-35 §1.4 (Readme), §3.5 (Forum-Regeln, Antwortvorlagen), §3.6 (Sicherheitsablauf), §5 (RC-027, 033, 036, 037, 067, 070…074, 076), R0-18 §3 (Artefakte, Release-Workflow), R0-19 (Routen, Fehlerkatalog, Hooks, Events), R0-31/R0-17 (Datenklassen, Fristen), R0-30 (Admin-UX), R0-15 (Barrierefreiheit), R0-34 (Integrationsstatus), Nachweise R1-01…R1-44. Clean Room (E-01): Die Nutzerdoku nennt keine Altprodukt-Namen; Wortliste geprüft.
**Grundsatz:** Beschrieben ist nur, was der Code in `src/` tut. Fehlende Teile sind als „geplant (R1-xx)“ markiert. Die Lernendenhilfe weist ausdrücklich darauf hin, dass Lektions- und Prüfungsoberfläche noch fehlen.

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `docs/user/README.md` | Index, Begriffe, Hinweis auf Entwicklungsstand und auf `docs/API.md` (R1-28) |
| `docs/user/installation.md` | Voraussetzungen (PHP 8.3, WP 7.0, MariaDB 10.6/MySQL 8.0, InnoDB, JSON, utf8mb4, Permalinks), Aktivierungsablauf, Migration/`ccap_maintenance`, Multisite (Blockade, Haltemodus), erste Schritte, Deaktivierung, Deinstallation mit Tabelle „Standard / mit bestätigter Datenlöschung“ |
| `docs/user/admin.md` | Rollen und 24 Rechte, Einstellungen (ausgewertete vs. registrierte Felder), Zugänge, Gruppen und Plätze, Lernsperre, Benachrichtigungen (16 Typen mit Stand), Mailvorlagen, Ausgangsmodus, Zertifikate, Import/Export, Website-Zustand, Audit log |
| `docs/user/autoren.md` | Kursaufbau, Editor-Metaboxen, 7 Activity-Typen mit Abschlussregeln, 9 Fragetypen, Prüfungsregeln/Blueprints/Varianten, Validator mit vollständiger Tabelle der umgesetzten VAL-Codes, Revisionen |
| `docs/user/lernende.md` | Lernbereich und Status, Fortschritt, Smarttrainer-Regeln, Karteikarten/Favoriten/Fehlertraining, Prüfungen (Serverzeit, Kulanz, Fortsetzen, Offline, Tabs, Abbruch, Ergebnis), Nachweise, Benachrichtigungen |
| `docs/user/troubleshooting.md` | Grundregeln und Stufen (R0-13), Fehlercodes `ccap_*` mit Status und Maßnahme, Cron-Hooks und Outbox-Dead-Letter, Website-Zustand, Caches/Page-Cache, Ratenlimits, Runbooks RB-SUP-1…8, Antwortvorlagen, bekannte Einschränkungen, Forum-Regeln, Sicherheitsmeldung |
| `docs/user/datenschutz.md` | Datenklassen aller Tabellen, Speicherorte, Export-Gruppen, Löschumfang, Aufbewahrungsfristen laut Code, Abweichung zum Policy-Text, externe Dienste, Sicherheitsvoreinstellungen |
| `docs/user/entwickler.md` | Handshake, implementierte REST-Routen (Lernende, Autoren, öffentlich), admin-post-Aktionen, Shortcodes/Blöcke, geplante Routen, Fehlerformat heute und nach R1-28, Hooks/Filter, Outbox-Ereignistypen, Konstanten, QA-Kommandos; verweist auf `docs/API.md`/`docs/API-CHANGELOG.md` (werden von R1-28 angelegt, hier bewusst nicht erstellt) |
| `docs/user/release.md` | Versionierung, Änderungsprotokoll R1, Release-Checkliste (Kurzfassung RC mit Status), Release-Ablauf, Vorlage Release-Notes (Marker, Rollback-Klasse, Backup, Signaturprüfung) |
| `readme.txt` | Beschreibung auf R1-Stand, FAQ Support-Kanal und Sicherheitsmeldung (RC-074), Changelog, **Privacy korrigiert** (vorher „stores no personal data“, falsch seit R1-09), External services um eingebettete Fremdvideos ergänzt (RC-027) |
| `CHANGELOG.md` | R1-Einträge unter `1.0.0-dev` ergänzt (Kopfzeile unverändert, RC-041 bleibt grün) |

## 2. Review-Protokoll (Abgleich Doku ↔ Code)

| Doku-Abschnitt | geprüfte Code-Stellen | Befund |
|---|---|---|
| Installation §1 Voraussetzungen | `course-captain.php` (Kopf, Konstanten), `composer.json` (`php >=8.3`), `src/Kernel/Requirements.php`, `readme.txt` | übereinstimmend; Fehlercodes `ccap_requirement_*` |
| Installation §2 Aktivierung/Migration | `src/Kernel/Activator.php`, `src/Storage/Migrator.php` (`ccap_maintenance`, `Retry-After: 30`), `src/Storage/Schema.php` | übereinstimmend |
| Installation §3 Multisite | `src/Kernel/{NetworkGuard,HoldMode,Plugin}.php` | übereinstimmend |
| Installation §5/§6 Deaktivierung/Uninstall | `Plugin::deactivate()`, `src/Kernel/{Uninstaller,UninstallManifest,CronHooks}.php` | Beiträge und Dateien bleiben auch bei Löschung (R1-23); kein UI zum Setzen von `delete_data_on_uninstall` |
| Admin §1 Rollen | `src/Policy/Roles.php`, `src/Kernel/Capabilities.php`, `src/Policy/{PolicyService,EscalationGuard}.php` | übereinstimmend; R1-03 A-1 übernommen |
| Admin §2 Einstellungen | `src/Settings/SettingsSchema.php`, alle Aufrufe von `Settings::get()` und direkte `get_option('course_captain_settings_*')` | nur 11 Felder werden ausgewertet; Rest als „ohne Wirkung“ dokumentiert |
| Admin §3 Zugänge/Gruppen | `src/Access/{GrantService,GrantTransitions,GroupService}.php` | Einladungsfrist 30 d und Pending-Timeout 24 h fest (Settings ohne Wirkung) |
| Admin §5 Benachrichtigungen | `src/Notification/{NotificationTypes,NotificationService,NotificationPrefs,MailTemplates,OutboundMode}.php` | 7 Typen verdrahtet; `NotificationService::EVENTS` enthält keine `certificate.*` → N-05…N-07 weiterhin nicht verdrahtet |
| Admin §6 Zertifikate | `src/Certificate/{CertificateService,CertificateRoutes,CertificatePages}.php` | admin-post ohne Formular (R1-35) |
| Admin §7 Import | `src/Import/*` | keine Oberfläche, keine REST-Route |
| Admin §8 Website-Zustand/Audit | `src/Ops/{HealthChecks,AuditView,AuditScreen}.php`, `NotificationService::health_tests` | 4 Tests; Docblock in `HealthChecks` nennt „retention backlog“, Test existiert nicht |
| Autoren §2/§3 | `src/Admin/EditorScreens.php`, `src/Activity/Providers/*` | übereinstimmend |
| Autoren §4 | `src/Question/QuestionTypes.php` | übereinstimmend |
| Autoren §5 | `src/Exam/ExamRules.php` (`default_settings`), `ExamSessions.php` | globale `exams.*`-Settings werden nicht als Regel-Defaults genutzt |
| Autoren §6 VAL-Codes | `src/Publish/CourseValidator.php` (alle `add('VAL-…')`), `AuthoringRoutes.php`, `PublishGuard.php` | Tabelle vollständig für die umgesetzten Codes |
| Lernende | `src/Learner/*`, `src/Learning/{SmartTrainer,Flashcards,Favorites,ErrorTraining,AnswerService}.php`, `src/Exam/{ExamSessions,ExamFeedback}.php`, `src/Certificate/CertificatePages.php`, `src/Notification/NotificationPrefs.php` | Lektions-/Prüfungs-UI fehlt (R1-16 A-1), ausdrücklich vermerkt |
| Troubleshooting §2 Fehlercodes | `grep` aller `CourseException`/`WP_Error`/`Decision`-Codes in `src/` mit Status | 44 Codes erfasst |
| Troubleshooting §3–§6 | `src/Kernel/CronHooks.php`, `src/Event/OutboxDispatcher.php`, `src/Cache/{CacheStore,CacheInvalidation}.php`, `src/Storage/RateLimiter.php`, Konstanten in `SmartTrainer`, `ExamSessions`, `CertificateService`, `NotificationService` | `ccap_privacy_export_generate` ohne Handler (reserviert) |
| Datenschutz | `src/Privacy/{PrivacyService,RetentionService}.php`, `CertificateService::erase/export_items/expire_retention`, `src/Activity/ExternalMedia.php` | zwei Abweichungen (§3 B-1, B-2) |
| Entwickler | `src/Learner/LearnerRoutes.php`, `src/Admin/AuthoringRoutes.php`, `src/Certificate/CertificateRoutes.php`, alle `do_action`/`apply_filters` mit `course_captain_`, `src/Event/*` (Ereignistypen), `src/functions.php`, `src/Contract/Api.php` | Abweichungen zu R0-19 §2.3 (Parameter R-08/R-20) dokumentiert; R1-28-Vertragsänderungen nach Koordinatorvorgabe aufgenommen |
| Release | `git log` des Integrationsstands, `docs/r1/*-nachweis.md`, R0-18 §3, R0-35 §5, `tests/unit/PluginHeaderTest.php` | – |

Wortliste (Altnamen, Modellnamen) über `docs/user/`, `readme.txt`, `CHANGELOG.md`: 0 Treffer.

## 3. Befunde im Code (gemeldet, nicht behoben – Auftrag „nur Doku“)

| Nr. | Befund | Ort | Wirkung |
|---|---|---|---|
| B-1 | `privacy.external_media_mode = link_only` ist im Register erlaubt, `ExternalMedia::mode()` kennt aber nur `click_to_load` und fällt sonst auf `always_load` zurück | `src/Activity/ExternalMedia.php:52-56`, `src/Settings/SettingsSchema.php` | **fail-open für Datenschutz**: Wer „nur Link“ wählt, bekommt sofortiges Laden fremder Videos. Vorschlag: `link_only` umsetzen oder wie `click_to_load` behandeln |
| B-2 | Vorschlagstext für die Datenschutzerklärung nennt Fristen, die der Code nicht umsetzt („2 Jahre nach Kursende“ – Code: 730 Tage nach Erstellung; „Prüfungsversuche 10 Jahre, beendete Zugänge 10 Jahre, Audit 4 Jahre“ – kein Löschlauf) | `src/Privacy/PrivacyService.php:349` vs. `RetentionService.php` | irreführende Datenschutzerklärung beim Betreiber; in `datenschutz.md` gewarnt |
| B-3 | Eraser löscht die User-Option `course_captain_notification_prefs` nicht (R1-21-Nachweis NTF-030 klingt umfassender) | `PrivacyService::erase_user()` | geringer Restbestand (Liste abbestellter Typen) |
| B-4 | `assignment_*`-Tabellen sind als personenbezogen exportierbar (`cc-submissions`), werden vom Eraser aber nicht gelöscht | `PrivacyService::erase_user()` | heute ohne Daten (Aufgaben nicht gebaut); mit R1-46 nachziehen |
| B-5 | `learning.feedback.visibility` wird nicht ausgewertet (nur `show_correct_answers`) | `src/Learning/AnswerService.php:183` | Einstellung ohne Wirkung |
| B-6 | `CertificateRoutes::run()` und `AuthoringRoutes::run()` geben `CourseException::$data` nicht weiter (anders als `LearnerRoutes`) | `src/Certificate/CertificateRoutes.php`, `src/Admin/AuthoringRoutes.php` | fehlende Details in Fehlern; Angleichung mit R1-28 |
| B-7 | Docblock von `HealthChecks` nennt einen Test „retention backlog“, der nicht existiert | `src/Ops/HealthChecks.php:22` | nur Doku im Code |
| B-8 | Kein Hinweis/keine Prüfung bei „einfachen“ Permalinks; Lernbereich, Nachweis- und Abmeldelinks setzen Rewrite voraus | `src/Learner/LearningArea.php::url()` | Links laufen ins Leere; Prüfung im Systemstatus vorschlagen (R1-38) |

## 4. Widersprüche Doku ↔ Spezifikation

| Nr. | Thema | Stand |
|---|---|---|
| W-1 | R0-19 §2.3 R-08/R-20 (`answer_ids[]`, `known`, `response_ms`) vs. Code (`answer`, `idempotency_key`) | in `entwickler.md` als Abweichung markiert; Angleichung R1-28 |
| W-2 | R0-13 sieht Systemstatus, Diagnosepaket, Debug-Log, K-01…K-17 vor; nichts davon hat eine Oberfläche | Runbooks nennen die heutigen Ersatzwege und „geplant (R1-38)“ |
| W-3 | R0-35 RC-070 verlangt `SECURITY.md` mit Sicherheitsadresse des Auftraggebers (E-211) | nicht angelegt, Adresse fehlt; Ablauf in `troubleshooting.md` §10 beschrieben |
| W-4 | R1-17 A-2 (Paketkopien aktiv + 1 vs. aktiv + 2) | Doku folgt dem Code (aktiv + jüngstes Rollback-Ziel) |

## 5. Prüfungen (16.11.2026)

Nur Doku geändert (kein PHP, keine `.pot`). `php tests/guards/free-guard.php` → 128 Dateien, 0 Treffer, 0 Allowlist-Fehler · `php tests/guards/textdomain-lint.php` → 123 Dateien, 0 Probleme · `vendor/bin/phpunit -c phpunit.xml.dist` → **OK (88 Tests, 2 740 Assertions)**, inkl. `PluginHeaderTest::test_RC_041_versions_are_equal` nach Änderung von `readme.txt`/`CHANGELOG.md`. Integrationssuiten nicht erneut gelaufen (keine Codeänderung).

## 6. Offene Punkte

| Nr. | Punkt | Ziel |
|---|---|---|
| O-1 | Screenshots in der Betreiberdoku (R0-13 §5.2) | sobald Admin-Oberflächen existieren (R1-32/R1-35/R1-38) |
| O-2 | `SECURITY.md`, Sicherheitsadresse, Kontaktweg WordPress.org-Plugins-Team (RC-070/071) | Auftraggeber (E-211), dann R1-39 |
| O-3 | Review der Runbooks durch eine Support-Testperson (SUP-039) | Auftraggeber |
| O-4 | Konformitätsbericht Barrierefreiheit (RC-077), Assets (RC-039/040), Verzeichnisseite (RC-053) | R1-19 / R1-39 |
| O-5 | Doku nachziehen, sobald R1-28 (`docs/API.md`, Fehlerformat, `[ccap_protected]`) gemergt ist; Querverweise prüfen | Koordinator nach Merge |
| O-6 | Deutsche Übersetzung der Oberflächentexte; die Doku nennt teils englische Originalbezeichnungen | R1-19/R1-39 |
| O-7 | Befunde B-1…B-8 beheben | jeweilige Folgeaufgaben (B-1/B-2 vor Release) |

Review durch zweite Person vor Merge (AG-05).
