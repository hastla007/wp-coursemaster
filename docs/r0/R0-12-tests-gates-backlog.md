# R0-12 · Tests, Gates und R1-Backlog abgleichen (Entwurf)

**Status:** Entwurf zur Abnahme, **nicht abgehakt**. Keine Implementierung, kein Testlauf, kein Commit. Die DoD verlangt Review und Freigabe. · **Stand:** 16.11.2026
**Bezug:** TODO R0-12 (Voraussetzung R0-05, R0-07, R0-08, R0-09, R0-10, R0-11), FLOW-001/002/004, CORE-SPEC-007/008, R0-PAR-003, LB-AUDIT-006/007, R0-GATE-001; Konzept Kap. 23 (QA-Matrix, Release-Gates), 28.6 (Gates A–D), 34 (55 Prozessfälle), 35.5 (CORE-01…CORE-12), 38 (PAR-01…12), 39 (LB-01…15)
**Quellen (nur gelesen):** `TODO-WETTBEWERBSANALYSE.md` (R0-01…R0-40, R1-01…R1-42, R2/R3/R4, FLOW-001…205, CORE-SPEC-001…010, SUITE, SEAM, R3-FIRST, R0-RBAC/DATA/PAR, LB-AUDIT), `konzept.md`, `docs/r0/ENTSCHEIDUNGEN.md` (E-01…E-171), R0-04 (Bausteine BS-01…BS-36, Kopplungen K-01…K-25), Testkataloge R0-05 (GM), R0-07 (RBAC), R0-09 (GRANT/SEAT/ID), R0-10 (IMP/MED), R0-14 (NFR), R0-16 (REC), R0-19 (CT-Anker), R0-23 (REV) sowie die während der Bearbeitung hinzugekommenen Entwürfe R0-13 (SUP), R0-15 (A11Y/I18N), R0-17 (SEC/PRIV), R0-21 (CON), R0-22 (PUB), R0-25 (LOC/MS), R0-26 (UX); Verträge R0-06, R0-08, R0-11.

**Kernzahlen** (per Skript, §5)

| Größe | Wert |
|---|---:|
| Prozess- und Kernfälle (ID 6 · LEARN 10 · ACCESS 9 · COMMERCE 10 · SUITEFLOW 10 · OPS 10 · CORE 12) | 67 |
| davon mit R1-Anteil (Pflicht für Gate B/C) | 45 |
| R1-Fälle ohne R1-Task / ohne Test | 0 / 0 |
| Fälle mit dokumentierter Lücke (R1-Fälle 29 · nur R2/R3 17) | 46 |
| Testfälle in 15 R0-Katalogen (GM 190 · RBAC 96 · GRANT 29 · SEAT 22 · ID 15 · IMP 32 · MED 26 · NFR 18 · REC 25 · REV 46 · CT 12 · SUP 42 · A11Y 66 · I18N 15 · SEC 46 · PRIV 30 · CON 48 · PUB 52 · LOC 29 · MS 23 · UX 58) | 920 |
| davon mit R1-Ziel-Task | 909 |
| R1-Tasks ohne Katalogtest / mit dünner Abdeckung (≤ 2 Tests) | 0 / 4 (R1-32, R1-33, R1-36, R1-39) |
| Vorgeschlagene zusätzliche R1-Tasks | 2 (R1-43, R1-44) |
| Vorgeschlagene ergänzende Testfälle (§1.3) | 23 |

**Lesehilfe.** „Release“ folgt R0-04 (Baustein BS-xx, Regeln Z-1…Z-10): **R1** = Free-Kern, **R1+R3** = neutraler Kern in R1, Adapter in R3.x, **R2** = zusätzliche P0, **R3.1/R3.2/R3.3/R3.4** = Woo, Voucher, gemeinsames Gate, übrige Suite, **R4** = nach Nachfrage. „Gate“ meint Konzept 28.6: **A** Portierbarkeit (= R0-GATE), **B** Free-Lernfluss, **C** Betriebsreife (B und C = R1-GATE), **D** Erweiterungen (R2-GATE, R3-GATE je Feature). Test-IDs mit „…“ sind lückenlose Bereiche. Ein Stern (R1-43\*, R1-44\*) kennzeichnet einen **vorgeschlagenen** Task (§2.3). „Lücke“ nennt, was fehlt; „–“ heißt: keine bekannte Lücke.

---

## 1. Traceability: Prozessfälle und Kernabnahmefälle

### 1.1 Prozessfälle Kap. 34 (55)

| Fall-ID | Kurzbeschreibung | Release (R0-04) | R1/R3-Task(s) | Testfall-IDs | Gate | Lücke |
|---|---|---|---|---|---|---|
| ID-01 | Angemeldeter Nutzer erhält nur eigene Kurse; Rollenwechsel, Sperre, fremde Kurs-ID ohne IDOR | R1 (BS-03, BS-12) | R1-03, R1-13, R1-16 | RBAC-001, RBAC-002, RBAC-006, RBAC-008, RBAC-041, RBAC-096, GM-001, GM-049, GM-151, GRANT-019, SEC-003, SEC-004, CON-022 | C | – |
| ID-02 | Gastkauf: Zugang erst nach verifiziertem Claim, nie Übernahme per E-Mail | R1+R3 (Claim-Kern BS-14; Gastkauf BS-31, R3.1) | R1-13; R3-02, R3-13 | ID-001, ID-003, ID-004, ID-005, ID-015, RBAC-054, SEAT-015, SEC-020, SEC-021, SEC-041 | C · R3-GATE | E-30 (Kontopflicht statt Claim-Token, Abweichung von CORE-SPEC-002) offen; ID-015 hängt daran |
| ID-03 | Käufer ≠ Lernender, mehrere Plätze; jede Zuweisung einmalig und bestätigt | R1+R3 (Seat-Kern BS-14; Kauf R3.1) | R1-13, R1-35; R3-02 | SEAT-001, SEAT-002, SEAT-005, SEAT-009, SEAT-017, GM-094, GM-098, ID-007, RBAC-036, RBAC-059, CON-001, CON-003, PRIV-019 | C · R3-GATE | Kauf für Dritte in R1 nur über FakeSource; Woo-Mehrmenge erst SEAT-020 (R3) |
| ID-04 | Getrennte Objekt-/Site-Rechte für Trainer, Autor, Tutor, Shop-Manager, Admin | R1 (BS-03) | R1-03, R1-31, R1-34 | RBAC-003, RBAC-004, RBAC-005, RBAC-011, RBAC-024, RBAC-025, RBAC-032, RBAC-033, RBAC-042, RBAC-062, RBAC-091, RBAC-092, GM-153, GM-154, SEC-044, SUP-001…SUP-006, MS-023 | C | SUP-006 prüft nur Screens ohne Caps; dass `editor`/`shop_manager` bei Aktivierung keine `cc_*`-Caps erhalten (E-83), prüft kein Test → RBAC-097 |
| ID-05 | E-Mail-Wechsel, Kontolöschung, Sperre, Zusammenführung | R1 (BS-19, BS-12) | R1-17, R1-13, R1-03, R1-38 | ID-008…ID-014, GM-102, GM-177, RBAC-050, GRANT-019, SUP-012…SUP-014, MS-016, MS-017, PRIV-009 | C | Merge-Werkzeug nicht in R1 (E-114), nur „Zugang übertragen“ (ID-012) |
| ID-06 | Multisite/Cross-Site-Identität | R4 (W-11, E-07); R1 nur Site-Isolation (BS-26) | R1-23, R1-30; R4-01 | RBAC-010, RBAC-078, GM-186, REC-024, MS-001…MS-022 | C (Isolation) · R4 | Multisite offiziell nicht unterstützt (E-07, E-49); R0-25 Entwurf, Abnahme offen |
| LEARN-01 | Kurs: Entwurf → veröffentlicht → Revision → archiviert | R1 (BS-04, BS-05) | R1-04, R1-05, R1-26, R1-27, R1-37 | GM-001, GM-003, GM-137, GM-144, RBAC-002, RBAC-004, RBAC-017, REV-038, REV-039, REV-040, REV-043, REV-044, PUB-001…PUB-052, CON-011, CON-047, LOC-008 | B | – |
| LEARN-02 | Lektion, Video, Datei, Activity bearbeiten, freigeben, absolvieren; keine fremden Uploads | R1 (BS-07, BS-15) | R1-05, R1-07, R1-14 | GM-007…GM-016, MED-001…MED-005, MED-010, RBAC-067…RBAC-076, IMP-012, PUB-025, PUB-028, PRIV-016, SEC-033, SEC-042 | B/C | – |
| LEARN-03 | Fortschritt speichern, unterbrechen, fortsetzen, Gerätewechsel; nie rückwärts | R1 (BS-09) | R1-10, R1-16, R1-20 | GM-011, GM-027, GM-036, GM-075, GM-077, REV-036, CON-019, PRIV-018 | B | Doppelabschluss geprüft (CON-019); verspätete Requests mit älterem Stand (Monotonie, E-70) ohne Test → GM-191 |
| LEARN-04 | Prüfung starten, speichern, Zeitlimit, Abgabe, Bewertung | R1 (BS-06, BS-08) | R1-06, R1-08, R1-09, R1-20, R1-33 | GM-027…GM-029, GM-035…GM-039, GM-045, GM-050, GM-053, GM-054, NFR-005, REC-011, SEC-009, SEC-011…SEC-014, CON-005, CON-016, CON-017 | B/C | – |
| LEARN-05 | Nicht bestanden, Wiederholung, Versuchslimit, Sperrfrist; keine Umgehung per URL/REST | R1 (BS-08) | R1-08, R1-09, R1-20 | GM-030, GM-040, GM-041, GM-046, RBAC-031, REV-024, SEC-015 | B | `cooldown_seconds` (R0-08 §4.4) ohne Test → GM-192 |
| LEARN-06 | Smarttrainer, Karteikarten, Favoriten, Fehlertraining, Wiederholungsplan | R1 (BS-10, BS-11) | R1-11, R1-12 | GM-055…GM-074, RBAC-021, RBAC-023, REV-029, REV-035, SEC-016, SEC-017, SEC-018, CON-008 | B | Referenzlauf der Rechenlogik offen (E-56); Abweichungsliste offen (E-55) |
| LEARN-07 | Completion, Mastery, Competency getrennt | R1 (BS-09) | R1-10 | GM-009, GM-066, GM-075, GM-076, REV-001, REV-003, REV-025 | B | Competency (abgeleitet, `competency_validity_days`, E-73) und „Zahlung ist keine Kompetenz“ ohne Test → CERT-009 |
| LEARN-08 | Zertifikat ausstellen, verifizieren, korrigieren, widerrufen | R1 Free-Basis (Z-3, E-96); Designer R3 | R1-35, R1-44\* | RBAC-060…RBAC-066, REV-045, REV-046, GRANT-028, SEC-034, PRIV-025, CON-037, PUB-038, PUB-039, LOC-020…LOC-022 | C | Kein LB-Quellbestand, kein GM (R0-04 §3, R0-06 E-39). Verifikation ist getestet; Ausstellung, Einmaligkeit (R0-09 R-06), Korrektur und E-113-Regel ohne Test → CERT-001…CERT-006; R0-29 fehlt; Task-Reihenfolge R1-27 → R1-35 widersprüchlich (§2.3) |
| LEARN-09 | Lernnotizen, Aufgabenabgabe, Trainerfeedback, Vorlagen | R2 (Z-8) | R2-01…R2-04 | – | D (R2-GATE) | Für R1 nicht anwendbar; R2-Katalog entsteht mit R2-01…R2-04 |
| LEARN-10 | Kurs löschen/archivieren, Inhalte importieren, Rollback | R1 (BS-16) | R1-15, R1-27, R1-41 | GM-118…GM-136, IMP-001…IMP-032, REV-026, REV-027, REV-041, REV-042, REC-017, SUP-032, LOC-025, LOC-026, SEC-028, SEC-029 | B/C | Endgültiges Löschen eines Kurses mit Lernständen (Lösch-Hooks, E-48) ohne Test → REV-047 |
| ACCESS-01 | Kostenloser, manueller, administrativer oder Einladungszugang | R1 (BS-12…BS-14) | R1-13 | GM-081, GM-087, GM-094, GM-098, GRANT-002, GRANT-003, RBAC-042…RBAC-045, SUP-009 | B/C | – |
| ACCESS-02 | Kauf-/Voucher-/Ticket-/Mitgliedschaftsgrant mit stabiler Quellreferenz | R1+R3 (Kern BS-12, K-01/K-02; Adapter R3.1/R3.2/R3.4) | R1-13; R3-02, R3-04, R3-07 | GM-088, GM-089, GRANT-004, GRANT-005, GRANT-006, GRANT-024 | C · R3-GATE | Mitgliedschafts-Entitlement ohne Vertrag (Kap. 34.7) |
| ACCESS-03 | Mehrere gültige Grants für denselben Kurs | R1 (BS-12) | R1-13 | GM-085, GM-092, GRANT-010, GRANT-011 | B | – |
| ACCESS-04 | Grant pending → active/failed/expired/revoked | R1 (BS-12) | R1-13; R3-10 | GRANT-001, GRANT-015, GRANT-016, GRANT-020, GRANT-022, GM-091, SUP-024, SUP-029 | C | Pending-Timeout und Eskalation offen (SEAM-006) |
| ACCESS-05 | Ablauf, Verlängerung, Pause, Reaktivierung | R1 (BS-12, BS-13) | R1-13, R1-14, R1-17, R1-25 | GM-084, GM-086, GM-089, GRANT-009, GRANT-012, GRANT-013, GRANT-014, RBAC-009, RBAC-046, CON-013, CON-048, I18N-009 | B/C | – |
| ACCESS-06 | Rückerstattung nach Kursbeginn oder nach Zertifikat | R1+R3 (Kern mit FakeSource; Woo R3.1) | R1-13, R1-25, R1-27; R3-03 | GRANT-007, GRANT-027, GRANT-028, RBAC-037, RBAC-065, REV-023, REV-046, GRANT-023, CON-010, CON-020, CON-021, CON-036 | C · R3-GATE | – |
| ACCESS-07 | Paket/Bündel, mehrere Plätze, Teilwiderruf | R1+R3 (Seats BS-14; Bundle-Teil nach E-25/W-07; Teilrefund R3.1) | R1-13, R1-04; R3-03 | GM-101, SEAT-009, SEAT-010, SEAT-013, SEAT-020, CON-004 | C · R3-GATE | Relation „Kurs enthält Kurs“ (INV-A-107/144) ohne Test → GM-193 (nach E-25) |
| ACCESS-08 | Gesperrter Kurs, eingeschränkte Lektion, geschützte Downloads; Prüfung bei jedem Zugriff | R1 (BS-15, BS-23) | R1-14, R1-37 | GM-015, GM-107…GM-116, MED-001…MED-017, RBAC-012…RBAC-020, RBAC-067…RBAC-075, CON-031…CON-036, SEC-032, SEC-033, SEC-037, LOC-010 | C | – |
| ACCESS-09 | Quelle/Partner deaktiviert oder inkompatibel; kein stiller Massenwiderruf | R1+R3 (Kern Z-6; Connector R3) | R1-13, R1-43\*; R3-10 | GRANT-021, RBAC-048, CT-08, CT-09 | C · R3-GATE | Betreiber-Policy für Bestands-Grants (Kap. 35.2) ohne Setting und Test (R0-27) |
| COMMERCE-01 | Woo-Produkt/Variation → Kurs; Zahlung bestätigt → Grant | R3.1 (BS-31) | R3-02, R3-06 | GRANT-004 | R3-GATE | Woo-Katalog fehlt (R3-FIRST-001/004: HPOS/Classic, Blocks/Classic, doppelte Hooks); GRANT-004 prüft nur den Kern |
| COMMERCE-02 | Bestellstatus pending/on-hold/failed/cancelled/completed | R3.1 | R3-02, R3-03 | GRANT-023 | R3-GATE | Nur RF-11/RF-13 abgedeckt; Statuswechsel nach Freischaltung offen (R3-FIRST-001) |
| COMMERCE-03 | Gastbestellung, Käufer ≠ Lernender, Mehrfachmenge | R3.1 | R3-02, R3-13 | ID-001, ID-015, SEAT-020 | R3-GATE | E-30 |
| COMMERCE-04 | Storno, Teil-/Vollrefund, Chargeback, Reversal | R3.1 | R3-03, R3-19 | GRANT-023, GRANT-025, GRANT-029 | R3-GATE | – |
| COMMERCE-05 | Gutschein validate/reserve → grant → commit | R3.2 (BS-32) | R3-04, R3-05 | GRANT-024, GRANT-026, SEC-024 | R3-GATE | – |
| COMMERCE-06 | Gutschein mehrfach, mit Guthaben/Plätzen, abgelaufen, ungültig | R3.2 | R3-04, R3-05 | – | R3-GATE | Kein Test; E-36 („ein Code je Platz“) offen |
| COMMERCE-07 | Woo-Bestellung mit Voucher-Rabatt oder -Zahlung | R3.3 | R3-05, R3-06 | – | R3-GATE | Kein Test (R3-FIRST-105); „Voucher allein“ entfällt (ENTSCHEIDUNGEN §7) |
| COMMERCE-08 | Gratisbestellung, 100 %-Rabatt, erneuter Kauf, Upgrade | R3.1 | R3-02 | – | R3-GATE | Kein Test; Freischaltpolicy offen |
| COMMERCE-09 | Produkt-/Kursmapping nach Verkauf geändert oder gelöscht | R3.1 | R3-02, R3-03 | – | R3-GATE | Kein Test; Zuordnung über `entitlement_revision` im `grant_source_key` (Kap. 35.2) |
| COMMERCE-10 | Partnerausfall zwischen Reservierung, Grant und Commit | R3.2 | R3-04, R3-10, R3-19 | GRANT-020, GRANT-024, SEC-025 | R3-GATE | Kompensationspfad nur teilweise (SEAM-002) |
| SUITEFLOW-01 | Workflow: Enrollment, Lektion, Prüfung, Completion, Zertifikat als Event | R3.4; Event-Kern Free in R1 (E-38, E-119) | R1-28, R1-43\*; R3-07, R3-12, R3-17 | CT-06, CT-07, CT-12, PRIV-023, SEC-039 | C · R3-GATE | Outbox-/Event-Kern hat keinen R1-Owner (§2.3) |
| SUITEFLOW-02 | Workflow-Action Enroll/Revoke oder Benachrichtigung | R3.4; v1 ohne schreibende Aktionen (E-39) | R3-17 | RBAC-047, RBAC-048 | R3-GATE | In v1 nicht anwendbar; Schleifenschutz ohne Test |
| SUITEFLOW-03 | Form: kursgebundenes Formular, Aufgabenreferenz | R3.4 (BS-33) | R3-07 | – | R3-GATE | Kein Test; Partner-Registry existiert nicht (E-40) |
| SUITEFLOW-04 | Review: Abschluss → verifiziertes Erlebnis → Einladung | R3.4 | R3-07 | – | R3-GATE | Kein Test (E-41) |
| SUITEFLOW-05 | Pass: Ticketkauf → Grant; Storno → Widerruf | R3.4 | R3-07 | – | R3-GATE | Kein Test; Check-in ist keine Completion (ENTSCHEIDUNGEN §7) |
| SUITEFLOW-06 | Affiliate: Sale/Refund über autoritative Quelle | R3.4; v1 meldet Course nichts (E-37) | R3-07, R3-19 | – | R3-GATE | Kein Test „keine doppelte Provision“ |
| SUITEFLOW-07 | License/Pro: aktiv, grace, abgelaufen, Server offline | R3 (BS-35) | R3-08, R3-15, R3-18 | SEC-038 | R3-GATE | Lizenzablauf/Grace/Offline ohne Test (SEC-038 prüft nur Datenminimierung); R0-39 fehlt; Free-Teil siehe OPS-10, CORE-10 |
| SUITEFLOW-08 | Kombinierte Rezepte Kauf → Kurs → Prüfung → Workflow → Review | R3.4 | R3-07 | – | R3-GATE | Kein Test (SUITE-110) |
| SUITEFLOW-09 | Partnerinstallationen erkennen und einzeln aktivieren | R3 (FLOW-204); Erkennung laut R0-11 A-14 schon F1 | R3-16; R1-43\* | CT-08, CT-09 | R3-GATE | Release-Konflikt R0-11 A-14 ↔ FLOW-204 → D12-4 |
| SUITEFLOW-10 | Partner-Upgrade, Downgrade, Deaktivierung, Neuaktivierung | R3 | R3-11, R3-12 | CT-08, CT-12 | R3-GATE | – |
| OPS-01 | WP-Datenexport, Lösch-/Anonymisierungsantrag, Retention | R1 (BS-19) | R1-17, R1-34 | GM-099, GM-103, GM-131, GM-162, GM-172…GM-177, ID-002, ID-009, ID-010, ID-011, ID-013, RBAC-086, RBAC-089, PRIV-001…PRIV-015, PRIV-024, PRIV-026…PRIV-029, MS-018 | C | Tombstones nach Restore getestet (PRIV-010, E-151); Retention-Defaults E-44/E-51/E-150 offen |
| OPS-02 | Objektzugriff, REST-Nonce, Webhook-Signatur; IDOR, CSRF, Replay, SSRF, Rate-Limit, Secret-Rotation | R1 (BS-02, BS-03, BS-18) | R1-03, R1-28 | RBAC-040, RBAC-043, RBAC-044, RBAC-079, RBAC-081, RBAC-093, RBAC-096, ID-004, ID-005, GM-073, GM-152, CT-02, CT-11, REC-022, SEC-001…SEC-004, SEC-020, SEC-022, SEC-023, SEC-026, SEC-029, SEC-035, SEC-036 | C | Secret-Rotation ohne Test → REC-026 |
| OPS-03 | Event verspätet, doppelt, out-of-order, verloren | R1+R3 (Kern FakeSource und Outbox; Partner R3) | R1-13, R1-43\*; R3-12 | GRANT-007, GRANT-025, RBAC-093, CT-07, CT-11, REC-016, CON-010, CON-018, PRIV-023 | C · R3-GATE | Outbox ohne R1-Owner (§2.3) |
| OPS-04 | Parallelität: zwei Zahlungen, Refunds, Einlösungen, Joins, Abgaben | R1 (BS-02) | R1-20, R1-42; R3-05 | GRANT-005, GRANT-017, SEAT-003…SEAT-006, SEAT-019, ID-014, GM-045, GM-053, GM-096, GM-188, NFR-010, NFR-011, REV-030, REV-031, REV-032, IMP-020, GRANT-026, CON-001…CON-019, CON-040…CON-046, SEC-014, SEC-024, SEC-027 | C | – |
| OPS-05 | Job, Queue, DB, Netzwerk oder Partner offline | R1 (BS-02, BS-20) | R1-17, R1-22, R1-38 | GM-156, GM-157, NFR-009, NFR-013, IMP-022, REC-008, CT-07, SUP-022, SUP-024, SUP-030, CON-029, CON-041…CON-045 | C | RB-04…RB-06 über CON-029/CON-040…CON-045 abgedeckt; RB-01, RB-02, RB-07, RB-08 ohne eigene Test-ID; Alarmierung ohne Test |
| OPS-06 | Admin-Diagnose, Healthcheck, Fehler-/Supportexport | R1 (BS-20) | R1-17, R1-24, R1-38 | GM-115, GM-146, GM-147, GM-148, MED-007, MED-008, RBAC-094, REC-022, SUP-017…SUP-028, SUP-033…SUP-037, PRIV-022, PRIV-030, SEC-043 | C | Integrationsstatus-Anzeige (R0-34) fehlt |
| OPS-07 | Installation, Update, Migration, Deinstallation, Rollback | R1 (BS-01, BS-02) | R1-01, R1-02, R1-23, R1-32, R1-36, R1-39, R1-40 | GM-158, GM-163…GM-171, GM-183…GM-186, REC-001…REC-025, RBAC-095, MS-014, MS-015, MS-020, PRIV-026 | C | – |
| OPS-08 | Übersetzungen, Accessibility, Responsive, E-Mail/Benachrichtigung | R1 (BS-17, BS-22) | R1-16, R1-19, R1-21, R1-29 | GM-141, GM-143, NFR-014, NFR-016, MED-025, SEAT-002, SEAT-022, A11Y-001, A11Y-002, A11Y-003, A11Y-095, I18N-001, I18N-009, I18N-013, LOC-003, LOC-005, LOC-012, LOC-016…LOC-018, PRIV-021, SEC-040, UX-001, UX-050, UX-060 | C | A11y/i18n/UX in R0-15 und R0-26 spezifiziert (alle A11Y-, I18N-, UX-Fälle gehören zu diesem Fall); transaktionale Mails: Inhalt und Sprache getestet, Versand-Einmaligkeit, Zustellfehler, Opt-out, Rate-Limit und Mail-Sink nicht; GM-159/GM-160 sind Pro-Erinnerung (E-24) → NTF-001…NTF-007 |
| OPS-09 | Große Kataloge, Prüfungsspitzen, Masseneinschreibungen | R1 | R1-22, R1-42 | NFR-001…NFR-009, NFR-018, GM-006, GM-110, GM-142, GM-187, IMP-032 | C | Masseneinschreibung (Bulk-Grant, Gruppe mit 1 000 Plätzen) in keinem Lastprofil → NFR-019 |
| OPS-10 | Standalone-Free ohne Partner und ohne Lizenzserver | R1 (Z-6) | R1-01, R1-18 | CT-09, NFR-016, NFR-017, PRIV-017 | B | Kein ausdrücklicher Test „Free ohne Woo/Suite/Pro, keine Verbindung zu Lizenz- oder Partnerservern“ → SA-001 |

### 1.2 Kernabnahmefälle Kap. 35.5 (12)

| Fall-ID | Kurzbeschreibung | Release (R0-04) | R1/R3-Task(s) | Testfall-IDs | Gate | Lücke |
|---|---|---|---|---|---|---|
| CORE-01 | Gast kauft mit E-Mail eines bestehenden Dritten → kein Zugriff, kein Account-Link | R1+R3 (Kern mit FakeSource; Gastkauf R3.1) | R1-13; R3-02 | ID-001 | C · R3-GATE | Woo-Variante erst R3 |
| CORE-02 | Zehn Plätze an verschiedene Lernende; keine Einsicht in fremde Antworten | R1+R3 (Seat BS-14; Kauf R3.1) | R1-13, R1-35; R3-02 | SEAT-001, SEAT-003, SEAT-005, SEAT-017, RBAC-036, RBAC-059, CON-001, CON-003, PRIV-019 | C | Fixture GRP-1 hat 6 Plätze; Variante mit 10 Plätzen und 10 Konten parametrisieren (SEAT-001) |
| CORE-03 | Derselbe Hook/Callback zehnmal → genau ein Grant | R1+R3 | R1-13, R1-42; R3-02, R3-04 | GRANT-004, GRANT-005, RBAC-093, SEC-024, CON-010 | C · R3-GATE | Woo-/Voucher-spezifische Doppelhooks erst R3 |
| CORE-04 | Zwei Quellen, eine widerrufen → Kurs bleibt zugänglich | R1 (BS-12) | R1-13 | GRANT-010, GM-085 | B | – |
| CORE-05 | Teilrefund einer von drei Positionen | R3.1 | R3-03 | GRANT-023, SEAT-020 | R3-GATE | – |
| CORE-06 | Refund nach Abschluss und Zertifikat → Zugang weg, Historie und Zertifikat bleiben | R1+R3 | R1-13, R1-35; R3-03 | GRANT-028, REV-046, RBAC-065 | C | – |
| CORE-07 | Refund vor verspätetem Zahlungs-Event → kein Re-Grant | R1+R3 | R1-13; R3-03 | GRANT-007, GRANT-025, CON-010 | C · R3-GATE | – |
| CORE-08 | Voucher-Reserve ok, Grant/Commit scheitert | R3.2 | R3-04, R3-19 | GRANT-020, GRANT-024, SEC-025 | R3-GATE | – |
| CORE-09 | Trainer ruft fremde Lernenden-/Kurs-ID über REST ab | R1 (BS-03) | R1-03 | RBAC-033, RBAC-034, RBAC-087, GM-154, SEC-003, PRIV-020 | C | – |
| CORE-10 | Pro-Lizenz läuft ab → Free, Historie, Verifikation bleiben | R1+R3 (Free-Teil R1; Pro R3) | R1-18; R3-15, R3-18 | RBAC-065, CT-09, SEC-038 | C · R3-GATE | Kein direkter Test; R0-39 fehlt; Free-Teil über SA-001 |
| CORE-11 | Zertifikat begründet widerrufen → Verifikation zeigt `revoked` | R1 Free-Basis | R1-35, R1-44\* | RBAC-062, RBAC-063, REV-046, SEC-034, CON-037 | C | – |
| CORE-12 | Connector deaktiviert und reaktiviert → keine Massenwiderrufe, keine Duplikate | R1+R3 | R1-13; R3-10, R3-11 | GRANT-021, RBAC-048, CT-09 | C · R3-GATE | Reconciliation nach Reaktivierung erst R3 |

### 1.3 Ergänzende Akzeptanzfälle (Vorschlag zur Übernahme in die Fachkataloge)

Diese 23 Fälle schließen Lücken aus §1.1/§1.2, die auch die Kataloge aus R0-13, R0-15, R0-17, R0-21, R0-22, R0-25 und R0-26 nicht abdecken. Sie sind **Vorschläge** und zählen in §5 nicht mit. Die Nummern setzen die bestehenden Kataloge fort; die neuen Präfixe CERT, NTF und SA gehören in R0-29, R0-27 bzw. R0-18.

| ID | Gegeben / Aktion | Erwartung | Ziel-Task | Schließt Lücke | Übernahme in |
|---|---|---|---|---|---|
| CERT-001 | Kurs erfüllt Abschlussregel; Zertifikat laut Kurseinstellung | genau ein `cc_certificate` (`revision_no = 1`, `issued`), Snapshot mit Kursrevision, Evidenz, `template_version`, Sprache (E-158); Event `certificate.issued` | R1-44\* | LEARN-08 | R0-29 |
| CERT-002 | Ausstellung doppelt ausgelöst (Cron und Abgabe, zwei Prozesse) | 1 Zertifikat (Unique `evidence_rev`), der zweite Aufruf liefert dasselbe | R1-44\*, R1-42 | LEARN-08, R0-09 R-06 | R0-29 |
| CERT-003 | Korrektur nach Nachbewertung (neue Evidenz) | `revision_no = 2`, alte Revision `corrected` mit Verweis, alter Token zeigt „ersetzt“, Mail nach E-136 | R1-44\* | LEARN-08 | R0-29 |
| CERT-004 | Bei Abgabe ist kein Grant aktiv (Refund während der Prüfung, E-113) | Ergebnis gewertet, kein automatisches Zertifikat | R1-44\* | LEARN-08 | R0-29 |
| CERT-005 | HTML- und Druckansicht (E-96) mit langen Namen und Umlauten | vollständige Darstellung, kein serverseitiges PDF in Free | R1-44\*, R1-19 | LEARN-08 | R0-29 |
| CERT-006 | Datenschutzexport und Löschantrag mit Zertifikat | Export enthält den Nachweis; Eraser nach E-150 (löscht bzw. pseudonymisiert per Einstellung); Verifikation danach „nicht gefunden“ bzw. Minimaldaten | R1-44\*, R1-17 | LEARN-08, OPS-01 | R0-29 |
| CERT-007 | Kompetenz aus bestandener Prüfung mit `competency_validity_days = 30`; Grant aus Kauf ohne Prüfung | Kompetenz gültig bis T0 + 30 d, danach abgelaufen; ein Kauf allein erzeugt keine Kompetenz | R1-10 | LEARN-07 | R0-08 |
| CERT-008 | Pro-Lizenz läuft ab, Add-on bleibt aktiv | Basisnachweise und Verifikation unverändert, Designer-Vorlage eingefroren (E-94) | R3-15 | CORE-10 | R0-29 |
| NTF-001 | Einladung, Claim-Bestätigung, Kursabschluss | genau eine Mail je Ereignis (Outbox-Dedupe), Absender von WordPress geerbt (K-08) | R1-21, R1-43\* | OPS-08 | R0-27 |
| NTF-002 | Zugang abgelaufen | genau eine Mitteilung **nach** Ablauf; keine Erinnerung davor in Free (E-24) | R1-21 | OPS-08 | R0-27 |
| NTF-003 | `wp_mail` liefert `false` bzw. SMTP fällt aus | Fehler protokolliert, Retry mit Backoff, nach Erfolg keine Doppelmail | R1-21, R1-17 | OPS-05, OPS-08 | R0-27 |
| NTF-004 | Lernender widerspricht nicht notwendigen Mitteilungen | keine weiteren Mails dieser Klasse; notwendige (Claim, Sicherheit) weiter | R1-21, R1-34 | OPS-08 | R0-27 |
| NTF-005 | Einladungsflut eines Besitzers | Rate-Limit greift fail-closed (E-152), keine Mail über dem Limit | R1-21 | OPS-08 | R0-27 |
| NTF-006 | Staging bzw. Haltemodus (E-85, E-86) | alle Mails im Catcher bzw. zurückgehalten, keine externe Zustellung | R1-21, R1-23 | OPS-08 | R0-27 |
| NTF-007 | Test-Mail-Sink in der CI | jede Mail der E2E-Läufe im Sink nachweisbar, 0 externe Zustellungen | R1-21, R1-18 | OPS-08 | R0-18 |
| RBAC-097 | Aktivierung auf einer Site mit `editor` und `shop_manager` | beide ohne `cc_*`-Caps, nur `administrator` erhält sie (E-83) | R1-03 | ID-04 | R0-07 |
| GM-191 | Lektionsfortschritt: `PUT progress` mit älterem Stand nach neuerem | Stand geht nie zurück; `completed` nur per `manual_confirm` zurücknehmbar (E-70) | R1-10 | LEARN-03 | R0-05 |
| GM-192 | Prüfungsregel mit `cooldown_seconds = 3600`, ein nicht bestandener Versuch | neuer Start vor Ablauf der Sperrfrist abgewiesen mit Restzeit, danach erlaubt; Fortsetzen nicht betroffen | R1-08, R1-20 | LEARN-05 | R0-05 |
| GM-193 | Kombikurs enthält Teilkurs, Grant nur auf den Kombikurs | Inhalte und Dateien des Teilkurses freigegeben (nur wenn E-25 den Paritätsteil R1 zuordnet) | R1-04, R1-14 | ACCESS-07 | R0-05 |
| REV-047 | Kurs mit Lernständen und Zertifikaten wird endgültig gelöscht | nur mit Bestätigung und Anzahl der Betroffenen; Versuche und Zertifikate bleiben verifizierbar; Orphan-Check sauber (E-48) | R1-04, R1-27 | LEARN-10 | R0-23 |
| REC-026 | Rotation von `COURSE_CAPTAIN_SECRET_KEY` und eines Webhook-Secrets | gespeicherte Secrets neu verschlüsselt; alte Signatur nach Übergangsfrist abgelehnt; nirgends Klartext | R1-23, R1-28 | OPS-02 | R0-16 |
| NFR-019 | Masseneinschreibung: 1 000 Grants per CLI bzw. Admin-Sammelaktion, Gruppe mit 1 000 Plätzen | im Budget der zugehörigen Operation, Outbox-Rückstand abgebaut, keine Lock-Timeouts in parallelen L1-Lernanfragen | R1-22, R1-42 | OPS-09 | R0-14 |
| SA-001 | Frische Site, nur Course Captain Free, ausgehender HTTP-Verkehr blockiert | R0-11 Flows 1–4 grün; 0 Anfragen an Lizenz-, Partner- oder Fremdhosts; keine Notices zu fehlenden Partnern | R1-18, R1-01 | OPS-10, CORE-10 | R0-18 |

### 1.4 Paritäts- und LB-Prüffälle (Querverweis, keine eigene Neuzuordnung)

PAR-01…PAR-12 sind in R0-05 §6 vollständig mit GM-Bereichen und R1-Tasks verknüpft; diese Zuordnung wird hier übernommen. Für LB-01…LB-15 gilt:

| LB | Release | Task(s) | Tests | Lücke |
|---|---|---|---|---|
| LB-01 Woo-Zuordnung, Reconcile | R1-Kern (Reconciler, FakeSource) · R3.1 Adapter | R1-13; R3-02, R3-03 | GRANT-021, GRANT-023 | Woo-Katalog R3 |
| LB-02 Zugang ohne Shop, Teilumfang, Drip | R1 | R1-13, R1-14 | GM-081…GM-083 | E-14 (Drip-Edition) |
| LB-03 Gruppenplätze | R1 (Kauf R3) | R1-13, R1-35 | GM-094…GM-106, SEAT-001…SEAT-022 | – |
| LB-04 geschützte Seiten, Block, Shortcode, Dateien | R1 | R1-14 | GM-107…GM-116 | – |
| LB-05 Login-Härtung, Privacy-Flows | Härtung nicht R1 (E-10, BS-27); LMS-Limits R1 | R1-02, R1-17 | GM-046, GM-073, ID-005, GM-174 | – |
| LB-06 Consent externe Medien | R1 | R1-07, R1-34 | GM-012, GM-013, MED-021…MED-025 | – |
| LB-07 Paketimport, Dry-run, Rollback | R1 | R1-15, R1-41 | GM-118…GM-136 | – |
| LB-08 Integrations-/E2E-Familien | R0/R1 (Woo-Flows R3) | R1-18 | Gesamtkatalog | – |
| LB-09 Source-IDs, Hashes, Rechte | R1 | R1-15 | GM-120, GM-123, GM-132, IMP-014 | – |
| LB-10 Fragetypen | R1 (`chart_task` exclude) | R1-06 | GM-017…GM-023 | E-69 |
| LB-11 Voucher-Grant, Dedupe | Kern R1, Adapter R3.2 | R1-13; R3-04 | GM-088…GM-090 | – |
| LB-12 Ticket-/Event-Schnittstellen | R3.4 | R3-07 | – | kein Test, zulässig vor R3 |
| LB-13 Snapshot-Konverter, Manifest | R1 | R1-15 | GM-118…GM-136 | – |
| LB-14 Fragenrevisionen, feste/zufällige Prüfungen | R1 | R1-08 | GM-027…GM-034, GM-052 | – |
| LB-15 Mitgliederrabatt, Garantie | nicht übernehmen / R4 (E-25, E-32) | – | – | nicht anwendbar |

---

## 2. Umgekehrte Prüfung: R1-Task → Fälle, Tests, R0-Dokumente

### 2.1 Tabelle

„Fälle“ = Fälle aus §1.1/§1.2, in denen der Task genannt ist. „Tests“ = Anzahl der Katalogtests mit diesem Task in der Ziel-Spalte (§5). „R0-Spez.“ = Dokument mit dem Vertrag; *fehlt* = Voraussetzung im TODO, aber noch nicht geschrieben. Bewertung: **ok**, **dünn** (≤ 2 Tests oder Tests nur am Rand), **ohne** (0 Tests).

| Task | Fälle (§1) | Tests | Beispiele | R0-Spez. | Bewertung / Anmerkung |
|---|---|---:|---|---|---|
| R1-01 | OPS-07 OPS-10 | 8 | GM-158, MS-001, MS-002, MS-003 | R0-16, R0-25, R0-20 *fehlt*, R0-35 *fehlt* | ok (MS-Aktivierung, GM-158); Clean-Install über SA-001 |
| R1-02 | OPS-07 | 10 | GM-149, GM-163, GM-164, GM-168 | R0-06, R0-37 *fehlt* | ok |
| R1-03 | CORE-09 ID-01 ID-04 ID-05 OPS-02 | 105 | GM-049, GM-058, GM-079, GM-097 | R0-07, R0-17 | ok |
| R1-04 | ACCESS-07 LEARN-01 | 14 | GM-001, GM-003, GM-004, LOC-001 | R0-06, R0-08, R0-23, R0-25 | ok |
| R1-05 | LEARN-01 LEARN-02 | 7 | GM-127, GM-137, GM-138, GM-140 | R0-11, R0-22 | ok |
| R1-06 | LEARN-04 | 12 | GM-017, GM-019, GM-020, GM-024 | R0-08, R0-17 | ok |
| R1-07 | LEARN-02 | 16 | GM-007, GM-008, GM-010, GM-011 | R0-08, R0-10 | ok |
| R1-08 | LEARN-04 LEARN-05 | 31 | GM-021, GM-023, GM-026, GM-027 | R0-08, R0-17 | ok |
| R1-09 | LEARN-04 LEARN-05 | 8 | GM-018, GM-019, GM-030, GM-031 | R0-08 | ok |
| R1-10 | LEARN-03 LEARN-07 | 17 | GM-005, GM-009, GM-010, GM-066 | R0-08, R0-23 | ok |
| R1-11 | LEARN-06 | 10 | GM-055, GM-056, GM-057, GM-058 | R0-08 | ok |
| R1-12 | LEARN-06 | 13 | GM-004, GM-043, GM-065, GM-066 | R0-08, R0-05 | ok |
| R1-13 | ACCESS-01 ACCESS-02 ACCESS-03 ACCESS-04 ACCESS-05 ACCESS-06 ACCESS-07 ACCESS-09 CORE-01 CORE-02 CORE-03 CORE-04 CORE-06 CORE-07 CORE-12 ID-01 ID-02 ID-03 ID-05 OPS-03 | 68 | GM-042, GM-048, GM-072, GM-076 | R0-09 | ok |
| R1-14 | ACCESS-05 ACCESS-08 LEARN-02 | 59 | GM-015, GM-016, GM-025, GM-107 | R0-07, R0-10 | ok |
| R1-15 | LEARN-10 | 60 | GM-022, GM-064, GM-118, GM-119 | R0-10, R0-38 *fehlt* | ok |
| R1-16 | ID-01 LEARN-03 OPS-08 | 17 | GM-001, GM-047, GM-050, GM-051 | R0-11, R0-15 | ok |
| R1-17 | ACCESS-05 ID-05 OPS-01 OPS-05 OPS-06 | 49 | GM-086, GM-090, GM-099, GM-102 | R0-13, R0-16, R0-17 | ok |
| R1-18 | CORE-10 OPS-10 | 11 | LOC-026, MED-013, NFR-013, NFR-014 | R0-05, R0-14, R0-18 *fehlt* | Sammel-Task, nimmt alle R1-Tests ab (§3) |
| R1-19 | OPS-08 | 84 | A11Y-001, A11Y-002, A11Y-003, A11Y-004 | R0-15, R0-25 | ok |
| R1-20 | LEARN-03 LEARN-04 LEARN-05 OPS-04 | 19 | A11Y-070, A11Y-075, GM-035, GM-036 | R0-08, R0-14, R0-17 | ok |
| R1-21 | OPS-08 | 8 | GM-159, GM-160, LOC-016, LOC-017 | R0-27 *fehlt* (D12-6), R0-25 §2 | teilweise: Inhalt/Sprache/Header (PRIV-021, LOC-016…019, SEC-040); Versand, Fehler, Opt-out fehlen → NTF-001…007; GM-159/160 sind Pro (E-24) |
| R1-22 | OPS-05 OPS-09 | 16 | GM-006, GM-110, GM-142, GM-187 | R0-14 | ok |
| R1-23 | ID-06 OPS-07 | 34 | GM-117, GM-169, GM-170, GM-183 | R0-16, R0-17 §5.5 | ok |
| R1-24 | OPS-06 | 9 | GM-189, GM-190, MS-023, PRIV-030 | R0-13, R0-16 §5 | ok; Doku-Inhalte per Review (SUP-039…042) |
| R1-25 | ACCESS-05 ACCESS-06 | 26 | CON-020, CON-021, CON-022, CON-023 | R0-21 | ok |
| R1-26 | LEARN-01 | 64 | GM-003, GM-022, GM-023, GM-137 | R0-22 | ok |
| R1-27 | ACCESS-06 LEARN-01 LEARN-10 | 46 | GM-052, LOC-011, PUB-031, PUB-035 | R0-23 | ok |
| R1-28 | OPS-02 SUITEFLOW-01 | 30 | CT-01, CT-02, CT-03, CT-04 | R0-19 | ok; CT-Anker ohne Ziel-Spalte (§5) |
| R1-29 | OPS-08 | 77 | A11Y-007, A11Y-008, A11Y-020, A11Y-021 | R0-26 | ok: UX-Katalog R0-26 mit Schwellen E-166 |
| R1-30 | ID-06 | 7 | LOC-014, LOC-027, LOC-028, MED-024 | R0-20 *fehlt*, R0-25 | knapp: Konfliktmatrix R0-20 fehlt |
| R1-31 | ID-04 | 3 | GM-178, GM-180, GM-181 | R0-27 *fehlt* | knapp: Feldverträge R0-27 fehlen |
| R1-32 | OPS-07 | 2 | GM-179, UX-060 | R0-30 *fehlt*, R0-11 Flow 4 | **dünn**: Wizard-Resume, feldnahe Fehler, Ungespeichert-Warnung ohne Test (R0-30) |
| R1-33 | LEARN-04 | 2 | GM-031, REV-020 | R0-28 *fehlt* | **dünn**: Vererbungsmatrix R0-28 fehlt |
| R1-34 | ID-04 OPS-01 | 12 | GM-012, GM-013, GM-152, GM-176 | R0-31 *fehlt*, R0-10 §5 | ok |
| R1-35 | CORE-02 CORE-06 CORE-11 ID-03 LEARN-08 | 20 | GM-105, GM-106, GRANT-028, LOC-020 | R0-29 *fehlt*, R0-09 | ok für Seats und Verifikation; Ausstellung ohne Domäne → R1-44\* |
| R1-36 | OPS-07 | 2 | GM-179, GM-181 | R0-27 *fehlt* | **dünn**: Reset, Downgrade, Multisite-Grenze ohne Test (R0-27) |
| R1-37 | ACCESS-08 LEARN-01 | 4 | LOC-023, LOC-024, REV-040, SEC-033 | R0-33 *fehlt*, R0-25 | knapp: Yoast-Konflikttests fehlen (R0-33) |
| R1-38 | ID-05 OPS-05 OPS-06 | 56 | GM-115, GM-134, GM-140, GM-146 | R0-13, R0-34 *fehlt* | ok |
| R1-39 | OPS-07 | 2 | REC-023, SEC-045 | R0-35 *fehlt* | **dünn**: Clean-Install, Readme, ZIP-Inhalt ohne Test (R0-35) |
| R1-40 | OPS-07 | 16 | GM-068, GM-080, GM-113, GM-149 | R0-37 *fehlt*, R0-16 | ok |
| R1-41 | LEARN-10 | 11 | GM-125, GM-136, IMP-017, IMP-018 | R0-38 *fehlt*, R0-10 §3.5 | ok |
| R1-42 | CORE-03 OPS-04 OPS-09 | 54 | CON-001, CON-002, CON-003, CON-004 | R0-14 L5, R0-09 §6, R0-21 | ok |
| R1-43\* | ACCESS-09, OPS-03, SUITEFLOW-01, SUITEFLOW-09 | (14) | CT-06…CT-12, GRANT-002, REC-016, PRIV-023, SUP-029 | R0-19 §5, R0-06 E-42 | vorgeschlagen (§2.3) |
| R1-44\* | LEARN-08, CORE-11 | (10) | RBAC-060…RBAC-066, SEC-034, PRIV-025, CON-037; CERT-001…006 | R0-29 *fehlt*, R0-08 §7.2, R0-06 E-39 | vorgeschlagen (§2.3) |

### 2.2 Befunde

1. **Kein R1-Task ohne Katalogtest.** Seit R0-26 hat auch R1-29 (Usability) einen Katalog (UX-001…, Schwellen E-166).
2. **R1-21 nur teilweise abgedeckt.** Getestet sind Mailinhalt und -sprache (PRIV-021, LOC-016…LOC-019) und Header-Injection (SEC-040). Versand-Einmaligkeit, Zustellfehler, Opt-out, Rate-Limit, Haltemodus und Mail-Sink fehlen → NTF-001…NTF-007. GM-159/GM-160 prüfen die Ablauf-**Erinnerung** und den Abmeldelink; beides ist nach E-24 Pro (BS-30, R3-08/R3-15). Für Benachrichtigungen gibt es keine R0-Spezifikation (D12-6).
3. **Dünn (≤ 2 Tests):** R1-32, R1-33, R1-36, R1-39. **Knapp (3–4 Tests):** R1-31, R1-37. Ursache ist jeweils eine noch nicht geschriebene R0-Spezifikation: R0-27 (R1-31, R1-36), R0-28 (R1-33), R0-30 (R1-32), R0-33 (R1-37), R0-35 (R1-39). Diese Tasks bekommen ihre Tests mit diesen Dokumenten; ein Ersatzkatalog in R0-12 wäre Doppelarbeit.
4. **Sammel-Task:** R1-18 hat 11 eigene Tests, nimmt aber alle 909 Tests mit R1-Ziel ab (Gate B/C). Seine DoD wird über §3 gemessen, nicht über die Ziel-Spalte.
5. **Zuordnungsfehler in den Katalogen** (technische Korrektur, keine Entscheidung): GM-159/GM-160 → R3-15 (siehe Nr. 2). GM-189/GM-190 (Starter-Inhalte) zusätzlich R2-03, weil die Starttexte nach E-16 erst mit R2-03 kommen. RBAC-060…RBAC-066, SEC-034, PRIV-025 und CON-037 (Verifikation) zusätzlich R1-44\*.
6. **Reihenfolgedefekt:** R1-27 („Zertifikate nachvollziehbar“, REV-003/REV-045/REV-046) setzt eine Zertifikatsdomäne voraus, die erst R1-35 abnimmt; R1-35 hängt aber von R1-27 ab. Ohne Domäne vor R1-27 ist R1-27 nicht testbar (§2.3, R1-44\*).
7. **Doppelte Abdeckung** (kein Fehler, aber bei R0-24 zu konsolidieren): Gruppenjoin-Races stehen als SEAT-003…SEAT-006, CON-001…CON-006 und SEC-027 in drei Katalogen; Webhook-Signatur als RBAC-093, CT-11 und SEC-026; Secrets in Ausgaben als REC-022 und SEC-036. Vorschlag: ein Fall führt, die anderen verweisen darauf.

### 2.3 Vorgeschlagene zusätzliche R1-Tasks (nur Pflichtverhalten ohne Owner)

| Vorschlag | Titel | Voraussetzung | DoD (Vorschlag) | Begründung (warum kein bestehender Task) |
|---|---|---|---|---|
| **R1-43\*** | Domain-Events, Outbox und Connector-Registry im Free-Kern | R1-02, R1-03 | Fachänderung, Event und Outbox-Eintrag in einer TX (R0-06 §4.8); Event-Envelope v1 und Katalog nach R0-19 §5; Zustellung mit Retry/Backoff/`dead`; Event-Log 30 Tage ohne Abonnenten (E-119); Connector-Registry mit Handshake und Status (R0-19 §5.7, E-38); Haltemodus (E-85/E-86). Tests CT-06…CT-12, GRANT-002 (Outbox-Teil), REC-016, PRIV-023, SUP-029, RBAC-047/048/093 grün | E-38 und E-119 legen Events, Read-API und Registry **in Free** fest. GRANT-002, REC-016, CT-07, PRIV-023 und SUP-029 setzen in R1 eine Outbox voraus; K-06 (Cache-Invalidierung über `grant.changed`) braucht die Domain-Events in R1-25. LB hat keine Outbox (R0-06 §1.1 Nr. 6), es gibt nichts zu portieren. R3-01 **sichert** laut DoD nur die öffentliche Schnittstelle **ab** und startet erst nach R2-GATE. R1-28 betrifft REST/Block/Shortcode, R1-17 Wartungsjobs. Kein R1-Task baut die Outbox |
| **R1-44\*** | Basisnachweis-Domäne (Ausstellung, Snapshot, Verifikation, Korrektur, Widerruf) | R1-09, R1-10 | `cc_certificate` nach R0-06 E-39 und R0-08 §7.2; Zustände `issued/corrected/revoked`; Verifikationsseite L-16 mit Minimaldaten (E-97); HTML-/Druckansicht (E-96); Unique je Evidenz. Tests CERT-001…CERT-006, RBAC-060…RBAC-066, SEC-034, PRIV-025, CON-037 grün. R1-27 und R1-35 erhalten R1-44 als Voraussetzung | Kein LB-Quellbestand (R0-04 §3 „Befund ohne Quellbestand“, R0-11 §1.4 „F1 neu, kein Golden Master“). R1-35 ist ein **Abnahme**-Task nach R0-29 und R1-27, R1-27 braucht die Domäne aber schon (Befund Nr. 6). Alternative ohne neue ID: R1-35 in R1-35.1 (Domäne, vor R1-27) und R1-35.2 (UI/Abnahme) teilen (TODO-Regel „`<ID>.1`“) → D12-2 |

Weitere Pflichtverhalten ohne Owner wurden **nicht** gefunden. Geprüft wurden insbesondere Lernsperre (R1-03, E-79), Rate-Limiter-Kern (R1-02, K-25), Haltemodus (R1-23/R1-40 laut E-85…E-88), Tombstones (R1-17/R1-23, E-151), Supportaktionen und Audit-Kette (R1-38, E-136…E-145), Berichte Basic (R1-10, GM-079, E-98), Kurs-FAQ/Tipps-Mechanik (R1-14/R1-15/R1-28 laut R0-04 BS-24), Voraussetzungen zwischen Lektionen (R1-04/R1-14, E-101), Sprachversionen (R1-19, E-155…E-160) und Lernbereich-Router (R1-16, K-07).

---

## 3. Gates: prüfbare Abnahmekriterien

**Allgemeine Regeln.** (a) „grün“ heißt: automatisiert bestanden in der CI-Matrix aus R0-14 §3 bzw. R0-20, mit Protokoll (Commit, Lauf-ID, Datum); manuelle Fälle (UX, Screenreader) mit Sitzungsprotokoll nach R0-26. (b) Ein Test zählt nur dann als **„nicht anwendbar“**, wenn eine Entscheidung ihn ausschließt (Beispiel: ID-015 bei E-30 = Kontopflicht). Die E-Nummer steht in der Ergebnisliste. (c) Eine **Abweichung** vom Golden Master gilt nur mit schriftlicher Freigabe nach E-55 und Eintrag in der Abweichungsliste von R0-05. (d) Gates werden mit Datum, technischem Reviewer und Auftraggeber im TODO protokolliert (D12-3). (e) Prozentwerte beziehen sich auf die Pflichtmenge der jeweiligen Zeile. (f) Ein Katalogtest mit mehreren Ziel-Tasks muss nur einmal grün sein.

### 3.1 Gate A = R0-GATE (Portierbarkeit, Spezifikation)

| Nr. | Kriterium | Prüfung / Nachweis | Soll |
|---|---|---|---|
| A-1 | Inventur vollständig | R0-02/R0-03: 835 Einträge, `unknown` = 0; R0-04-Prüfskript `fehlend [] · doppelt []` | 100 % |
| A-2 | R1-Grenze eingefroren | R0-04 auf „beschlossen“; alle 25 Kopplungen K-01…K-25 mit Entkopplungsvertrag; Widersprüche W-01…W-12 entschieden | 100 % |
| A-3 | Entscheidungen beantwortet | E-01…E-171 und D12-1…D12-6 (§6) haben Status „beschlossen“ mit Datum. **E-01** (Codefreigabe) ist Pflicht vor jeder Wiederverwendung | 100 %; sonst Gate rot |
| A-4 | R0-Dokumente freigegeben | Entwürfe auf „beschlossen“ mit Reviewer: R0-04…R0-11, R0-12 (dieses), R0-13…R0-17, R0-19, R0-21…R0-23, R0-25, R0-26, R0-32, R0-36. **Noch zu schreiben:** R0-18, R0-20, R0-24, R0-27…R0-31, R0-33…R0-35, R0-37…R0-40 (15) | 40/40 Tasks R0-01…R0-40 abgehakt |
| A-5 | Testkataloge vollständig | Alle 920 Katalogtests haben Erwartung und Ziel-Task (§5: `ohne Ziel = 0`); die 23 Vorschläge aus §1.3 sind übernommen oder begründet verworfen; Kataloge der noch fehlenden R0-27, R0-28, R0-30, R0-33, R0-35, R0-37, R0-38 liegen vor | 100 % |
| A-6 | Traceability ohne Lücke | §5-Skript: `R1-Fälle ohne Task = 0`, `ohne Test = 0`, `unbekannte Test-IDs = []`; jede Zeile mit „Lücke“ hat einen Vorschlag aus §1.3 oder eine E-/D12-Referenz; R0-24 übernimmt §1 als Pflichtvorlage | 0 offene Pflichtlücken |
| A-7 | Ausführbare Referenztests | `cc-fixture-neutral-v1` gebaut (E-02); Referenzlauf für die Rechenlogik-GM (GM-029…GM-031, GM-065…GM-068, GM-084, GM-131) nach E-56 protokolliert; Abweichungsliste (E-55) angelegt | 100 % der genannten GM |
| A-8 | ADRs akzeptiert | ADR-001…ADR-008 (Konzept 28.2) in R0-06, R0-07, R0-08, R0-19, R0-25 als „beschlossen“ | 8/8 |
| A-9 | Datenschutz, Sicherheit, Betrieb | R0-13, R0-16, R0-17, R0-21, R0-31 freigegeben; Retention E-44/E-51/E-150 bestätigt; DSFA-Bedarf bewertet | 100 % |
| A-10 | R3-Vorbereitung | FLOW-001/002 für alle 55 Fälle: Owner, Zustände, Fehlerpfad und Testskizze dokumentiert (Grundlage §1.1); ausführbare COMMERCE-/SUITEFLOW-Kataloge erst als Eingangsbedingung von R3-01 (D12-5) | 55/55 skizziert |

### 3.2 Gate B (Free-Lernfluss, Teil von R1-GATE)

| Nr. | Kriterium | Pflichtmenge | Soll |
|---|---|---|---|
| B-1 | Golden Master Lernkern | GM-001…GM-093, GM-137…GM-144 (PAR-01…PAR-08, PAR-10) | 100 % grün oder freigegebene Abweichung |
| B-2 | Revisionen laufender Kurse | REV-001…REV-046 | 100 % |
| B-3 | Publish-Validator | PUB-001…PUB-052; Blocker nicht übersteuerbar (E-128) | 100 % |
| B-4 | Referenz-E2E | R0-11 Flow 1 (Autor erstellt und veröffentlicht), Flow 2 (Lernender lernt, besteht, erhält Nachweis), Flow 3 (Gruppenverwalter lädt ein) in Chromium, Firefox, WebKit, Desktop und Mobil (NFR-016); Praxis-Benchmark 10 Lektionen / 50 Fragen / 1 Prüfung (Konzept 27.4, GM-187; Aufgabe erst R2 nach E-168) | 3/3 Flows, alle Zielzeilen |
| B-5 | Kurs ohne Import | GM-137 und Release-Gate 23.2 Nr. 2 | grün |
| B-6 | Standalone | SA-001, CT-09, NFR-017, PRIV-017; CI-Lauf ohne Woo/Suite/Pro (K-24) | grün |
| B-7 | Prozessfälle mit Gate B | alle Fälle aus §1 mit Gate „B“ bzw. „B/C“ | 100 % ihrer Tests grün |
| B-8 | Zertifikatsbasis | CERT-001…CERT-006 (nach Übernahme), SEC-034, PRIV-025, LOC-020…LOC-022 | 100 % |

### 3.3 Gate C (Betriebsreife, Teil von R1-GATE)

| Nr. | Kriterium | Pflichtmenge | Soll |
|---|---|---|---|
| C-1 | Rechte, IDOR, Sicherheit | RBAC-001…RBAC-096 (+RBAC-097), SEC-001…SEC-046 ohne R3-Fälle (SEC-024, SEC-025, SEC-026, SEC-030, SEC-038), CT-02, CT-10, SUP-001…SUP-008 | 100 %; RBAC-096 Körpergleichheit ohne Ausnahme |
| C-2 | Grants, Seats, Identität, Gate (R1-Teil) | GM-094…GM-117; GRANT-001…GRANT-022, GRANT-027, GRANT-028; SEAT-001…SEAT-019, SEAT-021, SEAT-022; ID-001…ID-014 | 100 % (R3-Teil GRANT-023…026, GRANT-029, SEAT-020, ID-015 ausgenommen) |
| C-3 | Import und Medien | IMP-001…IMP-032, MED-001…MED-026, GM-118…GM-136 | 100 % |
| C-4 | Nebenläufigkeit und Cache | CON-001…CON-048, NFR-010…NFR-012 auf MariaDB und MySQL 8.4 | 100 % |
| C-5 | Recovery | REC-001…REC-025 (+REC-026), PRIV-010, PRIV-011; REC-024 nur „bedingt“ nach E-49 | 100 % |
| C-6 | Nichtfunktionale Ziele | NFR-001…NFR-018 (+NFR-019), GM-187, GM-188 auf R-M bzw. R-S; Zeitbudgets sind Gate (E-60) | 100 %; Messprotokoll nach NFR-018 |
| C-7 | API-Verträge | CT-01…CT-12, GM-145…GM-155, SEC-002, SEC-039 | 100 %; Routen-Snapshot ohne undeklarierte Änderung |
| C-8 | Betrieb, Migration, Lifecycle, Support | GM-156…GM-186 ohne GM-159/GM-160 (Pro, E-24 → R3-15); GM-189/GM-190 nur bei E-16 = Mechanik R1; SUP-009…SUP-042; MS-001…MS-023 | 100 % |
| C-9 | Datenschutz | PRIV-001…PRIV-030 | 100 % |
| C-10 | Accessibility, i18n, Locale | A11Y- und I18N-Katalog R0-15, LOC-001…LOC-029: 100 % der automatisierten Fälle grün; Screenreader-Sitzungen mit NVDA und VoiceOver (E-126) | 0 Befunde S1; S2 nur nach E-171 |
| C-11 | Usability | UX-Katalog R0-26 mit Schwellen E-166 (Erfolg ≥ 80 % je Aufgabe, SUS ≥ 70 …), Teilnehmerzahl E-163, Nachtest nach Behebung | alle Schwellen erreicht |
| C-12 | Benachrichtigungen | NTF-001…NTF-007, PRIV-021, LOC-016…LOC-019, SEC-040 | 100 % |
| C-13 | Sicherheitsprüfung | SAST/Dependency-Scan nach R0-18 ohne offenen Befund „hoch“; Maßnahmen aus R0-17 umgesetzt | 0 hoch |
| C-14 | Kompatibilität | Matrix R0-20 grün (WP/PHP/DB laut E-57, E-58; Browser E-61); Konflikttests R1-30 dokumentiert | 100 % Zielzeilen |
| C-15 | Distribution | R1-39: Free-ZIP Clean-Install, Upgrade (REC-023), WordPress.org-Readme, SEC-045, Deaktivierung | grün |
| C-16 | Release-Gates Konzept 23.2 | Nr. 1–3 und 6–9 nachgewiesen; Nr. 4 und 5 (Pro, Suite) gehören zu R3 | 7/7 |

**R1-GATE** = Gate B **und** Gate C, alle R1-Tasks (42, nach D12-1/D12-2 bis zu 44) abgehakt, keine offenen Fehler der Schwere „kritisch/hoch“, Freigabe protokolliert.

### 3.4 Gate D (Erweiterungen: R2-GATE, R3-GATE, R4 je Feature)

| Nr. | Kriterium | Soll |
|---|---|---|
| D-1 | Regression: die R1-Pflichtmenge aus §3.2/§3.3 bleibt grün | 100 % |
| D-2 | Jedes R2-Feature (R2-01…R2-04) hat einen eigenen Katalog (positiv, negativ, Rechte, Export/Eraser, Migration) und erfüllt R2-06; UX-Nachtest nach E-170 | 100 % je Feature |
| D-3 | R3.1/R3.2: R3-Teil der Kataloge (GRANT-023…026, GRANT-029, SEAT-020, ID-015, SEC-024, SEC-025) und neue Kataloge für COMMERCE-01…COMMERCE-10 (R3-FIRST-001…004, CORE-SPEC-009) | 100 % |
| D-4 | R3.3: Woo allein, Woo + Voucher, beide deaktiviert (ENTSCHEIDUNGEN §7, R3-FIRST-105); CORE-01…CORE-12 vollständig mit Adaptern | 100 % |
| D-5 | R3.4: je Connector der SUITEFLOW-Fall mit Contract-Tests (CT-06…CT-12, SEC-026, SEC-030) und Freigabe der Event-Klassen (E-120) | 100 % je Connector |
| D-6 | Pro: Lizenzende, Grace, Server offline (SUITEFLOW-07, CORE-10, CERT-008, SEC-038); Core-ZIP ohne Lizenzcode (R3-18) | 100 % |
| D-7 | Einzelfreigabe nach Nutzen, Abhängigkeiten und Aufwand (Konzept 28.6) dokumentiert | je Feature |

---

## 4. Risikoliste (Top 15)

W = Wahrscheinlichkeit, A = Auswirkung (h = hoch, m = mittel, n = niedrig).

| Nr. | Risiko | W | A | Gegenmaßnahme | Task |
|---|---|---|---|---|---|
| RK-01 | Keine Codefreigabe (E-01) → Portierung wird Neuimplementierung, Golden Master ohne Referenzlauf | m | h | E-01 als erste Entscheidung; bis dahin nur Verhaltensspezifikation, keine Codekopie | R0-GATE, R1-18 |
| RK-02 | 171 offene Entscheidungen und 15 noch nicht geschriebene R0-Dokumente verzögern Gate A | h | h | Sammelbeschluss „Empfehlungen annehmen außer …“; fehlende R0-Tasks nach Abhängigkeit abarbeiten (R0-18, R0-20, R0-24, R0-27 zuerst, weil sie Gate-Kataloge liefern) | R0-GATE |
| RK-03 | Golden Master falsch aus Testcode abgeleitet (Smarttrainer-Reihenfolge, Kulanz, Intervalle) | m | h | Referenzlauf nach E-56 für Rechenlogik; Abweichungsliste nach E-55 | R1-12, R1-18 |
| RK-04 | Outbox/Event-Kern ohne Owner → Doppelmails, fehlende Cache-Invalidierung, R3 baut nachträglich um | h | h | R1-43\* anlegen; CT-06…CT-12, PRIV-023 in der R1-CI | R1-43\*, R1-25 |
| RK-05 | Zertifikatsdomäne neu ohne Vorbild, falsche Task-Reihenfolge | h | m | R1-44\* bzw. R1-35.1 vor R1-27; R0-29 mit CERT-Katalog | R1-44\*, R1-35 |
| RK-06 | Races bei Seats, Grants, Versuchen (GET_LOCK NULL auf Shared Hosting, Deadlocks) | m | h | Lock-Reihenfolge R0-09 §6.1/R0-21, Uniques als letzte Linie, L5-Runner auf MariaDB und MySQL (NFR-010…012, CON-001…CON-046) | R1-42 |
| RK-07 | Geschützte Medien unter nginx oder hinter Page-Cache/CDN öffentlich | h | h | Selbsttest und Health `exposed` (MED-007, GM-115), privater Ordner außerhalb Webroot, Cache-Header (CON-031…CON-036, SEC-032) | R1-14, R1-25, R1-38 |
| RK-08 | Staging-Kopie für CPT-Lektionen (E-104) erzeugt Leaks in REST, Suche, Sitemap oder Slug-Konflikte | m | h | REV-039…REV-044 früh als Prototyp; SEO-Grenzen in R0-33 | R1-27, R1-37 |
| RK-09 | Usability- und Screenreader-Abnahme scheitert spät (Rekrutierung, Hilfstechnik, Nachtest) | m | h | Rekrutierung und Budget früh beschließen (E-161…E-164); Pilot-Sitzung vor Feature-Freeze; automatisierte A11Y-Fälle in jeder CI | R1-19, R1-29 |
| RK-10 | Performance auf Shared Hosting verfehlt (Budgets ungemessen) | m | m | früher Messlauf L1/L2 auf R-S; Kapazitätsklasse nach E-59 dokumentieren | R1-22 |
| RK-11 | Unterbrochene Migration verliert Lernstände oder führt Schritte doppelt aus | n | h | Lock und `cc_migrations_applied`, Backfill-Cursor, REC-006…REC-008, MS-020, Backup-Pflicht vor Upgrade | R1-40, R1-23 |
| RK-12 | Gelöschte personenbezogene Daten kehren nach Restore zurück, weil das Journal im Backup liegt | m | h | Journal außerhalb des Backups (E-151), Health-Hinweis (PRIV-012), PRIV-010 in der Recovery-Probe | R1-17, R1-23 |
| RK-13 | Woo-Kopplungen (K-01…K-25) sickern in den Free-Kern; R3 blockiert R1 | m | h | CI ohne Woo (K-24), FakeSource (K-02), Review-Regel „kein `wc_`/`order_` im Kern“, SA-001 | R1-13, R1-18 |
| RK-14 | Editionswidersprüche (E-53 Pro-Connector vs. Kap. 43.3, E-24 vs. R1-21-DoD, Partnererkennung F1 vs. R3) führen zu Nacharbeit | h | m | Korrekturen aus §2.2 Nr. 2 und Nr. 5 einarbeiten; D12-4 entscheiden | R1-21, R1-38, R3-16 |
| RK-15 | Testvolumen (> 940 Fälle in 20 Katalogen, teils doppelt) macht die CI langsam und instabil; R3-Kataloge fehlen | h | m | Stufen-CI (Unit/Integration/E2E/Last/manuell getrennt, R0-18), feste Seeds, Wiederholregel nur für L5, Dubletten nach §2.2 Nr. 7 konsolidieren; R3-Kataloge als Eingangsbedingung von R3-01 (D12-5) | R0-18, R0-24, R1-18, R3-01 |

---

## 5. Nachweis der Zählungen (Skript)

Kommando (aus dem Repository-Wurzelverzeichnis, Python 3, nur lesend):

```bash
python3 - <<'PY'
import re, collections
D = 'docs/r0/'
PFX = r'(GM|RBAC|GRANT|SEAT|ID|IMP|MED|NFR|REC|REV|SUP|A11Y|I18N|SEC|PRIV|CON|PUB|LOC|MS|UX)-\d{3}'
CAT = ['R0-05-golden-master.md', 'R0-07-rbac-idor-matrix.md', 'R0-09-identity-seat-grant-automaten.md',
       'R0-10-import-medien-sicherheit.md', 'R0-13-support-betrieb.md', 'R0-14-nichtfunktionale-ziele.md',
       'R0-15-barrierefreiheit-i18n.md', 'R0-16-backup-recovery-deployment.md', 'R0-17-threat-model-dsfa.md',
       'R0-19-api-erweiterungsvertraege.md', 'R0-21-cache-nebenlaeufigkeit.md', 'R0-22-kursvalidierung-publish.md',
       'R0-23-revisionen-laufender-kurse.md', 'R0-25-mandanten-locale-adr.md', 'R0-26-ux-abnahme-nutzertests.md']
def cells(l): return [c.strip().strip('*').strip() for c in l.strip().strip('|').split('|')]
tests = {}                                   # Test-ID -> Ziel-Tasks (erste Fundstelle mit Ziel gilt)
for f in CAT:
    hdr = None
    for l in open(D + f, encoding='utf-8'):
        if not l.startswith('|'): hdr = None; continue
        c = cells(l)
        if hdr is None: hdr = c; continue
        if set(''.join(c)) <= set('-: '): continue
        ic = next((i for i, h in enumerate(hdr) if h in ('GM-ID', 'Test-ID', 'ID', 'Anker')), None)
        if ic is None or ic >= len(c): continue
        m = re.match(PFX + '|CT-\\d{2}', c[ic])
        if not m: continue
        t = m.group(0)
        if f.startswith('R0-05') and not t.startswith('GM-'): continue      # R0-05 §2: Fixture-IDs, keine Tests
        if t in tests and tests[t]: continue
        if t.startswith('CT-'): tests[t] = {'R1-28', 'R3-12'}; continue    # R0-19 §9: Anker für R1-28/R3-12
        zc = next((i for i, h in enumerate(hdr) if h.startswith('Ziel')), None)
        z = set(re.findall(r'R[0-4]-\d{2}', c[zc] if zc is not None and zc < len(c) else ''))
        if t.startswith('UX-'): z |= {'R1-29'} | set(re.findall(r'R1-\d{2}', l))  # R0-26 §5: alle Ziel R1-29
        tests[t] = z
def expand(s):                               # "GM-118…GM-136" -> Einzel-IDs
    out = []
    for a, b in re.findall(r'([A-Z]+-\d{2,3})(?:…([A-Z]+-\d{2,3}))?', s):
        if b and a.split('-')[0] == b.split('-')[0]:
            p = a.split('-')[0]; out += [f'{p}-{n:03d}' for n in range(int(a.split('-')[1]), int(b.split('-')[1]) + 1)]
        else: out.append(a)
    return out
cases, bytask = [], collections.defaultdict(set)
for l in open(D + 'R0-12-tests-gates-backlog.md', encoding='utf-8'):
    if re.match(r'^\| (ID|LEARN|ACCESS|COMMERCE|SUITEFLOW|OPS|CORE)-\d{2} \|', l):
        c = cells(l); fid, rel, tasks, tst, gap = c[0], c[2], c[3], c[4], c[6]
        ids = [t for t in expand(tst) if re.fullmatch(PFX + '|CT-\\d{2}', t)]
        r1 = [t for t in re.findall(r'R1-\d{2}', tasks) if int(t[3:]) <= 42]
        for t in re.findall(r'R1-\d{2}', tasks): bytask[t].add(fid)
        cases.append((fid, 'R1' in rel, r1, ids, gap))
fam = collections.Counter(re.sub(r'-\d+$', '', f) for f, *_ in cases)
print('Fälle', len(cases), dict(fam))
r1 = [x for x in cases if x[1]]
print('R1-Fälle', len(r1), '| ohne R1-Task', [x[0] for x in r1 if not x[2]],
      '| ohne Test', [x[0] for x in r1 if not x[3]])
print('unbekannte Test-IDs', sorted({t for x in cases for t in x[3] if t not in tests}))
print('Fälle mit Lücke', sum(1 for x in cases if x[4] not in ('–', '-')))
print('Katalogtests', len(tests), dict(sorted(collections.Counter(re.sub(r'-\d+$', '', t) for t in tests).items())))
print('ohne Ziel', [t for t, z in tests.items() if not z],
      '| mit R1-Ziel', sum(1 for z in tests.values() if any(x.startswith('R1') for x in z)))
cov = collections.Counter(x for z in tests.values() for x in z)
for n in range(1, 43):
    k = f'R1-{n:02d}'
    print(k, 'Tests', cov[k], '| Fälle', ' '.join(sorted(bytask[k])) or '–')
PY
```

Ergebnis am 16.11.2026:

```
Fälle 67 {'ID': 6, 'LEARN': 10, 'ACCESS': 9, 'COMMERCE': 10, 'SUITEFLOW': 10, 'OPS': 10, 'CORE': 12}
R1-Fälle 45 | ohne R1-Task [] | ohne Test []
unbekannte Test-IDs []
Fälle mit Lücke 46
Katalogtests 920 {'A11Y': 66, 'CON': 48, 'CT': 12, 'GM': 190, 'GRANT': 29, 'I18N': 15, 'ID': 15, 'IMP': 32, 'LOC': 29, 'MED': 26, 'MS': 23, 'NFR': 18, 'PRIV': 30, 'PUB': 52, 'RBAC': 96, 'REC': 25, 'REV': 46, 'SEAT': 22, 'SEC': 46, 'SUP': 42, 'UX': 58}
ohne Ziel [] | mit R1-Ziel 909
R1-01 Tests 8 | Fälle OPS-07 OPS-10
R1-02 Tests 10 | Fälle OPS-07
R1-03 Tests 105 | Fälle CORE-09 ID-01 ID-04 ID-05 OPS-02
R1-04 Tests 14 | Fälle ACCESS-07 LEARN-01
R1-05 Tests 7 | Fälle LEARN-01 LEARN-02
R1-06 Tests 12 | Fälle LEARN-04
R1-07 Tests 16 | Fälle LEARN-02
R1-08 Tests 31 | Fälle LEARN-04 LEARN-05
R1-09 Tests 8 | Fälle LEARN-04 LEARN-05
R1-10 Tests 17 | Fälle LEARN-03 LEARN-07
R1-11 Tests 10 | Fälle LEARN-06
R1-12 Tests 13 | Fälle LEARN-06
R1-13 Tests 68 | Fälle ACCESS-01 ACCESS-02 ACCESS-03 ACCESS-04 ACCESS-05 ACCESS-06 ACCESS-07 ACCESS-09 CORE-01 CORE-02 CORE-03 CORE-04 CORE-06 CORE-07 CORE-12 ID-01 ID-02 ID-03 ID-05 OPS-03
R1-14 Tests 59 | Fälle ACCESS-05 ACCESS-08 LEARN-02
R1-15 Tests 60 | Fälle LEARN-10
R1-16 Tests 17 | Fälle ID-01 LEARN-03 OPS-08
R1-17 Tests 49 | Fälle ACCESS-05 ID-05 OPS-01 OPS-05 OPS-06
R1-18 Tests 11 | Fälle CORE-10 OPS-10
R1-19 Tests 84 | Fälle OPS-08
R1-20 Tests 19 | Fälle LEARN-03 LEARN-04 LEARN-05 OPS-04
R1-21 Tests 8 | Fälle OPS-08
R1-22 Tests 16 | Fälle OPS-05 OPS-09
R1-23 Tests 34 | Fälle ID-06 OPS-07
R1-24 Tests 9 | Fälle OPS-06
R1-25 Tests 26 | Fälle ACCESS-05 ACCESS-06
R1-26 Tests 64 | Fälle LEARN-01
R1-27 Tests 46 | Fälle ACCESS-06 LEARN-01 LEARN-10
R1-28 Tests 30 | Fälle OPS-02 SUITEFLOW-01
R1-29 Tests 77 | Fälle OPS-08
R1-30 Tests 7 | Fälle ID-06
R1-31 Tests 3 | Fälle ID-04
R1-32 Tests 2 | Fälle OPS-07
R1-33 Tests 2 | Fälle LEARN-04
R1-34 Tests 12 | Fälle ID-04 OPS-01
R1-35 Tests 20 | Fälle CORE-02 CORE-06 CORE-11 ID-03 LEARN-08
R1-36 Tests 2 | Fälle OPS-07
R1-37 Tests 4 | Fälle ACCESS-08 LEARN-01
R1-38 Tests 56 | Fälle ID-05 OPS-05 OPS-06
R1-39 Tests 2 | Fälle OPS-07
R1-40 Tests 16 | Fälle OPS-07
R1-41 Tests 11 | Fälle LEARN-10
R1-42 Tests 54 | Fälle CORE-03 OPS-04 OPS-09
```

**Grenzen der Zählung:** Das Skript liest die Ziel-Spalten der Kataloge, nicht deren fachliche Richtigkeit. Nebenziele in Klammern (z. B. REV „R1-27 (R1-10)“) zählen mit. Die 23 Vorschläge aus §1.3 sind nicht enthalten, bis sie in die Fachkataloge übernommen sind. CT-01…CT-12 tragen in R0-19 keine Ziel-Spalte; das Skript ordnet sie nach R0-19 §9 R1-28 und R3-12 zu. UX-Fälle haben laut R0-26 §5 alle das Ziel R1-29. Kommt eine Test-ID mehrfach vor (z. B. A11Y-001 in einer Übersicht), gilt die erste Zeile mit Ziel-Spalte. R0-05 §2 enthält Fixture-IDs (MED-001…), die nicht als Tests zählen.

---

## 6. Entscheidungsbedarf (neu, nicht in `ENTSCHEIDUNGEN.md`)

Geprüft gegen E-01…E-171. Bereits registriert und hier nur als Abhängigkeit genutzt: E-01, E-16, E-24, E-25, E-30, E-36, E-38, E-44, E-51, E-55, E-56, E-60, E-83, E-94, E-96, E-97, E-113, E-114, E-119, E-136, E-150, E-151, E-152, E-158, E-163, E-166, E-168, E-171. Technische Korrekturen ohne Auftraggeber-Entscheidung stehen in §2.2 Nr. 2, 5 und 7.

| ID | Frage | Optionen | Empfehlung | Blockiert |
|---|---|---|---|---|
| **D12-1** | Neuer Task **R1-43** „Domain-Events, Outbox und Connector-Registry im Free-Kern“ in die R1-Liste aufnehmen? | a) ja, eigener Task vor R1-13/R1-25 · b) als Untertask R1-28.1 · c) nein, Outbox erst mit R3-01 | **a)**: E-38/E-119 sagen Free; R1-Tests setzen die Outbox voraus (§2.3) | R1-13, R1-21, R1-25, R3-01 |
| **D12-2** | Zertifikatsdomäne: neuer Task **R1-44** oder Teilung von R1-35? | a) R1-44 (vor R1-27), R1-35 bleibt UI/Abnahme · b) R1-35.1 Domäne / R1-35.2 Abnahme, Voraussetzungen anpassen · c) unverändert | **a)** oder b); c) lässt R1-27 untestbar | R1-27, R1-35, R0-29 |
| **D12-3** | Gate-Schwelle und Freigabeinstanz: Müssen alle R1-Pflichttests zu **100 %** grün sein, und wer zeichnet Gate A–D ab? (E-166/E-171 regeln nur die UX-Schwellen und S2-Befunde) | a) 100 %, Ausnahmen nur „nicht anwendbar“ per E-Nr. oder E-55-Abweichung; Abzeichnung Auftraggeber + technischer Reviewer · b) ≥ 95 % mit Fehlerliste · c) 100 % nur für Sicherheit und Datenintegrität (RBAC, SEC, PRIV, GRANT, SEAT, ID, CON, REC, IMP, MED), sonst ≥ 95 % | **a)**: Paritätsprinzip Kap. 38 („100 % der fachneutralen Paritätsmatrix“) | R0-GATE, R1-GATE |
| **D12-4** | Partnererkennung und Integrationsseite (R0-11 A-14 „F1“) in R1 oder erst R3-16/FLOW-204? | a) R1 nur lesend (erkannt/inaktiv/inkompatibel), kein Opt-in, kein Datenfluss · b) komplett R3 · c) komplett R1 | **a)**, gekoppelt an R1-43\*; Opt-in und Datenfreigabe bleiben R3 (FLOW-204, E-120) | R1-38, R1-43\*, R3-16 |
| **D12-5** | Müssen ausführbare Testkataloge für COMMERCE-01…10 und SUITEFLOW-01…10 schon für **Gate A** vorliegen? (FLOW-001/002 und R3-FIRST-001…004 stehen im R0-Abschnitt) | a) ja, vollständig vor R0-GATE · b) für Gate A nur Owner, Zustände und Testskizze (A-10); ausführbare Kataloge als Eingangsbedingung von R3-01 nach Code-Gegenprüfung (CORE-SPEC-009) · c) erst in R3 | **b)**: Partner-APIs müssen ohnehin vor R3 neu geprüft werden (Kap. 34.7), sonst veraltet der Katalog | R0-GATE, R3-01 |
| **D12-6** | Wo wird der Benachrichtigungsvertrag für R1-21 spezifiziert (Ereignisse, Vorlagen, Opt-out-Klassen, Rate-Limits, Versandregeln)? Es gibt keine R0-Task dafür; E-136 und E-159 regeln nur Teilaspekte | a) in R0-27 (Settings-Register) als eigenes Kapitel mit NTF-Katalog · b) neue R0-Task · c) erst in R1-21 | **a)**: keine neue ID; R0-27 ist ohnehin Voraussetzung von R1-31/R1-34 | R1-21, R1-34 |

---

## 7. Deltas zu anderen Dokumenten (mit deren Freigabe einzuarbeiten)

| Dokument | Delta |
|---|---|
| TODO | R1-43\*/R1-44\* nach D12-1/D12-2; Voraussetzung von R1-27 und R1-35 um R1-44 ergänzen; R1-21-DoD „Ablauf“ = Mitteilung nach Ablauf (E-24) |
| R0-05 | GM-159/GM-160 Ziel → R3-15; GM-189/GM-190 zusätzlich R2-03; GM-191…GM-193 aufnehmen |
| R0-07 | RBAC-097 aufnehmen; RBAC-060…RBAC-066 zusätzlich R1-44\* |
| R0-14 | NFR-019 (Masseneinschreibung); Test-IDs für RB-01, RB-02, RB-07, RB-08 |
| R0-16 | REC-026 (Secret-Rotation) |
| R0-17, R0-21 | SEC-034, PRIV-025, CON-037 zusätzlich R1-44\* |
| R0-23 | REV-047 (endgültiges Löschen) |
| R0-24 | Tabelle §1 als Pflichtvorlage; je Fall Spalten für die FLOW-002-Dimensionen (Ablehnung, Duplicate, Parallelität, Timeout, Partnerausfall, Retry, Widerruf, Recovery); Dubletten aus §2.2 Nr. 7 auf einen führenden Fall zurückführen |
| R0-27 | NTF-001…NTF-006 (D12-6); Setting „Bestands-Grants bei Connector-Deaktivierung“ (ACCESS-09) |
| R0-29 | CERT-001…CERT-006, CERT-008 |
| R0-08 | CERT-007 (Kompetenz) |
| R0-18 | SA-001 und NTF-007 als CI-Pflichtjobs; Stufen-CI (RK-15) |

---

## 8. Nachweis

- Gelesen: `TODO-WETTBEWERBSANALYSE.md` vollständig; `konzept.md` Kap. 23, 28.6, 34, 35, 38, 39; `docs/r0/ENTSCHEIDUNGEN.md` (E-01…E-171); R0-04 §1–§5; R0-05 §1, §4–§6; R0-06 (Zertifikat- und Outbox-Entitäten); R0-07 §5, §7; R0-08 §4.4, §8; R0-09 §5, §6, §8; R0-10 §6–§7; R0-11 §1.4, §2.2, §2.3, §5; R0-14 §2.2, §4, §5; R0-16 §4, §6; R0-19 §7–§9; R0-23 §7–§8. Testkataloge der während der Bearbeitung hinzugekommenen Entwürfe R0-13 (SUP), R0-15 (A11Y, I18N), R0-17 (SEC, PRIV), R0-21 (CON), R0-22 (PUB), R0-25 (LOC, MS), R0-26 (UX) wurden zeilenweise nach Ziel-Task und Stichworten ausgewertet, nicht vollständig fachlich gelesen.
- Zählungen: Skript in §5, Ergebnis dort wörtlich. Die Zuordnung Fall → Test in §1 ist fachlich hergeleitet und muss im Review gegengeprüft werden. Das Skript prüft nur, dass die IDs existieren und jeder R1-Fall einen Task und einen Test hat.
- Nicht erfolgt: keine Änderung an anderen Dateien, keine Implementierung, kein Testlauf, kein Commit.
