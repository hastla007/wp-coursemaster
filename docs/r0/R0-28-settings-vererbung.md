# R0-28 · Global-/Kurs-/Prüfungs-Vererbung, Snapshots und konfliktfreie Default-Änderung

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Keine Implementierung, kein Testlauf, kein Commit. Die DoD verlangt Feld-/UI-Vertrag, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe. · **Stand:** 16.11.2026
**Bezug:** TODO R0-28 (Voraussetzungen R0-08, R0-23, R0-27; DoD: „Override-Priorität, Snapshots, laufende Versuche, Reset und konfliktfreie Default-Änderung als Zustandsmatrix“), Ziel-Task **R1-33** (DoD: „Vererbung, Preview, Änderung laufender Versuche und historische Auswertung getestet“), daneben R1-08, R1-20, R1-26, R1-27, R1-31, R1-36; Konzept Kap. 43.1 (Konsens „Globaler Default → expliziter Kurs-/Prüfungs-Override → unveränderlicher Snapshot“), 43.2 (UX-Vertrag), 43.4 („Default und Vererbungsquelle“), 43.5 („Kurs-Overrides und ihre Snapshots“).
**Quellen (nur gelesen):** `konzept.md` Kap. 43; `TODO-WETTBEWERBSANALYSE.md` R0-27, R0-28, R1-33; `docs/r0/ENTSCHEIDUNGEN.md` Beschlussprotokoll und E-01…E-224; R0-27 §1.1 (SR-1…SR-8), §1.3 (Scope-Spalte aller 72 Register-Zeilen), §1.5, §2.1…§2.5, §4, §7 („R0-28: Override-Speicherorte und Materialisierung aus SR-3 und §2.3 übernehmen“); R0-08 §4.1, §4.3…§4.5, §6.3, §7.1…§7.3 und Nachträge; R0-23 §1.2, §2.1 (Hinweis „Vererbung regelt R0-28/R1-33“), §2.3 RV-01/RV-17, §3.2; R0-15 §2.3 (`timer_mode`, `cc_accommodation`); R0-37 Wartungsfenster W1/W2, D37-3; R0-10 Paketschema `courses.settings`, `cc_override` (Rechtestatus); R0-07 M-03, M-06, M-15, `cc_manage_accommodations`.

### Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). In dieses Dokument eingearbeitet:

| Beschluss | Wirkung hier |
|---|---|
| **E-01** Clean Room | dieses Dokument beschreibt nur Verhalten; es übernimmt und zitiert keinen LB-Code |
| **E-20**, E-268, E-269 | Lösungserklärungen sind Pro: `show_explanations` wirkt in Free nie (C-3), bei abgelaufener Lizenz eingefroren weiter, bei deaktiviertem Add-on nicht; der gespeicherte Wert bleibt |
| **E-45/E-45a** | `privacy.external_media_mode` global Default `always_load`, umschaltbar auf `click_to_load`; Medium-Override nur strenger (#28, INH-033) |
| **E-225 … E-230** (früher D28-1 … D28-6) | Sperre ab nächstem Versuchsstart, Sammel-Reset nur in Entwürfen, Neuauflösung bei neuer Regelversion, explizite Übernahme beim Import, keine Kursebene für `exams.*`, Sperren in Tab-Reset und Export (§6) |
| **E-229** | T28-1 ist in R0-08 §4.5 eingearbeitet (`learning.feedback` Kurs → Quiz; `exams.feedback` global → Prüfungsregel) |
| **E-22, E-69, E-168** | neue Fragetypen und Aufgaben bringen noch keine Register-Zeilen mit; Vererbung offen (OP-28-1) |
| **E-207** | Präfix `ccap_` für CPTs, Caps, Rollen, Cron, Meta und Tabellen; Optionen `course_captain_*`. Alle Bezeichner in diesem Dokument sind umgestellt |
| übrige | unverändert angewandt: E-50, E-68, E-71, E-94, E-100, E-104, E-107, E-108, E-110, E-113, E-122, E-123, E-128, E-137, E-147, E-173, E-174, E-215…E-224 |

**Normative Sprache:** **MUSS** / **SOLL** / **KANN** wie in R0-08.

**Kernaussagen**

1. **Vorrang:** Sperre (globale Pflicht) > Prüfungsregel bzw. Activity > Kurs > global > Schema-Default. Die Lernenden-Ebene ist **kein** Override, sondern ein Modifikator nur für die Bearbeitungszeit (Nachteilsausgleich E-122), den keine Sperre abschalten kann.
2. **„Erben“ ist ein eigener Zustand, nicht `null`.** Ein Override ist entweder vorhanden (expliziter Wert) oder fehlt (= erbt). `null` bleibt ein fachlicher Wert (z. B. `max_attempts = null` = unbegrenzt).
3. **Jedes Feld hat genau einen Auflösungszeitpunkt** (Klasse L, K, R, G, E, A in §1.2). Danach ist der Wert eingefroren. Eine Default-Änderung wirkt deshalb nie auf laufende oder abgeschlossene Versuche, Abschlüsse, Grants oder Zertifikate.
4. **Konfliktfrei** heißt: Eine Default-Änderung verändert kein historisches Objekt, erzeugt keinen ungültigen wirksamen Zustand und zeigt vorher, was sie betrifft („betrifft N Kurse“, §3.3). Hinweis und Wirkung kommen aus demselben Resolver.
5. **Kurs-Overrides wandern im Kurspaket**, nicht im Settings-Export (R0-27 §2.5). Reset auf „erben“ gibt es je Feld, je Kurs und als Sammelaktion; keiner davon berührt Lerndaten.
6. Testfälle **INH-001…INH-048** (Ziel R1-33), Beschlüsse **E-225…E-230**, technische Deltas **T28-1…T28-7** (T28-1 in R0-08 eingearbeitet).

---

## 1. Vererbungsmodell

### 1.1 Ebenen und Speicherorte (aus R0-27 SR-3, präzisiert)

| Ebene | Speicherort | Wer schreibt | Wann wirksam |
|---|---|---|---|
| **Schema-Default** | Settings-Schema (PHP), genau einmal (SR-7) | Code | nur als Rückfall, wenn die globale Option den Schlüssel nicht enthält (S-10, Health-Warnung) |
| **Global** | Tab-Option `course_captain_settings_<tab>.values.<feld>` (materialisiert, R0-27 §2.3) | #19 `cc_manage_settings` | nach Klasse (§1.2) |
| **Sperre (globale Pflicht)** | **neu** `course_captain_settings_<tab>.locks.<feld> = {reason ≤ 200, by, since}` (T28-5) | #19 | nach Klasse; Override ruht |
| **Kurs** | Post-Meta `_cc_settings` am Kursentwurf: `{_schema, overrides: {<key>: value}}`; beim Veröffentlichen in `cc_course_revision.settings` eingefroren (E-104) | M-03 | ab Veröffentlichung der Kursrevision |
| **Activity (Quiz)** | Activity-Settings (`settings_version`, `settings_hash`, R0-08 §7.1), Unterschlüssel `overrides` | M-06 | ab Veröffentlichung der Kursrevision |
| **Prüfungsregel** | `cc_exam_rule.settings` des Entwurfs: `{overrides}`; beim Veröffentlichen der Regelversion **materialisiert** als `{<key>: {v, src}}` (R0-08 §7.1) | M-06 | ab Veröffentlichung der Kursrevision, die die Regelversion referenziert (EV-07) |
| **Gruppe** (Nebenscope) | Gruppen-Datensatz, `overrides` | M-15 | bei Einladungserzeugung |
| **Medium** (Nebenscope) | Medien-Meta, nur strenger | M-03 | live |
| **Lernender** (nur Nachteilsausgleich) | `cc_accommodation` (R0-15 §2.3): Regel > Kurs > Site | `cc_manage_accommodations` | beim Versuchsstart |

**MUSS:** Ein Override-Schlüssel ist ein Register-Key aus R0-27 §1.3 mit Scope ≠ `global`. Andere Schlüssel lehnt der Validator ab (Kursentwurf, Import, CLI). **MUSS:** „Erben“ = Schlüssel fehlt in `overrides`. Es gibt keinen Sentinel-Wert `inherit` im Speicher. Die UI und das Paketschema dürfen `"inherit"` als Eingabewort verwenden; gespeichert wird das Entfernen des Schlüssels. **MUSS:** Setting-Overrides sind begrifflich und technisch getrennt von `cc_override` (Rechtestatus-Override aus R0-10 §4); keine gemeinsame Tabelle, kein gemeinsamer Name in UI und Audit.

### 1.2 Auflösungsklassen

| Klasse | Auflösungszeitpunkt | Was eingefroren wird | Wirkung einer globalen Änderung auf erbende Objekte |
|---|---|---|---|
| **L** live | jede Anfrage (Render, Lernaktion, Anmeldeanfrage) | nichts | sofort (nach `cache_bump`, R0-21) |
| **K** Kursrevision | Veröffentlichen der Kursrevision | wirksamer Wert + Herkunft in `cc_course_revision.settings.resolved` | erst mit der nächsten Kursrevision; vorher nur Vorschau |
| **R** Prüfungsregel | Veröffentlichen der Regelversion, zusätzlich Versuchsstart (Snapshot) | Regel: `{v, src}` je Feld; Versuch: wirksamer Wert nach Sperre und Ausgleich in `snapshot.settings` | Entwürfe: sofort in der Vorschau; veröffentlichte Regeln: erst mit neuer Regelversion (E-227); Sperren: ab nächstem Start (E-225) |
| **G** Grant/Einladung | Erzeugen des Grants bzw. der Einladung | z. B. `expires_at`, `claim_policy`, Ablauf der Einladung | nur neue Grants/Einladungen |
| **E** Ereignis | Eintritt des Ereignisses (Abschluss, Bestehen, Versandzeitpunkt) | Ergebnis des Ereignisses (Zertifikat ja/nein, Mail ja/nein) | ab dem nächsten Ereignis, nie rückwirkend |
| **A** Ausstellung | Zertifikatsausstellung | `template_version` und gedruckte Werte (R0-08 §7.2) | nur neue Ausstellungen (E-110) |

**Wirkzeitpunkt bei Klasse L, wenn der Kurs erbt:** Die Kursrevision speichert nur, *dass* er erbt; der Wert kommt live aus der globalen Option. Das ist der einzige Fall, in dem eine globale Änderung einen veröffentlichten Kurs ohne neue Revision ändert. Er betrifft ausschließlich Darstellungs- und Funktionsschalter ohne Bewertungs- oder Nachweiswirkung.

### 1.3 Vorrangregel (Resolver)

`SettingsResolver::resolve(key, context) → {value, source, locked, chain[]}` ist die einzige Quelle für Laufzeit, Snapshot, Vorschau, Hinweis-UI und Bericht (T28-6). `context` = `{course_revision?, course_draft?, activity?, exam_rule_version?, exam_rule_draft?, group?, media?, user?}`.

| Schritt | Regel | `source` |
|---|---|---|
| 1 | Ist der Key sperrbar und gesperrt → globaler Wert | `global_lock` |
| 2 | Sonst der spezifischste vorhandene Override: Prüfungsregel bzw. Activity → Kurs (bzw. Gruppe/Medium) | `exam_rule` · `activity` · `course` · `group` · `media` |
| 3 | Sonst der globale Wert | `global` |
| 4 | Fehlt der globale Schlüssel (kaputte Option) → Schema-Default + Health-Warnung | `schema_default` |
| 5 | **Constraints nach der Auswahl** (ändern den Wert, protokollieren den Grund in `chain`): C-1…C-6 aus §1.5 | unverändert, `chain` ergänzt |
| 6 | Nur `exams.time_limit_minutes` beim Versuchsstart: Nachteilsausgleich (spezifischste gültige Zuordnung Regel > Kurs > Site, `valid_until` ≥ Start) → `limit × factor / 100` bzw. ohne Limit | zusätzlich `accommodation` |

**Klasse R im Detail:** Bei einer **veröffentlichten** Regelversion ist Schritt 2 der materialisierte Wert der Regel (unabhängig davon, ob er beim Veröffentlichen geerbt war). Schritt 1 (Sperre) geht ihm trotzdem vor (E-225). Beim **Entwurf** und beim Veröffentlichen einer neuen Version läuft die volle Kette; Werte mit `src = global` werden dabei neu aufgelöst und im Veröffentlichungs-Diff gezeigt (E-227).

**`exams.passing_ratio_proposal`** ist kein vererbter Wert: Beim Anlegen einer Regel wird der Vorschlag als expliziter Wert in die Regel kopiert (`src = proposal`), danach gilt R0-08 §4.1 (Pflichtfeld, kein Code-Default). Eine Änderung des Vorschlags ändert keine bestehende Regel (SET-062).

### 1.4 Feldtabelle (alle Register-Zeilen mit Scope ≠ global)

Spalten: **Kette** = Ebenen von allgemein nach spezifisch; **Einheit** = was einzeln überschrieben wird (Blatt = einzelner Unterschlüssel eines Objekts); **Werte** = erlaubte Override-Werte; **Sperre** = Admin kann Override verbieten. Speicherorte nach §1.1.

| # | Key | Kette | Einheit | Werte (Override) | Klasse | Sperre | Besonderheit | Tests |
|---|---|---|---|---|---|---|---|---|
| 1 | `general.catalog_indexing` | global → Kurs | Feld | `index`, `noindex` | L | ja | Lektionen, Nachweis-, Einladungs-, Claim-Seiten immer `noindex` (fest, R0-27) | INH-010 |
| 2 | `display.container_width` | global → Kurs | Feld | `theme`, `wide`, `full` | L | ja | – | INH-010 |
| 3 | `display.breadcrumbs` | global → Kurs | Feld | bool | L | ja | – | INH-010 |
| 4 | `learning.course_completion_default` | global → Kurs | Feld | Enum wie global | **K** | ja | `…_and_exam` braucht eine Prüfungs-Activity (Publish-Blocker, E-128); Abschlussregeln sind Teil der Kursrevision (R0-08 §7.1) | INH-011, INH-031 |
| 5 | `learning.mastery_algorithm` | global → Kurs | Feld | registrierte Versionen | **K** | ja | wirkt nur auf neue Antworten (`algorithm_version`); Risiko R | INH-011 |
| 6 | `learning.favorites_enabled` | global → Kurs | Feld | bool | L | ja | Abschalten blendet nur aus; Daten bleiben, Export vollständig | INH-012 |
| 7 | `learning.mistake_training_enabled` | global → Kurs | Feld | bool | L | ja | wie #6 | INH-012 |
| 8 | `learning.feedback` | global → Kurs → Activity (Quiz) | Blatt: `visibility`, `show_correct_answers`, `show_explanations` | wie global | L (Lernmodus, unbewertetes Quiz); **R/Snapshot** (bewertetes Quiz, E-68) | ja (ganzes Objekt) | C-3 (E-20), C-4 (nie Lösungen im laufenden bewerteten Versuch), E-147 | INH-013, INH-014, INH-020 |
| 9 | `exams.max_attempts` | global → Prüfung | Feld | `null` oder 1–100 | **R** | ja | Zählung über Versionen nach E-107/RV-17 | INH-015, INH-022 |
| 10 | `exams.cooldown_seconds` | global → Prüfung | Feld | 0–2 592 000 | **R** | ja | Fortsetzen unberührt | INH-015 |
| 11 | `exams.count_cancelled` | global → Prüfung | Feld | bool | **R** | ja | Ausnahmen `support_void` (E-137) und Rückzug (E-108) fest | INH-015 |
| 12 | `exams.time_limit_minutes` | global → Prüfung (+ Lernender als Modifikator) | Feld | `null` oder 1–1440 | **R** | ja | C-5: Sperre hebt den Nachteilsausgleich nie auf (R0-15 „Pflichtausgleich“) | INH-016, INH-017 |
| 13 | `exams.timer_mode` | global → Prüfung | Feld | `strict`, `practice` | **R** | ja | `practice` nie nachweisfähig (Validator); Sperre auf `practice` bei vorhandenen Nachweisregeln abgelehnt (§3.4 K-4) | INH-018, INH-032 |
| 14 | `exams.late_tolerance_seconds` | global → Prüfung | Feld | 0–600 | **R** | ja | Filter wird auf 0–600 begrenzt und **vor** dem Snapshot angewandt | INH-019 |
| 15 | `exams.shuffle_questions` | global → Prüfung | Feld | typabhängig (feste Variante nur `none`) | **R** | **nein** (typabhängig, eine Sperre wäre für feste Varianten ungültig) | Default je Prüfungsart (T27-3) | INH-015 |
| 16 | `exams.shuffle_answers` | global → Prüfung | Feld | bool | **R** | ja | `pinned` bleibt fest | INH-015 |
| 17 | `exams.feedback` | global → Prüfung | Blatt: `visibility`, `show_score`, `show_correct_answers`, `show_explanations`, `show_correct_answers_when` | wie global | **R** | ja (ganzes Objekt) | C-3, C-4, E-147; keine Kurs-Ebene (E-229) | INH-020, INH-021 |
| 18 | `exams.auto_submit_inactive_days` | global → Prüfung | Feld | `null` oder 1–365 | **R** | ja | nur ohne Zeitlimit (C-6) | INH-015 |
| 19 | `enrollment.self_enrollment` | global → Kurs | Feld | `on`, `off` | L (zum Zeitpunkt der Anmeldeanfrage) | ja | „Einschreibung geschlossen“ (E-108, `_cc_enrollment = closed`) schlägt jeden Wert; erzeugt immer einen Grant (INV-S-11); Risiko R | INH-023, INH-024 |
| 20 | `enrollment.default_access_days` | global → Kurs | Feld | `null` oder 1–3650 | **G** | ja | bestehende Grants unverändert (SET-071) | INH-025 |
| 21 | `enrollment.groups_enabled` | global → Kurs | Feld | bool | L | ja | Abschalten verhindert nur neue Gruppen | INH-026 |
| 22 | `certificates.auto_issue` | global → Kurs | Feld | bool | **E** (Abschluss bzw. Bestehen) | ja | keine Rückwirkung; Zertifikat nur, wenn bei Start und Abgabe ein Grant aktiv war (E-113) | INH-027 |
| 23 | `certificates.base_template` | global → Kurs | Blatt: `issuer_name`, `footer_text`, `logo_id`, `signature_label` | wie global | **A** | ja (ganzes Objekt) | `personal`-Klasse (Export nur mit Opt-in); neue `template_version` je wirksamer Änderung | INH-028 |
| 24 | `pro.reminders` | global → Kurs | Blatt | wie global | **E** (Versandzeitpunkt) | ja (nur mit Pro) | C-2 (E-94); P/R3 | INH-029 |
| 25 | `enrollment.claim_policy` | global → Gruppe | Feld | Enum | **G** (Einladung) | ja | – | INH-030 |
| 26 | `enrollment.invite_valid_days` | global → Gruppe | Feld | 1 … globaler Wert (nur kürzer) | **G** | nein (Monotonie ersetzt Sperre) | – | INH-030 |
| 27 | `pro.groups.advanced_enabled` | global → Gruppe | Feld | bool | L | ja (nur mit Pro) | C-2; P/R3 | INH-029 |
| 28 | `privacy.external_media_mode` | global → Medium | Feld | nur strenger als global: `click_to_load` oder `link_only` (global Default `always_load`, E-45a) | L | nein (Monotonie) | C-1 | INH-033 |
| – | Nachteilsausgleich (`cc_accommodation`) | Site → Kurs → Regel (spezifischste gilt) | Datensatz | Faktor 100–400 % oder ohne Limit | Versuchsstart | – (nicht sperrbar) | nie sichtbar in Ergebnis und Nachweis (E-122) | INH-016, INH-017, INH-034 |

Nicht Teil der Vererbung: reine Global-, System- und Nutzerfelder aus R0-27 §1.3 (z. B. Slugs, Retention, `user.notification_prefs`), WordPress-Erbe (Locale, Zeitzone; R0-27 `WP:`) und Objektfelder ohne globalen Default (R0-27 §1.6, z. B. `passing_rule`).

### 1.5 Constraints nach der Auswahl

| Nr. | Constraint | Wirkung |
|---|---|---|
| **C-1** | Datenschutz-Felder: Override nur strenger als global (SR-3) | Wird der globale Wert strenger als ein Override, gilt der globale Wert (`chain: stricter_global`); der Override bleibt gespeichert und wird in der UI als „ruht“ markiert |
| **C-2** | Pro-Felder (`pro.*`) bei deaktiviertem Add-on: Free-Verhalten; bei abgelaufener Lizenz: eingefrorene Konfiguration wirkt weiter (E-94) | Override bleibt gespeichert, kein Datenverlust |
| **C-3** | `show_explanations` (#8, #17) wirkt nur mit Pro-Entitlement (**E-20**) | effektiv `false` ohne Pro und bei deaktiviertem Add-on (E-269); bei abgelaufener Lizenz eingefroren weiter wirksam (E-268); gespeicherter Wert bleibt; Free-UI zeigt den Schalter nicht (E-100) |
| **C-4** | In einem laufenden bewerteten Versuch nie Lösungen; `always` wird dort wie `after_submit` behandelt (R0-08 §4.5); Lösungen bei offenen Restversuchen nach E-147 | fest, nicht überschreibbar |
| **C-5** | Nachteilsausgleich wird nach einer Sperre auf `time_limit_minutes` trotzdem angewandt | fest |
| **C-6** | Abhängige Felder: `show_*` nur bei `visibility ≠ never`; `auto_submit_inactive_days` nur ohne Zeitlimit | Prüfung auf dem **zusammengeführten** Objekt (Blätter aus verschiedenen Ebenen), nicht je Ebene |

### 1.6 Sperre durch globale Pflicht

- **MUSS:** Nur Felder mit „Sperre: ja“ (§1.4) sind sperrbar. Setzen und Aufheben erfordern #19 und eine Bestätigung mit Auswirkungsliste (§3.3). Audit `settings_lock_set` / `settings_lock_removed` mit Key, Wert, Begründung und Anzahl ruhender Overrides.
- **MUSS:** Eine Sperre löscht keinen Override. Bestehende Overrides **ruhen** und werden nach Aufheben wieder wirksam (zu den Zeitpunkten ihrer Klasse). Neue Overrides auf ein gesperrtes Feld werden abgewiesen (UI deaktiviert, Server `cc_setting_locked`, 422).
- **MUSS:** Der gesperrte Wert ist der jeweils aktuelle globale Wert; ändert der Admin ihn bei bestehender Sperre, gilt §3 für alle Objekte als „erbend“.
- **MUSS:** Preflight beim Setzen: Wäre ein **veröffentlichtes** Objekt mit dem Sperrwert ungültig (Publish-Validator R0-22, z. B. `timer_mode = practice` bei nachweisfähiger Regel), wird die Sperre abgelehnt, mit Liste der Objekte. Es gibt keinen Teilzustand.
- **SOLL:** Kursmanager sehen am Feld „Von der Website festgelegt: <Wert>“ und die Begründung (≤ 200 Zeichen, Klasse `internal`, ohne personenbezogene Daten).
- Sperren gelten nur für Overrides, nie für den Nachteilsausgleich (C-5), laufende Versuche, abgeschlossene Versuche, Zertifikate oder Grants.

### 1.7 UI-Vertrag „erben“ (Kurs-, Activity-, Regeleditor; Wireframes in R0-30)

| Zustand | Anzeige | Bedienung |
|---|---|---|
| **erbt** | Radiogruppe „Standard verwenden“ ausgewählt; Text „erbt von <Ebene>: <Wert>“ (Ebene = Website bzw. Kurs) | Auswahl „Für diesen Kurs festlegen“ öffnet das Feld mit dem geerbten Wert vorbelegt |
| **überschrieben** | „Für diesen Kurs festlegen“ ausgewählt, Feld mit Wert; daneben „Standard wäre: <Wert>“ | „Auf Standard zurücksetzen“ (§4.1) |
| **gesperrt** | Feld schreibgeschützt (`aria-readonly`, nicht nur ausgegraut), Text „Von der Website festgelegt: <Wert>“ + Begründung | keine; Link „Wer kann das ändern?“ (Hilfetext) |
| **ruht** (Override + Sperre bzw. C-1/C-2) | wie gesperrt, zusätzlich „Dein Wert <X> ruht, solange die Vorgabe gilt“ | „Ruhenden Wert entfernen“ |
| **Pro inaktiv** (C-2/C-3) | Feld ausgeblendet (E-100); im Panel „Wirksame Einstellungen“ der Hinweis „gespeicherter Wert inaktiv“ | – |

- **Panel „Wirksame Einstellungen“** (Vorschau, R1-33 DoD) je Kurs und je Prüfungsregel: jede Zeile mit Wert, Herkunft und Klasse („gilt sofort“ / „gilt ab Veröffentlichung“ / „gilt ab nächstem Versuchsstart“). Es nutzt den Resolver (§1.3), nie eine eigene Berechnung.
- **Veröffentlichungs-Diff** (R0-22, R1-26): listet geänderte Overrides und geänderte geerbte Werte der Klassen K und R getrennt („3 Werte ändern sich, weil der Website-Standard geändert wurde“).
- Barrierefreiheit: Zustand immer als Text, nicht nur als Symbol oder Farbe; Radiogruppe mit `fieldset/legend`; Hinweise über `aria-describedby`; Fokus nach Reset auf das Feld (A11Y-Regeln R0-15).

---

## 2. Snapshot-Regeln

### 2.1 Was wann eingefroren wird

| Zeitpunkt | Eingefroren | Ort | Bezug |
|---|---|---|---|
| **Kurs veröffentlichen** | `overrides` (Kurs und Activities, erbende Keys fehlen); für Klasse K zusätzlich `resolved {key: {v, src}}` | `cc_course_revision.settings` (T28-3) | E-104, R0-08 §7.1 |
| **Prüfungsregel veröffentlichen** | alle R-Felder materialisiert `{v, src}`; `src ∈ {exam_rule, global, global_lock, proposal}` | `cc_exam_rule.settings` | R0-08 §7.1, SR-3 |
| **Versuchsstart** (Prüfung, bewertetes Quiz) | wirksame Werte nach Resolver inkl. Sperre, Constraints und Ausgleich: Versuchs-, Zeit-, Kulanz-, Misch-, Feedbackwerte; `settings_provenance {key: {v, src, ref}}`; Ausgleich `{factor_pct | unlimited, scope}` | `cc_exam_session.snapshot` (T28-2) | R0-08 §6.3, R0-15 §2.3, RV-01 |
| **Grant erzeugen** (manuell, Selbstanmeldung, Gruppe) | `expires_at` aus `default_access_days`, Quelle | Grant | SET-071 |
| **Einladung erzeugen** | `claim_policy`, `expires_at` | Einladung | SET-073 |
| **Abschluss / Bestehen** | Entscheidung `auto_issue` (ja/nein) mit Herkunft im Audit | Ereignis, ggf. Zertifikat | E-113 |
| **Zertifikat ausstellen** | `template_version`, gedruckte Texte, Sprache (E-158) | `cc_certificate.snapshot` | R0-08 §7.2, E-110 |
| **Lernantwort** | `algorithm_version`, `revision_id` | `cc_attempt` | R0-08 §7.1 |

### 2.2 Was live wirkt

Nur Klasse L: Katalog-Indexierung, Containerbreite, Breadcrumbs, Favoriten-/Fehlertraining-Schalter, Lernmodus-Feedback und Feedback unbewerteter Quizze, Selbstanmeldung (zum Zeitpunkt der Anfrage), Gruppen-Grundfunktion, Pro-Gruppen, Medienmodus. Außerdem live (nicht vererbt, aber erwähnt): Zugangsprüfung im laufenden Versuch (RV-02), Rechtestatus-Filter für Medien (RV-03), Pro-Entitlement für C-2/C-3.

### 2.3 Regeln

- **SN-1 MUSS:** Der Versuchsstart liest die Settings **einmal** innerhalb der Start-Transaktion (R0-08 §6.2) aus je einer Tab-Option (atomar, R0-27 §2.1). Ein gleichzeitiges Speichern liefert entweder den alten oder den neuen Stand, nie eine Mischung.
- **SN-2 MUSS:** Laufende Versuche lesen nach dem Start keinen Setting-Wert mehr, auch keinen Filter (Abweichung von LB-Verhalten, R0-08 §4.3). Ausnahmen sind nur die Zugangsprüfung (RV-02), die einmalige Zeitzugabe durch Berechtigte (R0-15 §2.3) und die Fristverlängerung nach Wartungsfenster (E-173). Beide letzteren werden als eigene Einträge neben dem Snapshot gespeichert (`deadline_adjustments[]`), der Snapshot und sein Hash bleiben unverändert.
- **SN-3 MUSS (historische Auswertung):** Ergebnisansichten, Berichte, Nachweise und Exporte lesen Einstellungen abgeschlossener Versuche nur aus `settings_provenance`, nie über den Resolver. Ein Berichtsfilter „Versuche mit Zeitlimit X“ arbeitet auf dem Snapshot. Der Ausgleich erscheint nur in A-09 für Berechtigte (E-122).
- **SN-4 MUSS:** Snapshots älterer `format_version` ohne `settings_provenance` bleiben lesbar; Herkunft wird dort als `unknown` angezeigt, nie nachträglich berechnet.
- **SN-5 MUSS:** Kursrevisionen und veröffentlichte Regelversionen werden bei einem Schema-Upgrade **nicht umgeschrieben**. Neue oder umbenannte Override-Schlüssel werden beim Lesen über versionierte Leser übersetzt (R0-08 §7.3 `format_version`); migriert werden nur Entwürfe (`_cc_settings`, Regelentwürfe) im Orchestrator unter Lock (R0-37).
- **SN-6 MUSS:** Während Wartungsfenster W1 (R0-37) werden Settings-, Sperr- und Override-Speichern abgewiesen („Wartung läuft, bitte in Kürze erneut speichern“, Eingaben bleiben im Formular); Versuchsstarts liefern `503 cc_maintenance`, es entsteht also kein Snapshot mit halb migrierten Werten.

---

## 3. Default-Änderung: Zustandsmatrix

### 3.1 Änderungsarten

| ID | Änderung | Auslöser |
|---|---|---|
| Ä-1 | globaler Wert eines **L**-Felds | Admin speichert Tab, Import (R0-27 §2.5), Tab-Reset (§2.4), CLI |
| Ä-2 | globaler Wert eines **K**-Felds | wie Ä-1 |
| Ä-3 | globaler Wert eines **R**-Felds | wie Ä-1 |
| Ä-4 | globaler Wert eines **G**-Felds | wie Ä-1 |
| Ä-5 | globaler Wert eines **E**- oder **A**-Felds | wie Ä-1 |
| Ä-6 | Sperre setzen | Admin (#19) |
| Ä-7 | Sperre aufheben | Admin (#19) |
| Ä-8 | neue Plugin-Version ändert einen **Schema-Default** | Update |
| Ä-9 | Pro-Add-on deaktiviert bzw. Lizenz abgelaufen | Betrieb |
| Ä-10 | Nachteilsausgleich angelegt, geändert, abgelaufen | `cc_manage_accommodations` |

### 3.2 Matrix

Spalten: **erbende Kurse** (Kurse bzw. Regeln ohne Override für den Key), **überschriebene Kurse** (mit Override), **laufende Versuche** (`in_progress`), **abgeschlossene Versuche** (`evaluated`, `cancelled`, `broken`), **Zertifikate**; zusätzlich **Grants/Einladungen**. „unverändert“ ist in jeder Zelle eine MUSS-Aussage.

| Änderung | erbende Kurse | überschriebene Kurse | laufende Versuche | abgeschlossene Versuche | Zertifikate | Grants/Einladungen |
|---|---|---|---|---|---|---|
| **Ä-1** L | neuer Wert ab der nächsten Anfrage, veröffentlichte Revision und Entwurf gleichermaßen | unverändert | unverändert (L-Felder sind nicht prüfungsrelevant; bewertete Quizze haben ihren Snapshot) | unverändert | unverändert | Selbstanmeldung: nur neue Anmeldeanfragen; bestehende Grants unverändert |
| **Ä-2** K | veröffentlichte Revision unverändert; Entwurf zeigt neuen Wert; wirkt ab nächster Veröffentlichung (Diff); ein ungültig werdender Entwurf erhält einen Publish-Blocker, nie die veröffentlichte Revision | unverändert | unverändert | unverändert | unverändert | – |
| **Ä-3** R | Regel**entwürfe**: neuer Wert in Vorschau; **veröffentlichte** Regeln: unverändert bis zur nächsten Regelversion (E-227) | unverändert | unverändert (Snapshot, INV-S-02) | unverändert | unverändert | – |
| **Ä-4** G | neue Grants bzw. Einladungen mit neuem Wert | unverändert | – | – | – | bestehende unverändert |
| **Ä-5** E/A | ab dem nächsten Abschluss bzw. der nächsten Ausstellung; keine Nachausstellung, kein Widerruf | unverändert | Ergebnis wird bei Abgabe nach dem dann gültigen Wert behandelt (Ereignis liegt nach der Änderung) | unverändert, keine Nachausstellung | unverändert (E-110) | – |
| **Ä-6** Sperre setzen | wie Ä-1…Ä-5 je Klasse; für R-Felder ab dem nächsten Versuchsstart auch gegen veröffentlichte Regeln (E-225) | Override **ruht** zu denselben Zeitpunkten; nicht gelöscht | unverändert | unverändert | unverändert | bestehende unverändert |
| **Ä-7** Sperre aufheben | erben weiter den globalen Wert | ruhende Overrides werden zu den Zeitpunkten ihrer Klasse wieder wirksam | unverändert | unverändert | unverändert | bestehende unverändert |
| **Ä-8** Schema-Default | keine Wirkung: Migration schreibt den alten Default in jede Installation ohne gespeicherten Schlüssel (R0-27 §2.3); nur Neuinstallationen erhalten den neuen | unverändert | unverändert | unverändert | unverändert | unverändert |
| **Ä-9** Pro | C-2/C-3: Pro-Felder und `show_explanations` wirken nach E-94; gespeicherte Werte bleiben | wie links | unverändert (Feedback des Snapshots gilt; bei Add-on-Deaktivierung zeigt die Ergebnisansicht Erklärungen nicht mehr, der Snapshot bleibt) | Anzeige wie links; Daten unverändert | unverändert | unverändert |
| **Ä-10** Ausgleich | ab dem nächsten Versuchsstart des Lernenden | ab dem nächsten Versuchsstart | unverändert; nur die einmalige Zeitzugabe ist möglich (SN-2) | unverändert | unverändert | – |

**Zusätzlich unverändert in allen Zeilen:** Kurs- und Activity-Abschlüsse (RV-07, RV-09), Lernstände (RV-05), Kompetenznachweise (RV-12), Audit-Historie.

### 3.3 Hinweis-UI „betrifft N Kurse“

- **MUSS:** Vor dem Speichern eines Tabs, eines Imports, eines Tab-Resets und einer Sperre berechnet ein Preflight je geändertem Key die Auswirkung mit dem Resolver und zeigt sie im Bestätigungsdialog (`role="alertdialog"`, Fokus auf Überschrift, Escape = Abbrechen):

  > **Diese Änderung betrifft `exams.late_tolerance_seconds` (120 → 0 s):**
  > 5 Prüfungsregel-Entwürfe übernehmen den neuen Wert sofort.
  > 8 veröffentlichte Prüfungsregeln erben ihn erst mit ihrer nächsten Version. [Liste]
  > 2 Prüfungsregeln haben einen eigenen Wert und sind nicht betroffen. [Liste]
  > 4 laufende Versuche sind nicht betroffen. Abgeschlossene Versuche und Zertifikate ändern sich nie.

- Zählbasis: Kurse mit veröffentlichter Revision und Kursentwürfe getrennt; archivierte Kurse nicht. Bei Klasse L „sofort“, bei K „ab nächster Veröffentlichung“, bei R wie im Beispiel, bei G/E/A „nur neue …“. Bei einer Sperre zusätzlich „N eigene Werte ruhen“.
- Ohne Betroffene und ohne Risiko-Flag kein Dialog; nach dem Speichern ein Hinweis „gespeichert, betraf N Kurse“.
- Die Listen sind gefiltert auf Objekte, die der Admin sehen darf (R0-07), und zeigen keine Lernendendaten (nur Anzahl laufender Versuche).
- Die Zahlen werden in `settings_saved` bzw. `settings_lock_set` als `impact` auditiert (Delta R0-13).
- **SOLL:** Preflight ≤ 2 s bei den Lastannahmen E-52 (100 Kurse); dafür ein abgeleiteter Index `course_captain_override_index` (Key → Objekt-IDs, neu aufbaubar, kein Autoload; T28-4). Weicht der Index ab (Health-Prüfung), wird er neu aufgebaut; die Wirkung hängt nie vom Index ab, nur der Hinweis.

### 3.4 Konflikte und ihre Auflösung

| ID | Konflikt | Auflösung |
|---|---|---|
| **K-1** | Zwei Admins ändern denselben Tab | `_rev`-Prüfung (R0-27 S-6), kein Überschreiben |
| **K-2** | Kursmanager speichert einen Override, während ein Admin das Feld sperrt | Speichern nach der Sperre: Override wird abgewiesen (`cc_setting_locked`), übrige Felder des Kursentwurfs werden gespeichert; vor der Sperre gespeichert: Override ruht |
| **K-3** | Globale Änderung während einer Kursveröffentlichung | Veröffentlichen liest die globalen K-Werte in seiner Transaktion; es gilt der Stand zum Lesezeitpunkt; der Diff im Dialog wird vor dem Commit gegen `_rev` der Tab-Option geprüft und bei Abweichung neu angezeigt |
| **K-4** | Neuer globaler Wert bzw. Sperrwert wäre für ein Objekt ungültig | L-Felder: Validator verhindert Werte ohne gültige Wirkung (z. B. kein globales Feld, das Kurse ungültig macht); K-Felder: nur Publish-Blocker für den Entwurf; R-Felder: veröffentlichte Regeln unberührt; Sperre: Preflight lehnt ab (§1.6) |
| **K-5** | Kurs „Einschreibung geschlossen“ erbt `self_enrollment = on` | Schließung gewinnt (E-108); Hinweis zählt den Kurs als „nicht betroffen (geschlossen)“ |
| **K-6** | Importiertes Kurspaket enthält Override auf gesperrtes Feld | Override wird gespeichert, ruht; Dry-run-Warnung (§4.3) |
| **K-7** | Pro-Feld bzw. `show_explanations` ohne Pro | C-2/C-3; kein Fehler, kein Datenverlust |
| **K-8** | Override gleich dem aktuellen globalen Wert | bleibt ein Override (bewusste Festlegung, Schutz vor späterer Default-Änderung); die UI zeigt „entspricht derzeit dem Standard“ |

---

## 4. Reset auf Erben, Export und Import

### 4.1 Reset auf Erben

| Aktion | Ebene | Recht | Wirkung | Rückgängig |
|---|---|---|---|---|
| **Feld zurücksetzen** | Kurs-, Activity-, Regelentwurf | M-03 bzw. M-06 | entfernt den Override-Schlüssel; die Vorschau zeigt den geerbten Wert; wirksam zu den Zeitpunkten der Klasse (Kurs und Regel erst mit Veröffentlichung) | Editor-Undo bis zum Speichern |
| **Alle Einstellungen dieses Kurses zurücksetzen** | Kursentwurf inkl. Activities; optional Regelentwürfe | M-03 (+ M-06) | Bestätigung mit Liste der Abweichungen; `_cc_settings_undo` (eine Generation, 30 Tage, wie R0-27 §2.4) | „Rückgängig“ 30 Tage |
| **Sammel-Reset** „N Kurse überschreiben diesen Wert“ | aus dem Hinweis-Dialog bzw. der Feldhilfe des globalen Tabs | #19 **und** M-03 je Kurs (Kurse ohne Recht werden übersprungen und gezählt) | entfernt den Override im Entwurf der gewählten Kurse; Klasse L: Angebot, die Kurse als `patch`-Revision zu veröffentlichen, **nur** wenn der Entwurf sonst unverändert ist; sonst bleibt es beim Entwurf (E-226) | je Kurs `_cc_settings_undo` |
| **Ruhenden Wert entfernen** | wie Feld zurücksetzen | wie dort | entfernt einen ruhenden Override | wie dort |

**Nie betroffen:** veröffentlichte Revisionen (außer über eine neue Veröffentlichung), veröffentlichte Regelversionen, laufende und abgeschlossene Versuche, Grants, Zertifikate, Lernstände. Der globale Tab-Reset (R0-27 §2.4) berührt keine Overrides und keine Sperren außer denen des eigenen Tabs; Sperren werden dabei mit zurückgesetzt und im Dialog aufgeführt (E-230). Audit `course_settings_reset` (Kurs, Keys, Anzahl) bzw. `settings_overrides_bulk_reset`.

### 4.2 Export

- Kurs-Overrides gehören zum **Kurspaket** (R0-10), nicht zum Settings-Export (R0-27 §2.5). Paketfeld (Delta zu R0-10 `courses.settings`, T28-4): `settings: {schema: <int>, overrides: {<key>: <value>}}` je Kurs und je Activity; erbende Keys fehlen.
- Prüfungsregeln: `settings: {<key>: {v, src}}` wie materialisiert; Regelentwürfe nur `overrides`.
- `personal`-Blätter (`certificates.base_template.issuer_name`, `footer_text`, `signature_label`) nur mit Opt-in wie R0-27 §2.5; `logo_id` als Medienverweis über das Medienmanifest. Keine Secrets (es gibt keine Secret-Felder mit Kurs-Scope; der Validator lehnt `secret`-Felder als Override ab).
- Sperren und globale Werte werden **nicht** im Kurspaket exportiert (sie gehören zur Zielsite).
- **Kurs-zu-Kurs-Übernahme** auf derselben Site: Aktion „Einstellungen von Kurs … übernehmen“ (M-03 für Quelle lesend, Ziel schreibend) kopiert die Overrides in den Zielentwurf, mit Diff und Undo wie §4.1. CLI: `wp course-captain course settings export|import <uid>` mit demselben Validator (SR-5).

### 4.3 Import (Dry-run-Pflicht, INV-S-09)

| Fall | Verhalten | Dry-run-Bericht |
|---|---|---|
| unbekannter Key oder Key mit Scope `global` | verworfen | Warnung je Key |
| Wert ungültig für das Ziel-Schema | verworfen → erbt | Warnung mit Quellwert |
| ältere Paket-`schema` | Übersetzung wie SN-5 | Hinweis |
| Datenschutz-Feld lockerer als global der Zielsite | verworfen → erbt (C-1) | Warnung |
| Feld auf der Zielsite gesperrt | gespeichert, ruht (K-6) | Warnung „ruht wegen Website-Vorgabe“ |
| Pro-Feld ohne Pro | gespeichert, inaktiv (C-2) | Hinweis |
| Prüfungsregel mit `src = global` | als **expliziter** Wert übernommen (`src = exam_rule`), damit die Prüfung identisch bleibt (E-228) | Hinweis „weicht vom Standard dieser Website ab“, wo zutreffend |
| Reimport, lokal geänderter Override | lokal gewinnt; Autor entscheidet je Objekt (E-63) | Diff je Key |

Import schreibt nur Entwürfe; wirksam wird alles erst mit „Kurs veröffentlichen“ (E-104). Audit `import_*` (R0-10) mit Anzahl übernommener, verworfener und ruhender Overrides.

---

## 5. Testfälle (Ziel R1-33)

Ebenen wie R0-27: **PU** PHPUnit ohne WP, **PI** PHPUnit mit WP-Testsuite, **PW** Playwright.

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel-Task | weitere |
|---|---|---|---|---|---|---|
| INH-001 | P | Resolver-Tabellentest über alle 28 Zeilen §1.4 × {erbt, Override, gesperrt, ruht} | Wert und `source` nach §1.3; jede Zeile hat Klasse und Sperrflag im Schema | PU | R1-33 | R1-31 |
| INH-002 | P/N | Kurs-Override `max_attempts = null` (unbegrenzt) gegen „erben“ bei globalem Wert 3 | `null` bleibt Override (unbegrenzt), Erben ergibt 3; im Speicher kein Sentinel `inherit` | PU | R1-33 | – |
| INH-003 | N | Override-Key mit Scope `global` (z. B. `general.course_slug`) per Editor-POST, Import und CLI | abgelehnt bzw. verworfen, nichts gespeichert | PI | R1-33 | R1-36 |
| INH-004 | P | Kurs `learning.feedback.visibility = after_submit`, Activity nur `show_correct_answers = false` | Activity: `after_submit` (Kurs) + `false` (Activity) + `show_explanations` (global); C-6 auf dem zusammengeführten Objekt | PU | R1-33 | R1-09 |
| INH-005 | N | Autor ohne M-03 bzw. M-06 setzt Kurs- bzw. Regel-Override; fremder Kurs per ID | 403/404 ohne Existenzleck (R0-07); Wert unverändert | PI | R1-33 | R1-03 |
| INH-006 | N | Kursmanager ohne #19 setzt oder hebt eine Sperre | 403; Sperre unverändert | PI | R1-33 | R1-31 |
| INH-007 | P | Panel „Wirksame Einstellungen“ für Kurs und Regel | jede Zeile Wert, Herkunft, Klasse; Werte identisch mit Resolver-Ausgabe | PW | R1-33 | R1-32 |
| INH-008 | P | Editor mit Tastatur und Screenreader: erben → festlegen → zurücksetzen; gesperrtes Feld | Zustände als Text angesagt, Fokus nach Reset im Feld, gesperrtes Feld `aria-readonly` mit Begründung | PW | R1-33 | R1-19 |
| INH-009 | P | Override auf den Wert gleich global (K-8), danach globale Änderung | Override bleibt, Kurs unverändert; UI zeigte „entspricht derzeit dem Standard“ | PI | R1-33 | – |
| INH-010 | P | Breadcrumbs global aus → an; Kurs A erbt, Kurs B Override „aus“ | A zeigt sofort Breadcrumbs ohne neue Revision; B nicht; Cache-Generation erhöht | PI | R1-33 | R1-16 |
| INH-011 | P | `course_completion_default` global ändern; erbender veröffentlichter Kurs | veröffentlichte Revision unverändert; Entwurf zeigt neuen Wert; Diff beim Veröffentlichen; danach neue Regel nur für offene Fortschritte (RV-07) | PI | R1-33 | R1-27 |
| INH-012 | P | Favoriten global aus, Kurs erbt; danach wieder an | Schalter verschwindet und kommt zurück; Favoriten-Daten unverändert und exportierbar | PI | R1-33 | R1-11 |
| INH-013 | P | Lernmodus-Feedback global `never` während ein Lernender eine Frage bearbeitet | nächste Antwort ohne Feedback (L); kein Fehler | PI | R1-33 | R1-09 |
| INH-014 | P | bewertetes Quiz (E-68) gestartet; danach `learning.feedback` global geändert | Ergebnisansicht des Versuchs nach Snapshot-Feedback | PI | R1-33 | R1-08 |
| INH-015 | P | Regelentwurf erbt `max_attempts`, `cooldown`, `count_cancelled`, `shuffle_*`, `auto_submit_inactive_days`; global geändert; dann Regel veröffentlicht | Entwurfsvorschau zeigt neue Werte; veröffentlichte Regel enthält `{v, src = global}`; spätere globale Änderung ändert die veröffentlichte Regel nicht | PI | R1-33 | R1-08 |
| INH-016 | P | Zeitlimit 60 min, Ausgleich 150 % (Kurs-Scope) und 200 % (Regel-Scope) | Frist 120 min (Regel > Kurs); Snapshot enthält Faktor und Scope; Ergebnis und Nachweis ohne Hinweis auf Ausgleich | PI | R1-33 | R1-20 |
| INH-017 | P | `time_limit_minutes` gesperrt auf 30; Lernender mit Ausgleich „ohne Limit“ | Versuch ohne Limit (C-5) | PI | R1-33 | R1-20 |
| INH-018 | N | Regel mit `producesEvidence` und Override `timer_mode = practice` | Publish-Blocker; kein Snapshot mit `practice` und `evidence_eligible = true` | PI | R1-33 | R1-26 |
| INH-019 | P | Versuch läuft mit Kulanz 120; global auf 0, Filter liefert 900 | laufender Versuch weiter 120; neuer Start mit Wert 0 (Regel erbt nur im Entwurf) bzw. materialisiertem Regelwert; Filterwert auf 600 begrenzt und im Snapshot | PI | R1-33 | R1-20 |
| INH-020 | P | Free ohne Pro: `show_explanations = true` global, Kurs und Regel | keine Erklärungstexte in Lernmodus und Ergebnis (E-20); Werte gespeichert; Schalter in Free-UI nicht sichtbar | PI | R1-33 | R1-09 |
| INH-021 | P | Pro aktiv → deaktiviert → reaktiviert | Erklärungen erscheinen, verschwinden, erscheinen wieder; keine Datenänderung (E-94) | PI | R1-33 | R3-15 |
| INH-022 | P | `max_attempts` 3 → neue Regelversion mit 5 (Override) | Zählung über Versionen nach E-107; Snapshot je Versuch mit eigener Grenze | PI | R1-33 | R1-27 |
| INH-023 | P | `self_enrollment` global an (Dialog „betrifft 4 Kurse“); Kurs A erbt, B `off`, C geschlossen | A anmeldbar mit Grant `self_enrollment`; B und C nicht; Dialog zählte C als „nicht betroffen (geschlossen)“ | PW | R1-33 | R1-13 |
| INH-024 | N | Filter `can_access` liefert `true`, Kurs erbt `self_enrollment = off` | kein Zugang ohne Grant (INV-S-11) | PI | R1-33 | R1-13 |
| INH-025 | P | `default_access_days` 30 → 90, Kurs erbt; zwei Grants vor und nach der Änderung | alter Grant 30 Tage, neuer 90 Tage | PI | R1-33 | R1-13 |
| INH-026 | P | Gruppen global aus, Kurs Override an | Kurs erlaubt neue Gruppen; bestehende Gruppen anderer Kurse bleiben aktiv | PI | R1-33 | R1-13 |
| INH-027 | P/N | `auto_issue` global an, Kurs erbt; drei bereits abgeschlossene Lernende, ein neuer Abschluss; ein Abschluss nach Grant-Verlust | keine Nachausstellung für die drei; neuer Abschluss erhält Zertifikat; ohne Grant bei Abgabe kein automatisches Zertifikat (E-113) | PI | R1-33 | R1-44\* |
| INH-028 | P | Kurs überschreibt `issuer_name`; global `footer_text` geändert | neue Ausstellung: Kurs-Aussteller + neue Fußzeile, neue `template_version`; alte Nachweise unverändert gerendert | PI | R1-33 | R1-35 |
| INH-029 | P | `pro.reminders` und `pro.groups.advanced_enabled` als Kurs-/Gruppen-Override, Pro deaktiviert | Free-Verhalten; Overrides gespeichert; nach Reaktivierung wieder wirksam | PI | R1-33 | R3-15 |
| INH-030 | P/N | Gruppe `invite_valid_days` 7 bei global 30; Gruppe 40 | 7 gespeichert und in neuer Einladung eingefroren; 40 abgelehnt (nur kürzer) | PI | R1-33 | R1-13 |
| INH-031 | P | Sperre `course_completion_default = required_modules_and_exam`; Kurs ohne Prüfung | Preflight: veröffentlichter Kurs bleibt gültig (Klasse K wirkt erst bei Veröffentlichung), Dialog nennt ihn; nächste Veröffentlichung: Publish-Blocker | PI | R1-33 | R1-26 |
| INH-032 | N | Sperre `timer_mode = practice` bei vorhandener Nachweisregel | Sperre abgelehnt mit Liste; kein Teilzustand | PI | R1-33 | – |
| INH-033 | P/N | global `always_load` (Default, E-45a): Medium `click_to_load` gültig; Medium `link_only` bei global `click_to_load`; global danach `link_only`; Medium-Override `click_to_load` | erster Override gültig; Lockerung abgelehnt | PI | R1-33 | R1-17 |
| INH-034 | N | Ausgleich durch Nutzer ohne `cc_manage_accommodations`; Ausgleich nach Start eines Versuchs angelegt | 403; laufender Versuch unverändert, nächster Start mit Ausgleich | PI | R1-33 | R1-20 |
| INH-035 | P | Sperre setzen auf `late_tolerance_seconds = 0` bei drei Regeln mit eigenem Wert 120 (E-225 a) | Dialog „3 eigene Werte ruhen“; neue Starts mit 0, `source = global_lock`; Regeln unverändert gespeichert | PI | R1-33 | R1-20 |
| INH-036 | P | Sperre aus INH-035 aufheben | neue Starts wieder 120; Audit `settings_lock_removed` mit Anzahl reaktivierter Overrides | PI | R1-33 | – |
| INH-037 | P | Kursmanager speichert Override, Admin sperrt danach; umgekehrte Reihenfolge (K-2) | erste Reihenfolge: Override ruht; zweite: Override abgewiesen `cc_setting_locked`, übrige Felder gespeichert | PI | R1-33 | – |
| INH-038 | P | Globales Speichern und Versuchsstart parallel (100 Wiederholungen) | jeder Snapshot entspricht vollständig dem alten oder dem neuen Stand (SN-1) | PI | R1-33 | R1-42 |
| INH-039 | P | Hinweis-Dialog für jede Änderungsart Ä-1…Ä-7 mit bekannten Datenbeständen | Zahlen je Kategorie stimmen exakt mit der danach beobachteten Wirkung überein; `impact` im Audit | PI | R1-33 | R1-38 |
| INH-040 | P | Index `course_captain_override_index` gelöscht bzw. verfälscht | Wirkung unverändert; Health meldet Abweichung; Neuaufbau stellt korrekte Zahlen her | PI | R1-33 | – |
| INH-041 | P | Plugin-Update ändert Schema-Default eines R-Felds (Ä-8); Alt- und Neuinstallation | Alt: alter Wert materialisiert, kein Dialog, keine Wirkung; Neu: neuer Default | PI | R1-33 | R1-36 |
| INH-042 | P | Feld zurücksetzen; „Alle zurücksetzen“ mit Undo; Sammel-Reset über 5 Kurse, davon 1 ohne M-03 und 1 mit weiteren Entwurfsänderungen | Overrides entfernt; Undo stellt her; Sammel-Reset: 1 übersprungen (gezählt), 1 nur Entwurf, 3 als `patch` veröffentlichbar; nie Lerndaten berührt | PW | R1-33 | R1-27 |
| INH-043 | P | globaler Tab-Reset „Prüfungen“ | Overrides aller Kurse und Regeln unverändert; Sperren dieses Tabs im Dialog aufgeführt und zurückgesetzt (E-230) | PI | R1-33 | R1-36 |
| INH-044 | P | Kurspaket-Export mit und ohne Opt-in; Kurs mit erbenden und überschriebenen Keys | nur überschriebene Keys; `personal`-Blätter nur mit Opt-in; keine Sperren, keine globalen Werte | PI | R1-33 | R1-15 |
| INH-045 | P/N | Import mit unbekanntem Key, ungültigem Wert, lockererem Datenschutzwert, gesperrtem Feld, Pro-Feld ohne Pro, Regel mit `src = global` | Dry-run listet jeden Fall nach §4.3; Ergebnis entsprechend; Regelwert explizit übernommen (E-228) | PI | R1-33 | R1-15 |
| INH-046 | P | Reimport, Override lokal geändert | lokal gewinnt, Diff je Key, Autor entscheidet (E-63) | PI | R1-33 | R1-15 |
| INH-047 | P | Bericht „Bestehensquote je Prüfung“ und Ergebnisansicht nach mehreren globalen Änderungen | Werte aus `settings_provenance`, nie neu aufgelöst; alter Snapshot ohne Provenienz zeigt `unknown` (SN-3, SN-4) | PI | R1-33 | R1-12 |
| INH-048 | N | Settings- oder Override-Speichern während W1; Versuchsstart während W1 | Speichern abgewiesen mit Hinweis, Eingaben erhalten; Start `503 cc_maintenance`, kein Snapshot (SN-6) | PI | R1-33 | R1-40 |

Ersetzt bzw. verfeinert: SET-056 (R0-27) wird durch INH-014, INH-019 und INH-038 abgedeckt; SET-062 durch INH-015.

---

## 6. Entscheidungsbedarf (beschlossen)

Die sechs Punkte dieses Dokuments sind entschieden („Von Claude entschieden“, `ENTSCHEIDUNGEN.md` Abschnitt 34) und eingearbeitet:

| Früher | Beschluss | Inhalt | Eingearbeitet in |
|---|---|---|---|
| D28-1 | **E-225** | globale Sperre wirkt auf veröffentlichte Prüfungsregeln ab dem nächsten Versuchsstart; laufende Versuche bleiben unverändert | §1.2 Klasse R, §1.3, §3.2 Ä-6, INH-035 |
| D28-2 | **E-226** | Sammel-Reset von Kurs-Overrides auf „erben“ in R1, nur in Entwürfen, mit Rückgängig | §4.1 |
| D28-3 | **E-227** | neue Prüfungsregel-Version löst geerbte Werte neu auf; Änderung im Veröffentlichungs-Diff | §1.2, §1.3, §3.2 Ä-3 |
| D28-4 | **E-228** | importierte Prüfungsregeln übernehmen Werte explizit, damit die Prüfung gleich bleibt | §4.3, INH-045 |
| D28-5 | **E-229** | keine Kurs-Ebene für `exams.*` in R1; Prüfungseinstellungen nur global und je Prüfungsregel | §1.4 #17; T28-1 in R0-08 §4.5 eingearbeitet |
| D28-6 | **E-230** | Sperren gehören zum Tab-Reset und zum Settings-Export | §4.1, INH-043 |

Weitere hier angewandte Beschlüsse: E-20 (C-3), E-45/E-45a (#28, INH-033), E-50, E-63, E-68, E-94 (C-2), E-100, E-104, E-107, E-108, E-110, E-113, E-122/E-123, E-128, E-147, E-173, E-268/E-269 (C-3).

### 6.1 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-28-1 | Einstellungen für Aufgaben (E-168: Dateigrößen, Allowlist, Textlängen) und für die neuen Fragetypen (E-22/E-69: z. B. Standard „Groß-/Kleinschreibung ignorieren“ beim Lückentext) haben noch keine Register-Zeile in R0-27 und damit keine Vererbungskette | die Beschlüsse legen nur die Funktionen fest; Werte und Scopes fehlen (R0-08 OP-08-3, R0-10 OP-10-2) | R0-27, dann R0-28 §1.4 |
| OP-28-2 | Enum-Werte von `privacy.external_media_mode` (`always_load`, `click_to_load`, `link_only`) mit dem Register R0-27 abgleichen | R0-27 SET-114 führt noch `enum{click_to_load, link_only}` mit Default `click_to_load`; E-45a verlangt Default „immer laden“ | R0-27 |

---

## 7. Technische Deltas (mit Freigabe der Zieldokumente einzuarbeiten)

| Nr. | Delta | Betroffen |
|---|---|---|
| **T28-1** | §4.5 „Einstellung je Kurs, überschreibbar je Quiz/Prüfungsregel“ präzisieren: Kurs → Quiz für `learning.feedback`; global → Prüfungsregel für `exams.feedback` (nach E-229) – **in R0-08 §4.5 eingearbeitet** | R0-08 §4.5 |
| **T28-2** | Snapshot um `settings_provenance {key: {v, src, ref}}`, Ausgleich `{factor_pct | unlimited, scope}` und `deadline_adjustments[]` (außerhalb des Hash) ergänzen | R0-08 §6.3, R0-06 |
| **T28-3** | `cc_course_revision.settings {overrides, resolved}` (K-Felder materialisiert); Regel `settings` als `{key: {v, src}}` | R0-06, R0-08 §7.1 |
| **T28-4** | Paketschema `courses.settings` → `settings {schema, overrides}` je Kurs und Activity, Regeln `{v, src}`; Name „Setting-Override“ klar von `cc_override` (Rechtestatus) trennen; Option `course_captain_override_index` (abgeleitet, kein Autoload) | R0-10, R0-27 §2.1/§2.7 |
| **T28-5** | Tab-Option um `locks {<feld>: {reason, by, since}}` erweitern; Schema-Eintrag um `lockable`, `resolve_class` (L/K/R/G/E/A), `override_unit` (Feld/Blatt) | R0-27 SR-2, SR-3, §2.1 |
| **T28-6** | Resolver als einzige Quelle für Laufzeit, Snapshot, Vorschau, Hinweis und Bericht; öffentliche PHP-API nur lesend (`course_captain_get_setting( $key, $context )`), kein Filter, der nach dem Snapshot greift | R0-19 §4 |
| **T28-7** | Audit-Ereignisse `settings_lock_set`, `settings_lock_removed`, `course_settings_reset`, `settings_overrides_bulk_reset`; `impact` in `settings_saved`; Fehlercode `cc_setting_locked` (422) | R0-13 §4.1, R0-19 Fehlerkatalog |

Weitere Deltas: `ENTSCHEIDUNGEN.md` E-225…E-230 – erledigt (beschlossen); R0-23 §2.1 („Nicht hier geregelt …“) auf dieses Dokument verweisen; R0-24 R1-33 Inputs = R0-28 §1–§5, Testliste INH-001…048; R0-27 SET-056/SET-062 auf INH verweisen; R0-30 Wireframes für §1.7 und §3.3.

---

## 8. Nachweis

- Gelesen: siehe Kopf „Quellen“. Die Feldtabelle §1.4 enthält alle Register-Zeilen aus R0-27 §1.3, deren Scope über `global` hinausgeht (24 Zeilen mit Kurs oder Prüfung, 4 Nebenscopes Gruppe/Medium), plus den Nachteilsausgleich als Lernenden-Ebene. `WP:locale/timezone/date_format` (Scope WP/Nutzer) und `user.notification_prefs` (nur Nutzer) sind WordPress-Erbe bzw. Nutzerwerte ohne Override-Kette und bewusst ausgenommen (§1.4 Schluss).
- Clean Room (E-01): Kein LB-Code übernommen oder zitiert; Verhaltensbezüge nur über R0-08/R0-23.
- Nicht erfolgt: keine Änderung an anderen Dateien, keine Implementierung, kein Testlauf, kein Commit.
