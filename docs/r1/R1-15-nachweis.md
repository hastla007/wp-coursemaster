# R1-15 · Import/Export/Dry-run/Rollback – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**Quellen:** R0-10 §1 (Paketformat `cc-package` v1), §3.2 (V1–V6), §3.3 (Dry-run), §3.4 (Zustandsautomat mit Aktivierung), §3.6 (Revisionen, Rollback), §3.7 (Konflikte), §6.1 (Export); E-176, E-180, IMP-021. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Import/Canonical.php` | kanonisches JSON (sortierte Schlüssel, NFC, getrimmt, Objekt ≙ assoziatives Array) und Datensatz-Hash ohne `hash` |
| `src/Import/PackageValidator.php` | V1 Archiv/Grenzen, V2 ZIP-Bombe, V3 Pfade/Symlinks/erlaubte Einträge, V4 Medientyp (Allowlist, gefährliche innere Endung, deklarierter und erkannter MIME), V5 Manifest/Major/Dateien/Prüfsummen/ungelistete Dateien/`content_sha256`; liest direkt aus dem ZIP, kein Entpacken |
| `src/Import/ImportService.php` | Automat `uploaded → validated/failed → previewed → approved → importing → activating → published → superseded`, `aborted`, `discarded` (bedingte Statuswechsel, Lock `import`); `requires` geprüft (IMP-021); Dry-run ohne Inhaltsschreiben mit Aktionen create/update/unchanged/conflict/archive/error und V6-Objektfehlern (Schema, doppelte `source_id`, Hash, Referenzen im Paket oder über die Source-Map, unbekannter Typ, abhängige Objekte) in `ccap_import_issue`, Fehlerschwelle max(100, 2 %); Anwendung in Phasen Fragen → Lektionen → Module → Kurse → Archiv, HTML-Allowlist ohne script/iframe/style/on*, nur https/mailto; Archivierung statt Löschen; lokal geänderte Objekte → `conflict`, bleiben erhalten; Aktivierung schaltet die gespeicherte Fragenrevision (bekannter Hash → reaktiviert) und veröffentlicht Kursrevisionen erst am Ende; Item-Log; Audit; Rollback = neuer Batch aus der Paketkopie mit Pflichtbegründung |
| `src/Import/PackageExporter.php` | Export von Kursen mit Modulen, Lektionen, Poolfragen; nur `source_id`s, keine IDs, keine Lernendendaten |
| `tests/integration/ImportTest.php` | 4 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **138 / 1 453** · Multisite **147 / 1 506** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · `.pot` neu.

## 3. Tests

| Test | Inhalt |
|---|---|
| Validierung | gültiges Paket ok; kein ZIP (V1), `../`-Pfad (V3), fremder Eintrag (V3), fehlende Datei/Prüfsumme/ungelistet (V5), `x.php.png` (V4), falsches Format/Major (V5), unbekannter Fragetyp in `requires` → `failed`; Abonnent darf nicht hochladen |
| Lebenszyklus | Erstimport veröffentlicht Kurs mit Revision, Struktur, Lektionen im Modul, HTML gefiltert, Fragen veröffentlicht; gleiches Paket → alles `unchanged`, keine neue Kursrevision, Vorgänger `superseded`; v2: Frage `update` mit neuer Revision, entfernte Lektion archiviert, lokal geändertes Modul `conflict` und erhalten; Rollback mit Begründung → alter Hash reaktiviert, Lektion wiederhergestellt, Audit |
| Objektfehler | Hash-Fehler und Abhängige markiert, Batch bleibt `previewed`, 3 Issues, Dry-run schreibt nichts, falscher Zustand 409, `discarded` |
| Export | Kurs/Modul/Lektion, Hash stimmt, keine Lernendendaten, Paket besteht V1–V5; ohne Recht 403/404 |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Zeitscheiben, Batches ≤ 200, Wiederaufnahme nach Abbruch, CSV-Report, Bildschirmliste ≤ 50 | R1-41 (Großimport) |
| A-2 | Entitäten `media` (Binärdateien in die Ablage), `blueprints`, `flashcards` | Validierung V4 vorhanden; Import der Entitäten mit R1-41 |
| A-3 | Lektionen werden in `importing` geschrieben (offener Punkt R0-10 §3.4 a/b) | Kurs-Revisionen erst in `activating`; Lernende sehen den Revisionsstand; Entscheidung a/b mit R1-27 |
| A-4 | Rechtemodell `rights` (§2), Sammelfreigabe GM-127, Speicherbedarf im Dry-run | R1-15-Folge mit R1-38 |
| A-5 | Aufbewahrung der Paketkopien (aktiv + 2) | Retention-Job R1-17 |
| A-6 | GM-137 (Struktur-Hash Handbau = Import) | mit R1-26 |

Review durch zweite Person vor Merge (AG-05).
