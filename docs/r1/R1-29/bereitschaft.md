# R1-29 · Bereitschaft der Referenzaufgaben (Stand 17.11.2026, Commit 6f6f05f)

**Zweck:** R0-26 §4.2 Schritt 2 verlangt eine abgenommene Testumgebung, bevor Personen eingeladen werden. Diese Tabelle zeigt je Referenzaufgabe, ob die **Oberfläche** sie heute zulässt. Grundlage: Code-Lesung (`src/Admin`, `src/Learner`, `src/Certificate`, `src/Ops`) und ein HTTP-Rauchtest gegen die Referenz-Site (§3). Eine Aufgabe, die nur über REST, WP-CLI oder PHP-Dienste geht, ist für einen Nutzertest **nicht bereit**: Die Person würde an der fehlenden Oberfläche scheitern, und der Befund wäre bekannt, bevor die Sitzung beginnt.

Legende: **bereit** = per Oberfläche lösbar · **teilweise** = lösbar mit bekannter Einschränkung (Spalte „Einschränkung“) · **blockiert** = keine Oberfläche; nicht in Runde 1 aufnehmen.

## 1. Matrix je Aufgabe

### Autor (Flow 1)

| ID | Stand | Einschränkung / fehlende Oberfläche | Zuständig (Vorschlag) |
|---|---|---|---|
| AU-T01 | teilweise | Kurs im Block-Editor anlegbar; **kein Feld „Lernziele“** – Lernziele nur im Beschreibungstext | R1-05/R1-16 |
| AU-T02 | teilweise | Module über Metabox „Modules“ (Positionszahlen), Lektionen über „Übergeordnet/Reihenfolge“; kein Ziehen, kein Strukturbaum mit Tastaturbefehlen (A11Y-008 nur über Zahlenfelder) | R1-05 |
| AU-T03 | teilweise | Video/Datei/Untertitel nur als **JSON-Einstellungen** in der Metabox „Activity“; Medien-UID muss bekannt sein – für Autor:innen ohne Technikwissen praktisch nicht lösbar | R1-05/R1-16 |
| AU-T04, AU-T05 | **blockiert** | kein Fragen-Editor (Autorenhandbuch „geplant R1-32/R1-33“) | Fragen-UI |
| AU-T06 | **blockiert** | Import nur REST/WP-CLI, keine Oberfläche mit Dry-run | R1-15/R1-36 |
| AU-T07 | **blockiert** | kein Prüfungs-Editor (Blueprint, Teilgrenzen, Zeit) | R1-08 |
| AU-T08 | teilweise | Kurseinstellungen vorhanden; **keine Vorschau „als Lernende“** (R0-22 §2.3), Zugangsmodus Selbstanmeldung ohne Lernenden-Gegenstück | R1-26 |
| AU-T09 | teilweise | Validierungspanel „Publish revision“ vorhanden; **Alt-Text-Regel A11Y-091 fehlt im Validator** (R1-19 offen), Blueprint-Fehler nur mit Prüfungs-Editor erzeugbar | R1-26 |
| AU-T10 | **blockiert** | ohne Fragen-Editor kein Revisionshinweis an einer Frage | Fragen-UI |
| AU-T11 | **blockiert** | keine Oberfläche für manuelle Zugänge und Fortschrittsansicht (A-09) | R1-09/R1-10 |
| AU-T12 | teilweise | Aufgabe nur über JSON-Einstellungen der Activity | R1-46 Folge |
| AU-T13 | **blockiert** | keine Bewertungsoberfläche (R1-46 §5: nur REST) | R1-46 Folge |
| UX-012 Störfall | teilweise | WordPress-Autosave im Block-Editor; Metabox-JSON wird nicht zwischengespeichert | – |

### Lernende (Flow 2)

| ID | Stand | Einschränkung / fehlende Oberfläche | Zuständig |
|---|---|---|---|
| LE-T01 | **blockiert** | Katalog (`/kurse/`) vorhanden, aber **keine Selbstanmeldung** (kein Knopf, keine Route). Ersatz für Folgeaufgaben: `scenario einschreiben` | R1-09/R1-16 |
| LE-T02 | bereit (Code) | **Befund B-01 behoben:** Lektionslinks führen auf die Lernbereichsroute `/lernen/kurs/<uid>/lektion/<id>` (`LessonView`), Abschluss `manual_confirm`/`view`/`downloaded` per Formular ohne JS; abgedeckt durch `tests/integration/LessonViewTest.php`. Rauchtest gegen die Referenz-Site steht noch aus | R1-16 |
| LE-T03 | **blockiert** | kein Video-Player im Lernbereich (R1-16 A-1, R1-19 A11Y-063/090) | R1-16/R1-19 |
| LE-T04, LE-T05, LE-T07 | **blockiert** | Smarttrainer, Fehlertraining, fällige Wiederholung nur REST (R1-19 A11Y-024 offen) | R1-16/R1-19 |
| LE-T06 | teilweise | Prüfungsraum ohne JS: Start, Antworten, Markieren, Timer mit Ansagen, Bestätigung, Wiederaufnahme serverseitig **bereit**. **Freitext, Karteikarte, Reihenfolge, Lückentext** sind im Prüfungsraum nicht beantwortbar („zählt als unbeantwortet“) – UX-038 nur für Zuordnung und Zahl prüfbar | R1-19/R1-20 |
| LE-T08 | bereit | Ergebnis im Prüfungsraum, Nachweisliste, Nachweisansicht mit Prüf-Link, öffentliche Verifikation (Rauchtest §3) | – |
| LE-T09 | **blockiert** | kein Export im Lernbereich; nur WordPress-Datenschutzanfrage durch den Betreiber (R1-34) | R1-34 |
| LE-T10 | bereit (Code) | „Continue: <Lektion>“ öffnet die Lektion direkt (B-01 behoben); Rauchtest gegen die Referenz-Site steht noch aus | R1-16 |
| LE-T11, LE-T12 | **blockiert** | keine Abgabe-Oberfläche (R1-46 §5) | R1-46 Folge |
| UX-031 | bereit | Sperrgründe als Text im Kursraum (Drip „Available from …“, „Locked: not yet released“) | – |
| UX-032 | bereit | Ablaufmeldung und Ergebnis im Prüfungsraum (Rauchtest §3) | – |
| UX-035 | teilweise | nur Prüfungsraum (wie LE-T06) | – |
| UX-075 | teilweise | Zeitausgleich wirkt; Anzeige „inkl. Zeitausgleich“ vor dem Start fehlt (R1-19 A11Y-070) | R1-19 |

### Gruppenverwaltung (Flow 3) und Admin (Flow 4)

| ID | Stand | Einschränkung | Zuständig |
|---|---|---|---|
| GV-T01 … GV-T06 | **blockiert** | Gruppenverwaltung nur im Admin für `ccap_manage_groups`; **keine Besitzer-Oberfläche L-18** im Lernbereich | R1-35 Folge |
| GV-T04, GV-T07 | **blockiert** | Beitrittslink `?ccap_claim=` wird im Lernbereich nicht ausgewertet (kein Handler gefunden) | R1-35 Folge |
| AD-T01, AD-T02 | bereit | Setup-Assistent mit Wiedereinstieg (R1-32) | – |
| AD-T03 | bereit | Bereitschaftsprüfung; für den Dateischutz-Fehler nginx-Staging ohne Regel nötig | – |
| AD-T04 | bereit | Einstellungs-Tabs, Warnung bei ungespeicherten Änderungen | – |
| AD-T05 | bereit | Systemstatus › Hintergrundaufgaben mit Retry; Störfall `scenario warteschlangenfehler` | – |
| AD-T06 | bereit | Admin-Seite „Groups“ | – |

### Hilfstechnik und Expertendurchläufe

| ID | Stand | Hinweis |
|---|---|---|
| UX-070 … UX-073 (Flow 2 mit AT) | teilweise | nur Prüfungsraum, Kursraum, Nachweise prüfbar; LE-T01…T05/T09/T11 blockiert (siehe oben) |
| UX-074 (Flow 1 mit AT) | teilweise | nur AU-T01/T02/T09 |
| UX-076/077 Expertendurchläufe | bereit für vorhandene Seiten | Lernbereich, Prüfungsraum, Nachweise, Setup, Einstellungen, Systemstatus, Gruppen-Admin |
| UX-078 Mails | teilweise | Mail-Sink vorhanden; Mailprogramm-Test braucht echte Zustellung an ein Testpostfach |
| UX-079 Druckansicht Nachweis | bereit | |
| UX-080 externe Medien „Klick zum Laden“ | **blockiert** | kein Video-Player im Lernbereich |
| UX-081 Upload/Bewertung | **blockiert** | keine Abgabe- und Bewertungsoberfläche |

## 2. Befunde aus der Vorbereitung (vor Runde 1 zu klären)

| Nr. | Befund | Schwere (Vorschlag nach R0-26 §4.4) | Beleg |
|---|---|---|---|
| B-01 | Lektionslinks im Kursraum führen auf 404; Lernende erreichen keine Lektion | S1 (kritische Aufgabe LE-T02/LE-T10 unlösbar) | Rauchtest §3, `LearningArea::course_room()` nutzt `get_permalink()` eines nicht öffentlichen Typs. **Behoben (17.11.2026):** Lernbereichsroute `kurs/<uid>/lektion/<id>` mit `LessonView`; Rauchtest wiederholen |
| B-02 | Keine Selbstanmeldung (UX-020 ★) | S1 | kein Handler für `free_enrollment` im Lernbereich |
| B-03 | Fragen aus einem Modulpool mit **Karteikarten** werden in Blueprint-Prüfungen gezogen und sind dort nicht beantwortbar (RK-1-Abschlussquiz zieht 20 aus 50 inkl. 5 Karteikarten) | zu klären (Produkt): Karteikarten aus Prüfungspools ausschließen oder Drehbuch ändern | Rauchtest §3; im deterministischen Seed enthält Prüfung 1 eine Karteikarte (Position 3) sowie Reihenfolge (13, 15) und Lückentext (20) |
| B-04 | Freitext/Reihenfolge/Lückentext im Prüfungsraum nicht beantwortbar; RK-2-Prüfung enthält diese Typen | S2 bei Prüfungen mit diesen Typen | R1-19 A11Y-023, Rauchtest |
| B-05 | Alt-Text-Regel fehlt im Veröffentlichungs-Validator; AU-T09-Blocker 1 wird nicht erkannt | S1 für AU-T09 (UX-009 ★) | R1-19 A11Y-091 offen |
| B-06 | Ohne WordPress-Sprachpaket `de_DE` bleibt der Lernbereich englisch (Testsprache E-169) | Umgebung | Seed-Lauf in diesem Container (kein Download möglich) |
| B-07 | `vendor/bin/wp` lädt `CourseCaptain\` aus `<repo>/src` statt aus dem installierten Plugin; betrifft auch `tools/release/artifact-test.sh` (J-17 prüft dann nicht den ZIP-Code) | Werkzeug | `isolate-autoload.php`, Kontrollzeile im Seed-Lauf |

## 3. Rauchtest gegen die Referenz-Site (17.11.2026)

Lokaler Lauf mit `php -S 127.0.0.1:8099` auf `/tmp/ccap-usab-referenz` (Konten mit gesetztem Testpasswort, nur für diesen Test):

| Prüfung | Ergebnis |
|---|---|
| `/kurse/` anonym | 200, drei Kurse mit Link „Open … in the learning area“ |
| `/lernen/` anonym | 302 auf die Anmeldung mit Rücksprung |
| `cc_fx_ux_p01` → „Mein Lernen“ | 200, RK-2 und RK-3 „Access active“, „Continue: Zellen, Zeilen, Spalten“ |
| Kursraum RK-3 | 200, Modul 2 „Available from November 18, 2026“, Lektionen „Locked: not yet released“ (UX-031) |
| Lektionslink aus dem Kursraum | **404** (B-01) |
| `cc_fx_ux_pruefung` → `/lernen/pruefung/1` | 200, 10 Fragen „answered“, 2 „marked for review“; Bild mit Alt-Text; Freitext/Karteikarte „cannot be answered in this view yet“ |
| `cc_fx_ux_abgelaufen` → `/lernen/pruefung/2` | 200, „The time is up. Your answers have been submitted. Not passed …“ |
| `cc_fx_ux_bestanden` → Nachweise | 200, Nachweis „Valid“, Prüf-Link; Prüf-Link anonym 200 „Certificate verification Valid“ |
| `scenario sitzungen-beenden` | danach `/lernen/` → 302 auf die Anmeldung (UX-012-Störfall wirkt) |

Die englischen Texte im Rauchtest folgen aus B-06.

## 4. Empfehlung für den Ablauf (R0-26 §4.2)

1. **Jetzt möglich:** Pilot und Expertendurchläufe (Schritte 3/4) für Flow 4 (AD-T01…T06), den Prüfungsraum (LE-T06 Teil 2 mit `cc_fx_ux_pruefung`, UX-026/UX-032/UX-075) und die Nachweise (LE-T08/UX-079), dazu AU-T01/T02/T09 als Teil-Pilot.
2. **Runde 1 erst starten**, wenn B-01, B-02 und die als „blockiert“ markierten Oberflächen der ★-Aufgaben (UX-007, UX-017, UX-020, UX-023, UX-036, UX-051, UX-053) vorhanden sind. Sonst verfehlen S-02 und S-A11Y die Schwelle mit Sicherheit, und die Sitzungen kosten Budget ohne neue Erkenntnis.
3. Vor jeder Runde diese Tabelle gegen den Release-Kandidaten neu prüfen und in `vorlage-abnahmebericht.md` §2 übernehmen.
