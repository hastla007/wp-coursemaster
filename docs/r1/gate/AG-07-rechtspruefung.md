# AG-07 · Prüfmappe für die juristische Bestätigung (Fristen und externe Medien)

**Stand:** 23.11.2026 · **Code-Stand:** `claude/blissful-curie-tuuf7q` @ `8ac511f`
**Zweck:** Grundlage für die juristische Bestätigung nach **E-329** (Eingangskriterium von **R1-GATE**, Auflage aus dem R0-GATE-Protokoll). Je Frist bzw. Regel: Beschluss, was der Code heute tut (Datei, Default, änderbar ja/nein), wo der Code vom Beschluss abweicht, und eine Zeile zum Abzeichnen.
**Wer prüft:** die Rechtsberatung bzw. der Datenschutz der Betreiberin (Inhaberin). Diese Mappe ist **keine Rechtsberatung**; sie beschreibt nur den technischen Stand.
**Grundlage:** `docs/r0/ENTSCHEIDUNGEN.md` (E-43, E-44, E-45, E-45a, E-51, E-92, E-143, E-145, E-150, E-243, E-244, E-289, E-312, E-329), R0-31 §2.1 (Datenarten RT-01…RT-45) und §3, R0-17 §6.5 (AR-01), R0-13 §4.4, R0-16 §2.3; Nachweise R1-17, R1-23, R1-34, R1-38, R1-18 g5; Code (unten je Zeile). Clean Room (E-01/E-327).

**Lesehilfe:** „Code = Beschluss“ heißt, der Code löscht bzw. verhält sich wie beschlossen. „**Abweichung**“ heißt, der Code tut heute etwas anderes oder (noch) nichts; die Rechtsprüfung soll dann sowohl den **Beschluss** bewerten als auch sagen, ob der heutige Stand bis zur Behebung vertretbar ist. Die Zeile „Bestätigung“ füllt die prüfende Person aus.

---

## 1. Kurzüberblick

| Nr. | Beschluss | Kern | Code = Beschluss? |
|---|---|---|---|
| J-01 | E-44 | Audit-Protokoll 4 Jahre | ja (nicht änderbar) |
| J-02 | E-44 / E-243 | beendete Zugänge 10 Jahre | **Abweichung:** kein Löschlauf |
| J-03 | E-44 | Import-Protokoll 14 / 180 Tage | ja (nicht änderbar) |
| J-04 | E-51 | Lernstände bis Kontolöschung | ja |
| J-05 | E-51 / E-244 | Rohantworten Lernmodus 2 Jahre nach Kursende | **Abweichung:** 2 Jahre nach Erstellung |
| J-06 | E-51 / E-244 | Rohantworten Prüfung 2 Jahre nach Kursende | **Abweichung:** kein Löschlauf |
| J-07 | E-51 | Prüfungsversuche 10 Jahre | **Abweichung:** kein Löschlauf |
| J-08 | E-51 | Zertifikate 10 Jahre | ja (nicht änderbar) |
| J-09 | E-289 | Aufgaben-Abgaben 2 Jahre nach Kursende; Dateitypen/Grenzen | ja |
| J-10 | E-92 | Lösch-Tombstones nach Restore wieder anwenden | **Abweichung:** nur Erkennung, keine Wiederanwendung |
| J-11 | E-143 | Debug-Fenster ≤ 24 h, Dateien 7 Tage, Fehler 30 Tage | **teilweise:** kein Debug-Log, keine Fehlertabelle |
| J-12 | E-145 | Akteure in der Betroffenenauskunft nur als Rolle | ja (Audit gar nicht im Export) |
| J-13 | E-150 | Eraser löscht standardmäßig; Option „pseudonymisiert behalten“ | ja für den Standard; Option fehlt |
| J-14 | E-45a / AR-01 | externe Medien Default „immer laden“ | ja |
| J-15 | RR-7 / GM-176 | Textvorschlag Datenschutzerklärung | **Abweichung:** Text nennt Fristen, die der Code nicht durchsetzt |
| J-16 | E-43 | Selbstbedienung Export/Löschung | ja |

**Hinweis zur Änderbarkeit:** E-44, E-51 und E-150 sprechen von **änderbaren** Defaults. Die Unterschlüssel `privacy.retention.*` (21 Stück, R0-31 §2.1) sind **nicht** im Settings-Register; alle Fristen stehen als Konstanten in `src/Privacy/RetentionService.php` (R1-17 A-4, R1-34 A-1). Die Betreiberin kann sie heute nicht ändern.

---

## 2. Fristen

### J-01 · Audit-Protokoll (E-44, RT-23)

| | |
|---|---|
| Beschluss | LB-Wert als änderbarer Default: **4 Jahre**; Eraser pseudonymisiert; Akteure nur als Rolle (E-145) |
| Code | `src/Privacy/RetentionService.php`: `AUDIT_YEARS = 4`, `prune_audit()` löscht stündlich höchstens 5 000 Einträge älter als 4 Jahre (älteste zuerst) und schreibt einen Sammeleintrag `audit_pruned` (Anzahl, Zeitspanne, Ankerwert der Hash-Kette); Test `GM-162` |
| Änderbar | nein (Konstante) |
| Abweichung | Pseudonymisierung bestehender Audit-Einträge bei einer Löschung ist offen (`docs/user/datenschutz.md` §2, R0-13 §4.4). Die Löschung selbst wird mit gehashter Nutzerreferenz protokolliert (`PrivacyService::erase_user()`, Aktion `personal_data_erased`) |
| Frage an die Prüfung | Ist 4 Jahre als Default vertretbar? Ist es vertretbar, Audit-Einträge bei einer Löschung bis zum Fristende nicht zu pseudonymisieren? |

Bestätigung: ☐ Ja, 4 Jahre vertretbar ☐ Nein, stattdessen: ________ · Pseudonymisierung: ☐ vor R1-GATE nötig ☐ später ausreichend · Name/Datum/Kürzel: ______________________

### J-02 · Beendete Zugänge und Grant-Historie (E-44, E-243, RT-12/RT-13)

| | |
|---|---|
| Beschluss | beendete Grants bestehender Konten samt Ereignissen **10 Jahre** ab Grant-Ende; Grant-Historie gelöschter Konten aus Kaufquellen 10 Jahre pseudonymisiert; übrige Quellen bei Kontolöschung sofort |
| Code | **kein Löschlauf** für `ccap_grant`/`ccap_grant_event` in `RetentionService::run()`. Bei Kontolöschung bzw. Eraser werden Grants und Ereignisse des Kontos vollständig gelöscht (`PrivacyService::erase_user()`), nicht pseudonymisiert. In Free gibt es keine Kaufquellen (Woo/Voucher erst R3) |
| Änderbar | – |
| Abweichung | Ohne Löschantrag bleiben beendete Zugänge **unbegrenzt** gespeichert (widerspricht RR-1 „kein stilles unbegrenzt“). Der Textvorschlag (J-15) behauptet „beendete Zugänge 10 Jahre“ |
| Frage an die Prüfung | 10 Jahre bestätigen? Ist der heutige Stand (keine automatische Löschung) bis zur Umsetzung vertretbar, oder blockiert er R1-GATE? |

Bestätigung: ☐ Ja, 10 Jahre vertretbar ☐ Nein, stattdessen: ________ · Heutiger Stand: ☐ blockiert R1-GATE ☐ vertretbar bis ________ · Name/Datum/Kürzel: ______________________

### J-03 · Import-Protokoll (E-44, RT-24/RT-25/RT-26)

| | |
|---|---|
| Beschluss | Zeilen „unverändert“ 14 Tage, übrige Zeilen 180 Tage; Paketkopien: die 2 neuesten je Quelle |
| Code | `RetentionService`: `IMPORT_UNCHANGED_DAYS = 14`, `IMPORT_LOG_DAYS = 180` (auch `ccap_import_issue`), `package_copies()` behält aktives Paket, jüngstes Rollback-Ziel und laufenden Import; Test `GM-131` |
| Änderbar | nein |
| Abweichung | keine inhaltliche. Akteur im Importprotokoll wird bei einer Löschung nicht pseudonymisiert (R0-31 RT-25 „Akteur pseudonymisieren bei Eraser“) |

Bestätigung: ☐ Ja ☐ Nein, stattdessen: ________ · Name/Datum/Kürzel: ______________________

### J-04 · Lernstände (E-51, RT-01…RT-03)

| | |
|---|---|
| Beschluss | Lernstand, Favoriten, Sammlungen **bis zur Kontolöschung** bzw. bis zum Löschantrag; inaktive Konten optional (RT-02, Default aus, E-154) |
| Code | `PrivacyService::erase_user()` löscht `ccap_course_progress`, `ccap_activity_progress`, `ccap_question_progress`, Favoriten, Sammlungen (Hook bei Kontolöschung und WP-Eraser). RT-02 (inaktive Konten) nicht umgesetzt (Default ist ohnehin aus) |
| Änderbar | RT-02 nicht vorhanden |

Bestätigung: ☐ Ja ☐ Nein, stattdessen: ________ · Name/Datum/Kürzel: ______________________

### J-05 · Rohantworten im Lernmodus (E-51, E-244, RT-04)

| | |
|---|---|
| Beschluss | **2 Jahre nach Kursende**; Kursende = späterer Zeitpunkt aus „letzter aktiver Grant endet“ und „letzte Aktivität im Kurs“ (E-244); danach in den Lernstand aggregieren |
| Code | `RetentionService`: `ANSWERS_DAYS = 730`; Schritt `RT-04_answers` löscht `ccap_attempt` samt `ccap_attempt_answer`, wenn `created_at` älter als 730 Tage ist |
| Änderbar | nein |
| Abweichung | Bezugspunkt ist die **Erstellung der Antwort**, nicht das Kursende. Der Code löscht damit **früher** als beschlossen (bei langen Kursen auch während der Kurs noch läuft). Smarttrainer-Stand und Fortschritt bleiben (PSD-014) |
| Frage an die Prüfung | 2 Jahre nach Kursende bestätigen? Ist die frühere Löschung (ab Erstellung) zulässig bzw. sogar vorzuziehen? |

Bestätigung: ☐ Ja, wie beschlossen ☐ Ja, wie heute im Code ☐ Nein, stattdessen: ________ · Name/Datum/Kürzel: ______________________

### J-06 · Rohantworten in Prüfungen (E-51, E-244, RT-05)

| | |
|---|---|
| Beschluss | Antworten, Markierungen, Freitexte einer Prüfung **2 Jahre nach Kursende** löschen; Kerndaten (RT-06) bleiben |
| Code | **kein Löschlauf** für `ccap_exam_session_question`; Löschung nur bei Kontolöschung/Eraser |
| Abweichung | Prüfungs-Rohantworten bleiben ohne Löschantrag **unbegrenzt** |
| Frage an die Prüfung | Frist bestätigen; heutigen Stand bewerten |

Bestätigung: ☐ Ja, 2 Jahre nach Kursende ☐ Nein, stattdessen: ________ · Heutiger Stand: ☐ blockiert R1-GATE ☐ vertretbar bis ________ · Name/Datum/Kürzel: ______________________

### J-07 · Prüfungsversuche, Kerndaten (E-51, RT-06/RT-08)

| | |
|---|---|
| Beschluss | Kurs, Datum, Punkte, bestanden: **10 Jahre** ab Abgabe bzw. Auswertung; laufende Versuche ohne Abgabe optional nach x Tagen automatisch abgeben (`exams.auto_submit_inactive_days`, Default aus) |
| Code | **kein Löschlauf** für `ccap_exam_session`; Löschung nur bei Kontolöschung/Eraser. `auto_submit_inactive_days` ist im Register (`src/Settings/SettingsSchema.php`) |
| Abweichung | ohne Löschantrag **unbegrenzt**; der Textvorschlag (J-15) nennt 10 Jahre |

Bestätigung: ☐ Ja, 10 Jahre ☐ Nein, stattdessen: ________ · Heutiger Stand: ☐ blockiert R1-GATE ☐ vertretbar bis ________ · Name/Datum/Kürzel: ______________________

### J-08 · Zertifikate (E-51, RT-09)

| | |
|---|---|
| Beschluss | Snapshot, Revisionen, Status **10 Jahre** ab letzter Fassung; danach meldet die Verifikation „nicht verfügbar“ |
| Code | `RetentionService`: `CERTIFICATES_DAYS = 3653`; `CertificateService::expire_retention()` löscht je Zertifikat alle Revisionen, wenn die jüngste älter ist (höchstens 500 je Lauf) |
| Änderbar | nein |
| Öffentliche Prüfseite | zeigt Status, Kurs, Datum, Namen nur nach Freigabe durch die Person; `noindex` (`docs/user/datenschutz.md` §4) |

Bestätigung: ☐ Ja ☐ Nein, stattdessen: ________ · Name/Datum/Kürzel: ______________________

### J-09 · Aufgaben-Abgaben (E-289, E-168, RT-45, Art.-9-Risiko DK-15)

| | |
|---|---|
| Beschluss | Text, Dateien, Bewertung, Rückmeldung **2 Jahre nach Kursende** (wie Rohantworten); erlaubt **pdf, docx, odt, txt, jpg, png**; **20 MB** je Datei, **5 Dateien** je Abgabe (änderbar); kein eingebauter Virenscan, aber Hook für Scanner |
| Code | `AssignmentService::expire_retention()` (über `RetentionService`, `ANSWERS_DAYS = 730`): löscht Abgaben je Person und Kurs erst, wenn die letzte Änderung älter als 2 Jahre ist, **keine** Abgabe mehr `submitted` ist, **kein** aktiver Grant mehr besteht und **keine** Aktivität im Kurs in diesem Zeitraum war (entspricht E-244). Grenzen im Register: `assignment_file_types` (Default alle 6), `assignment_max_file_mb` (20, 1–100), `assignment_max_files` (5, 1–20). Scanner-Hook `course_captain_assignment_file_scan` (`src/Assignment/AssignmentFiles.php`), Ablage geschützt, Auslieferung nur als Download |
| Änderbar | Grenzen ja (Einstellungen), Frist nein |
| Hinweis | Abgaben können Gesundheits- oder andere besondere Daten enthalten (Freitext, Dateien) – R0-17 DK-15 |

Bestätigung Frist: ☐ Ja ☐ Nein, stattdessen: ________ · Dateitypen/Grenzen: ☐ Ja ☐ Nein: ________ · Art.-9-Risiko: ☐ Hinweis in der Doku genügt ☐ weitere Maßnahme: ________ · Name/Datum/Kürzel: ______________________

### J-10 · Löschungen nach einem Restore (E-92, E-151, RT-41)

| | |
|---|---|
| Beschluss | Lösch-Tombstones **außerhalb** des normalen Datenbestands führen und **nach jedem Restore automatisch** erneut anwenden; Aufbewahrung `backup_max_age_days` + 30 Tage (Default 90 + 30 = 120 Tage) |
| Code | nur der Erkennungsteil: jede Löschung erhöht die Epoche (`SiteIdentity::bump_epoch()` in `PrivacyService::erase_user()`); ein Restore eines älteren Stands wird erkannt und schaltet den Mail-Haltemodus ein (R1-23). **Keine** Tombstone-Tabelle, **keine** Wiederanwendung (R1-23 O-2). Einstellung `privacy.backup_max_age_days` (Default 90) ist vorhanden, aber ohne Wirkung |
| Abweichung | Nach einem Restore aus einem älteren Backup tauchen gelöschte Personen **wieder auf**. Der Hilfetext der Einstellung sagt: „Damit gelöschte Personen nach einer Wiederherstellung nicht wieder auftauchen, merkt sich Course Captain Löschungen so lange plus 30 Tage.“ – das trifft heute **nicht** zu |
| Frage an die Prüfung | Beschluss bestätigen; reicht bis zur Umsetzung ein Betreiberhinweis (Löschungen nach Restore manuell wiederholen), oder blockiert das R1-GATE? Hilfetext ändern? |

Bestätigung: ☐ Ja, Beschluss ☐ Nein, stattdessen: ________ · Heutiger Stand: ☐ blockiert R1-GATE ☐ vertretbar mit Betreiberhinweis bis ________ · Name/Datum/Kürzel: ______________________

### J-11 · Debug- und Fehlerprotokolle (E-143, RT-33/RT-34/RT-35)

| | |
|---|---|
| Beschluss | Debug-Fenster höchstens **24 h** (Standard **2 h**), Debug-Dateien **7 Tage**; Fehlertabelle und Job-Fehler **30 Tage**; Diagnosepaket 24 h, Link 1 h |
| Code | **kein eigenes Debug-Log** und **keine Fehlertabelle** (R1-38 A-8/O-3; `docs/user/datenschutz.md` §5). Vorhanden: Outbox-Zustellungen, die 30 Tage offen sind, werden `dead` (`OutboxDispatcher::OPEN_DAYS = 30`), zugestellte Ereignisse nach 90 Tagen gelöscht (`DELIVERED_DAYS = 90`); zurückgehaltene Mails 30 Tage (`NotificationService::HELD_DAYS`); Benachrichtigungsprotokoll 90 Tage (`NOTIFICATION_LOG_DAYS`); Diagnosepaket wird direkt heruntergeladen (keine Serverkopie), Scrubber entfernt Personendaten |
| Abweichung | Fristen für Debug/Fehler sind gegenstandslos, solange es die Protokolle nicht gibt. Offene Frage: Wie lange bleiben `dead`-Zustellungen? (kein Löschschritt gefunden) |

Bestätigung: ☐ Ja, Fristen für spätere Umsetzung ☐ Nein, stattdessen: ________ · Name/Datum/Kürzel: ______________________

### J-12 · Akteure in der Betroffenenauskunft (E-145)

| | |
|---|---|
| Beschluss | in Auskunft/Export erscheinen Mitarbeitende **nur als Rolle** („Support“, „Administrator“, „System“), nie mit Namen |
| Code | Der Export (`PrivacyService::export`, Gruppen `cc-progress` … `cc-invitations`; Selbstbedienung `DataExport`) enthält **keine** Audit-Einträge und keine Namen von Mitarbeitenden. Bewertungen von Abgaben werden mit Punkten, Rückmeldung und Begründung, aber ohne bewertende Person exportiert (`cc-submission-grades`) |
| Frage an die Prüfung | Genügt es, Audit-Einträge über die eigene Person gar nicht zu beauskunften, oder müssen sie (mit Rolle statt Namen) in den Export? Muss bei Bewertungen die bewertende Rolle genannt werden? |

Bestätigung: ☐ Ja, Stand genügt ☐ Audit muss in den Export (mit Rolle) ☐ anders: ________ · Name/Datum/Kürzel: ______________________

### J-13 · Löschantrag gegen Nachweis-Aufbewahrung (E-150, RT-07/RT-10)

| | |
|---|---|
| Beschluss | **Eraser löscht standardmäßig** auch Prüfungsversuche und Zertifikate; Option „Nachweise trotz Löschantrag pseudonymisiert aufbewahren“ (`keep_evidence_on_erasure`) Default **aus**, mit Pflichtbegründung, Begründung im Eraser-Ergebnis sichtbar |
| Code | Standard umgesetzt: `PrivacyService::erase_user()` löscht Prüfungsversuche, Antworten und Zertifikate (`CertificateService::erase()`); die Verifikation meldet danach „nicht gefunden“. Die Option ist **nicht** im Register; Risikodialog und Text sind vorbereitet (`src/Admin/RiskDialog.php`, `src/Settings/RiskRules.php`): „Prüfungsversuche und Zertifikate von Personen, die eine Löschung verlangen, werden pseudonymisiert behalten statt gelöscht. Dein Grund erscheint im Ergebnis jeder Anfrage: „%s“. Ohne Rechtsgrundlage ist das nicht erlaubt.“ |
| Abweichung | strenger als beschlossen (Option fehlt) |
| Weitere Defaults | `privacy.auto_export_on_confirm` = an (Export nach Bestätigung im Hintergrund); `privacy.auto_erase_on_confirm` = **aus** (bestätigte Löschanträge warten auf die Verwaltung, E-43) |

Bestätigung: ☐ Ja ☐ Nein, stattdessen: ________ · Option vor R1-GATE nötig: ☐ ja ☐ nein · Name/Datum/Kürzel: ______________________

---

## 3. Externe Medien (E-45, E-45a, E-312, AR-01)

### J-14 · Default „immer laden“

| | |
|---|---|
| Beschluss | `privacy.external_media_mode` Default **`always_load`** („Immer laden“), umschaltbar auf `click_to_load` („Nach Klick laden“) und `link_only` („Nur als Link zeigen“); Hinweis im Setup-Assistenten; Offenlegung der externen Dienste in der `readme.txt`. Risiko AR-01 (R0-17 §6.5): Übertragung von IP, Referrer, Gerätedaten an den Anbieter ohne vorherige Einwilligung, ggf. Drittland; Verantwortlich ist die Betreiberin |
| Code – Einstellung | `src/Settings/SettingsSchema.php`: `external_media_mode` (enum, Default `always_load`), `consent_category` (Default `marketing`, für WP Consent API), `external_media_remember` (Default an; Lernende dürfen „Immer laden“ wählen, nie vorausgewählt) |
| Code – Verhalten | `src/Activity/ExternalMedia.php`: eigene Domain, Subdomains und per Filter `course_captain_trusted_media_hosts` erlaubte Hosts gelten nicht als fremd. `link_only`: nie eingebettet, Link in neuem Tab. `click_to_load`: vor Einwilligung keine URL des fremden Hosts im Seitenquelltext; mit Einwilligung über die WP Consent API wird geladen. `src/Learner/LessonView.php`: **YouTube und Vimeo werden bei `always_load` eingebettet** (`youtube-nocookie.com`, Vimeo `dnt=1`, ohne JavaScript); **direkte Videodateien auf fremden Hosts** mit dem nativen Player (`preload="metadata"`) – in beiden Fällen entsteht die Übertragung beim Seitenaufruf. `click_to_load`: Platzhalter mit Dienstname, Hinweis auf die Übertragung, Link zur Datenschutzerklärung des Dienstes und Knopf **„Video laden“** (lädt nur dieses Video); Kästchen „Externe Medien von <Dienst> immer laden“ (nie vorangehakt, Wahl im Browser, am Video widerrufbar); ohne JavaScript nur Link (Nachtrag h9, `docs/r1/R1-22-nachtrag-h9.md` §1) |
| Text in den Einstellungen (de_DE) | „Standardmäßig lädt der Lernbereich externe Videos sofort. Der Browser deiner Lernenden überträgt dann ohne vorherige Einwilligung mindestens die IP-Adresse an den Videodienst. Kläre mit deiner Datenschutzberatung, ob du das möchtest. Mit „Nach Klick laden“ wird die Verbindung erst nach einem Klick oder einer Einwilligung in deinem Consent-Tool aufgebaut.“ |
| Setup-Assistent | Schritt „E-Mail & Datenschutz“ enthält das Feld (`src/Admin/SetupWizard.php`, Einleitung: „Course Captain sendet Mails über WordPress. Wähle den Absender und wie externe Videos geladen werden.“) |
| readme.txt | Abschnitt „External services“: Plugin ruft selbst keine Dienste auf; Videos auf fremden Hosts lädt der Browser der Lernenden, der Host erhält IP und Browserdaten; im Modus „click to load“ wird vor dem Klick nichts geladen |
| Betreiberdoku | `docs/user/datenschutz.md` §4 |
| Abweichungen / Hinweise | (1) erledigt (Nachtrag h9, 23.11.2026): Knopf „Video laden“ im Modus `click_to_load`. (2) erledigt: YouTube/Vimeo werden datensparsam eingebettet; readme nennt beide Dienste mit Anbieter und Links. (3) Der Textvorschlag für die Datenschutzerklärung (J-15) nennt externe Videos **nicht** |
| Fragen an die Prüfung | (a) Ist `always_load` als **Default** für eine an Dritte ausgelieferte Software vertretbar, wenn Hinweis, Umschalter und readme-Offenlegung vorhanden sind? (b) Genügt der Hinweistext? (c) Muss der Textvorschlag externe Videos nennen? (d) Muss `click_to_load` vor R1-GATE voll bedienbar sein (Knopf)? |

Bestätigung (a) Default: ☐ Ja, `always_load` vertretbar ☐ Nein, Default `click_to_load` ☐ Nein, Default `link_only`
Bestätigung (b) Hinweistext: ☐ Ja ☐ Nein, Änderung: ______________________
Bestätigung (c) Textvorschlag nennt externe Videos: ☐ nötig ☐ nicht nötig
Bestätigung (d) Knopf „Nach Klick laden“ vor R1-GATE: ☐ nötig ☐ nicht nötig
Name/Datum/Kürzel: ______________________

---

## 4. Texte

### J-15 · Textvorschlag für die Datenschutzerklärung (RR-7, GM-176)

Der Text wird über `wp_add_privacy_policy_content()` im WordPress-Leitfaden zur Datenschutzerklärung angeboten (`PrivacyService::policy_html()`; Fristen werden aus `RetentionService` eingesetzt, ohne Links). Wortlaut de_DE mit den heutigen Werten:

> Course Captain speichert Lernfortschritt, Antworten, Prüfungsversuche, Zertifikate, Kurszugänge und Gruppenmitgliedschaften angemeldeter Lernender. Lernantworten werden 2 Jahre nach Kursende aufbewahrt, Prüfungsversuche und Zertifikate 10 Jahre, beendete Zugänge 10 Jahre, das Protokoll 4 Jahre und das Benachrichtigungsprotokoll 90 Tage. Personenbezogene Daten lassen sich mit den Datenschutz-Werkzeugen von WordPress exportieren und löschen; angemeldete Lernende können einen Export oder eine Löschung selbst beantragen.

| Aussage im Text | Code heute | Stimmt? |
|---|---|---|
| Lernantworten 2 Jahre **nach Kursende** | 2 Jahre nach **Erstellung** (J-05) | **nein** |
| Prüfungsversuche 10 Jahre | kein Löschlauf (J-07) | **nein** |
| Zertifikate 10 Jahre | ja (J-08) | ja |
| beendete Zugänge 10 Jahre | kein Löschlauf (J-02) | **nein** |
| Protokoll 4 Jahre | ja (J-01) | ja |
| Benachrichtigungsprotokoll 90 Tage | ja | ja |
| Export/Löschung per WordPress-Werkzeug und Selbstantrag | ja (J-16) | ja |
| externe Videos, Einladungsadressen (30 Tage nach Ablauf), Aufgaben-Abgaben, Gruppenbesitz | nicht genannt | fehlt |

`docs/user/datenschutz.md` §3 warnt Betreiber bereits vor dieser Abweichung.

Bestätigung: ☐ Text so freigegeben (nach Behebung der Abweichungen) ☐ Text ändern: ______________________ · Bis zur Behebung: ☐ Text darf nicht ausgeliefert werden ☐ Betreiberhinweis genügt · Name/Datum/Kürzel: ______________________

### J-16 · Selbstbedienung Export/Löschung (E-43, GM-174/175)

| | |
|---|---|
| Umsetzung | Shortcode `[ccap_privacy_request]` und REST `POST /me/privacy-requests`: Antrag nur für das eigene Konto als WordPress-Datenschutzantrag mit Bestätigungsmail; der Link zeigt eine Seite mit Bestätigungsknopf (GET ändert nichts). Export asynchron (`POST /me/data-exports`), Datei 24 h abrufbar, dann gelöscht (RT-36). Kontolöschung nach Bestätigung nur mit `privacy.auto_erase_on_confirm` (Default aus). Konten mit Verwaltungsrechten können keine Selbstlöschung beantragen. Höchstens 5 Anträge je Tag |
| Frage an die Prüfung | Genügt das Warten auf die Verwaltung bei Löschanträgen (Default) den Fristen nach Art. 12 Abs. 3 DSGVO, wenn die Betreiberin darauf hingewiesen wird? |

Bestätigung: ☐ Ja ☐ Nein: ______________________ · Name/Datum/Kürzel: ______________________

---

## 5. Gesamtbestätigung (E-329)

| Feld | Eintrag |
|---|---|
| Geprüft durch (Name, Funktion, Kanzlei/Stelle) | |
| Grundlage der Prüfung | diese Mappe, Stand 23.11.2026, Code `8ac511f` |
| Ergebnis | ☐ alle Fristen und externe Medien bestätigt ☐ bestätigt mit Auflagen (unten) ☐ nicht bestätigt |
| Auflagen vor R1-GATE (Nummern J-xx) | |
| Auflagen bis zum ersten Patch-Release | |
| Datum, Unterschrift prüfende Person | |
| Datum, Unterschrift Inhaberin (Kenntnisnahme, Übernahme ins R1-GATE-Protokoll) | |

Nach der Unterschrift: Ergebnis als Beschluss in `docs/r0/ENTSCHEIDUNGEN.md` (neue E-Nummer) und in `docs/r1/gate/R1-GATE-protokoll-entwurf.md` §4 eintragen; abgelehnte oder geänderte Fristen als Entwicklungsaufgabe anlegen.

## 6. Wartet auf …

| Punkt | Wartet auf | Zuständig |
|---|---|---|
| Juristische Bestätigung J-01…J-16 (AG-07, E-329) | Prüfung und Unterschrift | Inhaberin → Rechtsberatung/Datenschutz |
| Entscheidung, ob die Abweichungen J-02, J-05, J-06, J-07, J-10, J-15 vor R1-GATE behoben sein müssen | Ergebnis der Prüfung | Rechtsberatung, dann Inhaberin |
| Behebung der Abweichungen (Löschläufe RT-05/RT-06/RT-12/RT-13, Bezugspunkt RT-04, Tombstones, änderbare Fristen `privacy.retention.*`, `keep_evidence_on_erasure`, Textvorschlag; Knopf „Video laden“ erledigt mit Nachtrag h9) | Beauftragung | Entwicklung (Folgeaufgaben zu R1-17/R1-23/R1-34/R1-16) |
