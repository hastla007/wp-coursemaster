# R1-29 · Szenarien Tastatur, Screenreader, Vergrößerung und mobile Prüfung

**Grundlage:** R0-26 §3 (Matrix AT-01 … AT-10, BR-01 … BR-09), §5.4 (UX-070 … UX-081), R0-15 (A11Y-001 … 112, §2.3 Zeitausgleich), R1-19-Nachweis (automatisiert erledigt; offen: NVDA/VoiceOver mit echter Hilfstechnik, A11Y-102 … 104).

**Regeln (S-A11Y):** Jede Person mit Hilfstechnik löst jede ★-Aufgabe ihres Flows **ohne sehende Hilfe** (H2 nicht erlaubt, H1 nur zur Aufgabenklärung). Abbruch bei 4 × Zielzeit. Zeiten werden berichtet, gehen aber nicht in S-06 … S-10 ein. SEQ-Median der Hilfstechnik-Gruppe ≥ 4,5. Jede Verletzung = Befund **S1**. Personen nutzen bevorzugt ihr **eigenes Gerät** mit eigener Konfiguration; die Moderation notiert Versionen und Einstellungen im Protokollkopf.

**Bereitschaft:** Heute prüfbar sind Kursraum, „Mein Lernen“, Prüfungsraum, Nachweise, Setup und Einstellungen; Lektionsansicht, Smarttrainer, Aufgaben und Gruppen-Besitzeroberfläche fehlen (`bereitschaft.md`). Szenarien unten markieren das mit „(blockiert)“.

## 1. Sitzungsablauf mit Hilfstechnik (90 min + Pausen)

1. Technik-Check (10 min): Ton der Sprachausgabe über die Konferenzsoftware teilen (remote) oder Mitschnitt mit externem Mikrofon (vor Ort); Sprechgeschwindigkeit nicht ändern; Version von Hilfstechnik, Browser und Betriebssystem notieren.
2. Aufgabenkarten als zugängliche Datei (HTML oder getaggtes PDF, Überschrift je Aufgabe) vorab zusenden; nicht vorlesen lassen, die Person liest selbst.
3. Laut denken freiwillig; nach jeder Aufgabe retrospektiv: „Was war gut, was hat gestört?“
4. Pause nach spätestens 30 min.

## 2. Szenarien je Kombination

### AT-06 · Nur Tastatur (Chrome n oder Edge n, Windows 11) – Stufe Z (UX-073, UX-074)

| Schritt | Aufgabe | Prüfen (Beobachtung) | A11Y |
|---|---|---|---|
| T-1 | Anmelden, „Mein Lernen“ öffnen | Sprunglink „Zum Inhalt“ als erstes Fokusziel und sichtbar | A11Y-001/006 |
| T-2 | Kursraum RK-3 öffnen, Sperrgrund von Modul 2 nennen (UX-031) | Fokusreihenfolge = Lesereihenfolge; Fokus nie verdeckt | A11Y-002/005 |
| T-3 | LE-T06 Teil 1+2 im Prüfungsraum (RK-2) | Radiogruppen mit Pfeiltasten, Checkboxen mit Leertaste, Zuordnung per `select`; „Markieren“ erreichbar; Bestätigungsseite ohne Maus; Fokus nach Abgabe auf Ergebnis-Überschrift | A11Y-003/007/074 |
| T-4 | LE-T08 Nachweis öffnen, Prüf-Link kopieren | Link per Tastatur markier- und kopierbar | A11Y-001 |
| T-5 | (blockiert) LE-T01/T02/T04 | Anmeldung, Lektionsabschluss, Zuordnung/Reihenfolge per Tastatur (UX-038) | A11Y-008 |
| T-6 | Autor (SOLL-Person, UX-074): AU-T02 Verschieben per Tastatur | Positionsfelder bedienbar, Ergebnis ohne Maus | A11Y-008 |
| T-7 | GV-T02/T03 (blockiert) | – | – |

Beobachtung „Fokus immer sichtbar, nie verdeckt“ je Seite protokollieren (UX-073).

### AT-01 · NVDA (aktuell) + Firefox ESR, Windows 11 – Stufe Z (UX-070, A11Y-102)

Vorher: NVDA-Version, Sprachausgabe, Browse-/Fokusmodus-Einstellungen notieren; Firefox ESR-Version.

| Schritt | Aufgabe | Prüfen | A11Y |
|---|---|---|---|
| N-1 | „Mein Lernen“: Überschriften-Liste (NVDA+F7) aufrufen | eine `h1`, Kurse als `h2`; Landmarken Kopf/Haupt/Navigation benannt | A11Y-020 |
| N-2 | Kursraum: Status je Lektion vorlesen lassen | „(not started)“/„Gesperrt: …“ wird mit dem Titel gelesen; Drip-Datum verständlich | A11Y-027 |
| N-3 | Prüfung starten (LE-T06), Frage 1 beantworten | Fieldset-Legende „Frage n von m“ + Hinweis + Fragetext wird beim Eintritt gelesen; Optionen als Radiogruppe | A11Y-022 |
| N-4 | Restzeit abfragen (UX-025) | Knopf „Verbleibende Zeit ansagen“; Ansage über die Statusregion; Timer selbst nicht dauernd gesprochen (`aria-live=off`) | A11Y-071 |
| N-5 | Bei 10/5/1 min (mit `scenario ablauf` die 1-min-Ansage herbeiführen) | Ansage kommt genau einmal, unterbricht nicht | A11Y-071/073 |
| N-6 | Zwei Fragen markieren, abgeben | Bestätigung nennt offene und markierte Fragen; Fehlermeldung ohne Häkchen als `alert` | A11Y-074 |
| N-7 | Tab schließen, fortsetzen (UX-026) | Rückweg mit Screenreader in ≤ 4 min; Antworten werden als „ausgewählt“ angesagt | A11Y-025/075 |
| N-8 | Ergebnis lesen (LE-T08) | „bestanden/nicht bestanden“ als Überschrift, Teilergebnisse verständlich | A11Y-030 |
| N-9 | Zeitausgleich (UX-075, Konto `cc_fx_ux_ausgleich`) | Restzeit inkl. 150 % richtig verstanden (Anzeige „inkl. Zeitausgleich“ fehlt noch, A11Y-070) | A11Y-070/071 |
| N-10 | (blockiert) Smarttrainer-Feedback „richtig/falsch“ (UX-023), Datei-Upload (UX-036/UX-081) | – | A11Y-024 |

AT-02 (NVDA + Chrome n) für Autor/Admin: AD-T01 … AD-T06 vollständig (Setup-Schrittliste `aria-current="step"`, Fehlerzusammenfassung mit Feldlinks, Risiko-Dialog mit Anfangsfokus „Abbrechen“), AU-T09 Validierungspanel (`role=status`, A11Y-031).

### AT-03 · VoiceOver macOS + Safari n – Stufe E (UX-077, UX-067)

Expertendurchlauf Flow 1 (soweit bereit) und Flow 4: Rotor-Überschriften und -Formularelemente im Setup und in den Einstellungs-Tabs, ungespeicherte Änderungen (A11Y-009), Kurs-Editor-Metaboxen (Labels, Fehlerhinweise; A11Y-080 offen). Lernende: N-1 … N-8 analog mit VO-Befehlen.

### AT-04 · VoiceOver iOS + Safari, iPhone iOS n und n−1 – Stufe Z (UX-071, UX-075)

Je ein Durchlauf auf iOS n und n−1 (BR-07; kleinstes unterstütztes Modell einbeziehen).

| Schritt | Aufgabe | Prüfen |
|---|---|---|
| V-1 | Anmelden (Passwort-Eingabe mit VoiceOver), „Mein Lernen“ | Wischen in sinnvoller Reihenfolge, Ziele ≥ 44 px |
| V-2 | Prüfung (RK-2) starten, 10 Fragen mit Wischen/Doppeltippen beantworten, 2 markieren | Rotor „Formularelemente“, Legende je Frage |
| V-3 | **Echter Netzabbruch:** Kontrollzentrum → Flugmodus, App wechseln, 30 s, zurück, Flugmodus aus | Antworten und Markierungen erhalten; Hinweis bei fehlender Verbindung verständlich (UX-026) |
| V-4 | Restzeit abfragen, abgeben | Ansage, Bestätigungsseite |
| V-5 | Querformat während der Prüfung (UX-035) | kein Zustandsverlust |
| V-6 | Zeitausgleich (`cc_fx_ux_ausgleich`) | Restzeit verstanden (UX-075) |
| V-7 | LE-T10 mit `cc_fx_ux_teilweise` | „Continue“ in ≤ 1 min erreicht (Lektion selbst blockiert, B-01) |

### AT-07 · Vergrößerung 200 % Text / 400 % Zoom, Bildschirmlupe – Stufe Z (UX-072)

Firefox n, Windows-Lupe oder macOS-Zoom. Prüfen: kein horizontales Scrollen bei 400 % (A11Y-051), Timer und Abgabe bleiben ohne Suchen erreichbar, Fokus folgt der Lupe, Textabstand (A11Y-052/053). Aufgaben: LE-T06, LE-T08, UX-031.

### AT-08 · Windows-Kontrastdesign (`forced-colors`), Edge n – Stufe E

Ausgewählte Antwort, markierte Frage, Fokusrahmen, Timer und Statusmeldungen bleiben erkennbar (A11Y-043, teilweise automatisiert); Autor: Metaboxen A-04/A-07.

### AT-09 · Bewegung reduzieren, große Systemschrift, iOS – Stufe E

Keine Animationen trotz Einstellung (A11Y-060); bei größter Systemschrift keine abgeschnittenen Knöpfe im Prüfungsraum.

### AT-05 · TalkBack + Chrome, Android – Stufe B (best effort, Befunde höchstens S2)

Wie AT-04 V-1 … V-4 auf BR-08 (Mittelklasse 360 × 800).

### AT-10 · JAWS – nicht zugesichert (E-126)

Nur informativ, wenn eine Person JAWS mitbringt. Befunde ≤ S3, außer NVDA/VoiceOver zeigen denselben Fehler.

## 3. Mobile Prüfungsläufe ohne Hilfstechnik (R0-26 §3.2, UX-026 Pflicht)

| Lauf | Gerät | Ablauf | Erfolg |
|---|---|---|---|
| MP-1 | BR-07 iPhone, iOS n, Safari | LE-T06 mit echtem Netzabbruch (Flugmodus + App-Wechsel), danach Hoch-/Querformat-Wechsel (UX-035) | 0 verlorene Antworten/Markierungen, Fortsetzen ≤ 1 min (S-08) |
| MP-2 | BR-07 iPhone, iOS n−1 (älteres/kleines Modell) | wie MP-1 | wie MP-1 |
| MP-3 | BR-08 Android Mittelklasse, Chrome n (360 × 800, 3–4 GB RAM) | wie MP-1; zusätzlich Chrome im Hintergrund > 5 min (Tab-Entladung) | wie MP-1 |
| MP-4 | BR-09 iPad, Quer- und Hochformat | LE-T06, LE-T08 | kein Zustandsverlust, keine abgeschnittenen Bedienelemente |
| MP-5 | BR-07/BR-08 | LE-T10 Fortsetzen am nächsten Tag (`cc_fx_ux_teilweise`) | Median ≤ 1 min (UX-030) |

Für jeden Lauf: Uhrzeit vor/nach dem Abbruch, Anzahl Antworten vor/nach, Markierungen vor/nach im Protokoll.

## 4. Expertendurchläufe (keine Nutzertests; UX-076, UX-077, UX-078 … UX-081)

Eine A11Y-Fachperson (nicht an der Entwicklung beteiligt), Checkliste statt laut denken. Je Kombination ein Protokoll mit Versionen.

| ID | Umfang | Kombinationen | Stand |
|---|---|---|---|
| UX-076 | Flow 2: Mein Lernen, Kursraum, Prüfungsraum (inkl. Ablauf, Zeitausgleich), Ergebnis, Nachweisliste, Nachweis, öffentliche Verifikation, Benachrichtigungen | AT-01 … AT-09 | teilweise (Lektion, Smarttrainer, Aufgabe, Export blockiert) |
| UX-077 | Flow 1 (Kurs-, Modul-, Lektions-Editor, Validierungspanel) und Flow 4 (Setup, Einstellungen, Systemstatus, Gruppen-Admin, Nachweis-Admin) | AT-02, AT-03 | bereit für die vorhandenen Seiten |
| UX-078 | Mails (Zugang, Einladung, Nachweis) im Mailprogramm mit Screenreader | AT-01, AT-04 | Mail-Sink nur für Inhalt; echte Zustellung an Testpostfach nötig |
| UX-079 | Druckansicht Nachweis | BR-01, BR-05 | bereit |
| UX-080 | externe Medien „Klick zum Laden“ (Einstellung umstellen) | AT-01, AT-04, AT-06 | blockiert (kein Player) |
| UX-081 | Datei-Upload und Bewertungsansicht | AT-01 … AT-04 | blockiert |

**Checkliste je Seite** (aus R0-15 §2.4 verdichtet): Seitentitel eindeutig · eine `h1`, Überschriftenhierarchie · Landmarken benannt · Sprunglink · alle Bedienelemente mit Namen, Rolle, Zustand · Fokus sichtbar und nie verdeckt · Fehler/Status über eine Live-Region, nicht doppelt · Formulare mit Label und Hilfetext (`aria-describedby`) · Kontrast Text ≥ 4,5:1, Nicht-Text ≥ 3:1 (A11Y-041 manuell) · 400 % ohne horizontales Scrollen · Sprache `lang`/`dir` korrekt (de_DE, Inhaltssprache abweichend → eigenes `lang`) · Zeitgrenzen ansagbar und bei Zeitausgleich richtig.

Ergebnis je Befund: Eintrag in `befunde.csv` mit WCAG-Kriterium und A11Y-ID; S-EXP verlangt 0 offene S1.
