# R0-24 · Anforderungs-Traceability und Task-Definition-of-Ready

**Status:** Beschlossen (Auftraggeber/Claude im Auftrag, 16.11.2026) – Beschlüsse eingearbeitet. Keine Implementierung, kein Testlauf, kein Commit; Abhaken nach Review. · **Stand:** 16.11.2026
**Bezug:** TODO R0-24 (Voraussetzung R0-12; DoD: „Jeder FLOW/CORE/PAR/LB-Fall einer Task, einem Test und Gate zugeordnet; Template für Inputs, Dateien, Negativfälle, Abnahme, Reviewer und offene Fragen verbindlich“), Folgetasks R0-27, R0-32, R0-35 (Voraussetzung R0-24); Entscheidung **E-54** (R2-Zuordnungen ohne R2-Task); R0-12 §1 (Pflichtvorlage für die 67 Prozess- und Kernfälle), §2.2 Nr. 7 (Testdubletten), §7 (Delta „R0-24“); Konzept Kap. 28.2 (ADR-001…008), 29, 38 (PAR-01…12), 39 (LB-01…15), 40, 46.
**Quellen (nur gelesen):** `TODO-WETTBEWERBSANALYSE.md` vollständig (R-Plan Z. 7–608, Register Z. 653–919); `konzept.md` Kap. 28.2, 38, 39; `docs/r0/ENTSCHEIDUNGEN.md` (E-01…E-198; E-188…E-194 aus R0-18 und E-195…E-198 aus R0-20 kamen während dieser Arbeit hinzu); `R0-12-tests-gates-backlog.md` vollständig; R0-04 §1, §2, §4, §6; R0-05 §1.21, §6; R0-02-Inventar (Spalten Phase/Edition und Task-ID je INV-Zeile); Kopfzeilen und Gliederung aller übrigen R0-Dokumente einschließlich R0-18 und R0-20 (beide während dieser Arbeit hinzugekommen); Testkataloge in R0-05, R0-07, R0-09, R0-10, R0-13…R0-17, R0-19…R0-23, R0-25, R0-26, R0-37, R0-38.

## Beschlussstand

Alle Entscheidungen sind getroffen (`ENTSCHEIDUNGEN.md`, Beschlussprotokoll und „Von Claude entschieden“, 16.11.2026). Die Fragen D24-1…D24-9 aus §6.1 sind beschlossen bzw. technisch festgelegt (E-199…E-204, ENTSCHEIDUNGEN §31). Eingearbeitet sind:

| Beschluss | Wirkung in diesem Dokument |
|---|---|
| alle E-Nummern beschlossen | DoR-3 erfüllt: alle 46 Tasks R1-01…R1-46 sind **ready** (§4.2); Start erst nach R0-GATE |
| E-182, E-183, **E-239**, **E-168** | Neue DoR-Zeilen R1-43 (nur interne Outbox, E-38), R1-44, R1-45 (Härtung), R1-46 (Text-/Dateiaufgaben) |
| **E-168** | Registerzeilen Z.658, Z.712, Z.750 führen jetzt R1-46 statt R2-02 |
| **E-10**, E-239, E-261 | §3 Nr. 4: Härtung R1-45 statt R4-Pool; BS-27 ist R1-Baustein (§2.1) |
| **E-22**, **E-69** (E-199 entfällt) | §3 Nr. 10/11: Fragetypen R1-06 statt R2-08/R4 |
| **E-25**, E-25a | §3 Nr. 7: Bundle R1 |
| E-16, E-26 | R0-04-Prüfliste: BS-24 −10 (öffentliche FAQ → BS-Z), BS-12 +1, BS-04 +1 |
| E-200, E-201, E-202, E-203, E-204 | ersetzen D24-2…D24-5, D24-7 |
| E-299, E-301 | ADR-009/ADR-010 statt ADR-007/008 in R0-19 (T-1 erledigt); R3-20 Membership-Adapter im TODO – Zeilennummern ab Z. 586 um 4 verschoben (§1.2, §1.4 angepasst) |
| D24-6, D24-8, D24-9 | technisch festgelegt (ENTSCHEIDUNGEN §31): PAR-13…15, keine gestaffelte Readiness, Verweise an Duplikaten |
| E-207 | Präfix `ccap_` (Rolle `ccap_trainer`) |

Die Zeilennummern des Registers wurden auf den aktuellen TODO-Stand (`d5b31f7`, +8 Zeilen ab Z. 439 durch R1-45/R1-46) verschoben; im finalen Konsistenz-Durchgang erneut auf den Stand nach `98bfc38` (+4 Zeilen ab Z. 586 durch R3-20).

**Kernzahlen** (per Skript, §5)

| Größe | Wert |
|---|---:|
| Checkboxen im Register unterhalb des R-Plans (Z. 653–919) | 147 |
| davon bereits abgehakt (Z. 695, 720, 913) | 3 |
| offene Registereinträge | 144 |
| … führend (eigene Abarbeitung über genau eine R-Task) | 102 |
| … Duplikat, auf einen führenden Eintrag zurückgeführt | 40 |
| … obsolet durch Entscheidung (Kap. 29, Kap. 33) | 2 |
| führende Einträge „abgedeckt (Entwurf)“ / „offen“ | 38 / 64 |
| führende R-Tasks, die Registereinträge tragen | 53 |
| Registereinträge mit unbekannter Test-ID / unbekannter R-Task / unbekannter E-Nummer | 0 / 0 / 0 |
| PAR-01…16 und LB-01…15 mit genau einer führenden Task | 31 / 31 |
| R1-Bausteine (BS-01…BS-27) ohne PAR-Zeile | 0 (BS-19, BS-23, BS-24, BS-27 → PAR-13…PAR-16) |
| INV-Zeilen mit „R2“ in Phase/Edition | 42 (davon 3 mit R2-Task, 39 ohne) |
| weitere R2-Zuordnungen aus Entscheidungen ohne R2-Task | 5 (alle per Beschluss nach R1, R3 oder R4 zugeordnet, §3) |
| R1-Tasks mit DoR-Zeile (R1-01…R1-46) | 46 |
| davon „nicht ready“ | 0 (alle E beschlossen, alle führenden R0-Dokumente liegen vor; Start nach R0-GATE) |
| Testdubletten (Race, Webhook, Secret) → führender Test | 8 Duplikate in 6 Gruppen |

**Lesehilfe.** „Z.“ = Zeilennummer in `TODO-WETTBEWERBSANALYSE.md` (Stand nach Commit `98bfc38`; gegenüber `d5b31f7` sind alle Zeilen ab Z. 586 um 4 verschoben, weil R3-20 eingefügt wurde). „Führende Task“ = **die eine** R-Task aus dem R-Plan, mit deren Abnahme der Registereintrag abgehakt wird; Gates (R0-GATE, R1-GATE) zählen als Task. Test-IDs mit „…“ sind lückenlose Bereiche; ein Stern (\*) markiert einen **vorgeschlagenen** Test aus R0-12 §1.3 bzw. eine vorgeschlagene Task (R1-43, R1-44). Ein Kreuz (†) markiert ein R0-Dokument, das noch nicht geschrieben ist. „–“ heißt: nichts vorhanden bzw. nicht anwendbar.

---

## 1. Register-Traceability

### 1.1 Regeln (verbindlich für alle Registereinträge)

| Nr. | Regel |
|---|---|
| **RT-1** | **Eine Reihenfolge.** Abgearbeitet wird nur der R-Plan (Z. 7–608). Ein Registereintrag hat **keine** eigene Position in der Reihenfolge, sondern genau eine führende R-Task. Er wird abgehakt, wenn diese Task mit Nachweis abgenommen ist und der Eintrag in ihrer DoD-Prüfung genannt wurde. |
| **RT-2** | **Spezifikation vs. Nachweis.** Ein Eintrag, der etwas *spezifiziert, entscheidet oder inventarisiert*, führt eine R0-Task. Ein Eintrag, der etwas *umsetzt, testet oder nachweist*, führt die R1/R2/R3/R4-Task, die den Nachweis erbringt. Das abdeckende R0-Dokument steht dann in Spalte „R0-Abdeckung“. |
| **RT-3** | **Duplikate.** Beschreiben zwei Einträge dieselbe Arbeit, führt der Eintrag mit der präziseren DoD (bei Gleichstand der zuerst stehende). Das Duplikat erbt die führende Task und wird gemeinsam mit dem führenden Eintrag abgehakt. Ein Duplikat verweist nie auf ein anderes Duplikat (Skript §5). |
| **RT-4** | **Sammelpunkte** (Z. 736, 757, 758, 811, 812, 874) bündeln mehrere führende Einträge. Sie gelten als Duplikat des ersten genannten Eintrags und werden erst abgehakt, wenn **alle** dort genannten Einträge abgehakt sind. |
| **RT-5** | **Status.** `abgedeckt (Entwurf)` = ein R0-Entwurf deckt den Inhalt vollständig ab; abhakbar erst nach Freigabe des Dokuments und Abnahme der führenden Task. `offen` = Inhalt fehlt ganz oder teilweise (Bemerkung nennt, was fehlt). `obsolet (…)` = durch Entscheidung erledigt, abhakbar mit Verweis. `Duplikat → Z.n` nach RT-3. `erledigt` = bereits abgehakt. |
| **RT-6** | **Neue Einträge** im Register sind nur mit führender Task und Status nach diesem Schema zulässig (Arbeitsregel im TODO, Kap. 46.3). Eine neue R-Task entsteht nur mit Delta-Beleg. |

### 1.2 Zuordnungstabelle (147 Einträge)

| Z. | Abschnitt | Eintrag (gekürzt) | Führende Task | R0-Abdeckung | Test-IDs | Status | Bemerkung |
|---|---|---|---|---|---|---|---|
| 655 | P0 | LearnBoating-Paritätsmatrix abschließen | R0-05 | R0-02 A…E; R0-04 §2, §6; R0-05 §1, §6 | GM-001…GM-190 | abgedeckt (Entwurf) | führend für Z.735, Z.736; Abweichungsliste E-55 freigegeben (R0-GATE-Nachtrag); offen: Referenzlauf E-328/E-56 |
| 656 | P0 | Kurs-/Quiz-Builder im Admin prüfen und vervollständigen | R2-04 | R0-11 §1 (BS-05, F2); R0-22 §3 | – | offen | führend für Z.752; R1-Paritätsteil (Editor, Vorschau, Revisionen) bleibt R1-05/R1-26 (Ausnahmeregel Kap. 29); R2-Katalog fehlt (Gate D-2) |
| 657 | P0 | Persönliche Lernnotizen evaluieren | R2-01 | R0-11 §1 (F2) | – | offen | führend für Z.749; „Lernkarten aus Notizen“ optional in R2-01 |
| 658 | P0 | Aufgabenabgaben als universelle Aktivität spezifizieren | R1-46 | R0-11 §1 (F1, A-19, L-22); R0-05 §2.3 (Fixture v1.1); R0-26 §6 (RK-v1.1) | GM-195, GM-196 | erledigt | führend für Z.712, Z.750; E-168: aus R2-02 nach R1-46 vorgezogen; Praxisaufgaben-Abgrenzung: INV-A-059 exclude, E-22; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 659 | P0 | Kursvorlagen und Demokurse | R2-03 | R0-04 BS-24, BS-29; R0-11 §1 (BS-24, F2) | GM-189, GM-190 | offen | führend für Z.751; E-16 (Mechanik R1), E-102 (Demo erst R2-03) |
| 663 | P1 | Gradebook/Notenbuch | R3-08 | R0-11 §1 (Pro) | – | offen | führend für Sammelpunkt Z.757; Kap. 29 R3 |
| 664 | P1 | Tutorbewertung | R3-08 | R0-11 §1 (Pro) | – | offen | setzt R1-46 voraus (E-168); E-99 (Tutor-Rolle `ccap_trainer`) |
| 665 | P1 | Frontend-Kurs-Builder für Trainer | R3-08 | R0-11 §1 (Pro) | – | offen | Kap. 29 R3 |
| 666 | P1 | Mehrere Trainer je Kurs | R3-08 | R0-07 §2, §3 (`ccap_course_staff`); R0-04 BS-36 | RBAC-024, RBAC-025 | offen | R1-Basis Objekt-Scope je Kurs in R1-03 (E-78, E-80); Pro-Ausbau R3-08 |
| 667 | P1 | Visuellen Zertifikatsdesigner prüfen | R3-08 | R0-11 §1; R0-23 §5 | CERT-008* | offen | E-94, E-96, E-110, E-127; Free-Basisnachweis R1-44 |
| 668 | P1 | Kompetenzmatrix/Lernziele | R1-10 | R0-08 §5 | GM-075, GM-076 | Duplikat → Z.710 | Auswertung der drei Dimensionen; Matrix-Editor ohne Task → R4-01 |
| 669 | P1 | Kursdiskussionen und Trainerfragen | R4-01 | – | – | offen | kein Task R1–R3; Vorschlag R4-Nachfragepool |
| 670 | P1 | Interaktive Medienaktivitäten | R4-01 | R0-08 §3; R0-15 K-12 | – | offen | Zuordnen/Sortieren → §3 Nr. 10 (E-69); Hotspots, Video-Fragen, Szenarien R4 |
| 671 | P1 | Kurs-Bundles und Mitgliedschaften | R3-08 | R0-11 §1 („Abos/Mitgliedschaften/Bundles mit Verkauf“, Pro); R0-04 W-07 | GM-198 | offen | Bundle R1-04/R1-13 (E-25, E-25a); Mitgliedschaft = Grant-Quelle, Adapter R3 (E-203) |
| 672 | P1 | Kursbewertungen | R3-07 | R0-32 §5 | – | Duplikat → Z.778 | E-41 |
| 676 | P2 | Gamification | R4-01 | – | – | offen | führend für Sammelpunkt Z.758 |
| 677 | P2 | SCORM/xAPI/H5P/LTI | R4-01 | R0-04 W-03 (Client-API R4) | – | offen | R4-Nachfragepool |
| 678 | P2 | Erweiterte Kohorten-/Coaching-Funktionen | R4-01 | – | – | offen | R4-Nachfragepool |
| 682 | Markt | Prüfungen | R4-01 | R0-11 §1 | – | Duplikat → Z.686 | R1-Anteile (Quiz, Gruppen, Zertifikat Basis) sind in R0-11 zugeordnet |
| 683 | Markt | Autorenfunktionen | R4-01 | R0-11 §1 | – | Duplikat → Z.686 | Frontend-Builder, Mehrtrainer → Z.665, Z.666 |
| 684 | Markt | Lernerfahrung | R4-01 | R0-11 §1 | – | Duplikat → Z.686 | Notizen → Z.657; Karteikarten R1-11 |
| 685 | Markt | Kernumfang | R0-11 | R0-11 §1; R0-14; R0-15; R0-19 | SA-001* | abgedeckt (Entwurf) | E-53, E-93…E-102; Stand 24.11.2026: Bedienbarkeit mit Personen offen (R1-29) |
| 686 | Markt | Anforderungsmatrix | R4-01 | R0-11 §1 (Edition/Phase; Nutzen/Aufwand fehlen) | – | offen | führend für Z.682–684; Nutzerproblem/Nutzen/Aufwand als R4-01-Evidenz; braucht kaufmännische Bewertung |
| 687 | Markt | Praxis-Benchmark | R1-29 | R0-26 §1 | GM-187 | Duplikat → Z.714 | Referenzkurs mit Aufgabe schon in R1 (E-168, R1-46) |
| 691 | Tore | Keine Konkurrenzfunktion blind übernehmen | R0-24 | R0-24 §4.1 (DoR-Feld 1) | – | erledigt | Regel wirkt über DoR-1/4/11 und Gate D-7; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 692 | Tore | Dopplungen vermeiden | R0-24 | R0-24 §1 | – | erledigt | LB über R0-02/R0-04, Suite über R0-32/R0-36, Dubletten in §1 und §1.3; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 693 | Tore | Free/Pro-Grenzen einzeln entscheiden | R0-11 | R0-11 §1 | – | erledigt | abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 694 | Tore | P0 bedeutet Prüfung/Planung, nicht automatisch v1.0-Pflicht | R0-04 | R0-04 §1 Z-8 | – | erledigt | durch Kap. 29 und Z.720 entschieden (vorher obsolet); abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 695 | Tore | Aufnahme ins Konzept freigegeben | – | Konzept Kap. 27 | – | erledigt |  |
| 701 | K28 Arch. | ADR-001 bis ADR-008 verfassen | R0-06 | R0-06 (ADR-001…003, 006 Teil); R0-08 (ADR-004, 005); R0-19 §5.4 (ADR-006); R0-11 §1 (Lizenzende); R0-07, R0-17 (Rollen, Datenschutz) | – | erledigt | Nummernkonflikt gelöst (E-299: ADR-009/010 in R0-19); ADR-007 im Kopf von R0-39, ADR-008 im Kopf von R0-07 (T-1); E-47…E-52; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 702 | K28 Arch. | Unveränderliche Prüfungs-Snapshots nachweisen | R1-08 | R0-08 §6, §7; R0-23 §2 | GM-027…GM-034, GM-052 | erledigt | E-50; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 703 | K28 Arch. | Zustandsmaschinen spezifizieren | R0-08 | R0-08 §6 (Versuch); R0-22 §2 (Content); R0-09 §2, §4 (Einschreibung) | GRANT-001, PUB-001 | erledigt | Automat der Aufgabenabgabe mit R1-46 (Z.658, E-168); abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 704 | K28 Arch. | Idempotenz- und Wiederanlauftests | R1-42 | R0-21 §2, §5; R0-09 §6; R0-19 §5.4 | CON-001…CON-048, GRANT-005, CT-07, REC-016 | erledigt | Outbox-Teil braucht R1-43 (E-182); abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 705 | K28 Arch. | Golden-Master-Tests für Smarttrainer | R1-12 | R0-05 §1.6; R0-08 §5.2 | GM-065…GM-074 | abgedeckt (Entwurf) | Referenzlauf E-56; Stand 24.11.2026: GM-065…074 grün, Referenzlauf E-328 offen |
| 706 | K28 Arch. | Datenmodell-/Upgrade-Probe | R1-23 | R0-06 §6; R0-16 §2, §4; R0-37 §4, §7; R0-14 §2 | REC-001…REC-025, MIG-001…MIG-026 | abgedeckt (Entwurf) | Stand 24.11.2026: Rollback/Downgrade REC-009/025 (R1-23 O-6) und Messung R-M offen |
| 710 | K28 Lern/UX | Drei getrennte Fortschrittsdimensionen | R1-10 | R0-08 §5 | GM-066, GM-075, GM-076, CRT-064 | erledigt | führend für Z.668; E-70, E-73; Stand 24.11.2026: REST und UI für alle drei Dimensionen (`docs/r1/SET-CC-kompetenz.md`); Z.668 bleibt offen für den Matrix-Editor (R4-01) |
| 711 | K28 Lern/UX | Abschluss- und Bewertungsregeln je Activity | R1-09 | R0-08 §2.3, §4 | GM-017…GM-031 | erledigt | E-74, E-76, E-77; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 712 | K28 Lern/UX | Aufgaben-Workflow und sichere Uploads | R1-46 | R0-11 §1 (F1) | – | erledigt | Detail-DoD (draft/submitted/returned/graded, MIME, private Ablage) übernehmen; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 713 | K28 Lern/UX | Kompetenzzuordnung | R1-10 | R0-08 §5.3 | CRT-064 | erledigt | E-73 (abgeleitet, keine eigene Tabelle); abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 714 | K28 Lern/UX | Drei neutrale Autorentests | R1-29 | R0-26 §1, §5 | UX-001…UX-015 | abgedeckt (Entwurf) | führend für Z.687; E-161…E-171; Stand 24.11.2026: vorbereitet, Durchführung offen (E-341) |
| 715 | K28 Lern/UX | Lernenden-E2E und Accessibility | R1-18 | R0-15 §2; R0-26 §1–§5; R0-11 §3 Flow 2 | A11Y-001…A11Y-010, UX-050 | abgedeckt (Entwurf) | Gate B-4, C-10; Stand 24.11.2026: Firefox/WebKit (UI-F7) und Screenreader-Durchläufe offen |
| 719 | K28 Produkt | Release-Gates A–D verbindlich abnehmen | R0-12 | R0-12 §3 | – | abgedeckt (Entwurf) | Kriterien A-1…D-7; E-184; Abhaken je Gate; Stand 24.11.2026: Gate A abgenommen; B/C offen (E-368) |
| 720 | K28 Produkt | P0 aufteilen | – | Konzept Kap. 29 | – | erledigt |  |
| 721 | K28 Produkt | Free-/Pro-Grenzen abgleichen | R0-11 | R0-11 §1; R0-04 §3 | SA-001*, CT-09 | erledigt | führend für Z.693; E-53, E-93…E-102; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 722 | K28 Produkt | Suite-Contract-Tests | R3-12 | R0-19 §9 | CT-01…CT-12 | abgedeckt (Entwurf) | COMMERCE-/SUITEFLOW-Kataloge E-186 |
| 723 | K28 Produkt | Messkonzept erstellen | R0-26 | R0-26 §7; R0-14 §2; R0-13 §2 | – | offen | Teil abgedeckt (Veröffentlichung, Abschluss); Konversion, Supportlast, Job-Wiederherstellung ohne Baseline; R3-Teil SEAM-109 |
| 724 | K28 Produkt | Verbleibende Produktfragen entscheiden | R0-11 | R0-11 §0; ENTSCHEIDUNGEN | – | offen | E-07, E-49, E-78, E-80, E-94, E-96, E-99 |
| 734 | K29 R0 | Alle fachneutralen LearnBoating-Funktionen vollständig inventarisieren… | R0-02 | R0-02 A…E | – | erledigt | Gate A-1; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 735 | K29 R0 | Paritätsmatrix mit Quellreferenz, Zielkomponente, neutralem Testfall u… | R0-05 | R0-05 §6 | – | Duplikat → Z.655 |  |
| 736 | K29 R0 | Golden-Master-Tests, Lizenz-/Abhängigkeitsinventar und Architektur-ADR… | R0-05 | R0-05; R0-01; R0-06 | – | Duplikat → Z.655 | ADR-Teil → Z.701; Lizenzteil → E-01 |
| 737 | K29 R0 | Gate A abnehmen | R0-GATE | R0-12 §3.1 | – | erledigt | Gate A freigegeben 16.11.2026, A-2 R1-Grenze eingefroren; R1-Gate-Teil bleibt bei Z.907; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 741 | K29 R1 | Fachneutralen Kurs-/Lernfluss einschließlich notwendigem Basis-Kurs-/Q… | R1-18 | R0-12 §1 | – | Duplikat → Z.868 |  |
| 742 | K29 R1 | Sämtliche fachneutralen Lernfunktionen aus der freigegebenen Paritätsm… | R1-18 | R0-05 §6 | – | Duplikat → Z.868 |  |
| 743 | K29 R1 | Inhaltsversionierung, Prüfungs-Snapshots, Rechte, Datenschutz, Upgrade… | R1-18 | R0-08 §7; R0-23; R0-07; R0-17; R0-16 | – | Duplikat → Z.868 |  |
| 744 | K29 R1 | 100 % der fachneutralen Paritätsmatrix abnehmen; Gates B und C bestehe… | R1-GATE | R0-12 §3.2, §3.3 | – | abgedeckt (Entwurf) | führend für Z.745; Kriterien B-1…B-8, C-1…C-16; E-184 |
| 745 | K29 R1 | R1 ausdrücklich freigeben | R1-GATE | R0-12 §3 | – | Duplikat → Z.744 |  |
| 749 | K29 R2 | Persönliche Lernnotizen, Suche/Export und optional Lernkarten aus Noti… | R2-01 | R0-11 §1 | – | Duplikat → Z.657 |  |
| 750 | K29 R2 | Text-/Datei-Aufgabenabgaben mit sicherem Upload und Basisfeedback. | R1-46 | R0-11 §1 | – | erledigt | abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 751 | K29 R2 | Neutrale Kursvorlagen, Demokurse und Duplizierungs-Komfortfunktionen. | R2-03 | R0-11 §1 | – | Duplikat → Z.659 |  |
| 752 | K29 R2 | Zusätzliche Kurs-/Quiz-Builder-UX, soweit nicht bereits für R1-Parität… | R2-04 | R0-11 §1 | – | Duplikat → Z.656 |  |
| 753 | K29 R2 | Jedes R2-Feature einzeln auf Nutzen, Free-/Pro-Grenzen, UX, Sicherheit… | R2-05 | R0-12 §3.4 | – | Duplikat → Z.869 |  |
| 757 | K29 R3/R4 | R3 | R3-08 | R0-11 §1 | – | Duplikat → Z.663 | Sammelpunkt für Z.663–667 und die Adapter (SUITE-1xx, R3-FIRST-1xx) |
| 758 | K29 R3/R4 | R4 | R4-01 | – | – | Duplikat → Z.676 | Sammelpunkt für Z.676–678 |
| 768 | K30 R0 | SUITE-001 | R0-36 | R0-32 §3–§5; R0-36 §2–§6 | – | offen | 7 von 8 Partnern mit Commit belegt; Captain Pro nicht zugänglich (R0-36 §6, AG-01) |
| 769 | K30 R0 | SUITE-002 | R0-32 | R0-32 §6, §7; R0-36; R0-17 §1 | – | offen | Hoheit verteilt auf R0-32, R0-36, R0-39, R0-40 §1.1; offen: zusammengeführte Matrix, AG-03/AG-04 |
| 770 | K30 R0 | SUITE-003 | R0-19 | R0-19 §3, §5 | CT-01…CT-12 | erledigt | E-38, E-118, E-119; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 771 | K30 R0 | SUITE-004 | R0-34 | R0-19 §6; R0-16 §1; R0-13 §2 | – | erledigt | R0-34 §1.3, §2–§5; R1-Teil R1-38; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 772 | K30 R0 | SUITE-005 | R0-39 | R0-36 §5, §6; R0-11 §1 | – | offen | R0-39 liegt vor; E-09, E-42; offen: Captain-Pro-Abgleich (AG-01), Produktanlage (AG-02) |
| 776 | K30 R3 | SUITE-101 Workflow Captain | R3-07 | R0-32 §4; R0-19 §5 | CT-06, CT-07, CT-12 | offen | führend für Z.812, Z.874; E-39, E-40, E-120 |
| 777 | K30 R3 | SUITE-102 Form Captain | R3-07 | R0-32 §3 | – | offen | führend für Z.813; E-40 |
| 778 | K30 R3 | SUITE-103 Review Captain | R3-07 | R0-32 §5 | – | offen | führend für Z.672; E-41 |
| 779 | K30 R3 | SUITE-104 Voucher Captain | R3-04 | R0-36 §3 | – | Duplikat → Z.841 |  |
| 780 | K30 R3 | SUITE-105 Pass Captain | R3-07 | R0-36 §2 | – | offen | Check-in ist keine Completion (ENTSCHEIDUNGEN §7) |
| 781 | K30 R3 | SUITE-106 Affiliate Captain | R3-07 | R0-36 §4 | – | offen | E-37; Doppelprovision → R3-19 |
| 782 | K30 R3 | SUITE-107 License Captain | R3-18 | R0-36 §5 | – | offen | R0-39 liegt inzwischen vor; E-09 |
| 783 | K30 R3 | SUITE-108 Captain Pro | R3-08 | R0-36 §6 | – | offen | E-42; Quelle nicht zugänglich |
| 784 | K30 R3 | SUITE-109 Admin-Diagnose | R3-16 | R0-19 §6; R0-13 §2 | SUP-024, SUP-029 | offen | führend für Z.814, Z.876; R1 nur lesend (E-185, R1-38) |
| 785 | K30 R3 | SUITE-110 Rezeptvorlagen | R3-07 | – | – | offen | reproduzierbare Rezept-E2E fehlen (SUITEFLOW-08) |
| 786 | K30 R3 | SUITE-111 Dokumentationspaket | R3-09 | R0-19 | – | offen | führend für Z.819 |
| 790 | K30 R4 | SUITE-201 | R4-01 | R0-25 §1 | – | offen | E-07 (Single-Site) |
| 791 | K30 R4 | SUITE-202 | R4-01 | – | – | offen | R4-Nachfragepool |
| 801 | K31 R0 | SEAM-001 | R0-09 | R0-09 §2, §3 | GRANT-001…GRANT-022 | erledigt | gemeinsam mit Z.888; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 802 | K31 R0 | SEAM-002 | R0-40 | R0-09 §2, §5; R0-19 §5.4 | GRANT-020, GRANT-024 | offen | Voucher-Saga R0-40 §3 vollständig; offen: Pass-/Ticket-Kompensation (R3.4) |
| 803 | K31 R0 | SEAM-003 | R0-08 | R0-08 §5.3; ENTSCHEIDUNGEN §7 | CRT-064 | erledigt | Kompetenz nie aus Zahlung oder Check-in; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 804 | K31 R0 | SEAM-004 | R0-30 | R0-11 §2, §3 | – | offen | Course-Seite R0-30, E-202, R0-34 §3.3; offen: suiteweite Konventionen (R3-16) |
| 805 | K31 R0 | SEAM-005 | R0-19 | R0-19 §1; R0-07; R0-17 §5; R0-16 §2 | – | erledigt | Log-Retention E-44, E-116; juristische Bestätigung AG-07 bei Z.894; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 806 | K31 R0 | SEAM-006 | R0-09 | R0-09 §2 (G-06), §8 | – | erledigt | Gastkonto E-30; Pending-Timeout E-201/E-277; Navigationsmuster E-202; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 810 | K31 R3 | SEAM-101 | R1-13 | R0-09 §2, §3 | GRANT-001…GRANT-022 | abgedeckt (Entwurf) | neutraler Kern R1 (BS-12); Adapterquellen R3-02, R3-04 |
| 811 | K31 R3 | SEAM-102 | R3-03 | R0-09 §5 | – | Duplikat → Z.837 | Voucher-Teil → Z.842, Pass-Teil → Z.780 |
| 812 | K31 R3 | SEAM-103 | R3-07 | R0-32 §4, §5 | – | Duplikat → Z.776 | Review-Teil → Z.778 |
| 813 | K31 R3 | SEAM-104 | R3-07 | R0-32 §3 | – | Duplikat → Z.777 |  |
| 814 | K31 R3 | SEAM-105 | R3-16 | R0-19 §6 | – | Duplikat → Z.784 |  |
| 815 | K31 R3 | SEAM-106 | R3-13 | R0-11 §3 | – | Duplikat → Z.877 |  |
| 816 | K31 R3 | SEAM-107 | R3-13 | R0-11 §3 | – | Duplikat → Z.877 |  |
| 817 | K31 R3 | SEAM-108 | R3-11 | R0-16; R0-37 §6; R0-20 §1 | – | offen | Partner- und Mischversionsmatrix fehlt |
| 818 | K31 R3 | SEAM-109 | R3-10 | R0-13 §2; R0-26 §7 | – | offen |  |
| 819 | K31 R3 | SEAM-110 | R3-09 | R0-19 | – | Duplikat → Z.786 |  |
| 829 | K32 R0 | R3-FIRST-001 | R3-01 | R0-09 §2, §5; R0-04 §3 (K-01…K-04) | GRANT-004, GRANT-023 | offen | R0-40 §2.2, §4.2; offen: Variations-/Paket-Mapping, Code-Gegenprüfung Z.893 (E-186) |
| 830 | K32 R0 | R3-FIRST-002 | R0-40 | R0-36 §3 | – | offen | R0-40 §3 gegen `wp-couponmaster@2d84943`; offen: erneute Prüfung vor R3-04 (Z.893) |
| 831 | K32 R0 | R3-FIRST-003 | R0-09 | R0-09 §1, §5, §6 | ID-001…ID-015, GRANT-023…GRANT-026, CON-010 | erledigt | E-29, E-30, E-35, E-36, E-275, E-295, E-314 beschlossen; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 832 | K32 R0 | R3-FIRST-004 | R0-20 | R0-20 §1, §2.11 (Entwurf) | KOMP-100, KOMP-101 | offen | Teil abgedeckt: Woo-Koexistenz (HPOS an/aus, Block-Checkout); Adapter-Matrix Classic/Blocks und Pluginversionen offen bis R3-01 |
| 836 | K32 R3.1 | R3-FIRST-101 | R3-02 | R0-09; R0-04 BS-31 | GRANT-004, GRANT-005 | offen | E-34, E-53 |
| 837 | K32 R3.1 | R3-FIRST-102 | R3-03 | R0-09 §5 | GRANT-023, GRANT-025, GRANT-029, SEAT-020 | offen | führend für Z.811 |
| 841 | K32 R3.2 | R3-FIRST-103 | R3-04 | R0-36 §3; R0-04 BS-32 | GRANT-024, GRANT-026, SEC-024 | offen | führend für Z.779; E-31, E-35 |
| 842 | K32 R3.2 | R3-FIRST-104 | R3-05 | R0-09 §6 | GRANT-020, GRANT-026, SEC-025 | offen | E-36 |
| 846 | K32 R3.3 | R3-FIRST-105 | R3-06 | ENTSCHEIDUNGEN §7 | – | offen | „Voucher allein“ entfällt (ENTSCHEIDUNGEN §7); Rest offen |
| 847 | K32 R3.3 | R3-FIRST-106 | R3-06 | R0-12 §3.4 (D-4) | – | offen |  |
| 848 | K32 R3.3 | R3-FIRST-107 | R3-10 | R0-19 §6; R0-13 | SUP-024, SUP-029 | offen |  |
| 849 | K32 R3.3 | R3-FIRST-108 | R3-07 | – | – | offen | Regel für die R3.4-Reihenfolge |
| 859 | K34 R0 | FLOW-001 | R0-12 | R0-41 §1; R0-12 §1.1; R0-07; R0-08; R0-09; R0-13; R0-19 | – | erledigt | R0-41 §1: 55 Fälle mit Owner, Trigger, Vorbedingung, Events, UI, Retry, Audit, Release, Spezifikation und Test; COMMERCE/SUITEFLOW R3 (E-186); abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 860 | K34 R0 | FLOW-002 | R0-12 | R0-41 §2; R0-12 §1 | – | erledigt | T-4 in R0-41 §2 erfüllt (neun Dimensionen je Fall); 18 Lücken-Zellen in R0-41 §4.2; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 861 | K34 R0 | FLOW-003 | R0-GATE | ENTSCHEIDUNGEN | – | erledigt | führend für Z.894 (Fachentscheidungen); juristische Bestätigung offen bei Z.894; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 862 | K34 R0 | FLOW-004 | R0-12 | R0-12 §1.1; R0-04 §2 | – | erledigt | abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 863 | K34 R0 | FLOW-005 | R0-34 | R0-19 §5, §6 | CT-08, CT-09 | erledigt | R0-34 §1.3, §2; E-185; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 864 | K34 R0 | FLOW-006 | R4-01 | Konzept Kap. 33 | – | erledigt | Dashboard zurückgestellt; Notiz in Kap. 33 (vorher obsolet); abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 868 | K34 R1/R2 | FLOW-101 | R1-18 | R0-12 §1; R0-05 §6 | GM-001…GM-190 | abgedeckt (Entwurf) | führend für Z.741–743 |
| 869 | K34 R1/R2 | FLOW-102 | R2-05 | R0-04 §1 Z-8; R0-11 §1 (F2) | – | offen | führend für Z.753 |
| 873 | K34 R3 | FLOW-201 | R3-06 | R0-12 §1.1 (COMMERCE) | GRANT-023…GRANT-026 | offen | Kataloge Eingangsbedingung R3-01 (E-186) |
| 874 | K34 R3 | FLOW-202 | R3-07 | R0-12 §1.1 (SUITEFLOW) | – | Duplikat → Z.776 | Sammelpunkt; je Connector Z.776–783 |
| 875 | K34 R3 | FLOW-203 | R3-09 | R0-12 §1.1 (OPS) | SA-001* | offen | Standalone-Teil R1-18 |
| 876 | K34 R3 | FLOW-204 | R3-16 | R0-19 §6 | – | Duplikat → Z.784 |  |
| 877 | K34 R3 | FLOW-205 | R3-13 | R0-11 §3; R0-26 | – | offen | führend für Z.815, Z.816 |
| 885 | K35 | CORE-SPEC-001 | R0-07 | R0-07 §2, §3 | RBAC-001…RBAC-096 | erledigt | gemeinsam mit Z.900; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 886 | K35 | CORE-SPEC-002 | R0-09 | R0-09 §1 | ID-001…ID-015 | erledigt | E-30: Gast-Claim aus Kauf erst nach R3.1; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 887 | K35 | CORE-SPEC-003 | R0-09 | R0-09 §4 | SEAT-001…SEAT-022 | erledigt | E-28, E-115; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 888 | K35 | CORE-SPEC-004 | R0-09 | R0-09 §2, §3 | GRANT-001…GRANT-029 | erledigt | führend für Z.801; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 889 | K35 | CORE-SPEC-005 | R0-09 | R0-09 §5 | GRANT-023, GRANT-027, GRANT-028 | abgedeckt (Entwurf) | kaufmännische Grundsätze beschlossen (E-29, E-32, E-35); offen: rechtliche Freigabe der Matrix |
| 890 | K35 | CORE-SPEC-006 | R0-29 | R0-08 §7.2; R0-23 §5 | RBAC-060…RBAC-066, CERT-001* | erledigt | R0-29 §2, §3; E-94, E-96, E-97, E-183; Fristen AG-07; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 891 | K35 | CORE-SPEC-007 | R0-12 | R0-41 §3; R0-12 §1.2 | ID-001, GRANT-010 | erledigt | R0-41 §3 (Vorbedingungen, DB-Zustand, Auditspur je Fall); CORE-10 Free-Teil als Integrationstest; Woo/Voucher R3 (FIN, E-186); abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 892 | K35 | CORE-SPEC-008 | R0-12 | R0-12 §1 | – | erledigt | abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 893 | K35 | CORE-SPEC-009 | R3-01 | R0-36 §3 | – | offen | E-186 |
| 894 | K35 | CORE-SPEC-010 | R0-GATE | ENTSCHEIDUNGEN | – | offen | Fachentscheidungen erledigt (Z.861); offen: juristische Bestätigung der Fristen (AG-07, Eingang R1-GATE, E-329) |
| 900 | K36–38 | R0-RBAC-001 | R0-07 | R0-07 §2, §3 | RBAC-001…RBAC-096 | erledigt | führend für Z.885; E-78…E-84; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 901 | K36–38 | R0-RBAC-002 | R0-07 | R0-07 §4, §5 | RBAC-040, RBAC-093 | erledigt | RBAC-093 → T-2; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 902 | K36–38 | R0-DATA-001 | R0-06 | R0-06 §3, §4 | – | erledigt | E-47, E-48; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 903 | K36–38 | R0-DATA-002 | R0-06 | R0-06 §4.8; R0-19 §1, §5; R0-21; R0-37; R0-38; R0-17 §5 | CON-001, MIG-001, BIG-001 | erledigt | abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 904 | K36–38 | R0-PAR-001 | R0-02 | R0-02 A…E (835 Einträge) | – | erledigt | führend für Z.734, Z.915; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 905 | K36–38 | R0-PAR-002 | R0-03 | R0-02 README (unknown = 0) | – | erledigt | abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 906 | K36–38 | R0-PAR-003 | R0-05 | R0-05 §6 | GM-001…GM-190 | erledigt | abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 907 | K36–38 | R0-GATE-001 | R0-GATE | R0-12 §3.1 | – | offen | führend für Z.737, Z.919 (beide erledigt); Gate-A-Teil erfüllt (Freigabe 16.11.2026); offen: Gates B/C nach Free-Parität (R1-GATE, E-368) |
| 913 | K39 | LB-AUDIT-001 – Einstieg und Quelllandkarte | – | R0-01 | – | erledigt |  |
| 914 | K39 | LB-AUDIT-002 – Codeevidenz | R0-24 | R0-24 §2.2; R0-02 | – | abgedeckt (Entwurf) | LB → INV-ID mit Quellpfad im Commit `9f37c77`; offen: Kennzeichnung unzutreffender/geplanter README-Aussagen |
| 915 | K39 | LB-AUDIT-003 – Vollständigkeit | R0-02 | R0-02 | – | erledigt | gemeinsam mit Z.904; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 916 | K39 | LB-AUDIT-004 – Neutralität | R0-03 | R0-02 README | – | offen | Klassifikation erledigt (Z.905); offen: Aufwand je Codefunktion fehlt |
| 917 | K39 | LB-AUDIT-005 – R1/R3-Schnitt | R0-04 | R0-04 §2, §3 | – | erledigt | abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 918 | K39 | LB-AUDIT-006 – Golden Master | R0-05 | R0-05 §6; R0-41 §5 | – | erledigt | GM-118/125/128 vollständig (Medien-/Blueprint-Import, R1-18 h1), GM-136 Vollvolumen gelaufen, R1-18 C-3 aktualisiert; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
| 919 | K39 | LB-AUDIT-007 – Gate A | R0-GATE | R0-12 §3.1 | – | erledigt | Gate A-1 vor der Freigabe; abgehakt 24.11.2026 mit Nachweis (TODO-Zeile) |
### 1.3 Testdubletten → führender Test (R0-12 §2.2 Nr. 7)

Führend ist der Test mit der präzisesten Erwartung. Die übrigen bleiben als Verweis im Katalog stehen („wie …“) und zählen bei der Gate-Messung nicht doppelt (R0-12 §3 Regel f).

| Gruppe | Führender Test | Ziel-Task | Duplikate (Verweis) | Befund |
|---|---|---|---|---|
| Race: letzter Platz | CON-001 | R1-42 | SEAT-003, SEC-027 | identisch (50 Joins, 1 Platz); CON-001 nennt zusätzlich Fehlercodes |
| Race: gleiches Konto parallel | CON-002 | R1-42 | SEAT-004 | identisch |
| Race: persönliche Einladung | CON-003 | R1-42 | SEAT-005 | identisch |
| Race: Lock nicht verfügbar | CON-006 | R1-42 | SEAT-006 (Teil `GET_LOCK = NULL`) | SEAT-006 behält den Teil „Lock belegt, Timeout 0 → `ccap_group_busy`“ als eigenen Fall |
| Webhook-Signatur, Replay | CT-11 | R3-12 | RBAC-093, SEC-026 | **Widerspruch:** Replay-Antwort CT-11 und R0-19 R-51 `202 duplicate`, RBAC-093 und SEC-026 `200`. Außerdem ist die Route R3 (R0-19 R-51), RBAC-093 trägt aber Ziel R1-03 → Korrektur T-2 (§6.2) |
| Secrets in Ausgaben | REC-022 | R1-23 | SEC-036 | identisch („wie REC-022“) |

### 1.4 Führende Task → Registereinträge (Abarbeitungssicht)

So sieht die Reihenfolge aus Sicht des R-Plans aus: Wer eine Task bearbeitet, prüft in ihrer DoD die hier genannten Zeilen mit. Sortiert nach Phase und Nummer.

| Führende Task | führende Einträge (Z.) | Duplikate (Z.) | Summe |
|---|---|---|---:|
| R0-03 | 916 | – | 1 |
| R0-05 | 655 | 735, 736 | 3 |
| R0-09 | 889 | – | 1 |
| R0-11 | 685, 724 | – | 2 |
| R0-12 | 719 | – | 1 |
| R0-20 | 832 | – | 1 |
| R0-24 | 914 | – | 1 |
| R0-26 | 723 | – | 1 |
| R0-30 | 804 | – | 1 |
| R0-32 | 769 | – | 1 |
| R0-36 | 768 | – | 1 |
| R0-39 | 772 | – | 1 |
| R0-40 | 802, 830 | – | 2 |
| R0-GATE | 894, 907 | – | 2 |
| R1-10 | 710 | 668 | 2 |
| R1-12 | 705 | – | 1 |
| R1-13 | 810 | – | 1 |
| R1-18 | 715, 868 | 741, 742, 743 | 5 |
| R1-23 | 706 | – | 1 |
| R1-29 | 714 | 687 | 2 |
| R1-GATE | 744 | 745 | 2 |
| R2-01 | 657 | 749 | 2 |
| R2-03 | 659 | 751 | 2 |
| R2-04 | 656 | 752 | 2 |
| R2-05 | 869 | 753 | 2 |
| R3-01 | 829, 893 | – | 2 |
| R3-02 | 836 | – | 1 |
| R3-03 | 837 | 811 | 2 |
| R3-04 | 841 | 779 | 2 |
| R3-05 | 842 | – | 1 |
| R3-06 | 846, 847, 873 | – | 3 |
| R3-07 | 776, 777, 778, 780, 781, 785, 849 | 672, 812, 813, 874 | 11 |
| R3-08 | 663, 664, 665, 666, 667, 671, 783 | 757 | 8 |
| R3-09 | 786, 875 | 819 | 3 |
| R3-10 | 818, 848 | – | 2 |
| R3-11 | 817 | – | 1 |
| R3-12 | 722 | – | 1 |
| R3-13 | 877 | 815, 816 | 3 |
| R3-16 | 784 | 814, 876 | 3 |
| R3-18 | 782 | – | 1 |
| R4-01 | 669, 670, 676, 677, 678, 686, 790, 791 | 682, 683, 684, 758 | 12 |

*Stand 24.11.2026:* Nach dem Abhaken der Kap.-28-Einträge Z. 658, 693, 701–704, 711–713, 721 und 750 (TODO, mit Nachweis) entfallen die Zeilen R1-08, R1-09, R1-42 und R1-46; R0-06, R0-08, R0-11 und R1-10 verkürzt. Danach Audit der R0-Spezifikations- und Inventareinträge gegen die abgezeichneten R0-Dokumente (24.11.2026): abgehakt Z. 691, 692, 694, 734, 737, 770, 771, 801, 803, 805, 806, 831, 861–864, 885–888, 890, 892, 900–906, 915, 917, 919; Z. 894, 916, 918 sind keine Duplikate mehr, weil ihre DoD über den abgehakten führenden Eintrag hinausgeht (Rest in der Bemerkung); damit entfallen die Zeilen R0-02, R0-04, R0-06, R0-07, R0-08, R0-19, R0-29, R0-34; R0-03, R0-05, R0-09, R0-12, R0-24, R0-GATE und R4-01 geändert. Zählung per Skript (§5): erledigt 79, offen 101 (führend 73, Duplikat 28, obsolet 0). Die Befunde unten beschreiben den Entwurfsstand.
**Befunde zu §1**

1. **Kein Registereintrag ohne Task.** Alle 144 offenen Einträge haben genau eine führende R-Task. 13 Einträge hängen an R4-01 (Nachfragepool): 9 führend (Diskussionen, interaktive Medien, Gamification, SCORM, Kohorten, Anforderungsmatrix, SUITE-201, SUITE-202 und die obsolete Dashboard-Option), 4 Duplikate.
2. **Eine Reihenfolge.** 40 Duplikate (darunter 6 Sammelpunkte nach RT-4) fallen als eigenständige Arbeit weg. Größte Bündel: Kap. 29 Release-Plan (16 Einträge, davon 15 Duplikate von P0-, FLOW- und R0-PAR-Einträgen), Kap. 31 SEAM-1xx (7 Duplikate von SUITE-, R3-FIRST- und FLOW-Einträgen), die Paritätspunkte P0/Kap. 29/R0-PAR/LB-AUDIT (10 Einträge auf die 4 führenden Z. 655, 904, 905, 906).
3. **Fehlende R0-Dokumente** bestimmen den Großteil der offenen Spezifikationspunkte: R0-34 (Z. 771, 863), R0-39 (Z. 772, 782), R0-40 (Z. 802, 830), R0-29 (Z. 890), R0-30 (Z. 804). R0-20 liegt seit dieser Arbeit als Entwurf vor und deckt Z. 832 nur für die Koexistenz ab.
4. **ADR-Nummernkonflikt** (Z. 701): R0-19 vergibt ADR-007 (Fehlerformat) und ADR-008 (Event-Envelope) neu. Kap. 28.2 belegt diese Nummern mit „Lizenz-/Produktzugang“ und „Rollen und Datenschutz“. R0-12 A-8 („8/8 ADRs“) ist deshalb nicht prüfbar → T-1. **Gelöst durch E-299:** R0-19 führt ADR-009 (Fehlerformat) und ADR-010 (Event-Envelope); ADR-007 steht im Kopf von R0-39, ADR-008 als Verweis-ADR im Kopf von R0-07; R0-12 A-8 nennt 10 ADRs.
5. **FLOW-002-Dimensionen** (Z. 860): R0-12 §7 übergibt an R0-24 Spalten je Fall für Ablehnung, Duplicate, Parallelität, Timeout, Partnerausfall, Retry, Widerruf, Recovery. Dieser Entwurf liefert sie **nicht**; sie sind fachliche Testableitung je Fall (67 × 8) und gehören zur Freigabe von R0-12 → T-4.

---

## 2. PAR-01…12 und LB-01…15 → Inventar → Baustein → Task → Golden Master

### 2.1 PAR-01…PAR-12 (Konzept Kap. 38)

INV-IDs gelten über den Baustein (vollständige Liste je Baustein: R0-04 §6). Jeder R1-Baustein steht in genau einer Zeile. GM-Bereiche und Akzeptanz-Tasks aus R0-05 §6.

| PAR | Bereich | Bausteine (R0-04) | INV-IDs (Anzahl) | Führende Task | weitere Tasks | GM (R0-05 §6) | Gate |
|---|---|---|---:|---|---|---|---|
| PAR-01 | Kurskatalog, Kursstruktur, Freigabe | BS-04, BS-17 | 39 | R1-04 | R1-16, R1-26 | GM-001…GM-006 | B-1 |
| PAR-02 | Lektionen, Medien, Videos, Downloads | BS-07, BS-15 | 35 | R1-07 | R1-14 | GM-007…GM-016 | B-1, C-3 |
| PAR-03 | Fragenbank, Quiz, Auswertung | BS-06 | 11 | R1-06 | R1-09 | GM-017…GM-026, GM-191…GM-194, GM-197 | B-1 |
| PAR-04 | Zeitprüfungen und Versuche | BS-08 | 27 | R1-08 | R1-09, R1-20 | GM-027…GM-054, GM-210 | B-1 |
| PAR-05 | Karteikarten und Favoriten | BS-10 | 12 | R1-11 | – | GM-055…GM-064 | B-1 |
| PAR-06 | Fehlertraining und Smarttrainer | BS-11 | 6 | R1-12 | R1-11 | GM-065…GM-074 | B-1, A-7 |
| PAR-07 | Lernfortschritt und Completion | BS-09 | 4 | R1-10 | – | GM-075…GM-080, GM-209 | B-1 |
| PAR-08 | Zugang, Entitlements (+ Gruppen, Gate) | BS-12, BS-13, BS-14 | 66 | R1-13 | R1-14, R1-35 | GM-081…GM-117, GM-198…GM-200 | C-2 |
| PAR-09 | Import, Export, Rollback | BS-16 | 70 | R1-15 | R1-41 | GM-118…GM-136 | C-3 |
| PAR-10 | Admin-Autorenworkflow, Darstellung | BS-05, BS-21, BS-22 | 32 | R1-05 | R1-26, R1-36 | GM-137…GM-144 | B-1 |
| PAR-11 | REST/AJAX, Cron, Events | BS-03, BS-18, BS-20 | 68 | R1-28 | R1-03, R1-17, R1-38, R1-43 | GM-145…GM-162 | C-7 |
| PAR-12 | Datenmigration und Regression | BS-01, BS-02, BS-25, BS-26 | 118 | R1-02 | R1-23, R1-31, R1-40 | GM-163…GM-171, GM-178…GM-186 | C-8 |
| PAR-13 (D24-6) | Datenschutz und Audit | BS-19 | 21 | R1-17 | R1-34 | GM-172…GM-177 | C-9 |
| PAR-14 (D24-6) | SEO, Consent, Theme-/Cache-Interop | BS-23 | 6 | R1-37 | R1-30, R1-34 | GM-012, GM-013 (nur Consent) | C-14 |
| PAR-16 (E-10, analog D24-6) | Website-Härtung | BS-27 | 8 | R1-45 | R1-32, R1-34 | GM-201…GM-208 (Verhalten aus LB-Belegen, Inventur C; Abweichungen A-03…A-05) | C-17 |
| *ohne PAR* | Text-/Dateiaufgaben (E-168, kein Inventarbestand) | – | – | R1-46 | R1-14, R1-17 | GM-195, GM-196 (ohne LB-Quelle) | B-1 |
| PAR-15 (D24-6) | Kurs-FAQ, Lerntipps (E-16) | BS-24 | 23 | R1-28 | R1-14, R1-15 | GM-189, GM-190 | C-8 |
| *Querschnitt* | Performance, Parallelität | – | – | R1-22 | R1-42 | GM-187, GM-188 | C-6 |

**Summe** der R1-Bausteine BS-01…BS-27 = 546 INV-IDs (wie R0-04 nach Beschluss: BS-04 +1 durch E-25a, BS-12 +1 durch E-26, BS-24 −10 durch E-16, BS-27 +8 durch E-10). **Befund:** Die Prüfschablone Kap. 38 hat für Datenschutz/Audit, SEO/Consent, FAQ/Tipps und Website-Härtung keine PAR-Zeile; BS-22 (E-Mail-Basis) hat keinen R1-Golden-Master (GM-159/GM-160 sind Pro, R0-12 §2.2 Nr. 5). Technisch festgelegt (D24-6): PAR-13…15 werden in Kap. 38 ergänzt; für BS-27 ist eine PAR-Zeile nachzuziehen (Offene Punkte).

### 2.2 LB-01…LB-15 (Konzept Kap. 39) – zugleich Nachweis für LB-AUDIT-002

Die Quellpfade der genannten INV-IDs stehen in R0-02 (Commit `9f37c77`). „Tests“ nennt R1-Katalogtests außerhalb des GM, wo der GM nach R0-05 §1.21 nicht greift.

| LB | Beleg (Kap. 39) | Bausteine | Leit-INV-IDs | Führende Task | weitere Tasks | GM / Tests | Status |
|---|---|---|---|---|---|---|---|
| LB-01 | Woo-Zuordnung, Reconcile, stündlicher Job | BS-12 (Kern), BS-31 (Adapter) | INV-B-003, INV-B-010, INV-B-012, INV-B-120 | R1-13 | R3-02, R3-03 | GRANT-021, GRANT-023 | Kern R1 (FakeSource), Adapter R3.1 |
| LB-02 | Zugang ohne Shop, Teilumfang, Drip | BS-13, BS-12 | INV-A-013, INV-A-031, INV-A-032, INV-D-027, INV-D-028 | R1-13 | R1-14, R1-33 | GM-081…GM-083 | E-14, E-15 |
| LB-03 | Gruppenlizenzen, Plätze | BS-14 | INV-B-037, INV-B-039…INV-B-054 | R1-13 | R1-35 | GM-094…GM-106 | E-17, E-28; Kauf R3 |
| LB-04 | Geschützte Seiten, Block, Shortcode, Dateien | BS-15 | INV-A-088…INV-A-096, INV-C-122…INV-C-124 | R1-14 | R1-28 | GM-107…GM-116 | – |
| LB-05 | Login-Härtung, IP-Limits, Privacy-Flows | BS-27 (Härtung), BS-02 (LMS-Limits), BS-19 | INV-C-138, INV-C-143, INV-C-147 | R1-17 | R1-02, R1-45 | GM-046, GM-073, GM-174 | Härtung im Free-Kern (E-10), Task R1-45 (E-239) → §3 Nr. 4 |
| LB-06 | Consent externe Videos/Hosts | BS-07, BS-23 | INV-A-068, INV-A-069, INV-C-011, INV-E-059 | R1-07 | R1-34 | GM-012, GM-013 | E-45 |
| LB-07 | Paketimport, Dry-run, Rollback, Retention, Fehlerlog | BS-16 | INV-C-086, INV-C-105, INV-D-022 | R1-15 | R1-41 | GM-118…GM-136 | – |
| LB-08 | Integrations-/E2E-Familien | BS-25 | INV-A-149, INV-E-087 | R1-18 | – | GM-001…GM-190 | Woo-Flows R3 |
| LB-09 | Stabile Source-IDs, Hashes, Rechte | BS-16 | INV-C-116, INV-E-001, INV-E-005 | R1-15 | – | GM-120, GM-123, GM-132 | E-62, E-63 |
| LB-10 | Fragetypen single/multi/open_text/chart_task | BS-06 | INV-A-025 | R1-06 | – | GM-017…GM-023 | `chart_task` exclude; E-69 |
| LB-11 | Voucher-Grant, Dedupe, Failure-Callback | BS-12 (Kern), BS-32 (Adapter) | INV-B-013, INV-B-055, INV-B-056 | R1-13 | R3-04 | GM-088…GM-090 | Kern R1, Adapter R3.2 |
| LB-12 | Ticket-/Event-Schnittstellen | BS-33 | INV-C-181, INV-C-182 | R3-07 | – | – | zulässig ohne R1-Test (R3.4) |
| LB-13 | Snapshot-Konverter, Manifest, Importhistorie | BS-16 | INV-C-092, INV-E-001 | R1-15 | – | GM-118…GM-136 | – |
| LB-14 | Fragenrevisionen, feste/zufällige Prüfungen | BS-08 | INV-A-036 | R1-08 | – | GM-027…GM-034, GM-052 | E-50 |
| LB-15 | Mitgliederrabatt, Garantie, Streitfälle | BS-34 | INV-B-078, INV-B-085 | R0-40 | R3-19 | – | Garantie nicht übernehmen (E-32); Mitgliederrabatt im Woo-Adapter R3.1, Pro (E-25a); Ereignisvertrag R0-40 |

---

## 3. R2-Zuordnungen ohne R2-Task (E-54)

Beschluss **E-54**: Jede R2-Zuordnung bekommt eine R2-Task, sonst wandert sie in den R4-Nachfragepool (R4-01). Geprüft wurden alle 42 INV-Zeilen mit „R2“ in Phase/Edition (R0-02) und alle Entscheidungen bzw. R0-Dokumente, die eine Funktion „nach R2“ legen.

| Nr. | Gegenstand | INV-IDs / Quelle | heutige R2-Task | Vorschlag | Begründung / Entscheidung |
|---|---|---|---|---|---|
| 1 | Neutrale Starttexte, Paket-CLI, Seeder | INV-A-122, INV-E-021, INV-E-121 | R2-03 | **bleibt R2-03** | passt zu Kap. 29.1 (Vorlagen/Demos) |
| 2 | Kurs-FAQ-Admin, Taxonomie-FAQ, FAQ-/Tipps-Endpunkte, Tipps-Ansicht, Rich-Text | INV-D-057, INV-D-058, INV-D-082 (Tipps-Teil), INV-D-131, INV-D-132, INV-D-154 (FAQ/Tipps-Teil) | keine | **R1** (Mechanik, BS-24: R1-28, R1-14, R1-05), Starttexte R2-03 | E-16; keine R2-Task nötig |
| 3 | Drip „R1-Free oder R2-Pro“ | INV-D-028 | keine | **R1-13** (einfacher Drip), Kampagnen **R3-08** | E-14; „R2-Pro“ gibt es als Phase nicht |
| 4 | Website-Härtung (Login-Limit, XML-RPC, Header, Registrierungslimit, Gerätecookie) | INV-C-138, INV-C-140…INV-C-146, INV-C-193 (R2-Teil) | keine | **R1-45** (Free) | E-10, E-239; Enumeration und Anwendungspasswörter R4-Pool (E-261); LMS-eigene Limits R1 (BS-02) |
| 5 | Token-/Client-API (Geräte, Client-Login, Client-Export, App-Settings, App-Health) | INV-D-059, INV-D-104, INV-D-106, INV-D-107, INV-D-108, INV-D-110, INV-D-116, INV-D-127, INV-D-128, INV-D-129, INV-D-135, INV-D-136, INV-D-147 | keine | **R4-Pool** (R4-01) bis ein Client beauftragt ist | E-05, E-54 |
| 6 | Client-Varianten bei Web-Verträgen, Token-Anteil | INV-D-119…INV-D-124 (Client), INV-D-134, INV-D-141 (Token-Teil) | keine | Vertrag **R1** (R1-28, R1-17, R1-18); Client-Teil **R4-Pool** mit Nr. 5 | E-05 |
| 7 | Kursbeziehung „enthält/empfiehlt“ (Bundle) | INV-B-068; E-25; R0-19 §4 `bootspruefung_course_relations` | keine | Bundle **R1-04/R1-13** (GM-198); Teil „empfiehlt“ nicht ausdrücklich beschlossen (Offene Punkte) | E-25, E-25a |
| 8 | XLSX-Fragenimport | INV-E-028 | keine | **R4-Pool** | E-200 |
| 9 | Kursmanager nur für zugewiesene Kurse | R0-07 D07-3 b) „Pro-/R2-Option“ | keine | später, Vorschlag **R3-08** (Pro) | E-80 („kursbezogene Kursmanager erst später“) |
| 10 | Fragetypen Zuordnung, Sortieren, Lückentext („Free in R2“) | E-69; R0-22 VAL-041; R0-15 K-12 | keine | **R1-06** (Free) | E-69; E-199 (R2-08) entfällt |
| 11 | Zahl mit Toleranz, Fallstudie | E-22 (E-A-03) | keine (Fallstudie „nur im Rahmen von R2-02“) | Zahl mit Toleranz **R1-06**; Fallstudie entfällt | E-22 |
| 12 | Karteikarten über die Mastery-Engine | E-A-02 d) (zu E-21) | keine | **R4-Pool** | erst nach Golden Master sinnvoll; kein P0 |

**Zählung:** 42 INV-Zeilen = 3 (Nr. 1, mit R2-Task) + 6 (Nr. 2) + 1 (Nr. 3) + 9 (Nr. 4) + 13 (Nr. 5) + 8 (Nr. 6) + 1 (Nr. 7) + 1 (Nr. 8). Dazu 5 Zuordnungen aus Entscheidungen/R0-Dokumenten ohne eigene INV-Zeile (Nr. 7 E-25, Nr. 9, Nr. 10, Nr. 11, Nr. 12). Nach den Beschlüssen bleibt **keine** R2-Zuordnung ohne R2-Task.

---

## 4. Definition of Ready (DoR)

### 4.1 Template (verbindlich für jede R1-Task, auch für Untertasks `<ID>.n`)

Eine Task ist **ready**, wenn alle Pflichtfelder gefüllt und geprüft sind. Das Template ersetzt die acht DoR-Punkte des Expertenreviews (TODO Z. 623) nicht, sondern macht sie prüfbar; die Nummern in Klammern verweisen darauf.

| Nr. | Feld | Inhalt | Prüfregel |
|---|---|---|---|
| DoR-1 | **Ziel und Nichtumfang** (1) | User-Story bzw. Fehlerfall, Nutzerproblem und Nutzen (TODO Z. 691), ausdrücklicher Nichtumfang | 1–3 Sätze; Nichtumfang nennt R2/R3-Abgrenzung |
| DoR-2 | **Inputs / R0-Dokumente** (3) | führender Vertrag (Dokument + Abschnitt) und Nebenquellen | jedes Dokument hat Status „beschlossen“; kein † |
| DoR-3 | **Beantwortete Entscheidungen** (3) | E-Nummern aus §4.2 (direkt, über das Dokument, zusätzlich) | alle mit Status „beschlossen“ und Datum in `ENTSCHEIDUNGEN.md` |
| DoR-4 | **Abhängigkeiten** (6) | Voraussetzungen aus dem R-Plan; Registerzeilen aus §1.4 | alle Voraussetzungen abgehakt; Registerzeilen in der DoD genannt |
| DoR-5 | **Dateien/Module** (2) | Namespace bzw. Pfad (Neuanlage begründet), LB-Referenz (Baustein, INV-IDs) | Pfade passen zum Repository-Gerüst aus R0-18 §6 (R1-01) |
| DoR-6 | **Positive Fälle** (4) | Katalogtests mit Ziel = Task (R0-12 §5) | Liste aus Skript, keine unbekannte ID |
| DoR-7 | **Negativfälle** (4) | Ablehnung, Rechte/IDOR, Duplicate, Parallelität, Timeout, Ausfall, Widerruf, Recovery; Nichtanwendbarkeit begründet (FLOW-002) | mindestens die Negativtests aus §4.3; jede nicht anwendbare Dimension mit Grund |
| DoR-8 | **Migration, Recovery, Accessibility, Privacy** (5) | betroffene Tabellen/Optionen, Restore-Folgen, A11Y-/I18N-Fälle, Datenkategorien (R0-17 §2) | „nicht betroffen“ nur mit Begründung |
| DoR-9 | **Testkommando** | Auswahl der Tests nach Task und Test-ID, CI-Job aus R0-18 §1.4 | Kommando läuft lokal und in der PR-Spur (Konvention T-5) |
| DoR-10 | **Abnahme** | DoD aus dem R-Plan + Gate-Kriterien (R0-12 §3) + Registerzeilen | messbar; Gate-Nummer genannt |
| DoR-11 | **Größe und Zerlegung** (7) | Schätzung; Zerlegung in `<ID>.1 …`, wenn nicht in einem kleinen PR testbar | jede Untertask hat eigene DoR |
| DoR-12 | **Reviewer** (8) | Rollen aus §4.4 (mindestens TL; SEC bei RBAC/SEC/PRIV-Tests; A11Y bei UI; PO bei GM-Abweichung E-55) | Personen benannt (E-204) |
| DoR-13 | **Offene Fragen** | Liste mit Eigentümer und Frist; jede Frage blockiert oder ist ausdrücklich nicht blockierend | keine blockierende Frage offen |

**Vorlage** (für das Ticket bzw. den Untertask im TODO):

```text
R1-xx · <Titel> — DoR
1  Ziel/Nichtumfang: …
2  Inputs: <R0-Dok §> (beschlossen am …), Nebenquellen: …
3  Entscheidungen: E-…, E-… (alle beschlossen am …)
4  Abhängigkeiten: R1-…, Registerzeilen Z. …
5  Module: CourseCaptain\…  · LB-Referenz: BS-…, INV-…
6  Positive Tests: <IDs aus R0-12 §5>
7  Negativfälle: <IDs>; nicht anwendbar: <Dimension – Grund>
8  Migration/Recovery/A11y/Privacy: …
9  Testkommando: composer test -- --group=R1-xx · npx playwright test --grep @R1-xx · CI-Job J-…
10 Abnahme: DoD …; Gate …
11 Größe/Zerlegung: …
12 Reviewer: TL …, SEC …, A11Y …, PO …
13 Offene Fragen: … (Eigentümer, Frist, blockierend ja/nein)
```

### 4.2 Anwendung auf R1-01…R1-46 (A): Ready-Voraussetzungen

**Global für alle Zeilen:** R0-GATE (A-1…A-10) und damit **E-01** (beschlossen: Clean Room, nur Verhalten nachbauen, kein Code aus LB) sowie **E-184** (Gate-Schwelle). Formal verlangt Gate A, dass **alle** E-Nummern beschlossen sind (R0-12 A-3). Die Spalten unten nennen die E-Nummern, die **fachlich** für die Task gelten. Sie dienen der Beantwortungsreihenfolge und der DoR-Prüfung; eine gestaffelte Freigabe gibt es nicht (D24-8, technisch festgelegt).

Spalten: „direkt“ = `ENTSCHEIDUNGEN.md` nennt die Task in „Blockiert“; „über Dokument“ = Entscheidungen, die das führende R0-Dokument blockieren (ohne die direkten); „zusätzlich“ = fachlich nötig, aber in keiner der beiden Listen (begründet in §4.3 bzw. R0-12). „Ready“: *ready* = alle genannten E-Nummern sind beschlossen (16.11.2026) und das führende R0-Dokument liegt vor; Start weiterhin erst nach R0-GATE (Gate A). Die Spalten „direkt“ und „über Dokument“ sind nach Einarbeitung der Beschlüsse aus `ENTSCHEIDUNGEN.md` neu berechnet (Skript §5).

| Task | Voraussetzung (R-Plan) | führendes R0-Dokument | Nebenquellen | E direkt | E über Dokument | E zusätzlich | Ready |
|---|---|---|---|---|---|---|---|
| R1-01 | R0-GATE | R0-20 | R0-07 §2, R0-16, R0-25, R0-35 | E-83, E-121, E-188, E-189, E-191, E-192, E-193, E-194, E-195 | E-08, E-12, E-57, E-58, E-61 | E-07, E-49 | ready |
| R1-02 | R1-01 | R0-06, R0-37 | R0-16 | E-47, E-48 | E-07, E-08, E-27, E-49 | – | ready |
| R1-03 | R1-02 | R0-07 | R0-17 | E-78, E-79, E-80, E-82, E-83, E-152 | – | E-99 | ready |
| R1-04 | R1-02, R1-03 | R0-08, R0-23 | R0-06, R0-25 | E-47, E-67, E-197 | E-20, E-21, E-22, E-23, E-50 | E-25 | ready |
| R1-05 | R1-04 | R0-22 | R0-11, R0-15 | – | E-18, E-23 | E-128, E-131 | ready |
| R1-06 | R1-04 | R0-08 | R0-17 | E-69, E-77, E-146 | E-20, E-21, E-22, E-23, E-50 | – | ready |
| R1-07 | R1-04, R1-06 | R0-08, R0-10 | – | E-67, E-68, E-76 | E-03, E-18, E-20, E-21, E-22, E-23, E-45, E-50 | – | ready |
| R1-08 | R1-06, R1-07 | R0-08 | R0-17 | E-68, E-71, E-72, E-75, E-122, E-123, E-146 | E-20, E-21, E-22, E-23, E-50 | – | ready |
| R1-09 | R1-08 | R0-08 | – | E-74, E-77, E-147 | E-20, E-21, E-22, E-23, E-50 | E-55 | ready |
| R1-10 | R1-07, R1-09 | R0-08 | R0-23 | E-70, E-73 | E-20, E-21, E-22, E-23, E-50 | – | ready |
| R1-11 | R1-06, R1-10 | R0-08 | – | – | E-20, E-21, E-22, E-23, E-50 | – | ready |
| R1-12 | R1-10, R1-11 | R0-08, R0-05 | – | – | E-02, E-20, E-21, E-22, E-23, E-50 | E-55, E-56 | ready |
| R1-13 | R1-03, R1-04 | R0-09 | R0-21, R0-07 | E-81, E-112, E-113, E-114, E-115, E-116, E-117, E-201 | E-26, E-27, E-28, E-29, E-30, E-35 | E-14, E-15, E-17 | ready |
| R1-14 | R1-13, R1-07 | R0-10 | R0-07 | E-65, E-79, E-84 | E-03, E-18, E-45 | E-14 | ready |
| R1-15 | R1-04, R1-06 | R0-10, R0-38 | – | E-03, E-62, E-63, E-64, E-66 | E-18, E-45 | – | ready |
| R1-16 | R1-05, R1-07, R1-08, R1-10, R1-11, R1-12, R1-13 | R0-11 | R0-15 | – | E-14, E-15, E-24, E-38, E-53 | – | ready |
| R1-17 | R1-02, R1-14, R1-15 | R0-17, R0-13 | R0-16 | E-72, E-119, E-150, E-151, E-154 | E-04, E-10, E-19, E-43, E-46, E-51, E-81, E-82, E-122 | – | ready |
| R1-18 | R1-01…R1-17 | R0-05, R0-18 | R0-12, R0-14 | E-55, E-56, E-60 | E-02, E-11, E-13 | E-01, E-184 | ready |
| R1-19 | R1-05,R1-16 | R0-15, R0-25 | – | E-61, E-121, E-124, E-155, E-156, E-157, E-158, E-159, E-160 | E-07, E-49 | – | ready |
| R1-20 | R1-08,R1-09 | R0-08, R0-17 | R0-14 | E-71, E-148, E-149, E-173 | E-04, E-10, E-20, E-21, E-22, E-23, E-43, E-46, E-50, E-51, E-81, E-122 | E-123 | ready |
| R1-21 | R1-13,R1-17 | R0-27 | R0-25 §2 | E-187, E-215, E-216, E-217, E-218, E-219, E-220, E-221, E-222, E-223, E-224 | E-20 | E-24, E-136, E-159 | ready |
| R1-22 | R1-17,R1-20 | R0-14 | – | E-52, E-59 | – | – | ready |
| R1-23 | R1-15,R1-17 | R0-16 | R0-17 §5.5, R0-37 | E-85, E-86, E-87, E-88, E-89, E-90, E-91, E-92, E-151 | – | – | ready |
| R1-24 | R1-16,R1-21 | R0-13 | R0-16 §5, R0-35 | E-59, E-125, E-153 | E-19, E-82 | – | ready |
| R1-25 | R1-02,R1-13 | R0-21 | – | E-198 | E-46 | E-182 | ready |
| R1-26 | R1-04,R1-05,R1-06,R1-08 | R0-22 | – | E-128, E-129, E-130, E-131, E-132, E-133, E-134, E-135 | E-18, E-23 | – | ready |
| R1-27 | R1-08,R1-10,R1-15 | R0-23 | – | E-103, E-104, E-105, E-106, E-107, E-108, E-109, E-110, E-111, E-183 | E-50 | – | ready |
| R1-28 | R1-03,R1-14,R1-16 | R0-19 | – | E-05, E-118 | E-06, E-299, E-32, E-38, E-39, E-84 | – | ready |
| R1-29 | R1-16,R1-19,R1-26 | R0-26 | – | E-126, E-161, E-162, E-163, E-164, E-165, E-166, E-167, E-168, E-169, E-170, E-171 | – | – | ready |
| R1-30 | R1-17,R1-22,R1-28 | R0-20 | R0-25 | E-196, E-198 | E-08, E-12, E-57, E-58, E-61 | – | ready |
| R1-31 | R1-02,R1-03,R0-27 | R0-27 | – | E-215, E-216, E-217, E-218, E-219, E-220, E-221, E-222, E-223, E-224 | E-20, E-187 | – | ready |
| R1-32 | R1-05,R1-31,R0-30 | R0-30 | R0-11 Flow 4 | E-202, E-260, E-261, E-262, E-263, E-264, E-265, E-266, E-267 | – | E-102 | ready |
| R1-33 | R1-08,R1-27,R1-31,R0-28 | R0-28 | R0-23 | E-225, E-226, E-227, E-228, E-229, E-230 | – | – | ready |
| R1-34 | R1-17,R1-21,R1-31,R0-31 | R0-31 | R0-10 §5 | E-239, E-240, E-241, E-242, E-243, E-244, E-245, E-246 | E-43, E-44, E-45, E-51 | – | ready |
| R1-35 | R1-13,R1-27,R0-29 | R0-29 | R0-09 | E-183, E-231, E-232, E-233, E-234, E-235, E-236, E-237, E-238 | E-17, E-93, E-94, E-95, E-96, E-97, E-98, E-99, E-100, E-101, E-102 | E-28 | ready |
| R1-36 | R1-32,R1-33,R1-34,R1-35 | R0-27 | – | – | E-20, E-187 | – | ready |
| R1-37 | R1-14,R1-28,R0-33 | R0-33 | R0-25 | E-197, E-247, E-248, E-249, E-250, E-251, E-252, E-253 | – | – | ready |
| R1-38 | R1-17,R1-24,R0-34 | R0-13, R0-34 | – | E-136, E-137, E-138, E-139, E-140, E-141, E-142, E-143, E-144, E-145, E-153, E-185, E-254, E-255, E-256, E-257, E-258, E-259 | E-19, E-82 | – | ready |
| R1-39 | R1-23,R1-24,R1-30,R0-35 | R0-35 | – | E-188, E-189, E-191, E-192, E-193, E-194, E-205, E-206, E-207, E-208, E-209, E-210, E-211, E-212, E-213, E-214 | E-09, E-13, E-121 | – | ready |
| R1-40 | R1-02,R1-23,R0-37 | R0-37 | R0-16 | E-85, E-86, E-87, E-88, E-89, E-90, E-91, E-92, E-172, E-173, E-174, E-175 | E-07, E-49 | – | ready |
| R1-41 | R1-15,R0-38 | R0-38 | R0-10 §3.5 | E-176, E-177, E-178, E-179, E-180, E-181, E-200 | – | – | ready |
| R1-42 | R1-20,R1-22,R1-40 | R0-21, R0-14 | R0-09 §6 | E-152 | E-46, E-52 | – | ready |
| R1-43 | R1-02, R1-03 | R0-19 | R0-06 | E-203 | E-05, E-06, E-299, E-32, E-38, E-39, E-84 | E-85, E-86, E-116, E-119, E-182, E-185 | ready |
| R1-44 | R1-02, R1-10 | R0-29, R0-08 | R0-06, R0-23 §5 | E-231, E-232, E-233, E-234, E-235, E-236, E-237, E-238 | E-17, E-20, E-21, E-22, E-23, E-50, E-93, E-94, E-95, E-96, E-97, E-98, E-99, E-100, E-101, E-102 | E-110, E-113, E-150, E-158, E-183 | ready |
| R1-45 | R1-03, R1-31, R1-42 | R0-31, R0-30 | R0-20, R0-17 | E-239, E-240, E-241, E-242, E-243, E-244, E-245, E-246, E-260, E-261, E-262, E-263, E-264, E-265, E-266, E-267 | E-43, E-44, E-45, E-51 | E-10, E-46, E-152, E-196 | ready |
| R1-46 | R1-07, R1-09, R1-14 | R0-08, R0-10 | R0-26, R0-05 | – | E-03, E-18, E-20, E-21, E-22, E-23, E-45, E-50 | E-65, E-99, E-168, E-216 | ready (Abgabe-Workflow nach E-168 in R0-08/R0-10 einarbeiten) |
### 4.3 Anwendung auf R1-01…R1-46 (B): Umsetzungsrahmen

Module sind **Vorschläge für die Neuanlage** (kein Course-Captain-Code im Repository; Namensraum `CourseCaptain\` nach Kap. 13, Gerüst nach R0-18 §6). Negativfälle sind die tragenden Negativtests; die vollständige Testliste je Task liefert das Skript in R0-12 §5. Testkommando nach Konvention T-5 (§6.2).

| Task | Dateien/Module (Vorschlag) · LB-Referenz | Negativfälle (Auswahl) | Testkommando | Abnahme | Reviewer | Offene Fragen |
|---|---|---|---|---|---|---|
| R1-01 | `CourseCaptain\Kernel` (Plugin, Requirements, Activator), `uninstall.php`; Ref. BS-01 | MS-001…MS-003, GM-158, KOMP-001, SA-001* | `composer test -- --group=R1-01` · `npx playwright test --grep @R1-01` | aktivierbar/deaktivierbar ohne Woo/Suite/Pro; Mindestversionen geprüft; Gate B-6 | TL, ARC | E-195 ersetzt E-57 (PHP 8.3) |
| R1-02 | `CourseCaptain\Storage` (Schema, Migrator, Repositories); Ref. BS-02 | GM-163, GM-164, GM-168, MIG-001 | `composer test -- --group=R1-02` · `npx playwright test --grep @R1-02` | Install/Upgrade/Backfill ohne Datenverlust; Gate C-8 | TL, ARC | FK-Strategie (E-48) |
| R1-03 | `CourseCaptain\Policy` (Caps, Meta-Caps, PolicyService); Ref. BS-03 | RBAC-001…RBAC-096, RBAC-097*, SEC-003, SEC-004 | `composer test -- --group=R1-03` · `npx playwright test --grep @R1-03` | Gate C-1; RBAC-096 ohne Ausnahme | TL, SEC | Zielkorrektur RBAC-093 (T-2) |
| R1-04 | `CourseCaptain\Course` (CPT Kurs/Modul/Lektion, Revision); Ref. BS-04 | GM-002, GM-003, REV-047*, GM-198 | `composer test -- --group=R1-04` · `npx playwright test --grep @R1-04` | LEARN-01; Gate B-1 (PAR-01) | TL, ARC | Teilkurs-Relation (E-25) |
| R1-05 | `CourseCaptain\Admin\Authoring` (Editor-Panels, Blöcke); Ref. BS-05, BS-21 | GM-138, GM-140, A11Y-020 | `composer test -- --group=R1-05` · `npx playwright test --grep @R1-05` | R0-11 Flow 1; PAR-10 | TL, A11Y, PO | Abgrenzung zu R2-04 |
| R1-06 | `CourseCaptain\Question` (Typ-Registry, Revisionen); Ref. BS-06 | GM-024, GM-025, SEC-016 | `composer test -- --group=R1-06` · `npx playwright test --grep @R1-06` | PAR-03 (GM-017…GM-026) | TL, PO | Fragetypen R2 (§3 Nr. 10) |
| R1-07 | `CourseCaptain\Activity` (Contract, Registry, Video, Download, Consent-Adapter); Ref. BS-07 | GM-015, MED-021…MED-025 | `composer test -- --group=R1-07` · `npx playwright test --grep @R1-07` | PAR-02 (GM-007…GM-016) | TL, ARC, SEC | – |
| R1-08 | `CourseCaptain\Exam` (Blueprint, Versuchsautomat, Snapshot, Timer); Ref. BS-08 | GM-042, GM-045, GM-052, CON-005, GM-210 | `composer test -- --group=R1-08` · `npx playwright test --grep @R1-08` | PAR-04 (GM-027…GM-054) | TL, ARC, SEC | – |
| R1-09 | `CourseCaptain\Grading` (Regeln, Teilpunkte, Ergebnis); Ref. BS-06, BS-08 | GM-030, GM-031, GM-046 | `composer test -- --group=R1-09` · `npx playwright test --grep @R1-09` | Bewertung nach belegter Regel, Retake-UX | TL, PO | – |
| R1-10 | `CourseCaptain\Progress` (Completion, Mastery-Read-Model, Competency abgeleitet); Ref. BS-09 | GM-079, GM-080, GM-209, CRT-064 | `composer test -- --group=R1-10` · `npx playwright test --grep @R1-10` | PAR-07 (GM-075…GM-080) | TL, SEC | Monotonie (GM-209) |
| R1-11 | `CourseCaptain\Practice` (Favoriten, Sammlungen, Fehlertraining, Karteikarten); Ref. BS-10 | GM-058, GM-064 | `composer test -- --group=R1-11` · `npx playwright test --grep @R1-11` | PAR-05 (GM-055…GM-064) | TL | – |
| R1-12 | `CourseCaptain\Practice\SmartTrainer` (versionierte Regeln); Ref. BS-11 | GM-071, GM-072, GM-073 | `composer test -- --group=R1-12` · `npx playwright test --grep @R1-12` | PAR-06 (GM-065…GM-074) mit Referenzlauf | TL, PO | Referenzlauf (E-56) |
| R1-13 | `CourseCaptain\Access` (Grant, Seat, Claim, Reconciler, FakeSource); Ref. BS-12…BS-14 | GRANT-007, GRANT-021, SEAT-015, ID-004, CON-001…CON-004 | `composer test -- --group=R1-13` · `npx playwright test --grep @R1-13` | Gate C-2; CORE-01…04, 06, 07, 12 (R1-Teil) | TL, ARC, SEC | Pending-Timeout (E-201) |
| R1-14 | `CourseCaptain\Gate`, `CourseCaptain\Media` (geschützte Dateien, Range); Ref. BS-15 | RBAC-012…RBAC-020, MED-001…MED-017, CON-031…CON-036 | `composer test -- --group=R1-14` · `npx playwright test --grep @R1-14` | Gate C-3; ACCESS-08 | TL, SEC | – |
| R1-15 | `CourseCaptain\Package` (Validator, Dry-run, Import, Rollback, Export); Ref. BS-16 | IMP-001…IMP-032, GM-122, GM-128 | `composer test -- --group=R1-15` · `npx playwright test --grep @R1-15` | PAR-09 (GM-118…GM-136); Gate C-3 | TL, SEC, ARC | – |
| R1-16 | `CourseCaptain\Frontend` (Lernbereich-Router, Templates, Blöcke); Ref. BS-17 | RBAC-001, GM-047, GM-050 | `composer test -- --group=R1-16` · `npx playwright test --grep @R1-16` | R0-11 Flow 2; PAR-01 | TL, A11Y, PO | – |
| R1-17 | `CourseCaptain\Privacy`, `CourseCaptain\Maintenance` (Cron, Health); Ref. BS-19, BS-20 | PRIV-001…PRIV-015, GM-172…GM-177, SUP-022 | `composer test -- --group=R1-17` · `npx playwright test --grep @R1-17` | Gate C-9; OPS-01 | TL, SEC, OPS | Retention-Defaults E-44/E-51/E-150 |
| R1-18 | `tests/golden-master`, `tests/e2e`, Fixture `cc-fixture-neutral-v1`; Ref. BS-25 | SA-001*, NTF-007* | `composer test -- --group=R1-18` · `npx playwright test --grep @R1-18` | Gate B und C (909 Tests mit R1-Ziel) | TL, PO, SEC, A11Y | CI-Matrix erst mit R0-18 |
| R1-19 | `languages/`, `CourseCaptain\I18n`; Ref. BS-17, BS-22 | A11Y-001…A11Y-010, I18N-001…I18N-015, LOC-001…LOC-029 | `composer test -- --group=R1-19` · `npx playwright test --grep @R1-19` | Gate C-10 | A11Y, TL | – |
| R1-20 | `CourseCaptain\Exam\Integrity` (Serverzeit, Resume, Replay-Schutz) | SEC-009, SEC-011…SEC-015, GM-035, GM-036, CON-016, CON-017 | `composer test -- --group=R1-20` · `npx playwright test --grep @R1-20` | LEARN-04/05; Gate C-1 | TL, SEC | – |
| R1-21 | `CourseCaptain\Notification` (Vorlagen, Versand über Outbox); Ref. BS-22 | NTF-001…NTF-007*, SEC-040, PRIV-021 | `composer test -- --group=R1-21` · `npx playwright test --grep @R1-21` | Gate C-12 | TL, SEC | Vertrag fehlt (E-187) |
| R1-22 | `tests/load` (Lastprofile L1…L5); Ref. R0-14 §2 | NFR-001…NFR-009, NFR-019* | `composer test -- --group=R1-22` · `npx playwright test --grep @R1-22` | Gate C-6, Messprotokoll NFR-018 | TL, ARC | Kapazitätsklasse (E-59) |
| R1-23 | `CourseCaptain\Lifecycle` (Restore-Check, Ausgangs-Halt), `uninstall.php`; Ref. BS-01, BS-20, BS-26 | REC-001…REC-025, REC-026*, PRIV-010 | `composer test -- --group=R1-23` · `npx playwright test --grep @R1-23` | Gate C-5 | TL, OPS, SEC | – |
| R1-24 | `docs/` (Installation, Admin, Autoren, Lernende, Troubleshooting, API) | SUP-039…SUP-042 | `composer test -- --group=R1-24` · `npx playwright test --grep @R1-24` | Review-Protokoll; E-125-Bericht | PO, OPS, A11Y | – |
| R1-25 | `CourseCaptain\Cache` (K0…K4, Invalidierung, Header); Ref. BS-02 | CON-020…CON-036 | `composer test -- --group=R1-25` · `npx playwright test --grep @R1-25` | Gate C-4 | TL, ARC | K-06 braucht Domain-Events (R1-43) |
| R1-26 | `CourseCaptain\Validation` (VAL-000…VAL-145), Admin-Hinweise | PUB-001…PUB-052 | `composer test -- --group=R1-26` · `npx playwright test --grep @R1-26` | Gate B-3 | TL, PO, A11Y | – |
| R1-27 | `CourseCaptain\Course\Revision` (Staging-Kopie, Besitzstand) | REV-001…REV-046, REV-047* | `composer test -- --group=R1-27` · `npx playwright test --grep @R1-27` | Gate B-2 | TL, ARC, PO | braucht R1-44 (E-183) |
| R1-28 | `CourseCaptain\Rest`, `CourseCaptain\Contract`, Shortcodes, Blöcke, CLI; Ref. BS-18 | CT-01…CT-12, GM-145…GM-155 | `composer test -- --group=R1-28` · `npx playwright test --grep @R1-28` | Gate C-7; Routen-Snapshot | TL, ARC, SEC | – |
| R1-29 | Sitzungsprotokolle nach R0-26 §4, Referenzpaket `cc-fx-rk-v1` | UX-001…UX-015, UX-050 | `composer test -- --group=R1-29` · `npx playwright test --grep @R1-29` | Gate C-11 (Schwellen E-166) | PO, A11Y | Rekrutierung, Budget |
| R1-30 | Testinstallationen KP-xx nach R0-20 §4; Ref. BS-23, BS-26 | KOMP-101, KOMP-102, LOC-014, MED-024 | `composer test -- --group=R1-30` · `npx playwright test --grep @R1-30` | Gate C-14 | TL, OPS | Lizenzen kommerzieller Plugins (E-196) |
| R1-31 | `CourseCaptain\Settings` (Register, Schema, Migration); Ref. BS-21 | GM-178, GM-180, GM-181 | `composer test -- --group=R1-31` · `npx playwright test --grep @R1-31` | Feldverträge R0-27 grün | TL, SEC | R0-27 liegt inzwischen vor |
| R1-32 | `CourseCaptain\Admin\Setup`, Settings-Tabs; Ref. BS-21 | GM-179, UX-060 | `composer test -- --group=R1-32` · `npx playwright test --grep @R1-32` | Wireframes R0-30; A11Y | TL, A11Y, PO | R0-30 liegt inzwischen vor |
| R1-33 | `CourseCaptain\Settings\Inheritance` | GM-031, REV-020 | `composer test -- --group=R1-33` · `npx playwright test --grep @R1-33` | Zustandsmatrix R0-28 | TL, ARC | R0-28 liegt inzwischen vor |
| R1-34 | Settings-Anbindung Privacy/Medien/Mail/Rollen | GM-012, GM-013, GM-152, GM-176 | `composer test -- --group=R1-34` · `npx playwright test --grep @R1-34` | Testmatrix R0-31 | SEC, TL | R0-31 liegt inzwischen vor |
| R1-35 | `CourseCaptain\Admin\Groups`, Zertifikats-Verwaltung | GM-105, GM-106, GRANT-028, LOC-020 | `composer test -- --group=R1-35` · `npx playwright test --grep @R1-35` | Gate B-8, C-2 | TL, PO, A11Y | R0-29 liegt inzwischen vor |
| R1-36 | Regressionssuite Settings | GM-179, GM-181 | `composer test -- --group=R1-36` · `npx playwright test --grep @R1-36` | Reset, Downgrade, Multisite-Grenze grün | TL | R0-27 liegt inzwischen vor |
| R1-37 | `CourseCaptain\Seo` (Canonical, Sitemap, Schema, Yoast-Kompatibilität); Ref. BS-23 | LOC-023, LOC-024, REV-040, SEC-033 | `composer test -- --group=R1-37` · `npx playwright test --grep @R1-37` | keine privaten Leaks | TL, SEC | R0-33 liegt inzwischen vor |
| R1-38 | `CourseCaptain\Admin\Operations`, `CourseCaptain\Support` (Aktionen, Audit-Kette); Ref. BS-20 | SUP-009…SUP-038, GM-146, GM-147, GM-148 | `composer test -- --group=R1-38` · `npx playwright test --grep @R1-38` | OPS-06; Gate C-8 | OPS, SEC, TL | R0-34 liegt inzwischen vor |
| R1-39 | Build-Skripte, `readme.txt`, Free-ZIP | REC-023, SEC-045 | `composer test -- --group=R1-39` · `npx playwright test --grep @R1-39` | Gate C-15 | TL, PO | R0-35 liegt inzwischen vor |
| R1-40 | `CourseCaptain\Storage\Migrator` (Lease, Fencing, Backfill-Cursor) | MIG-001…MIG-026 | `composer test -- --group=R1-40` · `npx playwright test --grep @R1-40` | kein doppelter Schritt, kein Datenverlust | TL, ARC, OPS | – |
| R1-41 | `CourseCaptain\Package\Batch` (Spool, Lease, Report) | BIG-001…BIG-008, BIG-020…BIG-040 | `composer test -- --group=R1-41` · `npx playwright test --grep @R1-41` | Dry-run vor Commit, Resume, Report | TL, SEC | – |
| R1-42 | `tests/concurrency` (L5-Runner MariaDB/MySQL) | CON-001…CON-048, NFR-010…NFR-012 | `composer test -- --group=R1-42` · `npx playwright test --grep @R1-42` | Gate C-4 | TL, ARC | – |
| R1-43 | `CourseCaptain\Event` (Envelope v1 intern, Outbox, interne Zustellung), `CourseCaptain\Access\GrantSourceAdapter` (Schnittstelle); keine öffentliche Registry (Pro, E-38) | GRANT-002, REC-016, SUP-029, NTF-001* | `composer test -- --group=R1-43` · `npx playwright test --grep @R1-43` | Tests aus R0-12 §2.3 grün | TL, ARC, SEC | Ziel-Task der bisherigen Event-Tests (CT-06…CT-12, PRIV-023) je Test zwischen R1-43 und R3-01 aufteilen (E-38) |
| R1-44 | `CourseCaptain\Certificate` (Ausstellung, Snapshot, Verifikation, Widerruf) | CERT-001…CERT-006*, RBAC-060…RBAC-066, SEC-034, PRIV-025, CON-037 | `composer test -- --group=R1-44` · `npx playwright test --grep @R1-44` | Gate B-8 | TL, SEC, PO | R0-29 liegt inzwischen vor; Aufnahme E-183 |
| R1-45 | `CourseCaptain\Hardening` (Login-/Reset-/Registrierungslimit, KnownDevice, Header, XML-RPC, Plugin-Erkennung); Ref. BS-27 (Clean Room, E-01) | SUP-043, SUP-044, SEC-047…SEC-051, PRIV-033, ADM-050…ADM-054 | `composer test -- --group=R1-45` · `npx playwright test --grep @R1-45` | Gate C-17; Konfliktmatrix Security-Plugins (R0-20) | SEC, TL, OPS | Aufteilung INV-C-138 nach E-241/E-261 |
| R1-46 | `CourseCaptain\Assignment` (Abgabe-Automat, Bewertung), Uploads über `CourseCaptain\Media`; kein LB-Bestand | GM-195, GM-196 | `composer test -- --group=R1-46` · `npx playwright test --grep @R1-46` | Gate B-9 | TL, SEC, A11Y, PO | Workflow-Vertrag (R0-08) und Negativkatalog fehlen; Abgrenzung E-99/E-168 |
### 4.4 Reviewer-Rollen

| Kürzel | Rolle | Pflicht bei |
|---|---|---|
| TL | Principal Engineering Lead (technischer Reviewer) | jeder Task |
| ARC | WordPress-/LMS-Chefarchitekt | Datenmodell, öffentliche Verträge, Nebenläufigkeit, Migration |
| SEC | Security- und Datenschutz-Review | Tasks mit RBAC-, SEC-, PRIV-, MED-, IMP-Tests oder neuen Datenkategorien |
| A11Y | Accessibility- und i18n-Review | Tasks mit Oberfläche oder A11Y-/I18N-/LOC-/UX-Tests |
| OPS | Betrieb und Support | Diagnose, Recovery, Supportaktionen, Lifecycle |
| PO | LMS-Produktstrategin bzw. Auftraggeber | fachliche Abnahme, GM-Abweichungen (E-55), UX-Schwellen (E-166) |

Die Rollen stammen aus dem Expertenreview (TODO Z. 621: Chefarchitekt, Produktstrategin, Principal Engineering Lead) und werden um SEC, A11Y und OPS ergänzt. Besetzung nach **E-204**: Der Auftraggeber übernimmt die Reviewer-Rollen vorerst selbst; weitere Personen und Vertretungen werden vor R1-Start benannt; das Vier-Augen-Prinzip (Autor ≠ Reviewer) bleibt Pflicht.

**Befunde zu §4**

1. **Alle 46 Tasks sind ready** im Sinne von DoR-3: Alle E-Nummern sind beschlossen (16.11.2026), und alle führenden R0-Dokumente liegen vor. Global bleiben R0-GATE (Gate A, E-184) und DoR-2 (Dokumentstatus „beschlossen“ mit Reviewer) Eingangsbedingung; eine gestaffelte Freigabe gibt es nicht (D24-8, technisch festgelegt). Bei R1-46 muss der Abgabe-Workflow nach E-168 noch in R0-08/R0-10 eingearbeitet werden.
2. **R1-43…R1-46** sind Teil des R-Plans (E-182, E-183, E-239, E-168). R1-43 ist nach E-38 nur die **interne Outbox**; R1-25 braucht R1-43 (K-06), R1-27 braucht R1-44 (R0-12 §2.2 Nr. 6).
3. **R1-01** trägt durch R0-18 sieben neue Entscheidungen (E-188…E-194); deren „Blockiert“-Spalte nennt R1-01 und R1-39. Die Signatur der Pro-ZIPs (E-190) betrifft fachlich R3-18, nicht R1-01 → Hinweis an R0-18.
4. **Testkommando:** R0-18 legt die Jobs fest (J-11 Unit, J-12 Integration, J-13 GM, J-14 E2E), aber nicht die Auswahl nach Task. Die Konvention T-5 schließt die Lücke ohne neue Entscheidung.

---

## 5. Zählungen per Skript

Kommando (aus dem Repository-Wurzelverzeichnis, Python 3, nur lesend; prüft Register gegen TODO, Duplikatketten, R-Tasks, Test-IDs, E-Nummern, PAR/LB, R2-Liste und DoR-Tabelle):

```bash
python3 - <<'PY'
import re, collections, glob, os
D = 'docs/r0/'
doc = open(D + 'R0-24-traceability-dor.md', encoding='utf-8').read()
todo = open('TODO-WETTBEWERBSANALYSE.md', encoding='utf-8').read().split('\n')
ent = open(D + 'ENTSCHEIDUNGEN.md', encoding='utf-8').read()
def cells(l): return [c.strip() for c in l.strip().strip('|').split('|')]
def part(a, b): return doc.split(a, 1)[1].split(b, 1)[0]
start = next(i for i, l in enumerate(todo, 1) if l.startswith('## P0 '))
boxes = {i: l for i, l in enumerate(todo, 1) if i >= start and re.match(r'^- \[[ x]\]', l)}
done = {i for i, l in boxes.items() if l.startswith('- [x]')}
tasks = set(re.findall(r'^- \[[ x]\] \*\*(R\d-(?:\d\d|GATE))\b', '\n'.join(todo[:start]), re.M))
E = set(re.findall(r'^\| \**(E-\d+)\**', ent, re.M))
# Testkataloge (ohne R0-12; in R0-05 nur GM) und Vorschläge aus R0-12 §1.3
PFX = r'(?:GM|RBAC|GRANT|SEAT|ID|IMP|MED|NFR|REC|REV|SUP|A11Y|I18N|SEC|PRIV|CON|PUB|LOC|MS|UX|MIG|BIG|KOMP|SA|SET|NTF|INH|CRT|GRP|ADM|PSD|SEO|INT|RC|LIC|FIN)-\d{3}|CT-\d{2}'
cat = set()
for f in glob.glob(D + 'R0-*.md'):
    if 'R0-12' in f or 'R0-24' in f: continue
    txt = open(f, encoding='utf-8').read()
    if 'R0-05' in f: cat |= set(re.findall(r'\| (GM-\d{3}) \|', txt)); continue
    cat |= set(re.findall(r'^\| (' + PFX + r')\b[^|]*\|', txt, re.M))
r12 = open(D + 'R0-12-tests-gates-backlog.md', encoding='utf-8').read()
prop = set(re.findall(r'^\| ([A-Z0-9]+-\d{3}) \|', r12.split('### 1.3')[1].split('### 1.4')[0], re.M))
def expand(tok):
    tok = tok.strip().rstrip('*')
    m = re.fullmatch(r'([A-Z0-9]+)-(\d+)…\1-(\d+)', tok)
    if m: w = len(m.group(2)); return [f'{m.group(1)}-{n:0{w}d}' for n in range(int(m.group(2)), int(m.group(3)) + 1)]
    return [tok]
# §1 Register
reg = {int(cells(l)[0]): cells(l) for l in part('### 1.2', '### 1.3').split('\n') if re.match(r'^\| \d{3} \|', l)}
ST = r'abgedeckt \(Entwurf\)|offen|obsolet \(.+\)|Duplikat → Z\.\d{3}|erledigt'
err = collections.defaultdict(list)
for z, c in reg.items():
    lead, tests, st = c[3], c[5], c[6]
    if not re.fullmatch(ST, st): err['status'].append(z)
    if (st == 'erledigt') != (z in done): err['erledigt'].append(z)
    if lead not in tasks and not (lead == '–' and st == 'erledigt'): err['task'].append(z)
    if st.startswith('Duplikat'):
        t = int(st[-3:])
        if t not in reg or reg[t][6].startswith('Duplikat') or reg[t][3] != lead: err['dup'].append(z)
    for tok in ([] if tests == '–' else tests.split(', ')):
        for t in expand(tok):
            if (tok.endswith('*') and t not in prop) or (not tok.endswith('*') and t not in cat): err['test'].append((z, t))
unknownE = sorted({e for e in re.findall(r'(?<![A-Z]-)\bE-\d{2,3}\b', doc) if e not in E})
unknownR = sorted({t for t in re.findall(r'\bR\d-\d\d\b', doc) if t not in tasks and not glob.glob(D + t + '-*.md')} - {'R2-08', 'R2-02'})  # R2-02 nach R1-46 vorgezogen, R2-08 entfallen; R0-Dokument ohne eigene Task (R0-41) zählt als bekannt
open_ = [c for z, c in reg.items() if c[6] != 'erledigt']
lead_ = [c for c in open_ if not c[6].startswith(('Duplikat', 'obsolet'))]
print('Register', len(reg), '| TODO', len(boxes), '| fehlend', sorted(set(boxes) - set(reg)), '| überzählig', sorted(set(reg) - set(boxes)))
print('erledigt', len(done), '| offen', len(open_), '| führend', len(lead_), '| Duplikat', sum(c[6].startswith('Duplikat') for c in open_),
      '| obsolet', sum(c[6].startswith('obsolet') for c in open_))
print('führend: abgedeckt', sum(c[6].startswith('abgedeckt') for c in lead_), '| offen', sum(c[6] == 'offen' for c in lead_),
      '| führende Tasks', len({c[3] for c in open_}))
print('Fehler', dict(err) or '{}', '| unbekannte E', unknownE, '| unbekannte R-Tasks', unknownR)
# §1.4 gegen §1.2
agg = collections.defaultdict(lambda: [[], []])
for c in open_: agg[c[3]][1 if c[6].startswith('Duplikat') else 0].append(c[0])
tab = {cells(l)[0]: cells(l) for l in part('### 1.4', '**Befunde zu §1**').split('\n') if re.match(r'^\| R\d-', l)}
bad14 = [t for t, (f, d) in agg.items() if t not in tab or tab[t][1] != (', '.join(f) or '–') or tab[t][2] != (', '.join(d) or '–')]
print('§1.4 Zeilen', len(tab), '| Abweichungen', bad14 + sorted(set(tab) - set(agg)))
# §2 PAR/LB
r04 = open(D + 'R0-04-r1-r3-grenze.md', encoding='utf-8').read().split('PRUEFLISTE-START')[1].split('PRUEFLISTE-ENDE')[0]
bsn = {m.group(1): int(m.group(2)) for m in re.finditer(r'^\| (BS-\w+) \| (\d+) \|', r04, re.M)}
par = [cells(l) for l in part('### 2.1', '### 2.2').split('\n') if re.match(r'^\| (PAR-|\*)', l)]
bsl = [b for c in par for b in re.findall(r'BS-\d\d', c[2])]
sums = [(c[0], c[3], str(sum(bsn[b] for b in re.findall(r'BS-\d\d', c[2]))) if re.findall(r'BS-\d\d', c[2]) else '–') for c in par]
gm = {t for c in par for tok in re.findall(r'GM-\d{3}(?:…GM-\d{3})?', c[6]) for t in expand(tok)}
lb = [cells(l) for l in part('### 2.2', '## 3.').split('\n') if re.match(r'^\| LB-\d\d', l)]
print('PAR', sum(c[0].startswith('PAR') for c in par), '| LB', len(lb), '| Lead ungültig',
      [c[0] for c in par + lb if c[4].rstrip('†') not in tasks], '| BS doppelt', [b for b in set(bsl) if bsl.count(b) > 1],
      '| R1-BS fehlend', [b for b in bsn if b[3:].isdigit() and int(b[3:]) <= 26 and b not in bsl],
      '| INV-Summe falsch', [s[0] for s in sums if s[1] != s[2]], '| INV R1', sum(bsn[b] for b in set(bsl)),
      '| GM fehlend', len({f'GM-{n:03d}' for n in range(1, 211)} - gm))
# §3 R2-Zuordnungen
inv2 = set()
for f in glob.glob(D + 'R0-02-inventar/*.md'):
    for l in open(f, encoding='utf-8'):
        if l.startswith('| INV-') and re.search(r'\bR2\b', cells(l)[-3]): inv2.add(cells(l)[0])
s3 = part('## 3.', '## 4.')
listed = {t for tok in re.findall(r'INV-[A-E]-\d{3}(?:…INV-[A-E]-\d{3})?', s3) for t in
          ([f'{tok[:6]}{n:03d}' for n in range(int(tok[6:9]), int(tok[-3:]) + 1)] if '…' in tok else [tok])}
print('INV mit R2', len(inv2), '| nicht in §3', sorted(inv2 - listed))
# §4 DoR
direct, via = collections.defaultdict(set), collections.defaultdict(set)
for l in ent.split('\n'):
    m = re.match(r'^\| \**(E-\d+)\** \|', l)
    if m:
        b = cells(l)[-1]
        for t in re.findall(r'R1-\d\d', b): direct[t].add(m.group(1))
        for t in re.findall(r'R0-\d\d', b): via[t].add(m.group(1))
def es(s): return set(re.findall(r'E-\d+', s))
dor = [cells(l) for l in part('### 4.2', '### 4.3').split('\n') if re.match(r'^\| R1-', l)]
dor2 = [cells(l) for l in part('### 4.3', '### 4.4').split('\n') if re.match(r'^\| R1-', l)]
bad4, notready = [], 0
for c in dor:
    t = c[0].rstrip('\\*'); prim = [p.strip() for p in c[2].split(',')]
    exists = {p.rstrip('†'): bool(glob.glob(D + p.rstrip('†') + '-*.md')) for p in prim}
    v = set().union(*[via[p] for p in exists]) - direct[t]
    if es(c[4]) != direct[t] or es(c[5]) != v or any(p.endswith('†') == exists[p.rstrip('†')] for p in prim) \
       or c[7].startswith('nein') == all(exists.values()):
        bad4.append(t)
    notready += c[7].startswith('nein')
print('DoR-Zeilen', len(dor), '/', len(dor2), '| abweichend', bad4, '| nicht ready', notready)
PY
```

Ergebnis am 16.11.2026 (finaler Konsistenz-Durchgang, aus dem Repository-Wurzelverzeichnis ausgeführt, fehlerfrei; der vorige Eintrag war ein Lauf aus dem falschen Verzeichnis). Vorher angepasst: Registerzeilen auf den TODO-Stand nach `98bfc38` (+4 ab Z. 586), PAR-13…PAR-16, Test-Präfixe aller Kataloge, GM-Prüfung GM-001…GM-210:

```
Register 147 | TODO 147 | fehlend [] | überzählig []
erledigt 3 | offen 144 | führend 102 | Duplikat 40 | obsolet 2
führend: abgedeckt 38 | offen 64 | führende Tasks 53
Fehler {} | unbekannte E [] | unbekannte R-Tasks []
§1.4 Zeilen 53 | Abweichungen []
PAR 16 | LB 15 | Lead ungültig [] | BS doppelt [] | R1-BS fehlend [] | INV-Summe falsch [] | INV R1 546 | GM fehlend 0
INV mit R2 42 | nicht in §3 []
DoR-Zeilen 46 / 46 | abweichend [] | nicht ready 0
```

Ergebnis am 24.11.2026 nach dem Audit der R0-Registereinträge (aus dem Repository-Wurzelverzeichnis, Auszug Register und §1.4; `TODO` zählt seit dem Abschnitt „Einstellungen für den Verkauf“ ab Z. 921 auch dessen Checkboxen, die nicht zum Register gehören und deshalb als `fehlend` erscheinen; die DoR-Abweichungen bestanden schon vor diesem Audit und kommen aus späteren E-Nummern mit R1-Bezug):

```
Register 147 | überzählig []
erledigt 79 | offen 101 | führend 73 | Duplikat 28 | obsolet 0
führend: abgedeckt 14 | offen 59 | führende Tasks 41
Fehler {} | unbekannte E [] | unbekannte R-Tasks []
§1.4 Zeilen 41 | Abweichungen []
```

Ergebnis am 24.11.2026 nach dem Abhaken von Z. 859, 860, 891 und 918 (R0-41; Auszug; die R-Task-Prüfung akzeptiert seitdem auch eine R0-Nummer, zu der ein Dokument in `docs/r0/` existiert, weil R0-41 keine eigene Task hat):

```
Register 147 | überzählig []
erledigt 83 | offen 97 | führend 69 | Duplikat 28 | obsolet 0
führend: abgedeckt 14 | offen 55 | führende Tasks 41
Fehler {} | unbekannte E [] | unbekannte R-Tasks []
§1.4 Zeilen 41 | Abweichungen []
```

**Grenzen der Zählung:** Das Skript prüft Existenz, Eindeutigkeit und Vollständigkeit der Zuordnung, nicht ihre fachliche Richtigkeit (Review). Test-IDs gelten als bekannt, wenn sie in einer Katalogzeile der genannten R0-Dokumente stehen; vorgeschlagene IDs (\*) müssen in R0-12 §1.3 stehen. Bereiche „A…B“ werden aufgelöst und jede ID einzeln geprüft. Die Spalte „E zusätzlich“ der DoR-Tabelle wird nur auf Existenz geprüft; „E direkt“ und „E über Dokument“ müssen exakt der Berechnung aus `ENTSCHEIDUNGEN.md` entsprechen. Die Zeilennummern beziehen sich auf den Stand nach `98bfc38` (R3-20 eingefügt, +4 ab Z. 586); jede Änderung am TODO oberhalb von Z. 653 verschiebt sie (das Skript meldet dann `fehlend`/`überzählig`).

---

## 6. Entscheidungsbedarf und technische Korrekturen

### 6.1 Entscheidungsbedarf (beschlossen)

Alle Fragen sind entschieden (`ENTSCHEIDUNGEN.md` §31 und „Von Claude entschieden“, 16.11.2026).

| ID | Frage | Beschluss |
|---|---|---|
| **D24-1** | Eigene R2-Task für Zuordnung, Sortieren, Lückentext? | **E-199 entfällt** durch **E-69**: die Fragetypen kommen schon in R1 Free (R1-06); keine R2-08 |
| **D24-2** | XLSX-Fragenimport | **E-200:** R4-Pool; R1 importiert nur cc-package |
| **D24-3** | Pending-Timeout für Grants | **E-201:** 24 h, danach `failed` mit `pending_timeout`, Support-Hinweis und Event; für unbezahlte Woo-Bestellungen gilt er nicht (E-276) |
| **D24-4** | Suite-Navigationsmuster | **E-202:** eigenes Course-Captain-Hauptmenü, Suite-Status als Unterseite „Integrationen“ (in R1 nur lesend, E-254); gemeinsames Suite-Menü frühestens mit R3-16 |
| **D24-5** | Mitgliedschafts-Entitlement | **E-203:** kein eigener Vertrag; Mitgliedschaft ist Grant-Quelle über den GrantSourceAdapter (Schnittstelle in R1-43), Adapter erst in R3 |
| **D24-6** | PAR-13…15 in Kap. 38 | technisch festgelegt (ENTSCHEIDUNGEN §31): PAR-13…15 für BS-19/23/24 werden ergänzt |
| **D24-7** | Besetzung der Reviewer-Rollen | **E-204:** Auftraggeber übernimmt die Rollen vorerst selbst; weitere Personen/Vertretung vor R1-Start; Vier-Augen-Prinzip bleibt Pflicht (teilweise Abweichung von der Empfehlung) |
| **D24-8** | Gestaffelte Readiness | technisch festgelegt: keine gestaffelte Readiness, Gate A bleibt hart |
| **D24-9** | Verweise an Duplikat-Einträgen im TODO | technisch festgelegt: Verweise „→ Z.n / R-Task“ werden eingetragen |

### Offene Punkte nach Einarbeitung

1. **BS-27 ohne PAR-Zeile:** Mit E-10 ist die Härtung ein R1-Baustein (8 INV-IDs); D24-6 nennt nur PAR-13…15 für BS-19/23/24. Eine PAR-Zeile (oder ausdrückliche Ausnahme, da Clean Room ohne LB-Code nach E-01) ist in Kap. 38/R0-05 nachzuziehen. → erledigt: **PAR-16** Website-Härtung (BS-27) in §2.1 mit GM-201…GM-208 (R0-05 §1.23); PAR-13…15 für BS-19/23/24 eingetragen (D24-6)
2. **Registerstatus nach neuen Dokumenten:** Zeilen mit „R0-xx liegt inzwischen vor“ (R0-27…R0-40) stehen noch auf „offen“; ob die neuen Dokumente sie vollständig abdecken („abgedeckt (Entwurf)“), ist im Review je Zeile zu prüfen. Die Zählung „abgedeckt 38 / offen 64“ ist deshalb vorläufig. → **technisch, an Review je Registerzeile (R0-GATE) übergeben** (kein Auftraggeber-Beschluss, kein Blocker für Gate A): Zählung 38/64 bleibt bis zum Zeilen-Review vorläufig; Skript §5 läuft fehlerfrei
3. **R1-46 ohne Workflow-Vertrag:** Führende Dokumente R0-08/R0-10 müssen den Abgabe-Automaten `draft/submitted/returned/graded` nach E-168 aufnehmen; bis dahin ist DoR-2 für R1-46 nur formal erfüllt. → erledigt: Abgabe-Automat in R0-08 §2.5 und R0-10 §4.5 (E-168, E-288…E-290)
4. **Teil „empfiehlt“ der Kursbeziehung** (§3 Nr. 7) ist nicht ausdrücklich beschlossen (E-25a nennt nur das Bundle). → **erledigt durch E-298** (R4-Pool)
5. **GM-Prüfung im Skript** deckt GM-001…GM-190 ab; die inzwischen ergänzten GM-191…GM-200 (u. a. GM-195/GM-196 für R1-46) werden nicht auf PAR-Zuordnung geprüft. → erledigt: Skript §5 prüft jetzt GM-001…GM-210 auf PAR-Zuordnung (GM fehlend 0)

### 6.2 Technische Korrekturen (ohne Auftraggeber-Entscheidung, mit Freigabe der betroffenen Dokumente einzuarbeiten)

| Nr. | Korrektur | Betroffen |
|---|---|---|
| **T-1** | ADR-Nummern: R0-19 vergibt Fehlerformat und Event-Envelope als **ADR-009** und **ADR-010**. ADR-007 (Lizenz-/Produktzugang) wird in R0-39 geschrieben, ADR-008 (Rollen und Datenschutz) als Kurz-ADR, das auf R0-07 und R0-17 verweist. R0-12 A-8 nennt dann 10 ADRs. | R0-19 Kopf, §3, §5.1; R0-12 A-8; R0-39 |
| **T-2** | RBAC-093 und SEC-026: Replay-Antwort auf `202 duplicate` (wie CT-11, R0-19 R-51); Ziel von RBAC-093 auf **R3-01** (Route ist R3). R0-12 zählt RBAC-093 bisher unter R1-03. | R0-07 §5, R0-17 §7, R0-12 §5 |
| **T-3** | Testdubletten nach §1.3 in den Katalogen als „wie CON-00x“ bzw. „wie REC-022“ kennzeichnen; SEAT-006 auf den Teil „Lock belegt“ kürzen. | R0-09 §6, R0-17 §7, R0-21 §5 |
| **T-4** | FLOW-002-Dimensionsspalten je Fall (R0-12 §7, Delta „R0-24“) werden mit der Freigabe von R0-12 als §1.5 dort ergänzt, nicht in R0-24 (führende Task von Z. 860 ist R0-12). **Erledigt 24.11.2026** in R0-41 §2 (eigenes Dokument, R0-12 unverändert). | R0-12 §1, §7; R0-41 §2 |
| **T-5** | Testauswahl nach Task: Jeder Katalogtest trägt `@group <Test-ID>` und `@group <Task>` (PHPUnit) bzw. Tag `@<Task> @<Test-ID>` (Playwright). Kommando je Task: `composer test -- --group=R1-xx` (J-11/J-12/J-13) und `npx playwright test --grep @R1-xx` (J-14). Last- und Race-Läufe (R1-22, R1-42) über J-19/`perf-release.yml`, manuelle Fälle (R1-29) über Sitzungsprotokoll R0-26 §4. | R0-18 §1.4 (Ergänzung), R1-01 (Gerüst) |
| **T-6** | E-190 (Signatur Pro-ZIP) blockiert fachlich R3-18, nicht R1-01/R1-39 (übernommen in ENTSCHEIDUNGEN §31). | `ENTSCHEIDUNGEN.md` §29 |

---

## 7. Deltas zu anderen Dokumenten (mit deren Freigabe einzuarbeiten)

| Dokument | Delta |
|---|---|
| TODO | nach D24-9 (technisch festgelegt): Verweise „→ Z.n“ bzw. führende Task an den 40 Duplikaten (davon 6 Sammelpunkte); R2-08 entfällt (E-69); R2-02 ist nach R1-46 vorgezogen (erledigt); R0-24-DoD: DoR-Template §4.1 als Pflichtfeld jedes R1-Tickets |
| `ENTSCHEIDUNGEN.md` | erledigt (E-199…E-204, §31) |
| Konzept Kap. 38 | nach D24-6: PAR-13…PAR-15; zusätzlich PAR-16 Website-Härtung (BS-27, E-10) |
| R0-05 | nach D24-6: PAR-13…16 in §6 – **eingearbeitet** (16.11.2026); Hinweis „BS-22 ohne GM“ |
| R0-07, R0-17, R0-21, R0-09 | T-2, T-3 |
| R0-12 | T-1 (A-8), T-2 (Zählung R1-03), T-4 (FLOW-002-Spalten), Verweis auf R0-24 §1.3 statt §2.2 Nr. 7 |
| R0-18 | T-5 (Testauswahl nach Task); J-23 um das Skript aus §5 erweitern |
| R0-19 | T-1 |
| R0-41 (neu, 24.11.2026) | Prozessmatrix §1 (FLOW-001, Z. 859), Testdimensionen §2 (FLOW-002, Z. 860, T-4), Kernfälle mit DB-Zustand und Auditspur §3 (CORE-SPEC-007, Z. 891), Lücken §4, Golden-Master-Import §5 (LB-AUDIT-006, Z. 918); führende Task bleibt R0-12 bzw. R0-05 |
| R0-12 | Verweis aus §1.1/§1.2 auf R0-41 §1–§3 (bei der nächsten Freigabe von R0-12) |

---

## 8. Nachweis

- Gelesen: `TODO-WETTBEWERBSANALYSE.md` vollständig (alle 157 Checkboxen ab Z. 560; Register ab Z. 653); `konzept.md` Kap. 28.2, 38, 39; `docs/r0/ENTSCHEIDUNGEN.md` (E-01…E-194, Spalte „Blockiert“ maschinell ausgewertet); `R0-12-tests-gates-backlog.md` vollständig; R0-04 §1, §2, §4, §6; R0-05 §1.21, §6; R0-18 §1.4, §1.5, §4, §5, §6; R0-02-Inventar (Spalten Phase/Edition, Task-ID; Stichproben der Leit-INV-IDs in §2.2). Von den übrigen R0-Dokumenten wurden Kopf („Bezug“), Gliederung und Testkataloge ausgewertet, nicht der volle Fließtext.
- Zählungen: Skript in §5, Ergebnis dort wörtlich. Die fachliche Zuordnung (führende Task, Duplikat, Status) ist hergeleitet und muss im Review gegengeprüft werden.
- Während der Arbeit kamen R0-18 mit E-188…E-194 (Commit `c1d8997`) und R0-20 mit E-195…E-198 (Commit `d7ca58d`) hinzu; beide sind als Entwurf berücksichtigt.
- Beschlüsse vom 16.11.2026 eingearbeitet (Beschlussstand, §1.2 Zeilennummern und Leit-Tasks, §1.4, §2.1, §3, §4, §6); Skript §5 erneut ausgeführt.
- Nicht erfolgt: keine Änderung an anderen Dateien, keine Implementierung, kein Testlauf, kein Commit.
