# SET-CC Slice „cli-export-sperren“: SET-CC-11, -12, -15, -27, -29

Stand 23.11.2026, Branch `r2-c` (Basis main 89fe9ef). Nachweis je Punkt; die TODO wird vom Koordinator abgehakt.

## SET-CC-12 – WP-CLI `wp course-captain settings …`

**Gebaut:** `src/Settings/SettingsCommand.php` (registriert in `Plugin::boot` neben `OpsCommand`).

- `list [--tab=<tab>] [--format=json]`, `get <tab.feld>`: `secret`-Felder nie lesbar (`<set>`/`<empty>`, SR-5).
- `set <tab.feld> <wert>`: Wert nach Feldtyp (Text unverändert, Ja/Nein als `true/false/yes/no/on/off/1/0`, Objekte/Listen als JSON, `null` bei nullable). Prüfung über `Settings::check()` und `Settings::save()` (dieselbe Prüfung wie das Formular, SET-021). Risikofelder (`RiskRules::detect`) nur mit `--confirm`, sonst Exit 1, nichts gespeichert, kein Audit (PSD-007). `general.delete_data_on_uninstall` einschalten nur mit `--confirm=<Website-Name>` (E-89; SET-CC-30 baut das Bestätigungswort einer anderen Sitzung – CLI reicht den Wert unverändert als `_confirm_site_name` an den Dienst weiter).
- `export [--output=<datei>] [--include-personal] [--include=roles]`: stdout oder Safe-Export-Writer `Diagnostics::write_file` (neue Datei, `0600`, außerhalb des WordPress-Verzeichnisses, EX-6).
- `import <datei> [--dry-run] [--tabs=…] [--surfaces=…] [--confirm] [--yes]`: zeigt immer zuerst die Unterschiede; ohne `--yes` (bzw. interaktive Bestätigung) wird nichts geschrieben; Risikofelder und RC-07-Rollen nur mit `--confirm`.
- `reset <tab> --yes` (Rollen über `RoleCaps::reset`), `reset <tab.feld>` = `set` auf den Standard (Risikoregeln gelten).
- **Akteur `system:cli`:** Die Spalte `actor_type` des Audit-Logs kennt nur `user|system|connector`. Deshalb schreiben `Settings`, `EffectiveSettings`, `SettingsTransfer` und `RoleCaps` für Systemakteure zusätzlich `actor: system:cli` in den Audit-Kontext, die CLI außerdem `context: cli`. Jede Änderung wird auditiert (`settings_saved`, `settings_reset`, `settings_imported`, `settings_lock_set/_removed`, `role_caps_changed`, neu `settings_exported`).

**Tests:** `tests/integration/SettingsCliTransferTest.php::test_cli_list_get_set`, `::test_cli_export_import`.

## SET-CC-15 – Export/Import aller Einstellflächen mit Vorschau

**Gebaut:**
- Anbieterliste `src/Settings/TransferProvider.php` (Interface) und `SettingsTransfer::providers()` mit Filter `course_captain_settings_transfer_providers`. Jeder Anbieter besitzt einen eigenen Abschnitt der Datei neben `tabs` (R0-27 §2.5 `mail_templates?`); reservierte Kopfschlüssel und Dubletten werden abgewiesen. Format bleibt `format_version` 1 (ältere Importe ignorieren die Zusatzabschnitte).
- Eingebaut: `LocksTransfer` (`locks`, immer exportiert; ganze Sperrliste, fehlende Sperren werden beim Import entfernt; ohne „wer/seit wann“, EX-5; über `EffectiveSettings::lock/unlock` mit Vorabprüfung und Audit) und `RolesTransfer` (`roles`, nur mit Opt-in EX-1; Export für #24∧`promote_users` oder Admin, Import nur Administratoren; über `RoleCaps::check/apply`, nie Rechte über dem eigenen Satz; RC-07 bei Hochrisiko-Rechten; unbekannte Rechte werden gemeldet und übersprungen; Rückgängig-Stand im Rollen-Undo für 30 Tage).
- `SettingsTransfer::export()` (Tabs + Flächen, Audit `settings_exported` ohne Werte), `import()` mit `surfaces`-Auswahl, Bestätigungen `<fläche>.<eintrag>` und Platzhalter `*` (nur intern/CLI; die Oberfläche verwirft `*` aus dem POST). Je Fläche atomar; `settings_imported` nennt die angewandten Flächen.
- Oberfläche „Einstellungen sichern“: Opt-in-Kästchen je Opt-in-Fläche, Vorschau je Fläche mit Unterschieden (Zusammenfassung „Rolle: fügt … hinzu; entfernt …“ bzw. „gesperrt; N eigene Werte ruhen“), eigenes Auswahlkästchen und Bestätigungskästchen RC-07.
- **Zertifikatsvorlagen:** Die Basisvorlage ist das Registerfeld `certificates.base_template` und wird bereits mit dem Tab `certificates` exportiert/importiert (Feld-Diff in der Vorschau). Es gibt keine weiteren Vorlagen im Kern, daher kein eigener Anbieter.
- **Mailvorlagen:** Nicht gebaut (Editor in anderer Sitzung). Andockpunkt: eine `TransferProvider`-Implementierung mit Schlüssel `mail_templates`, `opt_in() = true` (EX-1), registriert über den Filter; `MailTemplates::save()` verlangt derzeit einen angemeldeten Nutzer (CLI-Import müsste das berücksichtigen).

**Tests:** `::test_transfer_surfaces` (Diff, Admin-only, RC-07, Undo, Anbieterliste inkl. Test-Anbieter `mail_templates`, reservierter Schlüssel), `::test_backup_screen_surfaces` (Vorschau, Auswahl, `*`-Manipulation wirkungslos, A11y). Bestehende `SettingsRegressionTest` unverändert grün.

## SET-CC-11 – Sperren und Übersicht der Übersteuerungen

**Gebaut:** versteckte Seite `src/Admin/SettingLocksScreen.php` (`course-captain-setting-locks`, #19), verlinkt im Kopf der Einstellungsseite.
- Übersicht aller übersteuerbaren Schlüssel: Website-Wert, Ebenen, Anzahl eigener Werte (`count_overrides`), Sperrzustand mit Grund.
- Sperren/Entsperren mit Bestätigungsseite „Betrifft N Kurse und M Prüfungen …“ (ohne JS, `role=alert`), Grund ≤ 200 Zeichen; Dienst `EffectiveSettings::lock/unlock` (bestehende Option `course_captain_setting_locks`, Audit, Vorabprüfung `ccap_setting_lock_conflict`).
- Liste der eigenen Werte je Schlüssel (`EffectiveSettings::list_overrides`, neu): Kurse (Entwurfs-Meta, Link zum Editor) und Prüfungen (Entwurf/veröffentlicht; veröffentlichte sind eingefroren).
- Sammel-Zurücksetzen auf „erben“: Auswahl (GET) → Bestätigung → `EffectiveSettings::reset_overrides` (neu; nutzt `save_course` bzw. `ExamRules::set_overrides`, Rechte M-03/M-06 je Objekt, Teilfehler werden gemeldet), Audit `settings_overrides_reset`.

**Tests:** `::test_locks_screen` (Dialogtext, Nonce, Rechte, Sperre, Liste, Bestätigung, Reset Kurs + Entwurfsprüfung, eingefrorene Prüfung, A11y-DOM). Seite zusätzlich in `A11yI18nTest::test_A11Y_admin_screens_and_blocks`.

## SET-CC-27 – Änderungsprotokoll: Filter „Einstellungen“ mit Vorher/Nachher und Export

**Gebaut:** `AuditView::settings_changes()` (eine Zeile je geändertem Schlüssel aus `settings_saved/_reset`, Sperren, Übersteuerungs-Reset, Rollen, Kurs-Übersteuerungen, Import) mit Filter `key` (Tab oder `tab.feld`); `AuditView::csv()` (aktueller Filter, höchstens 5000 Einträge, formelsicher über `CertificateAdmin::csv_cell`, BOM, Audit `audit_exported`). `AuditScreen`: Link „Einstellungsänderungen mit Vorher/Nachher“, Bereich `settings` zeigt die Vorher/Nachher-Tabelle, CSV-Knopf (admin-post `ccap_audit_export`, Nonce, Recht `ccap_view_audit_log`). **Geheimnisse/Personendaten:** Werte erscheinen nur, wenn das Register das Feld heute als `public`/`internal` führt – auch Alt-Einträge mit Personendaten zeigen nur „geändert“; E-Mail-Adressen werden maskiert.

**Tests:** `::test_audit_settings_view_and_csv` (Vorher/Nachher, persönliche und Alt-Werte nie sichtbar, CSV-Kopf/Zeile, Audit, 403 ohne Recht, A11y).

## SET-CC-29 – Handbuch `docs/EINSTELLUNGEN.md`

**Gebaut:** Generator `tools/docs/settings-doc.php` (ohne WordPress; `--write`, `--check`), erzeugt aus `SettingsSchema::fields()/overridable()`, `SettingsFields` (Bezeichnung, Hilfetext, Tab-Text) und `EffectiveSettings` (Ebenen, Wirksamkeit) je Tab eine Tabelle (Schlüssel, Bezeichnung, Typ/Werte, Standard, Recht, Datenklasse, Export, Übersteuerbar) mit Hilfetexten, dazu Abschnitte Sperren, WP-CLI, Export/Import. Bezeichnungen in der Quellsprache (Englisch), Rahmen deutsch.

**Prüfung:** Unit-Test `tests/unit/SettingsDocTest.php` (führt `--check` in eigenem PHP-Prozess aus; schlägt fehl, sobald Register und Datei auseinanderlaufen). Laut Koordinator kein CI-Schritt (`.github/workflows/ci.yml` unverändert).

**Hinweis für den Merge:** Andere Slices, die Felder oder Hilfetexte ändern (z. B. SET-CC-02/-09/-16), lassen den Test bis zum erneuten `php8.5 tools/docs/settings-doc.php --write` rot – gewollt.

## Offen / nicht gebaut

- Mailvorlagen-Anbieter (siehe oben, gehört zum Editor SET-CC-04).
- Risikocodes je Feld stehen nicht im Handbuch (RiskRules ist Code, keine Registerspalte).
- Übersteuerungen auf Gruppen- und Medienebene werden in der Übersicht weder gezählt noch gelistet („hier nicht aufgeführt“; `count_overrides` kennt nur Kurs und Prüfung).
- Übersetzungen `de_DE`/`de_DE_formal` der neuen Texte (Koordinator regeneriert die .po).
