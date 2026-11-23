# R1-28 · Öffentliche API-/Block-/Shortcode-Verträge – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-28):** Versionierung, Permissions, Fehlerformate, Sanitization, Deprecation- und Contract-Tests für freigegebene Einstiegspunkte.
**Quellen:** R0-19 §1 (Versionierung, Präfixe E-285, Stabilität, Deprecation §1.5, Handshake §1.6), §2.1 (REST-Konventionen), §3.1 (Fehlerformat), §4.5/§4.6 (Shortcodes, Blöcke), §9 (Contract-Test-Anker CT-01…CT-05). Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Rest/RestContract.php` | `rest_post_dispatch` für `course-captain/v1`. **Request-ID:** `X-Request-Id: req_<32 hex>` auf jeder Antwort. **Fehlerkörper nach §3.1:** `code`, `message`, `data.status`, `type` (`urn:course-captain:problem:…`), `title`, `status`, `detail`, `request_id`, `reason`, `retry_after` (+ Header `Retry-After`), `errors`, `details`. Eigene Codes liefern übersetzte Katalogtexte ohne technische Details; Kern-`rest_forbidden`/`rest_not_logged_in` bei 401 → `ccap_auth_required`. **Deprecation-Header** `Deprecation`, `Sunset`, `Link` je Route über `RestContract::deprecate()` |
| `src/Access/ContentProtection.php` | Shortcode nach E-285 `[ccap_protected]`; `[course_captain_protected]` bleibt als veralteter Alias mit `_deprecated_function` |
| `blocks/*/block.json` | `version` 1.0.0 (V-07) |
| `docs/api/routes.v1.json`, `docs/api/hooks.v1.json` | Vertragssnapshots (CT-01, CT-05) |
| `docs/API.md`, `docs/API-CHANGELOG.md` | API-Referenz und Changelog (§1.3, §1.5) |
| `tests/integration/ApiContractTest.php` | 8 Contract-Tests; `ContentGateTest` auf den neuen Shortcode umgestellt |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **88** · Integration **216** · Multisite **225** · Free-Guard 0 · Textdomain-Lint 0.

## 3. Tests

| Anker | Test |
|---|---|
| CT-01 Routensnapshot | `test_CT_01_route_snapshot`: Pfad, Methoden, Schema-Parameter, Öffentlich-Kennzeichen gegen `docs/api/routes.v1.json`; jede Route hat `permission_callback`; `__return_true` nur für dokumentierte Ö-Routen (R-42 Verifikation) |
| CT-02 Permissions | `test_CT_02_03_anonymous_and_error_format`: **jede** private Route und Methode anonym → 401 `ccap_auth_required` (> 20 Kombinationen); `test_CT_02_foreign_is_404…`: fremd → 404 |
| CT-03 Fehlerformat | alle Pflichtfelder, `type`-URN, `X-Request-Id` = `request_id`, `no-store`, keine SQL/Pfade im Text; 429 mit `retry_after` und `details.bucket` |
| §1.5 Deprecation | `test_route_deprecation_headers`; Shortcode-Alias mit `setExpectedDeprecated` |
| CT-05 Hooks | `test_CT_05_hook_snapshot`: Hooknamen und Art aus dem Quelltext gegen `docs/api/hooks.v1.json` |
| V-07/V-08 | `test_shortcodes_and_blocks`: Präfix `ccap_`, Blöcke `apiVersion` 3, Namensraum, Textdomain, `version`, registriert |
| V-01 | `test_contract_version`: Konstante = Funktion, unbekannte Feature-Schlüssel `false` |

## 4. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | Pfad-IDs als UUID (§2.1) | Lernrouten akzeptieren UID oder ID; Autorenrouten nutzen numerische IDs (intern). Umstellung vor 1.0.0, im Changelog vermerkt |
| A-2 | Idempotency-Key-Header (CT-04), `GET /status`, `course_captain_can_access` (CT-10), PHP-API `_v1`, WP-CLI | nicht in R1-28 gebaut; in `docs/API.md` §6 als geplant geführt. Fachliche Idempotenz besteht über `request_key`/`idempotency_key` der Dienste |
| A-3 | JSON-Schema je Route mit `x-cc-stability`, Verwerfen unbekannter Felder | Sanitization liegt in den Diensten (Allowlists, Typprüfungen, Prepared Statements); Schema-Parameter nur, wo vorhanden. Vollständige Route-Schemas sind ein Folgeschritt |
| A-4 | Reihenfolge Pflichtparameter vor Berechtigung | WordPress-Kernverhalten: fehlende Pflichtparameter → 400 vor 401; dokumentiert in `docs/API.md` §3 |
| A-5 | `ccap_maintenance`, `ccap_db_busy` | kommen mit R1-40 (Migration) bzw. R1-42 |
| A-6 | Stabilitätsstufen | stabil ist nur `course_captain_loaded_v1`; übrige Hooks experimentell/intern bis zur Freigabe |

Review durch zweite Person vor Merge (AG-05).
