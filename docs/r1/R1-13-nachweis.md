# R1-13 · Standalone-Free-Zugänge und Gruppenplätze – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoR (R0-24):** `CourseCaptain\Access` (Grant, Seat, Claim, Reconciler, FakeSource); Tests GRANT-007, GRANT-021, SEAT-015, ID-004, CON-001…004; Gate C-2.
**Quellen:** R0-09 §1.2, §2, §3, §4, §6; R0-19 §5.1; R0-21 §2.4; Beschlüsse E-17, E-26, E-27, E-28, E-29, E-112, E-113…E-117, E-201, E-286, E-287, E-293. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Access/GrantTransitions.php` | Automat G-01…G-15: 7 Zustände, `expired`/`revoked` endgültig je Quellrevision |
| `src/Access/GrantService.php` | Manueller Grant (M-14, Begründung Pflicht, Ende in der Zukunft, idempotent per `request_key`); Anlage aus Quellen über den Unique-Schlüssel (`ccap_idempotency_conflict` bei anderem Nutzer); Übergänge mit Guard, CAS (3 Versuche) und Event + Outbox in **einer** Transaktion; manueller Vorrang (manuelle Sperre nur manuell lösbar, Widerruf gewinnt); verbotene Übergänge werden als `ignored` protokolliert; Verlängerung (G-14, `valid_until_locked`), Ende in der Vergangenheit → `expired`; Reaktivierung als neue Revision (G-15, E-29); Verlängerungskette `extend` und Restack nach Widerruf (Laufzeit bleibt, `chain_anchor_at`); Ablauf-Job (G-10/G-11); Pending-Timeout 24 h (E-201); Abhängigkeiten eine Stufe ohne Zyklen (§3.5); `access.changed` nur bei echter Zugangsänderung (§3.4); Kettenlock `grant-chain:<user>:<course>` |
| `src/Access/GroupService.php` | Gruppen manuell (E-17, idempotent, Name bereinigt, Besitzer ohne eigenen Grant); persönliche Einladung `cci_` und Gruppenlink `ccg_` (256 Bit, nur SHA-256 gespeichert, E-112); Belegungsregel aktiv + offene Einladung; Claim unter `group-seats:<id>` (≤ 2 s, sonst `ccap_group_busy`), Token unter dem Lock neu aufgelöst, bedingtes UPDATE bzw. Insert mit Nachzählung (`ccap_group_full`), Seat-Grants in derselben Transaktion; `invited_address_only` (E-28, Link deaktiviert); Platz freigeben (Grants `revoked`, neuer Platz = neue Zeile); Platzreduktion in der Reihenfolge Einladungen (jüngste zuerst) → zuletzt Beigetretene, mit Vorschau; Gruppenstatus an Seat-Grants weitergeben (manuell entschiedene ausgenommen); Link-Rotation; Ablauf von Einladungen |
| `src/Event/Outbox.php` | Envelope v1 (R0-19 §5.1) in `ccap_outbox_event` + je internem Abonnent eine `ccap_outbox_delivery`; nur gespeichert, wenn ein interner Abonnent existiert (E-38/E-182); Kern-Abonnent `cache`; Zustellung/Dead-Letter folgt in R1-43 |
| `src/Event/AuditLog.php` | Audit-Einträge mit Hash-Kette (`row_hash`, E-139) |
| `src/Storage/NamedLock.php` | Wartezeit-Parameter, Lock-Namen je Schlüssel (`for_key`) |
| `src/Storage/StorageException.php` | Fehlernummer 1062 (`is_duplicate()`) |
| `src/Policy/PolicyService.php` | M-14 meldet bei bekanntem Kurs 403 statt 404; M-17 erkennt den eigenen aktiven Platz (idempotenter Claim) |
| `src/Kernel/CronHooks.php` | stündlich: Ablauf, Pending-Timeout, Einladungsablauf |
| `tests/integration/AccessTest.php` | 14 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85 / 2 667** · Integration **82 / 897** (zweimal) · Multisite **91 / 950** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · `.pot` neu.

## 3. Tests

| ID | Test |
|---|---|
| GRANT-001 | Übergangstabelle |
| GRANT-002 / 003 | manuell, doppelte Absendung → 1 Grant, 1 Event, Outbox, Audit; ohne Begründung/Ende in der Vergangenheit/Trainer abgewiesen |
| GRANT-004 / 006 | gleiches Quellereignis 10× → 1 Grant; gleicher Schlüssel, anderer Nutzer → Konflikt |
| GRANT-007 / 009 | kein Wiederaufleben nach Widerruf/Ablauf; Reaktivierung = Revision 2 |
| GRANT-010 | zweite Quelle hält Zugang, kein `access.changed` |
| GRANT-012 | Kette und Restack (Laufzeit bleibt) |
| GRANT-015 / 016 / 020 | manueller Vorrang, Pending-Suspend ignoriert, Pending-Timeout → `failed` → Retry |
| GRANT-018 | Fehler beim Outbox-Schreiben rollt Grant und Event zurück |
| GRANT-033 | Zyklus/Selbstbezug abgewiesen |
| SEAT-001 / 021 | idempotente Anlage, Besitzer ohne Grant, Namensbereinigung |
| SEAT-002 / 005 / ID-002 | Einladungen reservieren Plätze, Überzählige ohne Platz; Claim einmalig, anderes Konto `ccap_claim_used`; Token nur als Hash |
| SEAT-007 / 015 | abgelaufene Einladung belegt keinen Platz, Token ungültig; nur eingeladene Adresse |
| SEAT-009 / 010 | Freigabe, Reduktionsreihenfolge, Vorschau ohne Schreiben, neuer Platz = neue Zeile |
| SEAT-012 / 014 / 016 | Gruppe aussetzen/fortsetzen wirkt auf Seat-Grants; Link-Rotation; gesperrte Gruppe `ccap_group_not_active` |
| GM-198 / GRANT-032 | Bundle-Zugang über die Policy (`PolicyTest::test_bundle_grants_access_to_contained_courses`) |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung | Begründung |
|---|---|---|---|
| A-1 | Token-Hash nach Claim | bleibt am aktiven Platz gespeichert (statt NULL) | R0-09 §1.2 verlangt zugleich Idempotenz für dasselbe Konto (Doppelklick/zweites Gerät); fremde Konten scheitern am Status (`ccap_claim_used`). Abgelaufene/entfernte Plätze verlieren den Hash |
| A-2 | Quell-Adapter/Reconciler (`GrantSourceAdapter`, `source_seq`, Inbox) | Schnittstelle und FakeSource-Ereignisse über `create()`/`transition()`; Adaptervertrag mit R1-43 (R0-19 §5.8) | R1-43 hat Outbox und Adapter-Schnittstelle zum Gegenstand |
| A-3 | Einladungsmail, Budget max(20, 2 × Plätze)/24 h, Vorschau-GET, Rate-Limits | Mail/Budget mit R1-21/R1-35, Vorschau und Rate-Limit mit dem REST-Einstieg (R1-28/R1-45) | kein Einstieg in R1-13 |
| A-4 | Wiedereinsetzung einer widerrufenen Gruppe (§4.8) | nicht umgesetzt | Auslöser ist eine Quellrücknahme (R3); manueller Weg mit R1-35 |
| A-5 | Lernerlock L1 und Quell-Lock L2 | nur L3 (Gruppe) und L4 (Kette) | L1/L2 werden mit Kontolöschung (R1-17) und Quell-Adaptern (R1-43/R3) gebraucht |

## 5. Übergaben

Parallel-Tests SEAT-003/004/019, GRANT-005 → R1-42; Mein-Lernen-Anzeige (§3.2) → R1-16; Audit-Ansicht → R1-17; Review durch zweite Person vor Merge (AG-05).
