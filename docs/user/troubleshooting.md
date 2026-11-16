# Fehlerbehebung und Support-Runbook

Grundlage: R0-13 (Supportstufen, Runbooks RB-SUP-1…8), R0-35 §3.5/§3.6 (Forum, Sicherheitsmeldung). Geprüft gegen: `src/Ops/HealthChecks.php`, `src/Notification/NotificationService.php`, `src/Event/OutboxDispatcher.php`, `src/Kernel/CronHooks.php`, `src/Cache/*`, `src/Storage/{Migrator,RateLimiter}.php` und die Fehlercodes in `src/`.

## 1. Grundregeln für den Support

| Nr. | Regel |
|---|---|
| GR-1 | Auskünfte zu Zugängen, Versuchen und Daten nur an die E-Mail-Adresse des Kontos. |
| GR-2 | Nie Passwörter, Zahlungsdaten oder Ausweiskopien anfordern. |
| GR-3 | Korrekturen nur über Course-Captain-Funktionen, **nie** per SQL, `wp option update` oder Tabellen-Editor. Nur so entstehen Ereignis, Protokoll und Cache-Invalidierung. |
| GR-4 | In Begründungen gehören Ticketnummer und Anlass, keine Gesundheits-, Passwort- oder Zahlungsdaten. |
| GR-5 | Dem Lernenden nur die Wirkung nennen („Zugang ist bis … aktiv“), keine internen IDs fremder Objekte, keine Daten Dritter. |
| GR-6 | Eingangsdatum notieren; Datenschutzfristen (in der Regel ein Monat, vom Betreiber zu bestätigen) beachten. |
| GR-7 | Nach Fehlercode, Uhrzeit und – sobald vorhanden – `request_id` fragen (`X-Request-Id`, kommt mit R1-28). |

**Supportstufen** (R0-13 §1): 0 Selbsthilfe (Lernende) · 1 Support (`ccap_support`: lesen, Lernsperre, Protokoll) · 2 Kursmanager (fachliche Korrekturen) · 3 Administrator (Technik, Wartung) · 4 Herstellersupport (kein Konto auf der Site, nur Fehlercodes/Versionen/Diagnose ohne Personendaten).

**Empfohlene Reaktionszeiten** (keine Zusage des Herstellers): Stufe 1 werktäglich, Prüfungsabbrüche am selben Werktag, Sicherheitsvorfälle sofort.

## 2. Fehlercodes

Antworten der REST-API `course-captain/v1` enthalten einen stabilen Code `ccap_*` und einen HTTP-Status. Ab R1-28 kommen weitere Felder hinzu: `type`, `title`, `detail`, `request_id`, `retry_after`, `errors`, `details` (siehe [entwickler.md](entwickler.md) §3). Meldungen enthalten keine Stacktraces, SQL-Texte oder Pfade.

| Code | HTTP | Bedeutung | Was tun |
|---|---|---|---|
| `ccap_auth_required` | 401 | nicht angemeldet | anmelden; bei Cookie-Auth `X-WP-Nonce` mitsenden |
| `ccap_forbidden` | 403 | Recht fehlt | Rolle/Recht prüfen (§1 in [admin.md](admin.md)) |
| `ccap_not_found` | 404 | existiert nicht **oder** kein Recht (bewusst gleich) | Kennung prüfen; Rechte prüfen |
| `ccap_content_locked` | 403 | Inhalt gesperrt; Grund `trial`, `drip`, `prerequisite` oder `exam_running` (heute in `message`, ab R1-28 im Feld `reason`). Ohne Modulzugang kommt statt dessen die Policy-Ablehnung (401/403/404) | Zugang/Drip/Voraussetzung im Kursraum prüfen (RB-SUP-1) |
| `ccap_account_locked` | 403 | Lernsperre aktiv | Grund im Protokoll; Aufheben geplant (R1-38) |
| `ccap_invalid_param` | 400/409/422 | Parameter fehlt oder ungültig | Anfrage korrigieren |
| `ccap_validation_failed` | 422 | Validierung gescheitert (Einstellungen, Veröffentlichung mit Blockern, Zertifikat) | Feld- bzw. VAL-Liste lesen ([autoren.md](autoren.md) §6) |
| `ccap_validation_unacknowledged` | 409 | Warnungen beim Veröffentlichen nicht bestätigt | Warnungen bestätigen |
| `ccap_reason_required` | 422 | Pflichtbegründung fehlt | Begründung eintragen |
| `ccap_conflict` | 409 | Zustand passt nicht (z. B. gleichzeitige Änderung) | neu laden, erneut versuchen |
| `ccap_settings_conflict` | 409 | Einstellungen wurden zwischenzeitlich geändert | neu laden |
| `ccap_idempotency_conflict` | 409 | gleicher Schlüssel, anderer Inhalt | neuen Schlüssel verwenden |
| `ccap_request_in_progress` | 409 | dieselbe Anfrage läuft noch | kurz warten |
| `ccap_duplicate` / `ccap_limit_reached` / `ccap_not_favorite` | 409/422 | Sammlungen: Name doppelt / mehr als 30 / Frage kein Favorit | – |
| `ccap_invalid_token` | 422 | Fragen-Token manipuliert, fremd oder älter als 2 h | Frage neu laden |
| `ccap_invalid_answer` | 422 | Lernantwort leer oder ungültig | – |
| `ccap_rate_limited` | 429 | Ratenlimit (§6); `retry_after` in Sekunden | warten |
| `ccap_exam_not_startable` | 422 | Prüfung nicht veröffentlicht/ungültig | Prüfungsregel prüfen |
| `ccap_exam_attempts_exhausted` | 422 | Versuchsgrenze oder Sperrfrist | Zusatzversuch geplant (K-09, R1-38) |
| `ccap_exam_start_busy` | 409 | paralleler Start | erneut versuchen |
| `ccap_exam_running` | 409 | Aktion während laufender Prüfung nicht erlaubt | nach Abgabe |
| `ccap_exam_expired` | 410 | Frist (plus 5 s Kulanz) abgelaufen; Versuch wird ausgewertet | Ergebnis ansehen (RB-SUP-2) |
| `ccap_exam_already_submitted` / `ccap_exam_aborted` | 409 | Versuch bereits abgegeben / abgebrochen | – |
| `ccap_exam_invalid_answer` | 422 | Antwort passt nicht zum Fragetyp | – |
| `ccap_exam_answer_locked` | 422 | Freitextfrage: erst Musterantwort aufdecken | – |
| `ccap_exam_conflict` | 409 | Nachbewertung eines nicht ausgewerteten Versuchs | – |
| `ccap_rule_frozen` / `ccap_revision_frozen` | 409 | veröffentlichte Regel/Revision ist unveränderlich | neue Version anlegen |
| `ccap_activity_type_locked` | 409 | Activity-Typ nach Veröffentlichung nicht änderbar | neue Lektion |
| `ccap_bundle_invalid` | 422 | Bundle-Regel verletzt (Selbstbezug, verschachtelt) | – |
| `ccap_claim_used` / `ccap_group_full` / `ccap_group_busy` | 409 | Einladung bereits genutzt / Gruppe voll / Gruppe kurz gesperrt | Gruppenbesitzer lädt neu ein / Plätze erhöhen / erneut versuchen |
| `ccap_import_state` / `ccap_import_busy` / `ccap_import_failed` | 409/409/500 | Import im falschen Zustand / anderer Import läuft / Fehler | RB-SUP-4 |
| `ccap_media_type_forbidden` | 422 | Dateityp nicht erlaubt | – |
| `ccap_storage_unavailable` / `ccap_storage_move_failed` | 500 | geschützte Ablage fehlt oder nicht beschreibbar | Website-Zustand „protected storage“ (§4) |
| `ccap_temporarily_unavailable` | 503 | Zählerspeicher defekt (fail-closed) | Datenbank prüfen |
| `ccap_maintenance` | 503 | Migration fällig; nur schreibende Anfragen, `Retry-After: 30` | warten; bleibt es, RB-SUP-7 |
| `ccap_requirement_*` | – | Systemvoraussetzung fehlt (nur Aktivierung/Admin-Hinweis) | [installation.md](installation.md) §1 |

Interner Zustand ohne HTTP-Antwort: `ccap_exam_snapshot_invalid` (Versuch `broken`, weil der Snapshot defekt ist; Anzeige im Systemstatus geplant, R1-38).

## 3. Wartungslauf und Hintergrundaufgaben

| Hook | Zweck |
|---|---|
| `ccap_hourly` | stündlich: Zugangsablauf, Pending-Timeout (24 h), Einladungsablauf, Outbox-Aufräumen, Prüfungsfristen, Aufbewahrungsfristen, Mailversand. Jeder Schritt läuft isoliert; ein fehlerhafter Schritt wird protokolliert (`cron_task_failed`), die anderen laufen weiter. |
| `ccap_outbox_dispatch` | stellt interne Ereignisse zu (Mails, Zertifikate, Cache); plant sich nach, solange Arbeit da ist |
| `ccap_task_continue` | Fortsetzungen (Mailstapel, Neuaufbau Fortschritt) |
| `ccap_migrate` | Datenbank-Migration |
| `ccap_privacy_export_generate` | reserviert, noch ohne Handler (asynchroner Export geplant, R1-34) |

Fehlt die stündliche Planung, wird sie bei der nächsten Admin- oder Cron-Anfrage neu angelegt (ohne Doppelplanung).

**Interne Outbox:** Zustellversuche nach 1 min, 5 min, 15 min, 1 h, 6 h und dreimal 24 h; nach 8 Versuchen `dead` (Dead Letter, Aktion `course_captain_outbox_dead_lettered`). Offene Zustellungen werden nach 30 Tagen `dead`, vollständig zugestellte Ereignisse nach 90 Tagen gelöscht. Bei `COURSE_CAPTAIN_OUTBOUND = off` werden Zustellungen übersprungen (`skipped`), auch Zertifikate. Erneut zustellen (Replay) ist im Dienst vorhanden (Recht `ccap_manage_integrations`, zugestellte Ereignisse nur mit Begründung); Oberfläche und CLI geplant (R1-38).

## 4. Website-Zustand (Werkzeuge › Website-Zustand)

| Prüfung | Kritisch | Empfehlung | Abhilfe |
|---|---|---|---|
| Course Captain protected storage | Ordner fehlt, nicht beschreibbar oder ohne `.htaccess`/`index.php` | – | Schreibrechte von `uploads/course-captain` prüfen; auf nginx Direktzugriff auf `protected-*`/`packages-*` sperren oder `COURSE_CAPTAIN_PRIVATE_DIR` außerhalb des Webroots |
| Course Captain maintenance | `ccap_hourly` nicht geplant | Schritte fehlgeschlagen (Liste) oder letzter Lauf > 3 h | WP-Cron bzw. System-Cron prüfen; Fehler im Audit log (`cron_task_failed`) |
| Course Captain event delivery | – | Zustellungen mit Status `dead` | Ursache beheben (meist Mail/Ablage); Replay geplant (R1-38) |
| Course Captain mail delivery | ≥ 10 fehlgeschlagene Mails in 24 h oder Warteschlange > 1 h | einzelne Fehler oder ungültige Fangadresse | RB-SUP-5 |

Systemstatus-Screen mit allen Prüfungen H-01…H-38, Health-Endpunkt `/health`, Diagnosepaket und zeitlich begrenztes Debug-Logging: geplant (R1-38).

## 5. Caches und Page-Cache

- Alle Antworten von `course-captain/v1` und `wp/v2/ccap_*` senden `no-store, private` sowie `X-LiteSpeed-Cache-Control`, `X-Accel-Expires: 0`, `Surrogate-Control` und `CDN-Cache-Control`, auch bei Fehlern.
- Lernbereich, Nachweis-, Abmelde- und geschützte Seiten setzen `DONOTCACHEPAGE` und No-Cache-Header. Ein Seiten-Cache-Plugin oder CDN muss diese Signale beachten. Sonst `/lernen/*` und Seiten mit `[course_captain_protected]` (ab R1-28 `[ccap_protected]`) von Hand ausschließen.
- Objekt-Cache (Gruppe `course_captain`) wird über Generationen invalidiert, nach dem Commit der Transaktion. Zugangsentscheidungen werden nie dauerhaft zwischengespeichert. Ein Leeren des Objekt-Caches ist gefahrlos. CLI `wp course-captain cache flush`: geplant (R1-38).
- Bekannte Grenze: Reale Proxy- und Page-Cache-Kombinationen sind noch nicht geprüft (R1-30/R1-42).

## 6. Ratenlimits

| Bereich | Grenze | Code |
|---|---|---|
| Lernantworten (Smarttrainer) | 60 je Minute und Person | `ccap_rate_limited` (Bucket `answer`) |
| Prüfungsstarts | 40 je Stunde und Person (Fortsetzen zählt nicht) | `ccap_rate_limited` |
| Öffentliche Zertifikatsprüfung | 30 je Minute und IP | `ccap_rate_limited` (`bucket: verify`) |
| Testmail | 5 je Stunde | `ccap_rate_limited` |
| Einladung neu senden | 3 je Einladung | `ccap_rate_limited` |
| Informative Mails | 10 je Empfänger und 24 h (Pflichtmails werden verschoben, nicht verworfen) | Mailstatus `rate_limited` |
| Gruppeneinladungen | max(20, 2 × Plätze) je Gruppe und 24 h | Mailstatus `rate_limited` |

Die Zähler liegen in der Tabelle `ccap_rate_limit`. Ist sie defekt, wird abgelehnt (`ccap_temporarily_unavailable`), nicht freigegeben.

## 7. Runbooks

Format: **Symptom → Prüfen (nur lesend) → Ursache und Aktion → Eskalation → Abschluss.** Korrekturaktionen K-01…K-17 aus R0-13 §3 mit Dialog und Pflichtbegründung sind geplant (R1-38). Bis dahin gelten die genannten Ersatzwege.

### RB-SUP-1 · Kein Zugang trotz Kauf oder Einladung
- **Symptom:** Kurs fehlt in „Mein Lernen“, Status „ausstehend“ oder „kein Zugang“, `ccap_content_locked`.
- **Prüfen:** Identität (GR-1); Status im Lernbereich bzw. `GET /me/courses` der Person; Kurs veröffentlicht? Sperrgrund (`drip` mit Datum, `prerequisite`, `trial`); Lernsperre; Einladung abgelaufen (30 Tage) oder Gruppe voll; Website-Zustand „maintenance“ und „event delivery“.
- **Aktion:** Drip/Voraussetzung → Erklärung, keine Korrektur. Einladung abgelaufen/Gruppe voll → Gruppenbesitzer lädt neu ein. `failed` nach 24 h `pending` → Quelle klären, dann Retry (Dienst vorhanden; Dialog R1-38). Letzter Ausweg: manueller Grant mit Enddatum und Begründung (Kursmanager). Achtung, Doppelgrant-Risiko.
- **Eskalation:** viele Konten betroffen → Stufe 3 (Cron/Outbox); unklarer Fehlercode → Stufe 4 mit Versionen und Fehlercodes.
- **Abschluss:** Person sieht „aktiv“; Ticket mit Protokollverweis.

### RB-SUP-2 · Prüfung abgebrochen
- **Symptom:** Absturz, Verbindungsabbruch, „Zeit abgelaufen“, Fehler bei Abgabe.
- **Prüfen** (ohne Antworten einzusehen): Status des Versuchs (`in_progress`, `submitted`/`evaluated`, `expired`, `cancelled`, `broken`), Start, Frist, Fehlercode und Uhrzeit, Wartungslauf (Fristauswertung).
- **Aktion:** `in_progress` und Frist läuft → Person setzt fort (gleicher Versuch, Antworten bleiben). Ausgewertet ohne Störung → Ergebnis erklären. Belegte technische Störung → Zusatzversuch (K-09, geplant R1-38). `broken` → Stufe 3 behebt die Ursache; Nachauswertung (K-07) bzw. Verwerfen (K-08) geplant (R1-38). Selbst abgebrochen → zählt, wenn die Prüfung das vorsieht.
- **Eskalation:** mehrere `broken` derselben Prüfung → Stufe 3, Prüfung bis zur Klärung nicht veröffentlichen; Produktfehler → Stufe 4.

### RB-SUP-3 · Zertifikat falsch
- **Prüfen:** Nachweisansicht, Fassung (`revision_no`), Verifikationsstatus, zugrunde liegender Versuch.
- **Aktion:** Name/Druckfehler → Korrektur mit Nachweis (neue Fassung, `ccap_certificate_correct`). Späterer Kurstitel anders → keine Änderung (Titel wie ausgestellt). Punkte falsch nach Nachbewertung → Korrektur mit „Nachweis aktualisieren“; bestanden → nicht bestanden erzeugt nur einen Prüfauftrag im Protokoll. Irrtum/Betrug → Widerruf mit Kategorie (endgültig).
- **Eskalation:** Darstellungsfehler (Zeichen fehlen) → Stufe 3/4 mit Vorlagenversion.

### RB-SUP-4 · Import hängt
- **Prüfen:** Batch-Status (`importing`/`activating`), Item-Fehler, Wartungslauf, geschützte Ablage; läuft ein anderer Import (`ccap_import_busy`)?
- **Aktion:** Objektfehler → Paket korrigieren und neu hochladen; nicht fortsetzbar → verwerfen; veröffentlicht, aber fehlerhaft → Rollback (neuer Batch aus Paketkopie, Begründung). Wiederaufnahme nach Abbruch und Heartbeat-Prüfung: geplant (R1-41). Nie Tabellen von Hand bereinigen (GR-3).

### RB-SUP-5 · Mails kommen nicht an
- **Prüfen:** Ausgangsmodus (`COURSE_CAPTAIN_OUTBOUND`; ohne Konstante außerhalb von `production` = `catch`, Mails gehen an die Fangadresse); Website-Zustand „mail delivery“; Abmeldung der Person (informative Typen); Typ in `emails.types` abgeschaltet (`access_expired` ist standardmäßig aus); Ratenlimit; Mailstatus (`failed`, `unknown`, `held`, `suppressed`).
- **Aktion:** Konfiguration korrigieren; Testmail an die eigene Adresse; Zustellbarkeit (SPF/DKIM/DMARC, SMTP) beim Hoster prüfen. `unknown` **nicht** blind erneut senden (Doppelwirkung); „erneut senden“ legt einen neuen Datensatz an (Dienst vorhanden, Knopf geplant R1-38).

### RB-SUP-6 · Verdacht auf Kontomissbrauch
- **Prüfen:** Zugangs- und Platzhistorie, auffällige Versuchsmuster (nur Status und Zeiten).
- **Aktion:** Lernsperre (geplant R1-38), Passwort-Reset durch die Person; Zugänge nicht widerrufen, solange der Fall offen ist.

### RB-SUP-7 · Website-Zustand rot / Wartungslauf steht
- **Prüfen:** „maintenance“ (geplant? letzte Ausführung? fehlgeschlagene Schritte), `DISABLE_WP_CRON` ohne System-Cron, `ccap_maintenance` bei Schreibzugriffen (Migration fällig), Ablage.
- **Aktion:** System-Cron einrichten (`wp cron event run --due-now` per Server-Cron); Ursache des fehlgeschlagenen Schritts im Audit log; keine Sperren oder Optionen von Hand löschen. Migrations-CLI geplant (R1-40).

### RB-SUP-8 · Ausgesperrt durch Website-Härtung
Website-Härtung ist noch nicht gebaut (R1-45). Die Einstellungen `hardening.*` haben keine Wirkung. Sperren stammen aus anderen Plugins.

### Antwortvorlagen (Forum, Free)
- **Erstinstallation:** „Bitte nennen Sie PHP-, WordPress- und Datenbankversion sowie die Meldung bei der Aktivierung (Code `ccap_requirement_…`). Voraussetzungen: PHP 8.3, WordPress 7.0, MariaDB 10.6/MySQL 8.0 mit InnoDB.“
- **Upgrade/Migration fehlgeschlagen:** „Bitte prüfen Sie Werkzeuge › Website-Zustand (Einträge ‚Course Captain‘) und ob schreibende Anfragen `ccap_maintenance` melden. Bitte keine Tabellen von Hand ändern. Nennen Sie uns die Version vorher und nachher.“
- **Restore:** „Stellen Sie Datenbank und `uploads/course-captain` gemeinsam aus demselben Zeitpunkt wieder her.“
- **Konflikt mit Cache-/Security-Plugin:** „Bitte schließen Sie `/lernen/*` und die REST-Route `course-captain/v1` vom Seiten-Cache aus und prüfen Sie, ob das Plugin `DONOTCACHEPAGE` beachtet.“
- **Übersetzung fehlt:** „Übersetzungen kommen über translate.wordpress.org; bitte Sprachpakete aktualisieren.“
- **Pro-Frage:** „Fragen zu Pro beantworten wir über den Pro-Supportkanal, nicht im Forum.“
- **Sicherheitsmeldung:** „Bitte keine Details im Forum. Melden Sie Sicherheitsprobleme vertraulich wie unten beschrieben.“

## 8. Bekannte Einschränkungen (Stand 1.0.0-dev)

- `privacy.external_media_mode = link_only` wird wie `always_load` behandelt (Fremdvideo wird geladen). Bis zur Korrektur nur `always_load` oder `click_to_load` verwenden.
- Zertifikatsmails, Mails zu verworfenen/annullierten Versuchen und Übertragungen werden noch nicht versendet.
- Die Einstellungen sind nicht über eine Oberfläche änderbar (R1-32).
- Ohne sprechende Permalinks funktionieren Lernbereich, Nachweis- und Abmeldelinks nicht.

## 9. Forum-Regeln (WordPress.org, Stufe 4)

1. Nie nach Zugangsdaten, Admin-Konten, Datenbankdumps oder Personendaten fragen; kein Konto auf der Site des Betreibers.
2. Free-Support findet im Forum statt. Pro-Fragen werden freundlich an den Pro-Kanal verwiesen.
3. Zur Diagnose im Forum nur Versionen, Fehlercodes, aktive Plugins und den Website-Zustand posten. Die forentaugliche Textfassung des Diagnosepakets kommt mit R1-38.
4. Sicherheitsfunde nie im Forum diskutieren; Beitrag an die Moderation melden und auf den Sicherheitsweg verweisen.
5. Bewertungen nicht beeinflussen.

## 10. Sicherheitsmeldungen

Sicherheitsprobleme bitte **nicht** öffentlich melden, sondern an die Sicherheitsadresse des Herausgebers. Die Adresse und `SECURITY.md` werden vor dem ersten Release ergänzt (offen, Angabe des Auftraggebers fehlt). Zielwerte nach Beschluss E-211/E-303: Erstreaktion ≤ 72 h, Korrektur kritisch ≤ 7 Tage, hoch ≤ 30 Tage. Korrekturen erscheinen als PATCH-Release mit Changelog-Zeile „Sicherheitsupdate:“ ohne Ausnutzungsanleitung. Bei kritischen Lücken wird das WordPress.org-Plugins-Team kontaktiert.
