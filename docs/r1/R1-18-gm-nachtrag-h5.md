# R1-18 · Nachtrag h5 – Referenz-E2E Gate B-4

**Stand:** 23.11.2026 · **Basis:** `claude/blissful-curie-tuuf7q` (c3769a3) · **Gate:** B-4 (R0-12 §3.2) statt GM-IDs
**Quellen:** R0-12 §3.2 (B-4), R0-11 §3.1–§3.3 (Flows 1–3 mit Fehlerpfaden), R0-26 §1.1/§1.2 (Referenzkurs, UX-090), R0-22 §1.3/§3 (Validierungspanel, Sprungziel), R0-15 (A11Y), Konzept 27.4, E-165/E-166/E-168, `docs/r1/R1-18-nachweis.md` §4. Clean Room (E-01/E-327): kein LearnBoating-Quelltext.

## 1. Ergebnis

`tools/qa/e2e/live-flows.sh` baut eine frische WordPress-Site (WP 7.1.4, PHP 8.5, Plugin aus dem Arbeitsbaum, Fixture `cc-fixture-neutral-v1`), startet `php -S` und fährt alle drei Referenz-Flows in Chromium. Dreimal hintereinander `live-flows: OK` (die letzten drei Läufe, der letzte mit dem Endstand), `live-smoke.sh` weiterhin OK.

| B-4-Zeile | Stand |
|---|---|
| Flow 1 Autor erstellt und veröffentlicht (Chromium Desktop) | **erfüllt** über die WP-Oberfläche (Block-Editor + Metaboxen); Fragenbank, Prüfungsregel und Inline-Quizfragen mangels Editor über die Dienste (§4 L-1) |
| Flow 2 Lernender lernt, besteht, Nachweis (Chromium Desktop) | erfüllt (wie bisher) |
| Flow 2 in Mobil-Emulation (Chromium, Pixel 7) | **erfüllt** inkl. „keine Querscroll-Seite“ und Prüfungsraum GM-050/051 |
| Flow 3 Gruppenverwalter lädt ein (Chromium Desktop) | **erfüllt** (A-10, Mail, Login-Rückkehr, Annahme, Belegung, F3.3) |
| Referenzkurs mit Aufgabe (E-168) | erfüllt: Aufgabe angelegt (Editor), eingereicht und bewertet (REST, §4 L-3) |
| Praxis-Benchmark 10/50/1 (+ Aufgabe) | automatisierter Teil mit Zeitprotokoll (§3); UX-090 mit Person bleibt offen |
| Firefox, WebKit | **offen**: nur Chromium installiert (`/opt/pw-browsers`), kein Download erlaubt |
| Flows 1 und 3 mobil | offen (Admin-Flows nur Desktop gefahren) |

## 2. Skripte (`tools/qa/e2e/`)

| Datei | Inhalt |
|---|---|
| `live-flows.sh` | Runner: Site (Präfix `ccapflow_` in der Test-DB, Name muss `test` enthalten), Mail-Sink, Fixture, Konten, Server, alle Flows, A11Y, Benchmark, Prüfung des Server-Logs auf PHP-Meldungen; Ergebnisse in `CCAP_E2E_OUT` |
| `lib-site.sh` (neu, auch von `live-smoke.sh` genutzt) | Site anlegen (Core ohne `.git`/Alt-Uploads, `wp-includes` und Themes verlinkt: ~12 MB statt ~1 GB je Lauf), Server: ist `php` der Docker-Wrapper, läuft `php -S` in einem **eigenen benannten Container mit `--init`**, der am Ende mit Log gesichert und entfernt wird (kein verwaister Container); DB-Host 127.0.0.1 beim Wrapper |
| `lib.js` | Schrittprotokoll mit Dauer (`timings.jsonl`), Login, REST aus der Seite, Antwortschlüssel des Referenzkurses, Prüfungsformular ausfüllen |
| `flow-author.js` | Flow 1 (Details §2.1) |
| `live-smoke.js` | Flow 2; neu `CCAP_E2E_LEARNER`, `CCAP_E2E_DEVICE=mobile` (Pixel 7, Touch, Mobil-UA, Prüfung auf Querscrollen je Seite) |
| `flow-group.js` | Flow 3 und Referenzkurs aus Lernendensicht (§2.3) |
| `flow-a11y.js` | A11Y der Kernseiten (§2.4) |
| `bench-report.js` | Benchmark-Bericht `benchmark.md`/`benchmark.json` (§3) |
| `live-setup.php` | neue Befehle `users`, `bank`, `quiz`, `uid`; `dispatch` arbeitet zusätzlich die Mail-Warteschlange ab |
| `mu-mail-capture.php` | Mail-Sink (nur Test-Site): `wp_mail` → `wp-content/ccap-e2e-mail.jsonl` |

Aufruf: `NODE_PATH=<playwright> tools/qa/e2e/live-flows.sh ccap_test_h5_e2e` (Port `CCAP_E2E_PORT`, Standard 8766; `AXE_PATH` optional).

### 2.1 Flow 1 (`cc_e2e_author`, Rolle Course Manager)

Menü „Add course“ öffnet den Editor → 3 Module angelegt und veröffentlicht → Kurs mit Beschreibung, zwei Module mit zwei Speichervorgängen ohne Neuladen (beide bleiben, Befund B-2) und drittes Modul → **zu frühes Veröffentlichen**: Kurs bleibt Entwurf, keine Revision, Fehlermeldung sofort als Editor-Hinweis (B-3), Panel nennt VAL-020 mit **Sprunglink** zum Modul, der Link öffnet den Moduleditor (F1.1, B-4) → Fragenbank 50 Fragen + Prüfungsregel „20 aus 50 (7/7/6), 70 %, 30 min“ (Dienste) → Prüfungslektion mit ungültigen Einstellungen: Hinweis „Not saved … rule_uid“ → 10 Lektionen (4/3/3: 6 Text, 1 Quiz mit 5 Inline-Fragen, 1 Aufgabe Text 10/6 Punkte „graded“, 1 Prüfung „passed“) mit Modul, Typ, Einstellungen über die Metabox, je Lektion geprüft (Modul, Typ, kein Fehler) → Panel „0 blockers“, Warnungen bestätigt, Kurs veröffentlicht, „Published revision: 1“.

### 2.2 Flow 2

Desktop (U-L1) und Mobil (`cc_e2e_mia`, manueller Grant auf C-A): Anmeldung, Mein Lernen, Kursraum, Lektion, Lernfrage, Prüfung starten/speichern/neu laden/fortsetzen/abgeben → „Passed“, Zertifikat, Gastprüfung ohne Login/Adresse, Fortschritt; mobil zusätzlich kein horizontales Scrollen auf allen Seiten. Prüfungsraum (U-L2, Telefon): GM-050, GM-051.

### 2.3 Flow 3 und Referenzkurs als Lernende

Admin schaltet Gruppen ein (Einstellungen › Enrollment; Standard aus) → Gruppenverwalter (`cc_e2e_author`) legt „Team Zeitplan“ mit 2 Plätzen für den Referenzkurs an, lädt `ina@example.test` ein → Outbox/Mail-Warteschlange → Einladungsmail im Sink → Link als Gast: Login mit der Einladung als Ziel, keine Gruppendaten → nach dem Login zurück auf der Einladung (Gruppenname) → „Accept invitation“ ohne Fehler, Mein Lernen zeigt den Kurs → Verwalter: Mitglied „member“, „1 of 2 used“ → Gruppenlink zweimal erneuert: alter Link zeigt nichts und kann nicht angenommen werden (F3.3). Danach: Lektion „Mark as read“, 5 Übungsfragen richtig, Aufgabe Entwurf + Abgabe, Bewertung durch den Verwalter (8/10), Lernende sieht „graded“, Prüfung 20 Fragen bestanden, Zertifikat des Referenzkurses.

### 2.4 A11Y

axe-core ist weder im Repository noch offline im Container vorhanden (kein `axe.min.js`, kein Paket im npm-Cache); ein Download war nicht erlaubt. `flow-a11y.js` führt axe-core aus, sobald `AXE_PATH` gesetzt ist (WCAG 2.0/2.1/2.2 A/AA, Admin-Seiten nur im Plugin-Bereich), sonst eigene DOM-Prüfungen: `lang`, Titel, genau eine sichtbare h1, keine übersprungene Überschriftenebene, zugänglicher Name aller Bedienelemente und Links, Alt-Texte, eindeutige IDs, `main`, Skip-Link als erster Tab-Halt der Seite (nach „Skip to toolbar“ der WP-Werkzeugleiste), sichtbarer Fokus auf allen Tab-Halten, kein Querscrollen bei 320 px. Geprüft: Mein Lernen, Kursraum, Lektion (Text/Aufgabe/Prüfung), Zertifikat, Verifikation (Gast), Metaboxen Lektion/Kurs (Module, Revision), Gruppen-Screen. Ergebnis: 11/11 ohne Befund. Die gedumpten Prüfungen (`learning-area-a11y.js`, Prüfungsraum mit Timer/RTL) bleiben unverändert.

## 3. Praxis-Benchmark (automatisiert, Lauf h5-run3)

Referenzkurs 3 Module / 10 Lektionen / 50 Fragen / 1 Prüfung (20 aus 50) / 1 Aufgabe. Zeiten von `php -S` (4 Worker, Entwicklungs-Container), Chromium headless; Basiswert für Vergleiche zwischen Läufen, kein Nachweis der R0-14-Grenzwerte und kein Ersatz für UX-090 (Person, 50 Fragen manuell).

| Messpunkt | Wert |
|---|---|
| Flow 1 gesamt (Browser, ohne menschliche Denkzeit) | 68,0 s; Editor 64,8 s; Fragenbank + Prüfungsregel über Dienste 1,8 s |
| je Lektion anlegen (Editor, inkl. Speichern + Nachladen) | p50 3,9 s · p95 5,1 s |
| Flow 3 gesamt | 14,4 s |
| Seite Mein Lernen / Kursraum / Lektion (n = 20) | p95 85 / 95 / 107 ms |
| REST Kurs / nächste Frage / Meine Kurse (n = 20) | p95 74 / 50 / 61 ms |
| Prüfung starten / 20 Antworten speichern / abgeben | 300 / 1 304 / 239 ms |

## 4. Befunde

### Behoben (mit Test, `tests/integration/AuthoringTest.php`, `@group R1-18`)

| Nr. | Befund (gefunden im Browserlauf) | Korrektur | Test |
|---|---|---|---|
| B-1 | Course Manager/Author (ohne Core-`edit_posts`) bekamen für „Neuer Kurs/Modul/Lektion“ (`post-new.php`) **403**: die Typen hängen in keinem Menü, WordPress findet keine Elternseite und prüft gegen den versteckten „Beitrag erstellen“. Flow 1 war für diese Rollen unmöglich | `EditorScreens::menu()`: Untermenüs „Add course/module/lesson“ mit `create_posts`; Beschriftung `add_new_item` (PostTypes) | `test_course_manager_may_open_the_add_screens` (schlägt ohne Korrektur fehl, gegengeprüft) |
| B-2 | Der Block-Editor speichert die Metaboxen erneut mit dem Stand des ersten Seitenaufrufs: zweites „Modul hinzufügen“ ohne Neuladen **entfernte still** das erste Modul (F1.4) | Strukturversion `ccap_structure_rev` im Formular; veraltete Zeilen werden nicht angewendet, nur das hinzugefügte Modul; Hinweis „reload the page“ | `test_stale_module_form_keeps_added_modules` |
| B-3 | Fehlermeldungen beim Speichern („Not saved …“, „Not published …“) erreichten Autoren im Block-Editor **nie**: klassische Hinweise sind dort versteckt, und der im Hintergrund geladene Seitenaufruf nach dem Metabox-Speichern verbrauchte die Meldung | Meldung nur bei echten Seitenaufrufen verbrauchen (`Sec-Fetch-Mode: navigate`); `assets/js/editor-notices.js` zeigt sie als Editor-Hinweis beim Laden und nach jedem Metabox-Speichern (`wp_ajax_ccap_editor_flash`, Nonce) | `test_save_error_survives_the_background_meta_box_page` |
| B-4 | Lektionen ließen sich in der Oberfläche **keinem Modul zuordnen** (`post_parent` nur per Code/Import) | Auswahl „Module“ in der Metabox „Activity“, `AuthoringService::move_lesson()` (Rechte auf Lektion und Modul, 404/403) | `test_lesson_module_and_validation_jump_links` |
| B-5 | Validierungspanel ohne Sprungziel (R0-11 F1.1, R0-22 §1.3; R1-26 A-4) | Link „Edit "…"“ zu Modul/Lektion, nur mit Bearbeitungsrecht | wie B-4 |

Neue Texte (5) in POT und `de_DE` ergänzt. Keine neue REST-Route (Snapshots unverändert), kein neuer Hook, keine Audit-Aktion; neue AJAX-Aktion `ccap_editor_flash` (nur eigene Meldung des Nutzers).

### Offen / Lücken (keine Produktentscheidung getroffen)

| Nr. | Thema | Wirkung im E2E |
|---|---|---|
| L-1 | Kein Fragen-Editor (A-07), kein Prüfungsregel-Editor (A-08), keine Zuordnung von Inline-Quizfragen (R1-05 A-5) | Flow 1 legt Fragen, Regel und Quizfragen über `live-setup.php bank/quiz` an (eigene Zeitzeile) |
| L-2 | Quiz-Einstellung `source: module_pool` wird vom Schema angenommen, aber nicht ausgeliefert; der Validator blockiert sie (VAL-029). R0-08 §2.4 nennt „feste Fragen oder Modulpool“ | **Klärung**: Modulpool-Quiz umsetzen oder Option aus dem Schema nehmen |
| L-3 | Lernbereich: Quiz-Lektion („cannot be used on this page yet“), Aufgabenabgabe ohne Formular; keine Bewertungsoberfläche für Trainer | Abgabe und Bewertung per REST aus der angemeldeten Seite |
| L-4 | Block-Editor lädt Metaboxen nach dem Speichern nicht neu: Panel/Strukturliste veraltet bis zum Neuladen; das Kästchen „Publish a new revision when saving“ bleibt angehakt, ein weiteres Speichern veröffentlicht erneut | Skript lädt nach jedem Speichern neu; Vorschlag: Kästchen nach Veröffentlichung per Skript zurücksetzen bzw. Panel nachladen |
| L-5 | Reihenfolge der Lektionen im Modul nur über „Order“ (Seitenattribute); Prüfungslektion wurde zuerst angelegt und steht in Modul 3 vorn | kosmetisch im Referenzkurs |
| L-6 | Gäste werden vom Einladungslink direkt zum Login geschickt (Rückkehr funktioniert); die Gast-Hinweisseite mit „Create an account“ erscheint nur bei öffentlichem Lernbereich | Skript akzeptiert beide Wege |
| L-7 | Firefox/WebKit, Admin-Flows mobil, UX-090 mit Person, axe-core-Lauf | offen; `AXE_PATH` ist vorbereitet |
| L-8 | `/tmp/ccap-e2e-sylo` (~0,9 GB, 09:30) ist ein Überrest eines früheren Laufs der alten `live-smoke.sh` (Core mit `.git` kopiert); nicht von h5, nicht gelöscht | bitte vom Verantwortlichen entfernen |

## 5. Dateien

Neu: `tools/qa/e2e/{live-flows.sh, lib-site.sh, lib.js, flow-author.js, flow-group.js, flow-a11y.js, bench-report.js, mu-mail-capture.php}`, `assets/js/editor-notices.js`, dieses Dokument.
Geändert: `src/Admin/EditorScreens.php` (B-1…B-5), `src/Admin/AuthoringService.php` (`move_lesson`), `src/Course/PostTypes.php` (Beschriftungen), `tests/integration/AuthoringTest.php` (4 Tests), `tools/qa/e2e/{live-smoke.sh, live-smoke.js, live-setup.php}`, `languages/course-captain.pot`, `languages/course-captain-de_DE.{po,mo}`.

## 6. Kommandos und Ergebnisse (23.11.2026)

| Kommando | Ergebnis |
|---|---|
| `tools/qa/e2e/live-flows.sh ccap_test_h5_e2e` (3×) | `live-flows: OK` (Flow 1 70–86 s, Flow 2 desktop 9–13 s, mobil 9–11 s, Prüfungsraum 4–5 s, Flow 3 15–18 s, A11Y 11/11, Benchmark) |
| `tools/qa/e2e/live-smoke.sh ccap_test_h5_e2e` | `live-smoke: OK` |
| `php8.5 vendor/bin/phpcs -q` | 0 Fehler, 0 Warnungen |
| `php8.5 vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | 0 Fehler |
| `php8.5 vendor/bin/phpunit -c phpunit.xml.dist` | 130 Tests, 2 942 Assertions, 2 übersprungen |
| `WP_TESTS_DB_NAME=ccap_test_h5_int … phpunit-integration.xml.dist` | 468 Tests, 22 576 Assertions, 31 übersprungen (pcntl), 0 Fehler (eigene DB, weil ein paralleler Lauf auf `ccap_test_g1` Tabellen entfernt hatte) |
| `WP_TESTS_DB_NAME=ccap_test_g1 … phpunit-integration-multisite.xml.dist` | 481 Tests, 22 765 Assertions, 31 übersprungen, 0 Fehler |
| `php8.5 tests/guards/free-guard.php` | 0 Befunde (206 Dateien) |
| `php8.5 tests/guards/textdomain-lint.php` | 0 Probleme (198 Dateien) |
| `php8.5 tools/qa/gm-coverage.php` | 210 IDs, 207 mit Test, 3 Ausnahmen; missing/stale/invalid/unknown 0 |
| POT | `i18n make-pot … --skip-block-json` + die 6 block.json-Einträge der alten POT; 5 neue Texte |

Die Test-DB `ccap_test_h5_e2e` enthält nach den Läufen keine Tabellen (Präfixe werden entfernt), keine E2E-Container und keine Sites unter `/tmp/ccap-e2e-*` von h5 bleiben zurück.
