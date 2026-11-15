# Course Captain – Wettbewerbsanalyse: To-do-Liste

> **Status:** Vom Auftraggeber zur Aufnahme ins Konzept freigegeben und in **Kapitel 27 von `konzept.md`** aufgenommen. Diese Datei bleibt die operative Checkliste; offene Kästchen bedeuten **noch nicht umgesetzt**. Prioritäten und Editionen sind vorläufig und werden vor Implementierung konkretisiert.
>
> **Leitplanken:** Universell für alle Kursanbieter; zuerst fachneutrale Funktionsparität zu LearnBoating; vorhandene Lernlogik bevorzugt wiederverwenden; **keine** boots-, funk- oder navigationsspezifischen Module; LearnBoating und andere Captain-Repositories bleiben unverändert.

## P0 – Zuerst prüfen / Basis-Lücken schließen

- [ ] **LearnBoating-Paritätsmatrix abschließen:** alle fachneutralen Funktionen, Quellkomponenten, Übernahmestrategien, Regressionstests und Abnahmekriterien inventarisieren. Fachmodule ausdrücklich ausschließen.
- [ ] **Kurs-/Quiz-Builder im Admin prüfen und vervollständigen:** Fragen direkt in Lektionen erstellen, Vorschau, Duplizieren, Revisionen, barrierearme Bedienung.
- [ ] **Persönliche Lernnotizen evaluieren:** private Notizen je Lektion, Suche, Export und optionales Umwandeln in Lernkarten; Datenschutz und Speicherlast prüfen.
- [ ] **Aufgabenabgaben als universelle Aktivität spezifizieren:** Text- und Datei-Einreichungen, sichere Uploads, Status und Feedback; Abgrenzung zur vorhandenen Praxisaufgabenlogik prüfen.
- [ ] **Kursvorlagen und Demokurse:** Duplizieren, neutrale Vorlagen (Onboarding, Sprachen, IT, Weiterbildung), sicherer Import/Export und keine Fach-Altinhalte.

## P1 – Professioneller LMS-Betrieb

- [ ] **Gradebook/Notenbuch:** Quiz, Prüfungen, Aufgaben, manuelle Bewertungen, Exporte und Rollenrechte; nicht doppelt zur Lernhistorie speichern.
- [ ] **Tutorbewertung:** Bewertungsraster, Kommentare, Wiedereinreichungen, Nachvollziehbarkeit und Benachrichtigungen.
- [ ] **Frontend-Kurs-Builder für Trainer:** Kurse und Quiz ohne WP-Admin bearbeiten; Rechte, Entwürfe, Medien und Revisionen absichern.
- [ ] **Mehrere Trainer je Kurs:** Verantwortlichkeiten, Berechtigungen, Freigaben und Audit-Log definieren.
- [ ] **Visuellen Zertifikatsdesigner prüfen:** Vorlagen, dynamische Felder, Logo, Signatur, Nachweis-ID, Verifikation und Widerruf; keine amtlichen Zusagen.
- [ ] **Kompetenzmatrix/Lernziele:** Bearbeitung, Beherrschung und Nachweise getrennt auswerten; Versionierung und Datenschutz.
- [ ] **Kursdiskussionen und Trainerfragen:** Moderation, Benachrichtigungen, Anti-Spam und optional vorhandene WordPress-/Suite-Funktionen nutzen.
- [ ] **Interaktive Medienaktivitäten:** Bild-Hotspots, Video-Fragen, Matching, Sortierung und Szenarien als fachneutrale Activity-Provider evaluieren.
- [ ] **Kurs-Bundles und Mitgliedschaften:** vorhandene Entitlement-Logik und WooCommerce/Suite-Verträge prüfen; keine Commerce-Pflicht für Free.
- [ ] **Kursbewertungen:** Integration mit Review Captain prüfen, keine doppelte Bewertungsdatenhaltung.

## P2 – Nach Marktvalidierung

- [ ] **Gamification:** Badges, Punkte, Streaks; Lernwirksamkeit und Missbrauchsanreize prüfen.
- [ ] **SCORM/xAPI/H5P/LTI:** tatsächliche Kundennachfrage, Sicherheits- und Lizenzfolgen sowie Import-/Laufzeitkompatibilität bewerten.
- [ ] **Erweiterte Kohorten-/Coaching-Funktionen:** nur bei belegter Nachfrage priorisieren.

## Konkurrenzvergleich – gezielt verifizieren

- [ ] **LearnDash:** Quiz/Assignments, Gradebook, Gruppen, Zertifikate, Lernpfade und Preis-/Lizenzgrenzen anhand aktueller Produktdokumentation und Testinstallation vergleichen.
- [ ] **Tutor LMS:** Frontend-Builder, mehrere Trainer, Content-Bank, Bundles, Memberships und Checkout praktisch testen.
- [ ] **LifterLMS:** Kohorten, Coaching, Diskussionen, Notizen, Mitgliedschaften und Reports prüfen.
- [ ] **Sensei LMS:** WordPress-Editor, Lernmodus, interaktive Videos, Lernkarten und Hotspots prüfen.
- [ ] **LearnPress / MasterStudy LMS:** Kernumfang, Erweiterungen, Bedienbarkeit und Free-/Pro-Grenzen prüfen.
- [ ] **Vergleichsmatrix erstellen:** Feature, Konkurrent, tatsächliche Verfügbarkeit (Free/Pro/Add-on), Course-Captain-Status (vorhanden/geplant/fehlt), Nutzen, Aufwand, Priorität, Quelle und Prüfdatum.
- [ ] **Praxis-Benchmark:** Kurs mit 10 Lektionen, 50 Fragen, einer Aufgabe und einer Prüfung in jedem Produkt anlegen; Klicks, Zeit, UX, Accessibility und Performance dokumentieren.

## Entscheidungstore vor Umsetzung

- [ ] **Keine Konkurrenzfunktion blind übernehmen:** Nutzerproblem, konkreten Nutzen, Aufwand und Abhängigkeiten nachweisen.
- [ ] **Dopplungen vermeiden:** erst bestehende LearnBoating- und Captain-Suite-Funktionen prüfen.
- [ ] **Free/Pro-Grenzen einzeln entscheiden:** Free muss ein eigenständiges, vollwertiges LMS bleiben.
- [ ] **P0 bedeutet Prüfung/Planung, nicht automatisch v1.0-Pflicht:** vollständige fachneutrale LearnBoating-Parität hat Vorrang.
- [x] **Aufnahme ins Konzept freigegeben:** Funktionskandidaten und Evaluierungsaufträge in Kapitel 27 von `konzept.md` erfasst; Implementierungsumfang und Release-Zuordnung weiter prüfen.

## Referenzseiten für die nächste Verifikation

- https://www.learndash.com/
- https://tutorlms.com/
- https://lifterlms.com/
- https://senseilms.com/
- https://learnpresslms.com/
- https://stylemixthemes.com/wordpress-lms-plugin/
