# Course Captain – Wettbewerbsanalyse: To-do-Liste

> **Status:** Vom Auftraggeber zur Aufnahme ins Konzept freigegeben und in **Kapitel 27 von `konzept.md`** aufgenommen. Diese Datei bleibt die operative Checkliste; offene Kästchen bedeuten **noch nicht umgesetzt**. Prioritäten und Editionen sind vorläufig und werden vor Implementierung konkretisiert.
>
> **Leitplanken:** Universell für alle Kursanbieter; zuerst fachneutrale Funktionsparität zu LearnBoating; vorhandene Lernlogik bevorzugt wiederverwenden; **keine** boots-, funk- oder navigationsspezifischen Module; LearnBoating und andere Captain-Repositories bleiben unverändert.

## P0 – Fachliche Wichtigkeit; Umsetzung strikt nach Release-Stufen R0–R2

- [ ] **LearnBoating-Paritätsmatrix abschließen:** alle fachneutralen Funktionen, Quellkomponenten, Übernahmestrategien, Regressionstests und Abnahmekriterien inventarisieren. Fachmodule ausdrücklich ausschließen.
- [ ] **Kurs-/Quiz-Builder im Admin prüfen und vervollständigen:** Fragen direkt in Lektionen erstellen, Vorschau, Duplizieren, Revisionen, barrierearme Bedienung.
- [ ] **Persönliche Lernnotizen evaluieren:** private Notizen je Lektion, Suche, Export und optionales Umwandeln in Lernkarten; Datenschutz und Speicherlast prüfen.
- [ ] **Aufgabenabgaben als universelle Aktivität spezifizieren:** Text- und Datei-Einreichungen, sichere Uploads, Status und Feedback; Abgrenzung zur vorhandenen Praxisaufgabenlogik prüfen.
- [ ] **Kursvorlagen und Demokurse:** Duplizieren, neutrale Vorlagen (Onboarding, Sprachen, IT, Weiterbildung), sicherer Import/Export und keine Fach-Altinhalte.

## P1 – Professioneller LMS-Betrieb

- [ ] **Gradebook/Notenbuch:** Quiz, Prüfungen, Aufgaben, manuelle Bewertungen, Exporte und Rollenrechte; nicht doppelt zur Lernhistorie speichern.
- [ ] **Tutorbewertung:** Bewertungsraster, Kommentare, Wiedereinreichungen, Nachvollziehbarkeit und Benachrichtigungen.
- [ ] **Frontend-Kurs-Builder für Trainer:** Kurse und Quiz ohne WP-Admin bearbeiten; Rechte, Entwürfe, Medien und Revisionen absichern.
- [ ] **Mehrere Trainer je Kurs:** Verantwortlichkeiten, Berechtigungen, Freigaben und Audit-Log definieren.
- [ ] **Visuellen Zertifikatsdesigner prüfen:** Vorlagen, dynamische Felder, Logo, Signatur, Nachweis-ID, Verifikation und Widerruf; keine amtlichen Zusagen.
- [ ] **Kompetenzmatrix/Lernziele:** Bearbeitung, Beherrschung und Nachweise getrennt auswerten; Versionierung und Datenschutz.
- [ ] **Kursdiskussionen und Trainerfragen:** Moderation, Benachrichtigungen, Anti-Spam und optional vorhandene WordPress-/Suite-Funktionen nutzen.
- [ ] **Interaktive Medienaktivitäten:** Bild-Hotspots, Video-Fragen, Matching, Sortierung und Szenarien als fachneutrale Activity-Provider evaluieren.
- [ ] **Kurs-Bundles und Mitgliedschaften:** vorhandene Entitlement-Logik und WooCommerce/Suite-Verträge prüfen; keine Commerce-Pflicht für Free.
- [ ] **Kursbewertungen:** Integration mit Review Captain prüfen, keine doppelte Bewertungsdatenhaltung.

## P2 – Nach Marktvalidierung

- [ ] **Gamification:** Badges, Punkte, Streaks; Lernwirksamkeit und Missbrauchsanreize prüfen.
- [ ] **SCORM/xAPI/H5P/LTI:** tatsächliche Kundennachfrage, Sicherheits- und Lizenzfolgen sowie Import-/Laufzeitkompatibilität bewerten.
- [ ] **Erweiterte Kohorten-/Coaching-Funktionen:** nur bei belegter Nachfrage priorisieren.

## Konkurrenzvergleich – gezielt verifizieren

- [ ] **LearnDash:** Quiz/Assignments, Gradebook, Gruppen, Zertifikate, Lernpfade und Preis-/Lizenzgrenzen anhand aktueller Produktdokumentation und Testinstallation vergleichen.
- [ ] **Tutor LMS:** Frontend-Builder, mehrere Trainer, Content-Bank, Bundles, Memberships und Checkout praktisch testen.
- [ ] **LifterLMS:** Kohorten, Coaching, Diskussionen, Notizen, Mitgliedschaften und Reports prüfen.
- [ ] **Sensei LMS:** WordPress-Editor, Lernmodus, interaktive Videos, Lernkarten und Hotspots prüfen.
- [ ] **LearnPress / MasterStudy LMS:** Kernumfang, Erweiterungen, Bedienbarkeit und Free-/Pro-Grenzen prüfen.
- [ ] **Vergleichsmatrix erstellen:** Feature, Konkurrent, tatsächliche Verfügbarkeit (Free/Pro/Add-on), Course-Captain-Status (vorhanden/geplant/fehlt), Nutzen, Aufwand, Priorität, Quelle und Prüfdatum.
- [ ] **Praxis-Benchmark:** Kurs mit 10 Lektionen, 50 Fragen, einer Aufgabe und einer Prüfung in jedem Produkt anlegen; Klicks, Zeit, UX, Accessibility und Performance dokumentieren.

## Entscheidungstore vor Umsetzung

- [ ] **Keine Konkurrenzfunktion blind übernehmen:** Nutzerproblem, konkreten Nutzen, Aufwand und Abhängigkeiten nachweisen.
- [ ] **Dopplungen vermeiden:** erst bestehende LearnBoating- und Captain-Suite-Funktionen prüfen.
- [ ] **Free/Pro-Grenzen einzeln entscheiden:** Free muss ein eigenständiges, vollwertiges LMS bleiben.
- [ ] **P0 bedeutet Prüfung/Planung, nicht automatisch v1.0-Pflicht:** vollständige fachneutrale LearnBoating-Parität hat Vorrang.
- [x] **Aufnahme ins Konzept freigegeben:** Funktionskandidaten und Evaluierungsaufträge in Kapitel 27 von `konzept.md` erfasst; Implementierungsumfang und Release-Zuordnung weiter prüfen.

## Referenzseiten für die nächste Verifikation

- https://www.learndash.com/
- https://tutorlms.com/
- https://lifterlms.com/
- https://senseilms.com/
- https://learnpresslms.com/
- https://stylemixthemes.com/wordpress-lms-plugin/

## Expertenreview – zusätzliche verbindliche Arbeitsaufträge (Konzept Kapitel 28)

### Architektur und Datenintegrität – Gate A/B

- [ ] **ADR-001 bis ADR-008 verfassen:** Inhaltsspeicherung, Lern-/Prüfungsdaten, Inhaltsrevisionen, Activity-Provider, Smarttrainer-Portierung, Queue/Outbox, Lizenz vs. Lernzugang, Rollen/Datenschutz.
- [ ] **Unveränderliche Prüfungs-Snapshots nachweisen:** Fragen, Antwortoptionen, Reihenfolge, Zeitlimits, Regeln und Seed bei Versuchsbeginn fixieren; spätere Änderungen dürfen Ergebnisse nicht verändern.
- [ ] **Zustandsmaschinen spezifizieren:** Content, Prüfungsversuch, Aufgabenabgabe und Einschreibung mit erlaubten Übergängen und serverseitiger Validierung.
- [ ] **Idempotenz- und Wiederanlauftests:** Antwortspeicherung, Prüfungseinreichung, Enrollment, Eventzustellung, Queue-Retry, Backoff und Dead-Letter-Fälle.
- [ ] **Golden-Master-Tests für Smarttrainer:** Lernstufen, Fälligkeit, Zeitregeln, Fehlertraining und Algorithmusversionierung vor Portierung sichern.
- [ ] **Datenmodell-/Upgrade-Probe:** Schlüssel, Indizes, Migration, Rollback und Backup-Restore mit realistischen Datenmengen testen.

### Lernwissenschaft und Autoren-UX – Gate B/C

- [ ] **Drei getrennte Fortschrittsdimensionen:** Bearbeitung, Beherrschung und Kompetenznachweis samt erklärbaren Regeln, API und UI spezifizieren.
- [ ] **Abschluss- und Bewertungsregeln je Activity:** Ansichtsabschluss, Quizgrenzen, Teilpunkte, manuelle Korrekturen, Mehrfachversuche und Auditierbarkeit testen.
- [ ] **Aufgaben-Workflow und sichere Uploads:** draft/submitted/returned/graded, Dateityp-/MIME-/Größenprüfung, private Ablage und Wiedereinreichung.
- [ ] **Kompetenzzuordnung:** Lernziele mit Aktivitäten/Fragen verbinden; Nachweisquelle, Datum, Version und Gültigkeit speichern.
- [ ] **Drei neutrale Autorentests:** Selbstlernkurs, Prüfungsvorbereitung, Onboarding; jeweils Referenzkurs mit 10 Lektionen, 50 Fragen, Aufgabe und Prüfung.
- [ ] **Lernenden-E2E und Accessibility:** Desktop/Mobil, Tastatur, Screenreader, Wiederaufnahme, Smarttrainer, Prüfung, Export.

### Produkt, Suite und Release – Gate C/D

- [ ] **Release-Gates A–D verbindlich abnehmen:** Paritätsinventar, Free-Lernfluss, Betriebsreife, einzelne Erweiterungsfreigaben.
- [x] **P0 aufteilen:** R1 enthält nur fachneutrale Parität und notwendige Basisfunktionen; zusätzliche P0-Wettbewerbsfeatures folgen in R2 (siehe Konzept Kapitel 29).
- [ ] **Free-/Pro-Grenzen abgleichen:** mit Kapitel 5, keine Doppelimplementierung, Free unabhängig von Commerce und Lizenzserver.
- [ ] **Suite-Contract-Tests:** fehlende Plugins, doppelte Events, Timeouts, Retry, Versionswechsel, eindeutige Datenhoheit.
- [ ] **Messkonzept erstellen:** Zeit bis zur Kursveröffentlichung, Kursabschluss, Prüfungsfehler, Job-Wiederherstellung, Supportlast und Konversion mit Baselines.
- [ ] **Verbleibende Produktfragen entscheiden:** Multisite-/Mehrtrainer-Fokus und dauerhafte Verifikation nach Pro-Ablauf. **P0-Zusatzumfang ist entschieden: R2 nach R1.**

> **Status:** Kapitel 28 wurde nach einer simulierten Drei-Experten-Review ergänzt. Die Aufgaben sind **noch nicht implementiert**; `konzept.md` und diese Checkliste sind die einzigen geänderten Dateien.

## Verbindlicher Release-Plan – beschlossen (Konzept Kapitel 29)

**Entscheidung:** Erst **vollständige fachneutrale LearnBoating-Parität**, **danach zusätzliche P0-Wettbewerbsfunktionen**. P0 bedeutet Wichtigkeit, nicht automatisch Bestandteil des ersten Releases. Keine fachgebundenen Altmodule.

### R0 – Inventar und Paritätsnachweis vorbereiten

- [ ] Alle fachneutralen LearnBoating-Funktionen vollständig inventarisieren und fachgebundene Module ausschließen.
- [ ] Paritätsmatrix mit Quellreferenz, Zielkomponente, neutralem Testfall und Abnahmestatus erstellen.
- [ ] Golden-Master-Tests, Lizenz-/Abhängigkeitsinventar und Architektur-ADRs fertigstellen.
- [ ] **Gate A abnehmen** und den exakten R1-Umfang einfrieren.

### R1 – Free-Paritätsrelease (vor jeder zusätzlichen P0-Implementierung)

- [ ] Fachneutralen Kurs-/Lernfluss einschließlich **notwendigem** Basis-Kurs-/Quiz-Editor implementieren.
- [ ] Sämtliche fachneutralen Lernfunktionen aus der freigegebenen Paritätsmatrix portieren und testen (Smarttrainer, Prüfungen, Fortschritt, Medien usw.).
- [ ] Inhaltsversionierung, Prüfungs-Snapshots, Rechte, Datenschutz, Upgrade und Backup-Restore prüfen.
- [ ] **100 % der fachneutralen Paritätsmatrix abnehmen; Gates B und C bestehen.**
- [ ] **R1 ausdrücklich freigeben**, bevor R2-Features implementiert werden.

### R2 – Zusätzliche P0-Wettbewerbsfunktionen (erst nach R1)

- [ ] Persönliche Lernnotizen, Suche/Export und optional Lernkarten aus Notizen.
- [ ] Text-/Datei-Aufgabenabgaben mit sicherem Upload und Basisfeedback.
- [ ] Neutrale Kursvorlagen, Demokurse und Duplizierungs-Komfortfunktionen.
- [ ] Zusätzliche Kurs-/Quiz-Builder-UX, soweit **nicht** bereits für R1-Parität erforderlich.
- [ ] Jedes R2-Feature einzeln auf Nutzen, Free-/Pro-Grenzen, UX, Sicherheit und Regression abnehmen.

### R3/R4 – Danach

- [ ] **R3:** Gradebook, Tutorbewertung, Frontend-Kurs-Builder, Mehrtrainer, Zertifikatsdesigner und Pro-/Suite-Adapter nach Abhängigkeiten planen.
- [ ] **R4:** Gamification, SCORM/xAPI/H5P/LTI und Coaching nur nach Nachfrage-/Sicherheitsprüfung.

> **Ausnahmeregel:** Eine Funktion, die bereits **fachneutral in LearnBoating existiert**, gehört mit ihrem paritätsrelevanten Umfang nach **R1**, auch wenn sie in der Wettbewerbs-To-do-Liste unter R2 genannt ist. Der zusätzliche Funktionsausbau bleibt R2.

## Captain-Suite-Vollständigkeitsabgleich – operative Aufgaben (Konzept Kapitel 30)

> **Reihenfolge ist bindend:** R0/R1 = vollständige fachneutrale LearnBoating-Parität; R2 = zusätzliche P0-Wettbewerbsfunktionen; **R3 erst danach** = optionale Suite-Integrationen und Course-Captain-Pro. Aufgaben dieser Sektion dürfen zuvor spezifiziert, aber nicht vor R1/R2 als Ersatz für die priorisierte Entwicklung implementiert werden. Keine Änderungen an Schwesterrepositories.

### R0 – Schnittstellen inventarisieren (nur Dokumentation/Architektur)

- [ ] **SUITE-001** Schnittstellenbestand aller acht Partner anhand aktueller Quellen und Quellcode-Versionen verifizieren; Konzept-/Implementierungsstatus je API markieren.
- [ ] **SUITE-002** Ownership- und Datenflussmatrix (Lernen, Formulare, Reviews, Voucher, Tickets, Verkäufe, Lizenzen, Workflows) verbindlich freigeben.
- [ ] **SUITE-003** Event-Envelope v1, versionierte Commands/Queries, Capability- und Fehlercodes sowie Idempotenz-/Replay-Verträge spezifizieren.
- [ ] **SUITE-004** Integrationszustände, Mindestversionen, Datenschutz, Secrets, Audit und Diagnose-UX definieren.
- [ ] **SUITE-005** Course-Captain-Pro-Produkt-/Add-on-Grenzen mit Captain Pro und License Captain abgleichen; **keine** Softwarelizenz mit Lernzugang gleichsetzen.

### R3 – Optionale Connectoren nach Parität und zusätzlichen P0-Funktionen

- [ ] **SUITE-101 Workflow Captain:** Event-Provider für Enrollment, Lektion, Assessment, Kursabschluss, Zertifikate; idempotente Commands, Outbox, Retry/Replay, Contract-Tests.
- [ ] **SUITE-102 Form Captain:** sicherer Kurs-/Lektionskontext, Lernendenreferenz, Entry-Zuordnung, Feldmapping, Berechtigungs- und Datenschutztests.
- [ ] **SUITE-103 Review Captain:** verifizierte Completion-/Experience-ID, Opt-in/Opt-out, Deduplizierung, Widerruf, Einladungsregel und E2E-Test.
- [ ] **SUITE-104 Voucher Captain:** Connector gemäß `CONNECTORS.md` mit Reserve/Commit/Kompensation, Kurs-Entitlement, Storno/Refund und Parallelitätstests.
- [ ] **SUITE-105 Pass Captain:** Ticket-/Event-zu-Kurs-Mapping, Enrollment, Storno, Teilrefund, Check-in-Abgrenzung und Rollback.
- [ ] **SUITE-106 Affiliate Captain:** versionierter Verkaufs-/Refund-Adapter gemäß `docs/CONTRACT.md`, Autoritätsquelle je Verkauf, Doppelprovisionsschutz, Rückbuchungen.
- [ ] **SUITE-107 WP License Captain:** Pro-Client/Updater, Status-/Grace-Regeln, signierte Updates, Lizenzserver-Ausfall, Free-Unabhängigkeit und Release-Nachweis.
- [ ] **SUITE-108 Captain Pro:** separates Course-Captain-Pro-Add-on, Feature-Registry, Paketierung, Produktschlüssel, Lizenz-/Upgrade- und Deaktivierungstests.
- [ ] **SUITE-109 Admin-Diagnose:** Integrationsübersicht mit Zustand, Version, Vertrag, Test, Fehlerprotokoll, sicherem Retry und Supportexport.
- [ ] **SUITE-110 Rezeptvorlagen:** Lernen→Workflow, Kurs→Form, Abschluss→Review, Voucher→Kurs, Pass→Kurs, Affiliate→Verkauf, Pro→Lizenz als reproduzierbare E2E-Tests.
- [ ] **SUITE-111 Dokumentationspaket:** SDK, Beispiel-Connector, API-Referenz, Datenfluss, Fehlerkatalog, Migration, Deinstallation, Datenschutz, Betriebsanleitung und CI-Matrix.

### R4 – Nur nach separater Freigabe

- [ ] **SUITE-201** Multisite-/Cross-Site-Connectoren mit signierten Webhooks, Replay-Schutz, Schlüsselrotation und Site-scoped Idempotenz.
- [ ] **SUITE-202** Erweiterte Agentur-/CRM-/Mitgliedersystem-Connectoren nach validiertem Bedarf.

**Definition of Done pro Integration:** Öffentliche API versioniert, Partner optional/deaktivierbar, keine direkten Fremdtabellen, keine Doppelzustellung/-provision, Rechte/Datenschutz nachgewiesen, Ausfall/Retry/Refund/Upgrade getestet, Free ohne Suite weiter funktionsfähig. **Alle Aufgaben offen; kein Implementierungsstatus behauptet.**

## Expertenbeschlüsse: Nahtlose Captain Suite (Konzept Kapitel 31)

**Status:** Simulierte Fachdebatte; die folgenden Aufgaben sind **offen und nicht implementiert**. Keine Änderung an anderen Repositories. Reihenfolge R0/R1 → R2 → R3 → R4 bleibt verbindlich.

### R0 – Dokumentieren und entscheiden, keine vorgezogene Suite-Implementierung

- [ ] **SEAM-001** Zugangsgründe und Enrollment-Zustandsmaschine mit Mehrfach-Grants, Ablauf, Widerruf, Refund und Audit spezifizieren.
- [ ] **SEAM-002** Saga-/Kompensationsvertrag für Kauf, Voucher, Pass und Enrollment: persistierter Zwischenzustand, Timeout, Retry, manuelle Klärung, Korrelations-ID.
- [ ] **SEAM-003** Schnittstellen für verifizierte Completion, Prüfung und Zertifikat gegenüber bloßer Zahlung/Check-in abgrenzen.
- [ ] **SEAM-004** Suite-UX-Konventionen für Adminnavigation, Statussprache, Setup, Lernenden- und Trainerpfad definieren.
- [ ] **SEAM-005** Vertragsversionen, Capability-/Objektzugriff, Datenschutzscopes, Log-Retention und Deinstallationsverhalten festlegen.
- [ ] **SEAM-006** Referenzconnector-Reihenfolge, Pending-Eskalation, Gastkonto-Verknüpfung und Suite-Navigationsmuster entscheiden.

### R3 – Nahtlose Suite-Prozesse bauen und abnehmen

- [ ] **SEAM-101** Enrollment-Grants pro Quelle mit `pending/active/expired/revoked/failed`, eindeutiger Quellreferenz und mehreren gleichzeitig gültigen Zugängen.
- [ ] **SEAM-102** Gutschein-/Verkaufs-/Ticket-Prozesse mit idempotenten Commands, Bestätigung, Kompensation, Storno und Refund-End-to-End testen.
- [ ] **SEAM-103** Completion→Workflow/Review mit Outbox, Deduplizierung, Consent, Zertifikatsrevision und sicheren Retries testen.
- [ ] **SEAM-104** Kontextgebundene Formulare mit scoped Lernenden-/Kursreferenz und IDOR-/Datenschutztests integrieren.
- [ ] **SEAM-105** Admin-Integrationsübersicht mit Partnerstatus, Kompatibilität, Diagnose, Wiederholung, Supportexport und hilfreichen Fehlermeldungen.
- [ ] **SEAM-106** Lernenden-UX Kauf/Einlösung→Mein Lernen→Prüfung→Nachweis inklusive Pending-, Offline-, Refund- und Wiederaufnahmezuständen testen.
- [ ] **SEAM-107** Trainer-UX Kurs→Zugangsquelle→Einschreibung→Ergebnis→Automation/Review mit Rollenrechten und Accessibility prüfen.
- [ ] **SEAM-108** Kompatibilitätsmatrix, Upgrade/Downgrade, Partner-Deaktivierung, Lizenzserver-Ausfall, parallele Refunds und Backup-Restore in CI nachweisen.
- [ ] **SEAM-109** Metriken und Warnungen: Zeit bis Zugang, verwaiste Pending-Grants, Retry-Erfolg, doppelte Seiteneffekte, Supportfälle, End-to-End-Erfolgsquote.
- [ ] **SEAM-110** Dokumentierte Betriebsrezepte, Screens/Leerzustände, Fehlerkatalog und Betreiberhandbuch bereitstellen.

**Freigabekriterium:** Kein Partnerplugin erforderlich für den Free-Lernkern; alle Zustandsübergänge autorisiert und nachvollziehbar; keine doppelte Einschreibung, Auszahlung, Zertifikatsausstellung oder Review-Einladung; keine Kompetenzzusage aus Zahlung oder Ticket; sichere Rückabwicklung; reproduzierbare E2E-Nachweise.
