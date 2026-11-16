# R0-08 · Kurs-, Activity- und Prüfungsverträge (Entwurf)

**Status:** **Entwurf – Freigabe Auftraggeber/Architektur offen.** Task R0-08 ist **nicht** abgehakt. Es gibt keine Implementierung; die PHP-Signaturen in §2 sind Spezifikation, kein Code im Repository.
**Stand:** 16.11.2026
**ADR-Bezug:** ADR-004 (Activity-Provider), ADR-005 (Smarttrainer-Portierung), Prüfungs- und Bewertungsanteile von ADR-002/ADR-003. Baut auf `docs/r0/R0-06-datenmodell-adr.md` auf und verwendet dessen Tabellen- und Spaltennamen.
**Bezug:** TODO R0-08, R1-04 … R1-12, R1-20; Architekturaufgaben „Unveränderliche Prüfungs-Snapshots“, „Zustandsmaschinen“, „Idempotenz- und Wiederanlauftests“, „Golden-Master-Tests für Smarttrainer“, „Drei getrennte Fortschrittsdimensionen“, „Abschluss- und Bewertungsregeln je Activity“, „Kompetenzzuordnung“; Konzept Kap. 3, 4, 26.4, 27.1/27.2, 28.2–28.4, 35.4; Inventur `docs/r0/R0-02-inventar/A-lernen-pruefung.md` (INV-A-001 … 057, §5); Inventur C (INV-C-039, INV-C-132), Inventur E (INV-E-006, INV-E-008).
**Quelle:** LearnBoating `hastla007/learnboating` @ `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (nur gelesen, nicht verändert). Code-Pfade sind relativ zu `bootspruefung-learning/`, Schema-Pfade relativ zur Repo-Wurzel. Abkürzungen: **LS** = `src/Learning/Services/LearningService.php`, **QP** = `src/Learning/Services/QuestionPresenter.php`, **AS** = `src/Learning/Services/AccessService.php`, **ES** = `src/Exams/Services/ExamService.php`, **CR** = `src/Content/Repositories/ContentRepository.php`, **VS** = `src/Trainers/VideoService.php`, **TS** = `src/Trainers/TrainerService.php`.

**Normative Sprache:** **MUSS** = Vertragsbestandteil, Abweichung nur per ADR-Änderung. **SOLL** = Standard, begründete Abweichung erlaubt. **KANN** = optional.

---

## 0. Annahmen (Empfehlungen aus `ENTSCHEIDUNGEN.md`, noch nicht beschlossen)

Dieses Dokument behandelt die folgenden Empfehlungen als **Annahme**. Wird eine anders beschlossen, sind die genannten Abschnitte anzupassen.

| ID | Annahme | Wirkt auf |
|---|---|---|
| E-14 | Einfacher Drip („Modul X Tage nach Zugangsbeginn“) in Free; Kampagnen und Gruppen-Drip in Pro | §1.4 |
| E-20 | Erklärungen sind kein Verkaufsfeature, sondern Einstellung „Feedback sichtbar: immer / nach Abgabe / nie“ | §4.5 |
| E-21 | Ein Karteikartentyp mit zwei Modi (Selbsteinschätzung; „3 × gewusst in Folge“), R1 Free | §2.4, §5.2 |
| E-22 | Kein Fragetyp „Zahl mit Toleranz“/Fallstudie in R1 | §3.1 |
| E-23 | Kein Modus „offiziell/verifiziert“; Prüfungsregel hat Status `draft/published/retired`, Kennzeichnung `reduced` bleibt | §4.1, §6.3 |
| E-50 | Eine Kursrevision fixiert Struktur und Regeln, **nicht** die Fragenrevisionen; diese werden beim Versuchsstart fixiert | §1.2, §7 |

Weitere Abhängigkeiten, ebenfalls als Annahme: E-15 (Teilumfang in R1 Free), E-27 (Zugang je Kurs mit Scopes `view`, `download`, `exam`), E-47 (Hybrid-Speicherung), E-06 (einheitlicher Fehlercode-Katalog, Format in R0-19).

---

## 1. Kursstruktur-Vertrag

### 1.1 Hierarchie

```
Kurs (cc_course, CPT)
 └─ Modul (cc_module, CPT)          n:m über cc_course_module (sort_order, required, rules)
     └─ Lektion (cc_lesson, CPT)    1:n über post_parent + menu_order
         └─ Activity                in R1 genau eine je Lektion (→ D-01), Typ in Meta _cc_activity_type
             └─ Fragen              cc_activity_question (Inline-Quiz) bzw. Fragenpool des Moduls (cc_module_question)
Prüfung (cc_exam_rule + Blueprint/Variante) ist eine Activity vom Typ exam, die auf eine Prüfungsregel des Kurses verweist.
```

| Ebene | Pflichtattribute (Vertrag) | LB-Beleg | Regeln |
|---|---|---|---|
| Kurs | `uid`, `title`, `language`, `status` (`draft/published/archived`), `current_revision_id` | `schema/v1/courses.schema.json:9` (`code`, `language`, `status`, `sort_order`, `modules[]`) | Kurs ohne Quiz, ohne Video und ohne WooCommerce ist gültig (Konzept 3.1 „Datenregeln“). `course_type` aus LB entfällt (R0-06 §4.1). |
| Modul | `uid`, `title`, Zuordnung je Kurs mit `sort_order`, `required` | `courses.schema.json:23-27` (`module_code`, `sort_order`, `required`), `modules.schema.json:9` | Ein Modul kann in mehreren Kursen stehen (LB n:m). Modul trägt Lektionen **und** Fragenpool (R0-06 E-03). |
| Lektion | `uid`, `title`, `module` (Parent), `menu_order`, `activity_type` | `video_modules.schema.json` (`lessons[]` mit `sort_order`, `steps`, `questions`) | Genau ein Modul je Lektion (LB `video_lesson.module_id`). |
| Activity | `activity_uid`, `type`, `contract_version`, `settings` (typspezifisch, schema-validiert), `required` (Standard `true`), `completion_rule` | – (LB hat nur fest verdrahtete Bereiche, AS:24-48) | §2 |
| Frage | `uid` + Revision | `schema/v1/questions.schema.json` | §3 |

### 1.2 Stabile IDs

- **Extern zugesagt wird nur die UUID** (`uid`, UUID v4). Interne `id`/Post-IDs, Slugs und LB-Codes sind nie Vertragsbestandteil von REST, Export oder Events (R0-06 §4.0 „UID“).
- Für Tabellenobjekte (Frage, Prüfungsregel, Variante, Versuch, Zertifikat …) liegt `uid` als Spalte vor (R0-06). Für **CPT-Objekte** (Kurs, Modul, Lektion) fehlt in R0-06 eine DB-erzwungene UUID. Vorschlag: Spalte `uid char(36) NOT NULL` mit `UNIQUE uid` in `cc_source_map`, und jedes CPT-Objekt bekommt dort eine Zeile (auch lokal angelegte, mit `source_system = 'local'`) → **Delta Δ-1 zu R0-06** (§8.2).
- `activity_uid` ist **von der Lektion getrennt**, auch wenn in R1 eine Lektion genau eine Activity hat. So bleibt eine spätere 1:n-Aufteilung ohne Migration des Fortschritts möglich (`cc_activity_progress.activity_id` zeigt in R1 auf die Lektion; die UUID ist die stabile Referenz in Export, Events und Snapshots).
- **Duplizieren** eines Kurses/Moduls/einer Lektion erzeugt neue UUIDs; Herkunft wird als `copied_from_uid` in `cc_source_map` vermerkt (LB `copied_from` in `praxis_tasks.schema.json`, `exam_papers.schema.json`).
- **Import** ordnet über `(source_system, source_id)` zu (R0-06 E-06); ein Reimport ändert die UUID nie.
- Gelöschte Objekte geben ihre UUID nie frei (Archiv statt Hard-Delete, R0-06 §4.9).

### 1.3 Reihenfolge

- **MUSS:** Reihenfolge ist explizit gespeichert: Kurs→Modul über `cc_course_module.sort_order`, Modul→Lektion über `menu_order`, Fragen im Pool über `cc_module_question.sort_order` (NULL zuletzt, dann ID; Quelle CR:131 `ORDER BY FIELD(mq.module_id, …), COALESCE(mq.sort_order, 2147483647), q.id`). Gleichstand wird immer über die interne ID aufgelöst, damit Auswahl und Anzeige deterministisch sind.
- **SOLL:** Autoren-UI vergibt Lückenwerte (10, 20, 30 …); Umsortieren ändert nur betroffene Zeilen.
- **MUSS:** Die für Lernende gültige Reihenfolge ist die der **veröffentlichten Kursrevision** (`cc_course_revision.structure`). Umsortieren im Entwurf wirkt erst nach erneutem Veröffentlichen. Was mit Lernenden in einer älteren Revision passiert, regelt R0-23.
- „Nächste Lektion“ (Fortsetzen) = erste nicht abgeschlossene, nicht gesperrte Pflicht-Activity in Revisionsreihenfolge (LB VS:87 sucht die erste nicht abgeschlossene, nicht gesperrte Lektion; der dortige Ausschluss eines Fachmoduls per Titel entfällt).

### 1.4 Freischaltung

Die Freischaltung ist **eine** serverseitige Policy-Entscheidung je (Lernender, Kurs, Objekt, Zeitpunkt). Sie wird bei **jeder** lesenden und schreibenden Aktion neu ausgewertet, auch mitten in einem Vorgang (LB: Token-Kontext prüft den Zugriff erneut, LS:177; laufende Prüfung prüft bei jeder Aktion Zugriff und Teilumfang, ES:1044-1069).

**Auswertungsreihenfolge (erste zutreffende Sperre gewinnt):**

| # | Prüfung | Quelle des Rechts | Sperrgrund | LB-Beleg |
|---|---|---|---|---|
| 1 | Zugang zum Kurs mit passendem Scope (`view`/`download`/`exam`, E-27) | aktiver Grant, Free-Regel `free` mit Gültigkeitsfenster, Einstellung „Zugang für angemeldete Nutzer“ | `no_access` | AS:50-133, :335-357 |
| 2 | Teilumfang (`free_limited`): nur die ersten *n* Elemente je Abschnitt | `cc_access_rule` (`access_type = free_limited`, `limit_type`, `limit_value`) | `trial` | AS:37-46 (Limit-Typen), LS:405-428 (erste *n* Fragen je Modul), ES:166-171 (erste *n* Bögen, keine Zufallsprüfung) |
| 3 | Drip: Modul wird *d* Tage nach Zugangsbeginn frei (E-14) | `cc_access_rule` (`access_type = drip`, `module_id`, `limit_value` = Tage; bei mehreren Regeln gilt das Maximum) | `drip` + `unlock_at` | AS:263-283 (`MAX(limit_value)`), :315-333 (Zugangsbeginn = frühester `valid_from` eines aktiven Grants, sonst Registrierung) |
| 4 | Voraussetzungen | `cc_course_module.rules` bzw. Activity-`settings.prerequisites` | `prerequisite` + `requires[]` | **neu** (LB kennt nur `required` je Modul, `courses.schema.json:27`) |
| 5 | Laufende Prüfung sperrt ihre Fragen im Lernmodus | `cc_exam_session` im Zustand `in_progress` | `exam_running` | LS:97-99, :208-210, :791-810 |

**Ergebnis-Vertrag** (für REST, Blocks, Provider):

```
GateDecision {
  state: "unlocked" | "locked",
  reason: null | "no_access" | "trial" | "drip" | "prerequisite" | "exam_running",
  unlock_at: ?UTC-datetime,          // nur bei drip
  requires: list<activity_uid|module_uid>,   // nur bei prerequisite
  rule_refs: list<string>             // welche Regel(n) entschieden haben (Erklärbarkeit)
}
```

**Regeln:**

- **Drip in Free (E-14):** nur relativ zum Zugangsbeginn in ganzen Tagen, je Modul. Absolute Termine, Kampagnen und Gruppen-Drip sind Pro. Drip gilt auch für **Prüfungen**: Eine feste Variante ist gesperrt, solange eines ihrer Module gesperrt ist (ES:213-216, `paperUnlockTimes` ES:784), eine Zufallsprüfung, solange ein Blueprint-Modul gesperrt ist (ES:228-236).
- **Zugangsbeginn** ist pro Kurs und Scope zu berechnen und im Request zu memoisieren (LB `RequestMemo`, AS:309-312). Er ändert sich, wenn ein früherer Grant aktiv wird; das ist gewollt (LB-Parität) und im Test zu fixieren.
- **Voraussetzungen (neu):** Typen `completed(activity_uid)`, `completed(module_uid)`, `passed(exam activity_uid)`. Zyklen werden beim Veröffentlichen abgewiesen (Publish-Validator R1-26). Eine Voraussetzung auf eine nicht mehr existierende Activity der Revision ist ein Validierungsfehler, kein stilles „frei“.
- **Teilumfang** zählt in der Reihenfolge der Revision (§1.3). Der Limit-Typ wird vom Provider deklariert (§2.2 `ActivityCapabilities::previewLimitType`), statt wie in LB fest je Feature (AS:37-46).
- Gesperrte Inhalte werden **nicht** ausgeliefert (auch nicht als Lösung, Medien-URL oder Fragenliste). Sichtbar ist nur der Titel mit Sperrgrund.

---

## 2. Activity-Provider-Vertrag (ADR-004)

### 2.1 Grundsätze

- Der Core kennt **keine** fest verdrahteten Bereiche. Alle Lerninhalte laufen über registrierte Provider (ersetzt den Feature-Katalog AS:24-48, INV-A-029 `replace`).
- Provider sind PHP-Klassen aus Core oder Erweiterungs-Plugins. **Kein** ausführbarer Code aus Content-Paketen (Konzept 3.2 Nr. 7, 3.3).
- Provider greifen nie direkt auf Tabellen anderer Provider zu. Zugriffsentscheidungen gehen ausschließlich über den Policy-Service (§1.4, R0-07).
- Bewertung ist **serverseitig, deterministisch und testbar** (Konzept 3.3). Zeit und Zufall kommen nur über injizierte `Clock` und `Randomizer` (§3.4).

### 2.2 Interfaces (Spezifikation)

```php
<?php
declare(strict_types=1);

namespace CourseCaptain\Activity\Contract;

/** Pflicht für jeden Provider. */
interface ActivityProvider
{
    /** Stabiler Typschlüssel, z. B. "lesson", "video", "file", "quiz", "exam", "flashcard". [a-z0-9_]{1,32} */
    public function type(): string;

    /** SemVer des Provider-Vertrags. Major = inkompatible Settings/Bewertung. */
    public function contractVersion(): string;

    public function capabilities(): ActivityCapabilities;

    /** JSON-Schema (draft 2020-12) der Activity-Settings dieser Vertragsversion. */
    public function settingsSchema(): array;

    /** Fachliche Prüfung über das Schema hinaus (z. B. Fragenpool groß genug). Läuft im Publish-Validator. */
    public function validate(ActivityDefinition $definition, ValidationContext $context): ValidationResult;

    /** Teil der Definition, der beim Veröffentlichen in cc_course_revision.structure eingefroren wird. */
    public function freeze(ActivityDefinition $definition): FrozenActivity;

    /** Hebt Settings einer älteren settings_version auf die aktuelle an (rein, ohne Seiteneffekte). */
    public function migrateSettings(int $fromSettingsVersion, array $settings): array;
}

/** Ausgabe im Lernraum (Block, Shortcode, REST). */
interface RendersActivity
{
    /** Liefert nur, was der Lernende sehen darf: keine Lösungen, keine gesperrten Medien. */
    public function render(ActivityContext $context, RenderTarget $target): RenderResult;
}

/** Verarbeitet Lernenden-Interaktionen (Video-Position, „Als erledigt markieren“, Download, Kartenstatus). */
interface HandlesInteraction
{
    /** Idempotent über $interaction->idempotencyKey; schreibt nur eigenen Fortschritt. */
    public function interact(ActivityContext $context, Interaction $interaction): InteractionResult;
}

/** Abschlussermittlung (Dimension Completion, §5.1). */
interface DeterminesCompletion
{
    /** @return list<string> unterstützte Regeltypen, z. B. ["manual_confirm","view","position_ratio"] */
    public function completionRuleTypes(): array;

    public function defaultCompletionRule(): CompletionRule;

    /** Reine Funktion von (Regel, Fortschritt, Bewertungen); kein Schreiben. */
    public function evaluateCompletion(ActivityContext $context, CompletionRule $rule, ProgressState $state): CompletionDecision;
}

/** Bewertete Activities (quiz, exam; später Aufgaben/Tutorbewertung). */
interface GradesActivity
{
    /** Bewertet eine Abgabe ausschließlich gegen den Versuchs-Snapshot, nie gegen Live-Inhalte. */
    public function grade(AttemptSnapshot $snapshot, Submission $submission): GradeResult;

    /** Version der Bewertungslogik; wird im Snapshot gespeichert (evaluator_version). */
    public function evaluatorVersion(): string;
}

/** Export/Import von Definitionen (Paket R1-15) und Lernendendaten (Privacy R1-17). */
interface ExportsActivity
{
    public function exportDefinition(ActivityDefinition $definition, ExportContext $context): array;

    public function importDefinition(array $payload, ImportContext $context): ActivityDefinition;

    /** Personenbezogene Daten dieses Providers für den WP-Privacy-Exporter. */
    public function exportLearnerData(int $userId, ExportContext $context): array;

    /** Löschen/Pseudonymisieren nach Retention-Regel (R0-31); liefert Anzahl und Restbestand mit Grund. */
    public function eraseLearnerData(int $userId, EraseContext $context): EraseResult;
}

interface ActivityRegistry
{
    /** Doppelter Typ ist ein Fehler (kein stilles Überschreiben). */
    public function register(ActivityProvider $provider): void;

    public function get(string $type): ?ActivityProvider;

    /** @return array<string,ActivityProvider> */
    public function all(): array;
}
```

**Werteobjekte (Felder als Vertrag, alle unveränderlich):**

| Objekt | Felder |
|---|---|
| `ActivityCapabilities` | `completable`, `gradable`, `feedsMastery` (Antworten fließen in den Smarttrainer), `producesEvidence` (kann Kompetenznachweis sein), `accessScope` (`view`/`download`/`exam`), `dripAware`, `previewLimitType` (`first_items`/`questions_per_section`/`first_variants`/`none`), `needsExternalConsent` (Video-Host, E-45) |
| `ActivityDefinition` | `activityUid`, `type`, `contractVersion`, `settingsVersion`, `settings`, `required`, `completionRule`, `objectiveUids[]` |
| `FrozenActivity` | `activityUid`, `type`, `contractVersion`, `settingsVersion`, `settingsHash` (`sha256:`), `contentHash` (WP-Revisions-ID + Inhalts-Hash), `completionRule`, `required` |
| `ActivityContext` | `userId`, `courseUid`, `courseRevisionId`, `activityUid`, `gate` (`GateDecision`), `scopes`, `locale`, `clock`, `randomizer`, `correlationId` |
| `Interaction` | `kind` (z. B. `position`, `confirm`, `unconfirm`, `download`, `card_status`), `payload`, `idempotencyKey` (UUID), `clientTime` (nur Information, nie maßgeblich) |
| `ProgressState` | gespeicherter Stand aus `cc_activity_progress` (+ Bewertungen aus `cc_exam_session`, Kartenstände aus `cc_question_progress`) |
| `CompletionRule` | `type`, `params`, `ruleVersion`; zusammengesetzt über `all_of`/`any_of` (max. Tiefe 2) |
| `CompletionDecision` | `state` (`not_started`/`in_progress`/`completed`), `reasonCode`, `evidence[]` (Typ, Referenz-UUID, Zeitpunkt), `ruleVersion`, `decidedAt` |
| `GradeResult` | `pointsMilli`, `maxPointsMilli`, `perItem[]` (Position, `pointsMilli`, `correct` = volle Punktzahl), `parts[]`, `passed`, `evaluatorVersion` |

### 2.3 Lebenszyklus und Versionierung

- **contractVersion (SemVer):** Patch/Minor dürfen Settings nur additiv erweitern. Major bedeutet: alte eingefrorene Definitionen bleiben mit der alten Bewertungs-/Abschlusslogik auswertbar. Der Provider **MUSS** dazu alle Majors behalten, die in veröffentlichten Kursrevisionen oder offenen/abgeschlossenen Versuchen referenziert sind (Regressionstest im Golden Master R0-05).
- **settingsVersion (Ganzzahl):** Schema-Stand der Settings. `migrateSettings()` wird nur auf **Entwürfe** angewendet. Eingefrorene Definitionen werden nie umgeschrieben.
- **Veröffentlichen** ruft je Activity `validate()` und `freeze()`; das Ergebnis geht in `cc_course_revision.structure` mit `structure_hash` (R0-06 §5).
- **Provider fehlt/deaktiviert:** Activity wird als „derzeit nicht verfügbar“ angezeigt; Fortschritt bleibt erhalten; eine Pflicht-Activity wird **nicht** automatisch als abgeschlossen gewertet; Health-Check meldet es (R1-17). Kein Datenverlust beim Reaktivieren.
- **Typ ändern** ist bei veröffentlichten Activities nicht erlaubt (neue Activity anlegen, alte archivieren).

### 2.4 Activity-Typen in R1

| Typ | Zweck | Abschlussregeln (Standard **fett**) | Bewertung | Mastery | Nachweis | LB-Beleg |
|---|---|---|---|---|---|---|
| `lesson` | Text/Blöcke, Schrittgalerie, Ressourcen | **`manual_confirm`**, `view` (Activity gerendert und mindestens `min_seconds` geöffnet, serverseitig über zwei Interaktionen gemessen) | – | Inline-Fragen ja | nein | `video_lesson_step` → Blöcke (R0-06 E-05) |
| `video` | Video/Audio mit Position, Untertiteln, Inline-Fragen | **`manual_confirm`** (LB-Parität: Client meldet `completed`, VS:236-260), `position_ratio ≥ p` (Selbstauskunft des Clients, als solche gekennzeichnet) | – | Inline-Fragen ja (Kontext `video`, LS:177) | nein | VS:45-96, :173-233, :236-260; `video_modules.schema.json` (`provider` `url/youtube/vimeo/media`, `duration_seconds`) |
| `file` | geschützter Download | **`downloaded`** (serverseitig protokollierter, erfolgreicher Download), `manual_confirm` | – | – | nein | Gate/ProtectedFiles (Inventur A §2.5); Nutzungsereignis E-A-06 |
| `quiz` | Übung/Kurzquiz aus festen Fragen oder Modulpool | **`attempted`** (alle Fragen einmal beantwortet), `passed` (Bestehensregel §4.1), `all_correct_once` | ja: unbewertet (Lernmodus-Engine, `cc_attempt`) oder bewertet (Versuchsautomat §6, Modus `quiz`, → Δ-2) | ja (unbewertet) | nur wenn `settings.counts_as_evidence = true` und bewertet | Lernmodus LS:80-282; Videoquiz `video_lesson_question` |
| `exam` | Prüfung nach Regel, Blueprint oder fester Variante | **`passed`**, `evaluated` (abgegeben, egal ob bestanden) | ja (§4, §6) | **nein** (LB: Prüfungsantworten ändern den Lernstand nicht; ES ruft `applyProgress` nie auf) | ja, wenn nachweisfähig (§5.3) | ES gesamt |
| `flashcard` | Karteikarten-Set (E-21) | **`all_known`** (Modus Selbsteinschätzung), `all_streak` (Modus Serie), `ratio ≥ r` | – | eigener Kartenstand (nicht Smarttrainer, E-21) | nein | TS:272-297 (`open`/`practice`/`known`); Serie bis „gelernt“ ab 3 in Folge (Inventur A §5.3, `RadioService.php:278-279`) |

**Karteikarten-Modi (E-21):**
- `self_assessment`: Lernender setzt je Karte `open` → `practice` → `known` frei (jede Richtung erlaubt, LB TS:275, UPSERT TS:292-297).
- `streak`: Karte wird `known`, sobald `known_streak` (Standard 3, LB) Selbsteinschätzungen „gewusst“ in Folge vorliegen; „nicht gewusst“ setzt die Serie auf 0; einmal `known` bleibt `known` (LB „bleibt gelernt“). Doppelklick-Schutz über `idempotencyKey`.
- Speicherung: Kartenstand je Karte und Nutzer; R0-06 sieht `cc_question_progress` dafür vor (E-21-Vermerk in R0-06 E-25). Karten sind dann Fragen vom Typ `flashcard` (§3.1) mit getrenntem `learn_status`-Wertebereich und eigener `algorithm_version`.

---

## 3. Fragetypen und Bewertungsregeln

### 3.1 Typen

**Beleg aus dem LB-Schema** (`schema/v1/questions.schema.json`):
- `question_type` ∈ `single_choice`, `multi_choice`, `open_text`, `chart_task` (:27).
- Antwortschlüssel `^([A-Z]|MODEL)$` (:62) → höchstens 26 Optionen; `MODEL` ist die Musterantwort offener Fragen.
- `single_choice`: ≥ 2 Antworten, **genau eine** richtig (:84-85).
- `multi_choice`: ≥ 2 Antworten, **mindestens eine** richtig (:86-87).
- `open_text`: **genau eine** Antwort (die Musterantwort) (:88-89).
- `chart_task`: darf nie `published` sein (:90-91) → fachgebunden (Seekarte), **entfällt** (INV-E-006).
- Revision trägt `feedback_correct`/`feedback_incorrect` (:35-36), `topic`/`subtopic`/`competency`/`smart_category`/`scope` (:29-33 → `cc_question_tag`, R0-06 E-10), `image_required` (:34), Rechte- und Gültigkeitsfelder (:50-54), `media[]` mit `role` und `sort_order` (:69-80).

| Typ (neutral) | Phase | Validierung beim Veröffentlichen | Bewertungsregel (`all_or_nothing`) | Teilpunkte möglich | Beleg |
|---|---|---|---|---|---|
| `single_choice` | R1 | 2–26 Optionen, genau 1 richtig | richtig ⇔ genau eine Auswahl und diese ist richtig | nein | Schema :84-85; QP:177-195 (`default`-Zweig) |
| `true_false` | R1 | Spezialisierung von `single_choice` mit genau 2 festen Optionen („Wahr“/„Falsch“, übersetzbar), kein Mischen | wie `single_choice` | nein | aus `single_choice` abgeleitet; Konzept 3.3 |
| `multi_choice` | R1 | 2–26 Optionen, ≥ 1 richtig | richtig ⇔ Auswahl nicht leer **und** Menge = Menge der richtigen (QP:186-191) | ja, `scoring` §3.2 | Schema :86-87; QP:189 |
| `open_text` | R1 | genau 1 Musterantwort | richtig ⇔ Selbsteinschätzung „gewusst“ (`known === true`, QP:188); in der Prüfung erst nach Aufdecken der Musterantwort (ES:434-436) | nein | Schema :88-89; LS:236-238; ES:503-516 |
| `flashcard` | R1 | Vorderseite, Rückseite, optionale Erklärung/Medien | keine Punktbewertung; Kartenstand §2.4 | – | TS:272-297; `praxis_tasks.schema.json` (`question`, `answer`, `explanation`, `media_source_id`) |
| `matching`, `ordering`, `cloze` | **→ D-03** | Konzept 3.3 (Free), aber **ohne LB-Beleg** → kein Golden Master | Vertrag unten vorbereitet | ja | Konzept 3.3, 27.2 („Basis nach Parität“) |
| `numeric_tolerance` | nicht R1 (E-22) | – | – | – | E-A-03 |

Für die nicht belegten Typen gilt bereits jetzt der Vertrag: deterministische Bewertung, Teilpunkt-Strategie aus §3.2, Normalisierung (Lückentext: `trim`, Unicode-NFC, Groß-/Kleinschreibung und Leerzeichen-Kollaps konfigurierbar; **keine** semantische Bewertung, Konzept 3.3).

**Eingabevalidierung (LB-Parität):**
- Lernmodus: Auswahl nicht leer und nur aus den ausgelieferten Optionen (LS:229-232); `single_choice` genau eine (LS:233-235); `open_text` braucht `known` (LS:236-238).
- Prüfung: leere Auswahl bei `multi_choice` **nimmt die Antwort zurück** (unbeantwortet, ES:441-444); bei `single_choice` ist leer ungültig (ES:445-449).
- Die erlaubten Options-IDs kommen im Lernmodus aus dem signierten Frage-Token (`o`, QP:147-152, LS:227), in der Prüfung aus `answer_order` des Snapshots (ES:439).

### 3.2 Teilpunkte

**LB-Befund:** Es gibt keine Teilpunkte. `evaluate()` liefert nur `bool` (QP:177-195), die Prüfung zählt richtige Fragen (`score` = Anzahl, `max_score` = Fragenanzahl, ES:638-655). Konzept 3.3 verlangt für Multiple Choice eine **definierte** Teilpunkt- oder Alles-oder-nichts-Regel.

| `scoring` | Formel je Frage (Gewicht `w` Punkte, `c` = Anzahl richtiger Optionen, `n` = Anzahl Optionen, `r` = richtig gewählt, `f` = falsch gewählt) | Hinweis |
|---|---|---|
| **`all_or_nothing`** (Standard, Parität) | `w`, wenn Auswahlmenge = richtige Menge, sonst 0 | entspricht QP:189 |
| `proportional_penalty` | `w · max(0, (r − f) / c)` | Alles ankreuzen ergibt `(c − (n − c)) / c` und damit < `w`, sobald es mindestens eine falsche Option gibt |
| `per_option` | `w · (Anzahl korrekt klassifizierter Optionen) / n`, **aber 0 bei leerer Auswahl** | nur zulassen, wenn `n ≥ 3`; sonst Ratequote 50 % |

- **MUSS:** Punkte werden in **Tausendstelpunkten als Ganzzahl** gerechnet und gespeichert (`points_milli`); Abrundung je Frage auf ganze Tausendstel, Summe ohne Rundung. Damit entfällt der Gleitkomma-Epsilon-Vergleich (LB `- 1e-9`, ES:647, :700). → **Delta Δ-3 zu R0-06** (`score`/`max_score` in Tausendstelpunkten bzw. neue Spalten `points_milli`/`max_points_milli`; je Session-Frage `points_milli`).
- **MUSS (Golden-Master-Anker):** „Alles ankreuzen besteht keine Prüfung“ (LB-Test `testNoExamCanBePassedByTickingEveryOption`, INV-E-008) bleibt für jede zulässige Strategie wahr.
- `correct` einer Frage bedeutet „volle Punktzahl“. Fehlertraining („falsch beantwortet“, ES:562-574) nutzt `correct = false`.
- `scoring` steht an der Frage (Revision, Standard) und kann in der Prüfungsregel überschrieben werden; der wirksame Wert wird je Frage in den Snapshot geschrieben (§6.3).

### 3.3 Offene Fragen in Prüfungen

Selbsteinschätzung zählt in LB als richtig/falsch in der Gesamtquote (ES:629-640) und wird im Ergebnis gesondert ausgewiesen (`open_text`-Statistik, ES:869-872). Vertrag: `settings.open_text_in_score` (Standard `true` = Parität). Steht er auf `false`, zählt die Frage nicht zu Punkten und Maximum. Eine Prüfung mit `producesEvidence` **SOLL** offene Selbsteinschätzungsfragen ausschließen; der Publish-Validator warnt.

### 3.4 Mischen mit Seed

**LB-Befund:**
- Mischen per Fisher-Yates mit `random_int`, **ohne Seed** (QP:208-215).
- Antwortreihenfolge: im Lernmodus bei jeder Anzeige neu gemischt und im signierten Token mitgegeben (QP:111-145, :147-152); in der Prüfung beim Start gemischt und **als materialisierte Liste** in `answer_order` gespeichert (ES:298-305).
- Fragenauswahl der Zufallsprüfung: je Blueprint-Modul Pool ohne Doppelungen mischen und die ersten *k* nehmen (ES:237-252); die Fragenreihenfolge bleibt **nach Blueprint-Modulen gruppiert** (kein modulübergreifendes Mischen). Feste Varianten behalten die Positionsreihenfolge.
- `open_text` hat keine Optionen (QP:121-122).

**Vertrag:**
- **MUSS:** Zufall nur über `Randomizer` mit `seed` (64 Bit, aus `random_bytes` beim Versuchsstart) und `randomizer_version`. Abgeleitete Ströme je Zweck: `HMAC-SHA256(seed, purpose || counter)`, Zwecke `pool:<section_uid>`, `question_order`, `answers:<question_uid>`. Das ist reines PHP und plattformunabhängig (keine Abhängigkeit von `Random\Engine`, PHP 8.2).
- **MUSS:** Die **materialisierte** Reihenfolge (Fragenliste und `answer_order` je Frage) ist maßgeblich und wird gespeichert. Der Seed dient Reproduzierbarkeit, Tests und Audit; bei Abweichung gilt die gespeicherte Liste.
- Einstellungen in der Prüfungsregel: `shuffle_questions` ∈ `none` (feste Variante) / **`within_section`** (Parität) / `all`; `shuffle_answers` (Standard `true`); Option je Antwort `pinned` (fixe Position, z. B. „Keine der genannten“).
- Lernmodus: weiterhin frische Mischung je Anzeige; die Reihenfolge steht im signierten Token (QP-Muster). Test-Randomizer ist injizierbar (INV-A-028).

---

## 4. Bewertung

### 4.1 Bestehensregel

**LB-Befund (INV-C-132, `ENTSCHEIDUNGEN.md` §7):** Fester Rückfallwert `0.75` an vier Stellen: CR:207 (`?: ['min_correct_ratio' => 0.75]`, wenn `passing_rule` nicht lesbar ist), ES:646 (Auswertung), ES:936 und ES:1015 (Anzeige). Das Paketschema verlangt `min_correct_ratio` zwar zwingend (`exam_rules.schema.json:20-22`, `exclusiveMinimum 0`, `maximum 1`), der Code wertet aber bei defektem JSON still mit 75 % aus. Zusätzlich wählt `examRule()` bei fehlender veröffentlichter, verifizierter und gültiger Regel **die neueste nicht archivierte Regel** als Übungsmodus, auch einen Entwurf (CR:200-215).

**Vertrag:**
- **MUSS:** `passing_rule` ist Pflicht. Es gibt **keinen** Standardwert im Code. Die Settings-UI darf einen Startwert vorschlagen (R0-27), gespeichert wird immer ein expliziter Wert.
- **MUSS:** Fehlt die Regel oder ist sie ungültig, ist die Prüfung **nicht startbar** (`cc_exam_not_startable`). Ein bereits laufender Versuch mit defektem Snapshot geht in `broken` (§6.1), nie in „bestanden mit Standardwert“.
- Struktur (eingefroren im Snapshot):

```
passing_rule {
  min_ratio:   { num: int, den: int }           // z. B. 3/4; 0 < num/den ≤ 1
  parts: [ { section_uid, label, min_points_milli | min_correct, of, seconds? } ],
  require_all_parts: true,                      // LB: Gesamtquote UND alle Teile (ES:647)
  empty_part_policy: "pass" | "fail",           // LB: leerer Teil gilt als bestanden (ES:700)
  note: ?string
}
```

- **Vergleich ohne Gleitkomma:** bestanden ⇔ `points · den ≥ num · max_points` und jeder Teil `points_teil · of ≥ min · total_teil` (bzw. auf Punkte bezogen).
- **Mehrteilige Regeln (Parität):** Kommen nur einige Teile in der gezogenen Fragenmenge vor, gelten nur diese; die Gesamtquote wird dann aus `Σ min_correct / Σ of` der geltenden Teile neu berechnet und die Zeit aus den Teilzeiten summiert, wenn **alle** geltenden Teile `seconds` haben (ES:982-1007). Ergebnis wird beim Start eingefroren.
- **Zuordnung Frage → Teil** wird beim Start eingefroren (`question_parts`, ES:270-271); jede Frage gehört zum **ersten** passenden Teil in Teilreihenfolge (ES:713-736).
- **Regelstatus (E-23):** `draft` / `published` / `retired`. `evidence_eligible = (status = published) ∧ gültig am Starttag ∧ ¬reduced` ersetzt LB `live` (ES:267). Lernende können eine Entwurfsregel nur als Übung starten (Kennzeichnung sichtbar, E-A-04 (b)); der Text verspricht keine amtliche Gültigkeit.
- **Verkürzt (`reduced`):** Reicht der Pool für einen Blueprint nicht, läuft die Prüfung verkürzt als Übung (ES:243-246) – Parität. Verkürzte Versuche sind nie nachweisfähig und nie zertifikatsfähig.

### 4.2 Gewichtung

- LB: jede Frage zählt 1 (ES:638-655). **Parität = Gewicht 1.**
- Vertrag: Gewicht `points` je Frage in Blueprint (je Modul einheitlich) oder Variante (je Position), Standard 1, ganzzahlig 1–100. Teilgrenzen können als Anzahl richtiger Fragen (`min_correct`, Parität) oder als Punkte (`min_points_milli`) formuliert sein, nicht gemischt innerhalb einer Regel.
- Kursnote/Gradebook über mehrere Activities ist **Pro** (Konzept 27.2) und keine zweite Ergebniswahrheit: Es liest nur Versuchsergebnisse.

### 4.3 Zeitlimit

| Regel | Wert / Verhalten | LB-Beleg |
|---|---|---|
| Zeitlimit | `time_limit_seconds` ≥ 1 oder `NULL` = ohne Limit | `exam_rules.schema.json:16`; R0-06 `cc_exam_rule` |
| Frist | `deadline_at = started_at + Limit`, Serverzeit UTC | ES:289 |
| Grace | Antworten mit Zeitpunkt ≤ Frist + 5 s zählen | ES:41, :426, :624-629 |
| Kulanz für nachgesendete Antworten | Standard 120 s, Bereich 0–600; 0 = streng (Gerätezeit wird ignoriert) | ES:45, :56-60, :412-416 |
| Gerätezeit | `age_ms` oder `answered_at`, begrenzt auf [Start bzw. Aufdecken, jetzt] | ES:417-425 |
| Auto-Auswertung | wenn `now > deadline + Kulanz` (Cron stündlich und vor jedem Zugriff) | ES:576-599, :1021-1024 |
| Ohne Limit | Versuch läuft unbegrenzt; Fragen sind im Lernmodus nur 24 h nach Start gesperrt | LS:52, :791-810; → D-06 |

**Abweichung von LB (MUSS):** Grace und Kulanz werden **beim Start in den Snapshot geschrieben**. LB liest die Kulanz bei jeder Prüfung live aus einem Filter (ES:56-60); eine spätere Änderung verändert damit laufende Versuche.

### 4.4 Versuche

- `attempt_policy { max_attempts: ?int (NULL = unbegrenzt), cooldown_seconds: int (0), count_cancelled: bool }` an der Prüfungsregel; eingefroren beim Start. Konzept 4.3: Begrenzung **optional**, nie für den freien Übungsmodus erzwungen.
- Start-Rate-Limit bleibt als Missbrauchsschutz unabhängig davon: 40 neue Versuche je Nutzer und Stunde, Fortsetzen zählt nicht (ES:47, :196-198).
- Ob ein Abbruch als Versuch zählt → **D-05**.

### 4.5 Feedback-Sichtbarkeit (E-20)

**LB-Befund:** Erklärungen nur mit dem separat verkauften Feature `learning_explanations`, sonst `explanation_locked` (LS:628, :642-643; ES:834, :887). Lösungen (`correct_ids`, Musterantwort) zeigt LB im Lernmodus sofort (LS:639-641) und in der Prüfung erst im Ergebnis (ES:881-882). Während der Prüfung gibt es keine Lösungen, nur bei offenen Fragen die Musterantwort nach Aufdecken (ES:377).

**Vertrag:** Einstellung je Kurs, überschreibbar je Quiz/Prüfungsregel, eingefroren im Versuchs-Snapshot:

| Schlüssel | Werte | Standard Übung/Lernmodus | Standard Prüfung |
|---|---|---|---|
| `feedback_visibility` | `always` / `after_submit` / `never` | `always` | `after_submit` |
| `show_score` | bool | – | `true` |
| `show_correct_answers` | bool (nur wenn `feedback_visibility ≠ never`) | `true` | `true` |
| `show_explanations` | bool (nur wenn `feedback_visibility ≠ never`) | `true` | `true` |

- `always` bedeutet Sofortfeedback nach jeder Antwort und gilt nur für Lernmodus und unbewertete Quizze. In einem laufenden bewerteten Versuch gibt es **nie** Lösungen; `always` wird dort wie `after_submit` behandelt.
- Erklärungstext = `feedback_correct` bzw. `feedback_incorrect` der **Snapshot-Revision**, abhängig vom Ergebnis der Frage (Parität LS:642, ES:887).
- Eine Freigabe „nach Trainerfreigabe“ (`after_release`, Konzept 4.3 „ohne Lösung bis Freigabe“) ist Pro und nicht Teil von R1.

### 4.6 Manuelle Korrektur und Audit

- Abgeschlossene Ergebnisse werden **nie überschrieben** (28.3). Eine Korrektur (z. B. fehlerhafte Frage) erzeugt eine neue Auswertung mit Grund, Akteur und Zeit; die alte bleibt sichtbar → **D-08** (Umfang R1).
- Jede Änderung an Bewertungsregeln einer veröffentlichten Prüfung ist eine neue Regelversion (R0-06 §5) und erzeugt einen Audit-Eintrag (`cc_audit_log`).

---

## 5. Drei Fortschrittsdimensionen

| Dimension | Frage, die sie beantwortet | Speicherung | Quelle der Wahrheit | Darf **nicht** |
|---|---|---|---|---|
| **Completion** (bearbeitet) | „Habe ich das durchgearbeitet?“ | `cc_activity_progress`, Aggregat `cc_course_progress.completion_ratio` | Provider-Abschlussentscheidung (§2.2) | aus Mastery oder Kauf entstehen |
| **Mastery** (beherrscht) | „Kann ich das zuverlässig?“ | `cc_question_progress` (+ Kartenstände), Aggregat `mastery_ratio` | Smarttrainer-Algorithmus (§5.2) | aus Video-/Seitenaufrufen oder Prüfungsantworten entstehen (Parität) |
| **Competency** (nachgewiesen) | „Ist es belegt?“ | abgeleitet aus nachweisfähigen Versuchen und Zertifikaten (R0-06 E-28) | §5.3 | aus Completion, Mastery, Kauf oder Check-in entstehen (28.3, 35.4) |

**Erlaubte Kopplung:** Eine Abschlussregel darf einen Prüfungs- oder Quiz-Erfolg verlangen (`passed`). Damit nutzt Completion ein Bewertungsergebnis, nicht umgekehrt. Mastery und Competency speisen sich nie gegenseitig.

### 5.1 Completion

- **Activity:** Status `not_started` → `in_progress` → `completed`. Jeder Übergang speichert `reason_code`, `rule_version`, `completed_at` und den `content_hash` der Activity zum Abschlusszeitpunkt (R0-06 `cc_activity_progress.content_hash`).
- **Monotonie:** Nachweisbasierte Abschlüsse (`downloaded`, `passed`, `evaluated`, `attempted`, `all_known`) bleiben bestehen. `manual_confirm` kann der Lernende zurücknehmen (LB VS:246-247 erlaubt `completed = false`) → **D-04**.
- **Modul:** abgeschlossen ⇔ alle Pflicht-Activities des Moduls in der Revision des Lernenden abgeschlossen.
- **Kurs:** abgeschlossen ⇔ alle Pflichtmodule (`cc_course_module.required`, LB `courses.schema.json:27`) abgeschlossen **und** die optionale Kurs-Abschlussregel (z. B. `passed(exam)`) erfüllt. `completion_ratio = erledigte Pflicht-Activities / Pflicht-Activities` (`decimal(5,4)`).
- **Revision:** maßgeblich ist `cc_course_progress.course_revision_id`; Umstellung auf neue Revisionen regelt R0-23.
- **Erklärbarkeit:** API liefert je Activity `state`, `reason_code`, Regeltext (i18n) und Belegreferenz, z. B. „Abgeschlossen: Quiz bestanden mit 8/10 am 14.11.2026“ oder „Offen: Voraussetzung ‚Modul 2‘ nicht erfüllt“.

### 5.2 Mastery – Smarttrainer v1 (exakte LB-Beschreibung, ADR-005)

**Algorithmusversion:** `smarttrainer.v1` = Verhalten von LS @ 9f37c77. `algorithm_version` steht in `cc_question_progress` und `cc_attempt` (R0-06 §5). Parameter sind in v1 **fest** (Golden Master R0-05); konfigurierbare Parameter (INV-A-002/009 `abstract`) kommen nur als neue Version (≥ v2) und wirken nur auf neue Antworten (Konzept 4.2, 28.1).

**Parameter v1:**

| Parameter | Wert | Quelle |
|---|---|---|
| Modi | `smart`, `new`, `wrong`, `favorites` | LS:39 |
| Antwort-Rate-Limit | 60 je Nutzer und Minute | LS:40, :812-820 |
| `STREAK_GAP_SECONDS` | 300 s | LS:42 |
| `RECENT_EXCLUDE` | 3 Fragen | LS:44 |
| `SECURE_STREAK` | 3 richtige in Folge | LS:46 |
| `CONSOLIDATE_GAP_SECONDS` | 20 h | LS:48 |
| `REVIEW_INTERVAL_DAYS` | 7, 21, 60 (danach bleibt 60) | LS:50 |
| `OPEN_EXAM_BLOCK_SECONDS` | 24 h | LS:52 |
| Frage-Token-Gültigkeit | 2 h | QP:30 |

**Stufen:** `new` (kein Datensatz) · `unsure` (zuletzt falsch) · `learning` (Serie 1–2) · `secure` („sicher heute“) · `mastered` („gefestigt“).

**Fortschrittsupdate je Lernantwort** (`applyProgress`, LS:495-538; läuft in derselben Transaktion wie das Speichern der Antwort, LS:243-277):

1. Zeile anlegen, falls nötig (`INSERT IGNORE`, LS:497), und mit `SELECT … FOR UPDATE` sperren (LS:498). Ein zweiter Tab wartet und sieht danach den frischen Stand.
2. `attempts += 1`; `correct_count` bzw. `wrong_count += 1` (LS:499-501).
3. **Falsch** (LS:505-509): `streak = 0`, Stufe `unsure`, `due_at = NULL`, Rückgabe „nicht gezählt“ (`null`).
4. **Richtig, vorherige Stufe `secure` oder `mastered`** (LS:510-517): gezählt ⇔ fällig (`isDue`). Wenn gezählt: `streak += 1`, Stufe `mastered`, `due_at = jetzt + reviewInterval(streak)`. Sonst bleiben Serie, Stufe und Fälligkeit unverändert.
5. **Richtig, vorherige Stufe `new`, `unsure` oder `learning`** (LS:518-523): gezählt ⇔ `spaced()`. `streak += gezählt ? 1 : 0`; Stufe = `secure`, wenn `streak ≥ 3`, sonst `learning`; `due_at = jetzt + 20 h` bei `secure`, sonst `NULL`.
6. `mastery_score = round(min(1, 0.4 · correct_count/attempts + 0.6 · min(streak, 3)/3), 4)` (LS:524).
7. `first_answered_at` einmalig, `last_answered_at = jetzt` (LS:525-535).

**Intervall** (`reviewInterval`, LS:541-547): `index = clamp(streak − 3 − 1, 0, 2)` → Serie 4 (erste Festigung) = 7 Tage, Serie 5 = 21 Tage, Serie ≥ 6 = 60 Tage.

**Fälligkeit** (`dueAt`/`isDue`, LS:553-572): nur für `secure`/`mastered`; fällig ⇔ `due_at ≤ jetzt`. Der LB-Zweig für Altbestand ohne `due_at` (vor Schema 1.14.0: `secure` = letzte Antwort + 20 h, `mastered` = + 7 Tage, LS:559-564) betrifft nur LB-Daten. Vertrag: `due_at` ist für `secure`/`mastered` **Pflicht**; der Zweig wird nicht portiert (INV-A-009), außer ein Migrationswerkzeug (E-03) braucht ihn.

**Abstand** (`spaced`, LS:580-596): gezählt ⇔ (a) erste Antwort auf diese Frage, oder (b) ≥ 300 s seit `last_answered_at`, oder (c) seit `last_answered_at` (inklusive, `>=`) gibt es **irgendeine** Antwort des Nutzers auf eine andere Frage, kursübergreifend (LS:589-595).

**Auswahl der nächsten Frage** (`next`, LS:80-158):

1. Kandidaten: Fragen der nicht per Drip gesperrten Module (LS:85-95), im Teilumfang nur die ersten *n* je Modul (LS:393-428); Fragen laufender Prüfungen entfernt (LS:97-99); optional Themenfilter (`COALESCE(smart_category, topic)`, CR:127; LS:100-103); optional Sammlung/Fehlerliste (`onlyIds`) oder Favoriten (LS:104-109).
2. Modusfilter: `new` nur ohne Datensatz (LS:124-126); `wrong` nur „zuletzt falsch“ = `attempts > 0 ∧ streak = 0` (LS:127-129, :758-761).
3. Punktwert je Frage (`score`, LS:712-741), in dieser Reihenfolge:
   - noch nie beantwortet → **1000** („Noch nie gesehen“)
   - zuletzt falsch → **800 + 10 · min(wrong_count, 10)**
   - fällig → **700** („Heute sicher – Wiederholung an einem neuen Tag“ bei `secure`, sonst „Wiederholung fällig“)
   - sonst `base = (1 − mastery_score) · 300`; bei `secure`/`mastered` (nicht fällig) **`base · 0,1`**; bei schwachem Thema (Trefferquote des Themas über die **aktuelle Kandidatenmenge** < 0,6; ohne Versuche gilt 1,0, LS:691-710) **`base + 150`**; sonst `base`.
4. Abstandsregel: Die Fragen unter den letzten 100 Antworten der letzten 2 h (LS:604-624) werden nach Aktualität sortiert; die ersten 3 sind nur Rückfall, wenn sonst nichts übrig ist (LS:117, :132-137, :143).
5. Gewinner = erster Kandidat mit dem höchsten Wert in Kandidatenreihenfolge (strikt `>`, LS:138) → Gleichstand nach Modul- und Fragenreihenfolge, also deterministisch.
6. Ausgabe mit Begründungstext (i18n), Fortschritts-Payload (`status`, `streak`, `attempts`, `due_at`, LS:650-654) und signiertem Token (Nutzer, Revision, Antwortreihenfolge, Kontext, Zeit, Nonce; HMAC-SHA256, QP:147-152).

**Antwort-Idempotenz (LB):** Der Schlüssel wird aus dem Token-Hash abgeleitet (LS:212-215); ein zweites Einreichen desselben Tokens liefert das erste Ergebnis mit `repeated = true` (LS:217-224); ein paralleler Doppel-Submit scheitert am Unique-Index und liefert das Ergebnis des Gewinners (LS:259-265). R0-06 macht den Unique nutzerbezogen (`UNIQUE user_idem`).

**Aggregat** (`summaryFrom`, LS:442-458): Zählung je Stufe; `percent = floor(mastered · 100 / Fragen)`; `secure_percent` analog; `due` = Anzahl fälliger Fragen. Vertrag: `mastery_ratio = mastered / Fragen` im freigeschalteten Umfang; Themenzusammenfassung nur bei ≥ 2 Themen (INV-A-011).

**Für den Golden Master festzuhaltende Eigenheiten (Parität, nicht „reparieren“):**
- G-1: Richtige Antwort in `unsure` ohne Abstand → Stufe `learning` mit `streak = 0`. Die Frage bleibt damit „zuletzt falsch“ (LS:519-521 mit :758-761).
- G-2: `spaced()` zählt Antworten auf andere Fragen **aus allen Kursen** (LS:589-595).
- G-3: Richtige, nicht fällige Antworten in `secure`/`mastered` ändern Serie und Stufe nicht, erhöhen aber `attempts`/`correct_count` und damit `mastery_score`.
- G-4: Prüfungsantworten ändern den Lernstand nicht; Videoquiz-Antworten schon (Kontext `video`).
- G-5: Fragen einer laufenden Prüfung sind im Lernmodus bis Frist + Kulanz gesperrt, ohne Zeitlimit 24 h ab Start (LS:791-810), und im `answer()` abgewiesen (LS:208-210).

**Erklärbarkeit:** UI und API zeigen je Frage Stufe, Serie, nächste Fälligkeit und den Auswahlgrund; je Kurs/Modul/Thema die Stufenverteilung. Es gibt keinen Gesamt-„Beherrschungsgrad“ ohne Datengrundlage (28.4 Nr. 4): Bei 0 beantworteten Fragen wird „noch keine Daten“ angezeigt, nicht 0 %.

### 5.3 Competency

- **Lernziel** (`objective_uid`) ist ein Kursobjekt mit Titel und optionaler Gültigkeitsdauer. Zuordnung: Fragen über `cc_question_tag` (`tag_type = competency`, LB-Feld `competency`, `questions.schema.json:31`), Activities über `ActivityDefinition.objectiveUids`.
- **Nachweisquellen in R1:** (1) bewerteter Versuch mit `evidence_eligible = true`, `passed = true`, Activity `producesEvidence`; (2) daraus ausgestelltes Zertifikat. **Nicht** in R1: manuelle Trainerbestätigung und externe Nachweise (Pro/R2, → D-07).
- **Welche Ziele ein Versuch belegt:** die in der Prüfungsregel ausdrücklich genannten `certifies_objectives` (im Snapshot eingefroren). Die Themenanalyse eines Versuchs ist Information, kein Nachweis je Ziel.
- **Zustand je Lernendem und Ziel:** `not_evidenced` / `evidenced` / `expired` (Gültigkeit abgelaufen) / `revoked` (Versuch annulliert oder Zertifikat widerrufen). Ein Refund ist kein Widerrufsgrund (35.3).
- **Nachweisdatensatz (abgeleitet):** Quelle (`exam_session.uid` bzw. `certificate_uid`), Ziel, Kursrevision, Regelversion, `evaluated_at`, Punkte, `valid_until` (aus `competency_validity_days` im Snapshot).
- **Erklärbarkeit:** „Nachgewiesen durch Prüfung ‚X‘ (Version 3) am 12.11.2026, 27/30 Punkte, gültig bis …“. Ohne Nachweis steht dort „nicht nachgewiesen“, nie eine geschätzte Quote.

---

## 6. Prüfungsversuch-Zustandsautomat

### 6.1 Zustände

| CC-Zustand | LB-Status | Konzept 28.3 | Bedeutung |
|---|---|---|---|
| `in_progress` | `running` (ES:286) | `created` + `in_progress` | Versuch läuft. `created` ist nicht beobachtbar, weil Session, Fragen und Snapshot in **einer** Transaktion entstehen (ES:277-313) |
| `submitted` | – | `submitted` | nur für Versuche mit nicht automatisch bewertbaren Antworten (Tutorbewertung, Pro); in R1 nicht erreichbar |
| `evaluated` | `evaluated` (ES:649) | `evaluated` / `expired` | ausgewertet; `end_reason` = `submitted` oder `expired` (LB `result.expired`, ES:654) → D-09 |
| `cancelled` | `aborted` (ES:549) | `cancelled` | abgebrochen ohne Wertung, Antworten bleiben gespeichert |
| `broken` | `error` (ES:605) | – | Auswertung technisch gescheitert; Fehlertext im Ergebnis; Reparaturpfad §6.2 |

Endzustände: `evaluated`, `cancelled`; `broken` ist nur über eine Admin-Reparatur verlassbar.

### 6.2 Übergänge

| Von | Aktion | Nach | Vorbedingungen (serverseitig) | Sperre / Transaktion | Idempotenz | LB-Beleg |
|---|---|---|---|---|---|---|
| – | `start(activity, variant?, request_key)` | `in_progress` | Zugriff mit Scope `exam`; Teilumfang (nur erste *n* Varianten, keine Zufallsprüfung); Regel vorhanden und gültig; Drip frei; Variante vollständig auslieferbar; Pool nicht leer; Versuchs- und Start-Limit | `NamedLock exam_start:<user>` (10 s warten; `GET_LOCK` nicht verfügbar → ohne Sperre weiter, die Prüfung auf laufende Versuche bleibt); danach 1 TX: Session + alle Session-Fragen + Snapshot | Laufender Versuch derselben Form (Variante bzw. Zufall) wird **zurückgegeben** statt dupliziert; **neu:** Client-`request_key` → `start_token` UNIQUE, wiederholter Start mit gleichem Schlüssel liefert denselben Versuch | ES:160-317 (Lock :183-186, Fortsetzen :188-194, Rate-Limit :196-198, Variante :202-217, Zufall :218-252, Snapshot :263-276, TX :277-313) |
| `in_progress` | `saveAnswer(position, answer, client_time?)` | `in_progress` | Versuch eigener Nutzer; Zugriff besteht weiter (sonst `cc_access_denied`); Antwortzeitpunkt ≤ Frist + Grace; `open_text` erst nach `reveal` | TX: `FOR UPDATE` auf Session (Status muss `in_progress` sein) und Session-Frage; CAS `row_version` | gleiche Antwort zweimal = einmal; ein **älterer** Stand (früherer Antwortzeitpunkt) überschreibt keinen neueren → `applied = false` | ES:405-484 (Zeit :411-428, Sperren :456-462, Reihenfolge :464-468) |
| `in_progress` | `flag(position, on)` | `in_progress` | vor Frist + Grace | TX `FOR UPDATE` Session | Setzen auf Zielwert (natürlich idempotent) | ES:486-501, :1027-1032 |
| `in_progress` | `reveal(position)` (`open_text`) | `in_progress` | vor Frist + Grace; Typ `open_text` | **neu:** TX `FOR UPDATE` (LB setzt `revealed_at` ohne Sperre, ES:511-513) | `revealed_at` nur einmal gesetzt | ES:503-516 |
| `in_progress` | `submit()` | `evaluated` (`end_reason = submitted`; `expired = true`, wenn `now ≥ deadline`) | – | TX `FOR UPDATE` Session; Bewertung nur gegen Snapshot | Nicht-`in_progress` → keine Änderung, vorhandenes Ergebnis zurück | ES:518-526, :612-672 |
| `in_progress` | Frist + Kulanz vorbei (Cron stündlich; vor `start`, `show`, jeder Aktion) | `evaluated` (`end_reason = expired`) | `deadline_at < now − Kulanz` | wie `submit` | wie `submit` | ES:576-599, :339-342, :1063-1066, :173 |
| `in_progress` | `cancel()` | `cancelled` | nicht abgelaufen (sonst wird ausgewertet) | TX `FOR UPDATE` | Nicht-`in_progress` → aktueller Status zurück | ES:534-557 |
| `in_progress` | Auswertung wirft Fehler | `broken` | nur im Cron-Pfad abgefangen (LB); **neu:** in jedem Pfad, mit Health-Meldung | bedingtes Update `WHERE status = in_progress` | – | ES:589-596, :601-609 |
| `broken` | `admin.reevaluate()` | `evaluated` | Ursache behoben; Capability R0-07; Pflichtbegründung | wie `submit` | einmalig; Audit | **neu** |
| `broken` | `admin.cancel()` | `cancelled` | Pflichtbegründung | TX | Audit | **neu** |

**Bewertung (Parität, ES:612-672):** Je Frage zählt eine Antwort nur, wenn `answered_at ≤ deadline + Grace` (ES:629); unbeantwortet = falsch (ES:638); Teilergebnisse über die eingefrorene Zuordnung (ES:675-705); bestanden = Gesamtgrenze **und** alle Teile (ES:647). Ergebnis speichert Punkte, Maximum, `passed`, Teile, `expired` und Quote.

**Isolation:** Während `in_progress` sind die Fragen des Versuchs im Lernmodus und Videoquiz gesperrt (§5.2 G-5).

### 6.3 Snapshot-Inhalt (beim Start, unveränderlich)

`cc_exam_session.snapshot` (kanonisches JSON, Schlüssel sortiert) + `snapshot_hash = sha256` + alle `cc_exam_session_question`-Zeilen:

| Feld | Inhalt | LB-Entsprechung |
|---|---|---|
| `format_version` | Version des Snapshot-Formats | – |
| `rule` | `uid`, `version`, `status` beim Start, Hash der Regel | `rule_id`, `rule_source_id`, `version` (ES:264-266) |
| `evidence_eligible`, `reduced`, `practice` | Nachweisfähigkeit (§4.1) | `live`, `reduced` (ES:267-268) |
| `timing` | `time_limit_seconds`, `grace_seconds`, `late_tolerance_seconds` | nur `time_limit_seconds` (ES:269); Grace/Kulanz in LB live |
| `passing_rule` | wirksame Regel nach Teilauswahl (§4.1) | `passing` (ES:270) |
| `question_parts` | Frage → Teil | `question_parts` (ES:271) |
| `blueprints` / `variant` | Abschnitte mit Anzahl bzw. Variante | `blueprints`, `paper_number` (ES:273-274) |
| `randomizer` | `seed`, `randomizer_version`, `shuffle_questions`, `shuffle_answers` | – (kein Seed) |
| `scoring` | je Frage `scoring`, `points`; `open_text_in_score` | – |
| `feedback` | §4.5 | – (Feature live geprüft) |
| `attempt_policy` | §4.4 | – |
| `evaluator_version` | Version der Bewertungslogik | – |
| `course_revision_id`, `course_uid` | Kursstand | `course_code` (ES:272) |
| `certifies_objectives`, `competency_validity_days` | §5.3 | – |
| je Frage (Tabelle) | `position`, `question_id`, `revision_id`, `answer_order`, `points`, `scoring`, `section` | `position`, `question_id`, `revision_id`, `answer_order` (ES:298-305) |

**Nicht im Snapshot, aber unveränderlich referenziert:** Fragenrevision, Antwortoptionen und deren Feedbacktexte (R0-06 §5, unveränderlich ab `published`). Medien werden zur Anzeigezeit nach Rechtestatus gefiltert (QP:74); ein später gesperrtes Bild verschwindet aus der Ergebnisansicht, die Bewertung bleibt unberührt.

**LB-Befunde, die der Snapshot behebt:** Kulanz live (ES:56-60); Erklärungsrecht live (ES:834); Themenanalyse im Ergebnis nutzt aktuelle Modulnamen und -zuordnung (ES:837-853) → Vertrag: Themen-/Abschnittsbezeichnungen werden beim Start mit eingefroren.

### 6.4 Zeit, Wiederaufnahme, Offline

- **Serverzeit** ist maßgeblich. Restzeit liefert der Server (`remaining_seconds`, ES:382-384); der Client-Timer ist nur Anzeige.
- **Wiederaufnahme:** Reload, Gerätewechsel oder Verbindungsabbruch → `show()` bzw. erneuter `start()` liefert denselben laufenden Versuch mit gespeicherten Antworten, Markierungen und aufgedeckten Musterantworten (ES:188-194, :362-386). Eine abgelaufene Sitzung wird beim Öffnen ausgewertet (ES:339-342).
- **Offline:** Der Client darf Antworten puffern und mit `age_ms` nachsenden; der Server akzeptiert sie bis Frist + Kulanz, wenn der begrenzte Antwortzeitpunkt vor Frist + Grace liegt (ES:417-428). Designgrenze (ES:50-55): Gerätezeit ist nicht prüfbar; innerhalb der Kulanz kann ein Lernender bis zu `late_tolerance` Sekunden gewinnen. Strenger Modus = Kulanz 0.
- **Mehrere Tabs:** Antworten sind per Zeitstempel geordnet und per Zeilensperre serialisiert; der letzte inhaltliche Stand gewinnt.

### 6.5 Locks und Limits (Übersicht)

| Mechanismus | Zweck | Wert | Quelle |
|---|---|---|---|
| `NamedLock exam_start:<user>` | Doppelklick/paralleler Start | 10 s Wartezeit | ES:43, :183-186, :319-322 |
| `SELECT … FOR UPDATE` Session | jede schreibende Aktion | Transaktion | ES:457, :491, :547, :616 |
| `SELECT … FOR UPDATE` Session-Frage | Antwort speichern | Transaktion | ES:462 |
| CAS `row_version` | zusätzlicher Schutz gegen verlorene Updates | – | R0-06 §4.0 |
| Start-Limit | Missbrauch | 40 je Stunde | ES:47, :196 |
| Antwort-Limit Lernmodus | Missbrauch | 60 je Minute | LS:40, :812 |

### 6.6 Fehlercodes (Vorschlag für den Katalog R0-19)

| CC-Code | LB-Code | Wann | HTTP (Vorschlag) |
|---|---|---|---|
| `cc_exam_not_startable` | `exam_not_startable` | keine gültige Regel, Variante unvollständig, keine Fragen | 422 |
| `cc_exam_start_busy` | `exam_start_busy` | Startsperre belegt | 409 |
| `cc_exam_start_rate_limited` | `exam_start_rate_limited` | Start-Limit | 429 + `retry_after` |
| `cc_exam_attempts_exhausted` | – (neu) | `max_attempts` erreicht oder Cooldown | 422 (+ `retry_after` bei Cooldown) |
| `cc_exam_expired` | `exam_expired` | Antwort nach Frist + Grace; Aktion nach Ablauf | 410 (LB Web 422, App 410) |
| `cc_exam_finished` | `exam_finished` | Versuch nicht mehr `in_progress` | 409 |
| `cc_exam_answer_locked` | `exam_answer_locked` | offene Frage ohne Aufdecken | 422 |
| `cc_exam_invalid_answer` | Text-`RuntimeException` | ungültige/leere Auswahl, falsche Anzahl | 422 |
| `cc_exam_conflict` | – (neu) | CAS fehlgeschlagen nach Wiederholung | 409 |
| `cc_exam_snapshot_invalid` | – (neu; LB `error`) | Snapshot-Hash passt nicht, Regel defekt | 500 + Health |
| `cc_access_denied` | `AccessDenied` | Zugang/Scope/Teilumfang/Drip | 403 |
| `cc_not_found` | `NotFound` | unbekannt **oder fremd** (kein Existenzleck, ES:1034-1041) | 404 |
| `cc_answer_rate_limited` | `answer_rate_limited` | Lernmodus-Limit | 429 |
| `cc_question_token_expired` | `question_token_expired` | Lern-Token älter als 2 h | 422 |

---

## 7. Versionierung und Snapshots

### 7.1 Was wann fixiert wird

| Zeitpunkt | Fixiert | Nicht fixiert | Ort |
|---|---|---|---|
| **Kurs veröffentlichen** | Modul-/Lektions-/Activity-Struktur und Reihenfolge, `required`, Activity-Typ + `contract_version` + `settings_version` + `settings_hash` + `content_hash`, Abschlussregeln, Zugangs-/Drip-/Voraussetzungsregeln, Referenzen auf Prüfungsregel-Versionen, Lernziele | **Fragenrevisionen** (E-50) | `cc_course_revision.structure` + `structure_hash` |
| **Prüfungsregel veröffentlichen** | Bestehensregel, Teile, Blueprints, Zeitlimit, Feedback-, Versuchs-, Misch- und Bewertungseinstellungen | konkrete Fragen (bei Zufallsprüfung) | `cc_exam_rule` (`status = published`, dann nur `retired`) |
| **Versuch starten** | alles aus §6.3 inkl. Fragenrevisionen, Reihenfolgen, Seed, Zeit- und Kulanzwerte | – | `cc_exam_session.snapshot` + Session-Fragen |
| **Lernantwort** | `revision_id`, `algorithm_version`, Kontext | – | `cc_attempt` |
| **Activity abschließen** | `content_hash`, `rule_version`, `reason_code` | – | `cc_activity_progress` |
| **Zertifikat ausstellen** | siehe 7.2 | – | `cc_certificate.snapshot` |

### 7.2 Zertifikats-Snapshot

Pflichtinhalt (Konzept 35.4; R0-06 §4.5):

- `certificate_uid`, `revision_no`, `template_version`, `issued_at` (UTC), `issued_by` (System oder Akteur)
- Person **wie gedruckt** (Anzeigename zum Ausstellungszeitpunkt; eine spätere Profiländerung ändert den Nachweis nicht)
- Kurs: `course_uid`, Titel wie gedruckt, `course_revision_id`
- Nachweis: `evidence_type` (`exam_session` / `course_completion`), Referenz-UUID, Punkte/Maximum, `passed`, `evaluated_at`, Regel-`uid`+`version`, `evidence_eligible`
- belegte Lernziele (`certifies_objectives`) und `valid_until`
- Verifikation: nur `verify_token_hash`; öffentlich angezeigt werden nur die Felder der Privacy-Policy (R0-29)
- `snapshot_hash`

**Regeln:** Ausstellung nur aus nachweisfähigen Quellen (`reduced`/Übung nie). Korrektur = neue Revision mit `supersedes_id` (alte wird `corrected`). Widerruf mit Akteur, Grund und Zeit. Ein Refund ist kein Widerrufsgrund (35.3). Pro-Lizenzablauf ändert nichts an der Verifizierbarkeit (35.4).

### 7.3 Versionskennungen (Übersicht)

| Kennung | Objekt | Regel |
|---|---|---|
| `contract_version` (SemVer) | Activity-Provider | Major-Wechsel nur mit Weiterbetrieb alter Majors für eingefrorene Daten |
| `settings_version` | Activity-Settings | Migration nur für Entwürfe |
| `revision_no` | Kurs, Frage, Zertifikat | neue Revision statt Änderung |
| `version` | Prüfungsregel | `published` unveränderlich, Änderung = neue Version |
| `evaluator_version` | Bewertungslogik | alle referenzierten Versionen bleiben ausführbar (Golden Master) |
| `algorithm_version` | Smarttrainer | wirkt nur auf neue Antworten, keine stille Neubewertung |
| `randomizer_version` | Zufallsableitung | wie `evaluator_version` |
| `format_version` | Snapshot-/Struktur-JSON | Leser für alle alten Formate bleiben |
| `template_version` | Zertifikatsvorlage | im Zertifikat gespeichert |

**Testanker (für R0-05/R1-08/R1-20):** Autor ändert Frage, Regel oder Kurs nach Versuchsstart → Ergebnis unverändert. Kulanz-Einstellung ändert sich während eines Versuchs → wirkt nicht. `snapshot_hash` stimmt nach Auswertung. Doppelter `submit` und paralleler `submit`/Cron → ein Ergebnis. Doppelter `start` mit gleichem `request_key` → ein Versuch. Algorithmuswechsel → alte `cc_question_progress`-Zeilen unverändert.

---

## 8. Entscheidungsbedarf

### 8.1 Neue Entscheidungen (nicht in `ENTSCHEIDUNGEN.md`)

| ID | Frage | Optionen | Empfehlung | Blockiert |
|---|---|---|---|---|
| **D-01** | Hat eine Lektion in R1 genau eine Activity oder mehrere? | (a) 1:1; (b) 1:n mit Slots | **(a) 1:1 in R1**, aber mit eigener `activity_uid`, damit (b) später ohne Fortschrittsmigration möglich ist | R1-04, R1-07 |
| **D-02** | Laufen bewertete Quizze über denselben Versuchsautomaten wie Prüfungen? | (a) ja, `cc_exam_session` mit Modus `quiz`; (b) eigener Quiz-Automat | **(a)**: ein Automat, ein Snapshot-, Idempotenz- und Testpfad; unbewertete Quizze nutzen weiter die Lernmodus-Engine | R1-07, R1-08 |
| **D-03** | Welche Fragetypen ohne LB-Beleg kommen in R1? (Konzept 3.3 nennt Zuordnung, Sortieren, Lückentext als Free; R1-06 verlangt „belegte“ Typen) | (a) alle in R1; (b) R1 nur single/multi/true_false/open_text/flashcard, die anderen Free in R2; (c) Pro | **(b)**: Free bleibt Free, aber nach dem Paritäts-Gate (Kap. 29); Vertrag §3 gilt schon jetzt | R1-06, R0-04 |
| **D-04** | Darf ein Abschluss zurückgenommen werden? (LB: Client kann `completed = false` senden, VS:246-247) | (a) nie; (b) nur `manual_confirm` durch den Lernenden, nachweisbasierte nie, Reset durch Kursverwaltung mit Audit; (c) immer | **(b)** | R1-10 |
| **D-05** | Zählt ein abgebrochener Versuch gegen `max_attempts`? | (a) nie (LB-Verlauf blendet Abbrüche aus); (b) immer, wenn ein Versuchslimit gesetzt ist; (c) Einstellung | **(b)**: sonst kann man per Abbruch die Fragen sichten und neu starten; ohne Limit irrelevant | R1-08, R1-20 |
| **D-06** | Was passiert mit Versuchen **ohne** Zeitlimit, die nie abgegeben werden? (LB: laufen unbegrenzt, sperren Fragen 24 h) | (a) Parität; (b) Einstellung „automatisch abgeben nach *x* Tagen Inaktivität“, Standard aus; (c) fester Ablauf | **(b)** mit Standard aus | R1-08, R1-17 |
| **D-07** | Eigene Tabelle für Kompetenznachweise in R1? (R0-06 E-28: abgeleitet, bis R0-08 entscheidet) | (a) abgeleitet aus Versuchen/Zertifikaten + `competency_validity_days`; (b) Tabelle `cc_competency_evidence` schon in R1 | **(a) in R1**; Tabelle erst mit manuellen/externen Nachweisen (Pro/R2) | R1-10, R0-06 |
| **D-08** | Nachbewertung (Korrektur fehlerhafter Fragen nach Abgabe) in R1? | (a) nicht in R1; (b) Admin-Nachbewertung als neue Auswertungsrevision (`evaluation_no`) mit Audit; (c) automatische Neubewertung bei Fragenkorrektur | **(b)** im Datenvertrag schon in R1 vorsehen (Spalte `evaluation_no`, siehe Δ-4), UI erst mit Gradebook (Pro). **Nie (c)** (28.3) | R1-09, R0-06 |
| **D-09** | `expired` als eigener Endzustand (Konzept 28.3) oder als Kennzeichen an `evaluated` (LB)? | (a) Zustand; (b) `evaluated` + `end_reason = expired` | **(b)**: abgelaufene Versuche werden gewertet (LB-Parität); Konzept 28.3 wird redaktionell auf „`evaluated` (Ende durch Abgabe oder Ablauf)“ angepasst | R1-08, Konzept 28.3 |
| **D-10** | Standard-Abschlussregel für Videos | (a) `manual_confirm` (Parität); (b) `position_ratio ≥ 90 %` | **(a)** als Standard, (b) als Option mit Hinweis „Selbstauskunft des Players“ | R1-07 |
| **D-11** | Teilpunkt-Strategien in R1 | (a) nur `all_or_nothing`; (b) zusätzlich `proportional_penalty`; (c) alle drei | **(b)**: erfüllt Konzept 3.3 („definierte Teilpunktregel“), `per_option` erst nach Didaktik-Review | R1-06, R1-09 |

### 8.2 Technische Deltas zu R0-06 (mit dessen Freigabe einzuarbeiten, keine Produktentscheidung)

| Δ | Änderung | Grund |
|---|---|---|
| Δ-1 | `cc_source_map.uid char(36) NOT NULL` + `UNIQUE uid`; Zeile für **jedes** CPT-Objekt, auch lokal angelegt; optional `copied_from_uid` | DB-erzwungene stabile IDs für Kurs/Modul/Lektion (§1.2) |
| Δ-2 | `cc_exam_session.mode` um `quiz` erweitern; `activity_uid` als Spalte | D-02 |
| Δ-3 | Punkte in Tausendstel: `cc_exam_session.score`/`max_score` → `points_milli`/`max_points_milli`; `cc_exam_session_question.points_milli`, `max_points_milli` | §3.2, kein Gleitkommavergleich |
| Δ-4 | `cc_exam_session.evaluation_no smallint unsigned NOT NULL DEFAULT 1`; Ergebnis-Historie append-only | D-08 |
| Δ-5 | `cc_exam_session.end_reason varchar(16) NULL` (`submitted/expired`); Statuswerte `in_progress/submitted/evaluated/cancelled/broken` | §6.1, D-09 |
| Δ-6 | `cc_activity_progress`: `reason_code varchar(32)`, `rule_version smallint`; Status zusätzlich `not_started` (implizit ohne Zeile) | §5.1 Erklärbarkeit |
| Δ-7 | Lernziel-Objekt (`objective_uid`, Titel, Gültigkeit) als Kurs-Meta in der Kursrevision; keine eigene Tabelle in R1 | §5.3, D-07 |

---

## 9. Nachweis

- Quelle: Clone `…/scratchpad/lb` @ `9f37c77a2a6a3284f3cea700103bf6d3ecc5debd` (`git log -1`), nicht verändert.
- Gelesen: LS (826 Zeilen, vollständig für `next`, `answer`, `applyProgress`, `reviewInterval`, `dueAt`, `isDue`, `spaced`, `stepsSinceLastAnswer`, `score`, `summaryFrom`, `blockedByRunningExam`, `rateLimit`), QP (216 Zeilen, vollständig), ES (1 086 Zeilen: `start`, `show`, `saveAnswer`, `setFlag`, `reveal`, `submit`, `abort`, `expireRunning`, `markBroken`, `evaluate`, `partResults`, `assignParts`, `applicableParts`, `passingText`, `expired`, `assertBeforeDeadline`, `session`, `runningSession`, `resultView`), CR (`questionsForModules` :117-133, `revision` :140-145, `answers` :148-157, `examRule` :191-216), AS (:20-75, :255-370), VS (Fortschritt :236-260), TS (:272-297), `src/Support/DomainException.php` (:16-23).
- Schemas: `schema/v1/questions.schema.json` (vollständig), `common.schema.json` (:11-15), `exam_rules.schema.json`, `courses.schema.json`, `modules.schema.json`, `exam_papers.schema.json`, `video_modules.schema.json`, `praxis_tasks.schema.json`.
- Abgleich mit Inventur A §2.1–2.3, §5 (keine Widersprüche zu den dort genannten Zeilen gefunden), R0-06 §3–§5.
- Neu gegenüber Inventur A: Eigenheiten G-1 bis G-3 (§5.2), Kulanz/Erklärungsrecht/Modulnamen nicht im Snapshot (§6.3), `examRule`-Rückfall auf Entwurfsregeln (§4.1), `reveal` ohne Zeilensperre (§6.2).
- Keine Codeänderung an LearnBoating, keine Implementierung, kein Commit.

## Nachträge aus Folge-Tasks

| Datum | Quelle | Änderung |
|---|---|---|
| 16.11.2026 | R0-22 Δ22-1, E-129 | §4.1: Entwürfe von Prüfungsregeln sind **nie** als Übung erreichbar; Übungsprüfungen sind veröffentlichte Regeln mit `purpose=practice` |
| 16.11.2026 | R0-15 §2.3, E-122/E-123 | Prüfungsregel bekommt `timer_mode` (`strict`/`practice`); Nachteilsausgleich wird beim Versuchsstart in den Snapshot übernommen |
