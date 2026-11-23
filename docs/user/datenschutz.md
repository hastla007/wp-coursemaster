# Datenschutzhinweise für Betreiber

Grundlage: R0-31 (Privacy-/Security-Defaults), R0-17 (Threat Model/DSFA), Nachweis R1-17. Geprüft gegen: `src/Privacy/{PrivacyService,RetentionService}.php`, `src/Certificate/CertificateService.php` (Export/Löschung/Aufbewahrung), `src/Notification/*`, `src/Activity/ExternalMedia.php`, `src/Access/ProtectedStorage.php`, `src/Event/AuditLog.php`, `src/Kernel/UninstallManifest.php`.

Diese Hinweise ersetzen keine Rechtsberatung. Verantwortlich für die Verarbeitung ist der Betreiber der Website.

## 1. Was gespeichert wird

Alle Tabellen sind einer Klasse zugeordnet (vollständig, durch Test abgesichert):

| Klasse | Tabellen (`<präfix>ccap_…`) |
|---|---|
| **personenbezogen** | `exam_session`, `exam_session_question` (Prüfungsversuche, Antworten), `attempt`, `attempt_answer` (Lernantworten), `question_progress`, `activity_progress`, `course_progress` (Lernstand), `favorite`, `collection`, `collection_item`, `group` (Besitzer), `seat` (Plätze, Einladungsadresse), `grant`, `grant_event` (Zugänge und Historie), `assignment_submission`, `assignment_file`, `assignment_grade` (Aufgaben, noch ungenutzt), `certificate`, `audit_log`, `request_idempotency`, `course_staff`, `learner_lock`, `accommodation`, `notification`, `reset_journal` |
| **Inhalt** | Kursstruktur, Revisionen, Fragen und Antwortoptionen, Medienzuordnung, Prüfungsregeln, Blueprints, Varianten, Zugangsregeln, Overrides, Gruppenkurse, Source-Map |
| **System** | Leases, Migrationsschritte, Inbox/Outbox, Importprotokolle, Ratenzähler |

Weitere Speicherorte:

| Ort | Inhalt | Personenbezug |
|---|---|---|
| Optionen `course_captain_*` | Einstellungen, Mailvorlagen, Zertifikatsvorlagen, Zustände von Cron/Mail/Cache | Absendername/-adresse und Mail-Fußzeile (Klasse `personal`, im Protokoll nur „geändert“) |
| User-Meta `ccap_notice_state`, `ccap_certificate_name_public` | ausgeblendete Admin-Hinweise; Freigabe des Namens für die Zertifikatsprüfung | ja |
| User-Option `course_captain_notification_prefs` | abbestellte Mailarten | ja |
| Post-Meta `_ccap_*` | Activity, UIDs, Revisionsbezug, Schutzmarken | nein |
| `uploads/course-captain/protected-<geheim>/`, `packages-<geheim>/` (oder `COURSE_CAPTAIN_PRIVATE_DIR`) | geschützte Kursdateien, Importpakete | in der Regel nein (Inhalt) |
| Cookies, lokaler Speicher | **keine** eigenen Cookies | – |

Fragen- und Prüfungs-Token sind HMAC-signiert und werden nicht gespeichert. Einladungs- und Gruppenlinks werden nur als SHA-256-Hash gespeichert.

## 2. Export und Löschung (WordPress-Datenschutzwerkzeuge)

**Export** (Werkzeuge › Personenbezogene Daten exportieren, Eintrag „Course Captain“): Gruppen `cc-progress`, `cc-activity`, `cc-questions`, `cc-answers`, `cc-favorite`, `cc-collections`, `cc-exams`, `cc-grants`, `cc-seats`, `cc-notifications`, `cc-notification-prefs`, `cc-submissions`, `cc-certificates` (Snapshot-Daten) und `cc-invitations` (auch für Adressen ohne Konto). Es werden nur eigene Zeilen exportiert, ohne Hashes und Token.

**Löschung** (Werkzeuge › Personenbezogene Daten löschen bzw. Löschen des WordPress-Kontos):

- **gelöscht:** Lernstand, Lernantworten, Favoriten und Sammlungen, Prüfungsversuche samt Antworten, Zugänge samt Historie, Kursteam-Zuordnung, Lernsperren, Nachteilsausgleiche, Idempotenzschlüssel, Reset-Journal, Benachrichtigungen, **Zertifikate** (die Verifikation meldet danach „nicht gefunden“) und die Namensfreigabe; offene Einladungen der Adresse.
- **anonymisiert:** Plätze werden freigegeben (ohne Konto, Adresse und Token); Gruppen verlieren den Besitzer und bleiben bestehen.
- **behalten:** das Audit-Protokoll (Manipulationsschutz) und Kursinhalte. Die Löschung selbst wird mit einer pseudonymen Referenz protokolliert. Die Pseudonymisierung bestehender Protokolleinträge ist offen (Klärung R0-13 §4.4).
- Nicht gelöscht wird heute die User-Option `course_captain_notification_prefs` (Liste der abbestellten Mailarten); sie entfällt mit dem WordPress-Konto bzw. bei Deinstallation mit Datenlöschung.
- Option „Nachweise bei Löschung behalten (pseudonymisiert)“ (`keep_evidence_on_erasure`): geplant (R1-34).
- Eigenes Antragsformular im Lernbereich und asynchroner Export: geplant (R1-34). Die Einstellungen `privacy.auto_export_on_confirm` und `auto_erase_on_confirm` haben noch keine Wirkung.

## 3. Aufbewahrung (stündlicher Lauf)

| Daten | Frist im Code | Regel |
|---|---|---|
| Lernantworten (`attempt`, `attempt_answer`) | 730 Tage nach Erstellung | RT-04 |
| Einladungsadresse und Token abgelaufener/entfernter Plätze | 30 Tage nach Ablauf | RT-15 |
| entfernte Plätze | 365 Tage | RT-17 |
| aufgehobene Lernsperren | 30 Tage | RT-22 |
| Importprotokoll „unverändert“ / gesamt | 14 / 180 Tage | RT-24/25 |
| Importpaket-Kopien | aktives Paket + jüngstes Rollback-Ziel | RT-26 |
| Benachrichtigungen (abgeschlossene) | 90 Tage | RT-30 |
| Zertifikate | 10 Jahre nach der letzten Fassung | RT-09 |
| zurückgehaltene Mails (`held`) | 30 Tage | Mailstatus `held_expired` |
| Outbox: offene / zugestellte Ereignisse | 30 Tage → `dead` / 90 Tage gelöscht | Outbox |
| Idempotenzschlüssel, Ratenzähler, Settings-Undo | nach Ablauf / 30 Tage | RT-39/40/42 |

Jeder Lauf schreibt je Datenart einen Protokolleintrag `retention_applied` mit Anzahl, ohne IDs.

**Noch ohne automatische Löschung:** Prüfungsversuche, beendete Zugänge samt Historie, Lernstand, Favoriten und das Audit-Protokoll. Diese Daten bleiben, bis eine Person gelöscht wird. Fristen einstellbar machen und Rückstand im Systemstatus anzeigen: geplant (R1-34/R1-38).

> **Achtung, Abweichung:** Der von Course Captain vorgeschlagene Text für die Datenschutzerklärung (Einstellungen › Datenschutz › Leitfaden) nennt „Lernantworten 2 Jahre nach Kursende, Prüfungsversuche 10 Jahre, beendete Zugänge 10 Jahre, Audit-Protokoll 4 Jahre“. Der Code löscht Lernantworten 2 Jahre nach **Erstellung**. Für Prüfungsversuche, Zugänge und Protokoll gibt es noch keinen Löschlauf. Passen Sie den Text für Ihre Erklärung an, bis die Abweichung behoben ist (gemeldet im Nachweis R1-24).

## 4. Externe Dienste und Übermittlungen

- Course Captain ruft **keine** externen Dienste auf und sendet keine Telemetrie. Updates kommen nur über WordPress.org.
- **Eingebettete Videos von fremden Hosts** (z. B. Videoplattformen), die Autoren als Video-Lektion eintragen: Im Modus `always_load` (Standard) lädt der Browser der Lernenden diese Inhalte sofort, und der Host erhält dabei IP-Adresse und Browserdaten. YouTube wird über `youtube-nocookie.com`, Vimeo mit `dnt=1` eingebettet. Im Modus `click_to_load` wird vor dem Klick nichts geladen: Lernende sehen Dienstname, Hinweis auf die Übertragung (mit Link zur Datenschutzerklärung von YouTube bzw. Vimeo) und den Knopf „Video laden“, der nur dieses Video lädt. Untertitel werden ebenfalls erst danach geladen. Ohne JavaScript erscheint nur ein Link „Video auf <Dienst> ansehen“. Ist ein Consent-Tool mit WP Consent API aktiv und die Kategorie (`privacy.consent_category`, Standard `marketing`) erteilt, lädt das Video ohne Klick. Mit `privacy.external_media_remember` (Standard an) können Lernende „Externe Medien von <Dienst> immer laden“ ankreuzen (nie vorangekreuzt); die Wahl liegt nur im Browser (`localStorage`, ohne Personenbezug) und lässt sich am geladenen Video mit „Externe Medien von <Dienst> nicht mehr automatisch laden“ zurücknehmen. Hosts auf der eigenen Domain gelten nicht als fremd (Allowlist über Filter `course_captain_trusted_media_hosts`).
- **Mails** gehen über `wp_mail` an den vom Betreiber konfigurierten Versanddienst. Mails enthalten keine Punkte oder Quoten. Abmeldelinks sind HMAC-signiert.
- **Öffentliche Zertifikatsprüfung** zeigt nur Status, Kurs, Datum und – nach Freigabe durch die Person – den Namen; nie E-Mail, Konto-ID oder Punkte. Die Seite ist `noindex`.

## 5. Sicherheitsvoreinstellungen (Auszug, umgesetzt)

- Rechte werden serverseitig für jedes Objekt geprüft; fremde Objekte antworten wie nicht existierende.
- Antworten mit Personendaten werden nie öffentlich zwischengespeichert (`no-store`, `DONOTCACHEPAGE`).
- Geschützte Dateien werden nur über `/cc-file/<uid>/` nach Rechteprüfung ausgeliefert (`private, no-store`, `nosniff`, Sandbox außer PDF). Die Ablage ist per `.htaccess` gesperrt; für nginx ist eine eigene Regel nötig.
- Debug-Ausgaben enthalten keine Antworten oder Token. Ein eigenes Debug-Log gibt es noch nicht (R1-38).
- Website-Härtung (Login-/Reset-Limits, Header): geplant (R1-45).

## 6. Deinstallation

Standardmäßig bleiben alle Daten erhalten. Details in [installation.md](installation.md) §6.
