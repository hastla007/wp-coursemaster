# R0-27 · Settings-Register mit Feldverträgen und Benachrichtigungsvertrag

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Keine Implementierung, kein Testlauf. Die DoD verlangt Feld-/UI-Vertrag, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe. · **Stand:** 16.11.2026
**Bezug:** TODO R0-27 (Voraussetzungen R0-07, R0-11, R0-24; DoD: „Alle Kapitel-43-Felder haben Key, Typ, Scope, Default, Capability, Validierung, Edition, Migration, Audit und Tests“), Folgetasks R0-28…R0-31, R0-33, R0-34 sowie R1-21, R1-31, R1-36 (DoR in R0-24 §4.2/§4.3: „nicht ready, R0-27 fehlt“); Entscheidung **E-187** (Benachrichtigungsvertrag hier, aus R0-12 E-187); Konzept Kap. 16.1/16.2, **Kap. 19**, **Kap. 43 vollständig** (Feldquelle).
**Quellen (nur gelesen):** `konzept.md` Kap. 16, 19, 43 (43.1–43.5); `TODO-WETTBEWERBSANALYSE.md` R0-27…R0-31, R1-21, R1-31…R1-36, Z. 619; `docs/r0/ENTSCHEIDUNGEN.md` E-01…E-204 und die „technisch festgelegt“-Absätze (u. a. Optionspräfix `course_captain_`, D-20-03); R0-02 Inventur C §2.3 (INV-C-068…079) und §3.2 (11 Schlüssel in `bp_learning_settings`, 20 weitere Optionen), Inventur D §3.3 (Settings-Felder je Screen, INV-D-036/037/045/047/145), Inventur B INV-B-033/037/069…077; R0-05 GM-159/160/178…181; R0-07 §3.1, §3.5, §4.2, §4.3; R0-08 §3, §4.1, §4.3–4.5, §7; R0-09 §1.2, §7; R0-10 §4.2, §5.2; R0-11 A-02, A-15, A-16, BS-21/22/30, C-16; R0-12 §1.3 (NTF-001…007), ACCESS-09, E-187; R0-13 §2.2, §2.3, §3.1, §4.1, §4.4; R0-15 E-124, D-15-07; R0-16 §1, §2.3, §2.5, §3.2, E-86/E-89; R0-17 §3.4, §3.5, §5.6, §7, E-147/E-150/E-151/E-154, CS-05; R0-19 §1.2, §4.3, §5.4; R0-20 D-20-03/D-20-08/E-197, KOMP-132; R0-22 E-131; R0-24 §4; R0-25 §3.2, Ü-5, MS-R-05. Referenzcode LB `9f37c77` (read-only): `src/Settings/Settings.php`, `src/Mobile/MobileSettings.php`, `src/Admin/Menu.php` (`renderSettings`, `handleSecurity`), `src/Admin/MailStatus.php`.

### Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). Die früheren Fragen D27-1…D27-10 sind als **E-215…E-224** entschieden (§5); die technischen Korrekturen T27-1…T27-8 sind übernommen (§6). Eingearbeitet:

| Beschluss | Wirkung hier |
|---|---|
| **E-01** Clean Room | LB-Schlüssel in der Spalte „LB-Herkunft“ beschreiben nur das Verhalten bzw. die Migration von Werten, keinen übernommenen Code |
| **E-10**, E-239…E-242, E-260…E-263 | **INV-S-07 und SET-119 (alte Fassung „keine Login-Härtung“) sind überholt.** Neuer Tab **Website-Härtung** (11. Tab, E-262, Option `course_captain_settings_hardening`) mit Feldern `hardening.*` (§1.3); vor der ersten Bestätigung durch den Admin ist alles aus (E-260), danach je Funktion `auto` (E-240). K43-48 ist jetzt ein Feld |
| **E-20**, E-95, E-147, E-268, E-269 | Die Erklärungs-Schalter sind **Pro-Felder** (`pro.learning.feedback.show_explanations`, `pro.exams.feedback.show_explanations`); die Free-Objekte `learning.feedback`/`exams.feedback` regeln nur Sichtbarkeit, richtig/falsch und richtige Antwort |
| **E-38**, E-119, E-120, E-182 | Free hat nur die **interne Outbox**; Event-Speicherung ohne Abonnent (30 Tage) und Event-Klassen je Connector gibt es nur mit Pro |
| **E-45/E-45a**, E-246 | `privacy.external_media_mode` = enum{`always_load`, `click_to_load`, `link_only`}, **Default `always_load`**; neues Feld `privacy.consent_category` (Default `marketing`), wirksam nur bei `click_to_load` |
| **E-168**, **E-289** | Text-/Dateiaufgaben in R1; Register-Zeilen `assignments.allowed_file_types` (Default pdf, docx, odt, txt, jpg, png), `assignments.max_file_mb` (20), `assignments.max_files` (5), je Aufgabe nur strenger; Aufbewahrung wie `raw_answers_years_after_course_end` (§1.3, SET-134) |
| **E-290** | Mailtypen N-14 `assignment_submitted` (an zuständige Trainer), N-15 `assignment_returned`, N-16 `assignment_graded` (an Lernende), alle `informational` (§3.2, NTF-035…037) |
| **E-285** | Fehlercodes `ccap_*` (z. B. `ccap_setting_invalid`); Optionen/Transients `course_captain_*` |
| **E-207** | Präfix `ccap_` für CPTs, Caps, Rollen, Cron, Meta, Tabellen; Optionen `course_captain_*` (SR-1, im Text umgestellt) |
| **E-24, E-43, E-44, E-51, E-53, E-81…E-90, E-97, E-100, E-116, E-124, E-136, E-143, E-150…E-152, E-159, E-187, E-197, E-201** | wie angenommen |

**Kernzahlen** (per Skript, §8; erneut ausgeführt am 16.11.2026 nach Einarbeitung aller Beschlüsse einschließlich E-285…E-312)

| Größe | Wert |
|---|---:|
| Felder in Konzept Kap. 43.3 (Tabellenzeilen) | 57 |
| … davon mit Zuordnung in §1.2 (Name identisch, Reihenfolge identisch) | 57 |
| … mit editierbarem Feld (Art enthält „Feld“ oder „Pro“, auch Mischformen mit WP/Inv/Objekt) | 43 |
| … nur WordPress-Erbe | 1 |
| … nur feste Sicherheits-/Fachinvariante ohne Schalter | 5 |
| … Aktion bzw. Objektregel statt gespeichertem Wert | 5 |
| … eigene Seite (Integrationen, Pro-Lizenz) | 3 |
| Register-Zeilen §1.3 | 86 |
| … mit Quelle Kap. 43 (K43-xx) | 52 |
| … nur aus R0-Dokumenten, Entscheidungen oder LB-Inventur | 34 |
| Retention-Unterschlüssel §1.4 | 21 |
| Invarianten INV-S-01…14 (§1.5; INV-S-07 entfällt nach E-10) | 13 |
| LB-Schlüssel `bp_learning_settings` (11) mit Verbleib §1.6 | 11 / 11 |
| weitere LB-Optionen (20) mit Verbleib §2.7 | 20 / 20 |
| Mailtypen R1 (§3.2) | 16 |
| Testfälle SET / NTF (§4) | 91 / 37 |
| … Ziel-Task R1-31 / R1-36 / R1-32 / R1-21 | 63 / 27 / 1 / 37 |
| Entscheidungen E-215…E-224 (beschlossen) | 10 |

**Lesehilfe.** „K43-nn“ = nn-te Zeile der Tabelle in Konzept Kap. 43.3 (Zählung per Skript). „#n“ = primitive Capability Nr. n aus R0-07 §3.1 (z. B. #19 = `ccap_manage_settings`, #24 = `ccap_assign_staff`), „M-nn“ = Meta-Capability aus R0-07 §3.2. Edition/Phase: **F/R1** = Free, Release 1; **P/R3** = Course Captain Pro, R3; **C/R3** = Pro-Connector R3 (E-53). Typ-Kurzschrift: `int 1–100`, `int? …` = darf `null` sein (Bedeutung von `null` steht im Default), `enum{…}`, `set⊆{…}`, `map<…>`, `slug`, `page_id`. Risiko: **–** keine Bestätigung, **R** Bestätigungsdialog mit konkreter Folge (Anzahl Betroffener, wo ermittelbar), **R+** zusätzlich Eingabe des Site-Namens und Backup-Hinweis. Ebene der Tests: **PU** PHPUnit ohne WP, **PI** PHPUnit mit WP-Testsuite, **PW** Playwright, **MS** Multisite-Integration.

---

## 1. Settings-Register

### 1.1 Regeln für jedes Feld (verbindlich für R1-31)

| Nr. | Regel |
|---|---|
| **SR-1** | **Schlüssel.** Ein Register-Key `<tab>.<feld>` liegt in der Option `course_captain_settings_<tab>` unter dem Unterschlüssel `<feld>` (Optionspräfix `course_captain_`, E-207; Präfix `ccap_` gilt für CPTs, Caps, Rollen, Cron, Meta und Tabellen). Weitere Formen: `pro.<tab>.<feld>` → `course_captain_pro_settings_<tab>` (vom Pro-Add-on registriert); `mail_template.<typ>` → `course_captain_mail_template_<typ>`; `user.<name>` → User-Option `course_captain_<name>` (per `update_user_option`, MS-R-05); `WP:<option>` = geerbte WordPress-Option ohne CC-Kopie; `const:<NAME>` = Konstante in `wp-config.php`. Schlüssel sind ab Veröffentlichung stabil (R0-19 §2: Umbenennung = Bruch, nur per Migration). |
| **SR-2** | **Feldvertrag.** Jeder Key hat im Settings-Schema (PHP, eine Quelle für UI, Validierung, Export, CLI, Doku): `type`, `default`, `scope`, `cap`, `validate`, `privacy` (`public`/`internal`/`personal`/`secret`), `risk`, `side_effects` (z. B. `rewrite_flush`, `caps_sync`, `cron_reschedule`, `cache_bump`), `edition`, `since`, `audit`, `export`, `cli`, `help` (Hilfetext, Standard, Geltungsbereich, Abhängigkeit, sichtbarer Effekt; Konzept 43.2 UX-Vertrag). Beispiel unten. |
| **SR-3** | **Scope und Override.** Global = Option. Kurs = Post-Meta `_ccap_settings` am Kursentwurf, beim Veröffentlichen in `ccap_course_revision` eingefroren (R0-08 §7, R0-23). Prüfung = `ccap_exam_rule.settings`, beim Veröffentlichen der Regel **materialisiert** (expliziter Wert, R0-08 §7). Nutzer = User-Option. Ein Override kann nur `inherit` oder einen erlaubten Wert tragen; bei Datenschutz-Feldern nur strenger als global. Vorrang, Snapshot und laufende Versuche spezifiziert **R0-28**; dieses Register legt nur Key, Speicherort und erlaubte Werte fest. |
| **SR-4** | **Capability serverseitig.** Speichern prüft Nonce je Tab (`ccap_settings_save_<tab>`, R0-07 §4.3) **und** die Cap des Felds; ein Tab ist nur sichtbar, wenn die Cap des Tabs vorliegt. Keine Prüfung über Rollennamen (Konzept 43.3 K43-31). |
| **SR-5** | **Sichtbarkeit.** Kein Setting erscheint in REST (`show_in_rest = false`, keine eigene Route; R0-19 §3 „Admin-Oberfläche über admin-post“). WP-CLI `wp course-captain settings get|set|export|import|reset` nutzt denselben Validator und schreibt Audit mit `actor_type = system:cli`; `secret`-Felder sind per CLI nur setzbar, nie lesbar. |
| **SR-6** | **Audit.** „ja“ = Ereignis `settings_saved` (R0-13 §4.1) mit Tab, geänderten Schlüsseln und Vorher/Nachher-Werten für `public`/`internal`; für `personal` nur „geändert“; `secret` nie. Kein Eintrag, wenn sich nichts ändert. |
| **SR-7** | **Default ist Vorschlag.** Defaults aus Kap. 43.3 sind Vorschläge (43.3 „Einordnung“); sie stehen im Schema genau einmal. Ein geänderter Default in einer neuen Version wirkt nie still auf bestehende Installationen (§2.3 Materialisierung). |
| **SR-8** | **Keine Pro-Sperren in Free.** Pro-Felder (`pro.*`) erscheinen nur, wenn das Add-on aktiv ist (E-100). Free-Kernfelder hängen nie an einer Pro-Lizenz (Konzept 43.1). |

Schema-Eintrag (Muster, keine Implementierung):

```php
'learning_area_slug' => [
    'type' => 'slug', 'default' => 'lernen', 'scope' => ['global'],
    'cap' => 'ccap_manage_settings', 'privacy' => 'internal', 'risk' => 'confirm',
    'side_effects' => ['rewrite_flush'], 'edition' => 'free', 'since' => '1.0.0',
    'validate' => [SlugRule::class, ['reserved' => true, 'collision' => true]],
    'audit' => true, 'export' => true, 'cli' => true,
    'help' => 'help.general.learning_area_slug', // Text in/nach init (R0-15)
],
```

### 1.2 Vollständigkeit: Kap.-43.3-Zeilen → Register

„Art“: **Feld** (gespeicherter, editierbarer Wert), **WP** (WordPress-Wert geerbt, keine CC-Option), **Inv** (feste Sicherheits-/Fachregel, kein Schalter; Konzept 43.1 und 43.3 „Einordnung“), **Aktion** (Bedienhandlung, kein gespeicherter Wert), **Objekt** (Regel am Objekt, kein globaler Wert), **Seite** (eigener Bereich außerhalb der Tabs, Konzept 43.2), **Pro** (Pro-Feld). Kombinationen sind erlaubt.

| K43 | Tab (43.3) | Einstellung (43.3) | Art | Register-Key(s) bzw. Vertrag | Test |
|---|---|---|---|---|---|
| K43-01 | Allgemein | Lernbereich-Permalink | Feld | `general.learning_area_slug` | SET-030 |
| K43-02 | Allgemein | Kurskatalog-Seite | Feld | `general.catalog_page_id` | SET-031 |
| K43-03 | Allgemein | Lernenden-Startseite | Feld | `general.learner_home_page_id` | SET-031 |
| K43-04 | Allgemein | Sprache, Zeitzone, Datum | WP+Feld | `WP:locale/timezone/date_format`; `general.use_profile_locale` | SET-023, SET-033 |
| K43-05 | Allgemein | Öffentliche Kursindexierung | Feld | `general.catalog_indexing` | SET-034 |
| K43-06 | Allgemein | Deinstallation/Datenlöschung | Feld | `general.delete_data_on_uninstall` | SET-035 |
| K43-07 | Kursdarstellung | Layout/Breadcrumbs | Feld | `display.container_width`, `display.breadcrumbs` | SET-040 |
| K43-08 | Kursdarstellung | Farben/Typografie | Feld | `display.tokens` | SET-041 |
| K43-09 | Kursdarstellung | Kurskarten/Sortierung | Feld | `display.catalog_sort`, `display.card_elements` | SET-042 |
| K43-10 | Kursdarstellung | Medien/Externe Hosts | Feld | `privacy.external_media_mode`, `privacy.external_media_remember` (im Tab Kursdarstellung verlinkt) | SET-114 |
| K43-11 | Lernen | Fortschrittsregel | Feld | `learning.course_completion_default` | SET-050 |
| K43-12 | Lernen | Fortsetzen/Autosave | Inv | INV-S-01 | SET-054 |
| K43-13 | Lernen | Smarttrainer/Wiederholung | Feld | `learning.mastery_algorithm` | SET-051 |
| K43-14 | Lernen | Favoriten/Fehlertraining | Feld | `learning.favorites_enabled`, `learning.mistake_training_enabled` | SET-052 |
| K43-15 | Lernen | Lektionsfreigabe/Drip | Objekt | Zugangsregel `ccap_access_rule` je Kurs (E-14); Default: keine Regel = sofort | SET-055 |
| K43-16 | Lernen | Kursrevision bei aktiven Lernenden | Inv | INV-S-02 | SET-056 |
| K43-17 | Prüfungen | Versuche | Feld | `exams.max_attempts`, `exams.cooldown_seconds` | SET-060 |
| K43-18 | Prüfungen | Zeitlimit | Feld+Inv | `exams.time_limit_minutes`, `exams.timer_mode`, `exams.late_tolerance_seconds`; INV-S-03 | SET-061 |
| K43-19 | Prüfungen | Bestehensgrenze | Feld | `exams.passing_ratio_proposal` | SET-062 |
| K43-20 | Prüfungen | Fragenreihenfolge | Feld | `exams.shuffle_questions`, `exams.shuffle_answers` | SET-063 |
| K43-21 | Prüfungen | Feedback/Solutions | Feld+Pro | `exams.feedback`, `learning.feedback`; Erklärungen `pro.exams.feedback.show_explanations`, `pro.learning.feedback.show_explanations` (E-20) | SET-064, SET-132 |
| K43-22 | Prüfungen | Abbruch/Wiederaufnahme | Feld+Inv | `exams.count_cancelled`, `exams.auto_submit_inactive_days`; INV-S-01 | SET-065 |
| K43-23 | Prüfungen | Ergebnisaufbewahrung | Feld | `privacy.retention` (exam_attempts_years, raw_answers_years, certificates_years) | SET-112 |
| K43-24 | Einschreibung und Gruppen | Standard-Einschreibung | Feld+Inv | `enrollment.self_enrollment` (aus = nur manuelle Grants); INV-S-11 | SET-070 |
| K43-25 | Einschreibung und Gruppen | Kostenlose Selbstanmeldung | Feld | `enrollment.self_enrollment` | SET-070 |
| K43-26 | Einschreibung und Gruppen | Standard-Zugangsdauer | Feld | `enrollment.default_access_days` | SET-071 |
| K43-27 | Einschreibung und Gruppen | Gruppenplätze/Claim | Feld | `enrollment.groups_enabled`, `enrollment.invite_valid_days`, `enrollment.claim_policy` | SET-072, SET-073 |
| K43-28 | Einschreibung und Gruppen | Erweiterte Gruppenverwaltung | Pro+Inv | `pro.groups.advanced_enabled`; INV-S-12 | SET-076 |
| K43-29 | Einschreibung und Gruppen | Entzug/Sperrung | Aktion | Supportaktionen K-03, K-04, K-12 (R0-13 §3.1) | SET-077 |
| K43-30 | Rollen und Rechte | Standard-Lernendenrolle | WP | `WP:default_role` (Prüfung, keine Kopie) | SET-080 |
| K43-31 | Rollen und Rechte | Trainer-/Autorenrechte | Feld+Objekt | `roles.cap_assignments`; Kurszuweisung `ccap_course_staff` (E-78) | SET-081 |
| K43-32 | Rollen und Rechte | Rechteänderungen | Inv | INV-S-04 | SET-083 |
| K43-33 | Rollen und Rechte | Supportzugriff | Feld+Inv | `roles.cap_assignments` (`ccap_support` ohne #14); Pflichtbegründung E-82 | SET-082 |
| K43-34 | Zertifikate | Automatische Ausstellung | Feld | `certificates.auto_issue` | SET-090 |
| K43-35 | Zertifikate | Verifikation | Feld+Inv | `certificates.holder_name_policy`; INV-S-13 | SET-091 |
| K43-36 | Zertifikate | Designvorlagen/Branding | Feld+Pro | `certificates.base_template` (Designer: Pro, R3) | SET-092 |
| K43-37 | Zertifikate | Widerruf/Korrektur | Aktion | Supportaktion K-15 (R0-13 §3.1) | SET-093 |
| K43-38 | E-Mails | Absender | WP+Feld | WP-Erbe; `emails.from_name`, `emails.from_address` (leer = erben, E-219) | SET-100 |
| K43-39 | E-Mails | Transaktionale Ereignisse | Feld | `emails.types` | SET-102 |
| K43-40 | E-Mails | Optionale Erinnerungen | Pro | `pro.reminders` | SET-104 |
| K43-41 | E-Mails | Templates | Feld | `mail_template.<typ>` | SET-103 |
| K43-42 | E-Mails | Versandfehler | Inv | INV-S-10; §3.5 | NTF-003, NTF-013 |
| K43-43 | Datenschutz und Sicherheit | Datenexport/Löschung | Feld | `privacy.auto_export_on_confirm`, `privacy.auto_erase_on_confirm`, `privacy.keep_evidence_on_erasure` | SET-110, SET-111 |
| K43-44 | Datenschutz und Sicherheit | Retention | Feld | `privacy.retention`, `privacy.backup_max_age_days` | SET-112, SET-113 |
| K43-45 | Datenschutz und Sicherheit | Audit-Logging | Inv+Feld | INV-S-05; `privacy.retention` (audit_log_years) | SET-118 |
| K43-46 | Datenschutz und Sicherheit | Geschützte Medien | Inv+Feld | INV-S-06; `advanced.file_delivery` | SET-122 |
| K43-47 | Datenschutz und Sicherheit | Externe Medien | Feld | `privacy.external_media_mode`, `privacy.consent_category` (E-45a, E-246) | SET-114, SET-131 |
| K43-48 | Datenschutz und Sicherheit | Login-Härtung | Feld | Tab **Website-Härtung**: `hardening.*` (E-10, E-262; früher INV-S-07, überholt) | SET-119, SET-129, SET-130 |
| K43-49 | Integrationen und Betrieb | Plugin-Erkennung | Inv | INV-S-08 | SET-126 |
| K43-50 | Integrationen und Betrieb | WooCommerce | Seite | Integrationen › WooCommerce (C/R3, R3-14, E-53) | SET-127 |
| K43-51 | Integrationen und Betrieb | Voucher Captain | Seite | Integrationen › Voucher Captain (C/R3, R3-14, E-53) | SET-127 |
| K43-52 | Integrationen und Betrieb | Pro-Lizenz | Seite | Pro-Lizenzseite (P/R3, R3-15/R3-18) | SET-128 |
| K43-53 | Integrationen und Betrieb | Import-Dry-run/Rollback | Feld+Inv | `advanced.import_execution`, `advanced.import_error_threshold`; INV-S-09 | SET-121 |
| K43-54 | Integrationen und Betrieb | Queue/Jobs | Feld+Inv | `advanced.cron_stale_warning_minutes`; Retry-Folge fest (R0-19 §5.4) | SET-124 |
| K43-55 | Integrationen und Betrieb | Diagnose/Debug | Feld | `advanced.debug` | SET-120 |
| K43-56 | Integrationen und Betrieb | Einstellungen Import/Export | Aktion | §2.5 | SET-015, SET-016 |
| K43-57 | Integrationen und Betrieb | Zurücksetzen | Aktion+Inv | §2.4; INV-S-14 | SET-014 |

**Tab-Abbildung (Konflikt C-16, R0-11):** Kap. 43.2 gilt (10 Tabs) plus **11. Tab „Website-Härtung“** (E-262). Die Kap.-43.3-Gruppe „Integrationen und Betrieb“ ist kein Tab: K43-49 erscheint im Systemstatus, K43-50…52 sind eigene Seiten, K43-53…55 liegen im Tab **Erweitert**, K43-56/57 sind Aktionen im Kopf jeder Einstellungsseite bzw. unter Erweitert. „Einschreibung und Gruppen“ = Tab **Einschreibung**, „Rollen und Rechte“ = **Rollen & Rechte**, „Datenschutz und Sicherheit“ = **Datenschutz & Sicherheit**.

### 1.3 Register

Spalten nach Auftrag; „Quelle“ ergänzt die Herkunft (K43-Zeile, Entscheidung, R0-Dokument, LB-Inventur). Overrides (Kurs/Prüfung) stehen in „Scope“; ihr Speicherort folgt SR-3.

| Key | Tab | Quelle | Typ | Scope | Default | Capability | Validierung | Edition/Phase | Migration aus LB | Audit | Risiko | Test |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| `general.learning_area_slug` | Allgemein | K43-01, E-197 | slug | global | `lernen` | #19 | `^[a-z0-9][a-z0-9-]{0,48}[a-z0-9]$`; nicht reserviert (WP-Rewrite-Basen, `wp-*`, Feed-/Seitenbasen); keine Kollision mit Seite, Beitrag, CPT, Taxonomie (F4.1, KOMP-132); ≠ `course_slug`; Flush nur nach bestätigtem Speichern | F/R1 | – | ja | R | SET-030 |
| `general.course_slug` | Allgemein | E-197, E-197 | slug | global | `kurs` | #19 | wie oben; zusätzlich ≠ belegte Fremd-CPT-Slugs (`course`, `courses`, `lessons`) | F/R1 | – | ja | R | SET-030 |
| `general.catalog_page_id` | Allgemein | K43-02 | page_id | global | 0 (Setup wählt oder legt an) | #19 + `edit_page(id)` | Seite existiert, `publish`, nicht im Papierkorb; Inhalt wird nie überschrieben; Hinweis, wenn der Katalog-Block fehlt; 0 = Katalog im Lernbereich | F/R1 | – | ja | – | SET-031 |
| `general.learner_home_page_id` | Allgemein | K43-03 | page_id | global | 0 („Mein Lernen“ im Lernbereich) | #19 + `edit_page(id)` | wie oben; fehlt die Seite später, Rückfall auf den Lernbereich und Health-Warnung | F/R1 | – | ja | – | SET-031 |
| `general.learning_area_title` | Allgemein | INV-C-074 | string ≤ 60 | global | leer = Site-Titel | #19 | `sanitize_text_field`, höchstens 60 Zeichen, Altplatzhalter `XXXX` wird leer | F/R1 | `bp_learning_settings.brand_name` | ja | – | SET-032 |
| `general.use_profile_locale` | Allgemein | E-124, E-124 | bool? | global | `null` = an, wenn mehr als eine Sprache installiert ist | #19 | `null`, `true`, `false` | F/R1 | – | ja | – | SET-033 |
| `general.catalog_group_language_versions` | Allgemein | R0-25 Ü-6 | bool | global | false | #19 | – | F/R1 | – | ja | – | SET-033 |
| `WP:locale/timezone/date_format` | Allgemein | K43-04 | WP-Erbe | WP, Nutzer | `get_locale()`, `get_user_locale()`, `wp_timezone()`, `date_format`, `time_format` | WP `manage_options` | keine CC-Option; Schema-Test, dass kein Schlüssel diese Werte dupliziert | F/R1 | LB liest `timezone_string`, `date_format` | nein | – | SET-023 |
| `general.catalog_indexing` | Allgemein | K43-05 | enum{public_catalog, noindex} | global, Kurs | `public_catalog` | #19; Kurs: M-03 | Kurs nur `inherit`/`index`/`noindex`; Lernbereich, Lektionen, Nachweis-, Einladungs- und Claim-Seiten sind immer `noindex` und nie in der Sitemap (KOMP-060; Details R0-33) | F/R1 | – | ja | – | SET-034 |
| `general.delete_data_on_uninstall` | Allgemein | K43-06, E-89 | bool | system | false | #19, nur `administrator` | Einschalten nur mit Eingabe des Site-Namens, Backup-Hinweis und Audit; per CLI nur mit `--confirm=<site>`; Import setzt den Wert nie | F/R1 | `bp_learning_settings.delete_data_on_uninstall` (nie als `true` übernehmen) | ja | R+ | SET-035 |
| `display.container_width` | Kursdarstellung | K43-07 | enum{theme, wide, full} | global, Kurs | `theme` | #19; Kurs: M-03 | Enum; Ausgabe nur innerhalb des `.ccap-`-Containers | F/R1 | – | ja | – | SET-040 |
| `display.breadcrumbs` | Kursdarstellung | K43-07 | bool | global, Kurs | true | #19; Kurs: M-03 | – | F/R1 | – | ja | – | SET-040 |
| `display.tokens` | Kursdarstellung | K43-08, D-15-07 | object{accent, accent_text, font enum{inherit, system}, radius enum{inherit, none, small, medium}} | global | alles `inherit` bzw. leer | #19 | Farbe `^#[0-9a-f]{6}$`; Kontrast ≥ 4,5:1 (Text) bzw. 3:1 (Bedienelemente) gegen Theme-Hintergrund; bei Verstoß Vorschlag des nächsten konformen Werts, Speichern mit Warnung, der Lernbereich nutzt nur konforme Werte; Ausgabe nur als `--ccap-*`-Variablen | F/R1 | (`bp_learning_mobile.primary_color` nur als Validierungsmuster) | ja | – | SET-041 |
| `display.catalog_sort` | Kursdarstellung | K43-09 | enum{manual, title, newest} | global | `manual`, bei Gleichstand Titel | #19 | Enum | F/R1 | – | ja | – | SET-042 |
| `display.card_elements` | Kursdarstellung | K43-09 | set⊆{cover, summary, lesson_count, progress} | global | alle vier | #19 | Teilmenge; leer = nur Titel | F/R1 | – | ja | – | SET-042 |
| `display.require_captions` | Kursdarstellung | E-131, E-131 | bool | global | false | #19 | an = fehlende Untertitel werden Publish-Blocker; der Alt-Text-Blocker gilt immer (E-131) | F/R1 | (`bp_learning_settings.video_captions` wandert in die Video-Activity, §1.6) | ja | R | SET-043 |
| `learning.course_completion_default` | Lernen | K43-11, R0-08 §5.1 | enum{required_modules, required_modules_and_exam} | global, Kurs | `required_modules` | #19; Kurs: M-03 | `…_and_exam` verlangt eine Prüfungs-Activity (Publish-Validator); wirkt nur über eine Kursrevision; Fortschritt und Mastery bleiben getrennt | F/R1 | – | ja | – | SET-050 |
| `learning.mastery_algorithm` | Lernen | K43-13, R0-08 §5.2 | enum{smarttrainer.v1} | global, Kurs | `smarttrainer.v1` | #19; Kurs: M-03 | nur registrierte Algorithmusversionen; Wechsel wirkt nur auf neue Antworten; Hilfetext ohne Lernwirksamkeitsversprechen | F/R1 | – | ja | R | SET-051 |
| `learning.favorites_enabled` | Lernen | K43-14 | bool | global, Kurs | true | #19; Kurs: M-03 | Favoriten sind privat (nie in Berichten oder Traineransicht), im Export, vom Eraser gelöscht | F/R1 | – | ja | – | SET-052 |
| `learning.mistake_training_enabled` | Lernen | K43-14 | bool | global, Kurs | true | #19; Kurs: M-03 | wie oben | F/R1 | – | ja | – | SET-052 |
| `assignments.allowed_file_types` | Lernen | E-168, E-289, R0-10 §4.5 | set<enum{pdf, docx, odt, txt, jpg, png}> | global, Aufgabe | {pdf, docx, odt, txt, jpg, png} | #19; Aufgabe: M-03 | nur Werte der Abgabe-Allowlist R0-10 §4.5 (svg/html/js/php nie); Aufgabe nur Teilmenge (R0-08 §2.5 `file_rules`, eingefroren mit der Kursrevision) | F/R1 | – | ja | R | SET-134 |
| `assignments.max_file_mb` | Lernen | E-289 | int 1–100 | global, Aufgabe | 20 | #19; Aufgabe: M-03 | wirksam ist der kleinste Wert aus PHP-/WP-Limit, globalem und Aufgabenwert; Aufgabe nur kleiner | F/R1 | – | ja | – | SET-134 |
| `assignments.max_files` | Lernen | E-289 | int 1–20 | global, Aufgabe | 5 | #19; Aufgabe: M-03 | je Abgabe (`submission_no`); Aufgabe nur kleiner | F/R1 | – | ja | – | SET-134 |
| `learning.feedback` | Lernen | K43-21, E-20, E-147, R0-08 §4.5 | object{visibility enum{always, after_submit, never}, show_correct_answers bool} | global, Kurs, Quiz | always, true | #19; Kurs: M-03; Quiz: M-06 | `show_*` nur wirksam bei `visibility ≠ never`; gilt für Lernmodus und unbewertete Quizze; Free zeigt richtig/falsch und ggf. die richtige Antwort, nie Erklärungstexte (E-20) | F/R1 | (LB-Feld `learning_explanations` → `pro.learning.feedback.show_explanations`) | ja | – | SET-064 |
| `pro.learning.feedback.show_explanations` | Lernen (Pro) | E-20, E-95 (`course.explanations`), E-268, E-269 | bool | global, Kurs, Quiz | true | #19 + Pro aktiv; Kurs: M-03; Quiz: M-06 | nur wirksam, wenn `learning.feedback.visibility ≠ never`; ohne Pro unsichtbar (E-100); nach Lizenzende eingefroren weiter wirksam, neue Erklärungen nicht anlegbar (E-268, E-270); Texte bleiben in Free gespeichert (E-269) | P/R3 | `bp_learning_settings.learning_explanations` | ja | – | SET-132 |
| `exams.max_attempts` | Prüfungen | K43-17, R0-08 §4.4 | int? 1–100 | global, Prüfung | `null` = unbegrenzt | #19; Prüfung: M-06 | eingefroren beim Versuchsstart; keine Rückwirkung; Zählung alter Versuche nach E-107 | F/R1 | – | ja | – | SET-060 |
| `exams.cooldown_seconds` | Prüfungen | R0-08 §4.4, GM-192 | int 0–2 592 000 | global, Prüfung | 0 | #19; Prüfung: M-06 | Fortsetzen ist nicht betroffen | F/R1 | – | ja | – | SET-060 |
| `exams.count_cancelled` | Prüfungen | K43-22, E-71 | bool | global, Prüfung | true | #19; Prüfung: M-06 | wirkt nur bei gesetztem Limit; Ausnahmen `support_void` (E-137) und Kursrückzug (E-108) fest | F/R1 | – | ja | – | SET-065 |
| `exams.time_limit_minutes` | Prüfungen | K43-18 | int? 1–1440 | global, Prüfung | `null` = aus | #19; Prüfung: M-06 | gespeichert als `time_limit_seconds`; Serverzeit maßgeblich (INV-S-03) | F/R1 | – | ja | – | SET-061 |
| `exams.timer_mode` | Prüfungen | E-123, R0-15 §2.3 | enum{strict, practice} | global, Prüfung | `strict` | #19; Prüfung: M-06 | `practice` ist nicht nachweisfähig (Validator-Blocker bei `producesEvidence`) | F/R1 | – | ja | – | SET-061 |
| `exams.late_tolerance_seconds` | Prüfungen | R0-08 §4.3 | int 0–600 | global, Prüfung | 120 | #19; Prüfung: M-06 | beim Start eingefroren; Filter `course_captain_exam_late_tolerance` wird auf 0–600 begrenzt | F/R1 | Filter `bootspruefung_exam_late_tolerance` | ja | – | SET-061 |
| `exams.passing_ratio_proposal` | Prüfungen | K43-19, R0-08 §4.1 | object{num int, den int} | global | 7/10 | #19 | 0 < num/den ≤ 1, `den` ≤ 1000; **nur Vorschlag**: jede Regel speichert einen expliziten Wert (VAL-073-Quick-Fix); Hilfetext „kein amtlicher Standard“ | F/R1 | (LB-Festwert 0,75 in `examRule` entfällt) | ja | – | SET-062 |
| `exams.shuffle_questions` | Prüfungen | K43-20, R0-08 §3 | enum{none, within_section, all} | global, Prüfung | feste Variante: `none` (fix); Poolprüfung: `within_section` | #19; Prüfung: M-06 | feste Variante nur `none`; Änderung an veröffentlichter Regel nur als neue Regelversion (T27-3) | F/R1 | – | ja | – | SET-063 |
| `exams.shuffle_answers` | Prüfungen | R0-08 §3 | bool | global, Prüfung | true | #19; Prüfung: M-06 | Antworten mit `pinned` bleiben an ihrer Position | F/R1 | – | ja | – | SET-063 |
| `exams.feedback` | Prüfungen | K43-21, E-20, E-147 | object{visibility enum{after_submit, never}, show_score bool, show_correct_answers bool, show_correct_answers_when enum{no_attempts_left_or_passed, after_submit}} | global, Prüfung | after_submit, true, true, `no_attempts_left_or_passed` | #19; Prüfung: M-06 | während eines bewerteten Versuchs nie Lösungen; im Snapshot eingefroren | F/R1 | – | ja | – | SET-064 |
| `pro.exams.feedback.show_explanations` | Prüfungen (Pro) | E-20, E-95, E-147, E-268 | bool | global, Prüfung | true | #19 + Pro aktiv; Prüfung: M-06 | folgt `exams.feedback.show_correct_answers_when`; im Snapshot eingefroren; ohne Pro unsichtbar; nach Lizenzende eingefroren (E-268) | P/R3 | – | ja | – | SET-132 |
| `exams.auto_submit_inactive_days` | Prüfungen | K43-22, E-72 | int? 1–365 | global, Prüfung | `null` = aus | #19; Prüfung: M-06 | nur für Versuche ohne Zeitlimit | F/R1 | – | ja | – | SET-065 |
| `enrollment.self_enrollment` | Einschreibung | K43-24, K43-25, E-84 | bool | global, Kurs | false | #19; Kurs: M-03 + #10 | erzeugt einen Grant (Quelle `self_enrollment`), nie Zugang ohne Grant; Kurs `inherit`/`on`/`off`; globales Einschalten zeigt die Anzahl betroffener veröffentlichter Kurse | F/R1 | `bp_learning_settings.default_access` (`logged_in` → nur nach Bestätigung `true`; `entitled_only` → `false`; T27-5) | ja | R | SET-070 |
| `enrollment.default_access_days` | Einschreibung | K43-26, INV-B-033 | int? 1–3650 | global, Kurs | `null` = unbefristet | #19; Kurs: #10 | gilt für neue manuelle und Selbstanmelde-Grants; bestehende Grants unverändert | F/R1 | (LB-Vorbelegung 182 Tage manuell, Abweichung nach E-55, T27-5) | ja | – | SET-071 |
| `enrollment.groups_enabled` | Einschreibung | K43-27, E-17 | bool | global, Kurs | false (E-222) | #19; Kurs: M-03 | Abschalten verhindert nur neue Gruppen; bestehende Plätze und Grants bleiben | F/R1 | – | ja | R (beim Abschalten, mit Anzahl aktiver Gruppen) | SET-072 |
| `enrollment.invite_valid_days` | Einschreibung | K43-27, R0-09 §1.2, INV-B-037 | int 1–365 | global (Gruppe darf kürzer) | 30 | #19 | gilt für neue Einladungen | F/R1 | LB-Konstante `GroupService::INVITE_DAYS` | ja | – | SET-073 |
| `enrollment.claim_policy` | Einschreibung | K43-27, E-28 | enum{transferable, invited_address_only} | global, Gruppe | `transferable` | #19; Gruppe: M-15 | Hilfetext: Adresse ist kein Identitätsbeweis (R0-09 §1.2) | F/R1 | – | ja | – | SET-073 |
| `enrollment.pending_grant_timeout_hours` | Einschreibung | E-201, E-201 | int 1–168 | global | 24 | #19 | – | F/R1 | – | ja | – | SET-074 |
| `enrollment.source_disabled_policy` | Einschreibung | R0-12 ACCESS-09, E-93 | enum{keep, keep_and_flag} | global | `keep` | #19 | nie automatischer Massenwiderruf; `keep_and_flag` legt eine Prüfliste im Systemstatus an | F/R1 (Wirkung ab R3) | – | ja | – | SET-075 |
| `pro.groups.advanced_enabled` | Einschreibung | K43-28 | bool | global, Gruppe | false | #19 + Pro aktiv | ohne Pro unsichtbar (E-100); Basis-Seat-Rechte unabhängig (INV-S-12) | P/R3 (R3-15) | – | ja | – | SET-076 |
| `WP:default_role` | Rollen & Rechte | K43-30, E-83 | WP-Erbe | WP | `subscriber` | WP `manage_options` | CC legt keine Lernrolle an; Health-Warnung, wenn `default_role` `edit_posts`, `promote_users` oder `ccap_*` hat; nie `administrator` | F/R1 | – | nein | – | SET-080 |
| `roles.cap_assignments` | Rollen & Rechte | K43-31, K43-33, E-83, R0-07 §3.5 | map<role, set<ccap_cap>> (Wirkung in den WP-Rollen; Manifest `course_captain_role_grants`) | global | Aktivierungs-Defaults R0-07 §3.1 (nur `administrator` und vier `ccap_`-Rollen; `ccap_support` ohne #14) | #24 + `promote_users`; #19…#23 nur durch `administrator` | `administrator` nicht änderbar und nicht wählbar; keine Cap über dem eigenen Satz; #14 und #19…#24 an andere Rollen sind Hochrisiko | F/R1 | – | ja | R | SET-081 |
| `certificates.auto_issue` | Zertifikate | K43-34 | bool | global, Kurs | false | #19; Kurs: M-03 + #17 | Kurs-Override nur mit definierter Abschlussregel (Validator); keine Rückwirkung | F/R1 | – | ja | – | SET-090 |
| `certificates.holder_name_policy` | Zertifikate | K43-35, E-97 | enum{never, learner_opt_in} | global | `learner_opt_in` (je Nachweis standardmäßig aus) | #19 | Verifikationsseite zeigt nie E-Mail oder andere PII | F/R1 | – | ja | – | SET-091 |
| `certificates.base_template` | Zertifikate | K43-36, E-96, E-110, R0-25 Ü-5 | object{issuer_name ≤ 120, footer_text ≤ 500 (Sprachvarianten), logo_id int, signature_label ≤ 120} | global, Kurs | Aussteller = Site-Titel, Rest leer | #19; Kurs: M-03 + #17 | Text bereinigt; Logo = Bildanhang ≤ 2 MB; Änderung erzeugt neue `template_version`, ausgestellte Nachweise bleiben unverändert | F/R1 (Designer P/R3) | – | ja | – | SET-092 |
| `emails.from_name` | E-Mails | K43-38, K-08, E-219 | string ≤ 100, `personal` | global | leer = WordPress erben | #19 | kein CR/LF; leer setzt keinen `wp_mail_from_name`-Filter | F/R1 | (LB: `woocommerce_email_from_name`) | ja | – | SET-100 |
| `emails.from_address` | E-Mails | K43-38, E-219 | email oder leer, `personal` | global | leer = WordPress erben | #19 | `is_email`; Warnung bei Domain ≠ Site-Domain (SPF/DMARC-Hinweis aus dem Mailstatus, INV-D-045) | F/R1 | (LB: `woocommerce_email_from_address`) | ja | – | SET-100 |
| `emails.footer_text` | E-Mails | K-08, INV-C-083 | text ≤ 1000 (Sprachvarianten), `personal` | global | leer | #19 | Mail-kses-Allowlist (§3.10); genau einmal je Mail | F/R1 | Filter `bootspruefung_mail_footer` (Woo-Adresse) | ja | – | SET-100 |
| `emails.send_format` | E-Mails | Kap. 19 | enum{multipart, text_only} | global | `multipart` | #19 | – | F/R1 | – | ja | – | SET-101 |
| `emails.types` | E-Mails | K43-39, §3.2 | map<typ, {enabled bool}> | global | je Typ laut §3.2 | #19 | Typen der Klasse `required` sind nicht abschaltbar (Server lehnt ab) | F/R1 | – | ja | R (beim Abschalten) | SET-102 |
| `mail_template.<typ>` | E-Mails | K43-41, E-159, R0-25 Ü-5 | map<locale, {subject ≤ 200, body_html ≤ 20 000, body_text ≤ 20 000, rev int}> | global | Werkstext (nichts gespeichert bis zur Anpassung) | #19 | nur installierte Locales; Platzhalter-Allowlist je Typ; Mail-kses; Vorschau und Testversand | F/R1 | (LB: Texte im Code) | ja (Hash vorher/nachher) | – | SET-103 |
| `pro.reminders` | E-Mails | K43-40, E-24, INV-B-069…076 | object{enabled bool, days int 1–90, audience enum{paying_buyers}, frequency_cap_per_week int 1–7, quiet_hours {from, to}} | global, Kurs | aus; 14; `paying_buyers`; 1; 21–8 Uhr Site-Zeit | #19 + Pro aktiv | Opt-out-Pflicht (Klasse `marketing`); Abmeldeseite bleibt nach Lizenzende erreichbar (R0-11) | P/R3 (R3-15) | `bp_learning_settings.expiry_reminder_enabled`, `…_days` | ja | – | SET-104 |
| `privacy.auto_export_on_confirm` | Datenschutz & Sicherheit | K43-43, INV-C-077, E-223 | bool | system | true | #19 | – | F/R1 | `bp_learning_settings.privacy_requests_auto` (Exportteil) | ja | – | SET-110 |
| `privacy.auto_erase_on_confirm` | Datenschutz & Sicherheit | K43-43, E-43 | bool | system | false | #19 | Konto wird erst nach WP-Bestätigung gelöscht; Filter `course_captain_account_deletion_protected` | F/R1 | `privacy_requests_auto` (Löschteil, nie als `true` übernehmen) | ja | R | SET-110 |
| `privacy.keep_evidence_on_erasure` | Datenschutz & Sicherheit | K43-43, E-150 | object{enabled bool, reason text ≤ 500} | system | aus | #19 | Begründung Pflicht, wenn an; erscheint im Eraser-Ergebnis | F/R1 | – | ja | R | SET-111 |
| `privacy.retention` | Datenschutz & Sicherheit | K43-44, K43-23, K43-45, E-44, E-51, E-116, E-119, E-143, E-151, E-154, E-243…E-245 | object (21 Unterschlüssel, §1.4) | global | §1.4 | #19 | Bereiche §1.4; Verkleinern zeigt vor dem Speichern die Anzahl betroffener Datensätze (PRIV-008); Wirkung im nächsten Lauf | F/R1 | LB-Konstanten/Filter (`PersonalData.php:100-130`, Import 14/180) | ja | R (beim Verkleinern) | SET-112 |
| `privacy.backup_max_age_days` | Datenschutz & Sicherheit | K43-44, R0-17 §5.6, E-151 | int 1–3650 | system | 90 | #19 | nur Information; Tombstone-Frist = Wert + 30 Tage | F/R1 | – | ja | – | SET-113 |
| `privacy.external_media_mode` | Datenschutz & Sicherheit | K43-10, K43-47, E-45, E-45a | enum{always_load, click_to_load, link_only} | global, Medium | **`always_load`** (E-45a) | #19; Medium: nur strenger | `always_load` bettet direkt ein (bewusst akzeptiertes Betreiberrisiko, R0-17 §6.5); Hilfetext nennt die Übertragung an den Dienst; Hinweis im Setup (R0-30); Offenlegung der Dienste in der readme; Hosts per Filter `course_captain_external_media_hosts` | F/R1 | (`kurswasser_consent_external_hosts`, K-13) | ja | – | SET-114 |
| `privacy.consent_category` | Datenschutz & Sicherheit | E-246, R0-10 §5.3, R0-31 | enum{marketing, statistics, preferences, functional} | global | `marketing` | #19 | nur WP-Consent-API-Kategorien; wirkt nur bei `external_media_mode = click_to_load` (E-246) | F/R1 | – | ja | R bei `functional` | SET-131 |
| `privacy.external_media_remember` | Datenschutz & Sicherheit | K43-10, R0-10 §5.2, MED-023 | bool | global | true | #19 | nur wirksam bei `click_to_load`; Kästchen nie vorangehakt; aus = kein Kästchen „immer laden“, gespeicherte Browserwahl wird ignoriert | F/R1 | – | ja | – | SET-114 |
| `privacy.trusted_proxies` | Datenschutz & Sicherheit | INV-D-037, INV-D-145, K-25 | list<IP oder CIDR> ≤ 50 | system | leer (nur `REMOTE_ADDR`) | #19, nur `administrator` | IPv4/IPv6 bzw. CIDR gültig und normalisiert; `0.0.0.0/0` und `::/0` abgelehnt | F/R1 | `bp_learning_mobile.trusted_proxies` | ja | R | SET-115 |
| `privacy.show_avatars` | Datenschutz & Sicherheit | R0-17 CS-05 | bool | global | false | #19 | an = Hinweis auf die Gravatar-Übertragung | F/R1 | – | ja | – | SET-116 |
| `privacy.csp_mode` | Datenschutz & Sicherheit | R0-17 §3.5, E-224 | enum{enforce, report_only} | global | `enforce` | #19 | gilt für die CSP der eigenen Antworten; website-weite Header liegen im Tab Website-Härtung (`hardening.site_headers`) | F/R1 | – | ja | R (`report_only`) | SET-117 |
| `hardening.login_limit` | Website-Härtung | K43-48, E-10, E-240, E-260, E-261 | enum{auto, force, off} (UI: Kontrollkästchen an = `auto`, aus = `off`; `force` nur per CLI/erweiterter Ansicht, R0-30) | system | vor erster Bestätigung wirksam `off`; Setup schlägt `auto` vor, außer bei erkanntem Security-Plugin (E-260) | #19 **und** `manage_options` | `auto` zieht sich bei erkanntem Security-Plugin zurück (E-240); `force`/`off` nur mit Bestätigung; Grenzwerte nur per Filter `course_captain_hardening_limits` (R0-31 §3.4); fail-closed (E-46) | F/R1 (R1-45) | LB LoginGuard (nur Verhalten, E-01) | ja | R bei `force` und `off` | SET-119, SET-129 |
| `hardening.reset_limit` | Website-Härtung | E-10, E-261 | enum{auto, force, off} | system | wie `login_limit` | wie oben | gleiche Antwort für unbekannte Konten; fail-closed | F/R1 | – | ja | R wie oben | SET-129 |
| `hardening.registration_limit` | Website-Härtung | E-241 | enum{auto, force, off} | system | wie `login_limit`; wirksam nur bei `users_can_register` | wie oben | fail-closed | F/R1 | – | ja | – | SET-129 |
| `hardening.known_device` | Website-Härtung | E-241, E-263 | bool | system | true (wirksam nur mit `login_limit`) | wie oben | bekannte Geräte dürfen bei Adress-Sperre und Zählerausfall weiter einloggen (E-46, E-263) | F/R1 | – | ja | – | SET-130 |
| `hardening.xmlrpc` | Website-Härtung | E-10, E-261 | enum{auto, force, off} | system | wie `login_limit` | wie oben | `auto` ruht bei erkanntem Plugin, das XML-RPC schon regelt | F/R1 | – | ja | R | SET-129 |
| `hardening.site_headers` | Website-Härtung | E-10, E-261 | enum{auto, force, off} (4 Header, Status je Header) | system | wie `login_limit` | wie oben | setzt keinen Header, den Server oder Plugin schon senden | F/R1 | – | ja | – | SET-129 |
| `hardening.confirmed_at` | Website-Härtung | E-260 | UTC-Zeit oder null | system | null (= alles aus) | wie oben | wird beim ersten Speichern im Assistenten oder Tab gesetzt (Audit); erst danach wirken die Werte | F/R1 | – | ja | – | SET-129 |
| `const:COURSE_CAPTAIN_HARDENING` | Website-Härtung (Anzeige) | E-242, E-263 | `off` | system | nicht gesetzt | `wp-config.php` | Notausschalter; Formular dann nur lesend; zusätzlich WP-CLI `hardening disable` bzw. `hardening unlock` | F/R1 | – | nein | – | SET-130 |
| `advanced.debug` | Erweitert | K43-55, E-143, R0-13 §2.3 | object{until UTC-Zeit oder null} (Eingabe: Dauer 15 min–24 h) | system | aus; Dauer 2 h | #19 (UI), #23 (CLI) | Ablauf erzwungen (jede Anfrage und Stundenlauf); Redaction; Dateien 7 Tage | F/R1 | – | ja | – | SET-120 |
| `advanced.import_execution` | Erweitert | K43-53, E-177 | enum{background, browser} | global | `background` | #19 | WP-CLI bleibt immer möglich; Wechsel während eines Laufs erlaubt | F/R1 | – | ja | – | SET-121 |
| `advanced.import_error_threshold` | Erweitert | K43-53, E-180 | object{min_errors int 1–100 000, ratio_percent 0,1–100} | global | 100 / 2 % | #19 | – | F/R1 | – | ja | – | SET-121 |
| `advanced.file_delivery` | Erweitert | K43-46, R0-10 §4.2 | enum{php, x_sendfile, x_accel_redirect} | system | `php` | #19 | Umschalten nur nach erfolgreicher Selbstprüfung (Loopback auf eine geschützte Testdatei), sonst abgelehnt | F/R1 | Filter `bootspruefung_protected_file_sendfile` | ja | R | SET-122 |
| `advanced.media_delete_grace_days` | Erweitert | R0-16 I-3 | int 1–90 | system | 7 | #19 | – | F/R1 | – | ja | – | SET-123 |
| `advanced.cron_stale_warning_minutes` | Erweitert | K43-54, INV-C-055 | int 60–1440 | system | 180 | #19 | – | F/R1 | LB-Monitor fest 3 h | ja | – | SET-124 |
| `advanced.rate_limits` | Erweitert | R0-17 §3.4, E-152, E-221 | Anzeige (Änderung nur per Filter `course_captain_rate_limits`) | system | Tabelle R0-17 §3.4 | Anzeige #19 | Werte < 1 verworfen; `fail_mode` für `group_join`, `code_redeem`, `verify`, `privacy` nicht änderbar | F/R1 | `bootspruefung_*`-Filter, LoginGuard-Grenzen | nein | – | SET-125 |
| `const:COURSE_CAPTAIN_OUTBOUND` | Erweitert (Anzeige) | E-86, E-86 | enum{on, catch, off} | system | `on` in `production`, sonst `catch` | `wp-config.php` | keine Option; Anzeige in Systemstatus und Admin-Leiste | F/R1 | (LB-MU-Plugin `staging-mail.php`) | nein | – | NTF-006 |
| `const:COURSE_CAPTAIN_MAIL_CATCH_ADDRESS` | Erweitert (Anzeige) | §3.6 | email | system | leer = `admin_email` | `wp-config.php` | `is_email`; ungültig → Modus `off` mit Health-Warnung | F/R1 | – | nein | – | NTF-017 |
| `user.notification_prefs` | Konto (L-20) | Kap. 19, NTF-004 | object{optout set⊆{informational-Typen}, updated_at} | Nutzer | leer | eigenes Konto oder signierter Abmeldelink | nur Typen der Klasse `informational` | F/R1 | `bp_expiry_reminder_optout` (User-Meta, Pro-Teil) | ja (`notification_optout_changed`, ohne Inhalt) | – | NTF-015 |

### 1.4 Retention-Unterschlüssel `privacy.retention`

Werte sind änderbare Defaults; rechtliche Bestätigung durch den Betreiber (E-44, E-51). Prüfung in R0-31.

| Unterschlüssel | Default | Bereich | Quelle | Job |
|---|---|---|---|---|
| `audit_log_years` | 4 | 1–10 | E-44, R0-13 §4.4 | stündlich, ≤ 5 000 je Lauf |
| `grant_history_deleted_accounts_years` | 10 (pseudonymisiert) | 1–30 | E-44 | täglich, ≤ 1 000 je Lauf |
| `import_log_days` | 180 | 7–3650 | E-44 | stündlich |
| `import_unchanged_days` | 14 | 1–365 | E-44 | stündlich |
| `import_keep_packages` | 2 | 1–20 | R0-19 §4.3 | stündlich |
| `inbox_days` | 90 | 7–3650 | E-116 | stündlich |
| `outbox_event_days` | 30 | 7–365 | E-119, E-215: Events ohne Abonnent – nur mit Pro (E-38); in Free werden zugestellte Einträge der internen Outbox nach 90 Tagen gelöscht (E-215, wie `inbox_days`) | stündlich |
| `notification_log_days` | 90 | 7–3650 | §3.11, E-215 | täglich |
| `error_log_days` | 30 | 1–365 | E-143 | stündlich |
| `debug_file_days` | 7 | 1–30 | E-143 | stündlich |
| `exam_attempts_years` | 10 | 1–30 | E-51 | täglich |
| `raw_answers_years_after_course_end` | 2 | 0–30 | E-51 | täglich |
| `certificates_years` | 10 | 1–50 | E-51 | täglich |
| `inactive_learning_data_years` | `null` = aus | 1–20 | E-154 (Vorschau Pflicht) | täglich |
| `invite_email_days_after_expiry` | 30 | 1–365 | R0-09 §7 | stündlich |
| `held_mail_days` | 30 | 1–90 | E-151 | täglich |
| `learning_progress` | `until_account_deletion` (fest) | – | E-51 | – |
| `ended_grants_years` | 10 | 1–30 | E-243 (R0-31 §2.2) | täglich, ≤ 1 000 |
| `removed_seats_days` | 365 | 30–3650 | E-243 | täglich |
| `closed_groups_years` | 3 | 1–30 | E-243 | täglich |
| `lifted_locks_days` | 30 | 1–365 | E-243 | täglich |

Damit hat `privacy.retention` **21** Unterschlüssel. Fristbeginn „Kursende“ = späterer von Ende des letzten aktiven Grants und letzter Aktivität im Kurs (E-244). Verlängern über den Default nur mit Pflichtbegründung im Audit (E-245).

Fest und nicht im Register: personenbezogene Exportdateien 24 h (E-243), Reset-Journal 30 Tage (E-138), Idempotenzschlüssel 7 Tage (R0-19 §2.1), Tombstone-Frist abgeleitet aus `backup_max_age_days` + 30 (E-151).

### 1.5 Invarianten statt Schalter (Konzept 43.1 „systemkritische Invarianten“)

| ID | Invariante | K43 | Test |
|---|---|---|---|
| INV-S-01 | Fortsetzen und Autosave sind immer aktiv; Writes idempotent (`request_key`), Wiederaufnahme sicher | K43-12, K43-22 | SET-054 |
| INV-S-02 | Laufende Versuche und ausgestellte Nachweise behalten ihren Snapshot; Kursänderungen nur über Revision mit Vorschau (E-103…E-107) | K43-16 | SET-056 |
| INV-S-03 | Zeitlimit nach Serverzeit (UTC); Timeout explizit (`end_reason = expired`) | K43-18 | SET-061 |
| INV-S-04 | Rechteänderungen nur mit Nonce, Cap, Audit; IDOR-geschützt (RBAC-091) | K43-32 | SET-083 |
| INV-S-05 | Audit für Sicherheits- und Adminaktionen ist nicht abschaltbar; Redaction fest | K43-45 | SET-118 |
| INV-S-06 | Geschützte Medien: private Ablage plus serverseitige Prüfung, nicht abschaltbar | K43-46 | SET-122 |
| ~~INV-S-07~~ | **Entfällt** (überholt durch Beschluss E-10: Website-Härtung im Kern, Felder `hardening.*`). Die Nummer wird nicht neu vergeben | K43-48 | SET-119 (neu gefasst) |
| INV-S-08 | Plugin-Erkennung nur lesend; keine stille Aktivierung (E-185) | K43-49 | SET-126 |
| INV-S-09 | Import nur mit Dry-run; Rollback verfügbar | K43-53 | SET-121 |
| INV-S-10 | Versandfehler: Retry begrenzt, kein PII-Logging (§3.5) | K43-42 | NTF-013 |
| INV-S-11 | Kein Zugang ohne Grant; Filter darf nur einschränken (E-84) | K43-24 | SET-070 |
| INV-S-12 | Basis-Seat-/Claim-Rechte bestehen ohne Pro und nach Pro-Lizenzende (Konzept 43.1) | K43-28 | SET-076 |
| INV-S-13 | Verifikation und Widerrufsstatus immer abrufbar, unabhängig von Pro (Konzept 43.1) | K43-35 | SET-091 |
| INV-S-14 | Kein globales „Alles zurücksetzen“; Reset nie für Lernstände oder Grants (Konzept 43.2) | K43-57 | SET-014 |

### 1.6 Bewusst nicht im Register

| Gegenstand | Verbleib | Beleg |
|---|---|---|
| LB `radio_voice_output`, `exam_guide_enabled` | entfällt (fachgebunden) | INV-C-071, INV-C-073 |
| LB `price_note` | Woo-Adapter (P/R3, R3-14) | INV-C-075, E-33 |
| LB `update_check_enabled` | entfällt (Updates über WordPress.org bzw. License Captain) | INV-C-079, E-09 |
| LB `video_captions` | Activity-Setting der Video-Activity (R0-08 §2), kein globales Feld | INV-C-078 |
| LB `expiry_reminder_enabled`, `expiry_reminder_days` | `pro.reminders` (P/R3) | E-24, INV-C-076 |
| LB `default_access`, `brand_name`, `delete_data_on_uninstall`, `privacy_requests_auto` | siehe §1.3 | INV-C-070/072/074/077 |
| LB `bp_learning_mobile` (App an/aus, Tenant, Farbe, Support-Mail, Web-Session, App-IDs, Mindestversionen, Store-Links, Fingerabdrücke) | entfällt mit der Token-API (R4-Pool); nur `trusted_proxies` wandert in den Kern | E-05, INV-D-147, BS-28 |
| Objektfelder ohne globalen Default: `passing_rule` (Pflicht, R0-08 §4.1), Teile/Blueprints, `pinned`, `exam_only` (E-146), `competency_validity_days`, `counts_as_evidence`, `open_text_in_score`, Teilpunkt-Strategie (E-77), Video-Abschlussregel (E-76), Nachteilsausgleich je Lernendem (E-122), Namensfreigabe je Nachweis (E-97), Event-Klassen je Connector (E-120), Staff-Zuweisung, Drip-Regeln | Verträge in R0-08, R0-09, R0-15, R0-19; Speicherung am Objekt | – |
| Feste Werte: Grace 5 s, Start-Limit 40/h (nur Filter), Lernmodus-Sperre 24 h ohne Zeitlimit, Outbox-Backoff, Aufschub von Auto-Updates (E-174), Fristverlängerung bei Migration (E-173), Smarttrainer-v1-Parameter | Code bzw. Filter, kein Setting | R0-08, R0-19, R0-37 |
| Integrations-Secrets, Monitor-Schlüssel, `COURSE_CAPTAIN_SECRET_KEY` | Konstanten bzw. verschlüsselt in `course_captain_integrations`, eigene Seite (E-87) | R0-16 §3.2 |

---

## 2. Speicherung

### 2.1 Eine Option je Tab

**Entscheidung (technisch):** eine Option je Tab, keine Einzeloptionen je Feld, keine Gesamtoption wie LB.

| Variante | Für | Gegen |
|---|---|---|
| Gesamtoption (LB `bp_learning_settings`) | eine Abfrage | Tab-Speichern schreibt alle Tabs; Teilformular-Bug (INV-C-069); Konflikte zwischen Admins in verschiedenen Tabs; alles oder nichts beim Autoload |
| Einzeloptionen je Feld | feinste Granularität | etwa 70 Zeilen in `wp_options`, viele Autoload-Einträge; Tab-Speichern nicht atomar; Reset je Tab braucht Schleifen |
| **eine Option je Tab** | Speichern und Reset je Tab atomar (ein `update_option`); Autoload je Tab steuerbar; passt zum UX-Vertrag 43.2 | Felder mit anderem Lesemuster brauchen ggf. eine eigene Option (Mailvorlagen) |

| Option | Inhalt | Autoload | Budget | Export |
|---|---|---|---|---|
| `course_captain_settings_version` | Version des Registers (int) | ja | – | Metadaten |
| `course_captain_settings_general` | Tab Allgemein | ja | ≤ 1 KB | ja |
| `course_captain_settings_display` | Tab Kursdarstellung | ja | ≤ 1 KB | ja |
| `course_captain_settings_learning` | Tab Lernen | ja | ≤ 0,5 KB | ja |
| `course_captain_settings_enrollment` | Tab Einschreibung | ja | ≤ 0,5 KB | ja |
| `course_captain_settings_advanced` | Tab Erweitert (Debug-Ablauf und Dateiauslieferung werden je Anfrage gelesen) | ja | ≤ 0,5 KB | ja |
| `course_captain_settings_exams` | Tab Prüfungen (nur beim Anlegen/Veröffentlichen von Regeln gelesen) | nein | ≤ 1 KB | ja |
| `course_captain_settings_roles` | Tab Rollen & Rechte (Sollzustand; Wirkung in `wp_user_roles`) | nein | ≤ 2 KB | nur mit Opt-in (§2.5) |
| `course_captain_settings_certificates` | Tab Zertifikate | nein | ≤ 2 KB | ja |
| `course_captain_settings_emails` | Tab E-Mails | nein | ≤ 4 KB | ja, `personal` nur mit Opt-in |
| `course_captain_settings_privacy` | Tab Datenschutz & Sicherheit | nein | ≤ 2 KB | ja |
| `course_captain_settings_hardening` | Tab Website-Härtung (E-262; wird bei jeder Anfrage gelesen) | ja | ≤ 0,5 KB | ja, Import feldweise bestätigungspflichtig (R0-31) |
| `course_captain_mail_template_<typ>` | eine Option je Mailtyp mit allen Sprachvarianten | nein | ≤ 64 KB | nur mit Opt-in |
| `course_captain_settings_undo_<tab>` | Stand vor dem letzten Reset bzw. Import (eine Generation, 30 Tage) | nein | wie Tab | nein |
| `course_captain_role_grants` | Manifest der durch CC vergebenen Caps (für Reset, Restore, Uninstall) | nein | ≤ 2 KB | nein |
| `course_captain_pro_settings_<tab>` | Pro-Felder (Pro registriert sie über den Slot `settingsTabs`, R0-19 §4.2) | Pro-Regel | – | Pro-Regel |

**Autoload-Regeln:** (1) autoload nur, was auf normalen Front- oder Lernbereich-Anfragen gelesen wird; (2) alle autoloaded CC-Optionen zusammen ≤ 4 KB bei Maximalwerten (Test SET-009); (3) explizites Flag bei `add_option`/`update_option`; bestehende Installationen werden per Migration mit `wp_set_option_autoload_values()` korrigiert (LB-Muster INV-C-031, hier aus dem Register statt fest verdrahtet); (4) Systemzustand mit Sperren, Fehlern und Historien ist nie autoload, außer `course_captain_db_version` und `course_captain_cache_generation`.

**Aufbau einer Tab-Option:**

```json
{ "_schema": 1, "_rev": 7, "_updated_at": "2026-11-16T10:00:00Z", "_updated_by": 1,
  "values": { "learning_area_slug": "lernen", "course_slug": "kurs", "...": "..." } }
```

`_schema` = Schemaversion des Tabs, `_rev` = Änderungszähler für Konflikterkennung, `values` = materialisierte Werte (§2.3).

### 2.2 Teil-Update ohne Reset (Behebung LB-Befund INV-C-069)

LB-Befund: `Settings::sanitize()` baut den gesamten Wert aus `$input` neu auf; jedes Teilformular setzt nicht gesendete Felder auf den Default (`Settings.php:97-114`). Die Sicherheitsseite in LB macht es richtig (`Menu.php` `saveTrustedProxies`: `array_merge(MobileSettings::all(), […])`). Course Captain verallgemeinert das richtige Muster.

| Schritt | Regel |
|---|---|
| S-1 | Speichern über `admin-post.php?action=ccap_settings_save` mit `tab`, Nonce `ccap_settings_save_<tab>`, `_rev` und der Liste der gerenderten Felder (`_fields[]`). Kein `options.php`, kein `register_setting`-Schreibpfad. |
| S-2 | Cap des Tabs und jedes gesendeten Felds prüfen (SR-4). Felder, für die der Nutzer keine Cap hat, werden nicht gerendert und beim Speichern ignoriert. |
| S-3 | Kandidat = gespeicherte `values` ∪ validierte Werte der Felder aus `_fields[]` ∩ Schema des Tabs. Ein **fehlender Schlüssel bedeutet „unverändert“**, nie „Default“. Checkboxen senden ein verstecktes `0` (LB-Muster `Settings.php:107`). |
| S-4 | Schlüssel außerhalb des Tab-Schemas im POST werden verworfen und protokolliert (Debug), nie gespeichert. |
| S-5 | Validierung **alles oder nichts je Tab**: ein ungültiges Feld → nichts gespeichert, feldnahe Fehler, Eingaben bleiben im Formular (Nutzer-Transient 10 min). |
| S-6 | Konflikt: weicht `_rev` vom gespeicherten Wert ab, wird nicht gespeichert; Hinweis „inzwischen von <Anzeigename> geändert“ mit Liste der Schlüssel. |
| S-7 | Risiko-Felder (Spalte „Risiko“) brauchen ein Bestätigungstoken aus dem Dialog (einmalig, an Nutzer, Tab und Werte-Hash gebunden). |
| S-8 | Schreiben: ein `update_option` je Tab; `_rev + 1`; danach Nebenwirkungen in fester Reihenfolge (`caps_sync`, `rewrite_flush`, `cron_reschedule`, `cache_bump`); Audit `settings_saved`. Schlägt eine Nebenwirkung fehl, bleibt der Wert gespeichert, die Nebenwirkung wird als Job wiederholt und im Systemstatus angezeigt. |
| S-9 | Fremdschreiber: Filter `pre_update_option_course_captain_settings_<tab>` prüft die Struktur (Typen und bekannte Schlüssel). Ein ungültiger Wert wird abgelehnt (alter Wert bleibt) und protokolliert. |
| S-10 | Lesen: `Settings::get('<tab>.<feld>')` liefert den gespeicherten Wert, bei fehlendem oder typfremdem Wert feldweise den Default und eine Health-Warnung, nie einen Fatal. |

### 2.3 Versionsfeld, Migration, Default-Wechsel, Downgrade

| Fall | Regel |
|---|---|
| Upgrade | Je Tab reine Migrationsfunktionen `v(n) → v(n+1)` (ohne Seiteneffekte, Muster `migrateSettings` R0-08 §2); Ausführung im Migrations-Orchestrator unter Lock (`ccap_migration_step`, R0-37); idempotent; danach `_schema` erhöht. Unbekannte Schlüssel einer **älteren** Version werden verworfen (GM-181). |
| Erstes Speichern | materialisiert **alle** Felder des Tabs mit ihren aktuellen Defaults. |
| Default-Wechsel | Ändert eine neue Version einen Default, schreibt ihre Migration den **alten** Default in jede Installation, deren Tab den Schlüssel noch nicht enthält. Nur Neuinstallationen erhalten den neuen Default. So ändert sich Verhalten nie still (SR-7, R0-28 „konfliktfreie Default-Änderung“). |
| Downgrade (E-90) | Ist `_schema` neuer als der Code, liest der Code nur die bekannten Schlüssel, übernimmt unbekannte beim Speichern unverändert (Passthrough) und zeigt einen Hinweis. Keine Rückmigration. |
| Kaputter Wert | S-10; der gespeicherte Wert wird erst beim nächsten Speichern durch den Admin ersetzt. |
| Mailvorlagen | `rev` je Sprachvariante; neue Platzhalter sind additiv; ein entfernter Platzhalter ist ein Bruch (Migration ersetzt ihn durch leeren Text und meldet das im Systemstatus). |

### 2.4 Reset je Tab

Reset setzt nur die Felder des aktuellen Tabs auf ihre Defaults, nach Bestätigung mit Liste der Abweichungen. Vorher wird `course_captain_settings_undo_<tab>` geschrieben („Rückgängig“ 30 Tage, eine Generation). Nebenwirkungen wie beim Speichern (Slugs → Flush nach Bestätigung; Rollen → nur die im Manifest vermerkten Caps werden entfernt, `administrator` bleibt unverändert). Nie betroffen: Lernstände, Grants, Gruppen, Versuche, Zertifikate, Kurs- und Prüfungs-Overrides, andere Tabs. Mailvorlagen werden je Typ und Sprache über „Werkstext wiederherstellen“ zurückgesetzt, nicht über den Tab-Reset. Ein globales Reset gibt es nicht (INV-S-14). Audit `settings_reset`.

### 2.5 Export und Import ohne Secrets

| Punkt | Regel |
|---|---|
| Format | JSON `{format: "course-captain-settings", format_version: 1, plugin_version, settings_version, exported_at (UTC), site_hash (sha256 der `home_url`, keine URL), tabs: {<tab>: {_schema, values}}, mail_templates?: {…}}` |
| Inhalt | Klassen `public` und `internal`; `personal` (Absender, Fußzeile, Aussteller-Freitext) und Mailvorlagen nur mit Opt-in-Kästchen; `secret` nie, auch nicht als Platzhalter-Wert (Ausgabe nur `"<gesetzt>"`, R0-16 §3.2); Tab Rollen nur mit eigenem Opt-in. Nicht enthalten: Systemzustand (§2.7), Kurs-/Prüfungs-Overrides (gehören zum Kurspaket R0-10), `delete_data_on_uninstall`. Seiten-IDs als Verweis `{id, slug, title}`; der Import ordnet per Slug zu oder lässt das Feld leer (Warnung). |
| Import | Upload ≤ 256 KB; JSON-Schema-Prüfung; `format_version`-Major muss passen; `settings_version` älter → Migration wie §2.3, neuer → Ablehnung; Vorschau als Feld-Diff je Tab; Auswahl der Tabs; Risiko-Felder brauchen dieselbe Bestätigung wie in der UI; je Tab atomar mit Undo-Snapshot; Audit `settings_imported` (Tabs, Schlüssel). Rollen: nur durch `administrator`, nie Caps über dem eigenen Satz. |
| Rechte, Grenzen | Export und Import #19 (Rollen-Tab zusätzlich #24 und `promote_users`); Rate-Limit „Import/Export 10 je Konto und Stunde, fail-closed“ (R0-17 §3.4); CLI identisch (SR-5). |

### 2.6 Multisite, Uninstall, Restore

- **Multisite:** alle Optionen je Site (`get_option`, kein `get_site_option`), keine netzweiten Defaults (NS-01, E-07, E-49); Nutzerwerte per `update_user_option` mit Site-Präfix (MS-R-05).
- **Uninstall:** Jede Option aus §2.1 und §2.7 steht im Uninstall-Manifest (R0-16, INV-C-026); gelöscht wird nur bei `general.delete_data_on_uninstall` mit Bestätigung (E-89).
- **Restore:** Settings stammen aus dem Dump; der Haltemodus (E-85) gilt unabhängig von Einstellungen. `course_captain_role_grants` wird gegen die WP-Rollen geprüft (Drift → Health-Warnung, keine automatische Korrektur).

### 2.7 Systemzustand (keine Settings) und Verbleib der 20 weiteren LB-Optionen

| LB-Option | CC-Ersatz | Autoload | Bemerkung |
|---|---|---|---|
| `bp_learning_schema_version` | `course_captain_db_version` plus `ccap_migration_step` | ja | Präfix nach D-20-03 (T27-1) |
| `bp_learning_migration_lock` | `course_captain_lock_migration` | nein | Lock-Muster INV-C-052 |
| `bp_learning_fk_issues` | `course_captain_fk_issues` | nein | – |
| `bp_learning_migration_failed` | `course_captain_migration_failed` | nein | – |
| `bp_learning_cron_errors` | Fehlertabelle (Retention `error_log_days`) | – | R0-13 |
| `bp_cron_last_hourly` | `course_captain_cron_state` | nein | Warnschwelle `advanced.cron_stale_warning_minutes` |
| `bp_learning_import_lock` | `course_captain_lock_import` | nein | – |
| `bp_learning_retention_lock` | `course_captain_lock_retention` | nein | – |
| `bp_learning_active_package` | entfällt (Import je Kurs, R0-10) | – | – |
| `bp_learning_packages_suffix`, `bp_learning_protected_suffix` | `course_captain_storage_secret` (`secret`) | nein | nie exportiert (R0-16 §1) |
| `bp_learning_storage_layout` | `course_captain_storage_layout` (nur relative Schlüssel) | nein | R0-16 §1 Nr. 3 |
| `bp_learning_import_drop_moved` | `course_captain_import_drop_state` | nein | – |
| `bp_learning_content_generation` | `course_captain_cache_generation` | ja | R0-21 |
| `bp_learning_update_check` | entfällt | – | E-09 |
| `bp_learning_login_dummy_hash` | entfällt | – | E-10 (keine Login-Logik) |
| `bp_entitlement_prune_last` | `course_captain_retention_state` | nein | im Uninstall-Manifest (LB-Lücke INV-C-026) |
| `bp_privacy_endpoint_version`, `bp_learning_rewrite_version` | `course_captain_rewrite_version` | ja | Flush-Steuerung |
| `bp_learning_voucher_with_member_discount` | entfällt | – | E-25 |

Neu im Systemzustand: `course_captain_installation_uuid`, `course_captain_site_identity` (Haltemodus, E-85), `course_captain_setup_state` (Setup-Fortschritt, Konzept 16.1), `course_captain_slug_history` (E-220).

---

## 3. Benachrichtigungsvertrag (E-187)

### 3.1 Grundsätze

| Nr. | Regel |
|---|---|
| N-1 | **Ereignisgetrieben.** Mails entstehen nur aus Domain-Events der **internen** Outbox (R0-19 §5, R1-43 nach E-182; öffentliche Events nur mit Pro, E-38). Das Modul `Notification` ist ein `EventSubscriber`; je Event, Typ und Empfänger entsteht höchstens ein Datensatz in `ccap_notification` (§3.3). |
| N-2 | **Zustand vor Versand neu prüfen** (Kap. 19): Berechtigung, Objektstatus, Empfänger vorhanden und nicht gelöscht, Opt-out, Ausgangsmodus. Sonst `suppressed` mit Grundcode. |
| N-3 | **Höchstens einmal.** Lieber keine als eine doppelte Mail: Markieren vor dem Versand, kein automatischer Neuversand bei unklarem Ausgang (§3.4). |
| N-4 | **Klassen.** `required` (für Nutzung oder Rechtsposition nötig, nicht abbestellbar, vom Betreiber nicht abschaltbar), `informational` (abbestellbar, vom Betreiber abschaltbar), `marketing` (nur Pro, Opt-out-Pflicht, Frequency Cap, Ruhezeiten). Zuordnung → E-216. |
| N-5 | **Minimalinhalt.** Keine Punktzahl, keine Antworten, kein Freitext aus Antworten, keine fremden E-Mail-Adressen (PRIV-021, T-54); Link in den Lernbereich statt Inhalt. |
| N-6 | **Nur `wp_mail`.** Kein eigener Transport; SMTP-Plugins bleiben wirksam. Header (`From`, `Reply-To`) nie aus Nutzereingaben (SEC-040). |
| N-7 | **Kein PII-Logging.** Protokolle enthalten Typ, Status, Fehlerklasse und Nutzer-ID bzw. Platz-ID, nie Adresse, Betreff oder Text. |

### 3.2 Mailtypen R1 (Free)

| Nr. | Typ-Key | Auslöser (Event) | Empfänger | Klasse (E-216) | Default | Geschäftsschlüssel (Dedupe) | Prüfung direkt vor Versand | Quelle |
|---|---|---|---|---|---|---|---|---|
| N-01 | `access_granted` | `access.changed` (kein Zugang → Zugang), Ursache Grant-Aktivierung; nicht bei Verlängerung, Retry-Folge, Reconcile | Lernender | informational | an; im Grant-Dialog abwählbar (E-136) | `course_uid` + `object_version` des Zugangs | Zugang aktiv, keine Lernsperre | Kap. 19, BS-22, E-136 |
| N-02 | `seat_invitation` | `seat.invited` | Einladungsadresse | required | an (nicht abschaltbar) | `seat_id` + Einladungsrevision | Platz `invited`, Gruppe aktiv, Frist offen; **Token wird erst im Versandschritt erzeugt**, nur der Hash gespeichert, Klartext nur in der Mail (E-112) | R0-09 §1.2, E-28 |
| N-03 | `seat_claimed` | `seat.claimed` | Lernender | informational | an | `seat_id` | Platz aktiv | NTF-001 |
| N-04 | `course_completed` | `course.completed` (auch `revision_recalc`, E-111); nicht `reason_code = reconcile` | Lernender | informational | an | Abschlussdatensatz (`course_uid` + `completed_at`) | Abschluss gültig | Kap. 19, NTF-001 |
| N-05 | `certificate_issued` | `certificate.issued` (`revision_no = 1`) | Lernender | informational | an | `certificate_uid` + 1 | Status `issued`; mit N-04 derselben `correlation_id` zu **einer** Mail zusammengeführt | BS-22 |
| N-06 | `certificate_corrected` | `certificate.corrected` | Lernender | required | an | `certificate_uid` + `revision_no` | Revision ist die aktuelle | E-136, K-15 |
| N-07 | `certificate_revoked` | `certificate.revoked` | Lernender | required | an | `certificate_uid` + `revoked` | Status `revoked`; Grund nur als Kategorie | E-136, K-15 |
| N-08 | `attempt_voided` | `attempt_voided` (K-08) | Lernender | required | an; im Dialog abwählbar (E-136) | `session_uid` + `voided` | Versuch `cancelled`/`support_void` | E-136, E-137 |
| N-09 | `attempt_annulled` | `attempt_annulled` (K-10) | Lernender | required | an | `session_uid` + `annulled` | Nachweis `revoked` | E-136 |
| N-10 | `access_transferred` | `grant_transferred` (K-05) | nur Ziel-Lernender | informational | an; im Dialog abwählbar | Audit-ID der Übertragung | Ziel-Grant aktiv; ersetzt N-01 derselben `correlation_id` | E-136 |
| N-11 | `grading_completed` | Auswertung nach manueller Bewertung (`evaluation_no` neu, R1-09) | Lernender | informational | an | `session_uid` + `evaluation_no` | Ergebnis freigegeben (`feedback.visibility ≠ never` für den Link-Text) | PRIV-021 |
| N-12 | `access_expired` | `access.changed` (Zugang → kein Zugang), Ursache Ablauf | Lernender | informational | **aus** (E-217) | `course_uid` + `object_version` | weiterhin kein Zugang; ohne Kauf- oder Verlängerungslink | NTF-002, E-24 |
| N-13 | `test_mail` | Admin-Aktion „Testmail“ | eigene Profiladresse des Admins (keine freie Adresse, kein Relay) | required | – | – (Rate-Limit §3.8) | Ausgangsmodus | INV-D-045 |
| N-14 | `assignment_submitted` | `assignment.submitted` (R0-08 §2.5) | zuständige Trainer des Kurses (`ccap_course_staff`, Rolle `trainer`) | informational | an | `submission_uid` + `submission_no` | Abgabe weiter `submitted`; Empfänger hat Bewertungsrecht (E-288) | E-290 |
| N-15 | `assignment_returned` | `assignment.returned` | Lernender | informational | an | `submission_uid` + `returned` | Status `returned`; Kommentar nicht in der Mail, nur Link | E-290 |
| N-16 | `assignment_graded` | `assignment.graded` (auch Neubewertung, `evaluation_no`) | Lernender | informational | an | `submission_uid` + `evaluation_no` | Status `graded`; Punkte nicht in der Mail, nur Link | E-290 |

**Keine Mail in R1:** Widerruf, Aussetzung, Lernsperre, Fortschritts-Reset, Reconcile, Verlängerung (E-136, R0-13 K-02). **Nicht von CC:** Datenschutzanträge, Passwort-Reset, Kontoanlage (WordPress-Kern); Kaufbestätigungen (WooCommerce). **Pro (R3):** Ablauf-Erinnerungen, Drip-Freigaben, Fristen, Kohortenkommunikation (Kap. 19, E-24). **Admin-Benachrichtigungen** laufen in R1 über Admin-Hinweise und Site Health, nicht per Mail.

### 3.3 Datensatz und Zustände (`ccap_notification`, Delta R0-06)

Spalten: `id`, `uid char(36)`, `type varchar(48)`, `dedupe_key char(64)` (UNIQUE, `sha256(type:recipient_ref:business_key)`), `event_uuid char(36)`, `correlation_id char(36) NULL`, `recipient_user_id bigint NULL`, `recipient_ref varchar(64) NULL` (z. B. `seat:<id>`), `status varchar(16)`, `reason_code varchar(48) NULL`, `attempts tinyint`, `next_attempt_at`, `locked_until`, `lock_owner`, `last_error_class varchar(32) NULL`, `locale_requested`/`locale_resolved varchar(20)`, `template_rev int NULL`, `resend_of bigint NULL`, `created_at`, `sent_at`. **Keine** Adresse, kein Betreff, kein Text.

```
queued ──recheck ok, Modus on──► sending (Lease 10 min) ──wp_mail true──► sent
  │                               │ wp_mail false/Exception (vorübergehend) ──► retry_wait ──► sending
  │                               │ dauerhafter Fehler / Versuche erschöpft ──► failed
  │                               └ Lease abgelaufen ohne Ergebnis ──► unknown (kein Auto-Neuversand)
  ├─recheck negativ / Opt-out / Typ aus / Rate-Limit (informational) ──► suppressed (reason_code)
  ├─Haltemodus ──► held ──Admin „freigeben“──► queued │ Admin „verwerfen“ (Standard, E-88) oder Frist ──► discarded
  ├─Modus catch ──► caught (Zustellung nur an die Catch-Adresse)
  └─Modus off ──► suppressed (outbound_off)
Admin „erneut senden“ (failed, unknown, caught): neuer Datensatz mit resend_of, neue Prüfung, Rate-Limit
```

### 3.4 Versand-Einmaligkeit

| Nr. | Regel |
|---|---|
| V-1 | Outbox liefert at-least-once (R0-19 §5.4 Nr. 5). Das Anlegen erfolgt per `INSERT … ON DUPLICATE KEY` auf `dedupe_key`; ein zweites Event mit demselben Geschäftsschlüssel erzeugt keinen zweiten Datensatz. |
| V-2 | Zusammenführung über `correlation_id`: N-04 + N-05 → eine Mail; N-10 ersetzt N-01. Der Sammler wartet 60 s nach dem ersten Event derselben `correlation_id`. |
| V-3 | Markieren vor dem Versand: CAS `queued/retry_wait → sending` mit Lease; erst danach `wp_mail` (LB-Muster INV-B-071 „Merker vor dem Versand setzen“). |
| V-4 | Bricht der Prozess nach `wp_mail` ab, bleibt `sending`; nach Lease-Ablauf wird `unknown` gesetzt, nicht neu versendet. Systemstatus zeigt `unknown` mit Aktion „erneut senden“ (#23). |
| V-5 | Jeder Retry prüft den Zustand neu (N-2) und rendert neu (Locale, Vorlage). |
| V-6 | Nach Restore verhindert der Haltemodus (E-85) Neuversand aus wiederholten Events; Standardaktion für zurückgehaltene Mails ist „verwerfen“ (E-88). |

### 3.5 Zustellfehler

| Klasse | Erkennung | Folge |
|---|---|---|
| `transport` | `wp_mail()` liefert `false` oder `wp_mail_failed` meldet einen vorübergehenden Fehler | Retry nach 5 min, 30 min, 2 h, 12 h; nach 5 Versuchen `failed` (E-218) |
| `invalid_recipient` | Adresse ungültig (`is_email`), Konto gelöscht | sofort `failed`, kein Retry |
| `template_error` | Rendern fehlgeschlagen (z. B. Vorlage defekt) | sofort `failed`; Admin-Hinweis mit Typ und Sprache; Werkstext als Rückfall beim nächsten Versuch |
| `short_circuit` | `pre_wp_mail` liefert `false` (fremdes Plugin) | wie `transport` |

Fehlermeldungen von PHPMailer laufen durch den Scrubber (R0-13 §2.2) und landen gekürzt (≤ 200 Zeichen) in der Fehlertabelle (Retention `error_log_days`). Ein Admin-Hinweis höchstens einmal je 24 h, solange `failed > 0`; Site-Health-Test `course_captain_mail` (kritisch ab 10 `failed` in 24 h oder Warteschlange älter als 1 h). `wp_mail() = true` heißt „vom Transport angenommen“, nicht „zugestellt“; Bounces sind nicht Teil von R1.

### 3.6 Ausgangsmodus und Mail-Sink (E-85, E-86)

| Vorrang | Zustand | Wirkung auf Mails |
|---|---|---|
| 1 | Haltemodus (Site-Identität geändert, E-85) | `held`, nichts gerendert; nach Bestätigung freigeben oder verwerfen; Frist `held_mail_days` |
| 2 | `COURSE_CAPTAIN_OUTBOUND = off` | `suppressed (outbound_off)` |
| 3 | `catch` (Default außerhalb `production`) | `caught`: Zustellung nur an `COURSE_CAPTAIN_MAIL_CATCH_ADDRESS` bzw. `admin_email`, Original-Empfänger nur als Nutzer-ID im Header `X-CC-Recipient-Ref`; Betreff mit Präfix `[catch]` |
| 4 | `on` | normaler Versand |

**Mail-Sink in Tests (NTF-007, Ausführung R0-18):** PHPUnit fängt über `pre_wp_mail` in einen `MailSink` (kein Netz); Playwright läuft mit `catch` und Mailpit als Catch-Adresse über ein Test-MU-Plugin (`phpmailer_init`). Abnahmekriterium: jede erwartete Mail liegt im Sink, 0 externe Zustellungen.

### 3.7 Opt-out

- Nur Typen der Klasse `informational` (und `marketing` in Pro) sind abbestellbar. Der Betreiber kann eine Abmeldung nicht aufheben.
- Speicherung `user.notification_prefs` (User-Option, MS-R-05); Einstellung im Konto (L-20) je Typ.
- Jede abbestellbare Mail trägt `List-Unsubscribe` (signierte URL) und `List-Unsubscribe-Post: List-Unsubscribe=One-Click` (RFC 8058). Signatur HMAC-SHA256 über `user_id:type:user_registered`, Schlüssel aus `wp_salt('auth')` mit Zweckkennung, ohne Ablauf (LB-Muster INV-B-074). GET zeigt eine Bestätigungsseite mit Knopf (gegen Link-Scanner), POST wirkt sofort (INV-B-075). Falsche Signatur ändert nichts.
- `required`-Mails tragen keinen Abmeldelink, aber einen Fußzeilensatz, warum sie notwendig sind.
- Route der Abmeldeseite in Free (`/lernen/benachrichtigungen/`); R0-11 L-21 ist bisher Pro (Delta §7).
- Audit `notification_optout_changed` (Typ, Kanal Konto/Link), Export im Datenschutzexport.

### 3.8 Rate-Limits

| Grenze | Wert | Ausfallverhalten | Quelle |
|---|---|---|---|
| Einladungen je Gruppe | max(20, 2 × Plätze) je 24 h | fail-closed | E-152, R0-17 §3.4 |
| erneutes Senden einer Einladung | 3 je Platz | fail-closed | E-218 |
| Mails je Empfänger | 10 je 24 h (`informational` darüber `suppressed`, `required` auf das nächste Fenster verschoben) | fail-open | E-218 |
| Testmail | 5 je Admin und Stunde | fail-closed | E-218 |
| Versandlauf | 50 Empfänger bzw. 20 s je Lauf, Fortsetzung nach 60 s | – | LB INV-B-070 |

### 3.9 Sprache (E-124, E-159, R0-25 §3.2)

Empfänger-Locale: Konto → `get_user_locale()` (unabhängig von `general.use_profile_locale`); Einladung an eine Adresse → gewählte Sprache → Sprache der einladenden Person → Site-Sprache. Je Empfänger `switch_to_user_locale()`/`switch_to_locale()`, `restore_previous_locale()` in `finally`. Vorlagenauflösung: exakte Variante → gleiche Sprache → Standardvariante des Betreibers → Werkstext in der Empfänger-Locale. `lang`/`dir` nach verwendeter Vorlage; Datum und Zahl in der Rahmen-Locale, Fristen mit Zonenkennung (E-124); Kurstitel in Inhaltssprache mit `<bdi>`. `locale_requested`/`locale_resolved` im Datensatz.

### 3.10 Vorlagen und Variablen

- Syntax `{{name}}`; nur Variablen aus der Allowlist des Typs; unbekannter Platzhalter → Speichern abgelehnt (Feldfehler mit Liste der erlaubten).
- Escaping nach Kontext: HTML-Teil `esc_html`, URL-Variablen `esc_url`, Textteil ohne HTML; Betreff ohne CR/LF, ≤ 200 Zeichen. Keine Variable liefert rohes HTML.
- Betreibertext: Mail-kses-Allowlist `p, br, strong, em, a[href], ul, ol, li, h2, h3`; Rahmen-Layout fest (neutral, kontrastgeprüft).
- Vorschau mit Beispieldaten und Testversand an die eigene Adresse; Audit `mail_template_saved` (Typ, Locale, Hash vorher/nachher).

| Variable | Typen | Inhalt |
|---|---|---|
| `site_name`, `site_url`, `learning_area_url`, `privacy_policy_url` | alle | aus WordPress bzw. `general.*` |
| `recipient_display_name` | alle außer N-02 | Anzeigename |
| `unsubscribe_url` | nur `informational` | signierter Link |
| `course_title`, `course_url` | N-01, N-03…N-05, N-10…N-12 | Kurs in Inhaltssprache |
| `group_name`, `inviter_display_name`, `invite_expires_at`, `claim_url` | N-02 | bereinigt; `claim_url` enthält den Token (nur in dieser Mail) |
| `certificate_url`, `verify_url` | N-04, N-05, N-06 | Nachweisansicht (HTML, E-96) |
| `reason_label` | N-06…N-09 | Grundkategorie, kein Freitext |
| `attempt_title`, `result_url` | N-08, N-09, N-11 | ohne Punktzahl |
| `access_end_at` | N-12 | Datum mit Zone |

Verboten: Punktzahl, Antworten, Freitext aus Antworten, fremde Adressen, IP, interne IDs.

### 3.11 Datenschutz der Benachrichtigungen

`ccap_notification` hält nur Metadaten (§3.3); Retention `notification_log_days` (E-215). Der Datenschutzexport enthält Typ, Status und Zeitpunkt der eigenen Mails sowie `user.notification_prefs`; der Eraser löscht die Datensätze des Nutzers. Einladungsadressen liegen nur am Platz (`ccap_seat.invite_email`) und werden nach R0-09 §7 gelöscht. Gruppennamen in Mails sind bereinigt (SEC-040).

---

## 4. Testfälle

### 4.1 SET – Speicherung, Rechte, Migration (allgemein)

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel-Task | weitere |
|---|---|---|---|---|---|---|
| SET-001 | P | Schema laden | jede Register-Zeile hat alle Pflichtfelder (SR-2); jeder Default besteht den eigenen Validator | PU | R1-31 | – |
| SET-002 | N | Tab „Lernen“ speichern, nur 2 von 6 Feldern gerendert | übrige Felder und alle anderen Tabs unverändert (LB-Befund INV-C-069 behoben, vgl. GM-179) | PI | R1-31 | R1-32 |
| SET-003 | P | Checkbox abwählen | `false` gespeichert; ein im POST fehlendes Feld behält seinen Wert | PI | R1-31 | – |
| SET-004 | N | ein ungültiges Feld unter fünf gültigen | nichts gespeichert, feldnaher Fehler, Eingaben im Formular erhalten | PW | R1-36 | R1-32 |
| SET-005 | N | POST mit Schlüsseln eines anderen Tabs und unbekannten Schlüsseln | verworfen, nicht gespeichert | PI | R1-36 | – |
| SET-006 | N | Kursmanager (ohne #19), Autor, Gast; falsche oder fremde Nonce | 403; Tab nicht sichtbar; Wert unverändert | PI | R1-31 | R1-03 |
| SET-007 | N | Schreiben über `options.php` und `/wp/v2/settings`; Lesen über REST | abgewiesen bzw. nicht enthalten | PI | R1-31 | R1-28 |
| SET-008 | N | zwei Admins speichern denselben Tab mit gleichem `_rev` | zweiter erhält Konflikthinweis mit Schlüsselliste, kein Überschreiben | PI | R1-36 | – |
| SET-009 | P | Maximalwerte in allen Feldern | nur die Optionen aus §2.1 mit „ja“ sind autoload; Summe ≤ 4 KB; Migration korrigiert falsche Flags | PI | R1-31 | R1-40 |
| SET-010 | P | Upgrade Tab-Schema n−1 → n mit einem veralteten Schlüssel | Werte übernommen, veralteter Schlüssel verworfen, `_schema` erhöht; zweiter Lauf ändert nichts; unter Lock (GM-181) | PI | R1-31 | R1-40 |
| SET-011 | P | neue Version ändert Default von `x` auf `y`; Altinstallation ohne gespeicherten Schlüssel, Neuinstallation | Alt: `x` materialisiert; Neu: `y` | PI | R1-36 | – |
| SET-012 | P | Downgrade um eine Minor (E-90), speichern, erneutes Upgrade | unbekannte Schlüssel beim Speichern erhalten, Hinweis sichtbar, nach Upgrade kein Wertverlust | PI | R1-36 | R1-23 |
| SET-013 | N | Option manipuliert (kein Array, falscher Typ, ein Feld ungültig); Fremdcode ruft `update_option` mit Müll | feldweise Default beim Lesen, Health-Warnung, kein Fatal; Fremdschreiben abgelehnt | PI | R1-36 | – |
| SET-014 | P/N | Reset Tab „Prüfungen“; danach „Rückgängig“; Suche nach globalem Reset | nur dieser Tab zurückgesetzt, Undo stellt ihn her; Grants, Lernstände, Versuche, Vorlagen, andere Tabs unverändert; kein globales Reset (INV-S-14) | PW | R1-36 | R1-32 |
| SET-015 | P | Export mit und ohne Opt-in | JSON nach §2.5; ohne Opt-in keine `personal`-Felder, keine Vorlagen, kein Rollen-Tab; nie `secret`, nie Systemzustand; Audit | PI | R1-31 | – |
| SET-016 | P/N | Import: gültig; neuere `settings_version`; falsches Format; Risiko-Feld ohne Bestätigung; 11. Import in einer Stunde | Vorschau-Diff und atomarer Import; neuere Version und falsches Format abgelehnt; Risiko-Feld nicht übernommen; Rate-Limit greift (fail-closed) | PW | R1-36 | – |
| SET-017 | N | Secret gesetzt (Konstante und DB) | kein Klartext in Export, Diagnose, Site Health, REST, Audit (wie REC-022, SEC-036) | PI | R1-36 | R1-23 |
| SET-018 | P/N | Multisite, Plugin auf Site 1 und 2 aktiv; Netzwerkaktivierung versucht | getrennte Werte je Site, keine `site_option`; Netzwerkaktivierung blockiert (E-49) | MS | R1-36 | – |
| SET-019 | P | Speichern mit und ohne Änderung, mit `personal`-Feld | `settings_saved` mit Vorher/Nachher (public/internal), „geändert“ (personal); ohne Änderung kein Eintrag | PI | R1-31 | R1-38 |
| SET-020 | P | Uninstall ohne und mit Lösch-Setting | ohne: alle Optionen bleiben; mit (nach Bestätigung): alle Optionen aus §2.1/§2.7 gelöscht, Manifest vollständig (Scan) | PI | R1-36 | R1-23 |
| SET-021 | P/N | `wp course-captain settings set general.learning_area_slug wp-admin` und gültiger Wert | ungültig abgelehnt wie in der UI; gültig gespeichert mit Audit `system:cli` | PI | R1-31 | – |
| SET-022 | P | Einstellungsseite mit Tastatur und Screenreader | Labels, Fehler per `aria-describedby`, Fokus auf erstes fehlerhaftes Feld, Warnung bei ungespeicherten Änderungen, Bestätigungsdialoge erreichbar | PW | R1-36 | R1-32 |
| SET-023 | P | Site-Sprache, Zeitzone, `date_format`, `admin_email` in WordPress ändern | CC zeigt sofort die neuen Werte; kein CC-Schlüssel dupliziert sie | PI | R1-36 | R1-19 |

### 4.2 SET – Feldverträge

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel-Task | weitere |
|---|---|---|---|---|---|---|
| SET-030 | P/N | Lernbereich-Slug `wissen`; Slug einer bestehenden Seite; `wp-admin`; gleicher Wert für Kurs- und Lernbereich-Slug; Abbruch im Bestätigungsdialog | gültig: Flush erst nach Bestätigung, neue URL erreichbar, alte leitet weiter (E-220); Kollisionen und reservierte Werte abgelehnt mit Alternativvorschlag; Abbruch ändert nichts (KOMP-132) | PW | R1-31 | R1-16 |
| SET-031 | P/N | Katalogseite = Entwurf, Papierkorb, fremde Seite ohne `edit_page`; gültige Seite später gelöscht | ungültige abgelehnt; Inhalt nie überschrieben; gelöschte Seite → Rückfall Lernbereich und Health-Warnung | PI | R1-31 | R1-16 |
| SET-032 | P | Anzeigename leer, `XXXX`, 61 Zeichen | leer und `XXXX` → Site-Titel; 61 Zeichen abgelehnt | PI | R1-31 | – |
| SET-033 | P | zwei Sprachen installiert, `use_profile_locale = null`; Gruppierung Sprachversionen an | Profilsprache aktiv (LOC-012); Katalog zeigt nur die Version der UI-Sprache je Gruppe | PI | R1-31 | R1-19 |
| SET-034 | P/N | Indexierung `noindex` global; Kurs-Override `index`; Lektion und Nachweisseite | Kurs indexierbar, Katalog nicht; Lektion und Nachweis immer `noindex`, nie in der Sitemap | PI | R1-31 | R1-37 |
| SET-035 | N | Löschen bei Deinstallation einschalten ohne bzw. mit falschem Site-Namen; Import mit `true` | nicht gespeichert; Import ignoriert den Wert | PW | R1-36 | R1-23 |
| SET-040 | P | Breite `wide`, Breadcrumbs aus, Kurs-Override Breadcrumbs an | Ausgabe nur im `.ccap-`-Container; Kurs zeigt Breadcrumbs | PW | R1-31 | R1-16 |
| SET-041 | P/N | Akzentfarbe mit Kontrast 3:1 für Text; `red`; `#ABCDEF` | Warnung mit konformem Vorschlag, Lernbereich nutzt konformen Wert; `red` abgelehnt; Großschreibung normalisiert; keine CSS-Regel außerhalb des Containers | PW | R1-31 | R1-16 |
| SET-042 | P | Sortierung `manual` mit zwei Kursen ohne Position; Kartenelemente leer | Titel-Fallback; Karten nur mit Titel | PI | R1-31 | R1-16 |
| SET-043 | P | `require_captions` an, Entwurf mit Video ohne Untertitel | Veröffentlichen blockiert; Bestätigungsdialog nannte die Anzahl betroffener Entwürfe | PI | R1-31 | R1-26 |
| SET-050 | P/N | Default `required_modules_and_exam` für einen Kurs ohne Prüfung | Publish-Blocker; Kurs mit Prüfung: Abschluss erst nach Bestehen | PI | R1-31 | R1-10 |
| SET-051 | N | Algorithmus auf unbekannte Version setzen | abgelehnt; bestehende Werte unverändert | PU | R1-31 | R1-11 |
| SET-052 | P | Favoriten aus (Kurs), Fehlertraining an | Favoriten-Schalter fehlt im Kurs, Daten bleiben erhalten und exportierbar | PW | R1-31 | R1-11 |
| SET-054 | P | Suche nach Schalter für Fortsetzen/Autosave; Prüfung nach Reload | kein Schalter vorhanden; Wiederaufnahme ohne Antwortverlust (INV-S-01) | PW | R1-36 | R1-20 |
| SET-055 | P | neuer Kurs ohne Drip-Regel; Drip-Regel 3 Tage | Module sofort frei; mit Regel ab Tag 3 (E-14, Free) | PI | R1-31 | R1-14 |
| SET-056 | P | globales Prüfungs-Setting ändern, während ein Versuch läuft | laufender Versuch unverändert (Snapshot, INV-S-02) | PI | R1-36 | R1-33 |
| SET-060 | P/N | `max_attempts` 0, 101, `null`; Cooldown 3600 nach Fehlversuch | 0/101 abgelehnt; `null` unbegrenzt; Start vor Ablauf der Sperrfrist abgewiesen (GM-192) | PI | R1-31 | R1-08 |
| SET-061 | P/N | Zeitlimit 0 und 1441; Kulanz 601; Filter liefert 900; `practice` bei Nachweisprüfung | abgelehnt bzw. auf 600 begrenzt; Validator-Blocker für `practice` | PI | R1-31 | R1-08 |
| SET-062 | P/N | Vorschlag 0/10, 11/10, 7/10; neue Regel anlegen | 0/10 und 11/10 abgelehnt; neue Regel speichert expliziten Wert 7/10; Änderung des Vorschlags ändert bestehende Regeln nicht | PI | R1-31 | R1-08 |
| SET-063 | N | feste Variante mit `shuffle_questions = all` | abgelehnt; Pool-Prüfung erlaubt `within_section`/`all`; `pinned` bleibt fest | PI | R1-31 | R1-08 |
| SET-064 | P | Feedback `never` (Prüfung), `always` (Lernmodus); Lösungen bei offenen Restversuchen | Prüfung: nur Punkte; Lernmodus: Sofortfeedback; Lösungen erst ohne Restversuche oder nach Bestehen (E-147) | PI | R1-31 | R1-09 |
| SET-065 | P | Abbruch bei Limit 2; Auto-Abgabe nach 3 Tagen ohne Zeitlimit | Abbruch zählt; Versuch nach 3 Tagen Inaktivität abgegeben | PI | R1-31 | R1-08 |
| SET-070 | P/N | Selbstanmeldung global einschalten (Dialog mit Anzahl); Kurs-Override `off`; Filter `can_access` liefert `true` ohne Grant | Grant mit Quelle `self_enrollment`; Kurs mit `off` ohne Anmeldung; kein Zugang ohne Grant (INV-S-11, GM-180 angepasst) | PI | R1-31 | R1-13 |
| SET-071 | P | Default-Dauer 30 Tage; manueller Grant; Default danach auf `null` | neuer Grant 30 Tage; bestehender Grant unverändert | PI | R1-31 | R1-13 |
| SET-072 | P | Gruppen abschalten bei zwei aktiven Gruppen | Dialog nennt 2; keine neuen Gruppen; bestehende Plätze und Grants aktiv | PI | R1-31 | R1-13 |
| SET-073 | P/N | Einladungsfrist 0, 366, 14; `invited_address_only` mit fremdem Konto | 0/366 abgelehnt; neue Einladung 14 Tage; fremdes Konto 403 | PI | R1-31 | R1-13 |
| SET-074 | P | Timeout 1 h, Grant bleibt `pending` | nach 1 h `failed` mit `pending_timeout` (E-201) | PI | R1-31 | R1-13 |
| SET-075 | P | Quelle deaktiviert, Policy `keep_and_flag` | Grants bleiben aktiv, Prüfliste im Systemstatus, kein Widerruf (ACCESS-09) | PI | R1-31 | R1-43 |
| SET-076 | P | Pro aktiv, dann deaktiviert bzw. Lizenz abgelaufen | Pro-Feld nur mit Pro sichtbar; Basis-Plätze und Claims funktionieren in allen Fällen | PI | R1-36 | R3-15 |
| SET-077 | P | Grant entziehen bei zwei gleichwertigen Grants; Lernsperre | Vorschau zeigt verbleibenden Zugang; Fortschritt bleibt; keine Mail (E-136) | PI | R1-36 | R1-38 |
| SET-080 | N | WP `default_role` = `editor` | Health-Warnung; CC vergibt keine `ccap_*`-Cap an `editor` (E-83) | PI | R1-31 | R1-03 |
| SET-081 | P/N | #12 an `editor` geben; #19 durch Kursmanager vergeben; `administrator` bearbeiten | erstes mit Bestätigung und Audit, Manifest aktualisiert; zweites 403; `administrator` nicht wählbar (RBAC-091) | PI | R1-31 | R1-03 |
| SET-082 | P | Standard-Support-Rolle öffnet Versuchsdetail; danach #14 zuweisen | ohne #14 403; mit #14 nur mit Pflichtbegründung und Audit (E-82) | PI | R1-31 | R1-03 |
| SET-083 | N | Rollen-Tab ohne Nonce, mit fremder Nonce, per CSRF-Formular | abgewiesen, kein Audit-Erfolg, Wert unverändert (INV-S-04) | PI | R1-36 | R1-03 |
| SET-090 | N | Kurs-Override `auto_issue` ohne Abschlussregel | Validator-Blocker; mit Regel: Zertifikat bei Abschluss | PI | R1-31 | R1-44 |
| SET-091 | P | Policy `never`; Lernender versucht Namensfreigabe; Pro deaktiviert | keine Freigabe möglich; Verifikation zeigt Status ohne PII und bleibt ohne Pro erreichbar (INV-S-13) | PI | R1-31 | R1-35 |
| SET-092 | P | Ausstellername ändern nach drei ausgestellten Nachweisen | neue `template_version`; alte Nachweise unverändert gerendert (E-110) | PI | R1-31 | R1-35 |
| SET-093 | N | Widerruf ohne Grund, ohne #17 | abgelehnt; mit Grund: Status `revoked`, Audit, Mail N-07 | PI | R1-36 | R1-38 |
| SET-100 | P/N | Absender leer; Absender `kurse@andere-domain.example`; Name mit CR/LF | leer: kein `wp_mail_from`-Filter aktiv; fremde Domain: Warnung; CR/LF abgelehnt | PI | R1-31 | R1-21 |
| SET-101 | P | `text_only` | Mails ohne HTML-Teil | PI | R1-31 | R1-21 |
| SET-102 | N | `seat_invitation` abschalten (UI und manipulierter POST) | abgelehnt; `course_completed` abschaltbar mit Bestätigung | PI | R1-31 | R1-21 |
| SET-103 | P/N | Vorlage mit `{{score}}`, mit `<script>`, Variante für nicht installierte Locale; gültige Variante `de_DE` | Platzhalter und Locale abgelehnt, Skript entfernt; gültige Variante gespeichert, Audit mit Hash | PI | R1-31 | R1-21 |
| SET-104 | P | Free ohne Pro; Pro aktiv | Free zeigt keine Erinnerungs-Einstellung (E-100); Pro zeigt `pro.reminders` mit Default aus | PI | R1-36 | R3-15 |
| SET-110 | P | Löschantrag bestätigt, `auto_erase_on_confirm` aus bzw. an | aus: Antrag bleibt für den Admin; an (nach Bestätigung): Konto nach WP-Bestätigung gelöscht | PI | R1-31 | R1-17 |
| SET-111 | N | Nachweise behalten ohne Begründung | abgelehnt; mit Begründung: Eraser pseudonymisiert und meldet es im Ergebnis (E-150) | PI | R1-31 | R1-17 |
| SET-112 | P/N | Audit-Frist 0; Prüfungsversuche von 10 auf 5 Jahre | 0 abgelehnt (Minimum 1); Verkleinern zeigt Anzahl betroffener Datensätze, nächster Lauf wendet die neue Frist an (PRIV-008) | PI | R1-31 | R1-17 |
| SET-113 | P | `backup_max_age_days` = 60 | Tombstone-Frist 90 Tage | PI | R1-31 | R1-17 |
| SET-114 | P | Default ohne Änderung; `click_to_load` mit `remember` aus; `link_only` | Default `always_load`: Einbettung direkt (MED-031); `click_to_load`: nie externe Verbindung vor Klick, `remember` aus: kein Kästchen, Browserwahl ignoriert (MED-023); `link_only`: nur Link | PW | R1-31 | R1-07 |
| SET-115 | N | Proxy `0.0.0.0/0`; 51 Einträge; ungültige CIDR | abgelehnt; gültige Liste: Rate-Limit zählt die Client-IP aus `X-Forwarded-For` nur von diesen Proxys | PI | R1-31 | R1-42 |
| SET-116 | P | Avatare aus bzw. an | aus: kein `get_avatar()` im Lernbereich (PRIV-017); an: Hinweistext | PI | R1-31 | R1-16 |
| SET-117 | P | `report_only` | Header `Content-Security-Policy-Report-Only` auf CC-Antworten, Bestätigung vorher | PI | R1-31 | R1-16 |
| SET-118 | N | Suche nach Schalter „Audit aus“; Admin speichert Settings | kein Schalter; Audit-Eintrag entsteht immer (INV-S-05) | PI | R1-36 | R1-17 |
| SET-119 | P/N | Login mit falschem Passwort elfmal von einer IP: (a) frische Installation ohne Bestätigung, (b) nach Bestätigung ohne Security-Plugin, (c) mit erkanntem Security-Plugin | (a) keine Sperre (E-260); (b) Sperre nach R0-31 §3.4; (c) CC sperrt nicht, Plugin übernimmt (`auto`, E-240) – ersetzt die alte Fassung zu INV-S-07 | PI | R1-36 | R1-45 |
| SET-120 | P/N | Debug 25 h; Debug 2 h, Uhr + 2 h 1 min | 25 h abgelehnt; nach Ablauf automatisch aus, Audit `debug_disabled`; Logs redigiert | PI | R1-31 | R1-38 |
| SET-121 | P | Import im Hintergrund mit 3 % Fehlern bei 1 000 Objekten; Ausführung `browser` | Import hält bei max(100, 2 %) an; Dry-run immer vor dem Schreiben (INV-S-09) | PI | R1-31 | R1-41 |
| SET-122 | N | `x_accel_redirect` ohne passende nginx-Regel; Suche nach Schalter „Medien öffentlich“ | Umschalten abgelehnt (Selbstprüfung fehlgeschlagen); kein Schalter (INV-S-06) | PI | R1-31 | R1-14 |
| SET-123 | P | Karenz 7 Tage, Datei verliert letzte Referenz | Löschung frühestens nach 7 Tagen | PI | R1-31 | R1-23 |
| SET-124 | P | Cron 181 min ohne Lauf, Schwelle 180 | Warnung im Systemstatus und Site Health | PI | R1-31 | R1-38 |
| SET-125 | P/N | Filter setzt Claim-Limit auf 0 und `fail_mode = open` | Wert verworfen bzw. `fail_mode` bleibt `closed`; Anzeige zeigt wirksame Werte | PI | R1-31 | R1-42 |
| SET-129 | P/N | Tab Website-Härtung: speichern ohne Cap `manage_options`; `force`/`off` ohne Bestätigung; erstes Speichern | 403; abgelehnt; `confirmed_at` gesetzt, Audit, Werte wirken | PI | R1-31 | R1-45 |
| SET-130 | P | `COURSE_CAPTAIN_HARDENING = off`; Zählerausfall mit bekanntem Gerät | Formular lesend, alle Funktionen aus; bekanntes Gerät kann sich anmelden (E-263) | PI | R1-31 | R1-45 |
| SET-131 | P | `consent_category = statistics` bei `always_load` bzw. `click_to_load` | `always_load`: ohne Wirkung; `click_to_load`: Consent API mit Kategorie `statistics` abgefragt; `functional` nur mit Bestätigung | PI | R1-31 | R1-34 |
| SET-132 | P | Free ohne Pro; Pro aktiv; Pro-Lizenz abgelaufen | Free: kein Erklärungs-Schalter, keine Erklärungen ausgeliefert, Texte gespeichert; Pro: Schalter sichtbar; abgelaufen: eingefroren wirksam, Feld lesend (E-268) | PI | R1-36 | R3-15 |
| SET-133 | P | Setup-Assistent, Schritt Datenschutz | Hinweis zur Übertragung an externe Dienste und Umschalter `always_load`/`click_to_load` sichtbar | PW | R1-32 | R1-31 |
| SET-134 | P/N | Frischinstallation; Aufgabe mit `file_rules` {pdf}, 10 MB, 2 Dateien; global danach `max_file_mb = 5`; Aufgabe versucht `zip` oder 30 MB | Defaults pdf/docx/odt/txt/jpg/png, 20 MB, 5 Dateien (E-289); Aufgabenwerte gültig; wirksam 5 MB (kleinster Wert); `zip` und Werte über global abgelehnt (`ccap_setting_invalid`) | PI | R1-31 | R1-46 |
| SET-126 | P | Woo und Voucher installiert, nicht verbunden | Systemstatus zeigt sie lesend; keine Aktivierung, keine Settings (E-185) | PI | R1-36 | R1-38 |
| SET-127 | N | Free ohne Connector | keine Woo-/Voucher-Felder, keine Woo-Hooks (KOMP-101) | PI | R1-36 | R1-30 |
| SET-128 | N | Free ohne Pro | keine Lizenzseite, kein Lizenzfeld, kein Upsell-Schalter (E-100) | PI | R1-36 | – |

### 4.3 NTF – Benachrichtigungen (NTF-001…007 aus R0-12 §1.3 übernommen, NTF-007 in R0-18 ausgeführt)

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel-Task | weitere |
|---|---|---|---|---|---|---|
| NTF-001 | P | Einladung, Claim-Bestätigung, Kursabschluss | genau eine Mail je Ereignis (Dedupe), Absender von WordPress geerbt | PI | R1-21 | R1-43 |
| NTF-002 | P | Zugang abgelaufen, Default | keine Mail (E-217), Ablaufanzeige im Lernbereich; nach Einschalten genau eine Mail **nach** Ablauf, ohne Kauflink; keine Erinnerung davor (E-24) | PI | R1-21 | – |
| NTF-003 | N | `wp_mail` liefert `false` bzw. SMTP fällt aus | Fehler protokolliert (ohne PII), Retry mit Backoff, nach Erfolg keine Doppelmail | PI | R1-21 | R1-17 |
| NTF-004 | P | Lernender bestellt `informational`-Typen ab | keine weiteren Mails dieser Typen; `required` (Einladung, Korrektur, Widerruf) weiter | PI | R1-21 | R1-34 |
| NTF-005 | N | Besitzer lädt 50 Adressen bei 10 Plätzen ein | Rate-Limit max(20, 2 × 10) greift fail-closed; keine Mail über dem Limit | PI | R1-21 | R1-13 |
| NTF-006 | P | `WP_ENVIRONMENT_TYPE = staging` bzw. Haltemodus | alle Mails `caught` bzw. `held`, keine externe Zustellung | PI | R1-21 | R1-23 |
| NTF-007 | P | E2E-Lauf in der CI | jede erwartete Mail im Sink, 0 externe Zustellungen | PW | R1-21 | R1-18 |
| NTF-008 | N | dasselbe Outbox-Event wird zweimal zugestellt (Replay) | ein Datensatz, eine Mail | PI | R1-21 | R1-43 |
| NTF-009 | N | Prozessabbruch nach `wp_mail`, vor dem Status-Update | nach Lease `unknown`, kein automatischer Zweitversand; Aktion „erneut senden“ sichtbar | PI | R1-21 | R1-42 |
| NTF-010 | N | Grant zwischen Event und Versand widerrufen | `suppressed (state_changed)`, keine Mail | PI | R1-21 | R1-13 |
| NTF-011 | N | Empfänger vor Versand gelöscht bzw. Löschantrag ausgeführt | keine Mail; Datensatz gelöscht bzw. ohne Personenbezug | PI | R1-21 | R1-17 |
| NTF-012 | N | ungültige Adresse | sofort `failed (invalid_recipient)`, kein Retry | PI | R1-21 | – |
| NTF-013 | N | fünf Transportfehler in Folge | `failed`; Admin-Hinweis einmal je 24 h; Site Health kritisch ab 10 | PI | R1-21 | R1-38 |
| NTF-014 | P/N | Abmeldelink: GET; POST One-Click; falsche Signatur | GET zeigt Knopf ohne Wirkung; POST meldet ab; falsche Signatur ändert nichts; `List-Unsubscribe` nur bei `informational` (Free-Teil von GM-160) | PW | R1-21 | R1-34 |
| NTF-015 | P/N | Abmeldung im Konto je Typ; Admin versucht Rücknahme | wirkt sofort; Admin kann sie nicht aufheben; `required` nicht abwählbar | PW | R1-21 | R1-34 |
| NTF-016 | P | Haltemodus mit 3 zurückgehaltenen Mails; Admin bestätigt „gleicher Ort“; Standardaktion | Standard verwirft (E-88), „freigeben“ versendet; nach `held_mail_days` verworfen | PI | R1-21 | R1-23 |
| NTF-017 | P/N | `catch` ohne Catch-Konstante; ungültige Catch-Adresse; `off` | Zustellung an `admin_email` mit `[catch]`; ungültig → `off` mit Warnung; `off` → `suppressed` | PI | R1-21 | R1-23 |
| NTF-018 | P/N | Testmail; sechste Testmail in einer Stunde; SMTP-Fehler | an eigene Adresse; Rate-Limit; Fehlertext redigiert angezeigt | PW | R1-21 | R1-38 |
| NTF-019 | P | Empfänger `de_DE`, `en_US`, `ar` in einem Lauf, Exception beim zweiten | jede Mail in eigener Sprache, `lang`/`dir` korrekt, Locale danach zurückgesetzt (wie LOC-016…019) | PU | R1-21 | R1-19 |
| NTF-020 | P | Vorlage nur in `de_DE`, Empfänger `de_CH`; keine Variante, Empfänger `fr_FR` | `de_DE`-Variante; sonst Standardvariante bzw. Werkstext | PU | R1-21 | R1-19 |
| NTF-021 | N | Kurstitel und Gruppenname mit HTML, CR/LF und `Bcc:` | inert im Text, keine zusätzlichen Header (SEC-040) | PU | R1-21 | – |
| NTF-022 | N | Abschluss-, Bewertungs- und Annullierungsmail | keine Punktzahl, keine Antworten, kein Freitext (PRIV-021) | PI | R1-21 | – |
| NTF-023 | P | `multipart` | HTML- und Textteil inhaltsgleich, Textteil ohne Markup | PU | R1-21 | – |
| NTF-024 | P | Kursabschluss mit automatischem Zertifikat (eine `correlation_id`) | eine Mail mit beiden Links | PI | R1-21 | R1-44 |
| NTF-025 | P/N | elf Mails an einen Empfänger in 24 h | `informational` ab der 11. `suppressed`, `required` ins nächste Fenster verschoben | PI | R1-21 | – |
| NTF-026 | P | Gruppeneinladung an 500 Adressen | Stapel 50 bzw. 20 s, Fortsetzung, keine Doppelmail, alle Mails nach Abschluss im Sink | PI | R1-21 | R1-22 |
| NTF-027 | P | Supportdialog „Versuch verwerfen“ mit abgewählter Mail; „Lernsperre“ | keine Mail in beiden Fällen (E-136) | PI | R1-21 | R1-38 |
| NTF-028 | P/N | `course_completed` vom Betreiber abgeschaltet; manipulierter POST schaltet `seat_invitation` ab | keine Abschlussmail; Einladung bleibt aktiv | PI | R1-21 | – |
| NTF-029 | P | Einladung neu senden (4. Mal) | drittes Mal erlaubt, viertes abgelehnt; jeder Neuversand erzeugt neuen Token, alter ungültig | PI | R1-21 | R1-13 |
| NTF-030 | P | Datenschutzexport und Eraser eines Lernenden mit Mails | Export: Typ, Status, Zeit, Präferenzen; Eraser löscht `ccap_notification`-Zeilen | PI | R1-21 | R1-17 |
| NTF-031 | P | Absender-Override gesetzt; SMTP-Plugin erzwingt anderen Absender | nur CC-Mails mit Override; Systemstatus zeigt Hinweis auf den erzwungenen Absender | PI | R1-21 | R1-30 |
| NTF-032 | P | Retention 90 Tage | ältere `ccap_notification`-Zeilen gelöscht, jüngere bleiben | PI | R1-21 | R1-17 |
| NTF-033 | N | WP-Cron 2 h ausgefallen, 40 Mails in der Warteschlange | Warnung im Systemstatus; nach Wiederanlauf alle versendet, keine verloren, keine doppelt | PI | R1-21 | R1-38 |
| NTF-034 | P | Vorlage speichern, Vorschau, Testversand | Vorschau mit Beispieldaten in gewählter Sprache; Testversand nur an die eigene Adresse; Audit | PW | R1-21 | R1-32 |
| NTF-035 | P | Lernender reicht Aufgabe ein; Kurs hat 2 Trainer, 1 Autor | je zugewiesenem Trainer genau eine Mail N-14 (Dedupe `submission_uid` + `submission_no`); Lernender und Autor erhalten keine | PI | R1-21 | R1-46 |
| NTF-036 | P | Trainer gibt zurück, danach bewertet, danach Neubewertung | N-15 einmal; N-16 einmal je `evaluation_no`; keine Punkte, kein Kommentar im Mailtext | PI | R1-21 | R1-46 |
| NTF-037 | N | Lernender hat informative Mails abbestellt; Trainer hat N-14 abbestellt | N-15/N-16 bzw. N-14 `suppressed` (`opt_out`); Abgabe-Workflow unverändert | PI | R1-21 | R1-46 |

---

## 5. Entscheidungen (beschlossen)

Alle Fragen sind entschieden (`ENTSCHEIDUNGEN.md` §33, „Von Claude entschieden“, 16.11.2026); Empfehlung jeweils übernommen. Genutzt außerdem: E-10, E-20, E-24, E-28, E-38, E-43, E-44, E-45/E-45a, E-51, E-53, E-85…E-90, E-97, E-100, E-116, E-119, E-124, E-136, E-143, E-147, E-150…E-152, E-159, E-168, E-187, E-197, E-201, E-207, E-240…E-246, E-260…E-263, E-268…E-270.

| Frühere ID | Beschluss | Inhalt | Wirkt in | Tasks |
|---|---|---|---|---|
| D27-1 | **E-215** | kein echter Widerspruch: Inbox und zugestellte Outbox-Einträge 90 d; Events ohne Abonnent 30 d (nur Pro, E-38); Benachrichtigungsprotokoll 90 d | §1.4, §3.11 | R1-21, R1-31 |
| D27-2 | **E-216** | Pflicht (nicht abbestellbar): Claim/Einladung, Sicherheits- und Kontomails, Datenschutzanträge; übrige abbestellbar (§3.2) | §3.2, §3.7 | R1-21, R1-31 |
| D27-3 | **E-217** | Mail „Zugang abgelaufen“ vorhanden, Default aus, ohne Kauf-/Verlängerungslink | §3.2 | R1-21 |
| D27-4 | **E-218** | Retry 5× (5 min, 30 min, 2 h, 12 h); 10 Mails je Empfänger/24 h; 3 Neuversendungen je Einladung; 5 Testmails je Admin/h | §3.5, §3.8 | R1-21 |
| D27-5 | **E-219** | optionale Absenderfelder, leer = WordPress-Standard | §1.3 `emails.*` | R1-21, R1-32 |
| D27-6 | **E-220** | frühere Slugs dauerhaft merken, 301, solange kein anderes Objekt den Slug belegt | §1.3 | R1-16, R1-37 |
| D27-7 | **E-221** | Rate-Limits nur per Filter; wirksame Werte im Tab Erweitert (gilt sinngemäß für `course_captain_hardening_limits`) | §1.3 `advanced.rate_limits` | R1-42 |
| D27-8 | **E-222** | Gruppen-Grundfunktion aus, im Setup aktivierbar | §1.3 `enrollment.groups_enabled` | R1-13, R1-32, R1-35 |
| D27-9 | **E-223** | automatischer Datenexport nach bestätigtem Antrag an | §1.3 `privacy.auto_export_on_confirm` | R1-17 |
| D27-10 | **E-224** | CSP `enforce`, `report_only` nur mit Bestätigung | §1.3 `privacy.csp_mode` | R1-16, R1-30 |

### 5.1 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-27-1 | Settings für Aufgaben-Abgaben (erlaubte Dateitypen, Höchstgröße, ggf. Kurs-Override) und Mail bei `returned` | E-168 legt Funktion und Workflow fest, aber keine Feldwerte und keinen Mailtyp | Architektur (R1 Aufgaben), dann Register |
| OP-27-2 | Schutz gegen User-Enumeration: E-241 (Opt-in) gegen E-261 (R4-Pool) | widersprüchliche Beschlüsse; im Register nicht geführt (E-261) | Auftraggeber (Klarstellung) |
| OP-27-3 | Zählungen §8 nach Einarbeitung neu per Skript erzeugen | Skript-Lauf ist keine Planungsentscheidung | R1-31 |

---

## 6. Technische Korrekturen (ohne Auftraggeber-Entscheidung)

| Nr. | Korrektur | Betroffen |
|---|---|---|
| **T27-1** | Optionsnamen für Schema-/Migrationszustand, Ordner-Geheimnis und Installations-UUID (früher `ccap_db_version`, `ccap_schema_fingerprint`, `ccap_migrations_applied`, `ccap_backfill_state`, `ccap_migration_failed`, `ccap_fk_issues`, `ccap_storage_secret`, `ccap_installation_uuid`) heißen `course_captain_…` (E-207) – **eingearbeitet** in R0-16; Migrationszustand in `ccap_migration_step` (technisch festgelegt zu R0-37). | R0-16 §1, R0-06 §6.1 |
| **T27-2** | INV-C-068 nennt `ccap_settings`; maßgeblich sind die Tab-Optionen aus §2.1. | R0-02 C (Zielspalte) |
| **T27-3** | Kap. 43.3 „Fragenreihenfolge: Mischen aus“ gegen R0-08 `shuffle_questions` Default `within_section`: beides gilt je Prüfungsart (feste Variante `none`, Poolprüfung `within_section`); Kap. 43.3 redaktionell präzisieren. | Konzept 43.3, R0-08 §3 |
| **T27-4** | R0-13 K-15 „Mail mit neuem PDF-Link“: in Free gibt es kein PDF (E-96); Link auf die HTML-Nachweisansicht. | R0-13 §3.1 |
| **T27-5** | Golden Master: GM-180 (`default_access`) wird auf `enrollment.self_enrollment` umgestellt (kein Modus „alle Angemeldeten“, R0-07 L-W1); GM-178 (`reminder_days`) gehört zu Pro (E-24) und wird in R1 durch SET-001/SET-010 ersetzt. In die Abweichungsliste nach E-55: Standard-Zugangsdauer unbefristet statt 182 Tage (INV-B-033), Bestehensvorschlag 70 % statt Festwert 0,75, automatische Kontolöschung aus statt an (E-43). | R0-05 §1, GM-Abweichungsliste |
| **T27-6** | R0-19 §4.3: `course_captain_outbox_retention_days` und `course_captain_import_*`-Filter lesen ihren Default aus `privacy.retention` (wie bereits für Import-Log vermerkt). | R0-19 §4.3 |
| **T27-7** | Neue Tabelle `ccap_notification` (§3.3) und Abonnent `notification` an der Outbox. | R0-06, R0-19 §5.4 |
| **T27-8** | Kap. 43.3 „Free Connector R3“ für WooCommerce/Voucher gilt nach E-53 als Pro-Connector; im Register so geführt (K43-50/51). | Konzept 43.3 |

---

## 7. Deltas zu anderen Dokumenten

| Dokument | Delta |
|---|---|
| `ENTSCHEIDUNGEN.md` | E-215…E-224 aufgenommen – **erledigt** |
| TODO | R0-27 nach Freigabe abhaken; R1-21, R1-31, R1-36 DoR-Spalte „R0-27 fehlt“ entfällt |
| R0-05 | T27-5 (GM-178/180, Abweichungsliste) |
| R0-06 | T27-1, T27-7 |
| R0-11 | L-21 (Abmeldeseite) zusätzlich in Free für `informational`-Typen; A-15 verweist auf §1.3 als Feldliste |
| R0-12 | NTF-008…NTF-034 und SET-001…SET-128 in die Zählung (§5) aufnehmen; ACCESS-09 → SET-075; R1-31/R1-36 nicht mehr „knapp/dünn“ |
| R0-13 | T27-4; Audit-Ereignisse `settings_reset`, `settings_imported`, `mail_template_saved`, `notification_optout_changed` in §4.1 |
| R0-16 | T27-1 – **eingearbeitet**; Uninstall-Manifest um §2.1/§2.7 (inkl. `course_captain_settings_hardening`) ergänzen |
| R0-19 | T27-6, T27-7 |
| R0-24 | R1-21/R1-31/R1-36: Inputs = R0-27 §1–§4; Testlisten aus §4 |
| R0-28 | Override-Speicherorte und Materialisierung aus SR-3 und §2.3 übernehmen |
| R0-30 | Wireframes je Tab nach §1.3 (Hilfetexte, Risiko-Dialoge, Reset, Import-Vorschau); Default `always_load` und Setup-Hinweis (E-45a) – **eingearbeitet** |
| R0-31 | Defaults der Datenschutz-Felder und §1.4 prüfen; E-215, E-223; Härtungsfelder `hardening.*` statt `privacy.hardening` (E-262) – **eingearbeitet** |

---

## 8. Zählungen per Skript

Kommando (aus dem Repository-Wurzelverzeichnis, Python 3, nur lesend):

```bash
python3 - <<'PY'
import re, collections
D = 'docs/r0/'
doc = open(D + 'R0-27-settings-register.md', encoding='utf-8').read()
kon = open('konzept.md', encoding='utf-8').read()
ent = open(D + 'ENTSCHEIDUNGEN.md', encoding='utf-8').read()
def cells(l): return [c.strip() for c in l.strip().strip('|').split('|')]
def part(a, b): return doc.split(a, 1)[1].split(b, 1)[0]
rows43 = [cells(l) for l in kon.split('### 43.3', 1)[1].split('### 43.4', 1)[0].split('\n')
          if l.startswith('| ') and not l.startswith('| Tab |') and not l.startswith('|---')]
mp = [cells(l) for l in part('### 1.2', '### 1.3').split('\n') if l.startswith('| K43-')]
reg = [cells(l) for l in part('### 1.3', '### 1.4').split('\n') if l.startswith('| `')]
ret = [l for l in part('### 1.4', '### 1.5').split('\n') if l.startswith('| `')]
inv = {cells(l)[0]: cells(l) for l in part('### 1.5', '### 1.6').split('\n') if l.startswith('| INV-S-')}
gone = set(re.findall(r'~~(INV-S-\d\d)~~', part('### 1.5', '### 1.6')))   # entfallene Nummern
tests = {cells(l)[0]: cells(l) for l in part('## 4.', '## 5.').split('\n') if re.match(r'^\| (SET|NTF)-\d{3} ', l)}
keys = {re.sub(r'`', '', r[0]) for r in reg}
err = collections.defaultdict(list)
print('Kap. 43.3 Zeilen:', len(rows43), '| Zuordnungen:', len(mp))
for i, (k, m) in enumerate(zip(rows43, mp), 1):
    if m[0] != f'K43-{i:02d}' or m[1] != k[0] or m[2] != k[1]: err['zuordnung'].append(m[0])
    for key in re.findall(r'`([^`]+)`', m[4]):
        base = key.split(' ')[0]
        if base.endswith('.*') and any(k.startswith(base[:-1]) for k in keys): continue   # Gruppen-Key, z. B. hardening.*
        if base not in keys and not base.startswith('ccap_') and base not in ('ccap_access_rule',): err['key'].append((m[0], base))
    for t in re.findall(r'(?:SET|NTF)-\d{3}', m[5]):
        if t not in tests: err['test'].append((m[0], t))
    for iv in re.findall(r'INV-S-\d\d', m[4]):
        if iv not in inv and iv not in gone: err['inv'].append((m[0], iv))
art = collections.Counter()
for m in mp:
    a = m[3]
    art['Feld/Pro' if ('Feld' in a or 'Pro' in a) else 'Aktion/Objekt' if a.split('+')[0] in ('Aktion', 'Objekt') else a] += 1
print('Art:', dict(art))
for r in reg:
    if len(r) != 13 or any(c == '' for c in r): err['spalten'].append(r[0])
    for t in re.findall(r'(?:SET|NTF)-\d{3}', r[12]):
        if t not in tests: err['regtest'].append((r[0], t))
    if not re.search(r'(?:SET|NTF)-\d{3}', r[12]): err['ohnetest'].append(r[0])
k43 = sum(1 for r in reg if 'K43-' in r[2])
print('Register:', len(reg), '| mit K43:', k43, '| ohne K43:', len(reg) - k43, '| Retention:', len(ret), '| INV-S:', len(inv))
for iv, c in inv.items():
    if not re.search(r'(?:SET|NTF)-\d{3}', c[3]) or any(t not in tests for t in re.findall(r'(?:SET|NTF)-\d{3}', c[3])): err['invtest'].append(iv)
E = set(re.findall(r'^\| \**(E-\d+)\**', ent, re.M))
miss = sorted(set(re.findall(r'\bE-\d+\b', doc)) - E)
if miss: err['E-unbekannt'] = miss
ziel = collections.Counter(c[5] for c in tests.values())
print('Tests SET/NTF:', sum(t.startswith('SET') for t in tests), '/', sum(t.startswith('NTF') for t in tests), '| Ziel:', dict(ziel))
bad = [t for t, c in tests.items() if c[5] not in ('R1-21', 'R1-31', 'R1-32', 'R1-36')]
if bad: err['ziel'] = bad
mail = [l for l in part('### 3.2', '### 3.3').split('\n') if re.match(r'^\| N-\d\d ', l)]
print('Mailtypen:', len(mail), '| D27:', len(set(re.findall(r'^\| D27-(\d+) \|', doc, re.M))))
print('Fehler:', dict(err) if err else 'keine')
PY
```

Ausgabe (16.11.2026, erneut ausgeführt nach Einarbeitung von E-285…E-312; Skript dabei um Gruppen-Keys `x.*`, entfallene Invarianten `~~INV-S-nn~~`, Ziel R1-32 und die D27-Zählung aus §5 korrigiert):

```text
Kap. 43.3 Zeilen: 57 | Zuordnungen: 57
Art: {'Feld/Pro': 43, 'Inv': 5, 'Aktion/Objekt': 5, 'WP': 1, 'Seite': 3}
Register: 86 | mit K43: 52 | ohne K43: 34 | Retention: 21 | INV-S: 13
Tests SET/NTF: 91 / 37 | Ziel: {'R1-31': 63, 'R1-36': 27, 'R1-32': 1, 'R1-21': 37}
Mailtypen: 16 | D27: 10
Fehler: keine
```

Die Prüfungen umfassen: Name und Reihenfolge jeder Kap.-43.3-Zeile gegen §1.2; jeder dort genannte Register-Key existiert in §1.3; jede genannte Test-ID und Invariante ist definiert; jede Register-Zeile hat 13 gefüllte Spalten und mindestens einen definierten Test; alle E-Nummern existieren in `ENTSCHEIDUNGEN.md`; jeder Test zielt auf R1-21, R1-31, R1-32 oder R1-36.

---

## 9. Nachweis

- Gelesen: Konzept Kap. 16, 19, 43 vollständig; TODO R0-27…R0-31, R1-21, R1-31…R1-36; `ENTSCHEIDUNGEN.md` E-01…E-204 vollständig; R0-02 Inventur C §2.3/§3.2 und D §3.3 sowie B INV-B-033/037/069…077; R0-07 §3, §4.2/§4.3, §7; R0-08 §3, §4, §7; R0-09 §1.2, §7; R0-10 §4, §5; R0-11 (Settings-, Mail- und Pro-Zeilen); R0-12 §1.3, E-187; R0-13 §2–§4; R0-15 E-124/D-15-07; R0-16 §1–§3; R0-17 §3.4/§3.5, §5, §7, §8; R0-19 §1.2, §4.3, §5.4; R0-20 Slug-/Multisite-Zeilen; R0-22 E-131; R0-24 §4; R0-25 §3.2. LB `9f37c77`: `Settings.php` (Defaults, `sanitize()` Z. 97–114 als Ursache des Teilformular-Resets), `MobileSettings.php` (Defaults, `save()` mit Autoload), `Menu.php` (`handleSecurity`/`saveTrustedProxies` als korrektes Merge-Muster).
- Zählungen: Skript in §8, Ergebnis dort wörtlich. Feldnamen, Typen und Bereiche sind Vorschläge; Defaults aus Kap. 43.3 und den Entscheidungen übernommen, neue Werte sind als D27 gekennzeichnet.
- Nicht erfolgt: keine Änderung an anderen Dateien, keine Implementierung, kein Testlauf, kein Commit.
