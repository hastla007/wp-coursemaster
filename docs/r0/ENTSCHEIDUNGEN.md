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
| E-57 | PHP-Untergrenze | **PHP 8.2** (LB: 8.1). Lebenszyklus in R0-20 belegen und vor dem R1-Release neu bewerten | R0-14 D-14-01 | R0-20 |
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

Hauptrestrisiko ist die **Prüfungsintegrität**: Fragen und Antworten können über den Lernmodus gesammelt und nach der Abgabe weitergegeben werden. Das lässt sich bremsen (E-146, E-147), aber nicht verhindern; Course Captain verspricht kein Proctoring. **Klarstellung zu E-46:** Wegen E-10 hat der Free-Kern keine Login- bzw. Reset-Limits. Fail-closed gilt dort für Claim, Einlösen, Einladungen und Datenschutzanträge. Login und Reset folgen erst mit dem optionalen Härtungsmodul.

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
