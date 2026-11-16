# R0-02/R0-03 · Teilinventur D – Admin, Frontend, REST-API, Mobile-API

**Status:** Inventur abgeschlossen für Bereich D (keine Implementierung, keine Freigabe) · **Stand:** 16.11.2026
**Bezug:** TODO R0-02, R0-03 (Zuschnitt-Vorschlag für R0-04), Konzept Kap. 2, 9.4, 10, 16, 26, 38, 43, 47, 48
**Quellcommit:** `hastla007/learnboating@9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (siehe `docs/r0/R0-01-baseline.md`), nur lesend ausgewertet.

## 1. Kopf

### 1.1 Bereich

`bootspruefung-learning/src/Admin`, `src/Frontend`, `src/Rest`, `src/Mobile`, `bootspruefung-learning/templates`, `bootspruefung-learning/assets` (JS/CSS; Gerätesimulation nur summarisch), `docs/mobile-api` sowie die zugehörigen Tests unter `bootspruefung-learning/tests` (Integration, E2E, PHPStan-Hilfen).

Nicht in Bereich D (nur als Querverweis genannt, Inventur in anderen Teilen): `src/Learning`, `src/Exams`, `src/Trainers`, `src/Gate`, `src/Commerce`, `src/Faq`, `src/Tips`, `src/ExamGuide`, `src/Infrastructure`, `src/Security`, `src/Settings`, `src/Privacy`. Wo ein Controller aus Bereich D nur einen Service dieser Module aufruft, wird hier die **Schnittstelle** (Route, Screen, Parameter) inventarisiert, nicht der Algorithmus.

### 1.2 Lesetiefe

- **V** = vollständig Zeile für Zeile gelesen.
- **S** = vollständig strukturell gelesen: alle Signaturen, Konstanten, Doc-Kommentare, Fehlercodes, Routen/SQL-Schreibpfade; Rumpf stichprobenartig (Dateien, deren Verhalten durch Kommentare und Signaturen eindeutig belegt ist).
- **K** = Schlüssel-/Strukturliste (reine Text- bzw. Datentabellen).
- **Ü** = summarisch (Fremdcode, Binärdaten, Generate); nur Herkunft, Umfang, Zweck.

Abdeckung: 100 % der Dateien des Bereichs sind erfasst; jede Datei hat mindestens eine Inventarzeile oder ist in einer Sammelzeile enthalten.

### 1.3 Gelesene Dateien mit Zeilenzahl

**`src/Admin` (19 Dateien, 6 711 Zeilen)**

| Datei | Zeilen | Tiefe | Datei | Zeilen | Tiefe |
|---|---|---|---|---|---|
| AccessPages.php | 737 | V | Menu.php | 461 | V |
| AuditPage.php | 688 | V | Notices.php | 286 | V |
| ContentPages.php | 761 | V | PricePage.php | 263 | V |
| CustomerFaqAdmin.php | 344 | V | SiteHealth.php | 290 | V |
| ExamGuidePages.php | 185 | V | SystemStatus.php | 395 | V |
| FaqAdmin.php | 345 | V | TermFaqAdmin.php | 235 | V |
| Glossary.php | 49 | V | Ui.php | 238 | V |
| GoLiveCheck.php | 272 | V | ImportPage.php | 611 | V |
| GoLiveChecklist.php | 252 | V | MailStatus.php | 167 | V |
| GoLiveNotice.php | 132 | V | | | |

**`src/Frontend` (4 Dateien, 1 180 Zeilen):** NoCache.php 132 V · ReturnAfterLogin.php 127 V · Router.php 213 V · Strings.php 708 K (673 Schlüssel, Anfang/Ende im Wortlaut).

**`src/Rest` (8 Dateien, 1 006 Zeilen):** Controllers/ExamGuideController.php 43 V · Controllers/GroupController.php 156 V · Controllers/ImportController.php 41 V · Controllers/LearnerController.php 494 V · Controllers/StatusController.php 55 V · Controllers/TrainerController.php 150 V · NonceRefresh.php 36 V · RestRegistrar.php 31 V.

**`src/Mobile` (31 Dateien, 7 051 Zeilen)**

| Datei | Zeilen | Tiefe | Datei | Zeilen | Tiefe |
|---|---|---|---|---|---|
| Admin/MobileAdminPage.php | 163 | V | Controllers/LearningController.php | 483 | V |
| ApiException.php | 40 | V | Controllers/PrivacyController.php | 328 | V |
| AppLinks.php | 281 | V | Controllers/RadioController.php | 293 | V |
| AppSettings.php | 145 | V | Controllers/SettingsController.php | 74 | V |
| Controllers/AccountController.php | 150 | V | Controllers/TicketController.php | 41 | V |
| Controllers/AuthController.php | 223 | V | Controllers/TipsController.php | 91 | V |
| Controllers/BaseController.php | 275 | V | Controllers/TrainingController.php | 311 | V (Payload-Zeilen 193–241, 281–297 S) |
| Controllers/CouponController.php | 150 | V | Controllers/VoucherController.php | 219 | V (Meldungstexte 164–177 S) |
| Controllers/CourseController.php | 262 | V | Idempotency.php | 165 | V/S |
| Controllers/ExamController.php | 381 | V | Mapper/CourseMapper.php | 373 | S |
| Controllers/FaqController.php | 70 | V | Mapper/MediaMapper.php | 85 | S |
| Controllers/FileController.php | 135 | V | Mapper/QuestionMapper.php | 120 | S |
| Controllers/GroupController.php | 275 | V | MobileApi.php | 505 | V |
| MobileSettings.php | 249 | S | Problem.php | 173 | S (Codetabelle V) |
| RateLimiter.php | 314 | S | TokenAuth.php | 423 | V (Zeilen 1–170 S) |
| WebSession.php | 254 | S | | | |

**`templates` (2 Dateien, 71 Zeilen):** app.php 71 V · .gitkeep 0.

**`assets`**

| Pfad | Zeilen | Tiefe | Bemerkung |
|---|---|---|---|
| admin/admin.css | 78 | Ü | Admin-Stile (Tabellen-Scroll, Badges) |
| admin/import.js | 155 | S | Größenprüfung vor Upload, REST-Schrittimport |
| admin/restricted-block.js | 51 | V | Editor-Block `bootspruefung/zugang` |
| build/app.js | 4 768 | S | Lern-SPA (Router, alle Views, Funktionsindex vollständig) |
| build/app.css | 1 497 | Ü | Design des Lernbereichs |
| build/device-sim-a11y.js | 130 | Ü | ARIA-Ergänzung der Gerätesimulation |
| device-sim/** (26 Dateien, davon JS 10 910 Zeilen; SHA256SUMS 23, SOURCE.txt 2, device-sim.css 0) | 10 935 | Ü | unveränderte Kopie `hastla007/boatingschool@136a243…` (SOURCE.txt) |
| products/*.png (62 Dateien, 2,3 MB) | – | Ü | Shop-Produktbilder Bootsscheine/Funk/Gruppen/Simulator |
| maps/de-states.json (39 KB, eine Zeile) | – | Ü | Bundesländer-Karte für Ausschusssuche |
| build/.gitkeep, src/.gitkeep | 0 | – | – |

**`docs/mobile-api` (6 Dateien, 7 868 Zeilen):** README.md 1 088 S (Abschnitte 1–8, Abschnitt 7 „Offene Punkte“ V) · konzept-original.md 816 Ü · openapi-concept-original.yaml 1 412 Ü · openapi.yaml 4 204 S (72 Pfade abgeglichen) · plugin-concept-original/README.txt 23 V · plugin-concept-original/boatingschool-mobile-api.php 325 Ü.

**`tests` (40 Dateien, 14 536 Zeilen)** – Tiefe S (Kopfkommentar, alle Prüf-Labels/Routen, Referenzen auf Klassen aus Bereich D):

| Datei | Zeilen | Datei | Zeilen |
|---|---|---|---|
| e2e/account-flow.mjs | 171 | integration/learning-review-test.php | 400 |
| e2e/account-setup.php | 121 | integration/membership-test.php | 1 360 |
| e2e/admin-import.mjs | 66 | integration/mobile-api-test.php | 1 912 |
| e2e/checkout-flow.mjs | 158 | integration/multisite-setup.php | 76 |
| e2e/checkout-setup.php | 94 | integration/multisite-test.php | 156 |
| e2e/guide-flow.mjs | 52 | integration/navigation-check-test.php | 272 |
| e2e/guide-setup.php | 14 | integration/operations-test.php | 805 |
| e2e/learning-flow.mjs | 200 | integration/release-ops-test.php | 242 |
| e2e/membership-flow.mjs | 79 | integration/security-test.php | 774 |
| e2e/membership-setup.php | 53 | integration/smoke-test.php | 145 |
| e2e/router.php | 8 | integration/support/FakeOrders.php | 44 |
| e2e/setup.php | 53 | integration/support/guard.php | 13 |
| e2e/trainers-flow.mjs | 210 | integration/support/standalone.php | 43 |
| integration/account-test.php | 502 | integration/uninstall-phase.php | 153 |
| integration/admin-test.php | 1 336 | integration/voucher-grant-test.php | 215 |
| integration/commerce-test.php | 303 | integration/woocommerce-setup.php | 34 |
| integration/content-flow-test.php | 566 | integration/woocommerce-test.php | 1 963 |
| integration/customer-faq-test.php | 629 | phpstan/bootstrap.php | 19 |
| integration/exam-guide-test.php | 179 | phpstan/external-stubs.php | 138 |
| integration/extensions-test.php | 530 | phpstan/wp-cli-stubs.php | 37 |
| integration/faq-test.php | 411 | | |

Hinweis: Die Integrations-„Tests“ sind eigenständige PHP-Skripte gegen eine echte WordPress-Installation (`$check(bool, label)`), keine PHPUnit-Suites; die E2E-Skripte nutzen Playwright ohne Test-Runner. Für Golden Master müssen die Abläufe auf neutrale Fixtures übertragen werden (Konzept Kap. 2: „Tests der alten Anwendung nicht ungeprüft wiederverwenden“).

## 2. Funktionstabelle

Legende Art: UI = Admin-/Lernerscreen, PHP = Logik/Helfer, Daten = Speicher/Option, API = REST/AJAX, Cron = Hintergrund, Import = Inhaltsimport, Test = Testcode, Commerce = Shop/Gutschein/Zahlung. Pfade relativ zu `bootspruefung-learning/`, sofern nicht anders angegeben. „GM“ = vorhandener Quelltest als Golden-Master-Kandidat (Verhaltensreferenz, nicht 1:1 übernehmen).

### 2.1 Admin

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-IDs | GM-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-D-001 | src/Admin/Menu.php:44 | UI | Hauptmenü „Bootsprüfung“ (add_menu_page, Cap `manage_bp_content`) mit 19 Unterseiten und Woo-abhängiger Seite „Mitgliederrabatte“ | abstract | Struktur und Capability-je-Seite übertragbar; Benennung, Reihenfolge und Seitenschnitt folgen Konzept Kap. 10/43.2 | AdminMenuRegistry „Course Captain“ | R1-Free | R0-30, R1-32, R1-38 | integration/admin-test.php (Menu), release-ops-test.php |
| INV-D-002 | src/Admin/Menu.php:83, :104 | PHP | Feste Untermenü-Reihenfolge (ORDER), fremde Seiten werden vor „Protokoll“ einsortiert | reuse | rein technisch, erlaubt Add-ons (Pro) sich einzuhängen | MenuOrder mit Erweiterungspunkt | R1-Free | R0-30 | admin-test.php |
| INV-D-003 | src/Admin/Menu.php:118 | UI | Globale Rückfrage vor Löschen/Widerrufen (`data-bp-confirm`, setzt `bp_confirmed`) | reuse | neutraler UX-Schutz vor Hochrisikoaktionen (Kap. 43.2) | ConfirmAction-Muster (JS + No-JS-Zwischenschritt) | R1-Free | R0-30, R1-38 | admin-test.php (Bestätigung ohne JS) |
| INV-D-004 | src/Admin/Ui.php:47, :55 | PHP | Bestätigung ohne JavaScript: Formularwerte im Transient merken, Zwischenschritt „Bitte bestätigen“ | reuse | Barrierefreiheit/No-JS-Fallback, fachneutral | ConfirmAction (Server-Fallback) | R1-Free | R0-26, R1-19 | admin-test.php |
| INV-D-005 | src/Admin/Ui.php:21, :81, :112, :128, :226 | PHP | Admin-Helfer: Flash-Meldungen (Transient je Nutzer, 60 s), Tabellen mit Handy-Scroll, Status-Badges WCAG-AA-Kontrast, Pager | reuse | generische, barrierearme UI-Bausteine | AdminUi-Komponenten | R1-Free | R1-19, R0-26 | admin-test.php (Ui) |
| INV-D-006 | src/Admin/Ui.php:145, :192 | Daten | Deutsche Bezeichnungen für Status-, Rechte-, Import-, Kurstyp- und Entitätswerte | abstract | Schlüssel enthalten Fachwerte (`chart_task`, `radio_*`, `navigation_tasks`, Prüfungsausschüsse) | i18n-Labelregister mit neutralen Enum-Werten | R1-Free | R1-19 | – |
| INV-D-007 | src/Admin/ContentPages.php:65 | UI | Übersicht: aktives Paket, letzter Import, Bestand je Inhaltsart (gesamt/auslieferbar/Entwurf/durch Rechte gesperrt/archiviert), offene Punkte, Produktzuordnungsfehler, Glossar | abstract | Dashboard-Idee neutral; Zählbereiche (Navigation, Praxis, Funk), Seekarten-/Navigationswarnungen fachgebunden | Admin-Dashboard „Übersicht“ (Kap. 16.3) | R1-Free | R1-38, R1-32 | admin-test.php („Bestand: Spalten ergeben die Gesamtzahl“) |
| INV-D-008 | src/Admin/ContentPages.php:114 | PHP | Teure Dateiprüfung „Medien ohne Datei“ 1 h im Transient, Invalidierung über Stempel (Anzahl/Max-ID/updated_at) | reuse | generisches Cache-Muster mit Invalidierung | MediaIntegrityCheck | R1-Free | R1-25, R1-38 | operations-test.php |
| INV-D-009 | src/Admin/ContentPages.php:175 | UI | „Kurse & Module“: nur lesende Liste mit auslieferbaren/zugeordneten Fragen, Prüfungsregel, Bögen, Navigation, Praxis, Link zum Lernbereich | replace | LB ist reiner Content-Browser über Import; Course Captain verlangt WP-native Bearbeitung (Kap. 1, R1-05) | Kursliste + Kurseditor | R1-Free | R1-04, R1-05 | content-flow-test.php |
| INV-D-010 | src/Admin/ContentPages.php:212, :223 | UI | Fragenliste mit Filtern (Modul, Typ, Status, Rechte, Suche Kennung/Nr./Text), 50 je Seite | abstract | Filter neutral; „amtliche Nr.“ und Typ `chart_task` fachgebunden | Fragenbank-Liste | R1-Free | R1-06, R1-05 | admin-test.php |
| INV-D-011 | src/Admin/ContentPages.php:291 | UI | Fragendetail: aktuelle Fassung, Antworten mit Markierung, Erklärungen, Fassungshistorie, Zuordnung zu Modulen/Bögen, Auslieferbarkeit | abstract | Revisionsanzeige neutral (R0-23); Felder `official_number`, `content_role` fachgebunden | Fragenansicht/Revisionen im Frageneditor | R1-Free | R1-06, R0-23 | content-flow-test.php |
| INV-D-012 | src/Admin/ContentPages.php:344 | UI | Medienliste mit Vorschau, Verwendung (Fragen/Praxis), Datei vorhanden/fehlt, Filter „ohne Datei“ | abstract | Medienintegrität neutral; Spalte Praxis fachgebunden | Medienübersicht | R1-Free | R1-07, R1-38 | admin-test.php |
| INV-D-013 | src/Admin/ContentPages.php:386, :558 | UI | Prüfungsregeln: Zeitlimit, Fragen je Modul, Bestehensgrenze/Teile, „verifiziert“ → Prüfungsmodus vs. Übungsmodus; Sammelfreigabe mit Pflichtbegründung | abstract | Blueprint-Anzeige neutral; „verifiziert/amtlich“ als Fachbegriff entfällt (Kap. 26.2) | Prüfungseditor: Blueprint + Veröffentlichungsstatus | R1-Free | R1-08, R0-08, R0-22 | admin-test.php („Regelwerk: aktueller Stand vorausgewählt“) |
| INV-D-014 | src/Admin/ContentPages.php:442, :456, :540 | UI | Rechte/Freigabe: Rechte-Status je Gruppe (Katalog/Quelle/Kurs), lokale Abweichung mit Begründung, Sammelfreigabe „ungeklärt → lizenziert“, letzte Freigaben aus Protokoll | abstract | Lizenz-/Provenienzsperre je Inhalt neutral sinnvoll (R0-38); Gruppierung nach Fachkatalogen entfällt | ContentRights (Status + Override + Auslieferungssperre) | R1-Free (Entscheidung E7) | R0-10, R0-38, R1-15 | admin-test.php („Sammelfreigabe der Rechte“) |
| INV-D-015 | src/Admin/ContentPages.php:612, :728 | PHP | Override-Handler (`bp_override`): Rechte-/Verifiziert-Werte für IDs oder Gruppe, Zwischenschritt ohne JS, Audit | abstract | Override-Mechanik neutral; Feld `verified` fachgebunden | ContentOverride-Service | R1-Free | R0-38, R1-15 | admin-test.php |
| INV-D-016 | src/Admin/ContentPages.php:514, :576 | UI | Seekarten (BSH): Upload geschützt, Lizenz bestätigen/zurückziehen, löschen | exclude_domain_specific | amtliche Seekarten-Lizenz (Kap. 26.2 „Karten-/Spezialmedien“) | – (geschützte Dateien generisch über INV-D-118) | exclude | – | – |
| INV-D-017 | src/Admin/ContentPages.php:45 | Commerce | Verwaiste Woo-Produktzuordnung entfernen (`bp_mapping_remove`) | abstract | Woo-Adapter-Diagnose neutral, aber Commerce | Woo-Adapter: Mapping-Diagnose | R3-Pro | R3-02, R3-14 | woocommerce-test.php |
| INV-D-018 | src/Admin/ImportPage.php:57 | Import | Paket-Upload (ZIP) mit Erkennung `post_max_size`-Überschreitung vor Nonce, Fehlercodes, Endungsprüfung, Löschen der Temp-Datei | reuse | sichere Upload-Behandlung, fachneutral | ImportSource „Upload“ | R1-Free | R1-15, R0-10 | admin-test.php, e2e/admin-import.mjs |
| INV-D-019 | src/Admin/ImportPage.php:99 | Import | Import aus Server-Ablageordner (ZIP oder entpackter Ordner, strenge Namensprüfung, danach löschen) | reuse | Großpakete ohne Browser-Upload (R0-38) | ImportSource „Ablageordner“ | R1-Free | R1-15, R0-38, R1-41 | admin-test.php |
| INV-D-020 | src/Admin/ImportPage.php:131 | Import | Import über https-Link (wp_safe_remote_get, ≤3 Weiterleitungen, Größenlimit, optional SHA-256) | reuse | SSRF-/Integritätsschutz fachneutral (Kap. 9.4) | ImportSource „URL“ | R1-Free | R1-15, R0-10 | admin-test.php |
| INV-D-021 | src/Admin/ImportPage.php:158 | Import | Aktionen je Import: Vorschau (in Teilschritten), Freigeben mit Notiz, Verwerfen, Verwerfen + aktiven Stand wiederherstellen, erneut prüfen, Rollback | reuse | Zustandsautomat des Imports (Kap. 47); UI-Hülle neutral | Import-Assistent | R1-Free | R1-15, R1-41, R0-38 | admin-test.php („abgebrochene Importe, Mischstand“) |
| INV-D-022 | src/Admin/ImportPage.php:222, :313, :408, :440, :518 | UI | Importliste, Batch-Detail (Manifest, Schema, Vorschau-/Ergebnistabelle, Rechte im Paket, Warnungen/übersprungene Zeilen), Warnungen veraltet/Mischstand/hängend, Protokoll mit Filtern | abstract | Ablauf neutral; Warnungscodes (`ubi_erg_missing`, `chart_task_without_solution`, `praxis_elwis_missing` …) fachgebunden | Import-Detailseite mit neutralem Warnungskatalog | R1-Free | R1-15, R1-41 | admin-test.php, e2e/admin-import.mjs |
| INV-D-023 | src/Admin/ImportPage.php:31; assets/admin/import.js | UI | Clientseitige Größen-/Endungsprüfung, Import in REST-Teilschritten mit deutscher Fortschritts- und Fehlertextausgabe (Netz, Timeout, HTTP, ungültige Antwort, Sitzung) | reuse | robuste Langläufer-UI, fachneutral | ImportRunner-JS | R1-Free | R1-15, R1-41 | e2e/admin-import.mjs |
| INV-D-024 | src/Admin/AccessPages.php:48, :170 | UI | „Zugänge“: Suche (E-Mail/Name/ID/SKU), Filter Kurs/Status/Quelle/Bestellung, Detail mit Quelle, Laufzeit, manueller Entscheidung, Gutscheindaten, Verlauf | abstract | Entitlement-Verwaltung neutral; Spalten SKU/Bestellung/Gutschein gehören in Adapter | „Lernende“ › Zugänge | R1-Free (Woo-/Gutschein-Spalten R3) | R1-13, R1-05, R0-09 | commerce-test.php, membership-test.php |
| INV-D-025 | src/Admin/AccessPages.php:222, :269 | UI | Manuelle Freischaltung: Nutzer (E-Mail/Login/ID), Kurs, Funktionen, Enddatum (vorbelegt aus Kurslaufzeit, „ohne Ablauf“ nur ausdrücklich), Pflichtbegründung, Eingaben nach Fehler erhalten | abstract | Grant-Quelle `manual` neutral (Kap. 7); Laufzeit-Vorbelegung aus Shopkatalog fachgebunden | ManualGrant-Formular | R1-Free | R1-13, R0-09 | commerce-test.php |
| INV-D-026 | src/Admin/AccessPages.php:314, :337 | UI | Zugang ändern: Enddatum setzen, aktivieren/aussetzen/widerrufen, manuelle Entscheidung aufheben (Shop-Status gilt, Woo-Resync) | abstract | Statusübergänge neutral (Kap. 7 Statusliste); „release“ ist Woo-spezifisch | Grant-Statuswechsel (Admin) | R1-Free (release R3) | R1-13, R0-09, R3-03 | commerce-test.php |
| INV-D-027 | src/Admin/AccessPages.php:347, :479 | UI | Kostenlose Bereiche: Regel „ganz kostenlos“/„Teilumfang n“ je Kurs+Funktion mit Ablaufdatum und Pflichtbegründung | abstract | Free-/Vorschauregeln neutral; Funktionsschlüssel fachgebunden | AccessRule „öffentlich/Vorschau“ | R1-Free (E4) | R1-13, R1-14 | membership-test.php, e2e/membership-flow.mjs |
| INV-D-028 | src/Admin/AccessPages.php:396, :495 | UI | Zeitversetzte Freischaltung (Drip): Modul bzw. Videokapitel X Tage nach Zugangsbeginn | abstract | neutrales Drip-Muster | AccessRule „Drip“ | R1-Free oder R2-Pro (E3) | R1-13, R1-33 | membership-test.php |
| INV-D-029 | src/Admin/AccessPages.php:444, :459 | Commerce | „Schnupper-Paket anlegen“ (fester Testumfang: 10 Fragen/Modul, 1 Bogen, erste Video-/Navigations-/Praxisaufgabe) | exclude_domain_specific | fest verdrahteter Bootskatalog; neutraler Ersatz ist INV-D-027 | – | exclude | – | admin-test.php |
| INV-D-030 | src/Admin/AccessPages.php:530, :605 | UI | Gruppenlizenzen (Admin): Suche, Detail (Plätze belegt/gesamt, Umfang, Bestellung, Einladungslink), Mitglieder entfernen mit Begründung, Status aktivieren/aussetzen/beenden, Verlauf | reuse | neutrale Seat/Claim/Grant-Basis (Kap. 43.1) | Gruppen (Admin) | R1-Free | R1-13, R1-35, R0-29 | membership-test.php |
| INV-D-031 | src/Admin/AccessPages.php:631, :663 | Commerce | Mitgliederrabatte: Rabatt-% für Nutzer mit aktivem Zugang, je Kurs, „gilt für“-Gruppe | abstract | Woo-Rabattlogik, nicht LMS-Kern | Woo-Adapter: Lernenden-Rabatt | R3-Pro (E9) | R3-02, R3-14 | woocommerce-test.php |
| INV-D-032 | src/Admin/AccessPages.php:690 | PHP | Gemeinsamer Guard: Cap `manage_bp_entitlements` + `check_admin_referer` je Aktion, 403 | reuse | Muster „Capability + Nonce je admin-post“ | AdminActionGuard | R1-Free | R1-03, R0-07 | security-test.php |
| INV-D-033 | src/Admin/AuditPage.php:42, :102, :138 | UI | Protokoll: Filter Zeitraum, Bereich/Ereignis, Nutzer (auch gelöschte), Bezug Zugang/Gruppe/Bestellung; 50 je Seite; nur `manage_options`; keine E-Mails/IPs | abstract | Audit-Viewer neutral; Bereiche „Zahlungskonflikte/Seekarten/Prüfungsausschuss“ entfallen | AuditLog-Viewer | R1-Free (E8) | R1-17, R1-38, R0-17 | admin-test.php („ohne Administratorrechte: Protokollseite gesperrt“) |
| INV-D-034 | src/Admin/AuditPage.php:235, :288 | UI | „Verlauf“ je Zugang/Gruppe (Zusammenführung Bestellung, manuelle Freischaltung, Gutschein, Gruppe) | abstract | Objektverlauf neutral; Bestell-/Gutscheinverknüpfung in Adaptern | GrantHistory | R1-Free | R1-13, R1-17 | admin-test.php |
| INV-D-035 | src/Admin/AuditPage.php:383, :493, :527 | Daten | Klartext für ~90 Ereignisse, Begründungen und Kontextfelder (kein JSON in der UI) | abstract | Ereigniskatalog muss neutral neu definiert werden | AuditEventCatalog | R1-Free | R1-17, SUITE-003 | admin-test.php |
| INV-D-036 | src/Admin/Menu.php:250, :343 | UI | Einstellungen (eine Seite, Bereiche als Sprunganker): Zugang ohne Shop, Ablauf-Erinnerung, Untertitel, Sprachausgabe Funk, Markenname, Preishinweis, Prüfungsanmeldung, Datenschutz-Automatik, Deinstallation, Betrieb (Update-Prüfung) | abstract | Felder teils neutral, teils fachgebunden; CC verlangt getrennte Tabs mit eigenem Speichern (Kap. 16.2, 43.2) | Settings-Tabs (Allgemein, Lernen, Einschreibung, E-Mails, Datenschutz, Erweitert) | R1-Free | R0-27, R1-31, R1-32 | admin-test.php |
| INV-D-037 | src/Admin/Menu.php:366, :392, :404 | UI | Bereich „Sicherheit“: vertrauenswürdige Proxys (IP/CIDR) für Anmelde-/Einlöse-Limits, Proxy-Warnung, Audit | reuse | Client-IP-Ermittlung ist Sicherheitsgrundlage aller Rate-Limits | Settings › Datenschutz & Sicherheit › Proxys | R1-Free | R0-17, R0-31, R1-34 | security-test.php (RateLimiter hinter Proxy) |
| INV-D-038 | src/Admin/Menu.php:147, :216 | UI | Systemstatus: Version, Update, Schema, Fremdschlüssel(-fehler), Multisite-Restetabellen, Woo/HPOS, PHP/WP, Client-IP-Hinweis, Backup-Checkliste + Prüfung nach Wiederherstellung, Tabellenliste | reuse | Diagnose neutral (Kap. 16.3 Systemstatus); Shop-Teile ausgliedern | Systemstatus-Seite | R1-Free (Shop-Teil R3) | R1-38, R1-17, R0-16 | smoke-test.php, release-ops-test.php |
| INV-D-039 | src/Admin/Menu.php:415; src/Admin/SystemStatus.php:21, :43, :59, :141 | Commerce | Shop-Status: Gastbestellungen, Brutto-/Steuerwarnung, Kleinunternehmer-Erkennung, Woo-Grundeinstellungen (Sprache, IBAN-Prüfziffer, Adresse, Mailformat, Absender), Zahlungsarten-Profile, Express-Knopf-Warnung (§ 312j BGB) | abstract | Woo-Betriebsprüfungen; DE-Recht als konfigurierbare Prüfgruppe | Woo-Adapter: Partnerdiagnose | R3-Pro | R3-14, SEAM-105 | woocommerce-test.php |
| INV-D-040 | src/Admin/SystemStatus.php:166 | API | `collect()` – maschinenlesbarer Systemstatus (auch für REST `/status`) | reuse | Diagnose-Datenvertrag | StatusCollector | R1-Free | R1-38, R0-19 | smoke-test.php (`/bootspruefung/v1/status`) |
| INV-D-041 | src/Admin/SystemStatus.php:216, :329, :340 | PHP | Speicher-Selbsttest: Testdatei je privatem Ordner per HTTP abfragen (exposed/ok/unknown/private), 12 h Cache, nginx-Regel, Hinweis `…_PRIVATE_DIR` | reuse | Schutz privater Pakete/Medien unabhängig vom Webserver (Kap. 9.4) | StorageSelfTest | R1-Free | R1-14, R0-10, R1-38 | security-test.php, admin-test.php |
| INV-D-042 | src/Admin/SystemStatus.php:272 | Cron | Wartungsanzeige: Migration fällig (Schreib-API 503), stündlicher Lauf, Bestellabgleich (Lauf/Rückstand/Wiederholungen), Monitor-URL, Zeitzone, Drop-ins, Client-IP, letzte Cron-Fehler | abstract | Betriebsdashboard neutral; Bestellabgleich gehört zum Woo-Adapter | Betriebsdashboard „Jobs“ | R1-Free (Abgleich R3) | R1-38, R1-17, R1-40 | operations-test.php |
| INV-D-043 | src/Admin/SiteHealth.php:44, :161 | PHP | Website-Zustand: Debug-Abschnitt (ohne Pfade/Geheimnisse) und Tests Migration, Speicher, Objekt-Cache | reuse | WP-native Diagnose, fachneutral | SiteHealth-Integration | R1-Free | R1-17, R1-38 | release-ops-test.php |
| INV-D-044 | src/Admin/SiteHealth.php:244, :252 | PHP | Vorschlagstext Datenschutzerklärung aus belegten Speicherorten (Lernstand, App-Sitzungen, Login-Zähler, Cookie, Protokoll 4 Jahre, Erinnerung) | abstract | Muster neutral; Inhalte (Funk/Navigation, App) anpassen | PrivacyPolicyContent | R1-Free | R1-17, R0-17 | release-ops-test.php |
| INV-D-045 | src/Admin/MailStatus.php:21, :97, :127, :152 | UI | Mailversand: Absender, Versandweg (SMTP/pre_wp_mail/mail()), SPF/DMARC-DNS, Testmail mit Fehlertext | reuse | Zustellbarkeit für Lernbenachrichtigungen neutral | Settings › E-Mails › Testmail | R1-Free | R1-21, R1-38 | admin-test.php (MailStatus) |
| INV-D-046 | src/Admin/MailStatus.php:37, :46 | PHP | `wp_mail_from`-Filter: „wordpress@“ durch Woo-Absender ersetzen | replace | CC erbt Absender aus WP (Kap. 16.2); Woo-Bezug gehört nicht in Free | Absenderauflösung über WP-Einstellungen | R1-Free | R1-21 | admin-test.php |
| INV-D-047 | src/Admin/Notices.php:57, :86, :108, :192, :217, :242 | UI | Gemeinsame Admin-Hinweise: Bündelung, Typen, Wegklicken „forever/snooze/none“ mit signierter URL je Nutzer, Fehler nie dauerhaft, max. 50 Einträge, AJAX + No-JS | reuse | generisches, sicheres Notice-Framework | AdminNoticeCenter | R1-Free | R1-38, R0-30 | operations-test.php, release-ops-test.php, woocommerce-test.php |
| INV-D-048 | src/Admin/GoLiveCheck.php:29 | PHP | Livegang-Ampel mit Filter `bootspruefung_golive_checks` | abstract | Bereitschaftsprüfung neutral (Kap. 16.1 Schritt 7); Einzelprüfungen meist Shop/Recht | ReadinessCheck-Registry | R1-Free | R1-32, R1-38 | woocommerce-test.php |
| INV-D-049 | src/Admin/GoLiveCheck.php:56, :79, :126, :157, :221 | Commerce | Einzelprüfungen Rechtstexte/Platzhalter (Theme Kurswasser), Lernprodukte, Widerrufsbutton § 356a BGB, Bestellmails § 312f BGB, Schnupper-Paket | exclude_domain_specific | Theme- und Bootskatalog-gebunden; Woo-Rechtsprüfungen ggf. im Adapter neu | (R3-14 Partnerdiagnose, neu formuliert) | exclude (Woo-Teil R3) | R3-14 | woocommerce-test.php |
| INV-D-050 | src/Admin/GoLiveCheck.php:69, :198, :236, :251 | PHP | Prüfungen Monitor-Schlüssel, Prüfungsregelwerke im Prüfungsmodus, Zugangsmodus, Paket aktiv/kein Mischstand | abstract | neutral formulierbar (Monitor, veröffentlichte Prüfungen, Einschreibungsmodus, Importzustand) | ReadinessCheck-Einträge | R1-Free | R1-32, R1-38 | operations-test.php |
| INV-D-051 | src/Admin/GoLiveChecklist.php:30, :138, :179, :208 | UI | Livegang-Checkliste: Auto-Prüfungen + abhakbare Punkte (Rechtstexte, Steuer, Mail, Zahlung, Staging, Backup, Preise) mit Name/Datum/Audit, geprüfter Zustand Testmail/Backup | abstract | Checkliste neutral; Punkte Steuer/Preise/Zahlung shopbezogen | Setup-Assistent „Bereitschaft“ | R1-Free (Shop-Punkte R3) | R1-32, R1-39 | admin-test.php (GoLiveChecklist) |
| INV-D-052 | src/Admin/GoLiveNotice.php:31, :59, :92, :123 | UI | Warnung „Shop sichtbar, aber alle Angemeldeten lernen kostenlos“ mit Ein-Klick-Umstellung auf „nur Regeln/Freischaltungen“; Ampel auf Woo-Sichtbarkeitsseite | abstract | Kern: Warnung bei widersprüchlichem Einschreibungsmodus; Woo-Sichtbarkeit nur im Adapter | EnrollmentModeGuard | R1-Free (Woo-Teil R3) | R1-13, R3-14 | woocommerce-test.php (GoLiveNotice) |
| INV-D-053 | src/Admin/Glossary.php:16 | Daten | Feste Admin-Begriffe (Zugang, Funktion, Umfang, Teilumfang, Laufzeit, Aussetzen, Widerrufen, Rechte-Status, Regelwerk …) | abstract | Begriffsdisziplin übertragbar, Inhalte neutral neu | Glossar/Statussprache (SEAM-004) | R1-Free | R1-24, SEAM-004 | admin-test.php (Glossary) |
| INV-D-054 | src/Admin/PricePage.php:40, :147, :210 | Commerce | „Preise“: Katalogpreise je Boots-SKU (Theorie/Komplett/Upgrade, Pässe, Gruppen, Simulator), Abgleich in Woo mit Schutz von Handänderungen, Sale-Konflikten, Veröffentlichen | exclude_domain_specific | fest definierter Bootsprodukt-Katalog (Kap. 26.2 „Fachgebundene Kursprodukte“) | Woo-Adapter: Produkt↔Kurs-Mapping (ohne eigenen Preis-Editor) | exclude | R3-02 (Ersatz) | admin-test.php (Preiseingabe) |
| INV-D-055 | src/Admin/ExamGuidePages.php:32, :115, :167 | UI | Prüfungsanmeldung: Reiter Prüfungsausschüsse (DMYV-Liste, lokale Einträge mit Quelle) und Anleitungen | exclude_domain_specific | amtliche Prüfungsanmeldung (Kap. 26.2) | – | exclude | – | exam-guide-test.php |
| INV-D-056 | src/Admin/FaqAdmin.php:28, :201, :216, :234 | UI | Öffentliche FAQ (CPT): Spalten Nr./Rubrik/★ Theme, Rubrikfilter, Metabox, Start-FAQs importieren, FAQ-Seite mit Shortcode anlegen | replace | Marketing-FAQ der Website mit Boots-Startinhalten und Theme-Kopplung; kein LMS-Kern | – (Website-FAQ über Theme/Drittplugin) | exclude | – | faq-test.php |
| INV-D-057 | src/Admin/CustomerFaqAdmin.php:31, :185, :204, :239 | UI | Kunden-FAQ (CPT, nur für Kunden eines Kurses): Schein-/Unterkategorie, gemeinsame FAQs, Start-FAQ-Import mit Update-Bericht | abstract | kursgebundene Info nur für Eingeschriebene ist neutral; Taxonomie „Schein“ und Startinhalte fachgebunden | Kurs-FAQ/Kursinfo für Eingeschriebene | R2 (E6) | R2-03 (Vorlagen), FLOW-102 | customer-faq-test.php |
| INV-D-058 | src/Admin/TermFaqAdmin.php:40, :72, :161, :199 | PHP | Gemeinsame Taxonomie-Bausteine: Menü offen halten, Platzierung oberste Ebene/Unterkategorie, Term-Meta Schlüssel/Reihenfolge, Redaktionshinweis | reuse | WP-Admin-Muster, fachneutral | TaxonomyAdmin-Helfer | R2 | R1-05 | faq-test.php |
| INV-D-059 | src/Mobile/Admin/MobileAdminPage.php:33, :52, :65, :79, :91 | UI | „Mobile App“ (`manage_options`): App an/aus, Tenant-Kennung, Markenfarbe, Support-Mail, Web-Sitzungsübergabe, iOS/Android-IDs, Mindestversionen, Store-Links, Server-Prüfung Authorization-Header (Loopback), Sitzungen eines Kunden widerrufen | abstract | nur nötig, wenn eine Token-API ausgeliefert wird; „Bootsschule/Tenant“ entfällt | Settings › Erweitert › API-Clients/Token-Sitzungen | R2 (E1) | R0-19, R1-28 | mobile-api-test.php |

### 2.2 Frontend, Templates, Assets

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-IDs | GM-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-D-060 | src/Frontend/Router.php:81, :110, :132 | PHP | Lernbereich `/bootsscheine/` per Rewrite (`bp_app`, `bp_path`), Filter `bootspruefung_base_path`, Permalinks nur bei Versionswechsel neu schreiben | abstract | Pfad konfigurierbar `/lernen/` (Kap. 1, 43.3) | LearningAreaRouter | R1-Free | R1-16, R1-37, R1-32 | operations-test.php (Router), membership-test.php |
| INV-D-061 | src/Frontend/Router.php:142; templates/app.php:10, :41 | UI | App-Hülle im aktiven Theme (Block- und klassische Themes), Login-/Registrierungskarte mit Rückkehr, Gruppeneinladung für Gäste, `<noscript>`-Hinweis | abstract | Theme-Integration neutral; Fallback ohne JS ist nur Hinweis (Kap. 10 verlangt aussagekräftigen Fallback) | LearningShell-Template | R1-Free | R1-16, R1-19 | e2e/learning-flow.mjs |
| INV-D-062 | src/Frontend/Router.php:153 | PHP | Asset-Einbindung und `bpLearning`-Konfiguration (REST-Root, Nonce, Login/Logout, Basis, Gruppenanzahl, Kontolink, Kursbilder-Filter, Locale, i18n) | abstract | Konfigvertrag neutral; Felder `voice`, `deviceSim`, `maps` fachgebunden | FrontendBootConfig | R1-Free | R1-16, R1-28 | e2e/learning-flow.mjs |
| INV-D-063 | src/Frontend/Router.php:123, :196 | PHP | `noindex,nofollow` für den Lernbereich, Seitentitel „Kurs – Marke“, Body-Klasse | abstract | privater Bereich nicht indexieren; öffentliche Kursseiten separat SEO-fähig (Kap. 21) | LearningArea-SEO-Regeln | R1-Free | R1-37, R0-33 | – |
| INV-D-064 | src/Frontend/Router.php:209 | UI | Shortcode `[bootspruefung]` „Zum Lernbereich“ | abstract | neutraler Einstiegs-Shortcode/Block | Shortcode/Block „Mein Lernen öffnen“ | R1-Free | R1-28 | – |
| INV-D-065 | src/Frontend/Router.php:47 | UI | Gast-Vorschau einer Gruppeneinladung (Gruppe, Umfang, Status, freie Plätze) vor Anmeldung | reuse | Einladungs-UX neutral | GroupInvitePreview (Gast) | R1-Free | R1-35, SEAM-106 | membership-test.php, e2e/membership-flow.mjs |
| INV-D-066 | src/Frontend/NoCache.php:41, :62, :97 | PHP | Kein Seiten-Cache für personalisierte Seiten (Lernbereich, Widerruf, geschützte Inhalte): DONOTCACHEPAGE, nocache_headers, LiteSpeed, Aktion `bootspruefung_nocache`; nur Kopfzeilen bei REST/AJAX | reuse | verhindert Cache-Leaks zwischen Nutzern (R0-21) | PersonalizedNoCache | R1-Free | R1-25, R0-21, R1-30 | security-test.php (NoCache) |
| INV-D-067 | src/Frontend/ReturnAfterLogin.php:20, :50, :65, :77 | PHP | Rückkehr zur Ausgangsseite nach Login/Registrierung (Param + HttpOnly-Cookie 1 Tag, nur eigene Ziele, Unterordner-sicher, Woo-Login/Registrierung) | reuse | Einschreibe-/Einladungsfluss neutral | ReturnAfterLogin | R1-Free | R1-16, SEAM-106 | membership-test.php (8 Prüfungen) |
| INV-D-068 | src/Frontend/Strings.php:13 | Daten | 673 übersetzbare UI-Texte der Lern-SPA mit Platzhaltern und `_one`-Einzahl | abstract | Mechanik neutral; ca. 40 % der Schlüssel fachgebunden (guide_*, nav_*, radio_*, sim_*, samples_*, spelling_*, knots_*, praxis_*) | i18n-String-Bundle der Lern-App | R1-Free | R1-19 | exam-guide-test.php, extensions-test.php |
| INV-D-069 | src/Rest/NonceRefresh.php:22, :28 | API | AJAX `bp_rest_nonce`: frischer `wp_rest`-Nonce ohne Neuladen, 401 ohne Anmeldung | reuse | lange Lernsitzungen ohne Datenverlust | NonceRefresh | R1-Free | R1-16, R1-20 | e2e/learning-flow.mjs |
| INV-D-070 | assets/build/app.js:140, :108, :338 | UI | SPA-Kern ohne Build: `api()` mit X-WP-Nonce, einmalige Wiederholung nach Nonce-Erneuerung, Offline-/Sitzungsfehler, Abbruch veralteter Antworten (routeSeq), Client-Router, nur `textContent` | abstract | Architektur übertragbar; Design/Routen neu | Lern-App-Kern (JS) | R1-Free | R1-16, R1-19 | e2e/learning-flow.mjs |
| INV-D-071 | assets/build/app.js:549, :443, :474, :489 | UI | „Mein Lernen“: Kurskarten mit Fortschritt, Test-/Vollzugang, Ablauf-Hinweis mit Verlängern, Zugangsstatus (Zahlung offen/abgelaufen/ausgesetzt), Empfehlungen | abstract | Dashboard neutral; Kaufhinweise über Adapter | „Mein Lernen“ | R1-Free | R1-16, R1-10, SEAM-106 | e2e/learning-flow.mjs, e2e/checkout-flow.mjs |
| INV-D-072 | assets/build/app.js:929, :1029 | UI | Kurs-Hub: Fortschritt, nächster Schritt, Lernbereiche (Smarttrainer, Themen, Merkliste, Prüfung, Downloads, Trainer), Sperr-/Freischalthinweise, Kunden-FAQ, Tickets, Praxisangebote | abstract | Hub neutral; Trainer-Kacheln, Tickets, Praxisangebote fachgebunden bzw. R3 | Kurs-Hub | R1-Free | R1-16, R1-04 | e2e/learning-flow.mjs |
| INV-D-073 | assets/build/app.js:1207, :1194 | UI | Themenübersicht „Smarttrainer“ je Modul/Thema mit Mini-Fortschrittsringen | reuse | Lernstand je Thema neutral | Themen-/Modulübersicht | R1-Free | R1-10, R1-12 | e2e/learning-flow.mjs |
| INV-D-074 | assets/build/app.js:1903, :1989, :2196 | UI | Lernmodus: Modi smart/neu/falsch/Favoriten/Sammlung/Prüfungsfehler, Fragekarte, Antwort prüfen, Erklärung, Lernstufe, Favorit, offene Frage mit Musterantwort + Selbsteinschätzung | abstract | Kern-Lernmodus neutral; Begriffe/Stufentexte neutralisieren | Lernmodus/Smarttrainer-UI | R1-Free | R1-11, R1-12, R1-06 | e2e/learning-flow.mjs, content-flow-test.php |
| INV-D-075 | assets/build/app.js:1274 | UI | Merkliste: Favoriten, Sammlungen anlegen/umbenennen/löschen, Fehlerliste, Sortierung, Einsortieren | reuse | Favoriten/Sammlungen neutral (Kap. 26.1) | Merkliste/Lernlisten | R1-Free | R1-11 | e2e/learning-flow.mjs, mobile-api-test.php |
| INV-D-076 | assets/build/app.js:1516, :1542, :1642, :1740 | UI | Prüfungs-Hub: Simulation und feste Bögen, Bögenstatus, Ergebnisliste mit Filter/Pager, Verlaufsdiagramm (Schnitt/Bestes/Letztes) | abstract | neutral; „offizielle Simulation“-Texte neutralisieren | Prüfungsübersicht + Ergebnisse | R1-Free | R1-08, R1-09, R1-10 | e2e/learning-flow.mjs |
| INV-D-077 | assets/build/app.js:4059, :4364 | UI | Prüfungslauf: Timer, Navigation, Markieren zur Überprüfung, nur Markierte, Abgabebestätigung (offene/markierte), Abbrechen, automatische Abgabe bei Zeitende | reuse | Kap. 16.3 „Prüfung“ deckungsgleich | Prüfungsplayer | R1-Free | R1-08, R1-20 | navigation-check-test.php (Flag), learning-review-test.php |
| INV-D-078 | assets/build/app.js:4079, :4152, :4338 | UI | Offline-Puffer der Prüfungsantworten (localStorage, Wiederholung bei „online“, Alter `age_ms`), Hinweis „andere Registerkarte“ | reuse | Prüfungsintegrität bei Netzabbruch (R1-20) | ExamOutbox | R1-Free | R1-20, R1-42 | learning-review-test.php („Prüfung ohne Netz“), e2e/account-flow.mjs |
| INV-D-079 | assets/build/app.js:4660, :4630, :4649 | UI | Ergebnisseite: Score-Ring, Teilgrenzen, Themen, Review, „Fehler üben“ | reuse | neutral | Prüfungsergebnis | R1-Free | R1-09 | e2e/learning-flow.mjs |
| INV-D-080 | assets/build/app.js:2968, :3081, :3070, :3242 | UI | Videokurs: Kapitel/Lektionen, Fortsetzen, erledigt, Untertitel (VTT), externe Videos erst nach Einwilligung („immer erlauben“), Schrittgalerie, Videoquiz | reuse | Kap. 2 „VideoService übernehmen“, Consent Kap. 9.4 | Lektionstyp Video | R1-Free | R1-07 | e2e/trainers-flow.mjs (Video-Teil) |
| INV-D-081 | assets/build/app.js:1536, :2524, :2606, :2633 | UI | Downloads und Abschnittsdateien mit Sperrhinweis; Rückfrage vor erstem PDF bei laufender Geld-zurück-Garantie | abstract | Download-Ansicht neutral; Garantie-Rückfrage Commerce | Materialien/Downloads | R1-Free (Garantie R3) | R1-14, R1-07 | membership-test.php (`/bootsscheine/datei`) |
| INV-D-082 | assets/build/app.js:609, :636, :663, :696 | UI | Tipps, Gruppenbeitritt per Link, „Meine Gruppen“ (Name, Einladen per Mail, Link kopieren/erneuern, Mitglied entfernen) | abstract | Gruppen-Selbstverwaltung neutral; Tipps R2 | Gruppen-Selbstverwaltung; Lerntipps | R1-Free (Gruppen, E5) / R2 (Tipps) | R1-35, R0-29 | e2e/membership-flow.mjs |
| INV-D-083 | assets/build/app.js:1150, :2333, :2383, :2659, :2701, :2855, :3328, :3430, :3477, :3514, :3619, :3711, :3933, :3977 | UI | Knoten, Prüfungsanmeldung + Ausschusssuche (Karte), Navigationstrainer, Praxiskarten, Funk (Texte, Buchstabiertafel, Muster, Praxis, Vokabeln), Gerätesimulator mit Paywall | exclude_domain_specific | Fachtrainer und amtliche Inhalte (Kap. 26.2); Praxiskarten-Mechanik siehe INV-D-108 | – | exclude | – | e2e/trainers-flow.mjs, e2e/guide-flow.mjs |
| INV-D-084 | assets/admin/restricted-block.js (Registrierung src/Gate/ContentGate.php:231) | UI | Block „Nur mit Lernzugang“ (`bootspruefung/zugang`, apiVersion 3): Kurs, Funktion, Hinweis ohne Zugang, InnerBlocks | reuse | neutrales Content-Gating (Kap. 2 ContentGate) | Block „Nur für Eingeschriebene“ | R1-Free | R1-14, R1-28 | membership-test.php |
| INV-D-085 | assets/admin/admin.css | UI | Admin-Stile | replace | eigenes Course-Captain-Design | Admin-Styles | R1-Free | R1-19 | – |
| INV-D-086 | assets/build/app.css | UI | Design des Lernbereichs (Wellen, Hero, Kacheln) | replace | „Kein Theme oder Design der Referenzanwendung übernehmen“ (Kap. 10) | gekapselte Design-Tokens | R1-Free | R1-16, R1-19 | – |
| INV-D-087 | assets/device-sim/**, assets/build/device-sim-a11y.js | UI | Funkgerät-Simulation (6 Geräte, Lektionen, freies Üben), Kopie aus boatingschool + ARIA-Ergänzung | exclude_domain_specific | Geräteemulation (Kap. 26.2) | – | exclude | – | e2e/trainers-flow.mjs |
| INV-D-088 | assets/products/*.png (62), assets/maps/de-states.json | Daten | Shop-Produktbilder Bootsscheine und Bundesländerkarte der Ausschusssuche | exclude_domain_specific | Fachprodukte/Fachkarte | – | exclude | – | – |

### 2.3 Web-REST `bootspruefung/v1` (Controller in Bereich D)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-IDs | GM-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-D-089 | src/Rest/RestRegistrar.php:19 | API | Registrierung aller Web-Controller unter `bootspruefung/v1` und der App-API | abstract | ein versionierter Namespace `course-captain/v1` (Kap. 26.2) | RestRegistrar | R1-Free | R0-19, R1-28 | – |
| INV-D-090 | src/Rest/Controllers/LearnerController.php:469, :458 | API | Fehlerhülle: AccessDenied→403 `bp_forbidden`, NotFound→404, DomainException→422 mit `reason`, Prüfungsstart-Limit→429 mit `retry_after`, sonst 500 ohne Details; `Cache-Control: no-store, private` | reuse | stabile Fachcodes, kein Leak technischer Fehler | REST-Fehlervertrag (siehe Abschnitt 6) | R1-Free | R1-28, SUITE-003 | operations-test.php |
| INV-D-091 | src/Rest/Controllers/LearnerController.php:53, :138 | API | GET `/me/courses`: Kurse mit Freischaltung je Funktion, Testumfang, Fortschritt, Shop-Angeboten, Ausblenden/Empfehlung, Ablauf, Zugangsstatus, Hinweis-Bündelung je Kauf | abstract | Katalog „Mein Lernen“ neutral; Shop-/Relationsfelder als Adapter-Erweiterung | GET /me/courses | R1-Free | R1-16, R1-13, R1-22 | operations-test.php (Abfrageanzahl), membership-test.php |
| INV-D-092 | src/Rest/Controllers/LearnerController.php:54, :206 | API | GET `/courses/{code}`: Kursdetail inkl. Fortschritt, Favoriten, Prüfungsübersicht, Trainer, Limits, Downloads, Dateien, Seekarten, Prüfungsanmeldung, Kunden-FAQ-Anzahl, Tickets, Praxisangebote | abstract | Kern neutral; Felder `charts`, `exam_guide`, `tickets`, `practice_offers`, `money_back_active` fachgebunden bzw. R3 | GET /courses/{id} | R1-Free | R1-16, R1-04 | content-flow-test.php, membership-test.php |
| INV-D-093 | src/Rest/Controllers/LearnerController.php:55, :245 | API | GET `/courses/{code}/next-question` (Modul, Thema, Sammlung, Prüfungsfehler, Modus) | reuse | Smarttrainer-Schnittstelle (Kap. 47) | GET …/learn/next | R1-Free | R1-12, R1-11 | content-flow-test.php |
| INV-D-094 | src/Rest/Controllers/LearnerController.php:68, :80 | API | POST `/answers` (Token, Antwort-IDs, known, Idempotency-Key UUID Pflicht, response_ms) und POST `/answers/reveal` | reuse | signiertes Frage-Token + Idempotenz (Kap. 9.4) | POST …/learn/answer, …/learn/reveal | R1-Free | R1-12, R1-25 | content-flow-test.php, operations-test.php |
| INV-D-095 | src/Rest/Controllers/LearnerController.php:86, :92, :93, :99, :105 | API | Favoriten PUT/DELETE, Merkliste GET, Einsortieren PUT, Sammlungen POST/PUT/DELETE | reuse | Kap. 26.1 Favoriten/Lernlisten | Favorites/Collections-API | R1-Free | R1-11 | mobile-api-test.php (gleiche Services) |
| INV-D-096 | src/Rest/Controllers/LearnerController.php:110–135, :376–442 | API | Prüfung: Übersicht, Start (201, `live`/`reduced`), Anzeigen, Antwort speichern (Position, Offline-Alter, `applied`), Markieren, Musterantwort, Abgabe, Abbruch | reuse | Prüfungs-Zustandsautomat (Kap. 47) | Attempt-API (start/show/save/flag/submit/abort) | R1-Free | R1-08, R1-09, R1-20, R0-08 | content-flow-test.php, learning-review-test.php, navigation-check-test.php |
| INV-D-097 | src/Rest/Controllers/GroupController.php:35–41, :110, :121 | API | Gruppen: Einladung beschreiben (jeder Token-Teil trifft die Route), beitreten, meine Gruppen, umbenennen, per Mail einladen (≤200), Link erneuern, Mitglied entfernen; Besitzprüfung vor Aktion | reuse | Free-Basis Seat/Claim/Grant mit Self-Service | Groups-API | R1-Free (E5) | R1-13, R1-35, R0-29 | membership-test.php, e2e/membership-flow.mjs |
| INV-D-098 | src/Rest/Controllers/ImportController.php:23 | API | POST `/imports/{id}/step` (Cap `import_bp_content`, 250 Einträge je Schritt, 409 bei Fehler) | reuse | resumierbarer Batch-Import (R0-38) | POST /admin/imports/{id}/step | R1-Free | R1-15, R1-41 | e2e/admin-import.mjs |
| INV-D-099 | src/Rest/Controllers/StatusController.php:20 | API | GET `/status` mit JSON-Schema (Cap `manage_bp_content`) | reuse | Diagnose-API | GET /admin/status | R1-Free | R1-38 | smoke-test.php |
| INV-D-100 | src/Rest/Controllers/TrainerController.php:70–74, :127 | API | Videos: Kursübersicht, Lektion, Fortschritt (Position, erledigt); Trainer-Zusammenfassung für Kursseite | reuse | Video-Lektionen neutral | Lesson-Progress-API | R1-Free | R1-07, R1-10 | content-flow-test.php |
| INV-D-101 | src/Rest/Controllers/TrainerController.php:64–69 | API | Praxiskarten: Liste, Status `known/practice/open` | abstract | Karteikarten-Mechanik neutral, Inhalte fachgebunden | Flashcard-Status-API | R1-Free | R1-11 | content-flow-test.php |
| INV-D-102 | src/Rest/Controllers/TrainerController.php:59–63, :75–123 | API | Navigationstrainer, Funk (Texte, Auswertung, Vokabeln, Praxis, Muster, Buchstabiertafel), Gerätesimulation (Laravel-kompatibles Format) | exclude_domain_specific | Fachtrainer (Kap. 26.2) | – | exclude | – | content-flow-test.php, navigation-check-test.php |
| INV-D-103 | src/Rest/Controllers/ExamGuideController.php:23 | API | GET `/courses/{code}/pruefung-anmelden` | exclude_domain_specific | amtliche Prüfungsanmeldung | – | exclude | – | exam-guide-test.php |

### 2.4 Mobile-API `boatingschool/v1`

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-IDs | GM-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-D-104 | src/Mobile/MobileApi.php:45, :79, :106, :116, :126, :250 | API | Eigener Namespace nur mit Bearer-Token: Cookies/Nonce ignoriert, Token nie für fremde Routen, groß-/kleinschreibungs- und `rest_route`-sicher, App ein/aus (503), HTTPS-Pflicht, Mindest-App-Version (426) | abstract | Token-Isolation wertvoll; eigener App-Namespace widerspricht Kap. 2/26.2 („keine alte boatingschool/v1“) | Token-Auth als zweiter Authentifizierungsweg auf `course-captain/v1` | R2 (E1) | R0-19, R1-28, SUITE-201 | mobile-api-test.php |
| INV-D-105 | src/Mobile/MobileApi.php:197, :227 | API | CORS nur eigene Origin (+Filter), einheitliche Kopfzeilen (no-store, X-Request-Id, nosniff, API-Version) | reuse | Sicherheitskopfzeilen für jede private REST-Route | REST-Response-Policy | R1-Free | R0-17, R1-28 | mobile-api-test.php |
| INV-D-106 | src/Mobile/TokenAuth.php:27–37, :144, :174, :238, :305, :356–413 | API | Gerätesitzungen: Access 15 min, Refresh rotierend 30 Tage/absolut 90 Tage, nur SHA-256-Hashes, selbstidentifizierende Refresh-Tokens mit HMAC, Wiederverwendung → Widerruf, 60-s-Kulanz, max. 20 Sitzungen, Widerruf bei Passwortwechsel, Cleanup | reuse | erfüllt Kap. 9.4 „rotierbares/revokierbares Tokenverfahren“ | ClientTokenService | R2 (E1) | R0-17, R0-19 | mobile-api-test.php (Rotation, Kulanz, Reuse) |
| INV-D-107 | src/Mobile/Controllers/AuthController.php:38, :87, :100, :108 | API | Login per E-Mail/Benutzer + Passwort + Gerätename, gemeinsame Fehlversuchszähler mit Web (LoginGuard, Reservierung vor Prüfung), bekanntes Gerät, Verwaltungskonten ausgeschlossen (Filter), Refresh, Logout | abstract | Muster neutral; nur mit Token-API sinnvoll | Client-Login | R2 (E1) | R0-17, R1-34 | mobile-api-test.php, security-test.php |
| INV-D-108 | src/Mobile/Controllers/AuthController.php:119, :155, :192 | API | Passwort vergessen (immer 202, Mail nach der Antwort gegen Timing-Enumeration) und Zurücksetzen (≥12 Zeichen, widerruft alle Sitzungen) | abstract | Sicherheitsmuster neutral; Web nutzt WP-Kern | Client-Passwort-Reset | R2 (E1) | R0-17 | mobile-api-test.php |
| INV-D-109 | src/Mobile/Controllers/AuthController.php:172; src/Mobile/WebSession.php:33–41, :75, :123, :157 | API | Sitzungsübergabe App→Web: Einmal-URL 60 s, Hash, gebunden an App-Sitzung, Prüfwert-Header gegen Login-CSRF, nur Lernbereichs-Pfade, keine Verwaltungskonten, kein Kontowechsel | exclude_domain_specific | existiert für den Funkgerät-Simulator im Tablet-WebView | – | exclude | – | mobile-api-test.php (WebSession, 17 Prüfungen) |
| INV-D-110 | src/Mobile/Controllers/AccountController.php:21, :41, :63, :83, :116, :132, :143 | API | Profil, Mitgliedschaft (Tenant, Branding, Links), Rechtstexte-Links (Filter), Gerätesitzungen anzeigen/widerrufen/alle anderen abmelden | abstract | Profil + Sitzungsverwaltung neutral, Tenant „Bootsschule“ entfällt | GET /me, /me/sessions | R2 (E1) | R0-19 | mobile-api-test.php |
| INV-D-111 | src/Mobile/Controllers/BaseController.php:76 ; src/Mobile/MobileSettings.php:62 | PHP | Tenant-Prüfung per Header `X-Tenant-Slug` (ein Shop = ein Tenant) | replace | Mandanten-SaaS ist Nicht-Ziel v1 (Kap. 5) | – (Site-Identität über Basis-URL) | exclude | R0-25 | mobile-api-test.php |
| INV-D-112 | src/Mobile/Controllers/BaseController.php:130, :142, :169, :187, :210, :247 | API | Gemeinsame Bausteine: Feature-Pflicht mit ENTITLEMENT_REQUIRED/EXPIRED/SUSPENDED + Shop-Link, Idempotency-Key-Header (UUID), Paging (≤10 000/≤100), Fehlerübersetzung DomainException→Codes | reuse | stabile Fehler- und Paging-Verträge | REST-Basiscontroller | R1-Free | R1-28, SUITE-003 | mobile-api-test.php |
| INV-D-113 | src/Mobile/Problem.php:34, :88, :125; src/Mobile/ApiException.php | API | Fehlerobjekt RFC-9457-Stil (`application/problem+json`, code, title, request_id, retry_after, errors) mit 49 stabilen Codes; WP-Fehler (Param, Route, Methode, Rechte) einheitlich übersetzt | reuse | Kap. 30.3/SUITE-003 Fehlercodes | ProblemDetails für `course-captain/v1` | R1-Free | SUITE-003, R1-28 | mobile-api-test.php („Fehlercodes statt Meldungstexten“) |
| INV-D-114 | src/Mobile/Idempotency.php:24, :40, :86, :106, :134 | Daten | Idempotency-Key je Nutzer+Vorgang: gleiche Anfrage → gespeicherte Antwort, anderer Inhalt → 409 REUSED, „in Arbeit“ → 409 IN_PROGRESS, verwaiste Reservierung nach 300 s übernehmen, 7 Tage | reuse | Kap. 9.4 „Bearbeiten/Schreiben idempotent“ | IdempotencyStore | R1-Free | R1-25, R1-42, SUITE-003 | mobile-api-test.php („verwaiste Idempotenz-Einträge“) |
| INV-D-115 | src/Mobile/RateLimiter.php:32, :59, :152, :178, :206, :224, :249 | Daten | Atomare Fensterzähler (Tabelle), Client-IP nur über eingetragene Proxys (CIDR), IPv6 als /64, HTTPS-Erkennung, Proxy-Warnung | reuse | Rate-Limits für Login/Antworten/Einladung (Kap. 9.4) | RateLimiter | R1-Free | R0-17, R1-34, R1-42 | security-test.php (9 Prüfungen), mobile-api-test.php |
| INV-D-116 | src/Mobile/Controllers/CourseController.php:29, :39, :74, :116, :124; src/Mobile/Mapper/CourseMapper.php:27, :138, :320 | API | Kursliste (paginiert), Kursdetail, Übersicht mit „nächster Schritt“, Module (gesperrt drip/trial, unlock_at), Fortschritt (neu/unsicher/lernend/sicher/gefestigt, fällig) | abstract | Datenvertrag neutral; Capability-Schlüssel `smarttrainer/navigation/praxis/funk` neu | Course-DTOs | R2 (E1; Web-Pendant R1) | R1-10, R1-16 | mobile-api-test.php |
| INV-D-117 | src/Mobile/Controllers/CourseController.php:143, :222; src/Mobile/Controllers/RadioController.php:42–278; src/Mobile/Controllers/TrainingController.php:140–272 | API | Prüfungsanmeldung, Knoten-Kapitel, Funktraining (13 Routen), Navigationsaufgaben inkl. Seekarten | exclude_domain_specific | Fachtrainer/amtliche Inhalte | – | exclude | – | mobile-api-test.php |
| INV-D-118 | src/Mobile/Controllers/FileController.php:26, :84, :113; src/Mobile/Controllers/CourseController.php:206 | API | Geschützte Datei per Bearer: gleiche Freigabe wie Web, HTTP-Range (genau ein Bereich, 206/416), RFC-6266-Dateiname, nur PDF/Bild/Audio/Video inline, sonst Download, CSP-Sandbox, Downloadzählung | reuse | sichere Medienauslieferung (Kap. 9.4) | ProtectedMediaStream (auch für Web) | R1-Free (Range im Web-Controller) | R1-14, R0-10 | mobile-api-test.php („Range/Content-Disposition“) |
| INV-D-119 | src/Mobile/Controllers/LearningController.php:33, :100, :134, :143, :176, :358 | API | Lernen: nächste Frage (9 Modi inkl. `question`), gezielte Frage mit Sperrgründen (scheduled_release/partial_access/running_exam), Reveal, Antwortversuch idempotent (201/200), Versuch abrufen | abstract | Web-Pendant in INV-D-093/094; zusätzliche Sperrgründe als Vertrag übernehmen | Learn-API (gemeinsam Web/Client) | R1-Free (Vertrag) / R2 (Client) | R1-12, R1-11, R1-27 | mobile-api-test.php |
| INV-D-120 | src/Mobile/Controllers/LearningController.php:208–349 | API | Favoriten (Sortierung saved_at/assigned_at), Fehlerliste, Favorit setzen mit Sammlungen, entfernen, Sammlungen CRUD (idempotent) | reuse | Kap. 26.1 | Favorites/Collections-API | R1-Free (Vertrag) / R2 (Client) | R1-11 | mobile-api-test.php („Favoriten-Sortierung“) |
| INV-D-121 | src/Mobile/Controllers/ExamController.php:26, :89, :118, :153, :165, :173, :183, :195, :212 | API | Prüfungen: Optionen (Simulation/feste Bögen, Bestehensregel strukturiert), Start idempotent, Antwort mit `marked_for_review`, Markieren, Reveal, Abgabe, Abbruch, Ergebnis (Teile, Themen, Review, Fehler üben); Status 404/409/410 | reuse | Prüfungsvertrag neutral | Attempt-API (gemeinsam Web/Client) | R1-Free (Vertrag) / R2 (Client) | R1-08, R1-09, R1-20 | mobile-api-test.php, learning-review-test.php |
| INV-D-122 | src/Mobile/Controllers/TrainingController.php:35, :61, :93; src/Mobile/Mapper/MediaMapper.php:44 | API | Video-Kapitel, Lektion (Medienobjekt, externer Host, Schritte, Quiz mit answer_token), Fortschritt (null = unverändert) | reuse | Video-Lektion neutral | Lesson-API | R1-Free (Vertrag) / R2 (Client) | R1-07 | mobile-api-test.php |
| INV-D-123 | src/Mobile/Controllers/TrainingController.php:276, :303 | API | Praxiskarten nach Kategorien, Status setzen | abstract | Karteikarten-Mechanik neutral | Flashcard-API | R1-Free (Vertrag) / R2 (Client) | R1-11 | mobile-api-test.php |
| INV-D-124 | src/Mobile/Controllers/GroupController.php:41, :52, :58, :91, :110, :126 | API | Gruppen verwalten (nur Besitzer, fremde → 404), Einladen idempotent + 20/h, Platz freigeben, Beitrittscode erneuern, Einladungsvorschau (60/h), GROUP_NOT_ACTIVE | reuse | Free-Basis Gruppen | Groups-API | R1-Free (Vertrag) / R2 (Client) | R1-35, R0-29 | mobile-api-test.php |
| INV-D-125 | src/Mobile/Controllers/CouponController.php:36, :94 | Commerce | Code einlösen: Gruppen-Beitrittscode, sonst Gutschein-Plugin per Filter (0-€-Bestellung), Woo-Rabattcode und Unbekanntes gleich abgelehnt (keine Existenzprobe), Idempotenz vor Rate-Limit | abstract | Gruppencode → Free; Gutscheinbrücke → Voucher-Adapter | Redeem-Endpunkt (Gruppe) + Voucher-Adapter | R1-Free (Gruppe) / R3-Pro (Gutschein) | R1-13, R3-04, SUITE-104 | mobile-api-test.php, extensions-test.php |
| INV-D-126 | src/Mobile/Controllers/VoucherController.php:36, :39, :80, :95, :156 | Commerce | Kursgutschein prüfen/einlösen über Filter-Vertrag `bootspruefung_course_voucher_redeemer_v1` (check/redeem), Fehlercode-Abbildung (22 Quellcodes → 11 App-Codes), 30/h je Konto + IP-Limit | abstract | entspricht Voucher-Captain-Reserve/Grant/Commit (Kap. 32) | Voucher-Connector-Vertrag | R3-Pro | R3-04, R3-05, SUITE-104, SEAM-102 | mobile-api-test.php, voucher-grant-test.php |
| INV-D-127 | src/Mobile/Controllers/PrivacyController.php:52, :67, :82, :188 | API | Datenexport asynchron (Action Scheduler/WP-Cron, Sperre per bedingtem UPDATE, 30-s-Rückfall, 24 h, 5/Tag, alle WP-Exporter als JSON) | abstract | DSGVO-Self-Service neutral; im Web über WP-Kern | Privacy-Export-API | R2 (E1) | R1-17, R0-17 | mobile-api-test.php („asynchroner Datenexport“) |
| INV-D-128 | src/Mobile/Controllers/PrivacyController.php:101, :132; src/Mobile/AppLinks.php:247 | API | Kontolöschung beantragen (WP-Datenschutzanfrage) und Bestätigung per API ohne Anmeldung, einheitlich 422 | abstract | neutral, an Token-Client gekoppelt | Privacy-Deletion-API | R2 (E1) | R1-17 | mobile-api-test.php |
| INV-D-129 | src/Mobile/AppSettings.php:50, :97; src/Mobile/Controllers/SettingsController.php:21, :26 | Daten | Synchronisierte Client-Einstellungen je Konto (≤8 KB, ≤4 Ebenen, Versionszähler, If-Match/ETag, 409 VERSION_CONFLICT) | abstract | nur mit eigenem Client nötig | ClientPreferences | R2 (E1) | – | mobile-api-test.php |
| INV-D-130 | src/Mobile/AppLinks.php:34, :90, :144, :176, :209, :274 | PHP | Universal/App Links (`/.well-known/*`), App-Link-Pfade Passwort/Konto löschen mit Browser-Weiterleitung, Mail-Link-Umschreibung, Safari-App-Banner | exclude_domain_specific | an die konkrete Boots-App gebunden; native Apps Nicht-Ziel v1 (Kap. 5) | – | exclude | – | mobile-api-test.php („Universal Links/App Links“) |
| INV-D-131 | src/Mobile/Controllers/FaqController.php:27 | API | Kunden-FAQ eines Kurses (nur Vollzugang; Teilumfang → CONTENT_LOCKED partial_access) | abstract | siehe INV-D-057 | Kurs-FAQ-API | R2 (E6) | FLOW-102 | customer-faq-test.php |
| INV-D-132 | src/Mobile/Controllers/TipsController.php:21, :51, :62, :76 | API | Tipps & Tricks (Kategorien, HTML absolut verlinkt, Klartext, Linkliste) | abstract | redaktionelle Lerntipps neutral, nicht Kern | Lerntipps/Ankündigungen | R2 (E6) | FLOW-102 | mobile-api-test.php, security-test.php |
| INV-D-133 | src/Mobile/Controllers/TicketController.php:20, :26 | Commerce | Praxis-Tickets aus Ticket-Plugin (Filter `bootspruefung_tickets_for_user`) | abstract | Präsenztermine über Pass Captain (Kap. 45) | Pass-Connector: Termine im Kurs-Hub | R3-Pro | SUITE-105, R3-07 | extensions-test.php |
| INV-D-134 | src/Mobile/MobileApi.php:287, :54; src/Mobile/TokenAuth.php:413 | Cron | Stündliches Aufräumen: Sitzungen, Idempotenz, Exporte, Web-Codes, Rate-Limits (Cron-Guard `mobile_cleanup`) | reuse | Retention-Jobs neutral | Cleanup-Job | R1-Free (Idempotenz/RateLimit) / R2 (Token) | R1-17 | operations-test.php |
| INV-D-135 | src/Mobile/MobileApi.php:58–64, :267, :275 | PHP | Passwortänderung (Reset, Profil, Woo) beendet alle Token-Sitzungen; reines Neu-Hashen nicht | reuse | Sitzungshygiene | ClientTokenService-Hooks | R2 (E1) | R0-17 | mobile-api-test.php |
| INV-D-136 | src/Mobile/MobileApi.php:487 | API | GET `/health` ohne Anmeldung: Status, API-Version, Tenant, Authorization-Header angekommen?, HTTPS, Serverzeit, Rechtstexte, Mindestversion, Store-Links (Plugin-Version nur für Admins) | abstract | Health ohne Versionsleck neutral; Tenant/Store fachgebunden | GET /health | R2 (E1) | R1-38 | mobile-api-test.php, security-test.php |
| INV-D-137 | src/Mobile/Mapper/QuestionMapper.php:15, :41, :65, :85 | PHP | Fragen-DTO (Auswahltyp single/multiple/open, Buchstaben, Lernstufe, fällig), Antwortergebnis | abstract | DTO neutral | Question-DTO | R1-Free (Vertrag) | R1-06, R1-28 | mobile-api-test.php |

### 2.5 Dokumentation und Tests

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | neutraler Zielbaustein | Phase/Edition | Task-IDs | GM-Kandidat |
|---|---|---|---|---|---|---|---|---|---|
| INV-D-138 | docs/mobile-api/openapi.yaml; README.md:23–706 | Daten | OpenAPI-Vertrag (72 Pfade, Version 1.14.0), Entwicklerablauf, Fehlercodes, Änderungsprotokoll, offene Punkte | abstract | Methode (Vertrag im Repo + Routenabgleich im Test) übertragbar; Inhalt fachgebunden | OpenAPI `course-captain/v1` + Routen↔Spec-Test | R1-Free | R0-19, R1-28, SUITE-111 | mobile-api-test.php („Abgleich Routen ↔ openapi.yaml“) |
| INV-D-139 | docs/mobile-api/konzept-original.md, openapi-concept-original.yaml, plugin-concept-original/** | Daten | Ursprungskonzept der Boots-App und Konzept-Plugin `boatingschool-mobile-api` (Tenant, Zugangscodes) | exclude_domain_specific | historische Fachquelle | – | exclude | – | – |
| INV-D-140 | tests/integration/admin-test.php | Test | Admin/Import: veraltete Freigaben, Mischstand, hängende Importe, Vorschau in Teilschritten, Sammelfreigabe Rechte, Preiseingabe, Protokoll-Rechte, Menü, Mail, Glossar | abstract | Testfälle neutral übertragbar außer Preise/Katalog | GM-Suite Admin/Import | R1-Free | R1-18, R1-36, R0-05 | selbst |
| INV-D-141 | tests/integration/mobile-api-test.php | Test | 476 Prüfungen: Auth, Rotation, Rate-Limits, IDOR, Idempotenz, Range, Export, Löschung, Gruppen, Routen↔OpenAPI | abstract | Sicherheits-/Idempotenzfälle wertvoll; Fachteile (Funk, Navigation, Simulator) streichen | GM-Suite API-Sicherheit | R1-Free (Idempotenz/Rate-Limit/IDOR) / R2 (Token) | R1-18, R0-05, R0-07 | selbst |
| INV-D-142 | tests/integration/{content-flow,learning-review,navigation-check,membership,operations,security,release-ops,smoke,customer-faq,faq,exam-guide,extensions,woocommerce,account}-test.php | Test | Durchläufe Import→Lernen→Prüfung, Offline-Prüfung, Flag, Gruppen/Teilumfang/Return-After-Login, Betrieb/Notices/Abfrageanzahl, Proxys/NoCache, Site Health, Status-Route, FAQ, Erweiterungen, Woo | abstract | Abläufe als Vorlage; Fachfixtures (SBF-SEE, SRC, Seekarte) durch neutrale Kurse ersetzen (Kap. 26.3 Schritt 5) | GM-Suite Lernen/Betrieb | R1-Free (Woo/Extensions R3) | R1-18, R0-05, FLOW-002 | selbst |
| INV-D-143 | tests/e2e/*.mjs, tests/e2e/*-setup.php, tests/e2e/router.php | Test | Playwright-Flows: learning, account, membership, checkout, admin-import, trainers, guide; Setup-Skripte (Block-Theme, Konten, Mail-Abfang) | abstract | E2E-Gerüst übertragbar; guide-/trainers-flow fachgebunden | E2E-Suite Lernende/Admin | R1-Free (checkout R3) | R1-18, R1-29 | selbst |
| INV-D-144 | tests/phpstan/*.php; tests/integration/support/*.php | Test | PHPStan-Stubs/Bootstrap, Testhilfen (FakeOrders, Guard nur Test-DB, Standalone) | reuse | CI-Hilfen fachneutral | QA-Infrastruktur | R1-Free | R0-18 | – |

## 3. Admin-Screens

### 3.1 Menübaum (`Menu::addPages`, src/Admin/Menu.php:44; Reihenfolge `Menu::ORDER` :83)

Hauptmenü: `add_menu_page('Bootsprüfung', …, 'manage_bp_content', 'bootspruefung', ContentPages::renderOverview, 'dashicons-sos', 56)`.

| Nr. | Unterseite | Slug | Capability | Quelle |
|---|---|---|---|---|
| 1 | Übersicht | `bootspruefung` | `manage_bp_content` | ContentPages::renderOverview |
| 2 | Inhaltspakete | `bootspruefung-import` | `import_bp_content` | ImportPage::render |
| 3 | Kurse & Module | `bootspruefung-courses` | `manage_bp_content` | ContentPages::renderCourses |
| 4 | Fragen | `bootspruefung-questions` | `manage_bp_content` | ContentPages::renderQuestions |
| 5 | Medien | `bootspruefung-media` | `manage_bp_content` | ContentPages::renderMedia |
| 6 | Prüfungsregeln | `bootspruefung-rules` | `manage_bp_content` | ContentPages::renderRules |
| 7 | Rechte | `bootspruefung-rights` | `manage_bp_content` | ContentPages::renderRights |
| 8 | Prüfungsanmeldung | `bootspruefung-pruefungsanmeldung` | `manage_bp_content` | ExamGuidePages::render |
| 9 | FAQ | `edit.php?post_type=bp_faq` | `manage_bp_content` | CPT-Liste (FaqAdmin) |
| 10 | FAQ-Kategorien | `edit-tags.php?taxonomy=…&post_type=bp_faq` | `manage_bp_content` | Taxonomie |
| 11 | Kunden-FAQs | `edit.php?post_type=bp_customer_faq` | `manage_bp_content` | CPT-Liste (CustomerFaqAdmin) |
| 12 | Kunden-FAQ-Kategorien | `edit-tags.php?…` | `manage_bp_content` | Taxonomie |
| 13 | Tipps (CPT `bp_tip`) | `edit.php?post_type=bp_tip` | CPT-Capabilities = `manage_bp_content` (src/Tips/Tips.php:44–57, außerhalb Bereich D) | `show_in_menu => 'bootspruefung'` |
| 14 | Zugänge | `bootspruefung-access` | `manage_bp_entitlements` | AccessPages::render |
| 15 | Kostenlose Bereiche | `bootspruefung-free` | `manage_bp_entitlements` | AccessPages::renderFree |
| 16 | Gruppenlizenzen | `bootspruefung-groups` | `manage_bp_entitlements` | AccessPages::renderGroups |
| 17 | Mitgliederrabatte *(nur bei aktivem Woo-Adapter)* | `bootspruefung-discounts` | `manage_bp_entitlements` | AccessPages::renderDiscounts |
| 18 | Preise | `bootspruefung-prices` | `manage_bp_entitlements` | PricePage::render |
| 19 | Protokoll | `bootspruefung-audit` | `manage_options` | AuditPage::render |
| 20 | Einstellungen | `bootspruefung-settings` | `manage_options` | Menu::renderSettings |
| 21 | Mobile App | `bootspruefung-mobile` | `manage_options` | MobileAdminPage::render (eigener `admin_menu`-Hook, Priorität 20) |
| 22 | Systemstatus | `bootspruefung-status` | `manage_bp_content` | Menu::renderStatus |

Zusätzlich jede Render-Methode mit eigener `current_user_can`-Prüfung (`wp_die`); Aktionen über `admin-post.php` mit Capability + `check_admin_referer` (Tabelle 3.3).

### 3.2 Tabs, Bereiche und Unteransichten

| Screen | Tabs/Ansichten |
|---|---|
| Inhaltspakete | Liste (Upload, „Ohne Hochladen importieren“: Ablageordner, Link, WP-CLI-Hinweis; Importliste); Detail `?batch=` mit Zustandswarnungen, Aktionen je Status (validated, previewed, approved/importing, published, failed), Vorschau, Ergebnis, Hinweise, Protokoll mit Filter create/update/unchanged/archive/error/rejected und Pager |
| Fragen | Liste mit Filtern; Detail `?question=` |
| Medien | „alle“ / „ohne Datei“ (`?missing=1`) |
| Zugänge | Liste/Suche; Detail `?entitlement=`; Bestellfilter `?order=` |
| Gruppenlizenzen | Liste/Suche; Detail `?group=` |
| Prüfungsanmeldung | Reiter `committees` (Prüfungsausschüsse, `?id=`, `?new=1`) und `guides` (Anleitungen) – einzige echte `nav-tab`-Reiter |
| Einstellungen | eine Seite mit Sprunganker-Bereichen: Zugang und Erinnerungen, Darstellung und Lernen, Datenschutz und Daten, Betrieb, Sicherheit, Zahlungskonflikte (drei getrennte Formulare) |
| Systemstatus | Kopf-Tabelle, Shop und Zahlungsarten (nur Woo), Zahlungskonflikte-Status, Datensicherung, Wartung, OSS-Monitor, E-Mail-Versand, Dateischutz, Tabellen |
| Übersicht | Livegang-Checkliste (aufklappbar), Kennzahlen, Bestand, Offene Punkte, Produktzuordnungen, Glossar |

### 3.3 Settings-Felder und Formularaktionen je Screen

**Einstellungen › Hauptformular** (`options.php`, `settings_fields('bp_learning')`, Option `bp_learning_settings`, Defaults src/Settings/Settings.php:16–39):

| Feld | Typ/Werte | Default | Klassifikation |
|---|---|---|---|
| `default_access` „Zugang ohne Shop“ | `logged_in` / `entitled_only` | `logged_in` | abstract → Einschreibungsmodus (Kap. 16.1 Schritt 3) |
| `expiry_reminder_enabled` | Checkbox | an | abstract → E-Mails › Ablauf-Erinnerung (R1-21) |
| `expiry_reminder_days` | 1–90 | 14 | abstract |
| `video_captions` | `off` / `vtt` | off | reuse (R1-07) |
| `radio_voice_output` | browser/recording/off | browser | exclude_domain_specific |
| `brand_name` | Text ≤60 | leer = Website-Titel | abstract (Bezeichnung Lernbereich) |
| `price_note` | Text ≤80 | „inkl. MwSt.“ | abstract → Woo-Adapter (R3) |
| `exam_guide_enabled` | Checkbox | aus | exclude_domain_specific |
| `privacy_requests_auto` | Checkbox | an | abstract → Datenschutz (R1-17) |
| `delete_data_on_uninstall` | Checkbox | aus | reuse (R1-23) |
| `update_check_enabled` (Bereich „Betrieb“, UpdateCheck::renderSettingsSection) | Checkbox | aus | replace (WordPress.org-Updates; Pro über License Captain) |

**Einstellungen › Sicherheit** (admin-post `bp_security_save`, Nonce `bp_security_save`, `manage_options`): `trusted_proxies` (Textarea IP/CIDR, gespeichert in Option `bp_learning_mobile`).
**Einstellungen › Zahlungskonflikte**: `DisputeWebhooks::renderSettings()` (src/Commerce, Bereich B).

**Mobile App** (admin-post `bp_mobile_save`, Option `bp_learning_mobile`, Defaults src/Mobile/MobileSettings.php:19): `enabled`, `tenant_slug`, `primary_color`, `support_email`, `web_session_enabled`, `ios_app_ids`, `android_package`, `min_app_version_ios`, `min_app_version_android`, `app_store_url`, `play_store_url`, `android_cert_fingerprints`; weitere Aktionen `bp_mobile_check` (Loopback-Header-Prüfung), `bp_mobile_revoke` (Feld `customer`).

**admin-post/AJAX-Aktionen** (jeweils Nonce = Aktionsname):

| Aktion | Capability | Quelle |
|---|---|---|
| `bp_access_grant`, `bp_access_change`, `bp_free_rule`, `bp_group_admin`, `bp_member_discount`, `bp_shop_catalog` | `manage_bp_entitlements` | AccessPages.php:33–38, :690 |
| `bp_mapping_remove` | `manage_bp_entitlements` | ContentPages.php:45 |
| `bp_override` | `manage_bp_content` | ContentPages.php:612 |
| `bp_rights_release`, `bp_rules_release`, `bp_chart` | `manage_bp_content` | ContentPages.php:540, :558, :576 |
| `bp_import_upload`, `bp_import_server`, `bp_import_url`, `bp_import_action` | `import_bp_content` | ImportPage.php:22–25, :597 |
| `bp_exam_committee`, `bp_exam_committee_delete` | `manage_bp_content` | ExamGuidePages.php:25–26 |
| `bp_faq_import`, `bp_faq_page` (zusätzlich `publish_pages`) | `manage_bp_content` | FaqAdmin.php:39–40 |
| `bp_cfaq_import`, `bp_cfaq_report_dismiss` | `manage_bp_content` | CustomerFaqAdmin.php:42–43 |
| `bp_shop_prices` | `manage_bp_entitlements` | PricePage.php:37 |
| `bp_golive_tick` | `manage_options` | GoLiveChecklist.php:192 |
| `bp_learning_set_entitled_only` | `manage_options` | GoLiveNotice.php:111 |
| `bp_mail_test` | `manage_options` | MailStatus.php:114 |
| `bp_security_save` | `manage_options` | Menu.php:392 |
| `bp_notice_dismiss` (admin-post + `wp_ajax`) | angemeldet + `read`, signierte URL | Notices.php:242, :254 |
| `bp_mobile_save`, `bp_mobile_revoke`, `bp_mobile_check` | `manage_options` | MobileAdminPage.php:28–30 |
| Metaboxen `bp_faq_meta`, `bp_cfaq_meta` (save_post) | `manage_bp_content` | FaqAdmin.php:146, CustomerFaqAdmin.php:173 |
| Speicher-Neuprüfung `?bp_storage_recheck` (Nonce `bp_storage_recheck`) | Seitenzugang Systemstatus | SystemStatus.php:342 |

**Formularfelder weiterer Screens:** Manuelle Freischaltung (`user`, `course`, `features[]`, `valid_until`, `unlimited`, `reason`); Zugang ändern (`reason`, `valid_until`, `unlimited`, `op` extend/active/suspended/revoked/release); Kostenlose Bereiche (`course`, `feature`, `limit`, `valid_until`, `reason`; Drip: `target`, `days`, `reason`); Gruppe (`reason`, `status` bzw. `member`); Rabatt (`course`, `percent`, `applies`, `label`); Override (`type`, `ids` oder `group/imported/effective`, `field`, `value`, `reason`, `back`, `bp_confirmed`); Import per Link (`url` nur https, `sha256`); Import-Aktion (`batch`, `do`, `note`); Prüfungsausschuss (13 Kontaktfelder, `source_url` Pflicht für eigene Einträge, `course_codes[]`); Preise (`prices[SKU]`, `op`, `publish`, `drop_sales`, `overwrite_prices[]`, `publish_held[]`).

## 4. REST-API (vollständig)

Konventionen: `auth` = `is_user_logged_in()` sonst `WP_Error bp_login_required` 401 (Cookie + `X-WP-Nonce`); Mobile `requireUser` = gültiges Bearer-Token einer aktiven Sitzung, sonst `UNAUTHENTICATED` 401; „Tenant“ = zusätzlich Header `X-Tenant-Slug` (BaseController::wrap). `code` = `^[A-Za-z0-9-]+$`, `id` = Ganzzahl ≥1.

### 4.1 Web `bootspruefung/v1` – Controller in Bereich D

| # | Route | Methode | permission_callback | Args | Quelle |
|---|---|---|---|---|---|
| W1 | `/status` | GET | `current_user_can('manage_bp_content')` | – (Schema `bp-status`) | StatusController.php:20 |
| W2 | `/imports/(?P<id>\d+)/step` | POST | `current_user_can('import_bp_content')` | id | ImportController.php:23 |
| W3 | `/me/courses` | GET | auth | – | LearnerController.php:53 |
| W4 | `/courses/(?P<code>…)` | GET | auth | code (+ `ergebnisse` ungeprüft gelesen, :222) | :54 |
| W5 | `/courses/{code}/next-question` | GET | auth | code, module (int), topic (≤190), collection (int), exam (int), mode (enum LearningService::MODES, Default smart) | :55 |
| W6 | `/answers` | POST | auth | token (≤2000, Pflicht), answer_ids (int[] ≤26), known (bool/null), idempotency_key (UUID, Pflicht), response_ms | :68 |
| W7 | `/answers/reveal` | POST | auth | token | :80 |
| W8 | `/favorites/(?P<question>\d+)` | PUT, DELETE | auth | question | :86 |
| W9 | `/courses/{code}/saved` | GET | auth | code | :92 |
| W10 | `/courses/{code}/saved/(?P<question>\d+)` | PUT | auth | code, question, favorite (bool, Pflicht), collections (int[] ≤MAX_COLLECTIONS) | :93 |
| W11 | `/courses/{code}/collections` | POST | auth | code, name (≤200) | :99 |
| W12 | `/courses/{code}/collections/(?P<id>\d+)` | PUT, DELETE | auth | code, id, name (PUT) | :105 |
| W13 | `/courses/{code}/exam` | GET | auth | code | :110 |
| W14 | `/courses/{code}/exam-sessions` | POST | auth | code, paper_id (int/null) | :111 |
| W15 | `/exam-sessions/(?P<id>\d+)` | GET | auth | id | :117 |
| W16 | `/exam-sessions/{id}/answers/(?P<position>\d+)` | PUT | auth | id, position, answer_ids, known, age_ms, answered_at (≤40) | :118 |
| W17 | `/exam-sessions/{id}/answers/{position}/flag` | PUT | auth | flagged (bool, Pflicht) | :127 |
| W18 | `/exam-sessions/{id}/answers/{position}/reveal` | POST | auth | id, position | :133 |
| W19 | `/exam-sessions/{id}/submit` | POST | auth | id | :134 |
| W20 | `/exam-sessions/{id}/abort` | POST | auth | id | :135 |
| W21 | `/courses/{code}/navigation` | GET | auth | code | TrainerController.php:59 |
| W22 | `/courses/{code}/navigation/(?P<task>\d+)` | GET | auth | task | :60 |
| W23 | `/courses/{code}/navigation-questions/(?P<question>\d+)` | POST | auth | question, answer (≤5000), self_assessment (correct/partly/wrong/null) | :61 |
| W24 | `/courses/{code}/praxis` | GET | auth | code | :64 |
| W25 | `/courses/{code}/praxis/(?P<task>\d+)` | PUT | auth | task, status (known/practice/open) | :65 |
| W26 | `/courses/{code}/videos` | GET | auth | code | :70 |
| W27 | `/courses/{code}/videos/(?P<lesson>\d+)` | GET | auth | lesson | :71 |
| W28 | `/courses/{code}/videos/{lesson}/progress` | PUT | auth | position (≥0), completed (bool/null) | :72 |
| W29 | `/courses/{code}/radio` | GET | auth | code | :75 |
| W30 | `/courses/{code}/radio/texts/(?P<key>…)` | GET | auth | key (≤64), direction (de-en/en-de/'') | :76 |
| W31 | `/courses/{code}/radio/texts/{key}/evaluate` | POST | auth | direction, transcript, translation (≤5000) | :79 |
| W32 | `/courses/{code}/radio/texts/{key}/done` | PUT | auth | done (bool) | :82 |
| W33 | `/courses/{code}/radio/vocabulary` | GET | auth | catalog (SRC/UBI, Pflicht), category (≤191) | :87 |
| W34 | `/courses/{code}/radio/practice` | GET | auth | code | :99 |
| W35 | `/courses/{code}/radio/samples` | GET | auth | code | :100 |
| W36 | `/courses/{code}/radio/spelling` | GET | auth | code | :101 |
| W37 | `/courses/{code}/radio/practice/(?P<key>…)` | GET | auth | key | :102 |
| W38 | `/courses/{code}/radio/practice/{key}/evaluate` | POST | auth | answers (object, Pflicht) | :103 |
| W39 | `/courses/{code}/radio/practice/{key}/done` | PUT | auth | done (bool) | :106 |
| W40 | `/courses/{code}/device-sim` | GET | auth | code | :114 |
| W41 | `/courses/{code}/device-sim/evaluate` | POST | auth | lesson (≤100), step (0–50), text (≤2000) | :115 |
| W42 | `/courses/{code}/device-sim/progress` | POST | auth | lesson, level (1–3), device (`^[a-z0-9-]+$`) | :118 |
| W43 | `/courses/{code}/device-sim/exam` | POST | auth | series (≤50), device, passed (string[] ≤100) | :121 |
| W44 | `/courses/{code}/radio/vocabulary/(?P<key>…)` | POST | auth | known (bool, Pflicht), streak (int/null) | :92 |
| W45 | `/groups/join/(?P<token>[^/]{1,64})` | GET | auth | token | GroupController.php:35 |
| W46 | `/groups/join` | POST | auth | token (`^[gi][0-9a-f]{32}$`) | :36 |
| W47 | `/me/groups` | GET | auth | – | :37 |
| W48 | `/me/groups/(?P<id>\d+)` | PUT | auth (+ Besitzprüfung im Callback) | id, name (≤190) | :38 |
| W49 | `/me/groups/{id}/invite` | POST | auth (+ Besitz) | emails (string[] ≤200, Pflicht) | :39 |
| W50 | `/me/groups/{id}/token` | POST | auth (+ Besitz) | id | :40 |
| W51 | `/me/groups/{id}/members/(?P<member>\d+)` | DELETE | auth (+ Besitz) | member, reason (≤500) | :41 |
| W52 | `/courses/{code}/pruefung-anmelden` | GET | auth | code | ExamGuideController.php:23 |

**Weitere Routen desselben Namespaces außerhalb Bereich D (Querverweis, vom Web-Frontend bzw. Admin genutzt):** `/faqs` GET `__return_true` (src/Faq/Faq.php:114) · `/courses/{code}/customer-faqs` GET auth + Kundenprüfung (src/Faq/CustomerFaq.php:151) · `/tips` GET `is_user_logged_in` (src/Tips/Tips.php:167) · `/webhooks/paypal`, `/webhooks/dispute` POST `__return_true` mit Signaturprüfung im Callback (src/Commerce/Disputes/DisputeWebhooks.php:207–208) · `/monitor` GET `Monitor::allowed` (Schlüssel `BOOTSPRUEFUNG_MONITOR_KEY`, src/Infrastructure/Monitor.php:30). Zusätzlich AJAX `wp_ajax(_nopriv)_bp_rest_nonce` (NonceRefresh.php:24–25) und `wp_ajax_bp_notice_dismiss` (Notices.php:48).

Klassifikation der Web-Routen: W1–W20, W24–W28, W45–W51 reuse/abstract (Abschnitt 2.3); W21–W23, W29–W44, W52 exclude_domain_specific.

### 4.2 Mobile `boatingschool/v1` (MobileApi::routes, src/Mobile/MobileApi.php:298–465; Registrierung :472)

| # | Route | Methode | permission | Tenant | Args | Zeile |
|---|---|---|---|---|---|---|
| M1 | `/health` | GET | `__return_true` | nein | – | 311 |
| M2 | `/auth/login` | POST | `__return_true` | nein | email (≤190), password (≤4096), device_name (≤100), device_platform (ios/android/null), app_version (≤40), device_token (≤200) | 312 |
| M3 | `/auth/refresh` | POST | `__return_true` | nein | refresh_token (≤300, Pflicht) | 318 |
| M4 | `/auth/password/forgot` | POST | `__return_true` | nein | email (Pflicht) | 319 |
| M5 | `/auth/password/reset` | POST | `__return_true` | nein | email, login (≤60), token (≤200, Pflicht), password, password_confirmation (Pflicht) | 320 |
| M6 | `/auth/logout` | POST | requireUser | nein | – | 325 |
| M7 | `/me` | GET | requireUser | nein | – | 326 |
| M8 | `/me/sessions` | GET | requireUser | nein | – | 327 |
| M9 | `/me/sessions` | DELETE | requireUser | nein | – | 328 |
| M10 | `/me/sessions/{sessionId}` | DELETE | requireUser | nein | – | 329 |
| M11 | `/me/deletion-request` | POST | requireUser | nein | – | 330 |
| M12 | `/me/deletion-request/confirm` | POST | `__return_true` | nein | request_id (string/int, Pflicht), confirm_key (≤100, Pflicht) | 332 |
| M13 | `/me/settings` | GET | requireUser | nein | – | 335 |
| M14 | `/me/settings` | PUT | requireUser | nein | Roh-Body `settings`, `expected_version` / If-Match | 336 |
| M15 | `/me/tickets` | GET | requireUser | nein | – | 338 |
| M16 | `/auth/web-session` | POST | requireUser | nein | redirect (≤400, Pflicht) | 339 |
| M17 | `/files/{fileId}` | GET | requireUser | nein | Range/If-Range-Header | 340 |
| M18 | `/profile/export/{exportId}/download` | GET | requireUser | nein | exportId `[0-9a-f]{32}` | 341 |
| M19 | `/me/groups` | GET | requireUser | nein | – | 344 |
| M20 | `/me/groups/{groupId}` | GET | requireUser | nein | – | 345 |
| M21 | `/me/groups/{groupId}/invites` | POST | requireUser | nein | emails (string[] ≤200)/email | 346 |
| M22 | `/me/groups/{groupId}/members/{memberId}` | DELETE | requireUser | nein | reason (≤190) | 349 |
| M23 | `/me/groups/{groupId}/join-code` | POST | requireUser | nein | – (Idempotency-Key optional) | 350 |
| M24 | `/courses` | GET | requireUser | ja | page (1–10 000), per_page (1–100) | 353 |
| M25 | `/courses/{courseId}` | GET | requireUser | ja | – | 354 |
| M26 | `/courses/{courseId}/overview` | GET | requireUser | ja | – | 355 |
| M27 | `/courses/{courseId}/modules` | GET | requireUser | ja | – | 356 |
| M28 | `/courses/{courseId}/progress` | GET | requireUser | ja | – | 357 |
| M29 | `/courses/{courseId}/exam-registration` | GET | requireUser | ja | – | 358 |
| M30 | `/courses/{courseId}/trainer/next` | GET | requireUser | ja | mode (smarttrainer/new/wrong/topic/module/favorites/collection/exam_mistakes/question), module_id, topic, collection_id, exam_id, question_id | 361 |
| M31 | `/courses/{courseId}/trainer/reveal` | POST | requireUser | ja | answer_token (Pflicht) | 365 |
| M32 | `/courses/{courseId}/attempts` | POST | requireUser | ja | answer_token, question_id, revision_id, selected_answer_ids (≤26), known, mode, client_answered_at, response_ms; Header Idempotency-Key Pflicht | 366 |
| M33 | `/courses/{courseId}/attempts/{attemptId}` | GET | requireUser | ja | – | 370 |
| M34 | `/courses/{courseId}/favorites` | GET | requireUser | ja | collection_id, sort (saved_at/assigned_at), order (desc/asc) | 371 |
| M35 | `/courses/{courseId}/mistakes` | GET | requireUser | ja | – | 376 |
| M36 | `/courses/{courseId}/favorites/{questionId}` | PUT | requireUser | ja | collection_id, collection_ids (≤30), context | 377 |
| M37 | `/courses/{courseId}/favorites/{questionId}` | DELETE | requireUser | ja | – | 380 |
| M38 | `/courses/{courseId}/favorite-collections` | GET | requireUser | ja | – | 381 |
| M39 | `/courses/{courseId}/favorite-collections` | POST | requireUser | ja | name (≤200, Pflicht) | 382 |
| M40 | `/courses/{courseId}/favorite-collections/{collectionId}` | PATCH | requireUser | ja | name (Pflicht) | 383 |
| M41 | `/courses/{courseId}/favorite-collections/{collectionId}` | DELETE | requireUser | ja | – | 384 |
| M42 | `/courses/{courseId}/exams` | GET | requireUser | ja | – | 387 |
| M43 | `/courses/{courseId}/exams` | POST | requireUser | ja | mode (official_simulation/simulation/paper/null), paper_id | 388 |
| M44 | `/exams/{examId}` | GET | requireUser | ja | – | 391 |
| M45 | `/exams/{examId}/answers/{questionId}` | PUT | requireUser | ja | selected_answer_ids, known, marked_for_review, age_ms, answered_at | 392 |
| M46 | `/exams/{examId}/answers/{questionId}/reveal` | POST | requireUser | ja | – | 397 |
| M47 | `/exams/{examId}/answers/{questionId}/flag` | PUT | requireUser | ja | flagged (Pflicht) | 398 |
| M48 | `/exams/{examId}/submit` | POST | requireUser | ja | – | 399 |
| M49 | `/exams/{examId}/abort` | POST | requireUser | ja | – | 400 |
| M50 | `/exams/{examId}/result` | GET | requireUser | ja | – | 401 |
| M51 | `/courses/{courseId}/videos` | GET | requireUser | ja | – | 404 |
| M52 | `/videos/{lessonId}` | GET | requireUser | ja | – | 405 |
| M53 | `/videos/{lessonId}/progress` | PUT | requireUser | ja | position_seconds (int/null), completed | 406 |
| M54 | `/courses/{courseId}/navigation-tasks` | GET | requireUser | ja | page, per_page | 410 |
| M55 | `/navigation-tasks/{taskId}` | GET | requireUser | ja | course_id, show_solution (bool) | 411 |
| M56 | `/navigation-tasks/{taskId}/parts/{partId}` | POST | requireUser | ja | course_id, answer (≤5000), self_assessment | 415 |
| M57 | `/courses/{courseId}/praxis` | GET | requireUser | ja | – | 418 |
| M58 | `/courses/{courseId}/praxis/{cardId}` | PUT | requireUser | ja | status (known/practice/open, Pflicht) | 419 |
| M59 | `/courses/{courseId}/radio` | GET | requireUser | ja | – | 426 |
| M60 | `/courses/{courseId}/radio/spelling` | GET | requireUser | ja | – | 427 |
| M61 | `/courses/{courseId}/radio/texts/{textId}` | GET | requireUser | ja | direction | 428 |
| M62 | `/courses/{courseId}/radio/texts/{textId}/evaluate` | POST | requireUser | ja | direction, transcript, translation | 431 |
| M63 | `/courses/{courseId}/radio/texts/{textId}/done` | PUT | requireUser | ja | done | 435 |
| M64 | `/courses/{courseId}/radio/vocabulary` | GET | requireUser | ja | catalog (SRC/UBI/''), category | 436 |
| M65 | `/courses/{courseId}/radio/vocabulary/{cardId}/answers` | POST | requireUser | ja | known (Pflicht), streak | 439 |
| M66 | `/courses/{courseId}/radio/practice` | GET | requireUser | ja | – | 442 |
| M67 | `/courses/{courseId}/radio/practice/{scenarioId}` | GET | requireUser | ja | – | 443 |
| M68 | `/courses/{courseId}/radio/practice/{scenarioId}/evaluate` | POST | requireUser | ja | answers (object, Pflicht) | 444 |
| M69 | `/courses/{courseId}/radio/practice/{scenarioId}/done` | PUT | requireUser | ja | done | 447 |
| M70 | `/courses/{courseId}/radio/samples` | GET | requireUser | ja | – | 448 |
| M71 | `/courses/{courseId}/faqs` | GET | requireUser | ja | – | 451 |
| M72 | `/tips` | GET | requireUser | ja | – | 454 |
| M73 | `/coupons/redeem` | POST | requireUser | ja | code (≤300, Pflicht) | 457 |
| M74 | `/vouchers/check` | POST | requireUser | ja | code (≤100, Pflicht), course_id | 460 |
| M75 | `/vouchers/redeem` | POST | requireUser | ja | code, course_id | 461 |
| M76 | `/groups/join/{token}` | GET | requireUser | ja | token `[A-Za-z0-9]{1,64}` | 463 |
| M77 | `/profile/export` | POST | requireUser | ja | – | 464 |
| M78 | `/profile/export/{exportId}` | GET | requireUser | ja | – | 465 |

`courseId` = `[A-Za-z0-9-]+` (numerische ID oder Kurscode, BaseController.php:101). 78 Routen/Methoden-Kombinationen auf 72 Pfaden – deckungsgleich mit `docs/mobile-api/openapi.yaml` (72 Pfade). Klassifikation: M54–M70, M29, M16 exclude_domain_specific; übrige siehe 2.4.

## 5. Frontend

### 5.1 Shortcodes und Blocks

| Name | Registrierung | Zweck | Klassifikation |
|---|---|---|---|
| `[bootspruefung]` | src/Frontend/Router.php:125 | Knopf „Zum Lernbereich“ | abstract (INV-D-064) |
| `[bootspruefung_zugang]` | src/Gate/ContentGate.php:74 (Bereich A) | Inhalt nur mit Lernzugang | reuse (Querverweis) |
| Block `bootspruefung/zugang` | src/Gate/ContentGate.php:231, Editor-JS assets/admin/restricted-block.js | dito als Block | reuse (INV-D-084) |
| `[bootspruefung_faq]` | src/Faq/Faq.php:42 | öffentliche FAQ-Sektion | replace/exclude (INV-D-056) |
| `[bootspruefung_vertrag_widerrufen]` | src/Commerce/WooCommerce/WithdrawalFunction.php:72 (Bereich B) | Widerrufsbutton | R3 (Querverweis) |

### 5.2 Templates

`templates/app.php`: Hülle für Block-Themes (Template-Teile header/footer vor `wp_head`) und klassische Themes (`get_header/get_footer`); Inhalt: Erinnerungs-Abmeldeseite ohne Login, Login-/Registrierungskarte mit Rückkehr und Gruppeneinladung, Ladehinweis + `<noscript>`. Rewrite: `^bootsscheine/?$` → `bp_app=1`, `^bootsscheine/(.+?)/?$` → `bp_path` (Router.php:81).

### 5.3 Lernerscreens (Client-Router assets/build/app.js:338, Basis `/bootsscheine/`)

| Pfad | View (app.js) | Klassifikation |
|---|---|---|
| `/` | viewOverview :549 („Mein Lernen“) | abstract |
| `/gruppe/<token>` | viewGroupJoin :636 | reuse |
| `/gruppen` | viewGroups :663 | reuse |
| `/tipps` | viewTips :609 | abstract (R2) |
| `/<kurs>/` | viewCourse :929 / renderCourseHub :1029 | abstract |
| `/<kurs>/lernen/?modul&modus&thema&sammlung&pruefung` | viewLearn :1903 | abstract |
| `/<kurs>/smarttrainer/` (alias `themen`) | viewTopics :1207 | reuse |
| `/<kurs>/merkliste/?sammlung&ansicht` | viewSaved :1274 | reuse |
| `/<kurs>/pruefung/` | viewExamHub :1516 | abstract |
| `/<kurs>/pruefung/<id>/` | viewExam :4059 → examRun :4364 / examResult :4660 | reuse |
| `/<kurs>/downloads/` | viewDownloads :1536 | abstract |
| `/<kurs>/video/`, `/<kurs>/video/<id>/` | viewVideos :2968, viewVideoLesson :2972 | reuse |
| `/<kurs>/praxis/?kategorie` | viewPraxis :2855 | abstract (Karteikarten), Inhalt exclude |
| `/<kurs>/knoten/` | viewKnots :1150 | exclude |
| `/<kurs>/pruefung-anmelden/?teil` | viewExamGuide :2333 | exclude |
| `/<kurs>/navigation/[<id>]` | viewNavigation :2659, viewNavigationTask :2701 | exclude |
| `/<kurs>/funk/…` (text, praxis, geraet, buchstabiertafel, muster, vokabeln) | viewRadio :3328 ff., viewDeviceSim :3711 | exclude |
| Server-Seiten: `/erinnerungen` (Abmeldelink, ReminderOptOut), `/datei/<id>/` (geschützte Datei) | templates/app.php:11, Gate | abstract (Bereich A/B) |

### 5.4 JS-Module

| Datei | Inhalt | Klassifikation |
|---|---|---|
| assets/build/app.js | SPA: Helfer (t/el/uuid), `api()` mit Nonce-Erneuerung, Router, alle Views, Prüfungs-Outbox/-Sender, Diagramm, Video mit Consent | abstract (INV-D-070 ff.) |
| assets/admin/import.js | Upload-Vorprüfung + Schrittimport | reuse (INV-D-023) |
| assets/admin/restricted-block.js | Editorblock | reuse (INV-D-084) |
| assets/build/device-sim-a11y.js, assets/device-sim/** | Gerätesimulation | exclude (INV-D-087) |
| Inline-Skripte | Menu::confirmScript (Menu.php:118), Notices::script (Notices.php:268), Laufzeit-Vorbelegung (AccessPages.php:260), Vorschau-Fortsetzung (ImportPage.php:436) | reuse (in Admin-JS überführen) |

## 6. Zustände, Fehlercodes, Auth

### 6.1 Authentifizierung

| Weg | Mechanik | Quelle |
|---|---|---|
| Web-Lernbereich | WP-Login-Cookie + `X-WP-Nonce` (`wp_rest`), Nutzer-ID nur aus Sitzung, nie aus Payload; Nonce-Erneuerung per AJAX `bp_rest_nonce`, Client wiederholt einmal bei 401/`rest_cookie_invalid_nonce` | LearnerController.php:24–26, NonceRefresh.php, app.js:140 |
| Admin | Capabilities `manage_bp_content`, `import_bp_content`, `manage_bp_entitlements` (src/Security/Capabilities.php:12–14), `manage_options` für Protokoll, Einstellungen, Mobile, Livegang; `check_admin_referer` je Aktion | Abschnitt 3.3 |
| App | Bearer-Token `bpa_…` (Access 15 min) / Refresh `bpr_<id>.<rand>.<HMAC>` (30 Tage, absolut 90), Hashes in `bp_mobile_session`, Token nur für `boatingschool/v1`, Cookies/Nonce dort ignoriert; Verwaltungskonten ausgeschlossen (Filter `bootspruefung_mobile_user_allowed`) | MobileApi.php:104–131, TokenAuth.php |
| App → Web | Einmal-Code `bpw_…` 60 s + Prüfwert `bpv_…` im Header `X-Boatingschool-Web-Verifier` | WebSession.php |
| Monitor | Header `X-Monitor-Key`/`?key` gegen `BOOTSPRUEFUNG_MONITOR_KEY` | GoLiveCheck.php:69, Infrastructure/Monitor.php |
| Hinweis-Wegklicken | `wp_hash`-Signatur über Nutzer, Schlüssel, Tage, Version | Notices.php:217 |
| Login-Zähler | geteilte Zähler Web/App (LoginGuard: je IP, E-Mail+IP, E-Mail), Reservierung vor Passwortprüfung, Cookie `bp_known_device` | AuthController.php:56–73, SiteHealth.php:268 |

### 6.2 Fehlercodes

**Web (`bootspruefung/v1`):** `bp_login_required` 401 · `bp_forbidden` 403 · `bp_not_found` 404 · `bp_invalid` 422 (mit `reason` = DomainException-Code, z. B. `exam_expired`) · `bp_rate_limited` 429 (`retry_after`) · `bp_internal` 500 · `bp_import_failed` 409 (Import-Schritt). Kopfzeile `Cache-Control: no-store, private` (LearnerController.php:469–493).

**App (`boatingschool/v1`, Problem.php:34–84, Content-Type `application/problem+json`, Felder code/title/message/request_id, optional errors/details/retry_after):** UNAUTHENTICATED 401 · REFRESH_TOKEN_REUSED 401 · REFRESH_TOKEN_ROTATED 409 · PASSWORD_RESET_INVALID_OR_EXPIRED 422 · TENANT_MEMBERSHIP_REQUIRED 403 · ENTITLEMENT_REQUIRED/EXPIRED/SUSPENDED 403 · CONTENT_LOCKED 403 (details.reason: partial_access, scheduled_release, running_exam) · RESOURCE_NOT_FOUND 404 · VALIDATION_FAILED 422 · IDEMPOTENCY_KEY_REUSED 409 · REQUEST_IN_PROGRESS 409 · QUESTION_TOKEN_EXPIRED 409 · EXAM_NOT_STARTABLE 409 · EXAM_ALREADY_SUBMITTED 409 · EXAM_ABORTED 409 · EXAM_NOT_SUBMITTED 409 · EXAM_EXPIRED 410 · EXAM_ANSWER_LOCKED 409 · COUPON_INVALID 422 · COUPON_NOT_SUPPORTED (nicht mehr gesendet) · COURSE_VOUCHER 422 · COURSE_VOUCHERS_UNAVAILABLE 503 · VOUCHER_INVALID/RESERVED/REDEEMED/EXPIRED/NOT_YET_VALID/WRONG_COURSE/NOT_FOR_COURSES/NOT_ALLOWED · ACCESS_NOT_POSSIBLE 409 · TEMPORARY_ERROR 503 · GROUP_NOT_ACTIVE 409 · RATE_LIMITED 429 · EXPORT_EXPIRED 410 · EXPORT_NOT_READY 409 · EXPORT_FAILED 410 · RANGE_NOT_SATISFIABLE 416 · DELETION_CONFIRMATION_INVALID 422 · VERSION_CONFLICT 409 · WEB_SESSION_DISABLED/NOT_ALLOWED 403 · MOBILE_API_DISABLED 503 · HTTPS_REQUIRED 403 · APP_UPDATE_REQUIRED 426 · METHOD_NOT_ALLOWED 405 · MAINTENANCE · INTERNAL_ERROR 500. Abbildung DomainException → Code: BaseController.php:247–268.

### 6.3 Zustände (soweit in Bereich D sichtbar)

- **Import-Batch:** uploaded → validated → previewed → approved → importing → published; failed, discarded; Sonderzustände „abgebrochen/Mischstand“ (isAborted/isStale, `STALE_AFTER`), „überholt“ (supersededBy), Wiederherstellungs-Import (`rollback_of`) – ImportPage.php:408–516.
- **Zugang (Entitlement):** pending, active, suspended, revoked, expired (+ Marker „manuelle Entscheidung“) – AccessPages.php:731; Client-Status zusätzlich `limited`, `not_granted` (LearnerController.php:287–301).
- **Gruppe:** active, suspended, revoked, expired, pending; Mitglied invited, active, expired (Einladung abgelaufen, auch vor Cron), removed – AccessPages.php:563, GroupController (Web) :130.
- **Prüfungssitzung:** running, evaluated (inkl. `expired`), aborted; Flags `live`, `reduced`; Antwort `applied=false` bei älterem Stand.
- **Rechte-Status:** unknown (ungeklärt), review, licensed, public, restricted; „ungeklärt“ und „gesperrt“ blockieren Auslieferung (Glossary.php:30).
- **Datenexport (App):** pending → ready → expired, failed. **Löschantrag:** pending_confirmation, confirmed, completed.
- **Token-Sitzung:** aktiv, rotiert (Kulanz 60 s), widerrufen (Gründe logout, password_change, password_reset, admin, user, refresh_reuse, session_limit, issue_failed).
- **Speicher-Selbsttest:** private, ok, exposed, unknown, unchecked.

### 6.4 Rate-Limits und Fristen (belegte Konstanten/Aufrufe)

| Zweck | Grenze | Quelle |
|---|---|---|
| App-Login | LoginGuard-Grenzen je IP / E-Mail+IP / E-Mail in 15 min (Konstanten in src/Security/LoginGuard) | AuthController.php:33–36 |
| Refresh | 120 je IP / 15 min | AuthController.php:102 |
| Passwort vergessen | 10 je IP / h; je Konto still über LoginGuard::resetMailAllowed | AuthController.php:125–129 |
| Passwort zurücksetzen | 20 je IP / h | AuthController.php:194 |
| Web-Sitzung | 20 je Nutzer / 15 min | WebSession.php:36–37 |
| Code einlösen | 10 je Konto / h + gemeinsames IP-Limit (Vouchers::hitIpLimit, 30 je IP / h) | CouponController.php:45–48, VoucherController.php:31–32 |
| Kursgutschein check/redeem | 30 je Konto / h + IP-Limit | VoucherController.php:85, :101 |
| Gruppeneinladung (App) | 20 je Nutzer / h, zusätzlich Tagesbudget GroupService::inviteBudget | GroupController (Mobile) :80, :270 |
| Einladungsvorschau (App) | 60 je Nutzer / h | GroupController (Mobile) :129 |
| Datenexport | 5 je Nutzer / Tag, TTL 24 h | PrivacyController.php:56, :36 |
| Löschantrag / Bestätigung | 5 je Nutzer / Tag; 20 je IP / h; 10 je Antrag / h | PrivacyController.php:108, :134, :142 |
| Prüfungsstart / Antworten | in ExamService/LearningService (Bereich A), Retry-After aus `exam-start|<user>` | BaseController.php:271 |
| Idempotenz | 7 Tage, verwaist nach 300 s | Idempotency.php:24–27 |
| Token-Sitzungen | max. 20 je Konto, Cleanup 7 Tage nach Ende | TokenAuth.php:37, :413 |
| Notices | max. 50 Einträge je Nutzer, Vertagung 1–90 Tage | Notices.php:32, :166 |
| Admin-Flash / Bestätigung / Eingabepuffer | 60 s / 10 min / 5 min | Ui.php:23, :49; AccessPages.php:311 |

## 7. Entscheidungsbedarf (Produktentscheidungen für den Auftraggeber)

| Nr. | Frage | Befund im Code | Empfehlung |
|---|---|---|---|
| E1 | **Token-/Client-API (Mobile) in R1?** | LB betreibt neben der Web-API eine zweite, vollständige App-API (78 Routen, Bearer-Tokens, Sitzungsverwaltung, Export, Löschung, Settings-Sync). Kap. 5 schließt native Apps für v1 aus; Kap. 9.4 verlangt für externe Clients ein rotierbares Tokenverfahren. | **Nicht in R1.** R1 liefert eine einzige `course-captain/v1` mit Cookie+Nonce und übernimmt die client-unabhängigen Bausteine (Idempotenz, Rate-Limiter, Problem-Fehlerformat, sichere Dateiauslieferung mit Range). Token-Auth (TokenAuth-Muster) als **R2-Baustein** auf demselben Namespace, sobald ein konkreter externer Client (App, LTI-/Connector) beauftragt ist; kein eigener App-Namespace, kein Tenant-Header. |
| E2 | **Soll der Web-REST-Fehlervertrag schon in R1 auf das stabile Code-Format der App-API (Problem Details) umgestellt werden?** | Web liefert `bp_*`-Codes mit Freitext + `reason`, App 49 stabile Codes. | Ja: ein gemeinsamer, versionierter Fehlerkatalog für `course-captain/v1` ab R1 (SUITE-003). Technische Ausgestaltung als ADR. |
| E3 | **Zeitversetzte Freischaltung (Drip) Free oder Pro?** | LB hat fachneutralen Drip „Modul X Tage nach Zugangsbeginn“ (AccessPages.php:396). Kap. 5 nennt Drip als Pro, Kap. 38 verlangt neutrale LB-Funktionen in R1. | Einfachen Drip je Modul/Lektion relativ zum Zugangsbeginn in **R1 Free** (Parität); Termin-Kampagnen, absolute Daten und Abhängigkeitsketten **Pro**. Kap. 5 entsprechend präzisieren. |
| E4 | **Kostenloser Teilumfang („Vorschau/Test“) je Kurs in Free?** | `free_limited` mit Grenzwert je Funktion (erste n Fragen/Bögen/Lektionen), App-/Web-Sperrgrund `partial_access`. | **R1 Free**, ohne fest verdrahtetes „Schnupper-Paket“; Grenzen pro Kurs konfigurierbar. |
| E5 | **Gruppen-Selbstverwaltung durch die besitzende Person im Frontend (Einladen per Mail, Link erneuern, Platz freigeben, umbenennen) in Free?** | Vollständig im Web und in der App vorhanden, inkl. Tageslimit und Gast-Vorschau. Kap. 43.1: Seat/Claim/Grant-Kern Free, erweiterte Organisation Pro. | Besitzer-Selbstverwaltung **R1 Free** (gehört zum Claim-Kern); delegierte Gruppenadmins, Umbuchen, Massenverwaltung, Berichte **Pro** (R0-29). |
| E6 | **Kurs-FAQ für Eingeschriebene und „Tipps & Tricks“ als Parität R1 oder später?** | Beide existieren neutral nutzbar (CPT + REST + App), sind aber redaktionelle Zusatzbereiche, nicht im Übernahmekatalog Kap. 26.1. | **R2** als optionale Inhaltstypen (Abweichung von der Kap.-38-Regel bewusst beschließen); in R1 genügt Kursbeschreibung/Lektion. |
| E7 | **Rechte-/Lizenzstatus je Inhalt mit Auslieferungssperre in Free?** | Import setzt `rights_status`, „ungeklärt/gesperrt“ wird nicht ausgeliefert, lokale Overrides mit Pflichtbegründung und Audit. | **R1 Free** als schlankes Feld „Nutzungsrecht geklärt“ + Sperre, weil Content-Packages mit Fremdinhalten sonst ungeprüft live gehen (R0-38); Sammelfreigabe mit Pflichtbegründung übernehmen. |
| E8 | **Admin-Protokollansicht (Audit-Viewer mit Filtern und Verlauf je Zugang/Gruppe) in Free?** | Nur für `manage_options`, ohne E-Mail/IP, 4 Jahre Aufbewahrung. | **R1 Free** (Support und Nachweis manueller Grants); Exporte/erweiterte Berichte Pro. Aufbewahrungsfrist als Datenschutz-Einstellung (R0-31). |
| E9 | **Commerce-Zusätze aus LB übernehmen: Mitgliederrabatt für aktive Lernende, Geld-zurück-Garantie mit Lernfortschritt-Schwelle und Download-Sperre?** | Beide eng an WooCommerce und LB-Shopbedingungen gebunden. | Mitgliederrabatt optional im **Woo-Adapter R3 (Pro)**; Geld-zurück-Garantie **nicht übernehmen** (Shop-Policy des Betreibers, Rechtsrisiko), allenfalls später als Woo-Erweiterung neu bewerten. |
| E10 | **Prüfungsmodus „offiziell/verifiziert“ vs. „Übung“ beibehalten?** | LB unterscheidet Prüfungs- und Übungsmodus über ein „fachlich geprüftes“ Regelwerk und zeigt dies Lernenden. | Begriff „verifiziert/offiziell“ streichen; stattdessen Prüfung **veröffentlicht** vs. **Entwurf/Vorschau** (Autorenfreigabe, R1-26). Optionaler Hinweis „Übungsprüfung“ bleibt als Prüfungs-Einstellung. |

**Kein Entscheidungsbedarf** (durch Konzept bereits entschieden, hier nur bestätigt): Ausschluss Funk-/Navigations-/Praxis-Fachtrainer, Gerätesimulation, Seekarten, Prüfungsanmeldung, Bootsproduktkatalog/Preisseite, Universal Links, Tenant; neuer Pfad `/lernen/`; eigenes Design statt `app.css`.

## 8. Klassifikationsverteilung (Abschnitt 2, 144 Zeilen)

| Klassifikation | Anzahl |
|---|---|
| reuse | 54 |
| abstract | 70 |
| replace | 6 |
| exclude_domain_specific | 14 |
| unknown | 0 |

Phasenvorschlag (Hauptzuordnung je Zeile = erste Angabe; Teilaspekte wie „Shop-Teil R3“ oder „Client R2“ stehen in der Zeile): R1-Free 106 · R1-Free oder R2-Pro 1 (Drip, E3) · R2 16 · R3-Pro 5 · exclude 16 (14 exclude_domain_specific + 2 replace ohne Nachfolger in Course Captain: INV-D-056, INV-D-111).

Routenbilanz (Abschnitt 4): Web 52 Routen/Methoden-Zeilen in Bereich D (W1–W52; davon 20 fachgebunden: W21–W23, W29–W44, W52), 6 weitere Web-Routen außerhalb als Querverweis; App 78 Routen/Methoden-Kombinationen auf 72 Pfaden (davon 19 fachgebunden).
