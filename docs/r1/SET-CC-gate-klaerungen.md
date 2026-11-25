# Nachweis gate-klaerungen · Umsetzung E-392 und Fortschreibung R1-GATE-Entwurf

Stand 25.11.2026, Branch `r8-h` auf `225d57b`. Quellen: `docs/r0/ENTSCHEIDUNGEN.md` E-383…E-392, `docs/r1/gate/R1-GATE-protokoll-entwurf.md`, `docs/r1/R1-18-nachweis.md` §9.3, `docs/r1/R1-29/bereitschaft.md`, `docs/r1/SET-CC-ui-f7.md`, `docs/r1/R1-22-nachtrag-h9.md`. Clean Room (E-01): kein LearnBoating-Code.

## 1. Code (E-392)

| Punkt | Umsetzung | Test |
|---|---|---|
| Prüfungspuffer beim Abmelden leeren (PRIV-018, R1-18 g2 A-5) | **Schon vorhanden** seit R1-18 h3 (`e5b37a7`, `src/Learner/ExamBufferPurge.php`). `wp_logout` setzt das Marker-Cookie `ccap_exam_purge`. Ein Inline-Skript auf der Login-Seite (`login_footer`, immer) und im Frontend (`wp_footer`, nur mit Cookie) entfernt alle `ccap_exam_*` aus localStorage und sessionStorage und danach das Cookie. `assets/js/exam-room.js` prüft das Cookie, bevor es einen Puffer liest. In diesem Slice kein neuer Code | `BackupCheckH3Test::test_PRIV_018_logout_empties_exam_buffer` (Integration). Browser-E2E `tools/qa/e2e/exam-room.js`, Schritt „PRIV-018 logout empties the exam buffer, second account finds nothing“ (Lauf in h3: `live-smoke: OK`). In diesem Slice nicht erneut gelaufen |
| `course_captain_installation_uuid` (R1-18 g4) | Neu `src/Kernel/InstallationUuid.php`. `ensure()` legt die UUID nur an, wenn die Option fehlt (`add_option`, nicht autoload). Ein vorhandener Wert wird nie ersetzt, auch kein unlesbarer: den meldet weiter der `backup-check`. `get()` ist die bisherige Logik aus `PackageExporter::installation_uuid()` (legt beim ersten Export an oder ersetzt einen unlesbaren Wert); `PackageExporter` ruft sie nur noch auf. Aufruf von `ensure()` in `Activator::activate()` nach der Migration und in `Plugin::on_admin_init()`. So bekommt eine bestehende Installation die UUID beim nächsten Admin-Aufruf nach dem Update. `RestoreCheck::INSTALLATION_UUID` und `Rights::own_manifest()` nutzen die Konstante `InstallationUuid::OPTION` | `LifecycleTest::test_E_392_installation_uuid_on_activation_and_upgrade`: Aktivierung legt eine gültige UUID v4 an (autoload `off`). Erneute Aktivierung behält sie, und Exporte nutzen sie. Fehlt die Option, legt `on_admin_init` sie an; sie bleibt stabil. Ein unlesbarer Wert bleibt nach Aktivierung und Admin-Aufruf stehen. Angepasst: `LifecycleTest::test_LIC_008_…` (die Option gehört jetzt zum Aktivierungsstand), `BackupCheckH3Test` (der Fall „keine UUID“ löscht die Option vorher) |
| `intl` als Systemvoraussetzung | `readme.txt` (Installation, Schritt 2), `docs/user/installation.md` §1 (neue Zeile „PHP-Erweiterung `intl`“). Neue Prüfung `php_intl` in `Compatibility::checks()`: `ok` mit `Normalizer`, sonst `warn` mit Hinweis. Sie erscheint im Systemstatus (`OpsStatus::report`) und im Website-Zustand („Course Captain compatibility“). **Keine Aktivierungssperre**: `Requirements::evaluate()` und `tests/unit/RequirementsTest.php` sind unverändert | `CompatibilityTest::test_E_392_intl_requirement_check`: ohne Stub `ok`. Mit Stub `class:Normalizer => false` folgen `warn` mit Text und Hinweis, Website-Zustand `recommended`/`critical` mit dem Text in der Beschreibung. Nach dem Entfernen des Stubs wieder `ok` |

Neue Texte: 3 msgids (`php_intl`). Übersetzt in `course-captain-de_DE.po` (du) und `-de_DE_formal.po` (Sie), .mo neu gebaut, `check-address` 0 Befunde.

## 2. Dokumente

| Punkt | Änderung |
|---|---|
| R1-GATE-Entwurf | Stand 25.11.2026. §7 alle Zeilen entschieden (E-392), mit Umsetzung. §6 W-06 → E-390 (Inhaberin führt den Referenzlauf selbst durch), W-07 Inhaberin, W-09 → E-389 (gebaut, erledigt), W-12 → E-391 mit Code-Prüfung der ★-Oberflächen und den Restlücken, W-14 → E-388, W-18 nur noch Benchmark, W-20 → E-392. Kriterien: B-3 technisch erfüllt (VAL-140/141), B-4 nur noch Praxis-Benchmark offen (Matrix grün laut `SET-CC-ui-f7.md`), B-9 Oberflächen vorhanden (VAL-037…039 und Importformat weiter offen), B-10 technisch erfüllt, C-3 technisch erfüllt (Import gebaut), C-6 nur noch Cloud-VM, C-11 ohne Oberflächenlücke. §8 GM-118/125/128 und R1-22 B-3 entschieden. Tabelle „Ergebnis in Kürze“ neu gezählt: B 0/7/2/1, C 0/10/3/4 |
| W-12-Prüfung | Für jede ★-Aufgabe die Datei der Oberfläche im Code nachgesehen (`ExamsScreen`, `CourseValidator` VAL-140/141, `GradingArea`, `SelfEnrollment`, `TrainerView`, `ExamRoom::SUPPORTED`, `CertificatePages`, `AssignmentView`, `GroupsView`/`Invitation`/`LearnerRoutes` R-31/R-32): alle vorhanden. Restlücken bei Nicht-★-Aufgaben: AU-T01 (kein Lernziele-Feld), AU-T02 (kein Ziehen/Strukturbaum), AU-T03 (Video/Datei weiter als JSON; nur die Abschrift hat Felder), AU-T12 (Aufgaben-Einstellungen als JSON), UX-012, LE-T09, UX-075 |
| R1-22-Nachweis | B-3 mit E-388 ergänzt. Neu A-5: Liste der Regressionsgrenzen (`CEILING` in `PerformanceTest`): OP-01 15 (gemessen 9), OP-03 10, OP-04 **16** (Budget 8), OP-05 15, OP-06 **8** kalt / 6 mit Cache (Budget 6), OP-07 10. Kommentare in `PerformanceTest` verweisen jetzt auf E-388 statt auf „needs a decision“ (keine Grenze geändert) |
| R1-18-Nachweis §9.3 | Zeilen g2 A-5, g3 H-3/H-4/H-5, g4, g5 A-1…A-3 und Umgebung mit der Entscheidung E-392 und der Umsetzung ergänzt |
| R0-24 §4.2/§5 | DoR-Zeilen R1-22 (+E-388) und R1-18 (+E-364, E-389, E-390; über Dokument +E-362) an die neuen E-Nummern angeglichen. Skript §5: Fehler {}, unbekannte E [], §1.4 ohne Abweichung; DoR abweichend 34 statt 36 (R1-18 und R1-22 behoben, Rest unverändert und älter). Lauf in §5 eingetragen |

## 3. Gates

PHP 8.5, MariaDB 10.11:

| Gate | Ergebnis |
|---|---|
| phpcs `-q` | 0 |
| PHPStan | keine Fehler |
| Unit | 175 Tests, 3328 Zusicherungen, grün |
| Integration / Multisite | **nicht gelaufen**. Die vorgegebene Datenbank `ccap_old_runtime` wird von `tests/integration/wp-tests-config.php` abgelehnt (G-09: „DB_NAME must contain "test"“). Eine andere Datenbank war nicht erlaubt |
| ApiContractTest | nicht gelaufen (Integration); keine Hooks oder Routen geändert, Snapshots unberührt |
| free-guard | 0 Befunde (266 Dateien) |
| textdomain-lint | 0 Probleme (255 Dateien) |
| `settings-doc.php --check` | aktuell |
| `check-address` | 0 Befunde (du/Sie je 3077 Übersetzungen) |
| R0-24 §5 Skript | siehe §2 |

## 4. Offen

- Integration und Multisite müssen mit einer erlaubten Testdatenbank laufen. Betroffen sind `LifecycleTest`, `CompatibilityTest`, `BackupCheckH3Test`, `CardsRightsTest` und `PerformanceTest` (nur Kommentare).
- Rest `backup-check` (R0-16 §1.4) bleibt als Folgeaufgabe (E-392).
- B-9: Publish-Regeln VAL-037…039 und das Importformat für Aufgaben fehlen weiter.
- W-12-Restlücken (keine ★-Aufgabe): ob sie vor R1-29 gebaut werden, entscheidet E-391 nicht.
