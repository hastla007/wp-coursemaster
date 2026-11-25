=== Course Captain ===
Contributors: coursecaptain
Tags: lms, courses, e-learning, quiz, education
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.5
Stable tag: 1.0.0-dev
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Neutral learning management for WordPress: courses, lessons, exams and certificates without required add-ons.

== Description ==

Course Captain is a learning management plugin for any kind of course provider. It works on its own: WooCommerce and other plugins are optional.

This is a development build and not meant for production sites. It contains courses, modules and lessons with immutable course revisions, a question bank with nine neutral question types, exams with blueprints and a server-side timer, a smart trainer, flashcards and favorites, course access and groups, a learning area, notifications, basic certificates with public verification, a publish check for courses, privacy export and erasure, and Site Health checks.

Some screens are not available yet in this build: settings pages, the question and exam editors, group management, imports and the interactive lesson and exam views. The documentation in the plugin repository (docs/user/) marks every planned feature.

== Installation ==

1. Install the plugin from the plugin directory or upload the ZIP file under "Plugins > Add New".
2. Make sure the site meets the requirements: WordPress 7.0 or newer, PHP 8.5 or newer, MariaDB 12.3+ or MySQL 9.7+ with InnoDB and JSON functions (older database servers from MariaDB 10.6 / MySQL 8.0 work with a warning in the system status), and the PHP extension intl (without it the system status shows a warning, and certificate checksums and import fingerprints can differ between servers).
3. Activate the plugin. If a requirement is not met, activation stops with a message and nothing is changed.

Multisite: activate Course Captain on each site individually. Network activation is blocked.

== Frequently Asked Questions ==

= What happens to my data when I delete the plugin? =

By default all course data stays in the database, so a later reinstall continues where you left off. Only scheduled tasks, temporary data and rewrite rules are removed. Data is deleted only if an administrator explicitly enabled and confirmed the setting "Delete data on uninstall" beforehand.

= Does the plugin need WooCommerce or an add-on? =

No. Course Captain works without any other plugin.

= Where do I get support? =

Support for the free plugin is given in the WordPress.org support forum of this plugin. Please never post passwords, database dumps or personal data there. Questions about a commercial add-on belong to the support channel of that add-on.

= How do I report a security issue? =

Please do not report security issues in the forum. Report them privately to the security contact named in the plugin repository.

== Changelog ==

= 1.0.0-dev =
* Plugin core: requirements check, activation and deactivation, network activation blocked, uninstall manifest.
* Courses, modules, lessons, course revisions, publish check with stable codes.
* Question bank, exams with blueprints and attempt snapshots, smart trainer, flashcards, favorites.
* Course access, groups and seats, learning area, notifications with opt-out, basic certificates.
* Privacy exporter and eraser, retention jobs, Site Health checks, audit log.

== Upgrade Notice ==

= 1.0.0-dev =
Development build, not for production sites.

== Privacy ==

Course Captain stores personal data of logged-in learners in its own database tables: learning progress, learning answers, exam attempts, course access and its history, group seats and invitation addresses, favorites, notifications and certificates. Administrative actions are written to an audit log. The plugin sets no cookies of its own and does not send telemetry.

Personal data can be exported and erased with the WordPress privacy tools (Tools > Export Personal Data / Erase Personal Data). Erasure keeps the audit log and course content. Learning answers are deleted automatically 2 years after they were given; invitation addresses 30 days after an invitation expired; certificates 10 years after their last revision.

== External services ==

The plugin itself does not call any external services. If authors add a video lesson with an address on another host (for example a video platform), the browser of the learner loads that video from the host. The host then receives the IP address and browser data of the learner. In the privacy mode "click to load" nothing is loaded before the learner clicks "Load video" (or agrees in a consent tool that supports the WP Consent API). Which hosts are used depends on the content of the site.

Video platforms that are embedded:

* YouTube (Google Ireland Limited / Google LLC): embedded from youtube-nocookie.com. Transferred: IP address, browser data, the page that embeds the video. Terms: https://www.youtube.com/t/terms, privacy policy: https://policies.google.com/privacy
* Vimeo (Vimeo.com, Inc.): embedded from player.vimeo.com with "do not track" (dnt=1). Transferred: IP address, browser data, the page that embeds the video. Terms: https://vimeo.com/terms, privacy policy: https://vimeo.com/privacy
* Video files on other hosts are played with the browser's video player; the host receives IP address and browser data.
