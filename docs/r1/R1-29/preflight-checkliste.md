# R1-29 · Preflight-Checkliste für die Moderation

Vor **jeder** Sitzung abhaken. Fehlt ein Punkt, wird die Sitzung verschoben oder der Punkt im Protokollkopf als Abweichung vermerkt.

## A. Einmalig vor der Runde (−4 bis −2 Wochen, R0-26 §4.2)

- [ ] Bereitschaft gegen den Release-Kandidaten neu geprüft (`bereitschaft.md`); blockierte Aufgaben aus dem Plan genommen oder Termin verschoben
- [ ] Staging nach Ziel-Zeile R0-14 §3: PHP 8.5, WP 7.1.x, MariaDB 12.3, Apache, Objekt-Cache aus, Twenty Twenty-Five; zusätzlich nginx-Site ohne Dateischutz-Regel (AD-T03)
- [ ] WordPress-Sprachpaket **de_DE** installiert (Lernbereich sonst englisch, E-169); ein Admin-Konto für die en_US-Sitzung vorbereitet
- [ ] HTTPS-Zertifikat der Test-Sites gültig (Untertitel/Video verlangen https)
- [ ] `CCAP_UX_EXTERNAL_VIDEO_URL` auf ein neutrales Video eines Fremdhosts gesetzt (LE-T03/E-45a), Adresse auf dem Moderationsblatt
- [ ] Testvideo vorhanden (ffmpeg auf dem Staging-Server oder eigenes CC0-MP4 nachgereicht)
- [ ] `setup-site.sh --profil referenz` gelaufen; Kontrollzeilen: „Plugin-Code aus …/wp-content/plugins/course-captain/src“, `check-rk rk1/rk2/rk3: grün`, Abgaben 9, Gruppe 2 von 5
- [ ] Autoren-Kit gedruckt (groß) und als zugängliche Datei bereit; Moderationsfassungen getrennt abgelegt
- [ ] WP-Cron per Systemcron (Mails im Mail-Sink zeitnah)
- [ ] Einwilligungstext vom Datenschutz freigegeben, R0-17-Bewertung liegt vor; Aufwandsentschädigung organisiert
- [ ] Pilot (1 projektfremde Person je Rolle) gelaufen, Skripte und Zielzeiten justiert, Schwellen S-06 … S-10 eingefroren (E-166)
- [ ] Praxis-Benchmark UX-090 durchgeführt (Baseline)
- [ ] SUS-Fassung festgelegt (deutsche Fassung unverändert) und in `moderationsleitfaden.md` §4.2 eingetragen
- [ ] Rekrutierungsplan (`rekrutierungsplan.csv`) mit Matrix-Zuordnung AT-xx/BR-xx vollständig; Mindestabdeckung R0-26 §3.2 (u. a. LE-T06 auf BR-07 **und** BR-08 mit echtem Netzabbruch)

## B. Am Sitzungstag

### Technik und Hilfstechnik

| Prüfpunkt | Soll | Ist / Version |
|---|---|---|
| Gerät, Betriebssystem | laut Rekrutierungsplan (eigenes Gerät bei Hilfstechnik) | |
| Browser + Version | n oder n−1 laut Matrix (Chrome, Edge, Firefox, Firefox ESR, Safari, iOS Safari, Android Chrome) | |
| NVDA-Version (AT-01/02) | aktuelle Version; Sprachausgabe hörbar über Konferenz | |
| VoiceOver macOS/iOS (AT-03/04) | iOS n bzw. n−1 notiert | |
| TalkBack (AT-05) | aktuelle Version | |
| Vergrößerung (AT-07) | 200 % Text / 400 % Zoom bzw. Lupe eingestellt | |
| Kontrastdesign (AT-08), Bewegung reduzieren (AT-09) | aktiviert | |
| Konferenz-/Testsoftware | Bildschirm + Ton, Gesicht nur nach Einwilligung; Aufzeichnung testweise gestartet | |
| Netz | stabil; für Netzabbruch: Flugmodus erreichbar, Zustimmung eingeholt | |

### Umgebung zurücksetzen

- [ ] Site neu aufgebaut: Autor `setup-site.sh --profil autor --site …/autor-pNN --db ccap_usab_autor_pNN`; Admin `--profil admin …`; GV Referenz-Site neu; Lernende: Konto `cc_fx_ux_pNN` noch unbenutzt
- [ ] Passwörter aus der Konsolenausgabe auf die Zugangskarte übertragen (unten), Konsole geschlossen
- [ ] Browser der Person: privates Fenster bzw. Profil ohne Daten der vorigen Sitzung; Passwortmanager-Speichern abgelehnt
- [ ] zeitkritische Zustände unmittelbar vor der Aufgabe: `scenario laufende-pruefung` (RK-2), `scenario ablauf` (UX-032), `scenario warteschlangenfehler` (AD-T05)
- [ ] Zweites Fenster der Moderation mit den Szenario-Befehlen offen, nicht im geteilten Bildschirm

### Einwilligung und Unterlagen

- [ ] Einwilligung liegt unterschrieben/elektronisch bestätigt vor (Aufzeichnung ja/nein, Gesicht ja/nein)
- [ ] Pseudonym `P-<Rolle>-<nn>` vergeben, keine Klarnamen in Dateien
- [ ] Aufgabenkarten in der richtigen Reihenfolge (Rotation AU-T03 ↔ AU-T04/T05 nach Pseudonym-Nummer)
- [ ] Protokollvorlage (`vorlage-protokoll.md`) und Zeiterfassung (`zeiterfassung.csv`) vorbereitet; Uhr mit Sekunden
- [ ] SEQ-Karte (1–7) und SUS-Bogen (Papier oder zugängliches Formular)

## C. Zeitplan-Bogen (zum Ausdrucken)

| Block | geplant (min) | Start | Ende | Bemerkung |
|---|---|---|---|---|
| Begrüßung + Einwilligung | 5 (AT 10) | | | |
| Vorab-Fragebogen | 5 | | | |
| Aufwärmen | 2–5 | | | |
| Aufgaben (Karte für Karte, Zeiten in `zeiterfassung.csv`) | Autor 90 · Lernende 50 · AT 55 · GV 18 · Admin 38 | | | |
| Pause (AT, nach ≤ 30 min) | 5–10 | | | |
| SUS + Nachgespräch | 8–10 | | | |
| Abschluss | 2 | | | |

## D. Zugangskarte (je Sitzung, nach der Sitzung vernichten)

| Site-URL | Konto | Passwort | gültig bis zum nächsten Neuaufbau |
|---|---|---|---|
| | `cc_fx_ux_…` | | |

## E. Nach der Sitzung

- [ ] Systemprüfung: `check-rk` (Autor), Mail-Sink, Prüfungs-/Abgabestatus notiert
- [ ] Aufzeichnung am vereinbarten Ort gespeichert, Löschtermin (R1-GATE + 6 Monate) eingetragen
- [ ] Protokoll vollständig (SEQ je Aufgabe, SUS-Rohwerte, Nachgespräch), Befunde in `befunde.csv` übertragen
- [ ] Zugangskarte vernichtet; Site für die nächste Person neu aufbauen
