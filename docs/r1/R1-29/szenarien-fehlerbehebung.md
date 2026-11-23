# R1-29 · Fehlerbehebungs- und Störfall-Szenarien

**Grundlage:** R0-26 §1.2 (Störfall F1.4), §1.3 (LE-T06 Abbruch, F2.5), §1.5 (AD-T02, AD-T05), §3.3 (Störfall-Werkzeug nur auf Staging), R0-11 Fehlerpfade F1.x … F4.x. Die Moderation löst Störfälle **ohne Ankündigung** aus und sagt danach nur die Karte. Befehle: `testumgebung.md` §4 (`UX=…ccap-ux`).

**Wertung:** Datenverlust oder eine unbemerkt falsche Annahme („abgegeben“, „veröffentlicht“, „bewertet“) ist ein **kritischer Fehler** → Befund S1 (R0-26 §2.4, §4.4).

| Nr. | Szenario | Auslöser (Moderation) | Karte danach | Erwartet (Soll) | Beobachten | Messung | Bezug | Stand |
|---|---|---|---|---|---|---|---|---|
| FB-01 | Prüfung: Tab geschlossen (Desktop) | nach der 10. Antwort und 2 Markierungen: „Bitte schließen Sie jetzt den Tab.“ | „Setze die Prüfung fort und gib sie ab.“ | Fortsetzen ohne Hilfe; alle Antworten und Markierungen erhalten; Restzeit läuft serverseitig weiter | Rückweg, Vertrauen in Speicherung | Zeit bis zur nächsten offenen Frage (S-08 ≤ 1 min), verlorene Antworten (0) | LE-T06, UX-026, F2.5 | bereit |
| FB-02 | Prüfung: echter Netzabbruch + App-Wechsel (mobil) | Flugmodus an, Kamera-App öffnen, 30 s, zurück, eine Antwort ändern, Flugmodus aus | (keine neue Karte; weiterarbeiten) | Hinweis bei fehlender Verbindung; Antwort nach Wiederverbindung gespeichert oder klare Meldung, dass sie nicht gespeichert ist | Versteht die Person den Zustand ihrer letzten Antwort? | verlorene Antworten (0), Zeit | LE-T06, UX-026, BR-07/BR-08 | teilweise (Prüfungsraum ohne JS: Antwort ohne Netz wird nicht gesendet – Meldung prüfen) |
| FB-03 | Prüfung: Zeit läuft ab | `$UX scenario ablauf` (Konto `cc_fx_ux_ablauf`, 1 min Rest), Person beantwortet weiter | „Du schreibst gerade eine Prüfung. Mach weiter.“ | Ansage bei 1 min, Ablaufmeldung, automatische Auswertung (`expired`), Ergebnis auffindbar | Wird die Auto-Abgabe verstanden? | Verständnis (ja/nein) | UX-032, F2.7, A11Y-073, E-75 | bereit |
| FB-04 | Prüfung: Abgabe ohne Bestätigung versucht | – (Person klickt „Abgeben“ ohne Häkchen) | – | Fehlermeldung als Alert, nichts abgegeben, offene/markierte Fragen genannt | Bemerkt die Person, dass nicht abgegeben wurde? | kritischer Fehler „glaubt abgegeben“ (0) | A11Y-074 | bereit |
| FB-05 | Autor: Sitzung ungültig beim Schreiben | während AU-T05: `$UX scenario sitzungen-beenden --konto=cc_fx_ux_autor` | (weiterarbeiten) | Anmeldedialog; nach Anmeldung kein Text verloren | Wiederherstellung des Entwurfs | verlorene Eingaben (0) | AU-T05, UX-012, F1.4 | teilweise (Block-Editor-Autosave; Metabox-JSON nicht) |
| FB-06 | Autor: Netz weg beim Speichern | während AU-T05: WLAN 30 s aus | (weiterarbeiten) | Meldung „Verbindung verloren“, Speichern nach Wiederverbindung | wie FB-05 | wie FB-05 | UX-012, F1.4 | teilweise |
| FB-07 | Autor: Veröffentlichung mit Blockern | eingebaute Fehler im Drehbuch/Paket | „Veröffentliche den Kurs.“ | Validierungspanel nennt beide Blocker mit Sprung zum Fehler; Kurs bleibt Entwurf, bis behoben | Glaubt die Person, veröffentlicht zu haben? | kritischer Fehler (0) | AU-T09, UX-009, F1.1 | teilweise (Alt-Text-Regel fehlt, B-05) |
| FB-08 | Autor: veröffentlichte Frage ändern | – | AU-T10 | Revisionshinweis, laufende Versuche unberührt | Verständnisfrage | richtige Erklärung (≥ 80 %) | UX-010, F1.3 | blockiert |
| FB-09 | Lernende: gesperrte Inhalte | frisches Konto, RK-1 ohne Zugang (Teilumfang), RK-3 Modul 2 (Drip) | „Warum kannst du Lektion 4 noch nicht öffnen?“ | Sperrgrund als Text, Freigabedatum | richtige Begründung | ≥ 80 % richtig | UX-031, F2.2/F2.3, A11Y-027 | bereit |
| FB-10 | Lernende: Abgabe zurückgegeben | Konto `cc_fx_ux_rueckgabe` | LE-T12 | Kommentar sichtbar, neue Fassung möglich, alte lesbar | Findet die Person den Kommentar? | Erfolg | UX-037, R1-46 | blockiert |
| FB-11 | Lernende: Upload abgelehnt | LE-T11 (RK-2) mit einer `.docx`-Datei statt PDF (Datei bereitlegen) | (weiterarbeiten) | verständliche Meldung „nur PDF“, Entwurf bleibt | Nächster Schritt klar? | Erfolg nach Korrektur | UX-036, R0-10 §4.5 | blockiert |
| FB-12 | Setup abgebrochen | AD-T01 Schritt 4: „Bitte schließen Sie jetzt den Tab.“ | AD-T02 | Wiedereinstieg bei Schritt 4, gespeicherte Werte vorbelegt | Rückweg | Zeit (≤ 1 min), verlorene Werte (0) | UX-061, F4.6, A11Y-083 | bereit |
| FB-13 | Setup: Dateischutz-Problem | nginx-Site ohne Regel | AD-T03 | Bereitschaftsprüfung nennt Ursache und Abhilfe | Ursache genannt | Erfolg | UX-062, F4.5 | bereit (nginx-Staging nötig) |
| FB-14 | Einstellungen: ungespeicherte Änderung | AD-T04 | AD-T04 | Warnung beim Verlassen, nichts gespeichert | Versteht die Person die Warnung? | Erfolg | UX-063, A11Y-009 | bereit |
| FB-15 | Hintergrundaufgabe fehlgeschlagen | `$UX scenario warteschlangenfehler` | AD-T05 | Fehler sichtbar mit Ursache, Retry möglich, Ergebnis prüfbar | Weg in den Systemstatus | Erfolg | UX-064, A-16 | bereit |
| FB-16 | Gruppe voll / Einladung abgelaufen | siehe `skript-gruppenverwalter.md` GV-T07 | „Nimm die Einladung an.“ | neutrale, verständliche Meldung mit nächstem Schritt | nächster Schritt genannt | ≥ 80 % | UX-055, F3.1/F3.2 | blockiert |
| FB-17 | Alter Gruppenlink nach Erneuerung | GV-T05, alter Link im privaten Fenster | – | neutrale Meldung, kein Beitritt | – | Link nachweislich ungültig | UX-053, F3.3 | blockiert |

## Hinweise zur Durchführung

- **Zustand vorher sichern:** Bei FB-01/FB-02 die gegebenen Antworten (Position, Antwort) und Markierungen mitschreiben, um „0 verlorene Antworten“ belegen zu können. Danach vergleicht das Protokoll mit dem Prüfungsraum.
- **Remote-Netzabbruch:** nur mit vorheriger Zustimmung der Person (im Einwilligungsgespräch erwähnen: „Wir bitten Sie eventuell, das WLAN kurz auszuschalten“).
- **Nach jedem Störfall** die Site nicht reparieren, sondern den Zustand für die Auswertung lassen; Zurücksetzen erst nach der Sitzung (`setup-site.sh` erneut).
- Automatisierte Gegenstücke zu UX-002, UX-012, UX-026, UX-061 gehören zu R1-18/R1-19 (R0-26 §6) und ersetzen diese Szenarien nicht.
