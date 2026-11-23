# R1-29 · Moderationsleitfaden für die Nutzertests

**Grundlage:** R0-26 §2 (Testdesign), §4 (Ablauf, Protokoll, Befunde), Beschlüsse E-161 … E-171. Dieser Leitfaden gilt für alle moderierten Sitzungen; die Aufgaben stehen in den Skripten je Rolle (`skript-autor.md`, `skript-lernende.md`, `skript-gruppenverwalter.md`, `skript-admin.md`), die Hilfstechnik-Abläufe in `szenarien-hilfstechnik.md`, die Störfälle in `szenarien-fehlerbehebung.md`.

**Rollen in jeder Sitzung:** 1 Moderation (nicht an der Entwicklung beteiligt, E-164; für Barrierefreiheitssitzungen extern), 1 Protokoll. Die Moderation führt, das Protokoll schreibt in `vorlage-protokoll.md` bzw. `protokoll-aufgaben.csv` und bedient die Zeiterfassung (`zeiterfassung.csv`). Störfälle löst die Moderation über die Befehle in `testumgebung.md` §4 aus (zweites Fenster, nicht im geteilten Bildschirm).

## 1. Ablauf einer Sitzung

| Schritt | Inhalt | Autor (120 min) | Lernende (75 min) | Hilfstechnik (90 min + Pausen) | GV (30 min) | Admin (60 min) |
|---|---|---|---|---|---|---|
| 0 | Vorbereitung nach `preflight-checkliste.md` (vor dem Termin) | – | – | – | – | – |
| 1 | Begrüßung, Einwilligung (§2), „Wir testen das System, nicht Sie“ | 5 | 5 | 10 | 3 | 5 |
| 2 | Vorab-Fragebogen (§5), pseudonym | 5 | 5 | 5 | 2 | 5 |
| 3 | Aufwärmaufgabe ohne Wertung (Autor: eine WordPress-Seite öffnen; Lernende: Startseite beschreiben; Admin: Dashboard beschreiben) | 3 | 2 | 5 | 1 | 2 |
| 4 | Aufgaben in fester Reihenfolge, je Karte; nach jeder Aufgabe SEQ (§4.1) | ≈ 90 | ≈ 50 | ≈ 55 | ≈ 18 | ≈ 38 |
| 5 | SUS (§4.2), Nachgespräch (§4.3) | 10 | 8 | 10 | 5 | 8 |
| 6 | Dank, Aufwandsentschädigung (auch bei Abbruch), Hinweis auf Widerruf | 2 | 2 | 2 | 1 | 2 |
| 7 | Nacharbeit: Systemzustand sichern (`check-rk`, Mail-Sink, Screenshots ohne Personendaten), Site zurücksetzen | 10 | 5 | 5 | 5 | 5 |

Bei Hilfstechnik: Pause nach spätestens 30 min anbieten; die Pause zählt nicht zur Aufgabenzeit. Die Pilotsitzung (R0-26 §4.2 Schritt 3) prüft, ob 120 min für die Autorensitzung reichen (OP-26-1).

**Rotation (R0-26 §2.3 Nr. 4):** Die Reihenfolge von AU-T04/T05 gegenüber AU-T03 wechselt je Person. Ungerade Pseudonym-Nummer: T03 vor T04/T05; gerade: T04/T05 vor T03. Im Protokollkopf vermerken.

## 2. Einwilligung und Datenschutz (R0-26 §4.1, E-167, R0-17)

Die Einwilligung liegt **vor** der Sitzung schriftlich oder barrierefrei elektronisch vor. Vorlage (anpassen, vom Datenschutz des Auftraggebers freigeben lassen):

> **Einwilligung zur Teilnahme an einem Nutzertest**
>
> Wir testen die Software „Course Captain“ – nicht Sie. Zweck: Wir möchten herausfinden, wo die Software schwer zu bedienen ist.
>
> 1. Während der Sitzung zeichnen wir Bildschirm und Ton auf. Ihr Gesicht nehmen wir nur auf, wenn Sie das hier ankreuzen: ☐ Gesicht darf aufgezeichnet werden. ☐ Ich bin mit der Aufzeichnung von Bildschirm und Ton einverstanden.
> 2. Ihre Angaben speichern wir unter einem Pseudonym (`P-<Rolle>-<nn>`). Ihr Name steht nirgends in den Ergebnissen.
> 3. Angaben zu Hilfstechnik (z. B. „nutzt einen Screenreader“) sind freiwillig. Wir speichern nur die Art der Hilfstechnik, keine weiteren Gesundheitsangaben.
> 4. Speicherort: <Ort beim Auftraggeber>. Die Aufzeichnungen löschen wir **6 Monate nach der Freigabe des Release R1** (R1-GATE). Protokolle und Befundlisten bleiben pseudonym erhalten.
> 5. Sie können die Einwilligung jederzeit ohne Nachteil widerrufen – auch während der Sitzung. Die Aufwandsentschädigung erhalten Sie auch bei Abbruch.
> 6. Im System verwenden Sie nur Testkonten. Geben Sie bitte keine echten persönlichen Daten ein.
>
> Ort, Datum, Unterschrift bzw. elektronische Bestätigung

Regeln für das Team:

- Keine Klarnamen in Protokollen, Dateinamen, Befunden oder Commits; nur das Pseudonym. Die Zuordnungsliste Pseudonym ↔ Person führt nur die Rekrutierung, getrennt vom Repo.
- Screenshots nur ohne Personendaten (Testkonten `cc_fx_ux_…`, Domain `example.test`). Bei Remote-Sitzungen Benachrichtigungen der Person vorher ausschalten lassen.
- Aufzeichnung nur über die Konferenz- bzw. Testsoftware (R0-26 §3.3), keine Analyse- oder Fremd-Skripte auf der Test-Site.
- Die Angabe zur Hilfstechnik ist eine Gesundheitsangabe nach Art. 9 DSGVO; Bewertung durch R0-17 vor Runde 1 (R0-26 §6).

## 3. Laut denken, Hinweise, Abbruch

**Einleitung (wörtlich vorlesen):** „Bitte sagen Sie laut, was Sie denken: was Sie suchen, was Sie erwarten, was Sie überrascht. Es gibt kein Richtig oder Falsch. Wenn Sie nicht weiterkommen, ist das ein Ergebnis für uns, kein Fehler von Ihnen. Ich beantworte während der Aufgaben keine Fragen zur Bedienung, damit wir sehen, wo die Software selbst hilft. Wenn Sie aufgeben möchten, sagen Sie einfach ‚weiter‘.“

**Screenreader-Nutzende:** Laut denken ist freiwillig (konkurriert mit der Sprachausgabe). Stattdessen kurzes Gespräch nach jeder Aufgabe (retrospektiv). Zeiten getrennt berichten (R0-26 §2.2, §2.4).

**Neutral nachfragen** (nur wenn die Person verstummt): „Was geht Ihnen gerade durch den Kopf?“ · „Was erwarten Sie, wenn Sie das anklicken?“ Keine Bestätigungen („genau“, „richtig“), kein Zeigen.

**Hinweisstufen (verbindlich, R0-26 §2.3):**

| Stufe | Wortlaut | Wertung |
|---|---|---|
| H0 | Aufgabe wiederholen („Die Aufgabe war: …“) | zählt weiter als Erfolg |
| H1 | Bereich nennen („Schauen Sie in der Kursbearbeitung.“) | „Erfolg mit Hilfe“ (EH), **kein** Erfolg für die Schwellen |
| H2 | Zeigen | Aufgabe **nicht erfolgreich** (F); Moderation fährt fort, damit Folgeaufgaben möglich bleiben |

Hilfstechnik: H2 ist nicht erlaubt (S-A11Y), H1 nur zur Aufgabenklärung.

**Abbruch einer Aufgabe:** wenn die Person aufgibt oder bei der dreifachen Zielzeit (höchstens 20 min je Teilaufgabe); bei Hilfstechnik bei der vierfachen Zielzeit. Die Grenzen stehen je Aufgabe in den Skripten („Abbruch nach“). Danach: „Danke, das reicht uns für diese Aufgabe.“ Falls eine Folgeaufgabe den Zustand braucht, stellt die Moderation ihn ohne Kommentar her (z. B. `scenario einschreiben`).

**Störfälle** (z. B. Netz trennen, Sitzung beenden, Tab schließen) kündigt die Moderation nicht an; sie folgen dem Wortlaut in `szenarien-fehlerbehebung.md`.

## 4. Fragebögen

### 4.1 SEQ nach jeder Aufgabe

„Wie schwierig oder einfach war diese Aufgabe?“ 1 = sehr schwierig … 7 = sehr einfach. Bei Hilfstechnik mündlich oder als zugängliches Formular. Wert ins Protokoll (Spalte SEQ).

### 4.2 SUS am Ende (10 Aussagen, 1 = stimme überhaupt nicht zu … 5 = stimme voll zu)

R0-26 verlangt die **deutsche Fassung unverändert**. Vor Runde 1 die vom Auftraggeber gewählte veröffentlichte deutsche Übersetzung einsetzen und hier nicht verändern. Gängige Fassung zur Orientierung:

1. Ich denke, dass ich das System gerne häufig benutzen würde.
2. Ich fand das System unnötig komplex.
3. Ich fand das System einfach zu benutzen.
4. Ich glaube, ich würde die Hilfe einer technisch versierten Person benötigen, um das System benutzen zu können.
5. Ich fand, die verschiedenen Funktionen in diesem System waren gut integriert.
6. Ich denke, das System enthielt zu viele Inkonsistenzen.
7. Ich kann mir vorstellen, dass die meisten Menschen den Umgang mit diesem System sehr schnell lernen.
8. Ich fand das System sehr umständlich zu nutzen.
9. Ich fühlte mich bei der Benutzung des Systems sehr sicher.
10. Ich musste eine Menge lernen, bevor ich anfangen konnte, das System zu verwenden.

**Auswertung:** ungerade Aussagen (Antwort − 1), gerade Aussagen (5 − Antwort), Summe × 2,5 = SUS 0…100 je Person. `sus-auswertung.csv` rechnet das in der Tabellenkalkulation; Mittelwert je Rolle → S-04 (≥ 70, höchstens 1 Person < 50).

### 4.3 Nachgespräch (3 feste Fragen + Verständnisfragen)

1. Was war am schwierigsten?
2. Was hat gefehlt?
3. Würden Sie es so einsetzen?

Zusätzlich: Autoren – AU-T10 „Was passiert mit Personen, die gerade eine Prüfung schreiben, wenn Sie die Frage ändern?“ (richtig: laufende Versuche bewerten weiter gegen die eingefrorene Fassung; Änderung gilt für neue Versuche) und AU-T13 „Was sieht die Person, deren Abgabe Sie zurückgegeben haben?“ (richtig: Status „zurückgegeben“ mit Kommentar, sie kann eine neue Fassung einreichen). Gruppenverwaltung – GV-T06 „Was passiert mit dem Lernfortschritt der entfernten Person?“ (richtig: bleibt erhalten). Lernende – LE-T08 „Haben Sie bestanden, und welcher Teil war schwach?“

## 5. Vorab-Fragebogen (5 min, pseudonym)

| Nr. | Frage | Antwortformat |
|---|---|---|
| V1 | Rolle in diesem Test | Autor/in · Lernende/r · Gruppenverwaltung · Admin |
| V2 | Wie oft nutzen Sie WordPress? | nie · selten · monatlich · wöchentlich · täglich |
| V3 | Haben Sie schon Kurse oder Schulungen erstellt? | nein · ja, ohne Software · ja, mit Lernsoftware |
| V4 | Haben Sie schon online gelernt (E-Learning)? | nie · 1–2 Kurse · regelmäßig |
| V5 | Gerät, Betriebssystem, Browser mit Version | Freitext (Moderation hilft) |
| V6 | Nutzen Sie Hilfstechnik? (freiwillig) | keine · Screenreader (welcher, Version) · Vergrößerung · nur Tastatur · Spracheingabe · Sonstiges |
| V7 | Wie sicher fühlen Sie sich im Umgang mit Computern? | 1 (sehr unsicher) … 5 (sehr sicher) |
| V8 | Altersgruppe (für die Profilabdeckung §2.1) | 18–34 · 35–54 · 55+ |

Keine weiteren Gesundheitsangaben. Ergebnis in den Protokollkopf („Profil“).

## 6. Messen und Werten (R0-26 §2.4)

- **Zeit** von „Karte gelesen“ bis Erfolgskriterium erfüllt oder Abbruch; Lesezeit von Kursinhalten (LE-T02/T03) abziehen und separat notieren.
- **Ergebnis:** `E` Erfolg ohne Hilfe · `EH` Erfolg mit H1 · `F` nicht erfolgreich (inkl. H2) · `Abbr.` Abbruch.
- **Bedienfehler** = falscher Pfad mit Selbstkorrektur; **kritischer Fehler** = Datenverlust, falscher veröffentlichter Zustand, unbemerkt falsche Annahme (z. B. „Prüfung abgegeben“, obwohl nicht abgegeben).
- **Erfolg objektiv prüfen:** nicht nach Eindruck, sondern nach dem Systemzustand („Systemprüfung“ je Aufgabe im Skript: `check-rk`, Mail-Sink, Prüfungs- oder Abgabestatus).
- Jede Auffälligkeit sofort mit Zeitmarke notieren; Befunde nach der Sitzung in `befunde.csv` übertragen (Schweregrad erst in der Triage, R0-26 §4.4).

## 7. Nach der Runde

Auswertung innerhalb von 2 Werktagen (R0-26 §4.2 Schritt 6): Protokolle zusammenführen, Schwellen S-01 … S-EXP berechnen (`schwellen.csv`), Befund-Triage mit Produkt, Entwicklung und A11Y-Fachperson (bei Uneinigkeit gilt die höhere Stufe), Abnahmebericht nach `vorlage-abnahmebericht.md`.
