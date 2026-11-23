# R1-18 · GM-Nachtrag Gruppe g5 (GM-162, 167, 174, 175, 176, 189, 190)

**Stand:** 23.11.2026 · **Basis:** `claude/blissful-curie-tuuf7q` (1322ec4) · **Test-DB:** `ccap_test_g5`
**Quellen:** R0-05 §1.14–§1.16, §1.20, §3 (D-01…D-14); R0-13 §4.3/§4.4 (Audit-Prune, Ankereintrag); R0-31 §2.1 (RT-23, RT-36), RR-7; R0-19 R-38, R-48, §3 (`ccap_export_*`), Shortcode `[ccap_privacy_request]`; R0-07 RG-13, RG-17, M-23; R0-37 §4.5; E-16, E-43, E-44. Clean Room (E-01): kein LearnBoating-Quelltext gelesen.
**Test:** `tests/integration/GmOpsPrivacyFaqTest.php` (`@group R1-18`, je Test `@group GM-xxx`), 7 Tests.

## 1. Je GM-ID

| GM | Umsetzung | Test |
|---|---|---|
| GM-162 | `RetentionService::prune_audit()` als isolierter Schritt `RT-23_audit` im Stundenlauf: Einträge älter als 4 Jahre (`AUDIT_YEARS`), höchstens 5 000 je Lauf (`AUDIT_BATCH`), älteste zuerst; je Lauf mit Wirkung ein Sammeleintrag `audit_pruned` (Anzahl, ältestes/jüngstes Datum, `anchor` = `row_hash` der zuletzt gelöschten Zeile als Ankerwert der Restkette, R0-13 §4.3); Bezeichnung in `AuditView` | `test_GM_162_audit_retention_in_batches`: 6 000 Einträge T0 − 5 J., 10 Einträge T0 − 1 J.; 1. Lauf 5 000, 2. Lauf 1 000, junge 10 bleiben, 3. Lauf 0; zwei Sammeleinträge mit Spanne und Anker |
| GM-167 | `Admin\MigrationNotice`: bei Zustand `failed`/`blocked`/`newer` des Migrationsberichts genau **ein** Fehlerhinweis im gebündelten Hinweis (`ccap_migration_failed`, GM-140), Text nennt nur Schritt-IDs, nie die DB-Meldung; nur mit `manage_options`; Aufruf in `Plugin::on_admin_init()` nach dem Migrationsversuch | `test_GM_167_one_masked_notice_for_admins`: zwei fehlgeschlagene Schritte mit DB-Meldung (Benutzer, Host, Tabelle) → genau 1 Hinweis, 1 `notice`-Box, Schritt-IDs sichtbar, Meldung maskiert; Abonnent und Redakteur ohne Hinweis; nach Reparatur kein Hinweis |
| GM-174 | `Privacy\SelfService`: REST `POST /me/privacy-requests` (R-48, 202 `created\|resent\|confirmed`), admin-post `ccap_privacy_request`, Shortcode `[ccap_privacy_request]`. Antrag nur für das eigene Konto als WP-Datenschutzantrag + Bestätigungsmail; offener Antrag gleichen Typs → kein neuer, Mail erneut (`resent`); Mail-Link zeigt auf `admin-post.php?action=ccap_privacy_confirm`: **GET zeigt nur den Bestätigungsknopf**, **POST** prüft den Schlüssel und löst den Kern-Hook `user_request_action_confirmed` aus (danach greifen die Datenschutz-Einstellungen, Kontolöschung nur mit `auto_erase_on_confirm`, E-43). Konten mit Verwaltungs-/`ccap_*`-Rechten (RB-9) können keine Selbstlöschung beantragen (403 `ccap_forbidden`, `reason: staff_account`). Limit 5/Tag (`privacy`). Audit `privacy_request_created` nur mit Antrags-ID und Typ | `test_GM_174_self_service_requests`: Antrag + Mail mit eigenem Link; zweiter Antrag `resent` ohne neuen Antrag; ungültiger Typ 400; Löschantrag mit Opt-in: GET-Seite ändert nichts (Antrag `pending`, Konto da), falscher Schlüssel `invalid`, POST löscht Lerndaten und Konto, Link nur einmal; U-SM: Löschantrag 403, Export möglich; Gast 401; 429 nach 5 Anträgen; Audit ohne Adresse |
| GM-175 | `Privacy\DataExport`: `POST /me/data-exports` (202 `pending`), Aufbau im Hintergrund über den vorhandenen Cron-Hook `ccap_privacy_export_generate` mit `[0, user_id]`; `GET /me/data-exports/{uid}`: `pending` → 409 `ccap_export_not_ready` (`retry_after`), `ready` → 200 mit Daten (Exportgruppen von `PrivacyService::export`), abgelaufen (24 h, RT-36) → 410 `ccap_export_expired` und Datei gelöscht, Fehler → `failed`/410 `ccap_export_failed`; Auftrag als Nutzeroption je Site, fremde UIDs 404; Dateien im privaten `packages`-Ordner, Stundenlauf `RT-36_data_exports` löscht alte Dateien. Katalogtexte der drei Codes in `RestContract` | `test_GM_175_async_export`: 202 `pending`, geplant, kein zweiter Auftrag; 409 vorher; nach dem Hintergrundschritt `ready` mit `cc-favorite`, `no-store`; anderes Konto 404, Gast 401; Ablauf → 410, Datei weg; Schreibfehler → `failed`, 410, kein Neuaufbau; neuer Antrag baut wieder; RT-36 löscht alte Datei |
| GM-176 | `PrivacyService::policy_html()`: Vorschlagstext über `wp_add_privacy_policy_content` (Abschnitt „Course Captain“) mit Fristen aus `RetentionService` (Antworten 2 J., Versuche/Zertifikate 10 J., Audit 4 J., Benachrichtigungen 90 d), ohne Links | `test_GM_176_privacy_policy_text`: genau ein Abschnitt, alle Fristen genannt, 0 Links/URLs |
| GM-189 | `Course\StarterContent` (E-16: nur Mechanik; `ITEMS` ist in R1 leer, Texte mit R2-03): idempotent über `ccap_source_map` (`source_system = starter`, `source_id = <Schlüssel>`), nur in Admin/Cron/CLI (nie Frontend/REST/Ajax), unter Lease `starter-content` (immer freigegeben); geänderte Definition aktualisiert, auf der Site bearbeitete Einträge bleiben (Hash-Vergleich); automatischer Auslöser `maybe_run()` in `admin_init` nur bei geänderter Menge (Option `course_captain_starter_state`) | `test_GM_189_starter_content_seeder`: FX-FAQ-1…5; Frontend/REST/Ajax importieren nicht; 1. Lauf 5 neu, Sperre frei; 2. Lauf 5 unverändert, keine Dubletten; Update-Lauf: 1 aktualisiert, 1 lokal bearbeitet übersprungen; gehaltene Sperre → `skipped_locked`, fremde Sperre bleibt; R1-Auslöser ohne Wirkung |
| GM-190 | `Course\FaqService` + REST `GET /courses/{course}/faqs` (R-38): nur veröffentlichte Einträge, Zugang wie Kursobjekte ohne Modulbezug (mind. ein Modul bzw. Kurs-Grant, R0-08), Rohliste im K1g-Cache je Kursgeneration, jede Änderung eines `ccap_faq` (`clean_post_cache`) erhöht die Generation; Antwort je Anfrage gerendert, `[ccap_protected]` zeigt Personen ohne Zugang den Hinweis | `test_GM_190_faq_cache_rest_gate`: Gast 401 ohne FAQ-Daten; Lernende: ohne Entwurf, UID, geschützter Abschnitt → Hinweis; Titeländerung, neuer und verworfener Eintrag sofort sichtbar (warmer Cache); mit Zugang zum geschützten Kurs Abschnitt sichtbar; ohne Kurszugang 403, unbekannter Kurs 404 |

## 2. Dateien

Neu: `src/Admin/MigrationNotice.php`, `src/Privacy/SelfService.php`, `src/Privacy/DataExport.php`, `src/Course/FaqService.php`, `src/Course/StarterContent.php`, `tests/integration/GmOpsPrivacyFaqTest.php`.
Geändert: `src/Privacy/RetentionService.php` (RT-23, RT-36), `src/Privacy/PrivacyService.php` (Policy-Text), `src/Privacy/PrivacySettings.php` (Cron-Handler mit 2 Argumenten), `src/Learner/LearnerRoutes.php` (4 Routen), `src/Kernel/Plugin.php` (Registrierung, Hinweis, Seeder-Auslöser), `src/Kernel/UninstallManifest.php` (`course_captain_starter_state`, Nutzeroption `course_captain_data_export`, Post-Meta `_ccap_self_service`), `src/Ops/AuditView.php` (`audit_pruned`, `privacy_request_created`), `src/Rest/RestContract.php` (Katalog `ccap_export_*`), `tests/unit/KernelContractTest.php` (Kern-Hook `user_request_action_confirmed` in der Präfix-Ausnahmeliste, wie `the_content`), `tools/qa/gm-coverage-exceptions.php` (7 Einträge entfernt), `docs/api/routes.v1.json`, `docs/API.md`, `docs/API-CHANGELOG.md`, `languages/course-captain.pot`.

## 3. Kommandos und Ergebnisse (23.11.2026)

| Kommando | Ergebnis |
|---|---|
| `php8.5 vendor/bin/phpcs -q` | 0 Befunde |
| `php8.5 vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | 0 Fehler |
| `php8.5 vendor/bin/phpunit -c phpunit.xml.dist` | 120 Tests, 2 übersprungen, OK |
| `WP_TESTS_DB_NAME=ccap_test_g5 php8.5 vendor/bin/phpunit -c phpunit-integration.xml.dist` | 430 Tests, 31 übersprungen (pcntl), OK |
| `WP_TESTS_DB_NAME=ccap_test_g5 php8.5 vendor/bin/phpunit -c phpunit-integration-multisite.xml.dist` | 443 Tests, 31 übersprungen (pcntl), OK |
| `php8.5 tests/guards/free-guard.php` | 0 Befunde |
| `php8.5 tests/guards/textdomain-lint.php` | 0 Probleme (181 Dateien) |
| `php8.5 tools/qa/gm-coverage.php` | 210 IDs, 178 mit Test, 32 Ausnahmen (29 `open`, 1 `deviation`, 1 `r2`, 1 `r3`); missing/stale/invalid/unknown 0 |

POT: `wp i18n make-pot` (CI-Parameter) mit `--skip-block-json`, weil der Download von `block-i18n.json` im Sandbox-Netz scheitert; die sechs Block-Einträge aus `blocks/*/block.json` wurden unverändert aus der vorherigen POT übernommen (identisch zur CI-Erzeugung).

## 4. Abweichungen, Annahmen, Hinweise

| Nr. | Thema | Stand |
|---|---|---|
| A-1 | GM-190 „Gast ohne Entwurf; Gast Hinweis statt Abschnitt“: Die Kurs-FAQ ist nach R0-19 R-38/R0-07 RG-13 nur mit Kurszugang lesbar, eine öffentliche FAQ gibt es nach E-16 nicht. Gäste erhalten 401 ohne Daten; der Gate-Hinweis wird mit einer angemeldeten Person ohne Zugang zum geschützten Kurs geprüft | Lesart, keine Produktänderung; im R1-GATE bestätigen |
| A-2 | GM-175: R0-19 nennt die Fehlercodes, aber keine Web-Route (Quelle nur App-API, E-05). Routen `POST /me/data-exports`, `GET /me/data-exports/{uid}` neu (stabilitätsneutral, API-Changelog). Ein Auftrag je Person und Site | Lesart nach R0-05 L-27 („Web-Pfad“) |
| A-3 | GM-174 „POST löscht“: Löschung des Kontos folgt E-43 (Opt-in `auto_erase_on_confirm`); im Standard wartet der bestätigte Antrag auf die Verwaltung (GM-182). Der Test schaltet das Opt-in ein | konform zu E-43 |
| A-4 | GM-162: Die Hash-Kette selbst wird weiterhin nicht geprüft (keine `audit verify`-Funktion vorhanden); der Ankerwert steht im Sammeleintrag | Prüfung H-36 offen (R1-38) |
| A-5 | Frist `audit_log_years` ist noch Konstante (4 J.), nicht editierbar (R1-17 A-4) | unverändert offen |
| A-6 | Umgebung: Mit dem in der Anleitung vorgeschlagenen Symlink `vendor → /home/user/wp-coursemaster/vendor` lädt der Composer-Autoloader `CourseCaptain\*` aus dem **Haupt-Checkout** (`$baseDir` folgt dem realen Pfad), geänderte Klassen des Worktrees werden in PHPUnit also nicht getestet. Lauf hier mit einem Worktree-eigenen `vendor/` (echte Kopien: `autoload.php`, `composer/`, `bin/`, `phpstan/`; übrige Pakete als Symlinks); Prüfung per `ReflectionClass(Plugin)::getFileName()` zeigt in den Worktree | betrifft alle Worktree-Gruppen; Integrator sollte Testergebnisse anderer Gruppen darauf prüfen |

Keine `deviation`-Einträge neu angelegt; keine Freigabe nach §5.1 nötig.
