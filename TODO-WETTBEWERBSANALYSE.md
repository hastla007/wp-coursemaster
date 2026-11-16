# Course Captain – Wettbewerbsanalyse: To-do-Liste

> **Status:** Vom Auftraggeber zur Aufnahme ins Konzept freigegeben und in **Kapitel 27 von `konzept.md`** aufgenommen. Diese Datei bleibt die operative Checkliste; offene Kästchen bedeuten **noch nicht umgesetzt**. Prioritäten und Editionen sind vorläufig und werden vor Implementierung konkretisiert.
>
> **Leitplanken:** Universell für alle Kursanbieter; zuerst fachneutrale Funktionsparität zu LearnBoating; vorhandene Lernlogik bevorzugt wiederverwenden; **keine** boots-, funk- oder navigationsspezifischen Module; LearnBoating und andere Captain-Repositories bleiben unverändert.

## VERBINDLICHER SCHRITT-FÜR-SCHRITT-UMSETZUNGSPLAN (führender Arbeitsindex)

**Arbeitsregel:** Diese Aufgabenliste ist der operative Ausführungsplan. `konzept.md` ist die fachliche/technische Spezifikation. Die bisherigen P0/P1/P2-, FLOW-, CORE-, PAR- und LB-Aufgaben weiter unten sind **Anforderungs- und Nachweisregister**, keine konkurrierende Abarbeitungsreihenfolge. Neue Umsetzungstätigkeiten werden zuerst hier als einzeln prüfbare Task ergänzt und mit dem Anforderungsregister verknüpft. **Kein Task gilt als erledigt, weil er nur dokumentiert wurde.**

**Abarbeitung:** immer die erste offene Aufgabe wählen, deren Voraussetzungen erfüllt sind; nötige Entscheidungen, Dateien und Tests konkret dokumentieren; erst nach überprüftem DoD abhaken. Eine Phase startet erst nach bestandenem Gate der vorherigen Phase. Wo mehrere Teilaufgaben entstehen, unter dem jeweiligen Task Untertasks mit eigenen Akzeptanzkriterien ergänzen, nicht stillschweigend überspringen. Für jedes Ticket erfassen: Status, Abhängigkeiten, Code-/Dokupfade, Testkommando, Ergebnis, Commit und Reviewer. **R0 ist ausschließlich Inventur/Spezifikation; kein Implementierungsstart ohne Freigabe.**

**Interpretation:** `R0-GATE`, `R1-GATE`, `R2-GATE`, `R3-GATE` sind harte Voraussetzungen. Release-Nummern sind Reihenfolge, keine Zeitversprechen. WooCommerce ist R3.1, Voucher Captain R3.2, gemeinsames Gate R3.3, weitere Integrationen R3.4. Free bleibt ohne Partner vollständig nutzbar. Externe LMS-Wettbewerber werden nicht namentlich genannt oder verlinkt.

### R0 – Bestandsaufnahme und umsetzungsreife Architektur

- [x] **R0-01 · Repository- und Versionsbasis erfassen** — erledigt 16.11.2026: `docs/r0/R0-01-baseline.md` (LearnBoating `9f37c77`, Course Captain `5af78c3`, Modulkarte, Quellrechte; Lizenzfrage → `docs/r0/ENTSCHEIDUNGEN.md` E-01)
  - **Voraussetzung:** keine.
  - **Ergebnis/Definition of Done:** LearnBoating-Commit, Course-Captain-Stand, Verzeichnis-/Modulkarte und Quellrechte dokumentiert.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [x] **R0-02 · Originalfunktionen vollständig inventarisieren** — erledigt 16.11.2026: 5 Teilinventuren `docs/r0/R0-02-inventar/A…E` (alle Quell-PHP-Dateien von Plugin, Connectors, Theme vollständig; Schemas, 52 Web- + 78 App-Routen, 55 Tabellen, 7 Cron-Events, 15 CLI-Kommandos). Fachliche Tests nach Prüfabschnitten erfasst; zeilengenaue Testbelege in R0-05.
  - **Voraussetzung:** R0-01.
  - **Ergebnis/Definition of Done:** Jede UI-, PHP-, Daten-, API-, Cron-, Import-, Test- und Commerce-Funktion mit Quellpfad erfasst.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [x] **R0-03 · Jede Originalfunktion klassifizieren** — erledigt 16.11.2026: 835 Einträge in `docs/r0/R0-02-inventar/` (reuse 321, abstract 334, replace 69, exclude 111, **unknown 0**); Übersicht `docs/r0/R0-02-inventar/README.md`; Produktentscheidungen → `docs/r0/ENTSCHEIDUNGEN.md`
  - **Voraussetzung:** R0-02.
  - **Ergebnis/Definition of Done:** Jede Funktion reuse/abstract/replace/exclude_domain_specific; keine unknown.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-04 · R1/R3-Grenze je Funktion entscheiden** — **Vorschlag fertig 16.11.2026:** `docs/r0/R0-04-r1-r3-grenze.md` (724 nicht-exclude-IDs in 37 Bausteinen, Prüfskript ohne Lücke; 25 Kopplungsstellen mit Entkopplungsvertrag); Entscheidung offen → E-05, E-07, E-10, E-14, E-16, E-24…E-27, E-32, E-53, E-54
  - **Voraussetzung:** R0-03.
  - **Ergebnis/Definition of Done:** Neutrale Kernfunktionen R1, konkrete Woo/Voucher/Ticket-Adapter R3; keine Pflichtabhängigkeiten.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-05 · Golden-Master-Fixtures festlegen** — **Spezifikation fertig 16.11.2026:** `docs/r0/R0-05-golden-master.md` (190 GM-Tests mit Quelltest:Zeile, Fixture `cc-fixture-neutral-v1`, 14 Determinismusregeln, 27 Lücken). Referenzlauf und Freigabe offen → E-02, E-55, E-56
  - **Voraussetzung:** R0-03.
  - **Ergebnis/Definition of Done:** Quelltest, Fixture, erwartete Resultate und Regression pro neutralem Bereich referenziert.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-06 · Datenmodell-ADR finalisieren** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-06-datenmodell-adr.md` (52 Entitäten, 39+1 Tabellen, 5 CPTs, Migrationsregeln); Freigabe offen → `docs/r0/ENTSCHEIDUNGEN.md` E-47…E-52
  - **Voraussetzung:** R0-02.
  - **Ergebnis/Definition of Done:** Entitäten, CPT/Tabellen, Indizes, Uniques, Revisions-/Migration-/Rollbackregeln beschlossen.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-07 · RBAC-/IDOR-Matrix finalisieren** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-07-rbac-idor-matrix.md` (8 Fachrollen, 4 neue WP-Rollen, 24 Caps + 24 Meta-Caps, Matrix über REST/Admin/AJAX/Medien/Cron/CLI/Export, 96 Negativtests RBAC-001…096, LB-Abgleich 14 stärker/11 schwächer); Entscheidung offen → E-78…E-84
  - **Voraussetzung:** R0-06.
  - **Ergebnis/Definition of Done:** WP-Capabilities, Rollen, Objekt-Scope, REST/Admin/Medien/CLI/Export-Matrix beschlossen.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-08 · Kurs-, Activity- und Prüfungsverträge finalisieren** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-08-kurs-activity-pruefungsvertraege.md` (Kursstruktur, Activity-Provider-Interfaces, Fragetypen, Bewertung ohne festen Schwellenwert, Smarttrainer-Algorithmus belegt, Versuchsautomat, Snapshots); Entscheidung offen → E-67…E-77
  - **Voraussetzung:** R0-06.
  - **Ergebnis/Definition of Done:** Activity-Provider, Bewertung, Completion/Mastery/Competency, Snapshots, Versionen spezifiziert.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-09 · Identity-/Seat-/Grant-Zustandsautomaten finalisieren** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-09-identity-seat-grant-automaten.md` (Claim-Vertrag mit gehashten Tokens, Grant-Automat G-01…15 ohne Wiederaufleben, effektiver Zugang, Seat-Automat, Refund-Matrix RF-01…19, Races R-01…13, GRANT/SEAT/ID-Tests); Entscheidung offen → E-112…E-117
  - **Voraussetzung:** R0-06, R0-07.
  - **Ergebnis/Definition of Done:** Claim, Quellen, Mehrfachgrants, Suspend/Expire/Refund, Race Conditions und Privacy festgelegt.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-10 · Content-Import und Medien-Sicherheit finalisieren** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-10-import-medien-sicherheit.md` (Paketformat cc-package v1, Rechtemodell, Importautomat mit Aktivierungsphase, geschützte Auslieferung mit Range, Consent-Adapter, IMP-001…032/MED-001…026); Entscheidung offen → E-03, E-18, E-45, E-62…E-66
  - **Voraussetzung:** R0-06.
  - **Ergebnis/Definition of Done:** Paketformat, Rechte, Dry-run, Revisionen, Rollback, Protected Files, Consent spezifiziert.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-11 · Free-/Pro-Matrix und UX-Flows finalisieren** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-11-free-pro-matrix-ux.md` (Matrix BS-01…36 mit Lizenzende-Verhalten, Admin-Screens A-01…18, Lernerscreens L-01…21, 5 UX-Flows, Konflikte C-01…18); Entscheidung offen → E-93…E-102
  - **Voraussetzung:** R0-03, R0-08.
  - **Ergebnis/Definition of Done:** Jede Funktion und jeder Admin-/Learner-Screen eindeutig einer Edition/Phase zugeordnet.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-12 · Tests, Gates und R1-Backlog abgleichen**
  - **Voraussetzung:** R0-05, R0-07, R0-08, R0-09, R0-10, R0-11.
  - **Ergebnis/Definition of Done:** 55 FLOW + 12 CORE, Traceability, Akzeptanzfälle und Risiken ohne unzugeordnete Pflichtfunktion.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-13 · Support- und Betriebskonzept**
  - **Voraussetzung:** R0-07,R0-09.
  - **Ergebnis/Definition of Done:** Supportrollen, sichere Diagnose, Datenkorrektur, Audit- und Eskalationsregeln samt Testfällen dokumentiert.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-14 · Nichtfunktionale Qualitätsziele** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-14-nichtfunktionale-ziele.md` (12 Operationsbudgets, 5 Lastprofile, Matrix, Robustheit RB-01…08, NFR-001…018); ungemessene Zielwerte; Entscheidung offen → E-57…E-61
  - **Voraussetzung:** R0-02,R0-06.
  - **Ergebnis/Definition of Done:** Performancebudgets für große Fragenbanken/gleichzeitige Prüfungen, Lastprofile, Browser-/PHP-/WP-Matrix und Messverfahren festgelegt.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-15 · Barrierefreiheit und Internationalisierung spezifizieren**
  - **Voraussetzung:** R0-11.
  - **Ergebnis/Definition of Done:** WCAG-Ziel, Tastatur-/Screenreader-/Kontrastfälle, Übersetzbarkeit, RTL, Zeitzonen, Datums-/Zahlenformate und Sprachwechsel definiert.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-16 · Backup, Recovery und Deployment planen** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-16-backup-recovery-deployment.md` (Backup-Umfang und Konsistenzstufen, Regeln I-1…I-5, Runbooks für Upgrade/Rollback/Restore/Teil-Restore/Uninstall, Staging-Ausgangsmodus, Secret-Inventar, REC-001…025); Entscheidung offen → E-85…E-92
  - **Voraussetzung:** R0-06,R0-10.
  - **Ergebnis/Definition of Done:** Upgrade-/Rollback-/Restore-Runbook, Backup-Konsistenz, Staging, Secret-Handling und Recovery-Tests beschrieben.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-17 · Threat Model und Datenschutz-Folgenabschätzung prüfen**
  - **Voraussetzung:** R0-07,R0-09.
  - **Ergebnis/Definition of Done:** Datenflüsse, Schutzbedarf, Abuse Cases, Prüfungsintegrität, Consent, Lösch-/Exportfristen und DPIA-Bedarf bewertet.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-18 · Release- und Qualitätsautomatisierung festlegen**
  - **Voraussetzung:** R0-05,R0-12.
  - **Ergebnis/Definition of Done:** CI-Matrix, PHP-/JS-Checks, Unit/Integration/E2E, SAST, Dependency-Scan, Paket-Signierung und Releaseartefakte definiert.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R0-19 · Öffentliche API- und Erweiterungsverträge inventarisieren** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-19-api-erweiterungsvertraege.md` (Versionierung/Deprecation, 50 REST-Routen, 46+7 Fehlercodes, 14 PHP-Funktionen, 13 Actions, 24 Filter, 3 Shortcodes, 5 Blocks, 15 CLI-Kommandos, Event-Envelope v1 mit 27 Typen, Connector-Registry, Integrationsstatus); Entscheidung offen → E-118…E-120
  - **Voraussetzung:** R0-02,R0-08.
  - **Ergebnis/Definition of Done:** Alle Hooks, Filter, REST-Routen, Shortcodes, Blocks, CLI-Kommandos und geplanten Events mit Versionierung, Auth, Fehlercodes und Deprecation-Policy tabellarisch spezifiziert.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R0-20 · WordPress-Kompatibilität und Konfliktmatrix festlegen**
  - **Voraussetzung:** R0-14,R0-18.
  - **Ergebnis/Definition of Done:** Unterstützte WP-/PHP-/DB-Versionen, Multisite-Grenze, Cache-/Security-/SEO-/Theme-/Woo-Kombinationen und Testinstallationen beschlossen.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R0-21 · Cache-, Nebenläufigkeits- und Konsistenzregeln entscheiden**
  - **Voraussetzung:** R0-06,R0-09.
  - **Ergebnis/Definition of Done:** Invalidierung bei Grants/Refunds/Revisionen, Objektcache, HTTP/CDN-Cache, Locks, Race- und stale-read-Verhalten mit Negativtests spezifiziert.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R0-22 · Kursvalidierung und Veröffentlichungsfreigabe definieren**
  - **Voraussetzung:** R0-08,R0-11.
  - **Ergebnis/Definition of Done:** Fehlende Lektionen, ungültige Fragen, nicht lizenzierte Medien, unvollständige Prüfungsregeln und Preview/Publish-Blocker mit Autoren-UX dokumentiert.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R0-23 · Revisionen laufender Kurse und Lernender entscheiden** — **Entwurf fertig 16.11.2026:** `docs/r0/R0-23-revisionen-laufender-kurse.md` (Matrix 10 Ereignisse × 8 Zustände, Regeln RV-01…21, Grandfathering, Staging-Kopie, Zertifikatsfolgen, REV-001…046); Entscheidung offen → E-103…E-111
  - **Voraussetzung:** R0-06,R0-08.
  - **Ergebnis/Definition of Done:** Kursupdate bei laufendem Versuch, gelöschter Lektion, umsortierten Inhalten, Mastery, Zertifikaten und Migrations-/Grandfathering-Policy spezifiziert.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R0-24 · Anforderungs-Traceability und Task-Definition-of-Ready einführen**
  - **Voraussetzung:** R0-12.
  - **Ergebnis/Definition of Done:** Jeder FLOW/CORE/PAR/LB-Fall einer Task, einem Test und Gate zugeordnet; Template für Inputs, Dateien, Negativfälle, Abnahme, Reviewer und offene Fragen verbindlich.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R0-25 · Mandanten-/Mehrsprachigkeits-/Locale-Grenzen klären**
  - **Voraussetzung:** R0-07,R0-15.
  - **Ergebnis/Definition of Done:** Single-Site-Scope, Multisite-Nichtumfang, Locale-Fallback, Sprachversionen und gemischte Kurs-/Fragensprachen als ADR dokumentiert.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R0-26 · Accessibility- und UX-Abnahme mit Nutzertests planen**
  - **Voraussetzung:** R0-11,R0-15.
  - **Ergebnis/Definition of Done:** Autor-/Lernenden-Referenzflows, Testgeräte, Tastatur-/Screenreader-Prüfungen und echte Usability-Abnahmekriterien spezifiziert.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R0-27 · Settings-Register mit Feldverträgen erstellen**
  - **Voraussetzung:** R0-07,R0-11,R0-24.
  - **Ergebnis/Definition of Done:** Alle Kapitel-43-Felder haben Key, Typ, Scope, Default, Capability, Validierung, Edition, Migration, Audit und Tests.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R0-28 · Global-/Kurs-/Prüfungs-Vererbung spezifizieren**
  - **Voraussetzung:** R0-08,R0-23,R0-27.
  - **Ergebnis/Definition of Done:** Override-Priorität, Snapshots, laufende Versuche, Reset und konfliktfreie Default-Änderung als Zustandsmatrix.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R0-29 · Free-/Pro-Konflikte Gruppen und Zertifikate auflösen**
  - **Voraussetzung:** R0-04,R0-11,R0-27.
  - **Ergebnis/Definition of Done:** Free Seat/Claim und Basisnachweis vertraglich abgrenzen; Pro Gruppen-UX/Designer und Lizenzablauf getestet.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R0-30 · Admin-Menü, Setup und Einstellungs-UX abnehmen**
  - **Voraussetzung:** R0-15,R0-22,R0-26,R0-27.
  - **Ergebnis/Definition of Done:** Menübaum, Tab-Screens, Hilfetexte, Validierungsfehler, Risk-Confirmations und Tastaturabläufe als prüfbare Wireframes.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R0-31 · Datenschutz- und Sicherheitsdefaults für Admin-Settings prüfen**
  - **Voraussetzung:** R0-17,R0-27.
  - **Ergebnis/Definition of Done:** Retention-Entscheidungen, Medien/Consent, Secret-Handling, RBAC, Audit, Export/Reset und Testmatrix freigegeben.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R0-32 · Suite-Konzeptabgleich als Traceability-Matrix**
  - **Voraussetzung:** R0-19,R0-24.
  - **Ergebnis/Definition of Done:** Form/Workflow/Review-Quellen mit Quellkapitel, Course-Relevanz, bestehender Abdeckung, Delta und explizitem Nichtumfang erfasst.
  - **Nachweis:** Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests, Review; erst danach abhaken.

- [ ] **R0-33 · Kurs-SEO und öffentliche/private Grenzen spezifizieren**
  - **Voraussetzung:** R0-20,R0-27.
  - **Ergebnis/Definition of Done:** Katalog/Kurs/Lektion indexierbar nach Rechteklasse; Canonical, Sitemap, Schema, Yoast- und No-SEO-Fallback ohne private Leaks definiert.
  - **Nachweis:** Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests, Review; erst danach abhaken.

- [ ] **R0-34 · Integrationsstatus und Support-Vertrag spezifizieren**
  - **Voraussetzung:** R0-19,R0-21,R0-27.
  - **Ergebnis/Definition of Done:** Provider-ID/Version/Contract, letzter Erfolg, Queue, Correlation-ID, Fehlercode, Retry-Rechte und Datenschutzklasse spezifiziert.
  - **Nachweis:** Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests, Review; erst danach abhaken.

- [ ] **R0-35 · WordPress.org-/Distribution-Readiness festlegen**
  - **Voraussetzung:** R0-18,R0-24.
  - **Ergebnis/Definition of Done:** Plugin-Slug, Readme, Lizenz/Assets, Übersetzungen, Support, Paketbuild, Free/Pro-ZIPs und Update/Restore als Releasecheckliste.
  - **Nachweis:** Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests, Review; erst danach abhaken.

- [ ] **R0-36 · Weitere Suite-Quellen und Vertragsdeltas nachweisen**
  - **Voraussetzung:** R0-32,R0-19.
  - **Ergebnis/Definition of Done:** Pass/Voucher/Affiliate/License/Captain-Pro-Dokumente mit Version/Quellpfad, LMS-Relevanz, vorhandenem Task, offenem Delta und Nichtumfang abgeglichen.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R0-37 · Schema-Upgrade unter Konkurrenz und Ausfall spezifizieren**
  - **Voraussetzung:** R0-06,R0-16,R0-21.
  - **Ergebnis/Definition of Done:** Lock-Strategie, Upgrade-Trigger, idempotente dbDelta-/Backfill-Schritte, unterbrochene Migration, Multisite-Scope und Rollback-Grenzen beschlossen.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R0-38 · Großimporte mit Dry-run, Batches und Resume spezifizieren**
  - **Voraussetzung:** R0-10,R0-16,R0-21.
  - **Ergebnis/Definition of Done:** Validierungsreport pro Zeile/Objekt, Rechte/Provenienz, deduplizierter Import, Batch-Cursor, Timeout/Resume, Rollback und Fehlerklassen definiert.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R0-39 · Free-/Pro-Lizenzclient-Grenze technisch fixieren**
  - **Voraussetzung:** R0-11,R0-19,R0-29.
  - **Ergebnis/Definition of Done:** Core ohne Lizenzbibliothek, Updater, Lizenzserverzugriff; Add-on eigener Build/Namespace, Feature-Flags, Upgrade/Downgrade, Lizenzende und Ausfallverhalten spezifiziert.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R0-40 · Vertrag für finanzielle Suite-Ereignisse abgrenzen**
  - **Voraussetzung:** R0-09,R0-19,R0-34.
  - **Ergebnis/Definition of Done:** Course meldet keine doppelte Provision/Kauftransaktion; Voucher/Order/Grant-Quellen und Fehlercodes/Idempotenz/Refund eindeutig zugeordnet.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R0-GATE · R0-Freigabe:** Alle Pflichtaufgaben dieser Phase abgenommen, offene Blocker dokumentiert und Freigabe protokolliert. Gate A.

### R1 – Standalone Free und vollständige neutrale Parität

- [ ] **R1-01 · Plugin-Bootstrap und Lifecycle**
  - **Voraussetzung:** R0-GATE.
  - **Ergebnis/Definition of Done:** Plugin aktivierbar/deaktivierbar, Mindestversionen, Upgrade/Uninstall-Policy, keine Suite-Pflicht.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-02 · Storage, Migration und Repositories**
  - **Voraussetzung:** R1-01.
  - **Ergebnis/Definition of Done:** Schema versioniert; sichere Install/Upgrade/Backfill-Tests; keine verlorenen Lernstände.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-03 · Capabilities und zentralen Policy-Service**
  - **Voraussetzung:** R1-02.
  - **Ergebnis/Definition of Done:** Alle Server-Einstiege nutzen Objekt-/Site-Rechte; negative IDOR-Tests grün.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-04 · Kurs-/Modul-/Lektionsmodell**
  - **Voraussetzung:** R1-02, R1-03.
  - **Ergebnis/Definition of Done:** Neutrale Kursstruktur, Revisionen, Entwurf/Publikation/Archiv, Zuordnungen.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-05 · WP-native Autorenoberfläche**
  - **Voraussetzung:** R1-04.
  - **Ergebnis/Definition of Done:** Kurs- und Lektionseditor, Preview, Reihenfolge, Validierung und Accessibility.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-06 · Fragenbank und neutrale Fragetypen**
  - **Voraussetzung:** R1-04.
  - **Ergebnis/Definition of Done:** Single/Multi/Open/weitere belegte Typen, Revisionen, Medien und Feedback.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-07 · Activity-Provider und Lernmedien**
  - **Voraussetzung:** R1-04, R1-06.
  - **Ergebnis/Definition of Done:** Versionierte Activity-Verträge, Video/Schritte/Downloads, externe Medien-Consent.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-08 · Prüfungsblueprints und Versuche**
  - **Voraussetzung:** R1-06, R1-07.
  - **Ergebnis/Definition of Done:** Zufalls-/feste Prüfungen, Timer, immutable Snapshots, idempotente Abgabe.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-09 · Bewertung und Ergebnisanzeige**
  - **Voraussetzung:** R1-08.
  - **Ergebnis/Definition of Done:** Richtig/Falsch/Teilpunkte nach belegter Regel, Ergebnis-/Retake-UX, Audit.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-10 · Fortschritt, Completion, Mastery, Competency**
  - **Voraussetzung:** R1-07, R1-09.
  - **Ergebnis/Definition of Done:** Getrennte Zustände, Resume, Revisionsschutz, Datenschutz.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-11 · Karteikarten, Favoriten und Fehlertraining**
  - **Voraussetzung:** R1-06, R1-10.
  - **Ergebnis/Definition of Done:** Alle belegten neutralen Lern- und Wiederholungsfunktionen mit Persistenz.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-12 · Smarttrainer und Wiederholungsplanung**
  - **Voraussetzung:** R1-10, R1-11.
  - **Ergebnis/Definition of Done:** Belegte Algorithmen/Intervalle als neutrale Regeln; deterministische Tests.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-13 · Standalone-Free-Zugänge und Gruppenplätze**
  - **Voraussetzung:** R1-03, R1-04.
  - **Ergebnis/Definition of Done:** Kostenlose/manuelle/zeitgesteuerte Grants, Seat/Claim, Mehrfachzugang, kein Woo.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-14 · Zugriffsschutz und geschützte Inhalte**
  - **Voraussetzung:** R1-13, R1-07.
  - **Ergebnis/Definition of Done:** Page/Block/Shortcode/API/Medien/Download-Gates, Schutz gegen Direktzugriff.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-15 · Import/Export/Dry-run/Rollback**
  - **Voraussetzung:** R1-04, R1-06.
  - **Ergebnis/Definition of Done:** Neutrale Paketpipeline, Provenienz, Rights, Wiederholung, Revisionen, Recovery.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-16 · Lernoberfläche und responsive Navigation**
  - **Voraussetzung:** R1-05, R1-07, R1-08, R1-10, R1-11, R1-12, R1-13.
  - **Ergebnis/Definition of Done:** Kurskatalog, Kursraum, Activity, Prüfung, Fortschritt, Profile, Tastatur/Mobil.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-17 · Datenschutz, Diagnose und Wartungsjobs**
  - **Voraussetzung:** R1-02, R1-14, R1-15.
  - **Ergebnis/Definition of Done:** Export/Eraser, Retention, Cron-Recovery, Audit, Health-Checks, Security.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-18 · R1-Golden-Master- und E2E-Abnahme**
  - **Voraussetzung:** R1-01, R1-02, R1-03, R1-04, R1-05, R1-06, R1-07, R1-08, R1-09, R1-10, R1-11, R1-12, R1-13, R1-14, R1-15, R1-16, R1-17.
  - **Ergebnis/Definition of Done:** Alle neutralen Originalfunktionen nachgewiesen; Free ohne Woo/Suite/Pro, Gates B/C bestanden.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-19 · Mehrsprachigkeit und Accessibility umsetzen**
  - **Voraussetzung:** R1-05,R1-16.
  - **Ergebnis/Definition of Done:** Lernraum/Admin per Tastatur bedienbar, übersetzbare Strings, Locale/RTL/Screenreader- und Responsive-Tests grün.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-20 · Prüfungsintegrität und Wiederaufnahme absichern**
  - **Voraussetzung:** R1-08,R1-09.
  - **Ergebnis/Definition of Done:** Serverzeit/Timeout, Verbindungsabbruch, Doppelabgabe, Browser-Reload, Offline-Grenzen, Replay und Manipulationsschutz getestet.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-21 · Benachrichtigungen und E-Mail-Vorlagen**
  - **Voraussetzung:** R1-13,R1-17.
  - **Ergebnis/Definition of Done:** Einladung, Claim, Ablauf, Abschluss, Fehler und Opt-out mit WP-Mail, Rate-Limits, Datenschutz und Test-Mail-Sink.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-22 · Skalierung und Lasttests**
  - **Voraussetzung:** R1-17,R1-20.
  - **Ergebnis/Definition of Done:** Große Kurs-/Fragenmengen, parallele Prüfungen, Query-/Index-Analyse, Job-Backlog und Performancebudgets nachgewiesen.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-23 · Upgrade-, Restore- und Uninstall-Proben**
  - **Voraussetzung:** R1-15,R1-17.
  - **Ergebnis/Definition of Done:** Migration von Vorversionen, Backup-Restore, Plugin-Deaktivierung, sichere Datenaufbewahrung und explizite Uninstall-Policy getestet.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-24 · Dokumentation, Onboarding und Support**
  - **Voraussetzung:** R1-16,R1-21.
  - **Ergebnis/Definition of Done:** Installationsguide, Admin-/Autoren-/Lernendenhilfe, Troubleshooting, Privacy-Hinweise, API- und Release-Doku fertig.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R1-25 · Cache- und Zustandskonsistenz implementieren**
  - **Voraussetzung:** R1-02,R1-13.
  - **Ergebnis/Definition of Done:** Grant-/Refund-/Kursrevisionen invalidieren Cache sicher; private Antworten nie öffentlich zwischengespeichert; Paralleltests grün.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R1-26 · Kurs-Publish-Validator und Fehlerführung bauen**
  - **Voraussetzung:** R1-04,R1-05,R1-06,R1-08.
  - **Ergebnis/Definition of Done:** Autoren sehen konkrete Blocker/Warnungen; ungültige Kurse/Prüfungen werden nicht veröffentlicht; Preview testbar.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R1-27 · Kursänderungen während laufender Lernprozesse testen**
  - **Voraussetzung:** R1-08,R1-10,R1-15.
  - **Ergebnis/Definition of Done:** Versuche bleiben revisionsfest, Fortschritt bleibt konsistent, archivierte Inhalte und Zertifikate nachvollziehbar.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R1-28 · Öffentliche API-/Block-/Shortcode-Verträge absichern**
  - **Voraussetzung:** R1-03,R1-14,R1-16.
  - **Ergebnis/Definition of Done:** Versionierung, Permissions, Fehlerformate, Sanitization, Deprecation- und Contract-Tests für freigegebene Einstiegspunkte.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R1-29 · Echte Autoren- und Lernenden-Usability-Abnahme**
  - **Voraussetzung:** R1-16,R1-19,R1-26.
  - **Ergebnis/Definition of Done:** Referenzaufgaben durchgespielt; Tastatur, Screenreader, mobile Prüfungen und Fehlerbehebung mit dokumentierten Befunden.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R1-30 · Konflikt- und Interoperabilitätstests**
  - **Voraussetzung:** R1-17,R1-22,R1-28.
  - **Ergebnis/Definition of Done:** Themes, Objektcache, REST-Security, Mail-/Cache-Plugins und unterstützte Versionen in Matrix getestet; Konflikte dokumentiert.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R1-31 · Settings-Storage, Migration und Berechtigungen**
  - **Voraussetzung:** R1-02,R1-03,R0-27.
  - **Ergebnis/Definition of Done:** Versionierte Optionen, Typvalidierung, Capabilities, sichere Defaults, Upgrade- und Negativtests.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R1-32 · Setup-Assistent und globale Einstellungs-Tabs**
  - **Voraussetzung:** R1-05,R1-31,R0-30.
  - **Ergebnis/Definition of Done:** Resumierbarer Wizard, feldnahe Fehler, Tab-Save/Reset, ungespeicherte Warnung, Accessibility.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R1-33 · Kurs-/Prüfungs-Overrides und Snapshot-Policy**
  - **Voraussetzung:** R1-08,R1-27,R1-31,R0-28.
  - **Ergebnis/Definition of Done:** Vererbung, Preview, Änderung laufender Versuche und historische Auswertung getestet.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R1-34 · Rollen-, Datenschutz-, Medien- und Mail-Settings anbinden**
  - **Voraussetzung:** R1-17,R1-21,R1-31,R0-31.
  - **Ergebnis/Definition of Done:** UI und serverseitige Wirkung inklusive Audit, Consent, Opt-out, Redaction und Permissions geprüft.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R1-35 · Gruppen- und Basiszertifikatsverwaltung Free abnehmen**
  - **Voraussetzung:** R1-13,R1-27,R0-29.
  - **Ergebnis/Definition of Done:** Seat/Claim-UI, Basisnachweis, Widerruf/Verifikation, Lizenzunabhängigkeit und E2E-Tests grün.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R1-36 · Admin-Settings-Regression und Migrationstests**
  - **Voraussetzung:** R1-32,R1-33,R1-34,R1-35.
  - **Ergebnis/Definition of Done:** Reset, invalid inputs, Downgrade, WP-Werte-Vererbung, Multisite-Grenzen und Security/Accessibility getestet.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R1-37 · Kurs-SEO und Sichtbarkeit sicher umsetzen**
  - **Voraussetzung:** R1-14,R1-28,R0-33.
  - **Ergebnis/Definition of Done:** Öffentliche Kursseiten SEO-kompatibel; private Prüfungen, Profile, Medien und Grants nicht indexierbar oder per API abrufbar; Yoast-Konflikttests.
  - **Nachweis:** Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests, Review; erst danach abhaken.

- [ ] **R1-38 · Betriebsdashboard mit sicheren Reparaturaktionen**
  - **Voraussetzung:** R1-17,R1-24,R0-34.
  - **Ergebnis/Definition of Done:** Cron/Jobs/Import/Grant-Fehler mit IDs und sicheren Retry/Reconcile-Aktionen, Rollen-/Dedupe-Tests, redigiertem Diagnoseexport.
  - **Nachweis:** Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests, Review; erst danach abhaken.

- [ ] **R1-39 · Distribution- und Erstinstallationsabnahme**
  - **Voraussetzung:** R1-23,R1-24,R1-30,R0-35.
  - **Ergebnis/Definition of Done:** Free-ZIP Clean-Install, Upgrade, WordPress.org-Readme, i18n, Demo ohne Fremdinhalte, Support-Runbook und Deaktivierung geprüft.
  - **Nachweis:** Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests, Review; erst danach abhaken.

- [ ] **R1-40 · Datenbankmigration mit Lock und Wiederaufnahme testen**
  - **Voraussetzung:** R1-02,R1-23,R0-37.
  - **Ergebnis/Definition of Done:** Parallele Admin/Cron/CLI-Upgradeversuche, Crash, Retry, Backfill und Restore ohne doppelte Schemaaktionen oder Datenverlust getestet.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R1-41 · Großimport-Batchverarbeitung mit Fehlerreport**
  - **Voraussetzung:** R1-15,R0-38.
  - **Ergebnis/Definition of Done:** Dry-run vor Commit, Fortschritt/Resume, pro Objekt validierte Fehler, idempotenter Reimport, Rollback und Rechteprüfung E2E.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R1-42 · Parallelitäts- und Datenintegritäts-Lastprofil**
  - **Voraussetzung:** R1-20,R1-22,R1-40.
  - **Ergebnis/Definition of Done:** Gleichzeitige Enrollment-/Seat-/Attempt-/Completion-Operationen gegen echte DB, Unique-Invarianten, Deadlock-/Retry- und Messprotokoll.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R1-GATE · R1-Freigabe:** Alle Pflichtaufgaben dieser Phase abgenommen, offene Blocker dokumentiert und Freigabe protokolliert. Gates B/C.

### R2 – Zusätzliche universelle P0-Funktionen

- [ ] **R2-01 · Private Lernnotizen**
  - **Voraussetzung:** R1-GATE.
  - **Ergebnis/Definition of Done:** Notizen pro Lektion, Suche, Export/Löschung, Rechte- und E2E-Tests.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R2-02 · Text-/Dateiaufgaben**
  - **Voraussetzung:** R2-01.
  - **Ergebnis/Definition of Done:** Sichere Abgabe, Upload, Status, Feedback, Retakes und Objektberechtigung.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R2-03 · Kursvorlagen und neutrale Demos**
  - **Voraussetzung:** R2-01.
  - **Ergebnis/Definition of Done:** Duplikation, Starter-Templates, kein Fremd-/Fachcontent, Importtests.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R2-04 · Verbesserter Builder-Workflow**
  - **Voraussetzung:** R2-02, R2-03.
  - **Ergebnis/Definition of Done:** Direkte Fragenanlage, Preview, Duplikation, Revisionen, Keyboard-UX.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R2-05 · R2-Abnahme und Free-/Pro-Check**
  - **Voraussetzung:** R2-01, R2-02, R2-03, R2-04.
  - **Ergebnis/Definition of Done:** P0-Neuheiten getestet, keine Regression oder vorgezogene Suite-Abhängigkeit.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R2-06 · Zusätzliche Funktionen auf Lizenz- und Datenschutzgrenzen prüfen**
  - **Voraussetzung:** R2-02,R2-03,R2-04.
  - **Ergebnis/Definition of Done:** Notizen/Abgaben/Vorlagen mit Export, Eraser, Rechte- und Datenmigrations-Tests abgeschlossen.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R2-07 · Kursänderungs- und Autorenproduktivität messen**
  - **Voraussetzung:** R2-04,R1-29.
  - **Ergebnis/Definition of Done:** Vorlage, Duplikation und Fragenbearbeitung mit messbaren Autoren-Referenzflows, Fehlerquoten und Regression abgenommen.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R2-GATE · R2-Freigabe:** Alle Pflichtaufgaben dieser Phase abgenommen, offene Blocker dokumentiert und Freigabe protokolliert. R2-Release-Gate.

### R3 – Optionale Connectoren und Pro

- [ ] **R3-01 · Öffentliche Connector-Schnittstelle absichern**
  - **Voraussetzung:** R2-GATE.
  - **Ergebnis/Definition of Done:** Versionierte Events/Commands/Queries, Idempotenz, Outbox, Partnererkennung, Diagnose.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-02 · WooCommerce R3.1 – Mapping und Checkout**
  - **Voraussetzung:** R3-01.
  - **Ergebnis/Definition of Done:** Produkte/Varianten/Orders/Guest/Seats zu Grant-Quellen; HPOS/API-Verträge.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-03 · WooCommerce R3.1 – Refund und Recovery**
  - **Voraussetzung:** R3-02.
  - **Ergebnis/Definition of Done:** Teilrefund/Chargeback/Out-of-order/Reconcile, E2E- und Security-Tests.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-04 · Voucher Captain R3.2 – Reserve/Grant/Commit**
  - **Voraussetzung:** R3-03.
  - **Ergebnis/Definition of Done:** Code-Claim, eindeutiger Einlöseweg, Fehlerkompensation, Dedupe.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-05 · Voucher Captain R3.2 – Konflikt-/Race-Tests**
  - **Voraussetzung:** R3-04.
  - **Ergebnis/Definition of Done:** Parallelität, Doppelgrant, Rückabwicklung, Woo+Voucher-Kombination.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-06 · R3.3 – Gemeinsames Integrations-Gate**
  - **Voraussetzung:** R3-03, R3-05.
  - **Ergebnis/Definition of Done:** Standalone-Free, beide Adapter, Upgrade/Deactivate/Retry, Privacy, Suite UX grün.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-07 · R3.4 – Weitere Suite-Connectoren**
  - **Voraussetzung:** R3-GATE.
  - **Ergebnis/Definition of Done:** Workflow/Form/Review/Pass/Affiliate/License einzeln nach Contracts und Tests.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-08 · Pro-Funktionen und Lizenzverhalten**
  - **Voraussetzung:** R3-GATE.
  - **Ergebnis/Definition of Done:** Funktionsmatrix, Gradebook/Zertifikatsdesigner nach Freigabe; Lizenzablauf ohne Verlust historischer Nachweise.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-09 · R3-Abnahme und Kompatibilitätsmatrix**
  - **Voraussetzung:** R3-07, R3-08.
  - **Ergebnis/Definition of Done:** Versionsfenster, Partnerstatus, Rollback, E2E, Performance, dokumentierte Freigabe.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-10 · Integrations-Monitoring und Support-Recovery**
  - **Voraussetzung:** R3-03,R3-05.
  - **Ergebnis/Definition of Done:** Connector-Health, Fehlerevent-Queue, Reconcile-UI, manuelle Reparatur und Alarmierung ohne Datenleck geprüft.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-11 · Vertrags- und Upgrade-Kompatibilität testen**
  - **Voraussetzung:** R3-06,R3-09.
  - **Ergebnis/Definition of Done:** WP/Woo/Partner-/Pro-Versionen, Deaktivierung, Rollback, Downgrade und Mischversionsfälle getestet.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R3-12 · Event- und Connector-Vertragskompatibilität absichern**
  - **Voraussetzung:** R3-01,R3-06.
  - **Ergebnis/Definition of Done:** Schema-Evolution, Retry/Replay, deaktivierte Partner, Webhook-Signaturen und Versionshandshake durch Contract-Tests nachgewiesen.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R3-13 · Kauf-/Einlöse-UX über Plugin-Grenzen testen**
  - **Voraussetzung:** R3-03,R3-05.
  - **Ergebnis/Definition of Done:** Guest/Claim/Seat/Refund/Voucher-Abläufe ohne Medienbruch, doppelte Mails oder widersprüchliche Zustände E2E abgenommen.
  - **Nachweis:** konkrete Datei/Commit, Testkommando und Ergebnis, Review/Freigabe, Zuordnung zu Konzept/FLOW/CORE/PAR/LB; erst danach abhaken.

- [ ] **R3-14 · Woo-/Voucher-Settings und Partnerdiagnose**
  - **Voraussetzung:** R3-03,R3-05,R1-36.
  - **Ergebnis/Definition of Done:** Nur bei vorhandenem Connector sichtbare Settings; Mapping, Failure-Recovery, Dedupe und Rechte geprüft.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R3-15 · Pro-Admin-Settings und Lizenzrückfall**
  - **Voraussetzung:** R3-08,R1-35.
  - **Ergebnis/Definition of Done:** Pro-Gruppen/Designer/Reminder separat, Lizenzablauf ohne Verlust von Free-Grants oder Nachweisprüfung.
  - **Nachweis:** Feld-/UI-Vertrag, Commit/Datei, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe; erst danach abhaken.

- [ ] **R3-16 · Gemeinsame Suite-Providerstatus-Anzeige**
  - **Voraussetzung:** R3-10,R3-12,R0-34.
  - **Ergebnis/Definition of Done:** Partnerstatus/Vertragsversion/letzte Ereignisannahme, Fehler- und Correlation-ID ohne Cross-Plugin-Datenzugriff und mit Berechtigungschecks.
  - **Nachweis:** Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests, Review; erst danach abhaken.

- [ ] **R3-17 · Workflow-Captain-Fallback und Zuständigkeitsgrenzen**
  - **Voraussetzung:** R3-12,R3-16.
  - **Ergebnis/Definition of Done:** Native Course-Abläufe bleiben bei fehlendem/abgelaufenem Workflow erhalten; keine Doppelaktion, Retry/Disable/Versionstest.
  - **Nachweis:** Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests, Review; erst danach abhaken.

- [ ] **R3-18 · Pro-Add-on-Build und Lizenzclient-Isolation**
  - **Voraussetzung:** R3-08,R3-15,R0-39.
  - **Ergebnis/Definition of Done:** Core-ZIP ohne Lizenzcode; Pro-ZIP mit isolierter Clientbibliothek; mehrere Captain-Add-ons parallel, Lizenzablauf/Serverausfall ohne Free-Datenverlust getestet.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R3-19 · Suite-Vertragsfehler und doppelte Finanzereignisse abnehmen**
  - **Voraussetzung:** R3-03,R3-05,R3-12,R0-40.
  - **Ergebnis/Definition of Done:** Voucher-Reserve/Commit/Release, Teilrefund, doppelte Kauf-/Affiliate-Events, Replay, inkompatible Version und Recovery mit konkreten E2E-Fixtures.
  - **Nachweis:** referenzierter Quellvertrag, Datei/Commit, positive/negative Tests, Review und Freigabe; erst danach abhaken.

- [ ] **R3-GATE · R3-Freigabe:** Alle Pflichtaufgaben dieser Phase abgenommen, offene Blocker dokumentiert und Freigabe protokolliert. R3-Integrations-Gate.

### R4 – Nur nach Nachfrage und eigener Freigabe

- [ ] **R4-01 · Erweiterungsbedarf und Freigabe**
  - **Voraussetzung:** R3-GATE.
  - **Ergebnis/Definition of Done:** Multisite, Gamification, SCORM/xAPI/H5P/LTI und Agency nach Evidenz priorisiert.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R4-02 · Einzelne freigegebene R4-Features umsetzen**
  - **Voraussetzung:** R4-01.
  - **Ergebnis/Definition of Done:** Pro Feature eigener Contract, Security/Privacy, Migration, Tests und Release-Gate.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R4-03 · R4-Dokumentation und Migrationsfreigabe**
  - **Voraussetzung:** R4-02.
  - **Ergebnis/Definition of Done:** Feature-Gate, Update-/Rollback-Anleitung, Datenschutz- und Sicherheitsreview pro freigegebener Erweiterung.
  - **Nachweis:** konkreter Commit/Dateipfad, Test- oder Review-Protokoll und Referenz zu betroffenen Konzept-/FLOW-/CORE-/LB-Anforderungen; erst danach abhaken.

- [ ] **R4-GATE · R4-Freigabe:** Alle Pflichtaufgaben dieser Phase abgenommen, offene Blocker dokumentiert und Freigabe protokolliert. Einzel-Gates je Feature.

---

### Ergänzende Steuerungsregeln für die Umsetzung

- **Gates sind nicht bloße Checkboxen:** R0-GATE verlangt Quellinventar, ADRs, Testplan, Datenschutz-/Sicherheitsentscheidungen, Betriebs- und Qualitätsbudgets. R1-GATE verlangt außerdem Accessibility, i18n, Last-, Upgrade-/Restore- und Supportnachweise. R2-/R3-Gates schließen Integrations-/Privacy-Regression ein.
- **Fehlertoleranz:** Abgebrochene Jobs, Offline-/Netzwerkunterbrechungen, Zeitüberschreitung, Datenbank-Race, Quota/Storage-Full, E-Mail-Ausfall und Plugin-Konflikte als explizite negative Tests erfassen.
- **Release-Sicherheit:** Keine Produktivdaten in CI; Testdaten anonymisieren. Sicherheitskritische Secrets nie in Logs/Exporten; Versions- und Rollbackhinweise pro Release.
- **Reihenfolge:** Ergänzende Tasks sind Teil ihrer Phase und vor dem jeweiligen Gate zu erledigen; ihre Nummer ist eine stabile ID, keine Freigabe zum Überspringen von Abhängigkeiten.

### Expertenreview – verbindliches Task-Splitting vor Implementierung

**Simulierte Rollen:** WordPress-/LMS-Chefarchitekt, LMS-Produktstrategin und Principal Engineering Lead. Beschlüsse: Parität statt Feature-Creep in R1, reale Nutzbarkeit als Abnahme, jedes Arbeitspaket einzeln testbar. Dies ist kein Nachweis einer externen Expertenbeauftragung.

**Definition of Ready je Implementierungs-Task:** (1) konkrete User-Story/Fehlerfall und Nichtumfang; (2) betroffene Codepfade oder begründete Neuanlage; (3) Daten-/API-/Rechtevertrag; (4) positive und negative Testfälle; (5) Migration/Recovery/Accessibility/Privacy, soweit relevant; (6) Abhängigkeiten; (7) geschätzte Größe und Zerlegung, wenn nicht in einem kleinen PR testbar; (8) Reviewer. Unklare Aufgaben vor Bearbeitung in `<ID>.1`, `<ID>.2` usw. aufteilen, mit eigenen Checkboxen und DoDs.

**Definition of Done:** Implementierung, automatisierte Tests, Review, Doku, Security-/Privacy-Check und Regressionsnachweis mit Commitreferenz. Keine „fertig“-Markierung allein wegen Konzeption oder Code-Merge ohne Abnahme.

**Offene Entscheidungen aus der Review:** konkrete WP-Versionen, erlaubte Cache-Konfigurationen, Multi-Locale-Policy, Rollout laufender Kursrevisionen, Mindestumfang öffentlicher APIs und quantitative UX-/Performance-Ziele. Diese Entscheidungen gehören in die genannten R0-Tasks, nicht als ungeprüfte Produktbehauptung.

### Betreiber-Admin-Settings – Expertenbeschluss (Konzept Kapitel 43)

Die Einstellungs-Matrix in Kapitel 43 ist das verbindliche R0-Prüfregister. Neu: `R0-27…31`, `R1-31…36`, `R3-14…15`. **Keine Implementierung und kein Task-Abschluss durch die Review.** Alle Settings müssen einem eindeutigen Scope, Default, Validierungs- und Rechtevertrag folgen; sensible Settings nie ungeprüft exportieren. R1 enthält die Free-Basis für Gruppenplätze und Zertifikatsnachweise; R3 optionale Partner-/Pro-Settings.

### Cross-Concept-Abgleich (Kapitel 44)

Aus den tatsächlich gelesenen `konzept.md`-Dateien von Form Captain, Workflow Captain und Review Captain wurden nur LMS-relevante Muster übernommen: SEO-Zuständigkeiten, Integrations-/Providerstatus, Betriebsreparatur, Distribution und Fallback. Tasks `R0-32…35`, `R1-37…39`, `R3-16…17`. Nicht übernommen: Formular-/Review-/Workflow-Fachfunktionen. Dies ist ein **Konzeptvergleich**, keine Codeverifikation.

### Erweiterter Suite-Quellenabgleich (Konzept Kapitel 45)

Quellen: Pass Captain `docs/OPERATIONS.md`, `docs/TESTING.md`; Voucher Captain `API.md`; Affiliate Captain `docs/CONTRACT.md`, `docs/IMPORT-EXPORT.md`; License Captain `docs/CLIENT-INTEGRATION.md`, `docs/RELEASE-READINESS.md`; Captain Pro `docs/ARCHITECTURE.md`. **Nur übertragbare Muster, keine Implementierungs- oder Kompatibilitätsbehauptung.** Neu `R0-36…40`, `R1-40…42`, `R3-18…19`. Alle Tasks offen.

### Konsistenz- und Abhängigkeitsprüfung (Konzept Kapitel 46)

**Statischer Stand:** 111 eindeutige R-Tasks (R0 40 / R1 42 / R2 7 / R3 19 / R4 3), keine nicht existierenden R-IDs in den ausgewiesenen direkten Voraussetzungen gefunden. **Nicht geprüft:** vollständige fachliche Deduplizierung, transitive Zyklen und Code-/Testabdeckung. Ähnliche Tasks zu Settings, Migration, Integrationsstatus und Release-Evidenz sind als **Spezifikation (R0) → Umsetzung (R1/R3) → Abnahme** zu behandeln, nicht als parallele Implementierungen. Die Konflikte um Gruppen-/Zertifikats-Edition wurden in Kapitel 46 redaktionell aufgelöst; Detailabnahme bleibt R0-29. Neue Tasks nur mit Delta-Beleg und eindeutiger DoD. Nächster Schritt R0-01.

### LearnBoating-Codeabgleich II – vier Service-Dateien (Konzept Kapitel 47)

**Code geprüft, aber nur Teilinventur:** `LearningService.php` (Smarttrainer, Fortschritt, Favoriten, Rate-Limits), `ExamService.php` (Sitzung/Timeout/Abbruch/Fehlerzustände), `ImportService.php` (Preview, Batch, Resume, Rollback, verwaiste Locks), `EntitlementService.php` (Grant-Quellen, Verlängerung, selektiver Entzug). Vollständige Pfade und Methodenbelege in Kapitel 47. Die bestehenden Tasks **R0-01…04, R0-23, R0-38, R1-13, R1-15, R1-27, R1-41, R1-42** müssen die dortigen Einzelverträge ausdrücklich abdecken. **Nicht erledigt:** Vollinventur, genaue Daten-/API-/State-Mapping, Tests und Gate A. Keine zusätzlichen Task-IDs, um Dopplungen zu vermeiden.

### LearnBoating-Codeabgleich III – weitere direkt geprüfte Dateien (Konzept Kapitel 48)

Dateiweise geprüft: Plugin-Bootstrap und Composer, `Plugin.php`, `AccessService.php`, `MediaStorage.php`, `NamedLock.php`, `GroupService.php`, `QuestionPresenter.php`, `ContentRepository.php`, `Settings.php`, `RequestMemo.php`. Referenzmodule und Abnahmedeltas in Kapitel 48. **Verbindliche Nacharbeit innerhalb bestehender R0-01…04:** vollständige rekursive Quellbaum-/Commitinventur, alle REST-/Admin-/Frontend-/Migration-/Cron-/Privacy-/Test-Dateien, Methodenaufrufer, Status-/DB-/Rechte-Mapping und `unknown`-Auflösung. **R1-Nachweise:** Seat-/Invite-Races, Exam-Token/Shuffle, private Medien und Webserver-Konfiguration, Request-Cache-Invalidierung, Cron-/Upgrade-Retry. Code-Suche ohne Treffer ist kein Negativnachweis. Aufgaben bleiben offen, keine zusätzlichen IDs.

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
- [ ] **SUITE-107 License Captain:** Pro-Client/Updater, Status-/Grace-Regeln, signierte Updates, Lizenzserver-Ausfall, Free-Unabhängigkeit und Release-Nachweis.
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
