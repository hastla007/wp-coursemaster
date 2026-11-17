# R1-29 · Aufgabenskript Admin-Setup (Flow 4, R0-26 §1.5, R1-32)

**Sitzung:** 60 min, moderiert remote. Site: Profil `admin` (eine frische Site je Person: `setup-site.sh --profil admin --site …/admin-pNN --db ccap_usab_admin_pNN`). Konto: `cc_fx_ux_admin` (Administrator). Eine der fünf Sitzungen in **en_US** (E-169): Profilsprache des Kontos auf Englisch stellen.

**Startzustand:** frisches WordPress auf der Ziel-Zeile R0-14 §3 (PHP 8.5, WP 7.1.x, MariaDB 12.3, Apache), Course Captain gerade aktiviert, Setup-Assistent offen, Registrierung aus, RK-3 vorhanden (für AD-T06), `cc_fx_ux_gv_kandidatin` als künftige Besitzerin. Für AD-T03 eine zweite Site auf **nginx ohne Dateischutz-Regel** (F4.5).

**Bereitschaft (Stand 6f6f05f):** AD-T01 … AD-T06 **bereit** (`bereitschaft.md`). Flow 4 eignet sich für Pilot und Runde 1 sofort.

Abbruch = 3 × Zielzeit (max. 20 min), Hilfstechnik 4 ×.

### AD-T01 · Einrichten mit Selbstanmeldung ★ (UX-060, A-02, F4.3/F4.7, S-07)
- **Karte:** „Richte das Lernsystem so ein, dass sich Mitarbeitende selbst für Kurse anmelden können. Die Kurse sollen unter /lernen/ liegen.“
- **Erfolg:** Assistent abgeschlossen, Modus Selbstanmeldung mit bestätigter Risiko-Abfrage (F4.3), Permalink gesetzt; Warnung F4.7 verstanden (Nachfrage: „Was bedeutet die Warnung zur Registrierung?“).
- **Zeit:** **Ziel ≤ 10 min (Median, S-07)** · Abbruch 20 min (AT 40 min).
- **Beobachten:** Risiko-Dialog lesen, Fokus auf „Abbrechen“, Verständnis „Registrierung in WordPress ist aus“.
- **Systemprüfung:** `course_captain_setup_state.status` = `completed` oder `completed_with_issues`; Lernbereichs-Slug `lernen`; Einschreibemodus Selbstanmeldung.
- **Störfall AD-T02** wird während AD-T01 in Schritt 4 ausgelöst (siehe unten).

### AD-T02 · Weiter nach Abbruch ★ (UX-061, F4.6, A11Y-083)
- **Auslösen:** In Schritt 4 („learning_exams“), nachdem die Person Werte eingetragen, aber noch nicht gespeichert hat: Moderation bittet, den Tab zu schließen („Bitte schließen Sie jetzt diesen Tab.“).
- **Karte:** „Mach mit der Einrichtung weiter.“
- **Erfolg:** Wiedereinstieg bei Schritt 4, gespeicherte Werte vorbelegt. 0 verlorene (gespeicherte) Werte.
- **Zeit:** Ziel 1 min · Abbruch 3 min (AT 4 min).
- **Beobachten:** Weg zurück (Admin-Hinweis, Menü), Erwartung zu ungespeicherten Eingaben.

### AD-T03 · Bereitschaftsprüfung verstehen (UX-062, A-02 Schritt 7/8, F4.5)
- **Ort:** nginx-Site ohne Dateischutz-Regel.
- **Karte:** „Die Bereitschaftsprüfung meldet ein Problem. Finde heraus, was zu tun ist.“
- **Erfolg:** Die Person nennt die Ursache (Dateischutz) und die angebotene Abhilfe.
- **Zeit:** Ziel 4 min · Abbruch 12 min (AT 16 min).

### AD-T04 · Einstellungs-Tabs, ungespeicherte Änderungen (UX-063, A-15, A11Y-009)
- **Karte:** „Stelle ein, dass Prüfungsergebnisse erst nach der Abgabe Feedback zeigen, und mach eine zweite Änderung im anderen Tab rückgängig, ohne zu speichern.“
- **Erfolg:** Tab gespeichert; Warnung bei ungespeicherten Änderungen verstanden.
- **Zeit:** Ziel 5 min · Abbruch 15 min (AT 20 min).
- **Systemprüfung:** Option des Prüfungs-Tabs geändert, anderer Tab unverändert; Audit `settings_saved`.

### AD-T05 · Hängende Hintergrundaufgabe (UX-064, A-16)
- **Vorbereitung:** unmittelbar vorher `scenario warteschlangenfehler` (setzt eine Zustellung auf „failed“ mit der Meldung „Störfall Nutzertest R1-29: Empfänger nicht erreichbar (simuliert)“).
- **Karte:** „Im System hängen Hintergrundaufgaben. Finde sie und lass sie erneut laufen.“
- **Erfolg:** Job gefunden, Ursache gelesen, Retry ausgelöst, Ergebnis geprüft.
- **Zeit:** Ziel 6 min · Abbruch 18 min (AT 24 min).
- **Systemprüfung:** Zustellung wieder `pending`/`delivered`, `manual_retries` + 1, Audit-Eintrag.

### AD-T06 · Gruppe anlegen (UX-065, A-10)
- **Karte:** „Lege eine Gruppe mit 5 Plätzen für den Onboarding-Kurs an und bestimme eine Besitzerin.“ (Besitzerin: `cc_fx_ux_gv_kandidatin`)
- **Erfolg:** Gruppe = Soll (5 Plätze, Kurs RK-3, Besitzerin).
- **Zeit:** Ziel 4 min · Abbruch 12 min (AT 16 min).

### AD-GES (UX-066, S-10)
AD-T01 … AD-T06 gelöst, **Median ≤ 30 min**.

**Nachgespräch:** drei feste Fragen, SUS.
