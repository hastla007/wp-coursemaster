# R1-17 · Datenschutz, Diagnose und Wartungsjobs – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-17):** Export/Eraser, Retention, Cron-Recovery, Audit, Health-Checks, Security.
**Quellen:** R0-31 §2 (RT-01…RT-45, RR-1…RR-8), R0-17 §5, R0-13 §4, R0-10 §4.4; R0-05 GM-099, GM-102, GM-103, GM-131, GM-138, GM-139, GM-156, GM-157, GM-161, GM-172, GM-173. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Privacy/PrivacyService.php` | Einstufung **aller** 53 Tabellen (personal/content/system, GM-173); WP-Exporter mit Gruppen `cc-progress`, `cc-activity`, `cc-questions`, `cc-answers`, `cc-favorite`, `cc-collections`, `cc-exams`, `cc-grants`, `cc-seats`, `cc-certificates`, `cc-notifications`, `cc-submissions`, `cc-invitations` (nur eigene Zeilen, keine Hashes/Tokens); WP-Eraser und Kontolöschung: Lernstand, Antworten, Favoriten/Sammlungen, Prüfungsversuche, Grants samt Events, Staff, Sperren, Nachteilsausgleich, Benachrichtigungen, Idempotenz, Reset-Journal gelöscht; Plätze freigegeben (Adresse/Token leer), Gruppenbesitz auf 0 (GM-102); Einladungen ohne Konto export- und löschbar (GM-103); Audit `personal_data_erased` mit pseudonymer Referenz; Vorschlagstext für die Datenschutzerklärung (RR-7) |
| `src/Privacy/RetentionService.php` | isolierte Schritte RT-04 (Antworten 2 J.), RT-15 (Einladungsadressen 30 d nach Ablauf), RT-17 (entfernte Plätze 365 d), RT-22 (aufgehobene Sperren 30 d), RT-24/25 (Importprotokoll 14/180 d), RT-26 (Paketkopien: aktiv + jüngstes Rollback-Ziel), RT-39 (Idempotenz), RT-40 (Zählerfenster, GM-161), RT-42 (Settings-Undo); `retention_applied` je Datenart mit Anzahl ohne IDs (RR-5); Zustand für den Systemstatus |
| `src/Ops/HealthChecks.php` | Wartungsschritte isoliert (Fehler protokolliert und auditiert, Rest läuft, GM-156); Selbstheilung fehlender Planungen ohne Doppelplanung bei Admin-/Cron-Anfragen (GM-157); Site-Health-Tests Ablage, Wartung, Ereigniszustellung |
| `src/Kernel/CronHooks.php` | stündlicher Lauf über die isolierten Schritte inkl. Retention |
| `src/Ops/AuditView.php`, `src/Ops/AuditScreen.php` | Ereignis-Registry mit Bereich und Klartext-Bezeichnung (GM-139); Abfrage mit Filtern Nutzer/Bereich/Ereignis/Zeitraum/Objekt-ID, seitenweise, nur `ccap_view_audit_log`, E-Mail maskiert; Admin-Seite „Audit log“ (beschriftetes GET-Formular) |
| `tests/integration/PrivacyOpsTest.php` | 6 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **150 / 1 570** · Multisite **159 / 1 623** · MS-002 OK · Free-Guard 0 · Textdomain-Lint 0 · `.pot` aktualisiert.

## 3. Tests

| ID | Test |
|---|---|
| GM-173 | Einstufung deckt exakt alle Schema-Tabellen ab |
| GM-172 | Export mit `cc-progress`, `cc-favorite`, `cc-grants`, nur eigene Zeilen, keine Hashes/Tokens; Löschung entfernt eigene, fremde bleiben; Audit ohne Personen-ID; zweite Löschung ohne Wirkung |
| GM-102 / 103 | Kontolöschung gibt Platz frei, Grants weg, Gruppe bleibt ohne Besitzer; Einladung ohne Konto exportiert und gelöscht; Exporter registriert |
| Retention | Zählerfenster (GM-161), alte Antwort (RT-04), Einladungsadresse (GM-099), Paketkopien (GM-131), Audit, idempotent |
| GM-156 / 157 | werfender Schritt bricht den Lauf nicht ab, Fehler im Zustand; echter Stundenlauf fehlerfrei; fehlende Planung → kritisch, Selbstheilung einmalig; Health-Tests grün |
| GM-138 / 139 | Klartext, Filter Bereich/Ereignis/Objekt, Maskierung, 403 ohne Recht, Admin-Seite beschriftet; jede auditierte Aktion im Code hat Bezeichnung und Bereich |

## 4. Abweichungen und Klärungen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Audit-Pseudonymisierung durch den Eraser (RT-23) | Die Hash-Kette deckt `subject_user_id` ab; Ändern bricht die Manipulationsprüfung. Einträge bleiben, die Löschung selbst wird pseudonym auditiert. **Klärung R0-13 §4.4** (z. B. Kette über pseudonymisierbare Referenz) **Entschieden 24.11.2026: Kette über Pseudonym, getrennte Zuordnung wird bei Löschung entfernt (E-361); Umsetzung offen.** |
| A-2 | Aufbewahrung Paketkopien: GM-131/RT-26 „aktiv + jüngstes Rollback-Ziel“ vs. R0-10 §3.6 „aktiv + 2 weitere“ | umgesetzt nach GM-131/RT-26; **Klärung** im R1-Gate **Entschieden 24.11.2026: eigener Aufbewahrungsschlüssel, Vorgabe = kürzere Regel aus GM-131 und R0-10 §3.6 (E-350); Umsetzung mit SET-CC-02.** |
| A-3 | `keep_evidence_on_erasure` (RT-07/RT-10), Tombstones nach Restore (RT-41, E-92) | Einstellung und Tombstone-Tabelle fehlen im Datenmodell; mit R1-34 bzw. R1-23 |
| A-4 | `privacy.retention`-Unterschlüssel editierbar, Verkürzen mit Vorschau (RR-2/RR-3), Systemstatus-Rückstand (RR-6) | Konstanten nach RT-Defaults; Einstellungen mit R1-34, Anzeige mit R1-38 |
| A-5 | Selbstbedienungs-Anträge (GM-174), asynchroner Export (GM-175), Shortcode `[ccap_privacy_request]` | WP-Kernanträge funktionieren über Exporter/Eraser; eigene Selbstbedienung mit R1-28/R1-34 |
| A-6 | Direktabruf-Selbsttest per HTTP (GM-115), Diagnosepaket | R1-38 |

## 5. Übergaben

R1-23, R1-28, R1-34, R1-38. Review durch zweite Person vor Merge (AG-05).
