# R1-37 · Kurs-SEO und Sichtbarkeit – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-37):** Öffentliche Kursseiten SEO-kompatibel; private Prüfungen, Profile, Medien und Grants nicht indexierbar oder per API abrufbar; Yoast-Konflikttests.
**Quellen:** R0-33 §0 (SG-02 eine Zuständigkeit, SG-03 restriktivste Angabe gewinnt), §1.1–§1.3 (Klassen S-I/S-N/S-P/S-T/S-F/S-0, eine Indexierbarkeitsfunktion), §2 (Schema), §3.3–§3.5 (Yoast, Rank Math, Fallback), §4 (Leak-Kanäle LK-01…LK-25), §5 (SEO-0xx). Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Seo/CourseSeo.php` | **`indexable()`** nach §1.3: veröffentlicht, kein Passwort, gelistet (`_ccap_listing`), Kurs-Override `_ccap_seo_index` (`inherit/index/noindex`) vor `general.catalog_indexing`, `blog_public`, Filter `course_captain_seo_indexable` (nur einschränkend). Speist robots, Core-Sitemap, Yoast-/Rank-Math-Sitemap und JSON-LD. **Robots:** `wp_robots` setzt `noindex, follow` für nicht indexierbare Kurse (S-N) und `noindex, nofollow` im Lernbereich (S-P), entfernt nie ein fremdes `noindex` (SG-03). **S-P:** zusätzlich Header `X-Robots-Tag`, kein Canonical/Shortlink/REST-Discovery. **Sitemaps:** nur `ccap_course` unter den eigenen Typen; nicht indexierbare Kurse ausgeschlossen. **Suche:** Hauptsuche, `/wp/v2/search`, REST-Kursliste ohne nicht gelistete Kurse (Editoren ausgenommen). **oEmbed:** nur gelistete Kurse, nie Lektion/Modul/Tipp/FAQ. **JSON-LD-Fallback** `Course` nur ohne SEO-Plugin und nur auf indexierbaren Kursen, nur öffentliche Felder (Titel, Kurzbeschreibung, Anbieter, Sprache; keine Bewertungen, keine Inhalte). **Yoast:** `wpseo_robots`, `wpseo_canonical`, `wpseo_sitemap_exclude_post_type`, `wpseo_exclude_from_sitemap_by_post_ids`, `wpseo_frontend_presenters` (OG/Twitter/Canonical/Schema im privaten Bereich entfernt). **Rank Math:** `rank_math/frontend/robots`, `…/canonical`, `rank_math/sitemap/exclude_post_type`, `…/posts_to_exclude` |
| `src/Cache/CacheInvalidation.php` | `X-Robots-Tag: noindex, nofollow` auf allen REST-Antworten des Namespace (LK-23) |
| `src/Kernel/UninstallManifest.php` | Post-Meta `_ccap_listing`, `_ccap_seo_index` |
| `tests/integration/SeoTest.php` | 8 Tests |

Bereits vorhanden und hier mitgeprüft: S-0 für Lektion/Modul/Tipp/FAQ (`public => false`, `exclude_from_search`, keine Archive/Feeds, R1-04), geschützte Dateien mit `no-store` (R1-14), Verifikationsseite `noindex, nofollow` (R1-44), Feeds/Auszüge mit Teaser (R1-14).

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **88** · Integration **224** · Multisite **233** · Free-Guard 0 · Textdomain-Lint 0.

## 3. Tests

| Test-ID | Test |
|---|---|
| SEO-001/002/004/005 | `test_indexability` (öffentlich, nicht gelistet, Override `noindex`/`index` gegen globales `noindex`, `blog_public = 0`, Filter) |
| SEO-001/003/005, Yoast-/Rank-Math-Konflikt | `test_course_robots_and_plugin_filters`: indexierbarer Kurs unverändert; Plugin-`noindex` wird nie aufgehoben; nicht gelistet → `noindex, follow` in Kern-, Yoast- und Rank-Math-Format |
| SEO-009 | `test_private_area`: `noindex, nofollow`, kein Canonical, Plugin-Canonical `false` |
| SEO-010/013 | `test_sitemaps`: Core-Provider ohne `ccap_lesson`/`ccap_module`; Ausschluss-IDs Core/Yoast/Rank Math |
| SEO-030/031/034 | `test_search_and_rest` |
| SEO-038 | `test_oembed` |
| SEO-025 (Schema) | `test_json_ld`: keine Canary-Inhalte, kein `aggregateRating`, nicht auf `noindex`-Kursen |
| SEO-048 | `test_rest_robots_header` |

## 4. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Yoast-/Rank-Math-Tests | Plugins sind in der Testumgebung nicht installiert: getestet wird der **Filtervertrag** (unsere Callbacks auf den dokumentierten Plugin-Hooks). Lauf mit echten Plugins (KP-03/KP-04) in der Konfliktmatrix R1-30 |
| A-2 | Kurseditor „Sichtbarkeit & Suche“ (Felder `listing`/`seo_index`, Statusbegründung) | Meta ist vorhanden; Oberfläche mit R1-32/R1-33 (Kurs-Overrides) |
| A-3 | Katalogseite PT-01…PT-03 (gefilterte Katalogseiten S-N, Canonical) | Katalog-Block existiert (R1-16); Filterparameter-Robots folgen mit der Katalogseiten-Einstellung (R1-32) |
| A-4 | SEO-008 (301 nach Slug-Änderung), SEO-006 Vorschau-404, SEO-011 Staging-`lastmod` | Slug-Historie (D27-6) und Staging (R1-27) fehlen noch |
| A-5 | Breadcrumb-/`CourseInstance`-Schema, Yoast-Schema-Piece | Fallback nur `Course`; angeschlossenes Yoast-Piece folgt (E-248) |
| A-6 | LK-15 fremde Suchindexer, LK-24 XML-RPC | über Post-Typ-Registrierung (nicht öffentlich) abgedeckt; ausdrückliche Tests mit R1-30/R1-45 |

Review durch zweite Person vor Merge (AG-05).
