# Course Captain – Produkt- und Umsetzungskonzept

**Status:** Planung / Architekturgrundlage, nicht implementiert  
**Stand:** 15. November 2026  
**Produkt:** Course Captain – universelles WordPress-Lernmanagementsystem (LMS) der Captain Suite  
**Repository:** `hastla007/wp-coursemaster`  
**Free-Plugin / Slug / Textdomain:** `course-captain`  
**Pro-Add-on:** `course-captain-pro`, vorgesehen in `hastla007/captain-pro/addons/course-captain-pro/`  
**Lizenzprodukt:** `course-captain-pro` (im License Captain-Produktkatalog neu anzulegen)  
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
- Pro ist **ein separates** Premium-Add-on mit eigener Lizenz und Updater über den bestehenden Captain-Pro-Kernel und License Captain. Käufer brauchen keinen eigenen Lizenzserver. Der Server selbst bleibt ein separat verkauftes Produkt.
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

License Captain (`hastla007/wp-license`) ist der separate Lizenz**server**; das Add-on in `captain-pro` enthält ausschließlich den eingebauten **Client**. Nicht als Pro-Nutzen bewerben und kein eigener Lizenzserver beim Kunden voraussetzen.

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
| **License Captain** (`wp-license`) | Pro-Add-on-Lizenz über Captain-Pro-Client, keine Lernendenlizenz | Lizenzserver besitzt Add-on-Lizenzen; Course Captain besitzt Kursfreigaben |
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
- [License Captain](https://github.com/hastla007/wp-license), [Captain Pro – Architektur](https://github.com/hastla007/captain-pro/blob/main/docs/ARCHITECTURE.md), [Captain Pro – Tarife](https://github.com/hastla007/captain-pro/blob/main/docs/TARIFE.md), [Captain Pro – Release](https://github.com/hastla007/captain-pro/blob/main/docs/RELEASE.md)

**Repository-Regel:** Dieses Dokument darf im Zielprojekt weiterentwickelt werden. `learnboating` und die übrigen bestehenden Plugins sind Referenzen; jede spätere Änderung dort ist ein separates Vorhaben und nicht durch dieses Konzept autorisiert.


---

# Ergänzung: Suiteweiter Vollständigkeitsabgleich und verbindliche v1-Spezifikation (15.11.2026)

Diese Ergänzung basiert auf einem erneuten Abgleich mit den Konzepten von Workflow Captain, Form Captain, Review Captain, Ticket Captain, Voucher Captain, Affiliate Captain sowie der Captain-Pro-Architektur. Sie **ergänzt** die Abschnitte 1–14; bei widersprüchlichen Aussagen gelten die ausdrücklich als „verbindlich“ bezeichneten Entscheidungen unten. Funktionen sind geplante Anforderungen, nicht bereits implementiert.

## 15. Wettbewerbsbild, Nutzerreisen und Produktgrenzen

### 15.1 Marktanforderungen statt bloßer Featureliste

Marktanforderungen werden ausschließlich anonymisiert und funktionsbezogen geprüft. In diesen Projektdateien werden keine Namen, Marken, Domains oder Links konkurrierender LMS-Produkte geführt; unbelegte Preis- und Marktbehauptungen entfallen.

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

Vor verbindlicher Feinspezifikation und Release-Planung Marktanforderungen anhand neutraler Funktionskategorien und eigener Testfälle validieren. Pro Funktion Nutzerbedarf, UX, Rechte, Performance, Barrierefreiheit, Course-Captain-Abdeckung und Aufwand dokumentieren, ohne fremde LMS-Produkte oder deren URLs zu nennen. Referenzfall: 10 Lektionen, 50 Fragen, eine Aufgabe und eine Prüfung.

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
| **License Captain** | Lizenzserver verkauft/aktiviert Softwarelizenzen und stellt Updates bereit; **nicht** Lernzugänge | Course-Captain-Pro-Client/Updater mit getrennten Entitlements, Status/Grace-Handling, signierten Updates, Offline-/Ausfallverhalten und Release-Tests; Free ohne Lizenzserverabhängigkeit | R3 |
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
- [License Captain – Release-Gates](https://github.com/hastla007/wp-license/blob/main/docs/RELEASE-READINESS.md)
- [Captain Pro – Add-on-Architektur](https://github.com/hastla007/captain-pro/blob/main/README.md)

**Abgrenzung:** Die Dokumente der Schwesterprojekte enthalten teils Pläne und teils Implementierungsstände. Diese Matrix macht daraus **Course-Captain-Anforderungen**, nicht Behauptungen, dass Integrationen schon existieren.

## 31. Moderierte Drei-Experten-Debatte: Nahtlose Suite-Erfahrung – beschlossene Ergänzungen

**Methodik:** Simulierte Diskussion dreier idealtypischer Fachrollen (WordPress-/Integrationsarchitektur, LMS/Learning Experience, SaaS-Ökosystemstrategie), auf Basis der in Kapitel 30 verlinkten Schwesterkonzepte und öffentlichen Verträge. Kein externes Experteninterview, keine Code-Abnahme. Kapitel 29 bleibt **verbindlich**: R0/R1 Parität → R2 zusätzliche P0 → R3 Suite/Pro → R4 Spezialfunktionen.

### 31.1 Kritische Diskussion und Konsens

| Konflikt | Positionen und Einwand | Gemeinsamer Beschluss |
|---|---|---|
| Einheitliche Suite vs. eigenständige Plugins | Strategie fordert einen gemeinsamen Auftritt; Architektur warnt vor zentraler Abhängigkeit; LMS fordert nahtlosen Lernfluss | Gemeinsame **UX-Konventionen und Integrationsrezepte**, aber **kein zentraler Pflicht-Suite-Kern** und keine fremden Tabellenzugriffe |
| Sofortiger Kurszugang vs. asynchrone Zahlung/Gutscheine | LMS fordert sichtbaren Zugang; Architektur weist auf Pending/Refund/Timeout hin | Course Captain führt einen **nachvollziehbaren Enrollment-/Entitlement-Zustand** mit Quellenbezug, Ablauf, Widerruf, Pending-Status und Wiederanlauf |
| Automatisierung vs. fachliche Korrektheit | Produkt fordert End-to-End-Automation; LMS warnt vor falschen Kompetenznachweisen | Zahlung, Gutschein, Ticket und Check-in **gewähren höchstens Zugang**; Kursabschluss/Prüfung/Kompetenz nur durch Course-Captain-Lernregeln |
| Events vs. Geschäftsprozesse | Architektur: Ein Event ist keine verteilte Transaktion; Produkt: Kunden erwarten vollständige Abläufe | Für mehrstufige Vorgänge **Saga-/Kompensationsregeln** mit Status, Idempotenz, Timeout, Retry, manueller Klärung und auditierter Rücknahme |
| Suite-weite Oberfläche vs. Datenschutz | UX will Kontextübernahme; Architektur fordert Datensparsamkeit | Kontext per kurzlebigem, scoped Referenz-/Tokenvertrag; keine ungeprüften E-Mail-/Personendaten in Eventpayloads |
| Free vs. Integrationskomfort | Produkt will Upgrade-Pfad; LMS will keine kaputte Lernhistorie bei Lizenzende | Free-Kern und lokale Lernhistorie unabhängig; Pro/Suite deaktivierbar, ohne bestehende Nachweise zu verlieren |

### 31.2 Verbindliches Enrollment-/Entitlement-Modell

**Course Captain ist System of Record für Kurszugänge und Lernfortschritt;** WooCommerce, Voucher, Pass, Mitgliedschafts- und Projektconnectoren liefern lediglich **autorisierte Zugangsgründe**. Softwarelizenz (License Captain) ist strikt getrennt.

- **Zugangsgrund:** `grant_id`, `site_id`, `learner_id`, `course_id`, `source_plugin`, `source_type`, `source_reference`, `idempotency_key`, `valid_from`, `valid_until`, `status`, `revocation_reason`, `created_at`, `updated_at`; personenbezogene Felder minimieren.
- **Zustände:** `pending`, `active`, `expired`, `revoked`, `failed` mit serverseitig dokumentierten Übergängen; bei mehreren unabhängigen aktiven Grants bleibt der Kurszugang bestehen, wenn ein einzelner Grant widerrufen wird.
- **Zugangsentscheidung:** Autorisierte Quelle, gültiger Grant, Kursstatus, Lernendenidentität und Objektberechtigung prüfen. Änderungen atomar/idempotent anwenden; Prüfungs- und Lernhistorien **nicht** mit Zugangsgründen löschen.
- **Refund/Storno:** Rücknahme wirkt auf den **betroffenen Grant**, nicht pauschal auf den gesamten Kurs oder auf andere Zugangsquellen. Vergangene Prüfungsnachweise bleiben historisch nachvollziehbar; zukünftige Zugriffe folgen der Betreiberregel.
- **Sichtbarkeit:** `Mein Lernen` zeigt verständliche Zustände wie „Zugang wird aktiviert“, „aktiv“, „abgelaufen“, „vorübergehend nicht verfügbar“ und einen zulässigen Wiederaufnahme-/Supportweg.

### 31.3 Geschäftsprozess-Orchestrierung und Fehlerfälle

1. **Verkauf/Einlösung:** Externe Quelle bestätigt die fachliche Berechtigung → Connector sendet versionierten Grant-Befehl → Course Captain bestätigt die persistierte Entscheidung → Quelle schließt Einlösung/Verkauf ab. Bei Teilausfall: kompensierende Freigabe/Rücknahme oder kontrollierter Pending-Zustand; niemals stilles Doppel-Grant.
2. **Abschluss und Folgeaktionen:** Course Captain bewertet den Lernnachweis selbst → persistiert Completion/Assessment → publiziert Event via Outbox → Workflow/Review/Form reagieren optional. Doppelte oder verspätete Events dürfen keine mehrfachen Zertifikate, Nachrichten oder Review-Einladungen erzeugen.
3. **Widerruf und Datenschutz:** Erstattungen, Ticketstorno, Entzug einer Einschreibung und Löschanfragen haben unterschiedliche Fachsemantik. Datenlöschung nach Rechtsgrundlage/Retention; Widerruf eines Zugangs ist **keine** automatische Löschung historischer Nachweise.
4. **Konfliktbehandlung:** Ein eindeutiger Vorgangsschlüssel und Korrelations-ID verbindet die Schritte; bei Timeout, inkompatiblem Vertrag, deaktiviertem Plugin, abgelaufener Signatur und verwaistem Pending-Vorgang wird der Zustand angezeigt und sicher reparierbar gemacht.
5. **Betrieb:** Automatische Wiederholungen nur für temporäre Fehler; permanente Validierungs-/Berechtigungsfehler benötigen Klärung. Manuelle Wiederholung prüft Rechte und verwendet denselben Idempotenzschlüssel.

### 31.4 Nahtlose Suite-UX als überprüfbarer Vertrag

- **Admin:** Course Captain → Integrationen: Karten je Partner mit Installation, Version, Kompatibilität, Konfiguration, letztem Test, Fehlerklasse und „Verbindung prüfen“. Fehlende Plugins zeigen Erklärung statt Fatal Error.
- **Setup:** Standalone-Pfad ohne Suite; erkannte Partner als **optionale** Schritte, niemals erzwungene WooCommerce-/Lizenzinstallation.
- **Lernendenpfad:** Kauf/Einlösung → eindeutige Bestätigung → `Mein Lernen` mit Kursstatus → Fortsetzen → Prüfung → Zertifikat; keine zweite Registrierung, wenn eine vertrauenswürdig verknüpfte WP-Identität vorliegt.
- **Trainerpfad:** Kurs erstellen → Zugangsquelle wählen → Einschreibungen und ausstehende Aktivierungen sehen → Abschluss/Ergebnis prüfen → optionale Workflow-/Review-Regeln.
- **Gestaltung:** Konsistente Captain-Menübenennung, Statussprache, Hilfe, Leer-/Fehlerzustände, responsive Ansichten und Accessibility; kein stiller Redirect zwischen fremden Adminseiten.
- **Datenschutz:** Kurskontext nur über autorisierte Queries/kurzlebige Scopes, keine Offenlegung fremder Enrollment-/Form-/Review-Daten durch ID-Manipulation.

### 31.5 Suiteweite Sicherheit, Kompatibilität und Betrieb

**Vertragsprüfung:** semantische Major-Versionen, Mindest-/Höchstkompatibilität, Feature-Discovery und explizite `incompatible`-Zustände. Lokale Hooks nur innerhalb einer Site; Cross-Site-Verbindungen später signiert, scoped und replaygeschützt. Alle Commands mit Capability-/Objektprüfung, Nonce wo passend, Audit und Rate Limits.

**Datenhoheit:** Course Captain besitzt Course/Enrollment/Assessment/Completion/Certificate; Voucher besitzt Gutscheinwert und Ledger; Pass Ticket/Check-in; Affiliate Attribution/Provision; Form Entry; Review Anfrage/Review; Workflow Ausführung; License Softwarelizenz. Kein Connector schreibt direkt in fremde Tabellen.

**Beobachtbarkeit:** Korrelations-ID über Event, Connector, Enrollment und Workflow-Lauf; strukturierte Fehlerklassen, datensparsame Logs, definierte Retention, sichere Diagnose- und Supportexporte, Alarmierung für dauerhaft hängende Pending-Vorgänge.

**Upgrade/Deinstallation:** Tests für deaktivierten Partner, Downgrade, fehlende API, geänderte Payload, Wiederanlauf, Plugin-Reaktivierung, Netzwerkfehler, parallele Refunds, Backup/Restore und Lizenzserver-Ausfall. Kein Verlust der Free-Lernhistorie.

### 31.6 Abnahmeszenarien und messbare Ziele

| Szenario | Muss-Nachweis |
|---|---|
| Gutschein für Kurs | Einlösung und Einschreibung genau einmal; Abbruch/Refund sauber kompensiert |
| Ticket mit Kurs | Ticketstatus beeinflusst nur den zugehörigen Grant; Check-in ergibt keine Kompetenz |
| WooCommerce und Affiliate | Genau eine Verkaufsquelle; keine Doppelprovision bei Course-/Woo-Event |
| Prüfung bestanden | Lernnachweis gespeichert; Workflow-Event nachholbar; Zertifikat nicht doppelt |
| Review nach Abschluss | Nur verifiziertes Erlebnis, Consent/Opt-out geprüft, keine Mehrfacheinladung |
| Form im Kurs | Autorisierter Kontext; unbefugte Lernenden-/Kurs-IDs werden abgewiesen |
| Lizenzserver/Partner offline | Free-Lernen weiter möglich; Integrationsstatus erklärt Ausfall |
| Mehrere Grants | Widerruf eines Gutscheins sperrt nicht anderen gültigen Zugang |
| Datenschutzanfrage | Zuständigkeiten und Export-/Löschpfade je Datenquelle dokumentiert |

**Zu messende Produktziele (keine Istwerte):** Zeit zwischen bestätigtem Kauf und sichtbarem Kurszugang, Anteil verwaister Pending-Grants, erfolgreiche Wiederanläufe, doppelte Side-Effects (Ziel: null), Supportfälle je Integrationspfad, Abschluss des Lernenden-End-to-End-Flows ohne manuelle Adminhilfe. Schwellenwerte erst nach Baseline/Lasttest festlegen.

### 31.7 Noch zu klärende Entscheidungen

1. **Entschieden:** Die ersten R3-Referenzintegrationen sind **Voucher Captain und WooCommerce**. Weitere Connectoren folgen nach Abhängigkeiten und separater Abnahme; die Reihenfolge nach dem ersten Integrationspaket bleibt offen.
2. Wie lange bleibt ein **Pending-Grant** vor Eskalation bestehen? Vorgangsspezifisch konfigurieren und durch Tests begründen.
3. Welches **Captain-Suite-Navigationsmuster** gilt produktübergreifend? Gemeinsame Designkonventionen ja; ein zentrales Pflichtplugin nein.
4. Wie werden **Gastkäufer** sicher mit bestehenden WordPress-Lernenden verknüpft, ohne Accounts anhand unbestätigter E-Mail-Adressen zusammenzuführen?

**Prioritätsregel:** Alle R3-Integrationsarbeiten bleiben nach R1-Parität und R2-Zusatz-P0; die Dokumentation und Schnittstelleninventur dürfen bereits in R0 stattfinden. Dieses Kapitel ergänzt Kapitel 30 um fachliche Zustands- und UX-Verträge und ersetzt keine der dortigen Partnerintegrationen.

## 32. Verbindlicher R3-Start: Voucher Captain und WooCommerce zuerst

**Auftraggeberentscheidung:** Die erste R3-Integrationswelle für Course Captain umfasst **Voucher Captain und WooCommerce**. Diese Festlegung konkretisiert Kapitel 30 und 31 und hat bei widersprechenden Empfehlungen Vorrang. **Unverändert bindend:** R0/R1 vollständige fachneutrale LearnBoating-Parität → R2 zusätzliche P0-Funktionen → **erst dann** R3-Integrationen. Die Dokumentation darf vorher vorbereitet werden; Implementierung und Freigabe der beiden Connectoren erst in R3.

### 32.1 Umfang und klare Zuständigkeiten

- **Course Captain:** Kurskatalog, Lernendenidentität, Kurszugang/Grants, Lernfortschritt, Prüfung, Zertifikate und deren unveränderliche Nachweise. Kurszugang ist keine Softwarelizenz.
- **WooCommerce:** Produkt, Warenkorb, Checkout, Bestellung, Zahlung, Refund, Steuern und Bestellstatus. Course Captain speichert nur notwendige Bestell-/Positionsreferenzen und den daraus abgeleiteten Grant.
- **Voucher Captain:** Gutscheinausstellung, Code-/Wertprüfung, Reservierung, Commit, Freigabe, Einlösungsledger und Refund-Semantik. Course Captain speichert weder Gutschein-Geheimcodes noch Guthaben oder ein zweites Voucher-Ledger.
- **Optionale Installation:** WooCommerce und Voucher Captain sind **keine** Voraussetzungen für Course Captain Free; die beiden Integrationen sind unabhängig aktivierbar, sofern ihr jeweiliger fachlicher Pfad vollständig unterstützt wird. Ein Voucher-Connector darf WooCommerce nicht stillschweigend voraussetzen, falls der dokumentierte Voucher-Vertrag einen direkten Kurs-Einlösekanal vorsieht.

### 32.2 Zwei Referenzabläufe und Konfliktfälle

**A – WooCommerce-Kursverkauf:** Admin ordnet Produkt/Variation einem Kurs oder definierten Kurspaket zu → bestätigter, konfigurierter Bestellstatus erzeugt einen **idempotenten** Grant je zulässiger Bestellposition und Lernendem → `Mein Lernen` zeigt den Kurs → Storno, Teilrefund, Chargeback und spätere Statuskorrektur betreffen ausschließlich die entsprechenden Grants. Gastkauf-Identität muss vor Grant-Aktivierung sicher bestätigt/zugeordnet sein; E-Mail-Gleichheit allein genügt nicht.

**B – Voucher-Einlösung für einen Kurs:** Kurs-Einlösekanal gemäß aktuellem `wp-couponmaster/CONNECTORS.md` → Gutschein prüfen/reservieren → Grant idempotent erzeugen → Einlösung committen. Bei Fehler/Timeout zwischen den Schritten erfolgt dokumentierte Kompensation oder ein nachvollziehbarer `pending`-Fall mit sicherem Retry. Für mehrfach einlösbare Guthaben, Kursgruppen, mehrere Plätze und Teilrefunds sind Einheiten und Zuordnung ausdrücklich festzulegen.

**Doppelquellen-Schutz:** Wenn ein WooCommerce-Kauf gleichzeitig Voucher Captain als Zahlungsmittel/Rabatt nutzt, entstehen nicht zwei ungewollte Einschreibungen. Eindeutige Ownership pro Bestellposition und Grant-Quelle; unabhängige gültige Grants dürfen koexistieren. Doppelte Webhooks, parallele Calls und Backfill müssen dieselben fachlichen Ergebnisse liefern.

### 32.3 R3-Abnahmereihenfolge

1. **R3.0 Vertrags-Freeze:** WooCommerce HPOS/Classic und Blocks-/Classic-Checkout-Szenarien, Voucher-Connector-API, Mindestversionen, Product-to-Course-Mapping, Berechtigungen, Gastkonto-Regeln, Refund-Matrix und Installationsvarianten dokumentieren.
2. **R3.1 WooCommerce-Connector:** Produktzuordnung, Grant/Revocation, Retry/Backfill, Adminstatus, End-to-End-Tests mit Bestellung, Zahlung, Teilrefund und Storno.
3. **R3.2 Voucher-Captain-Connector:** Direkte Kurseinlösung, Reserve/Commit/Release, Kompensation, Duplikat-/Parallelitätstests und optionaler gemeinsamer WooCommerce-Pfad.
4. **R3.3 Gemeinsame Abnahme:** WooCommerce allein, Voucher allein (soweit dessen Vertrag/Installation dies erlaubt), beide zusammen, beide deaktiviert, Version inkompatibel, Upgrade, Backup-Restore, Lizenzserver offline, Refund nach Kursbeginn; Free bleibt ohne beide vollständig nutzbar.
5. **R3.4 Weitere Suite-Connectoren:** Workflow, Form, Review, Pass, Affiliate, License/Pro werden anschließend anhand Abhängigkeiten und Nutzen priorisiert; **keine** automatische Verschiebung ihrer Dokumentationsaufgaben in R1/R2.

**Go/No-Go:** Kein doppelter Kurszugang aufgrund derselben Quelloperation, kein verlorener Kauf-/Einlösezustand, nachvollziehbarer Refund/Widerruf, keine Löschung historischer Lernnachweise, sichere Identitätszuordnung, kein direkter Fremdtabellenzugriff, dokumentierter Betreiber-Recovery-Pfad und bestandene End-to-End-Tests.

**Quellen:** [Voucher-Connector-Vertrag](https://github.com/hastla007/wp-couponmaster/blob/main/CONNECTORS.md), [Course-Captain-Suite-Matrix](#30-captain-suite-gesamtabgleich-integrationslandkarte-und-vollständiger-aufgabenvertrag). Die Details der realen APIs und der WooCommerce-Kompatibilität sind vor der Implementierung erneut zu prüfen.

## 33. Produktentscheidungen: Suite-Erkennung und zurückgestelltes Gesamtdashboard

**Entscheidung 1:** Ein gemeinsames Captain-Suite-Gesamtdashboard ist **optional für eine spätere Produktentscheidung**, kein R0–R3-Releaseblocker und kein Pflichtplugin. Course Captain behält ein eigenständiges Dashboard und eigene Navigation. Einheitliche Bezeichnungen, Kontextlinks und UX-Konventionen bleiben erwünscht.

**Entscheidung 2:** Course Captain soll vorhandene Captain-Produkte **automatisch und sicher erkennen**, ohne sie selbst zu installieren, zu aktivieren oder zu konfigurieren. Erkennung umfasst `not_installed`, `inactive`, `active_compatible`, `active_incompatible`, `needs_configuration`, `connected`, `degraded` und `failed`; das ist **kein** Ersatz für die explizite Freigabe eines Datenaustauschs. Capability-Discovery nur über dokumentierte öffentliche Version-/Feature-APIs; keine Annahme aufgrund eines Slugs allein. Pro Partner: erkannte Version, Vertrag, unterstützte Funktionen, Voraussetzungen, Aktivierungs-/Deaktivierungssteuerung, Diagnose, Hilfe und Datenschutzinformation. Bei fehlendem oder deaktiviertem Partner keine fatalen Fehler; ohne Nutzerfreigabe keine personenbezogene Synchronisierung.

**Reihenfolge:** In R0 dokumentieren; Free-R1/R2 nicht von Suite-Partnern abhängig machen; optionale Integrationsoberfläche und echte Connectoren in R3 nach Kapitel 32. Dashboard-Idee erst nach separater Entscheidung bewerten.

## 34. End-to-End-Prozesskatalog: sämtliche relevanten Course-/Suite-Lebenszyklen

Dieser Katalog erfasst **fachlich relevante Szenariofamilien**, nicht die Behauptung, jede denkbare externe Plugin-Kombination abschließend zu kennen. Für jeden Ablauf gelten: **Trigger → Identität/Autorisierung → verantwortliches System → persistierter Zustandsübergang → Event/Bestätigung → UI-Rückmeldung → Fehler/Retry/Kompensation → Audit/Datenschutz → Test.** Wo die Gegenstelle noch nicht verifiziert ist, bleibt der Connector-Vertrag offen. **R0/R1 → R2 → R3** bleibt unverändert.

### 34.1 Identität, Rollen und Einstieg (ID)

| ID | Ablauf und Regel | Kritischer Ausnahme-/Abnahmetest |
|---|---|---|
| ID-01 | Bestehender WP-Nutzer meldet sich an, erhält ausschließlich eigene Kurse | Rollenwechsel, gesperrter Nutzer, fremde Kurs-ID: kein IDOR |
| ID-02 | Gast kauft Kurs: verifizierte Einladung/Account-Zuordnung vor aktivem Zugang | Gleiche E-Mail eines fremden Accounts führt nie zu automatischer Übernahme |
| ID-03 | Käufer erwirbt für andere Person oder mehrere Plätze; Käufer ≠ Lernender | Jede Platzzuweisung einmalig, Einwilligung/Identität geprüft |
| ID-04 | Trainer, Autor, Tutor, Shop-Manager, Admin besitzen getrennte Objekt-/Site-Rechte | Keine implizite Eskalation über Woo-/Suite-Rolle |
| ID-05 | Accountzusammenführung, E-Mail-Wechsel, Nutzerlöschung oder Sperre | Grants, Lernhistorie und Prüfungsnachweise konsistent; Datenschutz-/Retention-Regel |
| ID-06 | Multisite/Cross-Site-Identität nur nach R4-Entscheidung | Keine standortübergreifende Identitätsgleichsetzung allein per E-Mail |

### 34.2 Kurs- und Lernlebenszyklus (LEARN)

| ID | Ablauf und Regel | Kritischer Ausnahme-/Abnahmetest |
|---|---|---|
| LEARN-01 | Kurs erstellen → Entwurf → Prüfung → veröffentlichen → aktualisieren/archivieren | Unveränderliche Revision für laufende Prüfungsversuche; Draft nicht öffentlich |
| LEARN-02 | Lektion/Video/Datei/Activity bearbeiten, freigeben und absolvieren | Zugangsprüfung für Medien; keine fremden Uploads |
| LEARN-03 | Lernfortschritt speichern, unterbrechen, fortsetzen, Gerätewechsel | Doppelte/verspätete Requests verändern Fortschritt nicht rückwärts |
| LEARN-04 | Quiz/Prüfung starten, Antworten speichern, Zeitlimit, Abgabe, Bewertung | Serverzeit, Versuchssnapshot, Retry, Netzwerkabbruch, parallele Tabs |
| LEARN-05 | Prüfung nicht bestanden, Wiederholung, Versuchslimit, Sperrfrist | Kein Umgehen durch URL-/REST-Manipulation |
| LEARN-06 | Smarttrainer, Karteikarten, Favoriten, Fehlertraining, Wiederholungsplan | Neutraler LearnBoating-Paritätsnachweis und unabhängige Mastery |
| LEARN-07 | Completion, Mastery, Competency separat berechnen | Zahlung/Check-in/Anwesenheit ist kein Kompetenznachweis |
| LEARN-08 | Zertifikat ausstellen, verifizieren, korrigieren, widerrufen | Stabile Nachweis-ID, revisionssichere Historie, kein Doppelzertifikat |
| LEARN-09 | Lernnotizen, Aufgabenabgabe, Trainerfeedback, Vorlagen | Zusätzliche P0-Funktionen nur R2, soweit nicht R1-Parität |
| LEARN-10 | Kurs löschen/archivieren oder Inhalte importieren/rollback | Referenzintegrität, Nachweisaufbewahrung, Dry-run, Konfliktbericht |

### 34.3 Enrollment und Zugangsquellen (ACCESS)

| ID | Ablauf und Regel | Kritischer Ausnahme-/Abnahmetest |
|---|---|---|
| ACCESS-01 | Kostenloser, manueller, administrativer oder Einladungskurszugang | Nur autorisierte Quelle; Audit und Widerruf |
| ACCESS-02 | Kauf-/Voucher-/Ticket-/Mitgliedschaftsgrant hinzufügen | Pro Quelle stabile Referenz und idempotenter Grant |
| ACCESS-03 | Mehrere gültige Grants für denselben Kurs | Widerruf einer Quelle sperrt andere nicht |
| ACCESS-04 | Grant pending → active/failed/expired/revoked | Nur erlaubte Übergänge, Timeout-/Retry-/Recovery-Pfad |
| ACCESS-05 | Ablaufdatum, Verlängerung, Pause, Reaktivierung | Zeitgrenzen/Zeitzonen, keine doppelte Verlängerung |
| ACCESS-06 | Rückerstattung nach Kursbeginn oder nach Zertifikat | Zukunftszugang entziehen, historische Ergebnisse nicht löschen |
| ACCESS-07 | Paket/Bündel/mehrere Plätze oder Teilwiderruf | Zuordnung pro Position/Platz/Teilnehmer eindeutig |
| ACCESS-08 | Gesperrter Kurs, eingeschränkte Lektion, geschützte Downloads | Rechteprüfung bei jedem Zugriff, auch REST und Medien |
| ACCESS-09 | Quelle/Partner deaktiviert oder inkompatibel | Bestehende Grants nach expliziter Betreiberpolicy; kein stiller Massenwiderruf |

### 34.4 WooCommerce und Voucher – verbindliche erste R3-Welle (COMMERCE)

| ID | Ablauf und Regel | Kritischer Ausnahme-/Abnahmetest |
|---|---|---|
| COMMERCE-01 | Woo-Produkt/Variation → Kurs/Paket; Kauf → Zahlung bestätigt → Grant | HPOS/Classic, Checkout Blocks/Classic, doppelte Hooks |
| COMMERCE-02 | Bestellung pending/on-hold/failed/cancelled/completed | Keine Freischaltung vor definierter Bestätigung; spätere Statuswechsel |
| COMMERCE-03 | Gastbestellung, Käufer ≠ Lernender, Mehrfachmenge | Identitätsverifikation, Platzzuweisung, keine E-Mail-Übernahme |
| COMMERCE-04 | Woo-Storno, Teil-/Vollrefund, Chargeback, Reversal | Nur betroffene Positions-Grants; Ledger bei Woo |
| COMMERCE-05 | Gutschein für Kurs: validate/reserve → grant → commit | Kompensation bei Timeout, Retry und konkurrierender Einlösung |
| COMMERCE-06 | Gutschein mehrfach/mit Guthaben/Plätzen, Ablauf, ungültig | Einheit/Restwert bleibt Voucher-Eigentum |
| COMMERCE-07 | Woo-Bestellung mit Voucher-Rabatt oder Voucher-Zahlung | Eine autoritative Kauf-/Grant-Quelle, kein doppelter Zugang |
| COMMERCE-08 | Gratisbestellung, 100%-Rabatt, erneuter Kauf, Upgrade | Explizite Freischaltpolicy, bestehende Grants nicht überschreiben |
| COMMERCE-09 | Produkt-/Kursmapping nach Verkauf geändert oder gelöscht | Historische Bestellposition bleibt korrekt zuordenbar |
| COMMERCE-10 | Partnerausfall zwischen Reservierung, Grant und Commit | Persistenter Zwischenzustand, sichere Wiederholung/Supportreparatur |

### 34.5 Übrige Suite-Connectoren – spätere R3-Wellen (SUITEFLOW)

| ID | Ablauf und Regel | Kritischer Ausnahme-/Abnahmetest |
|---|---|---|
| SUITEFLOW-01 | Workflow: Enrollment, Lektion, Prüfung, Completion, Zertifikat als Event | Outbox, Dedupe, Replay, fehlender Workflow-Partner |
| SUITEFLOW-02 | Workflow-Action: autorisiertes Enroll/Revoke oder Benachrichtigung | Rechte, Idempotenz, keine Event-/Action-Schleife |
| SUITEFLOW-03 | Form: kursgebundenes Formular, Aufgabenreferenz, Trainerantwort | Context-Scopes, IDOR, Upload-Schutz, Entry-Eigentum bei Form |
| SUITEFLOW-04 | Review: Abschluss → verifiziertes Erlebnis → Einladung | Consent, Opt-out, Dedupe, Widerruf, keine Bewertungsmanipulation |
| SUITEFLOW-05 | Pass: Event-/Ticketkauf → Kursgrant; Storno → Grantwiderruf | Check-in ist nicht Completion; Teilrefund/Platztransfer |
| SUITEFLOW-06 | Affiliate: Sale/Refund über autoritative Quelle | Woo-Adapter und Course-Adapter niemals doppelt provisionieren |
| SUITEFLOW-07 | License/Pro: Lizenz aktiv, grace, abgelaufen, Server offline | Free-Lernen/Historie unabhängig; Pro-Funktion kontrolliert |
| SUITEFLOW-08 | Kombinierte Rezepte: Kauf → Kurs → Prüfung → Workflow → Review | Korrelation, Reihenfolge, Einwilligung, doppelte Ereignisse |
| SUITEFLOW-09 | Partnerinstallationen automatisch erkennen und einzeln aktivieren | Fehlend/inaktiv/inkompatibel/konfigurationsbedürftig korrekt anzeigen |
| SUITEFLOW-10 | Partner-Upgrade, Downgrade, Deaktivierung, Neuaktivierung | Contract-Handshake, Recovery, keine Datenkorruption |

### 34.6 Querschnitt: Datenschutz, Sicherheit, Betrieb (OPS)

| ID | Ablauf und Regel | Kritischer Ausnahme-/Abnahmetest |
|---|---|---|
| OPS-01 | WP-Datenexport, Lösch-/Anonymisierungsantrag, Retention | Jedes Plugin bearbeitet eigene Daten; Rechtsgrundlagen und Audit |
| OPS-02 | Rollen-/Objektzugriff, REST-Nonce/Auth, Webhook-Signatur | IDOR, CSRF, Replay, SSRF, Rate Limit, Secret-Rotation |
| OPS-03 | Event verspätet, doppelt, out-of-order oder verloren | Idempotenz, Sequenz-/Versionsprüfung, Outbox-Replay |
| OPS-04 | Parallelität: zwei Zahlungen, zwei Refunds, zwei Einlösungen | Atomare Zustandsübergänge, keine doppelten Side-Effects |
| OPS-05 | Job/Queue/DB/Netzwerk/Partner offline | Backoff, Dead-letter/Quarantäne, manuelle Reparatur, Alarm |
| OPS-06 | Admin-Diagnose, Healthcheck, Fehler-/Supportexport | Datenminimierung, RBAC, Korrelation, kein Secret-Leak |
| OPS-07 | Installation, Update, Migration, Deinstallation, Rollback | Datenbankversionen, Backup/Restore, bestehende Nachweise |
| OPS-08 | Übersetzungen, Accessibility, Responsive, E-Mail/Benachrichtigung | Lokalisierung, Tastatur/Screenreader, Opt-out, Zustellfehler |
| OPS-09 | Leistung: große Kurskataloge, Prüfungen, Masseneinschreibungen | Query-/Index-Budget, Last-/Parallelitätstest, Queue-Grenzen |
| OPS-10 | Standalone-Free ohne Partner und ohne Lizenzserver | Alle R1-Kernfunktionen und Lernhistorie weiterhin nutzbar |

### 34.7 Testabdeckung und offene Geschäftsentscheidungen

- **Coverage-Regel:** Jede Katalog-ID wird in der To-do-Liste als Spezifikations-/Testauftrag geführt. Für jede Kombination von Zuständen sind mindestens Erfolgsfall, Ablehnung, Wiederholung, Parallelität, Partnerausfall und Wiederanlauf zu bewerten; **nicht anwendbar** mit Begründung markieren. Ein Szenario gilt erst als abgeschlossen, wenn Owner, Vorbedingungen, Zustandsübergänge, UI-Feedback, Audit und automatisierter oder begründet manueller Test dokumentiert sind.
- **Verantwortungsregel:** Partnerkonzepte sind keine Implementierungsbelege. Vor jedem R3-Connector den aktuellen öffentlichen API-Vertrag und tatsächlichen Code gegenprüfen; bei fehlender Schnittstelle eine Anforderung dokumentieren, keine heimliche Fremdrepo-Änderung.
- **Offene Entscheidungen:** Refund-Policy nach begonnenem Kurs; Nachweis-/Zertifikatsverifikation nach Lizenzablauf; Identität bei Gastkauf; Käufer-/Teilnehmertrennung; Paket-/Platz-Einheiten; Pending-Timeout; Mitgliedschafts-Entitlement; Trainerrollen/Multisite; Datenretention je Objekt; genaue R3-Wellen nach Woo/Voucher. Bis zur Entscheidung sichere Defaults: kein ungeprüfter Grant, keine Account-Zusammenführung per E-Mail, keine automatische Nachweislöschung, keine automatische Kompetenzvergabe.


**Redaktionsregel:** Externe LMS-Wettbewerber werden in diesem Konzept weder namentlich genannt noch verlinkt. Marktanforderungen bleiben produktneutral. Eigene Captain-Suite-Projekte und die interne LearnBoating-Referenz bleiben als technische Quellen erhalten. Dies ersetzt keine rechtliche Prüfung.

## 35. Verbindlicher fachlicher Referenzvertrag: Identität, Enrollment, Refund und Zertifikate

**Status:** Verbindliche konzeptionelle Standardregeln und Abnahmekriterien, **keine Implementierung**. Vor Realisierung sind rechtliche/steuerliche Sonderfälle, reale Schnittstellen und Bestandsdaten zu validieren. Diese Regeln präzisieren Kapitel 30–34; die Reihenfolge R0 → R1 → R2 → R3 bleibt bestehen.

### 35.1 Identität, Nutzer und Rollen

- **Getrennte Rollen:** `buyer` (wirtschaftlicher Besteller), `learner` (berechtigter Lernender), `trainer` (zugewiesener Lehrender), `course_author` (Inhaltsbearbeiter), `course_manager` (Kursverwaltung), `site_admin` (technischer Betreiber). Es sind **fachliche Rollen**, nicht automatisch globale WordPress-Rollen; die konkrete Capability-Matrix ist in R0 zu definieren. Eine Person kann mehrere Rollen innehaben, ohne dass ihre Rechte automatisch kombiniert auf fremde Kursobjekte übertragen werden.
- **Autoritative Identität:** WordPress-User-ID innerhalb einer Site; externer Käufer/Besteller wird zunächst als externe `source + external_subject_id` referenziert. E-Mail-Adresse dient zur Kommunikation/Einladung, **nie als alleiniger Beweis einer Identität** oder automatischer Accountverknüpfung.
- **Gastkauf:** Bestellposition wird zunächst einer nicht personenbezogen offengelegten Claim-/Seat-Referenz zugeordnet; Aktivierung des persönlichen Grants erst nach bestätigtem Account-Claim über zeitlich begrenztes, einmal verwendbares, sicher gehashtes Token und geeignete Verifikation. Ein eingeloggter Käufer erhält den Lernzugang nur dann selbst, wenn die Bestellung tatsächlich ihm zugewiesen wurde.
- **Kauf für Dritte / Gruppenplätze:** Käufer darf freie Plätze zuweisen, erneut einladen und – solange nach Policy zulässig – umverteilen, aber **nicht** Lernfortschritt, Antworten oder personenbezogene Prüfungsergebnisse anderer Personen einsehen. Lernende müssen die Zuweisung bestätigen; eindeutige Seat-ID, Kurs-ID, Order-Line-ID und Learner-ID nach Claim.
- **Trainerzugriff:** ausschließlich zugewiesene Kurse/Gruppen und explizit freigegebene Lerninformationen; Autorenrechte verleihen keine Prüfungs- oder Bestellverwaltung. Jede API, Medienausgabe, Export- und Admin-Aktion prüft Capability **und** Objektzuordnung.
- **Accountwechsel/Löschung:** E-Mail-Wechsel verändert keine Grants. Account-Merge nur nach expliziter sicherer Bestätigung und Audit. Löschanfragen unterscheiden gesetzlich aufzubewahrende Belege, minimal notwendige Prüfungsnachweise und löschbare Profile; keine pauschale irreversible Löschung durch Connectoren.

### 35.2 Autoritativer Enrollment-/Grant-Vertrag

- Course Captain ist **einzige fachliche Autorität** für Kurszugang. Eine externe Quelle meldet lediglich eine nachgewiesene Berechtigung; WooCommerce besitzt Bestell-/Refundstatus, Voucher Captain Guthaben/Einlösungen, License Captain Softwarelizenzen.
- Pro Quellberechtigung ein unveränderlicher `grant_source_key` aus `source_system + source_object + source_line/seat + entitlement_revision`, site- und kursgebunden. Wiederholte Events erzeugen keinen zweiten Grant. Separate Quellen bleiben separate Grants.
- Zustände: `pending_claim`, `pending_confirmation`, `active`, `suspended`, `expired`, `revoked`, `failed`. `suspended` ist reversibel; `revoked` endgültig für diese Quellrevision, erneuter Kauf/erneute Berechtigung erzeugt neue Revision.
- Übergänge: neu → pending_claim/pending_confirmation; verifizierter Claim + gültiger Quellnachweis → active; active → suspended/expired/revoked; suspended → active/expired/revoked; pending → failed/revoked; failed → erneute Verarbeitung nur durch auditierten Retry mit gleicher Quellreferenz und nachgewiesenem Anspruch. Ungültige oder verspätete Ereignisse werden protokolliert, nicht blind angewendet.
- **Effektiver Zugang:** mindestens ein gültiger `active` Grant für Lernenden/Kurs zur Zugriffszeit, sofern keine eigenständige übergeordnete Sicherheitssperre besteht. Ablauf/Widerruf eines Grants entzieht keine anderen gültigen Grants. Ablaufzeiten werden serverseitig in UTC gespeichert, in der UI lokal dargestellt.
- **Kein stiller Widerruf bei Partnerausfall:** deaktivierter oder inkompatibler Connector löst nicht automatisch eine Massenrevokation aus. Neue externe Berechtigungen bleiben bis zur sicheren Bestätigung `pending`; bestehende Rechte folgen dokumentierter Betreiber-/Sicherheits-Policy.
- **Reconciliation:** persistente Inbox/Outbox, Korrelation, Idempotenz, Wiederholung mit Backoff, Quarantäne nach Fehlergrenze, administrativ nachvollziehbare Reparatur. Keine Aktivierung allein aufgrund einer fehlgeschlagenen Reservierung oder eines ungeprüften Webhooks.

### 35.3 Erstattungen, Stornos und Chargebacks

- **Default-Policy:** Nach bestätigter vollständiger Erstattung/Storno/Chargeback wird **nur der betroffene Kauf-Grant** widerrufen; bei Teilrefund nur eindeutig betroffene Positionen/Plätze beziehungsweise nach dokumentierter mengenbezogener Regel. Solange eine Erstattung nur beantragt ist, bleibt der bisherige Status erhalten, sofern nicht eine gesonderte Fraud-/Sicherheitsregel eine auditable Sperre verlangt.
- **Lernbeginn oder Kursabschluss ändern den Refund-Trigger nicht:** Wird der wirtschaftliche Anspruch rechtswirksam aufgehoben, endet der dadurch begründete zukünftige Zugang; Lernfortschritt, Prüfungsversuche und bereits ausgestellte Nachweise werden **nicht automatisch gelöscht oder rückwirkend umgeschrieben**.
- **Zertifikate bleiben grundsätzlich verifizierbar:** Ein Refund allein ist **kein** fachlicher Widerrufsgrund. Bei nachgewiesenem Betrug, ungültiger Prüfung oder irrtümlicher Ausstellung gilt ein separater, begründeter Zertifikats-Widerrufsprozess.
- **Race Conditions:** Zahlung und Refund out-of-order, mehrfach oder parallel: externe Source-Version/Status prüfen, atomaren Grant-Übergang und eindeutige Order-Line-/Seat-Zuordnung verwenden; keine Wiederaktivierung durch verspätetes `paid` nach endgültigem `refunded`.
- **Offen für Betreiber-/Rechtsprüfung:** freiwillige Rückerstattung, gesetzlicher Widerruf, digitale Inhalte, Rückabwicklungsfristen, Teilmengen, Steuern und Archivierungsfristen werden nicht durch das LMS entschieden. WooCommerce/Shop bleibt wirtschaftliche Autorität.

### 35.4 Prüfungen, Zertifikate und Lizenzablauf

- **Nachweisquelle:** Ein Zertifikat referenziert eindeutigen Lernenden, Kurs-/Inhaltsrevision, Prüfungs-/Completion-Snapshot, Ausstellungszeit, Templateversion und signierten/geschützten Verifikationsschlüssel. Completion, Mastery und Competency bleiben unabhängig; ein Kauf oder Ticket-Check-in erzeugt keine Kompetenz.
- **Zertifikatszustände:** `issued`, `corrected` (ersetzte Version mit Verweis), `revoked`. Korrektur überschreibt keinen historischen Nachweis. Widerruf nur mit berechtigtem Akteur, Grund, Zeit, Audit und sichtbarer Statusänderung.
- **Verifikation:** öffentlich abrufbar nur minimale Nachweisinformation und nach bewusst festgelegter Privacy-Policy (z. B. status/issuer/course/date, Personenname nur mit geeigneter Freigabe); Token nicht erratbar, Rate-Limit, kein Verzeichnis fremder Lernender.
- **Softwarelizenz abgelaufen:** Free-Kern, gespeicherte Lernhistorie und bestehende Zertifikats-Verifikation bleiben verfügbar. Eine Pro-Lizenz steuert nur neue Pro-Funktionen, nicht die Wahrheit historischer Prüfungsnachweise. Bei abgeschalteter Site ist keine Live-Verifikation garantiert; langfristiger externer Verifikationsdienst bleibt **separat zu entscheiden**, kein implizites Versprechen.
- **Löschung/Aufbewahrung:** Retention und datenschutzkonforme Anonymisierung pro Datentyp vor Umsetzung festlegen; historische Auditbelege nur soweit rechtlich und technisch begründet. Keine unbegrenzte Aufbewahrung als stiller Default.

### 35.5 Abnahmefälle und verbindliche offene Präzisierungen

| Test-ID | Gegeben / Aktion | Erwartung |
|---|---|---|
| CORE-01 | Gast kauft mit E-Mail eines existierenden Dritten | Kein Zugriff und kein Account-Link ohne verifizierten Claim |
| CORE-02 | Käufer erwirbt zehn Plätze und weist sie verschiedenen Lernenden zu | Zehn eindeutige Seat-/Claim-Zuordnungen; keine Einsicht in fremde Prüfungsantworten |
| CORE-03 | Derselbe Woo-Hook oder Voucher-Callback kommt zehnmal | Exakt ein Quellgrant, keine Mehrfacheinlösung |
| CORE-04 | Zwei Quellen gewähren denselben Kurs; eine wird widerrufen | Kurs bleibt über die zweite Quelle zugänglich |
| CORE-05 | Teilrefund für eine von drei Positionen | Nur eindeutig zugehöriger Grant widerrufen |
| CORE-06 | Refund nach Kursabschluss und Zertifikatsausstellung | Zugang aus dieser Quelle weg; Lernhistorie und Zertifikatsstatus unverändert |
| CORE-07 | Refund kommt vor verspätetem Zahlungs-Event | Kein unbeabsichtigtes Re-Grant |
| CORE-08 | Voucher-Reserve erfolgreich, Grant/Commit scheitert | Pending/Retry/Kompensation; kein Doppelverbrauch |
| CORE-09 | Trainer ruft fremde Lernenden-/Kurs-ID über REST ab | Zugriff verweigert und sicher auditiert |
| CORE-10 | Pro-Lizenz läuft ab | Free-Lernen, historische Ergebnisse und Zertifikatsverifikation funktionieren weiter |
| CORE-11 | Zertifikat wird begründet widerrufen | Verifikation zeigt revoked; historischer Ausstellungsbeleg bleibt nachvollziehbar |
| CORE-12 | Connector wird deaktiviert und später reaktiviert | Keine stillen Massenwiderrufe, Reconciliation ohne Duplikate |

**Noch zu konkretisieren (R0):** WP-Capability-Matrix pro Objekt; endgültige Identitäts-Claim-UX und Fristen; Gruppenplatz-Umbuchungsregeln; tatsächliche Woo-/Voucher-Status- und Revisionsverträge; rechtliche Refund-/Retention-Policy je Markt; genaue Zertifikatsfelder und Privacy-Defaults; Support-/Timeout-SLAs. Diese Punkte dürfen nicht als implementiert oder abschließend juristisch geprüft gelten.

## 36. R0-Spezifikation: WordPress-Capabilities, Objektrechte und Datenschutzgrenzen

**Status:** Architekturvorgabe, nicht implementiert. Rollen sind frei kombinierbare Berechtigungsbündel; jede Operation prüft **Capability + Objektbeziehung + Site-Kontext + Status**. WP-Administrator ist nicht gleich automatischer Kursinhaltseigentümer; `manage_options` wird nicht als allgemeine LMS-Autorisierung missbraucht.

| Aktion / Ressource | Lernender | Käufer | Trainer | Autor | Kursmanager | Site-Admin |
|---|---|---|---|---|---|---|
| Eigene Einschreibungen/Fortschritte lesen | eigen | nein* | zugewiesen, nur nötige Übersicht | nein | im verwalteten Kurs, minimiert | nur zweckgebunden |
| Eigene Prüfung starten/abgeben | eigen + active Grant | nein | nein | nein | nein | nein |
| Prüfungsantworten/Versuchsdaten lesen | eigen, soweit freigegeben | nein | nur zugewiesene Bewertung | nein | autorisiert, auditpflichtig | Supportfall, auditpflichtig |
| Kursinhalt erstellen/bearbeiten | nein | nein | nur explizite Zusatzberechtigung | eigene/zugewiesene | verwaltete | explizite Capability |
| Kurs veröffentlichen/archivieren | nein | nein | nein | nur mit Freigaberecht | verwaltete | explizite Capability |
| Teilnehmer einladen/Plätze zuweisen | nein | gekaufte unzugewiesene Plätze | nur explizite Kursbefugnis | nein | verwaltete | Supportfall |
| Grant manuell erstellen/widerrufen | nein | nein | nein | nein | verwaltete, nach Policy | explizite Capability + Audit |
| Zertifikat ausstellen/korrigieren/widerrufen | nein | nein | bewerten, nicht allein widerrufen | nein | autorisierte Freigabe | explizite Capability + Grund |
| Integrationen/Secrets konfigurieren | nein | nein | nein | nein | nein | eigene Integrations-Capability |
| Datenschutzexport/-löschung auslösen | eigene Anfrage | eigene Daten | nein | nein | nein | befugte Datenschutzrolle |

* Käufer erhält nur Bestell-, Platz- und Einladungsstatus, **nicht** Lernleistungen anderer Personen. Ein Nutzer mit mehreren Rollen erhält Zugriff ausschließlich im jeweils zulässigen Objektkontext.

**Vorgeschlagene Capability-Gruppen (vor Implementierung zu finalisieren):** `cc_view_own_learning`, `cc_manage_own_seats`, `cc_edit_courses`, `cc_publish_courses`, `cc_manage_enrollments`, `cc_grade_assigned`, `cc_manage_certificates`, `cc_view_course_reports`, `cc_manage_integrations`, `cc_manage_settings`, `cc_export_personal_data`. Die tatsächlichen Slugs, WP-Meta-Cap-Mappings und Default-Rollenzuweisungen werden in R0 durch ADR freigegeben. **Deny by default**, insbesondere bei fehlender Objektzuweisung, inaktivem Grant, fremder Site, Gastzugriff oder deaktiviertem Connector.

**Prüfpunkte:** REST-Route, Admin-Post/AJAX, Block-Editor, Shortcode, Medien-Download, Datei-Upload, Webhook, Cron und CLI durchlaufen denselben serverseitigen Autorisierungsservice; Nonces ersetzen keine Rechteprüfung. Cross-site-Daten bleiben R4. Reports aggregieren/minimieren; alle Einsichten in fremde Prüfungs-/Personendaten sind zweckgebunden und auditiert.

## 37. R0-Datenmodell und Transaktionsgrenzen – fachlicher Entwurf

**Keine endgültige Tabellenentscheidung:** WordPress-native Post-/Meta-Strukturen für redaktionelle Inhalte sind zu bevorzugen, wo angemessen; transaktionale Datensätze mit Eindeutigkeit, Konkurrenzschutz und großen Volumina benötigen einen begründeten eigenen Storage-Entwurf. Keine DB-Implementierung vor ADR und Migrationsplan.

| Entität | Pflichtschlüssel / Beziehungen | Invarianten |
|---|---|---|
| `Course`, `CourseRevision` | site_id, course_id, revision_id, status | Veröffentlichte Prüfungsreferenzen sind revisionsfest |
| `Lesson`, `Activity`, `Question`, `QuestionRevision` | course_revision_id, activity_id, question_revision_id | Neutraler Providervertrag; keine fachdomänenspezifischen Felder im Kern |
| `AssessmentBlueprint`, `Attempt`, `AttemptSnapshot`, `Answer` | learner_id, course_revision_id, attempt_id, snapshot_id | Versuchssnapshot nach Start unveränderlich; Abgabe idempotent |
| `LearningProgress`, `MasteryState`, `CompetencyEvidence` | learner_id, activity_id, revision, updated_at | Completion ≠ Mastery ≠ Competency; monotone/versionsichere Updates |
| `BuyerReference`, `Seat`, `Claim` | source_system, external_buyer_id, order_line_id, seat_id, token_hash | E-Mail keine Primäridentität; Claim einmalig, zeitlich begrenzt |
| `Grant`, `GrantEvent` | site_id, learner_id, course_id, grant_source_key, revision, state | Unique source key; effektiver Zugang aus aktiven Quellen |
| `Certificate`, `CertificateRevision`, `VerificationToken` | learner_id, course_revision_id, evidence_snapshot_id, certificate_id | Korrektur/Widerruf historisiert, Verifikation datensparsam |
| `IntegrationConnection`, `InboxEvent`, `OutboxEvent`, `Job` | connector_id, contract_version, event_id, correlation_id | Dedupe, Wiederholung, Quarantäne, kein Secret in Logs |
| `AuditRecord`, `RetentionRule` | actor, action, object, reason, timestamp, site_id | Manipulationserschwerung, Zweckbindung, definierte Löschfristen |

**Technische Vorgaben:** WordPress-User-ID nur innerhalb der Site eindeutig interpretieren; externe IDs immer mit Source und Scope. UTC für Zeitstempel; explizite Zeitzone in UI. Eindeutigkeit für `grant_source_key`, `event_id + connector_id`, `attempt_id + submission_revision`, `certificate_id` und einmalige Claims. DB-Transaktionen/Compare-and-swap für konkurrierende Statuswechsel; keine Verlass auf WP-Cron als garantierte Sofortzustellung. Schema-/API-Versionierung, Backfill, Dry-run, Rollback-/Forward-Fix-Strategie, Indizes, Lösch- und Exportpfade vor R1.

**Dateneigentum:** Woo besitzt Bestellungen/Refunds; Voucher Guthaben/Redemption; License Softwarelizenz; Course besitzt Grant, Lernfortschritt, Prüfungsversuche und Zertifikate. Keine redundanten Vollkopien fremder Datensätze. API-Verträge enthalten `schema_version`, `source`, `source_event_id`, `occurred_at`, `correlation_id`, `object_scope` und minimalen Payload. Partnerdetails erst nach echtem Codeabgleich fixieren.

## 38. R0-Paritätsinventar: verbindliche Prüfschablone statt unbelegter Vollständigkeit

**Wichtig:** Die folgende Liste ist eine **vorläufige, aus den bisherigen Konzeptentscheidungen abgeleitete Prüfschablone**. Sie ist **keine** Funktion-für-Funktion-Auditierung des LearnBoating-Codes und darf nicht als abgeschlossene 100-%-Parität ausgegeben werden. Jede Zeile braucht Codepfad, Version/Commit, UI-Nachweis, Datenstruktur, Abhängigkeiten, Wiederverwendungsentscheidung, Testfixture und R1-Akzeptanz.

| Inventar-ID | Neutraler Funktionsbereich | R0-Nachweis / R1-Abnahme |
|---|---|---|
| PAR-01 | Kurskatalog, Kursstruktur, Freigabe | Alle sichtbaren Kurs-/Lektionszustände und Zugriffspfade |
| PAR-02 | Lektionen, Medien, Videos, Downloads | Vollständige Wiedergabe-/Zugriffsszenarien und Rechte |
| PAR-03 | Fragenbank, Quiz, Auswertung | Fragetypen, Antwortlogik, Bewertungsgrenzen und Revisionsverhalten |
| PAR-04 | Zeitprüfungen und Prüfungsversuche | Zeitlimit, Wiederholung, Snapshot, Abbruch, Doppelabgabe |
| PAR-05 | Karteikarten und Favoriten | Erstellen, Speichern, Filtern, Wiederfinden, Berechtigung |
| PAR-06 | Fehlertraining und Smarttrainer | Fehlerstatus, Wiederholungsintervalle, Mastery-Logik |
| PAR-07 | Lernfortschritt und Completion | Persistenz, Resume, Reset, Berichte und Migration |
| PAR-08 | Nutzer-/Kurszugang und Entitlements | Standalone-Free, Quelle, Laufzeit, Mehrfachzugang |
| PAR-09 | Content-Import, Export und Rollback | Validierung, Preview, atomare/kompensierbare Rücknahme |
| PAR-10 | WP-Admin-Autorenworkflow und Darstellung | Editoren, Vorschau, Veröffentlichung, Responsive/Accessibility |
| PAR-11 | REST-/AJAX-Endpunkte, Cron und Events | Vertrag, Rechte, Idempotenz, Versionen, Hintergrundjobs |
| PAR-12 | Datenmigration und Regression | Golden Master, Fixture, Altbestandsdaten, Prüfungsnachweise |

**Klassifizierung pro Originalfunktion:** `reuse` (mit Lizenz-/Qualitätsprüfung), `abstract` (fachneutralisieren), `replace` (mit nachvollziehbarer Begründung), `exclude_domain_specific` (Boot/Funk/Navigation/amtliche Prüfungsinhalte), `unknown` (blockiert R0-Gate A). Funktionen, die LearnBoating neutral bereits hat, gehören nach R1 und dürfen nicht als R2-Neuheit verschoben werden. Jede Paritätsbehauptung benötigt Quellbeleg und automatisierten oder begründet manuellen Regressionstest.

**R0 Gate A:** 100 % der identifizierten Originalfunktionen klassifiziert; alle `unknown` aufgelöst; neutraler Golden Master definiert; ADRs zu Rollen, Datenmodell, Identität und Revisionslogik akzeptiert. **R1 Gate B/C:** Jede neutrale Funktion nachweislich abgenommen; Free ohne Suite/Woo/Lizenz lauffähig. R2/R3 bleiben nachgeordnet.

## 39. LearnBoating-Quellabgleich I – belegte Repository-Dokumentation und Paritätsdeltas

**Quellstand:** `hastla007/learnboating`, Standardbranch, Dokumente `README.md`, `konzept-wp-lernplugin.md`, `schema/README.md`, `docs/schnittstellen-erweiterungen.md`, geprüft am 15.11.2026. Dies ist eine **Dokumentations- und Schnittstelleninventur**, keine vollständige zeilenweise Implementierungsprüfung. Für jede Behauptung über tatsächlich vorhandenen Code sind im nächsten Schritt Klasse/Methode, Test und Commit zu belegen. Das LearnBoating-Repository bleibt unverändert.

### 39.1 Konkrete zusätzliche R1-Prüffälle

| ID | Beleg im LearnBoating-Repository | Neutrale Course-Captain-Abstraktion | Grenze / Nachweis |
|---|---|---|---|
| LB-01 | `README.md`: Woo-Produktzuordnung, bezahlte Orders, Reconcile und stündlicher Job | generischer Entitlement-/Reconciliation-Kern; Woo-Adapter **erst R3.1** | R1 darf nicht Woo voraussetzen; Order-spezifische Teile R3 |
| LB-02 | `README.md`: „Zugang ohne Shop“, kostenlose Teilbereiche, zeitgesteuerte Freigaben | Free-/Manual-/Scheduled-Grant-Policies | R1 |
| LB-03 | `README.md`: Gruppenlizenzen „Plätze je Stück“ | Seat-/Invitation-/Claim-Kern | R1, Shop-Verkauf erst R3 |
| LB-04 | `README.md`: geschützte Seiten, Block, Shortcode, PDF/Private Storage | generische Access Guards und sicherer Medienzugriff | R1, keine Bootsbezeichnungen |
| LB-05 | `README.md`: Login-Härtung, IP-Limits, Account-/Privacy-Flows | WP-konforme Schutzmaßnahmen und Datenschutzpfade | R1, kein pauschales XML-RPC-Abschalten ohne Kompatibilitätsprüfung |
| LB-06 | `README.md`: Consent für externe Videos/Hosts | einwilligungsbewusste Medienausgabe | R1 |
| LB-07 | `README.md`: Content-Paketimport, Dry-run, Rollback, Retention, Fehlerlog | versionierter neutraler Importer und Recovery | R1 |
| LB-08 | `README.md`: Integrationstests, E2E Lern-/Admin-/Account-/Checkout-Flows | Golden-Master-/Regressionstestfamilien | R0/R1; Woo-Flows R3 |
| LB-09 | `schema/README.md`: stabile Source-IDs, Hashes, Revisionen, Rights-/Publish-Status | Content-Provenienz, Rechteprüfung, revisionsfester Import | R1 |
| LB-10 | `schema/README.md`: single/multi/open_text/chart_task | generisches Fragetyp-Providerkonzept | Freie Aufgaben generisch; bootspezifische Kartenprüfung ausgeschlossen |
| LB-11 | `docs/schnittstellen-erweiterungen.md`: Voucher-Grant, Dedupe, Failure-Callback, zwei Einlösewege | fachneutraler Grant-/Saga-Vertrag | Kern R1; Voucher-Adapter R3.2, niemals zwei aktive Einlösewege |
| LB-12 | `docs/schnittstellen-erweiterungen.md`: Ticketstatus-/Event-Schnittstellen | optionale Activity-/Event-Provider-Verträge | R3.4; kein Praxis-Ticket-Zwang |
| LB-13 | `konzept-wp-lernplugin.md`: Snapshot-Konverter, Manifest, Importhistorie | versionierte Content-Pipeline und Quellmapping | R1, kein Laravel-Laufzeitzwang |
| LB-14 | `konzept-wp-lernplugin.md`: Fragenrevisionen, feste und zufällige Prüfungen | Prüfungsblueprints, unveränderliche Versuche | R1; amtliche Bögen nur Daten, keine Domänenlogik |
| LB-15 | `README.md`: Mitgliederrabatte, Zahlungsstreitfälle, Garantieanteile | optionale Commerce-Policies | R3; nicht Voraussetzung für Free |

**Wichtige Release-Trennung:** Eine in LearnBoating vorhandene **generische Kernfunktion** gehört in R1; eine **konkrete Woo-/Voucher-/Ticket-Integration** wird trotz LearnBoating-Vorbild erst im bereits verbindlich festgelegten R3 umgesetzt. R1 stellt die nötigen neutralen Erweiterungspunkte bereit, ohne diese Partner zu benötigen. Weder R1 noch R2 dürfen durch Connectoren blockiert werden.

### 39.2 Nicht als generischer Kern übernehmen

- Bootsführerschein-, Funk-, Navigations- und amtliche Prüfungskataloge sowie deren Regelwerke, Prüfungsstellen und Anmeldedaten.
- Fachspezifische Simulatorgeräte und vorgefertigte nautische Szenarien; **wiederverwendbar** sind lediglich generische Interaktions-/Activity-Provider, sofern sinnvoll.
- Bootsbezogene Kurscodes, Shortcodes, PHP-Präfixe, Paketnamen und proprietäre Quellannahmen; ersetzen durch neutrale, versionierte Course-Captain-Verträge.
- Rechteunklare Medien und Inhalte niemals ungeprüft übernehmen; Quellrechte, Lizenz und Provenienz sind vor Kopie/Veröffentlichung separat zu prüfen.

### 39.3 Quellverweise und Evidenzstatus

- `https://github.com/hastla007/learnboating/blob/main/README.md`
- `https://github.com/hastla007/learnboating/blob/main/konzept-wp-lernplugin.md`
- `https://github.com/hastla007/learnboating/blob/main/schema/README.md`
- `https://github.com/hastla007/learnboating/blob/main/docs/schnittstellen-erweiterungen.md`

**Status:** LB-01 bis LB-15 sind aus Repository-Dokumenten belegte **Prüfkandidaten**, keine bestätigten Codeimplementierungen. Nächster Pflichtschritt: konkrete Plugin-Klassen, REST-Routen, Datenbankmigrationen, Tests und tatsächliche Zustandsautomaten gegen den aktuellen Quellcommit abgleichen; weitere unentdeckte Funktionen ergänzen. Gate A bleibt offen.

## 40. Operativer Umsetzungsplan und Aufgabensteuerung

Die **führende, schrittweise abzuarbeitende Aufgabenliste** steht in [`TODO-WETTBEWERBSANALYSE.md`](TODO-WETTBEWERBSANALYSE.md), Abschnitt **„VERBINDLICHER SCHRITT-FÜR-SCHRITT-UMSETZUNGSPLAN“**. Jede Aufgabe hat eine stabile ID, Voraussetzungen, Definition of Done und Nachweispflicht. Ältere P0/P1/P2-/FLOW-/CORE-/LB-Abschnitte dienen als Anforderungsregister und dürfen die Release-Reihenfolge nicht überschreiben.

**Harte Gates:** R0 vollständige codebelegte Inventur und ADRs → R1 neutrale LearnBoating-Parität in standalone Free → R2 zusätzliche P0-Funktionen → R3 WooCommerce, Voucher, übrige Suite/Pro → R4 ausschließlich nach Nachfrage und Freigabe. Bei ungeklärter Quelle, fehlendem Test oder nicht erfülltem DoD bleibt die Aufgabe offen. Keine Implementierung ist durch die Erstellung dieses Plans erfolgt.

## 41. Zusätzliche verbindliche Qualitäts- und Betriebsanforderungen

Der operative Aufgabenindex in `TODO-WETTBEWERBSANALYSE.md` enthält ergänzende Tasks `R0-13…R0-18`, `R1-19…R1-24`, `R2-06`, `R3-10…R3-11` und `R4-03`. Sie schließen bisher unterrepräsentierte Aspekte ab: Betriebs-/Supportkonzept, Performance und Skalierung, Barrierefreiheit, Mehrsprachigkeit, Prüfungsintegrität, sichere E-Mail-Kommunikation, Bedrohungsmodell, Datenschutz, Backup/Restore, Upgrade/Downgrade, CI-/Security-Checks, Monitoring, Dokumentation und kompatible Releases.

**Freigaberegel:** Diese Punkte sind keine optionalen Nacharbeiten; ihre DoDs und negativen Tests sind vor den jeweiligen Release-Gates zu erfüllen. Bei Fehlern, Netzunterbrechung, doppelten Events, konkurrierenden Aktionen, vollen Speichern, Mail-Ausfall oder Plugin-Deaktivierung müssen Datenintegrität und sichere Wiederherstellung belegt werden. Keine Annahme, dass Tests oder Implementierungen bereits existieren.

## 42. Simulierte Drei-Experten-Lückenprüfung: ergänzende Umsetzungsaufträge

**Rollen:** WordPress-/LMS-Chefarchitekt, LMS-Produktstrategin/Learning-Experience-Expertin, Principal Engineering Lead für Qualität und Delivery. Diese Diskussion ist eine simulierte Rollenreview, keine echte externe Expertenberatung. Maßgeblich bleibt die operative Checkliste.

**Architekt:** Ohne definierte WordPress-Kompatibilitätsmatrix, öffentliche API-/Hook-Verträge, Cache-Invalidierung, Race-/Konsistenzregeln und Versionierungsstrategie entstehen Sicherheits- und Wartungsrisiken. Ergänzt: `R0-19…21`, `R0-25`, `R1-25`, `R1-28`, `R1-30`, `R3-12`.

**LMS-Produktstrategin:** Vollständige Features reichen nicht; Autoren benötigen verlässliche Publish-Validierung, Lernende revisionsfeste Prüfungen und verständliche Abläufe. Accessibility/Usability müssen in reale Abnahmen münden. Ergänzt: `R0-22…23`, `R0-26`, `R1-26…27`, `R1-29`, `R2-07`, `R3-13`.

**Engineering Lead:** Große Tasks ohne Definition of Ready, testbare Unterpakete und Traceability sind nicht ausführbar. Ergänzt: `R0-24` und verbindliche Task-Splitting-/DoD-Regeln in der To-do-Liste.

**Konsens und Gegenprüfung:** R1 bleibt auf die belegte fachneutrale LearnBoating-Parität begrenzt; reale Nutzbarkeit und Integrität sind Teil der Paritätsabnahme, keine zusätzlichen Marktfeatures. Erweiterte Autorenproduktivität gehört nach R2, Woo/Voucher-Connectoren nach R3. Neue Aufgaben sind erst nach Codeprüfung und Tests als abgeschlossen markierbar. Offene Architektur-/Produktentscheidungen sind explizite R0-Abhängigkeiten. Es wurde kein Programmcode verändert.

## 43. Simulierte Experten-Debatte: verbindliche Betreiber-Admin-Einstellungen

**Review-Rollen:** WordPress-Plugin-Architekt, LMS-Produktmanagerin/Learning-Experience-Expertin, Security-/UX-Operations-Expertin. Simulierte fachliche Rollen, keine externe Beratung. Die Matrix ist eine **Planungsentscheidung**; technische Details und UX müssen in R0 validiert werden.

### 43.1 Kontroverse und Konsens

- **Architekt:** WordPress-Systemwerte erben, keine doppelte Konfiguration; alle Felder brauchen Typ, Validierung, Capability, Migration und versionierte Speicherung.
- **Produktmanagerin:** Globale Defaults, Kurs-/Prüfungsüberschreibung und nutzerverständliche Vorschau trennen; seltene Betriebsoptionen nicht zwischen Alltagsregeln mischen.
- **Security/Operations:** Deny-by-default, keine rückwirkende Änderung historischer Versuche, Warnung vor riskanten Umschaltungen, Secrets niemals im normalen Export, Audit und sichere Recovery.
- **Konsens:** Globaler Default → expliziter Kurs-/Prüfungs-Override → unveränderlicher Snapshot bereits gestarteter Versuche und Nachweise. WP-Settings (Locale, Zeitzone, Absender) grundsätzlich erben. Nicht alle unten genannten Werte werden als freie Toggle angeboten: systemkritische Invarianten bleiben fest verdrahtete Sicherheitsregeln.
- **Wichtige Editionsauflösung:** Der **neutrale Seat-/Claim-/Grant-Kern** muss R1 Free abdecken; erweiterte Gruppenorganisation kann Pro sein. **Nachweisausstellung/Verifikation** als fachlicher Kern unabhängig von Pro-Lizenz; erweiterter Zertifikatsdesigner optional Pro. Bestehende gegenteilige Kurzbezeichnungen in Kapitel 10 sind entsprechend präzisiert, ohne vorweggenommene Implementierungszusage.

### 43.2 Admin-Menü und Bedienregeln

**Course Captain → Übersicht, Kurse, Fragenbank, Quizzes & Prüfungen, Lernende, Gruppen (Grundfunktionen Free; Pro-Erweiterungen gekennzeichnet), Zertifikate (Basis Free; Designer Pro), Berichte, Inhalte importieren, Integrationen, Einstellungen, Systemstatus.** Pro-Lizenz nur bei aktivem Add-on. Einstellungen: Tabs Allgemein, Kursdarstellung, Lernen, Prüfungen, Einschreibung, Rollen & Rechte, Zertifikate, E-Mails, Datenschutz & Sicherheit, Erweitert. Integrationen, Diagnose, Import/Export und Pro-Lizenz bleiben eigene Bereiche.

**UX-Vertrag:** Jede Einstellung hat Erklärung, Standard, Geltungsbereich, Abhängigkeit, Validierung und sichtbaren Effekt. Pro Tab speichern und zurücksetzen; Warnung bei ungespeicherten Änderungen; Bestätigung bei Risiko; Rollenprüfung serverseitig; Tastatur/Screenreader; keine irreführenden Pro-Sperren für Free-Kernfunktionen. Globales „Reset alles“ ohne Datensicherung verboten.

### 43.3 Vollständige Einstellungs- und Entscheidungs-Matrix (Mindestumfang)

| Tab | Einstellung | Vorgabe | Edition | Ebene | Schutz-/Validierungsregel |
|---|---|---|---|---|---|
| Allgemein | Lernbereich-Permalink | /lernen/ | Free | Global | Slug eindeutig; Rewrite-Regeln nur nach bestätigtem Speichern |
| Allgemein | Kurskatalog-Seite | Bei Setup auswählen/erstellen | Free | Global | Existenz/Publish/Rechte prüfen; keine automatische Überschreibung |
| Allgemein | Lernenden-Startseite | Mein Lernen | Free | Global | Seitenzuordnung und Fallback prüfen |
| Allgemein | Sprache, Zeitzone, Datum | WordPress erben | Free | WP | Keine doppelten Optionen; pro Nutzer Locale falls verfügbar |
| Allgemein | Öffentliche Kursindexierung | Öffentliche Katalogseiten indexierbar; private noindex | Free | Global/Kurs | Keine privaten Daten in SEO/Sitemap |
| Allgemein | Deinstallation/Datenlöschung | Daten behalten, expliziter separater Löschvorgang | Free | System | Nie automatisch personenbezogene Lernhistorie entfernen |
| Kursdarstellung | Layout/Breadcrumbs | Theme-kompatibel, Breadcrumbs an | Free | Global/Kurs | Scoped Styles; Theme-Tokens respektieren |
| Kursdarstellung | Farben/Typografie | Theme erben | Free | Global | AA-Kontrast prüfen; keine harten globalen CSS-Overrides |
| Kursdarstellung | Kurskarten/Sortierung | Manuelle Reihenfolge, Titel-Fallback | Free | Global | Leere Kurse nicht ungeprüft veröffentlichen |
| Kursdarstellung | Medien/Externe Hosts | Extern erst nach Klick/Einwilligung | Free | Global/Medium | Keine stillschweigende externe Verbindung |
| Lernen | Fortschrittsregel | Abschluss nur nach erfüllten Pflichtaktivitäten | Free | Global/Kurs | Fortschritt vs Mastery getrennt |
| Lernen | Fortsetzen/Autosave | Aktiv | Free | Global | Idempotente Writes, sichere Wiederaufnahme |
| Lernen | Smarttrainer/Wiederholung | Belegte neutrale Standardstrategie | Free | Global/Kurs | Algorithmen versioniert; kein ungeprüfter Lernwirksamkeitsclaim |
| Lernen | Favoriten/Fehlertraining | Aktiv, privat | Free | Global/Kurs | Datenschutz und Export |
| Lernen | Lektionsfreigabe/Drip | Sofort, ohne Zeitplan | Free | Global/Kurs | Zeitgesteuerte Free-Grants unabhängig von Pro-Marketing |
| Lernen | Kursrevision bei aktiven Lernenden | Laufende Versuche behalten Snapshot | Free | System/Kurs | Änderungen mit Vorschau und expliziter Migrationsregel |
| Prüfungen | Versuche | Unbegrenzt als neutraler Default | Free | Global/Prüfung | Kursregel kann begrenzen; keine Rückwirkung auf alte Versuche |
| Prüfungen | Zeitlimit | Aus | Free | Global/Prüfung | Serverzeit maßgeblich; Timeout explizit |
| Prüfungen | Bestehensgrenze | 70 % als änderbarer Vorschlagswert, nicht amtlich | Free | Global/Prüfung | Fragetyp-/Punktregeln vor Veröffentlichung validieren |
| Prüfungen | Fragenreihenfolge | Mischen aus | Free | Global/Prüfung | Feste Bögen dürfen nicht unabsichtlich verändert werden |
| Prüfungen | Feedback/Solutions | Nach Abgabe | Free | Global/Prüfung | Keine vorzeitige Lösungsoffenlegung |
| Prüfungen | Abbruch/Wiederaufnahme | Sicher wiederaufnehmen | Free | Global/Prüfung | Snapshot, Ablaufzeit und Doppelabgabe testen |
| Prüfungen | Ergebnisaufbewahrung | Nach Datenschutzpolicy, nicht pauschal löschen | Free | Global | Zertifikats-/Nachweiskonsistenz |
| Einschreibung und Gruppen | Standard-Einschreibung | Manuell/Freigabe erforderlich | Free | Global/Kurs | Keine unbeabsichtigte öffentliche Freischaltung |
| Einschreibung und Gruppen | Kostenlose Selbstanmeldung | Aus bis bewusst aktiviert | Free | Global/Kurs | Explizite Betreiberentscheidung |
| Einschreibung und Gruppen | Standard-Zugangsdauer | Unbefristet für manuelle Grants | Free | Global/Kurs | Ablauf nur nach expliziter Policy |
| Einschreibung und Gruppen | Gruppenplätze/Claim | Grundfunktion aktivierbar | Free | Global/Kurs | Seat-Zuordnung, Einladungen, keine E-Mail-Autoverknüpfung |
| Einschreibung und Gruppen | Erweiterte Gruppenverwaltung | Aus, nur bei Pro-Verfügbarkeit | Pro | Global/Gruppe | Basis-Seat-Rechte dürfen ohne Pro nicht verschwinden |
| Einschreibung und Gruppen | Entzug/Sperrung | Einzelgrant gezielt, mit Vorschau | Free | Objekt | Mehrfachgrants berücksichtigen; Fortschritt erhalten |
| Rollen und Rechte | Standard-Lernendenrolle | WP Subscriber oder eigene minimal privilegierte Rolle nach ADR | Free | Global | Keine automatischen Admin-Rechte |
| Rollen und Rechte | Trainer-/Autorenrechte | Least privilege; Kurs-Objektberechtigung | Free | Global/Objekt | Capabilities statt Rollennamen prüfen |
| Rollen und Rechte | Rechteänderungen | Nur Administrator/autorisiert | Free | System | Nonce, Audit, IDOR-Tests |
| Rollen und Rechte | Supportzugriff | Standardmäßig kein Zugriff auf private Antworten | Free | Global | Explizite Berechtigung und Audit |
| Zertifikate | Automatische Ausstellung | Aus, erst bei definierten Abschlussregeln | Free | Global/Kurs | Historische Nachweise revisionsfest |
| Zertifikate | Verifikation | Minimaldaten, Widerrufsstatus sichtbar | Free | Global/Nachweis | Keine E-Mail/PII in öffentlichen Links |
| Zertifikate | Designvorlagen/Branding | Basisvorlage; Designer optional | Free/Pro | Global/Kurs | Designer in Pro, historische Verifikation nicht Pro-abhängig |
| Zertifikate | Widerruf/Korrektur | Nur berechtigt, mit Grund und Audit | Free | Objekt | Keine stille Überschreibung |
| E-Mails | Absender | WordPress erben | Free | Global | Testmail und SMTP-Konflikte berücksichtigen |
| E-Mails | Transaktionale Ereignisse | Einschreibung/Einladung/Abschluss aktiv | Free | Global | Versand erst nach Zustandsprüfung; Dedupe |
| E-Mails | Optionale Erinnerungen | Aus | Pro | Global/Kurs | Opt-out, Frequency Cap, Ruhezeiten |
| E-Mails | Templates | Übersetzbare Standardvorlagen | Free | Global | HTML/Text, sichere Platzhalter, Vorschau |
| E-Mails | Versandfehler | Retry begrenzt, anonymisierter Status | Free | System | Kein PII-Logging |
| Datenschutz und Sicherheit | Datenexport/Löschung | WP Privacy API integriert | Free | System | Rechtliche Aufbewahrung gesondert prüfen |
| Datenschutz und Sicherheit | Retention | Datenart-spezifisch, vor Löschung festlegen | Free | Global | Keine erfundene pauschale Frist |
| Datenschutz und Sicherheit | Audit-Logging | Sicherheits-/Adminaktionen minimal protokollieren | Free | System | Redaction und Zugriffsschutz |
| Datenschutz und Sicherheit | Geschützte Medien | Private Ablage + serverseitige Zugriffsprüfung | Free | System | Direktzugriff unter Apache/nginx testen |
| Datenschutz und Sicherheit | Externe Medien | Consent vor Abruf | Free | Global/Medium | Consent-Revoke respektieren |
| Datenschutz und Sicherheit | Login-Härtung | WP-/Security-Plugin-kompatibel, keine pauschalen Eingriffe | Free | System | Keine doppelte Lockout-Logik erzwingen |
| Integrationen und Betrieb | Plugin-Erkennung | Automatisch, keine Pflichtintegration | Free | System | Capability/Version prüfen; keine stille Aktivierung |
| Integrationen und Betrieb | WooCommerce | Nicht vorhanden bis R3.1 | Free Connector R3 | Integration | Produkte/Refund/HPOS nur nach Connector-Installation |
| Integrationen und Betrieb | Voucher Captain | Nicht vorhanden bis R3.2 | Free Connector R3 | Integration | Genau ein Einlöseweg, Dedupe/Recovery |
| Integrationen und Betrieb | Pro-Lizenz | Nur bei installiertem Pro | Pro | Eigene Seite | Free bleibt unabhängig |
| Integrationen und Betrieb | Import-Dry-run/Rollback | Vorschau zwingend; Rollback verfügbar | Free | System | Keine unkontrollierten Live-Änderungen |
| Integrationen und Betrieb | Queue/Jobs | Sichere Defaults, Retry begrenzt | Free | System | Cron-Ausfall anzeigen |
| Integrationen und Betrieb | Diagnose/Debug | Debug aus; zeitlich begrenzt einschaltbar | Free | System | Secrets/PII redigieren; Ablauf erzwingen |
| Integrationen und Betrieb | Einstellungen Import/Export | Nur sichere Nicht-Secret-Settings | Free | System | Schema-/Versionsvalidierung, Preview |
| Integrationen und Betrieb | Zurücksetzen | Nur aktueller Tab, Bestätigung | Free | System | Kein Reset von Lernständen/Grants |

**Einordnung:** „Free/Pro“ bedeutet Free-Basis mit Pro-Erweiterung, nicht Lizenzpflicht für die Kernfunktion. „Free Connector R3“ bedeutet optionaler Adapter, nicht Woo-/Voucher-Pflicht für R1. Die genannten Defaultwerte sind **Vorschläge**, die R0 anhand LearnBoating-Verhalten, UX-Tests und Datenschutz prüfen muss; insbesondere 70 % Bestehensgrenze ist kein amtlicher Standard. Retention-Zeiten sind erst nach Rechts-/Betreiberentscheidung festzulegen. Werte wie „geschützte Medien“ sind Sicherheitsinvarianten und dürfen nicht abschaltbar sein.

### 43.4 Spezifikation pro Feld und Prüfpflicht

Jede Einstellung erhält im R0-Settings-Register: stabilen Key/Namespace, Datentyp/Enum, zulässigen Wertebereich, Default und Vererbungsquelle, Edition/Release, Scope (WP/global/Kurs/Prüfung/Objekt), benötigte Capability, Sanitization/Validation, Persistenz/Migration, REST-/CLI-Sichtbarkeit, Nebenwirkungen bei Änderung, Audit-/Privacy-Klasse, UI-Hilfetext und positive/negative Tests. Einstellungen mit Secrets erhalten eigenen verschlüsselungs-/secret-store-konformen Konfigurationsweg und erscheinen nie in Export, REST-Responses oder Diagnose. Kein pauschales Versprechen von Verschlüsselung, bevor Plattform/Keymanagement entschieden sind.

### 43.5 Freigabe- und Konfliktfragen

Vor R1 entscheiden: genaue Free-Gruppen-UI, Umfang der Basiszertifikate, konkrete Prüfungsvorgaben, Kurs-Overrides und ihre Snapshots, Standardrollen, Retention-Policies, Admin-Capabilities, WordPress-Kompatibilität, Migrations-/Reset-Verhalten und Accessibility. R3-Connector-Settings werden erst mit realen Partner-APIs und Tests freigegeben. Keine Implementierung durch diese Dokumentation.

## 44. Quervergleich anderer Captain-Konzepte – nur LMS-relevante Deltas

**Tatsächlich eingesehene Quellen (Konzeptdokumentation, nicht Implementierungsnachweis):** [Form Captain](https://github.com/hastla007/wp-formmaster/blob/main/konzept.md) (§6/6.1/6.2: Admin, Settings, SEO), [Workflow Captain](https://github.com/hastla007/wp-workflowmaster/blob/main/konzept.md) (§5, §8, §9, §15, §18: Providerverträge, Status, Rechte, Fallback und Release), [Review Captain](https://github.com/hastla007/wp-reviewmaster/blob/main/konzept.md) (§7.5, §8.9, §11, §18: Queue-/Connector-Diagnose, Privacy und SEO). Andere Suite-Repositories wurden nicht vollständig geprüft; aus nicht gelesenen Konzepten wird keine Vollständigkeit abgeleitet.

| Übertragbares Muster | Course-Captain-Konsequenz | Abgrenzung | Operative Tasks |
|---|---|---|---|
| Form/Review: SEO-Zuständigkeit und Yoast-Kompatibilität | Öffentliche Katalog-/Kursseiten indexierbar nach Rechteklasse; private Lektionen, Prüfungen, Lernstände, Grants und Medien bleiben privat; keine doppelte Canonical-/Schema-/Sitemap-Ausgabe | Kein eigenes SEO-Plugin, kein blindes `noindex` als Zugriffsschutz | R0-33, R1-37 |
| Workflow: Event-/Action-Provider mit Versionen | Course als unabhängiger Event-Provider; versionierte Metadaten, Rechte, Fehler und Retry-Verträge | Workflow Captain nicht für nativen Course-Abschluss nötig | R0-34, R3-16, R3-17 |
| Workflow/Review: Queue und Correlation-ID | Betreiber sieht wartende/fehlgeschlagene Grants, Imports, Events; sichere Retry/Reconcile-Aktionen mit Audit | Kein Zugriff auf fremde Plugin-Datenbanken/Logs | R0-34, R1-38, R3-16 |
| Form/Workflow: Setup und WordPress.org-Release | Installations-, Demo-, Readme-, Übersetzungs-, Lizenz-, Update-/Rollback- und Support-Gates | Keine fremden Formular-/Workflow-Vorlagen übernehmen | R0-35, R1-39 |
| Review/Workflow: Datenschutz und Provider-Fallback | Bei Plugin-/Vertragsausfall kein Fatal und keine doppelte Geschäftsaktion; Diagnosen ohne Secrets/PII | Keine Pflicht zu WooCommerce/Workflow/Review für R1 Free | R3-17 |

**Prioritätsbeschluss:** Zuerst die Quellkonzept-Deltas und die öffentliche/private SEO-Grenze in R0 festlegen; anschließend Free-Betrieb und Installationsfähigkeit in R1 absichern; Providerstatus/Fallback erst mit R3-Partnern implementieren. Bereits vorhandene Architektur-/Security-Tasks gelten weiter, die neuen Tasks ergänzen nur bisher nicht explizit prüfbare Ergebnisse. **Kein Code geändert, keine Tests ausgeführt.**

## 45. Zweiter Suite-Konzeptabgleich – Pass, Voucher, Affiliate, License und Captain Pro

**Geprüfte Quellunterlagen (Dokumente, keine Code-Audit-Bestätigung):**

- [Pass Captain – Betrieb](https://github.com/hastla007/wp-passmaster/blob/main/docs/OPERATIONS.md) und [Tests](https://github.com/hastla007/wp-passmaster/blob/main/docs/TESTING.md): Schema-Upgrades mit Lock, Admin/Cron/CLI-Trigger, Restore-Runbook, echte DB-Parallelität und Lastprofile.
- [Voucher Captain – API](https://github.com/hastla007/wp-couponmaster/blob/main/API.md): in-process PHP v1, Reserve/Commit/Release, idempotente Einlösung, stabile Fehlercodes und Konfliktfälle.
- [Affiliate Captain – Vertrag](https://github.com/hastla007/wpaffiliatemaster/blob/main/docs/CONTRACT.md) und [Import/Export](https://github.com/hastla007/wpaffiliatemaster/blob/main/docs/IMPORT-EXPORT.md): versionierte externe Verkaufsquellen, keine doppelte Provision, Dry-run, Batch-/Resume-Import und Fehlerberichte.
- [License Captain – Client-Integration](https://github.com/hastla007/wp-license/blob/main/docs/CLIENT-INTEGRATION.md) und [Release-Readiness](https://github.com/hastla007/wp-license/blob/main/docs/RELEASE-READINESS.md): kein Lizenzclient im Free-Kern, getrennte Add-on-Updates, reale Release-Evidenz statt Checkbox ohne Test.
- [Captain Pro – Architektur](https://github.com/hastla007/captain-pro/blob/main/docs/ARCHITECTURE.md): separate Add-ons, isolierte Clientbibliotheken/Namespaces, Free-Datenbestand bleibt bei Lizenzende unangetastet.

| Quellmuster | Relevanz für Course Captain | Umsetzungstasks |
|---|---|---|
| Pass: Migration/Lock und echte DB-Race-Tests | Course-Schema, Grants, Seats, Prüfungsversuche und Fortschritt dürfen bei konkurrierenden Requests nicht korrumpieren | R0-37, R1-40, R1-42 |
| Affiliate: Dry-run, Batch, Resume und Fehlerreport | Kurs-/Fragenimport großer Pakete mit verständlichem Preview, Dedupe, Wiederaufnahme und Rollback | R0-38, R1-41 |
| Voucher/Affiliate: öffentliche versionierte Geschäftsverträge | Kursfreischaltung von Kauf, Einlösung und Provision getrennt; Refund und Replay ohne doppelte Buchung | R0-40, R3-19 |
| License/Captain Pro: strikt isolierte Lizenzclient-Schicht | Free enthält keine Lizenz-/Updater-Abhängigkeit; Add-on mit separatem Build und sicheren Downgrade-/Ausfallregeln | R0-39, R3-18 |
| License: Release-Evidenz | Bereits bestehende Course-Release-Gates müssen echte Tests, Installations- und Upgradeprotokolle verlangen | R0-36, bestehende R0-35/R1-39 |

**Bewusst nicht übernommen:** Ticket-Scanner, Saalpläne, Gutschein-Guthabenbuchhaltung, Affiliate-Provisionsledger oder Lizenzserver-Verkauf. Diese gehören zu ihren jeweiligen Fachplugins. Die Quellverträge sind bei R3 erneut gegen tatsächliche Plugin-Versionen und APIs zu prüfen; Dokumentation allein bestätigt keine lauffähige Integration.
