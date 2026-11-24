# SET-CC – Slice „gruppen-audit“ (Nachweis)

Stand 24.11.2026, Branch `r4-d` (Basis 69cac5d). Umsetzung der Inhaberentscheidungen D11 und D12 vom 24.11.2026. Haken setzt der Koordinator.

Tests: `tests/integration/GroupAuditTest.php` (Gruppe `SET-CC-gruppen-audit`, 6 Tests), angepasst: `SchemaTest` (neuer Migrationsschritt) sowie sieben Tests, die bisher `actor_user_id`/`subject_user_id` des Audit-Logs lasen (lesen jetzt über `AuditIdentity`): `GoldenMasterImportTest`, `RecoveryTest`, `GoldenMasterG3Test`, `GoldenMasterGapsTest`, `BackupCheckH3Test`, `GoldenMasterLearningTest`, `UninstallProbeTest`.

## D11 · Beendete Gruppen wiedereinsetzen

- **Gebaut:** `GroupService::reinstate( Actor, group_id, reason )` nach R0-09 §4.8 (E-29, G-15):
  - nur Admins: `ccap_manage_groups` **und** `manage_options`, sonst `ccap_forbidden` (Kursmanager mit Gruppenrecht können nicht);
  - Begründung Pflicht (`ccap_reason_required`), unbekannte Gruppe `ccap_not_found`, nicht beendete Gruppe `ccap_conflict`;
  - unter dem Gruppen-Lock L3 (`group-seats:<id>`) wird der Status erneut gelesen (keine doppelte Revision bei parallelen Klicks), dann in einer TX: CAS auf `row_version`, `status = active`, `source_revision + 1`, Outbox `group.reinstated`;
  - danach für jeden Seat-Grant der alten Revision, der mit `group_revoked` endete und dessen Platz noch `active` ist, eine neue Grant-Revision über `GrantService::reactivate()` (Grund `reinstated`). `reactivate()` nimmt den Ketten-Lock L4 selbst: Reihenfolge L3 → L4 → DB-TX, kein Lock in offener TX (R0-09 §6.1);
  - Gültigkeit: ein Grant kommt nur zurück, solange er noch gültig ist – eigenes Ende bei `valid_until_locked`, sonst das Ende der Gruppe; abgelaufene werden gezählt (`skipped`) und bleiben beendet. Entfernte Plätze bleiben draußen; offene Einladungen gelten bis zu ihrer Frist wieder (Tokens wurden nie gelöscht, §4.7). Neue Beitritte bekommen die neue Revision;
  - Audit `group.reinstated` mit Begründung und Kontext `{revision, grants, skipped}`, registriert in `AuditView::registry()`.
- **Oberfläche:** Gruppen-Detail (`Admin\GroupsScreen`) zeigt bei beendeten Gruppen für Admins den Abschnitt „Reinstate the group“ mit Hinweistext (`aria-describedby`), Pflichtfeld „Reason for reinstating (required)“ und Knopf; admin-post `op=reinstate` mit eigener Nonce, Ergebnis über die vorhandene Meldung („Saved. Affected entries: n“ = erneuerte Zugänge) bzw. die Fehlertexte. Ohne JS. DOM-Prüfung mit `A11yDom::fragment_problems()` im Test.
- **Tests:** `test_D11_reinstate_ended_group` (Rechte, Nonce, Begründung, Revision 2, neue Grant-Revision nur für das aktive Mitglied, abgelaufenes Einzelende und entfernter Platz bleiben beendet, Audit, Outbox, späterer Beitritt mit Revision 2, Kette prüft), `test_D11_reinstate_after_validity_end` (Gruppe abgelaufen → Gruppe aktiv, kein Zugang zurück; zweite Wiedereinsetzung `ccap_conflict`; unbekannte Gruppe `ccap_not_found`).
- **Offen:** Teilfehler (Gruppe schon aktiv, ein `reactivate()` scheitert z. B. an `ccap_group_busy`/Deadlock) lässt sich nicht erneut über die Aktion nachholen; einzelne Zugänge dann manuell über die Zugangsverwaltung reaktivieren. Kein REST-Endpunkt (nur Verwaltungsoberfläche).

## D12 · Audit-Hash-Kette mit Pseudonymisierung (RT-23)

### Ausgangslage

`Event\AuditLog::write()` hashte `sha256(previous | json(row))` über `actor_user_id`, `subject_user_id`, `object_id`, `context` usw. Personenbezüge (Nutzer-IDs, `user`-Objekte, `owner`/`previous_owner` im Kontext, `user:<ID>` in `cache_flushed`) lagen also in der Kette; ein Eraser hätte sie nur um den Preis einer gebrochenen Kette entfernen können. Der Eraser ließ das Audit-Log deshalb unverändert.

### Design

- **Pseudonym:** `A-` + 16 Hex-Zeichen **zufällig** (nicht aus der ID abgeleitet, anders als das Export-Pseudonym `P-…` aus SET-CC-26), je Nutzer beim ersten Audit-Eintrag erzeugt (`AuditIdentity::ref_for()`, `INSERT IGNORE` + Nachlesen, Unique auf `user_id` und `pseudonym`).
- **Zuordnung:** einzige Verbindung Pseudonym ↔ Konto ist die neue Tabelle `ccap_audit_identity (id, pseudonym, user_id, created_at)`. Eine vorhandene Tabelle passte nicht (keine hat eine Zeile je Person ohne weitere Personendaten; eine Option skaliert nicht und liegt im Autoload-Pfad). Klassifiziert als `personal`, in `Schema::personal_data_tables()`, über `Schema::drop_order()` automatisch im `UninstallManifest`.
- **Audit-Zeile:** neue Spalten `actor_ref`, `subject_ref` (`varchar(24) NULL`) und `chain_epoch` (`smallint unsigned NOT NULL DEFAULT 0`) mit Indizes; `actor_user_id`/`subject_user_id` bleiben (Expand-Phase nach R0-37 I-M2/§4.7, kein DROP), werden aber nicht mehr beschrieben. Ersetzt werden zusätzlich: `object_id` bei `object_type = user`, Werte der Kontextschlüssel `AuditIdentity::PERSON_KEYS` (`user`, `user_id`, `owner`, `previous_owner`, …, beliebige Tiefe) und `user:<ID>`-Token in `object_id` und Kontext-Strings.
- **Hashformat (Epoche 2, aktuell):** `row_hash = sha256(prev | json({actor_ref, actor_type, subject_ref, action, object_type, object_id, reason, context, created_at, chain_epoch}))`, alle Werte als String oder `null` (identisch beim Schreiben und beim Nachlesen aus der DB).
- **Epochen:** 0 = Altzeilen (Hash über IDs), 1 = neu versiegelte Altzeilen, 2 = neue Zeilen. Jede Epoche ist eine eigene Kette in ID-Reihenfolge; neue Zeilen hängen nie an Altzeilen an, damit das Online-Backfill (W2) nicht mit laufenden Schreibzugriffen kollidiert. Start einer Epoche: `''` oder nach Aufbewahrung der Anker aus dem neuesten `audit_pruned`-Eintrag. `RetentionService::prune_audit()` schreibt dafür zusätzlich `anchors{<epoche>: hash der letzten entfernten Zeile}`; `anchor` bleibt (Altformat, gilt für Epoche 0). Werden alle Zeilen der aktuellen Epoche entfernt, setzt der Zusammenfassungseintrag selbst an seinem Anker an.
- **Löschung (Eraser):** `PrivacyService::erase_user()` ruft `AuditIdentity::erase()`: noch nicht neu versiegelte Altzeilen der Person werden sofort pseudonymisiert (ihre IDs verlassen die Tabelle; ihre IDs werden in `course_captain_audit_reseal.erased` vermerkt, damit die Altprüfung sie zählt statt prüft), danach wird die Zuordnungszeile gelöscht. Die Einträge bleiben, ihre Hashes bleiben gültig, das Pseudonym ist nicht mehr auflösbar (Audit-Ansicht zeigt „Deleted user“, Filter nach der Person findet nichts mehr). Die Löschung selbst wird wie bisher ohne ID auditiert. Löscht dieselbe (weiter bestehende) Person später erneut etwas aus, bekommt sie ein neues Pseudonym.
- **Audit-Ansicht:** `AuditView` löst Akteur und Personenfilter über die Zuordnung auf (`users_of()` gebündelt je Seite), mit Rückfall auf die Altspalten für noch nicht versiegelte Zeilen.

### Migration bestehender Einträge (Kettenepoche)

- Strukturschritt: `dbDelta` (P2, nur additiv: neue Spalten nullable/mit Default, neue Tabelle, Indizes).
- Datenschritt: Backfill `core:0005_audit_pseudonym_reseal` (`Event\AuditResealBackfill`, in `Migrator::core_backfills()`), `pending_where = chain_epoch = 0` (natürliche Idempotenz). Je Batch in der Batch-TX und ID-Reihenfolge:
  1. Altprüfung: Legacy-Hash gegen die alte Kette (der vorige alte Hash wird über Batches in der Option `course_captain_audit_reseal` geführt; Start = Altanker aus `audit_pruned` oder `''`);
  2. Pseudonymisieren (`AuditIdentity::pseudonymise()`), IDs auf `NULL`;
  3. neu hashen als Epoche 1 (vorher = letzte versiegelte Zeile).
- Ist keine Altzeile mehr übrig, schreibt der letzte Batch genau einmal den auditierten Marker **`audit_chain_resealed`** (Epoche 2, in `AuditView::registry()`): `{epoch: 1, step, rows, first_id, last_id, legacy_verified, legacy_broken, first_broken_id, erased_before, legacy_head, resealed_head}`. `resealed_head` bindet die neu versiegelte Historie in die laufende Kette; `legacy_head` ist der letzte alte Hash (Abgleich mit Sicherungen möglich). Wurde eine Altzeile vor dem Neuversiegeln verändert, steht das dauerhaft im Marker (`legacy_verified: false`, erste betroffene ID).
- Option `course_captain_audit_reseal` (Status des Neuversiegelns, nur IDs/Hashes/Zähler) steht im `UninstallManifest`.
- Frische Installationen haben keine Altzeilen: der Schritt ist sofort `done`, kein Marker.

### Prüfwerkzeug

- `Event\AuditChain::verify( Database )` (nur lesend, batchweise je 1 000 Zeilen): je Epoche Zeilen, erste/letzte ID, ok, erste gebrochene ID; Altzeilen (Anzahl, Abweichungen, durch Löschung pseudonymisiert); Anzahl versiegelter Zeilen, die noch eine Nutzer-ID tragen (muss 0 sein); Anzahl Zuordnungen. Ok = alle Epochen ok, keine Altabweichung, keine Rest-IDs.
- WP-CLI `wp course-captain audit verify [--format=json]` (`OpsCommand`, Exit 0 ok / 1 gebrochen).

### Tests

- `test_D12_pseudonymised_chain_survives_erasure`: keine IDs in Spalten/`object_id`/Kontext (`owner`, `previous_owner`, `user:<ID>`), Ansicht löst das Pseudonym auf; Kette ok **vor und nach** der Löschung; Zuordnung weg, Einträge bleiben, nicht mehr verknüpfbar; CLI Exit 0; nachträgliche Änderung von `reason` → gebrochen an genau dieser ID, CLI Exit 1.
- `test_D12_legacy_reseal_with_epoch_marker`: Altzeilen im alten Format, Löschung einer Person **vor** dem Neuversiegeln (gezählt, Kette ok), Backfill in zwei Batches (idempotent, Marker erst am Ende), danach Epoche 1 ok, keine IDs, Marker mit Kennzahlen und `resealed_head`; Kette ok nach weiterer Löschung.
- `test_D12_tampered_legacy_row_is_documented`: veränderte Altzeile → Prüfung meldet sie, Marker `legacy_verified: false` mit ID, neue Kette danach ok.
- `test_D12_chain_verifies_after_retention`: Aufbewahrung entfernt alte Zeilen, Zusammenfassung trägt `anchors`, Restkette ok.

### Offen / Grenzen

- Freitext-Begründungen (`reason`) werden nicht umgeschrieben; sie stammen vom Personal und sollen keine Personendaten enthalten (Hinweis für Betreiberdoku).
- Parallele Schreibzugriffe lesen den Kettenkopf ohne Sperre (wie vorher): zwei gleichzeitige Einträge können die Kette verzweigen; `audit verify` meldet das. Eine Sperre in `AuditLog::write()` würde gegen „kein Lock in offener TX“ verstoßen (Aufrufer schreiben oft in Transaktionen); Lösung wäre eine Kopfzeile mit `SELECT … FOR UPDATE` – nicht Teil dieses Slices.
- Contract-Schritt (Entfernen von `actor_user_id`/`subject_user_id`) frühestens in Release N+2 nach R0-37 §4.7.
- Downgrade (K-1): älterer Code würde wieder Zeilen im Altformat (Epoche 0) schreiben; ein erneuter Lauf des Backfills versiegelt sie, ein zweiter Marker entsteht dann nicht (Status `sealed`). Andere Slices, die neue Tests auf `actor_user_id`/`subject_user_id` des Audit-Logs schreiben, müssen auf `actor_ref`/`subject_ref` + `AuditIdentity::user_of()/ref_of()` umstellen.
- Neue msgids: 6 (de_DE und de_DE_formal übersetzt, `.mo` neu gebaut).
