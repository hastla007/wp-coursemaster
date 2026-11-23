# R1-43 · Domain-Events, interne Outbox und Quell-Adapter im Free-Kern – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoR (R0-24):** `CourseCaptain\Event` (Envelope v1 intern, Outbox, interne Zustellung), `CourseCaptain\Access\GrantSourceAdapter` (Schnittstelle); keine öffentliche Registry (Pro, E-38); Tests GRANT-002, REC-016, SUP-029, NTF-001*.
**Quellen:** R0-19 §5.1, §5.4, §5.8; R0-06 §4.6; R0-09 R-04; Beschlüsse E-38, E-116, E-182, E-215, E-258, E-311. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Event/Outbox.php` (aus R1-13) | Envelope v1 in derselben Transaktion; nur bei internem Abonnenten; plant den Dispatcher einmalig ein |
| `src/Event/OutboxDispatcher.php` | Claim per Lease (60 s, `lock_owner`), höchstens 100 je Lauf, Reihenfolge je Objekt nach `object_version`; Backoff 1 min, 5 min, 15 min, 1 h, 6 h, 24 h ×3; nach 8 Versuchen `dead` + Action `course_captain_outbox_dead_lettered`; `PermanentDeliveryError` → `failed`; abgelaufene Leases werden übernommen; `COURSE_CAPTAIN_OUTBOUND = off` → `skipped`; Retention: offene Zustellungen nach 30 Tagen `dead` (`retention`), vollständig zugestellte Events nach 90 Tagen gelöscht (Zustellungen per FK mit); Replay mit gleicher `event_id`, zugestellte nur mit Flag + Begründung + Audit, Recht `ccap_manage_integrations` |
| `src/Event/PermanentDeliveryError.php` | dauerhafter Zustellfehler |
| `src/Access/GrantSourceAdapter.php`, `SourceStatusMapper.php` | lesender Quellvertrag und reine Abbildung Quellzustand → Soll-Grants; unbekannter Status = nichts ändern (K-02) |
| `src/Access/FakeSource.php` | Testquelle (Adapter + Mapper) |
| `src/Access/GrantReconciler.php` | Abgleich unter Quell-Lock `grant-source:<typ>:<ref>`; veraltete Quellrevision (`source_seq`) ignoriert; Anlage/Übergänge über den GrantService; kein Wiederaufleben nach Widerruf |
| `src/Kernel/Plugin.php`, `CronHooks.php` | Handler `ccap_outbox_dispatch` (plant sich nach, solange Arbeit da ist); stündliche Retention |
| `tests/integration/OutboxTest.php` | 5 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **87 / 942** · Multisite **96 / 995** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · `.pot` neu.

## 3. Tests

| ID | Test |
|---|---|
| GRANT-002 | Outbox-Teil: je Event eine Zustellung, einmal zugestellt (`test_delivery`) |
| R0-19 §5.4 Nr. 4 | Backoff, Dead-Letter, permanente Fehler, Lease-Übernahme |
| REC-016 | Replay behält die `event_uuid`; zugestellte nur mit Flag/Begründung |
| E-258/E-116 | Retention offen 30 d / zugestellt 90 d |
| GRANT-004/007/021 | Reconciler mit FakeSource: idempotent, kein Wiederaufleben, veraltete Revision, unbekannter Status, fehlende Quelle |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Werteobjekte | Snapshot/DesiredGrant als Arrays mit festen Schlüsseln (Doku in den Interfaces) |
| A-2 | Haltemodus (E-85) | Zustellungen bleiben `pending`, sobald der Haltemodus-Check aus R1-17 vorliegt; heute nur `COURSE_CAPTAIN_OUTBOUND` |
| A-3 | SUP-029 (Reconcile-CLI mit Vorschau/Begründung) | Kern liegt vor (Reconciler, Lease-Übernahme); CLI/Admin-Weg mit R1-38 |
| A-4 | NTF-001 | Abonnent `notification` kommt mit R1-21 über den Filter `course_captain_internal_subscribers` |
| A-5 | Lokaler Hook `course_captain_event_v1` | nur Pro (R0-19 §5.4 Nr. 6) |

## 5. Übergaben

Benachrichtigungen (R1-21), Reconcile-CLI und Dashboard (R1-38), Haltemodus (R1-17). Review durch zweite Person vor Merge (AG-05).
