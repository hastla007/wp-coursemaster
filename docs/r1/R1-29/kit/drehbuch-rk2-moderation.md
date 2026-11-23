# Drehbuch RK-2 · Grundlagen der Tabellenkalkulation – Prüfungstraining (Moderationsfassung – nicht an Teilnehmende geben)

> Synthetischer Referenzkurs aus dem Paket `cc-fx-rk-v1` (CC0, R0-26 §1.1). Alle Firmen, Personen und Adressen sind erfunden. Erklärungstexte zu Lösungen entfallen, weil die freie Version sie nicht ausliefert (E-20).

## 1. Kurs (AU-T01)

- **Titel:** Grundlagen der Tabellenkalkulation – Prüfungstraining
- **Beschreibung:** Prüfungstraining zu Zellbezügen, Formeln, Sortieren und Diagrammen in einem beliebigen Tabellenprogramm.
- **Lernziele:**
  - Relative und absolute Zellbezüge sicher verwenden
  - Formeln und einfache Funktionen schreiben
  - Daten sortieren, filtern und mit Diagrammen auswerten

## 2. Module und Lektionen (AU-T02, AU-T03)

> Eingebauter Fehler AU-T02: In Modul 1 steht die letzte Lektion in diesem Drehbuch an erster Stelle. Die Person baut zuerst nach Drehbuch auf; danach reicht die Moderation die Karte AU-T02b: „Die Lektion an Position 1 von Modul 1 gehört ans Ende des Moduls. Verschiebe sie.“ Verschieben per Maus **oder** Tastatur (A11Y-008). Soll = Reihenfolge im Soll-Zustand.

### Modul 1: Zellen und Bezüge

1. **Bezüge üben** – Text mit Inline-Quiz (3 Fragen, siehe Abschnitt 3) **[eingebauter Fehler AU-T02, Soll: Position 3]**

   Prüfen Sie Ihr Wissen zu Zellen und Bezügen mit drei kurzen Fragen.

2. **Zellen, Zeilen, Spalten** – Text

   Eine Tabelle besteht aus Spalten, die mit Buchstaben bezeichnet sind, und Zeilen, die nummeriert sind. Die Zelladresse B3 meint Spalte B, Zeile 3. Der Bereich A1:C4 umfasst 3 Spalten mal 4 Zeilen, also 12 Zellen.

   Formeln beginnen immer mit einem Gleichheitszeichen. Text steht standardmäßig linksbündig, Zahlen stehen rechtsbündig.

3. **Relative und absolute Bezüge** – Video (intern) mit Untertiteln: Datei `rk2-video.mp4`, Untertitel `rk2-video.vtt`

   Ein relativer Bezug wie A1 passt sich beim Kopieren einer Formel an. Ein absoluter Bezug wie $A$1 bleibt beim Kopieren gleich. Bei gemischten Bezügen ist nur ein Teil fest: Bei $A1 bleibt die Spalte fest, bei A$1 die Zeile.

   In vielen Programmen wechselt die Taste F4 zwischen den Bezugsarten. Die Reihenfolge beim Erstellen einer Formel ist: Zelle auswählen, Gleichheitszeichen eingeben, Formel schreiben, mit der Eingabetaste bestätigen.

### Modul 2: Formeln und Funktionen

1. **Grundrechenarten** – Text

   Die Rechenzeichen sind + (plus), - (minus), * (mal) und / (geteilt). Es gilt Punkt- vor Strichrechnung: =2+3*4 ergibt 14. Mit Klammern ändert sich die Reihenfolge: =(2+3)*4 ergibt 20.

   Prozent rechnet man zum Beispiel mit =B2*19%. Ist B2 gleich 200, ergibt das 38.

2. **SUMME, MITTELWERT, MIN und MAX** – Video (intern) mit Untertiteln: Datei `rk2-video.mp4`, Untertitel `rk2-video.vtt`

   =SUMME(A1:A5) addiert alle Zahlen im Bereich. =MITTELWERT(A1:A5) berechnet den Durchschnitt; leere Zellen werden dabei nicht mitgezählt. =MIN und =MAX liefern den kleinsten und den größten Wert. =ANZAHL zählt die Zellen, die Zahlen enthalten.

   Beispiel: Für die Werte 4, 8 und 12 ist die Summe 24 und der Mittelwert 8.

3. **Die WENN-Funktion** – Text

   Die WENN-Funktion prüft eine Bedingung: =WENN(Bedingung; Dann-Wert; Sonst-Wert). In der deutschen Fassung trennt ein Semikolon die Teile.

   Beispiel: =WENN(B2>=50;"bestanden";"nicht bestanden") gibt „bestanden“ aus, wenn B2 mindestens 50 ist. Das Zeichen >= bedeutet „größer oder gleich“.

### Modul 3: Daten ordnen

1. **Sortieren und Filtern** – Text

   Aufsteigend sortieren heißt: von A nach Z bzw. von klein nach groß. Markieren Sie vor dem Sortieren den ganzen Bereich mit der Kopfzeile, sonst verrutschen die Zeilen. Für eine zweite Ordnung gibt es weitere Sortierebenen.

   Ein Filter blendet Zeilen nur aus; er löscht keine Daten. Die Reihenfolge beim Filtern: Kopfzeile markieren, Filter einschalten, Wert auswählen, Ergebnis prüfen.

2. **Übungsdatei** – Datei (PDF) aus dem Medienordner `rk2-l08.pdf`

   Die Übungsdatei (PDF) enthält eine Tabelle mit den Umsätzen eines erfundenen Geschäfts für 12 Monate. Der Umsatz im Januar beträgt 1.200 Euro, im Februar 1.500 Euro und im März 1.800 Euro. Der höchste Monatsumsatz liegt im Dezember bei 3.000 Euro.

### Modul 4: Auswerten

1. **Diagramme wählen** – Text

   Säulendiagramme eignen sich für Vergleiche zwischen Kategorien, Liniendiagramme für Entwicklungen über die Zeit und Kreisdiagramme für Anteile an einem Ganzen – am besten mit höchstens sechs Teilen.

   Jedes Diagramm braucht einen Titel und beschriftete Achsen. Für Menschen, die das Diagramm nicht sehen, gehört eine kurze Textbeschreibung dazu.

2. **Aufgabe: Umsatzauswertung** – Aufgabe (siehe Abschnitt 5)

   In dieser Aufgabe werten Sie die Übungsdatei selbst aus.

## 3. Fragen

AU-T04 (drei Fragen) und AU-T05 (sieben Fragen) legen Sie selbst an. Die übrigen 40 Fragen kommen aus der Datei `rk2-q40.zip` (AU-T06).

### AU-T04 – drei Fragen

1. **Eine richtige Antwort:** Bei welchem Bezug bleibt nur die Spalte fest?
   - [x] $A1
   - [ ] A1
   - [ ] A$1
   - [ ] $A$1

2. **Mehrere richtige Antworten:** Welche Aussagen zur Ausrichtung stimmen standardmäßig? (mehrere richtig)
   - [x] Text steht linksbündig.
   - [x] Zahlen stehen rechtsbündig.
   - [ ] Zahlen stehen linksbündig.
   - [ ] Text steht zentriert.

3. **Wahr/Falsch:** Ein relativer Bezug passt sich beim Kopieren einer Formel an.
   - Richtig ist: Wahr


### AU-T05 – sieben Fragen

4. **Eine richtige Antwort:** Das Bild zeigt einen Tabellenausschnitt mit markierter Zelle. Welche Adresse hat die markierte Zelle?
   - Bild: `rk2-zelle.png` – Alt-Text: „Tabellenausschnitt mit den Spalten A bis D und den Zeilen 1 bis 4; die Zelle in Spalte C, Zeile 2 ist markiert.“
   - [x] C2
   - [ ] B3
   - [ ] 2C
   - [ ] A1

5. **Zahl mit erlaubter Abweichung:** Wie viele Zellen umfasst der Bereich A1:C4?
   - Sollwert 12 Zellen, erlaubte Abweichung 0

6. **Mehrere richtige Antworten:** Welche Bezüge enthalten mindestens einen festen Teil? (mehrere richtig)
   - [x] $A$1
   - [x] $A1
   - [x] A$1
   - [ ] A1
   - [ ] B7

7. **Mehrere richtige Antworten:** Welche Aussagen zur Taste F4 stimmen laut Lektion? (mehrere richtig)
   - [x] Sie wechselt zwischen den Bezugsarten.
   - [x] Sie funktioniert in vielen Programmen.
   - [ ] Sie löscht die Formel.
   - [ ] Sie speichert die Datei.

8. **Eine richtige Antwort:** Was ergibt =2+3*4?
   - [x] 14
   - [ ] 20
   - [ ] 24
   - [ ] 9

9. **Eine richtige Antwort:** Was ergibt =(2+3)*4?
   - [x] 20
   - [ ] 14
   - [ ] 9
   - [ ] 24

10. **Eine richtige Antwort:** Welche Funktion berechnet den Durchschnitt?
   - [x] MITTELWERT
   - [ ] SUMME
   - [ ] ANZAHL
   - [ ] MAX


## 4. Prüfung (AU-T07)

- **Bezeichnung:** Prüfung 2026
- **Fragen:** 30 zufällig aus den Modulen: Modul 1: 8, Modul 2: 8, Modul 3: 7, Modul 4: 13 **[eingebauter Fehler AU-T09, Soll 7]**
- **Zeit:** 45 Minuten (feste Frist)
- **Bestehen:** ab 75 % gesamt und je Modul mindestens 60 %
- **Versuche:** 3
- **Ergebnis:** nach der Abgabe
- **Nachweis:** Ausstellung bei bestandener Prüfung (nachweisfähig)

## 5. Aufgabe (AU-T12)

- **Aufgabenstellung:** Erstellen Sie aus der Übungsdatei ein Diagramm der Monatsumsätze, berechnen Sie Summe und Mittelwert und laden Sie das Ergebnis als PDF hoch.
- **Abgabeart:** Datei-Upload (erlaubt: pdf)
- **Punkte:** höchstens 20, bestanden ab 12
- **Abschluss:** wenn bestanden
- **Bewertungshinweis (nur für Bewertende):** Diagrammart passend 5, Titel und Achsen 5, Summe richtig 5, Mittelwert richtig 5.

## 6. Zugang (AU-T08, AU-T11)

- Manuelle Freischaltung durch das Kursteam (AU-T11: Testnutzer nur für Modul 1 und 2 freischalten, E-27)

## 7. Eingebaute Fehler und Soll (nur Moderation)

- AU-T09 Alt-Text: Eine Bildfrage im Paket `rk2-q40.zip` hat **keinen** Alt-Text.
- AU-T09 Blueprint: Modul 4 verlangt 13 Fragen, der Pool hat 12. Soll: 7.
- Soll-Datei: `rk2-soll.json`, Prüfung nach der Sitzung: `wp ccap-ux check-rk rk2 --kurs=<ID>`.
