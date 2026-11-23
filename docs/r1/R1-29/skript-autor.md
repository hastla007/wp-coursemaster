# R1-29 · Aufgabenskript Autor (Flow 1, R0-26 §1.2)

**Sitzung:** 120 min, moderiert (remote oder vor Ort), laut denken. Profil: `autor` (eigene Site je Person). Rolle: Kursmanager `cc_fx_ux_autor`. Referenzkurs je Person nach Rekrutierungsplan (2 Personen je RK). Material: Teilnehmenden-Drehbuch `drehbuch-rk<n>.md` (gedruckt groß und als zugängliche Datei), Medienordner, `rk<n>-q40.zip`. Die Moderation hat `drehbuch-rk<n>-moderation.md`.

**Startzustand (vor jeder Sitzung prüfen):** Course Captain Free aktiv, Setup abgeschlossen, kein Kurs vorhanden (Z-L in A-03), angemeldet als `cc_fx_ux_autor` im Browser der Person, Drehbuch und Medienordner liegen bereit. Neu aufbauen: `tools/usability/setup-site.sh --profil autor --site … --db ccap_usab_autor_pNN`.

**Bereitschaft (Stand 6f6f05f):** AU-T04/T05/T06/T07/T10/T11/T13 sind **blockiert**, AU-T01/T02/T03/T08/T09/T12 nur **teilweise** lösbar (`bereitschaft.md`). Für den Pilot nur AU-T01, AU-T02, AU-T09 (ohne Alt-Text-Blocker) einsetzen; die volle Sitzung erst nach Freigabe der Bereitschaft.

**Karten:** Jede Aufgabe auf einer eigenen Karte; die Karte nennt das Ziel, nicht den Weg, und keine Oberflächenbegriffe. Wortlaut unten unter „Karte“. Abbruchgrenze = 3 × Zielzeit (max. 20 min), mit Hilfstechnik 4 × Zielzeit.

**Rotation:** Ungerade Pseudonym-Nummer: AU-T03 vor AU-T04/T05; gerade: AU-T04/T05 vor AU-T03.

---

### AU-T01 · Kurs anlegen (UX-001)
- **Karte:** „Lege den Kurs aus dem Drehbuch mit Titel, Beschreibung und Lernzielen an.“
- **Start:** Dashboard, kein Kurs.
- **Erfolg:** Kurs als Entwurf mit Titel, Beschreibung und Lernzielen (Text laut Drehbuch §1).
- **Zeit:** Ziel 4 min · Abbruch 12 min (AT 16 min).
- **Beobachten:** Findet die Person den Menüpunkt? Wo sucht sie ein Feld für Lernziele (es gibt keins)? Speichert sie als Entwurf oder veröffentlicht sie aus Versehen?
- **Systemprüfung:** Kurs im Status `draft` vorhanden, Inhalt enthält die drei Lernziele.

### AU-T02 · Module und Lektionen aufbauen, eine Lektion verschieben (UX-002, A11Y-008)
- **Karte:** „Baue die Module und 10 Lektionen in der Reihenfolge des Drehbuchs auf.“ Danach Karte **AU-T02b:** „Die Lektion an Position 1 von Modul 1 gehört ans Ende des Moduls. Verschiebe sie.“
- **Start:** Kurs aus AU-T01.
- **Erfolg:** Struktur = Soll (`rk<n>-soll.json`: Module, Lektionen je Modul, Reihenfolge). Verschieben per Maus **oder** Tastatur (die Person wählt).
- **Zeit:** Ziel 15 min · Abbruch 20 min (AT 60 min).
- **Beobachten:** Wie entsteht die Zuordnung Lektion → Modul? Versteht die Person Positionszahlen? Tastaturweg beim Verschieben (AT-06): Fokus sichtbar, Ansage der neuen Position?
- **Systemprüfung:** `check-rk` Zeilen „Modul n Lektionen/Activity-Typen“ grün (Typen erst nach AU-T03/T12 vollständig).

### AU-T03 · Medien einbinden (UX-003, A11Y-090/093)
- **Karte:** „Füge das Video mit Untertiteln, das externe Video und die PDF-Datei ein.“
- **Start:** Lektionen aus AU-T02; Medienordner mit `rk<n>-video.mp4`, `rk<n>-video.vtt`, PDF; Adresse des externen Videos auf dem Moderationsblatt (`CCAP_UX_EXTERNAL_VIDEO_URL`).
- **Erfolg:** Untertitelspur zugeordnet, externes Video eingebunden, PDF verknüpft.
- **Zeit:** Ziel 8 min · Abbruch 20 min (AT 32 min).
- **Beobachten:** Hochladen in die Mediathek, Zuordnung zur Lektion; Verständnis von „intern“/„extern“; heute nur JSON-Einstellungen (teilweise) – Abbruch dokumentieren, nicht helfen.
- **Systemprüfung:** `check-rk` „Interne Videos mit WebVTT“ = true.

### AU-T04 · Drei Fragen anlegen (UX-004)
- **Karte:** „Lege diese drei Fragen an: eine mit einer richtigen Antwort, eine mit mehreren, eine Wahr/Falsch-Frage.“ (Drehbuch §3, Nr. 1–3)
- **Erfolg:** 3 Fragen, Korrektheit = Soll.
- **Zeit:** Ziel 8 min (Frage 2 und 3 je ≤ 2 min, Zeit je Frage notieren) · Abbruch 20 min (AT 32 min).
- **Beobachten:** Markieren der richtigen Antwort(en), Unterschied „eine/mehrere richtig“, Zuordnung zum Modul.
- **Systemprüfung:** 3 veröffentlichte Fragen im Pool, richtige Antworten wie Drehbuch.

### AU-T05 · Sieben Fragen inkl. Bild und neuem Typ (UX-005, A11Y-091/110, E-22, E-69)
- **Karte:** „Lege die übrigen sieben Fragen aus dem Drehbuch an, eine davon mit Bild.“ (Drehbuch §3, Nr. 4–10; RK-1 enthält eine Zuordnung, RK-2 eine Zahl mit erlaubter Abweichung und Einheit, RK-3 keinen neuen Typ.)
- **Erfolg:** 7 Fragen = Soll; das Bild hat einen Alt-Text oder ist als dekorativ markiert; die Frage des neuen Typs ist richtig bewertet (Zuordnungen bzw. Sollwert, Toleranz, Einheit).
- **Zeit:** Ziel 14 min · Abbruch 20 min (AT 56 min).
- **Beobachten:** Wird der Alt-Text ohne Aufforderung gesetzt? Bei RK-3 nennt das Teilnehmenden-Drehbuch absichtlich keinen Alt-Text – fragt die Person nach? Bedienung Zuordnung/Toleranz.
- **Störfall UX-012 (Pflicht, einmal je Person):** siehe unten.
- **Systemprüfung:** `check-rk` Fragen je Typ (nach AU-T06), Bild mit Alt-Text.

### AU-T06 · 40 Fragen importieren (UX-006, A-13, F1.6)
- **Karte:** „Übernimm die restlichen 40 Fragen aus der bereitgestellten Datei.“
- **Start:** `rk<n>-q40.zip` im Medienordner. Hinweis Moderation: Das Paket enthält ersatzweise einen Kurs ohne Lektionen (kein Import nur von Fragen im Format v1, Delta R0-10).
- **Erfolg:** Dry-run gesehen, Freigabe erteilt, 50 Fragen im Kurs.
- **Zeit:** Ziel 6 min · Abbruch 18 min (AT 24 min).
- **Beobachten:** Versteht die Person den Dry-run („was würde passiert“)? Bemerkt sie, dass ein zweiter Kurs entsteht? Nachfrage: „Was hat der Probelauf gezeigt?“
- **Systemprüfung:** Import-Batch `published`; Fragenanzahl je Modul.

### AU-T07 · Prüfung einrichten ★ (UX-007, A-08)
- **Karte:** „Richte die Prüfung nach dem Drehbuch ein.“
- **Erfolg:** Prüfungsparameter = Soll; bei RK-2 Blueprint 8/8/7/7, Teilgrenzen 60 %, 45 min, 3 Versuche. **0 falsch gespeicherte Regeln** (S-02).
- **Zeit:** Ziel 10 min · Abbruch 20 min (AT 40 min).
- **Beobachten:** Bruch/Prozent bei der Bestehensgrenze, Teilgrenzen, Zeitlimit; bei RK-2 übernimmt die Person den eingebauten Blueprint-Fehler (Modul 4: 13) – erwartet, der Fehler fällt erst in AU-T09 auf.
- **Systemprüfung:** `check-rk` Zeilen „Prüfung …“ (nach AU-T09 grün).

### AU-T08 · Zugang einstellen, Vorschau ansehen (UX-008, R0-22 §2.3)
- **Karte:** „Stelle ein, wer den Kurs nutzen darf, und schau ihn dir an, wie Lernende ihn sehen.“
- **Erfolg:** Zugangsmodus = Soll (Drehbuch §6); Vorschau in mindestens einer Persona geöffnet; die Person erkennt den Vorschaurahmen („nichts wird gespeichert“).
- **Zeit:** Ziel 6 min · Abbruch 18 min (AT 24 min).
- **Beobachten:** Begriffe Selbstanmeldung/Teilumfang/Freischaltung; Vorschau fehlt heute (teilweise).
- **Systemprüfung:** `check-rk` „Zugangsmodus“, „Teilumfang“ bzw. „Drip“.

### AU-T09 · Veröffentlichen mit zwei eingebauten Fehlern ★ (UX-009, R0-22 §3, F1.1, A11Y-031)
- **Karte:** „Veröffentliche den Kurs.“
- **Eingebaute Fehler:** (1) Bild ohne Alt-Text (RK-1/RK-2 aus dem Importpaket, RK-3 aus AU-T05); (2) nur RK-2: Blueprint Modul 4 verlangt 13 Fragen, Pool hat 12.
- **Erfolg:** Die Person findet beide Blocker über das Validierungspanel, behebt sie und veröffentlicht. **0 Personen glauben, veröffentlicht zu haben, obwohl nicht** (S-02).
- **Zeit:** Ziel 8 min · Abbruch 20 min (AT 32 min).
- **Beobachten:** Liest die Person das Panel? Springt sie zum Fehler? Nachfrage: „Ist der Kurs jetzt für Lernende sichtbar?“ Heute erkennt der Validator den Alt-Text-Fehler nicht (B-05) – Beobachtung trotzdem protokollieren.
- **Systemprüfung:** Kursstatus `publish`, `check-rk rk<n> --kurs=<ID>` vollständig grün → AU-GES.

### AU-T10 · Veröffentlichte Frage ändern (UX-010, F1.3)
- **Karte:** „Ändere im veröffentlichten Kurs eine Frage.“ (beliebige Frage, z. B. Tippfehler korrigieren)
- **Erfolg:** Revisionshinweis gesehen; im Nachgespräch richtige Erklärung, was mit laufenden Versuchen passiert (≥ 80 % richtig).
- **Zeit:** Ziel 4 min · Abbruch 12 min (AT 16 min).
- **Systemprüfung:** neue Fragenrevision vorhanden, alte unverändert.

### AU-T11 · Testnutzer einschreiben, Fortschritt prüfen (UX-011, A-09, E-27)
- **Karte:** „Schreibe einen Testnutzer ein und prüfe dessen Fortschritt.“ RK-2: „… nur für die Module 1 und 2.“
- **Start:** Testnutzer `cc_fx_ux_beitritt` bzw. ein zusätzliches Konto, das die Moderation vorher im Profil `autor` anlegt (Admin › Benutzer, Rolle Abonnent).
- **Erfolg:** Zugang aktiv (bei RK-2 genau für Modul 1 und 2), Fortschrittsansicht geöffnet.
- **Zeit:** Ziel 5 min · Abbruch 15 min (AT 20 min).
- **Systemprüfung:** Grant `active`, bei RK-2 zwei Modul-Grants.

### AU-T12 · Aufgabe anlegen und Bewertung einstellen (UX-016, R1-46, neu ohne LB-Vorbild)
- **Karte:** „Lege die Aufgabe aus dem Drehbuch an und stelle ein, wie sie bewertet wird.“
- **Erfolg:** Aufgabenstellung, Abgabeart, Punkte, Bestehensgrenze, Abschlussregel = Soll (Drehbuch §5); RK-2 Dateityp PDF. 0 falsch gespeicherte Bewertungsregeln.
- **Zeit:** Ziel 6 min · Abbruch 18 min (AT 24 min).
- **Systemprüfung:** `check-rk` Zeilen „Aufgabe …“.

### AU-T13 · Abgaben bewerten und zurückgeben ★ (UX-017, R1-46, neu ohne LB-Vorbild)
- **Ort:** Referenz-Site (Profil `referenz`), Konto `cc_fx_ux_kursmanager` (E-288). Die Moderation wechselt die Site vor der Aufgabe.
- **Karte:** „Drei Abgaben warten auf dich. Bewerte zwei und gib eine mit einem Hinweis zur Überarbeitung zurück.“ (Kurs des eigenen RK)
- **Vorbereitet:** `cc_fx_ux_abgabe1` (gut), `abgabe2` (unvollständig), `abgabe3` (mit Datei) – je Kurs im Status `submitted`.
- **Erfolg:** 2 Abgaben `graded` mit Punkten = Soll ± 0, 1 Abgabe `returned` mit Kommentar; die Person erklärt richtig, was die lernende Person danach sieht. **0 Personen glauben, bewertet zu haben, obwohl die Abgabe noch `submitted` ist** (S-02).
- **Soll-Punkte** (nach dem Bewertungshinweis im Drehbuch §5; die Moderation gibt der Person den Bewertungshinweis, nicht die Punkte):

  | Abgabe | RK-1 (max. 10, Text) | RK-2 (max. 20, PDF) | RK-3 (max. 10, Text + PDF) |
  |---|---|---|---|
  | `abgabe1` „gut“ | 10 | 20 (`abgabe-gut.pdf`) | 10 |
  | `abgabe3` „mit Datei“ / „mit Lücken“ | 8 (Matrix vollständig, Puffer nur pauschal) | 15 (Diagrammart 5, Titel/Achsen 0, Summe 5, Mittelwert 5) | 8 |
  | `abgabe2` „unvollständig“ | zurückgeben | zurückgeben (`abgabe-unvollstaendig.pdf`) | zurückgeben |

  Eine andere, begründete Aufteilung (z. B. `abgabe3` zurückgeben) ist kein Fehler, solange Punkte und Rückgabe zur Begründung passen; die Moderation notiert die Begründung. Soll-Werte vor dem Pilot mit der Fachperson bestätigen.
- **Zeit:** Ziel 8 min · Abbruch 20 min (AT 32 min).
- **Systemprüfung:** Status der drei Abgaben (Admin/REST `GET /assignment-submissions/{uid}`); Mail-Sink enthält N-15/N-16 nach dem nächsten Cron-Lauf.

### AU-GES · Referenzkurs veröffentlicht ★ (UX-013, S-06)
- **Umfang:** AU-T01 … AU-T09 und AU-T12, Summe der Aufgabenzeiten.
- **Erfolg:** `check-rk` alle Pflichtmerkmale grün. **Median ≤ 90 min, keine Person > 120 min.**

---

### Störfall UX-012 · Sitzungs- oder Netzverlust während AU-T05 ★ (F1.4)
- **Auslösen:** Während die Person die 5. oder 6. Frage tippt (Text eingegeben, noch nicht gespeichert): `scenario sitzungen-beenden --konto=cc_fx_ux_autor` **oder** Netz trennen (remote: Person bitten, WLAN 30 s auszuschalten; vor Ort: Router-Port/Flugmodus).
- **Erwartet:** kein Text geht verloren, die Person arbeitet ohne H2 weiter (nach erneuter Anmeldung).
- **Beobachten:** Meldung verständlich? Wird der Entwurf wiederhergestellt? Wie lange bis weitergearbeitet wird?
- **Wertung:** verlorene Eingabe = kritischer Fehler (S1).

## Nachgespräch Autor

Drei feste Fragen (Leitfaden §4.3) plus Verständnisfragen zu AU-T10 und AU-T13. Danach SUS.
