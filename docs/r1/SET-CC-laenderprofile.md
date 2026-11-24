# Nachweis SET-CC-07 / SET-CC-P3 – Länderprofile DE/AT/CH/EU/OTHER

Stand: 24.11.2026 · Branch `r5-g` (Basis 9f6eaa8) · Bezug: TODO-Abschnitt „Einstellungen für den Verkauf“ (SET-CC-07, SET-CC-P3), E-346 S8, R0-30 §2 (Setup-Schritt 1), R0-27 K43-04 (Sprache, Zeitzone, Datum = WordPress-Werte).

## Ergebnis

**SET-CC-07 und SET-CC-P3 bleiben offen, aber nur noch wegen E4, E7 und der Rechtsprüfung.** Die Technik ist fertig: Datei, Filter, Vergleich Vorher/Nachher, ausdrückliche Übernahme, Audit, Export/Import. Offen sind nur noch:

- **E4** (Inhaber): EU-Wert für externe Videos (`privacy.external_media_mode`). Steht in der Datei als `null` mit `"open": "E4"`. Die Oberfläche zeigt „offen – Entscheidung Inhaber (E4); wird nie übernommen“. Empfehlung laut TODO: `click_to_load`. Nach der Entscheidung genügt ein Eintrag in `assets/data/country-profiles.json`.
- **E7** (Inhaber): Altersgrenze. Dafür gibt es noch keine Einstellung (SET-CC-17). Alle fünf Profile führen deshalb den Platzhalter `open.age_limit` mit `null` und `"open": "E7"`. Angezeigt wird „Noch keine Einstellung“ und „offen – Entscheidung Inhaber (E7)“.
- **Rechtsprüfung**: Alle rechtlich gewichtigen Werte haben `legal: true` und `reviewed: false`. Die Oberfläche zeigt bei ihnen **„ungeprüft – Rechtsprüfung nötig“**. Nach der Prüfung setzt man in der Datei (oder per Filter) `reviewed: true`; dann entfällt die Markierung. USt auf E-Learning, Zustimmung zu sofortigem Beginn/Widerruf (E1, SET-CC-03) und Pflichttexte (SET-CC-06) haben bisher keine Einstellung. Sie sind deshalb nicht Teil der Profile, und das Plugin legt dazu nichts fest (P3: „legt nichts Rechtliches fest“).

## Gebaut

| Teil | Umsetzung |
|---|---|
| Daten | `assets/data/country-profiles.json` (`format: course-captain-country-profiles`, v1). Jedes Profil enthält Einträge `{value, legal?, reviewed?, open?}` für **bestehende** Einstellungen: WordPress-Werte `wp.WPLANG` (Sprache, bestimmt auch Zahlen- und Datumsformat), `wp.timezone_string`, `wp.date_format`, `wp.time_format`, `wp.start_of_week` sowie die Registerfelder `privacy.external_media_mode`, `privacy.show_avatars`, `privacy.backup_max_age_days` und `advanced.error_log_days` (Aufbewahrung, soweit es dafür heute Schlüssel gibt), `advanced.report_pseudonymize` und `certificates.holder_name_policy`. Für OTHER gibt es keine Vorschläge (alle `null`), nur den Platzhalter für E7. EU hat keine Sprach- und Zeitzonenvorschläge; als Datumsformat wird ISO `Y-m-d` vorgeschlagen. |
| Filter | `course_captain_country_profiles( array $profiles )` (experimentell). Nur die IDs DE/AT/CH/EU/OTHER gelten. Verworfen werden unbekannte, nicht exportierbare, personenbezogene oder geheime Schlüssel, `general.country_profile` selbst, `wp.*` außerhalb der Liste, `open.*` mit Wert und ungültige Werte (Prüfung über `SettingsSchema::validate`, Zeitzonenliste, `sanitize_option`). Gibt der Filter etwas anderes als ein Array zurück, gelten alle Profile als leer. |
| Dienst | `src/Settings/CountryProfiles.php`: `profiles()`, `compare()` (Zeilen mit `current`, `suggested` und `status` = `differs`/`same`/`open`/`unavailable`; `null` ohne `open` wird übersprungen; eine Sprache, die nicht installiert ist, gilt als `unavailable`), `fingerprint()` und `apply()`. |
| Nie still überschreiben | Anzeigen und Vergleichen schreiben nichts. Wer im Tab Allgemein das Profil auswählt (`general.country_profile`), legt nur diese Wahl fest; kein anderer Wert ändert sich. Übernommen wird nur, was die Person ausdrücklich wählt („Ausgewählte Werte übernehmen“ oder „Alle Vorschläge übernehmen“), und davon nur Zeilen mit `differs`. Offene, gleiche und nicht verfügbare Zeilen werden übersprungen. Haben sich die Werte seit der Anzeige geändert (Fingerprint), wird nichts übernommen (`conflict`). Werte, die eine Risiko-Bestätigung bräuchten (RiskRules, z. B. Gravatar RC), werden übersprungen (`risk`) und bleiben dem Einstellungs-Tab vorbehalten. WordPress-Werte ändert nur, wer `manage_options` hat. |
| Oberfläche | `src/Admin/CountryProfilePanel.php` erscheint im Setup-Schritt 1 („Zweck & Sprache“) und im Einstellungs-Tab „Allgemein“ und funktioniert ohne JS. Die Profilwahl läuft per GET (speichert nichts). Die Tabelle hat die Spalten Auswahl, Einstellung, „Aktueller Wert (vorher)“, „Vorschlag (nachher)“ und Status, mit Caption und einem scrollbaren, benannten Bereich. Übernommen wird per admin-post `ccap_country_profile_apply` mit Nonce und `ccap_manage_settings`. Meldungen haben `role=status`/`alert`. |
| Registerfeld | `general.country_profile` (`''|DE|AT|CH|EU|OTHER`, Standard `''`, exportiert) im Tab Allgemein, Abschnitt „Länderprofil“. Export und Import übernehmen die gewählte Profil-ID; die Werte selbst laufen als normale Felder mit. |
| Audit | Neue Aktion `country_profile_applied` (Bereich `settings`, in `AuditView::registry()`; die Einstellungsansicht zeigt Vorher/Nachher je Schlüssel). Kontext: `profile`, `previous`, `changed` (Vorher/Nachher), `skipped`, `unreviewed`. Zusätzlich schreiben die Registeränderungen `settings_saved` mit `context = country_profile` und `profile` (`Settings::audit()` nimmt dafür neu den Kontextschlüssel `profile` auf). |
| Doku | `docs/EINSTELLUNGEN.md`: nur die Tabellenzeile und die Hilfezeile `general.country_profile` von Hand ergänzt (`--check` grün). `docs/API.md`: Hook-Zeile plus Vertragsabsatz. `docs/API-CHANGELOG.md`: neue Zeile. `docs/api/hooks.v1.json`: Snapshot aktualisiert. |
| Übersetzung | 45 neue msgids (davon 2 Pluralformen) in `course-captain-de_DE.po` (du) und `course-captain-de_DE_formal.po` (Sie), .mo neu erzeugt; `tools/i18n/check-address.php`: 0 Befunde. |

## Tests

`tests/integration/CountryProfilesTest.php` (Gruppe `SET-CC-07`, 6 Tests):

- `test_SET_CC_07_data_file_and_open_owner_decisions`: Datei mit fünf Profilen; E4 (EU-Video) und E7 (Alter) sind `null` mit Inhaber-Hinweis; rechtliche Werte sind `legal`/ungeprüft; die Oberfläche zeigt „offen – Entscheidung Inhaber“ ohne Übernahme-Kästchen.
- `test_SET_CC_07_diff_is_correct`: Vorher/Nachher und Status je Schlüssel (`differs`/`same`/`unavailable`), DOM-Prüfung von Tab und Setup-Schritt.
- `test_SET_CC_07_no_silent_overwrite`: Anzeige schreibt nichts; die Profilwahl ändert nur `general.country_profile`; „Ausgewählte übernehmen“ ändert genau den gewählten Schlüssel; leere Auswahl, veralteter Vergleich, falsche Nonce und fehlendes Recht ändern nichts.
- `test_SET_CC_07_apply_all_audit_and_export_import`: „Alle“ übernimmt nur abweichende Werte (E4 bleibt), schreibt einen Audit-Eintrag (auch in der Einstellungsansicht), eine Wiederholung schreibt keinen zweiten; Export/Import behält die Profil-ID.
- `test_SET_CC_07_filter_override_and_null_values_skipped`: der Filter gewinnt; fremde IDs, unbekannte, nicht exportierbare und ungültige Schlüssel sowie `null` werden verworfen; mit `reviewed: true` entfällt „ungeprüft“; ein kaputter Filter bedeutet leere Profile.
- `test_SET_CC_07_wordpress_values_need_admin_and_risks_are_skipped`: Wer Einstellungen verwalten darf, aber nicht `manage_options` hat, übernimmt nur Registerwerte; Risikowerte werden übersprungen.

Außerdem: `A11yI18nTest` prüft beide Vergleichsansichten mit A11yDom. Die Register-Audits (`SettingsRegisterAuditTest`, `SettingsFieldContractTest`, `SettingsDocTest`) decken das neue Feld ab.

## Noch offen

- E4, E7 und Rechtsprüfung, siehe oben. SET-CC-07 und SET-CC-P3 nicht abhaken, bis diese drei erledigt sind.
- Aufbewahrung: Sobald SET-CC-02 (D1) `privacy.retention.*` einführt und `advanced.error_log_days` dorthin verschiebt, müssen die Profilwerte auf die neuen Schlüssel umgestellt werden. Bis dahin überspringt `compare()` Schlüssel, die es nicht mehr gibt, ohne Fehler.
- POT erzeugt der Koordinator neu.
