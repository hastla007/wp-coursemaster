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

**Zielkunden:** Kursanbieter, Online-Akademien, Coaches, interne Weiterbildungsabteilungen, Agenturen und WooCommerce-Shops. **Verbindlicher Ausschluss:** Alle branchenspezifischen Altmodule, Fachprüfungsregeln, Geräteemulationen, Spezialmedien und Spezial-Workflows der Referenzanwendung werden nicht in Course Captain portiert. Es werden ausschließlich fachneutrale Lernfunktionen übernommen.

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
| `Trainers/TrainerService.php`: fachgebundene Praxisaufgaben | **Fachmodule nicht übernehmen** | Nur unabhängig nutzbare Muster für freie Antworten, Selbsteinschätzung und praktische Aufgaben; sämtliche fachspezifischen Bewertungsregeln verwerfen |
| Fachgebundene Dialoge, Wortschätze und Gerätesimulationen | **Nicht übernehmen** | Nur universelle Lernkarten und frei definierbare Dialog-/Szenarioaktivitäten als eigenständige, themenneutrale Funktion |
| `Content/Package/Package.php`, `ImportService.php`: JSON/ZIP-Pakete, Schemaprüfung, Preview, Batch-Import, Freigabe, Rollback, Medienprüfung | **Übernehmen / neu versionieren** | Neutraler Package Contract v1, keine Boots-Schemata; sichere Importverarbeitung |
| `Infrastructure/Database/Schema.php`: normalisierte Content-, Lern-, Rechte- und Audit-Tabellen | **Als Architekturvorbild** | Neuer `cc_`-Tabellensatz, keine Umbenennung bestehender `bp_`-Tabellen |
| `Commerce/EntitlementService.php`: Quellenreferenzen, Laufzeiten, Status, manuelle Grants, Idempotenz | **Übernehmen / generalisieren** | Rechte auf Kurse, Bundles und ggf. Inhalte statt Boots-Featurecodes |
| `Commerce/GroupService.php`: Mehrfachplätze, Einladungslinks, Gruppenablauf | **Übernehmen**, Free/Pro-Scope gesondert bestimmen | Schulklassen, Teams, Gruppen, Sitzplätze, Einladungs- und Widerrufsregeln |
| WooCommerce-Order-Sync und Reconcile, Refund/Dispute, Produktzuordnung | **Optionaler Commerce-Adapter** | Kaufen/Bezahlen niemals Voraussetzung für kostenloses Lernen; Checkout/HPOS testen |
| `Gate/ProtectedFiles.php`, `ContentGate.php`, `DownloadService.php` | **Übernehmen / härten** | Sichere private Downloads unabhängig von Dateinamen, Prüfung pro Request |
| `Mobile/` mit Bearer-Sessions, Idempotenz, REST-Fehlern | **API-Muster übernehmen** | Generischer Namespace `course-captain/v1`; keine alte `boatingschool/v1`-API übernehmen |
| `Frontend/Router.php` mit App-Shell im aktiven Theme | **Übernehmen / anpassen** | Konfigurierbarer Pfad `/lernen/`, Blocks, zugängliche Fallbacks |
| Audit-, Site-Health-, Wartungs-, Import-, Sicherheits- und E2E-Tests | **Als Test-/Architekturvorbild** | Präfixe und Szenarien neu, Tests der alten Anwendung nicht ungeprüft wiederverwenden |
| Branchenspezifische Prüfungsanmeldung, offizielle Prüfungsbezeichnungen, Spezialkarten, Referenz-Theme und Shoptexte | **Vollständig ausschließen** | Keine entsprechenden Core-Tabellen, Fachfelder, Assets, UI-Texte, Defaults, Demoinhalte oder Legacy-Endpunkte |

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

Videolektionen mit Untertiteln, Zeitposition, Lektionenfortschritt und eingebundenen Quizfragen. Lernkarten, Bildgalerien, Übungsabläufe, Fallstudien, Schrittfürschritt-Anleitungen und frei beantwortbare Praxisaufgaben mit Musterlösung. Das **erweiterbare, sichere Activity-Provider-Interface** enthält ausschließlich fachneutrale Verträge. Keine Portierung alter Fachsimulatoren, Fachprotokolle oder Spezialbewertungen.

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

- Vorhandene Voucher-/Affiliate-Connectoren aus dem Referenzprojekt sind **nur Architekturvorbilder**. Keine Legacy-Hooks, Legacy-REST-Routen oder alten Fachkennungen in Course Captain.
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

Kurskatalog → Kursdetail → Einschreibung/Kaufhinweis → „Mein Lernen“ → Kurs-Hub → Lektion/Video → Lernmodus/Smarttrainer → Quiz/Prüfung → Ergebnis → ggf. Zertifikat. Kein Theme oder Design der Referenzanwendung übernehmen; aktive WordPress-Themes respektieren, eigene Styles kapseln, Blocks und Shortcodes vorsehen, offene Kursseiten SEO-kompatibel. Frontend weiterhin bedienbar, wenn JavaScript nicht geladen werden kann (mindestens aussagekräftiger Fallback).

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
- Content-Package-Grundformat, Import/Export, Admin-Preview/Dry-run und Rollback; ausschließlich generische Testpakete, beispielsweise Sprachen, IT, Mitarbeiter-Onboarding und Weiterbildung.
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

1. **Domänenneutral:** Keine branchenspezifischen Altmodule, Fachterminologie, Prüfungsnormen, Bewertungsmaßstäbe, Datenfelder, Assets, Demos oder Legacy-Routen in Core, Pro, UI, Datenpaketen oder Tests. Historische Quellpfade sind ausschließlich in internen Quellen-/Portierungsnotizen zulässig.
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
- **Verbindlich:** Keine Portierung fachgebundener Alttrainer oder Spezialmodule. Neue Aktivitäten müssen aus neutralen Anforderungen heraus konzipiert und unabhängig vom Referenzthema getestet werden.

## 14. Primärquellen im Captain-Ökosystem

- [Course Captain Ziel-Repository](https://github.com/hastla007/wp-coursemaster)
- [learnboating – README](https://github.com/hastla007/learnboating/blob/main/README.md) und [technisches Quellkonzept](https://github.com/hastla007/learnboating/blob/main/konzept-wp-lernplugin.md)
- [learnboating – Lernservice](https://github.com/hastla007/learnboating/blob/main/bootspruefung-learning/src/Learning/Services/LearningService.php), [Prüfungsservice](https://github.com/hastla007/learnboating/blob/main/bootspruefung-learning/src/Exams/Services/ExamService.php), [Importservice](https://github.com/hastla007/learnboating/blob/main/bootspruefung-learning/src/Content/Import/ImportService.php), [Entitlementservice](https://github.com/hastla007/learnboating/blob/main/bootspruefung-learning/src/Commerce/EntitlementService.php)
- [Learnboating-Erweiterungen](https://github.com/hastla007/learnboating/blob/main/docs/schnittstellen-erweiterungen.md)
- [Workflow Captain](https://github.com/hastla007/wp-workflowmaster/blob/main/konzept.md), [Form Captain](https://github.com/hastla007/wp-formmaster/blob/main/konzept.md), [Review Captain](https://github.com/hastla007/wp-reviewmaster/blob/main/konzept.md)
- [Ticket Captain](https://github.com/hastla007/wp-passmaster/blob/main/CONCEPT.md), [Voucher Captain](https://github.com/hastla007/wp-couponmaster), [Affiliate Captain](https://github.com/hastla007/wpaffiliatemaster)
- [WP License Captain](https://github.com/hastla007/wp-license), [Captain Pro – Architektur](https://github.com/hastla007/captain-pro/blob/main/docs/ARCHITECTURE.md), [Captain Pro – Tarife](https://github.com/hastla007/captain-pro/blob/main/docs/TARIFE.md), [Captain Pro – Release](https://github.com/hastla007/captain-pro/blob/main/docs/RELEASE.md)

**Repository-Regel:** Dieses Dokument darf im Zielprojekt weiterentwickelt werden. `learnboating` und die übrigen bestehenden Plugins sind Referenzen; jede spätere Änderung dort ist ein separates Vorhaben und nicht durch dieses Konzept autorisiert.


---

# Ergänzung: Suiteweiter Vollständigkeitsabgleich und verbindliche v1-Spezifikation (15.11.2026)

Diese Ergänzung basiert auf einem erneuten Abgleich mit den Konzepten von Workflow Captain, Form Captain, Review Captain, Ticket Captain, Voucher Captain, Affiliate Captain sowie der Captain-Pro-Architektur. Sie **ergänzt** die Abschnitte 1–14; bei widersprüchlichen Aussagen gelten die ausdrücklich als „verbindlich“ bezeichneten Entscheidungen unten. Funktionen sind geplante Anforderungen, nicht bereits implementiert.

## 15. Wettbewerbsbild, Nutzerreisen und Produktgrenzen

### 15.1 Marktanforderungen statt bloßer Featureliste

Als Vergleichskategorien für die spätere Wettbewerbsprüfung dienen LearnDash, Tutor LMS, LifterLMS, LearnPress, Sensei LMS, MasterStudy und Moodle. Vor einer Preis- oder Featurebehauptung sind deren **aktuelle offizielle** Dokumentation, Editionen und Lizenzbedingungen zu prüfen. Keine unbelegten Preis- oder Marktführerbehauptungen.

| Marktanforderung | Course-Captain-Antwort | Priorität |
|---|---|---|
| Schneller Kurseinstieg ohne Fachwissen | Setup-Assistent, Beispielkurs, visuelles Curriculum, Vorschau | P0 |
| Lernmotivation statt reiner Videoablage | Smarttrainer, Fehlertraining, Wiederholungsfälligkeit, Zielanzeige | P0 |
| Flexible Prüfungen | Wiederverwendbare Fragenbank, Prüfungsregeln, stabile Sessions | P0 |
| Eigenes Branding und WordPress-Nähe | Theme-kompatible Blocks, Layout- und Typografie-Einstellungen | P0 |
| Monetarisierung ohne Lock-in | Optional WooCommerce, manuelle/freie Einschreibung | P1 |
| Organisationen und Schulungen | Gruppen, Tutorrollen, Sitzplätze, Reports, Zertifikate | P1 |
| Datenhoheit | Export, Löschung, nachvollziehbare Revisionen, lokale Daten | P0 |
| Plattformökosystem | Öffentliche Verträge und optionale Captain-Adapter | P0/P1 |

**Positionierung:** Nicht „alles wie jede LMS-Plattform“, sondern **LMS + intelligentem Wissenstraining + Prüfungsengine + Captain-Suite-Automation**. Kein Versprechen amtlicher Zertifizierungen oder rechtssicherer Compliance-Schulungsnachweise ohne fachliche Prüfung.

### 15.2 End-to-End-Nutzerreisen als verbindliche Abnahme

1. **Einzeltrainer:** installieren → Einrichtungsassistent → Kurs anlegen → Module/Lektionen sortieren → Quiz hinzufügen → Vorschau als Lernender → kostenlos veröffentlichen → Testnutzer einschreiben → Fortschritt prüfen.
2. **Lernender:** Kurs finden → anmelden → Lektion bearbeiten → Fragen trainieren → falsche Antworten wiederholen → Prüfung starten/fortsetzen → Ergebnis sehen → später fällige Wiederholung absolvieren.
3. **Akademie:** Team/Kohorte anlegen → Plätze vergeben → Einladungen annehmen → Kurszugang aktivieren → Tutor prüft offene Aufgabe → Fortschrittsbericht → Zertifikat ausstellen.
4. **Shopbetreiber:** WooCommerce-Produkt zuordnen → bestätigter Kauf → genau ein Grant pro Referenz → Refund → betroffenen Grant widerrufen, sonstige Grants erhalten.
5. **Suite-Nutzer:** Kursabschluss → dauerhaftes, minimiertes Ereignis → Workflow-Captain-Aktion → optional neutrale Review-Anfrage nach verifiziertem Lern-/Kurs-Erlebnis; kein Versand ohne Berechtigung.
6. **Administrator bei Ausfall:** Queuefehler sehen → Ursache und Retry prüfen → Jobs gefahrlos erneut ausführen → Audit/Datenschutz einhalten.

## 16. Einrichtung, Admin-Navigation und UX-Spezifikation

### 16.1 Setup-Assistent (Free)

Schritte: (1) Website-/Kurszweck und Standardsprache, (2) Permalink und Katalogseite, (3) Einschreibungsmodus öffentlich/kostenlos/manuell, (4) Lern-/Prüfungsdefaults, (5) Benachrichtigung und Datenschutz, (6) Demo-Kurs erstellen oder überspringen, (7) Bereitschaftsprüfung mit konkreten Fehlermeldungen. **Keine** Lizenzabfrage oder WooCommerce-Pflicht. Erneuter Aufruf ohne Datenverlust; Setup-Fortschritt separat speichern.

### 16.2 Einstellungen mit getrennten Tabs

| Tab | Einstellungen und Regeln |
|---|---|
| Allgemein | Sprache, Kurskatalog, Standardrollen, Zeit-/Datumsformat aus WordPress erben |
| Kursdarstellung | Farben/Typografie nur als scoped Tokens, Karten, Breadcrumbs, Theme-Kompatibilität |
| Lernen | Smarttrainer-Strategie, Wiederholungsintervalle, Fortschrittsregeln, Default-Fragensets |
| Prüfungen | Timer, Versuche, Feedbackfreigabe, Bestehensregeln, Default-Frageselektion |
| Einschreibung | Öffentlicher/kostenloser/manueller Zugang, Kurs- und Rollenrechte |
| E-Mails | Absender aus WP erben, Templates, Testmail, Erinnerungen mit Opt-out |
| Datenschutz | Retention je Datenart, Export, Löschung, Datenminimierung |
| Rollen & Rechte | Capability-Matrix, Kursbesitz, Tutorzugriff, Hochrisikoaktionen |
| Erweitert | Importlimits, Queue, Wartung, Debug mit zeitlichem Ablauf und Redaction |

Pro Tab separat speichern; ungespeicherte Änderungen warnen; feldnahe Validierung und Reset **nur des aktuellen Tabs**. Import/Export, Integrationssecrets, Pro-Lizenz und Diagnose erhalten eigene Seiten statt versteckter Einstellungen. Keine globalen WP-Optionen unnötig duplizieren.

### 16.3 Konkrete Screen-Spezifikation

- **Dashboard:** Setup-Checkliste, aktive Kurse, eingeschriebene Lernende, offene Bewertungen, letzte Abschlüsse, Queue-/Importwarnungen; Free/Pro-Funktionen klar gekennzeichnet, keine blockierenden Upsell-Popups.
- **Kursliste:** Suchfeld, Statusfilter, Bulk-Archivierung mit Bestätigung, „Kurs erstellen“, „Vorschau“, „Duplizieren“.
- **Kurseditor:** linke Curriculum-Navigation, mittlerer Lektionen-/Quiz-Editor, rechte Metadaten/Veröffentlichung, Autosave und Revisionen; Drag-and-drop **plus** Tastaturalternative.
- **Frageneditor:** Fragetyp, Fragetext, Medien, Antworten, Korrektheitsregeln, Erklärungen, Zuordnungen, Vorschau, Warnung bei veröffentlichter Revision.
- **Prüfungseditor:** Blueprint, Zeit-/Punktregeln, Fragemenge, verfügbare Poolgröße, Vorschau der Auswertung und Fehlermeldungen bei unlösbaren Konfigurationen.
- **Lernraum:** Kurskopf, Fortschritt, Fortsetzen-Button, Kapitelbaum, Tastaturbedienung, klare Zustände „gesperrt“, „fällig“, „abgeschlossen“.
- **Prüfung:** sichtbarer Timer, Speicherrückmeldung, Frage-Navigation, Markierung, Abgabebestätigung, sichere Wiederaufnahme.
- **Systemstatus:** Plugin-/Vertragsversionen, DB-Schema, Cron, letzte Jobs, REST, Dateischutz, Update-/Lizenzstatus, anonymisierter Diagnoseexport.

**Design-Gate:** Responsiv bei 320/375/768/1024/1440 px, Kontrast und Fokus, keine ausschließlich farbcodierten Zustände, Screenreader-Ankündigung bei Antwort-/Speicherstatus, reduzierte Animation. WCAG 2.2 AA als Ziel und dokumentierte manuelle Prüfungen.

## 17. Rollen, Rechte, Mandanten- und Inhaltszugriff

**Vorgeschlagene Rollen:** Administrator (technische Konfiguration), Course Manager (alle fachlichen Kurse), Instructor (eigene Kurse und zugewiesene Lernende), Tutor/Assessor (zugewiesene Bewertungen), Learner (eigene Lernaktivität). WordPress-Rollen sind Bündel, **Capabilities** sind die eigentliche Autorisierung.

| Capability (Vorschlag) | Admin | Manager | Instructor | Tutor | Learner |
|---|---|---|---|---|---|
| \`cc_manage_settings\` | Ja | Nein | Nein | Nein | Nein |
| \`cc_manage_courses\` | Ja | Ja | Eigene | Nein | Nein |
| \`cc_publish_courses\` | Ja | Ja | Nach Freigabe | Nein | Nein |
| \`cc_manage_question_bank\` | Ja | Ja | Eigene/Zugeordnete | Lesen | Nein |
| \`cc_grade_assessments\` | Ja | Ja | Zugeordnete | Zugeordnete | Nein |
| \`cc_manage_enrollments\` | Ja | Ja | Zugeordnete | Nein | Nein |
| \`cc_view_course_reports\` | Ja | Ja | Zugeordnete | Zugeordnete | Eigene |
| \`cc_manage_integrations\` | Ja | Ggf. delegiert | Nein | Nein | Nein |

Jede Entscheidung verlangt zusätzlich Objekt-/Kurs-/Gruppen-Ownership. Ein Tutor darf **nicht** alle Teilnehmerdaten der Website lesen. Ein Lernender darf niemals Ergebnis-/Antwort-IDs anderer Nutzer abfragen, auch nicht über direkte Downloads oder Reports. Kurswechsel und Rollenänderung entziehen Zugriff unmittelbar; serverseitige Prüfung in allen APIs. Benutzerdefinierte Rollen dürfen mit WordPress-Rollenverwaltung koexistieren; Deaktivierung darf fremde Rollen nicht zerstören.

## 18. Verbindlicher öffentlicher Integrationsvertrag v1

Wie bei Form und Workflow Captain muss die Integrationsarchitektur **vor** Adapterentwicklung feststehen. Folgende Namen sind **neue, vorgeschlagene Course-Captain-Verträge**, nicht bestehende Hooks.

- \`COURSE_CAPTAIN_CONTRACT_VERSION = '1.0.0'\`, Registrierung nach \`course_captain_loaded_v1\`; inkompatible Major-Version → Adapter deaktivieren und \`incompatible_contract\` im Systemstatus.
- Read-API: \`course_captain_get_course_v1(course_id, scope)\`, \`course_captain_get_enrollment_v1(user_id, course_id, scope)\`, \`course_captain_get_completion_v1(user_id, course_id, scope)\`.
- Write-API: \`course_captain_grant_access_v1(payload, context)\`, \`course_captain_revoke_access_v1(source, reference_id, context)\`, \`course_captain_enroll_v1(payload, context)\`. Rückgabe \`success/code/reference_id/request_id\` oder \`WP_Error\`; klare Fehlercodes \`forbidden\`, \`invalid_scope\`, \`duplicate\`, \`not_found\`, \`incompatible_contract\`, \`retryable\`.
- Ereignisse **nach erfolgreichem Commit**: \`course_captain_enrollment_created_v1\`, \`course_captain_enrollment_revoked_v1\`, \`course_captain_lesson_completed_v1\`, \`course_captain_assessment_submitted_v1\`, \`course_captain_assessment_passed_v1\`, \`course_captain_course_completed_v1\`, \`course_captain_certificate_issued_v1\`.
- Payload nur \`event_id\`, \`event_version\`, \`site_id\`, \`course_id\`, \`user_ref\`, \`source\`, \`reference_id\`, \`occurred_at\`, \`correlation_id\`, \`idempotency_key\` und notwendige Statuswerte; keine kompletten WP-User, E-Mail-Adressen, Prüfungsantworten, Tokens oder Zahlungsdaten.
- **Outbox-Muster:** Ereignis und Zustandswechsel konsistent speichern; Queue übernimmt nach Commit, markiert ausgelieferte Events, retryt mit Backoff und Dead-Letter-Status. PHP-Hooks dürfen nicht allein die einzige zuverlässige Zustellung sein. Event-Consumer deduplizieren \`event_id\`.
- **Aktionen:** vor jeder Ausführung Kurs-/Benutzerberechtigung, Providervertrag, Lizenzstatus und Eingabeschema prüfen. Erfolgreiche Fachaktion nie zurückrollen, nur weil ein optionaler Connector nicht erreichbar ist.
- **Schnittstellenbesitz:** nur Course Captain ändert Enrollment, Lernstand und Resultat; andere Plugins geben geprüfte Befehle ab. Für pluginübergreifende Jobs eindeutig festlegen, ob Course Captain oder Workflow Captain den Versand verantwortet. Keine parallelen Standard-Automationen.

### 18.1 Connector-SDK für individuelle Projekte

Separate lokale WordPress-Connector-Plugins dürfen Events abonnieren, neue Lernaktivitäten/Importquellen registrieren und freigegebene Kursgrants beantragen. Vertrag: Provider-ID, Version, Capability-Matrix, JSON-Schema, Timeout, Idempotenz, Diagnosestatus, Retry und Uninstall. Externe Installationen nur über signierte, scoped REST-/Webhook-Verträge mit Replay-Schutz, HMAC/Signaturrotation, Zeitstempel, Nonce und Egress-Allowlist. Kein dynamisches \`eval\`, keine freien PHP-Callbacks aus dem Admin, keine beliebigen Webhook-URLs ohne SSRF-Schutz.

### 18.2 Suite-Integrationsrezepte mit Ownership

| Auslöser | Besitzer des Ereignisses | Course-Captain-Aktion | Schutzbedingung |
|---|---|---|---|
| Form-Captain-Bewerbung freigegeben | Form Captain | Enrollment nach expliziter Zuordnung | Einreichung ≠ automatische Freigabe |
| WooCommerce-Kauf bestätigt | WooCommerce | Kursgrant | Zahlungsstatus, Item-Referenz, HPOS |
| Voucher eingelöst | Voucher Captain | Kursgrant | tatsächliche bestätigte Einlösung, kein bloßer Kauf |
| Pass-Captain-Teilnahme bestätigt | Ticket Captain | Kurs-/Workshopabschluss oder Grant | Check-in/Teilnahme statt Ticketkauf |
| Kurs bestanden | Course Captain | Workflow-Event, optional Zertifikat | Prüfungs-Snapshot und Status final |
| Kurs abgeschlossen | Course Captain | optional Review-Captain-Erlebnis | eindeutige experience_id, Empfängerberechtigung, neutraler Versand |
| Kursverkauf vermittelt | Affiliate Captain/WooCommerce | keine Lernstandsänderung; Provision separat | kein Provisionstrigger aus Quiz |
| Pro-Lizenz abgelaufen | Captain-Pro-Client | neue Pro-Aktionen pausieren | vorhandene Lern-/Kursdaten erhalten |

**Review-Regel:** Kursabschluss ist nur dann ein verifiziertes Erlebnis, wenn Course Captain die tatsächliche Teilnahme/Abschlussbedingung serverseitig belegt. \`experience_id\` gehört Course Captain; keine Sterne-Vorauswahl, keine Review-Anfrage abhängig von der Punktzahl, kein Versand ohne Kontaktresolver/Opt-out-Prüfung.

## 19. Benachrichtigungen, Kommunikation und Lernmotivation

**Free:** transaktionale Mails bei Einschreibung, Einladung zu manuell freigegebenem Kurs, Abschluss und sicherheitsrelevanter Zugangsänderung (je nach Bedarf); HTML und Klartext, übersetzbare Templates, Testmail, Status und Fehlerprotokoll ohne PII. **Pro:** optionale Erinnerungen an fällige Wiederholungen, Drip-Freigaben, Fristen, Tutor-Aufgaben und Kursablauf, Kohortenkommunikation.

- Trennung transaktional / optional werblich; Kommunikationspräferenzen und Abmeldemöglichkeit für nicht notwendige Nachrichten.
- Ruhezeiten, Zeitzone des Lernenden soweit vorhanden, Frequency-Caps, Deduplizierung, erneute Berechtigungsprüfung direkt vor Versand.
- Queue-basiert, bei Cron-Ausfall sichtbare Warnung, Retry und Abbruch; keine Mails nach Widerruf/Opt-out oder Benutzerlöschung.
- Gamification (Abzeichen, Lernserien, Punkte) als **optionales Folgefeature**, standardmäßig ohne manipulative Rankings und ohne öffentliche Anzeige persönlicher Leistung.

## 20. Datenschutz, rechtliche Grenzen und Sicherheit – konkrete Matrix

| Datenart | Besitzer | Export/Löschung/Retention |
|---|---|---|
| Benutzerprofil | WordPress | WP-Privacy-Exporter/-Eraser, WP-Account-Regeln |
| Einschreibungen, Kurszugänge | Course Captain | Export, Widerruf, Löschung/Anonymisierung nach konfigurierbarer Frist |
| Antworten und Smarttrainerhistorie | Course Captain | persönlicher Export, Löschung; historische aggregierte Statistik nur anonym |
| Prüfungssnapshots, Zertifikate | Course Captain | revisionssicher nach Betreiberzweck, dokumentierte Lösch-/Widerrufsregeln |
| Shop-Bestellungen und Zahlungen | WooCommerce | keine Duplikate von Karten-/Steuerdaten |
| Formularantworten | Form Captain | nur Referenzen und freigegebene Statuswerte in Course Captain |
| Gutschein-/Ticket-/Review-/Affiliate-Daten | jeweiliges Plugin | keine vollständigen Kopien |
| Lizenzaktivierung | Captain-Pro-Client / License Captain | keine Kursinhalte oder Lernendendaten übertragen |
| Diagnoselogs/Queue | Course Captain | kurze konfigurierbare Fristen, PII/Secrets standardmäßig maskiert |

**Zusätzliche Vorgaben:** Verzeichnis-/Medienfreigaben dürfen keine privaten PDFs oder Lösungen über öffentliche Upload-URLs leaken. Bei Zertifikatsverifikation minimaler öffentlicher Datensatz, opt-in/konfigurierbare Sichtbarkeit, Widerruf und Rate-Limit. Minderjährige: keine pauschale Altersprüfung erfinden; für konkrete Schul-/Kinderangebote Datenschutz, Rollen, Eltern-/Betreiberpflichten und Rechtsgrundlage gesondert klären. Datenverarbeitung in EU/DACH: DSGVO, AV-Verträge mit Videodiensten/Mailprovidern, Cookie-/Einwilligungspflichten, Fernabsatz- und Verbraucherinformation für digitale Kursverkäufe vor Launch fachlich prüfen. **Keine Rechtsberatung oder pauschale Konformitätszusage.**

**Bedrohungsmodell:** IDOR bei Ergebnissen/Dateien, CSRF bei Grants, XSS in WYSIWYG/Import, ZIP-Bomben, SSRF bei Medienimport/Webhooks, Brute Force/Rate Limits, Prüfungsspoofing, Token-Leaks, Race Conditions bei Seats und parallelen Prüfungsantworten, Backup-Replay bei Events. Für jede Bedrohung ein automatisierter Negativtest und dokumentierte Abwehr.

## 21. SEO, Auffindbarkeit und externe Inhaltsstandards

- Öffentliche Kursdetailseiten erhalten saubere Permalinks, Titel, Metabeschreibung, OpenGraph, Breadcrumbs und kanonische URLs; geschützte Lektionen, Prüfungsversuche, Ergebnis- und Teilnehmerseiten **noindex**, ohne vertrauliche Inhalte im HTML auszugeben.
- Yoast SEO und andere SEO-Plugins behalten die Zuständigkeit für Canonical, Sitemaps und Meta-Tags. Keine doppelten Tags oder konkurrierenden JSON-LD-Blöcke. Course Captain ergänzt nur kompatibel, soweit vom Betreiber aktiviert und semantisch korrekt.
- \`Course\`-/\`LearningResource\`-/\`BreadcrumbList\`-Schema nur für tatsächlich sichtbare, belegbare Daten; keine erfundenen Bewertungen, Preise oder Abschlüsse. Google-Rich-Result-Eignung getrennt von Schema.org-Gültigkeit prüfen.
- Mehrsprachige Kursinhalte: WordPress-Sprache im UI, Kurs-/Lektionssprache im Content, i18n-Strings, RTL-Fähigkeit; Übersetzungsplugin-Kompatibilität in Testmatrix. Pro-Kurs-Multilingual-Varianten erst nach Modellentscheidung.
- SCORM, xAPI, LTI und H5P **nicht als v1-Kompatibilität behaupten**. Als Folgeintegration nur mit Sicherheits-, Datenschutz-, Import-/Tracking- und Lizenzprüfung. Generische Lernaktivitäten können später ein entsprechendes Provider-SDK nutzen.

## 22. Betriebsmodell, Observability, Backup und Migrationsregeln

### 22.1 Queue- und Fehlerzustände

Jobs \`queued → running → succeeded\` bzw. \`retry_wait → failed/dead_letter/cancelled\`. Jede Aufgabe besitzt eindeutige Job-ID, Source-Referenz, maximale Versuche, Backoff, Zeitbudget, Lock/Lease und sicheren manuellen Retry. Keine persönlichen Antworten in Logs. Reconciliation für WooCommerce-Grants, ausstehende Prüfungen, Gruppenplätze und Ereignis-Outbox. WP-Cron-Fallback mit Hinweis auf echten System-Cron bei wenig Traffic.

### 22.2 Updates und Wiederherstellung

- Migrationen auf DB-Schema-Version, wiederanlaufbar und mit Wartungszustand; alte Daten **nie** vor erfolgreicher Prüfung löschen. Backups vor destruktiven Migrationen empfehlen und Restore-Prozedur dokumentieren.
- Nach Datenbank-Restore: Reconciliation von Grants, Session-Fristen, Importbatches, Event-Outbox und externen Bestellreferenzen; **keine** alten E-Mails, Zertifikate oder Review-Einladungen doppelt versenden.
- Plugin-Deaktivierung: keine Datenlöschung, geplante Jobs stoppen; Pro-Deaktivierung pausiert Pro-Worker. Deinstallation: Daten standardmäßig erhalten, vollständige Löschung nur nach gesonderter, bestätigter Betreiberoption und Datenschutzprüfung.
- Versionen von Core, Pro, Datenbankschema, Content-Package, REST-API und Connectorvertrag getrennt führen. Release-Notizen mit Migrations-/Rollback-Hinweisen.

### 22.3 Performance-Budgets als zu messende Ziele

**Ziele, keine belegten Benchmarks:** Bei 10.000 Lernenden, 100 Kursen und 100.000 Fragen soll die Kursübersicht ohne N+1-Abfragen und mit indexgestützten Paginationen funktionieren. Für eine typische Lernfrage p95 REST-Serverzeit < 300 ms bei definierter Testhardware/Last, Kurs-Hub p95 < 500 ms bei warmem Cache; endgültige Grenzwerte nach reproduzierbarem Lasttest freigeben. Batch-Import großer Pakete ohne PHP-Timeout, paginierte Exporte, keine Volltabellenscans für Dashboard-KPIs. Cache-Schlüssel enthalten Site, User, Kurs, Revision und Access-Scope; bei Grant-/Content-Änderung invalidieren.

## 23. QA-Matrix, Release-Gates und messbare Produktziele

### 23.1 Testfälle

| Testebene | Pflichtfälle |
|---|---|
| Unit | Smarttrainer-Stufen, zeitliche Fälligkeit, Bewertung aller Fragetypen, Teilgrenzen, Zugangsstatus, Capabilities |
| DB/Integration | Migration, Revisions-Snapshots, parallele Antworten/Grants, Gruppen-Sitzplätze, Rollback, Wiederanlauf |
| REST/Security | Gast, falscher User, Tutor fremder Kurs, abgelaufener Token, Rate-Limit, CSRF, SSRF, ZIP-Traversal, XSS |
| WordPress | Neuinstallation, Upgrade, Deaktivierung, Reaktivierung, Uninstall, Multisite explizit nicht unterstützt |
| UI/E2E | Admin-Kursbau, Lernfluss, mobile Prüfung, Tastatur, Screenreader, Consent, fehlendes JS |
| Commerce | WooCommerce HPOS/Legacy, Classic/Blocks, Paid/Zero-Total, Pending/Refund/Partial Refund, doppelte Hooks |
| Suite | jeder Adapter fehlt/aktiv/inkompatibel, Doppelereignis, Queue-Ausfall, Lizenzende, falsche Eventreihenfolge |
| Backup/Restore | Restore vor/ nach Eventversand, keine Doppel-Mails/-Grants, gültige Session-/Tokenregeln |
| Release | reproduzierbares ZIP, WordPress.org-Readme, GPL-/Dependency-Review, Übersetzungen, Lizenzsignaturen, Updates |

### 23.2 Release-Gates

- [ ] Free ohne Pro/WooCommerce/andere Captain-Plugins installierbar und produktiv nutzbar.
- [ ] Admin kann einen **vollständigen** Kurs ohne externen Import anlegen.
- [ ] Lernen/Smarttrainer/Prüfung und Ergebnis auf Mobil/Desktop funktionieren und bestehen Negativtests.
- [ ] Pro-Entitlements, Ablauf/Offline-Cache, Downgrade, Updates und Datenbestand entsprechen Captain-Pro-Vertrag.
- [ ] Jeder Suite-Adapter hat Ownership-, Idempotenz-, Fehler-, Retry- und Privacy-Tests.
- [ ] Admin-Screens, SEO-/Yoast-Kompatibilität, Barrierefreiheit, Übersetzungen und Datenschutz geprüft.
- [ ] Datenmigrationen, Backup/Restore, Importrollback und Cron-Ausfall dokumentiert und getestet.
- [ ] Supporthandbuch, Troubleshooting, öffentliche API, Changelog, Security-Disclosure, Lizenz-/Urheberrechtsinventar vorhanden.
- [ ] learnboating und alle Referenz-Repositories unverändert; Koexistenz auf derselben WordPress-Site getestet.

### 23.3 Messbare Erfolgskriterien (nach Launch)

Aktivierung → erster veröffentlichter Kurs; Anteil erfolgreich abgeschlossener Setup-Assistenten; Kurs-Erstellung ohne Supportkontakt; Lernsession-Fortsetzungsrate; Quiz-Abschlussrate; Anteil erfolgreich verarbeiteter Outbox-Events; Supportfälle je 100 Installationen; Crash-/REST-Fehlerquote; p95-Performance. Messung **lokal und datensparsam**, Telemetrie nach außen nur nach expliziter Zustimmung, keine verdeckte Nutzerverfolgung.

## 24. Priorisierte zusätzliche Umsetzungsliste

### P0 – vor Coding / vor Free-Release
- [ ] Lizenz- und Abhängigkeitsinventar für übernommene learnboating-Komponenten erstellen.
- [ ] Kurs-/Fragen-/Prüfungsmodell und UI-Editor als verbindliche ADR festlegen.
- [ ] Konkrete Capability-/Ownership-Matrix, Import-/Medienbedrohungsmodell und Retention-Matrix abnehmen.
- [ ] Course-Captain-Vertrag v1 mit Schemas, Fehlercodes, Outbox und API-Tests definieren.
- [ ] Setup-Assistent, Demo-Kurs, Kurseditor, Frageneditor, Prüfungseditor und Lernraum umsetzen.
- [ ] Yoast-/Theme-/Gutenberg-/Accessibility- und WordPress-Privacy-Integration testen.
- [ ] Systemstatus, Queue/Retry, Backup/Restore und Import-Rollback implementieren.

### P1 – Pro-Release und Suite
- [ ] Pro-Feature-Registry und Lizenzprodukt im bestehenden Captain-Pro-Build einrichten.
- [ ] WooCommerce-, Voucher-, Workflow-, Form-, Pass-, Review- und Affiliate-Adapter nach Vertrag priorisieren.
- [ ] Gruppen/Seats, Zertifikate, Reports, Drip-Learning und Tutorbewertungen liefern.
- [ ] Preis- und Tarifentscheidung samt Produktseite, Featurevergleich und Updatepfad dokumentieren.
- [ ] Connector-SDK und mindestens ein unabhängiges Beispiel-Connector-Plugin testen.

### P2 – bewusst nach v1
- [ ] Offline-/PWA-Synchronisierung, komplexe Simulationen, SCORM/xAPI/LTI/H5P, KI-Hilfen, Mehrsprachigkeitsvarianten und Migration persönlicher learnboating-Lernstände gesondert spezifizieren.
- [ ] Echte Wettbewerbs-/Preisprüfung und Betreiberinterviews in datierten Entscheidungsvorlagen aktualisieren.

## 25. Zusätzliche Quellen für diesen Abgleich

- [Workflow Captain – Integrationsmodell, Admin, Ausführung, Suite](https://github.com/hastla007/wp-workflowmaster/blob/main/konzept.md)
- [Form Captain – Vertrag v1, Setup, WooCommerce-Fragebogen, UI](https://github.com/hastla007/wp-formmaster/blob/main/konzept.md)
- [Review Captain – Erlebnis-IDs, Privacy, Connectoren, SEO](https://github.com/hastla007/wp-reviewmaster/blob/main/konzept.md)
- [Ticket Captain – Berechtigungen, Security und Release-Gates](https://github.com/hastla007/wp-passmaster/blob/main/CONCEPT.md)
- [Voucher Captain – DACH-/EU-Anforderungen](https://github.com/hastla007/wp-couponmaster/blob/main/SCOPE.md)
- [Affiliate Captain – Integrations-/WP-Konventionen](https://github.com/hastla007/wpaffiliatemaster)
- [Captain Pro – Architektur, Feature-Gating, Release](https://github.com/hastla007/captain-pro/blob/main/docs/ARCHITECTURE.md)

**Hinweis zum Umfang:** Diese Ergänzung definiert fehlende Konzepte, keine stillschweigende Verpflichtung, alle P2-Funktionen in v1 zu liefern. Neue API-Namen sind Vorschläge bis zur verbindlichen Implementierung und zum Abnahmetest.


## 26. Verbindliche Entscheidung: vollständige Entfernung aller fachgebundenen Altbestandteile

**Produktentscheidung (bestätigt):** Course Captain richtet sich universell an **alle Kursanbieter**. Die erste Entwicklungspriorität ist **vollständige Parität der fachneutralen LearnBoating-Lernfunktionen**; bewährte technische Logik soll möglichst weitgehend wiederverwendet werden. **Fachgebundene Altbestandteile werden ausdrücklich ausgeschlossen.** Diese Entscheidung hat Vorrang vor älteren, weiter gefassten Paritätsformulierungen in diesem Dokument.

### 26.1 Klare Trennlinie: Was „Parität“ bedeutet

- **Übernehmen:** generische Kursstruktur, Lektionen, Medien, Lernfragen, Karteikarten, Favoriten, Fehlertraining, Smarttrainer, Wiederholungsintervalle, Prüfungsengine, Prüfungs-Snapshots, Lernfortschritt, Ergebnisse, Zugangsrechte, Gruppenmechanismen, Import/Export, Sicherheits- und Wartungsmechanismen.
- **Abstrahieren:** fachspezifisch benannte Klassen, Datenfelder und Zustandsmaschinen, **sofern** darunter eine unabhängig sinnvolle generische Funktion liegt; deren Verhalten mit neutralen Referenzfällen testen.
- **Nicht übernehmen:** Fachfragenbanken, amtliche Prüfungsprofile, branchenspezifische Trainer, Spezialprotokolle, Geräteabbildungen, Karten-/Spezialmedien, Prüfungsanmeldungen, Fachbegriffe, Logos, Designs, Demo-Daten, Fachprodukte, Spezialbewertungen und Fach-Connectoren.
- **Nicht künstlich ersetzen:** Ein entfallenes Fachmodul erzeugt **keine** Pflicht, sofort einen gleich großen universellen Simulator zu bauen. Allgemeine Dialog-, Fallstudien- oder Aktivitätstypen werden nur umgesetzt, wenn sie einen eigenständigen universellen Nutzen haben.
- **Quellenbelege zulässig:** Der Projektname \`learnboating\` und originale GitHub-Quellpfade dürfen **nur** in Dokumentation, Herkunfts-/Lizenzinventar und Portierungsmatrix erscheinen, nicht in ausgelieferten Produkttexten, Produktcode, REST-URLs, Datenbankschemas, Demokursen oder öffentlichen Assets.

### 26.2 Ausschlussliste und neutrale Zielkomponenten

| Altbestandteil | Entscheidung | Ziel in Course Captain |
|---|---|---|
| Fachbezogene Dialog-/Kommunikationstrainer | Entfällt | Universelle Dialogaktivität nur bei unabhängigem Bedarf |
| Fachbezogene Geräte- und Ablauf-Simulationen | Entfällt | Kein entsprechendes Pflichtfeature |
| Fachkarten, Kartenaufgaben und spezielle Bewertungsschwellen | Entfällt | Generische Bild-/Fallstudienfragen ohne feste Fachbewertung |
| Amtliche Prüfungsordnungen und Prüfungstermine | Entfällt | Konfigurierbare Quiz-/Prüfungsregeln, ohne amtliche Profile |
| Fachgebundene Lehrpläne und Lernpakete | Entfällt | Neutrales Kurs-/Content-Package-v1 |
| Fachgebundene Kursprodukte, Shoptexte und Themes | Entfällt | Eigener Course-Captain-Katalog und WordPress-Theme-Integration |
| Fachgebundene REST-Routen, Hooks, Tabellen und Rollen | Entfällt | \`course-captain/v1\`, \`course_captain_*\`, \`cc_*\`, generische Capabilities |
| Allgemeine Lern-, Quiz-, Medien- und Zugangsservices | Portieren | Neue fachneutrale Services mit Verhaltenstests |

### 26.3 Verbindlicher Portierungsablauf

1. **Inventar:** Jede Klasse, Datenbanktabelle, REST-Route, Datei, jedes Asset und jeder Test erhält das Kennzeichen \`reuse\`, \`abstract\`, \`exclude\` oder \`reference_only\`.
2. **Abhängigkeitsanalyse:** Wenn ein generischer Service auf ausgeschlossene Fachmodule verweist, Abhängigkeit durch neutrales Interface ersetzen oder den nicht benötigten Teil entfernen.
3. **Verhaltenssicherung:** Vor Änderungen neutrale Golden-Master-/Regressionstests für Lernstufen, Fälligkeiten, Bewertungsregeln, Session-Snapshots, Idempotenz und Rechte anlegen.
4. **Portierung:** Nur freigegebene Komponenten in eigene Namespaces und Tabellen übertragen; niemals das Referenz-Repository verändern oder es zur Runtime-Abhängigkeit machen.
5. **Neutrale Testdaten:** Ausschließlich fachneutrale Beispielkurse, z. B. „Grundlagen der Tabellenkalkulation“, „Sprachvokabeln“, „Mitarbeiter-Onboarding“, „Projektmanagement“.
6. **Negativprüfung:** CI-Suche nach verbotenen Legacy-Namen, fachgebundenen Tabellen, Hooks, URLs, Medien, Texten und Paketfeldern in allen ausgelieferten Artefakten. Eine Allowlist gilt nur für interne Quellen-/Migrationsdokumentation.
7. **Paritätsabnahme:** Fachneutrale LearnBoating-Funktion gilt erst als portiert, wenn universeller Anwendungsfall, automatisierte Regression, API-/Rechteprüfung und UI-E2E bestehen. Ausgeschlossene Fachmodule zählen **nicht** als fehlende Parität.

### 26.4 Zusätzliche Release-Gates

- [ ] Kein branchenspezifisches Altmodul oder zugehöriges Asset im Core-/Pro-Release-ZIP.
- [ ] Keine fachgebundenen Demo-Fragen, offiziellen Prüfungsprofile oder hartcodierten Fachbewertungsschwellen.
- [ ] Kein Legacy-Namespace, keine Legacy-REST-Route, kein Legacy-Hook, keine Legacy-Tabelle und keine Legacy-Option.
- [ ] Kein Abhängigkeitsfehler durch entfernte Fachmodule.
- [ ] Alle wiederverwendeten generischen Lernfunktionen besitzen neutrale Referenztests und dokumentierten Paritätsstatus.
- [ ] \`learnboating\` und sämtliche anderen Referenz-Repositories bleiben unverändert.

**Konsequenz für die Roadmap:** Zuerst fachneutralen Bestand inventarisieren und portieren; ausgeschlossene Fachmodule werden **nicht** durch gleichwertige Fachnachbauten ersetzt. Kurs-Builder, generische Lernaktivitäten und Captain-Suite-Integration bleiben Bestandteil der universellen Zielarchitektur.


## 27. Wettbewerbsanalyse – freigegebene Ergänzungen aus der To-do-Liste

**Entscheidung:** Die in `TODO-WETTBEWERBSANALYSE.md` erfassten Funktionskandidaten sind als **verbindlich zu berücksichtigende Produktanforderungen bzw. Evaluierungsaufträge** in diesem Konzept aufgenommen. **P0/P1/P2 sind vorläufige Planungsprioritäten, keine Zusage, dass sämtliche Funktionen bereits in v1.0 umgesetzt werden.** Die fachneutrale LearnBoating-Parität und die Ausschlussregeln aus Kapitel 26 haben Vorrang. Bestehende Konzeptelemente werden erweitert statt doppelt implementiert.

### 27.1 P0 – Universeller Lernkern und Autoren-UX

| Funktion | Anforderung und Abnahmekriterium | Vorläufiger Umfang |
|---|---|---|
| Fachneutrale LearnBoating-Parität | Vollständige Quell-/Ziel-/Testmatrix; fachgebundene Module ausgeschlossen; Regressionen für Smarttrainer, Prüfung und Rechte | Free-Lernkern, soweit in Kapitel 5 geregelt |
| Integrierter Kurs- und Quiz-Builder | Fragen direkt aus Lektionen anlegen, Kursvorschau, Duplizieren, Revisionen, Tastaturbedienung; WordPress-Admin bleibt vollwertig | Free |
| Persönliche Lernnotizen | Private Notizen je Lektion, Suche und Export; Umwandlung in Lernkarten optional; Zugriffsschutz und Datenexport | Free |
| Aufgabenabgabe | Generische Text- und Datei-Einreichung als Activity; sichere Uploads, Abgabestatus, grundlegende Rückmeldung; vorhandene Praxisaufgabenlogik zuerst prüfen | Free-Basis |
| Kursvorlagen und Demokurse | Kursduplikation und importierbare neutrale Vorlagen für Onboarding, Sprachen, IT und Weiterbildung; kein Fach-Altinhalt | Free |

### 27.2 P1 – Professionelle Lehr- und Betriebsfunktionen

| Funktion | Anforderung und Abnahmekriterium | Vorläufiger Umfang |
|---|---|---|
| Gradebook / Notenbuch | Aggregierte Kursübersicht über Quiz, Prüfungen, Aufgaben und manuelle Bewertungen; Export, Rechteprüfung, keine zweite Ergebnis-Wahrheit | Pro |
| Tutorbewertung | Bewertungsraster, Kommentare, Wiedereinreichungen, Auditierbarkeit, Benachrichtigungen | Pro |
| Frontend-Kurs-Builder | Kurs-/Lektions-/Quizpflege für berechtigte Trainer ohne WP-Admin; Entwürfe, Medien, Revisionen und sichere Berechtigungsgrenzen | Pro |
| Mehrere Trainer pro Kurs | Rollen, Verantwortlichkeiten, Kursfreigaben und nachvollziehbare Änderungen | Pro |
| Visueller Zertifikatsdesigner | Vorlagen, Logo, Signatur, dynamische Felder, eindeutige Nachweis-ID, Online-Verifikation und Widerruf; keine amtlichen Versprechen | Pro |
| Kompetenzmatrix | Lernziele, Bearbeitungsfortschritt, Wissensbeherrschung und nachgewiesene Kompetenz getrennt darstellen; versionierbare Bewertungsgrundlagen | Basisdaten fachneutral, erweiterte Auswertung Pro |
| Kursdiskussion und Trainerfragen | Lektionenbezogene Fragen, Moderation, Anti-Spam, Privatsphäre und Benachrichtigungen; Integration vor Doppelentwicklung prüfen | P1, genaue Edition offen |
| Interaktive Medienaktivitäten | Bild-Hotspots, Fragen in Videos, Matching, Sortierung und neutrale Szenarien über Activity-Provider; kein Nachbau ausgeschlossener Fachsimulatoren | Basis nach Parität, Erweiterungen Pro |
| Kurs-Bundles und Mitgliedschaften | Bündel, zeitliche Zugänge und optionale Abonnements über vorhandene Entitlement-/WooCommerce-Verträge; kein WooCommerce-Zwang für Free | Pro |
| Kursbewertungen | Review-Captain-Anbindung über öffentliche Verträge; keine doppelte Datenhaltung; ohne Suite-Plugin kein Funktionsausfall des Lernkerns | Optionaler Pro-/Suite-Adapter |

### 27.3 P2 – Optionale, nachfrageabhängige Erweiterungen

- **Gamification:** Badges, Punkte und Streaks nur nach UX- und Lernwirksamkeitsprüfung; keine Belohnung bloßen Durchklickens.
- **SCORM, xAPI, H5P und LTI:** Lizenz-, Sicherheits-, Interoperabilitäts- und Wartungsprüfung vor Zusage einer Import- oder Runtime-Unterstützung.
- **Erweiterte Kohorten-/Coaching-Funktionen:** erst nach validiertem Bedarf; vorhandene Gruppen-/Trainerfunktionen nicht duplizieren.

### 27.4 Verbindliche Wettbewerbsvalidierung

Vor verbindlicher Feinspezifikation und Release-Planung die aktuellen Versionen von **LearnDash, Tutor LMS, LifterLMS, Sensei LMS, LearnPress und MasterStudy LMS** anhand ihrer offiziellen Dokumentation und möglichst eigener Testinstallationen vergleichen. Pro Funktion dokumentieren: tatsächliche Verfügbarkeit (Free/Pro/Add-on), Version/Prüfdatum, UX, Zugriffsrechte, Performance, Barrierefreiheit, Quellenlink, Course-Captain-Abdeckung und Entwicklungsaufwand. Ein neutraler Referenzkurs mit 10 Lektionen, 50 Fragen, einer Aufgabe und einer Prüfung dient als reproduzierbarer Vergleichsfall.

### 27.5 Reihenfolge, Abhängigkeiten und Entscheidungstore

1. **Zuerst:** fachneutrale LearnBoating-Parität und die Portierungs-/Regressionstests aus Kapitel 26.
2. **Danach bzw. parallel ohne Paritätsrisiko:** P0-Autoren-UX, Lernnotizen, Aufgabenabgabe und Kursvorlagen auf der generischen Activity-/Content-Architektur.
3. **Anschließend:** P1-Funktionen in sinnvollen Abhängigkeiten: Aufgabenabgabe → Tutorbewertung → Gradebook; Kurs-Builder → Frontend-Builder/Mehrtrainer; Lernziele → Kompetenzmatrix; Zertifikatsdaten → Designer.
4. **Nach Marktvalidierung:** P2-Erweiterungen. Keine Umsetzung nur deshalb, weil ein Wettbewerber die Funktion anbietet.
5. **Für jede Funktion:** vorhandenen LearnBoating-/Suite-Bestand prüfen, Datenhoheit und Sicherheit festlegen, Free/Pro-Grenze gegen Kapitel 5 abgleichen, UX-/API-/E2E-Abnahme definieren und Aufwand schätzen.

**Pflegehinweis:** `TODO-WETTBEWERBSANALYSE.md` bleibt die operative, abharkbare Umsetzungsliste. Dieses Kapitel ist die fachliche Zielbeschreibung; Änderungen an Status oder Reihenfolge in der To-do-Liste ändern nicht stillschweigend die Anforderungen.

## 28. Ergebnis der simulierten Drei-Experten-Review – Architektur, Didaktik und Produktsteuerung

**Status:** Verbindliche Ergänzung zu den bestehenden Kapiteln 1–27. Die drei Perspektiven sind **simulierte Fachrollen**, keine extern engagierten oder interviewten Personen. Die vorliegenden Vorschläge wurden anhand der vorhandenen Konzept- und To-do-Dateien diskutiert; sie sind **keine Aussage über bereits implementierten Code**. Bei Konflikten gelten die Ausschlussregeln aus Kapitel 26 und die Priorität der fachneutralen LearnBoating-Parität.

### 28.1 Moderierte Streitpunkte und Entscheidungen

| Perspektive | Kritische Frage | Gemeinsamer Beschluss |
|---|---|---|
| WordPress-Architektur | Wie bleiben Prüfungen und Lernstände korrekt, wenn Inhalte nachträglich bearbeitet werden? | Versionierte, unveränderliche Veröffentlichungs-/Prüfungsreferenzen und explizite Migrationsregeln |
| Lernwissenschaft | Wann bedeuten „abgeschlossen“, „gelernt“ und „bestanden“ wirklich unterschiedliche Dinge? | Drei getrennte, erklärbare Fortschrittsdimensionen mit konfigurierbaren, versionierten Kriterien |
| Produktstrategie | Gefährden zusätzliche Wettbewerbsfunktionen die Veröffentlichung? | Paritäts-Gate vor Zusatzmodulen; P0-Backlog in Muss-vor-Release und unmittelbar-danach aufteilen |
| Architektur vs. Produkt | Muss jede Suite-Integration von Anfang an funktionieren? | Eigenständiger Free-Kern; Adapter einzeln nach Vertrags-, Ausfall- und Rückwärtstests freigeben |
| Didaktik vs. Technik | Soll die Smarttrainer-Logik sofort verbessert werden? | Zuerst beobachtbare Verhaltensparität; neue Algorithmen erst nach separater Evaluation und Versionierung |
| Produkt vs. Didaktik | Sind Gamification und KI notwendig, um wettbewerbsfähig zu sein? | Nein; keine v1-Pflicht, zuerst echte Lernqualität und Autoren-UX messen |

### 28.2 ADR-Register: Architekturentscheidungen vor Implementierung

Für jede Entscheidung ein kurzes ADR (Architecture Decision Record) mit **Kontext, Optionen, Entscheidung, Folgen, Migrationspfad, Testbeweis und Datum** erstellen. Folgende ADRs sind Pflicht:

1. **ADR-001 Inhaltsspeicherung:** CPT/Block-Editor vs. Custom Tables vs. Hybrid; Revisionen, Suche, WP-REST, WP-CLI und Rechte berücksichtigen.
2. **ADR-002 Lern- und Prüfungsdaten:** Tabellen für Versuche, Antworten, Lernzustände, Zugänge und Audit-Ereignisse; Indizes, eindeutige Schlüssel, Retention.
3. **ADR-003 Inhaltsrevisionen:** Veröffentlichte Kurs-/Fragenrevisionen erhalten stabile IDs; abgeschlossene Prüfungen referenzieren unveränderliche Snapshots. Korrekturen erfolgen als neue Revision.
4. **ADR-004 Activity-Provider:** stabile Schnittstelle für Rendern, Validieren, Antworten, Bewerten, Fortschritt und Berechtigungen; keine Fachmodule im Core.
5. **ADR-005 Smarttrainer-Portierung:** fachneutrale Golden-Master-Tests, versionierte Regeln, nachvollziehbare Fälligkeit, keine stille Neubewertung alter Lernhistorie.
6. **ADR-006 Background Jobs:** Queue/Outbox, Idempotenzschlüssel, Retry mit Backoff, Dead-Letter-Ansicht, WP-Cron-Fallback und Wiederanlauf.
7. **ADR-007 Lizenz-/Produktzugang:** strikt getrennte Zustände für Course-Captain-Pro-Lizenz und Lernenden-Einschreibung; Graceful Degradation.
8. **ADR-008 Rollen und Datenschutz:** capabilities statt bloßer Rollenchecks, Datenexport/-löschung, Aufbewahrung und Einwilligungs-/Rechtsgrundlagen je Datenkategorie.

### 28.3 Verbindliche Daten- und Zustandsinvarianten

- **Prüfungsintegrität:** Start eines Versuchs fixiert Fragen-/Antwortversionen, Reihenfolge, Zeitlimit, Bewertungsregeln und ggf. Zufalls-Seed; spätere Autorenänderungen ändern keine abgeschlossenen Resultate.
- **Idempotenz:** Wiederholte Antwortübertragung, Prüfungseinreichung, Einschreibung und Eventzustellung erzeugen keine doppelten Ergebnisse oder Berechtigungen.
- **Zugriffsrechte:** Bei jeder Lese-/Schreibaktion gelten Objektberechtigungen und Kurszugang; direkte Objekt-IDs erlauben keinen Zugriff auf fremde Daten.
- **Konsistente Zustandsübergänge:** `draft → published → archived` für Content; definierte Versuchszustände (`created → in_progress → submitted → evaluated` sowie `expired/cancelled`); Übergänge sind serverseitig validiert.
- **Fortschritt:** `completion` (bearbeitet), `mastery` (wiederholt/beherrscht) und `assessment` (nachgewiesen) bleiben getrennt; keine automatische Kompetenzzusage allein durch Videoaufrufe.
- **Rückwärtskompatibilität:** Schema- und Algorithmusversionen sind nachvollziehbar; Rollback- und Wiederanlauftests sind Release-Gates.
- **Quelltrennung:** LearnBoating ist ausschließlich Referenz/Portierungsquelle; keine Änderungen am Referenzrepository, keine Laufzeitabhängigkeit, keine fachspezifischen Altmodule.

### 28.4 Pädagogische Abnahmeregeln und Autoren-UX

1. **Abschlussregeln je Activity:** z. B. Ansicht, explizite Bestätigung, Quiz-Mindestpunktzahl, Tutorfreigabe oder kombinierte Bedingungen; Status und Begründung sichtbar.
2. **Bewertungstransparenz:** Teilpunkte, Mehrfachversuche, Fragerandomisierung, Bestehensschwellen, Bewertungsrevisionen und manuelle Korrekturen sind eindeutig dokumentiert; Änderungen mit Audit-Eintrag.
3. **Aufgaben-Workflow:** `draft/submitted/returned/graded`; Upload-Typen, Dateigrößen, Malware-/MIME-Prüfung, private Speicherung, Wiedereinreichung und Rückmeldungen.
4. **Kompetenznachweise:** Lernzielzuordnung auf Frage-/Activity-Ebene; Nachweise nennen Quelle, Zeitpunkt, Version und Gültigkeit. Bei fehlenden Nachweisen keine erfundene Beherrschungsquote.
5. **Autoren-UX-Test:** Mindestens drei unterschiedliche neutrale Kursfälle (Selbstlernkurs, Prüfungsvorbereitung, Onboarding) mit Kursstruktur, Video, 50 Fragen, einer Aufgabe und einer Prüfung erstellen; Zeit, Fehler, Verständlichkeit und Tastaturbedienung erfassen.
6. **Lernenden-UX-Test:** Anmeldung, Fortsetzen, fällige Wiederholung, Prüfungspause/Wiederaufnahme, Ergebnisansicht und Datenexport auf Desktop/Mobil einschließlich Screenreader- und Tastaturpfaden testen.

### 28.5 Free-/Pro- und Suite-Integrationsregeln

- **Free ist eigenständig:** Kurse, Lektionen, Basisquiz, fachneutraler Smarttrainer und persönlicher Fortschritt funktionieren ohne Pro, WooCommerce oder Suite-Plugins.
- **Pro erweitert den Betrieb:** Mehrtrainer, Frontend-Autorenworkflow, Gradebook, komplexe Zertifikate, Gruppen/Commerce und erweiterte Auswertungen; jede Edition-Zuordnung vor Release mit Kapitel 5 abgleichen.
- **Keine Daten-Duplikate:** Course Captain ist System of Record für Lern- und Prüfungsergebnisse; Zahlungs-, Gutschein- und Review-Daten bleiben bei ihren jeweiligen Eigentümern.
- **Adapter-Abnahme:** Contract-Tests, deaktivierte/fehlende Partnerplugins, Zeitüberschreitungen, Event-Duplikate, Rückruf-/Retry-Verhalten und Upgrade-Matrix prüfen.
- **Keine Sperre durch Lizenzserver:** Pro-Lizenzfehler dürfen weder Free-Lernen noch bestehende Lernhistorien blockieren.

### 28.6 Release-Zuschnitt und messbare Gates

**Gate A – Portierbarkeit:** Inventar aller fachneutralen Quellfunktionen, Lizenz-/Abhängigkeitsprüfung, dokumentierte Ausschlüsse und ausführbare Referenztests.

**Gate B – Free-Lernfluss:** Ein neutraler Kurs kann erstellt, veröffentlicht, eingeschrieben, gelernt, wiederholt und geprüft werden; Ergebnis und Fortschritt bleiben nach Inhaltsänderung konsistent.

**Gate C – Betriebsreife:** Rollen-/IDOR-Tests, CSRF/Nonce, Upload-Sicherheit, Datenschutzexport/-löschung, WP-/PHP-Kompatibilität, Upgrade/Rollback, Performance-Baseline, Accessibility und Backup-Restore bestanden.

**Gate D – Erweiterungen:** P0-Zusatzfunktionen werden einzeln nach Nutzen, Abhängigkeiten und Aufwand freigegeben. P1-Pro-Module erst nach stabiler Free-Basis; P2 nur bei validierter Nachfrage.

**Messgrößen (Ziele, noch keine gemessenen Resultate):** Quote erfolgreicher Kurs-Erstellungen, Zeit bis zum ersten veröffentlichten Kurs, Abschlussquote des Referenzkurses, Fehlerquote bei Prüfungsabgabe, wiederherstellbare Jobs, Supportanfragen je 100 aktive Installationen und Free→Pro-Konversion. Vor dem Launch Baselines und Messmethode festlegen; keine erfundenen Zielerreichungen ausweisen.

### 28.7 Offene Fragen für die nächste Entscheidung

1. **Entschieden:** Zuerst vollständige fachneutrale LearnBoating-Parität und betriebsfähiger Basis-Lernfluss; zusätzliche P0-Wettbewerbsfunktionen erst **danach**. Diese Frage ist geschlossen.
2. Werden Kursanbieter primär auf **einer** WordPress-Website mit vielen Trainern arbeiten oder sollen Multisite-/Agentur-Szenarien schon in v1 vollständig unterstützt werden?
3. Sollen Lernende Zertifikate und Kompetenznachweise dauerhaft verifizieren können, auch wenn ein Pro-Abonnement ausläuft? Die bereits festgelegten Lese-/Exportrechte müssen dabei gewahrt bleiben.

**Umsetzung:** Die operative, priorisierte und abharkbare Liste befindet sich weiterhin in `TODO-WETTBEWERBSANALYSE.md`. Die zusätzlichen Architektur-/Qualitätsaufgaben dort verweisen auf dieses Kapitel; die bereits dokumentierten Wettbewerbsfunktionen bleiben erhalten.

## 29. Verbindliche Release-Strategie: Parität vor zusätzlichen P0-Funktionen

**Auftraggeberentscheidung:** Zuerst wird die **vollständige fachneutrale LearnBoating-Funktionsparität** erreicht und abgenommen. **Erst danach** werden zusätzliche P0-Wettbewerbsfunktionen umgesetzt. Diese Entscheidung ersetzt abweichende Prioritätsinterpretationen in den Kapiteln 11, 24, 27 und 28. „P0“ kennzeichnet künftig die fachliche Wichtigkeit, **nicht automatisch die Zugehörigkeit zum ersten Release**.

### 29.1 Verbindliche Reihenfolge

| Stufe | Inhalt | Freigabebedingung |
|---|---|---|
| **R0 – Bestandsaufnahme und Verträge** | Quellinventar, fachneutrale Paritätsmatrix, Ausschluss aller Fachmodule, Lizenz-/Abhängigkeitsprüfung, Architektur-ADRs, Golden-Master-Tests | Gate A bestanden |
| **R1 – Course Captain Free: Paritätsrelease** | Universelle Kurse/Lektionen, Autorenbasis zum Erstellen und Veröffentlichen, fachneutraler Lernkern, Smarttrainer, Fragenbank, Fehlertraining, Favoriten, Videos/Medien, Prüfungen, Lernfortschritt, Zugänge, Import/Export **soweit im fachneutralen Quellumfang vorhanden** | Gates B und C bestanden, **100 % der als fachneutral klassifizierten Quellfunktionen** in der Paritätsmatrix abgenommen; keine ausgeschlossenen Fachmodule |
| **R2 – Zusätzliche P0-Wettbewerbsfunktionen** | Persönliche Lernnotizen, universelle Text-/Datei-Aufgabenabgaben, neutrale Kursvorlagen/Demokurse sowie **über die Paritätsbasis hinausgehende** Kurs-/Quiz-Builder-Verbesserungen | R1 vollständig abgenommen; jedes Feature mit eigenem UX-, Sicherheits- und Regressionstest |
| **R3 – Pro und weitere P1-Integrationen** | Gradebook, Tutorbewertung, Frontend-Builder, Mehrtrainer, Zertifikatsdesigner, erweiterte Kompetenzmatrix, Commerce und Suite-Adapter | Stabiler Free-Kern; abhängige R2-Funktionen vorhanden; Pro-/Contract-Tests bestanden |
| **R4 – Nachfragebasierte Erweiterungen** | Gamification, SCORM/xAPI/H5P/LTI, zusätzliche Coaching- und Aktivitätstypen | Separater Produktentscheid und technische Prüfung |

**Präzisierung:** R1 enthält **alle für die fachneutrale Parität notwendigen Basisfunktionen**, auch wenn diese einen einfachen Kurs-/Quiz-Editor erfordern. R2 enthält ausschließlich **zusätzliche** Funktionen oder Komfortverbesserungen, die nicht zur nachgewiesenen Parität gehören. Sollte eine vermeintliche R2-Funktion bereits im fachneutralen LearnBoating-Bestand vorhanden sein, wird **ihr paritätsrelevanter Teil in R1 vorgezogen**; die Paritätsmatrix entscheidet, nicht der Name des Features.

### 29.2 Stop-/Go-Regeln

1. **Kein Start der R2-Implementierung vor R1-Abnahme**; R2-Ideen dürfen zuvor analysiert und spezifiziert, aber nicht als Ersatz für Paritätsarbeit entwickelt werden.
2. **Keine öffentliche Paritätszusage ohne Nachweis:** Für jede fachneutrale Funktion sind Quellreferenz, Zielimplementierung, neutrale E2E-/Regressionstests und Abnahmestatus dokumentiert.
3. **Keine Fachmodule:** Die Ausschlussliste aus Kapitel 26 gilt für alle Stufen.
4. **Keine automatische Pro-Verschiebung:** R3 ist eine Planungsstufe; Lizenzierung und bestehende Pro-Zusagen aus Kapitel 5 bleiben als Zielarchitektur erhalten. Termine werden erst nach Aufwandsschätzung festgelegt.
5. **Keine stillschweigende Streichung:** Wird eine Quellfunktion als fachneutral klassifiziert, kann sie nur per dokumentierter Entscheidung aus der Paritätsmatrix ausgeschlossen werden.
6. **Statusführung:** `TODO-WETTBEWERBSANALYSE.md` führt die operativen Checklisten nach R0–R4; ein abgehaktes Planungs-/Konzepttask bedeutet nicht, dass die Funktion implementiert ist.

### 29.3 Nächster verbindlicher Meilenstein

Die nächste Umsetzungseinheit ist **R0: vollständige fachneutrale Paritätsmatrix samt Golden-Master-Tests und Ausschlussprüfung**. Erst mit diesem Nachweis kann der Umfang von R1 endgültig eingefroren und geschätzt werden.

## 30. Captain-Suite-Gesamtabgleich: Integrationslandkarte und vollständiger Aufgabenvertrag

**Zweck:** Course Captain bleibt ein universelles, eigenständiges LMS. Die folgenden Integrationen übernehmen **keine** Domänenlogik anderer Captain-Plugins in den Lernkern. Dieser Abgleich nutzt die Konzepte von Workflow, Form und Review Captain sowie README-/Vertrags-/Release-Unterlagen von License, Voucher, Pass, Affiliate und Captain Pro. **Quelle ist die Dokumentation, kein vollständiger Quellcode-Audit; alle Ziel-APIs sind vor Implementierung gegen den dann aktuellen Code zu validieren.**

### 30.1 Verbindliche Reihenfolge und Geltungsbereich

- **R0/R1:** Vollständige fachneutrale LearnBoating-Parität, inkl. erforderlicher generischer Entitlements und neutraler Lernereignisse; die nachstehenden Suite-Adapter sind **keine Voraussetzung** für R1. Öffentliche, minimal notwendige Erweiterungspunkte können im R1-Design berücksichtigt werden, ohne die Integration zu bauen.
- **R2:** Zusätzliche P0-Wettbewerbsfunktionen nach vollständiger R1-Abnahme; keine Suite-Integration darf diese Reihenfolge umgehen.
- **R3:** Optionale Suite-Connectoren, Pro-Integrationen und Automationsrezepte; nach Abhängigkeiten und Contract-Tests einzeln freigeben.
- **R4:** Erweiterte Cross-Site-, Agentur-, Multisite- und Spezialprozesse nur bei bestätigtem Bedarf.
- **Keine Fremdrepo-Änderung:** Anforderungen an Gegenstellen zunächst nur als offene Kompatibilitätsfragen dokumentieren. Anpassungen anderer Produkte benötigen einen separaten Auftrag.

### 30.2 System-of-Record- und Integrationsmatrix

| Partner | Eigentümerdaten / Grenzen | Course-Captain-Gegenvertrag (zu spezifizieren) | Zielphase |
|---|---|---|---|
| **Workflow Captain** | Workflows, Läufe, Automationszustand und Retry; Course Captain besitzt Lern- und Prüfungsdaten | Typisierte, versionierte Events `enrollment.created/revoked`, `lesson.completed`, `assessment.submitted/passed/failed`, `course.completed`, `certificate.issued/revoked`; optionale Actions `enroll/revoke` nur mit Rechten, Idempotenz und Audit; Event-ID, Site-ID, Objektversion, Korrelations-ID, minimale Payload, Replay/Outbox | R3 |
| **Form Captain** | Formulare, Entries, Uploads und Aufbewahrung bleiben bei Form Captain | Privatsphärengeschützte Kurs-/Lektions-/Lernenden-Kontextauflösung, konfigurierbares Feldmapping, Berechtigungsprüfung, verifizierte Einreichungsreferenz, optionale Aufgabenübergabe nur über API und ausdrückliche Freigabe; keine direkten Tabellenzugriffe | R3 |
| **Review Captain** | Bewertungen, Anfragen, Opt-out, Moderation und Plattformregeln bleiben bei Review Captain | Bestätigtes `course.completed`/Lernerlebnis mit stabiler Experience-ID; nur erforderliche Kontaktdaten nach Rechtsgrundlage/Einwilligung; Ausschluss bei Widerruf, Duplikaten und bereits angefragten Erlebnissen; Review-Einladung nicht im Course-Core | R3 |
| **Voucher Captain** | Gutscheinwert, Reservierung, Einlösung, Refund-Ledger bleiben bei Voucher Captain | Separater Connector gemäß `CONNECTORS.md`: Validierung → Reservierung → idempotente Einschreibung/Entitlement → Commit oder kompensierende Freigabe; eindeutige Zuordnung pro Kurs/Platz, Rücknahme bei Storno/Refund, keine Code-/Guthaben-Kopie im LMS | R3 |
| **Pass Captain** | Event, Termin, Kontingent, Ticket, Check-in bleiben bei Pass Captain | Optionales Mapping von Event-/Ticketstatus zu Kurs-/Kohortenzugang; Ticket-Storno und Rückerstattung widerrufen nur die vertraglich verknüpften Zugänge; Check-in ist **nicht** automatisch Lernkompetenz oder Kursabschluss | R3/R4 |
| **Affiliate Captain** | Attribution, Partner, Provisionen, Auszahlung, Refund-Korrektur bleiben bei Affiliate Captain | Bei **eigenständigem** Course-Verkauf optional versionierte Sale-/Refund-Meldung gemäß `docs/CONTRACT.md`; bei WooCommerce-Verkauf keine doppelte Meldung aus Course Captain; stabile Quell-/Bestell-/Positions-IDs und Idempotenz; keine Provisionstabellen im LMS | R3 |
| **WP License Captain** | Lizenzserver verkauft/aktiviert Softwarelizenzen und stellt Updates bereit; **nicht** Lernzugänge | Course-Captain-Pro-Client/Updater mit getrennten Entitlements, Status/Grace-Handling, signierten Updates, Offline-/Ausfallverhalten und Release-Tests; Free ohne Lizenzserverabhängigkeit | R3 |
| **Captain Pro** | Separates Premium-Add-on je Produkt, Lizenzclient und Feature-Registry | Course-Captain-Pro als eigenständiges Add-on **erst nach Architektur-/Produktschlüssel-/Repository-Freigabe**; Free-Kern enthält keine Pro-Lizenzlogik; Paketierung, Aktivierungs-/Deaktivierungs-/Upgrade-Tests; vorhandene Add-on-Liste nicht als fertige Course-Integration ausgeben | R3 |

### 30.3 Öffentlicher Course-Captain-Vertrag – Spezifikationspflicht

**Event Envelope v1 (Vorschlag, nicht implementiert):** `event_id` (UUID), `event_type`, `schema_version`, `occurred_at` (UTC), `site_id`, `correlation_id`, `source_object_id`, `source_object_version`, `actor_id` (wenn zulässig), `payload` mit IDs/Status statt sensitiven Vollobjekten. Auslieferung über Source-Outbox oder nachholbare Abfrage; einmaliges `do_action` ist keine Zustellgarantie. Retry/Replay nur idempotent.

**Commands/Queries v1 (Vorschlag):** `enroll_learner`, `revoke_enrollment`, `get_course_context`, `get_enrollment_status`, `verify_completion`, `verify_certificate`; jede Operation benötigt Capability-/Objektzugangsprüfung, dokumentierte Eingabe-/Ausgabeschemas, Status- und Fehlercodes, Idempotenz-/Konsistenzregeln, Datenschutz- und Auditregeln. Externe Connectoren greifen **niemals** auf interne Course-Tabellen zu.

**Integrationszustände:** `not_installed`, `inactive`, `incompatible`, `needs_configuration`, `connected`, `degraded`, `failed`. Admin zeigt Version, Vertrag, letzte erfolgreiche Kommunikation, letzte Fehlerklasse, Test- und Retry-Aktion; keine Geheimnisse oder personenbezogenen Payloads in Logs.

**Transport:** lokale WordPress-Hooks/Service-API nur auf derselben Installation; über Site-Grenzen ausschließlich authentifizierte, signierte, scoped REST-/Webhook-Verträge mit Zeitstempel, Replay-Schutz, Schlüsselrotation und expliziter Opt-in-Freigabe. Network-/Multisite-Semantik separat entscheiden.

### 30.4 Verbindliche End-to-End-Rezepte für spätere Connector-Abnahme

1. **Lernen → Workflow:** Lernender besteht Prüfung → Course speichert Ergebnis → Event wird genau einmal wirksam verarbeitet → Workflow versendet Nachricht; Retry/Duplikat/Timeout getestet.
2. **Kurs → Form:** Lernender öffnet kontextgebundenes Formular → Course autorisiert Kurskontext → Form speichert Entry → Referenz erscheint beim berechtigten Tutor; unberechtigter Zugriff wird verweigert.
3. **Abschluss → Review:** Bestätigter Kursabschluss → Review prüft Consent, Opt-out und Deduplizierung → höchstens eine Einladung je Experience-Policy; Widerruf wird berücksichtigt.
4. **Voucher → Kurs:** Voucher-Reservierung → Kurszugang idempotent gewähren → Gutschein committen; bei Abbruch kompensieren; Refund/Teilrefund und Doppel-Callback testen.
5. **Pass → Kurs:** Ticketkauf/Teilnahme → verknüpften Kurszugang gewähren; Ticketstorno → vertraglich festgelegten Zugang widerrufen; keine automatische Kompetenzzusage.
6. **Affiliate → Verkauf:** Ein Verkauf wird genau einer autoritativen Verkaufsquelle zugerechnet; Storno/Chargeback korrigiert die Provisionsmeldung; WooCommerce-Adapter nicht doppelt zählen.
7. **Pro → Lizenz:** Lizenzserver offline/abgelaufen, Free-Plugin aktiv, Pro deaktiviert oder aktualisiert → Free-Lernen und historische Ergebnisse bleiben verfügbar; Premiumfunktionen verhalten sich nach definierten Grace-Regeln.

### 30.5 Dokumentations-, Test- und Freigabepaket

Für **jeden** Connector: Ownership-Tabelle, Datenflussdiagramm, öffentliche API mit Version und Beispieldaten, Kompatibilitäts-/Mindestversionsmatrix, Feature-/Lizenzgrenze, Installations-/Deinstallationsablauf, Test mit fehlendem Partner, Rechte-/Datenschutzprüfung, Deduplikations- und Fehlerfalltests, Upgrade-/Rollback-Plan, Admin-Diagnose, Betreiberhandbuch und CI-Nachweis. **Keine Freigabe aufgrund bloßer Konzeptübereinstimmung.**

**Quellunterlagen (Stand des Abgleichs):**
- [Workflow Captain – Konzept](https://github.com/hastla007/wp-workflowmaster/blob/main/konzept.md)
- [Form Captain – Konzept](https://github.com/hastla007/wp-formmaster/blob/main/konzept.md)
- [Review Captain – Konzept](https://github.com/hastla007/wp-reviewmaster/blob/main/konzept.md)
- [Voucher Captain – Connector-Vertrag](https://github.com/hastla007/wp-couponmaster/blob/main/CONNECTORS.md)
- [Affiliate Captain – öffentlicher Vertrag](https://github.com/hastla007/wpaffiliatemaster/blob/main/docs/CONTRACT.md)
- [Pass Captain – README](https://github.com/hastla007/wp-passmaster/blob/main/README.md)
- [WP License Captain – Release-Gates](https://github.com/hastla007/wp-license/blob/main/docs/RELEASE-READINESS.md)
- [Captain Pro – Add-on-Architektur](https://github.com/hastla007/captain-pro/blob/main/README.md)

**Abgrenzung:** Die Dokumente der Schwesterprojekte enthalten teils Pläne und teils Implementierungsstände. Diese Matrix macht daraus **Course-Captain-Anforderungen**, nicht Behauptungen, dass Integrationen schon existieren.
