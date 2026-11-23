# R0 – Offene Entscheidungen für den Auftraggeber

**Stand:** 16.11.2026 · **Zweck:** Alle Fragen, die aus den R0-Arbeiten entstanden sind und die nicht technisch, sondern vom Auftraggeber zu entscheiden sind. Doppelte Fragen aus verschiedenen Inventurteilen sind zusammengeführt. Jede Zeile nennt eine **Empfehlung**.

**Stand 16.11.2026: Alle Entscheidungen sind getroffen** – teils durch den Auftraggeber (Beschlussprotokoll), teils durch Claude im Auftrag (Abschnitt „Von Claude entschieden“). Die Abschnitte 1–41 darunter sind die ursprünglichen Fragen mit Empfehlung.

~~**So antworten:**~~ z. B. „Alle Empfehlungen annehmen außer E-12 (Option B) und E-30 (nein)“. Erst danach werden die betroffenen R0-Dokumente von „Vorschlag“ auf „beschlossen“ gesetzt und die Tasks abgehakt. **R0-GATE und damit jede Implementierung (R1) bleiben bis dahin gesperrt.**

Quellen: `R0-01-baseline.md`, `R0-02-inventar/A…E`, `R0-32-suite-traceability.md`, `R0-36-suite-vertragsdeltas.md`. Die Kürzel in der Spalte „Herkunft“ verweisen auf die Abschnitte „Entscheidungsbedarf“ dort.


## Beschlussprotokoll (Auftraggeber, 16.11.2026)

| ID | Beschluss | Abweichung von Empfehlung |
|---|---|---|
| E-01 | **Nein – Clean Room.** Kein Code aus learnboating wird übernommen; nur Verhalten wird nach Spezifikation neu implementiert. „reuse/abstract“ in der Inventur bedeutet ab jetzt „Verhalten nachbauen“, nicht „Code kopieren“ | **ja** |
| E-02 | Nur synthetische, neutrale Testpakete | nein |
| E-03 | LB-Import höchstens als separates Migrationswerkzeug | nein |
| E-04 | Keine Rechtstext-Vorlagen, nur Datenschutz-Bausteine | nein |
| E-05 | Keine App-API in R1, nur Web-API mit Cookie+Nonce | nein |
| E-06 | Einheitlicher Fehlercode-Katalog ab R1 | nein |
| E-07 | Offiziell nur Single-Site | nein |
| E-08 | InnoDB und JSON-Funktionen Pflicht | nein |
| E-09 | Free über WordPress.org, Pro über License Captain | nein |
| E-10 | **Website-Härtung im Kern** (Login-Limit, XML-RPC, Security-Header). Folge: E-46 fail-closed gilt auch für Login/Reset im Free-Kern; Konfliktmatrix mit Security-Plugins (R0-20) wird Pflicht; Härtung braucht Schalter und Erkennung vorhandener Security-Plugins | **ja** |
| E-11 | PHPUnit + WP-Testsuite und Playwright | nein |
| E-12 | **Plattformmatrix neu festlegen**, LB-Werte nicht als Startwert (R0-20/E-195 bilden die eigenständige Festlegung) | **ja** |
| E-13 | CI-Deploy zu WordPress.org nur bei grüner CI, Tag auf main und manueller Freigabe | nein |
| E-14 | Einfacher Drip in Free, Kampagnen/Gruppen-Drip in Pro | nein |
| E-15 | Schnupperbereich in R1 Free | nein |
| E-16 | FAQ/Tipps-Mechanik R1 Free, Starttexte R2; öffentliche Website-FAQ bleibt Theme/Drittplugin | nein |
| E-17 | Gruppen in Free, auch ohne Kauf; Besitzer verwaltet; delegierte Verwaltung/Reporting Pro | nein |
| E-18 | Rechtestatus-Sperre in R1 Free, nur für importierte/fremde Inhalte | nein |
| E-19 | Admin-Protokollansicht in R1 Free | nein |
| E-20 | **Lösungserklärungen sind ein eigenes Pro-Feature.** Free zeigt nur richtig/falsch und die richtige Antwort nach E-147; Erklärungstexte werden nur mit Pro ausgeliefert. Folgen: R0-08 §4.5, R0-11 Matrix, E-147 und Entitlement-Zuschnitt E-95 anpassen; Free-Kern speichert Erklärungstexte trotzdem (Import/Export, kein Datenverlust bei Pro-Ende) | **ja** |
| E-21 | Ein Karteikartentyp mit zwei Modi, R1 Free | nein |
| E-22 | **Neutraler Fragetyp „Zahl mit Toleranz“ in R1 Free** (Ersatz für den Navigationstrainer, ohne Code aus NavigationCheck; Fallstudie nicht). Folge: R0-08 §3 Fragetypen, R0-22 Validierung (Toleranz, Einheit), R0-04/E-69 anpassen | **ja** |
| E-23 | Prüfungsmodus „offiziell“ gestrichen; Regel freigegeben/Entwurf + Kennzeichen „verkürzt“ | nein |
| E-24 | Erinnerung vor Ablauf in Pro, nur zahlende Käufer | nein |
| E-25 | **Kursbeziehung „enthält“ (Bundle) und Mitgliederrabatt werden wie in LB übernommen** (Phase siehe E-25a) | **ja** |
| E-26 | **Abhängige Zusatz-Grants in R1** (Zusatzmodul läuft so lange wie der Basiskurs). Folge: R0-09 Grant-Automat um Abhängigkeitskette erweitern | **ja** |
| E-27 | **Zugang je Modul** statt je Kurs mit Scopes. Folge: Grant-Schlüssel in R0-06/R0-09 bekommt `module_id` (NULL = ganzer Kurs), effektiver Zugang und Freischaltprüfung (R0-08 §1) arbeiten je Modul, Gruppenplätze und Woo-Mapping je Modul möglich | **ja** |
| E-28 | Einladungslinks nach LB-Regeln übertragbar, plus Option „nur eingeladene Adresse“ | nein |
| E-25a | Bundle (ein Grant schaltet mehrere Kurse frei) in R1 Free; Mitgliederrabatt im WooCommerce-Adapter R3.1 | nein |
| E-29 | Kein Wiederaufleben nach Refund; neuer Kauf = neue Grant-Revision mit Audit | nein |
| E-30 | R3.1 mit Kontopflicht; Claim-Token später (bewusste Abweichung von CORE-SPEC-002) | nein |
| E-31 | **Gutscheinziel kann beides sein:** neutrales Course-Captain-Zugangsangebot **oder** WooCommerce-Produkt. Folge: Voucher-Connector (R3.2) unterstützt zwei Zieltypen; R0-40 und R3-04 müssen beide Wege spezifizieren | **ja** (erweitert) |
| E-32 | Keine Geld-zurück-Garantie; Kern sendet nur ein neutrales Nutzungsereignis | nein |
| E-33 | **Deutsche Shop-Rechtspflichten und PayPal/Klarna-Webhooks werden wie in LB übernommen** (Widerrufsbutton, Bestellknopf, Preisangaben, Rechtstexte in Mails, OSS-Schwelle, Zahlungs-Dispute). Wegen E-01 Clean Room als Neuimplementierung nach Verhalten; Ort: WooCommerce-Adapter R3.1 (Pro, E-53). Folge: R0-04 BS-Z und R3-02/R3-03 erweitern; juristische Prüfung durch den Betreiber | **ja** |
| E-34 | **Woo-Produkte werden automatisch aus Kursen/Modulen angelegt und gepflegt** (R3.1). Folge: Produkt-Sync mit Konfliktregeln (manuelle Änderungen am Produkt, Löschung, Preis) in R3-02 spezifizieren | **ja** |
| E-35 | Gutschein-Storno entzieht nur den Voucher-Grant; Lernhistorie bleibt | nein |
| E-36 | Ein Gutscheincode pro Gruppenplatz | nein |
| E-37 | Course meldet in v1 keine Verkäufe an Affiliate Captain | nein |
| E-38 | **Öffentliche Course-Events, Read-API und Connector-Registry sind Pro.** Free behält nur die interne Outbox für eigene Zwecke (Mails, Audit). Folge: R1-43 (E-182) wird auf „interne Outbox“ reduziert; Registry, öffentliche Events und Pull-API wandern in R3/Pro; E-119/E-120 gelten für Pro; R0-19 §5 und R0-11 anpassen | **ja** |
| E-39 | **Schreibende Workflow-Aktionen (Zugang anlegen/entziehen) schon in v1**, über die Grant-Quelle `workflow` mit Idempotenz und Audit. Achtung: Das Workflow-Captain-Konzept schließt das für das LMS-Muster aus (R0-32) – Abstimmung mit Workflow Captain nötig; wegen E-38 Teil von Pro/R3 | **ja** |
| E-40 | Connectoren zu Form/Workflow/Review in Course Pro, erst wenn die Partner-Registries existieren | nein |
| E-41 | Review Captain prüft die Kommunikationsberechtigung; Course liefert nur den Empfänger | nein |
| E-42 | Produkt `course-captain-pro` bei License Captain vor R3-18 beauftragen (nach Neuzuschnitt E-95); Zugriff auf captain-pro für die Prüfung nötig | nein |
| E-43 | Automatische Kontolöschung nach Löschantrag standardmäßig aus, Opt-in | nein |
| E-44 | LB-Aufbewahrungsfristen als änderbare Defaults; juristische Bestätigung durch den Betreiber | nein |
| E-45 | **Externe Medien werden ohne Klick-Einwilligung direkt geladen.** Hinweis: Das widerspricht R0-10 §5, R0-17 (Consent) und R0-31 (Privacy by Default) und ist datenschutzrechtlich riskant (Übermittlung an YouTube/Vimeo ohne Einwilligung); WordPress.org verlangt die Offenlegung externer Dienste in der readme. Rückfrage dazu in E-45a | **ja** |
| E-46 | Fail-closed für Login, Reset, Registrierung, Claim, Einlösen, Einladungen, Datenschutzanträge; Lernen fail-open; bekannte Geräte dürfen weiter einloggen | nein |
| E-47 | Hybrid: Inhalte als CPT, Massendaten/Transaktionen in eigenen Tabellen | nein |
| E-45a | Einstellung `external_media_mode`, **Default „immer laden“**, umschaltbar auf „Klick zum Laden“; Hinweis im Setup-Assistenten und Offenlegung der externen Dienste in der readme | ja (Präzisierung zu E-45) |
| E-48 | Harte Fremdschlüssel nur zwischen cc-Tabellen | nein |
| E-49 | Netzwerkaktivierung in R1 blockiert | nein |
| E-50 | Fragenrevisionen werden erst beim Versuchsstart fixiert | nein |
| E-51 | Lernstände bis Kontolöschung, Versuche/Zertifikate 10 Jahre, Rohantworten 2 Jahre nach Kursende (änderbar, juristische Bestätigung durch Betreiber) | nein |
| E-52 | Lastannahmen bestätigt: 10.000 Lernende, 100 Kurse, 100.000 Fragen, 500 gleichzeitige Prüfungen | nein |
| E-53 | WooCommerce- und Voucher-Connectoren sind Pro | nein |
| E-54 | Jede R2-Zuordnung bekommt eine R2-Task oder wandert in den R4-Pool | nein |
| E-55 | Abweichungen vom LB-Verhalten gibt der Auftraggeber schriftlich über die Abweichungsliste in R0-05 frei | nein |
| E-56 | Referenzlauf des LB-Plugins (nur Ausführen, kein Code) für Fälle mit Rechenlogik | nein |
| E-57 | durch E-195 ersetzt, wird dort entschieden | – |
| E-58 | CI: MariaDB 10.11 und 12.3 (bzw. 11.8 LTS), MySQL 8.4; MySQL 8.0/MariaDB 10.6 best effort | nein |
| E-59 | Doku nennt Shared-Hosting-Klasse „bis 50 gleichzeitige Prüfungen“ (wird in R1-22 gemessen) | nein |
| E-60 | Zeitbudgets sind Release-Gate für R1-GATE | nein |
| E-61 | Letzte zwei Versionen von Chrome, Edge, Firefox, Safari + Firefox ESR, iOS Safari, Android Chrome | nein |
| E-62 | „eigen“ aus Fremdpaket wird als „lizenziert“ gespeichert | nein |
| E-63 | Reimport: lokal gewinnt, Wahl je Objekt im Dry-run | nein |
| E-55a | Alle 25 Abweichungen vom LB-Verhalten (R0-GATE-protokoll §6) schriftlich freigegeben | nein |
| E-326 | Kursabschluss/-zertifikat nur mit allen Pflichtmodulen; zusätzlich optionaler Modul-Abschlussnachweis | nein |
| E-327 | Clean Room als Spec-Barriere: R1-Implementierung nur mit docs/r0, ohne LB-Klon; LB-Klon der Spezifikations-Session wird gelöscht; Referenzlauf durch getrennte Prüfrolle | nein |
| E-328 | Fixture-Bau und Referenzlauf als Eingangskriterium von R1-18 statt Gate A | nein |
| E-329 | Juristische Bestätigung der Fristen und von „externe Medien immer laden“ ist Eingangskriterium von R1-GATE, nicht von Gate A | nein |
| E-330 | **R0-GATE (Gate A) freigegeben** am 16.11.2026 mit den Auflagen im Gate-Protokoll | – |
| E-331 (F-1) | Prüfungsteile können mehrere Blueprint-Module umfassen (Teilbestehensregel über Modulgruppen); wird nachgebaut | nein |
| E-332 (F-2) | Kostenloser Teilumfang mit eigener Grenze je Art (Fragen, Lektionen, Prüfungsbögen, Dateien); wird nachgebaut | nein |
| E-333 (F-6/F-7) | Freigegebene Abweichungen: Join-Token nicht frei setzbar (nur Hash, E-112); paralleler Prüfungsstart 409 `ccap_exam_start_busy`; fremder Kurs 404 statt 403 | nein |

### Von Claude entschieden (Auftrag des Auftraggebers vom 16.11.2026: „überlege was am besten ist“)

Alle folgenden Punkte sind technische, gestalterische oder Prozessentscheidungen. Jede Empfehlung wurde gegen die Beschlüsse oben geprüft; wo ein Beschluss sie berührt, steht „angepasst“. Der Auftraggeber kann jeden Punkt jederzeit ändern.

| ID | Beschluss | Entschieden von |
|---|---|---|
| E-64 | Empfehlung übernommen: **Nein**; das Feld wird im Format nur reserviert | Claude |
| E-65 | Empfehlung übernommen: **Standardmäßig geschützt über PHP ausliefern** (LB: öffentlich in `uploads/`, nur die Frage gesperrt) | Claude |
| E-66 | Empfehlung übernommen: **Nur per Opt-in** mit Hinweis | Claude |
| E-67 | Empfehlung übernommen: **1:1 in R1**, aber mit eigener `activity_uid`, damit 1:n später ohne Fortschrittsmigration möglich ist | Claude |
| E-68 | Empfehlung übernommen: **Ja** (`cc_exam_session`, Modus `quiz`); unbewertete Quizze nutzen die Lernmodus-Engine | Claude |
| E-70 | Empfehlung übernommen: **Nur `manual_confirm` durch den Lernenden**; nachweisbasierte Abschlüsse nie; Reset durch die Kursverwaltung mit Audit | Claude |
| E-71 | Empfehlung übernommen: **Ja, wenn ein Limit gesetzt ist**; sonst könnte man Fragen durch Abbruch sichten | Claude |
| E-72 | Empfehlung übernommen: **Einstellung** „automatisch abgeben nach x Tagen Inaktivität“, standardmäßig aus | Claude |
| E-73 | Empfehlung übernommen: **Nein**: abgeleitet aus Versuchen und Zertifikaten plus `competency_validity_days`; Tabelle erst mit manuellen bzw. externen Nachweisen | Claude |
| E-74 | Empfehlung übernommen: **Im Datenvertrag ab R1** (`evaluation_no`, Revision mit Audit), Oberfläche erst mit dem Gradebook (Pro); nie automatisch | Claude |
| E-75 | Empfehlung übernommen: **`evaluated` mit `end_reason`** (LB-Parität); Konzept 28.3 wird redaktionell angepasst | Claude |
| E-76 | Empfehlung übernommen: **`manual_confirm`** als Standard; „≥ 90 % angesehen“ als Option mit Hinweis auf Selbstauskunft des Players | Claude |
| E-77 | Empfehlung übernommen: **`all_or_nothing` und `proportional_penalty`**; `per_option` erst nach didaktischem Review | Claude |
| E-78 | Empfehlung übernommen: **Ja** (Delta zu R0-06) | Claude |
| E-79 | Empfehlung übernommen: **Ja**; LB hat keine Konto- oder Lernsperre | Claude |
| E-80 | Empfehlung übernommen: **In R1 site-weit**, kursbezogene Kursmanager erst später | Claude |
| E-81 | Empfehlung übernommen: **Minimieren**: Name bzw. Anzeigename, E-Mail-Adresse nur maskiert oder per eigener Cap | Claude |
| E-82 | Empfehlung übernommen: **Ja, mit Audit-Eintrag** | Claude |
| E-83 | Empfehlung übernommen: **Nur `administrator`**; `editor` und `shop_manager` nicht automatisch | Claude |
| E-84 | Empfehlung übernommen: **Nein, er darf nur einschränken.** Zugang entsteht ausschließlich über einen Grant | Claude |
| E-85 | Empfehlung übernommen: **Ja**, bis ein Admin bestätigt | Claude |
| E-86 | Empfehlung übernommen: **`catch`** (zurückhalten und anzeigen) | Claude |
| E-87 | Empfehlung übernommen: **Konstante in `wp-config.php`**, sonst verschlüsselt in der DB mit `COURSE_CAPTAIN_SECRET_KEY` | Claude |
| E-88 | Empfehlung übernommen: **Admin-Liste**; Standardaktion „verwerfen“ | Claude |
| E-89 | Empfehlung übernommen: **Setting plus Eingabe des Site-Namens**, mit Audit | Claude |
| E-90 | Empfehlung übernommen: **Ja**, aber nur, wenn das Release keinen Contract-Migrationsschritt enthält | Claude |
| E-91 | Empfehlung übernommen: **RPO 24 h, RTO 4 h** als Empfehlung, nicht als Zusage | Claude |
| E-92 | Empfehlung übernommen: **Abweichend vom Agentenvorschlag („nur dokumentieren“): Lösch-Tombstones außerhalb des normalen Datenbestands führen und nach jedem Restore automatisch erneut anwenden.** „Nur dokumentieren“ reicht für Art. 17 DSGVO voraussichtlich nicht; rechtliche Bestätigung durch den Betreiber | Claude |
| E-93 | Empfehlung übernommen: **Ja, Grants und Widerrufe laufen weiter** (sonst zahlen Kunden ohne Zugang oder behalten ihn trotz Erstattung). Nur neue Zuordnungen und Konfigurationsänderungen sind gesperrt | Claude |
| E-94 | Empfehlung übernommen: **Lizenzende:** Konfiguration wirkt eingefroren weiter. **Add-on deaktiviert:** Rückfall auf Free-Verhalten, Daten bleiben erhalten | Claude |
| E-95 | Entitlement-Schlüssel neu zuschneiden; neu `course.explanations` (wegen E-20), `course.events`/`course.connectors` (wegen E-38); `drip_content`, `certificates`, `groups` entfallen als Free-Funktionen | Claude: angepasst an Beschlüsse |
| E-96 | Empfehlung übernommen: **HTML- und Druckansicht in Free**; serverseitiges PDF und Designer erst in Pro | Claude |
| E-97 | Empfehlung übernommen: **Standardmäßig aus**, je Nachweis vom Lernenden freizugeben | Claude |
| E-98 | Berichte Basic in Free: Fortschritt je Lernendem, Bestehensquote je Prüfung, CSV-Export | Claude: angepasst an Beschlüsse |
| E-99 | Empfehlung übernommen: **`cc_trainer` in R1 anlegen** (für Ergebnis-Einsicht und Support gebraucht, R0-07); Tutor-Bewertungsfunktionen erst mit dem Gradebook (Pro). Damit ist der Widerspruch zwischen R0-07 und R0-11 aufgelöst | Claude |
| E-100 | Empfehlung übernommen: **Nein**; höchstens ein Hinweis auf der Seite „Erweiterungen“ (WordPress.org-Richtlinie) | Claude |
| E-101 | Empfehlung übernommen: **Free R1** | Claude |
| E-102 | Empfehlung übernommen: **Leeres Kursgerüst** in R1; Demo-Kurse mit Inhalt in R2-03 | Claude |
| E-103 | Empfehlung übernommen: **Vorwärtsrollen mit Besitzstand:** Alle sehen die neue Revision, Erreichtes bleibt. Harter Bestandsschutz nur über eine neue Kursausgabe (eigener Kurs); Fortschrittsanrechnung zwischen Ausgaben erst R2/Pro | Claude |
| E-104 | Empfehlung übernommen: **Nicht öffentliche Staging-Kopie** für Import und Editor, Übernahme in einer Transaktion je Kurs. Folge: Änderungen an veröffentlichten Kursen werden erst mit „Kurs veröffentlichen“ sichtbar | Claude |
| E-105 | Empfehlung übernommen: **Ja**, beim Veröffentlichen, nur für Lernende ohne Kursabschluss, mit Audit und Hinweis | Claude |
| E-106 | Empfehlung übernommen: **Unverändert** (Parität), plus ausdrückliche Aktion „Lernstand dieser Frage zurücksetzen“ mit Audit; nie automatisch | Claude |
| E-107 | Empfehlung übernommen: **Ja als Standard**; der Autor kann beim Veröffentlichen „Versuchszähler zurücksetzen“ wählen (Audit) | Claude |
| E-108 | Empfehlung übernommen: **Zweistufig:** erst „Einschreibung geschlossen“ (bestehende Lernende lernen bis zum Grant-Ende weiter), dann „archiviert“. Sofortarchivierung nur mit Bestätigung und Anzahl der Betroffenen. Ein Abbruch wegen Rückzug zählt **nicht** gegen `max_attempts` (Ausnahme zu E-71) | Claude |
| E-109 | Empfehlung übernommen: **Frage bei der Auswertung neutralisieren** (aus Punkten und Maximum, Grund im Ergebnis) | Claude |
| E-110 | Empfehlung übernommen: **Nicht in R1** (alte Template-Version wird weiter gerendert); Sammel-Neuausstellung als Pro-Kandidat | Claude |
| E-111 | Empfehlung übernommen: **Automatisch** beim Umstellen; Zertifikat nach Kurseinstellung | Claude |
| E-112 | Empfehlung übernommen: **Ja** (LB speichert Tokens im Klartext) | Claude |
| E-113 | Empfehlung übernommen: **Wird gewertet**; automatisches Zertifikat nur, wenn bei Start **und** Abgabe ein Grant aktiv war | Claude |
| E-114 | Empfehlung übernommen: **Nein**; nur die Support-Aktion „Zugang übertragen“ mit Audit | Claude |
| E-115 | Empfehlung übernommen: **Keine Grenze** | Claude |
| E-116 | Empfehlung übernommen: **90 Tage**, danach löschen | Claude |
| E-117 | Empfehlung übernommen: **Neue Grant-Revision** statt stillem Umhängen wie in LB | Claude |
| E-118 | Empfehlung übernommen: **Mindestens 2 Minor-Releases und 6 Monate**; ein alter Major-Namespace bleibt 12 Monate parallel. Ausnahme bei Sicherheitsproblemen, mit Changelog | Claude |
| E-119 | Event-Speicherung ohne Abonnent 30 Tage – gilt wegen E-38 nur mit Pro; Free speichert nur die interne Outbox | Claude: angepasst an Beschlüsse |
| E-120 | Freigabe personenbezogener Event-Klassen je Connector – Pro (E-38) | Claude: angepasst an Beschlüsse |
| E-121 | Empfehlung übernommen: **Englisch (en_US)**, Deutsch als erste Übersetzung. Voraussetzung für translate.wordpress.org; LB nutzt Deutsch | Claude |
| E-122 | Empfehlung übernommen: **Ja:** Zeitfaktor 100–400 % oder ohne Limit je Lernendem, nur durch Berechtigte, ohne Begründungstext, im Snapshot eingefroren, in Ergebnis und Nachweis nicht sichtbar. Datenschutzbewertung (Art. 9) in R0-17 | Claude |
| E-123 | Empfehlung übernommen: **`strict`** (Standard für Nachweise) und **`practice`** (von Lernenden abschaltbar und verlängerbar, nicht nachweisfähig) | Claude |
| E-124 | Empfehlung übernommen: **Einstellung „Profilsprache“**, standardmäßig an bei mehr als einer installierten Sprache; Fristen mit Zonenkennung, keine eigene Nutzerzeitzone in R1 | Claude |
| E-125 | Empfehlung übernommen: **Ja**, WCAG-2.2-AA-Tabelle in der Doku. Keine Vorlage für die Barrierefreiheitserklärung (vgl. E-04), nur ein neutraler Hinweis, welche Angaben der Betreiber braucht | Claude |
| E-126 | Empfehlung übernommen: **NVDA und VoiceOver (macOS, iOS)**; TalkBack best effort, JAWS nicht zugesichert | Claude |
| E-127 | Empfehlung übernommen: **Ja**, getaggtes PDF (an PDF/UA orientiert) als Pro-Abnahmekriterium | Claude |
| E-128 | Empfehlung übernommen: **Nein**; Warnungen werden bestätigt (Fingerabdruck + Audit), Blocker nicht | Claude |
| E-129 | Empfehlung übernommen: **Nein.** Übungsprüfungen sind veröffentlichte Regeln mit `purpose=practice`. R0-08 §4.1 wird entsprechend angepasst | Claude |
| E-130 | Empfehlung übernommen: **Blocker** nur bei Nachweis- und Pflichtprüfungen, sonst Warnung | Claude |
| E-131 | Empfehlung übernommen: **Alt-Text fehlt (ohne `decorative`) = Blocker** wie R0-15; fehlende Untertitel = Warnung, per Site-Einstellung zum Blocker machbar. Damit ist der Widerspruch zwischen R0-15 und R0-22 aufgelöst | Claude |
| E-132 | Empfehlung übernommen: **Einstufig** (Autor reicht ein, Kursmanager veröffentlicht); mehrstufige Freigaben in Pro | Claude |
| E-133 | Empfehlung übernommen: **Nein** | Claude |
| E-134 | Empfehlung übernommen: **Durchspielbar ohne Speicherung** | Claude |
| E-135 | Empfehlung übernommen: **Nur melden**, nie automatisch zurückziehen | Claude |
| E-136 | Empfehlung übernommen: **Mail** bei Freischaltung, Übertragung (nur an das Ziel), technisch verworfenem Versuch, Annullierung, Zertifikatskorrektur/-widerruf; **keine Mail** bei Widerruf, Sperre, Aussetzung, Reset, Reconcile; im Dialog abwählbar | Claude |
| E-137 | Empfehlung übernommen: **Zählt nicht** (`end_reason = support_void`, zweite Ausnahme zu E-71 neben E-108); plus Aktion „Zusatzversuch“ für belegte Störungen | Claude |
| E-138 | Empfehlung übernommen: **Ja**, über ein Reset-Journal (30 Tage), nur solange kein neuer Fortschritt entstand | Claude |
| E-139 | Empfehlung übernommen: **Append-only plus Hash-Kette** mit täglicher Prüfung in R1; externer Sink (syslog/Webhook) als Pro-Kandidat | Claude |
| E-140 | Empfehlung übernommen: **Free**: gefilterter CSV/JSON-Export für Admins mit Pflichtbegründung. Prüfer und Betroffenenanfragen brauchen ihn auch ohne Pro. R0-07 §4.9 wird angepasst | Claude |
| E-141 | Empfehlung übernommen: **Nein**; stattdessen Ansicht des effektiven Zugangs mit Begründung; Lesevorschau ohne fremde Daten als spätere Option | Claude |
| E-142 | Empfehlung übernommen: **Kein Konto**, nur das Diagnosepaket (Domain standardmäßig gehasht); sonst würde der Hersteller Auftragsverarbeiter | Claude |
| E-143 | Empfehlung übernommen: **Debug-Fenster höchstens 24 h** (Standard 2 h), Dateien 7 Tage; Fehlertabelle und Job-Fehler 30 Tage | Claude |
| E-144 | Empfehlung übernommen: **Eine Rolle `cc_support`** (lesen + Lernsperre); Grant-Korrekturen durch den Kursmanager oder per gezielter Cap-Zuweisung | Claude |
| E-145 | Empfehlung übernommen: **Nur als Rolle** („Support“, „Administrator“, „System“), keine Namen der Mitarbeitenden | Claude |
| E-146 | Empfehlung übernommen: **Kennzeichen `exam_only`** je Frage bzw. Pool in Free, standardmäßig aus; Hinweis im Editor | Claude |
| E-147 | Nach Abgabe zeigt Free richtig/falsch und (nach Einstellung) die richtige Antwort; Erklärungstexte nur mit Pro (E-20) | Claude: angepasst an Beschlüsse |
| E-148 | Empfehlung übernommen: **Nein**: unzuverlässig und Verhaltensüberwachung ohne echten Integritätsgewinn | Claude |
| E-149 | Empfehlung übernommen: **Letzter Stand gewinnt** plus sichtbarer Hinweis „in anderem Fenster geöffnet“ | Claude |
| E-150 | Empfehlung übernommen: **Eraser löscht standardmäßig.** Einstellung „Nachweise trotz Löschantrag pseudonymisiert aufbewahren“ (Default aus, Pflichtbegründung, im Eraser-Ergebnis sichtbar). Präzisiert E-51; juristische Bestätigung durch den Betreiber | Claude |
| E-151 | Empfehlung übernommen: **Tabelle plus Journal** außerhalb des Backups (`COURSE_CAPTAIN_PRIVACY_JOURNAL_DIR`); Frist = maximales Backup-Alter + 30 Tage (Default 120 Tage); zurückgehaltene Mails 30 Tage | Claude |
| E-152 | Empfehlung übernommen: **Tabelle R0-17 §3.4 als Defaults**; fail-closed zusätzlich für Einladungsversand, Datenschutzanträge und Import/Export | Claude |
| E-153 | Empfehlung übernommen: **Doku-Vorlage plus generierte Verarbeitungsübersicht im Systemstatus**; keine fertige DSFA, keine Rechtsbewertung | Claude |
| E-154 | Empfehlung übernommen: **Optionaler Job** „Lerndaten nach x Jahren ohne Anmeldung und ohne aktiven Grant löschen“, Default aus, mit Vorschau | Claude |
| E-155 | Empfehlung übernommen: **Eigener Kurs je Sprache**, verknüpft über eine Übersetzungsgruppe; Fortschritt, Grants und Zertifikate gelten je Kurs | Claude |
| E-156 | Empfehlung übernommen: **Nein, kein Übertrag in R1**; Sammelvergabe durch den Kursmanager möglich | Claude |
| E-157 | Empfehlung übernommen: **Erlaubt** mit `lang`-Kennzeichnung und Validator-Warnung | Claude |
| E-158 | Empfehlung übernommen: **Kurssprache**, im Snapshot eingefroren | Claude |
| E-159 | Empfehlung übernommen: **Ja, schon in Free** | Claude |
| E-160 | Empfehlung übernommen: **Nein in R1**, kein `tenant_id` | Claude |
| E-163 | Empfehlung übernommen: **6 Autoren, 5+4 Lernende** (davon 4 mit Hilfstechnik), **5 Gruppenverwalter, 5 Admins**; Nachtest mit mindestens 3 neuen Personen | Claude |
| E-164 | Empfehlung übernommen: **Eine Person, die nicht an der Entwicklung beteiligt ist**; extern für die Barrierefreiheitssitzungen | Claude |
| E-165 | Empfehlung übernommen: **10 Fragen manuell, 40 per Import**; alle 50 manuell nur im Praxis-Benchmark | Claude |
| E-166 | Empfehlung übernommen: **Erfolg ≥ 80 % je Aufgabe, SUS ≥ 70**, Referenzkurs veröffentlichen im Median ≤ 90 min, Setup ≤ 10 min, Prüfung fortsetzen ≤ 1 min ohne Antwortverlust; jede Person mit Hilfstechnik löst jede kritische Aufgabe ohne sehende Hilfe | Claude |
| E-167 | Empfehlung übernommen: **Mit Einwilligung**, Löschung 6 Monate nach R1-GATE | Claude |
| E-169 | Empfehlung übernommen: **de_DE** plus eine Admin-Sitzung in en_US | Claude |
| E-170 | Empfehlung übernommen: **Ja**, gezielt für geänderte Flows vor R2-GATE und R3-GATE | Claude |
| E-171 | Empfehlung übernommen: **Nur mit dokumentierter Akzeptanz** durch den Auftraggeber, nie bei WCAG-Bezug in Lernenden-Flows; S1 muss immer behoben sein | Claude |
| E-172 | Empfehlung übernommen: **Nie automatisch**; nur nach Admin-Freigabe mit Backup-Bestätigung oder per CLI-Flag | Claude |
| E-173 | Empfehlung übernommen: **Frist = Ende des Wartungsfensters + 120 s**, höchstens 30 min Verlängerung | Claude |
| E-174 | Empfehlung übernommen: **Aufschieben**, höchstens 24 h | Claude |
| E-175 | Empfehlung übernommen: **Schreibzugriffe bleiben gesperrt**, Lesen weiter möglich; kein Notbetrieb; Admin-Hinweis mit Runbook | Claude |
| E-176 | Empfehlung übernommen: **Fehler am einzelnen Objekt isolieren**; der Rest läuft weiter, Abhängige werden mitmarkiert | Claude |
| E-177 | Empfehlung übernommen: **Hintergrund** (Action Scheduler bzw. WP-Cron) mit Fortschrittsanzeige; Browser-Polling und WP-CLI als Alternativen, mitten im Lauf wechselbar | Claude |
| E-178 | Empfehlung übernommen: **Ablageordner auf dem Server oder WP-CLI**, mit höheren Grenzwerten | Claude |
| E-179 | Empfehlung übernommen: **Zurückstellen**, die übrigen Kurse aktivieren | Claude |
| E-180 | Empfehlung übernommen: **max(100 Fehler, 2 % der Objekte)** | Claude |
| E-181 | Empfehlung übernommen: **Ja**, nach Vorprüfungen (Lease abgelaufen, Paketkopie unverändert) | Claude |
| E-182 | R1-43 wird auf **interne Outbox** (Mails, Audit, spätere Pro-Anbindung) reduziert; öffentliche Events, Read-API und Connector-Registry sind Pro (E-38) | Claude: angepasst an Beschlüsse |
| E-183 | Empfehlung übernommen: **R1-44 aufnehmen** (Domäne und Ausstellung), R1-35 bleibt Verwaltung und UI | Claude |
| E-184 | Empfehlung übernommen: **100 % der R1-Pflichttests grün**, Ausnahmen nur per E-Nummer oder freigegebener GM-Abweichung (E-55); Abzeichnung durch den Auftraggeber | Claude |
| E-185 | Partnererkennung lesend in R1 (nur Anzeige); alle Aktionen und die Registry mit Pro (E-38) | Claude: angepasst an Beschlüsse |
| E-186 | Empfehlung übernommen: **Eingangsbedingung von R3-01**, nicht von Gate A | Claude |
| E-187 | Empfehlung übernommen: **In R0-27 spezifizieren** | Claude |
| E-189 | Empfehlung übernommen: **GitHub Artifact Attestation + Sigstore keyless**, keine eigene Schlüsselverwaltung | Claude |
| E-190 | Empfehlung übernommen: **License Captain signiert beim Upload**, der Pro-Client prüft vor der Installation (abhängig vom License-Captain-Vertrag, E-42) | Claude |
| E-191 | Empfehlung übernommen: **Playwright je PR/nightly**; echtes iOS/Android/Safari **manuell vor jedem Minor-Release** | Claude |
| E-195 | PHP-Untergrenze 8.3 (ersetzt E-57); vor R1-GATE auf php.net verifizieren | Claude: angepasst an Beschlüsse |
| E-197 | Empfehlung übernommen: **Nur `cc_course` bekommt eine öffentliche URL**, Standard-Slug `kurs` (in den Einstellungen änderbar); Lernbereich `/lernen/` | Claude |
| E-198 | Empfehlung übernommen: **Nicht unterstützt**, mit Warnung im Systemstatus (Gefahr veralteter Zugriffsentscheidungen) | Claude |
| E-199 | **Entfällt** durch Beschluss E-69 (Fragetypen schon in R1, keine R2-08) | Claude: angepasst an Beschlüsse |
| E-200 | Empfehlung übernommen: **R4-Pool**; R1 importiert nur cc-package | Claude |
| E-201 | Empfehlung übernommen: **24 h**, danach `failed` mit Grund `pending_timeout`, Support-Hinweis und Event | Claude |
| E-202 | Empfehlung übernommen: **Eigenes Course-Captain-Hauptmenü**, Suite-Status als Unterseite „Integrationen“; gemeinsames Suite-Menü frühestens mit R3-16 | Claude |
| E-203 | Empfehlung übernommen: **Kein eigener Vertrag**: Mitgliedschaft ist eine Grant-Quelle über den GrantSourceAdapter, Adapter erst in R3 | Claude |
| E-205 | Empfehlung übernommen: **Slug vor R1-Start prüfen und früh einreichen**; Ersatz `course-captain-lms` | Claude |
| E-207 | Präfix `ccap_` für CPTs, Caps, Rollen, Cron, Meta und Tabellen (WordPress.org-Review, mindestens 4 Zeichen); vor R1-01 gegen aktuelle Richtlinie prüfen | Claude: angepasst an Beschlüsse |
| E-208 | Empfehlung übernommen: **Sprachpaket über translate.wordpress.org**, bis zur Freigabe zusätzlich im ZIP | Claude |
| E-209 | Empfehlung übernommen: **1.0.0** | Claude |
| E-210 | Empfehlung übernommen: **Keine** | Claude |
| E-213 | Pro-Add-on unter GPL-2.0-or-later (als WordPress-Erweiterung faktisch erforderlich); Lizenzschlüssel regelt Updates und Support | Claude: angepasst an Beschlüsse |
| E-214 | Empfehlung übernommen: **Erst ab R2-03** | Claude |
| E-215 | Empfehlung übernommen: **Kein echter Widerspruch:** Inbox und zugestellte Outbox-Einträge 90 Tage; Events ohne Abonnent 30 Tage; Benachrichtigungsprotokoll 90 Tage | Claude |
| E-216 | Empfehlung übernommen: **Pflicht:** Claim/Einladung, Sicherheits- und Kontomails, Datenschutzanträge; alle übrigen abbestellbar (Tabelle R0-27 §3.2) | Claude |
| E-217 | Empfehlung übernommen: **Vorhanden, standardmäßig aus**, ohne Kauf- oder Verlängerungslink | Claude |
| E-218 | Empfehlung übernommen: **Retry 5×** (5 min, 30 min, 2 h, 12 h); max. 10 Mails je Empfänger und 24 h; 3 Neuversendungen je Einladung; 5 Testmails je Admin und Stunde | Claude |
| E-219 | Empfehlung übernommen: **Optionale Felder Name/Adresse**, leer = WordPress-Standard | Claude |
| E-220 | Empfehlung übernommen: **Frühere Slugs dauerhaft merken und per 301 weiterleiten**, solange keine andere Seite den alten Slug belegt | Claude |
| E-221 | Empfehlung übernommen: **Nein, nur per Filter**; die wirksamen Werte werden im Tab „Erweitert“ angezeigt | Claude |
| E-222 | Empfehlung übernommen: **Aus**, im Setup-Assistenten aktivierbar | Claude |
| E-223 | Empfehlung übernommen: **An** (LB-Parität), Download-Link an die bestätigte Adresse | Claude |
| E-224 | Empfehlung übernommen: **Erzwingen (`enforce`)**, Umschalten auf `report_only` mit Bestätigung | Claude |
| E-225 | Empfehlung übernommen: **Ab dem nächsten Versuchsstart**; laufende Versuche bleiben unverändert | Claude |
| E-226 | Empfehlung übernommen: **Ja**, nur in Entwürfen, mit Rückgängig | Claude |
| E-227 | Empfehlung übernommen: **Ja**, Änderung wird im Veröffentlichungs-Diff angezeigt | Claude |
| E-228 | Empfehlung übernommen: **Explizit übernehmen**, damit die Prüfung gleich bleibt | Claude |
| E-229 | Empfehlung übernommen: **Nicht in R1**: Prüfungseinstellungen nur global und je Prüfungsregel | Claude |
| E-230 | Empfehlung übernommen: **Ja** | Claude |
| E-231 | Empfehlung übernommen: **Nur ein Gruppenfilter im Basisbericht** in Free; eigene Gruppenberichte in Pro | Claude |
| E-233 | Empfehlung übernommen: **Nur Einladungen erzeugen**, keine Konten | Claude |
| E-234 | Empfehlung übernommen: **Nur innerhalb derselben Stammgruppe** | Claude |
| E-235 | Empfehlung übernommen: **Layout-Revision**; der alte Link bleibt gültig und zeigt die neue Fassung | Claude |
| E-236 | Empfehlung übernommen: **Nein**, weder Initialen noch Ergebnis (präzisiert E-97) | Claude |
| E-237 | Empfehlung übernommen: **Besitzer und Admins** | Claude |
| E-238 | Empfehlung übernommen: **Kein externer Dienst.** Findet der Technik-Spike keine Bibliothek für getaggte PDFs, liefert Pro kein PDF (HTML/Druck bleibt) | Claude |
| E-239 | Empfehlung übernommen: **Neue Task R1-45 „Härtungsmodul“ in Free** | Claude |
| E-240 | Härtung: zunächst aus, bis der Admin im Assistenten bestätigt (E-260); danach je Funktion `auto` (zieht sich bei erkanntem Security-Plugin zurück) | Claude: angepasst an Beschlüsse |
| E-241 | Empfehlung übernommen: **Registrierungslimit und Geräte-Cookie ja**; Schutz gegen User-Enumeration nur als Opt-in | Claude |
| E-242 | Empfehlung übernommen: **Konstante `COURSE_CAPTAIN_HARDENING` in wp-config.php** schaltet ab; bekannte Geräte (Cookie) dürfen weiter | Claude |
| E-243 | Empfehlung übernommen: **Beendete Grants 10 Jahre, entfernte Plätze 365 Tage, geschlossene Gruppen 3 Jahre, aufgehobene Lernsperren 30 Tage, Exportdateien 24 h** | Claude |
| E-244 | Empfehlung übernommen: **Späterer von: letzter aktiver Grant endet oder letzte Aktivität im Kurs** | Claude |
| E-245 | Empfehlung übernommen: **Nur mit Pflichtbegründung und Audit** | Claude |
| E-246 | Consent-Kategorie `marketing` – wirkt nur, wenn der Betreiber nach E-45a auf „Klick zum Laden“ umstellt | Claude: angepasst an Beschlüsse |
| E-247 | Empfehlung übernommen: **Nein**; Schnupperzugang (E-15) erfordert Login | Claude |
| E-248 | Empfehlung übernommen: **Aus**, einschaltbar | Claude |
| E-249 | Empfehlung übernommen: **Einführen**, Default „gelistet“ | Claude |
| E-250 | Empfehlung übernommen: **404** | Claude |
| E-251 | Empfehlung übernommen: **Nein** | Claude |
| E-252 | Empfehlung übernommen: **Standardmäßig aus**, je Kurs einschaltbar | Claude |
| E-253 | Empfehlung übernommen: **Course-Captain-Typen über deren Filter ausschließen** plus Systemstatus-Warnung; Relevanssi getestet, andere best effort | Claude |
| E-254 | Unterseite „Integrationen“ schon in R1, nur lesend (Partnererkennung); Aktionen mit Pro/R3 | Claude: angepasst an Beschlüsse |
| E-255 | Empfehlung übernommen: **Auch `cc_support` und Kursmanager** (lesend) | Claude |
| E-256 | Empfehlung übernommen: **Erkannte Partner anzeigen**, mit Hinweis „kein Vertrag verfügbar“; nicht installierte nicht bewerben (E-100) | Claude |
| E-257 | Empfehlung übernommen: **Ja**, gebündelt höchstens einmal je 24 h | Claude |
| E-258 | Empfehlung übernommen: **Offene Zustellungen höchstens 30 Tage**, danach Dead-Letter mit Grund `retention` | Claude |
| E-259 | Empfehlung übernommen: **Erst in R3**, nur mit sichtbarem Hinweis im Admin und Abschaltmöglichkeit | Claude |
| E-260 | Empfehlung übernommen: **Alles aus, bis der Admin im Assistenten oder Tab speichert**; der Assistent schlägt „an“ vor (außer bei erkanntem Security-Plugin), danach gilt `auto` (E-240) | Claude |
| E-261 | Empfehlung übernommen: **Login-Limit, Reset-Limit, bekanntes Gerät, XML-RPC, 4 Security-Header**; übrige LB-Funktionen (Enumeration, Anwendungspasswörter) in den R4-Pool (ergänzt E-241) | Claude |
| E-262 | Empfehlung übernommen: **Eigener 11. Tab „Website-Härtung“** | Claude |
| E-263 | Empfehlung übernommen: **Login auf bekannten Geräten bleibt möglich** + Notausschalter per Konstante und WP-CLI (ergänzt E-242) | Claude |
| E-264 | Empfehlung übernommen: **Bereichsnavigation innerhalb bestehender Menüpunkte**; Protokoll unter Systemstatus | Claude |
| E-265 | Empfehlung übernommen: **Ausblenden**, solange aus und keine Gruppe existiert | Claude |
| E-266 | Empfehlung übernommen: **Erlaubt** mit Status „abgeschlossen mit Hinweisen“; Blocker bleiben sichtbar, geschützte Medien werden weiter nicht ausgeliefert | Claude |
| E-267 | Empfehlung übernommen: **Entfällt in R1**: nur „manuell“ und „Selbstanmeldung“; Gäste sehen nur den Schnupperbereich (E-15) | Claude |
| E-268 | Empfehlung übernommen: **Ja, eingefroren** wie bei E-94; neue Erklärungen lassen sich nicht mehr anlegen | Claude |
| E-269 | Empfehlung übernommen: **Ausblenden**, vorhandene Texte bleiben gespeichert | Claude |
| E-270 | Empfehlung übernommen: **Wie `expired`** behandeln (eingefroren) | Claude |
| E-271 | Empfehlung übernommen: **Standardmäßig auf „privat“ setzen**, sonst kann gekauft werden, ohne dass ein Zugang entsteht | Claude |
| E-272 | Empfehlung übernommen: **Signierte Antwort + SHA-256 erfüllt E-190**, ergänzt um eine Build-Attestierung | Claude |
| E-274 | Empfehlung übernommen: **Aktuelle und vorige Free-Minor** | Claude |
| E-275 | Empfehlung übernommen: **Ja** (auf `refunded`/`cancelled`), damit auch Affiliate die Provision storniert | Claude |
| E-276 | Empfehlung übernommen: **Gilt nicht**; Grant entsteht erst bei Zahlung, so lange die Bestellung offen ist | Claude |
| E-277 | Empfehlung übernommen: **Reservierung freigeben, Grant `failed`**, Lernender bekommt Hinweis zum erneuten Einlösen | Claude |
| E-278 | Empfehlung übernommen: **Nein**, Kursgutscheine sind Zugangsgutscheine | Claude |
| E-279 | Empfehlung übernommen: **Nein**, nur über die Course-Einlösung | Claude |
| E-280 | Empfehlung übernommen: **Pflegt der Shop in Woo**; Produkte entstehen als Entwurf | Claude |
| E-281 | Empfehlung übernommen: **Nein**, der bessere Rabatt gilt | Claude |
| E-282 | Empfehlung übernommen: **Einzeln**; mehrere Plätze nur über Gruppenprodukte | Claude |
| E-283 | Empfehlung übernommen: **Ja**, mit Begründung und Audit | Claude |
| E-284 | Empfehlung übernommen: **Ja**, Zeitpunkt des ersten Lernzugriffs wird vermerkt | Claude |
| E-39b | Schreibende Workflow-Aktionen über Grant-Quelle `workflow` im **Course-Pro-Connector**; Änderungsauftrag an das Workflow-Captain-Konzept (`wp-workflowmaster`), dort ist das LMS-Muster bisher ausgeschlossen | ja (Präzisierung) |
| E-69 | **Fragetypen Zuordnung, Sortieren, Lückentext schon in R1 Free.** Folge: R0-08 §3, R0-22 Validierung, R1-06 erweitern; E-199 (R2-08) entfällt | **ja** |
| E-168 | **Text-/Dateiaufgaben mit Bewertung in R1** (R2-02 wird nach R1 vorgezogen). Folge: Upload-Sicherheit (R0-10), Bewertungs-Workflow draft/submitted/returned/graded, Nutzertest-Referenzkurs mit Aufgabe schon in R1 (R0-26), Golden Master ohne LB-Quelle | **ja** |
| E-232 | Fortschrittseinsicht für Gruppenbesitzer nur in Pro als Opt-in, nur Fortschritt/Abschluss, Lernende werden informiert | nein |
| E-188 | Repository privat mit freien Sicherheitswerkzeugen; öffentlich zur ersten WordPress.org-Veröffentlichung | nein |
| E-193 | Deploy-Freigabe durch den Auftraggeber; Vertretung wird später benannt (vor R1-GATE) | nein |
| E-204 | Auftraggeber übernimmt die Reviewer-Rollen vorerst selbst; weitere Personen/Vertretung werden vor R1-Start benannt. Hinweis: Vier-Augen-Prinzip (Autor ≠ Reviewer) bleibt Pflicht | teilweise |
| E-161 | Rekrutierung gemischt: eigene Kontakte für Autoren/Admins, Dienstleister/Verbände für Personen mit Hilfstechnik | nein |
| E-162 | Budget für Nutzertests kommt; Betrag nennt der Auftraggeber vor R1-29 | nein |
| E-192 | Cloud-VM für Performance-Messungen wird gestellt | nein |
| E-196 | **Keine Lizenzen kommerzieller Plugins**; Kompatibilität mit WP Rocket, Elementor Pro u. a. nur „best effort“ (R0-20 Stufen anpassen) | **ja** |
| E-194 | Lesender Deploy-Key für hastla007/learnboating in der CI; nightly Koexistenztest gegen Commit 9f37c77, nur installiert, nie verändert/weitergegeben | nein |
| E-206 | WordPress.org-Konto mit 2FA legt der Auftraggeber an | nein |
| E-211 | Sicherheitsmeldungen über eine Adresse des Auftraggebers; Erstreaktion ≤ 72 h | nein |
| E-212 | Auftraggeber betreut das wp.org-Forum; Pro-Support über eigenen Kanal | nein |
| E-273 | **Pro-Add-on wird in `captain-pro/addons/course-captain-pro` gebaut** (Konzept-Plan). Voraussetzung: Zugriff auf das private Repo captain-pro für Prüfung von Kernel, FeatureRegistry, TARIFE, ARCHITECTURE | **ja** |
| E-195 | PHP 8.3 als Untergrenze bestätigt | nein |

### Folgeklärungen nach Einarbeitung (Claude im Auftrag, 16.11.2026)

| ID | Frage | Beschluss |
|---|---|---|
| E-285 | Präfix für Fehlercodes, AJAX/admin-post-Aktionen, Nonces, Shortcodes, Cache-Gruppe | **Einheitlich `ccap_`** (auch Fehlercodes, z. B. `ccap_setting_invalid`); Optionen/Transients `course_captain_*`; öffentliche Hooks/Funktionen `course_captain_*`. Präzisiert E-207/E-06 |
| E-286 | Zugang je Modul (E-27): Semantik | `module_id NOT NULL DEFAULT 0` (0 = ganzer Kurs). Scopes `view/download/exam` bleiben innerhalb eines Moduls. Eine Kursprüfung ist startbar, wenn Zugang zu allen Modulen besteht, aus denen ihr Blueprint zieht. Kursfortschritt: Nenner = Pflicht-Activities der zugänglichen Module, Anzeige „x von y freigeschalteten Modulen“ |
| E-287 | Abhängige Zusatz-Grants (E-26) | Abhängigkeit vom **effektiven Zugang zum Basiskurs**, nicht von einem bestimmten Grant; der Zusatz-Grant ändert seinen Status nicht, der effektive Zugang wird berechnet |
| E-288 | Wer bewertet Aufgaben in R1 (E-168 vs. E-99)? | **Trainer, Autor und Kursmanager des Kurses bewerten Aufgaben in R1 Free** (Note/bestanden + Feedback, Rückgabe). Gradebook, Bewertungsraster und Tutor-Workflows bleiben Pro. E-99 gilt für die Tutor-Bewertungsfunktionen darüber hinaus |
| E-289 | Grenzwerte für Datei-Abgaben | Erlaubt: pdf, docx, odt, txt, jpg, png; max. 20 MB je Datei, 5 Dateien je Abgabe (änderbar); kein eingebauter Virenscan, aber Hook für Scanner-Plugins; Aufbewahrung wie Rohantworten (E-51, 2 Jahre nach Kursende) |
| E-290 | Benachrichtigungen bei Aufgaben | „Abgabe eingereicht“ an zuständige Trainer, „zurückgegeben“ und „bewertet“ an Lernende; alle informativ (abbestellbar); Events `assignment.*` nur in der internen Outbox |
| E-291 | Schreibende öffentliche PHP-API (`grant_access_v1` u. a.) ohne Pro? | **Nur mit Pro** (E-38); Free vergibt Zugänge über Admin-UI, Gruppen, Selbstanmeldung und CLI |
| E-292 | Nachbewertung (R-47, E-74) | Datenvertrag (`evaluation_no`) in R1, Route und Oberfläche Pro |
| E-293 | Offene (unbezahlte) Woo-Bestellung | **Kein Grant**, auch nicht `pending`, bis zur Zahlung (präzisiert E-276); E-201 gilt nur für Quellen mit bestätigter Zahlung, aber ausstehender Course-Verarbeitung |
| E-294 | Gutschein nach 24 h freigegeben (E-277) | `failed` ist endgültig für diesen Idempotenzschlüssel; ein neuer Einlöseversuch erzeugt einen neuen Schlüssel |
| E-295 | Verlorener Dispute (E-275): `refunded` oder `cancelled`? | `refunded`, wenn Geld zurückgebucht wurde; `cancelled` nur, wenn die Bestellung vor Zahlung storniert wurde |
| E-296 | Speicherort des Reset-Journals (E-138) | Eigene Tabelle `ccap_reset_journal`, 30 Tage |
| E-297 | Verschachtelte Bundles und Bundles in Gruppen | **Eine Ebene** in R1 (Bundle enthält Kurse, keine Bundles); Gruppenplätze auf Bundles erlaubt |
| E-298 | Kursbeziehung „empfiehlt“ (INV-B-068/B-134) | **R4-Pool** (keine Zugangswirkung, reine Darstellung) |
| E-299 | ADR-Nummern aus R0-19 kollidieren mit Kap. 28.2 | R0-19-ADRs werden **ADR-009** (Fehlerformat) und **ADR-010** (Event-Envelope) |
| E-300 | Lease-Name der Migration | `schema-migration` |
| E-301 | Task für den Membership-Adapter (E-203) | **Neue Task R3-20** „Membership-Adapter“ |
| E-302 | WordPress.org-Einreichung (E-205 „früh“ vs. Richtlinie: nur vollständige Plugins) | Slug früh **prüfen**, Einreichung erst, wenn die R1-Release-Checkliste RC-001…078 grün ist |
| E-303 | Sicherheitsfristen (E-211) | Erstreaktion ≤ 72 h; Fix kritisch ≤ 7 Tage, hoch ≤ 30 Tage; zweiter Committer optional |
| E-304 | Shared-Hosting-Aussage (E-59) | Nur „bis 50 gleichzeitige Prüfungen“; keine Zahl für aktive Lernende |
| E-305 | Voucher-Kanal bei zwei Zieltypen (E-31) | Neutrales Ziel über Kanal `course-captain` (Einlöse-API), Woo-Produkt-Ziel über den Woo-Checkout von Voucher Captain |
| E-306 | Pro-Events/Connectoren und geplante Pro-Jobs nach Lizenzende | **Eingefroren** (E-93/E-94): bestehende Abonnements und geplante Jobs laufen weiter, keine neuen |
| E-307 | Doppelte Security-Header (Härtung vs. Security-Plugin) | Course Captain setzt keinen Header, den ein anderes Plugin oder der Server bereits setzt |
| E-308 | User-Enumeration-Schutz (E-241 Opt-in vs. E-261 R4) | **E-261 gilt** (R4-Pool); E-241 insoweit überholt |
| E-309 | Inhalt `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=()` nur auf Course-Captain-Antworten |
| E-310 | Paketserien beim Großimport (E-178) | Nicht Pflicht in R1; R4-Pool |
| E-311 | Integrationsstatus `failed` (D34-4) und Replay zugestellter Events (D34-7) | `failed` nach 3 Dead-Lettern innerhalb 1 h (nicht beim ersten); Replay zugestellter Events nur mit ausdrücklichem Flag, Begründung und Audit |
| E-312 | Widerspruch E-45a zu Konzept Kap. 43.3/R0-10 (externe Medien) | **Beschluss E-45a gilt** (jüngere Entscheidung, Kap. 46.1); Kap. 43.3 wird redaktionell angepasst |

### Neue Fragen aus dem Gate-Protokoll (Claude im Auftrag, 16.11.2026)

| ID | Frage | Beschluss |
|---|---|---|
| E-313 (NF-01) | LB-Vorabfreigabe unbezahlter Bestellungen (`approveOrder`) | **Behalten im Woo-Adapter (Pro):** ausdrückliche Admin-Aktion „vorab freischalten“ erzeugt einen Grant mit Quelle `woo_order` und Grund `preapproved`; wird die Bestellung storniert, wird er widerrufen. Ohne diese Aktion gilt E-293 |
| E-314 (NF-02) | Gutscheinziel Woo-Produkt: E-305 (Woo-Checkout) vs. E-279 (nur Course-Einlösung) | **E-279 gilt:** auch für Zieltyp Woo-Produkt wird über die Course-Einlösung eingelöst, ohne Woo-Bestellung, Quelle `voucher` (wie R0-40 §2.3); E-305 wird entsprechend korrigiert |
| E-315 (NF-04) | Grant auf ein Modul, das eine Kursrevision entfernt | Grant bleibt bestehen und wird im Admin als „Modul entfernt“ gemeldet; Umhängen nur per Support-Aktion als neue Grant-Revision (E-117) |
| E-316 (NF-05) | Aufgabe per Revision entfernt, offener Entwurf vorhanden | Entwurf bleibt schreibgeschützt lesbar und exportierbar, keine Abgabe mehr möglich |
| E-317 (NF-06) | Aufgaben: Standard-Abschluss und Nichtumfang R1 | Abschluss = `graded` (bzw. bestanden, falls Bewertung bestanden/nicht bestanden); nicht in R1: Zurückziehen nach Abgabe, Retakes, Fristen/Spätabgabe |
| E-318 (NF-07) | Supportaktionen für Abgaben | **Ja:** „Abgabe zurückgeben“ und „Bewertung korrigieren“ als K-18/K-19 mit Begründung und Audit |
| E-319 (NF-08) | Edition von `per_option`-Teilpunkten nach didaktischem Review | **Free** |
| E-320 (NF-10) | Deutsche Ersatztexte der Woo-Widerrufsfunktion (INV-B-134) | **Ja, Teil von E-33** |
| E-321 (NF-12) | Auto-Woo-Produkte für Zusatzmodule setzen die Abhängigkeit zum Basiskurs selbst? | **Ja**, aus der Kursbeziehung übernommen |
| E-322 (NF-13) | E-271 „standardmäßig privat“ bei Pro-Deaktivierung: abwählbar? Deinstallation? | Abwählbar im Wirkungsbericht; bei Deinstallation bleiben Produkte privat bestehen (keine Löschung) |
| E-323 (NF-15) | Schalter „Mehrsprachiger Kurs“ schaltet die Validator-Warnung (E-157) ab | **Ja** |
| E-324 (NF-16) | Bedienhilfe „alle Sprachversionen als Bundle vergeben“ | **Ja, in R1-13** (nutzt Bundle E-25a und Übersetzungsgruppe E-155) |
| E-325 (NF-18) | Zusätzliche Einstellung für Gutschein-Storno über E-35 hinaus | **Nein** |

## 1. Rechte und Lizenz (blockiert jede Code-Übernahme)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-01** | Gibst du als Urheber den learnboating-Code (Plugin, Connectors, Tools, Schemas, Tests) **schriftlich zur Übernahme unter GPL-2.0-or-later** in Course Captain frei? Inhalte, Medien, Gerätesimulation und KI-Fotos sind ausgenommen. | **Ja**, mit einer Freigabezeile in `docs/r0/R0-01-baseline.md`; sonst Neuimplementierung ohne Codeübernahme (Clean Room) | R0-01 §3, E/D-E-01 | R1 komplett (reuse/abstract) |
| E-02 | Werden Golden-Master-Tests nur mit synthetischen, neutralen Testpaketen gebaut, also ohne echte LB-Inhalte? | **Ja** | E/D-E-08 | R0-05 |
| E-03 | Bekommt Course Captain einen Importweg für alte LB-Content-Pakete? | **Höchstens als separates Migrationswerkzeug**, nie im Core | E/D-E-05 | R0-10, R1-15 |
| E-04 | Liefert Course Captain Rechtstext-Vorlagen mit (AGB, Widerruf)? | **Nein**, nur Datenschutz-Textbausteine über die WordPress-Datenschutzseite | E/D-E-06 | R0-17 |

## 2. Architektur und Plattform

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-05 | Gibt es in R1 eine eigene Mobile-/App-API mit Token-Auth? | **Nein.** Nur `course-captain/v1` mit Cookie und Nonce; Token-Auth erst, wenn ein App-Client beauftragt ist. Rate-Limit-Speicher und Idempotenz-Muster werden trotzdem übernommen | D/E1, C/E-C-04 | R0-19, R1-28 |
| E-06 | Gilt ab R1 ein einheitlicher, stabiler Fehlercode-Katalog für die Web-API (Format der LB-App-API)? | **Ja** | D/E2 | R0-19 |
| E-07 | Multisite-Unterstützung? | **Offiziell nur Single-Site.** Die Aktivierung je Website wird trotzdem technisch korrekt umgesetzt. Pro-Lizenz zählt je Site | C/E-C-01, Suite D4 | R0-06, R0-25, R0-37 |
| E-08 | Datenbank-Mindestanforderung | **InnoDB und JSON-Funktionen sind Pflicht** (MySQL 8 / MariaDB 10.6+, Details in R0-20) | C/E-C-08 | R0-06, R0-20 |
| E-09 | Wie bekommen Betreiber Updates? | **Free über WordPress.org, Pro über den License-Captain-Client**; keine GitHub-Abfrage | C/E-C-03 | R0-35, R0-39 |
| E-10 | Härtung der ganzen Website (Login-Limit, XML-RPC, Header) im LMS? | **Nicht im Free-Kern**; höchstens als optionales Modul ab R2, standardmäßig aus | C/E-C-02 | R0-17 |
| E-11 | Test- und CI-Werkzeuge | **PHPUnit mit WP-Testsuite und `@playwright/test`**; Schutz- und Isolationsmuster aus LB übernehmen | E/D-E-02 | R0-18 |
| E-12 | Plattform-Matrix (WP/PHP/Browser) | **LB-Matrix als Startwert** für R0-20 | E/D-E-03 | R0-20 |
| E-13 | Staging und Deploy | **Staging und CI-Deploy zu WordPress.org (SVN)** nur bei grüner CI, Tag auf `main` und manueller Freigabe | E/D-E-07 | R0-18, R0-35 |

## 3. Edition und Phase (Free / Pro / R2 / R3)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-14** | **Drip-Freischaltung:** Free oder Pro? (Kap. 5 sagt Pro, Kap. 38 verlangt LB-Parität in R1) | **Einfacher Drip** („Modul X Tage nach Zugangsbeginn“) **in Free**, Kampagnen und Gruppen-Drip in Pro | D/E3 | R0-04, R0-11 |
| E-15 | Kostenloser Teilumfang eines Kurses (Schnupperbereich) | **R1 Free** | D/E4 | R0-11 |
| **E-16** | **Kurs-FAQ und Lerntipps:** R1 Free oder erst R2? (Inventur A empfiehlt R1, Inventur D empfiehlt R2) | **Mechanik in R1 Free**, neutrale Starttexte erst mit R2-03. Das erfüllt die Parität ohne Content-Aufwand. Die öffentliche Website-FAQ (INV-D-056) bleibt Sache von Theme bzw. Drittplugin | A/E-A-05, D/E6 | R0-04 |
| E-17 | Gruppen: Verwaltung durch den Gruppenbesitzer | **In Free**, inklusive Gruppen ohne Kauf (manuell). Delegierte Verwaltung und Reporting in Pro | D/E5, B-Befund 2 | R0-29 |
| E-18 | Rechtestatus mit Auslieferungssperre: Gilt er auch für eigene Inhalte? | **In R1 Free**, aber die Sperre greift **nur für importierte bzw. fremde Inhalte**, nicht für selbst erstellte | D/E7, C/E-C-07 | R0-10, R0-22 |
| E-19 | Admin-Protokollansicht (Audit) | **R1 Free** | D/E8 | R0-13 |
| E-20 | Erklärungen zu Antworten | **Kein eigenes Verkaufsfeature**, sondern Kurseinstellung „Feedback sichtbar: immer / nach Abgabe / nie“ | A/E-A-01 | R0-08, R0-27 |
| E-21 | Karteikarten: ein Modell oder zwei? | **Ein Karteikartentyp mit zwei Modi** (Selbsteinschätzung; „3× gewusst in Folge“), R1 Free | A/E-A-02 | R0-08 |
| E-22 | Neutraler Fragetyp „Zahl mit Toleranz“ bzw. Fallstudie als Ersatz für den Navigationstrainer | **Nicht in R1**; höchstens Kandidat für R2/Pro, ohne Code aus NavigationCheck | A/E-A-03 | R0-08 |
| E-23 | Prüfungsmodus „offiziell/verifiziert“ | **Streichen.** Stattdessen Prüfungsregel „freigegeben/Entwurf“ über den Publish-Validator, plus Kennzeichnung für verkürzte Prüfungen | A/E-A-04, D/E10 | R0-08, R0-22 |
| E-24 | Erinnerung vor Zugangsablauf | **Pro**; Standard „nur an zahlende Käufer“. R1-21 umfasst in Free nur Status und Ablaufanzeige, keine Erinnerungsmails | B/D-9 | R0-11 |
| E-25 | Kursbeziehungen und Mitgliederrabatt | Beziehung „enthält“ (Bundle) in **R2**. Ausnahme: Teilkurs-Relation, falls sie für die Parität nötig ist (INV-A-107/144, D-153), dann in R1; **Rabatt nicht übernehmen**, höchstens im Woo-Adapter (R3) | B/D-8, D/E9 | R0-04 |
| E-26 | Abhängige Zusatz-Grants (Zusatzmodul läuft so lange wie der Basiskurs) | **Nur bei ausdrücklichem Upsell-Bedarf**, nicht in R1 | B/D-7 | R0-09 |
| E-27 | Granularität des Zugangs | **Je Kurs**, mit wenigen neutralen Scopes (Ansicht, Download, Prüfung) | B/D-10 | R0-06, R0-09 |
| E-28 | Einladungslinks für Gruppenplätze: übertragbar? Wer verliert bei Platzreduktion seinen Platz? | **LB-Regeln übernehmen**, plus Option „nur die eingeladene Adresse“ | B/D-11 | R0-09 |

## 4. Zugang, Kauf, Erstattung

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-29** | Darf ein durch Erstattung widerrufener Zugang **wieder aufleben**? (LB macht das, Konzept 35.2/35.3 verbietet es) | **Nein**; stattdessen neue Grant-Revision mit Audit | B/D-1 | R0-09, R3-03 |
| **E-30** | Gastkauf: **Kontopflicht** (wie LB) oder **Claim-Token** (Konzept CORE-SPEC-002)? | **R3.1 mit Kontopflicht starten**, Claim-Token als spätere Erweiterung. Das ist eine **Abweichung von CORE-SPEC-002** und muss ausdrücklich beschlossen werden | B/D-2 | R0-09, R3-02 |
| E-31 | Gutscheinziel: Woo-Produkt oder neutrales Zugangsangebot? | **Neutrales Zugangsangebot** im Course-Kern | B/D-3 | R0-40, R3-04 |
| E-32 | Geld-zurück-Garantie nach Lernfortschritt | **Nicht in R3.1/R3.2 übernehmen**. Der Kern sendet nur ein neutrales Nutzungsereignis, das ein Adapter verwenden kann | B/D-4, D/E9, A/E-A-06 | R0-19, R0-40 |
| E-33 | Deutsche Shop-Rechtspflichten (Widerrufsbutton, Bestellknopf, Preisangaben, OSS) und PayPal-/Klarna-Webhooks in Course Captain? | **Nein**, das gehört zu WooCommerce bzw. Rechtstext-Plugins. Ausnahme: optionales Gate „Grant erst nach Zustimmung zum sofortigen Beginn“ | B/D-5 | R0-04, R3-02 |
| E-34 | Woo-Produkte automatisch aus Kursen anlegen? | **Nein**, nur vorhandene Produkte zuordnen | B/D-6 | R3-02 |
| E-35 | Gutschein nach Einlösung storniert | **Nur den Voucher-Grant entziehen**; Lernhistorie bleibt | Suite D1 | R0-09, R3-04 |
| E-36 | Gruppengutscheine | **Ein Code pro Platz** | Suite D2 | R3-04 |
| E-37 | Meldet Course Verkäufe an Affiliate Captain? | **Nein (v1).** Affiliate bucht Woo-Bestellungen selbst; das verhindert doppelte Provision | Suite D3 | R0-40 |

## 5. Suite-Integration

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-38 | Course-Ereignisse, Read-API und Connector-Registry | **In Free**; Pro nur für fertige Adapter und Webhooks | Suite E1 | R0-19, R0-11 |
| E-39 | Schreibende Workflow-Aktionen (`course.enroll`/`revoke`) | **v1 ohne.** Später nur auf eigene Grants mit Quelle `workflow`. Das Workflow-Konzept schließt direktes Anlegen und Widerrufen von Lernzugängen aus; Konzept Kap. 8/30.2 wird angepasst | Suite E2 | R0-19, R3-17 |
| E-40 | Wo liegen die Connectoren zu Form, Workflow und Review? | **In Course Pro oder als separate Plugins**, erst wenn die Partner-Registries tatsächlich existieren (heute nur Konzept, kein Code) | Suite E3 | R3-07 |
| E-41 | Kommunikationsberechtigung für Bewertungsanfragen | **Prüft Review Captain**; Course liefert nur den Empfänger-Resolver | Suite E4 | R3-07 |
| **E-42** | Auftrag an License Captain bzw. Captain Pro: Produkt `course-captain-pro` und Präfix `course.` anlegen | **Ja, vor R3-18** (fehlt heute in `PRODUCT_KEYS`). `captain-pro` ist privat und konnte nicht geprüft werden; bitte Zugriff geben oder Inhalte bestätigen | Suite D5 | R0-39, R3-18 |

## 6. Datenschutz

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-43 | Konto nach bestätigtem Datenschutz-Löschantrag automatisch löschen? | **Standard aus**, nur als Opt-in | C/E-C-05 | R0-17, R0-31 |
| E-44 | Aufbewahrungsfristen | **LB-Werte als änderbare Defaults** (Audit 4 Jahre, Zugänge 10 Jahre, Import-Protokoll 14/180 Tage); juristische Bestätigung durch Betreiber | C/E-C-06 | R0-31 |
| E-46 | Verhalten der Rate-Limits bei Ausfall des Zählerspeichers (LB: alles durchlassen, nur loggen) | **Login, Passwort-Reset und Einlösen sperren (fail-closed); Lern-Limits durchlassen (fail-open)**; als ADR festhalten | D/E11 (INV-D-146) | R0-17, R0-21 |
| E-45 | Einwilligung für externe Medien (YouTube/Vimeo) | **Standard „Klick zum Laden“**, kein eigenes Cookie-Banner | E/D-E-04 | R0-10, R0-31 |

## 7. Konzept-Widersprüche, die mit diesen Entscheidungen aufgelöst werden

| Widerspruch | Aufgelöst durch |
|---|---|
| Kap. 18 und Kap. 30.3 enthalten zwei verschiedene Event-Envelopes „v1“ | **aufgelöst in R0-19 §5** (Struktur Kap. 30.3 plus Felder aus Kap. 18) |
| Kap. 30.3 und Kap. 33 nennen unterschiedliche Integrationsstatus-Listen | **aufgelöst in R0-19 §6** (8 Zustände aus Kap. 33) |
| Kap. 18.2: Pass-Check-in löst Kursabschluss aus; Kap. 30.2/31: höchstens Zugang | **Kap. 31 gilt** (jüngere Entscheidung nach Kap. 46.1); Kap. 18.2 wird redaktionell korrigiert |
| Kap. 8/30.2: Workflow-Aktion `course.enroll`/`revoke` | E-39 |
| Kap. 32: „Voucher allein“, Voucher setzt aber WooCommerce voraus | Faktenkorrektur: Test „Voucher allein“ entfällt; R3.3-Gate prüft „Woo allein“ und „Woo + Voucher“ |
| Kap. 30.2: Affiliate-Zeile „eigenständiger Course-Verkauf“ | E-37 |
| Kap. 45: bestellgebundene `api_v1` und key-basierte Einlöse-API vermengt | R0-40: Einlösung über `vcap()->redemptions` (technisch) |
| Kap. 5 (Drip Pro) und Kap. 38 (Parität R1) | E-14 |
| CORE-SPEC-002 (Claim-Token) und LB (Kontopflicht) | E-30 |
| Konzept 35.2/35.3 (kein Wiederaufleben) und LB-Code | E-29 |
| `ContentRepository::examRule` mit festem Schwellenwert 0.75 gegen Kap. 26.4 | technisch: Schwellenwert wird Kurs-/Prüfungseinstellung (R0-08/R0-27) |

## 8. Datenmodell (aus R0-06)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-47 | Speicherung hybrid (ADR-001)? | **Ja:** Kurs, Modul, Lektion, FAQ, Tipp als CPT; Lernstände, Versuche, Grants, Revisionen, Zertifikate, Outbox als eigene `cc_`-Tabellen | R0-06 D-2 | R1-02, R1-04 |
| E-48 | Fremdschlüssel | **Nur zwischen `cc_`-Tabellen**, nie auf `wp_posts`/`wp_users`; Integrität dorthin über Lösch-Hooks und Orphan-Check | R0-06 D-1 | R1-02 |
| E-49 | Netzwerkaktivierung auf Multisite in R1 | **Blockieren** wie LB; Schema trotzdem je Site (ergänzt E-07) | R0-06 D-3 | R0-25, R0-37 |
| E-50 | Fixiert eine veröffentlichte Kursrevision auch die Fragenrevisionen? | **Nein:** nur Struktur und Regeln; Fragenrevisionen werden beim Start eines Versuchs fixiert. Feinschnitt in R0-23 | R0-06 D-4 | R0-08, R0-23 |
| E-51 | Aufbewahrung von Lern- und Prüfungsdaten (E-44 deckt das nicht ab; Konzept 35.4 verbietet unbegrenzte Aufbewahrung als stillen Default) | **Änderbarer Default:** Lernstände bis Kontolöschung; Prüfungsversuche und Zertifikatsnachweise 10 Jahre; Rohantworten 2 Jahre nach Kursende. Bestätigung durch Betreiber/Recht | R0-06 D-5 | R0-17, R0-31 |
| E-52 | Lastannahmen A-01…A-18 (10 000 Lernende, 100 Kurse, 100 000 Fragen, 500 gleichzeitige Prüfungen) | **Bestätigen** als Messbasis für R0-14 | R0-06 D-6 | R0-14, R1-22 |

## 9. Phasen-Schnitt (aus R0-04)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-53 | Sind die WooCommerce- und Voucher-Connectoren Free oder Pro? (Kap. 5/6/7: Pro; Kap. 43.3: „Free Connector R3“) | **Pro** (Course Captain Pro). Free bleibt shopfrei und bietet nur die neutrale Grant-Schnittstelle. Begründung: Kap. 5/6/7 sind ausdrücklich, Kap. 43.3 ist eine Settings-Tabelle | R0-04 ED-6/W-06 | R0-11, R3-02, R3-04 |
| E-54 | Dürfen R2-Zuordnungen ohne eigene R2-Task bestehen? (z. B. E-028, D-058, B-068) | **Nein.** Jede R2-Zuordnung bekommt eine Task in R2, oder sie wandert in den R4-Nachfragepool. Die Zuordnung erfolgt in R0-24 | R0-04 ED-9/W-12 | R0-24 |

## 10. Golden Master (aus R0-05)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-55 | Wer gibt bewusste Abweichungen vom LB-Verhalten frei? (z. B. Smarttrainer-Reihenfolge, Kulanz von 120 s, Reihenfolge beim Platzverlust in Gruppen) | **Schriftlich durch den Auftraggeber**, über eine Abweichungsliste in `R0-05-golden-master.md` | R0-05 EGM-01 | R1-18 |
| E-56 | Ist ein Referenzlauf des LB-Plugins mit synthetischem Paket nötig, um die Erwartungswerte zu bestätigen? | **Nur für Fälle mit Rechenlogik** (Smarttrainer, Bewertung, Ablauf); setzt E-01 und E-02 voraus | R0-05 EGM-02 | R1-18 |

## 11. Plattform und Qualitätsziele (aus R0-14)

Die technischen Punkte D-14-03 (kürzere Lock-Wartezeiten), D-14-04 (FULLTEXT-Suche) und D-14-06 (k6 plus PHP-Race-Runner) sind Architekturfestlegungen. Sie gelten als Entwurf in R0-14 und brauchen keine eigene Auftraggeber-Entscheidung.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-57 | PHP-Untergrenze | ~~PHP 8.2~~ → **ersetzt durch E-195** (R0-20 empfiehlt 8.3) | R0-14 D-14-01 | R0-20 |
| E-58 | Welche Datenbankversionen werden getestet? | **MariaDB 10.11 und MySQL 8.4 in der CI**; MySQL 8.0 und MariaDB 10.6 „best effort“ (E-08 bleibt technische Mindestanforderung) | R0-14 D-14-02 | R0-20 |
| E-59 | Kapazitätsaussage für Shared Hosting | **Dokumentierte Klasse** „bis 50 gleichzeitige Prüfungen / 50 aktive Lernende“ (Annahme, Messung in R1-22); darüber VPS empfohlen | R0-14 D-14-05 | R1-22, R1-24 |
| E-60 | Sind Zeitbudgets ein Release-Gate? | **Ja für R1-GATE**; danach Query- und Scan-Gates in jeder CI-Runde und eine Zeitmessung je Minor-Release | R0-14 D-14-07 | R1-18, R1-GATE |
| E-61 | Browser-Support | **Die letzten zwei Hauptversionen** von Chrome, Edge, Firefox und Safari, dazu Firefox ESR, iOS Safari und Android Chrome (LB: nur Chromium) | R0-14 D-14-08 | R0-20, R1-19 |

## 12. Import und Medien (aus R0-10)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-62 | Wie wird der Rechtestatus `own` aus einem fremden Paket beim Import gespeichert? | **Als `licensed`**, weil für den Empfänger „eigen“ nicht stimmt | R0-10 R10-D1 | R1-15 |
| E-63 | Reimport trifft auf ein lokal geändertes Objekt | **Lokal gewinnt**; ob die Quelle übernommen wird, entscheidet der Autor je Objekt im Dry-run-Bericht | R0-10 R10-D2 | R1-15 |
| E-64 | Paketsignaturen in R1? | **Nein**; das Feld wird im Format nur reserviert | R0-10 R10-D3 | R1-15 |
| E-65 | Medien, die an einen Zugang gebunden sind | **Standardmäßig geschützt über PHP ausliefern** (LB: öffentlich in `uploads/`, nur die Frage gesperrt) | R0-10 R10-D4 | R1-14 |
| E-66 | Fremd lizenzierte Inhalte im Kurs-Export | **Nur per Opt-in** mit Hinweis | R0-10 R10-D5 | R1-15 |

## 13. Kurs-, Activity- und Prüfungsverträge (aus R0-08)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-67 | Hat eine Lektion in R1 genau eine Activity oder mehrere? | **1:1 in R1**, aber mit eigener `activity_uid`, damit 1:n später ohne Fortschrittsmigration möglich ist | R0-08 D-01 | R1-04, R1-07 |
| E-68 | Laufen bewertete Quizze über denselben Versuchsautomaten wie Prüfungen? | **Ja** (`cc_exam_session`, Modus `quiz`); unbewertete Quizze nutzen die Lernmodus-Engine | R0-08 D-02 | R1-07, R1-08 |
| E-69 | Fragetypen ohne LB-Beleg (Zuordnung, Sortieren, Lückentext; Konzept 3.3: Free) | **R1:** single, multi, true_false, open_text, flashcard; die übrigen **Free in R2** nach dem Paritäts-Gate | R0-08 D-03 | R1-06, R0-04 |
| E-70 | Darf ein Abschluss zurückgenommen werden? (LB: der Client kann `completed=false` senden) | **Nur `manual_confirm` durch den Lernenden**; nachweisbasierte Abschlüsse nie; Reset durch die Kursverwaltung mit Audit | R0-08 D-04 | R1-10 |
| E-71 | Zählt ein abgebrochener Versuch gegen `max_attempts`? | **Ja, wenn ein Limit gesetzt ist**; sonst könnte man Fragen durch Abbruch sichten | R0-08 D-05 | R1-08, R1-20 |
| E-72 | Versuche ohne Zeitlimit, die nie abgegeben werden (LB: laufen unbegrenzt und sperren Fragen 24 h) | **Einstellung** „automatisch abgeben nach x Tagen Inaktivität“, standardmäßig aus | R0-08 D-06 | R1-08, R1-17 |
| E-73 | Eigene Tabelle für Kompetenznachweise in R1? | **Nein**: abgeleitet aus Versuchen und Zertifikaten plus `competency_validity_days`; Tabelle erst mit manuellen bzw. externen Nachweisen | R0-08 D-07 | R1-10 |
| E-74 | Nachbewertung fehlerhafter Fragen nach Abgabe | **Im Datenvertrag ab R1** (`evaluation_no`, Revision mit Audit), Oberfläche erst mit dem Gradebook (Pro); nie automatisch | R0-08 D-08 | R1-09 |
| E-75 | `expired` als eigener Endzustand (Konzept 28.3) oder als `evaluated` mit `end_reason=expired` (LB)? | **`evaluated` mit `end_reason`** (LB-Parität); Konzept 28.3 wird redaktionell angepasst | R0-08 D-09 | R1-08 |
| E-76 | Standard-Abschlussregel für Videos | **`manual_confirm`** als Standard; „≥ 90 % angesehen“ als Option mit Hinweis auf Selbstauskunft des Players | R0-08 D-10 | R1-07 |
| E-77 | Teilpunkt-Strategien in R1 | **`all_or_nothing` und `proportional_penalty`**; `per_option` erst nach didaktischem Review | R0-08 D-11 | R1-06, R1-09 |

## 14. Rollen und Rechte (aus R0-07)

Wichtigster Sicherheitsbefund in LB: Der Default `default_access = logged_in` gibt jedem angemeldeten Konto Vollzugang zu allen Kursen. Course Captain übernimmt das **nicht**; der Default ist „nur mit Grant“. Das ist technisch zwingend und keine Auftraggeber-Frage.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-78 | Neue Tabelle `cc_course_staff` für die Zuweisung von Autoren und Trainern zu Kursen | **Ja** (Delta zu R0-06) | R0-07 D07-1 | R1-03 |
| E-79 | Eigene Lernsperre (`cc_learner_lock`) zusätzlich zum Rollenentzug | **Ja**; LB hat keine Konto- oder Lernsperre | R0-07 D07-2 | R1-03, R1-14 |
| E-80 | Kursmanager-Rolle | **In R1 site-weit**, kursbezogene Kursmanager erst später | R0-07 D07-3 | R1-03 |
| E-81 | Sichtbarkeit von E-Mail-Adressen für Gruppenbesitzer und Trainer (LB: alle Adressen sichtbar) | **Minimieren**: Name bzw. Anzeigename, E-Mail-Adresse nur maskiert oder per eigener Cap | R0-07 D07-4 | R1-13, R0-17 |
| E-82 | Pflichtbegründung, wenn Support oder Trainer fremde Versuchsdaten lesen | **Ja, mit Audit-Eintrag** | R0-07 D07-5 | R1-03, R0-13 |
| E-83 | Wer bekommt bei der Aktivierung Course-Captain-Caps? | **Nur `administrator`**; `editor` und `shop_manager` nicht automatisch | R0-07 D07-6 | R1-01, R1-03 |
| E-84 | Darf der Zugriffsfilter Zugang gewähren? (LB: `bootspruefung_can_access` kann Zugang beliebig erweitern) | **Nein, er darf nur einschränken.** Zugang entsteht ausschließlich über einen Grant | R0-07 D07-7 | R0-19, R1-14 |

## 15. Backup, Recovery und Deployment (aus R0-16)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-85 | Ausgang (Mails, Webhooks, Outbox) automatisch anhalten, wenn sich die Site-Identität ändert (Restore, Umzug, Kopie)? | **Ja**, bis ein Admin bestätigt | R0-16 D1 | R1-23, R1-40 |
| E-86 | Standard-Ausgangsmodus außerhalb von `production` (`COURSE_CAPTAIN_OUTBOUND`) | **`catch`** (zurückhalten und anzeigen) | R0-16 D2 | R1-23, R1-40 |
| E-87 | Ablage von Integrationsgeheimnissen | **Konstante in `wp-config.php`**, sonst verschlüsselt in der DB mit `COURSE_CAPTAIN_SECRET_KEY` | R0-16 D3 | R1-23, R1-40 |
| E-88 | Zurückgehaltene Mails und Ereignisse nach einem Restore | **Admin-Liste**; Standardaktion „verwerfen“ | R0-16 D4 | R1-23, R1-40 |
| E-89 | Bestätigung für Uninstall mit Datenlöschung | **Setting plus Eingabe des Site-Namens**, mit Audit | R0-16 D5 | R1-23, R1-40 |
| E-90 | Downgrade um eine Minor-Version ohne Restore offiziell unterstützen? | **Ja**, aber nur, wenn das Release keinen Contract-Migrationsschritt enthält | R0-16 D6 | R1-23, R1-40 |
| E-91 | RPO/RTO-Richtwerte für die Betreiberdoku | **RPO 24 h, RTO 4 h** als Empfehlung, nicht als Zusage | R0-16 D7 | R1-23, R1-40 |
| E-92 | Datenschutz-Löschanträge, die nach einem Restore aus einem älteren Stand wieder auftauchen würden | **Abweichend vom Agentenvorschlag („nur dokumentieren“): Lösch-Tombstones außerhalb des normalen Datenbestands führen und nach jedem Restore automatisch erneut anwenden.** „Nur dokumentieren“ reicht für Art. 17 DSGVO voraussichtlich nicht; rechtliche Bestätigung durch den Betreiber | R0-16 D8 | R1-23, R1-40 |

## 16. Free/Pro-Matrix und UX (aus R0-11)

Konzept-Widersprüche C-01…C-18 stehen in `R0-11-free-pro-matrix-ux.md` §4. Der wichtigste: Kap. 4.5/5 machen Zertifikate und Gruppen zu Pro, Kap. 43/46 zu Free-Basis. Nach der Rangfolge in Kap. 46.1 gilt Kap. 43/46.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-93** | Pro-Lizenz ist abgelaufen, das Add-on bleibt aktiv: Erzeugen bestehende Woo-/Voucher-Zuordnungen weiter Grants und Widerrufe? | **Ja, Grants und Widerrufe laufen weiter** (sonst zahlen Kunden ohne Zugang oder behalten ihn trotz Erstattung). Nur neue Zuordnungen und Konfigurationsänderungen sind gesperrt | R0-11 N-02 | R0-29, R0-39 |
| E-94 | Pro-Konfiguration bei Lizenzende bzw. bei deaktiviertem Add-on | **Lizenzende:** Konfiguration wirkt eingefroren weiter. **Add-on deaktiviert:** Rückfall auf Free-Verhalten, Daten bleiben erhalten | R0-11 N-01 | R0-29, R0-39 |
| **E-95** | Entitlement-Schlüssel (`course.*`) neu zuschneiden: Kap. 6 umfasst auch Free-Funktionen, es fehlen Erinnerungen, Gradebook, Frontend-Builder und Mehrtrainer | **Ja, neu zuschneiden vor dem Produktanlage-Auftrag E-42**; Liste in R0-11 §1 | R0-11 N-03 | R0-29, R0-39 |
| E-96 | Basisnachweis (Zertifikat Free) | **HTML- und Druckansicht in Free**; serverseitiges PDF und Designer erst in Pro | R0-11 N-04 | R0-29, R0-39 |
| E-97 | Name der Person auf der öffentlichen Verifikationsseite | **Standardmäßig aus**, je Nachweis vom Lernenden freizugeben | R0-11 N-05 | R0-29, R0-39 |
| E-98 | Umfang von „Berichte Basic“ in Free | **Kursfortschritt je Lernendem, Bestehensquote je Prüfung, CSV-Export**; Kohorten- und Zeitreihenberichte in Pro | R0-11 N-06 | R0-29, R0-39 |
| E-99 | Tutor-Rolle in R1 (R0-07 sieht `cc_trainer` vor, R0-11 empfiehlt nur reservieren) | **`cc_trainer` in R1 anlegen** (für Ergebnis-Einsicht und Support gebraucht, R0-07); Tutor-Bewertungsfunktionen erst mit dem Gradebook (Pro). Damit ist der Widerspruch zwischen R0-07 und R0-11 aufgelöst | R0-11 N-07 | R0-29, R0-39 |
| E-100 | Gesperrte Pro-Schalter in Free-Screens (Upselling) | **Nein**; höchstens ein Hinweis auf der Seite „Erweiterungen“ (WordPress.org-Richtlinie) | R0-11 N-08 | R0-29, R0-39 |
| E-101 | Voraussetzungen zwischen Lektionen innerhalb eines Kurses | **Free R1** | R0-11 N-09 | R0-29, R0-39 |
| E-102 | Demo-Schritt im Setup-Assistenten | **Leeres Kursgerüst** in R1; Demo-Kurse mit Inhalt in R2-03 | R0-11 N-10 | R0-29, R0-39 |

## 17. Revisionen laufender Kurse (aus R0-23)

Außerdem zur Bestätigung: Die Teilumfangsprüfung im laufenden Versuch läuft gegen den Snapshot, nicht wie in LB gegen die Live-Liste (RV-02, technisch begründet).

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-103** | Grandfathering-Modell bei Kursänderungen für laufende Lernende | **Vorwärtsrollen mit Besitzstand:** Alle sehen die neue Revision, Erreichtes bleibt. Harter Bestandsschutz nur über eine neue Kursausgabe (eigener Kurs); Fortschrittsanrechnung zwischen Ausgaben erst R2/Pro | R0-23 R23-D1 | R1-27 |
| E-104 | Wie bleiben CPT-Lektionen bis zur Veröffentlichung auf dem alten Stand? | **Nicht öffentliche Staging-Kopie** für Import und Editor, Übernahme in einer Transaktion je Kurs. Folge: Änderungen an veröffentlichten Kursen werden erst mit „Kurs veröffentlichen“ sichtbar | R0-23 R23-D2 | R1-27 |
| E-105 | „Erneut bearbeiten erforderlich“ je Activity nach einer inhaltlichen Änderung | **Ja**, beim Veröffentlichen, nur für Lernende ohne Kursabschluss, mit Audit und Hinweis | R0-23 R23-D3 | R1-27 |
| E-106 | Smarttrainer-Lernstand bei inhaltlicher Korrektur einer Frage | **Unverändert** (Parität), plus ausdrückliche Aktion „Lernstand dieser Frage zurücksetzen“ mit Audit; nie automatisch | R0-23 R23-D4 | R1-27 |
| E-107 | Zählen Versuche unter alter Prüfungsregel gegen `max_attempts` der neuen? | **Ja als Standard**; der Autor kann beim Veröffentlichen „Versuchszähler zurücksetzen“ wählen (Audit) | R0-23 R23-D5 | R1-27 |
| E-108 | Kursrückzug | **Zweistufig:** erst „Einschreibung geschlossen“ (bestehende Lernende lernen bis zum Grant-Ende weiter), dann „archiviert“. Sofortarchivierung nur mit Bestätigung und Anzahl der Betroffenen. Ein Abbruch wegen Rückzug zählt **nicht** gegen `max_attempts` (Ausnahme zu E-71) | R0-23 R23-D6, RV-04 | R1-27 |
| E-109 | Pflichtmedium einer Frage wird während eines laufenden Versuchs rechtlich gesperrt | **Frage bei der Auswertung neutralisieren** (aus Punkten und Maximum, Grund im Ergebnis) | R0-23 R23-D7 | R1-27 |
| E-110 | Bestehende Zertifikate im neuen Layout neu ausstellen | **Nicht in R1** (alte Template-Version wird weiter gerendert); Sammel-Neuausstellung als Pro-Kandidat | R0-23 R23-D8 | R1-27 |
| E-111 | Kursabschluss, wenn eine Revision die letzte offene Pflicht-Activity entfernt | **Automatisch** beim Umstellen; Zertifikat nach Kurseinstellung | R0-23 R23-D9 | R1-27 |

## 18. Identität, Plätze, Grants (aus R0-09)

Technisch festgelegt und nicht abzustimmen: In LB können Grants auf drei Wegen wieder aufleben (nach Refund, per `extend()` nach Ablauf, beim Aufheben eines manuellen Widerrufs). Alle drei sind in Course Captain verboten; das folgt aus E-29 und Konzept 35.2.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-112 | Gruppen-Einladungslink nur als Hash speichern (nach der Anlage nicht mehr anzeigbar, nur neu erzeugbar)? | **Ja** (LB speichert Tokens im Klartext) | R0-09 D09-1 | R1-13, R3-03 |
| E-113 | Prüfungsabgabe nach Verlust des Zugangs (z. B. Refund während der Prüfung) | **Wird gewertet**; automatisches Zertifikat nur, wenn bei Start **und** Abgabe ein Grant aktiv war | R0-09 D09-2 | R1-13, R3-03 |
| E-114 | Werkzeug zum Zusammenführen von Konten in R1? | **Nein**; nur die Support-Aktion „Zugang übertragen“ mit Audit | R0-09 D09-3 | R1-13, R3-03 |
| E-115 | Grenze für das Umverteilen von Gruppenplätzen in Free? | **Keine Grenze** | R0-09 D09-4 | R1-13, R3-03 |
| E-116 | Aufbewahrung von Inbox/Outbox-Ereignissen | **90 Tage**, danach löschen | R0-09 D09-5 | R1-13, R3-03 |
| E-117 | Kundenwechsel in der Quelle (z. B. Woo-Bestellung wird einem anderen Kunden zugeordnet) | **Neue Grant-Revision** statt stillem Umhängen wie in LB | R0-09 D09-6 | R1-13, R3-03 |

## 19. Öffentliche API (aus R0-19)

Technisch festgelegt und nicht abzustimmen (Architektur): öffentliche Hooks und Funktionen heißen `course_captain_*`, interne Kennungen (Tabellen, Caps, Cron, Fehlercodes) `cc_*`. `course_captain_enroll_v1` entfällt, Zugang entsteht nur über `grant_access_v1`. Fehlercodes sind klein mit `cc_`-Präfix, Content-Type `application/json` (WordPress-kompatibel, präzisiert E-06). Die Widersprüche Kap. 18 vs. 30.3 (Event-Envelope) und Kap. 30.3 vs. 33 (Integrationsstatus) sind in R0-19 §5/§6 aufgelöst.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-118 | Deprecation-Frist für stabile öffentliche Verträge (Hooks, REST, PHP-API, Events) | **Mindestens 2 Minor-Releases und 6 Monate**; ein alter Major-Namespace bleibt 12 Monate parallel. Ausnahme bei Sicherheitsproblemen, mit Changelog | R0-19 D19-2 | R1-28, R3-12 |
| E-119 | Werden Events auch ohne aktiven Connector gespeichert? | **Ja, 30 Tage** (änderbar im Datenschutz-Tab), minimal und pseudonym; nötig für Pull, Backfill und Replay | R0-19 D19-3 | R1-17, R3-01 |
| E-120 | Freigabe personenbezogener Event-Klassen (Lernfortschritt, Prüfungsergebnis, Zertifikat) je Connector | **Eigene Betreiberfreigabe je Klasse und Connector**; nur der Zugangsstatus ist ohne Extra-Freigabe dabei | R0-19 D19-4 | R3-01, R3-16 |

## 20. Barrierefreiheit und i18n (aus R0-15)

Zielstandard **WCAG 2.2 AA**. Technisch festgelegt und nicht abzustimmen: axe-Verstöße blockieren die CI (D-15-05); bei unzureichendem Kontrast schlägt das System die nächste konforme Farbe vor (D-15-07); fehlender Alt-Text blockiert die Veröffentlichung, fehlende Untertitel erzeugen eine Warnung (D-15-10); RTL-Fähigkeit ist Release-Gate (D-15-11, Konzept Kap. 21).

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-121** | Quellsprache der Oberflächen-Strings | **Englisch (en_US)**, Deutsch als erste Übersetzung. Voraussetzung für translate.wordpress.org; LB nutzt Deutsch | R0-15 D-15-01 | R1-01, R1-19, R0-35 |
| **E-122** | Nachteilsausgleich in Prüfungen | **Ja:** Zeitfaktor 100–400 % oder ohne Limit je Lernendem, nur durch Berechtigte, ohne Begründungstext, im Snapshot eingefroren, in Ergebnis und Nachweis nicht sichtbar. Datenschutzbewertung (Art. 9) in R0-17 | R0-15 D-15-02 | R1-08, R0-17 |
| E-123 | Timer-Modi je Prüfungsregel | **`strict`** (Standard für Nachweise) und **`practice`** (von Lernenden abschaltbar und verlängerbar, nicht nachweisfähig) | R0-15 D-15-03 | R1-08 |
| E-124 | Sprache des Lernbereichs und Zeitzonen | **Einstellung „Profilsprache“**, standardmäßig an bei mehr als einer installierten Sprache; Fristen mit Zonenkennung, keine eigene Nutzerzeitzone in R1 | R0-15 D-15-04 | R1-19, R0-25 |
| E-125 | Konformitätsbericht Barrierefreiheit je Release | **Ja**, WCAG-2.2-AA-Tabelle in der Doku. Keine Vorlage für die Barrierefreiheitserklärung (vgl. E-04), nur ein neutraler Hinweis, welche Angaben der Betreiber braucht | R0-15 D-15-06 | R1-24 |
| E-126 | Zugesicherte Screenreader | **NVDA und VoiceOver (macOS, iOS)**; TalkBack best effort, JAWS nicht zugesichert | R0-15 D-15-08 | R1-29 |
| E-127 | Barrierefreie PDF-Nachweise im Pro-Zertifikatsdesigner | **Ja**, getaggtes PDF (an PDF/UA orientiert) als Pro-Abnahmekriterium | R0-15 D-15-09 | R3 |

## 21. Kursvalidierung und Veröffentlichen (aus R0-22)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-128 | Darf ein Admin einen Publish-Blocker übersteuern? | **Nein**; Warnungen werden bestätigt (Fingerabdruck + Audit), Blocker nicht | R0-22 D22-1 | R1-26 |
| E-129 | Dürfen Entwürfe von Prüfungsregeln als Übung genutzt werden? (R0-08 §4.1 erlaubt es) | **Nein.** Übungsprüfungen sind veröffentlichte Regeln mit `purpose=practice`. R0-08 §4.1 wird entsprechend angepasst | R0-22 D22-2 | R1-26 |
| E-130 | Prüfungsblueprint verlangt mehr Fragen, als der Pool hat | **Blocker** nur bei Nachweis- und Pflichtprüfungen, sonst Warnung | R0-22 D22-3 | R1-26 |
| **E-131** | Fehlende Alt-Texte beim Veröffentlichen (R0-15: Blocker; R0-22: Warnung, per Site-Einstellung erzwingbar) | **Alt-Text fehlt (ohne `decorative`) = Blocker** wie R0-15; fehlende Untertitel = Warnung, per Site-Einstellung zum Blocker machbar. Damit ist der Widerspruch zwischen R0-15 und R0-22 aufgelöst | R0-22 D22-4 | R1-26 |
| E-132 | Freigabeprozess in Free | **Einstufig** (Autor reicht ein, Kursmanager veröffentlicht); mehrstufige Freigaben in Pro | R0-22 D22-5 | R1-26 |
| E-133 | Geplante Veröffentlichung (Zeitpunkt in der Zukunft) in R1 | **Nein** | R0-22 D22-6 | R1-26 |
| E-134 | Prüfungen in der Lernenden-Vorschau | **Durchspielbar ohne Speicherung** | R0-22 D22-7 | R1-26 |
| E-135 | Tägliche Health-Prüfung findet neue Blocker in einem veröffentlichten Kurs | **Nur melden**, nie automatisch zurückziehen | R0-22 D22-8 | R1-26 |

## 22. Support und Betrieb (aus R0-13)

Technisch festgelegt: Der Monitor-Schlüssel wird nur noch im Header akzeptiert, nicht als `?key=` (LB-Befund, Schlüssel würde sonst in Server-Logs landen).

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-136 | Benachrichtigung des Lernenden bei Supportaktionen | **Mail** bei Freischaltung, Übertragung (nur an das Ziel), technisch verworfenem Versuch, Annullierung, Zertifikatskorrektur/-widerruf; **keine Mail** bei Widerruf, Sperre, Aussetzung, Reset, Reconcile; im Dialog abwählbar | R0-13 D1 | R1-38 |
| E-137 | Zählt ein vom Support als technisch defekt verworfener Versuch gegen `max_attempts`? Gibt es „Zusatzversuch“? | **Zählt nicht** (`end_reason = support_void`, zweite Ausnahme zu E-71 neben E-108); plus Aktion „Zusatzversuch“ für belegte Störungen | R0-13 D2 | R1-38 |
| E-138 | Undo für „Fortschritt zurücksetzen“ | **Ja**, über ein Reset-Journal (30 Tage), nur solange kein neuer Fortschritt entstand | R0-13 D3 | R1-38 |
| E-139 | Manipulationserkennung im Audit | **Append-only plus Hash-Kette** mit täglicher Prüfung in R1; externer Sink (syslog/Webhook) als Pro-Kandidat | R0-13 D4 | R1-38 |
| **E-140** | Audit-Export in Free? (R0-07 §4.9 sieht ihn in Pro) | **Free**: gefilterter CSV/JSON-Export für Admins mit Pflichtbegründung. Prüfer und Betroffenenanfragen brauchen ihn auch ohne Pro. R0-07 §4.9 wird angepasst | R0-13 D5 | R1-38 |
| E-141 | „Anmelden als Lernender“ (Impersonation) für den Support | **Nein**; stattdessen Ansicht des effektiven Zugangs mit Begründung; Lesevorschau ohne fremde Daten als spätere Option | R0-13 D6 | R1-38 |
| E-142 | Zugang des Course-Captain-Herstellersupports zur Betreiber-Site | **Kein Konto**, nur das Diagnosepaket (Domain standardmäßig gehasht); sonst würde der Hersteller Auftragsverarbeiter | R0-13 D7 | R1-38 |
| E-143 | Debug- und Fehlerprotokolle | **Debug-Fenster höchstens 24 h** (Standard 2 h), Dateien 7 Tage; Fehlertabelle und Job-Fehler 30 Tage | R0-13 D8 | R1-38 |
| E-144 | Eine Supportrolle oder zwei? | **Eine Rolle `cc_support`** (lesen + Lernsperre); Grant-Korrekturen durch den Kursmanager oder per gezielter Cap-Zuweisung | R0-13 D9 | R1-38 |
| E-145 | Akteure in der Betroffenenauskunft | **Nur als Rolle** („Support“, „Administrator“, „System“), keine Namen der Mitarbeitenden | R0-13 D10 | R1-38 |

## 23. Threat Model und Datenschutz (aus R0-17)

Hauptrestrisiko ist die **Prüfungsintegrität**: Fragen und Antworten können über den Lernmodus gesammelt und nach der Abgabe weitergegeben werden. Das lässt sich bremsen (E-146, E-147), aber nicht verhindern; Course Captain verspricht kein Proctoring. **Klarstellung zu E-46 (aktualisiert nach Beschluss E-10 „Härtung im Kern“):** Fail-closed gilt im Free-Kern für Login, Passwort-Reset, Registrierung, Claim, Einlösen, Einladungen und Datenschutzanträge. Siehe R0-31 §3.4.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-146** | Fragen nur für Prüfungen (gegen Scraping: im Lernmodus ist mit Lösungen jeder Pool abrufbar) | **Kennzeichen `exam_only`** je Frage bzw. Pool in Free, standardmäßig aus; Hinweis im Editor | R0-17 D1 | R1-06, R1-08 |
| E-147 | Lösungen nach Abgabe, wenn noch Versuche offen sind | **Einstellung**, Default: nur richtig/falsch je Frage; Lösungen erst ohne Restversuche oder nach Bestehen (präzisiert E-20) | R0-17 D2 | R1-09 |
| E-148 | Tab- und Fokuswechsel in Prüfungen protokollieren? | **Nein**: unzuverlässig und Verhaltensüberwachung ohne echten Integritätsgewinn | R0-17 D3 | R1-20 |
| E-149 | Gleicher Versuch in mehreren Tabs/Geräten | **Letzter Stand gewinnt** plus sichtbarer Hinweis „in anderem Fenster geöffnet“ | R0-17 D4 | R1-20 |
| **E-150** | Löschantrag gegen Nachweis-Aufbewahrung (E-51: Versuche/Zertifikate 10 Jahre). Ohne gesetzliche Pflicht ist Behalten gegen einen Löschantrag riskant | **Eraser löscht standardmäßig.** Einstellung „Nachweise trotz Löschantrag pseudonymisiert aufbewahren“ (Default aus, Pflichtbegründung, im Eraser-Ergebnis sichtbar). Präzisiert E-51; juristische Bestätigung durch den Betreiber | R0-17 D5 | R1-17 |
| E-151 | Tombstones (zu E-92): Ablage und Frist | **Tabelle plus Journal** außerhalb des Backups (`COURSE_CAPTAIN_PRIVACY_JOURNAL_DIR`); Frist = maximales Backup-Alter + 30 Tage (Default 120 Tage); zurückgehaltene Mails 30 Tage | R0-17 D6 | R1-17, R1-23 |
| E-152 | Rate-Limit-Defaults | **Tabelle R0-17 §3.4 als Defaults**; fail-closed zusätzlich für Einladungsversand, Datenschutzanträge und Import/Export | R0-17 D7 | R1-03, R1-42 |
| E-153 | DSFA- und VVT-Baustein für Betreiber | **Doku-Vorlage plus generierte Verarbeitungsübersicht im Systemstatus**; keine fertige DSFA, keine Rechtsbewertung | R0-17 D8 | R1-24, R1-38 |
| E-154 | Lerndaten inaktiver Konten | **Optionaler Job** „Lerndaten nach x Jahren ohne Anmeldung und ohne aktiven Grant löschen“, Default aus, mit Vorschau | R0-17 D9 | R1-17 |

## 24. Multisite und Sprachen (aus R0-25)

Technisch festgelegt: Grenze zu WPML/Polylang/TranslatePress nur dokumentieren, mit Systemstatus-Hinweis (D-25-06). Fallback auf eine andere Variante derselben Sprache, z. B. `de_AT` → `de_DE` (D-25-07). Eine erzwungene Netzwerkaktivierung führt in den Haltemodus (D-25-08). Im Lernbereich gilt für Angemeldete die Profilsprache, für Gäste die Site-Sprache (D-25-10).

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-155** | Wie werden Sprachversionen eines Kurses abgebildet? | **Eigener Kurs je Sprache**, verknüpft über eine Übersetzungsgruppe; Fortschritt, Grants und Zertifikate gelten je Kurs | R0-25 D-25-01 | R1-19 |
| E-156 | Gilt ein Grant für alle Sprachversionen eines Kurses? | **Nein, kein Übertrag in R1**; Sammelvergabe durch den Kursmanager möglich | R0-25 D-25-02 | R1-19 |
| E-157 | Gemischte Sprachen innerhalb eines Kurses (z. B. englische Fragen in einem deutschen Kurs) | **Erlaubt** mit `lang`-Kennzeichnung und Validator-Warnung | R0-25 D-25-03 | R1-19 |
| E-158 | Sprache des Zertifikats | **Kurssprache**, im Snapshot eingefroren | R0-25 D-25-04 | R1-19 |
| E-159 | Sprachvarianten für Mailvorlagen | **Ja, schon in Free** | R0-25 D-25-05 | R1-19 |
| E-160 | Mandanten innerhalb einer Site (z. B. mehrere Akademien in einer Installation) | **Nein in R1**, kein `tenant_id` | R0-25 D-25-09 | R1-19 |

## 25. UX-Abnahme mit Nutzertests (aus R0-26)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-161** | Wer rekrutiert die Teilnehmenden für die Nutzertests? | **Gemischt**: eigene Kunden bzw. Netzwerk für Autoren und Admins; Personen mit Hilfstechnik über Dienstleister oder Verbände | R0-26 D-26-01 | R1-29 |
| **E-162** | Budget für Nutzertests | **Pauschale je Sitzung plus Testgeräte** (Mengengerüst in R0-26; Betrag legt der Auftraggeber fest) | R0-26 D-26-02 | R1-29 |
| E-163 | Teilnehmerzahl je Rolle | **6 Autoren, 5+4 Lernende** (davon 4 mit Hilfstechnik), **5 Gruppenverwalter, 5 Admins**; Nachtest mit mindestens 3 neuen Personen | R0-26 D-26-03 | R1-29 |
| E-164 | Wer moderiert? | **Eine Person, die nicht an der Entwicklung beteiligt ist**; extern für die Barrierefreiheitssitzungen | R0-26 D-26-04 | R1-29 |
| E-165 | Umfang der Autorenaufgabe | **10 Fragen manuell, 40 per Import**; alle 50 manuell nur im Praxis-Benchmark | R0-26 D-26-05 | R1-29 |
| E-166 | Abnahmeschwellen | **Erfolg ≥ 80 % je Aufgabe, SUS ≥ 70**, Referenzkurs veröffentlichen im Median ≤ 90 min, Setup ≤ 10 min, Prüfung fortsetzen ≤ 1 min ohne Antwortverlust; jede Person mit Hilfstechnik löst jede kritische Aufgabe ohne sehende Hilfe | R0-26 D-26-06 | R1-29 |
| E-167 | Aufzeichnung der Sitzungen | **Mit Einwilligung**, Löschung 6 Monate nach R1-GATE | R0-26 D-26-07 | R1-29 |
| E-168 | Referenzkurs mit „Aufgabe (Abgabe)“ (Konzept 27.4/28.4 verlangt eine) | **Erst ab R2** (RK-v1.1 nach R2-02), weil Text- und Dateiaufgaben erst dort kommen | R0-26 D-26-08 | R1-29 |
| E-169 | Testsprache | **de_DE** plus eine Admin-Sitzung in en_US | R0-26 D-26-09 | R1-29 |
| E-170 | Wiederholung der Nutzertests vor späteren Gates | **Ja**, gezielt für geänderte Flows vor R2-GATE und R3-GATE | R0-26 D-26-10 | R1-29 |
| E-171 | S2-Befunde (schwer, nicht kritisch) vor R1-GATE | **Nur mit dokumentierter Akzeptanz** durch den Auftraggeber, nie bei WCAG-Bezug in Lernenden-Flows; S1 muss immer behoben sein | R0-26 D-26-11 | R1-29 |

## 26. Schema-Upgrade (aus R0-37)

Technisch festgelegt: Migrationszustand in Tabelle `cc_migration_step` (D37-1); Cron-Hook heißt `cc_migrate` (Widerspruch R0-06 `cc_run_upgrade` und R0-19 aufgelöst zugunsten R0-19); Regel K-2: neuer Code muss das alte Schema lesen können.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-172 | Contract-Schritte (Entfernen alter Spalten/Tabellen) bei Upgrades | **Nie automatisch**; nur nach Admin-Freigabe mit Backup-Bestätigung oder per CLI-Flag | R0-37 D37-2 | R1-40 |
| E-173 | Prüfungsfrist, wenn während eines laufenden Versuchs migriert wird | **Frist = Ende des Wartungsfensters + 120 s**, höchstens 30 min Verlängerung | R0-37 D37-3 | R1-20, R1-40 |
| E-174 | WordPress-Auto-Updates während laufender `strict`-Prüfungen | **Aufschieben**, höchstens 24 h | R0-37 D37-4 | R1-40 |
| E-175 | Verhalten nach dauerhaft fehlgeschlagener Migration | **Schreibzugriffe bleiben gesperrt**, Lesen weiter möglich; kein Notbetrieb; Admin-Hinweis mit Runbook | R0-37 D37-5 | R1-40 |

## 27. Großimporte (aus R0-38)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-176 | Ein fehlerhafter Datensatz im Großimport (LB bricht den ganzen Import ab) | **Fehler am einzelnen Objekt isolieren**; der Rest läuft weiter, Abhängige werden mitmarkiert | R0-38 D1 | R1-41 |
| E-177 | Standard-Ausführungsweg für Importe | **Hintergrund** (Action Scheduler bzw. WP-Cron) mit Fortschrittsanzeige; Browser-Polling und WP-CLI als Alternativen, mitten im Lauf wechselbar | R0-38 D2 | R1-41 |
| E-178 | Pakete über 200 MB (Upload-Grenze im Admin) | **Ablageordner auf dem Server oder WP-CLI**, mit höheren Grenzwerten | R0-38 D3 | R1-41 |
| E-179 | Kurse mit Publish-Blocker nach dem Import | **Zurückstellen**, die übrigen Kurse aktivieren | R0-38 D4 | R1-41 |
| E-180 | Fehlerschwelle, ab der ein Import anhält | **max(100 Fehler, 2 % der Objekte)** | R0-38 D5 | R1-41 |
| E-181 | Automatische Wiederaufnahme abgebrochener Importe | **Ja**, nach Vorprüfungen (Lease abgelaufen, Paketkopie unverändert) | R0-38 D6 | R1-41 |

## 28. Tests, Gates und R1-Backlog (aus R0-12)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-182** | Neuer Task **R1-43** „Domain-Events, Outbox und Connector-Registry im Free-Kern“? (Nach E-38/E-119 gehört das in Free, R1-Tests setzen es voraus, kein R1-Task baut es) | **Ja, aufnehmen** | R0-12 D12-1 | R1-Backlog |
| **E-183** | Zertifikate: neuer Task **R1-44** „Basisnachweis-Domäne“ oder R1-35 teilen? (R1-27 braucht Zertifikate, R1-35 hängt von R1-27 ab) | **R1-44 aufnehmen** (Domäne und Ausstellung), R1-35 bleibt Verwaltung und UI | R0-12 D12-2 | R1-27, R1-35 |
| E-184 | Gate-Schwelle und Abzeichnung | **100 % der R1-Pflichttests grün**, Ausnahmen nur per E-Nummer oder freigegebener GM-Abweichung (E-55); Abzeichnung durch den Auftraggeber | R0-12 D12-3 | R1-GATE |
| E-185 | Partnererkennung (welche Suite-Plugins sind installiert) schon in R1? | **Lesend in R1** (nur Anzeige im Systemstatus), alle Aktionen erst in R3 | R0-12 D12-4 | R1-38, R3-16 |
| E-186 | COMMERCE- und SUITEFLOW-Testkataloge | **Eingangsbedingung von R3-01**, nicht von Gate A | R0-12 D12-5 | R3-01 |
| E-187 | Benachrichtigungsvertrag (Versand-Einmaligkeit, Zustellfehler, Opt-out, Rate-Limit, Mail-Sink) | **In R0-27 spezifizieren** | R0-12 D12-6 | R0-27, R1-21 |

## 29. Release- und QA-Automatisierung (aus R0-18)

Hinweis: E-58 nennt für die CI MariaDB 10.11, R0-14 nimmt MariaDB 12.3 als Zielzeile. Beide sind in der Matrix; R0-20 legt die endgültigen Versionen fest.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-188** | Sichtbarkeit des Repositorys und GitHub-Ausstattung (CodeQL, Secret Scanning, Dependabot-Alerts sind für private Repos kostenpflichtig) | **Privat mit freien Werkzeugen als Pflichtbasis** (Semgrep, Psalm, gitleaks, OSV, composer/npm audit); **öffentlich spätestens zur ersten WordPress.org-Veröffentlichung**, dann GitHub-Funktionen zusätzlich | R0-18 D18-1 | R1-01, R1-39 |
| E-189 | Signatur der Free-Artefakte | **GitHub Artifact Attestation + Sigstore keyless**, keine eigene Schlüsselverwaltung | R0-18 D18-2 | R1-01, R1-39 |
| E-190 | Signatur der Pro-ZIPs | **License Captain signiert beim Upload**, der Pro-Client prüft vor der Installation (abhängig vom License-Captain-Vertrag, E-42) | R0-18 D18-3 | R3-18 |
| E-191 | Browser-Abdeckung über Playwright hinaus | **Playwright je PR/nightly**; echtes iOS/Android/Safari **manuell vor jedem Minor-Release** | R0-18 D18-4 | R1-01, R1-39 |
| E-192 | Messumgebung R-M für Performance-Releases | **Cloud-VM mit festem Instanztyp**, je Lauf erzeugt und danach gelöscht (Kosten beim Auftraggeber) | R0-18 D18-5 | R1-01, R1-39 |
| **E-193** | Wer gibt Deploys zu WordPress.org bzw. License Captain frei? | **Der Auftraggeber** (Vertretung benennen); Selbstfreigabe durch den Tag-Ersteller ausgeschlossen | R0-18 D18-6 | R1-01, R1-39 |
| E-194 | Koexistenztest mit dem LB-Plugin in der CI | **Nightly gegen den festen LB-Commit `9f37c77`**, nur installiert, nie verändert oder weitergegeben; braucht einen lesenden Deploy-Key für learnboating | R0-18 D18-7 | R1-01, R1-39 |

## 30. Kompatibilität und Konflikte (aus R0-20)

Technisch festgelegt: CSS-Präfix `ccap-` (kollidiert sonst mit Cookie-Banner-Bibliotheken, die `cc-` nutzen), Optionen, AJAX-Aktionen und Query-Vars `course_captain_` (Nachtrag zu R0-19 §1.2; D-20-03). Die Rollen `cc_trainer`, `cc_support` und `cc_author` bekommen `view_admin_dashboard`, sonst leitet WooCommerce sie auf „Mein Konto“ um (D-20-10). Ausschlüsse für JS-Optimierer und Purge-Aufrufe für LiteSpeed/WP Rocket (D-20-04/05); der Lernbereich funktioniert auch mit einfachen Permalinks (D-20-08); die Zertifikatsverifikation wird serverseitig gerendert, weil Security-Plugins REST für Gäste oft sperren (D-20-09). Der MariaDB-Widerspruch ist nur scheinbar: E-58 regelt die Untergrenze 10.11, das Ziel ist 12.3 (bzw. 11.8, falls 12.3 keine LTS ist). **Offen:** Verfügbarkeit des Slugs `course-captain` auf WordPress.org prüfen (OP-20-1, kein Internetzugriff).

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-195** | PHP-Untergrenze (ersetzt die Empfehlung in E-57) | **PHP 8.3**, getestet 8.3/8.4/8.5. Begründung: 8.2 bekommt laut Modellwissen ab 01.01.2027 keine Sicherheitsupdates, R1 erscheint frühestens 2027. Vor R1-GATE auf php.net verifizieren | R0-20 D-20-01 | R1-01 |
| E-196 | Lizenzen für kommerzielle Plugins in den Kompatibilitätstests (WP Rocket, Elementor Pro u. a.) | **Stellt der Auftraggeber**; ohne Lizenz wird die Kombination als „best effort“ geführt | R0-20 D-20-07 | R1-30 |
| E-197 | Öffentlicher URL-Slug für Kurse (`course`/`courses` sind von LifterLMS, Tutor, LearnDash belegt) | **Nur `cc_course` bekommt eine öffentliche URL**, Standard-Slug `kurs` (in den Einstellungen änderbar); Lernbereich `/lernen/` | R0-20 D-20-11 | R1-04, R1-37 |
| E-198 | DB-Query-Caches (z. B. W3TC-Datenbankcache) | **Nicht unterstützt**, mit Warnung im Systemstatus (Gefahr veralteter Zugriffsentscheidungen) | R0-20 D-20-06 | R1-25, R1-30 |

## 31. Traceability und Definition of Ready (aus R0-24)

Technisch festgelegt: Die Verweise an den 40 Duplikaten im TODO-Register werden eingetragen (D24-9), es gibt keine gestaffelte Readiness (D24-8), PAR-13…15 für BS-19/23/24 werden in Kap. 38 ergänzt (D24-6). Korrekturen aus R0-24 §6.2: Die ADR-Nummern 007/008 in R0-19 kollidieren mit Kap. 28.2 und werden in R0-19 umbenannt. Webhook-Replay antwortet einheitlich `202 duplicate` (RBAC-093/SEC-026 angepasst, RBAC-093 Ziel R3). E-190 blockiert R3-18, nicht R1-01/R1-39. Tests werden per `@group R1-xx` je Task auswählbar.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-199 | Eigene R2-Task für die Fragetypen Zuordnung, Sortieren und Lückentext (E-69) | **Ja, neue R2-08** „Zusätzliche neutrale Fragetypen“ (Voraussetzung R2-04) | R0-24 D24-1 | R2 |
| E-200 | XLSX-Fragenimport (LB-Werkzeug außerhalb des Plugins) | **R4-Pool**; R1 importiert nur cc-package | R0-24 D24-2 | R1-41 |
| E-201 | Timeout für Grants im Zustand `pending` | **24 h**, danach `failed` mit Grund `pending_timeout`, Support-Hinweis und Event | R0-24 D24-3 | R1-13, R3-02 |
| E-202 | Suite-Navigationsmuster | **Eigenes Course-Captain-Hauptmenü**, Suite-Status als Unterseite „Integrationen“; gemeinsames Suite-Menü frühestens mit R3-16 | R0-24 D24-4 | R1-32, R3-16 |
| E-203 | Mitgliedschaften (Membership) als Zugangsweg | **Kein eigener Vertrag**: Mitgliedschaft ist eine Grant-Quelle über den GrantSourceAdapter, Adapter erst in R3 | R0-24 D24-5 | R1-43, R3 |
| **E-204** | Wer besetzt die Reviewer-Rollen (Technik-Lead, Architektur, Security, Barrierefreiheit, Betrieb, Produkt)? | **Der Auftraggeber benennt je Rolle eine Person und eine Vertretung**; Vier-Augen-Prinzip, Autor ≠ Reviewer | R0-24 D24-7 | alle R1-Tasks (DoR) |

## 32. WordPress.org-Distribution (aus R0-35)

WordPress.org-Details beruhen auf Modellwissen und werden über RC-001 vor R1-39 geprüft.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-205 | Ersatz-Slug und Zeitpunkt der Einreichung bei WordPress.org (`course-captain` noch ungeprüft) | **Slug vor R1-Start prüfen und früh einreichen**; Ersatz `course-captain-lms` | R0-35 D35-1 | R1-39 |
| **E-206** | WordPress.org-Konten, Committer und Herstellerangaben | **Organisationskonto**, mindestens zwei Committer mit 2FA | R0-35 D35-2 | R1-39 |
| E-207 | Präfix: `cc_` hat nur 3 Zeichen, das Review verlangt laut Modellwissen mindestens 4 | **`ccap_`** für CPTs, Caps, Rollen, Cron, Meta und Tabellen (revidiert D-20-03/R0-19 §1.2; vor R1-01 prüfen) | R0-35 D35-3 | R1-39 |
| E-208 | Deutsche Übersetzung | **Sprachpaket über translate.wordpress.org**, bis zur Freigabe zusätzlich im ZIP | R0-35 D35-4 | R1-39 |
| E-209 | Erste öffentliche Versionsnummer | **1.0.0** | R0-35 D35-5 | R1-39 |
| E-210 | Telemetrie / Nutzungsstatistik | **Keine** | R0-35 D35-6 | R1-39 |
| **E-211** | Sicherheitsmeldungen: Kanal, Fristen, unterstützte Versionen | **Eigene Sicherheitsadresse**, Erstreaktion ≤ 72 h, Fix für die aktuelle Minor-Version | R0-35 D35-7 | R1-39 |
| **E-212** | Wer betreut das wp.org-Forum, wo läuft Pro-Support? | **Auftraggeber benennt eine Person**; Pro-Support über eigenen Kanal | R0-35 D35-8 | R1-39 |
| E-213 | Lizenz des Pro-Add-ons | **GPL-2.0-or-later** | R0-35 D35-9 | R1-39 |
| E-214 | Live Preview auf WordPress.org | **Erst ab R2-03** | R0-35 D35-10 | R1-39 |

## 33. Settings-Register und Benachrichtigungen (aus R0-27)

Technische Korrekturen aus R0-27 (T27-1…8) sind übernommen, u. a. Optionsnamen `course_captain_*` in R0-06/R0-16 und kein PDF-Link in Free-Mails (E-96).

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-215 | Aufbewahrung: Inbox/Outbox 90 Tage (E-116) und Event-Log 30 Tage (E-119) – Widerspruch? Und welche Frist für das Benachrichtigungsprotokoll? | **Kein echter Widerspruch:** Inbox und zugestellte Outbox-Einträge 90 Tage; Events ohne Abonnent 30 Tage; Benachrichtigungsprotokoll 90 Tage | R0-27 D27-1 | R1-21, R1-31 |
| E-216 | Welche Mails sind Pflicht (nicht abbestellbar), welche informativ? | **Pflicht:** Claim/Einladung, Sicherheits- und Kontomails, Datenschutzanträge; alle übrigen abbestellbar (Tabelle R0-27 §3.2) | R0-27 D27-2 | R1-21, R1-31 |
| E-217 | Mail „Zugang abgelaufen“ in Free | **Vorhanden, standardmäßig aus**, ohne Kauf- oder Verlängerungslink | R0-27 D27-3 | R1-21, R1-31 |
| E-218 | Versand-Grenzwerte | **Retry 5×** (5 min, 30 min, 2 h, 12 h); max. 10 Mails je Empfänger und 24 h; 3 Neuversendungen je Einladung; 5 Testmails je Admin und Stunde | R0-27 D27-4 | R1-21, R1-31 |
| E-219 | Eigener Absender für Course-Captain-Mails | **Optionale Felder Name/Adresse**, leer = WordPress-Standard | R0-27 D27-5 | R1-21, R1-31 |
| E-220 | Slug-Änderung (Lernbereich, Kurs): alte Links auf Zertifikaten und in Mails | **Frühere Slugs dauerhaft merken und per 301 weiterleiten**, solange keine andere Seite den alten Slug belegt | R0-27 D27-6 | R1-21, R1-31 |
| E-221 | Rate-Limits im UI änderbar? | **Nein, nur per Filter**; die wirksamen Werte werden im Tab „Erweitert“ angezeigt | R0-27 D27-7 | R1-21, R1-31 |
| E-222 | Gruppen-Grundfunktion standardmäßig an? | **Aus**, im Setup-Assistenten aktivierbar | R0-27 D27-8 | R1-21, R1-31 |
| E-223 | Automatischer Datenexport nach bestätigtem Datenschutzantrag | **An** (LB-Parität), Download-Link an die bestätigte Adresse | R0-27 D27-9 | R1-21, R1-31 |
| E-224 | Content-Security-Policy für Course-Captain-Antworten | **Erzwingen (`enforce`)**, Umschalten auf `report_only` mit Bestätigung | R0-27 D27-10 | R1-21, R1-31 |

## 34. Settings-Vererbung (aus R0-28)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-225 | Wirkt eine globale Sperre auch auf bereits veröffentlichte Prüfungsregeln? | **Ab dem nächsten Versuchsstart**; laufende Versuche bleiben unverändert | R0-28 D28-1 | R1-33 |
| E-226 | Sammel-Reset von Kurs-Overrides auf „erben“ in R1? | **Ja**, nur in Entwürfen, mit Rückgängig | R0-28 D28-2 | R1-33 |
| E-227 | Geerbte Werte bei einer neuen Prüfungsregel-Version neu auflösen? | **Ja**, Änderung wird im Veröffentlichungs-Diff angezeigt | R0-28 D28-3 | R1-33 |
| E-228 | Importierte Prüfungsregeln: Werte erben oder explizit übernehmen? | **Explizit übernehmen**, damit die Prüfung gleich bleibt | R0-28 D28-4 | R1-33 |
| E-229 | Kurs-Ebene für Prüfungseinstellungen (`exams.*`)? (Widerspruch R0-08 §4.5 / R0-27) | **Nicht in R1**: Prüfungseinstellungen nur global und je Prüfungsregel | R0-28 D28-5 | R1-33 |
| E-230 | Gehören Sperren zum Tab-Reset und zum Settings-Export? | **Ja** | R0-28 D28-6 | R1-33 |

## 35. Gruppen und Zertifikate Free/Pro (aus R0-29)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-231 | Gruppen-Auswertung in Free (E-17: Reporting Pro; R0-07/R0-19 sehen einen Gruppenbericht in R1) | **Nur ein Gruppenfilter im Basisbericht** in Free; eigene Gruppenberichte in Pro | R0-29 D29-1 | R1-35, R1-44, R3-08 |
| **E-232** | Dürfen Gruppenbesitzer/Delegierte den Lernfortschritt der Mitglieder sehen? (Konzept 35.1: Käufer sieht keine Lernleistungen) | **Nur in Pro als Opt-in-Gruppeneinstellung**, nur Fortschritt und Abschluss, nie Antworten oder Punkte | R0-29 D29-2 | R1-35, R1-44, R3-08 |
| E-233 | Massenimport: Konten anlegen oder Plätze direkt zuweisen? | **Nur Einladungen erzeugen**, keine Konten | R0-29 D29-3 | R1-35, R1-44, R3-08 |
| E-234 | Umbuchen eines Platzes ohne neuen Claim (Pro) | **Nur innerhalb derselben Stammgruppe** | R0-29 D29-4 | R1-35, R1-44, R3-08 |
| E-235 | Sammel-Neuausstellung von Zertifikaten (Pro) | **Layout-Revision**; der alte Link bleibt gültig und zeigt die neue Fassung | R0-29 D29-5 | R1-35, R1-44, R3-08 |
| E-236 | Öffentliche Verifikation: Initialen und Ergebnis anzeigen? | **Nein**, weder Initialen noch Ergebnis (präzisiert E-97) | R0-29 D29-6 | R1-35, R1-44, R3-08 |
| E-237 | Wer ernennt Delegierte einer Gruppe (Pro)? | **Besitzer und Admins** | R0-29 D29-7 | R1-35, R1-44, R3-08 |
| E-238 | PDF-Technik und externer Verifikationsdienst | **Kein externer Dienst.** Findet der Technik-Spike keine Bibliothek für getaggte PDFs, liefert Pro kein PDF (HTML/Druck bleibt) | R0-29 D29-8 | R1-35, R1-44, R3-08 |

## 36. Datenschutz- und Sicherheitsdefaults (aus R0-31)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| **E-239** | Phase und Task für das Härtungsmodul (Beschluss E-10) | **Neue Task R1-45 „Härtungsmodul“ in Free** | R0-31 D31-1 | R1-34, R1-45 |
| E-240 | Standardmodus der Härtungsfunktionen | **`auto`**: hält sich vollständig zurück, wenn ein Security-Plugin erkannt wird; sonst aktiv. Je Funktion `auto | force | off` | R0-31 D31-2 | R1-34, R1-45 |
| E-241 | Umfang über E-10 hinaus | **Registrierungslimit und Geräte-Cookie ja**; Schutz gegen User-Enumeration nur als Opt-in | R0-31 D31-3 | R1-34, R1-45 |
| E-242 | Notausgang, wenn das Login fail-closed sperrt | **Konstante `COURSE_CAPTAIN_HARDENING` in wp-config.php** schaltet ab; bekannte Geräte (Cookie) dürfen weiter | R0-31 D31-4 | R1-34, R1-45 |
| E-243 | Fristen für Daten ohne bisherige Regel | **Beendete Grants 10 Jahre, entfernte Plätze 365 Tage, geschlossene Gruppen 3 Jahre, aufgehobene Lernsperren 30 Tage, Exportdateien 24 h** | R0-31 D31-5 | R1-34, R1-45 |
| E-244 | Definition „Kursende“ (Fristbeginn in E-51) | **Späterer von: letzter aktiver Grant endet oder letzte Aktivität im Kurs** | R0-31 D31-6 | R1-34, R1-45 |
| E-245 | Frist über den Default hinaus verlängern | **Nur mit Pflichtbegründung und Audit** | R0-31 D31-7 | R1-34, R1-45 |
| E-246 | Consent-Kategorie für externe Medien (R0-10 konfigurierbar, R0-27 ohne Feld) | **Neues Feld `privacy.consent_category`**, Default `marketing` | R0-31 D31-8 | R1-34, R1-45 |

## 37. Kurs-SEO (aus R0-33)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-247 | Öffentliche, von Suchmaschinen indexierbare Lektionen in R1? | **Nein**; Schnupperzugang (E-15) erfordert Login | R0-33 D33-1 | R1-37 |
| E-248 | Strukturierte Daten (schema.org Course) standardmäßig? | **Aus**, einschaltbar | R0-33 D33-2 | R1-37 |
| E-249 | Kurs-Sichtbarkeit „nicht gelistet“ | **Einführen**, Default „gelistet“ | R0-33 D33-3 | R1-37 |
| E-250 | URL eines archivierten Kurses | **404** | R0-33 D33-4 | R1-37 |
| E-251 | Öffentliche Kategorie-/Schlagwort-Archive in R1? | **Nein** | R0-33 D33-5 | R1-37 |
| E-252 | Trainername auf der Kursseite und im Schema | **Standardmäßig aus**, je Kurs einschaltbar | R0-33 D33-6 | R1-37 |
| E-253 | Fremde Suchindex-Plugins (z. B. Relevanssi) | **Course-Captain-Typen über deren Filter ausschließen** plus Systemstatus-Warnung; Relevanssi getestet, andere best effort | R0-33 D33-7 | R1-37 |

## 38. Integrationsstatus (aus R0-34)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-254 | Lesende Integrationsanzeige in R1: Systemstatus (E-185) oder Unterseite „Integrationen“ (E-202)? | **Unterseite schon in R1, nur lesend** | R0-34 D34-1 | R1-38, R3-10, R3-16 |
| E-255 | Wer darf den Integrationsstatus sehen? | **Auch `cc_support` und Kursmanager** (lesend) | R0-34 D34-2 | R1-38, R3-10, R3-16 |
| E-256 | Partner ohne veröffentlichten Vertrag bzw. nicht installierte Partner anzeigen? | **Erkannte Partner anzeigen**, mit Hinweis „kein Vertrag verfügbar“; nicht installierte nicht bewerben (E-100) | R0-34 D34-3 | R1-38, R3-10, R3-16 |
| E-257 | Alarm-Mail an Admins, wenn eine Integration `failed` ist (R3) | **Ja**, gebündelt höchstens einmal je 24 h | R0-34 D34-5 | R1-38, R3-10, R3-16 |
| E-258 | Offene (noch nicht zugestellte) Zustellungen und Aufbewahrungsfristen | **Offene Zustellungen höchstens 30 Tage**, danach Dead-Letter mit Grund `retention` | R0-34 D34-6 | R1-38, R3-10, R3-16 |
| E-259 | Übernahme einer Course-Aktion durch einen Partner (z. B. Workflow übernimmt einen Mailtyp) | **Erst in R3**, nur mit sichtbarem Hinweis im Admin und Abschaltmöglichkeit | R0-34 D34-8 | R1-38, R3-10, R3-16 |

## 39. Admin-Menü und Setup (aus R0-30)

D30-1 (Task R1-45 für die Härtung) ist identisch mit E-239.

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-260 | Härtung: Standard ohne Bestätigung des Admins (Widerspruch zu E-240 `auto`) | **Alles aus, bis der Admin im Assistenten oder Tab speichert**; der Assistent schlägt „an“ vor (außer bei erkanntem Security-Plugin), danach gilt `auto` (E-240) | R0-30 D30-2 | R1-32, R1-45 |
| E-261 | Umfang der Härtung in R1 | **Login-Limit, Reset-Limit, bekanntes Gerät, XML-RPC, 4 Security-Header**; übrige LB-Funktionen (Enumeration, Anwendungspasswörter) in den R4-Pool (ergänzt E-241) | R0-30 D30-3 | R1-32, R1-45 |
| E-262 | Ort der Härtungs-Einstellungen | **Eigener 11. Tab „Website-Härtung“** | R0-30 D30-4 | R1-32, R1-45 |
| E-263 | Aussperren bei fail-closed, wenn der Zählerspeicher ausfällt | **Login auf bekannten Geräten bleibt möglich** + Notausschalter per Konstante und WP-CLI (ergänzt E-242) | R0-30 D30-5 | R1-32, R1-45 |
| E-264 | Fehlende Menüplätze (Kurs-FAQ, Tipps, Medien, Protokoll, Rechte) | **Bereichsnavigation innerhalb bestehender Menüpunkte**; Protokoll unter Systemstatus | R0-30 D30-6 | R1-32, R1-45 |
| E-265 | Menüpunkt „Gruppen“ bei ausgeschalteten Gruppen | **Ausblenden**, solange aus und keine Gruppe existiert | R0-30 D30-7 | R1-32, R1-45 |
| E-266 | Setup abschließen trotz offener Blocker (z. B. Dateischutz) | **Erlaubt** mit Status „abgeschlossen mit Hinweisen“; Blocker bleiben sichtbar, geschützte Medien werden weiter nicht ausgeliefert | R0-30 D30-8 | R1-32, R1-45 |
| E-267 | Einschreibemodus „öffentlich“ (ganze Kurse ohne Konto/Grant, Kap. 16.1) | **Entfällt in R1**: nur „manuell“ und „Selbstanmeldung“; Gäste sehen nur den Schnupperbereich (E-15) | R0-30 D30-9 | R1-32, R1-45 |

## 40. Lizenzclient-Grenze (aus R0-39)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-268 | Werden Lösungserklärungen (Pro, E-20) nach Lizenzende weiter ausgeliefert? | **Ja, eingefroren** wie bei E-94; neue Erklärungen lassen sich nicht mehr anlegen | R0-39 D39-1 | R3-08, R3-15, R3-18 |
| E-269 | Erklärungsfeld im Free-Frageneditor | **Ausblenden**, vorhandene Texte bleiben gespeichert | R0-39 D39-2 | R3-08, R3-15, R3-18 |
| E-270 | Lizenz `suspended`/`revoked` | **Wie `expired`** behandeln (eingefroren) | R0-39 D39-3 | R3-08, R3-15, R3-18 |
| E-271 | Automatisch angelegte Woo-Produkte (E-34), wenn Pro deaktiviert wird | **Standardmäßig auf „privat“ setzen**, sonst kann gekauft werden, ohne dass ein Zugang entsteht | R0-39 D39-4 | R3-08, R3-15, R3-18 |
| E-272 | Signatur der Pro-Pakete (E-190): License Captain signiert die Update-Antwort samt SHA-256, nicht das ZIP | **Signierte Antwort + SHA-256 erfüllt E-190**, ergänzt um eine Build-Attestierung | R0-39 D39-5 | R3-08, R3-15, R3-18 |
| **E-273** | Wo wird das Pro-Add-on gebaut? | **Eigenes privates Repository `course-captain-pro`** (captain-pro ist für mich nicht prüfbar); alternativ in captain-pro, wenn du Zugriff gibst | R0-39 D39-6 | R3-08, R3-15, R3-18 |
| E-274 | Welche Free-Versionen unterstützt Pro? | **Aktuelle und vorige Free-Minor** | R0-39 D39-7 | R3-08, R3-15, R3-18 |

## 41. Finanzielle Suite-Ereignisse (aus R0-40)

| ID | Frage | Empfehlung | Herkunft | Blockiert |
|---|---|---|---|---|
| E-275 | Setzt ein verlorener Zahlungs-Dispute (Webhook) den Woo-Bestellstatus? | **Ja** (auf `refunded`/`cancelled`), damit auch Affiliate die Provision storniert | R0-40 D40-1 | R3-02…R3-05, R3-19 |
| E-276 | 24-h-Pending-Timeout (E-201) bei unbezahlten Woo-Bestellungen (z. B. Überweisung) | **Gilt nicht**; Grant entsteht erst bei Zahlung, so lange die Bestellung offen ist | R0-40 D40-2 | R3-02…R3-05, R3-19 |
| E-277 | Gutschein-Einlösung hängt länger als 24 h | **Reservierung freigeben, Grant `failed`**, Lernender bekommt Hinweis zum erneuten Einlösen | R0-40 D40-3 | R3-02…R3-05, R3-19 |
| E-278 | Guthaben (Restwert) über die Einlöse-API? | **Nein**, Kursgutscheine sind Zugangsgutscheine | R0-40 D40-4 | R3-02…R3-05, R3-19 |
| E-279 | Kursgutscheine zusätzlich im Woo-Warenkorb einlösbar? | **Nein**, nur über die Course-Einlösung | R0-40 D40-5 | R3-02…R3-05, R3-19 |
| E-280 | Preis automatisch angelegter Woo-Produkte (E-34) | **Pflegt der Shop in Woo**; Produkte entstehen als Entwurf | R0-40 D40-6 | R3-02…R3-05, R3-19 |
| E-281 | Mitgliederrabatt mit Rabattcodes kombinierbar? | **Nein**, der bessere Rabatt gilt | R0-40 D40-7 | R3-02…R3-05, R3-19 |
| E-282 | Kursprodukte einzeln oder als Menge (mehrere Plätze) verkaufen? | **Einzeln**; mehrere Plätze nur über Gruppenprodukte | R0-40 D40-8 | R3-02…R3-05, R3-19 |
| E-283 | Manuelle Support-Aktion „Gutscheinwert zurückgeben“ | **Ja**, mit Begründung und Audit | R0-40 D40-9 | R3-02…R3-05, R3-19 |
| E-284 | Lernbeginn als Vermerk an der Woo-Bestellposition (Widerrufsrecht) | **Ja**, Zeitpunkt des ersten Lernzugriffs wird vermerkt | R0-40 D40-10 | R3-02…R3-05, R3-19 |

## MINVER-1. Einheitliche Mindestversionen der Captain-Plugins (17.11.2026)

| ID | Frage | Beschluss | Herkunft | Wirkung |
|---|---|---|---|---|
| **MINVER-1** | Mindestversionen für alle Captain-Plugins, Pro-Add-ons und Connectoren | **PHP 8.5+** (8.6 experimentell in der CI), **WordPress 7.0+**, WooCommerce 11.2.2+ (wo genutzt, R3; 11.2.0 übernimmt Bestell-/Zahlungsdaten eines früheren Store-API-Checkouts im selben PHP-Prozess, behoben in 11.2.2), **MySQL 9.7+ / MariaDB 12.3+**; ältere Datenbankserver ab der harten Untergrenze (MariaDB 10.6 / MySQL 8.0, E-08) nur mit Warnung im Systemstatus, keine Sperre | Inhaberin, 17.11.2026 (suiteweit abgestimmt) | **Nachtrag 17.11.2026:** MariaDB ≥ 11.6.2 hat `innodb_snapshot_isolation = ON` als Standard (Lesen + späteres `SELECT … FOR UPDATE` auf eine geänderte Zeile → Fehler 1020, Rollback); `Database::transaction()` schaltet die Variable je Verbindung aus und wiederholt 1020 wie einen Deadlock (MySQL 9.7 nicht betroffen). Ersetzt E-195 (PHP 8.3) und die „unterstützt ab“-Werte aus E-58 (MariaDB 10.11 / MySQL 8.4); R0-20 §1.2 und R0-18 §1.3 sind entsprechend zu lesen. Umsetzung: `Requirements`, Plugin-Kopf, `readme.txt`, `composer.json`, PHPCS/PHPStan-Zielversion, CI-Matrix (M-Z, M-U, M-M9, M-M best effort, J-17b PHP 8.4) |
