# R0-40 · Vertrag für finanzielle Suite-Ereignisse

**Status:** Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet. Keine Implementierung, kein Code, kein Commit. Tabellen- und Spaltennamen folgen R0-06/R0-09 (Präfix `ccap_` nach E-207).
**Stand:** 16.11.2026
**Bezug:** TODO R0-40 (DoD: Course meldet keine doppelte Provision/Kauftransaktion; Voucher-/Order-/Grant-Quellen, Fehlercodes, Idempotenz und Refund eindeutig zugeordnet), CORE-SPEC-004/-005, R3-02…R3-05, R3-19; Konzept Kap. 32, 34.3 (ACCESS-02…09), 34.4 (COMMERCE-01…10), 35.1–35.5; `R0-09` (§2 Grant-Automat G-01…G-15, §5 RF-01…RF-19, §6 R-01…R-13), `R0-19` (§3 Fehlerkatalog, §3.4 Einlösecodes, §5 Events/Outbox, §5.8 GrantSourceAdapter), `R0-34` (Integrationsstatus), `R0-36` (§3 Voucher C1–C12, §4 Affiliate A1–A10, §2.6 Pass), `R0-02-inventar/B-commerce.md` (INV-B-002/003, 100–105, 106–131, 146–167), INV-C-184.
**Quellen (nur gelesen, nicht verändert):** `wp-couponmaster@2d84943` (`CONNECTORS.md`, `API.md`, `src/API/V1/RedemptionService.php`), `wpaffiliatemaster@3b7130e` (`docs/CONTRACT.md`, `src/Integrations/WooCommerce.php`, `src/Integrations/WooCommerce/OrderSync.php`), `wp-passmaster@b2b88ea` (über R0-36 §2.6). Clean Room nach E-01: LB-Stellen werden nur als **Verhalten** zitiert.

**Verbindliche Beschlüsse (Beschlussprotokoll 16.11.2026):** E-01 Clean Room · E-25/E-25a Bundle in R1 Free, **Mitgliederrabatt im Woo-Adapter R3.1** · E-26 abhängige Zusatz-Grants in R1 · E-27 **Zugang je Modul** (`module_id` im Grant-Schlüssel) · E-29 kein Wiederaufleben · E-30 Kontopflicht in R3.1 · E-31 **Gutscheinziel = neutrales Angebot oder Woo-Produkt** · E-32 keine Garantie, nur neutrales Nutzungsereignis · E-33 **deutsche Shop-Rechtspflichten und PayPal/Klarna-Dispute im Woo-Adapter R3.1** · E-34 **Woo-Produkte automatisch anlegen und pflegen** · E-35 Voucher-Storno entzieht nur den Voucher-Grant · E-38 öffentliche Events/Registry Pro · E-39/E-39b Quelle `workflow` im Pro-Connector.
**Von Claude im Auftrag entschieden:** E-36 ein Code je Platz · E-37 keine Affiliate-Meldung aus Course · E-53 Woo-/Voucher-Connector = Pro · E-93 Grants/Widerrufe laufen bei abgelaufener Pro-Lizenz weiter · E-113 Prüfungsabgabe nach Zugangsverlust gewertet, Auto-Zertifikat nur bei Grant zu Start **und** Abgabe · E-116 Inbox/Outbox 90 Tage · E-117 Kundenwechsel = neue Revision · E-201 Pending-Timeout 24 h · E-203 Mitgliedschaft = Grant-Quelle über GrantSourceAdapter · **E-275…E-284** (früher D40-1…D40-10, §7).

## Beschlussstand

| Beschluss | Änderung in diesem Dokument |
|---|---|
| E-27 | Grant-Schlüssel mit `module_id NOT NULL DEFAULT 0` ist in R0-06/R0-09 übernommen (Δ40-1 eingearbeitet) |
| E-26 | Ziele können eine Basiskurs-Abhängigkeit tragen (§2.2) |
| E-31 | Voucher-Zieltypen A (neutrales Angebot) und B (Woo-Produkt) bleiben spezifiziert (§2.3) |
| E-33, E-34, E-25a | Dispute-Webhooks, Zustimmungs-Gate, Auto-Produkte und Mitgliederrabatt im Woo-Adapter (§4) |
| E-38 | Course-Events an Partner nur über Pro; im Free-Kern nur interne Outbox (§0, §1.1) |
| E-275 (= D40-1), **E-295** | verlorener Dispute setzt den Woo-Status: `refunded`, wenn Geld zurückgebucht wurde; `cancelled` nur, wenn die Bestellung vor Zahlung storniert wurde (§4.4) |
| E-276 (= D40-2), **E-293** | unbezahlte Woo-Bestellung erzeugt **keinen** Grant, auch nicht `pending_confirmation`; kein Pending-Timeout dafür; E-201 gilt nur für Quellen mit bestätigter Zahlung, aber ausstehender Course-Verarbeitung (§4.2) |
| E-277 (= D40-3), **E-294** | hängende Einlösung > 24 h: Reservierung freigeben, Grant `failed` – endgültig für diesen Idempotenzschlüssel; ein neuer Einlöseversuch erzeugt einen neuen Schlüssel (§3.4) |
| **E-301** | Membership-Adapter ist eigene Task **R3-20** (§2.4, FIN-080/081) |
| **E-305** | Voucher-Kanal: neutrales Ziel (Zieltyp A) über Kanal `course-captain` (Einlöse-API) – so in §2.3 umgesetzt. Woo-Produkt-Ziel (Zieltyp B) über den Woo-Checkout von Voucher Captain: steht im Widerspruch zu E-279 („nur über die Course-Einlösung, nicht im Warenkorb“) und zur Zieltyp-B-Zeile in §2.3 (keine Woo-Bestellung, Quelle `voucher`); §2.3 bleibt bis zur Klärung unverändert (**neue Frage NF-02**, R0-GATE-protokoll.md) |
| E-278 (= D40-4) | keine Guthaben über die Einlöse-API (§2.3, §3.5) |
| E-279 (= D40-5) | Kursgutscheine nur über die Course-Einlösung, nicht im Warenkorb (§2.3, FIN-018/055) |
| E-280 (= D40-6) | Preis pflegt der Shop in Woo; Auto-Produkte entstehen als Entwurf (§4.5) |
| E-281 (= D40-7) | Mitgliederrabatt nicht mit Rabattcodes kombinierbar; der bessere Rabatt gilt (§4.6) |
| E-282 (= D40-8) | Kursprodukte einzeln verkauft; mehrere Plätze nur über Gruppenprodukte (§2.2) |
| E-283 (= D40-9) | Betreiberaktion „Gutscheinwert zurückgeben“ mit Begründung und Audit (§1.2, §3.6) |
| E-284 (= D40-10) | Zeitpunkt des ersten Lernzugriffs wird an der Bestellposition vermerkt (§1.1, §6.8) |

Die Deltas §8 sind eingearbeitet bzw. mit Status versehen.

**Normative Sprache:** **MUSS** = Vertragsbestandteil · **SOLL** = Standard, begründete Abweichung erlaubt · **KANN** = optional.

---

## 0. Kurzfassung

| Thema | Festlegung |
|---|---|
| Grundsatz | Course Captain ist **nur** Autorität für Kurszugang (Grants). Geld, Einlösewert und Provision liegen ausschließlich bei WooCommerce, Voucher Captain bzw. Affiliate Captain. Course **bucht nie** einen Kauf, Refund, Einlösewert oder eine Provision und **meldet** keine an Dritte (FIN-INV-01…08). |
| Ereignisse aus Course | Nur `grant.*`, `enrollment.*`, `seat.*`, `course.usage_recorded` (R0-19 §5.3); an Partner nur über Pro (E-38), im Free-Kern nur interne Outbox. **Keine** Finanz-Events, keine Beträge/Währungen/Bestellsummen im Payload. |
| Grant-Quellen | `woo_order` (je Bestellposition × Ziel `(course, module)`), `voucher` (beide Zieltypen nach E-31, ein Schlüssel `redemption_key`), `membership` (E-203). Idempotenz über `UNIQUE grant_source (source_type, source_id, source_line, source_revision, course_id, module_id)` mit `module_id NOT NULL DEFAULT 0` (Δ40-1). |
| Voucher | Saga über `vcap()->redemptions` (≥ 1.1.0): Schlüssel persistieren → `reserve` → Grants `pending_confirmation` → `commit` → Grants `active`. Kompensation `release`/`revoked`; Retry-Job mit `get($key)`. **Nicht** `vcap()->api_v1` (bestellgebunden). Gutscheinwert-Rückgabe (`refund`) nie automatisch. |
| Woo | Zustandsbasiert (Snapshot der Bestellung statt Ereignis-Payload) → Doppel- und Out-of-order-Hooks sind wirkungslos. Dispute-Webhooks (E-33) sind ereignisbasiert mit eigenem Fall-Automaten. Auto-Produkte (E-34) ändern nie verkaufte Positionen. Mitgliederrabatt (E-25a) wirkt nur auf den Preis, nie auf Grants. |
| Refund | Nur der betroffene Grant; kein Wiederaufleben (E-29); Lernhistorie und Zertifikat bleiben (35.3); Matrix §5 je Quelle erweitert R0-09 §5. |
| Tests | FIN-001…FIN-081 mit Ziel R3-02…R3-05, R3-19 (§6). |
| Entscheidungen | D40-1…D40-10 beschlossen als E-275…E-284 (§7). |

---

## 1. Zuständigkeitsmatrix

### 1.1 Wer bucht was (Ledger) und wer reagiert

Legende: **B** = bucht/führt das Ledger (einzige Autorität) · **R** = reagiert mit eigener Fachfolge (nur eigene Daten) · **E** = erkennt/leitet ab, ohne zu buchen · – = nicht beteiligt. „Course-Kern“ = Free (Grant-Automat, interne Outbox). „Woo-Adapter“ und „Voucher-Connector“ = Course Captain Pro (E-53).

| Nr. | Finanzielle Tatsache | WooCommerce | Voucher Captain | Affiliate Captain | Course-Kern | Course Woo-Adapter | Course Voucher-Connector |
|---|---|---|---|---|---|---|---|
| Z-01 | Kauf eines Kursprodukts (Bestellung, Zahlung, Steuer) | **B** | – | R (Provision aus Woo-Bestellung, A4) | R (Grant) | E (Snapshot → Soll-Grants) | – |
| Z-02 | Kauf eines Gutscheinprodukts | **B** | **B** (Ausgabe, Wert) | R (Provision auf Gutscheinkauf, Standard `vcap_affiliate_commission_on_voucher_sales = true`, A5) | – (Gutscheinprodukt hat **kein** Kursmapping, FIN-INV-05) | E (Mapping-Prüfung) | – |
| Z-03 | Gutschein als Zahlungsmittel/Rabatt im Warenkorb (Kanal `shop`) | **B** (Bestellung) | **B** (Wert, `api_v1`/Warenkorb) | R (Basis netto **nach** Gutscheinanteil, A3) | R (Grant **nur** aus `woo_order`) | E | – (nicht beteiligt, R0-36 §3.3 Nr. 6) |
| Z-04 | Externe Gutschein-Einlösung für einen Kurs (Kanal `course-captain`) | – | **B** (Einlöseledger reserve/commit) | – (**keine** Provision bei Einlösung, A3/A5) | R (Grant) | – | E (Saga, §3) |
| Z-05 | Provision | – | – | **B** | – | – | – |
| Z-06 | Voll-/Teilrefund, Storno einer Woo-Bestellung | **B** | R (Gutschein `void` bei Gutscheinkauf, `vcap_void_on_refund`; Guthabenrückgabe bei Gutschein als Zahlung) | R (Provisionskorrektur aus Woo-Refund, A4) | R (Grant nach §5) | E | R (nur bei `voided`, §3.6) |
| Z-07 | Chargeback/Dispute (PayPal/Klarna/generisch, E-33) | **B** (Bestellstatus, Notiz; Gateway-Plugin führt Zahlungsfall) | – | R (Halten/Rückbuchung über Woo-Status, `wpac_wc_hold_statuses`/`wpac_wc_chargeback_statuses`) | R (Grant suspend/resume/revoke) | **E** (Webhook-Annahme, Fall-Automat §4.4; Statuswirkung nach E-275) | – |
| Z-08 | Gewonnener Dispute / Storno zurückgenommen | **B** | – | R (`reinstate` bei Rückkehr in bezahlten Status, `OrderSync::maybe_chargeback`) | R (resume bzw. neue Revision, E-29) | E | – |
| Z-09 | Gutschein nach Einlösung storniert (`voided`) | – | **B** | – | R (nur Voucher-Grant, E-35) | – | E |
| Z-10 | Gutscheinwert nach Einlösung zurückgeben (`redemptions->refund`) | – | **B** | – | – | – | nur **Betreiberaktion** (E-283), nie automatisch |
| Z-11 | Mitgliedsbeitrag/Abo (E-203) | **B** (bzw. Mitgliedschafts-/Abo-Plugin) | – | R (Abo-Verlängerungen über Woo-Adapter von Affiliate) | R (Grant) | – (Abo-Positionen ausgeschlossen, §2.4) | – |
| Z-12 | Ticketkauf (Pass, später R3.4) | – | – | R (Pass meldet selbst, R0-36 §2.6) | R (Grant aus Ticket, nicht aus Bestellung) | – | – |
| Z-13 | Mitgliederrabatt (E-25a) | **B** (Preis der Position) | – | R (Basis netto nach Rabatt) | – (liefert nur „aktiver Grant ja/nein“) | E (Preisregel im Warenkorb, §4.6) | – |
| Z-14 | Nutzungsereignis (Lernbeginn, E-32) | – | – | – | **B** (Lerndaten) → Event `course.usage_recorded` | R (optional Positionsnotiz, E-284) | – |

### 1.2 Invarianten (MUSS, je Release als Test/Guard geprüft)

| ID | Invariante | Nachweis |
|---|---|---|
| FIN-INV-01 | Course (Kern und Pro) ruft **nie** `wpac_record_sale()`, `wpac_record_refund()`, `wpac_record_reinstatement()`, `do_action('wpac_v1_record_*')` oder `POST …/reinstatements` auf (E-37, R0-36 §4.3). | statischer CI-Guard (FIN-050) |
| FIN-INV-02 | Course erzeugt **nie** eine Woo-Bestellung, einen Woo-Refund (`wc_create_refund`) oder eine 0-€-Bestellung für Einlösungen (LB-Muster INV-C-184 wird **nicht** nachgebaut). | Guard + FIN-050 |
| FIN-INV-03 | Course ruft `vcap()->redemptions->refund()` nur aus der Betreiberaktion E-283 (Capability, Begründung, Audit), nie aus Hooks/Cron. `vcap()->api_v1` wird nicht verwendet. | Guard + FIN-050 |
| FIN-INV-04 | Kein Course-Event enthält Beträge, Währungen, Steuer, Zahlungsart, Gutscheincode oder Gutscheinwert (R0-19 §5.1 „nie Zahlungsdaten“). | Payload-Schema-Test FIN-053 |
| FIN-INV-05 | Ein Woo-Produkt ist **entweder** Kursprodukt (Mapping) **oder** Gutscheinprodukt (`vcap`-Typ), nie beides; Speichern einer Kombination wird abgewiesen (Verhalten LB INV-B-156). Damit entsteht aus Gutscheinkauf + Einlösung genau **ein** Zugang. | FIN-018, FIN-052 |
| FIN-INV-06 | Je Bestellposition und Ziel gibt es genau **eine** autoritative Quelle: Positionen mit Abo-/Mitgliedschaftsprodukt (E-203) erzeugen keine `woo_order`-Grants (§2.4). | FIN-081 |
| FIN-INV-07 | Course speichert keine Gutschein-Klarcodes, kein Guthaben und kein zweites Einlöseledger (Kap. 32.1); nur `redemption_key`, HMAC des Codes und optional die letzten 4 Zeichen (`code_hint`). | Schema-Review, FIN-014 |
| FIN-INV-08 | Ein Grant-Übergang, sein `ccap_grant_event` und sein Outbox-Event entstehen in **einer** Transaktion (R0-09 §2.4, R0-19 §5.4). Fremde Tabellen (`wc_*`, `vcap_*`, `wpac_*`) werden nie direkt gelesen oder geschrieben. | R1-02-Tests, Guard |

### 1.3 Folgerung für Affiliate (E-37) je Pfad

| Pfad | Provision entsteht | Course-Beitrag |
|---|---|---|
| Woo-Kauf eines Kurses (auch mit Mitgliederrabatt) | einmal aus der Woo-Bestellung, Basis netto nach Rabatten | keiner |
| Woo-Kauf mit Gutschein/Geschenkkarte als Zahlung | einmal, Gutscheinanteil abgezogen (A3) | keiner |
| Gutscheinkauf → externe Einlösung | einmal beim Gutscheinkauf (A5); Einlösung: keine | keiner |
| Woo-Refund/Storno/Chargeback | Korrektur durch Affiliate aus Woo (A4) | keiner (nur Grant) |
| Dispute über Course-Webhook | Halten/Rückbuchung nur, wenn der Woo-**Status** sich ändert (Affiliate liest Status, nicht Course) → E-275 | Statuswirkung nach E-275, nie Affiliate-API |
| Mitgliedschaft/Abo | durch Woo-Subscriptions-Integration von Affiliate | keiner |

---

## 2. Grant-Quellen und Idempotenzschlüssel

### 2.1 Gemeinsamer Schlüssel (E-27, in R0-06/R0-09 eingearbeitet)

`UNIQUE grant_source (source_type, source_id, source_line, source_revision, course_id, module_id)`.

- **`module_id bigint unsigned NOT NULL DEFAULT 0`**, `0` = ganzer Kurs (E-27 schreibt „NULL = ganzer Kurs“). Begründung: InnoDB erlaubt in einem UNIQUE-Index **mehrere NULL-Werte**; mit NULL wäre der Idempotenzschlüssel für Ganzkurs-Grants nicht DB-erzwungen (R0-06 §1 Nr. 4 „Idempotenzspalten sind NOT NULL“). → Δ40-1.
- Gleicher Schlüssel mit anderem `user_id` → `ccap_idempotency_conflict` (R0-09 §2.2).
- Ein Ziel ist immer ein Paar `(course_id, module_id)`; ein Bundle-Kurs (E-25a) ist ein gewöhnliches Ziel, die enthaltenen Kurse werden im effektiven Zugang abgeleitet (kein Grant je Teilkurs). Refund des Bundle-Grants beendet damit auch die abgeleiteten Zugänge, sofern keine anderen Grants bestehen.
- `source_revision` +1 nur nach E-29/E-117 (Refund zurückgenommen, Kundenwechsel, Wiederaufnahme nach `expired`/`revoked`).

### 2.2 `woo_order` (R3.1, Woo-Adapter)

| Feld | Wert | Regel |
|---|---|---|
| `source_type` | `woo_order` | – |
| `source_id` | Woo-Bestell-ID (HPOS/CRUD, `WC_Order::get_id()`) | nie Bestellnummer (änderbar durch Plugins) |
| `source_line` | Positions-ID `order_item_id` | bei Gruppenprodukten verweist der Grant über `group_seat` (R0-09) – die Position erzeugt die **Gruppe** (`group_source = woo_order:{order}:{item}:{rev}`), nicht direkt Grants |
| Ziele je Position | eingefrorene Liste `(course_uid, module_uid|0, scopes, depends_on_course_uid?)` (Abhängigkeit für Zusatzmodule, E-26) aus dem Mapping **zum Zahlungszeitpunkt** (Positions-Meta `_ccap_targets`, schreibgeschützt nach `paid`) | COMMERCE-09: spätere Mapping-/Produktänderung wirkt nicht auf verkaufte Positionen |
| Menge | Nicht-Gruppenprodukte „einzeln verkauft“ (Menge = 1, Beschluss E-282); Gruppenprodukte: Plätze = `seats_per_item × (qty − refunded_qty)` (R0-09 RF-04) | – |
| `source_revision` | 1; +1 bei RF-06 (Refund zurückgenommen) und E-117 (Kundenwechsel) | – |
| `source_seq` | Woo `date_modified` (µs) + Zähler je Bestellung | nur Diagnose; Woo-Pfad ist zustandsbasiert (§4.1) |
| Inbox-Dedupe | `(connector_id='woocommerce', event_id = sha256(order_id, status, date_modified, refund_ids, item_hash, consent_state, dispute_state))` | gleiche Bestellung im gleichen Zustand → `duplicate` |
| Identität | Kontopflicht (E-30): Kunde der Bestellung = Lernender; Gast-Checkout für Kursprodukte gesperrt; Käufer ≠ Lernender nur über Gruppenprodukte (Seats) | E-Mail nie Identitätsbeweis (35.1) |

### 2.3 `voucher` (R3.2, Voucher-Connector, beide Zieltypen nach E-31)

| Feld | Wert | Regel |
|---|---|---|
| `source_type` | `voucher` | – |
| `source_id` | `redemption_key` = `ccap:v1:{hmac32}:{n}` mit `hmac32` = erste 32 Hex von `HMAC-SHA256(site_secret, user_id ‖ normalisierter Code ‖ target)`, `n` = laufende Versuchsnummer (1, nach `released` +1) | erfüllt `KEY_PATTERN` `/^[A-Za-z0-9][A-Za-z0-9._:\-]{7,63}$/` (max. 47 Zeichen); Doppelklick/Reload/Retry → gleicher Schlüssel (CONNECTORS §2 Nr. 1); kein Klarcode gespeichert |
| `source_line` | `{index}` des Ziels in der beim Reservieren eingefrorenen Zielliste | – |
| Zieltyp A „neutrales Angebot“ | Voucher-`target` = `course-captain:offer:{offer_uid}`; Zielliste = Ziele des Course-Zugangsangebots (`AccessOffer`: `(course, module|0, scopes, duration)`) | Angebot gehört dem Course-Kern (E-31), kein Preis |
| Zieltyp B „Woo-Produkt“ | Voucher-`target` = `course-captain:product:{product_id}[:{variation_id}]`; Zielliste = Kursmapping dieses Produkts **zum Reservierungszeitpunkt** | Einlösung erzeugt **keine** Woo-Bestellung (FIN-INV-02); Grant-Quelle bleibt `voucher`; Produkt muss Kursprodukt (E-34 oder manuell) und **kein** Gruppenprodukt sein (E-36: ein Code je Platz) |
| Kanal | eigener Kanal `course-captain` (`vcap_redemption_channels`, `vcap_redemption_channel_for_target`); Kursgutscheintyp `course` (`vcap_voucher_types`) | Kanal `shop` für Kursgutscheine ausgeschlossen (Beschluss E-279); Produkt mit `shop`-Kanal und Kursgutscheintyp erzeugt eine Diagnosewarnung |
| Startzustand | `pending_confirmation` (`reserved`) nach erfolgreichem `reserve`; `active` erst nach erfolgreichem `commit` | Auflösung des Widerspruchs R0-09 §2.2 (pending bis commit) ↔ R0-36 §3.3 Nr. 2 (aktiv vor commit): **R0-09 gilt** – kein Zugang ohne endgültige Einlösung (Δ40-3) |
| Einheit | ein Einmalgutschein = ein Lernender × Zielliste; keine Guthaben/Mehrfachgutscheine über die Einlöse-API (Beschluss E-278: Kursgutscheine sind Zugangsgutscheine) | COMMERCE-06 |

### 2.4 `membership` (E-203, Adapter erst in R3)

| Feld | Wert | Regel |
|---|---|---|
| `source_type` | `membership` | Anbieter steht in `source_id`, damit `source_type` stabil bleibt |
| `source_id` | `{provider}:{membership_id}` (z. B. `wcm:812`) | – |
| `source_line` | `{plan_id}:{target_index}` | – |
| Statusabbildung (`SourceStatusMapper`) | aktiv → `active`; pausiert/zahlungsrückständig → `suspended` (`membership_paused`); „Kündigung zum Laufzeitende“ → bleibt `active`, `valid_until` = Laufzeitende; abgelaufen → `expired`; storniert/erstattet → `revoked` (`membership_cancelled`); unbekannt → nichts ändern (K-02) | Wiederaufnahme nach `expired`/`revoked` = neue Revision (E-29) |
| Doppelquellen | Abo-/Mitgliedschaftsprodukte, die ein Membership-Adapter beansprucht, sind im Woo-Mapping **gesperrt** (FIN-INV-06); Abo-Verlängerungsbestellungen erzeugen keine `woo_order`-Grants | eine autoritative Quelle je Position (32.2) |
| Finanzen | keine; Beitrag/Refund bucht Woo bzw. das Mitgliedschafts-Plugin | – |

### 2.5 Abgrenzung zu übrigen Quellen

`manual`, `group_seat` (R1) und `workflow` (Course-Pro-Connector, E-39/E-39b) bleiben wie R0-09 §2.2. Ein Gruppenplatz aus einer Woo-Position wird über `group_seat` gegrantet; sein Widerruf folgt der Gruppe (R0-09 §4.5).

---

## 3. Voucher-Ablauf über `vcap()->redemptions` (Saga)

### 3.1 Voraussetzungen

- `defined('VCAP_VERSION') && version_compare(VCAP_VERSION, '1.1.0', '>=') && function_exists('vcap')` (CONNECTORS §6); sonst Connector `active_incompatible` (R0-34 INT-003), Einlöse-UI antwortet `ccap_feature_unavailable`.
- Voucher setzt WooCommerce und InnoDB voraus (R0-36 C11, X1); „Voucher allein“ heißt „ohne Course-Woo-Adapter“.
- Angemeldet (E-30); Rate-Limit `code_redeem` **fail-closed** (E-46); zusätzlich Voucher-eigenes Limit (`vcap_rate_limited`).

### 3.2 Persistenter Saga-Zustand

Neue Tabelle im Pro-Schema `ccap_source_saga` (Δ40-2): `saga_key` = `redemption_key` (UNIQUE), `source_type`, `user_id`, `target`, `targets_json` (eingefroren), `code_hmac char(64)`, `code_hint char(4) NULL`, `state` (`new`, `reserving`, `reserved`, `committing`, `committed`, `releasing`, `released`, `lost`, `failed`), `attempts`, `next_attempt_at`, `deadline_at` (= erstellt + 24 h, E-201), `last_partner_code`, `correlation_id`, `row_version`. Die Tabelle ist kein Ledger (keine Beträge), nur Prozesszustand.

### 3.3 Schritte

Lock-Reihenfolge (ergänzt R0-09 §6.1): L1 `learner:<user>` → **LV `voucher-code:<code_hmac>`** → L2 `grant-source:voucher:<key>` → DB-TX.

| Schritt | Aktion | Erfolg | Fehler/Abbruch |
|---|---|---|---|
| S0 Vorprüfung | Ziel auflösen (Angebot/Produkt → Zielliste); `check($code, ['target'])` (reserviert nichts) | Vorschau „Zugang zu …, bereits vorhanden bis …“ (Verhalten LB INV-B-057) | Fehlercode nach §3.5; **nichts** gespeichert außer Rate-Limit-Zähler |
| S1 Schlüssel | unter LV: offene Saga zu `(user, code_hmac, target)` suchen → Schlüssel wiederverwenden; sonst neuen Schlüssel mit `n+1`, Saga `reserving` **committen**, bevor Voucher gerufen wird | – | DB-Fehler → `ccap_db_busy`, nichts reserviert |
| S2 Reservieren | `reserve($code, ['key','target','user_id', 'ttl_minutes' => 26*60, 'meta' => ['correlation_id']])` | weiter S3 | `vcap_already_redeemed` → `get($key)`: `committed` und Saga gehört uns → S5 (Ergebnis `duplicate`); sonst `ccap_code_already_used`, Saga `released`. `vcap_temporary_failure` → Saga bleibt `reserving`, Retry. Übrige Codes → Saga `released` (nichts reserviert), Fehler an Nutzer |
| S3 Grants vorbereiten | eine TX: Saga `reserved` + je Ziel Grant `pending_confirmation` (G-01, `reserved`), Outbox `grant.created` | Anzeige „Zugang wird aktiviert“ | Unique-Kollision mit fremdem Nutzer → `ccap_idempotency_conflict` + `release` |
| S4 Bestätigen | Saga `committing`; `commit($key)` | weiter S5 | `vcap_reservation_missing` → Grants `revoked` (`voucher_reservation_lost`), Saga `lost`, `release` (idempotent), Hinweis „erneut einlösen“. `vcap_cancelled` → Grants `revoked` (`voucher_cancelled`), `release`, Saga `released`. `vcap_temporary_failure`/Timeout → Saga bleibt `committing`, Retry |
| S5 Aktivieren | eine TX: Grants `active` (G-04, `committed`), Saga `committed`, Outbox `grant.activated` (+ `access.changed`/`enrollment.created` nach R0-09 §3.4) | Ergebnis `created` bzw. `duplicate` | DB-Fehler → Retry-Job (Saga `committed`, Grants noch pending) |
| S6 Retry-Job `ccap_voucher_retry` (alle 5 min, Backoff 5/15/60 min, Lease) | `get($key)` und fortsetzen: `reserved` → S3/S4; `committed` → S5 sicherstellen; `released` → Grants `revoked` (`released`), Saga `released`, Schlüssel verwerfen | – | siehe §3.4 |

`vcap_voucher_redeemed` und `vcap_redemption_released` sind **nur** Audit-/Diagnose-Signale; sie lösen keinen Grant aus (R0-36 §3.3 Nr. 5). Einzige Ausnahme: `vcap_redemption_released( $data, $expired = true )` zu einer Saga im Zustand `reserved`/`committing` setzt `next_attempt_at = now` (schnellere Kompensation).

### 3.4 Timeout und Partnerausfall (E-201, Beschluss E-277)

| Lage bei `deadline_at` (24 h) | Verhalten |
|---|---|
| Partner erreichbar, Saga `reserving`/`reserved` | `release($key)` → Grants `failed` (`pending_timeout`, G-06), Saga `released`; Event `grant.failed`; Lernender erhält Hinweis zum erneuten Einlösen (neuer Schlüssel `n+1`), Support-Hinweis (Beschluss E-277). `failed` ist für diesen Schlüssel **endgültig**; R0-09 G-13 (Retry) gilt dafür nicht (**E-294**) |
| Partner erreichbar, Saga `committing` | erst `get($key)`: `committed` → S5 (Zugang, weil Gutschein verbraucht ist); sonst `release` wie oben |
| Partner nicht erreichbar/inaktiv/inkompatibel | Grants `failed` (`partner_unavailable`, G-06), Saga `failed`; **kein** Release möglich. Nach Rückkehr des Partners: auditierter Retry (G-13) mit `get($key)` – `committed` → aktiv, `released`/`reservation_missing` → `revoked` |
| TTL | `ttl_minutes = 26 h` > Retry-Fenster 24 h (CONNECTORS §2 letzter Absatz); TTL läuft beim Partner trotzdem ab → Gutschein wird dort frei, Course entzieht beim nächsten `get` |

Kein Massenwiderruf bei Deaktivierung (ACCESS-09, R0-09 RF-15); E-93: bei abgelaufener Pro-Lizenz laufen Saga-Fortsetzung und Widerrufe weiter, neue Einlösungen sind gesperrt (`ccap_feature_unavailable`).

### 3.5 Fehlercode-Mapping (präzisiert R0-19 §3.4)

| Voucher-Code | Schritt | Course-Code (HTTP) | `reason` | R | Grant-/Saga-Wirkung |
|---|---|---|---|---|---|
| `vcap_invalid_request` | S0–S4 | `ccap_code_invalid` (422) | `invalid_request` | – | Saga `released`; **zusätzlich** Health-Fehlerklasse `validation` + Audit (Course-Fehler, nicht Nutzerfehler) |
| `vcap_not_found` | S0/S2 | `ccap_code_invalid` (422) | `unknown` | – | nichts |
| `vcap_not_redeemable` | S0/S2 | `ccap_code_invalid` (422) | `not_redeemable` (Ticket, Shop-only, `credit`, falscher Kanal) | – | nichts |
| `vcap_insufficient_balance` | S0/S2 | `ccap_code_invalid` (422) | `insufficient_balance` (nur Abwehr: Guthaben sind nach E-278 ausgeschlossen) | – | nichts |
| `vcap_expired` / `vcap_cancelled` | S0/S2 | `ccap_code_expired` (410) | `expired` / `voided` | – | nichts |
| `vcap_cancelled` | S4 | `ccap_code_expired` (410) | `voided` | – | Grants `revoked` `voucher_cancelled`, `release` |
| `vcap_not_active` | S0/S2 | `ccap_code_not_yet_valid` (422) | – | – | nichts |
| `vcap_already_redeemed` | S2 | `duplicate` (200) **oder** `ccap_code_already_used` (409) | – | – | Erfolg nur, wenn `get($key)` = `committed` für **diese** Saga |
| `vcap_reserved` | S2 | `ccap_code_in_progress` (409) | – | R (`retry_after: 30`) | nichts |
| `vcap_target_unavailable` | S0/S2 | `ccap_code_wrong_target` (422) | `target_unavailable` | – | nichts |
| `vcap_not_allowed` | S0–S4 | `ccap_code_not_allowed` (403) | – | – | nichts bzw. Saga `released` |
| `vcap_idempotency_conflict` | S2 | `ccap_idempotency_conflict` (409) | – | – | Saga `failed`, Audit (Manipulationsverdacht) |
| `vcap_reservation_missing` | S4/S6 | – (Nutzer: `ccap_not_found`, `reason: reservation_lost`) | – | – | Grants `revoked` `voucher_reservation_lost` |
| `vcap_rate_limited` | S0/S2 | `ccap_rate_limited` (429) | `details.bucket = code_redeem` | R | nichts |
| `vcap_temporary_failure` | alle | `ccap_temporarily_unavailable` (503) | `partner_unavailable` | R | Saga bleibt, Retry mit **gleichem** Schlüssel |
| `vcap_not_committed` | Refund (Betreiberaktion E-283) | `ccap_partner_error` (502) | `partner_code` | – | keine |
| `vcap_refund_exceeded` | Refund (Betreiberaktion E-283) | Ergebnis `unchanged` (200) | – | – | keine (bereits vollständig zurückgegeben) |
| alles Übrige | – | `ccap_partner_error` (502) | `partner_code` | nach `retryable` | Saga unverändert, Health `partner_error` |

Delta zu R0-19 §3.2/§3.4: `ccap_code_invalid` bekommt das `reason`-Enum `unknown|not_redeemable|insufficient_balance|invalid_request`, `ccap_code_expired` `expired|voided` (Δ40-4). Nutzertexte bleiben neutral (kein Existenzorakel, K-14).

### 3.6 Voucher-Ereignisse nach der Einlösung

| Voucher-Signal | Course-Wirkung |
|---|---|
| `vcap_voucher_event( $id, 'voided' )` nach `committed` (Gutscheinkauf erstattet/storniert) | nur die Grants dieser Saga `revoked` (`voucher_voided`, E-35, R0-09 RF-17/18); andere Quellen unberührt; Lernhistorie/Zertifikat bleiben |
| `voided` während `reserved` | wird beim `commit` als `vcap_cancelled` sichtbar (S4) |
| `restored`, `reactivated` (Händler reaktiviert Gutschein) | **keine** Wirkung auf widerrufene Grants (E-29); erneute Einlösung = neuer Schlüssel `n+1` = neue Grants |
| Teilrefund des Gutscheinkaufs | nichts (Gutschein bleibt gültig, CONNECTORS §3) |
| `vcap_redemption_refunded` (nach E-283) | nur Audit; der zugehörige Grant ist bereits durch die Betreiberaktion widerrufen |

---

## 4. WooCommerce-Ablauf (Woo-Adapter R3.1)

### 4.1 Prinzip: zustandsbasiert

Jeder Woo-Hook (`woocommerce_order_status_changed`, `woocommerce_payment_complete`, `woocommerce_order_refunded`, `woocommerce_refund_deleted`, `woocommerce_saved_order_items`, `woocommerce_process_shop_order_meta`, Trash/Untrash, Kundenwechsel) und der Reconcile-Job (`ccap_woo_reconcile`, stündlich, Cursor `(date_modified, id)`, Karenz 5 min, Start ab Deaktivierungszeitpunkt – Verhalten LB INV-B-120) lösen nur **„Snapshot lesen → Soll-Grants → Reconcile“** aus (R0-19 §5.8). Der Snapshot liest die Bestellung frisch über CRUD (HPOS/Classic). Folge: doppelte, parallele oder vertauschte Hooks liefern denselben Endzustand; spätes `paid` nach `refunded` sieht beim Lesen bereits `refunded` (RF-07). Verarbeitung je Bestellung unter L2 `grant-source:woo_order:<id>`.

### 4.2 Bestellstatus → Soll-Grant (je Position × Ziel)

| Woo-Status / Lage | Soll | `reason_code` | Bemerkung |
|---|---|---|---|
| `checkout-draft` | nichts | – | – |
| `pending` (Zahlung offen) | **nichts** (kein Grant, E-293) | – | kein Pending-Timeout (E-276); Grant entsteht erst bei Zahlung; Dashboard-Hinweis bei > 7 Tagen offener Bestellung (ohne Grant) |
| `on-hold` ohne `date_paid` (z. B. Überweisung) | **nichts** (kein Grant, E-293) | – | dto. |
| bezahlter Status (`wc_get_is_paid_statuses()`), Zustimmung erfüllt | `active` | `order_paid` | Laufzeit ab `date_paid` (R0-09 §3.3) |
| bezahlter Status, Zustimmung § 356 Abs. 5 BGB fehlt (E-33-Gate) | `pending_confirmation` | `consent_missing` | Freischaltung nach Zustimmung (`consent_given`) |
| `on-hold` mit `date_paid` | `suspended` | `payment_disputed` | Rückkehr in bezahlten Status → `active` (G-09) |
| Dispute-Fall offen (§4.4) | `suspended` | `payment_disputed` | unabhängig vom Status, solange ein Fall offen ist |
| `failed` ohne `date_paid` | nichts (kein Grant vorhanden, E-293) | – | RF-13 |
| `failed` mit `date_paid` | `revoked` | `chargeback_lost` | entspricht Affiliate-Default `wpac_wc_chargeback_statuses = ['failed']` |
| `cancelled` | `revoked`, falls nach Zahlung ein Grant besteht; vor Zahlung kein Grant (E-293) | `order_cancelled` | auch bei Regel `keep` (RF-11/12) |
| `refunded` | `revoked` | `order_refunded` | außer Regel `keep` (RF-02) |
| Teilrefund | je Position nach §4.3 | `item_refunded` | – |
| Position entfernt | `revoked` | `item_removed` | RF-14 |
| Papierkorb/gelöscht | nichts, Reconcile-Hinweis | – | RF-15 (bewusste Abweichung von LB INV-B-107) |
| Kunde geändert | alt `revoked` (`source_subject_changed`), neue Revision für neues Konto | – | E-117 |
| unbekannter/eigener Status | nichts | – | K-02 |

Manuelle Bestellaktionen (abgleichen, vor Zahlung freischalten, aussetzen, aufheben; Pflichtbegründung, Audit – Verhalten LB INV-B-116) wirken als `manual_override` nach R0-09 §2.3 (Quell-Widerruf setzt sich trotzdem durch).

### 4.3 Teilrefund

| Refund-Art | Wirkung | Regel je Mapping (`refund_rule`) |
|---|---|---|
| Positionsbezogen, volle Menge | Grants der Position `revoked` | `revoke_on_full_refund` (Standard), `revoke_on_any_refund`, `keep` |
| Positionsbezogen, Teilbetrag bei Menge 1 | bei `revoke_on_any_refund` → `revoked`; sonst keine Änderung + Hinweis „manuell prüfen“ | – |
| Gruppenposition, Teilmenge | Plätze = `seats_per_item × (qty − refunded_qty)`; Reduktion nach R0-09 §4.4 (offene Einladungen zuerst, dann zuletzt Beigetretene) | – |
| Betrag ohne Positionsbezug | keine Änderung, Warnung im Dry-run/Dashboard | RF-05 |
| Refund gelöscht (Rücknahme) | neue Revision `active` (`refund_reversed`), alte bleibt `revoked` | RF-06, E-29 |
| Modul-Position (E-27) | nur `(course, module)`-Grant der Position; Ganzkurs-Grant aus anderer Quelle bleibt | – |
| Bestellung teils mit Gutschein/Geschenkkarte bezahlt | wie oben; Guthabenrückgabe macht Voucher selbst, Course ruft nichts | FIN-INV-03 |

### 4.4 Chargeback/Dispute-Webhooks (E-33, Clean-Room-Neuimplementierung)

| Baustein | Festlegung (Verhalten nach LB INV-B-100…105) |
|---|---|
| Eingänge | PayPal-Webhook (Signaturprüfung über PayPal `verify-webhook-signature`, Vorfilter: Methode, JSON, ≤ 256 KB, Kopfzeilen, `cert_url` nur `*.paypal.com`, 10/min je IP, 60/min gesamt); Klarna als **manuelle Bestellaktion** „Zahlungskonflikt melden“ mit Fall-ID; generischer Webhook mit `X-CC-Signature` (HMAC, ±300 s, R0-19 §5.7) für weitere Gateways |
| Zuordnung | Bestellung **nur** über exakte Transaktions-ID; kein Treffer → `202`, Audit `dispute_unmatched` (gedrosselt), keine Wirkung |
| Dedupe | `ccap_inbox_event (connector_id='woo-dispute', event_id)`; Event-ID = Gateway-Event-ID bzw. Hash über normalisiertes JSON; Aufbewahrung 90 Tage (E-116); Replay → `202 duplicate` |
| Fall-Automat je `(gateway, case_id)` | `opened`/`updated` → offen; `won` / `lost` → abgeschlossen und **endgültig**; zwischen Ergebnissen gilt das jüngere `event_time`; älteres Ereignis nach Abschluss → `stale_event`; Fenster −60 Tage/+1 Tag |
| Speicherort | Fallstand als Bestell-Meta `_ccap_disputes` (über CRUD, gehört zur Bestellung) → fließt in den Snapshot (`disputeState`, Δ40-5) |
| Grant-Wirkung | offen → `suspended` (`payment_disputed`); `won` und kein weiterer offener Fall → `active` (`dispute_won`); `lost` → `revoked` (`chargeback_lost`) |
| Woo-Status | **Beschluss E-275:** der Adapter setzt den Woo-Status, damit auch Affiliate die Provision storniert: `lost` → `refunded`, wenn Geld zurückgebucht wurde, `cancelled` nur bei Storno vor Zahlung (**E-295**); `opened` → `on-hold`, `won` → vorheriger bezahlter Status (Empfehlung aus D40-1); nur aus passendem Ausgangsstatus, idempotent, mit Bestellnotiz |
| Lock | L2 `grant-source:woo_order:<id>` (statt LB-eigener Dispute-Sperre); belegt → `409 ccap_resource_busy`, Gateway wiederholt |
| Fehler | Signatur ungültig → `401 ccap_signature_invalid` (Audit max. 1 × je 10 min); Secrets nach E-87 (Konstanten haben Vorrang, nie angezeigt) |
| Finanzen | Course bucht nichts; Rückbuchung/Halten der Provision nur über den Woo-Status (E-275) bzw. durch das Gateway-Plugin; der Adapter ruft nie `wc_create_refund` (FIN-INV-02) |

### 4.5 Auto-Produkte (E-34)

| Regel | Festlegung |
|---|---|
| Anlage | je veröffentlichtem Kurs und je verkaufbarem Modul (E-27) ein **virtuelles**, einzeln verkauftes Woo-Produkt (Gruppenvariante optional mit `seats_per_item`); Sync-Schlüssel Produkt-Meta `_ccap_sync_key = course:{uid}[:module:{uid}][:seats:{n}]` (idempotent: zweiter Lauf legt nichts doppelt an) |
| Preis | **Beschluss E-280:** Course führt keinen Preis; Produkt entsteht als **Entwurf ohne Preis**, der Shop pflegt Preis/Steuerklasse und veröffentlicht in Woo |
| Gepflegte Felder | Titel, Kurzbeschreibung, Bild, `virtual`, `sold_individually`, Mapping, Katalogsichtbarkeit; **nie** Preis, Steuer, Lager, Gutscheintyp |
| Handänderungen | Fingerprint je Feld; manuell geänderte Felder werden nicht überschrieben (Verhalten LB INV-B-093/T98); Dry-run zeigt Drift |
| Kurs unveröffentlicht/„Einschreibung geschlossen“/archiviert (E-108) | Produkt → nicht kaufbar (Entwurf bzw. `catalog_visibility = hidden`); bestehende Grants unverändert |
| Kurs/Modul gelöscht | Produkt nie löschen (Bestellungen referenzieren es); auf Entwurf, Mapping als verwaist markieren; Grants bleiben |
| Produkt in Woo gelöscht | Mapping verwaist (Diagnose, Reparatur „verwaiste Zuordnung entfernen“), Grants bleiben; nächster Sync legt **kein** neues Produkt an, solange das verwaiste Mapping besteht (Betreiberentscheidung) |
| Einfluss auf Grants | keiner rückwirkend: verkaufte Positionen tragen ihre eingefrorenen Ziele (§2.2); Voucher-Zieltyp B friert beim Reservieren ein |
| Gutscheinprodukte | werden nie automatisch angelegt; FIN-INV-05 |

### 4.6 Mitgliederrabatt (E-25a)

| Regel | Festlegung (Verhalten nach LB INV-B-130) |
|---|---|
| Regel | `(Basis: course|module mit aktivem Grant, Prozent, Geltung: andere Kurse | Lernprodukte | alle Produkte)` im Woo-Adapter |
| Anwendung | Woo-Warenkorbpreis der Position; bei mehreren passenden Regeln nur der **beste** Rabatt (kein Stapeln untereinander); reine Upgrade-/Ergänzungsprodukte zählen nicht als Basis |
| Prüfzeitpunkt | Warenkorb **und** erneut bei Bestellanlage (Checkout-Validierung); Basis = effektiver Zugang nach R0-09 §3.1 (Lernsperre schließt aus) |
| Nachweis | Positions-Meta `_ccap_member_discount = {rule_id, basis_grant_uid, percent}` (keine Personendaten) |
| Einfluss auf Grants | **keiner**: der Rabatt erzeugt, verlängert oder widerruft keinen Grant; späterer Verlust des Basis-Grants ändert die rabattierte Bestellung nicht (Woo ist wirtschaftliche Autorität) |
| Finanzen | Affiliate bucht netto nach Rabatt aus Woo; Course meldet nichts |
| Kombination | **Beschluss E-281:** nicht mit Rabattcodes/Promotions kombinierbar, es gilt der bessere Rabatt; Geschenkkarten/Guthaben zahlen immer den Rest nach allen Rabatten (CONNECTORS §5) |

### 4.7 Deutsche Shop-Rechtspflichten (E-33) – nur Grant-relevante Teile

Widerrufsbutton, Bestellknopf, Preisangaben, Rechtstexte in Mails und OSS-Schwelle sind Woo-Adapter-Funktionen **ohne** Grant- oder Finanzwirkung in Course (Spezifikation R3-02; Clean-Room-Neuimplementierung nach E-01/E-33). Der Zeitpunkt des ersten Lernzugriffs wird als Positions-Meta vermerkt (Beschluss E-284, nur Zeitstempel, nur bei aktivem Zustimmungs-Gate, im Woo-Privacy-Export). Grant-relevant ist nur das **Zustimmungs-Gate** (§ 356 Abs. 5 BGB, `consent_missing`, §4.2). Ein Widerruf nach § 356a BGB wirkt erst über den bestätigten Woo-Refund/Storno (RF-19). Juristische Prüfung durch den Betreiber.

---

## 5. Refund-/Storno-Matrix je Quelle

Gilt zusätzlich zu R0-09 §5 (RF-01…RF-19). **Für alle Zeilen (MUSS):** nur die Grants der betroffenen Quelle/Position/Saga; Lernstände, Versuche und Zertifikate werden nie gelöscht oder umgeschrieben; Lernbeginn und Kursabschluss ändern den Auslöser nicht (35.3); Refund ist kein Zertifikats-Widerrufsgrund; kein Wiederaufleben (E-29) – zurückgenommene Refunds erzeugen eine neue Revision; laufende Prüfung nach E-113 (gewertet, Auto-Zertifikat nur bei Grant zu Start **und** Abgabe); `access.changed` nur, wenn sich der effektive Zugang ändert (R0-09 §3.4).

| Nr. | Quelle | Ereignis | Grant | vor Lernbeginn | nach Lernbeginn | nach Zertifikat | Finanzfolge (nicht Course) |
|---|---|---|---|---|---|---|---|
| FR-01 | woo_order | Refund beantragt | unverändert | – | – | – | – |
| FR-02 | woo_order | Vollrefund | `revoked` `order_refunded` (außer `keep`) | Zugang weg | Zugang weg, Historie bleibt | Zertifikat `issued`, verifizierbar | Affiliate korrigiert aus Woo |
| FR-03 | woo_order | Positionsrefund | nur Positions-Grants `revoked` | dto. | dto. | dto. | dto. |
| FR-04 | woo_order | Teilmenge Gruppe | Plätze reduziert (R0-09 §4.4); entfernte Seat-Grants `revoked` | dto. | Lernende des entfernten Platzes behalten Historie | Zertifikat bleibt | dto. |
| FR-05 | woo_order | Betrag ohne Positionsbezug | unverändert + Warnung | – | – | – | dto. |
| FR-06 | woo_order | Refund gelöscht/zurückgenommen | neue Revision `active` | – | – | – | Affiliate `reinstate` aus Woo |
| FR-07 | woo_order | Storno (vor/nach Zahlung) | `revoked` `order_cancelled` | dto. | dto. | dto. | dto. |
| FR-08 | woo_order | Dispute offen | `suspended` | Zugang pausiert | Lernstände bleiben lesbar (R0-09 §5 Anzeige) | Zertifikat bleibt | Affiliate hält (E-275) |
| FR-09 | woo_order | Dispute gewonnen | `active` (G-09, kein Wiederaufleben, da nie `revoked`) | – | – | – | Affiliate gibt frei |
| FR-10 | woo_order | Dispute verloren | `revoked` `chargeback_lost` | dto. | dto. | bleibt; Betrugsverdacht → separater Prozess (CORE-SPEC-006) | Affiliate bucht zurück (E-275) |
| FR-11 | woo_order | spätes `paid` nach Refund | ignoriert (`stale_event`) | – | – | – | – |
| FR-12 | voucher | Fehler vor `commit` | `revoked` `released`/`voucher_reservation_lost`/`voucher_cancelled` bzw. `failed` (§3.4) | kein Zugang entstanden | – | – | Wert bleibt/kommt zurück (Voucher) |
| FR-13 | voucher | `voided` nach `commit` | nur Voucher-Grants `revoked` `voucher_voided` (E-35) | Zugang weg | Historie bleibt | Zertifikat bleibt | Voucher/Woo erstatten den Gutscheinkauf; Affiliate korrigiert Gutscheinkauf |
| FR-14 | voucher | Teilrefund des Gutscheinkaufs | unverändert | – | – | – | – |
| FR-15 | voucher | Betreiber widerruft Voucher-Grant manuell | `revoked` `manual_revoke`; Gutscheinwert nur per E-283 | dto. | dto. | dto. | optional `refund` durch Betreiber |
| FR-16 | voucher | Gutschein reaktiviert | keine Wirkung; Neueinlösung = neue Grants | – | – | – | – |
| FR-17 | membership | Pause | `suspended` | – | – | – | – |
| FR-18 | membership | Ablauf zum Laufzeitende | `expired` | – | – | – | – |
| FR-19 | membership | Storno/Erstattung | `revoked` `membership_cancelled` | dto. | dto. | dto. | Woo/Mitgliedschafts-Plugin |
| FR-20 | alle | Partner deaktiviert/inkompatibel | unverändert; neue Ereignisse `pending`/abgewiesen | – | – | – | – |
| FR-21 | alle | Pro-Lizenz abgelaufen (E-93) | Grants und Widerrufe laufen weiter | – | – | – | – |

---

## 6. Testfälle FIN-xxx

Art: U = Unit, I = Integration (WP-Testsuite mit echtem Woo bzw. Voucher Captain ≥ 1.1.0), P = Paralleltest (Mehrprozess-Runner, R0-14 L5), E = E2E, S = statischer Guard. Jeder Fall prüft zusätzlich: Anzahl `ccap_grant`, `ccap_grant_event`, `ccap_outbox_event`, Affiliate-Provisionen (über `wpac_get_commissions()`), Voucher-Einlösezustand (über `get($key)`).

### 6.1 Doppel-Event und Idempotenz

| ID | Art | Gegeben / Aktion | Erwartung | Ziel |
|---|---|---|---|---|
| FIN-001 | I | Woo-Bestellung bezahlt: `payment_complete`, `status_processing`, `status_completed` und Reconcile-Lauf | je Position × Ziel genau 1 Grant, 1 × `grant.created`/`grant.activated`; Affiliate genau 1 Provision; 0 Affiliate-Aufrufe aus Course | R3-02, R3-19 |
| FIN-002 | P | gleicher Woo-Hook 10 × parallel in 2–10 Prozessen | wie FIN-001 (CORE-03) | R3-05, R3-19 |
| FIN-003 | I | Voucher: Einlöseformular doppelt abgeschickt / Reload | gleicher `redemption_key`, 1 Reservierung, 1 Grant-Satz, Ergebnis 2 = `duplicate` | R3-04 |
| FIN-004 | I | `commit` wird nach Erfolg wiederholt; `vcap_voucher_redeemed` feuert | keine zweite Aktivierung, kein Grant aus dem Hook | R3-04 |
| FIN-005 | I | Dispute-Webhook mit gleicher Event-ID 3 × | 1 Fallübergang, 2 × `202 duplicate` | R3-03, R3-19 |
| FIN-006 | I | Outbox-Replay `grant.activated` (IA-03) | gleiche `event_id`; Consumer dedupliziert; kein neuer Grant | R3-19 |
| FIN-007 | I | Backfill: Reconcile über 500 bestehende Bestellungen nach Connector-Freigabe | nur fehlende Grants, keine Duplikate, keine Provision | R3-03, R3-19 |
| FIN-008 | I | Woo-Bestellung `pending`, dann `on-hold` ohne `date_paid`, dann `cancelled`; zweite Bestellung `pending` → bezahlt | erste Bestellung: 0 `ccap_grant`, 0 `grant.*`-Events, Woo-Status `cancelled`; zweite: genau 1 Grant erst bei Zahlung, direkt `active` (E-293, E-295) | R3-02, R3-03 |

### 6.2 Race bei der Einlösung

| ID | Art | Gegeben / Aktion | Erwartung | Ziel |
|---|---|---|---|---|
| FIN-010 | P | zwei Konten lösen denselben Einmalgutschein gleichzeitig ein | genau 1 `committed`; anderes Konto `ccap_code_in_progress` bzw. `ccap_code_already_used`; 1 Grant-Satz | R3-05 |
| FIN-011 | P | ein Konto, zwei Tabs, gleicher Code | gleicher Schlüssel (LV-Lock), 1 Grant-Satz | R3-05 |
| FIN-012 | I | TTL läuft zwischen S3 und S4 ab (Zeit vorstellen) | `vcap_reservation_missing` → Grants `revoked` `voucher_reservation_lost`; Neuversuch mit Schlüssel `n+1` | R3-04, R3-05 |
| FIN-013 | I | Gutscheinkauf wird während offener Reservierung erstattet | `commit` → `vcap_cancelled`; Grants `revoked`, `release` | R3-05 |
| FIN-014 | I | Prozessabbruch nach `reserve`, vor S3 | Retry: `get` = `reserved` → S3/S4; kein Doppelverbrauch; DB enthält keinen Klarcode | R3-04 |
| FIN-015 | I | Prozessabbruch nach `commit`, vor S5 | Retry: `get` = `committed` → Grants `active`; Nutzer sieht bis dahin „wird aktiviert“ | R3-04 |
| FIN-016 | I | `vcap_temporary_failure` bei `reserve` und bei `commit` (erzwungener Ausfall) | Saga bleibt, Retry mit gleichem Schlüssel; Integrationsstatus `degraded` | R3-04, R3-19 |
| FIN-017 | I | gleicher Schlüssel, anderer Nutzer (manipuliert) | `ccap_idempotency_conflict`; kein Grant | R3-05 |
| FIN-018 | I | Kursgutschein mit Kanal `shop` konfiguriert; Einlöseversuch im Warenkorb und extern | Warenkorb weist den Kursgutschein ab, Diagnosewarnung (E-279); externe Einlösung erzeugt genau 1 Zugang; Gutscheinprodukt mit Kursmapping wird beim Speichern abgewiesen (FIN-INV-05) | R3-05 |
| FIN-019 | I | Zieltyp B: Produktmapping wird zwischen `reserve` und `commit` geändert | Grants nach eingefrorener Zielliste | R3-04 |

### 6.3 Teilrefund

| ID | Art | Gegeben / Aktion | Erwartung | Ziel |
|---|---|---|---|---|
| FIN-020 | I | 3 Positionen, eine voll erstattet | nur deren Grants `revoked` (CORE-05) | R3-03 |
| FIN-021 | I | Gruppenposition Menge 10, 3 erstattet | 7 Plätze, Reduktionsregel R0-09 §4.4; entfernte Seat-Grants `revoked` | R3-03 |
| FIN-022 | I | Refund nur als Betrag | keine Grant-Änderung, Warnung | R3-03 |
| FIN-023 | I | Teilbetrag Menge 1 mit `revoke_on_any_refund` bzw. Standardregel | `revoked` bzw. unverändert + Hinweis | R3-03 |
| FIN-024 | I | Refund gelöscht | neue Revision `active` (`refund_reversed`), alte `revoked`; Affiliate `reinstate` | R3-03, R3-19 |
| FIN-025 | I | Modulprodukt erstattet, Ganzkurs-Grant aus manueller Quelle vorhanden | Modul-Grant `revoked`, Zugang bleibt, kein `access.changed` | R3-03 |
| FIN-026 | I | Refund-Hook vor verspätetem `paid`-Hook | Endzustand `revoked`, `stale_event` (CORE-07) | R3-03 |
| FIN-027 | I | Bestellung teils mit Geschenkkarte bezahlt, voll erstattet | Grant `revoked`; Voucher stellt Guthaben selbst wieder her; Course ruft kein `refund`; Affiliate 1 Korrektur | R3-05, R3-19 |

### 6.4 Dispute gewonnen/verloren

| ID | Art | Gegeben / Aktion | Erwartung | Ziel |
|---|---|---|---|---|
| FIN-030 | I | PayPal `opened` (gültige Signatur) | Grants `suspended` `payment_disputed`; Woo-Status `on-hold`; Bestellnotiz | R3-03 |
| FIN-031 | I | danach `won` | Grants `active` `dispute_won`; Affiliate gibt Provision frei | R3-03, R3-19 |
| FIN-032 | I | danach `lost`, Lernender hat Zertifikat | Grants `revoked` `chargeback_lost`; Woo-Status `refunded` (Geld zurückgebucht, E-275/E-295); Zertifikat `issued`; Affiliate 1 Rückbuchung; 0 Course-Affiliate-Aufrufe | R3-03, R3-19 |
| FIN-033 | I | zwei Fälle, einer `won`, einer offen | bleibt `suspended` | R3-03 |
| FIN-034 | I | `won` (jünger) trifft vor `opened` (älter) ein | Fall endgültig `won`, `opened` → `stale_event` | R3-03 |
| FIN-035 | I | ungültige Signatur / falsche `cert_url` / zu alt | `401 ccap_signature_invalid` bzw. abgewiesen; keine Wirkung; Audit gedrosselt | R3-03 |
| FIN-036 | I | `lost` nach bereits erfolgtem Vollrefund | keine Änderung (`revoked` bleibt), kein zweites Event | R3-03 |
| FIN-037 | I | Klarna-Konflikt manuell gemeldet (Bestellaktion) | gleicher Pfad wie Webhook; Capability + Begründung + Audit | R3-03 |
| FIN-038 | I | Transaktions-ID ohne exakten Treffer | `202`, Audit `dispute_unmatched`, keine Wirkung | R3-03 |

### 6.5 Partner offline / inkompatibel

| ID | Art | Gegeben / Aktion | Erwartung | Ziel |
|---|---|---|---|---|
| FIN-040 | I | Voucher Captain wird bei Saga `reserved` deaktiviert; 24 h vergehen | Grants `failed` `partner_unavailable`; nach Reaktivierung Retry (G-13): `committed` → aktiv, sonst `revoked` | R3-04, R3-19 |
| FIN-041 | I | WooCommerce deaktiviert, Bestellungen ändern sich nicht; Reaktivierung | kein Widerruf; Reconcile ab Deaktivierungszeitpunkt ohne Duplikate (CORE-12) | R3-03, R3-19 |
| FIN-042 | I | Affiliate Captain deaktiviert/fehlt | Course-Grants unverändert; kein Fehler, keine Abhängigkeit | R3-19 |
| FIN-043 | I | Pro-Lizenz abgelaufen (E-93) | Woo-Grants/Widerrufe und Saga-Fortsetzung laufen; neue Einlösung `ccap_feature_unavailable` | R3-19 |
| FIN-044 | I | Voucher Captain 1.0.x aktiv | `active_incompatible`; Einlöse-UI `ccap_feature_unavailable`; bestehende Voucher-Grants unverändert | R3-19 |
| FIN-045 | I | Haltemodus (E-85) während Refund | Grant-Übergang erfolgt, Outbox-Zustellung bleibt `pending` | R3-19 |
| FIN-046 | I | Woo-Hook verloren (simuliert), Reconcile-Lauf | fehlender Übergang wird nachgeholt | R3-03 |

### 6.6 Finanz-Invarianten und Provision

| ID | Art | Gegeben / Aktion | Erwartung | Ziel |
|---|---|---|---|---|
| FIN-050 | S | Guard über Course-Kern und Pro-Add-on | keine Treffer für `wpac_record_`, `wpac_v1_record`, `/reinstatements`, `wc_create_refund`, `wc_create_order`, `->api_v1->`; `redemptions->refund(` nur in der E-283-Aktion | R3-19 |
| FIN-051 | E | Kurskauf mit Rabattcode, Affiliate-Partnerlink | 1 Provision netto nach Rabatt; 1 Grant | R3-19 |
| FIN-052 | E | Gutscheinkauf (Partnerlink) → externe Einlösung | 1 Provision beim Kauf, 0 bei Einlösung; 1 Grant (Quelle `voucher`) | R3-05, R3-19 |
| FIN-053 | U | Schema aller Course-Events | kein Feld für Betrag, Währung, Steuer, Zahlungsart, Code, Gutscheinwert | R3-19 |
| FIN-054 | E | Kurskauf mit Mitgliederrabatt | Preis rabattiert, Positions-Meta gesetzt; 1 Provision netto nach Rabatt; Basis-Grant unverändert | R3-02, R3-19 |
| FIN-055 | E | Woo-Kauf eines Kurses mit Geschenkkarte als Zahlung und zusätzliche externe Einlösung eines Kursgutscheins für denselben Kurs | 2 unabhängige Grants (Woo, Voucher), Widerruf eines lässt den anderen (CORE-04) | R3-05 |

### 6.7 Auto-Produkte und Mitgliederrabatt

| ID | Art | Gegeben / Aktion | Erwartung | Ziel |
|---|---|---|---|---|
| FIN-060 | I | Sync zweimal für Kurs mit 2 Modulen | 3 Produkte (Kurs + 2 Module), Entwurf ohne Preis (E-280); zweiter Lauf: 0 neue | R3-02 |
| FIN-061 | I | Titel im Woo-Produkt manuell geändert, Kurs umbenannt | manueller Titel bleibt, Drift im Dry-run | R3-02 |
| FIN-062 | I | Kurs archiviert (E-108) | Produkt nicht kaufbar; bestehende Grants aktiv | R3-02 |
| FIN-063 | I | Mapping nach Verkauf geändert, dann Positionsrefund | Widerruf trifft die eingefrorenen Ziele | R3-03 |
| FIN-064 | I | Mitgliederrabatt: Basis-Grant wird zwischen Warenkorb und Bestellanlage widerrufen | Preis bei Bestellanlage neu berechnet, kein Rabatt | R3-02 |
| FIN-065 | I | zwei Rabattregeln passen; Upgrade-Produkt als Basis; Rabattcode zusätzlich | nur bester Rabatt; Upgrade zählt nicht; Rabattcode und Mitgliederrabatt nicht kombiniert, der bessere gilt (E-281) | R3-02 |
| FIN-066 | I | Auto-Produkt in Woo gelöscht | Mapping verwaist (Diagnose), Grants bleiben, kein Neuanlegen | R3-02 |

### 6.8 Lernbeginn, Zertifikat, kein Wiederaufleben

| ID | Art | Gegeben / Aktion | Erwartung | Ziel |
|---|---|---|---|---|
| FIN-070 | I | Vollrefund nach Kursabschluss und Zertifikat (CORE-06) | Grant `revoked`; Lernhistorie, Versuche, Zertifikat unverändert; Verifikation `issued` | R3-03 |
| FIN-071 | I | Refund während laufender Prüfung, danach Abgabe | Antworten nach Widerruf abgewiesen, Abgabe gewertet, kein Auto-Zertifikat (E-113) | R3-03 |
| FIN-072 | I | Refund zurückgenommen in `extend`-Kette | neue Revision, Kettenposition über `chain_anchor_at` (R0-09 Δ-3) | R3-03 |
| FIN-073 | I | Gutschein nach Einlösung `voided`, Lernender hat zusätzlich Woo-Grant | nur Voucher-Grant `revoked` (E-35); Zugang bleibt | R3-05 |
| FIN-074 | I | Gutschein `reactivated` nach Void | widerrufener Grant bleibt `revoked`; Neueinlösung → Schlüssel `n+1`, neue Grants | R3-05 |
| FIN-075 | E | Nutzungsereignis `first_access` bei Woo-Kauf mit Zustimmungs-Gate | Event ohne Zahlungsdaten; Positions-Meta mit Zeitstempel des ersten Lernzugriffs (E-284) | R3-02 |
| FIN-076 | I | Saga `reserved`, Partner erreichbar, 24 h vergehen | `release`, Grants `failed` (`pending_timeout`), Hinweis „erneut einlösen“; Neueinlösung mit Schlüssel `n+1` | R3-04 |
| FIN-077 | I | Woo-Zusatzmodul-Produkt mit Basiskurs-Abhängigkeit; Basis-Grant erstattet | Zusatz-Grant bleibt `active`, zählt aber nicht mehr (R0-09 §3.5); kein Finanzaufruf | R3-02, R3-03 |

### 6.9 Mitgliedschaft (E-203)

| ID | Art | Gegeben / Aktion | Erwartung | Ziel |
|---|---|---|---|---|
| FIN-080 | I | FakeMembershipAdapter: aktiv → pausiert → aktiv → abgelaufen → reaktiviert | `active` → `suspended` → `active` → `expired` → neue Revision | R3-20 (E-301) |
| FIN-081 | I | Abo-Produkt im Woo-Mapping, das ein Membership-Adapter beansprucht | Mapping abgewiesen; Abo-Verlängerung erzeugt keinen `woo_order`-Grant | R3-02, R3-20 |

---

## 7. Entscheidungen (beschlossen)

| Nr. (alt) | Frage | Beschluss |
|---|---|---|
| D40-1 | Setzt ein verlorener Dispute den Woo-Bestellstatus? | **E-275:** ja (auf `refunded`/`cancelled`), damit auch Affiliate die Provision storniert |
| D40-2 | 24-h-Pending-Timeout bei unbezahlten Woo-Bestellungen? | **E-276:** gilt nicht; Grant entsteht erst bei Zahlung, solange die Bestellung offen ist |
| D40-3 | Gutschein-Einlösung hängt länger als 24 h | **E-277:** Reservierung freigeben, Grant `failed`, Lernender bekommt Hinweis zum erneuten Einlösen |
| D40-4 | Guthaben über die Einlöse-API? | **E-278:** nein, Kursgutscheine sind Zugangsgutscheine |
| D40-5 | Kursgutscheine zusätzlich im Woo-Warenkorb? | **E-279:** nein, nur über die Course-Einlösung |
| D40-6 | Preis automatisch angelegter Woo-Produkte | **E-280:** pflegt der Shop in Woo; Produkte entstehen als Entwurf |
| D40-7 | Mitgliederrabatt mit Rabattcodes kombinierbar? | **E-281:** nein, der bessere Rabatt gilt |
| D40-8 | Kursprodukte einzeln oder als Menge? | **E-282:** einzeln; mehrere Plätze nur über Gruppenprodukte |
| D40-9 | Betreiberaktion „Gutscheinwert zurückgeben“ | **E-283:** ja, nur manuell, mit Begründung und Audit (`refund_key = ccap:rf:{grant_uid}`) |
| D40-10 | Lernbeginn als Vermerk an der Woo-Bestellposition | **E-284:** ja, Zeitpunkt des ersten Lernzugriffs wird vermerkt |

**Weitere Beschlüsse:** Affiliate-Meldung → E-37; Gutscheinziel → E-31; Voucher-Storno → E-35; ein Code je Platz → E-36; Kontopflicht → E-30; Kundenwechsel → E-117; Prüfungsabgabe → E-113; Pro-Lizenzablauf → E-93; Pending-Timeout → E-201 (Geltungsbereich E-276/E-277); Inbox-Frist → E-116; Mitgliedschaftsvertrag → E-203.

---

## 8. Deltas zu anderen Dokumenten (mit deren Freigabe einzuarbeiten)

| Nr. | Dokument | Delta | Status |
|---|---|---|---|
| Δ40-1 | R0-06 `ccap_grant`, R0-09 §2.2, ENTSCHEIDUNGEN E-27 (Folgetext) | `module_id bigint unsigned NOT NULL DEFAULT 0` (0 = ganzer Kurs) statt NULL; Unique `grant_source` um `module_id` erweitern; gleiches für `ccap_group` (`group_source`) bei Modul-Gruppenprodukten | eingearbeitet (R0-06 §4.4, R0-09 §2.2; Modul in `ccap_group_course` statt `group_source`) |
| Δ40-2 | R0-06 (Pro-Schema), R0-39 | Tabelle `ccap_source_saga` (§3.2) im Pro-Add-on; Retention wie Inbox (E-116, `failed` bis Klärung + 90 Tage) | eingearbeitet (R0-06 §4.10) |
| Δ40-3 | R0-36 §3.3 Nr. 2 | „Grant aktiv geschrieben → commit“ ersetzen durch „Grants `pending_confirmation` → commit → `active`“ (R0-09 §2.2 gilt) | an R0-36 übergeben |
| Δ40-4 | R0-19 §3.2/§3.4 | `reason`-Enums für `ccap_code_invalid` und `ccap_code_expired`; Mapping `vcap_insufficient_balance`, `vcap_not_committed`, `vcap_refund_exceeded`; `vcap_invalid_request` zusätzlich als Health-Klasse `validation` | eingearbeitet (R0-19 §3.4) |
| Δ40-5 | R0-19 §5.8 `SourceSnapshot` | Felder `disputeState: none|open|won|lost` und `lines[].frozenTargets[]` (`courseUid`, `moduleUid|0`, `scopes`) | eingearbeitet (R0-19 §5.8) |
| Δ40-6 | TODO/R0-24 | eigene R3-Task „Membership-Adapter (E-203)“; FIN-080/081 Ziel R3-20 | **erledigt durch E-301** (R3-20 im TODO aufgenommen) |
| Δ40-7 | R0-09 §5 | RF-Zeilen um FR-08…FR-10 (Dispute über Webhook) und FR-12…FR-16 (Voucher-Saga) ergänzen; RF-15 „gelöscht“ als bewusste LB-Abweichung (E-55-Liste) | an R0-09 §5 übergeben (Verweis auf §5 dieses Dokuments) |
| Δ40-8 | R0-09 §6.1 | Lock LV `voucher-code:<code_hmac>` zwischen L1 und L2 | eingearbeitet (R0-09 §6.1) |
| Δ40-9 | Konzept Kap. 30.2 (Affiliate-Zeile), Kap. 45 (Voucher-API) | redaktionell: „Course meldet keine Verkäufe“ (E-37); Kurs-Einlösung nur `vcap()->redemptions` | redaktionell offen (Konzept) |
| Δ40-10 | R0-12/R0-24 Traceability | FIN-001…FIN-081 als Eingangskatalog COMMERCE (E-186) für R3-01 aufnehmen | an R0-12/R0-24 übergeben |

---

## 9. Nachweis

- Gelesen (nur lesend): `wp-couponmaster@2d84943` `CONNECTORS.md` (§1–§7), `API.md` (vollständig), `src/API/V1/RedemptionService.php:35, 72-147, 239-293, 326, 388, 555-667`, `voucher-captain.php:21`; `wpaffiliatemaster@3b7130e` `docs/CONTRACT.md:1-100`, `src/Integrations/WooCommerce.php:35-96`, `src/Integrations/WooCommerce/OrderSync.php:845-925`; Pass über R0-36 §2.6.
- Projektdokumente: TODO R0-40, R3-02…R3-06, R3-19, CORE-SPEC-004/005; Konzept Kap. 32, 34.3, 34.4, 35.1–35.5; R0-09 §0, §2, §3, §5, §6, §8, §9; R0-19 §3, §5; R0-34 §1, §2, INT-002…004; R0-36 §2.6, §3, §4, §7, §8; R0-02 B §2.10, §2.11, §2.13, INV-B-002/003/087/093/130; C INV-C-184; ENTSCHEIDUNGEN Beschlussprotokoll und E-36, E-37, E-53, E-93, E-113, E-116, E-117, E-201, E-203.
- Keine Änderung an Suite-Repositories oder LearnBoating, keine Implementierung, kein Commit. Beschlüsse E-275…E-284 sind eingearbeitet (16.11.2026); Deltas §8 mit Status.

## Offene Punkte nach Einarbeitung

1. **E-275 Zielstatus:** Der Beschluss nennt „`refunded`/`cancelled`“ ohne Regel, wann welcher gilt; für `opened`/`won` ist nur die Empfehlung aus D40-1 übernommen. Feinschnitt in R3-03. → **erledigt durch E-295** (`refunded` bei Rückbuchung, `cancelled` nur bei Storno vor Zahlung; §4.4, FIN-032)
2. **E-276 vs. §4.2:** Der Beschluss sagt „Grant entsteht erst bei Zahlung“; §4.2 legt für offene Bestellungen einen Grant `pending_confirmation` ohne Zugang an (Anzeige „wird aktiviert“). Klären, ob der Pending-Grant entfallen soll. → **erledigt durch E-293** (kein Pending-Grant für unbezahlte Bestellungen; §4.2, FIN-008); Folgefrage Vorabfreigabe: **neue Frage NF-01** (R0-GATE-protokoll.md §5; kein Blocker für Gate A, Klärung vor Gate D (R3-02))
3. **E-277 `failed` nach Release:** Ein freigegebener Gutscheinschlüssel ist nicht wiederholbar; `failed` ist hier für diesen Schlüssel endgültig, das erneute Einlösen erzeugt neue Grants (`n+1`). R0-09 G-13 (Retry) gilt dafür nicht. → **erledigt durch E-294**
4. **Δ40-6:** eigene R3-Task „Membership-Adapter (E-203)“ fehlt weiterhin im TODO. → **erledigt durch E-301** (R3-20 im TODO; FIN-080/081 Ziel R3-20)
5. **E-26 im Shop:** Ob Woo-Zusatzmodul-Produkte die Abhängigkeit automatisch aus dem Mapping setzen (E-34 Auto-Produkte), ist nicht beschlossen. → **neue Frage NF-12** (R0-GATE-protokoll.md §5; kein Blocker für Gate A, Klärung vor Gate D (R3-02))
