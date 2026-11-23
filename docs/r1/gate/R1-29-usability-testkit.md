# R1-29 · Usability-Testkit (Ausführungsmappe für die Inhaberin)

**Stand:** 23.11.2026 · **Code-Stand:** `claude/blissful-curie-tuuf7q` @ `8ac511f` (nach den R1-18-Gruppen g1…g6 und E-339)
**Zweck:** Alles, was für die Usability-Abnahme R1-29 nötig ist, in einer Mappe: Was die Inhaberin entscheiden und unterschreiben muss, welche Aufgaben heute testbar sind, wen man rekrutiert, welche Schwellen gelten, die Bögen (Einwilligung, Vorab-Fragebogen, SEQ, SUS), die Protokoll- und Auswertungsvorlagen und die Nachtest-Regel.
**Status:** **Vorbereitet, nicht durchgeführt.** Diese Mappe enthält keine Messwerte. R1-29 bleibt offen, bis Runde 1 und Runde 2 gelaufen sind und der Abnahmebericht freigegeben ist (R0-26 §4.2 Schritt 9).
**Grundlage:** R0-26 (vollständig), R0-15 §2.3/§2.4, E-161…E-171, E-126, E-45a; Vorbereitung `docs/r1/R1-29-vorbereitung.md` und die Detailunterlagen in `docs/r1/R1-29/`; Funktionsstand aus `docs/r1/R1-05/-16/-19/-20/-32/-35/-46-nachweis.md`, `docs/r1/R1-18-nachweis.md` §9 und den Nachträgen g2/g3/g5. Clean Room (E-01/E-327): nur `docs/r0`, `docs/r1`, `konzept.md`, TODO und Code; keine LearnBoating-Inhalte.

**Verhältnis zu den vorhandenen Unterlagen:** Die Mappe ersetzt nichts in `docs/r1/R1-29/`. Sie bündelt die Punkte, die eine Unterschrift oder Entscheidung brauchen, schreibt die Bereitschaft auf den Stand vom 23.11.2026 fort (§2) und enthält die Bögen in druckfertiger Form. Für den genauen Kartentext, die Startzustände und die Systemprüfung je Aufgabe gelten die Skripte `skript-autor.md`, `skript-lernende.md`, `skript-gruppenverwalter.md` und `skript-admin.md`.

---

## 1. Was die Inhaberin entscheiden oder unterschreiben muss

Reihenfolge = Reihenfolge der Erledigung. Ohne die Punkte 1–5 kann keine Sitzung mit echten Personen stattfinden.

| Nr. | Entscheidung / Handlung | Grundlage | Vorschlag | Erledigt (Datum, Kürzel) |
|---|---|---|---|---|
| U-1 | **Umfang der Runde 1 beschließen:** Variante A „Runde 1 erst, wenn alle ★-Oberflächen vorhanden sind“ oder Variante B „jetzt Pilot + Teilrunde mit den testbaren Aufgaben (§2.4), Rest als Runde 1b“ | R0-26 §4.2 Schritt 2, `bereitschaft.md` §4 | **B** (Pilot und Expertendurchläufe jetzt; Runde 1 mit Teilumfang erst nach Schließen von B-02 Selbstanmeldung; blockierte Aufgaben in Runde 1b) | ☐ A ☐ B ☐ anders: ____ |
| U-2 | **Budget** je Sitzung und für Testgeräte nennen (Pauschale je Sitzung plus Geräte) | E-162, AG-06, OP-26-2 | Mengengerüst §4.3 | Betrag: ____ € |
| U-3 | **Verantwortliche Stelle, Speicherort und Löschtermin** der Aufzeichnungen festlegen; Einwilligungstext §5 von der Datenschutzberatung freigeben lassen (Art. 9 DSGVO wegen Hilfstechnik-Angaben) | R0-26 §4.1, E-167, R0-17 | Speicherort beim Auftraggeber, nicht im Repository; Löschung 6 Monate nach R1-GATE | ☐ freigegeben, Ort: ____ |
| U-4 | **Moderation benennen** (nicht an der Entwicklung beteiligt; extern für Barrierefreiheitssitzungen) und **A11Y-Fachperson** für die Expertendurchläufe | E-164, R0-26 §2.1 | 1 Moderation + 1 Protokoll je Sitzung | Moderation: ____ · A11Y: ____ |
| U-5 | **Staging bereitstellen** nach R0-14 §3 (https, Sprachpaket de_DE, Apache + zusätzlich nginx für AD-T03, Fremdhost-Video für LE-T03) | R0-26 §3.3, `preflight-checkliste.md` A | Aufbau mit `tools/usability/setup-site.sh` (§3) | URL: ____ |
| U-6 | **SUS-Fassung** festlegen (veröffentlichte deutsche Übersetzung, unverändert) | R0-26 §2.3 Nr. 6, A-8 der Vorbereitung | gängige Fassung §6.3, sofern die Datenschutz-/UX-Beratung keine andere vorgibt | ☐ Fassung §6.3 ☐ andere: ____ |
| U-7 | **Referenzkurse bestätigen:** Typmix und Aufgabenkonfiguration `cc-fx-rk-v1`, Soll-Punkte AU-T13, Frage B-03 (Karteikarten im Prüfungspool; nach E-337 zählen Karteikarten als Fragen, im Prüfungsraum sind sie aber nicht beantwortbar) | OP-26-4, AG-12 | Karteikarten aus Prüfungspools der Referenzkurse herausnehmen (Drehbuch), Produktfrage getrennt klären | ☐ bestätigt ☐ Änderungen: ____ |
| U-8 | **Pilot und Praxis-Benchmark** (UX-090) abnehmen; Sitzungsdauer Autor (120 min) bestätigen; Schwellen S-06…S-10 danach einfrieren | R0-26 §2.5, §4.2 Schritt 3, E-166 | einmalige Prüfung, E-166-Werte nicht lockern | ☐ eingefroren am ____ |
| U-9 | **Rekrutierung beauftragen** (§4) | E-161, E-163 | eigene Kontakte für Autor/Admin/GV, Dienstleister oder Verband für Hilfstechnik | ☐ beauftragt |
| U-10 | **Abnahmebericht freigeben** (`vorlage-abnahmebericht.md`); akzeptierte S2 nur mit Begründung, nie mit WCAG-Bezug in Lernenden-Flows | E-171, R0-26 §4.4 | – | ☐ freigegeben am ____ |

---

## 2. Funktionsstand am 23.11.2026 und was heute testbar ist

### 2.1 Änderungen seit der Bereitschaftsprüfung vom 17.11.2026

Die Tabelle `docs/r1/R1-29/bereitschaft.md` stammt vom 17.11.2026. Seitdem hat sich im Code geändert (Quelle: Commits bis `8ac511f`, Nachträge R1-18 g2/g3/g5, Code-Lesung):

| Aufgabe | Stand 17.11. | Stand 23.11. | Beleg |
|---|---|---|---|
| LE-T02, LE-T10 | bereit (B-01 behoben) | unverändert bereit | `src/Learner/LessonView.php` |
| LE-T03 Video | blockiert (kein Player) | **teilweise:** Lektionsansicht zeigt eigene/geschützte Videos mit nativem Player und WebVTT-Untertitel; externe Videos bei `always_load` als Player bzw. bei YouTube/Vimeo als Link „Video auf <Host> ansehen“; bei `click_to_load` nur der Satz „Dieses Video wird von <Host> bereitgestellt und erst nach deiner Zustimmung geladen.“ **ohne Knopf** (Lektionsansicht hat kein JavaScript) | `LessonView::video()`, `ExternalMedia::video_view()` |
| LE-T06 Prüfung | teilweise (Freitext/Karteikarte/Reihenfolge/Lückentext nicht beantwortbar) | **teilweise, besser:** Freitext jetzt ohne JS beantwortbar (Musterantwort aufdecken + Selbsteinschätzung, F-4); Einfach-/Mehrfachauswahl, Wahr/Falsch, Zahl, Zuordnung beantwortbar; **Reihenfolge, Lückentext und Karteikarte weiterhin nicht** („zählt als unbeantwortet“) | `src/Learner/ExamRoom.php`, R1-18 g2 |
| LE-T09 Datenexport | blockiert | **teilweise:** Shortcode `[ccap_privacy_request]` (Antrag Export/Löschung für das eigene Konto), Bestätigungsmail, asynchroner Export `POST /me/data-exports`; braucht eine Seite mit dem Shortcode auf der Test-Site und den Mail-Sink | `src/Privacy/SelfService.php`, `DataExport.php`, R1-18 g5 GM-174/175 |
| GV-T07 Einladung annehmen | blockiert (kein Handler) | **teilweise:** Einladungsseite (anmelden/registrieren, „Einladung annehmen“); Seite ist minimal, REST R-31/R-32 fehlen | `src/Learner/Invitation.php`, R1-18 §9.3 g3 H-5 |
| GV-T01…T06 Besitzer-Oberfläche | blockiert | **unverändert blockiert** (keine Besitzer-Oberfläche L-18 im Lernbereich; Gruppenverwaltung nur im Admin mit `ccap_manage_groups`) | `src/Admin/GroupsScreen.php` |
| LE-T01 Selbstanmeldung (B-02) | blockiert | **unverändert blockiert** | kein Handler für `free_enrollment` im Lernbereich |
| LE-T04/T05/T07 Smarttrainer, Fehlertraining, fällig | blockiert | **unverändert blockiert** („Diese Übung kann auf dieser Seite noch nicht genutzt werden.“) | `LessonView::activity()` |
| LE-T11/T12 Abgabe | blockiert | **unverändert blockiert** („Das Einreichen der Aufgabe ist im Lernbereich noch nicht verfügbar.“) | `LessonView::activity()` |
| AU-T04/T05/T07/T10/T11/T13 | blockiert | **unverändert blockiert** (kein Fragen-, Prüfungs-, Bewertungs-Editor, keine Zugangs-/Fortschrittsansicht) | `src/Admin/*` |
| AU-T06 Import | blockiert | **unverändert blockiert** (Import nur REST/WP-CLI) | R1-41 offen „Admin-Oberfläche“ |
| Sprache | englisch ohne Sprachpaket (B-06) | **113 fehlende deutsche Texte ergänzt** (1be71c8); Sprachpaket des WordPress-Kerns de_DE muss auf Staging trotzdem installiert sein | `languages/course-captain-de_DE.po` |

**Vor jeder Runde:** Bereitschaft gegen den Release-Kandidaten neu prüfen (Rauchtest `bereitschaft.md` §3) und das Ergebnis in den Abnahmebericht §2 übernehmen.

### 2.2 Aufgaben je Rolle (Kurzfassung; Volltext in den Skripten)

Spalten: ★ = kritische Aufgabe (S-02, S-A11Y) · Ziel = Zielzeit aus R0-26 §1 · Abbruch = dreifache Zielzeit, höchstens 20 min (Hilfstechnik: vierfache) · Stand = 23.11.2026.

**Autor (Flow 1, 120 min, Profil `autor`, RK-1/2/3):**

| ID | Aufgabe (Ziel, nicht Weg) | Erfolg (Systemprüfung) | Ziel | ★ / UX | Stand |
|---|---|---|---|---|---|
| AU-T01 | Kurs mit Titel, Beschreibung, Lernzielen anlegen | Entwurf mit 3 Angaben (Lernziele im Beschreibungstext, A-5) | 4 min | UX-001 | teilweise |
| AU-T02 | Module und 10 Lektionen nach Drehbuch; eine Lektion verschieben | Struktur = Soll (`check-rk`) | 15 min | UX-002 | teilweise (nur Zahlenfelder) |
| AU-T03 | Video mit Untertiteln, externes Video, PDF einfügen | Spur, Fremd-URL, PDF verknüpft | 8 min | UX-003 | teilweise (JSON-Einstellungen, für Laien kaum lösbar) |
| AU-T04/T05 | 3 bzw. 7 Fragen anlegen, eine mit Bild | Fragen = Soll, Alt-Text | 8 / 14 min | UX-004/005 | **blockiert** |
| AU-T06 | 40 Fragen per Datei übernehmen | Dry-run, Freigabe, 50 Fragen | 6 min | UX-006 | **blockiert** |
| AU-T07 | Prüfung einrichten | Parameter = Soll | 10 min | ★ UX-007 | **blockiert** |
| AU-T08 | Zugang einstellen, Vorschau als Lernende | Zugangsmodus = Soll | 6 min | UX-008 | teilweise (keine Vorschau) |
| AU-T09 | Veröffentlichen (2 eingebaute Fehler) | beide Blocker gefunden, veröffentlicht | 8 min | ★ UX-009 | teilweise (Alt-Text-Regel fehlt im Validator, B-05) |
| AU-T10 | Frage im veröffentlichten Kurs ändern | Revisionshinweis + Verständnisfrage | 4 min | UX-010 | **blockiert** |
| AU-T11 | Testnutzer einschreiben, Fortschritt ansehen | Grant aktiv | 5 min | UX-011 | **blockiert** |
| AU-T12 | Aufgabe anlegen und Bewertung einstellen | Aufgabe = Soll | 6 min | UX-016 | teilweise (JSON) |
| AU-T13 | 2 Abgaben bewerten, 1 zurückgeben | 2 `graded`, 1 `returned` | 8 min | ★ UX-017 | **blockiert** |
| Störfall | Autosave bei Sitzungs-/Netzverlust (in AU-T05) | 0 verlorene Eingaben | – | ★ UX-012 | teilweise |
| AU-GES | Referenzkurs veröffentlicht | Median ≤ 90 min, max. 120 min | – | ★ UX-013 | **nicht messbar**, solange AU-T04…T07 blockiert |

**Lernende (Flow 2, 75 min; Hilfstechnik 90 min, Referenz-Site):**

| ID | Aufgabe | Erfolg | Ziel | ★ / UX | Stand |
|---|---|---|---|---|---|
| LE-T01 | Kurs finden und selbst anmelden | Grant `free_enrollment` | 3 min | ★ UX-020 | **blockiert (B-02)**; Ersatz für Folgeaufgaben `scenario einschreiben` |
| LE-T02 | Beginnen, erste Lektion abschließen | Completion L1 | 3 min | UX-021 | bereit |
| LE-T03 | Video mit Untertiteln, dann externes Video | Untertitel an, externes Video abgespielt | 4 min | UX-022 | teilweise (§2.1) |
| LE-T04 | Smarttrainer Modul 1 | ≥ 10 Fragen, Wiederholung benannt | 8 min | ★ UX-023, ★ UX-038 | **blockiert** |
| LE-T05 | Fehlertraining | ≥ 1 Frage | 3 min | UX-024 | **blockiert** |
| LE-T06 | Prüfung: ≥ 10 Antworten, 2 markieren; Abbruch; fortsetzen und abgeben | 0 Antwortverlust, Markierungen da, Abgabe bestätigt | Fortsetzen ≤ 1 min | ★ UX-025, ★ UX-026 | teilweise (Reihenfolge/Lückentext/Karteikarte nicht beantwortbar; Prüfungen im Drehbuch daraufhin prüfen) |
| LE-T07 | Fällige Wiederholungen | alle fälligen bearbeitet | 4 min | UX-027 | **blockiert** |
| LE-T08 | Ergebnis, Nachweis, Prüf-Link kopieren und öffnen | richtig wiedergegeben, Link geöffnet | 4 min | ★ UX-028 | bereit |
| LE-T09 | Daten herunterladen | Export erhalten | 3 min | UX-029 | teilweise (Seite mit Shortcode nötig) |
| LE-T10 | Am nächsten Tag mobil fortsetzen | richtige Lektion | 1 min | ★ UX-030 | bereit |
| LE-T11/T12 | Aufgabe einreichen; zurückgegebene überarbeiten | `submitted`; zweite Fassung | 6 / 5 min | ★ UX-036, UX-037 | **blockiert** |
| LE-GES | Ende-zu-Ende | Desktop ≤ 60, mobil ≤ 70 min | – | ★ UX-033 | **nicht messbar**, solange T01/T04/T05/T07/T11 blockiert |

**Gruppenverwaltung (Flow 3, 30 min) und Admin (Flow 4, 60 min):**

| ID | Aufgabe | Ziel | ★ / UX | Stand |
|---|---|---|---|---|
| GV-T01…T06 | freie Plätze, einladen, adressgebundener Link, Beitritt prüfen, Link ungültig, Platz freigeben | 2–4 min | UX-050, ★ UX-051, UX-052, ★ UX-053, UX-054 | **blockiert** (keine Besitzer-Oberfläche) |
| GV-T07 | Einladung annehmen (Gruppe voll / Einladung abgelaufen) | 2 min | UX-055 | teilweise (Einladungsseite minimal) |
| AD-T01 | Setup mit Selbstanmeldung, Kurse unter /lernen/ | ≤ 10 min | ★ UX-060 | bereit |
| AD-T02 | Setup nach Abbruch fortsetzen | 1 min | ★ UX-061 | bereit |
| AD-T03 | Bereitschaftsprüfung: Ursache finden (Dateischutz) | 4 min | UX-062 | bereit (nginx-Staging ohne Regel) |
| AD-T04 | Feedback-Einstellung; zweite Änderung verwerfen | 5 min | UX-063 | bereit |
| AD-T05 | Hängende Hintergrundaufgaben erneut ausführen | 6 min | UX-064 | bereit (`scenario warteschlangenfehler`) |
| AD-T06 | Gruppe mit 5 Plätzen und Besitzerin anlegen | 4 min | UX-065 | bereit |

### 2.3 Szenarien Hilfstechnik, mobil, Fehlerbehebung

| Bereich | Szenarien (Detail) | Heute durchführbar |
|---|---|---|
| Hilfstechnik (Nutzertest + Expertendurchlauf) | AT-01 NVDA + Firefox ESR (**Z**), AT-04 VoiceOver iOS (**Z**), AT-06 nur Tastatur (**Z**), AT-07 Vergrößerung 200/400 % (**Z**); AT-02, AT-03, AT-08, AT-09 (**E**, Expertendurchlauf Pflicht); AT-05 TalkBack (**B**); AT-10 JAWS nur informativ (E-126). Ablauf je Kombination: `docs/r1/R1-29/szenarien-hilfstechnik.md` §2 | Prüfungsraum (inkl. Zeitausgleich UX-075, aber ohne Anzeige „inkl. Zeitausgleich“ vor dem Start), Kursraum, Lektion, Nachweise, Setup, Einstellungen, Systemstatus, Gruppen-Admin |
| Mobile Prüfungsläufe | MP-1 … MP-5; LE-T06 **mindestens einmal auf BR-07 (iOS) und einmal auf BR-08 (Android) mit echtem Netzabbruch** (Flugmodus + App-Wechsel) | ja (Offline-Puffer und Wiederaufnahme vorhanden, R1-20, g2) |
| Fehlerbehebung (Störfälle) | FB-01 … FB-17 in `szenarien-fehlerbehebung.md`: Sitzung ungültig (`scenario sitzungen-beenden`), Prüfungsablauf (`scenario ablauf`), Queuefehler (`scenario warteschlangenfehler`), Netzabbruch, Tab geschlossen, abgelaufene Einladung, volle Gruppe | ja für FB mit Prüfung, Sitzung, Queue, Setup; nein für FB mit blockierten Oberflächen |
| Expertendurchläufe | UX-076/077 (A11Y-102/103) Seitencheckliste; UX-078 Mails (echte Zustellung an Testpostfach); UX-079 Druckansicht Nachweis; UX-080 externe Medien „Nach Klick laden“; UX-081 Upload/Bewertung | UX-076/077/079 ja; UX-078 nur mit Testpostfach; **UX-080 im Modus `click_to_load` nicht lösbar** (kein Knopf in der Lektionsansicht, §2.1); UX-081 nein |

### 2.4 Vorschlag für den Umfang (zu U-1)

| Stufe | Inhalt | Personen | Voraussetzung |
|---|---|---|---|
| **Jetzt** | Pilot (1 projektfremde Person je Rolle, nicht gewertet) und Expertendurchläufe AT-01…AT-09 für: AD-T01…T06, LE-T02/T03/T06/T08/T09/T10, AU-T01/T02/T08/T09 | 1 je Rolle + A11Y-Fachperson | U-3, U-4, U-5 |
| **Runde 1a** | Admin (5) vollständig; Lernende (5 + 4 AT) mit LE-T02/T03/T06/T08/T09/T10 und Störfällen; LE-T01 per `scenario einschreiben` überbrückt und als **nicht getestet** berichtet | 14 | U-1…U-9; B-02 entweder behoben oder bewusst ausgeklammert |
| **Runde 1b** | Autor (6), Lernende-Restaufgaben, Gruppenverwaltung (5) | 11 + Nachholer | Fragen-/Prüfungs-/Bewertungs-Editor, Import-Oberfläche, Selbstanmeldung, Smarttrainer-/Abgabe-UI, Besitzer-Oberfläche |
| **Runde 2** | Nachtest nach §8 | ≥ 3 neue je betroffener Rolle | Behebung S1/S2 |

**Wichtig:** Eine Teilrunde liefert für die blockierten ★-Aufgaben **keinen** Erfolg. S-02, S-06, S-09 und S-A11Y sind damit erst nach Runde 1b bewertbar; R1-29 und Gate C-11 bleiben bis dahin offen.

---

## 3. Testumgebung (kurz)

| Schritt | Befehl / Handlung | Verantwortlich |
|---|---|---|
| Datenbankrecht einmalig | `GRANT ALL PRIVILEGES ON \`ccap\_usab%\`.* TO 'ccap'@'localhost';` | Betrieb Staging |
| Referenz-Site (Lernende, GV, Expertendurchläufe) | `tools/usability/setup-site.sh --profil referenz --url https://<staging> --site <docroot> --db ccap_usab_referenz --kit <kit-dir>` | Technik |
| Frische Site je Autor bzw. Admin | `--profil autor --site <docroot>/autor-pNN --db ccap_usab_autor_pNN` bzw. `--profil admin …` | Technik, vor jeder Sitzung |
| Fremdhost-Video (LE-T03, E-45a) | `CCAP_UX_EXTERNAL_VIDEO_URL=https://<fremder Host>/…mp4` vor dem Aufbau setzen | Technik |
| Seite für LE-T09 | Seite „Meine Daten“ mit `[ccap_privacy_request]` anlegen | Technik |
| Zurücksetzen | Neuaufbau der Site (≈ 35 s), Passwörter werden einmal ausgegeben | Technik, nach jeder Sitzung |
| Kontrolle | `wp ccap-ux check-rk rk1|rk2|rk3`, Mail-Sink `wp-content/ccap-ux-mails/mails.log` | Protokoll |

Konten, Zustände und Störfall-Befehle: `docs/r1/R1-29/testumgebung.md` §3/§4. Checkliste am Sitzungstag: `preflight-checkliste.md`.

---

## 4. Rekrutierung (E-161, E-163, R0-26 §2.1)

### 4.1 Mengengerüst Runde 1

| Rolle | Anzahl | Pflichtprofil | Ausschluss | Format, Dauer | Weg (E-161) |
|---|---:|---|---|---|---|
| Autor | **6** (2 je RK) | 3 Kursautor/Trainer ohne WP-Kenntnis über den Block-Editor hinaus; 3 regelmäßige WP-Redaktion; SOLL 1 Person mit Tastatur-only oder Lupe | Projektbeteiligte, Personen mit Kenntnis der CC-Oberfläche | moderiert, remote oder vor Ort, 120 min | eigene Kunden/Netzwerk |
| Lernende ohne Hilfstechnik | **5** (3 mobil, 2 Desktop) | gemischte digitale Erfahrung, ≥ 1 mit geringer, Alter 18–65+, ≥ 1 Person 55+ | wie oben | moderiert, 75 min | eigenes Netzwerk |
| Lernende mit Hilfstechnik | **4** | (a) blind, NVDA, Alltagsnutzung ≥ 1 Jahr · (b) VoiceOver iOS · (c) Vergrößerung 200–400 %/Lupe, Kontrast · (d) nur Tastatur | – | moderiert (extern, E-164), eigenes Gerät, 90 min + Pausen | Dienstleister oder Verband |
| Gruppenverwaltung | **5** | organisiert im Alltag ein Team oder eine Lerngruppe | – | 3 unmoderiert remote, 2 moderiert, 30 min | eigenes Netzwerk |
| Admin (Setup) | **5**, davon 1 Sitzung in en_US (E-169) | WP-Site-Betreiber, installieren Plugins selbst | – | moderiert remote, 60 min | eigene Kunden/Netzwerk |
| Expertendurchlauf | 1 A11Y-Fachperson | Matrix AT-01…AT-09 | Entwickler der geprüften Funktion | Checkliste | extern |
| **Summe** | **25 Sitzungen + Expertendurchläufe** | | | ca. 2–3 Sitzungen je Tag | |

Runde 2: mindestens 3 **neue** Personen je betroffener Rolle, mindestens 1 mit derselben Hilfstechnik wie beim Befund (§8).

### 4.2 Abdeckung der Geräte (Pflicht, R0-26 §3.2)

BR-01 Chrome/Win (2 Autoren, 1 Admin) · BR-02 Edge/Win (1 Autor oder Admin) · BR-03 Firefox (1 Autor) · BR-05 Safari/macOS (1 Autor, 1 Admin) · BR-07 iOS Safari n und n−1 (2 Lernende, 1 GV) · BR-08 Android Chrome (1 Lernende, 1 GV) · BR-04/06/09 im Expertendurchlauf. Zuordnung je Person: `docs/r1/R1-29/rekrutierungsplan.csv`.

### 4.3 Kosten (Platzhalter für U-2)

| Posten | Menge | Satz | Summe |
|---|---:|---:|---:|
| Aufwandsentschädigung Autor (120 min) | 6 (+ Nachtest) | ____ € | ____ € |
| Aufwandsentschädigung Lernende (75 min) | 5 (+ Nachtest) | ____ € | ____ € |
| Aufwandsentschädigung Hilfstechnik (90 min + Pausen) | 4 (+ Nachtest) | ____ € | ____ € |
| Aufwandsentschädigung Gruppenverwaltung (30 min) | 5 (+ Nachtest) | ____ € | ____ € |
| Aufwandsentschädigung Admin (60 min) | 5 (+ Nachtest) | ____ € | ____ € |
| Externe Moderation Barrierefreiheit | 4 Sitzungen + Vorbereitung | ____ € | ____ € |
| A11Y-Expertendurchläufe | AT-01…AT-09 | ____ € | ____ € |
| Rekrutierung über Dienstleister/Verband | 4 Personen | ____ € | ____ € |
| Testgeräte (Windows-Notebook mit NVDA, MacBook, iPhone n, iPhone n−1, Android-Mittelklasse, iPad) | je 1 | ____ € | ____ € |
| **Gesamt** | | | **____ €** |

Die Entschädigung wird auch bei Abbruch gezahlt (R0-26 §4.1).

---

## 5. Datenschutz-Einwilligung für Teilnehmende

Vor der Sitzung schriftlich oder barrierefrei elektronisch (zugängliches PDF bzw. Formular). Platzhalter `<…>` füllt die Inhaberin; die Datenschutzberatung gibt den Text frei (U-3). Die Kurzfassung in `moderationsleitfaden.md` §2 bleibt gültig; diese Fassung ergänzt die Pflichtangaben nach Art. 13 DSGVO und die gesonderte Einwilligung nach Art. 9 Abs. 2 lit. a DSGVO.

> ### Einwilligung zur Teilnahme an einem Nutzertest der Software „Course Captain“
>
> **Verantwortlich:** <Name/Firma, Anschrift, E-Mail> · **Datenschutzkontakt:** <Name oder Funktion, E-Mail>
>
> **Worum es geht:** Wir prüfen, ob die Software „Course Captain“ leicht zu bedienen ist. Wir testen die Software – nicht Sie. Es gibt keine falschen Antworten.
>
> **Was wir verarbeiten:**
> 1. Ihre Bildschirminhalte und Ihre Stimme während der Sitzung (Aufzeichnung über <Konferenz- bzw. Testsoftware>).
> 2. Ihr Gesicht **nur**, wenn Sie das unten ausdrücklich ankreuzen.
> 3. Ihre Antworten auf den kurzen Fragebogen (Erfahrung, Gerät, Altersgruppe) und auf die Bewertungsbögen.
> 4. **Freiwillig:** die Art der Hilfstechnik, die Sie nutzen (z. B. „Screenreader“, „Bildschirmlupe“). Diese Angabe kann Rückschlüsse auf Ihre Gesundheit zulassen. Wir speichern nur die Art der Hilfstechnik und keine weiteren Gesundheitsangaben.
>
> **Pseudonym:** In allen Ergebnissen erscheinen Sie nur als `P-<Rolle>-<Nummer>`. Ihr Name steht nicht in Protokollen, Befundlisten oder Berichten. Die Zuordnung Name ↔ Pseudonym führt nur <Rekrutierung/Stelle> getrennt von den Ergebnissen.
>
> **Wer die Daten sieht:** das Testteam (Moderation, Protokoll), <ggf. externe Moderation / Rekrutierungsdienstleister als Auftragsverarbeiter>, <Anbieter der Konferenzsoftware>. Keine Weitergabe zu Werbezwecken.
>
> **Speicherort und Dauer:** Aufzeichnungen liegen bei <Speicherort>. Sie werden **6 Monate nach der Freigabe der Version R1** gelöscht, spätestens am <Datum>. Pseudonyme Protokolle und Befundlisten (ohne Aufzeichnung, ohne Name) bewahren wir als Nachweis der Produktqualität auf.
>
> **Rechtsgrundlage:** Ihre Einwilligung (Art. 6 Abs. 1 lit. a DSGVO), für die Angabe zur Hilfstechnik Ihre ausdrückliche Einwilligung (Art. 9 Abs. 2 lit. a DSGVO).
>
> **Freiwilligkeit und Widerruf:** Die Teilnahme ist freiwillig. Sie können jederzeit ohne Angabe von Gründen und ohne Nachteil abbrechen oder Ihre Einwilligung widerrufen – auch während der Sitzung – per E-Mail an <Adresse>. Der Widerruf gilt für die Zukunft. Die Aufwandsentschädigung erhalten Sie auch bei Abbruch.
>
> **Ihre Rechte:** Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit sowie Beschwerde bei einer Datenschutz-Aufsichtsbehörde.
>
> **Testkonten:** Sie arbeiten nur mit Testkonten. Bitte geben Sie in der Software keine echten persönlichen Daten ein.
>
> | Einwilligung | Ja | Nein |
> |---|---|---|
> | Ich nehme am Nutzertest teil und bin mit der Aufzeichnung von **Bildschirm und Ton** einverstanden. | ☐ | ☐ |
> | Mein **Gesicht** darf aufgezeichnet werden. | ☐ | ☐ |
> | Ich willige ausdrücklich ein, dass die **Art meiner Hilfstechnik** pseudonym gespeichert wird (freiwillig). | ☐ | ☐ |
> | Kurze **Zitate** von mir dürfen ohne Namen im Bericht stehen. | ☐ | ☐ |
>
> Ort, Datum: ____________________ Unterschrift bzw. elektronische Bestätigung: ____________________

**Regeln für das Team** (aus `moderationsleitfaden.md` §2): keine Klarnamen in Protokollen, Dateinamen, Befunden oder Commits; Screenshots nur mit Testkonten `cc_fx_ux_…` und Domain `example.test`; Benachrichtigungen der Person vorher ausschalten lassen; keine Analyse- oder Fremd-Skripte auf der Test-Site; Einwilligungen getrennt vom Repository ablegen.

---

## 6. Bögen

### 6.1 Vorab-Fragebogen (5 min, pseudonym)

| Nr. | Frage | Antwort |
|---|---|---|
| V1 | Rolle in diesem Test | ☐ Autor/in ☐ Lernende/r ☐ Gruppenverwaltung ☐ Admin |
| V2 | Wie oft nutzen Sie WordPress? | ☐ nie ☐ selten ☐ monatlich ☐ wöchentlich ☐ täglich |
| V3 | Haben Sie schon Kurse oder Schulungen erstellt? | ☐ nein ☐ ja, ohne Software ☐ ja, mit Lernsoftware |
| V4 | Haben Sie schon online gelernt? | ☐ nie ☐ 1–2 Kurse ☐ regelmäßig |
| V5 | Gerät, Betriebssystem, Browser mit Version | ______________________ |
| V6 | Nutzen Sie Hilfstechnik? (freiwillig, nur mit Einwilligung Zeile 3) | ☐ keine ☐ Screenreader: ______ Version ____ ☐ Vergrößerung ☐ nur Tastatur ☐ Spracheingabe ☐ Sonstiges: ______ |
| V7 | Wie sicher fühlen Sie sich am Computer? | ☐ 1 sehr unsicher ☐ 2 ☐ 3 ☐ 4 ☐ 5 sehr sicher |
| V8 | Altersgruppe | ☐ 18–34 ☐ 35–54 ☐ 55+ |

### 6.2 SEQ nach jeder Aufgabe

„Wie schwierig oder einfach war diese Aufgabe?“

| Aufgabe | 1 sehr schwierig | 2 | 3 | 4 | 5 | 6 | 7 sehr einfach |
|---|---|---|---|---|---|---|---|
| ____ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ | ☐ |

Bei Hilfstechnik mündlich oder als zugängliches Formular.

### 6.3 SUS-Bogen (am Ende, 10 Aussagen)

Fassung vorbehaltlich U-6 (verbindlich ist die von der Inhaberin festgelegte veröffentlichte deutsche Übersetzung, **unverändert**). Antwort je Zeile genau ein Kreuz; bei ausgelassener Zeile gilt der Mittelwert 3.

| Nr. | Aussage | 1 stimme überhaupt nicht zu | 2 | 3 | 4 | 5 stimme voll zu |
|---|---|---|---|---|---|---|
| 1 | Ich denke, dass ich das System gerne häufig benutzen würde. | ☐ | ☐ | ☐ | ☐ | ☐ |
| 2 | Ich fand das System unnötig komplex. | ☐ | ☐ | ☐ | ☐ | ☐ |
| 3 | Ich fand das System einfach zu benutzen. | ☐ | ☐ | ☐ | ☐ | ☐ |
| 4 | Ich glaube, ich würde die Hilfe einer technisch versierten Person benötigen, um das System benutzen zu können. | ☐ | ☐ | ☐ | ☐ | ☐ |
| 5 | Ich fand, die verschiedenen Funktionen in diesem System waren gut integriert. | ☐ | ☐ | ☐ | ☐ | ☐ |
| 6 | Ich denke, das System enthielt zu viele Inkonsistenzen. | ☐ | ☐ | ☐ | ☐ | ☐ |
| 7 | Ich kann mir vorstellen, dass die meisten Menschen den Umgang mit diesem System sehr schnell lernen. | ☐ | ☐ | ☐ | ☐ | ☐ |
| 8 | Ich fand das System sehr umständlich zu nutzen. | ☐ | ☐ | ☐ | ☐ | ☐ |
| 9 | Ich fühlte mich bei der Benutzung des Systems sehr sicher. | ☐ | ☐ | ☐ | ☐ | ☐ |
| 10 | Ich musste eine Menge lernen, bevor ich anfangen konnte, das System zu verwenden. | ☐ | ☐ | ☐ | ☐ | ☐ |

**Auswertung je Person:** ungerade Aussagen (Antwort − 1), gerade Aussagen (5 − Antwort), Summe × 2,5 = SUS 0…100. Rechenblatt: `docs/r1/R1-29/sus-auswertung.csv`. Schwelle S-04: Mittelwert je Rolle ≥ 70 **und** höchstens 1 Person < 50.

### 6.4 Nachgespräch

1. Was war am schwierigsten? 2. Was hat gefehlt? 3. Würden Sie es so einsetzen?
Verständnisfragen: Autor AU-T10 (laufende Versuche bleiben auf der eingefrorenen Fassung) und AU-T13 (Lernende sehen „zurückgegeben“ mit Kommentar, können neu einreichen) · Gruppenverwaltung GV-T06 (Lernfortschritt bleibt erhalten) · Lernende LE-T08 (bestanden? schwacher Teil?).

---

## 7. Protokoll- und Auswertungsvorlagen

### 7.1 Sitzungsprotokoll (je Sitzung eine Datei `P-<rolle>-<nn>.md`, Ablage beim Auftraggeber bzw. pseudonym im Repository)

```markdown
# Sitzung P-<rolle>-<nn>
- Datum/Uhrzeit: <JJJJ-MM-TT HH:MM> · Dauer: <min> · Runde: Pilot | 1a | 1b | 2
- Format: moderiert vor Ort | moderiert remote | unmoderiert · Sprache: de_DE | en_US
- Moderation: <Kürzel> · Protokoll: <Kürzel>
- Build: <Plugin-Version, Commit> · Paket: cc-fx-rk-v1 (<Hash>) · Referenzkurs: RK-1|2|3 · Site: <referenz | autor-pNN | admin-pNN>
- Gerät/OS: <…> · Browser + Version: <…> · Hilfstechnik + Version + Einstellungen: <… | keine>
- Matrix-Zeile(n): <AT-xx, BR-xx> · Rotation AU-T03/T04: <ungerade: T03 zuerst | gerade: T04/T05 zuerst>
- Profil (Vorab-Fragebogen V1–V8): <…>
- Einwilligung: liegt vor (Aufzeichnung Bildschirm/Ton: ja/nein · Gesicht: ja/nein · Hilfstechnik-Angabe: ja/nein)

| Aufgabe | Start | Ende | Zeit (min) | Lesezeit abgezogen | Ergebnis E/EH/F/Abbr./n. d. | H0/H1/H2 | Bedienfehler | Krit. Fehler | SEQ | Systemprüfung (check-rk, Mail-Sink, Status) | Beobachtung (wörtlich, kurz) | Befund-IDs |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| | | | | | | | | | | | | |

Störfälle: <FB-xx, Zeitpunkt, Reaktion der Person>
SUS-Rohwerte 1–10: <…> · SUS-Score: <…>
Nachgespräch: <3 Antworten + Verständnisfragen richtig/falsch>
Nacharbeit: Site zurückgesetzt um <HH:MM> · Screenshots ohne Personendaten: <Anzahl>
```

`E` Erfolg ohne Hilfe · `EH` Erfolg mit H1 (zählt **nicht** als Erfolg) · `F` nicht erfolgreich, auch nach H2 · `Abbr.` Abbruch · `n. d.` nicht durchführbar (blockierte Oberfläche; zählt weder als Erfolg noch als Misserfolg, wird im Bericht ausgewiesen). Tabellarisch: `protokoll-aufgaben.csv`, `zeiterfassung.csv`.

### 7.2 Befund (eine Zeile in `befunde.csv`, Vorlage `vorlage-befunde.md`)

`UXB-<nnn>` · Schweregrad S1–S4 (erst in der Triage) · Häufigkeit k von n · Aufgabe/Testfall · Screen · Umgebung (AT/BR, Versionen) · Beobachtung · Erwartung · WCAG/A11Y-ID · Beleg (Zeitmarke) · Vorschlag · Zuständig (R1-Task) · Status (offen / behoben <Commit> / Nachtest bestanden <Sitzung> / akzeptiert <Entscheid>) · Regressionstest.

Schweregrade (R0-26 §4.4): **S1** muss vor R1-GATE behoben **und** im Nachtest bestätigt sein (keine Akzeptanz möglich) · **S2** soll behoben sein; Akzeptanz nur dokumentiert durch die Inhaberin, nie bei WCAG-Bezug in Lernenden-Flows (E-171) · **S3/S4** Backlog. Bei Uneinigkeit in der Triage gilt die höhere Stufe.

### 7.3 Auswertungstabelle je Aufgabe (Runde, Rolle)

| Aufgabe | ★ | n gesamt | n. d. | n gewertet | E | EH | F | Abbr. | Erfolgsquote E / gewertet | Schwelle | Median Zeit | Max. Zeit | Zielzeit | SEQ Median | SEQ-Schwelle | krit. Fehler | erfüllt? | Befunde |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---|---:|---:|---:|---:|---|---:|---|---|
| AU-T01 | | | | | | | | | | ≥ 80 % | | | 4 min | | ≥ 5,0 | | ☐ ja ☐ nein | |
| AU-T09 | ★ | | | | | | | | | ≥ 80 % + 0 krit. | | | 8 min | | ≥ 5,0 | | ☐ ja ☐ nein | |
| LE-T06 | ★ | | | | | | | | | ≥ 80 % + 0 krit.; S-08 | | | ≤ 1 min (Fortsetzen) | | ≥ 5,5 | | ☐ ja ☐ nein | |
| … | | | | | | | | | | | | | | | | | | |

### 7.4 Schwellen (E-166, R0-26 §2.5) – Ergebnisblatt

| Nr. | Kriterium | Schwelle | Ist Runde 1 | Ist Runde 2 | erfüllt | Befund bei Verfehlung |
|---|---|---|---|---|---|---|
| S-01 | Erfolg je Kernaufgabe (ohne Hilfstechnik) | ≥ 80 % (n=5: 4/5; n=6: 5/6) | | | ☐ | S2 |
| S-02 | Erfolg je ★-Aufgabe | ≥ 80 % **und** 0 kritische Fehler | | | ☐ | S1 |
| S-03 | Gesamterfolg je Rolle | ≥ 85 % | | | ☐ | S2 |
| S-04 | SUS Mittelwert je Rolle | ≥ 70, höchstens 1 Person < 50 | | | ☐ | S2 |
| S-05 | SEQ Median je Kernaufgabe | ≥ 5,0; kritische Lernenden-Aufgaben ≥ 5,5 | | | ☐ | S2 |
| S-06 | Referenzkurs veröffentlichen (AU-GES) | Median ≤ 90 min, keine Person > 120 min, `check-rk` grün | | | ☐ | S1 |
| S-07 | Setup-Assistent (AD-T01) | Median ≤ 10 min | | | ☐ | S2 |
| S-08 | Prüfung fortsetzen (LE-T06) | Median ≤ 1 min, 0 verlorene Antworten | | | ☐ | S1 |
| S-09 | Ende-zu-Ende Lernende (LE-GES) | Desktop ≤ 60, mobil ≤ 70 min | | | ☐ | S2 |
| S-10 | GV-GES / AD-GES | ≤ 15 / ≤ 30 min Median | | | ☐ | S2 |
| S-A11Y | Hilfstechnik je Person | jede ★-Aufgabe ohne sehende Hilfe (kein H2); SEQ-Median Gruppe ≥ 4,5 | | | ☐ | S1 |
| S-EXP | Expertendurchläufe A11Y-102/103 (Stufe Z/E) | 0 offene S1 | | | ☐ | S1 |
| C-10 | Screenreader NVDA und VoiceOver (E-126) | 0 S1; S2 nur nach E-171 | | | ☐ | – |

Die Werte E-166 (Erfolg ≥ 80 %, SUS ≥ 70, ≤ 90 min, ≤ 10 min, ≤ 1 min) werden nicht gelockert. S-06…S-10 dürfen nach Pilot/Benchmark einmal angepasst werden, danach nur durch die Inhaberin mit Begründung im Bericht (U-8).

---

## 8. Nachtest-Regel (Runde 2)

1. **Auslöser:** jeder S1 und jeder nicht akzeptierte S2 nach der Behebung (Fix-Commit + Regressionstest R1-18/R1-19 oder Eintrag in der Checkliste A11Y-102/103).
2. **Umfang:** nur die betroffenen Aufgaben, plus jede Aufgabe, deren Oberfläche der Fix verändert hat.
3. **Personen:** mindestens **3 neue** Personen je betroffener Rolle; mindestens **1 Person mit derselben Hilfstechnik** wie beim Befund. Wer an Runde 1 teilgenommen hat, nimmt nicht teil – Ausnahme: Bestätigung eines A11Y-Befunds mit seltener Hilfstechnik.
4. **Bestanden:** Schwelle der betroffenen Aufgabe erreicht und der Befund tritt bei keiner Person wieder auf; Status im Befundregister „Nachtest bestanden (<Sitzung>)“.
5. **Nicht bestanden:** Befund bleibt offen; S1 blockiert R1-GATE ohne Ausnahme.
6. **Spätere Gates:** gezielte Wiederholung für geänderte Flows vor R2-GATE und R3-GATE (E-170).

---

## 9. Ablauf bis zum Abnahmebericht

| Schritt (R0-26 §4.2) | Inhalt | Wer | Zeitpunkt | Stand |
|---|---|---|---|---|
| 1 | Rekrutierung, Terminplan, Matrix-Zuordnung | Inhaberin / Rekrutierung | Start | wartet auf U-2, U-9 |
| 2 | Testumgebung abnehmen, `check-rk` grün, Bereitschaft neu prüfen | Technik | −4 Wochen | Werkzeuge fertig; wartet auf U-5 |
| 3 | Pilot + Praxis-Benchmark, Schwellen einfrieren | Moderation, Inhaberin | −3 Wochen | wartet auf U-4, U-8 |
| 4 | Expertendurchläufe A11Y-102/103 | A11Y-Fachperson | −3 bis −2 Wochen | wartet auf U-4 |
| 5 | Runde 1 (1a/1b) | Moderation, Protokoll | −2 Wochen | wartet auf U-1…U-9 und fehlende Oberflächen |
| 6 | Auswertung in 2 Werktagen, Triage (Produkt, Entwicklung, A11Y) | Team | −1 Woche | – |
| 7 | Behebung S1/S2, Regressionstests | Entwicklung | – | – |
| 8 | Runde 2 nach §8 | Moderation | vor R1-GATE | – |
| 9 | Abnahmebericht `vorlage-abnahmebericht.md`, Freigabe | Inhaberin | Eingang R1-GATE | wartet auf U-10 |

## 10. Wartet auf …

| Punkt | Wartet auf | Zuständig |
|---|---|---|
| Umfangsentscheidung Runde 1 (U-1) | Beschluss | Inhaberin |
| Budget (U-2, AG-06) | Betrag | Inhaberin |
| Einwilligungstext, Speicherort, Datenschutzbewertung Art. 9 (U-3) | Freigabe | Inhaberin mit Datenschutzberatung |
| Moderation, externe A11Y-Moderation, A11Y-Fachperson (U-4) | Benennung/Beauftragung | Inhaberin |
| Staging mit https, de_DE, nginx, Fremdhost-Video (U-5) | Bereitstellung | Inhaberin bzw. Betrieb |
| SUS-Fassung (U-6), Referenzkurse/Typmix/B-03 (U-7, AG-12) | Entscheidung | Inhaberin |
| Pilot, Benchmark, Schwellen einfrieren (U-8) | Durchführung, Bestätigung | Moderation, Inhaberin |
| Rekrutierung 25 Personen + Expertendurchlauf (U-9) | Beauftragung | Inhaberin / Dienstleister |
| Fehlende Oberflächen für ★-Aufgaben (Selbstanmeldung B-02, Fragen-/Prüfungs-/Bewertungs-Editor, Import-UI, Smarttrainer/Fehlertraining/fällig, Abgabe, Besitzer-Oberfläche, Vorschau, Alt-Text-Regel B-05, Reihenfolge/Lückentext im Prüfungsraum, Klick-zum-Laden-Knopf in der Lektion) | Umsetzung | Entwicklung (Folgeaufgaben zu R1-05/R1-16/R1-19/R1-26/R1-35/R1-46) |
| Runden 1/2, Abnahmebericht (U-10) | Durchführung, Freigabe | Moderation, Inhaberin |
