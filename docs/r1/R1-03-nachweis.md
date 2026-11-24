# R1-03 · Capabilities und zentraler Policy-Service – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-03):** Alle Server-Einstiege nutzen Objekt-/Site-Rechte; negative IDOR-Tests grün.
**DoR (R0-24 §4.2/4.3):** `CourseCaptain\Policy` (Caps, Meta-Caps, PolicyService); Tests RBAC-001…RBAC-104, SEC-003/004; Kommando `composer test -- --group=R1-03`.
**Quellen:** `docs/r0/R0-07-rbac-idor-matrix.md` (§1–§5, §7), Beschlüsse E-78…E-84, E-27/E-286/E-287, E-25a/E-297, E-168/E-288, E-38/E-291. Clean Room (E-01): kein LB-Quelltext.

**Stand der Einstiege:** Es gibt noch keine REST-Routen, admin-post-Aktionen, Shortcodes oder Download-Endpunkte. R1-03 liefert deshalb den **einen** Entscheidungsdienst samt WP-Fassade (`map_meta_cap`), den alle späteren Einstiege benutzen müssen (P-03). Die RBAC-Fälle sind auf Dienstebene geprüft; die HTTP-Varianten (Statuscode, Körper, Nonce) entstehen mit den jeweiligen Einstiegen (Übergabe §5).

## 1. Dateien

| Datei | Inhalt | Quelle |
|---|---|---|
| `src/Policy/Roles.php` | 4 Rollen `ccap_course_manager`, `ccap_author`, `ccap_trainer`, `ccap_support` mit Default-Caps nach Tabelle 3.1; Anlage bei Aktivierung (ergänzt nur fehlende Caps), Entfernung nur bei bestätigter Deinstallation | R0-07 §2, §3.1, P-10, E-83 (schließt R1-01 A-14) |
| `src/Policy/Decision.php` | `allow(caps)` / `deny(code, reason)`; Codes `ccap_not_found` 404, `ccap_forbidden` 403, `ccap_content_locked` 403 + Grund, `ccap_account_locked` 403, `ccap_auth_required` 401; Audit-Kennzeichen | P-02, P-08, §3.6 |
| `src/Policy/Actor.php` | Nutzer (ID nur aus der Sitzung, P-09), `system:cron`, `system:cli`, `connector:<id>` | P-09, P-12 |
| `src/Policy/Relations.php` | B-1…B-9: Autor/Staff (`ccap_course_staff` inkl. Gruppen-Scope), Trainer-Abdeckung über aktive Plätze, Kurszuordnung von Modul/Lektion/FAQ/Tipp/Regel/Variante/Blueprint, Fragenpools (inkl. Inline-Fragen), effektiver Zugang (Grant aktiv **jetzt**, unabhängig vom Ablauf-Cron; Modul-Grants; Scopes; Bundles eine Ebene; abhängige Zusatz-Grants; Free-Regel), Lernsperre, Medienbezug, Claim-Token (nur SHA-256) | §3.3, E-27, E-286, E-287, E-297, E-79, E-112 |
| `src/Policy/PolicyService.php` | `decide(actor, action, args, context)` für **M-01…M-24 und M-10a**, Request-Memo, deny by default, strukturelle Verbote als `do_not_allow` (Selbstbewertung, eigenes Zertifikat, Selbstsperre, höhere Rechte), Pflichtbegründung + Audit beim Lesen fremder Versuchsdaten (E-82) | §3.2, §3.4, P-01…P-08 |
| `src/Policy/MetaCaps.php` | `map_meta_cap`-Fassade für alle Meta-Caps; `edit_post`/`delete_post`/`read_post`/`publish_post` der eigenen CPTs über M-03/M-05/M-06 (Staff-Zuweisungen zählen); `post_type_capabilities()` für R1-04 | §3.2 CPT-Mapping |
| `src/Policy/Invalidation.php` | Memo-Flush bei Rollen-/Profiländerungen, `switch_blog`, `set_current_user`, Änderungen der Rollen-Option und `course_captain_policy_changed` | P-11, §3.5 Nr. 1 |
| `src/Policy/EscalationGuard.php` | `editable_roles` nur einschränkend: keine Rolle mit Rechten, die der Vergebende nicht hat; `administrator`/Kursmanager nur mit `promote_users` | §3.5, E-84 |
| `src/Policy/SystemPermissions.php` | feste Erlaubnisliste je Systemakteur, immer mit Audit; Connectoren in Free ohne Rechte (`feature_unavailable`) | P-12, E-38, E-291 |
| `src/Storage/Schema.php` | `ccap_course_staff.group_id` (0 = ganzer Kurs) + UNIQUE `(course_id, user_id, role, group_id)` | R0-07 B-2/B-3 |
| `src/Kernel/Activator.php`, `Plugin.php`, `UninstallManifest.php` | Rollen bei Aktivierung; Filter registriert; Manifest-Rollen = `Roles::ALL` | P-10 |
| `phpcs.xml.dist` | 24 eigene Caps als `custom_capabilities` bekannt gemacht | J-01 |
| `tests/unit/PolicyUnitTest.php`, `tests/integration/PolicyFixtures.php`, `tests/integration/PolicyTest.php` | Fixtures nach R0-07 §5.1 (U-ADM, RF-AU2, U-TUT, RF-TRB, RF-MGR, RF-SUP, U-L1…L3, U-X, RF-BLK, RF-NOROLE, U-GO, C-A…C-D, Module, Lektion, geteilte Frage, GRP-1, Versuche, Zertifikat, geschützte Dateien, Import-Batch) | §5.1 |

## 2. Kommandos und Ergebnisse (16.11.2026)

| Kommando | Ergebnis |
|---|---|
| `vendor/bin/phpcs` (inkl. Bootstrap-Compat) | 0 Fehler, 0 Warnungen |
| `vendor/bin/phpstan analyse` | Stufe 8: No errors |
| `composer test` | **OK (73 Tests, 2 479 Assertions)** |
| `composer test:integration` | **OK (47 Tests, 639 Assertions)**, mehrfach mit wechselndem Seed |
| `composer test:integration-ms` | **OK (56 Tests, 692 Assertions)** |
| `composer test:cli-ms002` | MS-002: OK |
| Free-Guard / Textdomain-Lint | 39 Dateien 0 Treffer / 37 Dateien 0 Probleme |
| `.pot` | neu erzeugt |

## 3. Abgedeckte Testfälle (Dienstebene)

RBAC-001, 002, 003, 004, 005, 006, 007, 008, 009, 012, 020, 024, 025, 026, 029, 030, 032, 033, 034, 035, 036, 037, 038, 042, 045, 047, 049, 050, 051, 053, 054, 055, 056, 057, 058, 060, 062, 063, 064, 065, 066, 067, 068, 072, 074, 077, 080, 083, 085, 086, 089, 090, 091, 092, 095, 096, 097, 098, 099, 101, 102, 103 – **62 Fälle**, je Test mit `@group RBAC-xxx`. Zusätzlich: Staff-Autor und Kurselemente (M-06), Bundle-Zugang (E-25a), Pflichtbegründung fremder Versuchsdaten (E-82), Systemakteure.

RBAC-096 (kein Existenz-Orakel) ist auf Dienstebene als **Gleichheit der Entscheidungen** für „fremd“ und „nicht existent“ geprüft (Versuch, Nutzung, Datei, Kurs); die Byte-Gleichheit der HTTP-Körper folgt mit den Routen.

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung | Begründung |
|---|---|---|---|
| A-1 | **Kursmanager vergibt `ccap_support`** (§3.5 Nr. 3) | **nicht erlaubt**, solange Support `ccap_view_audit_log` (#21) hat, das dem Kursmanager fehlt | widerspricht der Regel „nie Caps über dem eigenen Satz“ im selben Absatz; die strengere Regel gilt. **Klärung durch Auftraggeber**: entweder #21 für Kursmanager oder Support ohne #21 **Entschieden 24.11.2026: nur Administratoren vergeben die Support-Rolle (E-363).** |
| A-2 | Site-weiter Lernenden-Scope (M-11) | `ccap_view_learner_details` **und** (`ccap_manage_grants` oder `ccap_lock_learners`) = Kursmanager/Support/Admin; Trainer nur über Zuweisung | R0-07 unterscheidet Manager/Support von Trainern, beide Rollen haben aber #13 |
| A-3 | „Versuch in Bewertung“ (M-09) | Status `submitted` oder `evaluated` | R0-07 nennt keinen Status |
| A-4 | Zuordnung FAQ/Tipp | `post_parent` = Kurs (Lektion: `post_parent` = Modul) | R0-06 §3.1 legt nur die Lektion fest; R1-04 bestätigt |
| A-5 | Spaltenname Staff-Rolle | `role` (R0-06 §4.10) statt `staff_role` (R0-07 B-2) | Datenmodell-ADR ist führend |
| A-6 | M-17 Claim | Token-, Gruppen-, Frist-, Platz- und Adressprüfung in der Policy; das Belegen des Platzes (Lock, TX) ist R1-13 | Trennung Recht ↔ Zustandsübergang |
| A-7 | M-22 Export | Exportdatensatz wird im Kontext übergeben | Exporttabelle entsteht mit R1-15/R1-17 |
| A-8 | M-20 Prüfungsmedien | noch keine Sonderregel „nur Inhaber des laufenden Versuchs“ | Teil von R1-14 (R0-10 §4.3) |
| A-9 | Teilumfang/Drip | M-02 wertet nur `access_type = free`; Teilumfang/Drip im Content-Gate | R1-14 (RBAC-013/014) |
| A-10 | Systemakteure | Erlaubnisliste ist ein Startsatz; jede Job-/Kommando-Task ergänzt ihren Eintrag | P-12 |

## 5. Übergaben (HTTP-Varianten und Restfälle)

| Fälle | Ziel-Task |
|---|---|
| RBAC-011 (Autorwechsel per Core-REST), RBAC-039/040/043/044 (Nonce/CSRF), RBAC-041 (`user_id` im Payload) | R1-04/R1-05 und die jeweiligen Routen (R1-28) |
| RBAC-013…019, 069…071, 073, 075, 076 | R1-14 |
| RBAC-021…023, 027, 028 | R1-06/R1-11 (Frage-Token, Favoriten, Sammlungen) |
| RBAC-031 | R1-08 |
| RBAC-046, 052, 059 | R1-13 |
| RBAC-061 | R1-44 |
| RBAC-079, 081, 082, 084, 087, 088 | R1-15/R1-17 |
| RBAC-094 | R1-38 |
| RBAC-100 | R1-46 |
| RBAC-104 | R1-45 |
| RBAC-010, RBAC-078 (zweite Site) | Multisite-Variante mit den ersten Routen |
| RBAC-048, RBAC-093 | R3 (Connector-Registry, E-38) |
| Audit-Einträge schreiben (Decision `audit = true`) | Audit-Dienst (R1-17) |
| Review durch zweite Person vor Merge (AG-05) | Auftraggeber |
