# R1-21 · Benachrichtigungen und E-Mail-Vorlagen – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-b`
**DoD (TODO R1-21):** Einladung, Claim, Ablauf, Abschluss, Fehler und Opt-out mit WP-Mail, Rate-Limits, Datenschutz und Test-Mail-Sink.
**Quellen:** R0-27 §3 (Benachrichtigungsvertrag E-187, §3.1–§3.11), §1.3 (`emails.types`, `mail_template.<typ>`, `user.notification_prefs`, Konstanten `COURSE_CAPTAIN_OUTBOUND`/`COURSE_CAPTAIN_MAIL_CATCH_ADDRESS`), §4.3 (NTF-001…NTF-037); R0-31 RT-30/RT-32; R0-05 GM-159, GM-160; E-85, E-86, E-88, E-112, E-124, E-136, E-215…E-218. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Notification/NotificationTypes.php` | Katalog der 16 R1-Mailtypen (N-01…N-16) mit Klasse `required`/`informational` (E-216), Betreiber-Default (N-12 aus, E-217), Variablen-Allowlist je Typ (§3.10) und Kennzeichen „verdrahtet“; Feldvertrag `emails.types` (striktes Objekt, nur informative Typen) |
| `src/Notification/NotificationService.php` | Outbox-Abonnent `notification` (über die Filter `course_captain_internal_subscribers`/`…_handlers`, wie der Abonnent `cache`); legt je Event, Typ und Empfänger höchstens einen Datensatz an (`dedupe_key` = sha256(typ:empfänger:geschäftsschlüssel), UNIQUE); Versandlauf mit CAS `queued/retry_wait → sending` und 10-min-Lease vor `wp_mail`, abgelaufene Lease → `unknown` ohne Auto-Neuversand; Neuprüfung vor jedem Versuch (Haltemodus → `held`, `off` → `suppressed (outbound_off)`, Zustand → `suppressed (state_changed)`, Empfänger fehlt/ungültig → `failed (invalid_recipient)`, Typ aus → `type_disabled`, Opt-out → `opt_out`, Ratenlimit); Retry 5 min/30 min/2 h/12 h, nach 5 Versuchen `failed`; `pre_wp_mail = false` → `short_circuit`; Stapel 50 Empfänger bzw. 20 s, Fortsetzung nach 60 s über `ccap_task_continue` (Task `notification_send`), ein fehlerhafter Empfänger bricht den Lauf nicht ab; Admin-Aktionen „erneut senden“ (`resend_of`), Einladung neu senden (max. 3, neuer Token), Testmail (eigene Adresse, 5/h), zurückgehaltene Mails freigeben/verwerfen; Stundenschritt (Lease-Ablauf, `held` nach 30 d verwerfen, Versandlauf); Site-Health-Test `course_captain_mail`; Admin-Hinweis bei `failed` (Fehler-Snooze 24 h) |
| `src/Notification/MailTemplates.php` | Werkstexte aller 16 Typen (übersetzbar, Empfänger-Locale); Vorlagen-Option `course_captain_mail_template_<typ>` = map<locale, {subject, body_html, body_text, rev}>; Speichern nur mit `ccap_manage_settings`, nur installierte Locales, Platzhalter-Allowlist (unbekannt → Feldfehler mit Liste der erlaubten), Mail-kses `p, br, strong, em, a[href], ul, ol, li, h2, h3`, Betreff ohne CR/LF ≤ 200, Audit `mail_template_saved` (Typ, Locale, Hash vorher/nachher); Auflösung exakt → gleiche Sprache → Betreiber-Standard (Site-Locale) → Werkstext; Rendern mit kontextgerechtem Escaping (`esc_html`/`esc_url`, Kurstitel in `<bdi>`), Fußzeile genau einmal, `lang`/`dir`; Vorschau mit Beispieldaten |
| `src/Notification/NotificationPrefs.php` | User-Option `course_captain_notification_prefs` (nur informative Typen); Änderung nur durch die Person (Konto) oder signierten Link, Betreiber kann nicht aufheben; HMAC-SHA256 über `user_id:type:user_registered` mit zweckgebundenem Schlüssel aus `wp_salt('auth')`; Seite `/<lernbereich>/benachrichtigungen/`: GET mit Knopf ohne Wirkung, POST (One-Click, RFC 8058) meldet ab, falsche Signatur ändert nichts; Kontoformular je Typ (Nonce); Audit `notification_optout_changed` (Typ, Kanal) |
| `src/Notification/OutboundMode.php` | `COURSE_CAPTAIN_OUTBOUND` on/catch/off (ohne Konstante `on` nur in `production`, sonst `catch`); Catch-Adresse aus `COURSE_CAPTAIN_MAIL_CATCH_ADDRESS` bzw. `admin_email`, ungültig → `off` mit Warnung; Filter `course_captain_outbound_mode` (Tests/MU-Plugin) und `course_captain_notification_hold` (Haltemodus-Anschluss für R1-23) |
| `src/Notification/TemplateError.php` | Renderfehler → `failed (template_error)`; „erneut senden“ nutzt dann den Werkstext |
| `tests/integration/NotificationTest.php` | 19 Tests (NTF-001…NTF-034 soweit R1, GM-159 Stapelteil) |

### Änderungen an gemeinsam genutzten Dateien

| Datei | Änderung |
|---|---|
| `src/Kernel/Plugin.php` | eine Zeile `\CourseCaptain\Notification\NotificationService::register();` |
| `src/Kernel/UninstallManifest.php` | Option `course_captain_mail_state`; neue Liste `USER_OPTIONS` mit `course_captain_notification_prefs` (+ `user_options()`, in `covers()` und `to_array()`); Vorlagen-Optionen standen bereits im Manifest |
| `src/Kernel/Uninstaller.php` | löscht bei bestätigter Datenlöschung je Site die User-Optionen (`<blog prefix><name>`) |
| `src/Ops/AuditView.php` | Registry: `mail_template_saved`, `notification_optout_changed`, `notification_resent`, `invitation_resent`, `notifications_released`, `notifications_discarded` |
| `src/Settings/SettingsSchema.php` | Feld `emails.types` (aus `NotificationTypes::settings_field()`); Objekttyp kennt `strict` (unbekannte Schlüssel → Validierungsfehler) |
| `src/Kernel/CronHooks.php` | im stündlichen Lauf (isolierte Schritte) ein Schritt `notifications`; `CronHooks::ALL` unverändert |
| `src/Privacy/RetentionService.php` | Schritt `RT-30_notifications` (90 d, offene/gehaltene Zeilen ausgenommen) |
| `src/Privacy/PrivacyService.php` | Exportgruppe `cc-notifications` um `created_at`, `sent_at`; neue Gruppe `cc-notification-prefs` |
| `src/Storage/Schema.php` | **keine Änderung** (Tabelle `ccap_notification` reicht; Ereigniskontext wird über `event_uuid` aus `ccap_outbox_event` gelesen) |
| `tests/integration/OutboxTest.php` | erwartete Zustellungen je manuellem Grant 3 → 4 (zusätzlicher Abonnent `notification` für `access.changed`) |

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler (1 bekannte Warnung `tests/integration/ImportTest.php:318`) · PHPStan L8 0 · Unit **85 / 2 715** · Integration **175 / 1 808** (davon `NotificationTest` 19 / 215) · Multisite **184 / 1 861** · Free-Guard 0 · Textdomain-Lint 0. `.pot` bewusst nicht aktualisiert (Vorgabe; neue Strings beim nächsten `.pot`-Lauf).

## 3. Tests

| ID | Test |
|---|---|
| NTF-001 / 007 / 008 | Einladung, Claim, Abschluss je genau eine Mail im Sink; Replay aller Events derselben ID erzeugt weder Datensatz noch Mail; Absender von WordPress geerbt; keine Adresse im Protokoll; Token erst im Versandschritt erzeugt (alter Token ungültig, Klartext nur in der Mail) |
| N-01 / NTF-010 | Zugangsmail mit `List-Unsubscribe` + One-Click-Header; Grant nach Event, vor Versand widerrufen → `suppressed (state_changed)` |
| NTF-002 | Ablaufmail Default aus (`type_disabled`); eingeschaltet genau eine Mail nach Ablauf, ohne Kauf-/Verlängerungstext |
| NTF-003 / 012 / 013 | `pre_wp_mail = false` → `retry_wait` (5 min), danach Erfolg ohne Doppelmail; fünf Fehler → `failed`; Site Health `recommended`, ab 10 `critical`; ungültige Adresse → sofort `failed (invalid_recipient)` |
| NTF-004 / 014 / 015 / 037 | Abmeldelink GET ohne Wirkung, falsche Signatur ändert nichts, POST meldet ab; Admin kann nicht zurücknehmen, Link nicht wieder anmelden, `required` nicht abwählbar; informative Mails `opt_out`, Pflichtmail geht weiter; Kontoformular ohne Pflichttypen |
| NTF-005 | Einladungen je Gruppe max(20, 2 × 10) / 24 h → `rate_limited`, keine Mail |
| NTF-006 / 016 / 017 | `staging` → `catch`; Catch an Catch-Adresse mit `[catch]` und `X-CC-Recipient-Ref`; ungültige Catch-Adresse → `off` mit Warnung; `off` → `suppressed (outbound_off)`; Haltemodus 3 × `held`, Standard verwirft (E-88), Freigabe versendet, nach 30 d `held_expired` |
| NTF-009 | Lease abgelaufen → `unknown`, kein Auto-Neuversand; „erneut senden“ (nur Admin) erzeugt neue Zeile mit `resend_of` |
| NTF-011 | Konto vor Versand gelöscht → Zeilen gelöscht, keine Mail |
| NTF-018 | Testmail nur an eigene Profiladresse; sechste in der Stunde `ccap_rate_limited` |
| NTF-019 / 020 | `de_DE`-Vorlage für `de_CH`, Betreiber-Standard für `fr_FR`, sonst Werkstext; `lang`/`dir` (`ar` → `rtl`); Locale nach Exception zurückgesetzt |
| NTF-021 / 022 / 023 | Kurstitel mit HTML, CR/LF, `Bcc:` inert (kein Zusatzheader, Betreff einzeilig); Bewertungsmail ohne Punkte/Quote; HTML- und Textteil inhaltsgleich, Text ohne Markup |
| NTF-025 | 11. informative Mail `rate_limited`, Pflichtmail ins nächste Fenster (`rate_deferred`) |
| NTF-026 / 033 / GM-159 (Stapel) | 60 Mails: Lauf 1 = 50 (49 gesendet, 1 gelöschter Empfänger `failed` ohne Abbruch), Fortsetzung geplant, Lauf 2 Rest, nichts doppelt |
| NTF-028 / SET-102 | `course_completed` abgeschaltet → `type_disabled`; Abschalten von `seat_invitation` per manipuliertem Speichern → `ccap_validation_failed`, Einladung geht |
| NTF-029 | Einladung 3 × neu gesendet, 4. → `ccap_rate_limited`; jeder Versand neuer Token, alter ungültig |
| NTF-030 / 032 | Export Typ/Status/Zeiten + Präferenzen; Eraser löscht; Retention 90 d |
| NTF-031 | Absender-Override nur für CC-Mails (echter `wp_mail` mit Test-Mailer), Textteil als `AltBody` |
| NTF-034 / SET-103 | unbekannter Platzhalter abgelehnt (mit erlaubter Liste), Locale geprüft, kses, Betreff ohne CR/LF, Audit nur bei Änderung, Vorschau mit Beispieldaten für alle 16 Typen |

## 4. Abweichungen und Annahmen

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | GM-159 „Zugangsende-Erinnerung gebündelt“ | Erinnerungen **vor** Ablauf sind Pro (R0-27 §3.2 „Pro (R3): Ablauf-Erinnerungen“, E-24; NTF-002 „keine Erinnerung davor“). **Nicht gebaut.** Der R1-Anteil (Stapel 50/20 s, Fortsetzung, ein Fehler bricht nicht ab) ist im allgemeinen Versandlauf umgesetzt und mit NTF-026 geprüft |
| A-2 | Auslöser N-12 | `access.changed` kippt beim zeitlichen Ablauf nie (Zugriff endet schon vor dem Cron-Übergang). N-12 hängt deshalb an `grant.expired`; vor Versand wird „weiterhin kein Zugang“ geprüft. **Klärung R0-27 §3.2** |
| A-3 | Geschäftsschlüssel N-01 | `object_version` von `access.changed` ist in R1-13 konstant 1; Schlüssel = `course_uid` + Event-ID (beim Replay stabil) |
| A-4 | Ursache N-01 | aus dem jüngsten Grant-Event: nur `→ active` aus neu/`pending`, nicht Wiederaufnahme, Retry, Reconcile (Grundcode enthält `reconcil`); Platz-Grants melden sich über N-03 statt zusätzlich N-01. Wird die Outbox erst nach einer Folgeänderung zugestellt, entfällt die Mail (Regel N-3 „lieber keine“) |
| A-5 | Nicht verdrahtete Typen | N-05…N-10, N-14…N-16: Events (`certificate.*`, `attempt_voided/annulled`, `grant_transferred`, `assignment.*`) existieren noch nicht. Katalog, Werkstext, Vorlage, Opt-out und `emails.types` sind vorhanden; Verdrahtung mit R1-44, R1-38, R1-46. Damit entfällt auch die Zusammenführung über `correlation_id` (V-2: N-04 + N-05, N-10 ersetzt N-01) → NTF-024, NTF-027, NTF-035…NTF-037 (Trainer-Teil) offen |
| A-6 | `held`-Erkennung | Haltemodus nach Site-Identitätswechsel wird mit R1-23 erkannt; hier Anschluss per Filter `course_captain_notification_hold`, Zustände/Aktionen/Frist fertig |
| A-7 | Ausgangsmodus `off` | `OutboxDispatcher` überspringt bei `off` bereits alle Zustellungen; dann entsteht kein Datensatz. `suppressed (outbound_off)` gilt für Zeilen, die vorher angelegt wurden (Dispatcher unverändert gelassen) |
| A-8 | Fehlertext | Es gibt noch keine Fehlertabelle (`error_log_days`); gespeichert wird nur die Fehlerklasse, kein PHPMailer-Text (N-7). Testmail meldet Status + Klasse |
| A-9 | Claim-Link | `claim_url` = Lernbereich mit `?ccap_claim=<token>`; die Annahmeseite selbst fehlt noch (R1-13/R1-16-Folge) |
| A-10 | E-136-Dialoge | „Mail abwählen“ im Grant-/Support-Dialog: Dialoge existieren noch nicht (R1-05/R1-38) |
| A-11 | Variablen N-14…N-16 | in §3.10 nicht gelistet; angenommen `course_title`, `course_url` (+ Grundvariablen) |
| A-12 | Risiko-Bestätigung beim Abschalten (`emails.types`, Spalte „R“) und Admin-UI für Vorlagen/Vorschau/Haltemodus-Liste | Dienste fertig, Oberfläche mit R1-32/R1-38 |
| A-13 | Neue Option `course_captain_mail_state` | hält den letzten `template_error` (Typ, Locale, Zeit) für Admin-Hinweis/Systemstatus; im Uninstall-Manifest |

## 5. Übergaben

R1-23 (Haltemodus-Erkennung → Filter), R1-32 (Vorlagen-/Typen-Oberfläche, Vorschau, Testmail-Knopf), R1-38 (Systemstatus: `unknown`/`failed`-Liste mit „erneut senden“, Support-Dialoge, Fehlertabelle), R1-44 (Zertifikats-Events, V-2-Sammler), R1-46 (Aufgaben-Events N-14…N-16), R1-13/R1-16 (Claim-Seite). Review durch zweite Person vor Merge (AG-05).
