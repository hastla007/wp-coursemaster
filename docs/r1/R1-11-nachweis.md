# R1-11 · Karteikarten, Favoriten und Fehlertraining – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-11):** Alle belegten neutralen Lern- und Wiederholungsfunktionen mit Persistenz.
**Quellen:** R0-08 §2.4 (E-21), R0-06 E-30 (`ccap_favorite`, `ccap_collection`, `ccap_collection_item`), R0-05 PAR-05 (GM-055…GM-063) und PAR-06 (GM-070, GM-071), Inventur A INV-A-019 (30 je Kurs, 60 Zeichen). Clean Room (E-01; Mechanik aus den neutralen Testbeschreibungen, kein Quelltext).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Learning/Flashcards.php` | Ein Kartentyp, zwei Modi (E-21) mit eigenem Statusbereich `open`/`practice`/`known` in `ccap_question_progress` (nie Smarttrainer-Stufen): `set_state` (Selbsteinschätzung, jede Richtung) und `assess` (Serie: `known_streak` Standard 3, „nicht gewusst“ setzt 0, einmal `known` bleibt `known`). Adressierung nur über das signierte Lektions-Token (`deliver_inline`) → gesperrte Karten (Teilumfang/Drip) nicht setzbar; falscher Modus/Typ `ccap_invalid_param`. Doppelklick-Schutz über Idempotenzschlüssel (`ccap_attempt`, Kontext `flashcard`), veraltete `expected_streak` → `applied = false`; Zeilensperre `FOR UPDATE`. Danach Neubewertung der Activity |
| `src/Activity/ActivityProgress.php` | Interaktion `cards`; bei Flashcard-Lektionen werden `total`/`known` **immer serverseitig** gezählt (Client-Werte überschrieben) → `all_known`/`ratio` |
| `src/Learning/Favorites.php` | Favoriten je Nutzer (idempotent), nur Fragen in einem **zugänglichen** Modul des Kurses (fremd 404, gesperrt 403, GM-058); Sammlungen je Nutzer und Kurs: Name bereinigt (Leerraum, `sanitize_text_field`), ≤ 60 Zeichen, eindeutig ohne Groß/Klein (`ccap_duplicate`), höchstens 30 (`ccap_limit_reached`), nur Favoriten einsortierbar (`ccap_not_favorite`), fremde Sammlung 404; Entfavorisieren entfernt aus allen Sammlungen, Löschen der Sammlung behält Favoriten (GM-059); Liste mit Sammlungen und Sortierung `saved_desc/asc`, `assigned_desc/asc` (nur mit Sammlung), stabil nach (Zeit, Frage-ID), Nulldatum am Ende, sonst 422 (GM-060); Fragenmenge für den Modus `favorites` |
| `src/Learning/ErrorTraining.php` | Modus `wrong`: Fragen, deren **letzte** Lernantwort im Kurs falsch war (älteste zuerst); falsche Fragen einer eigenen ausgewerteten Prüfung (fremd 404, laufend 409 `ccap_exam_running`); beide auf den zugänglichen Umfang beschnitten; Prüfungsantworten ändern den Lernstand nicht |
| `tests/integration/LearningListsTest.php` | 4 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **112 / 1 145** · Multisite **121 / 1 198** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0.

## 3. Tests

| ID | Test |
|---|---|
| GM-062 | Selbsteinschätzung `practice`/`known`; unbekannter Status und falscher Modus abgewiesen; alle Karten `known` → Activity `completed`; gefälschte Kartenzahlen wirkungslos |
| GM-063 | Serie: Reset bei „nicht gewusst“, Doppelantwort mit gleichem Schlüssel ignoriert, veraltete Serie ignoriert, nach 3 × gewusst `known`, bleibt `known`, Activity abgeschlossen |
| GM-055…060 | Favoriten idempotent; Name `"  Wiederholung  "` → `Wiederholung`, Dublette/leer/zu lang abgewiesen; Nicht-Favorit abgewiesen; Übersicht mit Sammlungen; Lernen nach Sammlung; fremde Sammlung 404, fremde Frage 404, gesperrtes Modul 403; Sortierung inkl. gleicher Zeitstempel und Nulldatum; ungültige Sortierung 422; Kaskaden; Limit 30 |
| GM-070 / 071 | falsche Lernantwort im Set, nach richtiger Antwort raus; Prüfungsfehler nur aus eigener ausgewerteter Prüfung, fremd 404, laufend 409 |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Idempotenz mit „in Arbeit“/anderem Inhalt (GM-061, 409-Codes) | HTTP-Schicht R1-28 |
| A-2 | Auswahl der nächsten Frage (`next(mode)`), Stufe `learning` nach richtiger Antwort im Fehlertraining (GM-070 zweiter Teil) | Smarttrainer R1-12 nutzt die hier gelieferten Fragenmengen |
| A-3 | Rate-Limit 60 Antworten/min (GM-073) | R1-12 |
| A-4 | Paket-Archivierung entfernter Karten (GM-064) | R1-15 |
| A-5 | Datenschutz-Export der Favoriten/Sammlungen (GM-172/173) | R1-17 |

## 5. Übergaben

R1-12 (Auswahl, Stufen, Rate-Limit), R1-15 (GM-064), R1-16 (Oberfläche), R1-17 (Export/Löschung), R1-28 (HTTP-Idempotenz). Review durch zweite Person vor Merge (AG-05).
