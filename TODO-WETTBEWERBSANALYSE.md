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

## Anonymisierte Marktanforderungen – gezielt verifizieren

- [ ] **Prüfungen:** Quiz, Aufgaben, Gradebook, Gruppen, Zertifikate und Lernpfade produktneutral prüfen.
- [ ] **Autorenfunktionen:** Frontend-Builder, mehrere Trainer, Content-Bank, Pakete, Mitgliedschaften und Checkout evaluieren.
- [ ] **Lernerfahrung:** Kohorten, Coaching, Diskussionen, Notizen, interaktive Videos, Lernkarten und Hotspots anhand eigener Testfälle prüfen.
- [ ] **Kernumfang:** Free-/Pro-Grenzen, Erweiterbarkeit, Bedienbarkeit, Performance und Barrierefreiheit bewerten.
- [ ] **Anforderungsmatrix:** Funktion, Nutzerproblem, Bedarf, Course-Captain-Status, Nutzen, Aufwand und Priorität – ohne Fremdproduktnamen und URLs.
- [ ] **Praxis-Benchmark:** Neutralen Referenzkurs mit 10 Lektionen, 50 Fragen, einer Aufgabe und einer Prüfung im eigenen System testen.

## Entscheidungstore vor Umsetzung

- [ ] **Keine Konkurrenzfunktion blind übernehmen:** Nutzerproblem, konkreten Nutzen, Aufwand und Abhängigkeiten nachweisen.
- [ ] **Dopplungen vermeiden:** erst bestehende LearnBoating- und Captain-Suite-Funktionen prüfen.
- [ ] **Free/Pro-Grenzen einzeln entscheiden:** Free muss ein eigenständiges, vollwertiges LMS bleiben.
- [ ] **P0 bedeutet Prüfung/Planung, nicht automatisch v1.0-Pflicht:** vollständige fachneutrale LearnBoating-Parität hat Vorrang.
- [x] **Aufnahme ins Konzept freigegeben:** Funktionskandidaten und Evaluierungsaufträge in Kapitel 27 von `konzept.md` erfasst; Implementierungsumfang und Release-Zuordnung weiter prüfen.

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
- [ ] **SEAM-006** Verbleibende Entscheidungen: Pending-Eskalation, Gastkonto-Verknüpfung und Suite-Navigationsmuster. **Referenzconnector-Reihenfolge ist entschieden: Voucher Captain und WooCommerce zuerst (Kapitel 32).**

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

## Verbindliche Entscheidung: R3 beginnt mit Voucher Captain + WooCommerce (Konzept Kapitel 32)

> **Freigaberegel:** Keine R3-Connector-Implementierung vor vollständiger R1-Parität **und** abgeschlossener R2-P0-Stufe. WooCommerce und Voucher Captain bleiben optional; Free funktioniert eigenständig.

### R0 – Vorbereitende Spezifikation (noch keine Connector-Implementierung)

- [ ] **R3-FIRST-001** WooCommerce-Produkt-/Variations-/Paket-zu-Kurs-Mapping, Kursgrant-Quelle, Bestellpositionsreferenz und Statusmatrix dokumentieren.
- [ ] **R3-FIRST-002** Voucher-Connector-Vertrag gegen aktuelle `wp-couponmaster/CONNECTORS.md` und realen API-Stand verifizieren; direkter Kurs-Einlösekanal, Reservierung, Commit, Release und Refund spezifizieren.
- [ ] **R3-FIRST-003** Gastkauf-Identitätsverifikation, Mehrfach-Grants, Teilrefund, Chargeback, Parallelität, Duplicate-Callbacks und kombinierte Woo-/Voucher-Käufe entscheiden.
- [ ] **R3-FIRST-004** Kompatibilitätsmatrix für WooCommerce HPOS/Classic, Blocks-/Classic-Checkout, Pluginversionen und Installationskombinationen festlegen.

### R3.1 – WooCommerce-Connector zuerst implementieren und abnehmen

- [ ] **R3-FIRST-101** WooCommerce-Produktzuordnung, Kaufbestätigung, idempotente Enrollment-Grants und widerrufbare Zugänge umsetzen.
- [ ] **R3-FIRST-102** Storno, Teilrefund, Chargeback, Bestellstatuswechsel, Retry/Backfill, Gastkonto und historische Lernnachweise E2E testen.

### R3.2 – Voucher-Captain-Connector implementieren und abnehmen

- [ ] **R3-FIRST-103** Direkte Kurseinlösung mit Voucher-Reserve/Commit/Release, Grant und kompensierenden Fehlerpfaden integrieren.
- [ ] **R3-FIRST-104** Mehrfachnutzung, Kurs-/Platzpakete, doppelte und parallele Einlösungen, Timeout und Refund E2E prüfen.

### R3.3 – Gemeinsames Integrations-Gate

- [ ] **R3-FIRST-105** WooCommerce allein, Voucher allein (soweit vertraglich möglich), beide gemeinsam und beide deaktiviert testen; Doppel-Grant bei kombinierter Bestellung verhindern.
- [ ] **R3-FIRST-106** Sicherheits-/Datenschutz-/Kompatibilitäts-/Upgrade-/Backup-/Recovery-Gates mit dokumentierten Nachweisen bestehen.
- [ ] **R3-FIRST-107** Admin-Diagnose und Lernendenstatus für `pending/active/expired/revoked/failed`, Support- und Wiederanlaufpfade abnehmen.
- [ ] **R3-FIRST-108** Weitere Suite-Connectoren erst nach diesem Referenzpaket nach Abhängigkeit und Nutzen priorisieren; bestehende SUITE-/SEAM-Aufgaben erhalten.

**Status:** Strategische Entscheidung dokumentiert; sämtliche Implementierungs- und Testaufgaben bleiben offen.

## Produktentscheidungen und vollständiger Prozesskatalog (Konzept Kapitel 33–34)

**Entschieden:** Gemeinsames Suite-Gesamtdashboard **vorerst zurückgestellt**, kein Releaseblocker. **Automatische Partnererkennung** verbindlich, aber keine automatische Aktivierung, Konfiguration oder Datenfreigabe. R0/R1 Parität → R2 P0 → R3 WooCommerce und Voucher zuerst → weitere Integrationen.

### R0 – Konzept- und Testdesign

- [ ] **FLOW-001** Für alle IDs `ID-01…06`, `LEARN-01…10`, `ACCESS-01…09`, `COMMERCE-01…10`, `SUITEFLOW-01…10`, `OPS-01…10` aus Kapitel 34 Owner, Trigger, Vorbedingungen, Zustände, Auth, Events, UI, Fehler, Retry, Audit und Abnahmetest ausarbeiten.
- [ ] **FLOW-002** Testmatrix je Szenario: Happy Path, Ablehnung, Duplicate, Parallelität, Timeout, Partnerausfall, Retry, Widerruf und Recovery; Nichtanwendbarkeit begründen.
- [ ] **FLOW-003** Fachentscheidungen einholen: Refund nach Lernbeginn, Gastkonto-/Platzzuweisung, Grant-Timeout, Zertifikatsverifikation nach Lizenzablauf, Retention, Membership, Trainerrollen.
- [ ] **FLOW-004** Priorität und Release-Zuordnung jeder Katalog-ID prüfen: neutrale LearnBoating-Parität nach R1, zusätzliche P0 nach R2, Suite-Connectoren nach R3, Cross-Site nach R4.
- [ ] **FLOW-005** Partnererkennung spezifizieren: Plugin-Status, Version, Feature-Discovery, Vertragskompatibilität, explizites Opt-in, RBAC, Fehlerdiagnose und Deaktivierung.
- [ ] **FLOW-006** Gemeinsames Suite-Dashboard als **später zu prüfende Option** notieren; keine Implementierung oder Gate-Abhängigkeit ohne neue Entscheidung.

### R1/R2 – Fachliche Kernabläufe gemäß verbindlicher Release-Reihenfolge

- [ ] **FLOW-101** Neutrale Lern-, Prüfungs-, Fortschritts-, Zertifikats-, Zugangs- und Autorenabläufe aus `LEARN`, `ID` und `ACCESS` mit R1-Paritätsmatrix verknüpfen und abnehmen.
- [ ] **FLOW-102** Zusätzliche Notiz-, Aufgaben-, Vorlagen- und Builder-Abläufe nur in R2 umsetzen, sofern nicht nachweislich R1-Parität.

### R3 – WooCommerce/Voucher zuerst, danach übrige Suite

- [ ] **FLOW-201** Alle `COMMERCE-01…10` als reproduzierbare Vertrags- und End-to-End-Tests für WooCommerce und Voucher implementieren.
- [ ] **FLOW-202** Alle `SUITEFLOW-01…10` nach Referenzintegrationen einzeln als optionale Connector-Abnahme prüfen; keine Doppelprovision, kein doppelter Grant, keine falsche Kompetenz.
- [ ] **FLOW-203** Alle `OPS-01…10` über Standalone- und Connector-Matrix testen, inklusive Deaktivierung, Upgrade, Sicherheit, Datenschutz, Last und Recovery.
- [ ] **FLOW-204** Automatische Partnererkennung und optionale Integrationsoberfläche bauen: kompatibel/inaktiv/fehlend/fehlerhaft, Verbindungsprüfung, manuelles Opt-in, kein Pflicht-Suite-Kern.
- [ ] **FLOW-205** Kundenreisen für Käufer, Lernende, Trainer und Betreiber inklusive Statusmeldungen und Fehlerbehebung visuell spezifizieren und per E2E abnehmen.

**Definition of Done:** Jede Prozess-ID besitzt einen dokumentierten Owner, Zustandsautomaten, Fehler-/Kompensationspfad und Testnachweis; keine automatischen Datenfreigaben; Dashboard zurückgestellt; R1/R2 nicht durch Suite-Arbeiten verdrängen.

**Redaktionsregel:** Keine Namen, Domains oder URLs konkurrierender LMS-Produkte in diesen Projektdateien; Funktionsanforderungen produktneutral formulieren. Interne Captain-Suite- und LearnBoating-Referenzen bleiben zulässig.

## R0-Fachentscheidungen und konkrete Kernabnahme (Konzept Kapitel 35)

- [ ] **CORE-SPEC-001** Buyer/Learner/Trainer/Author/Manager/Admin als fachliche Rollen in einer WP-Capability-/Objektzugriffsmatrix ausarbeiten; IDOR-/Medien-/REST-/Exporttests festlegen.
- [ ] **CORE-SPEC-002** Gastkauf-Claim mit Einmal-Token, Verifikation, Ablauf, Wiederholung, Accountwechsel und Supportreparatur spezifizieren; keine E-Mail-basierte automatische Verknüpfung.
- [ ] **CORE-SPEC-003** Gruppen-/Seat-Modell einschließlich Kauf für Dritte, Zuweisung, Transfer, Teilrefund, Datenschutz und Mengeninkonsistenzen spezifizieren.
- [ ] **CORE-SPEC-004** Grant-Quelle, Idempotenzschlüssel, Statusautomat, effektiven Mehrfachzugang, Ablauf, Suspendierung, Widerruf und Reconciliation als versionierten Vertrag spezifizieren.
- [ ] **CORE-SPEC-005** Refund-/Chargeback-/Storno-Matrix je Woo-Status, Position, Menge, Lernbeginn und Zertifikat erstellen; rechtliche und kaufmännische Freigabe dokumentieren.
- [ ] **CORE-SPEC-006** Zertifikatsausstellung, Snapshot, Korrektur, Widerruf, öffentliche Minimalverifikation, Pro-Lizenzablauf und Retention präzisieren.
- [ ] **CORE-SPEC-007** Alle zwölf `CORE-01…CORE-12` als reproduzierbare Sicherheits-, Integrations- und E2E-Abnahmetests mit Vorbedingungen, erwarteten DB-Zuständen und Auditspuren ausarbeiten.
- [ ] **CORE-SPEC-008** Fachliche Regeln aus Kapitel 35 mit `ID`, `ACCESS`, `COMMERCE`, `LEARN`, `SUITEFLOW` und `OPS` aus Kapitel 34 sowie R0/R1/R2/R3-Gates verknüpfen; keine R3-Implementierung vor R1/R2.
- [ ] **CORE-SPEC-009** Tatsächliche WooCommerce-/Voucher-API-Verträge, Versionen, Checkout/HPOS und Partner-Recovery vor R3 anhand des aktuellen Codes erneut prüfen.
- [ ] **CORE-SPEC-010** Offene Datenschutz-/Rechts-/Betriebsentscheidungen zu Identitätsprüfung, Claim-Fristen, Refund, Datenaufbewahrung und Verifikation vor Implementierung explizit abzeichnen.

**Verbindliche Defaults:** kein Grant allein durch E-Mail; getrennte Käufer-/Lernendenidentität; Widerruf nur der betroffenen Quellberechtigung; Refund löscht weder Lernhistorie noch Zertifikat; Lizenzablauf beeinträchtigt historische Verifikation nicht. **Status:** ausschließlich konzeptionell dokumentiert, keine Implementierung.

## R0-Vertiefung: Rollen, Datenmodell und LearnBoating-Paritätsinventar (Konzept 36–38)

- [ ] **R0-RBAC-001** Capability-Slugs, Default-WP-Rollen, Meta-Cap-Mapping, Objektbeziehung und Site-Scope anhand Kapitel 36 finalisieren; Autorisierungsfixtures für jede Rolle/Aktion.
- [ ] **R0-RBAC-002** Rechteprüfung für REST, Admin, AJAX, Editor, Medien, Upload, Cron, CLI, Webhooks und Exporte zentral spezifizieren; IDOR, CSRF, Privilege-Escalation und Rollenwechsel testen.
- [ ] **R0-DATA-001** Jede Entität aus Kapitel 37 auf CPT/Meta vs eigene Tabelle abbilden; Schlüssel, Indizes, Unique Constraints, FK-Strategie und Storage-Owner dokumentieren.
- [ ] **R0-DATA-002** Versionierte API-/Eventschemas, Transaktionsgrenzen, Dedupe, Compare-and-swap, Outbox, Migration, Dry-run, Recovery und Privacy Export/Eraser als ADRs festlegen.
- [ ] **R0-PAR-001** Vollständiges tatsächliches LearnBoating-Quellinventar erheben: Dateien/Module, Screens, REST/AJAX, Cron, Datenobjekte, Importer und Tests mit Commitnachweis; **kein Haken ohne Codebeleg**.
- [ ] **R0-PAR-002** Jede Originalfunktion als `reuse/abstract/replace/exclude_domain_specific/unknown` klassifizieren; Domain-spezifische Inhalte und Regeln ausdrücklich ausnehmen.
- [ ] **R0-PAR-003** Für `PAR-01…PAR-12` Golden-Master-Fixtures, Regression, Rechte-, Performance- und Migrationstests sowie konkrete R1-Akzeptanz verlinken.
- [ ] **R0-GATE-001** Gate A erst bei vollständigem Quellinventar und aufgelösten Unknowns freigeben; R1-Gates B/C erst nach tatsächlicher Free-Parität, keine R2-/R3-Vorziehung.

**Status:** Kapitel 36–38 sind spezifizierte Architektur- und Inventarschablonen. Die Codeinventur und Tests bleiben offen; keine Implementierung.

## R0-Quellabgleich LearnBoating – erste belegte Dokumenteninventur (Konzept Kapitel 39)

- [x] **LB-AUDIT-001 – Einstieg und Quelllandkarte:** LearnBoating-`README.md`, `konzept-wp-lernplugin.md`, `schema/README.md` und `docs/schnittstellen-erweiterungen.md` lesend ausgewertet; 15 zusätzliche Prüffälle `LB-01…LB-15` in Kapitel 39 erfasst. **Nur Dokumentenabgleich, kein Implementierungsnachweis.**
- [ ] **LB-AUDIT-002 – Codeevidenz:** Für jeden `LB-01…LB-15` aktuelle Dateien/Klassen/Methoden, Commit, tatsächlichen Status und Testbelege erfassen; unzutreffende oder nur geplante README-Aussagen kennzeichnen.
- [ ] **LB-AUDIT-003 – Vollständigkeit:** Gesamtes `bootspruefung-learning/`, `tools/content-build/`, `schema/`, `tests/` und alle API-/Cron-/UI-Pfade systematisch inventarisieren; weitere Funktionsfamilien nachtragen.
- [ ] **LB-AUDIT-004 – Neutralität:** Jede Codefunktion als `reuse/abstract/replace/exclude_domain_specific/unknown` mit Lizenz-/Contentrechten, Abhängigkeiten und Aufwand klassifizieren.
- [ ] **LB-AUDIT-005 – R1/R3-Schnitt:** Generische Free-Kernfunktion nach R1; konkrete Woo-, Voucher- und Ticketadapter nach R3 gemäß bereits verbindlicher Reihenfolge. R1 muss standalone funktionieren.
- [ ] **LB-AUDIT-006 – Golden Master:** Bestehende Integration-/E2E-/Importtests auf neutrale Fixtures abbilden, Regressionslücken und neue Tests für Zugänge, Seats, Medien, Privacy und Fehlerfälle schließen.
- [ ] **LB-AUDIT-007 – Gate A:** R0 nicht abschließen, bevor alle Quellfunktionen codebasiert nachgewiesen und `unknown` aufgelöst sind; keine Aussage „100 % Parität“ allein aus der README.
