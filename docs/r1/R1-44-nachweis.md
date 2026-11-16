# R1-44 · Basisnachweis-Domäne (Zertifikat Free) – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-c`
**DoD (TODO R1-44):** Ausstellung, Einmaligkeit, Snapshot, Korrektur, Widerruf, Verifikation nach R0-08 §7 und R0-23 §5; R1-35 baut darauf Verwaltung und UI.
**Quellen:** R0-29 §2 (CR-1…CR-7, CF-01…CF-14, §2.4 Datenvertrag, §2.5 Feld-Policy, §4.4 CRT-001…029), R0-08 §5.3/§7.2, R0-23 §5, R0-19 (R-40, R-42, Block `course-captain/certificate-verify`, Events `certificate.*`), R0-27 SET-090…092, R0-31 RT-09/RT-10/RT-16. R0-05 enthält keine Zeile mit Ziel R1-44. Clean Room (E-01): kein Fremdcode, für Zertifikate gibt es keinen LB-Bestand.

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Certificate/CertificateService.php` | einziger Schreiber von `ccap_certificate` (CR-1): automatische Ausstellung als interner Outbox-Abonnent `certificates` (`course.completed`, `exam.passed`, `exam.reevaluated` nicht bestanden → bestanden), manuelle Ausstellung aus vorhandener Evidenz (CF-03, M-19, Pflichtgrund), Idempotenz je Evidenz (`UNIQUE evidence_rev` + `FOR UPDATE`, verlorenes Rennen liefert die bestehende Zeile), E-113-Prüfung (Grant mit `exam` zum Abgabezeitpunkt), Korrektur (neue Revision, `supersedes_id`, alte `corrected`, Vorlage/Evidenz/Person/Kurs unveränderlich, Idempotenz über `request_key` in `ccap_request_idempotency`), Widerruf (Kategorie `fraud/invalid_assessment/issued_in_error/other` + Freitext, alle Revisionen, endgültig), Verifikation nach §2.5, Partner-Lesesemantik CRT-029, Inhaberliste R-40, Ansicht M-18, Namensfreigabe je Nachweis (Δ-9), Rate-Limit `verify` 30/min fail-closed, Integritätsprüfung, Export-/Lösch-/Retention-Bausteine, Basisvorlagen-Versionierung, Prüfauftrag `certificate_review_required` bei bestanden → nicht bestanden (R0-23 §5.2) |
| `src/Certificate/CertificateSnapshot.php` | Snapshot `format_version = 1`: Schema (unbekannte/fehlende/falsch typisierte Felder → `ccap_validation_failed`), kanonisches JSON (Schlüssel rekursiv sortiert, NFC, ohne Escapes), `snapshot_hash = sha256:`, Prüfcode (12 Hex, gruppiert), Nachweis-Nr. `CC-XXXX-XXXX` |
| `src/Certificate/CertificatePages.php` | `/<lernbereich>/nachweise/` (Liste), `/nachweise/<uid>` (HTML-/Druckansicht, Druck-CSS A4, Text statt Bild, `dir="auto"`, Verifikationslink als Text, Nachweis-Nr., Prüfcode, Rezertifizierungshinweis), `/nachweis/<token>` (öffentlich, `noindex`, `no-store`, Rate-Limit); Renderer-Slot `course_captain_certificate_renderers` (`certificateRenderers`), Fallback „Shown in the standard layout“ mit Diagnose-Hook `course_captain_certificate_fallback_rendered`; Block `course-captain/certificate-verify` |
| `src/Certificate/CertificateRoutes.php` | REST `GET /me/certificates` (R-40, ohne `pdf_url`, mit `view_url`), `PUT /me/certificates/{uid}/visibility` (Δ-9), `GET /certificates/verify/{token}` (R-42, öffentlich, `Cache-Control: no-store`, `details.bucket = verify`); admin-post `ccap_certificate_issue`, `ccap_certificate_correct`, `ccap_certificate_revoke` (Nonce, Rücksprung mit Ergebnis) |
| `blocks/certificate-verify/block.json` | apiVersion 3, Attribut `showHolderName` (nur einschränkend) |
| `tests/integration/CertificateTest.php` | 13 Tests |
| `tests/unit/CertificateSnapshotTest.php` | 3 Tests |

**Änderungen an gemeinsam genutzten Dateien:** `src/Kernel/Plugin.php` (3 Registrierungen), `src/Kernel/UninstallManifest.php` (Option `course_captain_certificate_templates`, User-Meta `ccap_certificate_name_public`), `src/Ops/AuditView.php` (4 Aktionen, Bereich `certificates`), `src/Settings/SettingsSchema.php` (`certificates.base_template`, SET-092), `src/Privacy/PrivacyService.php` (Export mit Snapshot-Daten statt Rohspalten; Eraser löscht Zertifikate und Namensfreigabe), `src/Privacy/RetentionService.php` (Schritt `RT-09_certificates`, 10 Jahre nach letzter Revision), `src/Progress/Competency.php` (Zertifikate als zweite Quelle), `src/Contract/Api.php` (Feature-Schlüssel `certificates`). **Keine Schemaänderung**, `LearningArea.php` unverändert (eigene Route mit Priorität 4 vor dem Lernbereich).

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler (1 bekannte Warnung ImportTest.php:318) · PHPStan L8 0 · Unit **88 / 2 726** · Integration **169 / 1 780** · Multisite **178 / 1 833** · Free-Guard 0 · Textdomain-Lint 0. `.pot` nicht aktualisiert (Vorgabe).

## 3. Tests

| ID | Test |
|---|---|
| CRT-001 / 002 | `auto_issue` aus → nichts; an → genau ein Zertifikat `revision_no = 1`, `issued`, Snapshot mit Kursrevision, Evidenz, Vorlage/`template_version`, Sprache, Inhaber, `issued_by` ohne Namen; Event `certificate.issued`; zweiter automatischer Aufruf, manuelle Ausstellung auf dieselbe Evidenz und erneuter Dispatch liefern dasselbe Zertifikat; Prüfung → Zertifikat, späterer Kursabschluss ergänzt keines |
| CRT-003 / 010 / 012 | Profiländerung ändert nichts; Korrektur nach Nachbewertung (Punkte, `evaluation_no`) und Namensänderung → Revision 2, alte `corrected`, gleiche Vorlage, alter Token „ersetzt“ ohne Daten der neuen Fassung, Audit, Event; Doppelklick mit gleichem `request_key` → dieselbe Revision, anderer Inhalt → `ccap_idempotency_conflict`; Kurs/Person ändern → abgewiesen |
| CRT-004 / 011 | Grant bei Abgabe widerrufen → Ergebnis gewertet, kein automatisches Zertifikat, manuelle Ausstellung möglich (Audit); ohne Grund, ohne Evidenz, eigenes, Übungs- und nicht bestandene Versuche → abgewiesen |
| CRT-005 / 022 / 023 | Druckansicht mit langem Namen, Umlauten, RTL-Kurstitel: vollständig, A4-Druck-CSS, kein `<img>`, kein PDF-Bezug, eine `h1`; fremder Nutzer 404; `designer` mit `fields`/`assets` und unbekannte Art `xyz` → Fallback mit Hinweis, Feldliste, Alt-Texte, Diagnose-Hook |
| CRT-006 / RT-09 | Export enthält Snapshot-Daten ohne Hash/Token; Eraser löscht Zertifikate und Namensfreigabe, Verifikation danach `ccap_not_found`; Retention löscht alle Revisionen 10 Jahre nach der letzten |
| CRT-008 / 009 (Unit) | gleiche Daten in anderer Schlüsselreihenfolge und NFD → gleicher Hash; unbekanntes (auch verschachteltes), fehlendes, falsch typisiertes Feld → `ccap_validation_failed` |
| CRT-013 / 014 / 028 | Widerruf ohne Grund / `other` ohne Text → abgewiesen, Trainer 403, eigenes mit #17 403; Widerruf von Revision 2 → alle `revoked`, beide Tokens „widerrufen am …“ sofort, Event nur mit Kategorie, Audit, Kompetenz `revoked`, zweiter Widerruf ohne Wirkung, keine Korrektur danach |
| CRT-015 / 016 | `valid` mit exakt den Feldern der Policy (Datum ohne Uhrzeit), nie E-Mail, Name, Konto-ID, Punkte, Grund, Vorlagenart; abgelaufen → `expired`, Zeile bleibt `issued`; unbekannter Token → `ccap_not_found` |
| CRT-017 / SET-091 | Name standardmäßig nicht öffentlich; Freigabe nur durch Inhaber, wirkt sofort und für alle Revisionen; Policy `never` → kein Name, Freigabe 403 |
| CRT-018 / 019 | Ausstellername ändern → neue `template_version`, alte Snapshots unverändert, neue Ausstellung mit neuem Aussteller/Signaturzeile; Prüfcode Druck = Verifikation; manipulierter Snapshot → Integritätsprüfung meldet die UID |
| CRT-029, R-40/R-42 | Token → Minimaldaten, UID ohne Recht → `ccap_not_found`, UID mit #17 → volle Daten; REST R-42 200/404 mit `no-store`, R-40 `private, no-store` ohne `pdf_url`, Δ-9; Lernsperre → R-40 403, Verifikation 200 (RBAC-066); Rate-Limit 31. Abruf/min → 429; Verifikationsseite `noindex` |
| R0-08 §5.3, R0-23 §5.2 | Zertifikat belegt Lernziel auch ohne gültigen Versuch, abgelaufen nach `valid_until`; Nachbewertung bestanden → nicht bestanden: kein Widerruf, Audit `certificate_review_required` |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Tabelle `ccap_certificate_template` (Δ-7) und Spalte `revision_kind` (Δ-10) fehlen im Schema | keine Schemaänderung: `revision_kind` steht im Snapshot; Basisvorlagen-Versionen (Nummer, `definition_hash`, Zeit) in Option `course_captain_certificate_templates`, `template_version` = Versionsnummer. Weil jeder gedruckte Wert im Snapshot steht (CR-2), braucht die Darstellung die Vorlagenhistorie nicht. Tabelle mit R1-35/Schema-Upgrade nachziehen |
| A-2 | Auslöser Kursabschluss **und** Prüfung | beide über die interne Outbox (at-least-once, Retry, Idempotenz je Evidenz). Ein Kursabschluss stellt nur aus, wenn der Lernende noch kein gültiges Zertifikat des Kurses hat (verhindert Doppelnachweis Prüfung + Abschluss); jede neue bestandene Prüfung ist neue Evidenz (R0-23 §5.2). **Klärung** mit NF-03/OP-29-3 (Ausstellungsregel je Kurs) |
| A-3 | E-113 „Grant bei Abgabe“ | geprüft beim Verarbeiten des Events: direkter Grant des Kurses (aktiv oder zeitlich abgelaufen, Scope `all`/`exam`) mit Gültigkeitsfenster um `submitted_at`, sonst aktueller effektiver Zugang (Bundles). Ein danach widerrufener Grant zählt nicht |
| A-4 | Kurs-Override `auto_issue` (SET-090, Validator) | nur globaler Schalter; Kurs-Overrides gibt es im Settings-Kern noch nicht (R1-31/R0-28) |
| A-5 | `keep_evidence_on_erasure` (RT-10, E-150) | Einstellung existiert nicht (siehe R1-17 A-3): Eraser **löscht** Zertifikate; Pseudonymisierung mit R1-34 |
| A-6 | PHP-API `course_captain_verify_certificate_v1` | Lese-API ist nach E-38/R0-19 §4.1 Pro; Free liefert die Semantik als `CertificateService::verify_reference()` (CRT-029), keine globale Funktion |
| A-7 | Mails N-05…N-07 | Mailsystem fehlt (R1-21); Events `certificate.issued/.corrected/.revoked` stehen für einen internen Abonnenten bereit |
| A-8 | Lernziel-Titel | Lernziele haben in R1 nur UIDs (`certifies_objectives`); Snapshot `objectives[].title` = UID bis Lernziele Titel bekommen |
| A-9 | Fehlercodes | ungültige Korrektur/Widerruf eines widerrufenen Zertifikats → `ccap_validation_failed` (422); `ccap_temporarily_unavailable` (503) bei defektem Zählerspeicher, `ccap_request_in_progress` (409) bei offener Idempotenz |
| A-10 | Texte `texts.heading/body` | in der Sprache der laufenden Anfrage bei Ausstellung aufgelöst (kein Locale-Wechsel auf die Kurssprache) |
| A-11 | Ausgelassen (R1-35/R1-25) | A-11-Liste/CSV (CF-11), Slug-Historie mit 301 (CRT-020), Logo-Warnung beim Löschen (CRT-024), QR-Code (SOLL), Seiten-Cache-Test hinter Proxy (CRT-028 nur Anwendungsebene), Ausgabe `COURSE_CAPTAIN_OUTBOUND=off` überspringt auch diesen Abonnenten (wie alle internen) |

## 5. Übergaben

R1-35 (Verwaltung/UI, Vorlagentabelle, Liste/Export, Slug-Historie), R1-21 (Mails N-05…N-07), R1-34 (`keep_evidence_on_erasure`, Fristen editierbar), R1-31 (Kurs-Override `auto_issue`), R1-38 (Integritätsprüfung im Systemstatus). Review durch zweite Person vor Merge (AG-05).
