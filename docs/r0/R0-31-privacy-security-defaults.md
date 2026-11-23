# R0-31 · Datenschutz- und Sicherheitsdefaults der Admin-Settings

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Keine Implementierung, kein Testlauf. · **Stand:** 16.11.2026
**Keine Rechtsberatung.** Das Dokument prüft technische Defaults gegen die Grundsätze „Privacy by Default“ (Art. 25 DSGVO) und „Sicherheit der Verarbeitung“ (Art. 32 DSGVO). Alle Fristen sind änderbare Vorschläge. Rechtsgrundlagen, endgültige Fristen und die Bewertung im Einzelfall liegen beim Betreiber als Verantwortlichem und seiner Datenschutzberatung (E-44, E-51, Konzept 20 „keine pauschale Konformitätszusage“).
**Bezug:** TODO R0-31 (Voraussetzung R0-17, R0-27; DoD: Retention-Entscheidungen, Medien/Consent, Secret-Handling, RBAC, Audit, Export/Reset und Testmatrix freigegeben), Folgetask **R1-34** (UI und serverseitige Wirkung inkl. Audit, Consent, Opt-out, Redaction, Permissions), außerdem R1-17, R1-03, R1-30, R1-36; Konzept Kap. 20, Kap. 43 (43.1–43.5).
**Quellen (nur gelesen):** `TODO-WETTBEWERBSANALYSE.md` R0-31, R1-34; `konzept.md` Kap. 20, 43; `docs/r0/R0-27-settings-register.md` §1.3–§1.6, §2.4–§2.6, §3.7, §3.11, §4, §5; `R0-17-threat-model-dsfa.md` §2, §3.4, §3.5, §5, §7.2, §8; `R0-13-support-betrieb.md` §2.2, §2.3, §4; `R0-16-backup-recovery-deployment.md` §2.5, §3.1, §3.2; `R0-07-rbac-idor-matrix.md` §1–§3; `R0-10-import-medien-sicherheit.md` §4, §5, MED-021…026; `R0-09-identity-seat-grant-automaten.md` §7; `R0-20-kompatibilitaet-konflikte.md` §2.6; `R0-04-r1-r3-grenze.md` BS-27/W-04; `R0-02-inventar/C` INV-C-138…146, E-C-02; `ENTSCHEIDUNGEN.md` Beschlussprotokoll E-01…E-20 sowie E-43…E-46, E-51, E-81…E-92, E-116, E-119, E-138, E-143…E-154, E-207, E-215…E-224.
### Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). Die früheren Fragen D31-1…D31-8 sind als **E-239…E-246** entschieden (§6). Eingearbeitet:

| Beschluss | Wirkung hier |
|---|---|
| **E-01** Clean Room | Limits und Abläufe aus LB sind als Verhalten spezifiziert, kein Code übernommen |
| **E-10**, E-239, E-240, E-260…E-263 | Härtungsmodul im Free-Kern (Task R1-45) mit eigenem 11. Tab (E-262, Felder `hardening.*` statt `privacy.hardening`). **Vor der ersten Bestätigung durch den Admin ist alles aus** (E-260); danach je Funktion `auto` (E-240). Umfang nach E-261: Login-Limit, Reset-Limit, bekanntes Gerät, XML-RPC, 4 Security-Header; Registrierungslimit nach E-241 |
| **E-46**, E-242, E-263 | fail-closed für Login, Reset, Registrierung; bekannte Geräte dürfen weiter; Notausschalter per Konstante und WP-CLI |
| **E-45/E-45a**, E-246 | externe Medien **Default `always_load`** (abweichend von „Privacy by Default“, als bewusst akzeptiertes Betreiberrisiko in R0-17 §6.5 dokumentiert); umschaltbar auf `click_to_load`; Consent-Kategorie wirkt nur dort; Setup-Hinweis und readme-Offenlegung (§1.2, §3.1) |
| **E-243…E-245** | Fristen für Daten ohne Regel, „Kursende“ = späterer Zeitpunkt aus Ende des letzten aktiven Grants und letzter Aktivität im Kurs, Verlängern nur mit Pflichtbegründung (§2) |
| **E-20** | Erklärungs-Schalter sind Pro-Felder (R0-27); ohne Datenschutzbezug |
| **E-38** | Events ohne Abonnent (RT-29) entstehen nur mit Pro |
| **E-168**, **E-289** | Aufgaben-Abgaben als neue Datenart RT-45: Frist wie Rohantworten, 2 Jahre nach Kursende (E-289); juristische Bestätigung durch den Betreiber |
| **E-307…E-309** | kein doppelter Security-Header (E-307); Enumeration-Schutz R4-Pool, E-241 insoweit überholt (E-308); `Permissions-Policy` nur auf Course-Captain-Antworten (E-309) (§3.4) |
| **E-207** | Präfix `ccap_` für Caps, Rollen, Tabellen; Optionen `course_captain_*` (im Text umgestellt) |
| **E-28, E-43, E-44, E-51, E-65, E-79, E-81…E-92, E-97, E-112, E-116, E-119, E-138, E-140, E-143…E-154, E-215…E-224** | wie angenommen |

**Kernzahlen**

| Größe | Wert |
|---|---:|
| geprüfte Register-Zeilen mit Datenschutz-/Sicherheitsbezug (§1.2) | 43 |
| … davon Default bestätigt ohne Änderung | 34 |
| … davon mit Delta (Risiko, Audit, Hilfetext oder Default) | 9 |
| neue Register-Felder (§1.3) | 9 (7 × `hardening.*`, Konstante, `privacy.consent_category`) |
| Datenarten in der Retention-Übersicht (§2.1) | 45 |
| Härtungsmerkmale (§3.4) | 5 + Gerätecookie (Enumeration nicht in R1, E-261) |
| Testfälle PSD-001…PSD-065 (§5) | 65 |
| Entscheidungen E-239…E-246 (§6, beschlossen) | 8 |

**Kernaussagen**

1. **Die Defaults aus R0-27 halten der Prüfung weitgehend stand.** Mit **einer bewussten Ausnahme** erzeugt kein Default ohne Handlung der Person zusätzliche Daten, öffnet Daten für Dritte oder verlängert eine Frist über die Vorschläge aus E-44/E-51 hinaus. Die Ausnahme ist `privacy.external_media_mode = always_load` (E-45/E-45a): externe Videos übertragen ohne Einwilligung Daten an den Anbieter. Das ist eine Auftraggeber-Entscheidung, als Betreiberrisiko dokumentiert (R0-17 §6.5), mit Hinweis im Setup, Offenlegung in der readme und Umschalter auf `click_to_load`. Nachzuschärfen sind neun Felder, meist bei der Bestätigungspflicht (z. B. Avatare, Lösungsfreigabe in Prüfungen) und beim Export umgebungsgebundener Werte.
2. **E-10 ändert R0-27, R0-20 und R0-17.** Die Invariante INV-S-07 („keine eigene Login-Härtung“) ist überholt, SET-119 neu gefasst. An ihre Stelle tritt ein **Härtungsmodul im Kern** (R1-45, E-239) mit fünf Merkmalen plus Gerätecookie, Modus `auto | force | off` je Merkmal und Rückhaltung bei erkanntem Konflikt (§3.4). Bis zur ersten Bestätigung durch den Admin ist alles aus (E-260), danach `auto` (E-240).
3. **Retention ist vollständig erfasst.** Die früheren Lücken (Gruppen und Plätze, beendete Grants bestehender Konten, aufgehobene Lernsperren, personenbezogene Exportdateien) sind mit E-243 geschlossen; „Kursende“ ist mit E-244 definiert. Offen ist nur die Frist für Aufgaben-Abgaben (E-168, RT-45).
4. **Secrets bleiben außerhalb jeder Ausgabe.** Neu ist ein Schlüssel-Fingerabdruck, damit ein Wechsel von `COURSE_CAPTAIN_SECRET_KEY` oder der WP-Salts nicht still Pseudonyme und Tombstone-Abgleich entwertet (§3.2, T31-3).

---

## 1. Prüftabelle der Settings

### 1.1 Prüfmaßstab und Spalten

| Kürzel | Bedeutung |
|---|---|
| **M** | Art. 25 Abs. 2: **Menge** der erhobenen Daten (Default erzeugt nur Daten, die für den Zweck nötig sind) |
| **U** | Art. 25 Abs. 2: **Umfang** der Verarbeitung (keine Zusatzverarbeitung, keine Übermittlung an Dritte ohne Handlung) |
| **S** | Art. 25 Abs. 2: **Speicherfrist** (keine unbegrenzte Aufbewahrung als stiller Default, Konzept 35.4) |
| **Z** | Art. 25 Abs. 2: **Zugänglichkeit** (Daten nicht ohne Eingreifen einer unbestimmten Zahl von Personen zugänglich) |
| **Sec** | Art. 32: Sicherheit der Verarbeitung (Vertraulichkeit, Integrität, Belastbarkeit) |
| **R / R+** | Bestätigungsdialog nach R0-27 Lesehilfe; **R+** zusätzlich Eingabe des Site-Namens und Backup-Hinweis |
| **Audit** | `settings_saved` nach R0-27 SR-6 (Vorher/Nachher bei `public`/`internal`, nur „geändert“ bei `personal`, nie `secret`) |
| **Befund** | **ok** = Default und Schutzregel bestätigt; **Δ** = Änderung gegenüber R0-27 (in §1.4 gesammelt) |

**Prüfregeln (gelten für jede Zeile):**

- **PD-1 Strenger ist frei.** Eine Änderung in Richtung weniger Daten, kürzerer Frist, geringerer Zugänglichkeit oder mehr Schutz braucht keine Bestätigung. Ausnahme: Verkürzen einer Frist löscht Bestand und braucht daher R mit Anzahl (PRIV-008).
- **PD-2 Schwächer ist bestätigt.** Jede Änderung, die Daten erzeugt, an Dritte gibt, länger hält oder einen Schutz abschaltet, braucht R (oder R+ bei Datenverlust) und wird auditiert.
- **PD-3 Bestätigung serverseitig.** Der Dialog erzeugt ein Bestätigungstoken, gebunden an Nutzer, Feld, Zielwert und Tab-Nonce, einmalig, 10 min gültig. Speichern ohne gültiges Token wird für R-Felder abgelehnt (UI, admin-post, CLI ohne `--yes` bzw. `--confirm=<site>`, Import). Damit greift die Bestätigung auch ohne JavaScript und gegen nachgebaute Requests (PSD-004, PSD-007).
- **PD-4 Overrides nur strenger.** Kurs-, Prüfungs- und Medium-Overrides datenschutzrelevanter Felder dürfen nur in Richtung PD-1 abweichen (R0-27 SR-3).
- **PD-5 Hilfetext nennt die Folge.** Jedes Feld mit Bezug M/U/Z nennt im Hilfetext, welche Daten an wen gehen. Rechtliche Wertungen stehen dort nicht.

### 1.2 Prüftabelle (Register-Zeilen aus R0-27 §1.3)

| Key | Default | Bezug und Begründung | Risiko bei Änderung | Bestätigung | Audit | Befund |
|---|---|---|---|---|---|---|
| `general.delete_data_on_uninstall` | false | S, Sec: Datenverlust nie still (Konzept 22.2, E-89) | Uninstall löscht Lernhistorie, Nachweise, Audit unwiderruflich | R+ (Einschalten), CLI `--confirm=<site>` | ja | ok |
| `general.catalog_indexing` | `public_catalog` | Z: öffentlich ist nur der Katalog; Lernbereich, Lektionen, Nachweis-, Einladungs- und Claim-Seiten bleiben immer `noindex` (Invariante) | `noindex` nur strenger; private Seiten sind nie umschaltbar | – | ja | ok |
| `learning.favorites_enabled` | true | M: Daten entstehen erst durch Handlung der Person; privat, im Export, vom Eraser gelöscht | Abschalten blendet Funktion aus, Bestand bleibt (Retention §2) | – | ja | ok |
| `learning.mistake_training_enabled` | true | wie oben | wie oben | – | ja | ok |
| `learning.feedback` | always / true / true | Sec gering: Lernmodus ist kein Nachweis; Lösungsabruf im Lernmodus ist bekanntes Restrisiko (R0-17 AC-01, E-146 `exam_only`) | keins für Personendaten | – | ja | ok |
| `exams.feedback` | after_submit; Lösungen erst `no_attempts_left_or_passed` (E-147) | Sec (Prüfungsintegrität): keine Lösung, solange weitere Versuche offen sind | `show_correct_answers_when = after_submit` macht Pools über Wiederholungsversuche abschöpfbar | **R** (neu, Hinweis Prüfungsintegrität) | ja | **Δ-1** |
| `exams.timer_mode` | `strict` | Sec: nur `strict` ist nachweisfähig (E-123) | `practice` entwertet Nachweis; Validator blockiert bei `producesEvidence` | – (Validator genügt) | ja | ok |
| `enrollment.self_enrollment` | false | Z, Sec: Deny by default; Zugang nur über Grant (E-84, INV-S-11) | Einschalten öffnet veröffentlichte Kurse für alle Konten | R (mit Anzahl Kurse) | ja | ok |
| `enrollment.default_access_days` | `null` = unbefristet | S: betrifft Zugang, nicht Datenhaltung; Lernstand hängt nicht am Grant (R0-09 §7) | keins für Personendaten | – | ja | ok, Hilfetext: „Laufzeit des Zugangs, nicht der Daten“ |
| `enrollment.groups_enabled` | false (E-222) | M, Sec: weniger Angriffsfläche (Einladungen, Tokens) | Einschalten erzeugt Einladungsadressen Dritter | – (Einschalten); R beim Abschalten | ja | ok |
| `enrollment.invite_valid_days` | 30 | Sec, S: Token-Lebensdauer begrenzt; Adresse 30 d nach Ablauf gelöscht | lange Fristen verlängern Token-Missbrauch und Adresshaltung | – ; Hinweis ab > 90 Tagen | ja | **Δ-2** (Hinweistext) |
| `enrollment.claim_policy` | `transferable` (E-28) | Sec: Link ist kein Identitätsbeweis (R0-09 §1.2); `invited_address_only` ist strenger | keins (strenger) | – | ja | ok, Hilfetext nach PD-5 |
| `WP:default_role` | `subscriber` | Sec: keine Rechte über `read`; Health-Warnung bei `edit_posts`, `promote_users`, `ccap_*` | Eskalation, wenn Registrierung offen ist | WP-Kern | nein (WP) | ok |
| `roles.cap_assignments` | nur `administrator` + vier `ccap_`-Rollen; `ccap_support` ohne #14 (E-83, E-144) | Z, Sec: Least Privilege | Hochrisiko-Caps #14, #19…#24 an weitere Rollen öffnen fremde Antworten bzw. Administration | R mit Liste der Hochrisiko-Caps | ja (`role_caps_changed`) | ok |
| `certificates.auto_issue` | false | M: Nachweis erst bei definierter Abschlussregel | Ausstellung mit Personendaten im Snapshot | – | ja | ok |
| `certificates.holder_name_policy` | `learner_opt_in`, je Nachweis aus (E-97) | Z: Verifikation zeigt ohne Freigabe nur Status, Aussteller, Kurs, Datum, Initialen | `never` ist strenger; es gibt keinen Wert „immer öffentlich“ | – | ja | ok |
| `emails.from_name` | leer = WP erben | M: keine zweite Kopie; Klasse `personal` | kann Personennamen enthalten → Export nur per Opt-in | – | ja („geändert“) | ok |
| `emails.from_address` | leer = WP erben | wie oben | wie oben | – | ja („geändert“) | ok |
| `emails.footer_text` | leer | wie oben | wie oben | – | ja („geändert“) | ok |
| `emails.types` | je Typ nach R0-27 §3.2; N-12 aus (E-217) | U: nur transaktionale Mails; `required` nicht abschaltbar (E-216) | Abschalten von Pflichtmails verhindert Rechtsposition der Person; Server lehnt ab | R beim Abschalten | ja | ok |
| `mail_template.<typ>` | Werkstext | M, Z: Platzhalter-Allowlist ohne Punktzahl, Antworten, IP, fremde Adressen (R0-27 §3.10) | unbekannter Platzhalter wird abgelehnt | – | ja (Hash) | ok |
| `user.notification_prefs` | leer (keine Abmeldung) | U: Abmeldung je `informational`-Typ, Betreiber kann sie nicht aufheben | – | – | ja (`notification_optout_changed`) | ok |
| `pro.reminders` | aus; nur zahlende Käufer (E-24) | U: Werbewirkung nur per Opt-out-fähiger Klasse `marketing` | Einschalten erzeugt Erinnerungsmails | – | ja | ok (P/R3) |
| `privacy.auto_export_on_confirm` | true (E-223) | U: erleichtert Auskunft; Link nur an die von WP bestätigte Adresse, Frist WP-Kern 3 d | Abschalten: Admin führt Export manuell aus (Monatsfrist-Warnung PRIV-028) | – | ja | ok |
| `privacy.auto_erase_on_confirm` | false (E-43) | S, Sec: Kontolöschung nur Opt-in; Staff-Konten geschützt | Einschalten löscht WP-Konten nach Bestätigung | R | ja | ok |
| `privacy.keep_evidence_on_erasure` | aus (E-150) | S: Eraser löscht standardmäßig | Einschalten hält pseudonymisierte Nachweise trotz Löschantrag | R + Pflichtbegründung | ja | ok |
| `privacy.retention` (17 Unterschlüssel) | §2 | S: jede Datenart mit Frist; kein stilles „unbegrenzt“ | Verkürzen löscht Bestand; Verlängern über Default verlängert Haltung | R beim Verkürzen (Anzahl); Begründung beim Verlängern über Default (E-245) | ja | **Δ-3** |
| `privacy.backup_max_age_days` | 90 | S: Information für Tombstone-Frist (+30 d, E-151) | zu kleiner Wert lässt Tombstones vor Backup-Rotation verfallen | – ; Hinweis, wenn Wert < 30 | ja | **Δ-4** (Hinweis) |
| `privacy.external_media_mode` | **`always_load`** (E-45/E-45a) | U: Einbettung beim Seitenaufruf überträgt ohne Einwilligung IP und Nutzungsdaten an den Dienst – **nicht** Privacy by Default; bewusst akzeptiertes Betreiberrisiko (R0-17 §6.5, AR-01); Minderung: datensparsame Provider-Varianten (MC-2), Setup-Hinweis, readme-Offenlegung, Moduswert in der Verarbeitungsübersicht | `click_to_load` und `link_only` sind strenger | – (strenger immer erlaubt); Hinweistext im Feld | ja | **dokumentierte Ausnahme** (Auftraggeber-Beschluss) |
| `privacy.external_media_remember` | true (wirksam nur bei `click_to_load`) | U: bietet nur das Kästchen an; das Kästchen ist **nie vorangehakt**; Speicher ohne Personenbezug (CS-02) | aus: Kästchen verschwindet, gespeicherte Wahl wird ignoriert | – | ja | **Δ-5** (Regel „nie vorangehakt“, Widerruf für Lernende §3.1) |
| `privacy.trusted_proxies` | leer | Sec: Client-IP nur über eingetragene Proxys; `0.0.0.0/0` abgelehnt | falscher Eintrag macht Rate-Limits per `X-Forwarded-For` umgehbar | R, nur `administrator` | ja | ok; nicht im Export (§4) |
| `privacy.show_avatars` | false (CS-05) | U: Gravatar überträgt E-Mail-Hash an Dritte | Einschalten startet Drittübermittlung je Seitenaufruf | **R** (neu, Hinweis Drittanbieter) | ja | **Δ-6** |
| `privacy.csp_mode` | `enforce` (E-224) | Sec: XSS-Schutz der Lern-SPA | `report_only` schwächt Abwehr | R | ja | ok |
| `advanced.debug` | aus; Fenster 2 h, max. 24 h (E-143) | S, Sec: Redaction beim Schreiben, Dateien 7 d | Einschalten erzeugt Protokolle | – ; **Pflichtbegründung** (R0-13 §4.1 `debug_enabled`) | ja (`debug_enabled`) | ok |
| `advanced.file_delivery` | `php` | Sec: Rechteprüfung vor jeder Auslieferung (INV-S-06) | X-Sendfile/X-Accel nur nach erfolgreicher Selbstprüfung | R | ja | ok; nicht im Export (§4) |
| `advanced.media_delete_grace_days` | 7 | S: unreferenzierte Dateien verschwinden | lange Karenz hält ggf. geschützte Medien länger | – | ja | ok |
| `advanced.rate_limits` | Anzeige; nur Filter (E-221) | Sec: Werte < 1 verworfen, `fail_mode` für Claim, Einlösen, Verifikation, Datenschutz fest | Filter kann Limits erhöhen | – (kein UI) | nein (Filter) | **Δ-7** (Anzeige um Härtungs-Limits ergänzen, §3.4) |
| `const:COURSE_CAPTAIN_OUTBOUND` | `on` in `production`, sonst `catch` (E-86) | U: keine Mails aus Kopien | `on` auf Staging versendet an echte Personen | Konstante | nein | ok |
| `const:COURSE_CAPTAIN_MAIL_CATCH_ADDRESS` | leer = `admin_email` | U | ungültig → Modus `off` mit Health-Warnung | Konstante | nein | ok |
| INV-S-05 Audit | nicht abschaltbar | Sec: Rechenschaft | – | – | – | ok |
| INV-S-06 Geschützte Medien | nicht abschaltbar (E-65) | Z, Sec | – | – | – | ok |
| INV-S-07 „keine eigene Login-Härtung“ | – | **überholt durch E-10** | – | – | – | **Δ-8** – eingearbeitet in R0-27 (INV-S-07 entfällt, Felder `hardening.*`) |
| Diagnosepaket (R0-13 §2.2, Aktion) | Domain gehasht, Debug-Auszug aus | M, Z: Allowlist, Vorschau vor Download | „Domain im Klartext“ gibt Site-Identität heraus | – (Opt-in-Kästchen je Paket) | ja (`diagnostics_exported`) | **Δ-9** (Härtungszustand in Abschnitt `settings`, §4.3) |

**Geprüft und ohne Datenschutz-/Sicherheitsbezug** (keine Zeile oben): Slugs, Seiten-IDs, Lernbereich-Titel, Profilsprache, Darstellung (Breite, Breadcrumbs, Tokens, Karten, Sortierung), `display.require_captions` (Barrierefreiheit), Abschluss- und Mastery-Regeln, Versuchs-, Zeit-, Cooldown-, Mischregeln, Bestehensvorschlag, Ergebnis-Auto-Abgabe, Pending-Timeout, Quellen-Policy, Zertifikatsvorlage, Sendeformat, Import-Ausführung und Fehlerschwelle, Cron-Warnung.

### 1.3 Neue Register-Felder (Delta zu R0-27 §1.3)

| Key | Tab | Quelle | Typ | Scope | Default | Capability | Validierung | Edition | Audit | Risiko | Test |
|---|---|---|---|---|---|---|---|---|---|---|---|
| `hardening.login_limit`, `hardening.reset_limit`, `hardening.registration_limit`, `hardening.xmlrpc`, `hardening.site_headers` | **Website-Härtung** (11. Tab, E-262; Option `course_captain_settings_hardening`) | E-10, E-261, E-241, §3.4 | je enum{auto, force, off} | system | vor erster Bestätigung wirksam `off` (E-260); nach Bestätigung `auto` (E-240), der Assistent schlägt bei erkanntem Security-Plugin für die betroffene Funktion „aus“ vor | #19 **und** `manage_options` | `force` und `off` nur mit Bestätigung; `force` zeigt den erkannten Konflikt | F/R1 (R1-45, E-239) | ja | R bei `force` und bei `off` von `login_limit`/`reset_limit` | PSD-039…056 |
| `hardening.known_device` | Website-Härtung | E-241, E-242, E-263 | bool | system | true (wirksam nur mit `login_limit`) | wie oben | – | F/R1 | ja | – | PSD-044, PSD-046 |
| `hardening.confirmed_at` | Website-Härtung | E-260 | UTC-Zeit oder null | system | null | wie oben | wird beim ersten Speichern gesetzt; vorher wirken alle Werte als `off` | F/R1 | ja | – | PSD-065 |
| `const:COURSE_CAPTAIN_HARDENING` | Erweitert (Anzeige) | §3.5 | enum{off} | system | nicht gesetzt | `wp-config.php` | nur `off` erlaubt (Notausschalter); überstimmt alle `hardening.*` vollständig | F | Audit beim ersten Erkennen (`hardening_kill_switch_detected`) | – | PSD-045 |
| `privacy.consent_category` | Datenschutz & Sicherheit | R0-10 §5.3 („konfigurierbare Kategorie“), E-246 | enum{marketing, statistics, preferences, functional} | global | `marketing` | #19 | nur WP-Consent-API-Kategorien; wirkt nur bei `external_media_mode = click_to_load` (E-246) | F | ja | R bei `functional` (Einbettung gilt dann als nicht einwilligungsbedürftig) | PSD-025 |

Retention-Unterschlüssel nach E-243 kommen in §2.2 dazu.

### 1.4 Deltas Δ-1…Δ-9 (Zusammenfassung für R0-27; Δ-8 und die neuen Felder sind dort eingearbeitet)

| Nr. | Feld | Änderung |
|---|---|---|
| Δ-1 | `exams.feedback` | Risiko `R`, wenn `show_correct_answers_when` auf `after_submit` gesetzt wird; laufende Snapshots unverändert |
| Δ-2 | `enrollment.invite_valid_days` | Hinweistext ab > 90 Tagen (Token-Lebensdauer, Adresshaltung) |
| Δ-3 | `privacy.retention` | Verlängern über den Default mit Pflichtbegründung im Audit (E-245); Unterschlüssel nach E-243 ergänzen (17 → 21) |
| Δ-4 | `privacy.backup_max_age_days` | Hinweis bei Werten < 30 („Tombstones können vor der Backup-Rotation verfallen“) |
| Δ-5 | `privacy.external_media_remember` | Regel „Kästchen nie vorangehakt“; Widerruf im Lernenden-Konto (§3.1) |
| Δ-6 | `privacy.show_avatars` | Risiko `R` statt `–` |
| Δ-7 | `advanced.rate_limits` | Anzeige enthält die Härtungs-Limits und deren `fail_mode`; Login/Reset/Registrierung fest `closed` (E-10/E-46) |
| Δ-8 | INV-S-07, SET-119, K43-48 | **Eingearbeitet:** INV-S-07 entfällt in R0-27; K43-48 wird Feld (Tab Website-Härtung); SET-119 neu gefasst, ergänzt durch PSD-039…056 |
| Δ-9 | Diagnosepaket | Abschnitt `settings` zeigt je Härtungsmerkmal Modus und Wirkzustand (`active`, `held:<grund>`, `off`) ohne Lizenz- oder Konfigurationsdaten fremder Plugins |

---

## 2. Retention-Übersicht

### 2.1 Alle Datenarten

Spalten: **Frist** = Default (änderbar, wo „Setting“ steht); **Auslöser** = Beginn der Frist; **Löschart**: *löschen*, *pseudonymisieren* (HMAC mit Site-Schlüssel, R0-09 §7), *aggregieren* (Rohdaten in Lernstand überführen, dann löschen), *NULL* (Feld leeren), *kompaktieren* (Journal neu schreiben); **Export** = Teil des WP-Datenschutzexports der betroffenen Person. Jobs laufen idempotent mit Batch-Grenze (stündlich ≤ 5 000 bzw. täglich ≤ 1 000) und schreiben `retention_applied` (§2.3).

| Nr. | Datenart | Ort | Frist (Default) | Setting | Auslöser | Löschart | Export | Quelle |
|---|---|---|---|---|---|---|---|---|
| RT-01 | Lernstand (Fortschritt Kurs/Activity/Frage, Smarttrainer) | `ccap_course_progress`, `ccap_activity_progress`, `ccap_question_progress` | bis Kontolöschung bzw. Eraser | fest (`learning_progress`) | Löschantrag, Kontolöschung | löschen | ja | E-51 |
| RT-02 | Lernstand inaktiver Konten | wie RT-01 | aus (`null`); wählbar 1–20 J. | `inactive_learning_data_years` | letzte Anmeldung **und** kein aktiver Grant | löschen, Vorschau Pflicht | – | E-154 |
| RT-03 | Favoriten, Sammlungen | `ccap_favorite`, `ccap_collection(_item)` | wie RT-01 | – | wie RT-01 | löschen | ja | R0-17 §5.2 |
| RT-04 | Antwortlog Lernmodus | `ccap_attempt`, `ccap_attempt_answer` | 2 J. | `raw_answers_years_after_course_end` | Kursende = späterer von: Ende des letzten aktiven Grants, letzte Aktivität im Kurs (E-244) | aggregieren in RT-01 | ja | E-51, R0-06 D-5 |
| RT-05 | Rohantworten Prüfung (Antworten, Markierungen, Freitext) | `ccap_exam_session_question` | 2 J. | wie RT-04 | Kursende (E-244) | löschen; Kerndaten RT-06 bleiben | ja (Fragetext aus Snapshot) | E-51 |
| RT-06 | Prüfungsversuche (Kerndaten: Kurs, Datum, Punkte, bestanden) | `ccap_exam_session` | 10 J. | `exam_attempts_years` | Abgabe bzw. Auswertung | löschen | ja | E-51 |
| RT-07 | Prüfungsnachweise nach Löschantrag (nur mit `keep_evidence_on_erasure`) | `ccap_exam_session` pseudonym | Rest von RT-06 | wie RT-06 | Löschantrag | pseudonymisieren, nach Fristende löschen | – (Person gelöscht) | E-150 |
| RT-08 | Laufende/abgebrochene Versuche ohne Abgabe | `ccap_exam_session` | Auto-Abgabe nach x Tagen (Default aus); danach wie RT-06 | `exams.auto_submit_inactive_days` | letzte Aktivität | auswerten, dann RT-06 | ja | E-72 |
| RT-09 | Zertifikate (Snapshot, Revisionen, Status) | `ccap_certificate` | 10 J. | `certificates_years` | Ausstellung (letzte Revision) | löschen; Verifikation zeigt „nicht verfügbar“ | ja | E-51 |
| RT-10 | Zertifikate nach Löschantrag mit `keep_evidence_on_erasure` | `ccap_certificate` ohne Namen | Rest von RT-09 | wie RT-09 | Löschantrag | pseudonymisieren | – | E-150 |
| RT-11 | Aktive Grants | `ccap_grant` | solange aktiv | – | – | nie durch Retention | ja | R0-09 |
| RT-12 | Beendete Grants bestehender Konten (abgelaufen, widerrufen, failed) samt Events | `ccap_grant`, `ccap_grant_event` | **10 J. (E-243)** | `ended_grants_years` (neu) | Grant-Ende | löschen | ja | E-44, E-243 |
| RT-13 | Grant-Historie gelöschter Konten, Kaufquellen | `ccap_grant`, `ccap_grant_event` | 10 J. | `grant_history_deleted_accounts_years` | Kontolöschung | pseudonymisieren (`subject_ref`), nach Frist löschen | – | E-44, R0-09 §7 |
| RT-14 | Grants gelöschter Konten, übrige Quellen (manuell, Gruppe, Workflow) | wie RT-13 | sofort | – | Kontolöschung/Eraser | löschen | – | R0-09 §7 |
| RT-15 | Einladungsadressen | `ccap_seat.invite_email` | bis Claim; 30 d nach Ablauf | `invite_email_days_after_expiry` | Claim, Entfernen, Ablauf | NULL | ja (eigene) / maskiert (als Besitzer) | R0-09 §7 |
| RT-16 | Tokens (Claim, Gruppe, Verifikation) | Hash in `ccap_seat`, `ccap_group`, `ccap_certificate` | bis Claim, Rotation, Ablauf | – | Ereignis | NULL | nie | E-112 |
| RT-17 | Entfernte Plätze | `ccap_seat` (`removed`) | **365 d (E-243)** | `removed_seats_days` (neu) | Entfernung | löschen (Grant-Historie bleibt nach RT-12) | ja | E-243 |
| RT-18 | Geschlossene/widerrufene Gruppen ohne aktive Plätze | `ccap_group`, `ccap_group_course` | **3 J. (E-243)** | `closed_groups_years` (neu) | Schließung bzw. letzter aktiver Platz | löschen | ja (als Besitzer) | E-243 |
| RT-19 | Gruppenbesitz bei Kontolöschung | `ccap_group.owner_user_id`, `name` | sofort | – | Kontolöschung | `0`; Name neutralisieren, wenn er den Personennamen enthält | – | R0-09 §7 |
| RT-20 | Staff-Zuweisung | `ccap_course_staff` | bis Entfernung bzw. Kontolöschung | – | Ereignis | löschen | ja | R0-17 §5.2 |
| RT-21 | Aktive Lernsperre | `ccap_learner_lock` | bis Aufhebung | – | – | – | ja | E-79 |
| RT-22 | Aufgehobene Lernsperre | `ccap_learner_lock` | **30 d (E-243)**; Historie im Audit | `lifted_locks_days` (neu) | Aufhebung | löschen | ja | E-243 |
| RT-23 | Audit | `ccap_audit_log` | 4 J. | `audit_log_years` | Eintrag | löschen (Ankereintrag); Eraser pseudonymisiert | ja (eigene Vorgänge, Akteur nur als Rolle, E-145) | E-44, R0-13 §4.4 |
| RT-24 | Import-Protokoll „unverändert“ | `ccap_import_*` | 14 d | `import_unchanged_days` | Lauf | löschen | – | E-44 |
| RT-25 | Import-Protokoll übrige Zeilen | `ccap_import_*` | 180 d | `import_log_days` | Lauf | löschen; Akteur pseudonymisieren bei Eraser | – | E-44 |
| RT-26 | Paketkopien (enthalten Lösungen) | `packages-<geheim>/` | 2 neueste je Quelle | `import_keep_packages` | neuer Import | löschen | – | R0-19 §4.3 |
| RT-27 | Inbox | `ccap_inbox_event` | 90 d | `inbox_days` | Eingang | löschen; Eraser neutralisiert Nutzer-UID | – | E-116, E-215 |
| RT-28 | Outbox zugestellt (in Free nur interne Outbox: Mails, Audit; E-38) | `ccap_outbox_event`, `ccap_outbox_delivery` | 90 d | `inbox_days` (gemeinsam, E-215) | Zustellung | löschen | – | E-215 |
| RT-29 | Events ohne Abonnent (nur mit Pro, E-38/E-119) | `ccap_outbox_event` | 30 d | `outbox_event_days` | Erzeugung | löschen | – | E-119, E-215 |
| RT-30 | Benachrichtigungsprotokoll (nur Metadaten) | `ccap_notification` | 90 d | `notification_log_days` | Versand | löschen; Eraser löscht eigene | ja (Typ, Status, Zeit) | E-215, R0-27 §3.11 |
| RT-31 | Zurückgehaltene Mails (Haltemodus) | Mail-Protokoll `held` | 30 d | `held_mail_days` | Zurückhaltung | löschen | ja (Betreff, Zeit) | E-151 |
| RT-32 | Opt-out-Einstellungen | User-Option `course_captain_notification_prefs` | bis Kontolöschung | – | – | löschen mit Konto | ja | R0-27 §3.7 |
| RT-33 | Fehlertabelle, Job-Fehler | `error`/`warning` | 30 d | `error_log_days` | Eintrag | löschen | – | E-143 |
| RT-34 | Debug-Log-Dateien | `<privat>/logs/` | 7 d (Maximum) | `debug_file_days` | Datei | löschen | – | E-143 |
| RT-35 | Diagnosepaket (Serverkopie / Link) | `<privat>/` | 24 h / Link 1 h | fest | Erzeugung | löschen | – | R0-13 §2.2 |
| RT-36 | Personenbezogene Exportdateien (Bericht-CSV, Grant-Export, Audit-Export) | `packages-<geheim>/exports/` | **24 h (E-243)** | fest | Erzeugung | löschen | – | E-243, R0-10 §6.1 (Kurs-Export 24 h) |
| RT-37 | Kurs-Export (ohne Personendaten) | wie oben | 24 h | fest | Erzeugung | löschen | – | R0-10 §6.1 |
| RT-38 | Reset-Journal „Fortschritt zurücksetzen“ | `ccap_*` | 30 d | fest | Reset | löschen | ja | E-138 |
| RT-39 | Idempotenzschlüssel | `ccap_request_idempotency` | 7 d | fest | Anfrage | löschen | nein | R0-19 §2.1 |
| RT-40 | Rate-Limit- und Härtungszähler, Sperrfenster | Zählertabelle (gesalzene Hashes) | Fensterende | fest | Fenster | löschen | nein | R0-17 DK-11, §3.4 |
| RT-41 | Lösch-Tombstones | `ccap_privacy_tombstone`, Journal | `backup_max_age_days` + 30 (Default 120 d) | abgeleitet | Löschung | löschen bzw. kompaktieren | nein | E-92, E-151 |
| RT-42 | Settings-Undo-Snapshot | Option `course_captain_settings_undo_<tab>` | 30 d, eine Generation | fest | Reset/Import | löschen | nein | R0-27 §2.4 |
| RT-43 | Unreferenzierte Mediendateien | `protected-<geheim>/`, `media/` | 7 d Karenz | `advanced.media_delete_grace_days` | Verlust der letzten Referenz | löschen | – | R0-16 I-3 |
| RT-44 | Abgeschlossene WP-Datenschutzanträge (`user_request`) | WP-Kern | **keine Löschung durch CC**; Betreiberdoku schlägt 3 J. vor (E-243) | – | Abschluss | Betreiber | – | R0-17 §5.3 Nr. 5 |
| RT-45 | Aufgaben-Abgaben (Text, Dateien, Bewertung, Rückmeldung; E-168) | Abgabetabelle, Dateien in `protected-<geheim>/` | 2 J. nach Kursende wie RT-05 (**E-289**, E-51/E-244) | wie RT-04 (`raw_answers_years_after_course_end`) | Kursende (E-244) | löschen (Dateien und Texte) | ja | E-168, R0-10 §4.5, R0-17 DK-15 |

**Außerhalb des Datenbestands, nur Regel:**

| Datenart | Frist | Regel |
|---|---|---|
| Browser: „Externe Medien immer laden“ (CS-02, nur Modus `click_to_load`) | bis Widerruf | ohne Personenbezug; Widerruf durch die Person (§3.1) oder Setting aus |
| Browser: Prüfungspuffer (CS-03) | bis Abgabe, Bestätigung oder Logout | ohne Lösungen (PRIV-018) |
| Browser: Gerätecookie der Login-Härtung (E-242) | 180 d | technisch notwendig für die Sperrausnahme; Passwortwechsel macht es ungültig |
| WP-Datenschutzexport (ZIP) | WP-Kern 3 d | Course Captain legt keine eigene Exportdatei an |
| Backups | Betreiber | `backup_max_age_days` dokumentiert den Wert; Löschungen nach Restore über Tombstones (E-92) |
| Mail-Logs fremder SMTP-Plugins | Betreiber | Systemstatus-Hinweis (KOMP-056) |
| Kursinhalte, Revisionen, Fragen | dauerhaft | kein Personenbezug außer Autor-ID; Autor-ID bei Kontolöschung nach WP-Regel |

### 2.2 Delta zu `privacy.retention` (R0-27 §1.4)

| Unterschlüssel (neu) | Default | Bereich | Job | Quelle |
|---|---|---|---|---|
| `ended_grants_years` | 10 | 1–30 | täglich, ≤ 1 000 | E-243 |
| `removed_seats_days` | 365 | 30–3650 | täglich | E-243 |
| `closed_groups_years` | 3 | 1–30 | täglich | E-243 |
| `lifted_locks_days` | 30 | 1–365 | täglich | E-243 |

Damit hat `privacy.retention` 21 Unterschlüssel. Fest bleiben: Diagnosepaket 24 h, Exportdateien 24 h, Reset-Journal 30 d, Idempotenz 7 d, Undo 30 d, Debug-Dateien höchstens 7 d (Setting nur kleiner).

### 2.3 Regeln für alle Retention-Jobs

| Nr. | Regel |
|---|---|
| RR-1 | **Kein stilles „unbegrenzt“.** Ein Unterschlüssel darf `null` nur haben, wenn der Default ausdrücklich „aus“ lautet und ein Ersatz existiert (RT-02 neben RT-01). |
| RR-2 | **Verkürzen** zeigt vor dem Speichern die Anzahl betroffener Datensätze je Datenart (PRIV-008) und braucht R; Wirkung im nächsten Lauf. Gilt auch für Import und Tab-Reset (§4). |
| RR-3 | **Verlängern über den Default** braucht eine Begründung (E-245), die im Audit steht. Kein Blocker. |
| RR-4 | **Abhängigkeiten:** `raw_answers_years_after_course_end` ≤ `exam_attempts_years`; `certificates_years` ≥ `exam_attempts_years` ist nicht Pflicht, aber ein Hinweis, wenn Nachweise früher verschwinden als die zugrunde liegenden Versuche. Verletzung von Satz 1 wird abgelehnt. |
| RR-5 | **Protokoll:** jeder Lauf mit Wirkung schreibt `retention_applied` (`actor_type = system:cron`, Datenart, Anzahl, ältestes und jüngstes betroffenes Datum, keine IDs). Delta zu R0-13 §4.1 (Bereich `ops`). |
| RR-6 | **Systemstatus:** letzte Laufzeit, Anzahl, Rückstand je Datenart (PRIV-006). Ein Rückstand über 7 Tage erzeugt eine Site-Health-Warnung. |
| RR-7 | **Datenschutztext** (`wp_add_privacy_policy_content`) nennt die eingestellten Werte, nicht die Defaults (PRIV-024). |
| RR-8 | **Eraser vor Retention:** Ein Löschantrag wartet nie auf eine Frist. `keep_evidence_on_erasure` ist die einzige Ausnahme (E-150). |

---

## 3. Defaults je Bereich

### 3.1 Medien und Consent

| Nr. | Gegenstand | Default | Regel | Test |
|---|---|---|---|---|
| MC-1 | Externe Medien | **`always_load`** (E-45a) | Einbettung beim Seitenaufruf mit den Varianten aus MC-2; dokumentierte Ausnahme von Privacy by Default (R0-17 §6.5). Hinweis im Setup-Schritt 5 und im Feld; Offenlegung aller Provider in der `readme.txt` (WordPress.org) | MED-031, MED-032, PSD-022 |
| MC-1a | Externe Medien, Betreiberwahl `click_to_load` | – | Server rendert ohne Fremdverbindung (kein `iframe`, `track`, Fremd-Poster, `preconnect`, `dns-prefetch`); lokales Poster + Dienstname + Hinweistext mit Link (R0-10 §5.2.2) | MED-021, PSD-022 |
| MC-2 | Provider-Varianten | YouTube über `youtube-nocookie.com`, Vimeo mit `dnt=1` | in beiden Modi die datensparsame Variante; Provider-Registry ohne Option „Standard-Host“ | PSD-022 |
| MC-3 | Kästchen „immer laden“ (nur `click_to_load`) | angeboten (`external_media_remember = true`), **nie vorangehakt** | Wahl je Dienst in `localStorage`, ohne Personenbezug | PSD-023 |
| MC-4 | Widerruf durch Lernende | Knopf im Konto (L-20) „Gespeicherte Freigaben für externe Medien zurücksetzen“ | leert die Browserwahl sofort; zusätzlich Link im Hinweistext jedes geladenen Mediums | PSD-024 |
| MC-5 | WP Consent API (nur `click_to_load`, E-246) | aktiv, wenn vorhanden; Kategorie `privacy.consent_category` = `marketing` | Reihenfolge Consent API → Filter/JS-Ereignis → Klick zum Laden (R0-10 §5.3); Widerruf im Consent-Tool wirkt beim nächsten Seitenaufruf | MED-024, PSD-025 |
| MC-6 | Overrides | Medium und Kurs nur strenger (`always_load` → `click_to_load` → `link_only`) | PD-4 | PSD-026 |
| MC-7 | Ohne JavaScript | `always_load`: Einbettung; `click_to_load`/`link_only`: Link „Auf <Dienst> ansehen“ | im Modus `click_to_load` keine Einbettung | PSD-027 |
| MC-8 | Seiten-Cache | gleiches HTML für alle | bei `click_to_load` Entscheidung im Browser | PSD-028 |
| MC-9 | Avatare | aus | kein `get_avatar()` im Lernbereich (PRIV-017) | PSD-009 |
| MC-10 | Schriften, Icons, Skripte | lokal gebündelt | 0 CDN-Abrufe (CS-06) | PRIV-017 |
| MC-11 | Geschützte Medien | `protected` für alles, was an Lektion/Frage hängt (E-65) | Invariante; Direktabruf-Selbsttest, Nginx-Regel im Systemstatus | SET-122, MED-0xx |
| MC-12 | Cookie-Banner | keins (E-45) | Course Captain registriert sich nur bei Consent-Tools, zeigt kein eigenes Banner | MED-025 |
| MC-13 | Offenlegung | readme nennt jeden registrierten Provider (Dienst, Anbieter, übertragene Daten, Links zu Bedingungen/Datenschutz); Datenschutz-Textbaustein nennt die Dienste | Pflicht unabhängig vom Modus (E-45) | MED-032, PRIV-031 |

### 3.2 Secret-Handling

| Nr. | Gegenstand | Default | Regel | Test |
|---|---|---|---|---|
| SH-1 | Ablage Integrationsgeheimnisse | Konstante `COURSE_CAPTAIN_<CONNECTOR>_*`, sonst DB verschlüsselt mit `COURSE_CAPTAIN_SECRET_KEY` (E-87) | Konstante hat Vorrang, Feld gesperrt mit Hinweis „über `wp-config.php` festgelegt“ | PSD-029 |
| SH-2 | Fehlender `COURSE_CAPTAIN_SECRET_KEY` | Ableitung aus WP-Salts, Health-Warnung (R0-16 §3.2) | Setup-Assistent zeigt eine frisch erzeugte Zeile zum Einfügen in `wp-config.php` (Anzeige einmalig, nicht gespeichert) | PSD-031 |
| SH-3 | Schlüssel-Fingerabdruck | `course_captain_key_fingerprint` = HMAC(K, `"cc-fp-v1"`), gekürzt auf 16 Byte | bei Abweichung: Site Health kritisch „Pseudonyme und Tombstones nicht mehr abgleichbar“; Tombstones mit altem Schlüssel (`subject_key` und `email_key`) landen in einer Admin-Liste, statt geraten zu werden; Abhilfe: alten Schlüssel wieder einsetzen (T31-3) | PSD-032 |
| SH-4 | Anzeige | nur „gesetzt / nicht gesetzt / aus Konstante“ + letzte 4 Zeichen | nie Klartext in UI, REST, Site Health, Systemstatus, Diagnose, Audit, Fehlerlog | PSD-030, SET-017 |
| SH-5 | CLI | `secret`-Felder nur setzbar, nie lesbar (R0-27 SR-5) | `settings get` auf ein Secret liefert `<gesetzt>` | PSD-033 |
| SH-6 | Settings-Export/-Import | Secrets nie, auch nicht als Platzhalterwert; `"<gesetzt>"` im Import wird ignoriert | §4 | PSD-059 |
| SH-7 | Tokens | nur Hash (E-112); Klartext nur in der einen Mail | nie exportiert, nie geloggt | SEC-021 |
| SH-8 | Monitor-Schlüssel | Konstante, nur im Header | `?key=` wird ignoriert | SEC-035 |
| SH-9 | Abmeldelink-Signatur | HMAC aus `wp_salt('auth')` mit Zweckkennung (R0-27 §3.7) | Salt-Rotation macht alte Links ungültig; Hinweis in der Betreiberdoku | – |
| SH-10 | Kopie/Restore auf andere Site-Identität | DB-Secrets „nicht bestätigt“ bis Admin bestätigt; Haltemodus (E-85) | Staging erzeugt eigene Werte (R0-16 §3.1) | REC-0xx |
| SH-11 | Audit von Secrets | `integration_saved` / `integration_secret_rotated` nur „geändert“ | kein Hash des Werts im Audit (Hash wäre bei kurzen Werten rückrechenbar) | PSD-030 |

### 3.3 RBAC-Defaults

| Nr. | Gegenstand | Default | Regel | Test |
|---|---|---|---|---|
| RB-1 | Aktivierung | Caps nur für `administrator` (alle 24) und die vier `ccap_`-Rollen nach R0-07 §3.1 (E-83) | `editor`, `author`, `contributor`, `shop_manager` erhalten keine `ccap_*`-Cap | RBAC-097, PSD-034 |
| RB-2 | Lernende | WP `subscriber`, keine `ccap_*`-Cap | Zugang nur über Grant (E-84) | SET-080 |
| RB-3 | Support | `ccap_support` ohne #14 (E-144) | fremde Antworten nie im Standard; Versuchsdetails nur mit #14 und Pflichtbegründung (E-82) | SET-082 |
| RB-4 | Hochrisiko-Caps | #14, #19…#24 | Vergabe an andere Rollen nur durch `administrator`, R-Dialog mit Liste, Audit `role_caps_changed` | PSD-035 |
| RB-5 | Datenschutz- und Härtungs-Tab | #19 | Felder `trusted_proxies`, `delete_data_on_uninstall` und der Tab Website-Härtung (`hardening.*`, zusätzlich `manage_options`, E-262) nur `administrator` | PSD-008 |
| RB-6 | Audit-Ansicht | #21 (Admin, Support) | keine E-Mail- oder IP-Spalte; Export nur #21 + #19 mit Begründung (E-140) | PSD-038 |
| RB-7 | `unfiltered_html` | nie für `ccap_*`-Rollen | auch nicht nach Tab-Reset oder Import | PSD-037 |
| RB-8 | Tab-Reset Rollen | entfernt nur Caps aus dem Manifest `course_captain_role_grants` | Caps aus Rollen-Plugins bleiben; `administrator` unverändert | PSD-036 |
| RB-9 | Staff-Konten | Schutzliste der automatischen Kontolöschung um alle Konten mit `ccap_*`-Caps ergänzt | PRIV-014 | PRIV-014 |
| RB-10 | Datenschutzanträge | Person selbst (M-23), auch bei Lernsperre | WP-Kern-Caps für fremde Anträge | RBAC-086 |

### 3.4 Härtungsmodul (E-10)

**Grundsätze**

| Nr. | Regel |
|---|---|
| HM-0 | **Erst nach Bestätigung aktiv** (E-260): Bis der Admin im Setup-Assistenten (Schritt 6) oder im Tab „Website-Härtung“ speichert, wirken alle Merkmale als `off` (`hardening.confirmed_at = null`); der Assistent schlägt „an“ (`auto`) vor, außer für Merkmale mit erkanntem Konflikt. |
| HM-1 | **Merkmale einzeln schaltbar** (`auto`, `force`, `off`). `auto` = aktiv, solange kein Konflikt erkannt ist; bei Konflikt **zurückgehalten** (`held`). `force` = aktiv trotz Konflikt (R-Bestätigung, dauerhafter Hinweis). `off` = aus. |
| HM-2 | **Zurückhalten heißt vollständig:** Ein zurückgehaltenes Merkmal registriert keine Hooks, setzt keine Header, zählt nicht mit. Es gibt keinen „halben“ Modus. |
| HM-3 | **Erkennung bei jedem Wechsel** (Aktivierung/Deaktivierung von Plugins, Theme-Wechsel, Update) und täglich; Ergebnis mit Grund je Merkmal im Systemstatus und in Site Health. Übergänge `active ↔ held` schreiben Audit `hardening_state_changed` (`actor_type = system:cron` bzw. `system`). |
| HM-4 | **Nur eigene Mittel:** keine Schreibzugriffe auf `.htaccess`, `wp-config.php`, Serverkonfiguration oder Optionen fremder Plugins (LB-K-05). Deaktivieren von Course Captain beendet alle Effekte sofort. |
| HM-5 | **Fail-closed für Login, Reset, Registrierung** (E-10, E-46; außerdem Claim, Einlösen, Einladungen, Datenschutzanträge nach R0-17 §3.4). Ausnahme gegen Aussperren: gültiges Gerätecookie und Notausschalter per Konstante oder WP-CLI (§3.5, E-242, E-263). |
| HM-6 | **Datensparsam:** Zähler nur als gesalzener Hash aus IP (IPv6 je /64) bzw. Konto, Löschung bei Fensterende (RT-40); keine IP im Audit oder Log; Sperren werden nicht einzeln auditiert, nur gezählt (Site Health: Sperren je 24 h). |
| HM-7 | **Neutrale Antworten:** gleiche Meldung und vergleichbare Antwortzeit für unbekannte und bekannte Konten (Dummy-Passwortprüfung); keine Mail an Betroffene oder Admin je Sperre. |
| HM-8 | **Kompatibel mit 2FA und SSO:** gezählt werden nur fehlgeschlagene Erstfaktor-Prüfungen über `wp_authenticate`; SSO-Anmeldungen ohne Passwort zählen nicht. |

**Merkmale, Defaults und Grenzen** (Grenzen als Verhalten aus LB, E-01; Änderung nur per Filter `course_captain_hardening_limits`, Anzeige im Tab Erweitert, Δ-7)

| Merkmal | Default-Modus | Wirkung, wenn aktiv | Grenzen (Fenster) | Zählerausfall | konfliktauslösend (Auswahl, Liste in R1-30 gepflegt) |
|---|---|---|---|---|---|
| `login_limit` | `auto` nach Bestätigung (E-240, E-260) | alle Wege über `wp_authenticate` (wp-login, XML-RPC, REST Basic/Anwendungspasswort, WooCommerce-Konto) mit gemeinsamen Zählern | 10 je IP, 5 je Konto+IP, 30 je Konto in 15 min; Gerätecookie umgeht nur IP- und Konto+IP-Sperre | **closed** (Ausnahme E-242) | Wordfence (Login Security/Brute Force), Solid Security, Limit Login Attempts Reloaded, Loginizer, All-In-One WP Security, WP Cerber, Shield, Jetpack-Modul „Protect“; Filter-Meldung eines Plugins (`course_captain_hardening_conflicts`) |
| `reset_limit` | `auto` nach Bestätigung | „Passwort vergessen“: gleiche Bestätigung für unbekannte Konten, Antwort vor dem Mailversand | 10 je IP, 3 je Konto+IP, 10 je Konto je h | **closed** | wie `login_limit` |
| `registration_limit` | `auto` nach Bestätigung, wirksam nur bei `users_can_register` (E-241) | Registrierung über wp-login und Kern-REST | 10 je IP je h | **closed** | wie `login_limit`; CAPTCHA-Plugins zählen nicht als Konflikt |
| `xmlrpc` | `auto` nach Bestätigung | authentifizierte XML-RPC-Methoden aus, `system.multicall` und `pingback.ping` abgewiesen, Header `X-Pingback` entfernt; öffentliche Methoden bleiben | – | – | Jetpack aktiv (braucht XML-RPC); anderes Plugin deaktiviert XML-RPC bereits (dann `held:already_restricted`) |
| `site_headers` | `auto` nach Bestätigung | **vier** Header (E-261, Auswahl wie R0-30 §3.3.10): website-weit `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: SAMEORIGIN`; `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()` **nur auf Course-Captain-Antworten** (E-309); Course Captain setzt keinen Header, den ein anderes Plugin oder der Server bereits setzt (E-307); **kein** HSTS, keine website-weite CSP | – | – | **je Header**: Header bereits in `headers_list()` oder im Loopback-Selbsttest (Server-/CDN-Header) vorhanden → nur dieser Header `held` |
| ~~`user_enumeration`~~ | **nicht in R1** (R4-Pool nach E-261; E-241 insoweit überholt, **E-308**) | – | – | – | – |

**Nicht im Modul** (Zuständigkeit anderswo): Anwendungspasswörter einschränken (WP-Kern), Benutzer-Sitemap und Feed-Autor (SEO-Plugin, LB-K-05), Content-Security-Policy außerhalb eigener Antworten, HSTS (Hosting).

**Eigene Antworten unabhängig vom Modul:** Header nach R0-17 §3.5 und `privacy.csp_mode` gelten für Lernbereich, REST `course-captain/v1`, `/cc-file/` und Verifikation immer, auch bei `site_headers = off` (PSD-052).

**Konfliktmatrix (Delta zu R0-20 §2.6, ersetzt dort den Satz „CC Free begrenzt keine Logins“)**

| Kombination | `login_limit` / `reset_limit` | `xmlrpc` | `site_headers` | Systemstatus |
|---|---|---|---|---|
| kein Security-Plugin | aktiv | aktiv | aktiv | „Härtung aktiv“ |
| Wordfence free (KOMP-050) | `held:wordfence` | aktiv, außer Wordfence deaktiviert XML-RPC-Auth | je Header | Hinweis „Login-Schutz durch Wordfence“ |
| Solid Security (KOMP-051) | `held:solid-security` | `held:already_restricted`, wenn dort aktiv | je Header | wie oben |
| LLAR, Loginizer, AIOS, WP Cerber, Shield (KOMP-053) | `held:<slug>` | aktiv | je Header | wie oben |
| Jetpack mit „Protect“ | `held:jetpack-protect` | `held:jetpack` | je Header | wie oben |
| Jetpack ohne „Protect“ | aktiv | `held:jetpack` | je Header | Hinweis XML-RPC |
| Hosting setzt Header (Loopback) | – | – | betroffene Header `held:server` | Liste der Header |
| Multisite (offiziell nicht unterstützt, E-07) | `held:multisite`, außer auf der Hauptsite aktiv | wie Hauptsite | je Site | Hinweis |
| `force` trotz Konflikt | aktiv, Warnung „doppelte Sperre möglich“ | aktiv | aktiv | dauerhafter Hinweis, Audit |

### 3.5 Notausgang und Wiederherstellung

| Nr. | Regel |
|---|---|
| NA-1 | `define('COURSE_CAPTAIN_HARDENING', 'off');` schaltet alle Merkmale ab, unabhängig von Settings und Import. Site Health zeigt dauerhaft „Härtung per Konstante aus“; beim ersten Erkennen Audit `hardening_kill_switch_detected`. |
| NA-2 | WP-CLI `wp course-captain hardening status|disable|unlock --all` (Akteur `system:cli`, #23; E-263): `disable` schaltet alle Merkmale ab, `unlock --all` leert Zähler und Sperren; Audit `maintenance_action`. |
| NA-3 | Zählerausfall im Login: unbekanntes Gerät → `503` mit neutralem Hinweis; gültiges Gerätecookie → Login möglich (E-242); Site-Health-Warnung, höchstens ein Logeintrag je 10 min (R0-17 §3.4). |
| NA-4 | Restore/Umzug: Zähler sind flüchtig und werden nicht wiederhergestellt; Modus kommt aus den Settings des Dumps. |

---

## 4. Export- und Reset-Regeln ohne Secrets und PII

Ergänzt R0-27 §2.4/§2.5; dort Geregeltes wird nicht wiederholt.

### 4.1 Export

| Nr. | Regel |
|---|---|
| EX-1 | **Klassen:** `public` und `internal` immer; `personal` (Absender, Fußzeile, Aussteller-Freitext, Mailvorlagen) nur per Opt-in; `secret` nie (SH-6); Rollen-Tab nur per Opt-in (R0-27 §2.5). |
| EX-2 | **Umgebungsgebunden, nie exportiert:** `privacy.trusted_proxies`, `advanced.file_delivery`, `advanced.debug`, `general.delete_data_on_uninstall`, Konstanten. Grund: auf einer anderen Site falsch und sicherheitswirksam (XFF-Spoofing, Auslieferung ohne Selbstprüfung). Neue Schema-Eigenschaft `export: 'never_env'`. |
| EX-3 | **Härtung** wird exportiert (Modi), Wirkzustand und Konfliktgründe nicht. |
| EX-4 | **Retention** wird exportiert; im Kopf steht der Hinweis „Fristen sind vom Betreiber der Zielsite zu bestätigen“. |
| EX-5 | **Keine Personendaten im Datei-Kopf:** `site_hash` statt URL, kein Name oder Login der exportierenden Person (Audit hält sie fest). |
| EX-6 | **Ablage:** Browser-Download ohne Serverkopie; CLI schreibt mit dem Safe-Export-Writer (`0600`, außerhalb des Webroots, `fopen 'x'`). |

### 4.2 Import

| Nr. | Regel |
|---|---|
| IM-1 | Jede Änderung in Richtung PD-2 braucht im Import dieselbe Bestätigung wie in der UI (PD-3). Ohne Bestätigung wird das Feld übersprungen und im Bericht genannt; der Rest des Tabs wird importiert. |
| IM-2 | Betroffen sind mindestens: `privacy.auto_erase_on_confirm` an, `privacy.keep_evidence_on_erasure` an (mit Begründung aus der Datei, die im Dialog bestätigt wird), Verkürzen und Verlängern von Fristen (RR-2, RR-3), `hardening.*` auf `force`/`off`, `privacy.show_avatars` an, `privacy.csp_mode` `report_only`, `privacy.consent_category` `functional`, `exams.feedback` Δ-1, `roles.cap_assignments` mit Hochrisiko-Caps. |
| IM-3 | Werte aus EX-2 in einer manipulierten Datei werden ignoriert, nie übernommen. |
| IM-4 | Abweichender `site_hash` → Hinweis „Datei stammt von einer anderen Site“; keine Sperre. |
| IM-5 | Ein Import schreibt je Tab einen Undo-Snapshot (RT-42) und Audit `settings_imported` (Tabs, Schlüssel, keine Werte der Klasse `personal`). |

### 4.3 Reset und Diagnose

| Nr. | Regel |
|---|---|
| RS-1 | Reset des Tabs **Datenschutz & Sicherheit** listet die Abweichungen. Würde der Reset eine Frist verkürzen, gelten RR-2 (Anzahl) und R. Würde er `keep_evidence_on_erasure` abschalten, nennt der Dialog die Folge für künftige Löschanträge. |
| RS-2 | Reset berührt nie: Tombstones, Journal, Audit, Datenschutzanträge, Opt-outs der Lernenden, Browserwahl externer Medien, Zähler der Härtung, Secrets, Konstanten. |
| RS-3 | Reset der Härtung setzt alle Merkmale auf den Default (E-240); ein laufender Notausschalter bleibt wirksam. |
| RS-4 | Undo-Snapshot (RT-42) ist nie Teil von Export, Diagnose oder REST; Uninstall mit Datenlöschung entfernt ihn (Manifest R0-16 §2.5). |
| RS-5 | Diagnosepaket (R0-13 §2.2): Settings nach EX-1 ohne Opt-ins, `personal` als `"<gesetzt>"`, Secrets als `"<gesetzt>"`/`"<leer>"`/`"<konstante>"`, Härtung als Modus und Wirkzustand (Δ-9). |

---

## 5. Testmatrix PSD-001…PSD-065 (Ziel-Task R1-34, Härtung R1-45)

Art: **P** positiv, **N** negativ. Ebene: **PU** PHPUnit ohne WP, **PI** PHPUnit mit WP-Testsuite, **PW** Playwright, **MS** Multisite. Bestehende Fälle (SET, PRIV, MED, RBAC, SEC) werden referenziert, nicht dupliziert. Ziel-Task ist bei allen **R1-34**; „auch“ nennt Tasks, die denselben Fall als Eingangsbedingung nutzen.

| ID | Art | Fall | Erwartung | Ebene | auch | Bezug |
|---|---|---|---|---|---|---|
| **Defaults, Bestätigung, Audit** |||||||
| PSD-001 | P | Frischinstallation, Tabs Datenschutz & Sicherheit, Prüfungen, Einschreibung, Rollen, Zertifikate, E-Mails, Erweitert lesen | alle Werte aus §1.2/§1.3 = Default; Schema-Snapshot unverändert | PI | R1-31 | §1.2 |
| PSD-002 | P | Schema-Scan aller Felder | jedes Feld mit Bezug M/U/S/Z/Sec hat `audit = true`, `risk` laut §1.2, `privacy`-Klasse, Hilfetext-Schlüssel | PU | R1-31 | PD-5 |
| PSD-003 | N | Medium-Override `external_media_mode = click_to_load` bei global `link_only` (Scope global → Medium, R0-28 #28); Kurs-Override `holder_name_policy` lockerer | abgelehnt; strengerer Override gespeichert | PI | R1-33 | PD-4 |
| PSD-004 | N | POST auf R-Feld (`auto_erase_on_confirm`, `keep_evidence_on_erasure`, `trusted_proxies`, `csp_mode`, `file_delivery`, `show_avatars`, `exams.feedback` Δ-1, `hardening`) ohne bzw. mit fremdem, abgelaufenem, wiederverwendetem Bestätigungstoken | jeweils abgelehnt, Feldfehler; mit gültigem Token gespeichert | PI | R1-31 | PD-3 |
| PSD-005 | P | Bestätigungsdialog per Tastatur und Screenreader | Folge und Anzahl werden vorgelesen; Fokus kehrt zurück; Abbrechen ändert nichts | PW | R1-29 | Konzept 43.2 |
| PSD-006 | P | Änderung je Feld aus §1.2 | `settings_saved` mit Vorher/Nachher (`public`/`internal`), „geändert“ (`personal`), nie Secret; ohne Änderung kein Eintrag | PI | R1-31 | SR-6 |
| PSD-007 | N | CLI `settings set` auf R-Feld ohne `--yes`, auf `delete_data_on_uninstall` ohne `--confirm=<site>` | abgelehnt, Exit-Code ≠ 0, kein Audit | PI | R1-31 | PD-3 |
| PSD-008 | N | `ccap_course_manager` und `ccap_support` speichern Tab Datenschutz; Nicht-Admin mit #19 ändert `trusted_proxies`, `hardening` | 403 bzw. Feld abgelehnt | PI | R1-03 | RB-5 |
| PSD-009 | P | `show_avatars` einschalten | R-Dialog mit Drittanbieter-Hinweis; danach Gravatar nur mit Einstellung; aus: 0 Gravatar-Anfragen | PW | R1-16 | Δ-6, PRIV-017 |
| PSD-010 | P | `exams.feedback.show_correct_answers_when = after_submit` | R-Dialog; neue Versuche nutzen den Wert, laufende Snapshots unverändert | PI | R1-09 | Δ-1, E-147 |
| **Retention** |||||||
| PSD-011 | P | Default je Unterschlüssel `privacy.retention` (21) | Werte nach §1.4 R0-27 und §2.2 | PU | R1-17 | §2.2 |
| PSD-012 | P | Zeitreise je Datenart RT-04…RT-43 mit Fristende ± 1 Tag | vor Fristende unverändert, danach Löschart laut §2.1; Batchgrenze; zweiter Lauf ohne Wirkung | PI | R1-17 | PRIV-006 (erweitert) |
| PSD-013 | P | Kursende (E-244 = späterer von Ende des letzten aktiven Grants und letzter Aktivität im Kurs): (a) aktiver unbefristeter Grant, (b) Grant läuft ab, letzte Aktivität davor, (c) Abschluss bei weiter aktivem Grant, (d) neuer Grant nach Ablauf | (a) keine Frist; (b) Frist ab Grant-Ende; (c) keine Frist, solange der Grant aktiv ist; (d) Frist ruht, solange der neue Grant aktiv ist | PI | R1-17 | E-244 |
| PSD-014 | P | Antwortlog Lernmodus nach Frist (RT-04) | `ccap_attempt_answer` gelöscht; Smarttrainer-Stand und Fortschritt unverändert | PI | R1-17 | R0-06 D-5 |
| PSD-015 | N/P | Frist über Default verlängern ohne bzw. mit Begründung | ohne abgelehnt; mit gespeichert, Begründung im Audit | PI | R1-17 | E-245 |
| PSD-016 | P | Frist verkürzen in UI, per Import, per Tab-Reset | jeweils Anzahl betroffener Datensätze vor dem Speichern, R-Dialog | PW | R1-17 | RR-2, PRIV-008 |
| PSD-017 | N | `learning_progress` per UI, CLI oder Import ändern | kein Feld; CLI und Import lehnen ab | PI | R1-31 | RT-01 |
| PSD-018 | N | `raw_answers_years_after_course_end` > `exam_attempts_years` | abgelehnt mit Feldfehler | PI | R1-17 | RR-4 |
| PSD-019 | P | Retention-Lauf mit Wirkung | `retention_applied` mit Datenart, Anzahl, Datumsspanne, ohne IDs; Systemstatus zeigt Rückstand; Rückstand > 7 d → Site Health | PI | R1-38 | RR-5, RR-6 |
| PSD-020 | P | Fristen ändern, Datenschutzseite neu generieren | Textbaustein nennt eingestellte Werte | PI | R1-17 | RR-7, PRIV-024 |
| PSD-021 | P | `keep_evidence_on_erasure` an, Löschantrag, Zeitreise bis Fristende RT-06/RT-09 | pseudonyme Nachweise bis Fristende, danach gelöscht | PI | R1-17 | RT-07, RT-10 |
| **Medien und Consent** |||||||
| PSD-022 | P | Lektion mit YouTube, Vimeo, `video_url`; Netzwerkprotokoll (a) Default `always_load`, (b) `click_to_load` vor und nach Klick | (a) Einbettung beim Laden, nur `youtube-nocookie.com` bzw. Vimeo mit `dnt=1` bzw. Datei-Host; (b) vor Klick 0 Fremdanfragen, nach Klick wie (a) | PW | R1-07 | MC-1, MC-2, MED-021 |
| PSD-023 | P | Modus `click_to_load`: Kästchen „immer laden“ im Ausgangszustand; Klick ohne Haken, nächste Seite | Kästchen nicht angehakt; nächste Seite wieder Platzhalter | PW | R1-07 | MC-3 |
| PSD-024 | P | „immer laden“ gesetzt, danach Konto → „Gespeicherte Freigaben zurücksetzen“ | `localStorage` geleert; nächste Seite Platzhalter | PW | R1-16 | MC-4 |
| PSD-025 | P/N | Modus `click_to_load`, Consent API aktiv; Kategorie `marketing` erteilt/verweigert; zusätzlich Modus `always_load` (Consent wird nicht abgefragt); Setting auf `statistics`; Setting auf `functional` ohne Bestätigung | lädt bzw. Platzhalter je Kategorie; `functional` nur mit R-Dialog | PW | R1-30 | MC-5, E-246 |
| PSD-026 | N/P | Kurs setzt `click_to_load` bei global `link_only`; Kurs setzt `click_to_load` bei global `always_load` | erstes abgelehnt (nur strenger); zweites gespeichert | PI | R1-33 | MC-6 |
| PSD-027 | P | JavaScript aus, Modus `click_to_load` | Link „Auf <Dienst> ansehen“, keine Einbettung, 0 Fremdanfragen | PW | R1-16 | MC-7 |
| PSD-028 | P | Modus `click_to_load`, Seiten-Cache aktiv, Nutzer A hat zugestimmt, Nutzer B nicht | identisches HTML ohne Fremdhost; B sieht Platzhalter | PW | R1-30 | MC-8 |
| **Secrets** |||||||
| PSD-029 | P | Secret per Konstante und per DB | UI: Status + letzte 4 Zeichen; Feld bei Konstante gesperrt mit Hinweis | PI | R1-23 | SH-1, SH-4 |
| PSD-030 | N | bekannte Secret-Werte (Integration, `COURSE_CAPTAIN_SECRET_KEY`, Monitor-Key, Abmelde-HMAC, Tombstone-K) einspeisen; Suche in Settings-Export, Diagnose, Site Health, REST, Audit, Fehler- und Debug-Log | 0 Treffer (auch keine Hashes der Werte) | PI | R1-23 | SH-4, SH-11, SET-017 |
| PSD-031 | P | ohne `COURSE_CAPTAIN_SECRET_KEY` | Health-Warnung; Setup zeigt einmalig eine Zeile zum Einfügen, speichert sie nicht; Pseudonymisierung über Salt-Ableitung | PI | R1-23 | SH-2 |
| PSD-032 | N | Salt-Rotation bzw. neue Konstante nach vorhandenen Tombstones | Fingerabdruck weicht ab → Site Health kritisch; Tombstone-Abgleich meldet nicht prüfbare Einträge in der Admin-Liste; keine Falschlöschung | PI | R1-23 | SH-3, T31-3 |
| PSD-033 | N | CLI `settings get` auf Secret-Feld | Ausgabe `<gesetzt>`; `set` wirkt, Audit „geändert“ | PI | R1-31 | SH-5 |
| **RBAC** |||||||
| PSD-034 | P | Tab „Rollen & Rechte“ nach Aktivierung | zeigt Defaults nach R0-07 §3.1, Hochrisiko-Caps markiert; `editor`/`shop_manager` ohne `ccap_*` | PW | R1-03 | RB-1, RBAC-097 |
| PSD-035 | P/N | #14 an `ccap_trainer` durch `administrator`; #19 durch `ccap_course_manager` | erstes mit R-Dialog und Audit; zweites 403 | PI | R1-03 | RB-4, SET-081 |
| PSD-036 | P | Rollen-Plugin gibt `editor` zusätzlich `ccap_view_reports`; danach Tab-Reset Rollen | nur Manifest-Caps entfernt; Fremdvergabe bleibt | PI | R1-36 | RB-8 |
| PSD-037 | N | `unfiltered_html` bei `ccap_`-Rollen nach Aktivierung, Tab-Reset, Settings-Import | nie vorhanden | PI | R1-03 | RB-7 |
| PSD-038 | P | Audit-Ansicht als `ccap_support` | keine E-Mail-/IP-Spalte; Export-Knopf fehlt (ohne #19) | PW | R1-38 | RB-6 |
| **Härtungsmodul** |||||||
| PSD-039 | P/N | ohne Security-Plugin: 10 Fehlversuche je IP in 15 min, 11. Versuch | 11. gesperrt mit neutraler Meldung; nach Fensterende frei | PI | R1-30 | §3.4 |
| PSD-040 | P/N | 5 Fehlversuche Konto+IP; 30 je Konto über verteilte IPs | jeweils Sperre der passenden Dimension | PI | R1-30 | §3.4 |
| PSD-041 | N | Antwort und Antwortzeit für unbekanntes und bekanntes Konto | gleicher Text; Laufzeitunterschied im Median < 50 ms (Dummy-Prüfung) | PI | R1-30 | HM-7 |
| PSD-042 | P | Fehlversuche über wp-login, XML-RPC (wenn erlaubt), REST mit Anwendungspasswort, WooCommerce-Konto | gemeinsame Zähler | PI | R1-30 | §3.4 |
| PSD-043 | P/N | Passwort-Reset: unbekanntes Konto; 4 Anfragen Konto+IP; 11 je IP | gleiche Bestätigung; Grenzen greifen; Antwort vor Mailversand | PI | R1-30 | `reset_limit` |
| PSD-044 | P | Zählerspeicher fällt aus (DB-/Cache-Fehler simuliert) | Login unbekanntes Gerät 503; mit gültigem Gerätecookie Login möglich; Lernlimits fail-open; Site-Health-Warnung; Log gedrosselt | PI | R1-42 | HM-5, NA-3, E-242 |
| PSD-045 | P | Konstante `COURSE_CAPTAIN_HARDENING = off` | alle Merkmale aus, keine Hooks; Site Health dauerhaft; Audit beim ersten Erkennen; Import kann es nicht ändern | PI | R1-30 | NA-1 |
| PSD-046 | P/N | Gerätecookie nach erfolgreichem Login; Passwortwechsel; manipuliertes Cookie; anderes Konto | Cookie `HttpOnly`, `Secure` (bei HTTPS), `SameSite=Lax`, 180 d; nach Passwortwechsel ungültig; Manipulation wirkungslos; umgeht nur IP- und Konto+IP-Sperre | PI | R1-30 | E-242 |
| PSD-047 | P | Fixture-Stubs je Plugin der Konfliktmatrix aktivieren und deaktivieren | `held:<slug>` ohne CC-Hooks an `authenticate`/`lostpassword_post`; nach Deaktivierung wieder aktiv; Audit `hardening_state_changed` | PI | R1-30 | HM-1…HM-3 |
| PSD-048 | P | `force` trotz erkanntem Konflikt | R-Dialog mit Warnung „doppelte Sperre“; aktiv; dauerhafter Hinweis; Audit | PW | R1-30 | HM-1 |
| PSD-049 | P | `login_limit = off` ohne Security-Plugin | R-Dialog; Audit; Site Health „Anmeldung ohne Begrenzung“ | PI | R1-30 | HM-1 |
| PSD-050 | P/N | XML-RPC `auto`: authentifizierter Aufruf, `system.multicall`, `pingback.ping`, öffentliche Methode; Jetpack-Stub aktiv | erste drei abgewiesen, öffentliche bleibt, kein `X-Pingback`; mit Jetpack `held:jetpack` | PI | R1-30 | §3.4 |
| PSD-051 | P/N | `site_headers` `auto`; ein Header kommt schon von einem anderen Plugin bzw. vom Server (Loopback) | fehlende Header gesetzt; vorhandene nicht doppelt, `held:server` bzw. `held:<plugin>` je Header; kein HSTS | PI | R1-30 | §3.4 |
| PSD-052 | P | `site_headers = off` | CC-eigene Antworten tragen weiter die Header nach R0-17 §3.5 und die CSP nach `csp_mode` | PI | R1-16 | §3.4, SEC-007 |
| PSD-053 | P | `registration_limit` bei `users_can_register` aus bzw. an | aus: Merkmal inaktiv (Status „nicht nötig“); an: 11. Registrierung je IP/h abgewiesen | PI | R1-30 | E-241 |
| PSD-054 | P | Gäste rufen `/wp/v2/users` und `?author=1` ab | unverändert WordPress-Verhalten (Enumeration-Schutz nicht in R1, E-261) | PI | R1-30 | E-261 |
| PSD-055 | P | Multisite mit Plugin auf Unterseite | Login-Merkmale `held:multisite`; Header je Site | MS | R1-36 | §3.4 |
| PSD-056 | N | Zählertabelle nach Login-Flut, Audit, Logs | nur gesalzene Hashes, nach Fensterende gelöscht; keine IP in Audit/Log; keine Mail je Sperre | PI | R1-17 | HM-6, PRIV-027 |
| **Export, Import, Reset** |||||||
| PSD-057 | P | Export ohne Opt-ins | keine `personal`-Felder, kein Secret, keine Felder aus EX-2, kein Rollen-Tab; Härtung als Modus, Retention mit Hinweis | PI | R1-36 | EX-1…EX-4 |
| PSD-058 | P/N | Import von anderer Site mit `auto_erase` an, `keep_evidence` an, kürzerer Frist, `hardening.login_limit = off`, `show_avatars` an, Hochrisiko-Cap | Hinweis andere Site; jedes Feld einzeln bestätigungspflichtig; ohne Bestätigung übersprungen und im Bericht | PW | R1-36 | IM-1, IM-2, IM-4 |
| PSD-059 | N | manipulierte Datei mit Secret-Platzhalter, `trusted_proxies`, `delete_data_on_uninstall = true`, `file_delivery` | alles ignoriert, Bericht nennt die Felder | PI | R1-36 | IM-3, SH-6 |
| PSD-060 | P | Reset Tab Datenschutz & Sicherheit bei verlängerten und verkürzten Fristen, `keep_evidence` an | Liste der Abweichungen; Verkürzung mit Anzahl und R; Tombstones, Journal, Audit, Anträge, Opt-outs, Browserwahl, Zähler unverändert; Undo stellt wieder her | PW | R1-36 | RS-1…RS-3 |
| PSD-061 | N | Undo-Snapshot in Export, Diagnose, REST suchen; Uninstall mit Datenlöschung | nicht enthalten; nach Uninstall entfernt; nach 30 d gelöscht | PI | R1-23 | RS-4, RT-42 |
| PSD-062 | P | Diagnosepaket mit gesetztem Absender, Secret, `held`-Härtung | `"<gesetzt>"` für `personal` und Secret; Härtung als Modus + Wirkzustand ohne Daten fremder Plugins | PI | R1-38 | RS-5, Δ-9 |
| PSD-063 | P | Export per Browser und per CLI | Browser ohne Serverkopie; CLI-Datei `0600` außerhalb Webroot, kein Überschreiben vorhandener Datei | PI | R1-36 | EX-6 |
| PSD-064 | N | Export-Kopf auf Personendaten prüfen | nur `site_hash`, Versionen, Zeit; kein Name, Login, E-Mail, keine URL | PU | R1-36 | EX-5 |
| PSD-065 | P | Frischinstallation ohne Assistent; danach Assistent Schritt 6 mit und ohne erkanntes Security-Plugin speichern | vorher alle Merkmale `off` (keine Hooks, keine Header); danach `auto`, betroffene Merkmale `held:<slug>`; `confirmed_at` gesetzt, Audit | PI | R1-45, R1-32 | HM-0, E-260 |

Ergänzt: **SET-119** (neu gefasst in R0-27, dazu PSD-039…056, PSD-065). Erweitert: PRIV-006 (PSD-012), PRIV-008 (PSD-016), PRIV-027 (PSD-056), SET-017 (PSD-030).

---

## 6. Entscheidungen (beschlossen)

Alle Fragen sind entschieden (`ENTSCHEIDUNGEN.md` §36, „Von Claude entschieden“, 16.11.2026). Genutzt außerdem: E-01, E-10, E-20, E-28, E-38, E-43…E-46/E-45a, E-51, E-65, E-79, E-81…E-92, E-97, E-112, E-116, E-119, E-138, E-140, E-143…E-154, E-168, E-207, E-215…E-224, E-260…E-263.

| Frühere ID | Beschluss | Inhalt | Wirkt in | Tasks |
|---|---|---|---|---|
| D31-1 | **E-239** | neue Task **R1-45** „Härtungsmodul“ in Free | §3.4, PSD-039…056 | R1-34, R1-45 |
| D31-2 | **E-240** (angepasst an E-260) | zunächst alles aus, bis der Admin im Assistenten bestätigt; danach je Funktion `auto` (Rückzug bei erkanntem Security-Plugin) | §1.3, HM-0, §3.4 | R1-34, R1-45 |
| D31-3 | **E-241** (ergänzt durch E-261, insoweit überholt nach **E-308**) | Registrierungslimit und Gerätecookie ja; Enumeration-Schutz nach E-261 im R4-Pool | §3.4 | R1-45 |
| D31-4 | **E-242** (ergänzt durch E-263) | Notausschalter `COURSE_CAPTAIN_HARDENING` in `wp-config.php` und WP-CLI; bekannte Geräte dürfen weiter | §3.5 | R1-34, R1-45 |
| D31-5 | **E-243** | beendete Grants 10 J., entfernte Plätze 365 d, geschlossene Gruppen 3 J., aufgehobene Lernsperren 30 d, Exportdateien 24 h | §2.1, §2.2 | R1-17, R1-34 |
| D31-6 | **E-244** | „Kursende“ = späterer von: letzter aktiver Grant endet oder letzte Aktivität im Kurs (abweichend von der früheren Option a) | RT-04/05, PSD-013 | R1-17, R1-34 |
| D31-7 | **E-245** | Verlängern über den Default nur mit Pflichtbegründung und Audit | RR-3, PSD-015 | R1-34 |
| D31-8 | **E-246** | Feld `privacy.consent_category`, Default `marketing`; wirkt nur bei `click_to_load` (E-45a) | §1.3, MC-5 | R1-34, R1-45 |

### 6.1 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-31-1 | Enumeration-Schutz: E-241 (Opt-in) gegen E-261 (R4-Pool) | widersprüchliche Beschlüsse; hier gilt E-261 | Auftraggeber (Klarstellung) → **erledigt durch E-308** |
| OP-31-2 | Inhalt des vierten Headers `Permissions-Policy` | E-261 nennt nur „4 Security-Header“; R0-30 nennt Permissions-Policy, der frühere Stand hier schloss ihn aus | Architektur (R1-45) → **erledigt durch E-309** (`camera=(), microphone=(), geolocation=(), payment=()` nur auf Course-Captain-Antworten, §3.4) |
| OP-31-3 | Frist für Aufgaben-Abgaben (RT-45) | E-168 regelt keine Frist; Gleichsetzung mit Rohantworten (E-51) ist Annahme; juristische Bestätigung durch den Betreiber | Auftraggeber/Betreiber → **erledigt durch E-289** (wie Rohantworten); juristische Bestätigung: **Auftraggeber-Aktion (Blocker für Gate A)**: Gate A-9, AG-07 |
| OP-31-4 | Übernahme von Δ-1…Δ-7 und Δ-9, T31-2, T31-4 in R0-27 | technische Korrekturen ohne Beschluss; E-243-Unterschlüssel sind übernommen | Architektur (R1-31) → **technisch, an R1-31 übergeben** (kein Auftraggeber-Beschluss, kein Blocker für Gate A) |
| OP-31-5 | Rechtliche Bewertung der Ausnahme `always_load` | Betreiberrisiko (R0-17 §6.5) | Betreiber → **Auftraggeber-Aktion (Blocker für Gate A)**: rechtliche Bewertung `always_load` (Gate A-9), AG-07 |

---

## 7. Technische Korrekturen (ohne Auftraggeber-Entscheidung)

| Nr. | Korrektur | Betroffen |
|---|---|---|
| T31-1 | **Eingearbeitet** in R0-27: INV-S-07 entfällt; SET-119 neu gefasst; K43-48 auf `hardening.*` im 11. Tab (E-262) | R0-27 §1.2, §1.5, §4.2 |
| T31-2 | Bestätigungstoken serverseitig (PD-3) für alle R/R+-Felder in UI, CLI und Import | R0-27 SR-2/SR-4, R1-31 |
| T31-3 | Schlüssel-Fingerabdruck `course_captain_key_fingerprint` (SH-3); Tombstone-Abgleich meldet nicht prüfbare Einträge statt zu raten | R0-16 §3.2, R0-17 §5.5 |
| T31-4 | Schema-Eigenschaft `export: 'never_env'` (EX-2) | R0-27 §2.5 |
| T31-5 | Audit-Ereignisse `retention_applied`, `hardening_state_changed`, `hardening_kill_switch_detected` in den Katalog (Bereich `ops` bzw. `settings/integrations`) | R0-13 §4.1 |
| T31-6 | Lernenden-Aktion „Gespeicherte Freigaben für externe Medien zurücksetzen“ im Konto (L-20) | R0-10 §5.2, R0-11 L-20 |

---

## 8. Deltas zu anderen Dokumenten

| Dokument | Delta |
|---|---|
| `ENTSCHEIDUNGEN.md` | E-239…E-246 aufgenommen – **erledigt**; Klarstellung in §23 („Wegen E-10 hat der Free-Kern keine Login- bzw. Reset-Limits“) ist durch den Beschluss E-10 überholt und zu streichen |
| TODO | R0-31 abhaken; Task R1-45 (E-239); R1-34 DoR „R0-31 fehlt“ (R0-12 §2) entfällt |
| R0-04 | BS-27: Phase nach E-239 statt „R2/W-04“; ED-4 erledigt durch E-10 |
| R0-11 | BS-27 aus dem R4-Pool in Free (Phase nach E-239); Satz „keine Website-Härtung im Free-Kern“ streichen |
| R0-12 | PSD-001…065 in die Zählung; R1-34-Zeile „R0-31 fehlt“ → vorhanden; LB-05 „Härtung nicht R1“ nach E-239 anpassen |
| R0-13 | T31-5; Härtungszustand als H-38 – **eingearbeitet** |
| R0-16 | T31-3 |
| R0-17 | §3.5 um das Härtungsmodul ergänzt; §3.4 um Login/Reset/Registrierung (fail-closed); E-10 als Beschluss – **eingearbeitet** |
| R0-20 | §2.6 nach §3.4-Konfliktmatrix; KOMP-050…053 um die erwartete Rückhaltung erweitern |
| R0-27 | Δ-8, neue Felder §1.3, Retention-Unterschlüssel §2.2 (E-243) und T31-1 – **eingearbeitet**; Δ-1…Δ-7, Δ-9, T31-2, T31-4 noch zu übernehmen (OP-31-4) |
| R0-10 | T31-6; Consent-Kategorie verweist auf `privacy.consent_category` – **eingearbeitet** (R0-10 §5.3) |
| R0-30 | Wireframe Abschnitt „Website-Härtung“ (Modus, Wirkzustand, Konfliktgrund) und Bestätigungsdialoge nach PD-3 |

---

## 9. Nachweis

- **Gelesen:** siehe Kopf „Quellen“. Register-Zeilen aus R0-27 §1.3 einzeln gegen §1.1 geprüft; Retention-Angaben aus R0-27 §1.4, R0-17 §2/§5.2, R0-13 §4.4, R0-09 §7, R0-10 §6.1, R0-16 §2.5 zusammengeführt; Härtungsverhalten aus INV-C-138…146 als Spezifikation (Clean Room, E-01).
- **Nicht geprüft:** Code (es gibt keinen), aktuelle Funktionsweise der genannten Security-Plugins (Liste in R1-30 gegen reale Versionen zu verifizieren), Rechtslage.
- **Zählungen:** Kernzahlen am Kopf per Skript aus diesem Dokument ermittelt (Zeilen `| RT-`, `| PSD-`, `| **D31-`, Befundspalte §1.2).
