# R1-35 · Gruppen- und Basiszertifikatsverwaltung Free – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-e`
**DoD (TODO R1-35):** Seat/Claim-UI, Basisnachweis, Widerruf/Verifikation, Lizenzunabhängigkeit und E2E-Tests grün.
**Quellen:** R0-29 §1.1 (GR-1…GR-7), §1.2 (GF-01…GF-16), §1.4 (Kennzahlen), §2.1 (CR-1…CR-7), §2.2 (CF-01…CF-14), §2.4 Regel 3, §4.1 (GRP-001…025), §4.4 (CRT-017…027, CRT-063); R0-30 (Admin-Konventionen: WP-native Seiten, admin-post ohne JavaScript, Nonce je Aktion, Zustände als Text); R0-09 §3.3/§4; E-17, E-28, E-81, E-112, E-115, E-220, E-222; Vorarbeiten R1-13 (Gruppendienst), R1-21 (Einladungsmail, Budget, erneut senden), R1-44 (Zertifikatsdomäne). Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Admin/GroupsScreen.php` (neu) | A-10 „Groups“ (#11 `ccap_manage_groups`): Liste mit Status, Besitzer, Belegung/Plätze, offenen Einladungen, freigegebenen Plätzen (§1.4), Laufzeit; Anlegen (GF-02: Besitzer per Name/E-Mail/ID, Kurse, Plätze, Laufzeit oder bestätigt unbefristet, `claim_policy`, Notiz, `request_key`) nur wenn `enrollment.groups_enabled` für jeden Zielkurs wirkt (GF-01, Kurs-Override über R1-33); Detail mit Mitgliederliste (Status, Anzeigename, maskierte Adresse, keine Lernleistungen), Einladen ≤ 200 Adressen mit Ergebnis eingeladen/ungültig/ohne Platz (GF-09), erneut senden (GF-10 über `NotificationService::resend_invitation`, max. 3), Zurückziehen/Entfernen mit Bestätigung (GF-10/GF-13), Plätze ändern mit Vorschau der betroffenen Plätze und Bestätigungspflicht (GF-03/GF-04), Laufzeit (GF-05), Status aussetzen/fortsetzen/beenden mit Bestätigung (GF-06), Besitzerwechsel mit Pflichtbegründung (GF-07), `claim_policy` (GF-08), Umbenennen (GF-14), Gruppenlink neu (GF-11, einmalige Anzeige über Transient, nie in der URL); admin-post `ccap_group_admin` mit Nonce je Operation und Gruppe, testbarer Kern `process()` |
| `src/Access/GroupService.php` | neu: `set_validity()` (Seat-Grants übernehmen das Ende außer `valid_until_locked`), `change_owner()` (Begründung Pflicht, Gruppenlink ungültig, Event `group.owner_changed`, Policy-Memo geleert), `set_claim_policy()` (`invited_address_only` entfernt den Link-Hash), `rename()`, `overview()`, `members()` (Adresse nur für den Besitzer im Klartext, sonst maskiert, E-81), `mask_email()`; beendete Gruppen nur lesend |
| `src/Admin/CertificatesScreen.php` (neu) | A-11 „Certificates“ (#13 lesen, #17 ändern): Liste mit Filtern Kurs/Status/Zeitraum/Vorlagenart, Spalten ohne E-Mail (Nachweis-Nr., Inhaber wie gedruckt, Kurs, Status als Text, Ausstellung, Gültigkeit, Vorlagenversion/Revision), Seitenweise; CSV-Export nur mit Begründung (CF-11); manuelle Ausstellung aus Evidenz (CF-03); Detail mit Verifikationslink, HTML-/Druckansicht, Korrektur (CF-09) und Widerruf (CF-10) über die R1-44-Handler; Tab „Base template“ (CF-04: Aussteller, Fußtext, Logo als Bild ≤ 2 MB, Signaturzeile; #19; `_rev`-Konfliktschutz; neue Version sofort angelegt) mit Versionsliste |
| `src/Certificate/CertificateAdmin.php` (neu) | Abfrage der jeweils neuesten Revision je Zertifikat mit abgeleitetem Status; CSV (UTF-8 mit BOM, Formel-Präfixe neutralisiert, Audit `certificates_exported` mit Grund, Anzahl, Filter); Vorlagenversionen; Logo-Verwendung und Warnung in der Mediathek vor dem Löschen (CRT-024, SOLL); Slug-Historie `course_captain_slug_history` (E-220) bei Änderung von `general.learning_area_slug`/`course_slug` und 301 alter Nachweis-Links auf den aktuellen Pfad, solange keine Seite den alten Slug belegt (CRT-020) |
| `tests/integration/GroupCertificateAdminTest.php` (neu) | 7 Tests |

**Änderungen an gemeinsam genutzten Dateien:** `src/Kernel/Plugin.php` (3 Registrierungen), `src/Ops/AuditView.php` (`group.validity_changed`, `group.owner_changed`, `group.policy_changed`, `group.renamed`, `certificates_exported`). Keine neuen Optionen/Meta (die Slug-Historie stand schon im Manifest), keine Tabellen, Hooks oder REST-Routen; Contract-Snapshots unverändert.

## 2. Feld-/UI-Vertrag

| Screen | Cap | Einstieg | Felder / Regeln |
|---|---|---|---|
| A-10 Liste | #11 | `admin.php?page=course-captain-groups` | Name, Besitzer, Status (Text), Belegung/Plätze, offene Einladungen, freigegebene Plätze, Laufzeit |
| A-10 Anlegen | #11 + `groups_enabled` | `ccap_group_admin` `op=create` | Name (bereinigt), Besitzer, Plätze ≥ 1, Kurse ≥ 1, Ende oder „unbefristet“ bestätigt, `claim_policy`, Notiz |
| A-10 Detail | #11 | `op=invite/resend/withdraw/remove/seats/validity/status/owner/policy/rename/link` | Bestätigung bei Entfernen, Zurückziehen, Platzreduktion mit Betroffenen, Beenden; Begründung beim Besitzerwechsel; Link nur einmal sichtbar |
| A-11 Liste | #13 oder #17 | `admin.php?page=course-captain-certificates` | Filter Kurs/Status/Zeitraum/Vorlage; Export `ccap_certificate_export` mit Pflichtgrund |
| A-11 Detail | #17 | `ccap_certificate_correct`, `ccap_certificate_revoke` (R1-44) | Grund Pflicht; Widerruf endgültig für alle Revisionen |
| A-11 Basisvorlage | #19 | `ccap_certificate_template` | Aussteller ≤ 120, Fußtext ≤ 500, Logo (Bild ≤ 2 MB), Signaturzeile ≤ 120 |

## 3. Ergebnisse (16.11.2026)

phpcs 0 Fehler (1 bekannte Warnung ImportTest.php:318) · PHPStan L8 0 · Unit **104 / 2 819** · Integration **304 / 6 778** · Multisite **316 / 9 947** · Free-Guard 0 · Textdomain-Lint 0. `.pot` nicht aktualisiert (Vorgabe).

## 4. Tests

| ID | Test |
|---|---|
| GRP-001 / 002 / 003 | Gruppen standardmäßig aus → `ccap_groups_disabled`; Kurs-Override `groups_enabled` (veröffentlicht) erlaubt die Anlage (INH-026); Gruppe `manual`, Name ohne URL, Besitzer per E-Mail, unbefristet nur bestätigt, Besitzer ohne Grant, Audit; unbekannter Besitzer/0 Plätze/unbefristet ohne Bestätigung → feldnahe Fehler, nichts gespeichert; falscher Nonce, Lernender ohne #11 |
| GRP-004 / 005 / 011 / 013 | 3 Plätze: zwei gültige + eine ungültige Adresse → 2 eingeladen, 1 ungültig; nach Beitritt weitere Adresse → „ohne Platz“; Erhöhung auf 5; Vorschau „betrifft 2 Plätze“ schreibt nichts, Reduktion ohne Bestätigung abgewiesen mit Anzahl, mit Bestätigung entfernt (Einladungen zuerst, Mitglied bleibt); Einladung zurückziehen nur bestätigt, Adresse und Token sofort gelöscht |
| GRP-006 / 007 | Laufzeit ohne Datum nur bestätigt; neues Ende für Seat-Grants, gesperrtes Grant-Ende bleibt; aussetzen/fortsetzen wirkt auf Seat-Grants; beenden nur bestätigt, Grants widerrufen, danach nur lesend (403/409, Ansicht „can only be viewed“) |
| GRP-008 / 009 / 010 / 012 / 018 | Gruppenlink `ccg_`; Besitzer sieht offene Einladung im Klartext, Admin maskiert, Seite ohne Adresse und ohne Fortschritt/Punkte/Zertifikat (GR-7); Besitzerwechsel ohne Grund abgewiesen, mit Grund: neuer Besitzer, Link ungültig, alter Besitzer 404, Audit; `invited_address_only` deaktiviert den Link (neuer Link abgewiesen); Umbenennen; erneut senden mit Audit `invitation_resent` |
| CRT-024 / 026 / 063 | Liste und Filter (Status gültig/widerrufen, Vorlagenart), Lernender ohne Recht 403; Export ohne Grund abgewiesen, mit Grund: Formel neutralisiert, keine E-Mail, Audit mit Grund und Anzahl; Logo-Verwendung in Snapshots und Basisvorlage, Mediathek-Warnung nur bei Verwendung; Screens ohne PDF-, Designer-, Lizenz- oder Pro-Elemente |
| CRT-018 | Basisvorlage speichern → neue Version, Aussteller wirksam; Nonce, Logo kein Bild → `ccap_logo_invalid`, Redakteur ohne #19 → `ccap_forbidden`, Wert unverändert |
| CRT-020 | Lernbereich-Slug `lernen` → `akademie`: Historie, alter Verifikations- und Listenpfad → Ziel auf neuem Pfad, fremde Pfade und aktueller Slug ohne Umleitung; Seite mit altem Slug → keine Umleitung |

Bereits in R1-44 abgedeckt und weiter grün: CRT-017 (Namensfreigabe), CRT-013/014 (Widerruf), CRT-015/016 (Verifikation), CRT-021 (kein `pdf_url`), CRT-025 (Lernsperre: eigene Ansicht gesperrt, Verifikation 200).

## 5. Security-/Accessibility-Review

- Jede Aktion: admin-post mit Nonce je Operation und Gruppe bzw. je Formular, Rechte im Dienst (#11, M-15, #13/#17, #19); Fehler werden als Codes zurückgegeben, nie als Rohtext.
- Personendaten: Mitgliederliste maskiert (E-81), Klartext nur für den Besitzer; keine Lernleistungen (GR-7); CSV ohne E-Mail, nur mit Grund und Audit, Formel-Injektion neutralisiert; der neue Gruppenlink erscheint nur einmal (E-112) und nie in einer URL.
- Lizenzunabhängigkeit (GR-2, CR-5, E-100): alle Funktionen ohne Pro; keine Pro-, PDF- oder Lizenzelemente (CRT-063).
- A11y: Formulare ohne JavaScript, jedes Feld mit sichtbarem Label, Gruppen mit `fieldset/legend`, Tabellen mit `caption`/`th scope`, Rückmeldungen als `role="status"` bzw. `role="alert"`, Zustände als Text, Bestätigungen als beschriftete Kästchen.

## 6. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Tabelle `ccap_certificate_template` (Δ-7), Spalte `revision_kind` (Δ-10) | weiter Option `course_captain_certificate_templates` und Snapshot-Feld (R1-44 A-1); eine Schemaänderung mit Migration gehört in ein eigenes Schema-Upgrade. **Klärung**, ob das vor Gate R1 nötig ist |
| A-2 | GF-01 nur im Admin-Einstieg | Die Sperre `groups_enabled` prüft der A-10-Einstieg; der Dienst `GroupService::create()` bleibt ohne Prüfung, weil Quellen (Kauf, R3) und bestehende Tests ihn direkt nutzen. **Klärung**, ob Quellen die Einstellung beachten müssen |
| A-3 | E2E (Playwright) GRP-002/005/008/011/014/016/018, CRT-026, A11y-Prüfungen GRP-021/CRT-027 | in dieser Umgebung kein Browser-Lauf; abgedeckt über die testbaren Kerne der Handler und die gerenderten Seiten (Integration). Playwright-Läufe mit R1-19/R1-42 |
| A-4 | Elterngruppen/Kontingente (Δ-1/Δ-2, GRP-023/024/025), Delegierte (GRP-022) | Spalte `parent_group_id` und Resolver-Slot `groupManagerResolvers` fehlen im Schema bzw. Registry; ohne Pro gibt es keine Kinder und keine Delegierten, Verhalten unverändert (nur Besitzer und #11). Mit Schema-Upgrade/R3-08 |
| A-5 | Gruppenfilter im Basisbericht (GF-16, GRP-019/020) | Basisbericht A-12 existiert noch nicht (R1-12-Folge) |
| A-6 | Gruppen-Overrides `enrollment.claim_policy`/`invite_valid_days` (R0-28 #25/#26) | `claim_policy` ist Gruppenspalte; `invite_valid_days` je Gruppe (nur kürzer) validiert der Resolver, ein Speicherort am Gruppen-Datensatz fehlt; Einladungsdauer bleibt 30 Tage bzw. globaler Wert mit R1-36 |
| A-7 | Wiedereinsetzung einer beendeten Gruppe (R1-13 A-4) | nicht gebaut; „beendet“ bleibt endgültig je Quellrevision. **Klärung**, ob ein manueller Weg (neue Quellrevision) in R1 gebraucht wird |
| A-8 | Basisvorlage auch im globalen Settings-Tab „Zertifikate“ (R1-32/R1-34) | A-11 speichert über denselben Dienst (`Settings::save`, `_rev`); bei paralleler Umsetzung im Tab darf es nur eine Oberfläche geben – **Abstimmung mit R1-34** |
| A-9 | QR-Code (SOLL), Sprachvarianten des Fußtexts | nicht gebaut |

Review durch zweite Person vor Merge (AG-05).
