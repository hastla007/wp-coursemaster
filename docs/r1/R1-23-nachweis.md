# R1-23 · Upgrade-, Restore- und Uninstall-Proben – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-b`
**DoD (TODO R1-23):** Migration von Vorversionen, Backup-Restore, Plugin-Deaktivierung, sichere Datenaufbewahrung und explizite Uninstall-Policy getestet.
**Quellen:** R0-16 §1.4 (Health-Zeile Site-Identität), §2.1 (Upgrade-Runbook), §2.3 (Site-Identität, Haltemodus, drei Bestätigungswege, Nachhol-Grenze), §2.5 (Deaktivierung, Uninstall mit/ohne Datenlöschung, Manifest), §3.1 (Staging), §4 (REC-001…REC-025); R0-17 §5.5 (Epochenzähler `course_captain_state_epoch`, Journal außerhalb des Backups); R0-37 §4.1/§4.2, §6.1 (Vorversion, Idempotenz, verwaiste Lease); R0-27 §1.3 und R0-31 (`general.delete_data_on_uninstall`, E-89); R0-19 §4.7 (CLI `identity`); E-07/E-49 (Multisite nur Isolation), E-85, E-86, E-88, E-89, E-92; R1-21-Nachweis A-6 (Haltemodus-Erkennung an R1-23), R1-40-Nachweis. Clean Room (E-01): LB nur als Verhaltensbeleg aus R0-16, kein Quelltext gelesen oder übernommen.

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Kernel/SiteIdentity.php` | Option `course_captain_site_identity` = Hashes aus roher Option `home`, aufgelöstem privatem Basisordner (`ProtectedStorage::base()`), `DB_NAME\|<Blog-Präfix>` und `wp_get_environment_type()`; Status `ok/held/copy`, `changed`, `detected_at`, `choice`, `confirmed_at/by`. Reine Entscheidung `decide()`; `evaluate()` (nur lesend), `check()` (persistiert die erste Erkennung, ein späteres Zurückkehren der Werte hebt den Haltemodus nicht auf), `is_held()`. Fehlende Option (Neuinstallation, Upgrade von Version ohne Identität) wird still aufgezeichnet. Epochenzähler `course_captain_state_epoch` + Kopfdatei `course-captain-epoch-<hash>.json` in `COURSE_CAPTAIN_PRIVACY_JOURNAL_DIR` (je Site, Schlüssel aus `DB_NAME\|Präfix`): Journal-Epoche > DB-Epoche ⇒ DB wurde zurückgespielt (Restore am selben Ort, Grund `epoch`). `bump_epoch()` persistiert vorher eine offene Erkennung. `confirm()` (Site-Admin `manage_options` oder WP-CLI): `same_place`/`moved` heben auf, `copy` hält dauerhaft; schreibt Identität neu, gleicht die Epoche an, Audit `site_identity_confirmed`. Testnähte `with_journal_dir()`, `with_environment()` (`@internal`) |
| `src/Admin/IdentityNotice.php` | nicht wegklickbarer Admin-Hinweis für Site-Admins mit drei Knöpfen (POST an `admin-post.php?action=ccap_site_identity`, Nonce); bei `copy` Info-Hinweis ohne Knopf „Kopie“ |
| `src/Ops/IdentityCommand.php` | `wp course-captain identity [status] [--format=json]` (Exit 1 nur bei `held`) und `identity confirm <same_place\|moved\|copy>`; testbarer Kern `execute()` |
| `tests/unit/SiteIdentityTest.php` | Entscheidungstabelle (10 Fälle) und Vertragskonstanten |
| `tests/unit/ManifestCoverageTest.php` | REC-020-Erweiterung: jede in `src` per `table()/insert()/update()/delete()` genutzte Tabelle steht im Manifest und in `PrivacyService::CLASSIFICATION`; Negativtest; registrierte Post-Types im Manifest |
| `tests/integration/RecoveryTest.php` | 6 Tests (REC-001/002/003/016/021, PRIV-010-Erkennung) |
| `tests/integration/UpgradeProbeTest.php` | 3 Tests (REC-023, REC-006/MIG-021-Anteil, REC-018/GM-158) |
| `tests/integration/UninstallProbeTest.php` | 4 Tests (REC-019 ohne/mit Bestätigung, E-89/SET-035, Neuinstallation nach Löschung) |
| `tests/integration/RecoveryMultisiteTest.php` | 2 Tests (REC-024, REC-019/MS-015 voll), `ms-required` |

### Änderungen an gemeinsam genutzten Dateien

| Datei | Änderung |
|---|---|
| `src/Notification/OutboundMode.php` | `is_held()` übergibt `SiteIdentity::is_held()` als Startwert an den bestehenden Filter `course_captain_notification_hold` (statt `false`); kein neuer Hook |
| `src/Notification/NotificationService.php` | nach einem Versandlauf mit `sent > 0`: `SiteIdentity::bump_epoch()` |
| `src/Privacy/PrivacyService.php` | nach `erase_user()` mit Treffern: `SiteIdentity::bump_epoch()` |
| `src/Kernel/CronHooks.php` | stündlicher Lauf: isolierter Schritt `site_identity` als erster Schritt (vor `notifications`); `CronHooks::ALL` unverändert |
| `src/Kernel/Plugin.php` | Registrierung `IdentityNotice` und `IdentityCommand` |
| `src/Kernel/UninstallManifest.php` | Option `course_captain_state_epoch`; neue Liste `POST_TYPES` (= `Relations::POST_TYPES`) mit `post_types()` und Schlüssel `post_types` in `to_array()` |
| `src/Kernel/Uninstaller.php` | bei bestätigter Löschung: `delete_own_posts()` (alle Beiträge der eigenen Post-Types per exaktem Typ, `wp_delete_post(…, true)` samt Revisionen/Meta/Terms, vor dem Tabellen-Drop, `DeletionGuard` dafür ausgehängt); eigene Journal-Kopfdatei löschen |
| `src/Settings/Settings.php` | E-89: Einschalten von `general.delete_data_on_uninstall` nur mit `_confirm_site_name` = Site-Name (getrimmt, exakt; leerer Site-Name → Host von `home`), sonst `ccap_validation_failed` mit Feld; Ausschalten und andere Felder ohne Bestätigung; Konstante `Settings::CONFIRM_SITE_FIELD`, Helfer `site_name_confirmed()` |
| `src/Ops/HealthChecks.php` | Site-Health-Test `course_captain_identity` (kritisch bei `held` mit geänderten Teilen, Hinweis bei `copy`, sonst gut) |
| `src/Ops/AuditView.php` | Registry: `site_identity_confirmed` |
| `src/Kernel/HoldMode.php` | nur Docblock: Identitäts-Haltemodus liegt in `SiteIdentity` (weicher, Plugin läuft weiter); Konstante `REASON_SITE_IDENTITY` bleibt reserviert |
| `docs/API-CHANGELOG.md` | Eintrag Filter-Startwert `course_captain_notification_hold`, CLI `identity` |

Keine neue Tabelle, kein neuer Hook (Snapshot CT-05 unverändert), kein neuer Cron-Hook, neue Option `course_captain_state_epoch` im Manifest, neue Audit-Aktion registriert.

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler (1 bekannte Warnung `ImportTest.php:318`) · PHPStan L8 0 · Unit **104 / 2 801** · Integration **262** (Assertions je nach Zufallsreihenfolge 5 650…8 781) · Multisite **274 / 9 304** · Free-Guard 0 · Textdomain-Lint 0. Gruppe `R1-23` zweimal hintereinander grün (13 Tests / 256 Assertions). `.pot` bewusst nicht aktualisiert (Vorgabe).

## 3. Tests

| ID | Test | Umfang |
|---|---|---|
| REC-001 (Teil) | `RecoveryTest::test_identity_is_recorded_silently_and_stays_stable`, `test_restore_without_journal_is_not_detected` | Neuinstallation: Identität still aufgezeichnet, kein Fehlalarm, stabile Site wird bei wiederholter Prüfung nie gehalten und nichts neu geschrieben; Health gut, kein Hinweis, CLI Exit 0. Ohne Journal ist ein Restore am selben Ort nicht erkennbar (Restrisiko R0-17 §5.5), Identitätswechsel schon |
| REC-002 / NTF-017 | `test_REC_002_new_domain_holds_mails_until_moved_is_confirmed` | neue Domain → Mail `held`, 0 Mails; Status `held`, `changed = [home]`; Health kritisch mit „site address“; Hinweis mit drei Knöpfen nur für Admins; CLI Exit 1; Rückkehr zur alten Adresse hebt nicht auf; Lernender `ccap_forbidden`, falsche Wahl `ccap_invalid_param`; „moved“ → frei, Audit mit Wahl/Vorzustand/Teilen; gehaltene Mail bleibt `held` (kein Nachholen, E-88), Verwerfen als Standard; neue Mails gehen raus |
| REC-003 / REC-021 (Teil) | `test_REC_003_copy_next_to_original_keeps_outbound_blocked` | eigener privater Ordner + `staging` → `changed = [private, environment]`; CLI `confirm copy` → `copy`, stündlicher Lauf + Versandlauf: 3 Mails `held`, 0 versendet; Health „recommended“; Hinweis ohne Kopie-Knopf; CLI-Status Exit 0; später „moved“ per CLI, Audit als `system` |
| REC-016 / REC-001 | `test_REC_016_restore_of_older_database_never_resends` | Backup nach Grant vor Versand (Outbox-Event, Zustellungen, Notification-Tabelle, Epoche), Versand (Journal-Epoche 1), Restore am selben Ort → Grund `epoch`; Replay der Zustellungen erzeugt die Mail erneut als `held`, **keine zweite Mail**, gleiche `event_uuid`/`dedupe_key`; „same_place“ → Epoche angeglichen, gehaltene Mail nicht automatisch gesendet, Verwerfen |
| PRIV-010 (nur Erkennung) | `test_PRIV_010_erasure_then_restore_is_detected` | Löschung erhöht die Epoche; DB-Stand davor → Haltemodus `epoch`; weitere Erhöhung im Haltemodus verdeckt die Erkennung nicht |
| REC-023 / GM-164 | `UpgradeProbeTest::test_REC_023_upgrade_from_previous_release_keeps_data` | Vorversion (ohne `active_user`+Unique, ohne FKs, ohne Index `user_due`, ohne Schrittzeilen 0002/0003, `db_version 0.9.0`) mit Lerndaten; Update-Hook plant nur, Cron-Lauf migriert; Spalte/Index/alle FKs da, keine FK-Probleme, kein Drift, Zeilenzahlen je Tabelle und Inhalts-Hash (Grant, Grant-Events, Fortschritt, Outbox) unverändert; zweiter Lauf ohne `dbDelta`-Ausgabe, kein Schritt doppelt |
| REC-006 / MIG-021 (Teil) | `test_upgrade_with_pending_outbox_and_orphaned_locks` | offene Outbox-Zustellungen, Mail in `sending` eines abgestürzten Prozesses, verwaiste Migrations-Lease; W1: Dispatcher stellt nicht zu; Migration übernimmt die Lease (alter Prozess ausgefenced); danach jede Zustellung genau einmal, Zugangsmail einmal, halb gesendete Mail `unknown` (kein Auto-Neuversand), Replay sendet nichts doppelt |
| REC-018 / GM-158 | `test_REC_018_deactivate_uninstall_reinstall_keeps_everything` | Deaktivieren: Cron leer, Daten gleich; Uninstall ohne Bestätigung: Zeilen, Inhalts-Hash, alle Optionen, Kurs, Caps bleiben; Reaktivierung: Cron neu geplant, kein Migrationsschritt doppelt |
| REC-019 / REC-018 | `UninstallProbeTest::test_REC_019_without_confirmation_no_user_data_is_removed` | Tabellen, Kurs, Post-Meta, User-Option, Netzwerk-User-Meta, Identität, Journal, Caps bleiben; nur Cron weg; fremde Daten unberührt |
| REC-019 / LIC-063 | `test_REC_019_confirmed_deletion_removes_exactly_the_manifest` | Bestätigung über `Settings::save` mit Site-Name; danach: alle Manifest-Tabellen weg, nur fremde Optionen übrig, keine Beiträge der eigenen Post-Types, Revisionen weg, eigener Meta-Schlüssel auch an fremdem Beitrag weg, User-Option und Netzwerk-User-Meta weg, Rollen/Caps/Cron weg, Journal-Kopf weg; fremde Optionen, fremde Post-/User-Meta, Tabelle ohne Eigentumsmarke (KOMP-131), fremde Caps, Konten bleiben |
| SET-035 / E-89 | `test_E_89_deletion_setting_needs_site_name` | ohne/leer/falsche Groß-/Kleinschreibung/anderer Name → `ccap_validation_failed` (Feld), Wert bleibt `false`; mit Site-Name (Leerzeichen getrimmt) gespeichert, Audit `settings_saved` nennt das Feld; andere Felder und Ausschalten ohne Bestätigung |
| REC-019 | `test_reinstall_after_confirmed_deletion_starts_clean` | Neuinstallation nach Löschung: volles Schema, keine Grants, Caps, Identität `unrecorded`, kein Haltemodus |
| REC-020 | `ManifestCoverageTest` (3), bestehend `UninstallManifestTest` | Tabellen- und Post-Type-Abdeckung zusätzlich zur Schlüsselprüfung aus R1-01 |
| REC-024 | `RecoveryMultisiteTest::test_REC_024_restore_of_one_site_leaves_the_other_untouched` | je Site eigene Journal-Kopfdatei; Restore von Site 2 hält nur Site 2; Bestätigung und Audit nur auf Site 2; Adresswechsel der Hauptsite hält Site 2 nicht |
| REC-019 / MS-015 | `test_REC_019_multisite_deletes_only_on_agreeing_sites` | nur zustimmende Site verliert Tabellen und User-Option; Netzwerk-User-Meta bleibt, solange eine Site Daten behält, und geht, wenn alle zustimmen |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Haltemodus und interne Outbox (R0-16 §2.3 „Dispatch pausiert“) | Die interne Outbox hat nur interne Abonnenten (E-182: Cache, Notification). Statt den Dispatch zu pausieren, laufen die Zustellungen weiter und der Notification-Abonnent legt Mails an, die beim Versand `held` werden. Pausieren würde die Mails nach der Bestätigung **automatisch** nachsenden und damit E-88/REC-016 verletzen. Pro-Connectoren müssen `OutboundMode::is_held()` prüfen (R3) |
| A-2 | Restore am selben Ort | Ohne Journal-Ordner unerkennbar (Identität gleich). Erkennung über den Epochenzähler aus R0-17 §5.5; Erhöhung zusätzlich zu Löschungen auch nach jedem Versandlauf mit gesendeten Mails, sonst wäre REC-016 nicht erkennbar. Kopfdatei pro Site; ohne `COURSE_CAPTAIN_PRIVACY_JOURNAL_DIR` nur Identitätserkennung (Restrisiko, Betreiberdoku R1-24). **Klärung** |
| A-3 | `home` statt `home_url()` | rohe Option, weil Sprach-Plugins `home_url()` je Anfrage filtern (Fehlalarme) |
| A-4 | Fehlende Identität | wird still aufgezeichnet (Neuinstallation, Upgrade von Version ohne Identität); ein Restore eines solchen Altstands ist damit nicht erkennbar |
| A-5 | Erkennungszeitpunkt | lazy: stündlicher Lauf (erster Schritt), vor jedem Versandversuch, Admin-Hinweis, Health, CLI; nicht bei Aktivierung (Aktivierung legt keine Option an, LIC-008-Fußabdruck bleibt) |
| A-6 | Gehaltene Mails nach Bestätigung | bleiben `held`; Freigeben/Verwerfen über die R1-21-Dienste, Standard Verwerfen bzw. nach 30 d `held_expired` (E-88). Die „Nachhol-Liste“ als Oberfläche fehlt (R1-38) |
| A-7 | Rechte | Bestätigung durch `manage_options` der Site (nicht `ccap_manage_settings`), weil sie die Site-Identität betrifft; CLI immer erlaubt |
| A-8 | E-89-Bestätigung | Feld `_confirm_site_name` im Speicheraufruf; Audit über das bestehende `settings_saved` (Feld, Nutzer, Zeit). Backup-Hinweis-Dialog und CLI `settings set --confirm=<site>` gibt es noch nicht (UI R1-32, Settings-CLI R1-31/R0-19) |
| A-9 | Uninstall von Beiträgen | eigene Post-Types samt Revisionen/Meta/Terms werden gelöscht; Anhänge bleiben in der Mediathek (WordPress hängt sie um). R0-16 §2.5 Nr. 5 Standard „löschen“ → **Klärung** |
| A-10 | „Vorversion“ (REC-023) | Schema 1.0.0 ist die erste Version; die Vorversion ist synthetisch (fehlende Schritte 0002/0003, fehlender Index, alte Versionsnummer). Echte Artefakt-Probe gegen das letzte Tag (ZIP) mit R1-39 |

### Nicht gebaut (bewusst, offen)

| Nr. | Punkt | Ziel |
|---|---|---|
| O-1 | `backup-check` (REC-001 voll, REC-004/005/012/013/015 Dateiversatz, REC-022 Secrets, REC-026 Rotation), Read-only-Fenster `maintenance`, `preflight` | R1-24/R1-38 bzw. R1-28 (Secrets) |
| O-2 | Lösch-Tombstones `ccap_privacy_tombstone` + Journal `tombstones.jsonl` und automatische Wiederanwendung (PRIV-010 Rest, PRIV-011…013, E-92/E-151); hier nur Epoche + Erkennung | eigene Aufgabe (neue Tabelle, HMAC-Schlüssel aus `COURSE_CAPTAIN_SECRET_KEY`) |
| O-3 | Reconciliation-Job nach Restore (R0-16 §2.3 Nr. 8, REC-010 Prüfungen nach Fristende, REC-014 Import ohne Heartbeat) als eigener Lauf; vorhandene Mechanismen (Lease-Übernahme Outbox/Notification, `expire_due`, Import-Heartbeat) greifen über den stündlichen Lauf | R1-38 |
| O-4 | `identity --new` (neue Installations-UUID; die UUID wird in R1 nicht genutzt), Admin-Leisten-Anzeige des Modus | R1-28/R1-38 |
| O-5 | Uninstall von Ordnern (`protected-<geheim>`, `packages-<geheim>`, `media`) und geschützten Anhängen (Wahl im Bestätigungsdialog) | R1-32 (Dialog) |
| O-6 | REC-009/REC-025 (Rollback R-C, Downgrade R-B mit Contract), REC-017 (Teil-Restore über Paket) | sobald Schema 1.1 existiert (R1-40 O-1/O-4) bzw. R1-41 |

### Klärungsfragen

1. Reicht die Epochenerhöhung nach jedem Versandlauf (A-2), oder soll sie nur bei Löschungen (R0-17 §5.5 wörtlich) erfolgen und REC-016 auf Identitätswechsel beschränkt bleiben?
2. Sollen Anhänge eigener Kurse beim bestätigten Uninstall standardmäßig gelöscht werden (R0-16 §2.5 Nr. 5) – ohne Dialog-Wahl derzeit „behalten“ (A-9)?
3. Wer darf die Identität bestätigen: Site-Admin (`manage_options`, umgesetzt) oder zusätzlich `ccap_manage_settings`?
4. Soll `identity status` im Zustand `copy` Exit 0 (umgesetzt) oder einen eigenen Code liefern (Monitoring von Staging)?

Review durch zweite Person vor Merge (AG-05).
