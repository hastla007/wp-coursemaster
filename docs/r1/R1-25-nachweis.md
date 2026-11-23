# R1-25 · Cache- und Zustandskonsistenz – Nachweis

**Stand:** 16.11.2026 · **Branch:** `claude/fervent-sagan-r6es1v`
**DoD (TODO R1-25):** Grant-/Refund-/Kursrevisionen invalidieren Cache sicher; private Antworten nie öffentlich zwischengespeichert; Paralleltests grün.
**Quellen:** R0-21 §2.6 T-5 (After-Commit), §3.1–§3.7 (Klassen K0…K4, Request-Memo, Objektcache, HTTP H-X/H-3/H-7, Invalidierungsmatrix), §5.4/§5.5 (CON-020…039). Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Cache/CacheStore.php` | Objektcache für K1/K1g/K2 in der Gruppe `course_captain`. Schlüssel `cc:v<schema>:<blog>:<klasse>:<scope>:<id>:g<gen…>`. Generationen sind Zufallstoken: `content_gen` mit Option `course_captain_cache_generation`, `course_gen` mit Post-Meta `_ccap_cache_gen`, `user_gen` nur im Cache. Ein fehlender Token ergibt einen Fehltreffer. Das Leseprotokoll liest zuerst die Generationen und schreibt unter diesem Schlüssel. TTL K1/K1g 86 400 s, K2 ≤ 300 s bzw. Berichte ≤ 900 s, ±10 % Jitter. K2 nur mit Nutzer-Scope (Builder lehnt sonst ab). Bumps laufen nach dem Commit, beim Kurs-Bump plus `clean_post_cache` und Aktion `course_captain_cache_purge` (H-7) |
| `src/Storage/Database.php` | `after_commit()`: Callbacks laufen nach dem äußeren Commit und verfallen bei Rollback. **Transaktionszustand je Verbindung statt je Wrapper:** zuvor startete ein zweiter `Database::current()`-Wrapper in einer laufenden TX eine neue TX (impliziter Commit) |
| `src/Cache/CacheInvalidation.php` | Core-Hooks (Rolle, Caps, Site-Mitgliedschaft, Löschen) → `user_gen`. H-X-Header (`no-store, private`, `X-LiteSpeed-Cache-Control`, `X-Accel-Expires: 0`, `Surrogate-Control`, `CDN-Cache-Control`) auf **allen** Statuscodes von `course-captain/v1` und `wp/v2/ccap_*` (H-3) |
| `src/Event/Outbox.php` | jedes Ereignis mit `user_id` (Grant/Refund/Widerruf, Seat, Prüfung, Abschluss) bumpt `user_gen` nach dem Commit |
| `src/Course/CoursePublisher.php`, `CourseRelations.php` | Revision veröffentlicht/archiviert → `course_gen` + Purge; `contains` geändert → Bundle und enthaltener Kurs |
| `src/Question/QuestionRepository.php` | Fragenrevision als K1 (Entwürfe nie); Veröffentlichung entfernt die K1-Einträge der Frage (Statuswechsel) und bumpt `content_gen` |
| `src/Settings/Settings.php`, `src/Privacy/PrivacyService.php` | Settings-Änderung → `content_gen` (Zufallstoken statt nicht atomarem Zähler); Eraser → `user_gen` |
| `src/Kernel/UninstallManifest.php` | Post-Meta `_ccap_cache_gen` |
| `tests/integration/CacheTest.php` | 9 Tests |

## 2. Ergebnisse (16.11.2026)

phpcs 0 · PHPStan L8 0 · Unit **85** · Integration **176 / 1 759** · Multisite **185 / 1 812** · Free-Guard 0 · Textdomain-Lint 0.

## 3. Abdeckung

| Test-ID | Test |
|---|---|
| CON-020/021/051/052 (K3 nie gecacht) | Zugangsentscheidungen liegen nur im Request-Memo (R1-03, `Invalidation`); kein `wp_cache_*` in Policy/Gate (Code-Review dieses Commits). Laufend geprüft durch `test_GRANT_027_revoked_during_exam`, ContentGate-Tests |
| CON-022/025 (Rolle, `set_current_user`) | R1-03-Memo-Tests; zusätzlich `user_gen`-Bump auf Rollen-Hooks (`test_invalidation_matrix`) |
| CON-023 (Kursrevision) | `test_invalidation_matrix` (`course_gen`), `test_question_revision_k1` (alte K1 bleibt inhaltlich gültig, Status aktuell) |
| CON-024 Set-after-Invalidate | `test_CON_024_set_after_invalidate` |
| CON-026 Multisite | `test_CON_026_site_in_key` |
| CON-027 verdrängte Generation | `test_CON_027_evicted_generation` |
| CON-028 Nutzer-Scope | `test_CON_028_user_scope` |
| T-5 After-Commit/Rollback | `test_bump_after_commit_only` |
| CON-034/H-3 Fehler mit `no-store` | `test_CON_034_headers_on_errors` |
| CON-047/H-7 Purge | `test_invalidation_matrix` |

## 4. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | `course_gen` als Post-Meta statt Option | keine Optionszeile je Kurs (`alloptions`), gleiche Wirkung |
| A-2 | Art P/H/E (CON-029 FLUSHALL unter Last, CON-031…039 echter Page-Cache/Proxy, bfcache-E2E) | brauchen Docker-Proxy-Stack bzw. Mehrprozess-Runner → R1-42 (Paralleltests) und R1-30 (Interop-Matrix). Auf Anwendungsebene: H-X-Header für alle Statuscodes, `DONOTCACHEPAGE` für geschützte Seiten (R1-14) |
| A-3 | DoD „Paralleltests grün“ | logische Races über Sperren/CAS (R1-08/R1-13/R1-20) und Set-after-Invalidate getestet; echter Mehrprozess-Lauf mit R1-42 |
| A-4 | H-6 fail-closed bei später Erkennung, `late_gate_detected` | Shortcode/Block setzen bereits `DONOTCACHEPAGE`; Teaser-Erzwingung nach gesendeten Headern mit R1-30 |
| A-5 | CLI `wp course-captain cache flush` | mit R1-38 (Betriebsdashboard/CLI); bis dahin wirkt `bump_content()` |
| A-6 | Lernsperre (E-79) | noch kein Schreibpfad; Bump wird beim Anlegen mitgezogen |

Review durch zweite Person vor Merge (AG-05).
