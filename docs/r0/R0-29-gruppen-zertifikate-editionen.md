# R0-29 · Free-/Pro-Grenze für Gruppen und Zertifikate

**Stand:** 16.11.2026 · **Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** · **Bezug:** TODO R0-29 (DoD: „Free Seat/Claim und Basisnachweis vertraglich abgrenzen; Pro Gruppen-UX/Designer und Lizenzablauf getestet“), Folgetasks **R1-35**, **R1-44\*** (Vorschlag, E-183), **R3-08** (dazu R3-15, R3-18, R1-36); Konzept Kap. 4.5, 5, 6, 7, 35.1, 35.4, 43.1/43.3, 46.1; `R0-04` (BS-14, K-22, Befund ohne Quellbestand), `R0-07` (#11, #12, #17, M-15…M-19, RG-11/12/14/15, RBAC-051…066), `R0-08` §7.2, `R0-09` §1.2, §4, §6.3, §8, `R0-11` §1.2 (ER-1…ER-5), §1.5, §2, Flow 3/5, N-01…N-05, `R0-12` (CERT-001…008, R1-44\*), `R0-15` (A11Y-096, D-15-09), `R0-17` (T-38…T-40, SEC-034, PRIV-025), `R0-19` (R-31…R-45, Slots, Events), `R0-23` §5, `R0-27` (SET-072…076, SET-090…093, N-02, N-05…N-07).

### Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). In dieses Dokument eingearbeitet:

| Beschluss | Wirkung hier |
|---|---|
| **E-01** Clean Room | kein LB-Code; Gruppenverhalten wird nach R0-09 neu gebaut, für Zertifikate gibt es ohnehin keinen LB-Bestand (R0-04 §3) |
| **E-17** | Gruppen in Free, auch ohne Kauf; Besitzer verwaltet; delegierte Verwaltung und Reporting in Pro (§1) |
| **E-14** | Gruppen-Drip Pro (GP-05) |
| **E-20**, E-268 | Erklärungstexte erscheinen weder auf Nachweisen noch in Gruppenberichten; nach Lizenzende eingefroren weiter (betrifft R0-08 §4.5) |
| **E-27** | Zugang je Modul: Gruppenplätze können sich auf einzelne Module beziehen (GF-02); Datenmodell offen (OP-29-2) |
| **E-38** | Fachereignisse (`group.owner_changed`, Zertifikatsereignisse) gehen in Free nur in die interne Outbox; öffentliche Events sind Pro |
| **E-231 … E-238** (früher D29-1 … D29-8) | Gruppenfilter statt Gruppenbericht in Free; Fortschrittseinsicht nur Pro-Opt-in; Massenimport nur Einladungen; Umbuchen nur in der Stammgruppe; Layout-Revision; keine Initialen/Ergebnisse öffentlich; Delegierte durch Besitzer und Admins; kein externer PDF-Dienst (§5) |
| **E-232** (Auftraggeber) | Fortschrittseinsicht für Gruppenbesitzer nur in Pro als Opt-in, nur Fortschritt und Abschluss, Lernende werden informiert (GR-7, GP-06, GRP-041) |
| **E-28, E-81, E-93, E-94, E-95, E-96, E-97, E-98, E-100, E-110, E-112, E-113, E-115, E-127, E-136, E-150, E-158, E-183, E-220, E-222** | unverändert angewandt (früher Arbeitsannahmen) |
| **E-207** | Präfix `ccap_` für CPTs, Caps, Rollen, Cron, Meta und Tabellen; Optionen `course_captain_*`. Alle Bezeichner in diesem Dokument sind umgestellt |

**Kein Code, keine Tests ausgeführt, kein Commit.**

---

## 0. Kurzfassung

| Thema | Ergebnis |
|---|---|
| Grundregel | Free besitzt **alle Daten und alle Zustandsautomaten** (Gruppe, Platz, Claim, Grant, Zertifikat, Vorlage). Pro schreibt nur über Free-Domänendienste und Registries und hält eigene Tabellen nur für Pro-Konfiguration, Berichts-Caches und Jobs. Ohne Pro-Code bleibt jeder Datensatz lesbar, verwaltbar, verifizierbar und darstellbar. |
| Gruppen Free | Anlegen (Admin), Plätze, Laufzeit, Status, Besitzer, Einladen, Gruppenlink, Join, Entfernen, Umbenennen, `claim_policy`, Mitgliederliste minimiert, Gruppenfilter im Basisbericht für berechtigte Rollen (→ E-231). 16 Funktionen, Tabelle §1.2. |
| Gruppen Pro | Delegierte Verwalter, Untergruppen mit Kontingent, Umbuchen, Massenimport (nur Einladungen), Massenaktionen, Gruppenberichte, Gruppen-Drip, Umverteilungsregeln. 8 Funktionen, Tabelle §1.3. |
| Zertifikate Free | Ausstellungsregel, automatische und evidenzgebundene manuelle Ausstellung, Basisvorlage, HTML-/Druckansicht mit Verifikationslink und Prüfcode, öffentliche Verifikation, Namensfreigabe, Korrektur, Widerruf, Liste, Datenschutz. 14 Funktionen, Tabelle §2.2. |
| Zertifikate Pro | Designer, mehrere Vorlagen mit Zuordnungsregeln, serverseitiges getaggtes PDF (PDF/UA-orientiert), Sammel-Neuausstellung im neuen Layout, mehrsprachige Vorlagen, Rezertifizierungs-Erinnerung. 6 Funktionen, Tabelle §2.3. |
| Datenvertrag | Snapshot `format_version = 1` enthält **alle gedruckten Werte aufgelöst**; Vorlagenversionen (auch Designer) liegen in der Free-Tabelle `ccap_certificate_template`; Free besitzt einen Fallback-Renderer für jede Vorlagenart (§2.4). |
| Lizenzablauf | 9 Zustände L0…L6D, Erwartungsmatrix für 22 Objekte (§3.2), Sequenztest „aktiv → abgelaufen → deinstalliert → reaktiviert“ mit Free-Prüfsumme (§3.3). |
| Tests | 48 GRP- und 48 CRT-Fälle (GRP-001…065, CRT-001…063, mit Lücken je Block), davon 18 Lizenzfälle. Ziel R1-35, R1-44\*, R3-08 (§4). CERT-001…006/008 aus R0-12 sind übernommen. |
| Entscheidungen | 8 Beschlüsse E-231…E-238 (§5). |

---

## 1. Gruppen

### 1.1 Abgrenzungsregeln

| Nr. | Regel | Beleg |
|---|---|---|
| GR-1 | **Jeder Platz ist ein Free-Platz.** Pro erzeugt Plätze, Einladungen und Grants ausschließlich über den Free-Gruppendienst (`GroupService`, R0-09 §4); es gibt keine Pro-eigene Platztabelle. | ER-3, INV-S-12 |
| GR-2 | **Free-Rechte des Besitzers und der Mitglieder hängen nie an Pro.** Besitzer-Selbstverwaltung (M-15) und Join (M-17) funktionieren in L0…L6D identisch. | E-17, Kap. 43.3 „Basis-Seat-Rechte dürfen ohne Pro nicht verschwinden“ |
| GR-3 | **Pro erweitert Rechte nur über einen Resolver.** M-15 fragt den Registry-Slot `groupManagerResolvers` (neu, §6 Δ-3). Ohne registrierten Resolver gilt nur `owner_user_id` bzw. #11. Gespeicherte Delegationen sind ohne Pro wirkungslos, nicht gelöscht. | ER-2, R0-07 D07-1 |
| GR-4 | **Belegung ist immer vollständig berechnet.** Die Belegungsformel (R0-09 §4.1) zählt zusätzlich die Kontingente der Untergruppen (`SUM(child.seats)` für nicht widerrufene Kinder). Free kennt dafür die Spalte `parent_group_id`, auch wenn nur Pro sie schreibt. So kann eine Elterngruppe ohne Pro nicht überbucht werden. | §6 Δ-1, Δ-2 |
| GR-5 | **Lizenzende sperrt nur Pro-Schreibaktionen** (anlegen, ändern, neue Läufe starten). Bereits gestartete Läufe enden regulär. Lesen und Export bleiben. | ER-4, E-94 |
| GR-6 | **Keine Pro-Sperren in Free-Screens.** Pro-Tabs, -Spalten und -Knöpfe erscheinen nur über Registries, wenn das Add-on läuft (E-100, ER-5). Bei `expired` zeigen sie Z-P „Pro-Lizenz abgelaufen – bestehende Daten bleiben lesbar“. | R0-11 §2.1 |
| GR-7 | **Gruppe ≠ Lernleistung.** Besitzer und Delegierte sehen in Free nie Fortschritt, Antworten, Punkte oder Zertifikate von Mitgliedern (35.1, M-16). Pro darf das nach **E-232** nur als Opt-in-Gruppeneinstellung, nur für Fortschritt und Abschluss, mit Information der Lernenden (GP-06). | Kap. 35.1, E-232 |

### 1.2 Free-Funktionen (R1, Abnahme R1-35)

Spalte „Lizenzende“ gilt für **alle** Zustände L1…L6D (§3.1): Free-Funktionen ändern sich nie. Sie ist nur dort ausgefüllt, wo eine Pro-Erweiterung dieselbe Funktion berührt.

| Nr. | Funktion | Screen | Cap / Meta-Cap | API (R0-19 / R0-07 §4.2) | Regeln | Berührung durch Pro / Lizenzende |
|---|---|---|---|---|---|---|
| GF-01 | Gruppen-Grundfunktion an/aus | A-15 Tab Einschreibung | #19 | Setting `enrollment.groups_enabled` (SET-072) | Standard aus (E-222); Abschalten verhindert nur neue Gruppen, bestehende Plätze und Grants bleiben | – |
| GF-02 | Gruppe manuell anlegen (ohne Kauf) | A-10 „Gruppe anlegen“ | #11 | admin-post `ccap_group_admin` (`op=create`, `request_key`) | Pflicht: Besitzer, Name (bereinigt), Kurse bzw. einzelne Module (E-27) + Scopes (Vorbehalt OP-29-2), Plätze ≥ 1, Laufzeit, `claim_policy`; `source_type = manual`; Audit; Besitzer bekommt keinen Grant (R0-09 §4.2) | Pro „Untergruppe anlegen“ nutzt denselben Dienst mit `parent_group_id` |
| GF-03 | Plätze erhöhen | A-10 Detail | #11 | `ccap_group_admin` (`op=seats`) | nur `seats` erhöhen, keine Wirkung auf Bestehendes | Elterngruppe: neue Plätze stehen der Elterngruppe zu, nicht den Kindern |
| GF-04 | Plätze reduzieren mit Vorschau | A-10 Detail | #11 | `ccap_group_admin` (`op=seats`, `dry_run=1` → Vorschau) | Reihenfolge R0-09 §4.4 (offene Einladungen jüngste zuerst, dann zuletzt Beigetretene); Bestätigung nennt Betroffene | Elterngruppe: Reduktion unter `eigene Belegung + Kontingente` wird abgewiesen (`ccap_group_quota_conflict`, §6 Δ-4), erst Kontingente kürzen (Pro) |
| GF-05 | Laufzeit ändern | A-10 Detail | #11 | `ccap_group_admin` (`op=validity`) | Seat-Grants übernehmen `valid_until`, außer `valid_until_locked` (R0-09 §3.3) | Untergruppe: eigene Laufzeit ≤ Eltern-Laufzeit (Pro-Validierung; ohne Pro keine Prüfung, bestehende Werte bleiben) |
| GF-06 | Status aussetzen / fortsetzen / beenden | A-10 Detail | #11 | `ccap_group_admin` (`op=status`) | R0-09 §4.5; `revoked` endgültig je `source_revision` | Elterngruppe aussetzen setzt Kinder **nicht** automatisch aus (bewusst, Kinder sind eigenständige Free-Gruppen); Pro bietet eine Massenaktion dafür (GP-07) |
| GF-07 | Besitzer wechseln | A-10 Detail | #11 | `ccap_group_admin` (`op=owner`, Begründung) | Event `group.owner_changed`; alter Besitzer sofort 404 (RBAC-057); Gruppenlink wird rotiert (R0-09 §4.7 SOLL → hier MUSS) | Delegationen bleiben bestehen |
| GF-08 | `claim_policy` je Gruppe | A-10, L-18 | #11 bzw. M-15 | `ccap_group_admin` (`op=policy`), R-34 (Feld `claim_policy`, §6 Δ-6) | Umstellen auf `invited_address_only` deaktiviert und rotiert den Gruppenlink | – |
| GF-09 | Per E-Mail einladen (≤ 200 je Aufruf) | L-18, A-10 | M-15 (Besitzer) bzw. #11 | R-35 | Budget max(20, 2 × Plätze)/24 h, fail-closed (E-46-Klarstellung); übersprungen, wenn kein Platz frei; Mail N-02 | Pro-Massenimport (GP-04) nutzt R-35-Logik und dasselbe Budget |
| GF-10 | Einladung erneut senden / zurückziehen | L-18, A-10 | M-15 bzw. #11 | neu `POST /me/groups/{g}/invites/{seat}/resend`; Zurückziehen über R-37 auch für `invited` (§6 Δ-5) | neues Token, alter Hash ungültig; Zurückziehen löscht die Adresse sofort | – |
| GF-11 | Gruppenlink erzeugen / rotieren | L-18, A-10 | M-15 bzw. #11 | R-36 | nur bei `transferable`; Link nur direkt nach Erzeugung sichtbar (E-112) | – |
| GF-12 | Vorschau und Beitritt | L-17 | M-17 | R-31, R-32 | Lock `group-seats:<id>`, Nachzählung, eine TX mit Seat-Grants (R0-09 §4.3); Doppelzugang-Hinweis | Pro kann über Slot `responseFields` einen Transparenzhinweis ergänzen (nur bei E-232 b) |
| GF-13 | Mitglied entfernen / Platz freigeben | L-18, A-10 | M-15 (Mitglied ∈ Gruppe) bzw. #11 | R-37 | Seat `removed`, Seat-Grants `revoked` (`group_member_removed`), Lernhistorie bleibt, Platz sofort frei; Umverteilen = entfernen + einladen, keine Grenze (E-115) | Pro-Umverteilungsregeln (GP-08) können das Wiederbelegen begrenzen |
| GF-14 | Gruppe umbenennen | L-18, A-10 | M-15 bzw. #11 | R-34 | Name bereinigt (SEAT-021), `If-Match` | – |
| GF-15 | Mitglieder- und Platzübersicht | L-18 „Meine Gruppen“, A-10 | M-15 bzw. #11/#13 | R-33 | Status, Anzeigename, maskierte Adresse (E-81); keine Lernleistungen (GR-7) | Delegierte lesen dieselbe Ansicht (GP-01) |
| GF-16 | Gruppe als Filter im Basisbericht | A-12 | #12 (+ Scope B-2/B-3) | R-43 `?group=`, R-45 (→ E-231) | Spalten wie E-98 (Fortschritt je Lernendem, Bestehensquote, CSV); **nicht** für Besitzer | Pro-Gruppenberichte (GP-06) sind eine eigene Ansicht |

**Bewusst nicht in Free (und nicht in Pro geplant):** Lernender verlässt eine Gruppe selbst (kein Bedarf belegt; Support entfernt), Gruppenanlage durch Lernende, direkte Platzzuweisung ohne Claim (35.1 verlangt Bestätigung; → E-233), Gruppen aus Gutscheinen (E-36).

### 1.3 Pro-Funktionen (R3, Abnahme R3-08/R3-15)

Entitlement-Vorschlag nach E-95/N-03: `course.groups_advanced` (GP-01…GP-05, GP-07, GP-08), `course.analytics` (GP-06), `course.drip_campaigns` (GP-05 Gruppen-Drip, E-14). Pro-REST-Namensraum `course-captain-pro/v1` (Vorschlag, damit die Routen mit dem Add-on vollständig verschwinden); ohne gültige Lizenz antworten schreibende Pro-Routen `403 ccap_pro_license_required` (Code im Pro-Katalog, R0-11 F5.5).

| Nr. | Funktion | Screen | Cap | API | Datenort | `expired` (Add-on aktiv) | Add-on deaktiviert / deinstalliert | Reaktiviert |
|---|---|---|---|---|---|---|---|---|
| GP-01 | **Delegierte Verwalter** ernennen/entziehen; Delegierte verwalten Mitglieder | A-10 Tab „Verwalter“; Delegierte nutzen L-18 | Ernennen: #11 oder Besitzer (→ E-237); Wirken: M-15 über Pro-Resolver | `GET/POST/DELETE course-captain-pro/v1/groups/{g}/managers`; Delegierte verwenden R-33…R-37 | `ccap_course_staff` (`staff_role = group_manager`, `group_id`; R0-07 D07-1) | Delegierte nur lesend (R-33 ok; R-34…R-37 → 403 `ccap_pro_license_required`); keine neuen Delegierten; Besitzer unverändert | Resolver fehlt → Delegierte 404 (wie Fremde); Zeilen bleiben; Audit `group.managers_inactive` einmalig | Rechte sofort zurück (Policy-Memo-Invalidierung P-11) |
| GP-02 | **Untergruppen** mit Platzkontingent aus der Elterngruppe | A-10 Baumansicht; L-18 für Eltern-Besitzer/Delegierte | #11; Eltern-Besitzer/-Delegierte (Pro) | `POST …/groups/{g}/children`, `PUT …/groups/{child}/quota` | `ccap_group.parent_group_id` (Free-Schema, Δ-1); Kontingent = `child.seats` | keine neuen Untergruppen, keine Kontingentänderung; bestehende Kinder arbeiten als Free-Gruppen weiter | Kinder sind eigenständige Free-Gruppen; Elternbelegung zählt Kontingente weiter (GR-4); Eltern-Besitzer sieht Kinder nicht mehr (Admin sieht alle). Bei Pro-Datenlöschung: Hierarchie auflösen mit Platzerhalt (§3.2 Z-08) | Baumansicht zurück; keine Datenmigration |
| GP-03 | **Umbuchen** eines Mitglieds zwischen Gruppen derselben Wurzelgruppe | A-10, L-18 | #11 oder Verwalter beider Gruppen | `POST …/groups/{g}/members/{m}/move {target}` | Free-Tabellen; eine TX: Seat A `removed` (`transferred`), Seat B `active`, Grants A `revoked`, Grants B neu (Locks in ID-Reihenfolge, R0-09 §6.1) | gesperrt; Free-Weg (entfernen + einladen) bleibt | Free-Weg | – |
| GP-04 | **Massenimport** von Mitgliedern (CSV, Dry-run) | A-10 „Mitglieder importieren“; L-18 für Besitzer/Delegierte | #11 bzw. M-15 + Pro | `POST …/groups/{g}/imports` (`dry_run`), `POST …/imports/{job}/commit`, `GET …/imports/{job}` | Job: Pro-Tabelle; Ergebnis: Free-Plätze `invited` | kein neuer Import; laufender Import endet regulär (GR-5) | laufender Job pausiert (Lease läuft ab, R0-38-Muster); bereits erzeugte Einladungen gültig | Job setzt nach Vorprüfung fort oder wird verworfen (Admin-Wahl) |
| GP-05 | **Gruppen-Drip** (Freischaltplan je Gruppe, Anker Gruppenstart) | A-04 Panel „Kampagnen/Termine“, A-10 Detail | #10 (Regel), #11 (Gruppenstart) | `…/groups/{g}/drip` | Pro-Tabelle | Regeln wirken eingefroren weiter (E-94), nicht änderbar | Free-Drip relativ zum Grant-Beginn (E-14); Warnliste betroffener Module vor Deaktivierung (R0-11 §1.5) | Pro-Regel wirkt wieder; bereits freigeschaltete Module bleiben frei (kein Zurücksperren) |
| GP-06 | **Gruppenberichte** (Gruppenvergleich, Zeitverlauf, geplanter CSV-Export; Sicht für Besitzer/Delegierte nur nach E-232: Opt-in, nur Fortschritt und Abschluss) | A-12 Tab „Gruppen“, L-18 Tab „Bericht“ | #12; Besitzer/Delegierte nach E-232 | `GET …/reports/groups/{g}`, `…/reports/groups/compare` | Berichts-Cache: Pro-Tabelle | letzter Stand lesbar und exportierbar; keine neuen Läufe, geplante Exporte pausieren | nicht sichtbar; Free-Filter GF-16 bleibt | nächster Lauf rechnet neu |
| GP-07 | **Massenaktionen** (mehrere Mitglieder entfernen, Laufzeit/Status für mehrere Gruppen, Mitgliederliste exportieren) | A-10 Liste | #11 | `POST …/groups/bulk` (`dry_run`, `request_key`) | Free-Tabellen | gesperrt; Einzelaktionen GF-xx bleiben | Einzelaktionen | – |
| GP-08 | **Umverteilungsregeln** (D09-4 b/c: max. n Neubelegungen je Platz und Laufzeit; keine Neubelegung nach Bestehen) | A-10 Detail | #11 | `PUT …/groups/{g}/rules` | Pro-Tabelle; Prüfung über Hook `course_captain_seat_claim_allowed` (nur einschränkend, E-84-Analogie) | Regel wirkt eingefroren weiter | keine Grenze (E-115) | Regel wirkt wieder, rückwirkend keine Entfernung |

**Hinweis zu GP-03:** Umbuchen ohne neuen Claim ist eine Produktentscheidung (35.1 „Lernende müssen die Zuweisung bestätigen“) – entschieden mit **E-234**: nur innerhalb derselben Stammgruppe.

### 1.4 Kennzahlen, die Free bereitstellt (für Pro und Support)

`seats_total`, `seats_used` (Formel GR-4), `seats_allocated_to_children`, `invites_open`, `reassignments_per_seat` (D09-4 a), `last_activity_at` je Gruppe – im Free-Dashboard A-10 sichtbar, über R-33 für den Besitzer nur `seats_total/used`.

---

## 2. Zertifikate

### 2.1 Abgrenzungsregeln

| Nr. | Regel | Beleg |
|---|---|---|
| CR-1 | **Free besitzt die Nachweis-Domäne vollständig** (Ausstellung, Einmaligkeit, Snapshot, Korrektur, Widerruf, Verifikation, Datenschutz). Pro ruft für jede Ausstellung, Korrektur und Neuausstellung den Free-Dienst `CertificateService` auf; direkte Schreibzugriffe auf `ccap_certificate` sind verboten (Architekturtest CRT-040). | Kap. 43.1, 46.1; ER-3; R1-44\* |
| CR-2 | **Der Snapshot ist selbsttragend.** Alles, was gedruckt oder als PDF ausgegeben wird, steht als aufgelöster Wert im Snapshot (§2.4). Pro darf keine gedruckten Werte außerhalb des Snapshots halten. | R0-08 §7.2, R0-23 §5.1 |
| CR-3 | **Jede Vorlagenversion ist Free-Datenbestand** (`ccap_certificate_template`, append-only, Δ-7), auch Designer-Vorlagen. Eine referenzierte Version wird nie gelöscht, auch nicht bei Pro-Datenlöschung. | R0-23 §5.2 „neue Template-Version“, E-110 |
| CR-4 | **Free kann jedes Zertifikat darstellen.** Für `kind = base` rendert Free exakt; für jede andere Art (z. B. `designer`) rendert Free den **Fallback** aus dem Snapshot im Basislayout mit dem Hinweis „Darstellung im Standardlayout“. Verifikation und Status sind davon unabhängig. | ER-3, Kap. 35.4 |
| CR-5 | **Verifikation hängt nie an Pro** – weder an Lizenz noch an Add-on noch an der Vorlagenart. | Kap. 35.4, CORE-10 |
| CR-6 | **Format Free = HTML + Druck.** Kein serverseitiges PDF, keine PDF-Bibliothek, kein PDF-Link in Free-Mails und -Antworten (E-96, T27-4). PDF gibt es nur über die Pro-Erweiterung im Slot `responseFields`. | E-96, R0-27 T27-4 |
| CR-7 | **Lesen und Herunterladen sind keine Pro-Schreibaktionen.** Bei `expired` bleiben PDF-Download vorhandener Zertifikate und Designer-Darstellung verfügbar; nur Vorlagen ändern und Sammelläufe starten sind gesperrt. | ER-4 |

### 2.2 Free-Funktionen (R1; Domäne R1-44\*, Verwaltung/UI R1-35)

| Nr. | Funktion | Screen | Cap / Meta-Cap | API | Regeln | Lizenzende / Pro-Berührung |
|---|---|---|---|---|---|---|
| CF-01 | Ausstellungsregel je Kurs | A-15 Tab Zertifikate, A-04 Seitenleiste | #19 global; M-03 + #17 Kurs | Setting `certificates.auto_issue` (SET-090) | Standard aus; Kurs-Override nur mit definierter Abschlussregel (Validator); keine Rückwirkung | Pro-Vorlagenzuordnung (CP-02) wählt nur das Layout, nie das Ob |
| CF-02 | Automatische Ausstellung | – (System) | System | intern `CertificateService::issueFromEvidence()`, Event `certificate.issued` | nur nachweisfähige Evidenz (`reduced`/`practice` nie, R0-08, E-123); Grant mit `exam` bei Start **und** Abgabe (E-113); `UNIQUE evidence_rev` (CERT-002) | in L3 mit zuletzt gespeicherter Designer-Vorlage (E-94), in L4/L5 mit Basisvorlage |
| CF-03 | Manuelle Ausstellung aus vorhandener Evidenz | A-11, A-09 Lernenden-Detail | #17 (M-19) | admin-post `ccap_certificate_issue` (`evidence_ref`, Grund) | nur bestehende, nachweisfähige Evidenz (z. B. E-113-Fall); **keine** Ausstellung ohne Evidenz (E-73) | – |
| CF-04 | Basisvorlage | A-15 Tab Zertifikate | #19; Kurs: M-03 + #17 | Setting `certificates.base_template` (SET-092) | Aussteller, Fußtext (Sprachvarianten), Logo ≤ 2 MB, Signaturzeile; jede Änderung = neue `template_version` in `ccap_certificate_template`; alte Nachweise unverändert | Designer (CP-01) ist eine weitere Vorlagenart, ersetzt die Basisvorlage nicht |
| CF-05 | Eigene Nachweise ansehen | L-15 `/lernen/nachweise/` | M-18 (`user_id` = Akteur) | R-40 (ohne `pdf_url`, mit `view_url`, Δ-8) | Status gültig/korrigiert/widerrufen/abgelaufen; widerrufene bleiben sichtbar (CORE-11); Lernsperre → Ansicht `ccap_account_locked`, Verifikation bleibt (RBAC-066) | Pro ergänzt `pdf_url` über `responseFields` |
| CF-06 | HTML- und Druckansicht | L-15 `/lernen/nachweise/<uid>` | M-18 oder #17 | Seite, serverseitig gerendert | Druck-CSS A4/Letter, Text statt Bild (A11Y-096), Verifikationslink als Text, Nachweis-Nr., Prüfcode (§2.4), SOLL: QR-Code als lokal erzeugtes SVG (kein externer Dienst) | Designer-Zertifikat ohne Pro: Fallback (CR-4) |
| CF-07 | Öffentliche Verifikation | L-16 `/lernen/nachweis/<token>`; Block `course-captain/certificate-verify` | keine (Token) | R-42; PHP `course_captain_verify_certificate_v1` | Felder nach §2.5; Rate-Limit `verify` fail-closed (30/min, SEC-034); `noindex`, `no-store`, kein Verzeichnis; serverseitig gerendert (R0-20) | in allen Zuständen L0…L6D identisch |
| CF-08 | Namensfreigabe je Nachweis | L-15 Schalter | M-18 (eigenes) | neu `PUT /me/certificates/{uid}/visibility {holder_name_public}` (Δ-9) | Standard aus (E-97); wirkt sofort, auch nach vorherigem Abruf (PRIV-025); gilt für alle Revisionen der `certificate_uid` | – |
| CF-09 | Korrektur | A-11 Detail | #17, M-19 (nie eigenes) | admin-post `ccap_certificate_correct` (`request_key`, Grund) | R0-23 §5.3: neue Revision, alte `corrected`, alter Token „ersetzt durch Fassung vom …“; Evidenz/Person/Kurs unveränderlich; Mail N-06 (Link auf HTML-Ansicht) | Korrektur eines Designer-Zertifikats ohne Pro: neue Revision mit **derselben** `template_version` (Fallback-Darstellung), kein stiller Vorlagenwechsel |
| CF-10 | Widerruf | A-11 Detail | #17, M-19 | admin-post `ccap_certificate_revoke` (Grundkategorie + Freitext) | R0-23 §5.4: wirkt auf alle Revisionen, endgültig, Kompetenz `revoked`; Mail N-07 (nur Kategorie); Cache sofort invalidiert (CON-037) | – |
| CF-11 | Liste, Filter, Export | A-11 „Ausgestellte Nachweise“ | #13 lesen, #17 ändern | admin-Liste; CSV-Export mit Pflichtbegründung (Personendaten, R0-07 §1 Audit) | Filter Kurs, Status, Zeitraum, Vorlagenart; Spalten ohne E-Mail | Pro ergänzt Massenaktion CP-04 über `grantListColumns`-analogen Slot (Δ-3) |
| CF-12 | Datenschutzexport und Löschung | R1-17 (Export/Eraser) | – | WP-Privacy-Exporter/Eraser | Export enthält Snapshot-Daten; Eraser nach E-150 (löschen bzw. pseudonymisiert behalten, dann Verifikation ohne Name) | – |
| CF-13 | Ereignisse | – | – | `certificate.issued/.corrected/.revoked` (R0-19, Klasse `certificate`, D19-4) | `object_version` = `revision_no` | – |
| CF-14 | Gültigkeit und Rezertifizierungshinweis | L-04, L-15 | – | – | Ablauf ist abgeleitete Anzeige, kein Zustand (R0-23 §5.1); ab 30 Tagen vor Ablauf Hinweis „Rezertifizierung möglich“ | Erinnerungsmail nur Pro (CP-06) |

### 2.3 Pro-Funktionen (R3, Abnahme R3-08/R3-15)

Entitlement-Vorschlag: `course.certificate_designer` (CP-01…CP-05), `course.reminders` (CP-06).

| Nr. | Funktion | Screen | Cap | API | `expired` | Add-on deaktiviert / deinstalliert | Reaktiviert |
|---|---|---|---|---|---|---|---|
| CP-01 | **Designer** (Layout, Seitenformat, Schriften, Hintergrund, Logo, Signaturbilder, Zusatzfelder) | A-11 Tab „Designer“ (Z-P) | #17 + #19 für globale Vorlagen | `course-captain-pro/v1/certificate-templates` (Entwurf, Vorschau, Veröffentlichen = neue Version in `ccap_certificate_template`) | Bearbeiten gesperrt; Ausstellung mit zuletzt **veröffentlichter** Version (E-94); Darstellung bestehender Designer-Zertifikate exakt | neue Ausstellungen mit Basisvorlage; bestehende Designer-Zertifikate im Fallback (CR-4); Entwürfe in Pro-Tabelle bleiben | exakte Darstellung aller Versionen zurück; ab nächster Ausstellung wieder Designer-Zuordnung |
| CP-02 | **Mehrere Vorlagen und Zuordnungsregeln** (je Kurs, Prüfungsregel, Sprache, Bestehensstufe) | A-11, A-04 | #17 | `…/certificate-template-rules` | Regeln wirken eingefroren | Regeln nicht ausgewertet → Basisvorlage | Regeln wirken wieder, nicht rückwirkend |
| CP-03 | **Serverseitiges PDF, getaggt, PDF/UA-orientiert** (E-127, D-15-09) | L-15 „PDF herunterladen“, A-11 | M-18 bzw. #17 | `GET course-captain-pro/v1/certificates/{uid}/pdf` (bisher R-41, Δ-8) | **bleibt** (Lesen, CR-7) | kein PDF; HTML/Druck bleibt; früher geladene PDFs bleiben über Token verifizierbar | sofort verfügbar |
| CP-04 | **Sammel-Neuausstellung im neuen Layout** (E-110) | A-11 Massenaktion mit Dry-run | #17 | `POST …/certificates/reissue` (`dry_run`, Filter, Zielvorlage), Job | kein neuer Lauf; laufender Lauf endet regulär | Lauf pausiert; jede bereits neu ausgestellte Revision ist für sich konsistent (TX je Zertifikat) | Lauf fortsetzen oder verwerfen |
| CP-05 | **Mehrsprachige Vorlagen** (R0-25 Ü-5) | A-11 Designer | #17 | über CP-01 | eingefroren | Basisvorlage in Kurssprache (E-158) | – |
| CP-06 | **Rezertifizierungs-/Ablauf-Erinnerung** | A-15 Tab E-Mails (`pro.reminders`) | #19 | – | kein Versand neuer Erinnerungen; Abmeldelinks funktionieren | kein Versand; Abmeldeseite bleibt Free-Route (R0-11 L-21) | Versand ab nächstem Lauf, keine Nachholmails |

**Semantik von CP-04** (gleiche Evidenz, neue Vorlage) ist entschieden: Layout-Revision, der alte Link bleibt gültig und zeigt die neue Fassung (**E-235**). **Technik für CP-03:** kein externer Dienst; ohne geeignete Bibliothek für getaggte PDFs kein Pro-PDF (**E-238**).

**Abnahmekriterien CP-03 (E-127), messbar:** Strukturbaum mit Überschrift, Absätzen, Tabellen für Ergebnisteile; `/Lang` aus Snapshot-`locale.content`; Dokumenttitel = Kurs + „Nachweis“, `DisplayDocTitle`; alle Schriften eingebettet mit Unicode-Zuordnung; Logo und Signaturbild mit Alternativtext (Pflichtfeld im Designer) oder als Artefakt markiert; Verifikations-URL als Link-Annotation mit Text; keine Textinhalte als Bild; Prüfung mit veraPDF (Profil PDF/UA-1, maschinell prüfbare Regeln ohne Fehler) **und** manueller Stichprobe mit Screenreader (R0-15 MAN). Kein Versprechen „fälschungssicher“ (R0-17 §4.3).

### 2.4 Datenvertrag (vollständig in Free)

**Tabellen (Free):** `ccap_certificate` (R0-06, +Δ-10), `ccap_certificate_template` (neu, Δ-7). **Pro-Tabellen** nur für Designer-Entwürfe, Zuordnungsregeln, Sammeljobs und PDF-Cache; deren Verlust darf keinen Nachweis unlesbar machen.

**Snapshot `format_version = 1`** (kanonisches JSON, Schlüssel sortiert, UTF-8 NFC; `snapshot_hash = sha256:` über das kanonische JSON ohne `snapshot_hash`):

| Feld | Inhalt | Pflicht | Quelle | Öffentlich (§2.5) |
|---|---|---|---|---|
| `format_version` | `1` | ja | Free | – |
| `certificate_uid`, `revision_no`, `revision_kind` | UUID, ≥ 1, `initial`/`correction`/`layout` (Δ-10, E-235) | ja | Free | – |
| `display_id` | Nachweis-Nr., z. B. `CC-7F3A-91D2` (aus `certificate_uid` abgeleitet, nicht geheim) | ja | Free | ja |
| `template` | `{template_uid, version, kind: base\|designer\|…, definition_hash}` | ja | Free bzw. Pro über Free-Dienst | – |
| `locale` | `{content, ui}` eingefroren (E-158, R0-15 §5) | ja | Free | Darstellung |
| `issued_at` | UTC, ISO 8601 | ja | Free | Datum |
| `issued_by` | `{type: system\|user, role}` – keine Namen (E-145-Analogie) | ja | Free | – |
| `issuer` | `{name, logo: {attachment_id, sha256, alt}?, signature_label?}` wie gedruckt | ja (`name`) | Basisvorlage bzw. Designer | `name` |
| `holder` | `{display_name}` wie gedruckt | ja | Profil zum Ausstellungszeitpunkt | nur Opt-in (E-97) |
| `course` | `{course_uid, title, course_revision_id}` wie gedruckt | ja | Kursrevision | `title` |
| `evidence` | `{type, ref_uuid, points, max_points, passed, evaluated_at, evaluation_no, rule_uid, rule_version, evidence_eligible}` | ja | Prüfung/Completion | nein (E-236) |
| `objectives` | `[{uid, title}]` belegte Lernziele | nein | Kursrevision | ja |
| `valid_until` | Datum oder `null` | ja (nullable) | `competency_validity_days` | ja |
| `fields` | `[{key, label, value, type: text\|date\|number}]` **aufgelöste** Zusatzfelder des Designers | nein (Free: leer) | Pro über Free-Dienst | nein |
| `assets` | `[{role: background\|signature\|seal, attachment_id, sha256, alt}]` | nein | Pro | nein |
| `texts` | `{heading, body, footer}` aufgelöst in Inhaltssprache | ja | Vorlage | Darstellung |
| `verify` | `{path_at_issue}` (Pfad, nicht Token; Slug-Historie E-220) | ja | Free | – |
| `snapshot_hash` | `sha256:<hex>` | ja | Free | Prüfcode = erste 12 Hex-Zeichen, gruppiert |

**Regeln:**

1. Free validiert jeden Snapshot beim Schreiben gegen das Schema (unbekannte Felder → `ccap_validation_failed`), egal ob Free oder Pro ausstellt.
2. Der Free-Fallback-Renderer gibt `texts`, `issuer`, `holder`, `course`, `objectives`, `valid_until`, `fields` (als Definitionsliste) und `display_id`/Prüfcode aus; `assets` werden mit `alt` als Text erwähnt, nicht gezeichnet.
3. Fehlt ein Bildanhang (Mediathek gelöscht), wird er ausgelassen, nie ersetzt; das Löschen eines referenzierten Anhangs zeigt in WordPress eine Warnung „wird von n Nachweisen verwendet“ (SOLL).
4. `ccap_certificate_template.definition` ist für `kind = base` von Free interpretierbar, für andere Arten opak (JSON, `definition_hash`). Renderer je Art registrieren sich im neuen Slot `certificateRenderers` (Δ-3); fehlt er, greift der Fallback.
5. Eine Korrektur (CF-09) übernimmt `template` unverändert; nur CP-04 darf `template` wechseln (E-235).

### 2.5 Öffentliche Verifikation: Feld-Policy (offen aus R0-08 §7.2, R0-23 §5.6)

| Feld | Antwort R-42 / L-16 |
|---|---|
| Status | `valid` · `valid` + „Gültigkeit abgelaufen am …“ · `corrected` („ersetzt durch Fassung vom …“, keine weiteren Daten) · `revoked` („widerrufen am …“) · unbekannt → `ccap_not_found` |
| immer (außer `corrected`/`revoked`) | Aussteller, Kurstitel wie gedruckt, Ausstellungsdatum (Datum ohne Uhrzeit, Site-Zeitzone), `valid_until`, Lernziele, Nachweis-Nr., Prüfcode |
| nur Opt-in | Name wie gedruckt (E-97, CF-08) |
| nie | E-Mail, Konto-ID, Punkte/Note/Bestanden-Detail, Widerrufs- und Korrekturgrund, Vorlagenart, Daten einer neueren Revision über einen alten Token |

Initialen ohne Opt-in (R0-11 N-05 Vorschlag) und Ergebnisanzeige entfallen: **E-236** – weder Initialen noch Ergebnis öffentlich.

---

## 3. Lizenzablauf: Zustände und Tests

### 3.1 Zustände

| Code | Zustand | Wie hergestellt (Testhilfe) | Pro-Code läuft | Pro schreibt |
|---|---|---|---|---|
| L0 | Free ohne Pro (Referenz) | Pro nie installiert | nein | – |
| L1 | Pro aktiv (`active`) | signierter Test-Snapshot, Entitlements gesetzt | ja | ja |
| L2 | Grace (Server nicht erreichbar, Snapshot gültig ≤ 14 d) | Lizenzserver-Stub antwortet nicht | ja | ja (wie L1) |
| L3 | Abgelaufen bzw. `invalid` (Add-on aktiv) | Snapshot mit `cache_expires_at` in der Vergangenheit bzw. falscher Signatur | ja | nein (nur lesen, exportieren, laufende Läufe beenden; E-93-Vorgänge) |
| L4 | Add-on deaktiviert | `wp plugin deactivate course-captain-pro` | nein | – |
| L5 | Add-on deinstalliert, Pro-Daten behalten | Uninstall ohne Löschoption (R0-11 F5.7) | nein | – |
| L5D | deinstalliert **mit** Pro-Datenlöschung | Uninstall mit Löschbestätigung (E-89-Muster, Pro-eigenes Manifest) | nein | – |
| L6 | reaktiviert | aus L3: Lizenz verlängert; aus L5: Neuinstallation + gültiger Snapshot | ja | ja |
| L6D | Neuinstallation nach L5D | wie L6 | ja | ja |

### 3.2 Erwartete Zustände je Objekt

Fixture **FX-LIC** (in L1 aufgebaut): Kurs C-A; Gruppe **G-A** (Free, 10 Plätze: 6 aktiv, 2 eingeladen); Elterngruppe **G-P** (20 Plätze, Besitzer U-GO, Delegierte U-D) mit Untergruppe **G-C** (Kontingent 5, Besitzer U-C, 3 aktiv); Gruppen-Drip auf C-A für G-P (Modul 2 ab Gruppenstart + 7 d); Umverteilungsregel G-A „max. 2 je Platz“; Gruppenbericht G-P berechnet; Zertifikate **Z-B** (Basisvorlage v1), **Z-D** (Designer T-1 v3), **Z-K** (korrigiert, Revision 2), **Z-R** (widerrufen); Ausstellungsregel C-A mit Designer-Zuordnung T-1.

| Nr. | Objekt | L1/L2 | L3 | L4 | L5 | L5D | L6 / L6D |
|---|---|---|---|---|---|---|---|
| Z-01 | Plätze/Seat-Grants G-A, G-P, G-C | aktiv | **unverändert** | unverändert | unverändert | unverändert | unverändert |
| Z-02 | Join/Einladen durch Besitzer (G-A, G-C) | ok | ok | ok | ok | ok | ok |
| Z-03 | Delegierte U-D auf G-P | schreibt | liest; Schreiben 403 `ccap_pro_license_required` | 404 | 404 | 404, Delegationszeile gelöscht | L6: schreibt sofort; L6D: keine Delegation (neu ernennen) |
| Z-04 | Untergruppe G-C | Baum, Kontingent änderbar | Baum lesbar, Kontingent gesperrt | eigenständige Free-Gruppe; G-P-Belegung zählt 5 weiter | wie L4 | `parent_group_id = NULL`, G-P `seats` um 5 reduziert (Summe der Plätze bleibt 20), Audit `group.hierarchy_dissolved` | L6: Baum zurück; L6D: zwei unabhängige Gruppen |
| Z-05 | Freie Plätze G-P (Anzeige R-33/A-10) | 20 − eigene Belegung − 5 | gleich | gleich (GR-4) | gleich | gleich (jetzt über reduzierte `seats`) | gleich |
| Z-06 | Gruppen-Drip G-P | wirkt | wirkt eingefroren | Free-Drip ab Grant-Beginn | wie L4 | wie L4, Regel gelöscht | L6: Pro-Regel; bereits freie Module bleiben frei |
| Z-07 | Umverteilungsregel G-A | wirkt | wirkt eingefroren | keine Grenze | keine Grenze | gelöscht | L6: wirkt, keine Rückwirkung |
| Z-08 | Gruppenbericht G-P | neu berechenbar | letzter Stand lesbar + CSV | nicht sichtbar, Free-Filter A-12 bleibt | wie L4 | Cache gelöscht | neu berechnet |
| Z-09 | Massenimport (neuer Job) | möglich | 403 | Route fehlt (404) | 404 | 404 | möglich |
| Z-10 | Massenimport (laufender Job) | läuft | endet regulär | pausiert | pausiert | Job gelöscht; erzeugte Einladungen gültig | L6: fortsetzen/verwerfen |
| Z-11 | Pro-Screens, -Tabs, -Spalten | sichtbar | sichtbar mit Z-P-Banner | weg, kein toter Schalter (E-100) | weg | weg | sichtbar |
| Z-12 | Z-B (Basis) Ansicht/Druck/Verifikation | ok | ok | ok | ok | ok | ok |
| Z-13 | Z-D Darstellung | Designer exakt | Designer exakt | Fallback mit Hinweis | Fallback | Fallback (Vorlagenversion bleibt in Free) | Designer exakt |
| Z-14 | Z-D PDF | Download | **Download bleibt** | kein PDF-Link | kein PDF-Link | kein PDF-Link | Download |
| Z-15 | Verifikation Z-B, Z-D, Z-K (alter Token), Z-R | gültig/gültig/ersetzt/widerrufen | identisch | identisch | identisch | identisch | identisch |
| Z-16 | Neue automatische Ausstellung auf C-A | Designer T-1 (aktuelle Version) | Designer T-1, zuletzt veröffentlichte Version | Basisvorlage | Basisvorlage | Basisvorlage | Designer T-1 |
| Z-17 | Designer-Vorlage bearbeiten | ok | 403 | – | – | Entwürfe gelöscht, veröffentlichte referenzierte Versionen bleiben | ok |
| Z-18 | Korrektur/Widerruf (Free, #17) | ok | ok | ok (Korrektur behält `template_version`) | ok | ok | ok |
| Z-19 | Sammel-Neuausstellung (neuer Lauf) | möglich | 403 | 404 | 404 | 404 | möglich |
| Z-20 | Sammel-Neuausstellung (laufend) | läuft | endet regulär | pausiert, Teilmenge konsistent | pausiert | Job gelöscht, Teilmenge bleibt | fortsetzen/verwerfen |
| Z-21 | Free-Settings (Gruppen, Zertifikate) | unverändert | unverändert | unverändert | unverändert | unverändert | unverändert |
| Z-22 | Systemstatus | „Pro aktiv“ | Warnung „Pro abgelaufen“ + Liste eingefrorener Regeln | Hinweis „Pro-Daten vorhanden, Add-on inaktiv“ | wie L4 | kein Hinweis | „Pro aktiv“ |

### 3.3 Sequenztest und Prüfsumme

**Ablauf (GRP-060/CRT-060, E2E + Integration):** FX-LIC in L1 aufbauen → Prüfsumme **P1** → L3 → Prüfsumme **P3** → L4 → L5 → Prüfsumme **P5** → L6 (Neuinstallation + Lizenz) → Prüfsumme **P6**. Zwischen den Schritten laufen keine Jobs (Jobs prüfen GRP-044/CRT-043 getrennt).

**Free-Prüfsumme:** sha256 über die sortierten Zeilen von `ccap_group`, `ccap_group_course`, `ccap_seat`, `ccap_grant`, `ccap_certificate`, `ccap_certificate_template`, `ccap_course_staff` (ohne Spalten `updated_at`, `row_version`) sowie über die Free-Optionen `course_captain_settings_*`. **Erwartung:** P1 = P3 = P5 = P6. In L5D ist genau die dokumentierte Differenz erlaubt (Z-03 Delegationszeilen, Z-04 Hierarchieauflösung) und wird im Uninstall-Bericht gezählt.

**Zusätzlich je Schritt:** alle Verifikationsantworten aus Z-15 byte-gleich (ohne Header); jede Free-Route aus §1.2/§2.2 liefert denselben Status wie in L0; kein PHP-Fehler/Warnung im Log; keine Mail außer den dokumentierten (keine Nachholmails nach L6).

---

## 4. Testfälle

Typ: **U** Unit, **I** Integration (PHPUnit + WP-Testsuite), **E** E2E (Playwright), **P** Parallelität (R1-42-Muster), **A** Barrierefreiheit (AXE/MAN nach R0-15), **L** Lizenzzustand (§3.1). Bestehende Fälle werden **referenziert, nicht dupliziert** (R0-12 §5 Nr. 7): SEAT-001…022, ID-001…015 (R0-09), RBAC-036/051…066 (R0-07), SEC-034, PRIV-025 (R0-17), CON-037 (R0-21), SET-072…093 (R0-27), REV-045/046 (R0-23). Die Vorschläge CERT-001…006 und CERT-008 aus R0-12 sind hier als CRT-001…007 übernommen (Alias in Klammern). Die dreistelligen IDs kollidieren nicht mit den Fixture-Namen GRP-1/GRP-2 aus R0-07.

### 4.1 Gruppen – Free (Ziel R1-35)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| GRP-001 | I | `groups_enabled = false`, Admin ruft „Gruppe anlegen“ auf | abgewiesen mit Hinweis auf Setting; bestehende Gruppen und Joins weiter möglich | R1-35 | GF-01, SET-072 |
| GRP-002 | E | Admin legt Gruppe ohne Kauf an (Kurs C-A, 10 Plätze, Besitzer U-GO) | Gruppe `active`, `source_type = manual`, Besitzer ohne Grant, Mail an Besitzer, Audit | R1-35 | GF-02, SEAT-001 |
| GRP-003 | I | Anlage ohne Besitzer, mit 0 Plätzen, mit URL im Namen, unbefristet ohne Bestätigung | je feldnaher Fehler bzw. bereinigter Name; nichts gespeichert außer im Erfolgsfall | R1-35 | GF-02, SEAT-021 |
| GRP-004 | I | Plätze von 10 auf 12 erhöhen | 2 freie Plätze mehr, keine weitere Wirkung | R1-35 | GF-03 |
| GRP-005 | E | Reduktion 10 → 5 bei 6 aktiv + 2 eingeladen, erst Vorschau, dann Bestätigung | Vorschau nennt 2 Einladungen + 1 Mitglied (zuletzt beigetreten), schreibt nichts; nach Bestätigung genau diese entfernt | R1-35 | GF-04, SEAT-010/011 |
| GRP-006 | I | Laufzeit verkürzen, ein Seat-Grant hat `valid_until_locked` | alle übrigen Seat-Grants übernehmen das Datum, der gesperrte nicht | R1-35 | GF-05 |
| GRP-007 | I | Gruppe aussetzen, fortsetzen, beenden | Seat-Grants folgen (R0-09 §4.5); beendete Gruppe nur lesend | R1-35 | GF-06, SEAT-012 |
| GRP-008 | E | Besitzerwechsel U-GO → U-S1 mit Begründung | U-GO 404 sofort, U-S1 verwaltet, alter Gruppenlink ungültig, neuer nur U-S1 sichtbar | R1-35 | GF-07, RBAC-057 |
| GRP-009 | I | Besitzerwechsel ohne Begründung | abgewiesen | R1-35 | GF-07 |
| GRP-010 | I | `claim_policy` → `invited_address_only` | Gruppenlink deaktiviert und rotiert; persönliche Einladungen gültig | R1-35 | GF-08, SEAT-015 |
| GRP-011 | E | Besitzer lädt 3 Adressen ein, eine ungültig, Gruppe hat 2 freie Plätze | Ergebnis `invited, invited, invalid`; dritte gültige Adresse würde `limited` | R1-35 | GF-09, SEAT-002 |
| GRP-012 | I | Einladung erneut senden, dann alten Link öffnen | alter Token `ccap_invalid_token`, neuer gültig, Budget um 1 erhöht | R1-35 | GF-10, SEAT-008 |
| GRP-013 | I | Offene Einladung zurückziehen | Platz frei, `invite_email` sofort NULL, Token ungültig | R1-35 | GF-10 |
| GRP-014 | E | Gast öffnet Gruppenlink, registriert sich, tritt bei | Rücksprung zur Vorschau, Beitritt, Kurs in L-04 sichtbar | R1-35 | GF-12, ID-006 |
| GRP-015 | I | Beitritt, obwohl Kurs schon unbefristet manuell freigeschaltet | Vorschau warnt; nach Bestätigung zwei Grants, effektiver Zugang unverändert | R1-35 | GF-12, GRANT-011 |
| GRP-016 | E | Besitzer entfernt aktives Mitglied mit Grund | Seat `removed`, Seat-Grant `revoked`, manueller Grant derselben Person bleibt, Lernhistorie bleibt | R1-35 | GF-13, SEAT-009 |
| GRP-017 | I | Gleichen Platz 5× nacheinander umverteilen | in Free erlaubt (E-115); Kennzahl `reassignments_per_seat = 5` sichtbar in A-10 | R1-35 | GF-13, §1.4 |
| GRP-018 | E | Besitzer öffnet L-18 | Status, Anzeigename, maskierte Adresse; keine Spalte Fortschritt/Zertifikat; offene Einladung Klartext nur für den Besitzer | R1-35 | GF-15, SEAT-017, RBAC-059 |
| GRP-019 | I | Besitzer ruft `/reports/groups/{g}` und R-43 `?group=` auf | 403 bzw. 404 (GR-7) | R1-35 | GF-16, RBAC-036 |
| GRP-020 | I | Trainer mit Gruppenzuweisung G-A filtert Basisbericht | nur Mitglieder von G-A, Spalten nach E-98 | R1-35 | GF-16, E-231 |
| GRP-021 | A | L-17, L-18 und A-10 mit Tastatur und Screenreader (Einladen, Link kopieren, Entfernen mit Bestätigung, Fehler `ccap_group_full`) | alle Aktionen ohne Maus; Statusmeldungen als Live-Region; Fehler feldnah (K-14) | R1-35 | R0-15 ST-6/ST-8 |
| GRP-022 | I | Free-Gruppendienst ohne registrierten `groupManagerResolver`, Zeile `ccap_course_staff(group_manager)` vorhanden | Delegierter 404 auf alle RG-12-Routen | R1-35 | GR-3 |
| GRP-023 | I | Elterngruppe mit `parent_group_id`-Kind (direkt in DB, ohne Pro): Beitritt auf Elterngruppe | Belegung zählt Kontingent; letzter freier Platz korrekt; kein Überbuchen | R1-35 | GR-4, Δ-2 |
| GRP-024 | I | Elterngruppe ohne Pro unter `eigene Belegung + Kontingente` reduzieren | `ccap_group_quota_conflict`, nichts geändert | R1-35 | GF-04, Δ-4 |
| GRP-025 | P | 50 parallele Joins auf letzten Platz einer Elterngruppe, deren Kind gleichzeitig Joins erhält | Belegung Eltern ≤ Plätze − Kontingent; Kind ≤ Kontingent | R1-42 | GR-4, SEAT-003 |

### 4.2 Gruppen – Pro (Ziel R3-08)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| GRP-030 | E | Besitzer ernennt Delegierte U-D (nach E-237) | U-D sieht G-A in L-18, kann einladen/entfernen, nicht Plätze/Besitzer/Status/Delegierte ändern | R3-08 | GP-01 |
| GRP-031 | I | Delegierte versucht, weitere Delegierte zu ernennen | 403 | R3-08 | GP-01, E-237 |
| GRP-032 | E | Untergruppe G-C mit Kontingent 5 aus G-P (20) anlegen, dann 16 Plätze in G-P belegen wollen | 15 möglich, 16. `ccap_group_full` | R3-08 | GP-02, GR-4 |
| GRP-033 | I | Kontingent G-C unter eigene Belegung senken | Vorschau nach R0-09 §4.4, Bestätigung erforderlich | R3-08 | GP-02 |
| GRP-034 | I | Untergruppe einer Untergruppe anlegen | abgewiesen (Tiefe 1, Vorschlag) | R3-08 | GP-02 |
| GRP-035 | P | Umbuchen A → B parallel zu Join auf letzten Platz von B | genau ein Gewinner; bei Verlust bleibt Mitglied in A (TX rollt zurück) | R3-08 | GP-03, R0-09 §6.1 |
| GRP-036 | I | Umbuchen in eine Gruppe außerhalb der Wurzelgruppe | abgewiesen, Hinweis auf Einladung (E-234) | R3-08 | GP-03 |
| GRP-037 | E | CSV mit 120 Zeilen (5 ungültig, 3 doppelt, 2 bereits Mitglied), Dry-run, Commit | Dry-run schreibt nichts; Commit: 110 Einladungen, Bericht mit Zeilennummern; keine Konten angelegt (E-233) | R3-08 | GP-04 |
| GRP-038 | I | CSV mit Formel-Präfixen (`=`, `+`, `-`, `@`) und Fremd-Encoding | als Text behandelt; Fehlerbericht neutralisiert (CSV-Injection) | R3-08 | GP-04, R0-17 |
| GRP-039 | I | Import über Einladungsbudget | Rest `limited`, Job meldet Fortsetzungszeit | R3-08 | GP-04, GF-09 |
| GRP-040 | I | Gruppen-Drip G-P: Modul 2 ab Gruppenstart + 7 d | Mitglied, das an Tag 6 beitritt, sieht Modul 2 an Tag 7 | R3-08 | GP-05, E-14 |
| GRP-041 | I | Gruppenbericht als Admin; als Besitzer ohne/mit `share_progress` (E-232) | Admin: vollständig nach Scope; Besitzer: 403 bzw. nur Fortschritt und Abschluss freigegebener Mitglieder, nie Antworten, Punkte, Versuche oder Zertifikatsstatus | R3-08 | GP-06, E-232 |
| GRP-042 | I | Umverteilungsregel „max. 2 je Platz“: dritte Neubelegung | `ccap_seat_reassignment_limit` (Pro-Code), Platz bleibt frei | R3-08 | GP-08 |
| GRP-043 | I | Massenaktion „3 Gruppen aussetzen“ mit Dry-run | Vorschau zählt betroffene Seat-Grants; nach Bestätigung je Gruppe ein Audit | R3-08 | GP-07 |

### 4.3 Gruppen – Lizenzablauf (Ziel R3-08, Gate R1-36 für L0/L4-Teile)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| GRP-044 | L | Import- und Sammeljob laufen, Lizenz → L3, danach L4, danach L6 | L3: Job endet regulär; neuer Job 403. L4: laufender Job pausiert. L6: Fortsetzen ohne Doppel-Einladung | R3-08 | Z-09/Z-10 |
| GRP-045 | L | Delegierte U-D in L1, L3, L4, L6 | schreibt / liest + 403 / 404 / schreibt sofort | R3-08 | Z-03 |
| GRP-046 | L | L5D mit Hierarchie G-P/G-C | `parent_group_id` NULL, G-P `seats` 15, Summe 20, Audit, Uninstall-Bericht nennt 1 aufgelöste Hierarchie und n Delegationen | R3-08 | Z-04 |
| GRP-060 | L/E | Sequenz L1 → L3 → L4 → L5 → L6 mit FX-LIC (§3.3) | P1 = P3 = P5 = P6; Z-01…Z-11, Z-21, Z-22 wie Matrix | R3-08 | §3.3 |
| GRP-061 | L | Besitzer-Selbstverwaltung G-A in L0…L6D (GF-09…GF-15) | in jedem Zustand gleiches Ergebnis wie L0 | R1-36, R3-08 | GR-2, SET-076 |
| GRP-062 | L | Gruppen-Drip in L3 und L4 | L3 eingefroren; L4 Free-Drip; Warnliste vor Deaktivierung nennt C-A/Modul 2 | R3-08 | Z-06 |
| GRP-063 | L | Pro-UI in L3 und L4 | L3: Z-P-Banner, Schreibknöpfe deaktiviert mit Erklärung; L4: keine Pro-Elemente, kein Upsell (E-100) | R3-08 | Z-11 |
| GRP-064 | L | Lizenz `invalid` (Signatur manipuliert) | Verhalten wie L3, Diagnoseeintrag | R3-08 | R0-11 F5.4 |
| GRP-065 | L | L2 Grace 13 Tage, dann Tag 15 | Tag 13 wie L1; Tag 15 wie L3 ohne Fehlermeldung an Lernende | R3-08 | §3.1 |

### 4.4 Zertifikate – Free (Ziel R1-44\* Domäne, R1-35 Verwaltung/UI)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| CRT-001 (=CERT-001) | I | Kurs erfüllt Abschlussregel, `auto_issue` an | genau ein Zertifikat `revision_no = 1`, `issued`; Snapshot mit Kursrevision, Evidenz, `template_version`, Sprache; Event `certificate.issued` | R1-44\* | CF-02 |
| CRT-002 (=CERT-002) | P | Ausstellung doppelt (Cron + Abgabe) | ein Zertifikat, zweiter Aufruf liefert dasselbe | R1-44\*, R1-42 | CF-02 |
| CRT-003 (=CERT-003) | I | Korrektur nach Nachbewertung | `revision_no = 2`, alte `corrected`, alter Token „ersetzt“, Mail N-06 mit HTML-Link | R1-44\* | CF-09 |
| CRT-004 (=CERT-004) | I | Bei Abgabe kein Grant aktiv (E-113) | Ergebnis gewertet, kein automatisches Zertifikat; manuelle Ausstellung CF-03 möglich | R1-44\* | CF-02/03 |
| CRT-005 (=CERT-005) | E/A | HTML- und Druckansicht mit langen Namen, Umlauten, RTL-Kurstitel | vollständig, Druck 1 Seite A4, kein serverseitiges PDF, Text statt Bild (A11Y-096) | R1-44\*, R1-19 | CF-06 |
| CRT-006 (=CERT-006) | I | Datenschutzexport und Löschung | Export enthält Snapshot-Daten; Eraser nach E-150; Verifikation danach `ccap_not_found` bzw. ohne Name | R1-44\*, R1-17 | CF-12 |
| CRT-007 (=CERT-008) | L | Lizenz läuft ab, Add-on aktiv | Basisnachweise und Verifikation unverändert, Designer-Vorlage eingefroren | R3-08 | Z-12…Z-17 |
| CRT-008 | U | Snapshot-Kanonisierung: gleiche Daten in anderer Schlüsselreihenfolge, NFD-Umlaute | gleicher `snapshot_hash` | R1-44\* | §2.4 |
| CRT-009 | I | Snapshot mit unbekanntem Feld bzw. fehlendem Pflichtfeld schreiben | `ccap_validation_failed`, nichts gespeichert | R1-44\* | §2.4 Regel 1 |
| CRT-010 | I | Profilname nach Ausstellung ändern | Nachweis zeigt alten Namen; Korrektur auf Antrag übernimmt neuen | R1-44\* | R0-23 §5.2 |
| CRT-011 | I | Manuelle Ausstellung ohne Evidenz bzw. mit `reduced`-Versuch | abgewiesen | R1-44\* | CF-03 |
| CRT-012 | I | Korrektur, die Kurs oder Person ändern will | abgewiesen (Widerruf + Neuausstellung nötig) | R1-44\* | R0-23 §5.3 |
| CRT-013 | I | Widerruf Revision 2 eines korrigierten Zertifikats | alle Revisionen `revoked`; beide Tokens „widerrufen am …“; Mail N-07 nur Kategorie | R1-44\* | CF-10, REV-046 |
| CRT-014 | I | Widerruf ohne Grund; durch Trainer; eigenes Zertifikat mit #17 | abgewiesen / 403 / 403 | R1-44\* | RBAC-062/064 |
| CRT-015 | E | Verifikation je Zustand (`valid`, abgelaufen, `corrected` alt, `revoked`, unbekannt) | Antworten nach §2.5; unbekannt ohne Hinweis auf ähnliche Tokens | R1-44\* | CF-07, SEC-034 |
| CRT-016 | I | Verifikationsantwort auf Feld-Policy prüfen (Schema-Test) | enthält nie E-Mail, Konto-ID, Punkte, Grund, Vorlagenart | R1-44\* | §2.5 |
| CRT-017 | I | Namensfreigabe an/aus, Abruf vor und nach | sofort wirksam, auch für alten Token einer Korrektur | R1-35 | CF-08, PRIV-025 |
| CRT-018 | I | Basisvorlage ändern nach drei Ausstellungen | neue `template_version`; drei alte rendern unverändert; neue Ausstellung mit neuer Version | R1-35 | CF-04, SET-092 |
| CRT-019 | I | Prüfcode im Druck = Prüfcode auf Verifikationsseite; ein Zeichen im Snapshot manipuliert | gleich; nach Manipulation Integritätsprüfung schlägt an (Systemstatus) | R1-44\* | §2.4 |
| CRT-020 | I | Kurs-Slug ändern, alten Verifikationslink aufrufen | 301 auf neue URL, Antwort gleich (E-220) | R1-35 | §2.4 `verify` |
| CRT-021 | I | Free-Antworten R-40 und Mails N-05…N-07 auf PDF-Bezug prüfen | kein `pdf_url`, kein PDF-Link (CR-6) | R1-35 | E-96, T27-4 |
| CRT-022 | I | Zertifikat mit `template.kind = designer` und `fields`/`assets` ohne Pro rendern | Fallback mit Hinweis, alle `fields` als Liste, Assets als Alt-Text; Status und Verifikation normal | R1-44\* | CR-4 |
| CRT-023 | I | Unbekannte `template.kind = "xyz"` | Fallback, kein Fehler, Diagnoseeintrag | R1-44\* | CR-4 |
| CRT-024 | I | Referenziertes Logo aus Mediathek löschen | Warnung vor dem Löschen (SOLL); danach Nachweis ohne Logo, kein Platzhalterbild | R1-35 | §2.4 Regel 3 |
| CRT-025 | E | Lernender mit Lernsperre | eigene Ansicht `ccap_account_locked`; Verifikation 200 | R1-35 | RBAC-066 |
| CRT-026 | E | Admin-Liste A-11: Filter, CSV-Export mit/ohne Begründung | ohne Begründung abgewiesen; CSV ohne E-Mail, Audit-Eintrag | R1-35 | CF-11 |
| CRT-027 | A | L-15, L-16, A-11 mit Tastatur/Screenreader; Druckvorschau mit 200 % Zoom | Überschriftenhierarchie, Status als Text, Links mit Zweck, kein Informationsverlust | R1-35 | R0-15 ST-5/ST-8 |
| CRT-028 | I | Verifikation über Seiten-Cache/Proxy vor und nach Widerruf | nachher sofort `revoked` | R1-25, R1-44\* | CON-037, T-40 |
| CRT-029 | U | `course_captain_verify_certificate_v1` mit Token, UID ohne Recht, UID mit #17 | Minimaldaten / `ccap_not_found` / volle Daten | R1-44\* | R0-19 |

### 4.5 Zertifikate – Pro (Ziel R3-08)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| CRT-030 | E | Designer: Vorlage T-1 mit Logo, Signaturbild, Zusatzfeld „Unterrichtseinheiten“ veröffentlichen, Ausstellung | neue Version in `ccap_certificate_template` (`kind = designer`); Snapshot enthält aufgelöstes Feld und Assets mit Alt-Text | R3-08 | CP-01, §2.4 |
| CRT-031 | I | Designer-Vorlage ohne Alt-Text für Signaturbild veröffentlichen | Validierungsfehler (oder Bild als Artefakt markieren) | R3-08 | CP-03 |
| CRT-032 | I | Zuordnungsregel „Prüfung ≥ 90 % → T-2, sonst T-1“ | richtige Vorlage je Evidenz; Regel ändert nie, **ob** ausgestellt wird | R3-08 | CP-02 |
| CRT-033 | I | PDF für Z-D erzeugen | `application/pdf`, Inhalte = Snapshot; nur Inhaber oder #17 (RBAC-060-Muster) | R3-08 | CP-03 |
| CRT-034 | A | PDF mit veraPDF (PDF/UA-1) und Screenreader-Stichprobe | 0 Fehler in maschinell prüfbaren Regeln; Lesereihenfolge, Sprache, Titel, Link korrekt | R3-08 | CP-03, E-127 |
| CRT-035 | I | PDF für Zertifikat mit Inhaltssprache Englisch in deutscher Site | `/Lang en`, Rahmentexte nach eingefrorener UI-Sprache | R3-08 | E-158 |
| CRT-036 | E | Sammel-Neuausstellung 100 Zertifikate auf T-2, Dry-run, Commit | Dry-run zeigt Diff (nur Vorlagenfelder), schreibt nichts; Commit nach E-235; keine Mails bei Standardeinstellung | R3-08 | CP-04 |
| CRT-037 | I | Sammel-Neuausstellung enthält widerrufenes Zertifikat | übersprungen, im Bericht genannt | R3-08 | CP-04 |
| CRT-038 | P | Sammel-Neuausstellung parallel zu Einzelkorrektur desselben Zertifikats | eine Operation gewinnt (Unique `cert_rev`), die andere meldet Konflikt, keine doppelte Revision | R3-08 | CP-04, R0-21 |
| CRT-039 | I | Rezertifizierungs-Erinnerung 30 d vor `valid_until`, Opt-out gesetzt | keine Mail an abgemeldete Lernende; Frequenzgrenze | R3-08 | CP-06 |
| CRT-040 | U | Architekturtest: Pro-Code schreibt `ccap_certificate` nur über `CertificateService` | kein direktes `INSERT/UPDATE` auf `ccap_certificate` im Pro-Build (statische Prüfung) | R3-08 | CR-1 |

### 4.6 Zertifikate – Lizenzablauf (Ziel R3-08)

| ID | Typ | Fall | Erwartung | Ziel | Bezug |
|---|---|---|---|---|---|
| CRT-041 | L | Z-D in L1, L3, L4, L5, L5D, L6 darstellen | exakt / exakt / Fallback / Fallback / Fallback / exakt | R3-08 | Z-13 |
| CRT-042 | L | PDF-Download Z-D in L3 und L4 | L3 200; L4 kein Link, Pro-Route 404, HTML-Druck verfügbar | R3-08 | Z-14, CR-7 |
| CRT-043 | L | Sammel-Neuausstellung läuft, L3 → L4 → L6 | L3 endet regulär, neuer Lauf 403; L4 pausiert; L6 fortsetzen ohne doppelte Revision | R3-08 | Z-19/Z-20 |
| CRT-044 | L | Neue Ausstellung auf C-A in L1, L3, L4 | T-1 aktuell / T-1 zuletzt veröffentlicht / Basisvorlage | R3-08 | Z-16 |
| CRT-060 | L/E | Sequenz L1 → L3 → L4 → L5 → L6 (§3.3) | Verifikationsantworten Z-15 byte-gleich; Prüfsummen gleich; Z-12…Z-20 wie Matrix | R3-08 | §3.3 |
| CRT-061 | L | Korrektur von Z-D in L4 | neue Revision mit gleicher `template_version`; in L6 Designer-Darstellung der neuen Revision | R3-08 | CF-09, Z-18 |
| CRT-062 | L | L5D: Designer-Entwürfe und PDF-Cache gelöscht | veröffentlichte, referenzierte Versionen in `ccap_certificate_template` vorhanden; L6D rendert Z-D exakt | R3-08 | CR-3, Z-17 |
| CRT-063 | L | Free ohne je installiertes Pro (L0): Settings, A-11, L-15 | keine Designer-, PDF- oder Lizenzelemente (SET-128) | R1-35 | E-100 |

**Zählung:** GRP 25 + 14 + 9 = 48 Fälle (GRP-001…025, 030…043, 044…046, 060…065), CRT 29 + 11 + 8 = 48 Fälle (CRT-001…029, 030…040, 041…044, 060…063). Lizenzfälle (Typ L): GRP 9, CRT 9 (inkl. CRT-007).

---

## 5. Entscheidungsbedarf (beschlossen)

Die acht Punkte dieses Dokuments sind entschieden (`ENTSCHEIDUNGEN.md` Abschnitt 35; E-232 im Beschlussprotokoll, die übrigen „Von Claude entschieden“) und eingearbeitet:

| Früher | Beschluss | Inhalt | Eingearbeitet in |
|---|---|---|---|
| D29-1 | **E-231** | Gruppen-Auswertung in Free nur als Gruppenfilter im Basisbericht (Spalten nach E-98, Rollen mit #12, nie Besitzer); eigene Gruppenberichte in Pro | GF-16, GP-06, GRP-020 |
| D29-2 | **E-232** (Auftraggeber) | Fortschrittseinsicht für Gruppenbesitzer/Delegierte **nur in Pro** als Opt-in-Gruppeneinstellung (`share_progress_with_managers`, Standard aus), **nur Fortschritt und Abschluss**, nie Antworten, Punkte oder Versuche; Lernende werden informiert (Hinweis in Einladungsmail und Claim-Vorschau L-17) | GR-7, GF-12, GP-06, GRP-041, Δ-3, Δ-11 |
| D29-3 | **E-233** | Massenimport erzeugt nur Einladungen, keine Konten, keine Direktzuweisung | GP-04, GRP-037 |
| D29-4 | **E-234** | Umbuchen ohne neuen Claim nur innerhalb derselben Stammgruppe (Wurzelgruppe); sonst Einladung | GP-03, GRP-036 |
| D29-5 | **E-235** | Sammel-Neuausstellung als Layout-Revision (`revision_kind = layout`); der alte Link bleibt gültig und zeigt die neue Fassung | CP-04, §2.4, Δ-10, CRT-036 |
| D29-6 | **E-236** | öffentliche Verifikation zeigt ohne Opt-in weder Initialen noch Ergebnis (präzisiert E-97) | §2.5 |
| D29-7 | **E-237** | Delegierte ernennen nur Besitzer und Admins; Delegierte selbst ernennen niemanden | GP-01, GRP-030/031 |
| D29-8 | **E-238** | kein externer Dienst; findet der Technik-Spike keine Bibliothek für getaggte PDFs, liefert Pro kein PDF (HTML/Druck bleibt) | CP-03, §2.3 |

**Abhängigkeiten auf Beschlüsse:** Gruppen in Free → E-17; Drip → E-14; Lizenzende-Verhalten → E-93/E-94; Format → E-96; Name → E-97; Berichtsumfang → E-98; Pro-Schalter → E-100; Sammel-Neuausstellung Pro → E-110; Umverteilung ohne Grenze in Free → E-115 (Pro-Regel GP-08); PDF/UA → E-127; Lösch-/Aufbewahrungsregel → E-150; Sprache → E-158; R1-44 → E-183; Gruppen standardmäßig aus → E-222; Zugang je Modul → E-27; interne Outbox → E-38.

### 5.1 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-29-1 | E-232 nennt „Fortschritt/Abschluss“; die frühere Empfehlung enthielt zusätzlich „Zertifikat ja/nein“, „nur für Mitglieder, die nach dem Einschalten beitreten“ und „Mitglied kann die Freigabe zurücknehmen“. Hier gilt die strengere Lesart: **ohne** Zertifikatsstatus; Neubeitritte und Rücknahme bleiben als Schutz drin | E-232 äußert sich zu diesen Details nicht ausdrücklich; Transparenz/Einwilligung braucht die juristische Bestätigung durch den Betreiber | Auftraggeber (Datenschutz) |
| OP-29-2 | Gruppen und Plätze je Modul (E-27: „Gruppenplätze je Modul möglich“): Felder der Gruppenzuordnung (`ccap_group_course` mit `module_id`) und Anzeige im Gruppenbericht | Datenmodell in R0-06/R0-09 noch nicht nachgezogen; Scopes neben `module_id` offen | Architektur (R0-06/R0-09) |
| OP-29-3 | Zertifikate bei Zugang nur zu einzelnen Modulen (E-27) oder über ein Bundle (E-25a): Gilt die Ausstellungsregel je Kurs unverändert? | Beschlüsse regeln nur den Zugang, nicht die Nachweisregel | Auftraggeber (Didaktik) |

---

## 6. Deltas zu anderen Dokumenten (technisch, mit deren Freigabe einzuarbeiten)

| Nr. | Dokument | Delta |
|---|---|---|
| Δ-1 | R0-06 `ccap_group` | `parent_group_id bigint unsigned NULL` + Index `parent (parent_group_id)`; FK → `ccap_group` (RESTRICT). Free liest, nur Pro schreibt |
| Δ-2 | R0-09 §4.1 | Belegung = eigene Plätze (`active` oder offene Einladung) **+** `SUM(seats)` der nicht widerrufenen Kinder; Nachzählung im Join unter dem Lock der Elterngruppe; Lock-Reihenfolge Eltern vor Kind |
| Δ-3 | R0-19 Slots | neu: `groupManagerResolvers`, `certificateRenderers`, `certificateTemplateKinds`, `groupDetailTabs`, `certificateListActions`; `responseFields` dokumentiert `pdf_url` (R-40) und Transparenzhinweis (R-31, E-232) als Pro-Felder |
| Δ-4 | R0-19 Fehlerkatalog | `ccap_group_quota_conflict` (409, Free); Pro-Katalog: `ccap_pro_license_required` (403), `ccap_seat_reassignment_limit` (409) |
| Δ-5 | R0-19 R-35…R-37 | neu `POST /me/groups/{g}/invites/{seat}/resend`; R-37 gilt auch für `invited` (Zurückziehen) |
| Δ-6 | R0-19 R-34 | Feld `claim_policy` (nur M-15/#11; Wechsel rotiert Link) |
| Δ-7 | R0-06 | neue Tabelle `ccap_certificate_template (id, template_uid char(36), version int unsigned, kind varchar(32), definition longtext, definition_hash char(71), status varchar(16) published, created_at, created_by)`, `UNIQUE (template_uid, version)`; append-only; Basisvorlage (SET-092) schreibt hierher statt in eine Optionshistorie |
| Δ-8 | R0-19 R-40/R-41, R0-07 RG-14 | R-40 ohne `pdf_url`, mit `view_url`; R-41 entfällt in Free und wird Pro-Route `course-captain-pro/v1/certificates/{uid}/pdf`; RBAC-060/065 auf HTML-Ansicht bzw. Pro-Route umstellen |
| Δ-9 | R0-19 | neu `PUT /me/certificates/{uid}/visibility {holder_name_public: bool}` (M-18, eigenes Zertifikat) |
| Δ-10 | R0-06 `ccap_certificate`, R0-08 §7.2 | `revision_kind varchar(16) NOT NULL DEFAULT 'initial'` (`initial/correction/layout`); Snapshot-Felder `format_version`, `display_id`, `texts`, `fields`, `assets`, `verify.path_at_issue` |
| Δ-11 | R0-07 | M-15 um Resolver-Aufruf (GR-3) ergänzen; M-16 Besitzer bleibt `do_not_allow` in Free, Pro-Erweiterung nur nach E-232 |
| Δ-12 | R0-11 §1.5 | Zeile Zertifikatsdesigner: PDF-Download bleibt bei `expired` (CR-7); neue Zeilen Untergruppen, Massenimport, Sammel-Neuausstellung (Tabelle §3.2); L-15 „Format N-04“ → HTML/Druck; N-05 „Initialen“ nach E-236 |
| Δ-13 | R0-12 | CERT-001…006, CERT-008 → CRT-001…007 (Alias); R1-35 und R1-44\* zeigen auf §4 dieses Dokuments; Gate B-8 um CRT-008…029 erweitern |
| Δ-14 | R0-13 K-15 | „Mail mit neuem PDF-Link“ → Link auf HTML-Ansicht (bestätigt T27-4) |
| Δ-15 | konzept.md Kap. 4.5, 5 | redaktionell nach C-01/C-02: Free = Basisnachweis mit Verifikation, Gruppen-Grundfunktion; Pro = Designer, PDF, Vorlagen, erweiterte Gruppenorganisation |
| Δ-16 | TODO R1-35, R1-44\* | DoD-Verweis auf R0-29 §1.2, §2.2, §2.4, §4; R3-08 DoD auf §1.3, §2.3, §3 |

---

## 7. Nachweis

- Gelesen: `TODO-WETTBEWERBSANALYSE.md` (R0-29, R1-35, R1-44, R3-08); `konzept.md` Kap. 4.5, 5, 6, 7, 35.1, 35.4, 43.1–43.5, 46.1; `docs/r0/ENTSCHEIDUNGEN.md` (Beschlussprotokoll E-01…E-20, Empfehlungen bis E-224, gezielt E-14, E-17, E-28, E-36, E-46, E-73, E-81, E-84, E-89, E-93…E-100, E-110…E-117, E-127, E-136, E-145, E-150, E-158, E-183, E-220, E-222); R0-04 (Z-3/Z-4/Z-6, BS-14, K-22, Befund ohne Quellbestand); R0-06 (E-35…E-39, `ccap_group`, `ccap_group_course`, `ccap_seat`, `ccap_certificate`); R0-07 (§2, §3.1 #1…#24, M-15…M-19, RG-11/12/14/15, RBAC-036, 051…066, D07-1, D07-4); R0-08 §7; R0-09 §1, §4, §6.3, §8, §9; R0-11 §1.1–1.5, §2.2/2.3, §3.3, §3.5, §4, §5; R0-12 (LEARN-08, CERT-001…008, R1-35, R1-44\*, D12-2); R0-13 K-10…K-16; R0-15 (A11Y-092/096, D-15-09); R0-17 (T-38…T-40, SEC-034, PRIV-025); R0-19 (R-31…R-45, Slots, Fehlercodes, Events); R0-21 CON-037; R0-23 §5, R23-D8; R0-27 (K43-27/28, K43-34…37, SET-072…093, SET-128, N-02, N-05…N-07, T27-4, D27-8).
- Keine LearnBoating-Quellen gelesen (E-01; für Zertifikate existiert kein Bestand, für Gruppen genügen die Verhaltensbefunde aus R0-09).
- Keine Implementierung, keine Testausführung, kein Commit.
