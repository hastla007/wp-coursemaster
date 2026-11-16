# R0-11 · Free-/Pro-Matrix, Screen-Inventar und UX-Flows (Entwurf)

**Status:** **Entwurf – Freigabe Auftraggeber/Produkt offen.** Task R0-11 ist **nicht** abgehakt. Keine Implementierung, kein Wireframe-Ersatz für R0-30.
**Stand:** 16.11.2026
**Bezug:** TODO R0-11 (DoD: „Jede Funktion und jeder Admin-/Learner-Screen eindeutig einer Edition/Phase zugeordnet“), Folgetasks R1-05, R1-16, R1-32, R1-35, R3-08 (dazu R0-29, R0-30, R3-15, R3-18); Konzept Kap. 3–7, 10, 16, 17, 27, 29, 35.4, 43, 46; `docs/r0/R0-04-r1-r3-grenze.md` (Bausteine BS-01…BS-36, BS-Z, Kopplungen K-xx, Widersprüche W-xx); `docs/r0/R0-08-kurs-activity-pruefungsvertraege.md` (Gate-Vertrag §1.4, Activity-Typen §2.4, Versuchsautomat §6, Fehlercodes §6.6, Zertifikats-Snapshot §7.2); Inventur `docs/r0/R0-02-inventar/D-admin-frontend-api.md` (Menübaum §3.1, Lernerscreens §5.3, INV-D-001…088); `docs/r0/ENTSCHEIDUNGEN.md`.
**Quelle:** Für dieses Dokument wurde LearnBoating nicht erneut gelesen; alle LB-Aussagen stammen aus der Inventur (INV-…) bzw. aus R0-04/R0-08.

**Normative Sprache:** **MUSS** = Vertragsbestandteil, **SOLL** = Standard, **KANN** = optional. Alles hier ist Vorschlag bis zur Freigabe.

---

## 0. Annahmen (Empfehlungen aus `ENTSCHEIDUNGEN.md`, noch nicht beschlossen)

Dieses Dokument verwendet die folgenden Empfehlungen als **Annahme**. Wird eine anders beschlossen, sind die genannten Zeilen anzupassen.

| ID | Annahme | Wirkt auf |
|---|---|---|
| E-14 | Relativer Drip („Modul X Tage nach Zugangsbeginn“) Free R1; absolute Termine, Kampagnen, Gruppen-Drip Pro | BS-13, A-04, L-05 |
| E-15 | Kostenloser Teilumfang je Kurs Free R1 | BS-13, L-02, L-05 |
| E-16 | Kurs-FAQ/Lerntipps: Mechanik Free R1, Starttexte R2-03; **öffentliche Website-FAQ bleibt Theme/Drittplugin** | BS-24, A-05, L-20, Konflikt C-07 |
| E-17 | Gruppenbesitzer-Selbstverwaltung und Gruppen ohne Kauf Free; delegierte Verwaltung, Reporting Pro | BS-14, A-10, L-19, Flow 3 |
| E-18 | Rechte-/Auslieferungssperre Free R1, nur für importierte/fremde Inhalte | BS-16, A-13 |
| E-19 | Audit-Viewer Free R1 | BS-19, A-16 |
| E-20 | Feedback-Sichtbarkeit als Kurs-/Prüfungseinstellung, kein Verkaufsfeature | BS-06, A-08 |
| E-21 | Ein Karteikartentyp, zwei Modi, Free R1 | BS-10, L-12 |
| E-22 | Kein „Zahl mit Toleranz“/Fallstudie in R1 | BS-06 |
| E-23 | Kein Modus „offiziell/verifiziert“; Prüfungsregel `draft/published/retired`, Kennzeichen `reduced` | BS-08, A-08, L-13 |
| E-24 | Erinnerung **vor** Zugangsablauf Pro; Free zeigt nur Status/Ablauf, keine Erinnerungsmail | BS-22, BS-30, L-04 |
| E-25 | Kursbeziehung „enthält“ R2, Ausnahme paritätsnötige Teilkurs-Relation R1; Mitgliederrabatt nicht übernehmen | BS-04, BS-29, BS-34 |
| E-26 | Keine abhängigen Zusatz-Grants in R1 | BS-12 |
| E-27 | Zugang je Kurs mit Scopes `view`/`download`/`exam` | BS-12, A-09 |
| E-28 | Einladungslinks nach LB-Regeln, zusätzlich Option „nur die eingeladene Adresse“ | BS-14, Flow 3 |
| E-38 | Course-Ereignisse, Read-API, Connector-Registry Free; Pro nur fertige Adapter und Webhooks | BS-18, BS-33, A-14 |
| E-40 | Form/Workflow/Review-Connectoren in Course Pro oder als separate Plugins | BS-33 |
| E-53 | **WooCommerce- und Voucher-Connectoren sind Pro** (`course.commerce`); Free bleibt shopfrei | BS-31, BS-32, A-14, Flow 5 |
| E-54 | Keine R2-Zuordnung ohne R2-Task; sonst R4-Nachfragepool | BS-27…BS-29 |
| E-05, E-10 | Keine Token-/Client-API in R1; keine Website-Härtung im Free-Kern | BS-27, BS-28 |
| E-07, E-49 | Offiziell nur Single-Site, Netzwerkaktivierung blockiert | BS-26 |
| E-69, E-71, E-72, E-74, E-76, E-77 | Fragetypen R1/R2, Abbruch zählt bei Limit, Auto-Abgabe nach Inaktivität (Option), Nachbewertung im Datenvertrag/UI Pro, Video `manual_confirm`, Teilpunkte | BS-06, BS-08 |

---

## 1. Funktionsmatrix

### 1.1 Zuordnungskategorien

| Code | Bedeutung | Lieferung | Lizenz nötig |
|---|---|---|---|
| **F1** | **Free R1** – Paritätsrelease „Course Captain Free“ (Kap. 29.1, R0-04 Z-1/Z-2) | R1 | nie |
| **F2** | **Free R2** – zusätzliche P0-Funktion mit eigener R2-Task (Kap. 29.1, Z-8, E-54) | R2 | nie |
| **PK** | **Pro-Erweiterung eines R1-Kernbausteins** – die Free-Basis bleibt vollständig, der R1-Kern liefert nur einen neutralen Erweiterungspunkt (Registry, Datenfeld, Hook). Die Pro-Funktion selbst kommt nach R0-04 Z-4 in R3 | Erweiterungspunkt R1, Funktion R3 | ja, für die Erweiterung |
| **P3** | **Pro R3** – eigenständige Pro-Funktion bzw. Adapter ohne Free-Gegenstück | R3 (R3.1/R3.2/R3.4, R3-08, R3-15, R3-18) | ja |
| **X** | **ausgeschlossen** – fachgebunden (Kap. 26.2), ersetzt durch Fremdsystem (BS-Z), per Entscheidung gestrichen oder R4-Nachfragepool | – | – |

Spalte „Entitlement“ nennt den Pro-Schlüssel aus Kap. 6. Wo Kap. 6 keinen passenden Schlüssel hat oder der Name zu weit greift, steht der Vorschlag in *kursiv* (→ Entscheidung N-03).

### 1.2 Editionsregeln

| Nr. | Regel | Beleg |
|---|---|---|
| **ER-1** | **Einmal Free, immer Free.** Eine Funktion, die in einem Free-Release ausgeliefert wurde (F1 oder F2), wird nie nach Pro verschoben, nie hinter einen Lizenzschlüssel gelegt und nicht in Umfang, Grenze oder Qualität gedrosselt (keine Mengenlimits, keine „Testphase“, kein Wasserzeichen). Pro darf nur **hinzufügen**. Eine Verkleinerung einer Free-Funktion ist nur als Bugfix/Sicherheitsfix mit Changelog zulässig, nie aus Editionsgründen. | Kap. 5 („dauerhaft“), Kap. 43.2 („keine irreführenden Pro-Sperren für Free-Kernfunktionen“), Kap. 43.3 „Einordnung“, R0-04 Z-3 |
| **ER-2** | **Kein Entitlement-Schlüssel deckt eine Free-Funktion ab.** Jeder Pro-Schlüssel benennt nur die Erweiterung. Der Free-Kern fragt nie einen Lizenzstatus ab (kein Lizenzclient im Core, E-09, R0-39). | Kap. 6 „Jeder Feature-Key gehört zu genau einem Feature“, Kap. 45 License-Client-Isolation |
| **ER-3** | **Pro-Daten liegen im Free-Datenmodell oder in eigenen Pro-Tabellen, nie als Voraussetzung für Free-Lesepfade.** Lernhistorie, Grants, Versuche, Zertifikate und ihre Verifikation entstehen und bleiben im Free-Kern. | Kap. 35.4, CORE-10, Kap. 45 (Captain Pro: „Free-Datenbestand bleibt bei Lizenzende unangetastet“) |
| **ER-4** | **Lizenzende = Pro-Schreiben gesperrt, Lesen und Export bleiben.** Neue Pro-Schreibaktionen (anlegen, ändern, neue Läufe starten) sind gesperrt; bestehende Pro-Daten bleiben lesbar und exportierbar; Free und Lernhistorie sind unberührt. Ausnahmen, die Lernende oder Käufer schützen, regelt 1.4 (→ N-01, N-02). | Kap. 6 Zeile „Lizenzablauf“, Kap. 35.4, R3-08, R3-15, R3-18 |
| **ER-5** | **Free-Release-Regel für Kopplungen.** Ein Free-Screen darf eine Pro- oder Adapter-Erweiterung nur über eine Registry anzeigen (Menü, Tab, Spalte, Kachel, Response-Erweiterung). Ohne Add-on erscheint kein toter Schalter und kein gesperrtes Feld (→ N-08). | R0-04 K-03, K-05, K-11, K-16, K-17; Kap. 16.3 „keine blockierenden Upsell-Popups“ |

### 1.3 Matrix je Baustein und Teilfunktion

Lesart: Die erste Zeile eines Bausteins ist seine Grundzuordnung aus R0-04. Weitere Zeilen erscheinen nur, wo sich Edition oder Phase einer Teilfunktion unterscheidet.

| BS | Funktion / Teilfunktion | Zuord. | Entitlement | Begründung | Beleg |
|---|---|---|---|---|---|
| **BS-01** | Plugin-Kernel, Bootstrap, Lifecycle, Uninstall | F1 | – | Neutraler Lebenszyklus, Woo-Kopfzeilen entfallen (K-10) | R0-04 BS-01; Kap. 43.3 „Deinstallation“ |
| **BS-02** | Infrastruktur (Schema, Locks, Cron, Caches, Rate-Limit, Idempotenz) | F1 | – | Rein technisch, Kernbedarf auch ohne App-API | R0-04 BS-02; E-46 |
| **BS-03** | Capabilities, Policy-Service, Objekt-Scope | F1 | – | Kap. 17 Capability-Matrix, Objekt-Ownership | Kap. 17; R0-04 BS-03 |
| BS-03 | Rollen Administrator, Course Manager, Instructor (eigene Kurse), Learner | F1 | – | Ohne diese Rollen kein Autor-/Lernbetrieb | Kap. 17 |
| BS-03 | Rolle Tutor/Assessor | → N-07 | – | Ohne Tutorbewertung in R1 ohne Funktion | Kap. 17, Kap. 27.2 |
| BS-03 | Erweiterte Adminrollen, delegierte Rechte | PK | *course.advanced_roles* | Kap. 5 Zeile WordPress (Pro) | Kap. 5 |
| **BS-04** | Kurs, Modul, Lektion, Revision, Status Entwurf/Publiziert/Archiv | F1 | – | Parität und Kernmodell | R0-04 BS-04; R0-08 §1 |
| BS-04 | Teilkurs-Relation „Kombikurs gibt Teilkurs-Inhalte frei“ (INV-A-107, A-144, D-153) | F1 | – | Paritätsteil, Ausnahme in E-25 | E-25, R0-04 W-07 |
| BS-04 | Allgemeine Beziehung „enthält/empfiehlt“ (Bundle, B-068) | F2 | – | E-25; **braucht R2-Task nach E-54** (Konflikt C-10) | E-25, E-54 |
| BS-04 | Voraussetzungen **innerhalb** eines Kurses (Modul/Activity abgeschlossen, Prüfung bestanden) | → N-09 (Vorschlag F1) | – | R0-08 §1.4 Nr. 4 „neu“, LB kennt nur `required` je Modul | R0-08 §1.4; Kap. 3.1 |
| BS-04 | Lernpfade/Kursprogramme mit **kursübergreifenden** Abhängigkeiten | PK | course.advanced_paths | Kap. 5 Zeile Kurse (Pro) | Kap. 5, 6 |
| **BS-05** | Autoren-Adminlisten Kurse, Fragen, Medien, Prüfungsregeln | F1 | – | Kap. 29.1 „Autorenbasis zum Erstellen und Veröffentlichen“ | R0-04 BS-05; INV-D-009…013 |
| BS-05 | WP-native Editoren (Kurs-, Lektions-, Fragen-, Prüfungseditor), Publish-Validator, Vorschau | F1 | – | **Abweichung LB:** LB-Listen sind nur lesend, Inhalt kam über Import; CC braucht Editoren für die Parität „erstellen und veröffentlichen“ | Kap. 16.3, 29.1; R1-05, R1-26 |
| BS-05 | Builder-Komfort (Fragen direkt aus Lektion, erweiterte Duplikation) | F2 | – | R2-04 | Kap. 27.1, 29.1 |
| BS-05 | Frontend-Kurs-Builder | P3 | *course.frontend_builder* | Kap. 27.2, 29.1 R3 | Kap. 27.2 |
| **BS-06** | Fragenbank, Revisionen, Tags, Medien, Feedback | F1 | – | Parität | R0-04 BS-06; R0-08 §3 |
| BS-06 | Typen single, multi, true_false, open_text, flashcard | F1 | – | E-69 | R0-08 §3.1 |
| BS-06 | Typen matching, ordering, cloze | F2 | – | Free nach Paritäts-Gate (E-69); **ER-1:** bleiben Free | E-69; Kap. 3.3 |
| BS-06 | Teilpunkte `all_or_nothing`, `proportional_penalty` | F1 | – | E-77 | R0-08 §3.2 |
| BS-06 | Teilpunkte `per_option` | offen (Vorschlag PK) | course.advanced_assessment | E-77 „nach Didaktik-Review“; Kap. 5 „erweiterte Teilbewertungen“ Pro | E-77, Kap. 5 |
| BS-06 | Feedback-Sichtbarkeit immer/nach Abgabe/nie | F1 | – | E-20 | R0-08 §4.5 |
| BS-06 | Rubrik-/Tutorbewertung, Fragezweige, mehrteilige Fallstudien, Szenarios | PK | course.advanced_assessment | Kap. 3.3 Pro | Kap. 3.3, 27.2 |
| BS-06 | Zahl mit Toleranz, Fallstudie als Navigationstrainer-Ersatz | X (R1); Kandidat R2/Pro | – | E-22 | E-22 |
| BS-06 | Nachbewertung nach Abgabe: Datenvertrag | F1 | – | `evaluation_no` ab R1 | E-74, R0-08 Δ-4 |
| BS-06 | Nachbewertung: Oberfläche | PK | course.advanced_assessment | UI erst mit Gradebook | E-74 |
| **BS-07** | Activities `lesson`, `video`, `file`, `quiz`, `flashcard`; Untertitel; externe Medien mit Consent | F1 | – | Parität | R0-08 §2.4; R0-04 BS-07 |
| BS-07 | Activity-Provider-Interface (intern, für Kern-Typen) | F1 | – | Kap. 4.4 erweiterbares Interface | R0-08 §2 |
| BS-07 | Zusätzliche Activity-Typen (Hotspots, Szenarien, interaktive Medien jenseits Basis) | PK | course.custom_activities | Kap. 27.2 „Erweiterungen Pro“ | Kap. 6, 27.2 |
| BS-07 | Lernnotizen (R2-01), Text-/Dateiaufgaben mit Grundrückmeldung (R2-02) | F2 | – | Kap. 27.1 Free/Free-Basis | Kap. 27.1, 29.1 |
| **BS-08** | Prüfungsengine: Blueprint, feste Bögen, Versuch, Snapshot, Timer, Offline-Puffer, Bewertung, Versuchslimit, Auto-Abgabe-Option | F1 | – | Kap. 5 Quiz-Zeile Free, Parität | R0-08 §4, §6; E-71, E-72 |
| BS-08 | Kennzeichen „verifiziert/offiziell“ | X | – | E-23 | E-23 |
| BS-08 | Prüfungsprogramme mit Retake-/Freigaberegeln, Cooldown-Ketten, erweiterte Teilbewertungen | PK | course.advanced_assessment | Kap. 5 Quiz-Zeile Pro | Kap. 5 |
| BS-08 | Tutorbewertung (Zustand `submitted`) | P3 | course.advanced_assessment | Kap. 27.2, 29.1; in R1 nicht erreichbar | R0-08 §6.1 |
| BS-08 | Gradebook | P3 | *course.gradebook* | Kap. 27.2, 29.1 | Kap. 27.2 |
| **BS-09** | Fortschritt Completion/Mastery/Competency (abgeleitet), Read-Models | F1 | – | Parität, E-73 | R0-08 §5 |
| BS-09 | Eigene Ergebnisübersicht des Lernenden inkl. Export | F1 | – | Kap. 4.5 Free | Kap. 4.5 |
| BS-09 | Erweiterte Kompetenzmatrix, Kohortenstatistik, Learning Analytics | PK | course.analytics | Kap. 5, 27.2 | Kap. 5, 27.2 |
| **BS-10** | Favoriten, Sammlungen, Fehlertraining, Karteikarten | F1 | – | Kap. 26.1, E-21 | R0-04 BS-10 |
| **BS-11** | Smarttrainer, Wiederholungsplanung | F1 | – | Kap. 5 Lernen Free | R0-04 BS-11; R0-08 §5.2 |
| **BS-12** | Grant-Kern: Zustände, Reconciler, Source-Interfaces, manuelle Freischaltung, Laufzeit, Ablauf, Scopes | F1 | – | Kap. 7, 35.2; E-27 | R0-04 BS-12 |
| BS-12 | Woo-/Voucher-Quellen (`wc_item`, Bestellspalten) | P3 | course.commerce | E-53; Adapterteil K-01…K-04 | R0-04 K-01…K-04 |
| BS-12 | Abhängige Zusatz-Grants (`depends_on_grant`) | X (R1) | – | E-26 | E-26 |
| BS-12 | Wiederaufleben nach Erstattung | X | – | E-29 | E-29 |
| **BS-13** | Öffentlich, kostenlose Einschreibung, manuelle Freigabe, Teilumfang, relativer Drip | F1 | – | Kap. 43.3, E-14, E-15 | R0-08 §1.4 |
| BS-13 | Absolute Termine, Drip-Kampagnen, Gruppen-Drip, Abhängigkeitsketten | PK | course.drip_content (*course.drip_campaigns*) | E-14 | Kap. 5, 6; E-14 |
| BS-13 | Festes „Schnupper-Paket“ (INV-D-029) | X | – | exclude | INV-D-029 |
| **BS-14** | Gruppen, Seats, Claim, Einladung, manuelle Gruppenanlage, Besitzer-Selbstverwaltung | F1 | – | Kap. 7, 43.1, 46.1; E-17, E-28 | R0-04 BS-14; R1-35 |
| BS-14 | Delegierte Gruppenadmins, Umbuchen, Massenverwaltung, Untergruppen, Gruppenberichte | PK | course.groups (*course.groups_advanced*) | Kap. 7 Pro-Erweiterung, E-17 | Kap. 7, 43.3 |
| BS-14 | Gruppenplätze aus Woo-Mehrplatzkauf | P3 | course.commerce | R3.1 | Kap. 7; K-22 |
| BS-14 | Gruppengutscheine (ein Code je Platz) | P3 | course.commerce | E-36 | E-36 |
| **BS-15** | Content-Gate, Block „Nur mit Lernzugang“, geschützte Medien/Downloads | F1 | – | Kap. 7, 43.3; E-65 | R0-04 BS-15 |
| BS-15 | Angebotsliste im Sperrhinweis (Kaufoptionen) | P3 | course.commerce | Über `AccessOfferProvider` (K-05) | K-05 |
| **BS-16** | Content-Package v1, Import/Export, Dry-run, Rollback, Rechte/Provenienz, Batch-/Resume-Import großer Pakete | F1 | – | Parität (Kap. 26.1); E-18 | R0-04 BS-16; R0-10 |
| BS-16 | „Massenimport, erweiterte Content-Workflows, erweiterte Veröffentlichungsregeln“ (Kap. 5 Pro) | PK, **nur** jenseits der LB-Parität (z. B. geplante Veröffentlichung, Freigabe-Workflow mit mehreren Stufen) | *course.content_workflows* | Batch-Import großer Pakete ist Parität → F1 (Konflikt C-05) | Kap. 5; R0-04 Z-2 |
| BS-16 | XLSX-Fragenimport (INV-E-028) | offen (E-54) | – | keine R2-Task; R0-04 W-12 schlägt R1 vor | E-54 |
| BS-16 | Import alter LB-Pakete | X (Core); separates Werkzeug | – | E-03 | E-03 |
| BS-16 | Paketsignaturen | X (R1) | – | E-64, Feld reserviert | E-64 |
| **BS-17** | Lernbereich `/lernen/`, Mein Lernen, Kurs-Hub, Lern-/Prüfungs-UI, A11y, Kurskatalog-Block | F1 | – | Parität, Kap. 10 | R0-04 BS-17 |
| BS-17 | Kacheln für Tickets/Praxisangebote, Woo-„Mein Konto“-Verlinkung | P3 | course.commerce / course.suite_integrations | K-17, K-07 | R0-04 K-07, K-17 |
| **BS-18** | REST `course-captain/v1`, Fehlerkatalog, Idempotency-Key, Domain-Events, Read-API, Connector-Registry | F1 | – | E-06, E-38 | R0-04 BS-18 |
| BS-18 | Ausgehende Webhooks | P3 | course.webhooks | Kap. 5, 6; E-38 | Kap. 5 |
| **BS-19** | Privacy-Exporter/-Eraser, Retention-Settings, Self-Service, Audit-Service, Audit-Viewer | F1 | – | Kap. 43.3, E-19, E-43 | R0-04 BS-19 |
| BS-19 | Audit-/Berichtsexport (CSV) über die Privacy-API hinaus | PK | course.analytics | INV-D E8 „Exporte/erweiterte Berichte Pro“ | INV-D-033 |
| **BS-20** | Systemstatus, Health, Restore-Check, Dashboard, WP-CLI | F1 | – | Kap. 16.3, 43.3 | R0-04 BS-20 |
| BS-20 | Partnerdiagnose (Woo/HPOS/Shop-Status) | P3 | course.commerce | K-11, INV-D-039 | R3-14 |
| **BS-21** | Admin-Menü, Settings-Register, Setup-Assistent, Bereitschaftsprüfung | F1 | – | Kap. 16, 43; R1-31/32 | R0-04 BS-21 |
| BS-21 | Seite „Pro-Lizenz“, Pro-Settings-Felder | P3 | – (Add-on selbst) | Kap. 6, 43.2; R3-15 | Kap. 6 |
| **BS-22** | E-Mail-Basis: Absender erben, Vorlagen, Testmail, transaktionale Mails (Einschreibung, Einladung, Claim, Abschluss, Zertifikat) | F1 | – | Kap. 43.3 | R0-04 BS-22; R1-21 |
| BS-22 | Optionale Erinnerungen (Lernerinnerung, Ablauf-Erinnerung) | PK | *course.reminders* | Kap. 43.3, E-24 | Kap. 43.3 |
| **BS-23** | SEO, Consent für externe Medien, Theme-/Cache-Interop | F1 | – | Kap. 43.3, E-45 | R0-04 BS-23 |
| **BS-24** | Kurs-FAQ für Eingeschriebene, Lerntipps: Mechanik | F1 | – | E-16 | R0-04 BS-24 |
| BS-24 | Neutrale Starttexte/Vorlagen | F2 | – | R2-03 | E-16 |
| BS-24 | Öffentliche Website-FAQ (INV-A-123…132, D-056) | X (Theme/Drittplugin) | – | E-16; **widerspricht R0-04 BS-24/W-02** (Konflikt C-07) | E-16 |
| **BS-25** | Tests, Golden Master, QA, CI, Release | F1 | – | CI ohne Woo-Pflicht; Woo-Achse ab R3 | R0-04 BS-25 |
| **BS-26** | Multisite: Aktivierung je Website technisch korrekt, Netzwerkaktivierung blockiert | F1 (bedingt) | – | E-07, E-49 | R0-04 BS-26 |
| BS-26 | Offizielle Multisite-Unterstützung | X (R4-Pool) | – | E-07; TODO R4-01 | E-07 |
| **BS-27** | Website-Härtungsmodul | X (R4-Pool) | – | E-10 „nicht im Free-Kern“ + E-54 (keine R2-Task) | E-10, E-54 |
| **BS-28** | Token-/Client-API, Mobile-App-Einstellungen | X (R4-Pool) | – | E-05, E-54; Kap. 5 „Nicht in v1: native Mobile-Apps“ | E-05 |
| **BS-29** | Starttexte, Paket-CLI (INV-A-122, E-021, E-121) | F2 | – | R2-03 | R0-04 BS-29 |
| BS-29 | XLSX (INV-E-028), Kursbeziehung (INV-B-068) | offen (E-54) | – | s. BS-16, BS-04 | E-54 |
| **BS-30** | Ablauf-Erinnerung (Batch, signierter Abmeldelink, One-Click-Abmeldung) | PK | *course.reminders* | E-24; Free zeigt nur Status/Ablauf | R0-04 BS-30 |
| **BS-31** | WooCommerce-Adapter R3.1 | P3 | course.commerce | E-53 | R0-04 BS-31 |
| **BS-32** | Voucher-Captain-Adapter R3.2 (Kernvertrag BS-12/BS-18 bleibt F1) | P3 | course.commerce | E-53, E-31 | R0-04 BS-32 |
| **BS-33** | Weitere Suite-Connectoren R3.4 (Affiliate, Ticket/Pass, Workflow, Form, Review) | P3 | course.suite_integrations | E-38, E-40 | R0-04 BS-33 |
| **BS-34** | Geld-zurück-Garantie, Mitgliederrabatt | X | – | E-25, E-32 | R0-04 BS-34 |
| BS-34 | Neutrales Nutzungsereignis „Lerninhalt genutzt“ | F1 (Ereignisvertrag) | – | E-32, E-38 | R0-04 W-09 |
| **BS-35** | Pro-Updater und Lizenzclient | P3 | – (Add-on selbst) | E-09; Core ohne Lizenzcode | R0-04 BS-35; R3-18 |
| **BS-36** | Druck-/PDF-Export von Prüfungsbögen | P3 | course.advanced_assessment | Kap. 29.1 R3 | R0-04 BS-36 |
| BS-36 | Mehrere Trainer pro Kurs (Trainer-Kurs-Zuordnung) | P3 | *course.multi_instructor* | Kap. 27.2, 29.1 | INV-E-143 |
| **BS-Z** | Woo-Preise, Shop-Rechtspflichten, OSS, Gateway-Profile, Gutscheinfeld, Tenant-Header | X | – | Z-9, E-33 | R0-04 BS-Z |

### 1.4 Funktionen ohne LB-Quellbestand (kein BS, aber editionsrelevant)

| Funktion | Zuord. | Entitlement | Begründung | Beleg |
|---|---|---|---|---|
| Basisnachweis/Zertifikat: Ausstellung aus nachweisfähiger Quelle, Basisvorlage, Korrektur, Widerruf, öffentliche Verifikation | F1 (**neu**, kein Golden Master) | – | Kap. 43.1, 46.1; R1-35; R0-04 „Befund ohne Quellbestand“ | R0-08 §7.2; Kap. 35.4 |
| Format des Basisnachweises (HTML/Druck vs. PDF) | → N-04 | – | Kap. 4.5 nennt PDF als Pro | Kap. 4.5 |
| Zertifikatsdesigner (Logo, Signatur, Felder, mehrere Vorlagen, Branding) | PK | course.certificates (*course.certificate_designer*) | Kap. 27.2, 43.3 | Kap. 43.3 |
| Berichte Basic (Kursübersicht, Lernendendetail) | F1 | – | Kap. 10 „Basic im Free“; Umfang → N-06 | Kap. 10 |
| Berichte Kohorten, CSV, gespeicherte Auswertungen | PK | course.analytics | Kap. 5, 10 | Kap. 10 |
| Integrationsseite (Partnererkennung, Status, Fehler) | F1 | – | Kap. 43.3 „Plugin-Erkennung … keine Pflichtintegration“ | Kap. 43.3 |
| Kursvorlagen, Demokurse, Duplikation | F2 | – | R2-03 | Kap. 27.1 |
| Kursdiskussion/Trainerfragen | X (Edition offen, nicht R1–R3 geplant) | – | Kap. 27.2 „genaue Edition offen“; keine Task | Kap. 27.2 |
| Kursbewertungen über Review Captain | P3 | course.suite_integrations | Kap. 27.2, E-41 | Kap. 27.2 |
| Abos/Mitgliedschaften/Bundles mit Verkauf | P3 | course.commerce | Kap. 27.2 | Kap. 27.2 |
| Gamification, SCORM/xAPI/H5P/LTI | X (R4) | – | Kap. 27.3, 29.1 R4 | Kap. 29.1 |

### 1.5 Verhalten bei Pro-Lizenzende je Pro-Funktion

**Lizenzzustände** (Kap. 6): `active` → `grace` (kein Serverkontakt, signierter Snapshot gültig bis `cache_expires_at`, höchstens 14 Tage) → `expired`; außerdem `invalid` (Signatur/Site-Limit/Staging-Regel verletzt) und „Add-on deaktiviert/deinstalliert“. `grace` verhält sich wie `active`. `invalid` verhält sich wie `expired`. „Add-on deaktiviert“ heißt: kein Pro-Code läuft; es greift die Spalte „Ohne Add-on“.

**Immer gilt (alle Zeilen):** Free-Funktionen, Lernhistorie, Grants, Versuche, ausgestellte Zertifikate und deren Verifikation bleiben vollständig (CORE-10). Keine Pro-Daten werden gelöscht. Nach Reaktivierung ist der Stand vor dem Ablauf wieder bearbeitbar.

| Pro-Funktion | Lizenz `expired`: gesperrt | Lizenz `expired`: bleibt (lesen/exportieren) | Ohne Add-on (deaktiviert) | Offene Frage |
|---|---|---|---|---|
| Kursübergreifende Lernpfade (`advanced_paths`) | Pfade anlegen/ändern | Pfadansicht für Lernende und Admin lesend; Regeln werden **weiter ausgewertet** | Pfadsperren entfallen; Kurszugang folgt allein den Grants; Admin-Hinweis | N-01 |
| Drip-Kampagnen, absolute Termine, Gruppen-Drip | Regeln anlegen/ändern | Bestehende Regeln wirken weiter, lesbar und exportierbar | Pro-Regeln werden nicht ausgewertet; es gilt nur der Free-Drip; Admin-Warnung mit Liste betroffener Module vor Deaktivierung | N-01 |
| Erweiterte Bewertung (Retake-Programme, Rubriken, Fragezweige, Szenarios) | Neue Regeln/Fragen dieser Typen; Veröffentlichen von Kursen, die neue Pro-Fragetypen einführen | Laufende Versuche enden nach ihrem Snapshot regulär; Ergebnisse lesbar; Pro-Fragen in veröffentlichten Kursen bleiben spielbar | Pro-Fragetypen nicht renderbar → Frage wird im Lernmodus übersprungen, Prüfungen mit solchen Fragen sind `cc_exam_not_startable` mit Admin-Hinweis; bestehende Versuche lesbar | N-01 |
| Tutorbewertung (Versuche im Zustand `submitted`) | Neue tutorbewertete Activities veröffentlichen | Offene Bewertungen **dürfen abgeschlossen werden** (Vorschlag), Bewertungshistorie lesbar | Versuche bleiben `submitted` und werden im Systemstatus gemeldet | N-02 |
| Gradebook, Nachbewertungs-UI | Manuelle Noten, neue Nachbewertung | Gradebook lesend, CSV-Export des letzten Stands | Nicht sichtbar; Daten bleiben in den Tabellen, Privacy-Export enthält sie | – |
| Zertifikatsdesigner | Vorlagen anlegen/ändern | Ausstellung läuft mit der **zuletzt gespeicherten** Vorlage weiter (Vorschlag); alle ausgestellten Zertifikate und ihre Verifikation unverändert | Neue Ausstellungen mit der Free-Basisvorlage; alte Zertifikate behalten ihre `template_version` und bleiben abrufbar | N-01 |
| Erweiterte Gruppenverwaltung (delegierte Admins, Umbuchen, Massenaktionen, Gruppenberichte) | Delegierte Admins verlieren Schreibrechte; keine Umbuchung/Massenaktion | Gruppenberichte lesend/exportierbar; **Besitzer-Selbstverwaltung (Free) bleibt** | wie `expired`, Berichte nicht sichtbar | – |
| Erinnerungen (Ablauf-, Lernerinnerung) | Versand neuer Erinnerungen, Kampagnen ändern | Opt-out-Links funktionieren weiter (rechtlich nötig); Versandprotokoll lesbar | Kein Versand; Abmeldeseite bleibt als Free-Route erreichbar | – |
| Analytics, Kohorten, CSV-Berichte | Neue Berichtsläufe, neue Kohortendefinitionen | Zuletzt berechnete Berichte lesbar und als CSV exportierbar | Nicht sichtbar; Basisberichte (Free) bleiben | – |
| WooCommerce-/Voucher-Connector (`commerce`, E-53) | Neue Produktzuordnungen, neue Kanalregeln | **Vorschlag:** Grants/Revokes aus **bestehenden** Zuordnungen laufen weiter (Bezahlung, Erstattung, Einlösung), Reconcile läuft weiter; Admin-Warnbanner | Kein Hook-Empfang; Systemstatus warnt; nach Reaktivierung Reconcile ohne Duplikate (CORE-12) | **N-02** |
| Suite-Connectoren (`suite_integrations`) | Neue Verbindungen, Mapping-Änderungen | Bestehende Lese-Integrationen dürfen weiterlaufen (Vorschlag); Diagnose lesbar | Partner sieht Connector als „inaktiv“; Course-Events (Free, E-38) laufen weiter | N-02 |
| Webhooks | Neue Endpunkte, Änderungen | Endpunktliste und Zustellprotokoll lesbar; **Zustellung pausiert**, Outbox puffert bis zur Retention-Grenze | Keine Zustellung | – |
| Zusätzliche Activity-Typen (`custom_activities`) | Neue Activities dieses Typs | Vorhandene bleiben für Lernende nutzbar, Fortschritt zählt | Activity zeigt „Inhalt derzeit nicht verfügbar“, zählt nicht als Pflicht für den Abschluss (Vorschlag) | N-01 |
| Frontend-Builder, Mehrtrainer, erweiterte Rollen | Frontend-Bearbeitung, Zuordnung weiterer Trainer | Zuordnungen bleiben lesbar; Kurseigentümer (Free) unverändert | Zusatztrainer verlieren Objektrechte; Audit-Eintrag | – |
| Pro-Updater/Lizenzclient | Pro-Updates | Installierte Pro-Version läuft weiter im Umfang oben | – | – |

---

## 2. Screen-Inventar

### 2.1 Gemeinsame Zustandsdefinitionen

| Kürzel | Zustand | Darstellung (MUSS) |
|---|---|---|
| **Z-L** | Leer | Erklärender Text, eine primäre nächste Aktion (z. B. „Ersten Kurs anlegen“), kein leeres Tabellengitter |
| **Z-F** | Fehler | Feldnahe Meldung mit stabilem Code (E-06, R0-19), Eingaben bleiben erhalten (INV-D-025), Wiederholen-Aktion; keine technischen Details |
| **Z-R** | Rechte fehlen | 403-Seite ohne Existenzleck (fremde Objekte = 404, R0-08 §6.6 `cc_not_found`) |
| **Z-G** | Gesperrt (Lernende) | Titel sichtbar, Inhalt nicht ausgeliefert; Grund aus `GateDecision.reason` (`no_access`, `trial`, `drip` + Datum, `prerequisite` + Liste, `exam_running`) in Klartext, nie nur Farbe (Kap. 16.3) |
| **Z-P** | Pro-Erweiterung | Nur mit Add-on sichtbar (ER-5). Bei Lizenz `expired`: lesend, Hinweis „Pro-Lizenz abgelaufen – bestehende Daten bleiben lesbar“, Link zur Seite Pro-Lizenz |
| **Z-O** | Offline/Sitzung | Lernbereich: Hinweis „Verbindung unterbrochen – Antworten werden gespeichert, sobald du wieder online bist“ (Prüfungs-Outbox INV-D-078), Nonce-Erneuerung (INV-D-069) |
| **Z-W** | Wartung/Migration | Schreib-API 503 mit Hinweis (INV-D-042); Lesen bleibt |

Capability-Namen nach Kap. 17 (`cc_manage_settings`, `cc_manage_courses`, `cc_publish_courses`, `cc_manage_question_bank`, `cc_grade_assessments`, `cc_manage_enrollments`, `cc_view_course_reports`, `cc_manage_integrations`). Endgültige Namen und Objektregeln: R0-07. Jede Seite prüft die Capability serverseitig (INV-D-032).

### 2.2 Admin-Screens (Menü „Course Captain“)

Zielmenü nach Kap. 43.2; Spalte „LB“ nennt die Unterseite aus INV-D §3.1 (Nr.) bzw. „neu“.

| ID | Screen (Menü › Ansicht) | LB | Edition/Phase | Rolle / Capability | Kernaktionen | Leer / Fehler / Gesperrt | Abweichung zu LB |
|---|---|---|---|---|---|---|---|
| **A-01** | Übersicht (Dashboard) | 1 (INV-D-007, 047, 048, 051) | F1; Widget „offene Bewertungen“ ab F2 (R2-02) bzw. P3 | Manager, Admin; Instructor sieht eigene Kurse | Setup-Checkliste fortsetzen, Kennzahlen (aktive Kurse, Lernende, letzte Abschlüsse), Warnungen (Queue, Import, Cron, Dateischutz), Hinweise wegklicken | Z-L: Checkliste „Kurs anlegen / Inhalte importieren / Demo überspringen“; Z-F: Kennzahl nicht ladbar → Kachel einzeln mit Fehler; Pro-Kacheln Z-P | Ohne Produktzuordnungen, Glossar und Shop-Checkpunkte (K-11); Livegang-Ampel wird Setup-Bereitschaftsprüfung |
| **A-02** | Setup-Assistent (7 Schritte) | neu (Teile aus INV-D-048, 050, 051) | F1 | Admin (`cc_manage_settings`) | Schritte speichern/zurück/überspringen, später fortsetzen, erneut aufrufen ohne Datenverlust | Z-F feldnah je Schritt; Abbruch → Fortschritt gespeichert; Schritt 7 listet Blocker konkret | Keine Lizenzabfrage, keine Woo-Pflicht, keine Plugin-Installation (K-23); Demo-Kurs → N-10 |
| **A-03** | Kurse › Liste | 3 (INV-D-009) | F1 | Manager alle, Instructor eigene | Suchen, Statusfilter, „Kurs erstellen“, Vorschau, Duplizieren (F1 einfach), Bulk-Archivierung mit Bestätigung | Z-L „Noch kein Kurs“ + Erstellen/Importieren; Archivieren mit aktiven Lernenden → Bestätigung mit Anzahl | LB nur lesend; Duplizieren/Erstellen neu |
| **A-04** | Kurse › Kurseditor (Curriculum, Lektions-/Activity-Editor, Seitenleiste Metadaten, Zugang & Freischaltung, Veröffentlichung) | neu; Zugangsteil aus 15 (INV-D-027, 028) | F1; Panel „Kampagnen/Termine“ PK; Produktzuordnung P3 | Instructor eigene, Manager alle; Veröffentlichen `cc_publish_courses` | Module/Lektionen anlegen, sortieren (Drag-and-drop **und** Tastatur), Activity-Typ wählen, Teilumfang und relativen Drip je Modul, Voraussetzungen (N-09), Vorschau als Lernender, Publish-Validator, Autosave, Revisionen | Z-L: leeres Curriculum mit „Erstes Modul“; Z-F: Validator listet Blocker (leerer Kurs, Zyklus, Prüfung ohne ausreichenden Pool, Rechte ungeklärt) und blockiert Veröffentlichung (R1-26); Änderung bei aktiven Lernenden → Vorschau der Wirkung (Kap. 43.3) | LB hat keinen Editor; „Kostenlose Bereiche“ als globale Seite entfällt zugunsten des Kurs-Panels plus Übersicht in A-09 |
| **A-05** | Kurse › Kurs-FAQ, Lerntipps (CPT-Listen) | 11, 12, 13 (INV-D-057, 058) | F1 Mechanik; Starttexte F2 | Instructor/Manager | Einträge anlegen, Kurs zuordnen, Kategorie, Reihenfolge | Z-L mit Hinweis „Starttexte folgen mit Vorlagen (R2)“ | Öffentliche FAQ (9, 10) entfällt (E-16) |
| **A-06** | Kurse › Medien & Dateien | 5 (INV-D-012) | F1 | Instructor/Manager | Verwendung je Medium, Filter „ohne Datei“, geschützt/öffentlich, Untertitel zuordnen | Z-L; Z-F „Datei fehlt“ mit Verwendungsliste | Unterseite statt eigenem Menüpunkt (Kap. 43.2 hat keinen Punkt „Medien“, Konflikt C-06) |
| **A-07** | Fragenbank › Liste, Fragendetail, Frageneditor | 4 (INV-D-010, 011) | F1; Pro-Fragetypen PK; matching/ordering/cloze F2 | `cc_manage_question_bank` | Filtern, anlegen, Typ wählen, Antworten/Korrektheit, Erklärung, Medien, Tags, Vorschau, Revisionshistorie, Sammelbearbeitung | Z-L; Z-F Typvalidierung (R0-08 §3.1); Bearbeiten einer veröffentlichten Revision → Warnung „neue Revision, laufende Versuche unverändert“ | LB nur lesend |
| **A-08** | Quizzes & Prüfungen › Regeln, Blueprint, feste Bögen | 6 (INV-D-013) | F1; Retake-Programme PK | Instructor/Manager | Blueprint (Fragen je Modul), Zeitlimit, Bestehensgrenze/Teile, Versuche, Feedback-Sichtbarkeit, Status `draft/published/retired`, Vorschau der Auswertung | Z-F „Pool zu klein (n von m)“ → `reduced`-Hinweis statt stiller Verkürzung; Sammelfreigabe mit Pflichtbegründung | Kein „verifiziert“ (E-23) |
| **A-09** | Lernende › Zugänge (Liste, Detail, Verlauf), manuelle Freischaltung, Zugangsregeln-Übersicht, Fortschritt/Ergebnisse je Lernendem | 14, 15 (INV-D-024…028, 034) | F1; Woo-/Gutschein-Spalten P3 | `cc_manage_enrollments` (Instructor: zugeordnete) | Suchen (Name/E-Mail/ID), filtern (Kurs/Status/Quelle), freischalten mit Scope, Laufzeit, Pflichtbegründung; aussetzen/widerrufen/verlängern; Verlauf; Fortschritt zurücksetzen mit Audit (E-70) | Z-L; Z-F Eingaben bleiben (INV-D-025); Widerruf bei Mehrfachquelle → Vorschau „bleibt über Quelle X zugänglich“ (CORE-04) | „release“/Woo-Resync wird generisches `source.resync` (K-03); SKU-Suche entfällt |
| **A-10** | Gruppen › Liste, Detail, Gruppe anlegen | 16 (INV-D-030) | F1; delegierte Admins/Umbuchen/Massenaktionen/Berichte PK | `cc_manage_enrollments` | Gruppe manuell anlegen (Kurs, Plätze, Laufzeit, Besitzer), Plätze ändern, Mitglied entfernen mit Begründung, Einladungslink erneuern, Status aktivieren/aussetzen/beenden, Verlauf | Z-L „Noch keine Gruppe“ + Anlegen; Platzreduktion unter Belegung → Vorschau, wer den Platz verliert (E-28) | Manuelle Anlage neu (LB nur aus Bestellung, K-22) |
| **A-11** | Zertifikate › Ausgestellte Nachweise, Basisvorlage, (Designer) | neu | F1; Designer PK | Manager; Widerruf/Korrektur eigene Capability (R0-07) | Ausstellungsregel je Kurs (Standard aus), Liste, Detail, Korrektur (neue Revision), Widerruf mit Grund, Verifikationslink kopieren | Z-L „Noch keine Nachweise – Ausstellung im Kurs aktivieren“; Widerruf → Bestätigung; Designer Z-P | Kein LB-Bestand |
| **A-12** | Berichte | neu (Teile aus INV-D-007, 076) | F1 Basic; Kohorten/CSV PK | `cc_view_course_reports` | Kurs wählen: Eingeschriebene, aktiv, Abschlussquote, Prüfungsergebnisse, Drilldown auf Lernende | Z-L „Noch keine Lernaktivität“; Pro-Kohorten Z-P | Umfang Basic → N-06 |
| **A-13** | Inhalte importieren › Pakete (Liste, Batch-Detail), Rechte & Freigabe, Kurs-Export | 2, 7 (INV-D-014, 015, 018…023) | F1 | `cc_import_content` (R0-07) | Upload/Ordner/https-Link, Dry-run-Vorschau, Freigeben mit Notiz, Verwerfen, Rollback, Rechte-Sammelfreigabe mit Begründung, Export (Fremdlizenz nur Opt-in, E-66) | Z-L mit Formatbeschreibung; Z-F Fehler je Schritt (Netz, Timeout, Größe) mit Wiederaufnahme; „Mischstand/hängend“-Warnung | Warnungskatalog neutral; Rechte-Seite wird Tab |
| **A-14** | Integrationen | neu (Woo-Teile aus INV-D-038/039 ausgelagert) | F1 Erkennung/Status; Connectoren P3; Webhooks P3 | `cc_manage_integrations` | Erkannte Partner, Version/Vertrag, Status (fehlt/aktiv/inkompatibel), Fehlerliste mit Retry (R1-38) | Z-L „Keine Partner erkannt – Course Captain funktioniert vollständig ohne“; Connector-Karten nur mit Add-on (ER-5) | Keine Pflichtintegration |
| **A-15** | Einstellungen (Tabs Allgemein, Kursdarstellung, Lernen, Prüfungen, Einschreibung, Rollen & Rechte, Zertifikate, E-Mails, Datenschutz & Sicherheit, Erweitert) | 20 (INV-D-036, 037) | F1; Pro-Felder PK (R3-15) | `cc_manage_settings` | Je Tab speichern/zurücksetzen, Warnung bei ungespeicherten Änderungen, Risiko-Bestätigung, Testmail | Z-F feldnah; „Reset“ nur aktueller Tab, nie Lernstände/Grants | Eine Seite mit Ankern → Tabs; Zahlungskonflikte/Preishinweis entfallen (K-11) |
| **A-16** | Systemstatus › Status, Jobs/Queue, Dateischutz, Protokoll (Audit), Diagnoseexport, Einstellungen sichern | 19, 22 (INV-D-033, 038, 040…043) | F1 | Status: Manager lesend; Protokoll/Diagnose: Admin | Schema/Cron/Jobs prüfen, sichere Reparatur (Retry/Reconcile), Protokoll filtern, redigierten Diagnoseexport, Nicht-Secret-Settings exportieren/importieren | Z-W; Z-F je Prüfpunkt mit Abhilfe | Protokoll als Tab statt eigenem Menüpunkt (Konflikt C-06); Shop-Teil nur via Adapter |
| **A-17** | Pro-Lizenz | neu | P3 (nur mit Add-on) | Admin | Schlüssel aktivieren/deaktivieren, Status, Site-Limit, Ablaufdatum, Update-Kanal | Z-F: Server nicht erreichbar (Grace bis Datum), Site-Limit erreicht, Staging-Hinweis, Core-Version zu alt | – |
| **A-18** | Pro-Screens: Gradebook, Tutor-Bewertungen (Warteschlange), Lernpfade | neu | P3 bzw. PK | Tutor/Instructor/Manager | Bewerten, Noten, Pfade pflegen | Z-P | – |
| — | **Entfallende LB-Screens:** Prüfungsanmeldung (8), öffentliche FAQ + Kategorien (9, 10), Mitgliederrabatte (17), Preise (18), Mobile App (21), Seekarten (INV-D-016) | 8, 9, 10, 17, 18, 21 | X | – | – | – | E-16, E-25, BS-Z, BS-28, Kap. 26.2 |

**Vollständigkeit:** Alle 22 LB-Unterseiten sind zugeordnet (1→A-01; 2, 7→A-13; 3→A-03/A-04; 4→A-07; 5→A-06; 6→A-08; 11–13→A-05; 14, 15→A-09/A-04; 16→A-10; 19, 22→A-16; 20→A-15; 8, 9, 10, 17, 18, 21→X).

### 2.3 Lernenden-Screens (Lernbereich `/lernen/`)

Spalte „LB“ nennt die Route aus INV-D §5.3 (Basis `/bootsscheine/`). Rolle: Gast = nicht angemeldet, L = Lernender, GV = Gruppenverwalter/-besitzer.

| ID | Screen | LB | Edition/Phase | Rolle | Kernaktionen | Leer / Fehler / Gesperrt | Abweichung zu LB |
|---|---|---|---|---|---|---|---|
| **L-01** | Kurskatalog (Block/Seite) | neu (Kurskatalog-Block BS-17) | F1 | Gast, L | Kurse filtern/suchen, Kursdetail öffnen | Z-L „Noch keine Kurse veröffentlicht“; nur öffentliche Kurse indexierbar (Kap. 43.3) | LB hat nur „Mein Lernen“ mit Empfehlungen |
| **L-02** | Kursdetail / Einschreibung | neu; Sperrhinweis INV-B-064 | F1; Kaufangebote P3 (K-05) | Gast, L | Beschreibung, Lernziele, Curriculum-Titel, „Einschreiben“ (wenn Selbstanmeldung an), „Kostenlos testen“ (Teilumfang), sonst Kontaktweg | Selbstanmeldung aus → „Zugang über die Kursverwaltung“; Gast → L-03 mit Rückkehr | Ohne Shop nur Login/Einschreibung/Kontakt |
| **L-03** | Anmeldung/Registrierung (Karte in der App-Hülle) | INV-D-061, 067 | F1 | Gast | WP-Login, Registrierung (wenn WP erlaubt), Rückkehr zur Ausgangsseite | Registrierung in WP aus → nur Login + Hinweis; ungültiges Rückkehrziel → Mein Lernen | Woo-Login nur per Adapter (K-19) |
| **L-04** | Mein Lernen | `/` (INV-D-071) | F1 | L | Kurskarten mit Fortschritt, „Fortsetzen“, Zugangsstatus (aktiv, Teilumfang, ausgesetzt, abgelaufen, Ablaufdatum) | Z-L „Noch keine Kurse – Katalog ansehen“; abgelaufen → Karte bleibt mit Ergebnissen lesbar | „Verlängern“-Link nur mit Angebots-Provider; keine Erinnerungsmail in Free (E-24) |
| **L-05** | Kurs-Hub | `/<kurs>/` (INV-D-072) | F1; Ticket-/Praxiskacheln P3 | L | Fortschritt, nächster Schritt, Kapitelbaum mit Status „gesperrt/fällig/abgeschlossen“, Einstiege Smarttrainer, Fehlertraining, Merkliste, Karteikarten, Prüfungen, Materialien, Kurs-FAQ/Tipps | Z-G je Modul (`drip` mit Datum, `trial`, `prerequisite`); kein Zugang → L-02 | Kachel-Registry statt fester Kacheln (K-17) |
| **L-06** | Lektion (Text/Blöcke, Video/Audio, Datei) | `/<kurs>/video/`, `/<kurs>/video/<id>/` (INV-D-080), `/<kurs>/downloads/` (INV-D-081), `/datei/<id>/` | F1 | L | Inhalt lesen, Video mit Position/Untertiteln, externes Video erst nach Klick/Einwilligung, Inline-Fragen, „Als erledigt markieren“, Datei laden, weiter/zurück | Z-G; externe Medien ohne Consent → Platzhalter mit „Laden“; Datei 403/404/416 (Range) mit Klartext; Z-O | Allgemeine Lektion neu (LB nur Video-Lektion); Downloads als Activity `file` statt eigener Seite; keine Garantie-Rückfrage (E-32) |
| **L-07** | Smarttrainer / Themenübersicht | `/<kurs>/smarttrainer/` (INV-D-073) | F1 | L | Modul/Thema wählen, Fortschrittsringe, „Smart lernen“ starten | Z-L „Keine Fragen in diesem Kurs“; gesperrte Module ausgegraut mit Grund | – |
| **L-08** | Lernmodus (Fragekarte) | `/<kurs>/lernen/?modus…` (INV-D-074) | F1 | L | Modi smart/neu/falsch/fällig/Favoriten/Sammlung/Prüfungsfehler, Antwort prüfen, Erklärung (nach Feedback-Einstellung), Favorit, offene Frage aufdecken + Selbsteinschätzung | Z-L „Alles erledigt – nächste Wiederholung am …“; `exam_running` → „Diese Fragen sind während deiner laufenden Prüfung gesperrt“; `cc_question_token_expired`/`cc_answer_rate_limited` → automatisch neu laden bzw. kurz warten | – |
| **L-09** | Merkliste & Sammlungen | `/<kurs>/merkliste/` (INV-D-075) | F1 | L | Favoriten, Sammlungen anlegen/umbenennen/löschen, einsortieren, daraus lernen | Z-L „Noch nichts gemerkt – Stern auf einer Frage setzen“; Löschen mit Bestätigung | – |
| **L-10** | Fehlertraining | Teil von `/lernen/?modus=falsch` und Fehlerliste in `/merkliste/` | F1 | L | Falsch beantwortete und Prüfungsfehler üben, Liste leeren | Z-L „Keine offenen Fehler“ | **Eigener Einstieg im Kurs-Hub** statt nur Modus; Route bleibt L-08 mit `modus=wrong` |
| **L-11** | Karteikarten | `/<kurs>/praxis/` (abstract; Inhalt exclude) | F1 | L | Set wählen, Karte umdrehen, open/practice/known bzw. Serie, Fortschritt | Z-L; Z-G | Neutraler Typ `flashcard` (E-21), Praxis-Fachinhalt entfällt |
| **L-12** | Prüfungs-Hub | `/<kurs>/pruefung/` (INV-D-076) | F1 | L | Simulation und feste Bögen, Status, Versuche übrig, Ergebnisliste, Verlaufsdiagramm, laufenden Versuch fortsetzen | Z-L „Noch keine Prüfung“; `trial` → nur erste *n* Bögen, keine Zufallsprüfung; `cc_exam_attempts_exhausted` mit Datum bei Cooldown; `reduced` → Kennzeichnung „verkürzte Übung, nicht nachweisfähig“ | Kein „verifiziert“ (E-23) |
| **L-13** | Prüfungslauf | `/<kurs>/pruefung/<id>/` examRun (INV-D-077, 078) | F1 | L | Timer (Serverzeit), Navigation, Markieren, „nur Markierte“, Abgabebestätigung mit offenen/markierten, Abbrechen, Auto-Abgabe | `cc_exam_expired` → Ergebnis; `cc_exam_finished` (andere Registerkarte) → Ergebnis; `cc_exam_conflict` → neu laden; Z-O mit Outbox; Zugangsverlust → `cc_access_denied` mit Klartext | – |
| **L-14** | Ergebnis | examResult (INV-D-079) | F1 | L | Punkte, bestanden, Teilgrenzen, Themenanalyse, Review (nach Feedback-Einstellung), „Fehler üben“, Nachweis ansehen | `broken` → „Auswertung verzögert, wird geprüft“ (R0-08 §6.1); Feedback „nie“ → nur Punkte | – |
| **L-15** | Zertifikat / Nachweis (eigene Nachweise) | neu | F1 (Designer-Layout PK) | L | Nachweis ansehen, drucken/herunterladen (Format N-04), Verifikationslink teilen, Status (gültig/korrigiert/widerrufen/abgelaufen) | Z-L „Noch keine Nachweise“; widerrufen → Status sichtbar, nicht ausgeblendet (CORE-11) | Kein LB-Bestand |
| **L-16** | Öffentliche Verifikation `/lernen/nachweis/<token>` | neu | F1 | Gast | Status, Aussteller, Kurs, Datum, Name nach Policy (N-05) | Unbekannter Token → „Nachweis nicht gefunden“ (kein Verzeichnis, keine Hinweise auf ähnliche Tokens); widerrufen/korrigiert → Status mit Datum, bei Korrektur Verweis auf die gültige Revision; Rate-Limit 429 | Kein LB-Bestand |
| **L-17** | Gruppeneinladung (Gast-Vorschau, Beitritt) | `/gruppe/<token>` (INV-D-065) | F1 | Gast, L | Vorschau (Gruppe, Kurs, freie Plätze), anmelden/registrieren, beitreten | `cc_invite_expired`, `cc_group_full`, `cc_group_not_active`, `cc_invite_address_mismatch` (E-28) – Codes Vorschlag R0-19; bereits Mitglied → Hinweis + Kurs öffnen | – |
| **L-18** | Meine Gruppen (Gruppenverwalter) | `/gruppen` (INV-D-082) | F1; Berichte/Delegation PK | GV | Mitglieder und Platzbelegung, per E-Mail einladen, Link kopieren/erneuern, Platz freigeben, Gruppe umbenennen | Z-L „Noch niemand eingeladen“; Einladungsbudget erschöpft → Zeit bis Freigabe; Gruppe ausgesetzt → nur lesend | – |
| **L-19** | Kurs-FAQ & Lerntipps | `/tipps` (INV-D-082 „R2“), Kunden-FAQ im Hub | F1 (E-16) | L | Lesen, filtern | Z-L (bis R2-03 meist leer) | Phase R1 statt LB-Inventur „R2“ (E-16) |
| **L-20** | Konto & Datenschutz | Woo-„Mein Konto“ „Meine Daten“ (INV-C-160, K-07) | F1 | L | Eigene Daten exportieren (Privacy-API), Löschung beantragen, E-Mail-Einstellungen, Consent für externe Medien widerrufen, eigene Ergebnisübersicht exportieren | Z-F Rate-Limit Export/Antrag (INV-D §6.4); Antrag läuft → Status | Eigener Bereich statt Woo-Endpunkt |
| **L-21** | Erinnerungen abbestellen | `/erinnerungen` (Server-Seite) | PK (nur mit Erinnerungen); Seite bleibt erreichbar nach Lizenzende | Gast (signierter Link) | Ein-Klick-Abmeldung | Ungültiger/abgelaufener Link → Hinweis auf L-20 | – |
| — | **Entfallende LB-Lernerscreens:** `/<kurs>/knoten/`, `/pruefung-anmelden/`, `/navigation/` (2 Views), `/funk/…` inkl. Gerätesimulation | INV-D-083, 087 | X | – | – | – | Kap. 26.2 |

**Vollständigkeit:** Alle 22 LB-Views aus INV-D §5.3 sind zugeordnet (Overview→L-04; GroupJoin→L-17; Groups→L-18; Tips→L-19; Course/CourseHub→L-05; Learn→L-08/L-10; Topics→L-07; Saved→L-09/L-10; ExamHub→L-12; examRun→L-13; examResult→L-14; Downloads, Videos, VideoLesson, `/datei/`→L-06; Praxis→L-11; `/erinnerungen`→L-21; Knots, ExamGuide, Navigation ×2, Radio/DeviceSim→X). Neu ohne LB: L-01, L-02, L-15, L-16, L-20 (L-03 ist Teil der LB-Hülle).

**Pro-Lernenden-Erweiterungen (P3/PK, nur mit Add-on):** Lernpfad-Übersicht in L-04, Tutor-Feedback in L-14, Designer-Layout in L-15, Kaufangebote in L-02/L-05, Woo-„Mein Konto“-Link.

---

## 3. UX-Flows

Notation: `[Screen]` → Aktion → Ergebnis. **F#** = Fehlerpfad. Alle Server-Entscheidungen erfolgen serverseitig; der Client wertet nur stabile Codes aus (INV-D-156).

### 3.1 Flow 1 – Autor erstellt und veröffentlicht einen Kurs (F1)

```
[A-03 Kurse] → „Kurs erstellen“
  → [A-04 Kurseditor] Titel, Beschreibung, Lernziele (Autosave, Revision 0 = Entwurf)
  → Modul anlegen → Lektion anlegen → Activity-Typ wählen (lesson/video/file/quiz/flashcard)
      ├─ Fragen nötig → [A-07 Fragenbank] anlegen oder aus Bank zuordnen → zurück
      └─ Prüfung nötig → [A-08 Prüfungen] Blueprint/Bogen, Grenze, Zeit, Versuche
  → Seitenleiste „Zugang“: Einschreibemodus, Teilumfang, relativer Drip, Voraussetzungen
  → „Vorschau als Lernender“ (L-05/L-06 im Vorschaumodus, keine Fortschrittsdaten)
  → „Veröffentlichen“ → Publish-Validator
      ├─ ok → Status „veröffentlicht“, Kursrevision fixiert Struktur und Regeln (E-50) → Katalog (L-01), falls öffentlich
      └─ F1.1
```

| Fehlerpfad | Auslöser | Verhalten |
|---|---|---|
| F1.1 | Validator-Blocker (leerer Kurs/Modul, Prüfung mit zu kleinem Pool, Voraussetzungszyklus, Verweis auf gelöschte Activity, Rechte ungeklärt bei importiertem Inhalt) | Veröffentlichung abgelehnt, Liste mit Sprunglinks zum Objekt; Warnungen (z. B. „verkürzte Prüfung“) erlauben Veröffentlichung nach Bestätigung |
| F1.2 | Fehlende Capability `cc_publish_courses` (Instructor „nach Freigabe“, Kap. 17) | Aktion „Zur Freigabe einreichen“ statt „Veröffentlichen“; Manager erhält Hinweis in A-01 |
| F1.3 | Änderung eines veröffentlichten Kurses mit aktiven Lernenden | Neue Revision; Vorschau „betrifft n Lernende, laufende Versuche behalten Snapshot“ (Kap. 43.3, R1-27) |
| F1.4 | Autosave scheitert (Sitzung/Netz) | Hinweis, lokaler Entwurf bleibt, Wiederholen; kein stiller Verlust |
| F1.5 | Gleichzeitige Bearbeitung | Revisionskonflikt (`VERSION_CONFLICT`-Muster), Vergleich anbieten |
| F1.6 | Alternativer Einstieg Import | [A-13] Upload → Dry-run → Freigabe → Kurs als Entwurf in A-03 → weiter wie oben |

### 3.2 Flow 2 – Lernender startet, lernt, besteht, erhält Nachweis (F1)

```
[L-01 Katalog] → [L-02 Kursdetail]
  ├─ Gast → [L-03 Anmeldung] → Rückkehr zu L-02
  └─ „Einschreiben“ (Selbstanmeldung an) → Grant source=free_enrollment, scope view+exam
       oder manuelle Freischaltung durch Admin (A-09) → E-Mail „Zugang freigeschaltet“
  → [L-04 Mein Lernen] Kurskarte → „Starten“
  → [L-05 Kurs-Hub] nächster Schritt
  → [L-06 Lektion] lesen/ansehen → „erledigt“ (Completion)
  → [L-07 Smarttrainer] → [L-08 Lernmodus] Fragen beantworten (Mastery)
       → [L-10 Fehlertraining] / [L-11 Karteikarten] nach Bedarf
  → [L-12 Prüfungs-Hub] → „Prüfung starten“ (Snapshot)
  → [L-13 Prüfungslauf] beantworten, markieren → „Abgeben“ → Bestätigung
  → [L-14 Ergebnis] bestanden, nachweisfähig
       → Ausstellungsregel des Kurses erfüllt? ja → Zertifikat issued → E-Mail
  → [L-15 Nachweis] ansehen/drucken, Verifikationslink → [L-16 Verifikation]
```

| Fehlerpfad | Auslöser | Verhalten |
|---|---|---|
| F2.1 | Selbstanmeldung aus | L-02 zeigt Kontaktweg/Hinweis; kein Button |
| F2.2 | Teilumfang (`trial`) | Nur erste *n* Elemente; gesperrte zeigen „Im vollen Zugang enthalten“ (ohne Kauflink in Free) |
| F2.3 | Drip/Voraussetzung | Z-G mit Datum bzw. Liste der Voraussetzungen; Prüfung gesperrt, solange ein Blueprint-Modul gesperrt ist (R0-08 §1.4) |
| F2.4 | Zugang läuft während der Lektion/Prüfung ab oder wird widerrufen | Nächste Aktion `cc_access_denied`; laufender Versuch: Antworten bleiben gespeichert, Ergebnis lesbar (Policy R0-09) |
| F2.5 | Netz weg im Prüfungslauf | Outbox puffert, Hinweis Z-O; Antworten mit `answered_at ≤ deadline + Grace` zählen |
| F2.6 | Zweite Registerkarte gibt ab | Erste Karte erhält `cc_exam_finished` → Ergebnis |
| F2.7 | Zeit abgelaufen | Auto-Abgabe, `end_reason = expired`, Ergebnis |
| F2.8 | Nicht bestanden | L-14 mit „Fehler üben“ → L-10; neuer Versuch, sofern Limit/Cooldown es erlaubt (`cc_exam_attempts_exhausted`) |
| F2.9 | Verkürzte Prüfung (`reduced`) bestanden | Kein Nachweis; L-14 erklärt „verkürzte Übung, nicht nachweisfähig“ |
| F2.10 | Auswertung `broken` | L-14 „Auswertung verzögert“; Admin-Reparatur in A-16; Lernender wird per Mail informiert, sobald ausgewertet |
| F2.11 | Ausstellung deaktiviert | Kein Zertifikat; Abschluss und Ergebnis bleiben sichtbar (Kap. 4.5 Free) |

### 3.3 Flow 3 – Gruppenverwalter lädt ein (F1, E-17, E-28)

```
[A-10 Gruppen] Admin: „Gruppe anlegen“ (Kurs, Plätze n, Laufzeit, Besitzer)
  → Besitzer erhält E-Mail „Du verwaltest Gruppe X“
  → [L-18 Meine Gruppen] Besitzer: „Per E-Mail einladen“ (Adresse) oder „Link kopieren“
       (Option „nur eingeladene Adresse“ je Gruppe)
  → Eingeladene Person öffnet Link → [L-17 Einladung] Vorschau (Gruppe, Kurs, freie Plätze)
  → nicht angemeldet → [L-03] anmelden/registrieren → zurück zu L-17
  → „Beitreten“ → Claim: Platz atomar belegen → Grant source=group (Seat-Referenz)
  → [L-04 Mein Lernen] Kurs erscheint
  → [L-18] Besitzer sieht Mitglied „aktiv“, Belegung n−1 frei
```

| Fehlerpfad | Auslöser | Verhalten |
|---|---|---|
| F3.1 | Keine Plätze frei (paralleler Claim) | `cc_group_full`; kein Doppelbelegen (atomar, R1-40); Besitzer erhält Hinweis |
| F3.2 | Einladung abgelaufen (auch vor Cron, INV-D-030/LB) | `cc_invite_expired`; „Neue Einladung beim Gruppenverwalter anfordern“ |
| F3.3 | Link erneuert | Alter Link ungültig, gleiche neutrale Meldung wie F3.2 (keine Existenzauskunft) |
| F3.4 | Adressbindung aktiv, angemeldet mit anderer Adresse | `cc_invite_address_mismatch`; keine Kontoverknüpfung per E-Mail (Kap. 43.3) |
| F3.5 | Gruppe ausgesetzt/beendet | `cc_group_not_active`; Besitzer-Aktionen nur lesend |
| F3.6 | Person ist schon Mitglied bzw. hat den Kurs über andere Quelle | Kein zweiter Platz bei Mitgliedschaft; bei anderer Quelle Platzverbrauch nach LB-Regel (E-28), Hinweis vor Beitritt |
| F3.7 | Einladungsbudget/Rate-Limit | Zeit bis zur nächsten Einladung (INV-D §6.4) |
| F3.8 | Admin reduziert Plätze unter Belegung | Vorschau in A-10, wer nach LB-Reihenfolge den Platz verliert (E-28, E-55); Fortschritt der Person bleibt erhalten |
| F3.9 | Mitglied entfernt | Grant aus Gruppenquelle widerrufen, andere Quellen bleiben (CORE-04), Lernhistorie bleibt |

### 3.4 Flow 4 – Admin: Setup-Assistent (F1, Kap. 16.1, R1-32)

```
Plugin aktiviert → Admin-Hinweis „Einrichtung starten“ → [A-02 Setup]
  1 Zweck & Sprache (Sprache aus WP, nur Anzeige)
  2 Permalink /lernen/ + Katalogseite wählen/erstellen
  3 Einschreibemodus: manuell (Standard) / kostenlose Selbstanmeldung / öffentlich
  4 Lern- und Prüfungsdefaults (Feedback nach Abgabe, Versuche unbegrenzt, Grenze 70 % Vorschlag)
  5 E-Mail (Absender aus WP, Testmail) & Datenschutz (Retention-Defaults, externe Medien Klick-zum-Laden)
  6 Demo-Kurs oder überspringen (→ N-10)
  7 Bereitschaftsprüfung: Dateischutz, Cron, Permalinks, Mail, Rollen → Ampel mit Abhilfe
  → „Fertig“ → [A-01 Übersicht] Checkliste mit Rest-Punkten
```

| Fehlerpfad | Auslöser | Verhalten |
|---|---|---|
| F4.1 | Slug kollidiert mit Seite/Beitrag | Feldfehler mit Alternativvorschlag; Rewrite erst nach bestätigtem Speichern (Kap. 43.3) |
| F4.2 | Katalogseite existiert nicht/nicht veröffentlicht | Anbieten „Seite erstellen“, keine automatische Überschreibung |
| F4.3 | Modus „öffentlich/kostenlos“ gewählt | Risiko-Bestätigung „Alle angemeldeten Personen erhalten Zugang“ (Gegenstück zur LB-Warnung INV-D-052) |
| F4.4 | Testmail scheitert | Fehlertext des Versandwegs, Hinweis SMTP-Plugin; Setup kann fortgesetzt werden (Warnung, kein Blocker) |
| F4.5 | Dateischutz `exposed` (nginx) | Blocker-Ampel mit Regelvorschlag (INV-D-041); geschützte Medien bleiben bis Behebung nicht ausgeliefert |
| F4.6 | Abbruch in Schritt n | Fortschritt separat gespeichert; Wiederaufnahme bei Schritt n; erneuter Aufruf ändert nichts ohne Speichern |
| F4.7 | Registrierung in WP aus, aber Selbstanmeldung gewählt | Warnung „Nur bestehende Konten können sich einschreiben“ |
| F4.8 | Netzwerkaktivierung (Multisite) | Aktivierung blockiert mit Erklärung (E-49), Setup nicht erreichbar |

### 3.5 Flow 5 – Pro-Upgrade und Pro-Ablauf (P3, R3-08, R3-15, R3-18)

```
Upgrade:
[A-14 Integrationen] oder Captain-Pro → Pro-Add-on installieren/aktivieren (separates ZIP)
  → Core-Versionscheck ok → Menüpunkt [A-17 Pro-Lizenz] erscheint
  → Schlüssel eingeben → License Captain prüft (nur Lizenzdaten, keine Kurs-/Lerndaten)
  → signierter Snapshot (Ed25519) → Entitlements aktiv
  → Pro-Erweiterungen erscheinen per Registry: Tabs/Panels in A-04, A-10, A-11, A-12, A-15; Screens A-18; Connector-Karten in A-14
  → Free-Daten unverändert, keine Migration von Free-Daten nötig

Ablauf:
active → (Server nicht erreichbar) grace bis cache_expires_at (≤ 14 Tage), Banner in A-17 mit Datum
       → expired: Banner in allen Pro-Bereichen, Pro-Schreibaktionen gesperrt (Tabelle 1.5)
       → Verlängern → active, alle Pro-Daten wieder bearbeitbar
Add-on deaktivieren:
       → Vorab-Bericht „Wirkung“ (betroffene Drip-Kampagnen, Pfade, Activities, offene Tutorbewertungen, Connector-Zuordnungen)
       → Bestätigen → Pro-UI verschwindet, Free-Fallback nach Tabelle 1.5
```

| Fehlerpfad | Auslöser | Verhalten |
|---|---|---|
| F5.1 | Core-Version zu alt/zu neu für das Add-on | Add-on lädt keine Funktionen, Hinweis mit nötiger Version; Free läuft |
| F5.2 | Schlüssel ungültig/Site-Limit erreicht | Klartext in A-17 mit Plan und Anzahl Aktivierungen; Staging nicht als Live-Site gezählt (Kap. 6) |
| F5.3 | Lizenzserver nicht erreichbar bei Aktivierung | Keine Aktivierung ohne gültigen Snapshot; Wiederholen |
| F5.4 | Snapshot-Signatur ungültig/manipuliert | Zustand `invalid` = wie `expired`, Diagnoseeintrag |
| F5.5 | Ablauf mitten in einer Pro-Schreibaktion | Server lehnt Speichern ab (`cc_pro_license_required`, Vorschlag R0-19), Eingaben bleiben im Formular, Hinweis Z-P |
| F5.6 | Ablauf bei Commerce-Connector | Verhalten nach N-02; Warnbanner „Neue Zuordnungen gesperrt, bestehende Käufe werden weiter verarbeitet“ (Vorschlag) |
| F5.7 | Add-on deinstalliert | Pro-Tabellen bleiben erhalten, solange nicht ausdrücklich gelöscht (Kap. 43.3 „Daten behalten“); Neuinstallation findet sie wieder |
| F5.8 | Mehrere Captain-Add-ons | Isolierte Clientbibliothek je Add-on (R3-18); Fehler eines Add-ons beeinflusst Course Pro nicht |

---

## 4. Konflikte und Widersprüche Konzept ↔ Matrix

Rangfolge nach Kap. 46.1: jüngere explizite Entscheidungen (Kap. 29, 32, 35, 43, 46) vor älteren Tabellen. „Auflösung“ ist ein Vorschlag; redaktionelle Konzeptkorrekturen erst nach Freigabe.

| Nr. | Widerspruch | Fundstellen | Auflösung (Vorschlag) | Register |
|---|---|---|---|---|
| **C-01** | Zertifikate/Verifikation: Pro vs. Free-Basis | Kap. 4.5 und Kap. 5 Zeile „Nachweise“ (PDF-Zertifikate, Vorlagen, öffentliche Echtheitsprüfung = Pro) gegen Kap. 43.1, 43.3, 46.1 (Ausstellung, Widerruf, Verifikation Free; Designer Pro) | Kap. 43/46 gilt. Kap. 4.5 und 5 redaktionell auf „Basisnachweis + Verifikation Free, Designer/Branding Pro“ korrigieren; Format → N-04 | N-04 |
| **C-02** | Gruppen: Pro vs. Free-Basis | Kap. 5 Zeile „Teilnehmer“ (Teams/Kohorten, Gruppenplätze, Einladungen = Pro) gegen Kap. 7, 43.1, 46.1, E-17 | Kap. 43/46 gilt; Kap. 5 korrigieren | E-17 |
| **C-03** | Drip Pro vs. Free | Kap. 5 Zeile „Kurse“, Kap. 6 `course.drip_content` gegen Kap. 43.3, E-14 | E-14; Entitlement nur für Kampagnen (N-03) | E-14, N-03 |
| **C-04** | Entitlement-Schlüssel greifen über Free-Funktionen | Kap. 6 `course.certificates`, `course.groups`, `course.drip_content` benennen ganze Bereiche, deren Basis Free ist; zugleich fehlen Schlüssel für Erinnerungen, Gradebook, Frontend-Builder, Mehrtrainer, erweiterte Rollen | Schlüssel auf die Erweiterung zuschneiden bzw. ergänzen (ER-2) | N-03, E-42 |
| **C-05** | „Massenimport“ als Pro | Kap. 5 Zeile „Inhalt“ (Massenimport Pro) gegen Kap. 29.1 und R0-04 Z-2 (LB-Batch-/Resume-Import großer Pakete ist Parität) | Paritätsumfang F1; Pro nur Workflows jenseits LB (geplante Veröffentlichung, mehrstufige Freigabe) | – |
| **C-06** | Menübaum Kap. 43.2 unvollständig | Kap. 43.2/10 nennen weder Protokoll (E-19 Free), Medien, Rechte & Freigabe, Kurs-FAQ/Tipps (E-16) noch Setup-Assistent | Platzierung nach 2.2 (A-05, A-06, A-13-Tab, A-16-Tab, A-02 ohne Menüpunkt); Abnahme in R0-30 | R0-30 |
| **C-07** | Öffentliche Website-FAQ | R0-04 BS-24/W-02 ordnet INV-A-123…132 R1 zu und empfiehlt, die öffentliche FAQ mitzunehmen; E-16 (Empfehlung) sagt „bleibt Theme/Drittplugin“; D-056 = BS-Z | Mit E-16 als Annahme: INV-A-123…132 und A-131 (JSON-LD) als **dokumentierte Streichung** nach Kap. 29.2 Nr. 5 in R0-04 von BS-24 nach BS-Z verschieben; sonst E-16 ergänzen | E-16, R0-04 ED-2 |
| **C-08** | Ablauf-Mitteilung in R1-21 | TODO R1-21 DoD nennt „Ablauf“; R0-04 W-05 legt aus „Mitteilung nach Ablauf (Free)“; E-24 sagt „Free nur Status und Ablaufanzeige, keine Erinnerungsmails“ | E-24 wörtlich: in Free **keine** Ablaufmail, nur Anzeige in L-04; R1-21-DoD und R0-04 W-05 anpassen. Wer eine transaktionale Mail **nach** Ablauf will, muss E-24 präzisieren | E-24 |
| **C-09** | Commerce Pro, aber Lizenzablauf sperrt „neue Pro-Schreibaktionen“ | E-53 (Connectoren Pro) + Kap. 6 Zeile „Lizenzablauf“: Wörtlich würden nach Ablauf bezahlte Bestellungen keinen Zugang mehr erzeugen und Erstattungen nicht widerrufen | Käufer-/Vertragsverarbeitung gilt nicht als „neue Pro-Schreibaktion“ | **N-02** |
| **C-10** | Kursbeziehung „enthält“ R2 ohne R2-Task | E-25 (R2) gegen E-54 (keine R2-Zuordnung ohne R2-Task) und TODO R2-01…R2-07 (keine passende Task) | Entweder R2-Task anlegen (R0-24) oder Paritätsteil R1, Rest R4-Pool | E-25, E-54 |
| **C-11** | Rollen ohne Edition | Kap. 17 schlägt Tutor/Assessor vor; Tutorbewertung ist Pro/R3 (Kap. 27.2, 29.1); Mehrtrainer Pro, aber Instructor-Rolle mit „eigenen Kursen“ Free | Instructor als Einzel-Eigentümer F1, Mehrtrainer P3; Tutor → N-07 | N-07 |
| **C-12** | Setup Schritt „Demo-Kurs“ vs. Demos in R2 | Kap. 16.1 Schritt 6 (Free, R1-32) gegen R2-03 „Kursvorlagen und neutrale Demos“ und Kap. 29.2 Nr. 1 | → N-10 | N-10 |
| **C-13** | Standard-Einschreibemodus weicht von LB ab | LB `default_access = logged_in` (alle Angemeldeten lernen kostenlos, INV-D-036); Kap. 43.3 Standard „manuell“, Selbstanmeldung aus | Kap. 43.3 gilt (sicherer Default); als bewusste Abweichung in die Golden-Master-Abweichungsliste (E-55) | E-55 |
| **C-14** | „Exportberichte“ Pro vs. eigene Ergebnisübersicht Free | Kap. 5 Zeile „Lernen“ (Exportberichte Pro) gegen Kap. 4.5 (exportierbare eigene Ergebnisübersicht Free) | Kein Widerspruch in der Sache: Lernender exportiert **eigene** Daten (F1, L-20), Admin-Auswertungsexporte PK; in Kap. 5 präzisieren | N-06 |
| **C-15** | Dashboard „offene Bewertungen“ | Kap. 16.3 Dashboard nennt offene Bewertungen; in R1 gibt es keinen bewertbaren Zustand `submitted` (R0-08 §6.1) | Widget erst mit R2-02 (Free-Grundrückmeldung) bzw. Tutorbewertung (P3) | – |
| **C-16** | Settings-Tabs | Kap. 16.2 (9 Tabs, ohne „Zertifikate“, „Datenschutz“ ohne „Sicherheit“) gegen Kap. 43.2 (10 Tabs) | Kap. 43.2 gilt (A-15) | – |
| **C-17** | Kap. 7 „ohne Pro nur freie/manuell freigeschaltete Kurse“ | Kap. 7 (Woo-Adapter-Liste) klingt, als bräuchten Gruppen-Grants Pro; Kap. 7 Abschnitt „Teams“ und Kap. 43 sehen Gruppen-Grants Free | Satz präzisieren: „ohne Pro keine **Kauf**-Grants“ | – |
| **C-18** | Inventur-Phasen „R2“ für Tipps und Taxonomie-Helfer | INV-D-057, 058, 082 („R2 Tipps“) gegen E-16 (Mechanik R1) | E-16 gilt; INV-D-058 nach R1-05 (R0-04 W-12) | E-16, E-54 |

---

## 5. Entscheidungsbedarf (neu, nicht in `ENTSCHEIDUNGEN.md`)

| ID | Frage | Optionen | Empfehlung | Blockiert |
|---|---|---|---|---|
| **N-01** | Was passiert mit **bestehender Pro-Konfiguration** (Drip-Kampagnen, Lernpfade, Pro-Fragetypen, Pro-Activities, Designer-Vorlage), wenn (a) die Lizenz abläuft bzw. (b) das Add-on deaktiviert wird? | (1) wirkt weiter, nur Änderungen gesperrt; (2) fällt sofort auf Free-Verhalten zurück; (3) wirkt weiter bei Ablauf, Free-Fallback nur bei Deaktivierung | **(3):** Bei Ablauf wirkt alles eingefroren weiter (ER-4). Bei Deaktivierung kann kein Pro-Code laufen, daher Free-Fallback wie in Tabelle 1.5 (Sperren entfallen, Pro-Activities „nicht verfügbar“ und nicht abschlusspflichtig, Basisvorlage) mit Vorab-Wirkungsbericht | R3-08, R3-15, R3-18 |
| **N-02** | Laufen **Käufer-schützende Vorgänge** der Pro-Connectoren nach Lizenzablauf weiter (Grant nach Zahlung, Widerruf nach Erstattung, Gutscheineinlösung, Abschluss offener Tutorbewertungen)? | (1) ja, nur neue Konfiguration gesperrt; (2) nein, alles gesperrt (Kap. 6 wörtlich); (3) ja, aber nur für eine Übergangsfrist | **(1)**, sonst zahlen Kunden ohne Zugang oder behalten Zugang trotz Erstattung (C-09). Admin-Banner, Systemstatus-Warnung | R3-02…R3-06, R3-08, E-53 |
| **N-03** | **Zuschnitt der Entitlement-Schlüssel** für License Captain | (1) Kap. 6 unverändert; (2) zuschneiden und ergänzen: `course.drip_campaigns` (statt `drip_content`), `course.certificate_designer` (statt `certificates`), `course.groups_advanced` (statt `groups`), neu `course.reminders`, `course.gradebook`, `course.frontend_builder`, `course.multi_instructor`, `course.advanced_roles`, `course.content_workflows` | **(2)**, vor dem Shop-Eintrag aus E-42; nur tatsächlich implementierte Schlüssel in die FeatureRegistry (Kap. 6) | E-42, R0-39, R3-18 |
| **N-04** | **Format des Free-Basisnachweises** | (1) HTML-Nachweisseite mit Druckansicht (Browser-PDF), serverseitiges PDF erst mit Designer (Pro); (2) serverseitiges PDF schon in Free; (3) nur Statusanzeige ohne Dokument | **(1):** keine PDF-Bibliothek im Free-Kern, Kap. 4.5 (PDF = Pro) bleibt im Kern erhalten, Verifikation unabhängig vom Format. ER-1 beachten: was in R1 Free ist, bleibt Free | R1-35, R0-29 |
| **N-05** | **Personenname auf der öffentlichen Verifikationsseite** (Kap. 35.4 „nur mit geeigneter Freigabe“) | (1) nie; (2) Standard aus, Lernender gibt je Nachweis frei; (3) Standard an, Betreiber kann abschalten | **(2)**, Standard: Status, Aussteller, Kurs, Datum, Initialen | R1-35, R0-31 |
| **N-06** | **Umfang „Berichte Basic“ (Free)** | (1) Kursübersicht (Eingeschriebene, aktiv, Abschluss-/Bestehensquote) + Drilldown je Lernendem, kein CSV; (2) wie (1) plus CSV der Kursteilnehmerliste; (3) nur Lernendendetail in A-09 | **(1)** gemäß Kap. 10 („CSV in Pro“); personenbezogene Exporte bleiben über die Privacy-API möglich | R1-16, R3-08 |
| **N-07** | **Tutor-/Assessor-Rolle in R1?** | (1) Rolle und `cc_grade_assessments` in R1 anlegen; (2) Capability nur reservieren, Rolle erst mit R2-02/Pro; (3) gar nicht | **(2)**: keine Rolle ohne Funktion; Instructor (Einzel-Eigentümer) in R1 Free, Mehrtrainer Pro | R0-07, R1-03 |
| **N-08** | **Sichtbarkeit von Pro im Free-Plugin** (WordPress.org-Richtlinien, ER-5) | (1) keine Pro-Hinweise im Free-UI; (2) eine ausblendbare Seite/Karte „Erweiterungen“ in A-14, keine gesperrten Schalter in Free-Screens; (3) gekennzeichnete, gesperrte Pro-Felder in Free-Screens | **(2)** | R1-32, R0-30, R0-35 |
| **N-09** | **Voraussetzungen innerhalb eines Kurses** (R0-08 §1.4 Nr. 4, LB nur `required` je Modul) | (1) Free R1; (2) Free R2 (neue Task); (3) Pro `advanced_paths` | **(1)** für kursinterne Voraussetzungen (Kernfunktion jedes LMS, Kap. 3.1 „optionale Voraussetzungen“), kursübergreifende Pfade Pro. Abweichung von „Parität zuerst“ (Kap. 29.2 Nr. 1) ausdrücklich beschließen | R1-04, R1-26, R0-08 |
| **N-10** | **Demo-Kurs im Setup-Assistenten (R1)**, obwohl Demos R2-03 sind | (1) Schritt 6 in R1 nur „Leeres Kursgerüst anlegen“ (Modul + Lektion + Beispielprüfung ohne Fachinhalt); (2) Schritt 6 entfällt bis R2-03; (3) Demo-Inhalt schon in R1 | **(1)** | R1-32, R2-03 |
