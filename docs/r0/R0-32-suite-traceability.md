# R0-32 · Suite-Traceability-Matrix Form / Workflow / Review Captain

**Status:** Ergebnis der Quellprüfung, **nicht abgehakt** (DoD verlangt zusätzlich Review/Freigabe; Tests sind für eine Dokumentenprüfung nicht anwendbar) · **Stand:** 16.11.2026
**Bezug:** TODO R0-32 (Voraussetzung R0-19, R0-24), Konzept Kap. 8, 18, 30, 31, 32, 44; Folgeaufgaben R0-33, R0-34, R0-35, R1-37…39, R3-07, R3-12, R3-16, R3-17
**Fortsetzung:** Pass/Voucher/Affiliate/License/Captain Pro in [R0-36-suite-vertragsdeltas.md](R0-36-suite-vertragsdeltas.md)

## 1. Quellbasis und Art der Quellen

| Repository | Produkt | Commit (voll) | Commit-Datum | Inhalt im Clone |
|---|---|---|---|---|
| `hastla007/wp-formmaster` | Form Captain | `97cbe73bbef63777a08de35c687f6972d3d855cc` | 15.11.2026 23:30 +0100 | `README.md` (leer, 0 Zeilen), `TODO.md`, `konzept.md` (673 Z.), 8 SVG-Mockups |
| `hastla007/wp-workflowmaster` | Workflow Captain | `e5f41e67e3c0a58500be18a9a4fe8a25f0ac0de5` | 15.11.2026 23:30 +0100 | `README.md` (leer), `TODO.md`, `konzept.md` (1233 Z.), 9 SVG |
| `hastla007/wp-reviewmaster` | Review Captain | `33d1362f1bb571f70aef301d83c3fdf30d35bdfd` | 15.11.2026 23:31 +0100 | `README.md` (9 Z.), `TODO.md`, `konzept.md` (950 Z.), 4 SVG |

**Codeprüfung (Auftragspunkt „enthalten die Repos Code?“):** Nein. Die Dateizählung ohne `.git` ergibt je Repo ausschließlich `*.md` (3) und `*.svg`; es gibt **keine** PHP-, JS- oder Testdateien. Damit existieren für diese drei Produkte **keine realen Hooks, REST-Routen oder Provider-Interfaces**, die mit Pfad:Zeile im Code belegt werden könnten. Alle unten genannten Namen sind **Vorschläge der Konzepte**; die Konzepte sagen das selbst:

- Form: „ein im Konzept benannter zukünftiger Hook ist bis zur Implementierung kein vorhandener Vertrag“ – `wp-formmaster@97cbe73:konzept.md:487`.
- Workflow: „Vorgeschlagene öffentliche PHP-Schnittstelle (Namen sind Entwurf …)“ – `wp-workflowmaster@e5f41e6:konzept.md:123`.
- Review: „Die Integrationsmatrix beschreibt Zielverhalten; sie bedeutet nicht, dass jeder Adapter bereits gebaut ist“ – `wp-reviewmaster@33d1362:konzept.md:813`; Form-Vertrag „nicht als implementierte Schnittstelle zu behandeln“ – ebd. `:818`.

**Course Captain wird in keinem der drei Repos erwähnt.** Suche nach `course captain|course-captain|course_captain|coursemaster` über alle sieben Suite-Clones: 0 Treffer. Die Konzepte kennen nur den Kundenconnector **LearnBoating** (Form `konzept.md:320, 336-352`; Workflow `konzept.md:922-956`). Jede Course-Integration ist daher auf Partnerseite **nicht geplant** und bräuchte nach Konzept Kap. 30.1 („Keine Fremdrepo-Änderung“) einen separaten Auftrag.

**Abgleich mit dem bisherigen Stand:** Kap. 44 nennt als gelesene Abschnitte Form §6/6.1/6.2, Workflow §5/8/9/15/18 und Review §7.5/8.9/11/18. Diese Matrix erweitert das um Form §5/§7/Suite-Abgleich, Workflow §4/§16/§17.11 und Review §7.1–7.10, weil dort die eigentlichen Vertragsmuster stehen.

## 2. Legende

- **Relevanz:** *hoch* = Course-Vertrag oder R0-Spezifikation muss es abbilden; *mittel* = übertragbares Betriebs-/UX-Muster; *keine* = fachfremd, nur zur Abgrenzung gelistet.
- **Abdeckung:** Kapitel aus `konzept.md` bzw. Task-ID aus `TODO-WETTBEWERBSANALYSE.md`, wo das Muster bereits steht.
- **Delta:** was in Course-Spezifikationen noch fehlt (Arbeitsauftrag an die genannte Task, keine neue Task-ID).
- Quellangaben in der Form `Datei:Zeile` beziehen sich auf den Commit aus Abschnitt 1.

## 3. Matrix Form Captain (`wp-formmaster@97cbe73`)

| # | Quelle (Datei, Abschnitt) | Inhalt / Muster | Relevanz | Abdeckung konzept.md / TODO | Delta | Expliziter Nichtumfang |
|---|---|---|---|---|---|---|
| F1 | `konzept.md:179-190` §5 Leitprinzip, Gemeinsame Verträge | Versionierte öffentliche API mit Capability-Checks und Fehlercodes; Aktionen erst nach Commit mit minimalem Payload; Idempotency-Key; Integrationsdiagnose | hoch | Kap. 8 (Prinzip), 18, 30.3; R0-19, R0-34 | Keines in der Sache. | Form-Felder, Spamregeln |
| F2 | `konzept.md:192-204` §5 Vertrag v1 | `FORM_CAPTAIN_CONTRACT_VERSION = 1.0.0`; Familie `form_captain_*_v1`; Registrierung erst nach `form_captain_loaded_v1`; inkompatible Major → Adapter deaktiviert, Code `incompatible_contract`; Admin-Menü-Hook `form_captain_admin_menu_built_v1($parent_slug)` (Z. 198); Ergebnisobjekt `success/code/…/request_id`, **erwartete Validierungs-/Berechtigungsfehler als Ergebnis** (Z. 201) | hoch | Kap. 18 übernimmt Konstante, `course_captain_loaded_v1`, `incompatible_contract`, `request_id` | (a) Course fehlt ein Admin-Menü-Erweiterungspunkt für Pro/Adapter analog Z. 198 (R0-19). (b) Kap. 18 nennt „Rückgabe … oder `WP_Error`“, Form trennt erwartete Fehler (Ergebnis) von technischen – Course muss festlegen, welche Codes Ergebnis vs. `WP_Error` sind (R0-19). | – |
| F3 | `konzept.md:208` Integrationen, Zeile License | Nur `form-captain-pro` nutzt den License Client; Kern lizenzfrei; Lizenzseite unter dem Produktmenü | hoch | Kap. 6, 30.2; R0-39, R3-18 | Keines (Details R0-36 §5). | – |
| F4 | `konzept.md:254-263` Festgelegte Form→Review-Brücke | Herkunft `source_plugin`/`source_entry_id`, Idempotency `form-captain:{site_id}:{entry_id}`, Standard „nicht verifiziert“, Formularwert kann nie Kauf/Teilnahme belegen, Entry löst keine Einladung aus | mittel | Kap. 18.2 („Einreichung ≠ automatische Freigabe“), 30.2 Form-Zeile | Course-Regel ergänzen: Ein Formular-Entry kann **nie** Abschluss, Prüfungsergebnis oder Zertifikat setzen (bisher nur Enrollment-Freigabe geregelt) – R0-34/R3-07. | Form→Review selbst |
| F5 | `konzept.md:274-300` Kundenspezifische Projekt-Connectoren | Connector = eigenes Plugin; Registry `form_captain_connectors_v1` (Z. 290); Opt-in je Site, standardmäßig aus (Z. 292); Outbox, Backoff, Dead-letter nach 8 Versuchen (Z. 294); Connector-Vertrag/Registry im **Free**-Kern, Connector darf einfache Aktionen nicht an Pro koppeln (Z. 297) | hoch | Kap. 18.1 (Connector-SDK), 30.3 | Entscheidung Free/Pro für Course-Provider-Vertrag fehlt (siehe Abschnitt 8, E1). Retry-Obergrenze/Dead-letter-Schwelle für Course-Outbox nicht beziffert (R0-21/R0-34). | – |
| F6 | `konzept.md:336-352` LearnBoating-Kursfrage | Lernplugin liefert versionierten **Kurskontext-Vertrag** (Vorschlag `bootspruefung_form_context_v1`) mit nur `course_code`, `label`, `permalink`, `status` (Z. 340); ausdrücklich ausgeschlossen: Lernzugang, Prüfung, Gutschein, Affiliate, Nutzeranlage (Z. 346) | hoch | Kap. 30.2 Form-Zeile („Kurs-/Lektions-/**Lernenden**-Kontextauflösung“), 30.4 Rezept 2 | Course braucht einen öffentlichen, **nur lesenden** Kurskontext mit festem Public-Scope (R0-19). Kap. 30.2 geht mit „Lernenden-Kontext“ über das Form-Muster hinaus; Scope und Datenklasse sind in R0-34 zu begrenzen. | Kursfrage als Ticket/Inbox |
| F7 | `konzept.md:354-385` §6, §6.1 Admin/Settings | Integrationen als eigener Menüpunkt; Settings-Tabs; Systemstatus ohne Feldwerte/IP/Secrets | mittel | Kap. 16, 43; R0-30, R0-31, R0-34 | Keines. | Formular-Editor |
| F8 | `konzept.md:387-409` §6.2 Yoast | Formular hat **keine** eigene URL/Sitemap/Schema; Seite ist das indexierbare Dokument; kein Noindex wegen Formular; Shortcode-Analyse nur über `yoast.analysis.shortcodes` | mittel | Kap. 21 (Z. 534-535); R0-33, R1-37 | Muster nur teilweise übertragbar: Course **hat** eigene öffentliche Kurs-URLs. R0-33 muss für eingebettete Course-Blöcke/Shortcodes auf fremden Seiten dieselbe Regel festlegen (keine private Ausgabe in Yoast-Analyse). | Form-SEO-Modell als Ganzes |
| F9 | `konzept.md:420-434` §7 Datenmodell/Leitplanken | Eigene Tabellen statt Post je Antwort; Multisite-Entscheidung vor MVP; Uninstall nur nach expliziter Auswahl; WordPress.org: Lizenz/Updater/Pro-Code nur im Add-on (Z. 434) | mittel | Kap. 9, 22; R0-06, R0-35, R0-39 | Keines. | – |
| F10 | `konzept.md:477-488` Suite-Abgleich 15.11.2026 | Tabelle der realen Partnerverträge – **ohne Course Captain** | hoch (Lücke) | Kap. 30.1 (keine Fremdrepo-Änderung) | Partnerseitige Aufgabe fehlt; bis zu einem Auftrag darf Course keine Form-Integration als geplant/kompatibel ausweisen (R3-07). | Änderung an wp-formmaster |

## 4. Matrix Workflow Captain (`wp-workflowmaster@e5f41e6`)

| # | Quelle (Datei, Abschnitt) | Inhalt / Muster | Relevanz | Abdeckung konzept.md / TODO | Delta | Expliziter Nichtumfang |
|---|---|---|---|---|---|---|
| W1 | `konzept.md:97-105` §4 Lizenzvertrag | Free ohne Lizenzcode (Z. 100); bei Lizenzablauf Rezepte/Logs lesbar, neue Pro-Runs starten nicht, geplante Pro-Aktionen **pausiert und nicht automatisch nachgeholt** | hoch | Kap. 6 („neue Pro-Schreibaktionen gesperrt“), 18.2; R0-39, R3-15 | Course-Regel für **geplante** Pro-Jobs (Drip, Reminder) bei Ablauf/Erneuerung fehlt: pausieren + Vorschau statt Nachholen (R0-39). | – |
| W2 | `konzept.md:109-119` §5 Event-/Action-Provider | Trigger-, Action-, Condition-Provider mit Schema, Mindestversion, Datenschutzklasse, Retry/Idempotenz; Adapterstatus; Contract-SemVer; Provider liest nie fremde Tabellen (Z. 119) | hoch | Kap. 8 (Adapter-Handshake), 30.3; R0-19, R0-34, R3-12 | Course-Providerbeschreibung je Event/Action (Titel, Schema, Privacy-Klasse, Seiteneffekt) als Pflichtspalten in R0-19. | – |
| W3 | `konzept.md:121-129` Event-Vertrag | Registry-Vorschlag `workflow_captain_register_providers` (Z. 125); Envelope `event_id, event_key, occurred_at_utc, site_id, actor_user_id, object_type, object_id, schema_version, payload, privacy_tags` (Z. 127); Retry mit gleichem Idempotency-Key | hoch | Kap. 18, 30.3 | Feldnamen weichen ab (Abschnitt 6). | – |
| W4 | `konzept.md:131-159` Kundenspez. Connectoren | Lokaler Connector über `workflow_captain_connectors_v1` (Z. 143); externe Verbindungen nur HMAC-SHA256, Replay-Fenster, Host-Allowlist; Aktivierung nur mit Betreiberfreigabe | mittel | Kap. 18.1, 30.3 Transport | Keines. | n8n, externe Apps |
| W5 | `konzept.md:161-172` Ausführungsmodell | Event zuerst persistieren, Lease, Fehlerklassen, Replay = neuer Run mit gleicher Idempotenz, Schleifenerkennung über Korrelations-ID | hoch | Kap. 18 Outbox, 31.3 | Envelope braucht ein Kausalitätsfeld gegen Schleifen Workflow→Course→Workflow (`causal_run_id`, Z. 728); fehlt in Kap. 18/30.3 (R0-19). | – |
| W6 | `konzept.md:289-311` §8.6/8.7 Integrationen/Settings | Kacheln mit Mindestversion/Vertrag/letzter Prüfung; Secrets nur im Verbindungsdetail; Notfall-Pause getrennt | mittel | Kap. 31.4, 43; R0-34, R3-16 | Keines. | – |
| W7 | `konzept.md:321-332` §9 Rechte/Datenschutz | Eigene Capabilities, `permission_callback`, Token-Datenklassen, kein Versand abhängig von positiven Reviews | mittel | Kap. 17, 20, 36 | Keines. | – |
| W8 | `konzept.md:343-353` §10 Yoast | Interne Daten nie öffentlich (kein CPT/REST/Sitemap/Schema) | mittel | Kap. 21; R0-33 | Keines (Course-Lernstände, Grants, Runs analog privat). | – |
| W9 | `konzept.md:507` §15.3 | „Order bezahlt“ und „Voucher ausgestellt“ sind getrennte Quellen; Rezept mit beiden braucht explizite Deduplizierung | hoch | Kap. 8 („kein doppeltes Verarbeiten“), 32.2 Doppelquellen | Course muss festlegen, welches Event für Zugangs-Onboarding **kanonisch** ist (Muster LearnBoating: Entitlement-Event, `konzept.md:944`) – R0-34/R3-17. | – |
| W10 | `konzept.md:607-617` §15.6 Provider-Verträge | Pro Provider: SemVer, Mindestversion, Schemas, Datenschutzklassen, Capability, Retry/Idempotenz, Fehlercodes, **Beispielpayload, Migrationsanleitung, Tests, Changelog**; „vor Commit“ muss ausdrücklich markiert sein | hoch | R0-19 (Hooks/REST/Fehlercodes/Deprecation) | R0-19-Tabelle um Beispielpayload, Datenschutzklasse, Migrationshinweis und Commit-Zeitpunkt ergänzen. | – |
| W11 | `konzept.md:635-659` §15.8/15.9 | Free-ZIP vollständig ohne Lizenzkontakt; Release erst mit Tests zu doppelten Hooks, Refunds, Lizenzablauf, Restore | hoch | Kap. 6, 23; R0-35, R1-39, R3-18 | Keines. | – |
| W12 | `konzept.md:703-712` §16.1 | Fachplugin bleibt ohne Workflow lauffähig; Fachplugin-Pro-Funktion braucht **zwei** Entitlements (Workflow + Fachplugin) | hoch | Kap. 5, 6, 8 | Ob Course-Events/Read-API Free sind oder an `course.suite_integrations` hängen, ist offen (E1). | – |
| W13 | `konzept.md:714-737` §16.2 | Envelope mit `source_plugin/source_version/contract_version`, `object_ref`, `correlation_id`, `privacy_tags`, `causal_run_id`; Empfänger nur über Resolver zur Laufzeit; Source-Outbox oder nachholbare Abfrage (Z. 737) | hoch | Kap. 18 Outbox, 30.3 („nachholbare Abfrage“) | **Empfänger-Resolver** (`user_ref` → Kontakt, minimal berechtigt) fehlt in Course (R0-19/R0-34). | – |
| W14 | `konzept.md:739-747` §16.3 Commands | Typisierte Commands im datenbesitzenden Plugin; Erfolg erst nach Annahme; Seiteneffektklasse, Idempotenz, Capability, Entitlement je Aktion; finanzielle/irreversible Aktionen Extra-Capability (Z. 746) | hoch | Kap. 18 Write-API | Course-Write-API je Command: Seiteneffektklasse, Retryfähigkeit, Capability, Entitlement ergänzen (R0-19). | – |
| W15 | `konzept.md:749-759` §16.4 Laufzeitmatrix | Verhalten je Installationszustand; inkompatible Major pausiert nur betroffene Runs | hoch | Kap. 30.3 Zustände, 33; R3-17 | Keines (R3-17 deckt es). | – |
| W16 | `konzept.md:761-806` §16.5 Multisite-Netzwerk | Cross-Site-Flows mit Netzwerktabellen, Site-Opt-in, Saga | keine (vor R4) | Kap. 30.1 (R4) | – | Netzwerkweite Course-Flows |
| W17 | `konzept.md:922-956` §17.11 LearnBoating-Connector | Events `learning.entitlement.changed`, `learning.group.member.changed`, `learning.exam.completed`; Fortschritt **nicht** je Item; **„Nicht als Workflow-Aktion exponieren: … Lernzugang direkt anlegen, verlängern oder widerrufen“** (Z. 942) | hoch | Kap. 8 schlägt Aktionen `course.enroll`, `course.revoke` vor; Kap. 30.2 „optionale Actions enroll/revoke“ | Spannung zu Kap. 8/30.2 → Widerspruch WS2, Entscheidung E2. Fortschrittsereignis-Granularität: Kap. 18 hat `lesson_completed_v1` – Datensparsamkeit begründen (R0-34). | Kundenconnector LearnBoating |
| W18 | `konzept.md:1000-1044` §18.1–18.4 Ownership | Kein stiller Takeover; Modi plugin-eigen/ergänzend/Workflow übernimmt; native Aktion wird erst nach **dauerhafter Annahme** unterdrückt; Hookfamilien-Vorschlag `captain_suite_event_v1`, `captain_suite_automation_claim_v1` (Z. 1035); Fallback bei Lizenzende | hoch | Kap. 8 („optionaler Ownership-Handshake“), 18 („eindeutig festlegen …“); R3-17 | Course-Benachrichtigungen (Kap. 19) brauchen Claim-Semantik und die Hookfamilie; beides fehlt in konzept.md (R0-34, R3-17). | – |
| W19 | `konzept.md:1053-1063` §18.6 Vertragslage | Reale Lage je Suite-Plugin – **ohne Course Captain** | hoch (Lücke) | Kap. 8 Tabelle (Workflow-Repo kennt nur LearnBoating) | Partnerseitige Provider-Aufgabe fehlt (R3-07). | Änderung an wp-workflowmaster |
| W20 | `konzept.md:1219-1229` §19.15 Yoast | Keine Workflow-SEO-Ausgabe | keine | Kap. 21 | – | – |

## 5. Matrix Review Captain (`wp-reviewmaster@33d1362`)

| # | Quelle (Datei, Abschnitt) | Inhalt / Muster | Relevanz | Abdeckung konzept.md / TODO | Delta | Expliziter Nichtumfang |
|---|---|---|---|---|---|---|
| V1 | `konzept.md:226-233` §7.1 | Ein Ereignis, ein Besitzer; keine Tabellenkopplung; Referenzen statt Kopien; asynchron/idempotent; Opt-in je Verbindung; kein Review-Gating | hoch | Kap. 18.2 Review-Regel, 31.5 | Keines. | Reviewverwaltung |
| V2 | `konzept.md:235-245` §7.2 Integrationsmatrix | Woo, Voucher, Pass, Affiliate, Form, License, WP-Kern – **kein Course Captain** | hoch (Lücke) | Kap. 30.2 Review-Zeile | Course-Weg nur über das Connector-Framework (V8) oder einen neuen Review-Adapter (Fremdrepo) – E3. | – |
| V3 | `konzept.md:288-323` §7.4 Ereignisvertrag | Vorschläge `wp_review_captain_experience_event_v1` (`experience_created/completed/cancelled/refunded`), `…_request_v1`, `…_widget_context_v1`, `…_review_summary_v1`; Payload `contract, source, event_id, experience_id, event_type, occurred_at, object_type/object_id, recipient_ref, locale, consent/communication_allowed, context`; „unbekannt bedeutet nicht erlaubt“ (Z. 320); Kontakt erst zur Versandzeit über Resolver (Z. 323) | hoch | Kap. 18.2 (experience_id gehört Course, Kontaktresolver/Opt-out), 30.2 | Course muss `experience_id`-Format, `recipient_ref`, `locale` und **Quelle des Kommunikationsstatus** spezifizieren; Resolver fehlt (siehe W13). Ohne Consent-Angabe versendet Review nicht – E4. | – |
| V4 | `konzept.md:325-333` §7.5 Laufzeit/Diagnose | Sperre je Verbindung/Erlebnis, Backoff, `Retry-After`; Statusseite: Version, Vertrag, letzte Ereignisannahme, wartende Jobs, letzte Fehler-ID; `incompatible_contract` ohne Fatal; Deaktivieren nimmt nichts zurück | hoch | Kap. 30.3 Integrationszustände; R0-34, R3-16 | Keines (R0-34-DoD deckt es). | – |
| V5 | `konzept.md:348-357` §7.7 Grenzen | Lizenzserver erhält keine Profil-/Bestell-/Review-Daten; Form-Entry nie Einladungsauslöser | mittel | Kap. 6 (keine Teilnehmerdaten an Lizenzserver) | Keines. | – |
| V6 | `konzept.md:378-437` §7.9 Festlegungen | Max. **eine Einladung je Erlebnis + Empfänger**; Erlebnis = tatsächliche Nutzung, nicht Kauf; Quellplugin erzeugt `experience_id` aus Site-ID + Source-Key + Quellobjekt; **widerrufende Ereignisse** (Refund, Absage, Nichtteilnahme, Opt-out) stoppen offene Anfragen; nach Restore Reconciliation ohne Doppel-Einladung | hoch | Kap. 18.2, 31.3 Pkt. 2, 31.6 | (a) Course-Event für **Widerruf eines Abschlusses/Zertifikats** fehlt in Kap. 18 (nur `enrollment_revoked_v1`; Kap. 30.2 nennt `certificate.revoked`) – R0-19. (b) Restore-Regel für Outbox (keine alten Events erneut zustellen) – R0-16/R0-37. | Einladungslogik |
| V7 | `konzept.md:438-503` §7.10 Connector-Framework | Fähigkeiten **Experience Source** (Beispiel „abgeschlossener Kurs“, Z. 456), **Recipient Resolver** (Z. 457), Context Provider; Registry `wp_review_captain_connectors_v1` (Z. 468), `ReviewCaptain\Contracts\ConnectorInterface` | hoch | Kap. 18.1, 30.4 Rezept 3 | Course→Review wäre ein Experience-Source-/Resolver-Connector; Ort (Course Pro vs. separates Plugin) offen – E3 (R3-07). | – |
| V8 | `konzept.md:569-580` §8.9 Settings | Tabs inkl. Diagnose ohne Secrets | mittel | Kap. 43 | Keines. | – |
| V9 | `konzept.md:650-662` §11 Datenschutz | Rechtsgrundlage je Einladung, Secrets verschlüsselt, CSV-Formel-Injection | mittel | Kap. 20, 36 | Keines. | – |
| V10 | `konzept.md:811-822` Integrationsstatus-Abgleich | Reale Lage je Suite-Plugin – **ohne Course Captain** | hoch (Lücke) | – | wie V2. | Änderung an wp-reviewmaster |
| V11 | `konzept.md:906-950` §18 Yoast/Schema | Ein Schema-Graph, nur eine Canonical; externe Plattformbewertungen **nie** als `Review`/`AggregateRating` (Z. 921); eigene Produktreviews nur bei Richtlinienkonformität (Z. 923); kein paralleler JSON-LD bei inkompatiblem Yoast | hoch | Kap. 21 (Z. 535: `Course`-Schema nur für sichtbare, belegbare Daten, keine erfundenen Bewertungen) | R0-33: Course gibt **kein** eigenes `aggregateRating` aus; wenn Review Captain Kursbewertungen anzeigt, liegt Review-Markup bei Review Captain und hängt sich an den `Course`-Knoten im Yoast-Graph – keine Doppelausgabe. | Review-Widgets |

## 6. Querschnitt: Feldabgleich Ereignis-Envelope

| Bedeutung | Course Kap. 18 (`konzept.md:480`) | Course Kap. 30.3 (`konzept.md:850`) | Workflow §16.2 (`konzept.md:718-728`) | Review §7.4 (`konzept.md:312-321`) |
|---|---|---|---|---|
| Ereignis-ID | `event_id` | `event_id` | `event_id` | `event_id` |
| Typ | (Hookname) | `event_type` | `event_key` | `event_type` |
| Vertragsversion | `event_version` | `schema_version` | `contract_version` (+ `source_version`) | `contract` |
| Zeit | `occurred_at` | `occurred_at` (UTC) | `occurred_at_utc` | `occurred_at` |
| Quelle | `source` (Grant-Quelle) | – | `source_plugin` | `source` |
| Objekt | `course_id`, `reference_id` | `source_object_id`, `source_object_version` | `object_type`/`object_ref` | `object_type`/`object_id` |
| Person | `user_ref` | `actor_id` | `actor_user_id` | `recipient_ref` |
| Korrelation | `correlation_id` | `correlation_id` | `correlation_id`, `causal_run_id` | – |
| Idempotenz | `idempotency_key` | – | (über `event_id`) | (über `event_id`/`experience_id`) |
| Datenschutz | – | – | `privacy_tags` | `consent`/`communication_allowed`, `locale` |
| Erlebnis | – | – | – | `experience_id` |

**Befund:** Course hat zwei unterschiedliche Envelope-Entwürfe (WS1) und weder Datenschutzklasse noch Kausalitätsfeld. R0-19 muss **ein** kanonisches Envelope festlegen und eine dokumentierte Abbildung auf die Workflow- und Review-Felder liefern; die Partnernamen sind selbst nur Entwürfe.

## 7. Konsequenzen für Folgetasks (ohne neue Task-IDs)

- **R0-19:** ein Envelope (Abschnitt 6) inkl. `privacy_tags`-Äquivalent und Kausalitätsfeld; je Command Seiteneffektklasse/Capability/Entitlement (W14); Beispielpayload und Migrationshinweis je Vertrag (W10); Admin-Menü-Hook (F2); öffentlicher Kurskontext (F6); Widerrufsereignisse für Abschluss/Zertifikat (V6).
- **R0-33:** Regel „kein eigenes `aggregateRating`“, Zusammenspiel mit Review-Markup (V11); eingebettete Course-Ausgaben auf fremden Seiten (F8).
- **R0-34:** Empfänger-Resolver (W13, V3), kanonisches Onboarding-Event (W9), Ownership-Claim (W18), Retry-/Dead-letter-Schwellen (F5).
- **R0-35/R1-39:** keine Abweichung zu Form §7/Workflow §15.9 gefunden.
- **R3-07/R3-16/R3-17:** Partnerseitige Aufgaben existieren in keinem der drei Repos (F10, W19, V2/V10) – Integrationen bis zu einem Auftrag an die Partnerrepos nicht als geplant ausweisen.

## 8. Entscheidungsbedarf (Auftraggeber)

| ID | Frage | Empfehlung | Begründung / Quelle |
|---|---|---|---|
| E1 | Gehören Course-Ereignisse, Read-API und Connector-Registry in **Free**, oder hinter `course.suite_integrations`/`course.webhooks` (Kap. 6)? | **Free:** Ereignisse, Read-API, Registry und lokale Hooks; Pro nur für fertige Premium-Adapter und ausgehende Webhooks. | Form legt Vertrag/Registry in den Free-Kern und verbietet künstliche Pro-Kopplung (`wp-formmaster:konzept.md:297`); Workflow verlangt ohnehin das Workflow-Pro-Recht für Suite-Provider (`wp-workflowmaster:konzept.md:757`); Kap. 5 nennt „Automationen/Webhooks“ als Pro, aber nicht die Ereignisse selbst. |
| E2 | Darf Workflow Captain Course-Zugänge **anlegen/widerrufen** (Aktionen `course.enroll`/`course.revoke`, Kap. 8)? | Nur `course.enroll` mit eigener Grant-Quelle `workflow` (nie Commerce-/Voucher-Quellen ändern), `course.revoke` nur für selbst erzeugte `workflow`-Grants; v1 ggf. ganz ohne schreibende Aktionen starten. | Workflow-Konzept schließt für den LMS-Kundenconnector das direkte Anlegen/Widerrufen von Lernzugang aus (`konzept.md:942`) und hält Kernabläufe im Fachplugin (`konzept.md:1012-1024`). |
| E3 | Wo entstehen Course→Workflow-/Review-/Form-Connectoren: im Course-Pro-Add-on, in separaten Connector-Plugins oder in den Partnerrepos? | Course veröffentlicht nur die eigenen Verträge; Adapter als **separate Connector-Plugins** bzw. in Course Pro, sobald die Partner-Registries real existieren. | Alle Partner sehen Connectoren als eigene Plugins vor (Form `:288`, Workflow `:141-143`, Review `:440`); Registries sind unimplementiert (Abschnitt 1); Kap. 30.1 verbietet Fremdrepo-Änderungen ohne Auftrag. |
| E4 | Wer erhebt die **Kommunikationsberechtigung** für Bewertungsanfragen nach Kursabschluss? | Course erhebt **keine** Marketing-Einwilligung und liefert nur `user_ref` + Resolver; Review Captain prüft Rechtsgrundlage/Opt-out selbst. Rechtliche Prüfung vor R3. | Review: unbekannter Status = nicht erlaubt (`konzept.md:320`), Kontakt nur über Resolver (`:323`); Kap. 18.2 verlangt Kontaktresolver/Opt-out, nennt aber keinen Besitzer. |

## 9. Widersprüche zu konzept.md

| ID | Stelle in konzept.md | Widerspruch | Quelle |
|---|---|---|---|
| WS1 | Kap. 18 (`konzept.md:480`) vs. Kap. 30.3 (`konzept.md:850`) | Zwei verschiedene Event-Envelopes (`event_version` vs. `schema_version`; `user_ref` vs. `actor_id`; `reference_id` vs. `source_object_id`) – **innerer** Widerspruch, beide als „v1“ bezeichnet. | – |
| WS2 | Kap. 8 Workflow-Zeile (Aktionen `course.enroll`, `course.revoke`) und Kap. 30.2 | Workflow-Konzept schließt für LMS-Zugänge schreibende Workflow-Aktionen aus. | `wp-workflowmaster@e5f41e6:konzept.md:942, 1012-1024` |
| WS3 | Kap. 30 (Z. 825: Abgleich „nutzt die Konzepte von Workflow, Form und Review“) und Kap. 44 | Die Partnerkonzepte enthalten **keine** Course-Captain-Zeile; die in Kap. 30.2 beschriebenen Gegenverträge sind reine Course-Wünsche, keine abgestimmten Verträge. | Abschnitt 1 (0 Treffer); Form `:477-488`, Workflow `:1053-1063`, Review `:811-822` |
| WS4 | Kap. 30.3 Integrationszustände (`konzept.md:854`, 7 Werte, `incompatible`) vs. Kap. 33 (8 Werte, `active_compatible`/`active_incompatible`) | Zwei Zustandslisten für denselben Statusvertrag (innerer Widerspruch); Partner nutzen `incompatible_contract`. | Review `:330`, Form `:202` |
| WS5 | Kap. 44 (Quellenangabe Form §6/6.1/6.2 als „SEO-Zuständigkeit … Yoast-Kompatibilität“ übertragbar) | Form setzt voraus, dass das Plugin **keine** eigenen öffentlichen URLs hat (`:394`); für Course nur eingeschränkt übertragbar (F8). Kein harter Widerspruch, aber Kap. 44 verallgemeinert zu stark. | `wp-formmaster:konzept.md:394` |

## 10. Nachweis und Grenzen

- Gelesen: die in den Tabellen genannten Abschnitte der drei `konzept.md`; `TODO.md` der Partner nur per Suche (keine Course-Treffer). Keine Partnerdatei verändert, nichts ausgeführt außer Lese-/Suchbefehlen.
- Keine Tests anwendbar (keine Implementierung auf beiden Seiten). Abhaken von R0-32 erst nach Review/Freigabe dieser Matrix und Übernahme der Deltas in R0-19/R0-33/R0-34.
