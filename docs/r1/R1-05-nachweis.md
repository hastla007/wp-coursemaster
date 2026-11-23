# R1-05 · WP-native Autorenoberfläche – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-05):** Kurs- und Lektionseditor, Preview, Reihenfolge, Validierung und Accessibility.
**Quellen:** R0-04/R0-08 (Struktur, Activity-Vertrag), R0-23 Δ-23-2 (Änderungsklasse), R0-19 (Namespace `course-captain/v1`, Fehlercodes), R0-05 GM-137, GM-140, GM-141; R0-07 (M-03, Nonce je Formular). Clean Room (E-01; die Quelle hat keinen Editor).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Admin/AuthoringService.php` | ein Dienst für Formulare und REST: Struktur lesen/ersetzen (Reihenfolge per Position, Pflicht, Entfernen; Validierung unbekannt/doppelt/Position, alles oder nichts; bestehende Modulregeln bleiben), Activity definieren (Provider-Schema, Typsperre nach Veröffentlichung), Modul/Lektion duplizieren als Entwurf mit `_ccap_copied_from_uid` und neuer Activity-UID, Kurs veröffentlichen mit Änderungsklasse `patch`/`minor` und bereinigter Notiz; Rechte über die Policy (M-03) |
| `src/Admin/AuthoringRoutes.php` | `GET/PUT /courses/{id}/structure`, `POST /courses/{id}/publish`, `PUT /lessons/{id}/activity`, `POST /items/{id}/duplicate`; Cookie-Auth mit `wp_rest`-Nonce, 401 ohne Login, Domänenfehler → `WP_Error` mit Code und Status |
| `src/Admin/EditorScreens.php` | Menü „Course Captain“ (Kurse, Module, Lektionen); Metabox „Module“ (Tabelle mit Caption, Positionsfeld, Pflicht- und Entfernen-Kästchen, Hinzufügen-Auswahl; ohne JavaScript bedienbar), Metabox „Revision veröffentlichen“ (Änderungsklasse, Notiz, ausdrückliches Kästchen), Metabox „Activity“ (Typ, Settings-JSON, Abschlussregel; Hinweis auf Reihenfolge per „Reihenfolge“ und WP-Vorschau); Speichern nur mit Nonce je Formular, Fehler als gebündelter Hinweis; Zeilenaktion „Duplizieren“ mit Nonce |
| `src/Admin/Notices.php` | gebündelte Hinweise (GM-140): ein Kasten, Fehler zuerst, Fehler nur vertagbar (24 h), andere ausblendbar, Zustand je Nutzer (`ccap_notice_state`, ≤ 50 Einträge), Aktionslink mit Nutzer-Nonce, Schweregrad als Text (GM-141) |
| `src/Kernel/Plugin.php`, `src/Kernel/UninstallManifest.php` | Registrierung; Meta `_ccap_copied_from_uid`, User-Meta `ccap_notice_state` |
| `languages/course-captain.pot` | neu erzeugt |
| `tests/integration/AuthoringTest.php` | 5 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **134 / 1 393** · Multisite **143 / 1 446** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0.

## 3. Tests

| ID | Test |
|---|---|
| Struktur | Reihenfolge, Pflicht, Entfernen; unbekannt/doppelt/ungültige Position abgewiesen ohne Teiländerung; ohne Recht 403/404 |
| Duplizieren/Veröffentlichen | Entwurf „(copy)“ mit gleichem Modul, `copied_from_uid` = Quell-UID, gleicher Activity-Typ, neue Activity-UID; Kurs nicht duplizierbar; unbekannte Änderungsklasse abgewiesen; `patch` mit bereinigter Notiz, Kurs veröffentlicht |
| HTTP-Varianten | 401 abgemeldet, 403/404 Abonnent, 200 Struktur, 422 Validierung, 400 unbekannter Typ, 201 Duplikat, 200 Veröffentlichen |
| GM-141 (Teil) | alle sichtbaren Felder der drei Metaboxen beschriftet (DOM-Prüfung), keine Symbole ohne Text |
| Formulare | Speichern über das Kursformular (Position, Pflicht, Hinzufügen, Revision) und das Lektionsformular; kaputtes JSON → Fehlerhinweis; gefälschte Nonce wirkungslos; Zeilenaktion nur für Module/Lektionen |
| GM-140 | ein Kasten, Reihenfolge Fehler → Warnung → Hinweis, Fehler kehren nach Vertagung zurück, Ausblenden je Nutzer, ≤ 50 Einträge, Nonce-Link |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | GM-137 (Kurs von Hand = Struktur-Hash des Imports) | braucht den Import (R1-15) und den Publish-Validator (R1-26); Editor-Pfad vorhanden |
| A-2 | GM-138 Audit-Ansicht | mit R1-17 (Diagnose/Audit) |
| A-3 | Kontrastmessung ≥ 4,5:1 und Playwright-A11y (A11Y-020) | WP-Core-Stile ohne eigene Farben; automatisierte Browser-Prüfung mit R1-19 |
| A-4 | Sortieren per Ziehen, Blockeditor-Seitenleiste (JS) | progressive Erweiterung später; Formular ohne JS ist die verbindliche Basis |
| A-5 | Fragen-Editor, Prüfungsregel-Editor | Dienste vorhanden (R1-06/R1-08); Oberflächen mit R1-32/R1-33 |
| A-6 | Rechte-Sammelfreigabe (GM-127) | R1-15 |

## 5. Übergaben

R1-15, R1-17, R1-19, R1-26, R1-28 (Fehlerkatalog vollständig), R1-32. Review durch zweite Person vor Merge (AG-05).
