# R0-02 / R0-03 · LearnBoating-Funktionsinventur – Übersicht

**Status:** Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet
**Quellcommit:** `hastla007/learnboating@9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (siehe `../R0-01-baseline.md`)
**Methode:** Fünf Teilinventuren mit getrenntem Dateiumfang. Jede öffentliche Methode bzw. jedes zusammengehörige Verhalten ist eine Zeile mit Quellpfad:Zeile, Art, Klassifikation, Zielbaustein, Phasenvorschlag, Task-Bezug und Golden-Master-Kandidat.

## Beschlussstand

| Beschluss | Wirkung auf die Inventur |
|---|---|
| **E-01 – Nein, Clean Room** | Kein Code aus learnboating wird übernommen. Die Inventur ist eine **Verhaltensinventur**; Quellpfad:Zeile ist nur der Beleg, wo das Verhalten beobachtet wurde |
| E-02 | Golden-Master-Fälle nur mit synthetischen, neutralen Testpaketen |
| E-03 | LB-Paketimport höchstens als separates Migrationswerkzeug |
| E-55, E-56 | Abweichungen vom LB-Verhalten gibt der Auftraggeber schriftlich über die Abweichungsliste in R0-05 frei; Referenzlauf des LB-Plugins nur durch Ausführen |
| E-54 | Jede R2-Zuordnung bekommt eine R2-Task oder wandert in den R4-Pool |

### Bedeutung der Klassen nach E-01

| Klasse | Bedeutung bisher | Bedeutung ab Beschluss E-01 |
|---|---|---|
| `reuse` | Logik fachneutral, nur umbenennen/neu namespacen (Codeübernahme vorbehaltlich E-01) | **Verhalten gleich nachbauen.** Das Verhalten wird als Spezifikation beschrieben und ohne Kopie neu implementiert; kein Code, keine Kommentare, keine Tests aus LB |
| `abstract` | generischer Kern mit fachgebundener Hülle, die neutralisiert werden muss | **Verhalten verallgemeinert nachbauen** (neutral, shop-/fachunabhängig), ebenfalls Neuimplementierung |
| `replace` | Funktion gebraucht, Umsetzung so nicht übertragbar | unverändert: Funktion gebraucht, anderes Verhalten/andere Bauweise |
| `exclude` | nicht übertragen (fachspezifisch o. Ä.) | unverändert: entfällt |

Die Spalten und Zählungen unten bleiben unverändert; nur die Lesart der Klassen ändert sich. Das gilt auch für Werkzeuge in Teil E (z. B. `tools/build-zips.sh`, `tools/i18n/check-domains.php`, `tools/i18n/make-pot.sh`): Sie werden nicht kopiert, sondern nach Verhaltensspezifikation neu geschrieben (R0-15 §3.8, R0-18).

| Teil | Datei | Umfang | Einträge | reuse (Verhalten nachbauen) | abstract (verallgemeinert nachbauen) | replace | exclude |
|---|---|---|---|---|---|---|---|
| A | `A-lernen-pruefung.md` | Learning, Exams, Trainers, Gate, ExamGuide, Tips, Faq · 39 PHP-Dateien, 10 033 Zeilen | 154 | 80 | 33 | 9 | 32 |
| B | `B-commerce.md` | Commerce, WooCommerce, Affiliate-/Voucher-Connector · 55 PHP-Dateien, 14 375 Zeilen | 177 | 65 | 89 | 16 | 7 |
| C | `C-infrastruktur-content.md` | Bootstrap, Content/Import/Medien, Infrastruktur, Settings, Security, Privacy, CLI, Extensions, 17 JSON-Schemas · 49 PHP-Dateien, 12 374 Zeilen | 199 | 97 | 71 | 20 | 11 |
| D | `D-admin-frontend-api.md` | Admin, Frontend, REST (52 Web-Routen), Mobile-API (78 Routen), Templates, Assets | 157 | 57 | 76 | 10 | 14 |
| E | `E-theme-tools-content-docs.md` | Theme, tools, schema, staging, content-source, docs, Test-/CI-Infrastruktur | 148 | 22 | 65 | 14 | 47 |
| **Summe** | | | **835** | **321** | **334** | **69** | **111** |

**`unknown`: 0.** Damit ist die DoD von R0-03 erfüllt.

Zählkommando: `grep -E '^\| INV-' <datei> | grep -c '<klasse>'`.

## Abdeckung und Restpunkte

- **Vollständig gelesen:** Teile A, B und C (sämtliche PHP-Dateien), die Schemas in C, die Routen in D sowie der Großteil von D.
- **Teil D nachgelesen:** Mobile-Mapper, Settings, RateLimiter, WebSession, TokenAuth, Idempotency, Problem, `app.js`, `admin/import.js` vollständig (INV-D-145…157). Funk-/Simulator-Ansichten in `app.js` nur mechanisch (exclude).
- **Tests:** Fachliche Testdateien sind nach Prüfabschnitten ausgewertet, nicht Zeile für Zeile. Die Zeilengenauigkeit für Golden-Master-Tests liefert R0-05.
- **Bewusst summarisch und als `exclude` eingestuft:** Gerätesimulation, Produktbilder, CSS, `content-source/` (Laravel-Snapshot).

## Wichtigste Querbefunde (für Folge-Tasks)

| Befund | Teil | Folge-Task |
|---|---|---|
| Grant-Kern ist schon weitgehend shop-neutral; nur `wc_item:<id>` und Bestell-/SKU-Spalten sind Woo-gebunden | B | R0-04, R0-06, R0-09 |
| `idempotency_key` ist nicht eindeutig; eindeutig ist nur `reference_scope` | B | R0-06, R0-21 |
| Zwei Lock-Techniken, dazu eine eigene `GET_LOCK`-Kopie im Voucher-Connector | B, C | R0-21, R0-37 |
| Gruppen entstehen nur aus Bestellungen; manuelle Gruppen ohne Kauf fehlen | B | R0-29, R1-13 |
| Fester Bestehensschwellenwert 0.75 in `ContentRepository::examRule` (widerspricht Kap. 26.4) | C | R0-08, R0-27 |
| `Settings::sanitize()` setzt bei Teilformularen alle nicht gesendeten Felder auf den Default zurück | C | R0-27, R1-31 |
| Uninstall lässt `bp_entitlement_prune_last` und `_bp_catalog_fields` zurück | C | R0-16, R1-23 |
| Monitor-Schlüssel ist per `?key=` erlaubt und landet so in Logs | C | R0-17 |
| Alle Integrationstests laufen gegen das Boots-Inhaltspaket; Fixtures müssen neutral ersetzt werden | A, C | R0-05 |
| Harte Kurscode-Tabellen (`SectionFiles::PARTS`, `RUBRIC_KEYS`, `SCHEIN_KEYS`), Theme-Filter, Rate-Limiter im Mobile-Namespace | A | R0-04 |
| Prüfungsstart-Limit hat keinen Test; `wrongQuestionIds` ist nur über die App-API positiv getestet (Korrektur aus R0-05) | A | R0-05 |
| Kernfunktionen (Web-Login, Registrierung, Limits, Gutscheine, Webhooks, Privacy-Self-Service) hängen an `Mobile\RateLimiter`/`ApiException` – müssen in den Kern, auch ohne App-API | D (INV-D-145) | R0-04, R0-19 |
| Rate-Limiter ist „fail-open“ bei Speicherausfall | D (INV-D-146) | R0-17, ENTSCHEIDUNGEN E-46 |
| In der Quelle fehlen SAST, Dependency-Scan und Paketsignatur | E | R0-18 |

Entscheidungen aus allen Teilen: `../ENTSCHEIDUNGEN.md` – alle getroffen (16.11.2026). Die Abschnitte „Entscheidungsbedarf“ der Teile A–E sind dort unter E-01…E-63 zusammengeführt und beschlossen.

## Offene Punkte nach Einarbeitung

- Die Teildateien A–E enthalten in den Spalten noch die Klassen `reuse`/`abstract` mit der früheren Lesart in Freitexten (z. B. „übernehmen“). Maßgeblich ist die Tabelle „Bedeutung der Klassen nach E-01“ oben; eine Überarbeitung der Teildateien ist nicht Teil dieser Einarbeitung.
