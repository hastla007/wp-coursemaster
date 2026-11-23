# R1-29 · Echte Autoren- und Lernenden-Usability-Abnahme – Vorbereitung

**Stand:** 17.11.2026 · **Branch:** `r1-par-d` (Basis 6f6f05f)
**DoD (TODO R1-29):** „Referenzaufgaben durchgespielt; Tastatur, Screenreader, mobile Prüfungen und Fehlerbehebung mit dokumentierten Befunden.“
**Status:** **Vorbereitet, nicht durchgeführt.** Die Sitzungen mit echten Personen führt der Auftraggeber durch. R1-29 bleibt in der TODO offen, bis Runde 1/2 gelaufen und der Abnahmebericht freigegeben ist. Es gibt **keine** Messwerte aus Nutzertests in diesem Dokument.
**Quellen:** R0-26 (vollständig, inkl. §8.1 OP-26-1…4), R0-15 (A11Y-IDs, §2.3/§2.4), R1-19-Nachweis (automatisiert erledigt, NVDA/VoiceOver offen), R1-16-Nachweis (A-1 Lektionsansicht offen), R1-32-Nachweis (Setup), R1-46-Nachweis (Aufgaben, §5 ohne Oberfläche), `docs/user/*`. Clean Room (E-01): alle Kursinhalte, Medien und Werkzeuge sind neu; keine LearnBoating-Inhalte.

## 1. Was vorbereitet ist

| Bereich | Datei(en) | Inhalt |
|---|---|---|
| Übersicht | `docs/r1/R1-29/README.md` | Reihenfolge der Nutzung aller Unterlagen |
| Bereitschaft | `docs/r1/R1-29/bereitschaft.md` | je Referenzaufgabe: bereit / teilweise / blockiert mit Grund und zuständigem Task; Vorbereitungsbefunde B-01 … B-07; Rauchtest; Empfehlung für den Ablauf |
| Moderationsleitfaden | `docs/r1/R1-29/moderationsleitfaden.md` | Sitzungsablauf mit Zeitplan je Rolle, Einwilligungstext, Datenschutzregeln, laut denken (Screenreader retrospektiv), Hinweisstufen H0–H2, Abbruchregeln, SEQ, SUS mit Auswertungsformel, Vorab-Fragebogen V1–V8, Nachgespräch mit Verständnisfragen |
| Aufgabenskripte | `skript-autor.md`, `skript-lernende.md`, `skript-gruppenverwalter.md`, `skript-admin.md` | je Aufgabe: Kartentext, Startzustand, Erfolgskriterium, Zielzeit und Abbruchgrenze (3 ×, max. 20 min; Hilfstechnik 4 ×), Beobachtungspunkte, objektive Systemprüfung, ★ und UX-ID; Störfall UX-012; Soll-Punkte für AU-T13 |
| Hilfstechnik/Mobil | `szenarien-hilfstechnik.md` | AT-06 Tastatur, AT-01/02 NVDA + Firefox/Chrome, AT-03 VoiceOver macOS + Safari, AT-04 VoiceOver iOS mit echtem Netzabbruch, AT-07 Vergrößerung, AT-08 Kontrast, AT-09 Bewegung, AT-05 TalkBack, AT-10 JAWS; mobile Prüfungsläufe MP-1 … MP-5; Expertendurchläufe UX-076 … UX-081 mit Seitencheckliste |
| Fehlerbehebung | `szenarien-fehlerbehebung.md` | FB-01 … FB-17: Auslöser, Karte, Soll, Messung, Bezug, Stand |
| Protokoll | `vorlage-protokoll.md`, `protokoll-aufgaben.csv`, `zeiterfassung.csv`, `sus-auswertung.csv` | Sitzungskopf, Aufgabentabelle (E/EH/F/Abbr./n. d., H0/H1/H2, Bedien-/kritische Fehler, SEQ), Störfälle, SUS-Rohwerte und Score, Nachgespräch, Systemprüfung |
| Befundregister | `vorlage-befunde.md`, `befunde.csv` | Spalten ID, Schweregrad, Häufigkeit, Aufgabe, Testfall, Beobachtung, Erwartung, WCAG/A11Y, Empfehlung, Zuständig, Status, Regressionstest; vorbelegt mit UXB-001 … UXB-005 aus der Vorbereitung |
| Abnahmebericht | `vorlage-abnahmebericht.md`, `schwellen.csv` | S-01 … S-EXP mit Soll/Ist, UX-Testfälle, Befundstatus, akzeptierte S2, Baselines §7, Datenschutz, Freigabe |
| Preflight | `preflight-checkliste.md` | einmalig vor der Runde, am Sitzungstag (Browser-/AT-Versionen, Aufzeichnung, Einwilligung, Zurücksetzen), Zeitplan-Bogen, Zugangskarte, Nacharbeit |
| Rekrutierung | `rekrutierungsplan.csv` | Pseudonym, Rolle, RK, Format, Matrix-Zeilen, Site, Konto |
| Testumgebung | `docs/r1/R1-29/testumgebung.md`, `tools/usability/*` | reproduzierbare Test-Sites (§2) |
| Referenzkurse | `tools/usability/content/rk-v1.php`, `docs/r1/R1-29/kit/` | Paket `cc-fx-rk-v1`: RK-1/RK-2/RK-3 mit genau dem Mengengerüst aus R0-26 §1.1 (Lektionen 4/3/3, 3/3/2/2, 5/5; je 50 Fragen im Typmix nach E-22/E-69; Prüfungen; je eine Aufgabe nach E-168), eigene, verständliche Inhalte unter CC0; Drehbücher (Teilnehmende/Moderation) und Soll-Dateien als Kopie |

## 2. Testumgebung: Aufbau und Lauf in diesem Container

### 2.1 Werkzeuge (`tools/usability/`, `export-ignore`)

| Datei | Aufgabe |
|---|---|
| `setup-site.sh` | baut eine Test-Site: Release-ZIP (`build-zip.php`, `check-zip.php`), WordPress-Kern aus `vendor/roots/wordpress-no-content`, `wp-config` mit `CCAP_UX_TESTSITE`, Datenbank `ccap_usab…` (andere Namen werden abgelehnt), Installation, Sprachpaket (falls erreichbar), Permalinks, `.htaccess`, Plugin aus dem ZIP, Mail-Sink, Seed. Neu ausführen = Zurücksetzen |
| `seeder.php` + `lib/Builder.php`, `lib/Scenarios.php`, `lib/Kit.php`, `lib/Media.php` | WP-CLI-Befehl `wp ccap-ux seed|check-rk|kit|scenario` über `wp --require` (dieses WP-CLI hat weder `eval`/`eval-file` noch `db`). Baut Kurse, Fragen, Prüfungen, Aufgaben, Zugänge über die Plugin-Dienste (`CourseStructure`, `ActivityService`, `QuestionRepository`, `ExamRules`, `GrantService`, `GroupService`, `ExamSessions`, `AssignmentService`, `CertificateService`, `SmartTrainer`); Muster und Konventionen aus `NeutralFixture` (idempotente Schritte, `cc_fx_…`-Konten, `PackageExporter::sealed`). Die Platzhaltertexte von `cc-fixture-neutral-v1` sind für Nutzertests ungeeignet (R0-26 §0.2 Nr. 3), daher eigener Inhalt |
| `isolate-autoload.php` | verhindert, dass `vendor/bin/wp` die Klassen `CourseCaptain\` aus `<repo>/src` statt aus dem installierten ZIP lädt (Befund B-07) |
| `mailsink.php` | Mu-Plugin, schreibt alle Mails nach `wp-content/ccap-ux-mails/mails.log` |
| `content/rk-v1.php` | Inhalt `cc-fx-rk-v1` |

Determinismus: Konten, Inhalte, Medien und Prüfungsziehungen (fester `Randomizer`-Seed je Konto und Regel) sind bei jedem Neuaufbau gleich; nur die Passwörter werden neu erzeugt und **einmal** ausgegeben. Zwei aufeinanderfolgende Läufe ergaben dieselbe Prüfungsziehung (MD5 über alle Sitzungsfragen identisch).

### 2.2 Aufruf

```bash
# einmalig als MySQL-Admin
GRANT ALL PRIVILEGES ON `ccap\_usab%`.* TO 'ccap'@'localhost';
# Referenzstand + Autoren-Kit
tools/usability/setup-site.sh --profil referenz --url https://<staging> --site <docroot> --db ccap_usab_referenz --kit <kit-dir>
# Autorensitzung bzw. Admin-Setup, je Person eine frische Site
tools/usability/setup-site.sh --profil autor --site <docroot>/autor-p01 --db ccap_usab_autor_p01 --url https://<staging-autor>
tools/usability/setup-site.sh --profil admin --site <docroot>/admin-p01 --db ccap_usab_admin_p01 --url https://<staging-admin>
```

Konten, Zustände und Szenario-Befehle: `docs/r1/R1-29/testumgebung.md` §3/§4.

### 2.3 Ergebnis des Laufs (17.11.2026, PHP 8.3.6, WordPress 7.1.4, MariaDB, dieser Container)

`tools/usability/setup-site.sh --profil referenz --url http://127.0.0.1:8099 --kit /tmp/ccap-usab-kit` – Exit 0, **31–37 s** gesamt, Seed 14–17 s. Ausgabe (Passwörter entfernt):

```
[1/7] Release-ZIP bauen (--ref=HEAD)
…
WARNUNG: WordPress-Sprachpaket de_DE fehlt (kein Download möglich). …
[5/7] Course Captain aus dem ZIP installieren und aktivieren
Plugin 'course-captain' activated.
[6/7] Startzustand Profil referenz anlegen
rk1: Drehbuch, Soll, Fragenpaket mit 40 Fragen; Paketprüfung: gültig, Dry-run previewed {"questions":{"create":40},"modules":{"create":3},"courses":{"create":1}}
rk2: … {"questions":{"create":40},"modules":{"create":4},"courses":{"create":1}}
rk3: … {"questions":{"create":40},"modules":{"create":2},"courses":{"create":1}}
=== NUR TEST-SITE · Profil referenz · cc-fx-rk-v1 ===
Zugangsdaten (werden nur jetzt angezeigt und nirgends gespeichert):   [27 Konten]
[Kontrolle] Plugin-Code aus: /tmp/ccap-usab-referenz/wp-content/plugins/course-captain/src
[Kontrolle] teilweise (RK-1): l01=completed, l02=completed, l03=completed, l04=completed, l05=started
[Kontrolle] pruefung (RK-1): laufende Sitzung 1
[Kontrolle] abgelaufen (RK-2): Sitzung 2 status=evaluated end_reason=expired
[Kontrolle] bestanden (RK-2): Sitzung 3 passed=ja Nachweis=<uid>
[Kontrolle] faellig (RK-2): 6 Fragen fällig
[Kontrolle] rueckgabe (RK-1): status=returned
[Kontrolle] Abgaben eingereicht und unbewertet: 9
[Kontrolle] gruppe (RK-3): belegt 2 von 5
[Kontrolle] Katalogseite: http://127.0.0.1:8099/kurse/
[Kontrolle] check-rk rk1: grün
[Kontrolle] check-rk rk2: grün
[Kontrolle] check-rk rk3: grün
Warning: rk1/rk3: externes Video zeigt auf die eigene Site. Für E-45a einen Fremdhost über CCAP_UX_EXTERNAL_VIDEO_URL setzen.
[7/7] Fertig in 34 s
```

Weitere Läufe: Profil `autor` (16 s; 2 Konten, Setup abgeschlossen, kein Kurs) und Profil `admin` (30 s; Setup offen, RK-3, 2 Konten) – beide Exit 0, Plugin-Code aus dem ZIP. Szenario-Befehle geprüft: `scenario ablauf` (Restzeit 60 s), `scenario laufende-pruefung --konto=cc_fx_ux_p03` (2700 s), `scenario warteschlangenfehler` (Zustellung `failed`), `scenario sitzungen-beenden` (danach Lernbereich → Anmeldung), `scenario einschreiben`, `check-rk rk2` (28 Merkmale OK), `check-rk <soll.json>` (25 OK), `check-rk rk1 --kurs=999` (Fehler), erneutes `seed` (abgelehnt: „schon befüllt“), Befehl ohne `CCAP_UX_TESTSITE` (abgelehnt). HTTP-Rauchtest mit `php -S`: `bereitschaft.md` §3.

### 2.4 Gates

| Prüfung | Ergebnis |
|---|---|
| `vendor/bin/phpcs` (Projekt) | 0 Fehler, 1 Warnung (bekannt: ImportTest.php:318) |
| `vendor/bin/phpcs --standard=phpcs.xml.dist tools/usability` (zusätzlich, nicht im Gate) | 0 Fehler, 0 Warnungen |
| `vendor/bin/phpstan analyse --no-progress --memory-limit=2G` | OK, 0 Fehler |
| `vendor/bin/phpstan analyse -c phpstan.neon.dist tools/usability` (zusätzlich) | OK, 0 Fehler (Level 8) |
| Unit `vendor/bin/phpunit -c phpunit.xml.dist` | OK, 107 Tests, 2 881 Zusicherungen |
| Integration/Multisite | nicht nötig: `src/` unverändert |
| `tools/release/check-zip.php` | bestanden (in jedem `setup-site.sh`-Lauf und nach dem Commit, §5); `tools/` und `docs/` sind `export-ignore` |

## 3. Was für den Auftraggeber bleibt

| Nr. | Aufgabe | Bezug |
|---|---|---|
| AG-1 | **Bereitschaft herstellen lassen** oder Umfang beschließen: Befunde B-01 (Lektionslinks 404) und B-02 (Selbstanmeldung) sowie die blockierten Oberflächen der ★-Aufgaben (Fragen-/Prüfungs-Editor, Bewertung, Abgabe, Smarttrainer, Gruppen-Besitzeroberfläche) vor Runde 1. Bis dahin nur Pilot/Expertendurchläufe für Flow 4, Prüfungsraum und Nachweise | `bereitschaft.md` §4, R0-26 §4.2 Schritt 2 |
| AG-2 | Budgetbetrag nennen (E-162) | OP-26-2 |
| AG-3 | Rekrutierung (E-161, E-163): 6 Autoren, 5 + 4 Lernende, 5 Gruppenverwaltung, 5 Admins, 1 A11Y-Fachperson; externe Moderation für Barrierefreiheitssitzungen (E-164); Personen dürfen die CC-Oberfläche nicht kennen | R0-26 §2.1 |
| AG-4 | Staging nach R0-14 §3 inkl. nginx-Site, Sprachpaket de_DE, https, Fremdhost-Video, Testgeräte nach R0-26 §3.2 | `preflight-checkliste.md` A |
| AG-5 | Einwilligungstext und Datenschutzbewertung der Hilfstechnik-Angaben (R0-17, Art. 9 DSGVO); Speicherort und Löschtermin der Aufzeichnungen | R0-26 §4.1, E-167 |
| AG-6 | Typmix und Aufgabenkonfiguration der Referenzkurse bestätigen (`content/rk-v1.php`, Drehbücher); Soll-Punkte AU-T13 bestätigen; Frage zu B-03 (Karteikarten im Prüfungspool) entscheiden | OP-26-4, AG-12 |
| AG-7 | Praxis-Benchmark UX-090 und Pilot; Sitzungsdauer Autor bestätigen (OP-26-1); Schwellen S-06 … S-10 einfrieren | R0-26 §4.2 Schritt 3, E-166 |
| AG-8 | SUS-Fassung (deutsch, unverändert) festlegen | R0-26 §2.3 Nr. 6 |
| AG-9 | Expertendurchläufe A11Y-102/103 (AT-01 … AT-09) | R0-26 §4.2 Schritt 4 |
| AG-10 | Runde 1 durchführen, protokollieren, Befund-Triage, Behebung S1/S2, Runde 2 | R0-26 §4.2 Schritte 5–8 |
| AG-11 | Abnahmebericht nach Vorlage; Freigabe; erst dann R1-29 in der TODO abhaken | R0-26 §4.2 Schritt 9, UX-091 |

## 4. Zuordnung zu R0-26

| R0-26 | Umsetzung in der Vorbereitung |
|---|---|
| §0.2 Nr. 1 (Aufgabe in R1, E-168) | jeder RK enthält genau eine Aufgabe (RK-1 Text 10/6 „bewertet“, RK-2 PDF 20/12 „bestanden“, RK-3 Text + Datei 10 ohne Grenze „eingereicht“) |
| §0.2 Nr. 2 (Video Pflicht) | je RK internes Video mit WebVTT + externes Video (Fremdhost per `CCAP_UX_EXTERNAL_VIDEO_URL`) |
| §0.2 Nr. 3 (verständliche Inhalte) | eigener Inhalt, jede Frage aus dem Lektionstext beantwortbar, CC0, `example.test`, `cc-fx-rk…` |
| §0.2 Nr. 4 (Free ohne Pro) | Profil installiert nur Course Captain Free aus dem Release-ZIP |
| §1.1 Paket `cc-fx-rk-v1` | `content/rk-v1.php`; Seed prüft das Mengengerüst mit `check-rk` (grün); Drehbücher, `rk<n>-q40.zip` (Ersatzweg: Kurs ohne Lektionen, Import-Dry-run grün), `rk<n>-soll.json`; vorbereitete Abgaben (3 je RK) und zurückgegebene Abgabe |
| §1.2 Flow 1 (AU-T01 … T13, AU-GES, Störfall) | `skript-autor.md`; Profil `autor`; AU-T13 auf der Referenz-Site |
| §1.3 Flow 2 (LE-T01 … T12, LE-GES, LE-T08b) | `skript-lernende.md`; Konten `p01…`, `teilweise`, `pruefung`, `faellig`, `bestanden`, `rueckgabe` |
| §1.4 Flow 3 (GV-T01 … T07) | `skript-gruppenverwalter.md`; Gruppe RK-3 mit 5 Plätzen, Besitzerin `cc_fx_ux_gv` |
| §1.5 Flow 4 (AD-T01 … T06) | `skript-admin.md`; Profil `admin`; Störfall AD-T05 `scenario warteschlangenfehler` |
| §2.1 Rollen/Profile/Anzahl | `rekrutierungsplan.csv`, Vorab-Fragebogen im Leitfaden §5 |
| §2.2 moderiert/unmoderiert | Leitfaden §1/§3; GV-Skript für unmoderierte Sitzungen |
| §2.3 Aufgabenskripte, SEQ, SUS, Hinweisstufen, Abbruch | Skripte je Rolle (Ablage hier statt `docs/ux/`), Leitfaden §3/§4 |
| §2.4 Messgrößen | `vorlage-protokoll.md`, `protokoll-aufgaben.csv`, `zeiterfassung.csv`, `sus-auswertung.csv` |
| §2.5 Schwellen S-01 … S-EXP | `schwellen.csv`, `vorlage-abnahmebericht.md` §3 |
| §3.1 AT-01 … AT-10 | `szenarien-hilfstechnik.md` §2 |
| §3.2 BR-01 … BR-09, mobile Prüfungsläufe | `szenarien-hilfstechnik.md` §3 (MP-1 … MP-5), Preflight |
| §3.3 Testumgebung | `tools/usability/setup-site.sh` (Loader idempotent bzw. Neuaufbau, Reset je Sitzung = `reset-session`-Vorschlag), Mail-Sink, Störfall-Werkzeug `scenario` (nur mit `CCAP_UX_TESTSITE`), keine Fremd-Skripte |
| §4.1 Einwilligung/Datenschutz | Leitfaden §2 |
| §4.2 Ablauf Schritte 1–9 | `bereitschaft.md` §4, Preflight A, Leitfaden §7, Abschnitt 3 dieses Dokuments |
| §4.3 Protokoll- und Befundvorlage | `vorlage-protokoll.md`, `vorlage-befunde.md`, `befunde.csv` |
| §4.4 Schweregrade | `vorlage-befunde.md` (Kurzfassung), Triage-Regeln |
| §5 UX-001 … UX-091 | in den Skripten je Aufgabe genannt; Stand je Testfall in `bereitschaft.md` |
| §6 Übergaben | Paket + `check-rk` + Reset + Störfall-Werkzeug geliefert (`tools/usability` statt `tools/ux`); Delta R0-10 „Import nur von Fragen“: **nein**, Ersatzweg umgesetzt; Datenschutzbewertung R0-17: offen (AG-5) |
| §7 Baselines | `vorlage-abnahmebericht.md` §6 |
| §8.1 OP-26-1 … OP-26-4 | AG-7, AG-2, (erledigt durch E-288: AU-T13 mit Kursmanager), AG-6 |

## 5. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Ablageort | R0-26 nennt `docs/ux/…` und `tools/ux/…`; gebündelt unter `docs/r1/R1-29/` und `tools/usability/` (Auftrag) |
| A-2 | Reset je Sitzung | statt `reset-session <pNN>` vollständiger Neuaufbau der Site (≈ 35 s) – einfacher und garantiert den Startzustand; Lernende arbeiten in eigenen Konten des Referenzstands |
| A-3 | Laufende Prüfung | die vorbereitete laufende Prüfung liegt im RK-1-Quiz **ohne Zeitlimit**, weil ein 45-min-Versuch bis zur Sitzung abliefe; RK-2-Versuche entstehen per `scenario laufende-pruefung`/`ablauf` unmittelbar vor der Aufgabe |
| A-4 | RK-3 für frische Lernende | manuelle Freischaltung statt Gruppenplatz, damit die Gruppe (5 Plätze) für GV-Aufgaben frei bleibt; Drip wirkt identisch (Zugangsbeginn) |
| A-5 | Lernziele | kein eigenes Kursfeld; Lernziele im Beschreibungstext (Bereitschaft AU-T01) |
| A-6 | AU-T02 „falsche Position“ | im Teilnehmenden-Drehbuch steht die letzte Lektion von Modul 1 vorne; Karte AU-T02b fordert das Verschieben; Soll-Reihenfolge bleibt die des Pakets |
| A-7 | Alt-Text-Blocker RK-3 | RK-3 hat nur ein Bild (AU-T05); der Fehler steckt deshalb im Teilnehmenden-Drehbuch (Alt-Text fehlt), bei RK-1/RK-2 im Importpaket |
| A-8 | SUS-Wortlaut | gängige deutsche Fassung zur Orientierung; die verbindliche, unveränderte Fassung legt der Auftraggeber fest (AG-8) |
| A-9 | Sprache im Container | ohne Zugriff auf `downloads.wordpress.org` kein Sprachpaket de_DE; Lernbereich im Rauchtest englisch (B-06). Auf Staging installieren |

## 6. Folgeaufgaben

- Lektionsansicht im Lernbereich und Links aus dem Kursraum (B-01) – als Folgeaufgabe vorgeschlagen.
- `tools/release/artifact-test.sh` lud Plugin-Klassen aus dem Repository statt aus dem ZIP (B-07) – erledigt 17.11.2026: gemeinsame Isolierung `tools/qa/wp-cli-autoload-isolation.php` plus Prüfung der Klassenherkunft im Artefakttest.
- Karteikarten in Prüfungspools (B-03) – Produktentscheidung (AG-6).

Review durch zweite Person vor Merge (AG-05).
