# R1-38 · Betriebsdashboard mit sicheren Reparaturaktionen – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-c`
**DoD (TODO R1-38):** Cron/Jobs/Import/Grant-Fehler mit IDs und sicheren Retry/Reconcile-Aktionen, Rollen-/Dedupe-Tests, redigierter Diagnoseexport.
**Quellen:** R0-13 §1.2 (Rechte je Stufe), §2.1 (Prüfliste H-01…H-34, Teilmenge), §2.2 (Diagnosepaket, Allowlist, Scrubber, Pseudonyme), §3 (gemeinsamer Ablauf, K-16, K-17-Teil), §4.1 (Ops-Aktionen); R0-34 §1.3 (Partnerkatalog), §2.1–§2.3 (Datenvertrag, Ableitung), §2.5 (Rechte), §3 (Oberfläche); R0-19 §4.7 (WP-CLI), §6 (Integrationszustände), CT-09; R0-21 §3.7 (manueller Cache-Flush); R0-37 §3.5 („Jetzt erneut versuchen“); R0-30 S-22 (`course-captain-status`, M-3). Offene Punkte aus R1-02 O-3, R1-17 A-6, R1-21 A-12, R1-25 A-5, R1-40 O-5, R1-43 A-3. Clean Room (E-01): LB-Verhalten nur nach Spezifikation, kein Code übernommen.

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Ops/OpsStatus.php` | eine Prüfliste für Screen, CLI und Diagnosepaket: H-01 Versionen, H-02/H-03 Migration (Schritt-IDs), H-07 Wartungslauf, H-12 Speicher, H-25 Ausgangsmodus, H-26 Haltemodus/zurückgehaltene Mails, H-27 interne Zustellungen (Dead-Letter mit `delivery:<id>` und Fehlerklasse, abgelaufene Leases), H-28 Rückstand > 1 h, Mail-Zustand, H-29 Grants `failed`/`pending_*` > 24 h (`grant:<uid>`), H-30 Versuche `broken` (`attempt:<uid>`), H-31 Importe ohne Heartbeat > 15 min (`import:<id>`), H-33 `COURSE_CAPTAIN_SECRET_KEY` (nur gesetzt/leer), H-34 Integrationen; Status `ok/warn/fail/info`, Gesamtstatus; Recht #22 `ccap_view_system_status` oder CLI |
| `src/Ops/IntegrationStatus.php` | statischer Partnerkatalog R0-34 §1.3 (WooCommerce, Voucher Captain, Affiliate Captain, Pass/Form/Workflow/Review Captain), Probe nur über dokumentierte Konstanten/Funktionen, `get_plugins()` nur für `not_installed` ↔ `inactive`; reine Ableitung `derive()` nach §2.2/§2.3; Datensatz `IntegrationStatus` v1 (R1-Felder, `actions` leer, Klasse IK-0); Texte nach §3.3; keine Schreibzugriffe, keine Partner-Hooks |
| `src/Ops/RepairService.php` | Reparaturaktionen mit Recht #23 `ccap_run_maintenance` (CLI als Systemakteur): Migration „Jetzt erneut versuchen“ (manueller Lauf, ignoriert Backoff, versucht Poison erneut), Dead-Letter erneut zustellen (gleiche `event_id`, `manual_retries + 1`), Dead-Letter verwerfen (`skipped/discarded`, Event bleibt; Begründung ≥ 10 Zeichen), zurückgehaltene Mails freigeben/verwerfen (Begründung), Cache-Flush (Inhalt, `--user`, `--course` inkl. Purge); je Aktion Transaktion mit Audit (fail-closed), Idempotenz über `ccap_request_idempotency` (`request_key`), wiederholter Aufruf ohne Wirkung |
| `src/Ops/Diagnostics.php` | redigiertes Diagnosepaket nach Allowlist: `manifest` (Format, Zeit, Regeln, Site als HMAC, SHA-256 je Abschnitt), `versions`, `environment` (ohne Pfade, Secret nur `<konstante>`/`<leer>`), `plugins`, `health`, `jobs` (ohne Payload/`user_ref`, Meldungen gekürzt und gescrubbt, `correlation_id` als HMAC), `imports`, `outbox`, `notifications`, `integrations`, `counts` (Lernende als Größenklasse), `schema`; Scrubber (E-Mail, Domain, ABSPATH/WP_CONTENT_DIR, Bearer/JWT/Hex/Base64, `protected-<…>`, Query, IPv4/IPv6, IBAN, `user/order:<id>`, Pfade); HMAC-Schlüssel je Paket, nicht gespeichert; Audit `diagnostics_exported` (Bytes, SHA-256, ohne Inhalt); Safe-Writer für die CLI (`x`, 0600, nicht im WordPress-Verzeichnis) |
| `src/Ops/OpsScreen.php` | Admin-Seite „System status“ (`admin.php?page=course-captain-status`, #22) mit Bereichsnavigation (M-3: Links, `aria-current`) Status · Jobs · Integrationen · Diagnose; Aktionsformulare nur mit #23; admin-post `ccap_ops` mit Nonce je Aktion (`ccap_ops_<op>`), `request_key`, Pflichtbegründung und Bestätigungskästchen bei zerstörenden Aktionen; Ergebnis per Redirect mit Statusmeldung (`role="status"`); Download des Diagnosepakets über admin-post `ccap_ops_diagnostics` (Nonce) |
| `src/Ops/OpsCommand.php` | WP-CLI `wp course-captain status [--format=json]`, `cache flush [--user=] [--course=]`, `outbox status\|retry\|discard [--reason=] [--yes]`, `integrations status [--detected] [--format=json]`, `diagnostics [--output=]` nach dem Muster von `MigrateCommand` (testbarer Kern `execute()`) |
| `src/Ops/AuditView.php` | Ereignisse `job_retried`, `job_discarded`, `cache_flushed`, `diagnostics_exported` registriert (Bereich `ops`) |
| `src/Notification/NotificationService.php` | `release_held()`/`discard_held()` nehmen optional eine Begründung an und schreiben sie ins Audit |
| `src/Kernel/Plugin.php` | Registrierung `OpsCommand`, `OpsScreen` |
| `docs/API-CHANGELOG.md` | Eintrag für die neuen WP-CLI-Kommandos (keine neuen Hooks, keine REST-Routen) |
| `tests/integration/OpsDashboardTest.php` | 9 Tests (Abschnitt 3) |

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler (bekannte Warnung `ImportTest.php:318`) · PHPStan L8 0 · Unit **90** · Integration **258 / 7 331** · Multisite **268 / 5 620** · Free-Guard 0 · Textdomain-Lint 0. `.pot` nicht aktualisiert (Auftrag).

## 3. Tests (`@group R1-38`)

| Test | Spec-IDs | Inhalt |
|---|---|---|
| `test_SUP_001_006_roles` | SUP-001, SUP-006, INT-009, INT-010, INT-013 | Support und Kursmanager lesen Status, Jobs und Integrationen, sehen keine Aktionsknöpfe; admin-post mit gültiger Nonce → `ccap_forbidden`, Dienst → 403, keine Zustandsänderung, kein Audit; Admin sieht Knöpfe; Editor ohne `ccap_*` → 403 für Status, Integrationen, Diagnose und Seite |
| `test_SUP_024_checks_with_ids` | SUP-024, SUP-022 (Teil) | Dead-Letter, `failed`-Grant, `broken`-Versuch, Import ohne Heartbeat erscheinen in H-27/H-29/H-30/H-31 mit IDs; Gesamtstatus `fail`; CLI `status` Exit 1, JSON enthält die Prüfungen; Seite zeigt IDs |
| `test_INT_012_014_dead_letter_repair` | INT-012, INT-014, INT-015, INT-016, INT-017, SUP-009 | Retry: `pending`, gleiche `event_id`, `manual_retries = 1`, genau eine Zustellung; gleicher `request_key` → gleiche Antwort, ein Audit; erneuter Aufruf ohne Wirkung. Verwerfen ohne/mit zu kurzer Begründung abgelehnt; mit Begründung `skipped/discarded`, Event bleibt, Audit mit Begründung und Zähler; gleicher Schlüssel mit anderer Begründung → 409 `ccap_idempotency_conflict`. CLI verlangt `--yes` bzw. `--reason` |
| `test_held_notifications_admin_post` | SUP-038 (Teil), E-88 | admin-post: falsche Nonce, fehlende Bestätigung, zu kurze Begründung, unbekannte Aktion jeweils ohne Wirkung; Freigeben → `queued` mit Audit-Begründung; Verwerfen → `discarded` |
| `test_admin_post_redirects_with_result` | – | vollständiger admin-post-Einstieg: Redirect auf die Statusseite mit Ergebnis, Audit `cache_flushed`, Erfolgsmeldung |
| `test_cache_flush_cli` | R0-21 §3.7 | ohne Optionen neue Inhaltsgeneration; mit `--user`/`--course` neue Nutzer-/Kursgeneration, Inhaltsgeneration bleibt; unbekannter Kurs Exit 1 `ccap_not_found`; Support darf nicht leeren |
| `test_migration_retry_now` | MIG-011 (Ergänzung), R0-37 §3.5 | Poison-Schritt: H-03 mit Schritt-ID, Admin-Kontext versucht nicht automatisch; Knopf nur für #23, Fehlertext ohne Pfade; „Jetzt erneut versuchen“ → Schema `ok`, Audit `job_retried` (`before: blocked`) |
| `test_CT_09_integration_status` | CT-09, INT-001…INT-007 | ohne Partner alle `not_installed/not_found`, Filter „Nur erkannte“ leer mit Erklärtext; Voucher Captain installiert/deaktiviert über die echte Plugin-Liste → `inactive/plugin_inactive`, null Schreibzugriffe bei der Erkennung, Zugang des Lernenden und Grants unverändert; Ableitung: kompatibel, `version_too_low`, Slug ohne API → `no_published_contract`, Affiliate-Vertrag Major 1/2, Form Captain; CLI JSON |
| `test_SUP_017_021_redacted_diagnostics` | SUP-017, SUP-018, SUP-021, INT-019 (Teil) | Canaries (E-Mail, IPv4/IPv6, Bearer-Token, Ordner-Geheimnis, ABSPATH, fremder Pfad, Query-Secret, `user:42`, IBAN, Domain, E-Mail/Login eines Nutzers) in Zustell- und Importfehlern: 0 Treffer, Platzhalter vorhanden; Abschnitte nach Allowlist, Prüfsummen stimmen; Pseudonym innerhalb eines Pakets gleich, zwischen Paketen verschieden; Secret nur `<leer>`; Audit mit SHA-256/Bytes; Safe-Writer 0600, kein Überschreiben, nicht im WordPress-Verzeichnis |

Bestehend und weiter grün: GM-139 (jede auditierte Aktion hat Bezeichnung und Bereich) deckt die neuen Ereignisse ab.

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Prüfliste | Teilmenge von H-01…H-38 (Abschnitt 1); keine eigene `HealthRegistry` mit Filter für Module/Pro – `OpsStatus` ist die eine Liste für Screen, CLI und Paket. Site-Health-Tests bleiben unverändert (R1-17) |
| A-2 | Recht für interne Dead-Letter | nach R0-34 §2.5 „intern: R1 mit #23“; `OutboxDispatcher::replay()` (R1-43, #20) bleibt unverändert und wird hier nicht verwendet |
| A-3 | Audit-Namen | `job_retried`/`job_discarded` nach R0-13 §4.1 (statt `delivery_retried`/`dead_letter_discarded` aus R0-34 INT-012/014); Migration-Retry als `job_retried` mit `object_type = migration`; Cache-Flush als neues `cache_flushed` (nicht im Katalog, kein Begründungszwang, weil gefahrlos); Freigeben/Verwerfen zurückgehaltener Mails behalten die R1-21-Namen `notifications_released`/`notifications_discarded`, jetzt mit Begründung (statt `held_outbound_released`) |
| A-4 | Bestätigung ohne JavaScript | Pflicht-Kontrollkästchen im Formular statt eigener Bestätigungsseite mit Token (R0-30 D-6/D-7) |
| A-5 | „Jetzt erneut versuchen“ | nutzt den manuellen Lauf (`CONTEXT_CLI`): ohne Zeitbudget, ignoriert Backoff, versucht Poison erneut; DDL kann nicht in einer Transaktion mit dem Audit laufen, daher Audit nach dem Lauf |
| A-6 | WooCommerce-Mindestversion | `>=8.0.0` als Annahme (R0-20 nennt kein Fenster) – **Klärung** |
| A-7 | `cache flush --user` | WP-CLI verbraucht `--user` als globalen Parameter; das Kommando nimmt dann den aktuellen Nutzer als Ziel (Akteur bleibt `system:cli`) |
| A-8 | Diagnosepaket | ohne Vorschau-Seite, Einmal-Link und Serverkopie (SUP-019/020): Download direkt per admin-post; Abschnitte `settings`, `errors`, `debug_log` fehlen (Settings-Register mit Privacy-Klassen und Fehlertabelle existieren nicht); Domain nur als HMAC, Option „Domain im Klartext“ fehlt; Partner-Hosts in Meldungen bleiben (keine Connectoren in Free) |
| A-9 | Mails `failed`/`unknown` | nur als Zähler; „erneut senden“ je Mail bleibt Dienst (R1-21), keine Liste in diesem Screen |

### Nicht gebaut (bewusst, offen)

| Nr. | Punkt | Ziel |
|---|---|---|
| O-1 | Korrekturaktionen K-01…K-15 (Grant-Dialoge, Versuch nachauswerten/verwerfen, Zusatzversuch, Fortschritts-Reset mit Undo, Lernsperre-Schreibweg, Import-Rollback-Dialog) und Grant-Retry K-06 (kein Retry-Dienst für `failed`-Grants vorhanden) | Folgeaufgaben R1-35/R1-38-Folge |
| O-2 | Reconcile RC-1…RC-7 mit Vorschau/`--apply` (SUP-029/030) | R1-38-Folge (Kern `GrantReconciler` aus R1-43 vorhanden) |
| O-3 | Haltemodus-Erkennung und K-17 „bestätigen“ (H-26 zeigt nur den Filterzustand), Debug-Logging (DL-1…DL-10, H-35), Audit-Export und `audit verify` (H-36), Monitor `/health` und REST `GET /integrations` | R1-23, R1-38-Folge |
| O-4 | H-04…H-06, H-08…H-11, H-13…H-24, H-32, H-37, H-38 | mit den jeweiligen Modulen (R1-45 Härtung, R1-14 Selbsttest) |
| O-5 | WP-CLI `backup-check`, `preflight`, `identity`, `maintenance`, `outbox list/replay --since/purge` | R1-24/R1-28 |

### Klärungsfragen

1. WooCommerce-Mindestversion für `active_compatible` (A-6)?
2. Sollen Cache-Flush und Migration-Retry eine Pflichtbegründung bekommen (§4.1 führt `maintenance_action` als begründungspflichtig)?
3. Reicht das Bestätigungskästchen für zerstörende Aktionen, oder wird die tokenbasierte Bestätigungsseite (R0-30 D-6/D-7) schon in R1 verlangt?

Review durch zweite Person vor Merge (AG-05).
