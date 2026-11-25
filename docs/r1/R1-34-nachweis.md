# R1-34 · Rollen-, Datenschutz-, Medien- und Mail-Settings anbinden – Nachweis

**Stand:** 16.11.2026 · **Branch:** `r1-par-d`
**DoD (TODO R1-34):** UI und serverseitige Wirkung inklusive Audit, Consent, Opt-out, Redaction und Permissions geprüft.
**Quellen:** R0-31 §1.2/§1.3 (Prüftabelle, PD-1…PD-5), §3.1 (MC-1…MC-13), §3.2 (SH-1…SH-11), §3.3 (RB-1…RB-10), §5 (PSD-001…PSD-038); R0-27 §1.3 (`roles.cap_assignments`, `privacy.*`, `emails.*`, `learning.feedback`), §3.2/§3.7; R0-30 §3.3.6, §3.3.8, §3.3.9, RC-07…RC-13; Doku-Review-Befund B-5. Clean Room (E-01).

## 1. Dateien

| Datei | Inhalt |
|---|---|
| `src/Policy/RoleCaps.php` | Dienst des Rollen-Tabs: nur `ccap_*`-Primitive zuweisbar (nie `unfiltered_html`, RB-7); `administrator` nicht änderbar (F-51); niemand vergibt/entzieht ein Recht, das er selbst nicht hat (F-50); #19…#23 nur durch Administratoren; Hochrisiko-Rechte #14, #19…#24 werden gemeldet (RC-07); Wirkung in den WP-Rollen, jede Vergabe im Manifest `course_captain_role_grants`; Audit `role_caps_changed` (Rolle, hinzugefügt, entfernt, `confirmed_risks`); Reset (RB-8: eigene Rollen auf Aktivierungs-Defaults, Fremdrollen verlieren nur Manifest-Rechte, Rechte aus Rollen-Plugins bleiben) mit Undo 30 Tage (`course_captain_settings_undo_roles`, bereits im Manifest) |
| `src/Admin/RoleSettings.php` | Tab „Rollen & Rechte“ (#24 + `promote_users` oder Administrator mit #19): Übersichtstabelle mit `caption`, je Rolle `<details>` mit drei `fieldset`, Kontrollkästchen „{Rolle}: {Recht}“, „high impact“ als Text, nicht vergebbare Rechte `aria-disabled`; Formular je Rolle; RC-07-Bestätigung mit Kontenzahl und Rechteliste; Reset/Undo als eigene Formulare mit Bestätigungsseite |
| `src/Privacy/PrivacySettings.php` | serverseitige Wirkung: `auto_export_on_confirm` (bestätigter Exportantrag → Hintergrundexport über den Kern-Export, Link an die bestätigte Adresse; Handler für den vorhandenen Cron-Hook `ccap_privacy_export_generate`), `auto_erase_on_confirm` (bestätigter Löschantrag → Kontolöschung; Schutz für Konten mit Verwaltungs- oder `ccap_*`-Rechten, RB-9, Filter `course_captain_account_deletion_protected`), `show_avatars` (kein Gravatar im Lernbereich, MC-9); Audit `privacy_request_automated` nur mit Antrags-ID (keine Adresse) |
| `src/Security/ClientIp.php` | `privacy.trusted_proxies`: `X-Forwarded-For` zählt nur, wenn `REMOTE_ADDR` ein eingetragener Proxy ist (rechteste nicht vertrauenswürdige Adresse; IPv4/IPv6/CIDR); genutzt von Härtung (`Hardening::ip()`), Zertifikats-Prüfseite und -REST-Rate-Limit |
| `src/Activity/ExternalMedia.php` | **geteilt:** Modus über den Settings-Dienst; `consent()` liefert Kategorie (`privacy.consent_category`), Angebot „immer laden“ (nur `click_to_load` und `external_media_remember`), nie vorangehakt, und die Einwilligung der WP Consent API (MC-5); eine erteilte Einwilligung gibt das Video im Modus `click_to_load` frei |
| `src/Learning/AnswerService.php` | **geteilt:** `learning.feedback.visibility = never` schaltet die Lösungsanzeige im Lernmodus ab (B-5) |
| `src/Admin/TestMail.php` | Testmail aus Tab „E-Mails“ und Setup-Schritt 5 (eigenes Formular, Nonce, #19; 5/h fail-closed, F-63; Fehlschlag als Warnung F4.4); merkt den letzten Erfolg für RDY-07 |
| `src/Admin/SettingsScreen.php`, `src/Admin/SetupWizard.php` | Testmail-Knopf und Ergebnis in der Statusregion; Hinweis zu Aufbewahrungsfristen in Schritt 5 |
| `src/Settings/SettingsRules.php` | F-64: unbekannter (Pflicht-)Mailtyp im strikten Objekt `emails.types` → `required_mail` |
| `src/Ops/AuditView.php` | **geteilt:** `role_caps_changed`, `privacy_request_automated` |
| `src/Security/Hardening.php`, `src/Certificate/CertificateRoutes.php`, `src/Certificate/CertificatePages.php` | **geteilt:** Client-Adresse über `ClientIp` |
| `src/Kernel/Plugin.php` | **geteilt:** Registrierung `RoleSettings`, `TestMail`, `PrivacySettings` |
| `tests/integration/PrivacySettingsTest.php` | 10 Tests |

Bereits vor R1-34 angebunden (R1-21/R1-44, geprüft): `emails.from_name/from_address/footer_text/send_format/types` (Versand, Opt-out je Informationsmail, Pflichtmails nicht abschaltbar), `certificates.*`, `general.catalog_indexing`, `hardening.*`.

## 2. Ergebnisse (16.11.2026)

phpcs 0 Fehler · PHPStan 0 · Unit **104** · Integration **306** · Multisite **318** · Free-Guard 0 · Textdomain-Lint 0 · Hook-Snapshot aktualisiert (`course_captain_account_deletion_protected`).

## 3. Tests

| Test | PSD/SET |
|---|---|
| `test_PSD_001_defaults` | PSD-001, RB-1, RB-3 |
| `test_PSD_006_008_audit_and_rights` | PSD-006 (personal nur „changed“, kein Klartext), PSD-008 (Proxys nur Administrator, Härtung/Datenschutz ohne Recht → 403) |
| `test_PSD_010_risk_fields` | PSD-009/PSD-025 (RC-20, RC-21), PSD-010 (RC-19, danach neue Werte, andere Unterfelder erhalten) |
| `test_MC_external_media_and_consent` | PSD-022/023/025: Default bettet ein; `click_to_load` ohne Fremd-URL, Kästchen nie vorangehakt, Kategorie; `remember` aus; `link_only` |
| `test_SET_115_trusted_proxies` | SET-115, ADM-106: XFF ignoriert ohne Proxy, mit Proxy rechteste fremde Adresse, IPv6-CIDR, F-81 mit Zeilennummer, F-82, F-83 |
| `test_SET_110_confirmed_requests` | E-223 (Export geplant/abgeschaltet), E-43 (Standard keine Löschung; geschütztes Trainer-Konto; Löschung), Audit ohne Adresse |
| `test_MC_9_avatars` | MC-9/PSD-009 |
| `test_PSD_035_roles` | PSD-034 (Tab, Hochrisiko markiert), PSD-035 (RC-07, Audit, Manifest; F-50, Admin-only, F-51), PSD-036 (Reset entfernt nur Manifest-Rechte, Undo), PSD-037 (`unfiltered_html` nie), ADM-006, ADM-100/101 |
| `test_ADM_103_mail_settings` | ADM-033 (Testmail, F-63, Nonce, Recht), F-64, RC-08 |

## 4. Security-/Accessibility-Review (Selbstprüfung)

- Rechte serverseitig je Tab/Feld/Recht; Rollenänderung nie über dem eigenen Satz; Hochrisiko nur mit Token-Bestätigung; Audit ohne Personendaten.
- `X-Forwarded-For` wird ohne eingetragenen Proxy ignoriert (kein Umgehen der Limits); `0.0.0.0/0`/`::/0` abgelehnt.
- Kontolöschung nie für Staff-Konten; Export nur an die vom Kern bestätigte Adresse.

## 5. Abweichungen und offene Punkte

| Nr. | Thema | Entscheidung |
|---|---|---|
| A-1 | `privacy.keep_evidence_on_erasure` (RC-10, F-80) und `privacy.retention` (21 Unterschlüssel, RC-11, PSD-011…021) | nicht im Register; Speicher und Löschwirkung gehören zu R1-17/R1-44 (Retention, pseudonyme Nachweise); RC-10-Text und Risiko-Erkennung sind vorbereitet |
| A-2 | `privacy.csp_mode` | noch keine eigene CSP im Lernbereich (R1-16); Feld wird gespeichert und auditiert, RC-13 aktiv, Wirkung mit R1-16 |
| A-3 | `privacy.backup_max_age_days` | Tombstones (E-151) fehlen noch; Feld informativ mit Hinweis Δ-4 |
| A-4 | Globale `exams.*`/`enrollment.*` als Vorgaben neuer Regeln und Grants (B-5) | Vererbung/Overrides liegen bei R1-33; hier kein Resolver gebaut |
| A-5 | Widerruf der Browserwahl im Konto (MC-4), JS-Platzhalter (MC-7/8) | Lernenden-Oberfläche R1-16/R1-19; Server liefert `consent()` |
| A-6 | Mailvorlagen-Editor (F-65, F-66, RC-18) | R1-21 (Vorlagenspeicher vorhanden), UI nicht gebaut |
| A-7 | Secrets (SH-1…SH-5, PSD-029…033) | keine `secret`-Felder im Free-Register; Integrationsgeheimnisse mit R1-23/R1-39 |
| A-8 | Multisite: automatische Löschung entfernt das Konto nur von der Site und löscht die CC-Daten der Site | netzweites Löschen bleibt dem Netzwerk-Admin; **Klärung** **Entschieden 24.11.2026: nur auf der Site, beibehalten (E-367).** |
| A-10 | Basisvorlage im Tab „Zertifikate“ | kein zweites Formular: Link auf den Zertifikats-Screen A-11 (R1-35, `CertificatesScreen`) |
| A-9 | Rollen-Sollzustand in `course_captain_settings_roles` | Wirkung und Manifest genügen; keine zweite Kopie (Drift wäre sonst möglich) |

Review durch zweite Person vor Merge (AG-05).
