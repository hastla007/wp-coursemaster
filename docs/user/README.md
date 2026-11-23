# Course Captain – Dokumentation (Entwicklungsstand 1.0.0-dev)

Diese Dokumentation beschreibt den **tatsächlichen Stand des Codes** im Branch der R1-Umsetzung (Stand 16.11.2026). Funktionen, die zwar geplant, aber noch nicht gebaut sind, sind als **„geplant (R1-xx)“** gekennzeichnet. Was dort steht, ist nicht verfügbar.

> **Wichtig:** Course Captain ist eine Entwicklungsversion und nicht für Produktivseiten freigegeben. Viele Dienste sind serverseitig fertig (Datenmodell, Regeln, REST-API), aber es fehlen noch Teile der Oberfläche, etwa die Einstellungsseiten, der Fragen- und Prüfungseditor, die Gruppenverwaltung und die interaktive Lektions- und Prüfungsansicht.

| Dokument | Für wen | Inhalt |
|---|---|---|
| [installation.md](installation.md) | Betreiber | Voraussetzungen, Aktivierung, Multisite, Deaktivierung, Deinstallation und Datenlöschung, erste Schritte |
| [admin.md](admin.md) | Administratoren, Kursmanager | Rollen und Rechte, Einstellungen, Gruppen und Plätze, Benachrichtigungen, Zertifikate, Import/Export, Website-Zustand, Protokoll |
| [autoren.md](autoren.md) | Autorinnen und Autoren | Kurse, Module, Lektionen, Activity-Typen, Fragetypen, Prüfungsregeln und Blueprints, Veröffentlichungsprüfung mit VAL-Codes |
| [lernende.md](lernende.md) | Lernende (Vorlage für Betreiber-Hilfeseiten) | Lernbereich, Smarttrainer, Karteikarten, Favoriten, Prüfungen, Nachweise, Benachrichtigungen |
| [troubleshooting.md](troubleshooting.md) | Support, Betreiber | Fehlercodes, Runbooks, Wartungslauf und Ereigniszustellung, Caches, Rate-Limits, Forum-Regeln, Sicherheitsmeldung |
| [datenschutz.md](datenschutz.md) | Betreiber, Datenschutzbeauftragte | Datenklassen, Export und Löschung, Aufbewahrungsfristen, Speicherorte, externe Dienste |
| [entwickler.md](entwickler.md) | Entwickler, Integratoren | REST-Routen, Hooks und Filter, interne Ereignisse, Fehlerkatalog, Konstanten |
| [release.md](release.md) | Release-Verantwortliche | Änderungsprotokoll R1, Release-Checkliste, Release-Notes-Vorlage |

Die normative API-Referenz entsteht mit R1-28 in [`docs/API.md`](../API.md) und [`docs/API-CHANGELOG.md`](../API-CHANGELOG.md). Die Übersicht in `entwickler.md` verweist dorthin.

**Begriffe:** *Grant* = Kurszugang einer Person; *Platz* (Seat) = Mitgliedschaft in einer Gruppe; *Kursrevision* = eingefrorener, veröffentlichter Stand eines Kurses; *Outbox* = interne Ereigniswarteschlange (Mails, Zertifikate, Cache); *Lernbereich* = Frontend unter `/lernen/` (Slug einstellbar).

Sprache der Oberfläche: Die Quelltexte sind Englisch. Deutsche Übersetzungen werden über translate.wordpress.org bzw. mitgelieferte Sprachdateien geladen. Bezeichnungen in dieser Dokumentation geben daher teils den englischen Originaltext an.
