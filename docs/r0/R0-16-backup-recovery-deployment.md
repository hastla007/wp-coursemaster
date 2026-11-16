# R0-16 · Backup, Recovery und Deployment (Entwurf)

**Status:** **Vorgeschlagen – Freigabe Auftraggeber/Architektur offen.** Task R0-16 ist **nicht** abgehakt. Es gibt keine Implementierung, keinen Code und keine Betreiberdoku.
**Stand:** 16.11.2026
**Bezug:** TODO R0-16 (DoD: Upgrade-/Rollback-/Restore-Runbook, Backup-Konsistenz, Staging, Secret-Handling, Recovery-Tests), Zuarbeit für **R0-37** (Schema-Upgrade unter Konkurrenz), **R1-23** (Upgrade-, Restore- und Uninstall-Proben), **R1-40** (Migration mit Lock und Wiederaufnahme), R0-35, R0-39, R1-24, R1-38, R1-39; Konzept Kap. 22.2 (Updates und Wiederherstellung), 22.1, 6, 9.4; `docs/r0/R0-06-datenmodell-adr.md` (§3.5, §4.7–4.9, §6, Nachträge); `docs/r0/R0-10-import-medien-sicherheit.md` (§3.4–3.6, §4.1, §4.4, §6.1); `docs/r0/R0-02-inventar/C-infrastruktur-content.md` (INV-C-012, -024…-029, -035, -060…-066, -097, -165, -190, §3.3 Optionen); `docs/r0/R0-02-inventar/E-theme-tools-content-docs.md` (§2.3, §2.5, §4, §5 Betriebsdoku); `docs/r0/R0-36-suite-vertragsdeltas.md` (§2.2/2.3/2.5, L1–L12)
**Entscheidungen als Annahme (noch nicht beschlossen, `ENTSCHEIDUNGEN.md`):** **E-09** (Free über WordPress.org, Pro über License-Captain-Client, keine GitHub-Abfrage), **E-13** (Staging und CI-Deploy zu WordPress.org-SVN nur bei grüner CI, Tag auf `main` und manueller Freigabe). Außerdem abhängig von E-07/E-49 (Single-Site, Schema je Site), E-08 (InnoDB Pflicht), E-44/E-51 (Aufbewahrung), E-63 (Reimport: lokal gewinnt), E-65 (geschützte Medien). Weicht ein Beschluss ab, ändern sich §2.4, §3.3 und §3.4.
**Quellen (nur gelesen):** LearnBoating `hastla007/learnboating` @ `9f37c77`: `docs/backup-restore.md`, `docs/installation-und-updates.md`, `docs/release-regel.md`, `docs/livegang-drehbuch.md`, `staging/` (8 Dateien), `tools/release/{build-zips.sh, upgrade-check.php}`, `bootspruefung-learning/uninstall.php`, `src/Plugin.php:344-520` (Migration), `src/Infrastructure/{Maintenance, BackupCheck, Uninstaller}.php`. Pass Captain `wp-passmaster` @ `b2b88ea`: `docs/OPERATIONS.md` (Abschnitte „Vor einem Upgrade“, „Schema-Upgrade“, „Wiederherstellung“, „Release-Kandidat“). Pfade ohne Präfix sind relativ zu `bootspruefung-learning/`.

---

## 0. Kurzfassung

1. **Ein Backup ist nur als Paar gültig:** vollständige Datenbank (nicht nur `cc_*`) **und** die drei Dateiklassen `public`, `protected`, `packages` (R0-10 §4.1), dazu `wp-config.php`. Der private Ordner liegt bevorzugt außerhalb des Webroots und wird von vielen Backup-Plugins **nicht** erfasst. Das ist der häufigste Restore-Fehler (LB `docs/backup-restore.md` §1).
2. **Konsistenz ohne Wartungsfenster:** `mysqldump --single-transaction` (InnoDB ist Pflicht, E-08), **erst DB, dann Dateien**. Das funktioniert, weil Course Captain Mediendateien nie überschreibt (neuer SHA-256 → neuer `storage_key`, R0-10 §3.6), Dateien vor der DB-Zeile schreibt (R0-10 §3.5) und unreferenzierte Dateien erst nach einer Karenzzeit löscht (§1.3). Ein **Read-only-Fenster** ist nur für Werkzeuge ohne konsistenten Dump und vor Upgrades nötig.
3. **Delta zu LB: keine absoluten Pfade in der Datenbank.** LB speichert `bp_learning_storage_layout` und `_bp_protected_path` absolut. Ein Restore neben dem Original kann deshalb die Ordner des Originals in die Kopie verschieben (LB-Übung 13.11.2026, T209). Course Captain speichert nur Schlüssel relativ zur Basis; die Basis kommt zur Laufzeit aus `COURSE_CAPTAIN_PRIVATE_DIR` bzw. `uploads`.
4. **Rollback heißt Restore von DB und Dateien zusammen**, keine Rückmigration (R0-06 §6.5). Ausnahme: Downgrade um eine Minor-Version innerhalb des Expand-Fensters (Entscheidung R16-D6).
5. **Restore- und Kopie-Erkennung:** Ändert sich die Site-Identität (URL, Pfad, DB-Fingerprint), schaltet Course Captain ausgehende Wirkungen (Mail, Outbox, Connectoren) in einen Haltemodus, bis ein Admin bestätigt (R16-D1). Damit gibt es nach einem Restore keine doppelten Mails, Zertifikate oder Einladungen (Konzept 22.2) und aus Staging-Kopien keine Mails an echte Lernende.
6. **Teil-Restore eines Kurses** geht nur über den Paket-Export aus einer temporären Restore-Kopie (R0-10 §6.1), nie über einzelne Tabellen.
7. **Uninstall:** Daten bleiben standardmäßig. Löschen nur nach Setting **und** ausdrücklicher Bestätigung. Ein **Uninstall-Manifest mit CI-Vollständigkeitstest** schließt die LB-Lücke (`bp_entitlement_prune_last`, `_bp_catalog_fields` bleiben dort liegen, INV-C-026).
8. **Release:** Free reproduzierbar aus Commit → CI → Tag auf `main` → manuelle Freigabe → WordPress.org-SVN (E-13). Pro über den License-Captain-Client mit `core_version`-Sperre (R0-36 L4). 25 Recovery-Tests REC-001…REC-025 gehen an R1-23 und R1-40.

---

## 1. Backup: Umfang und Konsistenz

### 1.1 Was zu einem konsistenten Backup gehört

**Grundregel (LB `docs/backup-restore.md` §1, Pass `OPERATIONS.md` „Wiederherstellung“):** die **ganze** Datenbank sichern, nicht nur Plugin-Tabellen. Grants verweisen auf Nutzer, Lektionen sind Posts, geschützte Anhänge sind Mediathek-Einträge. Ein Backup nur der `cc_*`-Tabellen ist nach dem Restore inkonsistent.

| Teil | Inhalt | Warum kritisch | Quelle |
|---|---|---|---|
| **Tabellen `{prefix}cc_*`** | 39 Tabellen + 1 bedingte (`cc_request_idempotency`) | Fragenbank, Revisionen, Prüfungssitzungen, Lernstände, Grants, Zertifikate, Outbox/Inbox, Import-Batches, Audit | R0-06 §2, §4 |
| **Optionen `cc_*`** | Settings; Schema-/Migrationszustand `cc_db_version`, `cc_schema_fingerprint`, `cc_migrations_applied`, `cc_backfill_state`, `cc_migration_failed`, `cc_fk_issues`; aktives Paket; **Ordner-Geheimnis** (Vorschlag `cc_storage_secret`); **Installations-UUID** (Vorschlag `cc_installation_uuid`, Provenienz im Export R0-10 §6.1) | Fehlt das Ordner-Geheimnis, findet das Plugin `protected-<geheim>`/`packages-<geheim>` nicht mehr. LB legte dann still neue, leere Ordner an (LB §1 „Wichtig“). Fehlt der Migrationszustand, laufen Schritte doppelt | R0-06 §6.1, E-52; R0-10 §4.1 |
| **CPTs und Meta** | `cc_course`, `cc_module`, `cc_lesson`, `cc_faq`, `cc_tip` in `wp_posts` (inkl. WP-Revisionen, auf die `cc_course_revision.structure` verweist), `wp_postmeta` (`_cc_activity_type`, `_cc_rights`, Schutz-Meta geschützter Anhänge), Terms | Inhalte; Kursrevisionen referenzieren WP-Revisions-IDs (R0-10 §3.4, offener Punkt) | R0-06 E-01…E-05, E-15/16 |
| **Mediathek** | Anhänge mit `cc_media.attachment_id` | `cc_media` ↔ Anhang | R0-06 E-13 |
| **Nutzer** | `wp_users`, `wp_usermeta` (inkl. `cc_*`-Nutzer-Meta) | Grants, Lernstände, Zertifikate verweisen auf Nutzer-IDs | R0-06 §4.9 |
| **Cron/Queue** | Option `cron`; Action-Scheduler-Tabellen, falls genutzt | Zustand geplanter Läufe; nach Restore ohnehin per Selbstheilung neu eingeplant (LB `ensureSchedules`, INV-C-012) | ADR-006 offen |
| **R3: WooCommerce** | Bestellungen (HPOS/Posts), Woo-Optionen | Grant-Quellen verweisen auf Bestellungen (R3-Adapter) | LB §1, INV-C-065 |
| **Dateien `public`** | `uploads/course-captain/media/` | Kursbilder, öffentliche Vorschau | R0-10 §4.1 |
| **Dateien `protected`** | `<privat>/protected-<geheim>/` | zugangsgebundene Medien, geschützte Anhänge samt Bildgrößen | R0-10 §4.1, E-65 |
| **Dateien `packages`** | `<privat>/packages-<geheim>/`: **Paketkopien** (Rollback-Ziel des Imports), **Import-Staging** (`import/`, Upload-Zwischenordner), Exporte | Ohne Paketkopie kein Content-Rollback (INV-C-097) | R0-10 §3.6, §4.1 |
| **`wp-config.php`** | DB-Zugang, Salts/Keys, `COURSE_CAPTAIN_PRIVATE_DIR`, Secret-Konstanten (§3.2), `DISABLE_WP_CRON`, `WP_ENVIRONMENT_TYPE` | Mit anderen Salts sind Sitzungen und signierte Links ungültig (LB §1 zu `wp-config.php`) | LB §1 |
| **Versionsnotiz** | Plugin-Version Free/Pro, `cc_db_version`, Zeitpunkt (UTC) | Der Restore muss zu einem passenden ZIP greifen (Pass „Vor einem Upgrade“ 3.) | §2.1 |

**Nicht nötig:** Transients, `cc_rate_limit`, `cc_request_idempotency` (beide dürfen leer zurückkommen, Ablauf über `expires_at`), Exporte älter als 24 h (R0-10 §6.1), `languages/` (lässt sich neu laden).

**Systemstatus** zeigt unter „Datensicherung“ die tatsächlichen Pfade **aller** drei Klassen und warnt, wenn der private Ordner außerhalb von `ABSPATH` liegt („von vielen Backup-Plugins nicht erfasst“). Muster: LB `BackupCheck::storage` (INV-C-062).

### 1.2 Konsistenz sichern

**Problem:** DB-Dump und Dateikopie sind zwei Zeitpunkte. Ein tabellenweiser Dump ohne Transaktion (viele Backup-Plugins) ist zusätzlich **zwischen Tabellen** inkonsistent, z. B. `cc_exam_session` ohne zugehörige `cc_exam_session_question`.

| Stufe | Verfahren | Wann | Grenzen |
|---|---|---|---|
| **A (Standard)** | `mysqldump --single-transaction --quick --routines --triggers --hex-blob --default-character-set=utf8mb4` bzw. `wp db export`, **danach** Dateien (`tar`/restic/borg) | täglich | konsistenter DB-Schnappschuss (InnoDB MVCC, E-08); Dateien sind eine Obermenge (siehe Invarianten unten) |
| **B (Read-only-Fenster)** | `wp course-captain maintenance on --read-only` (Name unter R0-19-Vorbehalt): schreibende Anfragen an `course-captain/v1` bekommen 503 + `Retry-After: 30` (derselbe Mechanismus wie bei fälliger Migration, R0-06 §6.4 Nr. 5, LB `Maintenance`); Import-/Export-/Backfill-Jobs und Outbox-Dispatch pausieren; lesende Anfragen laufen weiter. Dann Backup, dann `maintenance off` | vor Upgrades mit Schemaänderung; bei Backup-Werkzeugen ohne Single-Transaction; vor destruktiven Contract-Schritten | Prüfungen laufen weiter. Der Client puffert Antworten (LB-Muster). Die Fensterdauer zählt gegen die Prüfungsfrist → R0-08 legt fest, ob Fristen um das Fenster verlängert werden (REC-011) |
| **C (Snapshot)** | Dateisystem-/Volume-Snapshot (LVM, ZFS, Hoster) von DB-Volume und Dateien im selben Moment | Hoster bietet es an | DB-Snapshot nur crash-konsistent; InnoDB-Recovery beim Start muss laufen; Snapshot im Read-only-Fenster ist sicherer |

**Reihenfolge-Invarianten (Pflicht für R1-14/R1-15, sonst gilt Stufe A nicht):**

- **I-1 Datei vor Zeile:** Eine Mediendatei wird vollständig geschrieben (Temp-Datei, SHA-256, `rename`), **bevor** die `cc_media`-Zeile bzw. die Zuordnung committet wird (R0-10 §3.5). Jede Zeile im Dump zeigt also auf eine Datei, die zum Dump-Zeitpunkt existierte.
- **I-2 Kein Überschreiben:** Gleicher Schlüssel = gleicher Inhalt; ein neuer Inhalt bekommt einen neuen `storage_key` (R0-10 §3.6; Delta zu LB `MediaStorage::store`, das am selben Schlüssel überschreibt).
- **I-3 Karenz vor Löschen:** Der Retention-Job löscht eine unreferenzierte Datei bzw. Paketkopie erst **7 Tage** nach dem Wegfall der letzten Referenz (Startwert, Setting). Damit kann eine Datei, die der DB-Dump noch referenziert, während der Dateikopie nicht schon weg sein. Während Stufe B ruht der Job ganz.
- **I-4 Relative Pfade:** In der DB stehen nur `storage_key` bzw. Pfade relativ zur Klassenbasis (Delta zu LB `_bp_protected_path`, `bp_learning_storage_layout`). Die Basis wird zur Laufzeit aufgelöst. Ein automatisches „Ordner an Soll-Ort verschieben“ wie bei LB (`docs/backup-restore.md` §5) gibt es **nicht**: Course Captain verschiebt nie einen Ordner, den es nicht selbst unter der aktuellen Basis angelegt hat.
- **I-5 Aktivierung atomar:** Ein Import wird erst in `activating` sichtbar, in einer Transaktion je Kurs (R0-10 §3.4). Ein Dump mitten im Import enthält entweder den alten oder den neuen Stand, nie einen sichtbaren Mischstand.

### 1.3 Rhythmus, Aufbewahrung, Ziel (Betreiberempfehlung, R1-24)

| Was | Wann | Aufbewahrung |
|---|---|---|
| DB + Dateien voll (Stufe A) | täglich nachts | 14 täglich, 4 wöchentlich, 3 monatlich (LB §2 als Startwert) |
| DB + Dateien zusätzlich (Stufe B) | vor jedem Free-/Pro-Update mit Schemaänderung, vor jedem Content-Import mit „destruktiv“-Hinweis, vor Uninstall mit Datenlöschung | mindestens bis zum nächsten erfolgreichen Update, ≥ 14 Tage |

- **RPO/RTO-Richtwerte** (Empfehlung, R16-D7): RPO ≤ 24 h (Standard), ≤ 1 h mit stündlichem DB-Dump oder Binlog; RTO ≤ 4 h für eine Site der Lastklasse `default` (E-52). Die tatsächliche Dauer misst REC-001; R0-14 verweist für Backup-/Restore-Dauer hierher.
- **Ort und Schutz:** Kopie an einem zweiten Ort ohne Löschrecht des Webservers, verschlüsselt (restic/borg o. Ä.), Schlüssel getrennt; AV-Vertrag nach Art. 28 DSGVO mit dem Speicheranbieter (LB §3).
- **Datenschutz:** Backups enthalten Personendaten (Registry R0-06 §4.9). Das Höchstalter der Backups gehört ins Löschkonzept (R0-31). Nach einem Restore aus älterem Stand sind Löschungen seit dem Backup erneut auszuführen. Weil die WP-Privacy-Anfragen (`user_request`) und `cc_audit_log` mit zurückgerollt werden, braucht der Betreiber dafür ein externes Protokoll (R16-D8).

### 1.4 Backup-Prüfung `backup-check`

Nur lesend, Gesamtstatus `ok/warn/fail`, CLI-Exit 1 bei `fail`, `--format=json` (LB INV-C-060…065, INV-C-165). Course-Prüfungen:

| Prüfung | `fail` bei | `warn` bei |
|---|---|---|
| Schema | `cc_db_version` fehlt; Schema neuer als Code (`schemaIsNewer`) | Migration fällig; Backfill offen |
| Tabellen | eine `cc_*`-Tabelle fehlt oder ist nicht InnoDB | – |
| Fremdschlüssel | – | weniger als erwartet (`cc_fk_issues`, E-48) |
| Optionen | Ordner-Geheimnis oder Installations-UUID fehlt | – |
| Ablage | Klassenordner fehlt; **eingestellter privater Ordner nicht lesbar** | Ordner außerhalb von `ABSPATH` (Backup-Hinweis); fremder Ordner gleichen Namens |
| Aktives Paket | kein `published`-Batch zum aktiven Paket | Paketkopie fehlt (kein Content-Rollback möglich) |
| Mediendateien | referenzierte Datei fehlt (Liste `media_uid`, `storage_key`) | Größe/SHA-256 weicht ab (Stichprobe; `--full` prüft alle); unreferenzierte Dateien (Versatz, REC-012) |
| Grants | – | Stichprobe 25 aktive Grants: Zugriffsprüfung sagt „nein“; Grants ohne Nutzerkonto (LB-Übung, Auffälligkeit 2) |
| Laufende Vorgänge | – | `cc_import_batch` in `importing/activating` ohne Heartbeat; `cc_exam_session` `in_progress` über Frist; Outbox `dispatching` mit abgelaufenem `locked_until` |
| Site-Identität | – | Identität weicht ab → Haltemodus aktiv (§2.3) |
| R3-Adapter | Bestellungen zu nicht beendeten Woo-Grants fehlen (Beitrag des Adapters) | – |

---

## 2. Runbooks

Die Betreiberfassung schreibt R1-24 (Struktur „Was / Wann / Woran erkennbar“, LB `betreiber-schritte.md`). Hier steht der fachliche Ablauf. CLI-Namen stehen unter R0-19-Vorbehalt.

### 2.1 Upgrade (Free, Pro)

**Vorab-Checks** (Systemstatus-Abschnitt „Update-Bereitschaft“ und `wp course-captain preflight`; nur lesend):

| # | Check | Abbruch bei |
|---|---|---|
| U-1 | Changelog bzw. `readme.txt` „Upgrade Notice“ gelesen: Kennzeichnung **Schema**, **Backfill**, **destruktiv (Contract)**, **Pro braucht Free ≥ x** | – (Pflichtlektüre) |
| U-2 | Kein Import in `importing/activating`, kein Export läuft, keine Migration mit `cc_migration_failed` | aktiver Import → erst abschließen oder `discard` |
| U-3 | Backfill der Vorversion abgeschlossen (`cc_backfill_state`) | offen **und** das Update enthält einen Contract-Schritt. Der Schritt prüft das selbst und verweigert sich (R0-06 §6.2: Entfernen erst ab N+2 nach abgeschlossenem Backfill) |
| U-4 | DB-Rechte `CREATE, ALTER, INDEX, REFERENCES, DROP` (Contract), freier Speicher ≥ 2 × größte Tabelle (für `ALTER` mit Kopie) | fehlend (LB-Ursachen T120) |
| U-5 | PHP/WP/DB-Version erfüllt die Kopfzeilen (`Requires PHP`, `Requires at least`) und E-57/E-58 | nicht erfüllt |
| U-6 | Laufende Prüfungen (`in_progress`) gezählt und angezeigt; Empfehlung: Zeitfenster mit wenigen Sitzungen | – (Hinweis) |
| U-7 | Backup Stufe B erstellt, Zeitpunkt + Versionen notiert, `backup-check` auf einer Restore-Kopie grün (Livegang: Pflicht, sonst empfohlen, LB Livegang T−2 h) | kein Backup bei Contract-Schritt |
| U-8 | Erst Staging mit gleicher PHP-/WP-/DB-Version (Pass „Vor einem Upgrade“ 1.), dann Live | – |
| U-9 | Pro: Pro-Release verlangt `requires_core` ≤ installierte Free-Version (License Captain hält sonst zurück, R0-36 L4) | Free zu alt → Free zuerst |

**Ablauf:**

1. Reihenfolge bei mehreren Paketen: **Free → Pro → Adapter**. Ist Free zu alt, pausiert Pro seine Worker und zeigt einen Hinweis; Free-Daten bleiben unberührt (Konzept 22.2, R0-36 A8/L5).
2. Paket ersetzen (WordPress.org-Update, „Plugin hochladen › Ersetzen“ oder `wp plugin update`). **Nicht vorher deaktivieren oder löschen** (LB `installation-und-updates.md` Schritt 4).
3. Die Migration startet über Admin, Cron oder CLI (`wp course-captain migrate [--dry-run]`); Frontend/REST plant nur `cc_run_upgrade` (R0-06 §6.4). Bis dahin: schreibende Lern-API 503 + `Retry-After`, lesend normal.
4. Reihenfolge im Lauf: `dbDelta` → Drift-Check (`information_schema`) → FK-Schritt (nicht fatal) → nummerierte Schritte `NNNN_*` → Schemaversion setzen → Backfill als Job (eigener Status, R0-06 §6.3).
5. Nachkontrolle: Systemstatus Schema „aktuell“, `cc_fk_issues` leer, Backfill-Fortschritt, Health `migration=true`; ein Testkonto öffnet „Mein Lernen“ und eine Frage; eine geschützte Datei lädt.

**Abbruch und Wiederaufnahme** (Detailvertrag R0-37):

| Situation | Verhalten | Betreiber |
|---|---|---|
| Prozess stirbt in `dbDelta` | `GET_LOCK` endet mit der Verbindung; der nächste Trigger wiederholt `dbDelta` (idempotent) und den Drift-Check | nichts; ggf. `migrate` |
| Prozess stirbt in Schritt `NNNN` | Schritt prüft den Ist-Zustand; `cc_migrations_applied` wird erst nach Erfolg geschrieben; Wiederholung ist sicher | nichts; ggf. `migrate` |
| Prozess stirbt im Backfill | Cursor in `cc_backfill_state`, Heartbeat; Fortsetzung ab Cursor; Funktionen bleiben bis Abschluss auf dem alten Pfad | nichts |
| Fehler (z. B. fehlendes Recht) | `cc_migration_failed {at, step, message}`, Backoff 15 min (außer CLI), ein Admin-Hinweis mit CLI-Befehl, Health `migration=false` (LB `Plugin.php:436-520`) | Ursache beheben, `migrate`; bleibt der Fehler: **Rollback §2.2** |
| Options-Lock-Fallback (kein `GET_LOCK`) | TTL 1 800 s, Übernahme toter Sperren (LB `Lock`) | – |

**Verboten:** Tabellen von Hand löschen, `cc_db_version` von Hand zurücksetzen, die Migration bei laufendem Read-only-Fenster eines anderen Backups starten.

### 2.2 Rollback

Keine Rückmigration (R0-06 §6.5; LB `release-regel.md` „Downgrade“; Pass). Entscheidungsbaum:

| Fall | Weg | Datenverlust |
|---|---|---|
| **R-A** gleiche Schemaversion (PATCH, MINOR ohne DB) | älteres ZIP einspielen („Ersetzen“) | keiner |
| **R-B** neueres Schema, nur Expand-Schritte, Code genau **eine** Minor älter | älteres ZIP einspielen; Admin-Hinweis „Schema neuer als Code“; keine Migration; Betrieb läuft, weil Code N das Schema N+1 lesen und schreiben kann (Expand/Contract R0-06 §6.2). **Nur wenn R16-D6 = a**, sonst wie R-C | keiner; Daten in neuen Spalten bleiben ungenutzt |
| **R-C** neueres Schema mit Contract-Schritt **oder** mehr als eine Minor | **Restore DB + Dateien des Backups von U-7 als zusammengehöriger Stand**, dann älteres ZIP | alles seit dem Backup |
| **R-D** Content-Fehler nach Import (kein Schema-Thema) | **kein Backup**: Import-Rollback aus der Paketkopie (neuer Import, Dry-run, Freigabe; R0-10 §3.6, INV-C-097) | keiner |

**Ablauf R-C:**

1. Read-only-Fenster (Stufe B) an; Shop-Adapter (R3) nimmt keine Bestellungen an.
2. **Delta sichern:** Grants seit dem Backup (`wp course-captain export grants --since=<backup-zeit>`), Zertifikate seit dem Backup und R3-Bestellungen exportieren (LB Livegang §4 Nr. 3).
3. DB und **alle drei Dateiklassen** aus demselben Backup-Satz zurückspielen. **Nie** DB aus Satz X mit Dateien aus Satz Y mischen und nie selektiv aus mehreren Sicherungen zusammenführen (Pass „Wiederherstellung“).
4. Älteres ZIP (Free und ggf. Pro) einspielen; `backup-check`.
5. Reconciliation (§2.3 Schritt 8), Delta nachtragen: Woo-Grants über den Adapter-Abgleich, manuelle Grants per Import, Zertifikate neu ausstellen (neue Revision, kein Versand ohne Bestätigung).
6. Lernende informieren, falls Lernstand oder Prüfungen verloren sind.

### 2.3 Restore (gleicher Ort, andere Domain, Kopie neben dem Original)

**Site-Identität (neu, Delta zu LB):** Course Captain speichert `cc_site_identity` = Hashes aus `home_url`, aufgelöster Privat-Basis, `DB_NAME|$wpdb->prefix` und `WP_ENVIRONMENT_TYPE`. Weicht der Ist-Wert ab, geht die Site in den **Haltemodus** (R16-D1):

- Mails von Course Captain werden nicht versendet, sondern als `held` protokolliert. Outbox-Dispatch und Connectoren pausieren; Inbox nimmt an, verarbeitet aber nicht.
- Cron läuft weiter (Ablauf von Grants und Prüfungen, Retention), seine ausgehenden Wirkungen laufen aber durch dieselben gesperrten Kanäle.
- Admin-Hinweis mit drei Wegen: „Restore am selben Ort bestätigen“, „Umzug bestätigen (neue Domain)“, „Kopie/Staging (Ausgang dauerhaft gesperrt)“. Erst danach wird die Identität neu geschrieben.

| Schritt | gleicher Ort | andere Domain/Server | Kopie neben dem Original (gleicher Server) |
|---|---|---|---|
| 1 | Read-only-Fenster am Ziel bzw. Wartungsseite (`wp-content/maintenance.php`, Vorlage R1-24, INV-C-027) | ebenso | ebenso |
| 2 | Prüfsummen prüfen (`sha256sum -c`), entschlüsseln | ebenso | ebenso |
| 3 | DB in **leere** DB (utf8mb4) | ebenso | **neue** DB, eigener Präfix erlaubt |
| 4 | Dateien: `uploads/`, privater Ordner an seinen Pfad, `wp-config.php` | ebenso; DB-Zugang anpassen | **`COURSE_CAPTAIN_PRIVATE_DIR` auf einen neuen Pfad** setzen und den privaten Ordner dorthin kopieren. Sonst teilen sich beide Installationen `protected`/`packages`, und Retention oder Import der Kopie löschen Dateien des Originals (LB T209). `DISABLE_WP_CRON` und `WP_ENVIRONMENT_TYPE=staging` setzen |
| 5 | – | `wp search-replace 'https://alt' 'https://neu' --precise --skip-columns=guid`. **Keine** Pfad-Ersetzung nötig (I-4) | Domain wie links; keine Pfad-Ersetzung |
| 6 | `wp course-captain backup-check` | ebenso | ebenso; `ok` außer „Identität weicht ab“ |
| 7 | Haltemodus bestätigen („gleicher Ort“) | „Umzug“ bestätigen; Pro-Lizenz meldet `site_changed` und will eine Reaktivierung (R0-36 L5) | „Kopie“ wählen; Pro-Lizenz **nicht** auf der Kopie aktivieren (verbraucht sonst eine Aktivierung) |
| 8 | **Reconciliation** (Konzept 22.2, läuft als Job, Bericht im Systemstatus): Grants gegen Quellen (Ablauf, R3-Bestellungen); `cc_exam_session` über Frist → regulärer Übergang `expired` ohne Benachrichtigung; `cc_import_batch` ohne Heartbeat → `aborted` (R0-10 §3.4); Outbox `dispatching` mit abgelaufener Sperre → `pending` (gleiche `event_uuid`, Empfänger deduplizieren); `cc_course_progress` neu aufbauen; Cron-Ereignisse neu einplanen (INV-C-012) | ebenso | ebenso; Ausgang bleibt gesperrt |
| 9 | **Nachhol-Grenze:** `held`-Mails und Outbox-Ereignisse mit `created_at` < Erkennungszeitpunkt prüft der Admin in einer Liste; Standard **verwerfen** (R16-D4). Zertifikate, Einladungen und Erinnerungen werden nie automatisch nachgeholt | ebenso | entfällt |
| 10 | Read-only-Fenster aus; Handprobe: Testkonto, „Mein Lernen“, geschützte Datei, Prüfung starten und abbrechen | ebenso | ebenso; Mail-Catcher (Mailpit) statt Versand |
| 11 | Löschungen seit dem Backup erneut ausführen (§1.3, R16-D8) | ebenso | – |

**Installations-UUID:** Eine Kopie behält die UUID des Originals. Ein Kurs-Export aus Staging wird auf Live damit als „eigen“ erkannt und aktualisiert dieselben Objekte (gewollt für den Weg Staging → Live, R0-10 §6.1). Eine neue UUID gibt es nur ausdrücklich (`wp course-captain identity --new`), z. B. wenn aus der Kopie eine eigenständige Site wird.

### 2.4 Teil-Restore eines Kurses

Ein Teil-Restore über einzelne Tabellen oder Zeilen ist **nicht unterstützt**: Fragen, Revisionen, Kursrevisionen, Medien und Lerndaten hängen über RESTRICT-Beziehungen zusammen (R0-06 §4, E-48).

**Weg über den Paket-Export:**

1. Backup in eine **temporäre Kopie** zurückspielen (§2.3, Spalte „Kopie“, Ausgang gesperrt).
2. In der Kopie den Kurs als `cc-package` exportieren (R0-10 §6.1: Module, Lektionen, Fragen in der veröffentlichten Revision, Medien, Blueprints; fremde Inhalte nur per Opt-in, E-66).
3. Auf Live importieren: Dry-run zeigt die Unterschiede. Weil die UUID gleich ist, trifft der Import dieselben Objekte. Für lokal geänderte Objekte gilt E-63 (lokal gewinnt); für einen Restore wählt der Autor im Dry-run „Quelle übernehmen“ für alle betroffenen Objekte (Sammelaktion, Audit).
4. Wirkung: bekannte Fragen-Hashes reaktivieren die alte Revision (INV-C-097/111), sonst entsteht eine neue Revision; eine neue `cc_course_revision`, wenn sich `structure_hash` ändert. Laufende Prüfungen behalten ihren Snapshot. **Lerndaten bleiben unberührt**: Sie sind nie im Paket (R0-10 §1.3).
5. Temporäre Kopie löschen (Personendaten).

**Grenzen:** Gelöschte Kurse mit Lerndaten können nicht endgültig gelöscht sein (R0-06 §4.9 verweigert das); sie liegen im Papierkorb bzw. Archiv und werden dort wiederhergestellt. Lerndaten oder Grants **eines einzelnen Nutzers** lassen sich nicht teilweise zurückspielen. Grants werden über Quelle bzw. manuellen Grant mit Audit neu gesetzt; Lernstände gehen nur per vollständigem Restore zurück (Nicht-Ziel R1).

### 2.5 Deaktivierung und Uninstall

| Vorgang | Wirkung |
|---|---|
| **Deaktivieren** (Free) | keine Datenlöschung; alle `cc_*`-Cron-Hooks und Action-Scheduler-Aktionen der Gruppe austragen; laufende Jobs bleiben in ihrem Zustand und werden bei Reaktivierung fortgesetzt (Heartbeat/Stale-Regeln). Rewrite-Regeln leeren. Geschützte Dateien sind ohne Plugin nicht erreichbar (Serverregel bzw. außerhalb des Webroots). |
| **Deaktivieren** (Pro) | Pro-Worker pausieren; Free unberührt (Konzept 22.2). |
| **Uninstall ohne Datenlöschung** (Standard) | Nur Cron-Hooks, Transients und Rewrite-Regeln entfernen. Tabellen, Optionen, Meta, CPTs, Dateien und Capabilities bleiben. Eine Neuinstallation migriert vom gespeicherten Schema aus weiter. |
| **Uninstall mit Datenlöschung** | Nur wenn **beides** gilt: Setting `delete_data_on_uninstall` = an (INV-C-070) **und** eine ausdrückliche Bestätigung beim Einschalten (Eingabe des Site-Namens, Backup-Hinweis, Audit-Eintrag mit Nutzer und Zeit; R16-D5). Konzept 22.2 verlangt „gesonderte, bestätigte Betreiberoption und Datenschutzprüfung“. |

**Ablauf mit Datenlöschung** (Muster LB `Uninstaller` INV-C-024/025, Course-Abweichungen fett):

1. Je Site (`switch_to_blog`), nur wenn die Site zugestimmt hat; netzweite Nutzer-Meta nur, wenn **keine** Site ihre Daten behält (INV-C-024; Pass „Multisite“).
2. Cron/Action Scheduler austragen.
3. Tabellen mit `FOREIGN_KEY_CHECKS=0`, Kindtabellen zuerst (INV-C-035).
4. Optionen, Transients, Nutzer-Meta, Post-Meta **nach exakten Namen aus dem Manifest**, keine `cc_%`-Wildcard (INV-C-026: schützt fremde Daten mit ähnlichem Präfix).
5. CPTs samt Revisionen, Meta und Terms; geschützte Anhänge **zurück in die Mediathek verschieben oder löschen** (Wahl im Bestätigungsdialog; Standard: löschen, weil sie zugangsgebunden waren).
6. Capabilities und Rollen.
7. Ordner: `uploads/course-captain/`, `protected-<geheim>/`, `packages-<geheim>/`. Ein per Konstante eingestellter privater **Basisordner** bleibt bestehen; gelöscht wird nur, was das Plugin darin angelegt hat. Konstanten in `wp-config.php` bleiben; ein Hinweis nennt sie.
8. Objekt-Cache leeren.

**Uninstall-Manifest (neu):** Jedes Modul (Core, Fragenbank, Prüfung, Gruppen, Nachweise, Integration, Pro, R3-Adapter) liefert eine Liste aus Tabellen, Optionen, Transient-Präfixen, Nutzer-Meta, Post-Meta (auch an **fremden** Objekten, z. B. Woo-Produkten im R3-Adapter), CPTs, Taxonomien, Capabilities, Cron-Hooks und Ordnern. **CI-Vollständigkeitstest:** Ein statischer Scan sammelt alle Schlüssel-Literale, die an `get_option/update_option/add_option`, `*_transient`, `*_user_meta`, `*_post_meta` und `wp_schedule_*` übergeben werden und mit `cc_`/`_cc_`/`course_captain_` beginnen. Jeder Schlüssel muss im Manifest stehen, sonst schlägt die CI fehl (REC-020). Dynamische Schlüssel sind nur über registrierte Präfixe erlaubt. Das schließt genau die LB-Lücke: `bp_entitlement_prune_last` (`Privacy/PersonalData.php:119`) und `_bp_catalog_fields` (`Commerce/ShopCatalog.php:301`) fehlen dort in `Uninstaller.php:41/:141` und bleiben nach dem Löschen liegen.

**Pro-Uninstall** löscht nur Pro-Daten (Lizenzoptionen des Clients, Pro-Tabellen/-Optionen laut Pro-Manifest), nie Free-Daten.

---

## 3. Staging und Deployment

### 3.1 Staging-Kopie ohne Nebenwirkungen

**Nebenwirkungen einer Kopie mit echten Daten** und ihre Sperre:

| Kanal | Risiko | Sperre |
|---|---|---|
| Mails (Erinnerungen, Einladungen, Zertifikate, Passwort-Reset) | echte Lernende bekommen Mails aus Staging | Haltemodus (§2.3) bzw. Ausgangsmodus `catch` (Mail-Catcher) oder `off`; zusätzlich das MU-Plugin-Muster aus LB `staging/mu-plugins/staging-mail.php` (alle Mails an Mailpit, Absender `staging@example.invalid`, INV-E-083) |
| Outbox → Suite-Partner (Workflow, Review, Affiliate) | doppelte Ereignisse, doppelte Provisionen | Outbox-Dispatch pausiert; Connectoren aus |
| Inbox / Zahlungs-Webhooks (R3) | Live-Anbieter ruft Staging auf bzw. umgekehrt | Webhook-Endpunkte auf Staging lehnen ab (503); Sandbox-Zugangsdaten (LB §5 Schritt 7) |
| Cron-Abläufe | Grant-Ablauf erzeugt Events und Mails | Cron läuft (nötig für Tests), Ausgang aber gesperrt |
| Pro-Lizenz | Aktivierung verbraucht ein Kontingent bzw. erzeugt `site_changed` | Lizenz auf Staging nicht aktivieren oder Staging-Lizenz (R0-39) |
| Suchmaschinen | Duplicate Content, Indexierung von Lerninhalten | `blog_public 0`, `X-Robots-Tag: noindex, nofollow` (LB `setup.sh:27`, `Caddyfile`, INV-E-084) |
| Geteilte Ordner | Kopie löscht Dateien des Originals | eigener `COURSE_CAPTAIN_PRIVATE_DIR` (§2.3 Schritt 4) |

**Steuerung:** Konstante `COURSE_CAPTAIN_OUTBOUND` = `on | catch | off`. Ohne Konstante gilt: `on` bei `wp_get_environment_type() === 'production'`, sonst `catch` (R16-D2). Der Haltemodus (§2.3) setzt sich unabhängig davon durch. Systemstatus und Admin-Leiste zeigen den Modus dauerhaft an.

**Staging-/Abnahmeumgebung (Annahme E-13, Muster LB `staging/`, INV-E-081…086):** `docker compose` mit `db` (MariaDB/MySQL laut E-58), `wordpress`, idempotentem `setup` per WP-CLI (Installation, `blog_public 0`, Plugin aktivieren, `migrate`, **neutrales Demo-Paket** importieren, Demo-Konto), `cron`-Dienst (`wp cron event run --due-now` jede Minute, `DISABLE_WP_CRON`), `mailpit` (nur an `127.0.0.1`), optional `caddy` (HTTPS, noindex). Kein Pflicht-Woo; ein Woo-Profil kommt für R3. Geheimnisse nur in einer nicht eingecheckten `.env` (INV-E-085). Der CI-Job „staging“ prüft Start, erreichbare Seiten, wiederholbares Setup und PHP-Fehlerfreiheit (INV-E-093). `WP_ENVIRONMENT_TYPE=staging` wird im Compose gesetzt.

### 3.2 Secret-Handling

**Inventar (Course Captain, Vorschlag):**

| Geheimnis | Ort (bevorzugt → Rückfall) | im Backup? | nach Restore/Kopie |
|---|---|---|---|
| WP-Salts/Keys | `wp-config.php` | ja (Datei) | gleich lassen, sonst sind Sitzungen ungültig |
| Integrations-Zugangsdaten (Connector-API-Keys, Webhook-HMAC), später Zahlungsanbieter (R3) | Konstante `COURSE_CAPTAIN_<CONNECTOR>_*` → DB **verschlüsselt** (`cc_integrations`, R0-06 E-44) | DB-Wert nur als Chiffrat | Kopie: als „nicht bestätigt“ markiert, bis ein Admin sie neu eingibt oder bestätigt |
| Schlüssel für DB-Geheimnisse | Konstante `COURSE_CAPTAIN_SECRET_KEY` (32 Byte, `sodium_crypto_secretbox`) → Rückfall: aus `AUTH_KEY`/`AUTH_SALT` abgeleitet, mit Health-Warnung (Salt-Rotation macht die Werte unlesbar) | ja (Datei), **getrennt** vom DB-Dump aufbewahren | fehlt der Schlüssel: Integrationen „nicht konfiguriert“, kein Fatal (REC-022) |
| Monitor-Schlüssel | Konstante `COURSE_CAPTAIN_MONITOR_KEY` (LB `BOOTSPRUEFUNG_MONITOR_KEY`) | Datei | gleich |
| Pro-Lizenzschlüssel | License-Client-`OptionStore` (R0-36 L2) | DB | `site_changed` → Reaktivierung (R0-36 L5) |
| Ordner-Geheimnis `cc_storage_secret` | Option (Verteidigung in der Tiefe, kein Zugriffsschutz, R0-10 §4.1) | **muss** in der DB sein | nie still neu erzeugen (REC-005) |
| Token (Claim, Zertifikat-Verify, Einladungen) | nur als Hash in der DB (R0-06 E-37, E-39) | Hash | gültig, solange nicht abgelaufen |

**Regeln:**

- **Kein Klartext-Geheimnis in Ausgaben:** Settings-Export/-Import (Felder mit `secret: true` im Settings-Schema werden als `"<gesetzt>"` exportiert und beim Import nicht übernommen), `cc-package`-Export (enthält ohnehin keine Settings, R0-10 §1.3), Site-Health-„Info“ (Debug-Daten), Systemstatus, `backup-check --format=json`, Audit- und Fehlerlogs, REST-Antworten. Angezeigt werden nur „gesetzt / nicht gesetzt / aus Konstante“ und die letzten 4 Zeichen.
- Konstanten haben Vorrang vor DB-Werten; das UI zeigt „über `wp-config.php` festgelegt“ und sperrt das Feld (LB-Empfehlung `BOOTSPRUEFUNG_PAYPAL_*`, `docs/backup-restore.md` §1 T209).
- Die LB-Klartextablage von Live-Zugangsdaten in einer Option (`bp_learning_disputes`) wird **nicht** übernommen.
- Staging und Live haben **verschiedene** Integrationsgeheimnisse; das Setup-Skript erzeugt Staging-Werte selbst.
- CI-Guard: Kein Geheimnis-Feldname taucht in den Exportern auf (Test REC-022); Secret-Scanning im Repository (R0-18).

### 3.3 Release-Pfad Free (WordPress.org, Annahme E-09/E-13)

Details zur Distribution in R0-35; hier die betriebsrelevanten Gates.

1. **Version:** SemVer; PATCH ohne DB-Änderung, MINOR mit Schemaänderung, MAJOR bei Vertragsbruch (LB `release-regel.md`). Gleich sein müssen: Plugin-Kopf `Version`, `readme.txt` `Stable tag`, Konstante, CHANGELOG. Getrennt geführt werden Core, Pro, `cc_db_version`, Paketformat, REST und Connectorvertrag (Konzept 22.2).
2. **Changelog/Upgrade Notice:** Pflichtmarker `Schema`, `Backfill`, `Destruktiv (Contract) – Backup vorher`, `Sicherheitsupdate:` (LB T117, Text ohne Exploit-Anleitung).
3. **Build:** `git archive` eines Commits, reproduzierbar (Zeitstempel = Commit-Zeit, sortiert), Entwicklerdateien raus, Negativprüfung inkl. Legacy-Namen (`learnboating`, `bootspruefung`, `bp_`), `SHA256SUMS` (INV-E-043/044). Minifiziertes JS nur mit lesbarer Quelle im Paket (WordPress.org-Richtlinie).
4. **CI-Gates:** alle Jobs grün; **Artefakttest** (ZIP installieren statt Repo-Ordner, INV-E-090); **Upgrade-Probe** von der Vorversion (letzter Tag, Lerndaten anlegen, ZIP ohne Reaktivierung darüber, Migration, Zeilenzahlen gleich; Muster `tools/release/upgrade-check.php`, INV-E-045); Uninstall-Manifest-Test (REC-020).
5. **Freigabe:** Tag `v<version>` auf `main` → Release-Workflow prüft „CI für diesen Commit grün“ und „Tag = Version“ (INV-E-096) → **manuelle Freigabe** (geschützte CI-Umgebung) → SVN-Deploy: `trunk` und `tags/<version>` sowie `assets/`; `Stable tag` wird **zuletzt** umgestellt.
6. **Keine eigene Update-Quelle** im Free-Plugin: kein `Update URI`, keine GitHub-Abfrage, kein Lizenzcode (R0-36 A8/L1; LB INV-C-066 entfällt).
7. **Release zurückziehen:** Bereits aktualisierte Sites behalten die neue Version, deshalb nur vorwärts mit PATCH korrigieren. `Stable tag` darf **nie** auf eine Version mit älterem Schema zurückgesetzt werden, solange Sites schon migriert sind (sonst R-B/R-C ungeplant auf vielen Sites); nur wenn das Schema gleich ist (Fall R-A).
8. **Sicherheitsfix:** private Meldung, PATCH nur mit Korrektur, beschleunigter Ablauf, danach Veröffentlichung des Advisory (LB `release-regel.md` T117).

### 3.4 Release-Pfad Pro (License-Captain-Client, Annahme E-09)

- Pro ist ein eigenes Plugin mit vendorter Client-Kopie im eigenen Namespace (z. B. `CourseCaptainPro\Licensing`, R0-36 L2); Updates über `pre_set_site_transient_update_plugins`/`plugins_api` **nur in Pro**.
- `core_version` = installierte Free-Version; der Server hält Releases zurück, deren `requires_core` höher ist (R0-36 L4). Damit gilt die Reihenfolge Free → Pro technisch.
- Pro-Schema über Moduldefinitions-Hash in `cc_db_version` (R0-06 §6.1); gleicher Migrationsvertrag, gleicher Lock.
- Lizenzablauf schaltet nur Pro-Funktionen ab, nie Free-Verhalten oder Daten (R0-36 L5).
- Gates wie Free (Build, Artefakt, Upgrade-Probe) plus Paket-Signatur/Prüfsumme nach License-Captain-Vertrag. **Externer Blocker:** Die Release-Readiness-Gates von License Captain sind alle offen (R0-36 L12), `captain-pro` ist nicht geprüft (E-42) → R3-18.

---

## 4. Recovery-Tests (REC-xxx)

Umgebung: Staging-Compose (§3.1) mit Lastprofil `default` (E-52) für REC-001, sonst kleiner synthetischer Stand (E-02). „Gleich“ heißt: Zeilenzahl je `cc_*`-Tabelle und SHA-256 aller Mediendateien wie im Original (LB-Übung §7). Ziel-Tasks: **R1-23** (Restore/Uninstall/Upgrade-Proben), **R1-40** (Migration unter Lock, Crash, Retry).

| ID | Szenario | Aufbau | Erwartung | Ziel | Quelle |
|---|---|---|---|---|---|
| REC-001 | **Restore-Probe** gleicher Ort | Stufe-A-Backup, DB leeren, Ordner löschen, Restore | `backup-check ok`; Daten gleich; Handprobe (§2.3 Nr. 10) grün; RTO gemessen und protokolliert | R1-23 | LB §7, INV-C-060 |
| REC-002 | Restore auf andere Domain und anderen Pfad | Restore auf zweiten Host, nur Domain-`search-replace` | keine Pfad-Ersetzung nötig; geschützte Medien laden über `/cc-file/…`; `get_attached_file` zeigt neue Basis | R1-23 | LB §5 Schritt 5 |
| REC-003 | Kopie neben dem Original | gleiche Maschine, eigener `PRIVATE_DIR` | Original-Ordner unverändert (Prüfsummen vorher/nachher); Kopie im Haltemodus; 0 Mails verschickt | R1-23 | LB T209 |
| REC-004 | Backup ohne privaten Ordner | Backup-Plugin-Simulation schließt Pfad außerhalb `ABSPATH` aus | `fail` „Mediendateien fehlen“ mit Liste; kein stilles Neuanlegen leerer Ordner; Auslieferung 404, kein 500 | R1-23 | LB §1 „Achtung“, T204 |
| REC-005 | Ordner-Geheimnis fehlt | Restore ohne `cc_storage_secret` | `fail`; kein neues Geheimnis ohne ausdrückliche Reparaturaktion | R1-23 | LB §1 „Wichtig“ |
| REC-006 | **Unterbrochenes Upgrade** | `kill -9` (a) in `dbDelta`, (b) in Schritt `NNNN`, (c) im Backfill | Lock frei; Wiederaufnahme; jeder Schritt genau einmal in `cc_migrations_applied`; Backfill ab Cursor; Daten gleich | R1-40 | R0-06 §6.3/6.4 |
| REC-007 | Parallele Upgrade-Trigger | Admin, Cron, CLI und 50 Frontend-Requests gleichzeitig | genau ein Lauf; die übrigen überspringen; Frontend plant `cc_run_upgrade` höchstens einmal | R1-40 | Pass `OPERATIONS.md`, R0-36 §2.2 |
| REC-008 | Migration schlägt fehl | DB-Nutzer ohne `ALTER` | `cc_migration_failed`, Backoff 15 min, ein Hinweis; schreibend 503, lesend 200; CLI versucht sofort; nach Rechtevergabe Erfolg | R1-40 | LB `Plugin.php:436-520` |
| REC-009 | Rollback R-C | Upgrade mit Contract-Schritt, dann Restore DB+Dateien + altes ZIP | `backup-check ok`, kein „Schema neuer“; Gegenprobe: nur altes ZIP ohne Restore → Hinweis, keine Aktion, keine Datenänderung | R1-23 | R0-06 §6.5 |
| REC-010 | **Restore während laufender Prüfungen** | Backup bei 20 Sitzungen `in_progress`; Restore nach Fristende; Client sendet gepufferte Antworten | Sitzungen über Frist → `expired` über den regulären Automaten, keine Mail; veraltete Schreibversuche → sauberer Konflikt (CAS/`start_token`, 409/410), kein 500, keine Doppelwertung | R1-23 | R0-06 §4.8 |
| REC-011 | Prüfung läuft während Migration bzw. Read-only-Fenster | Prüfung starten, Update einspielen | Antworten 503 + `Retry-After`, Client puffert; nach Migration gehen Antworten und Abgabe durch; Fristbehandlung nach R0-08 | R1-40 | LB `Maintenance` |
| REC-012 | **Versatz: Dateien neuer als DB** | DB-Dump, danach Import mit neuen Medien, dann Dateikopie | Restore ok; unreferenzierte Dateien `warn`; nichts automatisch gelöscht (Karenz I-3) | R1-23 | I-1…I-3 |
| REC-013 | **Versatz: DB neuer als Dateien** | Dateien aus älterem Satz als DB | `fail` mit `media_uid`/`sha256`-Liste; Auslieferung 404; Reparatur durch Reimport des Pakets (Dedupe über SHA-256) | R1-23 | INV-C-063 |
| REC-014 | Import während Backup | Dump während `importing` bzw. `activating` | nach Restore: Batch ohne Heartbeat → `aborted`; Lernende sehen vollständig alt oder neu (I-5); `resume`/`discard` möglich | R1-23 (R1-15) | R0-10 §3.4 |
| REC-015 | Retention während Backup | Referenz entfällt zwischen DB-Dump und Dateikopie | Datei ist im Datei-Backup enthalten | R1-23 | I-3 |
| REC-016 | **Keine Doppelwirkung nach Restore** | Backup vor Versand von Zertifikat, Einladung, Erinnerung; nach Versand Restore | Haltemodus; nichts wird automatisch nachgesendet; Outbox-Ereignisse behalten `event_uuid` (Empfänger dedupliziert) | R1-23 | Konzept 22.2 |
| REC-017 | Teil-Restore eines Kurses | Kurs ändern; aus Restore-Kopie exportieren; auf Live importieren | alte Fragenrevisionen reaktiviert; neue Kursrevision; Lerndaten und laufende Prüfungssnapshots unverändert | R1-23 (R1-15) | §2.4, INV-C-097 |
| REC-018 | Uninstall ohne Datenlöschung | deinstallieren, neu installieren | alle Daten vorhanden; Cron neu eingeplant; keine Migration doppelt | R1-23 | INV-C-024 |
| REC-019 | Uninstall mit Datenlöschung | Bestätigung erteilt; Gegenprobe mit fremden `cc-`/`course_`-ähnlichen Namen | keine Reste laut Manifest; fremde Daten bleiben; Basisordner aus Konstante bleibt; Multisite nur zustimmende Site | R1-23 | INV-C-190, INV-E-103 |
| REC-020 | Manifest-Vollständigkeit | CI-Scan der Schlüssel-Literale | jeder Schlüssel im Manifest; Negativtest mit absichtlich vergessener Option schlägt fehl | R1-23 | LB-Lücke INV-C-026 |
| REC-021 | Staging ohne Nebenwirkungen | `WP_ENVIRONMENT_TYPE=staging`, echte Daten, Cron eine Stunde | 0 externe Mails (alle im Catcher), Outbox pausiert, Webhooks 503, Pro nicht aktiviert | R1-23 | INV-E-083 |
| REC-022 | Secrets | Settings-Export, Site-Health-Info, Systemstatus, JSON-Check, Logs; Restore ohne `COURSE_CAPTAIN_SECRET_KEY` | kein Klartextwert in allen Ausgaben; ohne Schlüssel „nicht konfiguriert“, kein Fatal | R1-23 (R0-17) | §3.2 |
| REC-023 | Upgrade vom Release-Artefakt | Vorversion (letzter Tag) mit Lerndaten, neues ZIP ohne Reaktivierung | Migration ok, FKs vollständig, Zeilenzahlen ≥ vorher | R1-23 (R1-39) | INV-E-045 |
| REC-024 | Multisite: Restore einer Site | zwei Sites, eine zurückspielen | andere Site unverändert; Locks und Identität je Site | R1-23 | E-49, R0-06 §6.6 |
| REC-025 | Downgrade R-B | Code N auf Schema N+1 (nur Expand) | Hinweis; Lesen/Schreiben fehlerfrei; erneutes Upgrade ohne doppelte Schritte. Nur bei R16-D6 = a, sonst Erwartung „Betrieb gesperrt mit Hinweis“ | R1-23 (R1-40) | R0-06 §6.2 |

---

## 5. Übergaben

| An | Inhalt |
|---|---|
| R0-37 | Abbruchtabelle §2.1, Contract-Selbstprüfung U-3, Downgrade-Regel R-B (R16-D6), Read-only-Fenster als gemeinsamer Mechanismus mit der Migrations-503 |
| R0-08 | Fristbehandlung von Prüfungen bei Read-only-Fenster/Migration (REC-011) und nach Restore (REC-010) |
| R0-10 / R1-14 / R1-15 | Invarianten I-1…I-5 (insbesondere relative Pfade und 7-Tage-Karenz der Retention) |
| R0-17 / R0-31 | Secret-Inventar §3.2, Backup-Höchstalter, Löschungen nach Restore (R16-D8) |
| R0-19 | CLI-Namen `maintenance`, `preflight`, `backup-check`, `identity`, `export grants` |
| R0-35 / R0-39 | Release-Gates §3.3/§3.4, `Stable tag`-Regel, Pro-`core_version` |
| R1-24 | Betreiber-Runbooks (Backup, Upgrade, Restore, Uninstall), Wartungs-/Fehlerseiten-Vorlage, Livegang-Drehbuch (LB-Struktur ohne Shop-Fachpunkte) |
| R1-38 | Systemstatus „Datensicherung“, „Update-Bereitschaft“, Haltemodus-Anzeige, Health-Zeilen aus §1.4 |

---

## 6. Entscheidungsbedarf (nur neue Punkte)

Bereits in `ENTSCHEIDUNGEN.md` und hier als Annahme verwendet: E-09, E-13 (außerdem abhängig von E-07, E-08, E-42, E-44, E-49, E-51, E-52, E-57, E-58, E-63, E-65, E-66).

Technische Festlegungen ohne Auftraggeber-Entscheidung (Architektur-Entwurf hier): relative Pfade (I-4), Karenz vor dem Löschen (I-3), Uninstall-Manifest mit CI-Test, Secret-Verschlüsselung mit eigener Konstante, Nachhol-Grenze als Mechanismus.

| ID | Frage | Optionen | Empfehlung | Entscheider | blockiert |
|---|---|---|---|---|---|
| **R16-D1** | Soll Course Captain eine geänderte Site-Identität (Restore, Umzug, Kopie) erkennen und den Ausgang automatisch anhalten? | a) automatisch Haltemodus bis zur Admin-Bestätigung · b) nur Hinweis, Versand läuft weiter · c) nichts (LB) | **a)**. Nur so lassen sich doppelte Mails, Zertifikate und Partner-Ereignisse nach einem Restore (Konzept 22.2) und Mails aus Staging-Kopien zuverlässig verhindern. Kosten: ein zusätzlicher Admin-Klick nach jedem Umzug | Auftraggeber + Architektur | R1-17, R1-21, R1-23, R1-38 |
| **R16-D2** | Standard-Ausgangsmodus außerhalb von `production` (`WP_ENVIRONMENT_TYPE`) | a) `catch`: Mails an den Admin bzw. Mail-Catcher, Outbox und Connectoren pausiert · b) `off`: nichts versenden · c) `on` wie Produktion | **a)**. Die Abnahme braucht sichtbare Mails, aber keine an echte Empfänger. Überschreibbar per `COURSE_CAPTAIN_OUTBOUND` | Auftraggeber | R1-21, R1-23 |
| **R16-D3** | Ablage von Integrationsgeheimnissen | a) Konstante bevorzugt, sonst DB **verschlüsselt** mit `COURSE_CAPTAIN_SECRET_KEY` (Rückfall Salt-Ableitung mit Warnung) · b) Klartext in Optionen (LB) · c) nur Konstanten | **a)**. Ein DB-Dump allein verrät keine Zugangsdaten; c) wäre für Shared Hosting zu umständlich | Architektur (mit R0-17) | R0-17, R0-21, R1-17, R3-02 |
| **R16-D4** | Was passiert nach einem Restore mit zurückgehaltenen Mails und Ereignissen, die älter als der Erkennungszeitpunkt sind? | a) Admin-Liste, Standard **verwerfen** · b) Standard senden · c) immer verwerfen ohne Liste | **a)**. Zertifikate, Einladungen und Erinnerungen werden nie automatisch nachgeholt; Partner-Ereignisse können gezielt freigegeben werden | Auftraggeber | R1-21, R1-23 |
| **R16-D5** | Form der Bestätigung für „Daten bei Deinstallation löschen“ | a) Setting + Eingabe des Site-Namens + Backup-Hinweis + Audit · b) nur Setting (LB) · c) zusätzlich Löschung nur per WP-CLI | **a)**. Konzept 22.2 verlangt eine „gesonderte, bestätigte“ Option; c) schließt Betreiber ohne Shell aus | Auftraggeber | R1-23 |
| **R16-D6** | Downgrade ohne Restore um **eine** Minor-Version innerhalb des Expand-Fensters offiziell unterstützen? | a) ja, getestet (REC-025), nur ohne Contract-Schritt · b) nein, jeder Downgrade mit neuerem Schema braucht Restore (LB: „nicht getestet“) | **a)**. Expand/Contract (R0-06 §6.2) garantiert ohnehin, dass Code N das Schema N+1 liest; das erspart Datenverlust bei Fehlern in neuem Code | Auftraggeber + Architektur | R0-37, R1-40 |
| **R16-D7** | RPO/RTO-Richtwerte in der Betreiberdoku | a) RPO ≤ 24 h, RTO ≤ 4 h als Empfehlung, Messung in REC-001 · b) keine Werte nennen | **a)** als Empfehlung, nicht als Produktzusage; Werkzeug und Hoster wählt der Betreiber | Auftraggeber | R1-24 |
| **R16-D8** | Löschanträge nach einem Restore aus älterem Stand | a) nur dokumentieren: Betreiber führt Löschungen extern und wiederholt sie nach dem Restore; Course Captain zeigt dazu einen Hinweis · b) Course Captain schreibt ein Löschjournal (Nutzer-ID + E-Mail-Hash) zusätzlich in eine Datei im privaten Ordner, die beim Restore nicht überschrieben werden soll · c) nichts | **a)**. Ein Journal in derselben Sicherung würde mit zurückgerollt; b) ist fehleranfällig und selbst personenbezogen | Auftraggeber (mit Datenschutz) | R0-31, R1-17, R1-24 |

---

## 7. Nachweis

- LB (`9f37c77`, nur gelesen): `docs/backup-restore.md` §1–§7 (Tabellen-/Datei-Umfang, absolute Pfade, T209, Übung 13.11.2026), `docs/release-regel.md` (Versionen, Ablauf, Downgrade, T117), `docs/installation-und-updates.md` („Updates einspielen“ Schritte 1–7, CI-Job „Update von der Vorversion“), `docs/livegang-drehbuch.md` §3–§4 (T−2 h, Rückfallplan), `staging/{docker-compose.yml:18,53,88-111, setup.sh:27, mu-plugins/staging-mail.php, .env.example}`, `tools/release/upgrade-check.php:8-12,52-86`, `uninstall.php`, `src/Plugin.php:344-520` (`maybeMigrate`, `migrationAction`, Backoff 15 min), `src/Infrastructure/Maintenance.php` (503, `RETRY_AFTER = 30`), `src/Infrastructure/BackupCheck.php:32-306` (Prüffunktionen), `src/Infrastructure/Uninstaller.php:41-228`.
- LB-Befund Uninstall: `grep -n prune_last src` → `Privacy/PersonalData.php:119` (`ENTITLEMENT_PRUNE_OPTION = 'bp_entitlement_prune_last'`); `grep -n _bp_catalog_fields src` → `Commerce/ShopCatalog.php:301`; beide fehlen in den Löschlisten `Uninstaller.php:41`/`:141` (Inventur C INV-C-026, §3.3).
- Pass Captain (`b2b88ea`, nur gelesen): `docs/OPERATIONS.md:3-16` (Upgrade, Lock, Fingerprint, 5-min-Übernahme), `:43-47` (Restore als zusammengehöriger Stand, keine selektive Zusammenführung), `:51` (Release-Kandidat).
- Keine Änderung an LearnBoating oder den Suite-Klonen, keine Implementierung, kein Commit.
