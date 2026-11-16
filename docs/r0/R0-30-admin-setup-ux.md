# R0-30 · Admin-Menü, Setup-Assistent und Einstellungs-UX

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Keine Implementierung, kein Testlauf. Die DoD verlangt Feld-/UI-Vertrag, positive und negative Testfälle, Security-/Accessibility-Review und Freigabe. · **Stand:** 16.11.2026
**Bezug:** TODO R0-30 (Voraussetzungen R0-15, R0-22, R0-26, R0-27; DoD: „Menübaum, Tab-Screens, Hilfetexte, Validierungsfehler, Risk-Confirmations und Tastaturabläufe als prüfbare Wireframes“), Folgetask **R1-32** (DoR in R0-24 §4.2: „nicht ready, R0-30 fehlt“), mittelbar R1-36 und R1-29.
**Quellen (nur gelesen):** `konzept.md` Kap. 16 (16.1–16.3), Kap. 43.2/43.3; `TODO-WETTBEWERBSANALYSE.md` R0-30, R1-31…R1-36; `docs/r0/ENTSCHEIDUNGEN.md` Beschlussprotokoll E-01…E-20 und Empfehlungen E-21…E-224; R0-11 §2.1 (Z-Zustände), §2.2 (A-01…A-18), §3.4 (Flow 4, F4.1…F4.8), §4 (C-06, C-12, C-16), §5 (N-08, N-10); R0-27 §1.1 (SR-1…SR-8), §1.3 (Register), §1.4, §1.5 (INV-S-01…14), §2.1–§2.5, §2.7, §3.2, §4.1, §5; R0-15 §1.3 (K-01…K-18), §2.2 (A11Y-001…010, A11Y-080…085), §3.2 (I18N-R); R0-22 §1.1–§1.3, §3.4 (Meldungsregeln); R0-26 §1.5 (AD-T01…AD-T06), §2.5 (S-07), §5.3 (UX-060…065); R0-07 §3.1 (Caps #1…#24), §4.2 (S-01…S-24); R0-17 §3.4 „Hinweis zu E-10“; R0-20 LB-K-05/06, §2.6 (KOMP-050…056), KOMP-102; R0-02 Inventur C INV-C-138…146; R0-35 E-207.

### Beschlussstand

Verbindlich sind das Beschlussprotokoll und „Von Claude entschieden“ in `ENTSCHEIDUNGEN.md` (16.11.2026). Die früheren Fragen D30-1…D30-9 sind als **E-239** und **E-260…E-267** entschieden (§6). Eingearbeitet:

| Beschluss | Wirkung hier |
|---|---|
| **E-01** Clean Room | Verhalten aus LB (Härtung, Systemstatus) wird nur nach Spezifikation neu gebaut |
| **E-10**, E-239…E-242, E-260…E-263 | Website-Härtung im Kern (Task R1-45): Setup-Schritt 6 und 11. Tab; **aus, bis der Admin speichert**, der Assistent schlägt „an“ vor (außer bei erkanntem Security-Plugin), danach je Funktion `auto`; Registrierungslimit und Gerätecookie (E-241); fail-closed für Login/Reset mit Ausnahme bekannter Geräte und Notausschalter |
| **E-20**, E-268, E-269 | Erklärungs-Schalter sind Pro-Felder (`pro.learning.feedback.show_explanations`, `pro.exams.feedback.show_explanations`); Free-Editor blendet das Erklärungsfeld aus |
| **E-38**, E-254, E-256 | Menüpunkt „Integrationen“ in R1 nur lesend (Partnererkennung); Aktionen mit Pro/R3 |
| **E-45/E-45a**, E-246 | Setup-Schritt 5 und Tab Datenschutz: externe Medien **Default „Immer laden“**, Hinweis auf die Datenübertragung und Umschalter auf „Klick zum Laden“; Offenlegung in der readme (§2.4, §3.3.9) |
| **E-100, E-102, E-202, E-19, E-46, E-49, E-83, E-89, E-121, E-124, E-131, E-166, E-197, E-210, E-216…E-224** | wie angenommen |
| **E-168** | Text-/Dateiaufgaben in R1; eigene Admin-Seiten dafür sind hier nicht spezifiziert (Offene Punkte) |
| **E-207** | Präfix `ccap_` für Caps, CPTs, Rollen; Optionen `course_captain_*` |

**Sprache der Texte.** Alle UI-Texte unten sind deutsche Zieltexte (de_DE, Anrede „du“ wie im Lernbereich). Quellsprache im Code ist en_US (E-121); R1-32 leitet die Quelltexte daraus ab. Platzhalter in `{}`; Mengen mit `_n()` (I18N-R-03/04).

**Kernzahlen** (per `grep` gezählt, §9)

| Größe | Wert |
|---|---:|
| sichtbare Menüpunkte Free (Gruppen bedingt) / mit Pro | 12 / 13 + Pro-Screens |
| versteckte Admin-Seiten mit eigenem Slug | 9 |
| Settings-Tabs (Kap. 43.2: 10, plus „Website-Härtung“ nach E-262) | 11 |
| Setup-Schritte | 8 |
| Zeilen in den Tab-Wireframes §3.3 (Felder und Anzeigen) | 94 |
| Risiko-Dialoge RC-01…RC-18 + Dialog „Ungespeicherte Änderungen“ | 18 + 1 |
| Bereitschaftsprüfungen RDY-01…RDY-15 | 15 |
| Testfälle ADM (§5) | 104 |
| Entscheidungen E-239, E-260…E-267 (beschlossen) | 9 |

---

## 1. Menübaum

### 1.1 Lösung des Konflikts C-06 (Kap. 43.2 unvollständig)

Kap. 43.2 nennt zwölf Menüpunkte, aber keinen Platz für **Protokoll** (E-19, Free), **Medien & Dateien** (A-06), **Kurs-FAQ und Lerntipps** (E-16, A-05), **Rechte & Freigabe** und den **Setup-Assistenten** (R0-11 C-06). Zusätzliche Menüpunkte würden das Menü auf 16 bis 17 Einträge verlängern. LB-Unterseiten zeigen, dass seltene Seiten im Menü untergehen.

**Lösung (E-264):** Das Hauptmenü bleibt bei den Punkten aus Kap. 43.2. Fehlende Seiten werden **Bereiche** eines vorhandenen Menüpunkts und sind über eine einheitliche **Bereichsnavigation** (§1.4) oben auf der Seite erreichbar:

| Fehlender Platz | Neuer Ort | Begründung |
|---|---|---|
| Kurs-FAQ, Lerntipps (CPT `ccap_faq`, `ccap_tip`) | Kurse › Bereich „Kurs-FAQ“ bzw. „Lerntipps“ | gehören immer zu einem Kurs; dieselbe Cap #1 |
| Medien & Dateien (A-06) | Kurse › Bereich „Medien & Dateien“ | Verwendung je Kurs; Cap #1 (R0-07 S-07) |
| Rechte & Freigabe, Kurs-Export | Inhalte importieren › Bereiche „Rechte & Freigabe“, „Kurs-Export“ | gehören zum Importablauf (A-13) |
| Protokoll (Audit, E-19) | Systemstatus › Bereich „Protokoll“; Direktlinks aus Übersicht und Lernenden-Detail („Verlauf“) | eigene Cap #21; `ccap_support` hat #21 und #22 (R0-07) |
| Einstellungen sichern (Export/Import) | Systemstatus › Bereich „Einstellungen sichern“ | R0-11 A-16; Cap #19 |
| Setup-Assistent (A-02) | **kein Menüpunkt**; erreichbar über Admin-Hinweis, Übersicht-Checkliste und Link „Einrichtungsassistent öffnen“ im Kopf von Einstellungen | wird selten gebraucht |
| „Erweiterungen“ (E-100) | kein Menüpunkt; ausblendbare Karte in Integrationen (R0-11 N-08) | WordPress.org-Richtlinie |

### 1.2 Endgültiger Menübaum (Free)

Präfixe: Caps `ccap_` nach E-207. Die Nummer `#n` verweist auf R0-07 §3.1 (dort noch `ccap_…`). **Meta-Caps** `ccap_menu_*` werden nie Rollen zugewiesen; `map_meta_cap` löst sie in „eine der genannten Caps“ auf. Menüposition des Hauptmenüs: `'26.3'` (direkt nach „Kommentare“), Filter `course_captain_menu_position`. Icon: `dashicons-welcome-learn-more`.

| Pos | Menüpunkt | Slug / URL | Cap (Seite) | Sichtbar wenn | Screen | Bereiche (Bereichsnavigation) |
|---:|---|---|---|---|---|---|
| – | **Course Captain** (Hauptmenü) | slug der ersten erlaubten Unterseite (Regel M-1) | Cap dieser Unterseite | mindestens eine Unterseite erlaubt | – | – |
| 1 | Übersicht | `admin.php?page=course-captain` | `ccap_menu_dashboard` ⇐ #1 ∨ #12 ∨ #13 ∨ #22 | immer bei Cap | A-01 | – |
| 2 | Kurse | `edit.php?post_type=ccap_course` | #1 `ccap_edit_courses` | immer bei Cap | A-03, A-04 | Kurse · Kurs-FAQ (`edit.php?post_type=ccap_faq`) · Lerntipps (`edit.php?post_type=ccap_tip`) · Medien & Dateien (`admin.php?page=course-captain-media`) |
| 3 | Fragenbank | `admin.php?page=course-captain-questions` | #1 (kursübergreifend #5) | immer bei Cap | A-07 | – |
| 4 | Quizzes & Prüfungen | `admin.php?page=course-captain-exams` | #1 (Objekt: M-06) | immer bei Cap | A-08 | – |
| 5 | Lernende | `admin.php?page=course-captain-learners` | #13 `ccap_view_learner_details` | immer bei Cap | A-09 | Zugänge · Zugangsregeln (#10) |
| 6 | Gruppen | `admin.php?page=course-captain-groups` | #11 `ccap_manage_groups` | `enrollment.groups_enabled = true` **oder** mindestens eine Gruppe existiert (E-265) | A-10 | – |
| 7 | Zertifikate | `admin.php?page=course-captain-certificates` | `ccap_menu_certificates` ⇐ #13 ∨ #17 | immer bei Cap | A-11 | Ausgestellte Nachweise · Basisvorlage (#17) |
| 8 | Berichte | `admin.php?page=course-captain-reports` | #12 `ccap_view_reports` | immer bei Cap | A-12 | – |
| 9 | Inhalte importieren | `admin.php?page=course-captain-import` | `ccap_menu_import` ⇐ #6 ∨ #7 ∨ #8 | immer bei Cap | A-13 | Pakete (#6) · Rechte & Freigabe (#8) · Kurs-Export (#7) |
| 10 | Integrationen | `admin.php?page=course-captain-integrations` | #20 `ccap_manage_integrations` | immer bei Cap | A-14 | – |
| 11 | Einstellungen | `admin.php?page=course-captain-settings` | `ccap_menu_settings` ⇐ #19 ∨ (#24 ∧ `promote_users`) | immer bei Cap | A-15 | Tabs §3 (je Tab eigene Cap) |
| 12 | Systemstatus | `admin.php?page=course-captain-status` | `ccap_menu_status` ⇐ #22 ∨ #21 | immer bei Cap | A-16 | Status (#22) · Jobs (#22, Aktionen #23) · Dateischutz (#22) · Protokoll (#21) · Diagnose (#22) · Einstellungen sichern (#19) |
| 13 | Pro-Lizenz | `admin.php?page=course-captain-license` | #19 | nur bei aktivem Pro-Add-on (vom Add-on registriert) | A-17 | – |

**Pro-Slot:** Pro-Screens (A-18: Gradebook, Tutor-Bewertungen, Lernpfade) registriert das Add-on über den Slot `course_captain_admin_menu_items` **zwischen Pos 8 (Berichte) und Pos 9**. Free zeigt an dieser Stelle nichts (E-100). Free-Bewertung offener Antworten ab R2-02 wird Bereich von „Quizzes & Prüfungen“, kein eigener Menüpunkt (R0-11 A-01).

### 1.3 Versteckte Seiten (ohne Menüeintrag)

| Seite | Slug / URL | Cap | Hervorgehobener Menüpunkt (`parent_file`/`submenu_file`) |
|---|---|---|---|
| Setup-Assistent | `admin.php?page=course-captain-setup&step=<key>` | #19 | Übersicht |
| Kurseditor (Block-Editor) | `post.php?post=<id>&action=edit` (CPT `ccap_course`) | M-03 | Kurse |
| Medien & Dateien | `admin.php?page=course-captain-media` | #1 | Kurse |
| Lernenden-Detail | `admin.php?page=course-captain-learners&learner=<id>` | #13 + Objektregel | Lernende |
| Versuchsdetail | `admin.php?page=course-captain-learners&attempt=<uid>` | M-09, Begründungsdialog (E-82) | Lernende |
| Gruppendetail | `admin.php?page=course-captain-groups&group=<uid>` | #11 | Gruppen |
| Import-Batch | `admin.php?page=course-captain-import&batch=<uid>` | #6 + M-21 | Inhalte importieren |
| Mailvorlage bearbeiten | `admin.php?page=course-captain-settings&tab=emails&template=<typ>` | #19 | Einstellungen |
| Nachweisdetail | `admin.php?page=course-captain-certificates&certificate=<uid>` | #13, Ändern #17 | Zertifikate |

Modul- und Lektions-CPTs (`ccap_module`, `ccap_lesson`) haben `show_in_menu = false`; sie werden nur im Curriculum des Kurseditors bearbeitet.

### 1.4 Regeln

| Nr. | Regel |
|---|---|
| **M-1** | Das Hauptmenü verlinkt die **erste erlaubte** Unterseite in der Reihenfolge von §1.2 (R0-07 §4.2). Ohne erlaubte Unterseite gibt es kein Hauptmenü; der direkte Aufruf jeder Seite liefert 403 (Z-R) ohne Existenzleck. |
| **M-2** | Jede Seite prüft ihre Cap beim Registrieren **und** beim Rendern; jeder Bereich und jeder Tab prüft seine eigene Cap. Nicht erlaubte Bereiche werden nicht angezeigt (kein `aria-disabled`, kein Hinweis). |
| **M-3** | **Bereichsnavigation** = `<nav aria-label="{Seitenname}: Bereiche">` mit Links; der aktive Link trägt `aria-current="page"`. Jeder Bereich hat eine eigene URL und lädt neu. Das ist **kein** ARIA-Tab-Widget: K-07 (Pfeiltasten) gilt nicht, normale Tab-Reihenfolge. Gleiches Bauteil für Kurse, Lernende, Zertifikate, Import, Einstellungen und Systemstatus. |
| **M-4** | Reihenfolge der Bereiche wie in §1.2; leere Bereiche (z. B. „Lerntipps“ ohne Einträge) bleiben sichtbar und zeigen Z-L. |
| **M-5** | Keine Upsell-Einträge, keine Badges „Pro“, keine gesperrten Menüpunkte (E-100, SR-8). |
| **M-6** | Bezeichnungen sind kurz (≤ 22 Zeichen in de_DE, damit nichts im eingeklappten WordPress-Menü umbricht) und über `_x()` mit Kontext `admin menu` übersetzt. |
| **M-7** | Keine automatische Weiterleitung nach der Aktivierung; der Einstieg in die Einrichtung erfolgt über den Admin-Hinweis (§2.1). |

---

## 2. Setup-Assistent (A-02)

### 2.1 Einstieg, Wiederaufnahme, erneuter Aufruf

| Punkt | Regel |
|---|---|
| Einstieg | Nach der Aktivierung erscheint für Nutzer mit #19 ein Admin-Hinweis (nur auf Dashboard, Plugins-Liste und Course-Captain-Seiten): „Course Captain ist bereit. Richte den Lernbereich in etwa 10 Minuten ein.“ [Einrichtung starten] · Schließen-Knopf „Hinweis ausblenden“. Bei laufender Einrichtung: „Die Einrichtung ist bei Schritt {n} von 8 stehen geblieben.“ [Einrichtung fortsetzen]. |
| Ausblenden | Schließen setzt `dismissed_by[<user_id>]`; der Hinweis kommt für diese Person nicht wieder. Die Checkliste in der Übersicht (A-01) bleibt, bis die Einrichtung abgeschlossen ist. |
| Wer | Nur #19. Andere Rollen sehen keinen Hinweis; `page=course-captain-setup` liefert 403. |
| Kein Zwang | Ohne Assistent gelten die Defaults aus R0-27; alle Seiten funktionieren. Keine Lizenzabfrage, keine WooCommerce-Pflicht, keine Plugin-Installation, keine Anfragen an den Hersteller (K-23, E-210). |
| Speichern je Schritt | „Speichern und weiter“ schreibt **nur** die Felder dieses Schritts über denselben Settings-Dienst wie die Tabs (R0-27 S-1…S-10: Teil-Update, Validierung alles oder nichts, `_rev`, Risiko-Token, Audit `settings_saved` mit `context = setup`, Schritt-Key). |
| Überspringen | „Überspringen“ speichert nichts. Der Schritt erhält den Status `skipped`; es gelten die Defaults. Ein übersprungener Schritt ist später über die Schrittliste wieder erreichbar. |
| Freie Navigation | Jeder Schritt ist über die Schrittliste erreichbar (keine erzwungene Reihenfolge). Ausnahme: Schritt 8 prüft immer den aktuellen Stand. |
| Wiederaufnahme | Beim erneuten Öffnen springt der Assistent auf den ersten Schritt mit Status `pending` nach dem zuletzt bearbeiteten (AD-T02: Abbruch in Schritt 4 → Wiedereinstieg in Schritt 4). Gespeicherte Werte kommen aus den Settings und sind vorbelegt (A11Y-083). Nicht gespeicherte Eingaben eines Schritts gehen beim Schließen des Tabs verloren; davor warnt `beforeunload`. |
| Erneuter Aufruf | Nach Abschluss öffnet „Einrichtungsassistent öffnen“ den Assistenten mit allen aktuellen Werten. Verlassen ohne Speichern ändert nichts (F4.6): keine Option, kein Audit, kein Status. Seiten und Kursgerüst werden nie doppelt angelegt (Idempotenz über `created`). |
| Abschluss | „Einrichtung abschließen“ in Schritt 8 ist **immer** möglich. Mit offenen Blockern lautet der Status `completed_with_issues`; die Übersicht listet die offenen Punkte (E-266). Danach Weiterleitung auf die Übersicht mit fokussiertem Hinweis „Einrichtung abgeschlossen. {n} Punkte sind noch offen.“ |
| Parallel | Zwei Admins: Konflikte je Tab über `_rev` (R0-27 S-6). `setup_state` hat ein eigenes `_rev`; Schrittliste zeigt „zuletzt bearbeitet von {Anzeigename}, {Zeit}“. |
| Multisite | Netzwerkaktivierung ist blockiert (E-49, F4.8). Je Site eigene `course_captain_setup_state`. |

**Systemzustand** `course_captain_setup_state` (R0-27 §2.7, autoload nein, nie exportiert):

```json
{ "_schema": 1, "_rev": 4,
  "status": "not_started | in_progress | completed | completed_with_issues",
  "purpose": "internal_training | customer_courses | exam_prep | other | null",
  "current_step": "learning_exams",
  "steps": { "purpose_language": {"status": "saved", "at": "2026-11-16T10:02:11Z", "by": 1},
             "addresses": {"status": "saved", "…": "…"}, "enrollment": {"status": "skipped"},
             "learning_exams": {"status": "pending"}, "…": "…" },
  "created": { "catalog_page_id": 12, "first_course_id": 0 },
  "dismissed_by": [1], "started_by": 1, "updated_at": "…", "updated_by": 1 }
```

Unbekannte Schritt-Keys einer neueren Version bleiben beim Speichern erhalten (Passthrough wie R0-27 §2.3).

### 2.2 Schrittfolge

Kap. 16.1 hat sieben Schritte. Die Härtung (E-10) kommt als eigener Schritt 6 dazu; „Demo-Kurs“ wird nach E-102 zu „Erster Kurs“. Ziel: Median ≤ 10 min (E-166, S-07).

| Nr | Key | Titel | Inhalt (Felder) | Ziel-Option(en) | Richtzeit |
|---:|---|---|---|---|---|
| 1 | `purpose_language` | Zweck & Sprache | Zweck (nur Vorbelegung), Website-Sprache und Zeitzone aus WP (nur Anzeige), Profilsprache | `setup_state.purpose`, `general.use_profile_locale` | 1 min |
| 2 | `addresses` | Adressen & Seiten | Lernbereich-Adresse, Kurs-Adresse, Katalogseite, Lernenden-Startseite | `general.*` | 1,5 min |
| 3 | `enrollment` | Einschreibung & Gruppen | Modus manuell/Selbstanmeldung, Zugangsdauer, Gruppen an/aus, Einladungsfrist | `enrollment.*` | 1,5 min |
| 4 | `learning_exams` | Lernen & Prüfungen | Rückmeldung im Lernmodus, Versuche, Bestehensvorschlag, Zeitlimit, Timer-Modus, Lösungsfreigabe | `learning.feedback`, `exams.*` | 1,5 min |
| 5 | `mail_privacy` | E-Mail & Datenschutz | Absender (optional), Testmail, externe Medien (Default „Immer laden“ mit Hinweis, E-45a), Datenexport, Fristen (Übersicht) | `emails.*`, `privacy.*` | 1,5 min |
| 6 | `hardening` | Website-Härtung | Erkannte Sicherheits-Plugins, Login-Limit, Reset-Limit, Registrierungslimit, bekannte Geräte, XML-RPC, Sicherheits-Header | `hardening.*` (E-262) | 1 min |
| 7 | `first_course` | Erster Kurs | Leeres Kursgerüst anlegen / später importieren / überspringen | Kurs-CPT, `setup_state.created` | 1 min |
| 8 | `readiness` | Bereitschaftsprüfung | RDY-01…RDY-15 mit Abhilfe, „Erneut prüfen“, „Einrichtung abschließen“ | `setup_state.status` | 1 min |

**Zweck-Vorbelegung** (Schritt 1, nur Vorschlag, kenntlich als „Vorschlag für deinen Zweck“, nichts wird ohne Speichern übernommen):

| Zweck | Schritt 3 | Schritt 4 |
|---|---|---|
| `internal_training` „Mitarbeitende oder Mitglieder schulen“ | Selbstanmeldung vorgewählt | Rückmeldung immer, Versuche unbegrenzt |
| `customer_courses` „Kurse an Kundinnen und Kunden vergeben“ | manuell | Defaults |
| `exam_prep` „Auf eine Prüfung vorbereiten“ | manuell | Zeitlimit-Vorschlag 60 min, `strict`, Lösungen erst ohne Restversuche |
| `other` / keine Angabe | manuell | Defaults |

### 2.3 Gemeinsamer Rahmen eines Schritts (Wireframe)

```
┌─ WordPress-Admin ─────────────────────────────────────────────────────────┐
│ [Admin-Menü]  │ h1 (tabindex=-1)  Einrichtung · Schritt 3 von 8:          │
│               │                    Einschreibung & Gruppen                │
│               │ nav "Einrichtungsschritte" ─ <ol>                         │
│               │   1 ✓ Zweck & Sprache – gespeichert                       │
│               │   2 ✓ Adressen & Seiten – gespeichert                     │
│               │   3 ● Einschreibung & Gruppen – aktueller Schritt         │
│               │   4 ○ Lernen & Prüfungen – offen                          │
│               │   5 ↷ E-Mail & Datenschutz – übersprungen                 │
│               │   6 ○ …  7 ○ …  8 ○ Bereitschaftsprüfung                  │
│               │ [Fehlerzusammenfassung – nur nach fehlgeschlagenem Speichern] │
│               │ p  Kurzbeschreibung des Schritts (1–2 Sätze)              │
│               │ <form id="cc-step"> Felder (Abschnitt 2.4) </form>        │
│               │ [Zurück] (Link)  [Überspringen] (form="cc-skip")          │
│               │                        [Speichern und weiter] (primär)    │
│               │ Link: Einrichtung verlassen – dein Fortschritt bleibt     │
│               │ Statusregion role=status (dauerhaft, leer)                │
└───────────────┴───────────────────────────────────────────────────────────┘
```

**Bedienregeln (gelten für alle Schritte):**

| Nr. | Regel |
|---|---|
| W-1 | Jeder Schritt ist eine eigene Seite (funktioniert ohne JavaScript, A11Y-010). `document.title` = „Schritt {n} von 8: {Titel} – Einrichtung – Course Captain – {Website}“. Mit JS setzt die Seite nach dem Laden den Fokus auf die `h1` (überspringt das WP-Menü). |
| W-2 | Schrittliste: `<ol>`, aktueller Schritt `aria-current="step"`; Status als Text im Link („– gespeichert“, „– übersprungen“, „– offen“), Symbol `aria-hidden` (K-18). Alle Schritte sind Links. |
| W-3 | **Implizites Absenden (Enter in einem Textfeld) löst immer „Speichern und weiter“ aus.** „Zurück“ ist ein Link; „Überspringen“ gehört über das Attribut `form` zu einem eigenen POST-Formular (`cc-skip`, eigene Nonce). |
| W-4 | Tab-Reihenfolge: `h1` → Schrittliste → Fehlerzusammenfassung (falls vorhanden) → Felder in Lesereihenfolge → Zurück → Überspringen → Speichern und weiter → Einrichtung verlassen (A11Y-002). |
| W-5 | Fehler: Validierung alles oder nichts je Schritt; Fehlerzusammenfassung oben (`role=alert`, `tabindex=-1`, erhält den Fokus) mit Links auf die Felder; am Feld `aria-invalid="true"` und `aria-describedby` auf Hilfetext **und** Fehlertext; Eingaben bleiben erhalten (K-14, A11Y-080, Z-F). Warnungen (z. B. F4.4, F4.7) blockieren nicht und stehen als „Warnung:“ unter dem Feld. |
| W-6 | Risiko-Felder öffnen vor dem Absenden den passenden Dialog aus §4 (mit JS) bzw. eine Bestätigungsseite (ohne JS). **Ausnahme Ersteinrichtung:** Ist für einen Slug noch kein Wert gespeichert und existieren keine veröffentlichten Kurse, entfällt RC-01 (es gibt nichts umzuleiten). |
| W-7 | Ungespeicherte Änderungen: Klick auf Zurück, Schrittliste oder „Einrichtung verlassen“ öffnet UD-1 (§4.2); Schließen des Browser-Tabs nutzt `beforeunload`. |
| W-8 | Nach „Speichern und weiter“: nächster Schritt lädt, Statusregion meldet „Schritt {n} gespeichert.“ (`role=status`). |

### 2.4 Wireframes je Schritt

Spalten: **Feld** (Key) · **Element** · **Label und Hilfetext** (Hilfetext ist sichtbarer Text unter dem Label, kein Tooltip) · **Fehler / Warnung** · **Risiko**.

#### Schritt 1 · Zweck & Sprache

Kurzbeschreibung: „Wofür nutzt du Course Captain? Deine Antwort füllt die nächsten Schritte mit passenden Vorschlägen. Du kannst jeden Vorschlag ändern.“

| Feld | Element | Label und Hilfetext | Fehler / Warnung | Risiko |
|---|---|---|---|---|
| `setup_state.purpose` | Radiogruppe (`fieldset`/`legend`), 4 Optionen | „Zweck“ · Optionen §2.2. Hilfetext: „Wird nur für Vorschläge im Assistenten genutzt und nirgends sonst gespeichert.“ | – (optional) | – |
| WP-Sprache, Zeitzone, Datum | Beschreibungsliste (nur Anzeige) | „Website-Sprache: {Deutsch} · Zeitzone: {Europe/Berlin} · Datum und Uhrzeit: {16. November 2026, 10:05}“. Hilfetext: „Course Captain übernimmt diese Werte aus WordPress.“ Link „In WordPress ändern“ (nur mit `manage_options`) | Warnung (RDY-13), wenn die Zeitzone ein fester Versatz ist: „Die Zeitzone ist als fester Versatz (UTC+1) eingestellt. Fristen verschieben sich dann bei der Zeitumstellung. Wähle in WordPress eine Stadt, z. B. Berlin.“ · Warnung (RDY-14), wenn keine Course-Captain-Übersetzung für die Website-Sprache geladen ist: „Course Captain ist für {Sprache} noch nicht vollständig übersetzt. Teile der Oberfläche erscheinen auf Englisch. Übersetzungen kommen über Dashboard › Aktualisierungen.“ | – |
| `general.use_profile_locale` | Radiogruppe: Automatisch / Immer / Nie | „Sprache für Lernende“ · „Automatisch: Lernende sehen den Lernbereich in ihrer Profilsprache, sobald mehr als eine Sprache installiert ist (derzeit {1} Sprache installiert: {an/aus}).“ Standard: Automatisch | – | – |

#### Schritt 2 · Adressen & Seiten

Kurzbeschreibung: „Lege fest, unter welcher Adresse Lernende ihre Kurse finden.“

| Feld | Element | Label und Hilfetext | Fehler / Warnung | Risiko |
|---|---|---|---|---|
| `general.learning_area_slug` | Textfeld mit sichtbarem Präfix `{home_url}/` und Suffix `/` (Präfix/Suffix als Text, `aria-describedby`) | „Adresse des Lernbereichs“ · „Hier finden Lernende ihre Kurse, Prüfungen und Nachweise. Erlaubt: Kleinbuchstaben, Ziffern, Bindestriche.“ Standard `lernen` | Fehler F-01…F-04 (§3.2) mit Alternativvorschlag als Knopf „„{vorschlag}“ übernehmen“ | RC-01 (nur bei Änderung, W-6) |
| `general.course_slug` | wie oben, Beispiel `{home_url}/kurs/{kursname}/` | „Adresse einzelner Kurse“ · „Steht vor dem Namen jedes Kurses.“ Standard `kurs` | F-01…F-05 | RC-01 |
| `general.catalog_page_id` | Radiogruppe: (a) „Neue Seite „Kurse“ anlegen“ (Standard) · (b) „Vorhandene Seite wählen“ + Auswahlliste (nur veröffentlichte Seiten, Suche) · (c) „Keine eigene Seite – Katalog im Lernbereich zeigen“ | „Kurskatalog“ · „(a) legt eine veröffentlichte Seite mit dem Block „Kurskatalog“ an. Vorhandene Seiten werden nie überschrieben.“ | F-10 (Seite nicht veröffentlicht), F-11 (im Papierkorb), F-12 (keine Bearbeitungsrechte) · Hinweis F-13 (Block fehlt) | – |
| `general.learner_home_page_id` | Radiogruppe: „Mein Lernen im Lernbereich“ (Standard) · „Vorhandene Seite wählen“ | „Startseite für Lernende“ · „Hierhin kommen Lernende nach der Anmeldung über „Mein Lernen“.“ | F-10…F-12 | – |

Wenn (a) gewählt und `setup_state.created.catalog_page_id` auf eine existierende Seite zeigt, steht statt (a): „Seite „{Titel}“ wurde bereits angelegt. [Ansehen]“ – keine zweite Seite (ADM-029).

#### Schritt 3 · Einschreibung & Gruppen

Kurzbeschreibung: „Wie kommen Lernende in einen Kurs? Zugang gibt es immer nur über eine Freischaltung; ohne Freischaltung sehen Gäste höchstens den kostenlosen Teil eines Kurses.“ (INV-S-11, E-15)

| Feld | Element | Label und Hilfetext | Fehler / Warnung | Risiko |
|---|---|---|---|---|
| `enrollment.self_enrollment` | Radiogruppe: „Ich schalte Lernende frei“ (Standard) · „Angemeldete Personen schreiben sich selbst ein (kostenlos)“ | „Einschreibung“ · „Gilt für alle Kurse ohne eigene Einstellung. Einzelne Kurse kannst du später abweichend einstellen. Verkauf über einen Shop richtest du später unter Integrationen ein.“ | Warnung F4.7, wenn WordPress keine Registrierung erlaubt: „Neue Personen können sich auf dieser Website nicht registrieren. Nur Personen mit bestehendem Konto können sich selbst einschreiben. [Registrierung in WordPress einstellen]“ | RC-05 |
| `enrollment.default_access_days` | Radiogruppe: „Unbefristet“ (Standard) · „Befristet auf [Zahl] Tage“ | „Dauer eines neuen Zugangs“ · „Gilt für neue Freischaltungen und Selbstanmeldungen. Bestehende Zugänge bleiben unverändert.“ | F-20 (1–3650) | – |
| `enrollment.groups_enabled` | Kontrollkästchen | „Gruppen mit Plätzen nutzen“ · „Für Firmen, Vereine oder Klassen: Du legst eine Gruppe mit einer Anzahl Plätze an, eine Besitzerin lädt Personen ein. Standard: aus.“ (E-222) | – | RC-06 (nur beim Abschalten mit vorhandenen Gruppen) |
| `enrollment.invite_valid_days` | Zahl, sichtbar unter dem Kästchen (mit JS nur bei „an“, `aria-controls`/`aria-expanded` am Kästchen; ohne JS immer sichtbar) | „Einladungen gültig für [30] Tage“ | F-21 (1–365) | – |

#### Schritt 4 · Lernen & Prüfungen

Kurzbeschreibung: „Diese Werte sind Vorschläge für neue Kurse und Prüfungen. Jede Prüfung kannst du später einzeln einstellen. Laufende Versuche ändern sich nie.“

| Feld | Element | Label und Hilfetext | Fehler / Warnung | Risiko |
|---|---|---|---|---|
| `learning.feedback.visibility` | Radiogruppe: Sofort nach jeder Antwort (Standard) · Nach Abgabe der Übung · Nie | „Rückmeldung beim Üben“ · „Zeigt richtig oder falsch und die richtige Antwort.“ (Erklärungstexte nur mit Pro, E-20; in Free kein Hinweis darauf, E-100) | – | – |
| `exams.max_attempts` | Radiogruppe: Unbegrenzt (Standard) · Höchstens [Zahl] | „Versuche je Prüfung“ | F-30 (1–100) | – |
| `exams.passing_ratio_proposal` | Zahl mit Einheit „%“ (Dezimaltrennzeichen der Sprache) | „Bestehensgrenze (Vorschlag)“ · „Vorschlag für neue Prüfungen. Es gibt keinen amtlichen Standardwert; jede Prüfung speichert ihre eigene Grenze.“ Standard 70 % | F-31, F-32 | – |
| `exams.time_limit_minutes` | Radiogruppe: Kein Zeitlimit (Standard) · [Zahl] Minuten | „Zeitlimit (Vorschlag)“ | F-33 (1–1440) | – |
| `exams.timer_mode` | Radiogruppe: Streng (Standard) · Übung | „Zeitmodus“ · „Übung: Lernende dürfen den Timer ausblenden und verlängern; das Ergebnis gilt nicht als Nachweis.“ | – | – |
| `exams.feedback.show_correct_answers_when` | Radiogruppe: Erst wenn keine Versuche mehr offen sind oder bestanden ist (Standard) · Direkt nach jeder Abgabe | „Richtige Antworten zeigen“ · „Nach jeder Abgabe sieht man immer, welche Fragen richtig oder falsch waren.“ (E-147) | – | – |

#### Schritt 5 · E-Mail & Datenschutz

Kurzbeschreibung: „Course Captain verschickt Mails über WordPress. Prüfe hier, ob sie ankommen, und lege fest, wie externe Videos geladen werden.“

| Feld | Element | Label und Hilfetext | Fehler / Warnung | Risiko |
|---|---|---|---|---|
| `emails.from_name` | Textfeld, Platzhalter zeigt aktuellen WP-Wert | „Absendername (optional)“ · „Leer lassen: WordPress-Standard „{aktueller Name}“.“ (E-219) | F-60 | – |
| `emails.from_address` | E-Mail-Feld, `autocomplete="off"` | „Absenderadresse (optional)“ · „Leer lassen: „{wordpress@…}“. Eine Adresse deiner eigenen Domain kommt seltener im Spam an.“ | F-61 · Warnung F-62 (andere Domain) | – |
| Testmail | Knopf „Testmail an {eigene Adresse} senden“ (eigenes Formular, `form`-Attribut) | „Die Testmail geht an die Adresse deines Profils.“ | Ergebnis in der Statusregion: „Testmail gesendet. Prüfe dein Postfach (auch Spam).“ · Warnung F4.4: „Die Testmail konnte nicht gesendet werden: {Meldung des Versandwegs}. Häufige Ursache: Der Server darf keine Mails senden. Ein SMTP-Plugin hilft. Du kannst die Einrichtung trotzdem fortsetzen.“ · F-63 (Rate-Limit) | – |
| `privacy.external_media_mode` | Radiogruppe: Immer laden (Standard) · Erst nach Klick laden · Nur als Link zeigen | „Externe Videos (z. B. YouTube, Vimeo)“ · Hinweis (immer sichtbar, `role="note"`): „Im Standard lädt der Lernbereich externe Videos sofort. Dabei überträgt der Browser deiner Lernenden ohne vorherige Zustimmung mindestens die IP-Adresse an den Videodienst. Prüfe mit deiner Datenschutzberatung, ob du das so willst. Mit „Erst nach Klick laden“ entsteht die Verbindung erst nach einem Klick bzw. einer Einwilligung in deinem Consent-Tool.“ (E-45, E-45a) | – | – (bewusst kein Risiko-Dialog: der Standard ist die Auftraggeber-Entscheidung) |
| `privacy.auto_export_on_confirm` | Kontrollkästchen (Standard an) | „Datenexport nach bestätigtem Antrag automatisch bereitstellen“ · „Die Person erhält den Download-Link an ihre bestätigte Adresse.“ (E-223) | – | – |
| Fristen | Tabelle (nur Anzeige) mit 5 wichtigsten Fristen aus `privacy.retention` + Link „Alle Fristen unter Einstellungen › Datenschutz & Sicherheit“ | „Aufbewahrungsfristen“ · „Das sind änderbare Vorschläge. Lass sie rechtlich prüfen, bevor du Lerndaten erhebst.“ (E-44, E-51) | – | – |
| Datenschutzseite | Hinweiszeile | „Datenschutzerklärung: {gesetzt: „Titel“ / nicht gesetzt}. Textbausteine für Course Captain findest du in der WordPress-Anleitung zur Datenschutzerklärung.“ [Öffnen] | Warnung RDY-08, wenn keine Seite gesetzt | – |

#### Schritt 6 · Website-Härtung (E-10)

Kurzbeschreibung: „Course Captain kann deine ganze Website gegen häufige Angriffe schützen. Wenn ein Sicherheits-Plugin das schon übernimmt, hält sich Course Captain bei dieser Funktion zurück.“

```
h2 Erkannte Sicherheits-Plugins
   • Wordfence 8.x – hat eigene Login-Sperren  → Login-Limit von Course Captain ruht
   (oder: „Kein Sicherheits-Plugin erkannt.“)
h2 Schutzfunktionen                              [Vorschlag: alle an, außer bei Konflikt]
   [x] Login-Limit                 Status: wird aktiv | ruht wegen {Plugin}
       [x] Bekannte Geräte von Adress-Sperren ausnehmen
   [x] Limit für „Passwort vergessen“
   [x] Limit für Registrierungen      (nur sichtbar, wenn „Jeder kann sich registrieren“ an ist)
   ( ) XML-RPC: WordPress-Standard   (•) XML-RPC abschalten
   Sicherheits-Header:  [x] nosniff  [x] Referrer-Policy  [x] Einbetten nur auf eigener Website  [x] Permissions-Policy
       je Header: „bereits vom Server gesetzt“ oder leer
⚠ Warnung (nur bei Proxy-Verdacht): Alle Anfragen scheinen von 10.0.0.5 zu kommen …  [Proxy eintragen]
ℹ Notfall: Falls du dich aussperrst … define( 'COURSE_CAPTAIN_HARDENING', 'off' ); · wp course-captain hardening disable
[Zurück] [Überspringen] [Speichern und weiter]
```

| Feld | Element | Label und Hilfetext | Fehler / Warnung | Risiko |
|---|---|---|---|---|
| Erkennung | Liste (nur Anzeige), Quelle: aktive Plugins (Wordfence, Solid Security, Limit Login Attempts Reloaded, All-In-One WP Security, Loginizer, Sucuri, Shield) und Filter `course_captain_hardening_conflicts` | „Erkannte Sicherheits-Plugins“ | – | – |
| `hardening.login_limit` | Kontrollkästchen (an = `auto`); Status als Text daneben | „Login-Limit“ · „Sperrt weitere Anmeldeversuche nach zu vielen Fehlversuchen. Grenzwerte siehe Einstellungen › Website-Härtung. Ruht automatisch, solange ein Sicherheits-Plugin eigene Login-Sperren hat.“ Vorschlag im Assistenten: an (ohne Konflikt) | Warnung F-90 (Proxy-Verdacht, RDY-12) | RC-14 |
| `hardening.known_device` | Kontrollkästchen, eingerückt | „Bekannte Geräte von Adress-Sperren ausnehmen“ · „Ein signiertes, technisch notwendiges Cookie erkennt Geräte, auf denen sich die Person schon erfolgreich angemeldet hat. Diese Geräte werden nicht wegen fremder Fehlversuche von derselben Adresse gesperrt.“ Standard an | – | – |
| `hardening.reset_limit` | Kontrollkästchen (an = `auto`) | „Limit für „Passwort vergessen““ · „Begrenzt Anfragen je Adresse und Konto. Die Antwort ist für bekannte und unbekannte Konten gleich.“ | – | – |
| `hardening.xmlrpc` | Radiogruppe: WordPress-Standard · Abschalten | „XML-RPC“ · „Alte Schnittstelle für externe Editoren und Pingbacks; wird oft für Angriffe genutzt.“ | Warnung F-91, wenn eine abhängige Erweiterung erkannt ist (Jetpack u. a.) | RC-15 (Abschalten) |
| `hardening.registration_limit` | Kontrollkästchen, nur bei `users_can_register` | „Limit für Registrierungen“ · „Begrenzt neue Konten je Adresse und Stunde.“ (E-241) | – | – |
| `hardening.site_headers` | `fieldset` mit 4 Kontrollkästchen | „Sicherheits-Header“ · je Kästchen eine Zeile Erklärung, z. B. „Einbetten nur auf eigener Website: verhindert, dass fremde Seiten deine Website in einem Rahmen zeigen.“ | Hinweis „bereits vom Server gesetzt – Course Captain sendet ihn nicht doppelt“ | – |

**Wenn übersprungen:** Härtung bleibt aus (E-260).

**Speichermodell (gilt für Schritt 6 und §3.3.10):** Jede Funktion speichert enum{`auto`, `force`, `off`} (R0-27 §1.3, R0-31 §3.4). Kontrollkästchen an = `auto` (E-240: ruht bei erkanntem Security-Plugin), aus = `off`. `force` (trotz erkanntem Plugin aktiv) ist nur über WP-CLI bzw. eine erweiterte Ansicht mit Bestätigung erreichbar. Vor dem ersten Speichern gilt `hardening.confirmed_at = null`, alles wirkt als `off` (E-260).

#### Schritt 7 · Erster Kurs (E-102)

Kurzbeschreibung: „Leg ein leeres Kursgerüst an, damit du gleich loslegen kannst. Inhalte schreibst du selbst oder importierst sie.“

| Feld | Element | Label und Hilfetext | Fehler / Warnung | Risiko |
|---|---|---|---|---|
| Auswahl | Radiogruppe: (a) „Leeres Kursgerüst anlegen“ (Standard) · (b) „Inhalte nach der Einrichtung importieren“ · (c) „Überspringen – ich lege Kurse später an“ | „Wie möchtest du starten?“ · (a) „Legt einen Kursentwurf mit einem Modul, einer Lektion und einer leeren Abschlussprüfung an. Nichts wird veröffentlicht.“ | – | – |
| Kurstitel | Textfeld (Pflicht bei (a), „Pflichtfeld“ im Label) | „Titel des Kurses“ | F-70 (leer), F-71 (> 200 Zeichen) | – |

Nach dem Speichern bei (a): Statusmeldung „Kursentwurf „{Titel}“ angelegt.“ mit Link „Im Kurseditor öffnen“ (öffnet nach Abschluss). Existiert `setup_state.created.first_course_id` noch, zeigt der Schritt statt (a) „Kursentwurf „{Titel}“ wurde bereits angelegt. [Öffnen]“ (ADM-034). Bei (b) führt der Abschluss auf „Inhalte importieren“ statt auf die Übersicht.

#### Schritt 8 · Bereitschaftsprüfung

```
h1 Einrichtung · Schritt 8 von 8: Bereitschaftsprüfung
p  Course Captain prüft, ob alles bereit ist. Blocker solltest du vor dem ersten Kurs beheben.
role=status  „Prüfung abgeschlossen: 1 Blocker, 2 Warnungen, 12 in Ordnung.“
h2 Blocker (1)
   ✖ Blocker: Geschützte Dateien sind öffentlich abrufbar.
     Dein Server (nginx) liefert Dateien aus dem geschützten Ordner direkt aus.
     Abhilfe: Ergänze diese Regel in der nginx-Konfiguration:  <pre>…</pre> [Regel kopieren]
     [Anleitung öffnen]
h2 Warnungen (2)  … 
h2 In Ordnung (12)   <details> zugeklappt
[Erneut prüfen]                         [Einrichtung abschließen]
```

| Nr. | Prüfung | Schwere bei Befund | Meldung (Abhilfe) |
|---|---|---|---|
| RDY-01 | Geschützte Dateien (Loopback auf Testdatei im geschützten Ordner, erwartet 403/404) | Blocker | „Geschützte Dateien sind öffentlich abrufbar.“ Abhilfe: Regel für Apache/nginx zum Kopieren; geschützte Medien werden bis zur Behebung nicht ausgeliefert (F4.5) |
| RDY-02 | Permalinks nicht „Einfach“ | Blocker | „Der Lernbereich braucht sprechende Adressen. Stelle unter Einstellungen › Permalinks eine andere Struktur als „Einfach“ ein.“ |
| RDY-03 | Lernbereich erreichbar (Loopback `GET /{slug}/` = 200) | Blocker | „Der Lernbereich unter {URL} ist nicht erreichbar ({Status}).“ [Adressregeln neu schreiben] |
| RDY-04 | Speicherordner beschreibbar | Blocker | „Course Captain kann im Ordner für geschützte Dateien nicht schreiben. Bitte deinen Hoster, Schreibrechte für {Pfad relativ zu uploads} zu setzen.“ |
| RDY-05 | Hintergrundjobs (letzter Lauf, `DISABLE_WP_CRON`) | Warnung | „Hintergrundaufgaben liefen seit {Dauer} nicht. Mails und Auswertungen verzögern sich.“ Abhilfe: echter Cron-Job (Doku) |
| RDY-06 | REST für angemeldete Personen; REST für Gäste gesperrt (KOMP-052) | Warnung | „Die Schnittstelle des Lernbereichs antwortet nicht.“ bzw. „Ein Plugin sperrt die Schnittstelle für Gäste. Katalog und Nachweisprüfung funktionieren weiter, Gäste-Funktionen eingeschränkt.“ |
| RDY-07 | E-Mail: erfolgreiche Testmail, Mail-Log-Plugin (KOMP-056), Ausgangsmodus | Warnung / Info | „Noch keine Testmail erfolgreich.“ · „Ein Mail-Log speichert auch Einladungslinks.“ · Info: „Ausgangsmodus „zurückhalten“: Mails werden nicht verschickt, sondern unter Systemstatus angezeigt.“ (E-86) |
| RDY-08 | Datenschutzseite in WordPress gesetzt und veröffentlicht | Warnung | „Es ist keine Datenschutzerklärung festgelegt.“ |
| RDY-09 | HTTPS | Warnung | „Die Website läuft ohne HTTPS. Anmeldungen und Prüfungen sind dann nicht geschützt.“ |
| RDY-10 | Standardrolle ohne Verwaltungsrechte | Warnung | „Neue Konten erhalten die Rolle „{Rolle}“ mit Rechten wie {Cap}. Wähle in WordPress eine Rolle ohne Bearbeitungsrechte.“ |
| RDY-11 | Datenbank-Abfrage-Cache aktiv (E-198) | Warnung | „Ein Datenbank-Cache ist aktiv. Course Captain unterstützt ihn nicht: Zugriffsentscheidungen könnten veraltet sein.“ |
| RDY-12 | Härtung: Proxy-Verdacht ohne vertrauenswürdige Proxys bei aktivem Login-Limit; ruhende Funktionen | Warnung / Info | F-90 · Info „Login-Limit ruht wegen {Plugin}.“ |
| RDY-13 | Zeitzone als fester Versatz | Warnung | wie Schritt 1 |
| RDY-14 | Übersetzung für Website-Sprache vorhanden | Info | wie Schritt 1 |
| RDY-15 | Lernbereich wird von einem Seiten-Cache ausgeliefert (Antwort-Header) | Warnung | „Der Lernbereich wird zwischengespeichert. Lernende könnten fremde oder veraltete Inhalte sehen. Nimm {/lernen/} vom Cache aus.“ |

Regeln: Jede Zeile hat Symbol **und** Wort („Blocker:“, „Warnung:“, „Info:“, „In Ordnung:“), Ursache, Abhilfe und ggf. Link (R0-22 §3.4). „Erneut prüfen“ lädt die Ergebnisse neu und meldet **eine** Zusammenfassung in der Statusregion; der Fokus bleibt auf dem Knopf. Dieselben Prüfungen laufen später unter Systemstatus › Status. Codeblöcke haben einen Knopf „Regel kopieren“ mit Statusmeldung „Kopiert.“

---

## 3. Einstellungen (A-15)

### 3.1 Seitenrahmen (Wireframe, gilt für alle Tabs)

```
┌─ WordPress-Admin ───────────────────────────────────────────────────────────┐
│ h1  Einstellungen                         Link: Einrichtungsassistent öffnen │
│ nav "Einstellungen: Bereiche"                                                │
│   Allgemein | Kursdarstellung | Lernen | Prüfungen | Einschreibung |         │
│   Rollen & Rechte | Zertifikate | E-Mails | Datenschutz & Sicherheit |       │
│   Website-Härtung | Erweitert          (aktiver Tab: aria-current="page")    │
│ [Erfolgs- oder Konfliktmeldung, tabindex=-1]                                 │
│ [Fehlerzusammenfassung role=alert, tabindex=-1 – nur nach Fehler]            │
│ h2 Tabname   p Einleitung (1 Satz: wofür, für wen es gilt)                   │
│ <form id="cc-settings-<tab>">                                                │
│   h3 Abschnitt                                                               │
│     Label                                                                    │
│     [Eingabe]                                                                │
│     Hilfetext (Erklärung + sichtbarer Effekt)                                │
│     Standard: {Wert} · Gilt: {für alle | für alle, je Kurs änderbar} ·       │
│     Hängt ab von: {Feld}            (Metazeile, Teil von aria-describedby)   │
│     [Fehlertext, nur bei Fehler]                                             │
│ </form>                                                                      │
│ [Änderungen speichern] (primär, form=cc-settings-<tab>)                      │
│ [Tab zurücksetzen …] (form=cc-reset-<tab>)   [Rückgängig: Zurücksetzen vom …] │
│ Statusregion role=status (dauerhaft)                                         │
└──────────────────────────────────────────────────────────────────────────────┘
```

| Nr. | Regel |
|---|---|
| T-1 | Jeder Tab ist eine eigene URL `…&tab=<key>` mit eigenem Formular, eigener Nonce `ccap_settings_save_<tab>`, eigenem `_rev` (R0-27 S-1). Sichtbar sind nur Tabs mit Cap (SR-4). Fehlt `tab` oder ist er nicht erlaubt, öffnet der erste erlaubte Tab. |
| T-2 | Pro-Felder (`pro.*`, auch Erklärungstexte nach E-20) erscheinen nur bei aktivem Add-on; bei abgelaufener Lizenz lesend mit Hinweis Z-P. Kein gesperrter Schalter in Free (E-100). |
| T-3 | **UX-Vertrag Kap. 43.2:** Jedes Feld hat Label, Hilfetext (Erklärung und sichtbarer Effekt), Metazeile (Standard, Geltung, Abhängigkeit) und feldnahe Validierung. Hilfetexte stehen sichtbar unter dem Feld, keine Info-Icons mit Tooltip (vermeidet A11Y-055-Probleme). |
| T-4 | Speichern: POST → Redirect → GET. Erfolg: Meldung „Einstellungen „{Tab}“ gespeichert.“ erhält den Fokus (`tabindex=-1`); ohne Änderung: „Keine Änderungen – nichts gespeichert.“ |
| T-5 | Fehler: nichts gespeichert (alles oder nichts, R0-27 S-5); Fehlerzusammenfassung „{n} Eingaben müssen korrigiert werden:“ mit Links auf die Felder, erhält den Fokus; Link setzt den Fokus ins Feld. Am Feld: „Fehler: {Text}“, `aria-invalid`, `aria-describedby` = Hilfetext + Metazeile + Fehler. Eingaben bleiben erhalten (Nutzer-Transient 10 min). Bei genau einem Fehler entfällt die Zusammenfassung nicht (einheitlich), sie hat dann einen Eintrag. |
| T-6 | Warnungen speichern trotzdem; sie stehen nach dem Speichern als „Warnung: …“ am Feld und in der Erfolgsmeldung („gespeichert, 1 Warnung“). |
| T-7 | Konflikt (R0-27 S-6): Meldung „Dieser Tab wurde inzwischen von {Anzeigename} geändert ({Zeit}). Betroffen: {Feldliste}. Deine Eingaben sind noch da. Prüfe die neuen Werte und speichere erneut.“ Knöpfe: „Neue Werte laden (meine Eingaben verwerfen)“ · „Meine Eingaben behalten“ (lädt neuen `_rev`, markiert die betroffenen Felder mit „Inzwischen geändert auf: {Wert}“). |
| T-8 | Risiko-Felder: Vor dem Speichern **ein** Dialog für alle geänderten Risiko-Felder dieses Speichervorgangs (§4); Token an Nutzer, Tab und Werte-Hash gebunden (S-7). Ohne JS: Zwischenseite mit demselben Text und Knöpfen. |
| T-9 | Ungespeicherte Änderungen: Dirty-Tracking je Formular; Klick auf einen anderen Tab, einen Menüpunkt oder „Einrichtungsassistent öffnen“ öffnet UD-1; Browser-Schließen nutzt `beforeunload`. Nach Speichern oder Verwerfen keine Warnung. |
| T-10 | Reset: eigener Knopf in eigenem Formular; Enter in einem Feld löst nie Reset aus. Dialog RC-17; danach Link „Rückgängig“ 30 Tage (R0-27 §2.4). |
| T-11 | Tab-Reihenfolge: `h1` → Assistent-Link → Bereichsnavigation → Meldung → Fehlerzusammenfassung → Felder → Speichern → Zurücksetzen → Rückgängig. Keine klebende Speicherleiste (K-13). |
| T-12 | Abhängige Felder (z. B. „Richtige Antworten zeigen“ bei Rückmeldung „Nie“) werden nicht ausgeblendet, sondern `aria-disabled="true"` mit Begründung im Hilfetext („Nur wirksam, wenn eine Rückmeldung gezeigt wird.“); der gespeicherte Wert bleibt (K-04). |

### 3.2 Meldungskatalog Validierung

Meldungsschlüssel `cc.set.<regel>`; Fehlercode für CLI/Audit `ccap_setting_invalid` mit `field` und `rule` (E-06). Texte nennen Feld, Problem und nächsten Schritt (R0-22 §1.1). Server ist maßgeblich; Client-Vorprüfung (z. B. `required`, `min`/`max`) nur ergänzend.

| Nr. | Regel | Text (de_DE) |
|---|---|---|
| F-01 | Slug-Format | „{Feld}: Erlaubt sind Kleinbuchstaben a–z, Ziffern und Bindestriche, 2 bis 50 Zeichen, nicht am Anfang oder Ende ein Bindestrich. Vorschlag: „{vorschlag}“.“ (Umlaute werden im Vorschlag umgeschrieben: „über“ → „ueber“) |
| F-02 | reserviert | „„{slug}“ ist in WordPress reserviert. Wähle eine andere Adresse, z. B. „{vorschlag}“.“ |
| F-03 | Kollision | „„{slug}“ wird schon von {Objektart} „{Titel}“ verwendet. Wähle eine andere Adresse, z. B. „{vorschlag}“, oder benenne {Objektart} um.“ (Objektart: Seite, Beitrag, Kategorie, Inhaltstyp von {Plugin}) |
| F-04 | gleich | „Lernbereich und einzelne Kurse brauchen verschiedene Adressen.“ |
| F-05 | Fremd-CPT | „„{slug}“ verwendet {Plugin} bereits für eigene Kurse. Wähle eine andere Adresse, z. B. „kurs“.“ |
| F-06 | Historie belegt | „„{slug}“ war früher die Adresse von {Kurs/Lernbereich} und leitet noch weiter. Wähle eine andere Adresse.“ |
| F-10 | Seite nicht veröffentlicht | „Die Seite „{Titel}“ ist nicht veröffentlicht. Veröffentliche sie oder wähle eine andere Seite.“ |
| F-11 | Seite im Papierkorb / gelöscht | „Die gewählte Seite gibt es nicht mehr. Wähle eine andere Seite oder lege eine neue an.“ |
| F-12 | keine Rechte | „Du darfst die Seite „{Titel}“ nicht bearbeiten. Wähle eine andere Seite.“ |
| F-13 | Hinweis Block fehlt | „Hinweis: Auf der Seite „{Titel}“ fehlt der Block „Kurskatalog“. Ohne ihn zeigt die Seite keine Kurse. [Seite bearbeiten]“ |
| F-14 | Textlänge | „{Feld}: höchstens {max} Zeichen (jetzt {n}).“ |
| F-15 | Steuerzeichen | „{Feld}: Zeilenumbrüche sind hier nicht erlaubt.“ |
| F-20…F-23 | Zahlenbereich | „{Feld}: Gib eine ganze Zahl von {min} bis {max} ein.“ (F-20 Zugangsdauer 1–3650 Tage, F-21 Einladung 1–365, F-22 Ausstehend 1–168 h, F-23 Zahl erwartet: „{Feld}: Gib eine Zahl ein, z. B. {beispiel}.“) |
| F-30 | Versuche | „Versuche: Gib eine Zahl von 1 bis 100 ein oder wähle „Unbegrenzt“.“ |
| F-31 | Bestehensgrenze Bereich | „Bestehensgrenze: Gib einen Wert über 0 und höchstens 100 % ein.“ |
| F-32 | Bestehensgrenze Genauigkeit | „Bestehensgrenze: höchstens eine Nachkommastelle, z. B. 66,7 %.“ |
| F-33 | Zeitlimit | „Zeitlimit: Gib 1 bis 1440 Minuten ein oder wähle „Kein Zeitlimit“.“ |
| F-34 | Sperrzeit | „Wartezeit zwischen Versuchen: höchstens 30 Tage.“ |
| F-35 | Kulanz | „Kulanzzeit: 0 bis 600 Sekunden.“ |
| F-36 | Auto-Abgabe | „Automatische Abgabe: 1 bis 365 Tage oder „Aus“.“ |
| F-40 | Farbformat | „{Feld}: Gib die Farbe als #rrggbb an, z. B. #1a5fb4.“ |
| F-41 | Kontrast (Warnung) | „Warnung: {Farbe} hat zu wenig Kontrast zur Schrift ({ist}:1, nötig {soll}:1). Vorschlag: {vorschlag} [Vorschlag übernehmen]. Bis dahin nutzt der Lernbereich die Farbe des Themes.“ |
| F-45 | Logo | „Logo: Nur Bilder (PNG, JPEG, WebP, SVG ohne Skripte) bis 2 MB. Die gewählte Datei hat {größe} MB.“ |
| F-50 | Rollen: eigener Satz | „Du kannst „{Cap-Name}“ nicht vergeben, weil du dieses Recht selbst nicht hast.“ |
| F-51 | Rollen: Administrator | „Die Rolle Administrator hat immer alle Rechte und lässt sich hier nicht ändern.“ |
| F-60 | Absendername | „Absendername: höchstens 100 Zeichen, keine Zeilenumbrüche.“ |
| F-61 | Adresse ungültig | „Absenderadresse: „{wert}“ ist keine gültige E-Mail-Adresse.“ |
| F-62 | Domain (Warnung) | „Warnung: Die Adresse gehört nicht zur Domain dieser Website ({domain}). Ohne passende SPF-/DMARC-Einträge landen Mails oft im Spam.“ |
| F-63 | Testmail-Limit | „Du hast in der letzten Stunde 5 Testmails gesendet. Die nächste ist ab {uhrzeit} möglich.“ |
| F-64 | Pflichtmail | „„{Mailtyp}“ ist eine Pflichtmail und lässt sich nicht abschalten.“ (nur bei manipuliertem POST sichtbar) |
| F-65 | Platzhalter | „Der Platzhalter {{name}} ist in „{Mailtyp}“ nicht erlaubt. Erlaubt: {liste}.“ |
| F-66 | Vorlage leer | „Betreff und Text dürfen nicht leer sein. Zum Zurücksetzen nutze „Werkstext wiederherstellen“.“ |
| F-80 | Begründung fehlt | „Begründung: Pflichtfeld, wenn Nachweise trotz Löschantrag aufbewahrt werden (höchstens 500 Zeichen).“ |
| F-81 | Proxy-Zeile | „Zeile {n}: „{wert}“ ist keine gültige IP-Adresse und kein gültiger Bereich (z. B. 10.0.0.0/8).“ |
| F-82 | Proxy alles | „Zeile {n}: „{wert}“ würde jeder Adresse vertrauen und ist nicht erlaubt.“ |
| F-83 | Proxy Anzahl | „Höchstens 50 Einträge (jetzt {n}).“ |
| F-84 | Frist | „{Frist}: {min} bis {max} {Einheit}.“ |
| F-85 | Debug-Dauer | „Debug-Dauer: 15 Minuten bis 24 Stunden.“ |
| F-86 | Selbstprüfung | „Die Selbstprüfung ist fehlgeschlagen: Der Server hat die Testdatei nicht über {Verfahren} ausgeliefert (Antwort {status}). Prüfe die Serverregel [Anleitung]. Es bleibt bei „PHP“.“ |
| F-87 | Fehlerschwelle | „Fehlerschwelle: mindestens 1 Fehler und 0,1 bis 100 %.“ |
| F-88 | Site-Name | „Der eingegebene Name stimmt nicht mit „{site_name}“ überein.“ |
| F-89 | Bestätigung fehlt | „Diese Änderung braucht eine Bestätigung. Speichere erneut und bestätige den Dialog.“ (Token fehlt, abgelaufen oder Werte geändert) |
| F-90 | Proxy-Verdacht (Warnung) | „Warnung: Alle Anfragen scheinen von {ip} zu kommen (vermutlich ein Proxy oder Load-Balancer). Trage ihn unter Datenschutz & Sicherheit › Vertrauenswürdige Proxys ein, sonst trifft eine Login-Sperre alle Personen gleichzeitig.“ [Proxy eintragen] |
| F-91 | XML-RPC-Abhängigkeit (Warnung) | „Warnung: {Plugin} nutzt XML-RPC. Nach dem Abschalten funktionieren Teile davon nicht mehr.“ |
| F-92 | Härtung per Konstante aus | „Die Website-Härtung ist in wp-config.php abgeschaltet (COURSE_CAPTAIN_HARDENING). Änderungen hier wirken erst, wenn die Konstante entfernt ist.“ |

### 3.3 Wireframes je Tab

Spalten wie §2.4; zusätzlich **Meta** = Metazeile (Standard · Geltung · Abhängigkeit). Typen, Bereiche und Caps stehen in R0-27 §1.3 und werden hier nicht wiederholt. Tab-Cap ist #19, wo nicht anders genannt.

#### 3.3.1 Allgemein (`course_captain_settings_general`)

Einleitung: „Adressen, Seiten und Grundwerte für den Lernbereich.“

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `general.learning_area_slug` | Textfeld mit Präfix/Suffix | „Adresse des Lernbereichs“ · „Hier finden Lernende ihre Kurse. Alte Adressen leiten dauerhaft weiter.“ | `lernen` · alle · ≠ Kurs-Adresse | F-01…F-04, F-06 | RC-01 |
| `general.course_slug` | wie oben | „Adresse einzelner Kurse“ | `kurs` · alle | F-01…F-06 | RC-01 |
| `general.catalog_page_id` | Auswahlliste (Suche) + Knopf „Neue Seite „Kurse“ anlegen“ + Option „Keine eigene Seite“ | „Kurskatalog-Seite“ · „Seite mit dem Block „Kurskatalog“. Inhalte werden nie überschrieben.“ | keine eigene Seite · alle | F-10…F-13 | – |
| `general.learner_home_page_id` | Auswahlliste + Option „Mein Lernen im Lernbereich“ | „Startseite für Lernende“ · „Fehlt die Seite später, nutzt Course Captain den Lernbereich und meldet das im Systemstatus.“ | Lernbereich · alle | F-10…F-12 | – |
| `general.learning_area_title` | Textfeld | „Titel des Lernbereichs“ · „Leer lassen: Titel der Website.“ | leer · alle | F-14 (60) | – |
| `general.use_profile_locale` | Radiogruppe Automatisch/Immer/Nie | „Sprache für Lernende“ (wie Setup) | Automatisch · alle | – | – |
| `general.catalog_group_language_versions` | Kontrollkästchen; nur sichtbar, wenn > 1 Sprache installiert oder Übersetzungsgruppen existieren | „Sprachversionen im Katalog zusammenfassen“ · „Zeigt einen Kurs und seine Übersetzungen als eine Karte mit Sprachauswahl.“ (E-155) | aus · alle | – | – |
| WP-Werte | Anzeige | „Sprache, Zeitzone, Datums- und Zeitformat übernimmt Course Captain aus WordPress.“ [In WordPress ändern] | – | – | – |
| `general.catalog_indexing` | Radiogruppe | „Suchmaschinen“ · „Öffentliche Katalog- und Kursseiten dürfen in Suchmaschinen erscheinen. Lernbereich, Lektionen, Nachweise und Einladungen erscheinen nie.“ Optionen: „Katalog indexierbar“ / „Nichts indexieren“ | indexierbar · alle, je Kurs änderbar | – | – |
| `general.delete_data_on_uninstall` | Kontrollkästchen in eigenem Abschnitt „Plugin entfernen“ (am Tabende, `h3`) | „Beim Löschen des Plugins alle Daten entfernen“ · „Deaktivieren löscht nie etwas. Diese Einstellung wird beim Import von Einstellungen nie übernommen.“ | aus · System | F-88 | **RC-02 (R+)** |

#### 3.3.2 Kursdarstellung (`…_display`)

Einleitung: „Wie Kurse im Theme erscheinen. Course Captain übernimmt Farben und Schriften des Themes, solange du nichts änderst.“

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `display.container_width` | Radiogruppe Theme/Breit/Volle Breite | „Breite des Lernbereichs“ | Theme · alle, je Kurs | – | – |
| `display.breadcrumbs` | Kontrollkästchen | „Brotkrumen-Navigation zeigen“ | an · alle, je Kurs | – | – |
| `display.tokens.accent` | Farbwähler **und** Textfeld `#rrggbb` (beide beschriftet, synchron) + Knopf „Theme-Farbe verwenden“ | „Akzentfarbe“ · „Für Knöpfe und Fortschritt. Course Captain prüft den Kontrast automatisch.“ | Theme · alle | F-40, F-41 | – |
| `display.tokens.accent_text` | wie oben | „Schriftfarbe auf Akzentfarbe“ | Theme · alle | F-40, F-41 | – |
| `display.tokens.font` | Auswahl Theme/Systemschrift | „Schrift“ | Theme | – | – |
| `display.tokens.radius` | Auswahl Theme/keine/klein/mittel | „Eckenrundung“ | Theme | – | – |
| Vorschau | Musterbereich mit Knopf, Fortschrittsbalken, Kurskarte (echte Elemente, `inert`, mit Überschrift „Vorschau“) | – | – | – | – |
| `display.catalog_sort` | Auswahl | „Reihenfolge im Katalog“ · Manuell/Titel/Neueste | Manuell, bei Gleichstand Titel | – | – |
| `display.card_elements` | `fieldset` 4 Kästchen | „Inhalt der Kurskarten“ · „Ohne Auswahl zeigt die Karte nur den Titel.“ | alle vier | – | – |
| `display.require_captions` | Kontrollkästchen | „Untertitel für Videos verlangen“ · „Kurse mit Videos ohne Untertitel lassen sich dann nicht veröffentlichen. Fehlende Alternativtexte blockieren immer.“ (E-131) | aus · alle | – | RC-03 (beim Einschalten) |

#### 3.3.3 Lernen (`…_learning`)

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `learning.course_completion_default` | Radiogruppe | „Kurs gilt als abgeschlossen, wenn …“ · „… alle Pflichtmodule erledigt sind“ / „… zusätzlich eine Prüfung bestanden ist“ · Hilfe: „Wirkt bei der nächsten Veröffentlichung eines Kurses.“ | Pflichtmodule · alle, je Kurs | – (Validator im Kurs, VAL) | – |
| `learning.mastery_algorithm` | Bei genau einer registrierten Version: Text „Smarttrainer v1 (einzige verfügbare Strategie)“; sonst Auswahl | „Wiederholungsstrategie“ · „Bestimmt, welche Fragen beim Üben wiederkommen. Ein Wechsel wirkt nur auf neue Antworten.“ (kein Wirksamkeitsversprechen) | v1 · alle, je Kurs | – | RC-04 |
| `learning.favorites_enabled` | Kontrollkästchen | „Favoriten erlauben“ · „Favoriten sind privat; Trainerinnen und Berichte sehen sie nie.“ | an · alle, je Kurs | – | – |
| `learning.mistake_training_enabled` | Kontrollkästchen | „Fehlertraining anbieten“ | an · alle, je Kurs | – | – |
| `learning.feedback.visibility` | Radiogruppe | „Rückmeldung beim Üben“ (wie Setup) | sofort · alle, je Kurs/Quiz | – | – |
| `learning.feedback.show_correct_answers` | Kontrollkästchen (T-12) | „Richtige Antwort zeigen“ | an · hängt ab von Rückmeldung ≠ Nie | – | – |
| `pro.learning.feedback.show_explanations` | nur mit Pro (E-20) | „Erklärungen zeigen“ | an | – | – |

#### 3.3.4 Prüfungen (`…_exams`)

Einleitung (fett): „Diese Werte sind Vorgaben für Prüfungsregeln, die danach veröffentlicht werden. Bereits veröffentlichte Regeln und laufende Versuche ändern sich nicht.“ (SR-3, INV-S-02)

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `exams.max_attempts` | Radiogruppe Unbegrenzt / Höchstens [Zahl] | „Versuche“ | unbegrenzt · je Prüfung | F-30 | – |
| `exams.cooldown_seconds` | Zahl + Einheit (Minuten/Stunden/Tage) | „Wartezeit zwischen Versuchen“ · „Unterbrochene Versuche können jederzeit fortgesetzt werden.“ | 0 · je Prüfung | F-23, F-34 | – |
| `exams.count_cancelled` | Kontrollkästchen (T-12: nur wirksam mit Versuchslimit) | „Abgebrochene Versuche zählen mit“ · „Versuche, die der Support als technisch fehlerhaft verwirft oder die durch einen Kursrückzug enden, zählen nie.“ | an | – | – |
| `exams.time_limit_minutes` | Radiogruppe Kein / [Zahl] Minuten | „Zeitlimit“ · „Maßgeblich ist die Serverzeit.“ | aus | F-33 | – |
| `exams.timer_mode` | Radiogruppe Streng / Übung | „Zeitmodus“ (wie Setup) | streng | – | – |
| `exams.late_tolerance_seconds` | Zahl (Sekunden) | „Kulanz bei Verbindungsabbruch“ · „Antworten, die bis zu dieser Zeit nach Ablauf ankommen, werden noch gewertet.“ | 120 s | F-35 | – |
| `exams.passing_ratio_proposal` | Zahl „%“ | „Bestehensgrenze (Vorschlag)“ (wie Setup) | 70 % | F-31, F-32 | – |
| `exams.shuffle_questions` | Text „Feste Bögen: Reihenfolge immer fest“ + Auswahl für Poolprüfungen (keine/innerhalb von Abschnitten/alle) | „Fragen mischen (Poolprüfungen)“ | innerhalb von Abschnitten | – | – |
| `exams.shuffle_answers` | Kontrollkästchen | „Antworten mischen“ · „Als fest markierte Antworten (z. B. „Alle genannten“) bleiben an ihrem Platz.“ | an | – | – |
| `exams.feedback.visibility` | Radiogruppe Nach Abgabe / Nie | „Ergebnis-Rückmeldung“ | nach Abgabe | – | – |
| `exams.feedback.show_score` | Kontrollkästchen (T-12) | „Punkte zeigen“ | an | – | – |
| `exams.feedback.show_correct_answers` | Kontrollkästchen (T-12) | „Richtige Antworten zeigen“ | an | – | – |
| `exams.feedback.show_correct_answers_when` | Radiogruppe (T-12) | „Wann richtige Antworten erscheinen“ (wie Setup) | ohne Restversuche oder bestanden | – | – |
| `pro.exams.feedback.show_explanations` | nur mit Pro (E-20) | „Erklärungen zeigen“ | an | – | – |
| `exams.auto_submit_inactive_days` | Radiogruppe Aus / nach [Zahl] Tagen | „Automatisch abgeben bei Inaktivität“ · „Nur für Versuche ohne Zeitlimit. Lernende werden vorher gewarnt.“ | aus | F-36 | – |

#### 3.3.5 Einschreibung (`…_enrollment`)

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `enrollment.self_enrollment` | Radiogruppe (wie Setup) | „Einschreibung“ | manuell · alle, je Kurs | Warnung F4.7 | RC-05 (beim Einschalten) |
| `enrollment.default_access_days` | Radiogruppe | „Dauer eines neuen Zugangs“ | unbefristet · alle, je Kurs | F-20 | – |
| `enrollment.groups_enabled` | Kontrollkästchen | „Gruppen mit Plätzen nutzen“ · „Ausschalten verhindert nur neue Gruppen. Bestehende Gruppen, Plätze und Zugänge bleiben.“ | aus · alle, je Kurs | – | RC-06 (Ausschalten bei ≥ 1 aktiver Gruppe) |
| `enrollment.invite_valid_days` | Zahl | „Gültigkeit von Einladungen“ · „Gilt für neue Einladungen; einzelne Gruppen können kürzer sein.“ | 30 Tage | F-21 | – |
| `enrollment.claim_policy` | Radiogruppe | „Wer darf eine Einladung annehmen?“ · „Jede Person mit dem Link“ / „Nur die eingeladene Adresse“ · Hilfe: „Eine E-Mail-Adresse beweist nicht, wer jemand ist.“ | Link · alle, je Gruppe | – | – |
| `enrollment.pending_grant_timeout_hours` | Zahl (Stunden) | „Wartende Freischaltungen gelten als fehlgeschlagen nach“ | 24 h | F-22 | – |
| `enrollment.source_disabled_policy` | Radiogruppe; **nur sichtbar, wenn eine externe Zugangsquelle registriert ist** | „Wenn eine Zugangsquelle wegfällt“ · „Zugänge behalten“ / „Behalten und zur Prüfung vormerken“ | behalten | – | – |
| `pro.groups.advanced_enabled` | nur mit Pro | „Erweiterte Gruppenverwaltung“ | aus | – | – |

#### 3.3.6 Rollen & Rechte (`…_roles`; Cap #24 + `promote_users`, #19…#23 nur durch `administrator`)

Einleitung: „Welche Rolle darf was in Course Captain. Lernende brauchen keine Rechte: Zugang zu Kursen entsteht nur über Freischaltungen.“

```
h3 Überblick   <table caption="Rechte je Rolle (nur Anzeige)">  Rolle | Anzahl Konten | Rechte (Kurzliste)
h3 Rollen bearbeiten
  <details> <summary>Kursmanager (3 Konten)</summary>
     <fieldset><legend>Kurse und Inhalte</legend> [x] Kurse bearbeiten … </fieldset>
     <fieldset><legend>Lernende und Zugänge</legend> …
     <fieldset><legend>Betrieb und Einstellungen</legend> [ ] Einstellungen verwalten  ⚠ weitreichend …
  </details>
  <details> Support … </details> <details> Autor … </details> <details> Trainer … </details>
  Administrator: „hat immer alle Rechte“ (Text, kein Formular)
p  Standardrolle neuer Konten: Abonnent (aus WordPress) [In WordPress ändern]
```

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `roles.cap_assignments` | je Rolle ein `<details>` mit drei `fieldset`; je Cap ein Kontrollkästchen „{Cap-Name}“ + eine Zeile Erklärung; weitreichende Caps (#14, #19…#24) mit Text „weitreichend“ (nicht nur Symbol) | Cap-Namen in Alltagssprache, z. B. #14 „Fremde Prüfungsantworten lesen (wird protokolliert)“ | Aktivierungs-Defaults R0-07 §3.1 | F-50, F-51 | RC-07 (Vergabe weitreichender Caps an Nicht-Admin-Rollen) |
| `WP:default_role` | Anzeige | „Standardrolle neuer Konten: {Rolle}“ | WP | Warnung (RDY-10) | – |

Weitere Rollen aus Rollen-Editoren (KOMP-055) erscheinen als eigene `<details>`. Kurszuweisungen von Autoren und Trainern erfolgen im Kurs, nicht hier (Hinweiszeile mit Link „Kurse“).

#### 3.3.7 Zertifikate (`…_certificates`)

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `certificates.auto_issue` | Kontrollkästchen | „Nachweise automatisch ausstellen“ · „Gilt für Kurse ohne eigene Einstellung, die eine Abschlussregel haben. Bereits ausgestellte Nachweise ändern sich nicht.“ | aus · alle, je Kurs | – | – |
| `certificates.holder_name_policy` | Radiogruppe | „Name auf der öffentlichen Prüfseite“ · „Nie“ / „Wenn die Person es für ihren Nachweis erlaubt“ · Hilfe: „E-Mail-Adressen erscheinen dort nie.“ | Erlaubnis je Nachweis | – | – |
| `certificates.base_template.issuer_name` | Textfeld | „Aussteller“ | Website-Titel | F-14 (120) | – |
| `certificates.base_template.footer_text` | Textbereich je installierter Sprache (`fieldset` „Fußzeile“, je Sprache ein beschriftetes Feld) | „Fußzeile“ | leer | F-14 (500) | – |
| `certificates.base_template.logo_id` | Knopf „Logo wählen“ (WP-Mediathek) + Vorschaubild mit Alt-Text + Knopf „Logo entfernen“ | „Logo“ | leer | F-45 | – |
| `certificates.base_template.signature_label` | Textfeld | „Unterschriftszeile“ | leer | F-14 (120) | – |
| Vorschau | Link „Vorschau der Basisvorlage“ (gleiches Fenster, Rückweg-Link) | „Änderungen erzeugen eine neue Vorlagenversion; ausgestellte Nachweise behalten ihre Version.“ | – | – | – |

#### 3.3.8 E-Mails (`…_emails`, `course_captain_mail_template_<typ>`)

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `emails.from_name` | Textfeld | wie Setup | WP | F-60 | – |
| `emails.from_address` | E-Mail-Feld | wie Setup | WP | F-61, F-62 | – |
| `emails.footer_text` | Textbereich je Sprache | „Fußzeile aller Course-Captain-Mails“ · „Erlaubt: einfache Formatierung und Links.“ | leer | F-14 (1000) | – |
| `emails.send_format` | Radiogruppe | „Format“ · „HTML mit Textfassung“ / „Nur Text“ | HTML + Text | – | – |
| `emails.types` | Tabelle `caption="Mails von Course Captain"`: Spalten Mail · Empfänger · Art · Versand · Vorlage. Art = „Pflicht“ (Text, kein Schalter, Begründung „Wichtig für Zugang oder Rechte“) oder „Information“ mit Kontrollkästchen „{Mail} senden“ | „Welche Mails verschickt werden“ · „Lernende können Informationsmails selbst abbestellen.“ | §3.2 R0-27 (N-12 aus) | F-64 | RC-08 (Abschalten) |
| Testmail | Knopf (eigenes Formular) | wie Setup | – | F-63 | – |
| Versandstatus | Anzeige „Letzter Fehler: {Zeit}, {Kategorie}“ + Link Systemstatus › Jobs | – | – | – | – |
| `pro.reminders` | nur mit Pro | – | – | – | – |

**Unteransicht „Vorlage bearbeiten“** (`&template=<typ>`): Brotkrumen „Einstellungen › E-Mails › {Mail}“; Sprachauswahl als Bereichsnavigation (je installierte Sprache ein Link); Felder Betreff (F-14 200, F-66), HTML-Text (Textbereich, Code-Editor optional mit Escape-Ausweg, A11Y-004), Textfassung; Liste der erlaubten Platzhalter als Knöpfe „{{course_title}} einfügen“ (fügt an der Cursorposition des zuletzt fokussierten Felds ein, Statusmeldung „Platzhalter eingefügt“); Knöpfe „Vorschau“ (Vorschau unterhalb, Überschrift erhält Fokus), „Testmail mit dieser Vorlage“, „Speichern“, „Werkstext wiederherstellen“ (RC-18). Fehler F-65, F-66.

#### 3.3.9 Datenschutz & Sicherheit (`…_privacy`)

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `privacy.auto_export_on_confirm` | Kontrollkästchen | wie Setup | an | – | – |
| `privacy.auto_erase_on_confirm` | Kontrollkästchen | „Konto nach bestätigtem Löschantrag automatisch löschen“ | aus | – | RC-09 (Einschalten) |
| `privacy.keep_evidence_on_erasure.enabled` | Kontrollkästchen | „Prüfungsnachweise trotz Löschantrag pseudonymisiert aufbewahren“ · „Nur mit Rechtsgrundlage, z. B. gesetzlicher Aufbewahrungspflicht.“ | aus | – | RC-10 |
| `privacy.keep_evidence_on_erasure.reason` | Textbereich (Pflicht, wenn an; „Pflichtfeld, wenn eingeschaltet“ im Label) | „Begründung“ · „Erscheint im Ergebnis jedes Löschantrags.“ | leer | F-80 | – |
| `privacy.retention.*` | Tabelle `caption="Aufbewahrungsfristen"` mit 16 Zeilen: Datenart · Frist (Zahlenfeld, beschriftet „{Datenart}: Frist in {Einheit}“) · erlaubter Bereich; „Lernstände: bis zur Kontolöschung (fest)“ als Text | „Aufbewahrungsfristen“ · „Werte sind Vorschläge; lass sie rechtlich prüfen. Kürzere Fristen wirken beim nächsten Lauf.“ | R0-27 §1.4 | F-84 | RC-11 (Verkürzen) |
| `privacy.backup_max_age_days` | Zahl | „Höchstes Alter deiner Datensicherungen“ · „Damit gelöschte Personen nach einer Wiederherstellung nicht wieder auftauchen, merkt sich Course Captain Löschungen so lange plus 30 Tage.“ | 90 | F-84 | – |
| `privacy.external_media_mode` | Radiogruppe | wie Setup (mit Hinweis) | Immer laden (E-45a) | – | – |
| `privacy.external_media_remember` | Kontrollkästchen, nur sichtbar bei „Erst nach Klick laden“ | „Lernende dürfen „Immer laden“ wählen“ · „Das Kästchen ist bei Lernenden nie vorangehakt.“ | an | – | – |
| `privacy.consent_category` | Auswahlliste, nur sichtbar bei „Erst nach Klick laden“ | „Kategorie im Consent-Tool“ · „Unter dieser Kategorie fragt Course Captain die Einwilligung über die WP Consent API ab.“ (E-246) | Marketing | – | R bei „Funktional“ |
| `privacy.trusted_proxies` | Textbereich, eine Adresse je Zeile; darunter „Deine Anfrage kommt von {REMOTE_ADDR}.“ | „Vertrauenswürdige Proxys“ · „Nur eintragen, wenn deine Website hinter einem eigenen Proxy oder Load-Balancer läuft.“ | leer | F-81…F-83 | RC-12 |
| `privacy.show_avatars` | Kontrollkästchen | „Profilbilder (Gravatar) zeigen“ · „Dabei werden gehashte E-Mail-Adressen an Gravatar übertragen.“ | aus | – | – |
| `privacy.csp_mode` | Radiogruppe | „Content-Security-Policy für Course-Captain-Seiten“ · „Erzwingen“ / „Nur melden (zur Fehlersuche)“ | erzwingen | – | RC-13 (Nur melden) |
| Hinweise | Links | „Verarbeitungsübersicht“ (Systemstatus, E-153) · „Textbausteine für die Datenschutzerklärung“ | – | – | – |

#### 3.3.10 Website-Härtung (`course_captain_settings_hardening`, neu, E-262; Cap #19 **und** `manage_options`)

Einleitung: „Schutz der ganzen Website, nicht nur des Lernbereichs. Funktionen, die ein Sicherheits-Plugin schon übernimmt, ruhen automatisch.“

```
[Hinweis F-92, falls Konstante gesetzt – Formular dann lesend]
h3 Erkannte Sicherheits-Plugins   (Liste wie Setup-Schritt 6)
h3 Anmeldung
   [x] Login-Limit                  Status: aktiv | ruht wegen Wordfence | aus
       [x] Bekannte Geräte von Adress-Sperren ausnehmen
   [x] Limit für „Passwort vergessen“
   Wirksame Grenzwerte (table, nur Anzeige, geändert per Filter course_captain_hardening_limits)
       Fehlversuche je Adresse: 10 in 15 min · je Konto und Adresse: 5 in 15 min · je Konto: 30 in 15 min
       Passwort vergessen je Adresse: 10/h · je Konto und Adresse: 3/h · je Konto: 10/h
   Aktive Sperren: 2   [Alle Sperren aufheben]   (keine Adressen sichtbar)
h3 XML-RPC         ( ) WordPress-Standard  (•) Abschalten
h3 Sicherheits-Header   [x] nosniff [x] Referrer-Policy [x] Einbetten nur auf eigener Website [x] Permissions-Policy
                        je Header Status: „wird gesendet“ | „bereits vom Server gesetzt“ | „aus“  [Header erneut prüfen]
h3 Notfall
   p Falls du dich aussperrst: in wp-config.php  define( 'COURSE_CAPTAIN_HARDENING', 'off' );
     oder per WP-CLI  wp course-captain hardening disable  ·  wp course-captain hardening unlock --all
```

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `hardening.login_limit` | Kontrollkästchen + Statustext (an = `auto`) | wie Setup | aus bis zur Bestätigung (E-260), danach `auto` (E-240) · System | F-90, F-92 | RC-14 (Einschalten) |
| `hardening.known_device` | Kontrollkästchen (T-12) | wie Setup | an · hängt ab von Login-Limit | – | – |
| `hardening.reset_limit` | Kontrollkästchen (an = `auto`) | wie Setup | aus bis zur Bestätigung (E-260) | F-92 | – |
| `hardening.registration_limit` | Kontrollkästchen (an = `auto`), nur bei `users_can_register` | wie Setup | aus bis zur Bestätigung (E-260) | F-92 | – |
| Grenzwerte | Tabelle `caption`, Zeilen mit „per Filter geändert“, wenn abweichend | „Wirksame Grenzwerte“ · „Änderbar nur per Filter (wie E-221).“ | R0-02 INV-C-143/145 | – | – |
| Aktive Sperren | Zahl + Knopf „Alle Sperren aufheben“ (eigenes Formular; Audit) | „Gesperrte Adressen und Konten“ · „Aus Datenschutzgründen speichert Course Captain keine Adressen im Klartext.“ | – | – | – (Bestätigung einfach: „{n} Sperren aufheben?“) |
| `hardening.xmlrpc` | Radiogruppe | wie Setup | WordPress-Standard | F-91, F-92 | RC-15 (Abschalten) |
| `hardening.site_headers` | `fieldset` 4 Kästchen + Status je Header + Knopf „Header erneut prüfen“ (Loopback) | wie Setup | keine bis zur Bestätigung (E-260) | F-92 | – |

Wirkung bei Deaktivierung des Plugins: alle Härtungsfunktionen enden sofort; Course Captain schreibt keine Serverdateien (`.htaccess`, nginx) für die Härtung.

#### 3.3.11 Erweitert (`…_advanced`)

| Feld | Element | Label · Hilfetext | Meta | Fehler | Risiko |
|---|---|---|---|---|---|
| `advanced.debug` | Aus: Auswahl Dauer (15 min, 30 min, 1 h, **2 h**, 4 h, 8 h, 12 h, 24 h) + Knopf „Debug-Protokoll einschalten“; An: Text „An bis {Datum, Uhrzeit} (noch {Dauer})“ + Knopf „Jetzt ausschalten“ (eigene Formulare) | „Debug-Protokoll“ · „Schreibt ausführliche Protokolle ohne Passwörter, Schlüssel und Inhalte von Antworten. Schaltet sich automatisch ab; Dateien werden nach 7 Tagen gelöscht.“ | aus · System | F-85 | – |
| `advanced.import_execution` | Radiogruppe | „Importe ausführen“ · „Im Hintergrund (empfohlen)“ / „Im Browser-Tab“ · „WP-CLI ist immer möglich.“ | Hintergrund | – | – |
| `advanced.import_error_threshold` | zwei Zahlenfelder | „Import anhalten ab“ · „{100} Fehlern oder {2} % der Objekte, je nachdem was größer ist“ | 100 / 2 % | F-87 | – |
| `advanced.file_delivery` | Radiogruppe PHP / X-Sendfile (Apache) / X-Accel-Redirect (nginx); Wechsel startet Selbstprüfung beim Speichern | „Auslieferung geschützter Dateien“ · „PHP funktioniert überall. Die Server-Verfahren sind bei großen Dateien schneller.“ | PHP · System | F-86 | RC-16 |
| `advanced.media_delete_grace_days` | Zahl | „Gelöschte Medien endgültig entfernen nach“ | 7 Tage | F-84 | – |
| `advanced.cron_stale_warning_minutes` | Zahl | „Warnen, wenn Hintergrundaufgaben länger nicht liefen als“ | 180 min | F-84 | – |
| `advanced.rate_limits` | Tabelle (nur Anzeige) mit „per Filter geändert“-Kennzeichen | „Wirksame Grenzwerte“ · „Änderbar nur per Filter (E-221).“ | R0-17 §3.4 | – | – |
| Konstanten | Anzeige | „Ausgangsmodus: {an/zurückhalten/aus} (wp-config.php)“, „Sammeladresse: {Adresse}“ | – | – | – |

### 3.4 Tastaturablauf und Fokusführung (zusammengefasst)

| Situation | Ablauf | Fokus danach |
|---|---|---|
| Tab wechseln (sauber) | Tab-Taste bis Bereichsnavigation, Enter auf Link | neue Seite; Erfolgs-/Hinweismeldung falls vorhanden, sonst Seitenanfang |
| Tab wechseln (geändert) | Enter auf Link → UD-1 | im Dialog auf „Speichern und wechseln“; Escape → zurück auf den Link |
| Speichern erfolgreich | Enter in Textfeld oder auf „Änderungen speichern“ | Erfolgsmeldung |
| Speichern mit Fehler | wie oben | Fehlerzusammenfassung; Enter auf Eintrag → Feld |
| Speichern mit Risiko-Feld | wie oben → Dialog | Dialog „Abbrechen“; nach Bestätigung → Erfolgsmeldung; nach Abbruch → Knopf „Änderungen speichern“, Eingaben bleiben |
| Reset | Tab bis „Tab zurücksetzen …“, Enter → RC-17 | Dialog „Abbrechen“; nach Bestätigung → Meldung „Tab zurückgesetzt. [Rückgängig]“ |
| Abhängiges Feld | Feld bleibt fokussierbar, `aria-disabled`, Hilfetext nennt Grund | – |
| `<details>` (Rollen) | Enter/Leertaste auf `summary` öffnet | bleibt auf `summary` |
| Mediathek (Logo) | WP-Modal; Schließen | zurück auf „Logo wählen“ |
| Vorlage: Platzhalter einfügen | Knopf „{{x}} einfügen“ | zurück in das zuletzt fokussierte Textfeld, Cursor hinter dem Platzhalter |

---

## 4. Risiko-Bestätigungen und Dialoge

### 4.1 Gemeinsame Regeln

| Nr. | Regel |
|---|---|
| D-1 | Natives `<dialog>` mit `showModal()`, `role="alertdialog"`, `aria-labelledby` = Titel (`h2`), `aria-describedby` = Folgetext (K-09). |
| D-2 | Inhalt: konkrete Folge mit Zahlen (serverseitig ermittelt, mit `_n()`), was **nicht** betroffen ist, ob und wie es rückgängig geht. Keine Fachbegriffe ohne Erklärung. |
| D-3 | Knöpfe: Aktionsknopf benennt die Aktion („Adresse ändern“, nie „OK“), danach „Abbrechen“. **Anfangsfokus auf „Abbrechen“** (RC-Dialoge); bei R+ auf dem Eingabefeld. |
| D-4 | Escape = Abbrechen; Fokus zurück auf den Auslöser; keine Änderung, kein Audit, Formulareingaben bleiben. |
| D-5 | Mehrere Risiko-Felder in einem Speichervorgang → ein Dialog mit je einem Abschnitt (`h3` je Feld) und einem gemeinsamen Aktionsknopf „Änderungen bestätigen und speichern“. |
| D-6 | Bestätigung erzeugt ein Token (Nutzer, Tab, Werte-Hash, 10 min gültig). Ändert sich danach ein Wert, ist das Token ungültig (F-89). |
| D-7 | Ohne JavaScript: Bestätigungsseite mit `h1` = Dialogtitel, demselben Text, Formular mit Token und den Knöpfen. |
| D-8 | Audit `settings_saved` enthält `confirmed_risks[]` (RC-Nummern). |

### 4.2 Dialogtexte

| Nr. | Auslöser | Titel | Text | Aktionsknopf |
|---|---|---|---|---|
| **UD-1** | Verlassen mit ungespeicherten Änderungen (T-9, W-7) | „Ungespeicherte Änderungen“ | „Im Tab „{Tab}“ hast du {n} Einstellungen geändert, aber nicht gespeichert: {Liste der Labels}.“ | „Speichern und wechseln“ · „Änderungen verwerfen“ · „Abbrechen“ (Anfangsfokus: „Speichern und wechseln“; schlägt Speichern fehl, bleibt die Seite mit Fehlern stehen) |
| **RC-01** | `general.learning_area_slug` oder `general.course_slug` geändert (nicht bei Ersteinrichtung, W-6) | „Adresse ändern?“ | „Neue Adresse: {home}/{neu}/ (bisher {home}/{alt}/). Links in E-Mails, auf Nachweisen und in Lesezeichen zeigen auf die alte Adresse. Course Captain leitet sie dauerhaft weiter, solange keine andere Seite „{alt}“ verwendet. Suchmaschinen übernehmen die neue Adresse erst nach einiger Zeit.“ | „Adresse ändern“ |
| **RC-02** (R+) | `general.delete_data_on_uninstall` einschalten | „Beim Entfernen alle Daten löschen?“ | „Wenn du Course Captain später unter Plugins löschst, werden unwiderruflich entfernt: {n} Kurse, Lernstände von {m} Personen, {k} Prüfungsversuche, {z} Nachweise sowie Zugänge, Gruppen, Protokoll und alle Einstellungen. Ausgestellte Nachweise lassen sich danach nicht mehr prüfen. Erstelle vorher eine Datensicherung. Deaktivieren löscht nichts.“ Eingabefeld: „Gib zur Bestätigung den Namen der Website ein: {site_name}“ | „Löschen beim Entfernen erlauben“ (`aria-disabled`, bis der Name stimmt; Klick vorher zeigt F-88) |
| **RC-03** | `display.require_captions` einschalten | „Untertitel verpflichtend machen?“ | „{n} veröffentlichte Kurse enthalten Videos ohne Untertitel. Sie bleiben online. Neue Fassungen dieser Kurse lassen sich erst veröffentlichen, wenn die Untertitel ergänzt sind.“ | „Untertitel verlangen“ |
| **RC-04** | `learning.mastery_algorithm` wechseln | „Wiederholungsstrategie wechseln?“ | „Neue Antworten werden ab sofort mit „{neu}“ ausgewertet. Bisherige Lernstände bleiben erhalten. Bei {n} Lernenden kann sich die Auswahl der nächsten Übungsfragen ändern.“ | „Strategie wechseln“ |
| **RC-05** | `enrollment.self_enrollment` einschalten | „Selbstanmeldung einschalten?“ | „Jede Person mit einem Konto auf dieser Website kann sich dann selbst einschreiben und erhält sofort Zugang. Das gilt für {n} veröffentlichte Kurse ohne eigene Einstellung und für alle künftigen Kurse.“ + bei offener Registrierung: „Da sich jede Person auf dieser Website registrieren kann, gilt das praktisch für alle Besucherinnen und Besucher.“ + „Kurse mit der eigenen Einstellung „aus“ bleiben geschlossen.“ | „Selbstanmeldung einschalten“ |
| **RC-06** | `enrollment.groups_enabled` ausschalten bei ≥ 1 aktiver Gruppe | „Gruppen ausschalten?“ | „{n} aktive Gruppen mit {m} belegten Plätzen bleiben bestehen; Mitglieder behalten ihren Zugang und bestehende Gruppen lassen sich weiter verwalten. Neue Gruppen kannst du erst wieder anlegen, wenn du Gruppen einschaltest.“ | „Gruppen ausschalten“ |
| **RC-07** | weitreichende Cap (#14, #19…#24) an eine Nicht-Admin-Rolle | „Weitreichende Rechte vergeben?“ | „Die Rolle „{Rolle}“ ({n} Konten) erhält: {je Cap eine Zeile mit Folge, z. B. „Einstellungen verwalten – kann alle Course-Captain-Einstellungen ändern, auch Datenschutzfristen“, „Fremde Prüfungsantworten lesen – jeder Zugriff wird protokolliert“, „Reparaturen ausführen – kann Migrationen und Wiederholungen von Hintergrundaufgaben starten“}. Die Änderung wirkt sofort für alle Konten mit dieser Rolle.“ | „Rechte vergeben“ |
| **RC-08** | Informationsmail abschalten | „Mail „{Typ}“ abschalten?“ | „Lernende erhalten diese Mail dann nicht mehr. {n} bereits geplante Mails dieses Typs werden nicht verschickt.“ | „Mail abschalten“ |
| **RC-09** | `privacy.auto_erase_on_confirm` einschalten | „Konten automatisch löschen?“ | „Bestätigt eine Person ihren Löschantrag, löscht WordPress ihr Konto ohne weitere Prüfung durch dich, mit Lernständen und Prüfungsversuchen. Konten mit Verwaltungsrechten sind ausgenommen.“ | „Automatisch löschen“ |
| **RC-10** | `privacy.keep_evidence_on_erasure` einschalten | „Nachweise trotz Löschantrag aufbewahren?“ | „Prüfungsversuche und Nachweise von Personen, die ihre Löschung beantragen, werden pseudonymisiert aufbewahrt statt gelöscht. Deine Begründung steht im Ergebnis jedes Antrags: „{Begründung}“. Ohne Rechtsgrundlage ist das nicht zulässig.“ | „Aufbewahren“ |
| **RC-11** | eine Frist in `privacy.retention` verkürzt | „Aufbewahrungsfristen verkürzen?“ | Tabelle: „{Datenart}: {alt} → {neu} – beim nächsten Lauf werden etwa {n} Einträge gelöscht“ je geänderter Frist. „Gelöschte Daten lassen sich nur aus einer Datensicherung wiederherstellen.“ | „Fristen verkürzen“ |
| **RC-12** | `privacy.trusted_proxies` geändert | „Vertrauenswürdige Proxys ändern?“ | „Course Captain übernimmt dann die Adresse aus dem Header X-Forwarded-For, wenn eine Anfrage von {Liste} kommt. Steht hier eine Adresse, die nicht zu deinem eigenen Proxy gehört, können Angreifer Limits und Sperren umgehen. Deine aktuelle Anfrage kommt von {REMOTE_ADDR}.“ | „Proxys speichern“ |
| **RC-13** | `privacy.csp_mode = report_only` | „Content-Security-Policy nur melden?“ | „Course-Captain-Seiten blockieren dann eingeschleuste Skripte nicht mehr, sondern melden sie nur. Nutze das nur kurz, um Konflikte mit dem Theme zu finden.“ | „Nur melden“ |
| **RC-14** | `hardening.login_limit` einschalten | „Login-Limit einschalten?“ | „Nach {10} Fehlversuchen von einer Adresse oder {5} für ein Konto innerhalb von 15 Minuten sind weitere Anmeldeversuche bis zum Ende des Zeitfensters gesperrt. Fällt der Zählerspeicher aus, sind Anmeldung und „Passwort vergessen“ gesperrt, außer auf bekannten Geräten. Notfall: define( 'COURSE_CAPTAIN_HARDENING', 'off' ); in wp-config.php oder wp course-captain hardening disable.“ + bei Proxy-Verdacht (F-90): „Achtung: Alle Anfragen scheinen von {ip} zu kommen. Ohne eingetragenen Proxy trifft eine Sperre alle Personen gleichzeitig.“ | „Login-Limit einschalten“ |
| **RC-15** | `hardening.xmlrpc = disabled` | „XML-RPC abschalten?“ | „Externe Editoren, die WordPress-App und Pingbacks können sich dann nicht mehr verbinden.“ + bei Abhängigkeit: „Diese Erweiterungen nutzen XML-RPC und funktionieren danach nur eingeschränkt: {Liste}.“ | „XML-RPC abschalten“ |
| **RC-16** | `advanced.file_delivery` auf Server-Verfahren nach erfolgreicher Selbstprüfung | „Dateiauslieferung umstellen?“ | „Die Selbstprüfung war erfolgreich. Geschützte Dateien liefert ab jetzt der Webserver aus; die Zugriffsprüfung bleibt bei Course Captain. Nach einem Umzug oder Hosterwechsel können Downloads fehlschlagen; die tägliche Prüfung meldet das im Systemstatus.“ | „Umstellen“ |
| **RC-17** | „Tab zurücksetzen …“ | „Tab „{Tab}“ zurücksetzen?“ | „Diese {n} Einstellungen erhalten wieder ihren Standardwert: {Liste „Label: aktuell → Standard“}. Nicht betroffen: andere Tabs, Einstellungen einzelner Kurse und Prüfungen, Lernstände, Zugänge, Gruppen, Versuche und Nachweise. Du kannst das 30 Tage lang rückgängig machen.“ + Folgetexte enthaltener Risiko-Felder (z. B. RC-01). Bei 0 Abweichungen ist der Knopf „Tab zurücksetzen …“ `aria-disabled` mit Text „Alle Werte entsprechen dem Standard.“ | „Tab zurücksetzen“ |
| **RC-18** | „Werkstext wiederherstellen“ (Mailvorlage) | „Werkstext wiederherstellen?“ | „Deine Fassung von „{Mail}“ ({Sprache}) wird durch den mitgelieferten Text ersetzt. Deine Fassung geht dabei verloren.“ | „Werkstext wiederherstellen“ |

Gleiche Dialoge gelten beim Import von Einstellungen (Systemstatus › Einstellungen sichern) für jedes Risiko-Feld im Diff (R0-27 §2.5).

---

## 5. Testfälle ADM (Ziel-Task R1-32)

Ebenen wie R0-27: **PI** PHPUnit mit WP-Testsuite, **PW** Playwright, **AXE** axe-core in Playwright, **MAN** manuell, **NVDA**/**VO** Screenreader-Durchlauf, **MS** Multisite. „weitere“ = mitbetroffene Tasks. R1-45 ist der vorgeschlagene Härtungs-Task (E-239).

### 5.1 Menü und Navigation

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel | weitere |
|---|---|---|---|---|---|---|
| ADM-001 | P | Admin, Free, Gruppen an | Menü in Reihenfolge §1.2 Pos 1–12, Bezeichnungen und Slugs exakt; kein Pro-Lizenz-Eintrag | PI, PW | R1-32 | R1-03 |
| ADM-002 | P | Pro-Add-on aktiv | „Pro-Lizenz“ an Pos 13; Pro-Screens zwischen Berichte und Inhalte importieren; ohne Add-on keinerlei Pro-Spur im Menü (E-100) | PI | R1-32 | R3-15 |
| ADM-003 | N | `ccap_author` (#1) | sieht nur Übersicht, Kurse (mit Bereichen), Fragenbank, Quizzes & Prüfungen; direkter Aufruf `page=course-captain-settings`, `-status`, `-learners` → 403 ohne Existenzleck | PI | R1-32 | R1-03 |
| ADM-004 | N | `ccap_trainer` | sieht Übersicht, Lernende, Berichte; Einstellungen/Integrationen → 403 | PI | R1-32 | R1-03 |
| ADM-005 | N | `ccap_support` (#13, #18, #21, #22) | Systemstatus mit Bereichen Status, Jobs, Dateischutz, Protokoll, Diagnose; keine Aktionsknöpfe (#23 fehlt); POST einer Reparatur → 403; kein Bereich „Einstellungen sichern“ | PI, PW | R1-32 | R1-38 |
| ADM-006 | P | `ccap_course_manager` ohne #19, mit #24 und `promote_users` | Einstellungen sichtbar, nur Tab „Rollen & Rechte“; Aufruf `tab=general` öffnet Rollen-Tab | PI | R1-32 | R1-36 |
| ADM-007 | P | Aufruf versteckter Seiten (§1.3) | korrekter Menüpunkt hervorgehoben (`parent_file`/`submenu_file`) | PW | R1-32 | – |
| ADM-008 | P | Kurse › Bereichsnavigation | `nav` mit Namen, 4 Links, aktiver mit `aria-current="page"`; keine Pfeiltastensteuerung; Bereiche ohne Cap fehlen | PW, AXE | R1-32 | R1-19 |
| ADM-009 | P | Gruppen aus + 0 Gruppen / aus + 1 Gruppe / an | Menüpunkt fehlt / sichtbar mit Hinweis „Gruppen sind ausgeschaltet; bestehende Gruppen lassen sich verwalten“ / sichtbar (E-265) | PI | R1-32 | R1-35 |
| ADM-010 | N | Konto ohne `ccap_*` | kein Hauptmenü; `page=course-captain` → 403 | PI | R1-32 | R1-03 |
| ADM-011 | P | Eigene Rolle nur mit #21 | Hauptmenü verlinkt Systemstatus (M-1); nur Bereich Protokoll | PI | R1-32 | R1-03 |
| ADM-012 | P | WooCommerce „Admin-Zugang verhindern“ aktiv, Login `ccap_trainer` | CC-Seiten erreichbar (KOMP-102) | PW | R1-32 | R1-30 |
| ADM-013 | P | Integrationen | Karte „Erweiterungen“ ausblendbar (je Nutzer); kein eigener Menüpunkt; keine gesperrten Schalter (N-08) | PW | R1-32 | R1-39 |
| ADM-014 | P | Übersicht und Lernenden-Detail | Direktlink „Protokoll“ bzw. „Verlauf“ führt in Systemstatus › Protokoll mit Filter; nur mit #21 sichtbar | PW | R1-32 | R1-38 |
| ADM-015 | P | de_DE und en_US | alle Menütexte übersetzt (`_x` Kontext `admin menu`), ≤ 22 Zeichen de_DE, kein Umbruch im eingeklappten Menü | PI, MAN | R1-32 | R1-19 |

### 5.2 Setup-Assistent

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel | weitere |
|---|---|---|---|---|---|---|
| ADM-020 | P | Aktivierung (einzeln und Sammelaktivierung) | keine Weiterleitung (M-7); Hinweis nur für #19 auf Dashboard, Plugins, CC-Seiten; Ausblenden je Nutzer; Checkliste in der Übersicht bleibt | PW | R1-32 | – |
| ADM-021 | N | Kursmanager | kein Hinweis; `page=course-captain-setup` → 403 | PI | R1-32 | R1-03 |
| ADM-022 | P | Assistent mit allen Vorschlägen durchlaufen | 8 Schritte; Felder materialisiert; je Schritt ein Audit `settings_saved` mit `context=setup`; Status `completed` | PI, PW | R1-32 | R1-31 |
| ADM-023 | P | AD-T01: Selbstanmeldung + `/lernen/`, WP-Registrierung aus | RC-05 erscheint und wird bestätigt; Warnung F4.7 sichtbar; Werte gespeichert; Zeit im Nutzertest ≤ 10 min (UX-060) | PW, MAN | R1-32 | R1-29 |
| ADM-024 | P | AD-T02: Tab in Schritt 4 schließen, erneut öffnen | Hinweis „fortsetzen“; Einstieg in Schritt 4; Schritte 1–3 vorbelegt und „gespeichert“ (A11Y-083) | PW | R1-32 | R1-29 |
| ADM-025 | P | Schritt 5 überspringen | Status `skipped`; Optionen `emails`/`privacy` unverändert (bytegleich); Schrittliste „übersprungen“; Schritt später erreichbar | PI | R1-32 | – |
| ADM-026 | P | Erneuter Aufruf nach Abschluss, durchklicken ohne Speichern, verlassen | keine Option, kein Audit, kein Status geändert (F4.6) | PI | R1-32 | – |
| ADM-027 | N | Seite „lernen“ existiert; Slug `lernen` | F-03 mit Vorschlag; nichts gespeichert; Eingaben erhalten; „Vorschlag übernehmen“ setzt Feld (F4.1) | PW | R1-32 | R1-37 |
| ADM-028 | N | Slugs `wp-admin`, `feed`, `-lernen`, `über`, gleiche Slugs für Lernbereich/Kurs | F-02 bzw. F-01 (Vorschlag `ueber`) bzw. F-04 | PI | R1-32 | – |
| ADM-029 | P | Katalog „Neue Seite anlegen“, danach Assistent erneut mit (a) | genau eine veröffentlichte Seite mit Katalog-Block; zweiter Lauf zeigt „bereits angelegt“, keine zweite Seite | PI | R1-32 | – |
| ADM-030 | N | gewählte Katalogseite Entwurf / Papierkorb / fremde ohne `edit_page` | F-10 / F-11 / F-12 (F4.2) | PI | R1-32 | – |
| ADM-031 | P | Schritt 3 Gruppen an | `groups_enabled = true`; Menüpunkt Gruppen erscheint; AD-T06 durchführbar | PW | R1-32 | R1-35 |
| ADM-032 | P | Zweck `exam_prep` | Schritt 4 vorbelegt nach §2.2 mit Kennzeichnung „Vorschlag für deinen Zweck“; ohne Speichern nichts übernommen | PW | R1-32 | – |
| ADM-033 | P/N | Testmail: `wp_mail` liefert `false`; 6. Testmail in einer Stunde | Warnung F4.4, „Speichern und weiter“ möglich; F-63 | PI, PW | R1-32 | R1-21 |
| ADM-034 | P | Schritt 7 (a) „Grundkurs“; Assistent erneut | Kursentwurf mit 1 Modul, 1 Lektion, Prüfungsregel-Entwurf ohne Fragen; nichts veröffentlicht; Validator meldet erwartete Blocker; zweiter Lauf legt nichts doppelt an | PI | R1-32 | R1-26 |
| ADM-035 | N | Schritt 7 (a) ohne Titel / 201 Zeichen | F-70 / F-71 | PI | R1-32 | – |
| ADM-036 | P | AD-T03: nginx ohne Schutzregel | RDY-01 Blocker mit kopierbarer Regel; „Einrichtung abschließen“ möglich → `completed_with_issues`; Übersicht listet Blocker; geschützte Medien bleiben gesperrt (F4.5) | PW (nginx) | R1-32 | R1-14 |
| ADM-037 | P | Permalinks „Einfach“ → beheben → „Erneut prüfen“ | RDY-02 Blocker, danach „In Ordnung“; eine Zusammenfassung in der Statusregion; Fokus bleibt auf dem Knopf | PW | R1-32 | – |
| ADM-038 | P | Fixture mit je einem Befund pro RDY-Prüfung | alle 15 Prüfungen liefern Schwere, Wort + Symbol, Ursache, Abhilfe; gleiche Ergebnisse unter Systemstatus › Status | PI | R1-32 | R1-38 |
| ADM-039 | N | Admin A und B speichern Schritt 2 auf Basis desselben `_rev` | B erhält T-7-Konflikt; `setup_state` zeigt „zuletzt bearbeitet von A“ | PI | R1-32 | R1-36 |
| ADM-040 | N | Schritt-POST ohne/mit falscher Nonce; Nutzer ohne #19 | 403; nichts gespeichert; `setup_state` unverändert | PI | R1-32 | R1-03 |
| ADM-041 | P | Enter in einem Textfeld jedes Schritts | löst immer „Speichern und weiter“ aus, nie Zurück/Überspringen (W-3) | PW | R1-32 | – |
| ADM-042 | P | JavaScript aus | alle Schritte bedienbar; Risiko-Felder über Bestätigungsseite (D-7) | PW (`javaScriptEnabled: false`) | R1-32 | R1-19 |
| ADM-043 | P | Schrittliste | `ol`, `aria-current="step"`, Status als Text; `document.title` nach W-1; Fokus auf `h1` | PW, AXE | R1-32 | R1-19 |
| ADM-044 | P | Multisite: Netzwerkaktivierung / Aktivierung je Site | blockiert mit Erklärung (F4.8) / je Site eigener `setup_state` | MS | R1-32 | R1-40 |
| ADM-045 | P | kompletter Assistent mit HTTP-Mitschnitt | keine Lizenzabfrage, kein WooCommerce-Hinweis als Pflicht, keine Plugin-Installation, keine externen Anfragen außer Loopback (E-210) | PI | R1-32 | R1-39 |
| ADM-046 | P | `setup_state` einer neueren Version mit unbekanntem Schritt-Key; Plugin-Update | unbekannter Key bleibt erhalten; Status und Schritte unverändert | PI | R1-32 | R1-40 |
| ADM-047 | P | Website-Sprache de_DE ohne CC-Übersetzung; Zeitzone „UTC+1“ | Warnungen RDY-14/RDY-13 in Schritt 1 und 8; en_US ohne Übersetzungswarnung | PI | R1-32 | R1-19 |
| ADM-048 | P | „Einrichtung verlassen“ mit Änderungen | UD-1; „Änderungen verwerfen“ → Übersicht, Status `in_progress` | PW | R1-32 | – |
| ADM-049 | P | Schritt 5 ohne Änderung speichern; danach „Erst nach Klick laden“ wählen | Default `always_load` gespeichert, Hinweis zur Datenübertragung sichtbar und per Screenreader erreichbar; nach Umschalten erscheinen „Immer laden“-Kästchen und Consent-Kategorie (E-45a, E-246) | PW, PI | R1-32 | R1-07 |

### 5.3 Website-Härtung (E-10)

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel | weitere |
|---|---|---|---|---|---|---|
| ADM-050 | P | Neuinstallation ohne Assistent | keine Härtung aktiv: XML-RPC wie WP, keine Header, keine Login-Zählung (E-260) | PI | R1-32 | R1-45 |
| ADM-051 | P | Schritt 6 ohne Sicherheits-Plugin, Vorschläge speichern; 11 Fehlanmeldungen einer Adresse in 15 min | Login-Limit aktiv; 11. Versuch gesperrt mit neutraler Meldung; Audit | PI | R1-32 | R1-45 |
| ADM-052 | P | Wordfence aktiv | Erkennung gelistet; Login-/Reset-Limit „ruht wegen Wordfence“; CC zählt und sperrt nicht (keine doppelte Sperre, K43-48); XML-RPC und Header weiter wählbar | PI, PW | R1-32 | R1-45, R1-30 |
| ADM-053 | N | Proxy-Verdacht (alle Anfragen von 10.0.0.5, `X-Forwarded-For` gesetzt), keine Proxys eingetragen; Login-Limit einschalten | F-90 am Feld; RC-14 enthält Proxy-Absatz; Abbrechen → nichts gespeichert; RDY-12 Warnung | PW | R1-32 | R1-45 |
| ADM-054 | P | Jetpack aktiv; XML-RPC abschalten | F-91; RC-15 nennt Jetpack; nach Bestätigung liefert `xmlrpc.php` keine Methoden, Pingbacks aus | PI | R1-32 | R1-45 |
| ADM-055 | P | Server sendet `X-Content-Type-Options` bereits | Status „bereits vom Server gesetzt“; CC sendet ihn nicht doppelt; übrige gewählte Header auf Front und Admin vorhanden | PI | R1-32 | R1-45 |
| ADM-056 | N | Zählerspeicher fällt aus, Login-Limit an | Anmeldung und „Passwort vergessen“ fail-closed mit neutraler Meldung (E-46); Anmeldung von bekanntem Gerät möglich (E-263); Lern-Limits fail-open | PI | R1-32 | R1-45, R1-42 |
| ADM-057 | P | `COURSE_CAPTAIN_HARDENING = 'off'`; CLI `hardening disable`, `unlock --all` | alle Funktionen aus; Tab lesend mit F-92; CLI mit Audit `system:cli` | PI | R1-32 | R1-45 |
| ADM-058 | P | „Alle Sperren aufheben“ | Bestätigung „{n} Sperren aufheben?“; Zähler leer; Audit; keine Adresse im UI oder Audit | PI, PW | R1-32 | R1-45 |
| ADM-059 | N | Kursmanager; Admin ohne `manage_options` (Rollen-Editor) | Tab „Website-Härtung“ fehlt; POST → 403 | PI | R1-32 | R1-03 |
| ADM-060 | P | 4. „Passwort vergessen“ für Konto+Adresse in 1 h; unbekanntes Konto | gleiche neutrale Bestätigung, keine Mail beim gesperrten Versuch, keine Enumeration | PI | R1-32 | R1-45 |
| ADM-061 | P | Plugin deaktivieren | alle Härtungswirkungen enden sofort; keine Serverdateien geschrieben | PI | R1-32 | R1-45 |
| ADM-062 | P | Härtungs-Tab speichern | eigene Option `course_captain_settings_hardening` autoload, ≤ 0,5 KB; Gesamt-Autoload weiter ≤ 4 KB (SET-009) | PI | R1-32 | R1-31 |

### 5.4 Einstellungsrahmen

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel | weitere |
|---|---|---|---|---|---|---|
| ADM-070 | P | Einstellungen öffnen | Bereichsnavigation mit 11 Tabs in Reihenfolge §3.1, `aria-current`; je Tab eigene URL; nur erlaubte Tabs | PW, AXE | R1-32 | – |
| ADM-071 | P | Tab speichern | PRG; Erfolgsmeldung nennt Tab und erhält Fokus; ohne Änderung „Keine Änderungen“ und kein Audit | PW | R1-32 | R1-31 |
| ADM-072 | N | ein ungültiges Feld unter fünf gültigen | nichts gespeichert; Zusammenfassung mit Link fokussiert; Link → Feld; `aria-invalid` + `aria-describedby`; Eingaben erhalten (SET-004) | PW | R1-32 | R1-36 |
| ADM-073 | P | Feld ändern, anderen Tab anklicken | UD-1; Escape → Seite bleibt, Eingaben da, Fokus auf Link; „Verwerfen“ → Wechsel ohne Speichern; „Speichern und wechseln“ → gespeichert und gewechselt; bei Fehler bleibt die Seite mit Fehlern | PW | R1-32 | R1-29 |
| ADM-074 | P | Reload/Schließen mit und ohne Änderungen | `beforeunload` nur bei Änderungen; nach Speichern keine Warnung | PW | R1-32 | – |
| ADM-075 | P | Reset „Prüfungen“, danach „Rückgängig“ | RC-17 listet Abweichungen; nur dieser Tab; Rückgängig stellt her; Enter in einem Feld löst nie Reset aus (SET-014) | PW | R1-32 | R1-36 |
| ADM-076 | N | Reset-Dialog abbrechen; Tab ohne Abweichung | nichts geändert, kein Audit; Knopf `aria-disabled` mit Begründung | PW | R1-32 | – |
| ADM-077 | N | `_rev`-Konflikt | T-7-Meldung mit Feldliste und Anzeigename; „Meine Eingaben behalten“ markiert geänderte Felder | PW | R1-32 | R1-36 |
| ADM-078 | P | zwei Risiko-Felder in einem Speichervorgang | ein Dialog mit zwei Abschnitten (D-5); Audit `confirmed_risks` | PW | R1-32 | – |
| ADM-079 | N | Risiko-Feld per manipuliertem POST ohne Token; Token mit anderem Werte-Hash; abgelaufenes Token | F-89; nichts gespeichert | PI | R1-32 | R1-36 |
| ADM-080 | P | Schema-Scan aller Felder | jedes Feld hat Label, Hilfetext, Metazeile; `aria-describedby` verweist auf existierende IDs; kein Tooltip-Hilfetext | PI, AXE | R1-32 | R1-31 |
| ADM-081 | P | Free / Pro aktiv / Pro-Lizenz abgelaufen | Free: kein `pro.*`, keine Erklärungs-Schalter (E-20, E-100); Pro: Felder sichtbar; abgelaufen: lesend mit Z-P | PI, PW | R1-32 | R3-15 |
| ADM-082 | P | AD-T04 nur mit Tastatur | Prüfungen-Feedback „nach Abgabe“ gespeichert; Änderung im anderen Tab verworfen über UD-1 | PW, MAN | R1-32 | R1-29 |
| ADM-083 | P | 320 px, 200 % Text, Textabstand-CSS, `forced-colors` | kein waagerechtes Scrollen (außer Tabellen-Container), Fokus sichtbar, nichts abgeschnitten (A11Y-050…052) | PW, MAN | R1-32 | R1-19 |
| ADM-084 | P | Abhängiges Feld (Rückmeldung „Nie“) | „Richtige Antwort zeigen“ fokussierbar, `aria-disabled`, Grund im Hilfetext, Wert bleibt gespeichert (T-12) | PW | R1-32 | – |
| ADM-085 | P | Warnung F-62 beim Speichern | gespeichert, Meldung „gespeichert, 1 Warnung“, Warnung am Feld | PW | R1-32 | – |

### 5.5 Tabs im Einzelnen

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel | weitere |
|---|---|---|---|---|---|---|
| ADM-090 | P/N | Allgemein: Lernbereich-Slug ändern, RC-01 abbrechen / bestätigen | abbrechen: alter Slug; bestätigen: neuer Slug, Rewrite-Flush, alter Slug leitet 301 (E-220) | PW, PI | R1-32 | R1-37 |
| ADM-091 | P/N | Allgemein: Datenlöschung einschalten, falscher / richtiger Site-Name | Knopf `aria-disabled`, F-88 / gespeichert mit Audit; Import von Einstellungen setzt den Wert nie | PW, PI | R1-32 | R1-23 |
| ADM-092 | P | Katalogseite ohne Katalog-Block | Hinweis F-13 (kein Fehler), speichern möglich | PW | R1-32 | – |
| ADM-093 | P | Kursdarstellung: Akzentfarbe mit 2,1:1 | Warnung F-41 mit Vorschlag; „Vorschlag übernehmen“ setzt konformen Wert; bis dahin Theme-Farbe im Lernbereich (A11Y-042) | PW, PI | R1-32 | R1-19 |
| ADM-094 | P | Untertitel verlangen bei 3 Kursen mit fehlenden Untertiteln | RC-03 nennt „3 veröffentlichte Kurse“ (`_n`) | PW | R1-32 | R1-26 |
| ADM-095 | P | Lernen: eine registrierte Strategie | Strategie als Text, kein Auswahlfeld, kein RC-04 | PW | R1-32 | – |
| ADM-096 | P/N | Prüfungen: Grenze 0, 101, „70,55“, „66,7“ (de_DE), „66.7“ (en_US) | F-31, F-31, F-32, gespeichert als 667/1000, gespeichert | PI | R1-32 | R1-19 |
| ADM-097 | P | Prüfungen: Versuche auf 3 ändern | Hinweis „gilt für danach veröffentlichte Regeln“; bestehende Regel und laufender Versuch unverändert (INV-S-02) | PI | R1-32 | R1-33 |
| ADM-098 | P | Einschreibung: Selbstanmeldung an bei 4 veröffentlichten Kursen, Registrierung offen | RC-05 mit „4 veröffentlichte Kurse“ und Absatz zur offenen Registrierung | PW | R1-32 | R1-13 |
| ADM-099 | P | Gruppen aus bei 2 aktiven Gruppen | RC-06 mit Zahlen; Gruppen, Plätze, Grants bleiben | PI, PW | R1-32 | R1-35 |
| ADM-100 | P/N | Rollen: #19 an Kursmanager; #23 durch Nicht-Admin vergeben; Administrator-Zeile | RC-07 mit Kontenzahl und Folgen; F-50; Administrator nur Text, POST abgelehnt (F-51) | PI, PW | R1-32 | R1-03 |
| ADM-101 | P | Rollen-Tab per Tastatur | `details`/`summary` bedienbar, Kontrollkästchen mit vollem Namen „{Rolle}: {Cap}“, „weitreichend“ als Text | PW, NVDA | R1-32 | R1-29 |
| ADM-102 | N | Zertifikate: Logo 3 MB / PDF / SVG mit Skript | F-45 je Fall; Mediathek-Fokus kehrt zurück | PI, PW | R1-32 | R1-44 |
| ADM-103 | P/N | E-Mails: ungültige Adresse; fremde Domain; Pflichtmail per POST abschalten; Informationsmail abschalten | F-61; Warnung F-62; F-64 (Server lehnt ab); RC-08 mit Anzahl geplanter Mails | PI, PW | R1-32 | R1-21 |
| ADM-104 | P/N | Mailvorlage: unbekannter Platzhalter; leerer Betreff; Platzhalter per Tastatur einfügen; Werkstext | F-65; F-66; Einfügen an Cursorposition mit Statusmeldung; RC-18 | PW | R1-32 | R1-21 |
| ADM-105 | P | Datenschutz: Protokollfrist 4 → 2 Jahre | RC-11 mit Anzahl betroffener Einträge; Abbrechen ändert nichts; Bestätigen wirkt im nächsten Lauf | PI, PW | R1-32 | R1-34 |
| ADM-106 | N | Proxys: Zeile 3 ungültig, `0.0.0.0/0`, 51 Einträge | F-81 mit Zeilennummer, F-82, F-83; gültige Liste → RC-12 mit eigener Adresse | PI, PW | R1-32 | R1-42 |
| ADM-107 | N/P | Nachweise aufbewahren ohne / mit Begründung | F-80 / RC-10 zeigt Begründung | PW | R1-32 | R1-34 |
| ADM-108 | P | CSP „Nur melden“ | RC-13; danach Header `Content-Security-Policy-Report-Only` nur auf CC-Seiten | PI | R1-32 | R1-16 |
| ADM-109 | P/N | Erweitert: Debug 2 h; Ablauf; manipulierte Dauer 48 h | Anzeige Endzeit; nach Ablauf automatisch aus; F-85 | PI | R1-32 | R1-38 |
| ADM-110 | P/N | Dateiauslieferung X-Accel: Selbstprüfung scheitert / gelingt | F-86, bleibt PHP / RC-16, gespeichert | PI | R1-32 | R1-14 |
| ADM-111 | P | Rate-Limits per Filter geändert | Tabelle zeigt wirksame Werte mit „per Filter geändert“; keine Eingabefelder (E-221) | PI | R1-32 | R1-42 |

### 5.6 Barrierefreiheit und Sprache (übergreifend)

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel | weitere |
|---|---|---|---|---|---|---|
| ADM-120 | P | alle 11 Tabs, 8 Schritte, alle Dialoge geöffnet | axe-core WCAG 2.2 A/AA: 0 Verstöße | AXE | R1-32 | R1-19 |
| ADM-121 | P | jeden Dialog öffnen | K-09: Anfangsfokus nach D-3, Fokusfalle, Escape, Rückkehr zum Auslöser; Name und Beschreibung angesagt | PW, NVDA | R1-32 | R1-19 |
| ADM-122 | P | alle Fehlertexte F-xx | nennen Feld und Abhilfe, beginnen mit „Fehler:“/„Warnung:“/„Hinweis:“, nie nur Farbe | PI (Katalog-Scan), MAN | R1-32 | R1-19 |
| ADM-123 | P | Assistent und Einstellungen mit NVDA + Firefox und VoiceOver + Safari (AD-T01, AD-T02, AD-T04) | jede Aufgabe ohne sehende Hilfe lösbar (E-166) | NVDA, VO | R1-32 | R1-29 |
| ADM-124 | P | RTL-Sprache (ar) | Schrittliste, Bereichsnavigation, Knopfleisten gespiegelt; Zahlen, Slugs, Code LTR (I18N-R-12/13) | PW (Screenshot), MAN | R1-32 | R1-19 |
| ADM-125 | P | Dialoge und Meldungen mit 1 und n Objekten | Mengen über `_n()`, keine zusammengesetzten Sätze, Übersetzerkommentare (I18N-R-03/04/06) | PI (Lint) | R1-32 | R1-19 |
| ADM-126 | P | Statusregionen | je Seite genau eine `role=status`, Erfolgsmeldungen nicht doppelt angesagt; Fehlerzusammenfassung `role=alert` (K-05) | PW, NVDA | R1-32 | R1-19 |
| ADM-127 | P | Zielgrößen und Abstände | alle Knöpfe/Kästchen ≥ 24 × 24 px oder Abstand (K-11) | PW | R1-32 | R1-19 |

---

## 6. Entscheidungen (beschlossen)

Alle Fragen sind entschieden (`ENTSCHEIDUNGEN.md` §39 und §36, „Von Claude entschieden“, 16.11.2026); Empfehlung jeweils übernommen, bei E-240 angepasst an E-260. Genutzt außerdem: E-10, E-19, E-20, E-45/E-45a, E-46, E-49, E-89, E-100, E-102, E-131, E-166, E-197, E-202, E-207, E-210, E-219…E-224, E-241, E-242, E-246, E-254, E-268, E-269.

| Frühere ID | Beschluss | Inhalt | Wirkt in | Tasks |
|---|---|---|---|---|
| D30-1 | **E-239** | neue Task **R1-45** „Härtungsmodul“ in Free; R1-32 liefert Tab und Setup-Schritt | §2.4 Schritt 6, §3.3.10, §5.3 | R1-32, R1-45 |
| D30-2 | **E-260** (mit **E-240**) | alles aus, bis der Admin im Assistenten oder Tab speichert; der Assistent schlägt „an“ vor (außer bei erkanntem Security-Plugin), danach je Funktion `auto` | Schritt 6, §3.3.10, ADM-050 | R1-32, R1-45 |
| D30-3 | **E-261** (ergänzt E-241) | Umfang R1: Login-Limit, Reset-Limit, bekanntes Gerät, XML-RPC, 4 Security-Header; Registrierungslimit nach E-241; Enumeration und Anwendungspasswörter R4-Pool | Schritt 6, §3.3.10 | R1-45, R0-04 |
| D30-4 | **E-262** | eigener 11. Tab „Website-Härtung“ mit eigener Option, Cap #19 + `manage_options` | §3.3.10 | R1-32, R0-27 |
| D30-5 | **E-263** (ergänzt E-242) | Login auf bekannten Geräten bleibt bei fail-closed möglich; Notausschalter per Konstante `COURSE_CAPTAIN_HARDENING` und WP-CLI | §3.3.10, ADM-056/057 | R1-45 |
| D30-6 | **E-264** | Bereichsnavigation in bestehenden Menüpunkten; Protokoll unter Systemstatus | §1.1, §1.4 | R1-32 |
| D30-7 | **E-265** | Menüpunkt „Gruppen“ ausblenden, solange aus und keine Gruppe existiert | §1.2 | R1-32, R1-35 |
| D30-8 | **E-266** | Abschluss trotz Blocker erlaubt („abgeschlossen mit Hinweisen“); geschützte Medien werden weiter nicht ausgeliefert | Schritt 8 | R1-32 |
| D30-9 | **E-267** | Einschreibemodus „öffentlich“ entfällt in R1; nur „manuell“ und „Selbstanmeldung“; Gäste sehen nur den Schnupperbereich (E-15) | Schritt 3 | R1-32, R0-09 |

### 6.1 Offene Punkte nach Einarbeitung

| Nr. | Punkt | Warum offen | Zuständig |
|---|---|---|---|
| OP-30-1 | Admin-Screens für Text-/Dateiaufgaben (Abgabenliste, Bewertung `submitted → returned/graded`) und ihr Menüplatz | E-168 zieht die Funktion nach R1, R0-30 spezifiziert dafür keine Wireframes | Architektur (R1 Aufgaben, R1-32) |
| OP-30-2 | Enumeration-Schutz: E-241 nennt Opt-in, E-261 R4-Pool | widersprüchlich; hier ohne UI (E-261) | Auftraggeber (Klarstellung) |
| OP-30-3 | Hinweistext zu externen Medien rechtlich und sprachlich prüfen | Text ist Entwurf; Bewertung liegt beim Betreiber (R0-17 §6.5) | Auftraggeber, Redaktion R1-24 |

---

## 7. Technische Korrekturen (ohne Auftraggeber-Entscheidung)

| Nr. | Korrektur | Betroffen |
|---|---|---|
| **T30-1** | **Eingearbeitet** (R0-27 §1.5, SET-119). R0-27 **INV-S-07** („keine eigene Login-Härtung“) ist durch E-10 überholt; ersetzen durch „Härtung nur nach Admin-Bestätigung, ruht bei erkanntem Security-Plugin, keine doppelten Sperren“ (K43-48 bleibt erfüllt). SET-119 entsprechend neu fassen. | R0-27 §1.5, §4 |
| **T30-2** | **Eingearbeitet** (R0-27 §1.3, mit Speichermodell enum{auto, force, off}): `hardening.login_limit`, `hardening.known_device`, `hardening.reset_limit`, `hardening.registration_limit`, `hardening.xmlrpc`, `hardening.site_headers`, `hardening.confirmed_at` und Option `course_captain_settings_hardening` (autoload ja, ≤ 0,5 KB) ergänzen; Konstante `const:COURSE_CAPTAIN_HARDENING`; Filter `course_captain_hardening_limits`, `course_captain_hardening_conflicts`. | R0-27 §1.3, §2.1 |
| **T30-3** | **Eingearbeitet** (R0-27 §1.3). E-20: `learning.feedback.show_explanations` und `exams.feedback.show_explanations` werden `pro.*`-Felder; der Free-Kern speichert Erklärungstexte trotzdem (E-20). | R0-27 §1.3, R0-08 §4.5 |
| **T30-4** | Caps und CPTs mit Präfix `ccap_` (E-207); Meta-Caps `ccap_menu_dashboard`, `ccap_menu_certificates`, `ccap_menu_import`, `ccap_menu_settings`, `ccap_menu_status` in R0-07 §3.2 aufnehmen. | R0-07 §3.2, §4.2 |
| **T30-5** | R0-07 §4.2 S-15 nennt #11 für Gruppen, R0-11 A-10 `ccap_manage_enrollments`: maßgeblich ist R0-07 (#11). | R0-11 A-10 |
| **T30-6** | Kap. 16.1 Schritt 6 „Demo-Kurs“ → „Erster Kurs (leeres Kursgerüst)“ (E-102, C-12 erledigt); Schrittzahl 8 mit Härtung. | Konzept 16.1, R0-11 Flow 4 |
| **T30-7** | **Eingearbeitet** (R0-17 §3.2/§3.4). R0-17 „Hinweis zu E-10“ (Login/Reset fail-closed nur im R2-Modul) gilt nicht mehr; fail-closed gilt im Kern, sobald die Härtung aktiv ist. | R0-17 §3.4 |
| **T30-8** | R0-20 §2.6 Einleitung („CC Free begrenzt keine Logins“) und LB-K-06 an E-10 anpassen; Konfliktmatrix Security-Plugins × Härtungsfunktion wird Pflicht (E-10), Grundlage §3.3.10 dieser Datei. | R0-20 §2.6 |

## 8. Deltas zu anderen Dokumenten

| Dokument | Delta |
|---|---|
| `ENTSCHEIDUNGEN.md` | E-239…E-267 aufgenommen – **erledigt** |
| TODO | R0-30 Entwurfsvermerk; nach E-239 Task R1-45 anlegen; R1-32 DoR „R0-30 fehlt“ entfällt nach Freigabe |
| R0-04 / R0-11 / R0-24 | BS-27 von X/R4 nach F1 (E-10, E-239, E-261); C-06 gelöst nach E-264; A-02 acht Schritte; A-15 elf Tabs |
| R0-07 | T30-4, T30-5; Hardening-Tab Cap #19 + `manage_options` |
| R0-12 | ADM-001…ADM-127 (103 Fälle, Nummernlücken bewusst) in die Zählung; Ziel R1-32 |
| R0-15 | A11Y-083 verweist auf §2.3 W-2; A11Y-009 auf UD-1 |
| R0-17 | T30-7; Bedrohungen für Login/Reset im Kern (fail-closed, Gerätecookie) – **eingearbeitet** (T-63…T-67) |
| R0-20 | T30-8 |
| R0-26 | AD-T01/AD-T03 Schrittnummern (Bereitschaftsprüfung = Schritt 8); UX-060 Zeitbudget mit 8 Schritten |
| R0-27 | T30-1, T30-2, T30-3 – **eingearbeitet**; `course_captain_setup_state`-Schema aus §2.1; RC-Texte §4.2 als `help`/Dialog-Schlüssel |

## 9. Nachweis

- Quellen nur gelesen (Liste im Kopf); keine Implementierung, kein Testlauf, kein Commit.
- Zählungen per `grep` über diese Datei: Testfälle `grep -c '^| ADM-'` = 103; Dialoge `grep -c '^| \*\*RC-'` = 18; Bereitschaftsprüfungen `grep -c '^| RDY-'` = 15; Entscheidungen `grep -c '^| \*\*D30-'` = 9; Feldzeilen in §3.3 (Zeilen mit Backtick-Key in der ersten Spalte bzw. Anzeigezeilen der Tab-Tabellen) = 94 (Tabellenzeilen ohne Kopf in §3.3).
- Offen für die Abnahme: Security-Review (Härtung, Risiko-Token, Rollen), Accessibility-Review (Dialoge, Bereichsnavigation, Schrittliste), Freigabe durch den Auftraggeber.
