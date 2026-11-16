# Autorenhandbuch

Geprüft gegen: `src/Course/*`, `src/Admin/{EditorScreens,AuthoringService,AuthoringRoutes}.php`, `src/Activity/*`, `src/Question/QuestionTypes.php`, `src/Exam/ExamRules.php`, `src/Publish/{CourseValidator,PublishGuard}.php`.

> **Stand der Oberfläche:** Kurse, Module und Lektionen bearbeiten Sie im WordPress-Editor mit den Course-Captain-Metaboxen. **Fragen, Prüfungsregeln, Blueprints und Varianten haben noch keinen Editor** (geplant R1-32/R1-33). Heute entstehen sie über den Paketimport (ebenfalls noch ohne Oberfläche, R1-28/R1-36) oder über die PHP-Dienste. Die Regeln in §4 und §5 gelten trotzdem schon, denn der Server prüft sie bei jeder Veröffentlichung.

## 1. Aufbau eines Kurses

```
Kurs (ccap_course, öffentlich unter /kurs/<slug>/)
 └─ Modul (ccap_module; Reihenfolge, Pflicht ja/nein, Regeln)
     ├─ Lektion (ccap_lesson) mit genau einer Activity
     └─ Fragenpool des Moduls (für Smarttrainer und Prüfungen)
```

- Jedes Objekt bekommt beim Anlegen eine feste UID, die sich nie ändert (wichtig für Import, Fortschritt und Zertifikate).
- Die Kurssprache wird beim Anlegen aus der Site-Sprache übernommen.
- Kurse, Module und Lektionen mit Historie (Revisionen, Zugänge, Fortschritt, Versuche, Zertifikate) lassen sich nicht endgültig löschen. Der Papierkorb bleibt möglich; zum Stilllegen gibt es den Status **Archiviert**.
- Kurs-FAQ und Lerntipps sind als Inhaltstypen angelegt, haben aber noch keinen Menüeintrag (geplant).

## 2. Arbeiten im Editor

**Kurs bearbeiten** (Course Captain › Courses):

- Metabox **Modules**: Tabelle der zugeordneten Module mit Positionsfeld, Kästchen *Pflicht* und *Entfernen* sowie Auswahl *Modul hinzufügen*. Ohne JavaScript bedienbar; Reihenfolge über die Positionszahlen.
- Metabox **Publish revision**: Änderungsklasse *Correction (no effect on progress)* (`patch`) oder *Content change* (`minor`), optionale Notiz, ausdrückliches Kästchen und das Prüfergebnis (§6).

**Lektion bearbeiten** (Course Captain › Lessons): Metabox **Activity** mit Typ, Einstellungen (JSON) und Abschlussregel. Nach der ersten Veröffentlichung lässt sich der Typ nicht mehr ändern (`ccap_activity_type_locked`).

**Duplizieren:** Zeilenaktion *Duplizieren* bei Modulen und Lektionen erzeugt einen Entwurf „(copy)“ mit neuer UID und Verweis auf das Original. Kurse lassen sich nicht duplizieren.

Fehler beim Speichern erscheinen gebündelt in einem Hinweiskasten (Fehler zuerst). Sortieren per Ziehen und eine Block-Editor-Seitenleiste: geplant.

## 3. Activity-Typen

| Typ | Zweck | Abschlussregeln |
|---|---|---|
| `lesson` | Text-Lektion (auch Lektionen ohne gewählten Typ) | `manual_confirm`, `view` |
| `video` | Video über eine https-Adresse, optional Untertitel (`vtt`, https) | `manual_confirm`, `position_ratio` |
| `file` | Datei-Download aus der geschützten Ablage | `downloaded`, `manual_confirm` |
| `quiz` | Lektionsquiz mit eigenen Fragen | `attempted`, `passed`, `all_correct_once` |
| `exam` | Verweis auf eine Prüfung | `passed`, `evaluated` |
| `flashcard` | Karteikarten (Selbsteinschätzung oder Serie) | `all_known`, `all_streak`, `ratio` |
| `assignment` | Aufgabe mit Abgabe | Einstellungen vorhanden; Abgabe/Bewertung geplant (R1-46) |

Ein Video als **Platzhalter** zählt nicht für den Abschluss. Videos von fremden Hosts lädt der Lernbereich je nach Datenschutzeinstellung sofort oder erst nach Klick.

**Kursabschluss:** Ein Modul ist fertig, wenn alle Pflicht-Activities fertig sind; der Kurs, wenn alle Pflichtmodule fertig sind. Gerechnet wird gegen die Kursrevision, auf der die Person begonnen hat. Ein späteres Veröffentlichen ändert den Nenner laufender Lernender nicht.

## 4. Fragetypen

| Typ | Beschreibung | Bewertung |
|---|---|---|
| `single_choice` | eine richtige Option | richtig/falsch |
| `true_false` | wahr/falsch (wird nie gemischt) | richtig/falsch |
| `multi_choice` | mehrere richtige Optionen | `all_or_nothing` (Standard) oder `proportional_penalty` |
| `open_text` | Freitext mit Musterantwort und Selbsteinschätzung | in Prüfungen nach Aufdecken |
| `flashcard` | Karteikarte | eigene Zustände, keine Punkte |
| `numeric_tolerance` | Zahl mit Toleranz | Festkomma-Vergleich |
| `matching` | Zuordnung (nur rechte Seite wird gemischt) | `all_or_nothing` oder `proportional_penalty` |
| `ordering` | Reihenfolge (nie in Lösungsreihenfolge ausgeliefert) | richtig/falsch |
| `cloze` | Lückentext (Normalisierung: Leerraum, Unicode, optional Groß/Klein) | `all_or_nothing` oder `proportional_penalty` |

- Eine veröffentlichte Fragenrevision wird nie geändert; jede Änderung erzeugt eine neue Revision. Laufende Prüfungen bewerten weiter gegen die eingefrorene Revision.
- Ungültige Eingaben zählen nicht als falsch; leere Eingaben gelten als unbeantwortet.
- Erklärungstexte zu Lösungen gibt es in der freien Version nicht.
- Fragen einer laufenden Prüfung sind im Lernmodus für diese Person gesperrt.

## 5. Prüfungsregeln, Blueprints und Varianten

- **Bestehensregel ist Pflicht** und hat keinen Standardwert: Quote `min_ratio` als Bruch (z. B. 7/10). Optional Teilgrenzen je Abschnitt (Modul).
- **Blueprint:** je Modul eine Anzahl Fragen und Punkte je Frage (1–100). Die Fragen werden beim Start zufällig aus dem Pool gezogen. Ist der Pool zu klein, wird die Prüfung verkürzt; eine verkürzte Prüfung ist nie nachweisfähig.
- **Feste Varianten:** vorgegebene Fragen in Positionsreihenfolge.
- **Einstellungen:** Zweck `evidence` (Nachweis) oder `practice` (Übung, nie nachweisfähig); Versuchsgrenze, Sperrfrist zwischen Versuchen, ob abgebrochene Versuche zählen (Standard ja); Zeitlimit (1 s bis 24 h); Kulanz beim Antworten 5 s nach Fristende; Toleranz für offline gepufferte Antworten 120 s; Mischen der Fragen (`none`, `within_section`, `all`) und Antworten; Feedback (Ergebnis nach Abgabe oder nie, Punkte anzeigen, Lösungen erst nach Bestehen bzw. ohne Restversuch).
- Status `draft → published → retired`. Veröffentlichte Regeln sind eingefroren (`ccap_rule_frozen`); Änderungen bedeuten eine neue Version. Jeder Versuch friert Regel, Fragen, Punkte und Zeiten in einem Snapshot ein; spätere Änderungen wirken nicht auf laufende oder gewertete Versuche.
- Nachteilsausgleich: geplant.

## 6. Veröffentlichungsprüfung (Validator)

Vor jeder Veröffentlichung – im Editor, über REST (`POST /courses/{id}/publish`) und beim Statuswechsel im Block-Editor – prüft der Server den Kurs. Das Ergebnis erscheint im Kasten *Publish revision*:

- **Blocker** (`blocker`): Veröffentlichen ist unmöglich, es gibt keine Übersteuerung (`ccap_validation_failed`, 422). Ein Statuswechsel auf „Veröffentlicht“ bleibt dann Entwurf mit Hinweis.
- **Warnungen** (`warning`): müssen einzeln bestätigt werden (Kästchen je Warnung). Unbestätigt: `ccap_validation_unacknowledged` (409). Die Bestätigung gilt für genau diesen Befund (Fingerabdruck) und wird mit der Veröffentlichung protokolliert.
- **Hinweise** (`notice`): informativ.

Die Prüfung allein, ohne zu veröffentlichen: `GET /courses/{id}/validation`.

| Code | Schwere | Bedeutung | Abhilfe |
|---|---|---|---|
| VAL-000 | Blocker | Prüfung konnte nicht vollständig laufen | erneut versuchen, sonst Support mit Zeitpunkt |
| VAL-001 | Blocker | Kurs ohne Titel bzw. leerer Kurs | Titel eintragen |
| VAL-002 | Blocker | Kurssprache fehlt/ungültig | Sprache wählen |
| VAL-003 | Blocker | Kurs ohne Module | Modul hinzufügen |
| VAL-004 | Warnung | keine Pflicht-Activity, Kurs nie abschließbar | Pflicht setzen oder bestätigen |
| VAL-006 | Hinweis | keine Kursbeschreibung | Beschreibung ergänzen |
| VAL-008 | Hinweis | nichts geändert seit der letzten Revision | – |
| VAL-020 | Blocker | Modul ohne Lektionen **und** ohne Fragen | Inhalt ergänzen oder Modul entfernen |
| VAL-021 | Blocker | Modul ohne Titel | Titel eintragen |
| VAL-023 | Blocker | Lektion ohne Activity-Typ | Typ wählen |
| VAL-024 | Blocker | Activity-Typ nicht verfügbar | anderen Typ wählen |
| VAL-025 | Blocker | Activity-Einstellungen ungültig | Einstellungen korrigieren |
| VAL-026 | Blocker | Lektion ohne Inhalt | Text schreiben |
| VAL-027 | Blocker / Hinweis | Video ohne Quelle / Video ist Platzhalter | Video ergänzen |
| VAL-029 | Blocker | Quiz ohne Fragen | Fragen zuordnen |
| VAL-033 | Hinweis | zwei Lektionen eines Moduls mit gleichem Titel | umbenennen |
| VAL-040…045, 053…056, 063, 066…068 | Blocker | Frage unvollständig oder widersprüchlich (typabhängig) | Frage korrigieren |
| VAL-047 | Blocker | gleiche Optionstexte, eine richtig, eine falsch | Optionen korrigieren |
| VAL-048 | Warnung | gleiche Optionstexte | prüfen |
| VAL-049 | Blocker | doppelter Optionsschlüssel | korrigieren |
| VAL-052 | Warnung | alle Optionen einer Mehrfachauswahl richtig | prüfen |
| VAL-058 | Warnung | Poolfrage ohne veröffentlichte Revision (wird nicht ausgeliefert) | Frage veröffentlichen |
| VAL-073 / 074 | Blocker | Bestehensregel fehlt / ungültig | Quote setzen |
| VAL-079 | Blocker | Zeitlimit außerhalb 1 s – 24 h | korrigieren |
| VAL-082 | Blocker | Kulanz/Toleranz außerhalb 0–600 s | korrigieren |
| VAL-083 | Blocker | Blueprint zieht aus fremdem Modul | Modul zuordnen |
| VAL-084 | Blocker | Blueprint mit 0 Fragen | Anzahl ≥ 1 |
| VAL-085 | Blocker | Modul ohne auslieferbare Fragen | Fragen ergänzen |
| VAL-086 / 087 | Blocker / Warnung | zu wenige Fragen im Pool (Nachweis- / Übungsprüfung) | Anzahl senken oder Fragen ergänzen |
| VAL-089 | Blocker | Variante mit nicht auslieferbarer Frage | Frage ersetzen |
| VAL-090 | Blocker | Variante leer oder mit Lücken | Positionen korrigieren |
| VAL-120 | Blocker | Voraussetzungen bilden einen Zyklus | Zyklus auflösen |
| VAL-121 / 127 | Blocker | Voraussetzung zeigt auf fehlende Activity / auf Objekt außerhalb des Kurses | Verweis korrigieren |

Weitere Regelgruppen (Medienrechte, Barrierefreiheit, Zertifikate, Drip) folgen mit R1-27/R1-34/R1-44/R1-46. Korrekturvorschläge mit Vorschau und Sprung mit Fokus ins Feld: geplant (R1-32/R1-19).

## 7. Veröffentlichen und Revisionen

- Veröffentlichen erzeugt eine **Kursrevision** mit eingefrorener Struktur (Module, veröffentlichte Lektionen, Activities, Prüfungsregeln, Zugangsregeln). Ist die Struktur unverändert, entsteht keine neue Revision (VAL-008).
- Die Vorgängerrevision wird `superseded`. Lernende bleiben auf ihrer Revision, neue Lernende starten auf der neuen. Umstellen laufender Lernender (Staging, Vorwärtsrollen): geplant (R1-27).
- Vorschau: WordPress-Vorschau der Lektion; eine Lernenden-Vorschau ist geplant (R1-27).
