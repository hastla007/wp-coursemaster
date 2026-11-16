# Installation und erste Schritte

Geprüft gegen: `course-captain.php`, `readme.txt`, `composer.json`, `src/Kernel/{Requirements,Activator,NetworkGuard,HoldMode,Plugin,Uninstaller,UninstallManifest,CronHooks}.php`.

## 1. Voraussetzungen

| Bereich | Mindestens (harte Grenze) | Empfohlen | Prüfung |
|---|---|---|---|
| PHP | 8.3 | 8.3 – 8.5 | bei Aktivierung und bei jeder Anfrage |
| WordPress | 7.0 | 7.1 (`Tested up to`) | bei Aktivierung und bei jeder Anfrage |
| Datenbank | MariaDB 10.6 oder MySQL 8.0 | MariaDB 10.11+ oder MySQL 8.4+ | nur bei Aktivierung |
| Tabellen-Engine | InnoDB | – | bei Aktivierung |
| Datenbankfunktionen | `JSON_VALID`, Zeichensatz `utf8mb4` | – | bei Aktivierung |
| Permalinks | sprechende Permalinks (nicht „Einfach“) | – | **keine Prüfung**: Lernbereich, Nachweis- und Abmeldeseiten nutzen Rewrite-Regeln (`/lernen/…`) |

Nicht nötig: WooCommerce, andere Plugins, ein Lizenzschlüssel oder ein Konto beim Hersteller. Course Captain sendet keine Telemetrie.

Ist eine harte Grenze verletzt, bricht die Aktivierung mit einer Meldung ab, **bevor** etwas in die Datenbank geschrieben wird. Die Fehlercodes lauten `ccap_requirement_php`, `ccap_requirement_wordpress`, `ccap_requirement_database`, `ccap_requirement_innodb`, `ccap_requirement_json` und `ccap_requirement_utf8mb4`. Fällt eine Voraussetzung später weg (etwa nach einem PHP-Downgrade), lädt das Plugin nicht und zeigt im Admin einen Hinweis statt eines Fatal Errors.

## 2. Installation und Aktivierung

1. ZIP unter „Plugins › Installieren › Plugin hochladen“ hochladen oder den Ordner `course-captain` nach `wp-content/plugins/` kopieren.
2. Plugin aktivieren. Die Aktivierung läuft in dieser Reihenfolge: Netzwerkblockade, Systemprüfung, Tabellenanlage (53 Tabellen mit Präfix `<wp-präfix>ccap_`), Rollen und Rechte, stündlicher Wartungslauf (`ccap_hourly`), Neuaufbau der Permalinks beim nächsten Seitenaufruf.
3. Existiert bereits eine gleichnamige Tabelle ohne Course-Captain-Eigentumsmarke, bricht die Aktivierung ab, ohne etwas zu verändern (Schutz vor Kollisionen mit anderen Plugins).

Erneutes Aktivieren ist gefahrlos: Tabellen und Rollen werden nur ergänzt, nichts wird überschrieben.

**Einrichtungsassistent:** geplant (R1-32). Es gibt noch keinen Assistenten und keine Weiterleitung nach der Aktivierung.

### 2.1 Datenbank-Updates

Neue Versionen bringen Schemaänderungen über einen eigenen Migrator mit Sperre mit. Solange eine Migration fällig ist, antworten **schreibende** REST-Anfragen an `course-captain/v1` mit `503 ccap_maintenance` und `Retry-After: 30`. Lesende Anfragen funktionieren weiter. Die Migration läuft über das Ereignis `ccap_migrate` von selbst. WP-CLI-Befehl `wp course-captain migrate`: geplant (R1-40/R1-28).

## 3. Multisite

- **Netzwerkweite Aktivierung ist blockiert** (Oberfläche, WP-CLI `--network`). Course Captain wird auf jeder Site einzeln aktiviert. Jede Site hat eigene Tabellen, Rollen und Einstellungen.
- Wurde das Plugin trotzdem netzwerkweit erzwungen (z. B. per Datenbank), startet es im **Haltemodus**: keine Cron-Handler, keine Migration, keine Erweiterungen. Netzwerk- und Site-Admins sehen einen Hinweis. Abhilfe: netzwerkweite Aktivierung aufheben und auf den gewünschten Sites einzeln aktivieren.
- Haltemodus nach Umzug oder Kopie der Site (geänderte Site-Identität): geplant (R1-23). Der Anschluss für Mails existiert bereits als Filter `course_captain_notification_hold`.

## 4. Erste Schritte nach der Aktivierung

1. **Permalinks prüfen** (Einstellungen › Permalinks, nicht „Einfach“). Der Lernbereich liegt unter `/lernen/`, Kurse öffentlich unter `/kurs/<slug>/`.
2. **Website-Zustand öffnen** (Werkzeuge › Website-Zustand). Course Captain meldet dort geschützte Ablage, Wartungslauf, Ereigniszustellung und Mailzustellung (siehe [troubleshooting.md](troubleshooting.md) §4).
3. **Echten System-Cron einrichten**, wenn die Site wenig Besucher hat oder `DISABLE_WP_CRON` gesetzt ist. Mails, Zertifikate, Ablauf von Zugängen und Fristen von Prüfungen hängen am Cron.
4. **Geschützte Ablage**: Auf nginx eine Regel ergänzen, die den Direktzugriff auf die Ordner `uploads/course-captain/protected-*` und `packages-*` verbietet, oder `COURSE_CAPTAIN_PRIVATE_DIR` in `wp-config.php` auf einen Ordner außerhalb des Webroots setzen. Apache wird über mitgelieferte `.htaccess`-Dateien geschützt.
5. **Mailversand prüfen**: Außerhalb von `production` (`WP_ENVIRONMENT_TYPE`) gehen Mails standardmäßig an eine Fangadresse (Modus `catch`), siehe [admin.md](admin.md) §5.
6. **Rollen zuweisen** (Benutzer › Profil): Kursmanager, Autor, Trainer, Support (siehe [admin.md](admin.md) §1).
7. **Ersten Kurs anlegen**: Course Captain › Courses › Erstellen (siehe [autoren.md](autoren.md)).

## 5. Deaktivierung

Deaktivieren entfernt nur die eigenen Cron-Ereignisse und die Rewrite-Regeln. **Daten bleiben vollständig erhalten.** Nach erneuter Aktivierung sehen Lernende denselben Stand.

## 6. Deinstallation (Löschen)

Beim Löschen über „Plugins › Löschen“ läuft `uninstall.php`:

| Was | Standard | Mit bestätigter Datenlöschung |
|---|---|---|
| Cron-Ereignisse, Rewrite-Regeln | entfernt | entfernt |
| Tabellen `ccap_*` (nur mit Eigentumsmarke) | **bleiben** | gelöscht |
| Optionen `course_captain_*` (exakte Liste, kein Platzhalter) | **bleiben** | gelöscht |
| Rollen `ccap_*`, eigene Post-Meta, User-Meta und User-Optionen | **bleiben** | gelöscht |
| Kurs-, Modul- und Lektionsbeiträge (WordPress-Beiträge) | bleiben | **bleiben** (Inhaltslöschung geplant, R1-23) |
| Dateien in der geschützten Ablage | bleiben | **bleiben** (geplant, R1-23) |

Die Datenlöschung greift nur, wenn in den allgemeinen Einstellungen `delete_data_on_uninstall` den Wert `true` hat. Dieses Feld darf nur ein Administrator ändern. **Stand:** Es gibt noch keine Einstellungsseite und keinen Bestätigungsdialog (Site-Name, Backup-Hinweis, Protokolleintrag). Beides ist geplant (R1-32/R1-36). Bis dahin bleiben bei einer Deinstallation **immer alle Daten** erhalten. Setzen Sie den Wert nicht per SQL oder `wp option update`.

Multisite: Die Deinstallation läuft je Site mit der Einstellung dieser Site. Netzwerkweite User-Meta wird nur entfernt, wenn **keine** Site ihre Daten behält.

Vor einer Datenlöschung immer ein Backup von Datenbank und `uploads/` anlegen. Backup-, Restore- und Upgrade-Proben: geplant (R1-23).
