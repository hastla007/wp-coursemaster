# R1-46 · Text-/Dateiaufgaben mit Bewertung (aus R2-02 vorgezogen) – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-c`
**DoD (TODO R1-46, Beschluss E-168):** Abgabe-Workflow draft/submitted/returned/graded, sichere Uploads (R0-10), Bewertung durch Trainer, Benachrichtigung; Tests und Nutzertest-Referenzkurs mit Aufgabe (R0-26).
**Quellen:** R0-08 §2.4/§2.5 (Settings, Zustände, Übergänge, Bewertungsrecht E-288, Sichtbarkeit, Ereignisse E-290), §4.6 (append-only Neubewertung), §5.1 (Monotonie), §7.1 (eingefrorener Stand), Δ-8; R0-10 §4.5 (MED-027…030, 033, 034; E-289); R0-19 R-46, R-54…R-58, §2.1 (Idempotenz, Fremd = 404, Pagination), §5.3 (`assignment.*` nur intern); R0-23 RV-22 (REV-047/048); R0-07 M-10a; R0-27 N-14…N-16 (NTF-035…037), SET-134; R0-31 RT-45; R0-05 GM-195/GM-196. Clean Room (E-01): neu, ohne LB-Vorbild.

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Assignment/AssignmentService.php` | Abgabekette je Lernendem und Lektion (`submission_no`); `view` (R-54), `save_draft` (R-55, CAS über `If-Match`), `add_file`/`remove_file` (R-56), `submit` (R-57, natürlich idempotent), `detail`, `queue` (R-46), `grade` mit `action grade|return` (R-58, `Idempotency-Key` Pflicht). Beim Einreichen werden `content_hash` (Text + SHA-256 je Datei), `settings_hash`, die Settings selbst und `course_revision_id` eingefroren; Bewertung und Neubewertung rechnen gegen diesen Stand (RV-22). Rückgabe nur mit Kommentar; Bearbeiten/Einreichen nach `returned` legt Fassung n+1 an (Text und Dateiverweise übernommen), Fassung n bleibt lesbar. Neubewertung nur mit Begründung als neue `evaluation_no`; Completion bleibt (Monotonie). Audit `assignment_returned`/`assignment_graded`/`assignment_regraded`; Outbox `assignment.submitted|returned|graded` ohne Inhalt und Punkte. Privacy-Export/-Eraser und Retention RT-45 |
| `src/Assignment/AssignmentFiles.php` | Typprüfung nach V4 (Endung aus Allowlist, gefährliche innere Endung, deklarierter und per `finfo` erkannter MIME, Office-Marker für docx/odt), Größen-/Anzahlregeln (kleinster Wert aus PHP/WP-Limit, Website, Aufgabe), Filter `course_captain_assignment_file_scan` vor der Ablage, Ablage unter `protected-<geheim>/assignments/<submission_uid>/<zufall>` ohne Endung (Name nur Metadatum, nie Mediathek), Auslieferungsrecht (Eigentümer oder M-10a, fremder Abruf auditiert `assignment_file_accessed`, sonst 404) |
| `src/Assignment/AssignmentRoutes.php` | REST R-46, R-54…R-58 plus `GET /assignment-submissions/{uid}` und `DELETE …/files/{media}`; Login Pflicht (401), `Cache-Control: private, no-store`, Fehler-Mapping inkl. `data`; Upload nur echte PHP-Uploads (`is_uploaded_file`, in Tests `COURSE_CAPTAIN_TESTING` + Temp-Verzeichnis) |
| `tests/integration/AssignmentTest.php` | 9 Tests, 159 Zusicherungen (Abschnitt 3) |
| `docs/r1/R1-46-nachweis.md` | dieser Nachweis |

**Geänderte gemeinsame Dateien**

| Datei | Änderung |
|---|---|
| `src/Storage/Schema.php` | `ccap_assignment_submission` um `content_hash`, `settings_hash` (`char(71) NULL`), `settings`, `return_comment` (`longtext NULL`) ergänzt – Felder aus R0-08 Δ-8/§7.1, die in R0-06 §4.4a fehlten (dbDelta, nur nullable Spalten) |
| `src/Activity/Providers/AssignmentProvider.php` | Schema: `instructions`, `text_limits{min_chars, max_chars}`, `file_rules{allowlist ⊆ pdf/docx/odt/txt/jpg/png, max_files ≤ 20, max_bytes}`; Konstanten `FILE_TYPES`, `TEXT_DEFAULT_MAX` (20 000), `TEXT_HARD_MAX` (200 000) |
| `src/Activity/ActivityProgress.php` | `record_evidence()` für serverseitige Nachweise (`submitted`, `graded`, `passed`) ohne erneute Zugangsprüfung; Bewertung/Speicherung aus `interact()` in private Helfer ausgelagert (Verhalten unverändert) |
| `src/Access/ProtectedFiles.php` | Medien mit `source_system = assignment` werden über `AssignmentFiles::resolve()` autorisiert und immer als Download mit `CSP: sandbox` ausgeliefert; `headers()` mit optionalem `$attachment` |
| `src/Privacy/PrivacyService.php` | Befund B-4 behoben: Eraser löscht Abgaben, Auswertungen, Dateizeilen, Medienzeilen und Dateien; als Bewertende wird die Person in `graded_by` anonymisiert (0). Export über `AssignmentService::export_items()` (Fassungen inkl. Rückgabekommentar, Dateimetadaten, Auswertungen) statt der flachen Gruppe |
| `src/Privacy/RetentionService.php` | Schritt `RT-45_assignments` (2 Jahre nach Kursende wie RT-04) |
| `src/Notification/NotificationService.php`, `NotificationTypes.php` | N-14…N-16 verdrahtet (`wired = true`): N-14 an jeden zugewiesenen Trainer des Kurses, der den Lernenden abdeckt (Dedupe `submission_uid:submission_no`), N-15/N-16 an Lernende (Dedupe `:returned` bzw. `:evaluation_no`); vor dem Versand Zustand und Bewertungsrecht geprüft, nur Link in der Mail |
| `src/Settings/SettingsSchema.php` | Tab `learning`: `assignment_file_types`, `assignment_max_file_mb` (20), `assignment_max_files` (5) = Register `assignments.*` (SET-134); keine neue Option |
| `src/Ops/AuditView.php` | Aktionen `assignment_graded`, `assignment_regraded`, `assignment_returned`, `assignment_file_accessed` (GM-139) |
| `src/Rest/RestContract.php` | Titel/Meldungen für `ccap_submission_not_editable`, `ccap_submission_incomplete`, `ccap_submission_file_rejected`, `ccap_grade_invalid` |
| `src/Kernel/Plugin.php` | `AssignmentRoutes::register()` |
| `docs/api/routes.v1.json`, `docs/api/hooks.v1.json`, `docs/API-CHANGELOG.md` | Snapshots neu erzeugt, Changelog-Einträge |

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler (bekannte Warnung ImportTest.php:318) · PHPStan L8 0 · Unit **104 / 2 818** · Integration **297 / 7 772** · Multisite **309 / 7 141** · Free-Guard 0 · Textdomain-Lint 0. `.pot` nicht aktualisiert (Vorgabe).

## 3. Tests (`@group R1-46`)

| Test | Spec-ID | Inhalt |
|---|---|---|
| `test_GM_195_state_machine` | GM-195 | Entwurf 2× → `draft`, CAS-Konflikt 409, zu langer Text 422; Einreichen → `submitted`, `submission_no = 1`, Inhalts-Hash, Wiederholung ohne Doppelung; Änderung danach 409 `ccap_submission_not_editable`; `settings_hash`/Revision eingefroren; Rückgabe ohne Kommentar 422, mit Kommentar `returned`, Kommentar für Lernende sichtbar, Rubrik nicht; Fassung 2, Fassung 1 lesbar; 11 Punkte und 6,5 Punkte 422; 7/10 → `graded`, `passed = true`, Activity `completed` (`graded`); gleicher Key → kein zweiter Datensatz, anderer Inhalt → 409; Neubewertung ohne Begründung 422, mit Begründung `evaluation_no = 2`, Completion bleibt; Audit; U-X 404; Selbstbewertung 403 |
| `test_GM_196_files_rights_and_privacy` | GM-196, MED-027…030 | PDF 1 MB angenommen; `x.php.png`, SVG, `.htaccess`, MIME-Abweichungen, 3 MB, nicht erlaubter Typ abgelehnt, nichts gespeichert; Auslieferung `attachment`, `nosniff`, `sandbox`, RFC-8187-Name, Pfad ohne Originalnamen; U-X 404, Trainer 200 mit Audit; Upload nach `submitted` 409; ohne Grant 403 `ccap_content_locked`, keine Kette; Export enthält Abgabe und Dateimetadaten; Eraser entfernt Datei, Zeilen und Medium |
| `test_MED_033_034_limits_and_scanner` | MED-033, MED-034 | docx/odt/txt angenommen; zip und als docx getarntes zip abgelehnt; 21 MB abgelehnt; Scanner-Befund → `reason: scan`, nichts abgelegt; 6. Datei → `count`; Entfernen gibt Platz frei; `text_and_file` einreichbar |
| `test_completion_rules` | R0-08 §2.4/§5.1 | Regel `submitted` schließt beim Einreichen ab; Regel `passed` erst bei bestandener (Neu-)Bewertung |
| `test_REV_047_frozen_settings` | REV-047 | Autor ändert `max_points` 10 → 20 und veröffentlicht: Abgabe A wird gegen 10 bewertet (15 → 422, `max_points_milli = 10000`, alte Bestehensgrenze), Entwurf B reicht gegen 20 ein; `settings_hash` verschieden |
| `test_REV_048_removed_task` | REV-048 | Aufgabe aus der Revision entfernt: X-Entwurf 409 und lesbar (`editable = false`), Y weiter bewertbar, Z-Abschluss und Auswertung bleiben, keine Zeile gelöscht |
| `test_NTF_035_036_037_notifications` | NTF-035…037 | 2 Trainer + 1 Autor: je Trainer genau eine N-14, Autor/Lernender keine; N-15 einmal; N-16 je `evaluation_no`, nach Abbestellung `suppressed (opt_out)`; keine Texte, Kommentare oder Punkte in Mails |
| `test_rest_routes` | R-46, R-54…R-58 | 200 + `no-store`; `If-Match` 409; 422 `empty`; Upload 201, gleicher `Idempotency-Key` 200 ohne zweite Datei; gefälschter `tmp_name` 422 `upload`; Submit 200 (auch Wiederholung); Warteschlange für Lernende leer, Trainer `X-WP-Total = 1`; Bewertung ohne Key 400; Bewertung 200; fremd 404 |
| `test_RT_45_retention` | RT-45 | aktiver Grant hält Daten; nach Kursende (Grant abgelaufen, keine Aktivität) werden Kette und Datei gelöscht |

Der anonyme Zugriff (401 `ccap_auth_required`) aller neuen Routen ist durch CT-02/03 (`ApiContractTest`) abgedeckt.

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Schema | R0-06 §4.4a enthielt die Δ-8-Felder `content_hash`, `settings_hash`, `return_comment` nicht; die Kursrevision speichert nur den `settings_hash`, nicht die Settings. Ohne die Settings ist REV-047 (Bewertung gegen `max_points = 10` nach Änderung) nicht umsetzbar → vier nullable Spalten ergänzt (inkl. `settings`). `evaluator_version` (§7.1) entfällt: manuelle Bewertung hat keine Auswertungslogik |
| A-2 | Fehlercodes (Feinschnitt an R1-46 übergeben, R0-19 OP) | R0-08 §6.6/GM-195/REV-048 haben Vorrang vor dem R0-19-Entwurf: `ccap_submission_not_editable` 409 statt `ccap_validation_failed (not_editable)`, `ccap_submission_incomplete` 422 statt `(reason: empty)`, `ccap_grade_invalid` 422; Dateien `ccap_submission_file_rejected` mit `reason`. Fehlender Zugang liefert wie bei Lektionen `ccap_content_locked` (403) bzw. 404 statt `ccap_access_denied` |
| A-3 | Auslieferung | Abgabedateien immer `attachment` + `sandbox`, auch PDF/Rasterbild (GM-196 verlangt `attachment`; strenger als R0-10 §4.5) |
| A-4 | `text_and_file` | verlangt Text **und** mindestens eine Datei; `type` in R-54 ist der Settings-Wert (`text_and_file` statt `text_file`) |
| A-5 | Textgrenzen (OP-08-3, technisch) | Standard ohne Mindestlänge, max. 20 000 Zeichen, Obergrenze 200 000; Mindestlänge wird beim Einreichen geprüft, Höchstlänge schon beim Speichern |
| A-6 | Punkte | R-58 nimmt `points_milli`; nur ganze Punkte (Vielfache von 1000) nach §2.5 „Ganzzahl“ |
| A-7 | Lektionsadressierung | wie R-27/R-28 numerische Lektions-ID; Abgaben per UID |
| A-8 | Zusätzliche Routen | `GET /assignment-submissions/{uid}` (Bewertende brauchen Text/Dateien/Rubrik) und `DELETE …/files/{media}` (Korrektur im Entwurf); im Changelog vermerkt |
| A-9 | Fassung n+1 | übernimmt Dateiverweise der zurückgegebenen Fassung (geteilte Medien, kein Kopieren); Datei wird erst gelöscht, wenn keine Fassung sie mehr referenziert |
| A-10 | Rate-Limit | 30 Uploads je Stunde, Lernendem und Aufgabe (Bucket `assignment_upload`), Zählerausfall fail-open (E-46) |
| A-11 | RT-45 | Kursende = kein aktiver Grant mit Ende nach Stichtag und keine Kursaktivität (`last_activity_at`) bzw. Abgabeänderung nach Stichtag; Fassungen im Status `submitted` bleiben bis zur Bewertung |
| A-12 | Audit | auditiert werden Rückgabe, Bewertung, Neubewertung und fremde Dateiabrufe; das Lesen von `detail` durch Bewertende nicht |

## 5. Bewusst nicht umgesetzt

| Thema | Grund |
|---|---|
| Publish-Regeln VAL-037…039, PUB-057…059 | Validator-Aufgabe (R1-26), Folgearbeit; die Settings-Schema-Prüfung greift bereits beim Definieren |
| Lernenden-/Trainer-Oberfläche (Blöcke, Admin-Bewertungsliste) | R1-46 liefert Domäne und REST; UI mit dem Lernbereich bzw. Pro-Gradebook (E-74, E-288) |
| Nutzertest-Referenzkurs mit Aufgabe (R0-26, PKG-X1) | Paketformat/Import kennt noch keine `assignment`-Activity; folgt mit Fixture-Erweiterung |
| Zurückziehen, Retakes nach `graded`, Fristen, Gruppenabgaben, Peer-Review | laut §2.5 nicht in R1 (OP-08-4/NF-06) |

## 6. Offene Klärungen

- NF-05 (OP-23-1): entfernter Aufgabenentwurf bleibt hier schreibgeschützt lesbar (RV-22-Ableitung).
- NF-06 (OP-08-4): Standard-Abschlussregel `graded` und die Liste „nicht in R1“.
- Juristische Bestätigung der Frist RT-45 durch den Betreiber (OP-31-3, Gate A-9).

Review durch zweite Person vor Merge (AG-05).
