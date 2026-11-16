# R0-02/R0-03 · Inventar Teil B – Commerce, Gruppen, Gutscheine, Partnerprogramm

**Status:** Inventur und Klassifikation (R0-02, R0-03). Keine Entscheidung über R1/R3 außer als Vorschlag (R0-04). **Stand:** 16.11.2026
**Bezug:** TODO R0-02, R0-03 (Vorarbeit R0-04, R0-05, R0-09, R0-40); Konzept Kap. 7, 26, 31, 32, 35, 47, 48

## 1. Kopf

| Angabe | Wert |
|---|---|
| Bereich | `bootspruefung-learning/src/Commerce` (mit `Disputes/` und `WooCommerce/`), `learnboating-affiliate-connector/`, `learnboating-voucher-connector/` und die zugehörigen Tests |
| Quelle | `hastla007/learnboating`, Commit `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (wie R0-01) |
| Arbeitskopie | Shallow-Clone, nur gelesen, nicht verändert |
| Klassifikation | `reuse` = Logik übernehmen (neue Namen), `abstract` = generischer Kern mit neutraler Schnittstelle, `replace` = Aufgabe gehört einem anderen System bzw. einer anderen Technik, `exclude_domain_specific` = boots-, funk- oder prüfungsspezifisch, entfällt |
| Phasen | R1-Free, R1-Pro, R2, R3.1 Woo, R3.2 Voucher, R3.4 sonstige, exclude |

### 1.1 Gelesene Dateien – Code (vollständig, Zeile für Zeile)

`bootspruefung-learning/src/Commerce/` – **45 PHP-Dateien, 12 275 Zeilen** (Summe stimmt mit R0-01 überein):

| Datei | Zeilen | Datei | Zeilen |
|---|---|---|---|
| `AccessStatus.php` | 178 | `WooCommerce/AutoReconcile.php` | 229 |
| `CourseRelations.php` | 130 | `WooCommerce/BankDetails.php` | 45 |
| `DefaultDuration.php` | 67 | `WooCommerce/CartDuplicateHint.php` | 365 |
| `Disputes/DisputeWebhooks.php` | 888 | `WooCommerce/CartRelationHint.php` | 366 |
| `EntitlementPlanner.php` | 184 | `WooCommerce/Compatibility.php` | 24 |
| `EntitlementService.php` | 529 | `WooCommerce/ContractSummary.php` | 175 |
| `ExpiryReminder.php` | 472 | `WooCommerce/CouponField.php` | 102 |
| `GroupService.php` | 765 | `WooCommerce/DigitalConsent.php` | 498 |
| `Labels.php` | 102 | `WooCommerce/Gateways.php` | 107 |
| `MappingCheck.php` | 172 | `WooCommerce/Integration.php` | 886 |
| `MoneyBack.php` | 332 | `WooCommerce/LegalTextsEmail.php` | 264 |
| `OrderItemSnapshot.php` | 34 | `WooCommerce/MemberDiscounts.php` | 181 |
| `OrderSnapshot.php` | 30 | `WooCommerce/MoneyBackWoo.php` | 529 |
| `OrderSource.php` | 21 | `WooCommerce/OrderButton.php` | 120 |
| `OrderSync.php` | 253 | `WooCommerce/OssMonitor.php` | 287 |
| `Plan.php` | 43 | `WooCommerce/PriceHistory.php` | 134 |
| `ProductAccessRepository.php` | 159 | `WooCommerce/PriceNote.php` | 81 |
| `ReminderOptOut.php` | 187 | `WooCommerce/ProductPanel.php` | 366 |
| `ShopCatalog.php` | 1149 | `WooCommerce/PurchaseInfo.php` | 181 |
| `VoucherGrants.php` | 301 | `WooCommerce/ShopCache.php` | 161 |
| | | `WooCommerce/SimUpgrade.php` | 188 |
| | | `WooCommerce/VideoUpgrade.php` | 241 |
| | | `WooCommerce/WithdrawalFunction.php` | 533 |
| | | `WooCommerce/WithdrawalTexts.php` | 114 |
| | | `WooCommerce/WooOrderSource.php` | 102 |

`learnboating-affiliate-connector/` – 7 Dateien (583 PHP-Zeilen, wie R0-01):

| Datei | Zeilen | Lesetiefe |
|---|---|---|
| `learnboating-affiliate-connector.php` | 32 | vollständig |
| `src/Connector.php` | 374 | vollständig |
| `uninstall.php` | 28 | vollständig |
| `tests/connector-test.php` | 149 | vollständig |
| `README.md` | 47 | vollständig |
| `CHANGELOG.md` | 20 | vollständig |
| `languages/learnboating-affiliate-connector.pot` | 132 | Übersetzungsvorlage, kein Code (28 `msgid`) |

`learnboating-voucher-connector/` – 9 Dateien (1 517 PHP-Zeilen, wie R0-01):

| Datei | Zeilen | Lesetiefe |
|---|---|---|
| `learnboating-voucher-connector.php` | 33 | vollständig |
| `src/Connector.php` | 866 | vollständig |
| `uninstall.php` | 32 | vollständig |
| `tests/connector-integration-test.php` | 385 | vollständig |
| `tests/locks-test.php` | 169 | vollständig |
| `tests/setup.php` | 32 | vollständig |
| `README.md` | 62 | vollständig |
| `CHANGELOG.md` | 26 | vollständig |
| `languages/learnboating-voucher-connector.pot` | 161 | Übersetzungsvorlage, kein Code (35 `msgid`) |

**Abdeckung Code:** 45 + 4 + 6 PHP-Dateien = **55 PHP-Dateien, 14 375 Zeilen, 100 % gelesen.** Die 4 MD-Dateien wurden ebenfalls gelesen. Die 2 POT-Dateien wurden nur gezählt.

### 1.2 Gelesene Dateien – zugehörige Tests in `bootspruefung-learning/tests`

| Datei | Zeilen | Lesetiefe | Bezug |
|---|---|---|---|
| `integration/commerce-test.php` | 303 | vollständig | Planer, Sync, Erstattung, Laufzeit, manuell, Abgleich (ohne Woo) |
| `integration/support/FakeOrders.php` | 44 | vollständig | Testquelle `OrderSource` |
| `integration/woocommerce-setup.php` | 34 | vollständig | Woo aktivieren (Gast-Kasse an, BACS an) |
| `integration/voucher-grant-test.php` | 215 | alle Abschnitte und alle `$check`-Zeilen | `VoucherGrants` |
| `integration/woocommerce-test.php` | 1 963 | alle 39 Abschnitte (366 Prüfungen), Zeilennummern der Abschnitte | Woo-Adapter gesamt |
| `integration/membership-test.php` | 1 360 | alle Abschnitte; Gruppen-Abschnitt Z. 400–660 Prüfung für Prüfung | Gruppen, Schnupper-Paket, Erinnerung, Zugangsstatus |
| `integration/account-test.php`, `extensions-test.php`, `operations-test.php`, `admin-test.php`, `mobile-api-test.php`, `customer-faq-test.php` | – | nur Abschnitte mit Commerce-Bezug (per Suche gefunden) | Querschnitt; Hauptinventur in Teil C/D |
| `e2e/checkout-flow.mjs`, `e2e/checkout-setup.php`, `e2e/membership-setup.php` | 158 / 94 / 53 | nur Kopf und Commerce-Importe | Browser-Kaufablauf |

**Abdeckung Tests:** Alle Prüfungen der Commerce-Tests sind erfasst. Die anderen Testdateien gehören zu Teil C/D. Hier sind nur ihre Commerce-Abschnitte zitiert.

### 1.3 Wichtige Befunde vorab

1. **Kein Rechnungs- und kein Steuercode im Bereich.** R0-01 nennt „Rechnungen“. Im Bereich gibt es aber keine Rechnungsklasse. Steuern kommen nur in `OssMonitor` (OSS-Schwelle) und bei der Erstattung brutto (`WooOrderSource::taxRefunded`) vor.
2. **Der Grant-Kern ist schon shop-neutral angelegt.** `OrderSnapshot`, `OrderSource` und `EntitlementPlanner` greifen nicht auf Woo zu. `grantFromReference()` ist eine generische Referenz-API. Das passt zu `source_type`/`source_id` (Konzept 31.2, 47). Woo-gebunden sind nur `wc_item:<id>` (`EntitlementService.php:31-34`) und die Spalten `order_id`, `order_item_id` und `sku_snapshot`.
3. **Gruppen entstehen nur aus Bestellpositionen** (`GroupService::syncItem`, Z. 94-131). Es gibt keine manuelle Gruppenanlage. Konzept 7 verlangt aber Gruppen in Free ohne Kaufpflicht. Das ist eine **Lücke**, die R1-13 und R1-35 neu bauen müssen.
4. **`idempotency_key` ist nicht eindeutig.** Die Spalte wird gefüllt (`EntitlementService.php:103, 272`). Eindeutig ist aber nur `reference_scope (reference_key, course_code, feature_code)` (`Infrastructure/Database/Schema.php:662`, Teil C).
5. **Feature-Codes sind fachspezifisch** (`navigation_trainer`, `radio_training`, `device_simulation`, `praxis_trainer`; `Labels.php:19-30`). Deshalb ist die Zugangsgranularität eine Produktentscheidung (→ D-10).
6. **Zwei Lock-Techniken nebeneinander:** `Infrastructure\NamedLock` (GET_LOCK) und `Infrastructure\Lock` (Optionszeile). Dazu kommt eine eigene GET_LOCK-Kopie im Voucher-Connector (`Connector.php:611-655`).

## 2. Funktionstabelle

Spaltenkürzel: **Art** = UI/PHP/Daten/API/Cron/Import/Test/Commerce. Bei **Golden Master** steht die Quelltest-Datei mit Abschnitt und Zeile.

### 2.1 Grant-Kern: Planer, Plan, Snapshots, Sync

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-001 | `src/Commerce/EntitlementPlanner.php:21-27` | Daten | Zugangsstatus `pending, active, suspended, revoked, expired` | abstract | Deckt Konzept 7 ab. Kap. 35.2 verlangt zusätzlich `pending_claim`, `pending_confirmation` und `failed`. | `GrantState`-Enum mit Übergangstabelle | R1-Free | R0-09, R1-13 | `commerce-test.php` (alle Abschnitte) |
| INV-B-002 | `EntitlementPlanner.php:34-71` | Commerce | `orderTarget()`: Bestellstatus → Sollstatus und Grund (Storno/Erstattung → revoked; bezahlt → active bzw. `consent_missing` → pending; on-hold mit Zahldatum → suspended „payment_disputed“; failed → revoked; `checkout-draft` → nichts; pending/on-hold → pending; Unbekanntes → nicht ändern) | abstract | Die Statusnamen sind Woo-spezifisch. Das Muster „Quellstatus → Grant-Sollstatus, unbekannt = nicht ändern“ ist generisch. | Interface `SourceStatusMapper` im Kern, Woo-Tabelle im Adapter | R3.1 Woo (Kern-Interface R1) | R3-02, R3-03, R0-09 | `commerce-test.php` „Überweisung“ Z. 79, „Dispute“ Z. 92, „Storno“ Z. 147; `woocommerce-test.php` Z. 736 (T147/T157) |
| INV-B-003 | `EntitlementPlanner.php:78-124` | PHP | `plan()`: Sollplan je Position × (Kurs, Feature) aus Zuordnung und vorhandenen Zeilen; Erstattungsregeln `revoke_on_full_refund`, `revoke_on_any_refund` und `keep`; entfernte Position → revoked `item_removed` | abstract | Ein shop-neutraler Diff-Planer, ohne DB- und Shopzugriff. Der Kern für idempotente Wiederholung (Konzept 35.2). | `GrantReconciler` (Quellzeilen → Grant-Änderungen) | R1-Free (Kern), Daten von R3.1 | R0-09, R1-13, R3-03 | `commerce-test.php` „Erstattungen“ Z. 110-145 |
| INV-B-004 | `EntitlementPlanner.php:127-170` | PHP | `decide()`: Übergangswächter. Kundenwechsel überträgt den Grant. Eine manuelle Entscheidung bleibt, nur Widerruf geht trotzdem durch. Expired bleibt expired. Bei Erstattung ohne Positionsbezug gibt es kein Wiederaufleben. pending → suspended wird ignoriert. **Ein automatischer Widerruf lebt wieder auf**, wenn wieder bezahlt wird. | abstract | Die Logik ist wertvoll, widerspricht aber teilweise Konzept 35.2/35.3 („revoked endgültig je Quellrevision“) → D-1 | `GrantTransitionGuard` | R1-Free | R0-09, R3-03 | `commerce-test.php` Z. 122-124, 161-163, 208-210 |
| INV-B-005 | `EntitlementPlanner.php:172-183` | PHP | `key()` und `meta()`: Planschlüssel Position\|Kurs\|Feature, JSON-Meta lesen | reuse | Technische Hilfen | `GrantKey`, Meta-Codec | R1-Free | R1-02 | – |
| INV-B-006 | `src/Commerce/Plan.php:10-43` | PHP | Plan als Vorschau und Ausführung: `changes`, `warnings`, `removals()` für die Sicherheitsabfrage beim Abgleich | reuse | Dry-run und Vorschau verlangt Konzept 7 („Admin-Dry-Run“) | `GrantPlan` | R1-Free | R3-03, R3-10 | `commerce-test.php` „Vorschau schreibt nichts“ Z. 281-282 |
| INV-B-007 | `OrderSnapshot.php:13-30`, `OrderItemSnapshot.php:10-34` | Daten | Shop-neutrale Bestellsicht; `fullyRefunded()` und `anyRefund()` mit Rundungstoleranz; `unallocatedRefund`; `consent` | abstract | Das Snapshot-Muster ist generisch. Die Felder `paymentMethod` und `consent` sind shopnah. | `SourceSnapshot`, `SourceLineSnapshot` | R1-Free (Vertrag), R3.1 | R3-01, R3-02 | `FakeOrders.php` (Fixture) |
| INV-B-008 | `OrderSource.php:10-21` | API | Interface `snapshot()` und `orderIds()` (Woo oder Testquelle) | abstract | Genau die Adapter-Grenze aus Konzept 32 | `GrantSourceAdapter` | R1-Free (Interface) | R3-01 | `FakeOrders.php` |
| INV-B-009 | `OrderSync.php:36-91` | Commerce | `plan()` und `sync()`: vollständiger, idempotenter Abgleich je Ereignis. Filter `bootspruefung_order_access_target`. Gruppenpositionen getrennt. `no_account` wird je Anfrage nur einmal protokolliert (T243). | abstract | Orchestrierung Quelle → Planer → Service | `GrantSyncService` | R3.1 Woo (Kern R1) | R3-02, R3-03 | `commerce-test.php`; `woocommerce-test.php` Z. 275 |
| INV-B-010 | `OrderSync.php:93-139` | PHP | `syncGroups()` und `groupChanges()`: Gruppen je Position synchronisieren bzw. als Vorschau zeigen | abstract | Mehrplatzkauf → Kontingent | Seat-Pool-Sync im Adapter | R3.1 Woo | R3-02 | `membership-test.php` „Vorschau (reconcile)“ (Gruppen ab Z. 400) |
| INV-B-011 | `OrderSync.php:147-218` | Commerce | `suspendOrder`, `revokeOrder`, `approveOrder` (vor Zahlung freischalten) und `releaseOrder` (manuelle Entscheidung aufheben), jeweils mit Gruppen | abstract | Manuelle Aktionen je Quellvorgang | Admin-Kommandos je Quelle auf Grant-Ops | R3.1 Woo | R3-03, R3-10 | `commerce-test.php` „Manuelle Aktionen“ Z. 236-273 |
| INV-B-012 | `OrderSync.php:225-247` | Commerce | `reconcile()`: alle Bestellungen mit Lernprodukten seitenweise, dazu Bestellungen mit Zugängen ohne Zuordnung (Generator) | abstract | Backfill und Reconcile aus Konzept 7 und 35.2 | Reconcile-Job je Adapter | R3.1 Woo | R3-03, R3-10 | `commerce-test.php` „Abgleich und Filter“ Z. 275-295 |

### 2.2 Grant-Schreibdienst `EntitlementService`

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-013 | `EntitlementService.php:19-34` | Daten | Quellen `woocommerce`, `manual`, `voucher` (dazu `group` in `GroupService`); Referenz `wc_item:<id>` | abstract | Konzept 47: kein `wc_item` im Free-Kern; stabile `source_type`/`source_id` | `source_type` + `source_reference` | R1-Free | R0-06, R0-09 | – |
| INV-B-014 | `EntitlementService.php:37-65` | PHP | Lesen: `forOrder`, `forItem`, `forUser`, `orderIds` und `find` | abstract | `forOrder` und `forItem` sind Woo-Sichten | `GrantRepository` (je Quelle oder Lernendem) | R1-Free | R1-02 | – |
| INV-B-015 | `EntitlementService.php:72-118` | PHP | `apply()` und `create()`: Insert mit `reference_scope` (unique), `idempotency_key` sha256, `suppress_errors` gegen Race; Audit `entitlement_<type>`; Aktion `bootspruefung_entitlements_changed` | abstract | Idempotenz über Unique-Key ist der Kern von CORE-03. Woo-Spalten (`order_id`, `sku_snapshot`) gehören ins Meta bzw. die Quellreferenz. | `GrantRepository::createIdempotent` | R1-Free | R0-09, R1-02, R1-13, R1-42 | `commerce-test.php` Z. 88-90 („wiederholtes Zahlungsereignis ändert nichts“) |
| INV-B-016 | `EntitlementService.php:121-134` | PHP | `transition()`: Statuswechsel; bei der ersten Aktivierung wird die Gültigkeit gesetzt; Verkettung neu berechnen | reuse | Generisch | `GrantService::transition` | R1-Free | R0-09 | `commerce-test.php` Z. 99-101 |
| INV-B-017 | `EntitlementService.php:140-162` | PHP | `setValidity()`: Beginn = Zahldatum. `renewal_mode=extend` beginnt am spätesten Ende eines bestehenden Zugangs. `paid_at` wird gemerkt. | reuse | Laufzeitlogik ohne Fachbezug | Grant-Laufzeitrechner | R1-Free | R1-13 | `commerce-test.php` „Laufzeit und Verlängerung“ Z. 178-191 |
| INV-B-018 | `EntitlementService.php:165-206` | PHP | `restackIfNeeded()` und `restackExtensions()`: Die Verlängerungskette rückt bei Erstattung nach vorn und bei Rücknahme der Erstattung wieder zurück | reuse | So geht keine bezahlte Zeit verloren, und es läuft keine doppelt (CORE-04/05) | Verlängerungskette | R1-Free | R1-13, R0-09 | `commerce-test.php` „Verlängerungskette“ Z. 212-225; `voucher-grant-test.php` Z. 191 |
| INV-B-019 | `EntitlementService.php:213-243` | PHP | `alignVideoUpgrades()`: Das Video-Upgrade (SKU `-VIDEO`) läuft so lange wie die Theorie | replace | Die SKU-Konvention ist katalogspezifisch. Generisch wäre „abhängiger Zusatz-Grant“ → D-7 | `depends_on_grant` (optional) | R3.1 Woo (nur nach D-7) | R3-02 | `woocommerce-test.php` „Video-Upgrade“ Z. 1323 |
| INV-B-020 | `EntitlementService.php:248-297` | API | `forReference()` und `grantFromReference()`: Grants je Zuordnungszeile mit gleicher Referenz, idempotent; bei einer Kollision wird die vorhandene ID zurückgegeben | reuse | Die generische Grant-API für externe Quellen | `GrantService::grantFromSource` | R1-Free (genutzt von R3.2) | R1-13, R3-04 | `voucher-grant-test.php` „Idempotenz“ Z. 142-168 |
| INV-B-021 | `EntitlementService.php:304-322` | API | `revokeReference()`: Widerruf je Referenz; `keep` bleibt | reuse | Selektiver Entzug (CORE-04) | `GrantService::revokeSource` | R1-Free | R0-09, R3-04 | `voucher-grant-test.php` „Widerruf“ Z. 186-194 |
| INV-B-022 | `EntitlementService.php:332-361` | PHP | `grantManual()`: Pflichtbegründung, Enddatum muss in der Zukunft liegen, Referenz `manual:<uuid>`, `granted_by`, Audit | reuse | Free-Quelle `manual` (Konzept 7) | Manuelle Einschreibung | R1-Free | R1-13 | `commerce-test.php` Z. 250-267 |
| INV-B-023 | `EntitlementService.php:364-381` | PHP | `extend()`: Enddatum setzen; `meta.extended` schützt vor dem Gruppenabgleich (T158); expired ↔ active | reuse | Generisch | Grant verlängern | R1-Free | R1-13 | `commerce-test.php` Z. 259-269 |
| INV-B-024 | `EntitlementService.php:387-423` | PHP | `setStatusManual()` und `releaseManual()`: Sperre `meta.manual{status,from,by,at,reason}`; beim Aufheben zurück zum Stand vorher (T160) | abstract | Das Prinzip ist generisch. Die Bedingung `source === woocommerce` ist es nicht. | Manuelle Sperre je Grant | R1-Free | R0-09 | `commerce-test.php` Z. 237-240, 270-271 |
| INV-B-025 | `EntitlementService.php:426-432` | Cron | `expireDue()`: stündlich active → expired (Aufruf `Plugin.php:316`) | reuse | Generisch | Ablaufjob | R1-Free | R1-17 | `commerce-test.php` Z. 208 |
| INV-B-026 | `EntitlementService.php:440-470` | UI | `search()` für die Adminliste: E-Mail, Login, SKU, `reference_key`, User-ID; Filter Kurs, Status, Quelle, Bestellung | abstract | SKU und Bestellung als Spalten sind Woo-Sicht | Grant-Suche | R1-Free | R1-38, R1-13 | `voucher-grant-test.php` Z. 202; `connector-integration-test.php` Z. 370-371 |
| INV-B-027 | `EntitlementService.php:478-503` | UI | `summaryForUser()`: „Mein Lernen“ mit einem Eintrag je Kurs und Feature (Rangfolge), spätestes Ende, Unbefristet gewinnt; nie aktive und widerrufene ausgeblendet; offen = Laufzeit ab Zahlung | reuse | Statussprache aus Konzept 31.2 | Zugangsübersicht des Lernenden | R1-Free | R1-16 | `commerce-test.php` Z. 190-205 |
| INV-B-028 | `EntitlementService.php:505-528` | PHP | `mustFind`, `requireReason`, `now()` (UTC) | reuse | Hilfen | – | R1-Free | R1-02 | – |

### 2.3 Produktzuordnung und Laufzeit-Vorgaben

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-029 | `ProductAccessRepository.php:18-33` | Daten | `FEATURE_ALL`, `RENEWAL_MODES (extend, parallel)`, `REFUND_RULES`, `features()` (= `all` + `AccessService::FEATURES`) | abstract | Verlängerungs- und Erstattungsregeln sind generisch. Die Feature-Liste ist fachspezifisch (→ D-10). | Grant-Policy (`renewal_mode`, `refund_rule`), neutrale Scopes | R1-Free (Policy), R3.1 (Mapping) | R0-08, R0-09 | `commerce-test.php` Z. 69-77 |
| INV-B-030 | `ProductAccessRepository.php:40-74` | Commerce | `forItem()` (eine Variation überschreibt sonst das Produkt, sonst erbt sie), `rows`, `seats()`, `hasAccess` | abstract | Produkt/Variation → Kurs: Mapping-Vertrag aus Konzept 32.2 A | `ProductCourseMapping` | R3.1 Woo | R3-02 | `commerce-test.php` „Produktzuordnung“ Z. 69-77 |
| INV-B-031 | `ProductAccessRepository.php:82-114` | Commerce | `save()`: Zuordnung ersetzen, Werte validieren; bestehende Grants tragen ihren Umfang selbst | abstract | Wie INV-B-030 | Mapping-Repository | R3.1 Woo | R3-02 | `commerce-test.php` Z. 292-295 („Zuordnung gelöscht: Erstattung widerruft trotzdem“) |
| INV-B-032 | `ProductAccessRepository.php:117-158` | API | `productIds()`, `grantingProducts()` und `activeMappings()` (RequestMemo): Kaufangebote für Sperrhinweise | abstract | Upsell-Link bei gesperrtem Inhalt | `AccessOfferProvider` (vom Adapter gefüllt) | R3.1 Woo | R3-13 | `woocommerce-test.php` „Kundenansichten“ Z. 349 |
| INV-B-033 | `DefaultDuration.php:14-67` | PHP | Vorbelegte Laufzeit (Produkt 365, manuell 182 Tage, sonst aus dem Katalog); „ohne Ablauf“ nur ausdrücklich (T101) | abstract | Die Regel „Unbefristet nur ausdrücklich“ ist generisch. Die Werte und der Katalogbezug sind fachlich. | Einstellung „Standardlaufzeit“ | R1-Free (manuell), R3.1 (Produkt) | R0-27, R1-13 | `admin-test.php` „Laufzeit vorbelegt“ Z. 402 |
| INV-B-034 | `MappingCheck.php:20-74` | UI | `issues()`: unbekannter Kurs, gelöschtes, unveröffentlichtes, nicht kaufbares oder nicht virtuelles Produkt, uneinheitliche Regeln, „ohne Ablauf“ | abstract | Mapping-Diagnose (R3-10/R3-14) | Connector-Health: Mapping | R3.1 Woo | R3-10, R3-14 | `woocommerce-test.php` „Prüfung der Produktzuordnungen“ Z. 1158 |
| INV-B-035 | `MappingCheck.php:114-139` | UI | `isOrphan()` und `removeOrphan()`: Zuordnung gelöschter Produkte entfernen; Grants bleiben | abstract | Reparaturaktion | Mapping-Reparatur | R3.1 Woo | R3-10 | `woocommerce-test.php` Z. 1158 |
| INV-B-036 | `MappingCheck.php:143-166` | UI | `summary()`: Mapping-Fehler und -Warnungen, bezahlte Bestellungen ohne Konto (30 Tage, aus dem Audit-Log), überbuchte Gruppen | abstract | Teile für das Betriebsdashboard | Dashboard-Kennzahlen | R1-Free (überbuchte Gruppen), R3.1 (Bestellung ohne Konto) | R1-38, R3-10 | `admin-test.php` (MappingCheck Z. 256) |

### 2.4 Gruppen, Plätze, Einladungen (`GroupService`)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-037 | `GroupService.php:23-35` | Daten | Grenzen: 20 Einladungen pro Tag, Einladungslink 30 Tage gültig, E-Mail-Aufbewahrung 30 Tage nach Ablauf; Status Gruppe `active/suspended/revoked`, Mitglied `invited/active/removed/expired` | reuse | Neutrale Seat- und Claim-Grundlage (Konzept 7); die Werte werden Einstellungen | Seat-Pool-Policy | R1-Free | R0-09, R0-27, R1-13 | `membership-test.php` Gruppen Z. 400-660 |
| INV-B-038 | `GroupService.php:61-148` | Commerce | `decide()` und `syncItem()`: Bestellposition → Gruppe; Plätze = je Stück × (Menge − erstattete Menge); Wiederaufleben nur bei automatischem Widerruf; neutraler Gruppenname aus dem Produkt | abstract | Woo-Mehrplatzkauf kommt erst in R3.1 (Konzept 7). **Free braucht eine eigene manuelle Gruppenanlage, die es in LB nicht gibt.** | Seat-Pool aus Quellposition | R3.1 Woo | R3-02, R3-03 | `membership-test.php` „bezahlt: Gruppe mit 3 Plätzen“, „Menge 2: 6 Plätze“, „Teilerstattung …“ |
| INV-B-039 | `GroupService.php:150-207` | PHP | `setStatus`, `isManual`, `releaseManual` und `applyMembers`: Gruppenstatus und Laufzeit auf die Grants aller aktiven Mitglieder übertragen; manuell entschiedene und verlängerte Zeilen bleiben unberührt (T158); `valid_until` als echtes NULL (T196) | reuse | Generische Weitergabe Pool → Seat-Grants | Seat-Grant-Propagation | R1-Free | R1-13, R1-35 | `membership-test.php` „Dispute: Gruppe und alle Plätze ausgesetzt“ und folgende |
| INV-B-040 | `GroupService.php:217-256` | PHP | `invite()`: E-Mail-Einladung reserviert den Platz unter `NamedLock group-seats:<id>`; Einladungsmail; Audit | reuse | Atomare Seat-Limits (Konzept 7) | Einladung mit Platzreservierung | R1-Free | R1-13, R1-35, R1-42 | `membership-test.php` „Einladung reserviert letzten Platz …“, „Einladung während paralleler Platzvergabe …“ |
| INV-B-041 | `GroupService.php:262-270` | PHP | `inviteBudget()`: Schutz vor Missbrauch als Mail-Relay, max(20, 2 × Plätze) pro 24 h | reuse | Sicherheit | Einladungs-Rate-Limit | R1-Free | R1-35, R0-17 | `membership-test.php` Gruppen |
| INV-B-042 | `GroupService.php:276-330` | API | `resolve`, `describeToken` (Vorschau; `other_account` und maskierte Adresse, T132), `maskEmail` und `ownerName` (nur Vor- und Nachname) | reuse | Neutrale Claim-Vorschau | Claim-Vorschau | R1-Free | R1-35 | `membership-test.php` „Beitrittslink beschreibt Gruppe“, „Vorschau mit eingeladener Adresse …“ |
| INV-B-043 | `GroupService.php:333-417` | PHP | `join()` und `joinLocked()`: Beitritt unter NamedLock; die persönliche Einladung wird mit einem bedingten UPDATE angenommen; beim Gruppenlink wird nach dem Insert neu gezählt und bei Überbuchung gelöscht; ein Doppelklick liefert die vorhandene Mitgliedschaft | reuse | Seat-Claim race-sicher (CORE-02, R1-42) | Seat-Claim | R1-Free | R1-13, R1-42 | `membership-test.php` „zwei Beitritte …“, „Gruppenbeitritt gesperrt …“, „Gruppe voll …“ |
| INV-B-044 | `GroupService.php:420-434` | PHP | `removeMember()`: Platz freigeben → Grants des Platzes widerrufen | reuse | Generisch | Seat freigeben | R1-Free | R1-35 | `membership-test.php` „Platz freigegeben …“ |
| INV-B-045 | `GroupService.php:444-469` | PHP | `releaseUser()`: Konto gelöscht → Plätze frei, Adresse weg; eigene Gruppen bleiben (Besitzer = 0) | reuse | Datenschutz und Kontinuität | Konto-Löschung bei Seat-Pools | R1-Free | R1-17 | `membership-test.php` „Mitglied löscht Konto …“, „Käufer löscht Konto …“ |
| INV-B-046 | `GroupService.php:472-504` | PHP | `trimMembers()` und `removedFields()`: Bei weniger Plätzen gehen zuerst offene Einladungen, dann die zuletzt Beigetretenen; Einladungen ohne Konto verlieren sofort ihre Adresse | reuse | Regel für Platzreduktion (→ D-11) | Seat-Reduktion | R1-Free | R1-13 | `membership-test.php` „Teilerstattung: zuletzt beigetretenes Mitglied …“ |
| INV-B-047 | `GroupService.php:507-562` | UI | `regenerateToken`, `rename`, `cleanName` (keine Links, Adressen oder Domains, T49) und `inviterName` (Personal → Marke, sonst gewählter Anzeigename) | reuse | Anti-Phishing in Einladungsmails | Pool-Verwaltung | R1-Free | R1-35 | `membership-test.php` „Gruppenname ohne Links/Adressen“, „Einladung durch Personal nennt den Shop“ |
| INV-B-048 | `GroupService.php:564-584` | PHP | `grantMember()`: Seat-Grant mit Quelle `group`, Referenz `group:<g>:<m>`; beim Wiederbeitritt wird reaktiviert | reuse | Generisch | Seat → Grant | R1-Free | R1-13 | `membership-test.php` „Platz-Zugang mit Laufzeit der Gruppe“ |
| INV-B-049 | `GroupService.php:588-633` | PHP | Abfragen `find`, `byItem`, `forOwner`, `forOrder`, `all` (Suche mit Belegung) und `membersOf` | abstract | `byItem` und `forOrder` sind Quellsichten | Seat-Pool-Repository | R1-Free | R1-02 | – |
| INV-B-050 | `GroupService.php:639-654` | PHP | `occupying()`, `inviteCutoff()` und `inviteExpiresAt()`: Ein Platz ist belegt bei aktiv oder einer Einladung, die weniger als 30 Tage alt ist | reuse | Eine einzige Belegungsregel (auch in `MappingCheck`) | Belegungsregel | R1-Free | R1-13 | `membership-test.php` „abgelaufene Einladung belegt keinen Platz“ |
| INV-B-051 | `GroupService.php:665-684` | Cron | `expireInvites()`: Einladungen auf `expired`, Adresse 30 Tage nach Ablauf löschen (Aufruf `Plugin.php:317`) | reuse | Datenschutzvorgabe aus Konzept 7 | Einladungs-Ablaufjob | R1-Free | R1-17 | `membership-test.php` „Cron: Einladung auf ‚expired‘ …“, „Cron: Adresse 30 Tage nach Ablauf gelöscht“ |
| INV-B-052 | `GroupService.php:687-709` | UI | `inviteMail()`: Text nach Art. 14 DSGVO (wer lädt ein, wie lange, Datenschutzerklärung) | reuse | Text neutralisieren | Mail-Vorlage Einladung | R1-Free | R1-21 | `membership-test.php` „Einladungsmail: Hinweis zum Weitergeben (T132)“ |
| INV-B-053 | `GroupService.php:711-753` | PHP | `freeSeats`, `scopes` (JSON), `reference`, `joinUrl` (Router `gruppe/<token>`) und `resolveToken` (Format `[gi][0-9a-f]{32}`) | abstract | Die Route wird ersetzt (`cc_*`) | Token-Auflösung | R1-Free | R1-35 | `membership-test.php` „kaputter Einladungslink …“ |
| INV-B-054 | `GroupService.php:756-764` | PHP | `managed()`: Verwalten dürfen Besitzer oder `manage_bp_entitlements` | abstract | Muss in die RBAC- und IDOR-Matrix | Policy-Check | R1-Free | R0-07, R1-03 | `membership-test.php` „nur Besitzer verwaltet die Gruppe“, „Betreiber kann die Gruppe weiter verwalten“ |

### 2.5 Grant-Vertrag für Gutscheine (`VoucherGrants`)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-055 | `VoucherGrants.php:34-64` | API | Öffentlicher PHP-Vertrag v1 über `apply_filters('bootspruefung_voucher_grants_v1')`, `CONTRACT_VERSION 1.0.0`, `KEY_PATTERN`, Referenz `voucher:<key>` | abstract | Versionierte Connector-Schnittstelle (R3-01) | Course-Captain-Grant-API v1 | R3.2 Voucher (Vertrag), Kern R1 | R3-01, R3-04, R0-19 | `voucher-grant-test.php` Z. 80 |
| INV-B-056 | `VoucherGrants.php:71-115` | API | `target()`: Prüft das Ziel-Woo-Produkt (vorhanden, veröffentlicht oder privat, Zuordnung aktiv, keine Gruppe, kein Upgrade, Kurs veröffentlicht); Fehlercodes `lb_*` mit HTTP-Status | abstract | Gutscheinziel = Woo-Produkt. Konzept 32.1 verlangt einen Voucher-Kanal ohne Woo-Pflicht → D-3 | Ziel = Zugangsangebot (Kurs, Scopes, Laufzeit) | R3.2 Voucher | R3-04 | `voucher-grant-test.php` „Zielprüfung“ Z. 114-125 |
| INV-B-057 | `VoucherGrants.php:123-154` | API | `check()`: Vorschau je Bereich (`existing` permanent/until, `starts_at`, `ends_at`); `lb_access_exists`, wenn alles schon dauerhaft freigeschaltet ist | reuse | Generische Vorschau | Grant-Preview | R3.2 Voucher (Kern R1) | R3-04 | `voucher-grant-test.php` Z. 170-184 |
| INV-B-058 | `VoucherGrants.php:164-229` | API | `grant()`: idempotent über `redemption_key` unter `Lock bp_voucher_grant_<md5>` (30 s); `lb_idempotency_conflict` bei einem anderen Nutzer oder Produkt; Audit; Aktion `bootspruefung_voucher_granted` | reuse | Abgedeckt sind CORE-03 und CORE-08 | Grant per Fremdschlüssel | R3.2 Voucher | R3-04, R3-05 | `voucher-grant-test.php` „Idempotenz“ Z. 142-168 (mit Parallel-Test) |
| INV-B-059 | `VoucherGrants.php:232-275` | API | `get()`, `revoke()` (`lb_grant_not_found`, Aktion `bootspruefung_voucher_revoked`) und `result()` | reuse | Support und Wiederholung | Grant-Lookup und -Widerruf | R3.2 Voucher | R3-04 | `voucher-grant-test.php` Z. 186-204 |
| INV-B-060 | `VoucherGrants.php:278-300` | PHP | `existing()` (dauerhaft, spätestes Ende); `error()` = WP_Error mit `status` und `retryable` | reuse | Fehlerkonvention (vorübergehend oder endgültig) | Fehlerkontrakt | R3.2 Voucher | R3-01, R3-12 | `voucher-grant-test.php` |

### 2.6 Zugangsstatus, Beschriftungen, Kursbeziehungen

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-061 | `AccessStatus.php:21-66` | UI | `forUser()` und `status()`: Anzeigestatus `active`, `pending`, `suspended`, `expired`, `limited` und `not_granted` | abstract | Statussprache aus Konzept 31.2; `limited` = kostenloser Teilumfang | Zugangsstatus-Presenter | R1-Free | R1-16 | `membership-test.php` „Zugangsstatus …“ Z. 1034 |
| INV-B-062 | `AccessStatus.php:39-48, 75-111` | Commerce | `orderAwaitsPayment`, `details()` (Bestellung, Laufzeit, `paid` bei `consent_missing`) und `order()` | abstract | Woo-Details | Statusdetails vom Adapter | R3.1 Woo | R3-13 | `membership-test.php` Z. 1034 |
| INV-B-063 | `AccessStatus.php:119-169` | Commerce | `renewUrl()` und `purchasedProductUrl()`: „Verlängern“ führt zur gekauften Stufe (Theorie + Video → Komplett), keine Gruppenpakete | abstract | Der Verlängern-Link ist generisch. Die Stufenlogik ist katalogspezifisch. | Verlängerungsangebot vom Adapter | R3.1 Woo | R3-13 | `membership-test.php` Z. 1034 |
| INV-B-064 | `AccessStatus.php:172-177` | UI | `contactUrl()`: Kontaktseite bei Sperre | reuse | Generisch | Supportweg (Konzept 31.2) | R1-Free | R1-16 | – |
| INV-B-065 | `Labels.php:17-33` | UI | `feature()`: fachliche Bereichsnamen (Navigationstrainer, Funktraining, Gerätesimulation …) | replace | Fachspezifisch | Neutrale Scope-Labels | R1-Free | R0-08 | – |
| INV-B-066 | `Labels.php:35-101` | UI | `status()` (mit `consent_missing`), `course()`, `courses()`, `duration()` (12/6/3 Monate), `validity()` und `date()` | reuse | Statussprache, i18n | Label-Service | R1-Free | R1-16, R1-19 | `membership-test.php` „Katalogtexte übersetzbar …“ Z. 695 |
| INV-B-067 | `CourseRelations.php:24-39` | Daten | Tabelle besessener Kurs → `hide`/`recommend` (SBF, SRC, UBI, FKN …) | exclude_domain_specific | Fachkurse | – | exclude | – | – |
| INV-B-068 | `CourseRelations.php:42-129` | PHP | `rules()` (Filter `bootspruefung_course_relations`), `ownedCourses()`, `evaluate()` und `forUser()`: Kurse ausblenden oder empfehlen | abstract | Generische Beziehungen „enthält“ und „empfiehlt“ verhindern Doppelkäufe → D-8 | Kursbeziehungen (`contains`, `recommends`) | R2 | – (keine passende ID) | `membership-test.php` „Kursempfehlungen und Ausblenden“ Z. 887; `mobile-api-test.php` Z. 336 |

### 2.7 Ablauf-Erinnerung und Widerspruch

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-069 | `ExpiryReminder.php:49-158` | PHP | `enabled`, `days` (1–90, Standard 14), `expiring()`: Zugänge, die bald enden, ohne späteren oder unbefristeten Zugang; nicht bei „Zugang ohne Shop“; `collapse()` über CourseRelations; `freeCourses` | reuse | Generische Erinnerung. Laut Konzept R3-15 ist sie Pro. | Ablauf-Erinnerung | R1-Pro | R1-21, R3-15 | `membership-test.php` „Ablauf-Erinnerung“ Z. 946 |
| INV-B-070 | `ExpiryReminder.php:60-65, 195-296` | Cron | `schedule` (täglich `bp_learning_daily`), `run`, `resume` (`bp_learning_expiry_reminder_continue`), `process`: Lock (15 min), Stapel 50, Zeitbudget 20 s, Fortsetzung nach 60 s, Fehler pro Empfänger | reuse | Robustes Batch-Job-Muster (T80, T124) | Batch-Mailjob | R1-Pro | R1-21, R1-22 | `membership-test.php` „Stapel, Zeitbudget und Fortsetzung“ Z. 1175; `operations-test.php` Z. 502 |
| INV-B-071 | `ExpiryReminder.php:302-348, 396-414` | PHP | `remind()`: Merker vor dem Versand setzen, bei Fehlschlag zurücknehmen; `List-Unsubscribe`; Merker je Kurs\|Ende (User-Meta) | reuse | Kein Doppelversand | Versandmerker | R1-Pro | R1-21 | `operations-test.php` Z. 502 |
| INV-B-072 | `ExpiryReminder.php:358-383` | Commerce | `boughtCourses()`: Erinnerung nur an Käufer einer eigenen, bezahlten Woo-Bestellung über 0 € (§ 7 Abs. 3 UWG, T44) | abstract | Rechtsregel, ihre Daten kommen von Woo → D-9 | Empfängerfilter „zahlende Käufer“ | R3.1 Woo (Filter), R1-Pro (Hook) | R1-21, R3-15 | `membership-test.php` Z. 946 |
| INV-B-073 | `ExpiryReminder.php:166-179, 386-394, 423-471` | UI | `renewUrl`, `date`, `collectionNotice` (Hinweis bei der Erhebung der Adresse), `mail()` (sachlich, mit Widerspruchshinweis) und `forget()` (Datenschutz) | reuse | Texte neutralisieren | Mail-Vorlage, Privacy-Hook | R1-Pro | R1-21, R1-17 | `membership-test.php` „Widerspruch und Abmeldelink“ Z. 1087 |
| INV-B-074 | `ReminderOptOut.php:50-90` | PHP | `optedOut`, `set` (Audit), `signature` (HMAC, an Konto und Registrierungszeit gebunden, ohne Ablauf), `verify`, `url` und `isRequest` | reuse | Abmeldung ohne Login | Signierter Abmeldelink | R1-Pro | R1-21, R0-31 | `membership-test.php` Z. 1087 |
| INV-B-075 | `ReminderOptOut.php:93-144` | UI | `handle()`: One-Click nach RFC 8058 (T155); Bestätigungsseite mit Knopf (gegen Link-Scanner); Schalter im Konto mit Nonce; PRG-Weiterleitung | reuse | Generisch | Abmelde-Endpunkt | R1-Pro | R1-21 | `membership-test.php` Z. 1087 |
| INV-B-076 | `ReminderOptOut.php:147-186` | UI | `page()` und `renderAccountSection()` | reuse | UI | Abmeldeseite, Kontoeinstellung | R1-Pro | R1-21 | `membership-test.php` Z. 1087 |
| INV-B-077 | `ReminderOptOut.php:31-48` | UI | `register()`: Hinweis an der Woo-Kasse (klassisch und Block), bei der Woo-Registrierung und bei `register_form` | abstract | Die WordPress-Registrierung gehört in R1, die Woo-Kasse ins Woo-Paket | Hinweis bei der Erhebung | R1-Pro (WP), R3.1 Woo (Kasse) | R1-21 | `account-test.php` (ReminderOptOut Z. 343) |

### 2.8 Geld-zurück-Garantie

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-078 | `MoneyBack.php:95-138` | Commerce | `policy`, `strictestPolicy` (T237), `setPolicy` und `percentLabel`: Produkt-Meta `{days, max_progress}` | abstract | Betreiber-Marketingregel, kein Lernkern → D-4 | Erstattungsberechtigung nach Nutzungsgrad | R3.4 sonstige | – (keine ID) | `woocommerce-test.php` „Shop-Katalog und Geld-zurück-Garantie“ Z. 1171; `admin-test.php` Z. 443 |
| INV-B-079 | `MoneyBack.php:147-191` | PHP | `progress()`: Nutzungsgrad seit Zahlung (Lernfragen je Frage einmal plus abgeschlossene Lektionen; Prüfungssimulationen zählen nicht, T43); `limitReached()` ungerundet | abstract | Der Nutzungsgrad je Kurs ist auch neutral nutzbar | `CourseUsageMeter` | R3.4 sonstige | – | `woocommerce-test.php` Z. 1171, Z. 1757 |
| INV-B-080 | `MoneyBack.php:200-288` | Commerce | `status()`: Zustand der Garantie mit Gründen `voucher`, `refunded`, `no_policy`, `not_paid`, `expired`, `requested`, `used_before`, `downloaded`, `too_much_progress`, `eligible` | abstract | Wie INV-B-078 | Garantie-Statusautomat | R3.4 sonstige | – | `woocommerce-test.php` Z. 1171, 1246, 1757 |
| INV-B-081 | `MoneyBack.php:39-92` | PHP | `usedCourses` und `markUsed` (einmal je Kurs), `recordDownload` und `downloadedSince` (ein PDF-Download beendet die Garantie, T234) | abstract | Wie INV-B-078 | – | R3.4 sonstige | – | `woocommerce-test.php` „einmal je Kurs, PDF-Download …“ Z. 1246 |
| INV-B-082 | `MoneyBack.php:291-331` | PHP | `frozenUsage` (Stand vor einer Datenlöschung festschreiben), `usageExplanation`, `reasonLabel` und `recordRequest` (Audit) | abstract | Datenschutz-Interaktion beachten | – | R3.4 sonstige | R1-17 (Interaktion) | `account-test.php` „Geld-zurück-Anfrage nur einmal (QA-09)“ Z. 107 |
| INV-B-083 | `MappingCheck.php:81-111` | UI | `moneyBackIssues()`: Garantie mit Grenze 0 % (T107) | abstract | Gehört zu INV-B-078 | – | R3.4 sonstige | – | `woocommerce-test.php` Z. 1158 |
| INV-B-084 | `WooCommerce/MoneyBackWoo.php:15-97` | UI | `register` und Hinweise auf Produktseite, im Warenkorb und im Block: Bei einem Wiederholungskauf gilt keine Garantie (T234); Hook `bootspruefung_before_erase_user` | abstract | Wie INV-B-078 | – | R3.4 sonstige | – | `woocommerce-test.php` Z. 1246 |
| INV-B-085 | `MoneyBackWoo.php:100-173` | Commerce | `snapshot()` (Positionserstattung oder nur Betrag → ganze Bestellung), `activeForCourse` (Hinweis vor PDF-Download) und `status` | abstract | Wie INV-B-078 | – | R3.4 sonstige | – | `woocommerce-test.php` Z. 1757 |
| INV-B-086 | `MoneyBackWoo.php:181-206` | PHP | `freezeUsage()` vor dem Löschen der Lerndaten (QA-06) | abstract | Datenschutz-Interaktion | – | R3.4 sonstige | R1-17 | `account-test.php` Z. 300 |
| INV-B-087 | `MoneyBackWoo.php:218-277` | Commerce | `breakdown()`: Erstattungsbetrag der Garantie-Positionen; per Gutschein bezahlter Anteil (`_bp_paid_by_voucher`) anteilig; Filter `bootspruefung_money_back_cash_amount` | abstract | Woo und Voucher berührt (Doppelquelle, R0-40) | – | R3.4 sonstige | R0-40 | `woocommerce-test.php` Z. 1171 |
| INV-B-088 | `MoneyBackWoo.php:290-348, 492-528` | UI | `renderAccountSection` (Tabelle in „Mein Lernen“) und `adminOrder` (Anzeige in der Bestellung) | abstract | Wie INV-B-078 | – | R3.4 sonstige | – | `woocommerce-test.php` Z. 1171 |
| INV-B-089 | `MoneyBackWoo.php:350-490` | Commerce | `handleRequest`, `submitRequest` (genau einmal je Bestellung unter `NamedLock money_back:<id>`, Anfrage frisch aus der DB, HPOS/CPT), `record` (Notiz, Mails an Betreiber und Kunde, Aktion `bootspruefung_money_back_requested`) und `flash` (Transient) | abstract | Das Muster „einmalige Anfrage unter Lock“ ist generisch | – | R3.4 sonstige | R1-42 (Muster) | `account-test.php` Z. 107 |

### 2.9 Shop-Katalog (überwiegend fachlich)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-090 | `ShopCatalog.php:21-75, 192-298` | Daten | Feste Produktliste (SBF, Funk, Ergänzungen, Kapitäns-Bundle, Pässe, 17 Gruppenpakete, Simulator-Pässe, Video-Stufen `-KOMPLETT`/`-VIDEO`) mit Preisen; `find`, `tierBase`, `komplettSku`, `videoSku` und `videoUpgradeSkus` | exclude_domain_specific | Fachprodukte und Preise (Konzept 26.2) | – | exclude | – | – |
| INV-B-091 | `ShopCatalog.php:44, 119-189` | UI | `products()`, `ownPrices()` und `saveOwnPrices()` (Option `bp_learning_prices`, Eingabe mit Tausenderpunkt) | replace | Preise verwaltet WooCommerce (Konzept 32.1) | WooCommerce-Produktpreis | exclude | – | `admin-test.php` „Preise“ Z. 179 |
| INV-B-092 | `ShopCatalog.php:95-117` | Daten | `DISABLED_COURSES`, `disabledCourses` und `disabledSkus` (FKN → SKN) | exclude_domain_specific | Fachlich | – | exclude | – | – |
| INV-B-093 | `ShopCatalog.php:300-723` | Import | `applyProducts()`: Produkte anlegen oder abgleichen mit Fingerprint je Feld; Handänderungen bleiben (T98); Dry-run; Entwürfe zurückstellen (`heldBack`, `trackManualStatus`); `priceChangedByHand`, `saleConflicts`, `existingCount`, `accessKey`, `policyKey` und `fingerprint` | abstract | Der Mechanismus „Shopobjekte aus Kursen erzeugen, Drift erkennen“ ist generisch. Der Inhalt ist fachlich → D-6 | Optional: Produkt für Kurs anlegen | R3.1 Woo (nur nach D-6) | R3-02 | `woocommerce-test.php` Z. 1171; CLI `wp bootspruefung shop setup` (`Cli/Commands.php:27`) |
| INV-B-094 | `ShopCatalog.php:725-786` | Daten | Kategorien und Slugs (`sbf`, `funk`, `seenot` …) und Produktbilder aus `assets/products/` | exclude_domain_specific | Fachlich | – | exclude | – | – |
| INV-B-095 | `ShopCatalog.php:789-856` | PHP | `courseStats`, `flushCourseStats` und `countCourseStats`: Kennzahlen je Kursmenge (Fragen, Navigation, Praxis, Videos, Prüfungsbögen); Request-Memo plus Transient mit Paketstempel | abstract | Kennzahlen je Kurs mit Cache sind generisch. `nav` und `praxis` sind fachlich. | Kurskennzahlen-Cache | R1-Free | R1-16, R1-25 | `woocommerce-test.php` „Kennzahlen der Kurse zwischengespeichert“ Z. 470 |
| INV-B-096 | `ShopCatalog.php:858-1109` | UI | `texts`, `guaranteeParagraph`, `videoUpgradeTexts`, `simTexts`, `simBenefits`, `simStats` und `termLabel`: Produkttexte aus Fachinhalten | exclude_domain_specific | Fachtexte (Konzept 26.2) | – | exclude | – | `membership-test.php` Z. 695 |
| INV-B-097 | `ShopCatalog.php:991-1002` | UI | `priceText()`: Betrag im Shop-Format (oder `number_format_i18n`) | abstract | Woo-Formatierung | Preisformatierung im Adapter | R3.1 Woo | R1-19 | `membership-test.php` Z. 695 |
| INV-B-098 | `ShopCatalog.php:80-88, 1116-1148` | Daten | `FREE_TIER` und `applyFreeTier()`: Schnupper-Paket als `free_limited`-Regeln in `bp_access_rule` (10 Fragen je Modul, 1 Bogen, 1 Lektion …) | abstract | Ein kostenloser Teilumfang je Kurs ist generisch. Die Feature-Werte sind fachlich. | Vorschau- und Teilumfangsregeln je Kurs | R1-Free | R1-13, R1-14 | `membership-test.php` „Teilumfang kostenlos“ Z. 133, „Schnupper-Paket …“ Z. 661 |
| INV-B-099 | `ShopCatalog.php:77-78` | Daten | `MONEY_BACK_DAYS = 14` und `MONEY_BACK_MAX_PROGRESS = 20` | abstract | Gehört zu INV-B-078 | – | R3.4 sonstige | – | – |

### 2.10 Zahlungskonflikte (`Disputes/DisputeWebhooks`)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-100 | `DisputeWebhooks.php:671-774` | Commerce | `handle()`: gateway-neutral; Doppel-Erkennung (Transient 90 Tage und Order-Meta mit 100 Ereignissen); Bestellung über Transaktions-ID (nur exakte Treffer); `Lock bp_dispute_lock_<order>` (60 s, sonst `retry` → 409); Fallstand je Case; opened → suspend, won → release (sofern kein anderer Fall offen ist, T235), lost → revoke; Bestellnotiz und Audit | abstract | Chargeback → Grant suspend/revoke ist ein Pflichtfall in R3-03. Die Doppel-Erkennung entspricht R3-12. | Dispute-Ereignisverarbeitung im Woo-Adapter | R3.1 Woo | R3-03, R3-12, R3-19 | `woocommerce-test.php` „Disputes per Webhook“ Z. 641 |
| INV-B-101 | `DisputeWebhooks.php:782-816` | PHP | `blockingCases()` und `decide()`: Ein abgeschlossener Fall ist endgültig, zwischen Ergebnissen gilt das jüngere, sonst `apply/ignore/noop` | reuse | Out-of-order-Regel (Konzept 35.3) | Fall-Zustandsautomat | R3.1 Woo | R3-03 | `woocommerce-test.php` „Reihenfolge nach Ereigniszeit …“ Z. 724 |
| INV-B-102 | `DisputeWebhooks.php:515-576` | API | `generic()`: HMAC-SHA256-Webhook `X-Bootspruefung-Signature`; optional Pflichtfelder `dispute_id` und `timestamp`; Zeitfenster −60 Tage/+1 Tag (T134); Hash über normalisiertes JSON als Event-ID; `freshEnough`, `normalize` und `eventTime` | reuse | Generischer signierter Webhook (R3-12) | Signierter Dispute-Webhook | R3.1 Woo | R3-12 | `woocommerce-test.php` Z. 641 |
| INV-B-103 | `DisputeWebhooks.php:252-511` | API | PayPal-Webhook: `prefilterPaypal` (10 pro Minute und IP, Methode, JSON, 256 KB, Kopfzeilen, `cert_url` auf `*.paypal.com`), `verifyPaypal` (API `verify-webhook-signature`, 60 pro Minute insgesamt, Rohtext unverändert), `paypalToken` (OAuth, Transient), `paypalAction` und `rememberVerify` (gedrosselt, T125) | replace | Gateway-spezifisch; das Zahlungssystem gehört Woo und dem Gateway-Plugin (Konzept 32.1) → D-5 | Woo-Statusereignis bzw. INV-B-102 | R3.1 Woo (nur nach D-5) | R3-03 | `woocommerce-test.php` „PayPal: echte Signaturprüfung“ Z. 828, „Budget je IP“ Z. 988 |
| INV-B-104 | `DisputeWebhooks.php:578-661` | UI | Klarna von Hand: Bestellaktionen „Klarna-Konflikt melden“ mit Fall-ID, gleicher Weg wie `handle()` | replace | Gateway-spezifisch; generisch wäre „Dispute manuell melden“ | Manuelle Dispute-Aktion | R3.1 Woo | R3-03 | `woocommerce-test.php` „Klarna-Konflikt von Hand melden“ Z. 1026 |
| INV-B-105 | `DisputeWebhooks.php:54-248, 389-398, 818-887` | UI | Einstellungen (`bp_learning_disputes`, Konstanten in `wp-config.php` haben Vorrang, Secret wird nie angezeigt), `paypalStatus`, `renderStatus`, `renderSettings`, `saveSettings` und `logRejected` (Audit höchstens einmal je 10 Minuten) | abstract | Admin und Diagnose; Secrets in Konstanten sind ein gutes Muster | Connector-Settings und -Status | R3.1 Woo | R3-14, R3-16 | `woocommerce-test.php` Z. 828 |

### 2.11 WooCommerce-Adapter (`WooCommerce/`)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-106 | `Integration.php:35-83` | Commerce | `active`, `sync` (Singleton OrderSync), `syncOrder` und `orderStateKey`: Merker je Anfrage über Änderungszeit, Status, Zahlung, Kunde, Erstattung, Positionen und Zustimmung (T243) | abstract | Woo-Adapter-Kern | Woo-Adapter-Bootstrap | R3.1 Woo | R3-02 | `woocommerce-test.php` Z. 275 |
| INV-B-107 | `Integration.php:85-133` | Commerce | Bestellereignisse: `order_status_changed`, `payment_complete` (je Anfrage einmal), `order_refunded` und `refund_deleted` (immer), `process_shop_order_meta`; Bestellung gelöscht (CPT und HPOS) → `revokeOrder` | abstract | Ereignisquelle des Adapters | Woo-Event-Bindings | R3.1 Woo | R3-02, R3-03 | `woocommerce-test.php` Z. 275, Z. 299 |
| INV-B-108 | `Integration.php:136-142` | API | Öffentliche Aktionen `bootspruefung_suspend_order` und `bootspruefung_reactivate_order` (für Gateways) | abstract | Versionierte Connector-Commands (R3-01) | Command `grant.suspend` / `grant.resume` | R3.1 Woo | R3-01 | `woocommerce-test.php` „Gateway-Hooks und Bestellaktionen“ Z. 326 |
| INV-B-109 | `Integration.php:145-304` | Commerce | Kontopflicht im Checkout: `soldIndividually` (außer Gruppenprodukte), `registrationRequired`, Gast-Optionen per `option_*`-Filter, `blocksAccountGuard` (Store API), `blockCheckoutForGuests` (Anmeldeformular und Übersicht) und `guestCartSummary` | abstract | Sichere Identität vor dem Grant (CORE-01). Konzept 35.1 sieht stattdessen einen Claim-Flow vor → D-2 | Gastkauf-Policy im Adapter | R3.1 Woo | R3-02, R3-13 | `woocommerce-test.php` „Checkout“ Z. 385, „Storno, Gast …“ Z. 299; `e2e/checkout-flow.mjs` |
| INV-B-110 | `Integration.php:306-348` | PHP | `cartHasLearning`, `isGroupProduct` und `productHasLearning` (Variationen, ShopCache) | abstract | Adapter-Helfer | – | R3.1 Woo | R3-02 | `woocommerce-test.php` Z. 406 |
| INV-B-111 | `Integration.php:356-455` | API | `offers()`: Kaufangebote je gesperrtem Bereich, höchstens 6, nur veröffentlicht und sichtbar, Rang Kurs vor Bundle, günstigstes zuerst; Filter `bootspruefung_shop_offer_url`; `primeProducts`; `offerRank` über Katalog-Gruppen | abstract | Der Upsell-Mechanismus ist generisch. `offerRank` ist katalogspezifisch. | `AccessOfferProvider` (Woo) | R3.1 Woo | R3-13 | `woocommerce-test.php` Z. 349 |
| INV-B-112 | `Integration.php:457-569` | UI | „Mein Konto › Mein Lernen“ (Endpunkt `mein-lernen`): Tabelle Kurs, Umfang, Status und „gültig bis“; Simulatorzeilen zusammengefasst; Status mit Link zur Bestellung bzw. „Verlängern“; Abschnitte Erinnerung, Garantie und Widerruf | abstract | Konzept 7: „Mein Konto → Meine Kurse“; die Simulator-Sonderfälle sind fachlich | Woo-Kontoseite „Meine Kurse“ | R3.1 Woo | R3-13 | `woocommerce-test.php` „Kundenansichten“ Z. 349 |
| INV-B-113 | `Integration.php:571-653` | UI | `thankYou`, `email` und `accessNotice`: Zugangshinweis auf der Danke-Seite und in der Mail (erst abgleichen, dann melden); Erstattungsmail; Hinweis auf die Gruppenlizenz | abstract | Kaufbestätigung ohne Medienbruch (R3-13) | Zugangshinweis im Woo-Kauf | R3.1 Woo | R3-13 | `woocommerce-test.php` Z. 349 |
| INV-B-114 | `Integration.php:659-732` | Commerce | `awaitsConsent`, `recentOrders` (T241), `consentMissingNotice` und `consentMissingOrders`: Bezahlte Bestellungen ohne Zustimmung nach § 356 Abs. 5 BGB warten auf Freischaltung | abstract | Rechtsregel DE/EU → D-5 | Aktivierungs-Gate „Zustimmung“ (optional) | R3.1 Woo | R3-02 | `woocommerce-test.php` Z. 736 |
| INV-B-115 | `Integration.php:739-789` | Commerce | `amountOnlyRefund()`: Eine Erstattung nur als Betrag lässt den Zugang bestehen (Hinweis); bei einer Garantie-Anfrage werden die Garantie-Positionen beendet (T233) | abstract | Teilrefund-Semantik (Konzept 35.3) | Regel für Erstattung ohne Positionsbezug | R3.1 Woo | R3-03 | `woocommerce-test.php` „Erstattung nur als Betrag“ Z. 1269 |
| INV-B-116 | `Integration.php:793-880` | UI | Bestell-Admin: `itemMeta` (Zugänge je Position, Link „verwalten“), `orderActions` (abgleichen, vor Zahlung freischalten, aussetzen, aufheben) mit Pflichtbegründung, `orderNote` (Support-Notiz ohne Zahlungsdaten) | abstract | Admin-Recovery (R3-10) | Woo-Bestellaktionen | R3.1 Woo | R3-10 | `woocommerce-test.php` Z. 326, „Admin und Abgleich“ Z. 570 |
| INV-B-117 | `WooOrderSource.php:19-79` | Commerce | `snapshot()`: nur über WC_Order/CRUD (HPOS-fähig); Beträge brutto inkl. Steuer-Erstattung; `unallocatedRefund`; `consentState()` (Filter `bootspruefung_require_digital_consent`) | reuse | Adapterimplementierung von INV-B-008 | `WooSourceAdapter::snapshot` | R3.1 Woo | R3-02 | `woocommerce-test.php` Z. 275 |
| INV-B-118 | `WooOrderSource.php:81-101` | Commerce | `orderIds()`: Bestellungen seitenweise, Positionen prüfen (kein Produktfilter in der Order-Query) | reuse | Backfill | `WooSourceAdapter::list` | R3.1 Woo | R3-03 | `woocommerce-test.php` „Admin und Abgleich“ Z. 570 |
| INV-B-119 | `Compatibility.php:13-23` | PHP | Deklariert HPOS (`custom_order_tables`) und `cart_checkout_blocks` | reuse | Pflicht nach Konzept 7 | Woo-Feature-Deklaration | R3.1 Woo | R3-02, R3-11 | `woocommerce-test.php` „Kompatibilität“ Z. 61 |
| INV-B-120 | `AutoReconcile.php:22-229` | Cron | Automatischer Abgleich stündlich und nach Aktivierung: Cursor (Änderungszeit, ID), höchstens 100 je Lauf, 5 min Karenz, Start 48 h bzw. ab Deaktivierung, Retry-Liste (5 Versuche, T47), Lock 30 min, Optionen für letzten Lauf und Deaktivierung | reuse | Verlorene Hooks (Konzept 7, 35.2) | Reconcile-Scheduler je Adapter | R3.1 Woo | R3-03, R3-10 | `woocommerce-test.php` „Automatischer Abgleich …“ Z. 1080; `operations-test.php` Z. 631 |
| INV-B-121 | `ProductPanel.php:19-62, 197-274` | UI | Produktreiter „Lernzugang“: Matrix Kurse × Funktionen, Laufzeit (vorbelegt, „ohne Ablauf“ als Kästchen), Plätze je Stück, Verlängerung, Erstattung; Variationen erben oder überschreiben | abstract | Mapping-UI (R3-02); die Funktionsmatrix ist fachlich (→ D-10) | Woo-Produktfeld „Kurszugang“ | R3.1 Woo | R3-02, R3-14 | `woocommerce-test.php` „Produktfeld ‚Lernzugang‘“ Z. 68 |
| INV-B-122 | `ProductPanel.php:276-358` | Commerce | `save`, `saveVariation`, `persist` (Audit `product_access_saved`), `duration()` (T101) und `defaultDays` | abstract | Wie INV-B-121 | – | R3.1 Woo | R3-02 | `woocommerce-test.php` Z. 68; `admin-test.php` Z. 402 |
| INV-B-123 | `ProductPanel.php:64-195` | UI | Felder für die Geld-zurück-Garantie, Warnung bei Abweichung von den AGB oder bei 0 % (T107) | abstract | Gehört zu INV-B-078 | – | R3.4 sonstige | – | `admin-test.php` „Geld-zurück-Garantie am Produkt“ Z. 443 |
| INV-B-124 | `ShopCache.php:20-161` | PHP | Request-Cache für Zuordnungen, Mitgliedskurse und Warenkorb; wird über den Filter `query` bei Schreibzugriffen auf `bp_product_access` bzw. `bp_entitlement` und bei `set_current_user` verworfen (T85) | abstract | Das Invalidierungsprinzip ist generisch (R1-25). Die SQL-String-Erkennung ist fragil. | Request-Cache mit Domain-Events | R1-Free (Prinzip), R3.1 | R1-25, R0-21 | `woocommerce-test.php` „Zwischenspeicher Shop …“ Z. 406 |
| INV-B-125 | `CartDuplicateHint.php:24-201` | Commerce | `evaluate` und `overlap` ohne DB: Kurs schon bestellt, besessen, unbefristet, Verlängerung bald oder abgelaufen, gesperrt, Überschneidung bei Paketen; neues Ende bei `extend`; `forUser`; `term` | abstract | Doppelkaufschutz ist generisch. Stufen- und Videohinweise sind katalogspezifisch. | Hinweis „bereits Zugang / Verlängerung“ | R3.1 Woo | R3-13 | `woocommerce-test.php` „Hinweis … Doppelkauf (QA-03/QA-17)“ Z. 1429 |
| INV-B-126 | `CartDuplicateHint.php:204-365` | UI | `message`, `videoLostText`, `box`, `blockBox` und `classicBox` (Block und klassisch, nur angemeldet; Filter `bootspruefung_cart_duplicate_hint`) | abstract | Wie INV-B-125 | – | R3.1 Woo | R3-13 | `woocommerce-test.php` Z. 1429 |
| INV-B-127 | `CartRelationHint.php:22-366` | Commerce | Hinweis „Kurs schon abgedeckt“ mit günstigerer Alternative; Tausch-Knopf (Nonce, Ziel neu ermittelt); Filter `bootspruefung_cart_relation_hint`; Nutzung von `Gate\SectionFiles::PARTS` | abstract | Hängt von INV-B-068 ab; Tabelle und Artikel („die Ergänzung …“) sind fachlich | Cross-Sell-Hinweis mit Tausch | R3.1 Woo (nach D-8) | R3-13 | `woocommerce-test.php` „Hinweis im Warenkorb: Kurs durch besessenen Kurs abgedeckt“ Z. 1371 |
| INV-B-128 | `ContractSummary.php:19-94, 140-174` | UI | Laufzeit je Position in den Bestellmails („6 Monate, bis …“ bzw. „ab Zahlungseingang“), kein Abo (T52) | abstract | Konzept 7 verlangt „transparente Dauer“ | Laufzeit-Zeile in der Bestellmail | R3.1 Woo | R3-13 | `woocommerce-test.php` „Vertragsbestätigung …“ Z. 249 |
| INV-B-129 | `ContractSummary.php:100-138` | UI | `guarantor()` (Filter `bootspruefung_guarantor`), `guaranteeText()` (§ 479 BGB) und `statutoryRights()` | abstract | Gehört zu INV-B-078 | – | R3.4 sonstige | – | `woocommerce-test.php` Z. 249 |
| INV-B-130 | `MemberDiscounts.php:19-181` | Commerce | Mitgliederrabatt: Regeln (Kurs, Prozent, Geltung andere/Lern/alle) in der Option; der beste Rabatt im Warenkorb, Preis frisch gelesen (kein Doppelrabatt); Preisanzeige; Meta an der Position; reine Upgrades zählen nicht | abstract | Mitgliedschaftsvorteil, kein Lernkern → D-8 | Rabatt für aktive Lernende | R3.4 sonstige | – | `woocommerce-test.php` „Mitgliederrabatte“ Z. 588; `extensions-test.php` |
| INV-B-131 | `DigitalConsent.php:22-498` | Commerce | Zustimmung zum vorzeitigen Beginn (§ 356 Abs. 5 BGB): Pflicht-Kästchen in Block (Additional Checkout Field mit Regel aus der Store-API-Erweiterung), klassischer Kasse und „Bestellung bezahlen“ (zunächst vorläufig, endgültig mit der Zahlung); Meta mit Zeit, Text, Quelle und Konto-Abgleich; Admin-Anzeige; Bestätigung in der Mail und Nachweis des Versands (T149) | abstract | Rechtspflicht im Verkauf digitaler Inhalte (EU), Grant-Gate → D-5 | Zustimmungs-Gate im Woo-Adapter | R3.1 Woo (nach D-5) | R3-02 | `woocommerce-test.php` Z. 736 und „Zustimmung digitale Inhalte …“ Z. 1665 |
| INV-B-132 | `WithdrawalFunction.php:29-267, 369-533` | UI | Widerrufsbutton nach § 356a BGB über die Woo-Funktion „Order withdrawal“ (Woo ≥ 11.1): feste Adresse `/vertrag-widerrufen/`, einmal automatisch einschalten, Frist 14 Tage, Knopf in „Mein Lernen“ und der Bestellansicht, Formular vorbefüllen, Admin-Hinweis, Altdaten `_bp_withdrawal`, Shortcode `bootspruefung_vertrag_widerrufen` | replace | Gehört Woo bzw. den Rechtstext-Plugins | WooCommerce-Kernfunktion | exclude | – | `account-test.php`; `woocommerce-test.php` (Shop-Abschnitte) |
| INV-B-133 | `WithdrawalFunction.php:287-366` | Daten | Woo-Datenexport und -löschung der Bestellung: frühere Widerrufe, Zustimmung, Geld-zurück-Anfrage, festgeschriebener Lernstand | abstract | Das Muster „Commerce-Meta im Privacy-Export“ brauchen der Adapter und R1-17 | Privacy-Exporter für Grant- und Commerce-Meta | R3.1 Woo | R1-17, R3-06 | `account-test.php` „Datenexport vollständig …“ Z. 300 |
| INV-B-134 | `WithdrawalTexts.php:17-114` | UI | Deutsche Ersatztexte für die Woo-Widerrufsfunktion (`gettext_woocommerce`) | replace | Woo-Übersetzung | WooCommerce-Sprachpaket | exclude | – | – |
| INV-B-135 | `LegalTextsEmail.php:24-264` | UI | AGB und Widerrufsbelehrung in der ersten Bestellmail (§ 312f Abs. 2 BGB), vermerkt nach erfolgreichem Versand (T236), Platzhalter-Prüfung | replace | Rechtstext-Plugin | z. B. Germanized / German Market | exclude | – | `woocommerce-test.php` „AGB und Widerrufsbelehrung in der Bestellmail“ Z. 1817 |
| INV-B-136 | `OrderButton.php:18-120` | UI | „Zahlungspflichtig bestellen“ (§ 312j Abs. 3 BGB) für klassische Kasse, Bezahlseite und Block; Gateway-Beschriftung wird zurückgesetzt | replace | Shop-Rechtspflicht | Rechtstext- bzw. Shop-Plugin | exclude | – | `woocommerce-test.php` „Bestell-Schaltfläche“ Z. 1774 |
| INV-B-137 | `PurchaseInfo.php:19-181` | UI | Pflichtangaben vor dem Bestellen: Zahlungsarten, Verkaufsländer, Lieferung digital (§ 312j Abs. 1 BGB) | replace | Shop-Rechtspflicht | Rechtstext- bzw. Shop-Plugin | exclude | – | `woocommerce-test.php` „Hinweis Zahlungsarten und Lieferung“ Z. 496 |
| INV-B-138 | `PriceHistory.php:18-134` | Commerce | Niedrigster Preis der letzten 30 Tage (§ 11 PAngV): Preisverlauf in der Produkt-Meta | replace | Preisrecht des Shops | Rechtstext- bzw. Shop-Plugin | exclude | – | `woocommerce-test.php` (Shop-Abschnitte) |
| INV-B-139 | `PriceNote.php:15-81` | UI | Preishinweis „inkl. MwSt.“ an Preisen, im Warenkorb und in Mails (Text aus den Einstellungen) | replace | Shop-Rechtspflicht | Theme- bzw. Shop-Plugin | exclude | – | `woocommerce-test.php` (Shop-Abschnitte) |
| INV-B-140 | `OssMonitor.php:18-287` | Commerce | OSS-Schwelle 10 000 € (SQL-Summen für HPOS und CPT, Transient je Jahr, Admin-Hinweis, Systemstatus, Schweiz) | replace | Steuerthema des Shops (Konzept 32.1: Steuern bei Woo) | Steuer- bzw. Buchhaltungslösung | exclude | – | `woocommerce-test.php` „OSS-Schwelle“ Z. 120 und Z. 160 |
| INV-B-141 | `BankDetails.php:14-45` | UI | Bankdaten der Überweisung (BACS) auch in der Bestellansicht (T50) | replace | Woo-Gateway-Anzeige | WooCommerce bzw. Theme | exclude | – | `woocommerce-test.php` „Überweisung (BACS)“ Z. 101 |
| INV-B-142 | `CouponField.php:30-102` | UI | Gutscheinfeld nur zeigen, wenn es einen gültigen Gutschein gibt (T58), Filter `bootspruefung_hide_coupon_field`; Ausnahme Voucher Captain | replace | Shop-UX | WooCommerce-Einstellung | exclude | – | `extensions-test.php` (CouponField) |
| INV-B-143 | `Gateways.php:15-107` | UI | Gateway-Profile (BACS, Stripe, PayPal, Klarna) als Erklärtexte, `expressCapable` (T148), Filter `bootspruefung_gateway_profiles` | replace | Nur Doku- und Diagnosetext; das Adapterverhalten ist gateway-neutral | Woo-Status in der Connector-Diagnose | exclude | R3-14 (nur Diagnose) | `woocommerce-test.php` Z. 326 |
| INV-B-144 | `SimUpgrade.php:17-188` | Commerce | Order Bump „Funkgerät-Simulator“ nur zusammen mit einem Funkkurs (Validierung, verwaiste Positionen entfernen); `styles()` als gemeinsames CSS der Hinweisboxen | exclude_domain_specific | Fachlicher Simulator | – | exclude | – | `woocommerce-test.php` „Simulator-Upgrade im Checkout“ Z. 1296 |
| INV-B-145 | `VideoUpgrade.php:18-241` | Commerce | Video-Upgrade zur Theorie-Stufe: Angebot im Checkout und im Lernbereich (`offerFor`), Validierung, Warenkorb bereinigen | exclude_domain_specific | Stufenmodell des Fachkatalogs; ein generisches Upsell-Modell ist D-7 | – | exclude | – | `woocommerce-test.php` „Video-Upgrade zur Theorie-Stufe“ Z. 1323 |

### 2.12 Add-on Partnerprogramm (`learnboating-affiliate-connector`)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-146 | `learnboating-affiliate-connector/src/Connector.php:45-138` | API | `boot`, `diagnose` und `problemText`: Major-Vertragsversion von Affiliate Captain, Versionsvertrag der Lernplattform und genutzte Methoden prüfen; sonst kontrolliert inaktiv; Fehlercodes, keine Übersetzung vor `init` (T122) | reuse | Muster für Version-Handshake (Konzept 31.5) | Connector-Handshake | R3.4 sonstige | R3-07, R3-11, R3-12 | `tests/connector-test.php` Z. 78-132 |
| INV-B-147 | `Connector.php:163-212` | Commerce | `commissionBase`, `exclusionReason` und `hasSeats`: keine Provision auf Gruppenpakete (Plätze), Upgrades oder SKU-Muster mit `*`; nur Quelle `woocommerce` | abstract | Die Regeln sind generisch formulierbar (Seats, Zusatzprodukt, Muster); die SKUs sind katalogspezifisch | Affiliate-Ausschlussregeln | R3.4 sonstige | R3-07, R0-40 | `connector-test.php` Z. 82-93 |
| INV-B-148 | `Connector.php:218-236` | Commerce | `holdUntil`: Provision frühestens nach max(Widerrufsfrist 14, Garantie des Produkts) + Puffer 2 Tage | abstract | Doppelte Finanzereignisse und Refund vermeiden (R0-40) | Haltefrist-Regel | R3.4 sonstige | R0-40, R3-19 | `connector-test.php` Z. 95-100 |
| INV-B-149 | `Connector.php:244-317` | API | `offerUrl`, `requestCode`, `enterRest`, `leaveRest`, `isMobileRoute` und `isMobileRequest`: Partnercode aus dem App-Deeplink (`?partner` / `X-Partner-Code`) an Kauf-URLs der Mobile-API, nur gültige Codes, einmal je Anfrage aufgelöst, `utm_campaign=app` | abstract | Hängt an der Mobile-API (Teil D) und am Filter `bootspruefung_shop_offer_url` | Partnerkennung im Kauf-Link | R3.4 sonstige | R3-07 | `connector-test.php` Z. 102-124 |
| INV-B-150 | `Connector.php:141-157, 321-373` | UI | Einstellungen (`lbac_settings`), Seite und Speichern (Nonce, Rechte), Admin-Hinweis, `registerIntegration` (`wpac_registered_integrations`) | reuse | Generisch | Connector-Settings | R3.4 sonstige | R3-14, R3-16 | `connector-test.php` |
| INV-B-151 | `learnboating-affiliate-connector.php:1-32`, `uninstall.php:1-28` | PHP | Plugin-Kopf (`Requires Plugins`), Bootstrap an `plugins_loaded` 20, Textdomain an `init`; Deinstallation löscht die Option (Multisite je Website) | reuse | Muster für Add-on-Lifecycle | Add-on-Bootstrap | R3.4 sonstige | R1-23, R3-18 | – |
| INV-B-152 | `learnboating-affiliate-connector/tests/connector-test.php:134-145` | Test | Versionen der Add-ons: Kopf = Konstante = oberster CHANGELOG-Eintrag, Autor gesetzt (beide Add-ons) | reuse | Release-Gate | Release-Check | R3.4 sonstige | R0-18, R1-39 | ebendort |

### 2.13 Add-on Kursgutscheine (`learnboating-voucher-connector`)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-153 | `learnboating-voucher-connector/src/Connector.php:77-140` | API | `boot` (Grant-Vertrag 1.x und `vcap()->redemptions` prüfen, sonst Hinweis), `deactivate` und `register`: Gutscheintyp `course`, Kanal `learnboating`, Ereignis-Labels, Filter `bootspruefung_course_voucher_redeemer_v1`, Cron `lbvc_retry` stündlich | abstract | R3.2-Connector; der Kanalname wird neutral | Voucher-Captain-Connector | R3.2 Voucher | R3-04 | `tests/connector-integration-test.php` Z. 63-66 |
| INV-B-154 | `Connector.php:145-178` | Commerce | `parseTarget`, `target` (Ziel beim Kauf in der Bestellposition festhalten, sonst aus der Produkt-Meta, Filter `lbvc_voucher_target`) und `targetString` | abstract | Zielmodell → D-3 | Gutscheinziel-Auflösung | R3.2 Voucher | R3-04 | `connector-integration-test.php` „Kauf, Kanal und Ziel“ Z. 150-153 |
| INV-B-155 | `Connector.php:183-244` | Commerce | `channelAllowed` (Kursgutschein nur im eigenen Kanal, Shop nur ausdrücklich), `shopRefusedMessage` und `neutralShopMessage` (eindeutige Meldung nur angemeldet und nach Limitprüfung, T109), `shopLimited` und `redemptionAllowed` (nur das eigene Ziel) | abstract | Ein eindeutiger Einlöseweg (R3-04) | Kanal- und Zielregeln | R3.2 Voucher | R3-04, R3-05 | `connector-integration-test.php` Z. 155-184, Z. 227-245 |
| INV-B-156 | `Connector.php:249-329` | UI | `targetOptions` (nur Produkte mit aktiver Zuordnung), `productField`, `productSave` (ungültiges Ziel abgelehnt; ein Gutscheinprodukt darf selbst keinen Lernzugang haben; Kanal ergänzt) und `snapshot` | abstract | Admin-Verknüpfung (R3-14) | Gutscheinprodukt → Zugangsangebot | R3.2 Voucher | R3-14 | `connector-integration-test.php` „Admin-Verknüpfung“ Z. 136-148 |
| INV-B-157 | `Connector.php:332-363` | UI | `flushNotice` und `targetNotice`: Hinweis auf Gutscheine mit kaputtem Ziel (Transient 10 min, sofort neu geprüft bei Produkt-Hooks, T105) | reuse | Diagnose (R3-10) | Connector-Health | R3.2 Voucher | R3-10 | `connector-integration-test.php` Z. 279-286 |
| INV-B-158 | `Connector.php:367-416` | PHP | `key` (`learnboating:grant:{id}[:n]`), `attempt` (bis 50 Versuche nach Freigabe), `is_course_voucher` und `check` (Vorschau ohne Reservierung) | abstract | Schlüsselpräfix neutralisieren | Einlöse-Schlüssel | R3.2 Voucher | R3-04 | `connector-integration-test.php` Z. 184-189 |
| INV-B-159 | `Connector.php:423-540` | Commerce | `redeem`, `redeemLocked` und `finish`: erst Warteschlange abarbeiten; Sperre je Gutschein; vor der Reservierung prüfen (kein Zugang möglich → nichts reserviert); reserve (24 h) → grant → commit; endgültiger Grant-Fehler → release; Commit-Fehler `reservation_missing/cancelled/expired` → Grant widerrufen und release; vorübergehend → Eintrag bleibt in der Warteschlange | reuse | Saga mit Kompensation (Konzept 31.3, CORE-08) | Reserve/Grant/Commit-Saga | R3.2 Voucher | R3-04, R3-05, R3-19 | `connector-integration-test.php` Z. 186-265 (mit Parallel-Test Z. 207-225) |
| INV-B-160 | `Connector.php:545-602` | Cron | `retry` und `retryOne`: Warteschlange unter der Gutscheinsperre; committed → fertig; released → angelegten Grant widerrufen; kurz vor Ablauf der Reservierung freigeben | reuse | Recovery (R3-10) | Pending-Recovery-Job | R3.2 Voucher | R3-10 | `connector-integration-test.php` Z. 247-304 |
| INV-B-161 | `Connector.php:611-655` | PHP | `lockVoucher`, `unlockVoucher`, `lockName` (sha256 über DB, Präfix und Name, ≤ 64 Zeichen), `lock` (GET_LOCK, `send_reads_to_masters`, NULL = ohne Sperre weiter) und `unlock` | reuse | Doppelt zu `Infrastructure\NamedLock` (Teil C); eine einzige Lock-Infrastruktur nutzen | Gemeinsamer DB-Lock-Dienst | R1-Free (Infrastruktur) | R0-21, R1-42 | `tests/locks-test.php` Z. 70-89 |
| INV-B-162 | `Connector.php:662-684` | PHP | `redeemLimited`: 30 pro IP und Stunde (gemeinsamer Zähler mit App und „Mein Lernen“, IPv6 je /64) und 10 pro Konto und Stunde; vor dem Nachschlagen gezählt | reuse | Schutz gegen Durchprobieren (R0-17) | Rate-Limit für Einlösungen | R3.2 Voucher | R0-17, R3-05 | `locks-test.php` „Einlöseseite: Limit je IP und je Konto“ Z. 134-182 |
| INV-B-163 | `Connector.php:687-704` | Commerce | `onVoucherEvent`: Gutschein `voided` → Grant nach der Erstattungsregel widerrufen (Filter `lbvc_revoke_on_void`) | reuse | Refund über die Quelle (CORE-04) | Revocation bei Quellwiderruf | R3.2 Voucher | R3-05 | `connector-integration-test.php` „Widerruf“ Z. 356-359 |
| INV-B-164 | `Connector.php:707-743` | UI | `redeemPage`: Shortcode `[vcap_redeem]` (gehört Voucher Captain) über den Filter `vcap_redeem_code`; Limit, Login-Hinweis, Meldung vorübergehend oder endgültig; Link „Jetzt lernen“ (Standard `/bootsscheine/` ist fachlich) | abstract | Der Einlöseweg ist generisch, die URL fachlich | Einlöse-Seite | R3.2 Voucher | R3-13 | `connector-integration-test.php` |
| INV-B-165 | `Connector.php:747-794` | PHP | `find` (Code-Normalisierung), `resolve` (mehrfach einlösbare Gutscheine ausgeschlossen), `targetData`, `voucherData` (`code_hint` nur die letzten 4 Zeichen) und `email` | reuse | Keine Speicherung von Geheimcodes (Konzept 32.1) | – | R3.2 Voucher | R3-04 | `connector-integration-test.php` |
| INV-B-166 | `Connector.php:796-865` | Daten | `pending`, `done`, `queue` (Cache umgangen), `editQueue` (unter der Sperre `queue`; belegt → Cron `lbvc_queue_edit` in 60 s, höchstens 5 Versuche, T115) und `deferredQueueEdit` | abstract | Option als Warteschlange; Konzept 35.2 verlangt eine persistente Inbox/Outbox | Pending-Inbox (Tabelle) | R3.2 Voucher (Kern-Outbox R3-01) | R3-01, R3-10 | `locks-test.php` „Warteschlange nie ohne Sperre“ Z. 91-132 |
| INV-B-167 | `learnboating-voucher-connector.php:1-33`, `uninstall.php:1-32` | PHP | Bootstrap (`plugins_loaded` 30), Deaktivierung räumt Cron-Ereignisse; Deinstallation löscht `lbvc_pending`, Transient und Cron (Multisite), Produkt-Meta und Grants bleiben | reuse | Lifecycle-Muster | Add-on-Lifecycle | R3.2 Voucher | R1-23, R3-11 | – |

### 2.14 Tests und Fixtures (Golden-Master-Kandidaten)

| ID | Quellpfad:Zeile | Art | Funktion | Klassifikation | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Golden Master |
|---|---|---|---|---|---|---|---|---|---|
| INV-B-168 | `tests/integration/commerce-test.php:1-303` | Test | Grant-Kern ohne Woo: BACS, Gateways, Dispute, Erstattung, Bundle, `keep`, Storno, failed, Konto, Laufzeit, `parallel`, Kette, Doppelkauf, manuell, Abgleich, Filter | reuse | Bester Golden Master für R1-Grants; die Fixtures sind neutral umsetzbar | Golden-Master-Suite „Grants“ | R1-Free | R0-05, R1-18 | ebendort |
| INV-B-169 | `tests/integration/support/FakeOrders.php:12-44` | Test | Testquelle `OrderSource` (`put`, Seiten) | reuse | Fixture für Adapter-unabhängige Tests | `FakeSourceAdapter` | R1-Free | R0-05 | ebendort |
| INV-B-170 | `tests/integration/voucher-grant-test.php` | Test | Zielprüfung, Freischaltung, Idempotenz mit parallelen Prozessen, Verlängerung, `parallel`, dauerhaft, Widerruf, Audit, Suche | reuse | Golden Master für die Grant-API mit Fremdschlüssel | Suite „Grant-API“ | R3.2 Voucher (Kernteil R1) | R0-05, R3-05 | ebendort |
| INV-B-171 | `tests/integration/membership-test.php:400-660` | Test | Gruppen: Kauf → Pool, Einladung, Beitritt, Races (NamedLock), Ablauf, Anonymisierung, Dispute, Refund, Teilrefund, Kontolöschung, Export, Rückkehr nach Login | reuse | Golden Master für Seat und Claim (ohne Woo-Teil) | Suite „Seat-Pools“ | R1-Free | R0-05, R1-35, R1-42 | ebendort |
| INV-B-172 | `tests/integration/woocommerce-test.php:1-1963` | Test | Woo-Adapter gesamt (39 Abschnitte); die Shop-Rechtsabschnitte (OSS, § 312j/f, PAngV, BACS) entfallen | abstract | Shop- und Fachabschnitte aussortieren | Suite „Woo-Adapter“ | R3.1 Woo | R0-05, R3-03 | ebendort |
| INV-B-173 | `tests/integration/woocommerce-setup.php:1-34` | Test | Woo aktivieren (EUR, Gast-Kasse an – das Plugin muss trotzdem ein Konto erzwingen, BACS) | reuse | Testumgebung | Woo-Fixture | R3.1 Woo | R0-05 | ebendort |
| INV-B-174 | `learnboating-voucher-connector/tests/connector-integration-test.php:1-385`, `tests/setup.php:1-32` | Test | Abnahme mit echtem Voucher Captain: 6 Kriterien (exakter Umfang, Idempotenz und Race, Laufzeit ab Einlösung, Ausschluss Warenkorb/„Mein Lernen“, Fehler und Retry, Verlauf), App-Fehlercodes `VOUCHER_*` | reuse | Golden Master R3.2 | Suite „Voucher-Connector“ | R3.2 Voucher | R3-05, R3-19 | ebendort |
| INV-B-175 | `learnboating-voucher-connector/tests/locks-test.php:1-169` | Test | GET_LOCK, Warteschlange unter Sperre (belegt, NULL, HyperDB), IP- und Konto-Limit, keine Übersetzung beim Start | reuse | Muster für Lock-Tests | Suite „Locks und Limits“ | R1-Free (Lock-Teil), R3.2 | R1-42, R0-21 | ebendort |
| INV-B-176 | `learnboating-affiliate-connector/tests/connector-test.php:1-149` | Test | Ohne WordPress (Stubs): Ausschlussregeln, Haltefrist, App-Übergabe, Vertragsfehler | reuse | Contract-Test-Muster | Suite „Affiliate-Connector“ | R3.4 sonstige | R3-12 | ebendort |
| INV-B-177 | `tests/e2e/checkout-flow.mjs`, `checkout-setup.php`, `membership-setup.php` | Test | Browser: Sperrhinweis mit Produktlink, Kontopflicht für Gäste, Checkout als Block und klassisch mit Überweisung, Freischaltung, „Mein Lernen“; Gruppen-Setup | abstract | Fachtexte und -produkte ersetzen | E2E „Kauf → Zugang“ | R3.1 Woo | R3-13, R1-18 | ebendort |

## 3. Daten

### 3.1 Tabellen (Schema in `src/Infrastructure/Database/Schema.php`, Teil C; hier nur Nutzung)

| Tabelle | Spalten | Schlüssel | Schreiber im Bereich |
|---|---|---|---|
| `{prefix}bp_entitlement` (`Schema.php:641-666`) | `id, user_id, course_code, feature_code, status (Std. pending), valid_from, valid_until, source (≤16), reference_key (≤100), order_id, order_item_id, product_id, variation_id, sku_snapshot, granted_by, reason, idempotency_key, meta (JSON), created_at, updated_at` | **UNIQUE `reference_scope (reference_key, course_code, feature_code)`**; `user_access`, `order_id`, `order_item_id`, `status_until` | `EntitlementService`, `GroupService` (direktes SQL), `Integration::amountOnlyRefund` (über Plan) |
| `{prefix}bp_product_access` (`Schema.php:680-693`) | `id, product_id, variation_id (0 = Produkt), course_code, feature_code, duration_days (NULL = unbefristet), renewal_mode, refund_rule, seats, active` | UNIQUE `product_scope (product_id, variation_id, course_code, feature_code)` | `ProductAccessRepository`, `MappingCheck::removeOrphan`, `ShopCatalog::applyProducts` |
| `{prefix}bp_learning_group` (`Schema.php:733-752`) | `id, owner_user_id, name, scopes (JSON [{course, feature}]), seats, status, valid_from, valid_until, order_id, order_item_id, product_id, join_token, reason (Text oder JSON {"manual": …})` | UNIQUE `order_item`, UNIQUE `join_token` | `GroupService` |
| `{prefix}bp_learning_group_member` (`Schema.php:753-772`) | `id, group_id, user_id, email, status (invited/active/removed/expired), invite_token, invited_at, joined_at, removed_at` | UNIQUE `invite_token`; Fremdschlüssel → Gruppe CASCADE (`Schema.php:914`) | `GroupService` |
| `{prefix}bp_access_rule` | `course_code, module_code, feature_code, access_type (free_limited/free/…), limit_type, limit_value, valid_from, valid_until` | – | nur `ShopCatalog::applyFreeTier` (schreibt), `ExpiryReminder::freeCourses` (liest) |
| gelesen | `bp_audit_log` (`MappingCheck::summary`), `bp_progress`, `bp_video_progress`, `bp_course`, `bp_course_module`, `bp_module_question`, `bp_question`, `bp_video_*`, `bp_exam_paper`, `bp_navigation_task_course`, `bp_praxis_task`, `bp_device` (Kennzahlen und Nutzungsgrad), `bp_mobile_rate_limit` (RateLimiter), `wc_orders*`, `posts`/`postmeta`, `woocommerce_order_items(meta)`, `woocommerce_tax_rates` (OSS, Garantie-Anfrage) | – | – |

**JSON in `bp_entitlement.meta`:** `duration_days`, `renewal_mode`, `refund_rule`, `paid_at`, `manual{status, from, by, at, reason}`, `extended{to, by, at}`; bei Gutscheinen zusätzlich `voucher_id`, `redemption_key`, `grant_source`, `source_reference` und `redeemed_at`.
**Referenzschema `reference_key`:** `wc_item:<itemId>`, `manual:<uuid4>`, `group:<groupId>:<memberId>`, `voucher:<redemptionKey>`.

### 3.2 Optionen

| Option | Inhalt | Quelle |
|---|---|---|
| `bp_learning_disputes` | PayPal Client-ID, Secret und Webhook-ID, Modus, Secret des allgemeinen Webhooks, Strict-Modus | `DisputeWebhooks.php:54` |
| `bp_learning_paypal_verify_last` | letzte Prüfung {result, time, mode} | `DisputeWebhooks.php:84` |
| `bp_learning_prices` | eigene Preise je SKU | `ShopCatalog.php:45` |
| `bp_learning_reconcile_cursor`, `bp_learning_reconcile_retry`, `bp_learning_reconcile_last`, `bp_learning_deactivated_at` | Stand, Retry-Liste, letzter Lauf und Deaktivierungszeit des Abgleichs | `AutoReconcile.php:25-39` |
| `bp_learning_member_discounts` | Rabattregeln | `MemberDiscounts.php:21` |
| `bp_learning_hide_unused_coupon_field` | „no“ schaltet das Ausblenden ab | `CouponField.php:33` |
| `bp_learning_withdrawal_page`, `bp_learning_withdrawal_autoenabled`; schreibt `woocommerce_feature_order_withdrawal_enabled` | Widerrufsfunktion | `WithdrawalFunction.php:46-52, 150` |
| `lbac_settings` | Einstellungen Affiliate-Connector | `affiliate Connector.php:25` |
| `lbvc_pending` | Warteschlange offener Einlösungen | `voucher Connector.php:47` |
| gelesen | `Settings`-Schlüssel `expiry_reminder_enabled`, `expiry_reminder_days`, `default_access`, Marke, Preishinweis; `bp_learning_active_package`; Woo-Optionen (`woocommerce_terms_page_id`, `woocommerce_store_*`, `woocommerce_allowed_countries`, `woocommerce_enable_*`, `woocommerce_currency`) | diverse |

### 3.3 Meta

| Ebene | Schlüssel | Quelle |
|---|---|---|
| Bestellung | `_bp_dispute_state`, `_bp_dispute_events` | `DisputeWebhooks.php:57-60` |
| Bestellung | `_bp_money_back_request`, `_bp_money_back_usage` | `MoneyBack.php:28-30` |
| Bestellung | `_bp_digital_consent`, `_bp_digital_consent_pending`, `_bp_consent_confirmation_sent` | `DigitalConsent.php:24-38` |
| Bestellung | `_bp_legal_texts_sent` | `LegalTextsEmail.php:29` |
| Bestellung | `_bp_withdrawal` (alt), liest `_order_withdrawal_requested` (Woo) | `WithdrawalFunction.php:43-49` |
| Bestellung | liest `_bp_paid_by_voucher` (`Extensions\Vouchers::PAID_META`) | `MoneyBackWoo.php:238` |
| Position | Anzeige-Meta „Mitgliederrabatt“; Warenkorb-Daten `bp_member_discount`, `bp_base_price` | `MemberDiscounts.php:131-165` |
| Position (VCAP) | `\VCAP\Issuer::LINE_META[learnboating_target]` | `voucher Connector.php:46, 159-161, 325` |
| Produkt | `_bp_money_back` | `MoneyBack.php:27` |
| Produkt | `_bp_catalog_fields`, `_bp_catalog_price`, `_bp_catalog_status`, `_bp_manual_status`, `_bp_catalog_text`; liest `_edit_last` | `ShopCatalog.php:301-313, 377, 620` |
| Anhang | `_bp_catalog_image` | `ShopCatalog.php:758-783` |
| Produkt | `_bp_price_history` | `PriceHistory.php:20` |
| Produkt | `_lbvc_target`, schreibt `_vcap_channels` | `voucher Connector.php:45, 310-313` |
| Nutzer | `bp_expiry_reminders`, `bp_expiry_reminder_optout` | `ExpiryReminder.php:35`, `ReminderOptOut.php:22` |
| Nutzer | `bp_money_back_used`, `bp_money_back_downloads` | `MoneyBack.php:33-36` |

### 3.4 Transients

| Transient | TTL | Quelle |
|---|---|---|
| `bp_dispute_<md5(source\|eventId)>` | 90 Tage | `DisputeWebhooks.php:679, 768` |
| `bp_webhook_rejected_<source>` | 10 min | `DisputeWebhooks.php:392-397` |
| `bp_paypal_verify_calls` | 1 min | `DisputeWebhooks.php:422-426` |
| `bp_paypal_token_<md5(mode\|clientId)>` | Ablauf −5 min, ≤ 8 h | `DisputeWebhooks.php:481-492` |
| `bp_learning_course_stats` | ohne Ablauf (Paketstempel) | `ShopCatalog.php:789-833` |
| `bp_oss_totals_<Jahr>` | 1 h | `OssMonitor.php:22, 104` |
| `bp_money_back_notice_<userId>` | 10 min | `MoneyBackWoo.php:478` |
| `lbvc_broken_targets` | 10 min | `voucher Connector.php:52-54` |

## 4. Schnittstellen

### 4.1 REST-Routen

| Route | Methode | Berechtigung | Quelle |
|---|---|---|---|
| `bootspruefung/v1/webhooks/paypal` | POST | `__return_true`; Vorfilter, Signaturprüfung bei PayPal; 401/405/413/415/429/503 | `DisputeWebhooks.php:207` |
| `bootspruefung/v1/webhooks/dispute` | POST | `__return_true`; HMAC-SHA256 `X-Bootspruefung-Signature: sha256=<hex>`; 400/401/503; 409 bei Sperre | `DisputeWebhooks.php:208` |
| Store API (Erweiterung) | – | `cart.extensions.bootspruefung.digital`; Zusatzfeld `bootspruefung/digital-consent` (Ort `order`) | `DigitalConsent.php:163-204` |
| Ausgehend | – | PayPal `/v1/oauth2/token`, `/v1/notifications/verify-webhook-signature` | `DisputeWebhooks.php:427-440, 468` |
| Mobile-API (Teil D) | – | `/vouchers/redeem` nutzt den Redeemer-Vertrag; der Affiliate-Connector greift `boatingschool/v1` ab | `affiliate Connector.php:301-317` |

### 4.2 admin-post, Endpunkte, Seiten

`admin_post_bp_dispute_settings` (`DisputeWebhooks.php:100`), `admin_post_bp_money_back` (`MoneyBackWoo.php:17`), `admin_post_bp_enable_withdrawal` (`WithdrawalFunction.php:65`), `admin_post_lbac_save` (`affiliate Connector.php:50`); Woo-Konto-Endpunkt `mein-lernen` (`Integration.php:31, 158-161`); Weiterleitung `/vertrag-widerrufen/` (`WithdrawalFunction.php:202-215`); Router-Pfade `gruppe/<token>` (`GroupService.php:733`), `gruppen` (`Integration.php:484`) und `erinnerungen` (`ReminderOptOut.php:23`); Einstellungsseite `options-general.php?page=lbac`.

### 4.3 Shortcodes

`[bootspruefung_vertrag_widerrufen]` (`WithdrawalFunction.php:72`). `[vcap_redeem]` gehört Voucher Captain; der Connector hängt sich über den Filter `vcap_redeem_code` ein (`voucher Connector.php:118, 707`).

### 4.4 Eigene Hooks

**Aktionen:** `bootspruefung_entitlements_changed` (`EntitlementService.php:88`), `bootspruefung_suspend_order` und `bootspruefung_reactivate_order` (Eingang, `Integration.php:136-141`), `bootspruefung_voucher_granted` und `bootspruefung_voucher_revoked` (`VoucherGrants.php:223, 249`), `bootspruefung_money_back_requested` (`MoneyBackWoo.php:467`), `lbvc_course_granted` (`voucher Connector.php:536`). Konsumiert: `bootspruefung_before_erase_user` (`MoneyBackWoo.php:20`).

**Filter:** `bootspruefung_order_access_target` (`OrderSync.php:59`), `bootspruefung_course_relations` (`CourseRelations.php:45`), `bootspruefung_shop_offer_url` (`Integration.php:389`, `AccessStatus.php:164`, `VideoUpgrade.php:142`), `bootspruefung_catalog_disabled_courses` (`ShopCatalog.php:104`), `bootspruefung_voucher_grants_v1` (`VoucherGrants.php:35`), `bootspruefung_voucher_money_back` (`MoneyBack.php:208`), `bootspruefung_paypal_webhook_per_ip` und `bootspruefung_paypal_verify` (nur Tests) (`DisputeWebhooks.php:302, 410`), `bootspruefung_gateway_profiles` (`Gateways.php:54`), `bootspruefung_cart_duplicate_hint` (`CartDuplicateHint.php:158`), `bootspruefung_cart_relation_hint` (`CartRelationHint.php:135`), `bootspruefung_hide_coupon_field` (`CouponField.php:76`), `bootspruefung_member_discount` (`MemberDiscounts.php:87`), `bootspruefung_money_back_cash_amount` und `bootspruefung_money_back_recipient` (`MoneyBackWoo.php:260, 449`), `bootspruefung_require_digital_consent` (`WooOrderSource.php:64`), `bootspruefung_guarantor` (`ContractSummary.php:108`), `bootspruefung_legal_texts_email_pages` (`LegalTextsEmail.php:144`), `bootspruefung_oss_threshold` (`OssMonitor.php:59`), `bootspruefung_purchase_info_lines` (`PurchaseInfo.php:115`), `bp_learning_expiry_reminder_batch_size` und `bp_learning_expiry_reminder_time_budget` (`ExpiryReminder.php:224, 233`). Im Connector genutzt bzw. angeboten: `bootspruefung_course_voucher_redeemer_v1`, `bootspruefung_voucher_admin_url`, `bootspruefung_voucher_redeem_per_ip`, `lbvc_voucher_target`, `lbvc_reservation_minutes`, `lbvc_learning_url`, `lbvc_revoke_on_void`, `lbvc_lock_timeout`.

**Partner-Hooks:** Affiliate Captain `wpac_registered_integrations`, `wpac_commission_base`, `wpac_hold_until` sowie die Funktionen `wpac_contract_version()` und `wpac_resolve_partner_code()`. Voucher Captain `vcap_voucher_types`, `vcap_redemption_channels`, `vcap_redemption_channel_for_target`, `vcap_redemption_channel_allowed`, `vcap_shop_channel_refused_message`, `vcap_redemption_allowed`, `vcap_product_settings_fields`/`_save`, `vcap_line_snapshot`, `vcap_redeem_code`, `vcap_voucher_event`, `vcap_event_labels`; API `vcap()->redemptions` (reserve, check, commit, release, get) und `vcap()->vouchers`.

### 4.5 WooCommerce-Hooks (konsumiert)

- **Bestellung und Zahlung:** `woocommerce_order_status_changed`, `woocommerce_payment_complete`, `woocommerce_order_refunded`, `woocommerce_refund_deleted`, `woocommerce_process_shop_order_meta`, `woocommerce_before_delete_order`, `woocommerce_new_order`, `woocommerce_update_order`, `woocommerce_order_status_{on-hold,processing,completed}`; WP `before_delete_post` (`Integration.php:102-133`, `DigitalConsent.php:61-64`, `OssMonitor.php:29`).
- **Kasse und Warenkorb:** `woocommerce_is_sold_individually`, `woocommerce_checkout_registration_required`/`_enabled`, `option_woocommerce_enable_guest_checkout`, `option_woocommerce_enable_signup_and_login_from_checkout`, `woocommerce_store_api_checkout_update_order_from_request`, `render_block_woocommerce/checkout` und `/cart`, `woocommerce_before_cart`, `woocommerce_before_checkout_form`, `woocommerce_review_order_before_submit`, `woocommerce_checkout_process`, `woocommerce_checkout_create_order`, `woocommerce_checkout_create_order_line_item`, `woocommerce_before_calculate_totals`, `woocommerce_add_to_cart_validation`, `woocommerce_coupons_enabled`, `woocommerce_order_button_text`, `woocommerce_pay_order_button_text`, `woocommerce_available_payment_gateways`, `woocommerce_pay_order_before_submit`, `woocommerce_before_pay_action`, `woocommerce_blocks_loaded`, `woocommerce_init`, `before_woocommerce_init`; WP `render_block_data`.
- **Konto und Mails:** `woocommerce_get_query_vars`, `woocommerce_account_menu_items`, `woocommerce_account_mein-lernen_endpoint`, `woocommerce_endpoint_mein-lernen_title`, `woocommerce_thankyou`, `woocommerce_view_order`, `woocommerce_order_details_after_order_table`, `woocommerce_email_after_order_table`, `woocommerce_email_customer_details`, `woocommerce_email_sent`, `woocommerce_register_form`.
- **Admin:** `woocommerce_after_order_itemmeta`, `woocommerce_order_actions`, `woocommerce_order_actions_end`, `woocommerce_order_action_bp_access_{sync,approve,suspend,release}`, `woocommerce_order_action_bp_dispute_klarna_{opened,won,lost}`, `woocommerce_admin_order_data_after_order_details`, `woocommerce_product_data_tabs`, `woocommerce_product_data_panels`, `woocommerce_process_product_meta`, `woocommerce_product_after_variable_attributes`, `woocommerce_save_product_variation`.
- **Produkt:** `woocommerce_new_product`, `woocommerce_update_product`, `woocommerce_delete_product`, `woocommerce_trash_product`, `woocommerce_*_product_variation`, `woocommerce_get_price_html`, `woocommerce_single_product_summary`, `woocommerce_after_add_to_cart_form`, `woocommerce_after_cart_totals`, `woocommerce_cart_totals_after_order_total`, `woocommerce_review_order_after_order_total`, `woocommerce_new/update/delete/trash_coupon`.
- **Datenschutz und Texte:** `woocommerce_privacy_export_order_personal_data`, `woocommerce_privacy_remove_order_personal_data`, `gettext_woocommerce`.
- **WordPress allgemein:** `transition_post_status`, `template_redirect`, `admin_notices`, `admin_init`, `rest_api_init`, `register_form`, `wp_footer`, `wp_enqueue_scripts`, `clean_post_cache`, `query` (ShopCache), `set_current_user`, `rest_pre_dispatch`/`rest_post_dispatch` (Affiliate), `plugins_loaded`, `init`, `untrashed_post`.

### 4.6 Cron

| Ereignis | Takt | Handler | Quelle |
|---|---|---|---|
| Wartungslauf stündlich: `entitlement_expire`, `group_invites_expire`, `order_reconcile` | stündlich | `expireDue`, `expireInvites`, `AutoReconcile::run` | `Plugin.php:316-321` (Teil C) |
| `bp_learning_reconcile` | einmalig 1 min nach Aktivierung, falls vorher deaktiviert | `AutoReconcile::run` | `AutoReconcile.php:48, 223-228`, `Plugin.php:260` |
| `bp_learning_daily` | täglich | `ExpiryReminder::run` | `ExpiryReminder.php:32, 60-65`, `Plugin.php:261` |
| `bp_learning_expiry_reminder_continue` | einmalig, +60 s | `ExpiryReminder::resume($now, $after)` | `ExpiryReminder.php:34, 237-240` |
| `lbvc_retry` | stündlich | `Connector::retry` | `voucher Connector.php:48, 135-139` |
| `lbvc_queue_edit` | einmalig, +60 s, höchstens 5 Versuche | `Connector::deferredQueueEdit` | `voucher Connector.php:63, 826-865` |

### 4.7 CLI

`wp bootspruefung reconcile [--order=] [--since=]` (Vorschau bzw. Anwenden über `OrderSync::plan`/`reconcile`, `Cli/Commands.php:25, 353-360`), `wp bootspruefung shop setup` (`ShopCatalog::applyProducts`, `Cli/Commands.php:27, 441-451`). Die CLI selbst gehört zu Teil C.

### 4.8 Mails (ausgehend)

Gruppeneinladung (`GroupService.php:251`), Ablauf-Erinnerung mit `List-Unsubscribe` (`ExpiryReminder.php:335`), Geld-zurück-Anfrage an Betreiber und Kunde (`MoneyBackWoo.php:450-466`). Dazu Einblendungen in Woo-Mails: Zugangshinweis, Laufzeit, Zustimmung, Rechtstexte, Preishinweis.

## 5. Zustände und Locks

### 5.1 Grant-Zustände (`bp_entitlement.status`) und Übergänge

| Von → Nach | Auslöser | Grund-Code | Quelle |
|---|---|---|---|
| neu → pending | Bestellung pending/on-hold ohne Zahlung | `awaiting_payment` | `EntitlementPlanner.php:65-67` |
| neu → pending | bezahlt, Zustimmung fehlt | `consent_missing` | `:47-49` |
| neu/pending → active | bezahlt (`is_paid`) | `order_paid` | `:43-51` |
| active → suspended | bezahlt, dann wieder on-hold (Dispute) | `payment_disputed` | `:53-56` |
| pending → suspended | **ignoriert** | – | `:165-167` |
| * → revoked | Storno, Bestellung erstattet, Zahlung fehlgeschlagen | `order_cancelled`, `order_refunded`, `payment_failed` | `:37-59` |
| active → revoked | Position erstattet (je Regel) | `item_refunded` | `:109-112` |
| * → revoked | Position entfernt | `item_removed` | `:117-121` |
| revoked (automatisch) → active | wieder bezahlt, Position nicht erstattet, kein Betrag ohne Positionsbezug | – | `:155-164` |
| expired → active | **nie automatisch**; nur über `extend()` | – | `:158-160`; `EntitlementService.php:376-378` |
| active → expired | Cron oder `extend()` in der Vergangenheit | – | `EntitlementService.php:373-375, 426-432` |
| bei `refund_rule=keep` | Erstattung ändert nichts (Storno widerruft trotzdem) | `refund_kept` | `:107-108`; `commerce-test.php:145-152` |
| manuell (`meta.manual`) | wird nur noch durch revoked übersteuert | `manual_approval` u. a. | `:150-154`; `EntitlementService.php:387-423` |
| Gruppenplatz → revoked | Platz frei, weniger Plätze, Konto gelöscht | `group_member_removed`, `group_seats_reduced`, `account_deleted` | `GroupService.php:429-486` |
| Planebene ohne Positionen | – | `group_only`, `no_learning_products`, `status_<x>`, Warnung `no_account` | `OrderSync.php:63-67`, `EntitlementPlanner.php:63-70, 86-88` |

### 5.2 Weitere Zustandsautomaten

- **Gruppe:** `active`, `suspended`, `revoked`; manuell markiert, wenn `reason` = JSON `{"manual": …}`. Ein manueller Widerruf lebt nicht wieder auf. Ein automatischer Widerruf lebt nur ohne Erstattung ohne Positionsbezug wieder auf (`GroupService.php:83-91`).
- **Mitglied:** `invited` → `active` (Beitritt) | `removed` | `expired` (Cron nach 30 Tagen). Ein Platz ist belegt bei `active` oder bei `invited` innerhalb der 30 Tage (`:639-642`).
- **Dispute-Fall** (je `source|case`): `opened`, `won`, `lost`. Entscheidung `apply`/`ignore`/`noop` (`DisputeWebhooks.php:801-816`). Antworten: `duplicate`, `matched` + `orders`/`action` (+ `ignored`), `matched:false`, `invalid_action`, `retry` → HTTP 409.
- **Geld-zurück:** Gründe `voucher`, `refunded`, `no_policy`, `not_paid`, `expired`, `requested`, `used_before`, `downloaded`, `too_much_progress`, `eligible` (`MoneyBack.php:200-288`). Anfrage: `requested`, `already_requested`, `busy` (`MoneyBackWoo.php:377-406`).
- **Gutschein-Einlösung (Voucher Captain):** `reserved` (24 h) → `committed` | `released`. Warteschlangeneintrag `lbvc_pending[key] = {voucher_id, user_id, product_id, variation_id, since}` (`voucher Connector.php:484, 843`).
- **Zustimmung:** vorläufig (`_pending`) → endgültig bei on-hold, processing, completed oder `payment_complete` (`DigitalConsent.php:144-159`).

### 5.3 Idempotenz

| Stelle | Mechanismus | Quelle |
|---|---|---|
| Grant aus Bestellung | UNIQUE `reference_scope`; Insert mit `suppress_errors`, kein Fehler bei einer Kollision | `EntitlementService.php:107-112` |
| Grant aus Referenz | wie oben; liefert die vorhandene ID | `:276-293` |
| `idempotency_key` | sha256 aus (Bestellung\|Position\|Kurs\|Feature) bzw. (Referenz\|Kurs\|Feature) – **nicht unique** | `:103, 272` |
| Gutschein-Grant | `redemption_key` + Lock; anderer Nutzer oder anderes Produkt → `lb_idempotency_conflict` | `VoucherGrants.php:173-187` |
| Gruppe je Position | UNIQUE `order_item`; bei einer Kollision → `byItem` | `GroupService.php:114-127` |
| Dispute | Transient 90 Tage und Order-Meta mit 100 Ereignisschlüsseln; abgeschlossener Fall endgültig | `DisputeWebhooks.php:678-682, 719-723, 760-768` |
| Woo-Hooks je Anfrage | `orderStateKey` | `Integration.php:57-83` |
| Ablauf-Erinnerung | Merker vor dem Versand, Rücknahme bei Fehler | `ExpiryReminder.php:327-345` |
| Geld-zurück-Anfrage | genau einmal je Bestellung (Lock + DB-Lesen ohne Cache) | `MoneyBackWoo.php:377-419` |
| Rechtstext- und Zustimmungsmails | Vermerk erst nach erfolgreichem `woocommerce_email_sent` | `LegalTextsEmail.php:57-75`, `DigitalConsent.php:73-87` |
| `no_account`-Audit | einmal je Anfrage | `OrderSync.php:81-86` |

### 5.4 Locks

| Lock | Technik | Wartezeit / TTL | Zweck | Quelle |
|---|---|---|---|---|
| `group-seats:<groupId>` | `NamedLock` (GET_LOCK) | Standard von NamedLock | Einladung und Beitritt (Belegung) | `GroupService.php:236, 346, 364-367` |
| `money_back:<orderId>` | `NamedLock` | 10 s | Garantie-Anfrage | `MoneyBackWoo.php:367, 381-399` |
| `bp_voucher_grant_<md5(ref)>` | `Lock` (Option) | 30 s | Gutschein-Grant | `VoucherGrants.php:174-228` |
| `bp_dispute_lock_<orderId>` | `Lock` | 60 s | Dispute je Bestellung | `DisputeWebhooks.php:711-766` |
| `bp_learning_expiry_reminder_lock` | `Lock` | 15 min | Erinnerungslauf | `ExpiryReminder.php:45-47` |
| `bp_learning_reconcile_lock` | `Lock` | 30 min | automatischer Abgleich | `AutoReconcile.php:50, 61` |
| `lbvc:<sha256(DB\|Präfix\|voucher:<id>)>` | GET_LOCK (Connector) | 10 s (Filter) bzw. 0 in der Warteschlange | Einlösung je Gutschein | `voucher Connector.php:611-649` |
| `lbvc:…queue` | GET_LOCK (Connector) | 5 s, belegt → Cron | Warteschlange schreiben | `:826-857` |

### 5.5 Rate-Limits

PayPal-Webhook: 10 Anfragen pro IP und Minute (`DisputeWebhooks.php:75, 302`), 60 Prüfungen pro Minute insgesamt (`:77, 422-426`). Einlösungen: 30 pro IP und Stunde, 10 pro Konto und Stunde (`voucher Connector.php:61, 67, 662-684`). Einladungen: max(20, 2 × Plätze) pro 24 h (`GroupService.php:262-270`).

### 5.6 Fehlercodes

- **Grant-Vertrag (`VoucherGrants`):** `lb_shop_unavailable` (503, retryable), `lb_target_unavailable` (409), `lb_invalid_request` (400), `lb_target_unmapped` (409), `lb_target_group` (409), `lb_target_upgrade` (409), `lb_course_unavailable` (409), `lb_user_required` (401), `lb_access_exists` (409), `lb_temporary_failure` (503, retryable), `lb_idempotency_conflict` (409), `lb_grant_not_found` (404).
- **Connector:** `lb_not_course_voucher` (422), `lb_wrong_course` (422), `vcap_reserved` (409, ggf. retryable), `vcap_already_redeemed` (409), `vcap_not_found` (404), `vcap_temporary_failure` (503); geprüft werden auch `vcap_idempotency_conflict`, `vcap_reservation_missing`, `vcap_cancelled` und `vcap_expired`. In der App (Teil D) abgebildet als `VOUCHER_INVALID`, `VOUCHER_REDEEMED`, `VOUCHER_EXPIRED`, `VOUCHER_WRONG_COURSE`, `VOUCHER_NOT_FOR_COURSES`, `VOUCHER_RESERVED` und `ACCESS_NOT_POSSIBLE` (`connector-integration-test.php:330-354`).
- **Webhooks:** `bp_not_configured` (503), `bp_invalid` (400/405/413/415), `bp_signature` (401), `bp_verify_unavailable` (503), `bp_rate_limited` (429), `bp_stale` (400).
- **Affiliate-Connector:** `captain_missing`, `contract_mismatch`, `learning_missing`, `learning_incompatible`.
- **Checkout:** `bootspruefung_account_required` (400), `bootspruefung_digital_consent_required` (400) als Store-API-`RouteException`.

## 6. Entscheidungsbedarf (Produktentscheidungen für den Auftraggeber)

| Nr. | Frage | Befund im Code | Empfehlung |
|---|---|---|---|
| **D-1** | Darf ein durch Erstattung oder Storno widerrufener Grant **wieder aufleben**, wenn der Shop die Erstattung löscht bzw. die Bestellung wieder bezahlt meldet? | LB belebt automatische Widerrufe wieder (`EntitlementPlanner.php:155-164`, `GroupService.php:87`; Test `commerce-test.php:122-124, 161-163`). Konzept 35.2/35.3 sagt: „revoked endgültig je Quellrevision“, kein Re-Grant durch ein verspätetes `paid`. | Dem Konzept folgen: Ein revoked Grant bleibt revoked. Eine bestätigte Rücknahme der Erstattung erzeugt eine **neue Grant-Revision** (gleiche Quellposition, `entitlement_revision`+1) mit Audit. So bleibt das Verhalten für Lernende gleich, und die Historie ist sauber. |
| **D-2** | Gastkauf im Woo-Adapter: **Kontopflicht im Checkout** (wie LB) oder **Claim-Token nach dem Kauf** (Konzept 35.1)? | LB erzwingt ein Konto über Filter, Store-API-Guard und Block-Ersatz (`Integration.php:145-289`). Einen Claim-Flow gibt es nur für Gruppen. | R3.1 mit Kontopflicht als Standard (erprobt, sicher gegen CORE-01). Einen Gast-Claim per einmaligem Token als Option später ergänzen, damit nicht beides zugleich gebaut werden muss. |
| **D-3** | Was ist das **Gutscheinziel**: ein Woo-Produkt (wie LB) oder ein Course-Captain-„Zugangsangebot“ (Kurs, Scopes, Laufzeit) ohne Woo? | `VoucherGrants::target()` verlangt ein veröffentlichtes Woo-Produkt mit Zuordnung (`VoucherGrants.php:71-115`). Konzept 32.1: Der Voucher-Connector darf Woo nicht stillschweigend voraussetzen. | Ziel = neutrales Zugangsangebot in Course Captain. Ein Woo-Produkt ist nur eine optionale zweite Referenz. Dann funktioniert R3.2 auch ohne R3.1 (R3.3: „Voucher allein“). |
| **D-4** | **Geld-zurück-Garantie nach Lernfortschritt** (MoneyBack, MoneyBackWoo, Garantie-Texte und -Prüfungen; rund 1 000 Zeilen) übernehmen? | Es ist eine Marketingregel des Betreibers mit AGB-Bezug, kein Lernkern. Sie berührt Datenschutz (Stand festschreiben) und Gutscheine (Anteil per Gutschein). | Nicht in R3.1/R3.2. Als optionales späteres Modul (R3.4/R4 nach Nachfrage) vormerken. Den neutralen Baustein „Nutzungsgrad je Kurs seit Zeitpunkt“ (INV-B-079) für Berichte festhalten. |
| **D-5** | Welche **deutschen Shop-Rechtspflichten** gehören in Course Captain? (Zustimmung nach § 356 Abs. 5, Widerrufsbutton § 356a, § 312j-Knopf und -Angaben, § 312f-Rechtstexte, PAngV, OSS, Gateway-Webhooks PayPal/Klarna) | Etwa 2 700 Zeilen in `WooCommerce/` und `Disputes/`. Davon wirkt **nur die Zustimmung** auf den Grant (pending `consent_missing`). | Nicht übernehmen (Woo bzw. Rechtstext-Plugins). **Ausnahme:** Der Adapter bietet ein optionales Aktivierungs-Gate „Grant erst nach dokumentierter Zustimmung“ als Filter oder Einstellung, Standard aus. Disputes nur über Woo-Status, den generischen signierten Webhook und eine manuelle Aktion; kein PayPal-API-Client. |
| **D-6** | Soll der Woo-Adapter **Produkte aus Kursen selbst anlegen und abgleichen** (Katalog-Abgleich mit Drift-Erkennung, wie `ShopCatalog::applyProducts`)? | Mechanismus generisch (Fingerprints je Feld, Dry-run, Entwürfe), Inhalt rein fachlich. | Nein für R3.1: nur Zuordnung zu vorhandenen Woo-Produkten. Ein Knopf „Produkt für diesen Kurs anlegen“ (ohne fortlaufenden Abgleich) kann später kommen. |
| **D-7** | Braucht es **abhängige Zusatz-Grants** (Zusatzmodul läuft so lange wie der Basiskurs bzw. ruht ohne ihn)? | LB löst das nur für das Video-Upgrade (SKU-Konvention, `EntitlementService.php:213-243`) und das Simulator-Upgrade (in AccessService, Teil A). | Nicht in R1. Nur in R3.1 aufnehmen, wenn Upsell-Produkte („Kurs + Zusatzmodul“) ausdrücklich gewollt sind. Dann generisch als `depends_on_grant`, nicht über SKUs. |
| **D-8** | **Kursbeziehungen und Cross-Sell** (enthält/empfiehlt, Doppelkauf- und Tausch-Hinweise) und **Mitgliederrabatt** übernehmen? | Tabelle fachlich (`CourseRelations.php:24-39`); Mechanik generisch (INV-B-068, -125, -127); Rabatt in `MemberDiscounts`. | Kursbeziehung „enthält“ als neutrales Modell in R2 (schützt vor Doppelkäufen und Doppelerinnerungen). Hinweise im Warenkorb in R3.1. Mitgliederrabatt nicht übernehmen (Woo-Rabatt- bzw. Mitgliedschafts-Plugins); höchstens R3.4. |
| **D-9** | **Ablauf-Erinnerung:** Edition (Free oder Pro) und Empfängerkreis? | LB erinnert nur Käufer mit einer bezahlten Bestellung über 0 € (§ 7 Abs. 3 UWG, `ExpiryReminder.php:358-383`), mit Widerspruchslink. Konzept R3-15 nennt Reminder bei Pro. | Pro. Mechanik ganz übernehmen (Batch, Lock, Abmeldung). Empfängerkreis als Einstellung, Standard „nur zahlende Käufer aus einer Kaufquelle“. Ohne Kaufquelle (Free, manuell, Gruppe) gibt es keine Mail, nur den Hinweis in „Mein Lernen“. |
| **D-10** | **Granularität des Zugangs:** Grant je Kurs oder je Kurs × Bereich? | LB vergibt Grants je Kurs × Feature (`question_bank`, `exam_simulation`, `video_course`, …; Feature-Liste fachlich). Konzept 7: getrennte Rechte für Ansicht, Download und Prüfung. | Grant je Kurs (optional Kurspaket) mit wenigen neutralen Scopes (`view`, `download`, `exam`; Standard = alle). Fachliche Feature-Codes entfallen. Muss vor R0-06/R0-08 feststehen. |
| **D-11** | **Seat-Regeln:** Sind Einladungslinks **übertragbar** (wer zuerst annimmt, bekommt den Platz)? Und wer verliert bei weniger Plätzen seinen Platz? | LB: übertragbar mit Warnhinweis (T132, `GroupService.php:287-308, 699`). Bei Reduktion gehen zuerst offene Einladungen, dann die zuletzt Beigetretenen (`:472-488`). Konzept 35.1/35.5: Umbuchungsregeln offen; E-Mail ist nie ein Identitätsbeweis. | LB-Regeln als Standard übernehmen (getestet, kein E-Mail-Abgleich nötig). Zusätzlich eine Gruppeneinstellung „nur die eingeladene Adresse darf annehmen“ für Firmen und Vereine. |

**Kein Entscheidungsbedarf, aber Pflicht für R1 (aus dem Befund):** eine manuelle Gruppenanlage ohne Kauf (fehlt in LB); `wc_item` und die Woo-Spalten aus dem Grant-Kern lösen; `idempotency_key` je Quellrevision eindeutig machen; eine einzige Lock-Infrastruktur (NamedLock und Connector-GET_LOCK zusammenführen).
