# R1-26 · Kurs-Publish-Validator und Fehlerführung – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-26):** Autoren sehen konkrete Blocker/Warnungen; ungültige Kurse/Prüfungen werden nicht veröffentlicht; Preview testbar.
**Quellen:** R0-22 §1 (Grundregeln, `ValidationReport`, Bestätigung per Fingerabdruck, Katalog VAL-000…145), §2.4/§2.5 (alle Wege durch den Validator), §3.1/§3.3 (Panel, Zusammenfassung). Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Publish/CourseValidator.php` | deterministischer, serverseitiger Validator auf dem Kandidaten (Live-Entwurf) mit Report nach §1.2 (`course_uid`, `context`, `candidate_hash`, `validated_at`, `validator_version`, `summary`, `issues` mit Code, Schwere, Fingerabdruck, Objekt, Feld, Meldungsschlüssel, Parametern, Meldung, Sprungziel, `acknowledged`); Regelfehler → Blocker `VAL-000`. Umgesetzte Regeln: **Kurs** VAL-001, 002, 003, 004 (W), 006 (H), 008 (H); **Struktur** VAL-020, 021, 023, 024, 025, 026, 027 (Platzhalter als Hinweis), 029, 033 (H); **Fragen** (alle Poolfragen inkl. unveröffentlichter) VAL-040, 041, 042/043, 044, 045, 047, 048 (W), 049, 052 (W), 053, 054, 055, 056, 058 (W), 063, 066, 067, 068; **Prüfung** VAL-073, 074, 079, 082, 083, 084, 085, 086 (Nachweis-Prüfung) bzw. 087 (W, Übung), 089, 090; **Voraussetzungen** VAL-120 (Zyklus), 121, 127 |
| `src/Admin/AuthoringService.php` | Veröffentlichen nur über den Validator: Blocker → `ccap_validation_failed` (422, kein Override); unbestätigte Warnungen → `ccap_validation_unacknowledged` (409); Audit `course_published` mit Revision, Änderungsklasse und bestätigten Fingerabdrücken |
| `src/Admin/AuthoringRoutes.php` | `GET /courses/{id}/validation`; `POST /courses/{id}/publish` nimmt `acknowledged[]` und liefert bei Ablehnung den Report in `data.report` |
| `src/Admin/EditorScreens.php` | Panel im Veröffentlichen-Kasten: Zusammenfassung (`role="status"`, `aria-live="polite"`), Blocker/Hinweise als Text, Warnungen mit Bestätigungs-Kästchen je Fingerabdruck |
| `src/Publish/PublishGuard.php` | Statuswechsel auf „veröffentlicht“ im Admin/Block-Editor (REST) bleibt bei Blockern Entwurf, mit Hinweis |
| `src/Ops/AuditView.php` | Ereignis `course_published` registriert |
| `tests/integration/PublishValidatorTest.php` | 6 Tests; `tests/integration/AuthoringTest.php` an gültige Kurse angepasst |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **162 / 1 640** · Multisite **171 / 1 693** · Free-Guard 0 · Textdomain-Lint 0 · `.pot` aktualisiert.

## 3. Tests

| Test | Inhalt |
|---|---|
| gültiger Kurs | keine Blocker/Warnungen, Report-Format, Veröffentlichung, VAL-008 danach, Audit |
| Struktur | leerer Kurs VAL-001/003; leeres Modul, Lektion ohne Typ, leere Lektion, Video ohne Quelle → VAL-020/023/026/027; Veröffentlichen 422, keine Revision |
| Fragen | widersprüchliche gleiche Optionen VAL-047, alle richtig VAL-052, unveröffentlichte Poolfrage VAL-058 |
| Prüfung/Voraussetzungen | leerer Pool VAL-085; zu kleiner Pool (Nachweis) VAL-086 mit `{available: 1, required: 5}`; Übung VAL-087; ungültige Quote VAL-074; Zeitlimit > 24 h VAL-079; Zyklus VAL-120; fehlende Activity VAL-121 |
| Bestätigung | Warnung VAL-004 ohne Bestätigung 409; mit Fingerabdruck veröffentlicht; Audit enthält die Fingerabdrücke; fremder Fingerabdruck zählt nicht |
| Wege | REST-Validierung 200 mit Blockern; Publish 422 mit Report; Editor-Statuswechsel bleibt Entwurf mit Hinweis; nach Korrektur veröffentlicht |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | VAL-020 „Modul ohne Lektionen“ vs. Fixture R0-05 §2.2 (reine Fragenmodule M-A1…) | Blocker nur bei Modul **ohne Lektionen und ohne Fragen**; **Klärung** |
| A-2 | Import-Aktivierung (§2.5) | Import nutzt den Publisher direkt; Report im Import-Dry-run und blockierende Aktivierung mit R1-41 (Medien-/Blueprint-Import, sonst würden Video-Lektionen ohne Medien blockieren) |
| A-3 | Medien-/Rechte-Regeln VAL-100…114, Barrierefreiheit VAL-140…145, Zertifikat VAL-130…137, Drip/Erreichbarkeit VAL-122…126, 128, Kurs VAL-005/007/009/010/011/012, VAL-030…032/034…039, VAL-046/050/051/057/059…062/064/065/069, VAL-075…078/080/081/088/091…098 | Datenmodell-Felder fehlen (Rechte, Alt-Text, Gültigkeit, Teile, Lernziele) oder Abhängigkeit zu R1-34/R1-44/R1-46/R1-27; Regeln folgen mit diesen Aufgaben |
| A-4 | Korrekturhilfen (`fixes`, quick_fix mit Vorschau), Sprung mit Fokus, Inline-Fehler | Report enthält `target`; interaktive Hilfen mit R1-32/R1-19 |
| A-5 | Vorschau als Lernender (§2.3) | WP-Vorschau der Lektion; eigene Lernenden-Vorschau mit R1-27 (Staging) |

Review durch zweite Person vor Merge (AG-05).
