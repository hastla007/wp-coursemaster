# R0-13 · Support- und Betriebskonzept

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Es gibt keine Implementierung, keinen Code und keine Betreiberdoku.
**Stand:** 16.11.2026
**Bezug:** TODO R0-13 (DoD: Supportrollen, sichere Diagnose, Datenkorrektur, Audit- und Eskalationsregeln samt Testfällen), Zuarbeit für **R1-38** (Betriebsdashboard mit sicheren Reparaturaktionen), **R1-24** (Dokumentation, Onboarding und Support), R1-17 (Diagnose, Audit, Health-Checks), R0-34 (Integrationsstatus und Support-Vertrag); Konzept Kap. 22.1 (Queue- und Fehlerzustände), 22.2, 36 (Rechtetabelle „Supportfall, auditpflichtig“), 43.2 (Systemstatus „anonymisierter Diagnoseexport“), 43.3 (Supportzugriff, Diagnose/Debug), Kap. 25 Tabelle „Diagnoselogs/Queue“, OPS-06; `docs/r0/R0-07-rbac-idor-matrix.md` (§2 Rolle `ccap_support`, §3.1 Caps #9/#13/#14/#16/#17/#18/#21/#22/#23, §3.6, §4.2 S-13/S-21/S-22, §4.3, §4.7, E-79, E-82); `docs/r0/R0-09-identity-seat-grant-automaten.md` (§1.3 „Zugang übertragen“, §2.3 G-01…G-15, §2.4, §2.5, E-114); `docs/r0/R0-16-backup-recovery-deployment.md` (§1.4 `backup-check`, §2 Runbooks, §3.1 Ausgangsmodus, §3.2 Secrets, E-85/E-86/E-88/E-92); `docs/r0/R0-08-kurs-activity-pruefungsvertraege.md` (§6.1/6.2 Zustand `broken`); `docs/r0/R0-10-import-medien-sicherheit.md` (§3.6 Rollback, Import-Ereignisse); `docs/r0/R0-23-revisionen-laufender-kurse.md` (§5 Zertifikate); `docs/r0/R0-02-inventar/C-infrastruktur-content.md` (INV-C-055, -084, -147…-149, -163…-175, §5 Audit-Aktionen); `docs/r0/R0-02-inventar/E-theme-tools-content-docs.md` (§5 Betriebsdoku, `support-ablaeufe.md`)
**Quellen (nur gelesen):** LearnBoating `hastla007/learnboating` @ `9f37c77`: `docs/support-ablaeufe.md`, `docs/betrieb-entscheidungen.md`; `bootspruefung-learning/src/Admin/{SystemStatus.php, SiteHealth.php, AuditPage.php, AccessPages.php}`, `src/Infrastructure/{Monitor.php, Cron.php}`, `src/Audit/AuditLog.php`, `src/Cli/Commands.php` (`reconcile` `:353`, `access` `:571`), `src/Commerce/WooCommerce/AutoReconcile.php`, `src/Mobile/Problem.php` (Request-ID). Pfade ohne Präfix sind relativ zu `bootspruefung-learning/src/`. LB-Stellen sind Verhaltensreferenz, kein Code wird übernommen (E-01).

### Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). Alle früheren Fragen R13-D1…D10 sind als **E-136…E-145** entschieden und im Text eingearbeitet (§7). Außerdem wirken:

| Beschluss | Wirkung hier |
|---|---|
| **E-01** Clean Room | Systemstatus, Monitor, Audit und Runbooks werden nach dem beschriebenen LB-Verhalten neu gebaut; `reuse`/`abstract` in §2.1 heißt „Verhalten nachbauen“ |
| **E-10**, E-46, E-239…E-242, E-260, E-261, E-263 | Website-Härtung im Free-Kern (R1-45): neue Prüfung H-38, Runbook RB-SUP-8 „ausgesperrt“; fail-closed für Login/Reset mit Ausnahme bekannter Geräte und Notausschalter |
| **E-19**, E-79, E-82, E-114, E-44, E-51, E-116, E-215 | wie angenommen: Protokollansicht Free, Lernsperre, Pflichtbegründung beim Lesen fremder Versuche, „Zugang übertragen“ statt Merge, Fristen |
| **E-38**, E-182, E-185, E-254 | Free hat nur die **interne Outbox** (Mails, Audit); öffentliche Events, Read-API und Connector-Registry sind Pro. Integrationsstatus in R1 nur lesend (H-34) |
| **E-85…E-92** | Haltemodus, Ausgangsmodus `catch`, Secrets, zurückgehaltene Mails, Tombstones (R0-16) |
| **E-168** | Abgaben in Aufgaben sind Lerndaten: nie im Diagnosepaket, Einsicht wie Versuchsdaten nur mit Begründung |
| **E-207** | Präfix `ccap_` für Rollen, Caps, Tabellen, Cron, Meta; Optionen `course_captain_*` (im Text umgestellt) |
| **E-211**, **E-212**, **E-273** | Sicherheitsmeldungen über die Adresse des Auftraggebers (≤ 72 h), Forum durch den Auftraggeber, Pro-Support über eigenen Kanal; Pro-Add-on in `captain-pro/addons/course-captain-pro` |


---

## 0. Kurzfassung

1. **Fünf Stufen, eine Regel:** Wer korrigiert, braucht die Cap **und** eine Begründung, und es entsteht ein Audit-Eintrag. Stufe 1 (`ccap_support`) sieht Status und Historie, aber keine Antworten und ändert keine Grants. Stufe 2 (Kursmanager) korrigiert Fachdaten. Stufe 3 (Betreiber-Admin) repariert Technik. Der **Course-Captain-Herstellersupport** hat kein Konto auf der Site und bekommt nur ein redigiertes Diagnosepaket.
2. **Systemstatus nach LB-Verhalten neu bauen (E-01), ergänzen um Queue-Sicht:** 24 Prüfungen nach LB-Vorbild (`SystemStatus`, `SiteHealth`, `Monitor`, `BackupCheck`) und 14 neue: Ausgangs- und Haltemodus, Website-Härtung (E-10), Jobs nach Zustand, hängende Grants, `broken`-Versuche, Importe ohne Heartbeat, Outbox-Rückstand.
3. **Diagnosepaket nach Allowlist, nicht nach Blocklist.** Es enthält nur Schlüssel, die ausdrücklich freigegeben sind. Freitext läuft zusätzlich durch Scrubber (E-Mail, IP, Token, Pfade). Vor dem Download sieht der Betreiber eine Vorschau. Das Paket enthält keine Namen, E-Mails, IP-Adressen, Antworten, Kursinhalte, Secrets oder Pfade mit dem Ordner-Geheimnis.
4. **Debug-Logging ist standardmäßig aus**, läuft höchstens 24 h und schaltet sich selbst ab (Konzept 43.3). Es schreibt nur strukturierte Zeilen mit `request_id`/`correlation_id` in den privaten Ordner und wird nach 7 Tagen gelöscht.
5. **17 Korrekturaktionen** `K-01…K-17` (§3) mit festem Muster: Cap, Pflichtbegründung, Audit-Aktion, Undo-Weg, Benachrichtigung. **Nichts wird gelöscht.** „Rückgängig“ bedeutet immer eine neue, auditierte Gegenaktion (Grant-Revision, neuer Import-Batch, Entsperren). Die einzige echte Undo-Funktion ist das Reset-Journal (E-138).
6. **Audit:** `ccap_audit_log` wird um `actor_type`, `subject_user_id`, `reason_code`, `correlation_id`, `request_id` und eine Hash-Kette ergänzt (E-139). Es ist append-only. Ausnahmen sind nur der Prune nach E-44 und die Pseudonymisierung durch den Eraser. Der Ereigniskatalog umfasst 88 Aktionen in 11 Bereichen (§4.1).
7. **Runbooks** für 8 typische Fälle (§5.3), darunter „ausgesperrt durch Website-Härtung“ (E-10). Die vier aus der Aufgabe sind dabei: kein Zugang trotz Kauf, Prüfung abgebrochen, Zertifikat falsch, Import hängt. Grundsatz: Retry und Reconcile gehen vor einem manuellen Grant, sonst entstehen Doppelgrants, die ein späterer Refund nicht mehr erfasst.
8. **44 Testfälle** `SUP-001…SUP-044` (§6) für R1-38, R1-24 und R1-45; die früheren Fragen R13-D1…D10 sind als `E-136…E-145` beschlossen (§7).

---

## 1. Supportrollen und -stufen

### 1.1 Stufenmodell

| Stufe | Wer | WP-Abbildung (R0-07 §2) | Zweck | Grenze |
|---|---|---|---|---|
| **0 · Selbsthilfe** | Lernende, Käufer, Gruppenverwalter | `subscriber` bzw. Konto mit `read`; Objektrecht über Grant/Gruppe | Status in „Mein Lernen“ (R0-09 §3.2), Passwort-Reset, Einladung erneut senden (Besitzer), Datenschutz-Antrag (M-23), Abmeldung von Erinnerungen | nur eigene Objekte |
| **1 · Support-Mitarbeiter** | First-Level beim Betreiber | Rolle `ccap_support`: `read`, #13 `ccap_view_learner_details`, #18 `ccap_lock_learners`, #21 `ccap_view_audit_log`, #22 `ccap_view_system_status` | Fall aufnehmen, Identität prüfen, Status lesen, Ursache eingrenzen, Lernsperre setzen oder aufheben, Diagnosepaket erzeugen, eskalieren | **keine** Antworten/Snapshots (#14), **keine** Grant-Änderung (#9), keine Inhalte, keine Wartung (#23) |
| **2 · Fachliche Korrektur** | Kursmanager, ggf. Trainer | `ccap_course_manager` (#1–#18, #22, #24); Trainer `ccap_trainer` nur Bewertung (#15) im zugewiesenen Kurs | Grants anlegen, verlängern, aussetzen, widerrufen, übertragen; Versuch reparieren oder verwerfen; Fortschritt zurücksetzen; Zertifikat korrigieren oder widerrufen; Import zurückrollen | keine Einstellungen, keine Integrationen, keine Wartung/Reconcile (#23), kein Audit-Screen (#21 fehlt, Objekthistorie siehe §4.5) |
| **3 · Betreiber-Admin** | technischer Betreiber | WP `administrator` (alle 24 Caps) | Reparaturaktionen, Reconcile, Job-Retry, Haltemodus bestätigen, Debug-Logging, Migration, CLI, Integrationen | eigene Versuche nicht bewerten (M-10); fremde Antworten nur mit Begründung (E-82) |
| **4 · Course-Captain-Herstellersupport** | Hersteller (Free-Forum, Pro-Support) | **kein Konto auf der Site** (E-142) | Fehleranalyse anhand von Diagnosepaket, `request_id`, Fehlercodes und Versionen; Anleitung, Patch, Bekannte-Fehler-Liste | **kein Datenzugriff**, kein Remote-Login, keine Personendaten, keine Inhalte |

**Getrennte Rolle Datenschutz:** Export und Löschung personenbezogener Daten laufen über die WP-Kern-Caps `export_others_personal_data` und `erase_others_personal_data` (R0-07 §4.9). Supportstufen erhalten diese Caps nicht automatisch. Datenschutzanfragen gehen nach §5.1 an die befugte Person.

**Kein „Anmelden als Lernender“:** Course Captain bietet keine Impersonation (E-141). Für die Frage „Was sieht der Lernende?“ zeigt die Detailansicht den **effektiven Zugang** samt Begründung des `PolicyService` (R0-07 P-02). Beispiel: „Grant #… aktiv bis …, Scope view/exam, Drip frei ab …“ oder „verweigert: `ccap_account_locked`“.

### 1.2 Was jede Stufe sehen und tun darf

`✓` erlaubt · `B` erlaubt mit Pflichtbegründung · `–` nicht erlaubt. Alle schreibenden Aktionen erzeugen einen Audit-Eintrag (§4).

| Bereich | Daten / Aktion | Cap (R0-07) | St. 1 Support | St. 2 Manager | St. 3 Admin | St. 4 Hersteller |
|---|---|---|---|---|---|---|
| **Lernende** | Liste, Suche per exakter E-Mail/Login/ID | #13 | ✓ | ✓ | ✓ | – |
| | Detail: Kontoname, E-Mail, Grants (Status, Quelle als Referenz, Gültigkeit), Plätze, Lernsperre, effektiver Zugang | #13, M-11 | ✓ | ✓ | ✓ | – |
| | Fortschritt je Kurs/Activity (Prozent, Status, Zeitpunkte) | #13 | ✓ (aggregiert) | ✓ | ✓ | – |
| | Versuchsliste: Status, Start/Ende, `end_reason`, Ergebnis bestanden/nicht bestanden, Punkte | #13 | ✓ | ✓ | ✓ | – |
| | **Versuchsdetail: Antworten, Snapshot** | #14, M-09 | – | B (E-82) | B (E-82) | – |
| **Grants** | lesen inkl. `ccap_grant_event`-Historie | #13 | ✓ | ✓ | ✓ | – |
| | anlegen, verlängern, aussetzen, freigeben, widerrufen, übertragen, Retry `failed` | #9, M-13/M-14 | – | B | B | – |
| **Lernsperre** | setzen / aufheben | #18, M-24 | B | B | B | – |
| **Prüfung/Fortschritt** | Versuch nachauswerten oder verwerfen, Zusatzversuch, Fortschritt zurücksetzen | #16, M-12 | – | B | B | – |
| **Zertifikate** | ansehen (eigene Ansicht, Verifikationsstatus) | #13, M-18 | ✓ | ✓ | ✓ | – |
| | korrigieren, widerrufen | #17, M-19 | – | B | B | – |
| **Gruppen** | Status, Plätze, Einladungsstatus (Adresse nach E-81) | #13 | ✓ | ✓ | ✓ | – |
| | Plätze, Besitzer, Status ändern | #11, M-15 | – | B | B | – |
| **Import** | Batch-Status, Schritt, Heartbeat, Item-Fehler (ohne Inhalte) | #22 | ✓ | ✓ | ✓ | über Diagnosepaket |
| | fortsetzen, verwerfen, zurückrollen | #6, M-21 | – | B (Rollback), ✓ (Resume) | B | – |
| **Systemstatus** | alle Prüfungen §2.1, Job-Liste mit IDs und Fehlercodes | #22 | ✓ | ✓ | ✓ | über Diagnosepaket |
| | Diagnosepaket erzeugen (§2.2) | #22 | ✓ | ✓ | ✓ | erhält es vom Betreiber |
| | Reparaturaktionen, Reconcile, Job-Retry, Haltemodus bestätigen | #23 | – | – | B | – |
| | Debug-Logging ein/aus, Log lesen | #19 + #23 | – | – | B | erhält Auszug im Paket |
| **Audit** | Protokollansicht S-21, Filter | #21 | ✓ | Objekthistorie (§4.5) | ✓ | – |
| | Audit-Export | #21 (E-140) | – | – | B | – |
| **Einstellungen, Integrationen** | lesen/ändern | #19, #20 | – | – | ✓ | Versionen/Status im Paket |

**Folgen für R1-03/R1-38:** Support braucht für seinen Arbeitsfluss keine weitere Cap. Ein Betreiber, der dem First-Level Grant-Korrekturen erlauben will, weist im Tab „Rollen & Rechte“ #9 zu (E-144). Geprüft werden Caps, nicht Rollennamen.

### 1.3 Grundregeln für den Support (aus LB `docs/support-ablaeufe.md` „Grundregeln“, neutralisiert)

| Nr. | Regel | Herkunft |
|---|---|---|
| GR-1 | Auskünfte zu Zugängen, Versuchen und Daten nur an die **E-Mail-Adresse des Kontos** (bzw. der Bestellung, R3). Kommt eine Anfrage von einer anderen Adresse, bittet der Support um eine Antwort von der Kontoadresse und nennt keine Daten. | LB Grundregeln „Identität prüfen“ |
| GR-2 | Nie per Mail anfordern: Passwörter, Zahlungsdaten, Ausweiskopien. Course Captain hat kein Feld dafür. | LB „Nie per Mail anfordern“ |
| GR-3 | Jede Korrektur über die Aktion in Course Captain, **nie** per SQL, `wp option update` oder Tabellen-Editor. Nur so entstehen Grant-Event, Audit und Cache-Invalidierung (R0-07 P-11). | neu (LB dokumentiert in Bestellnotizen, Woo-gebunden) |
| GR-4 | In die Begründung gehören Ticket- bzw. Fallnummer und der Anlass. Nicht hinein gehören Gesundheitsangaben, Passwörter und Zahlungsdaten. Das Formular zeigt diesen Hinweis. | neu |
| GR-5 | Gegenüber dem Lernenden nennt der Support nur die **Wirkung** („Zugang ist bis … aktiv“), keine internen IDs fremder Objekte und keine Daten anderer Personen (zum Beispiel das Zielkonto einer Übertragung). | R0-07 P-08 |
| GR-6 | Eingangsdatum notieren. Datenschutzfristen (in der Regel ein Monat, rechtlich vom Betreiber zu bestätigen) beachten. | LB „Fristen“ |
| GR-7 | Fehlermeldungen im Frontend und in der REST-Antwort tragen eine `request_id` (LB `Mobile/Problem.php:23`, Header `X-Request-Id`). Der Support fragt danach. Sie verbindet die Meldung mit Log, Audit und Diagnosepaket. | LB Mobile, neu für alle Einstiege |

---

## 2. Sichere Diagnose

### 2.1 Systemstatus und Site-Health-Prüfungen

**Ort:** Admin-Screen S-22 „Systemstatus“ (Cap #22), WP-Werkzeuge › Website-Zustand (Abschnitt `debug_information` und `site_status_tests`, LB `Admin/SiteHealth.php:35-36`), `GET course-captain/v1/monitor` (Alias `/health`, E-336; LB `Monitor.php:30`), `wp course-captain status` und `backup-check` (LB `Cli/Commands.php:46`, `:78`). Alle vier lesen **eine** Prüfliste (`HealthRegistry`). Module und Pro registrieren weitere Prüfungen dort (LB INV-C-013 „registrierbare Tasks“). Jede Prüfung liefert `id`, `status` (`ok`/`warn`/`fail`/`info`), Kurztext, Handlungshinweis und optional Objekt-IDs. Sie enthält **nie** Personendaten, Pfade mit dem Ordner-Geheimnis oder Secrets.

**Nach LB-Verhalten neu gebaut** (Klassifikation `reuse`/`abstract` = Verhalten nachbauen, kein Code, E-01; `exclude` = fachlich LB-gebunden):

| Nr. | Prüfung | LB-Beleg | CC-Ziel | Status-Regel CC |
|---|---|---|---|---|
| H-01 | Plugin-Version, API-/Vertragsversion | `SystemStatus.php:171`, `SiteHealth.php:72-73` | reuse; zusätzlich Core, Pro, Schema, Paketformat, REST-API und Connectorvertrag getrennt (Konzept 22.2) | info |
| H-02 | Schema installiert/erwartet, Migration fällig | `SystemStatus.php:172-174`, `Monitor.php:77` | reuse | fällig = warn (Schreib-API 503), Schema neuer als Code = fail |
| H-03 | Letzter Migrationsfehler | `SystemStatus.php:287`, `SiteHealth.php:83` | reuse | fail, solange gesetzt |
| H-04 | Tabellen vorhanden und InnoDB | `SystemStatus.php:180`, `BackupCheck` | reuse (E-08) | fail |
| H-05 | Fremdschlüssel: Anzahl und Probleme | `SystemStatus.php:175-176`, `Monitor.php:84` | reuse (E-48) | warn |
| H-06 | Verwaiste Site-Tabellen (Multisite) | `SystemStatus.php:177` | bedingt (E-07) | warn |
| H-07 | Letzter stündlicher Wartungslauf (≤ 3 h) | `Monitor.php:24`, `:74`, `SystemStatus.php:297` | reuse | > 3 h = fail; frisch installiert und geplant = ok |
| H-08 | Nächster geplanter Lauf, `DISABLE_WP_CRON` ohne System-Cron | `SystemStatus.php:288`, `SiteHealth.php:93` | reuse, mit Hinweis auf echten System-Cron (Konzept 22.1) | warn |
| H-09 | Fehler der Wartungsläufe (letzte 20, je 500 Zeichen) | `Cron.php:19-21`, `:46-55`, `SystemStatus.php:317` | abstract: wird Teil der Job-Liste H-27; Meldung ohne Pfade, nur `basename:line` | Aufgabe in den letzten 2 h gescheitert = fail (`Monitor.php:102`) |
| H-10 | Wartung insgesamt ok (keine ganze Aufgabe in 2 h gescheitert) | `Monitor.php:79`, `:102-112` | reuse | fail |
| H-11 | Zeitzone ist Ortszeit (nicht UTC/fester Versatz) | `Monitor.php:115-120` | abstract: Text neutral, kein „Berlin“ (INV-C-056) | warn |
| H-12 | Speicher-Selbsttest: private Ordner per Direkt-URL abrufbar? | `SystemStatus.php:216-270`, `:340-393` | reuse; nginx-Regel als Hinweis | `exposed` = fail, `unknown` = warn |
| H-13 | Privater Ordner außerhalb des Webroots | `SiteHealth.php:99` | reuse; nur „ja/nein“, kein Pfad | info (Backup-Hinweis R0-16) |
| H-14 | Privater Ordner erreichbar, letzter Umzug fehlerfrei | `Monitor.php:81` | reuse | fail |
| H-15 | Persistenter Objekt-Cache | `SiteHealth.php:165`, `:200` | reuse (R0-14) | info/warn nach Lastklasse |
| H-16 | Wartungs- und Fehlerseiten-Drop-ins (`maintenance.php`, `php-error.php`) | `SystemStatus.php:302-306` | reuse (Vorlage R1-24) | warn |
| H-17 | Client-IP dieser Anfrage, vertrauenswürdige Proxys | `SystemStatus.php:308` | reuse; IP **nur im Screen**, nie im Diagnosepaket | info |
| H-18 | Überwachungs-Endpunkt und Schlüssel gesetzt | `SystemStatus.php:300`, `Monitor.php:43-63` | reuse; **CC-Delta:** Schlüssel nur im Header `X-CC-Monitor-Key` (E-336, wie R0-19 R-50), nicht als `?key=` (landet in Server-Logs, INV-C-055) | ohne Schlüssel = warn |
| H-19 | PHP- und WP-Version gegen Matrix | `SystemStatus.php:193-194` | reuse (R0-14 Matrix) | außerhalb = warn |
| H-20 | Update-Prüfung | `SiteHealth.php:114`, INV-C-067 | replace: Free über WordPress.org, Pro über License-Captain-Client | info |
| H-21 | Backup-Prüfung (Schema, Optionen, Ablage, aktives Paket, Mediendateien, Grants-Stichprobe, laufende Vorgänge, Site-Identität) | `Infrastructure/BackupCheck.php`, INV-C-060…065 | reuse, Zeilen nach R0-16 §1.4 | wie R0-16 §1.4 |
| H-22 | Mail-Absender gesetzt | `SystemStatus.php:133` (Woo-gebunden) | abstract: eigener Absender (R1-21) | warn |
| H-23 | R3: WooCommerce-Status, Bestellabgleich letzter Lauf/Rückstand/Wiederholungen | `SystemStatus.php:181`, `:289-296` | R3-Adapter registriert eigene Prüfung | warn |
| H-24 | R3: Gastkauf, Lernprodukte, Bestätigungsmails | `SystemStatus.php:182-183`, `:178` | R3-Adapter | – |
| – | Steuer, Shop-Adresse, IBAN, Sprache, Zahlungskonflikte | `SystemStatus.php:21-160`, `:187-192` | **exclude** (E-33) | – |

**Neu in Course Captain** (Konzept 22.1, 43.2 „letzte Jobs, REST“, R0-16, R0-09):

| Nr. | Prüfung | Regel | Quelle |
|---|---|---|---|
| H-25 | Ausgangsmodus `on`/`catch`/`off` | dauerhaft sichtbar, auch in der Admin-Leiste; `off` in `production` = warn | R0-16 §3.1, E-86 |
| H-26 | Haltemodus nach Änderung der Site-Identität | aktiv = fail mit Knopf „bestätigen“ (#23) | R0-16 §2.3, E-85 |
| H-27 | Jobs nach Zustand (`queued`, `running`, `retry_wait`, `failed`, `dead_letter`) mit Anzahl, ältestem Eintrag, Job-ID, Fehlercode | `dead_letter` > 0 = fail; `running` mit abgelaufener Lease = warn | Konzept 22.1 |
| H-28 | Outbox-Rückstand (`dispatching` mit abgelaufenem `locked_until`, älteste offene Zeile) | > 1 h = warn | R0-16 §1.4, E-116 |
| H-29 | Grants `pending_*` über Timeout, Grants `failed` | Anzahl, älteste ID; `failed` > 0 = warn | R0-09 G-06, Konzept 31.7 |
| H-30 | Versuche `broken`; `in_progress` über Frist + Kulanz | `broken` > 0 = warn | R0-08 §6.2 |
| H-31 | Importe `importing`/`activating` ohne Heartbeat (> 15 min, Vorschlag) | warn mit Batch-ID | R0-16 §1.4, INV-C-102 |
| H-32 | REST-Erreichbarkeit `course-captain/v1` (Loopback) | fail | Konzept 43.2 |
| H-33 | Schlüssel `COURSE_CAPTAIN_SECRET_KEY` gesetzt, sonst Salt-Ableitung | nicht gesetzt = warn; **nie** Wert, nur „gesetzt/aus Konstante“ | R0-16 §3.2, E-87 |
| H-34 | Integrationen/Connectoren: Status nach Konzept-Erkennung (`not_installed` … `failed`), letzter Erfolg | `degraded`/`failed` = warn | R0-34 (Vertrag dort); in Free nur lesende Partnererkennung, Connector-Status mit Pro (E-38, E-185, E-254) |
| H-35 | Debug-Logging aktiv bis … | aktiv = info mit Restzeit | §2.3 |
| H-36 | Audit-Integrität (Hash-Kette der letzten 1 000 Einträge, täglich) | Bruch = fail | §4.3, E-139 |
| H-37 | Pro-Lizenz und `core_version`-Sperre | nur mit Pro | R0-36 L4/L5 |
| H-38 | Website-Härtung (R1-45): Zustand je Funktion (`off`/`auto`/`force`, noch nicht bestätigt), erkanntes Security-Plugin, Notausschalter `COURSE_CAPTAIN_HARDENING` aktiv, fail-closed-Ereignisse der letzten 24 h | nicht bestätigt = info mit Link in den Tab „Website-Härtung“; Notausschalter aktiv = warn; Zählerspeicher ausgefallen = warn | E-10, E-240, E-242, E-260, E-263, R0-31 |

**Monitor `GET course-captain/v1/monitor` (Alias `/health`, E-336):** Gesamtstatus 200/503, `Cache-Control: no-store`, Einzelprüfungen nur mit Schlüssel oder für #22 (LB `Monitor.php:88-99`, T175). Ein Ausfall (503) zählt nur bei H-02 (fällig), H-04, H-07, H-10, H-14, H-26, H-32. Alle anderen Prüfungen sind Hinweise.

### 2.2 Diagnosepaket-Export ohne PII und Secrets

**Zweck:** Der Betreiber gibt das Paket an Stufe 3 oder an den Herstellersupport (Stufe 4). Es ersetzt Bildschirmfotos und Admin-Zugänge. Konzept 43.2 („anonymisierter Diagnoseexport“), OPS-06, R1-38 („redigierter Diagnoseexport“).

**Erzeugung:** S-22 Knopf „Diagnosepaket erstellen“ (#22) oder `wp course-captain diagnostics --output=<datei>` (Safe-Export-Writer: außerhalb des Webroots, `0600`, `fopen 'x'`, LB `Commands.php:964-1009`). Ablauf:

1. Optionen wählen: Zeitraum der Fehlereinträge (Standard 72 h, höchstens 14 Tage), optional konkrete `request_id`/Job-ID/Batch-ID, optional „Debug-Log-Auszug anhängen“, optional „Domain im Klartext“ (Standard: gehasht, E-142).
2. **Vorschau** des vollständigen Inhalts im Browser. Die redigierten Stellen sind markiert, damit der Betreiber sieht, was das Haus verlässt.
3. Download als `course-captain-diagnose-<UTC>.json` (optional als ZIP mit `manifest.json`). Einmal-Link, gültig 1 h. Die Serverkopie im privaten Ordner wird nach 24 h gelöscht.
4. Audit `diagnostics_exported` mit Umfang (Optionen, Bytes, SHA-256), ohne Inhalt.

**Inhalt (Allowlist; was nicht hier steht, kommt nicht hinein):**

| Abschnitt | Felder | Redaktion |
|---|---|---|
| `manifest` | Paketformat-Version, Erzeugungszeit (UTC), Redaktionsregeln-Version, SHA-256 je Abschnitt, Installations-ID **als HMAC** (`course_captain_installation_uuid`), gewählte Optionen | – |
| `versions` | Course Captain Core/Pro, Schema, Vertrag, REST-API, Paketformat, PHP, WP, DB-Server (Typ + Version), Webserver-Software (Produkt, ohne Hostname) | – |
| `environment` | `WP_ENVIRONMENT_TYPE`, Multisite ja/nein, Locale, Zeitzone, `memory_limit`, `max_execution_time`, `upload_max_filesize`, `post_max_size`, Objekt-Cache-Typ, `DISABLE_WP_CRON`, System-Cron erkannt, HTTPS ja/nein, `home_url` **als HMAC** (Klartext nur per Option) | Pfade nie; nur „außerhalb Webroot ja/nein“ |
| `plugins` | aktive Plugins und Theme: Slug, Version, Netzwerk-aktiv; Must-Use-Plugins: Dateiname | keine Lizenzschlüssel |
| `health` | alle Prüfungen §2.1: `id`, `status`, Kurztext, Anzahl; IDs nur als interne UID | IP aus H-17 entfällt |
| `settings` | alle Settings mit Privacy-Klasse `public`/`internal` aus dem Settings-Register (Konzept 43.3) | Felder mit `secret: true` → `"<gesetzt>"`/`"<leer>"`/`"<konstante>"` (R0-16 §3.2); Felder mit Klasse `personal` (z. B. Absender-Adresse, Impressum) → `"<gesetzt>"` |
| `jobs` | letzte 200 Jobs mit Zustand ≠ `succeeded` im Zeitraum: Job-ID, Typ, Zustand, Versuche, nächster Versuch, Fehlercode, Fehlerklasse, gekürzte Meldung, `correlation_id` | Job-Argumente nur als **Objekttyp + UID**, nie `user_id` im Klartext (HMAC) |
| `errors` | strukturierte Fehlereinträge (§2.3) im Zeitraum: Zeit, `request_id`, Route/Hook, Fehlercode, Klasse, `datei:zeile` (nur `basename`), gekürzte Meldung | Scrubber (unten) |
| `imports` | Batches der letzten 30 Tage: UID, Status, Schritt, Cursor, Heartbeat-Alter, Anzahl Items je Ergebnis, Fehlercodes der Items | keine Inhalte, keine Fragen-/Lektionstexte, keine Dateinamen aus dem Paket außer Hash |
| `outbox` / `inbox` | Zähler je Zustand und Eventtyp, älteste offene Zeile, letzte Fehlercodes je Connector | keine Payloads |
| `counts` | Kurse, Lektionen, Fragen, aktive Grants, aktive Versuche, Gruppen; jeweils Größenklasse (`<100`, `<1 000`, `<10 000`, `≥10 000`) statt exakter Zahl für Lernende | – |
| `schema` | Tabellen, Engine, Zeilenzahl-Klasse, Indizes, `course_captain_fk_issues`, Migrationshistorie | – |
| `debug_log` (optional) | Auszug des Debug-Logs im Zeitraum | Scrubber; höchstens 2 MB |

**Nie im Paket:** Namen, E-Mail-Adressen, Logins, WP-User-IDs im Klartext, IP-Adressen, User-Agents, Antworten, Snapshots, Abgaben zu Aufgaben (Text und Dateien, E-168), Kurs- und Frageninhalte, Zertifikatsdaten, Bestell-/Zahlungsdaten, Begründungstexte aus dem Audit, Audit-Einträge selbst, Secrets, Token, Nonces, Cookies, Hashes von Token, Lizenzschlüssel, absolute Pfade, Ordnernamen mit Geheimnis, Domain im Klartext (Standard).

**Scrubber (zweite Linie für Freitext in Meldungen):** E-Mail → `<email>`, IPv4/IPv6 → `<ip>`, `Bearer …`/JWT/Hex- und Base64-Folgen ≥ 32 Zeichen → `<token>`, absolute Pfade → `<abspath>/…`/`<private>/…`, `protected-<geheim>`/`packages-<geheim>` → `protected-<…>`, URL-Query-Parameter → `?<redacted>`, IBAN-Muster → `<iban>`, Zahlen ≥ 6 Stellen nach `user`/`order` → `<id>`. Der CI-Test SUP-017 füttert bekannte Geheimnisse und Personendaten ein und verlangt null Treffer im Ergebnis (analog REC-022).

**Pseudonyme:** HMAC-SHA-256 mit einem eigenen Schlüssel je Paket (`diag_salt`, nicht gespeichert). Damit sind IDs **innerhalb** eines Pakets vergleichbar, aber nicht paketübergreifend und nicht auf der Site rückrechenbar. Will der Betreiber einen Fall gemeinsam mit dem Hersteller nachverfolgen, nennt er die `request_id`. Sie ist zufällig und enthält keine Personendaten.

### 2.3 Debug-Logging-Regeln

| Nr. | Regel | Begründung |
|---|---|---|
| DL-1 | **Standard aus.** Einschalten im Einstellungs-Tab „Erweitert“ (#19) oder über `wp course-captain debug on --for=2h` (#23). Dauer 15 min bis höchstens 24 h, Standard 2 h. Danach schaltet sich das Logging selbst ab (Prüfung bei jeder Anfrage und im Stundenlauf). | Konzept 43.3 „Debug aus; zeitlich begrenzt einschaltbar; Ablauf erzwingen“ |
| DL-2 | Ein- und Ausschalten erzeugt Audit `debug_enabled`/`debug_disabled` (Akteur, Dauer, Stufe, Begründung). H-35 zeigt die Restzeit. | Nachvollziehbarkeit |
| DL-3 | Stufen `error` (immer aktiv, auch ohne Debug), `warning`, `info`, `debug`. Nur `error` und `warning` laufen dauerhaft, und zwar in die Fehlertabelle bzw. Job-Liste mit Retention 30 Tage (E-143). `info`/`debug` nur im Debug-Zeitfenster. | Datensparsamkeit |
| DL-4 | Format: eine JSON-Zeile je Ereignis mit `ts`, `level`, `request_id`, `correlation_id`, `component`, `code`, `message`, `context` (Allowlist-Schlüssel je Komponente). Kein `var_export`/`print_r` ganzer Objekte. | maschinenlesbar, redigierbar |
| DL-5 | Ablage im **privaten Ordner** (`<private>/logs/cc-debug-YYYYMMDD.log`, nie im Webroot), Rotation bei 5 MB, höchstens 5 Dateien, Löschung nach 7 Tagen (E-143). Kein Download über eine öffentliche URL. Die Einsicht im Systemstatus erfordert #23. | LB schreibt in `error_log` des Servers (`Cron.php:49`), Rotation dort unkontrolliert |
| DL-6 | **Nie loggen:** Antworten und Antwortoptionen, Snapshots, Passwörter, Token und ihre Hashes, Nonces, Cookies, Secrets, vollständige Request-Bodies, Zahlungsdaten. E-Mail-Adressen nur maskiert (`m***@d***.tld`), IP-Adressen gekürzt (/24 bzw. /48), User-IDs nur als HMAC mit Site-Schlüssel. | Konzept 22.1 „keine persönlichen Antworten in Logs“, Kap. 25 „PII/Secrets maskiert“ |
| DL-7 | Die Scrubber aus §2.2 laufen **beim Schreiben**, nicht erst beim Export. | Defense in depth |
| DL-8 | Fehlermeldungen an Nutzer: stabiler Fehlercode und `request_id`, keine Stacktraces, keine SQL-Texte, keine Pfade (R0-19 Fehlerkatalog). Der Stacktrace steht nur im Log (`error`). | LB `LearnerController.php:463` schreibt Datei und Zeile ins Server-Log; CC zusätzlich mit `request_id` |
| DL-9 | `WP_DEBUG`/`WP_DEBUG_DISPLAY` beeinflussen das CC-Logging nicht. Mit `WP_DEBUG_DISPLAY = true` in `production` meldet Site Health eine Warnung. | Schutz vor Ausgabe im Frontend |
| DL-10 | Staging- und Testumgebungen dürfen `debug` dauerhaft aktivieren (Konstante `COURSE_CAPTAIN_DEBUG`), aber nur, wenn `WP_ENVIRONMENT_TYPE` ≠ `production`. In `production` wirkt die Konstante wie DL-1 und läuft nach 24 h ab. | Staging-Abnahme (R0-16 §3.1) |

---

## 3. Datenkorrektur-Aktionen

**Gemeinsamer Ablauf jeder Aktion** (R0-07 §4.3 und Konzept 43.2 UX-Vertrag):

1. Cap- und Meta-Cap-Prüfung, dann Nonce je Aktion und Objekt (`ccap_<aktion>_<id>`).
2. **Bestätigungsseite** mit Wirkung in Klartext („Zugang zu Kurs X für dieses Konto endet sofort“), Anzahl der Betroffenen und Undo-Hinweis.
3. **Pflichtbegründung:** Auswahl `reason_code` (je Aktion fester Katalog plus `other`) **und** Freitext von mindestens 10 Zeichen (Ticketnummer empfohlen, GR-4). Die CLI verlangt `--reason`.
4. Ausführung in **einer** Transaktion mit CAS (R0-09 §2.4), Fachereignis (z. B. `ccap_grant_event`), **Audit-Eintrag** und Eintrag in die **interne Outbox**, wenn es Außenwirkung hat (Mail; E-38/E-182). Öffentliche Course-Events entstehen daraus nur mit Pro.
5. Cache und Policy-Memo invalidieren (R0-07 P-11).
6. Benachrichtigung nach Spalte „Lernender“ (Defaults nach E-136). Im Ausgangsmodus `catch`/`off` oder im Haltemodus wird sie zurückgehalten (R0-16 §3.1).
7. Idempotenz: `request_key` aus dem Formular. Doppelt abgeschickt ergibt keine zweite Wirkung.

**Undo-Grundsatz:** Course Captain löscht bei einer Korrektur keine fachlichen Datensätze. Jede Rücknahme ist eine **neue** Aktion mit eigenem Audit. Endzustände (`revoked`, `expired`, Zertifikat `revoked`) leben nie wieder auf (E-29, R0-09 §2.3). Es entsteht dann eine neue Revision.

### 3.1 Aktionskatalog

| Nr. | Aktion | Cap / Meta-Cap | Pflichtbegründung | Audit-Aktion (+ Fachhistorie) | Undo | Lernender wird informiert (Default, E-136) |
|---|---|---|---|---|---|---|
| K-01 | **Grant gewähren** (manuell) | #9, M-14; Nutzer muss auf der Site existieren | ja (`support_case`, `goodwill`, `migration`, `source_outage`, `other`) | `grant_manual_created` (+ `ccap_grant_event` G-03 `manual_grant`, Outbox `grant.created`/`grant.activated`) | K-03 Widerruf (`manual_revoke`); kein Löschen | **Mail** „Zugang freigeschaltet“ (abwählbar im Dialog) + „Mein Lernen“ |
| K-02 | **Grant verlängern / Enddatum ändern** | #9, M-13 | ja | `grant_extended` (+ G-14 `extended`) | erneute Änderung des Enddatums; nach Ablauf nur neue Revision (G-15) | Mail aus, Anzeige „aktiv bis …“ |
| K-03 | **Grant entziehen** (widerrufen) | #9, M-13; externe Quelle: widerrufen ja, löschen nein | ja (`fraud`, `duplicate`, `wrong_course`, `support_case`, `other`) | `grant_manual_revoked` (+ G-12 `manual_revoke`, Outbox `grant.revoked`, `access.changed`) | **kein** Aufheben (Endzustand); erneuter Zugang = K-01 bzw. G-15 `reactivated` als neue Revision | **keine Mail**; „Mein Lernen“ zeigt „beendet“ mit Supportweg |
| K-04 | **Grant aussetzen / freigeben** | #9, M-13 | ja | `grant_manual_suspended` / `grant_manual_released` (+ G-08/G-09) | Gegenaktion; manuelle Sperre nur manuell lösbar | keine Mail; Anzeige „vorübergehend nicht verfügbar“ |
| K-05 | **Zugang übertragen** (E-114, E-114) | #9, M-13 auf den Quell-Grant **und** M-14 auf den Kurs für das Ziel; Ziel ≠ Quelle, Ziel ohne Lernsperre | ja (`wrong_account`, `account_change`, `other`) | `grant_transferred` mit `subject_user_id` = Quelle und `context.target_subject` = Ziel; zwei Einträge, einer je Konto (+ G-12 `transferred`, neuer Grant `source_revision + 1` G-03) | Rückübertragung (erneut K-05); Historie, Fortschritt, Versuche, Zertifikate bleiben beim Quellkonto (R0-09 §1.3) | **Ziel: Mail** „Zugang freigeschaltet“; **Quelle: keine Mail**, Anzeige „auf ein anderes Konto übertragen“ ohne Angabe des Ziels (GR-5) |
| K-06 | **Fehlgeschlagenen Grant erneut verarbeiten** (Retry `failed`) | #9, M-13 | ja (kurz, `retry_after_fix`) | `grant_retry_requested` (+ G-13 `retry`, gleicher Idempotenzschlüssel) | – (Ergebnis folgt der Quelle) | wie die Folgeaktivierung (Mail „freigeschaltet“ nur bei erster Aktivierung) |
| K-07 | **Versuch nachauswerten** (`broken` → `evaluated`) | #16 + M-12; Ursache behoben (H-30 grün für diesen Versuch) | ja | `attempt_reevaluated` (+ Session-Zustandswechsel, `evaluation_no`) | – (Ergebnis ist neue Auswertung; spätere Nachbewertung nach E-74) | Anzeige des Ergebnisses; Zertifikat-Mail nach normalem Ablauf |
| K-08 | **Versuch als defekt verwerfen** (`broken` oder `in_progress` → `cancelled`, `end_reason = support_void`) | #16 + M-12 | ja (`technical_failure`, `outage`, `other`) | `attempt_voided` | – (Endzustand); Ersatz über K-09 | **Mail** „Prüfungsversuch wurde wegen eines technischen Fehlers nicht gewertet“ + Hinweis auf Zusatzversuch |
| K-09 | **Zusatzversuch gewähren** (ein Versuch zählt nicht gegen `max_attempts`) | #16 + M-12 | ja | `attempt_allowance_granted` (Anzahl, Bezug auf Versuch) | Entzug, solange nicht verbraucht (`attempt_allowance_revoked`) | Anzeige „1 zusätzlicher Versuch“; Mail aus |
| K-10 | **Versuch annullieren** (`evaluated` → Nachweis `revoked`, z. B. Betrug) | #16 + #17 bei Zertifikat | ja | `attempt_annulled` (+ ggf. `certificate_revoked`) | keine Rücknahme; Nachbewertung E-74 nur fachlich | Mail **an**, sachlich (Konzept 35.4: Widerruf mit Grund) |
| K-11 | **Fortschritt zurücksetzen** (Kurs, Activity oder Frage, E-106) | #16, M-12 (eigener Fortschritt: `read`, falls Kurs erlaubt) | ja (bei Selbstreset nicht) | `progress_reset` mit Umfang und Anzahl der Zeilen; Versuche und Zertifikate bleiben (R0-23 §5) | **Undo innerhalb von 30 Tagen** aus dem Reset-Journal, solange danach kein neuer Fortschritt entstand (E-138) | Mail aus; Hinweis in „Mein Lernen“ („Fortschritt am … zurückgesetzt“) |
| K-12 | **Lernsperre setzen / aufheben** (E-79) | #18, M-24; nicht gegen höhere Caps, nicht sich selbst | ja (`abuse`, `account_compromised`, `legal_hold`, `other`) | `learner_locked` / `learner_unlocked`; optional `sessions_terminated` (E-79) | Aufheben | **keine Mail** (oft Missbrauch oder kompromittiertes Konto); Meldung `ccap_account_locked` mit Supportweg |
| K-13 | **Import zurückrollen** | #6 + M-21, für die Aktivierung zusätzlich #3 | ja (`content_error`, `rights_issue`, `other`) | `import_rollback_requested`, `import_rolled_back` (neuer Batch aus Paketkopie, R0-10 §3.6) | Rollback auf den vorherigen Stand (neuer Batch); nichts wird gelöscht | keine; laufende Versuche bleiben auf ihrem Snapshot (R0-08 §6.3); „Erneut bearbeiten“ nur nach E-105 |
| K-13a | **Import fortsetzen / verwerfen** | #6 + M-21 | Fortsetzen nein, Verwerfen ja | `import_resumed` / `import_discarded` | Verwerfen ist endgültig für den Batch; neues Hochladen möglich | keine |
| K-14 | **Reconcile** (Abgleich, §3.2) | #23; CLI als Systemakteur | ja bei `--apply` | `reconcile_previewed` (nur CLI/UI-Vorschau, ohne Begründung), `reconcile_applied` mit Zählern je Abweichungsart; jede Einzeländerung schreibt ihr eigenes Fachereignis (`actor_type = system:reconcile`, `correlation_id` des Laufs) | keine Sammelrücknahme; jede Einzeländerung ist per K-01…K-04 umkehrbar | **keine Mails** aus dem Reconcile (Konzept 22.2: keine Doppelwirkung); Anzeige in „Mein Lernen“ |
| K-15 | **Zertifikat korrigieren / widerrufen** | #17, M-19 | ja | `certificate_corrected` (neue `revision_no`) / `certificate_revoked` | Korrektur: erneute Korrektur; Widerruf: Endzustand, Neuausstellung = neues Zertifikat | Korrektur: Mail mit neuem PDF-Link; Widerruf: Mail mit Grund |
| K-16 | **Job erneut ausführen / Dead-Letter verwerfen** | #23 | Retry nein, Verwerfen ja | `job_retried` / `job_discarded` (Job-ID, Typ, Fehlercode) | – | keine |
| K-17 | **Haltemodus bestätigen** (gleicher Ort / Umzug / Kopie) | #23 | ja | `site_identity_confirmed` (Wahl, alte und neue Identitäts-Hashes) | erneute Wahl | keine; zurückgehaltene Mails nach E-88 |

**Lesen fremder Versuchsdaten** ist keine Korrektur, aber begründungspflichtig (E-82, E-82): Beim Öffnen von S-11 fragt das Formular einen `reason_code` (`support_case`, `grading`, `appeal`, `fraud_check`) und einen Freitext ab. Audit `attempt_details_viewed`. Zugewiesene Trainer bewerten ohne Begründung, aber mit Audit.

**Nicht vorgesehen:** Grants oder Versuche löschen, Grant-Status direkt setzen, Fortschritt auf einen beliebigen Wert setzen, Konten zusammenführen (E-114), Antworten eines Lernenden ändern.

### 3.2 Reconcile in R1 (neutral) und R3 (Woo)

LB kennt nur den Woo-Bestellabgleich: stündlich, Cursor über das Änderungsdatum, 100 Bestellungen je Lauf, 5 Wiederholungen je Fehler, 5 min Karenz, erster Lauf 48 h rückwirkend, Lock (`AutoReconcile.php:26-50`); dazu die CLI `reconcile [--apply] [--order] [--since] [--yes]` mit Vorschau als Standard und Rückfrage vor Widerrufen (`Commands.php:353-414`). Dieses Muster übernimmt Course Captain für **interne** Abgleiche in R1. Der Woo-Teil folgt als Adapter in R3 (INV-C-168).

| Abgleich | prüft | Korrektur (nur mit `--apply` bzw. Knopf) | Phase |
|---|---|---|---|
| RC-1 Seats ↔ Seat-Grants | belegter Platz ohne aktiven Grant; Grant `group_seat` ohne belegten Platz; Gruppenstatus ≠ Grant-Status | Grant nach R0-09 §4.5 anlegen, aussetzen oder widerrufen | R1 |
| RC-2 Grants ↔ Zeit | `active` mit `valid_until` < jetzt (kosmetisch, G-10) | auf `expired` setzen | R1 |
| RC-3 Pending/Failed | `pending_*` über Timeout; `failed` mit temporärem Fehlercode | G-06 bzw. G-13 | R1 |
| RC-4 Prüfungssitzungen | `in_progress` über Frist + Kulanz | Auswertung `expired` (wie Cron) | R1 |
| RC-5 Outbox | `dispatching` mit abgelaufener Lease | zurück in `queued`; Empfänger dedupliziert per `event_uuid` | R1 |
| RC-6 Importe | `importing` ohne Heartbeat und ohne lebende Sperre | als `aborted` markieren (danach K-13a) | R1 |
| RC-7 Fortschritt ↔ Kursrevision | Kursabschluss fehlt, obwohl alle Pflicht-Activities erfüllt sind (E-111) | Abschluss nachtragen, **ohne** Mail (Zertifikat nach Kurseinstellung, Versand nach E-88-Regel) | R1 |
| RC-8 Woo-Bestellungen ↔ Grants | wie LB `OrderSync` | Adapter | R3 |

**Regeln:** Vorschau ist Standard. `--apply` verlangt eine Begründung. Lock je Abgleich, Batchgröße und Cursor wie LB. Kein Abgleich versendet Mails oder stellt Zertifikate aus (Konzept 22.2). Nach einem Restore läuft der Abgleich erst, wenn der Haltemodus bestätigt ist (R0-16 §2.3).

---

## 4. Audit

### 4.1 Ereigniskatalog

Namensschema `<bereich>_<ereignis>`, Vergangenheitsform, nur `[a-z0-9_]`, höchstens 64 Zeichen (LB `AuditPage.php:105`). **B** = Begründung Pflicht. Die Bereiche entsprechen dem LB-Filter „Ereignis“ (`AuditPage.php:27-40`), ohne die Shop-Bereiche.

| Bereich | Aktionen | B |
|---|---|---|
| **grant** (11) | `grant_manual_created`, `grant_extended`, `grant_manual_suspended`, `grant_manual_released`, `grant_manual_revoked`, `grant_transferred`, `grant_retry_requested`, `grant_manual_approved` (Vorabfreigabe R0-09 §2.3), `grant_note_added`, `grant_idempotency_conflict`, `grants_closed_account_deleted` | alle außer den letzten zwei |
| **group/seat** (10) | `group_created`, `group_status_changed`, `group_seats_changed`, `group_owner_changed`, `group_reinstated`, `group_token_rotated`, `seat_invited`, `seat_claimed`, `seat_removed`, `group_invites_expired` | Admin-Änderungen an fremden Gruppen |
| **learner** (5) | `learner_locked`, `learner_unlocked`, `sessions_terminated`, `progress_reset`, `progress_reset_undone` | ja |
| **attempt** (8) | `attempt_details_viewed`, `attempt_graded`, `attempt_reevaluated`, `attempt_voided`, `attempt_annulled`, `attempt_allowance_granted`, `attempt_allowance_revoked`, `attempt_counter_reset` (E-107) | ja (`attempt_graded` nur Audit) |
| **certificate** (5) | `certificate_issued_manual`, `certificate_corrected`, `certificate_revoked`, `certificate_review_required` (R0-23), `certificate_reissued` | ja (außer `review_required`) |
| **content/rights** (8) | `course_published`, `course_archived`, `course_enrollment_closed`, `rights_override_set`, `rights_bulk_release`, `access_rule_saved`, `access_rule_deleted`, `override_<feld>` | Rechte und Sammelfreigaben ja |
| **import** (13) | `import_uploaded`, `import_validated`, `import_validation_failed`, `import_previewed`, `import_approved`, `import_started`, `import_aborted`, `import_resumed`, `import_activated`, `import_discarded`, `import_rollback_requested`, `import_rolled_back`, `import_retention` (R0-10 §3.6, LB `AuditPage.php:457-466`) | Freigabe, Rollback, Verwerfen |
| **staff/roles** (4) | `staff_assigned`, `staff_removed`, `role_caps_changed`, `export_created` (Inhalt/Bericht/Grants) | nein (Export: Zweck) |
| **privacy** (9) | `personal_data_exported`, `personal_data_erased`, `privacy_export_sent`, `privacy_export_failed`, `account_deleted`, `account_deletion_skipped`, `account_deletion_failed`, `account_removed_from_site`, `audit_pseudonymized` (LB `AuditPage.php:468-475`) | nein (WP-Antrag ist der Beleg) |
| **ops** (11) | `diagnostics_exported`, `audit_exported`, `audit_pruned`, `debug_enabled`, `debug_disabled`, `job_retried`, `job_discarded`, `reconcile_applied`, `maintenance_action`, `site_identity_confirmed`, `held_outbound_released` (E-88) | `audit_exported`, `debug_enabled`, `job_discarded`, `reconcile_applied`, `maintenance_action`, `site_identity_confirmed`, `held_outbound_released` |
| **settings/integrations** (4) | `settings_saved` (Tab, geänderte Schlüssel, Secret-Felder nur „geändert“), `integration_saved`, `integration_secret_rotated`, `webhook_rejected` (gedrosselt, R0-07 §3.6) | nein |

Summe: 88 Aktionen. **Nicht im Audit**, weil die Fachhistorie sie trägt: automatische Grant-Übergänge aus Quellen (nur `ccap_grant_event`), Lernereignisse, normale Prüfungsabgaben. LB-Aktionen für Shop, Preise, Rabatte, Zahlungskonflikte, Seekarten und Prüfungsausschüsse entfallen (exclude).

### 4.2 Felder

Basis ist `ccap_audit_log` (R0-06 E-47, LB `Audit/AuditLog.php:13`, Schema INV-C-042). **Delta an R0-06:**

| Feld | Typ (Vorschlag) | Inhalt | neu |
|---|---|---|---|
| `id` | `bigint unsigned` PK | fortlaufend | – |
| `created_at` | `datetime` (UTC) | Zeitpunkt | – |
| `actor_type` | `varchar(24)` | `user`, `system:cron`, `system:cli`, `system:reconcile`, `connector:<id>` (R0-07 P-12) | **ja** |
| `actor_user_id` | `bigint NULL` | WP-User-ID; bei CLI mit `--user` diese ID | – |
| `subject_user_id` | `bigint NULL`, Index | **betroffene Person** (Lernender). Ersetzt die LB-Suche per `LIKE` in `context.user_id`/`from_user` (`AuditPage.php:206`) und dient Export und Eraser | **ja** |
| `action` | `varchar(64)`, Index | Katalog §4.1 | – |
| `object_type`, `object_id` | `varchar(32)`, `varchar(191)` | Objekt als **UID** (`grant_uid`, `batch_uid` …); feste Formate gegen die `%d`-Falle (LB `AuditLog.php:24-26`) | – |
| `reason_code` | `varchar(32) NULL` | Katalogwert je Aktion | **ja** |
| `reason` | `text NULL` | Freitext der Begründung (GR-4) | – |
| `context` | `longtext NULL` (JSON) | nur Allowlist-Schlüssel je Aktion: Vorher/Nachher-Werte fachlicher Felder, Zähler, Bezug-UIDs; **keine** E-Mail, IP, Antworten, Secrets | – |
| `correlation_id` | `char(36) NULL`, Index | verbindet Audit, Grant-Event, Outbox, Job und Log | **ja** |
| `request_id` | `char(26) NULL` | Anfrage, die die Aktion ausgelöst hat (GR-7) | **ja** |
| `row_hash` | `binary(32)` | SHA-256 über `prev_hash`‖kanonisierte Zeile (E-139) | **ja** |

Anzeige wie in LB: Akteur nur mit Anzeigenamen, ohne E-Mail; gelöschte Konten als „Nutzer <ID> (gelöscht)“ bzw. nach Pseudonymisierung „Nutzer (gelöscht)“ (`AuditPage.php:329-343`). „System (automatisch)“ steht für `actor_type ≠ user`.

### 4.3 Unveränderbarkeit

| Regel | Umsetzung |
|---|---|
| **Append-only** | Der Audit-Service kennt nur `record()`. Es gibt keine Update- oder Delete-Methode und keine REST-, AJAX- oder CLI-Route zum Ändern. |
| **Erlaubte Ausnahmen (abschließend)** | (1) Prune nach Retention (§4.4), je Lauf begrenzt (LB 5 000), mit Sammel-Eintrag `audit_pruned` (Anzahl, ältestes/jüngstes Datum). (2) Eraser: `actor_user_id`/`subject_user_id` → `NULL`, personenbezogene Kontextschlüssel → HMAC (LB `PersonalData.php:366` `JSON_SET` mit sha256), Eintrag `audit_pseudonymized`. Begründungstexte des Betroffenen-Falls werden auf `"<pseudonymisiert>"` gesetzt. |
| **Hash-Kette** (E-139) | `row_hash = SHA-256(prev_row_hash ‖ kanonisches JSON der Zeile ohne row_hash)`. Prune und Pseudonymisierung schreiben einen **Ankereintrag** mit dem neuen Startwert. Prüfung H-36 täglich, im Export und in `wp course-captain audit verify`. Die Kette **erkennt** Änderungen außerhalb des Plugins (SQL, Tabellen-Editor, Teil-Restore). Gegen Angreifer mit DB-Schreibrecht schützt sie nicht vollständig, sie macht Manipulationen aber sichtbar. |
| **Restore** | Ein DB-Restore rollt das Audit mit zurück (R0-16 §1.3, E-92). Nach einem bestätigten Haltemodus schreibt Course Captain `site_identity_confirmed` mit dem Hash der letzten Zeile vor der Erkennung. |
| **Kein Audit im Cache** | Schreiben erfolgt synchron in derselben Transaktion wie die Fachaktion. Scheitert das Audit, scheitert die Aktion (fail-closed für Korrekturaktionen). |

### 4.4 Aufbewahrung (E-44)

| Daten | Default | Änderbar | Löschlauf | Quelle |
|---|---|---|---|---|
| `ccap_audit_log` | **4 Jahre** | Setting (Datenschutz & Sicherheit), Minimum 1 Jahr | stündlich `ccap_audit_prune`, Index auf `created_at`, höchstens 5 000 je Lauf | E-44, LB `PersonalData.php:100-108`, `betrieb-entscheidungen.md` |
| Grant-Historie `ccap_grant_event` gelöschter Konten (Kaufquellen) | 10 Jahre, pseudonymisiert | Setting | täglich, höchstens 1 000 | E-44, R0-09 §7 |
| Import-Protokoll | „unverändert“ 14 Tage, übrige Zeilen 180 Tage | Setting | stündlich | E-44, LB T40 |
| Inbox/Outbox | 90 Tage | Setting | stündlich | E-116, E-215 |
| Fehlertabelle / Job-Fehler (`error`, `warning`) | 30 Tage | Setting | stündlich | **neu**, E-143 |
| Debug-Log-Dateien | 7 Tage | nein (Maximum) | Stundenlauf | **neu**, E-143 |
| Diagnosepaket (Serverkopie) | 24 h | nein | Stundenlauf | §2.2 |
| Reset-Journal (K-11), Tabelle `ccap_reset_journal` (E-296) | 30 Tage | nein | täglich | E-138, E-296 |

Die juristische Bestätigung der Fristen liegt beim Betreiber (E-44). Die Protokollansicht nennt die eingestellte Frist im Kopf (LB `AuditPage.php:50`).

### 4.5 Einsicht und Export

| Weg | Wer | Umfang |
|---|---|---|
| **Protokollansicht S-21** (E-19, Free) | #21: Admin, Support | Filter wie LB (`AuditPage.php:102-136`): Zeitraum, Bereich oder Aktion, Akteur oder betroffene Person (ID, E-Mail, Login; gelöschte Konten per Nummer), Objekt (Grant, Gruppe, Versuch, Zertifikat, Import) + ID, zusätzlich `correlation_id`/`request_id`. 50 je Seite. Keine E-Mail- oder IP-Spalte. |
| **Objekthistorie** (Kasten in Grant-, Gruppen-, Lernenden-, Zertifikats- und Import-Detail) | wer das Objekt sehen darf (#13 bzw. Objekt-Cap), also auch Manager ohne #21 | nur Einträge zu diesem Objekt, höchstens 50, wie LB `historyBox` (`AccessPages.php:219`, `:566`) |
| **Audit-Export** | #21 **und** #19 (Admin), Begründung Pflicht, E-140 | CSV oder JSON, gefilterte Menge, Begründungstexte nur per Option, Prüfsumme und Hash-Kettenanker im Kopf; Safe-Export-Writer; Audit `audit_exported` |
| **Betroffenenauskunft** | WP-Exporter (M-23 bzw. `export_others_personal_data`) | Einträge mit `subject_user_id` = Person: Aktion, Zeit, Objekt, `reason_code`. Freitext-Begründung nur, wenn sie die Person selbst betrifft. Akteur nur als Rolle („Support“), nicht als Name (Schutz der Mitarbeitenden, R0-31 zu bestätigen) |
| **CLI** | Systemakteur, mit `--user` die Caps dieses Nutzers | `wp course-captain audit list|verify|export` mit denselben Filtern |

---

## 5. Eskalation und typische Supportfälle

### 5.1 Eskalationspfade

```
Stufe 0 Selbsthilfe ──► Stufe 1 Support ──► Stufe 2 Kursmanager ──► Stufe 3 Betreiber-Admin ──► Stufe 4 Hersteller
                          │                    (Fachdaten)              (Technik, Reconcile,       (nur Diagnosepaket,
                          │                                              Jobs, Debug)               request_id, Versionen)
                          ├─► Datenschutzrolle (Export/Löschung, WP-Kern)
                          └─► Sicherheitsvorfall: sofort Stufe 3 + Security-Disclosure des Herstellers (R0-18)
```

| Auslöser | von → an | Übergabe (Pflichtinhalt) |
|---|---|---|
| Korrektur an Grant, Versuch, Fortschritt, Zertifikat, Import nötig | 1 → 2 | Ticketnummer, betroffene Konto-ID, Objekt-UID, beobachteter Status, gewünschte Aktion (K-Nr.), Nachweis der Identitätsprüfung (GR-1) |
| Systemstatus `fail`, `dead_letter`, `broken` mit unklarer Ursache, Haltemodus, wiederholtes Scheitern eines Jobs, Verdacht auf Datenfehler über mehrere Konten | 1/2 → 3 | Prüfungs-IDs (H-xx), Job-/Batch-ID, `request_id`, Zeitpunkt |
| Fehler im Produkt (reproduzierbar, Fehlercode ohne dokumentierte Lösung, Migrationsfehler, Integritätsbruch H-36) | 3 → 4 | Diagnosepaket (§2.2), Schritte zur Reproduktion **ohne** Personendaten, betroffene Versionen |
| Datenschutzanfrage per Mail | 1 → Datenschutzrolle | Eingangsdatum, Antragsart; Durchführung über WP-Werkzeuge (LB `support-ablaeufe.md` §3) |
| Verdacht auf kompromittiertes Konto oder Missbrauch | 1 → K-12 Lernsperre, dann 3 | Grund, Zeitpunkt; keine Mail an das Konto |
| Sicherheitslücke, Datenabfluss | jede Stufe → 3 sofort | kein Diagnosepaket mit Details an Dritte vor Bewertung; Herstellerkontakt über Security-Kanal |

**Fristen:** Course Captain gibt keine SLA vor (Konzept 36 „Support-/Timeout-SLAs“ offen). R1-24 nennt eine **Empfehlung** für Betreiber: Stufe 1 werktäglich, Prüfungsabbrüche am selben Werktag, Sicherheitsvorfälle sofort.

### 5.2 Format der Runbooks

Kurzform je Fall: **Symptom → Prüfen (Stufe 1, nur lesend) → Ursache und Aktion (Stufe/K-Nr.) → Eskalation → Abschluss.** Die Betreiberfassung mit Screenshots und Textbausteinen schreibt R1-24 (LB-Struktur `support-ablaeufe.md`: „Was die Software tut“, „Ablauf“, „Textbaustein“).

### 5.3 Runbooks

#### RB-SUP-1 · Lernender hat keinen Zugang trotz Kauf oder Einladung

| Schritt | Inhalt |
|---|---|
| **Symptom** | „Kurs nicht freigeschaltet“, „Zugang wird aktiviert“ hängt, Kurs fehlt in „Mein Lernen“ |
| **Prüfen** (St. 1) | 1. Identität (GR-1). 2. Lernende › Detail: **effektiver Zugang** für den Kurs mit Grund des `PolicyService`. 3. Lernsperre? Rolle auf der Site (`read`)? 4. Grants zum Kurs: Status und Quelle. 5. Falsches Konto? (Kauf-/Einladungsadresse ≠ Kontoadresse, LB „Falsches Konto“). 6. Kurs veröffentlicht, Einschreibung offen, Drip/Scope. 7. H-29 (pending/failed), H-27 (Jobs), H-34 (Connector). |
| **Ursache → Aktion** | `pending_confirmation` → Quelle offen (Zahlung, Zustimmung, R3); **kein** manueller Grant, sondern Quelle klären bzw. Vorabfreigabe `grant_manual_approved` (St. 2) · `failed` → Ursache aus Fehlercode, dann **K-06 Retry** (St. 2) · gar kein Grant, Quelle vorhanden → **K-14 Reconcile** (St. 3; R3: Woo-Abgleich) · Grant beim falschen Konto → **K-05 Zugang übertragen** (St. 2) · Einladung abgelaufen/Plätze voll → Gruppenbesitzer lädt neu ein (St. 0) · `suspended` → Grund aus der Historie; Zahlungskonflikt über die Quelle, manuelle Sperre K-04 · `expired` → Verlängerung über die Quelle bzw. K-02/G-15 · Lernsperre → nach Prüfung K-12 aufheben · **Letzter Ausweg** K-01 manueller Grant mit Enddatum und Begründung mit Quellreferenz, nur wenn die Quelle nachweislich nicht mehr liefern kann (Doppelgrant-Risiko: ein späterer Refund der Quelle beendet den manuellen Grant nicht). |
| **Eskalation** | `pending_*` über Timeout bei mehreren Konten oder Connector `degraded` → St. 3; Fehlercode ohne Lösung → St. 4 mit Diagnosepaket (Abschnitte `jobs`, `outbox`, `health`) |
| **Abschluss** | Lernender sieht Status „aktiv bis …“; Textbaustein „Zugang freigeschaltet“; Ticket mit Audit-`correlation_id` |

#### RB-SUP-2 · Prüfung abgebrochen

| Schritt | Inhalt |
|---|---|
| **Symptom** | Browserabsturz, Verbindungsabbruch, „Zeit abgelaufen“, Fehlermeldung bei Abgabe, Ergebnis fehlt |
| **Prüfen** (St. 1, **ohne** Antworten) | Versuchsliste: Status, Start, Frist, `end_reason`, Punkte. `request_id` aus der Fehlermeldung. H-30, H-07 (Cron), Störungszeitraum im Systemstatus (Fehler und Jobs zur Uhrzeit). |
| **Ursache → Aktion** | `in_progress`, Frist läuft → Lernender setzt fort (Wiederaufnahme R1-20), keine Aktion · `evaluated`/`expired` ohne technische Störung → Ergebnis erklären, keine Korrektur · `evaluated`/`expired` mit **belegter** Störung (Fehler mit `request_id` im Zeitraum, H-32 rot, Ausfall) → St. 2: **K-09 Zusatzversuch** (E-137); Versuch bleibt als gewertet stehen · `broken` → St. 3 behebt die Ursache (Regel, Snapshot, `ccap_exam_snapshot_invalid`), dann St. 2 **K-07 Nachauswertung**; ist das nicht möglich → **K-08 Verwerfen** + K-09 · `cancelled` durch den Lernenden → zählt nach E-71 bei gesetztem Limit; Kulanz nur über K-09 mit Begründung |
| **Eskalation** | Mehrere `broken` derselben Prüfung → St. 3 (Regel/Variante prüfen, ggf. Prüfung bis zur Klärung „nicht startbar“); Produktfehler → St. 4 mit Diagnosepaket |
| **Abschluss** | Textbaustein „Versuch nicht gewertet, zusätzlicher Versuch freigeschaltet“; Antworten wurden nur bei Bedarf mit Begründung (E-82) eingesehen |

#### RB-SUP-3 · Zertifikat falsch

| Schritt | Inhalt |
|---|---|
| **Symptom** | Name falsch geschrieben, falscher Kurstitel, falsche Punkte oder Datum, Zertifikat für den falschen Kurs/Person, Verifikation zeigt „widerrufen“ |
| **Prüfen** (St. 1) | Zertifikatsansicht und Verifikationsstatus, `revision_no`, zugrunde liegender Versuch (Status, Punkte), Name im Profil zum Ausstellungszeitpunkt (Snapshot) |
| **Ursache → Aktion** (R0-23 §5) | Name geändert oder Druckfehler → **K-15 Korrektur** auf Antrag mit Nachweis (neue Revision); Profiländerung allein ändert nichts · Kurstitel nach späterer Revision anders → **keine** Änderung (Zertifikat zeigt den Titel wie ausgestellt, RV-13); Erklärung · Punkte falsch wegen fehlerhafter Frage → Nachbewertung nach E-74 (Datenvertrag R1, Oberfläche Pro; in R1 über CLI durch St. 3), danach Korrektur bzw. Prüfauftrag `certificate_review_required` · irrtümlich ausgestellt oder Betrug → **K-15 Widerruf** (St. 2, Grund Pflicht), ggf. K-10 · altes Layout → keine Neuausstellung in R1 (E-110) |
| **Eskalation** | Renderfehler (PDF leer, Zeichen fehlen) → St. 3/4 mit Template-Version und `request_id` |
| **Abschluss** | neuer PDF-Link per Mail (Korrektur); Verifikation zeigt die gültige Revision |

#### RB-SUP-4 · Import hängt

| Schritt | Inhalt |
|---|---|
| **Symptom** | Batch bleibt in `importing`/`activating`, Fortschrittsbalken steht, neue Inhalte erscheinen nicht |
| **Prüfen** (St. 1/2) | Import-Liste: Batch-UID, Status, Schritt, Cursor, **Heartbeat-Alter**, Item-Fehler. H-31, H-07 (Cron läuft?), H-14 (privater Ordner), H-12. Läuft ein anderer Import (nur einer gleichzeitig, INV-C-102)? |
| **Ursache → Aktion** | Heartbeat frisch → warten (Schritte zu 200 Datensätzen bzw. 64 MB Medien) · Heartbeat alt, keine lebende Sperre → **K-13a Fortsetzen** (St. 2) bzw. CLI `import resume <id>` (St. 3, 500 je Schritt, ohne PHP-Timeout des Webservers) · wiederholt derselbe Schritt mit Fehlercode → Ursache beheben (Speicher, Rechte, Medienvalidierung R0-10), dann fortsetzen · nicht mehr fortsetzbar → **K-13a Verwerfen** (Begründung) und neu hochladen · bereits veröffentlicht, Inhalt fehlerhaft → **K-13 Rollback** auf den vorherigen Stand · Reconcile RC-6 markiert verwaiste Batches als `aborted` |
| **Eskalation** | Fehler im Paketformat oder Validator ohne erklärbare Ursache → St. 4 mit Diagnosepaket (Abschnitt `imports`, keine Paketinhalte; Paket nur nach gesonderter Freigabe durch den Rechteinhaber) |
| **Abschluss** | aktives Paket im Systemstatus = erwarteter Stand; keine Tabellen von Hand bereinigen (GR-3) |

#### RB-SUP-5 · Mails kommen nicht an

**Prüfen:** H-25 Ausgangsmodus (`catch`/`off`?), H-26 Haltemodus, H-22 Absender, Liste der zurückgehaltenen Mails, Opt-out des Lernenden. **Aktion:** Haltemodus bestätigen (K-17, St. 3) und zurückgehaltene Mails nach E-88 behandeln (Standard verwerfen); Testmail an die eigene Adresse (`ccap_mail_test`); Zustellbarkeit (SPF/DKIM/DMARC) ist Betreibersache (R1-24). **Nie** Mails ohne Prüfung „nachsenden“, sonst entstehen Doppelwirkungen (Konzept 22.2).

#### RB-SUP-6 · Verdacht auf Kontomissbrauch oder geteiltes Konto

**Prüfen:** Grant- und Seat-Historie, Login-Sperre des Härtungsmoduls (H-38, E-10; Verhalten nach LB `support-ablaeufe.md` §6), auffällige Versuchsmuster (nur Status und Zeiten). **Aktion:** K-12 Lernsperre mit `abuse`/`account_compromised`, optional Sitzungen beenden; Passwort-Reset durch den Lernenden; Grants **nicht** widerrufen, solange der Fall nicht geklärt ist (Sperre ändert keine Grants, R0-09 §1.3). Eskalation an St. 3 bei mehreren Konten.

#### RB-SUP-8 · Admin oder Lernende ausgesperrt (Website-Härtung, fail-closed)

**Prüfen:** H-38 (Zustand je Funktion, erkanntes Security-Plugin, Zählerspeicher), Login- und Reset-Limit-Ereignisse im Protokoll. **Aktion:** Auf **bekannten Geräten** (Geräte-Cookie) bleibt der Login möglich (E-46, E-263). Sonst Notausschalter: Konstante `COURSE_CAPTAIN_HARDENING` in `wp-config.php` oder WP-CLI (E-242, E-263), danach Ursache beheben (Zählerspeicher, Konflikt mit Security-Plugin nach R0-20) und Härtung im Tab „Website-Härtung“ wieder bestätigen. **Nie** die Sperrtabellen per SQL leeren (GR-3).

#### RB-SUP-7 · Systemstatus rot / Wartungslauf steht

**Prüfen:** H-07/H-08 (Cron), H-02/H-03 (Migration), H-10 und H-27 (Job-Fehler mit Fehlercode), H-14. **Aktion (St. 3):** System-Cron einrichten; `wp course-captain migrate`; Job-Retry K-16 nach Behebung; Debug-Logging zeitlich begrenzt einschalten (DL-1) und Diagnosepaket an St. 4. Kein manuelles Löschen von Locks oder Optionen, sondern die dokumentierten Befehle (R0-16 §2).

---

## 6. Testfälle

Erwartung je Negativtest wie R0-07 §5.2: 403/404 ohne Datenleck, kein Fachereignis, kein Audit außer gedrosselter Ablehnung.

| ID | Bereich | Vorbedingung / Aktion | Erwartung | Ziel-Task | Bezug |
|---|---|---|---|---|---|
| SUP-001 | Rollen | `ccap_support` öffnet S-10, S-13, S-21, S-22 | lesbar; Knöpfe für K-01…K-11, K-13…K-17 fehlen | R1-38 | §1.2 |
| SUP-002 | Rollen | `ccap_support` sendet `ccap_grant_change` (revoke) mit gültiger Nonce | 403, kein `ccap_grant_event`, kein Audit-Fachereignis | R1-38 | R0-07 #9 |
| SUP-003 | Rollen | `ccap_support` öffnet S-11 Versuchsdetail | 403/404; keine Antworten in Liste, REST oder CSV | R1-38 | R0-07 #14, Konzept 43.3 |
| SUP-004 | Rollen | `ccap_support` setzt Lernsperre bei einem Lernenden, dann bei einem Admin, dann bei sich selbst | erst ok mit Audit `learner_locked`; Admin und selbst → 403 (M-24) | R1-38 | K-12 |
| SUP-005 | Rollen | Kursmanager öffnet S-21 | 403; Objekthistorie im Grant-Detail sichtbar | R1-38 | §4.5 |
| SUP-006 | Rollen | Shop-Manager bzw. Editor ohne `ccap_*`-Caps ruft S-22 und `/monitor` (bzw. `/health`) mit Details auf | 403 bzw. nur Gesamtstatus | R1-38 | E-83 |
| SUP-007 | Begründung | K-01, K-03, K-05, K-08, K-11, K-12 ohne `reason_code` oder mit Freitext < 10 Zeichen | abgelehnt (Feldfehler), keine Wirkung | R1-38 | §3 Schritt 3 |
| SUP-008 | Begründung | Manager öffnet S-11 ohne Begründung, dann mit Begründung | ohne: Formular, keine Daten; mit: Daten + Audit `attempt_details_viewed` mit `subject_user_id` | R1-38 | E-82 |
| SUP-009 | Grant | K-01 doppelt abgeschickt (gleicher `request_key`) | ein Grant, ein Audit, eine Mail | R1-38 | R0-09 §2.2 |
| SUP-010 | Grant | K-03 auf Woo-/Voucher-Grant; danach Löschversuch | Widerruf ok; Löschen nicht angeboten bzw. 403 | R1-38 | M-13 |
| SUP-011 | Grant | K-03, danach „Widerruf aufheben“ | nicht möglich; K-01 erzeugt neue Revision, alte bleibt `revoked` | R1-38 | E-29, G-15 |
| SUP-012 | Übertragung | K-05 von Konto A nach B | A `revoked` (`transferred`), B neuer Grant `source_revision + 1`; zwei Audit-Einträge (A und B als `subject_user_id`); Fortschritt/Versuche/Zertifikat bleiben bei A; Mail nur an B | R1-38 | E-114, ID-012 |
| SUP-013 | Übertragung | K-05 mit Ziel = Quelle, Ziel gesperrt, Ziel ohne Site-Rolle | jeweils abgelehnt | R1-38 | K-05 |
| SUP-014 | Übertragung | Quellkonto A ruft „Mein Lernen“ und REST nach K-05 auf | Status „übertragen“ ohne Name/E-Mail/ID von B | R1-38 | GR-5, P-08 |
| SUP-015 | Versuch | `broken`-Versuch: K-07 nach Behebung; zweiter Aufruf | einmal `evaluated` mit `evaluation_no`, Audit; zweiter Aufruf ohne Wirkung | R1-38 | R0-08 §6.2 |
| SUP-016 | Versuch | K-08 auf `broken`, dann K-09; Lernender startet erneut bei ausgeschöpftem `max_attempts` | Start möglich genau einmal; Audit `attempt_voided`, `attempt_allowance_granted`; Zähler nach E-137 | R1-38 | E-137 |
| SUP-017 | Diagnose | Fixture mit E-Mails, IPs, Token, Secrets, Pfaden mit Ordner-Geheimnis, Antworten in Fehlermeldungen und Job-Argumenten; Diagnosepaket erzeugen | 0 Treffer der Fixture-Werte im Paket; Platzhalter vorhanden; `manifest` mit Prüfsummen | R1-38 | §2.2, Konzept OPS-06 |
| SUP-018 | Diagnose | Settings mit `secret: true` aus DB und aus Konstante | Paket zeigt `<gesetzt>`/`<konstante>`, nie den Wert, nie die letzten 4 Zeichen | R1-38 | R0-16 §3.2 |
| SUP-019 | Diagnose | Download-Link nach 1 h; Serverkopie nach 24 h; Link eines anderen Nutzers | 404/abgelaufen; Datei gelöscht; fremder Link 404 | R1-38 | §2.2 |
| SUP-020 | Diagnose | Vorschau vor Download | Inhalt identisch zum Download (Hash-Vergleich) | R1-38 | §2.2 Schritt 2 |
| SUP-021 | Diagnose | zwei Pakete nacheinander | Pseudonyme zwischen den Paketen nicht gleich, innerhalb eines Pakets konsistent | R1-38 | §2.2 Pseudonyme |
| SUP-022 | Health | Cron-Lauf > 3 h her; frisch installiert und geplant | `fail` und 503; frisch → `ok` | R1-38 | H-07, LB `operations-test.php:686` |
| SUP-023 | Health | `/monitor` bzw. `/health` ohne Schlüssel, mit Schlüssel im Header `X-CC-Monitor-Key`, mit `?key=` | nur Gesamtstatus; Details; `?key=` wird ignoriert (nur Gesamtstatus) | R1-38 | H-18 |
| SUP-024 | Health | `dead_letter`-Job, `failed`-Grant, `broken`-Versuch, Import ohne Heartbeat | H-27/H-29/H-30/H-31 mit IDs; Job-Liste zeigt Job-ID und Fehlercode | R1-38 | DoD R1-38 |
| SUP-025 | Health | Haltemodus aktiv | H-26 `fail`, Knopf nur für #23; Bestätigung schreibt `site_identity_confirmed` | R1-38 | E-85 |
| SUP-026 | Debug | Debug für 2 h einschalten, Uhr +2 h 1 min | Logging aus, Audit `debug_enabled` und automatisches `debug_disabled` (`actor_type = system:cron`) | R1-38 | DL-1/DL-2 |
| SUP-027 | Debug | Prüfungsabgabe, Login, Grant-Anlage mit Debug `debug` | Log enthält `request_id`, keine Antworten, keine Token, E-Mail maskiert, IP gekürzt | R1-38 | DL-6 |
| SUP-028 | Debug | Log-Datei per URL abrufen | nicht erreichbar (privater Ordner bzw. H-12 bestanden) | R1-38 | DL-5 |
| SUP-029 | Reconcile | Seat belegt ohne Grant, Outbox mit abgelaufener Lease; Vorschau, dann `--apply` ohne und mit Begründung | Vorschau ändert nichts; ohne Begründung abgelehnt; mit: Korrekturen, `reconcile_applied` mit Zählern, Einzelereignisse mit gleicher `correlation_id`, **keine** Mail | R1-38, R1-43 | §3.2, Konzept 22.2 |
| SUP-030 | Reconcile | zwei Reconcile-Läufe parallel | zweiter Lauf gesperrt (Lock), keine Doppelkorrektur | R1-38 | LB `AutoReconcile` Lock |
| SUP-031 | Fortschritt | K-11 Kurs-Reset, danach Undo innerhalb 30 Tagen; zweites Szenario mit neuem Fortschritt nach dem Reset | Undo stellt den Vorzustand her (`progress_reset_undone`); mit neuem Fortschritt abgelehnt; Versuche und Zertifikate unberührt | R1-38 | E-138, R0-23 |
| SUP-032 | Import | Batch ohne Heartbeat: Fortsetzen; aktive Sperre: Verwerfen; Rollback ohne Begründung | fortgesetzt; Verwerfen abgelehnt, solange die Sperre lebt (T208); Rollback abgelehnt | R1-38 | K-13/K-13a, IMP-027 |
| SUP-033 | Audit | Update/Delete auf `ccap_audit_log` über jede CC-Route (REST, admin-post, CLI) | keine Route vorhanden bzw. 404 | R1-38 | §4.3 |
| SUP-034 | Audit | Zeile per SQL ändern, dann `audit verify` und H-36 | Bruch mit ID erkannt, H-36 `fail` | R1-38 | E-139 |
| SUP-035 | Audit | Prune nach Frist und Eraser für eine Person | `audit_pruned`/`audit_pseudonymized`-Anker, Kette danach gültig; IDs der Person entfernt | R1-38 | §4.3, E-44 |
| SUP-036 | Audit | Protokollfilter nach gelöschtem Konto (ID), nach `correlation_id`, nach Bereich | Treffer korrekt; keine E-Mail-/IP-Spalte | R1-38 | LB `AuditPage.php:102-136` |
| SUP-037 | Audit | Korrekturaktion, deren Audit-Insert scheitert (Fehlerinjektion) | Aktion vollständig zurückgerollt | R1-38 | §4.3 fail-closed |
| SUP-038 | Benachrichtigung | K-01, K-03, K-05, K-08, K-12 im Ausgangsmodus `on` und `catch` | Mails genau nach Tabelle §3.1; in `catch` zurückgehalten bzw. an Catcher | R1-38 | E-136, E-86 |
| SUP-039 | Doku | Runbooks RB-SUP-1…7 in der Betreiberdoku mit Schritten, Caps und Textbausteinen | vorhanden, jede K-Nr. verlinkt, Review durch Support-Testperson | R1-24 | §5 |
| SUP-040 | Doku | Stufenmodell und Rollenzuweisung (Tab „Rollen & Rechte“) in der Admin-Hilfe | beschrieben inkl. „kein Herstellerkonto“, „keine Impersonation“ | R1-24 | §1, E-141/E-142 |
| SUP-041 | Doku | Anleitung „Diagnosepaket erstellen und weitergeben“ mit Liste „was nie enthalten ist“ | vorhanden; Abgleich mit §2.2 in CI (Abschnittsnamen) | R1-24 | §2.2 |
| SUP-042 | Doku | Troubleshooting nach Fehlercode (R0-19) mit `request_id`-Hinweis für Lernende | jeder öffentliche Fehlercode hat Ursache und Supportweg | R1-24 | GR-7, DL-8 |
| SUP-043 | Härtung | H-38 vor Bestätigung, nach Bestätigung mit/ohne erkanntes Security-Plugin, mit gesetztem `COURSE_CAPTAIN_HARDENING` | info „nicht bestätigt“; `auto` zieht sich bei erkanntem Plugin zurück; Notausschalter = warn | R1-45, R1-38 | E-240, E-242, E-260 |
| SUP-044 | Härtung | Zählerspeicher fällt aus: Login von neuem Gerät, von bekanntem Gerät, danach Notausschalter per WP-CLI | neues Gerät gesperrt (fail-closed), bekanntes Gerät angemeldet, nach Notausschalter Login möglich; Audit-Einträge | R1-45 | E-46, E-263, RB-SUP-8 |

---

## 7. Entscheidungen (beschlossen)

Alle Fragen sind entschieden (`ENTSCHEIDUNGEN.md` §22, „Von Claude entschieden“, 16.11.2026); Empfehlung jeweils übernommen.

| Frühere ID | Beschluss | Inhalt | Wirkt in | Tasks |
|---|---|---|---|---|
| R13-D1 | **E-136** | Mail bei Freischaltung, Übertragung (nur Ziel), technisch verworfenem Versuch, Annullierung, Zertifikatskorrektur/-widerruf; keine Mail bei Widerruf, Sperre, Aussetzung, Reset, Reconcile; im Dialog abwählbar | §3.1 | R1-21, R1-38 |
| R13-D2 | **E-137** | `support_void` zählt nicht gegen `max_attempts` (zweite Ausnahme zu E-71 neben E-108); Aktion K-09 „Zusatzversuch“ | §3.1, SUP-016 | R1-08, R1-20, R1-38 |
| R13-D3 | **E-138** | Undo für Fortschritts-Reset über Reset-Journal (30 Tage), nur ohne neuen Fortschritt | §3.1, §4.4 | R0-06, R1-38 |
| R13-D4 | **E-139** | append-only plus Hash-Kette mit täglicher Prüfung in R1; externer Sink Pro-Kandidat | §4.3, H-36 | R0-06, R1-17, R1-38 |
| R13-D5 | **E-140** | Audit-Export in Free für Admins mit Pflichtbegründung; R0-07 §4.9 wird angepasst | §4.5 | R0-07, R0-11, R1-38 |
| R13-D6 | **E-141** | keine Impersonation; Ansicht des effektiven Zugangs mit Begründung | §1.1 | R1-03, R1-38 |
| R13-D7 | **E-142** | Herstellersupport ohne Konto, nur Diagnosepaket (Domain gehasht) | §1.1, §2.2 | R1-24, R1-38, R0-17 |
| R13-D8 | **E-143** | Debug-Fenster höchstens 24 h (Standard 2 h), Dateien 7 Tage; Fehlertabelle/Job-Fehler 30 Tage | §2.3, §4.4 | R0-31, R1-17 |
| R13-D9 | **E-144** | eine Rolle `ccap_support` (lesen + Lernsperre); Grant-Korrekturen durch Kursmanager oder gezielte Cap-Zuweisung | §1.2 | R1-03, R1-34 |
| R13-D10 | **E-145** | Akteure in der Betroffenenauskunft nur als Rolle | §4.5 | R0-31, R1-17 |

Genutzt, nicht neu gefragt: E-19, E-44, E-51, E-79, E-81, E-82, E-85…E-92, E-114, E-116, E-215 sowie E-10/E-46/E-240/E-242/E-260/E-263 (Härtung), E-38/E-182 (interne Outbox), E-168 (Abgaben).

### 7.1 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-13-1 | Schwellen für H-31 (Heartbeat > 15 min) und H-38 (Zeitraum fail-closed-Ereignisse) | technische Vorschläge, in keinem Beschluss festgelegt | Architektur (R1-38) → **technisch, an R1-38 übergeben** (kein Auftraggeber-Beschluss, kein Blocker für Gate A) |
| OP-13-2 | Juristische Bestätigung der Fristen (E-44, E-143) und der Akteursdarstellung (E-145) | Beschlüsse verlangen Bestätigung durch den Betreiber | Betreiber → **Auftraggeber-Aktion (Blocker für Gate A)**: juristische Bestätigung E-44, E-143, E-145 durch den Betreiber (Gate A-9), AG-07 |
| OP-13-3 | Supportaktionen für Aufgaben-Abgaben (z. B. Abgabe zurückgeben, Bewertung korrigieren) | E-168 legt den Workflow fest, aber keine Supportaktionen; nicht in K-01…K-17 | Auftraggeber/Architektur (R1 Aufgaben) → **neue Frage NF-07** (R0-GATE-protokoll.md §5; kein Blocker für Gate A, Klärung vor Gate C (R1-38)) |

---

## 8. Deltas zu anderen R0-Dokumenten

| Dokument | Delta |
|---|---|
| R0-06 | `ccap_audit_log` um `actor_type`, `subject_user_id` (Index), `reason_code`, `correlation_id` (Index), `request_id`, `row_hash` ergänzen (§4.2); Tabelle `ccap_reset_journal` (E-138; Name und Ort nach **E-296**, in R0-06 §4.10 eingearbeitet); `ccap_exam_session.end_reason` um `support_void`; Zähler bzw. Tabelle für Zusatzversuche (K-09, E-137); Fehlertabelle `ccap_error_log` oder Teil der Job-Tabelle (DL-3) |
| R0-07 | Aktionen K-01…K-17 in §4.3 aufnehmen (`ccap_attempt_reevaluate`, `ccap_attempt_void`, `ccap_attempt_allowance`, `ccap_grant_transfer`, `ccap_grant_retry`, `ccap_progress_reset_undo`, `ccap_diagnostics_export`, `ccap_job_action`, `ccap_reconcile_apply`, `ccap_debug_toggle`, `ccap_audit_export`); Objekthistorie für Inhaber der Objekt-Cap (§4.5); Audit-Export nach E-140; Reason-Code-Kataloge je Aktion |
| R0-08 | `support_void` zählt nicht gegen `max_attempts` (E-137); K-07/K-08 als Admin-Übergänge aus `broken` bestätigt, K-08 zusätzlich aus `in_progress` |
| R0-09 | K-05/K-06 entsprechen G-12 `transferred` bzw. G-13; Audit-Aktionsnamen aus §4.1 übernehmen |
| R0-16 | H-25/H-26 und K-17 als Systemstatus- und Aktionsbestandteil; RB-SUP-5 verweist auf §2.3 und E-88 |
| R0-19 | `request_id` in jeder Fehlerantwort und als Header `X-Request-Id`; Fehlercodes `ccap_reason_required`, `ccap_transfer_invalid_target`, `ccap_undo_not_possible`, `ccap_diagnostics_expired` |
| R0-31 | Fristen E-143 und Reset-Journal in das Löschkonzept; Betroffenenauskunft des Audits nach E-145 |
| R0-34 | H-34 übernimmt den Integrationsstatus; Diagnosepaket-Abschnitte `inbox`/`outbox` und Connector-Fehlercodes |
| R1-24 | Betreiberfassung von §1 (Stufen), §2.2 (Diagnosepaket), §5 (Runbooks, Textbausteine), Fristenempfehlung §5.1 |

---

## 9. Nachweis

- Gelesen (Planung): `TODO-WETTBEWERBSANALYSE.md` (R0-13, R1-17, R1-24, R1-38, R0-34), `konzept.md` Kap. 22, 36 (Rechtetabelle), 43.2/43.3 (Systemstatus, Supportzugriff, Diagnose/Debug), Kap. 25 (Diagnoselogs/Queue), OPS-06; `docs/r0/R0-07-rbac-idor-matrix.md` §0–4, §7, §8; `R0-09` §1.3, §2.1–2.5, §6.3 ID-012, §7, §8; `R0-16` §0, §1.4, §2, §3.1/3.2, §6; `R0-08` §6.1/6.2; `R0-10` §3.6; `R0-23` §5; `R0-02-inventar/C` §2.3, §2.6, §4.4, §5 (Audit-Aktionen), INV-C-055/-084/-149/-168/-172; `R0-02-inventar/E` §5; `ENTSCHEIDUNGEN.md` (E-19, E-44, E-51, E-71, E-74, E-79, E-82, E-107, E-108, E-110, E-114, E-116).
- LB @ `9f37c77` (nur gelesen): `docs/support-ablaeufe.md` (Grundregeln, §3, §5, §6), `docs/betrieb-entscheidungen.md` (T40); `Admin/SystemStatus.php:166-215`, `:216-270`, `:272-327`, `:340-393`; `Admin/SiteHealth.php:19-37`, `:44-120`, `:161-236`; `Infrastructure/Monitor.php:20-137`; `Infrastructure/Cron.php:16-60`; `Audit/AuditLog.php:10-27`; `Admin/AuditPage.php:22-136`, `:206-235`, `:288-346`, `:383-475`; `Admin/AccessPages.php:219`, `:566`; `Commerce/WooCommerce/AutoReconcile.php:13-60`; `Cli/Commands.php:353-414`, `:571`; `Mobile/Problem.php:23`, `Mobile/MobileApi.php:238`; `Rest/Controllers/LearnerController.php:463`.
- Keine Codeänderung an LearnBoating, keine Implementierung, kein Commit.
