# Course Captain – Produkt- und Umsetzungskonzept

**Status:** Planung / Architekturgrundlage, nicht implementiert  
**Stand:** 15. November 2026  
**Produkt:** Course Captain – universelles WordPress-Lernmanagementsystem (LMS) der Captain Suite  
**Repository:** `hastla007/wp-coursemaster`  
**Free-Plugin / Slug / Textdomain:** `course-captain`  
**Pro-Add-on:** `course-captain-pro`, vorgesehen in `hastla007/captain-pro/addons/course-captain-pro/`  
**Lizenzprodukt:** `course-captain-pro` (im WP License Captain-Produktkatalog neu anzulegen)  
**Entitlement-Präfix:** `course.`  
**Lernbereich:** `/lernen/` (änderbar; stabile Permalinks / Block- und Shortcode-Einbindung)  
**Quellreferenz:** `hastla007/learnboating` – nur analysieren, **nicht verändern**.

> Dieses Dokument trennt nachgewiesene Bestandsmerkmale, geplante Wiederverwendung und neue Produktentscheidungen. Verweise auf andere Captain-Plugins bedeuten nicht, dass passende Verträge heute bereits existieren. Integration und Kompatibilität sind erst nach Code- und E2E-Tests als fertig zu kennzeichnen.

## 1. Vision, Positionierung und Grenzen

Course Captain ist ein **vollständig themenneutrales, WordPress-natives LMS** für Wissenskurse, Prüfungsvorbereitung, Mitarbeiterschulungen, Zertifikatsprogramme, Sprachkurse, berufliche Fortbildung, Onboarding und praxisorientierte Übungen. Ein Kursanbieter erstellt Kurse mit Kapiteln, Lektionen, Medien, Fragen, Lernkarten, Übungen, Prüfungen und individuellen Freischaltungen. Lernende erhalten einen modernen, mobil bedienbaren Lernraum mit Fortschritt, Smarttrainer, Merkliste und Ergebnissen.

**Kernversprechen:** „Kurse erstellen, Wissen systematisch trainieren und Lernerfolge nachvollziehen – direkt in WordPress und ohne zwingenden externen LMS-Dienst.“

Die Bedienung soll so gut sein wie das bestehende Lernsystem in `learnboating`, jedoch **ohne feste Fachdomäne**. Nicht nur Strings umbenennen: Datenmodell, Eingabeeditoren, Bewertung, Veröffentlichungsworkflow, Berechtigungen und externe Schnittstellen müssen domänenneutral gestaltet werden.

**Zielkunden:** Kursanbieter, Online-Akademien, Coaches, interne Weiterbildungsabteilungen, Agenturen und WooCommerce-Shops. **Keine** Bootsführerschein-, Funk-, Navigation-, Prüfungsausschuss- oder amtliche Prüfungsspeziallogik im generischen Produkt. Ein zukünftiges Fachpaket könnte solche Module über die öffentlichen Verträge bereitstellen; es gehört nicht in Core.

### Verbindliche Architekturentscheidungen

- Free ist ein dauerhaft sinnvoll nutzbares, eigenständiges Plugin für WordPress.org. **Keine Lizenzprüfung, kein Updater für Premium, keine künstlichen Kurs-/Teilnehmerlimits in Free.** Bereits freie Funktionen werden nicht nachträglich entzogen.
- Pro ist **ein separates** Premium-Add-on mit eigener Lizenz und Updater über den bestehenden Captain-Pro-Kernel und WP License Captain. Käufer brauchen keinen eigenen Lizenzserver. Der Server selbst bleibt ein separat verkauftes Produkt.
- Lernende sind WordPress-User; Lernstände bleiben in der WordPress-Datenbank. Das System funktioniert ohne WooCommerce, andere Captain-Plugins und Fremd-Cloud.
- Kurse und Entitlements gehören Course Captain. WooCommerce gehört der Kauf; Voucher Captain gehört der Gutschein; Pass Captain gehört das Ticket; Workflow Captain orchestriert optionale Abläufe. **Keine direkten Fremdtabellenzugriffe.**
- Einheitliche API-first-Architektur mit PHP-Service-Layer, versionierter WordPress-REST-API und gut zugänglichem Gutenberg-/Frontend-UI.
- Content-Bearbeitung im WordPress-Admin **ist Pflicht**, nicht bloß ein Import aus GitHub/Laravel. Der Importweg aus learnboating wird zusätzlich für umfangreiche Bestände adaptiert.
- Quellcode ist keine Runtime-Abhängigkeit. learnboating bleibt unverändert, Datenmigration ist nicht automatisch Bestandteil eines Neustarts.
- Bei Pro-Ablauf bleiben freie Funktionen, vorhandene Kurse, Fortschritte, Prüfungsresultate, Lerner-Einsicht und Datenexport erhalten; nur neue lizenzierte Pro-Aktionen fallen weg. Lese-/Exportpfade für Pro-Daten bleiben erhalten, soweit technisch ohne Pro-Kern möglich.
- **Doppelte Lizenzierung verhindern:** Die Lizenz von Course Captain Pro für den Websitebetreiber ist etwas anderes als die Zugangserlaubnis eines Kursteilnehmers zu einem konkreten Kurs.

## 2. Analyse von learnboating – geprüfter Bestand

**Prüfgrundlage:** `learnboating/README.md`, `konzept-wp-lernplugin.md`, `bootspruefung-learning/src/{Learning,Exams,Trainers,Content,Commerce,Gate,Mobile,Rest,Infrastructure,Frontend}`, `docs/schnittstellen-erweiterungen.md`. Dies ist eine Quellcode-/Konzeptprüfung, **keine** durchgeführte Vollmigration oder Live-Abnahme.

Der Bestand enthält ein produktnahes WP-Lernplugin (`bootspruefung-learning/`), Content-Paket-Schemata, Build-Tools, Tests, eine REST-Web-App, gesonderte Mobile-API, Import-/Rollback-Prozesse, WooCommerce-Freischaltungen und Praxis-/Fachtrainer. Der Lernkern ist gut übertragbar, Spezialdaten und Produkteinstellungen nicht.

| Bestehende Fähigkeit / relevante Quelle | Entscheidung für Course Captain | Notwendige Umgestaltung |
|---|---|---|
| `Learning/Services/LearningService.php`: Lernmodus, Fragen, Antworten, persönliche Statistik | **Übernehmen / abstrahieren** | Kurs-/Modul-/Fragen-IDs, fachliche Feldtypen, Benutzertexte und API vereinheitlichen |
| Smarttrainer mit Stufen „neu → unsicher → in Bearbeitung → sicher → gefestigt“, Fälligkeit und Wiederholungsintervallen 7/21/60 Tage | **Übernehmen, konfigurierbar machen** | Algorithmus als LearningStrategy mit Versionierung; Scores und Intervalle als sachlich dokumentierte Standardwerte, pro Kurs ggf. anpassbar |
| `CollectionService.php`: Favoriten, eigene Sammlungen und Fehlerfragen | **Übernehmen** | Generische Lernlisten, Kurszuordnung, Berechtigungsprüfung |
| `Exams/Services/ExamService.php`: Zeitlimit, Session-Snapshot, feste/zufällige Bögen, Teilgrenzen, Wiederaufnahme, idempotente Antwortspeicherung | **Übernehmen / generalisieren** | Generischer Quiz-/Exam-Blueprint, freie Bestehensgrenzen und Fragetypen; keine amtlichen Regeln |
| `Trainers/VideoService.php`: Videolektionen, Fortschritt, Schrittgalerien, Fragen pro Video | **Übernehmen** | Universelle Lektionstypen und Medienprovider, Untertitel/Datenschutz |
| `Trainers/TrainerService.php`: Navigations- und Praxisaufgaben | **Nur Muster übernehmen** | Generische Fallstudien, freie Antworten, Selbsteinschätzung und praktische Aufgaben; feste 7-von-9-Navigationswertung entfernen |
| Funktext, Vokabeln, Funkszenarien, Gerätesimulation | **Nicht als Fachmodule übernehmen** | Allgemeine Karteikarten, Dialog-/Rollenspiel-Trainer und optional erweiterbarer interaktiver Simulationstyp |
| `Content/Package/Package.php`, `ImportService.php`: JSON/ZIP-Pakete, Schemaprüfung, Preview, Batch-Import, Freigabe, Rollback, Medienprüfung | **Übernehmen / neu versionieren** | Neutraler Package Contract v1, keine Boots-Schemata; sichere Importverarbeitung |
| `Infrastructure/Database/Schema.php`: normalisierte Content-, Lern-, Rechte- und Audit-Tabellen | **Als Architekturvorbild** | Neuer `cc_`-Tabellensatz, keine Umbenennung bestehender `bp_`-Tabellen |
| `Commerce/EntitlementService.php`: Quellenreferenzen, Laufzeiten, Status, manuelle Grants, Idempotenz | **Übernehmen / generalisieren** | Rechte auf Kurse, Bundles und ggf. Inhalte statt Boots-Featurecodes |
| `Commerce/GroupService.php`: Mehrfachplätze, Einladungslinks, Gruppenablauf | **Übernehmen**, Free/Pro-Scope gesondert bestimmen | Schulklassen, Teams, Gruppen, Sitzplätze, Einladungs- und Widerrufsregeln |
| WooCommerce-Order-Sync und Reconcile, Refund/Dispute, Produktzuordnung | **Optionaler Commerce-Adapter** | Kaufen/Bezahlen niemals Voraussetzung für kostenloses Lernen; Checkout/HPOS testen |
| `Gate/ProtectedFiles.php`, `ContentGate.php`, `DownloadService.php` | **Übernehmen / härten** | Sichere private Downloads unabhängig von Dateinamen, Prüfung pro Request |
| `Mobile/` mit Bearer-Sessions, Idempotenz, REST-Fehlern | **API-Muster übernehmen** | Generischer Namespace `course-captain/v1`; keine alte `boatingschool/v1`-API übernehmen |
| `Frontend/Router.php` mit App-Shell im aktiven Theme | **Übernehmen / anpassen** | Konfigurierbarer Pfad `/lernen/`, Blocks, zugängliche Fallbacks |
| Audit-, Site-Health-, Wartungs-, Import-, Sicherheits- und E2E-Tests | **Als Test-/Architekturvorbild** | Präfixe und Szenarien neu, Tests der alten Anwendung nicht ungeprüft wiederverwenden |
| Prüfungsausschüsse, Bootsprüfungsanmeldung, SRC/UBI/SBF/SKN/FKN, Navigationskarten, Kurswasser-Theme, spezielle Shoptexte | **Nicht übernehmen** | Keine entsprechende Core-Datenstruktur und keine Bootsprüfungssprache |

**Übernahmeprinzip:** Algorithmus und Tests als Referenz, dann mit eigenen Klassen, IDs, Abstraktionen, Migrationsregeln und sauberer Lizenz-/Copyright-Prüfung portieren. Die neue Anwendung darf **parallel** mit learnboating auf einer WordPress-Installation laufen: unterschiedliche Namespaces, Tabellen, REST-Routen, Hooks, Slugs, Optionen, WP-CLI-Commands und Assets.

## 3. Produktumfang: Kursaufbau und Autorenerfahrung

### 3.1 Generisches Inhaltsmodell

- **Kurs**: Titel, Beschreibung, Vorschaubild, Autor/Trainer, Tags/Kategorien, Sprache, Schwierigkeitsgrad, Dauer, Voraussetzungen, Veröffentlichungszeitraum, Status, Sichtbarkeit, SEO-Metadaten, Lernziele.
- **Abschnitt/Modul**: sortierbare Gliederung, Einführung, optionale Voraussetzungen, Sperr-/Freigaberegeln.
- **Lektion**: Rich Text, Video/Audio, PDF/Download, Bild, Schrittgalerie, eingebettete Inhalte, Ressourcen, Transkript, Aufgaben, Abschlussstatus.
- **Fragenbank**: getrennte, wiederverwendbare, versionierte Fragen und Antworten; tags, Themen, Schwierigkeitsgrad, Erklärungen, Medien und Feedback.
- **Übung**: gezieltes Training, freie Antwort mit Selbstbewertung, Fallstudie, Karteikarten, Dialog-/Simulationsvorlage, Fehlertraining.
- **Quiz und Prüfung**: Fragenpool oder fester Fragenbogen, Zufallsmischung, Zeitlimits, Bestehensgrenze, Teilbereiche, Versuche, Ergebnisfreigabe, Wiederholung, optional Zertifikat.
- **Kursprogramm / Bundle**: mehrere Kurse als ein Lernpfad mit optionalen Voraussetzungen.
- **Lernende**: Einschreibung, Zugangsstatus, Fortschritt, Versuche, Merkliste, persönliche Trainingshistorie.
- **Inhaltsrevision**: stabile Content-UUID, Quelle, Version, veröffentlichte Revision; laufende Prüfungen referenzieren Snapshots.

**Datenregeln:** Kurs kann ohne Quiz existieren, Quiz ohne Video, Lektionen ohne WooCommerce. Inhalte sind kursspezifisch referenzierbar, eine Frage kann in mehreren Kursen vorkommen. Änderungen an Fragen ändern historische Ergebnisse nicht. Für veröffentlichte Inhalte sind Draft/Preview/Publish/Archive und nachvollziehbare Revisionsregeln erforderlich.

### 3.2 Editor und Verwaltung

1. WordPress-nativer Kurs-Builder mit Modulen, Lektionen, Unterkapiteln, Drag-and-drop-Reihenfolge, Duplizieren, Entwurf, Vorschau und Veröffentlichungscheck.
2. WYSIWYG-Lektionen auf Basis zugänglicher Gutenberg-Komponenten und wiederverwendbarer Blöcke.
3. Visueller Frageneditor: Frage/Antwort, Auswahl- und Bewertungsregeln, Medien, Erklärung, Tags, Fragensets, Sammelbearbeitung, Vorschau.
4. Quiz-/Prüfungs-Builder mit Regelvorschau und Prüfung eines ausreichenden, passenden Fragenpools.
5. Medien- und Downloadverwaltung mit privat/öffentlich, Untertiteln und Nutzungsrechten.
6. Kursverwaltung mit Suchfiltern, Status, Sprache, Autor, Teilnehmerzahl und Kurszuordnung.
7. Import/Export als eigenständige, reproduzierbare Pakete; kein Upload von ausführbarem PHP/JS als „Content“.
8. Tutor-/Redakteursrollen mit feingranularen Capabilities, revisionsfähigen Freigaben und Audit-Historie.

### 3.3 Fragetypen

**Free:** Single Choice, Multiple Choice (mit definierter Teilpunkt-/Alles-oder-nichts-Regel), Wahr/Falsch, Zuordnung, Sortieren, Lückentext mit konfigurierbarer Normalisierung, kurze Textantwort mit Selbsteinschätzung, Lernkarte; Bild und Text in Frage/Antwort. **Keine automatische semantische Bewertung offener Antworten ohne gesonderte fachliche Lösung.**

**Pro (Start oder Folgeversion):** komplexe Rubrikbewertung/offene Aufgaben mit Tutor-Feedback, Fragezweige, mehrteilige Fallstudien, interaktive Szenarios und zusätzliche typisierte Question-Plugins. Evaluation muss serverseitig deterministisch und testbar sein. Kein unkontrolliertes JS aus Importpaketen.

## 4. Lernoberfläche – Funktionsparität zur Lernplattform

### 4.1 „Mein Lernen“ und Kurs-Hub

Kurskacheln mit Fortsetzen, Lernziel, sichtbarem Fortschritt und letzten Aktivitäten; Filter/Suche; freie und eingeschriebene Kurse; Kurs-Hub mit Modulen, Lektionen, Fragenbank, Quiz, Prüfung, Downloads und Training. Mobil-first, mit Tastaturbedienung, Screenreader-Labels, verständlichem Fokus und reduziertem Bewegungsmodus.

### 4.2 Lernmodus, Merkliste und Smarttrainer

- Lernen nach Kurs, Modul, Thema oder Schwierigkeitsgrad; alle, neue, falsche, fällige, favorisierte Fragen.
- Sofortfeedback mit fachlicher Erklärung, manuelle Selbstbewertung offener Aufgaben, Lernzeit und Wiederaufnahmepunkt.
- Fortschrittsstufen wie learnboating, einschließlich zeitlicher Entkopplung gegen das schnelle Auswendigklicken und fälliger Wiederholung. Algorithmus versionieren; Umstellungen verändern nicht rückwirkend Historien.
- Intelligente Priorisierung: neu → zuletzt falsch → fällige Wiederholung → schwaches Thema → übrige. Parameter dokumentieren, Grenzfälle testen.
- Eigene Favoriten- und Fehlerlisten, Sammlungen, Trainingsfilter sowie Duplikats-/Mehrtab-Schutz.

### 4.3 Quizzes, Übungen, Prüfungsmodus

- Übungsquiz mit Lösungshinweisen; strikter Prüfungsmodus ohne Lösung bis Freigabe.
- Fester Fragenbogen oder serverseitig zusammengestellter Pool, optional nach Themenanteilen; Zeitlimit und Bestehensgrenzen pro Quiz/Teilgebiet konfigurierbar.
- Session-Snapshot von Fragenrevision, Regeln, Antwortreihenfolge und Auswertung. Nachträgliche Kursänderung darf laufende oder abgeschlossene Versuche nicht umdeuten.
- Autosave, Wiederaufnahme, Fristablauf ausschließlich serverseitig, idempotente Antworten, definierter Toleranzkorridor für verspätete Netzübertragung.
- Abschlussauswertung mit Punktzahl, Themenanalyse, Fehlerfragen, Lernempfehlungen und Verlauf.
- Begrenzung von Versuchen / Zertifikatsprüfungen **optional**, ohne willkürliche Einschränkung des freien Übungsmodus.

### 4.4 Medientraining, Praxis, Szenarien

Videolektionen mit Untertiteln, Zeitposition, Lektionenfortschritt und eingebundenen Quizfragen. Lernkarten, Bildgalerien, Übungsabläufe, Fallstudien, Schrittfürschritt-Anleitungen und frei beantwortbare Praxisaufgaben mit Musterlösung. Fachspezifische Simulationen nur über ein **erweiterbares, sicheres Activity-Provider-Interface**. Keine hartcodierten Geräte-, Navigations- oder Funkprotokolle.

### 4.5 Zertifikate und Lernnachweise

**Free:** nachvollziehbarer Abschlussstatus, Kursfortschritt und exportierbare eigene Ergebnisübersicht. **Pro:** individualisierbare PDF-Zertifikate (Vorlage, Kurs, Person, Ausstellungsdatum, Nachweis-ID, optionale Gültigkeit), Online-Verifikation mit widerrufbarer Token-ID und Datenminimierung; optional aus Bedingungen/Prüfungsergebnis automatisch ausstellen. Ein Anbieterzertifikat ist **kein amtlicher Prüfungsnachweis**, sofern keine externe Berechtigung vorliegt.

## 5. Free-/Pro-Zuschnitt

| Bereich | Course Captain Free (dauerhaft) | Course Captain Pro (Add-on) |
|---|---|---|
| Kurse | Unbegrenzte Kurse, Module, Lektionen, öffentlicher / eingeloggter Zugang | Lernpfade, Abhängigkeiten, Drip-/Terminkampagnen, erweiterte Regeln |
| Inhalt | Editor, Text, Bilder, Video/Audio-Links, Medien, Materialien, Basic-Import/Export | erweiterte Medien-/Content-Workflows, Massenimport, erweiterte Veröffentlichungsregeln |
| Fragen | Fragenbank, Standardfragetypen, Lernmodus, Favoriten | fortgeschrittene Szenarien, Tutorfeedback, zusätzliche Auswertungsregeln |
| Lernen | Smarttrainer, Spaced Repetition, Fehler-/Wiederholungsmodus, Fortschritt | detaillierte Kohortenstatistik, Learning Analytics und Exportberichte |
| Quiz | Übungsquiz und Prüfungssimulation mit fester/zufälliger Auswahl, Zeitlimit, Bestehensgrenze | Prüfungsprogramme mit Retake-/Freigaberegeln, erweiterte Teilbewertungen |
| Teilnehmer | Standard-Einschreibung, manuelle Kursfreigabe, Self-Service-Dashboard | Teams/Kohorten, Gruppenplätze, Einladungs- und Managementfunktionen |
| Zugriff | öffentliche, kostenlose und manuell freigeschaltete Kurse | WooCommerce-Kaufzuordnung, Abos/zeitbasierte Modelle soweit Adapter verifiziert, Voucher-/Pass-Integrationen |
| Nachweise | Abschlussanzeige und eigene Ergebnisse | PDF-Zertifikate, Vorlagen, öffentliche Echtheitsprüfung |
| WordPress | Gutenberg-Blocks, Shortcodes, REST-Basis, Datenschutz, Übersetzungen | Premium-Integrationen, erweiterte Adminrollen, Automationen/Webhooks |

**Wichtige Scope-Regel:** Das bestehende learnboating-Produkt enthält WooCommerce und umfangreiche Gruppenzugänge im eigenen Spezialprodukt. Course Captain kopiert **nicht automatisch** dessen kommerzielle Funktionen in Free. Diese Aufteilung muss vor Umsetzung veröffentlicht und mit dem freien Kernversprechen abgeglichen werden. Keine Monetarisierungsfunktion darf technisch erforderlich sein, um Kurse zu erstellen, zu veröffentlichen und kostenlos zu lernen.

**Nicht in v1:** eigener Stripe-/PayPal-Checkout, native Mobile-Apps, KI-Antwortbewertung, SCORM/xAPI-LRS-Komplettimplementierung, Videokonferenzhosting, Marktplatz, Mandanten-SaaS, offizielle Zulassungs-/Prüfungsregister oder ungesicherte HTML-/JS-Paket-Plugins.

## 6. Lizenzierung: Captain-Suite-Standard

WP License Captain (`hastla007/wp-license`) ist der separate Lizenz**server**; das Add-on in `captain-pro` enthält ausschließlich den eingebauten **Client**. Nicht als Pro-Nutzen bewerben und kein eigener Lizenzserver beim Kunden voraussetzen.

| Eigenschaft | Vorgabe |
|---|---|
| Produktschlüssel / Add-on-Slug / Textdomain | `course-captain-pro` |
| Core-Slug / Textdomain | `course-captain` |
| Rechtepräfix | `course.` |
| Pro-Ort | `captain-pro/addons/course-captain-pro/` |
| Lizenzprüfung | signierter Snapshot, keine Anfrage auf jedem Frontend-Request |
| Offline-Snapshot | wie Captain-Pro-Kernel, maximal 14 Tage bis `cache_expires_at` |
| Lizenzablauf | neue Pro-Schreibaktionen gesperrt, Free & lesender Zugriff bestehen |
| Update | bestehender Captain-Pro-Build und License-Captain-Updater |
| Sicherheit | eigene Namespaces für pro Add-on vendorten Client/Kernel, Ed25519-Verifikation |
| Aktivierungen | Staging/Development gemäß License-Captain-Regeln nicht als Live-Seite |
| Abrechnung | Monats- oder Jahreslizenz technisch; Jahresplan für Verkaufsstart vorgeschlagen, keine Lifetime-Funktion voraussetzen |

**Tarife gemäß tatsächlich dokumentiertem Captain-Pro-Stand** (`captain-pro/docs/TARIFE.md`): `single` (1 Live-Website, standard), `business` (5, standard), `agency` (25, priority). **Alle Course-Captain-Pro-Funktionen in jedem Plan.** Produktpreis für Course Captain Pro noch **nicht** festgelegt; keine fremden Pluginpreise einfach übernehmen. In älteren Konzepten abweichende Namen (`solo/studio/agency`) auf den aktuellen Katalogstand abgleichen.

**Vorgesehene Pro-Entitlements:** `course.advanced_paths`, `course.drip_content`, `course.advanced_assessment`, `course.certificates`, `course.groups`, `course.commerce`, `course.analytics`, `course.suite_integrations`, `course.webhooks`, `course.custom_activities`. Nur aktivierte, implementierte Features in die tatsächliche FeatureRegistry / Katalog-CSV aufnehmen. Jeder Feature-Key gehört zu genau einem Feature.

**Vor Release zwingend:** `captain-pro`-Buildregistry erweitern, Add-on-Konfiguration, Core-Versioncheck, Kursmenü-Unterseite „Pro-Lizenz“, Produktschlüssel und Entitlements im License-Captain-Shop, Hostname, öffentlicher Ed25519-Schlüssel, Konto-URL, Update URI, Lizenz-/Entitlement-E2E-Tests. Keine Produktdaten, Prüfungsantworten oder Kursteilnehmerdaten an den Lizenzserver senden.

## 7. Kurszugänge und Commerce – strikt von der Pro-Lizenz getrennt

### Zugangsmodell

Eine allgemeine `Enrollment/Entitlement`-Service-API bewertet `user_id`, `course_id`, Inhalt/Aktion, `source`, `reference`, `status`, `valid_from`, `valid_until` und `reason`. Quellen: `public`, `free_enrollment`, `manual`, später `woocommerce`, `voucher`, `group`, `api`. Zugang ist serverseitig in jedem privaten API-/Medien-Request zu prüfen. Getrennte Rechte für Ansicht, Download und Prüfung; eine Quellenreferenz darf nur einmal freischalten.

**Status:** `pending`, `active`, `suspended`, `expired`, `revoked` mit definierten Übergängen und Audit. Zwei unabhängige Freischaltungen dürfen sich nicht gegenseitig ungewollt deaktivieren. Ein Refund widerruft genau die referenzierten Kaufrechte; manuelle oder bereits anderweitig bestehende Freigaben bleiben gültig.

### Optionaler WooCommerce-Adapter (Pro)

- WooCommerce-Produkte/Variationen zu Kursen, Lernpfaden und Zugriffsdauern zuordnen; Kurspaket, Startverzug und Fortsetzungsregeln.
- Bestätigte Bezahlung löst idempotente Freischaltung aus; Refund, Storno, Dispute und Rücknahme über Statusmatrix.
- HPOS, klassischer Bestellspeicher, klassischer und Block-Checkout testen; keine direkte SQL-Manipulation an WooCommerce-Bestellungen.
- Kurskauf im Checkout und „Mein Konto → Meine Kurse“ mit transparenter Dauer und Zugriff.
- Reconcile-Job und Admin-Dry-Run für verlorene Hooks; mehrfach ausgelöste Events bleiben idempotent.
- **Kein** Zwang zu WooCommerce im Free; ohne Pro nur freie/manuell freigeschaltete Kurse.

### Teams / Gruppen (Pro)

Mehrplatz-Kauf, Unterrichtsgruppe, Einladungen mit Ablauf, Gruppenadmin, Teilnahme- und Zugangsstatus, Rollen, Entlassung, Umbuchung, Freiplätze und Prüfung des tatsächlichen Seat-Limits unter atomarer Sperre. Keine E-Mail-Adressen unbefristet behalten; Gruppenaktivität darf nicht unbeabsichtigt alle individuellen Fortschritte löschen.

## 8. Integration mit der Captain Suite

**Prinzip:** Eigenständiger Core, optional aktivierte Adapter, ausschließlich öffentliche versionierte PHP-/REST-Verträge und Hooks, klare Ownership, reproduzierbare Idempotenz-Keys, Audit, Fail-safe. Kein globaler Zwang zur Installation aller Suite-Produkte. Vor tatsächlicher Implementierung Verträge beider Seiten anhand des aktuellen Codes verifizieren.

| Plugin | Geplante Verbindung | Ownership und Verfügbarkeit |
|---|---|---|
| **Workflow Captain** (`wp-workflowmaster`) | Events `course.enrollment.created`, `course.lesson.completed`, `course.quiz.passed`, `course.course.completed`, `course.access.expiring`; Aktionen `course.enroll`, `course.revoke`, `course.notify` | Course Captain besitzt Status/Ergebnis; Workflow orchestriert nur. Workflow-Repo enthält bereits Kundenconnectoren für LearnBoating, nicht denselben Vertrag für Course Captain. |
| **Form Captain** (`wp-formmaster`) | optionale Anmeldeformulare, Tutoraufgaben, Feedbackformulare, Kursbewerbungen | Form besitzt Einsendungen; Course Captain vergibt Zugänge nach geprüftem Übergabeauftrag |
| **Voucher Captain** (`wp-couponmaster`) | Kursgutschein verkaufen/einlösen, bestätigte Einlösung → Zugangsgrant | Voucher besitzt Code/Guthaben/Einlösung; Course Captain besitzt Enrollment |
| **Pass/Ticket Captain** (`wp-passmaster`) | Live-Training, Termine, Tickets, Workshop-Abschlussnachweise | Pass besitzt Buchung/Ticket/Check-in; Course Captain berechnet Lernerfolg |
| **Affiliate Captain** (`wpaffiliatemaster`) | Attribution beim Kursverkauf, Provisionen nach bestätigter Bestellung | Affiliate besitzt Partner/Provision; Course Captain speichert keine Provisionen |
| **Review Captain** (`wp-reviewmaster`) | Kursbewertung und optionale Anfrage nach bestätigtem Abschluss | Review besitzt Bewertung, Moderation und externe Plattformen |
| **WP License Captain** (`wp-license`) | Pro-Add-on-Lizenz über Captain-Pro-Client, keine Lernendenlizenz | Lizenzserver besitzt Add-on-Lizenzen; Course Captain besitzt Kursfreigaben |
| **Captain Pro** (`captain-pro`) | `course-captain-pro` als weiterer Build-/Release-Eintrag | Add-on-Kernel, Lizenzclient, FeatureRegistry und ZIP-Auslieferung |

**Adapter-Handshake:** Provider-Identität + Semver, `supports(action/event)`, Eingabeschéma, Capability-Prüfung, idempotente `reference_id`, normiertes Resultat / `WP_Error`. Hook-Namen zuerst im Core-Vertrag `docs/API.md` festlegen; obige Events sind **Vorschläge**, keine vorhandenen WordPress-Hooks.

**Beispielablauf:** WooCommerce-Produkt bezahlt → Commerce-Adapter sendet eindeutige Bestellpositionsreferenz → Course Captain erstellt/erneuert Kurszugang → Lernender schließt Kurs/Prüfung ab → optional Zertifikat → Workflow Captain versendet Hinweis → Review Captain darf eine regelkonforme Anfrage anstoßen. Bei Deaktivierung von Workflow bleibt die direkte Kursfreischaltung erhalten. **Kein** doppeltes Verarbeiten desselben Kaufs durch Workflow und native Bridge; optionaler Ownership-Handshake.

### Verhältnis zu learnboating

- Die vorhandenen `learnboating-voucher-connector`- und `learnboating-affiliate-connector`-Schnittstellen sind **nur Referenzmuster**. Keine alten `bootspruefung_*`-Hooks oder `boatingschool/v1`-Routen in Course Captain.
- Kein automatisches Lesen oder Mutieren von `bp_*`-Tabellen. Ein gesondertes, optionales **Import-/Migrationswerkzeug** könnte später mit Vorschau, Zuordnung, Zustimmung, Backup und Rollback Inhalte migrieren. Keine Vermischung persönlicher Lernstände ohne dokumentierte Identitäts- und Datenschutzentscheidung.

## 9. Technischer Aufbau

### 9.1 Zielverzeichnis und Standards

```text
wp-coursemaster/
  course-captain.php               # Core-Einstieg
  src/
    Admin/                          # Kurs-/Fragen-/Teilnehmerverwaltung
    Content/                        # Curriculum, Fragen, Revisionen, Import
    Learning/                       # Smarttrainer, Favoriten, Progress
    Assessment/                     # Quiz, Prüfungen, Ergebnis-Snapshots
    Enrollment/                     # Zugänge, manuelle Freigabe, Audit
    Media/                          # Downloads, Video, Datenschutz
    Frontend/                       # Lernbereich, Blocks, Shortcodes
    Rest/V1/                        # Versionierte Routen
    Integrations/                   # öffentliche Verträge, Free-Provider
    Infrastructure/                 # Migrationen, Queue, Locks, Monitoring
    Privacy/                        # Exporter, Eraser, Retention
  assets/                           # Versionierte Frontend/Admin-Assets
  languages/
  templates/
  schema/course-package/v1/
  tests/unit, tests/integration, tests/e2e
  docs/                             # API, Data Model, Integrationen, Betrieb
  uninstall.php
  composer.json
  readme.txt

captain-pro/
  addons/course-captain-pro/
    course-captain-pro.php
    config.php
    src/Features/
    uninstall.php
    readme.txt
  # bestehende packages/license-client, packages/addon-kernel,
  # Build-, Test- und Release-Infrastruktur erweitern
```

**Namensraum:** `CourseCaptain\`; **Prefix:** `cc_`; **WP-CLI:** `wp course-captain …`; **REST:** `/wp-json/course-captain/v1/`; **Hooks:** `course_captain_*`. Exakte Konstanten und Signaturen vor Implementierung in `docs/ARCHITECTURE.md` festschreiben. Konfiguration nur in WordPress-Optionen und dokumentierten Konstanten. Keine kopierten `bp_`-Globalnamen, keine unkontrollierten Abhängigkeiten.

Kompatibilitätsziel nach den jüngsten Captain-Suite-Konzepten: **WordPress 6.8+, PHP 8.3+**, WooCommerce optional; Mindest- und aktuelle WP-/PHP-Version in CI testen. Multisite **nicht** als ungeprüfte v1-Zusage; bei späterer Unterstützung konsequent blog-spezifische Daten- und Lizenzisolation.

### 9.2 Datenmodell

Für öffentliche, redaktionelle Inhalte bietet sich eine **hybride Architektur** an: WordPress-CPTs / Block-Editor für Kurse und Lektionen, dedizierte Tabellen für hochvolumige Fragen, Beziehungen, Lernstände, Prüfungs-Snapshots und Freischaltungen. Eine vollständige Custom-Table-Architektur wie learnboating ist möglich, muss aber mit WordPress-Editor und Revisionen bewusst integriert werden.

**Vorgeschlagene Tabellen** (alle mit `$wpdb->prefix . 'cc_'`, exakte DDL vor Entwicklung):
- `cc_question`, `cc_question_revision`, `cc_answer`, `cc_question_tag`, `cc_module_question`
- `cc_course_structure` / `cc_course_module` (falls nicht als CPT/Postmeta); `cc_quiz_rule`, `cc_quiz_pool`, `cc_quiz_paper`
- `cc_learning_attempt`, `cc_attempt_answer`, `cc_question_progress`, `cc_favorite`, `cc_collection`, `cc_collection_item`
- `cc_exam_session`, `cc_exam_session_question`, `cc_lesson_progress`, `cc_course_progress`
- `cc_enrollment`, `cc_access_grant`, `cc_access_event`, `cc_group`, `cc_group_member` (Gruppentabellen erst Pro)
- `cc_import_batch`, `cc_import_log`, `cc_content_revision`, `cc_audit_log`, ggf. `cc_job`

Fremdschlüssel, eindeutige Keys und Indizes für `(user_id,course_id)`, `(question_id,revision)`, `(source,reference_id,course_id,user_id)`, Kurspositionen sowie Status/Fristen definieren. Ausreichend große Typen, UTC-Timestamps, überprüfte Migrationen mit Rückwärtskompatibilität. Keine Speicherung kompletter Medien oder umfangreicher JSON-Blob-Sammlungen in `wp_options`.

### 9.3 Content-Pakete

Neues, domainneutrales Package-Schema `course-captain/v1` für Kurse, Hierarchie, Lektionen, Fragen, Antworten, Pools, Rubriken, Medienmanifest, Dateihashes, Rechte/Attributionen und Version/Provenienz. **Importablauf:** Upload → ZIP-Sicherheitsprüfung (Traversal, Symlinks, Zip-Bomben, Anzahl/Größe, MIME/Extension) → Schema- und Referenzvalidierung → Dry-run/Diff → Betreiberfreigabe → chunkweiser Import unter Lock → atomare Veröffentlichung/Versionierung → Audit → Rollback. Signaturen optional für vertrauenswürdige Anbieter; keine Fremdskripte aus Datenpaketen ausführen. Lokale redaktionelle Overrides und Updates der Quellrevision brauchen eine explizite Konfliktpolitik.

### 9.4 REST, Sicherheit und Performance

- Routen für Katalog/Kurs/Module/Lektionen, Enrollment, Lernmodus (`next/reveal/answer`), Favoriten, Fortschritt, Prüfungen (`start/show/save/submit/result`), private Medien, Content-Import (Admin), Reports.
- WP-Capabilities und `permission_callback` **auf jeder** privaten REST-Route. Nonce für eingeloggte Browser; für externe Clients gesondertes, rotierbares/revokierbares Tokenverfahren; keine Nonce als Ersatz für serverseitige Berechtigung.
- Bearbeiten/Schreiben idempotent (Ids/Idempotency-Key), transaktionale oder sperrengesicherte Zustandswechsel, Rate-Limits bei Antworten/Login/Einladung.
- Sichere Medien außerhalb Webroot bevorzugen; sonst Serverregeln und Streaming-Autorisierung; direkte Medien-URL darf keine Bezahlschranke umgehen. Externe Videodienste mit Klick-zum-Laden/Consent, Untertitel und klaren Dienstanbieterregeln.
- Korrekte REST-/HTML-Escapes, SQL-Prepared Statements, Capability-/Ownership-Prüfungen, CSRF/SSRF-Schutz, importierte URLs nur kontrolliert zulassen, keine ungeprüften ZIP-Dateipfade.
- Cron/Action Scheduler für große Imports, Reconcile, Erinnerungen, Cleanup; Queue-Backoff und Retry mit Monitoring. Keine schweren Jobs auf jeder Frontend-Anfrage.
- Datensparsamkeit, WP-Privacy-Exporter/Eraser, konfigurierbare Retention und Schutz historisch notwendiger Nachweise. Vorhandene Ergebnisse niemals durch Plugin-Update/Pro-Deaktivierung löschen.

## 10. Administrations- und Lern-UX

### Admin „Course Captain“

| Seite | Funktion |
|---|---|
| **Übersicht** | Kurse, Teilnehmer, Lernaktivität, offene Aufgaben, Systemwarnungen |
| **Kurse** | Kurse, Module, Lektionen, Vorschaubilder, Freigaben, Veröffentlichungsregeln |
| **Fragenbank** | Fragen, Fragetypen, Themen, Revisionen, Merkmale, Bulk-Import |
| **Quizzes & Prüfungen** | Pool/Regeln, feste Bögen, Bewertung, Versuche |
| **Lernende** | Kursfreigaben, Fortschritt, Ergebnisübersicht, manuelle Zuordnungen |
| **Inhalte importieren** | ZIP-Import, Schema, Validierung, Vorschau, Publish, Rollback |
| **Gruppen** *(Pro)* | Gruppen, Seats, Einladungen, Administratoren |
| **Zertifikate** *(Pro)* | Vorlagen, Ausstellungen, Widerrufe |
| **Berichte** | Basic im Free; Kohorten, CSV und weitere Auswertungen in Pro |
| **Integrationen** | erkennbare Plugin-Verfügbarkeit, Verbindungs-/Fehlerstatus |
| **Einstellungen** | Permalinks, Lernparameter, E-Mail, Datenschutz, Medien |
| **Systemstatus** | Datenbankschema, Jobs, Medienpfad, Health, Diagnostik |
| **Pro-Lizenz** *(nur Pro)* | Lizenzaktivierung, Status, Site-Limits, Updates |

### Frontend-Screens

Kurskatalog → Kursdetail → Einschreibung/Kaufhinweis → „Mein Lernen“ → Kurs-Hub → Lektion/Video → Lernmodus/Smarttrainer → Quiz/Prüfung → Ergebnis → ggf. Zertifikat. Keine verpflichtende Verwendung des Theme `kurswasser`; aktive Themes respektieren, eigene Styles kapseln, Blocks und Shortcodes vorsehen, offene Kursseiten SEO-kompatibel. Frontend weiterhin bedienbar, wenn JavaScript nicht geladen werden kann (mindestens aussagekräftiger Fallback).

## 11. Roadmap und Abnahme

### Phase 0 – Verträge, Bestand und verbindliche Entscheidungen

- Alle learnboating-Komponenten als Portierungsmatrix mit Tests, Lizenzbedingungen und fachlichen Abhängigkeiten katalogisieren.
- Kompatibilitätsmatrix der Captain Suite und Pro-Build-Infrastruktur festschreiben.
- Datenmodell, Content-Package-v1, IDs, Veröffentlichungs-/Revisionspolitik, LMS-Core/Pro-Matrix und API-Verträge stabilisieren.
- Qualitäts- und CI-Ziele: WP 6.8+, PHP 8.3+, WordPress Coding Standards, PHPStan, PHPUnit, Playwright, echte MySQL/MariaDB.

### Phase 1 – Course Captain Free v1.0

- Admin-Kurs-/Lektions-/Fragen-/Quiz-Builder, öffentliche und manuell freigegebene Kurse.
- Lernraum, Benutzerkonto, Lernmodus, Smarttrainer, Favoriten, Video, Quiz/Prüfung, serverseitige Auswertung.
- Progress/Prüfungsrevisionen, Sicherheits- und Datenschutzwerkzeuge, Block/Shortcode, Übersetzungen.
- Content-Package-Grundformat, Import/Export, Admin-Preview/Dry-run und Rollback; Testpaket ohne Bootsbezug.
- REST/API-Vertrag und für Integrationen nutzbare Free-Grants/Events, ohne externe Pflichtplugins.

### Phase 2 – Course Captain Pro v1.0

- Integration in Captain-Pro-Build, Lizenzkatalog, Ed25519-Client und Updater.
- Erweiterte Lernpfade/Drip/Assessment, Gruppen, Zertifikate, Analytics, WooCommerce-Adapter.
- Native Suite-Adapter zuerst Voucher Captain und Workflow Captain, anschließend Pass/Form/Review/Affiliate nach gesicherten Verträgen.
- Lizenzstatuswechsel mit Read-only- und Fail-safe-Tests.

### Phase 3 – Release und Abnahme

- Voller Lernpfad: Admin erstellt Kurs → Teilnehmer schreibt sich ein → trainiert → wird geprüft → erhält Ergebnis; ohne WooCommerce/Pro/Internetdienste.
- Wiederholungen im Smarttrainer inklusive Zeit-/Mehrtab-/Duplikatsfällen; serverseitige Prüfungsfrist und stabile Evaluation.
- Imports: diff, Schemafehler, große Pakete, abgebrochene Läufe, atomische Veröffentlichung, Rollback, sichere Medien.
- Berechtigungen: Gast darf private Inhalte nicht direkt abrufen; Benutzer A darf nicht auf Fortschritt von B zugreifen; Downgrade, Refund, Lizenzablauf und Löschung verursachen keine Datenkorruption.
- Suite-Adapter: fehlendes Plugin, Plugin-Ausfall, doppelte Events, falsche Reihenfolge, HPOS/Block-Checkout, Updater-Kollisionen.
- Tests: Unit, Integration mit WP/MySQL, E2E in Browser/Mobile, Performance mit großen Fragenbanken, Barrierefreiheit, i18n, Security, WPCS/PHPStan.
- Dokumentierte Anleitung für Installieren, Upgrade, Migration, Backup/Restore, Entitlements, Datenschutz, Betreiberpflichten und Supportdiagnose.

### Folgeversionen

Migrationsassistent für bestehende learnboating-Installationen (optional, keine Änderungen an Quelle), SCORM/xAPI-Import nach Spezifikationsprüfung, zusätzliche Interactive-Activity-Provider, Mobile/PWA-Offline mit konfliktfestem Sync, Tutorfeedback, Mehrsprachigkeit pro Kurs, Kursverkaufsanalysen, Webinar-/Meeting-Provider und mandantenfähige Schulungskohorten.

## 12. Konkrete Entwickler-Abnahmekriterien („Definition of Done“)

1. **Domänenneutral:** Kein harter Verweis auf Bootsprüfungen, SBF, SRC, UBI, Navigation, amtliche Prüfer oder eine fachliche Punkteskala in Core-Code, Datenmodell, UI, Datenpaketen oder Tests.
2. **Gleichwertiger Lernfluss:** Kursübersicht, Lernmodus, Favoriten/Fehlerfragen, Smarttrainer, fällige Wiederholung, Prüfungssimulation, Videos/Quiz und persönlicher Fortschritt sind in den generischen Anwendungsfällen funktionsfähig.
3. **Eigener Content-Editor:** Ein Admin kann ohne GitHub oder Importpaket einen Kurs mit Video, Fragen, Quiz und Bestehensregeln veröffentlichen und testen.
4. **Datenintegrität:** Änderungen an veröffentlichten Fragen/Reihenfolge/Regeln beeinflussen gespeicherte Prüfungssnapshots nicht; derselbe Grant/Answer-Request erzeugt keinen doppelten Datensatz.
5. **Zugangssicherheit:** Private Inhalte, Dateien, Tokens und Resultate sind ausschließlich für berechtigte Accounts abrufbar; Gruppen/Refunds machen keine fremden Zugänge kaputt.
6. **Eigenständigkeit:** Free funktioniert bei deaktivierten WooCommerce-, Captain- und Pro-Plugins; Pro-Deaktivierung lässt Free-Daten und Free-Kursnutzung unangetastet.
7. **Captain-Standard:** Pro wird aus `captain-pro` gebaut, prüft `course.`-Entitlements über den bestehenden signierten Client und lädt keine Premium-Klassen bei fehlendem Core.
8. **Keine Änderungen an Quelle:** `hastla007/learnboating` wird weder zum Portieren noch zum Testen verändert.
9. **Releasefähig:** reproduzierbare ZIPs, Version/Slug/Textdomain, Übersetzungen, Tests, Security-/Privacy-Checks, Upgrade und Downgrade/Restore dokumentiert.

## 13. Offene Entscheidungen vor Implementierungsbeginn

- Preis für Course Captain Pro bei `single/business/agency` (Site-Limits und Funktionsparität sind entschieden, Preis nicht).
- Endgültiger Editor: hybride CPT-/Block-Architektur gegen vollständige Custom-Tables. **Empfehlung:** WordPress-CPTs für Kurs/Lektion, separate Tabellen für Fragen, Fortschritt und Prüfungsdaten.
- Welche komplexen Fragetypen bereits Free v1.0 zuverlässig implementiert werden können (insbesondere Lückentext, Matching, Sortierung) – keine „funktioniert“-Zusage ohne E2E.
- Umfang und Zeitpunkt von Kurs-Gruppen, bezahlten Kursen und Zertifikaten; dieser Entwurf ordnet sie Pro zu.
- Supportumfang und Datenmodell für mehrsprachige Kurse und bestehende Übersetzungsplugins.
- Falls Migration aus learnboating später benötigt: nur Content oder auch Nutzer-/Fortschrittsdaten, ID-Abgleich, Rechte und gesetzliche Aufbewahrung.
- Bewusst **kein** 1:1-Port der Bootsfachtrainer; zukünftige Branchenmodule getrennt konzipieren.

## 14. Primärquellen im Captain-Ökosystem

- [Course Captain Ziel-Repository](https://github.com/hastla007/wp-coursemaster)
- [learnboating – README](https://github.com/hastla007/learnboating/blob/main/README.md) und [technisches Quellkonzept](https://github.com/hastla007/learnboating/blob/main/konzept-wp-lernplugin.md)
- [learnboating – Lernservice](https://github.com/hastla007/learnboating/blob/main/bootspruefung-learning/src/Learning/Services/LearningService.php), [Prüfungsservice](https://github.com/hastla007/learnboating/blob/main/bootspruefung-learning/src/Exams/Services/ExamService.php), [Importservice](https://github.com/hastla007/learnboating/blob/main/bootspruefung-learning/src/Content/Import/ImportService.php), [Entitlementservice](https://github.com/hastla007/learnboating/blob/main/bootspruefung-learning/src/Commerce/EntitlementService.php)
- [Learnboating-Erweiterungen](https://github.com/hastla007/learnboating/blob/main/docs/schnittstellen-erweiterungen.md)
- [Workflow Captain](https://github.com/hastla007/wp-workflowmaster/blob/main/konzept.md), [Form Captain](https://github.com/hastla007/wp-formmaster/blob/main/konzept.md), [Review Captain](https://github.com/hastla007/wp-reviewmaster/blob/main/konzept.md)
- [Ticket Captain](https://github.com/hastla007/wp-passmaster/blob/main/CONCEPT.md), [Voucher Captain](https://github.com/hastla007/wp-couponmaster), [Affiliate Captain](https://github.com/hastla007/wpaffiliatemaster)
- [WP License Captain](https://github.com/hastla007/wp-license), [Captain Pro – Architektur](https://github.com/hastla007/captain-pro/blob/main/docs/ARCHITECTURE.md), [Captain Pro – Tarife](https://github.com/hastla007/captain-pro/blob/main/docs/TARIFE.md), [Captain Pro – Release](https://github.com/hastla007/captain-pro/blob/main/docs/RELEASE.md)

**Repository-Regel:** Dieses Dokument darf im Zielprojekt weiterentwickelt werden. `learnboating` und die übrigen bestehenden Plugins sind Referenzen; jede spätere Änderung dort ist ein separates Vorhaben und nicht durch dieses Konzept autorisiert.
