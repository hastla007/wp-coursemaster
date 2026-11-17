# R1-29 · Aufgabenskript Lernende (Flow 2, R0-26 §1.3)

**Sitzung:** 75 min (ohne Hilfstechnik), moderiert, laut denken. Mit Hilfstechnik: `szenarien-hilfstechnik.md`. Site: Profil `referenz`. Konto: frisches `cc_fx_ux_pNN` (eine Nummer je Person, nie zweimal vergeben). Mindestens 3 von 5 Personen ohne Hilfstechnik machen LE-T01 … T08 auf dem **Smartphone** (BR-07/BR-08), alle Personen mit VoiceOver iOS ebenfalls.

**Startzustand (vor jeder Sitzung prüfen):** Referenzkurse veröffentlicht (`check-rk rk1|rk2|rk3` grün), Konto `cc_fx_ux_pNN` angemeldet bzw. Zugangskarte bereit, RK-2 manuell freigeschaltet, RK-1 **ohne** Zugang (für LE-T01), RK-3 freigeschaltet mit Modul 2 gesperrt (Drip). Browser ohne gespeicherte Daten der vorigen Person (privates Fenster oder Profil zurückgesetzt). Für LE-T06 Teil 2 auf dem Smartphone: Flugmodus erreichbar.

**Bereitschaft (Stand 6f6f05f):** LE-T01/T02/T03/T04/T05/T07/T09/T11/T12 **blockiert**, LE-T06/T10 **teilweise**, LE-T08/UX-031/UX-032 **bereit** (`bereitschaft.md`). Pilot heute: LE-T06 Teil 2 (mit `cc_fx_ux_pruefung`), LE-T08 (mit `cc_fx_ux_bestanden`), UX-031, UX-032. Wenn eine blockierte Aufgabe den Zustand für die nächste liefern muss, stellt die Moderation ihn her (`scenario einschreiben`, `scenario laufende-pruefung`) und protokolliert „nicht durchführbar (Bereitschaft)“ – das ist **kein** Teilnehmendenergebnis.

**Karten:** Ziel, nicht Weg. Abbruch = 3 × Zielzeit (max. 20 min), Hilfstechnik 4 ×. Lesezeit für Kursinhalte (LE-T02/T03) separat stoppen und abziehen.

---

### LE-T01 · Kurs finden und anmelden ★ (UX-020, L-01…L-03)
- **Karte:** „Finde den Kurs zum Zeitmanagement und melde dich dafür an.“
- **Start:** angemeldet, Startseite der Site; Katalog unter `/kurse/`.
- **Erfolg:** Grant `free_enrollment` für RK-1 aktiv.
- **Zeit:** Ziel 3 min · Abbruch 9 min (AT 12 min).
- **Beobachten:** Weg zum Katalog, Erkennen des Kurses, Anmeldeknopf (fehlt heute, B-02), Bestätigung der Anmeldung.
- **Systemprüfung:** Grant des Kontos für `cc-fx-rk1`, Quelle `free_enrollment`.
- **Ersatz bei Blockade:** `scenario einschreiben --konto=cc_fx_ux_pNN --kurs=rk1`.

### LE-T02 · Beginnen und erste Lektion abschließen (UX-021, L-04…L-06, A11Y-021)
- **Karte:** „Beginne mit dem Kurs und schließe die erste Lektion ab.“
- **Erfolg:** Completion für Lektion 1 („Warum Zeitmanagement?“).
- **Zeit:** Ziel 3 min ohne Lesezeit · Abbruch 9 min (AT 12 min).
- **Beobachten:** Einstieg „Fortsetzen“/Kursraum, Lektionsansicht (heute 404, B-01), Abschluss-Bedienelement, Rückmeldung „abgeschlossen“.
- **Systemprüfung:** Lektion 1 `completed` (Kursraum zeigt den Status).

### LE-T03 · Video mit Untertiteln, externes Video (UX-022, A11Y-063/090/093, E-45a)
- **Karte:** „Sieh dir das Video mit Untertiteln an und öffne danach das zweite Video.“
- **Erfolg:** Untertitel eingeschaltet; externes Video abgespielt (Default „immer laden“).
- **Zeit:** Ziel 4 min ohne Videolänge · Abbruch 12 min (AT 16 min).
- **Beobachten:** Untertitel-Schalter finden, Tastatur/Touch am Player, Ladeverhalten des Fremdhosts.
- **Systemprüfung:** Lektion 3 und 6 Fortschritt (Position).

### LE-T04 · Üben bis zur Wiederholung ★ (UX-023, UX-038, L-07, L-08, A11Y-022/024)
- **Karte:** „Übe die Fragen zu Modul 1, bis dir das System etwas zum Wiederholen vorschlägt.“ (RK-2; Modul 1 enthält je eine Zuordnungs-, Sortier-, Lückentext- und Rechenfrage)
- **Erfolg:** ≥ 10 Fragen im Smarttrainer beantwortet; die Person nennt richtig, welche Fragen wiederholt werden. Neue Fragetypen per Maus, Touch **und** Tastatur bedienbar; Zahl mit Dezimalkomma.
- **Zeit:** Ziel 8 min · Abbruch 20 min (AT 32 min).
- **Beobachten:** Feedback richtig/falsch verstanden, Bedienung Zuordnung/Reihenfolge, Eingabe „1.500“ vs. „1500“ vs. „1,5“. Scheitert die Person an der Bedienung (nicht an der Fachfrage) → Befund UX-038.
- **Systemprüfung:** ≥ 10 Lernantworten des Kontos in RK-2.

### LE-T05 · Fehlertraining (UX-024, L-10)
- **Karte:** „Übe die Fragen, die du falsch beantwortet hast.“
- **Erfolg:** Fehlertraining gestartet, ≥ 1 Frage beantwortet.
- **Zeit:** Ziel 3 min · Abbruch 9 min (AT 12 min).

### LE-T06 · Prüfung mit Abbruch und Wiederaufnahme ★ (UX-025, UX-026, L-12, L-13, F2.5, A11Y-074/075)
- **Teil 1, Karte:** „Starte die Prüfung und beantworte mindestens zehn Fragen. Markiere zwei, bei denen du unsicher bist.“ (RK-2, 45 min `strict`)
- **Abbruch durch die Moderation (ohne Ankündigung, nach der 10. Antwort):** Desktop – Tab schließen. Mobil – Flugmodus an, App wechseln (z. B. Kamera), 30 s warten, Flugmodus aus. Details: `szenarien-fehlerbehebung.md` FB-01/FB-02.
- **Teil 2, Karte:** „Setze die Prüfung fort und gib sie ab.“
- **Erfolg:** Fortsetzung **ohne Hilfe**, keine Antwort fehlt, beide Markierungen noch da, Abgabe mit Bestätigung. Die Person nennt die Restzeit richtig (UX-025).
- **Zeit:** Teil 1 Abbruch nach 20 min (AT 30 min); **Fortsetzen Ziel ≤ 1 min** (S-08) · Abbruch 3 min (AT 4 min); gesamt ≤ 30 min.
- **Beobachten:** Weg zurück (Startseite → „Mein Lernen“ → Kursraum → „Continue exam“), Vertrauen in gespeicherte Antworten, Bestätigungsdialog mit offenen/markierten Fragen, Verständnis der Restzeit.
- **Systemprüfung:** Sitzung `evaluated`, Antworten vor und nach dem Abbruch identisch (Protokoll der 10 Antworten mitschreiben), 2 Markierungen. **0 verlorene Antworten**, sonst kritischer Fehler.
- **Pilot mit vorbereitetem Konto:** `cc_fx_ux_pruefung` hat eine laufende RK-1-Prüfung (ohne Zeitlimit, 10 Antworten, 2 Markierungen) – direkt mit Teil 2 beginnen. Für den zeitlich laufenden RK-2-Fall: `scenario laufende-pruefung --konto=cc_fx_ux_pNN` unmittelbar vor der Aufgabe.

### LE-T07 · Fällige Wiederholungen (UX-027, L-05, L-08)
- **Konto:** `cc_fx_ux_faellig` (6 Fragen in RK-2 fällig).
- **Karte:** „Mach die Wiederholungen, die heute für dich fällig sind.“
- **Erfolg:** Modus „fällig“ gefunden, alle fälligen Fragen bearbeitet.
- **Zeit:** Ziel 4 min · Abbruch 12 min (AT 16 min).

### LE-T08 · Ergebnis, Nachweis, Verifikationslink ★ (UX-028, L-14…L-16, A11Y-030, E-96)
- **Karte:** „Finde dein Ergebnis, sieh nach, ob du einen Nachweis bekommen hast, und kopiere den Link zur Echtheitsprüfung.“
- **Erfolg:** Ergebnis gelesen; die Person gibt richtig wieder, ob bestanden und welcher Teil schwach war. Verifikationslink kopiert und in einem privaten Fenster geöffnet.
- **Zeit:** Ziel 4 min · Abbruch 12 min (AT 16 min).
- **LE-T08b:** Hat die Person nicht bestanden, Ergebnis wie oben werten und danach die Nachweisansicht mit `cc_fx_ux_bestanden` zeigen (Moderation meldet um; zweiter Teil der Aufgabe: Link kopieren und privat öffnen).
- **Systemprüfung:** privates Fenster zeigt „Certificate verification Valid“ (bzw. deutsch „gültig“).

### LE-T09 · Eigene Daten herunterladen (UX-029, L-20)
- **Karte:** „Lade dir eine Übersicht deiner Ergebnisse und Daten herunter.“
- **Erfolg:** Export ausgelöst, Datei erhalten.
- **Zeit:** Ziel 3 min · Abbruch 9 min (AT 12 min).

### LE-T10 · Am nächsten Tag weitermachen, mobil ★ (UX-030, L-04, L-05)
- **Konto:** `cc_fx_ux_teilweise` (RK-1, Lektionen 1–4 fertig, Lektion 5 begonnen) auf dem Smartphone, frisch angemeldet.
- **Karte:** „Du hast den Kurs gestern abgebrochen. Mach dort weiter, wo du warst.“
- **Erfolg:** Über „Fortsetzen“ an die richtige Lektion (5 „Die ALPEN-Methode“) bzw. Position. **Median ≤ 1 min.**
- **Zeit:** Ziel 1 min · Abbruch 3 min (AT 4 min).

### LE-T11 · Aufgabe bearbeiten und einreichen ★ (UX-036, R1-46, neu ohne LB-Vorbild)
- **Karte:** „Bearbeite die Aufgabe und reiche sie ein.“ (RK-1 Text; RK-2 Datei-Upload: Datei `abgabe-gut.pdf` auf dem Testgerät bereitlegen)
- **Erfolg:** Abgabe `submitted`; die Person weiß, dass sie danach nichts mehr ändern kann, und findet den Status „eingereicht“. 0 verlorene Entwürfe. Upload mit Tastatur und Screenreader bedienbar.
- **Zeit:** Ziel 6 min ohne Schreibzeit · Abbruch 18 min (AT 24 min).
- **Beobachten:** Entwurf speichern, Zeichenbegrenzung (RK-1: 150–1500 Zeichen), Bestätigung vor dem Einreichen.

### LE-T12 · Rückgabe überarbeiten, Bewertung lesen (UX-037, R1-46, neu ohne LB-Vorbild)
- **Konto:** `cc_fx_ux_rueckgabe` (RK-1, zurückgegeben mit Kommentar „Bitte ergänze bei jedem Ziel einen Termin …“).
- **Karte:** „Deine Abgabe wurde zurückgegeben. Überarbeite sie, reiche sie erneut ein und sieh dir später die Bewertung an.“
- **Ablauf:** Nach dem Einreichen bewertet die Moderation als `cc_fx_ux_trainer` (z. B. 8 Punkte, Feedback „Gut ergänzt.“); danach liest die Person die Bewertung.
- **Erfolg:** Kommentar gefunden, zweite Fassung eingereicht, Punkte und Feedback richtig wiedergegeben.
- **Zeit:** Ziel 5 min · Abbruch 15 min (AT 20 min).

### LE-GES · Ende-zu-Ende ★ (UX-033, S-09)
- **Umfang:** LE-T01 … LE-T08, Summe ohne Lesezeit. **Desktop ≤ 60 min, mobil ≤ 70 min (Median).** Alle ★-Teilaufgaben gelöst.

---

## Zusatzaufgaben (wenn Zeit bleibt, sonst Expertendurchlauf)

| ID | Karte / Ablauf | Erfolg | Konto / Vorbereitung |
|---|---|---|---|
| UX-031 | „Warum kannst du Lektion 4 noch nicht öffnen?“ (RK-1 ohne Zugang: Teilumfang erste 3 Lektionen) bzw. „Warum ist Modul 2 im Onboarding-Kurs gesperrt?“ (RK-3, Drip) | ≥ 80 % nennen den Grund richtig | frisches Konto |
| UX-032 | „Du schreibst gerade eine Prüfung. Mach weiter.“ – nach 1 min erscheint die Ablaufmeldung | Auto-Abgabe-Meldung verstanden, Ergebnis gefunden | `cc_fx_ux_ablauf`, vorher `scenario ablauf`; Ergebnisseite ohne Wartezeit: `cc_fx_ux_abgelaufen` |
| UX-035 | Während LE-T06 Smartphone/iPad drehen (Hoch ↔ Quer) | kein Zustandsverlust | BR-07, BR-09 |
| UX-075 | „Wie viel Zeit hast du für diese Prüfung?“ vor und nach dem Start | Restzeit inkl. Zeitausgleich richtig genannt; Ansagen 10/5/1 min | `cc_fx_ux_ausgleich` (150 %: 67 min 30 s) |

## Nachgespräch Lernende

Drei feste Fragen (Leitfaden §4.3), Verständnisfrage LE-T08, danach SUS.
