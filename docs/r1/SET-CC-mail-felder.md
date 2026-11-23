# SET-CC – Slice „mail-felder“ (Nachweis)

Stand 23.11.2026, Branch `r2-b` (Basis main 89fe9ef). Punkte aus `TODO-WETTBEWERBSANALYSE.md`, Abschnitt „Einstellungen für den Verkauf“. Haken setzt der Koordinator.

Tests: `tests/integration/SettingsMailFieldsTest.php` (Gruppe `SET-CC`, 7 Tests), DOM-Prüfung der neuen Seiten in `tests/integration/A11yI18nTest.php::test_A11Y_admin_screens_and_blocks` (Übersicht und Editor der Mailtexte), Hook-Snapshot `docs/api/hooks.v1.json`.

## SET-CC-04 · Mailtext-Editor je Mailart und Sprache

- **Gebaut:** versteckte Admin-Seite `admin.php?page=course-captain-mail-texts` (`Admin\MailTextsScreen`, Recht `ccap_manage_settings`, ohne JS, admin-post → Redirect → GET), verlinkt im Tab „E-Mails“.
  - Übersicht: alle 16 Mailtypen aus `NotificationTypes` × installierte Sprachen (`MailTemplates::locales()`), Zustand „Eigener Text“/„Werkstext“.
  - Editor: Betreff, HTML-Text, Textfassung; Liste der erlaubten Platzhalter des Typs; Knöpfe „Speichern“, „Vorschau“, „Testmail an mich“. Vorschau und Testmail arbeiten mit dem eingegebenen Entwurf und speichern nie.
  - Platzhalterprüfung feldnah (`MailTemplates::check()`): unbekannter Platzhalter → nichts gespeichert, Fehlerzusammenfassung `role=alert`, Feldfehler mit `aria-describedby`/`aria-invalid` und der Liste der erlaubten Platzhalter (R0-27 §3.10).
  - Vorschau mit Beispieldaten (`MailTemplates::preview()` mit Entwurf): Betreff, HTML im `iframe sandbox` (`srcdoc`), Textfassung.
  - Testversand (`NotificationService::send_template_test()`): an die eigene Profiladresse, Betreff „[Test] …“, gleiches Budget wie die Testmail (5 je Admin und Stunde, fail-closed), gleiche Ausgangsregeln (Haltemodus/`off` → nichts, `catch` → Fangadresse). Datensatz als `test_mail` (nur Metadaten).
  - „Werkstext wiederherstellen“ je Typ und Sprache mit Bestätigungsseite (`MailTemplates::reset()`, Audit `mail_template_reset`; R0-27 §2.4).
  - Export (JSON-Download `course-captain-mail-templates` v1, ohne Personendaten) und Import mit Vorschau je Eintrag (neu/geändert/unverändert/übersprungen: unbekannter Typ, Sprache nicht installiert, ungültiger Text) und Bestätigung; jeder Eintrag läuft durch dieselbe Prüfung wie das Speichern. Datei ≤ 256 KB, 10 Exporte/Importe je Konto und Stunde (fail-closed). Audit `mail_templates_imported`.
- **Tests:** `test_SET_CC_04_editor_save_preview_test_reset`, `test_SET_CC_04_export_import`.
- **Offen:** Mailtexte im Gesamt-Export der Einstellungen (R0-27 §2.5 `mail_templates?` mit Opt-in) gehört zu SET-CC-15. Die Dienste `MailTemplates::export()`/`import()` lassen sich dort wiederverwenden. Kein REST-Endpunkt.

## SET-CC-23 · Testbetrieb im Tab E-Mails

- **Gebaut:** `SettingsScreen::outbound_status()` zeigt im Tab „E-Mails“ eine schreibgeschützte Tabelle mit diesen Angaben:
  - wirksamer Modus (`OutboundMode::current()`: an, Testbetrieb mit Fangadresse oder aus, auch „aus wegen ungültiger Fangadresse“);
  - Quelle (Konstante `COURSE_CAPTAIN_OUTBOUND` oder automatisch nach Umgebungstyp);
  - Fangadresse (`COURSE_CAPTAIN_MAIL_CATCH_ADDRESS` oder Admin-Adresse);
  - gegebenenfalls den Haltemodus.

  Es gibt kein Formularfeld; die Konstanten bleiben die einzige Quelle.
- **Tests:** `test_SET_CC_23_09_test_mode_and_assignment_fields`.

## SET-CC-09 · Abgabe-Felder im Tab Lernen

- **Gebaut:** neuer Abschnitt „Assignment submissions“ mit `learning.assignment_file_types` (Kästchen je Typ), `assignment_max_file_mb`, `assignment_max_files`. Jedes Feld hat Hilfetext und Meta-Zeile. Die Werte wirkten schon vorher in `AssignmentFiles`.
- **Tests:** wie oben (Darstellung, Speichern, Bereichsfehler).

## SET-CC-10 · `privacy.csp_mode` wirksam (Entscheidung)

**Entscheidung: wirksam machen, nicht entfernen; die Werte bleiben `enforce|report_only`, ohne „off“.** Die Begründung stützt sich auf R0-31 und den zugehörigen Beschluss:

- R0-31 PSD-052 und R0-17 §3.5 verlangen eine CSP auf den eigenen Antworten.
- E-224 setzt `enforce` als Standard und `report_only` nur mit Bestätigung (RC-13).

Ein Wert „off“ würde E-224 widersprechen und die XSS-Abwehr ohne Not abschaltbar machen. Zur Fehlersuche gibt es `report_only`, als Notausgang für fremde Frames den Filter. Deshalb ist keine Migration nötig.

`Security\Csp::start()` läuft in `LearningArea::serve()` und deckt damit alle Seiten des Lernbereichs ab: Startseite, Kursraum, Lektionen, Üben, Prüfungsraum, Gruppen und Einladung. Die Regeln der Richtlinie:

- `script-src 'nonce-…' 'strict-dynamic' 'unsafe-inline' https:`. Die beiden letzten Quellen sind ein Rückfall für Browser ohne CSP 3 und werden von allen anderen ignoriert. Die Nonce erreicht über `wp_script_attributes` und `wp_inline_script_attributes` jedes Skript, das WordPress ausgibt. Dazu gehören Theme, Admin-Leiste, Emoji, `exam-room.js` und `external-media.js`. Nachgeladene Skripte erlaubt `'strict-dynamic'`. Blockiert werden nur roh ausgegebene Skripte und Inline-Handler – genau das ist der Schutz.
- `frame-src 'self'` sowie `www.youtube-nocookie.com`, `www.youtube.com`, `youtube.com`, `player.vimeo.com`, `course_captain_trusted_media_hosts` und der neue Filter `course_captain_csp_frame_hosts`. Damit funktionieren die eingebauten Player und die YouTube-oEmbeds im Lektionstext. Click-to-load bleibt eine Entscheidung im Markup, vor der Einwilligung wird nichts geladen.
- `object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'`.
- Styles, Bilder, Medien und Verbindungen bleiben frei: Theme-Schriften, Videodateien auf beliebigen HTTPS-Hosts und REST-Aufrufe des Prüfungsraums dürfen nicht brechen.
- `report_only` sendet `Content-Security-Policy-Report-Only`. Verstöße erscheinen nur in der Browser-Konsole, es gibt keinen Report-Endpunkt.

Die Richtlinie gilt unabhängig von `hardening.site_headers` (PSD-052). Der Hilfetext im Tab nennt Geltungsbereich und Wirkung.

- **Tests:** `test_SET_CC_10_csp`:
  - Header je Modus;
  - ungültige Hosts werden verworfen;
  - Filter;
  - Nonce an `wp_get_script_tag`/`wp_get_inline_script_tag`;
  - die Lernbereich-Seite enthält kein `<script>` ohne Nonce;
  - es gibt keinen Wert „off“.
- **Offen:** CSP auf der öffentlichen Zertifikats-Prüfseite. `CertificatePages` nutzt dort einen Inline-`onclick` für „Drucken“, der vorher umgebaut werden muss. REST-Antworten liefern JSON, dort ist eine CSP ohne Wirkung. `/cc-file/` hat bereits `sandbox`. Browser-E2E mit Fremd-Themes (SEC-007) steht aus.

## SET-CC-16 · Grund-Marke Lernbereich

- **Gebaut:**
  - `display.logo_id`: Typ int; Regel `logo_missing`, wenn die ID kein Bild ist. Ausgabe im Kopf des Lernbereichs mit Lernbereich- oder Website-Titel als Alt-Text. Das Feld wird nicht exportiert, weil eine Medien-ID nur auf dieser Website gilt.
  - `display.accent_color`: neuer Schematyp `color`, `#rrggbb` normalisiert oder leer. Die Regel `contrast` lehnt Farben mit weniger als 4,5:1 gegen Weiß ab (WCAG-Formel `SettingsRules::contrast()`), denn Knöpfe bekommen weiße Schrift. Ausgabe nur als CSS-Variable `--ccap-accent` für `.ccap-button` und `progress`.
  - `display.button_labels` (`enroll`, `complete`, `start_exam`, `submit_exam`, je ≤ 40 Zeichen, strenges Objekt): Wirkt in Selbstanmeldung, Lektionsabschluss, Prüfungsstart und Prüfungsabgabe. Leer bedeutet übersetzter Standardtext.
- **Tests:** `test_SET_CC_16_branding`:
  - Kontrast-Ablehnung mit feldnaher Meldung;
  - Formatfehler;
  - Logo-Regel;
  - Normalisierung;
  - CSS;
  - Beschriftungen und Längengrenze;
  - kein Export der Logo-ID;
  - DOM-Prüfung des Lernbereich-Dokuments.

## SET-CC-18 · Barrierefreiheit der Inhalte

- **Gebaut:**
  - `display.require_transcripts` (Standard aus).
  - Videoeinstellungen `transcript` (Text, ≤ 100 000 Zeichen) und `transcript_url` (https). Ausgabe unter dem Player als `<details>` „Transcript“ bzw. als Link, in jedem Modus für externe Medien.
  - Publish-Validator VAL-144: Video ohne Abschrift ergibt immer einen Hinweis (`notice`), mit `display.require_transcripts` einen Blocker. Bewusst kein `warning`, weil Warnungen das Veröffentlichen bis zur Bestätigung anhalten würden und damit das Verhalten bestehender Kurse ändern.
  - `display.accessibility_page_id` (Seitenregel wie bei anderen Seitenfeldern): Link „Accessibility statement“ im Fuß jeder Lernbereich-Seite.
  - Die Felder liegen im neuen Abschnitt „Accessibility of the content“ zusammen mit `require_captions`.
- **Tests:** `test_SET_CC_18_transcripts`:
  - Hinweis, Blocker und behoben;
  - https-Pflicht für `transcript_url`;
  - Fußlink;
  - Entwurfsseite abgelehnt.
- **Offen:** `display.require_captions` ist weiterhin nicht im Validator verdrahtet (VAL-142 fehlt, vorbestehend; Befund für SET-CC-P1). Die Abschrift lässt sich nur über das JSON-Einstellungsfeld der Lektion eingeben; ein eigenes Formularfeld fehlt (UI-F8-Umfeld).

## SET-CC-22 · Protokollstufe und Fristen

- **Gebaut:**
  - Neues `Ops\Log` mit Stufen `error < warning < info < debug`.
    - Fehler und Warnungen landen in `<protected>/logs/cc-error-YYYYMMDD.log`, Info und Debug in `cc-debug-…`. Beide liegen im Deny-all-Ordner, nie im Webroot.
    - Rotation bei 5 MB.
    - Format: JSON-Zeilen; E-Mail-Adressen maskiert, Werte ≤ 200 Zeichen.
  - Registerfeld `advanced.log_level`: Standard `warning`, `export: never_env`.
    - Der Zeitpunkt der Erhöhung wird über `update_option`/`add_option` des Tabs in `course_captain_log_level_since` festgehalten. Das gilt für jeden Weg: Formular, Import und CLI.
    - Der Stundenlauf (`CronHooks`, Schritt `logs`) setzt `info`/`debug` nach 7 Tagen auf den Standard zurück (Akteur `system:cron`, Audit `settings_saved` mit `context: auto_reset`).
  - Fristen `advanced.error_log_days` (1–365, 30) und `advanced.debug_file_days` (1–30, 7, E-143): `Log::prune()` im Stundenlauf.
  - Aufrufer:
    - fehlgeschlagene Cron-Schritte (`HealthChecks::run_isolated`, error);
    - Retention-Schritte (error);
    - Mail-Vorlagenfehler (error);
    - Zustellfehler (info, beim letzten Versuch warning);
    - Versandlauf-Statistik (debug).
  - Option im Uninstall-Manifest.
- **Tests:** `test_SET_CC_22_log_level_and_retention`:
  - Filter nach Stufe;
  - Maskierung;
  - Deny-all-Ordner;
  - kein Rücksetzen nach 6 Tagen, Rücksetzen nach 7 Tagen mit Audit-Kontext;
  - Löschfristen je Kanal;
  - Bereichsfehler.
- **Hinweis/Offen:**
  - R0-27 §1.4 führt `error_log_days`/`debug_file_days` als Unterschlüssel von `privacy.retention` (SET-CC-02, Gruppe h8). Hier liegen sie als `advanced.*` im Tab „Erweitert“. Beim Zusammenführen mit SET-CC-02 entscheiden, ob `privacy.retention` darauf verweist oder die Felder übernimmt (dann Migration der zwei Werte).
  - R0-13 DL-1 sieht für das Debug-Fenster höchstens 24 h vor; der TODO-Punkt verlangt 7 Tage. Umgesetzt sind die 7 Tage aus dem TODO; die Abweichung ist zur Bestätigung vorgemerkt.
  - Es gibt keine Log-Ansicht im Systemstatus und keinen Diagnose-Auszug (R1-38-Umfeld).

## Geteilte Dateien

- `Kernel/Plugin.php`: Registrierung von `MailTextsScreen` und `Log`.
- `Ops/AuditView.php`: 2 Aktionen.
- `Settings/SettingsSchema.php`: Felder und Typ `color`.
- `Learner/LearningArea.php`: CSP-Start, Logo, Akzent-CSS, Fuß, `button_label()`.
- `Learner/LessonView.php`, `Learner/ExamRoom.php`: Knopfbeschriftungen und Abschrift.
- `Kernel/UninstallManifest.php`, `Kernel/CronHooks.php`.
- `Notification/NotificationService.php`, `Notification/MailTemplates.php`.
- `Publish/CourseValidator.php`, `Privacy/RetentionService.php`, `Ops/HealthChecks.php`.
- `Admin/SettingsFields.php`, `Admin/SettingsScreen.php`, `Settings/SettingsRules.php`.
- `Activity/Providers/VideoProvider.php`.

`Storage/Schema.php` ist unverändert.
