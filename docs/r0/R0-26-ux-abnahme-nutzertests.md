# R0-26 · UX- und Accessibility-Abnahme mit Nutzertests

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Dies ist ein Testplan. Es gibt noch keine Implementierung, keine Teilnehmenden und keine Messwerte. Alle Zahlen in den Spalten „Schwelle“ und „Zielzeit“ sind **Vorgaben**, keine Ergebnisse. Durchgeführt wird der Plan in **R1-29** (Voraussetzungen R1-16, R1-19, R1-26 und für die Aufgaben R1-46, E-168). Das Ergebnis geht in **R1-GATE** ein (Gates B/C, Konzept 28.6).
**Stand:** 16.11.2026
**Bezug:** TODO R0-26 (DoD: „Autor-/Lernenden-Referenzflows, Testgeräte, Tastatur-/Screenreader-Prüfungen und echte Usability-Abnahmekriterien spezifiziert“), R1-29 (DoD: „Referenzaufgaben durchgespielt; Tastatur, Screenreader, mobile Prüfungen und Fehlerbehebung mit dokumentierten Befunden“), TODO-Punkte „Drei neutrale Autorentests“, „Lernenden-E2E und Accessibility“, „Praxis-Benchmark“, „Messkonzept erstellen“. Konzept Kap. 15.2 (E2E-Nutzerreisen 1, 2, 3, 6), 16.1/16.3, 23.3, 27.4, 28.4 Nr. 5/6, 28.6. `R0-11-free-pro-matrix-ux.md` (Screens A-01…A-18, L-01…L-21, Zustände Z-x, Flows 1–4). `R0-15-barrierefreiheit-i18n.md` (A11Y-001…112, §2.3 Zeitausgleich, §2.4 Matrix, §5). `R0-14-nichtfunktionale-ziele.md` §3 (Browser/Geräte). `R0-05-golden-master.md` §2 (`cc-fixture-neutral-v1`). `R0-22-kursvalidierung-publish.md` §2.3/§3 (Vorschau, Validierungspanel).
**Abgrenzung:** Automatisierte A11Y-, E2E- und Golden-Master-Prüfungen gehören zu R1-18 und R1-19. Dieses Dokument regelt nur, was **Menschen** prüfen: Nutzertests mit Teilnehmenden und manuelle Expertendurchläufe mit Hilfstechnik (A11Y-102…104). Rechtliche Konformitätsaussagen trifft es nicht (vgl. R0-15, E-125). Telemetrie nach dem Launch (Kap. 23.3) wird hier nicht gebaut. §7 liefert nur die Baselines dafür.

**Normative Sprache:** **MUSS** ist Abnahmebedingung, **SOLL** ist Standard, **KANN** ist optional.

---

## 0. Annahmen und Rahmen

### 0.1 Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). In dieses Dokument eingearbeitet:

| Beschluss | Wirkung hier |
|---|---|
| **E-01**, E-02 | Referenzkurse sind eigene, synthetische Inhalte (§0.2 Nr. 3); kein LB-Material |
| **E-161 … E-171** (früher D-26-01 … D-26-11) | Rekrutierung, Budget, Teilnehmerzahl, Moderation, Autorenumfang, Schwellen, Aufzeichnung, Aufgabe, Sprache, Wiederholung, S2-Regel (§8) |
| **E-168** | **abweichend von der früheren Empfehlung D-26-08:** Text-/Dateiaufgaben mit Bewertung kommen in R1 (R1-46). Jeder Referenzkurs enthält **eine Aufgabe** (§0.2 Nr. 1, §1.1); neue Aufgaben AU-T12, AU-T13, LE-T11, LE-T12 und Testfälle UX-016, UX-017, UX-036, UX-037 (neu, ohne LB-Vorbild); `RK-v1.1` entfällt |
| **E-20** | Lösungserklärungen sind Pro: Die Tests laufen ohne Pro (§0.2 Nr. 4), Drehbücher enthalten keine Erklärungstexte, AU-T04 verlangt keine Erklärung; Lernende sehen richtig/falsch und nach Einstellung die richtige Antwort (E-147) |
| **E-22**, **E-69** | Fragetypen „Zahl mit Toleranz“, Zuordnung, Sortieren, Lückentext in R1 Free: Typmix der Referenzkurse erweitert (§1.1), AU-T05, LE-T04, neuer Testfall UX-038 (neu, ohne LB-Vorbild) |
| **E-27** | Zugang je Modul: AU-T11 enthält bei RK-2 eine Freischaltung nur für einzelne Module |
| **E-45/E-45a** | externe Medien Default „immer laden“: AU-T03, LE-T03, UX-022 ohne Klick-Zustimmung; der Platzhalter „Klick zum Laden“ wird im Expertendurchlauf UX-080 mit umgestellter Einstellung geprüft |
| **E-232** | Gruppenbesitzer sehen in Free keine Lernleistungen; Flow 3 enthält keine Fortschrittsaufgabe (Fortschrittseinsicht nur Pro als Opt-in, R3) |
| **E-61**, **E-126** | Browser: die letzten zwei Hauptversionen von Chrome, Edge, Firefox und Safari, dazu Firefox ESR, iOS Safari und Android Chrome; zugesichert NVDA und VoiceOver (macOS, iOS), TalkBack best effort, JAWS nicht zugesichert (Befunde nur mit JAWS ≤ S3, außer NVDA/VO zeigen denselben Fehler) (§3, §4.4) |
| E-17, E-28 | Gruppenbesitzer verwalten ihre Gruppe selbst (Free); Einladungslink mit Option „nur eingeladene Adresse“ (Flow GV §1.4) |
| E-96 | Basisnachweis als HTML- und Druckansicht (Free) (LE-T08) |
| E-102 | Setup legt in R1 nur ein leeres Kursgerüst an (AD-T01) |
| E-121 | Quell-Strings en_US, Deutsch erste Übersetzung (Testsprache nach E-169) |
| E-122, E-123 | Zeitausgleich je Lernendem; Timer-Modi `strict`/`practice` (RK-2-Prüfung `strict`, UX-075) |
| E-72, E-75 | Auto-Abgabe nach Inaktivität (Option); Ablauf führt zu `evaluated` mit `end_reason=expired` (Wiederaufnahme-Aufgaben, UX-032) |
| E-207 | Präfix `ccap_` für Plugin-Bezeichner; Testkonten `cc_fx_…` und Paketnamen `cc-fx-…` sind Testdaten und bleiben |

### 0.2 Zuschnitt und Abweichungen

1. **Aufgabe (Abgabe) in R1 (E-168).** Konzept 27.4/28.4 und TODO verlangen Referenzkurse „mit einer Aufgabe“. Nach E-168 kommen Text- und Dateiaufgaben mit Bewertung in R1 (R1-46). Die R1-Referenzkurse haben deshalb **10 Lektionen, 50 Fragen, 1 Prüfung und 1 Aufgabe** (eine der 10 Lektionen ist die Aufgabe). Für Aufgaben gibt es kein LB-Vorbild; die zugehörigen Testaufgaben und Testfälle sind als neu gekennzeichnet. Voraussetzung für R1-29 ist damit zusätzlich R1-46.
2. **Video ist Pflicht.** Konzept 28.4 Nr. 5 nennt „Video“. Jeder Referenzkurs hat deshalb ein internes Video mit WebVTT-Untertitel und ein externes Video. Die Testumgebung nutzt den Default „immer laden“ (E-45a); der Platzhalter „Klick zum Laden“ wird im Expertendurchlauf UX-080 geprüft.
3. **Lernraum-Inhalte müssen verständlich sein.** Die Platzhaltertexte aus `cc-fixture-neutral-v1` („Welche Aussage trifft auf Konzept 1 zu?“) taugen für Golden Master, aber nicht für Nutzertests: Lernende könnten Fragen nur raten. Für die Tests gibt es eigene Referenzkurse (§1.1). Ihre Inhalte sind allgemein verständlich, fachneutral, ohne Bezug auf einen Fremdanbieter, unter CC0, und jede Frage ist aus dem Lektionstext beantwortbar. Für die technischen Regeln gilt R0-05 §2.1: Präfix `cc-fx-rk…`, Domains `example.test`, synthetische Medien, stabile `source_id`.
4. **Edition:** Alle Aufgaben laufen in **Free ohne Pro, ohne WooCommerce und ohne Suite-Plugins** (Gate B, Kap. 28.5). Flow 5 (Pro) gehört zu R3.

---

## 1. Referenzkurse und Referenzflows

### 1.1 Drei neutrale Referenzkurse (Paket `cc-fx-rk-v1`)

Jeder Kurs hat dieselbe Grundmenge, damit die Messwerte vergleichbar bleiben. Die Konfiguration unterscheidet sich je Kurstyp.

| Merkmal | **RK-1 Selbstlernkurs** | **RK-2 Prüfungsvorbereitung** | **RK-3 Onboarding** |
|---|---|---|---|
| Slug / Arbeitstitel | `cc-fx-rk1` „Zeitmanagement im Alltag“ | `cc-fx-rk2` „Grundlagen der Tabellenkalkulation – Prüfungstraining“ | `cc-fx-rk3` „Einarbeitung bei der Beispiel GmbH“ (fiktiv, `example.test`) |
| Struktur | 3 Module, 10 Lektionen (4/3/3) | 4 Module, 10 Lektionen (3/3/2/2) | 2 Module, 10 Lektionen (5/5), lineare Reihenfolge |
| Activities | 6 × Text, 1 × Video intern + VTT, 1 × Video extern, 1 × Datei (PDF), **1 × Aufgabe (Text)** | 5 × Text, 2 × Video intern + VTT, 1 × Text + Inline-Quiz (3 Fragen), 1 × Datei, **1 × Aufgabe (Datei-Upload, z. B. Tabelle als PDF)** | 5 × Text mit „gelesen bestätigen“, 1 × Video intern + VTT, 1 × Video extern, 2 × Datei, **1 × Aufgabe (Text + Datei)** |
| 50 Fragen (Typmix nach E-22, E-69) | 22 single, 9 multi, 5 true_false, 4 open_text, 5 flashcard, 2 matching, 2 ordering, 1 cloze | 22 single, 12 multi, 4 true_false, 2 open_text, **4 numeric_tolerance**, 2 matching, 2 ordering, 2 cloze | 25 single, 10 multi, 10 true_false, 5 flashcard |
| Aufgabe (E-168) | max. 10 Punkte, Bestehen ab 6, Abschluss „bewertet“ | max. 20 Punkte, Bestehen ab 12, Abschluss „bestanden“, Allowlist PDF | max. 10 Punkte, ohne Bestehensgrenze, Abschluss „eingereicht“; Voraussetzung für den Abschlusscheck |
| Medien in Fragen | 2 Bilder (1 mit Alt-Text, 1 dekorativ) | 3 Bilder mit Alt-Text, davon 1 Diagramm mit Textalternative | 1 Bild mit Alt-Text |
| Prüfung | Abschlussquiz: 20 aus 50, ohne Zeitlimit, Grenze 70 %, Versuche unbegrenzt, Feedback sofort | Prüfung **`strict`**: Blueprint 30 aus 50 nach Modul (8/8/7/7), 45 min, Grenze 75 % gesamt und je Teil ≥ 60 %, 3 Versuche, Feedback nach Abgabe | Abschlusscheck: 15 feste Fragen, 20 min `practice`, Grenze 80 %, Voraussetzung: alle Lektionen bestätigt |
| Zugang | Kostenlose Selbstanmeldung, Teilumfang „erste 3 Lektionen“ | Manuelle Freischaltung | Gruppe (Plätze 5), relativer Drip: Modul 2 ab Tag 1 |
| Nachweis | Ausstellung bei Abschluss + Quiz bestanden | Ausstellung bei bestandener Prüfung (nachweisfähig) | Ausstellung bei Abschlusscheck bestanden |
| Schwerpunkt im Test | Curriculum, Medien, Smarttrainer-Fragen, Selbstanmeldung | Blueprint, Teilgrenzen, Timer, Pool-Validierung | Voraussetzungen, Drip, Gruppe |

**Lieferform für die Autorentests:**

- **Drehbuch (Papier/PDF, barrierefrei):** Texte, Fragen, Antworten und die Aufgabenstellung zum Kopieren, dazu die Soll-Konfiguration als Checkliste. Erklärungstexte entfallen, weil Free sie nicht ausliefert (E-20). Die Medien liegen in einem Ordner.
- **Fragen-Teilpaket `rk<n>-q40`** im CC-Paketformat v1 (R0-10) mit 40 der 50 Fragen. 10 Fragen legt die Person selbst im Editor an (E-165). Kann das Paketformat keinen Import nur von Fragen, ist das ein Delta für R0-10. Ersatzweise enthält das Teilpaket den Kurs ohne Lektionen.
- **Soll-Zustand `rk<n>-soll.json`:** Eine maschinenlesbare Liste der Pflichtmerkmale (Anzahl Lektionen, Fragen je Modul, Prüfungsparameter, Alt-Texte, Untertitel, Zugangsmodus). Ein Prüfskript `tools/ux/check-rk` (Vorschlag) vergleicht nach der Sitzung den veröffentlichten Kurs damit. Damit ist „Erfolg“ objektiv feststellbar (§2.4).
- **Referenzstand für Lernendentests:** Das Team veröffentlicht alle drei Kurse vollständig nach Drehbuch. Lernende arbeiten **nie** mit Kursen aus Autorensitzungen.
- **Vorbereitete Abgaben** (für AU-T13 und LE-T12): je Referenzkurs drei eingereichte Abgaben synthetischer Konten (eine gute, eine unvollständige, eine mit Datei) und ein Lernendenkonto mit zurückgegebener Abgabe samt Kommentar.

### 1.2 Referenzflow Autor (Flow 1, Kap. 15.2 Nr. 1, Kap. 28.4 Nr. 5)

Startzustand: Free ist installiert, Setup ist abgeschlossen, das Konto hat die Rolle Course Manager, kein Kurs ist vorhanden (Z-L in A-03). Die Aufgabentexte nennen keine UI-Begriffe, die der Oberfläche die Lösung vorwegnehmen.

| ID | Aufgabe (Text an die teilnehmende Person, gekürzt) | Erfolgskriterium (objektiv) | Zielzeit Median | Screens / Bezug |
|---|---|---|---|---|
| AU-T01 | „Lege den Kurs aus dem Drehbuch mit Titel, Beschreibung und Lernzielen an.“ | Kurs als Entwurf mit allen 3 Feldern | 4 min | A-03, A-04 |
| AU-T02 | „Baue die Module und 10 Lektionen in der Reihenfolge des Drehbuchs auf. Eine Lektion steht an der falschen Stelle; verschiebe sie.“ | Struktur = Soll. Verschieben gelingt per Maus **oder** Tastatur (die Person wählt) | 15 min | A-04, A11Y-008 |
| AU-T03 | „Füge das Video mit Untertiteln, das externe Video und die PDF-Datei ein.“ | Untertitelspur zugeordnet, externes Video eingebunden, PDF verknüpft | 8 min | A-04, A-06, A11Y-090/093 |
| AU-T04 | „Lege diese drei Fragen an: eine mit einer richtigen Antwort, eine mit mehreren, eine Wahr/Falsch-Frage.“ | 3 Fragen, Korrektheit = Soll | 8 min (Frage 2/3 je ≤ 2 min) | A-07 |
| AU-T05 | „Lege die übrigen sieben Fragen aus dem Drehbuch an, eine davon mit Bild.“ Darunter ist je Kurs eine Frage eines neuen Typs: RK-1 Zuordnung, RK-2 Rechenfrage mit erlaubter Abweichung (Zahl mit Toleranz, mit Einheit), RK-3 keine | 7 Fragen = Soll. Das Bild hat einen Alt-Text oder ist als dekorativ markiert. Die Frage des neuen Typs ist richtig bewertet (Zuordnungen bzw. Sollwert, Toleranz, Einheit = Soll) | 14 min | A-07, A11Y-091/110, E-22, E-69 |
| AU-T06 | „Übernimm die restlichen 40 Fragen aus der bereitgestellten Datei.“ | Dry-run gesehen, Freigabe erteilt, 50 Fragen im Kurs | 6 min | A-13 (Flow 1 F1.6) |
| AU-T07 | „Richte die Prüfung nach dem Drehbuch ein.“ | Prüfungsparameter = Soll. Bei RK-2: Blueprint, Teilgrenzen und Zeit korrekt | 10 min | A-08 |
| AU-T08 | „Stelle ein, wer den Kurs nutzen darf, und schau ihn dir an, wie Lernende ihn sehen.“ | Zugangsmodus = Soll. Die Vorschau wurde in mindestens einer Persona geöffnet | 6 min | A-04, R0-22 §2.3 |
| AU-T09 | „Veröffentliche den Kurs.“ Das Drehbuch hat **zwei eingebaute Fehler**: ein Bild ohne Alt-Text und bei RK-2 einen Blueprint, der mehr Fragen verlangt als ein Modul hat. | Die Person findet beide Blocker über das Validierungspanel, behebt sie und veröffentlicht | 8 min | R0-22 §3, F1.1, A11Y-031 |
| AU-T10 | „Ändere im veröffentlichten Kurs eine Frage.“ | Die Person sieht den Revisionshinweis (F1.3) und erklärt im Nachgespräch richtig, was mit laufenden Versuchen passiert | 4 min | A-07, F1.3 |
| AU-T11 | „Schreibe einen Testnutzer ein und prüfe dessen Fortschritt.“ Bei RK-2: „… nur für die Module 1 und 2.“ (E-27) | Grant aktiv (bei RK-2 genau für Modul 1 und 2), Fortschrittsansicht geöffnet | 5 min | A-09 (Kap. 15.2 Nr. 1) |
| AU-T12 | „Lege die Aufgabe aus dem Drehbuch an und stelle ein, wie sie bewertet wird.“ **(neu, ohne LB-Vorbild)** | Aufgabe = Soll: Aufgabenstellung, Abgabeart, Punkte, Bestehensgrenze, Abschlussregel; bei RK-2 Dateityp PDF | 6 min | A-04, R0-08 §2.5, R1-46 |
| AU-T13 | „Drei Abgaben warten auf dich. Bewerte zwei und gib eine mit einem Hinweis zur Überarbeitung zurück.“ (vorbereitete Abgaben) **(neu, ohne LB-Vorbild)** | 2 Abgaben `graded` mit Punkten = Soll ± 0, 1 Abgabe `returned` mit Kommentar; die Person erklärt richtig, was der Lernende danach sieht | 8 min | R0-08 §2.5, R1-46 |
| **AU-GES** | **Referenzkurs vollständig veröffentlicht** (T01–T09 und T12) | Prüfskript `check-rk`: alle Pflichtmerkmale erfüllt | **≤ 90 min Median, keine Person > 120 min** | Messkonzept „Zeit bis zur Kursveröffentlichung“ |

Störfall in der Sitzung (Moderation löst aus): Während AU-T05 wird die Sitzung ungültig gemacht oder das Netz getrennt (F1.4). Erwartet wird, dass kein Text verloren geht und die Person ohne Hilfe weiterarbeitet (UX-012).

### 1.3 Referenzflow Lernende (Flow 2, Kap. 15.2 Nr. 2, Kap. 28.4 Nr. 6)

Startzustand: Die Referenzkurse sind veröffentlicht. Die Person hat ein frisches Konto (`cc_fx_ux_pNN`). Für RK-2 ist der Zugang manuell freigeschaltet. Für LE-T07 gibt es zusätzlich ein vorbereitetes Konto mit Lernhistorie und fälligen Fragen (`due_at` in der Vergangenheit, nach dem Muster von R0-05 PAR-06).

| ID | Aufgabe (gekürzt) | Erfolgskriterium | Zielzeit Median | Screens / Bezug |
|---|---|---|---|---|
| LE-T01 | „Finde den Kurs zum Zeitmanagement und melde dich dafür an.“ (RK-1, angemeldet) | Grant `free_enrollment` aktiv | 3 min | L-01, L-02, L-03 |
| LE-T02 | „Beginne mit dem Kurs und schließe die erste Lektion ab.“ | Completion für Lektion 1 | 3 min | L-04, L-05, L-06 |
| LE-T03 | „Sieh dir das Video mit Untertiteln an und öffne danach das zweite Video.“ | Untertitel eingeschaltet. Das externe Video wurde abgespielt (Default „immer laden“, E-45a) | 4 min | L-06, A11Y-090/093 |
| LE-T04 | „Übe die Fragen zu Modul 1, bis dir das System etwas zum Wiederholen vorschlägt.“ (RK-2; Modul 1 enthält je eine Zuordnungs-, Sortier-, Lückentext- und Rechenfrage) | ≥ 10 Fragen im Smarttrainer beantwortet. Die Person nennt richtig, welche Fragen wiederholt werden | 8 min | L-07, L-08 |
| LE-T05 | „Übe die Fragen, die du falsch beantwortet hast.“ | Fehlertraining gestartet, ≥ 1 Frage beantwortet | 3 min | L-10 |
| LE-T06 | „Starte die Prüfung und beantworte mindestens zehn Fragen. Markiere zwei, bei denen du unsicher bist.“ Danach bricht die Moderation ab: Tab schließen (Desktop) bzw. Flugmodus und App-Wechsel (mobil). „Setze die Prüfung fort und gib sie ab.“ | Die Prüfung wird **ohne Hilfe** fortgesetzt, keine Antwort fehlt, beide Markierungen sind noch da, Abgabe mit Bestätigung | Fortsetzen ≤ 1 min. Gesamt ≤ 30 min | L-12, L-13, F2.5, A11Y-074/075 |
| LE-T07 | „Mach die Wiederholungen, die heute für dich fällig sind.“ (vorbereitetes Konto) | Modus „fällig“ gefunden, alle fälligen Fragen bearbeitet | 4 min | L-05, L-08 (Kap. 15.2 Nr. 2 „später fällige Wiederholung“) |
| LE-T08 | „Finde dein Ergebnis, sieh nach, ob du einen Nachweis bekommen hast, und kopiere den Link zur Echtheitsprüfung.“ | Ergebnis gelesen. Die Person gibt richtig wieder, ob bestanden und welcher Teil schwach war. Verifikationslink kopiert und in einem privaten Fenster geöffnet | 4 min | L-14, L-15, L-16 |
| LE-T09 | „Lade dir eine Übersicht deiner Ergebnisse und Daten herunter.“ | Export ausgelöst, Datei erhalten | 3 min | L-20 (Kap. 28.4 Nr. 6 „Datenexport“) |
| LE-T10 | „Du hast den Kurs gestern abgebrochen. Mach dort weiter, wo du warst.“ (neu angemeldet, mobil) | Über „Fortsetzen“ an die richtige Lektion bzw. Position gekommen | 1 min | L-04, L-05 |
| LE-T11 | „Bearbeite die Aufgabe und reiche sie ein.“ (RK-1 Text, RK-2 Datei-Upload) **(neu, ohne LB-Vorbild)** | Abgabe `submitted`; die Person weiß, dass sie danach nichts mehr ändern kann, und findet den Status „eingereicht“ | 6 min (ohne Schreibzeit) | L-06, R0-08 §2.5, R1-46 |
| LE-T12 | „Deine Abgabe wurde zurückgegeben. Überarbeite sie, reiche sie erneut ein und sieh dir später die Bewertung an.“ (vorbereitetes Konto, Bewertung durch die Moderation) **(neu, ohne LB-Vorbild)** | Kommentar gefunden, zweite Fassung eingereicht, Punkte und Feedback richtig wiedergegeben | 5 min | R0-08 §2.5, R1-46 |
| **LE-GES** | **Ende-zu-Ende:** Start, Lernen, Smarttrainer, Prüfung und Nachweis (T01–T08) | Alle kritischen Teilaufgaben (★ in §5) gelöst | Desktop ≤ 60 min, mobil ≤ 70 min (ohne Lesezeit des Kursinhalts, siehe §2.4) | Kap. 15.2 Nr. 2 |

Wenn die Person die Prüfung nicht besteht, ist LE-T08 trotzdem erfüllbar: Gefragt ist das Ergebnis, nicht das Bestehen. Danach wird die Nachweisansicht an einem vorbereiteten Konto mit bestandener Prüfung gezeigt (LE-T08b).

**Mobil:** Mindestens 3 von 5 Lernenden ohne Hilfstechnik machen LE-T01…T08 auf einem Smartphone, dazu alle Personen mit VoiceOver iOS. LE-T06 enthält mobil einen echten Netzabbruch.

### 1.4 Referenzflow Gruppenverwalter (Flow 3, Kap. 15.2 Nr. 3, R1-35)

Startzustand: Ein Admin hat für RK-3 eine Gruppe mit 5 Plätzen angelegt. Die teilnehmende Person ist Besitzerin. Mail-Sink ist aktiv, und eine zweite Testperson (Moderation) tritt bei.

| ID | Aufgabe | Erfolgskriterium | Zielzeit Median | Bezug |
|---|---|---|---|---|
| GV-T01 | „Finde die Gruppe, die du verwaltest, und sag, wie viele Plätze frei sind.“ | Richtige Zahl genannt | 2 min | L-18 |
| GV-T02 | „Lade drei Personen per E-Mail ein.“ (Adressen auf Karte) | 3 Einladungen versendet | 4 min | L-18 |
| GV-T03 | „Gib einer weiteren Person einen Link zum Beitreten, der nur für ihre Adresse gilt.“ | Option „nur eingeladene Adresse“ gesetzt, Link kopiert | 3 min | L-18, E-28 |
| GV-T04 | Die Moderation tritt über den Link bei. „Prüfe, ob die Person dabei ist und wie viele Plätze noch frei sind.“ | Mitglied „aktiv“, Belegung richtig | 2 min | L-18 |
| GV-T05 | „Ein Link ist in falsche Hände geraten. Mach ihn ungültig.“ | Link erneuert. Der alte Link führt zur neutralen Meldung (F3.3) | 3 min | L-18, F3.3 |
| GV-T06 | „Eine Person hat das Team verlassen. Gib ihren Platz frei.“ | Mitglied entfernt. Die Person erklärt richtig, dass der Lernfortschritt erhalten bleibt (F3.9) | 3 min | L-18, F3.9 |
| GV-T07 | Eingeladene Person (Rollentausch): „Nimm die Einladung an.“ Ausgangslage: Gruppe voll (F3.1) bzw. Einladung abgelaufen (F3.2) | Die Person versteht die Meldung und nennt den nächsten Schritt | 2 min | L-17 |
| **GV-GES** | T01–T06 | alle gelöst | ≤ 15 min | – |

### 1.5 Referenzflow Admin-Setup (Flow 4, Kap. 16.1, R1-32, Kap. 15.2 Nr. 6)

Startzustand: frisches WordPress auf der Ziel-Zeile von R0-14 §3, Course Captain gerade aktiviert, Registrierung in WP ausgeschaltet. Dateischutz-Fehler erzwungen (nginx ohne Regel, F4.5).

| ID | Aufgabe | Erfolgskriterium | Zielzeit Median | Bezug |
|---|---|---|---|---|
| AD-T01 | „Richte das Lernsystem so ein, dass sich Mitarbeitende selbst für Kurse anmelden können. Die Kurse sollen unter /lernen/ liegen.“ | Assistent abgeschlossen, Modus Selbstanmeldung mit bestätigter Risiko-Abfrage (F4.3), Permalink gesetzt. Die Warnung zu F4.7 wurde verstanden (Nachfrage) | **≤ 10 min** | A-02 |
| AD-T02 | Abbruch in Schritt 4 (Moderation schließt den Tab). „Mach mit der Einrichtung weiter.“ | Wiedereinstieg bei Schritt 4, Werte vorbelegt (F4.6, A11Y-083) | 1 min | A-02 |
| AD-T03 | „Die Bereitschaftsprüfung meldet ein Problem. Finde heraus, was zu tun ist.“ | Die Person nennt die Ursache (Dateischutz) und die angebotene Abhilfe | 4 min | A-02 Schritt 7, F4.5 |
| AD-T04 | „Stelle ein, dass Prüfungsergebnisse erst nach der Abgabe Feedback zeigen, und mach eine zweite Änderung im anderen Tab rückgängig, ohne zu speichern.“ | Tab-Speichern erfolgt, die Warnung bei ungespeicherten Änderungen wurde verstanden | 5 min | A-15, A11Y-009 |
| AD-T05 | „Im System hängen Hintergrundaufgaben. Finde sie und lass sie erneut laufen.“ (vorbereiteter Queuefehler) | Job gefunden, Ursache gelesen, Retry ausgelöst, Ergebnis geprüft | 6 min | A-16 (Kap. 15.2 Nr. 6) |
| AD-T06 | „Lege eine Gruppe mit 5 Plätzen für den Onboarding-Kurs an und bestimme eine Besitzerin.“ | Gruppe = Soll | 4 min | A-10 (Flow 3, Start) |
| **AD-GES** | T01–T06 | alle gelöst | ≤ 30 min | – |

---

## 2. Testdesign

### 2.1 Rollen, Profile, Anzahl je Runde

| Rolle | Anzahl Runde 1 | Profil (Einschluss) | Ausschluss | Art |
|---|---|---|---|---|
| **Autor** | **6** (2 je Referenzkurs), davon SOLL 1 Person mit Tastatur-only oder Bildschirmlupe | 3 Personen mit Erfahrung als Kursautor/Trainer **ohne** WordPress-Kenntnisse über den Block-Editor hinaus. 3 Personen, die WordPress regelmäßig redaktionell nutzen. Keine LMS-Plugin-Erfahrung ist kein Ausschlussgrund | Beteiligte am Projekt, Personen mit Kenntnis der CC-Oberfläche | moderiert, remote oder vor Ort, laut denken, 120 min |
| **Lernende (ohne Hilfstechnik)** | **5** (3 mobil, 2 Desktop) | gemischte digitale Erfahrung (mindestens 1 Person mit geringer), Alter 18–65+, mindestens 1 Person 55+ | wie oben | moderiert, vor Ort oder remote, 75 min |
| **Lernende mit Hilfstechnik** | **4** | (a) blind, NVDA mit Firefox oder Chrome, Alltagsnutzung ≥ 1 Jahr. (b) blind oder hochgradig sehbehindert, VoiceOver iOS. (c) sehbehindert, Vergrößerung 200–400 % bzw. Bildschirmlupe, Kontrastmodus. (d) motorisch eingeschränkt, **nur Tastatur** (oder Schalter/Spracheingabe, falls verfügbar) | – | **moderiert**, eigenes Gerät mit eigener Konfiguration, 90 min plus Pausen |
| **Gruppenverwalter** | **5** | Personen, die im Alltag ein Team oder eine Lerngruppe organisieren (Ausbilder, Teamleitung, Vereinsvorstand) | – | 3 unmoderiert remote mit Aufgabenskript und Aufzeichnung, 2 moderiert. 30 min |
| **Admin (Setup)** | **5** | WordPress-Site-Betreiber, die Plugins selbst installieren (kein Entwicklerprofil Pflicht) | – | moderiert remote, 60 min |
| **Expertendurchläufe** (keine Nutzertests) | 1 A11Y-Fachperson | Durchläufe A11Y-102/103 nach Matrix §3 | Entwickler der geprüften Funktion | Checkliste, nicht laut denken |

**Begründung der Anzahl:** 5 je Rolle ist das übliche Minimum, um häufige Probleme qualitativ zu finden. Die Schwellen in §2.5 sind deshalb **Abnahmeregeln**, keine statistischen Aussagen über alle Nutzer. Bei den Hilfstechnik-Profilen zählt jede einzelne Person (§2.5 Regel A11Y). Runde 2 (Nachtest nach Behebung) prüft nur die betroffenen Aufgaben, mit **mindestens 3 neuen** Personen je betroffener Rolle und mindestens einer Person mit derselben Hilfstechnik wie beim Befund. Wer an Runde 1 teilgenommen hat, nimmt an Runde 2 nicht teil, außer zur Bestätigung eines A11Y-Befunds mit seltener Hilfstechnik.

**Vorab-Fragebogen** (5 min, pseudonym): Rolle, Erfahrung (WordPress, Kurse erstellen, E-Learning als Lernende), Gerät/Browser/Hilfstechnik mit Version, Selbsteinschätzung digitale Sicherheit (1–5). Keine Gesundheitsdaten außer „nutzt Hilfstechnik X“. Diese Angabe ist freiwillig und wird nur pseudonym gespeichert (§4.1).

### 2.2 Moderiert und unmoderiert

| Format | Einsatz | Regeln |
|---|---|---|
| **Moderiert, vor Ort** | Lernende mobil (echter Netzabbruch), Hilfstechnik nach Wunsch der Person | 1 Moderation, 1 Protokoll. Gerät der Person oder Testgerät nach §3 |
| **Moderiert, remote** | Autor, Admin, Lernende Desktop, Hilfstechnik nach Wunsch (Screenreader-Ton über die Konferenzsoftware teilen) | Bildschirm und Ton, Gesicht nur auf Wunsch. Die Moderation steuert Störfälle per Admin-Werkzeug (Sitzung ungültig, Queuefehler) |
| **Unmoderiert, remote** | Gruppenverwalter (3 von 5) | Testportal mit Aufgabenskript, Bildschirmaufzeichnung, SEQ nach jeder Aufgabe, SUS am Ende. Erfolg prüft ein Skript am Systemzustand. Keine Hinweise möglich, daher ist dort nur „Erfolg ohne Hilfe“ messbar |

Laut denken gilt in allen moderierten Sitzungen. **Ausnahme:** Bei Screenreader-Nutzenden ist das Laut-Denken freiwillig, weil es mit der Sprachausgabe konkurriert. Sie kommentieren nach jeder Aufgabe (retrospektiv). Zeitmessungen dieser Personen werden getrennt berichtet (§2.4).

### 2.3 Aufgabenskripte

Für jede Rolle gibt es ein Skript `docs/ux/skript-<rolle>.md` (Ablage in R1-29). Bausteine:

1. Begrüßung und Einwilligung (§4.1), Hinweis „Wir testen das System, nicht Sie“.
2. Vorab-Fragebogen.
3. Aufwärmaufgabe ohne Wertung (Autor: eine WordPress-Seite öffnen. Lernende: Startseite beschreiben).
4. Aufgaben nach §1 in fester Reihenfolge. Die Reihenfolge von AU-T04/T05 gegenüber T03 wird je Person rotiert, damit Lerneffekte über die Personen verteilt sind. Jede Aufgabe steht auf einer eigenen Karte (gedruckt, groß und als zugängliche Datei für Hilfstechnik-Nutzende). Die Karte nennt das Ziel, nicht den Weg.
5. Nach jeder Aufgabe: **SEQ** („Wie schwierig oder einfach war diese Aufgabe?“ 1 = sehr schwierig … 7 = sehr einfach).
6. Am Ende: **SUS** (10 Fragen, deutsche Fassung, unverändert), dann ein Nachgespräch mit 3 festen Fragen: Was war am schwierigsten? Was hat gefehlt? Würden Sie es so einsetzen? Bei Autoren zusätzlich die Verständnisfragen zu AU-T10, bei Gruppenverwaltern zu GV-T06.

**Hinweisstufen (verbindlich):** H0 = Aufgabe wiederholen. H1 = Bereich nennen („Schauen Sie in der Kursbearbeitung“). H2 = zeigen. Nach H2 gilt die Aufgabe als **nicht erfolgreich**. Die Moderation fährt fort, damit Folgeaufgaben möglich bleiben.
**Abbruch:** wenn die Person aufgibt oder bei der dreifachen Zielzeit (spätestens nach 20 min je Teilaufgabe). Bei Hilfstechnik gilt die vierfache Zielzeit.

### 2.4 Messgrößen

| Messgröße | Definition | Erhebung |
|---|---|---|
| **Erfolgsquote** | Erfolg = Erfolgskriterium (§1) erfüllt **ohne H1/H2**. „Erfolg mit Hilfe“ (H1) wird getrennt gezählt und gilt nicht als Erfolg für die Schwelle | Protokoll und Systemzustand (`check-rk`, Mail-Sink, DB-Abfrage) |
| **Zeit je Aufgabe** | Von „Karte gelesen“ bis Erfolgskriterium erfüllt oder Abbruch. Lesezeit für Kursinhalte (LE-T02/T03) wird abgezogen, sonst misst man die Länge des Textes | Zeitstempel im Protokoll, Aufzeichnung. Bericht als Median und Maximum, wegen n=5 kein Mittelwert |
| **Fehler** | **Bedienfehler** = falscher Pfad mit Korrektur durch die Person. **Kritischer Fehler** = Datenverlust, falscher veröffentlichter Zustand, unbemerkt falsche Annahme (z. B. „Prüfung abgegeben“, obwohl nicht abgegeben) | Protokoll |
| **SEQ** | 1–7 je Aufgabe | Karte bzw. Formular |
| **SUS** | 0–100 je Person, Mittelwert je Rolle | Fragebogen |
| **Hinweise** | Anzahl H0/H1/H2 | Protokoll |
| **A11Y-Befund** | Barriere mit WCAG-Kriterium und A11Y-ID aus R0-15 | Protokoll, Befundvorlage §4.3 |
| **Verständnis** | Richtige Antwort auf Verständnisfragen (AU-T10, GV-T06, LE-T08) | Nachgespräch |

### 2.5 Abnahmeschwellen (MUSS für R1-29)

| Nr. | Kriterium | Schwelle |
|---|---|---|
| S-01 | Erfolg je **Kernaufgabe** (alle T-Aufgaben in §1) über die Personen der Rolle ohne Hilfstechnik | **≥ 80 %** (bei n = 5: mindestens 4 von 5, bei n = 6: mindestens 5 von 6) |
| S-02 | Erfolg je **kritischer Aufgabe** ★ (§5) | ≥ 80 % **und 0 kritische Fehler** bei allen Personen |
| S-03 | Gesamterfolg je Rolle (alle Aufgaben) | ≥ 85 % |
| S-04 | **SUS** Mittelwert je Rolle | **≥ 70**, und höchstens 1 Person < 50 |
| S-05 | **SEQ** Median je Kernaufgabe | ≥ 5,0. Bei kritischen Lernenden-Aufgaben ≥ 5,5 |
| S-06 | **Autor veröffentlicht Referenzkurs** (AU-GES, Umfang §1.2) | **Median ≤ 90 min, keine Person > 120 min**. Jede veröffentlichte Version besteht `check-rk` |
| S-07 | Setup-Assistent (AD-T01) | Median ≤ 10 min |
| S-08 | Wiederaufnahme der Prüfung nach Abbruch (LE-T06) | Median ≤ 1 min bis zur nächsten offenen Frage. 0 verlorene Antworten |
| S-09 | Ende-zu-Ende Lernende (LE-GES) | Desktop ≤ 60 min, mobil ≤ 70 min (Median, ohne Lesezeit) |
| S-10 | Gruppenverwalter GV-GES / Admin AD-GES | ≤ 15 min bzw. ≤ 30 min Median |
| **S-A11Y** | **Hilfstechnik (Regel je Person):** Jede Person mit Hilfstechnik löst jede **kritische** Aufgabe ★ ihres Flows ohne sehende Hilfe (H2 ist nicht erlaubt, H1 nur zur Aufgabenklärung). Zeiten werden berichtet, gehen aber nicht in S-06…S-10 ein. SEQ-Median der Hilfstechnik-Gruppe ≥ 4,5 | Jede Verletzung ist ein Befund **S1** |
| S-EXP | Expertendurchläufe A11Y-102/103 für alle zugesicherten Kombinationen (§3, Stufe „Z“) | Keine offenen Befunde S1 |

Ist eine Schwelle verfehlt, entsteht ein Befund mit mindestens der Stufe nach §4.4. Die Schwellen S-06…S-10 sind vorläufig. Sie werden nach dem Praxis-Benchmark (UX-090) und dem Pilot (§4.2 Schritt 3) **einmal** überprüft und dann vor Runde 1 eingefroren (E-166; die dort genannten Werte – Erfolg ≥ 80 %, SUS ≥ 70, Referenzkurs ≤ 90 min Median, Setup ≤ 10 min, Fortsetzen ≤ 1 min – sind beschlossen und werden nicht gelockert). Danach dürfen sie nur der Auftraggeber ändern, mit Begründung im Bericht.

---

## 3. Testgeräte, Browser und Hilfstechnik (aus E-61, E-126, R0-14 §3, R0-15 §2.4)

**Stufen:** **Z** = zugesichert, ein Nutzertest **und** ein Expertendurchlauf sind Pflicht. **E** = zugesichert, ein Expertendurchlauf ist Pflicht, ein Nutzertest optional. **B** = best effort, ein Expertendurchlauf, Befunde höchstens S2. Versionen: „n“ ist die aktuelle, „n−1“ die vorige Hauptversion zum Testzeitpunkt. Die genaue Version steht im Protokoll.

### 3.1 Hilfstechnik-Kombinationen

| Nr. | Hilfstechnik | Browser | Gerät / OS | Stufe | Rollen / Flows | Nutzertest-Profil |
|---|---|---|---|---|---|---|
| AT-01 | NVDA (aktuell) | Firefox ESR | Windows 11 | **Z** | Lernende Flow 2 (A11Y-102) | (a) |
| AT-02 | NVDA (aktuell) | Chrome n | Windows 11 | **E** (+ Z für Autor, falls SOLL-Person vorhanden) | Autor Flow 1, Admin Flow 4 (A11Y-103), Lernende Flow 2 | (a) alternativ |
| AT-03 | VoiceOver macOS (aktuell) | Safari n | macOS n | **E** | Autor Flow 1, Admin Flow 4, Lernende Flow 2 | – |
| AT-04 | VoiceOver iOS | Safari | iPhone, iOS n und n−1 (je ein Durchlauf) | **Z** | Lernende Flow 2 inkl. Prüfung mit Zeitausgleich | (b) |
| AT-05 | TalkBack | Chrome n | Android (aktuell), mittleres Gerät | **B** | Lernende Flow 2 | – |
| AT-06 | nur Tastatur (ohne Screenreader) | Chrome n oder Edge n | Windows 11 | **Z** | Lernende Flow 2, Gruppenverwalter Flow 3. Autor Flow 1 SOLL | (d) |
| AT-07 | Vergrößerung 200 % Text / 400 % Zoom, Bildschirmlupe | Firefox n | Windows 11 (Lupe) oder macOS (Zoom) | **Z** | Lernende Flow 2 inkl. A11Y-051/053 | (c) |
| AT-08 | Windows-Kontrastdesign (`forced-colors`) | Edge n | Windows 11 | **E** | Lernende Flow 2, Autor A-04/A-07 (A11Y-043) | (c) optional |
| AT-09 | Bewegung reduzieren, Systemschrift groß | Safari | iPhone, iOS n | **E** | Lernende Flow 2 (A11Y-060) | – |
| AT-10 | JAWS | Chrome | Windows | **nicht zugesichert** (E-126) | nur informativ, wenn eine Person JAWS mitbringt | – |

### 3.2 Browser- und Gerätematrix ohne Hilfstechnik

Ziel: jede Zeile aus E-61 kommt in mindestens einer **Nutzersitzung** oder einem Expertendurchlauf je Rolle vor. Die Zuordnung zu Personen steht im Rekrutierungsplan, nicht hier.

| Nr. | Browser | Plattform | Rollen | Mindestens |
|---|---|---|---|---|
| BR-01 | Chrome n | Windows 11 | Autor, Admin, Lernende | 2 Autoren, 1 Admin |
| BR-02 | Edge n | Windows 11 | Autor, Admin | 1 Autor oder Admin |
| BR-03 | Firefox n | Windows oder Linux | Autor, Lernende | 1 Autor |
| BR-04 | Firefox ESR | Windows | Lernende | Expertendurchlauf Flow 2 |
| BR-05 | Safari n | macOS | Autor, Admin, Lernende | 1 Autor, 1 Admin |
| BR-06 | Version n−1 von Chrome, Edge, Firefox, Safari | wie oben | alle | Expertendurchlauf Flow 1 + 2 (Rauchtest) |
| BR-07 | iOS Safari, iOS n und n−1 | iPhone, 6,1" (390 × 844) und kleinstes unterstütztes Modell | Lernende, Gruppenverwalter | 2 Lernende, 1 GV |
| BR-08 | Android Chrome n | Android-Mittelklasse, 360 × 800, und ein Gerät mit 3–4 GB RAM | Lernende, Gruppenverwalter | 1 Lernende, 1 GV |
| BR-09 | Tablet (iPadOS Safari, Querformat und Hochformat) | iPad | Autor (Kurs ansehen und Vorschau), Lernende | Expertendurchlauf (A11Y-054) |

**Mobile Prüfungsläufe:** LE-T06 MUSS mindestens einmal auf BR-07 und einmal auf BR-08 mit echtem Netzabbruch laufen (R0-14 §3 „Prüfungsabläufe zusätzlich manuell auf einem echten iOS- und Android-Gerät“).
**Testgeräte des Projekts (Beschaffung aus dem Budget nach E-162):** 1 Windows-11-Notebook mit NVDA, Firefox ESR, Chrome und Edge. 1 MacBook (Safari, VoiceOver). 1 iPhone mit aktuellem iOS und 1 iPhone mit iOS n−1 (älteres Modell). 1 Android-Mittelklasse-Gerät. 1 iPad. Personen mit Hilfstechnik nutzen bevorzugt ihr **eigenes** Gerät, weil die persönliche Konfiguration (Sprechgeschwindigkeit, Gesten, Lupe) Teil des Alltags ist.

### 3.3 Testumgebung

- Staging nach der **Ziel-Zeile** von R0-14 §3: PHP 8.5, WP 7.1.x, MariaDB 12.3, Apache, Objekt-Cache aus, Block-Theme Twenty Twenty-Five. AD-T03 läuft zusätzlich auf nginx.
- Sprache de_DE, zusätzlich eine Admin-Sitzung in en_US (E-169), Zeitzone Europe/Berlin.
- Paket `cc-fx-rk-v1` mit idempotentem Loader (Muster R0-05 D-03), Reset-Skript je Sitzung (`tools/ux/reset-session <pNN>`, Vorschlag). Jede Person bekommt eine eigene, frisch zurückgesetzte Umgebung oder einen eigenen Kurs- und Kontonamensraum.
- Mail-Sink (keine echten Mails), Admin-Werkzeug für Störfälle (Sitzung ungültig machen, Queuefehler erzeugen, Netzabbruch simulieren, nur auf Staging).
- Keine Analyse- oder Fremd-Skripte (R0-14 FE-07). Aufzeichnung nur über die Konferenz- bzw. Testsoftware.

---

## 4. Ablauf, Protokoll, Befunde

### 4.1 Einwilligung und Datenschutz

- Schriftliche bzw. barrierefrei elektronische **Einwilligung** vor der Sitzung: Zweck, Aufzeichnung (Bildschirm und Ton, Gesicht nur auf Wunsch), Pseudonym `P-<Rolle>-<nn>`, Speicherort, Löschfrist, Widerruf jederzeit ohne Nachteil. Die Aufwandsentschädigung wird auch bei Abbruch gezahlt.
- Aufzeichnungen werden **6 Monate nach R1-GATE gelöscht** (E-167). Protokolle und Befunde bleiben pseudonym im Repo, **ohne Klarnamen, ohne Gesundheitsangaben** über „Hilfstechnik X“ hinaus.
- Testkonten enthalten keine echten Personendaten (Domain `example.test`).
- Bewertung durch R0-17 (Datenschutz), weil Angaben zur Hilfstechnik Rückschlüsse auf Gesundheit zulassen (Art. 9 DSGVO).

### 4.2 Ablauf

| Schritt | Inhalt | Ergebnis | Zeitpunkt (relativ) |
|---|---|---|---|
| 1 | Rekrutierung nach §2.1, Terminplan, Zuordnung zur Matrix §3 | Rekrutierungsplan (pseudonym) | Start, wenn R1-16/R1-19/R1-26 im Release-Kandidaten sind, ca. 6 Wochen vor dem geplanten R1-GATE |
| 2 | Testumgebung, Paket `cc-fx-rk-v1`, Skripte, `check-rk` | Umgebung abgenommen | −4 Wochen |
| 3 | **Praxis-Benchmark** (UX-090) und **Pilot** mit 1 internen, projektfremden Person je Rolle (wird nicht gewertet) | Skripte und Zielzeiten justiert, Schwellen eingefroren (§2.5) | −3 Wochen |
| 4 | Expertendurchläufe A11Y-102/103 (Matrix §3, Stufe Z/E/B) | S1-Funde vorher beheben, damit Nutzertests nicht an bekannten Blockern scheitern | −3 bis −2 Wochen |
| 5 | **Runde 1** Nutzertests | Protokolle, Rohdaten | −2 Wochen (ca. 25 Sitzungen, 2–3 Sitzungen am Tag) |
| 6 | Auswertung innerhalb von 2 Werktagen nach der letzten Sitzung, Befund-Triage mit Produkt, Entwicklung und A11Y-Fachperson | Befundliste mit Schweregrad, Schwellenbericht | −1 Woche |
| 7 | Behebung S1/S2, Regressionstest R1-18/R1-19 | Fix-Commits | – |
| 8 | **Runde 2** Nachtest nach §2.1 für betroffene Aufgaben | Nachweis der Behebung | vor R1-GATE |
| 9 | **Abnahmebericht R1-29**: Schwellen S-01…S-EXP mit Ist-Werten, Befundliste mit Status, offene akzeptierte S2 mit Begründung, Baselines für §7 | Freigabe durch den Auftraggeber | Eingang R1-GATE |

### 4.3 Protokollvorlage

**Sitzungskopf** (je Sitzung eine Datei `docs/ux/r1-29/P-<rolle>-<nn>.md`):

```markdown
# Sitzung P-<rolle>-<nn>
- Datum/Uhrzeit: <YYYY-MM-DD HH:MM> · Dauer: <min> · Runde: 1|2
- Format: moderiert vor Ort | moderiert remote | unmoderiert
- Moderation: <Kürzel> · Protokoll: <Kürzel>
- Build: <Plugin-Version, Commit> · Paket: cc-fx-rk-v1 (<Hash>) · Referenzkurs: RK-1|2|3
- Gerät/OS: <…> · Browser + Version: <…> · Hilfstechnik + Version + Einstellungen: <… | keine>
- Matrix-Zeile(n): <AT-xx, BR-xx>
- Profil (Vorab-Fragebogen): <Erfahrung, Selbsteinschätzung 1–5>
- Einwilligung: ja (Aufzeichnung: ja/nein)
```

**Aufgabentabelle:**

```markdown
| Aufgabe | Start | Ende | Zeit (min) | Ergebnis (E / EH / F / Abbr.) | Hinweise H0/H1/H2 | Bedienfehler | Krit. Fehler | SEQ | Beobachtung (wörtlich, kurz) | Befund-IDs |
|---|---|---|---|---|---|---|---|---|---|---|
| AU-T01 | 10:04 | 10:07 | 3,0 | E | 0/0/0 | 1 | 0 | 6 | „Wo speichere ich?“ | UXB-012 |
```

`E` = Erfolg ohne Hilfe, `EH` = Erfolg mit H1, `F` = nicht erfolgreich (inkl. H2), `Abbr.` = Abbruch.

**Sitzungsende:** SUS-Rohwerte (10 Antworten) und Score, Antworten im Nachgespräch, Prüfskript-Ergebnis (`check-rk`, Mail-Sink, Systemzustand).

**Befundvorlage** (eine Zeile in `docs/ux/r1-29/befunde.md` bzw. ein Issue mit Label `ux-r1-29`):

```markdown
### UXB-<nnn> · <Kurztitel>
- Schweregrad: S1 | S2 | S3 | S4 · Häufigkeit: <k> von <n> Personen (Rolle)
- Aufgabe/Testfall: <AU-T05 / UX-005> · Screen: <A-07> · Flow/Fehlerpfad: <Flow 1, F1.1>
- Umgebung: <AT-01 / BR-07>, Version(en)
- Beobachtung: <was passiert ist, Zitat>
- Erwartung: <Soll laut R0-11/R0-15/R0-22>
- WCAG/A11Y-ID: <2.4.11 / A11Y-006 | –>
- Belege: <Zeitmarke Aufzeichnung, Screenshot ohne Personendaten>
- Vorschlag: <…> · Zuständig: <Task R1-xx> · Status: offen | behoben (<Commit>) | Nachtest bestanden (<Sitzung>) | akzeptiert (<Entscheid>)
```

### 4.4 Schweregrade und Behebungspflicht

| Stufe | Definition (ein Kriterium genügt) | Behebungspflicht |
|---|---|---|
| **S1 blockierend** | Kritische Aufgabe ★ für mindestens 1 Person unlösbar (bei Hilfstechnik: S-A11Y verletzt). Kritischer Fehler (Datenverlust, falsches Ergebnis oder falscher veröffentlichter Zustand, falsch verstandene Abgabe). WCAG-2.2-A/AA-Verstoß in Flow 1, 2 oder 4 mit einer zugesicherten Kombination (Stufe Z/E). Schwelle S-02, S-06 oder S-08 verfehlt | **MUSS vor R1-GATE behoben und im Nachtest (Runde 2) bestätigt** sein. Keine Ausnahme durch Akzeptanz |
| **S2 schwer** | Kernaufgabe nur mit Hilfe oder großem Umweg lösbar bei ≥ 2 Personen. Schwelle S-01, S-03, S-04, S-05, S-07, S-09 oder S-10 verfehlt. WCAG-AA-Verstoß außerhalb der kritischen Flows. Befund nur mit Stufe-B-Kombination (TalkBack), wenn er dort eine kritische Aufgabe blockiert | **SOLL vor R1-GATE behoben** sein. Ausnahme nur durch dokumentierte Akzeptanz des Auftraggebers mit Workaround in der Doku, Zieltermin spätestens im ersten Patch-Release und Eintrag im Konformitätsbericht (E-125). **Nicht akzeptierbar** sind S2 mit WCAG-Bezug in Lernenden-Flows |
| **S3 mittel** | Verzögerung, Verwirrung oder Bedienfehler mit Selbstkorrektur bei ≥ 2 Personen. Einzelbefund ohne Datenrisiko. JAWS-Befund ohne Gegenstück in NVDA/VO | Backlog mit Zuordnung zu R1-Task oder R2. Muss im Bericht stehen |
| **S4 gering** | Kosmetik, Textvorschläge, Wünsche | Backlog, keine Frist |

**Regeln:**

- Ein Befund, den nur 1 Person hatte, wird nicht automatisch abgestuft. Maßgeblich sind Folge und Kriterium, nicht nur die Häufigkeit.
- Die Einstufung macht die Triage (Schritt 6) gemeinsam. Bei Uneinigkeit gilt die höhere Stufe, bis der Auftraggeber entscheidet.
- „Keine offenen Befunde blockierend“ aus R0-15 §5 bedeutet: **0 offene S1**.
- Jeder behobene S1/S2 bekommt einen automatisierten Regressionstest, wenn er automatisierbar ist (R1-18/R1-19), sonst einen Eintrag in der manuellen Checkliste A11Y-102/103.
- Funktionsfehler, die keine UX-Befunde sind (z. B. falsche Bewertung), gehen zusätzlich als Bug an den zuständigen R1-Task und gelten als S1.

---

## 5. Testfälle UX-xxx (Ziel-Task R1-29)

★ = kritische Aufgabe für S-02 und S-A11Y. Spalte „Matrix“ verweist auf §3. Alle Fälle haben das Ziel **R1-29**. Gleichzeitig betroffene Umsetzungstasks stehen in „Bezug“.

### 5.1 Autor

| ID | Prüfung | Messgröße / Schwelle | Matrix | Bezug |
|---|---|---|---|---|
| UX-001 | Kurs anlegen (AU-T01) | S-01 | BR-01…05 | A-03, A-04, R1-05 |
| UX-002 | Curriculum aufbauen und umsortieren, mit Maus oder Tastatur (AU-T02) | S-01. Mit AT-06 per Tastatur ohne H2 | BR-01…05, AT-06 | A-04, A11Y-008 |
| UX-003 | Medien mit Untertitel, externes Video, PDF (AU-T03) | S-01 | BR-01…05 | A-06, A11Y-090/093 |
| UX-004 | Fragen anlegen, drei Typen (AU-T04). Zweite und dritte Frage je ≤ 2 min Median | S-01, Zeit | BR-01…05 | A-07 |
| UX-005 | Sieben Fragen, davon eine mit Bild und Alt-Text und eine eines neuen Typs (AU-T05) | S-01. 100 % der Bilder haben Alt-Text oder `decorative`. Neuer Typ ohne Bewertungsfehler | BR-01…05 | A-07, A11Y-091, E-22, E-69 |
| UX-006 | Fragenimport mit Dry-run (AU-T06) | S-01. Die Person beschreibt den Dry-run richtig | BR-01…05 | A-13, R1-15 |
| UX-007 ★ | Prüfung konfigurieren (AU-T07). Bei RK-2 Blueprint und Teilgrenzen | S-02. 0 falsch gespeicherte Regeln | BR-01…05 | A-08, R1-08 |
| UX-008 | Zugang einstellen und Vorschau als Lernender (AU-T08) | S-01. Die Person erkennt den Vorschaurahmen („nichts wird gespeichert“) | BR-01…05 | R0-22 §2.3, R1-26 |
| UX-009 ★ | Veröffentlichen mit zwei eingebauten Blockern (AU-T09) | S-02. Beide Blocker gefunden und behoben. 0 Personen glauben, veröffentlicht zu haben, obwohl nicht | BR-01…05 | R0-22 §3, F1.1, R1-26 |
| UX-010 | Revisionshinweis verstanden (AU-T10) | ≥ 80 % richtige Erklärung | BR-01…05 | F1.3, R1-27 |
| UX-011 | Testnutzer einschreiben, Fortschritt prüfen (AU-T11) | S-01 | BR-01…05 | A-09, Kap. 15.2 Nr. 1 |
| UX-012 ★ | Autosave bei Sitzungs- oder Netzverlust (Störfall in AU-T05) | 0 verlorene Eingaben. Die Person arbeitet ohne H2 weiter | BR-01, BR-05 | F1.4, Z-O |
| UX-013 ★ | **Referenzkurs veröffentlicht** (AU-GES) | **S-06**: Median ≤ 90 min, Maximum ≤ 120 min, `check-rk` grün | alle Autoren | Kap. 27.4, 28.4 Nr. 5 |
| UX-014 | SUS und SEQ Autor | S-04, S-05 | – | – |
| UX-015 | Autorenflow mit Screenreader (Expertendurchlauf, A11Y-103) | S-EXP | AT-02, AT-03 | A11Y-031, A11Y-008 |
| UX-016 | Aufgabe anlegen und Bewertung einstellen (AU-T12) **(neu, ohne LB-Vorbild)** | S-01. 0 falsch gespeicherte Bewertungsregeln | BR-01…05 | R0-08 §2.5, R1-46, E-168 |
| UX-017 ★ | Abgaben bewerten und zurückgeben (AU-T13) **(neu, ohne LB-Vorbild)** | S-02. 0 Personen glauben, bewertet zu haben, obwohl die Abgabe noch `submitted` ist | BR-01…05, AT-06 | R0-08 §2.5, R1-46, E-168 |

### 5.2 Lernende

| ID | Prüfung | Messgröße / Schwelle | Matrix | Bezug |
|---|---|---|---|---|
| UX-020 ★ | Kurs finden und selbst einschreiben (LE-T01) | S-02 | BR-01, BR-05, BR-07, BR-08, AT-01, AT-04, AT-06, AT-07 | L-01…L-03 |
| UX-021 | Starten und erste Lektion abschließen (LE-T02) | S-01 | wie UX-020 | L-04…L-06, A11Y-021 |
| UX-022 | Video mit Untertitel, externes Video (LE-T03) | S-01 | wie UX-020 | A11Y-063/090/093, E-45a |
| UX-023 ★ | Smarttrainer: Fragen beantworten, Feedback verstehen (LE-T04) | S-02. Feedback richtig wiedergegeben. Mit Screenreader: Ansage „richtig/falsch“ verstanden (A11Y-024) | wie UX-020 | L-07, L-08, A11Y-022/024 |
| UX-024 | Fehlertraining (LE-T05) | S-01 | wie UX-020 | L-10 |
| UX-025 ★ | Prüfung starten, markieren, Timer verstehen (LE-T06, Teil 1) | S-02. Die Person nennt die Restzeit richtig. Mit Screenreader: Restzeit-Ansage abrufbar (A11Y-071) | wie UX-020 | L-12, L-13, A11Y-007/071 |
| UX-026 ★ | **Wiederaufnahme nach Abbruch** (Tab geschlossen, Flugmodus, App-Wechsel) und Abgabe mit Bestätigung (LE-T06, Teil 2) | **S-08**. 0 verlorene Antworten und Markierungen | BR-01, BR-05, **BR-07 und BR-08 mit echtem Netzabbruch**, AT-01, AT-04 | F2.5, Z-O, A11Y-025/074/075 |
| UX-027 | Fällige Wiederholung finden und bearbeiten (LE-T07) | S-01 | BR-01, BR-07, AT-01, AT-04 | L-05, L-08 |
| UX-028 ★ | Ergebnis verstehen und Nachweis mit Verifikationslink (LE-T08) | S-02. Bestanden/nicht bestanden und schwacher Teil richtig wiedergegeben | wie UX-020 | L-14…L-16, A11Y-030, E-96 |
| UX-029 | Eigene Daten exportieren (LE-T09) | S-01 | BR-01, BR-07 | L-20 |
| UX-030 ★ | Fortsetzen am nächsten Tag, mobil (LE-T10) | S-02. Median ≤ 1 min | BR-07, BR-08, AT-04 | L-04, L-05 |
| UX-031 | Gesperrte Inhalte verstehen (Teilumfang, Drip RK-3, Voraussetzung): „Warum kannst du Lektion 4 noch nicht öffnen?“ | ≥ 80 % nennen den Grund richtig | BR-01, BR-07, AT-01 | Z-G, F2.2/F2.3, A11Y-027 |
| UX-032 | Zeitablauf in der Prüfung (vorbereiteter Versuch mit 1 min Restzeit) | Die Person versteht die Auto-Abgabe-Meldung und findet das Ergebnis | BR-01, AT-01 | F2.7, A11Y-073, E-75 |
| UX-033 ★ | **Ende-zu-Ende Lernende** (LE-GES) | **S-09** | alle Lernenden | Kap. 15.2 Nr. 2, 28.4 Nr. 6 |
| UX-034 | SUS und SEQ Lernende | S-04, S-05 | – | – |
| UX-035 | Hoch- und Querformat, Wechsel während der Prüfung | Kein Zustandsverlust, kein Befund ≥ S2 | BR-07, BR-09 | A11Y-054 |
| UX-036 ★ | Aufgabe bearbeiten und einreichen, Text bzw. Datei (LE-T11) **(neu, ohne LB-Vorbild)** | S-02. 0 verlorene Entwürfe; Datei-Upload mit Tastatur und Screenreader bedienbar; Status „eingereicht“ richtig verstanden | BR-01, BR-05, BR-07, BR-08, AT-01, AT-04, AT-06 | R0-08 §2.5, R0-10 §4.5, R1-46 |
| UX-037 | Rückgabe überarbeiten, Bewertung lesen (LE-T12) **(neu, ohne LB-Vorbild)** | S-01. Punkte und Feedback richtig wiedergegeben | BR-01, BR-07, AT-01 | R0-08 §2.5, R1-46 |
| UX-038 ★ | Neue Fragetypen bedienen: Zuordnung und Sortieren per Maus, Touch **und** Tastatur, Lückentext, Zahl mit Dezimalkomma (in LE-T04) **(neu, ohne LB-Vorbild)** | S-02. Keine Person scheitert an der Bedienung (nur an der Fachfrage); mit Screenreader: Zuordnung und Reihenfolge werden angesagt | BR-01, BR-07, BR-08, AT-01, AT-04, AT-06 | R0-08 §3.1, E-22, E-69 |

### 5.3 Gruppenverwalter und Admin

| ID | Prüfung | Messgröße / Schwelle | Matrix | Bezug |
|---|---|---|---|---|
| UX-050 | Gruppe finden, freie Plätze nennen (GV-T01) | S-01 | BR-01, BR-07, BR-08 | L-18 |
| UX-051 ★ | Per E-Mail einladen, adressgebundener Link (GV-T02/T03) | S-02 | BR-01, BR-07, BR-08, AT-06 | L-18, E-28 |
| UX-052 | Belegung prüfen nach Beitritt (GV-T04) | S-01 | wie UX-050 | F3.1 |
| UX-053 ★ | Link ungültig machen (GV-T05) | S-02. Der alte Link ist nachweislich ungültig | wie UX-050 | F3.3 |
| UX-054 | Platz freigeben, Folgen verstanden (GV-T06) | S-01. ≥ 80 % richtige Erklärung | wie UX-050 | F3.9 |
| UX-055 | Meldungen „Gruppe voll“ und „Einladung abgelaufen“ verstanden (GV-T07) | ≥ 80 % nennen den nächsten Schritt | BR-07, AT-01 | L-17, F3.1/F3.2 |
| UX-056 | GV-GES, SUS, SEQ | S-10, S-04, S-05 | – | R1-35 |
| UX-060 ★ | Setup-Assistent mit Selbstanmeldung (AD-T01) | **S-07**, S-02 | BR-01, BR-02, BR-05 | A-02, F4.3/F4.7, R1-32 |
| UX-061 ★ | Setup nach Abbruch fortsetzen (AD-T02) | S-02. 0 verlorene Werte | wie UX-060 | F4.6, A11Y-083 |
| UX-062 | Bereitschaftsprüfung verstehen (AD-T03) | S-01 | nginx-Staging | F4.5 |
| UX-063 | Einstellungs-Tabs, ungespeicherte Änderungen (AD-T04) | S-01 | wie UX-060 | A-15, A11Y-009 |
| UX-064 | Queuefehler finden, Retry (AD-T05) | S-01 | wie UX-060 | A-16, Kap. 15.2 Nr. 6 |
| UX-065 | Gruppe anlegen (AD-T06) | S-01 | wie UX-060 | A-10 |
| UX-066 | AD-GES, SUS, SEQ Admin | S-10, S-04, S-05 | – | – |
| UX-067 | Setup und Einstellungen mit Screenreader (Expertendurchlauf, A11Y-103) | S-EXP | AT-02, AT-03 | R0-30 |

### 5.4 Hilfstechnik und Expertendurchläufe

| ID | Prüfung | Messgröße / Schwelle | Matrix | Bezug |
|---|---|---|---|---|
| UX-070 ★ | Flow 2 vollständig mit NVDA (Nutzertest, Profil a) | **S-A11Y** | AT-01 (oder AT-02) | A11Y-102, A11Y-104 |
| UX-071 ★ | Flow 2 vollständig mit VoiceOver iOS (Nutzertest, Profil b), inkl. Wiederaufnahme | S-A11Y | AT-04 | A11Y-102 |
| UX-072 ★ | Flow 2 mit Vergrößerung 200/400 % (Nutzertest, Profil c) | S-A11Y. Timer und Abgabe bleiben erreichbar | AT-07 | A11Y-050/051/053 |
| UX-073 ★ | Flow 2 und Flow 3 nur mit Tastatur (Nutzertest, Profil d) | S-A11Y. Fokus immer sichtbar, nie verdeckt (Beobachtung) | AT-06 | A11Y-001…007 |
| UX-074 | Flow 1 Autor mit Tastatur oder Lupe (Nutzertest, SOLL-Person) | S-01 für diese Person. Ein Verfehlen ist ein Befund mindestens S2 | AT-06 oder AT-07 | A11Y-008, A11Y-031 |
| UX-075 ★ | Prüfung mit Zeitausgleich 150 % (Konto vorbereitet): Anzeige „inkl. Zeitausgleich“ verstanden, Ansagen 10/5/1 min | S-A11Y. Restzeit richtig verstanden | AT-01, AT-04 | §2.3 R0-15, A11Y-070/071, E-122 |
| UX-076 | Expertendurchlauf Flow 2 in allen Stufen Z/E/B (AT-01…AT-09) | S-EXP. Protokoll je Kombination | AT-01…09 | A11Y-102 |
| UX-077 | Expertendurchlauf Flow 1 und 4 (AT-02, AT-03) | S-EXP | AT-02, AT-03 | A11Y-103 |
| UX-078 | E-Mails (Zugang freigeschaltet, Einladung, Nachweis) mit Screenreader in einem Mailprogramm lesen | Kein Befund ≥ S2 | AT-01, AT-04 | A11Y-095 |
| UX-079 | Druckansicht Nachweis (Expertendurchlauf) | Kein Befund ≥ S2 | BR-01, BR-05 | A11Y-096 |
| UX-080 | Expertendurchlauf: externe Medien im Modus „Klick zum Laden“ (Einstellung umgestellt): Platzhalter, Dienstname, Tastaturbedienung, kein Request vor dem Klick | Kein Befund ≥ S2 | AT-01, AT-04, AT-06 | E-45a, R0-10 §5 |
| UX-081 | Expertendurchlauf: Datei-Upload der Aufgabe und Bewertungsansicht mit NVDA und VoiceOver | S-EXP | AT-01…04 | R1-46, A11Y-102/103 |

### 5.5 Benchmark und Bericht

| ID | Prüfung | Messgröße / Schwelle | Matrix | Bezug |
|---|---|---|---|---|
| UX-090 | **Praxis-Benchmark** (TODO): Eine projektinterne Fachperson, die nicht an der Entwicklung beteiligt ist, erstellt **alle drei** Referenzkurse, darunter **einen vollständig mit 50 manuell angelegten Fragen** ohne Import. Zeit je Abschnitt, Fehler und Validator-Meldungen werden protokolliert | Baseline für S-06 (keine Schwelle). Bericht mit Zeiten je AU-Teilaufgabe | BR-01 | Kap. 27.4, TODO „Praxis-Benchmark“ |
| UX-091 | Abnahmebericht mit allen Schwellen, Befundstatus und Baselines | 0 offene S1. S2 behoben oder akzeptiert nach §4.4 | – | R1-29 → R1-GATE |

---

## 6. Übergaben

| Nachweis | Task | Kriterium |
|---|---|---|
| Paket `cc-fx-rk-v1` (3 Referenzkurse mit je einer Aufgabe, Drehbücher, Teilpakete `rk<n>-q40`, `rk<n>-soll.json`, vorbereitete Abgaben) | R1-29 (Erstellung), R1-15 (Importierbarkeit) | Loader idempotent, CC0, `check-rk` grün gegen den Referenzstand |
| Skripte und Werkzeuge `tools/ux/check-rk`, `tools/ux/reset-session`, Störfall-Werkzeug (nur Staging) | R1-29 | Lauffähig in der Testumgebung §3.3 |
| Delta Paketformat: Import nur von Fragen in einen bestehenden Kurs | R0-10 | ja/nein dokumentiert, sonst Ersatzweg §1.1 |
| Automatisierte Gegenstücke zu UX-002, UX-012, UX-026, UX-061 (Störfälle) | R1-18, R1-19 | E2E grün (NFR-016) |
| Datenschutzbewertung der Nutzertests | R0-17 | §4.1 bewertet |
| Aufgabe in den Referenzkursen (statt `RK-v1.1`) | R1-29, R1-46 | nach E-168 in `cc-fx-rk-v1` enthalten |
| Wiederholung der Tests | R2-GATE, R3-GATE | gezielt für geänderte Flows (E-170) |

---

## 7. Bezug zum Messkonzept (Kap. 23.3, 28.6)

Nutzertests messen im Labor. Die Werte aus Runde 1 und 2 sind **Baselines**, keine Produktkennzahlen. Für spätere lokale und datensparsame Messung (Kap. 23.3) liefert R1-29:

| Kennzahl Messkonzept | Baseline aus | Messmethode im Test |
|---|---|---|
| Zeit bis zur Kursveröffentlichung | UX-013, UX-090 | Median/Maximum AU-GES, Benchmark je Abschnitt |
| Quote erfolgreicher Kurs-Erstellungen | UX-013 | Anteil `check-rk` grün |
| Anteil erfolgreich abgeschlossener Setup-Assistenten | UX-060/061 | Erfolgsquote AD-T01/T02 |
| Kursabschluss / Abschlussquote Referenzkurs | UX-033 | Erfolgsquote LE-GES |
| Fehlerquote bei Prüfungsabgabe | UX-026, UX-032 | kritische Fehler je Abgabe |
| Lernsession-Fortsetzungsrate | UX-030 | Erfolg und Zeit LE-T10 |
| Job-Wiederherstellung | UX-064 | Erfolgsquote AD-T05 |
| Supportlast | alle | Anzahl H1/H2 je Aufgabe als Näherung für „ohne Supportkontakt“ |

Konversion Free→Pro wird hier nicht gemessen. Es werden **keine** erfundenen Zielerreichungen berichtet (Kap. 28.6). Fehlende Werte stehen im Bericht als „nicht gemessen“.

---

## 8. Entscheidungsbedarf (beschlossen)

Alle Punkte sind entschieden (`ENTSCHEIDUNGEN.md` Abschnitt 25; E-161, E-162, E-168 im Beschlussprotokoll, die übrigen „Von Claude entschieden“):

| Früher | Beschluss | Inhalt | Wirkung hier |
|---|---|---|---|
| D-26-01 | **E-161** | Rekrutierung gemischt: eigene Kontakte für Autoren und Admins, Dienstleister bzw. Verbände für Personen mit Hilfstechnik | R1-29 Schritt 1; Personen aus dem Kundenkreis dürfen die CC-Oberfläche nicht vorab kennen (§2.1) |
| D-26-02 | **E-162** | Budget für Nutzertests kommt; den Betrag nennt der Auftraggeber vor R1-29 | Mengengerüst bleibt: Runde 1 mit 25 Sitzungen, Runde 2 ca. 10–12, Pilot 4, Expertendurchläufe ca. 3 Personentage, Testgeräte §3.2 |
| D-26-03 | **E-163** | 6 Autoren, 5 + 4 Lernende (davon 4 mit Hilfstechnik), 5 Gruppenverwalter, 5 Admins; Nachtest mit mindestens 3 neuen Personen | §2.1 |
| D-26-04 | **E-164** | Moderation durch eine Person, die nicht an der Entwicklung beteiligt ist; extern für die Barrierefreiheitssitzungen | §4.2 |
| D-26-05 | **E-165** | 10 Fragen manuell, 40 per Import; alle 50 manuell nur im Praxis-Benchmark | §1.2, UX-013, UX-090 |
| D-26-06 | **E-166** | Erfolg ≥ 80 % je Aufgabe, SUS ≥ 70, Referenzkurs veröffentlichen Median ≤ 90 min, Setup ≤ 10 min, Prüfung fortsetzen ≤ 1 min ohne Antwortverlust; jede Person mit Hilfstechnik löst jede kritische Aufgabe ohne sehende Hilfe | §2.5 |
| D-26-07 | **E-167** | Aufzeichnung mit Einwilligung, Löschung 6 Monate nach R1-GATE | §4.1 |
| D-26-08 | **E-168** (Auftraggeber, **abweichend** von der Empfehlung a) | Text-/Dateiaufgaben mit Bewertung in R1 (R1-46); Referenzkurs mit Aufgabe schon in den R1-Nutzertests | §0.2 Nr. 1, §1.1, AU-T12/T13, LE-T11/T12, UX-016/017/036/037/081 |
| D-26-09 | **E-169** | de_DE plus eine Admin-Sitzung in en_US | §3.3 |
| D-26-10 | **E-170** | Wiederholung gezielt für geänderte Flows vor R2-GATE und R3-GATE | §6 |
| D-26-11 | **E-171** | S2-Befunde vor R1-GATE nur mit dokumentierter Akzeptanz des Auftraggebers, nie bei WCAG-Bezug in Lernenden-Flows; S1 immer beheben | §4.4 |

### 8.1 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-26-1 | Die Autorensitzung (120 min) enthält jetzt zusätzlich AU-T12 (Aufgabe) und AU-T13 (Bewertung); die beschlossene Schwelle „Referenzkurs ≤ 90 min Median“ (E-166) gilt für AU-GES einschließlich AU-T12 | Ob 120 min Sitzungsdauer reichen, zeigt erst der Pilot; E-166 ist unveränderlich, die Sitzungsdauer nicht beschlossen | Pilot (§4.2 Schritt 3), Auftraggeber → **Auftraggeber-Aktion (kein Blocker für R0, aber für Gate B (R1-29))**: Pilot nach §4.2 Schritt 3, Sitzungsdauer bestätigen, AG-12 |
| OP-26-2 | Budgetbetrag (E-162) | nennt der Auftraggeber vor R1-29 | Auftraggeber → **Auftraggeber-Aktion (kein Blocker für R0, aber für Gate B (R1-29))**: Budgetbetrag nennen (E-162) vor R1-29, AG-06 |
| OP-26-3 | Wer bewertet in den Tests (Kursmanager oder Trainer)? AU-T13 nutzt die Rolle Course Manager | Bewertungsrecht in R1 nicht abschließend geregelt (R0-08 OP-08-1) | Auftraggeber / R0-07 → **erledigt durch E-288** (Kursmanager darf bewerten; AU-T13 bleibt gültig) |
| OP-26-4 | Typmix und Aufgabenkonfiguration der Referenzkurse (§1.1) sind an E-22/E-69/E-168 angepasste Vorschläge | keine Vorgabe in den Beschlüssen | Produkt (vor Erstellung von `cc-fx-rk-v1`) → **Auftraggeber-Aktion (kein Blocker für R0, aber für Gate B (R1-29))**: Typmix der Referenzkurse vor Erstellung von `cc-fx-rk-v1` bestätigen, AG-12 |

---

## 9. Nachweis dieser Arbeit

- Gelesen: TODO R0-26, R1-16, R1-18, R1-19, R1-26, R1-29, R1-32, R1-35, R1-GATE sowie die Punkte „Praxis-Benchmark“, „Drei neutrale Autorentests“, „Lernenden-E2E und Accessibility“ und „Messkonzept erstellen“. Konzept Kap. 15.2, 16.1, 16.3, 23.3, 27.4, 28.4, 28.6. R0-11 §2 und §3 (Flows 1–4 mit Fehlerpfaden). R0-15 §2.1–2.4 und §5–6. R0-14 §3 und NFR-014/-016. R0-05 §2 (`cc-fixture-neutral-v1`). R0-22 §2.3 und Gliederung §3. `ENTSCHEIDUNGEN.md` E-04, E-11, E-12, E-17, E-28, E-45, E-61, E-72, E-75, E-96, E-102, E-121…E-127.
- Nicht geprüft: ob das Paketformat R0-10 einen Import nur von Fragen unterstützt (Delta §6). Es gibt keine Marktpreise für die Aufwandsentschädigung. Es gibt keine Messwerte, weil noch keine Software vorhanden ist.
