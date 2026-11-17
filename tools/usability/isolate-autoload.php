<?php
/**
 * WP-CLI-Vorlauf (`--require`): Course-Captain-Klassen kommen aus dem
 * installierten Plugin der Test-Site, nicht aus dem Repository (Befund B-07).
 *
 * Die Isolierung selbst steht in `tools/qa/wp-cli-autoload-isolation.php`
 * und wird auch vom Artefakttest `tools/release/artifact-test.sh` genutzt.
 * Diese Datei bleibt als Einstiegspunkt für `setup-site.sh`, `seeder.php`
 * und die Anleitung in `docs/r1/R1-29/testumgebung.md`.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/qa/wp-cli-autoload-isolation.php';
