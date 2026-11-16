# R0-15 · Barrierefreiheit und Internationalisierung

**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Keine Implementierung, kein Prüfprotokoll. Alle Testfälle sind Vorgaben für R1-19 (Umsetzung) und R1-29 (Abnahme mit Menschen); den Testplan mit Nutzertests schreibt R0-26.
**Stand:** 16.11.2026
**Bezug:** TODO R0-15 (DoD: „WCAG-Ziel, Tastatur-/Screenreader-/Kontrastfälle, Übersetzbarkeit, RTL, Zeitzonen, Datums-/Zahlenformate und Sprachwechsel definiert“), Folgetasks R0-25, R0-26, R1-19, R1-29 (dazu R0-22, R0-27, R1-20, R1-21); Konzept Kap. 4.1, 10, 16.2, 16.3 (Design-Gate), 19, 21, 23.1/23.2, Kap. 37 (UTC); `docs/r0/R0-11-free-pro-matrix-ux.md` (Screens A-01…A-18, L-01…L-21, Zustände Z-x, Flows 1–5); `docs/r0/R0-14-nichtfunktionale-ziele.md` §3 (Browser-/Viewport-Matrix, NFR-014/-016); `docs/r0/R0-08-kurs-activity-pruefungsvertraege.md` §4.3 (Zeitlimit, Grace, Kulanz), §6 (Automat, Snapshot); `docs/r0/R0-06-datenmodell-adr.md` §7 (Zeit in UTC); `docs/r0/R0-10-import-medien-sicherheit.md` (Alt-Text-Pflicht im Paketformat); Inventur D (INV-D-004, -005, -006, -062, -068, -070, -152, -154, -156) und E (INV-E-007, -046, -047, Doku `barrierefreiheit-oesterreich.md`, `uebersetzung.md`, `videos-untertitel.md`); `docs/r0/ENTSCHEIDUNGEN.md`.
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77`, nur gelesen. Pfade relativ zu `bootspruefung-learning/`, wenn nicht anders angegeben.
**Abgrenzung:** Rechtliche Anwendbarkeit (BFSG/BaFG, Kleinstunternehmen) beurteilt der Betreiber mit Rechtsberatung, nicht dieses Dokument. Mehrsprachige Kursvarianten, Locale-Fallback und Multisite gehören zu R0-25 (§4 übergibt). Testgeräte, Probanden und Usability-Kriterien gehören zu R0-26.

**Normative Sprache:** **MUSS** = Vertragsbestandteil, **SOLL** = Standard, **KANN** = optional. Mit den Beschlüssen unten ist der Inhalt verbindliche Vorgabe für R1-19/R1-29.

## Beschlussstand

Quelle: `ENTSCHEIDUNGEN.md`, Beschlussprotokoll und „Von Claude entschieden“ (16.11.2026).

| Beschluss | Wirkung in diesem Dokument |
|---|---|
| **E-01 Clean Room** | Kein LB-Code wird übernommen. Die LB-Belege in §0.2 sind reine Verhaltensbefunde; „reuse“/„abstract“ bedeutet „Verhalten nachbauen“. Die LB-Werkzeuge `tools/i18n/check-domains.php` und `tools/i18n/make-pot.sh` werden **nicht kopiert**, sondern nach der Verhaltensspezifikation in §3.8 neu geschrieben |
| E-121 (D-15-01) | Quellsprache en_US, Deutsch erste Übersetzung (§3.1) |
| E-122 (D-15-02), E-123 (D-15-03) | Nachteilsausgleich und Timer-Modi `strict`/`practice` wie §2.3 |
| E-124 (D-15-04) | Einstellung „Profilsprache“, Fristen mit Zonenkennung, keine Nutzerzeitzone in R1 (§3.3, §3.5) |
| E-125 (D-15-06) | Konformitätsbericht je Release, keine Vorlage für die Barrierefreiheitserklärung (E-04) |
| E-126 (D-15-08) | NVDA und VoiceOver (macOS, iOS) zugesichert; TalkBack best effort, JAWS nicht zugesichert (§2.4) |
| E-127 (D-15-09) | Getaggtes PDF als Pro-Abnahmekriterium |
| E-131 (D-15-10) | Fehlender Alt-Text (ohne `decorative`) = Blocker, fehlende Untertitel = Warnung (per Site-Einstellung Blocker); Widerspruch zu R0-22 aufgelöst |
| D-15-05, D-15-07, D-15-11 | Technisch festgelegt (ENTSCHEIDUNGEN §20): axe-Gate blockierend, Kontrast-Vorschlag, RTL als Release-Gate |
| E-61, E-191 | Browserachse (je 2 letzte Hauptversionen Chrome/Edge/Firefox/Safari, Firefox ESR, iOS Safari, Android Chrome); echte Geräte manuell vor jedem Minor-Release |
| **E-45, E-45a** | Externe Medien werden standardmäßig **direkt geladen** (`external_media_mode` = „immer laden“); der Consent-Platzhalter (A11Y-093) gibt es nur im Modus „Klick zum Laden“ |
| E-20, E-147 | Lösungserklärungen sind Pro; Free zeigt nach Abgabe richtig/falsch und (nach Einstellung) die richtige Antwort (A11Y-024) |
| E-208 | Deutsches Sprachpaket über translate.wordpress.org, bis zur Freigabe zusätzlich im ZIP |
| E-155…E-160 | Beantworten die Übergabefragen an R0-25 (§4) |
| E-207 | Präfix `ccap_` für CPTs, Caps, Rollen, Cron, Meta und Tabellen. Namen wie `ccap_accommodation`, `ccap_manage_accommodations`, `ccap_question` sind hier Arbeitsnamen und heißen in der Umsetzung `ccap_…` |

---

## 0. Annahmen und Belege

### 0.1 Beschlüsse aus `ENTSCHEIDUNGEN.md`

| ID | Beschluss | Wirkung hier |
|---|---|---|
| E-61 | Browser: je 2 letzte Hauptversionen Chrome, Edge, Firefox, Safari, dazu Firefox ESR, iOS Safari, Android Chrome | Browser-Achse der A11Y-Tests (§2.4) |
| E-11 | `@playwright/test` und PHPUnit | axe-core läuft in Playwright (`@axe-core/playwright`) |
| E-07, E-49 | Nur Single-Site | Locale-Fragen nur je Site (§4) |
| E-20, E-147 | Lösungserklärungen nur mit Pro; Free zeigt nach Abgabe richtig/falsch und (nach Einstellung) die richtige Antwort | Live-Region für Feedback nur, wenn Feedback gezeigt wird; Erklärungstext nur mit Pro (A11Y-024) |
| E-45, E-45a | Externe Medien Default „immer laden“, umschaltbar auf „Klick zum Laden“ | Platzhalter nur im Modus „Klick zum Laden“, dann mit Tastatur bedienbar und benannt (A11Y-093) |
| E-72 | Auto-Abgabe nach Inaktivität (Standard aus) | Warnhinweis vor Datenverlust (A11Y-076) |
| E-75 | `evaluated` + `end_reason = expired` | Ansage „Zeit abgelaufen“ statt eigenem Zustand (A11Y-073) |
| E-100 | Keine gesperrten Pro-Schalter in Free-Screens | keine „toten“ fokussierbaren Elemente |

### 0.2 LearnBoating-Belege (Verhalten übertragbar bzw. zu korrigieren)

Nach **E-01 (Clean Room)** wird kein Code übernommen. In der Spalte „Übernahme“ bedeutet **reuse** „Verhalten gleich nachbauen“ und **abstract** „Verhalten verallgemeinert nachbauen“; **replace** heißt „anderes Verhalten“. Die Fundstellen dienen nur als Verhaltensbeleg.

| ID | Fundstelle | Befund | Übernahme (Verhalten, E-01) |
|---|---|---|---|
| LB-A11Y-01 | `assets/build/app.js:14-16`, `:244-261` | Hülle ist keine Live-Region mehr; Seitenwechsel meldet die App über Fokus auf `h1` (`tabindex=-1`) und `document.title` | **reuse** (K-01, K-02) |
| LB-A11Y-02 | `app.js:208-224`, `app.css` „aria-disabled“ | Knopf sperren mit `aria-disabled`, Fokus bleibt, Sperre im Handler | **reuse** (K-04) |
| LB-A11Y-03 | `app.js:1283`, `:1658`, `:1827-1869`, `:282` | Status als `role=status` (`.bp-sr`), Inline-Fehler `role=alert` außerhalb polite-Regionen (keine Doppelansage) | **reuse** (K-05) |
| LB-A11Y-04 | `app.js:1430-1437` | Tablist mit Pfeil/Home/End und Roving-Tabindex – fest für **zwei** Tabs, Pfeilrichtung nicht RTL-bewusst | **abstract** (K-07, I18N-R-14) |
| LB-A11Y-05 | `app.js:1198` | Umschalter mit fester Beschriftung, Zustand nur in `aria-pressed` | **reuse** (K-08) |
| LB-A11Y-06 | `app.js:202-206` | `prefers-reduced-motion` schaltet weiches Scrollen ab | **reuse** (K-10) |
| LB-A11Y-07 | `app.js:4369-4374` | Prüfungstimer `role=timer` mit `aria-live=off`, Speicherstatus `aria-live=polite`, Fragennavigation als `ol` mit `aria-label` | **reuse**, ergänzt um Schwellen-Ansagen (A11Y-071) |
| LB-A11Y-08 | `app.js:2067-2081` | Antworten als `fieldset`; `legend` ist nur „Eine/mehrere Antworten wählen“, der **Fragetext ist nicht Teil der `legend`** | **abstract** (A11Y-022) |
| LB-A11Y-09 | `app.js:2138-2158` | Dialog mit Escape und Tastatur-Abfang in der Capture-Phase | **abstract** (K-09, A11Y-004) |
| LB-A11Y-10 | `app.css:1462-1476` | `scroll-margin` gegen Verdecken durch fixen Timer, Admin-Bar, sticky Antwortleiste | **reuse** (WCAG 2.4.11, A11Y-006) |
| LB-A11Y-11 | `templates/app.php:46`, `:56` | `language_attributes()`, ein `<main id="bp-main">` | **reuse**; Sprunglink fehlt (A11Y-020) |
| LB-A11Y-12 | `tests/e2e/learning-flow.mjs:193` | Kein waagerechtes Scrollen bei 390 px (`scrollWidth`-Prüfung) | **reuse**, auf 320 px verschärft (A11Y-050) |
| LB-A11Y-13 | Repo-Doku `docs/barrierefreiheit-oesterreich.md` §2 | axe-core 4.10, Regeln WCAG 2.0/2.1 A/AA, 22 Seitenaufrufe Desktop 1280/Mobil 390, 0 Verstöße nach Korrektur (Kontrast gesperrter Elemente); **axe ist nicht im Repository oder in der CI verankert** | Prüfmethode **abstract**, in CI verankern (A11Y-100) |
| LB-A11Y-14 | wie oben §3 | Offen in LB: Untertitel, getaggte PDFs, Alt-Text-Stichprobe, manueller NVDA-/VoiceOver-Durchlauf | als Pflichtfälle übernommen (A11Y-030 ff., A11Y-090 ff.) |
| LB-A11Y-15 | `tests/e2e/*.mjs` | Tastaturbedienung kaum getestet (1 Treffer in `learning-flow.mjs`, 3 in `trainers-flow.mjs`); Rollen-Locator (`getByRole`) durchgängig | Locator-Stil **reuse**; Tastaturpfade neu (A11Y-001 ff.) |
| LB-I18N-01 | Repo-Werkzeug `tools/i18n/check-domains.php` | Tokenizer-Lint: jede i18n-Funktion trägt die Domain als Literal; `.pot` je Paket vorhanden | **Verhalten nachbauen** (INV-E-046): Werkzeug wird neu geschrieben, nicht kopiert (§3.8); Domains `course-captain`, `course-captain-pro` |
| LB-I18N-02 | Repo-Werkzeug `tools/i18n/make-pot.sh` | `wp i18n make-pot` je Paket, leere Header, `--skip-theme-json`, Hinweis `make-json` | **abstract** (INV-E-047): eigenes Skript nach §3.8, kein Code übernommen |
| LB-I18N-03 | `src/Frontend/Strings.php` (INV-D-068) | 673 Texte als Bundle für die SPA; Mehrzahl über Schlüsselpaare `*_one`/`*` | **replace**: Mehrzahl nur über `_n` (Sprachen mit mehr als zwei Pluralformen, I18N-R-04) |
| LB-I18N-04 | `src/Frontend/Router.php:190`, `app.js:17-22` | Locale an den Client (`determine_locale()`, `de_AT`→`de-AT`), Rückfall `<html lang>`, sonst `de-DE` | **reuse**; Rückfall neutral `en` statt `de-DE` |
| LB-I18N-05 | `src/Gate/ContentGate.php:223` | `wp_set_script_translations()` nur für einen Block | **ausweiten** auf alle Skripte (I18N-R-10) |
| LB-I18N-06 | z. B. `src/Admin/SystemStatus.php:276`, `MailStatus.php:147`, `SiteHealth.php:62` | `wp_date('d.m.Y H:i', …)` mit **festem Format**; an anderen Stellen korrekt `get_option('date_format')` (`ImportPage.php:292`) | **replace**: nur WP-Formate (I18N-R-20) |
| LB-I18N-07 | `src/Exams/Services/ExamService.php:288-289` | Zeitstempel mit `gmdate()` in UTC | **reuse** (R0-06 §7) |
| LB-I18N-08 | `src/Support/Mail.php`; `src/` ohne `switch_to_locale`/`switch_to_user_locale` (nur Kommentar `Commerce/WooCommerce/WithdrawalTexts.php:111`) | Mails immer in Website-Sprache | **replace** (I18N-R-30) |
| LB-I18N-09 | `assets/build/app.css` (22 physische `left/right`-Eigenschaften, keine logischen) | nicht RTL-fähig | **replace** (I18N-R-12) |
| LB-I18N-10 | `app.js:4243`, `:4251`, `:4421` (INV-D-156) | Client wertet deutsche Fehlermeldungstexte aus | **replace**: nur stabile Codes (I18N-R-08) |
| LB-I18N-11 | Inventur C, INV-C-056 | Zeitzonen-Diagnose mit Hinweis „Berlin wählen“ | **abstract**: neutraler Hinweis „feste UTC-Abweichung ohne Sommerzeit“ (I18N-R-23) |

---

## 1. Zielstandard und Geltungsbereich

### 1.1 Ziel

- **MUSS:** Alle in §1.2 genannten Oberflächen erfüllen **WCAG 2.2, Stufe AA** (alle Erfolgskriterien A und AA). Das bestätigt Konzept 16.3 („WCAG 2.2 AA als Ziel“).
- **Bezug EN 301 549 / European Accessibility Act (kurz, keine Rechtsberatung):** Die Richtlinie (EU) 2019/882 (EAA) gilt seit 28.06.2025 für bestimmte Produkte und Dienstleistungen, darunter Dienstleistungen im elektronischen Geschäftsverkehr; umgesetzt z. B. in Deutschland durch das BFSG, in Österreich durch das BaFG. Als technische Vermutungsgrundlage dient die harmonisierte Norm **EN 301 549**. Ihre derzeit harmonisierte Fassung verweist für Web-Inhalte auf WCAG 2.1 AA; WCAG 2.2 AA deckt diese Kriterien ab (das in 2.2 entfallene 4.1.1 „Parsing“ ausgenommen). Welche Fassung zum Release gilt, wird bei der Freigabe von R1-19 erneut geprüft.
- **Rollenverteilung:** Course Captain ist Software, die Betreiber einsetzen. Ob eine Pflicht greift, entscheidet sich beim Betreiber (z. B. kostenpflichtige Online-Kurse an Verbraucher; Ausnahme für Kleinstunternehmen bei Dienstleistungen). Course Captain liefert dafür (a) eine konforme Oberfläche, (b) Werkzeuge, mit denen Autoren barrierefreie Inhalte erstellen, und (c) einen Konformitätsbericht je Release (E-125). Rechtstexte liefert Course Captain nicht (E-04).
- **Autorenwerkzeug:** Die Admin-Editoren (A-04, A-07, A-08, A-13) sind ein Autorenwerkzeug im Sinne von EN 301 549 Kap. 11.8. Daraus folgen: barrierefreie Inhalte müssen erstellbar sein (Alt-Text-Feld, Untertitelspur, Überschriftenstruktur aus dem Block-Editor), Barrierefreiheitsangaben bleiben beim Import/Export erhalten (Alt-Text, Untertitel, Sprache; R0-10) und der Publish-Validator (R0-22) bietet Reparaturhinweise (A11Y-110 ff.).
- **SOLL über AA hinaus:** `prefers-reduced-motion` respektieren (WCAG 2.3.3, AAA), Windows-Kontrastmodus (`forced-colors`) ohne Informationsverlust, Fokusindikator mindestens 2 px mit 3:1 (Richtwert aus 2.4.13, AAA).

### 1.2 Geltungsbereich

| Bereich | Umfang | Anmerkung |
|---|---|---|
| Lernenden-Screens | L-01…L-21 (R0-11 §2.3) einschließlich Zustände Z-L, Z-F, Z-G, Z-O, Z-W, Vorschaumodus und No-JS-Fallback (Konzept Kap. 10) | volle WCAG 2.2 AA |
| Admin-Screens | A-01…A-16 (Free), A-17/A-18 (Pro, nur mit Add-on), Setup-Assistent A-02 | volle WCAG 2.2 AA; WP-Core-Bausteine (Admin-Menü, `WP_List_Table`, Block-Editor) gelten als Fremdkomponente: eigene Erweiterungen darin MÜSSEN konform sein, Core-Mängel werden dokumentiert, nicht nachgebaut |
| Blocks/Shortcodes | Kurskatalog, „Nur mit Lernzugang“ usw. im aktiven Theme | Konform im Referenz-Theme (Twenty Twenty-Five, R0-20); fremde Themes nicht garantiert, aber gekapselte Styles dürfen Theme-Fokus/Kontrast nicht verschlechtern |
| E-Mails | alle Templates aus R1-21 (HTML + Klartext) | §2.3 A11Y-095 ff. |
| Druck/Nachweis | Zertifikat-HTML und Druckansicht (E-96), öffentliche Verifikation L-16 | Pro-PDF: D-15-09 |
| Nicht im Umfang | Inhalte der Autoren (Texte, Bilder, Videos) als solche, Theme, WooCommerce-Kasse, externe Player (YouTube/Vimeo), Zahlungsseiten | Werkzeuge und Warnungen ja (§2.3 A11Y-09x, A11Y-11x), Konformität des Inhalts verantwortet der Autor |

### 1.3 Komponentenregeln (MUSS, gelten für alle Screens)

| ID | Regel | WCAG | Herkunft |
|---|---|---|---|
| K-01 | Bei clientseitigem Seitenwechsel Fokus auf die neue `h1` (`tabindex=-1`), `document.title` = „Seitentitel – Kurs – Website“; Hülle **keine** Live-Region | 2.4.2, 2.4.3, 4.1.3 | LB-A11Y-01 |
| K-02 | Landmarks: genau ein `main`; Brotkrumen als `nav` mit Namen; Kursnavigation/Kapitelbaum als `nav` mit eigenem Namen; Sprunglink „Zum Inhalt“ als erstes fokussierbares Element im Lernbereich | 1.3.1, 2.4.1 | LB-A11Y-11 (Sprunglink neu) |
| K-03 | Überschriften lückenlos (`h1` je Screen, keine Ebene übersprungen); Kurskarten/Kacheln haben eine Überschrift, der Kartenlink trägt deren Text | 1.3.1, 2.4.6, 2.4.4 | neu |
| K-04 | Gesperrte Aktionen mit `aria-disabled="true"` + Begründung im Text oder `aria-describedby`; nie nur ausgegraut | 4.1.2, 1.4.1 | LB-A11Y-02, Z-G |
| K-05 | Statusmeldungen (gespeichert, Fehler beim Speichern, Ergebnis) über **eine** dauerhaft vorhandene Region: `role=status` (polite) für Erfolg/Fortschritt, `role=alert` nur für blockierende Fehler; keine Meldung doppelt | 4.1.3 | LB-A11Y-03 |
| K-06 | Nach Neuzeichnen einer Liste Fokus auf dasselbe Element (stabiler Schlüssel) oder dessen Nachbarn, nie auf `body` | 2.4.3 | INV-D-152 |
| K-07 | Composite-Widgets (Tabs, Fragennavigation, Kapitelbaum) nach ARIA APG: ein Tab-Stopp, Pfeiltasten innen, Home/End; Pfeilrichtung folgt `dir` (I18N-R-14); beliebige Anzahl Einträge | 2.1.1, 4.1.2 | LB-A11Y-04 |
| K-08 | Umschalter (Merken, Markieren) mit fester Beschriftung und `aria-pressed`; sichtbarer Text ist Anfang des zugänglichen Namens | 2.5.3, 4.1.2 | LB-A11Y-05 |
| K-09 | Dialoge: natives `<dialog>` mit `showModal()` oder gleichwertig; Fokus beim Öffnen auf erstes sinnvolles Element, Escape schließt (außer bei laufender Abgabe), Fokus kehrt zum Auslöser zurück; Fokusfalle nur innerhalb des offenen Dialogs | 2.1.2, 2.4.3 | LB-A11Y-09 |
| K-10 | Bewegung: keine Animation > 5 s ohne Pause; Übergänge und weiches Scrollen bei `prefers-reduced-motion: reduce` aus; nichts blinkt mehr als 3× pro Sekunde; Fortschrittsringe ohne Dauerschleife | 2.2.2, 2.3.1, (2.3.3) | LB-A11Y-06 |
| K-11 | Zielgröße interaktiver Elemente ≥ 24 × 24 CSS-px oder ausreichender Abstand; Antwortoptionen klickbar über die ganze Zeile (Label) | 2.5.8 | neu |
| K-12 | Drag-and-drop (Curriculum A-04, Sortierfragen ab R2) immer mit Alternative ohne Ziehen: „Nach oben/unten“-Knöpfe bzw. Positionsauswahl, Ergebnis als Statusmeldung | 2.5.7, 2.1.1 | Konzept 16.3 |
| K-13 | Fokus nie vollständig verdeckt durch fixen Timer, Admin-Bar, sticky Leisten oder Cookie-Banner (`scroll-margin`, `scroll-padding`) | 2.4.11 | LB-A11Y-10 |
| K-14 | Formulare: sichtbares `label` je Feld, Pflichtfeld im Text (nicht nur `*`), `autocomplete` bei Personendaten (Login, Registrierung, L-20), Fehler feldnah mit `aria-invalid` + `aria-describedby`, Fehlerzusammenfassung oben mit Links bei mehr als einem Fehler, Eingaben bleiben erhalten (Z-F) | 1.3.5, 3.3.1–3.3.3, 3.3.7 | INV-D-004/-005, Z-F |
| K-15 | Login/Einladungsannahme ohne kognitiven Test; Einfügen in Passwortfelder erlaubt; Passwortmanager nicht blockieren (eigene Formulare; WP-Login ist Core) | 3.3.8 | neu |
| K-16 | Hilfe-/Kontaktlink an gleicher relativer Stelle in allen Lernbereich-Screens, wenn der Betreiber einen hinterlegt | 3.2.6 | neu |
| K-17 | Rich-Text aus Autoreninhalt nur über die zentrale Sanitizer-Komponente; Überschriftenebenen im Inhalt werden relativ zur Screen-Struktur eingeordnet (Lektionsinhalt beginnt unter `h1`) | 1.3.1 | INV-D-154 |
| K-18 | Farbe nie einziger Träger: Status „gesperrt/fällig/abgeschlossen“, richtig/falsch, bestanden/nicht bestanden immer mit Text bzw. Symbol + Text | 1.4.1 | Konzept 16.3, Z-G |

---

## 2. Prüffälle

### 2.1 Screen-Typen

| Typ | Beschreibung | Screens (R0-11) |
|---|---|---|
| **ST-1** Navigation/Listen | Kacheln, Listen, Hubs, Dashboards | L-01, L-04, L-05, L-07, L-09, L-12, L-18, L-19, A-01, A-03, A-05, A-09, A-10, A-12, A-16 |
| **ST-2** Inhalt/Medien | Lektion, Video/Audio, Datei, Karteikarte | L-06, L-11 |
| **ST-3** Frageformular (ungetaktet) | Lernmodus, Fehlertraining, Inline-Fragen | L-08, L-10, Inline-Fragen in L-06 |
| **ST-4** Prüfungslauf (getaktet) | Timer, Navigation, Markieren, Abgabe | L-13 |
| **ST-5** Ergebnis/Nachweis | Ergebnis, Review, Zertifikat, Verifikation | L-14, L-15, L-16 |
| **ST-6** Formular/Assistent | Einstellungen, Setup, Konto, Einladung, Lizenz | A-02, A-15, A-17, L-03, L-17, L-20, L-21 |
| **ST-7** Editor | Kurs-, Fragen-, Prüfungseditor (inkl. DnD, Autosave) | A-04, A-07, A-08, A-18 |
| **ST-8** Tabellen/Batch | Import, Medien, Zertifikatsliste, Integrationen, Protokoll | A-06, A-11, A-13, A-14, A-16 (Protokoll) |
| **ST-9** Nicht-Screen | E-Mails, Druckansicht | R1-21-Templates, Druck L-15 |

### 2.2 Testfälle

Werkzeuge: **AXE** = axe-core über `@axe-core/playwright`, Tags `wcag2a, wcag2aa, wcag21a, wcag21aa, wcag22aa`; **PW** = Playwright-Skript mit Tastatur (`page.keyboard`), Rollen-Locatoren und Zustandsprüfung; **EMU** = Playwright-Emulation (`emulateMedia` für `reducedMotion`/`forcedColors`, Viewport, CSS-Injektion); **NVDA** = NVDA + Firefox und NVDA + Chrome (Windows); **VO** = VoiceOver + Safari (macOS) und VoiceOver + Safari (iOS); **TB** = TalkBack + Chrome (Android, best effort); **MAN** = manuelle Sichtprüfung nach Checkliste. Ziel-Task: **R1-19** = automatisiert/entwicklerseitig nachgewiesen, **R1-29** = manuell mit Hilfstechnik bzw. Nutzertest (Protokoll nach R0-26).

#### Tastatur

| ID | Prüfung (Erwartung) | WCAG | Typen | Werkzeug | Ziel |
|---|---|---|---|---|---|
| A11Y-001 | Jede Funktion des Screens ist nur mit Tab/Shift+Tab/Enter/Leertaste/Pfeilen erreichbar und auslösbar (Referenzflows 1 und 2 aus R0-11 komplett ohne Maus) | 2.1.1 | alle | PW, MAN | R1-19 |
| A11Y-002 | Fokusreihenfolge folgt der visuellen Lesereihenfolge; Sprunglink → Brotkrumen → `h1` → Inhalt → Aktionen; Seitenleiste A-04 nach Hauptbereich oder als eigener Landmark erreichbar | 2.4.3, 1.3.2 | alle | PW (Tab-Sequenz gegen erwartete Liste), MAN | R1-19 |
| A11Y-003 | Fokus ist immer sichtbar (≥ 2 px, Kontrast ≥ 3:1 zu angrenzenden Farben), auch in `forced-colors` | 2.4.7, 1.4.11 | alle | EMU (Screenshot-Vergleich), MAN | R1-19 |
| A11Y-004 | Keine Fokusfalle: aus jedem Widget, eingebettetem Player (iframe), Editor (Block-Editor, Code-Feld) und Dialog kommt man per Tastatur heraus; Dialog-Falle endet mit Schließen | 2.1.2 | ST-2, ST-6, ST-7 | PW, MAN | R1-19 |
| A11Y-005 | Shortcuts: keine Einzeltasten-Kürzel ohne Modifikator; falls im Lernmodus/Prüfung Ziffern für Antworten angeboten werden (KANN), nur bei Fokus in der Frage aktiv **und** in L-20 abschaltbar | 2.1.4 | ST-3, ST-4 | PW | R1-19 |
| A11Y-006 | Fokussiertes Element wird nicht vom fixen Timer, sticky Antwortleiste, Admin-Bar oder Consent-Banner verdeckt (390×844, 320×640, Desktop) | 2.4.11 | ST-3, ST-4 | PW (BoundingBox-Überlappung), MAN | R1-19 |
| A11Y-007 | Fragennavigation L-13: ein Tab-Stopp, Pfeile wechseln Position, Enter öffnet; Status je Position (beantwortet/markiert/offen) im Namen, nicht nur Farbe | 2.1.1, 1.4.1, 4.1.2 | ST-4 | PW, NVDA | R1-19, R1-29 |
| A11Y-008 | Curriculum A-04 und Fragenreihenfolge: Umsortieren ohne Ziehen (Knöpfe/Menü), Ergebnis angesagt, Fokus bleibt am verschobenen Element | 2.5.7, 4.1.3 | ST-7 | PW, NVDA | R1-19, R1-29 |
| A11Y-009 | Ungespeicherte-Änderungen-Warnung (A-15) und Abgabebestätigung (L-13) sind per Tastatur bedienbar; Escape bricht ab, ohne Daten zu verlieren | 2.1.1, 3.3.4 | ST-4, ST-6 | PW | R1-19 |
| A11Y-010 | No-JS-Fallback (Konzept Kap. 10, INV-D-004): Bestätigungsschritte und Kernnavigation per Tastatur ohne JavaScript | 2.1.1 | ST-1, ST-6 | PW (`javaScriptEnabled: false`) | R1-19 |

#### Screenreader und Semantik

| ID | Prüfung (Erwartung) | WCAG | Typen | Werkzeug | Ziel |
|---|---|---|---|---|---|
| A11Y-020 | Landmarks und Sprunglink nach K-02; Landmark-Liste in NVDA/VO zeigt `main`, benannte `nav`; keine doppelten unbenannten Landmarks | 1.3.1, 2.4.1 | alle | AXE (`landmark-*`, `region`), NVDA, VO | R1-19, R1-29 |
| A11Y-021 | Seitenwechsel in der SPA: neue `h1` wird angesagt, Titel aktualisiert, keine Doppelansage des gesamten Inhalts | 2.4.2, 4.1.3 | alle Lernbereich | PW (Fokus = `h1`), NVDA, VO | R1-19, R1-29 |
| A11Y-022 | **Frageformular:** jede Frage ist eine `fieldset`; die `legend` enthält Fragenummer und Fragetext (bzw. ist per `aria-labelledby` mit dem Fragetext verbunden) und den Hinweis „eine/mehrere Antworten“; Single-Choice als Radiogruppe, Multi als Checkboxen, Wahr/Falsch als Radiogruppe; Medien der Frage stehen vor den Antworten und haben Alt-Text | 1.3.1, 3.3.2, 4.1.2 | ST-3, ST-4 | AXE, PW (`getByRole('group', { name: /Frage 3/ })`), NVDA, VO | R1-19, R1-29 |
| A11Y-023 | Offene Textfrage/Karteikarte: „Aufdecken“ ist ein Knopf mit `aria-expanded`/Statusansage; Selbsteinschätzung als Radiogruppe mit `legend` | 1.3.1, 4.1.2 | ST-2, ST-3 | PW, NVDA | R1-19 |
| A11Y-024 | **Feedback-Live-Region:** nach „Antwort prüfen“ wird „Richtig“/„Falsch – richtig wäre …“ einmal polite angesagt (nur wenn Feedback-Sichtbarkeit es erlaubt, E-20); Markierung richtig/falsch an den Optionen mit Text bzw. `aria-describedby`, nicht nur Farbe; Fokus bleibt auf dem Prüfen-Knopf oder springt auf die Rückmeldung (eine Variante festlegen, nicht mischen) | 4.1.3, 1.4.1 | ST-3 | PW (Region-Inhalt), NVDA, VO | R1-19, R1-29 |
| A11Y-025 | **Speicherstatus Prüfung:** „Gespeichert“/„Wird gespeichert“/„Offline – wird nachgesendet“ (Z-O) polite und gedrosselt (höchstens eine Ansage je Antwort, keine Ansage bei jedem Tastendruck in Textfeldern) | 4.1.3 | ST-4 | PW, NVDA | R1-19, R1-29 |
| A11Y-026 | Fortschrittsanzeigen (Kurs, Smarttrainer-Ringe, Prüfungsbalken) mit Text („7 von 20 Fragen beantwortet“) bzw. `role=progressbar` + `aria-valuetext`; SVG-Ringe `aria-hidden` mit Textalternative daneben | 1.1.1, 1.3.1 | ST-1, ST-4 | AXE, NVDA | R1-19 |
| A11Y-027 | Zustände Z-G (gesperrt + Grund), Z-L (leer + nächste Aktion), Z-F (Fehler) sind als Text vorhanden und werden beim Erreichen der Kachel/Seite vorgelesen | 1.3.1, 1.4.1 | ST-1 | NVDA, VO | R1-29 |
| A11Y-028 | Tabellen (A-09, A-12, A-13, A-16, L-14 Themenanalyse): `th` mit `scope`, Beschriftung (`caption`), Sortierstatus `aria-sort`; mobile Scroll-Container fokussierbar und benannt (INV-D-005) | 1.3.1, 2.1.1 | ST-1, ST-5, ST-8 | AXE, NVDA | R1-19 |
| A11Y-029 | Diagramme (Verlaufskurve L-12, Berichte A-12): Daten zusätzlich als Tabelle oder Textzusammenfassung | 1.1.1 | ST-1, ST-5 | MAN, NVDA | R1-19 |
| A11Y-030 | Ergebnis L-14: bestanden/nicht bestanden, Punkte, Teilgrenzen als Überschrift + Text; „verkürzte Prüfung“ (F2.9) und „Auswertung verzögert“ (F2.10) klar formuliert | 1.3.1, 1.4.1 | ST-5 | NVDA, VO | R1-29 |
| A11Y-031 | Admin-Editoren: Autosave-Status, Revisionswarnung („veröffentlichte Revision“), Publish-Blocker (R0-22) als Statusmeldung bzw. Fehlerzusammenfassung; Meta-Boxen benannt | 4.1.3, 3.3.1 | ST-7 | NVDA | R1-19, R1-29 |
| A11Y-032 | Sprache: `lang` der Seite = UI-Sprache; Inhaltscontainer mit `lang` (und `dir`) der Kurs-/Fragensprache, wenn abweichend (§4) | 3.1.1, 3.1.2 | ST-2, ST-3, ST-4 | AXE (`html-has-lang`, `valid-lang`), PW | R1-19 |

#### Kontrast und Farbe

| ID | Prüfung (Erwartung) | WCAG | Typen | Werkzeug | Ziel |
|---|---|---|---|---|---|
| A11Y-040 | Text ≥ 4,5:1 (groß ≥ 3:1) in allen Zuständen inkl. Hover, Fokus, gesperrt-aber-lesbar (LB-Befund: gesperrte Bögen zu schwach, LB-A11Y-13) | 1.4.3 | alle | AXE (`color-contrast`), MAN für Verläufe/Bilder | R1-19 |
| A11Y-041 | Bedienelemente, Fokusrahmen, Formularränder, Fortschrittsbalken, Diagrammlinien ≥ 3:1 | 1.4.11 | alle | MAN mit Kontrastprüfer, EMU-Screenshots | R1-19 |
| A11Y-042 | Scoped Design-Tokens (Kap. 16.2 „Kursdarstellung“): Speichern einer Akzentfarbe mit Kontrast < 4,5:1 zu Text/Hintergrund wird mit feldnahem Hinweis abgelehnt oder mit konformem Vorschlag beantwortet (Verhalten D-15-07) | 1.4.3 | ST-6 | PHPUnit (Kontrastfunktion), PW | R1-19 |
| A11Y-043 | `forced-colors: active`: Status, Fokus, Auswahl der Antwortoption, Timer bleiben erkennbar (System-Farben, keine reinen Hintergrundbilder) | (1.4.11) SOLL | ST-3, ST-4 | EMU | R1-19 |
| A11Y-044 | Dunkles Theme bzw. Theme-Farben: Kontrast mit dem Referenz-Theme in Hell und (falls vorhanden) Dunkel geprüft | 1.4.3 | alle Lernbereich | AXE in beiden Schemata | R1-19 |

#### Zoom, Reflow, Textabstand, Ausrichtung

| ID | Prüfung (Erwartung) | WCAG | Typen | Werkzeug | Ziel |
|---|---|---|---|---|---|
| A11Y-050 | Reflow: Viewport 320 × 640 CSS-px (entspricht 1280 px bei 400 %) ohne waagerechtes Scrollen der Seite (Ausnahme: Datentabellen in eigenem Scroll-Container); kein Inhalt abgeschnitten | 1.4.10 | alle | PW (`scrollWidth`, Muster LB-A11Y-12), MAN bei 400 % Browserzoom | R1-19 |
| A11Y-051 | Text auf 200 % (Browser-Textzoom, Firefox „Nur Text zoomen“) ohne Überlappung und Verlust | 1.4.4 | alle | MAN | R1-29 |
| A11Y-052 | Textabstand: CSS-Injektion `line-height:1.5; letter-spacing:.12em; word-spacing:.16em; p{margin-bottom:2em}` ohne abgeschnittenen Text (Knöpfe, Badges, Timer) | 1.4.12 | alle | EMU + Screenshot-Diff, MAN | R1-19 |
| A11Y-053 | Prüfungslauf bei 400 %: Timer, aktuelle Frage, Navigation und Abgabe bleiben erreichbar; fixer Timer verdeckt nicht mehr als ein Viertel der Höhe (sonst wird er statisch) | 1.4.10, 2.4.11 | ST-4 | PW 320 × 256, MAN | R1-19 |
| A11Y-054 | Hoch- und Querformat auf Mobilgeräten, keine Sperre der Ausrichtung | 1.3.4 | alle Lernbereich | PW (Viewport-Wechsel), MAN auf Gerät | R1-29 |
| A11Y-055 | Tooltips/Popover (Hilfetexte A-15, Info-Icons) per Fokus erreichbar, mit Escape schließbar, bleiben beim Überfahren sichtbar | 1.4.13 | ST-6, ST-7 | PW | R1-19 |

#### Bewegung

| ID | Prüfung (Erwartung) | WCAG | Typen | Werkzeug | Ziel |
|---|---|---|---|---|---|
| A11Y-060 | Mit `reducedMotion: 'reduce'`: keine Übergangsanimationen, kein weiches Scrollen, Karteikarte dreht ohne Animation | (2.3.3) SOLL | ST-1…ST-4 | EMU + `getAnimations()` leer | R1-19 |
| A11Y-061 | Automatisch bewegte/aktualisierte Inhalte (Karussell, Live-Zähler im Dashboard) haben Pause/Stopp oder laufen < 5 s | 2.2.2 | ST-1 | PW, MAN | R1-19 |
| A11Y-062 | Kein Flackern > 3 Hz (Erfolgsanimation bei „bestanden“, Fortschritts-Konfetti nicht vorgesehen) | 2.3.1 | ST-5 | MAN | R1-19 |
| A11Y-063 | Video/Audio startet nie automatisch mit Ton; Player-Bedienelemente per Tastatur und benannt (eigener Player bzw. Mindestanforderung an Einbettung) | 1.4.2, 2.1.1 | ST-2 | PW, NVDA | R1-19, R1-29 |

#### Zeitlimits (Details §2.3)

| ID | Prüfung (Erwartung) | WCAG | Typen | Werkzeug | Ziel |
|---|---|---|---|---|---|
| A11Y-070 | Prüfung mit Zeitausgleich (Faktor 1,5) startet mit `deadline_at = started_at + limit × 1,5`; der Faktor steht im Snapshot und ändert sich bei späterer Änderung der Zuordnung nicht | 2.2.1 | ST-4 | PHPUnit/Integration, PW | R1-19 (mit R1-20) |
| A11Y-071 | Timer: `role=timer`, `aria-live=off`; Ansage per polite-Region bei 10 min, 5 min, 1 min Restzeit (Schwellen ≤ Limit), keine Sekundenansage; Knopf „Restzeit ansagen“; sichtbare Restzeit ausblendbar, Ansagen bleiben | 2.2.1, 4.1.3 | ST-4 | PW (Fake-Clock), NVDA, VO | R1-19, R1-29 |
| A11Y-072 | Übungsprüfung (`timer_mode = practice`): Lernender kann Timer vor dem Start abschalten oder ab der 1-min-Warnung mit einer Aktion um mindestens 20 s verlängern (beliebig oft), Ergebnis als „ohne Zeitlimit“ gekennzeichnet | 2.2.1 | ST-4 | PW | R1-19 |
| A11Y-073 | Ablauf: „Die Zeit ist abgelaufen. Deine Antworten wurden abgegeben.“ als `alert`, Fokus auf `h1` des Ergebnisses (E-75) | 4.1.3, 3.3.4 | ST-4, ST-5 | PW, NVDA | R1-19 |
| A11Y-074 | Abgabe mit Bestätigung, die offene und markierte Fragen nennt; Abbrechen möglich | 3.3.4 | ST-4 | PW | R1-19 |
| A11Y-075 | Sitzungs-/Nonce-Ablauf während Lernen oder Prüfung führt nicht zu Datenverlust: Nonce-Erneuerung (INV-D-069), Antworten bleiben gepuffert, nach erneutem Login Rückkehr zur Position | 2.2.1 | ST-3, ST-4, ST-6 | PW (Nonce invalidieren) | R1-19 (mit R1-20) |
| A11Y-076 | Auto-Abgabe nach Inaktivität (E-72, falls aktiviert): Start-Hinweis nennt die Frist; Erinnerung im Prüfungs-Hub L-12 | 2.2.1 (2.2.6 SOLL) | ST-1, ST-4 | PW | R1-19 |
| A11Y-077 | Keine zeitgesteuert verschwindenden Meldungen < 20 s ohne Verlängerung; Flash-Meldungen bleiben bis zur nächsten Aktion | 2.2.1 | alle | PW | R1-19 |

#### Fehlermeldungen und Formulare

| ID | Prüfung (Erwartung) | WCAG | Typen | Werkzeug | Ziel |
|---|---|---|---|---|---|
| A11Y-080 | Feldfehler nach K-14: Text nennt Feld und Abhilfe („Bestehensgrenze muss zwischen 1 und 100 % liegen“), `aria-invalid`, `aria-describedby`; Fokus nach Absenden auf Fehlerzusammenfassung bzw. erstes fehlerhaftes Feld | 3.3.1, 3.3.3 | ST-6, ST-7 | AXE, PW, NVDA | R1-19 |
| A11Y-081 | Serverfehler (Z-F, stabile Codes R0-19) als verständlicher Text mit Wiederholen-Aktion; kein Rohcode als einzige Information; Text kommt aus der Übersetzung, der Client wertet nur den Code aus (I18N-R-08) | 3.3.1 | alle | PW (Fehler injizieren) | R1-19 |
| A11Y-082 | Rechte fehlen (Z-R) und Wartung (Z-W) als eigene Seite mit `h1` und Weg zurück | 3.3.1, 2.4.2 | alle | PW | R1-19 |
| A11Y-083 | Setup-Assistent A-02: Schrittanzeige als Liste mit `aria-current="step"`; bereits eingegebene Werte werden beim Zurückgehen/erneuten Aufruf vorbelegt | 1.3.1, 3.3.7 | ST-6 | PW, NVDA | R1-19 |
| A11Y-084 | Hochrisikoaktionen (Löschen, Widerruf, Uninstall mit Site-Name E-89): Bestätigung mit klarer Folge, Eingabefeld beschriftet | 3.3.4, 3.3.2 | ST-6, ST-8 | PW | R1-19 |
| A11Y-085 | Import-Dry-run A-13: Warnungen/Fehler je Zeile als Tabelle mit Filter, Zählung als Statusmeldung | 1.3.1, 4.1.3 | ST-8 | AXE, NVDA | R1-29 |

#### Medien, E-Mail, Druck

| ID | Prüfung (Erwartung) | WCAG | Typen | Werkzeug | Ziel |
|---|---|---|---|---|---|
| A11Y-090 | Player unterstützt WebVTT-Untertitel (Spur auswählbar, Standard nach UI-Sprache, wenn vorhanden) und Transkript-Link; Medien ohne Untertitel → Publish-Warnung (A11Y-111) | 1.2.2, 1.2.3 | ST-2 | PW, MAN | R1-19 |
| A11Y-091 | Bilder in Fragen/Lektionen: `alt` aus Medienobjekt; dekorativ nur mit `decorative: true` (R0-10) → `alt=""` | 1.1.1 | ST-2, ST-3 | AXE (`image-alt`) | R1-19 |
| A11Y-092 | Downloads (PDF): Dateityp und Größe im Linktext; Hinweis, wenn das PDF nicht als barrierefrei markiert ist (Autorenfeld) | 2.4.4 | ST-2 | AXE, MAN | R1-19 |
| A11Y-093 | Consent-Platzhalter externer Medien, nur im Modus `external_media_mode` = „Klick zum Laden“ (Default ist „immer laden“, E-45a): Knopf „Video von … laden“ mit Tastatur, Fokus nach Laden auf den Player | 2.1.1, 4.1.2 | ST-2 | PW | R1-19 |
| A11Y-095 | E-Mails: HTML mit `lang`, semantischen Überschriften, Tabellen nur mit `role="presentation"`, Linktexte aussagekräftig, Kontrast 4,5:1; gleichwertige Klartext-Version | 1.3.1, 2.4.4, 3.1.1 | ST-9 | Mail-Sink + HTML-Lint (axe auf gerendertes HTML), MAN in einem Mailprogramm | R1-19 |
| A11Y-096 | Druckansicht Nachweis: Lesereihenfolge, Überschrift, Text statt Bild; Verifikationslink als Text | 1.3.2, 1.4.5 | ST-9, ST-5 | AXE, MAN | R1-19 |

#### Automatisierte Abdeckung, manuelle Durchläufe, Autorenwerkzeug

| ID | Prüfung (Erwartung) | Werkzeug | Ziel |
|---|---|---|---|
| A11Y-100 | axe-Lauf je Screen L-01…L-21, A-01…A-16 in allen Zuständen aus R0-11 (Z-L, Z-F, Z-G, gefüllt), Viewports 1280×900 und 390×844, Hell (+Dunkel); Gate nach D-15-05 | AXE in CI (Chromium); wöchentlich zusätzlich Firefox/WebKit | R1-19 |
| A11Y-101 | Tastatur-Referenzlauf Flow 1 (Autor) und Flow 2 (Lernender) in Chromium, Firefox, WebKit (E-61); WebKit-Besonderheit „Tab erreicht Links“ berücksichtigt | PW | R1-19 |
| A11Y-102 | Manueller Screenreader-Durchlauf Flow 2 inkl. Prüfung mit Zeitausgleich: NVDA+Firefox, NVDA+Chrome, VO macOS Safari, VO iOS Safari; TalkBack best effort | NVDA, VO, TB | R1-29 |
| A11Y-103 | Manueller Durchlauf Flow 1 und Flow 4 (Setup) mit NVDA+Chrome und VO macOS | NVDA, VO | R1-29 |
| A11Y-104 | Nutzertest mit mindestens einer Person, die Hilfstechnik im Alltag nutzt (Details R0-26) | MAN | R1-29 |
| A11Y-110 | Publish-Validator (R0-22): Bild ohne Alt-Text und ohne `decorative` → Blocker; Fehler nennt Objekt und Link zum Feld (Reparaturhilfe, EN 301 549 11.8.4) | PHPUnit, PW | R1-19 |
| A11Y-111 | Publish-Validator: Video ohne Untertitelspur, Lektion mit übersprungener Überschriftenebene, Link „hier klicken“ → Warnung, nicht Blocker (E-131); fehlende Untertitel per Site-Einstellung als Blocker konfigurierbar | PHPUnit, PW | R1-19 |
| A11Y-112 | Import/Export erhält Alt-Text, `decorative`, Untertitel, Sprachkennzeichen verlustfrei (Roundtrip) | PHPUnit (Golden Master R0-05) | R1-19 |

### 2.3 Zeitlimits in Prüfungen und Nachteilsausgleich

**Problem:** WCAG 2.2.1 verlangt für jedes Zeitlimit eine der Optionen Abschalten, Anpassen (mindestens auf das Zehnfache) oder Verlängern (Warnung ≥ 20 s vorher, einfache Aktion, mindestens zehnmal). Ausnahme „essenziell“: wenn eine Verlängerung die Aktivität ungültig machen würde. Eine bewertete Prüfung mit Zeitlimit kann sich auf diese Ausnahme berufen; frei verlängerbare Zeit würde die Prüfungsintegrität (R0-08 §4.3, R1-20) aufheben. Gleichzeitig brauchen Lernende mit Behinderung in der Praxis einen **Nachteilsausgleich** (Zeitverlängerung), den die prüfende Stelle gewährt, nicht der Lernende selbst.

**Lösung (beschlossen, E-122/E-123):**

1. **Ungetaktete Aktivitäten** (Lernmodus, Smarttrainer, unbewertete Quizze, Karteikarten) haben **nie** ein Zeitlimit (MUSS). Bewertete Quizze ohne `time_limit_seconds` ebenso.
2. **Timer-Modus je Prüfungsregel** `timer_mode` (eingefroren im Snapshot):
   - `practice`: Lernender kann vor dem Start „ohne Zeitlimit üben“ wählen oder während des Laufs verlängern (A11Y-072). Ergebnis gekennzeichnet „ohne Zeitlimit“, zählt nicht für Nachweise. Erfüllt 2.2.1 direkt.
   - `strict` (Standard für nachweisfähige Prüfungen): kein Selbst-Verlängern. Die Ausnahme „essenziell“ wird in der Regel dokumentiert (Autorenhinweis im Prüfungseditor A-08). **Pflichtausgleich:** Jede `strict`-Prüfung MUSS einen Zeitausgleich je Lernendem zulassen; ein Abschalten des Ausgleichs pro Regel ist nicht vorgesehen.
3. **Zeitausgleich (Accommodation)** als eigene Zuordnung, nicht als Prüfungsregel:
   - Datensatz (Delta zu R0-06; Präfix nach E-207 `ccap_`): `ccap_accommodation (user_id, scope_type 'site'|'course'|'exam_rule', scope_id, time_factor_pct smallint 100–400 | NULL, unlimited tinyint, valid_until datetime NULL, created_by, created_at)`; **keine Freitext-Begründung, keine Diagnose** (Datenminimierung: ein Nachteilsausgleich lässt auf Gesundheitsdaten schließen, Art. 9 DSGVO; Bewertung in R0-17).
   - Setzen/Ändern nur mit eigener Capability `ccap_manage_accommodations` (Präfix nach E-207 `ccap_`; Default nur `administrator` und Kursmanager; Delta zu R0-07), jede Änderung mit Audit-Eintrag ohne Inhaltsdetails außer Faktor.
   - Wirkung: beim Start `deadline_at = started_at + time_limit_seconds × time_factor_pct / 100` (bzw. ohne Frist bei `unlimited`); Faktor, Quelle und Wert stehen im Snapshot (Muster R0-08 §6.3). Grace (5 s) und Kulanz bleiben unverändert. Laufende Versuche ändern sich durch spätere Zuordnungen nicht; ein Admin KANN einem **laufenden** Versuch einmalig Zeit hinzufügen (Aktion mit Audit, R1-20-Negativtest: nur durch Berechtigte, nur `in_progress`).
   - Sichtbarkeit: Lernender sieht vor dem Start „Deine Bearbeitungszeit: 45 Minuten (inkl. Zeitausgleich)“. Ergebnis, Bericht A-12 und Nachweis zeigen den Ausgleich **nicht** (E-122); in A-09 nur für Berechtigte.
   - Mehrere Zuordnungen: die spezifischste gilt (Regel > Kurs > Site).
4. **Ansagen und Anzeige** nach A11Y-071; Server ist maßgeblich (R0-08 §4.3, `remaining_seconds`), der Client-Timer ist nur Anzeige.
5. **Keine Pause in R1** für `strict` (Integrität); `practice` KANN pausieren. Pausen für `strict` als Ausgleichsart sind ein späterer Kandidat (Datenvertrag offen lassen: `accommodation_type` reservieren).

### 2.4 Werkzeug- und Matrixregeln

- axe-core MUSS als Projektabhängigkeit mit fester Version laufen (LB nutzte 4.10 nur ad hoc, LB-A11Y-13). Ausnahmen in einer versionierten Liste mit Begründung, Screen, Ablaufdatum und Ticket.
- Browser-Achse nach E-61/R0-20 (eigenständige Matrix, E-12): axe in Chromium je Commit; Tastatur-Referenzläufe (A11Y-101) in Chromium, Firefox, WebKit; mobile Viewports 390×844 (WebKit) und 360×800 (Chromium-Android) plus 320×640 für Reflow.
- Screenreader-Matrix (E-126): NVDA aktuell + Firefox ESR und Chrome; VoiceOver macOS + Safari; VoiceOver iOS + Safari; TalkBack + Chrome best effort; JAWS nicht zugesichert.
- Automatisch findet axe nur einen Teil der Barrieren (LB-Doku: „etwa ein Drittel“). Manuelle Fälle (MAN/NVDA/VO) sind deshalb Pflicht für R1-29 und werden mit Datum, Version von Browser/Hilfstechnik, Befund und Schweregrad protokolliert (Format R0-26).

---

## 3. Internationalisierung

### 3.1 Textdomains und Laden

| Paket | Textdomain | Vorlage | Header |
|---|---|---|---|
| Course Captain (Free) | `course-captain` | `languages/course-captain.pot` | `Text Domain: course-captain`, `Domain Path: /languages` |
| Course Captain Pro | `course-captain-pro` | `languages/course-captain-pro.pot` | dto. |

- **Quellsprache** der Strings: **Englisch (en_US)** (E-121). Grund: Übersetzung über translate.wordpress.org (Free auf WordPress.org, E-09) setzt englische Quelltexte voraus; Deutsch wird erste vollständige Übersetzung (`de_DE`, `de_DE_formal`, KANN `de_AT`/`de_CH`); Auslieferung als Sprachpaket über translate.wordpress.org, bis zur Freigabe zusätzlich im ZIP (E-208). LB nutzt Deutsch als Quelle (`docs/uebersetzung.md`).
- Laden: Für WordPress.org-Plugins lädt WP Übersetzungen just-in-time; `load_plugin_textdomain()` bleibt nur für mitgelieferte Dateien nötig (Pro). **MUSS:** Kein Übersetzungsaufruf vor dem Hook `init` (sonst lädt WP ab 6.7 zu früh und meldet `_load_textdomain_just_in_time`); Labels für CPT/Rollen/Settings-Register entstehen in oder nach `init`.
- Übersetzungen unter `wp-content/languages/plugins/` haben Vorrang (WP-Standard, wie LB).

### 3.2 Regeln für übersetzbare Texte (I18N-R)

| ID | Regel | Beleg/Werkzeug |
|---|---|---|
| I18N-R-01 | Domain immer als Literal, keine Variablen/Konstanten | LB-I18N-01 (Verhalten von `check-domains.php`, neu geschrieben nach E-01), WPCS `WordPress.WP.I18n` |
| I18N-R-02 | Text immer als Literal; keine Variablen im ersten Argument; keine dynamisch gebauten Schlüssel | WPCS `NonSingularStringLiteralText`; ESLint `@wordpress/i18n-no-variables` |
| I18N-R-03 | **Keine zusammengesetzten Sätze:** ganze Sätze mit Platzhaltern (`sprintf( __( 'Module %1$s unlocks on %2$s.', … ), $name, $date )`), nummerierte Platzhalter ab zwei Werten, kein Verketten übersetzter Fragmente, keine Satzteile in Variablen | Code-Review; Lint „Verkettung mit `.` direkt an `__()`“ (neues Prüfskript, §3.8) |
| I18N-R-04 | Mengen nur mit `_n()`/`_nx()` (PHP) bzw. `_n` aus `@wordpress/i18n` (JS); **nie** `*_one`-Schlüsselpaare (LB-I18N-03) und nie `n === 1 ? … : …` | WPCS, ESLint, Review |
| I18N-R-05 | Mehrdeutige Kurzwörter mit Kontext `_x()` (z. B. „Open“ als Status vs. Aktion, „Draft“, „Due“, „Lock“, „Pass“); Enum-Label-Register (INV-D-006) über `_x()`/`_n_noop()` mit Kontext je Enum | Review; Register-Unit-Test: jeder Enum-Wert hat Label |
| I18N-R-06 | Übersetzerkommentar `/* translators: */` bei jedem Platzhalter und bei Abkürzungen | WPCS `MissingTranslatorsComment`; ESLint `i18n-translator-comments` |
| I18N-R-07 | Escaping-Varianten verwenden (`esc_html__`, `esc_attr__`); HTML im String nur minimal (Links über Platzhalter, `wp_kses` mit erlaubten Tags) | WPCS |
| I18N-R-08 | Stabile Codes (Fehler, Status, Events, REST-Enums) werden **nie** übersetzt und nie aus Text rückgeschlossen; Anzeige-Text kommt getrennt dazu | LB-I18N-10, E-06, R0-19 |
| I18N-R-09 | Autoreninhalte (Kurstitel, Fragen, Mailtexte, die der Betreiber pflegt) laufen **nicht** durch `__()`; Mehrsprachigkeit von Inhalten → §4 / R0-25 | Konzept Kap. 21 |
| I18N-R-10 | Alle JS-Handles mit Texten: `wp_set_script_translations( $handle, 'course-captain', plugin_dir_path( __FILE__ ) . 'languages' )`; JSON per `wp i18n make-json --no-purge`; Bundles so bauen, dass der Quellpfad der Strings zum ausgelieferten Skript passt (sonst findet WP die JSON-Datei nicht; ggf. `make-json --use-map`) | LB-I18N-05; E2E-Test I18N-012 |
| I18N-R-11 | Keine Textmanipulation nach Übersetzung (kein `strtoupper`, `ucfirst`, Kürzen per Zeichenzahl ohne `mb_*`/`wp_trim_words`); Großschreibung per CSS; Sortierung übersetzter Labels mit `Collator` (Extension `intl`, LB-CI setzt sie voraus) mit Rückfall | Review |
| I18N-R-12 | **RTL:** CSS nur mit logischen Eigenschaften (`margin-inline-start`, `inset-inline-end`, `text-align: start`); falls eine RTL-Datei nötig ist, über `wp_style_add_data( $handle, 'rtl', 'replace' )`; Lint verbietet physische `left/right` außer mit Kommentar-Ausnahme | LB-I18N-09; Stylelint `liberty/use-logical-spec` o. ä. |
| I18N-R-13 | Richtungsabhängige Icons (Pfeile „Weiter/Zurück“, Fortsetzen, Brotkrumen-Trenner) spiegeln in RTL; Fortschrittsbalken füllen von `start`; Zahlen, Uhrzeiten und Codes bleiben LTR (`dir="ltr"`/`<bdi>`) | MAN, Screenshot RTL |
| I18N-R-14 | Tastatur in Composite-Widgets (K-07): in RTL bedeutet ArrowLeft „nächstes“ | PW in RTL-Locale |
| I18N-R-15 | Nutzer- und Autorentexte in übersetzten Sätzen in `<bdi>` bzw. mit `dir="auto"` einbetten (Namen, Kurstitel in anderer Schriftrichtung) | PW |
| I18N-R-16 | Layout hält 40 % längere Texte aus (Knöpfe, Tabs, Badges umbrechen statt abschneiden) | Pseudo-Locale (§3.8) |

### 3.3 Datum, Uhrzeit, Zeitzonen

| ID | Regel |
|---|---|
| I18N-R-20 | **Speichern:** alle `*_at` in UTC, von PHP gesetzt (`gmdate`/`time()`), keine DB-Defaults (R0-06 §7, LB-I18N-07). Fristen (Drip-Datum, Zugangsende, Prüfungsfrist) ebenfalls UTC. |
| I18N-R-21 | **Anzeige (PHP):** `wp_date( get_option( 'date_format' ), $ts )` bzw. `date_format`/`time_format` der Website; nutzt `wp_timezone()` und die aktuelle Locale. **Nie** feste Formatstrings wie `'d.m.Y H:i'` (LB-I18N-06). `date_i18n()` nur, wo eine bestehende API es verlangt; neue Aufrufe nutzen `wp_date()` (seit WP 5.3, zeitzonenkorrekt). Ausnahmen: ISO 8601 in CSV/Export/Logs. |
| I18N-R-22 | **REST/JS:** Zeitpunkte als ISO 8601 mit `Z` (UTC). Der Client formatiert mit `Intl.DateTimeFormat(locale, { timeZone })`; `locale` aus der Boot-Konfiguration (LB-I18N-04, BCP 47), `timeZone` = `wp_timezone_string()` (IANA). Ist die WP-Zeitzone nur ein Offset (`+02:00`), sendet der Server zusätzlich formatierte Strings oder der Client nutzt `Etc/GMT±n` (Vorzeichen beachten). Relative Angaben („in 3 Tagen“) mit `Intl.RelativeTimeFormat`. |
| I18N-R-23 | **Zeitzone der Anzeige:** Website-Zeitzone (WP kennt keine Nutzerzeitzone). Fristen werden mit Zeitzonenkennung angezeigt („bis 12. Dez. 2026, 18:00 MEZ“), damit Lernende in anderen Zonen nicht falsch rechnen (D-15-04). Systemstatus A-16 warnt bei Offset-Zeitzone ohne Sommerzeit (neutral statt LB „Berlin wählen“, LB-I18N-11). |
| I18N-R-24 | **Kalendertage:** „Modul X Tage nach Zugangsbeginn“ (E-14) und „Ablauf nach n Tagen“ rechnen in der Website-Zeitzone auf Tagesgrenzen (00:00 lokal), gespeichert als UTC-Zeitpunkt. Tests über Sommerzeitwechsel (Europe/Berlin März/Oktober) und eine Zone ohne Sommerzeit (Asia/Kolkata, +05:30). |
| I18N-R-25 | **Prüfungstimer** ist Dauer, keine Uhrzeit: Restzeit als `mm:ss` bzw. `h:mm:ss` mit lokalisierten Ziffern über `Intl.NumberFormat`; Screenreader-Ansage als Satz („5 Minuten verbleibend“, `_n`). |

### 3.4 Zahlen und Prozent

| ID | Regel |
|---|---|
| I18N-R-26 | PHP: `number_format_i18n( $n, $decimals )` für jede angezeigte Zahl (Zähler, Punkte, Plätze); Mengen-Satz mit `_n( '%s question', '%s questions', $n )` und `number_format_i18n( $n )` als Wert. |
| I18N-R-27 | Prozent über übersetzbares Muster (`/* translators: %s: number */ __( '%s%%' )`), weil Stellung und Leerzeichen sprachabhängig sind; JS: `Intl.NumberFormat(locale, { style: 'percent' })`. |
| I18N-R-28 | Eingaben (Bestehensgrenze, Punkte, Zeitlimit) in Admin-Formularen als ganze Zahlen mit Einheit im Label („Zeitlimit (Minuten)“); Dezimaleingaben akzeptieren Komma und Punkt, gespeichert wird kanonisch. Interne Werte bleiben ganzzahlig (`*_milli`, R0-08). |
| I18N-R-29 | CSV-Export: Zahlen ohne Tausendertrennzeichen, Dezimalpunkt, Datum ISO 8601; Trennzeichen wählbar (`,`/`;`), UTF-8 mit BOM optional; Formel-Injection-Schutz (INV-E-027). |

### 3.5 Sprache des Lernenden und Sprachwechsel

- WordPress kennt eine **Website-Sprache** (`get_locale()`) und eine **Profilsprache** je Benutzer (`get_user_locale()`). Im Admin gilt automatisch die Profilsprache. Im Frontend gilt standardmäßig die Website-Sprache.
- **Beschluss (E-124):** Einstellung „Lernbereich in der Profilsprache des Lernenden anzeigen“ (Tab Allgemein, Standard **an**, wenn mehr als eine Sprache installiert ist). Umsetzung: Für angemeldete Nutzer im Lernbereich `switch_to_user_locale( $user_id )` (WP ≥ 6.2) vor dem Rendern der Course-Captain-Hülle; Boot-Konfiguration liefert diese Locale an den Client; REST-Aufrufe der Lern-App senden `_locale=user`. Der Container erhält `lang`/`dir` der UI-Sprache, auch wenn das Theme drumherum in der Website-Sprache bleibt (WCAG 3.1.2).
- **Sprachwechsel:** L-20 „Konto & Datenschutz“ zeigt ein Feld „Sprache“ (nur installierte Sprachen, schreibt die WP-Profilsprache `locale`); nach Speichern lädt der Lernbereich in der neuen Sprache. Kein eigener Sprachspeicher neben WP (Konzept 43: „keine doppelten Optionen“).
- Gäste (Katalog, Einladung L-17, Verifikation L-16) sehen die Website-Sprache bzw. die Sprache, die ein Übersetzungsplugin für die Seite setzt (§4).

### 3.6 E-Mails in Empfängersprache

| ID | Regel |
|---|---|
| I18N-R-30 | Vor dem Erzeugen jeder Mail an einen bekannten Benutzer `switch_to_user_locale( $user_id )`, danach `restore_previous_locale()` in `finally`; Betreff, Text, Datum (`wp_date`) und Zahlen entstehen innerhalb des Wechsels. LB wechselt nie (LB-I18N-08). |
| I18N-R-31 | Massen- und Queue-Versand (Outbox, R1-21) erzeugt Texte **je Empfänger**; keine übersetzten Texte über Empfänger hinweg zwischenspeichern (vgl. Kommentar LB `WithdrawalTexts.php:111`). |
| I18N-R-32 | Empfänger ohne Konto (Gruppeneinladung per Adresse): Sprache der einladenden Person bzw. Website-Sprache; KANN in der Einladung gewählt werden. Admin-Benachrichtigungen in der Profilsprache des jeweiligen Admins. |
| I18N-R-33 | Betreiberdefinierte Mailtexte (Templates im Tab E-Mails) sind Inhalt: Platzhalter werden in der Empfängersprache befüllt, der Rahmentext folgt §4 (mehrsprachige Vorlagen → R0-25). |
| I18N-R-34 | Mail-HTML trägt `lang` und `dir` der Empfängersprache (A11Y-095). |

### 3.7 Testfälle i18n

| ID | Prüfung (Erwartung) | Werkzeug | Ziel |
|---|---|---|---|
| I18N-001 | Eigener Domain-Lint mit dem Verhalten von LB `check-domains.php` (neu geschrieben, kein Code übernommen, E-01; Domains `course-captain`, `course-captain-pro`) und `.pot` vorhanden – CI blockierend | PHP-Skript (INV-E-046) | R1-19 |
| I18N-002 | WPCS `WordPress.WP.I18n` ohne Fehler (Literal, Translator-Kommentare, Platzhalter-Reihenfolge) | PHPCS | R1-19 |
| I18N-003 | ESLint `@wordpress/i18n-*` (text-domain, no-variables, translator-comments, no-collapsible-whitespace, no-placeholders-only) ohne Fehler | ESLint | R1-19 |
| I18N-004 | `.pot` ist aktuell: `make-pot` in CI erzeugt keinen Diff zu den eingecheckten Strings (ohne Header-Zeitstempel) | `wp i18n make-pot` (INV-E-047) | R1-19 |
| I18N-005 | Kein `wp_date`/`date`/`date_i18n` mit Literal-Format außer ISO/Logs (Regex-Lint mit Ausnahmeliste) | Prüfskript | R1-19 |
| I18N-006 | Pseudo-Locale (`en_XA`: Akzente, +40 % Länge, Klammern) – alle Screens: kein abgeschnittener Text, kein unübersetzter String (fehlende Klammern = hart kodiert) | PW + Screenshot, MAN | R1-19 |
| I18N-007 | RTL: WP-Sprache `ar` oder `he_IL`; Lernbereich und A-04/A-15 gespiegelt, `dir=rtl` am Container, Icons gespiegelt, Pfeiltasten in Tabs korrekt (I18N-R-14), axe ohne Verstöße | PW, AXE, MAN | R1-19 |
| I18N-008 | Pluralformen: Sprache mit 3+ Formen (`pl_PL` oder `ru_RU`) mit Test-`.mo`; Zähler 1, 2, 5, 22 korrekt | PHPUnit, PW | R1-19 |
| I18N-009 | Zeitzone Europe/Berlin und Asia/Kolkata: Drip-Freischaltung, Ablauf und Prüfungsfrist am Tag des Sommerzeitwechsels richtig berechnet und angezeigt; Offset-Zeitzone löst Systemstatus-Hinweis aus | PHPUnit, PW (`timezoneId`) | R1-19 |
| I18N-010 | Zahlen/Datum: `de_DE` „1.234,5“, `en_US` „1,234.5“, `fr_FR` (schmales Leerzeichen); Prozent nach Muster | PHPUnit, PW | R1-19 |
| I18N-011 | Profilsprache ≠ Website-Sprache: Lernbereich, REST-Fehlertexte und Datumsformat in Profilsprache, Theme-Rahmen in Website-Sprache, `lang` am Container korrekt; Option aus → alles Website-Sprache | PW | R1-19 |
| I18N-012 | JS-Übersetzungen werden geladen (JSON vorhanden, Handle registriert): ein übersetzter String erscheint in der Lern-App und im Admin-Block | PW | R1-19 |
| I18N-013 | Mail an Lernenden mit Profilsprache `de_DE` bei Website `en_US`: Betreff, Text, Datum deutsch; nächste Mail in derselben Queue an `en_US`-Nutzer englisch; Locale danach zurückgesetzt | PHPUnit + Mail-Sink | R1-19 |
| I18N-014 | Kein Übersetzungsaufruf vor `init` (keine `_load_textdomain_just_in_time`-Notice im Debug-Log beim Aktivieren und bei Frontend-/REST-Aufruf) | PHPUnit/E2E mit `WP_DEBUG` | R1-19 |
| I18N-015 | Übersetzer-Review `de_DE`: vollständige Übersetzung, Du/Sie-Konsistenz (`de_DE` vs. `de_DE_formal`), Terminologieliste | MAN | R1-29 |

### 3.8 Werkzeuge: Verhalten aus LearnBoating, neu geschrieben

Nach **E-01 (Clean Room)** wird kein LB-Werkzeug kopiert. Die folgenden Punkte sind **Verhaltensspezifikationen**; die Werkzeuge werden ohne Einsicht in den LB-Quelltext während der Umsetzung neu geschrieben.

- **Domain-Lint (Verhalten wie LB `tools/i18n/check-domains.php`):** Tokenizer-basierte Prüfung, dass jede i18n-Funktion die Domain als Literal trägt und je Paket eine `.pot` existiert. Erlaubte Domains `course-captain`, `course-captain-pro`; Ausgabe englisch oder neutral; ausgenommen `tests`, `vendor`, `node_modules`, Build-Ordner.
- **POT-Erzeugung (Verhalten wie LB `tools/i18n/make-pot.sh`):** `wp i18n make-pot` je Paket mit leeren Headern und `--skip-theme-json`; **ohne** `--skip-audit` (Audit-Warnungen von WP-CLI sind für I18N-R-03/-06 nützlich); `make-json` im Release-Schritt (R0-35).
- **Ergänzen:** Regex-Lint für feste Datumsformate (I18N-005) und Verkettung übersetzter Texte (I18N-R-03), Stylelint für logische Eigenschaften (I18N-R-12), Pseudo-Locale-Generator (`.po` aus `.pot` per Skript), axe-Fixture für Playwright.
- **Nicht übernehmen:** String-Bundle mit `_one`-Schlüsseln (LB-I18N-03); Rückfall auf `de-DE` (LB-I18N-04); feste Formatstrings (LB-I18N-06).

---

## 4. Inhaltssprache vs. UI-Sprache (Übergabe an R0-25)

**Abgrenzung:** UI-Sprache = Texte von Course Captain (Knöpfe, Meldungen, Mails-Rahmen) über `.po/.mo`; Inhaltssprache = Sprache von Kurs, Lektion, Frage, Antwort, Erklärung, Zertifikatstext, die Autoren pflegen. Beide sind unabhängig: ein Kurs in Englisch kann in einer deutschen Oberfläche laufen.

**Was R0-15 festlegt (MUSS, unabhängig von der R0-25-Entscheidung):**

1. Kurs und Frage tragen ein Sprachkennzeichen (BCP 47): `ccap_question.language` existiert (R0-06), Kurs erhält `language` als Meta (LB-Paketformat `courses.language`, INV-E-004); Standard = Website-Sprache.
2. Inhaltscontainer (Lektion, Frage, Antworten, Erklärung, Zertifikatstext) bekommen `lang` und `dir` aus diesem Kennzeichen, wenn es von der UI-Sprache abweicht (A11Y-032; WCAG 3.1.2). Damit liest der Screenreader englische Fragen in einer deutschen Oberfläche richtig vor.
3. Ein Zertifikat friert Inhaltssprache und UI-Sprache bei Ausstellung im Snapshot ein (R0-08 §7.2); die Verifikationsseite zeigt den Nachweis in der eingefrorenen Sprache, den Rahmen in der Besuchersprache.
4. Untertitelspuren tragen `srclang`; die Standardspur folgt der UI-Sprache, sonst der Inhaltssprache (A11Y-090).
5. Suche und Sortierung in der Fragenbank berücksichtigen die Sprache (Collation `utf8mb4_unicode_520_ci` bzw. `*_0900_ai_ci`; FULLTEXT-Stoppwörter, D-14-04).

**Übergabefragen an R0-25 und ihre Beschlüsse:**

| Nr. | Frage | Beschluss |
|---|---|---|
| Ü-1 | Sprachversionen eines Kurses: eigener Kurs je Sprache mit Verknüpfung (`translation_group`) oder ein Kurs mit Sprachvarianten je Lektion/Frage? Wie verhalten sich Fortschritt, Mastery und Grants zwischen Sprachversionen? | **E-155:** eigener Kurs je Sprache, verknüpft über eine Übersetzungsgruppe; Fortschritt, Grants und Zertifikate je Kurs. **E-156:** kein Grant-Übertrag in R1, Sammelvergabe durch den Kursmanager |
| Ü-2 | Gemischte Sprachen innerhalb eines Kurses/einer Prüfung zulässig? Prüfungs-Blueprint nur aus Fragen einer Sprache? | **E-157:** erlaubt mit `lang`-Kennzeichnung und Validator-Warnung |
| Ü-3 | Locale-Fallback: fehlt eine Übersetzung einer Frage, Original zeigen (mit `lang`) oder Frage ausschließen? | Durch E-155 (eigener Kurs je Sprache) gibt es keine fehlenden Fragenübersetzungen innerhalb eines Kurses; UI-Locale-Fallback auf eine andere Variante derselben Sprache (`de_AT` → `de_DE`) ist technisch festgelegt (D-25-07) |
| Ü-4 | Kompatibilität mit Übersetzungsplugins (WPML, Polylang, TranslatePress) für CPT-Inhalte: unterstützt, getestet oder nur dokumentiert? Was gilt für eigene `ccap_`-Tabellen (Fragen), die solche Plugins nicht kennen? | Technisch festgelegt (D-25-06): Grenze nur dokumentieren, mit Systemstatus-Hinweis |
| Ü-5 | Mehrsprachige Mail-Vorlagen des Betreibers (I18N-R-33) und mehrsprachige Zertifikatsvorlagen (Pro). | **E-159:** Sprachvarianten für Mailvorlagen schon in Free. **E-158:** Zertifikat in der Kurssprache, im Snapshot eingefroren |
| Ü-6 | Katalog L-01: Filter nach Inhaltssprache; SEO `hreflang` bleibt Sache des SEO-/Übersetzungsplugins (Kap. 21). | Kein eigener Beschluss; Details in R0-25 |
| Ü-7 | Import (R0-10): Paket-`languages[]` vs. Zielsprache der Site; Verhalten bei Paket mit mehreren Sprachen. | Kein eigener Beschluss; Details in R0-25/R0-10 |

---

## 5. Abnahme und Übergaben

| Nachweis | Task | Kriterium |
|---|---|---|
| Komponentenregeln K-01…K-18 als Entwicklerrichtlinie | R1-16, R1-19 | Richtlinie im Repo, jede Regel mit Testverweis |
| A11Y-001…A11Y-112 (automatisierbare Teile) | R1-19 | grün in CI nach Gate D-15-05 |
| A11Y-0xx mit NVDA/VO/MAN, A11Y-102…104 | R1-29 | Protokoll mit Befunden und Behebungsstand; keine offenen Befunde „blockierend“ |
| I18N-001…014 | R1-19 | grün in CI |
| I18N-015, Übersetzer-Review | R1-29 | Protokoll |
| Zeitausgleich (§2.3) Datenvertrag | R0-06/R0-07/R0-17 (Deltas), R1-08, R1-20 | Snapshot-Test, Rechte-Negativtest, Audit |
| Konformitätsbericht je Release | R1-24, R0-35 | nach E-125 |
| Nutzertests, Geräte, Probanden | R0-26 | übernimmt §2.4 und A11Y-102…104 |
| Inhaltssprache | R0-25 | §4 Ü-1…Ü-7 beantwortet |

---

## 6. Entscheidungsbedarf – beschlossen

Alle Punkte sind entschieden (`ENTSCHEIDUNGEN.md`, 16.11.2026). Die D-Nummern bleiben als Verweise im Text stehen.

| ID | Frage | Beschluss | Betrifft |
|---|---|---|---|
| D-15-01 → **E-121** | Quellsprache der UI-Strings | Englisch (en_US), Deutsch als erste Übersetzung; Sprachpaket über translate.wordpress.org (E-208) | R1-01, R1-19, R0-35 |
| D-15-02 → **E-122** | Nachteilsausgleich in Prüfungen | Zeitfaktor 100–400 % oder ohne Limit je Lernendem, nur durch Berechtigte, ohne Begründungstext, im Snapshot eingefroren, in Ergebnis und Nachweis nicht sichtbar; Datenschutzbewertung (Art. 9) in R0-17 | R0-06, R0-07, R0-08, R0-17, R1-08 |
| D-15-03 → **E-123** | Timer-Modi je Prüfungsregel | `strict` (Standard für Nachweise) und `practice` (abschaltbar/verlängerbar, nicht nachweisfähig) | R0-08, R1-08 |
| D-15-04 → **E-124** | Lernbereich in Profilsprache und Zeitzonenanzeige | Einstellung „Profilsprache“, Standard an bei > 1 installierten Sprache; Fristen mit Zonenkennung; keine Nutzerzeitzone in R1 | R1-19, R0-25 |
| D-15-05 | A11Y-Gate in der CI | Technisch festgelegt: axe-Verstöße für WCAG-2.2-AA-Tags blockieren, Ausnahmen nur über Liste mit Ablaufdatum; manuelle Durchläufe vor jedem Minor-Release | R0-18, R1-18, R1-19 |
| D-15-06 → **E-125** | Konformitätsnachweis des Produkts | Konformitätsbericht je Release; keine Vorlage für die Barrierefreiheitserklärung (E-04), nur neutraler Hinweis auf nötige Angaben | R1-24, R0-35 |
| D-15-07 | Kontrastprüfung bei Design-Tokens | Technisch festgelegt: nächstgelegene konforme Farbe vorschlagen | R0-27, R1-16 |
| D-15-08 → **E-126** | Zugesicherte Screenreader | NVDA und VoiceOver (macOS, iOS); TalkBack best effort, JAWS nicht zugesichert | R0-26, R1-29 |
| D-15-09 → **E-127** | Barrierefreie PDF-Nachweise (Pro) | Getaggtes PDF (an PDF/UA orientiert) als Pro-Abnahmekriterium; ohne geeignete Bibliothek kein PDF (E-238) | R3, R0-29 |
| D-15-10 → **E-131** | Barrierefreiheit im Publish-Validator | Fehlender Alt-Text (ohne `decorative`) = Blocker; fehlende Untertitel = Warnung, per Site-Einstellung Blocker; Überschriftensprünge/Linktexte warnen | R0-22, R1-19 |
| D-15-11 | RTL-Stufe in R1 | Technisch festgelegt: RTL-fähig und mit einer RTL-Sprache getestet, Release-Gate | R1-19, R1-18 |

### Offene Punkte nach Einarbeitung

- Welche Fassung der EN 301 549 zum Release gilt, wird bei der Freigabe von R1-19 geprüft (§1.1). → **technisch, an R1-19 übergeben** (kein Auftraggeber-Beschluss, kein Blocker für Gate A)
- A11Y-093 im Default-Modus „immer laden“ (E-45a): Für den direkt geladenen Fremd-Player gibt es noch keinen eigenen Prüffall (z. B. zugänglicher Name des eingebetteten Players); in R1-19 ergänzen. → **technisch, an R1-19 übergeben** (kein Auftraggeber-Beschluss, kein Blocker für Gate A): Prüffall für den direkt geladenen Fremd-Player ergänzen
- Ü-6/Ü-7 haben keinen eigenen Beschluss und werden in R0-25 bzw. R0-10 präzisiert. → **technisch, an R0-25/R0-10 (präzisiert) übergeben** (kein Auftraggeber-Beschluss, kein Blocker für Gate A)

---

## 7. Nachweis dieser Arbeit

- Gelesen: TODO R0-15, R0-25, R0-26, R1-19, R1-29; Konzept Kap. 10, 16, 21, 23 (und Fundstellen zu Barrierefreiheit/Locale per Suche); R0-11 §2–3; R0-14 §0, §3, §6; R0-08 §4.3, §6; R0-06 §7 und Tabellen `ccap_question`/`ccap_exam_*`; R0-10 Alt-Text-Regel; Inventur D (INV-D-004/-005/-006/-062/-068/-070/-152…-157) und E (INV-E-007, -046, -047, Doku-Tabelle); `ENTSCHEIDUNGEN.md` E-01…E-102.
- LB gelesen (@ `9f37c77`): `docs/barrierefreiheit-oesterreich.md`, `docs/uebersetzung.md`, `tools/i18n/check-domains.php`, `tools/i18n/make-pot.sh` vollständig; `assets/build/app.js` an den Stellen aus INV-D-152 sowie Fragenformular (:2067-2081), Dialog (:2138-2158), Prüfungstimer (:4369-4374); `assets/build/app.css` (Scroll-Margins, physische Eigenschaften gezählt); `templates/app.php` (Kopf, `main`); `src/` per Suche nach `wp_date`, `_n`, `switch_to_*locale`, `determine_locale`, `wp_set_script_translations`, `gmdate`; `tests/e2e/*.mjs` per Suche nach Tastatur- und Reflow-Prüfungen.
- Nicht geprüft: Laufzeitverhalten von LB mit Screenreader (keine Testumgebung), aktuelle Fassung der harmonisierten EN 301 549 zum Release-Zeitpunkt.
