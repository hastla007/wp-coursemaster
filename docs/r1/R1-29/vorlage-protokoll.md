# Sitzung P-<rolle>-<nn>

> Vorlage nach R0-26 §4.3. Je Sitzung eine Kopie als `docs/r1/R1-29/protokolle/P-<rolle>-<nn>.md` (pseudonym, ohne Klarnamen und ohne Gesundheitsangaben über „Hilfstechnik X“ hinaus). Die Aufgabentabelle kann parallel in `protokoll-aufgaben.csv` geführt werden.

## Kopf

- Datum/Uhrzeit: <YYYY-MM-DD HH:MM> · Dauer: <min> · Runde: 1 | 2 | Pilot
- Format: moderiert vor Ort | moderiert remote | unmoderiert
- Moderation: <Kürzel> · Protokoll: <Kürzel>
- Build: <Plugin-Version, Commit> · Paket: cc-fx-rk-v1 (<Commit von tools/usability/content/rk-v1.php>) · Referenzkurs: RK-1 | RK-2 | RK-3
- Site: <URL> · Profil: referenz | autor | admin · Neu aufgebaut um: <HH:MM>
- Gerät/OS: <…> · Browser + Version: <…> · Hilfstechnik + Version + Einstellungen: <… | keine>
- Matrix-Zeile(n): <AT-xx, BR-xx>
- Profil (Vorab-Fragebogen V1–V8): <WordPress-Erfahrung, Kurse erstellt, E-Learning, Selbsteinschätzung 1–5, Altersgruppe>
- Einwilligung: ja (Aufzeichnung Bildschirm/Ton: ja/nein, Gesicht: ja/nein)
- Rotation (nur Autor): T03 vor T04/T05 | T04/T05 vor T03
- Konto: `cc_fx_ux_<…>`

## Aufgaben

`E` = Erfolg ohne Hilfe · `EH` = Erfolg mit H1 · `F` = nicht erfolgreich (inkl. H2) · `Abbr.` = Abbruch · `n. d.` = nicht durchführbar (Bereitschaft, kein Teilnehmendenergebnis).

| Aufgabe | Start | Ende | Zeit (min) | Lesezeit abgezogen (min) | Ergebnis (E / EH / F / Abbr. / n. d.) | Hinweise H0/H1/H2 | Bedienfehler | Krit. Fehler | SEQ (1–7) | Beobachtung (wörtlich, kurz) | Befund-IDs |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Aufwärmen | | | | – | – | – | – | – | – | | |
| AU-T01 | 10:04 | 10:07 | 3,0 | – | E | 0/0/0 | 1 | 0 | 6 | „Wo speichere ich?“ | UXB-012 |
| | | | | | | | | | | | |

## Störfälle

| Störfall | Auslöser + Uhrzeit | Zustand vorher (Antworten/Markierungen/Text) | Zustand nachher | Zeit bis Weiterarbeit | Hilfe nötig | Befund-IDs |
|---|---|---|---|---|---|---|
| z. B. FB-01 | Tab geschlossen 10:41 | 10 Antworten, Markierung Pos. 3 und 7 | | | | |

## Sitzungsende

- SUS-Rohwerte (Aussage 1–10): `_ _ _ _ _ _ _ _ _ _` → Score: `<0–100>` (Formel: ungerade − 1, 5 − gerade, Summe × 2,5)
- Nachgespräch:
  1. Am schwierigsten: <…>
  2. Gefehlt: <…>
  3. Einsatz so: <…>
  4. Verständnisfragen (AU-T10 / AU-T13 / GV-T06 / LE-T08): <Antwort> → richtig/falsch
- Systemprüfung:
  - `check-rk rk<n> --kurs=<ID>`: <grün / Abweichungen …> (Autor)
  - Mail-Sink (`wp-content/ccap-ux-mails/mails.log`): <relevante Mails>
  - Zustand: <Prüfungsstatus, Abgabestatus, Grants, Gruppenbelegung>
- Belege: <Zeitmarken der Aufzeichnung, Screenshots ohne Personendaten>
- Besonderheiten / Abweichungen vom Skript: <…>
