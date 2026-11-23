# SET-CC Slice „pro-grenze-pruefung“ – Nachweis

**Stand:** 23.11.2026 · **Branch:** `r3-c` (Basis 0081aef)
**Punkte:** SET-CC-01 (nur Kern-Seite), SET-CC-P6, SET-CC-P1, SET-CC-P4 (TODO-WETTBEWERBSANALYSE, Abschnitte „Einstellungen für den Verkauf“ und „Prüfphase“).
**Grundlagen:** E-346 (S1 Hinweiszeile ohne Funktions-/Lizenzcode, S5 Liste leer bis zum Verkauf), R0-27 (SR-1, SR-8, §1.3, §2.3, §2.5), R0-39 §1.2 (Free-Guard), R0-22 (VAL-142), R0-19 §1 (Vertrag, Hooks).
Der TODO-Haken bleibt beim Koordinator. Die Add-on-Seite (captain-pro) ist nicht Teil dieses Slices.

## SET-CC-01 · Hinweiszeilen und Andockpunkt (Kern-Seite)

**Hinweiszeilen** – `src/Settings/SettingsHints.php`, Darstellung in `SettingsScreen::hints()`.
- Datenform `{id, tab, title, text, link}` über den Filter `course_captain_settings_hints_v1` (stabil, Merkmal `settings.hints.v1`). `id` `[a-z0-9_]{1,40}`, erste Registrierung gewinnt, höchstens 100 Zeilen; `tab` einer der Tabs der Einstellungsseite (auch `roles`); `title` ≤ 80, `text` ≤ 300 Zeichen, reiner Text; `link` leer, `https://…` oder eine Seite dieser Verwaltung.
- Kein Feld, kein Funktionscode: alle anderen Schlüssel werden verworfen (z. B. `right`, `callback`, `field`), Einträge mit Nicht-Text-Werten (Closures) ganz. Keine Lizenzabfrage, kein Rechteschlüssel im Kern.
- **Kernliste leer (S5).** Die Texte aus `docs/settings-pro-hints.md` registriert das Add-on, sobald die Funktion verkauft wird.
- Darstellung: graue Zeile (`description`) in `<aside aria-label="Notes about further functions">` am Tab-Ende (nach Speichern/Zurücksetzen, auch im Tab „Rollen & Rechte“). „Mehr erfahren“ und „Ausblenden“ tragen den Zeilentitel als Screenreader-Text.
- Ausblenden je Person: eigenes Formular ohne JS, admin-post `ccap_settings_hint_dismiss`, Nonce `ccap_settings_hint_dismiss_<id>`, Tab muss für die Person offen sein. Gespeichert in der User-Option `course_captain_hints_dismissed` (je Website, MS-R-05), im Deinstallations-Manifest (`USER_OPTIONS`). Meldung „The note is hidden for you.“

**Andockpunkt `course_captain_settings_register_fields_v1`** – `SettingsSchema::addon_fields()` (stabil, Merkmal `settings.fields.v1`).
- Schlüssel `pro.<tab>.<feld>[.<unterfeld>]`, gleiche Felddefinition wie im Kern; verworfen werden andere Präfixe (Kernfelder sind nie ersetzbar), unbekannte Typen, ein `default`, der die eigene Prüfung nicht besteht, eine unbekannte `cap`/`privacy`/`export`, Objekte/Closures; `side_effects` werden geleert. Ergebnis mit zusätzlichem `tab`.
- Der Kern speichert, zeigt, exportiert oder löscht diese Werte nie (Speicher `course_captain_pro_settings_*`, Oberfläche und Lizenzlogik bleiben im Add-on, R0-39).

**Export/Import erhält unbekannte `pro.`-Felder** – `SettingsTransfer::import()`.
- Abschnitt `pro` gehört dem Add-on (eigener `TransferProvider` mit Schlüssel `pro`) und wird unverändert übergeben, auch Schlüssel, die der Kern nicht kennt; der Export übernimmt den Abschnitt des Anbieters unverändert.
- Ohne Anbieter bleiben Abschnitt `pro`, Tabs `pro.<tab>` und `pro.*`-Schlüssel in Kern-Tabs unberührt: nichts geschrieben, nichts gelöscht, kein Fehler, kein „unbekannt“. Der Bericht listet sie unter `addon` (Hinweis `addon_kept`); Vorschau „Einstellungen sichern“ und WP-CLI zeigen einen Hinweis.

**Wächtertest** – Free-Guard **G-15** (neu, `tests/guards/free-guard.php`): Rechteschlüssel des Lizenzkatalogs `course.<funktion>` (die 19 Funktionen aus `docs/settings-pro-hints.md`) dürfen nicht im Kern stehen; Ereignisse wie `course.completed` bleiben erlaubt. Treffer-Fixture `hits/src/g15.php`, `FreeGuardTest` erwartet 15 Treffer. Zusätzlich prüft `SettingsDockingPointsTest`, dass die neuen Dateien weder Lizenz-/Berechtigungslogik noch Rechteschlüssel enthalten.

**Hook-Doku und Snapshot:** `docs/API.md` (Hook-Tabelle, Verträge beider Filter, Abschnitt Einstellungsdatei, Merkmale), `docs/api/hooks.v1.json` (+2 Filter), `docs/API-CHANGELOG.md`.

## SET-CC-P6 · Free/Pro-Grenze, Verträge, Betrieb

| Prüfung | Ergebnis | Test |
|---|---|---|
| Free-Guard über den neuen Code | G-1…G-15 ohne Treffer, `SettingsHints.php` wird gescannt | `test_SET_CC_P6_free_guard_covers_new_code`, `FreeGuardTest` |
| Hinweiszeile ohne Funktionscode | nur Textfelder überleben, Closures verworfen | `test_SET_CC_01_hint_shape_without_function_code` |
| Andockpunkt für Add-on-Felder | nur `pro.*`, Kern unverändert | `test_SET_CC_01_addon_fields_docking_point` |
| Vertragsversion und Hook-Doku vollständig | jeder Hook aus `hooks.v1.json` steht mit Art in `docs/API.md`; Vertragsversion, Merkmale `settings.fields.v1`/`settings.hints.v1` und Changelog geprüft | `test_SET_CC_P6_contract_and_hook_docs` |
| Aktualisierung von der Vorversion | gespeicherte Werte von Registerversion 1 und 2 bleiben gleich (alle Tabs, `_rev`), Geschäftsregeln = frühere Konstanten, entfernte Felder verworfen, zweiter Lauf ohne Wirkung | `test_SET_CC_P6_upgrade_keeps_behaviour` |

- **P6-F01 (behoben, gering):** Hook-Snapshot erkennt nur Literal-Hooknamen – die neuen Filter rufen `apply_filters()` deshalb mit Literal auf (sonst wären sie undokumentiert durch CT-05 gerutscht).
- Vertragsversion bleibt `0.1.0` (vor Release, Änderungen nur additiv); Block `course-captain/course-catalog` 1.0.0 → 1.1.0 (neue Vorgabe von `orderBy`).
- **Nicht in diesem Slice:** Leistung, Mindestplattform und CI-Matrix (P6-Text) – CI-Matrix gehört einer anderen Sitzung (`ci.yml`), keine neuen Abfragen auf heißen Pfaden (PerformanceTest grün).

## SET-CC-P1 · Einstellungen und Daten

Automatisierter Test `tests/integration/SettingsRegisterAuditTest.php` über **alle** Registerfelder:

| Regel | Umsetzung |
|---|---|
| dargestellt oder ausdrücklich verborgen | jeder Tab wird gerendert, die `_fields[]`-Marker der Formularelemente gesammelt; `mastery_algorithm` als Textzeile; verborgen (Liste `HIDDEN`): `certificates.base_template` (Zertifikate-Seite, Tab verlinkt), `enrollment.source_disabled_policy` (nur mit externen Zugangsquellen; Darstellung mit Filter geprüft) |
| im Code gelesen (keine toten Werte) | statischer Scan von `src/` ohne Register-/Formular-/Transferdateien nach `'tab.feld'`; dokumentierte dynamische Leser (Härtung über `'hardening.' . $feature`, Kurs-Slug und Proxy-Liste aus der Option, Löschschalter im Uninstaller) mit Pflicht-Fundstelle; Restliste `PENDING` nur mit Befund-ID in dieser Datei |
| Vorgabe besteht die eigene Prüfung | `validate( default )` ok und unverändert |
| Export/Import-Rundlauf | jedes Feld auf einen gültigen Nicht-Standardwert, Export (mit Opt-in), alle Tabs zurücksetzen, Import mit Bestätigung → alle exportierbaren Werte gleich |
| Zurücksetzen | nach `reset` jedes Feld = Vorgabe |
| WP-CLI `settings get` | jeder Schlüssel, Exit 0, Wert = `Settings::get` |

**Befunde und Entscheidungen:**

| ID | Feld | Befund | Entscheidung |
|---|---|---|---|
| P1-F01 | `advanced.cron_stale_warning_minutes` | Konstante `Monitor::CRON_STALE` und fester Text „drei Stunden“ | **verdrahtet:** `Monitor::cron_stale()` für Monitor H-07 und Site Health (Text nennt die Minuten) |
| P1-F01 | `enrollment.pending_grant_timeout_hours` | Stundenjob nutzte festen Wert 24 | **verdrahtet** (`CronHooks` → `fail_stale_pending( Einstellung )`) |
| P1-F01 | `enrollment.claim_policy` | neue Gruppen immer `transferable` | **verdrahtet:** Vorgabe für neue Gruppen (Formular vorausgewählt, Dienst ohne Angabe) |
| P1-F01 | `exams.passing_ratio_proposal` | Vorschlag nirgends genutzt | **verdrahtet:** Bestehensgrenze neuer Prüfungen vorbelegt |
| P1-F02 | `display.catalog_sort`, `display.card_elements` | Katalog ignorierte beide | **verdrahtet:** Block-Attribut `orderBy` leer (neue Vorgabe) = Website-Einstellung; Karten zeigen Titelbild, Kurzbeschreibung, Lektionenzahl, Fortschritt nach Auswahl, leer = nur Titel |
| P1-F02 | `display.container_width`, `display.breadcrumbs` | Lernbereich ignorierte beide | **verdrahtet:** Klasse `ccap-width-<wert>` (60/80 rem/voll, je Kurs übersteuerbar), Navigationspfad „Mein Lernen › Kurs › Modul › Lektion“ in Lektionen |
| P1-F03 | `learning.favorites_enabled`, `learning.mistake_training_enabled` | Schalter ohne Wirkung | **verdrahtet** (je Kurs): aus → `404 ccap_module_disabled` (`module: favorites|mistake_training`) für Favoriten-REST, Trainer-Modi `favorites`/`wrong`, `mode=exam_mistakes`; Einstiege „Fehler üben“ ausgeblendet; Daten bleiben |
| P1-F04 | `general.catalog_page_id`, `general.learner_home_page_id` | nur im Assistenten gesetzt | **verdrahtet:** „Kurse im Katalog finden“ in „Mein Lernen“; Lernbereich-Link (Block/Shortcode) führt auf die Startseite, Rückfall Lernbereich |
| P1-F05 | `display.require_captions` | Regel VAL-142 fehlte | **verdrahtet:** Blocker bei Video ohne Untertitelspur (VTT-Einstellung oder Medienrolle `subtitle`); ohne Einstellung bewusst kein Hinweis (R0-22 sieht eine Warnung vor; eine neue Warnung würde jede Veröffentlichung bestätigungspflichtig machen) |
| P1-F05 | `learning.course_completion_default` | `…_and_exam` ohne Wirkung | **verdrahtet** wie R0-27: VAL-013 (neu, Kursblock) blockiert ohne Prüfungs-Lektion in einem Pflichtmodul; Prüfungs-Lektionen zählen ohnehin zum Abschluss |
| P1-F09 | `general.catalog_group_language_versions`, `advanced.import_execution`, `advanced.file_delivery`, `advanced.media_delete_grace_days` | Funktion existiert nicht (keine Übersetzungsgruppen, kein Browser-Import-Schalter, keine Server-Auslieferung mit Selbstprüfung, kein Medien-Aufräumjob) | **entfernt mit Migration:** Registerversion 3 (`SettingsSchema::REMOVED`), `migrate()` verwirft nur diese gespeicherten Werte; Bestätigung RC-22 entfällt; Felder und Texte aus Oberfläche und `docs/EINSTELLUNGEN.md`. Wieder aufnehmen, sobald die Funktion gebaut wird (R0-27 SET-033/121/122/123) |
| P1-F06 | `learning.mastery_algorithm` | nur eine registrierte Version, Feld ist eine Versionsbindung | **wartet auf Inhaber/Folgeaufgabe:** bleibt (R0-27 SET-051); wird gelesen, sobald eine zweite Version existiert |
| P1-F07 | `enrollment.source_disabled_policy` | Wirkung laut R0-27 erst ab R3, Quellen nur mit Erweiterung | **wartet (R3):** bleibt verborgen ohne Quellen |
| P1-F08 | `privacy.backup_max_age_days` | „nur Information“; Tombstone-Frist hängt an SET-CC-02 | **wartet auf SET-CC-02 (Gruppe h8):** bleibt, Warnung unter 30 Tagen vorhanden |

Hinweis für den Koordinator: R0-27 (Registerzeilen SET-033/121/122/123) nennt die entfernten Felder noch; die Zeilen sollten mit „entfernt in Registerversion 3 (SET-CC-P1)“ markiert werden (Entscheidungsdokument, nicht in diesem Slice geändert).

## SET-CC-P4 · Sicherheit und Datenschutz

Automatisierter Test `tests/integration/SettingsSecurityAuditTest.php`:
- **Rechte und Nonces jeder Einstellungs-/Importaktion:** 19 admin-post-Aktionen (Speichern/Zurücksetzen/Rückgängig, Hinweis ausblenden, Sicherung Export/Import, Testmail, Mailtexte und Export, Sperren, Assistent Schritt/Überspringen/Abschluss/Ausblenden, Rollen Zurücksetzen/Rückgängig, Zertifikatsvorlage, Import, Protokoll-Export) werden wie von WordPress ausgelöst (Weiterleitung/`wp_die` beenden den Lauf). Je Aktion: registriert, nie `nopriv`; Admin ohne Nonce und Abonnent mit gültigem Nonce ändern nichts (Fingerabdruck über `course_captain_*`-Optionen, Rollen, Nutzer-Meta, Protokollzeilen) und bekommen keine Daten; Gegenprobe mit Nonce und Recht wirkt. Import-REST (`/imports/{id}`, `/step`, `/cancel`, `/resume`, `/report`): Gäste 401, Abonnenten 403/404.
- **Geheimnisse nie im Export, Protokoll oder Log:** Wächterwert im Speichergeheimnis, persönliche Absenderadresse, Proxy-Liste und Protokollstufe: Export mit und ohne Opt-in ohne Geheimnis und ohne umgebungsgebundene Felder, persönliche Werte nur mit Opt-in; Änderungsprotokoll nach Speichern/Import/Zurücksetzen ohne Geheimnis und mit persönlichen Werten nur als „changed“; Diagnose-Export ohne Geheimnis; Logdatei ohne Geheimnis und E-Mail-Adresse.
- **P4-F01 (behoben, mittel):** `Log::scrub()` maskierte nur E-Mail-Adressen; ein Kontextschlüssel wie `token`, `secret`, `password`, `nonce`, `api_key`, `signature`, `cookie`, `authorization`, `credential` landete im Klartext in der Logdatei. Jetzt `[redacted]` (`Log::SECRET_KEYS`).
- **Deinstallation:** jede `course_captain_*`-Option, die die Einstellungsabläufe anlegen, steht im Manifest; bestätigte Deinstallation entfernt die neue User-Option `course_captain_hints_dismissed` und alle Manifest-Optionen. Neue Optionen gibt es in diesem Slice nicht.
- Keine weiteren Befunde: alle Aktionen prüfen Nonce und Recht serverseitig.

## Tests

- `tests/integration/SettingsDockingPointsTest.php` (7): leere Kernliste, Datenform ohne Funktionscode, Darstellung/A11y-DOM/Ausblenden je Person, Andockpunkt, `pro.*` im Export/Import (ohne und mit Add-on-Anbieter), Free-Guard über neuen Code, Vertrags-/Hook-Doku, Aktualisierung v1/v2 → v3.
- `tests/integration/SettingsRegisterAuditTest.php` (5): Darstellung, keine toten Werte, Vorgaben, Rundlauf/Zurücksetzen/WP-CLI, Wirkung der vorher toten Felder.
- `tests/integration/SettingsSecurityAuditTest.php` (4): admin-post-Rechte/Nonces, Import-REST, Geheimnisse, Deinstallation.
- Angepasst: `SettingsRegressionTest` (Beispiel für ein umgebungsgebundenes Feld jetzt `advanced.log_level` statt des entfernten `file_delivery`), `FreeGuardTest` (15 Guard-Treffer).

## Noch offen

- P1-F06/F07/F08 (siehe Tabelle, begründet wartend).
- Systemstatus-Warnung, wenn die Lernenden-Startseite später fehlt (R0-27 Zeile `learner_home_page_id`), ist nicht gebaut; der Link fällt still auf den Lernbereich zurück.
- R0-27-Zeilen der entfernten Felder markieren (Koordinator).
- Add-on-Seite (Registrierung der Hinweiszeilen und `pro.*`-Felder, Transferanbieter `pro`) im Repository captain-pro.

## Gates (23.11.2026, PHP 8.5, MariaDB, DB `ccap_test_c`)

phpcs 0 Fehler, 0 Warnungen · PHPStan 0 · Unit 141 OK · Integration 559 OK (3 übersprungen) · Multisite 572 OK (3 übersprungen) · Free-Guard 0 Treffer (Selbsttest OK, G-15 neu) · Textdomain-Lint 0 · Adressprüfung 0 (de_DE du, de_DE_formal Sie) · ApiContractTest-Snapshot aktualisiert (`hooks.v1.json` +2 Filter). Neue msgids: 13 (davon 3 Pluralformen); `.pot` regeneriert der Koordinator.
