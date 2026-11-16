# R1-45 · Website-Härtungsmodul – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-45):** Login-/Reset-/Registrierungslimit, bekanntes Gerät, XML-RPC, 4 Security-Header; Erkennung von Security-Plugins (`auto`), Notausschalter; Spezifikation R0-31 §3.4, R0-30 Tab „Website-Härtung“; Tests PSD-xxx/ADM-xxx.
**Quellen:** R0-31 §3.4 (HM-0…HM-8, Merkmale, Grenzen, Konfliktmatrix), §3.5 (NA-1…NA-4), §5 (PSD-039…056, PSD-065); Beschlüsse E-10, E-239…E-242, E-260…E-263, E-307…E-309. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Security/Hardening.php` | **Zustand je Merkmal** (`login_limit`, `reset_limit`, `registration_limit`, `xmlrpc`, `site_headers`): `off:kill_switch` (Konstante `COURSE_CAPTAIN_HARDENING = off`), `off:unconfirmed` (Tab noch nie gespeichert, HM-0), `off`, `off:not_needed` (Registrierung geschlossen), `held:<slug>` (Konflikt im Modus `auto`), `active`. **HM-2:** Hooks nur für aktive Merkmale; Neubewertung bei Plugin-/Theme-Wechsel; Übergänge auditiert (`hardening_state_changed`), Notausschalter einmalig (`hardening_kill_switch_detected`). **Erkennung:** Wordfence, Solid Security, LLAR, Loginizer, AIOS, WP Cerber, Shield, Jetpack/Protect, Multisite-Unterseite; Filter `course_captain_hardening_conflicts` für die Matrix R1-30. **Login-Limit** nach Kern-Authentifizierung über `wp_authenticate` (alle Wege): 10/IP, 5/Konto+IP, 30/Konto in 15 min; neutrale Meldung; Zählung nur bei fehlgeschlagener Erstfaktor-Prüfung (`wp_login_failed`). **Gerätecookie** (`HttpOnly`, `Secure` bei HTTPS, `SameSite=Lax`, 180 d, HMAC an Passwort-Hash gebunden) umgeht nur IP- und Konto+IP-Sperre. **Fail-closed:** Zählerausfall → 503 `ccap_temporarily_unavailable`, außer bekanntes Gerät. **Reset-Limit** 10/IP, 3/Konto+IP, 10/Konto je h vor dem Mailversand. **Registrierung** 10/IP je h. **XML-RPC:** authentifizierte Methoden aus, `system.multicall`/Pingbacks entfernt, kein `X-Pingback`. **Header:** `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options` nur, wenn noch nicht gesetzt (E-307); `Permissions-Policy` nur auf eigenen REST-Antworten (E-309); kein HSTS/CSP. **Datensparsam:** Zähler als gesalzener HMAC (Präfix `h`) in `ccap_rate_limit`, keine IP/Logins im Klartext, keine IP im Audit. **CLI** `wp course-captain hardening status|disable|unlock`; Site-Health-Test |
| `src/Ops/AuditView.php` | `hardening_state_changed`, `hardening_kill_switch_detected`, `maintenance_action` |
| `src/Kernel/UninstallManifest.php` | Option `course_captain_hardening_state` |
| `tests/integration/HardeningTest.php` | 11 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **90** · Integration **266** · Multisite **276** · Free-Guard 0 · Textdomain-Lint 0.

## 3. Tests

| PSD | Test |
|---|---|
| PSD-065 (HM-0/HM-2) | `test_PSD_065_inactive_until_confirmed` |
| PSD-039/040, HM-7 | `test_PSD_039_040_login_limits` (Konto+IP, IP, Fensterende, neutrale Meldung) |
| PSD-046 | `test_PSD_046_known_device` (Umgehung, Manipulation, Passwortwechsel) |
| PSD-044 | `test_PSD_044_fail_closed` |
| PSD-043 | `test_PSD_043_reset_limit` |
| PSD-053 | `test_PSD_053_registration` |
| PSD-047/048 | `test_PSD_047_048_conflicts` (held ohne Hooks, Audit, `force`) |
| PSD-050 | `test_PSD_050_xmlrpc` (inkl. Jetpack → `held:jetpack`) |
| PSD-051 | `test_PSD_051_site_headers` |
| PSD-045, NA-1/NA-2 | `test_PSD_045_kill_switch_and_unlock` |
| PSD-056 | `test_PSD_056_data_minimisation` |

## 4. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | `hardening.confirmed_at` als Feld | Bestätigung = Tab einmal gespeichert (`_rev > 0` der Option); kein eigenes Feld nötig |
| A-2 | Tab „Website-Härtung“ im Admin, R-Dialog bei `force`/`off`, Assistent Schritt 6 (PSD-048/049/065) | Oberfläche mit R1-32 (Setup-Assistent/Tabs); Dienste und Zustände stehen |
| A-3 | Header-Erkennung des Servers per Loopback (`held:server`) | nur PHP-seitig vorhandene Header (`headers_list`) werden erkannt; Loopback-Probe mit R1-30 |
| A-4 | PSD-041 Laufzeitgleichheit (Dummy-Passwortprüfung), PSD-042 alle Login-Wege, PSD-055 Multisite-Lauf | Kern prüft unbekannte Konten bereits mit Dummy-Hash; Messung und Wege-Matrix mit R1-30/R1-42 |
| A-5 | Zählerausfall-Log gedrosselt (NA-3) | Site Health zeigt den Zustand; Drossel-Log mit R1-38 |
| A-6 | Konfliktliste | Erkennung nur über dokumentierte Konstanten/Klassen; Pflege der Liste in R1-30 |

Review durch zweite Person vor Merge (AG-05).
