# R0-25 · ADR Site-Scope, Multisite, Mandanten und Sprach-/Locale-Modell

**ADR-Nr.:** R0-25
**Status:** **Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet.** Keine Implementierung, kein Testprotokoll. Alle Testfälle sind Vorgaben für die genannten R1-Tasks.
**Stand:** 16.11.2026
**Bezug:** TODO R0-25 (DoD: „Single-Site-Scope, Multisite-Nichtumfang, Locale-Fallback, Sprachversionen und gemischte Kurs-/Fragensprachen als ADR dokumentiert“), Voraussetzungen R0-07, R0-15; Konzept Kap. 17 (Rollen, Mandanten- und Inhaltszugriff), Kap. 21 (mehrsprachige Inhalte, Übersetzungsplugins), Kap. 35.2 (Grants sitegebunden), Kap. 37; `docs/r0/R0-15-barrierefreiheit-i18n.md` §3.5, §3.6, §4 (Ü-1…Ü-7); `docs/r0/R0-06-datenmodell-adr.md` §6.6 (Multisite-Scope), D-3; `docs/r0/R0-07-rbac-idor-matrix.md` §3 (Site-Scope, Super-Admin, gesperrter Nutzer), RBAC-010/-078; `docs/r0/R0-16-backup-recovery-deployment.md` (Site-Identität, Uninstall, REC-019/-024); `docs/r0/R0-02-inventar/C-infrastruktur-content.md` (INV-C-004/-007/-008/-024/-058/-148/-154/-159/-173/-192, E-C-01); `docs/r0/R0-10-import-medien-sicherheit.md` (Paketfelder `languages`, `language`); `docs/r0/R0-22-kursvalidierung-publish.md` (VAL-002); `docs/r0/ENTSCHEIDUNGEN.md`.
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77`, nur gelesen. Pfade relativ zu `bootspruefung-learning/`.

## Beschlussstand

Quelle: `ENTSCHEIDUNGEN.md`, Beschlussprotokoll und „Von Claude entschieden“ (16.11.2026). Alle Punkte aus §7 sind entschieden.

| ID | Beschluss | Wirkung hier |
|---|---|---|
| **E-01** | Clean Room: kein LB-Code wird übernommen | LB-Fundstellen in §0 sind Verhaltensbelege; Site-Löschhaken, Orphan-Prüfung und Netzwerkblockade werden nach Verhalten neu implementiert |
| E-155 (D-25-01) | Eigener Kurs je Sprache, verknüpft über eine Übersetzungsgruppe; Fortschritt, Grants und Zertifikate je Kurs | §2.3 Ü-1 |
| E-156 (D-25-02) | Kein Grant-Übertrag zwischen Sprachversionen in R1; Sammelvergabe durch den Kursmanager möglich | §2.3 Ü-1 |
| E-157 (D-25-03) | Gemischte Sprachen erlaubt mit `lang`-Kennzeichnung und Validator-Warnung | §2.3 Ü-2 |
| E-158 (D-25-04) | Zertifikat in der Kurssprache, im Snapshot eingefroren | §3.3 |
| E-159 (D-25-05) | Sprachvarianten für Mailvorlagen schon in Free | §2.3 Ü-5, §3.2 |
| E-160 (D-25-09) | Keine Mandanten in R1, kein `tenant_id` | §1.4 |
| D-25-06, -07, -08, -10 | Technisch festgelegt (ENTSCHEIDUNGEN §24): Grenze zu Übersetzungsplugins nur dokumentieren mit Systemstatus-Hinweis; Varianten-Fallback `de_AT` → `de_DE`; Haltemodus bei erzwungener Netzwerkaktivierung; Profilsprache für Angemeldete, Site-Sprache für Gäste | §2.3 Ü-4, §3.0, §1.2, §3.1 |
| **E-196** | Keine Lizenzen kommerzieller Plugins | WPML-Koexistenz nur best effort; regelmäßig getestet wird Polylang (§2.3 Ü-4, LOC-028) |
| E-25a | Bundle (ein Grant schaltet mehrere Kurse frei) in R1 Free | Mehrere Sprachversionen lassen sich schon in R1 über ein Bundle gemeinsam vergeben (§2.3 Ü-1) |
| **E-207** | Präfix `ccap_` für CPTs, Caps, Rollen, Cron, Meta und Tabellen | Bezeichner sind auf `ccap_` umgestellt (`ccap_course`, `_ccap_translation_group`, `ccap_`-Tabellen u. Ä.; E-285) |

**Weitere zugrunde liegende Beschlüsse:**

| ID | Beschluss | Wirkung hier |
|---|---|---|
| E-07 | Offiziell nur Single-Site; Aktivierung je Site technisch korrekt; Pro-Lizenz zählt je Site | §1 |
| E-49 | Netzwerkaktivierung in R1 blockieren, Schema trotzdem je Site | §1.2 |
| E-121 | Quellsprache der UI-Strings `en_US`, Deutsch erste Übersetzung | Letztes Glied jeder Fallback-Kette (§3) |
| E-124 | Lernbereich in der Profilsprache (Einstellung, Standard an bei > 1 installierter Sprache); Fristen mit Zonenkennung, keine Nutzerzeitzone | §2.1, §3.1 |
| E-43 | Kontolöschung nach Datenschutzantrag Standard aus | §1.3 (netzweite Kontolöschung) |

Alle genannten Beschlüsse sind getroffen; die Abschnitte unten sind entsprechend angepasst.

**Normative Sprache:** **MUSS** = Vertragsbestandteil, **SOLL** = Standard, **KANN** = optional.

---

## 0. Kontext

1. **WordPress-Grenzen.** Eine Site hat eigene Tabellen (`$wpdb->prefix`), Optionen, Rollen (`{prefix}user_roles`), Cron (Option `cron`), Rewrite-Regeln und Uploads. Netzweit geteilt sind `wp_users` und `wp_usermeta`. Super-Admins bestehen auf jeder Site jede Capability außer `do_not_allow` (R0-07 §3).
2. **LearnBoating** blockiert die Netzwerkaktivierung (`Plugin.php:75-79`, `activate(bool $networkWide)` → `wp_die`), lehnt den Netzwerk-Admin ab (`Infrastructure/Requirements.php:41`, INV-C-058), ergänzt `wpmu_drop_tables` um die eigenen Tabellen in umgekehrter Abhängigkeitsreihenfolge (`Plugin.php:160`, `dropTablesOnSiteDelete`, INV-C-007), findet Tabellen gelöschter Sites (`Plugin.php:176`, `orphanSiteTables`, INV-C-008; Begründung im Code: WP löscht sie nur, wenn das Plugin in der Lösch-Anfrage geladen ist), deinstalliert je Site mit `switch_to_blog` (INV-C-024), löscht Lerndaten bei netzweiter Kontolöschung auf allen Sites mit Tabellen (INV-C-148/-159) und hat `cleanup-sites [--apply]` (INV-C-173) samt Multisite-Integrationstest (INV-C-192). Super-Admins sind vor automatischer Kontolöschung geschützt (`Privacy/AccountDeletion.php:80`).
3. **LearnBoating-Sprache:** Inhalte tragen `language varchar(10) 'de-DE'` (Kurs, Frage; Inventur C §Schema), die App bekommt `locale` aus `determine_locale()` (`Frontend/Router.php:190`), es gibt keinen Wechsel in die Empfängersprache bei Mails außer einer Stelle (`WithdrawalTexts.php:111`) und keine Sprachversionen von Kursen.
4. **Course-Captain-Datenmodell (R0-06):** Kurs, Modul, Lektion sind CPTs (`ccap_course`, `ccap_module`, `ccap_lesson`); Fragen, Prüfungsregeln, Versuche, Fortschritt, Grants und Zertifikate liegen in eigenen `ccap_`-Tabellen. Übersetzungsplugins kennen die Tabellen nicht.
5. **Offene Fragen:** R0-15 §4 übergibt Ü-1…Ü-7; R0-06 D-3 und Inventur C E-C-01 übergeben den Multisite-Umfang; Konzept Kap. 17 nennt „Mandanten“ ohne Definition; Konzept Kap. 21 verlangt eine „Modellentscheidung“ vor Kurs-Sprachvarianten.

---

## 1. Entscheidung A (beschlossen): Site-Scope, Multisite, Mandanten

### 1.1 Single-Site-Scope R1

- **MUSS:** Eine Course-Captain-Installation ist **eine Site**. Alle fachlichen Objekte (Kurse, Fragen, Grants, Gruppen, Fortschritt, Versuche, Zertifikate, Audit, Einstellungen) gehören genau zu der Site, in deren Präfix sie liegen. Es gibt keinen Fremdschlüssel, keine Abfrage und keine REST-Route, die eine andere Site liest (RBAC-010, RBAC-078: fremde IDs ergeben 404).
- **Supportstatus:** Single-Site ist offiziell unterstützt. Multisite mit Aktivierung je Site ist **„funktioniert, nicht beworben“** (E-C-01), bis die MS-Suite (§4.2) in R1-30 grün ist; danach in der Doku als „unterstützt mit Einschränkungen §1.3“ aufgeführt. Die Pro-Lizenz zählt je Site (E-07).

### 1.2 Multisite: was technisch korrekt sein MUSS

| Nr. | Regel | LB-Beleg | Ziel-Task |
|---|---|---|---|
| MS-R-01 | **Netzwerkaktivierung blockiert:** `activate( $network_wide = true )` bricht mit verständlicher Meldung ab, **bevor** Optionen, Caps, Tabellen, Cron oder Rewrite-Regeln geschrieben werden (auch über WP-CLI `--network`). Im Netzwerk-Admin wird der Aktionslink „Netzwerkweit aktivieren“ über `network_admin_plugin_action_links_{plugin}` entfernt. | `Plugin.php:77-79` | R1-01 |
| MS-R-02 | **Erzwungene Netzwerkaktivierung** (Eintrag in `active_sitewide_plugins` direkt in der DB oder per Code): Das Plugin bootet im **Haltemodus** – keine Frontend-/REST-Routen, keine Migration, kein Cron; Hinweis im Netzwerk-Admin und auf jeder Site für Admins (→ D-25-08). | – (neu) | R1-01 |
| MS-R-03 | Kein Course-Captain-Menü im Netzwerk-Admin; `is_network_admin()` lädt keine Admin-Screens. | `Requirements.php:41` | R1-01 |
| MS-R-04 | **Je Site:** Tabellen `{$wpdb->prefix}ccap_*`, Optionen, Locks, Schema-Version, Site-Identität (R0-16), Rewrite-Regeln, privater Ordner `<privat>/sites/<blog_id>/…` (Hauptsite ohne Unterordner, wie WP-Uploads), Capabilities an den Rollen **dieser** Site, Cron-Ereignisse in der Cron-Option **dieser** Site. | `Plugin.php` (Prefix), INV-C-004 | R1-01, R1-02 |
| MS-R-05 | **Nutzerbezogene Werte** (Einstellungen der Lernenden, Hinweis-Quittungen, Onboarding-Status) MÜSSEN per `update_user_option()`/`get_user_option()` (Schlüssel mit Site-Präfix) gespeichert werden, **nie** per `update_user_meta()` mit festem Schlüssel. Einzige Ausnahme ist die WP-Profilsprache `locale` (WP-Kern, netzweit). | – (neu; LB nutzt teils feste Meta-Schlüssel) | R1-02, R1-03 |
| MS-R-06 | Objekt-Cache nur in nicht-globalen Gruppen (WP trennt sie je Blog); PolicyService-Memo und Repository-Memos werden bei `switch_blog` verworfen (R0-07 §3). | – | R1-03, R1-25 |
| MS-R-07 | `switch_to_blog()` nur in: Uninstall, netzweiter Kontolöschung, `cleanup-sites`, Orphan-Diagnose. Nie im normalen Request-Pfad. Jeder Wechsel endet in `finally` mit `restore_current_blog()`. | INV-C-024, -159 | R1-17, R1-23 |
| MS-R-08 | **Upgrade lazy je Site** (R0-06 §6.6): Der Schema-Trigger läuft beim ersten Request der Site; CLI-Schleife über `wp site list` (R0-37). | – | R1-02, R1-40 |
| MS-R-09 | **Neue Site im Netzwerk:** Da nur je Site aktiviert wird, passiert beim Anlegen nichts. Wird auf der neuen Site aktiviert, läuft die vollständige Aktivierung wie bei Single-Site. | – | R1-01 |
| MS-R-10 | **Deaktivierung je Site** entfernt nur Cron-Ereignisse und Rewrite-Regeln dieser Site, keine Daten. | INV-C-004 | R1-01 |
| MS-R-11 | **Site-Löschung räumt auf:** Filter `wpmu_drop_tables` ergänzt alle `ccap_`-Tabellen der gelöschten Site (Präfix über `get_blog_prefix( $site_id )`) in umgekehrter Abhängigkeitsreihenfolge; Aktion `wp_uninitialize_site` löscht den privaten Ordner der Site. Läuft die Löschung ohne geladenes Plugin, findet die Diagnose (Health, A-16) Tabellen **und** private Ordner ohne Site; `wp course-captain cleanup-sites` zeigt sie (Trockenlauf) und löscht erst mit `--apply`. | `Plugin.php:160`, `:176`; INV-C-173 | R1-17, R1-38 |
| MS-R-12 | **Site archiviert / als Spam / deaktiviert markiert:** keine Datenänderung; Cron läuft dort ohnehin nicht. Nach Reaktivierung arbeitet die Site normal weiter (abgelaufene Fristen werden beim nächsten Lauf nachgezogen, R0-08). | – | R1-17 |
| MS-R-13 | **Uninstall** (im Netzwerk einmal ausgelöst): Schleife über Sites mit `ccap_`-Tabellen; Daten nur dort löschen, wo die Site `delete_data_on_uninstall` gesetzt hat; netzweite Nutzer-Meta nur, wenn **keine** Site ihre Daten behält (INV-C-024, R0-16, REC-019). | INV-C-024 | R1-23 |
| MS-R-14 | **Nutzer und Site:** Konto ohne Rolle auf der Site (`remove_user_from_blog`) oder netzweit `spam`/`deleted` = gesperrter Nutzer (R0-07 §3: Lern-Caps verweigert, Grants unverändert, Daten bleiben). Netzweite Kontolöschung (`wpmu_delete_user`) startet den Eraser auf **allen** Sites mit `ccap_`-Tabellen, auch wo das Plugin deaktiviert ist (INV-C-148/-159). Entfernen aus einer Site allein löscht keine Lerndaten. | INV-C-148, -154, -159 | R1-17, R1-34 |
| MS-R-15 | **Restore einer Site** lässt andere Sites unverändert; Locks und Site-Identität sind je Site (REC-024). | – | R1-23 |

### 1.3 Ausdrücklich **nicht** unterstützt (R1, kein Bug, kein Support)

| Nr. | Nicht unterstützt | Folge / Hinweis in Doku und Systemstatus |
|---|---|---|
| NS-01 | Netzwerkaktivierung, netzweite Einstellungen, Netzwerk-Admin-Screens | MS-R-01…03 |
| NS-02 | **Cross-Site-Kurse:** Kurs, Modul, Lektion, Frage, Prüfungsregel oder Medium einer Site auf einer anderen Site anzeigen, einbetten oder referenzieren; gemeinsame Fragenbank | Austausch nur über Export/Import-Paket (R0-10) |
| NS-03 | **Netzweite Lernende/Fortschritte:** dasselbe WP-Konto hat auf zwei Sites zwei unabhängige Lernstände, Grants, Gruppenplätze, Zertifikate | Cross-Site-Identität bleibt R4 (R0-07 ID-06) |
| NS-04 | Netzweite Berichte, Exporte, Audit-Ansicht, Lizenz | je Site; Pro-Lizenz je Site (E-07) |
| NS-05 | **Super-Admin-Sonderrechte:** Course Captain gewährt Super-Admins **keine** eigene Fähigkeit über das hinaus, was WP ihnen ohnehin gibt (Pass-through aller Caps außer `do_not_allow` auf der jeweils aktuellen Site). Kein netzweiter Lernenden-Blick, kein Sprung zwischen Sites im Plugin. Strukturelle Verbote (Selbstbewertung M-10, Selbstsperre M-24) gelten auch für Super-Admins (RBAC-032). Super-Admin-Handlungen werden im Audit der Site wie Admin-Handlungen protokolliert, mit Kennzeichen `actor_is_super_admin`. Super-Admins sind von automatischer Kontolöschung ausgenommen (LB `AccountDeletion.php:80`). | DPIA-Hinweis an R0-17: Super-Admins sehen WP-bedingt die Lerndaten jeder Site, auf der sie arbeiten |
| NS-06 | Domain-Mapping-Plugins mit Wechsel der `home_url` ohne Neuaufnahme | löst Haltemodus der Site-Identität aus (R0-16 R16-D1), Freigabe je Site |
| NS-07 | Gemeinsame Tabellen mehrerer Sites (`base_prefix`-Tabellen) | nicht vorgesehen; spätere Freigabe der Netzwerkaktivierung braucht keine Migration (R0-06 D-3) |

### 1.4 Mandantenfähigkeit

- **Entscheidung (E-160):** **Keine Mandanten innerhalb einer Site in R1.** Es gibt keine Spalte `tenant_id`, keine mandantenbezogenen Einstellungen, Kataloge, Marken oder Admin-Bereiche.
- **Was es stattdessen gibt (keine Mandanten):** Objekt-Scope nach R0-07 – Instructor sieht eigene/zugeordnete Kurse, Tutor zugeordnete Bewertungen, Gruppenbesitzer die eigene Gruppe. Das ist **Zugriffsbegrenzung**, keine Datenisolation: Course Manager und Admin sehen alles auf der Site; Einstellungen, Mailvorlagen, Design und Katalog gelten für die ganze Site.
- **Weg für echte Mandanten** (getrennte Marken, Admins, Einstellungen, Kataloge): getrennte Sites (Multisite je Site, §1.2, mit NS-02/03) oder getrennte Installationen. Ein Mandantenmodell innerhalb einer Site ist R4-Kandidat und braucht ein eigenes ADR.
- **Begründung gegen vorsorgliche `tenant_id`:** würde jede Abfrage, jeden Unique-Index (`UNIQUE uid`, `source`), Cache-Schlüssel, Rechte-Check und Test verdoppeln, ohne dass eine R1-Funktion sie nutzt; eine später ergänzte Spalte ist per Expand/Contract migrierbar (R0-06 §6.2), eine falsch vorweggenommene Semantik nicht.

---

## 2. Entscheidung B (beschlossen): Sprach- und Locale-Modell

### 2.1 Drei Ebenen

| Ebene | Was | Quelle | Format |
|---|---|---|---|
| **UI-Sprache** | Texte von Course Captain (Knöpfe, Meldungen, Mail-Rahmen, Zertifikats-Beschriftungen) | `.mo`/`.l10n.php` + JSON der Textdomains `course-captain`, `course-captain-pro`; aufgelöst nach §3 | WP-Locale (`de_DE`, `de_DE_formal`); an den Client als BCP 47 (`de-DE`) |
| **Inhaltssprache** | Kurs, Lektion, Frage samt Antworten/Erklärungen, Zertifikatstext, betreiberdefinierte Mailtexte | vom Autor gepflegtes Kennzeichen | **BCP 47** (`de-DE`, `en`, `pt-BR`, `ar`), gespeichert in `language varchar(20)` |
| **Formatsprache** | Datum, Zahl, Prozent | immer = aufgelöste **UI-Sprache** (nicht Inhaltssprache); Zeitzone = Website (E-124) | – |

- **MUSS:** Inhaltssprache muss **nicht** als WP-Sprachpaket installiert sein (ein englischer Kurs auf einer rein deutschen Site ist gültig). Validierung: syntaktisch gültiges BCP-47-Tag (Sprache[-Schrift][-Region]). → **Delta zu R0-22 VAL-002**: „gültiges BCP-47-Tag“ statt „gültige WP-Locale“; Quick-Fix bleibt „Site-Sprache übernehmen“ (umgerechnet `de_DE` → `de-DE`; `de_DE_formal` → `de-DE`).
- **MUSS:** `dir` wird aus dem Tag abgeleitet (Skripte/Sprachen `ar`, `he`, `fa`, `ur`, `yi`, `ps`, `dv`, `ckb`, Schrift `Arab`/`Hebr`/`Thaa` → `rtl`), nicht separat gespeichert. Ableitung zentral in einem `LanguageTag`-Wertobjekt.

### 2.2 Wo die Inhaltssprache sitzt

| Objekt | Feld | Standard | Vererbung |
|---|---|---|---|
| Kurs `ccap_course` | Meta `_ccap_language` | Site-Sprache bei Anlage | – |
| Modul `ccap_module` | – | – | erbt vom Kurs (Module sind Struktur, kein eigener Text mit Sprache) |
| Lektion `ccap_lesson` | Meta `_ccap_language` **optional** | leer = Kurssprache | Abschnitte darin über Block-Attribut `lang` des Editors (WP-Standard) |
| Frage `ccap_question` | Spalte `language` (R0-06) | Kurssprache des Ziel-Pools bei Anlage, sonst Site-Sprache | Antworten, Erklärung, Feedback erben von der Frage |
| Zertifikat | `snapshot.content_language`, `snapshot.locale` | §3.3 | eingefroren |
| Mailvorlage | Schlüssel `locale` je Variante | §3.2 | – |

- Kursrevision (`ccap_course_revision.structure`) enthält `language` und je Lektion die effektive Sprache; eine Sprachänderung am veröffentlichten Kurs erzeugt eine neue Revision, laufende Versuche behalten ihren Snapshot (R0-23).

### 2.3 Antworten auf R0-15 Ü-1…Ü-7

**Ü-1 Sprachversionen eines Kurses → eigener Kurs je Sprache, verknüpft (Option A, beschlossen E-155).**
- Jede Sprachversion ist ein vollständiger, eigenständiger `ccap_course` mit eigenen Modulen, Lektionen, Fragen, Prüfungsregeln, Revisionen. Verknüpfung über Meta `_ccap_translation_group` (UUID v4, gleich für alle Versionen) – keine eigene Tabelle.
- **MUSS:** je Gruppe höchstens ein Kurs je Inhaltssprache (Publish-Validator blockiert Duplikat → neue Regel VAL-0xx in R0-22, „In dieser Sprachgruppe gibt es schon einen Kurs auf {Sprache}.“). Prüfung unter dem Kurs-Lock beim Verknüpfen und beim Veröffentlichen.
- Admin A-04 (Kurs bearbeiten › Metadaten): „Sprachversionen“ mit Liste der verknüpften Kurse, Aktionen „Mit bestehendem Kurs verknüpfen“, „Als neue Sprachversion kopieren“ (Kopie aller Strukturen als Entwurf, Inhaltssprache neu gesetzt, Fragen kopiert mit neuer `uid` und Quelle `local`), „Verknüpfung lösen“.
- **Fortschritt, Mastery, Grants, Zertifikate sind je Kurs.** Kein automatischer Übertrag zwischen Sprachversionen in R1 (E-156): ein Grant gilt für genau einen Kurs bzw. die Kurse eines Bundles (R0-09, E-25a); Mastery hängt an `question_id`, und die Fragen der Versionen sind verschiedene Datensätze; ein Zertifikat nennt genau einen Kurs. Ein Wechsel der Sprachversion durch Lernende ist in R1 ein zusätzlicher Grant durch Berechtigte, auch als Sammelvergabe durch den Kursmanager (E-156). Mehrere Sprachversionen in einem Zugangsprodukt sind über ein Bundle schon in R1 Free möglich (E-25a; bisher „R2“).
- Lernoberfläche: Kursübersicht L-xx zeigt „Auch verfügbar auf: …“ nur für Versionen, auf die die Person Zugriff hat oder die öffentlich im Katalog sind.
- **Verworfen: Übersetzungsfelder** (ein Kurs, Varianten je Lektion/Frage). Gründe in §5 Alternative B2.

**Ü-2 Gemischte Sprachen → zulässig, gekennzeichnet, gewarnt (beschlossen E-157).**
- Ein Kurs darf Lektionen und Fragen in anderen Sprachen enthalten (Sprachkurse, Fachbegriffe, internationale Prüfungen). Jede abweichende Einheit bekommt `lang`/`dir` am Container (R0-15 §4 Punkt 2, A11Y-032).
- Publish-Validator: weicht die Sprache einer Frage/Lektion von der Kurssprache ab, **Warnung** (nicht blockierend) „{n} Fragen sind auf {Sprache}, der Kurs auf {Kurssprache}.“ – unterdrückt, wenn am Kurs „Mehrsprachiger Kurs“ (Meta `_ccap_mixed_language = 1`) gesetzt ist.
- Prüfungs-Blueprint: **kein Sprachfilter in R1**; der Blueprint zieht aus den Modulpools wie gehabt. Wer einsprachige Prüfungen will, hält einsprachige Pools (Sprachversion = eigener Kurs, Ü-1). Snapshot speichert je Frage die Sprache (aus der Revision), damit Auswertung und Nachweis reproduzierbar sind.

**Ü-3 Fehlende Übersetzung einer Frage → kein Laufzeit-Fallback für Inhalte.**
- Da Sprachversionen eigene Kurse mit eigenen Pools sind, gibt es keine „Übersetzung einer Frage“, die zur Laufzeit fehlen könnte. Eine Frage wird immer in ihrer eigenen Sprache mit `lang` gezeigt; Course Captain übersetzt oder ersetzt Inhalte nie automatisch.
- Fehlt beim Kopieren einer Sprachversion (Ü-1) die Übersetzung, bleibt die Kopie Entwurf mit Originaltext und Originalsprache; der Validator meldet „{n} Fragen noch auf {Originalsprache}“ als Warnung (Ü-2) bzw. Autor setzt den Kurs auf „Mehrsprachig“. Fallback-Ketten gelten nur für **UI-Texte, Mailvorlagen und Zertifikatsbeschriftungen** (§3).

**Ü-4 Übersetzungsplugins (WPML, Polylang, TranslatePress) → Grenzen dokumentiert, keine Integration in R1 (→ D-25-06).**

| Bereich | Grenze R1 |
|---|---|
| CPTs `ccap_course`, `ccap_module`, `ccap_lesson` | Course Captain registriert sie **nicht** als übersetzbar. Ein vom Plugin dupliziertes „Übersetzungs“-Post wäre fachlich ein **neuer Kurs** ohne Fragen, Prüfungsregeln, Revisionen, Grants (die liegen in `ccap_`-Tabellen). Empfehlung: diese CPTs im Übersetzungsplugin auf „nicht übersetzen“ lassen; Sprachversionen über Ü-1 anlegen. |
| `ccap_`-Tabellen (Fragen, Regeln, Versuche, Zertifikate) | werden von keinem Übersetzungsplugin erfasst; **nicht unterstützt** |
| String-Übersetzung von Einstellungen/Mailvorlagen über Plugin-String-Übersetzer | nicht unterstützt; Mailvorlagen haben eigene Sprachvarianten (Ü-5) |
| Seiten mit Course-Captain-Blöcken/Shortcodes (Katalog-, Kursdetailseite) | dürfen vom Plugin übersetzt werden; der Block rendert seine UI-Texte in der vom Plugin gesetzten Request-Sprache (`determine_locale()`), die Kursinhalte in ihrer Inhaltssprache |
| Lernbereich (eigene Route) | nicht vom Plugin übersetzt; Sprache nach §3.1 (Profilsprache gewinnt, → D-25-10) |
| URL-Präfixe (`/en/…`), `hreflang`, Sitemaps | Sache des Übersetzungs-/SEO-Plugins (Kap. 21). Course Captain gibt für Sprachversionen keine eigenen `hreflang`-Tags aus. |
| TranslatePress (Ausgabe-Übersetzung per HTML-Ersetzung) | Lernbereich und REST-Antworten der Lern-App sind ausgenommen (Doku-Hinweis); Ersetzen von Prüfungstexten im Ausgabestrom verfälscht Snapshot-Treue und ist nicht unterstützt |
| Testmatrix | R1-30 prüft Koexistenz (kein Fatal, keine doppelten Tags, Lernbereich in korrekter Sprache) mit Polylang je aktueller Version; WPML ist kommerziell und ohne Lizenz nur best effort (E-196, R0-20 KOMP-090); TranslatePress nur Smoke-Test. Kein Funktionsversprechen darüber hinaus. |
| Systemstatus A-16 | Hinweis, wenn ein Übersetzungsplugin aktiv ist **und** einen `ccap_`-CPT als übersetzbar führt (WPML-Einstellung bzw. Polylang `pll_is_translated_post_type`), mit Link zur Doku. Nur Lesen, keine Konfigurationsänderung. |

**Ü-5 Mehrsprachige Mail- und Zertifikatsvorlagen → Sprachvarianten je Vorlage (beschlossen E-159; Zertifikat E-158).**
- Jede betreiberdefinierte Mailvorlage (R1-21) hat eine **Standardvariante** (Sprache = Site-Sprache bei Anlage) und KANN Varianten für weitere installierte Sprachen haben (Auswahl nur installierter Locales). Speicherung je Variante mit Schlüssel `(template_key, locale)`; Auflösung nach §3.2. → **Delta zu R0-06/R0-27** (Speicherort der Vorlagen mit Locale-Schlüssel).
- Basiszertifikat (Free, HTML/Druck): Beschriftungen kommen aus der Textdomain (übersetzbar), der Freitext des Betreibers hat Varianten wie Mailvorlagen. Pro-Designer (R3): Vorlage mit Sprachvarianten, gleiche Auflösung (§3.3).

**Ü-6 Katalog → Filter nach Inhaltssprache, kein `hreflang`.**
- L-01 zeigt einen Filter „Sprache“ nur, wenn veröffentlichte Kurse in mehr als einer Inhaltssprache existieren; Sprachname in der UI-Sprache (`Intl.DisplayNames` bzw. `locale_get_display_language()`, Rückfall: Tag). Standardfilter: keiner. Je Sprachgruppe KANN der Katalog nur die Version in der aufgelösten UI-Sprache zeigen (Einstellung „Sprachversionen zusammenfassen“, Standard aus).
- Kursdetailseite: `lang` am Inhaltscontainer = Kurssprache; Schema.org `inLanguage` = Kurssprache (R1-37). `hreflang` siehe Ü-4.

**Ü-7 Import → Paketsprachen bleiben, keine Umdeutung.**
- Jede importierte Entität behält ihr `language` aus dem Paket (R0-10 Gemeinsame Felder). Fehlt es, gilt das erste Element von `languages[]` des Manifests; fehlt auch das, Abbruch im Dry-run mit Fehler (keine stille Annahme der Site-Sprache).
- Ein Paket mit mehreren Sprachen wird unverändert importiert; Kurse mit gleichem optionalem Feld `translation_group` (neu, → **Delta zu R0-10**: optionales UUID-Feld am Kurs) werden verknüpft. Kollidiert eine Sprache in einer bestehenden Gruppe, meldet der Dry-run einen Konflikt (kein Überschreiben).
- Ist eine Inhaltssprache nicht als WP-Sprache installiert: **Hinweis**, kein Fehler (§2.1). Ungültige Tags: Fehler.
- Export schreibt `language` je Entität und `translation_group`, wenn gesetzt.

---

## 3. Entscheidung C (beschlossen): Locale-Fallback-Ketten

### 3.0 Gemeinsame Auflösung einer Locale zu einer Übersetzungsdatei (→ D-25-07)

Für die eigenen Textdomains `course-captain`/`course-captain-pro` gilt bei gewünschter Locale `L`:
1. Datei für `L` vorhanden (Sprachpaket unter `wp-content/languages/plugins/` hat Vorrang, dann mitgeliefert) → verwenden.
2. Sonst **Variante derselben Sprache** nach fester Tabelle: `de_AT`, `de_CH`, `de_CH_informal`, `de_DE_formal` → `de_DE`; `de_DE` → `de_DE_formal`; `en_*` → Quelltexte; `es_*` → `es_ES`; `fr_*` → `fr_FR`; `pt_PT` ↔ `pt_BR`; `nl_BE` → `nl_NL`; sonst erste installierte Datei mit gleichem Sprachcode. Umsetzung über Filter `load_translation_file`/`lang_dir_for_domain` nur für die eigenen Domains; JS-JSON analog über `load_script_translation_file`.
3. Sonst **Quelltexte `en_US`** (E-121).
- Die aufgelöste Locale (nicht die gewünschte) bestimmt `lang` am Container und die Formatsprache, damit Text und Formate zusammenpassen.

### 3.1 Oberfläche

| Kontext | Kette (erste vorhandene gewinnt, danach §3.0) |
|---|---|
| Admin (alle Course-Captain-Screens) | Profilsprache (`get_user_locale()`, WP-Standard) → Site-Sprache |
| Lernbereich, angemeldet, Einstellung „Profilsprache“ an (E-124) | Profilsprache der Person → Site-Sprache. Gilt auch, wenn ein Übersetzungsplugin für die Seite eine andere Sprache setzt (→ D-25-10) |
| Lernbereich, angemeldet, Einstellung aus | Request-Sprache (`determine_locale()`, also ggf. Übersetzungsplugin) → Site-Sprache |
| Gäste: Katalog, Kursdetail, Einladung L-17, Verifikation L-16, Blöcke/Shortcodes auf Seiten | Request-Sprache (`determine_locale()`) → Site-Sprache |
| REST der Lern-App | wie die Seite, die sie gestartet hat: Client sendet `_locale=user`, wenn der Lernbereich in Profilsprache läuft; sonst Request-Sprache. Fehlertexte (`message`) in dieser Locale, Codes nie übersetzt (I18N-R-08) |
| WP-CLI, Cron ohne Empfänger | Site-Sprache |

Inhalte werden nie nach dieser Kette übersetzt; sie tragen ihre Inhaltssprache (§2).

### 3.2 E-Mails

| Schritt | Regel |
|---|---|
| Empfänger-Locale | bekanntes Konto: `get_user_locale( $user_id )` (Profilsprache, leer → Site-Sprache), **unabhängig** von der Lernbereich-Einstellung (I18N-R-30). Konto ohne Profil (Einladung an Adresse): in der Einladung gewählte Sprache → Sprache der einladenden Person → Site-Sprache (I18N-R-32). Admin-Benachrichtigung: Profilsprache des jeweiligen Admins. |
| Rahmen-/Systemtexte | `switch_to_user_locale()` bzw. `switch_to_locale()` auf die Empfänger-Locale, Auflösung nach §3.0; `restore_previous_locale()` in `finally`; je Empfänger neu (I18N-R-31). |
| Betreibervorlage (Ü-5) | 1. Variante exakt für die Empfänger-Locale → 2. Variante gleicher Sprache (Tabelle §3.0) → 3. **Standardvariante** des Betreibers (auch wenn sie in anderer Sprache ist: Betreiberformulierung geht vor Werkstext) → 4. mitgelieferter Werkstext der Vorlage, übersetzt in die Empfänger-Locale. |
| Sprachkennzeichen | `<html lang dir>` = Sprache der verwendeten Vorlage (Schritt 1–3) bzw. Empfänger-Locale (Schritt 4); weichen Rahmen und Vorlage ab, trägt der Vorlagenblock eigenes `lang`/`dir`. Betreff in der Sprache des Vorlagentexts. |
| Platzhalter | Datum/Zahl in der **Rahmen-Locale** (= aufgelöste Empfänger-Locale); Kurstitel unverändert in Inhaltssprache, eingebettet mit `<bdi>`/`dir="auto"` (I18N-R-15). |
| Outbox | Die Outbox speichert `locale_requested` und `locale_resolved` je Nachricht (Diagnose, keine Wiederverwendung über Empfänger). |

### 3.3 Zertifikate (beschlossen E-158)

| Schritt | Regel |
|---|---|
| Zertifikats-Locale bei Ausstellung | 1. Kurssprache (BCP 47 → WP-Locale, z. B. `de-AT` → `de_AT`), aufgelöst nach §3.0, sofern dabei eine Übersetzung der eigenen Domain gefunden wird → 2. Site-Sprache → 3. `en_US`. |
| Vorlagentext (Freitext/Pro-Designer) | Variante für die Zertifikats-Locale → gleiche Sprache → Standardvariante → Werkstext |
| Einfrieren | Snapshot (R0-08 §7.2) speichert `content_language` (Kurssprache), `locale` (aufgelöst), Datum als UTC und **gerendert** in dieser Locale, Beschriftungen als gerenderten Text. Neu-Rendern (Download später, andere Sprachpakete) verwendet ausschließlich den Snapshot. |
| Korrektur-Revision | übernimmt `locale`; Änderung nur ausdrücklich durch Berechtigte (Audit). |
| Verifikationsseite L-16 | Rahmen in der Besuchersprache (§3.1 Gäste), Nachweisblock mit `lang`/`dir` der eingefrorenen Locale (R0-15 §4 Punkt 3). |
| Sprachversionen | Zertifikat gilt für genau den abgeschlossenen Kurs (Ü-1); kein „gleiches Zertifikat in anderer Sprache“ in R1. |

---

## 4. Testfälle

Werkzeuge: PU = PHPUnit-Integration (WP-Testsuite), PU-MS = PHPUnit mit `WP_TESTS_MULTISITE=1`, PW = Playwright, CLI = WP-CLI im Testcontainer, MAN = manuell.

### 4.1 Locale und Inhaltssprache (LOC)

| ID | Prüfung (Erwartung) | Werkzeug | Ziel-Task |
|---|---|---|---|
| LOC-001 | Kurs auf Site `de_DE` anlegen: `_ccap_language = de-DE`; Frage im Pool des Kurses erbt `de-DE`; Lektion ohne eigene Sprache meldet effektive Sprache `de-DE` | PU | R1-04, R1-06 |
| LOC-002 | Sprach-Tag-Validierung: `en`, `pt-BR`, `zh-Hant-TW`, `sr-Latn` gültig; `de_DE`, `deutsch`, `xx-`, leer ungültig; WP-Locale `de_DE_formal` wird zu `de-DE` normalisiert | PU (Unit `LanguageTag`) | R1-04 |
| LOC-003 | `dir`-Ableitung: `ar`, `he-IL`, `fa`, `ur`, `ku-Arab` → `rtl`; `de-DE`, `sr-Latn` → `ltr` | PU | R1-19 |
| LOC-004 | Englische Frage in deutschem Kurs auf deutscher UI: Fragecontainer, Antworten, Erklärung tragen `lang="en"`; Seite `lang="de-DE"`; axe ohne `valid-lang`-Verstoß | PW, AXE | R1-16, R1-19 |
| LOC-005 | Arabische Frage in deutschem Kurs: Container `dir="rtl"`, UI bleibt LTR; Zahlen/Codes in `<bdi>` | PW | R1-19 |
| LOC-006 | Kurssprache nicht als WP-Sprache installiert (`en` auf reiner `de_DE`-Site): Publish erlaubt, kein Fehler | PU | R1-26 |
| LOC-007 | Gemischter Kurs: 3 von 10 Fragen `en` in `de-DE`-Kurs → Validator-Warnung mit Zahl; mit „Mehrsprachiger Kurs“ keine Warnung; Publish in beiden Fällen möglich | PU, PW | R1-26 |
| LOC-008 | Sprachgruppe: zwei Kurse `de-DE`/`en` verknüpfen ok; dritter Kurs `de-DE` in dieselbe Gruppe → Validator blockiert; parallele Verknüpfung zweier `de-DE`-Kurse (zwei Requests) → genau einer erfolgreich | PU (Parallelität mit Lock) | R1-04, R1-26 |
| LOC-009 | „Als neue Sprachversion kopieren“: Kopie ist Entwurf, gleiche `_ccap_translation_group`, neue Inhaltssprache, Fragen mit neuen `uid`, Original unverändert; Kopie ohne Übersetzung → Warnung „Fragen noch auf {Originalsprache}“ | PU | R1-04, R1-05 |
| LOC-010 | Fortschritt/Grant je Sprachversion: Grant auf DE-Version gibt **keinen** Zugriff auf EN-Version (403/`no_access`); Mastery und Zertifikat der DE-Version erscheinen nicht in der EN-Version | PU, PW | R1-10, R1-13 |
| LOC-011 | Prüfungs-Snapshot enthält je Frage die Sprache; spätere Sprachänderung der Frage ändert Auswertung und Anzeige eines laufenden/abgeschlossenen Versuchs nicht | PU | R1-08, R1-27 |
| LOC-012 | UI-Kette Lernbereich: Site `en_US`, Profil `de_DE`, Einstellung an → UI deutsch, `lang="de-DE"`; Einstellung aus → UI englisch; Profil `de_AT` ohne `de_AT`-Datei → deutsche Texte aus `de_DE`, `lang="de-DE"` (aufgelöste Locale) | PW | R1-19 |
| LOC-013 | Profil `fr_FR` ohne französische Übersetzung, Site `de_DE`: UI deutsch (nächstes Glied §3.1), `lang="de-DE"`; Site `es_ES` ohne Übersetzung → Quelltexte `en_US`, `lang="en-US"` | PW | R1-19 |
| LOC-014 | Übersetzungsplugin (Polylang) setzt Seite auf `en`, Profil `de_DE`, Einstellung an: Lernbereich deutsch; Katalog-Block auf derselben Site englisch | PW | R1-19, R1-30 |
| LOC-015 | REST-Fehlertext der Lern-App in Profilsprache mit `_locale=user`; `code` identisch in allen Sprachen | PU | R1-19, R1-28 |
| LOC-016 | Mail an Konto mit Profil `de_DE` (Site `en_US`): Betreiber hat nur englische Standardvariante angepasst → Mail mit englischem Betreibertext, Rahmen/Datum deutsch, Vorlagenblock `lang="en"`; mit deutscher Variante → komplett deutsch; ohne Anpassung → Werkstext deutsch | PU + Mail-Sink | R1-21 |
| LOC-017 | Mailvariante `de_DE` vorhanden, Empfänger `de_CH` → Variante `de_DE` (gleiche Sprache) | PU | R1-21 |
| LOC-018 | Queue mit drei Empfängern (`de_DE`, `en_US`, `ar`) in einem Lauf: jede Mail in eigener Sprache, `lang`/`dir` korrekt; Outbox `locale_resolved` gesetzt; Locale nach Lauf zurückgesetzt, auch nach Exception im zweiten Empfänger | PU | R1-21 |
| LOC-019 | Einladung an Adresse ohne Konto: gewählte Sprache → sonst Sprache der Einladenden → sonst Site-Sprache | PU | R1-13, R1-21 |
| LOC-020 | Zertifikat für `en`-Kurs auf `de_DE`-Site mit englischen Quelltexten: Beschriftungen englisch, Datum englisch formatiert, Snapshot `locale = en_US`, `content_language = en`; Sprachpaket später entfernt/geändert → Download identisch (Snapshot-Hash gleich) | PU | R1-35 |
| LOC-021 | Zertifikat für `fr`-Kurs ohne französische Übersetzung auf `de_DE`-Site → `locale = de_DE` (zweites Glied §3.3) | PU | R1-35 |
| LOC-022 | Verifikationsseite: Besucher `en_US`, Zertifikat `de_DE` → Rahmen englisch, Nachweisblock `lang="de-DE"` | PW | R1-35 |
| LOC-023 | Katalogfilter „Sprache“ erscheint erst ab zwei Inhaltssprachen; Sprachnamen in UI-Sprache; „Sprachversionen zusammenfassen“ zeigt je Gruppe die Version in UI-Sprache, sonst die erste veröffentlichte | PW | R1-16, R1-37 |
| LOC-024 | Kursdetailseite: `inLanguage` = Kurssprache; Course Captain gibt keine `hreflang`-Links aus | PU (HTML-Parse) | R1-37 |
| LOC-025 | Import: Paket mit `de-DE`- und `en`-Kurs gleicher `translation_group` → zwei verknüpfte Kurse; Entität ohne `language` → Manifest-Sprache; ohne beides → Dry-run-Fehler; Kollision mit bestehender Gruppe → Konflikt; nicht installierte Sprache → Hinweis | PU | R1-15 |
| LOC-026 | Export/Import-Rundlauf erhält `language` je Frage/Lektion und `translation_group` | PU (Golden Master) | R1-15, R1-18 |
| LOC-027 | Systemstatus: WPML/Polylang aktiv und `ccap_course` als übersetzbar eingestellt → Hinweis; nicht übersetzbar → kein Hinweis; Course Captain ändert die Plugin-Einstellung nicht | PU (Plugin-Stub), MAN | R1-38, R1-30 |
| LOC-028 | Koexistenz Polylang (aktuelle Version; WPML nur best effort ohne regelmäßigen Lauf, E-196): Aktivierung beider Reihenfolgen ohne Fatal/Notice, keine doppelten Meta-/JSON-LD-Tags, Lernbereich nach LOC-012/014 | PW, MAN | R1-30 |
| LOC-029 | Fallback-Tabelle §3.0 für JS: Profil `de_AT`, nur `de_DE`-JSON vorhanden → übersetzte Strings in der Lern-App | PW | R1-19 |

**Präzisierung der Kette (LOC-012/-013, LOC-021):** §3.0 löst eine **einzelne** gewünschte Locale auf (exakt → gleiche Sprache). Findet sie für die Profilsprache nichts, geht §3.1 zum nächsten Glied (Site-Sprache) und wendet §3.0 erneut an; `en_US` ist das letzte Glied der Gesamtkette. Für Zertifikate gilt dasselbe (§3.3).

### 4.2 Multisite (MS)

Alle MS-Tests laufen in einer eigenen Suite mit zwei bis drei Sites (RF-SITE2, R0-07) und sind für R1-30 Release-Gate des Status „unterstützt mit Einschränkungen“.

| ID | Prüfung (Erwartung) | Werkzeug | Ziel-Task |
|---|---|---|---|
| MS-001 | Netzwerkaktivierung über Netzwerk-Admin: abgebrochen mit Meldung; danach keine `ccap_`-Tabellen, Optionen, Caps, Cron-Ereignisse, Rewrite-Regeln auf irgendeiner Site; Plugin nicht in `active_sitewide_plugins` | PU-MS | R1-01 |
| MS-002 | `wp plugin activate course-captain --network` → Exit ≠ 0, gleicher Endzustand wie MS-001 | CLI | R1-01 |
| MS-003 | Link „Netzwerkweit aktivieren“ fehlt in der Netzwerk-Pluginliste | PU-MS (Filter) | R1-01 |
| MS-004 | Erzwungene Netzwerkaktivierung (DB-Eintrag): Haltemodus – keine Routen, keine Migration, kein Cron; Hinweis im Netzwerk-Admin; Entfernen des Eintrags hebt den Zustand ohne Datenverlust auf | PU-MS | R1-01 |
| MS-005 | Aktivierung auf Site 2: Tabellen `wp_2_ccap_*`, Caps nur an Rollen von Site 2, Cron-Ereignisse nur in Site-2-Cron; Site 1 unverändert | PU-MS | R1-01, R1-02 |
| MS-006 | Aktivierung auf Site 1 und 2: Kurs-ID von Site 2 auf Site 1 abrufen → 404 (RBAC-010); Batch-ID fremder Site → 404 (RBAC-078) | PU-MS | R1-03 |
| MS-007 | Gleiches Konto auf beiden Sites: Grant, Fortschritt, Zertifikat auf Site 1 erscheinen nicht auf Site 2; Nutzereinstellungen (Lernmodus, Quittungen) liegen in `wp_2_…` bzw. `wp_…`-präfixierten User-Optionen, nicht in festen Meta-Schlüsseln | PU-MS | R1-02, R1-10 |
| MS-008 | Statischer Check: kein `update_user_meta`/`get_user_meta` mit Course-Captain-Schlüssel außerhalb einer Ausnahmeliste (MS-R-05) | Prüfskript in CI | R1-02 |
| MS-009 | `switch_to_blog` in Uninstall/Eraser: Policy-Memo und Repository-Caches nach Wechsel leer; nach Exception ist `get_current_blog_id()` wieder die Ausgangs-Site | PU-MS | R1-03, R1-17 |
| MS-010 | Site-Löschung mit geladenem Plugin: alle `wp_N_ccap_*` gelöscht (abhängige zuerst, keine FK-Fehler), privater Ordner `sites/N` gelöscht; andere Sites unverändert | PU-MS | R1-17 |
| MS-011 | Site-Löschung ohne geladenes Plugin (Plugin auf der Lösch-Anfrage nicht aktiv): Diagnose meldet verwaiste Tabellen und Ordner; `cleanup-sites` Trockenlauf listet sie ohne Löschung, `--apply` löscht nur diese; Hauptsite und bestehende Sites nie betroffen | PU-MS, CLI | R1-17, R1-38 |
| MS-012 | Neue Site anlegen, während Plugin auf Site 1 aktiv: keine Tabellen/Cron auf der neuen Site | PU-MS | R1-01 |
| MS-013 | Site archivieren/als Spam markieren und reaktivieren: Daten unverändert; nachgezogene Fristabläufe beim nächsten Cron-Lauf | PU-MS | R1-17 |
| MS-014 | Deaktivierung auf Site 2 entfernt nur Site-2-Cron/Rewrite; Daten bleiben; Reaktivierung ohne Migration-Doppellauf | PU-MS | R1-01 |
| MS-015 | Uninstall im Netzwerk: Site 1 mit `delete_data_on_uninstall`, Site 2 ohne → nur Site 1 leer; globale Nutzer-Meta bleibt, weil Site 2 Daten behält | PU-MS | R1-23 |
| MS-016 | Konto aus Site 2 entfernen: Lernzugriff auf Site 2 verweigert (gesperrter Nutzer), Grants und Daten unverändert; Site 1 unbeeinflusst | PU-MS | R1-03, R1-17 |
| MS-017 | Konto netzweit als Spam markieren: auf allen Sites gesperrt (R0-07 §3 (c)), keine Datenänderung | PU-MS | R1-03 |
| MS-018 | Netzweite Kontolöschung: Eraser läuft auf allen Sites mit `ccap_`-Tabellen, auch auf Site mit deaktiviertem Plugin; Audit-Pseudonym je Site | PU-MS | R1-17, R1-34 |
| MS-019 | Super-Admin ohne Mitgliedschaft auf Site 2: darf dort administrieren wie Admin (WP-Pass-through); Selbstbewertung und Selbstsperre verweigert (`do_not_allow`); Audit-Eintrag mit `actor_is_super_admin`; kein Course-Captain-Screen im Netzwerk-Admin; automatische Kontolöschung nicht anwendbar | PU-MS | R1-03, R1-17 |
| MS-020 | Upgrade lazy: neue Schema-Version → Site 1 migriert beim ersten Request, Site 2 erst bei ihrem; gleichzeitige Requests auf zwei Sites blockieren sich nicht (Lock je Site) | PU-MS | R1-40 |
| MS-021 | Restore Site 2 aus Backup: Site 1 unverändert, Site-Identität und Locks je Site (= REC-024) | MAN, PU-MS | R1-23 |
| MS-022 | Objekt-Cache (Redis-Drop-in) aktiv: gleiche Kurs-ID auf Site 1 und 2 liefert jeweils eigene Daten | PU-MS mit Cache | R1-25 |
| MS-023 | Mandanten-Negativtest (Single-Site): Instructor A sieht Kurse von B nicht (Objekt-Scope), Einstellungen/Vorlagen sind siteweit; Doku enthält keinen Mandanten-Begriff | PU, MAN | R1-03, R1-24 |

---

## 5. Alternativen

| Nr. | Alternative | Bewertung |
|---|---|---|
| A1 | **Multisite offiziell mit Netzwerkaktivierung** (Tabellen je Site, Upgrade lazy) | Technisch vorbereitet (R0-06 D-3), aber Testaufwand für Netzwerk-Aktivierungs-/Deinstallations- und Upgrade-Pfade über viele Sites, Support für Netzwerk-Lizenzen und Super-Admin-Fragen. **Verschoben**; spätere Freigabe braucht keine Migration (MS-R-04). |
| A2 | Multisite gar nicht berücksichtigen (kein Site-Löschhaken, keine Orphan-Prüfung) | billiger, aber Datenleck: Tabellen und private Medien gelöschter Sites blieben liegen. Das LB-Verhalten existiert und ist getestet; es wird nach E-01 ohne Codeübernahme neu implementiert. **Verworfen** (E-C-01). |
| A3 | Netzweite Lernidentität (eine Lernhistorie über Sites) | widerspricht Site-Bindung von Grants (35.2) und Datenschutz je Site; erst mit Cross-Site-Identität R4. **Verworfen für R1**. |
| A4 | Mandanten in einer Site (`tenant_id`) | §1.4. **Verworfen für R1.** |
| B1 | **Eigener Kurs je Sprache, verknüpft** | gewählt: passt zu CPT-Modell, Revisionen, Snapshots, Grants (alles je Kurs); jede Version kann unabhängig veröffentlicht, korrigiert und geprüft werden; Fragenpools je Sprache halten Prüfungen sprachrein; keine Änderung an Bewertung und Mastery. Nachteil: Struktur- und Fragenänderungen müssen je Version gepflegt werden (Kopierhilfe, aber kein Sync). |
| B2 | **Übersetzungsfelder** (ein Kurs; Titel, Lektionen, Fragen, Antworten je Sprache) | ein Fortschritt über Sprachen möglich, aber: jede Frage/Antwort/Erklärung bräuchte Sprachvarianten in `ccap_question_revision`, der Snapshot müsste die Sprache je Versuch fixieren, Bewertung bei teilweise fehlender Übersetzung wäre unklar (Ü-3), Block-Editor kennt keine Feldvarianten je Sprache, Revision/Publish-Validator müssten Vollständigkeit je Sprache prüfen, Import-/Golden-Master-Format ändert sich. Hoher R1-Aufwand ohne LB-Beleg. **Verworfen für R1**, R4-Kandidat. |
| B3 | Sprachversionen nur über WPML/Polylang | erfasst `ccap_`-Tabellen nicht (Ü-4), erzeugt Kurse ohne Fragen/Regeln. **Verworfen.** |
| B4 | Inhaltssprache als WP-Locale (`de_DE`) statt BCP 47 | koppelt Inhalt an installierte Sprachpakete; `lang`-Attribut und Paketformat (R0-10, LB `de-DE`) brauchen BCP 47 ohnehin. **Verworfen.** |
| C1 | UI immer nur Site-Sprache (LB-Verhalten) | widerspricht E-124 und I18N-R-30. **Verworfen.** |
| C2 | Kein Varianten-Fallback (`de_AT` → Quelltexte) | österreichische/schweizerische Nutzer sähen Englisch, obwohl Deutsch vorliegt. **Verworfen** (D-25-07). |
| C3 | Zertifikat in Profilsprache des Lernenden | Mischdokument (Kursinhalt deutsch, Beschriftung englisch); Sprache hinge von einer Profileinstellung im Ausstellungsmoment ab. Option in D-25-04. |

---

## 6. Konsequenzen

**Positiv**
- Keine Schemaänderung für Sprachversionen: zwei Meta-Felder (`_ccap_translation_group`, `_ccap_mixed_language`) und eine optionale Lektion-Meta; Fragen haben `language` bereits (R0-06).
- Bewertung, Snapshots, Grants und Mastery bleiben je Kurs eindeutig; keine sprachabhängigen Sonderfälle in R0-08/R0-09.
- Multisite-Datenhygiene (Site-Löschung, Orphans, Uninstall, Kontolöschung) wie LB, zusätzlich private Ordner und User-Optionen je Site.
- Spätere Netzwerkaktivierung oder Übersetzungsfelder bleiben ohne Datenmigration der bestehenden Kurse möglich.

**Negativ / Risiken**
- Autoren pflegen Sprachversionen getrennt; strukturelle Änderungen laufen auseinander. Gegenmaßnahme: Sprachversionsliste in A-04 mit „zuletzt veröffentlicht“ je Version; Sync ist R4-Kandidat.
- Lernende, die die Sprache wechseln, verlieren in R1 ihren Fortschritt (neuer Kurs) – in Doku und Kursübersicht klar benennen.
- Betreiber mit WPML/Polylang erwarten Integration; wir liefern nur Grenzen und einen Systemstatus-Hinweis.
- Super-Admins haben WP-bedingt Vollzugriff je Site; das ist Datenschutzthema für R0-17, nicht technisch abstellbar ohne Bruch mit WP.

**Deltas an andere Dokumente (beim Zusammenführen einarbeiten)**

| Dokument | Delta |
|---|---|
| R0-06 | Kurs-Meta `_ccap_language`, `_ccap_translation_group`, `_ccap_mixed_language`; Lektion-Meta `_ccap_language` (optional); Revisionsstruktur mit effektiver Sprache je Lektion; Mailvorlagen mit Schlüssel `(template_key, locale)`; Outbox `locale_requested`/`locale_resolved`; Zertifikats-Snapshot `content_language`, `locale`; §6.6 um MS-R-04 (privater Ordner je Site) und MS-R-05 (User-Optionen) ergänzen |
| R0-07 | Audit-Feld `actor_is_super_admin`; NS-05 als Regel; MS-016/-017 ergänzen RBAC-Negativfälle |
| R0-08 §7.2 | Zertifikats-Locale nach §3.3; Snapshot je Frage mit Sprache |
| R0-10 | optionales Kursfeld `translation_group` (UUID); Regel „ohne `language` und ohne Manifest-Sprache → Fehler“; nicht installierte Sprache → Hinweis |
| R0-15 | §4 Ü-1…Ü-7 beantwortet (Verweis auf dieses ADR); I18N-R-33 verweist auf §3.2 |
| R0-16 | Privater Ordner je Site im Backup-Umfang; Uninstall nach MS-R-13 |
| R0-19 | REST-Objekte Kurs/Lektion/Frage mit `language` (BCP 47) und Kurs mit `translationGroup`, `translations[]` (nur sichtbare Versionen); Filter `course_captain_locale_fallbacks` (Tabelle §3.0 erweiterbar) |
| R0-22 | VAL-002 auf BCP 47; neue Regeln „Sprachgruppe doppelt“ (B) und „gemischte Sprachen“ (W) |
| R0-27 | Einstellungen „Sprachversionen im Katalog zusammenfassen“ (Standard aus); Mailvorlagen-Varianten |

---

## 7. Entscheidungsbedarf – beschlossen

Alle Punkte sind entschieden (`ENTSCHEIDUNGEN.md` §24, 16.11.2026). Zugrunde liegen außerdem E-07, E-49 (beantwortet R0-06 D-3 und E-C-01), E-121, E-124. Die D-Nummern bleiben als Verweise im Text stehen.

| ID | Frage | Beschluss | Betrifft |
|---|---|---|---|
| D-25-01 → **E-155** | Modell für Sprachversionen eines Kurses | Eigener Kurs je Sprache, verknüpft über eine Übersetzungsgruppe; Fortschritt, Grants und Zertifikate je Kurs | R1-04, R1-05, R1-15, R1-26 |
| D-25-02 → **E-156** | Zugang/Fortschritt zwischen Sprachversionen in R1 | Kein Übertrag in R1; Sammelvergabe durch den Kursmanager möglich; gemeinsame Vergabe über Bundle (E-25a) | R1-10, R1-13, R0-09 |
| D-25-03 → **E-157** | Gemischte Sprachen in einem Kurs/einer Prüfung | Erlaubt mit `lang`-Kennzeichnung und Validator-Warnung | R1-06, R1-08, R1-26 |
| D-25-04 → **E-158** | Sprache des Zertifikats | Kurssprache, im Snapshot eingefroren | R1-35, R3 |
| D-25-05 → **E-159** | Mehrsprachige Betreiber-Mailvorlagen | Sprachvarianten schon in Free | R1-21, R0-27 |
| D-25-06 | Grenze zu WPML/Polylang/TranslatePress | Technisch festgelegt: nur dokumentieren, mit Systemstatus-Hinweis; Koexistenztest regelmäßig nur mit Polylang (WPML best effort, E-196) | R1-30, R1-38, R1-24 |
| D-25-07 | Varianten-Fallback (`de_AT` → `de_DE`) | Technisch festgelegt: ja, feste Tabelle §3.0, nur eigene Textdomains | R1-19 |
| D-25-08 | Erzwungene Netzwerkaktivierung | Technisch festgelegt: Haltemodus | R1-01 |
| D-25-09 → **E-160** | Mandanten innerhalb einer Site | Nein in R1, kein `tenant_id` | R0-06, R1-02, R4-01 |
| D-25-10 | Vorrang im Lernbereich | Technisch festgelegt: Profilsprache für Angemeldete, Site-/Plugin-Sprache für Gäste | R1-19, R1-30 |

### Offene Punkte nach Einarbeitung

- D-25-03 sah zusätzlich einen Schalter „Mehrsprachiger Kurs“ vor, mit dem die Warnung abgeschaltet wird; E-157 nennt ihn nicht. Bis zur Klärung gilt nur „erlaubt mit Kennzeichnung und Warnung“. → **neue Frage NF-15** (R0-GATE-protokoll.md §5; kein Blocker für Gate A, Klärung vor Gate B (R1-26))
- E-25a (Bundle in R1) erlaubt die gemeinsame Vergabe mehrerer Sprachversionen; ob dafür eine eigene Bedienhilfe („alle Sprachversionen als Bundle“) gebraucht wird, ist nicht entschieden. → **neue Frage NF-16** (R0-GATE-protokoll.md §5; kein Blocker für Gate A, Klärung vor Gate B (R1-13/R1-32))

---

## 8. Nachweis dieser Arbeit

- Gelesen: TODO R0-25 (und R1-Tasks als Zielzuordnung); Konzept Kap. 17, 21; R0-15 §0.1, §3, §4, §6; R0-06 §2–3 (CPT/Tabellen), `ccap_question`, `ccap_certificate`, §6.6, D-3; R0-07 §3 (Site, Super-Admin, gesperrter Nutzer), RBAC-010/-032/-078; R0-16 (Site-Identität, Uninstall, REC-019/-024); Inventur C (INV-C-004/-007/-008/-024/-058/-148/-153/-154/-157/-159/-173/-192, Schema `course`/`question`, E-C-01); R0-10 (Manifest `languages`, Gemeinsame Felder); R0-08 §7.2 (Verweis); R0-22 VAL-002; `ENTSCHEIDUNGEN.md` E-07, E-43, E-49, E-121…E-127.
- LB gelesen (@ `9f37c77`): `src/Plugin.php` (`activate`, `dropTablesOnSiteDelete`, `orphanSiteTables`); per Suche `is_network_admin`, `is_super_admin`, `switch_to_locale`, `determine_locale`, `wp_initialize_site` in `src/` (Treffer: `Requirements.php:41`, `AccountDeletion.php:80`, `Router.php:190`, `WithdrawalTexts.php:111`; kein `wp_initialize_site`/`wp_uninitialize_site`).
- Nicht geprüft: Laufzeitverhalten mit WPML/Polylang/TranslatePress (keine Testumgebung); genaues Verhalten von `activate_plugin()` bei `wp_die` in der aktuellen WP-Version (MS-001/-002 belegen es in R1).
