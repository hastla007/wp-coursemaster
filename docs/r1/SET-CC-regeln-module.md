# SET-CC Slice „regeln-module“ – Nachweis

**Stand:** 23.11.2026 · **Branch:** `r2-d` (Basis `main` 89fe9ef)
**Punkte:** SET-CC-13, SET-CC-24, SET-CC-25, SET-CC-26, SET-CC-30, UI-F2 (TODO-WETTBEWERBSANALYSE, Abschnitt „Einstellungen für den Verkauf“).
**Grundlagen:** R0-27 (Register), R0-28 (Vererbung), R0-23 Δ-23-6 / E-108, R0-31 §3.4 / R1-45, E-346 (S2 „Filter gewinnt“, S5, S10).
Der TODO-Haken bleibt beim Koordinator.

## SET-CC-13 · Geschäftsregeln aus Konstanten gelöst

| Regel | Registerschlüssel | Vorgabe (= alte Konstante) | Lesestelle |
|---|---|---|---|
| Prüfungsstarts je Stunde | `exams.starts_per_hour` (1–1000) | 40 (`ExamSessions::START_LIMIT_HOUR`) | `ExamSessions::start()` |
| Bekanntes Gerät, Laufzeit | `hardening.known_device_days` (1–365) | 180 (`Hardening::DEVICE_DAYS`) | Gerätecookie in `Hardening` |
| Anmeldegrenzen | `hardening.login_limits` `{ip, account_ip, account}` | 10 / 5 / 30 je 15 min (`Hardening::LIMITS`) | `Hardening::limits()` |
| Wiederholungsabstände | `learning.review_intervals` `{first, second, third}` | 7 / 21 / 60 Tage (`SmartTrainer::REVIEW_INTERVAL_DAYS`) | `SmartTrainer::review_interval()` |
| Erlaubte (eigene) Medien-Hosts | `privacy.trusted_media_hosts` (Hostliste, max. 50) | leer | `ExternalMedia::foreign_hosts()` |
| Einladungsdauer | `enrollment.invite_valid_days` (bestand) | 30 (`GroupService::INVITE_DAYS`, **entfernt**) | `GroupService::invite()` |

- Neuer Dienst `src/Settings/BusinessRules.php`: liest über `Settings::get`, danach Filter `course_captain_business_rule( $value, $key )` (S2: Filter gewinnt; ungültige Filterwerte werden gegen das Register geprüft und verworfen). Die älteren Filter `course_captain_hardening_limits` und `course_captain_trusted_media_hosts` laufen weiter zuletzt und erhalten die Registerwerte als Startwert.
- **R1-45 konsistent:** Zeitfenster und Reset-/Registrierungsgrenzen bleiben technische Konstanten (R0-27: „Grenzwerte nur per Filter“); nur die drei Login-Zähler sind Einstellung. Kleiner Fehler mitbehoben: `hits()`/`count()` lasen das Fenster aus der Konstante, die Grenze aber aus dem Filter – jetzt beide aus `Hardening::limits()`. Die Härtungsoption bleibt unter 0,5 KB (ADM-062, Test `test_SET_009_autoload_budget`).
- Oberfläche: Felder in den Tabs Lernen (Wiederholungsabstände), Prüfungen, Datenschutz (Hostliste, neues Element `hosts` mit zeilengenauer Fehlermeldung), Website-Härtung (Abschnitt „Fehlgeschlagene Anmeldungen je 15 Minuten“). Regel `interval_order` (Abstände fallen nie), Regel `host`.
- **Migration:** `SettingsSchema::VERSION` 1 → 2; `Settings::maybe_migrate()` läuft bei `admin_init` nach einem Update und behält jeden gespeicherten Wert; neue Schlüssel lesen ihre Vorgabe (= alte Konstante).
- Bewusst nicht umgestellt: `LegacyDueBackfill` nutzt weiter den ersten Standardabstand (einmalige Altdaten-Übernahme, Fingerabdruck stabil).

## SET-CC-24 · Modulschalter

`certificates.enabled`, `learning.assignments_enabled`, `learning.flashcards_enabled` (Vorgabe an); Dienst `src/Settings/Modules.php`.

| Aus heißt | Zertifikate | Abgaben | Karteikarten |
|---|---|---|---|
| UI | kein Menüpunkt, Seite zeigt Hinweis; Lernbereich `/nachweise` nicht ausgeliefert | Bereich „Bewertung“ ausgeblendet; Lektion zeigt „nicht verfügbar“ statt Formular | Lektion zeigt „nicht verfügbar“ |
| REST | `/me/certificates…` nicht registriert | R-46, R-54…R-58 nicht registriert | Fortschritt → `404 ccap_module_disabled` |
| Hintergrund | keine automatische Ausstellung (Outbox-Handler quittiert) | – (Ereignisse entstehen nicht mehr) | – |
| Kursprüfung | – | Lektionen ignoriert | Lektionen und Karteikarten-Fragen ignoriert |
| Admin-Aktionen | Ausstellen/Korrigieren/Widerrufen/Export/Vorlage → `ccap_module_disabled` | Bewerten → `ccap_module_disabled` | – |

Nichts wird gelöscht; Einschalten stellt alles wieder her (Test). **Bewusste Ausnahmen:** öffentliche Prüfung ausgestellter Zertifikate (`/certificates/verify`, Seite `/nachweis/<token>`) bleibt erreichbar; Datenauskunft, Löschung und Aufbewahrungsfristen erfassen die Moduldaten weiter (rechtliche Pflichten).

## SET-CC-25 · Freischaltplan

`enrollment.default_drip_days` (leer = alles sofort, 1–365; gelesen über `EffectiveSettings::resolve`, websiteweit). Eine Kursübersteuerung ist bewusst nicht eingebaut: sie kostete je Gate-Prüfung eine Revisionsabfrage und brach die Abfragebudgets OP-03/OP-05 (R0-14); Kurse mit eigenem Plan nutzen Drip-Regeln je Modul. `ContentGate`: Modul an Position *n* (0-basiert) öffnet *n × Tage* nach Zugangsbeginn (`rule_refs = drip:default`); eine eigene Drip-Regel des Moduls gewinnt; ein bereits begonnenes Modul bleibt offen (RV-14). Pro-Hinweis `drip_campaigns` (feste Termine) unberührt.

## SET-CC-26 · Berichte

- `advanced.report_csv_columns` (14 Spalten, Vorgabe alle) und `advanced.report_pseudonymize` (Vorgabe aus).
- `CourseReport::csv()` + Download im Bereich **Lernende** (Kursansicht): Spaltenauswahl je Export (vorbelegt aus der Einstellung), Kästchen „Pseudonymisieren“ (bei aktiver Einstellung fest an). Audit `report_exported` (Spalten, Anzahl, pseudonym ja/nein).
- Pseudonym `src/Privacy/Pseudonym.php`: `P-` + 12 Hex aus HMAC(User-ID, Website-Geheimnis) – stabil je Person und Website, ohne Name/E-Mail. Auch das Zertifikats-CSV ersetzt `holder` durch `pseudonym`. E-Mail-Adressen werden nie exportiert.
- **Hinweiszeile `course.analytics`: offen.** Das Muster der Pro-Hinweiszeilen (SET-CC-01) existiert im Kern noch nicht; nach S5 bleibt die Liste ohnehin leer, bis die Funktion verkauft wird (`analytics`, Tab „Erweitert“, siehe `docs/settings-pro-hints.md`).

## SET-CC-30 · Deinstallation suite-einheitlich (S10)

- Einschalten von `general.delete_data_on_uninstall` nur mit Eingabe LÖSCHEN/LOESCHEN/DELETE (Groß-/Kleinschreibung egal, Leerzeichen und zerlegte Umlaute toleriert, serverseitig in `Settings::check()`); ersetzt den Website-Namen (E-89). Ausschalten ohne Wort. Feld `_confirm_delete` (`Settings::CONFIRM_WORD_FIELD`), Fehlerregel `confirm_word`.
- Beschriftung „Remove all data when the plugin is deleted“ (de: „Beim Löschen des Plugins alle Daten entfernen“); Hilfetext nennt, was gelöscht wird, dass Hintergrundjobs immer entfernt werden und dass im Netzwerk jede Website selbst entscheidet. Nicht exportiert (bestand), Optionsname unverändert.
- **Offen:** WP-CLI `--confirm=LÖSCHEN` – es gibt noch keinen `settings`-Befehl (SET-CC-12). Der Dienst nimmt das Wort über `CONFIRM_WORD_FIELD` an; der CLI-Befehl muss es nur durchreichen.

## UI-F2 · „Einschreibung geschlossen“ (E-108)

- Kurs-Meta `_ccap_enrollment = closed` (R0-23 Δ-23-6, live, im Deinstallations-Manifest). Umschalten im Bereich **Lernende** (Kursansicht, nur wer den Kurs bearbeiten darf), Audit `course_enrollment_closed`/`course_enrollment_opened`.
- Kursraum: kein Knopf, klare Meldung; Katalog ohne „Kostenlos einschreiben“. Bestehende Zugänge laufen weiter (Stufe 1 von E-108).
- Neue REST-Route `POST /courses/{course}/enrollment` (gab es noch nicht): geschlossen → `403 ccap_enrollment_closed` (Katalogtext), Formular-Weg liefert denselben Code.

## Tests

`tests/integration/RegelnModuleTest.php` (7 Tests): Vorgaben = Konstanten, Wirkung + Filter gewinnt + Validierung, Migration v1 → v2, Modulschalter aus/an (UI, REST, Prüfung, Daten), Freischaltplan, CSV-Spalten/Pseudonym/Audit/Download, geschlossene Einschreibung (UI, Formular, REST, Audit). Zusätzlich `ExamTest::test_SET_CC_13_start_limit_from_setting`, angepasst `UninstallProbeTest::test_SET_CC_30_deletion_setting_needs_typed_word` und `AdminSettingsTest::test_ADM_085_warnings_and_site_name`; Lernende-Kursansicht in `A11yI18nTest::test_A11Y_admin_screens_and_blocks`.

## Noch offen

- Hinweiszeile `course.analytics` (wartet auf SET-CC-01 und S5).
- WP-CLI `--confirm=LÖSCHEN` (wartet auf SET-CC-12).
- Übersetzungen de_DE/de_DE_formal (Koordinator erzeugt die .po neu).
- Lektionen eines ausgeschalteten Moduls zählen weiter für den Kursabschluss (Fortschritt bleibt unverändert, damit nichts still neu bewertet wird); wer ein Pflichtmodul abschaltet, sieht das in der Kursprüfung nicht. Entscheidung, ob solche Lektionen aus dem Nenner fallen, steht aus. **Entschieden 24.11.2026: sie zählen weiter (E-353).**

## Gates (23.11.2026)

phpcs 0 Fehler (bekannte Warnung ImportTest) · PHPStan 0 · Unit 137 OK · Integration 520 OK (3 übersprungen; ein Lauf zeigte `MigrationResumeTest::test_MIG_003…` einmal rot – zeitabhängig, einzeln 3× grün, Folgelauf komplett grün) · Multisite 533 OK · Free-Guard 0 · Textdomain-Lint 0 · ApiContractTest-Snapshots aktualisiert (`routes.v1.json` +1 Route, `hooks.v1.json` +1 Filter). Neue msgids: 66 (4 entfallen).
