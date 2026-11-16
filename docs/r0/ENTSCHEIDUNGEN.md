# R0 – Offene Entscheidungen für den Auftraggeber

**Stand:** 16.11.2026 · **Zweck:** Alle Fragen, die aus den R0-Arbeiten entstanden sind und die nicht technisch, sondern vom Auftraggeber zu entscheiden sind. Doppelte Fragen aus verschiedenen Inventurteilen sind zusammengeführt. Jede Zeile nennt eine **Empfehlung**.

**So antworten:** z. B. „Alle Empfehlungen annehmen außer E-12 (Option B) und E-30 (nein)“. Erst danach werden die betroffenen R0-Dokumente von „Vorschlag“ auf „beschlossen“ gesetzt und die Tasks abgehakt. **R0-GATE und damit jede Implementierung (R1) bleiben bis dahin gesperrt.**

Quellen: `R0-01-baseline.md`, `R0-02-inventar/A…E`, `R0-32-suite-traceability.md`, `R0-36-suite-vertragsdeltas.md`. Die Kürzel in der Spalte „Herkunft“ verweisen auf die Abschnitte „Entscheidungsbedarf“ dort.

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
| Kap. 18 und Kap. 30.3 enthalten zwei verschiedene Event-Envelopes „v1“ | R0-19: ein Envelope wird festgelegt (technisch, keine Produktentscheidung) |
| Kap. 30.3 und Kap. 33 nennen unterschiedliche Integrationsstatus-Listen | R0-34 (technisch) |
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
