# R0-04 · R1/R3-Grenze je Funktion

**Status:** Vorschlag zur Abnahme (keine Implementierung, keine Freigabe; Punkte mit „Entscheidung offen“ sind **nicht** entschieden) · **Stand:** 16.11.2026
**Bezug:** TODO R0-04 und LB-AUDIT-005; Konzept Kap. 5, 7, 26, 29, 32, 35, 43, 46; `docs/r0/R0-01-baseline.md`; Inventur `docs/r0/R0-02-inventar/A…E` (835 Einträge, Stand nach Commit `b8c942a` mit INV-D-145…157); Entscheidungsregister `docs/r0/ENTSCHEIDUNGEN.md` (E-xx); Suite-Abgleich `docs/r0/R0-36-suite-vertragsdeltas.md`
**Quelle der Inventur:** `hastla007/learnboating@9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (siehe R0-01). LearnBoating wurde für dieses Dokument nicht erneut gelesen. Alle Aussagen stützen sich auf die Inventurzeilen (INV-…) und die genannten Konzeptstellen.

**Kernzahlen**

| Größe | Wert |
|---|---:|
| Inventureinträge gesamt (A 154 · B 177 · C 199 · D 157 · E 148) | 835 |
| davon `exclude_domain_specific`/`exclude` (entfallen, Kap. 26.2) | 111 |
| davon nicht-exclude (in diesem Dokument genau einem Baustein zugeordnet) | 724 |
| → R1-Bausteine BS-01…BS-26 (Free-Kern; Pro-Erweiterungen erst R3) | 546 |
| → R2-Bausteine BS-27…BS-29 (alle mit „Entscheidung offen“) | 27 |
| → Pro/R3-Bausteine BS-30…BS-36 | 137 |
| → BS-Z „ersetzt durch Fremdsystem/WordPress, kein Bau“ (`replace` mit Phase `exclude`) | 14 |
| Kopplungsstellen R1 → WooCommerce/Suite/Theme/optionales Modul mit Entkopplungsvertrag (Abschnitt 3) | 25 |
| Widersprüche Inventur ↔ Inventur/Konzept (Abschnitt 4) | 12 |

---

## 1. Regeln der Zuordnung

Die Regeln sind aus dem Konzept abgeleitet. Bei widersprechenden Formulierungen gilt die Rangfolge aus Kap. 46.1: jüngere explizite Entscheidungen in Kap. 29, 32, 35, 43 und 46 vor älteren Tabellen.

| Nr. | Regel | Beleg |
|---|---|---|
| **Z-1** | **Fachneutrale Kernfunktion → R1 Free.** R1 ist das Paritätsrelease „Course Captain Free“. Es umfasst den fachneutralen Lernkern, Prüfungen, Fortschritt, Zugänge sowie Import/Export, soweit die Funktion im neutralen Quellumfang vorhanden ist. | Kap. 29.1 (Zeile R1); Kap. 26.1 „Übernehmen“; TODO R1-18 DoD „Alle neutralen Originalfunktionen nachgewiesen“ |
| **Z-2** | **Die Paritätsmatrix entscheidet, nicht der Featurename.** Steht eine Funktion als Pro oder R2 im Konzept, ist sie aber im neutralen LearnBoating-Bestand vorhanden, geht ihr paritätsrelevanter Teil nach R1. Eine neutral klassifizierte Quellfunktion fällt nur durch eine dokumentierte Entscheidung aus der Matrix. | Kap. 29.1 „Präzisierung“ (`konzept.md:808`); Kap. 29.2 Nr. 5 (`konzept.md:816`) |
| **Z-3** | **Free-Basis mit Pro-Erweiterung.** Der neutrale Seat-/Claim-/Grant-Kern und die Basisnachweise (Ausstellung, Widerruf, Verifikation) gehören in R1 Free. Pro bekommt nur Erweiterungen: erweiterte Gruppenorganisation, Zertifikatsdesigner, optionale Erinnerungen. „Free/Pro“ heißt nie, dass die Kernfunktion eine Lizenz braucht. | Kap. 43.1 „Wichtige Editionsauflösung“ (`konzept.md:1298`); Kap. 43.3 „Einordnung“ (`konzept.md:1368`); Kap. 46.1; Kap. 7 „Teams / Gruppen (Free-Basis …)“ |
| **Z-4** | **Pro wird in R3 geliefert.** Pro-Funktionen, der Pro-Add-on-Build und das Lizenzverhalten sind R3-Tasks. Die Inventur-Kennzeichnung „R1-Pro“ ist deshalb in der Planung **R3** (R3-08, R3-15, R3-18). Die Pro-Zusagen aus Kap. 5 bleiben bestehen und werden nicht gestrichen. | Kap. 29.1 (Zeile R3: „Pro und weitere P1-Integrationen“); Kap. 29.2 Nr. 4 (`konzept.md:815`); TODO R3-08, R3-15, R3-18 |
| **Z-5** | **Konkrete Adapter → R3.x.** WooCommerce kommt in R3.1, Voucher Captain in R3.2, das gemeinsame Gate in R3.3. Workflow, Form, Review, Pass, Affiliate und License/Pro kommen in R3.4. Ticket- und Event-Anbindungen sind Suite-/Partner-Connectoren und gehören ebenfalls nach R3.4. | Kap. 32 (Auftraggeberentscheidung), 32.3 Nr. 1–5 (`konzept.md:986`); TODO-Kopf „Interpretation“ (TODO:13); LB-AUDIT-005 |
| **Z-6** | **Keine Pflichtabhängigkeit des Free-Kerns.** Free funktioniert ohne WooCommerce, Voucher, Suite-Plugins, Pro-Lizenz und ohne das Theme. Partner werden nur erkannt, nie vorausgesetzt. Läuft die Pro-Lizenz ab, bleiben Free, die Lernhistorie und die Zertifikatsprüfung erhalten. | Kap. 5 Scope-Regel (`konzept.md:144`); Kap. 7 (`konzept.md:187`); Kap. 32.1 „Optionale Installation“ (`konzept.md:970`); Kap. 43.3 „Plugin-Erkennung … keine Pflichtintegration“ (`konzept.md:1358`) und „Einordnung“; Kap. 35.4/CORE-10; TODO R1-01 („keine Suite-Pflicht“), R1-13 („kein Woo“), R1-18 („Free ohne Woo/Suite/Pro“) |
| **Z-7** | **Course Captain ist System of Record. Schnittstellen sind neutral, Adapter liegen außen.** Im Kern stehen der Grant mit `source_type`/`source_reference` (bzw. `grant_source_key`), die Zustände, die Idempotenz und neutrale Source-Interfaces samt Test-Fake (R1). Die Woo-, Voucher- und Ticket-Abbildungen sind Adapter (R3). Der öffentliche, versionierte Connector-Vertrag wird in R3-01 abgesichert. Seine Grundlage ist das R1-Kernmodell. | Kap. 31.2 (`konzept.md:903`); Kap. 35.2; Kap. 47 („keine `wc_item` im Free-Kern … `source_type`-/`source_id`-Verträge“, `konzept.md:1450`); Kap. 7 Zugangsmodell (Quellen „später `woocommerce`, `voucher`“) |
| **Z-8** | **R2 nur für zusätzliche P0-Funktionen.** Gemeint sind Notizen, Text- und Dateiaufgaben, Vorlagen bzw. Demos und Builder-Komfort (R2-01…R2-07). R2 ist kein Sammelbecken für zurückgestellte Paritäts- oder Betriebsfunktionen. Eine Inventurzeile mit „R2“ ohne passende R2-Task wird deshalb als „Entscheidung offen“ geführt. | Kap. 29.1 (Zeile R2); Kap. 29.2 Nr. 1; TODO R2-01…R2-07 |
| **Z-9** | **Fachgebundenes entfällt ersatzlos.** `exclude_domain_specific` wird keinem Baustein zugeordnet. Für Zeilen mit `replace` → „Aufgabe gehört WooCommerce, Rechtstext-Plugin, Theme o. ä.“ und Phase `exclude` baut Course Captain nichts (BS-Z). Sie werden trotzdem geführt, damit keine nicht-exclude-ID verloren geht. | Kap. 26.1/26.2; Kap. 29.2 Nr. 3; Inventur B Kap. 2.11, D Kap. 8 |
| **Z-10** | **Mischzeilen** (z. B. „R1-Free (Woo-Teil R3)“) kommen in den Baustein ihres **neutralen Kernteils**. Der Adapterteil steht als Kopplungsstelle mit Entkopplungsvertrag in Abschnitt 3. Methodenregel dieses Dokuments, abgeleitet aus Z-6/Z-7. | – |

---

## 2. Konsolidierte Zuordnungstabelle nach fachlichem Baustein

Spalte „Phase/Edition“ ist der **Vorschlag dieses Dokuments** nach Abschnitt 1. „Entscheidung offen“ verweist auf Abschnitt 4/5. Die vollständige ID-Liste je Baustein (Bereiche) steht in Abschnitt 6. Hier sind die Bereiche gekürzt nach Inventurteil angegeben.

### 2.1 R1 – Free-Kern (546 IDs)

| Baustein | Enthaltene INV-IDs (Kurzform) | Anz. | Phase/Edition | Ziel-Task(s) | Begründung / Beleg |
|---|---|---:|---|---|---|
| **BS-01** Plugin-Kernel, Bootstrap, Lifecycle, Uninstall | C-001…006, 010, 014/015, 024…026, 037, 058 | 14 | R1 Free | R1-01, R1-23, R1-39 | Neutraler Lebenszyklus. Woo-Kopfzeilen und der Woo-Abgleich bei der Aktivierung werden ausgelagert (INV-C-001, C-004 → K-10, K-09). Z-1, Z-6 |
| **BS-02** Infrastruktur: Schema/Migration, Locks, Cron/Task-Runner, Caches, Rate-Limit, Idempotenz-Speicher | A-017, 035, 097; B-124, 161; C-012/013, 016…020, 029…036, 047/048, 051…054, 057, 082, 128; D-134, 145/146; E-146, 148 | 34 | R1 Free | R1-02, R1-25, R1-40, R1-42, R1-17 | Rein technisch. Eine einzige Lock-Infrastruktur (B Befund 6, INV-B-161). Rate-Limit- und Idempotenzspeicher sind auch ohne App-API Kernbedarf (C E-C-04, E-05). Der Rate-Limiter wird aus dem App-Namensraum gelöst (INV-D-145 → K-25). Fail-open/-closed: E-46. Z-1 |
| **BS-03** Capabilities, Policy-Service, Request-Sanitizing | C-137; D-032; E-072, 135, 144 | 5 | R1 Free | R1-03 (R0-07) | Generische Rechteprüfung, Objekt-Scope statt Mandanten-RLS (INV-E-135). Z-1 |
| **BS-04** Kursstruktur- und Inhaltsmodell | A-107; C-038, 129; E-126 | 4 | R1 Free; **INV-A-107 Entscheidung offen (W-07)** | R1-04 | Kurs/Modul/Revision neutral. Die Bundle-Relation „Kurs enthält Kurs“ steht in A auf R1, in B auf R2 (W-07) |
| **BS-05** Autoren-Adminlisten (Kurse, Fragen, Medien, Prüfungsregeln) | D-009…013 | 5 | R1 Free | R1-05, R1-06, R1-08 | Kap. 29.1 Präzisierung: Autorenbasis zum Erstellen und Veröffentlichen gehört zu R1 |
| **BS-06** Fragenbank, Fragetypen, Darstellung, Bewertung, Lern-Antwortdienst | A-004…006, 016, 023…026; C-130/131; E-127 | 11 | R1 Free | R1-06, R1-09 | Neutral. Feedback-Sichtbarkeit als Kursregel statt verkaufbarem Feature (E-20) |
| **BS-07** Activities und Lernmedien (Video-Lektion, Untertitel, externe Medien, Lektionstyp-Fähigkeiten) | A-064…070; C-011, 041; D-149; E-131 | 11 | R1 Free | R1-07, R1-34 | Neutral. Theme-Filter `kurswasser_consent_external_hosts` wird neutral ersetzt (K-13). Fähigkeiten über `LessonTypeRegistry` statt SQL über Fachtabellen, Angebote über Adapter (INV-D-149 → K-05) |
| **BS-08** Prüfungsengine (Blueprint, feste Bögen, Versuch, Snapshot, Timer, Offline-Puffer, Bewertung) | A-007, 028, 036…050, 052…056; C-039, 132/133; E-128/129 | 27 | R1 Free | R1-08, R1-09, R1-20, R1-27, R1-42 | Kap. 26.1 „Prüfungsengine, Prüfungs-Snapshots“. Kennzeichen „verifiziert/live“ wird zu „freigegeben/Entwurf“ (E-23) |
| **BS-09** Fortschritt und Read-Models | A-011/012; C-043; E-130 | 4 | R1 Free | R1-10 | Kap. 26.1 „Lernfortschritt, Ergebnisse“ |
| **BS-10** Favoriten, Sammlungen, Fehlertraining, Karteikarten | A-014/015, 018…022, 051, 061/062, 075; E-132 | 12 | R1 Free | R1-11 | Kap. 26.1 „Karteikarten, Favoriten, Fehlertraining“. Ein Karteikartentyp mit zwei Modi (E-21) |
| **BS-11** Smarttrainer und Wiederholungsplanung | A-001…003, 008…010 | 6 | R1 Free | R1-12 | Kap. 26.1 „Smarttrainer, Wiederholungsintervalle“. Gewichte und Intervalle als versionierte Regeln (INV-A-002, A-009) |
| **BS-12** Grant-Kern (Zustände, Reconciler, Source-Interfaces, manuelle Freischaltung, Laufzeit, Ablauf, Admin-Zugänge) | A-029/030, 033/034; B-001, 003…008, 013…018, 020…029, 033, 065; C-045, 158; D-024…026; E-138 | 35 | R1 Free (Woo-/Voucher-Teile R3, siehe K-01…K-04) | R1-13, R1-02, R0-09; Adapterteile R3-02/03/04 | Kap. 31.2, 35.2, 47: Der Kern ist laut B Befund 2 schon shop-neutral angelegt. Woo-gebunden sind nur `wc_item:<id>` und die Spalten `order_id`/`order_item_id`/`sku_snapshot` (Z-7). Granularität offen (E-27) |
| **BS-13** Free-Zugangsregeln: öffentlich, Teilumfang, Drip, Einschreibemodus | A-013, 031/032, 057; B-098; D-027/028, 052 | 8 | R1 Free; **Drip-Edition Entscheidung offen (W-01)** | R1-13, R1-14, R1-33 | Kap. 43.3 „Lektionsfreigabe/Drip … Free“; Kap. 5 nennt Drip als Pro (W-01). Teilumfang R1 Free (E-15) |
| **BS-14** Gruppen, Seat, Claim, Einladung (Free-Basis inkl. Besitzer-Selbstverwaltung) | B-037, 039…054; D-030, 065, 082, 125; E-136 | 22 | R1 Free; Pro-Erweiterung R3-15 | R1-13, R1-35 (R0-29) | Kap. 7, 43.1, 46.1. **Lücke:** In LB entstehen Gruppen nur aus Bestellpositionen (B Befund 3, INV-B-038 → BS-31). Die manuelle Gruppenanlage ohne Kauf muss R1 neu bauen (E-17) |
| **BS-15** Content-Gate, geschützte Medien und Downloads | A-088…096, 098…102, 106, 111/112; C-122…124, 126/127; D-084, 118 | 24 | R1 Free | R1-14, R1-28 | Kap. 7 „Zugang serverseitig in jedem privaten API-/Medien-Request“; Kap. 43.3 „Geschützte Medien“. Angebotsliste aus Woo wird zum Interface (K-05) |
| **BS-16** Content-Package v1, Import/Export, Dry-run, Rollback, Rechte/Provenienz | A-027; C-042, 050, 085…093, 095…113, 115…121, 125, 134/135; D-014/015, 018…023; E-001…011, 019/020, 024…027, 029/030, 036, 118 | 70 | R1 Free | R1-15, R1-41 (R0-10, R0-38) | Kap. 26.1 „Import/Export“. Rechtesperre nur für importierte bzw. fremde Inhalte (E-18) |
| **BS-17** Lernoberfläche (Lernbereich-Router, Lern-App, „Mein Lernen“, Kurs-Hub, Prüfungs-UI, A11y-Muster, sichere Weiterleitung, Rich-Text, Design-Tokens, Kurskatalog-Block) | A-063; B-061, 064, 066, 095; C-009; D-060…062, 064, 067…081, 085/086, 151…154, 157; E-057, 067 | 34 | R1 Free | R1-16, R1-19 | Eigener Lernbereich statt Woo-„Mein Konto“ (INV-C-009 → K-07). Kap. 43.3 „Lernbereich-Permalink /lernen/“. Hart kodierte Kurscodes und `COURSE_PARTS` im Client (INV-D-153) werden durch Datenmodell und Kurs-Einstellungen ersetzt (W-07) |
| **BS-18** Web-REST-API `course-captain/v1` und öffentliche Verträge (Contract-Version, Fehlerkatalog, Idempotency-Key, DTOs, OpenAPI) | C-022/023, 080/081, 179; D-089…101, 105, 112…115, 119…124, 137/138, 148, 150, 156; E-058 | 35 | R1 Free (Client-Varianten zu INV-D-119…124: R2 laut D, siehe W-03) | R1-28 (R0-19) | Ein Namespace, Cookie+Nonce. Einheitlicher Fehlerkatalog ab R1 (E-06). Die Verträge sind client-unabhängig (D E1) |
| **BS-19** Datenschutz und Audit (Exporter/Eraser, Retention, Self-Service, Audit-Service und Audit-Viewer) | C-049, 084, 147…154, 156/157, 160…162; D-033…035, 044; E-069, 133 | 21 | R1 Free | R1-17, R1-34 | Kap. 43.3 Tab „Datenschutz und Sicherheit“ (Free). Audit-Viewer Free (E-19). Woo-Endpunkt „Meine Daten“ wird ausgelagert (K-07) |
| **BS-20** Betrieb und Diagnose (Systemstatus, Health, Restore-Check, Dashboard, WP-CLI) | A-103; B-036; C-027/028, 055/056, 060…064, 163…167, 170, 172, 174/175, 178; D-007/008, 038, 040…043 | 28 | R1 Free | R1-38, R1-17, R1-23 | Kap. 43.3 „Integrationen und Betrieb“. Shop-Punkte als Adapter-Beitrag (K-11) |
| **BS-21** Admin-Menü, Settings-Register, Setup-Assistent, Bereitschaftsprüfung | C-068…070, 072, 074, 077/078; D-001…006, 036/037, 047/048, 050/051, 053; E-060, 065, 145 | 23 | R1 Free | R1-31, R1-32, R1-36 (R0-27, R0-30) | Kap. 43.2/43.3. Menüseite „Mitgliederrabatte“ und Shop-Checks werden Adapter-Beiträge (K-11) |
| **BS-22** E-Mail-Basis (Absender, Vorlagen, Testmail) | C-083; D-045/046; E-070 | 4 | R1 Free | R1-21, R1-34 | Kap. 43.3 „Absender: WordPress erben“. Der Woo-Absender wird entfernt (K-08) |
| **BS-23** SEO, Consent für externe Medien, Theme-/Cache-Interop | D-063, 066, 155; E-059, 063/064 | 6 | R1 Free | R1-37, R1-30, R1-34 | Kap. 43.3 „Öffentliche Kursindexierung“, „Externe Medien: Consent vor Abruf“. Kein eigenes Banner (E-45) |
| **BS-24** Inhaltstypen FAQ (öffentlich), Kurs-FAQ für Eingeschriebene, Lerntipps | A-118…121, 123…135, 138…147; C-021; D-057/058, 131/132; E-134 | 33 | **Entscheidung offen (W-02)**. Vorschlag: Mechanik R1 Free, Starttexte R2-03 (= Empfehlung E-16) | R1-28, R1-14, R1-15; R2-03 | A empfiehlt R1-Free (E-A-05), D empfiehlt R2 (E6). D-056 führt die öffentliche FAQ als „replace → Theme/Drittplugin“ (BS-Z) |
| **BS-25** Tests, Golden Master, QA, CI, Release, Staging | A-149…152, 154; B-168/169, 171, 175; C-188…191, 193, 195/196, 198/199; D-140…144; E-037, 040, 043…048, 078/079, 081…091, 093, 096…103, 106…108, 111…117 | 63 | R1 Free (Woo-Achse und Checkout-Flows erst R3) | R1-18, R1-39, R0-05, R0-18 | CI ohne Woo-Pflicht (INV-E-087). Fake-Grant-Source statt Woo (INV-B-169, E-100). Kap. 26.3 Nr. 5 neutrale Fixtures |
| **BS-26** Multisite-Grenze (Aktivierung je Website, verwaiste Tabellen, Kontolöschung) | C-007/008, 159, 173, 192; E-092, 104 | 7 | R1 Free „bedingt“; **Entscheidung offen (W-11, E-07)** | R0-25, R1-23, R1-30 | C E-C-01: Code übernehmen, offiziell nur Single-Site. TODO R0-25: „Multisite-Nichtumfang“ |

### 2.2 R2 – nur mit Entscheidung (27 IDs)

| Baustein | Enthaltene INV-IDs | Anz. | Phase/Edition | Ziel-Task(s) | Begründung / Beleg |
|---|---|---:|---|---|---|
| **BS-27** Website-Härtungsmodul (Login-Limit, XML-RPC, Header, Registrierungslimit, Gerätecookie) | C-138, 140…146 | 8 | **Entscheidung offen (W-04)**. Inventur: R2, standardmäßig aus (E-C-02/E-10) | keine R2-Task vorhanden | Kein P0-Feature nach Kap. 29.1. Kap. 43.3 „Login-Härtung … keine pauschalen Eingriffe“. LMS-eigene Limits bleiben in BS-02 |
| **BS-28** Token-/Client-API (Gerätesitzungen, Client-Login, Client-Export/-Löschung, Client-Einstellungen, App-Einstellungsspeicher, App-Health) | C-046; D-059, 104, 106…108, 110, 116, 127…129, 135/136, 147 | 14 | **Entscheidung offen (W-03)**. D: R2 (E1); C: R3/R4 (E-C-04) | keine R2-Task; ggf. R4-01 | Kap. 5 „Nicht in v1: native Mobile-Apps“. Kap. 29.1 R2 nennt keine Client-API |
| **BS-29** R2-Ergänzungen: neutrale Starttexte/Vorlagen, Paket-CLI, XLSX-Fragenimport, Kursbeziehung „enthält/empfiehlt“ | A-122; B-068; E-021, 028, 121 | 5 | R2. INV-A-122, E-021, E-121 → R2-03. **INV-E-028 und B-068: Entscheidung offen (W-07, W-12)** | R2-03; für E-028/B-068 keine R2-Task | Starttexte und Vorlagen gehören zu R2-03 (Kap. 29.1). XLSX und Kursbeziehung haben keine R2-Task (Z-8) |

### 2.3 Pro und R3 (137 IDs)

| Baustein | Enthaltene INV-IDs | Anz. | Phase/Edition | Ziel-Task(s) | Begründung / Beleg |
|---|---|---:|---|---|---|
| **BS-30** Ablauf-Erinnerung (Batch-Mail, signierter Abmeldelink, One-Click-Abmeldung, Hinweis bei der Erhebung) | B-069…077; C-076 | 10 | Pro → **R3** (Inventur: „R1-Pro“). **Entscheidung offen (W-05)** | R3-08, R3-15; Free-Teil ggf. R1-21 | Kap. 43.3 „Optionale Erinnerungen … Pro“; E-24. INV-C-076 (Setting) steht in C auf R1-Free. Woo-Kassenhinweis gehört zu BS-31 (K-21) |
| **BS-31** WooCommerce-Adapter R3.1 (Status-Mapping, Sync/Reconcile, Produktzuordnung, Checkout-/Konto-UI, Dispute über Woo-Status, Zustimmungs-Gate, Woo-Privacy, Woo-Tests) | B-002, 009…012, 019, 030…032, 034/035, 038, 062/063, 093, 097, 100…122, 125…128, 131, 133, 172/173, 177; C-059, 065, 075, 139, 155, 168, 187; D-017, 039; E-066, 068, 105, 109, 137 | 62 | R3.1. Edition (Free-Connector oder Pro) **Entscheidung offen (W-06)** | R3-02, R3-03, R3-10, R3-13, R3-14 | Kap. 7, 32.1–32.3. B-019 (abhängige Grants) nur nach E-26. B-093 nur nach E-34. B-103/131 nur nach E-33 |
| **BS-32** Voucher-Captain-Adapter R3.2 (Grant-API v1 für Gutscheine, Reserve/Grant/Commit, Pending-Inbox, Retry, Kanalregeln, Einlöse-UI) | B-055…060, 153…160, 162…167, 170, 174; C-183…186; D-126 | 27 | R3.2 (Kern-Vertrag R1 über BS-12/BS-18) | R3-04, R3-05, R3-19 | Kap. 32.1/32.2 B. Ziel = neutrales Zugangsangebot (E-31). Voucher Captain selbst setzt WooCommerce voraus (R0-36 X1, siehe W-10) |
| **BS-33** Weitere Connectoren R3.4 (Affiliate, Ticket/Events, Connector-Handshake, Contract-Tests) | B-146…152, 176; C-180…182, 197; D-133; E-095 | 14 | R3.4 | R3-07, R3-12 | Kap. 32.3 Nr. 5. Affiliate meldet in v1 keine Course-Verkäufe (E-37) |
| **BS-34** Commerce-Zusätze: Geld-zurück-Garantie, Mitgliederrabatt, Nutzungsereignis | A-104; B-078…089, 099, 123, 129/130; D-031 | 18 | **Entscheidung offen (W-08, W-09)**. Inventur: R3.4 bzw. R3-Pro | R0-40, R3-19 (Ereignisvertrag) | Kein Suite-Connector im Sinne von Kap. 32.3 Nr. 5. E-25, E-32: Rabatt und Garantie nicht übernehmen |
| **BS-35** Pro-Updater und Lizenzclient | C-066/067, 079, 194 | 4 | R3 (Pro) | R3-15, R3-18 (R0-39) | Free nur über WordPress.org-Updates (E-09). Kap. 6 |
| **BS-36** Pro-Zusätze ohne Paritätsbezug (Druck-/PDF-Export von Prüfungsbögen, Trainer-Kurs-Zuordnung) | E-050, 143 | 2 | R3 | R3-08 | Kap. 29.1 R3 „Mehrtrainer“. INV-E-050 hat in der Inventur keine Task-ID |

### 2.4 Ohne Course-Captain-Bau

| Baustein | Enthaltene INV-IDs | Anz. | Phase | Begründung |
|---|---|---:|---|---|
| **BS-Z** Ersetzt durch Fremdsystem bzw. WordPress (Woo-Preise, deutsche Shop-Rechtspflichten nach § 356a/§ 312f/§ 312j BGB und PAngV, OSS, BACS, Gutscheinfeld, Gateway-Profile, öffentliche Website-FAQ-Verwaltung, Tenant-Header) | B-091, 132, 134…143; D-056, 111 | 14 | kein Bau (Z-9) | Klassifikation `replace` mit Phase `exclude`. Inventur B D-5/E-33, D Kap. 8. INV-B-143 dient nur noch als Erklärtext in der Partnerdiagnose R3-14. **INV-D-056 steht im Widerspruch zu BS-24 (W-02)** |

---

## 3. Pflichtabhängigkeits-Prüfung (R1 → WooCommerce/Suite/Theme)

Methode: Alle nicht-exclude-Zeilen mit Phase „R1…“ wurden nach `woo|wc_|WC_|Shop|Bestell|order_|SKU|voucher|Gutschein|ticket|affiliate|partner|suite|kurswasser` durchsucht und die Treffer einzeln bewertet (Kommando in Abschnitt 7). Treffer, die nur Testnamen („Suite“) oder bereits neutrale Ziele betreffen, sind nicht aufgeführt. K-25 betrifft kein Partner-Plugin, sondern das optionale Client-Modul (BS-28); es wurde beim Lesen der Treffer zu INV-D-145 aufgenommen. Jede aufgeführte Stelle ist eine **Kopplung in der Quelle**. Der Entkopplungsvertrag ist Bedingung für R1-13, R1-18 und R1-01 („keine Suite-Pflicht“).

| Nr. | Kopplung in der Quelle | Betroffene IDs | Entkopplungsvertrag (R1-Kern) | Adapterteil (R3) |
|---|---|---|---|---|
| **K-01** | Grant-Referenz `wc_item:<id>`, Spalten `order_id`, `order_item_id`, `sku_snapshot` im Grant und in der Kaufstatus-Ermittlung (`EntitlementService.php:31-34`) | B-013, B-015, C-045, A-033 | `cc_grant` mit `source_type`, `source_reference` und `grant_source_key` = `source_system + source_object + source_line/seat + entitlement_revision` (Kap. 35.2, 31.2, 47). Unique je Quellrevision. Woo-Felder liegen in der Adapter-Meta bzw. -Tabelle. `idempotency_key` eindeutig machen (B Befund 4) | Woo-Positionsreferenz im Adapter (R3-02) |
| **K-02** | Statusabbildung Woo-Bestellstatus → Grant-Sollstatus im Planer; Bestellsicht und Sync-Orchestrierung | B-002, B-003, B-007, B-008, B-009 | Interfaces `GrantSourceAdapter` (snapshot, list) und `SourceStatusMapper` im Kern. Unbekannter Quellstatus bedeutet „nicht ändern“. `FakeSourceAdapter` als R1-Testquelle (INV-B-169, E-100). Diff-Planer `GrantReconciler` ohne Shopzugriff | Woo-Statustabelle, `WooSourceAdapter` (R3-02/03) |
| **K-03** | Woo-Sichten in Repository, Suche und Admin-UI: `forOrder`/`forItem`, Suche nach SKU/Bestellung, Spalten SKU/Bestellung/Gutschein, Verlauf mit Bestellbezug, Aktion „release“ mit Woo-Resync, Sperrbedingung `source === woocommerce` | B-014, B-024, B-026, D-024, D-026, D-034 | Abfrage nach `source_type`/`source_reference`. Spalten, Filter und Verlaufseinträge registriert der Adapter über einen Erweiterungspunkt. Die manuelle Sperre gilt für jede Quelle. „Manuelle Entscheidung aufheben“ ruft ein generisches `source.resync`-Kommando auf, das ohne Adapter nichts tut | Woo-Spalten, `release` → Woo-Resync (R3-03, R3-10) |
| **K-04** | Zusatz-Grants hängen am Basiskurs über SKU-Suffix (Video- und Simulator-Upgrade), Restore-Check mit SKU-Ausnahmen, Client-Kachel per SKU `SIM-PASS-12M` | A-034, B-019, C-064, D-153 | Optionales Datenfeld `depends_on_grant` (nur wenn E-26 positiv entschieden wird). Sonst gibt es im R1-Kern keine Abhängigkeitsregel. Der Restore-Check prüft Grants ohne SKU-Logik | ggf. R3.1 nach E-26 |
| **K-05** | Sperrhinweis und Client-Kursfähigkeiten listen Woo-Produkte (`WooCommerce\Integration::offers`); Angebots-Provider lesen Woo-Zuordnungen | A-095, B-032, B-111, D-149 | Interface `AccessOfferProvider`. Free zeigt ohne Provider Login, Einschreibung bzw. Kontaktweg (INV-B-064) | Woo-Angebote (R3-13) |
| **K-06** | Cache-Invalidierung hängt an Gutschein- und Produkt-Hooks bzw. an SQL-Stringerkennung auf `bp_product_access` | A-097, B-124, C-128 | Neutrale Domain-Events `grant.changed`, `content.changed`. Adapter feuern sie, der Kern hört nur auf eigene Events (R1-25) | Adapter feuert Events |
| **K-07** | Lernbereich „Mein Lernen“ und „Meine Daten“ als WooCommerce-„Mein Konto“-Endpunkte | C-009, C-160 | Eigener Lernbereich-Router (`/lernen/`, Kap. 43.3), Privacy-Self-Service als Block/Shortcode | Woo-„My Account“-Verlinkung (R3-13) |
| **K-08** | Mail-Absender und Anbieterfuß aus WooCommerce-Einstellungen; `wp_mail_from` ersetzt „wordpress@“ durch den Woo-Absender | C-083, D-046 | Absender von WordPress erben, eigene Fußzeile im Mail-Setting (Kap. 43.3 „E-Mails/Absender“) | – |
| **K-09** | Wartungs-Scheduler und Aktivierung enthalten `order_reconcile` und Woo-Abgleich fest verdrahtet; Betriebsdashboard zeigt Bestellabgleich | C-004, C-013, D-042 | Task-Registry: Module registrieren eigene Jobs und Aktivierungsschritte. Der Kern kennt keinen Woo-Job | Reconcile-Job (R3-03/R3-10) |
| **K-10** | Plugin-Kopf `WC requires at least`; Woo-Mindestversion und HPOS-Status in den Requirements | C-001, C-059 | Core-Kopf ohne WC-Angaben und ohne `Requires Plugins` für Partner. Woo-Requirements nur im Adapter | C-059 → BS-31 |
| **K-11** | Settings, Systemstatus und Livegang-Checks enthalten Shop-Teile (Preishinweis, Woo/HPOS, Steuer/Zahlung/Preise); Menü enthält die Woo-abhängige Seite „Mitgliederrabatte“; Einschreibe-Warnung prüft Woo-Sichtbarkeit | D-001, D-036, D-038, D-048, D-051, D-052, C-075 | Menü-, Settings-Tab-, Diagnose- und ReadinessCheck-Registries. Adapter liefern ihre Einträge nur, wenn sie aktiv sind (R3-14). Der Kern-Guard prüft nur den eigenen Einschreibemodus | Woo-Settings, Partnerdiagnose (R3-14, R3-16) |
| **K-12** | Platzhalter `{{agb}}` verweist auf die WooCommerce-AGB-Seite | A-128 | Platzhalter-Register. Die AGB-/Rechtsseite ist eine WordPress-Seitenauswahl im Setting | – |
| **K-13** | Theme-Kopplung: Filter `kurswasser_consent_external_hosts`, Theme-API `window.kwConsent`/Ereignis `kw-consent-change` im Client, Theme-Erkennung und `kw-*`-Klassen im FAQ-Shortcode, Cookie-Banner des Themes | A-069, A-130, C-011, D-155, E-059 | Neutraler Filter `course_captain_external_media_hosts`, „Klick zum Laden“ als Standard, optionale Consent-Plugin-Anbindung (E-45). Theme-neutrale Ausgabe | – |
| **K-14** | Code-Einlösung reicht unbekannte Codes per Filter an das Gutschein-Plugin weiter (0-€-Bestellung) | D-125 (dazu C-183…186, B-055 in BS-32) | R1-Endpunkt löst nur Gruppen-Beitrittscodes ein. Gutscheine laufen über eine registrierte Connector-Fähigkeit. Ist keine registriert, kommt dieselbe neutrale Ablehnung (keine Existenzauskunft) | Voucher-Einlösung (R3-04) |
| **K-15** | Kontolöschung: Grants „ohne Bestellung löschen, mit Bestellung als manuell revoked markieren“ (Kriterium `order_id`) | C-158 | Quellenneutrale Regel: Grants mit externer `source_reference` werden revoked und behalten die Referenz. Grants ohne externe Quelle werden gelöscht. Endgültige Policy in R0-09/R0-17 | Woo-Anonymisierung C-155 (R3) |
| **K-16** | REST-Antworten und Zugangsstatus-DTO tragen Shop-Link (`ENTITLEMENT_REQUIRED` + Shop-Link), Shop-Angebote, Bestell-/Verlängern-Links, `tickets`, `practice_offers`, `money_back_active` | D-091, D-092, D-112, D-148 | Kernantwort ohne diese Felder. Erweiterung nur über `AccessOfferProvider` bzw. registrierte Response-Erweiterungen mit eigenem Schema (R1-28, R0-19) | Woo-, Ticket- und Garantie-Felder (R3) |
| **K-17** | Kurs-Hub mit Ticket- und Praxisangebots-Kacheln | D-072 | Kachel-Registry. Der Kern liefert nur Lernbereiche | Pass/Ticket-Kachel (R3-07) |
| **K-18** | Datenexport mit Zusatzposten Tickets und Geld-zurück; Deinstallation löscht Shop-Meta mit | C-151, C-025 | Export-, Eraser- und Uninstall-Beiträge registriert das jeweilige Modul (Uninstall-Manifest je Modul, INV-C-026) | Connector-Beiträge (R3) |
| **K-19** | Rückkehr nach Login über Woo-Login/-Registrierung | D-067 | WP-Login als Standard. Woo-Formulare hängen sich per Adapter-Hook ein | R3-13 |
| **K-20** | Doppelte Lock-Implementierung im Voucher-Connector (GET_LOCK-Kopie) | B-161 | Ein gemeinsamer DB-Lock-Dienst im Kern (B Befund 6). Connectoren nutzen ihn | – |
| **K-21** | Erhebungshinweis der Ablauf-Erinnerung an der Woo-Kasse; Empfängerfilter „zahlende Käufer“ aus Woo-Bestellungen | B-072, B-077 | Betrifft BS-30 (Pro), nicht R1. Empfängerkreis als Setting. Ohne Kaufquelle keine Mail (B D-9) | Woo-Kasse (R3.1) |
| **K-22** | Gruppenpools entstehen nur aus Bestellpositionen | B-038 (Befund 3) | Seat-Pool mit `source_type` (`manual`, später `woocommerce`/`voucher`). Manuelle Anlage in R1 neu (R1-13/R1-35) | Pool aus Woo-Position (R3-02) |
| **K-23** | Setup-Assistent installiert bzw. empfiehlt Partner-Plugins (Theme-Muster) | E-065 | Nur Erkennung, keine Installation fremder ZIPs, keine stille Aktivierung (Kap. 43.3 „Plugin-Erkennung“) | – |
| **K-24** | CI, Staging und PHPStan setzen WooCommerce, Woo-Stubs und Voucher-/Affiliate-Platzhalter voraus; E2E-Checkout-Flows | E-081, E-082, E-087, E-112, D-142, D-143 | CI-Matrix und Staging ohne Woo-Pflicht. Woo-Achse und Woo-Profil erst ab R3 (D-E-03). Stubs nur im Adapter-Paket | E-105, E-109 → BS-31 |

| **K-25** | Kernsicherheit hängt am optionalen App-Modul: Web-Login, Registrierung, Antwort- und Prüfungsstart-Limit, Gutscheine, Zahlungs-Webhooks und Privacy-Self-Service nutzen `Mobile/RateLimiter` und `ApiException`; die vertrauenswürdigen Proxys liegen in der App-Option `bp_learning_mobile` | D-145, D-146 (dazu D-147 in BS-28) | Rate-Limiter, Client-IP-Ermittlung und Fehlerausnahme in die Kern-Infrastruktur (BS-02), Proxy-Liste in die Sicherheits-Einstellungen (INV-D-037). Verhalten bei Ausfall des Zählerspeichers nach E-46 | App-Settings nur mit Client-API (BS-28) |

**Suite/Pro-Kopplungen im R1-Bestand:** Lizenz- und Update-Code (INV-C-066/067/079/194) liegt in BS-35 (R3), der Free-Kern enthält keinen Lizenzclient (E-09, R0-39). Bei den übrigen Suite-Partnern (Workflow, Form, Review, Pass, Affiliate) gibt es **keine** Kopplung einer R1-Zeile; sie erscheinen nur in BS-33. Offen bleibt die Frage aus R0-36 E1 (= E-38): Sind Course-Ereignisse und Read-API Free (Empfehlung) oder hängen sie an `course.suite_integrations`? Sie berührt nur die Edition des Ereignisvertrags, keine R1-Pflichtabhängigkeit.

**Befund ohne Quellbestand:** Zu Zertifikaten und Basisnachweisen gibt es in A…E keinen Inventureintrag (Suche nach „Zertifikat“ in `R0-02-inventar/` ohne Treffer). Für R1-35 „Basiszertifikat Free“ (Kap. 43.1, 46.1) gibt es also keine Paritätsquelle, es wird neu gebaut.

---

## 4. Widersprüche zwischen Phasenvorschlägen (Vorschlag, Entscheidung offen)

Dieses Dokument **entscheidet keinen** der folgenden Punkte. Die Spalte „Empfehlung“ ist ein Vorschlag. Soweit `ENTSCHEIDUNGEN.md` die Frage schon führt, ist die E-ID genannt.

| Nr. | Widerspruch | Fundstellen | Empfehlung (Vorschlag, Entscheidung offen) | Register |
|---|---|---|---|---|
| **W-01** | **Drip Free oder Pro** | A: INV-A-013, A-032 „R1-Free“. D: INV-D-028 „R1-Free oder R2-Pro (E3)“. Konzept Kap. 5 (`konzept.md:134`) „Drip-/Terminkampagnen“ in der Pro-Spalte, Kap. 6 Entitlement `course.drip_content` (`konzept.md:168`). Kap. 43.3 „Lektionsfreigabe/Drip … Free … Zeitgesteuerte Free-Grants unabhängig von Pro-Marketing“ (`konzept.md:1324`). TODO R1-13 „zeitgesteuerte Grants“. Außerdem: „R2-Pro“ gibt es als Phase nicht (Kap. 29.1) | Einfacher relativer Drip („Modul/Lektion X Tage nach Zugangsbeginn“) **R1 Free** (Z-2, Parität). Absolute Termine, Kampagnen und Abhängigkeitsketten **Pro (R3-08)**. Kap. 5/6 redaktionell präzisieren | E-14 |
| **W-02** | **Öffentliche FAQ, Kurs-FAQ und Tipps: R1 oder R2 bzw. gar nicht** | A E-A-05: alle R1-Free (INV-A-118…147, Starttexte INV-A-122 → R2). D E6: Kurs-FAQ und Tipps R2 (INV-D-057, D-131, D-132, D-082 „R2 (Tipps)“). D-154 (Rich-Text-Rendering) „R1-Free (FAQ/Tipps R2, E6)“. D-056: Verwaltung der öffentlichen FAQ `replace` → „Website-FAQ über Theme/Drittplugin“, Phase exclude. C-021 „nur falls Teil A ein FAQ-Modul übernimmt“. E-134 R1-Free. Konzept: FAQ fehlt in der Übernahmeliste Kap. 26.1. Es gelten aber Kap. 29.1 Präzisierung und 29.2 Nr. 5 (keine stille Streichung). Für FAQ gibt es keine R2-Task (Z-8) | Mechanik (Inhaltstyp, Endpunkt, Zugriffsschutz, Shortcode/Block, Seeder) **R1 Free**, Starttexte R2-03 (wie E-16). Die **öffentliche Website-FAQ** (A-123…132 gegen D-056) ausdrücklich mitentscheiden. Vorschlag: ebenfalls R1, weil sie neutral klassifiziert ist und JSON-LD/SEO bietet (INV-A-131, R1-37). Sonst muss die Streichung als Entscheidung dokumentiert werden | E-16 (öffentliche FAQ dort nicht ausdrücklich) |
| **W-03** | **Mobile-/Token-API: R2, R3/R4 oder nicht** | D E1: Token-Auth als „R2-Baustein“ (INV-D-059, D-104…110, D-116, D-127…129, D-135/136). C E-C-04: „erst nach eigener Produktentscheidung (R3/R4)“, INV-C-046 „R3 (nach E-C-04)“. Kap. 5 „Nicht in v1: native Mobile-Apps“ (`konzept.md:146`). Kap. 29.1: R2 nennt keine Client-API. E-05 legt nur „nicht in R1“ fest | **Nicht R1, nicht R2**: als R4-01-Kandidat zurückstellen, bis ein Client beauftragt ist. Die client-unabhängigen Bausteine (Idempotenz, Rate-Limit, Problem-Details, Range-Auslieferung) bleiben R1 (BS-02, BS-15, BS-18) | E-05 (Phase nach R1 offen) |
| **W-04** | **Website-Härtung als R2-Modul** | C E-C-02 und E-10: „optionales Modul ab R2“ (INV-C-138, C-140…146). Kap. 29.1/Z-8: kein P0-Wettbewerbsfeature, keine R2-Task. Kap. 43.3 „Login-Härtung: WP-/Security-Plugin-kompatibel, keine pauschalen Eingriffe“ | Nicht als R2-Task führen. Entweder **streichen** (WordPress.org-Richtlinien, Konflikte mit Sicherheits-Plugins) oder als **R4**-Kandidat vormerken. LMS-eigene Limits bleiben R1 (BS-02) | E-10 |
| **W-05** | **Ablauf-Erinnerung: „R1-Pro“, R1-Free-Setting oder R1-21** | B: INV-B-069…076 „R1-Pro“ (B D-9: Pro). C: INV-C-076 Setting `expiry_reminder_enabled` „R1-Free“. Kap. 43.3 „Optionale Erinnerungen … Pro“ (`konzept.md:1349`). TODO R3-15 „Pro-Gruppen/Designer/Reminder“. TODO R1-21 DoD nennt aber „Einladung, Claim, **Ablauf**, Abschluss …“ für R1 | Erinnerung **vor** dem Ablauf ist Pro und wird in **R3** geliefert (Z-4; R3-08/R3-15). R1-21 „Ablauf“ als transaktionale Mitteilung **nach** dem Ablauf auslegen (Kap. 43.3 „Transaktionale Ereignisse“). INV-C-076 gehört zu BS-30 | E-24 (R1-21-Auslegung nicht geführt) |
| **W-06** | **Edition der Woo-/Voucher-Connectoren und Bedeutung der Kennzeichnungen „R1-Pro“/„R3-Pro“** | Kap. 5 (`konzept.md:140`) und Kap. 7 Überschrift „Optionaler WooCommerce-Adapter (Pro)“ (`konzept.md:180`), Entitlement `course.commerce`. Kap. 43.3 „WooCommerce/Voucher Captain … **Free Connector R3**“ samt „Einordnung“. D kennzeichnet Woo-/Voucher-Zeilen als „R3-Pro“ (INV-D-017, D-031, D-039, D-125/126). B nennt nur „R3.1 Woo“/„R3.2 Voucher“ | Klären, ob die R3.1/R3.2-Connectoren **Free** (Kap. 43.3, jüngere Entscheidung nach Kap. 46.1) oder **Pro** (Kap. 5/6/7) sind. Vorschlag: Kap. 43.3 folgen (Free-Connector, optional). Pro-Mehrwert nur in erweiterten Commerce-Regeln. Für R0-04 ändert sich nichts: beide Wege sind R3 und keine R1-Pflicht | **neu** |
| **W-07** | **Kursbeziehung „enthält“/Bundle: R1 oder R2** | A: INV-A-107, A-144 „Bundle-/Teilkurs-Relation“ R1-Free (A-107: Kombikurse erben Dateien). D: INV-D-153 Kombikurs → Teilkurse hart im Client (`COURSE_PARTS`), Ziel „Bundle-/Teilkurs-Relation“ R1-Free. B: INV-B-068 Kursbeziehungen R2 (B D-8). E-25: „enthält“ in R2. Kap. 29.1 Präzisierung: Paritätsteile nach R1 | Ein gemeinsames Datenmodell „Kurs enthält Kurs“. Den **paritätsrelevanten Teil** (Inhalte/Dateien eines Teilkurses im Kombikurs freigeben) **R1**. Empfehlungen und Cross-Sell R3.1 (nach E-34/D-8). Wer bei E-25 „R2“ wählt, muss für A-107/A-144 eine Ausnahme von Z-2 dokumentieren | E-25 |
| **W-08** | **Mitgliederrabatt** | B: INV-B-130 „R3.4 sonstige“ (B D-8: „nicht übernehmen; höchstens R3.4“). D: INV-D-031 „R3-Pro (E9)“ (D E9: „optional im Woo-Adapter R3 (Pro)“). E-25: nicht übernehmen, höchstens Woo-Adapter. R3.4 ist laut Kap. 32.3 Nr. 5 für Suite-Connectoren reserviert | Nicht in R3.1 aufnehmen. Höchstens späterer Woo-Adapter-Zusatz nach Nachfrage (R4-01) | E-25 |
| **W-09** | **Geld-zurück-Garantie und Nutzungsereignis** | B: INV-B-078…089, B-099, B-123, B-129 „R3.4 sonstige“ (B D-4: R3.4/R4 vormerken). D E9: „nicht übernehmen“. A: INV-A-104 „R3“, Kern sendet ein Nutzungsereignis (E-A-06). E-32: nicht in R3.1/R3.2, Kern sendet nur das neutrale Ereignis. Auch hier passt „R3.4“ nicht zu Kap. 32.3 Nr. 5 | Garantie-Logik **nicht übernehmen** bzw. R4-01 nach Nachfrage. Das neutrale Ereignis „Lerninhalt genutzt“ in den Ereignisvertrag R0-40/R0-19 aufnehmen. Ob der Kern es schon in R1 sendet, folgt aus E-38 | E-32, E-38 |
| **W-10** | **„Voucher allein“** | B D-3 und E-31: neutrales Zugangsangebot, damit R3.2 ohne R3.1 funktioniert. R0-36 X1: Voucher Captain selbst setzt WooCommerce zwingend voraus (`voucher-captain.php:9`). Kap. 32.1/32.3 „Voucher allein“ | Kein R1-Problem, Free bleibt unberührt. „Voucher allein“ bedeutet „ohne Course-Woo-Connector R3.1“, nicht „ohne WooCommerce“. ENTSCHEIDUNGEN Kap. 7 korrigiert das bereits als Fakt | E-31, R0-36 X1 |
| **W-11** | **Multisite** | C: INV-C-007/008, C-159, C-173, C-192 „R1-Free (bedingt)“ (E-C-01: Code übernehmen, Single-Site offiziell). TODO R0-25 DoD „Multisite-Nichtumfang“. TODO R4-01 „Multisite“. R0-36 D4 (Lizenz je Site) | Technisch korrekte Aktivierung je Website in R1 (BS-26), **ohne** Multisite-Supportzusage. Offizielle Unterstützung R4-01 | E-07 |
| **W-12** | **R2 als Sammelbecken ohne R2-Task** | R2-Kennzeichnung ohne passende R2-Task: INV-E-028 (XLSX, Tasks R1-15/R0-38), D-058 (Taxonomie-Helfer, Task R1-05), B-068 (keine ID), BS-27, BS-28, E-A-02 (d) und E-A-03 (b). Nur A-122, E-021 und E-121 passen zu R2-03 | Jede R2-Zuordnung ohne R2-Task ist ein **Vorschlag**. Entweder eine neue R2-Task mit Delta-Beleg anlegen (Kap. 46.3) oder die Zeile nach R1 (Parität) bzw. R4 verschieben. Vorschlag: E-028 und D-058 nach R1 (Komfort zu R1-15/R1-05), BS-27/BS-28 siehe W-03/W-04 | **neu** |

**Formale Befunde (keine Produktentscheidung):**
- 14 Zeilen sind als `replace` klassifiziert, haben aber die Phase `exclude` (INV-B-091, B-132, B-134…143, D-056, D-111 → BS-Z). Für R0-03 sollte die Klassifikation hier entweder `replace (extern)` heißen oder die Phase ein „kein Bau“ statt `exclude` tragen. D Kap. 8 zählt zwei davon korrekt als „exclude 16 (14 + 2 replace)“.
- In `B-commerce.md:112` (INV-B-005) ist der Pipe in „Position\|Kurs\|Feature“ nicht maskiert. Die Zeile hat dadurch 12 statt 10 Spalten, und spaltenbasierte Zählungen lesen dort „Kurs“ als Klassifikation. Die Prüfung in Abschnitt 7 berücksichtigt das. Korrektur in R0-03 empfohlen (`\|`).
- Mehrere Inventurzeilen tragen Task-Bezüge auf FLOW-/SEAM-/SUITE-IDs statt R-IDs (z. B. INV-D-057, D-131 „FLOW-102“). Das ist für R0-12/R0-24 relevant, nicht für die R1/R3-Grenze.

---

## 5. Entscheidungsbedarf (nur echte Produktentscheidungen)

Diese Fragen blockieren das Einfrieren der R1-Grenze (R0-GATE). Bereits registrierte Fragen sind nur referenziert. Zwei Fragen sind **neu** und sollten in `ENTSCHEIDUNGEN.md` aufgenommen werden.

| Nr. | Frage | Optionen | Empfehlung | Register / blockiert |
|---|---|---|---|---|
| **ED-1** | Drip-Edition (W-01) | (a) Drip komplett Pro · (b) relativer Drip Free, Kampagnen Pro · (c) alles Free | **(b)** | E-14 · BS-13, R1-13, R0-11 |
| **ED-2** | FAQ, Kurs-FAQ, Tipps (W-02), **einschließlich der öffentlichen Website-FAQ** | (a) alle Mechaniken R1 · (b) Kurs-FAQ/Tipps R2, öffentliche FAQ entfällt (D) · (c) Mechanik R1, öffentliche FAQ entfällt | **(a)**, Starttexte in R2-03 | E-16 (öffentliche FAQ ergänzen) · BS-24, BS-Z (D-056) |
| **ED-3** | Phase der Token-/Client-API nach dem „nicht in R1“ (W-03) | (a) R2 · (b) R4-01 nach Beauftragung eines Clients · (c) streichen | **(b)** | E-05 · BS-28 |
| **ED-4** | Website-Härtung (W-04) | (a) optionales R2-Modul · (b) R4-Kandidat · (c) streichen | **(c)** oder (b); nicht (a) ohne R2-Task | E-10 · BS-27 |
| **ED-5** | Ablauf-Erinnerung Pro und Auslegung R1-21 „Ablauf“ (W-05) | (a) Erinnerung Pro (R3), R1-21 = Mitteilung nach Ablauf · (b) Erinnerung Free in R1 | **(a)** | E-24 (R1-21 ergänzen) · BS-30, R1-21 |
| **ED-6** | **neu:** Edition der R3.1/R3.2-Connectoren (W-06) | (a) Free-Connector (Kap. 43.3) · (b) Pro-Funktion `course.commerce` (Kap. 5/6/7) · (c) Connector Free, erweiterte Commerce-Regeln Pro | **(c)** bzw. (a) | **neu** · R3-02…R3-06, R3-08, R0-11 |
| **ED-7** | Kursbeziehung „enthält“ (W-07) | (a) R2 komplett · (b) Paritätsteil R1, Cross-Sell R3.1 | **(b)** | E-25 · BS-04 (A-107), BS-24 (A-144), BS-29 (B-068) |
| **ED-8** | Geld-zurück-Garantie, Mitgliederrabatt, Nutzungsereignis (W-08, W-09) | (a) R3.4 · (b) nicht übernehmen, Ereignis in R0-40 · (c) R4-01 nach Nachfrage | **(b)**, Garantie und Rabatt höchstens (c) | E-25, E-32 · BS-34 |
| **ED-9** | **neu:** Dürfen R2-Zuordnungen ohne R2-Task bestehen (W-12)? | (a) nein: neue R2-Task mit Delta-Beleg oder Verschiebung nach R1/R4 · (b) ja, R2 als Zusatzstufe | **(a)** (Kap. 29.1, 46.3) | **neu** · BS-27/28/29, INV-E-028, D-058 |
| **ED-10** | Zugangsgranularität (Scopes) und abhängige Zusatz-Grants | E-27: je Kurs mit Scopes `view`/`download`/`exam` · E-26: `depends_on_grant` nur bei Upsell-Bedarf | wie E-26/E-27 | E-26, E-27 · BS-12 (A-029/030/034, B-065), K-04 |
| **ED-11** | Multisite-Zusage (W-11) | (a) offiziell nur Single-Site, Code je Website · (b) Multisite unterstützt | **(a)** | E-07 · BS-26, R0-25 |

Nicht hier aufgeführt sind die bereits registrierten Fragen, die die R1/R3-Grenze nicht verschieben: E-01 (Lizenz der Codeübernahme, blockiert jede Wiederverwendung), E-29/E-30/E-33/E-34 (Inhalte von R3.1) und E-38 (Free-Edition des Ereignisvertrags, siehe Abschnitt 3).

---

## 6. Prüfliste „INV-ID → Baustein“

Jede nicht-exclude-ID steht genau einmal. Bereiche „001…006“ schließen beide Enden ein und enthalten nur IDs dieses Bausteins (keine exclude-IDs). Alle nicht genannten IDs sind `exclude_domain_specific`/`exclude` (111).

<!-- PRUEFLISTE-START -->
| Baustein | Anzahl | INV-IDs |
|---|---:|---|
| BS-01 | 14 | INV-C-001…006, 010, 014…015, 024…026, 037, 058 |
| BS-02 | 34 | INV-A-017, 035, 097; INV-B-124, 161; INV-C-012…013, 016…020, 029…036, 047…048, 051…054, 057, 082, 128; INV-D-134, 145…146; INV-E-146, 148 |
| BS-03 | 5 | INV-C-137; INV-D-032; INV-E-072, 135, 144 |
| BS-04 | 4 | INV-A-107; INV-C-038, 129; INV-E-126 |
| BS-05 | 5 | INV-D-009…013 |
| BS-06 | 11 | INV-A-004…006, 016, 023…026; INV-C-130…131; INV-E-127 |
| BS-07 | 11 | INV-A-064…070; INV-C-011, 041; INV-D-149; INV-E-131 |
| BS-08 | 27 | INV-A-007, 028, 036…050, 052…056; INV-C-039, 132…133; INV-E-128…129 |
| BS-09 | 4 | INV-A-011…012; INV-C-043; INV-E-130 |
| BS-10 | 12 | INV-A-014…015, 018…022, 051, 061…062, 075; INV-E-132 |
| BS-11 | 6 | INV-A-001…003, 008…010 |
| BS-12 | 35 | INV-A-029…030, 033…034; INV-B-001, 003…008, 013…018, 020…029, 033, 065; INV-C-045, 158; INV-D-024…026; INV-E-138 |
| BS-13 | 8 | INV-A-013, 031…032, 057; INV-B-098; INV-D-027…028, 052 |
| BS-14 | 22 | INV-B-037, 039…054; INV-D-030, 065, 082, 125; INV-E-136 |
| BS-15 | 24 | INV-A-088…096, 098…102, 106, 111…112; INV-C-122…124, 126…127; INV-D-084, 118 |
| BS-16 | 70 | INV-A-027; INV-C-042, 050, 085…093, 095…113, 115…121, 125, 134…135; INV-D-014…015, 018…023; INV-E-001…011, 019…020, 024…027, 029…030, 036, 118 |
| BS-17 | 34 | INV-A-063; INV-B-061, 064, 066, 095; INV-C-009; INV-D-060…062, 064, 067…081, 085…086, 151…154, 157; INV-E-057, 067 |
| BS-18 | 35 | INV-C-022…023, 080…081, 179; INV-D-089…101, 105, 112…115, 119…124, 137…138, 148, 150, 156; INV-E-058 |
| BS-19 | 21 | INV-C-049, 084, 147…154, 156…157, 160…162; INV-D-033…035, 044; INV-E-069, 133 |
| BS-20 | 28 | INV-A-103; INV-B-036; INV-C-027…028, 055…056, 060…064, 163…167, 170, 172, 174…175, 178; INV-D-007…008, 038, 040…043 |
| BS-21 | 23 | INV-C-068…070, 072, 074, 077…078; INV-D-001…006, 036…037, 047…048, 050…051, 053; INV-E-060, 065, 145 |
| BS-22 | 4 | INV-C-083; INV-D-045…046; INV-E-070 |
| BS-23 | 6 | INV-D-063, 066, 155; INV-E-059, 063…064 |
| BS-24 | 33 | INV-A-118…121, 123…135, 138…147; INV-C-021; INV-D-057…058, 131…132; INV-E-134 |
| BS-25 | 63 | INV-A-149…152, 154; INV-B-168…169, 171, 175; INV-C-188…191, 193, 195…196, 198…199; INV-D-140…144; INV-E-037, 040, 043…048, 078…079, 081…091, 093, 096…103, 106…108, 111…117 |
| BS-26 | 7 | INV-C-007…008, 159, 173, 192; INV-E-092, 104 |
| BS-27 | 8 | INV-C-138, 140…146 |
| BS-28 | 14 | INV-C-046; INV-D-059, 104, 106…108, 110, 116, 127…129, 135…136, 147 |
| BS-29 | 5 | INV-A-122; INV-B-068; INV-E-021, 028, 121 |
| BS-30 | 10 | INV-B-069…077; INV-C-076 |
| BS-31 | 62 | INV-B-002, 009…012, 019, 030…032, 034…035, 038, 062…063, 093, 097, 100…122, 125…128, 131, 133, 172…173, 177; INV-C-059, 065, 075, 139, 155, 168, 187; INV-D-017, 039; INV-E-066, 068, 105, 109, 137 |
| BS-32 | 27 | INV-B-055…060, 153…160, 162…167, 170, 174; INV-C-183…186; INV-D-126 |
| BS-33 | 14 | INV-B-146…152, 176; INV-C-180…182, 197; INV-D-133; INV-E-095 |
| BS-34 | 18 | INV-A-104; INV-B-078…089, 099, 123, 129…130; INV-D-031 |
| BS-35 | 4 | INV-C-066…067, 079, 194 |
| BS-36 | 2 | INV-E-050, 143 |
| BS-Z | 14 | INV-B-091, 132, 134…143; INV-D-056, 111 |
<!-- PRUEFLISTE-ENDE -->

**Summen:** R1 (BS-01…BS-26) 546 · R2 (BS-27…BS-29) 27 · Pro/R3 (BS-30…BS-36) 137 · BS-Z 14 · **zusammen 724** = nicht-exclude-Einträge. Mit den 111 exclude-Einträgen ergibt das **835**.

---

## 7. Nachweis

**Zählung der Inventur (grep):**

```bash
cd /home/user/wp-coursemaster/docs/r0
grep -h '^| INV-' R0-02-inventar/[A-E]-*.md | wc -l                      # 835
for f in R0-02-inventar/[A-E]-*.md; do echo "$f $(grep -c '^| INV-' "$f")"; done   # 154/177/199/157/148
```

**Vollständigkeits- und Eindeutigkeitsprüfung der Prüfliste (Skript, liest dieses Dokument und die Inventur):**

```bash
cd /home/user/wp-coursemaster/docs/r0 && python3 -I - <<'PY'
import re, glob
inv = {}
for f in sorted(glob.glob('R0-02-inventar/[A-E]-*.md')):
    for l in open(f, encoding='utf-8'):
        if l.startswith('| INV-'):
            c = [x.strip() for x in l.replace('\\|', '¦').strip().strip('|').split('|')]
            # erste Zelle ab Spalte 5, die eine Klassifikation ist (fängt INV-B-005 mit unmaskiertem Pipe ab)
            inv[c[0]] = next(x for x in c[4:] if re.match(r'(reuse|abstract|replace|exclude)', x))
nonex = {i for i, k in inv.items() if not k.startswith('exclude')}
doc = open('R0-04-r1-r3-grenze.md', encoding='utf-8').read()
sec = doc.split('<!-- PRUEFLISTE-START -->')[1].split('<!-- PRUEFLISTE-ENDE -->')[0]
seen = {}
for l in sec.splitlines():
    m = re.match(r'\| (BS-\w+) \| (\d+) \| (.+) \|$', l)
    if not m:
        continue
    ids = []
    for grp in m.group(3).split(';'):
        p, rest = re.match(r'\s*INV-([A-E])-(.*)', grp).groups()
        for part in rest.split(','):
            a, _, b = part.strip().partition('…')
            ids += [f'INV-{p}-{n:03d}' for n in range(int(a), int(b or a) + 1)]
    assert len(ids) == int(m.group(2)), m.group(1)
    for i in ids:
        seen.setdefault(i, []).append(m.group(1))
print('Inventar', len(inv), '| exclude', len(inv) - len(nonex), '| nicht-exclude', len(nonex))
print('zugeordnet', len(seen), '| fehlend', sorted(nonex - set(seen)),
      '| exclude zugeordnet', sorted(set(seen) - nonex),
      '| doppelt', [i for i, v in seen.items() if len(v) > 1])
PY
```

Ergebnis am 16.11.2026: `Inventar 835 | exclude 111 | nicht-exclude 724` und `zugeordnet 724 | fehlend [] | exclude zugeordnet [] | doppelt []`. Eine Abweichung (z. B. neue INV-Zeilen) zeigt das Skript als `fehlend` an; dann ist die Prüfliste nachzuziehen.

**Kopplungssuche für Abschnitt 3:** Alle nicht-exclude-Inventurzeilen, deren Phase (Spalte 8) mit „R1“ beginnt, wurden in den Spalten Funktion, Begründung und Zielbaustein nach `woo|wc_|WC_|kurswasser|voucher|gutschein|ticket|affiliate|partner|Bestell|order_|SKU|suite|vcap|Shop` (ohne Groß-/Kleinschreibung) durchsucht. Die 93 Trefferzeilen wurden einzeln gelesen. Reine Testnamen („Suite“) und bereits neutrale Ziele wurden verworfen, der Rest ist in K-01…K-24 zusammengefasst.

**Nicht erfolgt:** keine erneute Lektüre von LearnBoating, keine Codeänderung, kein Commit. Die Zuordnung ist ein Vorschlag. Erst nach den Entscheidungen in Abschnitt 5 kann R0-04 abgehakt werden; R0-11 und R0-29 bauen darauf auf.
