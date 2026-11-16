# R1-04 · Kurs-/Modul-/Lektionsmodell – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-04):** Neutrale Kursstruktur, Revisionen, Entwurf/Publikation/Archiv, Zuordnungen.
**DoR (R0-24):** `CourseCaptain\Course` (CPT Kurs/Modul/Lektion, Revision); Tests GM-001, GM-003, LOC-001 (GM-004 Themen → R1-06/R1-12, GM-198 Bundle-Grant → R1-13, REV-047 → R1-46/R1-27); Kommando `composer test -- --group=R1-04`.
**Quellen:** R0-08 §1, §7; R0-06 §3.1, §4.1, §4.9, §5; R0-33 §1.4; R0-23 Δ-23-2/Δ-23-4; R0-25 LOC-001; Beschlüsse E-47, E-50, E-67, E-197, E-25a, E-297, E-298. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt | Quelle |
|---|---|---|
| `src/Course/PostTypes.php` | 5 CPTs (`ccap_course` öffentlich mit Slug aus `general.course_slug`, Standard `kurs`; Modul/Lektion/FAQ/Tipp nur Editor), keine Archive/Feeds/Kommentare, Cap-Mapping aus `MetaCaps::post_type_capabilities()`; Post-Status `ccap_archived` und `ccap_staged` | R0-33 §1.4, E-197, Δ-23-4 |
| `src/Course/ObjectIdentity.php` | eine UUID je Live-Objekt in `ccap_source_map` (auch lokal), nie geändert; Lektion zusätzlich `_ccap_activity_uid` (E-67); Staging-Kopien ohne Identität; Kurssprache beim Anlegen aus der Site-Sprache (LOC-001) | R0-08 §1.2, Δ-1 |
| `src/Course/CourseStructure.php` | Modulzuordnung (`ccap_course_module`, Lückenwerte 10/20/…, `required`, `rules` als JSON), Entfernen aus dem Entwurf, Reihenfolge (sort_order, Gleichstand nach ID; Lektionen menu_order, dann ID), Sprache (BCP 47) | R0-08 §1.1/§1.3, R0-25 |
| `src/Course/CoursePublisher.php` | Veröffentlichen (M-04) schreibt `ccap_course_revision` mit eingefrorener Struktur (Module, veröffentlichte Lektionen mit UID, Activity-UID/-Typ, WP-Revisions-ID, Inhalts-Hash, Reihenfolge, Pflicht, Regeln, veröffentlichte Prüfungsregel-Versionen, Zugangsregeln), `structure_hash`, `format_version 1`, `change_class`/`notes`; unveränderte Struktur → keine neue Revision; Vorgänger → `superseded`; Archivieren; Status-Hook für Block-Editor/WP-CLI | R0-06 §5, R0-08 §7.1, E-50 |
| `src/Course/DeletionGuard.php` | `pre_delete_post`: kein endgültiges Löschen von Kurs/Modul/Lektion unter Historie (Revisionen, Grants, Fortschritt, Zertifikate, Versuche, Blueprints, Abgaben); Papierkorb bleibt | R0-06 §4.9 |
| `src/Course/CourseRelations.php` | Bundle `contains`: eine Ebene, kein Selbstbezug, kein Bundle im Bundle, enthaltene Kurse werden nie selbst Bundle; idempotent | E-25a, E-297, E-298 |
| `src/Course/CourseException.php` | Fehler mit `ccap_*`-Code und HTTP-Status; `unless_allowed()` übersetzt Policy-Ablehnungen | R0-19 |
| `src/Kernel/Plugin.php` | Registrierung; `REWRITE_VERSION` 2 (einmaliger Flush wegen Kurs-Slug) | – |
| `src/Kernel/UninstallManifest.php` | Post-Meta `_ccap_activity_type`, `_ccap_activity_uid`, `_ccap_current_revision_id`, `_ccap_language` | REC-020 |
| `tests/integration/CourseModelTest.php` | 9 Tests | – |
| `tests/unit/KernelContractTest.php` | Präfix-Scan erlaubt `_ccap_`-Meta, wenn sie im Manifest steht | KOMP-130, E-207 |

## 2. Ergebnisse (16.11.2026)

| Kommando | Ergebnis |
|---|---|
| phpcs / PHPStan L8 | 0 / No errors |
| `composer test` | OK (73 Tests, 2 486 Assertions) |
| `composer test:integration` | OK (56 Tests, 727 Assertions) |
| `composer test:integration-ms` | OK (65 Tests, 780 Assertions) |
| MS-002, Free-Guard, Textdomain-Lint | OK / 0 / 0 |

## 3. Tests

| ID | Test |
|---|---|
| GM-001 | `CourseModelTest::test_GM_001_GM_003_catalog_and_status` – Frontend-Abfrage liefert genau die veröffentlichten Kurse |
| GM-003 | dito – Entwurf/Archiv: Lernen 404, nicht sichtbar, Vorschau nur mit M-03 |
| LOC-001 | `test_LOC_001_language` |
| R0-33 §1.4 | `test_post_type_registration` |
| R0-08 §1.2 | `test_stable_uids` |
| R0-08 §1.3 | `test_module_assignment_and_order` |
| R0-06 §5 | `test_publish_creates_immutable_revisions`, `test_archive_and_republish` |
| R0-06 §4.9 | `test_deletion_guard` |
| E-25a/E-297 | `test_bundle_relations` (Zugang über Bundle: `PolicyTest::test_bundle_grants_access_to_contained_courses`) |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung | Begründung |
|---|---|---|---|
| A-1 | `copied_from_uid` (R0-08 §1.2 Duplizieren) | noch keine Spalte | Duplizieren gibt es mit der Autorenoberfläche (R1-05); dann additiv in `ccap_source_map` |
| A-2 | Entfernen eines Moduls | im Entwurf jederzeit; Lernende bleiben auf ihrer Revision bis zur nächsten Veröffentlichung | R0-06 §4.1 „sonst neue Kursrevision“ |
| A-3 | Effektive Sprache einer Lektion in mehreren Kursen | Sprache des ersten Kurses (nach ID) | R0-25 regelt den Mehrfachfall nicht |
| A-4 | Testisolation | Schema-Testbasis löscht eigene Beiträge: `START TRANSACTION` unserer Dienste beendet die äußere Transaktion der WP-Testsuite | betrifft nur Tests |
| A-5 | Uninstall | Post-Meta wird gelöscht, die CPT-Beiträge selbst bleiben | Inhaltslöschung beim Uninstall regelt R1-23 (REC-019) |

## 5. Übergaben

Staging-Kopien/Revisionswechsel für laufende Lernende (R1-27), Publish-Validator (R1-26), Kurs-SEO/Slug-Validierung (R1-37/R1-31), Autorenoberfläche (R1-05), Themen GM-004 (R1-06/R1-12), Bundle-Grant GM-198 (R1-13). Review durch zweite Person vor Merge (AG-05).
