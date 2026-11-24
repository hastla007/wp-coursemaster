# Admin-Handbuch

Geprüft gegen: `src/Policy/{Roles,MetaCaps,PolicyService}.php`, `src/Kernel/Capabilities.php`, `src/Settings/{SettingsSchema,Settings}.php`, `src/Access/{GrantService,GroupService}.php`, `src/Notification/*`, `src/Certificate/*`, `src/Import/*`, `src/Ops/*`, `src/Admin/EditorScreens.php`.

> **Stand der Oberfläche:** Im Admin gibt es heute das Menü **Course Captain** mit *Courses*, *Modules*, *Lessons* und *Audit log*, die Kurs- und Lektions-Metaboxen, die Prüfungen im Website-Zustand und die WordPress-Datenschutzwerkzeuge. Alle anderen in diesem Handbuch genannten Funktionen sind als Dienst vorhanden, aber **noch ohne Bedienoberfläche**. Das ist in jedem Abschnitt vermerkt.

## 1. Rollen und Rechte

Bei der Aktivierung legt Course Captain vier Rollen an und gibt der Rolle `administrator` alle 24 eigenen Rechte. Fehlende Rechte werden bei erneuter Aktivierung ergänzt, vorhandene nie entfernt.

| Rolle | Kennung | Standardrechte |
|---|---|---|
| Kursmanager | `ccap_course_manager` | Inhalte bearbeiten, veröffentlichen, löschen; Fragenbank; Import/Export; Rechtefreigabe; Zugänge, Zugangsregeln, Gruppen; Berichte und Lernendendetails; fremde Versuchsdaten (mit Begründung); bewerten; Lernstand verwalten; Zertifikate; Lernsperre; Systemstatus lesen; Kursteam zuweisen |
| Autor | `ccap_author` | `read`, `upload_files`, `ccap_edit_courses` (nur eigene bzw. zugewiesene Kurse) |
| Trainer | `ccap_trainer` | `read`, Berichte, Lernendendetails, Bewerten – jeweils nur in zugewiesenen Kursen/Gruppen |
| Support | `ccap_support` | `read`, Lernendendetails, Lernsperre, Systemstatus lesen, Protokoll lesen |

Rechte, die nur der Administrator hat: `ccap_manage_settings`, `ccap_manage_integrations`, `ccap_view_audit_log` (außer Support), `ccap_run_maintenance`.

Grundregeln, die der zentrale Policy-Dienst durchsetzt:

- Geprüft werden Rechte, nicht Rollennamen. Ein Recht kann per Rollen-Editor einzeln vergeben werden.
- Niemand bewertet den eigenen Versuch, stellt sich selbst ein Zertifikat aus oder sperrt sich selbst.
- Niemand vergibt Rollen mit Rechten, die er selbst nicht hat. Administrator und Kursmanager vergibt nur, wer `promote_users` hat.
- Fremdes und Nichtexistentes sieht für Unberechtigte gleich aus (404 statt 403, kein Existenz-Orakel).
- Fremde Antworten und Versuchsdetails liest auch ein Berechtigter nur mit Pflichtbegründung; das wird protokolliert.
- Bekannte Abweichung: Ein Kursmanager kann die Rolle *Support* nicht vergeben, weil Support das Recht `ccap_view_audit_log` hat, das dem Kursmanager fehlt (Klärung offen).

Tab „Rollen & Rechte“ in den Einstellungen: geplant (R1-34).

## 2. Einstellungen

Die Einstellungen liegen je Tab in einer Option `course_captain_settings_<tab>`. Speichern ist ein Teil-Update („alles oder nichts“ mit Feldfehlern), Konflikte gleichzeitiger Bearbeitung werden erkannt (`ccap_settings_conflict`), jede Änderung wird protokolliert (personenbezogene Werte nur als „geändert“, Geheimnisse nie). Je Tab gibt es *Zurücksetzen* mit einer Undo-Stufe (30 Tage) und einen Export ohne Geheimnisse.

> **Stand:** Die Einstellungsseiten (Formulare, Risiko-Bestätigung) sind **geplant (R1-32)**; Import (R1-36) und WP-CLI (R1-28) ebenso. Heute gelten die Standardwerte. Schreiben Sie Optionen nicht direkt; ungültige Fremdschreibungen werden verworfen.

Tabs und Felder, die der Code heute **tatsächlich auswertet**:

| Tab | Feld | Standard | Wirkung |
|---|---|---|---|
| general | `learning_area_slug` | `lernen` | Pfad des Lernbereichs |
| general | `course_slug` | `kurs` | öffentlicher Pfad der Kursseiten |
| general | `delete_data_on_uninstall` | `false` | Datenlöschung beim Deinstallieren (nur Administrator) |
| learning | `feedback.show_correct_answers` | `true` | Lösung nach einer Lernantwort anzeigen |
| certificates | `auto_issue` | `false` | Zertifikate automatisch ausstellen |
| certificates | `holder_name_policy` | `learner_opt_in` | Name auf der öffentlichen Verifikation nur nach Freigabe durch die Person (`never` = nie) |
| certificates | `base_template` | leer | Ausstellername, Fußzeile, Logo, Signaturzeile |
| emails | `from_name`, `from_address` | leer (WordPress-Standard) | Absender nur für Course-Captain-Mails |
| emails | `footer_text`, `send_format` | leer, `multipart` | Fußzeile, HTML+Text oder nur Text |
| emails | `types` | siehe §5 | informative Mailtypen ein/aus |
| privacy | `external_media_mode` | `always_load` | eingebettete Fremdvideos sofort oder erst nach Klick laden |

Im Register vorhanden, aber **noch ohne Wirkung**: `display.*`, `learning.course_completion_default`, `learning.favorites_enabled`, `learning.mistake_training_enabled`, `learning.feedback.visibility`, alle Felder in `exams.*` (Prüfungsregeln haben eigene Standardwerte, Übernahme mit R1-33), `enrollment.*` (Einladungsfrist 30 Tage und Pending-Timeout 24 h sind fest), `privacy.*` außer `external_media_mode`, `hardening.*` (Website-Härtung geplant, R1-45), `advanced.*`. Der Wert `link_only` bei `external_media_mode` wird heute wie `always_load` behandelt (siehe [troubleshooting.md](troubleshooting.md) §8).

## 3. Zugänge (Grants), Gruppen und Plätze

**Zugänge** haben die Zustände `pending_*`, `active`, `suspended`, `expired`, `revoked` und `failed`. `expired` und `revoked` sind endgültig; erneuter Zugang entsteht als neue Revision. Ein manueller Grant braucht eine Begründung und ein Enddatum in der Zukunft (oder keines) und ist bei Doppelklick idempotent. Manuelle Sperren lassen sich nur manuell lösen; ein Widerruf gewinnt immer. Abgelaufene Zugänge und hängende `pending`-Zugänge (24 h) werden im Stundenlauf nachgezogen. Der Zugriff selbst endet aber sofort zum Enddatum, unabhängig vom Cron.

**Gruppen** (Schalter `enrollment.groups_enabled` noch ohne Wirkung):

- Eine Gruppe hat Besitzer, Kurse und eine Platzzahl. Plätze werden durch persönliche Einladungen (Token `cci_…`) oder einen Gruppenlink (`ccg_…`) belegt. Token werden nur als SHA-256 gespeichert.
- Eine offene Einladung reserviert einen Platz. Ist die Gruppe voll, meldet der Beitritt `ccap_group_full`.
- Mit `claim_policy = invited_address_only` kann nur die eingeladene Adresse beitreten; der Gruppenlink ist dann aus.
- Platz freigeben widerruft die Platz-Zugänge. Beim Verkleinern fallen zuerst die jüngsten Einladungen weg, dann die zuletzt Beigetretenen (mit Vorschau).
- Gruppe aussetzen/fortsetzen wirkt auf alle Platz-Zugänge, außer auf manuell entschiedene.
- Einladungen laufen nach 30 Tagen ab; die Adresse wird 30 Tage später gelöscht.

> **Stand:** Verwaltungsoberfläche für Zugänge und Gruppen geplant (R1-35, Support-Dialoge R1-38); Annahmeseite für Einladungslinks geplant (R1-28). Die Einladungsmail wird bereits versendet.

## 4. Lernsperre

Unter **Lernende › Detail** setzt oder hebt eine Person mit `ccap_lock_learners` (Support, Kursmanager, Admin) die Lernsperre auf (R0-13 K-12, Dienst `Policy\LearnerLock`):

- **Setzen:** Grund-Code (`abuse`, `account_compromised`, `legal_hold`, `other`; bei `other` Freitext Pflicht), optional Enddatum, optional „überall abmelden“ (beendet alle Sitzungen des Kontos). Audit `learner_locked`, bei Abmeldung zusätzlich `sessions_terminated`.
- **Aufheben:** Begründung Pflicht; Audit `learner_unlocked`.
- Nie gegen sich selbst und nie gegen Konten mit mehr Rechten (M-24). Pro Konto höchstens eine aktive Sperre.
- Zugänge, Plätze, Ergebnisse und Zertifikate bleiben unverändert; die Person erhält **keine Mail** und beim nächsten Aufruf `ccap_account_locked`. Aufgehobene Sperren werden nach 30 Tagen gelöscht (RT-22).

## 5. Benachrichtigungen und Mailvorlagen

Mails entstehen aus internen Ereignissen und werden im Stundenlauf bzw. sofort nach dem Ereignis in Stapeln (50 Empfänger oder 20 s) versendet. Jede Mail wird je Ereignis, Typ und Empfänger höchstens einmal angelegt.

| Typ | Klasse | Standard | Stand |
|---|---|---|---|
| `access_granted` (Zugang freigeschaltet) | informativ | an | aktiv |
| `seat_invitation` (Gruppeneinladung) | Pflicht | an | aktiv |
| `seat_claimed` (Gruppenbeitritt bestätigt) | informativ | an | aktiv |
| `course_completed` (Kurs abgeschlossen) | informativ | an | aktiv |
| `grading_completed` (Prüfung nachbewertet) | informativ | an | aktiv |
| `access_expired` (Zugang beendet) | informativ | **aus** | aktiv, wenn eingeschaltet |
| `test_mail` (Testmail) | Pflicht | an | aktiv (nur an die eigene Adresse, 5 je Stunde) |
| `certificate_issued`, `certificate_corrected`, `certificate_revoked` | informativ / Pflicht / Pflicht | an | geplant (Anbindung an Zertifikatsereignisse, R1-21-Folge) |
| `attempt_voided`, `attempt_annulled`, `access_transferred` | Pflicht / Pflicht / informativ | an | geplant (R1-38) |
| `assignment_submitted/returned/graded` | informativ | an | geplant (R1-46) |

Regeln:

- **Pflichtmails** lassen sich nicht abschalten. **Informative Mails** kann der Betreiber im Feld `emails.types` abschalten und jede Person für sich abbestellen (Link in jeder Mail, `List-Unsubscribe` mit One-Click). Der Betreiber kann eine Abmeldung nicht aufheben.
- Vor jedem Versand wird neu geprüft: Ausgangsmodus, Haltemodus, ob der Anlass noch gilt (z. B. Zugang inzwischen widerrufen → `suppressed`), Empfänger, Typ, Abmeldung, Ratenlimit (10 informative Mails je Empfänger und 24 h; Einladungen je Gruppe max(20, 2 × Plätze) je 24 h; eine Einladung höchstens dreimal neu senden).
- Fehlversuche werden nach 5 min, 30 min, 2 h und 12 h wiederholt, nach 5 Versuchen `failed`. Ein abgebrochener Versand mit unklarem Ergebnis wird `unknown` und **nicht** automatisch erneut gesendet.
- **Vorlagen** je Typ und Sprache (`course_captain_mail_template_<typ>`): Betreff ≤ 200 Zeichen ohne Zeilenumbruch, HTML nur `p, br, strong, em, a, ul, ol, li, h2, h3`, nur erlaubte Platzhalter je Typ. Reihenfolge der Auswahl: exakte Sprache → gleiche Sprache → Site-Sprache → Werkstext. Oberfläche für Vorlagen, Vorschau und Testmail-Knopf: geplant (R1-32).
- **Ausgangsmodus** (`COURSE_CAPTAIN_OUTBOUND` in `wp-config.php`): `on` = normal; `catch` = alle Mails an die Fangadresse (`COURSE_CAPTAIN_MAIL_CATCH_ADDRESS`, sonst `admin_email`) mit `[catch]` im Betreff; `off` = keine Mails und keine internen Zustellungen. Ohne Konstante gilt `on` nur, wenn `WP_ENVIRONMENT_TYPE = production`, sonst `catch`. Eine ungültige Fangadresse schaltet auf `off`.
- Zustellbarkeit (SPF, DKIM, DMARC, SMTP-Plugin) ist Sache des Betreibers.

## 6. Zertifikate

- Automatische Ausstellung (wenn `certificates.auto_issue` an) nach bestandener nachweisfähiger Prüfung oder Kursabschluss; je Nachweis genau ein Zertifikat. Ein Kursabschluss stellt nur aus, wenn noch kein gültiges Zertifikat dieses Kurses existiert. Voraussetzung: Zugang mit Prüfungsumfang zum Abgabezeitpunkt.
- Alle gedruckten Werte stehen in einem eingefrorenen Snapshot mit Prüfcode und Nachweis-Nr. `CC-XXXX-XXXX`. Spätere Profil- oder Kursänderungen ändern ein Zertifikat nicht.
- **Manuell ausstellen** (aus vorhandenem Nachweis, Pflichtgrund), **korrigieren** (neue Revision, alte Fassung „ersetzt“; Kurs und Person sind unveränderlich) und **widerrufen** (Kategorie `fraud`, `invalid_assessment`, `issued_in_error`, `other` + Freitext; endgültig, wirkt auf alle Revisionen) laufen über die admin-post-Aktionen `ccap_certificate_issue`, `ccap_certificate_correct`, `ccap_certificate_revoke` mit Nonce und Recht `ccap_manage_certificates`. Formulare und Liste im Admin: geplant (R1-35). PDF-Ausgabe: nicht in R1; die Druckansicht ist HTML mit A4-Druckstil.
- Wird eine bestandene Prüfung nachträglich als nicht bestanden bewertet, wird das Zertifikat **nicht** automatisch widerrufen; es entsteht ein Protokolleintrag „Certificate needs review after re-evaluation“.
- Öffentliche Verifikation unter `/lernen/nachweis/<token>` und per Block *Certificate verify*: zeigt nur Status, Kurs, Datum und – mit Freigabe – den Namen. 30 Abrufe je Minute und IP.

## 7. Import und Export

Paketformat mit Manifest, Prüfsummen und Validierung (keine Pfade außerhalb, keine gefährlichen Dateitypen, ZIP-Bomben-Schutz), Trockenlauf mit Aktionen *create/update/unchanged/conflict/archive/error*, Freigabe, Import, Aktivierung und Rollback als neuer Import aus der Paketkopie (Pflichtbegründung). Lokal geänderte Objekte werden nicht überschrieben (`conflict`). Export enthält Kurse, Module, Lektionen und Poolfragen ohne Lernendendaten.

> **Stand:** Dienste vorhanden, **keine Oberfläche und keine REST-Route** (geplant R1-28/R1-36); Medien, Blueprints und Karteikarten im Paket sowie Großimporte geplant (R1-41).

## 8. Website-Zustand und Protokoll

**Website-Zustand** (Werkzeuge › Website-Zustand) enthält vier Course-Captain-Prüfungen – siehe [troubleshooting.md](troubleshooting.md) §4. Eigener Systemstatus-Screen, Diagnosepaket, Debug-Logging, Health-Endpunkt und Reparaturaktionen: geplant (R1-38).

**Audit log** (Course Captain › Audit log, Recht `ccap_view_audit_log`): Filter nach Nutzer, Bereich, Ereignis, Zeitraum und Objekt-ID, seitenweise, E-Mail-Adressen maskiert. Einträge sind über eine Hash-Kette verknüpft und werden nicht geändert. Protokolliert werden u. a. Veröffentlichungen, Einstellungen, Importe, Nachbewertungen, Zertifikate, Mailvorlagen, Abmeldungen, Datenlöschungen, Aufbewahrungsläufe und fehlgeschlagene Wartungsschritte. Export des Protokolls: geplant (R1-38).

## 9. Datenschutz

Siehe [datenschutz.md](datenschutz.md). Kurz: Export und Löschung laufen über Werkzeuge › Personenbezogene Daten exportieren/löschen; Course Captain trägt sich dort als „Course Captain“ ein und schlägt unter Einstellungen › Datenschutz einen Text für die Datenschutzerklärung vor.
