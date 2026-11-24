# SET-CC Slice „aufbewahrung“ – Nachweis

**Stand:** 24.11.2026 · **Branch:** `r4-b` (Basis 69cac5d)
**Punkt:** SET-CC-02 (TODO-WETTBEWERBSANALYSE, Abschnitt „Einstellungen für den Verkauf“), Inhaber-Entscheidung **D1** vom 24.11.2026.
**Grundlagen:** R0-27 §1.3 (`privacy.retention`, Risiko „R beim Verkleinern“, PRIV-008) und §1.4 (21 Unterschlüssel), R0-31 §2.1 (RT-01…RT-45), R0-17 (DSFA), E-44, E-51, E-143, E-154, E-215, E-243…E-245, R0-05 GM-131, R0-10 §3.6.
Der TODO-Haken bleibt beim Koordinator.

## Was gebaut ist

### Register `privacy.retention` (Registerversion 4)

- Neues Objektfeld `privacy.retention` in `src/Settings/SettingsSchema.php`; die Unterschlüssel, Einheiten, Bereiche und die zugehörigen Schritte stehen an einer Stelle: `src/Privacy/RetentionPolicy.php` (`KEYS`).
- **Alle 21 Unterschlüssel aus R0-27 §1.4** mit Bereich aus der Spezifikation, plus `import_package_days` (D1). Vorgabe = bisher fest eingebaute Frist:

| Unterschlüssel | Vorgabe | Bereich | bisher (Konstante) | wirkt in |
|---|---|---|---|---|
| `audit_log_years` | 4 | 1–10 | `RetentionService::AUDIT_YEARS` | RT-23 (≤ 5 000/Lauf) |
| `grant_history_deleted_accounts_years` | 10 | 1–30 | – (neu, R0-31 RT-13) | RT-13, nur pseudonymisierte Zeilen (`user_id` NULL, `subject_ref` gesetzt) |
| `import_log_days` | 180 | 7–3650 | `IMPORT_LOG_DAYS` | RT-25 |
| `import_unchanged_days` | 14 | 1–365 | `IMPORT_UNCHANGED_DAYS` | RT-24 |
| `import_keep_packages` | 2 | 1–20 | `KEEP_PACKAGES` | RT-26 (`package_copies`) |
| `import_package_days` | 30 | 1–365 | – (neu, D1) | RT-26 (`package_work`) |
| `inbox_days` | 90 | 7–3650 | `OutboxDispatcher::DELIVERED_DAYS` | RT-27 (Inbox), RT-28 (Outbox zugestellt, E-215) |
| `outbox_event_days` | 30 | 7–365 | `OutboxDispatcher::OPEN_DAYS` | RT-29 (offene Zustellungen → `dead`) |
| `notification_log_days` | 90 | 7–3650 | `NOTIFICATION_LOG_DAYS` | RT-30 |
| `error_log_days` | 30 | 1–365 | `advanced.error_log_days` | RT-33 `Log::prune` |
| `debug_file_days` | 7 | 1–30 | `advanced.debug_file_days` | RT-34 `Log::prune` |
| `exam_attempts_years` | 10 | 1–30 | – (neu, RT-06) | RT-06; Versuche mit Zertifikat und laufende Versuche bleiben |
| `raw_answers_years_after_course_end` | 2 | 0–30 | `ANSWERS_DAYS` = 730 | RT-04, RT-45 |
| `certificates_years` | 10 | 1–50 | `CERTIFICATES_DAYS` = 3653 | RT-09 |
| `inactive_learning_data_years` | `null` (aus) | 1–20 | – (E-154) | RT-02, nur eingeschaltet |
| `invite_email_days_after_expiry` | 30 | 1–365 | `INVITE_EMAIL_DAYS` | RT-15 |
| `held_mail_days` | 30 | 1–90 | `NotificationService::HELD_DAYS` | RT-31 (`expire_held`) |
| `learning_progress` | `until_account_deletion` (fest) | – | – | – |
| `ended_grants_years` | 10 | 1–30 | – (neu, RT-12) | RT-12 |
| `removed_seats_days` | 365 | 30–3650 | `REMOVED_SEATS_DAYS` | RT-17 |
| `closed_groups_years` | 3 | 1–30 | – (neu, RT-18) | RT-18 |
| `lifted_locks_days` | 30 | 1–365 | `LIFTED_LOCKS_DAYS` | RT-22 |

- Jahreswerte rechnen in Kalenderjahren (`strtotime('-N years')`, wie bisher schon das Audit). 730 bzw. 3653 Tage entsprechen 2 bzw. 10 Kalenderjahren auf ±1 Tag (Schaltjahr); die bestehenden Tests mit Abstand 800 bzw. 3654 Tagen laufen unverändert.
- **Prüfung:** gleiche Registerprüfung wie jedes Feld (Ganzzahl im Bereich, `inactive_learning_data_years` darf `null` sein, `learning_progress` nur der feste Wert); Werte außerhalb ergeben einen Feldfehler (`ccap_validation_failed`, Teilfeld wird markiert, D13).
- **Filter** `course_captain_retention_days( $value, $key )` gewinnt über die Einstellung (S2). Wert in der Einheit des Unterschlüssels; ein Wert außerhalb des Bereichs wird ignoriert. Die Oberfläche zeigt einen Hinweis über dem Abschnitt und am Feld („Der Filter … übersteuert diesen Wert; wirksam ist …“).
- `RetentionService` liest jede Frist über `RetentionPolicy::effective()`; die alten Konstanten bleiben als dokumentierte Vorgaben stehen (Tests und Fremdcode). Ebenso lesen `OutboxDispatcher::purge()`, `NotificationService::hourly()` (neu `expire_held()`), `Log::prune()` und der Datenschutz-Vorschlagstext (`PrivacyService::policy_html()`, GM-176) die Einstellung.
- **Neue Schritte** mit Grenze 1 000 Objekte je Lauf (R0-27 §1.4 „≤ 1 000“), Kinder vor Eltern gelöscht (FK-sicher): RT-06 Prüfungsversuche, RT-12 beendete Zugänge, RT-13 Zugänge gelöschter Konten, RT-18 beendete Gruppen (nur ohne lebenden Zugang über ihre Plätze), RT-02 Lernstand inaktiver Personen (nur eingeschaltet), RT-27 verarbeitete Inbox-Ereignisse, RT-26 `package_work`.

### Vorschau der Löschmengen

- `RetentionService::preview( ?array $values )`: je Unterschlüssel `value`, `unit`, `kind` (`rows`/`files`/`people`), `count`, `error` – **nur Zählabfragen**, nichts wird gelöscht (Test: Zeilen vorher/nachher gleich, Logdatei bleibt). Mit `$values` zählt sie für Kandidatenwerte vor dem Speichern.
- Schritte, deren Job an anderer Stelle des stündlichen Laufs läuft (Outbox, zurückgehaltene Mails, Logdateien), werden mit derselben Bedingung gezählt (`OutboxDispatcher::due()`, `NotificationService::expire_held(…, true)`, `Log::prune(…, true)`, `CertificateService::expire_retention(…, true)`, `AssignmentService::expire_retention(…, true)`).
- Tab „Datenschutz & Sicherheit“, Abschnitt **„Aufbewahrungsfristen“**: Einleitung (Vorschläge, keine Rechtsberatung), ein Feld je Frist, darunter die Tabelle „Was der nächste Lauf mit den gespeicherten Fristen löschen würde“ (`th scope`, Caption).
- **RC-23** (neu in `RiskRules::CODES`): Verkürzen einer Frist oder Einschalten von `inactive_learning_data_years` braucht die Bestätigung; der Dialog nennt je verkürzter Frist die Menge, die der nächste Lauf mit den neuen Werten löscht (PRIV-008). Gleiches gilt für Import (`confirmed`) und WP-CLI (`--confirm`). Verlängern braucht keine Bestätigung.

### Markierung „Rechtsprüfung“

Jedes Fristfeld trägt den Status „Legal review needed“ / „Rechtsprüfung erforderlich“ nach dem bestehenden Muster der Feldstatus-Zeile (`ccap-field-status`, wie der Härtungsstatus); der Status ist jetzt zusätzlich per `aria-describedby` Teil der Feldbeschreibung (gilt auch für die Härtungsfelder). Der feste Wert `learning_progress` erscheint als Textzeile mit derselben Markierung. Handbuch und WP-CLI-Vorschau tragen denselben Hinweis.

### Umzug der Logfristen (Migration)

- `advanced.error_log_days` / `advanced.debug_file_days` → `privacy.retention.error_log_days` / `debug_file_days` (`SettingsSchema::MOVED`, `VERSION` 3 → 4).
- `Settings::migrate()` übernimmt vor dem Tab-Durchlauf den gespeicherten, gültigen Wert aus `advanced` (nur wenn dessen `_schema` < 4) in das Objekt des Datenschutz-Tabs – mit oder ohne vorhandene Option des Datenschutz-Tabs –, danach verwirft der normale Durchlauf die alten Schlüssel. Revisionen bleiben, idempotent (Tests `test_migration_moves_log_periods`, angepasst `SettingsDockingPointsTest::test_SET_CC_P6_upgrade_keeps_behaviour`: Wert 60 kommt an und wirkt).
- `Log.php` liest nur noch die neuen Schlüssel.
- **Import älterer Dateien** (`settings_version` < 4): `SettingsSchema::upgrade_file()` verschiebt die Werte vor der Prüfung in `privacy.retention` (auf dem aktuellen Objekt, andere Fristen unverändert); sie erscheinen nicht als „unbekannt“.

### Paketkopien unfertiger Importe (D1)

Neuer Unterschlüssel `import_package_days`, Vorgabe **30 Tage** = die kürzere der beiden Quellen:

- **R0-05 GM-131** (Golden Master „Aufbewahrung“): „Paketkopien und Protokoll aufräumen · 4 Paketkopien, Clock +200 d · aktives Paket + jüngstes Rollback-Ziel + laufender Import bleiben; ältere gelöscht; Protokoll `unchanged` 14 d / Änderungen 180 d“. GM-131 nennt für Paketkopien keine Tagesfrist; die einzigen Fristen sind 14/180 Tage für das Protokoll, der Fall läuft über 200 Tage, und der **laufende** Import bleibt dabei erhalten.
- **R0-10 §3.6** (Revisionen und Rollback): „Aufbewahrung Paketkopien: aktives Paket + 2 weitere veröffentlichte; **Importe in Arbeit 30 Tage**; Zwischen- und Download-Ordner 24 h; Protokoll `unchanged` 14 Tage, sonst 180 Tage (E-44)“.
- Kürzer ist R0-10 §3.6 mit 30 Tagen (gegenüber 180 Tagen bzw. > 200 Tagen aus GM-131). Beide Quellen bleiben vereinbar: der Schritt `RT-26_package_work` löscht die Kopie nur bei Importen in Arbeit **ohne laufenden Lauf** (`uploaded`, `validated`, `previewed`, `approved`, `aborted`) ohne Aktivität seit der Frist (spätester Zeitpunkt aus `created_at`, `approved_at`, `heartbeat_at`); `importing`/`activating` werden nie angefasst (GM-131 „laufender Import bleibt“). Der bestehende Schritt `RT-26_package_copies` (aktiv + `import_keep_packages` je Quelle, verworfene/fehlgeschlagene sofort) liest jetzt `import_keep_packages`. Danach braucht ein solcher Import einen neuen Upload (IMP-B1.07).

### Export/Import, WP-CLI, Handbuch

- Export/Import: `privacy.retention` ist ein normales exportierbares Feld (Rundlauf im Test und in `SettingsRegisterAuditTest`); Verkürzen beim Import nur mit Bestätigung (`privacy.retention` bzw. `*`).
- WP-CLI: `get|set|reset privacy.retention.<unterschlüssel>` (andere Unterschlüssel bleiben, `null` schaltet `inactive_learning_data_years` aus), RC-23 verlangt `--confirm`; `wp course-captain settings retention [--format=json]` zeigt die Vorschau (nur lesend).
- Handbuch `docs/EINSTELLUNGEN.md` neu erzeugt (`php8.5 tools/docs/settings-doc.php --write`), neuer Abschnitt „Aufbewahrungsfristen“ mit Tabelle, Filter, Rechtsprüfung und WP-CLI-Zeilen.
- `docs/API-CHANGELOG.md`: Eintrag (Filter, RC-23, CLI, neue Schritte, Registerversion 4); Hook-Snapshot `docs/api/hooks.v1.json` (`CCAP_UPDATE_SNAPSHOTS=1`) und Hook-Tabelle in `docs/API.md` aktualisiert.

## Tests

- Neu `tests/integration/SettingsRetentionTest.php` (Gruppe `SET-CC-02`, 9 Tests): Register/Bereiche/Vorgaben = Konstanten, Feldfehler; Filter gewinnt und ungültiger Filterwert wird ignoriert; Vorschau = Lauf und nur lesend, Kandidatenvorschau; neue Schritte RT-02/06/12/18/26 inkl. Ausnahmen (Zertifikat, laufender Versuch, lebender Zugang, laufender Import); Logdateien, zurückgehaltene Mails, Outbox-Zählung, Datenschutztext; Migration v3 → v4 mit und ohne Datenschutz-Option, idempotent; Export/Import inkl. Datei der Version 3 und RC-23 beim Import; WP-CLI Unterschlüssel, `--confirm`, Vorschau; Datenschutz-Tab (22 Markierungen, Vorschautabelle, A11y-Fragmentprüfung, `aria-describedby` des Status) und RC-23-Ablauf ohne JS.
- Angepasst: `SettingsMailFieldsTest` (Logfrist jetzt im Datenschutz-Tab), `SettingsDockingPointsTest` P6 (Umzug des Werts).
- Bestehende Retention-Tests (`PrivacyOpsTest`, `GmOpsPrivacyFaqTest` GM-131/GM-176, `CertificateTest`, `AssignmentTest`, `NotificationTest`) unverändert grün.

## Was noch fehlt / offen

- **E-245** (Verlängern über den Default nur mit Pflichtbegründung im Audit) ist nicht gebaut; Verlängern ist frei, steht aber mit Vorher/Nachher im Änderungsprotokoll.
- **E-346 S9** („neue Installationen ohne Löschfrist, Profil schlägt vor, Website-Zustand mahnt“) widerspricht D1/Vorgabe „Default = bisherige Frist“; gebaut ist D1. Länderprofil-Vorschläge gehören zu SET-CC-07.
- **RT-05** (Rohantworten der Prüfung 2 Jahre nach Kursende) und der genaue Fristbeginn „Kursende“ (E-244) für RT-04 sind wie bisher nicht umgesetzt: RT-04 rechnet weiter ab `created_at`.
- **RT-13** findet erst Zeilen, wenn der Eraser Zugänge pseudonymisiert statt löscht (heute: Löschung).
- **RT-02** nutzt die letzte Lernaktivität (Kurs-, Lektions- und Fragenfortschritt) statt der letzten Anmeldung, weil Course Captain keine Anmeldezeit speichert.
- `privacy.backup_max_age_days` (Tombstones RT-41) bleibt „nur Information“ (P1-F08).
- Rechtsprüfung aller Werte durch den Betreiber; die Übersetzung „Rechtsprüfung erforderlich“ und die Hilfetexte sollte eine zweite Person gegenlesen (AG-05).
