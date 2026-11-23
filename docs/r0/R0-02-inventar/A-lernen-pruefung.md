# R0-02/R0-03 · Inventar Teil A – Lernen, Prüfung, Trainer, Zugangstor, Prüfungsanmeldung, Tipps, FAQ

**Status:** Inventur und Klassifikation (Vorschlag), keine Implementierung, keine Freigabe · **Stand:** 16.11.2026
**Bezug:** TODO R0-02, R0-03 (Vorarbeit für R0-04, R0-05), Konzept Kap. 26, 47, 48; Baseline `docs/r0/R0-01-baseline.md`

## 1. Kopf

| Feld | Wert |
|---|---|
| Bereich | `bootspruefung-learning/src/{Learning,Exams,Trainers,Gate,ExamGuide,Tips,Faq}` und die Tests, die diese Klassen betreffen |
| Quelle | `hastla007/learnboating`, Commit `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (nur gelesen, nicht verändert) |
| Pfadkonvention | Quellpfade in den Tabellen relativ zu `bootspruefung-learning/src/`; Tests relativ zu `bootspruefung-learning/tests/` |
| Klassifikation | `reuse` = Logik fachneutral, nur umbenennen/neu namespacen · `abstract` = generischer Kern mit fachgebundener Benennung, Konstante oder Kopplung, die neutralisiert werden muss · `replace` = Funktion gebraucht, Umsetzung so nicht übertragbar (andere Bauweise) · `exclude_domain_specific` (kurz `exclude`) = boots-/funk-/navigations-/amtsprüfungsgebunden, entfällt (Kap. 26.2) |
| Edition | Vorschlag, Entscheidung in R0-04/R0-11. „R1-Free“ = fachneutraler Lernkern; „R3“ = Woo-/Voucher-Adapter |

### 1.1 Gelesene Quelldateien (100 % des Bereichs)

Abgleich mit der Modulkarte in R0-01: Learning 4 Dateien / 1 829 Zeilen, Exams 1 / 1 086, Trainers 8 / 2 437, Gate 5 / 1 096, ExamGuide 2 / 365, Tips+Faq 19 / 3 220. **Summe: 39 Dateien, 10 033 Zeilen.** Jede Datei wurde vollständig gelesen. Die reinen Datendateien `Faq/DefaultFaqs.php`, `Faq/DefaultFaqHistory.php` und `Faq/DefaultCustomerFaqs.php` wurden ebenfalls vollständig durchgesehen. Ihr Inhalt besteht aus fachgebundenen FAQ-Texten und Prüfsummen und wird in Abschnitt 2 gesammelt klassifiziert.

| # | Datei | Zeilen | | # | Datei | Zeilen |
|---|---|---|---|---|---|---|
| 1 | `Learning/Services/LearningService.php` | 826 | | 21 | `Tips/Tips.php` | 201 |
| 2 | `Learning/Services/AccessService.php` | 520 | | 22 | `Tips/StarterTips.php` | 72 |
| 3 | `Learning/Services/CollectionService.php` | 267 | | 23 | `Tips/TipsRest.php` | 27 |
| 4 | `Learning/Services/QuestionPresenter.php` | 216 | | 24 | `Tips/TipsPostsController.php` | 21 |
| 5 | `Exams/Services/ExamService.php` | 1 086 | | 25 | `Tips/TipsTermsController.php` | 21 |
| 6 | `Trainers/TrainerService.php` | 319 | | 26 | `Faq/Faq.php` | 135 |
| 7 | `Trainers/VideoService.php` | 309 | | 27 | `Faq/FaqTerms.php` | 89 |
| 8 | `Trainers/RadioService.php` | 399 | | 28 | `Faq/FaqRepository.php` | 251 |
| 9 | `Trainers/RadioPracticeService.php` | 249 | | 29 | `Faq/FaqShortcode.php` | 234 |
| 10 | `Trainers/DeviceSimService.php` | 492 | | 30 | `Faq/FaqImporter.php` | 370 |
| 11 | `Trainers/NavigationCheck.php` | 523 | | 31 | `Faq/DefaultFaqs.php` | 492 |
| 12 | `Trainers/RadioText.php` | 96 | | 32 | `Faq/DefaultFaqHistory.php` | 119 |
| 13 | `Trainers/RadioChecks.php` | 50 | | 33 | `Faq/CustomerFaq.php` | 168 |
| 14 | `Gate/ContentGate.php` | 336 | | 34 | `Faq/CustomerFaqRepository.php` | 195 |
| 15 | `Gate/ProtectedFiles.php` | 433 | | 35 | `Faq/CustomerFaqImporter.php` | 509 |
| 16 | `Gate/Charts.php` | 150 | | 36 | `Faq/DefaultCustomerFaqs.php` | 247 |
| 17 | `Gate/SectionFiles.php` | 89 | | 37 | `Faq/CustomerFaqRestGuard.php` | 27 |
| 18 | `Gate/DownloadService.php` | 88 | | 38 | `Faq/CustomerFaqPostsController.php` | 21 |
| 19 | `ExamGuide/ExamGuideService.php` | 234 | | 39 | `Faq/CustomerFaqTermsController.php` | 21 |
| 20 | `ExamGuide/GuideCorrections.php` | 131 | | | **Summe** | **10 033** |

### 1.2 Ausgewertete Quelltests (Golden-Master-Kandidaten)

Nur Tests, die Klassen dieses Bereichs aufrufen (ermittelt per `grep` auf die Klassennamen in `tests/`). Prüfaussagen wurden je Abschnitt gelesen. Eine Laufzeitausführung fand nicht statt.

| Test | Zeilen | Gelesener Umfang | Betroffene Klassen |
|---|---|---|---|
| `integration/learning-review-test.php` | 400 | vollständig | LearningService, ExamService, QuestionPresenter, AccessService |
| `integration/content-flow-test.php` | 566 | Abschnitte „Lernen“ bis „Review-Funde“ (Z. 166–566) | alle Lern-/Prüf-/Trainer-Services, ProtectedFiles, Charts |
| `integration/navigation-check-test.php` | 272 | Prüfaussagen Z. 59–268 | NavigationCheck, TrainerService, ExamService (Markieren) |
| `integration/exam-guide-test.php` | 179 | Prüfaussagen Z. 39–174 | ExamGuideService, Tips, StarterTips |
| `integration/faq-test.php` | 411 | Prüfaussagen Z. 62–398 | Faq, FaqImporter, FaqRepository, FaqShortcode, DefaultFaqs |
| `integration/customer-faq-test.php` | 629 | Prüfaussagen Z. 102–626 | CustomerFaq*, FaqImporter |
| `integration/membership-test.php` | 1 360 | Z. 133–400 (Teilumfang, Sammlungen, Drip, Gate, Dateien, Downloads), 831–886 (Seekarte), 1034–1086 (Kaufstatus), 1303–1324 (Video-Hosts) | AccessService, CollectionService, ContentGate, ProtectedFiles, SectionFiles, DownloadService, Charts, VideoService |
| `integration/security-test.php` | 774 | Z. 95–140 (ProtectedFiles), 633–674 (Tipps in Core-REST, T88) | ProtectedFiles, TipsRest |
| `integration/operations-test.php` | 805 | Z. 715–725 (Untertitel), 781 (Content-Disposition) | VideoService, ProtectedFiles |
| `integration/mobile-api-test.php` | 1 912 | Fundstellen zu Tips, ExamGuideService, QuestionPresenter, contentDisposition (Z. 583–658, 1212–1213) | über Mobile-API (Teil D) |
| `integration/extensions-test.php`, `uninstall-phase.php` | 530 / 153 | nur Fundstellen AccessService bzw. Tips | AccessService, Tips |
| `e2e/learning-flow.mjs` | 200 | Schritte (Smarttrainer, Favoriten, offene Frage, Prüfung, Markieren, offline, Tipps) | UI zu Learning/Exams |
| `e2e/trainers-flow.mjs` | 210 | Schritte (Navigation, Praxis, Video, Funk, Gerätesimulation) | UI zu Trainers |
| `e2e/guide-flow.mjs`, `e2e/guide-setup.php` | 52 / 14 | vollständig | UI zu ExamGuide |

**Hinweis zu den Golden Mastern:** Alle Integrationstests laufen gegen das echte Boots-Content-Paket (`PACKAGE_DIR`) oder gegen Kurscodes wie `SBF-SEE`. Für Course Captain sind sie **Verhaltensvorlagen**, keine übertragbaren Fixtures. Ihre Prüfaussagen müssen in R0-05 mit neutralen Fixtures neu formuliert werden (Kap. 26.3, Schritt 5).

## 2. Funktionstabelle

Eine Zeile pro fachlicher Funktion. Private Hilfsmethoden werden der öffentlichen Funktion zugeschlagen, die sie nutzt.

### 2.1 Learning – Smarttrainer, Fortschritt, Favoriten (LearningService, CollectionService, QuestionPresenter)

| ID | Quellpfad:Zeile | Art | Funktion | Klass. | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Quelltest |
|---|---|---|---|---|---|---|---|---|---|
| INV-A-001 | `Learning/Services/LearningService.php:80` | PHP | `next()`: nächste Lernfrage je Modus (`smart`, `new`, `wrong`, `favorites`, L39), optional Modul, Thema (L100) und Fragenauswahl `onlyIds` (Sammlung, Prüfungsfehler) | reuse | Generischer Auswahlalgorithmus über Fragen/Module, kein Fachbezug | `cc` Smarttrainer-Selektor (Practice-Session-Service) | R1-Free | R1-11, R1-12 | `learning-review-test.php`, `content-flow-test.php`, `membership-test.php` (Thema, Sammlung), `e2e/learning-flow.mjs` |
| INV-A-002 | `Learning/Services/LearningService.php:712` | PHP | `score()`: Priorität „nie gesehen“ 1000 → „zuletzt falsch“ 800+10/Fehler → fällig 700 → schwaches Thema (<60 %) +150 → Rest nach `mastery_score`; Begründungstext je Frage | abstract | Gewichte und Schwelle sind Betreiberentscheidungen (Klassenkommentar L27), Gründe hart deutsch | Prioritätsregeln als konfigurierbare, versionierte Strategie mit i18n-Gründen | R1-Free | R1-12, R0-05, R0-28 | `learning-review-test.php` (Reihenfolge, Gründe) |
| INV-A-003 | `Learning/Services/LearningService.php:604` | PHP | Abstandsregel: die letzten `RECENT_EXCLUDE` = 3 beantworteten Fragen (L44) nur als Rückfall anbieten (`stepsSinceLastAnswer`, 100 Antworten / 2 h) | reuse | Neutrale Lernpsychologie | Teil des Smarttrainer-Selektors | R1-Free | R1-12 | `content-flow-test.php` („eben beantwortete Frage kommt nicht sofort wieder“) |
| INV-A-004 | `Learning/Services/LearningService.php:161` | API | `reveal()`: Musterantwort einer offenen Frage im Lernmodus nach Token | reuse | Neutraler Fragetyp `open_text` mit Selbsteinschätzung | Antwortdienst für offene Fragen | R1-Free | R1-06 | `content-flow-test.php` (offene Frage, Musterantwort) |
| INV-A-005 | `Learning/Services/LearningService.php:177` | PHP | `tokenContext()`: Token prüfen, Kontext `learn`/`video` auf Feature abbilden, Zugriff erneut prüfen (z. B. nach Erstattung) und Auslieferbarkeit der Frage prüfen | abstract | Kontextliste und Feature-Abbildung sind fest verdrahtet (L181). Neue Activity-Typen brauchen ein Register | Kontext-Register je Activity-Typ, Policy-Check bei jeder Antwort | R1-Free | R1-06, R1-07, R1-14, R0-07 | `content-flow-test.php` (Token nach Zugangsende 403, Frage nicht auslieferbar 404) |
| INV-A-006 | `Learning/Services/LearningService.php:200` | API/Daten | `answer()`: Validierung (single/multi/open), Auswertung, Versuch speichern (`bp_attempt`, `bp_attempt_answer`) in Transaktion. Idempotenz über einen aus dem Token abgeleiteten Schlüssel (L214), paralleler Doppel-Submit über eindeutigen Schlüssel (L259) | reuse | Kern-Lernpfad, fachneutral | Attempt-Service mit Idempotenz-Key | R1-Free | R1-09, R1-10, R1-20, R1-42 | `content-flow-test.php` („Doppel-Submit zählt nicht doppelt“), `learning-review-test.php` |
| INV-A-007 | `Learning/Services/LearningService.php:791` | PHP | `blockedByRunningExam()` und Sperre in `answer()` (L208): Fragen laufender Prüfungen sind im Lernmodus und Videoquiz gesperrt, bis Frist + Kulanz bzw. 24 h ohne Zeitlimit (`OPEN_EXAM_BLOCK_SECONDS`, L52) | reuse | Prüfungsintegrität, neutral | Exam-Isolation-Policy | R1-Free | R1-08, R1-20 | `learning-review-test.php`, `content-flow-test.php` (Prüfung ohne Zeitlimit: nach 24 h frei) |
| INV-A-008 | `Learning/Services/LearningService.php:495` | Daten | `applyProgress()`: Zustandsautomat neu → unsicher → in Bearbeitung → „sicher heute“ (3 in Folge) → gefestigt. Zeile per `FOR UPDATE` gesperrt, `mastery_score` = 0,4·Quote + 0,6·min(Serie,3)/3 | reuse | Generische Mastery-Logik (Konzept 26.1 „Wiederholungsintervalle“) | Mastery-/Spaced-Repetition-Engine | R1-Free | R1-10, R1-12, R0-05 | `learning-review-test.php` (FACH-02) |
| INV-A-009 | `Learning/Services/LearningService.php:541` | PHP | Fälligkeit: `reviewInterval()` 7/21/60 Tage (L50), `dueAt()` L553 (inkl. Bestandspfad vor Schema 1.14.0 ohne `due_at`), `isDue()` L567, Konsolidierung nach 20 h (L48) | abstract | Intervalle als Konstanten; der Bestandspfad betrifft nur Altdaten von LearnBoating | Intervallplan als Einstellung je Kurs bzw. global, ohne Legacy-Zweig | R1-Free | R1-12, R0-28 | `learning-review-test.php` (Bestand vor 1.14.0, 7/21 Tage) |
| INV-A-010 | `Learning/Services/LearningService.php:580` | PHP | `spaced()`: richtige Antwort zählt für die Serie nur nach 300 s (`STREAK_GAP_SECONDS`, L42) oder wenn dazwischen andere Fragen beantwortet wurden (Schutz gegen Kurzzeitgedächtnis und zweiten Tab) | reuse | Neutral, durch Zeilensperre racefest | Teil der Mastery-Engine | R1-Free | R1-12, R1-42 | `content-flow-test.php` („zweiter Tab“), `learning-review-test.php` |
| INV-A-011 | `Learning/Services/LearningService.php:294` | API | `courseProgress()` je Modul und Thema (`topicSummary` L343, nur bei ≥ 2 Themen), `courseTotal()` L284 mit Memo je Anfrage | reuse | Neutrale Fortschrittsaggregation | Progress-Read-Model | R1-Free | R1-10, R1-16 | `membership-test.php` („Themen ergeben Modulumfang“), `content-flow-test.php` (Kursliste = Kursdaten) |
| INV-A-012 | `Learning/Services/LearningService.php:431` | PHP | `summary()` / `summaryFrom()` L442: Zählung je Stufe, `percent` = Anteil gefestigt, `secure_percent`, `due` | reuse | Neutral | Progress-Read-Model | R1-Free | R1-10 | `learning-review-test.php` („Zählung“) |
| INV-A-013 | `Learning/Services/LearningService.php:372` | PHP | `moduleGates()` (Drip je Modul) und `allowedQuestions()` L393: Fragen ohne gesperrte Module, im Teilumfang „erste n Fragen je Modul“ | abstract | Gekoppelt an Feature `question_bank` und Limit-Typ `questions_per_module` | Free-Preview-Regel „erste n Elemente je Abschnitt“ plus Drip-Policy | R1-Free | R1-13, R1-14 | `membership-test.php` (Teilumfang, Zeitgesteuerte Freigabe) |
| INV-A-014 | `Learning/Services/LearningService.php:460` | Daten | `setFavorite()` (beim Entfernen auch aus allen Sammlungen), `favoriteIds()` L481, `courseFavoriteCount()` L743 | reuse | Neutral | Favoriten-Service | R1-Free | R1-11 | `content-flow-test.php` („Merkliste“), `membership-test.php` |
| INV-A-015 | `Learning/Services/LearningService.php:751` | PHP | `wrongIds()` / `lastWrong()` L758: Fehlertraining „zuletzt falsch“ (Versuche > 0 und Serie 0) | reuse | Neutral | Fehlertraining | R1-Free | R1-11 | `learning-review-test.php` (Modus „Falsch beantwortete“) |
| INV-A-016 | `Learning/Services/LearningService.php:626` | API | `result()`: Ergebnis mit `correct_ids`, Musterantwort, Erklärung nur mit Feature `learning_explanations`, sonst `explanation_locked` als Upsell-Hinweis | abstract | Erklärungen als eigenes, separat verkauftes Feature ist ein LearnBoating-Produktschnitt | Feedback-Sichtbarkeit als Kursregel bzw. Edition-Feature | R1-Free | R1-09, R0-11 | `content-flow-test.php` („Lösung danach sichtbar“) |
| INV-A-017 | `Learning/Services/LearningService.php:812` | PHP | `rateLimit()`: höchstens 60 Antworten je Nutzer und Minute über einen atomaren Zähler (`Mobile\RateLimiter`, Bucket `answer\|<id>` L822), Fehler `ANSWER_RATE_LIMITED` | reuse | Missbrauchsschutz, neutral. Die Abhängigkeit zu `Mobile\` muss in die Infrastruktur verschoben werden | Rate-Limiter (Infrastruktur) | R1-Free | R1-20, R1-42, R0-21 | `learning-review-test.php` (T114) |
| INV-A-018 | `Learning/Services/CollectionService.php:36` | API | `collections()`: eigene Sammlungen je Kurs mit Anzahl (nur Favoriten zählen) | reuse | Neutral | Favoriten-Sammlungen | R1-Free | R1-11 | `membership-test.php` („Favoriten & Fehler: Sammlungen“) |
| INV-A-019 | `Learning/Services/CollectionService.php:55` | Daten | `create()` / `rename()` L70 / `delete()` L80 mit Namensbereinigung, Eindeutigkeit ohne Groß/Klein (L254), max. 30 je Kurs, 60 Zeichen (L19-20), Eigentumsprüfung (L233) | reuse | Neutral | Favoriten-Sammlungen | R1-Free | R1-11, R0-07 | `membership-test.php` (doppelter/leerer Name, fremde Sammlung) |
| INV-A-020 | `Learning/Services/CollectionService.php:91` | Daten | `assign()`: Favorit und Sammlungen einer Frage gemeinsam setzen (Transaktion). Nur Favoriten einsortierbar, nur Fragen im freigeschalteten Umfang | reuse | Neutral | Favoriten-Sammlungen | R1-Free | R1-11 | `membership-test.php` |
| INV-A-021 | `Learning/Services/CollectionService.php:139` | PHP | `questionIds()`: Lernen nach Sammlung (nur Favoriten) | reuse | Neutral | Smarttrainer-Eingabe `onlyIds` | R1-Free | R1-11 | `membership-test.php` („Lernen nach Sammlung“) |
| INV-A-022 | `Learning/Services/CollectionService.php:152` | API | `overview()`: Übersicht „Favoriten & Fehler“ mit Text, Nummer (`official_number`), Modul, Thema, Sammlungen | abstract | Feld `official_number` steht für die amtliche Fragennummer | Neutrale „Referenznummer“ der Frage | R1-Free | R1-11, R1-06 | `membership-test.php` („Übersicht: Favoriten mit Sammlungen“) |
| INV-A-023 | `Learning/Services/QuestionPresenter.php:111` | API | `present()`: Darstellung ohne Lösung, Antworten gemischt oder in der Snapshot-Reihenfolge, Buchstaben A–Z, Thema (`smart_category` oder `topic`), Medien | reuse | Neutral | Question-Renderer | R1-Free | R1-06 | `content-flow-test.php` („Frage ohne Lösung ausgeliefert“) |
| INV-A-024 | `Learning/Services/QuestionPresenter.php:147` | PHP | `token()` / `verify()` L155: HMAC-SHA256-Token (`wp_salt('auth')`) mit Nutzer, Revision, Reihenfolge, Kontext, Zeit, Nonce. Gültig 2 h (L30), Fehler `QUESTION_TOKEN_EXPIRED` | reuse | Manipulationsschutz, neutral | Signed-Question-Token | R1-Free | R1-20, R0-07 | `content-flow-test.php` (manipuliertes/fremdes Token 422, TTL 2 h), `mobile-api-test.php` |
| INV-A-025 | `Learning/Services/QuestionPresenter.php:177` | PHP | `evaluate()`: single = genau eine richtige, multi = exakte Menge, `open_text` = Selbsteinschätzung „gewusst“; `modelAnswer()` L197 | reuse | Neutrale Bewertungsregeln | Scoring-Regeln je Fragetyp | R1-Free | R1-06, R1-09 | `content-flow-test.php`, `learning-review-test.php` |
| INV-A-026 | `Learning/Services/QuestionPresenter.php:50` | PHP | `preload()`: Revisionen, Antworten und Bilder vieler Fragen in Blöcken zu je 500 laden (Prüfung, Videoquiz) | reuse | Performance, neutral | Repository-Batch-Load | R1-Free | R1-22 | indirekt `content-flow-test.php` |
| INV-A-027 | `Learning/Services/QuestionPresenter.php:74` | Daten | Medienfilter: Bilder mit blockierendem Rechtestatus (`Overrides::BLOCKING_RIGHTS`) oder archivierte Bilder werden nicht ausgeliefert, Alt-Text-Ersatz | abstract | Hängt am Rechte-Override-Modul (Teil C) mit LearnBoating-Rechtewerten | Media-Rights-Policy (Provenienz) | R1-Free | R1-07, R1-15, R0-10 | `content-flow-test.php` („Rechte-Override“) |
| INV-A-028 | `Learning/Services/QuestionPresenter.php:209` | PHP | `shuffle()`: Fisher-Yates mit `random_int` | reuse | Neutral. Für deterministische Tests braucht es eine injizierbare Zufallsquelle | Randomizer-Interface (seedbar im Test) | R1-Free | R1-08, R0-05 | – (nur indirekt) |

### 2.2 Learning – Zugriff (AccessService)

| ID | Quellpfad:Zeile | Art | Funktion | Klass. | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Quelltest |
|---|---|---|---|---|---|---|---|---|---|
| INV-A-029 | `Learning/Services/AccessService.php:24` | Daten | Feature-Katalog (9 Konstanten L24-34), Limit-Typen je Feature (L37), Drip-Features (L48) | replace | Die Liste mischt neutrale (`question_bank`, `exam_simulation`, `video_course`, `downloads`, `learning_explanations`) und fachliche Features (`navigation_trainer`, `praxis_trainer`, `radio_training`, `device_simulation`) | Registrierbares Feature-/Activity-Register mit Limit-Typ je Activity | R1-Free | R1-03, R1-13, R0-08 | `membership-test.php` (Teilumfang je Bereich) |
| INV-A-030 | `Learning/Services/AccessService.php:50` | PHP | `can()` / `full()` L58 / `limit()` L64 (null = unbegrenzt, 0 = kein Zugriff, n = Teilumfang) / `scope()` L72 / `scopes()` L91 (gebündelt) / `features()` L127 / `limits()` L133. Filter `bootspruefung_can_access` (L80, L119) | reuse | Kern der Zugriffsentscheidung, fachneutral. Hook- und Präfixnamen werden ersetzt | Policy-Service „Grant je Kurs/Feature“ | R1-Free | R1-03, R1-13, R1-14 | `membership-test.php`, `extensions-test.php`, `mobile-api-test.php` |
| INV-A-031 | `Learning/Services/AccessService.php:77` | PHP | Freigabequellen: Free-Regel (`bp_access_rule`, `free`/`free_limited` mit Gültigkeitsfenster, L335/L357), aktive Grants (`bp_entitlement`) und Einstellung `default_access = logged_in` („Zugang ohne Shop“) | reuse | Neutrale Grant-Quellen ohne Woo-Pflicht | Access-Rule-Repository und Settings | R1-Free | R1-13, R0-27 | `membership-test.php` („ohne Regel kein Zugriff“, „Vollzugang hebt Teilumfang auf“) |
| INV-A-032 | `Learning/Services/AccessService.php:263` | PHP | Drip: `unlockTimes()`, `unlockAt()` L285, `unlocked()` L307. Modul/Kapitel wird n Tage nach Zugangsbeginn frei; `accessStart()` L315 = frühester aktiver Grant, sonst Registrierung | reuse | Neutral | Drip-Policy | R1-Free | R1-13 | `membership-test.php` („Modul 2 erst nach 7 Tagen“, „ohne Kauf: Beginn = Registrierung“) |
| INV-A-033 | `Learning/Services/AccessService.php:154` | Commerce | `purchaseStates()` / `stateFromRows()` L200: Kaufstatus je Kurs (active > pending > suspended > expired; revoked ergibt keinen Status) mit Bestellung, SKU, Laufzeit, Video-Upgrade-Kennung, Callback „Bestellung noch offen“ | abstract | Status-Priorisierung ist neutral, aber Felder `order_id`/`product_id`/`sku_snapshot` und Video-Kennung sind Shop-gebunden | Neutraler Grant-Status im Kern; Bestell-Details über R3-Adapter | R1-Free (Status) + R3 (Bestellbezug) | R0-09, R1-13, R3-02, R3-03 | `membership-test.php` (Zugangsstatus, QA-03/UX-08) |
| INV-A-034 | `Learning/Services/AccessService.php:391` | Commerce | `entitledFeatures()` / `entitledByCourse()` L424: Upgrades gelten nur mit Basiskurs (Video-Upgrade per `ShopCatalog::VIDEO_SUFFIX`, Simulator-Upgrade per `SIM_UPGRADE` mit `SIM_COURSES`) | abstract | Die Regel „Add-on-Grant nur mit gültigem Basis-Grant“ ist neutral, die SKU-Konstanten sind Funk-/Shop-spezifisch | Grant-Abhängigkeit (requires_grant) als Datenfeld | R1-Free (Regel) / R3 (Mapping) | R0-09, R1-13, R3-02 | `membership-test.php` („Video-Upgrade erkannt“) |
| INV-A-035 | `Learning/Services/AccessService.php:454` | PHP | `courseRules()` / `groupFreeRules()` L477 / `groupDripRules()` L502: alle Regeln einmal je lesender REST-Anfrage laden (`RequestMemo`) | reuse | Performance mit gleicher Semantik wie der SQL-Pfad | Request-Cache in der Policy | R1-Free | R1-25, R0-21 | `mobile-api-test.php` (indirekt) |

### 2.3 Exams – Prüfungssimulation (ExamService)

| ID | Quellpfad:Zeile | Art | Funktion | Klass. | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Quelltest |
|---|---|---|---|---|---|---|---|---|---|
| INV-A-036 | `Exams/Services/ExamService.php:82` | API | `overview()`: Bögen mit Bestehensstruktur, Fragetypen, Sperrgrund `trial`/`drip`, letzter Versuch je Bogen (ohne abgebrochene), laufende Zufallsprüfung, Verlauf (10) und alle Ergebnisse (bis 500) | reuse | Neutral | Exam-Overview-Read-Model | R1-Free | R1-08, R1-09, R1-16 | `content-flow-test.php` (Drip-Bogen), `learning-review-test.php` (Verlauf ohne Abbruch) |
| INV-A-037 | `Exams/Services/ExamService.php:160` | API/Daten | `start()`: Start oder Fortsetzen derselben laufenden Sitzung. Snapshot (`rule_snapshot`: Regelwerk, Zeit, Grenzen, Zuordnung Frage → Teil, Blueprints, Bogennummer) und `bp_exam_session_question` mit fester Antwortreihenfolge in einer Transaktion; `flags()` L328 | reuse | Konzept 26.1 „Prüfungsengine, Prüfungs-Snapshots“ | Exam-Attempt-Service mit unveränderlichem Snapshot | R1-Free | R1-08, R1-27, R0-23 | `content-flow-test.php` („Start und Fortsetzen derselben Simulation“) |
| INV-A-038 | `Exams/Services/ExamService.php:183` | PHP | Startsperre je Nutzer per `NamedLock` (`exam_start:<id>`, L319, Wartezeit 10 s); belegt → `EXAM_START_BUSY` | reuse | Nebenläufigkeitsschutz | Named-Lock (Infrastruktur, Teil C) | R1-Free | R1-42, R0-21 | `content-flow-test.php` (gehaltene Startsperre → 422 „erneut“) |
| INV-A-039 | `Exams/Services/ExamService.php:196` | PHP | Start-Limit: höchstens 40 neue Sitzungen je Nutzer und Stunde (Filter `bootspruefung_exam_starts_per_hour`); Fortsetzen zählt nicht; `EXAM_START_RATE_LIMITED` | reuse | Missbrauchsschutz | Rate-Limiter | R1-Free | R1-20, R1-42 | – (kein Integrationstest im Bereich gefunden) |
| INV-A-040 | `Exams/Services/ExamService.php:202` | PHP | Fester Bogen: Bogen gehört zum Kurs, alle Fragen müssen auslieferbar sein, Drip gilt auch für Bögen (`paperUnlockTimes` L784) | reuse | Neutral | Feste Prüfungsform | R1-Free | R1-08, R1-13 | `content-flow-test.php` („fester Bogen 1“, „Drip: fester Bogen gesperrt“) |
| INV-A-041 | `Exams/Services/ExamService.php:223` | PHP | Zufallsprüfung nach Blueprint je Modul ohne Doppelungen. Gesperrte Module blockieren den Start. Ist der Pool zu klein, läuft die Prüfung verkürzt (`reduced`) und nur als Übung | reuse | Neutral | Blueprint-Ziehung | R1-Free | R1-08 | `content-flow-test.php` („Blueprint 7 Basis + 23 See“, „reduced“) |
| INV-A-042 | `Exams/Services/ExamService.php:982` | PHP | `applicableParts()`: Bei Kombiprüfungen gelten nur die Teile, deren Modul vorkommt. Zeit = Summe der Teilzeiten (`seconds`), Gesamtquote = Summe der Teilgrenzen | abstract | Mechanik neutral; Anlass sind Kombikurse (Festlegung 07.11.2026, Kommentar L259) | Mehrteilige Prüfungsregel mit Zeit/Grenze je Teil | R1-Free | R1-08, R1-33 | `content-flow-test.php` (Kombi-Regelwerke) |
| INV-A-043 | `Exams/Services/ExamService.php:263` | Daten | Snapshot-Kennzeichen `live` (Regelwerk verifiziert und nicht verkürzt) gegenüber Übungsmodus; „Ohne verifiziertes Regelwerk Übungsmodus“ (L37) | abstract | `live` bedeutet heute „amtlich verifiziertes Regelwerk“. Course Captain hat keine amtlichen Profile (Kap. 26.2) | Status „freigegebene Prüfungsregel“ gegenüber „Entwurf/Übung“ | R1-Free | R1-08, R1-26, R0-08 | `content-flow-test.php` („unverifiziertes Regelwerk → Übungsmodus“), `e2e/learning-flow.mjs` |
| INV-A-044 | `Exams/Services/ExamService.php:336` | API | `show()`: laufende Sitzung (Fragen ohne Lösung, Auswahl, Restzeit, Markierung, Musterantwort nach Aufdecken), abgebrochene oder ausgewertete Sitzung. Abgelaufene Sitzungen werden dabei ausgewertet | reuse | Neutral | Exam-Attempt-View | R1-Free | R1-08, R1-20 | `learning-review-test.php`, `content-flow-test.php` („30 Fragen ohne Lösungen“) |
| INV-A-045 | `Exams/Services/ExamService.php:405` | API/Daten | `saveAnswer()`: Gerätezeit (`age_ms`/`answered_at`) für offline gepufferte Antworten, begrenzt auf [Start bzw. Aufdecken, jetzt]. Nach Frist + 5 s → `EXAM_EXPIRED`. Strenger Modus bei Kulanz 0. Leere Mehrfachauswahl nimmt die Antwort zurück. Status-Sperre `FOR UPDATE`, ein älterer Stand überschreibt keinen neueren (`applied`) | reuse | Prüfungsintegrität (QA-02) | Exam-Answer-Service | R1-Free | R1-20, R1-42 | `learning-review-test.php` (QA-02, T129), `e2e/learning-flow.mjs` (offline puffern) |
| INV-A-046 | `Exams/Services/ExamService.php:56` | PHP | `lateTolerance()`: Kulanz für nachgesendete Antworten 120 s (L45), Filter `bootspruefung_exam_late_tolerance` 0–600, `GRACE_SECONDS` 5 (L41) | reuse | Neutral; Hook wird umbenannt, Wert als Einstellung denkbar | Exam-Timing-Policy | R1-Free | R1-20, R0-28 | `learning-review-test.php` (Kulanz 0) |
| INV-A-047 | `Exams/Services/ExamService.php:486` | API | `setFlag()`: Frage zur Überprüfung markieren, Rückgabe Anzahl markierter Fragen. Nach Fristende oder Abgabe gesperrt | reuse | Neutral | Exam-Flagging | R1-Free | R1-08 | `navigation-check-test.php` (Web-REST flag, 404/400/422), `e2e/learning-flow.mjs` |
| INV-A-048 | `Exams/Services/ExamService.php:503` | API | `reveal()`: Musterantwort einer offenen Frage freigeben; ohne Aufdecken keine Selbsteinschätzung (`EXAM_ANSWER_LOCKED`, L435) | reuse | Neutral (`open_text`) | Exam-Open-Question | R1-Free | R1-06, R1-09 | `mobile-api-test.php` (App-Reveal) |
| INV-A-049 | `Exams/Services/ExamService.php:518` | API | `submit()`: Abgabe (idempotent, „Zeit abgelaufen“ ab Fristende) | reuse | Neutral | Exam-Attempt-Service | R1-Free | R1-08, R1-20 | `learning-review-test.php`, `content-flow-test.php` |
| INV-A-050 | `Exams/Services/ExamService.php:534` | API | `abort()`: Abbruch ohne Wertung (`aborted`), Fragen sofort wieder frei, Antworten bleiben gespeichert. Abgelaufene Sitzung wird stattdessen gewertet | reuse | Neutral | Exam-Attempt-Service | R1-Free | R1-08 | `learning-review-test.php` („Prüfung abbrechen“) |
| INV-A-051 | `Exams/Services/ExamService.php:562` | PHP | `wrongQuestionIds()`: falsch beantwortete Fragen einer ausgewerteten Prüfung (Eingabe für Fehlertraining) | reuse | Neutral | Fehlertraining aus Prüfung | R1-Free | R1-11 | `mobile-api-test.php:617`, `:627` (positiv über die App-API; Web-Pfad und Negativfälle fehlen, R0-05 L-02) |
| INV-A-052 | `Exams/Services/ExamService.php:576` | Cron | `expireRunning()`: abgelaufene Sitzungen nach Kulanz auswerten (stündlich über `bp_learning_hourly`, Aufgabe `exam_expire` in `Plugin::hourly`, und vor jedem Zugriff). Defekte Sitzung → `markBroken()` L601: Status `error`, `Cron::recordError` | reuse | Wartungsjob, neutral | Exam-Expiry-Job | R1-Free | R1-17, R1-20 | `content-flow-test.php` („abgelaufene Prüfung automatisch abgegeben“), `learning-review-test.php` |
| INV-A-053 | `Exams/Services/ExamService.php:612` | Daten | `evaluate()` mit `partResults()` L675 und `assignParts()` L713: Wertung nur innerhalb Frist + 5 s; bestanden = Gesamtquote (Standard 0,75) und alle Teilgrenzen; Zuordnung Frage → Teil aus dem Snapshot; leerer Teil gilt als bestanden; Ergebnis-JSON | reuse | Neutrale Bewertungslogik; der Standard 0,75 wird Einstellung | Exam-Scoring | R1-Free | R1-09, R1-27, R0-05 | `content-flow-test.php` („27/30“, „Teilgrenze verfehlt“, „Snapshot-Zuordnung, leerer Teil“) |
| INV-A-054 | `Exams/Services/ExamService.php:830` | API | `resultView()`: Ergebnis mit Antworten, Lösung, Erklärung (nur mit Feature), Auswertung je Thema (Fallback Modulname), Statistik offene Fragen, Markierungen | reuse | Neutral | Exam-Result-View | R1-Free | R1-09 | `navigation-check-test.php` („Ergebnis zeigt Markierungen“), `e2e/learning-flow.mjs` |
| INV-A-055 | `Exams/Services/ExamService.php:908` | UI/PHP | Regelanzeige: `ruleSummary()`, `passingStructure()` L929, `passingText()` L1010, `typeCounts()` L943, `simulationTypes()` L966 | abstract | Texte hart deutsch, Kommentare mit SBF-Beispielen; Struktur neutral | Rule-Presenter (i18n) | R1-Free | R1-09, R1-19 | `content-flow-test.php` |
| INV-A-056 | `Exams/Services/ExamService.php:1044` | PHP | `runningSession()`: Zugriff bei jeder Aktion neu prüfen (Erstattung, Ablauf). Im Teilumfang nur freie Bögen (QA-10). Abgelaufen → auswerten und `EXAM_EXPIRED` | reuse | Neutral | Exam-Policy | R1-Free | R1-20, R1-27, R0-09 | `learning-review-test.php` (QA-10) |
| INV-A-057 | `Exams/Services/ExamService.php:167` | PHP | Teilumfang Prüfung: nur die ersten n Bögen, keine Zufallsprüfung (Limit-Typ `exam_papers`) | abstract | Neutral, aber an Feature-Konstante gebunden | Free-Preview-Regel für Prüfungen | R1-Free | R1-13 | `membership-test.php` („erste 2 Bögen frei, Zufallsprüfung gesperrt“) |

### 2.4 Trainers – Navigation, Praxis, Video, Funk, Gerät

| ID | Quellpfad:Zeile | Art | Funktion | Klass. | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Quelltest |
|---|---|---|---|---|---|---|---|---|---|
| INV-A-058 | `Trainers/TrainerService.php:56` | PHP | `navigationScore()`: amtliche Wertung 7 von 9 Punkten (L45-47), „teilweise“ zählt nicht | exclude | Amtliche Navigationswertung SBF See (Kap. 26.2 „spezielle Bewertungsschwellen“) | entfällt | exclude | – | `navigation-check-test.php` (FACH-10) |
| INV-A-059 | `Trainers/TrainerService.php:76` | API | `navigationTasks()` / `navigationTask()` L123 / `requireNavigationTask()` L108: Navigationsaufgaben je Kurs mit Teilaufgaben, Nachbarn, Teilumfang, „Siehe Karte“-Kennung (L171) | exclude | Seekarten-Aufgaben (Kap. 26.2) | entfällt; das Muster „Szenario mit Teilaufgaben und Selbsteinschätzung“ höchstens als neutrale Fallstudie (siehe E-A-03) | exclude | – | `navigation-check-test.php`, `content-flow-test.php`, `e2e/trainers-flow.mjs` |
| INV-A-060 | `Trainers/TrainerService.php:181` | API/Daten | `navigationAnswer()`: Antwort und Selbsteinschätzung (`correct`/`partly`/`wrong`) speichern; NULL-Semantik „Feld fehlt“ gegenüber „bewusst geleert“; automatische Prüfung | exclude | Hängt an `bp_navigation_*`. Die NULL/Leer-Semantik und das sichere SQL (L196-201) sind als Muster dokumentiert | entfällt (Muster für R2-02) | exclude | – | `content-flow-test.php` (Einschätzung bleibt, auch mit %), `navigation-check-test.php` |
| INV-A-061 | `Trainers/TrainerService.php:228` | API | `praxis()`: Karten nach Kategorie mit Frage, Musterantwort, Erklärung, Bild, Quelle und Lernstatus. Im Teilumfang nur gesperrter Titel | abstract | Technisch generische Karteikarten (Kap. 26.1 „Karteikarten“). Feldnamen `exam_reference`, `task_type` und der Name „Praxistrainer“ sind fachlich geprägt | Karteikarten-Activity (Kategorie, Vorder-/Rückseite, Bild, Quelle) | R1-Free | R1-11, R1-07 | `membership-test.php` („Praxis: 5 Karten“), `content-flow-test.php`, `e2e/trainers-flow.mjs` |
| INV-A-062 | `Trainers/TrainerService.php:272` | Daten | `praxisStatus()`: Kartenstatus `known`/`practice`/`open` (Upsert), Sperre im Teilumfang | abstract | wie INV-A-061 | Karteikarten-Status | R1-Free | R1-11 | `membership-test.php` („Status für gesperrte Karte abgewiesen“), `content-flow-test.php` |
| INV-A-063 | `Trainers/TrainerService.php:302` | API | `summary()`: Trainer-Überblick der Kursseite (Navigation + Praxis) | replace | Fest verdrahtete Summe zweier Trainer | Generische Activity-Übersicht je Kurs | R1-Free | R1-16, R1-07 | `content-flow-test.php` („Kursseite meldet Navigation, Praxis und Video“) |
| INV-A-064 | `Trainers/VideoService.php:45` | API | `course()`: Kapitel und Lektionen mit Dauer, Fortschritt, Quiz-/Schrittzahl, Drip je Kapitel, Teilumfang „erste n Lektionen“, nächste Lektion | abstract | Neutral bis auf die harte Ausnahme `module_title !== 'Knoten'` (L87) und die Rechte-Overrides | Video-/Lektions-Activity | R1-Free | R1-07, R1-10, R1-13 | `membership-test.php` (Video erste 2 Lektionen, Kapitel zeitgesteuert), `content-flow-test.php` |
| INV-A-065 | `Trainers/VideoService.php:173` | API | `lesson()`: Lektion mit Schrittgalerie, Videoquiz aus der Fragenbank (Token-Kontext `video:`), Fragen laufender Prüfungen ausgeblendet, vorige/nächste Lektion inkl. `next_locked`; Zugriff wird vermerkt (`touch` L263) | reuse | Neutral | Lektion mit Inline-Quiz | R1-Free | R1-07 | `content-flow-test.php` (Videoquiz ohne Lösungen, Kontext `video`), `e2e/trainers-flow.mjs` |
| INV-A-066 | `Trainers/VideoService.php:216` | Daten | Platzhalter-Lektionen (`is_placeholder`) zählen mit, liefern aber keine Video-URL (keine IP-Übertragung an Dritte) | reuse | Datenschutzregel, neutral | Media-Placeholder-Policy | R1-Free | R1-07, R0-17 | `content-flow-test.php` („Platzhalter-Lektion liefert keine externe Video-URL“) |
| INV-A-067 | `Trainers/VideoService.php:236` | API/Daten | `progress()`: Position und Abschluss speichern; `null` lässt die gespeicherte Position stehen (T216), Position 0–86 400 s | reuse | Neutral | Media-Progress | R1-Free | R1-10 | `content-flow-test.php` („Lektion abgeschlossen“) |
| INV-A-068 | `Trainers/VideoService.php:106` | PHP | `externalHost()`: Video auf fremdem Server erkennen (eigene Domain und Subdomains gelten als eigen, Filter `bootspruefung_video_first_party_hosts`) | reuse | Neutral (Einwilligung für externe Medien) | External-Media-Consent | R1-Free | R1-07, R1-34 | `membership-test.php` („Videos auf fremden Servern (T23)“) |
| INV-A-069 | `Trainers/VideoService.php:127` | PHP | `externalHosts()`: Liste fremder Video- und Untertitel-Hosts für das Consent-Banner, gespeichert im Transient `bp_learning_video_hosts` ohne Ablauf (Leerung beim Import über `ContentCache`) | abstract | Abnehmer ist der Theme-Filter `kurswasser_consent_external_hosts` (Kommentar L124) | Neutraler Hook bzw. Adapter für Consent-Plugins | R1-Free | R1-07, R1-34, R1-30 | `membership-test.php` |
| INV-A-070 | `Trainers/VideoService.php:159` | PHP | `captionsUrl()`: WebVTT neben dem Video bei Einstellung `video_captions = vtt`, Filter `bootspruefung_video_captions_url`, nur `https` oder eigene Domain | reuse | Barrierefreiheit, neutral | Caption-Resolver | R1-Free | R1-07, R1-19 | `operations-test.php` (Z. 719–721) |
| INV-A-071 | `Trainers/RadioService.php:42` | API | `overview()` / `summary()` L297: Funktraining-Übersicht (Texte je Katalog und Übungsart, Vokabelkategorien, Funkpraxis, Gerät) | exclude | Funkausbildung SRC/UBI | entfällt | exclude | – | `content-flow-test.php` („Funktraining SRC: 27 Texte“) |
| INV-A-072 | `Trainers/RadioService.php:81` | API | `spelling()`: Buchstabiertafel mit Aussprachehilfen, Ziffern je Funkkatalog | exclude | Funkspezifisch | entfällt | exclude | – | `e2e/trainers-flow.mjs` |
| INV-A-073 | `Trainers/RadioService.php:112` | API | `text()` / `hideSolution()` L150: Funktext mit markierten Buchstabierwörtern, Zielsprache bis zur Auswertung verborgen | exclude | Funkspezifisch | entfällt | exclude | – | `content-flow-test.php` (T165) |
| INV-A-074 | `Trainers/RadioService.php:177` | API/Daten | `evaluate()` / `setDone()` L211: Mitschrift und Übersetzung per Wortabgleich auswerten und speichern (`bp_radio_progress`, Typ `text`) | exclude | Funkspezifisch | entfällt | exclude | – | `content-flow-test.php` („100 %“), `e2e/trainers-flow.mjs` |
| INV-A-075 | `Trainers/RadioService.php:249` | Daten | `vocabulary()` L219 / `vocabularyAnswer()`: Vokabelkarten „gewusst/nicht gewusst“, gelernt nach 3 × in Folge (L24). Doppelklick-Schutz über Vergleich der erwarteten Serie oder ein 2-s-Fenster, Zeilensperre | abstract | Mechanik ist neutral (Karteikarten mit Lernziel, z. B. „Sprachvokabeln“ aus Kap. 26.3). Tabelle, Kataloge und Kategorien sind funkgebunden | Karteikarten-Modus „Serie bis gelernt“ (zusammen mit INV-A-061, siehe E-A-02) | R1-Free | R1-11, R1-42 | `content-flow-test.php` („nach 3 × gewusst gelernt“, „doppelte Antwort ignoriert“) |
| INV-A-076 | `Trainers/RadioText.php:17` | PHP | NATO-Alphabet, Ziffern DE/EN, `spellGerman()` L40, `normalize()` L32, `wordMatch()` L48 (Prozent und fehlende Wörter, Zahlwörter → Ziffern) | exclude | Funkspezifisch; der Wortabgleich wird nur von entfallenden Trainern genutzt | entfällt | exclude | – | `content-flow-test.php` (indirekt) |
| INV-A-077 | `Trainers/RadioChecks.php:33` | PHP | `run()` / `checkText()` L44 / `fold()` / `normalize()`: Funkspruch gegen Regex-Prüfpunkte mit Mindestanzahl prüfen | exclude | Funkprotokoll-Prüfung (Kap. 26.2 „Spezialprotokolle“) | entfällt | exclude | – | `content-flow-test.php` (Musterlösungen erfüllen alle Prüfpunkte) |
| INV-A-078 | `Trainers/RadioPracticeService.php:37` | API | `overview()` / `show()` L62: Funkpraxis-Szenarien je Katalog mit Feldern und Teilumfang | exclude | Funkspezifisch | entfällt | exclude | – | `content-flow-test.php` („ohne Prüfmuster ausgeliefert“) |
| INV-A-079 | `Trainers/RadioPracticeService.php:91` | API/Daten | `evaluate()` / `setDone()` L116: Felder gegen Prüfpunkte auswerten, Musterlösung je Feld | exclude | Funkspezifisch | entfällt | exclude | – | `content-flow-test.php`, `e2e/trainers-flow.mjs` |
| INV-A-080 | `Trainers/RadioPracticeService.php:130` | API | `samples()`: Muster-Funksprüche je Meldungsart (`SAMPLE_KINDS` L123: distress … routine) | exclude | Funkspezifisch | entfällt | exclude | – | `e2e/trainers-flow.mjs` |
| INV-A-081 | `Trainers/RadioPracticeService.php:169` | PHP | `catalogsFor()` (SRC/UBI) / `count()` L174 | exclude | Funkspezifisch | entfällt | exclude | – | – |
| INV-A-082 | `Trainers/DeviceSimService.php:90` | API | `config()` mit `data()` L61 (`bp_device`), `available()` L72, `premium()` L78, `catalogs()` L84: Konfiguration der Gerätesimulation (Geräte, Lektionen, Prüfungen, REST-URLs `…/device-sim/{evaluate,progress,exam}`, Stufe free/premium) | exclude | Fachgebundene Gerätesimulation (Kap. 26.2); JS-Lizenz ungeklärt (R0-01) | entfällt | exclude | – | `content-flow-test.php` (Gerätesimulation) |
| INV-A-083 | `Trainers/DeviceSimService.php:163` | Commerce | `offers()` (WooCommerce `ShopCatalog::SIM_PASSES`, Filter `bootspruefung_sim_offers`) und `lockedSummary()` L141 | exclude | Fachprodukt-Upsell | entfällt | exclude | – | `content-flow-test.php` („Simulator ohne Freischaltung: free“) |
| INV-A-084 | `Trainers/DeviceSimService.php:188` | API/Daten | `evaluate()`, `progress()` L208 (Stufe 1–3), `exam()` L236 mit Funkspruch-Belegen (6 h, `geraet-check`, Beleg wird verbraucht) | exclude | Fachgebunden. Das Muster „serverseitiger Beleg vor Fortschrittsstufe“ ist dokumentiert | entfällt | exclude | – | `content-flow-test.php` („Stufe nur mit bestandenem Funkspruch, Beleg verbraucht“) |
| INV-A-085 | `Trainers/DeviceSimService.php:270` | API | `summary()`: gemeisterte Lektionen über alle Geräte | exclude | Fachgebunden | entfällt | exclude | – | `content-flow-test.php` |
| INV-A-086 | `Trainers/NavigationCheck.php:73` | PHP | `check()` / `decode()` L54 / `coverage()` L45: eigene Navigationswerte gegen Lösungswerte mit Toleranz prüfen (Status `within`/`deviates`/`unrecognized`) | exclude | Nautische Größen, Einheiten und Kürzel (L25-37, L366) | entfällt (siehe E-A-03: optional neuer neutraler Typ „Zahl mit Toleranz“) | exclude | – | `navigation-check-test.php` (78 Lösungswerte, ±2° über 360°) |
| INV-A-087 | `Trainers/NavigationCheck.php:124` | PHP | Parser `parse()`, `collapseCalculations()` L214, `assign()` L262, `deviation()` L480, `format()` L503: Positionen, Uhrzeiten, Kurse, sm/kn | exclude | Rein nautisch | entfällt | exclude | – | `navigation-check-test.php` |

### 2.5 Gate – Zugangstor, geschützte Dateien, Downloads

| ID | Quellpfad:Zeile | Art | Funktion | Klass. | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Quelltest |
|---|---|---|---|---|---|---|---|---|---|
| INV-A-088 | `Gate/ContentGate.php:105` | PHP | `requirement()` L80 (Post-Meta `_bp_required_access`), `allows()` L87 (`any` = irgendein voller Zugang, sonst voller Zugang zum Feature, Teilumfang zählt nicht), `canView()` (Redakteure dürfen immer, Merker je Anfrage), `locked()` L126 | reuse | Generische Inhaltssperre (Kap. 26.1 „Zugangsrechte“) | Content-Gate-Policy | R1-Free | R1-14 | `membership-test.php` („Geschützte Seiten und Abschnitte“) |
| INV-A-089 | `Gate/ContentGate.php:132` | UI | `filterContent()` auf `the_content` (Priorität 1 und `PHP_INT_MAX`) und `the_content_feed`, Hinweis statt Inhalt, `NoCache::mark` | reuse | Neutral | Content-Gate-Filter | R1-Free | R1-14, R1-25 | `membership-test.php` |
| INV-A-090 | `Gate/ContentGate.php:151` | UI | `builderData()` (Elementor) / `builderRender()` L157 (Beaver Builder): Layoutdaten gesperrter Beiträge verwerfen | reuse | Page-Builder-Kompatibilität (T77) | Builder-Kompatibilitätsschicht | R1-Free | R1-30, R1-14 | `membership-test.php` („Page-Builder schreibt Originalinhalt zurück“) |
| INV-A-091 | `Gate/ContentGate.php:162` | UI | `filterExcerpt()` / `filterExcerptLate()` L177: kein automatischer Auszug aus geschütztem Text, nur der manuelle Auszug | reuse | Neutral (verhindert Leaks über SEO/Feeds) | Content-Gate-Filter | R1-Free | R1-14, R1-37 | `membership-test.php` |
| INV-A-092 | `Gate/ContentGate.php:189` | UI | `postClass()`: CSS-Klassen `bp-requires-access` / `bp-access-locked` | reuse | Neutral, Präfix wird ersetzt | Content-Gate-Filter | R1-Free | R1-14 | `membership-test.php` („10 gesperrte Beiträge: Klassen“) |
| INV-A-093 | `Gate/ContentGate.php:199` | UI | Shortcode `[bootspruefung_zugang kurs funktion hinweis]` für Abschnitte | reuse | Neutral; Name und Attribute werden neu vergeben (Kap. 26.2) | Shortcode „Inhalt nur mit Zugang“ | R1-Free | R1-14, R1-28 | `membership-test.php` |
| INV-A-094 | `Gate/ContentGate.php:215` | UI | Block `bootspruefung/zugang` (api_version 3, Editor-Skript `assets/admin/restricted-block.js`, lokalisiert als `bpRestricted`) | reuse | Neutral; Block-Name wird neu vergeben | Block „Inhalt nur mit Zugang“ | R1-Free | R1-14, R1-28 | `membership-test.php` („Block sperrt Abschnitt“) |
| INV-A-095 | `Gate/ContentGate.php:255` | UI/Commerce | `notice()` / `offersHtml()` L274: Hinweis mit Anmeldelink und passenden Produkten (`WooCommerce\Integration::offers`), je Anfrage gemerkt | abstract | Die Produktliste ist Woo-gebunden; Free braucht einen Hinweis ohne Shop | Gate-Notice mit optionalem Offer-Provider-Interface | R1-Free (Hinweis) / R3 (Angebote) | R1-14, R3-02, R3-13 | `membership-test.php` (`bp-gate-notice`) |
| INV-A-096 | `Gate/ContentGate.php:289` | UI | Metabox „Lernzugang erforderlich“ (`metaBox`, `renderMetaBox` L298, `save` L317 mit Nonce `bp_required_access`) an allen öffentlichen Post-Typen außer Anhang/Produkt | reuse | Neutral | Gate-Metabox | R1-Free | R1-14, R1-05 | `membership-test.php` |
| INV-A-097 | `Gate/ContentGate.php:49` | PHP | `register()` / `flush()` L43: Merker verwerfen bei Nutzerwechsel, Grant-Änderung (`bootspruefung_entitlements_changed`), Gutschein (`bootspruefung_voucher_granted`/`_revoked`), Produkt speichern und Meta-Änderung | abstract | Gutschein- und Produkt-Hooks sind Commerce-Kopplungen | Ein neutrales Ereignis „Grant geändert“ | R1-Free | R1-25, R0-21 | `membership-test.php` („gemerkt“, „Vorgabe entfernt: sofort frei“) |
| INV-A-098 | `Gate/ProtectedFiles.php:191` | Daten | `protect()` / `unprotect()` L242: Anhang (inkl. Vorschaubilder/Originale, `derivedFiles` L307) in den geschützten Ordner verschieben bzw. zurücklegen. Das Schutzfeld wird erst nach erfolgreichem Verschieben gesetzt, Fehler als Meta und Audit-Eintrag | reuse | Konzept 26.1 „Medien, Sicherheit“ | Protected-Media-Store | R1-Free | R1-14, R0-10 | `content-flow-test.php` (Rückfall), `membership-test.php`, `security-test.php` |
| INV-A-099 | `Gate/ProtectedFiles.php:344` | API | `serve()` unter `/<base>/datei/<id>/`: 404 ohne Vorgabe, Login-Weiterleitung, 403 mit Hinweis, Redakteure dürfen immer. `stream()` L374: Ausgabepuffer leeren, `nosniff`, CSP `sandbox` außer bei PDF, `no-store`, Filter `bootspruefung_protected_file_sendfile` | reuse | Neutral | Protected-Download-Endpoint | R1-Free | R1-14, R0-10 | `membership-test.php` („Geschützte Dateien“) |
| INV-A-100 | `Gate/ProtectedFiles.php:402` | PHP | `inlineMime()` (nur PDF, Rasterbilder, Audio/Video inline), `clearOutputBuffers()` L412, `contentDisposition()` L425 (RFC 6266/8187, Schutz gegen Header-Injection) | reuse | Neutral | Download-Header-Helfer | R1-Free | R1-14 | `mobile-api-test.php` (Z. 1212–1213), `operations-test.php` (Z. 781) |
| INV-A-101 | `Gate/ProtectedFiles.php:111` | UI | Mediathek-Felder `fields()` / `saveFields()` L155: Auswahl „Kurs · Funktion“ nur mit `Capabilities::MANAGE_CONTENT` (T195) | reuse | Neutral | Media-Gate-Feld | R1-Free | R1-14, R1-03 | `membership-test.php` |
| INV-A-102 | `Gate/ProtectedFiles.php:281` | PHP | `url()` (Anhang-URL → geprüfter Download), `attachedFile()` L286, `onDelete()` L293, `restAttachment()` L331 (REST ohne Dateiname, Größe und Pfad) | reuse | Neutral, verhindert Umgehung | Media-Gate-Integrität | R1-Free | R1-14, R0-10 | `membership-test.php` („Mediathek-URL zeigt auf geprüfte Auslieferung“) |
| INV-A-103 | `Gate/ProtectedFiles.php:57` | UI | `adminNotice()` / `fail()` L75: Fehlermeldung einmalig je Admin (Transient `bp_protect_notice_<id>`, 1 h, max. 10) | reuse | Neutral | Admin-Notices | R1-Free | R1-38, R1-17 | `content-flow-test.php` (`ERROR_META`) |
| INV-A-104 | `Gate/ProtectedFiles.php:48` | Commerce | `noteDownload()`: PDF-Download beendet die Geld-zurück-Garantie für den Kurs und seine Kombi-/Ergänzungskurse (`MoneyBack::recordDownload`) | replace | Garantie-Logik ist Commerce (Teil B). Der Kern soll nur ein Nutzungsereignis senden | Ereignis „Lerninhalt genutzt“ für einen Commerce-Adapter | R3 | R0-40, R3-19 | `membership-test.php` („Schnupper-Paket, Geld-zurück-Garantie“) |
| INV-A-105 | `Gate/ProtectedFiles.php:89` | PHP | `isChart()`: Seekarten (`_bp_chart`) lassen sich nie freigeben (L164) | exclude | BSH-Seekarten | entfällt | exclude | – | `content-flow-test.php` („Seekarte lässt sich nicht frei zugänglich machen“) |
| INV-A-106 | `Gate/SectionFiles.php:46` | API | `forCourse()`: geschützte PDFs je Lernbereich (`SECTIONS` L21: Navigation, Prüfung, Praxis, Funk) mit gesperrt/URL/Größe; Seekarten ausgenommen | abstract | Mechanik neutral, Bereichsliste fachlich | Dateien je Activity bzw. Kursbereich | R1-Free | R1-07, R1-14 | `membership-test.php` (Bereichs-PDF gesperrt im Teilumfang) |
| INV-A-107 | `Gate/SectionFiles.php:31` | Daten | `PARTS` / `includedIn()` L38: Kombi- und Ergänzungskurse erben Dateien (harte Kurscode-Tabelle, Festlegung 07.11.2026) | replace | Kurscodes als Konstante; neutral wäre eine gepflegte Beziehung „Kurs enthält Kurs“ | Bundle-/Teilkurs-Relation als Daten | R1-Free | R1-04, R1-13 | `membership-test.php` („Ergänzung SRC → UBI erhält UBI-PDFs“) |
| INV-A-108 | `Gate/SectionFiles.php:24` | UI | `SLOT_META` / `SLOTS` (`vocabulary`, `samples`, `spelling`) / `slot()` L83 und das Slot-Feld in `ProtectedFiles::fields()` L140: Funk-Unterseiten als zusätzlicher Anzeigeort | exclude | Nur für Funk-Seiten | entfällt | exclude | – | `membership-test.php` |
| INV-A-109 | `Gate/Charts.php:59` | UI/Daten | `catalog()` L24 (D49), `state()` L37, `attach()`, `setLicense()` L80 (Lizenznachweis Pflicht), `remove()` L90, Option `bp_learning_charts`, Audit-Einträge | exclude | BSH-Lizenzkarte (Kap. 26.2 „Fachkarten“) | entfällt | exclude | – | `membership-test.php` („Seekarte D49“), `content-flow-test.php` |
| INV-A-110 | `Gate/Charts.php:132` | PHP | `allowFile()` (Filter `bootspruefung_protected_file_allowed`), `forCourse()` L113, `available()` L101: Karte nur mit Lizenz und vollem Navigationszugang | exclude | wie oben | entfällt | exclude | – | `membership-test.php` |
| INV-A-111 | `Gate/DownloadService.php:22` | API | `items()`: Downloads-Feature, PDFs je Kurs (Meta `_bp_access_course`), Teilumfang „erste n Dateien“ | reuse | Neutral | Kurs-Downloads | R1-Free | R1-07, R1-14 | `membership-test.php` („Downloads“) |
| INV-A-112 | `Gate/DownloadService.php:55` | PHP | `allowed()` / `allowedByRequirement()` L61: Freigabe nach Vorgabe, auch über Kombi/Ergänzung, Filter `bootspruefung_protected_file_allowed` | reuse | Neutral (Kombi-Teil über INV-A-107) | Download-Policy | R1-Free | R1-14 | `membership-test.php` |

### 2.6 ExamGuide – Prüfungsanmeldung

| ID | Quellpfad:Zeile | Art | Funktion | Klass. | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Quelltest |
|---|---|---|---|---|---|---|---|---|---|
| INV-A-113 | `ExamGuide/ExamGuideService.php:35` | PHP | `enabled()`: Einstellung `exam_guide_enabled` | exclude | Prüfungsanmeldung (Kap. 26.2) | entfällt | exclude | – | `exam-guide-test.php` („deaktiviert: REST 404“), `e2e/guide-setup.php` |
| INV-A-114 | `ExamGuide/ExamGuideService.php:80` | API | `payload()` mit `guides()` L41 und `pagesFor()` L60: Reiter je Prüfung mit Gebühr, Fristen, Mindestalter, Unterlagen, Varianten, Prüfungsteilen, FAQ und Verbänden | exclude | Amtliche Prüfungsanmeldung | entfällt | exclude | – | `exam-guide-test.php`, `e2e/guide-flow.mjs` |
| INV-A-115 | `ExamGuide/ExamGuideService.php:112` | PHP | `variants()`, `catalogSize()` L128 (ohne `chart_task`), `scope()` L142, `pass()` L157: Umfang und Bestehensgrenze als Text | exclude | Amtliche Prüfungsformen | entfällt | exclude | – | `exam-guide-test.php` („24 Fragen aus 180“) |
| INV-A-116 | `ExamGuide/ExamGuideService.php:176` | API | `committees()` mit Rang je Kurs, `statesFor()` L208 (Bundesland per Regex), `address()` L227, `committeesNote()` L101 | exclude | Prüfungsausschüsse DSV/DMYV | entfällt | exclude | – | `exam-guide-test.php` („18 Prüfungsausschüsse“, „16 Bundesländer“) |
| INV-A-117 | `ExamGuide/GuideCorrections.php:79` | PHP | `apply()`, `forCourse()` L63, `firstVariant()` L52, `committeeCourses()` L119: redaktionelle Korrekturen (Gebühren, Texte, Variantenreihenfolge) | exclude | Fachinhalt | entfällt | exclude | – | `exam-guide-test.php` („Gebühren korrigiert“) |

### 2.7 Tips – Tipps & Tricks

| ID | Quellpfad:Zeile | Art | Funktion | Klass. | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Quelltest |
|---|---|---|---|---|---|---|---|---|---|
| INV-A-118 | `Tips/Tips.php:35` | Daten/UI | `registerTypes()`: Post-Typ `bp_tip` (nicht öffentlich, Block-Editor, Rechte `MANAGE_CONTENT`) und hierarchische Taxonomie `bp_tip_category`; `register()` L27 | reuse | Neutrale redaktionelle Lernhinweise | Inhaltstyp „Lerntipp“ | R1-Free | R1-05, R1-16 | `exam-guide-test.php` (Tipps), `uninstall-phase.php` |
| INV-A-119 | `Tips/Tips.php:77` | Import | `maybeSeed()` mit Sperre (`Infrastructure\Lock`, 300 s, `bp_learning_tips_seed_lock`), einmalige Anlage (gelöschte Tipps kommen nicht zurück), Textstand `VERSION` (L22) und `updateDefaults()` L139 (nur unbearbeitete Tipps werden nachgezogen). Nur im Hintergrund (`maybeSeedInBackground` L62) | abstract | Mechanik neutral; die Starttexte sind LearnBoating-Inhalt | Starter-Content-Seeder (wiederverwendbar für Tipps/FAQ/Vorlagen) | R1-Free (Mechanik) | R1-15, R1-23, R2-03 | `exam-guide-test.php` (unbearbeiteter Text wird nachgezogen, bearbeiteter bleibt) |
| INV-A-120 | `Tips/Tips.php:165` | API | REST `GET bootspruefung/v1/tips` (nur angemeldet), `payload()` L175, `groups()` L186 (Kategorie, Reihenfolge, gefiltertes HTML) | reuse | Neutral; der Namespace wird ersetzt (`course-captain/v1`, Kap. 26.2) | Tipps-Endpunkt | R1-Free | R1-28, R1-16 | `exam-guide-test.php` („Tipps nur angemeldet“ 401), `mobile-api-test.php` |
| INV-A-121 | `Tips/TipsRest.php:18` | API | `TipsRest::check()` mit `TipsPostsController` und `TipsTermsController`: Core-REST `/wp/v2/bp_tip(_category)` nur für Konten mit Bearbeitungsrecht (T88) | reuse | Neutrale Härtung | REST-Guard für private Inhaltstypen | R1-Free | R1-03, R0-07 | `security-test.php` („Tipps in der Core-REST-API (T88)“) |
| INV-A-122 | `Tips/StarterTips.php:35` | Daten | `all()` (3 Kategorien, 13 Starttexte) und `previous()` L26 (frühere Fassungen) | replace | Texte beschreiben LearnBoating-Funktionen und enthalten Fachtipps (SBF See, SRC, Prüfungsausschuss) | Neue neutrale Starttexte | R2 | R2-03 | `exam-guide-test.php` („3 Kategorien, 13 Starttexte“) |

### 2.8 Faq – öffentliche FAQ und Kunden-FAQ

| ID | Quellpfad:Zeile | Art | Funktion | Klass. | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Quelltest |
|---|---|---|---|---|---|---|---|---|---|
| INV-A-123 | `Faq/Faq.php:54` | Daten/UI | `registerTypes()`: Post-Typ `bp_faq` (keine Einzelseiten/Archive) mit Taxonomie `bp_faq_category` (Rubrik → Unterkategorie), Post-Meta (Nummer, ★ Theme, Redaktionshinweis), Term-Meta (Schlüssel, Reihenfolge), Rechte `MANAGE_CONTENT`; `register()` L36 | reuse | Neutrale FAQ-Verwaltung | FAQ-Inhaltstyp | R1-Free | R1-05, R1-28 | `faq-test.php` („Admin“) |
| INV-A-124 | `Faq/Faq.php:34` | Daten | `RUBRIC_KEYS`: `allgemein` und 10 Kurs-/Produktcodes | replace | Harte Fachcodes | Rubriken frei bzw. aus Kursen abgeleitet | R1-Free | R1-04 | `faq-test.php` („11 Rubriken“) |
| INV-A-125 | `Faq/Faq.php:112` | API | REST `GET bootspruefung/v1/faqs` öffentlich (nur veröffentlichte FAQs, ohne Redaktionshinweise) mit Filtern `rubric`, `theme_only`, `subcategory` | reuse | Neutral; der Namespace wird ersetzt | FAQ-Endpunkt | R1-Free | R1-28, R1-37 | `faq-test.php` („REST GET /bootspruefung/v1/faqs“) |
| INV-A-126 | `Faq/FaqTerms.php:46` | PHP | `flushOnChange()`: Cache leeren bei Speichern, Statuswechsel, Löschen/Papierkorb, Kategorie- und Meta-Änderungen; `map()` L21 (Begriffe mit Schlüssel und Reihenfolge); Aufruf aus `Faq::registerCacheInvalidation()` L131 | reuse | Neutral | Cache-Invalidierung für Inhaltstypen | R1-Free | R1-25 | `faq-test.php` („Cache: geänderte Frage sofort sichtbar“) |
| INV-A-127 | `Faq/FaqRepository.php:27` | PHP | `query()`, `rubrics()` L52, `all()` L164 (Object-Cache `bp_faq`, Schlüssel `all-v2:<last_changed>`, 1 Tag), `placement()` L216, `terms()` L239: sortiert nach Rubrik, Unterkategorie, `menu_order` | reuse | Neutral | FAQ-Repository | R1-Free | R1-28, R1-25 | `faq-test.php` („96 veröffentlichte FAQs, sortiert“) |
| INV-A-128 | `Faq/FaqRepository.php:94` | PHP | `tokens()` (`{{agb}}` = Link auf die WooCommerce-AGB-Seite, Filter `bootspruefung_faq_tokens`), `links()` L80 (`{{link:/pfad/\|Text}}` → `home_url`), `renderAnswer()` L71, `filterAnswer()` L108 | abstract | Platzhaltermechanik neutral, `{{agb}}` ist Woo-gebunden | Platzhalter-Register (AGB-Seite als Einstellung) | R1-Free | R1-28, R0-27 | `faq-test.php` (`{{agb}}`) |
| INV-A-129 | `Faq/FaqRepository.php:122` | PHP | `cacheableAnswer()`, `answerHtml()` L130, `hasDynamicBlocks()` L136: Antworten mit dynamischen Blöcken (z. B. Zugangsblock) erst beim Ausgeben je Person rendern (T116) | reuse | Verhindert Leaks des Zugangsblocks über den Cache | Cache-sicheres Block-Rendering | R1-Free | R1-25, R1-14 | `faq-test.php`, `customer-faq-test.php` („Dynamischer Block … (T116)“) |
| INV-A-130 | `Faq/FaqShortcode.php:30` | UI | Shortcode `[bootspruefung_faq rubric theme_only subcategory ids limit title search schema]`: Rubrik-Navigation, `<details>`, kuratierter Auszug über `ids`/`limit`, Überschriftenhierarchie | abstract | Kopplung an das Theme Kurswasser (`themeActive()` L182, Klassen `kw-*`) | FAQ-Shortcode/Block, theme-neutral | R1-Free | R1-28, R1-19 | `faq-test.php` („Shortcode [bootspruefung_faq]“) |
| INV-A-131 | `Faq/FaqShortcode.php:125` | UI | `jsonLd()`: JSON-LD `FAQPage` nur mit den ausgegebenen FAQs, `schema="0"` schaltet ab | reuse | SEO neutral | Strukturierte Daten FAQ | R1-Free | R1-37, R0-33 | `faq-test.php` („JSON-LD nur mit den ausgegebenen FAQs“) |
| INV-A-132 | `Faq/FaqShortcode.php:223` | UI | Inline-Suche (`script()`, ohne JS ausgeblendet, `aria-live`-Status) und Inline-CSS `css()` L201 / `enqueueStyle()` L188 | reuse | Neutral, barrierearm | FAQ-Frontend | R1-Free | R1-19, R1-16 | `faq-test.php` |
| INV-A-133 | `Faq/FaqImporter.php:47` | Import | `maybeImport()` / `import()` L110 / `needsImport()` L41: Standard-FAQs idempotent über die Nummer (`_bp_faq_ref`) anlegen, Sperre `bp_learning_faq_import_lock` (300 s), Datenstand `bp_learning_faq_version`, neue Nummern je Stand (`addedSince` L92). Kategorien werden ergänzt, nie überschrieben (`ensureRubric` L308, `ensureSubcategory` L328) | abstract | Mechanik neutral; die Daten (DefaultFaqs) sind fachlich | Starter-Content-Seeder (vgl. INV-A-119) | R1-Free (Mechanik) | R1-15, R1-23, R2-03 | `faq-test.php` („Import“, „Idempotenz“) |
| INV-A-134 | `Faq/FaqImporter.php:170` | Import | `content()`: Antworttext → Blöcke (Absätze, Listen „- “, `**fett**`, HTML maskiert); `hash()` L192 (md5 über normalisierte Frage und Antwort); `statusFor()` L79 (Entwurf bei Platzhalter `[…]` oder Redaktionshinweis ohne ★) | reuse | Neutral | Content-Konverter und Prüfsumme | R1-Free | R1-15 | `faq-test.php` |
| INV-A-135 | `Faq/FaqImporter.php:221` | Import | `updateDefaults()`: unveränderte Standard-FAQs auf neuen Text bringen (Prüfsumme oder Historie), entfernte unveränderte in den Papierkorb, bearbeitete bleiben (`kept`) | abstract | Mechanik neutral; die Historie (`DefaultFaqHistory`) ist LearnBoating-Altbestand | Upgrade-sicheres Nachziehen von Starterinhalten | R1-Free | R1-23, R1-15 | `faq-test.php` („Unveränderte Standard-FAQs aktualisieren“, „entfernte FAQ 13“) |
| INV-A-136 | `Faq/DefaultFaqs.php:46` | Daten | `RUBRICS` (98 FAQs zu Bootsführerscheinen), `ADDED` L31, `REMOVED` L41 | exclude | Fachinhalt (Kap. 26.2) | entfällt | exclude | – | `faq-test.php` („98 FAQs wie in der Datei“) |
| INV-A-137 | `Faq/DefaultFaqHistory.php:25` | Daten | `HASHES`: Prüfsummen früherer Standardstände | exclude | Altbestand der Fach-FAQs | entfällt | exclude | – | `faq-test.php` |
| INV-A-138 | `Faq/CustomerFaq.php:77` | Daten/UI | Post-Typ `bp_customer_faq` mit Taxonomie `bp_cfaq_category` beliebiger Tiefe; nie öffentlich, auch nicht in Sitemaps (Filter `wp_sitemaps_post_types`/`_taxonomies` L56/L61); `register()` L49 | reuse | Neutrale Kursteilnehmer-FAQ | Kurs-FAQ nur für Teilnehmende | R1-Free | R1-14, R1-37 | `customer-faq-test.php` („Nie öffentlich“) |
| INV-A-139 | `Faq/CustomerFaq.php:138` | PHP | `isCustomer()`: Kunde = voller Zugang zu `question_bank` (Teilumfang zählt nicht); `deniedMessage()` L144 | abstract | Definition hängt am Feature `question_bank` | „Teilnehmer = voller Kurs-Grant“ | R1-Free | R1-14 | `customer-faq-test.php` (Gast 401, ohne Zugang 403) |
| INV-A-140 | `Faq/CustomerFaq.php:149` | API | REST `GET bootspruefung/v1/courses/(?P<code>…)/customer-faqs` (`LearnerController::wrap`, 401 `bp_login_required`, 403, 404) | reuse | Neutral; Namespace ersetzen | Kurs-FAQ-Endpunkt | R1-Free | R1-28, R0-07 | `customer-faq-test.php` („Zugriff Web“) |
| INV-A-141 | `Faq/CustomerFaq.php:47` | Daten | `SCHEIN_KEYS` (7 Scheine) | replace | Fachcodes | Kategorien je Kurs dynamisch | R1-Free | R1-04 | `customer-faq-test.php` („38 Kategorien (7 Scheine …)“) |
| INV-A-142 | `Faq/CustomerFaqRestGuard.php:19` | API | `check()` mit `CustomerFaqPostsController` / `CustomerFaqTermsController`: Core-REST nur für `MANAGE_CONTENT` | reuse | Neutrale Härtung | REST-Guard (vgl. INV-A-121) | R1-Free | R1-03, R0-07 | `customer-faq-test.php` („Nie öffentlich“) |
| INV-A-143 | `Faq/CustomerFaqRepository.php:51` | PHP | `forCourse()`, `count()` L71, `groups()` L90, `data()` L154: Baum je Kurs (Tiefe ≤ 10), leere Zweige fallen weg, eine gemeinsame FAQ erscheint je Kurs nur einmal, Cache `bp_cfaq` | reuse | Neutral | Kurs-FAQ-Repository | R1-Free | R1-28, R1-25 | `customer-faq-test.php` („Gemeinsame FAQ in mehreren Scheinen“) |
| INV-A-144 | `Faq/CustomerFaqRepository.php:41` | PHP | `keysFor()`: Kurs → Schein-Schlüssel über `SectionFiles::PARTS` | replace | Harte Kombi-Tabelle (vgl. INV-A-107) | Bundle-/Teilkurs-Relation | R1-Free | R1-04, R1-13 | `customer-faq-test.php` („Zuordnung Kurs → Scheine“) |
| INV-A-145 | `Faq/CustomerFaqImporter.php:55` | Import | `maybeImport()`, `upgrade()` L81, `import()` L142 (alle als Entwurf, Zuordnung zu mehreren Kategorien), `updateDefaults()` L228, `missingRefs()` L121 (Admin-Knopf), Bericht `bp_learning_cfaq_report` | abstract | Mechanik neutral, Daten fachlich | Starter-Content-Seeder (vgl. INV-A-133) | R1-Free (Mechanik) | R1-15, R1-23, R2-03 | `customer-faq-test.php` („Startinhalte“, „Idempotenz“, „Nachziehen“) |
| INV-A-146 | `Faq/CustomerFaqImporter.php:289` | Import | `mergeCopies()`: Migration Datenstand 1 → 2, unbearbeitete Kopien zu einer gemeinsamen FAQ zusammenführen; Status gemischt → Entwurf; Rest in den Papierkorb | replace | Migration für LearnBoating-Altdaten; in Course Captain gibt es diesen Bestand nicht. Übernehmen lässt sich nur die Regel „nur unbearbeitete Inhalte anfassen, nie endgültig löschen“ | Migrationsrichtlinie (R1-23/R1-40) | R1-Free | R1-23, R1-40 | `customer-faq-test.php` („Migration Datenstand 1 → 2“) |
| INV-A-147 | `Faq/CustomerFaqImporter.php:404` | PHP | `trash()`: in den Papierkorb, auch wenn `EMPTY_TRASH_DAYS = 0` gilt; nie endgültig löschen | reuse | Neutrale Datensicherheit | Safe-Trash-Helfer | R1-Free | R1-17 | `customer-faq-test.php` („nichts endgültig gelöscht“) |
| INV-A-148 | `Faq/DefaultCustomerFaqs.php:125` | Daten | `faqs()` (16 Start-FAQs, Belegstellen B1–B9), `scheine()` L94, `exam()` L217 (Prüfungsregeln je Schein), `total()` L237, `scheinName()` L243 | exclude | Fachinhalt (amtliche Grenzen, Funk, Navigation) | entfällt | exclude | – | `customer-faq-test.php` („16 Start-FAQs“) |

### 2.9 Tests als Golden-Master-Kandidaten

| ID | Quellpfad | Art | Funktion | Klass. | Begründung | Neutraler Zielbaustein | Phase/Edition | Task-ID(s) | Quelltest |
|---|---|---|---|---|---|---|---|---|---|
| INV-A-149 | `tests/integration/learning-review-test.php:1` | Test | Smarttrainer-Reihenfolge, FACH-02-Stufen und Intervalle, Offline-Prüfungsantworten, Abbruch, Rate-Limit, Zugangsende während der Prüfung | abstract | Die Prüfaussagen sind neutral, der Aufbau nutzt `SBF-SEE` und Paketdaten | Golden Master „Mastery & Exam Integrity“ mit neutralem Kurs | R1-Free | R0-05, R1-18 | (selbst) |
| INV-A-150 | `tests/integration/content-flow-test.php:166` | Test | Lernen, Prüfung (Blueprint, Teilgrenzen, Snapshot), Trainer, Video, Zugriffsschutz, Review-Funde | abstract | Gemischt: Prüfaussagen zu Lernen/Prüfung/Video/Dateien neutral, Navigation/Funk/Gerät/Seekarte entfallen | Golden Master „Learning & Exam Flow“ (ohne Fachblöcke) | R1-Free | R0-05, R1-18 | (selbst) |
| INV-A-151 | `tests/integration/membership-test.php:133` | Test | Teilumfang, Sammlungen, Drip, Gate, geschützte Dateien, Downloads, Kaufstatus, Video-Hosts | abstract | Prüfaussagen neutral, Kurscodes fachlich; Seekarten- und Funkteile entfallen | Golden Master „Access & Gate“ | R1-Free | R0-05, R1-14, R1-18 | (selbst) |
| INV-A-152 | `tests/integration/faq-test.php:62`, `customer-faq-test.php:102` | Test | Import, Idempotenz, Nachziehen, Cache, Shortcode, REST, Rechte, T116 | abstract | Mechanik neutral, Zählerwerte (98, 16, 38) an Fachdaten gebunden | Golden Master „Starter Content & FAQ“ mit neutralen Daten | R1-Free | R0-05, R1-18 | (selbst) |
| INV-A-153 | `tests/integration/navigation-check-test.php:59`, `exam-guide-test.php:39`, `e2e/guide-flow.mjs`, `e2e/guide-setup.php` | Test | Navigation, Prüfungsanmeldung, Prüfungsausschüsse | exclude | Fachmodule. Ausnahmen: die Prüfaussagen zum Markieren in Prüfungen (`navigation-check-test.php` Z. 230–257) und zu den Tipps (`exam-guide-test.php` Z. 44–59) wandern nach INV-A-150 bzw. INV-A-152 | entfällt (Teilübernahme wie beschrieben) | exclude | R0-05 | (selbst) |
| INV-A-154 | `tests/e2e/learning-flow.mjs`, `e2e/trainers-flow.mjs` | Test | Browserflows Lernbereich und Trainer | abstract | learning-flow ist weitgehend neutral; aus trainers-flow bleiben nur Video und Praxis/Karteikarten | E2E-Referenzflows R1-18 | R1-Free | R0-05, R1-18, R1-29 | (selbst) |

### 2.10 Verteilung der Klassifikation

| Klassifikation | Anzahl | Anteil |
|---|---|---|
| reuse | 80 | 52 % |
| abstract | 33 | 21 % |
| replace | 9 | 6 % |
| exclude_domain_specific | 32 | 21 % |
| **Summe** | **154** | 100 % |

Kein Eintrag ist `unknown`. Die Zählung ergibt sich aus der Spalte „Klass.“ (Prüfkommando in Abschnitt 7).

## 3. Daten

R = liest, W = schreibt. Tabellen mit Präfix `{$wpdb->prefix}bp_`. Spalten nur, soweit dieser Bereich sie benutzt.

### 3.1 Datenbanktabellen

| Tabelle | Spalten (genutzt) | R/W | Benutzer im Bereich | Bewertung für Course Captain |
|---|---|---|---|---|
| `bp_attempt` | `id, user_id, question_id, revision_id, context (learn/video), context_ref (Kurscode), is_correct, self_assessment (known/unknown), answer_ms (0–3 600 000), idempotency_key (eindeutig), created_at` | R/W | LearningService | reuse (Attempt-Log) |
| `bp_attempt_answer` | `attempt_id, answer_id, position` | R/W | LearningService | reuse |
| `bp_progress` | `id, user_id, question_id (eindeutig je Nutzer), attempts, correct_count, wrong_count, streak, mastery_score, learn_status (new/unsure/learning/secure/mastered), first_answered_at, last_answered_at, due_at` | R/W | LearningService | reuse |
| `bp_favorite` | `user_id, question_id, context ('question'), created_at` (eindeutig, INSERT IGNORE) | R/W | LearningService | reuse |
| `bp_favorite_collection` | `id, user_id, course_id, name, created_at` | R/W | CollectionService | reuse |
| `bp_favorite_collection_item` | `collection_id, question_id, created_at` | R/W | CollectionService, LearningService | reuse |
| `bp_exam_session` | `id, user_id, course_id, rule_id, paper_id, mode (paper/simulation), rule_snapshot (JSON), status (running/evaluated/aborted/error), start_token, started_at, deadline_at, submitted_at, score, max_score, passed, result (JSON)` | R/W | ExamService, LearningService (R) | reuse |
| `bp_exam_session_question` | `id, session_id, position, question_id, revision_id, answer_order (JSON), given_answer (JSON answer_ids), self_assessed_known, revealed_at, answered_at, flagged, is_correct` | R/W | ExamService, LearningService (R) | reuse |
| `bp_exam_paper`, `bp_exam_paper_question` | `id, paper_number` / `paper_id, question_id, position` | R | ExamService (über ContentRepository) | reuse (feste Prüfungsform) |
| `bp_question`, `bp_question_revision`, `bp_answer` | `q: id, source_id, official_number, content_role, current_revision_id, status` / `r: id, question_id, question_type, question_text, topic, smart_category, feedback_correct, feedback_incorrect` / `a: id, revision_id, answer_key, answer_text, is_correct, sort_order` | R | QuestionPresenter, CollectionService, ExamService, VideoService, ExamGuideService | abstract (`official_number` neutral benennen, siehe Teil C) |
| `bp_content_media`, `bp_media` | `object_type, object_id, caption_override, alt_override, sort_order` / `id, storage_key, caption, alt_text, rights_status, status` | R | QuestionPresenter, TrainerService | abstract (Rechtemodell Teil C) |
| `bp_module`, `bp_module_question`, `bp_course_module`, `bp_course` | `id, code` / `module_id, question_id` / `course_id, module_id, sort_order` / `id, code, name` | R | ExamService, ExamGuideService | reuse |
| `bp_access_rule` | `course_code, feature_code, module_code, access_type (free/free_limited/drip), limit_type, limit_value, valid_from, valid_until` | R | AccessService | reuse |
| `bp_entitlement` | `user_id, course_code, feature_code ('all' möglich), status (active/pending/suspended/expired/revoked), valid_from, valid_until, order_id, product_id, sku_snapshot, source, meta (JSON duration_days)` | R | AccessService | abstract (Grant-Kern R1, Bestellfelder R3; Teil B) |
| `bp_mobile_rate_limit` | `bucket_hash, hits` (über `Mobile\RateLimiter`, Buckets `answer\|<id>`, `exam-start\|<id>`) | R/W | LearningService, ExamService | reuse (in die Infrastruktur verschieben) |
| `bp_navigation_task`, `bp_navigation_task_course`, `bp_navigation_question`, `bp_navigation_progress` | `task_number, hint, scenario_text, source_text, status, rights_status, sort_order` / `task_id, course_id` / `question_number, question_text, answer_text, answer_values (JSON), status, sort_order` / `user_id, navigation_question_id, answer_text, self_assessment, done` | R/W | TrainerService, NavigationCheck | exclude |
| `bp_praxis_task`, `bp_praxis_progress` | `id, course_id, category, subcategory, exam_reference, task_type, question, answer, explanation, source_text, source_url, media_id, status, active, rights_status, sort_order` / `user_id, praxis_task_id, status (known/practice/open)` | R/W | TrainerService | abstract (Karteikarten) |
| `bp_video_module`, `bp_video_lesson`, `bp_video_progress`, `bp_video_lesson_question`, `bp_video_lesson_step` | `id, course_id, source_id, title, group_label, status, rights_status, sort_order` / `id, module_id, title, duration_seconds, is_placeholder, video_url, status, sort_order` / `user_id, lesson_id, completed, position_seconds, last_access_at` / `lesson_id, question_id, sort_order` / `lesson_id, title, sort_order` | R/W | VideoService | reuse |
| `bp_radio_text`, `bp_radio_vocabulary`, `bp_radio_scenario`, `bp_radio_progress`, `bp_device` | `source_id, catalog, exercise_type, number, direction, text_de, text_en, segments, course_codes, status, rights_status, sort_order` / `source_id, catalog, category, term_de, term_en, hint` / `source_id, catalog, number, message_kind, task_text, scenario, checkpoints (JSON), model_message` / `id, user_id, item_type (text/vocab/praxis/geraet/geraet-check), item_source_id, streak, done, data, updated_at` / `source_id ('geraetesimulation'), data, status` | R/W | RadioService, RadioPracticeService, DeviceSimService | exclude (Vokabel-Mechanik siehe INV-A-075) |
| `bp_exam_guide`, `bp_exam_committee` | `source_id, data (JSON), status, sort_order` / `id, commission, location_type, contact_person, street, postal_code, city, phone, fax, email, website, office_hours, note, course_codes, status, sort_order` | R | ExamGuideService | exclude |
| `{$wpdb->posts}`, `{$wpdb->postmeta}` | Direktabfragen für `post_type`/`post_title` (Tips L122), FAQ-Nummern (FaqImporter L296, CustomerFaqImporter L103) | R | Tips, FaqImporter, CustomerFaqImporter | reuse |

### 3.2 Options-Keys

| Option | Inhalt | R/W | Quelle |
|---|---|---|---|
| `bp_learning_charts` | Seekarten-Zustand (Anhang, Lizenz, Nachweis), `autoload=false` | R/W | `Gate/Charts.php:20` (exclude) |
| `bp_learning_tips_seeded` | Zeitpunkt der Erstanlage | R/W | `Tips/Tips.php:20` |
| `bp_learning_tips_version` | Textstand der Starttexte (`'2'`), `autoload=true` | R/W | `Tips/Tips.php:23` |
| `bp_learning_tips_seed_lock` | Sperre (über `Infrastructure\Lock`, 300 s) | R/W | `Tips/Tips.php:25` |
| `bp_learning_faq_version` | Datenstand Standard-FAQs (`'5'`) | R/W | `Faq/FaqImporter.php:35` |
| `bp_learning_faq_import_lock` | Sperre Import (300 s) | R/W | `Faq/FaqImporter.php:36` |
| `bp_learning_faq_page_id` | Seite „Häufige Fragen“ (Admin-Knopf; nur deklariert, genutzt in `FaqAdmin`, Teil D) | – | `Faq/Faq.php:31` |
| `bp_learning_cfaq_version` | Datenstand Kunden-FAQ (`'4'`) | R/W | `Faq/CustomerFaqImporter.php:37` |
| `bp_learning_cfaq_report` | Bericht des letzten Updates (Admin-Hinweis), `autoload=false` | W | `Faq/CustomerFaqImporter.php:39` |
| `bp_learning_cfaq_import_lock` | Sperre Import (300 s) | R/W | `Faq/CustomerFaqImporter.php:44` |
| `woocommerce_terms_page_id` | AGB-Seite (Rückfall, wenn `wc_terms_and_conditions_page_id()` fehlt) | R | `Faq/FaqRepository.php:97` |
| `date_format` | Datumsanzeige der Freigabe-Meldungen | R | LearningService L92, ExamService L220, VideoService L296 |
| Settings (`Settings::OPTION`, Teil C): `default_access`, `video_captions`, `exam_guide_enabled` | Zugang ohne Shop / WebVTT / Prüfungsanmeldung | R | AccessService L78/L115, VideoService L162, ExamGuideService L37 |

### 3.3 Post-/Term-Meta

| Schlüssel | Objekt | Inhalt | Quelle |
|---|---|---|---|
| `_bp_required_access` | Beitrag/Seite und Anhang | `{course, feature}` | `Gate/ContentGate.php:36`, `Gate/ProtectedFiles.php:26` |
| `_bp_access_course` | Anhang | Kurscode (abfragbar für Downloads/Bereichsdateien) | `Gate/ProtectedFiles.php:27` |
| `_bp_protected_path` | Anhang | Pfad im geschützten Ordner | `Gate/ProtectedFiles.php:28` |
| `_bp_protect_error` | Anhang | letzte Schutz-Fehlermeldung | `Gate/ProtectedFiles.php:29` |
| `_bp_file_slot` | Anhang | Funk-Anzeigeort (exclude) | `Gate/SectionFiles.php:24` |
| `_bp_chart` | Anhang | Seekarten-Code (exclude) | `Gate/ProtectedFiles.php:91`, `Gate/Charts.php` |
| `_wp_attached_file`, Anhang-Metadaten (`sizes`, `original_image`) | Anhang (Core) | Originalpfad, Ableitungen | `Gate/ProtectedFiles.php:204, 309` |
| `_bp_faq_ref`, `_bp_faq_theme`, `_bp_faq_note` | `bp_faq` | Nummer, ★, Redaktionshinweis | `Faq/Faq.php:24-26` |
| `_bp_faq_default_hash`, `_bp_faq_default_status` | `bp_faq` | Prüfsumme und Status des Standardstands | `Faq/FaqImporter.php:32-34` |
| `_bp_cfaq_ref`, `_bp_cfaq_note` | `bp_customer_faq` | Nummer, Redaktionshinweis | `Faq/CustomerFaq.php:40-41` |
| `_bp_cfaq_default_hash`, `_bp_cfaq_merged_from` | `bp_customer_faq` | Prüfsumme; Herkunft der Zusammenführung | `Faq/CustomerFaqImporter.php:41-43` |
| `_wp_trash_meta_status`, `_wp_trash_meta_time` | `bp_customer_faq` | Core-Papierkorb-Meta bei `EMPTY_TRASH_DAYS = 0` | `Faq/CustomerFaqImporter.php:410-411` |
| `bp_faq_key`, `bp_faq_order` | Term `bp_faq_category` | Rubrik-Schlüssel, Reihenfolge | `Faq/Faq.php:27-28` |
| `bp_cfaq_key`, `bp_cfaq_order` | Term `bp_cfaq_category` | Schein-Schlüssel, Reihenfolge | `Faq/CustomerFaq.php:42-43` |

**Usermeta:** Dieser Bereich liest und schreibt keine Usermeta. `accessStart()` liest nur `user_registered` aus `get_userdata()` (`AccessService.php:330`).

### 3.4 Transients und Caches

| Schlüssel | Art | Ablauf | Quelle |
|---|---|---|---|
| `bp_learning_video_hosts` | Transient | ohne Ablauf (autoload; Leerung durch `ContentCache::flush()` beim Import) | `Trainers/VideoService.php:129, 148` |
| `bp_protect_notice_<userId>` | Transient | 1 h, max. 10 Meldungen | `Gate/ProtectedFiles.php:30, 83` |
| Object-Cache-Gruppe `bp_faq`, Schlüssel `all-v2:<last_changed>`, `terms:<last_changed>` | `wp_cache_*` | 1 Tag, `wp_cache_set_last_changed` | `Faq/FaqRepository.php:65, 164, 239` |
| Object-Cache-Gruppe `bp_cfaq`, Schlüssel `data-v2:<last_changed>` | `wp_cache_*` | 1 Tag | `Faq/CustomerFaqRepository.php:84, 154` |
| `RequestMemo` `access_rules`, `entitled:<id>`, `access_start:<id\|code\|feature>` | anfragelokal | Anfrage | `Learning/Services/AccessService.php:317, 395, 459` |
| statische Merker `ContentGate::$memo`, `$offersHtml`; Instanz-Memos in LearningService, ExamService, QuestionPresenter, DeviceSimService | Prozess | Anfrage | diverse |
| Optionen-Cache (`wp_cache_delete` auf `options`/`notoptions`) | Core | – | `Tips/Tips.php:88-90` |

### 3.5 Dateisystem

`MediaStorage::protectedDir()/<attachmentId>/` und `…/derived/` für geschützte Anhänge (`Gate/ProtectedFiles.php:212-229`); Rückverschiebung in `wp_upload_dir()['basedir']`.

## 4. Schnittstellen

### 4.1 REST-Routen (in diesem Bereich registriert)

| Route | Methode | Berechtigung | Quelle | Klass. |
|---|---|---|---|---|
| `/bootspruefung/v1/tips` | GET | angemeldet | `Tips/Tips.php:165` | reuse (umbenennen) |
| `/bootspruefung/v1/faqs` (`rubric`, `theme_only`, `subcategory`) | GET | öffentlich | `Faq/Faq.php:112` | reuse (umbenennen) |
| `/bootspruefung/v1/courses/(?P<code>[A-Za-z0-9-]+)/customer-faqs` | GET | angemeldet + voller Kurszugang (401/403/404) | `Faq/CustomerFaq.php:149` | reuse (umbenennen) |
| `/wp/v2/bp_tip`, `/wp/v2/bp_tip_category` | Core | Lesen nur mit Bearbeitungsrecht | `Tips/TipsPostsController.php`, `TipsTermsController.php` | reuse |
| `/wp/v2/bp_customer_faq`, `/wp/v2/bp_cfaq_category` | Core | Lesen nur `MANAGE_CONTENT` | `Faq/CustomerFaqPostsController.php`, `CustomerFaqTermsController.php` | reuse |
| `/wp/v2/bp_faq`, `/wp/v2/bp_faq_category` | Core | `show_in_rest` mit Rechten `MANAGE_CONTENT` | `Faq/Faq.php:80, 102` | reuse |

**Von Services dieses Bereichs bediente, aber außerhalb registrierte Routen (Teil D, nur Verweis):** Web-REST `bootspruefung/v1` (z. B. `/answers/reveal`, `/exam-sessions/{id}/answers/{pos}`, `…/flag`, `…/submit`, `…/abort`, `/courses/{code}/device-sim/{evaluate,progress,exam}` laut `DeviceSimService.php:119`, `/courses/{code}/pruefung-anmelden` laut `exam-guide-test.php` Z. 67) sowie App-REST `boatingschool/v1` (`/exams/{id}/answers/{pos}`, `/exams/{id}/abort`, `/courses/{id}/faqs`, `/courses/{id}/trainer/reveal`). Vollständige Routenliste: Inventar Teil D.

**Eigener Download-Endpunkt (kein REST):** `Router::url('datei/<id>')` über die Query-Vars `bp_app` und `bp_path` in `template_redirect` (`Gate/ProtectedFiles.php:94, 344`).

### 4.2 Hooks – eigene `apply_filters` / `do_action`

| Hook | Typ | Zweck | Quelle | Ziel |
|---|---|---|---|---|
| `bootspruefung_can_access` | Filter | Zugriffsentscheidung erweitern/einschränken | `AccessService.php:80, 119` | reuse → `course_captain_*` |
| `bootspruefung_exam_late_tolerance` | Filter | Kulanz 0–600 s | `ExamService.php:58` | reuse |
| `bootspruefung_exam_starts_per_hour` | Filter | Start-Limit | `ExamService.php:196` | reuse |
| `bootspruefung_named_lock_timeout` | Filter (nur erwähnt; ausgewertet in `NamedLock`, Teil C) | Wartezeit Startsperre | `ExamService.php:42` | reuse |
| `bootspruefung_video_first_party_hosts` | Filter | eigene Video-Hosts | `VideoService.php:117` | reuse |
| `bootspruefung_video_captions_url` | Filter | Untertitel-URL | `VideoService.php:168` | reuse |
| `bootspruefung_protected_file_sendfile` | Filter | X-Sendfile/X-Accel übernehmen | `ProtectedFiles.php:392` | reuse |
| `bootspruefung_protected_file_allowed` | Filter | Freigabe einer geschützten Datei überstimmen | `DownloadService.php:55` (Abnehmer `Charts::allowFile`) | reuse |
| `bootspruefung_faq_tokens` | Filter | Platzhalter in FAQ-Antworten | `FaqRepository.php:104` | abstract |
| `bootspruefung_sim_offers` | Filter | Simulator-Angebote | `DeviceSimService.php:166, 184` | exclude |
| `do_action` | – | In diesem Bereich wird **kein** eigener `do_action` ausgelöst | – | – |

**Abonnierte fremde/eigene Aktionen (`add_action`/`add_filter`):**
- ContentGate (`ContentGate.php:51-76`): `set_current_user`, `bootspruefung_entitlements_changed`, `bootspruefung_voucher_granted`, `bootspruefung_voucher_revoked` (Auslöser in Teil B), `save_post_product`, `added_post_meta`, `updated_post_meta`, `deleted_post_meta`, `add_meta_boxes`, `save_post`, `the_content` (1 und `PHP_INT_MAX`), `get_the_excerpt` (1 und `PHP_INT_MAX`), `the_content_feed`, `elementor/frontend/builder_content_data`, `fl_builder_do_render_content`, `init` (Block), `post_class`, `enqueue_block_editor_assets`.
- ProtectedFiles (`ProtectedFiles.php:34-41`): `attachment_fields_to_edit`, `attachment_fields_to_save`, `wp_get_attachment_url`, `get_attached_file`, `template_redirect` (0), `delete_attachment`, `rest_prepare_attachment`, `admin_notices`.
- Charts (`Charts.php:31`): `bootspruefung_protected_file_allowed`.
- Tips (`Tips.php:27-31`): `init` (Typen), `init` (20, Seed im Hintergrund), `rest_api_init`.
- Faq (`Faq.php:36-44`): `init`, `init` (20), `rest_api_init`; FaqTerms (`FaqTerms.php:46-88`): `save_post_<type>`, `transition_post_status`, `before_delete_post`, `deleted_post`, `trashed_post`, `untrashed_post`, `set_object_terms`, `created_/edited_/delete_<tax>`, `added/updated/deleted_post_meta`, `added/updated/deleted_term_meta`.
- CustomerFaq (`CustomerFaq.php:49-67`): `init`, `init` (20), `rest_api_init`, `wp_sitemaps_post_types`, `wp_sitemaps_taxonomies`, dazu FaqTerms-Invalidierung.
- Registrierung der Gate-, Tips- und FAQ-Klassen in `src/Plugin.php:270-275` (Teil C).

**Externe Filter, die dieser Bereich voraussetzt (nicht registriert):** `kurswasser_consent_external_hosts` (Theme, Kommentar `VideoService.php:124`); Theme-Funktion `kurswasser_course_faq` (`FaqShortcode.php:184`).

### 4.3 Shortcodes, Blocks, Post-Typen

| Element | Name | Quelle | Klass. |
|---|---|---|---|
| Shortcode | `[bootspruefung_zugang kurs="" funktion="any" hinweis=""]` | `ContentGate.php:74, 199` | reuse (neu benennen) |
| Shortcode | `[bootspruefung_faq rubric theme_only subcategory ids limit title search schema]` | `Faq.php:42`, `FaqShortcode.php:30` | abstract |
| Block | `bootspruefung/zugang` (Attribute `course`, `feature`, `message`; Skript `bp-restricted-block`) | `ContentGate.php:215-251` | reuse (neu benennen) |
| Metabox | `bp-required-access` „Lernzugang erforderlich“ | `ContentGate.php:289` | reuse |
| Mediathek-Felder | `bp_required_access`, `bp_file_slot` | `ProtectedFiles.php:141-144` | reuse / exclude (Slot) |
| Post-Typen / Taxonomien | `bp_tip` / `bp_tip_category`; `bp_faq` / `bp_faq_category`; `bp_customer_faq` / `bp_cfaq_category` | `Tips.php:37`, `Faq.php:60`, `CustomerFaq.php:83` | reuse (Präfix `ccap_`) |
| CSS-Klassen/Markup | `bp-gate-notice`, `bp-requires-access`, `bp-access-locked`, `bp-faq*`, `data-bp-faq*`; mit Theme `kw-section kw-faq` | ContentGate, FaqShortcode | reuse / abstract (Theme) |

### 4.4 Cron, Hintergrundjobs, CLI

| Job | Auslöser | Aufgabe | Quelle |
|---|---|---|---|
| `bp_learning_hourly` → `Cron::run('exam_expire', …)` | WP-Cron stündlich (`Plugin::hourly`, Teil C) | `ExamService::expireRunning()`; Fehler je Sitzung über `markBroken()` → `Cron::recordError('exam_expire', …)` | `ExamService.php:576, 601`, `Plugin.php:310-314` |
| Seed im Hintergrund | `init` (20) nur bei `Plugin::isBackgroundRequest()` (Admin, Cron, WP-CLI; T84) | `Tips::maybeSeed()`, `FaqImporter::maybeImport()`, `CustomerFaqImporter::maybeImport()` | `Tips.php:62`, `Faq.php:47`, `CustomerFaq.php:70` |
| CLI | In diesem Bereich keine WP-CLI-Befehle registriert. Die Seeds laufen bei CLI-Anfragen über den Hintergrundpfad mit | – | – |

## 5. Zustände und Locks

### 5.1 Zustandsautomat Lernstand je Frage (`bp_progress.learn_status`, `LearningService::applyProgress`)

```
new ──falsch──▶ unsure ◀──────────── falsch (aus jedem Zustand; streak=0, due_at=NULL)
 │                 │
 └──richtig──▶ learning (streak 1–2, nur gezählt wenn spaced(): ≥300 s oder andere Fragen dazwischen)
                   │ streak = 3
                   ▼
                secure („sicher heute“, due_at = jetzt + 20 h)
                   │ richtig UND fällig
                   ▼
                mastered (due_at = jetzt + 7 d / 21 d / 60 d je weiterer fälliger richtiger Antwort)
                   │ richtig, nicht fällig → unverändert (streak_counted = false)
```
„Zuletzt falsch“ = `attempts > 0` und `streak = 0` (`LearningService.php:758`). Ein Altbestand `mastered` ohne `due_at` gilt 7 Tage nach der letzten Antwort als fällig (L553-565).

### 5.2 Zustandsautomat Prüfungssitzung (`bp_exam_session.status`, ExamService)

| Von | Ereignis | Nach | Bedingungen / Quelle |
|---|---|---|---|
| – | `start()` | `running` | Zugriff, Teilumfang, Regelwerk vorhanden, Drip frei, Startsperre, Start-Limit; bei vorhandener laufender Sitzung derselben Form: Rückgabe dieser ID (L188-194) |
| `running` | `saveAnswer` / `setFlag` / `reveal` | `running` | Status `FOR UPDATE` = running (L457, L491); Frist + 5 s (Gerätezeit bis Kulanz) |
| `running` | `submit()` | `evaluated` | `evaluate()` mit Zeilensperre (L616); `expired`-Kennung ab Fristende |
| `running` | Frist + Kulanz abgelaufen (Cron, `show`, `abort`, `runningSession`) | `evaluated` (`expired = true`) | `expired()` L1021 |
| `running` | `abort()` | `aborted` | nur wenn nicht abgelaufen; `submitted_at` gesetzt, keine Wertung (L545-551) |
| `running` | Auswertung wirft Fehler im Cron | `error` | `markBroken()` L601, `result.error` |
| `evaluated`/`aborted`/`error` | `submit()`/`abort()` | unverändert | idempotent (L521, L537) |

Snapshot-Felder (`rule_snapshot`): `rule_id, rule_source_id, version, live, reduced, time_limit_seconds, passing, question_parts, course_code, blueprints, paper_number` (`ExamService.php:263-276`).

### 5.3 Weitere Zustände

| Objekt | Zustände | Quelle |
|---|---|---|
| Grant-Kaufstatus (Anzeige) | `active` > `pending` > `suspended` > `expired`; `revoked` ergibt keinen Status | `AccessService.php:200-240` |
| Modul/Bogen/Lektion | frei / gesperrt `trial` (Teilumfang) / gesperrt `drip` mit `unlock_at` | `ExamService.php:122-125`, `LearningService.php:379`, `VideoService.php:71-73` |
| Karteikarte (Praxis) | `open` / `practice` / `known` | `TrainerService.php:275` |
| Vokabel (exclude, Muster) | Serie 0…n, `done` ab Serie 3 (bleibt gelernt) | `RadioService.php:278-279` |
| Navigation (exclude) | Selbsteinschätzung `correct`/`partly`/`wrong`, `done` | `TrainerService.php:193` |
| Gerät (exclude) | Stufe 1–3, Beleg 6 h, verbraucht | `DeviceSimService.php:208-233` |
| Geschützte Datei | ungeschützt → geschützt (nur nach erfolgreichem Verschieben) → ungeschützt; Fehler-Meta; Seekarte nie ungeschützt | `ProtectedFiles.php:191-279` |
| Standard-FAQ beim Import | `publish` oder `draft` (Platzhalter, Redaktionshinweis ohne ★); beim Update: `updated`/`kept`/`current`/`removed` (Papierkorb) | `FaqImporter.php:79, 221` |
| Kunden-FAQ beim Import | immer `draft`; Zusammenführung: gemischter Status → `draft` | `CustomerFaqImporter.php:196, 322` |

### 5.4 Locks und Transaktionen

| Mechanismus | Name / Ort | Wartezeit / TTL | Quelle |
|---|---|---|---|
| `NamedLock` (DB `GET_LOCK`) | `exam_start:<userId>` | 10 s; nicht verfügbar → ohne Sperre weiter | `ExamService.php:43, 183-186, 319` |
| `Infrastructure\Lock` (Option, INSERT IGNORE) | `bp_learning_tips_seed_lock`, `bp_learning_faq_import_lock`, `bp_learning_cfaq_import_lock` | 300 s | `Tips.php:83`, `FaqImporter.php:365`, `CustomerFaqImporter.php:505` |
| Zeilensperre `SELECT … FOR UPDATE` | `bp_progress` (Antwort), `bp_exam_session` (save/flag/abort/evaluate), `bp_exam_session_question` (save), `bp_radio_progress` (Vokabel) | Transaktion | `LearningService.php:498`, `ExamService.php:457, 462, 491, 547, 616`, `RadioService.php:262` |
| Eindeutiger Schlüssel als Idempotenz | `bp_attempt.idempotency_key` (aus dem Token-Hash abgeleitet) | – | `LearningService.php:214-268` |
| `START TRANSACTION … COMMIT/ROLLBACK` | Antwort, Sammlungszuordnung, Prüfungsstart, Abbruch, Auswertung, Vokabel | – | LearningService L243, CollectionService L106, ExamService L277/L456/L490/L545/L614, RadioService L254 |

### 5.5 Rate-Limits und Zeitgrenzen

| Limit | Wert | Quelle |
|---|---|---|
| Antworten Lernmodus/Videoquiz | 60 je Nutzer und Minute (Bucket `answer\|<id>`) | `LearningService.php:40, 812` |
| Prüfungsstarts | 40 je Nutzer und Stunde (Filter), Fortsetzen zählt nicht | `ExamService.php:47, 196` |
| Frage-Token | 2 h | `QuestionPresenter.php:30` |
| Prüfung: Grace / Kulanz | 5 s / 120 s (0–600) | `ExamService.php:41, 45` |
| Prüfungssperre im Lernmodus ohne Zeitlimit | 24 h | `LearningService.php:52` |
| Smarttrainer: Abstand / Konsolidierung | 300 s / 20 h; Intervalle 7, 21, 60 Tage | `LearningService.php:42-50` |
| Sammlungen | 30 je Kurs, Name 60 Zeichen | `CollectionService.php:19-20` |
| Eingabelängen | Antworttext Navigation 5 000; Funk 5 000/2 000; NavigationCheck 2 000 | `TrainerService.php:199`, `RadioService.php:185`, `DeviceSimService.php:197`, `NavigationCheck.php:251` |

### 5.6 Fehlercodes und Ausnahmen

| Code / Klasse | Bedeutung | Werfer | Web-Abbildung (Teil D) |
|---|---|---|---|
| `DomainException::ANSWER_RATE_LIMITED` (`answer_rate_limited`) | zu viele Antworten | LearningService L817 | 422 `bp_invalid` + `reason` |
| `QUESTION_TOKEN_EXPIRED` | Token älter als 2 h | QuestionPresenter L166 | 422 |
| `EXAM_NOT_STARTABLE` | kein Regelwerk / Bogen unvollständig / keine Fragen | ExamService L177, L214, L256 | 422 |
| `EXAM_START_BUSY` | Startsperre belegt | ExamService L185 | 422 |
| `EXAM_START_RATE_LIMITED` | Start-Limit | ExamService L197 | 429 `bp_rate_limited` + `retry_after` (`LearnerController.php:483-485`) |
| `EXAM_EXPIRED` | Frist abgelaufen | ExamService L427, L1030, L1065 | 422 (App: 410, `mobile-api-test.php` Z. 639) |
| `EXAM_FINISHED` | Sitzung nicht mehr laufend | ExamService L460, L494, L1048 | 422 |
| `EXAM_ANSWER_LOCKED` | offene Frage ohne Aufdecken | ExamService L435 | 422 |
| `AccessDenied` | kein (mehr) Zugriff, gesperrtes Modul/Bogen/Lektion/Karte | diverse | 403 |
| `NotFound` | Frage, Bogen, Sitzung, Lektion, Aufgabe unbekannt oder fremd | diverse | 404 |
| `RuntimeException` (Text) | Validierung (z. B. „Bitte genau eine Antwort auswählen.“, „Ungültiger Idempotency-Key.“, „Nur für offene Fragen.“, Sammlungsfehler) | diverse | 422 `bp_invalid` |
| `InvalidArgumentException` | Seekarte unbekannt/nicht schützbar, Lizenznachweis fehlt | Charts L152, L159, L173 | Admin |
| `WP_Error rest_forbidden` | Core-REST für Tipps/Kunden-FAQ | TipsRest L25, CustomerFaqRestGuard L25 | 401/403 |
| `WP_Error bp_login_required` | Kunden-FAQ ohne Anmeldung | CustomerFaq L164 | 401 |
| HTTP direkt | Download: 404 / Login-Weiterleitung / 403 mit Hinweis | ProtectedFiles L353-365 | – |

## 6. Entscheidungsbedarf

Nur Fälle, bei denen die Klassifikation von einer Produktentscheidung des Auftraggebers abhängt. Alles andere folgt aus Kap. 26.

| Nr. | Frage | Betroffene IDs | Optionen | Empfehlung |
|---|---|---|---|---|
| **E-A-01** | **Erklärungen als eigenes Feature?** LearnBoating schaltet Erklärungen zu Antworten (`learning_explanations`) getrennt frei und zeigt sonst `explanation_locked`. Soll Course Captain Feedback-Texte immer mit dem Kurs-Grant zeigen oder als eigenes, verkaufbares Feature führen? | INV-A-016, INV-A-054, INV-A-029 | (a) immer mit Kurs-Grant; (b) Kursregel „Feedback sichtbar: immer / nach Abgabe / nie“; (c) eigenes Feature wie LearnBoating | **(b)** als Kurs-/Prüfungseinstellung in R1-Free (R0-28). Ein separat verkaufbares Feature nur über Bundles in R3, nicht im Kern |
| **E-A-02** | **Ein oder zwei Karteikarten-Modelle?** Die Praxiskarten (Status `open`/`practice`/`known`, Selbsteinschätzung) und die Funk-Vokabeln („gelernt nach 3 × gewusst in Folge“, Doppelklick-Schutz) sind technisch generische Karteikarten. Konzept 26.1 verlangt Karteikarten. Welche Lernlogik gilt in Course Captain? | INV-A-061, INV-A-062, INV-A-075 | (a) nur Selbsteinschätzung; (b) nur Serie bis gelernt; (c) ein Karteikarten-Typ mit wählbarem Modus; (d) Karteikarten über die Mastery-Engine des Smarttrainers (INV-A-008) | **(c)** als ein Typ in R1-Free mit zwei belegten Modi. Die Smarttrainer-Kopplung (d) erst in R2, nach Golden Master |
| **E-A-03** | **Neutraler Fragetyp „Zahl mit Toleranz“ bzw. Fallstudie?** Der Navigationstrainer entfällt. Er enthält aber zwei neutrale Muster: Toleranzprüfung (`NavigationCheck::deviation`) und „Szenario mit Teilaufgaben + Selbsteinschätzung + Punktestand“. Kap. 26.1 erlaubt Ersatz nur bei eigenständigem Nutzen | INV-A-058 bis INV-A-060, INV-A-086, INV-A-087 | (a) nicht übernehmen; (b) neuer neutraler Typ „Zahl mit Toleranz“ (ohne nautischen Parser); (c) Fallstudien-Activity | **(a) für R1.** (b) als Kandidat für R2/Pro (R1-06 „weitere belegte Typen“), ohne Code aus `NavigationCheck`. (c) nur im Rahmen von R2-02 |
| **E-A-04** | **„Offizielle“ Prüfung gegenüber Übungsmodus?** LearnBoating kennzeichnet Prüfungen mit verifiziertem amtlichem Regelwerk als `live` und andere als Übung; ein zu kleiner Fragenpool ergibt `reduced`. Course Captain hat keine amtlichen Profile. Soll es einen Freigabestatus von Prüfungsregeln geben? | INV-A-043, INV-A-041 | (a) Kennzeichen entfällt (jede Prüfung gleich); (b) Status „freigegeben/Entwurf“ der Prüfungsregel mit Übungsmodus für Entwürfe; (c) nur `reduced` behalten | **(b) + `reduced` behalten**, angebunden an den Publish-Validator (R1-26). Der Text darf keine amtliche Gültigkeit versprechen |
| **E-A-05** | **Tipps und Kunden-FAQ im Free-Kern?** Beide sind fachneutrale Mechaniken (Inhaltstyp, Seeder, Endpunkt, Zugriffsschutz), gehören aber nicht zum Lernkern im engeren Sinn | INV-A-118 bis INV-A-121, INV-A-123 bis INV-A-147 | (a) R1-Free; (b) R2; (c) Pro | **(a)** für FAQ/Kurs-FAQ (Paritätsbestandteil, geringe Komplexität, SEO-relevant R1-37). Tipps ebenfalls R1-Free, aber ohne Starttexte; neutrale Starttexte in R2-03 |
| **E-A-06** | **Geld-zurück-Nutzungsereignis?** Ein PDF-Download beendet in LearnBoating die Geld-zurück-Garantie. Soll der Kern ein generisches Nutzungsereignis („Inhalt genutzt“) für Commerce-Adapter bereitstellen? | INV-A-104 | (a) nein; (b) neutrales Ereignis im Kern, Auswertung im R3-Adapter | **(b)**: Das Ereignis kostet im Kern wenig und gehört in den Ereignisvertrag R0-40/R3-19. Die Garantie-Logik bleibt außerhalb des Kerns (Teil B) |

## 7. Nachweis

- Quelle: `…/scratchpad/lb` = Clone von `hastla007/learnboating` @ `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd`, nicht verändert.
- Zeilenzahlen: `wc -l bootspruefung-learning/src/{Learning,Exams,Trainers,Gate,ExamGuide,Tips,Faq}/**/*.php` (Summe 10 033).
- Testzuordnung: `grep -rl` der Klassennamen in `bootspruefung-learning/tests/`.
- Klassifikationszählung: `grep '^| INV-A-' A-lernen-pruefung.md | awk -F' \\| ' '{print $5}' | sort | uniq -c` (Spalte 5 = „Klass.“; maskierte `\|` in Zellen trennen nicht).
- Keine Laufzeitausführung der Quelltests, keine Codeänderung, kein Commit.
