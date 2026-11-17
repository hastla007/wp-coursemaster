<?php
/**
 * WP-CLI-Vorlauf (`--require`): Course-Captain-Klassen kommen aus dem
 * installierten Plugin der Test-Site, nicht aus dem Repository.
 *
 * `vendor/bin/wp` lädt den Composer-Autoloader des Repositorys, der
 * `CourseCaptain\` auf `<repo>/src` abbildet und vor dem Plugin-Autoloader
 * registriert ist. Ohne diese Datei liefe die Test-Site mit dem Quellcode
 * des Repositorys (in Worktrees mit verlinktem `vendor/` sogar mit dem
 * Stand eines anderen Checkouts) statt mit dem Release-ZIP. Der Composer-
 * Autoloader steht erst nach den `--require`-Dateien bereit, deshalb
 * greift die Korrektur am WP-CLI-Hook `before_wp_load`.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

if ( class_exists( 'WP_CLI' ) && ! defined( 'CCAP_UX_ISOLATED' ) ) {
	define( 'CCAP_UX_ISOLATED', true );
	\WP_CLI::add_hook(
		'before_wp_load',
		static function (): void {
			if ( ! class_exists( \Composer\Autoload\ClassLoader::class, false ) ) {
				return;
			}
			foreach ( \Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader ) {
				if ( isset( $loader->getPrefixesPsr4()['CourseCaptain\\'] ) ) {
					$loader->setPsr4( 'CourseCaptain\\', array() );
				}
			}
		}
	);
}
