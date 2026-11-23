# R1-29 · Aufgabenskript Gruppenverwaltung (Flow 3, R0-26 §1.4, R1-35)

**Sitzung:** 30 min. 3 von 5 Personen **unmoderiert** remote (Testportal mit diesem Skript, Bildschirmaufzeichnung, SEQ nach jeder Aufgabe, SUS am Ende; nur „Erfolg ohne Hilfe“ messbar), 2 moderiert. Site: Profil `referenz`. Konto: `cc_fx_ux_gv` (Besitzerin der RK-3-Gruppe „Team Einarbeitung (Testgruppe)“, 5 Plätze, 2 belegt durch `team1`/`team2`). Mail-Sink aktiv; die Moderation tritt als `cc_fx_ux_beitritt` bei.

**Startzustand:** Gruppe neu aufgebaut (Referenz-Site je GV-Person neu aufsetzen, weil die Aufgaben Einladungen und Plätze verbrauchen). Einladungsadressen auf einer Karte: `gv.einladung1@example.test`, `gv.einladung2@example.test`, `gv.einladung3@example.test`; adressgebundener Link für `cc_fx_ux_beitritt@example.test`.

**Bereitschaft (Stand 6f6f05f):** **alle GV-Aufgaben blockiert** – es gibt keine Besitzer-Oberfläche (L-18) im Lernbereich, und der Beitrittslink `?ccap_claim=` wird nicht ausgewertet (`bereitschaft.md`). Die Admin-Seite „Groups“ ist nur für Konten mit `ccap_manage_groups` (AD-T06).

Abbruch = 3 × Zielzeit (max. 20 min), Hilfstechnik 4 ×.

| ID | Karte | Erfolg | Ziel / Abbruch (AT) | Beobachten | Systemprüfung |
|---|---|---|---|---|---|
| GV-T01 (UX-050) | „Finde die Gruppe, die du verwaltest, und sag, wie viele Plätze frei sind.“ | „3 Plätze frei“ genannt | 2 / 6 min (8) | Einstieg aus „Mein Lernen“, Begriff „Platz“ | – |
| GV-T02 ★ (UX-051) | „Lade drei Personen per E-Mail ein.“ (Adressen auf der Karte) | 3 Einladungen versendet | 4 / 12 min (16) | Mehrfacheingabe, Bestätigung, Tippfehler-Korrektur | Mail-Sink: 3 Einladungsmails; 3 Plätze `invited` |
| GV-T03 ★ (UX-051, E-28) | „Gib einer weiteren Person einen Link zum Beitreten, der nur für ihre Adresse gilt.“ | Option „nur eingeladene Adresse“ gesetzt, Link kopiert | 3 / 9 min (12) | Verständnis „übertragbar“ vs. „nur diese Adresse“ | Gruppe `claim_policy = invited_address_only` |
| GV-T04 (UX-052) | Moderation tritt als `cc_fx_ux_beitritt` über den Link bei. „Prüfe, ob die Person dabei ist und wie viele Plätze noch frei sind.“ | Mitglied „aktiv“, Belegung richtig | 2 / 6 min (8) | Aktualisierung der Anzeige | Platz `active` |
| GV-T05 ★ (UX-053, F3.3) | „Ein Link ist in falsche Hände geraten. Mach ihn ungültig.“ | Link erneuert; der alte Link führt zur neutralen Meldung | 3 / 9 min (12) | Warnung vor Erneuern, Verständnis „alter Link geht nicht mehr“ | Moderation öffnet den alten Link im privaten Fenster |
| GV-T06 (UX-054, F3.9) | „Eine Person hat das Team verlassen. Gib ihren Platz frei.“ (`team2`) | Mitglied entfernt; richtige Erklärung, dass der Lernfortschritt erhalten bleibt | 3 / 9 min (12) | Bestätigung, Nachfrage zur Folge | Platz `ended`, Grant beendet, Fortschritt vorhanden |
| GV-T07 (UX-055, F3.1/F3.2) | Rollentausch, als `cc_fx_ux_beitritt`: „Nimm die Einladung an.“ Ausgangslage A: Gruppe voll (Moderation füllt vorher alle Plätze); B: Einladung abgelaufen | Die Person versteht die Meldung und nennt den nächsten Schritt | 2 / 6 min (8) | Ton und Verständlichkeit der Meldung | – |
| **GV-GES** (UX-056) | GV-T01 … T06 | alle gelöst; **Median ≤ 15 min** (S-10) | – | – | – |

**Vorbereitung GV-T07:** Gruppe voll – Moderation lädt als Admin (Seite „Groups“) bis zur Platzgrenze ein. Abgelaufene Einladung – Einladung anlegen und in der Datenbank `claim_expires_at` in die Vergangenheit setzen (nur Test-Site) oder 14 Tage alte Einladung aus einer früheren Vorbereitung nutzen; ein Szenario-Befehl dafür folgt, sobald die Besitzer-Oberfläche existiert.

**Nachgespräch:** drei feste Fragen, Verständnisfrage GV-T06, SUS.
