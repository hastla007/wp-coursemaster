# R0-09 · Identity-, Seat- und Grant-Zustandsautomaten (Entwurf)

**Status:** **Entwurf – Freigabe Auftraggeber/Architektur offen.** Task R0-09 ist **nicht** abgehakt. Keine Implementierung, kein Code im Repository; Tabellen-/Spaltennamen folgen `docs/r0/R0-06-datenmodell-adr.md` §4.4.
**Stand:** 16.11.2026
**Bezug:** TODO R0-09 (DoD: Claim, Quellen, Mehrfachgrants, Suspend/Expire/Refund, Race Conditions, Privacy), CORE-SPEC-001…006, SEAM-001, SEAM-101, R1-13, R3-03; Konzept Kap. 31.2, 31.3, 34.1 (ID-01…06), 34.3 (ACCESS-01…09), 35.1–35.5; `R0-02-inventar/B-commerce.md` (§2.1–2.5, §5 Zustände/Locks, §6 D-1…D-11), `R0-06-datenmodell-adr.md` (§3.4, §4.4, §4.8, §4.9, Nachträge), `R0-07-rbac-idor-matrix.md` (M-02, M-13…M-17, B-4…B-7, §5 Grant/Gruppe), `R0-04-r1-r3-grenze.md` (K-01, K-02), `R0-05-golden-master.md` (GM-081…GM-106), `R0-14-nichtfunktionale-ziele.md` (RB-04, L5), `R0-36-suite-vertragsdeltas.md` (P4, C2, C6, C9).
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77` (nur gelesen). Pfade relativ zu `bootspruefung-learning/`. Kürzel: **ESV** = `src/Commerce/EntitlementService.php`, **EPL** = `src/Commerce/EntitlementPlanner.php`, **GS** = `src/Commerce/GroupService.php`, **AS** = `src/Learning/Services/AccessService.php`, **NL** = `src/Infrastructure/NamedLock.php`, **ES** = `src/Exams/Services/ExamService.php`.
**Arbeitsannahmen (Empfehlungen aus `ENTSCHEIDUNGEN.md`, noch nicht beschlossen):** E-17 (Gruppen in Free, auch ohne Kauf), E-26 (keine abhängigen Zusatz-Grants in R1), E-27 (Grant je Kurs, Scopes `view/download/exam`), E-28 (LB-Seat-Regeln + Option „nur eingeladene Adresse“), E-29 (kein Wiederaufleben, neue Revision), **E-30 (Kontopflicht beim Gastkauf in R3.1; Claim-Vertrag wird trotzdem vollständig spezifiziert, weil Gruppeneinladungen ihn in R1 nutzen)**, E-35 (Voucher-Storno entzieht nur den Voucher-Grant), E-36 (ein Code pro Platz), E-79 (eigene Lernsperre), E-81 (E-Mail minimiert). Weicht ein Beschluss ab, sind die mit der E-Nummer markierten Stellen anzupassen.

**Normative Sprache:** **MUSS** = Vertragsbestandteil, Abweichung nur per ADR-Änderung. **SOLL** = Standard, begründete Abweichung erlaubt. **KANN** = optional.

---

## 0. Kurzfassung

| Thema | Festlegung |
|---|---|
| Identität | WP-User-ID je Site ist die einzige Lernidentität. E-Mail ist **nie** Identitätsbeweis (35.1). Käufer, Gruppenbesitzer und Lernender sind getrennte Beziehungen. |
| Claim | Ein Vertrag für alle Einmal-Tokens (persönliche Einladung R1, Gruppenlink R1, Gast-Claim R3 später): 256 Bit, nur SHA-256-Hash gespeichert, Ablauf, bedingtes Einlösen (`affected_rows = 1`), Rotation bei Wiederholung, Vorschau ohne Zustandsänderung. |
| Grant-Automat | 7 Zustände aus 35.2. `revoked` und `expired` sind **Endzustände je Quellrevision**. Neue Berechtigung = `source_revision + 1` (E-29). Jeder Übergang: CAS auf `row_version` + `cc_grant_event` + `cc_outbox_event` in **einer** Transaktion. |
| Idempotenz | `UNIQUE grant_source (source_type, source_id, source_line, source_revision, course_id)` ist der Idempotenzschlüssel; Inbox-Dedupe `(connector_id, event_id)`; Event-Dedupe `(grant_id, source_event_id)`. |
| Effektiver Zugang | Vereinigung aller `active`-Grants im Zeitfenster, Vereinigung der Scopes, spätestes Ende (unbefristet gewinnt). Verlängerungskette `extend` und Restack wie LB, aber nach `paid_at` statt Insert-Reihenfolge geordnet. Lernsperre (E-79) übersteuert alles, ohne Grants zu ändern. |
| Seats | Gruppe auch ohne Kauf (E-17). Belegung = `active` + offene, nicht abgelaufene Einladung. Vergabe unter `NamedLock group-seats:<id>` + Nachzählung. Reduktion: **zuerst offene Einladungen, dann zuletzt Beigetretene** (LB, GM-101). Entfernte Plätze werden nie reaktiviert; neuer Platz = neue Seat-Zeile. |
| Refund | Nur der betroffene Grant/Platz; keine Wiederbelebung; Lernhistorie bleibt; Zertifikat bleibt gültig und verifizierbar (35.3). |
| Races | 13 Szenarien mit Lock-Reihenfolge, Unique-Invarianten und Tests GRANT-001…, SEAT-001…, ID-001…. |
| Neu zu entscheiden | D09-1 … D09-6 (§8). |

---

## 1. Identität

### 1.1 Begriffe und autoritative Quellen

| Begriff | Definition | Speicherort | Regel |
|---|---|---|---|
| **Lernender** (`learner`) | WP-Konto mit `read` auf der Site, auf das ein Grant zeigt | `cc_grant.user_id`, `cc_seat.user_id` | Einzige Lernidentität; site-gebunden (R0-06 §6.6, ID-06) |
| **Käufer** (`buyer`) | wirtschaftlicher Besteller in einer Quelle | **nicht** als Person kopiert; nur Quellreferenz `cc_grant.source_id`/`cc_group.source_id` (R0-06 E-38) | Woo bleibt Owner der Bestelldaten; Käufer erhält **keinen** Grant, nur weil er gekauft hat (35.1, GM-094 „Verwalter ohne Einzelgrant“) |
| **Gruppenbesitzer** | WP-Konto, das einen Platz-Pool verwaltet | `cc_group.owner_user_id` | Käufer einer Mehrplatzposition oder vom Admin bestimmt (E-17); sieht nur Platz-/Einladungsstatus (M-15, M-16) |
| **Eingeladener** | Empfänger einer Einladung ohne bestätigte Kontozuordnung | `cc_seat.invite_email` + `claim_token_hash` | Kommunikationsziel, kein Identitätsbeweis |
| **Externes Subjekt** | Kennung der Quelle (z. B. Woo-Kunde, Voucher-Einlöser) | nur im Adapter bzw. `cc_inbox_event.payload` (minimal) | Abbildung auf WP-User nur über eingeloggten Akteur oder verifizierten Claim; nie per E-Mail-Gleichheit (CORE-01, ID-02) |

**MUSS:** Kein Codepfad verknüpft Grant, Platz oder Bestellung mit einem WP-Konto anhand einer E-Mail-Adresse. Erlaubt sind nur: (a) die Quelle meldet die WP-User-ID eines **zur Zeit der Aktion eingeloggten** Kontos (Woo-Checkout mit Kontopflicht, E-30), (b) ein verifizierter Claim (§1.2), (c) eine auditierte Admin-Zuordnung mit Begründung (`cc_manage_grants`).

### 1.2 Claim-Vertrag (einheitlich für Einladung, Gruppenlink und späteren Gast-Claim)

| Element | Regel | LB-Befund / Abweichung |
|---|---|---|
| Tokenarten | `i` persönliche Einladung (einmalig, an einen Platz gebunden); `g` Gruppenlink (mehrfach nutzbar bis Platzgrenze); `c` Gast-Claim aus Kauf (R3, nach Aufhebung von E-30) | LB `[gi][0-9a-f]{32}` (GS:737-751) |
| Erzeugung | `random_bytes(32)`, base64url (43 Zeichen) mit Typpräfix, z. B. `cci_…`, `ccg_…`, `ccc_…` | LB 128 Bit Hex (GS:233, :509) |
| Speicherung | **nur** `sha256(token)` als `char(64)`, UNIQUE (`cc_seat.claim_token_hash`, `cc_group.join_token_hash`); Klartext nur in Mail bzw. einmaliger Anzeige (D09-1) | LB Klartext `varchar(64)` (R0-06 §1.1 Nr. 3) – **Abweichung** |
| Nachschlagen | über den Hash (Indexzugriff, kein Vergleich im Klartext); Formatprüfung vor dem DB-Zugriff | wie LB GS:739 |
| Ablauf | `i`: `claim_expires_at = invited_at + 30 d` (Setting, LB `INVITE_DAYS`, GS:25); `g`: gültig, solange Gruppe `active` und `now ≤ valid_until`, optional eigenes Ablaufdatum; `c`: 14 d (Setting, Vorschlag für R3) | LB GS:639-654 |
| Vorschau (`GET`) | ändert nichts; zeigt Gruppenname (bereinigt, GS:521-531), Besitzer-Anzeigename, Kurse/Scopes, freie Plätze, Ablauf; bei persönlicher Einladung und anderem Konto: Hinweis mit **maskierter** Adresse (`a…@example.de`, GS:287-308, T132) | übernehmen |
| Einlösen (`POST`, Nonce) | nur angemeldet (Gast → Login/Registrierung mit Rücksprung, GM-104); unter Gruppen-Lock (§4.3); persönlich: `UPDATE cc_seat SET status='active', user_id=?, claim_token_hash=NULL, invite_email=NULL, claimed_at=? WHERE id=? AND status='invited' AND claim_token_hash=? AND claim_expires_at>?` → genau 1 Zeile, sonst `cc_claim_used` bzw. `cc_claim_expired` | LB bedingtes UPDATE GS:390-399; **Delta:** `invite_email` wird beim Claim geleert (Privacy §7) |
| Einmaligkeit | `i` und `c` sind nach erfolgreichem Claim sofort ungültig (Hash = NULL). Doppelklick/zweites Gerät desselben Kontos liefert die bestehende Mitgliedschaft (idempotent, GS:350-357) | übernehmen |
| Wiederholung („erneut senden“) | erzeugt **neues** Token, alter Hash wird überschrieben (sofort ungültig), `invited_at`/`claim_expires_at` neu; zählt gegen das Einladungsbudget max(20, 2 × Plätze)/24 h (GS:262-270) | LB kennt kein Resend; neu |
| Accountwechsel | Token ist ein Inhaberrecht: Standard `claim_policy = transferable` (LB, E-28) → jedes angemeldete Konto kann nach Hinweis annehmen. Option `invited_address_only` → nur wenn `user_email` des Kontos (WP-bestätigt, s. u.) der Einladungsadresse entspricht; sonst 403 `cc_invite_other_account` (RBAC-054). Bei dieser Option ist der Gruppenlink `g` deaktiviert | E-28 |
| Falsches Konto beansprucht | keine Selbstkorrektur durch den Lernenden; Besitzer entfernt den Platz und lädt neu ein (§4.6), Support kann „Zugang übertragen“ (D09-3) | – |
| Brute Force | Rate-Limit je IP und Konto auf Vorschau und Einlösen (Werte in R0-17, LB Einlösung 30/IP/h, 10/Konto/h); bei Ausfall des Zählers **fail-closed** (E-46) | E-46 |
| Leckschutz | Claim-Seite mit `Referrer-Policy: no-referrer`, `Cache-Control: no-store`; Token nie in Logs, Audit, Exporten (R0-07 §4.9), Fehlermeldungen oder Outbox-Payloads | neu |
| Gast-Claim aus Kauf (R3, nach E-30 später) | Bestellposition für Dritte oder ohne Konto → Grant `pending_claim` mit `user_id = NULL`; Aktivierung erst nach Claim **und** gültigem Quellnachweis (35.1); Mail an Bestelladresse mit Token `c`; Ablauf → Grant bleibt `pending_claim`, Support/Käufer kann neues Token auslösen; Eskalation nach Pending-Timeout (31.7 Nr. 2, SEAM-006) | Speicherort siehe Delta Δ-9 |

**Hinweis zur E-Mail-Bindung (`invited_address_only`):** Selbst geänderte Adressen bestätigt WordPress über einen Link an die neue Adresse; Admin-Änderungen nicht. Der Vergleich ist deshalb nur so stark wie die Adresspflege der Site; das wird in der Option erklärt und nicht als Identitätsnachweis bezeichnet.

### 1.3 E-Mail-Wechsel, Kontolöschung, Sperre, Zusammenführung

| Ereignis | Wirkung auf Grants | Wirkung auf Seats/Gruppen | Lernhistorie/Zertifikat | Beleg |
|---|---|---|---|---|
| **E-Mail-Wechsel** | keine (35.1) | keine; offene Einladungen an die alte Adresse bleiben gültig (Token, nicht Adresse zählt); bei `invited_address_only` zählt die Adresse zum Claim-Zeitpunkt | keine; Zertifikat-Snapshot unverändert | 35.1 |
| **Lernsperre** (`cc_learner_lock`, E-79) | **keine Änderung**; effektiver Zugang = deny (`cc_account_locked`) | eigene Plätze bleiben belegt; als Besitzer: Verwaltung gesperrt, Mitglieder behalten Zugang (RBAC-056) | bleibt; öffentliche Verifikation bleibt | R0-07 §3.4, RBAC-050 |
| **Rollenentzug auf der Site** | keine Änderung; Zugriff mangels `read` verweigert | wie Sperre | bleibt | R0-07 §3.4 (b) |
| **Kontolöschung** | alle Grants → `revoked` (`account_deleted`), Event + Outbox; danach Retention nach §7 (Kaufquellen pseudonymisiert behalten, sonst löschen) | eigene Plätze → `removed`, `invite_email` NULL, Seat-Grants `revoked`; eigene Gruppen: `owner_user_id = 0`, Gruppe läuft weiter, Admin verwaltet (GS:444-469, GM-102) | Lerndaten über Eraser (R1-17, INV-C-152); Zertifikate nach Retention E-51 | LB INV-C-148/158 quellenneutral |
| **Zusammenführung (Merge)** | R1: **kein** Merge-Werkzeug (D09-3). Support-Aktion „Zugang übertragen“: Quell-Grant `revoked` (`transferred`), neuer Grant gleicher Quelle mit `source_revision + 1` für das Zielkonto, Begründung Pflicht, beide Konten im Audit | Platz: entfernen + neu einladen | Historie bleibt beim Ursprungskonto; keine automatische Übernahme von Fortschritt/Versuchen/Zertifikaten | 35.1 „Merge nur nach expliziter sicherer Bestätigung“ |
| **Kundenwechsel in der Quelle** (Woo-Bestellung einem anderen Kunden zugeordnet, R3) | **kein** stilles Umhängen (LB tut das, EPL:144-146); alter Grant `revoked` (`source_subject_changed`), neuer Grant `source_revision + 1` für den neuen Kunden; Hinweis im Dashboard | Gruppe: Besitzerwechsel nur mit Audit-Event `group.owner_changed` | bleibt beim alten Konto | D09-6 |

**Merge-Vertrag für später (Spezifikation, nicht R1):** Nur durch Akteur mit `delete_users` **und** `cc_manage_grants`; Nachweis der Kontrolle über beide Konten (eingeloggt als Ziel + Einmal-Token an die Adresse des Quellkontos, Claim-Vertrag §1.2); Sperren beider Konten in fester Reihenfolge (§6.1); Grants per Übertragung (s. o.), Lernfortschritt nur nach festgelegter Mergeregel je Tabelle (R0-08), Zertifikate werden **nicht** umgeschrieben, sondern ggf. als neue Revision ausgestellt (CORE-SPEC-006); Quellkonto danach gesperrt oder gelöscht; Event `learner.merged`.

---

## 2. Grant-Zustandsautomat

### 2.1 Zustände

| Zustand | Bedeutung | Zugang | Anzeige „Mein Lernen“ (31.2) | Ende? |
|---|---|---|---|---|
| `pending_claim` | Berechtigung besteht, Lernender noch nicht zugeordnet (`user_id NULL`) | nein | – (beim Käufer: „Zuweisung offen“) | nein |
| `pending_confirmation` | Lernender bekannt, Quelle hat noch nicht bestätigt (Zahlung offen, Zustimmung fehlt, Voucher reserviert) | nein | „Zugang wird aktiviert“ | nein |
| `active` | gültig; Zugang nur innerhalb `valid_from ≤ now ≤ valid_until` (Zeitprüfung, nicht Cron, R0-07 B-6) | ja | „aktiv bis …“ | nein |
| `suspended` | reversibel gesperrt (Zahlungskonflikt, manuelle Sperre, Gruppe ausgesetzt) | nein | „vorübergehend nicht verfügbar“ + Supportweg | nein |
| `expired` | Laufzeit abgelaufen | nein | „abgelaufen“ + ggf. Verlängerungsangebot | **ja** (je Revision) |
| `revoked` | fachlich zurückgenommen (Refund, Storno, Platzentzug, Kontolöschung, Admin) | nein | ausgeblendet bzw. „beendet“ (LB blendet aus, ESV:478-503) | **ja** (je Revision) |
| `failed` | Verarbeitung gescheitert (Validierung, Ziel fehlt, Vertrag inkompatibel, Timeout eskaliert) | nein | „Aktivierung fehlgeschlagen – Support“ | nein (Retry) |

**Abgrenzung `failed` ↔ `revoked`:** `failed` = technischer oder Validierungsfehler der **Verarbeitung**, wiederholbar mit demselben Schlüssel; `revoked` = **fachliche** Entscheidung der Quelle oder eines Akteurs. Eine fehlgeschlagene Zahlung ist fachlich → `revoked` (`payment_failed`, wie LB EPL:37-59).

### 2.2 Quellen und Idempotenzschlüssel

Schlüssel = `UNIQUE grant_source (source_type, source_id, source_line, source_revision, course_id)` (R0-06 §4.4). Gleicher Schlüssel mit anderem `user_id` → `cc_idempotency_conflict` (GM-088, LB `lb_idempotency_conflict`).

| `source_type` | Phase | `source_id` | `source_line` | `source_revision` | Erzeuger / Actor | Startzustand |
|---|---|---|---|---|---|---|
| `manual` | R1 | `request_key` (UUID v4 aus dem Formular, bei Doppelabsendung identisch) | `''` | 1; Reaktivierung nach `expired`/`revoked` = +1 | Admin/Manager mit `cc_manage_grants` (M-14), Begründung Pflicht (ESV:332-361) | `active` |
| `group_seat` | R1 | `cc_group.uid` | `cc_seat.id` (Seat-Zeile ist einmalig) | 1; nach Gruppen-Wiedereinsetzung +1 (§4.8) | System beim Claim (`actor = user`) | `active` |
| `woo_order` | R3.1 | Woo-Bestell-UID (HPOS-ID) | Positions-ID | 1; bestätigte Rücknahme einer Erstattung oder Kundenwechsel +1 (E-29, D09-6) | `connector:woocommerce` | `pending_confirmation` oder `active` (bezahlt, Konto vorhanden, Zustimmung erfüllt) |
| `voucher` | R3.2 | `redemption_key` (`vcap()->redemptions`, R0-36 C2) | `''` (E-36: ein Code je Platz) | 1 | `connector:voucher-captain` | `pending_confirmation` (reserve) → `active` (commit) |
| `workflow` | später (E-39) | Workflow-Lauf-ID | Aktions-ID | 1 | `connector:workflow`, nur eigene Grants | `active` |

Woo-spezifische Felder (`order_id`, `order_item_id`, `sku`) liegen **nicht** im Kern (K-01); der Statusabgleich läuft über `GrantSourceAdapter` + `SourceStatusMapper`, unbekannter Quellstatus = „nicht ändern“ (K-02, EPL:34-71).

### 2.3 Übergangstabelle

Spalte **Vorrang**: Eine manuelle Entscheidung (`manual_override`, Δ-2) wird von Quellereignissen nicht überschrieben – **außer** durch Widerruf (LB EPL:150-154).

| Nr. | Von → Nach | Auslöser | Quelle(n) | `reason_code` | Event (Outbox) | Bemerkung |
|---|---|---|---|---|---|---|
| G-01 | ∅ → `pending_confirmation` | Quelle meldet Anspruch, unbestätigt | woo_order, voucher | `awaiting_payment`, `consent_missing`, `reserved` | `grant.created` | LB EPL:47-49, :65-67 |
| G-02 | ∅ → `pending_claim` | Anspruch ohne zugeordneten Lernenden | woo_order (nach E-30 später) | `awaiting_claim` | `grant.created` | 35.1 |
| G-03 | ∅ → `active` | sofort gültiger Anspruch | manual, group_seat, woo_order, workflow | `manual_grant`, `seat_claimed`, `order_paid` | `grant.created` + `grant.activated` | Erste Aktivierung setzt Laufzeit (§3.3) |
| G-04 | `pending_confirmation` → `active` | Quelle bestätigt (bezahlt, Zustimmung, Commit) | woo_order, voucher | `order_paid`, `consent_given`, `committed` | `grant.activated` | LB ESV:121-134 |
| G-05 | `pending_claim` → `active` | verifizierter Claim + gültiger Quellnachweis | woo_order (später) | `claimed` | `grant.activated` | 35.2 |
| G-06 | `pending_*` → `failed` | Validierung/Ziel/Vertrag/Timeout-Eskalation | alle externen | `target_unavailable`, `contract_incompatible`, `pending_timeout` | `grant.failed` | Timeout-Wert offen (31.7 Nr. 2) |
| G-07 | `pending_*` → `revoked` | Storno, Zahlung fehlgeschlagen, Refund vor Aktivierung, Voucher `released` | woo_order, voucher | `order_cancelled`, `payment_failed`, `released` | `grant.revoked` | LB EPL:37-59 |
| G-08 | `active` → `suspended` | Zahlungskonflikt, manuelle Sperre, Gruppe ausgesetzt | woo_order, manual (Admin), group_seat | `payment_disputed`, `manual_suspend`, `group_suspended` | `grant.suspended` | LB EPL:53-56 |
| G-09 | `suspended` → `active` | Konflikt gewonnen, manuelle Freigabe, Gruppe fortgesetzt | wie G-08 | `dispute_won`, `manual_release`, `group_resumed` | `grant.resumed` | nur wenn **alle** Sperrgründe weg sind; manuelle Sperre nur manuell lösbar |
| G-10 | `active` → `expired` | `now > valid_until` (Cron `cc_grant_expire`, stündlich) oder Enddatum in der Vergangenheit gesetzt | alle | `validity_ended` | `grant.expired` | Cron ist nur kosmetisch (R0-07 §4.6) |
| G-11 | `suspended` → `expired` | Laufzeit endet während der Sperre | alle | `validity_ended` | `grant.expired` | – |
| G-12 | `active`/`suspended` → `revoked` | Refund/Teilrefund nach Regel, Chargeback verloren, Storno, Position entfernt, Platz entfernt/reduziert, Konto gelöscht, Admin-Widerruf, Voucher `void` (E-35), Übertragung | alle | `item_refunded`, `order_refunded`, `chargeback_lost`, `item_removed`, `group_member_removed`, `group_seats_reduced`, `group_revoked`, `account_deleted`, `manual_revoke`, `voucher_voided`, `transferred`, `source_subject_changed` | `grant.revoked` | Endzustand |
| G-13 | `failed` → `pending_*`/`active` | auditierter Retry (Admin oder automatisch bei temporärem Fehler) mit **demselben** Schlüssel und nachgewiesenem Anspruch | alle externen | `retry` | `grant.retried` + Folgeevent | 35.2, 31.3 Nr. 5 |
| G-14 | `active` → `active` | Enddatum ändern (`extend`), Restack der Kette | manual, System | `extended`, `rescheduled` | `grant.extended` / `grant.rescheduled` | kein Statuswechsel, aber CAS + Event |
| G-15 | `expired`/`revoked` → neue Revision `active` | Reaktivierung, bestätigte Rücknahme der Erstattung, erneute Berechtigung | manual, woo_order, group_seat | `reactivated`, `refund_reversed`, `reinstated` | `grant.created` + `grant.activated` (neue Zeile, `supersedes` im Event-Detail) | E-29, RBAC-046 |

**Verbotene/ignorierte Übergänge (MUSS protokolliert, nicht angewendet):**

| Fall | Verhalten | LB |
|---|---|---|
| `revoked` → irgendwas | nie; spätes `paid` nach `refunded` → `cc_grant_event` `ignored_late_event` | LB belebt automatische Widerrufe wieder (EPL:155-164, GS:83-91, GS:181-200) – **Abweichung (E-29)** |
| `expired` → `active` | nie automatisch; Verlängerung nach Ablauf = G-15 | LB `extend()` setzt `expired` → `active` (ESV:376-378) – **Abweichung** (RBAC-046, Abweichungsliste E-55) |
| `pending_*` → `suspended` | ignoriert, Event `ignored` | wie LB EPL:165-167 |
| manueller `revoked` → Aufheben | nicht möglich; Aufheben gilt nur für `suspended`/`approved` | LB `releaseManual` springt auf den Stand vor der Sperre zurück (ESV:387-423) – für `revoked` **Abweichung** |
| Ereignis mit älterer Quellversion | ignoriert (`stale_event`), siehe §6 R-04 | neu |

**Manuelle Vorabfreigabe** (LB `approveOrder`, INV-B-011): `pending_confirmation` → `active` mit `manual_override = approved`, Begründung Pflicht; ein späterer Widerruf der Quelle (Zahlung fehlgeschlagen, Storno) setzt sich trotzdem durch.

### 2.4 Transaktion, CAS, Audit

Ablauf jedes Übergangs (MUSS):

1. Eingang dedupliziert (`cc_inbox_event` UNIQUE `(connector_id, event_id)`) bzw. interner Befehl mit `request_key`.
2. Quell-Lock `grant-source:<source_type>:<source_id>` (NamedLock, §6.1) – serialisiert alle Positionen eines Quellobjekts für den Diff-Planer (`GrantReconciler`, K-02).
3. `START TRANSACTION`; Grant lesen; `GrantTransitionGuard` entscheidet nach §2.3.
4. Neu: `INSERT` mit Unique `grant_source`; bei Duplicate-Key die vorhandene Zeile lesen und vergleichen (gleicher Nutzer/Kurs → idempotente Antwort, sonst `cc_idempotency_conflict`). **Kein** `SELECT … FOR UPDATE` auf nicht existierende Zeilen (R0-36 P4).
5. Bestehend: `UPDATE cc_grant SET status=?, …, row_version=row_version+1 WHERE id=? AND row_version=? AND status=?`; 0 Zeilen → TX zurück, neu lesen, Guard erneut (höchstens 3 Versuche), sonst `cc_conflict` (HTTP 409) bzw. Inbox-Retry.
6. `INSERT cc_grant_event` (`from_status`, `to_status`, `reason_code`, `actor_type`, `actor_id`, `source_event_id`, `correlation_id`; UNIQUE `(grant_id, source_event_id)`).
7. `INSERT cc_outbox_event` (Event aus §2.3, plus `access.changed`, wenn sich der effektive Zugang für Nutzer × Kurs ändert, §3.4).
8. `COMMIT`; danach Policy-Memo/Cache `cc_policy` invalidieren (R0-07 P-11).

Deadlock (1213) → ganze TX wiederholen (max. 3, Jitter), Lock-Wait (1205) → `cc_db_busy` (R0-14 RB-04). Nie im Autocommit weiterlaufen (R0-36 P4).

**Audit:** `cc_grant_event` ist die fachliche Historie (append-only). Admin-Aktionen (manuell anlegen/verlängern/sperren/widerrufen/übertragen) schreiben zusätzlich `cc_audit_log` mit Begründung (R0-07 §3.6). Grants mit externer Quelle kann der Admin sperren, notieren und widerrufen, aber nicht löschen (M-13).

### 2.5 Ereigniskatalog (Namen; Envelope in R0-19)

| Event | Payload (minimal) | Empfänger (Beispiele) |
|---|---|---|
| `grant.created`, `grant.activated`, `grant.suspended`, `grant.resumed`, `grant.expired`, `grant.revoked`, `grant.failed`, `grant.retried`, `grant.extended`, `grant.rescheduled` | `grant_uid`, `learner_uid`, `course_uid`, `scopes`, `status`, `valid_until`, `source_type`, `source_ref` (ohne Personendaten), `reason_code`, `source_revision`, `correlation_id` | Connector (Bestätigung 31.3 Nr. 1), Workflow (später) |
| `access.changed` | `learner_uid`, `course_uid`, effektive Scopes, effektives Ende, `has_access` | „Mein Lernen“-Cache, Reminder (Pro), Workflow |
| `group.created`, `group.status_changed`, `group.seats_changed`, `group.owner_changed`, `group.reinstated` | `group_uid`, Zahlen/Status, `source_ref` | Adapter, Dashboard |
| `seat.invited`, `seat.claimed`, `seat.removed`, `seat.expired` | `group_uid`, `seat_id`, `learner_uid` (nur nach Claim) – **keine** E-Mail | Dashboard, Workflow |
| `learner.erased`, `learner.merged` (später) | `learner_uid` | Partner mit eigenen Daten (31.3 Nr. 3) |

Token-Rotation erzeugt nur Audit, kein Outbox-Event (keine Außenwirkung).

---

## 3. Effektiver Zugang bei Mehrfachgrants

### 3.1 Formel

```
zugang(u, c, scope, t) =
      ¬ lernsperre(u, t)                                    -- E-79, übersteuert alles
  ∧   kursstatus(c) erlaubt Lernen                          -- R0-08/R0-07 §3.4
  ∧ ( ∃ g ∈ cc_grant: g.user_id = u ∧ g.course_id = c ∧ g.status = 'active'
        ∧ g.valid_from ≤ t ∧ (g.valid_until IS NULL ∨ t ≤ g.valid_until)
        ∧ scope ∈ expand(g.scopes)                          -- 'all' = {view, download, exam}
      ∨ free_regel(c, scope, t) )                           -- cc_access_rule, ggf. Teilumfang
```

- Zeitgrenzen inklusiv wie LB (AS:408-409), UTC, sekundengenau. Zugriff hängt **nicht** vom Ablauf-Cron ab.
- Effektive Scopes = **Vereinigung** über alle zählenden Grants (LB `entitledFeatures` DISTINCT, AS:391-420; GM-092).
- Effektives Ende = **spätestes** `valid_until` über alle `active`-Grants inklusive angeschlossener, noch nicht begonnener Verlängerungen; existiert ein unbefristeter → unbefristet (LB AS:220-225, ESV:478-503, GM-084).
- Ein voller Grant hebt den Teilumfang auf (`limit = null`, GM-082).
- Zugangsbeginn für Drip = frühestes `valid_from` eines aktiven Grants, sonst Registrierung (AS:320-333; R0-08 §1).
- Filter `course_captain_can_access` darf nur einschränken (D07-7).
- Abhängige Zusatz-Grants (`depends_on_grant`) gibt es in R1 nicht (E-26, K-04).

### 3.2 Anzeige-Status je Kurs

Rangfolge bei mehreren Grants (LB AS:200-240, GM-091): `active` (im Fenster) > `pending_confirmation`/`pending_claim` > `suspended` (nicht abgelaufen) > `expired` > keiner. `failed` wird nur angezeigt, wenn kein anderer Grant existiert. `revoked` wird ausgeblendet. Teilumfang ohne Grant → `limited`.

### 3.3 Laufzeit, Verlängerung und Restack

| Regel | Festlegung | LB-Beleg |
|---|---|---|
| Laufzeitbeginn | bei **erster** Aktivierung: `valid_from = paid_at` der Quelle bzw. `now`; `valid_until = valid_from + duration_days`; „unbefristet“ nur ausdrücklich (T101) | ESV:121-162, DefaultDuration (INV-B-033) |
| `renewal_mode = extend` (Standard) | Beginn = spätestes Ende eines bestehenden aktiven, befristeten Grants **mit gleichen Scopes** für Nutzer × Kurs, sonst `paid_at`. Bei abweichenden Scopes gilt `parallel` (deterministisch, keine Teilverkettung) | ESV:146-155 (je Kurs × Feature) |
| `renewal_mode = parallel` | Beginn = `paid_at`/`now` | GM-084 G-6 |
| Restack | Wird ein befristeter Grant der Kette (nicht) mehr nutzbar (Refund, Konflikt, Wiedereinsetzung als neue Revision), werden die `extend`-Grants neu verkettet: Beginn = max(Ende der vorherigen aktiven Grants, eigenes `paid_at`), **Laufzeit bleibt gleich**; Event `grant.rescheduled` + Audit | ESV:165-206, GM-089 („Verlängerung rückt vor“) |
| Kettenreihenfolge | nach `chain_anchor_at` (= ursprüngliches `paid_at`; eine neue Revision erbt es von ihrem Vorgänger), dann `id` – damit landet eine nach E-29 neu erzeugte Revision an ihrer alten Position und nicht am Kettenende | LB ordnet nach `id` (ESV:181-184) – **Delta Δ-3** |
| Manuelle Verlängerung | nur für `active`: neues `valid_until` (Zukunft, Begründung), `valid_until_locked = 1` schützt vor Gruppenabgleich (T158); Enddatum in der Vergangenheit → G-10 | ESV:364-381 |
| Verlängerung nach Ablauf | G-15 (neue Revision); kein Statussprung `expired` → `active` | RBAC-046 |
| Doppelte Verlängerung | Unique-Schlüssel je Quellposition verhindert zweite Laufzeit aus demselben Ereignis (ACCESS-05) | ESV:107-112 |
| Gruppen-Laufzeit | Seat-Grants übernehmen `cc_group.valid_until`, außer `valid_until_locked` oder `manual_override` | GS:181-200 |

### 3.4 `access.changed`

Wird nach jedem Grant-Übergang berechnet: Ändert sich (`has_access`, effektive Scopes, effektives Ende) für Nutzer × Kurs nicht (z. B. Widerruf eines von zwei gleichwertigen Grants, CORE-04/GM-085), wird **kein** `access.changed` emittiert – Folgeprozesse (Mails, Workflow) reagieren dann nicht fälschlich auf einen Teilwiderruf.

---

## 4. Seat- und Gruppen-Automat

### 4.1 Zustände

**Gruppe** (`cc_group.status`): `active` · `suspended` (reversibel) · `revoked` (Endzustand je `source_revision`, Δ-1). Gültigkeit über `valid_from/valid_until`; nach Ablauf kein Beitritt, Seat-Grants laufen mit ab.

**Platz** (`cc_seat.status`): `invited` → `active` | `removed` | `expired`; `active` → `removed`. `removed` und `expired` sind endgültig; neuer Platz = neue Zeile (kein Wiederbeleben, Unterschied zu LB GS:578-583).

**Belegung (eine Regel für Join, Einladung, Dashboard):** `status = active` **oder** (`status = invited` und `claim_expires_at > now`) (LB GS:639-642).

### 4.2 Gruppe anlegen

| Weg | Phase | Akteur | Regeln |
|---|---|---|---|
| Manuell ohne Kauf | R1 Free (E-17) | `cc_manage_groups` (Admin/Manager) | Pflichtfelder: Besitzer (WP-Konto), Name (bereinigt: keine Links/Adressen/Domains, GS:521-531), Kurse + Scopes (`cc_group_course`), Plätze ≥ 1, Laufzeit (unbefristet nur ausdrücklich), `claim_policy`; `source_type = manual`, `source_id = request_key` (Doppelabsendung idempotent); Audit (GM-106) |
| Aus Mehrplatzposition | R3.1 | `connector:woocommerce` | Plätze = Plätze je Stück × (Menge − erstattete Menge) (GS:138); Besitzer = eingeloggter Käufer; `UNIQUE group_source`; Gruppe erst bei `active` der Quelle (GM-094); Name neutral aus Produkt, nie Käufername/Bestellnummer |
| Aus Gutschein | – | – | nicht vorgesehen (E-36: ein Code je Platz → Einzel-Grants) |

Der Besitzer erhält **keinen** eigenen Grant; will er selbst lernen, beansprucht er einen Platz wie jedes Mitglied (GM-094).

### 4.3 Einladung, Gruppenlink, Join

| Schritt | Regel | LB |
|---|---|---|
| Einladen | Besitzer/Admin (M-15); je Adresse unter `NamedLock group-seats:<id>`: freier Platz? → Seat `invited` mit Token-Hash und Ablauf; sonst übersprungen (keine Mail); Budget max(20, 2 × Plätze)/24 h; Mailtext nach Art. 14 DSGVO (wer lädt ein, wie lange, Datenschutzerklärung), Absender bei Personal = Marke | GS:217-270, :687-709, GM-097 |
| Gruppenlink | nur bei `claim_policy = transferable`; jeder angemeldete Nutzer mit Link belegt einen freien Platz („wer zuerst kommt“, E-28) | GS:276-330 |
| Join | (1) Formatprüfung, Rate-Limit; (2) Lock `group-seats:<id>` (Wartezeit ≤ 2 s, D-14-03, sonst `cc_group_busy`); (3) Token **unter dem Lock erneut** auflösen; (4) Gruppe `active` und gültig, sonst 409 `cc_group_not_active` (RBAC-055); (5) Nutzer hat bereits `active`-Platz → diesen zurückgeben (idempotent); (6) persönlich: bedingtes UPDATE (§1.2); Link: freie Plätze zählen, `INSERT`, **nach dem Insert nachzählen** (ältere Zeilen zuerst), bei Überbuchung löschen → `cc_group_full`; (7) Seat-Grants je `cc_group_course` anlegen (G-03), alles in **einer** TX mit dem Seat (R0-06 §4.8) | GS:333-417, GM-095/096 |
| Hinweis Doppelzugang | Vorschau meldet, wenn der Nutzer den Kurs bereits unbefristet über eine andere Quelle hat; Claim bleibt möglich (Bestätigung), damit der Besitzer keinen Platz unbemerkt verliert | LB `lb_access_exists` nur bei Voucher (INV-B-057) |

### 4.4 Entfernen, Platzreduktion, Platzerhöhung

| Aktion | Regel | LB |
|---|---|---|
| Platz freigeben | Besitzer/Admin, Mitglied ∈ Gruppe (sonst 404, RBAC-052); Seat → `removed`, Seat-Grants → `revoked` (`group_member_removed`); nie angenommene Einladung verliert sofort die Adresse; Lernhistorie bleibt; Platz sofort frei | GS:420-434, :496-504, GM-098 |
| **Platzreduktion** (Teilrefund R3, Admin) | unter Gruppen-Lock; überzählige Plätze in dieser Reihenfolge freigeben: **1. offene Einladungen, jüngste zuerst; 2. aktive Mitglieder, zuletzt beigetretene zuerst** (`ORDER BY (status='active'), COALESCE(claimed_at, invited_at) DESC, id DESC`); deren Grants → `revoked` (`group_seats_reduced`); Event `group.seats_changed` + je Platz `seat.removed`; Besitzer und Betroffene sehen den Grund | **GS:472-488, GM-101** (E-28) |
| Reduktion unter Belegung durch Admin | Vorschau zeigt vorab, wer den Platz verliert (Dry-run, `GrantPlan`, INV-B-006); Bestätigung erforderlich | neu |
| Platzerhöhung | nur `seats` erhöhen; keine Wirkung auf bestehende Plätze | – |

### 4.5 Gruppenstatus und Weitergabe an Seat-Grants

| Gruppe | Seat-Grants aktiver Plätze | Ausnahmen |
|---|---|---|
| `active` → `suspended` | → `suspended` (`group_suspended`) | Grants mit `manual_override` bleiben (GS:181-200, T158) |
| `suspended` → `active` | → `active` (`group_resumed`), nur Grants, die wegen der Gruppe gesperrt wurden | entfernte Plätze bleiben draußen (GM-100) |
| → `revoked` | → `revoked` (`group_revoked`) | – |
| `revoked` → Wiedereinsetzung | siehe §4.8 | – |
| `valid_until` geändert | übernehmen | `valid_until_locked` |

### 4.6 Transfer (Umverteilung) und Besitzerwechsel

- **Umverteilen** = Platz A freigeben (A verliert Zugang ab sofort, Historie bleibt) + neue Einladung an B. Kein direkter „Umschreiben“-Pfad, damit jeder Platzinhaber eine eigene Seat-Zeile und Grant-Revision hat. Grenzen der Umverteilung: D09-4.
- **Besitzerwechsel** nur mit `cc_manage_groups`, Begründung, Event `group.owner_changed`; alter Besitzer verliert Verwaltungsrecht sofort (RBAC-057). Delegierte Verwalter: Pro (D07-1).

### 4.7 Tokenrotation

- „Neuen Gruppenlink erzeugen“ (Besitzer/Admin): neuer Hash, alter sofort ungültig; persönliche Einladungen unberührt (GS:507-513). Audit `group.token_rotated` (kein Outbox-Event).
- „Einladung erneut senden“: neues Einzeltoken (§1.2).
- Bei `suspended`/`revoked`/abgelaufener Gruppe werden alle Tokens abgewiesen, aber nicht gelöscht (Wiederaufnahme ohne neue Mails möglich, solange nicht abgelaufen).
- **SOLL:** Rotation des Gruppenlinks bei Besitzerwechsel und bei Umstellung auf `invited_address_only` (Link wird dann deaktiviert).

### 4.8 Wiedereinsetzung einer widerrufenen Gruppe (E-29)

LB belebt Gruppe und Seat-Grants wieder (GS:83-91, :181-200). Course Captain: Bestätigte Rücknahme der Erstattung/Storno → `cc_group.source_revision + 1`, Gruppe `active`, Event `group.reinstated` mit Audit; jeder Platz, der **mit Grund `group_revoked`** endete und noch `active` ist, erhält einen neuen Seat-Grant mit `source_revision + 1` (G-15). Freigegebene Plätze bleiben draußen. Für Lernende wirkt das wie bisher, die Historie bleibt sauber.

### 4.9 Konto-Ereignisse

Mitglied löscht Konto → Platz `removed`, Adresse weg, Grants `revoked` (`account_deleted`). Besitzer löscht Konto → `owner_user_id = 0`, Gruppe läuft weiter, Admin verwaltet (GS:444-469, GM-102).

---

## 5. Refund-, Teilrefund-, Chargeback- und Storno-Matrix

Gilt für Kaufquellen (R3.1 Woo, R3.2 Voucher). **Grundsätze (MUSS):** nur der betroffene Grant bzw. Platz; kein Wiederaufleben (E-29); Lernfortschritt, Versuche und Zertifikate werden **nie** gelöscht oder umgeschrieben; Lernbeginn ändert den Trigger nicht (35.3); Refund ist **kein** Zertifikats-Widerrufsgrund; Free-Teilumfang bleibt nutzbar (LB QA-10). Die Erstattungsregel je Zuordnung (`refund_rule`) bleibt aus LB: `revoke_on_full_refund` (Standard), `revoke_on_any_refund`, `keep` (INV-B-029).

| Nr. | Quellereignis | Einzel-Grant (Käufer = Lernender) | Mehrplatzposition (Gruppe) | Lernhistorie | Zertifikat |
|---|---|---|---|---|---|
| RF-01 | Erstattung **beantragt** / Widerruf angefragt | keine Änderung (35.3) | keine | bleibt | bleibt |
| RF-02 | Volle Erstattung der Bestellung bestätigt | `revoked` (`order_refunded`); bei `keep` keine Änderung (EPL:107-108) | Gruppe `revoked` → alle Seat-Grants `revoked` | bleibt | bleibt gültig |
| RF-03 | Positionsbezogene volle Erstattung | `revoked` (`item_refunded`) | Gruppe `revoked` | bleibt | bleibt |
| RF-04 | Teilmenge einer Position erstattet (Menge) | Menge 1: wie RF-03; Menge > 1 ohne Gruppe: nur nach `revoke_on_any_refund`, sonst Hinweis „unklar“ im Plan | Plätze = Stück × (Menge − erstattete Menge) → Reduktion nach §4.4 | bleibt | bleibt |
| RF-05 | Betrag ohne Positionsbezug (Teilbetrag) | **keine** automatische Änderung; Warnung im Dry-run/Dashboard „manuell prüfen“ (35.3 „eindeutig betroffen“) | keine Platzänderung; Warnung | bleibt | bleibt |
| RF-06 | Erstattung wird zurückgenommen / Bestellung erneut bezahlt | **neue Revision** `active` (`refund_reversed`, G-15), alte bleibt `revoked`; nicht, wenn ein Betrag ohne Positionsbezug erstattet blieb (EPL:161-163) | Wiedereinsetzung §4.8 | bleibt | bleibt |
| RF-07 | Spätes `paid`/`processing` nach endgültigem `refunded` (Out-of-order) | ignoriert, `stale_event` protokolliert | ignoriert | – | – |
| RF-08 | Chargeback/Dispute eröffnet (on-hold nach Zahlung) | `suspended` (`payment_disputed`) | Gruppe `suspended` | bleibt | bleibt |
| RF-09 | Chargeback gewonnen | `active` (G-09), sofern kein manueller Hold | Gruppe `active` | bleibt | bleibt |
| RF-10 | Chargeback verloren | `revoked` (`chargeback_lost`), wie manueller Widerruf endgültig | Gruppe `revoked` | bleibt | bleibt; Betrugsverdacht → separater Prozess CORE-SPEC-006 |
| RF-11 | Storno vor Zahlung | `pending_confirmation` → `revoked` (`order_cancelled`) | keine Gruppe angelegt | – | – |
| RF-12 | Storno nach Zahlung | `revoked` (`order_cancelled`), auch bei `keep` (EPL:107; `commerce-test.php:145-152`) | Gruppe `revoked` | bleibt | bleibt |
| RF-13 | Zahlung fehlgeschlagen | `pending_confirmation` → `revoked` (`payment_failed`) | – | – | – |
| RF-14 | Position aus Bestellung entfernt | `revoked` (`item_removed`) | Gruppe `revoked` | bleibt | bleibt |
| RF-15 | Bestellung gelöscht/Papierkorb, Quelle nicht erreichbar, Connector deaktiviert | **keine** Änderung; Reconcile-Hinweis; kein Massenwiderruf (ACCESS-09, 35.2) | keine | – | – |
| RF-16 | Gutschein **vor** Commit freigegeben/abgelaufen (`released`) | `pending_confirmation` → `revoked` (`released`) | – | – | – |
| RF-17 | Gutschein **nach** Einlösung storniert (`void`) | nur der Voucher-Grant `revoked` (`voucher_voided`, E-35); andere Quellen bleiben | – (E-36) | bleibt | bleibt |
| RF-18 | Kauf des Gutscheins erstattet (Voucher `void` nach Commit, R0-36 C6) | wie RF-17 | – | bleibt | bleibt |
| RF-19 | Gesetzlicher Widerruf digitaler Inhalte / freiwillige Kulanz | entscheidet der Shop; Course Captain reagiert nur auf den bestätigten Refund-Status (RF-02…RF-05) | wie dort | bleibt | bleibt |

**Wirkung auf laufende Prüfungen:** §6 R-05 und D09-2. **Anzeige:** Lernende sehen „beendet“ bzw. „vorübergehend nicht verfügbar“ mit Supportweg; ihr Verlauf und ihre Ergebnisse bleiben lesbar (403 `cc_content_locked` nur für neue Lerninhalte, eigene Versuche bleiben einsehbar, R0-07 P-08).

---

## 6. Race Conditions

### 6.1 Lock- und Unique-Strategie

| Ebene | Mittel | Zweck |
|---|---|---|
| L1 Lernender | `NamedLock learner:<user_id>` | Kontolöschung, Übertragung, Merge (später); bei zwei Konten aufsteigend nach ID |
| L2 Quelle | `NamedLock grant-source:<source_type>:<source_id>` | alle Ereignisse eines Quellobjekts nacheinander (Planer-Diff konsistent) |
| L3 Gruppe | `NamedLock group-seats:<group_id>` | Einladung, Join, Entfernen, Reduktion, Statuswechsel |
| L4 Kette | `NamedLock grant-chain:<user_id>:<course_id>` | Laufzeitberechnung `extend` und Restack |
| DB | kurze TX innerhalb des Locks; CAS `row_version`; Uniques `grant_source`, `group_source`, `claim_token_hash`, `join_token_hash`, `connector_event`, `grant_source_event` | letzte Verteidigungslinie |

**Lock-Reihenfolge (MUSS):** L1 → L2 → L3 → L4 → DB-TX. Kein Lock wird innerhalb einer offenen TX erworben. Wartezeiten interaktiv ≤ 2 s, Hintergrund ≤ 10 s (D-14-03). `GET_LOCK` liefert NULL (nicht verfügbar) → LB-Verhalten: weiter ohne Lock, protokolliert (NL:42-68); deshalb **MUSS** jede Invariante zusätzlich per Unique, CAS oder Nachzählung gesichert sein. Lock-Namen werden wie LB je DB und Präfix gehasht (NL:112-116). Eine einzige Lock-Infrastruktur (NamedLock + Option-Lock aus LB zusammenführen, B §6 Pflicht).

### 6.2 Szenarien

| Nr. | Szenario | Strategie | Erwartung |
|---|---|---|---|
| R-01 | 50 parallele Joins auf den letzten Platz (Gruppenlink) | L3 + Nachzählung nach Insert + TX Seat+Grant | genau 1 Platz, 1 Grant-Satz; Rest `cc_group_full`/`cc_group_busy` |
| R-02 | Dasselbe Konto joint doppelt (Doppelklick, zwei Tabs) | L3 + Prüfung „aktiver Platz vorhanden“; optional Unique über generierte Spalte (Δ-5) | 1 Platz; zweite Antwort = bestehende Mitgliedschaft |
| R-03 | Persönliche Einladung von zwei Konten gleichzeitig eingelöst | bedingtes UPDATE (`affected_rows = 1`) | 1 Gewinner, anderer `cc_claim_used` |
| R-04 | Webhook doppelt/out-of-order (`paid` und `refunded` parallel bzw. vertauscht) | Inbox-Unique; L2; `source_seq` (Δ-4): Ereignis mit Version ≤ zuletzt angewendeter → `stale_event`; Guard nach §2.3 | exakt 1 Grant; Endzustand entspricht der höchsten Quellversion; kein Wiederaufleben |
| R-05 | Refund/Platzentzug **während laufender Prüfung** | Jede Schreibaktion (`saveAnswer`, `flag`, `reveal`) prüft den effektiven Zugang neu → 403 `cc_content_locked` (`revoked`) (LB ES:1044-1057); Abgabe nach D09-2 | gespeicherte Antworten bleiben; kein Datenverlust; Ergebnis/Zertifikat nach D09-2 |
| R-06 | Refund während Zertifikatsausstellung | Ausstellung prüft nur Evidenz und D09-2-Regel, nicht den späteren Grant; Unique `evidence_rev` | höchstens 1 Zertifikat; ausgestelltes bleibt gültig |
| R-07 | Doppel-Grant aus zwei verschiedenen Quellen für denselben Kurs | gewollt: zwei Grants (Mehrfachzugang) | 2 Zeilen, Vereinigung; Widerruf einer lässt die andere |
| R-08 | Gleicher Quellschlüssel, verschiedene Nutzer (Voucher-Key, Manipulation) | Unique + Vergleich | `cc_idempotency_conflict`, kein zweiter Grant (GM-088) |
| R-09 | Platzreduktion während Join/Einladung | beides unter L3 | Belegung ≤ Plätze nach Abschluss beider Operationen |
| R-10 | Ablauf-Cron gegen manuelle Verlängerung | CAS + Cron-UPDATE mit `status='active' AND valid_until < now AND row_version = ?` | Verlängerung gewinnt oder Cron gewinnt und Verlängerung wird als G-15 behandelt; nie „aktiv mit Enddatum in der Vergangenheit“ |
| R-11 | Restack gleichzeitig mit Refund in derselben Kette | L4 um Laufzeitberechnung | Summe der Laufzeiten unverändert, keine Überlappung |
| R-12 | Kontolöschung während Join/Grant | L1 im Lösch-Handler; Join prüft Konto innerhalb der TX | kein Grant/Platz auf gelöschtem Konto |
| R-13 | Gruppe gesperrt während Join | Statusprüfung unter L3 (Schritt 4) | Join abgewiesen oder Grant wird mit der Gruppe gesperrt; nie aktiver Grant in gesperrter Gruppe |

### 6.3 Testkatalog

Spalte **Art**: U = Unit, I = Integration (WP-Testsuite), P = Paralleltest (PHP-Race-Runner, R0-14 L5), E = E2E.

#### Grants

| Test-ID | Art | Gegeben / Aktion | Erwartung | Ziel-Task | Bezug |
|---|---|---|---|---|---|
| GRANT-001 | U | Übergangstabelle: jede (Von, Nach)-Kombination | nur G-01…G-15 erlaubt, Rest `ignored`/Exception | R1-13 | §2.3 |
| GRANT-002 | I | manuell anlegen, Formular doppelt mit gleichem `request_key` | 1 Grant, 1 Event, 1 Outbox-Eintrag | R1-13 | §2.2, GM-087 |
| GRANT-003 | I | manuell ohne Begründung / Enddatum in der Vergangenheit | abgewiesen, keine Zeile | R1-13 | GM-087 |
| GRANT-004 | I | FakeSource-Ereignis zehnmal | 1 Grant (CORE-03) | R1-13 | GM-088 |
| GRANT-005 | P | 2…50 Prozesse, gleicher Quellschlüssel | 1 Grant; andere erhalten ihn oder `cc_temporary_failure` | R1-42 | R-08, GM-088 |
| GRANT-006 | I | gleicher Schlüssel, anderer Nutzer | `cc_idempotency_conflict` | R1-13 | R-08 |
| GRANT-007 | I | `revoked` (Refund), dann `paid` | bleibt `revoked`, `stale_event`/`ignored` | R1-13 (FakeSource), R3-03 | E-29, RF-07 |
| GRANT-008 | I | Refund zurückgenommen (FakeSource `reinstate`) | neue Revision `source_revision = 2` `active`, alte `revoked`; Kettenposition unverändert | R1-13, R3-03 | G-15, Δ-3 |
| GRANT-009 | I | `expired` „verlängern“ | neue Revision; alte bleibt `expired` | R1-13 | RBAC-046 |
| GRANT-010 | I | zwei Quellen, eine widerrufen | Zugang bleibt; **kein** `access.changed` | R1-13 | CORE-04, GM-085, §3.4 |
| GRANT-011 | I | manuell + Gruppe + Free-Regel | Vereinigung Scopes, spätestes Ende, unbefristet gewinnt | R1-13 | GM-092 |
| GRANT-012 | I | `extend` 30 d an bestehenden 30-d-Grant; dann ersten erstatten | zweiter rückt vor, Laufzeit 30 d bleibt; Rücknahme → Rückverschiebung | R1-13 | GM-084/089, ESV:165-206 |
| GRANT-013 | I | `extend` mit abweichenden Scopes | Beginn sofort (parallel) | R1-13 | §3.3 |
| GRANT-014 | I | Zeitgrenzen: `now = valid_until`, `now = valid_until + 1 s`, Cron nicht gelaufen | Zugang / kein Zugang unabhängig vom Cron | R1-14 | B-6, AS:408-409 |
| GRANT-015 | I | manuelle Sperre, dann Quell-`resume` | bleibt `suspended`; Quell-`revoke` setzt sich durch | R1-13 | §2.3 Vorrang |
| GRANT-016 | I | `pending_confirmation` + Quell-`suspend` | ignoriert, Event protokolliert | R1-13 | EPL:165-167 |
| GRANT-017 | I | CAS-Konflikt: zwei Übergänge mit gleicher `row_version` | einer gewinnt, anderer neu bewertet; kein verlorenes Event | R1-02, R1-42 | §2.4 |
| GRANT-018 | I | Übergang bricht nach Grant-Update ab (simulierter Fehler vor Outbox) | Rollback: weder Grant- noch Event- noch Outbox-Zeile | R1-02 | §2.4 |
| GRANT-019 | I | Lernsperre gesetzt und aufgehoben | Grants unverändert; Zugang sofort zurück | R1-03 | E-79, RBAC-050 |
| GRANT-020 | I | `failed` → Retry mit gleichem Schlüssel | gleiche Zeile, Event `grant.retried` | R1-13, R3-10 | G-13 |
| GRANT-021 | I | Connector deaktiviert | bestehende Grants unverändert; neue Ereignisse `pending`/abgewiesen | R1-13, R3-10 | ACCESS-09, RF-15 |
| GRANT-022 | E | „Mein Lernen“ mit pending/suspended/expired/aktiv | Statussprache 31.2, Rangfolge §3.2 | R1-16 | GM-091 |
| GRANT-023 | I | Woo-Matrix RF-01…RF-15 (je Zeile ein Fall) | Ergebnis laut Matrix | R3-03 | §5 |
| GRANT-024 | I | Voucher reserve → commit / release / void nach Commit | `pending` → `active` / `revoked` / nur Voucher-Grant `revoked` | R3-04 | RF-16…18, E-35 |
| GRANT-025 | P | Woo `paid` und `refunded` parallel, beide Reihenfolgen | Endzustand = höchste Quellversion | R3-03, R1-42 | R-04 |
| GRANT-026 | P | Voucher-Reserve/Grant/Commit parallel, gleicher und verschiedener Key | genau 1 Einlösung je Key | R3-05 | R0-36 C10 |
| GRANT-027 | I | Prüfung läuft, Grant widerrufen; `saveAnswer`, dann `submit` | Antwort abgewiesen (`revoked`); Abgabe nach D09-2 | R1-08, R1-14 | R-05 |
| GRANT-028 | I | Zertifikat ausgestellt, danach Refund | Zertifikat `issued`, Verifikation unverändert | R1-10/R1-35 | 35.3 |
| GRANT-029 | I | Kundenwechsel in der Quelle | alter `revoked` (`source_subject_changed`), neue Revision für neuen Kunden, Audit | R3-03 | D09-6 |

#### Seats/Gruppen

| Test-ID | Art | Gegeben / Aktion | Erwartung | Ziel-Task | Bezug |
|---|---|---|---|---|---|
| SEAT-001 | I | Gruppe manuell anlegen (doppelt abgeschickt) | 1 Gruppe, Besitzer ohne Grant | R1-13, R1-35 | E-17, GM-106 |
| SEAT-002 | I | Einladung reserviert letzten Platz; weitere Adressen | übersprungen, keine Mail | R1-13 | GM-097 |
| SEAT-003 | P | 50 Joins auf letzten Platz über Gruppenlink | 1 Platz | R1-42 | R-01, GM-096 |
| SEAT-004 | P | gleicher Nutzer joint 20× parallel | 1 Platz, 1 Grant je Kurs | R1-42 | R-02 |
| SEAT-005 | P | persönliche Einladung von 2 Konten parallel | 1 Gewinner | R1-42 | R-03 |
| SEAT-006 | I | `GET_LOCK` liefert NULL / Lock belegt (Timeout 0) | ohne Lock: Nachzählung hält Grenze, Log-Eintrag; belegt: `cc_group_busy`, nichts geschrieben | R1-42 | GM-096, NL:42-68 |
| SEAT-007 | I | Einladung nach 30 d | belegt keinen Platz, Token ungültig, Cron → `expired`, Adresse nach +30 d gelöscht | R1-13, R1-17 | GM-099 |
| SEAT-008 | I | erneut senden | altes Token ungültig, neues gültig, Budget gezählt | R1-13 | §1.2 |
| SEAT-009 | I | Platz freigeben | Grants `revoked`, Lernhistorie bleibt, Platz neu belegbar mit **neuer** Seat-Zeile | R1-13 | GM-098 |
| SEAT-010 | I | 6 Plätze, 2 Einladungen + 4 Mitglieder, Reduktion auf 3 | erst 2 Einladungen (jüngste zuerst), dann das zuletzt beigetretene Mitglied | R1-13, R3-03 | GM-101, GS:472-488 |
| SEAT-011 | I | Reduktion per Admin mit Vorschau | Vorschau nennt Betroffene, schreibt nichts | R1-35 | §4.4 |
| SEAT-012 | I | Gruppe aussetzen / fortsetzen | Seat-Grants folgen; entfernte bleiben draußen; manuell entschiedene unberührt | R1-13 | GM-100 |
| SEAT-013 | I | Gruppe widerrufen und wiedereinsetzen | neue Revisionen nur für Plätze mit Grund `group_revoked` | R1-13, R3-03 | §4.8 |
| SEAT-014 | I | Gruppenlink rotieren | alter Link 404/ungültig, persönliche Einladungen gültig | R1-13 | §4.7 |
| SEAT-015 | I | `invited_address_only`, fremdes Konto | 403 `cc_invite_other_account`; Gruppenlink deaktiviert | R1-03 | RBAC-054, E-28 |
| SEAT-016 | I | Join in gesperrter/abgelaufener Gruppe | 409 `cc_group_not_active` | R1-03 | RBAC-055, R-13 |
| SEAT-017 | I | Besitzer sieht Mitglieder | Status, Name, maskierte Adresse; keine Fortschritte | R1-35 | E-81, RBAC-059 |
| SEAT-018 | I | Besitzerwechsel | alter Besitzer sofort 404 | R1-03 | RBAC-057 |
| SEAT-019 | P | Reduktion parallel zu Join | Belegung ≤ Plätze | R1-42 | R-09 |
| SEAT-020 | I | Mehrplatzposition Menge 2 × 3 Plätze, Teilrefund 1 Stück | 3 Plätze, Reduktion nach SEAT-010 | R3-02, R3-03 | GS:138 |
| SEAT-021 | I | Gruppenname mit URL/Adresse | bereinigt | R1-35 | GS:521-531 |
| SEAT-022 | I | Einladungsbudget überschritten | abgewiesen, keine Mail | R1-35 | GS:262-270 |

#### Identität

| Test-ID | Art | Gegeben / Aktion | Erwartung | Ziel-Task | Bezug |
|---|---|---|---|---|---|
| ID-001 | I | Quelle meldet E-Mail eines bestehenden Dritten | kein Grant, kein Link zum Konto (CORE-01) | R1-13 (FakeSource), R3-02 | ID-02 |
| ID-002 | I | Token im Klartext in DB, Logs, Audit, Export, Outbox suchen | 0 Treffer; nur Hash | R1-02, R1-17 | §1.2 |
| ID-003 | I | Vorschau-GET mit gültigem Token | keine Zustandsänderung | R1-13 | §1.2 |
| ID-004 | I | Claim ohne Nonce / per GET | 403/405, nichts geändert | R1-03 | R0-07 P-04 |
| ID-005 | I | Brute Force auf Token-Endpunkt; Zähler-Speicher fällt aus | Rate-Limit greift; fail-closed | R1-03, R0-17 | E-46 |
| ID-006 | E | Gast öffnet Einladung, registriert sich | Rücksprung zur Einladung, fremdes Ziel ignoriert | R1-13, R1-16 | GM-104 |
| ID-007 | I | Einladung auf anderem Konto angenommen (`transferable`) | Hinweis mit maskierter Adresse vor Annahme; Annahme möglich | R1-13 | T132 |
| ID-008 | I | E-Mail-Wechsel des Lernenden | Grants/Seats unverändert | R1-13 | 35.1 |
| ID-009 | I | Mitglied löscht Konto | Platz frei, Adresse weg, Grants `revoked`; Abgleich belebt nicht | R1-17 | GM-102, GM-177 |
| ID-010 | I | Besitzer löscht Konto | Gruppe läuft, `owner_user_id = 0`, Admin verwaltet | R1-17 | GM-102 |
| ID-011 | I | Kontolöschung mit Kauf-Grant und manuellem Grant | Kauf-Grant pseudonymisiert behalten, manueller gelöscht (§7) | R1-17 | INV-C-158 |
| ID-012 | I | Support „Zugang übertragen“ | alter `revoked` (`transferred`), neuer Grant Revision +1 auf Ziel, beide im Audit; Historie bleibt beim Ursprung | R1-13, R1-38 | D09-3 |
| ID-013 | I | Datenschutz-Export eines Lernenden und eines Besitzers | eigene Grants/Plätze; Besitzer: Plätze ohne fremde Klartextadressen, ohne Tokens | R1-17 | GM-103, §7 |
| ID-014 | P | Kontolöschung parallel zu Join | kein Platz/Grant auf gelöschtem Konto | R1-42 | R-12 |
| ID-015 | I | Gast-Claim (`c`): abgelaufen, erneut, falsches Konto, erfolgreich | `pending_claim` bleibt / neues Token / Hinweis / `active` | R3-02 (nach E-30-Erweiterung) | §1.2 |

---

## 7. Privacy: Identitätsdaten, Löschung und Aufbewahrung

| Datum | Ort | Zweck | Sichtbar für | Löschung / Aufbewahrung |
|---|---|---|---|---|
| WP-User-ID des Lernenden | `cc_grant.user_id`, `cc_seat.user_id`, `cc_grant_event.actor_id` | Zugang, Historie | Lernender, Admin, Support (minimiert), Trainer nur zugewiesen | Kontolöschung: Grants `revoked`; **Kaufquellen** (`woo_order`, `voucher`) pseudonymisiert (`user_id = NULL`, `subject_ref = HMAC`) bis Ablauf der Zugangs-Retention (E-44: 10 Jahre); **übrige Quellen** (`manual`, `group_seat`, `workflow`) samt Events löschen. Audit-Einträge pseudonymisiert (E-44: 4 Jahre) |
| Einladungsadresse | `cc_seat.invite_email` | nur Zustellung | Besitzer: Klartext nur bei offener Einladung, sonst maskiert (E-81, D07-4) | bei Claim sofort leeren (Δ-6); bei Entfernen ohne Konto sofort; abgelaufene Einladung 30 d nach Ablauf (LB GS:665-684) |
| Token | nur Hash in `cc_seat`, `cc_group` | Claim | niemand (nicht exportiert) | bei Claim/Rotation/Ablauf NULL |
| Gruppenname | `cc_group.name` | Anzeige, Mail | Mitglieder, Eingeladene | bereinigt; bei Besitzerlöschung neutralisieren, wenn er den Personennamen enthält (LB INV-C-152) |
| Besitzerbezug | `cc_group.owner_user_id` | Verwaltung | Admin | bei Kontolöschung `0` |
| Käuferreferenz | `cc_grant.source_id`, `cc_group.source_id` (Bestell-UID) | Abgleich mit Quelle | Admin, Support | wie Grant; keine Kopie von Namen/Adresse/Zahlungsdaten (R0-06 E-38) |
| Inbox-Payload | `cc_inbox_event.payload` | Dedupe, Diagnose | Admin (Diagnose, redigiert) | Minimalpayload ohne E-Mail/Name; Frist nach D09-5 |
| Outbox-Payload | `cc_outbox_event.payload` | Zustellung | Partner | nur UIDs und Status (§2.5); Frist nach D09-5 |
| Lernfortschritt, Versuche | R0-08-Tabellen | Lernen, Nachweis | Lernender, zugewiesene Trainer | **unabhängig** vom Grant; Widerruf ≠ Löschung (31.3 Nr. 3); Retention E-51 |
| Zertifikat | `cc_certificate` | Nachweis | Lernender, öffentliche Minimalverifikation | Refund ändert nichts; Retention E-51 und CORE-SPEC-006 |

**Pseudonymisierung (MUSS):** HMAC-SHA-256 mit Site-Geheimnis statt bloßem `sha256(user_id)` (LB `JSON_SET` mit sha256, INV-C-152), weil fortlaufende IDs per Durchprobieren rückrechenbar sind.

**Export (WP-Exporter):** eigene Grants (Kurs, Status, Laufzeit, Quelltyp, ohne Quellinterna), eigene Plätze (Gruppenname, Status), als Besitzer eigene Gruppen mit Platzstatus und **maskierten** Adressen Dritter, keine Tokens, keine Idempotenzschlüssel (R0-07 §4.9). **Eraser:** in einer TX (LB INV-C-152); Kontolöschung nur auf Antrag/Opt-in (E-43).

---

## 8. Entscheidungsbedarf (nur neue, nicht in `ENTSCHEIDUNGEN.md`)

| Nr. | Frage | Optionen | Empfehlung | Wer | Blockiert |
|---|---|---|---|---|---|
| **D09-1** | **Gruppenlink nur als Hash?** Konzept 35.1 verlangt gehashte Claim-Tokens; ein Gruppenlink ist aber mehrfach nutzbar, und Besitzer wollen ihn später erneut kopieren (LB zeigt ihn dauerhaft, Klartext in der DB). | a) nur Hash; Link nur direkt nach Anlage/Rotation sichtbar, „Link erneut anzeigen“ = Rotation; b) reversibel verschlüsselt (Schlüssel aus WP-Salts), jederzeit anzeigbar; c) Klartext wie LB | **a)** – gleiche Regel für alle Tokens, kein Schlüsselmanagement; UX-Hinweis „Link nur jetzt kopierbar“ | Auftraggeber + Architektur | R1-02, R1-13, R1-35 |
| **D09-2** | **Prüfungsabgabe nach Zugangsverlust** (Refund/Platzentzug während des Versuchs). LB sperrt weitere Antworten, wertet bei `submit` aber die gespeicherten Antworten ohne Zugangsprüfung (ES:518-525, :1044-1057). | a) LB: Abgabe erlaubt und gewertet, Zertifikat wie sonst; b) Abgabe gewertet, **automatisches** Zertifikat nur, wenn bei Start **und** Abgabe ein Grant mit `exam` aktiv war, sonst Ausstellung nur manuell; c) Versuch wird `cancelled` | **b)** – kein Datenverlust, Ergebnis historisch korrekt, aber kein neuer Nachweis aus einem bereits widerrufenen Anspruch; bestehende Zertifikate bleiben unberührt (35.3) | Auftraggeber | R0-08, R1-08, R1-10 |
| **D09-3** | **Kontozusammenführung in R1?** ID-05 verlangt sie konzeptionell, LB hat keine. | a) R1 nur Support-Aktion „Zugang übertragen“ (Revision +1, Audit), Merge-Werkzeug später nach Vertrag §1.3; b) volles Merge inkl. Fortschritt in R1; c) gar nichts | **a)** – deckt Fehlzuordnungen ab, ohne Lernhistorie und Zertifikate umzuschreiben | Auftraggeber | R1-13, R1-38 |
| **D09-4** | **Grenzen der Platz-Umverteilung** (Missbrauch: ein Platz wird nacheinander für viele Personen genutzt). LB hat keine Grenze. | a) keine Grenze (LB), nur Audit und Kennzahl „Umverteilungen je Platz“ im Dashboard; b) feste Grenze (z. B. 3 je Platz und Laufzeit); c) keine Umverteilung nach Zertifikat/Prüfungsbestehen | **a) in Free**; b)/c) als Pro-Gruppenregel | Auftraggeber | R1-13, R1-35, R0-29 |
| **D09-5** | **Aufbewahrung Inbox/Outbox** (R0-06 A-18 offen, E-44 nennt sie nicht) | a) 90 Tage für `done`/`ignored`/`delivered`, `failed`/`quarantined` bis zur Klärung + 90 Tage; b) wie Audit 4 Jahre; c) 30 Tage | **a)** (LB hält Dispute-Dedupe 90 Tage, B §3.4); Payload minimal | Auftraggeber (Datenschutz) | R0-31, R1-17 |
| **D09-6** | **Kundenwechsel in der Quelle** (Woo-Bestellung wird einem anderen Konto zugeordnet). LB hängt den Grant still um (EPL:144-146). | a) alter Grant `revoked` (`source_subject_changed`), neue Revision für neues Konto, Audit + Dashboard-Hinweis; b) wie LB umhängen; c) ignorieren, nur Hinweis | **a)** – passt zu 35.1 und E-29; Historie bleibt beim alten Konto | Auftraggeber | R3-02, R3-03 |

**Keine neue Entscheidung, nur Abhängigkeit:** Pending-Timeout und Eskalation → 31.7 Nr. 2 / SEAM-006; Gast-Claim statt Kontopflicht → E-30; Seat-Regeln → E-28; Wiederaufleben → E-29; Lernsperre → E-79; Adresssichtbarkeit → E-81/D07-4; Retention Grants/Audit → E-44, Lern-/Prüfungsdaten → E-51; Abweichungen von LB-Golden-Master (GM-100 Wiederaufleben, `extend()` nach Ablauf, Kundenwechsel) → Freigabe über die Abweichungsliste nach E-55.

---

## 9. Deltas zu anderen R0-Dokumenten

| Nr. | Dokument | Delta |
|---|---|---|
| Δ-1 | R0-06 `cc_group` | `source_revision int unsigned NOT NULL DEFAULT 1` (Wiedereinsetzung §4.8); `claim_policy varchar(24) NOT NULL DEFAULT 'transferable'` (E-28); `join_token_hash` bleibt UNIQUE |
| Δ-2 | R0-06 `cc_grant` | `manual_override varchar(16) NULL` (`suspended`/`approved`) mit Akteur/Zeit im Event statt LB-`meta.manual`; `valid_until_locked tinyint(1) NOT NULL DEFAULT 0` (statt `meta.extended`, T158) |
| Δ-3 | R0-06 `cc_grant` | `paid_at datetime NULL`, `chain_anchor_at datetime NULL`, `renewal_mode varchar(16) NOT NULL DEFAULT 'extend'` als Spalten (Restack ohne JSON-Scan); Index `chain (user_id, course_id, renewal_mode, chain_anchor_at)` |
| Δ-4 | R0-06 `cc_grant` | `source_seq bigint unsigned NOT NULL DEFAULT 0` (zuletzt angewendete Quellversion, R-04) |
| Δ-5 | R0-06 `cc_seat` | optional `active_user_id` als STORED generated column `IF(status='active', user_id, NULL)` mit `UNIQUE (group_id, active_user_id)`; nur wenn `dbDelta`/R1-02 es tragfähig abbilden, sonst Lock + Prüfung (R0-06 §4.4 Hinweis) |
| Δ-6 | R0-06 `cc_seat` | `invite_email` wird beim Claim geleert; `claim_expires_at` statt Berechnung aus `invited_at` (bereits Spalte) |
| Δ-7 | R0-06 `cc_grant` | `user_id NULL` zusätzlich erlaubt für `revoked` + `revocation_reason = account_deleted` (pseudonymisiert), plus `subject_ref char(64) NULL` (HMAC) |
| Δ-8 | R0-06 §4.8 | Transaktionsgrenze „Platz beanspruchen“ um Lock-Reihenfolge §6.1 ergänzen |
| Δ-9 | R0-06 `cc_grant` (R3, nach E-30-Erweiterung) | `claim_token_hash char(64) NULL` UNIQUE, `claim_expires_at datetime NULL` für Gast-Claim `c` |
| Δ-10 | R0-19 | Events §2.5; Fehlercodes `cc_idempotency_conflict`, `cc_conflict`, `cc_claim_used`, `cc_claim_expired`, `cc_group_full`, `cc_group_busy`, `cc_group_not_active`, `cc_invite_other_account`, `cc_invalid_token` |
| Δ-11 | R0-07 | M-17 um `claim_policy` und Rate-Limit fail-closed präzisieren; Aktion „Zugang übertragen“ unter `cc_manage_grants` + Pflichtbegründung + Audit (S-13/`cc_grant_change`) |
| Δ-12 | R0-05 | GM-086/GM-100 und GM-087 („Verlängern“ nach Ablauf) auf neue-Revision-Semantik umstellen; GM-101 bleibt (LB-Regel übernommen); Abweichungsliste (E-55) um §2.3 „Verbotene Übergänge“ und D09-6 ergänzen |
| Δ-13 | R0-08 | Versuchsabgabe nach Zugangsverlust nach D09-2; Schreibaktionen prüfen effektiven Zugang je Aufruf |

---

## 10. Nachweis

- Gelesen (LB @ `9f37c77`, nur lesend): ESV:121-210, :332-382, :387-423; EPL:127-170; GS:20-40, :60-148, :150-207, :217-256, :276-312, :333-530, :560-590, :636-660, :711-765; AS:200-240, :315-333, :391-420; NL:8-68, :112-116; ES:400-410, :518-560, :1030-1060.
- Projektdokumente: TODO R0-09, R1-13, R3-03, CORE-SPEC-001…006, SEAM-001/101; Konzept 31.2, 31.3, 34.1, 34.3, 35.1–35.5; R0-02 B §1.3, §2.1–2.6, §3, §5, §6; R0-06 §1, §3.4, §4.0, §4.4–4.6, §4.8, §4.9, §11, Nachträge; R0-07 §1–3, §4.8–4.9, §5 Grant/Gruppe, §7, §8; R0-04 K-01…K-04; R0-05 §1.8–1.9, §2.8; R0-14 RB-04, L5; R0-36 P4, C2, C6, C9; ENTSCHEIDUNGEN E-17, E-26…E-30, E-35, E-36, E-44, E-46, E-51, E-55, E-79, E-81.
- Keine Änderung an LearnBoating, keine Implementierung, kein Commit.
