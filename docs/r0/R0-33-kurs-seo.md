# R0-33 · Kurs-SEO und öffentliche/private Grenzen (Entwurf)

**Status:** **Entwurf zur Abnahme, nicht abgehakt.** Keine Implementierung, kein Testlauf, kein Commit. Die DoD verlangt Quellkonzept/Abschnitt, Course-Delta, Datei/Commit, positive und negative Tests und Review. · **Stand:** 16.11.2026
**Bezug:** TODO R0-33 (Voraussetzungen R0-20, R0-27; DoD: „Katalog/Kurs/Lektion indexierbar nach Rechteklasse; Canonical, Sitemap, Schema, Yoast- und No-SEO-Fallback ohne private Leaks definiert“). Ziel-Task **R1-37** (DoD: „Öffentliche Kursseiten SEO-kompatibel; private Prüfungen, Profile, Medien und Grants nicht indexierbar oder per API abrufbar; Yoast-Konflikttests“), Nachbarn R1-14 (Zugriffsschutz), R1-28 (API-/Block-Verträge), R1-30 (Konfliktmatrix-Lauf). Konzept Kap. 21, 23.2 Nr. 6, 43.3 (K43-05, K43-07), 44 (Zeile „SEO-Zuständigkeit“: „kein eigenes SEO-Plugin, kein blindes `noindex` als Zugriffsschutz“).

**Quellen (nur gelesen, nicht verändert):**

| Quelle | Stand | Verwendet |
|---|---|---|
| `konzept.md` | Arbeitskopie | Kap. 7 (Kursfelder Z. 70), 21 (Z. 531–537), 23.2, 43.3 (Z. 1314), 44 (Z. 1378–1390), 46.2 |
| `docs/r0/R0-20-kompatibilitaet-konflikte.md` | Entwurf 16.11. | §2.7 KOMP-060…063, §3.1 CPT-Slugs, D-20-08, D-20-09, D-20-11, LB-K-05 |
| `docs/r0/R0-27-settings-register.md` | Entwurf 16.11. | `general.catalog_indexing`, `general.catalog_page_id`, `general.learner_home_page_id`, `display.breadcrumbs`, `certificates.holder_name_policy`, SET-034, D27-6 |
| `docs/r0/R0-21-cache-nebenlaeufigkeit.md` | Entwurf | §3.5 Routenklassen H-P/H-X/H-T, H-1…H-8, CB-13 |
| `docs/r0/R0-25-mandanten-locale-adr.md` | Entwurf | Ü-4 (kein `hreflang`), Ü-6 (`inLanguage`) |
| `docs/r0/R0-07-rbac-idor-matrix.md` | Entwurf | P-03, P-08, M-01, M-02, M-18, M-20, RG-21, §4.4, RBAC-015, RBAC-061, RBAC-069, Fixtures §6 |
| `docs/r0/R0-11-free-pro-matrix-ux.md` | Entwurf | L-01…L-19, N-05 |
| learnboating `hastla007/learnboating` | `9f37c77` | nur **Verhaltensbelege** (E-01 Clean Room, kein Code übernommen): `bootspruefung-learning/src/Frontend/Router.php:123` (`wp_robots` noindex/nofollow für App-Routen), `src/Gate/ContentGate.php:70, 131–170` (Feed-/Auszugssperre, Page-Builder-Daten verwerfen), `src/Mobile/WebSession.php:242`, `src/Mobile/AppLinks.php:191, 198` (`X-Robots-Tag`), `src/Faq/CustomerFaq.php:56–61` (Core-Sitemap ohne Kunden-FAQ), `src/Faq/FaqShortcode.php:138` (JSON-LD-Kodierung mit `JSON_HEX_TAG`), `src/Security/Hardening.php:51–65` (oEmbed ohne Autor, Yoast-Optionsfilter – **wird nicht nachgebaut**, LB-K-05), `themes/kurswasser/inc/content-yoast.php:11–18` (Theme schreibt Yoast-Meta – CC tut das **nicht**), Tests `tests/integration/membership-test.php:265–267`, `security-test.php:562–581`, `themes/kurswasser/tests/content-pages-test.php:288–291` („Yoast-Feld entscheidet“) |
| Form Captain `wp-formmaster/konzept.md` | `97cbe73` | §6.2 Z. 387–409: Seite ist das indexierbare Dokument, keine eigenen öffentlichen URLs/Sitemaps, kein eigenes Schema in v1, kein `noindex`, nur weil ein Formular auf der Seite steht |
| Review Captain `wp-reviewmaster/konzept.md` | `33d1362` | §18 Z. 906–925: Schema nur über Yoast-Schema-API als angeschlossenes Piece, keine zweite Organization/WebPage, Fallback nur ohne anderes SEO-Plugin, bei inkompatibler Yoast-Version kein paralleles JSON-LD; keine erfundenen Bewertungen |

**Beschlossen (Beschlussprotokoll):** **E-01** Clean Room (LB nur als Verhaltensbeleg); **E-10** Website-Härtung im Kern mit Schalter (Autoren-Sitemap/Autorenarchiv sind Sache des Härtungsmoduls, nicht dieses Dokuments; auch dort ändert CC **keine** Optionen fremder Plugins).
**Arbeitsannahmen (Empfehlungen aus `ENTSCHEIDUNGEN.md`, nicht beschlossen):** E-15 (Teilumfang Free), E-16 (öffentliche Website-FAQ bleibt Theme/Drittplugin), E-65 (zugangsgebundene Medien geschützt über PHP), E-97 (Name auf Verifikation standardmäßig aus), E-104 (nicht öffentliche Staging-Kopie), E-108 (Rückzug: „Einschreibung geschlossen“, dann „archiviert“), E-118 (Deprecation-Frist), E-155 (eigener Kurs je Sprache), **E-197** (nur `cc_course` hat eine öffentliche URL, Slug `kurs`, Lernbereich `/lernen/`), **E-220** (alte Slugs 301). Aus R0-20 als Annahme: D-20-08 (Plain-Permalink-Fallback), D-20-09 (Verifikation serverseitig), D-20-11 (`cc_module`/`cc_lesson`/`cc_tip` ohne öffentliche URL).

**Kennzeichnung:** „(v)“ = Hook-/Filtername eines fremden Plugins oder Kernverhalten aus Modellwissen, beim ersten R1-30-Lauf gegen die aktuelle Version zu prüfen (Konvention aus R0-20 §2). Neue Entscheidungsfragen heißen **D33-n** (§6).

**Kernzahlen:** Seitentypen §1.2: 27 · SEO-Klassen: 6 · Schema-Zeilen §2.1: 22 (davon 7 nie ausgegeben) · Leak-Kanäle §4: 26 · Testfälle SEO-001…SEO-052: 52 (alle Ziel R1-37) · neue Entscheidungen: 7.

---

## 0. Grundsätze

| ID | Regel | Herkunft |
|---|---|---|
| **SG-01** | **Zugriffsschutz kommt vor SEO.** Private Inhalte werden serverseitig gar nicht ausgeliefert (R0-07 P-03, R1-14). `noindex` ist eine zusätzliche Kennzeichnung, nie der Schutz selbst. | Kap. 44 („kein blindes `noindex` als Zugriffsschutz“) |
| **SG-02** | **Ein Dokument, eine Zuständigkeit.** Ist ein SEO-Plugin aktiv, gehören Titel-Template, Description, Canonical, Open Graph/X, Sitemap und Basis-Schema dem Plugin. CC liefert nur Robots-Einschränkungen, Sitemap-Ausschlüsse und – wenn eingeschaltet – ein angeschlossenes `Course`-Piece. | Kap. 21, R0-20 §2.7, Review Captain §18 |
| **SG-03** | **Die restriktivste Robots-Angabe gewinnt.** CC macht nie etwas indexierbar, das WordPress (`blog_public = 0`), das SEO-Plugin oder der Betreiber auf `noindex` gesetzt hat. CC kann nur zusätzlich einschränken. | neu |
| **SG-04** | **Kein Cloaking.** Suchmaschinen erhalten exakt die Gast-Antwort. Keine User-Agent-, IP- oder Bot-Erkennung, kein „First Click Free“, kein Volltext für Crawler. | neu (Google-Richtlinie) |
| **SG-05** | **Kein Schreiben in fremde Plugins.** CC ändert keine Yoast-/Rank-Math-Optionen, keine Post-Meta fremder Plugins (`_yoast_wpseo_*`, `rank_math_*`) und keine Indexables, auch nicht per Options-Filter. Nur dokumentierte Filter zur Laufzeit. | R0-20 LB-K-05; Gegenbeispiel LB-Theme `content-yoast.php` |
| **SG-06** | **Schema nur aus sichtbaren Daten.** Jedes Feld im JSON-LD stammt aus demselben View-Model, das die sichtbare Kursdetailseite rendert. Keine Bewertungen, Preise, Teilnehmerzahlen oder Abschlüsse, die nicht belegt und sichtbar sind. | Kap. 21 |
| **SG-07** | **Keine Disallow-Regeln.** CC schreibt nichts in `robots.txt`. Ein `Disallow` würde Crawler daran hindern, das `noindex` zu sehen; Schutz läuft über Auslieferung, Robots-Meta und `X-Robots-Tag`. | neu |
| **SG-08** | **Private Seiten tragen keine personenbezogenen Daten in Titel, Canonical, OG oder URL-Parametern** (kein Name, keine Punktzahl, keine E-Mail). Session-/Versuchs-IDs in Pfaden sind nur für den Inhaber gültig (P-08). | R0-07 P-08, neu |

---

## 1. Rechteklassen je Seitentyp

### 1.1 SEO-Klassen

| Klasse | Bedeutung | robots-Meta (`wp_robots` + Plugin-Filter) | `X-Robots-Tag` | Canonical | Sitemap | OG/X | JSON-LD | Cache (R0-21) |
|---|---|---|---|---|---|---|---|---|
| **S-I** öffentlich, indexierbar | für Gäste identisch, nicht personalisiert | keine CC-Einschränkung (Kern/Plugin-Standard) | – | selbstreferenzierend (Kern `rel_canonical` bzw. Plugin) | ja | ja (Plugin, sonst CC-Fallback §3.5) | `Course` nur mit D33-2 | H-P |
| **S-N** öffentlich, nicht indexiert | erreichbar, aber nicht für die Suche bestimmt | `noindex, follow` | – | nur vom Plugin, CC gibt keinen aus | nein | Plugin; CC-Fallback ja (teilbare Seite) | nein | H-P |
| **S-P** personalisiert/privat | Lernbereich, Anmeldung, Fortschritt, Prüfung | `noindex, nofollow` | `noindex, nofollow` | **keiner** (auch Plugin-Canonical unterdrückt, §3.3) | nein | **nein** (auch Plugin-OG unterdrückt) | nein | H-X |
| **S-T** Token-Seite | Verifikation, Einladung, Abmelde-/Widerrufslink | `noindex, nofollow` | `noindex, nofollow` | keiner | nein | nein | nein | H-T (`Referrer-Policy: no-referrer`) |
| **S-F** Datei/API | geschützte Datei, Zertifikat-PDF, eigene REST-Antworten | – (kein HTML) | `noindex, nofollow` | – | nein | – | – | H-X |
| **S-0** keine öffentliche Adresse | Lektion, Modul, Tipp, Frage, Staging-Kopie als WP-Objekt | – | – | – | nein | – | – | – (direkter Aufruf → 404) |

`noindex, nofollow` für S-P/S-T schützt zusätzlich vor dem Verfolgen von Links in Lerninhalten; für S-N bleibt `follow`, damit Kurslinks aus einem nicht indexierten Katalog weiter entdeckt werden.

### 1.2 Matrix Seitentyp → Klasse

„Gast“ = nicht angemeldet; „L“ = angemeldeter Lernender mit passendem Zugang. Die HTTP-Status folgen R0-07 P-08 (fremd/unsichtbar = 404, legitim bekannt ohne Zugang = 403); SEO ändert keinen Status.

| PT | Seitentyp (Screen) | Adresse | Bedingung | Klasse | Indexierbar | Sitemap | Bemerkung |
|---|---|---|---|---|---|---|---|
| PT-01 | Katalog auf WP-Seite (L-01) | `general.catalog_page_id` | `catalog_indexing = public_catalog`, Seite veröffentlicht | **S-I** | ja | ja (als Seite) | Fortschritt auf Karten nur clientseitig nachgeladen (R0-21 H-5), sonst wäre die Seite H-X |
| PT-02 | Katalog auf WP-Seite | wie PT-01 | `catalog_indexing = noindex` | S-N | nein | nein | SET-034 |
| PT-03 | Katalog gefiltert/paginiert | PT-01 + `?course_captain_lang=`, `…_cat=`, `…_q=` | Filter- oder Suchparameter gesetzt | S-N | nein | nein | Canonical (Plugin bzw. Kern) zeigt auf die ungefilterte Katalogseite; reine Seitenzahl `…_page=n` bleibt S-I mit selbstreferenzierendem Canonical |
| PT-04 | Katalog im Lernbereich | `/lernen/katalog/` (wenn `catalog_page_id = 0`) | immer | S-P | nein | nein | Lernbereich ist immer `noindex` (R0-27). Bereitschaftsprüfung weist darauf hin: „Ohne Katalogseite ist der Katalog nicht auffindbar“ |
| PT-05 | Kursdetail (L-02) | `/kurs/<slug>/` | veröffentlicht, gelistet, effektiv `index` (§1.3) | **S-I** | ja | ja | einzige CC-Adresse mit Schema |
| PT-06 | Kursdetail | wie PT-05 | Kurs-Override `noindex` oder global `noindex` ohne Override `index` | S-N | nein | nein | SET-034 |
| PT-07 | Kursdetail „nicht gelistet“ | wie PT-05 | `listing = unlisted` (D33-3) | S-N | nein | nein | nicht im Katalog, nicht in Suche/REST-Liste (§4) |
| PT-08 | Kursdetail „Einschreibung geschlossen“ | wie PT-05 | E-108 Stufe 1 | wie PT-05/06 | wie PT-05/06 | wie PT-05/06 | sichtbarer Hinweis; Schema ohne Änderung, kein `offers` |
| PT-09 | Kurs archiviert | wie PT-05 | E-108 Stufe 2 | – | nein | entfernt | **404** (M-01; D33-4), Purge H-7; Lernende erreichen ihre Ergebnisse über den Lernbereich |
| PT-10 | Kurs Entwurf / Staging / Vorschau | `?preview=true`, Staging (E-104) | nur M-03 | S-P | nein | nie | Gast/U-X → 404; Vorschau-Links nicht teilbar (Nonce) |
| PT-11 | Alter Kurs-Slug | `/kurs/<alt>/` | Slug-Historie (D27-6) | – | – | nur neuer Slug | **301** auf neuen Slug (E-220); Canonical und Sitemap nur neu |
| PT-12 | Kurs-Embed | `/kurs/<slug>/embed/` | veröffentlicht, gelistet | S-N | nein | nein | nur öffentliche Felder (§4 L-09) |
| PT-13 | Mein Lernen (L-04), Lernenden-Startseite | `/lernen/` bzw. `general.learner_home_page_id` | immer | S-P | nein | nein | gilt auch, wenn der Betreiber eine eigene WP-Seite wählt (CC setzt dort `noindex`, SG-03) |
| PT-14 | Anmeldung/Registrierung (L-03) | `/lernen/anmelden/` | immer | S-P | nein | nein | `wp-login.php` regelt der Kern |
| PT-15 | Kurs-Hub (L-05), Smarttrainer, Lernmodus, Merkliste, Fehlertraining, Karteikarten (L-07…L-11) | `/lernen/<kurs>/…` | immer | S-P | nein | nein | Titel nur „Kursname – Bereich“, keine Zahlen |
| PT-16 | Lektion **frei** (Teilumfang/Free-Regel, E-15) | `/lernen/<kurs>/video/<id>/` u. a. (L-06) | angemeldet, Zugang über Teilumfang | S-P | nein | nein | Teilumfang verlangt in R1 eine Anmeldung (D33-1) |
| PT-17 | Lektion **Teaser** (gesperrt, Kurs bekannt) | wie PT-16 | veröffentlichter Kurs ohne passenden Zugang/Drip | S-P | nein | nein | nur Titel + Sperrgrund + Weg zum Zugang; kein Auszug aus dem Inhalt |
| PT-18 | Lektion **privat** (fremd, Entwurf, Rechtestatus gesperrt) | wie PT-16 | sonst | – | nein | nein | 404 (P-08) |
| PT-19 | Lektion **öffentlich** (Gast, indexierbar) | – | **in R1 nicht vorhanden** (E-197/D-20-11; D33-1) | S-0 | nein | nein | `?post_type=cc_lesson&p=<id>`, `?cc_lesson=<slug>` → 404 ohne Weiterleitung |
| PT-20 | Prüfungs-Hub, Prüfungslauf (L-12, L-13) | `/lernen/<kurs>/pruefung/…` | immer | S-P | nein | nein | Titel ohne Restzeit/Punkte |
| PT-21 | Ergebnis (L-14) | `/lernen/<kurs>/pruefung/<session>/ergebnis/` | nur Inhaber (M-09) | S-P | nein | nein | fremde Session → 404 |
| PT-22 | Eigene Nachweise (L-15) | `/lernen/nachweise/` | angemeldet | S-P | nein | nein | – |
| PT-23 | Zertifikat-PDF (R-41) | REST bzw. Download | M-18 | S-F | nein | nein | `X-Robots-Tag: noindex, nofollow`, `no-store` |
| PT-24 | Öffentliche Verifikation (L-16) | `/lernen/nachweis/<token>`, REST R-42 | Token | **S-T** | nein | nie | Name nur nach E-97; kein Schema (`EducationalOccupationalCredential` würde Personendaten strukturieren) |
| PT-25 | Gruppeneinladung (L-17), Claim, Abmelde-/Widerrufsseiten | `/gruppe/<token>`, Mail-Links | Token | S-T | nein | nie | – |
| PT-26 | Geschützte Datei | `/cc-file/<uid>/`, `GET /media/{uid}` | M-20 | S-F | nein | nein | auch für Bilder; kein Eintrag in Bild-Sitemaps |
| PT-27 | WP-Beitrag/Seite mit Gate (Block, Shortcode, Gate-Angabe am Beitrag) | Permalink des Beitrags | Betreiberinhalt | **Betreiber/Plugin** (S-I möglich) | nach Betreiber | nach Betreiber | CC ändert die Robots nicht (wie Form Captain §6.2); gesperrter Teil wird für alle Gäste **und** Crawler durch den Teaser ersetzt (SG-04); kein automatischer Auszug (LK-14 in §4); Seite wird H-X |

### 1.3 Auflösung der Indexierbarkeit (eine Funktion, überall genutzt)

`course_indexable(course) =` veröffentlicht ∧ keine Staging-Kopie ∧ nicht archiviert ∧ `listing = listed` ∧ (`seo_index = index` ∨ (`seo_index = inherit` ∧ `general.catalog_indexing = public_catalog`)) ∧ `blog_public = 1` ∧ ¬(SEO-Plugin meldet `noindex` für diesen Beitrag) ∧ `apply_filters('course_captain_seo_indexable', true, …)`.

- Dieselbe Funktion speist robots-Meta, Sitemap-Ausschluss, JSON-LD-Ausgabe, `rest_post_search_query` und den Katalog-Block (nur `listing`). Abweichungen zwischen Kanälen sind damit ausgeschlossen (SEO-040).
- Der Filter `course_captain_seo_indexable` kann **nur einschränken**: Das Ergebnis wird mit dem internen Wert UND-verknüpft; `true` von einem Filter macht nichts indexierbar.
- Der Kurs-Override `seo_index` (`inherit/index/noindex`, R0-27) und ein gleichzeitig gesetztes Robots-Feld im SEO-Plugin: Es gilt SG-03. Der Kurseditor zeigt den Hinweis „Yoast/Rank Math setzt für diesen Kurs `noindex`; die Course-Captain-Einstellung kann das nicht aufheben“.
- Sprachversionen (E-155): Jede Version ist ein eigener Kurs mit eigenem Canonical. Kein Canonical auf eine andere Sprachversion, kein `hreflang` (R0-25 Ü-4); `inLanguage` nach §2.
- Plain-Permalinks (D-20-08): Kursdetail behält die Kern-Adresse `?cc_course=<slug>` mit Kern-Canonical; der Query-Fallback des Lernbereichs ist S-P.

### 1.4 Registrierung der Inhaltstypen (Vorgabe für R1-04, SEO-relevant)

| Typ | `public` | `publicly_queryable` | `exclude_from_search` | `has_archive` | `show_in_rest` | Kommentare | Begründung |
|---|---|---|---|---|---|---|---|
| `cc_course` | true | true | false (unlisted wird per Abfrage-Filter entfernt) | **false** | true (Editor) | nein | Katalog ist die Katalogseite, kein zweites Archiv mit Duplicate Content |
| `cc_module`, `cc_lesson`, `cc_tip` | **false** | false | true | false | true (nur Editor; Sichtprüfung RG-21) | nein | D-20-11; Core-/Plugin-Sitemaps, `/wp/v2/search` und oEmbed berücksichtigen nur öffentliche Typen (v) |
| `cc_faq` | false | false | true | false | true (nur Editor) | nein | Kurs-FAQ für Teilnehmende; öffentliche Website-FAQ bleibt Theme/Drittplugin (E-16) |
| Kurs-Taxonomien (falls R1-04 sie anlegt) | false | false | – | – | true | – | keine Archivseiten in R1 (D33-5), Katalogfilter statt Archiv |

---

## 2. Strukturierte Daten (schema.org)

**Schalter:** neue Einstellung `general.structured_data` = `off | course` (Default nach D33-2: `off`). Ausgabe nur auf PT-05 (S-I). Das Schema-Piece wird aus dem View-Model der sichtbaren Kursdetailseite gebaut (SG-06); ein Feld, das auf der Seite nicht sichtbar ist, entfällt.

### 2.1 Feldzuordnung `Course` und `CourseInstance`

| Eigenschaft | Quelle (veröffentlichte Kursrevision) | Bedingung | Ausgabe |
|---|---|---|---|
| `@type` / `@id` | – | – | `Course`, `@id = <canonical>#course` |
| `name` | Kurstitel | immer | Klartext |
| `description` | Kurzbeschreibung bzw. manueller Auszug | nicht leer | Klartext, HTML entfernt, ≤ 500 Zeichen; nie aus Lektionsinhalten |
| `url` | Canonical | immer | absolute URL (neuer Slug, E-220) |
| `inLanguage` | `_cc_language` (BCP 47) | immer | z. B. `de-DE` (R0-25 Ü-6) |
| `image` | Beitragsbild des Kurses | Anhang **nicht** geschützt (E-65), öffentlich erreichbar | URL der öffentlichen Bildgröße |
| `provider` | Yoast: `{"@id": "<home>/#organization"}` bzw. `#/schema/person/…` (v); Rank Math: Entität des Plugins (v); ohne Plugin: `Organization` mit `name = blogname`, `url = home_url()` | immer | Referenz statt zweiter Organization (Review Captain §18) |
| `teaches` | Lernziele | sichtbar auf der Seite | Liste von Texten |
| `coursePrerequisites` | Voraussetzungen | sichtbar | Text |
| `educationalLevel` | Schwierigkeitsgrad | gesetzt | Bezeichnung in Kurssprache |
| `hasCourseInstance` | – | immer, wenn `Course` ausgegeben wird | genau eine `CourseInstance` |
| ↳ `courseMode` | – | immer | `online` |
| ↳ `courseWorkload` | Kursfeld „Dauer“ | gesetzt | ISO 8601, z. B. `PT6H30M` |
| ↳ `inLanguage` | wie oben | – | – |
| ↳ `instructor` | Trainername | nur nach D33-6 (Anzeige freigegeben **und** sichtbar) | `Person` nur mit `name` |
| `offers` | – | **R1 nie.** R3 nur über einen Angebots-Provider mit echtem Preis/Währung | – |
| `isAccessibleForFree` | – | **nie in R1** (Free kennt keinen belegbaren Preis; „kostenlos“ wäre bei extern verkauften Grants falsch) | – |
| `aggregateRating`, `review` | – | **nie** (keine eigenen Bewertungsdaten; Review Captain zuständig) | – |
| `totalHistoricalEnrollment` | – | **nie** (Geschäfts-/Personenbezug bei kleinen Kursen) | – |
| `educationalCredentialAwarded`, `numberOfCredits` | – | **nie** (Basisnachweis ist kein anerkannter Abschluss, Kap. 21) | – |
| `hasPart`, `syllabusSections` | – | **nie in R1** (Lektionen ohne öffentliche URL; Kurstitel der Module stehen nur sichtbar im HTML) | – |
| `startDate`, `endDate`, `courseSchedule` | – | **nie in R1** (selbstgesteuerte Kurse; Termine erst mit R3/Pro) | – |

**Kodierung:** `wp_json_encode` mit `JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` (Verhaltensbeleg LB `FaqShortcode.php:138`), Texte vorher mit `wp_strip_all_tags`. Nach dem Filter `course_captain_seo_schema_course` validiert CC erneut gegen die erlaubten Schlüssel dieser Tabelle; verbotene Schlüssel (z. B. `aggregateRating`) werden entfernt und im Debug-Log vermerkt.

### 2.2 `BreadcrumbList`

- Nur wenn `display.breadcrumbs = true`. Pfad: Startseite › Katalogseite (falls PT-01 existiert und veröffentlicht ist) › Kurs.
- Mit Yoast/Rank Math gibt das Plugin die Breadcrumbs (HTML und Schema) aus; CC ergänzt nur die Katalogseite als Zwischenglied über `wpseo_breadcrumb_links` bzw. `rank_math/frontend/breadcrumb/items` (v). Ohne Plugin gibt CC HTML-Breadcrumbs **und** – nur bei `structured_data = course` – das `BreadcrumbList`-JSON-LD aus.
- Lernbereich (S-P) hat sichtbare Navigation, aber kein Breadcrumb-Schema.

### 2.3 Prüfung der Schemaausgabe

- **Gültigkeit** gegen schema.org (Validator, Snapshot im Test) und **Rich-Result-Eignung** getrennt bewerten (Kap. 21). Google verlangt für Kursinformationen u. a. `offers`; R1 ist damit voraussichtlich **gültig, aber nicht rich-result-fähig**. Das wird im Kurseditor nicht als Fehler gemeldet und in der Doku so benannt (kein Versprechen von Rich Results).
- Kein `FAQPage`, `ItemList` (Katalog), `VideoObject` (geschützte Videos) oder `EducationalOccupationalCredential` in R1.

---

## 3. Integration mit SEO-Plugins und Fallback

### 3.1 Erkennung (einmal je Request, Request-Memo)

| Modus | Erkennung | Stufe (R0-20) |
|---|---|---|
| `yoast` | `defined('WPSEO_VERSION')` und Mindestversion mit Schema-API (`wpseo_schema_graph_pieces`) (v) | zugesichert (KOMP-060) |
| `rankmath` | `defined('RANK_MATH_VERSION')`, Filter `rank_math/json_ld` vorhanden (v) | getestet (KOMP-061) |
| `other` | AIOSEO (`AIOSEO_VERSION`), SEOPress (`SEOPRESS_VERSION`), The SEO Framework (`THE_SEO_FRAMEWORK_VERSION`), Slim SEO u. a. (v) | best effort (KOMP-063) |
| `none` | keines der obigen | zugesichert (KOMP-062) |

- Yoast/Rank Math in einer Version **ohne** erwartete Schnittstelle → Modus `other` (nur Robots), Systemstatus „SEO-Plugin erkannt, Schnittstelle fehlt: Course Captain gibt kein Schema aus“ (Review Captain §18: bei inkompatibler Version kein paralleles JSON-LD).
- Filter `course_captain_seo_provider` erlaubt dem Betreiber, den Modus zu erzwingen (z. B. `other` für ein nicht erkanntes Plugin). Eigener Vertrag nach R0-19, Deprecation nach E-118.
- Zusätzlich erkannt: Jetpack Open Graph (`jetpack_enable_open_graph`, v) und Themes mit eigener OG-Ausgabe über Filter `course_captain_seo_fallback_og` (Default true) → bei Jetpack-OG gibt CC im Modus `none` **kein** eigenes OG aus.

### 3.2 Zuständigkeit je Ausgabe

| Ausgabe | `yoast` / `rankmath` | `other` | `none` |
|---|---|---|---|
| `<title>` | Plugin (Template); auf CC-Routen S-P/S-T CC über Plugin-Titelfilter | Plugin; S-P über `pre_get_document_title` | Kern `wp_get_document_title`; S-P über `pre_get_document_title` |
| Meta-Description | Plugin | Plugin | **CC** auf PT-01/PT-05 (Kurzbeschreibung, ≤ 160 Zeichen) |
| Canonical | Plugin auf S-I; auf S-P/S-T von CC unterdrückt | Plugin; Unterdrückung auf S-P best effort | Kern `rel_canonical` (singular); auf CC-Routen keiner |
| robots-Meta | Plugin + CC über Plugin-Robots-Filter **und** `wp_robots` | `wp_robots` + bekannte Filter (v) | `wp_robots` |
| `X-Robots-Tag` | CC (S-P/S-T/S-F) | CC | CC |
| Open Graph / X | Plugin auf S-I/S-N; auf S-P/S-T unterdrückt | Plugin | **CC** auf PT-01/PT-05 (§3.5), sonst nichts |
| JSON-LD `Course` | CC als Piece im Plugin-Graph (nur D33-2) | **nichts** | CC als eigener `<script>`-Block (nur D33-2) |
| Sitemap | Plugin + CC-Ausschlüsse | Plugin; Ausschluss nur über `noindex` + Typflags | Kern-Sitemap + CC-Ausschlüsse |
| Breadcrumbs | Plugin + CC-Zwischenglied | Theme/Plugin | CC (HTML, Schema nur D33-2) |

### 3.3 Yoast SEO (Vorgaben für R1-37, alle Namen (v))

| Zweck | Mechanik | Regel |
|---|---|---|
| Robots | `wpseo_robots` / `wpseo_robots_array` | auf S-N/S-P/S-T einschränken, nie lockern |
| Canonical auf CC-Routen | `wpseo_canonical` → `false` | nur S-P/S-T (virtuelle Routen hätten sonst ein falsches Canonical der Trägerseite) |
| OG/X/Schema auf CC-Routen | `wpseo_frontend_presenters` (Presenter für OG, Twitter, Canonical, Schema entfernen) bzw. `wpseo_json_ld_output` | nur S-P/S-T |
| Sitemap-Typen | `wpseo_sitemap_exclude_post_type` | `cc_module`, `cc_lesson`, `cc_tip`, `cc_faq` → ausschließen (zusätzlich zu `public = false`) |
| Sitemap-Einträge | `wpseo_exclude_from_sitemap_by_post_ids` | alle `cc_course` mit `course_indexable = false` und die Katalogseite bei PT-02; nötig, weil Yoast CC-Robots aus Filtern nicht in seine Indexables übernimmt |
| Sitemap-Taxonomien | `wpseo_sitemap_exclude_taxonomy` | CC-Taxonomien |
| Bilder in Sitemap | `wpseo_sitemap_urlimages` | geschützte Anhänge entfernen |
| Schema | `wpseo_schema_graph_pieces` (Piece-Klasse mit `is_needed()` nur auf PT-05 und D33-2) | Piece referenziert `WebPage`/`Organization` per `@id`; vor dem Hinzufügen prüfen, ob der Graph schon ein `Course` mit derselben `url` enthält → dann nichts |
| Breadcrumbs | `wpseo_breadcrumb_links` | Katalogseite als Zwischenglied |
| REST-Kopf | Felder `yoast_head`/`yoast_head_json`, Route `yoast/v1/get_head` | darf für CC-Routen nur die S-P-Kopfdaten liefern (Test SEO-024); für nicht öffentliche Typen keine Felder |

### 3.4 Rank Math (alle Namen (v))

`rank_math/frontend/robots` (einschränken), `rank_math/frontend/canonical` (auf S-P/S-T leer), `rank_math/opengraph/facebook/…` bzw. `rank_math/opengraph/twitter/…` (auf S-P/S-T entfernen), `rank_math/json_ld` (Course-Entität ergänzen; entfällt, wenn der Betreiber im Rank-Math-Schemagenerator schon „Course“ für den Beitrag gesetzt hat), `rank_math/sitemap/exclude_post_type`, `rank_math/sitemap/posts_to_exclude`, `rank_math/sitemap/exclude_taxonomy`, `rank_math/frontend/breadcrumb/items`. Headless-Kopf-Route (`rankmath/v1/getHead`) wie Yoast-REST-Kopf (SEO-024).

### 3.5 Fallback ohne SEO-Plugin (Modus `none`)

- **Robots:** `wp_robots` (Kern seit 5.7): S-N `noindex, follow`, S-P/S-T `noindex, nofollow`; vorhandene Kernwerte (`max-image-preview:large`) bleiben auf S-I unverändert. `blog_public = 0` setzt der Kern selbst (SG-03).
- **Core-Sitemaps:** `wp_sitemaps_post_types` entfernt alle CC-Typen außer `cc_course`; `wp_sitemaps_posts_query_args` (für `cc_course`) schließt nicht indexierbare Kurse aus; dasselbe für `page` mit der Katalogseite bei PT-02; `wp_sitemaps_taxonomies` entfernt CC-Taxonomien; `wp_sitemaps_posts_entry` setzt `lastmod` auf den Zeitpunkt der letzten **veröffentlichten** Kursrevision (Staging-Änderungen nach E-104 ändern ihn nicht) (v). Sind die Core-Sitemaps durch ein anderes Plugin abgeschaltet, gibt CC keine eigene Sitemap aus.
- **Meta/OG** (nur PT-01, PT-05, und nur wenn `course_captain_seo_fallback_og` true und kein Jetpack-OG): `description`, `og:type=website`, `og:title`, `og:description`, `og:url` (= Canonical), `og:locale` (Kurssprache → `de_DE`-Form), `og:site_name`, `og:image` nur nach Regel aus §2.1 `image`, `twitter:card=summary_large_image` bzw. `summary` ohne Bild. Auf S-N-Kursdetail dürfen Meta/OG ebenfalls erscheinen (teilbar), auf S-P/S-T nie.
- **JSON-LD:** nur bei D33-2, ein einziger `<script type="application/ld+json">` mit `@graph` aus `Course` (+ `BreadcrumbList`).
- **Canonical:** Kern `rel_canonical` für `cc_course` (singular); CC gibt keinen eigenen Canonical aus und entfernt ihn auf virtuellen CC-Routen (`remove_action('wp_head','rel_canonical')` nur dort).

### 3.6 Systemstatus und Kurseditor

- Systemstatus „SEO“: erkannter Modus, Version, ob Schema-Schnittstelle vorhanden, `blog_public`, Katalogseite indexierbar ja/nein, Anzahl indexierbarer Kurse, Warnung bei Konflikten (Jetpack-OG + CC-OG, Suchindex-Plugin mit CC-Typen nach D33-7, Lernbereich-Slug mit Disallow in einer physischen `robots.txt`).
- Kurseditor-Seitenleiste „Sichtbarkeit & Suche“: `listing`, `seo_index`, berechneter Status („wird indexiert / nicht indexiert, weil …“) mit Begründung aus §1.3. Keine eigenen Titel/Description-Felder, wenn ein SEO-Plugin aktiv ist (Hinweis auf das Plugin-Feld); ohne Plugin nutzt CC die Kurzbeschreibung.

---

## 4. Leak-Schutz: private Inhalte in maschinellen Kanälen

„Privat“ = Inhalt von Lektionen/Modulen/Tipps/Kurs-FAQ, Fragen und Lösungen, Prüfungs- und Ergebnisdaten, Fortschritt, Grants/Seats, Teilnehmer- und Trainerdaten (soweit nicht freigegeben), geschützte Medien, Staging-Revisionen, nicht gelistete/archivierte Kurse in Listen. Prüfmethode für alle Kanäle: **Canary-Marker** (eindeutige Zeichenfolgen in jedem privaten Fixture-Objekt, §5.1) dürfen in keiner Gast-/U-X-Antwort vorkommen.

| LK | Kanal | Risiko | Regel | Test |
|---|---|---|---|---|
| LK-01 | Frontend-Suche `?s=` | Lektionstext/-titel in Treffern | `exclude_from_search` für alle nicht öffentlichen Typen; `pre_get_posts` entfernt nicht gelistete/archivierte Kurse aus der Hauptsuche | SEO-030 |
| LK-02 | REST `/wp/v2/search` | Typen mit `show_in_rest` | nur öffentliche Typen (v); `rest_post_search_query` (v) entfernt nicht gelistete Kurse | SEO-031 |
| LK-03 | REST `/wp/v2/cc_lesson`, `cc_module`, `cc_tip`, `cc_faq` (Liste, Einzel, `_embed`, `?rest_route=`) | `content.rendered`, `excerpt`, `title` von Entwürfen, Meta | Gast/U-X: Liste leer bzw. 401/404, kein `content`/`excerpt` ohne M-02/M-03 (RG-21); Post-Meta nur mit `show_in_rest` für Editoren (`auth_callback` = M-03) | SEO-032, RBAC-015 |
| LK-04 | REST Revisionen/Autosaves | ältere Lektionsfassungen | gleicher Check wie Bearbeitung (R0-07 §4.4) | SEO-033 |
| LK-05 | REST `/wp/v2/cc_course` | Staging, nicht gelistete Kurse, interne Meta | Liste nur `course_indexable`- oder zumindest `listing = listed`-Kurse; Einzelabruf nicht gelisteter Kurse nur per Slug/ID (wie URL); keine Grant-/Statistik-Meta | SEO-034 |
| LK-06 | REST `/wp/v2/media`, Attachment-Seiten, `?attachment_id=` | geschützte Dateien, Pfade | RBAC-069; Attachment-Seite geschützter Anhänge → 404 | SEO-035 |
| LK-07 | REST `/wp/v2/blocks` (synchronisierte Muster mit Gate-Block) | Rohinhalt | nur mit Bearbeitungsrecht (v); gerenderter Inhalt durchläuft das Gate | SEO-036 |
| LK-08 | REST-Discovery (`<link rel="alternate" type="application/json">`, `/wp-json/`) | Lektions-IDs | Link nur auf S-I-Kursseiten; keine CC-eigenen Discovery-Links auf S-P | SEO-037 |
| LK-09 | oEmbed (`/oembed/1.0/embed`, `/embed/`-Template) | Auszug geschützter Inhalte, Trainername | Lektion/Lernbereich-URL → 404; Kurs → Titel, öffentliche Kurzbeschreibung, öffentliches Bild; `author_name`/`author_url` nur nach D33-6; Embed `noindex` | SEO-038 |
| LK-10 | Feeds (`/feed/`, `?post_type=cc_lesson&feed=rss2`, Kommentar-Feeds, `the_content_feed`, `the_excerpt_rss`) | Volltext | CC-Typen nicht im Hauptfeed; kein Archiv/Feed für CC-Typen (`has_archive=false`, keine Kommentare); Gate-Beiträge im Feed nur Teaser (Verhalten LB `ContentGate.php:70`, Test `membership-test.php:265–267`) | SEO-039 |
| LK-11 | Core-Sitemaps | nicht öffentliche Typen, nicht indexierbare Kurse | §3.5 | SEO-010…012 |
| LK-12 | Yoast-/Rank-Math-Sitemaps inkl. Bild-Sitemap | wie LK-11 + geschützte Bilder | §3.3/§3.4 | SEO-013, SEO-014 |
| LK-13 | Open Graph / X | Bild/Beschreibung aus geschütztem Material, OG auf S-P | §3.5; Plugin-OG auf S-P/S-T unterdrückt | SEO-020, SEO-022 |
| LK-14 | Automatische Auszüge (`get_the_excerpt`, Yoast-Variable `%%excerpt%%`, Theme-Teaser) | erster Absatz geschützter Inhalte | Gate-Beiträge: nur manueller Auszug, sonst neutraler Hinweis (Verhalten LB `ContentGate.php:160–170`) | SEO-041 |
| LK-15 | Fremde Suchindexer (Relevanssi, SearchWP, ElasticPress, Jetpack Search) | Volltext in eigener Index-Tabelle | nach D33-7 | SEO-042 |
| LK-16 | Page-/CDN-Cache | Volltext eines Käufers an Gast | R0-21 H-X/H-2/H-6 (CON-031…033); SEO ändert nichts daran | CON-031 |
| LK-17 | Vorschau-Links `?preview=true` | Entwurf öffentlich teilbar | nur M-03, `noindex`; Gast → 404 | SEO-006 |
| LK-18 | Kurz-/ID-Links (`?p=<id>`, `?page_id=`, `?cc_lesson=<slug>`, Shortlink) | Existenz-Orakel, Weiterleitung auf Slug | 404 ohne Weiterleitung; `redirect_guess_404_permalink` darf nie auf CC-Typen außer `cc_course` zeigen (v) | SEO-043 |
| LK-19 | HTML der Kursdetailseite | Lektionsinhalte, Fragen, Teilnehmerzahl, Staging-Titel | nur veröffentlichte Revision: Beschreibung, Lernziele, Modul-/Lektionstitel (L-02), Kennzahlen nur aus `display.card_elements` | SEO-044 |
| LK-20 | Titel/Referer privater Seiten | Name/Punktzahl in Verlauf, Logs, Referer | SG-08; `Referrer-Policy: no-referrer` auf S-T (R0-21 H-T) | SEO-045 |
| LK-21 | Verifikationsseite | Name, E-Mail, Token-Liste | S-T, E-97, kein Schema, kein Verzeichnis (L-16) | SEO-046 |
| LK-22 | Sprachversionen | Hinweis auf unveröffentlichte Übersetzung | kein `hreflang`, keine Querverweise auf Entwürfe | SEO-047 |
| LK-23 | Eigene REST-Antworten `course-captain/v1` | Indexierung von JSON | `X-Robots-Tag: noindex, nofollow` für alle Statuscodes (wie H-3) | SEO-048 |
| LK-24 | XML-RPC `wp.getPosts` | CPT-Inhalte | Capability-Mapping (R0-07); ggf. Härtungsmodul (E-10) | SEO-049 |
| LK-25 | `robots.txt` | Disallow verdeckt `noindex` bzw. verrät Pfade | SG-07: CC schreibt nichts | SEO-050 |
| LK-26 | Domain-Events/Exporte an Dritte | – | nicht Teil von R0-33 (R0-19/R0-34) | – |

---

## 5. Testfälle (Ziel R1-37)

### 5.1 Fixtures (ergänzt R0-07 §6)

Vorhanden: Kurse **C-A**, **C-B** (veröffentlicht), **C-C** (Entwurf), **C-D** (archiviert); Nutzer **Gast**, **U-L1** (Grant C-A), **U-L3** (Teilumfang), **U-X** (ohne Bezug), **U-EDT**; Verifikationstoken **VT-1**. Neu: **C-U** (veröffentlicht, `listing = unlisted`), **C-N** (veröffentlicht, `seo_index = noindex`), **C-E** (EN-Version von C-A, E-155), **C-S** (C-B mit offener Staging-Kopie, E-104), Lektion **LA-1** in C-A und Frage **QA-1**, Anhang **MA-1** (geschützt) und **MA-2** (öffentliches Kursbild). Jedes private Objekt enthält einen Canary-Marker (`CANARY-LA1`, `CANARY-QA1`, `CANARY-STAGE`, `CANARY-MA1`, `CANARY-NAME`). Plugin-Profile aus R0-20 §4: **KP-03** (Yoast), **KP-04** (Rank Math), **KP-05** (ohne SEO-Plugin).

Ebene: **PU** PHPUnit ohne WP, **PI** PHPUnit mit WP-Testsuite, **PW** Playwright, **H** HTML-/Header-/Sitemap-Probe per HTTP (Parser zählt Tags).

### 5.2 Robots, Canonical, Status

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel-Task | weitere |
|---|---|---|---|---|---|---|
| SEO-001 | P | Gast ruft C-A-Kursdetail auf (KP-03, KP-04, KP-05) | 200; kein CC-`noindex`; genau ein `<link rel="canonical">` auf `/kurs/<slug>/`; kein `X-Robots-Tag` | H | R1-37 | KOMP-060…062 |
| SEO-002 | P | `general.catalog_indexing = noindex`, C-A Override `index`; Katalogseite und C-A abrufen | C-A indexierbar, Katalog `noindex, follow` | H | R1-37 | SET-034 |
| SEO-003 | N | C-N mit Yoast-Feld „index“; C-A mit Yoast-Feld „noindex“ und CC `index` | C-N `noindex` (CC einschränkend); C-A `noindex` (Plugin einschränkend, SG-03); Editor zeigt Hinweis | H, PW | R1-37 | KP-03 |
| SEO-004 | N | `blog_public = 0` | alle CC-Seiten `noindex`; keine Kurse in Sitemap; Systemstatus meldet es | H | R1-37 | – |
| SEO-005 | P | C-U per Direktlink als Gast | 200, `noindex, follow`, nicht im Katalog-Block | H, PW | R1-37 | D33-3 |
| SEO-006 | N | Gast/U-X: `?preview=true` für C-C und C-S | 404 ohne Canary; U-EDT: 200 `noindex, nofollow`, `CANARY-STAGE` nur für U-EDT | H | R1-37 | R1-27 |
| SEO-007 | N | C-D (archiviert) als Gast und als U-L1 | 404 (D33-4); nicht in Sitemap; U-L1 erreicht Ergebnisse über `/lernen/` | H | R1-37 | E-108 |
| SEO-008 | P | Kurs-Slug ändern, alten Slug abrufen | 301 auf neuen Slug; Canonical und Sitemap nur neu | H | R1-37 | E-220, SET-030 |
| SEO-009 | P | Alle S-P-Routen (PT-04, PT-13…PT-22) als Gast, U-L1, U-L3, U-X; Profile KP-03/04/05 | jede Antwort `noindex, nofollow` als Meta **und** Header; kein Canonical; kein `og:*`; Titel ohne Namen/Zahlen | H | R1-37 | KOMP-060 |
| SEO-015 | P | PT-24 `/lernen/nachweis/VT-1`, R-42, `/gruppe/<token>`, Abmeldelink | `noindex, nofollow` (Meta+Header), `Referrer-Policy: no-referrer`, kein JSON-LD, kein OG | H | R1-37 | R0-21 H-T |
| SEO-016 | P | Zertifikat-PDF, `/cc-file/MA-1/` als Berechtigter | `X-Robots-Tag: noindex, nofollow`, `no-store` | H | R1-37 | R1-14 |
| SEO-017 | P | Katalogseite mit `?course_captain_lang=en` bzw. Suchparameter; `…_page=2` | gefiltert: `noindex, follow`, Canonical auf Katalogseite; Seite 2: indexierbar, Canonical selbst | H | R1-37 | – |
| SEO-018 | P | Plain-Permalinks (D-20-08) | Kursdetail unter `?cc_course=<slug>` mit Kern-Canonical; Lernbereich-Query-Fallback S-P | H | R1-37 | R1-16 |
| SEO-019 | P | Betreiber wählt eigene WP-Seite als Lernenden-Startseite (K43-03) mit Yoast „index“ | `noindex, nofollow` (SG-03), kein OG | H | R1-37 | KP-03 |

### 5.3 Sitemaps

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel-Task | weitere |
|---|---|---|---|---|---|---|
| SEO-010 | P | KP-05: `/wp-sitemap.xml` und Untersitemaps abrufen | nur C-A, C-B, C-E (+ Katalogseite); kein C-C/C-D/C-N/C-U, keine `cc_module`/`cc_lesson`/`cc_tip`/`cc_faq`, keine CC-Taxonomie | H | R1-37 | KOMP-062 |
| SEO-011 | P | KP-05: Staging-Änderung an C-S speichern, nicht veröffentlichen | `lastmod` von C-B unverändert; nach Veröffentlichen neu | PI | R1-37 | E-104 |
| SEO-012 | N | KP-05: Core-Sitemaps durch Filter abgeschaltet | CC registriert keine eigene Sitemap | PI | R1-37 | – |
| SEO-013 | P | KP-03: Yoast-Sitemap-Index, `cc_course-sitemap.xml`, Bild-Einträge | wie SEO-010; kein Bild MA-1; MA-2 erlaubt; keine Sitemap für CC-Typen außer `cc_course` | H | R1-37 | KOMP-060 |
| SEO-014 | P | KP-04: Rank-Math-Sitemaps | wie SEO-013 | H | R1-37 | KOMP-061 |

### 5.4 Meta, Open Graph, Schema

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel-Task | weitere |
|---|---|---|---|---|---|---|
| SEO-020 | P | KP-03/KP-04: C-A-Kursdetail | genau 1 Canonical, 1 Description, 1 `og:title`, 1 `og:url`; kein von CC ausgegebenes Meta/OG | H | R1-37 | KOMP-060/061 |
| SEO-021 | P | KP-05: C-A-Kursdetail | genau 1 Description, 1 `og:title`, `og:url` = Canonical, `og:locale` aus Kurssprache | H | R1-37 | KOMP-062 |
| SEO-022 | N | KP-05: Kursbild = MA-1 (geschützt) | kein `og:image`, kein `image` im Schema, `twitter:card=summary` | H | R1-37 | E-65 |
| SEO-023 | N | KP-05 + Jetpack-OG aktiv | CC gibt kein OG aus; genau ein Satz OG-Tags | H | R1-37 | §3.1 |
| SEO-024 | N | KP-03: `/wp-json/yoast/v1/get_head?url=<Lernbereich-/Ergebnis-URL>`; REST `/wp/v2/cc_lesson/LA-1` mit `yoast_head`; KP-04 analog `rankmath/v1/getHead` (wenn aktiviert) | Kopf mit `noindex`, ohne Canonical/OG/Canary; keine `yoast_head`-Felder für nicht öffentliche Typen | H | R1-37 | KP-03, KP-04 |
| SEO-025 | P | `structured_data = course`, KP-05: C-A | genau ein JSON-LD-Block; `@graph` mit genau einem `Course` (+ `BreadcrumbList`), Felder nur aus §2.1, gültig gegen schema.org | H, PU | R1-37 | – |
| SEO-026 | P | `structured_data = course`, KP-03 | kein zweiter `<script type="application/ld+json">`; Yoast-Graph enthält genau ein `Course`, `provider` als `@id`-Referenz, keine zweite `Organization` | H | R1-37 | KOMP-060 |
| SEO-027 | P | `structured_data = course`, KP-04; zusätzlich Rank-Math-Schema „Course“ manuell am Beitrag | ohne manuelles Schema genau ein CC-`Course`; mit manuellem keines von CC | H | R1-37 | KOMP-061 |
| SEO-028 | N | `structured_data = course`, Modus `other` (AIOSEO) bzw. Yoast ohne Schema-API | kein CC-JSON-LD; Systemstatus-Hinweis | H | R1-37 | KOMP-063 |
| SEO-029 | N | Filter `course_captain_seo_schema_course` fügt `aggregateRating`, `offers`, `totalHistoricalEnrollment` hinzu; Lernziel enthält `</script><script>` | verbotene Schlüssel entfernt; Ausgabe mit `<`, kein Skript ausgeführt | PU, H | R1-37 | R0-17 XSS |
| SEO-051 | N | `structured_data = off` (Default nach D33-2), alle Profile | kein CC-JSON-LD auf irgendeiner Seite | H | R1-37 | – |
| SEO-052 | P | C-A ohne Dauer, ohne Lernziele, Trainer nicht freigegeben | kein `courseWorkload`, kein `teaches`, kein `instructor`; `hasCourseInstance` mit `courseMode=online` | PU | R1-37 | D33-6 |

### 5.5 Leak-Kanäle

| ID | P/N | Gegeben / Aktion | Erwartung | Ebene | Ziel-Task | weitere |
|---|---|---|---|---|---|---|
| SEO-030 | N | Gast/U-X: `?s=CANARY-LA1`, `?s=<Titel C-U>` | keine Treffer; C-U nicht in Suche | H | R1-37 | LK-01 |
| SEO-031 | N | Gast: `/wp/v2/search?search=CANARY-LA1`, `…&subtype=cc_lesson` | leer bzw. `rest_invalid_param`; kein C-U | H | R1-37 | LK-02 |
| SEO-032 | N | Gast/U-X: `/wp/v2/cc_lesson`, `/wp/v2/cc_lesson/LA-1`, `?_embed`, `?rest_route=/wp/v2/cc_module`, `context=edit` | kein Canary, kein `content`/`excerpt`, kein Staging-Titel; `context=edit` 401/403 | H | R1-37 | RBAC-015, R1-28 |
| SEO-033 | N | U-X: `/wp/v2/cc_lesson/LA-1/revisions`, `/autosaves` | 401/403/404, kein Canary | H | R1-37 | R0-07 §4.4 |
| SEO-034 | N | Gast: `/wp/v2/cc_course?per_page=100&status=any` | nur gelistete veröffentlichte Kurse; kein C-C/C-D/C-U; keine internen Meta-Felder | H | R1-37 | LK-05 |
| SEO-035 | N | Gast: `/wp/v2/media/MA-1`, `?attachment_id=MA-1`, Attachment-Seite | 403/404, kein Pfad, kein Canary | H | R1-37 | RBAC-069 |
| SEO-036 | N | Synchronisiertes Muster mit Gate-Block um `CANARY-LA1`; Gast `/wp/v2/blocks`, Seite mit Muster | kein Canary in REST/HTML; Seite H-X | H | R1-37 | CON-033 |
| SEO-037 | N | S-P-Seite als U-L1 | kein `rel="alternate" type="application/json"` auf CC-Objekte | H | R1-37 | LK-08 |
| SEO-038 | N/P | `/oembed/1.0/embed?url=<Lektions-/Lernbereich-URL>`; `?url=<C-A>`; `/kurs/<C-A>/embed/` | Lektion/Lernbereich 404; C-A nur Titel, Kurzbeschreibung, MA-2; ohne `author_name` (D33-6 aus); Embed `noindex` | H | R1-37 | LK-09 |
| SEO-039 | N | `/feed/`, `/kurs/feed/`, `?post_type=cc_lesson&feed=rss2`, Kommentar-Feed C-A; Gate-Beitrag mit `CANARY-LA1` im Hauptfeed; Fremdfilter schreibt Inhalt zurück | kein Canary; CC-Feeds 404 bzw. leer; Gate-Beitrag nur Teaser | H, PI | R1-37 | LK-10 |
| SEO-040 | P | Für jeden Fixture-Kurs `course_indexable` gegen robots, Sitemap, Schema, Such-/REST-Filter vergleichen | alle Kanäle stimmen überein (§1.3) | PI | R1-37 | – |
| SEO-041 | N | Gate-Beitrag ohne manuellen Auszug; Theme ruft `the_excerpt()`; Yoast-Description `%%excerpt%%` | neutraler Hinweis, kein Canary | H | R1-37 | LK-14 |
| SEO-042 | N | Relevanssi mit `cc_lesson` im Index konfiguriert; Gast sucht `CANARY-LA1` | nach D33-7: kein Treffer bzw. Systemstatus-Warnung „Suchindex enthält Course-Captain-Inhalte“ | H, PI | R1-37 | R1-30 |
| SEO-043 | N | Gast: `?p=LA-1`, `?cc_lesson=<slug>`, `/kurs/<falscher-slug-ähnlich-LA-1>/` | 404 ohne `Location`-Header auf CC-Objekte (kein Slug-Orakel) | H | R1-37 | P-08 |
| SEO-044 | N | Gast: C-A-Kursdetail-HTML (inkl. versteckter Elemente, `data-*`, eingebettetes JSON) | kein `CANARY-LA1/QA1/STAGE/NAME`, keine Teilnehmerzahl; nur Modul-/Lektionstitel der veröffentlichten Revision | H | R1-37 | LK-19 |
| SEO-045 | N | U-L1 öffnet Ergebnis (PT-21); externer Link auf der Seite | `<title>` ohne Punktzahl/Namen; Referer an Dritte ohne Pfad (Browser-Standard `strict-origin-when-cross-origin` bleibt nicht schwächer) | PW | R1-37 | SG-08 |
| SEO-046 | N | VT-1 mit Policy `learner_opt_in`, nicht freigegeben; unbekannter Token | nur Status/Aussteller/Kurs/Datum/Initialen; kein `CANARY-NAME`; unbekannt → „nicht gefunden“, `noindex` | H | R1-37 | RBAC-061, SET-091 |
| SEO-047 | N | C-E als Entwurf, C-A veröffentlicht | kein `hreflang`, kein Link/Hinweis auf C-E im HTML von C-A | H | R1-37 | R0-25 Ü-4 |
| SEO-048 | P | `course-captain/v1`-Antworten 200/401/403/404/429 | jeweils `X-Robots-Tag: noindex, nofollow` | H | R1-37 | R0-21 H-3 |
| SEO-049 | N | U-X per XML-RPC `wp.getPosts` mit `post_type=cc_lesson` | Fehler bzw. leer, kein Canary | PI | R1-37 | E-10 |
| SEO-050 | P | Plugin aktivieren, `/robots.txt` abrufen (virtuell) | unverändert gegenüber Zustand ohne CC | H | R1-37 | SG-07 |

---

## 6. Entscheidungsbedarf (neu, geprüft gegen E-01…E-224)

Keine dieser Fragen ist in `ENTSCHEIDUNGEN.md` (E-01…E-224, Beschlussprotokoll E-01…E-20) oder in D-20-xx/D27-x schon beantwortet. Vorschlag für die Übernahme: **E-225…E-231**.

| ID | Frage | Optionen | Empfehlung | Begründung | Blockiert |
|---|---|---|---|---|---|
| **D33-1** | Gibt es in R1 **öffentliche, indexierbare Lektionen** (Gast ohne Anmeldung)? | a) **nein**: Teilumfang (E-15) nur angemeldet; öffentlicher Teaser = Kursdetail (Beschreibung, Lernziele, Curriculum-Titel) · b) Kennzeichen „öffentliche Vorschau“ je Lektion mit eigener URL unter `/kurs/<slug>/vorschau/<lektion>/` (S-I) · c) Vorschau-Lektion nur eingebettet in die Kursdetailseite | **a)**, b)/c) frühestens R2 | E-197/D-20-11 sehen keine Lektions-URL vor; jede öffentliche Lektionsroute ist ein neuer Leckweg (RBAC-015) und widerspricht der kleinen Kollisionsfläche | R1-04, R1-14, R1-37 |
| **D33-2** | Default der strukturierten Daten (`general.structured_data`) | a) **aus**, im Setup-Assistenten und in den Einstellungen einschaltbar · b) an, wenn Yoast/Rank Math mit Schema-API oder kein SEO-Plugin aktiv | **a)** | Kap. 21: „nur kompatibel, soweit vom Betreiber aktiviert“; ohne `offers` ohnehin keine Rich Results (§2.3) | R1-31, R1-37 |
| **D33-3** | Kurs-Sichtbarkeit „nicht gelistet“ (Konzept Kap. 7 „Sichtbarkeit“) in R1? | a) **ja**: Kursfeld `listing = listed / unlisted`, Default `listed`; unlisted = S-N, nicht im Katalog, nicht in Suche/REST-Liste/Sitemap · b) nein, nur `seo_index` | **a)** | Häufiger Bedarf (Firmenkurse per Link), heute nur über `noindex` lösbar, das den Kurs trotzdem im Katalog und in `/wp/v2/cc_course` zeigt | R1-04, R1-31, R1-37 |
| **D33-4** | Antwort der öffentlichen URL eines **archivierten** Kurses | a) **404** wie M-01, Sitemap-Ausschluss und Purge · b) 410 Gone · c) 301 auf die Katalogseite | **a)** | konsistent mit P-08/M-01; b) wäre ein eigener Status nur für SEO, c) ist für Suchmaschinen ein Soft-404 | R1-37, R1-27 |
| **D33-5** | Öffentliche Archivseiten für Kurskategorien/-schlagwörter (Konzept Kap. 7 „Tags/Kategorien“) | a) **nein in R1**: Taxonomien nicht öffentlich, Katalogfilter per Parameter (PT-03) · b) ja, mit eigener Sitemap und Canonical | **a)** | vermeidet Duplicate Content zur Katalogseite und eine zweite Listenlogik für `listing`; b) später möglich ohne Datenmigration | R1-04, R1-37 |
| **D33-6** | Name des **Trainers/Autors** auf der öffentlichen Kursdetailseite, in oEmbed (`author_name`) und Schema (`instructor`) | a) **Standard aus, je Kurs einschaltbar** (Kursfeld `show_instructor`) · b) immer an (WP-Autor) · c) nie | **a)** | Trainername ist Personendatum; Parallele zu E-97; Schema nur bei sichtbarem Namen (SG-06) | R1-04, R1-37, R0-31 |
| **D33-7** | Fremde Suchindex-Plugins (Relevanssi, SearchWP, ElasticPress, Jetpack Search) | a) **CC schließt seine nicht öffentlichen Typen über deren dokumentierte Filter aus (v); Relevanssi „getestet“, übrige „best effort“; Systemstatus warnt, wenn CC-Typen im Index konfiguriert sind** · b) nur Doku · c) alle vier „zugesichert“ | **a)** | Leckweg mit hohem Schaden (Volltext in fremder Tabelle), aber kommerzielle Indexer sind in CI nicht dauerhaft testbar (vgl. D-20-07) | R1-30, R1-37 |

---

## 7. Deltas zu anderen R0-Dokumenten (nicht in diesem Task geändert)

| Dokument | Delta |
|---|---|
| R0-27 | neue Register-Zeilen `general.structured_data` (D33-2); Kursfelder `listing` (D33-3), `show_instructor` (D33-6) mit Tests; SET-034 um SEO-002/SEO-005 ergänzen |
| R0-20 §2.7 | neue Zeile KOMP-064 „Suchindex-Plugins“ (D33-7) und KOMP-065 „Jetpack Open Graph“; KOMP-060/061 um REST-Kopf (SEO-024) und Bild-Sitemap (SEO-013) ergänzen |
| R0-07 | RBAC-015 auf SEO-030…039 verweisen; M-01 um `listing = unlisted` (Einzelabruf ja, Listen nein) |
| R0-06 | Kurs-Meta `_cc_listing`, `_cc_seo_index`, `_cc_show_instructor`; Taxonomie-Flags nach §1.4 |
| R0-19 | öffentliche Filter `course_captain_seo_indexable` (nur einschränkend), `course_captain_seo_schema_course`, `course_captain_seo_provider`, `course_captain_seo_fallback_og`; `course_captain_get_course_v1(scope='public')` liefert nicht gelistete Kurse nur bei Abruf per UID, nicht in Listen |
| R0-11 | L-02 um Trainer-Anzeige (D33-6) und Hinweis „Einschreibung geschlossen“ (E-108); F4 Setup um Frage „Strukturierte Daten“ (D33-2) |
| R0-12 | Gate-Katalog um SEO-001…052 (Ziel R1-37) |

---

## 8. Nachweis

- **Gelesen:** TODO R0-33/R1-37; `konzept.md` Kap. 21, 44 (und Kap. 7 Z. 70, 23.2, 43.3 Z. 1314, 46.2); `ENTSCHEIDUNGEN.md` Beschlussprotokoll und E-01…E-224 (gezielt auf SEO, Sichtbarkeit, Slugs, Verifikation, Sprachen, Medien, Rückzug geprüft); R0-20 §0, §2.4, §2.7, §3.1, §5; R0-27 Register-Zeilen K43-01…09, K43-35, SET-030/031/034/091; R0-21 §1, §3.5, CON-031…047; R0-25 Ü-4, Ü-6; R0-07 §2–4.4, §6, RBAC-015/061/069; R0-11 L-01…L-19, N-04/N-05; learnboating `9f37c77` (Dateien wie oben, nur Verhalten); Form Captain `97cbe73` §6.2; Review Captain `33d1362` §18.
- **Zählung** der Kernzahlen per `grep -c` auf Tabellenzeilen dieser Datei (PT-01…27, LK-01…26, SEO-001…052, D33-1…7).
- **Nicht erfolgt:** keine Änderung an anderen Dateien, keine Implementierung, kein Testlauf, kein Commit. Fremde Hook-Namen (v) sind ungeprüft.
