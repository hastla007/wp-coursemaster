# R1-16 · Lernoberfläche und responsive Navigation – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-16):** Kurskatalog, Kursraum, Activity, Prüfung, Fortschritt, Profile, Tastatur/Mobil.
**Quellen:** R0-19 §2.3 (R-05, R-06, R-07, R-08, R-10/11, R-26…R-28), §4.5 Shortcodes, §4.6 Blöcke; R0-08 §1.4 (Sperranzeige: Titel + Grund); R0-15 (A11y); R0-05 GM-001, GM-091, GM-143. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Learner/LearnerQueries.php` | Katalog nur veröffentlichter Kurse (GM-001); „Mein Lernen“ mit Zugangsstatus nach GM-091 (active, limited bei Modul-Grant/Teilumfang, dann suspended > expired > pending > not_granted; abgelaufene aktive Grants zählen nicht), Fortschritt in %, Fortsetzen-Ziel (zuletzt geöffnete unvollständige, sonst erste offene freie Lektion); Kursraum mit Modulen/Lektionen, Sperrgrund und `unlock_at` aus dem Gate, Lektionsstatus; Kurs per ID oder UID |
| `src/Learner/LearnerRoutes.php` | `GET /me/courses`, `GET /courses/{course}`, `GET /courses/{course}/lessons`, `GET /courses/{course}/next-question`, `POST /answers`, `PUT /courses/{course}/lessons/{lesson}/progress`, `PUT/DELETE /courses/{course}/favorites/{question}`; 401 ohne Login, Katalog-Fehlercodes, `Cache-Control: private, no-store` |
| `src/Learner/LearningArea.php` | Lernbereich `/<general.learning_area_slug>/` (Standard `/lernen/`, Rewrite-Version 4): „Mein Lernen“ und Kursraum `kurs/<uid>`, serverseitig ohne JS; Skip-Link, `header`/`main`/`nav` mit Bezeichnung, `aria-current="step"` für die Fortsetzen-Lektion, Sperrgründe und Status als Text, `progress` mit Beschriftung; einspaltig, keine festen Breiten, Umbruch langer Wörter, sichtbarer Fokus, Zielgrößen ≥ 44 px; ein Dokumenttitel; nie seitengecacht; Abgemeldete → Login mit Rücksprung; Blöcke `course-captain/course-catalog` und `course-captain/learning-link` (dynamisch), Shortcodes `[ccap_learning_link]`, `[ccap_access]` |
| `blocks/course-catalog/block.json`, `blocks/learning-link/block.json` | `apiVersion: 3`, `textdomain: course-captain`, Attribute nach R0-19 §4.6 |
| `tools/qa/e2e/learning-area-mobile.js` | Browser-Prüfung der gerenderten Seiten bei 390 px (Chromium/Playwright) |
| `tests/integration/LearnerTest.php` | 6 Tests |
| `languages/course-captain.pot` | inkl. Block-Strings |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **144 / 1 513** · Multisite **153 / 1 566** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · Browser-Prüfung 390 px: `{"ok":true, overflow 0, Skip-Link sichtbar mit Fokusrahmen, Ziel ≥ 44 px, errors []}`.

## 3. Tests

| ID | Test |
|---|---|
| GM-001 | Katalog = veröffentlichte Kurse; Entwurf/Archiv fehlen, auch im Block und für Gäste; „nur eingeschrieben“; Blöcke registriert, apiVersion 3 |
| GM-091 | active; not_granted → pending → expired (abgelaufener aktiver Grant) → suspended (Priorität); Modul-Grant und Teilumfang → limited |
| Fortsetzen/Kursraum | erste offene Lektion, nach Abschluss die nächste, 50 %; Drip-Modul mit Grund und Freigabedatum; Lektionsstatus |
| Seiten (GM-143 Teil) | Viewport, Skip-Link, ein `main`, bezeichnete `nav`, `aria-current` auf der Fortsetzen-Lektion, eine `h1`, keine festen Breiten, ein `<title>`; ohne Zugang Statustext; unbekannter Kurs „nicht verfügbar“ |
| REST | 401, 200 mit `no-store`, UID-Adressierung, 404, Lektionsliste, Fortschritt, nächste Frage mit Token, Antwort, Favorit, unbekannter Modus 422 |
| Shortcodes | Lernbereichs-Link mit Label; `[ccap_access]` mit eigener Meldung gesperrt, mit Zugang sichtbar |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Lektionsansicht selbst (Activity-Rendering je Provider, Video-Player, Fragen-UI mit JS), Prüfungsoberfläche (GM-047 Anzeige, GM-050 Offline-Puffer, GM-051 Abbruch-Dialog) | Dienste und REST stehen; die interaktiven Oberflächen folgen mit R1-19/R1-20 (A11y-Komponenten, Offline/Integrität) |
| A-2 | Profile (Konto, Datenschutz-Anfrage `[ccap_privacy_request]`) | R1-17 |
| A-3 | Vollständige E2E-Läufe gegen eine laufende Site (Login, Navigation) | R1-18 (Golden-Master-E2E); hier statische Browser-Prüfung der echten Server-Ausgabe |
| A-4 | Release-ZIP muss `blocks/` enthalten | Prüfpunkt für R1-39 (ZIP-Prüfung J-17) |
| A-5 | Rücksprung nach Login zur Einladung (GM-104) | Login-Rücksprung für den Lernbereich umgesetzt; Einladungsfluss mit R1-28 |

## 5. Übergaben

R1-17, R1-18, R1-19, R1-20, R1-28, R1-39. Review durch zweite Person vor Merge (AG-05).
