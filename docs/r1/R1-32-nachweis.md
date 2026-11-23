# R1-32 · Setup-Assistent und globale Einstellungs-Tabs – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-d`
**DoD (TODO R1-32):** Resumierbarer Wizard, feldnahe Fehler, Tab-Save/Reset, ungespeicherte Warnung, Accessibility.
**Quellen:** R0-30 §1.2/§1.3 (Menü, versteckte Seite), §2 (Assistent, W-1…W-8, Schritte 1–8, RDY-01…RDY-15), §3 (Seitenrahmen T-1…T-12, Meldungskatalog F-xx, Tabs §3.3.1–§3.3.11), §4 (D-1…D-8, RC-01…RC-18, UD-1), §5 (ADM-xxx); R0-27 S-1…S-10, §2.4, §2.7; R0-31 PD-1…PD-3; R1-31/R1-45-Nachweis (Übergaben A-3, A-2). Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Admin/SettingsScreen.php` | Seite `course-captain-settings` (Meta-Cap `ccap_menu_settings` ⇐ #19 ∨ (#24 ∧ `promote_users`)); Bereichsnavigation als Links mit `aria-current="page"` (M-3); je Tab eigene URL, eigenes Formular, Nonce `ccap_settings_save_<tab>`, `_rev`, `_fields[]` (T-1, S-1); Tab „Website-Härtung“ nur mit `manage_options`; admin-post → Redirect → GET (T-4) mit Erfolgsmeldung (fokussiert, `tabindex=-1`), „Keine Änderungen“, Warnungszähler (T-6); Fehler: nichts gespeichert, Eingaben 10 min im Nutzer-Transient, Fehlerzusammenfassung `role="alert"` mit Feldlinks (T-5); `_rev`-Konflikt mit Anzeigename, Zeit, Feldliste und „Inzwischen geändert auf“ (T-7); Risiko-Felder über Bestätigungsseite mit Token (T-8, D-7); Reset in eigenem Formular mit Bestätigungsseite (Liste „Label: aktuell → Standard“, RC-17) und Rückgängig-Knopf (T-10); Knopf `aria-disabled` ohne Abweichung; Warnungen F-62, F4.7, F-90, F-91, Δ-2, Δ-4; Härtungs-Tab mit Konflikterkennung, Status je Merkmal, Grenzwerttabelle, Notfallhinweis, lesend bei Notausschalter (F-92) |
| `src/Admin/SettingsFields.php` | Feld-/UI-Vertrag: 11 Tabs in Reihenfolge §3.1, Einleitungen, Abschnitte, je Feld Label, sichtbarer Hilfetext, Element, Optionen, Einheit, Geltung, Abhängigkeit; Meldungskatalog (F-01…F-15, F-20…F-36, F-61, F-70/71, F-80…F-83, F-88; alle beginnen mit „Error:“) |
| `src/Admin/SettingsForm.php` | barrierearme Felder: `label`/`fieldset`+`legend`, `aria-describedby` = Hilfe + Metazeile (+ Fehler/Warnung), `aria-invalid`, abhängige Felder fokussierbar mit `aria-disabled` und Grund (T-12, K-04); Dekodierung (verstecktes `0` für Kästchen, Modus für „unbegrenzt/Wert“, Mengen, Zeilenlisten mit Zeilennummer F-81/F-82/F-83, Prozent mit höchstens einer Nachkommastelle → gekürzter Bruch, ADM-096) |
| `src/Admin/RiskDialog.php` | Texte RC-01, RC-03…RC-06, RC-08…RC-10, RC-12…RC-15 und RC-19…RC-22 mit Zahlen (`_n`), ein Abschnitt je Risiko (D-5), Aktionsknopf benennt die Aktion, Anfangsfokus auf „Abbrechen“ (D-3), funktioniert ohne JavaScript (D-7) |
| `src/Admin/SetupWizard.php`, `src/Admin/SetupState.php` | versteckte Seite `course-captain-setup&step=<key>` (#19); 8 Schritte nach §2.2; Schrittliste `<ol>` mit `aria-current="step"`, Status als Text, Symbol `aria-hidden` (W-2); h1 mit Fokus (W-1); implizites Absenden = „Speichern und weiter“, „Überspringen“ als eigenes Formular mit eigener Nonce (W-3); Speichern nur der Schrittfelder über `Settings::check/save`, alles oder nichts über alle Tabs eines Schritts, Audit `settings_saved` mit `context=setup` und `step`; Risiko-Bestätigung (RC-01 entfällt bei Ersteinrichtung, W-6); Zweck-Vorschläge (Schritt 3/4, gekennzeichnet, ohne Speichern nichts übernommen); Schritt 2 legt die Katalogseite genau einmal an (ADM-029); Schritt 6 speichert den Härtungs-Tab (= Bestätigung HM-0, Vorschlag „an“ außer bei erkanntem Plugin); Schritt 7 legt Kursentwurf mit 1 Modul und 1 Lektion genau einmal an (ADM-034, F-70/F-71); Schritt 8 Bereitschaftsprüfung, Abschluss immer möglich (`completed_with_issues`, E-266); Zustand `course_captain_setup_state` mit eigenem `_rev`, Passthrough unbekannter Schritt-Keys (ADM-046), „zuletzt bearbeitet von“; Admin-Hinweis nur für #19 auf Dashboard/Plugins/CC-Seiten, Ausblenden je Nutzer (`dismissed_by`) |
| `src/Admin/Readiness.php` | RDY-02, RDY-04, RDY-05, RDY-07, RDY-08, RDY-09, RDY-10, RDY-12, RDY-13, RDY-14 mit Schwerewort, Ursache, Abhilfe; RDY-01/03/06/15 als Hinweis „im Systemstatus geprüft“ (kein Loopback im Assistenten, E-210) |
| `src/Settings/Settings.php` | **geteilt:** `check()` (Validierung ohne Schreiben, Fehler `field => rule`), `save()` mit optionalem Audit-Kontext (`context`, `step`, `confirmed_risks`) und `data.fields` im Fehler, `last_writer()`, `undo_until()`, `allowed()` |
| `src/Settings/SettingsSchema.php` | **geteilt:** Slug-Regel nach R0-27 (`^[a-z0-9][a-z0-9-]{0,48}[a-z0-9]$`); CIDR `/0` abgelehnt (F-82, SET-115) |
| `src/Settings/SettingsRules.php` | semantische Regeln: reservierte Slugs, Kollision mit Seite/Beitrag, Fremd-CPT, gleiche Slugs (F-02…F-05), Seiten (F-10…F-12), Vorschlag mit Umlaut-Umschrift; Filter `course_captain_settings_rules` |
| `src/Settings/RiskRules.php`, `src/Settings/RiskConfirm.php` | Risiko-Erkennung je Tab (PD-1: strenger ist frei); Token an Nutzer, Scope und Werte-Hash gebunden, 10 min, einmalig, nur Hash gespeichert (S-7, D-6, PD-3); Filter `course_captain_settings_risks` |
| `assets/js/admin-settings.js` | progressive Verbesserung: Fokus auf Meldung/Zusammenfassung/h1, Dirty-Tracking, Warnung beim Verlassen über Navigation/Schrittliste/„Einrichtung verlassen“ und `beforeunload` (T-9, W-7) |
| `src/Kernel/Plugin.php` | **geteilt:** Registrierung `SettingsScreen`, `SetupWizard` |
| `tests/integration/AdminSettingsTest.php` | 9 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler (bekannte Warnung ImportTest.php:318) · PHPStan 0 · Unit **104** · Integration **297** · Multisite **309** · Free-Guard 0 · Textdomain-Lint 0 · Hook-Snapshot aktualisiert (`docs/api/hooks.v1.json`, `docs/API-CHANGELOG.md`). `LifecycleTest::test_LIC_008` war reihenfolgeabhängig (Härtungszustand wird auf `init` geschrieben) und toleriert jetzt diese Systemoption.

## 3. Tests

| Test | Fälle |
|---|---|
| `test_ADM_070_tabs_and_navigation` | ADM-070, ADM-059 (Härtung nur mit `manage_options`, POST → `ccap_forbidden`), Konto ohne #19 ohne Formular |
| `test_ADM_071_save_and_field_contract` | ADM-071 (PRG, „Keine Änderungen“ ohne Audit), ADM-080 (jedes Feld aller Tabs mit Label, Hilfetext, Metazeile; keine Tooltips), ADM-084 (`aria-disabled`) |
| `test_ADM_072_invalid_input` | ADM-072 (alles oder nichts, Zusammenfassung mit Link, `aria-invalid`, Eingaben erhalten), ADM-096 (0/101/„70,55“ → F-31/F-31/F-32; „66,7“ → 667/1000; „66.7“ unverändert), ADM-028 (`wp-admin`, `feed`, `-lernen`, `über`, gleiche Slugs, Kollision mit Seite, Vorschlag `ueber`), ADM-030 (Entwurf, gelöscht) |
| `test_ADM_079_risk_confirmation` | ADM-079/PSD-004 (ohne Token nichts gespeichert, gefälschtes/fremdes/wiederverwendetes Token, geänderte Werte), ADM-078 (zwei Risiken, ein Dialog, `confirmed_risks` im Audit), PD-1 |
| `test_ADM_075_reset_undo_conflict` | ADM-075/076 (Bestätigungsseite, Reset, Undo, eine Generation, Knopf `aria-disabled`), ADM-077 (Konflikt mit Anzeigename und Markierung), ADM-040 (Nonce) |
| `test_ADM_085_warnings_and_site_name` | ADM-085 (F-62 „gespeichert, 1 Warnung“), ADM-091 (E-89 ohne/mit Site-Name) |
| `test_ADM_022_full_run` | ADM-022, ADM-029, ADM-034/035, ADM-050/051, PSD-065 (vor dem Assistenten `off:unconfirmed`, nach Schritt 6 `active`), ADM-032 (Vorschlag sichtbar, nicht gespeichert), Abschluss, Hinweis verschwindet |
| `test_ADM_025_skip_leave_rights` | ADM-025 (Optionen bytegleich), ADM-026 (Rendern schreibt nichts, kein Audit), ADM-046, ADM-021, ADM-040, Ausblenden je Nutzer |
| `test_ADM_039_conflict_and_conflicting_plugin` | ADM-039, ADM-052 (Vorschlag „aus“ bei Wordfence), ADM-027 (F-03, Eingabe erhalten) |

## 4. Security-/Accessibility-Review (Selbstprüfung)

- Jede schreibende Aktion: admin-post, Nonce je Tab/Schritt/Aktion, Capability serverseitig (Tab, Feld, `manage_options` für Härtung, `admin`-Felder), Ausgaben escaped, kein REST (SR-5).
- Risiko-Token serverseitig, an Nutzer + Scope + Werte-Hash gebunden, einmalig; die Bestätigung trägt keine Werte (Werte aus dem geparkten Transient).
- Formulare ohne JavaScript bedienbar; Labels/Legends, sichtbare Hilfetexte, `aria-describedby`, `aria-invalid`, Fehlerzusammenfassung mit Fokus, eine Statusregion je Seite.

## 5. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | UD-1 als natives `<dialog>` mit drei Knöpfen, RC-Dialoge als `<dialog>` (D-1) | Bestätigung als serverseitige Seite (D-7, gilt für alle Clients); UD-1 als `window.confirm` im Skript; `<dialog>`-Variante mit R1-19/R1-29 (Playwright/AXE) |
| A-2 | RC-14: R0-30 „beim Einschalten“, R0-27/R0-31 „bei `force` und `off`“ | Vereinigung umgesetzt (Einschalten, `force`, Abschalten nach Bestätigung); **Klärung** |
| A-3 | R0-31-Risikofelder ohne Dialognummer (Δ-1, Δ-6, `consent_category = functional`, `file_delivery`) | als RC-19…RC-22 nummeriert |
| A-4 | Token-Pflicht im Dienst `Settings::save` | in den Eingängen (Tab, Assistent; Import mit R1-36); der Dienst bleibt die vertrauenswürdige API für Code, CLI (eigene `--yes`-Prüfung) und Tests |
| A-5 | Menüpunkte Übersicht/Kurse/… (§1.2), Menüposition 26.3, Übersicht-Checkliste, Einrichtungshinweis „{n} Punkte offen“ auf der Übersicht | es gibt noch keine Übersichtsseite; Abschlussmeldung erscheint im Assistenten; Menübaum mit R1-19/R1-38 |
| A-6 | Testmail (Schritt 5, F-63, F4.4), Rollen-Tab, Fristen-Übersicht | R1-34 |
| A-7 | Farbtokens (`display.tokens`), Debug (`advanced.debug`), Fehlerschwelle Import, Rate-Limit-Tabelle, „Alle Sperren aufheben“, „Header erneut prüfen“ | Felder fehlen im Register bzw. Loopback; nicht gebaut |
| A-8 | RDY-01/03/06/11/15 | ohne Loopback nur Hinweis; Prüfung im Systemstatus (R1-38/R1-30) |
| A-9 | Schritt 7 „leere Abschlussprüfung“ | Kursentwurf mit Modul und Lektion; Prüfungsregel-Entwurf mit dem Prüfungseditor |
| A-10 | Playwright/AXE/NVDA-Fälle (ADM-041…043, 073, 074, 083, 120…127) | Struktur per PHPUnit geprüft; Browserläufe mit R1-19/R1-29 |

Review durch zweite Person vor Merge (AG-05).
