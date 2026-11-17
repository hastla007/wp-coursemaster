# R1-29 · Unterlagen für die Usability-Abnahme

Vorbereitung der echten Autoren- und Lernenden-Nutzertests nach `docs/r0/R0-26-ux-abnahme-nutzertests.md`. Die Sitzungen selbst führt der Auftraggeber mit echten Personen durch; der Stand der Vorbereitung und was offen bleibt, steht in `docs/r1/R1-29-vorbereitung.md`.

R0-26 §2.3/§4.3 nennt als Ablage `docs/ux/…`; die Unterlagen liegen gebündelt hier. Protokolle der Sitzungen kommen nach `docs/r1/R1-29/protokolle/P-<rolle>-<nn>.md`.

## Reihenfolge der Nutzung

1. **`bereitschaft.md`** – welche Referenzaufgaben die Oberfläche heute zulässt (viele sind blockiert). Vor jeder Runde neu prüfen.
2. **`testumgebung.md`** – Test-Sites mit `tools/usability/setup-site.sh` aufbauen und zurücksetzen, Konten, Szenario-Befehle, Autoren-Kit.
3. **`preflight-checkliste.md`** – vor jeder Sitzung: Technik, Hilfstechnik-Versionen, Einwilligung, Zurücksetzen, Zeitplan-Bogen, Zugangskarte.
4. **`moderationsleitfaden.md`** – Ablauf, Einwilligungstext, laut denken, Hinweisstufen H0–H2, Abbruch, SEQ/SUS, Vorab-Fragebogen, Nachgespräch.
5. **Skripte je Rolle** – Karte, Startzustand, Erfolgskriterium, Zielzeit/Abbruch, Beobachtung, Systemprüfung:
   - `skript-autor.md` (AU-T01 … T13, AU-GES, Störfall UX-012)
   - `skript-lernende.md` (LE-T01 … T12, LE-GES, UX-031/032/035/075)
   - `skript-gruppenverwalter.md` (GV-T01 … T07)
   - `skript-admin.md` (AD-T01 … T06)
6. **`szenarien-hilfstechnik.md`** – Tastatur, NVDA + Firefox, VoiceOver + Safari (macOS/iOS), Vergrößerung, Kontrast, mobile Prüfungsläufe, Expertendurchläufe.
7. **`szenarien-fehlerbehebung.md`** – Störfälle und Fehlerpfade FB-01 … FB-17 mit Auslöser und Soll.
8. **Erfassen und auswerten:**
   - `vorlage-protokoll.md` – eine Kopie je Sitzung
   - `protokoll-aufgaben.csv`, `zeiterfassung.csv`, `sus-auswertung.csv` (mit SUS-Formel) – Tabellenkalkulation
   - `vorlage-befunde.md` + `befunde.csv` – Befundregister (vorbelegt mit den Vorbereitungsbefunden)
   - `schwellen.csv` – Schwellen S-01 … S-EXP mit Ist-Spalten
   - `rekrutierungsplan.csv` – Pseudonyme, Matrix-Zuordnung, Sites, Konten
   - `vorlage-abnahmebericht.md` – Abnahmebericht für R1-GATE
9. **`kit/`** – generierte Drehbücher (Teilnehmenden- und Moderationsfassung) und Soll-Dateien des Laufs vom 17.11.2026. Quelle: `tools/usability/content/rk-v1.php`; Fragenpakete und Medien erzeugt `setup-site.sh --kit`.

CSV-Dateien: UTF-8, Trennzeichen Semikolon, Dezimalkomma (deutsche Tabellenkalkulation).
