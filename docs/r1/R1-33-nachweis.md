# R1-33 · Kurs-/Prüfungs-Overrides und Snapshot-Policy – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-e`
**DoD (TODO R1-33):** Vererbung, Preview, Änderung laufender Versuche und historische Auswertung getestet.
**Quellen:** R0-28 §1 (Ebenen, Klassen L/K/R/G/E/A, Resolver §1.3, Feldtabelle §1.4, Constraints C-1…C-6, Sperre §1.6, UI-Vertrag §1.7), §2 (Snapshot-Regeln SN-1…SN-6), §3 (Zustandsmatrix, K-1…K-8), §4.1 (Reset/Undo), §5 (INH-001…049), §7 (T28-2, T28-3, T28-5, T28-6, T28-7); R0-27 §1.3 (Register), R0-08 §6.3 (Prüfungs-Snapshot), R0-23 RV-01/REV-020, R0-33 (`_ccap_listing`/`_ccap_seo_index`), R0-29 SET-090 (`auto_issue`). Clean Room (E-01): kein Fremdcode.

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Settings/EffectiveSettings.php` (neu) | **einziger Resolver** global → Kurs → Prüfungsregel (T28-6): Vorrang Sperre > Regel/Activity > Kurs (Gruppe, Medium) > global > Schema-Default (§1.3 Schritte 1–4, `source` = `global_lock`/`exam_rule`/`activity`/`course`/`group`/`media`/`global`/`schema_default`), Blatt-Merge für Objekte (`learning.feedback`, `exams.feedback`, `certificates.base_template`), Monotonie (C-1: `invite_valid_days` nur kürzer, `external_media_mode` nur strenger; lockerer Override ruht mit `chain: stricter_global`), „erben“ = Schlüssel fehlt (kein Sentinel, `null` bleibt Wert); Override-Validator je Scope (`ccap_setting_not_overridable`/`ccap_validation_failed`/`ccap_setting_not_stricter`); **Kurs-Overrides** im Entwurf (`_ccap_settings = {_schema, overrides}`, `general.catalog_indexing` über die bestehende Live-Meta `_ccap_seo_index` aus R1-37), Speichern alles-oder-nichts bei fremden Schlüsseln, gesperrte Schlüssel einzeln abgewiesen (K-2), Reset mit Undo (`_ccap_settings_undo`, eine Generation, 30 Tage); Laufzeitwert je Kurs aus der veröffentlichten Revision (`for_course`), Klasse K aus `resolved`; **Sperren** (`course_captain_setting_locks`, #19, Begründung ≤ 200, Preflight `timer_mode = practice` gegen veröffentlichte Nachweisregeln → `ccap_setting_lock_conflict`, Zählung ruhender/reaktivierter Overrides); Panel-Daten (§1.7) und **Nachteilsausgleich** beim Versuchsstart (Regel > Kurs > Site, `valid_until`, 100–400 % oder ohne Limit) |
| `src/Settings/SettingsSchema.php` | nur Override-Metadaten `overridable()` je Register-Key: `scopes`, `class` (L/K/R/G/E/A), `lockable`, `unit` (Feld/Blatt), `monotonic` (T28-5); Register und Validator unverändert |
| `src/Exam/ExamRules.php` | Regeleinstellungen mit Register-Bezug (#9–#18, `REGISTER_MAP`) werden nur noch als **Overrides** gespeichert (`settings = {_format: 2, overrides, purpose, grace_seconds, …}`), übrige Regelfelder explizit; Entwurf löst live auf (Vorschau), **Veröffentlichen materialisiert** `resolved {key: {v, src}}` und das wirksame Zeitlimit (E-227), Sperren wirken danach live ab dem nächsten Start (E-225); Altbestand ohne `_format` gilt vollständig als explizit (SN-5, keine Umschreibung); `set_overrides()` für Entwürfe (`inherit` entfernt), `panel()` (Wert, Herkunft, Klasse); Publish-Blocker für Nachweisregel mit `timer_mode = practice` (INH-018); C-6 (`auto_submit_inactive_days` nur ohne Zeitlimit) |
| `src/Exam/ExamSessions.php` | Snapshot `format_version = 2` mit `settings_provenance {key: {v, src, ref}}` und `accommodation {factor_pct, unlimited, scope}` (T28-2); Frist nach Ausgleich; `settings_provenance()` liest historische Werte nur aus dem Snapshot, ältere Snapshots → `unknown` (SN-3/SN-4) |
| `src/Course/CoursePublisher.php` | Kursrevision friert `settings {schema, overrides, resolved}` (Klasse K) im Struktur-JSON ein (T28-3); eine geänderte geerbte K-Einstellung erzeugt bei der nächsten Veröffentlichung eine neue Revision |
| `src/Certificate/CertificateService.php` | `auto_issue` (Klasse E) je Kurs aus der veröffentlichten Revision; Basisvorlage (Klasse A) mit Kurs-Blättern; gleiche Definition behält ihre `template_version` |
| `src/Admin/CourseSettingsPanel.php` (neu) | Kurseditor-Box „Settings for this course“: je Schlüssel `fieldset/legend` mit Radiogruppe „Use the default“/„Set for this course“, Text „Inherits from the website: …“ bzw. „The default would be: …“ (+ „currently the same as the default“, K-8), Klasse als Text; gesperrt: schreibgeschütztes Feld mit `aria-readonly`, Begründung, ruhender Wert mit „Remove resting value“; Reset/Undo; Panel „Effective settings“ (Entwurf, für Lernende jetzt, Herkunft, Wirkzeitpunkt) für Kurs und jede Prüfungsregel; Speichern mit Nonce vor der Revisionsveröffentlichung (Priorität 15) |
| `tests/integration/EffectiveSettingsTest.php` (neu) | 9 Tests |

**Änderungen an gemeinsam genutzten Dateien:** `src/Kernel/Plugin.php` (1 Registrierung), `src/Kernel/UninstallManifest.php` (Option `course_captain_setting_locks`, Post-Meta `_ccap_settings`, `_ccap_settings_undo`), `src/Ops/AuditView.php` (`settings_lock_set`, `settings_lock_removed`, `course_settings_saved`, `course_settings_reset`), `src/Settings/SettingsSchema.php` (nur `overridable()`). Keine neuen Hooks, REST-Routen oder Tabellen; Contract-Snapshots unverändert.

## 2. Feld-/UI-Vertrag

| Ebene | Speicher | Wirksam |
|---|---|---|
| global | `course_captain_settings_<tab>.values` (R1-31) | nach Klasse |
| Sperre | `course_captain_setting_locks.<key> = {reason, by, since}` | Klasse L/K/G/E/A sofort bzw. beim nächsten Ereignis; R ab nächstem Versuchsstart, auch gegen veröffentlichte Regeln |
| Kurs | `_ccap_settings.overrides` (Entwurf) → `ccap_course_revision.structure.settings` | ab Veröffentlichung der Kursrevision; `general.catalog_indexing` live (`_ccap_seo_index`) |
| Prüfungsregel | `ccap_exam_rule.settings.overrides` (Entwurf) → `settings.resolved` + Spalte `time_limit_seconds` | Entwurf: Vorschau live; veröffentlicht: eingefroren |
| Versuch | `ccap_exam_session.snapshot` (`timing`, `attempt_policy`, `feedback`, `settings_provenance`, `accommodation`) | unveränderlich |

## 3. Ergebnisse (16.11.2026)

phpcs 0 Fehler (1 bekannte Warnung ImportTest.php:318) · PHPStan L8 0 · Unit **104 / 2 816** · Integration **297 / 8 019** · Multisite **309 / 8 105** · Free-Guard 0 · Textdomain-Lint 0. `.pot` nicht aktualisiert (Vorgabe).

## 4. Tests

| ID | Test |
|---|---|
| INH-001 / 002 / 004 | jede Override-Zeile ist Register-Key mit Klasse, Sperrflag und Label; globale Schlüssel nicht überschreibbar; erben/Override/Leaf-Merge Kurs + Activity; `max_attempts = null` als Regel-Override bleibt unbegrenzt, Erben ergibt 3, kein Sentinel `inherit` im Speicher; fehlender Schlüssel in der Option → `schema_default` |
| INH-003 / 005 | `general.course_slug` und `exams.max_attempts` als Kurs-Override, ungültiger Enum-Wert, Fremdschlüssel in Regel-Overrides → `ccap_validation_failed`, nichts gespeichert; Abonnent 403/404; unbekannter Kurs 404 |
| INH-009 / 010 / 011 | Breadcrumbs global aus → an: erbender Kurs sofort, Kurs mit Override (= alter Standard) unverändert; `course_completion_default` global geändert: veröffentlichte Revision unverändert, Panel zeigt Entwurf/veröffentlicht getrennt mit „applies from the next publication“, nächste Veröffentlichung = neue Revision mit `src = global`; Kurs-Override `auto_issue` erst nach Veröffentlichung wirksam; Audit |
| INH-015 / 019 / 022 / 047 | Regelentwurf erbt Kulanz/Cooldown, Vorschau folgt globaler Änderung, Veröffentlichung materialisiert `{v, src = global}`, spätere Änderung ändert die Regel nicht; Versuch startet mit 300 s, globale Änderung danach ändert den laufenden Snapshot nicht; Provenienz `src`/`ref` im Snapshot, historischer Wert nur aus dem Snapshot, alter Snapshot `unknown`; neue Version mit `max_attempts = 5`; Entwurf-Override setzen/zurücksetzen, veröffentlichte Regel `ccap_rule_frozen`, Panel |
| INH-006 / 032 / 035 / 036 / 037 | Sperre durch Redakteur ohne #19 → 403, nicht sperrbarer Schlüssel 422; Sperre `late_tolerance_seconds = 0` bei Regel mit eigenem Wert 120: Audit `resting = 1`, nächster Start 0 mit `global_lock`, neuer Regel-Override abgewiesen; Aufheben → wieder 120, Audit; K-2 in beiden Reihenfolgen (ruht bzw. `ccap_setting_locked`, übrige Felder gespeichert); Editor zeigt `aria-readonly`, „rests“, `legend`; Sperre `timer_mode = practice` mit veröffentlichter Nachweisregel → `ccap_setting_lock_conflict`, keine Sperre gespeichert |
| INH-016 / 017 / 018 | Zeitlimit 60 min, Ausgleich Kurs 150 % und Regel 200 % → 120 min, Scope im Snapshot; Sperre auf 30 min + Ausgleich „ohne Limit“ → ohne Frist (C-5); Nachweisregel mit `timer_mode = practice` → Publish-Blocker, Übungsregel veröffentlichbar |
| INH-027 / 028 | Kurs überschreibt `issuer_name`, globale Fußzeile geändert → neue Ausstellungen mit Kurs-Aussteller + neuer Fußzeile und neuer `template_version`, gleiche Definition gleiche Version; `auto_issue` global aus, Kurs an → nur dieser Kurs |
| INH-030 / 033 | Gruppe 7 bei global 30 gültig, 40 abgelehnt, Kurs-Scope abgelehnt; Medium `click_to_load` gültig, Lockerung abgelehnt; global strenger als Override → globaler Wert, `stricter_global`, ruht |
| INH-042 | Formular-Abbildung (Modus/Wert, leer = unbegrenzt, Indexierung über SEO-Meta), Reset aller Overrides mit Audit, Undo stellt her, nur eine Generation |

Bestehende Suiten (ExamTest, CertificateTest, CourseModelTest, PublishValidatorTest …) laufen unverändert auf dem neuen Regel-Speicherformat.

## 5. Security-/Accessibility-Review

- Rechte: Kurs-Overrides nur mit M-03 (`EDIT_COURSE`, 404 ohne Existenzleck für unbekannte Kurse), Regel-Overrides nur im Entwurf mit M-06, Sperren nur mit #19; Formular mit Nonce je Kurs; alle Eingaben über den Register-Validator; Ausgaben escaped.
- Keine personenbezogenen Daten in Sperrbegründung (Klasse `internal`, Text bereinigt); Nachteilsausgleich steht nur im Snapshot, nie in Ergebnis oder Nachweis (E-122).
- A11y: Zustände als Text (erbt/festgelegt/gesperrt/ruht), Radiogruppen mit `fieldset/legend`, Hinweise per `aria-describedby`, gesperrtes Feld `readonly` + `aria-readonly`, Tabellen mit `caption` und `th scope`.

## 6. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Sperren in `course_captain_settings_<tab>.locks` (T28-5) | eigene Option `course_captain_setting_locks`, damit `Settings`/Tab-Speicher (R1-31/R1-34) unverändert bleiben; Tab-Reset und Settings-Export mit Sperren (E-230) folgen mit R1-34/R1-36 |
| A-2 | Spalte `ccap_course_revision.settings` (T28-3) | im Struktur-JSON (`structure.settings`) ohne Schemaänderung; der Strukturhash deckt die Einstellungen mit ab |
| A-3 | Zeitlimit-Einheit | Regel-Spalte bleibt Sekunden; Register und Provenienz in Minuten (bei krummen Sekundenwerten als Dezimalzahl) |
| A-4 | Konsumenten der L/G-Felder (`display.*`, `learning.favorites_enabled`, `mistake_training_enabled`, `enrollment.self_enrollment`, `default_access_days`, `groups_enabled`) und K-Feld `course_completion_default` in der Abschlusslogik | Resolver und `for_course()` liefern die Werte; die Verdrahtung in Darstellung, Selbstanmeldung, Grants und Fortschritt erfolgt mit R1-36 (globale Settings in Diensten) bzw. R1-10-Folge; hier nur Prüfung, Zertifikate und Indexierung |
| A-5 | Hinweis-Dialog „betrifft N Kurse“ (§3.3), Index `course_captain_override_index`, Sammel-Reset über mehrere Kurse, Kurs-zu-Kurs-Übernahme, Paket-Export/-Import der Overrides (§4.2/§4.3), Wartungsfenster W1 (SN-6) | nicht gebaut: Dialog/Index gehören zu den globalen Tabs (R1-32/R1-34), Paketformat zu R1-15/R1-36; `count_overrides()` liefert die Zählbasis |
| A-6 | Activity-Overrides (`learning.feedback` je Quiz), Gruppen- und Medien-Overrides | Resolver und Validator unterstützen die Scopes; Speicherorte am Quiz/Gruppe/Medium und deren UI folgen mit R1-09-Folge, R1-35 bzw. R1-17 |
| A-7 | Regel-Editor-Oberfläche | Vorschau je Regel im Kurseditor; Bearbeiten der Regel-Overrides über `ExamRules::set_overrides()` (R1-05 A-5: Regel-Editor mit R1-32) |
| A-8 | `show_explanations` (C-3) | Feld existiert in Free nicht (Pro, E-20); keine Free-Zeile |

Review durch zweite Person vor Merge (AG-05).
