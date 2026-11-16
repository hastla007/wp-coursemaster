=== Course Captain ===
Contributors: coursecaptain
Tags: lms, courses, e-learning, quiz, education
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 1.0.0-dev
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Neutral learning management for WordPress: courses, lessons, exams and certificates without required add-ons.

== Description ==

Course Captain is a learning management plugin for any kind of course provider. It works on its own: WooCommerce and other plugins are optional.

This is a development build. Course features are added step by step; this version contains the plugin core (activation, requirements check, uninstall).

== Installation ==

1. Install the plugin from the plugin directory or upload the ZIP file under "Plugins > Add New".
2. Make sure the site meets the requirements: WordPress 7.0 or newer, PHP 8.3 or newer, MariaDB 10.6+ or MySQL 8.0+ with InnoDB and JSON functions (MariaDB 10.11+ or MySQL 8.4+ recommended).
3. Activate the plugin. If a requirement is not met, activation stops with a message and nothing is changed.

Multisite: activate Course Captain on each site individually. Network activation is blocked.

== Frequently Asked Questions ==

= What happens to my data when I delete the plugin? =

By default all course data stays in the database, so a later reinstall continues where you left off. Only scheduled tasks, temporary data and rewrite rules are removed. Data is deleted only if an administrator explicitly enabled and confirmed the setting "Delete data on uninstall" beforehand.

= Does the plugin need WooCommerce or an add-on? =

No. Course Captain works without any other plugin.

== Changelog ==

= 1.0.0-dev =
* Plugin core: requirements check, activation and deactivation, network activation blocked, uninstall manifest.

== Upgrade Notice ==

= 1.0.0-dev =
Development build, not for production sites.

== Privacy ==

This version stores no personal data. It does not send telemetry.

== External services ==

The plugin does not call any external services.
