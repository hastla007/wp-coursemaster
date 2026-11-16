# R0-12 · Tests, Gates und R1-Backlog abgleichen (Entwurf)

**Status:** Entwurf zur Abnahme, **nicht abgehakt**. Keine Implementierung, kein Testlauf, kein Commit. Die DoD verlangt Review und Freigabe. · **Stand:** 16.11.2026
**Bezug:** TODO R0-12 (Voraussetzung R0-05, R0-07, R0-08, R0-09, R0-10, R0-11), FLOW-001/002/004, CORE-SPEC-007/008, R0-PAR-003, LB-AUDIT-006/007, R0-GATE-001; Konzept Kap. 23 (QA-Matrix, Release-Gates), 28.6 (Gates A–D), 34 (55 Prozessfälle), 35.5 (CORE-01…CORE-12), 38 (PAR-01…12), 39 (LB-01…15)
**Quellen (nur gelesen):** `TODO-WETTBEWERBSANALYSE.md` (R0-01…R0-40, R1-01…R1-42, R2/R3/R4, FLOW-001…205, CORE-SPEC-001…010, SUITE, SEAM, R3-FIRST, R0-RBAC/DATA/PAR, LB-AUDIT), `konzept.md`, `docs/r0/ENTSCHEIDUNGEN.md` (E-01…E-120), R0-04 (Bausteine BS-01…BS-36, Kopplungen K-01…K-25), Testkataloge R0-05 (GM), R0-07 (RBAC), R0-09 (GRANT/SEAT/ID), R0-10 (IMP/MED), R0-14 (NFR), R0-16 (REC), R0-19 (CT-Anker), R0-23 (REV); Verträge R0-06, R0-08, R0-11.

**Kernzahlen** (per Skript, §5)

| Größe | Wert |
|---|---:|
| Prozess- und Kernfälle (ID 6 · LEARN 10 · ACCESS 9 · COMMERCE 10 · SUITEFLOW 10 · OPS 10 · CORE 12) | 67 |
| davon mit R1-Anteil (Pflicht für Gate B/C) | 46 |
| R1-Fälle ohne R1-Task / ohne Test | 0 / 0 |
| Fälle mit dokumentierter Lücke | 54 |
| Testfälle in den R0-Katalogen (GM 190 · RBAC 96 · GRANT 29 · SEAT 22 · ID 15 · IMP 32 · MED 26 · NFR 18 · REC 25 · REV 46 · CT 12) | 511 |
| davon mit R1-Ziel-Task | 505 |
| R1-Tasks ohne Katalogtest / mit dünner Abdeckung (≤ 2 Tests) | 1 (R1-29) / 10 |
| Vorgeschlagene zusätzliche R1-Tasks | 2 (R1-43, R1-44) |
| Vorgeschlagene ergänzende Testfälle (§1.3) | 27 |

**Lesehilfe.** „Release“ folgt R0-04 (Baustein BS-xx, Regeln Z-1…Z-10): **R1** = Free-Kern, **R1+R3** = neutraler Kern in R1, Adapter in R3.x, **R2** = zusätzliche P0, **R3.1/R3.2/R3.3/R3.4** = Woo, Voucher, gemeinsames Gate, übrige Suite, **R4** = nach Nachfrage. „Gate“ meint Konzept 28.6: **A** Portierbarkeit (= R0-GATE), **B** Free-Lernfluss, **C** Betriebsreife (B und C = R1-GATE), **D** Erweiterungen (R2-GATE, R3-GATE je Feature). Test-IDs mit „…“ sind lückenlose Bereiche. Ein Stern (R1-43\*, R1-44\*) kennzeichnet einen **vorgeschlagenen** Task (§2.3). „Lücke“ nennt, was fehlt; „–“ heißt: keine bekannte Lücke.

---

## 1. Traceability: Prozessfälle und Kernabnahmefälle

### 1.1 Prozessfälle Kap. 34 (55)

| Fall-ID | Kurzbeschreibung | Release (R0-04) | R1/R3-Task(s) | Testfall-IDs | Gate | Lücke |
|---|---|---|---|---|---|---|
| ID-01 | Angemeldeter Nutzer erhält nur eigene Kurse; Rollenwechsel, Sperre, fremde Kurs-ID ohne IDOR | R1 (BS-03, BS-12) | R1-03, R1-13, R1-16 | RBAC-001, RBAC-002, RBAC-006, RBAC-008, RBAC-041, RBAC-096, GM-001, GM-049, GM-151, GRANT-019 | C | – |
| ID-02 | Gastkauf: Zugang erst nach verifiziertem Claim, nie Übernahme per E-Mail | R1+R3 (Claim-Kern BS-14; Gastkauf BS-31, R3.1) | R1-13; R3-02, R3-13 | ID-001, ID-003, ID-004, ID-005, ID-015, RBAC-054, SEAT-015 | C · R3-GATE | E-30 (Kontopflicht statt Claim-Token, Abweichung von CORE-SPEC-002) offen; ID-015 hängt daran |
| ID-03 | Käufer ≠ Lernender, mehrere Plätze; jede Zuweisung einmalig und bestätigt | R1+R3 (Seat-Kern BS-14; Kauf R3.1) | R1-13, R1-35; R3-02 | SEAT-001, SEAT-002, SEAT-005, SEAT-009, SEAT-017, GM-094, GM-098, ID-007, RBAC-036, RBAC-059 | C · R3-GATE | Kauf für Dritte in R1 nur über FakeSource; Woo-Mehrmenge erst SEAT-020 (R3) |
| ID-04 | Getrennte Objekt-/Site-Rechte für Trainer, Autor, Tutor, Shop-Manager, Admin | R1 (BS-03) | R1-03, R1-31, R1-34 | RBAC-003, RBAC-004, RBAC-005, RBAC-011, RBAC-024, RBAC-025, RBAC-032, RBAC-033, RBAC-042, RBAC-062, RBAC-091, RBAC-092, GM-153, GM-154 | C | Kein Test, dass `editor`/`shop_manager` bei Aktivierung keine `cc_*`-Caps erhalten (E-83) → RBAC-097 |
| ID-05 | E-Mail-Wechsel, Kontolöschung, Sperre, Zusammenführung | R1 (BS-19, BS-12) | R1-17, R1-13, R1-03, R1-38 | ID-008…ID-014, GM-102, GM-177, RBAC-050, GRANT-019 | C | Merge-Werkzeug nicht in R1 (E-114), nur „Zugang übertragen“ (ID-012) |
| ID-06 | Multisite/Cross-Site-Identität | R4 (W-11, E-07); R1 nur Site-Isolation (BS-26) | R1-23, R1-30; R4-01 | RBAC-010, RBAC-078, GM-186, REC-024 | C (Isolation) · R4 | R0-25 fehlt; RBAC-010/078 nur „bedingt“ |
| LEARN-01 | Kurs: Entwurf → veröffentlicht → Revision → archiviert | R1 (BS-04, BS-05) | R1-04, R1-05, R1-26, R1-27 | GM-001, GM-003, GM-137, GM-144, RBAC-002, RBAC-004, RBAC-017, REV-038, REV-039, REV-040, REV-043, REV-044 | B | Blocker-/Warnungskatalog des Publish-Validators fehlt (R0-22) |
| LEARN-02 | Lektion, Video, Datei, Activity bearbeiten, freigeben, absolvieren; keine fremden Uploads | R1 (BS-07, BS-15) | R1-05, R1-07, R1-14 | GM-007…GM-016, MED-001…MED-005, MED-010, RBAC-067…RBAC-076, IMP-012 | B/C | – |
| LEARN-03 | Fortschritt speichern, unterbrechen, fortsetzen, Gerätewechsel; nie rückwärts | R1 (BS-09) | R1-10, R1-16, R1-20 | GM-011, GM-027, GM-036, GM-075, GM-077, REV-036 | B | Kein Test für verspätete/doppelte Lektions-Fortschritts-Requests (Monotonie, E-70) → GM-191 |
| LEARN-04 | Prüfung starten, speichern, Zeitlimit, Abgabe, Bewertung | R1 (BS-08) | R1-08, R1-09, R1-20 | GM-027…GM-029, GM-035…GM-039, GM-045, GM-050, GM-053, GM-054, NFR-005, REC-011 | B/C | – |
| LEARN-05 | Nicht bestanden, Wiederholung, Versuchslimit, Sperrfrist; keine Umgehung per URL/REST | R1 (BS-08) | R1-08, R1-09, R1-20 | GM-030, GM-040, GM-041, GM-046, RBAC-031, REV-024 | B | `cooldown_seconds` (R0-08 §4.4), `max_attempts` ohne Revisionswechsel und „Abbruch zählt“ (E-71) ohne Test → GM-192 |
| LEARN-06 | Smarttrainer, Karteikarten, Favoriten, Fehlertraining, Wiederholungsplan | R1 (BS-10, BS-11) | R1-11, R1-12 | GM-055…GM-074, RBAC-021, RBAC-023, REV-029, REV-035 | B | Referenzlauf der Rechenlogik offen (E-56); Abweichungsliste offen (E-55) |
| LEARN-07 | Completion, Mastery, Competency getrennt | R1 (BS-09) | R1-10 | GM-009, GM-066, GM-075, GM-076, REV-001, REV-003, REV-025 | B | Competency (abgeleitet, `competency_validity_days`, E-73) und „Zahlung ist keine Kompetenz“ ohne Test → CERT-009 |
| LEARN-08 | Zertifikat ausstellen, verifizieren, korrigieren, widerrufen | R1 Free-Basis (Z-3, E-96); Designer R3 | R1-35, R1-44\* | RBAC-060…RBAC-066, REV-045, REV-046, GRANT-028 | C | Kein LB-Quellbestand, kein GM (R0-04 §3, R0-06 E-39). Ausstellung, Einmaligkeit (R0-09 R-06) und Verifikationsseite L-16 ohne Test → CERT-001…CERT-008; R0-29 fehlt; Task-Reihenfolge R1-27 → R1-35 widersprüchlich (§2.3) |
| LEARN-09 | Lernnotizen, Aufgabenabgabe, Trainerfeedback, Vorlagen | R2 (Z-8) | R2-01…R2-04 | – | D (R2-GATE) | Für R1 nicht anwendbar; R2-Katalog entsteht mit R2-01…R2-04 |
| LEARN-10 | Kurs löschen/archivieren, Inhalte importieren, Rollback | R1 (BS-16) | R1-15, R1-27, R1-41 | GM-118…GM-136, IMP-001…IMP-032, REV-026, REV-027, REV-041, REV-042, REC-017 | B/C | Endgültiges Löschen eines Kurses mit Lernständen (Lösch-Hooks, E-48) ohne Test → REV-047 |
| ACCESS-01 | Kostenloser, manueller, administrativer oder Einladungszugang | R1 (BS-12…BS-14) | R1-13 | GM-081, GM-087, GM-094, GM-098, GRANT-002, GRANT-003, RBAC-042…RBAC-045 | B/C | – |
| ACCESS-02 | Kauf-/Voucher-/Ticket-/Mitgliedschaftsgrant mit stabiler Quellreferenz | R1+R3 (Kern BS-12, K-01/K-02; Adapter R3.1/R3.2/R3.4) | R1-13; R3-02, R3-04, R3-07 | GM-088, GM-089, GRANT-004, GRANT-005, GRANT-006, GRANT-024 | C · R3-GATE | Mitgliedschafts-Entitlement ohne Vertrag (Kap. 34.7) |
| ACCESS-03 | Mehrere gültige Grants für denselben Kurs | R1 (BS-12) | R1-13 | GM-085, GM-092, GRANT-010, GRANT-011 | B | – |
| ACCESS-04 | Grant pending → active/failed/expired/revoked | R1 (BS-12) | R1-13; R3-10 | GRANT-001, GRANT-015, GRANT-016, GRANT-020, GRANT-022, GM-091 | C | Pending-Timeout und Eskalation offen (SEAM-006) |
| ACCESS-05 | Ablauf, Verlängerung, Pause, Reaktivierung | R1 (BS-12, BS-13) | R1-13, R1-14, R1-17 | GM-084, GM-086, GM-089, GRANT-009, GRANT-012, GRANT-013, GRANT-014, RBAC-009, RBAC-046 | B/C | Zeitzonen-Darstellung (UTC gespeichert, lokal angezeigt) ohne Test; R0-15 fehlt |
| ACCESS-06 | Rückerstattung nach Kursbeginn oder nach Zertifikat | R1+R3 (Kern mit FakeSource; Woo R3.1) | R1-13, R1-27; R3-03 | GRANT-007, GRANT-027, GRANT-028, RBAC-037, RBAC-065, REV-023, REV-046, GRANT-023 | C · R3-GATE | – |
| ACCESS-07 | Paket/Bündel, mehrere Plätze, Teilwiderruf | R1+R3 (Seats BS-14; Bundle-Teil nach E-25/W-07; Teilrefund R3.1) | R1-13, R1-04; R3-03 | GM-101, SEAT-009, SEAT-010, SEAT-013, SEAT-020 | C · R3-GATE | Relation „Kurs enthält Kurs“ (INV-A-107/144) ohne Test → GM-193 (nach E-25) |
| ACCESS-08 | Gesperrter Kurs, eingeschränkte Lektion, geschützte Downloads; Prüfung bei jedem Zugriff | R1 (BS-15) | R1-14 | GM-015, GM-107…GM-116, MED-001…MED-017, RBAC-012…RBAC-020, RBAC-067…RBAC-075 | C | – |
| ACCESS-09 | Quelle/Partner deaktiviert oder inkompatibel; kein stiller Massenwiderruf | R1+R3 (Kern Z-6; Connector R3) | R1-13, R1-43\*; R3-10 | GRANT-021, RBAC-048, CT-08, CT-09 | C · R3-GATE | Betreiber-Policy für Bestands-Grants (Kap. 35.2) ohne Setting und Test (R0-27) |
| COMMERCE-01 | Woo-Produkt/Variation → Kurs; Zahlung bestätigt → Grant | R3.1 (BS-31) | R3-02, R3-06 | GRANT-004 | R3-GATE | Woo-Katalog fehlt (R3-FIRST-001/004: HPOS/Classic, Blocks/Classic, doppelte Hooks); GRANT-004 prüft nur den Kern |
| COMMERCE-02 | Bestellstatus pending/on-hold/failed/cancelled/completed | R3.1 | R3-02, R3-03 | GRANT-023 | R3-GATE | Nur RF-11/RF-13 abgedeckt; Statuswechsel nach Freischaltung offen (R3-FIRST-001) |
| COMMERCE-03 | Gastbestellung, Käufer ≠ Lernender, Mehrfachmenge | R3.1 | R3-02, R3-13 | ID-001, ID-015, SEAT-020 | R3-GATE | E-30 |
| COMMERCE-04 | Storno, Teil-/Vollrefund, Chargeback, Reversal | R3.1 | R3-03, R3-19 | GRANT-023, GRANT-025, GRANT-029 | R3-GATE | – |
| COMMERCE-05 | Gutschein validate/reserve → grant → commit | R3.2 (BS-32) | R3-04, R3-05 | GRANT-024, GRANT-026 | R3-GATE | – |
| COMMERCE-06 | Gutschein mehrfach, mit Guthaben/Plätzen, abgelaufen, ungültig | R3.2 | R3-04, R3-05 | – | R3-GATE | Kein Test; E-36 („ein Code je Platz“) offen |
| COMMERCE-07 | Woo-Bestellung mit Voucher-Rabatt oder -Zahlung | R3.3 | R3-05, R3-06 | – | R3-GATE | Kein Test (R3-FIRST-105); „Voucher allein“ entfällt (ENTSCHEIDUNGEN §7) |
| COMMERCE-08 | Gratisbestellung, 100 %-Rabatt, erneuter Kauf, Upgrade | R3.1 | R3-02 | – | R3-GATE | Kein Test; Freischaltpolicy offen |
| COMMERCE-09 | Produkt-/Kursmapping nach Verkauf geändert oder gelöscht | R3.1 | R3-02, R3-03 | – | R3-GATE | Kein Test; Zuordnung über `entitlement_revision` im `grant_source_key` (Kap. 35.2) |
| COMMERCE-10 | Partnerausfall zwischen Reservierung, Grant und Commit | R3.2 | R3-04, R3-10, R3-19 | GRANT-020, GRANT-024 | R3-GATE | Kompensationspfad nur teilweise (SEAM-002) |
| SUITEFLOW-01 | Workflow: Enrollment, Lektion, Prüfung, Completion, Zertifikat als Event | R3.4; Event-Kern Free in R1 (E-38, E-119) | R1-28, R1-43\*; R3-07, R3-12, R3-17 | CT-06, CT-07, CT-12 | C · R3-GATE | Outbox-/Event-Kern hat keinen R1-Owner (§2.3) |
| SUITEFLOW-02 | Workflow-Action Enroll/Revoke oder Benachrichtigung | R3.4; v1 ohne schreibende Aktionen (E-39) | R3-17 | RBAC-047, RBAC-048 | R3-GATE | In v1 nicht anwendbar; Schleifenschutz ohne Test |
| SUITEFLOW-03 | Form: kursgebundenes Formular, Aufgabenreferenz | R3.4 (BS-33) | R3-07 | – | R3-GATE | Kein Test; Partner-Registry existiert nicht (E-40) |
| SUITEFLOW-04 | Review: Abschluss → verifiziertes Erlebnis → Einladung | R3.4 | R3-07 | – | R3-GATE | Kein Test (E-41) |
| SUITEFLOW-05 | Pass: Ticketkauf → Grant; Storno → Widerruf | R3.4 | R3-07 | – | R3-GATE | Kein Test; Check-in ist keine Completion (ENTSCHEIDUNGEN §7) |
| SUITEFLOW-06 | Affiliate: Sale/Refund über autoritative Quelle | R3.4; v1 meldet Course nichts (E-37) | R3-07, R3-19 | – | R3-GATE | Kein Test „keine doppelte Provision“ |
| SUITEFLOW-07 | License/Pro: aktiv, grace, abgelaufen, Server offline | R3 (BS-35) | R3-08, R3-15, R3-18 | – | R3-GATE | Kein Test; R0-39 fehlt; Free-Teil siehe OPS-10, CORE-10 |
| SUITEFLOW-08 | Kombinierte Rezepte Kauf → Kurs → Prüfung → Workflow → Review | R3.4 | R3-07 | – | R3-GATE | Kein Test (SUITE-110) |
| SUITEFLOW-09 | Partnerinstallationen erkennen und einzeln aktivieren | R3 (FLOW-204); Erkennung laut R0-11 A-14 schon F1 | R3-16; R1-43\* | CT-08, CT-09 | R3-GATE | Release-Konflikt R0-11 A-14 ↔ FLOW-204 → D12-4 |
| SUITEFLOW-10 | Partner-Upgrade, Downgrade, Deaktivierung, Neuaktivierung | R3 | R3-11, R3-12 | CT-08, CT-12 | R3-GATE | – |
| OPS-01 | WP-Datenexport, Lösch-/Anonymisierungsantrag, Retention | R1 (BS-19) | R1-17, R1-34 | GM-099, GM-103, GM-131, GM-162, GM-172…GM-177, ID-002, ID-009, ID-010, ID-011, ID-013, RBAC-086, RBAC-089 | C | Wiederanwenden von Löschungen nach Restore (E-92) ohne Test → REC-026; Retention-Defaults E-44/E-51 offen |
| OPS-02 | Objektzugriff, REST-Nonce, Webhook-Signatur; IDOR, CSRF, Replay, SSRF, Rate-Limit, Secret-Rotation | R1 (BS-02, BS-03, BS-18) | R1-03, R1-28 | RBAC-040, RBAC-043, RBAC-044, RBAC-079, RBAC-081, RBAC-093, RBAC-096, ID-004, ID-005, GM-073, GM-152, CT-02, CT-11, REC-022 | C | Secret-Rotation ohne Test → REC-027; R0-17 fehlt |
| OPS-03 | Event verspätet, doppelt, out-of-order, verloren | R1+R3 (Kern FakeSource und Outbox; Partner R3) | R1-13, R1-43\*; R3-12 | GRANT-007, GRANT-025, RBAC-093, CT-07, CT-11, REC-016 | C · R3-GATE | Outbox ohne R1-Owner (§2.3) |
| OPS-04 | Parallelität: zwei Zahlungen, Refunds, Einlösungen, Joins, Abgaben | R1 (BS-02) | R1-20, R1-42; R3-05 | GRANT-005, GRANT-017, SEAT-003…SEAT-006, SEAT-019, ID-014, GM-045, GM-053, GM-096, GM-188, NFR-010, NFR-011, REV-030, REV-031, REV-032, IMP-020, GRANT-026 | C | – |
| OPS-05 | Job, Queue, DB, Netzwerk oder Partner offline | R1 (BS-02, BS-20) | R1-17, R1-22, R1-38 | GM-156, GM-157, NFR-009, NFR-013, IMP-022, REC-008, CT-07 | C | Robustheitsfälle RB-01…RB-08 (R0-14 §4.1) ohne Test-ID; Alarmierung ohne Test |
| OPS-06 | Admin-Diagnose, Healthcheck, Fehler-/Supportexport | R1 (BS-20) | R1-17, R1-38 | GM-115, GM-146, GM-147, GM-148, MED-007, MED-008, RBAC-094, REC-022 | C | Redigierter Diagnoseexport ohne Test; R0-13 und R0-34 fehlen |
| OPS-07 | Installation, Update, Migration, Deinstallation, Rollback | R1 (BS-01, BS-02) | R1-01, R1-02, R1-23, R1-39, R1-40 | GM-158, GM-163…GM-171, GM-183…GM-186, REC-001…REC-025, RBAC-095 | C | – |
| OPS-08 | Übersetzungen, Accessibility, Responsive, E-Mail/Benachrichtigung | R1 (BS-17, BS-22) | R1-16, R1-19, R1-21, R1-29 | GM-141, GM-143, NFR-014, NFR-016, MED-025, SEAT-002, SEAT-022 | C | Kein Tastatur-, Screenreader-, RTL- oder Locale-Test (R0-15, R0-26 fehlen); transaktionale Mails ohne Test, GM-159/GM-160 sind Pro-Erinnerung (E-24) → NTF-001…NTF-008 |
| OPS-09 | Große Kataloge, Prüfungsspitzen, Masseneinschreibungen | R1 | R1-22, R1-42 | NFR-001…NFR-009, NFR-018, GM-006, GM-110, GM-142, GM-187, IMP-032 | C | Masseneinschreibung (Bulk-Grant, Gruppe mit 1 000 Plätzen) in keinem Lastprofil → NFR-019 |
| OPS-10 | Standalone-Free ohne Partner und ohne Lizenzserver | R1 (Z-6) | R1-01, R1-18 | CT-09, NFR-016, NFR-017 | B | Kein ausdrücklicher Test „Free ohne Woo/Suite/Pro, keine Verbindung zu Lizenz- oder Partnerservern“ → SA-001 |

### 1.2 Kernabnahmefälle Kap. 35.5 (12)

| Fall-ID | Kurzbeschreibung | Release (R0-04) | R1/R3-Task(s) | Testfall-IDs | Gate | Lücke |
|---|---|---|---|---|---|---|
| CORE-01 | Gast kauft mit E-Mail eines bestehenden Dritten → kein Zugriff, kein Account-Link | R1+R3 (Kern mit FakeSource; Gastkauf R3.1) | R1-13; R3-02 | ID-001 | C · R3-GATE | Woo-Variante erst R3 |
| CORE-02 | Zehn Plätze an verschiedene Lernende; keine Einsicht in fremde Antworten | R1+R3 (Seat BS-14; Kauf R3.1) | R1-13, R1-35; R3-02 | SEAT-001, SEAT-003, SEAT-005, SEAT-017, RBAC-036, RBAC-059 | C | Fixture GRP-1 hat 6 Plätze; Variante mit 10 Plätzen und 10 Konten parametrisieren (SEAT-001) |
| CORE-03 | Derselbe Hook/Callback zehnmal → genau ein Grant | R1+R3 | R1-13, R1-42; R3-02, R3-04 | GRANT-004, GRANT-005, RBAC-093 | C · R3-GATE | Woo-/Voucher-spezifische Doppelhooks erst R3 |
| CORE-04 | Zwei Quellen, eine widerrufen → Kurs bleibt zugänglich | R1 (BS-12) | R1-13 | GRANT-010, GM-085 | B | – |
| CORE-05 | Teilrefund einer von drei Positionen | R3.1 | R3-03 | GRANT-023, SEAT-020 | R3-GATE | – |
| CORE-06 | Refund nach Abschluss und Zertifikat → Zugang weg, Historie und Zertifikat bleiben | R1+R3 | R1-13, R1-35; R3-03 | GRANT-028, REV-046, RBAC-065 | C | – |
| CORE-07 | Refund vor verspätetem Zahlungs-Event → kein Re-Grant | R1+R3 | R1-13; R3-03 | GRANT-007, GRANT-025 | C · R3-GATE | – |
| CORE-08 | Voucher-Reserve ok, Grant/Commit scheitert | R3.2 | R3-04, R3-19 | GRANT-020, GRANT-024 | R3-GATE | „Commit scheitert nach Grant“ nicht ausdrücklich in GRANT-024 |
| CORE-09 | Trainer ruft fremde Lernenden-/Kurs-ID über REST ab | R1 (BS-03) | R1-03 | RBAC-033, RBAC-034, RBAC-087, GM-154 | C | – |
| CORE-10 | Pro-Lizenz läuft ab → Free, Historie, Verifikation bleiben | R1+R3 (Free-Teil R1; Pro R3) | R1-18; R3-15, R3-18 | RBAC-065, CT-09 | C · R3-GATE | Kein direkter Test; R0-39 fehlt; Free-Teil über SA-001 |
| CORE-11 | Zertifikat begründet widerrufen → Verifikation zeigt `revoked` | R1 Free-Basis | R1-35, R1-44\* | RBAC-062, RBAC-063, REV-046 | C | Verifikationsantwort `revoked` ohne eigenen Test → CERT-005 |
| CORE-12 | Connector deaktiviert und reaktiviert → keine Massenwiderrufe, keine Duplikate | R1+R3 | R1-13; R3-10, R3-11 | GRANT-021, RBAC-048, CT-09 | C · R3-GATE | Reconciliation nach Reaktivierung erst R3 |

### 1.3 Ergänzende Akzeptanzfälle (Vorschlag zur Übernahme in die Fachkataloge)

Diese 27 Fälle schließen die Lücken aus §1.1/§1.2. Sie sind **Vorschläge** und noch nicht Teil der Zählung in §5. Die Nummern folgen den bestehenden Katalogen; die neuen Präfixe CERT, NTF und SA gehören in R0-29 bzw. R0-27/R0-24.

| ID | Gegeben / Aktion | Erwartung | Ziel-Task | Schließt Lücke | Übernahme in |
|---|---|---|---|---|---|
| CERT-001 | Kurs erfüllt Abschlussregel; Zertifikat laut Kurseinstellung | genau ein `cc_certificate` (`revision_no = 1`, `issued`), Snapshot mit Kursrevision, Evidenz, `template_version`; Event `certificate.issued` | R1-44\* | LEARN-08 | R0-29 |
| CERT-002 | Ausstellung doppelt ausgelöst (Cron + Abgabe, zwei Prozesse) | 1 Zertifikat (Unique `evidence_rev`), zweiter Aufruf liefert dasselbe | R1-44\*, R1-42 | LEARN-08, R0-09 R-06 | R0-29 |
| CERT-003 | Öffentliche Verifikation `/lernen/nachweis/<token>` | Status, Aussteller, Kurs, Datum; Name nur nach Freigabe (E-97); Rate-Limit; kein Verzeichnis | R1-44\*, R1-14 | LEARN-08, L-16 | R0-29 |
| CERT-004 | Korrektur (neue Evidenz) | `revision_no = 2`, alte Revision `corrected` mit Verweis, alter Token zeigt „ersetzt“ | R1-44\* | LEARN-08 | R0-29 |
| CERT-005 | Widerruf mit Grund durch berechtigte Rolle | Verifikation `revoked` mit Datum, Beleg bleibt lesbar, Audit; ohne Grund abgewiesen | R1-44\*, R1-35 | CORE-11 | R0-29 |
| CERT-006 | Ausstellung, wenn bei Abgabe kein Grant aktiv war (E-113) | kein automatisches Zertifikat, Ergebnis gewertet | R1-44\* | LEARN-08 | R0-29 |
| CERT-007 | HTML- und Druckansicht (E-96) mit Umlauten und RTL-Namen | korrekte Darstellung, kein serverseitiges PDF in Free | R1-44\*, R1-19 | LEARN-08 | R0-29 |
| CERT-008 | Datenschutzexport/-löschung mit Zertifikat | Export enthält Nachweis; Löschung pseudonymisiert nach Retention (E-51), Verifikation zeigt nur Minimaldaten | R1-44\*, R1-17 | LEARN-08, OPS-01 | R0-29 |
| CERT-009 | Kompetenz aus bestandener Prüfung mit `competency_validity_days = 30`; Grant aus Kauf ohne Prüfung | Kompetenz gültig bis T0 + 30 d, danach `expired`; Kauf allein erzeugt keine Kompetenz | R1-10 | LEARN-07 | R0-08 |
| CERT-010 | Lizenzende Pro (Add-on bleibt aktiv) | Basisnachweise und Verifikation unverändert, Designer-Vorlage eingefroren (E-94) | R3-15 | CORE-10 | R0-29 |
| NTF-001 | Einladung, Claim-Bestätigung, Kursabschluss: je ein Mailversand | genau eine Mail je Ereignis (Outbox-Dedupe), Absender von WP geerbt (K-08) | R1-21 | OPS-08 | R0-27 |
| NTF-002 | Zugang abgelaufen (transaktional nach Ablauf, E-24) | eine Mitteilung nach Ablauf, keine Erinnerung davor in Free | R1-21 | OPS-08 | R0-27 |
| NTF-003 | `wp_mail` liefert `false` / SMTP-Ausfall | Fehler protokolliert, Retry mit Backoff, keine Doppelmail nach Erfolg | R1-21, R1-17 | OPS-05, OPS-08 | R0-27 |
| NTF-004 | Opt-out des Lernenden für nicht notwendige Mitteilungen | keine weiteren Mails dieser Klasse; notwendige (Claim) weiter | R1-21, R1-34 | OPS-08 | R0-27 |
| NTF-005 | Rate-Limit Einladungen je Besitzer/Stunde | Überschreitung abgewiesen, keine Mail (vgl. SEAT-022) | R1-21 | OPS-08 | R0-27 |
| NTF-006 | Staging/Haltemodus (E-85, E-86) | Mails im Catcher, nichts extern | R1-21, R1-23 | OPS-08 | R0-27 |
| NTF-007 | Vorlage mit Platzhaltern, Sprache des Empfängers | korrekte Locale, Platzhalter escaped, keine Tokens im Log | R1-21, R1-19 | OPS-08 | R0-27 |
| NTF-008 | Test-Mail-Sink in CI | alle Mails der E2E-Läufe im Sink nachweisbar, 0 externe Zustellung | R1-21, R1-18 | OPS-08 | R0-18 |
| RBAC-097 | Aktivierung auf Site mit `editor` und `shop_manager` | beide ohne `cc_*`-Caps; nur `administrator` (E-83) | R1-03 | ID-04 | R0-07 |
| GM-191 | Lektionsfortschritt: `PUT progress` mit älterer Sequenz nach neuerer; doppelt | Stand nie rückwärts; `completed` nur per `manual_confirm` zurücknehmbar (E-70) | R1-10 | LEARN-03 | R0-05 |
| GM-192 | Prüfungsregel mit `max_attempts = 2`, `cooldown_seconds = 3600`, ein Abbruch | zweiter Start erst nach Sperrfrist, Abbruch zählt (E-71), dritter Start `cc_exam_attempts_exhausted` | R1-08, R1-20 | LEARN-05 | R0-05 |
| GM-193 | Kombikurs enthält Teilkurs; Grant auf Kombikurs | Inhalte und Dateien des Teilkurses freigegeben (nur falls E-25 den Paritätsteil R1 zuordnet) | R1-04, R1-14 | ACCESS-07 | R0-05 |
| REV-047 | Kurs mit Lernständen und Zertifikaten wird endgültig gelöscht | nur mit Bestätigung und Anzahl Betroffener; Versuche/Zertifikate bleiben verifizierbar; Orphan-Check sauber (E-48) | R1-04, R1-27 | LEARN-10 | R0-23 |
| REC-026 | Löschantrag ausgeführt, danach Restore eines älteren Backups | Tombstone wird automatisch erneut angewendet (E-92), Audit | R1-23, R1-17 | OPS-01 | R0-16 |
| REC-027 | Rotation von `COURSE_CAPTAIN_SECRET_KEY` und Webhook-Secret | alte Signaturen nach Frist abgelehnt, gespeicherte Secrets neu verschlüsselt, kein Klartext | R1-23, R1-28 | OPS-02 | R0-16/R0-17 |
| NFR-019 | Masseneinschreibung: 1 000 Grants per CLI/Admin, Gruppe mit 1 000 Plätzen | innerhalb Budget OP-xx, Outbox-Rückstand abgebaut, keine Lock-Timeouts in L1 | R1-22, R1-42 | OPS-09 | R0-14 |
| SA-001 | Frische Site, nur Course Captain Free, ausgehender HTTP-Verkehr blockiert | Flows 1–4 aus R0-11 grün; 0 Requests an Lizenz-, Partner- oder Fremdhosts; keine PHP-Notices zu fehlenden Partnern | R1-18, R1-01 | OPS-10, CORE-10 | R0-18 |

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
%%REVERSE%%

### 2.2 Befunde

1. **Ohne Testabdeckung:** R1-29 (Usability-Abnahme). Das ist ein Abnahmeformat, kein Katalogtest; es fehlt die Vorgabe aus R0-26 (Referenzaufgaben, Teilnehmerzahl, Abbruchkriterien).
2. **Effektiv ohne gültigen Test:** R1-21. Die zwei zugeordneten GM-159/GM-160 prüfen die Ablauf-**Erinnerung** und den Abmeldelink. Beides ist nach E-24 Pro (R3-08/R3-15, BS-30). Korrektur: GM-159/GM-160 auf R3-15 umhängen; R1-21 erhält NTF-001…NTF-008. Eine R0-Spezifikation für Benachrichtigungen gibt es nicht, R0-27 (Settings) deckt nur die Felder ab.
3. **Dünn:** R1-01, R1-19, R1-24, R1-30, R1-32, R1-33, R1-36, R1-37, R1-39 (je 1–2 Tests). Ursache ist jeweils eine fehlende R0-Spezifikation: R0-15/R0-26 (R1-19), R0-13 (R1-24), R0-20 (R1-30), R0-27/R0-30 (R1-32, R1-36), R0-28 (R1-33), R0-33 (R1-37), R0-35 (R1-39). R1-01 ist über OPS-07/SA-001 und REC-018 inhaltlich breiter abgedeckt, als die Ziel-Spalte zeigt. R1-24 trägt nur die Seeder-Tests GM-189/GM-190; die Dokumentation selbst wird per Review abgenommen.
4. **Sammel-Task:** R1-18 hat 9 eigene Tests, nimmt aber alle 505 R1-Tests ab (Gate B/C). Seine DoD wird über §3 gemessen, nicht über die Ziel-Spalte.
5. **Zuordnungsfehler in den Katalogen** (technische Korrektur, keine Entscheidung): GM-159/GM-160 → R3-15 (siehe 2.). GM-189/GM-190 (Starter-Inhalte) zusätzlich R2-03, weil Starttexte nach E-16 erst mit R2-03 kommen. RBAC-061/RBAC-066 (Verifikation) zusätzlich R1-44\*.
6. **Reihenfolgedefekt:** R1-27 („Zertifikate nachvollziehbar“, REV-003/045/046) setzt eine Zertifikatsdomäne voraus, die erst R1-35 abnimmt, und R1-35 hängt von R1-27 ab. Ohne Domäne vor R1-27 ist R1-27 nicht testbar (§2.3, R1-44\*).

### 2.3 Vorgeschlagene zusätzliche R1-Tasks (nur Pflichtverhalten ohne Owner)

| Vorschlag | Titel | Voraussetzung | DoD (Vorschlag) | Begründung (warum kein bestehender Task) |
|---|---|---|---|---|
| **R1-43\*** | Domain-Events, Outbox und Connector-Registry im Free-Kern | R1-02, R1-03 | Fachänderung, Event und Outbox-Eintrag in einer TX (R0-06 §4.8); Event-Envelope v1 und Katalog nach R0-19 §5; Zustellung mit Retry/Backoff/`dead`; Event-Log 30 Tage ohne Abonnenten (E-119); Connector-Registry mit Handshake und Status (R0-19 §5.7, E-38); Haltemodus (E-85/E-86). Tests CT-06…CT-12, GRANT-002 (Outbox-Teil), REC-016, RBAC-047/048/093 grün | E-38 und E-119 legen Events, Read-API und Registry **in Free** fest; GRANT-002, REC-016, CT-07 setzen in R1 eine Outbox voraus; K-06 (Cache-Invalidierung über `grant.changed`) braucht die Domain-Events in R1-25. LB hat keine Outbox (R0-06 §1.1 Nr. 6), es gibt also nichts zu portieren. R3-01 ist laut DoD nur das **Absichern** der öffentlichen Schnittstelle und startet erst nach R2-GATE. R1-28 betrifft REST/Block/Shortcode, R1-17 Wartungsjobs. Kein R1-Task baut die Outbox |
| **R1-44\*** | Basisnachweis-Domäne (Ausstellung, Snapshot, Verifikation, Korrektur, Widerruf) | R1-09, R1-10 | `cc_certificate` nach R0-06 E-39 und R0-08 §7.2; Zustände `issued/corrected/revoked`; Verifikationsseite L-16 mit Minimaldaten (E-97); HTML-/Druckansicht (E-96); Unique je Evidenz. Tests CERT-001…CERT-008, RBAC-060…RBAC-066 grün. R1-27 und R1-35 erhalten R1-44 als Voraussetzung | Kein LB-Quellbestand (R0-04 §3 „Befund ohne Quellbestand“, R0-11 §1.4 „F1 neu, kein Golden Master“). R1-35 ist ein **Abnahme**-Task nach R0-29 und R1-27, R1-27 braucht die Domäne aber schon (Befund 2.2 Nr. 6). Alternative ohne neue ID: R1-35 in R1-35.1 (Domäne, vor R1-27) und R1-35.2 (UI/Abnahme) teilen (TODO-Regel „`<ID>.1`“) → Entscheidung D12-2 |

Weitere Pflichtverhalten ohne Owner wurden **nicht** gefunden. Geprüft wurden insbesondere Lernsperre (R1-03, E-79), Rate-Limiter-Kern (R1-02, K-25), Haltemodus (R1-23/R1-40 laut E-85…E-88), Berichte Basic (R1-10, GM-079, E-98), Kurs-FAQ/Tipps-Mechanik (R1-14/R1-15/R1-28 laut R0-04 BS-24), Voraussetzungen zwischen Lektionen (R1-04/R1-14, E-101, REV-012) und Lernbereich-Router (R1-16, K-07).

---

## 3. Gates: prüfbare Abnahmekriterien

**Allgemeine Regeln.** (a) „grün“ heißt: automatisiert bestanden in der CI-Matrix aus R0-14 §3 bzw. R0-20, mit Protokoll (Commit, Lauf-ID, Datum). (b) Ein Test darf nur als **„nicht anwendbar“** zählen, wenn eine Entscheidung ihn ausschließt (Beispiel: ID-015 bei E-30 = Kontopflicht). Das wird mit E-Nummer in der Ergebnisliste vermerkt. (c) Eine **Abweichung** vom Golden Master gilt nur mit schriftlicher Freigabe nach E-55 und Eintrag in der Abweichungsliste von R0-05. (d) Gates werden mit Datum, Reviewer und Auftraggeber im TODO protokolliert (D12-3). (e) Prozentwerte beziehen sich auf die Pflichtmenge der jeweiligen Zeile.

### 3.1 Gate A = R0-GATE (Portierbarkeit, Spezifikation)

| Nr. | Kriterium | Prüfung / Nachweis | Soll |
|---|---|---|---|
| A-1 | Inventur vollständig | R0-02/R0-03: 835 Einträge, `unknown` = 0; R0-04-Prüfskript `fehlend [] · doppelt []` | 100 % |
| A-2 | R1-Grenze eingefroren | R0-04 auf „beschlossen“; alle 25 Kopplungen K-01…K-25 mit Entkopplungsvertrag; Widersprüche W-01…W-12 entschieden | 100 % |
| A-3 | Entscheidungen beantwortet | E-01…E-120 und die neuen Punkte dieses Dokuments (§6) haben Status „beschlossen“ mit Datum. **E-01** (Codefreigabe) ist Pflicht vor jeder Wiederverwendung | 100 %; sonst Gate rot |
| A-4 | R0-Dokumente freigegeben | Status „beschlossen“ mit Reviewer: R0-04…R0-11, R0-12 (dieses), R0-14, R0-16, R0-19, R0-23, R0-32, R0-36. **Noch zu schreiben und freizugeben:** R0-13, R0-15, R0-17, R0-18, R0-20, R0-21, R0-22, R0-24…R0-31, R0-33…R0-35, R0-37…R0-40 | 40/40 Tasks R0-01…R0-40 abgehakt |
| A-5 | Testkataloge vollständig | Jeder der 511 Katalogtests hat Fixture, Erwartung und Ziel-Task (§5: `ohne Ziel = 0`); die 27 Vorschläge aus §1.3 sind übernommen oder begründet verworfen; Kataloge aus R0-15, R0-21, R0-22, R0-26, R0-27/R0-28, R0-33, R0-37, R0-38 liegen vor | 100 % |
| A-6 | Traceability ohne Lücke | §5-Skript: `R1-Fälle ohne Task = 0`, `ohne Test = 0`; jede Zeile mit „Lücke“ hat einen Vorschlag oder eine E-Referenz; R0-24 übernimmt die Matrix als Pflichtvorlage | 0 offene Pflichtlücken |
| A-7 | Ausführbare Referenztests | `cc-fixture-neutral-v1` gebaut (E-02); Referenzlauf für die Rechenlogik-GM (GM-029…GM-031, GM-065…GM-068, GM-084, GM-131) nach E-56 protokolliert; Abweichungsliste (E-55) angelegt | 100 % der genannten GM |
| A-8 | ADRs akzeptiert | ADR-001…ADR-008 (Konzept 28.2) in R0-06/R0-08/R0-19 bzw. R0-07 als „beschlossen“ | 8/8 |
| A-9 | Datenschutz/Sicherheit/Betrieb | R0-17 (Threat Model, DPIA-Bedarf), R0-16, R0-31 freigegeben; Retention E-44/E-51 bestätigt | 100 % |
| A-10 | R3-Vorbereitung | FLOW-001/002 für alle 55 Fälle: Owner, Zustände, Fehlerpfad und Testskizze dokumentiert; ausführbare COMMERCE-/SUITEFLOW-Kataloge erst vor R3-01 (Vorschlag D12-5) | 55/55 skizziert |

### 3.2 Gate B (Free-Lernfluss, Teil von R1-GATE)

| Nr. | Kriterium | Pflichtmenge | Soll |
|---|---|---|---|
| B-1 | Golden Master Lernkern | GM-001…GM-093, GM-137…GM-144 (PAR-01…PAR-08, PAR-10) | 100 % grün oder freigegebene Abweichung |
| B-2 | Revisionen laufender Kurse | REV-001…REV-046 | 100 % |
| B-3 | Referenz-E2E | R0-11 Flow 1 (Autor erstellt und veröffentlicht), Flow 2 (Lernender lernt, besteht, erhält Nachweis), Flow 3 (Gruppenverwalter lädt ein) in Chromium, Firefox, WebKit, Desktop und Mobil (NFR-016); Praxis-Benchmark 10 Lektionen / 50 Fragen / 1 Prüfung (Konzept 27.4, GM-187) | 3/3 Flows, alle Zielzeilen |
| B-4 | Kurs ohne Import | GM-137 und Release-Gate 23.2 Nr. 2 | grün |
| B-5 | Standalone | SA-001, CT-09, NFR-017; CI-Lauf ohne Woo/Suite/Pro (K-24) | grün |
| B-6 | Prozessfälle mit Gate B | alle Fälle aus §1 mit Gate „B“ bzw. „B/C“ | 100 % ihrer Tests grün |
| B-7 | Zertifikatsbasis | CERT-001…CERT-008 (nach Übernahme) | 100 % |

### 3.3 Gate C (Betriebsreife, Teil von R1-GATE)

| Nr. | Kriterium | Pflichtmenge | Soll |
|---|---|---|---|
| C-1 | Rechte/IDOR | RBAC-001…RBAC-096 (+RBAC-097), CT-02, CT-10 | 100 %; RBAC-096 Körpergleichheit ohne Ausnahme |
| C-2 | Grants, Seats, Identität (R1-Teil) | GRANT-001…GRANT-022, GRANT-027, GRANT-028; SEAT-001…SEAT-019, SEAT-021, SEAT-022; ID-001…ID-014 | 100 % (R3-Teil GRANT-023…026, GRANT-029, SEAT-020, ID-015 ausgenommen) |
| C-3 | Import und Medien | IMP-001…IMP-032, MED-001…MED-026, GM-118…GM-136 | 100 % |
| C-4 | Recovery | REC-001…REC-025 (+REC-026, REC-027); REC-024 nur „bedingt“ nach E-49 | 100 % |
| C-5 | Nichtfunktionale Ziele | NFR-001…NFR-018 (+NFR-019) auf R-M bzw. R-S; nach E-60 sind Zeitbudgets Gate | 100 %; Messprotokoll nach NFR-018 |
| C-6 | API-Verträge | CT-01…CT-12, GM-145…GM-155 | 100 %; Routen-Snapshot ohne undeklarierte Änderung |
| C-7 | Betrieb, Migration, Lifecycle | GM-156…GM-186 | 100 % |
| C-8 | Accessibility, i18n | Katalog aus R0-15/R0-26: 0 kritische und 0 schwere Befunde (WCAG-Ziel laut R0-15); R1-29-Protokoll mit Befundliste und Behebung | 100 % der kritischen Fälle |
| C-9 | Benachrichtigungen | NTF-001…NTF-008 | 100 % |
| C-10 | Sicherheitsprüfung | SAST/Dependency-Scan nach R0-18 ohne offene Befunde „hoch“; Threat-Model-Maßnahmen aus R0-17 umgesetzt | 0 hoch |
| C-11 | Kompatibilität | Matrix R0-20 grün (WP/PHP/DB laut E-57, E-58; Browser E-61); Konflikttests R1-30 dokumentiert | 100 % Zielzeilen |
| C-12 | Distribution | R1-39: Free-ZIP Clean-Install, Upgrade (REC-023), WordPress.org-Readme, Deaktivierung | grün |
| C-13 | Release-Gates Konzept 23.2 | Nr. 1–3 und 6–9 nachgewiesen; Nr. 4 und 5 (Pro, Suite) sind R3 | 7/7 |

**R1-GATE** = Gate B **und** Gate C, alle 42 (bzw. 44) R1-Tasks abgehakt, keine offenen Fehler der Schwere „kritisch/hoch“, Freigabe protokolliert.

### 3.4 Gate D (Erweiterungen: R2-GATE, R3-GATE, R4 je Feature)

| Nr. | Kriterium | Soll |
|---|---|---|
| D-1 | Regression: die R1-Pflichtmenge aus §3.2/§3.3 bleibt grün | 100 % |
| D-2 | Jedes R2-Feature (R2-01…R2-04) hat eigenen Katalog (positiv, negativ, Rechte, Export/Eraser, Migration) und erfüllt R2-06 | 100 % je Feature |
| D-3 | R3.1/R3.2: R3-Teil der Kataloge (GRANT-023…026, GRANT-029, SEAT-020, ID-015) und neue Kataloge für COMMERCE-01…COMMERCE-10 (R3-FIRST-001…004, CORE-SPEC-009) | 100 % |
| D-4 | R3.3: Woo allein, Woo + Voucher, beide deaktiviert (ENTSCHEIDUNGEN §7, R3-FIRST-105); CORE-01…CORE-12 vollständig mit Adaptern | 100 % |
| D-5 | R3.4: je Connector SUITEFLOW-Fall mit Contract-Tests (CT-06…CT-12) und Freigabe der Event-Klassen (E-120) | 100 % je Connector |
| D-6 | Pro: Lizenzende, Grace, Server offline (SUITEFLOW-07, CORE-10, CERT-010); Core-ZIP ohne Lizenzcode (R3-18) | 100 % |
| D-7 | Einzelfreigabe nach Nutzen, Abhängigkeiten, Aufwand (Konzept 28.6) dokumentiert | je Feature |

---

## 4. Risikoliste (Top 15)

W = Wahrscheinlichkeit, A = Auswirkung (h = hoch, m = mittel, n = niedrig).

| Nr. | Risiko | W | A | Gegenmaßnahme | Task |
|---|---|---|---|---|---|
| RK-01 | Keine Codefreigabe (E-01) → Portierung wird Neuimplementierung, Golden Master ohne Referenzlauf | m | h | E-01 als erste Entscheidung; bis dahin nur Verhaltensspezifikation, keine Codekopie | R0-GATE, R1-18 |
| RK-02 | 120 offene Entscheidungen und 22 fehlende R0-Dokumente verzögern Gate A | h | h | Sammelbeschluss „Empfehlungen annehmen außer …“; fehlende R0-Tasks nach Abhängigkeit abarbeiten (R0-13, R0-15, R0-17 zuerst) | R0-GATE |
| RK-03 | Golden Master falsch aus Testcode abgeleitet (Smarttrainer-Reihenfolge, Kulanz, Intervalle) | m | h | Referenzlauf nach E-56 für Rechenlogik; Abweichungsliste nach E-55 | R1-12, R1-18 |
| RK-04 | Outbox/Event-Kern ohne Owner → Doppelmails, fehlende Cache-Invalidierung, R3 baut nachträglich um | h | h | R1-43\* anlegen; CT-06…CT-12 in R1-CI | R1-43\*, R1-25 |
| RK-05 | Zertifikatsdomäne neu ohne Vorbild, falsche Task-Reihenfolge | h | m | R1-44\* bzw. R1-35.1 vor R1-27; R0-29 mit CERT-Katalog | R1-44\*, R1-35 |
| RK-06 | Races bei Seats, Grants, Versuchen (GET_LOCK NULL auf Shared Hosting, Deadlocks) | m | h | Lock-Reihenfolge R0-09 §6.1, Uniques als letzte Linie, L5-Runner auf MariaDB und MySQL (NFR-010…012) | R1-42 |
| RK-07 | Geschützte Medien unter nginx öffentlich (`.htaccess` wirkungslos) | h | h | Selbsttest und Health `exposed` (MED-007, GM-115), Regeltext, privater Ordner außerhalb Webroot | R1-14, R1-38 |
| RK-08 | Staging-Kopie für CPT-Lektionen (E-104) erzeugt Leaks in REST, Suche, Sitemap oder Slug-Konflikte | m | h | REV-039…REV-044 früh als Prototyp; R0-33 für SEO-Grenzen | R1-27, R1-37 |
| RK-09 | Accessibility und i18n ungeprüft (keine Tests vorhanden, R0-15/R0-26 fehlen) | h | m | R0-15/R0-26 vor Gate A; C-8 als harte Gate-Bedingung | R1-19, R1-29 |
| RK-10 | Performance auf Shared Hosting verfehlt (Budgets ungemessen) | m | m | früher Messlauf L1/L2 auf R-S; Kapazitätsklasse nach E-59 dokumentieren | R1-22 |
| RK-11 | Unterbrochene Migration verliert Lernstände oder führt Schritte doppelt aus | n | h | Lock + `cc_migrations_applied`, Backfill-Cursor, REC-006…REC-008, Backup-Pflicht vor Upgrade | R1-40, R1-23 |
| RK-12 | Gelöschte personenbezogene Daten kehren nach Restore zurück | m | h | Tombstones nach E-92, REC-026 | R1-17, R1-23 |
| RK-13 | Woo-Kopplungen (K-01…K-25) sickern in den Free-Kern; R3 blockiert R1 | m | h | CI ohne Woo (K-24), FakeSource (K-02), Codereview-Regel „kein `wc_`/`order_` im Kern“ | R1-13, R1-18 |
| RK-14 | Editionswidersprüche (E-53 Pro-Connector vs. Kap. 43.3, E-24 vs. R1-21-DoD, Partnererkennung F1 vs. R3) führen zu Nacharbeit | h | m | Korrekturen aus §2.2 Nr. 2/5 einarbeiten; D12-4 entscheiden | R1-21, R1-38, R3-16 |
| RK-15 | Testvolumen (> 530 Fälle, Paralleltests) macht CI langsam und instabil; R3-Kataloge fehlen noch | m | m | Stufen-CI (Unit/Integration/E2E/Last getrennt, R0-18), feste Seeds, Wiederholregel nur für L5; R3-Kataloge als Eingangsbedingung von R3-01 (D12-5) | R0-18, R1-18, R3-01 |

---

## 5. Nachweis der Zählungen (Skript)

Kommando (aus dem Repository-Wurzelverzeichnis, Python 3, nur lesend):

```bash
python3 - <<'PY'
import re, collections
D = 'docs/r0/'
PFX = r'(GM|RBAC|GRANT|SEAT|ID|IMP|MED|NFR|REC|REV)-\d{3}'
CAT = ['R0-05-golden-master.md', 'R0-07-rbac-idor-matrix.md', 'R0-09-identity-seat-grant-automaten.md',
       'R0-10-import-medien-sicherheit.md', 'R0-14-nichtfunktionale-ziele.md',
       'R0-16-backup-recovery-deployment.md', 'R0-23-revisionen-laufender-kurse.md',
       'R0-19-api-erweiterungsvertraege.md']
def cells(l): return [c.strip().strip('*').strip() for c in l.strip().strip('|').split('|')]
tests = {}                                   # Test-ID -> Ziel-Tasks
for f in CAT:
    hdr = None
    for l in open(D + f, encoding='utf-8'):
        if not l.startswith('|'): hdr = None; continue
        c = cells(l)
        if hdr is None: hdr = c; continue
        if set(''.join(c)) <= set('-: '): continue
        ic = next((i for i, h in enumerate(hdr) if h in ('GM-ID', 'Test-ID', 'ID', 'Anker')), None)
        if ic is None or ic >= len(c): continue
        t = c[ic]
        if t.startswith('CT-'): tests[t] = {'R1-28', 'R3-12'}; continue   # R0-19 §9: Anker für R1-28/R3-12
        if not re.fullmatch(PFX, t): continue
        zc = next((i for i, h in enumerate(hdr) if h.startswith('Ziel')), None)
        tests[t] = set(re.findall(r'R[0-4]-\d{2}', c[zc] if zc is not None and zc < len(c) else ''))
def expand(s):                               # "GM-118…GM-136" -> Einzel-IDs
    out = []
    for a, b in re.findall(r'([A-Z]+-\d{3})(?:…([A-Z]+-\d{3}))?', s):
        if b and a.split('-')[0] == b.split('-')[0]:
            p = a.split('-')[0]; out += [f'{p}-{n:03d}' for n in range(int(a[-3:]), int(b[-3:]) + 1)]
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
%%RESULT%%
```

**Grenzen der Zählung:** Das Skript liest die Ziel-Spalten der Kataloge, nicht deren fachliche Richtigkeit. Nebenziele in Klammern (z. B. REV „R1-27 (R1-10)“) zählen mit. Die 27 Vorschläge aus §1.3 sind nicht enthalten, bis sie in die Fachkataloge übernommen sind. CT-01…CT-12 tragen in R0-19 keine Ziel-Spalte; das Skript ordnet sie nach R0-19 §9 R1-28 und R3-12 zu.

---

## 6. Entscheidungsbedarf (neu, nicht in `ENTSCHEIDUNGEN.md`)

Bereits registriert und hier nur als Abhängigkeit genutzt: E-01, E-24, E-25, E-30, E-36, E-38, E-44, E-51, E-55, E-56, E-60, E-83, E-92, E-96, E-97, E-113, E-114, E-119. Technische Korrekturen ohne Auftraggeber-Entscheidung stehen in §2.2 Nr. 2 und Nr. 5.

| ID | Frage | Optionen | Empfehlung | Blockiert |
|---|---|---|---|---|
| **D12-1** | Neuer Task **R1-43** „Domain-Events, Outbox und Connector-Registry im Free-Kern“ in die R1-Liste aufnehmen? | a) ja, eigener Task vor R1-13/R1-25 · b) als Untertask R1-28.1 · c) nein, Outbox erst mit R3-01 | **a)**: E-38/E-119 sagen Free; R1-Tests setzen die Outbox voraus (§2.3) | R1-13, R1-21, R1-25, R3-01 |
| **D12-2** | Zertifikatsdomäne: neuer Task **R1-44** oder Teilung von R1-35? | a) R1-44 (vor R1-27), R1-35 bleibt UI/Abnahme · b) R1-35.1 Domäne / R1-35.2 Abnahme, Voraussetzungen anpassen · c) unverändert | **a)** oder b); c) lässt R1-27 untestbar | R1-27, R1-35, R0-29 |
| **D12-3** | Gate-Schwelle und Freigabeinstanz: Müssen alle R1-Pflichttests zu **100 %** grün sein, und wer zeichnet Gate A–D ab? | a) 100 %, Ausnahmen nur „nicht anwendbar“ per E-Nr. oder E-55-Abweichung; Abzeichnung Auftraggeber + technischer Reviewer · b) ≥ 95 % mit Fehlerliste · c) 100 % nur für Sicherheit/Datenintegrität (RBAC, GRANT, SEAT, ID, REC, IMP, MED), sonst ≥ 95 % | **a)**: Paritätsprinzip Kap. 38 („100 % der fachneutralen Paritätsmatrix“) | R0-GATE, R1-GATE |
| **D12-4** | Partnererkennung und Integrationsseite (R0-11 A-14 „F1“) in R1 oder erst R3-16/FLOW-204? | a) R1 nur lesend (erkannt/inaktiv/inkompatibel), kein Opt-in, kein Datenfluss · b) komplett R3 · c) komplett R1 | **a)**, gekoppelt an R1-43\*; Opt-in und Datenfreigabe bleiben R3 (FLOW-204, E-120) | R1-38, R1-43\*, R3-16 |
| **D12-5** | Müssen die ausführbaren Testkataloge für COMMERCE-01…10 und SUITEFLOW-01…10 schon für **Gate A** vorliegen? (FLOW-001/002 und R3-FIRST-001…004 stehen im R0-Abschnitt) | a) ja, vollständig vor R0-GATE · b) für Gate A nur Owner/Zustände/Testskizze (A-10), ausführbare Kataloge als Eingangsbedingung von R3-01 nach Code-Gegenprüfung (CORE-SPEC-009) · c) erst in R3 | **b)**: Partner-APIs müssen ohnehin vor R3 neu geprüft werden (Kap. 34.7), sonst veraltet der Katalog | R0-GATE, R3-01 |
| **D12-6** | Wo wird der Benachrichtigungsvertrag (Ereignisse, Vorlagen, Opt-out-Klassen, Rate-Limits) für R1-21 spezifiziert? Es gibt keine R0-Task dafür | a) in R0-27 (Settings-Register) als eigenes Kapitel mit NTF-Katalog · b) neue R0-Task · c) erst in R1-21 | **a)**: keine neue ID; R0-27 ist Voraussetzung von R1-31/R1-34 | R1-21, R1-34 |

---

## 7. Deltas zu anderen Dokumenten (mit deren Freigabe einzuarbeiten)

| Dokument | Delta |
|---|---|
| TODO | R1-43\*/R1-44\* nach D12-1/D12-2; Voraussetzung R1-27 und R1-35 um R1-44 ergänzen; R1-21-DoD „Ablauf“ = Mitteilung nach Ablauf (E-24) |
| R0-05 | GM-159/GM-160 Ziel → R3-15; GM-189/GM-190 zusätzlich R2-03; GM-191…GM-193 aufnehmen |
| R0-07 | RBAC-097 aufnehmen; RBAC-061/RBAC-066 zusätzlich R1-44\* |
| R0-14 | NFR-019 (Masseneinschreibung) und Test-IDs für RB-01…RB-08 |
| R0-16 | REC-026 (Tombstones, E-92), REC-027 (Secret-Rotation) |
| R0-23 | REV-047 (endgültiges Löschen) |
| R0-24 | Tabelle §1 als Pflichtvorlage; Spalten für FLOW-002-Dimensionen (Ablehnung, Duplicate, Parallelität, Timeout, Partnerausfall, Retry, Widerruf, Recovery) je Fall ergänzen |
| R0-27 | NTF-Katalog (D12-6); Setting „Bestands-Grants bei Connector-Deaktivierung“ (ACCESS-09) |
| R0-29 | CERT-001…CERT-010 |
| R0-18 | SA-001 und NTF-008 als CI-Pflichtjobs; Stufen-CI (RK-15) |

---

## 8. Nachweis

- Gelesen: `TODO-WETTBEWERBSANALYSE.md` vollständig; `konzept.md` Kap. 23, 28.6, 34, 35, 38, 39; `docs/r0/ENTSCHEIDUNGEN.md` (E-01…E-120); R0-04 §1–§5; R0-05 §1, §4–§6; R0-06 (Zertifikat- und Outbox-Entitäten); R0-07 §5, §7; R0-08 §4.4, §8; R0-09 §5, §6, §8; R0-10 §6–§7; R0-11 §1.4, §2.2, §2.3, §5; R0-14 §2.2, §4, §5; R0-16 §4, §6; R0-19 §7–§9; R0-23 §7–§8.
- Zählungen: Skript in §5, Ergebnis dort wörtlich. Die Zuordnung Fall → Test in §1 ist fachlich hergeleitet und muss im Review gegengeprüft werden. Das Skript prüft nur, dass die IDs existieren und jeder R1-Fall einen Task und einen Test hat.
- Nicht erfolgt: keine Änderung an anderen Dateien, keine Implementierung, kein Testlauf, kein Commit.
