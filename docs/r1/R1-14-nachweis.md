# R1-14 · Zugriffsschutz und geschützte Inhalte – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-14):** serverseitige Freischaltung je Objekt, geschützte Dateien und Inhalte ohne Umgehungswege.
**Quellen:** R0-08 §1.4 (Auswertungsreihenfolge, `GateDecision`, E-14, E-27, E-286), R0-10 §4.1–§4.3 (Speicherklassen, Webserver-Regeln, Auslieferung), R0-05 GM-015, GM-016, GM-025, GM-107…GM-112, GM-116. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Access/GateDecision.php` | Ergebnisvertrag `{state, reason, unlock_at, requires, rule_refs}` + Teilumfang-Limit; `assert()` wirft bei `no_access` die Policy-Ablehnung (401/403/404 bleiben), sonst `ccap_content_locked` (403) mit Grund |
| `src/Access/ContentGate.php` | Eine Entscheidung je (Lernender, Kurs, Objekt, Zeit), erste Sperre gewinnt: 1. Modulzugang (Policy M-01; ohne Zugang gewährt eine `free_limited`-Regel Teilumfang) → 2. Teilumfang: erste *n* Lektionen/Poolfragen/Dateien in Revisionsreihenfolge (`trial`) → 3. Drip: Maximum der Regeln, ab Zugangsbeginn (frühester aktiver Grant, sonst Registrierung; je Request memoisiert) mit `unlock_at` → 4. Voraussetzungen `completed_module`/`completed_activity`/`passed_exam` mit `requires` → 5. laufende Prüfung (`exam_running`). Prüfung: alle Module mit Scope `exam` (E-286, Drip); im Teilumfang nur feste Varianten bis *n*, nie Zufallsprüfung. Injizierbare Uhr |
| `src/Question/QuestionPresenter.php`, `src/Learning/SmartTrainer.php`, `src/Activity/ActivityProgress.php`, `src/Learning/Favorites.php`, `src/Exam/ExamSessions.php` | nutzen das Gate statt einzelner LEARN-Prüfungen (Auslieferung, Token-Neuprüfung bei jeder Antwort, Kandidaten auf erste *n* je Modul beschnitten, Interaktionen, Favoriten, Prüfungsstart) |
| `src/Access/ProtectedStorage.php` | Basis `COURSE_CAPTAIN_PRIVATE_DIR`/Filter (nie stiller Rückfall), sonst `uploads/course-captain`; Ordner `protected-<geheim>`/`packages-<geheim>` mit nie ersetztem Geheimnis, `index.php`, `.htaccess` für Apache 2.4/2.2; Medienordner mit Skriptsperre (php/phtml/phar/html/svg/js …); fremde `.htaccess` bleiben unangetastet; Anhänge samt aller Bildgrößen schützen bzw. zurückverschieben (Rollback bei Fehler, sichtbarer Fehler); Pfadprüfung per `realpath`, keine Symlinks |
| `src/Access/ProtectedFiles.php` | Endpunkt `/cc-file/<media_uid>/` (Rewrite-Version 3) und `wp_get_attachment_url`-Umbiegung; Auflösung nur über UID, unbekannt/nicht geschützt 404; Rechte M-17, sonst Teilumfang über das Gate; Header `private, no-store`, `nosniff`, `sandbox` außer PDF, `inline` nur für PDF/Raster/Audio/Video/VTT, sonst `application/octet-stream`; `Content-Disposition` mit ASCII-Ersatz und `filename*=UTF-8''` (Rohtitel + Endung); Range (`a-b`, `a-`, `-n`) → 206, unerfüllbar → 416 mit `bytes */size`, mehrere Bereiche → 200; Streaming in 1-MB-Blöcken mit Abbruch; Dateiliste mit Sperrgrund (GM-015) |
| `src/Access/ContentProtection.php` | Beiträge/Seiten mit `_ccap_protected_course`: Text nur bei Zugang zu mindestens einem Modul oder Bearbeitungsrecht; spät greifende Filter für Inhalt, Auszug, Feeds und REST (Inhalt/Auszug ersetzt, `meta`/Builder-Daten entfernt); Shortcode `[course_captain_protected course=…]`; geschützte Seiten `DONOTCACHEPAGE` + No-Cache-Header; Memo je Nutzer, geleert bei Regel-/Nutzerwechsel |
| `src/Kernel/UninstallManifest.php` | Meta `_ccap_protected_course`, `_ccap_protected_origin` |
| `tests/integration/ContentGateTest.php` | 6 Tests; `tests/unit/KernelContractTest.php`: `DONOTCACHEPAGE` als einzige Fremdkonstante zugelassen (GM-116) |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **124 / 1 292** · Multisite **133 / 1 345** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · `.pot` aktualisiert (1 neuer String).

## 3. Tests

| ID | Test |
|---|---|
| §1.4 Nr. 3 | Drip: Maximum zweier Regeln, `unlock_at = Start + 3 d`; Auslieferung `ccap_content_locked`/`drip`; Smarttrainer zieht nicht aus dem gesperrten Modul; danach frei |
| §1.4 Nr. 2 | Teilumfang ohne Grant: Limit 2; Frage 3 und Lektion 3 `trial`; Smarttrainer-Umfang 4 (2 je Modul); keine Zufallsprüfung, Variante 2 erlaubt, 3 gesperrt; abgemeldet `no_access` |
| §1.4 Nr. 4 | Voraussetzung „Modul 1 abgeschlossen“ mit `requires`; nach Abschluss frei |
| §1.4 Nr. 5 / E-286 | laufende Prüfung sperrt ihre Frage (`exam_running`); Prüfung über Drip-gesperrtes Modul gesperrt mit `requires` |
| GM-015 / 016 / 111 / 112 | Schutzordner mit Geheimnis, `.htaccess` 2.2/2.4, `index.php`, Skriptsperre; Anhang verschoben, URL über die Prüfung; Teilumfang: 3 gelistet/1 frei, zweite Datei 403; Vollzugang alle frei; unbekannte UID 404; `Content-Disposition` beide Formen für `Übersicht "A".pdf`; `nosniff`, `no-store`, PDF ohne Sandbox; Range 206/416/200; Zurückverschieben |
| GM-107 / 108 / 109 / 110 | geschützte Seite: Außenstehende Hinweis, Teilnehmende und Redakteur Inhalt; Feed, Auszug und `/wp/v2/pages` ohne geschützten Text; Abschnitt gesperrt/sichtbar; neue Free-Regel wirkt sofort (Memo geleert) |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Ordnerumzug Altlayout mit Rückfall (GM-113) | mit R1-40 (Migration) |
| A-2 | Selbsttest Direktabruf, Nginx-Regel im Systemstatus (GM-115), Site-Health-Anzeige | R1-38 (Betriebsdashboard); Regeln und Ordner liegen vor |
| A-3 | Privater Ordner außerhalb des Webroots (GM-114): Selbsttest „privat“ und Rückweg | Basis-Auflösung umgesetzt; Selbsttest R1-38 |
| A-4 | Lösungsmedien nach Feedback-Regel, Medien in Prüfungsfragen nur für den Versuchsinhaber, Rate-Limit je Medium, `If-Range`, X-Sendfile | R1-20 (Integrität) bzw. R1-28; Grundauslieferung prüft M-17 + Gate |
| A-5 | Performance 10 gesperrte Beiträge ≤ 100 Abfragen (GM-110 Teil) | Messung mit R1-22; Memo je Nutzer und Kurs vorhanden |
| A-6 | REST-Route `GET /course-captain/v1/media/<uid>` | R1-28 nutzt `ProtectedFiles::prepare()`; Rewrite-Endpunkt besteht |
| A-7 | Downloadzählung ab Byte 0 / Activity `downloaded` | Anbindung an `ActivityProgress` mit der Lektionsansicht (R1-16) |

## 5. Übergaben

R1-16 (Sperranzeige mit Grund/`unlock_at`, Download-Abschluss), R1-20, R1-22, R1-25 (Cache-Gruppe), R1-28 (REST-Routen), R1-38 (Selbsttest/Health), R1-40 (Ordnerumzug). Review durch zweite Person vor Merge (AG-05).
