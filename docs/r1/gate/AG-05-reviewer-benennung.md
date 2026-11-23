# AG-05 · Entscheidungsvorlage: Benennung der Reviewer und Vertretungen

**Stand:** 23.11.2026 · **Zur Entscheidung durch:** die Inhaberin (Auftraggeberin)
**Worum es geht:** Nach **E-204** benennt die Inhaberin für jede Reviewer-Rolle **eine Person und eine Vertretung**; es gilt das Vier-Augen-Prinzip (Autor ≠ Reviewer). Nach **E-193** braucht die Deploy-Freigabe (WordPress.org, später License Captain) eine **Vertretung**, benannt vor R1-GATE. Beides ist Auflage aus dem R0-GATE (AG-05) und Voraussetzung für jeden weiteren Merge in R1 sowie für R1-GATE.
**Grundlage:** E-193, E-204, **E-339**; R0-24 §4.4 (Rollen) und Tabelle §3 (Reviewer je Task); R0-18 §2.5/§4 (CODEOWNERS, Pflicht-Reviewer, Environment `wordpress-org`); R0-16 §3.3; R0-GATE-Protokoll §4 AG-05 und Freigabe-Auflagen.

---

## 1. Ausgangslage

- E-204 (Beschluss): Die Inhaberin übernahm die Reviewer-Rollen **vorerst selbst**; weitere Personen und Vertretungen sollten **vor R1-Start** benannt werden. Das ist nicht geschehen.
- In der TODO tragen **alle 44 abgehakten R1-Aufgaben** den Vermerk „Review durch zweite Person vor Merge ausstehend (AG-05)“.
- **E-339 (23.11.2026, einmalige Ausnahme):** Für den **ersten** R1-Merge (PR hastla007/wp-coursemaster#3) zählt das Code-Review der Koordinations-Sitzung (KI) als zweites Review. **Ab dem nächsten Merge gilt wieder das Vier-Augen-Prinzip mit menschlichem Reviewer; AG-05 bleibt offen.** Die Ausnahme ist nicht wiederholbar und gilt nicht für Tag- oder Deploy-Freigaben.
- Der Code entsteht durch KI-Sitzungen (Spec-Barriere E-327). „Autor“ im Sinne des Vier-Augen-Prinzips ist damit die Sitzung bzw. die Person, die den PR verantwortet. Die Inhaberin ist nicht Autorin des Codes und **kann** daher selbst Reviewerin sein – aber nicht für Änderungen, die sie selbst schreibt oder in Auftrag gibt und zugleich freigibt, ohne dass eine zweite Person sie gesehen hat (Tag-/Deploy-Freigabe: Selbstfreigabe durch den Tag-Ersteller ausgeschlossen, E-193).
- Es gibt im Repository noch **keine** `CODEOWNERS`-Datei; die Pflicht-Reviewer im Environment `wordpress-org` sind noch nicht eingerichtet (R0-18 §4 Schritt 7).

## 2. Rollen (E-204, R0-24 §4.4)

| Kürzel | Rolle | Pflicht bei | Anzahl R1-Aufgaben, die diese Rolle brauchen | Anforderungsprofil (Vorschlag) |
|---|---|---|---:|---|
| **TL** | Technik-Lead (Principal Engineering Lead, technischer Reviewer) | **jeder** Aufgabe | 44 (alle außer R1-24, R1-29) | erfahren in PHP 8.3+, WordPress-Plugin-Entwicklung, Datenbank/Transaktionen; liest PHPUnit-Tests |
| **ARC** | Architektur (WordPress-/LMS-Chefarchitektur) | Datenmodell, öffentliche Verträge, Nebenläufigkeit, Migration | 15 (R1-01, 02, 04, 07, 08, 13, 15, 22, 25, 27, 28, 33, 40, 42, 43) | Datenmodell, API-Design, Migrationen, Locks/Caches |
| **SEC** | Security und Datenschutz | RBAC-, SEC-, PRIV-, MED-, IMP-Tests oder neue Datenkategorien | 22 (u. a. R1-03, 07, 08, 10, 13, 14, 15, 17, 18, 20, 21, 23, 28, 31, 34, 37, 38, 41, 43, 44, 45, 46) | WordPress-Sicherheit (Nonces, Caps, Escaping), OWASP, DSGVO-Grundkenntnisse |
| **A11Y** | Barrierefreiheit und i18n | Oberfläche, A11Y-/I18N-/LOC-/UX-Tests | 10 (R1-05, 16, 18, 19, 24, 26, 29, 32, 35, 46) | WCAG 2.2 AA, Screenreader-Praxis (NVDA/VoiceOver), Übersetzungsprozesse |
| **OPS** | Betrieb und Support | Diagnose, Recovery, Supportaktionen, Lifecycle | 7 (R1-17, 23, 24, 30, 38, 40, 45) | Hosting, Backup/Restore, Cron, Support-Abläufe |
| **PO** | Produkt (LMS-Produktstrategie bzw. Auftraggeberin) | fachliche Abnahme, GM-Abweichungen (E-55), UX-Schwellen (E-166) | 15 (R1-05, 06, 09, 12, 16, 18, 24, 26, 27, 29, 32, 35, 39, 44, 46) + Gate-Abnahme | fachliche Verantwortung; darf Abweichungen freigeben |
| **DEP** | Deploy-Freigabe (E-193) | jeder Tag `v*` → WordPress.org; später License Captain | – | Zugang zum GitHub-Environment `wordpress-org`; nie zugleich Tag-Ersteller |

Ein Review gilt als erledigt, wenn **jede** für die Aufgabe genannte Rolle (Spalte „Reviewer“ in R0-24 §3) den PR freigegeben hat. Eine Person darf mehrere Rollen tragen, aber nie den eigenen PR prüfen.

## 3. Benennung (auszufüllen)

| Rolle | Person | Vertretung | Bestätigt die Person die Übernahme? (Datum) | Interessenkonflikt geprüft (nicht Autor der zu prüfenden PRs) |
|---|---|---|---|---|
| TL Technik-Lead | | | | ☐ |
| ARC Architektur | | | | ☐ |
| SEC Security/Datenschutz | | | | ☐ |
| A11Y Barrierefreiheit/i18n | | | | ☐ |
| OPS Betrieb/Support | | | | ☐ |
| PO Produkt | Inhaberin (Vorschlag) | | | ☐ |
| DEP Deploy-Freigabe | Inhaberin (E-193) | | | ☐ (Vertretung ≠ Tag-Ersteller) |

**Mindestbesetzung (wenn nicht alle Rollen sofort besetzbar sind):** zwei Menschen, die sich gegenseitig vertreten – Person 1 trägt TL + ARC + OPS, Person 2 trägt SEC + A11Y, die Inhaberin trägt PO + DEP; Vertretung für PO/DEP ist Person 1. Für A11Y-Abnahmen in R1-29 zusätzlich die externe A11Y-Fachperson aus dem Usability-Testkit (`R1-29-usability-testkit.md` U-4).

## 4. Varianten zur Entscheidung

| Variante | Beschreibung | Folge für R1-GATE | Vorschlag |
|---|---|---|---|
| **V1 – volle Besetzung** | je Rolle Person + Vertretung nach §3 | Nachholreview (§5) möglich, Gate-Abzeichnung durch technischen Reviewer möglich | **empfohlen** |
| **V2 – Mindestbesetzung** | zwei Personen + Inhaberin nach §3 „Mindestbesetzung“ | wie V1, mit höherer Last je Person | akzeptabel |
| **V3 – keine Benennung** | Inhaberin bleibt einzige Reviewerin | **kein** weiterer Merge mit Vier-Augen-Prinzip möglich (E-339 war einmalig); R1-GATE nicht abzeichenbar (R0-12 §3 (d): technischer Reviewer + Auftraggeber) | nicht zulässig ohne neuen Beschluss |

Entscheidung: ☐ V1 ☐ V2 ☐ anders (neuer Beschluss nötig): ______________________

## 5. Nachholreview der erledigten R1-Aufgaben

Die 44 abgehakten Aufgaben brauchen das ausstehende Review, R1-18 und R1-29 ein Review vor dem Abhaken (zusammen 46). Vorschlag zur Reihenfolge (nach Risiko):

| Paket | Aufgaben | Rollen | Begründung |
|---|---|---|---|
| 1 Sicherheit/Datenschutz | R1-03, R1-14, R1-17, R1-20, R1-28, R1-34, R1-41, R1-45, R1-46 | TL, SEC (+ OPS bei R1-17/R1-45, A11Y/PO bei R1-46) | Rechte, Datenschutz, Uploads, Härtung |
| 2 Datenmodell/Nebenläufigkeit | R1-01, R1-02, R1-04, R1-13, R1-22, R1-25, R1-40, R1-42, R1-43 | TL, ARC (+ SEC/OPS) | Migration, Locks, Cache, Outbox |
| 3 Prüfung/Bewertung | R1-06, R1-07, R1-08, R1-09, R1-10, R1-11, R1-12, R1-27, R1-33, R1-44 | TL, ARC, SEC, PO | Rechenlogik, GM-Fälle (mit Referenzlauf E-328 abgleichen) |
| 4 Oberfläche/Betrieb | R1-05, R1-16, R1-19, R1-21, R1-23, R1-24, R1-26, R1-30, R1-31, R1-32, R1-35, R1-36, R1-37, R1-38, R1-39 | TL, A11Y, OPS, PO | Bedienung, Doku, Distribution |
| 5 Abnahme | R1-15, R1-18, R1-29 | TL, PO, SEC, A11Y | Gate-Nachweise |

Je Aufgabe genügt ein Review des **heutigen** Stands (nicht jedes Commits), mit Ergebnis in der TODO („Review durch <Kürzel> am <Datum>“).

## 6. Technische Umsetzung nach der Entscheidung (Handgriffe, durch die Inhaberin oder auf ihre Anweisung)

1. GitHub-Zugänge der benannten Personen mit Rolle „Write“ (Reviewer) bzw. „Maintain“ (DEP-Vertretung) anlegen.
2. Branch-Schutz für `main`: mindestens **1 Freigabe** durch eine Person ungleich Autor, „Dismiss stale approvals“ an, Status-Checks der CI (`ci`) Pflicht.
3. `CODEOWNERS` anlegen (Pfade → Rollen-Teams, z. B. `src/Privacy/`, `src/Security/` → SEC; `src/Learner/`, `assets/` → A11Y; `tools/qa/free-guard-allow.txt` → TL, R0-18 §2.5). Inhalt beschließt TL.
4. GitHub-Environment `wordpress-org`: Pflicht-Reviewer = Inhaberin **und** DEP-Vertretung; „Prevent self-review“ an (R0-18 §4 Schritt 7).
5. Eintrag in `docs/r0/ENTSCHEIDUNGEN.md` als neuer Beschluss (Benennung, Datum) und Vermerk „AG-05 erledigt“ im R1-GATE-Protokoll.

## 7. Beschluss

| Feld | Eintrag |
|---|---|
| Gewählte Variante | |
| Benennungen wie §3 | ☐ ja |
| Gültig ab | |
| Hinweis E-339 zur Kenntnis genommen (einmalige Ausnahme, keine Wiederholung) | ☐ ja |
| Inhaberin (Name, Datum, Unterschrift) | |

## 8. Wartet auf …

| Punkt | Wartet auf | Zuständig |
|---|---|---|
| Benennung je Rolle + Vertretung (§3), Variante (§4) | Entscheidung | Inhaberin |
| Vertretung der Deploy-Freigabe (E-193) | Benennung | Inhaberin |
| Zusage der benannten Personen | Bestätigung | benannte Personen |
| GitHub-Einrichtung (§6) | Umsetzung | Inhaberin (Repository-Admin) |
| Nachholreview der 44 abgehakten R1-Aufgaben + Review R1-18/R1-29 (§5) | Durchführung | benannte Reviewer |
