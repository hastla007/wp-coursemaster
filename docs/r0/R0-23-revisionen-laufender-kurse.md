# R0-23 · Revisionen laufender Kurse und Lernender (Entwurf)

**Status:** **Vorgeschlagen – Freigabe Auftraggeber/Architektur offen.** Task R0-23 ist **nicht** abgehakt. Es gibt keine Implementierung und keinen Code.
**Stand:** 16.11.2026
**Bezug:** TODO R0-23 (DoD: Kursupdate bei laufendem Versuch, gelöschte Lektion, umsortierte Inhalte, Mastery, Zertifikate, Migrations-/Grandfathering-Policy), Zuarbeit für R1-27 (Testziel), R1-33 (Snapshot-Policy), R0-28 (Vererbung), R1-04, R1-08, R1-10, R1-15, R1-35, R1-42; Konzept Kap. 28.3, 35.3, 35.4, 37; `docs/r0/R0-06-datenmodell-adr.md` (§4, §5 „Offen in R0-23“, Nachträge), `docs/r0/R0-08-kurs-activity-pruefungsvertraege.md` (§1.3, §1.4, §2.3, §5, §6.3, §7), `docs/r0/R0-10-import-medien-sicherheit.md` (§3.4 „Offener Punkt“, §3.6).
**Entscheidungen als Annahme (`ENTSCHEIDUNGEN.md`, noch nicht beschlossen):** **E-29** (kein Wiederaufleben; neue Revision statt Rückkehr), **E-50** (Kursrevision fixiert Struktur und Regeln, nicht die Fragenrevisionen), **E-74** (Nachbewertung nur als neue Auswertung mit Audit, nie automatisch), **E-75** (`expired` = `evaluated` + `end_reason`). Außerdem abhängig von E-14 (Drip Free), E-47 (Hybrid), E-63 (lokal gewinnt), E-67 (Lektion 1:1 Activity), E-68 (Quiz im Versuchsautomaten), E-70 (Abschluss-Rücknahme), E-71 (Abbruch zählt bei Limit), E-72 (Inaktivitäts-Abgabe). Weicht ein Beschluss ab, sind §2.3 und §3 anzupassen.
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (nur gelesen, nicht verändert). Pfade relativ zu `bootspruefung-learning/src/`. Abkürzungen wie in R0-08: **LS** `Learning/Services/LearningService.php`, **ES** `Exams/Services/ExamService.php`, **CR** `Content/Repositories/ContentRepository.php`, **IS** `Content/Import/ImportService.php`, **EI** `Content/Import/EntityImporter.php`, **VS** `Trainers/VideoService.php`.

**Normative Sprache:** **MUSS** / **SOLL** / **KANN** wie in R0-08.

---

## 0. Kurzfassung

1. **Grundmodell „Vorwärtsrollen mit Besitzstand“:** Innerhalb eines Kurses sehen alle Lernenden nach dem Veröffentlichen die neue Kursrevision. Offener Fortschritt wird über stabile UUIDs (`activity_uid`, `question_id`) übertragen. Was ein Lernender schon erreicht hat (abgeschlossene Activities, Kursabschluss, Freischaltung, Prüfungsergebnis, Nachweis, Zertifikat), bleibt erhalten. Nichts wird still zurückgesetzt oder neu bewertet.
2. **Harter Bestandsschutz nur über eine neue Kursausgabe:** Wer den alten Stand unverändert weiterführen muss (z. B. Prüfungsordnung wechselt), legt eine neue Ausgabe als eigenen Kurs an (`copied_from_uid`). Der alte Kurs wird für neue Einschreibungen geschlossen und läuft aus.
3. **Laufende Prüfungs- und Quizversuche** laufen immer auf ihrem Snapshot zu Ende. Course Captain friert zusätzlich die drei Werte ein, die LB live liest: Kulanz, Erklärungsrecht und Modulnamen. Die Teilumfangsprüfung im laufenden Versuch läuft gegen den Snapshot statt gegen die Live-Variantenliste (neuer LB-Befund, §1.3).
4. **CPT-Lektionen bis zur Aktivierung (R0-10):** Empfehlung **Variante C, Staging-Kopie** („Umschreiben und neu veröffentlichen“). Änderungen an Lektionen eines veröffentlichten Kurses, ob aus Import oder Editor, landen in einer nicht öffentlichen Kopie. Beim Veröffentlichen bzw. in `activating` werden sie in **einer** DB-Transaktion je Kurs übernommen. WP-Revisionen werden nicht als Auslieferungsquelle benutzt.
5. **Zertifikate** ändern sich durch Kursrevision, Kursrückzug, Fragenkorrektur, Refund oder Lizenzablauf **nie**. Eine Korrektur entsteht nur aus Druckfehler, Namensänderung oder einer Nachbewertung, die das Ergebnis ändert, aber `passed` hält. Ein Widerruf ist nur manuell mit Grund möglich. Ein neues Zertifikat gibt es nur mit neuer Evidenz.
6. Testfälle **REV-001 … REV-046** (Ziel R1-27, Races mit R1-42); neun neue Entscheidungen **R23-D1 … R23-D9**; acht technische Deltas zu R0-06/R0-08.

---

## 1. Begriffe, Prinzipien, Befunde

### 1.1 Begriffe

| Begriff | Bedeutung | Speicherort |
|---|---|---|
| **Kursrevision** | unveränderlicher veröffentlichter Stand: Struktur, Reihenfolge, Activity-Definitionen, Regeln, Referenzen auf Prüfungsregel-Versionen (R0-08 §7.1) | `cc_course_revision` |
| **Lernstand-Revision** | Kursrevision, gegen die Completion, Gates und „Nächste Lektion“ eines Lernenden berechnet werden | `cc_course_progress.course_revision_id` |
| **Abschluss-Revision** | Kursrevision, in der der Lernende den Kurs abgeschlossen hat; ändert sich danach nie | **neu** `cc_course_progress.completed_revision_id` (Δ-23-1) |
| **Revisionsklasse** | Angabe des Autors beim Veröffentlichen: `patch` (redaktionell: Tippfehler, Bild, Formulierung) oder `minor` (Struktur, Pflichtumfang, Regeln). Steuert nur Hinweise und Prüfaufwand, nicht die Rechte der Lernenden | **neu** `cc_course_revision.change_class` (Δ-23-2) |
| **Kursausgabe** | neuer Kurs (neue `course_uid`) als Nachfolger, angelegt per Duplizieren mit `copied_from_uid` (R0-08 §1.2) | `cc_source_map` |
| **Besitzstand** | einmal erreichter Zustand eines Lernenden, den eine Revision nicht wegnimmt | Regeln RV-07, RV-09, RV-14, RV-15 |
| **Sicherer Punkt** | Zeitpunkt, an dem die Lernstand-Revision umgestellt wird: nächster Kurszugriff des Lernenden oder Backfill-Lauf. Laufende Versuche sind davon unabhängig, weil sie ihren eigenen Snapshot haben | §3.2 |
| **Fragenänderungsklasse** | `editorial` (Formulierung, Tippfehler) oder `substantive` (Lösung, Optionen, Bewertung geändert) an einer neuen Fragenrevision | **neu** `cc_question_revision.change_class` (Δ-23-3) |

### 1.2 Prinzipien (MUSS)

- **P-1 Ereignis statt Zustand:** Prüfungsergebnis, Activity-Abschluss, Kursabschluss, Kompetenznachweis und Zertifikat sind Ereignisse auf einer bestimmten Revision. Spätere Autorenänderungen schreiben sie nie rückwirkend um (28.3 „Prüfungsintegrität“, 35.3 „nicht rückwirkend umgeschrieben“).
- **P-2 Snapshot schlägt Live-Stand:** Ein laufender Versuch wird ausschließlich gegen seinen Snapshot und die referenzierten unveränderlichen Fragenrevisionen ausgeführt und bewertet (R0-06 §5, R0-08 §6.3).
- **P-3 Offenes rollt vorwärts:** Nicht abgeschlossener Fortschritt folgt der neuen Revision. Die Zuordnung läuft nur über stabile UUIDs, nie über Position, Titel oder Post-ID-Reihenfolge.
- **P-4 Kein stilles Herabstufen:** Eine Revision darf einen erreichten Zustand nur herabsetzen, wenn der Autor das beim Veröffentlichen ausdrücklich wählt (`recompletion_required`, R23-D3), mit Audit und sichtbarem Hinweis für die Lernenden. Neubewertungen gibt es nur nach E-74.
- **P-5 Archivieren statt Löschen:** Lektionen, Module, Fragen, Regeln und Kursrevisionen werden archiviert, nie hart gelöscht, solange Lerndaten verweisen (R0-06 §4.9, LB EI:17-19 `source_archived`).
- **P-6 Sperren bleiben sicher:** Eine Revision darf eine Sperre nicht versehentlich öffnen. Verweist eine Voraussetzung auf eine entfernte Activity, ist das ein Publish-Fehler, kein stilles „frei“ (R0-08 §1.4). Zugangsentzug (Refund, Ablauf) wirkt sofort, auch im laufenden Versuch (LB ES:1044-1069).
- **P-7 Erklärbar:** Jede revisionsbedingte Änderung, die ein Lernender sieht (neuer Pflichtumfang, „aktualisiert seit Abschluss“, „Frage nicht mehr verfügbar“, Neuberechnung), hat einen `reason_code` und einen i18n-Text.

### 1.3 Befunde aus LearnBoating (Code @ 9f37c77)

| # | Befund | Beleg | Folge für Course Captain |
|---|---|---|---|
| B-1 | LB hat **keine Kursrevision**. Lektionen, Module und Zuordnungen werden live ausgeliefert, der Import schreibt sie direkt. Parität bedeutet damit Vorwärtsrollen | R0-06 §1.1 Befund 8; IS `runStep` | Grundmodell B (§3.1) ist LB-nah, Kursrevision kommt hinzu |
| B-2 | `ContentRepository::revision()` lädt eine Fragenrevision **ohne Statusfilter** | CR:140-145 | Laufende Versuche können archivierte/`deprecated` Revisionen weiter anzeigen und bewerten (RV-03), Parität |
| B-3 | `saveAnswer` liest die Revision über die Session-Frage (`revision_id`) und prüft **keine** Auslieferbarkeit; geschützt wird nur der Status `running` per `FOR UPDATE` | ES:405-484 (Sperre :456-462) | Archivieren/Korrigieren während `saveAnswer` beeinflusst die Antwort nicht (REV-030) |
| B-4 | Lernmodus: Token pinnt die Revision `r`, `tokenContext` prüft aber nur `isDeliverable(question_id)` (aktueller Fragenstatus) | LS:177-190, CR:88-97 | Korrigierte Frage: Antwort zählt gegen die Token-Revision. Archivierte Frage: `NotFound`, nichts gespeichert (RV-06) |
| B-5 | **Neu:** `runningSession` prüft den Teilumfang im laufenden Versuch gegen die **Live**-Bogenliste (`papers()` sortiert nach `sort_order`, ohne archivierte) | ES:1052-1060, CR:228-236 | Umsortieren oder Archivieren von Varianten kann laufende Teilumfang-Versuche sperren. CC prüft den Zugriff live, die Teilumfangszugehörigkeit aber gegen den Snapshot (RV-02) |
| B-6 | Kulanz, Erklärungsrecht und Modulnamen der Themenanalyse werden live gelesen | ES:56-60, :834, :837-853 (R0-08 §6.3) | im Snapshot eingefroren (RV-01) |
| B-7 | Lernstand `progress` ist je `(user_id, question_id)`, nicht je Revision und nicht je Kurs | R0-06 §4.3 `cc_question_progress`; LS:495-538 | Kursrevisionen ändern Lernstände nie. Fragenkorrektur behält den Lernstand (RV-05) |
| B-8 | Mastery-Zählung und Smarttrainer-Kandidaten laufen über `deliverableQuestionSql` | CR:117-133, LS:415, :431-458 | archivierte Fragen fallen aus Nenner und Auswahl, der Datensatz bleibt (RV-11, RV-16) |
| B-9 | `setFavorite(on)` lehnt nicht auslieferbare Fragen ab; vorhandene Favoriten bleiben | LS:460-470 | Favoriten werden nie durch Revisionen gelöscht (RV-16) |
| B-10 | Video-Fortschritt je `(user_id, lesson_id)` ohne Inhalts-Hash; `completed` kann vom Client zurückgenommen werden | VS:236-262 | `content_hash` bei Abschluss (R0-08 §5.1), Rücknahme nur nach E-70 |
| B-11 | Rollback bzw. `restoreActive` legt einen **neuen** Import aus der Paketkopie an. Bekannte Hashes reaktivieren alte Revisionen, nichts wird gelöscht | IS:139-164, :383-391; EI:264, :314 | Ein Rollback ist für Lernende eine normale neue Revision, kein Zurückspringen (RV-21, E-29-Analogie) |

---

## 2. Revisionsereignisse × betroffene Zustände

### 2.1 Ereigniskatalog

| ID | Ereignis | Wird wirksam durch | Was geschrieben wird |
|---|---|---|---|
| **EV-01** | Lektion inhaltlich geändert (gleiche `activity_uid`, neuer `content_hash`) | Kurs veröffentlichen bzw. Import-`activating` | Staging übernommen (§4), neue `cc_course_revision` |
| **EV-02** | Lektion gelöscht (aus der Revision entfernt → `cc_archived`); gilt entsprechend für ein entferntes Modul mit allen Lektionen und seinem Fragenpool | wie EV-01 | neue Revision ohne die Activity; Post-Status `cc_archived` (Δ-23-4) |
| **EV-03** | Lektion verschoben (anderes Modul oder andere Position) | wie EV-01 | neue Revision; `post_parent`/`menu_order` |
| **EV-04** | Modul umsortiert | wie EV-01 | neue Revision; `cc_course_module.sort_order` |
| **EV-05** | Frage korrigiert (neue `cc_question_revision`, alte `deprecated`) | sofort mit Freigabe der Fragenrevision (E-50: keine Kursveröffentlichung nötig) bzw. Import-`activating` | `current_revision_id` umgestellt |
| **EV-06** | Frage archiviert oder durch Rechtestatus gesperrt (`restricted`/`unknown` bei fremden Inhalten, E-18) | sofort | `cc_question.status = archived` bzw. Override |
| **EV-07** | Prüfungsregel geändert (neue `version`, alte `retired`) | Kurs veröffentlichen (Revision referenziert Regelversionen, R0-08 §7.1) | `cc_exam_rule` neue Zeile |
| **EV-08** | Kurs zurückgezogen (`archived`, mit Auslauf nach R23-D6) | Statuswechsel durch Kursmanager | Kursstatus, Audit |
| **EV-09** | Pflicht-Activity hinzugefügt (zusätzlich zur Aufgabenliste, weil es den Pflichtumfang verändert) | wie EV-01 | neue Revision |
| **EV-10** | Abschluss-, Drip- oder Voraussetzungsregel geändert (zusätzlich) | wie EV-01 | neue Revision (`rules`, `settings`) |

Nicht hier geregelt: Änderung globaler oder kursweiter Defaults, z. B. Kulanz oder Feedback-Sichtbarkeit. Laufende Versuche schützt der Snapshot, die Vererbung regelt R0-28/R1-33.

### 2.2 Verhaltensmatrix

Spalten: **Versuch** = laufender Prüfungs- oder Quizversuch (`cc_exam_session` `in_progress`), **Lernstand** = Smarttrainer-Lernstand je Frage (`cc_question_progress`), **Completion** (`cc_activity_progress`/`cc_course_progress`), **Mastery** = Aggregat `mastery_ratio` und Stufenverteilung, **Competency** = abgeleiteter Nachweis (R0-08 §5.3), **Zertifikat**, **Drip** = Drip-Fristen/`unlock_at`, **Fav./Fehler** = Favoriten, Sammlungen und Fehlerliste („zuletzt falsch“). „–“ = nicht betroffen.

| Ereignis | Versuch | Lernstand | Completion | Mastery | Competency | Zertifikat | Drip | Fav./Fehler |
|---|---|---|---|---|---|---|---|---|
| **EV-01** Lektion geändert | läuft auf Snapshot (RV-01) | – (RV-05) | bleibt abgeschlossen, Hinweis „aktualisiert“; Ausnahme `recompletion_required` (RV-07); Video-Position ggf. 0 (RV-19) | – ; neu nur bei geänderten Inline-Fragen (RV-11) | – (RV-12) | – (RV-13) | – | – |
| **EV-02** Lektion gelöscht | läuft auf Snapshot zu Ende, Ergebnis bleibt Historie (RV-01) | bleibt (RV-05) | Zeile bleibt, zählt nicht mehr; Quote neu, ggf. Kursabschluss (RV-08, RV-10) | Nenner ohne Fragen, die nur dort hingen (RV-11) | – (RV-12) | – (RV-13) | entfällt für die Lektion | bleiben; ausgeblendet, falls nicht mehr auslieferbar (RV-16) |
| **EV-03** Lektion verschoben | – | – | wandert mit `activity_uid`; beide Modulabschlüsse und „Nächste Lektion“ neu (RV-07, RV-08) | Modulaggregate neu, Kursaggregat gleich (RV-11) | – | – | Drip des Zielmoduls, Besitzstand bei vorhandenem Fortschritt (RV-14) | – |
| **EV-04** Modul umsortiert | – ; Abschnittslabels eingefroren (RV-01) | – ; Auswahlreihenfolge bei Gleichstand folgt neuer Ordnung (RV-05) | – ; „Nächste Lektion“ neu (RV-08) | – | – | – | – (Tage je Modul, nicht Position) | – |
| **EV-05** Frage korrigiert | alte Revision; nach Abgabe keine automatische Neubewertung (RV-01, E-74) | bleibt (RV-05); laufende Antwort zählt gegen die Token-Revision (RV-06) | `passed(quiz)` bleibt (RV-07) | – | – ; nur Nachbewertung ändert (RV-12) | nur über Nachbewertung → §5 (RV-13) | – | bleiben, zeigen neue Revision (RV-16) |
| **EV-06** Frage archiviert/gesperrt | bleibt beantwortbar und zählt; Medien nach Rechtestatus (RV-03); Neustarts ggf. `reduced`/nicht startbar (RV-18) | Zeile bleibt, Frage nicht mehr ausgewählt; laufende Antwort → `cc_not_found` (RV-06) | – | aus Nenner und Verteilung (RV-11) | – (RV-12) | – (RV-13) | – | ausgeblendet mit Zähler, bei Reaktivierung zurück (RV-16) |
| **EV-07** Prüfungsregel geändert | alte Version (RV-01) | – (Parität G-4) | „bestanden“ bleibt (RV-17) | – | Nachweis der alten Version bleibt, Gültigkeit aus deren Snapshot (RV-12) | – (RV-13) | Startsperre nach Blueprint-Modulen der neuen Version (RV-17) | – |
| **EV-08** Kurs zurückgezogen | Auslauf bis Frist, dann `cancelled`/`withdrawn`, zählt nicht (RV-04) | bleibt; Fragen nur dieses Kurses nicht mehr ausgewählt (RV-20) | eingefroren, nur lesbar (RV-20) | eingefroren (letzter Stand) (RV-20) | bleibt bis `valid_until` (RV-12) | bleibt verifizierbar (RV-13) | gegenstandslos | bleiben, ausgeblendet (RV-16) |
| **EV-09** Pflicht-Activity neu | – | – | offene Kurse: Nenner +1; abgeschlossene Kurse bleiben, Activity „neu seit Abschluss“ (RV-08, RV-09) | Nenner + neue Fragen (RV-11) | – | – | erbt Modul-Drip, ggf. sofort frei (RV-14) | – |
| **EV-10** Regeln geändert | – ; neue Starts nach neuem Gate | – | vorhandene Abschlüsse bleiben (`rule_version`), offene nach neuer Regel (RV-07) | – | – | – | neu berechnet mit Besitzstand (RV-14, RV-15) | – |

### 2.3 Regelkatalog

| ID | Regel | Begründung | Beleg / Bezug |
|---|---|---|---|
| **RV-01** | **MUSS:** Ein laufender Versuch (Prüfung und bewertetes Quiz, E-68) nutzt nur Snapshot und referenzierte Revisionen: Fragen, Antwortoptionen, Reihenfolge, Regel, Zeit, **Kulanz**, **Feedback-/Erklärungsrecht**, **Abschnitts-/Themenlabels**, Bewertung. Entfernt eine Revision die Activity, läuft der Versuch zu Ende. Sein Ergebnis bleibt in der Historie, zählt aber nicht für die Completion der neuen Revision (RV-08). | 28.3 Prüfungsintegrität; Fairness: Lernende dürfen mitten im Versuch nicht die Regeln wechseln | R0-08 §6.3 (Befunde B-6), IMP-025 |
| **RV-02** | **MUSS:** Im laufenden Versuch wird der **Zugang** bei jeder Aktion live geprüft (Refund, Ablauf wirken sofort). Die **Teilumfangszugehörigkeit** (erste *n* Varianten) wird gegen die im Snapshot eingefrorene Variantenposition geprüft, nicht gegen die Live-Liste. | Zugangsentzug ist eine Sicherheitsregel (P-6); Umsortieren ist Autorenarbeit und darf keine laufenden Versuche sperren | Befund B-5, ES:1052-1060 |
| **RV-03** | **MUSS:** Eine während des Versuchs archivierte oder korrigierte Frage bleibt im Versuch anzeigbar, beantwortbar und bewertet. Medien werden zur Anzeigezeit nach Rechtestatus gefiltert (R0-08 §6.3). Ist ein **Pflichtmedium** (`image_required`) inzwischen gesperrt, gilt R23-D7. | Snapshot-Treue (P-2); Rechtssperren müssen trotzdem sofort greifen | B-2, B-3; QP:74 |
| **RV-04** | **MUSS:** Rückzug eines Kurses bricht laufende Versuche nicht sofort ab. Versuche mit Zeitlimit dürfen bis Frist + Kulanz beendet werden. Versuche ohne Limit gehen nach der Auslauffrist (Standard 24 h, wie `OPEN_EXAM_BLOCK_SECONDS`) in `cancelled` mit `end_reason = withdrawn` (Δ-23-5). Sie zählen **nicht** gegen `max_attempts` (Ausnahme zu E-71, weil der Abbruch nicht vom Lernenden kommt). Neue Starts sind sofort gesperrt (`cc_exam_not_startable`). | Wer die Prüfung begonnen hat, soll sie fair beenden können; ein Versuch ohne Ende darf nicht ewig laufen | LS:52; E-71, E-72 |
| **RV-05** | **MUSS:** Der Smarttrainer-Lernstand hängt an `question_id` und ist kursunabhängig (Module n:m). Kursrevisionen ändern ihn nie. Eine Fragenkorrektur behält ihn (LB-Parität). Ein Zurücksetzen gibt es nur als ausdrückliche, auditierte Aktion (R23-D4), nie automatisch. Die Auswahlreihenfolge bei Gleichstand folgt der Reihenfolge der aktuellen Revision; das bleibt deterministisch. | ADR-005, keine stille Neubewertung (R0-08 §7.3 `algorithm_version`) | B-7; LS:138, CR:131 |
| **RV-06** | **MUSS:** Eine Lernantwort mit Token, das vor einer Fragenkorrektur ausgegeben wurde, wird gegen die **Token-Revision** bewertet und mit deren `revision_id` gespeichert (Gültigkeit 2 h). Das Feedback zeigt die Lösung dieser Revision und den Hinweis „Frage wurde inzwischen aktualisiert“. Ist die Frage beim Absenden nicht mehr auslieferbar (archiviert/gesperrt), gibt es `cc_not_found`: kein `cc_attempt`, kein Lernstand-Update, kein Rate-Limit-Verbrauch. Die Auslieferbarkeit wird zu Beginn des Requests geprüft; eine Archivierung zwischen Prüfung und Commit ist unschädlich, weil die Revision unveränderlich ist. | Lernende werden nicht für eine Änderung bestraft, die sie nicht sehen konnten; gesperrte Inhalte werden nicht weiter genutzt | B-4; LS:177-190, :212-224 |
| **RV-07** | **MUSS (Besitzstand Completion):** Eine abgeschlossene Activity bleibt abgeschlossen, wenn sie in der neuen Revision mit gleicher `activity_uid` existiert, egal ob Inhalt, Position, Modul oder Abschlussregel sich geändert haben. Gespeichert bleiben `content_hash` und `rule_version` zum Abschlusszeitpunkt. Ausnahme: Der Autor markiert die Activity beim Veröffentlichen als `recompletion_required` (R23-D3). Dann geht sie **nur bei Lernenden ohne Kursabschluss** auf `in_progress` mit `reason_code = content_revised`, die alte Abschlusszeile bleibt in der Historie. Offene Activities folgen der neuen Abschlussregel. | P-1/P-4; nachweisbasierte Abschlüsse sind monoton (R0-08 §5.1, E-70) | R0-08 §5.1 |
| **RV-08** | **MUSS:** Completion wird gegen die Lernstand-Revision berechnet. Activities, die dort fehlen, zählen nicht (die Zeile bleibt als Historie, Anzeige „nicht mehr Teil des Kurses“). Neue Pflicht-Activities erhöhen den Nenner. Modul- und Kursstatus sowie „Nächste Lektion“ werden beim Umstellen neu berechnet (idempotent, aus Detailtabellen). | Die veröffentlichte Revision definiert den Pflichtumfang (R0-08 §5.1); `cc_course_progress` ist neu aufbaubar (R0-06 E-27) | R0-08 §1.3, §5.1 |
| **RV-09** | **MUSS:** Ein Kursabschluss ist ein Ereignis. `status = completed`, `completed_at` und `completed_revision_id` ändern sich durch keine spätere Revision. Später hinzugekommene Pflicht-Activities erscheinen für diese Lernenden als „neu seit deinem Abschluss“ (freiwillig). Die Lernstand-Revision folgt trotzdem der aktuellen Revision, damit Inhalte und Gates aktuell sind. | P-1; Zertifikate und Reports verweisen auf den Abschluss (35.4) | Δ-23-1 |
| **RV-10** | **SOLL:** Fällt durch eine Revision die letzte offene Pflicht-Activity weg und ist die Kurs-Abschlussregel erfüllt, wird der Kurs beim Umstellen abgeschlossen: `reason_code = revision_recalc`, `completed_revision_id` = neue Revision. Ein Zertifikat aus `course_completion` wird nach Kurseinstellung wie bei jedem Abschluss ausgestellt → R23-D9. | Der Lernende hat alles erbracht, was die gültige Revision verlangt; andernfalls hinge er in einem unerreichbaren Zustand fest | RV-08 |
| **RV-11** | **MUSS:** Mastery-Aggregate (`mastery_ratio`, Stufenverteilung, Themenzusammenfassung) sind abgeleitet. Sie werden beim Umstellen bzw. beim nächsten Lesen über den auslieferbaren Fragenbestand der Lernstand-Revision neu berechnet. Bei sichtbarer Änderung zeigt die UI „Fragenbestand geändert am …“. Gespeicherte Lernstände je Frage bleiben unberührt. | Mastery ist Information, kein Nachweis; der Nenner muss zum aktuellen Lernumfang passen (LB-Parität) | B-8; LS:431-458 |
| **RV-12** | **MUSS:** Competency-Nachweise entstehen nur aus bewerteten, nachweisfähigen Versuchen bzw. Zertifikaten. Sie tragen Regelversion, `certifies_objectives` und `valid_until` aus dem Versuchs-Snapshot. Kursrevision, Regeländerung, Fragenkorrektur und Kursrückzug ändern sie nicht. Nur eine Nachbewertung (E-74: neue `evaluation_no`, Audit) oder die Annullierung eines Versuchs ändert den Nachweis. Er folgt dann der jüngsten Auswertung bzw. wird `revoked`. Neue Lernziele einer Revision sind für Alt-Nachweise `not_evidenced`; es gibt keine Rückprojektion. | Nachweise beziehen sich auf das, was zum Prüfungszeitpunkt geprüft wurde (28.3, 35.4) | R0-08 §5.3 |
| **RV-13** | **MUSS:** Zertifikate ändern sich durch Revisionsereignisse nicht. Korrektur, Widerruf und Neuausstellung folgen ausschließlich §5. | 35.4; Zertifikats-Snapshot (R0-08 §7.2) | §5 |
| **RV-14** | **MUSS (Besitzstand Drip):** `unlock_at` wird gegen die Lernstand-Revision neu berechnet (Zugangsbeginn + Tage des Moduls, E-14). Eine Activity mit vorhandenem Fortschritt (begonnen oder abgeschlossen) bleibt aber frei, auch wenn sie in ein später freigegebenes Modul verschoben oder die Frist verlängert wurde (`rule_refs = ["unlocked_before_revision"]`). Für den **Teilumfang** (`free_limited`) gibt es keinen Besitzstand: Er ist eine Zugangsgrenze, keine Zeitgrenze. | Bereits geöffnete Inhalte wieder zu sperren verwirrt und verliert Arbeit; der Teilumfang ist ein Geschäftsrecht, das nicht durch Umsortieren wachsen darf | R0-08 §1.4; AS:263-333 |
| **RV-15** | **MUSS:** Neue oder verschärfte Voraussetzungen sperren keine Activity, die der Lernende schon begonnen hat (Besitzstand). Für alle übrigen gelten sie sofort. Voraussetzungen auf entfernte Activities lehnt der Publish-Validator ab (R1-26). | P-6 und Besitzstand | R0-08 §1.4 |
| **RV-16** | **MUSS:** Favoriten, Sammlungen und Fehlerliste hängen an `question_id` und werden durch Revisionen nie gelöscht. Nicht auslieferbare Fragen werden im Training ausgeblendet; die Liste zeigt „*n* Fragen derzeit nicht verfügbar“. Neu hinzufügen lässt sich eine nicht auslieferbare Frage nicht (LB-Parität). Wird die Frage reaktiviert (Rollback, bekannter Hash), erscheint sie wieder. Eine korrigierte Frage erscheint mit der neuen Revision; ihr Fehlerstatus (`streak = 0`) bleibt. | Persönliche Daten des Lernenden; Datenschutzexport muss vollständig sein | B-9; LS:460-470 |
| **RV-17** | **MUSS:** Eine neue Regelversion gilt für Starts ab Veröffentlichung der Kursrevision. Bestandene Versuche der alten Version bleiben für `passed(exam)` und als Nachweis gültig. Der Versuchszähler (`max_attempts`, Cooldown) zählt standardmäßig je `activity_uid` über alle Versionen (R23-D5). Startsperren durch Drip werden nach den Blueprint-Modulen der neuen Version berechnet. | P-1; sonst ließe sich ein Versuchslimit durch Regeländerung umgehen | R0-08 §4.4, §7.1 |
| **RV-18** | **SOLL:** Archivierte Fragen verkleinern Pools. Neue Zufallsprüfungen laufen dann ggf. `reduced` (Übung, nie nachweisfähig), feste Varianten mit nicht auslieferbaren Fragen sind nicht startbar (LB-Parität). Health und Publish-Validator warnen den Autor (R0-22). Laufende Versuche sind nicht betroffen (RV-01). | Parität ES:243-246, CR:245 | R0-08 §4.1 |
| **RV-19** | **SOLL:** Ändert sich das Medium einer Video-Activity (anderer `sha256` bzw. andere Quelle), wird `position_seconds` auf 0 gesetzt. Der Abschluss bleibt (RV-07). | Eine alte Position im neuen Video ist sinnlos | B-10 |
| **RV-20** | **MUSS:** Bei einem zurückgezogenen Kurs (`archived`) gibt es nur noch Lesezugriff auf die eigene Historie („Mein Lernen“, Ergebnisse, Zertifikate). Lernen und neue Starts sind gesperrt. Daten bleiben unverändert (Retention nach E-51). Der Smarttrainer wählt Fragen, die **nur** in diesem Kurs vorkommen, nicht mehr aus; Fragen, die auch in anderen aktiven Kursen stehen, bleiben dort lernbar. | Rückzug ist kein Datenverlust (35.3-Analogie); Module und Fragen sind n:m | R0-06 E-03, E-11 |
| **RV-21** | **MUSS:** Ein Content-Rollback (IS `rollback`/`restoreActive`) ist für Lernende eine **neue** Revision mit allen Regeln oben. Es gibt kein Zurückspringen von Lernstand-Revisionen und kein Wiederaufleben archivierter Zustände, außer der Reaktivierung von Fragen über den bekannten Hash. | E-29-Analogie: neue Revision statt Rückkehr; Rollback ist fachlich, nicht schematisch (R0-06 §6.5) | B-11 |

---

## 3. Grandfathering-Policy

### 3.1 Varianten

| Variante | Beschreibung | Vorteile | Nachteile | Bewertung |
|---|---|---|---|---|
| **A Strikter Bestandsschutz** | Jeder Lernende bleibt auf der Revision seiner Einschreibung, bis er fertig ist; Inhalte werden je Revision ausgeliefert | maximale Stabilität für Lernende | Lektionsinhalte müssten je Revision gespeichert und gerendert werden (§4 Variante D); Fehlerkorrekturen und Rechtssperren erreichen Altlernende nicht oder brauchen Sonderwege; Support muss wissen, „welche Fassung“ jemand sieht; Suche/SEO zeigen trotzdem den Live-Stand | verworfen als Standard |
| **B Vorwärtsrollen mit Besitzstand** (§2.3) | Alle sehen die neue Revision; Erreichtes bleibt; Herabstufung nur ausdrücklich | LB-nah (B-1); Korrekturen wirken sofort; ein Auslieferungspfad; Fairness über Besitzstand | Lernende erleben Änderungen mitten im Kurs (Pflichtumfang kann wachsen); braucht gute Hinweise (P-7) | **empfohlen** |
| **C Harte Migration** | Alle werden umgestellt, Fortschritt wird gegen den neuen Stand neu bewertet | einfach | verletzt P-1/P-4, Abschlüsse können verschwinden; widerspricht 28.3 und E-70 | verworfen |

**Empfehlung (R23-D1):** B als einziges In-Place-Modell. Harter Bestandsschutz entsteht nur über eine **neue Kursausgabe** (eigener Kurs, `copied_from_uid`). Den alten Kurs schließt der Autor für neue Einschreibungen (R23-D6) und ändert ihn danach nicht mehr. Damit bleibt er für seine Lernenden faktisch eingefroren, ohne zweiten Auslieferungspfad.

### 3.2 Ablauf der Umstellung

1. **Veröffentlichen** schreibt die neue `cc_course_revision` (und übernimmt Staging, §4). Lernendenzeilen werden dabei **nicht** geschrieben, damit Veröffentlichen kurz bleibt und nicht mit Lernaktionen um Sperren konkurriert.
2. **Lazy Umstellung am sicheren Punkt:** Beim nächsten Kurszugriff (Kursseite, „Mein Lernen“, Activity-Aufruf, Abschlussmeldung) wird `cc_course_progress.course_revision_id ≠ published` erkannt. Die Umstellung läuft dann per CAS (`row_version`): Completion nach RV-07/RV-08/RV-10 neu berechnen, Aggregate nach RV-11, Gates nach RV-14/RV-15. Bei CAS-Konflikt: neu lesen, einmal wiederholen; das Ergebnis ist idempotent.
3. **Backfill-Job** nach jeder Veröffentlichung: alle `cc_course_progress` des Kurses mit alter Revision, Batches zu 500, Cursor in `cc_backfill_state` (R0-06 §6.3). Er nutzt dieselbe Funktion wie Schritt 2. So sind Reports und Kursmanager-Übersichten ohne Lernendenaufruf aktuell.
4. **Mehrere Revisionen übersprungen** (Lernender war lange inaktiv): Es wird direkt auf die aktuelle Revision umgestellt; die Regeln gelten gegen den Stand der Lernendendaten, nicht als Kette.
5. **Laufende Versuche** werden nicht angefasst. Ihr Ergebnis wirkt bei Abgabe auf die Completion der dann gültigen Lernstand-Revision, sofern die Activity dort noch existiert (sonst nur Historie, RV-01).
6. **Abschlussmeldung mit veralteter Seite:** Der Client sendet die Revision, mit der die Seite gerendert wurde (`rendered_revision_id`). Der Server speichert den Abschluss mit dem `content_hash` jener Revision, wenn die Activity dort existierte, und stellt danach um (REV-036).

### 3.3 Wann ist ein Kurs für einen Lernenden „neu“?

| Situation | „neu“? | Wirkung |
|---|---|---|
| neue Kursrevision (`patch` oder `minor`) | **nein** | Vorwärtsrollen mit Besitzstand (§2.3) |
| einzelne Activity mit `recompletion_required` | nur diese Activity, nur ohne Kursabschluss | RV-07 |
| neue **Kursausgabe** (anderer Kurs) | **ja**, eigener Kurs mit eigenem Fortschritt | Keine automatische Anrechnung in R1. Der Zugang zur neuen Ausgabe braucht einen eigenen Grant (manuell/Sammelvergabe durch Kursmanager mit Audit) → R23-D1 |
| Reset durch Kursverwaltung (E-70) | **ja** (neuer Durchlauf) | `cycle_no + 1` (Δ-23-1); Activity-Zeilen des alten Durchlaufs bleiben als Historie; Lernstände je Frage, Versuche, Nachweise und Zertifikate bleiben unverändert |
| neuer Grant nach Ablauf/Widerruf (E-29: neue Grant-Revision) | **nein** | Fortschritt bleibt erhalten (LB-Parität); der Grant ändert nur den Zugang |
| Kompetenz abgelaufen (`valid_until`) | **nein** | Nur die Prüfung wird für den Nachweis erneut nötig; Completion bleibt |
| Kurs zurückgezogen, später neu veröffentlicht | **nein** | wie neue Revision; die Historie bleibt. Ein Reaktivieren ist auditiert (Kursmanager) |

### 3.4 Hinweise an Lernende (P-7)

- Kursseite nach Umstellung (einmalig je Revision): „Dieser Kurs wurde am … aktualisiert“ plus die Autoren-Notiz (`cc_course_revision.notes`, Δ-23-2). Bei `patch` gibt es nur einen dezenten Hinweis.
- Activity-Badges: „aktualisiert seit deinem Abschluss“, „neu“, „neu seit deinem Abschluss (freiwillig)“, „erneut bearbeiten erforderlich“ (nur bei `recompletion_required`), „nicht mehr Teil des Kurses“ (nur in der Historie).
- Kein Versand von Mails in R1 Free (E-24 analog); ein Event `course.revision_published` geht in die Outbox (R0-19) für Pro/Adapter.

---

## 4. CPT-Lektionen bis zur Aktivierung (offene Frage aus R0-10 §3.4)

**Problem:** Fragen haben einen Revisionszeiger (`current_revision_id`), Lektionen (`cc_lesson`, CPT) nicht. Ein Import schreibt Lektionen in `importing` chunkweise. Ohne Gegenmaßnahme sehen Lernende einen Mischstand, bis `activating` die Kursrevision veröffentlicht. Dasselbe gilt im Editor: Speichert ein Autor eine Lektion eines veröffentlichten Kurses, ist sie bei WordPress sofort live, an der Kursrevision vorbei (R0-06 §10 „Entwurf ≠ veröffentlichte Revision“).

### 4.1 Varianten

| Variante | Mechanik | Vorteile | Nachteile / Risiken | Bewertung |
|---|---|---|---|---|
| **0 Live schreiben** | Lektionen sofort live; nur Struktur ist revisioniert | einfachste Lösung, LB-Parität | Mischstand bei Import (widerspricht R0-10 §0 Nr. 3 und IMP-022/024); Editoränderungen umgehen die Kursrevision | verworfen |
| **A Schreiben erst in `activating`** | Import bereitet Lektionsdaten nur in `cc_import_item_log`/Paketkopie vor; `activating` schreibt chunkweise mit Kurs-Wartungshinweis | keine neuen Post-Typen/-Status | `activating` wird lang und ist über Chunks hinweg nicht atomar; Wartungshinweis heißt Ausfall für Lernende; Editorproblem ungelöst | nur Rückfall für sehr große Kurse (§4.3) |
| **B Auslieferung über WP-Revisions-ID** | `structure` speichert je Lektion die WP-Revisions-ID; das Frontend rendert den Revisions-Post | nutzt WP-Bordmittel | `WP_POST_REVISIONS` kann `false`/0 sein, `wp_revisions_to_keep` kürzt; Optimierungs-Plugins löschen Revisionen; Post-Meta ist nur mit `revisions_enabled` (WP ≥ 6.4) revisioniert; REST, Suche, Sitemaps, Feeds und Theme-Templates zeigen trotzdem den Live-Post → doppelter Mischstand; Blocks/Shortcodes erwarten den Live-Kontext | verworfen |
| **C Staging-Kopie („Umschreiben und neu veröffentlichen“)** | Änderungen an Lektionen eines veröffentlichten Kurses landen in einer Kopie `cc_lesson` mit Post-Status `cc_staged` (nicht öffentlich) und Meta `_cc_stage_of = <live_id>`. Neue Lektionen entstehen direkt als `cc_staged` ohne `_cc_stage_of`. Übernahme beim Veröffentlichen bzw. in `activating` | Live-Post bleibt bis zur Übernahme unverändert; **ein** Mechanismus für Import und Editor; Übernahme ist ein kurzer DB-Schritt (Daten sind validiert, Medien liegen schon); WP-Revision des Live-Posts entsteht bei der Übernahme normal (Editor-Historie) | eigener Post-Status muss überall ausgeschlossen werden (Queries, REST, Suche, Sitemaps, Feeds, Menüs); SEO-/Cache-Plugins reagieren auf `save_post` der Kopie; Slug-Konflikte (Kopie bekommt Suffix); Autoren-UX „Änderungen warten auf Veröffentlichung“ | **empfohlen** |
| **D Eigene Inhalts-Snapshot-Tabelle** | Lektionsinhalt (gerendert oder als Block-Markup) je Kursrevision in `cc_lesson_content` | ermöglicht Variante A der Grandfathering-Policy | doppelte Inhaltswahrheit neben dem CPT; dynamische Blocks/Shortcodes lassen sich nicht sicher vorab rendern; Volumen (20 000 Lektionen × Revisionen); widerspricht „WP-nativ bevorzugen“ (Kap. 37) | verworfen (nur nötig, falls R23-D1 = A) |

**Empfehlung (R23-D2): Variante C** für Import und Editor.

### 4.2 Ablauf Variante C

1. **Bearbeiten:** Öffnet ein Autor eine Lektion eines Kurses mit veröffentlichter Revision, bearbeitet der Editor die Staging-Kopie. Sie wird beim ersten Speichern angelegt, eine Kopie je Live-Post. Der Import schreibt in `importing` ebenfalls nur Kopien, markiert mit `_cc_stage_batch = <batch_id>`. Kurse ohne veröffentlichte Revision (reiner Entwurf) bearbeiten den Post direkt.
2. **Löschen** einer Lektion im veröffentlichten Kurs ist eine Staging-Markierung (`_cc_stage_action = archive`). Erst die Übernahme setzt `cc_archived` (P-5).
3. **Übernahme** (Kurs veröffentlichen bzw. `activating` je Kurs), unter `NamedLock course_publish:<course_id>` (nicht wartend, sonst `cc_publish_busy` 409):
   - eine DB-Transaktion: für jede Kopie `post_title`, `post_content`, `post_excerpt`, `menu_order`, `post_parent` und eine **Whitelist** von Meta-Schlüsseln (`_cc_*`) auf den Live-Post übertragen; neue Lektionen `cc_staged → publish`; Archivierungen `→ cc_archived`; Kopien löschen; `cc_course_revision` einfügen; bei Import zusätzlich Fragenzeiger umstellen (R0-10 §3.4);
   - nach Commit: `clean_post_cache` je Post, Content-Generation erhöhen (R0-06 §10, INV-C-128), Outbox `course.revision_published`, Backfill einplanen (§3.2).
   - Hooks von Drittplugins (`save_post`) laufen erst nach dem Commit über eine gesammelte Liste, nicht innerhalb der Transaktion, damit externe Seiteneffekte keinen Rollback brauchen.
4. **Restfenster:** DB-seitig atomar. Ein persistenter Objekt-Cache kann bis zur Invalidierung nach dem Commit (Millisekunden bis Sekunden) den alten Stand liefern; das ist ein konsistent alter Stand, kein Mischstand je Lektion. Seiten-Caches invalidiert der Cache-Adapter (R1-38).
5. **Verwerfen:** „Änderungen verwerfen“ löscht die Kopie (Audit). Ein verworfener oder abgebrochener Import löscht alle Kopien mit seinem `_cc_stage_batch` (IMP-022/023).
6. **Konflikt Editor ↔ Import** (E-63 „lokal gewinnt“): Existiert schon eine Editor-Kopie, meldet der Dry-run `conflict`. Standard: Die Editor-Kopie bleibt, die Quelländerung wird protokolliert. Mit „Quelle übernehmen“ wird die Kopie ersetzt (Audit).
7. **Sichtbarkeit:** `cc_staged` ist mit `register_post_status` als nicht öffentlich, nicht durchsuchbar und ohne `show_in_admin_all_list` registriert. REST (`course-captain/v1` und `wp/v2`) liefert Kopien nur an Autoren mit Bearbeitungsrecht für den Kurs (R0-07). Sitemaps, Feeds, Suche und `WP_Query` ohne ausdrücklichen Status schließen sie aus (REV-040).
8. **Vorschau:** Autoren sehen die Kopie über die Kursvorschau („Kurs so ansehen, wie er nach dem Veröffentlichen aussieht“), R1-04/R1-33.

### 4.3 Grenzen

- **Sehr große Kurse:** Übersteigt die Übernahme das Zeitbudget (Richtwert > 500 Lektionen je Kurs oder > 5 s gemessen in R0-14), läuft sie chunkweise mit Kurs-Wartungshinweis (Variante A als Rückfall). Die Kursrevision wird erst nach dem letzten Chunk eingefügt; bis dahin rendern Lektionen mit bereits übernommenem Inhalt, Gates und Completion laufen auf der alten Revision. Der Hinweis „Kurs wird aktualisiert“ erscheint für diesen Kurs.
- **`wp_revisions_to_keep = 0`** ist unschädlich: Variante C braucht keine WP-Revisionen.
- **Module** (`cc_module`, CPT) nutzen denselben Mechanismus für Titel und Beschreibung; Zuordnung und Reihenfolge liegen ohnehin in `cc_course_module` und werden als Entwurf geführt (R0-06 §4.1 „Entfernen nur bei Entwurf, sonst neue Kursrevision“).
- **Kurs-CPT** selbst (Titel, Beschreibung, Bild) ist Katalog- und SEO-Inhalt und wird **live** geschrieben (`patch`-Charakter, keine Lernrelevanz). Der Zertifikatstitel kommt aus dem Snapshot (§5).

---

## 5. Zertifikate: Korrektur, Widerruf, Neuausstellung

### 5.1 Grundsatz

Ein Zertifikat bestätigt eine Evidenz (Versuch bzw. Kursabschluss) auf einer bestimmten Kurs- und Regelrevision zum Ausstellungszeitpunkt (35.4, R0-08 §7.2). Seine Wahrheit hängt nicht am heutigen Kursstand. Zustände nach 35.4: `issued`, `corrected` (ersetzte Fassung), `revoked`. Ablauf (`valid_until`) ist **kein** Zustand, sondern eine abgeleitete Anzeige.

### 5.2 Auslöser und Wirkung

| Auslöser | Wirkung | Akteur | Begründung |
|---|---|---|---|
| neue Kursrevision (jede Klasse), Lektion/Frage/Regel geändert | **keine** | – | RV-13; Snapshot enthält Titel wie gedruckt und `course_revision_id` |
| Kurs zurückgezogen | **keine**; Verifikation funktioniert weiter | – | RV-20; 35.3-Analogie |
| Refund, Chargeback, Pro-Lizenzablauf | **keine** | – | 35.3, 35.4, CORE-06, CORE-10 |
| Fragenkorrektur ohne Nachbewertung | **keine** | – | E-74 „nie automatisch“ |
| Nachbewertung (E-74): `passed` bleibt, Punkte ändern sich | **Korrektur**: neue Zeile `revision_no + 1`, `supersedes_id`, neuer Snapshot mit `evaluation_no`; alte Zeile → `corrected` | berechtigte Rolle (`cc_manage_certificates`), Grund Pflicht | Nachweis soll das gültige Ergebnis zeigen; Historie bleibt |
| Nachbewertung: bestanden → nicht bestanden | **kein automatischer Widerruf.** Das System legt einen Prüfauftrag an (Admin-Hinweis, Audit `certificate_review_required`). Widerruf nur manuell nach §5.4 | berechtigte Rolle | 35.4 „Widerruf nur mit berechtigtem Akteur, Grund“; E-74 |
| Nachbewertung: nicht bestanden → bestanden | **Erstausstellung**, sofern der Kurs Zertifikate vorsieht; Evidenz mit `evaluation_no` | System (wie bei normaler Abgabe) | neue Evidenz |
| Druckfehler im Snapshot, Namensänderung des Lernenden | **Korrektur** auf Antrag, mit Nachweis/Grund | berechtigte Rolle | 35.1 (Name wie gedruckt); Profiländerung allein ändert nichts (R0-08 §7.2) |
| Betrug, annullierter Versuch, irrtümliche Ausstellung | **Widerruf** (§5.4) | berechtigte Rolle, Grund Pflicht | 35.3, 35.4 |
| neue Template-Version | **keine**. Alte Zertifikate rendern mit ihrer `template_version` (Renderer für alle referenzierten Versionen bleiben, analog `format_version`) → R23-D8 | – | Zertifikat ist ein historischer Beleg |
| neuer bestandener Versuch / Rezertifizierung nach Revision | **Neuausstellung = neues Zertifikat** (neue `certificate_uid`). Das alte bleibt `issued` und belegt den früheren Stand | System | neue Evidenz, keine Korrektur der alten |
| Kompetenz abgelaufen (`valid_until` < heute) | **keine Statusänderung**; Verifikation zeigt „gültig bis … (abgelaufen)“ | – | 35.4; Ablauf ist abgeleitet |
| Reset durch Kursverwaltung (§3.3) | **keine** | – | Reset betrifft Completion, nicht Nachweise |

### 5.3 Korrektur

- Eine Transaktion: neue `cc_certificate`-Zeile (gleiche `certificate_uid`, `revision_no + 1`, `supersedes_id`, neuer Snapshot, neuer `verify_token_hash`), alte Zeile `status = corrected`, Audit (Akteur, Grund, Diff der Snapshot-Felder), Outbox `certificate.corrected`.
- `UNIQUE evidence_rev` verhindert doppelte Korrekturen bei Doppelklick (Idempotenz über `request_key` des Admin-Requests).
- Der **alte Verifikations-Token** bleibt auflösbar. Die Antwort lautet „korrigiert, aktuelle Fassung vom …“ und gibt nur Felder der Privacy-Policy aus (R0-29); Daten der neuen Fassung werden nicht über den alten Token preisgegeben.
- Eine Korrektur darf Evidenz, Lernenden und Kurs **nicht** wechseln. Das wäre ein Widerruf plus Neuausstellung.

### 5.4 Widerruf

- Nur Rollen mit `cc_manage_certificates` (Kap. 36), Pflichtgrund aus Liste (`fraud`, `invalid_assessment`, `issued_in_error`, `other` + Freitext), Zeit, Audit, Outbox `certificate.revoked`.
- Wirkt auf **alle** Revisionen der `certificate_uid`; Verifikation zeigt „widerrufen am …“ (Grund öffentlich nur nach R0-29-Policy).
- **Endgültig** (E-29-Analogie): Ein widerrufenes Zertifikat lebt nicht wieder auf. War der Widerruf ein Irrtum, wird ein neues Zertifikat (neue `certificate_uid`) mit Verweis im Audit ausgestellt.
- Der zugehörige Competency-Nachweis wird `revoked` (R0-08 §5.3), Completion bleibt unberührt.

### 5.5 Neuausstellung

- Nur bei neuer Evidenz (§5.2). Keine „Neuausstellung im aktuellen Kursstand“ ohne neue Prüfung, weil das Zertifikat sonst etwas belegen würde, das nicht geprüft wurde.
- Hat der Kurs Gültigkeitsdauern (`competency_validity_days`), zeigt „Mein Lernen“ ab 30 Tagen vor Ablauf „Rezertifizierung möglich“ (Anzeige; Erinnerungsmails Pro, E-24 analog).

### 5.6 Verifikation je Zustand (öffentliche Minimalantwort; Felder nach R0-29)

| Zustand | Antwort |
|---|---|
| `issued`, gültig | gültig, Aussteller, Kurs (wie gedruckt), Datum |
| `issued`, `valid_until` überschritten | gültig ausgestellt, Gültigkeit abgelaufen am … |
| `corrected` (alter Token) | ersetzt durch Fassung vom …; keine weiteren Daten |
| `revoked` | widerrufen am … |
| unbekannter Token | nicht gefunden, mit Rate-Limit (35.4) |

---

## 6. Nebenläufigkeit

| Konstellation | Regel | Test |
|---|---|---|
| **Veröffentlichen während `saveAnswer`** | Veröffentlichen schreibt `cc_course_revision`, `cc_question`/`cc_question_revision`-Status und Posts, **nie** `cc_exam_session*`. `saveAnswer` sperrt nur Session und Session-Frage (`FOR UPDATE`) und liest die Revision über die unveränderliche `revision_id`. Es gibt keine gemeinsamen Zeilensperren, beide committen. `snapshot_hash` bleibt gleich | REV-030, REV-031 |
| Versuchsstart während Veröffentlichen/Aktivieren | Start liest die veröffentlichte Kursrevision und die Fragenzeiger in seiner Start-TX (konsistenter Lesestand): vollständig alt oder vollständig neu. `course_revision_id` steht im Snapshot. Einfügen der Session-Fragen setzt bei FK nach D-1/B Shared Locks auf `cc_question_revision`, Veröffentlichen setzt X-Locks auf dieselben Zeilen. Das gibt Wartezeit, aber keinen Zyklus, solange beide in fester Reihenfolge (Revision-ID aufsteigend) sperren. Deadlock 1213 → einmal wiederholen | REV-032 |
| Lernantwort mit altem Token | RV-06 | REV-033, REV-034 |
| Lazy Umstellung ↔ Abschlussmeldung | beide über CAS auf `cc_course_progress`; Abschluss schreibt zuerst `cc_activity_progress` (Unique `user_activity`), danach Neuberechnung; Konflikt → neu lesen, einmal wiederholen | REV-036 |
| Backfill ↔ Lernendenzugriff | gleiche idempotente Funktion; CAS verhindert Doppelzählung | REV-037 |
| zwei Veröffentlichungen desselben Kurses | `NamedLock course_publish:<id>` nicht wartend → zweite bekommt `cc_publish_busy`; `UNIQUE course_rev` als letzte Grenze | REV-038 |
| Archivieren einer Frage während Smarttrainer-`next` | `next` liefert ggf. noch die Frage; das folgende `answer` → `cc_not_found` (RV-06) | REV-034 |
| Kursrückzug während laufendem Versuch | RV-04; Auslauf-Cron bedingt `WHERE status = in_progress` | REV-026 |

---

## 7. Testfälle

Fixtures synthetisch und neutral (E-02). Alle Tests prüfen zusätzlich: keine Lerndaten gelöscht, `snapshot_hash` laufender/abgeschlossener Versuche unverändert, Audit-Eintrag bei Autoren- und Admin-Aktionen. **Ziel-Task R1-27**, sofern nicht anders angegeben (in Klammern Mitnutzer).

| ID | Gegeben / Aktion | Erwartung | Regel | Ziel |
|---|---|---|---|---|
| REV-001 | Lernender hat Lektion L abgeschlossen; Autor ändert L-Text, veröffentlicht `patch` | L bleibt `completed`, `content_hash` alt gespeichert, Badge „aktualisiert seit Abschluss“; Kursquote unverändert | RV-07 | R1-27 (R1-10) |
| REV-002 | wie REV-001, aber L mit `recompletion_required`; Lernender A ohne, B mit Kursabschluss | A: L `in_progress`, `reason_code = content_revised`, alte Zeile in Historie; B: unverändert `completed` | RV-07, RV-09 | R1-27 |
| REV-003 | Pflichtlektion L (offen) wird entfernt; sonst alles erledigt, Kurs-Abschlussregel erfüllt | nach Umstellung Kurs `completed`, `reason_code = revision_recalc`, `completed_revision_id` = neu; Zertifikat nach Kurseinstellung | RV-08, RV-10 | R1-27 (R1-35) |
| REV-004 | Abgeschlossene Lektion L wird entfernt | Zeile bleibt, „nicht mehr Teil des Kurses“; Quote nur über verbleibende Activities | RV-08 | R1-27 |
| REV-005 | Modul M mit Fortschritt wird aus dem Kurs entfernt | alle Activities wie REV-004; Fragen nur aus M fallen aus Mastery-Nenner, Lernstände bleiben | RV-08, RV-11 | R1-27 |
| REV-006 | Lektion L (begonnen) wird in ein Modul mit Drip +14 Tage verschoben, Zugangsbeginn vor 3 Tagen | L bleibt frei (`unlocked_before_revision`); unbegonnene Lektionen des Zielmoduls gesperrt mit `unlock_at` | RV-14 | R1-27 (R1-10) |
| REV-007 | Lektion im Teilumfang (`free_limited`, erste 2) wird nach hinten verschoben | Testnutzer im Teilumfang verliert Zugriff (`trial`), kein Besitzstand; abgeschlossene Zeile bleibt | RV-14 | R1-27 (R1-13) |
| REV-008 | abgeschlossene Lektion verschoben in anderes Modul | Abschluss wandert mit `activity_uid`; Status beider Module neu; „Nächste Lektion“ nach neuer Reihenfolge | RV-07, RV-08 | R1-27 |
| REV-009 | Module umsortiert; Smarttrainer mit Gleichstand | Auswahl deterministisch nach neuer Reihenfolge; Lernstände unverändert; Drip unverändert | RV-05 | R1-27 (R1-18) |
| REV-010 | neue Pflicht-Activity; A in Arbeit, B abgeschlossen | A: Nenner +1, Quote sinkt, Hinweis „neu“; B: `completed` bleibt, Activity „neu seit Abschluss (freiwillig)“ | RV-08, RV-09 | R1-27 |
| REV-011 | Drip eines Moduls von 7 auf 14 Tage, Lernender hat eine Lektion darin begonnen | begonnene Lektion frei, übrige gesperrt bis Tag 14 | RV-14 | R1-27 |
| REV-012 | neue Voraussetzung auf Activity X, Lernender hat Y schon begonnen | Y bleibt frei; neue Lernende sehen `prerequisite` | RV-15 | R1-27 |
| REV-013 | Veröffentlichen mit Voraussetzung auf entfernte Activity | Publish abgelehnt (Validator), keine Revision | RV-15, P-6 | R1-26 (R1-27) |
| REV-014 | Abschlussregel Video `manual_confirm` → `position_ratio ≥ 90 %` | vorhandene Abschlüsse bleiben (`rule_version` alt); offene nach neuer Regel | RV-07 | R1-27 (R1-07) |
| REV-015 | Video-Medium ausgetauscht, Lernender bei Position 300 s, Lektion abgeschlossen | Position 0, Abschluss bleibt | RV-19 | R1-27 |
| REV-016 | Prüfung läuft; Autor korrigiert Frage Q (neue Revision) | Versuch zeigt und bewertet alte Revision; Ergebnis = Golden-Master-Erwartung der alten Revision | RV-01 | R1-27 (R1-08, IMP-025) |
| REV-017 | Prüfung läuft; Q wird archiviert | Q bleibt im Versuch beantwortbar und zählt | RV-03 | R1-27 (R1-08) |
| REV-018 | Prüfung läuft; Bild von Q wird `restricted` (fremd), `image_required = false` | Bild verschwindet aus Anzeige, Bewertung unverändert | RV-03 | R1-27 (R1-14) |
| REV-019 | wie REV-018 mit `image_required = true` | Verhalten nach R23-D7 (Empfehlung: Q `neutralized`, aus Punkten und Maximum, Grund im Ergebnis) | RV-03 | R1-27 (R1-09) |
| REV-020 | Prüfung läuft; Kulanz-Default und Feedback-Sichtbarkeit werden geändert | Versuch nutzt Snapshot-Werte; neue Versuche die neuen | RV-01 | R1-33 (R1-27) |
| REV-021 | Prüfung läuft; Modul wird umbenannt | Themenanalyse im Ergebnis zeigt den Namen beim Start | RV-01, B-6 | R1-27 |
| REV-022 | Teilumfang-Versuch auf Variante 2 läuft; Varianten werden umsortiert (Variante 2 an Position 5) | Versuch läuft weiter (Snapshot-Position); neuer Start auf Variante 2 abgelehnt (`trial`) | RV-02, B-5 | R1-27 (R1-13) |
| REV-023 | Prüfung läuft; Grant wird per Refund widerrufen | nächste Aktion `cc_access_denied`; Versuch bleibt `in_progress` bis Frist/Auswertung nach R0-08 | RV-02, P-6 | R1-27 (R1-13) |
| REV-024 | neue Regelversion; Lernender hatte 3 von `max_attempts = 3` mit alter Version | Start abgelehnt (`cc_exam_attempts_exhausted`), außer Autor hat beim Veröffentlichen „Zähler zurücksetzen“ gewählt (R23-D5) | RV-17 | R1-27 (R1-08) |
| REV-025 | bestandener Versuch mit Regel v1; v2 veröffentlicht | `passed(exam)` bleibt erfüllt; Nachweis zeigt „Version 1“; `valid_until` aus v1-Snapshot | RV-12, RV-17 | R1-27 (R1-10) |
| REV-026 | Kurs zurückgezogen; Versuch A mit Limit (Rest 10 min), Versuch B ohne Limit | A bis Frist abschließbar; B nach 24 h `cancelled`/`withdrawn`, nicht gegen `max_attempts`; neue Starts `cc_exam_not_startable` | RV-04 | R1-27 (R1-08) |
| REV-027 | Kurs zurückgezogen; Lernender öffnet „Mein Lernen“ | Historie, Ergebnisse, Zertifikate lesbar; Lernen gesperrt; Daten unverändert | RV-20 | R1-27 |
| REV-028 | Frage Q in Kurs K1 und K2; K1 zurückgezogen | Q im Smarttrainer von K2 weiter auswählbar, Lernstand gemeinsam | RV-20, RV-05 | R1-27 (R1-10) |
| REV-029 | Lernstand Q = `mastered`; Q korrigiert (`substantive`) | Lernstand unverändert (Parität); Hinweis an Q; Admin-Aktion „Lernstand zurücksetzen“ (R23-D4) setzt `unsure`, `streak = 0`, `due_at = NULL`, ohne `attempts` zu ändern, Audit | RV-05 | R1-27 (R1-18) |
| **REV-030** | **Race:** Prüfung läuft; parallel (a) `saveAnswer(pos 7)` und (b) Veröffentlichen einer Kursrevision, die Q7 korrigiert, Q8 archiviert und die Regel ersetzt; 200 Wiederholungen, Interleaving über Barrieren vor/nach Commit | beide committen; Antwort gespeichert (`applied = true`), `revision_id` der Session-Frage unverändert; kein Deadlock, Wartezeit < Lock-Budget (R0-14); `snapshot_hash` gleich; Auswertung gegen alte Revisionen | §6, RV-01 | R1-27 (R1-42) |
| REV-031 | **Race:** `saveAnswer` und `submit` parallel zu Import-`activating` desselben Kurses | wie REV-030; genau ein Ergebnis; Abgabe gegen Snapshot | §6 | R1-27 (R1-42, R1-41) |
| REV-032 | **Race:** Versuchsstart parallel zum Veröffentlichen | Snapshot vollständig alt **oder** vollständig neu (Kursrevision, Regel, Fragenrevisionen konsistent zueinander); Deadlock → eine Wiederholung erfolgreich | §6 | R1-27 (R1-42) |
| REV-033 | **Race:** Lernmodus-Token für Q (Rev. r1) ausgegeben; Q korrigiert (r2); dann Antwort | Bewertung gegen r1, `cc_attempt.revision_id = r1`, Feedback mit Hinweis; zweites Absenden desselben Tokens `repeated = true` | RV-06 | R1-27 (R1-18) |
| REV-034 | **Race:** Token für Q; Q archiviert; dann Antwort | `cc_not_found`; kein `cc_attempt`, kein Lernstand-Update, kein Rate-Limit-Verbrauch | RV-06 | R1-27 |
| REV-035 | Q (Favorit, in Sammlung, „zuletzt falsch“) archiviert, später per Rollback reaktiviert | archiviert: Training blendet aus, Zähler „1 nicht verfügbar“, `setFavorite(on)` 404; reaktiviert: alles wieder sichtbar, Fehlerstatus erhalten | RV-16, RV-21 | R1-27 |
| REV-036 | **Race:** Seite mit Revision 4 gerendert; Revision 5 veröffentlicht; Lernender meldet Abschluss mit `rendered_revision_id = 4` parallel zur Lazy-Umstellung | Abschluss gespeichert mit `content_hash` aus Rev. 4; `cc_course_progress` danach auf Rev. 5 korrekt; kein verlorenes Update (CAS) | §3.2 Nr. 6 | R1-27 (R1-42) |
| REV-037 | Backfill läuft, gleichzeitig greifen 50 Lernende zu; Backfill wird mittendrin abgebrochen und fortgesetzt | jede Zeile genau einmal umgestellt; Aggregate identisch zu vollständiger Neuberechnung | §3.2 | R1-27 (R1-40) |
| REV-038 | zwei Autoren veröffentlichen denselben Kurs gleichzeitig | einer erfolgreich, anderer `cc_publish_busy`; genau eine neue `revision_no` | §6 | R1-27 (R1-04) |
| REV-039 | Editor ändert Lektion eines veröffentlichten Kurses und speichert, ohne zu veröffentlichen | Lernende sehen alten Inhalt (Frontend, REST, Suche); Autor sieht Kopie in Vorschau; nach Veröffentlichen neuer Inhalt | §4.2 | R1-04 (R1-27) |
| REV-040 | Staging-Kopie vorhanden | nicht in `wp/v2`-REST für Lernende/Gäste, Sitemaps, Feeds, Suche, Menüs, `WP_Query` ohne Status; kein Slug-Konflikt mit Live-Post | §4.2 Nr. 7 | R1-04 (R1-37) |
| REV-041 | Import mit 120 geänderten Lektionen bricht in `importing` ab; danach `resume` bzw. `discard` | bis `activating` 0 sichtbare Änderungen; `discard` löscht alle Kopien des Batches; `resume` aktiviert alles in einer TX je Kurs | §4.2 | R1-15 (R1-41, IMP-022/024) |
| REV-042 | Import trifft auf Editor-Kopie derselben Lektion | Dry-run `conflict`; Standard Editor-Kopie bleibt; „Quelle übernehmen“ ersetzt sie (Audit) | §4.2 Nr. 6, E-63 | R1-15 |
| REV-043 | `WP_POST_REVISIONS = false`; Kurs mit geänderten Lektionen veröffentlichen | funktioniert unverändert | §4.3 | R1-04 |
| REV-044 | Kurs mit 800 Lektionen (über Schwellwert) veröffentlichen | chunkweise Übernahme mit Hinweis „Kurs wird aktualisiert“; Kursrevision erst nach letztem Chunk; Gates/Completion bis dahin auf alter Revision | §4.3 | R1-04 (R1-22) |
| REV-045 | Zertifikat (bestanden, 27/30); Nachbewertung → 28/30 / → 20/30 (nicht bestanden) / Versuch vorher 20/30 → 24/30 (bestanden) | 28/30: Korrektur `revision_no = 2`, alte `corrected`, alter Token zeigt „ersetzt“; 20/30: kein Widerruf, Prüfauftrag + Audit; 20→24: Erstausstellung | §5.2, §5.3 | R1-35 (R1-27, R1-09) |
| REV-046 | Zertifikat; danach Kursrevision, Kursrückzug, Refund, neue Template-Version, Profil-Namensänderung | Status `issued` unverändert, Snapshot unverändert, Verifikation gültig, Rendering mit alter `template_version`; Widerruf mit Grund → alle Revisionen `revoked`, kein Wiederaufleben; Rezertifizierung → neue `certificate_uid`, altes bleibt `issued` | RV-13, §5.4, §5.5 | R1-35 (R1-27, CORE-06/10/11) |

---

## 8. Entscheidungsbedarf (nur neue Punkte)

Bereits in `ENTSCHEIDUNGEN.md` und hier als Annahme verwendet: E-29, E-50, E-74, E-75 (außerdem E-14, E-24, E-47, E-51, E-63, E-67, E-68, E-70, E-71, E-72).

### 8.1 Neue Entscheidungen

| ID | Frage | Optionen | Empfehlung | Entscheider | blockiert |
|---|---|---|---|---|---|
| **R23-D1** | Grandfathering-Grundmodell für laufende Lernende | A strikter Bestandsschutz je Einschreiberevision · B Vorwärtsrollen mit Besitzstand, harter Bestandsschutz nur per neuer Kursausgabe · C harte Migration | **B** (§3.1). Anrechnung von Fortschritt zwischen Kursausgaben **nicht in R1** (Kandidat R2/Pro); in R1 nur Grant-Sammelvergabe für die neue Ausgabe durch den Kursmanager | Auftraggeber (Didaktik) | R1-04, R1-10, R1-27 |
| **R23-D2** | Wie bleiben CPT-Lektionen bis zur Aktivierung bzw. Veröffentlichung auf dem alten Stand? (R0-10 §3.4) | 0 live · A Schreiben erst in `activating` · B WP-Revisions-ID · C Staging-Kopie für Import **und** Editor · D eigene Inhaltstabelle | **C** (§4), mit A als Rückfall für sehr große Kurse. Folge für die Autoren-UX: Änderungen an Lektionen veröffentlichter Kurse werden erst mit „Kurs veröffentlichen“ sichtbar | Auftraggeber + Architektur | R1-04, R1-15, R1-41 |
| **R23-D3** | Darf ein Autor bei einer inhaltlichen Änderung „erneut bearbeiten erforderlich“ für einzelne Activities setzen? | a) nein, Abschlüsse sind immer Besitzstand · b) ja, je Activity beim Veröffentlichen, nur für Lernende ohne Kursabschluss, mit Audit und Hinweis · c) ja, auch für abgeschlossene Kurse | **b)** | Auftraggeber (Didaktik) | R1-10, R1-27 |
| **R23-D4** | Smarttrainer-Lernstand bei inhaltlicher Fragenkorrektur (`substantive`) | a) unverändert (Parität) · b) a) + ausdrückliche Admin-/Autorenaktion „Lernstand dieser Frage zurücksetzen“ (Stufe `unsure`, Serie 0, `due_at = NULL`, Zähler bleiben, Audit) · c) automatisch zurücksetzen | **b)**. c) wäre eine stille Neubewertung (ADR-005) | Auftraggeber (Didaktik) | R1-18, R1-10 |
| **R23-D5** | Zählen Versuche unter einer alten Prüfungsregelversion gegen `max_attempts` der neuen? | a) ja, je `activity_uid` über alle Versionen · b) nein, je Regelversion · c) a) als Standard, Autor kann beim Veröffentlichen „Versuchszähler zurücksetzen“ wählen (Audit) | **c)** | Auftraggeber | R1-08, R1-20 |
| **R23-D6** | Wie wird ein Kurs zurückgezogen? | a) sofort `archived` · b) zweistufig: „Einschreibung geschlossen“ (neue Grants für den Kurs werden mit `failed`/`course_closed` abgewiesen, bestehende Lernende lernen bis Grant-Ende weiter), danach `archived` mit Auslauf nach RV-04 · c) nur b) ohne `archived` | **b)**; ein sofortiges Archivieren bei aktiven Grants braucht eine Bestätigung mit Anzahl betroffener Lernender und Audit | Auftraggeber | R1-04, R1-13, R0-09 |
| **R23-D7** | Pflichtmedium einer Frage wird während eines laufenden Versuchs rechtlich gesperrt | a) Frage bleibt ohne Medium und zählt normal · b) Frage wird bei der Auswertung `neutralized` (aus Punkten und Maximum, Grund im Ergebnis), Regel über `evaluator_version` · c) Medium für laufende Versuche weiter ausliefern | **b)**; c) widerspricht der Sperrwirkung (E-18), a) ist unfair | Auftraggeber + Rechtsprüfung Betreiber | R1-09, R1-14 |
| **R23-D8** | Neuausstellung bestehender Zertifikate im neuen Layout (Template-Version) | a) nie, alte Version wird weiter gerendert · b) optionale Sammelaktion als Korrektur-Revision (gleiche Evidenz, neue `template_version`, Audit) | **a) in R1**, b) als Pro-Kandidat | Auftraggeber | R1-35, R0-29 |
| **R23-D9** | Kursabschluss durch Revision (letzte offene Pflicht-Activity entfällt) | a) automatisch beim Umstellen (`revision_recalc`), Zertifikat nach Kurseinstellung · b) automatisch, aber ohne automatisches Zertifikat (Ausstellung erst nach Bestätigung durch den Lernenden) · c) nie, Kursmanager muss abschließen | **a)**; der Pflichtumfang ist durch die gültige Revision definiert | Auftraggeber (Didaktik) | R1-10, R1-35 |

### 8.2 Technische Deltas (mit Freigabe von R0-06/R0-08 einzuarbeiten, keine Produktentscheidung)

| Δ | Änderung | Grund |
|---|---|---|
| Δ-23-1 | `cc_course_progress`: `completed_revision_id bigint NULL` (→`cc_course_revision` RESTRICT), `cycle_no smallint unsigned NOT NULL DEFAULT 1`; Index `course_rev (course_id, course_revision_id)` für den Backfill. `course_revision_id` = Lernstand-Revision | RV-09, §3.2, §3.3 |
| Δ-23-2 | `cc_course_revision`: `change_class varchar(8)` (`patch/minor`), `notes longtext NULL`; in `structure` je Activity `recompletion_required` (bool) und je Regel `reset_attempts` (bool) | §1.1, RV-07, RV-17 |
| Δ-23-3 | `cc_question_revision.change_class varchar(16)` (`editorial/substantive`) | RV-05, R23-D4 |
| Δ-23-4 | Post-Status `cc_staged` und `cc_archived` (nicht öffentlich) für `cc_lesson`/`cc_module`; Meta `_cc_stage_of`, `_cc_stage_batch`, `_cc_stage_action`; Staging-Kopien bekommen **keine** Zeile in `cc_source_map` (Δ-1 aus R0-08 gilt nur für Live-Objekte) | §4 |
| Δ-23-5 | `cc_exam_session.end_reason` zusätzlich für `cancelled`: `user`, `admin`, `withdrawn`; `cc_exam_session_question.neutralized tinyint(1) NOT NULL DEFAULT 0` (nur bei R23-D7 b) | RV-04, R23-D7 |
| Δ-23-6 | Kurs-Meta `_cc_enrollment` (`open/closed`) und `_cc_sunset_at` (UTC) | R23-D6 |
| Δ-23-7 | `cc_activity_progress.course_revision_id bigint NULL` (Revision beim Abschluss, Erklärbarkeit „abgeschlossen in Fassung 3“) | RV-07, §3.2 Nr. 6 |
| Δ-23-8 | R0-08 §6.2/§6.3: Teilumfangsprüfung im laufenden Versuch gegen eingefrorene Variantenposition (`variant.position` im Snapshot); R0-08 §1.4: Besitzstand-Ausnahme `unlocked_before_revision` für Drip und Voraussetzungen | RV-02, RV-14, RV-15 |

---

## 9. Nachweis

- Quelle: Clone `…/scratchpad/lb` @ `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (`git log -1`), nur gelesen.
- Gelesen: CR `deliverableQuestionSql` (:88-97), `questionsForModules` (:117-133), `revision` (:140-145), `answers` (:148-157), `papers` (:228-236); IS `rollback` (:139-164), `restoreActive` (:383-391); EI Kopfkommentar (:14-21), `deprecated` (:264, :314); ES `saveAnswer` (:405-484), `runningSession` (:1044-1069); LS `tokenContext` (:177-190), `answer` (:200-230), `summary` (:431-458), `setFavorite` (:460-470); VS `progress` (:236-262).
- Neu gegenüber R0-08: Befund B-5 (Teilumfangsprüfung im laufenden Versuch gegen Live-Variantenliste), B-4 (Lernmodus bewertet gegen Token-Revision, Auslieferbarkeit nur auf Fragenebene).
- Abgleich: R0-06 §4–§5 und Nachträge, R0-08 §1.3, §1.4, §2.3, §4.4, §5, §6, §7, R0-10 §3.4/§3.6, Konzept 28.3, 35, 37, `ENTSCHEIDUNGEN.md` (E-29, E-50, E-74, E-75 und weitere in §8).
- Keine Änderung an LearnBoating, keine Implementierung, kein Commit.
