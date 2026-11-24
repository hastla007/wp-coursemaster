# SET-CC Slice „aufbewahrung-2“ – Nachweis

**Stand:** 24.11.2026 · **Branch:** `r6-d` (Basis 6c2f244)
**Punkt:** SET-CC-02 (Folgearbeit zu `docs/r1/SET-CC-aufbewahrung.md`), Inhaber-Entscheidungen D1 und D12 vom 24.11.2026.
**Grundlagen:** R0-27 §1.4, R0-31 §2.1 (RT-04, RT-05, RT-12…RT-14, RT-45), §2.3 (RR-3, RR-8), §4 (PSD-013, PSD-015), D31-6/D31-7, R0-17 DK-04/§5, R0-09 §2/§7 (Δ-7), E-51, E-244, E-245.
Der TODO-Haken bleibt beim Koordinator.

## 1 · E-245 – Verlängern nur mit Pflichtbegründung im Audit

**Regel** (`RetentionPolicy::extended( $before, $after )`): ein Unterschlüssel gilt als verlängert, wenn der neue Wert über dem Standardwert **und** über dem bisherigen Wert liegt. Schlüssel mit Standard „aus“ (`inactive_learning_data_years`) und der feste Wert `learning_progress` zählen nie (Einschalten ist Verkürzen, RC-23). Eine bereits begründete Verlängerung braucht beim Speichern anderer Fristen keine neue Begründung; Verkürzen braucht keine.

- **Dienst:** `Settings::check()` liefert die Feldregel `retention_reason` (→ `ccap_validation_failed`), wenn eine Verlängerung ohne Begründung im Eingabefeld `_retention_reason` (`Settings::RETENTION_REASON_FIELD`, getrimmt, höchstens 500 Zeichen) kommt. `Settings::save()` schreibt die Begründung in die Audit-Spalte `reason` von `settings_saved` und die Schlüssel nach `context.retention_extended` (beides Teil der Hash-Kette; D12 unberührt, keine Personendaten).
- **Oberfläche** (Tab Datenschutz, Abschnitt Aufbewahrungsfristen, ohne JS): Textfeld „Reason for longer periods“ mit Label, Beschreibung (`aria-describedby`), `maxlength`; bei fehlender Begründung Fehler am ersten verlängerten Feld („Error: <Feld>: this period is longer than its suggested default …“, `params.sub`, Fehlerübersicht verlinkt dorthin) und `aria-invalid` am Textfeld; die Eingabe bleibt über das Formular-Transient erhalten und läuft durch den RC-23-Bestätigungsweg mit.
- **Import:** Option `retention_reason`; ohne Begründung wird `privacy.retention` mit Grund `retention_reason` (und `extended`) übersprungen, die übrigen Felder/Tabs werden importiert. Backup-Seite: die Vorschau zeigt am Änderungseintrag ein Begründungsfeld (Option `list_unreasoned`); Anwenden ohne Begründung ergibt `ccap_reason_required`, die Vorschau bleibt erhalten (Feld mit `aria-invalid`).
- **WP-CLI:** `settings set … --reason=<text>` (ohne: Exit 1, Hinweis `add --reason`, nichts geschrieben), `settings import … --reason=<text>` (ohne: Zeile `retention: skipped (retention_reason <schlüssel>; add --reason)`).
- Handbuch `docs/EINSTELLUNGEN.md` (Generator ergänzt), `docs/API-CHANGELOG.md`.

## 2 · RT-05 – Rohantworten von Prüfungen

R0-27 §1.4 hat **keinen eigenen Unterschlüssel**; R0-31 RT-05 sagt „Setting: wie RT-04“, Frist 2 J., Auslöser Kursende (E-244), Löschart „löschen; Kerndaten RT-06 bleiben“, Ort `ccap_exam_session_question` (R0-17 DK-04: „Rohantworten, Markierung, Freitext“). Umsetzung genau so:

- Neuer Schritt `RT-05_exam_answers` unter `raw_answers_years_after_course_end` (zählt in Vorschau und RC-23-Dialog mit RT-04 und RT-45 zusammen).
- Bei abgeschlossenen Versuchen (`status <> in_progress`), deren Kursende vor der Frist liegt, werden `given_answer` (Antwort und Freitext), `self_assessed_known`, `revealed_at` und `flagged` geleert. Zeilen, `answer_order`, `is_correct`, `neutralized`, `answered_at` und der Versuch selbst (Punkte, bestanden, Daten; RT-06) bleiben. ≤ 1 000 Versuche je Lauf, idempotent, `retention_applied` wie alle Schritte.
- Nachbewertung (`ExamSessions::regrade()`) eines Versuchs ohne Rohantworten wird mit `409 ccap_exam_conflict` (`reason: raw_answers_deleted`) abgelehnt – sonst würde die Neuberechnung leere Antworten werten.
- Hilfetext und Bezeichnung des Feldes nennen jetzt Prüfungsantworten und den Fristbeginn.

## 3 · E-244 – Kursende als Fristbeginn

`Privacy\CourseEnd::ended_before( $db, $user_col, $course_col, $cutoff )` liefert die SQL-Bedingung „Kurs dieser Person endete vor dem Stichtag“: kein Zugang `active`/`suspended` ohne Ende oder mit Ende ≥ Stichtag, kein beendeter Zugang (`expired`/`revoked`/`failed`) mit Ende ≥ Stichtag (Ende = früherer Wert aus `valid_until` und letzter Änderung, also Ablauf- bzw. Widerrufszeitpunkt) und keine Aktivität im Kurs ≥ Stichtag (Kursfortschritt `last_activity_at`, Lernantworten, Prüfungsversuche inkl. laufender, Aufgaben-Abgaben).

- **RT-04** (Lernantworten) rechnet für Antworten mit Kurs ab dem Kursende statt ab `created_at`; Antworten ohne Kurs (`course_id` NULL, z. B. kursfreie Übung) haben kein Kursende und zählen wie bisher ab `created_at`. RT-04 läuft jetzt in Stapeln zu ≤ 5 000 Antworten je Lauf (R0-31 §2.1 „stündlich ≤ 5 000“; vorher unbegrenzt).
- **RT-05** (siehe 2) und **RT-45** (Aufgaben-Abgaben; `AssignmentService::expire_retention()` nutzt jetzt dieselbe Bedingung statt eigener Logik; offene Bewertungen bleiben) ebenso.
- **PSD-013** im Test: (a) aktiver Zugang ohne Ende → keine Frist; (b) Zugang abgelaufen, letzte Aktivität davor → Frist ab Zugangsende (zusätzlich: Ablauf vor 1 Jahr hält 4 Jahre alte Antworten); (c) Abschluss bei weiter aktivem Zugang → keine Frist; (d) neuer Zugang nach Ablauf → Frist ruht. Frist 0 löscht, sobald der Kurs beendet ist.
- **Vorschautabelle** im Tab: neue Spalte „Period starts“ / „Fristbeginn“ mit dem Auslöser je Unterschlüssel (`RetentionPreview::start_text()`, Quelle R0-31 §2.1 Spalte „Auslöser“ bzw. die tatsächliche Abfrage des Schritts).

| Unterschlüssel | Fristbeginn (Tabelle) |
|---|---|
| `audit_log_years` | Eintrag geschrieben |
| `grant_history_deleted_accounts_years` | Kontolöschung (Kauf-Zugänge bis dahin pseudonymisiert) |
| `import_log_days`, `import_unchanged_days` | Protokolleintrag geschrieben |
| `import_keep_packages` | kein Datum (aktive + neueste Kopien je Quelle bleiben) |
| `import_package_days` | letzte Aktivität des unfertigen Imports |
| `inbox_days` | Ereignis empfangen bzw. erzeugt |
| `outbox_event_days` | Ereignis erzeugt (offene Zustellungen) |
| `notification_log_days` | Nachricht erzeugt |
| `error_log_days`, `debug_file_days` | Datum der Logdatei |
| `exam_attempts_years` | Abgabe bzw. Auswertung |
| `raw_answers_years_after_course_end` | Kursende (E-244) |
| `certificates_years` | Ausstellung der neuesten Revision |
| `inactive_learning_data_years` | letzte Lernaktivität, nur ohne aktiven Zugang |
| `invite_email_days_after_expiry` | Ablauf der Einladung |
| `held_mail_days` | zurückgehaltene Nachricht erzeugt |
| `learning_progress` | Kontolöschung oder Löschantrag |
| `ended_grants_years` | Ende des Zugangs |
| `removed_seats_days` | Entfernung des Platzes |
| `closed_groups_years` | Schließung bzw. Ende der Gültigkeit |
| `lifted_locks_days` | Aufhebung der Sperre |

## 4 · Eraser – Zugangshistorie gelöschter Konten pseudonymisieren (RT-13)

Spezifikation: R0-31 RT-13 „Grant-Historie gelöschter Konten, Kaufquellen · pseudonymisieren (`subject_ref`), nach Frist löschen“, RT-14 „übrige Quellen (manuell, Gruppe, Workflow) · sofort löschen“; R0-09 §7 „Kaufquellen (`woo_order`, `voucher`) pseudonymisiert (`user_id = NULL`, `subject_ref = HMAC`) … übrige Quellen samt Events löschen“, „HMAC-SHA-256 mit Site-Geheimnis statt bloßem sha256(user_id)“; R0-09 §2 Kontolöschung „alle Grants → `revoked` (`account_deleted`), Event + Outbox“.

- `PrivacyService::PURCHASE_SOURCES = ['woo_order', 'voucher']` (wörtlich R0-09 §7).
- `erase_user()` (Eraser und `delete_user`): zuerst enden laufende Kauf-Zugänge über `GrantService::transition()` als `revoked`/`account_deleted` (Grant-Event, Outbox `grant.revoked`, Cache); dann in der Löschtransaktion: Kauf-Zugänge bekommen `user_id = NULL`, `subject_ref = PrivacyService::grant_subject_ref()` (HMAC-SHA-256 über die ID mit `ProtectedStorage::secret()`, 64 Hex), `updated_at` = Löschzeitpunkt (Fristbeginn RT-13); `ccap_grant_event.actor_id` und `ccap_grant.granted_by` der Person werden überall geleert; danach werden alle übrigen Zugänge der Person samt Events gelöscht (RT-14, wie bisher).
- RT-13 (`user_id IS NULL AND subject_ref IS NOT NULL AND updated_at < Stichtag`) findet diese Zeilen jetzt und löscht sie samt Events nach `grant_history_deleted_accounts_years`; RT-12 bleibt auf Zugänge bestehender Konten beschränkt.
- **R0-09-Prüfung der Historienregeln:** `ccap_grant_event` ist append-only (§3, Audit) – Ereignisse werden nicht umgeschrieben, nur das Personenfeld `actor_id` geleert (von §7 als Personenbezug geführt); der Widerruf ist ein neues Ereignis. Beendete Zugänge (`expired`, `failed`) behalten ihren Zustand, weil der Automat nach `expired` keinen Übergang kennt (§2, `GrantTransitions`). **Abweichung zur Wortlaut von Δ-7** („`user_id NULL` zusätzlich erlaubt für `revoked` + `account_deleted`“): pseudonymisierte, bereits abgelaufene Kauf-Zugänge stehen mit `user_id NULL` und Status `expired` in der Tabelle. Eine Schemaprüfung (CHECK) gibt es nicht; eine Umstellung auf `revoked` hätte die Historie verfälscht. Bitte bei Bedarf als Präzisierung von Δ-7 aufnehmen.
- Der Quellschlüssel bleibt eindeutig (`grant_source`); ein erneut zugestelltes Kaufereignis derselben Bestellung trifft auf den pseudonymisierten Zugang und endet mit `ccap_idempotency_conflict` (kein stilles Neuverknüpfen).
- **D12:** Weder `subject_ref` noch die Nutzer-ID gelangen in das Audit-Log; die Löschung wird wie bisher nur mit Zeilenzahl auditiert, die Kette prüft vor und nach der Löschung (Test).

## 5 · Widerspruch für den Inhaber: E-346 S9 ↔ D1

E-346 S9 (23.11.2026): „Aufbewahrung: neue Installationen technische Vorgabe ‚nie löschen‘, Länderprofil schlägt Fristen vor, Website-Zustand mahnt; bestehende Installationen unverändert (ersetzt für Neuinstallationen die bisherigen Standardwerte von E-44/E-51/…)“.
D1 (24.11.2026): „SET-CC-02 retention: build it (all 21 keys of R0-27 §1.4 …)“ mit den Vorgaben aus R0-27 §1.4 – gebaut ist, dass **jede** Installation (auch eine neue) mit den bisherigen Fristen löscht. Zusätzlich stünde S9 im Widerspruch zu R0-31 RR-1 („kein stilles unbegrenzt“).
**Gebaut ist D1.** `docs/r0/ENTSCHEIDUNGEN.md` ist nicht geändert. Bitte entscheiden, ob S9 für Neuinstallationen durch D1 ersetzt ist (dann E-346 S9 als überholt markieren) oder ob neue Installationen ohne Fristen starten sollen (dann Vorgabe `null` je Unterschlüssel, Site-Health-Mahnung und Länderprofil-Vorschlag in SET-CC-07 nachziehen).

## Tests

- Neu `tests/integration/SettingsRetention2Test.php` (Gruppen `SET-CC-02`, `aufbewahrung-2`, 5 Tests):
  - `test_E245_reason_for_longer_periods`: Dienst (ohne Begründung abgelehnt, > 500 Zeichen abgelehnt, mit Begründung gespeichert, `reason` + `retention_extended` im Audit, Kette ok), Regel `extended()` (unverändert/verkürzt/≤ Standard/„aus“/Anzahl), andere Frist ohne neue Begründung; Tab ohne JS (invalid → Textfeld `aria-invalid`, Label, Fehler am Feld, A11y-Fragmentprüfung; mit Begründung gespeichert); WP-CLI `--reason` (Exit 1 ohne, Audit mit, Verkürzen ohne Begründung).
  - `test_E245_reason_on_import`: Dienst (übersprungen mit `extended`, anderer Tab importiert), WP-CLI-Import ohne/mit `--reason`, Backup-Seite (Vorschau mit Feld, A11y, `ccap_reason_required` behält die Vorschau, mit Begründung importiert, Audit).
  - `test_E244_course_end_for_learning_answers`: PSD-013 (a)–(d) plus Ablauf vor 1 Jahr, Aktivität ohne Zugang, Antwort ohne Kurs; Vorschau = Lauf; `ccap_attempt_answer` mitgelöscht; zweiter Lauf ohne Wirkung; Frist 0; Vorschautabelle mit Spalte „Period starts“ (22 Zeilen, keine leere Zelle).
  - `test_RT05_raw_exam_answers`: Rohantworten geleert, Zeilen/Einzelergebnis/Kerndaten bleiben, aktiver Zugang behält sie, idempotent, `retention_applied`, Nachbewertung abgelehnt.
  - `test_eraser_pseudonymises_purchase_grants_for_RT13`: `woo_order` aktiv → `revoked`/`account_deleted` mit Event, `voucher` abgelaufen bleibt `expired`, beide pseudonymisiert (HMAC, kein bloßer Hash), manueller Zugang samt Events gelöscht, `actor_id`/`granted_by` geleert, fremder Zugang bleibt, Outbox `grant.revoked`, kein `subject_ref`/keine ID im Audit, Kette ok; RT-13 erst nach 10 Jahren (Zeitreise), dann samt Events gelöscht, RT-12 unberührt.
- Angepasst `SettingsRetentionTest` (Verlängerungen in Fixture, Tab, CLI und Import jetzt mit Begründung; CLI prüft zusätzlich die Ablehnung ohne `--reason`) und `SettingsRegisterAuditTest::test_SET_CC_P1_round_trip_reset_and_cli` (Testwerte über dem Standard brauchen jetzt die Begründung, beim Speichern und beim Import).
- Bestehende Retention-Tests (`PrivacyOpsTest` RT-04 ohne Kurs, `AssignmentTest` RT-45, `GroupAuditTest`, `GmOpsPrivacyFaqTest`) grün.

## Neue Texte

28 neue msgids (Begründungsfeld, Fehlertexte Tab/Backup, Spalte und 20 Fristbeginn-Texte, geänderte Bezeichnung/Hilfe von `raw_answers_years_after_course_end`), in `de_DE` (du) und `de_DE_formal` (Sie) übersetzt, `.mo` neu gebaut; die zwei ersetzten alten msgids wurden in beiden `.po` ersetzt.

## Was noch fehlt / offen

- **RR-4** (`raw_answers_years_after_course_end` ≤ `exam_attempts_years`, sonst Feldfehler; Hinweis bei `certificates_years` < `exam_attempts_years`) ist nicht gebaut (nicht Teil dieses Auftrags).
- Kaufquellen sind wörtlich `woo_order` und `voucher`; ein künftiger Pro-Connector mit anderem Quelltyp (z. B. andere Shops) würde gelöscht statt pseudonymisiert – Liste dann erweitern (ggf. Filter, wäre ein neuer Hook).
- Laufende Zugänge **übriger** Quellen werden beim Löschen wie bisher direkt gelöscht (RT-14), ohne vorheriges `revoked`-Ereignis/Outbox (R0-09 §2 nennt „alle Grants → revoked“); bei Bedarf nachziehen.
- Das Löschereignis `personal_data_erased` trägt weiterhin als `object_id` den gesalzenen SHA-256 der Nutzer-ID (bestehendes Verhalten, Test `PrivacyOpsTest`); im Sinne von D12 könnte es ganz entfallen – Entscheidung offen.
- ~~Rückgängig („Undo“) eines Tabs kann eine früher begründete Verlängerung ohne neue Begründung wiederherstellen.~~ **Erledigt (24.11.2026):** `Settings::undo()` verlangt für den Tab Datenschutz eine Begründung (`_retention_reason`, wie beim Speichern höchstens 500 Zeichen), sobald die Wiederherstellung Fristen über ihren Vorschlag hinaus verlängert (`Settings::undo_extended()`); ohne Begründung `422 ccap_validation_failed` (`fields.retention = retention_reason`), nichts wird geändert, der Undo-Platz bleibt. Das Undo-Formular zeigt dann ein Begründungsfeld, Ergebnis `undo_reason` mit eigener Meldung; Audit `settings_saved` mit `reason`, `retention_extended` und `context = undo`. Test `SettingsUndoRetentionReasonTest`.
- `CourseEnd` nutzt NOT-EXISTS-Unterabfragen über fünf Tabellen; bei sehr großen `ccap_attempt`-Beständen ist die Laufzeit der Vorschauzählung im Betrieb zu beobachten (Indizes `user_course`/`user_access` vorhanden).
- Rechtsprüfung der Werte und Gegenlesen der neuen Übersetzungen durch eine zweite Person (AG-05).
