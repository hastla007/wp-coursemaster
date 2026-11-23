# R1-29 · Referenz-Testumgebung (R0-26 §3.3, §6)

> **Nur für Test-Sites.** `tools/usability/setup-site.sh` löscht die angegebene Datenbank und das Site-Verzeichnis und legt Konten mit Passwörtern an. Der WP-CLI-Befehl `wp ccap-ux` arbeitet nur, wenn in der `wp-config.php` die Konstante `CCAP_UX_TESTSITE` gesetzt ist; das Skript setzt sie. Auf einer Produktiv-Site niemals ausführen.

> **PHP 8.5 nötig (MINVER-1).** Das Skript nutzt automatisch `php8.5`, wenn das Standard-`php` älter ist. Für die Befehle `wp ccap-ux …` von Hand ebenfalls PHP 8.5 verwenden, z. B. `PATH=<Verzeichnis mit php → php8.5>:$PATH` oder `WP_CLI_PHP=php8.5`.

## 1. Überblick

| Baustein | Datei | Aufgabe |
|---|---|---|
| Aufbau-Skript | `tools/usability/setup-site.sh` | frisches WordPress aus `vendor/roots/wordpress-no-content`, Course Captain **aus dem Release-ZIP** (`tools/release/build-zip.php` + `check-zip.php`), Mail-Sink, Startzustand je Profil. Ein erneuter Lauf = Zurücksetzen auf den Startzustand |
| Befehl `wp ccap-ux` | `tools/usability/seeder.php`, `lib/*.php` | `seed`, `check-rk`, `kit`, `scenario` (über `wp --require=…`, weil das WP-CLI dieses Repos kein `eval`/`eval-file`/`db` hat) |
| Paket `cc-fx-rk-v1` | `tools/usability/content/rk-v1.php` | drei neutrale Referenzkurse nach R0-26 §1.1 (je 10 Lektionen, 50 Fragen, 1 Prüfung, 1 Aufgabe), CC0, eigene Inhalte, `example.test` |
| Autoloader-Isolierung | `tools/usability/isolate-autoload.php` | sorgt dafür, dass die Test-Site den Code des installierten ZIPs ausführt und nicht `<repo>/src` (siehe §6) |
| Mail-Sink | `tools/usability/mailsink.php` | Mu-Plugin; alle Mails landen in `wp-content/ccap-ux-mails/mails.log` (per `.htaccess` gesperrt) |
| Medien | erzeugt von `lib/Media.php` | Bilder (GD), PDFs, WebVTT, Testvideo (ffmpeg, falls vorhanden) – alles synthetisch |

`tools/` ist in `.gitattributes` `export-ignore` und gelangt nicht ins Release-ZIP (`check-zip.php` geprüft).

## 2. Profile und Aufruf

```bash
# Lernende, Trainer, Gruppenverwaltung, AU-T13 (Standard):
tools/usability/setup-site.sh --profil referenz --site /srv/ux/referenz --db ccap_usab_referenz \
    --url https://ux-referenz.staging.example --kit /srv/ux/kit
# Autorensitzung (Setup abgeschlossen, kein Kurs, Rolle Kursmanager):
tools/usability/setup-site.sh --profil autor --site /srv/ux/autor-p01 --db ccap_usab_autor_p01 --url https://ux-autor.staging.example
# Admin-Setup (Assistent offen, nur RK-3 für AD-T06):
tools/usability/setup-site.sh --profil admin --site /srv/ux/admin-p01 --db ccap_usab_admin_p01 --url https://ux-admin.staging.example
```

| Option | Standard | Hinweis |
|---|---|---|
| `--profil` | `referenz` | `referenz`, `autor`, `admin` |
| `--site` | `/tmp/ccap-usab-<profil>` | DocumentRoot; ein vorhandenes Verzeichnis wird nur ersetzt, wenn es die Markierung `.ccap-ux-testsite` trägt |
| `--db` | `ccap_usab_<profil>` | muss mit `ccap_usab` beginnen; Zugang über `WP_TESTS_DB_USER/_PASSWORD/_HOST` (Standard `ccap`/`ccap`/`localhost`) |
| `--url` | `https://ccap-usab-<profil>.example.test` | echte Staging-Adresse mit **https** (Untertitel- und Video-Adressen müssen https sein) |
| `--kit` | `/tmp/ccap-usab-kit` | nur `referenz`: Drehbücher, Soll-Dateien, Fragenpakete, Medien für die Autorensitzungen |
| `--lernende` | `9` | Anzahl frischer Konten `cc_fx_ux_p01 …` |
| `--worktree` | aus | ZIP aus dem Arbeitsbaum statt aus `HEAD` |

Umgebungsvariablen: `CCAP_UX_EXTERNAL_VIDEO_URL` (Video auf einem Fremdhost für LE-T03/E-45a; ohne sie zeigt das „externe“ Video auf die eigene Site und das Skript warnt), `CCAP_UX_CORE_LANG_ZIP` (lokales WordPress-Sprachpaket `de_DE.zip`, falls `downloads.wordpress.org` nicht erreichbar ist).

**Datenbank anlegen (einmalig, als MySQL-Admin):** `GRANT ALL PRIVILEGES ON \`ccap\_usab%\`.* TO 'ccap'@'localhost';` – damit darf das Skript alle `ccap_usab…`-Datenbanken löschen und neu anlegen, aber keine andere.

**Je Person eine frische Umgebung (R0-26 §3.3):** Autoren und Admins bekommen je Sitzung eine eigene Site (`--site …/autor-p01 --db ccap_usab_autor_p01`) oder dieselbe Site wird vor jeder Sitzung neu aufgebaut. Lernende arbeiten auf dem Referenzstand mit je einem eigenen Konto `cc_fx_ux_pNN`; nach der Runde wird der Referenzstand neu aufgebaut. Lernende arbeiten **nie** mit Kursen aus Autorensitzungen.

## 3. Konten (Profil `referenz`)

Passwörter werden bei jedem Lauf neu erzeugt und **nur einmal** auf der Konsole ausgegeben (nicht gespeichert). Die Moderation überträgt sie auf die Zugangskarte der Sitzung (Vorlage in `preflight-checkliste.md`) und vernichtet die Karte danach.

| Konto | Rolle | Zustand | Für |
|---|---|---|---|
| `cc_fx_ux_admin` | Administrator | – | Moderation, Störfälle, AD-Aufgaben |
| `cc_fx_ux_kursmanager` | Kursmanager | – | AU-T10, AU-T11, **AU-T13** (bewertet die vorbereiteten Abgaben, E-288) |
| `cc_fx_ux_trainer` | Trainer | im Kursteam aller drei Kurse | Bewertungswarteschlange, Rückgabe für LE-T12 |
| `cc_fx_ux_p01 … p09` | Abonnent | RK-2 und RK-3 freigeschaltet, **RK-1 ohne Zugang** | frische Lernende `P-LE-nn` (LE-T01 … T11) |
| `cc_fx_ux_teilweise` | Abonnent | RK-1: Lektionen 1–4 abgeschlossen, Lektion 5 begonnen (40 %) | LE-T10 (Fortsetzen) |
| `cc_fx_ux_pruefung` | Abonnent | **laufende Prüfung** RK-1 (ohne Zeitlimit, 10 Antworten, 2 Markierungen); RK-2 freigeschaltet | UX-026 Wiederaufnahme, AT-Sitzungen |
| `cc_fx_ux_abgelaufen` | Abonnent | RK-2-Prüfung **abgelaufen**, automatisch ausgewertet (`end_reason = expired`) | UX-032 Ergebnisseite |
| `cc_fx_ux_ablauf` | Abonnent | RK-2 freigeschaltet; Versuch mit 1 min Restzeit per `scenario ablauf` | UX-032 live |
| `cc_fx_ux_bestanden` | Abonnent | RK-2 bestanden, **Nachweis ausgestellt** | LE-T08b |
| `cc_fx_ux_faellig` | Abonnent | RK-2-Lernhistorie, 6 Fragen fällig | LE-T07 |
| `cc_fx_ux_ausgleich` | Abonnent | RK-2 mit Zeitausgleich 150 % | UX-075 |
| `cc_fx_ux_rueckgabe` | Abonnent | RK-1-Aufgabe **zurückgegeben** mit Kommentar | LE-T12 |
| `cc_fx_ux_abgabe1 … 3` | Abonnent | je Kurs eine Abgabe „gut“, „unvollständig“, „mit Datei“ – **wartet auf Bewertung** (9 Abgaben) | AU-T13 |
| `cc_fx_ux_gv` | Abonnent | Besitzerin der RK-3-Gruppe (5 Plätze, 2 belegt) | GV-T01 … T06 |
| `cc_fx_ux_team1`, `team2` | Abonnent | Gruppenmitglieder RK-3 | Belegung |
| `cc_fx_ux_beitritt` | Abonnent | – | GV-T04/GV-T07 (Moderation tritt bei) |

Profil `autor`: `cc_fx_ux_admin`, `cc_fx_ux_autor` (Kursmanager; Setup als abgeschlossen markiert; kein Kurs – Startzustand R0-26 §1.2). Profil `admin`: `cc_fx_ux_admin`, `cc_fx_ux_gv_kandidatin` (künftige Gruppenbesitzerin für AD-T06); Setup-Assistent offen, Registrierung aus, RK-3 vorhanden.

Weitere Zustände: Katalogseite `/kurse/` mit dem Block „Kurskatalog“; RK-1 Teilumfang „erste 3 Lektionen“; RK-3 Modul 2 per Drip ab Tag 1 (für frische Konten am Sitzungstag gesperrt, UX-031).

## 4. Befehle während der Sitzung

```bash
UX="vendor/bin/wp --allow-root --path=<site> --require=tools/usability/isolate-autoload.php --require=tools/usability/seeder.php ccap-ux"
$UX scenario laufende-pruefung [--konto=cc_fx_ux_pNN]   # RK-2-Versuch (45 min) mit 10 Antworten, 2 Markierungen
$UX scenario ablauf [--konto=cc_fx_ux_ablauf]           # RK-2-Versuch mit 1 min Restzeit (UX-032)
$UX scenario sitzungen-beenden --konto=<login>          # alle Anmeldesitzungen beenden (Störfall UX-012)
$UX scenario warteschlangenfehler                       # eine Zustellung auf „failed“ setzen (AD-T05)
$UX scenario einschreiben --konto=<login> --kurs=rk1    # Zugang setzen (Ersatz, solange es keine Selbstanmeldung gibt)
$UX check-rk rk1 [--kurs=<ID|slug>]                     # Soll-Ist-Vergleich nach der Autorensitzung (S-06)
$UX kit --ziel=<verzeichnis>                            # Autoren-Kit neu schreiben (nur Profil referenz)
```

`--konto` statt `--user`: `--user` ist ein globaler WP-CLI-Parameter und würde den Befehl unter diesem Konto ausführen.

**Zeitkritisch:** RK-2-Versuche laufen mit echter Uhr (45 min). Darum entsteht die laufende RK-2-Prüfung erst kurz vor der Aufgabe per `scenario laufende-pruefung`; die vorbereitete laufende Prüfung von `cc_fx_ux_pruefung` liegt im RK-1-Quiz ohne Zeitlimit und überdauert die Zeit bis zur Sitzung.

## 5. Autoren-Kit (`--kit`, R0-26 §1.1 „Lieferform“)

| Datei | Inhalt |
|---|---|
| `drehbuch-rk<n>.md` | Teilnehmenden-Drehbuch: Kurs, Lernziele, Module/Lektionen mit Texten, 10 Fragen zum Abtippen (AU-T04/T05), Prüfung, Aufgabe, Zugang. Enthält die eingebauten Fehler (AU-T02 falsche Position, AU-T09 Alt-Text bei RK-3, Blueprint bei RK-2) ohne Markierung |
| `drehbuch-rk<n>-moderation.md` | dieselbe Fassung mit markierten Fehlern und Soll-Werten – **nicht an Teilnehmende** |
| `rk<n>-soll.json` | Soll-Zustand für `check-rk` |
| `rk<n>-q40.zip` | CC-Paket v1 mit den übrigen 40 Fragen. Ersatzweg R0-26 §1.1: Kurs ohne Lektionen, weil das Paketformat keinen Import nur von Fragen kennt (Delta R0-10). Eine Bildfrage hat absichtlich keinen Alt-Text (AU-T09, RK-1/RK-2). Beim Schreiben läuft ein Dry-run (Upload, Validierung, Vorschau, Verwerfen) |
| `medien/` | Bilder, PDFs, Video, Untertitel |

Eine Kopie der Drehbücher und Soll-Dateien des Laufs vom 17.11.2026 liegt in `docs/r1/R1-29/kit/` (generiert; Quelle bleibt `content/rk-v1.php`).

## 6. Wichtige technische Hinweise

- **Autoloader:** `vendor/bin/wp` registriert den Composer-Autoloader des Repositorys vor dem Plugin-Autoloader; `CourseCaptain\` würde dann aus `<repo>/src` statt aus dem installierten ZIP geladen (in Worktrees mit verlinktem `vendor/` sogar aus einem anderen Checkout). `isolate-autoload.php` entfernt diese Zuordnung am WP-CLI-Hook `before_wp_load`. Der Seed-Lauf gibt zur Kontrolle aus, woher der Plugin-Code kommt („Plugin-Code aus: …/wp-content/plugins/course-captain/src“). Die Isolierung steht seit 17.11.2026 in `tools/qa/wp-cli-autoload-isolation.php` (`isolate-autoload.php` bindet sie ein); auch `tools/release/artifact-test.sh` nutzt sie und bricht zusätzlich ab, wenn eine Plugin-Klasse von außerhalb des Plugin-Ordners der Test-Site geladen wird.
- **Sprache:** Ohne WordPress-Sprachpaket `de_DE` bleibt der Lernbereich englisch, weil WordPress nur installierte Sprachen schaltet. Auf der Staging-Site Sprachpaket installieren (E-169) oder `CCAP_UX_CORE_LANG_ZIP` setzen.
- **Webserver:** Apache mit der erzeugten `.htaccess` (Ziel-Zeile R0-14 §3) oder nginx mit `try_files $uri $uri/ /index.php?$args;`; AD-T03 verlangt zusätzlich eine nginx-Site **ohne** Dateischutz-Regel.
- **Zeitzone** Europe/Berlin, Registrierung aus, Suchmaschinen ausgeschlossen (`blog_public = 0`), Theme Twenty Twenty-Five.
- **Mails:** Benachrichtigungen laufen über die Outbox (WP-Cron). Auf Staging WP-Cron per Systemcron anstoßen, sonst erscheinen Mails verzögert im Mail-Sink.
