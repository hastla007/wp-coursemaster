# TODO

## Mindestversionen: neueste PHP-, MySQL- und MariaDB-Version (Entscheidung der Inhaberin, 16.11.2026)

- [ ] **MINVER-1 — Auf die neuesten Versionen umstellen und testen.** Die Inhaberin hat am 16.11.2026 für alle Captain-Plugins entschieden: Mindestversionen sind die jeweils neuesten – **PHP 8.5** (PHP 8.6 erscheint am 19.11.2026 und kommt in die Test-Matrix), **MySQL 9.7 (LTS)** und **MariaDB 12.3 (LTS)**. Heute verlangt dieses Repository keine ausdrückliche PHP-Mindestversion im Plugin-Header.
  - *Zu tun:* Plugin-Header `Requires PHP`, Laufzeitprüfung, `readme.txt`, README, `composer.json`, PHPCS `testVersion`, PHPStan `phpVersion` und die CI-Zellen (Unit-Matrix ab 8.5, Integration auf MySQL 9.7 und MariaDB 12.3) einheitlich umstellen; eine Warnung (keine Sperre), wenn die Datenbank älter ist; Changelog-Eintrag.
  - *Test (Inhaberin):* auf einem echten Host mit PHP 8.5/8.6 und MySQL 9.7 bzw. MariaDB 12.3 installieren, aktualisieren und die Hauptabläufe durchspielen.
  - *Vorbild:* Affiliate Captain (`hastla007/wpaffiliatemaster`, Aufgaben PHP-1/PHP-2).
