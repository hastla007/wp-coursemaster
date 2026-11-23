# R1-07 · Activity-Provider und Lernmedien – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD/DoR (R0-24):** `CourseCaptain\Activity` (Contract, Registry, Video, Download, Consent-Adapter); PAR-02 (GM-007…GM-016), GM-015, MED-021…MED-025; Kommando `composer test -- --group=R1-07`.
**Quellen:** R0-08 §2.1–§2.5, §5.1, §7.1; R0-10; R0-05 §1; Beschlüsse E-21, E-45/E-45a, E-65, E-67, E-68, E-168. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Activity/ActivityProvider.php` | Pflichtvertrag: Typ, `contract_version` (SemVer), `settings_version`, Fähigkeiten, Settings-Schema, Abschlussregeln, reine Abschlussentscheidung |
| `src/Activity/ActivityRegistry.php` | Registry, doppelter Typ = Fehler; Erweiterungen über `course_captain_register_activities`; Settings-Validierung; `freeze()` (Typ, Vertrags-/Settings-Version, `settings_hash`, Abschlussregel; fehlender Provider → `available: false`) |
| `src/Activity/SettingsSchema.php` | Validator für die genutzte JSON-Schema-Teilmenge (type, properties, required, additionalProperties, enum, Grenzen, Länge, pattern, items, `format: https-url`) |
| `src/Activity/Providers/*` | 7 R1-Typen: `lesson` (manual_confirm/view), `video` (manual_confirm/position_ratio, Consent-Fähigkeit), `file` (downloaded/manual_confirm), `quiz` (attempted/passed/all_correct_once), `exam` (passed/evaluated, Nachweis), `flashcard` (all_known/all_streak/ratio, Modi E-21), `assignment` (Settings §2.5; Abgabe/Bewertung in R1-46) |
| `src/Activity/ActivityService.php` | Activity einer Lektion (E-67) als Entwurfs-Meta; Schemaprüfung; Typwechsel nach Veröffentlichung gesperrt (`ccap_activity_type_locked`) |
| `src/Activity/ActivityProgress.php` | Interaktionen `position`/`confirm`/`view`/`download` nur für den eigenen Fortschritt nach Zugangsprüfung; Position bleibt ohne Feld stehen (GM-011); Abschluss monoton mit `reason_code`, `rule_version`, `content_hash`, `completed_at` |
| `src/Activity/ExternalMedia.php` | Fremdhost-Erkennung (eigene Domain/Subdomain/relativ ≠ fremd, Allowlist-Filter), Modus `always_load` (Standard, E-45a) / `click_to_load`, Video-Ansicht (Platzhalter ohne URL, nur https, Untertitel nur `vtt` über https und erst nach Klick) |
| `src/Activity/MediaLibrary.php` | Anhang → `ccap_media` (idempotent, SHA-256, MIME-Allowlist, `protected` Standard, E-65); Zuordnung zu Kurs/Modul/Lektion/Entwurfs-Fragenrevision mit Rechteprüfung |
| `src/Question/QuestionPresenter.php` | Lektionsquiz: Inline-Fragen (`ccap_activity_question`) mit gleichem Token-Verfahren (`a` = Lektion) |
| `src/Course/CoursePublisher.php` | eingefrorene Activity je Lektion in der Kursrevision |
| `src/Kernel/UninstallManifest.php` | Meta `_ccap_activity_settings`, `_ccap_completion_rule` |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85 / 2 662** · Integration **68 / 809** · Multisite **77 / 862** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · `.pot` neu.

## 3. Tests

| ID | Test |
|---|---|
| GM-007 | `ActivityTest::test_GM_007_lesson_quiz` |
| GM-008 | `test_GM_008_012_013_014_video_view` (Platzhalter ohne URL, echte URL) |
| GM-010 | Regel `attempted` in `ActivityUnitTest::test_completion_rules`; Speicherung der Lernantwort mit R1-09/R1-10 |
| GM-011 | `test_GM_011_progress` |
| GM-012 | Fremdhosts, Allowlist, Reset |
| GM-013 | Serverteil beider Modi (kein Fremdhost-URL vor Klick); E2E-Netzwerkprotokoll mit R1-16/R1-18 |
| GM-014 | Untertitel off/vtt, nur https; ungültiger Wert per Schema abgewiesen |
| R0-08 §2.2/§2.3 | Registry, Schema, Einfrieren, Typsperre |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | PHP-Interfaces | Vertrag als ein Pflicht-Interface + Registry; `Renders`/`HandlesInteraction`/`Grades`/`Exports` entstehen mit Lernoberfläche (R1-16), Prüfung (R1-08), Export/Privacy (R1-15/R1-17) |
| A-2 | Schema-Validator | eigene Teilmenge statt vollem JSON Schema 2020-12 (keine Laufzeitabhängigkeit, R0-18 T-6) |
| A-3 | Idempotenz-Schlüssel je Interaktion | Interaktionen sind natürlich idempotent (Position setzen, Bestätigen, Download); Schlüsselprüfung mit dem REST-Einstieg (R0-19 §2.1) |
| A-4 | Abschlussregel beim Lernen | Entwurfs-Definition; Umstellung auf die Regel der Lernstand-Revision mit R1-10/R1-27 |
| A-5 | GM-015/GM-016, MED-021…025 | Download-Auslieferung, Teilumfang und Consent-Browserfälle gehören zu R1-14/R1-34/R1-30 (DoR-Zuordnung in R0-05) |

## 5. Übergaben

Antwortspeicherung/Lektionsabschluss durch Quiz (GM-010) → R1-09/R1-10; Download-Endpunkt (GM-015/016) → R1-14; Consent-API (MED-024) → R1-34; Abgabe/Bewertung → R1-46; Review durch zweite Person vor Merge (AG-05).
