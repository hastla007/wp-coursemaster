# Release-Dokumentation

Grundlage: R0-18 (Release- und QA-Automatisierung), R0-35 (Distribution-Readiness, Release-Checkliste RC-001…RC-078), `CHANGELOG.md`, Nachweise `docs/r1/R1-*-nachweis.md`, Git-Historie des Integrationsstands.

## 1. Versionierung

- SemVer. Startversion `1.0.0` erst beim ersten Release (E-209); bis dahin `1.0.0-dev`.
- Plugin-Kopf, Konstante `COURSE_CAPTAIN_VERSION`, `Stable tag` in `readme.txt` und oberster Eintrag in `CHANGELOG.md` müssen gleich sein (Test `PluginHeaderTest::test_RC_041_versions_are_equal`).
- Getrennt versioniert: Vertragsversion (`COURSE_CAPTAIN_CONTRACT_VERSION`, heute `0.1.0`), Datenbankschema (`<semver>+<hash>`), Settings-Schema, Paketformat, Zertifikats-Snapshot.
- Pflichtmarker im Changelog: `Schema`, `Backfill`, `Destruktiv (Contract) – Backup vorher`, `Sicherheitsupdate:`.

## 2. Änderungsprotokoll R1 (Stand 16.11.2026, unveröffentlicht)

Alle Punkte gehören zu `1.0.0-dev`. Jeder Eintrag verweist auf den Nachweis. Ein Review durch eine zweite Person vor dem Merge (AG-05) steht für alle Einträge aus.

| Aufgabe | Inhalt | Nachweis |
|---|---|---|
| R1-01 | Plugin-Kern: Systemprüfung, Aktivierung je Site, Netzwerkaktivierung blockiert, Haltemodus, Deaktivierung, Deinstallation nach exaktem Manifest (Daten bleiben ohne Bestätigung) | `docs/r1/R1-01-nachweis.md` |
| R1-02 | Schema mit 53 Tabellen, Migrator mit Sperre und Lease, Wartungsmodus für Schreibzugriffe (`ccap_maintenance`) — **Schema** | R1-02 |
| R1-03 | Rollen (Kursmanager, Autor, Trainer, Support), 24 Rechte, zentraler Policy-Dienst, Schutz vor Rechteausweitung | R1-03 |
| R1-04 | Kurs-/Modul-/Lektionsmodell, unveränderliche Kursrevisionen, Archiv, Löschschutz, Bundles | R1-04 |
| R1-05 | Autorenoberfläche (Metaboxen Module/Revision/Activity), Duplizieren, gebündelte Hinweise, Autoren-REST | R1-05 |
| R1-06 | Fragenbank mit 9 neutralen Fragetypen, Revisionen, signierte Fragen-Token | R1-06 |
| R1-07 | Activity-Vertrag, 7 Activity-Typen, Lernmedien, Fremdhost-Erkennung | R1-07 |
| R1-08 | Prüfungsregeln, Blueprints, Varianten, Versuchsautomat mit Snapshot | R1-08 |
| R1-09 | Lernantworten mit Idempotenz, Ergebnisfilter nach Feedback-Regeln, Nachbewertungsvertrag | R1-09 |
| R1-10 | Kursabschluss gegen die Revision des Lernenden, Mastery-Quote, abgeleitete Kompetenz, Neuaufbau | R1-10 |
| R1-11 | Karteikarten (zwei Modi), Favoriten und Sammlungen, Fehlertraining | R1-11 |
| R1-12 | Smarttrainer v1 mit Intervallen und Ratenlimit | R1-12 |
| R1-13 | Zugangsautomat, manuelle Zugänge, Gruppen, Plätze, Einladungen | R1-13 |
| R1-14 | Content-Gate (Teilumfang, Drip, Voraussetzungen), geschützte Ablage und Auslieferung, geschützte Seiten | R1-14 |
| R1-15 | Paketvalidierung, Trockenlauf, Import-Zustandsautomat, Rollback, Export | R1-15 |
| R1-16 | Lernbereich („Mein Lernen“, Kursraum), Lernenden-REST, Blöcke Katalog und Lernbereichs-Link | R1-16 |
| R1-17 | Datenschutz-Export/-Löschung, Aufbewahrungsläufe, isolierter Wartungslauf, Website-Zustand, Audit-Ansicht | R1-17 |
| R1-18 | neutrale Referenz-Fixture und Golden-Master-Tests (Teil) | R1-18 |
| R1-20 | Prüfungsintegrität: Offline-Puffer, mehrere Tabs, Startlimit, Prüfungs-REST | R1-20 |
| R1-21 | Benachrichtigungen (16 Typen, 7 aktiv), Mailvorlagen, Abmeldung, Ausgangsmodus | R1-21 |
| R1-25 | Objektcache mit Generationen, Invalidierung nach Commit, `no-store` auf allen REST-Antworten | R1-25 |
| R1-26 | Veröffentlichungsprüfung mit VAL-Codes und Bestätigung von Warnungen | R1-26 |
| R1-31 | Settings-Register, Speicherung je Tab, Rechte, Migration, Zurücksetzen/Undo, Export | R1-31 |
| R1-43 | interne Outbox mit Zustellung, Dead-Letter und Abgleich von Zugangsquellen | R1-43 |
| R1-44 | Basiszertifikate mit Snapshot, Korrektur, Widerruf und öffentlicher Verifikation | R1-44 |
| R1-24 | Betreiber-, Autoren-, Lernenden-, Support-, Datenschutz-, Entwickler- und Release-Dokumentation (`docs/user/`) | R1-24 |

Parallel in Arbeit: R1-28 (API-Verträge, einheitliches Fehlerformat, Shortcode `[ccap_protected]`; Doku in `docs/API.md`/`docs/API-CHANGELOG.md`).

## 3. Release-Checkliste (Kurzfassung von R0-35 §5)

Vollständige, prüfbare Liste: R0-35 §5 (RC-001…RC-078), Abnahme in R1-39. Status hier: ✓ vorhanden · ◐ teilweise · ○ offen.

| Bereich | Prüfpunkte | Status |
|---|---|---|
| Slug, Konto, Einreichung | RC-001…006 (Slug `course-captain`, Konto mit 2FA, öffentliches Repository) | ○ |
| Rechte und Lizenz | RC-010…014 (Clean Room, GPLv2, `LICENSE`, `CREDITS.md` = SBOM, keine Altinhalte) | ◐ (`LICENSE`, `CREDITS.md`, Free-Guard vorhanden; SBOM/ZIP-Prüfung offen) |
| Richtlinien und Sicherheit | RC-020…032 (Free-Guard, kein Upsell, keine Telemetrie, External services, Plugin Check, Sicherheitsjobs, Präfixe) | ◐ (Free-Guard, Präfix-Scan; Plugin Check, SAST, E2E-Fremdhost offen) |
| readme.txt, Übersetzungen | RC-033…038 | ◐ (`readme.txt` auf R1-Stand gebracht; Übersetzungen, Validierungsskript offen) |
| Assets | RC-039/040 (Icon, Banner, Screenshots) | ○ |
| Build und Artefakt | RC-041…048 (Versionsgleichheit, Positiv-/Negativliste, reproduzierbar, Signatur, PHP-8.2-Probe) | ◐ (RC-041 Repository-Teil, RC-046/047 Logik; `release.yml`, `tools/release/` fehlen) |
| Deploy und Verzeichnis | RC-050…054 | ○ |
| Installation, Update, Restore | RC-060…067 (Clean-Install, Upgrade, Restore, Deaktivierung, Release-Notes mit Rollback-Klasse) | ◐ (Deaktivierung/Uninstall-Teil getestet; R1-23 offen) |
| Support und Sicherheit | RC-070…074 (`SECURITY.md`, Probe Sicherheitsablauf, Runbook, Forum-Diagnose, Readme-FAQ) | ◐ (Runbook und Forum-Regeln in `troubleshooting.md`, FAQ in `readme.txt`; `SECURITY.md` und Sicherheitsadresse fehlen) |
| Gate-Abschluss | RC-075…078 (CI grün, Datenschutz/Supporthandbuch verknüpft, Barrierefreiheits-Konformitätsbericht, Gate-Protokoll) | ◐ (Doku-Belege R1-24; Konformitätsbericht R1-19) |

### 3.1 Ablauf eines Releases (R0-18 §3.6)

1. `preflight`: Tag `v<version>` auf `main`, Versionsgleichheit, Changelog-Marker.
2. Volle CI auf dem Tag-Commit (Release-Lane).
3. Zwei reproduzierbare Builds mit gleichen Prüfsummen, SBOM (CycloneDX).
4. Signatur: Build-Attestierung und cosign über `SHA256SUMS`.
5. Staging: Neuinstallation und Upgrade von der Vorversion.
6. GitHub-Release als Entwurf.
7. WordPress.org-SVN-Deploy nur nach Freigabe durch den Auftraggeber (`tags/<v>` → `assets/` → `trunk`).
8. `wporg-verify`: Inhaltsvergleich des Verzeichnis-ZIPs.
9. Release veröffentlichen.

Ein Release wird nie zurückgezogen, sondern nur durch ein PATCH-Release ersetzt.

**Lokale Vorprüfung** (heute möglich): `composer lint`, `composer analyse`, `composer test`, `composer test:integration`, `composer test:integration-ms`, `php tests/guards/free-guard.php`, `php tests/guards/textdomain-lint.php`, `wp i18n make-pot` gegen `languages/course-captain.pot`.

## 4. Vorlage Release-Notes

```markdown
# Course Captain <version> – <Datum>

## Zusammenfassung
<2–4 Sätze, neutral, ohne Werbeaussagen>

## Änderungen
- <Eintrag> (Aufgabe/Issue)

## Marker
- Schema: ja/nein (<Migrationsschritte>)
- Backfill: ja/nein
- Destruktiv (Contract) – Backup vorher: ja/nein
- Sicherheitsupdate: ja/nein (<ohne Ausnutzungsdetails>)

## Rollback-Klasse
R-A (Downgrade gefahrlos) / R-B (Downgrade nur mit Backup) / R-C (kein Downgrade, nur Restore)

## Vor dem Update
- Backup von Datenbank und `uploads/course-captain` anlegen.
- Während strenger Prüfungen nicht aktualisieren (automatische Updates werden aufgeschoben).

## Prüfung der Signatur
sha256sum -c SHA256SUMS
cosign verify-blob --bundle SHA256SUMS.sigstore.json --certificate-identity <workflow-identität> --certificate-oidc-issuer https://token.actions.githubusercontent.com SHA256SUMS
gh attestation verify course-captain-<version>.zip --repo <owner>/<repo>

## Bekannte Einschränkungen
- <Liste>
```

Hinweis: Das Aufschieben automatischer Updates während strenger Prüfungen (E-174) ist noch nicht umgesetzt (R1-23).
