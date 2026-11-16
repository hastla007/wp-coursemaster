# R0-22 · Kursvalidierung und Veröffentlichungsfreigabe (Entwurf)

**Status:** **Entwurf – Freigabe Auftraggeber/Didaktik/Architektur offen.** Task R0-22 ist **nicht** abgehakt. Es gibt keine Implementierung; Schnittstellen und Datenformate sind Spezifikation für R1-26.
**Stand:** 16.11.2026
**Bezug:** TODO R0-22 (Voraussetzung R0-08, R0-11), Ziel-Task **R1-26** (Publish-Validator und Fehlerführung), daneben R1-04, R1-06, R1-08, R1-14, R1-15, R1-27; Konzept Kap. 3.2 (Nr. 1, 3, 4, 5), 16.3 (Kurs-, Frage-, Prüfungseditor, Design-Gate), 28.4 (Abschlussregeln, Bewertungstransparenz, Kompetenznachweise, Autoren-UX-Test); `R0-08` (Struktur §1, Provider `validate()/freeze()` §2.2–2.3, Fragetypen §3, Bestehensregel/Zeitlimit §4, Nachweis §5.3, Zertifikat §7.2), `R0-10` (Rechtestatus §2.2, Medientypen §3.2 V4, externe Medien §5), `R0-11` (A-04, A-06, A-07, A-08, A-11, Flow 1 mit F1.1–F1.6), `R0-07` (`cc_edit_courses`, `cc_publish_courses`, `cc_release_rights`, M-01, M-03, M-04, M-06, M-07, M-21), `R0-23` (Kursrevision, Staging-Kopie R23-D2, Rückzug EV-08/RV-04/RV-20, Pool-Schrumpfung RV-18).
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77` (nur gelesen, nicht verändert). Pfade relativ zu `bootspruefung-learning/`, Schemas relativ zur Repo-Wurzel. **CR** = `src/Content/Repositories/ContentRepository.php`, **ES** = `src/Exams/Services/ExamService.php`, **PK** = `src/Content/Package/Package.php`, **SV** = `src/Content/Package/SchemaValidator.php`, **IS** = `src/Content/Import/ImportService.php`.

**Normative Sprache:** **MUSS** = Vertragsbestandteil; **SOLL** = Standard, begründete Abweichung erlaubt; **KANN** = optional.

---

## 0. Annahmen und Kurzfassung

### 0.1 Annahmen (Empfehlungen aus `ENTSCHEIDUNGEN.md`, noch nicht beschlossen)

| ID | Annahme | Wirkt auf |
|---|---|---|
| E-18 | Rechte-Auslieferungssperre (`unknown`, `restricted`) gilt **nur für importierte bzw. fremde** Inhalte, nicht für selbst erstellte | VAL-100 … VAL-105 |
| E-23 | Kein Modus „offiziell/verifiziert“. Prüfungsregel hat `draft/published/retired`; Freigabe über den Publish-Validator; Kennzeichnung `reduced` bleibt | §2.4, VAL-070 ff. |
| E-69 | R1-Fragetypen: `single_choice`, `multi_choice`, `true_false`, `open_text`, `flashcard`; `matching`, `ordering`, `cloze` Free in R2 | VAL-040 … VAL-062 |
| E-77 | Teilpunkt-Strategien in R1: `all_or_nothing`, `proportional_penalty`; `per_option` erst nach Didaktik-Review | VAL-056 |

Ebenfalls als Annahme: E-14 (relativer Drip je Modul in Free), E-45 („Klick zum Laden“), E-50 (Kursrevision fixiert Struktur und Regeln, nicht die Fragenrevisionen), E-65 (zugangsgebundene Medien geschützt), E-101 (kursinterne Voraussetzungen Free R1), R23-D2 (Staging-Kopie für Lektionen veröffentlichter Kurse).

### 0.2 Kurzfassung

1. **Ein Validator, mehrere Einstiege.** Dieselbe serverseitige Regelmenge (§1) läuft live im Editor, auf Anforderung, beim Einreichen, beim Veröffentlichen (verbindlich), im Import-Dry-run und täglich als Health-Prüfung veröffentlichter Kurse. Es gibt keinen zweiten Prüfpfad im Client.
2. **Drei Schweregrade.** **Blocker** verhindern Einreichen und Veröffentlichen über **jeden** Weg (Editor, REST, Block-Editor-Statuswechsel, Quick Edit, WP-CLI, Import-Aktivierung). **Warnungen** erlauben das Veröffentlichen erst nach ausdrücklicher Bestätigung, die mit Fingerabdruck auditiert wird. **Hinweise** brauchen keine Bestätigung.
3. **LB-Rückfälle werden geschlossen.** Lernende erreichen eine Prüfungsregel ausschließlich über die Referenz in der veröffentlichten Kursrevision (`rule uid + version`, Status `published`). Der LB-Rückfall von `examRule()` auf die neueste nicht archivierte Regel, auch einen Entwurf (CR:186-216), und der Standardwert `0.75` bei fehlender Bestehensregel (CR:207) entfallen ersatzlos.
4. **Vorschau als Lernender** simuliert Zugangszustände (neu eingeschrieben, Drip-Tag *n*, Teilumfang, Voraussetzung erfüllt) auf dem **Entwurfsstand** und schreibt **nichts** in Lerntabellen, Outbox, Mails, Events oder Lernenden-Audit.
5. **Autoren-UX:** Validierungspanel mit Gruppierung nach Objekt und Schwere, Inline-Fehler am Feld, Zusammenfassung vor dem Veröffentlichen mit Wirkungsvorschau, Meldungen mit Sprungziel, stabilem Code und Korrekturhilfe; WCAG 2.2 AA (Konzept 16.3).
6. **Katalog:** 118 Regeln in VAL-001 … VAL-145 (§1, Nummernblöcke je Objekt, Lücken reserviert; dazu VAL-000 für Validatorfehler), Testfälle **PUB-001 … PUB-052** für R1-26 (§4), acht neue Entscheidungen **D22-1 … D22-8** (§5).

---

## 1. Validierungsregel-Katalog

### 1.1 Begriffe und Grundregeln

| Begriff | Bedeutung |
|---|---|
| **Blocker** | Der Kurs (bzw. die Prüfungsregel) ist so nicht auslieferbar oder würde Lernende in einen falschen bzw. unerreichbaren Zustand bringen. **MUSS** Einreichen und Veröffentlichen verhindern. Kein Override, auch nicht für `administrator` (→ D22-1). |
| **Warnung** | Auslieferbar, aber mit erkennbarem Qualitäts-, Rechts- oder Barrierefreiheitsrisiko. Veröffentlichen erst nach Bestätigung je Warnung oder als Sammelbestätigung („alle 4 Warnungen gelesen“); Audit mit Fingerabdrücken (§1.3). |
| **Hinweis** | Verbesserungsvorschlag ohne Risiko für Lernende. Keine Bestätigung, ausblendbar je Objekt („nicht mehr anzeigen“, gespeichert am Objekt, nicht global). |
| **Kandidat** | Die Kursstruktur, die beim Veröffentlichen entstünde: Entwurf bzw. Staging-Kopie (R23-D2) + referenzierte Prüfungsregel-Entwürfe + aktuelle auslieferbare Fragenrevisionen. Der Validator prüft immer den Kandidaten, nie die veröffentlichte Revision allein. |
| **Pflichtpfad** | Alles, was ein Lernender für den Kursabschluss oder einen Nachweis braucht: Pflicht-Activities (`required = true`), Prüfungen, auf die `passed(...)` oder eine Zertifikatsregel verweist, sowie deren Fragenpools und Pflichtmedien (`image_required`). |
| **Auslieferbar** | Frage mit veröffentlichter Revision, nicht archiviert, nicht gültigkeitsabgelaufen, Rechte nicht sperrend (R0-10 §2.2) und mit freigegebenem Pflichtmedium (LB `deliverableQuestionSql`, CR:88-104). Poolgrößen zählen nur auslieferbare Fragen. |

**Grundregeln (MUSS):**

- Die Regeln sind **deterministisch** und laufen ausschließlich serverseitig. Der Client zeigt Ergebnisse nur an; eine Client-Vorprüfung darf existieren, ist aber nie maßgeblich.
- Jede Regel hat einen **stabilen Code** `VAL-nnn` (bleibt über Versionen gleich; entfallene Codes werden nicht neu vergeben), eine feste Schwere je Bedingung und einen i18n-Meldungsschlüssel mit Parametern. Codes erscheinen in REST-Antworten und im Audit, nicht als Hauptinformation im UI (dort nur klein als „Code VAL-073“ für Support).
- Activity-Provider steuern ihre Regeln über `validate(ActivityDefinition, ValidationContext): ValidationResult` bei (R0-08 §2.2). Provider-Codes haben das Präfix des Providers (`VAL-P-<provider>-nnn`) und dieselbe Struktur. Ein Provider kann die Schwere einer Core-Regel nicht herabsetzen.
- Ein Validator-Fehler (Exception in einer Regel) ist selbst ein **Blocker** `VAL-000` („Prüfung konnte nicht vollständig laufen“) – nie ein stilles Bestehen.
- Meldungen nennen **Objekt, Problem und nächsten Schritt** in Alltagssprache, ohne interne IDs, Tabellen- oder Feldnamen. Zahlen konkret („9 von 12 Fragen“).

### 1.2 Meldungsformat (REST und UI)

```
ValidationReport {
  course_uid, context: "live" | "manual" | "submit" | "publish" | "import_dry_run" | "health",
  candidate_hash: "sha256:…",          // Hash des Kandidaten (wird beim Publish als expected_hash zurückgegeben)
  validated_at: UTC, validator_version: "1.0.0",
  summary: { blocker: int, warning: int, notice: int, acknowledged: int },
  issues: [ Issue ]
}
Issue {
  code: "VAL-073", severity: "blocker" | "warning" | "notice",
  fingerprint: "sha256:…",             // code | object_uid | field | normalisierte Parameter
  object: { type: "course|module|lesson|activity|question|exam_rule|blueprint|variant|media|certificate_rule", uid, title },
  path: [ {type, uid, title} … ],      // Brotkrumen Kurs › Modul › Lektion
  field: "passing_rule.min_ratio" | null,
  message_key: "cc.val.exam.pool_too_small", params: { required: 12, available: 9, module: "Grundlagen" },
  message: "…",                        // serverseitig übersetzt (Sprache des Autors)
  target: { screen: "A-04|A-06|A-07|A-08|A-11|A-13", object_uid, field, anchor },
  fixes: [ { id: "reduce_blueprint_count", label, kind: "quick_fix|dialog|jump", preview: "12 → 9" } ],
  acknowledged: bool                   // nur Warnungen
}
```

- **Sprungziel** (`target`) öffnet den Editor am Objekt und setzt den Fokus auf das Feld (§3.2). Fehlt dem Autor das Recht für das Zielobjekt (z. B. Frage in fremdem Kurs, M-07), steht statt des Links „Nur für Kursverwaltung bearbeitbar“ (kein Existenzleck: nur Titel, den der Autor ohnehin im eigenen Kurs sieht).
- **Korrekturhilfen** (`fixes`): `quick_fix` ist eine deterministische, sofort angewandte Änderung **mit Vorschau** („12 → 9“) und Rückgängig (Autosave-Revision); `dialog` öffnet einen geführten Dialog (z. B. Zyklus auflösen: welche Kante entfernen?); `jump` springt nur zum Feld. **MUSS NICHT:** Korrekturhilfen entscheiden nie fachliche Inhalte (welche Antwort richtig ist, welcher Rechtestatus gilt). Sie werden nie automatisch ohne Klick angewandt.

### 1.3 Bestätigung von Warnungen

- Bestätigt wird der **Fingerabdruck** (`code | object_uid | field | Parameter`). Ändert sich das Problem (z. B. Pool von 9 auf 7), entsteht ein neuer Fingerabdruck und die Bestätigung gilt nicht mehr.
- Bestätigungen gelten für **einen** Veröffentlichungsvorgang und werden mit `revision_no` im Audit (`course_published`, Feld `acknowledged_warnings[]`) gespeichert, nicht als Dauerzustand am Objekt. Bei der nächsten Revision erscheinen unveränderte Warnungen als „bereits bei Revision 4 bestätigt“ vorausgewählt, müssen aber erneut bestätigt werden.
- Hinweise, die ein Autor ausblendet, bleiben im Report mit `notice` und `hidden = true` (für Manager sichtbar).

### 1.4 Katalog

Spalten: **Obj.** = Objekt; **S** = Schwere (**B** Blocker, **W** Warnung, **H** Hinweis); **Ziel** = Sprungziel-Screen (R0-11). Meldungstexte sind Formulierungsvorgaben (i18n-Quelltext, Platzhalter in `{}`).

#### Kurs (VAL-001 … VAL-019)

| Code | Obj. | Bedingung | S | Meldung (Sprungziel) | Korrekturhilfe |
|---|---|---|---|---|---|
| VAL-001 | Kurs | Titel leer oder nur Leerzeichen | B | „Der Kurs hat noch keinen Titel.“ (A-04, Feld Titel) | jump |
| VAL-002 | Kurs | `language` fehlt oder ist keine gültige WP-Locale | B | „Lege die Kurssprache fest. Sie steuert Datumsformate und Vorlesefunktionen.“ (A-04 › Metadaten) | quick_fix „Site-Sprache {locale} übernehmen“ |
| VAL-003 | Kurs | keine Module im Kandidaten | B | „Der Kurs hat noch keine Module. Lege mindestens ein Modul mit einer Lektion an.“ (A-04 › Curriculum) | dialog „Erstes Modul anlegen“ |
| VAL-004 | Kurs | keine einzige Pflicht-Activity (alle `required = false`) | W | „Kein Inhalt ist als Pflicht markiert. Lernende gelten damit sofort als fertig.“ (A-04 › Curriculum) | quick_fix „Alle Lektionen als Pflicht markieren“ |
| VAL-005 | Kurs | Kurs ist öffentlich im Katalog, aber Einschreibemodus `manual` ohne Hinweistext | H | „Der Kurs erscheint im Katalog, ist aber nur manuell zugänglich. Erkläre im Kurstext, wie man Zugang bekommt.“ (A-04 › Zugang) | jump |
| VAL-006 | Kurs | Beschreibung/Auszug leer | H | „Ohne Kurzbeschreibung zeigt der Katalog nur den Titel.“ (A-04 › Metadaten) | jump |
| VAL-007 | Kurs | Teilumfang (`free_limited`) ≥ Gesamtzahl der Elemente des Abschnitts | H | „Der kostenlose Teilumfang umfasst bereits den ganzen Abschnitt {section}.“ (A-04 › Zugang) | quick_fix „Teilumfang auf {n−1} setzen“ |
| VAL-008 | Kurs | Kandidat ist identisch mit der veröffentlichten Revision (`candidate_hash` = `structure_hash`) | H | „Es gibt keine Änderungen gegenüber Revision {no}. Veröffentlichen erzeugt keine neue Revision.“ | – |
| VAL-009 | Kurs | gleichzeitige Bearbeitung: Kandidat basiert nicht auf der neuesten Entwurfsversion (CAS) | B | „Jemand anderes hat den Kurs inzwischen geändert. Lade die neue Fassung und prüfe erneut.“ | dialog „Änderungen vergleichen“ (F1.5) |
| VAL-010 | Kurs | Kurs ist `archived` (zurückgezogen) und soll veröffentlicht werden, aber `cc_publish_courses` + Reaktivierungsbegründung fehlt | B | „Ein zurückgezogener Kurs braucht zum Reaktivieren eine Begründung.“ | dialog |
| VAL-011 | Kurs | Lernziel (`objective_uid`) ohne Titel oder ohne Zuordnung zu einer Activity/Frage | W | „Das Lernziel {title} ist keiner Lektion, Frage oder Prüfung zugeordnet und kann nie nachgewiesen werden.“ (A-04 › Lernziele) | jump |

#### Modul, Lektion, Activity (VAL-020 … VAL-039)

| Code | Obj. | Bedingung | S | Meldung (Sprungziel) | Korrekturhilfe |
|---|---|---|---|---|---|
| VAL-020 | Modul | Modul ohne Lektionen | B | „Das Modul {module} ist leer. Füge eine Lektion hinzu oder entferne das Modul aus dem Kurs.“ (A-04 › Modul) | quick_fix „Modul aus diesem Kurs entfernen“ (nur Zuordnung, Modul bleibt); dialog „Lektion anlegen“ |
| VAL-021 | Modul | Titel leer | B | „Ein Modul an Position {pos} hat keinen Titel.“ | jump |
| VAL-022 | Modul | alle Lektionen des Moduls optional, Modul aber `required` | W | „{module} ist Pflicht, enthält aber nur optionale Lektionen. Es zählt damit nie für den Abschluss.“ | quick_fix „Modul optional“ bzw. „Lektionen Pflicht“ |
| VAL-023 | Lektion | Lektion ohne Activity bzw. `activity_type` fehlt | B | „Die Lektion {lesson} hat keinen Inhaltstyp. Wähle Text, Video, Datei, Quiz oder Karteikarten.“ (A-04 › Lektion) | dialog „Typ wählen“ |
| VAL-024 | Activity | Provider des Typs fehlt/deaktiviert oder `contract_version`-Major wird vom installierten Provider nicht unterstützt (R0-08 §2.3) | B | „Der Inhaltstyp {type} von {lesson} ist derzeit nicht verfügbar (Erweiterung fehlt oder ist zu alt).“ (A-14 Integrationen bzw. Plugins) | jump |
| VAL-025 | Activity | Provider-`validate()` liefert Fehler (Settings gegen Schema) | B | Text vom Provider, z. B. „Mindestanzeigedauer muss zwischen 0 und 3 600 Sekunden liegen.“ | Provider-Fix |
| VAL-026 | Activity `lesson` | Inhalt leer (keine Blöcke außer leeren Absätzen) | B | „Die Lektion {lesson} hat noch keinen Inhalt.“ | jump |
| VAL-027 | Activity `video` | keine Quelle, Provider unbekannt oder Datei fehlt | B | „Für das Video in {lesson} fehlt die Quelle.“ (A-04 › Lektion › Video) | jump |
| VAL-028 | Activity `file` | kein Download hinterlegt oder Datei fehlt im Speicher | B | „Die Datei für {lesson} fehlt. Lade sie erneut hoch.“ (A-06, Filter „ohne Datei“) | jump |
| VAL-029 | Activity `quiz` | Quiz ohne Fragen bzw. leerer Modulpool | B | „Das Quiz {lesson} enthält keine auslieferbaren Fragen.“ (A-07) | dialog „Fragen zuordnen“ |
| VAL-030 | Activity | Abschlussregel passt nicht zum Typ (z. B. `passed` bei unbewertetem Quiz, `downloaded` bei `lesson`) oder Parameter außerhalb des Bereichs (`position_ratio` ∉ (0, 1], `ratio` ∉ (0, 1], `min_seconds` < 0) | B | „Die Abschlussregel ‚{rule}‘ ist für {type} nicht möglich.“ (A-04 › Lektion › Abschluss) | quick_fix „Standardregel {default} verwenden“ (R0-08 §2.4) |
| VAL-031 | Activity `video` | Abschlussregel `position_ratio` | H | „Die Abspielposition meldet der Player selbst. Sie ist kein sicherer Nachweis, dass das Video angesehen wurde.“ | – |
| VAL-032 | Activity `quiz` | `counts_as_evidence = true`, Quiz aber unbewertet | B | „Ein unbewertetes Quiz kann kein Nachweis sein.“ | quick_fix „bewertet schalten“ / „Nachweis aus“ |
| VAL-033 | Lektion | doppelter Lektionstitel innerhalb eines Moduls | H | „Zwei Lektionen in {module} heißen {title}. Lernende können sie im Kapitelbaum nicht unterscheiden.“ | jump |
| VAL-034 | Activity | Typwechsel einer bereits veröffentlichten Activity (`activity_uid` gleich, Typ anders) | B | „Der Typ einer veröffentlichten Lektion kann nicht geändert werden. Lege eine neue Lektion an und archiviere die alte.“ (R0-08 §2.3) | dialog „Als neue Lektion anlegen“ |
| VAL-035 | Activity | Pflicht-Activity in der neuen Revision entfernt, Lernende haben Fortschritt darauf | W | „{n} Lernende haben {lesson} begonnen oder abgeschlossen. Der Abschluss bleibt erhalten, offener Fortschritt entfällt.“ (R0-23 RV-07) | – (Wirkungsvorschau §3.3) |
| VAL-036 | Activity | `recompletion_required` gesetzt | W | „{n} Lernende ohne Kursabschluss müssen {lesson} erneut abschließen.“ (R0-23 R23-D3) | – |

#### Frage (VAL-040 … VAL-062)

Gilt für jede Frage, die im Kandidaten erreichbar ist (Inline-Quiz, Modulpool eines Quiz- oder Prüfungs-Blueprints, feste Variante). Normalisierung für Vergleiche: `trim`, Unicode-NFC, Groß-/Kleinschreibung ignorieren, Leerzeichen zusammenfassen, HTML-Tags entfernen (R0-08 §3.1).

| Code | Obj. | Bedingung | S | Meldung (Sprungziel A-07, Feld) | Korrekturhilfe |
|---|---|---|---|---|---|
| VAL-040 | Frage | Fragetext leer und kein Pflichtmedium | B | „Die Frage hat keinen Text.“ | jump |
| VAL-041 | Frage | Fragetyp in R1 nicht freigegeben (`matching`, `ordering`, `cloze` vor R2; Pro-Typ ohne aktives Add-on) | B | „Der Fragetyp {type} ist in dieser Version nicht verfügbar.“ | – |
| VAL-042 | `single_choice` | keine Option als richtig markiert | B | „Markiere genau eine richtige Antwort.“ (Feld Antworten) | jump |
| VAL-043 | `single_choice` | mehr als eine Option richtig | B | „Bei Einfachauswahl darf nur eine Antwort richtig sein. Es sind {n} markiert.“ | quick_fix „In Mehrfachauswahl umwandeln“ (Vorschau der Bewertungsregel `all_or_nothing`) |
| VAL-044 | `multi_choice` | keine Option richtig | B | „Markiere mindestens eine richtige Antwort.“ | jump |
| VAL-045 | `single/multi` | weniger als 2 oder mehr als 26 Optionen (Schema-Parität `^[A-Z]$`) | B | „Eine Auswahlfrage braucht 2 bis 26 Antworten, hier sind es {n}.“ | jump |
| VAL-046 | Option | Option ohne Text und ohne Medium | B | „Antwort {key} ist leer.“ | quick_fix „Leere Antwort entfernen“ |
| VAL-047 | Option | zwei Optionen mit gleichem normalisiertem Text (und gleichem Medium), **eine richtig, eine falsch** (widersprüchlich) | B | „Die Antworten {a} und {b} sind gleich, aber unterschiedlich bewertet.“ | dialog „Welche Antwort behalten?“ |
| VAL-048 | Option | zwei Optionen gleich (normalisiert), gleiche Bewertung | W | „Die Antworten {a} und {b} sind doppelt.“ | quick_fix „Doppelte Antwort entfernen“ |
| VAL-049 | Option | doppelter Antwortschlüssel (`answer_key`) | B | „Zwei Antworten haben den Schlüssel {key}.“ | quick_fix „Schlüssel neu vergeben (A, B, C …)“ |
| VAL-050 | Option | widersprüchliche Sammeloptionen: „Alle genannten“ richtig, aber eine andere Option falsch; „Keine der genannten“ richtig zusammen mit einer weiteren richtigen Option (Erkennung über Option-Flag `role = all_of_the_above / none_of_the_above`, nicht über Textsuche) | B | „‚{label}‘ widerspricht den übrigen Markierungen.“ | jump |
| VAL-051 | Option | mehrere Optionen `pinned` auf derselben Position | B | „Zwei Antworten sind auf Position {pos} fixiert.“ | quick_fix „Zweite Fixierung lösen“ |
| VAL-052 | `multi_choice` | **alle** Optionen richtig | W | „Alle Antworten sind richtig. Wer alles ankreuzt, bekommt volle Punkte.“ | jump |
| VAL-053 | `true_false` | nicht genau 2 feste Optionen oder nicht genau 1 richtig | B | „Wahr/Falsch braucht genau eine richtige Aussage.“ | quick_fix „Standardoptionen wiederherstellen“ |
| VAL-054 | `open_text` | Musterantwort fehlt, leer oder mehr als eine | B | „Hinterlege genau eine Musterantwort. Lernende vergleichen ihre Antwort damit.“ | jump |
| VAL-055 | `flashcard` | Vorder- oder Rückseite leer | B | „Die Karteikarte braucht Vorder- und Rückseite.“ | jump |
| VAL-056 | Frage/Regel | `scoring` nicht in R1 erlaubt (`per_option`, E-77) bzw. `per_option` mit < 3 Optionen | B | „Die Teilpunktregel {scoring} ist hier nicht verfügbar.“ | quick_fix „Alles-oder-nichts verwenden“ |
| VAL-057 | Frage | `image_required = true`, aber kein Pflichtmedium | B | „Die Frage verlangt ein Bild, es ist aber keins hinterlegt.“ | jump |
| VAL-058 | Frage | keine veröffentlichte Revision (nur Entwurf) | W | „Die Frage ist noch Entwurf und wird Lernenden nicht gezeigt. Sie fehlt in {n} Pools.“ | quick_fix „Revision veröffentlichen“ (nur mit M-07 + `cc_publish_courses`) |
| VAL-059 | Frage | Gültigkeit (`valid_to`) abgelaufen bzw. läuft in ≤ 30 Tagen ab | W / H | „Die Frage ist seit {date} nicht mehr gültig“ bzw. „läuft am {date} ab.“ | jump |
| VAL-060 | Frage | `feedback_correct`/`feedback_incorrect` leer, Kurs zeigt Erklärungen (`show_explanations = true`) | H | „Ohne Erklärung sehen Lernende nach der Antwort nur richtig/falsch.“ | jump |
| VAL-061 | Frage | Fragenrevision ist `substantive` geändert seit letzter Kursrevision und in einer Prüfung mit Nachweis | H | „Die Lösung von {question} wurde geändert. Laufende Versuche behalten die alte Fassung.“ (R0-23) | – |
| VAL-062 | Frage | HTML-Filter hat beim Speichern Inhalte entfernt (Script, Iframe, Event-Attribute) | W | „Beim Speichern wurden nicht erlaubte Elemente entfernt. Prüfe die Darstellung.“ | jump (Vorschau) |

#### Prüfung und Quiz-Regel (VAL-070 … VAL-099)

Gilt für `exam`-Activities und bewertete Quizze (Modus `quiz`, E-68) und ihre Prüfungsregel (`cc_exam_rule`) samt Blueprints bzw. Varianten.

| Code | Obj. | Bedingung | S | Meldung (Sprungziel A-08) | Korrekturhilfe |
|---|---|---|---|---|---|
| VAL-070 | Prüfung | `exam`-Activity ohne Regelreferenz | B | „Die Prüfung {lesson} ist mit keiner Prüfungsregel verbunden.“ | dialog „Regel wählen/anlegen“ |
| VAL-071 | Regel | referenzierte Regel ist `retired` oder archiviert | B | „Die Prüfungsregel {rule} ist außer Kraft. Wähle die aktuelle Version.“ | quick_fix „Auf Version {v} umstellen“ |
| VAL-072 | Regel | referenzierte Regel ist `draft` | H | „Die Prüfungsregel {rule} (Entwurf) wird mit diesem Kurs veröffentlicht.“ (§2.4) | – |
| VAL-073 | Regel | `passing_rule` fehlt oder ist nicht lesbar | B | „Lege fest, ab wann die Prüfung bestanden ist. Ohne Bestehensregel kann niemand die Prüfung starten.“ (Feld Bestehensgrenze) | quick_fix „Vorschlag aus Einstellungen übernehmen ({num}/{den})“ – speichert einen **expliziten** Wert (R0-08 §4.1) |
| VAL-074 | Regel | `min_ratio` ungültig: `den = 0`, `num ≤ 0`, `num/den > 1` | B | „Die Bestehensgrenze muss zwischen 1 % und 100 % liegen.“ | jump |
| VAL-075 | Regel | Teilregel: `min_correct > of`, `of` ≠ Fragenzahl des Abschnitts, Teil verweist auf nicht vorhandenen Abschnitt, `min_correct` und `min_points_milli` innerhalb einer Regel gemischt | B | „Teil {part}: {min} von {of} richtigen Antworten ist nicht erreichbar, der Abschnitt hat {count} Fragen.“ | quick_fix „{of} an Abschnitt anpassen“ |
| VAL-076 | Regel | `empty_part_policy` fehlt | B | „Lege fest, ob ein Teil ohne Fragen als bestanden gilt.“ | quick_fix „Bestanden (wie bisher üblich)“ – expliziter Wert |
| VAL-077 | Regel | Bestehensgrenze < 50 % | H | „Die Bestehensgrenze von {p} % liegt unter der Hälfte.“ | – |
| VAL-078 | Regel | „Alles ankreuzen“ erreicht die Bestehensgrenze (Simulation: je Frage alle Optionen gewählt, wirksames `scoring`, Ergebnis ≥ Grenze für **irgendeine** mögliche Ziehung; Prüfung über Obergrenze: Summe der besten *k* Fragen je Abschnitt) | B | „Wer bei jeder Frage alles ankreuzt, besteht diese Prüfung. Prüfe Fragen mit nur richtigen Antworten.“ (Golden-Master-Anker INV-E-008) | jump (Liste der betroffenen Fragen) |
| VAL-079 | Regel | `time_limit_seconds` ≤ 0, keine Ganzzahl oder > 86 400 (24 h) | B | „Das Zeitlimit muss zwischen 1 Sekunde und 24 Stunden liegen oder leer sein (ohne Limit).“ | quick_fix „Ohne Limit“ / „{n} × 60 s“ |
| VAL-080 | Regel | Zeitlimit < 10 s je gezogener Frage | W | „{n} Fragen in {t} – das sind unter 10 Sekunden pro Frage.“ | quick_fix „{n} × 60 s“ |
| VAL-081 | Regel | Teilzeiten (`parts[].seconds`) nur bei einem Teil der Abschnitte gesetzt bzw. Summe ≠ Gesamtlimit | W | „Nur einige Teile haben eine Zeit. Das Gesamtlimit wird dann nicht aus Teilzeiten berechnet.“ (R0-08 §4.1, ES:982-1007) | jump |
| VAL-082 | Regel | Grace oder Kulanz außerhalb 0–600 s | B | „Die Nachsendefrist muss zwischen 0 und 600 Sekunden liegen.“ | quick_fix „Standard 120 s“ |
| VAL-083 | Blueprint | Blueprint verweist auf Modul, das nicht im Kurs ist | B | „Der Abschnitt {section} zieht Fragen aus {module}, das nicht zu diesem Kurs gehört.“ | dialog „Modul wählen“ |
| VAL-084 | Blueprint | `question_count` < 1 | B | „Abschnitt {section}: Fragenzahl muss mindestens 1 sein.“ | jump |
| VAL-085 | Blueprint | auslieferbarer Pool = 0 | B | „Abschnitt {section} hat keine einzige auslieferbare Frage.“ | jump (A-07 gefiltert) |
| VAL-086 | Blueprint | `question_count` > auslieferbarer Pool, Prüfung liegt im **Pflichtpfad** (Nachweis, Zertifikat, `passed(...)`-Voraussetzung, Abschlussregel `passed`) | B | „Abschnitt {section} fordert {required} Fragen, es gibt aber nur {available} auslieferbare. Die Prüfung würde nur verkürzt als Übung laufen und könnte nie als Nachweis zählen.“ | quick_fix „Fragenzahl auf {available} senken“; jump „Fragen ergänzen“; Liste „{x} Fragen wegen Rechten gesperrt, {y} Entwurf“ |
| VAL-087 | Blueprint | wie VAL-086, Prüfung **nicht** im Pflichtpfad | W | „… Die Prüfung läuft dann verkürzt als Übung (Kennzeichnung ‚verkürzt‘).“ (Parität ES:243-246) | wie VAL-086 |
| VAL-088 | Blueprint | Pools mehrerer Abschnitte überschneiden sich (Modul in zwei Blueprints oder Frage in zwei Modulen); Berechnung mit Doppelausschluss in Abschnittsreihenfolge wie ES:237-252 | H | „Abschnitt {a} und {b} teilen sich {n} Fragen. Für {b} bleiben {m}.“ | – |
| VAL-089 | Variante | feste Variante enthält nicht auslieferbare Frage | B | „Der feste Bogen {variant} enthält {n} Fragen, die Lernenden nicht gezeigt werden dürfen. Er wäre nicht startbar.“ (R0-23 RV-18) | dialog „Frage ersetzen“ |
| VAL-090 | Variante | Variante leer bzw. Positionen doppelt/lückenhaft | B | „Der Bogen {variant} ist leer“ bzw. „Position {pos} ist doppelt.“ | quick_fix „Positionen neu nummerieren“ |
| VAL-091 | Regel | `max_attempts = 0`, `cooldown_seconds < 0` | B | „Die Zahl der Versuche muss mindestens 1 sein oder unbegrenzt.“ | quick_fix „unbegrenzt“ |
| VAL-092 | Regel | Frage gehört zu keinem Teil der Bestehensregel (`question_parts`) | W | „{n} Fragen zählen nur zur Gesamtquote, zu keinem Teil.“ | jump |
| VAL-093 | Regel | `valid_until` liegt in der Vergangenheit | B | „Die Prüfungsregel ist seit {date} abgelaufen.“ | jump |
| VAL-094 | Regel | `valid_from` liegt in der Zukunft | W | „Die Prüfung ist erst ab {date} startbar.“ | – |
| VAL-095 | Regel | `producesEvidence` und Pool/Variante enthält `open_text` mit `open_text_in_score = true` | W | „Selbsteinschätzungsfragen zählen in einer Nachweisprüfung mit.“ (R0-08 §3.3) | quick_fix „Offene Fragen nicht werten“ |
| VAL-096 | Regel | `feedback_visibility = always` bei bewertetem Versuch | H | „Während einer bewerteten Prüfung gibt es keine Lösungen. ‚Immer‘ wirkt hier wie ‚nach Abgabe‘.“ | quick_fix „Nach Abgabe“ |
| VAL-097 | Regel | Prüfung enthält Fragen mit externen Medien (VAL-110) | W | „{n} Prüfungsfragen laden Medien von {provider}. Die Zustimmung kostet Lernende Prüfungszeit.“ | jump |
| VAL-098 | Prüfung | Prüfung ist per Drip/Teilumfang nie startbar, weil ein Blueprint-Modul dauerhaft gesperrt ist (z. B. Teilumfang ohne Grant-Weg, Drip > maximale Zugangsdauer) | B | „Die Prüfung kann nie starten: Modul {module} wird erst nach {d} Tagen frei, der Zugang dauert aber nur {g} Tage.“ | jump (A-04 › Zugang) |

#### Medium und Rechte (VAL-100 … VAL-119)

„Fremd“ und „eigen“ nach R0-10 §2.2. Medien schließen Bilder, Audio, Video, Dateien, Untertitel und Fragenmedien ein.

| Code | Obj. | Bedingung | S | Meldung (Sprungziel A-06 bzw. A-13 Rechte) | Korrekturhilfe |
|---|---|---|---|---|---|
| VAL-100 | Medium/Inhalt | **fremd**, Rechtestatus `unknown` oder `restricted` bzw. `valid_until` abgelaufen, im **Pflichtpfad** | B | „{n} importierte Inhalte haben ungeklärte Rechte und werden Lernenden nicht gezeigt, darunter {title} in {lesson}.“ (A-13 › Rechte & Freigabe, gefiltert) | jump „Rechte prüfen“ (nur mit `cc_release_rights`, sonst „Kursverwaltung informieren“); quick_fix „Medium aus Lektion entfernen“ |
| VAL-101 | Medium/Inhalt | wie VAL-100, außerhalb des Pflichtpfads | W | „… An dieser Stelle sehen Lernende den Platzhalter ‚Medium nicht verfügbar‘.“ | wie VAL-100 |
| VAL-102 | Medium | **eigen**, Status `restricted` | W | „{title} ist als ‚darf nicht gezeigt werden‘ markiert, ist aber eigenes Material und wird ausgeliefert.“ (E-18) | jump |
| VAL-103 | Medium | eigen, `unknown` | H | „Rechte für {title} sind nicht dokumentiert.“ | quick_fix „Als eigenes Material markieren“ |
| VAL-104 | Medium/Inhalt | Status `review` | W | „{title} ist noch in Rechteprüfung.“ | jump |
| VAL-105 | Medium | `licensed`/`public` ohne Attribution, obwohl Lizenz sie verlangt (Lizenz-Registry: CC-BY*, CC-BY-SA* …) | W | „Die Lizenz {license} verlangt eine Namensnennung. Ergänze sie.“ | jump (Feld Attribution) |
| VAL-106 | Medium | fremde Lizenz läuft in ≤ 30 Tagen ab | H | „Die Lizenz für {title} endet am {date}.“ | – |
| VAL-107 | Medium | Datei fehlt im Speicher bzw. Hash stimmt nicht | B | „Die Datei {title} fehlt oder ist beschädigt.“ (A-06 „ohne Datei“) | jump „Neu hochladen“ |
| VAL-108 | Medium | Endung/MIME nicht in Allowlist oder Endung ≠ erkannter MIME (R0-10 V4) | B | „{title} hat einen nicht erlaubten Dateityp ({mime}).“ | quick_fix „Medium entfernen“ |
| VAL-109 | Medium | zugangsgebundenes Medium liegt in Speicherklasse `public` (E-65) | W | „{title} ist öffentlich abrufbar, obwohl es zu einem geschützten Kurs gehört.“ | quick_fix „Geschützt speichern“ |
| VAL-110 | Medium extern | Host keinem registrierten Provider zugeordnet (Filter `course_captain_external_media_providers`) | B | „Das Video in {lesson} kommt von {host}. Für diesen Dienst gibt es keinen Datenschutz-Platzhalter, es kann nicht eingebunden werden.“ | jump; quick_fix „Als Link statt Einbettung“ |
| VAL-111 | Medium extern | Provider registriert, aber ohne Datenschutzhinweis (Dienstname, Anbieter, Link) | B | „Für {provider} fehlt der Datenschutzhinweis im Platzhalter.“ (A-15 › Datenschutz) | jump |
| VAL-112 | Medium extern | kein Consent-Provider aktiv (WP Consent API/Filter, R0-10 §5.3), Fallback „Klick zum Laden“ greift | H | „Es ist kein Einwilligungs-Tool verbunden. Lernende laden externe Medien einzeln per Klick.“ | – |
| VAL-113 | Medium extern | Activity `video` mit Abschlussregel `position_ratio` und externem Provider ohne Player-API | W | „{provider} meldet keine Abspielposition. Lernende können die Lektion nur bestätigen.“ | quick_fix „Abschluss per Bestätigung“ |
| VAL-114 | Medium extern | lokales Posterbild fehlt (Platzhalter würde Fremd-Vorschaubild brauchen) | H | „Ohne eigenes Vorschaubild zeigt der Platzhalter eine neutrale Grafik.“ | jump |

#### Freischaltung, Voraussetzungen, Erreichbarkeit (VAL-120 … VAL-129)

Der Validator baut einen gerichteten Graphen aus Voraussetzungen (`completed(activity|module)`, `passed(exam)`, R0-08 §1.4) und prüft ihn zusammen mit Drip und Teilumfang für einen **neu eingeschriebenen Lernenden mit vollem Zugang** (Simulation, keine Daten).

| Code | Obj. | Bedingung | S | Meldung (Sprungziel A-04 › Zugang & Freischaltung) | Korrekturhilfe |
|---|---|---|---|---|---|
| VAL-120 | Kurs | Zyklus im Voraussetzungsgraphen (Tarjan/SCC; auch über Modul-Aggregation: Lektion in M1 verlangt M2, Lektion in M2 verlangt M1) | B | „Diese Inhalte warten gegenseitig aufeinander: {a} → {b} → {a}. Niemand kann sie freischalten.“ (Pfad als Liste) | dialog „Welche Voraussetzung entfernen?“ (Kanten zur Auswahl) |
| VAL-121 | Activity | Voraussetzung verweist auf nicht vorhandene, archivierte oder aus der Revision entfernte Activity/Modul | B | „{lesson} setzt {missing} voraus, das es nicht mehr gibt.“ (R0-23 P-6: kein stilles „frei“) | quick_fix „Voraussetzung entfernen“ |
| VAL-122 | Activity | unerreichbar: Voraussetzung `passed(exam)` auf eine Prüfung mit Blocker (VAL-070 … VAL-098) oder auf eine Prüfung, die nur `reduced` laufen kann | B | „{lesson} ist nie erreichbar, weil die Prüfung {exam} nicht bestanden werden kann.“ | jump zur Prüfung |
| VAL-123 | Activity | unerreichbar: Voraussetzung auf **optionale** Activity, die selbst gesperrt ist (Teilumfang/Drip) und keinen Weg zur Freischaltung hat | B | „{lesson} ist für niemanden erreichbar.“ (mit Begründungskette) | jump |
| VAL-124 | Modul | Drip-Tage > maximale Zugangsdauer aller Zugangswege des Kurses (Grant-Laufzeit, Free-Regel) | W | „{module} wird erst nach {d} Tagen frei, der Zugang endet aber nach {g} Tagen.“ | quick_fix „Drip auf {g−1} Tage“ |
| VAL-125 | Modul | Drip-Reihenfolge widerspricht Voraussetzung (B setzt A voraus, A wird später frei als B) | H | „{b} ist ab Tag {db} sichtbar, aber erst nach {a} (ab Tag {da}) bearbeitbar.“ | – |
| VAL-126 | Activity | Prüfung frei, aber ein Blueprint-Modul ist per Drip länger gesperrt (Prüfung startet erst nach Tag *d*) | H | „Die Prüfung {exam} ist erst ab Tag {d} startbar, weil {module} vorher gesperrt ist.“ (ES:228-236) | – |
| VAL-127 | Kurs | Voraussetzung auf Activity eines anderen Kurses (kursübergreifend = Pro-Lernpfad) | B | „Voraussetzungen auf andere Kurse sind in dieser Version nicht möglich.“ (E-101) | quick_fix „Voraussetzung entfernen“ |
| VAL-128 | Kurs | Tiefe der Voraussetzungskette > 50 oder > 500 Kanten | W | „Die Freischaltkette ist sehr lang ({n} Schritte). Prüfe, ob alle Voraussetzungen nötig sind.“ | – |

#### Zertifikat und Nachweis (VAL-130 … VAL-139)

| Code | Obj. | Bedingung | S | Meldung (Sprungziel A-11 bzw. A-04 › Zertifikat) | Korrekturhilfe |
|---|---|---|---|---|---|
| VAL-130 | Zertifikatsregel | Ausstellung aktiv, `evidence_type = exam_session`, aber keine referenzierte Prüfung bzw. Prüfung ohne `producesEvidence` | B | „Das Zertifikat soll nach einer Prüfung ausgestellt werden, es ist aber keine nachweisfähige Prüfung gewählt.“ | dialog „Prüfung wählen“ |
| VAL-131 | Zertifikatsregel | Nachweisprüfung kann nur `reduced` laufen (VAL-086) oder hat einen Blocker | B | „Mit der Prüfung {exam} kann niemand ein Zertifikat erhalten, weil sie nur verkürzt laufen kann.“ | jump |
| VAL-132 | Zertifikatsregel | `evidence_type = course_completion`, aber Kurs hat keine Pflicht-Activity (VAL-004) | B | „Das Zertifikat würde ohne jede Leistung ausgestellt.“ | jump |
| VAL-133 | Zertifikatsregel | `course_completion` ohne bewertete Activity im Pflichtpfad | W | „Das Zertifikat bestätigt nur, dass Inhalte bearbeitet wurden, nicht dass etwas geprüft wurde.“ (28.4 Nr. 4) | – |
| VAL-134 | Zertifikatsregel | Zertifikat nennt Lernziele, aber `certifies_objectives` der Prüfung leer bzw. Ziele nicht in der Prüfung abgedeckt | W | „Das Zertifikat nennt {objective}, die Prüfung weist es aber nicht nach.“ | jump |
| VAL-135 | Zertifikatsregel | Vorlage (`template_version`) fehlt oder Pro-Vorlage ohne aktives Add-on | B | „Die Zertifikatsvorlage ist nicht verfügbar. Wähle die Basisvorlage.“ | quick_fix „Basisvorlage verwenden“ |
| VAL-136 | Zertifikatsregel | `competency_validity_days` < 1 oder > 3 650 | B | „Die Gültigkeit muss zwischen 1 Tag und 10 Jahren liegen oder unbegrenzt sein.“ | jump |
| VAL-137 | Zertifikatsregel | Ausstellerangabe (Name der Organisation) leer | W | „Auf dem Zertifikat steht kein Aussteller.“ | quick_fix „Site-Titel verwenden“ |

#### Barrierefreiheit (VAL-140 … VAL-145)

| Code | Obj. | Bedingung | S | Meldung | Korrekturhilfe |
|---|---|---|---|---|---|
| VAL-140 | Medium Bild | Alt-Text leer und nicht als dekorativ markiert (Lektion, Frage, Option) | W (→ D22-4) | „{n} Bilder haben keinen Alternativtext, z. B. in {lesson}. Screenreader-Nutzende erfahren nicht, was zu sehen ist.“ (A-06 bzw. Block) | dialog „Alt-Text eingeben“ je Bild; quick_fix „Als dekorativ markieren“ (setzt `alt=""` ausdrücklich) |
| VAL-141 | Frage | Pflichtmedium (`image_required`) ohne Alt-Text bzw. als dekorativ markiert | W (→ D22-4) | „Die Frage ist ohne das Bild nicht lösbar, das Bild hat aber keinen Alternativtext.“ | dialog; **kein** quick_fix „dekorativ“ |
| VAL-142 | Video/Audio | Video ohne Untertitelspur, Audio ohne Transkript | W | „Das Video in {lesson} hat keine Untertitel.“ | jump (A-06 › Untertitel zuordnen) |
| VAL-143 | Untertitel | VTT ungültig (Parser) oder Sprache fehlt | W | „Die Untertiteldatei {file} ist fehlerhaft.“ | jump |
| VAL-144 | Lektion | Überschriftenebenen übersprungen (h2 → h4), Tabellen ohne Kopfzeile, Linktexte ohne Aussage („hier“, „mehr“) | H | „In {lesson} fehlt eine Überschriftenebene.“ | jump (Block) |
| VAL-145 | Option/Frage | Bedeutung nur über Farbe/Bild ohne Text (Option ohne Text, nur Bild ohne Alt) | W | „Antwort {key} besteht nur aus einem Bild ohne Beschreibung.“ | dialog |

**Abdeckung der Mindestvorgaben:** fehlende Lektionen/leere Module (VAL-003, VAL-020, VAL-023, VAL-026); Frage ohne richtige Antwort (VAL-042, VAL-044, VAL-053, VAL-054); widersprüchliche Optionen (VAL-043, VAL-047, VAL-050); doppelte Optionen (VAL-048, VAL-049); Blueprint > Pool (VAL-085 … VAL-089); fehlende Bestehensregel (VAL-073 … VAL-076); Zeitlimit ungültig (VAL-079 … VAL-082); Rechtestatus fremd (VAL-100, VAL-101, eigen VAL-102/103); externe Medien ohne Consent-Provider (VAL-110 … VAL-113); Drip-/Voraussetzungszyklen (VAL-120, VAL-125); unerreichbare Lektionen (VAL-121 … VAL-123, VAL-098); fehlende Alt-Texte (VAL-140, VAL-141); Zertifikatsregel ohne Nachweisquelle (VAL-130 … VAL-132).

### 1.5 Ausführung

| Kontext | Auslöser | Umfang | Wirkung |
|---|---|---|---|
| `live` | Speichern/Autosave eines Objekts (debounced 2 s) | Regeln des Objekts + direkt abhängige (Frage → Pools, in denen sie steht → Blueprints) | Inline-Fehler, Panel aktualisiert |
| `manual` | Schaltfläche „Kurs prüfen“ | vollständig | Panel |
| `submit` | „Zur Freigabe einreichen“ | vollständig | nur ohne Blocker möglich |
| `publish` | „Veröffentlichen“ (jeder Weg, §2.5) | vollständig, unter Sperre, gegen `expected_hash` | verbindlich |
| `import_dry_run` | R0-10 §3.3 | Regeln ohne Kursbezug (Frage, Medium, Rechte, Regel); Kursregeln, wenn das Paket einen Kurs enthält | im Dry-run-Bericht, je Objekt; Import legt nur Entwürfe an |
| `health` | täglicher Cron + `course_captain_content_changed` für betroffene veröffentlichte Kurse | vollständig gegen veröffentlichte Revision + aktuelle Pools/Rechte | Warnung in A-01/A-16 und an Kursverantwortliche; **nie** automatisches Zurückziehen |

- **Performance (SOLL, Abgleich R0-14):** vollständige Prüfung eines Kurses mit 200 Lektionen und 2 000 Fragen in ≤ 2 s serverseitig; `live` ≤ 300 ms. Poolgrößen per Aggregat-Query je Modul (keine N+1). Werte sind Ziele, keine Messung.
- **Reihenfolge der Issues:** Blocker vor Warnung vor Hinweis; innerhalb davon Curriculum-Reihenfolge (Modul, Lektion, Position). Stabil, damit Screenreader-Nutzende nach einer Korrektur die Position wiederfinden.
- **Limits:** Ab 200 Issues je Code werden Einzelobjekte zusammengefasst („und 180 weitere“), der vollständige Bericht ist als CSV abrufbar. LB bricht nach 50 Fehlern ab (PK:395); Course Captain zählt weiter, zeigt aber gruppiert.

---

## 2. Publish-Workflow

### 2.1 Zustände

```
                     einreichen (M-03, 0 Blocker)
  ┌──────────┐ ───────────────────────────────▶ ┌────────────────┐
  │ Entwurf  │ ◀─────────────────────────────── │ Zur Freigabe   │
  │ (draft)  │   zurückgeben (M-04, Kommentar)   │ (pending)      │
  └────┬─────┘   zurückziehen (Einreicher)       └───────┬────────┘
       │ veröffentlichen (M-04, 0 Blocker, Warnungen bestätigt)
       ▼                                                  │ veröffentlichen (M-04)
  ┌──────────────────────────┐ ◀───────────────────────────┘
  │ Veröffentlicht           │   Änderungen → Staging-Kopie (Entwurf der nächsten Revision, R23-D2)
  │ (published, Revision n)  │   erneut veröffentlichen → Revision n+1, n wird superseded
  └────┬─────────────────────┘
       │ zurückziehen (M-04, Begründung, Wirkungsvorschau)
       ▼
  ┌──────────────────────────┐   reaktivieren = veröffentlichen mit Begründung (VAL-010)
  │ Zurückgezogen (archived) │
  └──────────────────────────┘
```

- WP-Poststatus: `draft`/`auto-draft` = Entwurf, `pending` = Zur Freigabe, `publish` = veröffentlicht, Course-Captain-Status `archived` als registrierter Poststatus (nicht `trash`). `future` (geplant) und `private` sind für `cc_course`, `cc_module`, `cc_lesson` **gesperrt** (→ D22-6).
- Ein veröffentlichter Kurs hat **immer** genau eine Revision mit `status = published`; der Editor arbeitet dann auf der Staging-Kopie. „Änderungen verwerfen“ setzt die Staging-Kopie auf die veröffentlichte Revision zurück (mit Bestätigung, Audit).
- „Zur Freigabe“ ist ein einfacher Ein-Schritt-Workflow für Autoren ohne `cc_publish_courses` (Konzept 17: Instructor „nach Freigabe“; R0-11 F1.2). Mehrstufige Freigaben, Fristen und Gutachter-Zuweisung sind Pro (`course.content_workflows`, R0-11 N-03) → D22-5.

### 2.2 Schritte und Rechte

| Schritt | Aktion | Recht (R0-07) | Vorbedingung | Ergebnis / Audit |
|---|---|---|---|---|
| 1 Entwurf | anlegen, bearbeiten, Autosave | M-03 `cc_edit_course` (eigener/zugewiesener Kurs `cc_edit_courses`, fremder `cc_edit_others_courses`); Fragen M-07; Regeln M-06 | – | WP-Revision des Entwurfs; Live-Validierung |
| 2 Prüfung | „Kurs prüfen“ | M-03 | – | ValidationReport, kein Audit |
| 2a Einreichen | „Zur Freigabe einreichen“ | M-03 **ohne** M-04 | 0 Blocker; Notiz optional | Status `pending`; Hinweis an Kursmanager (A-01-Kachel, optional Mail); Audit `course_submitted` mit `candidate_hash` |
| 2b Zurückgeben | „Zur Überarbeitung zurückgeben“ | M-04 | Status `pending`; Kommentar Pflicht | Status `draft`; Kommentar am Kurs sichtbar; Audit `course_returned` |
| 3 Vorschau | „Vorschau als Lernender“ | M-03 (auch der prüfende Manager über M-04 ⊃ M-03) | – | §2.3; kein Audit pro Aufruf, aber Zugriffslog im Debug-Modus |
| 4 Veröffentlichen | „Veröffentlichen“ | M-04 `cc_publish_course` (= M-03 + `cc_publish_courses`) | 0 Blocker; alle Warnungen bestätigt; `expected_hash` = aktueller `candidate_hash`; Revisionsklasse `patch`/`minor` (R0-23) gewählt | §2.4; Audit `course_published` |
| 5 Zurückziehen | „Kurs zurückziehen“ | M-04 | Begründung Pflicht; Bestätigung mit Zahlen (aktive Lernende, laufende Versuche, gültige Zertifikate) | Status `archived`; Auslauf laufender Versuche nach R0-23 RV-04; Audit `course_withdrawn` |
| 5a Für neue Einschreibungen schließen | Schalter | M-04 | – | Kurs bleibt veröffentlicht, Katalog/Einschreibung gesperrt (R23-D6); Audit |
| – Fragenrevision veröffentlichen | aus Frageneditor oder Quick-Fix VAL-058 | M-07 + `cc_publish_courses` | Frage-Regeln VAL-040 … VAL-062 ohne Blocker | neue Revision; betroffene Kurse `health`-validiert |
| – Rechte freigeben | aus VAL-100/101 | `cc_release_rights` | Pflichtbegründung | Override (R0-10 §2.2), Audit |
| – Import aktivieren | A-13 | M-21 (`cc_import_content` + `cc_publish_courses`) | Import legt Kurse als Entwurf an; Aktivieren eines bereits veröffentlichten Kurses = Schritt 4 mit `context = publish` | wie Schritt 4 |

`cc_support` und `cc_trainer` haben keinen dieser Schritte (R0-07 §3.1). Trainer sehen den veröffentlichten Kurs nur über einen eigenen Grant wie Lernende.

### 2.3 Vorschau als Lernender

**Zweck:** Der Autor sieht den **Kandidaten** (nicht die veröffentlichte Revision) so, wie ein Lernender in einem gewählten Zustand ihn sähe, inklusive Sperren, Platzhaltern und Prüfungsablauf.

**Simulierte Zustände (Auswahl im Vorschaukopf):**

| Persona | Wirkung |
|---|---|
| Neu eingeschrieben, voller Zugang | Zugangsbeginn = jetzt, Drip-Tag 0, keine Abschlüsse |
| Drip-Tag *n* | Zugangsbeginn = jetzt − *n* Tage |
| Teilumfang (ohne Grant) | nur `free_limited`-Elemente frei, Rest Z-G „trial“ |
| Ohne Zugang | Katalog-/Kursseite wie Gast bzw. angemeldeter Nutzer ohne Grant |
| Voraussetzungen erfüllt bis … | Auswahl einer Activity; alle davor gelten als abgeschlossen |

Schalter „Gesperrte Inhalte trotzdem anzeigen“ (nur Vorschau) blendet Rechte-Platzhalter auf; standardmäßig zeigt die Vorschau **die Lernendensicht mit Platzhaltern** für fremde `unknown/restricted`-Medien, damit der Autor sieht, was Lernende sehen. (R0-10 §2.2 nimmt die Admin-Vorschau von der Sperre aus; hier wird das zur Option präzisiert → Delta Δ22-4.)

**Schreibschutz (MUSS):**

- Die Vorschau läuft in einem **Vorschaukontext** (`PreviewContext`, gesetzt nur serverseitig aus einem signierten, an Nutzer und Kurs gebundenen Vorschau-Token mit 2 h Gültigkeit). Alle Repositories für Lerndaten (`cc_activity_progress`, `cc_course_progress`, `cc_attempt`, `cc_question_progress`, `cc_exam_session`, `cc_exam_session_question`, `cc_certificate`, `cc_grant`), Outbox, Mailversand, `do_action`-Ereignisse für Lernfortschritt, Webhooks und Lernenden-Audit prüfen den Kontext und **schreiben nicht**. Die Prüfung erfolgt zentral im Schreibpfad (Repository-Basisklasse/`WriteGuard`), nicht verteilt in Controllern; ein Schreibversuch im Vorschaukontext ist ein Programmierfehler und wirft `cc_preview_readonly` (im Test: harter Fehler).
- Zustand während der Vorschau (beantwortete Fragen, Video-Position, Prüfungsablauf, Timer) liegt **flüchtig** in einem nutzergebundenen Transient (`cc_preview_<user>_<course>`, TTL 2 h) bzw. im Browser-Speicher der Seite. Prüfungen sind durchspielbar mit simuliertem Snapshot (gleicher Code wie §6 R0-08, In-Memory-Repository) und zeigen die Auswertung mit Kennzeichnung „Vorschau – nicht gespeichert“ (→ D22-7).
- Smarttrainer-Auswahl in der Vorschau nutzt einen leeren Lernstand; sie liest **nicht** den echten Lernstand des Autors.
- Rate-Limits und Versuchslimits zählen in der Vorschau nicht; das Start-Rate-Limit (40/h) gilt als Missbrauchsschutz trotzdem je Vorschau-Token.
- **Kein Leck:** Vorschau-URLs sind `noindex`, `Cache-Control: no-store, private`, nicht über Seiten-Caches auslieferbar (Header + `DONOTCACHEPAGE`), und ohne gültiges Token bzw. ohne M-03 404 (R0-07 M-01, Z-R). Das Token gibt keinem anderen Nutzer Zugriff (Teilen eines Links funktioniert nicht).
- Sichtbarer Rahmen „Vorschau als {persona} – Entwurf, nichts wird gespeichert“ mit Persona-Wechsel und „Vorschau beenden“; Rahmen ist eine Landmark (`role="region"`, `aria-label`) und per Tastatur erreichbar.

### 2.4 Veröffentlichen (verbindlicher Ablauf)

1. Client sendet `POST /course-captain/v1/courses/{uid}/publish` mit `expected_hash`, `acknowledged[]` (Fingerabdrücke), `change_class`, `note`, `request_key`, optional `recompletion_required[]` (R23-D3).
2. Server prüft M-04 und Nonce, nimmt `NamedLock course_publish:<course_id>` (10 s; nicht erhältlich → `cc_publish_busy`, 409).
3. Baut den Kandidaten **neu** aus der DB (nie aus Client-Daten), berechnet `candidate_hash`. Abweichung von `expected_hash` → `cc_validation_stale` (409) mit neuem Report.
4. Vollständige Validierung (`context = publish`). Blocker → `cc_publish_blocked` (422) mit Report. Unbestätigte Warnung (Fingerabdruck fehlt in `acknowledged`) → `cc_publish_unacknowledged` (422).
5. Eine Transaktion: Staging-Kopien der Lektionen übernehmen (R23-D2); referenzierte **Prüfungsregel-Entwürfe** werden als neue Version `published` gesetzt, die bisher referenzierte Version wird `retired` (R0-06 `cc_exam_rule`); je Activity `validate()` + `freeze()`; neue `cc_course_revision` (`revision_no + 1`, `structure`, `structure_hash = candidate_hash`, `change_class`, `published_by`, `published_at`), vorherige `superseded`; WP-Status `publish`.
6. Nach Commit: Cache-Invalidierung (`course_captain_content_changed`), Ereignis `course_captain_course_published` über Outbox, Audit `course_published` (Akteur, Revision, Hash, bestätigte Warnungen, `change_class`, Notiz, Anzahl betroffener Lernender).
7. Idempotenz: gleicher `request_key` → dieselbe Revision zurück. `candidate_hash` = Hash der veröffentlichten Revision → keine neue Revision (VAL-008), Antwort 200 mit `unchanged = true`.

**Prüfungsregel-Auflösung für Lernende (MUSS, schließt den LB-Befund):**

- **LB:** `examRule()` liefert, wenn keine Regel `published ∧ verified ∧ gültig` ist, die neueste nicht archivierte Regel als Übungsmodus – **auch einen Entwurf** (CR:186-216, Kommentar CR:187-188). Defektes `passing_rule`-JSON wird still zu `min_correct_ratio = 0.75` (CR:207). Damit sehen Lernende unfertige Regeln eines Autors, sobald die gültige Regel abläuft oder fehlt.
- **Course Captain:** Lernende lösen die Regel **ausschließlich** über `cc_course_revision.structure.activities[].rule_ref = {rule_uid, version}` der veröffentlichten Revision auf. Die referenzierte Version muss `published` sein und am Starttag gültig. Ist sie das nicht (abgelaufen, später `retired`, defekt), ist die Prüfung **nicht startbar** (`cc_exam_not_startable`, Grund `rule_unavailable`), Health meldet es; es gibt **keine** Suche nach einer anderen Regel und **keinen** Standardwert.
- Es gibt keine Abfrage „neueste Regel des Kurses“ im Lernpfad. Eine solche Methode darf im Code nur im Autorenkontext existieren (Repository-Trennung `AuthorExamRuleRepository` / `DeliveryExamRuleRepository`), Testanker PUB-030 … PUB-032.
- Übungsprüfungen sind ausdrücklich veröffentlichte Regeln mit `purpose = practice` (nie nachweisfähig, Kennzeichnung „Übung“), nicht ein Rückfall auf Entwürfe (→ D22-2, Delta Δ22-1 zu R0-08 §4.1).

### 2.5 Alle Wege zum Veröffentlichen laufen durch den Validator

| Weg | Absicherung |
|---|---|
| Course-Captain-Editor (A-04) | REST `…/publish` (§2.4) |
| Block-Editor/WP-REST `wp/v2/cc_course` mit `status=publish` | `rest_pre_insert_cc_course`: Statuswechsel auf `publish`/`future`/`private` wird abgewiesen (`cc_publish_via_validator`, 422) mit Verweis auf A-04; Speichern als Entwurf bleibt erlaubt |
| Quick Edit / Bulk Edit / `wp_insert_post` aus Fremdcode | `wp_insert_post_data`-Filter setzt einen ungültigen Statuswechsel auf den alten Status zurück; `transition_post_status`-Wächter loggt den Versuch (Audit `publish_bypass_blocked`) |
| XML-RPC | wie `wp_insert_post` (gleicher Filter) |
| WP-CLI `wp post update --post_status=publish` | wie oben; eigener Befehl `wp course-captain course publish <uid> [--ack=<fp,…>|--ack-all-warnings] [--note=…]` ruft §2.4 mit Akteur `system:cli` + `--user` |
| Import-Aktivierung (A-13, M-21) | ruft §2.4 je Kurs; Blocker → Aktivierung des Kurses scheitert, Batch-Bericht nennt Issues |
| Module/Lektionen einzeln „veröffentlichen“ | nicht möglich; Module/Lektionen haben keinen eigenen Publish, nur den Kurs (R0-08 §7.1) |
| Prüfungsregel einzeln veröffentlichen (A-08) | erlaubt (VAL-070 … VAL-098 ohne Blocker), wirkt für Lernende aber erst mit der nächsten Kursrevision (R0-23 RV-17); Hinweis im UI |

### 2.6 Zurückziehen

- Vorab-Wirkungsbericht: aktive Lernende, laufende Versuche (mit/ohne Limit), gültige Zertifikate, offene Gruppenplätze. Text: „Lernende behalten Ergebnisse und Zertifikate, können aber nicht weiterlernen. {n} laufende Prüfungen können noch bis zu ihrer Frist abgegeben werden.“ (R0-23 RV-04, RV-20).
- Zurückziehen ist kein Löschen und kein Zurück-auf-Entwurf: Die Revisionen bleiben, die Lernhistorie bleibt. Ein Kurs **ohne jemals eingeschriebene Lernende** KANN stattdessen „zurück auf Entwurf“ gesetzt werden (Revision bleibt `superseded` für das Audit).
- Reaktivieren: erneutes Veröffentlichen mit Begründung, voller Validierung und neuer Revision.

---

## 3. Autoren-UX

### 3.1 Validierungspanel im Editor (A-04)

- **Ort:** rechte Seitenleiste, Abschnitt „Veröffentlichung“ (Konzept 16.3) mit Zählern „{b} Blocker · {w} Warnungen · {h} Hinweise“ und Status „zuletzt geprüft vor 2 min“ bzw. „wird geprüft …“. Ein Klick öffnet das Panel als eigene Seitenleiste bzw. auf schmalen Bildschirmen (< 768 px) als Vollbild-Dialog.
- **Gliederung:** umschaltbar „nach Schwere“ (Standard) oder „nach Kursstruktur“ (Baum Modul › Lektion). Je Issue: Symbol **und** Wort für die Schwere (nie nur Farbe), Meldung, Brotkrumen, Schaltflächen „Anzeigen“ (Sprungziel) und Korrekturhilfen.
- **Curriculum-Navigation** (linke Spalte) zeigt je Modul/Lektion ein Schwere-Symbol mit Textalternative („Lektion 3, 1 Blocker“).
- **Filter:** nur Blocker, nur meine Objekte (Autor), ausgeblendete Hinweise einblenden.
- **Nach Korrektur:** Das Issue verschwindet nicht sofort, sondern wird als „behoben“ markiert, bis der Nutzer das Panel neu sortiert oder schließt; so springt die Liste nicht unter dem Fokus weg.
- Frageneditor (A-07) und Prüfungseditor (A-08) haben dasselbe Panel auf Objektebene; A-08 zeigt zusätzlich die **Poolrechnung** je Abschnitt („fordert 12 · auslieferbar 9 · 2 Rechte gesperrt · 1 Entwurf“) und eine **Auswertungsvorschau** (Beispiel: „Mit 27 von 36 richtig bestanden; Teil B mindestens 5 von 8“) nach Konzept 16.3.

### 3.2 Inline-Fehler

- Am Feld: Meldung unter dem Feld, `aria-invalid="true"`, `aria-describedby` auf die Meldung; Schwere als Wort im Text („Blocker: …“). Feldwerte bleiben erhalten (Z-F, INV-D-025).
- Inline-Fehler erscheinen erst nach Verlassen des Feldes oder nach dem ersten Prüflauf, nicht während des Tippens.
- Sprungziel aus dem Panel: öffnet Modul/Lektion/Frage, scrollt, setzt den **Fokus auf das Feld** (bei Optionslisten auf die betroffene Option) und kündigt die Meldung an. „Zurück zur Liste“ führt zum selben Issue im Panel zurück.
- Für Objekte, die in mehreren Kursen verwendet werden (Modul n:m, Fragen in mehreren Pools): Hinweis „Diese Änderung wirkt auch in {n} anderen Kursen“ vor dem Speichern.

### 3.3 Zusammenfassung vor dem Veröffentlichen

Dialog nach „Veröffentlichen“ (bzw. „Einreichen“), modal mit Fokusfalle, Überschrift als erstes fokussiertes Element:

1. **Status:** „Bereit zum Veröffentlichen“ oder „{b} Blocker verhindern die Veröffentlichung“ (dann nur Liste + Sprunglinks, keine Veröffentlichen-Schaltfläche).
2. **Änderungen gegenüber Revision {n}:** Module/Lektionen hinzugefügt, entfernt, verschoben; geänderte Regeln (Bestehensgrenze, Zeitlimit, Fragenzahl), Prüfungsregel-Entwürfe, die mit veröffentlicht werden (VAL-072).
3. **Wirkung auf Lernende** (R0-11 F1.3, R0-23): „betrifft {n} Lernende; {m} laufende Prüfungen behalten ihre Fassung; {k} müssen {lesson} erneut abschließen“.
4. **Warnungen** mit Kontrollkästchen je Warnung und „Alle Warnungen gelesen“ (setzt alle Kästchen; jede bleibt einzeln abwählbar).
5. **Revisionsklasse** `patch` („Korrektur: Tippfehler, Bild, Formulierung“) / `minor` („Struktur, Pflichtumfang oder Regeln geändert“) mit Erklärung; Notiz optional.
6. Schaltflächen „Veröffentlichen“ (primär, deaktiviert mit sichtbarer Begründung, solange Warnungen offen sind) und „Abbrechen“.

Antwortet der Server mit `cc_validation_stale`, zeigt der Dialog „Der Kurs wurde inzwischen geändert“ und lädt die neue Zusammenfassung; bestätigte Warnungen mit gleichem Fingerabdruck bleiben angehakt.

### 3.4 Barrierefreiheit der Meldungen (WCAG 2.2 AA, Konzept 16.3)

- Schwere nie nur über Farbe: Symbol + Wort; Kontrast ≥ 4,5:1 für Text, ≥ 3:1 für Symbole und Fokusrahmen (WCAG 1.4.1, 1.4.3, 1.4.11, 2.4.11/2.4.13).
- Ergebnis eines Prüflaufs als **eine** zusammenfassende Ansage in einer `aria-live="polite"`-Region („Prüfung abgeschlossen: 2 Blocker, 3 Warnungen“); Blocker beim Veröffentlichungsversuch als `role="alert"`. Keine Ansage je Autosave, nur bei geändertem Zählerstand.
- Panel-Liste als Liste mit Überschriften je Gruppe (navigierbar per Überschriften-Sprung), Issue-Elemente als Listenpunkte mit echten `<button>`/`<a>`-Elementen; vollständig per Tastatur (Tab/Umschalt+Tab, Enter, Esc schließt Dialoge und gibt den Fokus an den Auslöser zurück).
- Tastenkürzel KANN „Kurs prüfen“ (z. B. Alt+Umschalt+V) und „nächstes Problem“ anbieten; Kürzel abschaltbar (WCAG 2.1.4).
- Meldungstexte: kurze Sätze, aktive Formulierung, keine Fachbegriffe ohne Erklärung („Pool“ → „verfügbare Fragen“ im Lernendenkontext, im Autorenkontext mit Tooltip), keine Abkürzungen außer dem Code.
- Zielgrößen ≥ 24 × 24 px (WCAG 2.5.8); Panel bei 320 px ohne horizontales Scrollen (1.4.10); Animationen respektieren `prefers-reduced-motion`.
- Dieselben Regeln gelten für den Import-Dry-run-Bericht und die Health-Meldungen.
- Manueller Prüfschritt im Autoren-UX-Test (Konzept 28.4 Nr. 5): drei neutrale Kursfälle mit absichtlich eingebauten Fehlern; gemessen werden Zeit bis zur Behebung, Fehlinterpretationen von Meldungen, Tastatur- und Screenreaderpfad (NVDA + Firefox, VoiceOver + Safari).

---

## 4. Testfälle (Ziel-Task R1-26)

Fixtures nach R0-05 §2.7 / R0-07 §5.1 (Autor `Au`, Manager `M`, Admin `A`, Lernender `L`), synthetische neutrale Inhalte (E-02). „Ziel“ nennt die Haupttask; Mit-Tasks in Klammern. Erwartete Codes nach R0-19 (Vorschläge in §6).

| ID | Gegeben | Aktion | Erwartet | Regel | Ziel |
|---|---|---|---|---|---|
| PUB-001 | Kurs ohne Module | `M` veröffentlicht | 422 `cc_publish_blocked`, Issue VAL-003, Kurs bleibt `draft`, keine `cc_course_revision` | VAL-003 | R1-26 |
| PUB-002 | Modul ohne Lektion | Prüfen | VAL-020 Blocker; Quick-Fix entfernt nur die Zuordnung `cc_course_module`, Modul-CPT bleibt | VAL-020 | R1-26 (R1-04) |
| PUB-003 | Lektion `lesson` mit nur leeren Absätzen | Prüfen | VAL-026 | VAL-026 | R1-26 |
| PUB-004 | `single_choice` ohne richtige Option | Speichern | Inline-Fehler + VAL-042; Kurs mit dieser Frage im Pool → Blocker im Kursreport | VAL-042 | R1-26 (R1-06) |
| PUB-005 | `single_choice` mit 2 richtigen | Quick-Fix „In Mehrfachauswahl umwandeln“ | Typ `multi_choice`, `scoring = all_or_nothing`, Rückgängig stellt Ursprung her | VAL-043 | R1-26 |
| PUB-006 | Optionen „ Paris “ (richtig) und „paris“ (falsch) | Prüfen | VAL-047 Blocker (Normalisierung trim/casefold) | VAL-047 | R1-26 |
| PUB-007 | zwei identische falsche Optionen | Prüfen | VAL-048 Warnung, nicht Blocker | VAL-048 | R1-26 |
| PUB-008 | Optionen mit gleichem `answer_key` „B“ | Import-Dry-run eines Pakets | VAL-049 im Dry-run (LB-Schema erkennt Duplikate nicht: kein `uniqueItems` in SV:23-27) | VAL-049 | R1-26 (R1-15) |
| PUB-009 | „Keine der genannten“ (`role = none_of_the_above`) richtig + weitere richtige Option | Prüfen | VAL-050 | VAL-050 | R1-26 |
| PUB-010 | `true_false` mit 3 Optionen | Prüfen | VAL-053; Quick-Fix stellt 2 Standardoptionen her | VAL-053 | R1-26 |
| PUB-011 | `open_text` ohne Musterantwort | Prüfen | VAL-054 | VAL-054 | R1-26 |
| PUB-012 | `scoring = per_option` | Prüfen | VAL-056 (E-77) | VAL-056 | R1-26 (R1-06) |
| PUB-013 | Prüfung, 20 `multi_choice` mit nur richtigen Optionen, Grenze 75 % | Prüfen | VAL-052 je Frage (W) **und** VAL-078 (B) | VAL-078 | R1-26 (R1-09, GM INV-E-008) |
| PUB-014 | Blueprint fordert 12, Pool 9 auslieferbar (2 Rechte gesperrt, 1 Entwurf), Prüfung ist Zertifikatsquelle | Prüfen | VAL-086 Blocker mit Parametern `required=12, available=9`, Aufschlüsselung 2/1; Quick-Fix setzt 9 | VAL-086 | R1-26 (R1-08) |
| PUB-015 | wie PUB-014, Prüfung optional, kein Nachweis | Veröffentlichen ohne Bestätigung | 422 `cc_publish_unacknowledged`; mit Bestätigung → Revision, Audit enthält Fingerabdruck | VAL-087, §1.3 | R1-26 |
| PUB-016 | zwei Blueprints auf dasselbe Modul (Pool 10, Anforderungen 6 + 6) | Prüfen | VAL-088 Hinweis „für B bleiben 4“ **und** VAL-086/087 für B | VAL-088 | R1-26 |
| PUB-017 | Pool 0 | Prüfen | VAL-085 | VAL-085 | R1-26 |
| PUB-018 | feste Variante mit fremder Frage `restricted` | Prüfen | VAL-089 | VAL-089 | R1-26 (R1-14) |
| PUB-019 | Regel ohne `passing_rule` | Prüfen; außerdem Lernender startet (direkt per REST) einen Versuch einer bereits veröffentlichten Regel mit nachträglich defektem JSON (DB-Manipulation) | VAL-073 Blocker; Start → `cc_exam_not_startable`, **nie** Auswertung mit 0.75 | VAL-073, R0-08 §4.1 | R1-26 (R1-08) |
| PUB-020 | `min_ratio = 5/4` bzw. `den = 0` | Prüfen | VAL-074 | VAL-074 | R1-26 |
| PUB-021 | Teil „8 von 5“ | Prüfen | VAL-075 | VAL-075 | R1-26 |
| PUB-022 | `time_limit_seconds` = 0 / −5 / 90 000 / „abc“ | Prüfen | VAL-079 je Wert; `NULL` gültig (ohne Limit) | VAL-079 | R1-26 |
| PUB-023 | 60 Fragen, Limit 300 s | Prüfen | VAL-080 Warnung | VAL-080 | R1-26 |
| PUB-024 | Kulanz 900 s | Prüfen | VAL-082 | VAL-082 | R1-26 |
| PUB-025 | importiertes Bild `unknown` in Pflichtlektion | Prüfen | VAL-100 Blocker; nach Rechtefreigabe (`cc_release_rights`, Begründung) verschwindet Issue; Autor ohne `cc_release_rights` sieht „Kursverwaltung informieren“ statt Link | VAL-100, E-18 | R1-26 (R1-14, R1-15) |
| PUB-026 | dasselbe Bild in optionaler Lektion | Prüfen | VAL-101 Warnung | VAL-101 | R1-26 |
| PUB-027 | eigenes Bild `restricted` / `unknown` | Prüfen | VAL-102 Warnung / VAL-103 Hinweis, kein Blocker (E-18) | VAL-102, VAL-103 | R1-26 |
| PUB-028 | externes Video von nicht registriertem Host | Prüfen | VAL-110 Blocker | VAL-110 | R1-26 (R1-07) |
| PUB-029 | YouTube-Video, keine WP Consent API aktiv | Prüfen | nur VAL-112 Hinweis; Lernendenansicht ohne Fremdverbindung (R0-10 §5.2) | VAL-112 | R1-26 |
| PUB-030 | veröffentlichte Regel v1 abgelaufen, Entwurf v2 vorhanden, Kursrevision verweist auf v1 | Lernender öffnet Prüfung / startet | nicht startbar (`cc_exam_not_startable`, `rule_unavailable`); v2 **nie** sichtbar; Health-Warnung an Autor | §2.4 (LB CR:186-216) | R1-26 (R1-08) |
| PUB-031 | Kurs veröffentlicht, Autor legt Regel-Entwurf v2 an | Lernender startet | Versuch mit v1 (Snapshot `rule.version = 1`); nach Veröffentlichen der Kursrevision Starts mit v2 | §2.4, RV-17 | R1-26 (R1-27) |
| PUB-032 | statische Analyse | CI | Lernpfad-Klassen (`Delivery*`, REST-Lernrouten) rufen keine Methode auf, die Regeln ohne `rule_ref` auflöst (Architekturtest, z. B. deptrac/PHPStan-Regel) | §2.4 | R1-26 (R1-18) |
| PUB-033 | Zyklus L1 → L2 → L3 → L1 | Prüfen | VAL-120 mit Pfadangabe; Dialog bietet drei Kanten | VAL-120 | R1-26 (R1-04) |
| PUB-034 | Zyklus über Modulaggregation (L in M1 verlangt M2, L' in M2 verlangt M1) | Prüfen | VAL-120 | VAL-120 | R1-26 |
| PUB-035 | Voraussetzung auf archivierte Lektion | Prüfen | VAL-121; Lektion **nicht** frei geschaltet in Vorschau | VAL-121, R0-23 P-6 | R1-26 (R1-27) |
| PUB-036 | Lektion verlangt `passed(exam)`, Prüfung nur `reduced` möglich | Prüfen | VAL-122 | VAL-122 | R1-26 |
| PUB-037 | Drip 400 Tage, Grant-Laufzeit 365 | Prüfen | VAL-124 Warnung; Prüfung mit diesem Modul im Blueprint → VAL-098 Blocker | VAL-124, VAL-098 | R1-26 (R1-14) |
| PUB-038 | Zertifikat `exam_session` ohne Prüfung | Prüfen | VAL-130 | VAL-130 | R1-26 (R1-35) |
| PUB-039 | Zertifikat `course_completion`, keine Pflicht-Activity | Prüfen | VAL-132 + VAL-004 | VAL-132 | R1-26 |
| PUB-040 | 3 Bilder ohne Alt, 1 als dekorativ markiert | Prüfen | VAL-140 mit `n = 3`; markiertes Bild ohne Issue; Pflichtbild der Frage → VAL-141 ohne Quick-Fix „dekorativ“ | VAL-140, VAL-141 | R1-26 (R1-19) |
| PUB-041 | `Au` (ohne `cc_publish_courses`) | `POST …/publish` | 403 `cc_forbidden`; UI zeigt nur „Zur Freigabe einreichen“ | §2.2, M-04 | R1-26 (R1-03) |
| PUB-042 | `Au` reicht mit 1 Blocker ein | Einreichen | 422 `cc_publish_blocked`, Status bleibt `draft` | §2.2 | R1-26 |
| PUB-043 | `Au` reicht ein, `M` gibt zurück ohne Kommentar / mit Kommentar | Zurückgeben | ohne: 422; mit: `draft`, Kommentar sichtbar, Audit `course_returned` | §2.2 | R1-26 |
| PUB-044 | Kurs mit Blocker | `wp/v2/cc_course` `status=publish`; Quick Edit; `wp post update --post_status=publish`; XML-RPC `wp.editPost` | jeweils Status unverändert, Audit `publish_bypass_blocked`, keine Revision | §2.5 | R1-26 (R1-03) |
| PUB-045 | Kurs ohne Blocker | `wp/v2/cc_course` `status=future` | 422 (D22-6) | §2.1 | R1-26 |
| PUB-046 | Report mit `candidate_hash = h1`; danach Autor B ändert eine Lektion | `M` veröffentlicht mit `expected_hash = h1` | 409 `cc_validation_stale`, neuer Report; keine Revision | §2.4 Schritt 3 | R1-26 (R1-42) |
| PUB-047 | zwei parallele Publish-Requests (verschiedene `request_key`) | gleichzeitig | genau eine neue Revision; zweiter → 409 `cc_publish_busy` oder `unchanged = true` | §2.4 | R1-26 (R1-42) |
| PUB-048 | gleicher `request_key` zweimal | Publish | dieselbe Revision, keine Doppelung im Audit/Outbox | §2.4 Schritt 7 | R1-26 |
| PUB-049 | Vorschau als „Neu eingeschrieben“: Lektion bestätigen, Quiz beantworten, Prüfung starten und abgeben, Video bis 100 % | `Au` in Vorschau | Zeilenzahl aller `cc_*`-Lerntabellen, `cc_outbox`, Mail-Spy, `cc_audit_log` (Lernendenereignisse) **unverändert**; Auswertung angezeigt mit „nicht gespeichert“; `WriteGuard` meldet 0 Verstöße | §2.3 | R1-26 (R1-10, R1-08) |
| PUB-050 | Vorschau-URL von `Au` | anderer Nutzer `Au2` (kein Staff des Kurses) / Gast / Seiten-Cache | 404; Header `no-store`, `noindex`; Cache liefert nie die Vorschau | §2.3, R0-07 M-01 | R1-26 (R1-03) |
| PUB-051 | Vorschau Drip-Tag 6 bei Modul 2 mit Drip 7 | Vorschau | Modul 2 Z-G „drip“ mit Datum; Tag 7 → frei | §2.3, R0-08 §1.4 | R1-26 |
| PUB-052 | Validierungspanel mit 2 Blockern, 3 Warnungen | axe-core + Playwright-Tastaturpfad, 320 px | keine axe-Verstöße (AA); Live-Region kündigt „2 Blocker, 3 Warnungen“ einmal an; Sprungziel setzt Fokus auf das Feld; Esc gibt Fokus zurück; Schwere als Text vorhanden | §3.4 | R1-26 (R1-19) |

**Zusätzlich (Querschnitt, R1-26):** Health-Lauf nach Rechteänderung eines Mediums in einem veröffentlichten Kurs erzeugt VAL-100 als Health-Warnung, zieht den Kurs nicht zurück, Lernende sehen Platzhalter; Validator-Exception in einer Regel → VAL-000 Blocker; Lasttest Kurs 200 Lektionen/2 000 Fragen ≤ 2 s (Ziel, R0-14).

**Testkommandos (Vorschlag für R1-26, E-11):** `composer test -- --group=publish-validator` (PHPUnit mit WP-Testsuite), `npx playwright test tests/e2e/author-publish.spec.ts` (inkl. axe), Architekturtest PUB-032 in `composer analyse`.

---

## 5. Entscheidungsbedarf (nur neue Punkte)

Bereits in `ENTSCHEIDUNGEN.md` und hier als Annahme verwendet: E-18, E-23, E-69, E-77 (außerdem E-14, E-45, E-50, E-65, E-101).

| ID | Frage | Optionen | Empfehlung | Entscheider | blockiert |
|---|---|---|---|---|---|
| **D22-1** | Dürfen Blocker übergangen werden (z. B. Admin „trotzdem veröffentlichen“)? | a) nie · b) nur `administrator` mit Begründung und Audit · c) je Regel konfigurierbar | **a)**. Blocker beschreiben nicht auslieferbare oder unerreichbare Zustände; ein Override erzeugt genau die stillen Fehlzustände, die R0-08/R0-23 ausschließen. Wo fachlich Spielraum besteht, ist die Regel bereits eine Warnung | Auftraggeber | R1-26 |
| **D22-2** | Dürfen Lernende einen **Entwurf** einer Prüfungsregel als Übung starten (R0-08 §4.1 erlaubt es heute, LB-Parität CR:186-216)? | a) ja, Parität · b) nein; Übungsprüfungen sind veröffentlichte Regeln mit `purpose = practice` | **b)**. Entwürfe sind unfertige Autorenarbeit; ein Rückfall macht sie sichtbar, sobald eine Regel abläuft. → Delta Δ22-1 zu R0-08 | Auftraggeber + Didaktik | R1-08, R1-26 |
| **D22-3** | Zu kleiner Pool: Blocker oder Warnung? | a) immer Blocker · b) Blocker im Pflichtpfad (Nachweis, Zertifikat, `passed`-Voraussetzung, Abschlussregel), sonst Warnung mit `reduced` (Parität) · c) immer Warnung | **b)** (VAL-086/087) | Didaktik | R1-08, R1-26 |
| **D22-4** | Barrierefreiheits-Mängel (fehlender Alt-Text, fehlende Untertitel) als Blocker? | a) Warnung · b) Blocker · c) Warnung, per Site-Einstellung „Barrierefreiheit erzwingen“ als Blocker (Standard aus) | **c)**. Erzwingen als Standard würde Importe mit Altbestand blockieren; Betreiber mit gesetzlicher Pflicht (BFSG/EAA) schalten es ein. VAL-141 (Pflichtbild ohne Alt-Text) SOLL auch ohne Einstellung nicht per Quick-Fix „dekorativ“ lösbar sein | Auftraggeber | R1-19, R1-26 |
| **D22-5** | Einreichen/Zurückgeben (WP-Status `pending`) in R1 Free? | a) ja, einstufig · b) nein, Autoren ohne Publish-Recht warten auf manuelle Absprache · c) mehrstufig schon in Free | **a)**; Mehrstufigkeit, Fristen, Gutachterzuweisung in Pro (`course.content_workflows`) | Auftraggeber | R1-26, R0-11 |
| **D22-6** | Geplante Veröffentlichung (WP `future`) für Kurse in R1? | a) nein, Status gesperrt · b) ja, Validierung bei Planung und erneut beim Auslösen, bei Blockern bleibt Entwurf + Benachrichtigung | **a)** in R1 (Fehlerfall „Cron veröffentlicht nicht, Autor merkt es nicht“ vermeiden); b) als R2-Kandidat | Auftraggeber | R1-26 |
| **D22-7** | Prüfungen in der Vorschau durchspielbar? | a) nur Ansicht der Fragen und Regeln · b) durchspielbar mit flüchtigem Zustand (Transient/In-Memory), keine Tabellen, keine Ereignisse | **b)**; der Autor muss Zeitlimit, Teilregeln und Auswertung erleben können (Konzept 16.3 „Vorschau der Auswertung“) | Auftraggeber | R1-08, R1-26 |
| **D22-8** | Health-Prüfung veröffentlichter Kurse: Was passiert bei neuem Blocker (z. B. Rechte entzogen, Provider deaktiviert)? | a) nur melden · b) automatisch zurückziehen · c) betroffene Prüfung automatisch nicht startbar, Kurs bleibt | **a)** + die ohnehin geltende Laufzeitsperre (Rechte-Platzhalter, `cc_exam_not_startable`); kein automatischer Statuswechsel | Auftraggeber | R1-17, R1-26 |

---

## 6. Deltas zu anderen R0-Dokumenten (mit deren Freigabe einzuarbeiten)

| Δ | Dokument | Änderung |
|---|---|---|
| Δ22-1 | R0-08 §4.1 | Satz „Lernende können eine Entwurfsregel nur als Übung starten“ ersetzen durch „Lernende erreichen nur die in der Kursrevision referenzierte veröffentlichte Regelversion; Übungsprüfungen = `purpose = practice`“ (D22-2) |
| Δ22-2 | R0-06 | `cc_exam_rule.purpose varchar(16) NOT NULL DEFAULT 'exam'` (`exam/practice`); `cc_course_revision.change_class` (bereits R0-23 Δ-23-2) und `validator_version varchar(16)`; Poststatus `archived` und `pending` für `cc_course` |
| Δ22-3 | R0-19 | Fehlercodes `cc_publish_blocked` (422), `cc_publish_unacknowledged` (422), `cc_validation_stale` (409), `cc_publish_busy` (409), `cc_publish_via_validator` (422), `cc_preview_readonly` (500, nur Programmierfehler), Grund `rule_unavailable` an `cc_exam_not_startable` |
| Δ22-4 | R0-10 §2.2 | Admin-Vorschau: „ausgenommen“ präzisieren – Lernendenvorschau zeigt standardmäßig Platzhalter, Aufblenden per Schalter |
| Δ22-5 | R0-07 | Meta-Cap `cc_submit_course` (= M-03 ohne M-04) und `cc_return_course` (= M-04) für §2.2; `publish_bypass_blocked` in die Audit-Pflichten §3.6; Vorschau-Token-Regel zu M-01 |
| Δ22-6 | R0-11 A-04/A-08 | Panel, Zusammenfassungsdialog, Poolrechnung und Vorschau-Personas nach §2.3/§3 übernehmen; F1.2 auf D22-5 verweisen |

---

## 7. Nachweis

- Gelesen: `TODO-WETTBEWERBSANALYSE.md` (R0-22, R1-26), `konzept.md` Kap. 3.2, 16, 28.4; `docs/r0/R0-08` (§0–§8), `R0-10` (§2, §3.2–3.3, §5, §7), `R0-11` (§2, §3.1, §5), `R0-07` (§3.1–3.4), `R0-23` (§0–§2, RV-04, RV-17, RV-18, RV-20), `R0-06` (`cc_exam_rule`, `cc_course_revision`), `ENTSCHEIDUNGEN.md` (E-18, E-23, E-69, E-77 u. a.).
- LearnBoating @ `9f37c77` (nur lesend): CR:186-216 (`examRule()`-Rückfall auf nicht archivierte Regeln inkl. Entwurf, Standard `0.75` in :207), CR:171 (Medienfilter `BLOCKING_RIGHTS`), `src/Content/Repositories/Overrides.php:34`; ES:237-252 (Pool je Blueprint mit Doppelausschluss, `reduced` statt Fehler), ES:261-269; PK:387-430 (`referenceErrors`, Abbruch bei 50 Meldungen :395); SV:23-27 (unterstützte Schlüsselwörter, **kein** `uniqueItems` → doppelte Optionen/Schlüssel unerkannt); IS:244-264 (`validate`); `schema/v1/questions.schema.json:55-89` (Antwortregeln je Typ), `schema/v1/exam_rules.schema.json:9-22, :43-51` (`time_limit_seconds ≥ 1`, `question_count ≥ 1`, `min_correct_ratio` Pflicht), `schema/v1/media.schema.json:14` (`alt_text` nullable, keine a11y-Pflicht).
- LB hat **keinen** Kurseditor und keinen Publish-Validator; Validierung findet nur beim Import (Schema + Referenzen) und zur Laufzeit (stilles `reduced`, Rechte-Filter, 0.75-Rückfall) statt. Alle Regeln dieses Dokuments ohne LB-Beleg sind neu.
- Keine Änderung an LearnBoating, keine Implementierung, kein Commit.
