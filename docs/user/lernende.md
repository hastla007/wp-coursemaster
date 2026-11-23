# Hilfe für Lernende

Diese Seite dient Betreibern als Vorlage für eigene Hilfeseiten. Geprüft gegen: `src/Learner/{LearningArea,LessonView,LearnerQueries,LearnerRoutes}.php`, `src/Learning/*`, `src/Exam/{ExamSessions,ExamFeedback}.php`, `src/Certificate/CertificatePages.php`, `src/Notification/NotificationPrefs.php`.

> **Stand:** Der Lernbereich zeigt „Mein Lernen“, den Kursraum, die Lektionsansicht (Text, Video, Datei, Abschluss per Knopf), den Prüfungsraum, die Nachweise und die Benachrichtigungseinstellungen. **Fragen beantworten und Karteikarten umdrehen in der Lektion** sowie die **Abgabe von Aufgaben** sind **geplant (R1-19/R1-20, R1-46)**. Die Regeln unten sind serverseitig umgesetzt und über die REST-API nutzbar. Sie beschreiben, wie sich diese Funktionen verhalten werden.

## 1. Lernbereich

Adresse: `https://<ihre-site>/lernen/` (der Betreiber kann den Pfad ändern). Ohne Anmeldung werden Sie zur Anmeldung und danach zurück geleitet.

**Mein Lernen** zeigt Ihre Kurse mit Status und Fortschritt in Prozent sowie einen Knopf *Continue*, der direkt die nächste offene Lektion öffnet.

| Status | Bedeutung |
|---|---|
| aktiv | voller Zugang |
| eingeschränkt | Zugang nur zu einzelnen Modulen oder zu einer Probe-Auswahl |
| ausgesetzt | Zugang vorübergehend nicht verfügbar |
| abgelaufen | Zugangszeitraum beendet |
| ausstehend | Zugang wird gerade aktiviert |
| kein Zugang | Sie sind für diesen Kurs nicht freigeschaltet |

**Kursraum** (`/lernen/kurs/<kennung>`): Module und Lektionen mit Status. Gesperrte Inhalte nennen den Grund als Text, zum Beispiel „ab <Datum> freigeschaltet“ oder „erst nach Abschluss von Modul X“. Gründe für eine Sperre können sein: kein Zugang zum Modul, nur Probe-Auswahl (die ersten *n* Lektionen bzw. Fragen), zeitliche Freigabe, offene Voraussetzung, laufende Prüfung.

**Lektion** (`/lernen/kurs/<kennung>/lektion/<nummer>`): Titel, Text und je nach Typ Video oder Datei-Download, darunter „Your progress“ mit dem Status als Text und – wenn die Lektion durch Bestätigen abgeschlossen wird – dem Knopf *Mark as completed*. Lektionen mit Mindest-Lesezeit haben den Knopf *Mark as read*; er wirkt erst nach der angegebenen Zeit. Datei-Lektionen zählen den Download über den Knopf *Download the file*. Unten führen *Previous*/*Next* zur vorigen bzw. nächsten freigeschalteten Lektion. Eine gesperrte Lektion zeigt nur den Sperrgrund (HTTP 403). Alles funktioniert ohne JavaScript.

Barrierefreiheit: Sprunglink zum Inhalt, Statusangaben als Text, sichtbarer Fokus, Bedienelemente mindestens 44 px, einspaltig auf dem Smartphone.

## 2. Fortschritt und Kursabschluss

- Lektionen werden je nach Typ durch Bestätigen, Ansehen, Download, Videoposition, Quiz oder Prüfung abgeschlossen.
- Ein Kurs ist abgeschlossen, wenn alle Pflichtmodule fertig sind. Der Prozentwert zählt nur Module, auf die Sie Zugang haben.
- Ein einmal erreichter Abschluss geht nicht verloren, auch wenn der Zugang später endet oder der Kurs überarbeitet wird.

## 3. Smarttrainer (Fragen üben)

Der Smarttrainer wählt die nächste Frage aus den Modulen, auf die Sie Zugang haben. Modi: **smart** (gemischt), **new** (noch nie gesehene), **wrong** (zuletzt falsch beantwortete), **favorites** (Ihre Favoriten oder eine Sammlung). Optional nach Thema.

So funktioniert die Wiederholung:

- Eine falsche Antwort setzt die Frage auf „unsicher“.
- Drei richtige Antworten in Folge mit etwas Abstand (mindestens 5 Minuten oder eine andere Frage dazwischen) machen sie „sicher“; sie kommt nach 20 Stunden wieder.
- Richtig beantwortete fällige Fragen werden „gefestigt“ und kommen nach 7, 21 und dann 60 Tagen wieder.
- Mehrfaches Antworten in kurzer Folge zählt nicht für die Serie, es wird aber gezählt.
- Höchstens 60 Antworten pro Minute; darüber erscheint „bitte kurz warten“ (`ccap_rate_limited`).
- Ob nach einer Antwort die richtige Lösung angezeigt wird, legt der Betreiber fest.

## 4. Karteikarten, Favoriten, Fehlertraining

- **Karteikarten** je nach Lektion: Selbsteinschätzung (offen / üben / gewusst, jederzeit änderbar) oder Serie (dreimal hintereinander „gewusst“, ein „nicht gewusst“ setzt die Serie zurück; einmal gewusst bleibt gewusst).
- **Favoriten:** Fragen aus zugänglichen Modulen merken. **Sammlungen** je Kurs: höchstens 30, Name bis 60 Zeichen, eindeutig. Wenn Sie einen Favoriten entfernen, verschwindet er aus allen Sammlungen. Wenn Sie eine Sammlung löschen, bleiben die Favoriten erhalten.
- **Fehlertraining:** Fragen, deren letzte Lernantwort falsch war, und die falschen Fragen Ihrer ausgewerteten Prüfungen. Prüfungsantworten ändern Ihren Lernstand nicht.

## 5. Prüfungen

- **Start:** Ein Prüfungsversuch beginnt mit dem Start. Wird die Seite mit demselben Versuch neu geladen, läuft derselbe Versuch weiter. Höchstens 40 Starts pro Stunde. Versuchsgrenze und Wartezeit zwischen Versuchen legt die Prüfung fest.
- **Zeit:** Es gilt die **Serverzeit**. Die Prüfung zeigt Restzeit und Fristende. Antworten bis 5 Sekunden nach Fristende werden noch angenommen. Danach antwortet der Server mit „Zeit abgelaufen“, und der Versuch wird automatisch ausgewertet, spätestens beim nächsten Aufruf oder im Stundenlauf.
- **Fortsetzen:** Nach einem Verbindungsabbruch, Browserabsturz oder Neuladen sehen Sie Ihre gespeicherten Antworten, Markierungen und aufgedeckten Musterantworten wieder.
- **Offline:** Antworten, die ohne Verbindung gepuffert und später gesendet werden, zählen mit ihrem ursprünglichen Zeitpunkt (höchstens 120 Sekunden zurück), wenn dieser vor dem Fristende plus Kulanz lag.
- **Mehrere Tabs:** Ein älterer Stand überschreibt nie einen neueren.
- **Markieren** von Fragen zum späteren Prüfen; **Freitextfragen:** Erst die Musterantwort aufdecken, dann die eigene Einschätzung abgeben (vorher meldet der Server `ccap_exam_answer_locked`).
- **Abgeben** ist endgültig; doppeltes Abgeben ändert nichts. **Abbrechen** beendet den Versuch ohne Wertung; er zählt je nach Prüfung als Versuch.
- **Ergebnis:** je nach Prüfung sofort nach Abgabe oder gar nicht. Ob Fragen richtig oder falsch waren, wird angezeigt, wenn das Ergebnis freigegeben ist. Punkte und Lösungen hängen von der Prüfung ab. Lösungen erscheinen oft erst nach Bestehen oder wenn kein Versuch mehr übrig ist.
- Wird der Kurszugang während der Prüfung entzogen, sind keine weiteren Antworten möglich; eine Abgabe wird gewertet.
- Bei einem technischen Fehler melden Sie dem Support die angezeigte Fehlernummer bzw. Anfrage-ID. Ein Zusatzversuch kann nur vom Kursteam gewährt werden (geplant: Support-Werkzeuge R1-38).

## 6. Nachweise (Zertifikate)

- **Liste:** `/lernen/nachweise/`; **Ansicht und Druck:** `/lernen/nachweise/<kennung>` (Druckansicht im A4-Format mit Nachweis-Nr., Prüfcode und Prüf-Link). Ein PDF-Download ist nicht vorhanden.
- Ein Nachweis zeigt die Daten zum Ausstellungszeitpunkt. Wenn Sie Ihren Namen im Profil ändern, ändert sich der Nachweis nicht. Für eine Korrektur wenden Sie sich an den Betreiber; es entsteht dann eine neue Fassung.
- **Öffentliche Prüfung:** Dritte können den Nachweis über den Prüf-Link prüfen. Ihr Name erscheint dort nur, wenn Sie ihn freigeben (falls der Betreiber das erlaubt). E-Mail-Adresse und Punkte werden nie gezeigt. Die Freigabe gilt für alle Fassungen und lässt sich jederzeit zurücknehmen. Ein Bedienelement im Lernbereich ist geplant; heute geht das über `PUT /me/certificates/{uid}/visibility`.

## 7. Benachrichtigungen

- Seite `/lernen/benachrichtigungen/`: Häkchen je informativer Mailart (z. B. neuer Kurszugang, Kurs abgeschlossen, Zugang beendet).
- Jede informative Mail enthält einen Abmeldelink. Der Link fragt nach einer Bestätigung; erst der Knopf *Unsubscribe* meldet ab.
- Mails, die für Konto oder Teilnahme nötig sind (z. B. Gruppeneinladung, Widerruf eines Nachweises), werden immer gesendet.

## 8. Ihre Daten

Sie können beim Betreiber eine Kopie Ihrer Daten oder deren Löschung beantragen (WordPress-Datenschutzanfrage). Was gespeichert wird, steht in [datenschutz.md](datenschutz.md). Ein eigenes Antragsformular im Lernbereich ist geplant (R1-34).
