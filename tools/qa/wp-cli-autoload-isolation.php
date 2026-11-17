<?php
/**
 * WP-CLI preamble (`--require`) shared by the release and usability scripts:
 * Course Captain classes must come from the plugin installed on the test site,
 * never from the repository (finding B-07; J-17, R0-35 §3.4).
 *
 * `vendor/bin/wp` loads the repository's Composer autoloader, which maps
 * `CourseCaptain\` to `<repo>/src` and is registered before the plugin's own
 * `src/autoload.php`. Without this file a test site would run the repository's
 * source code (in worktrees with a symlinked `vendor/` even another
 * checkout's) instead of the release ZIP, and a file missing from the ZIP
 * would go unnoticed.
 *
 * 1. Isolation (always): at the WP-CLI hook `before_wp_load` (the Composer
 *    loader is not yet available while `--require` files load), every
 *    registered Composer loader loses its `CourseCaptain\` PSR-4 mapping and
 *    any `CourseCaptain\` classmap entries (optimised autoloader).
 * 2. Guard (opt-in, environment `CCAP_AUTOLOAD_GUARD=1`): when a WP-CLI
 *    process that loaded WordPress ends, every declared `CourseCaptain\`
 *    class, interface, trait and enum must have been loaded from
 *    `WP_PLUGIN_DIR/course-captain/`. Otherwise the offending files are
 *    printed to STDERR and the process exits with status 1. With
 *    `CCAP_AUTOLOAD_GUARD_LOG=<file>` one line per process is appended
 *    (`OK <count> <plugin dir> :: <command>` or `FAIL …`), so the caller can
 *    prove that plugin code was actually loaded and where it came from.
 *    The guard is opt-in because the usability seeder declares its own
 *    classes under `CourseCaptain\Tools\…` from `tools/`.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

if ( class_exists( 'WP_CLI' ) && ! defined( 'CCAP_AUTOLOAD_ISOLATED' ) ) {
	define( 'CCAP_AUTOLOAD_ISOLATED', true );

	\WP_CLI::add_hook(
		'before_wp_load',
		static function (): void {
			if ( ! class_exists( \Composer\Autoload\ClassLoader::class, false ) ) {
				return;
			}
			$strip_classmap = \Closure::bind(
				static function ( \Composer\Autoload\ClassLoader $loader ): void {
					foreach ( array_keys( $loader->classMap ) as $class_name ) {
						if ( str_starts_with( (string) $class_name, 'CourseCaptain\\' ) ) {
							unset( $loader->classMap[ $class_name ] );
						}
					}
				},
				null,
				\Composer\Autoload\ClassLoader::class
			);
			foreach ( \Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader ) {
				if ( isset( $loader->getPrefixesPsr4()['CourseCaptain\\'] ) ) {
					$loader->setPsr4( 'CourseCaptain\\', array() );
				}
				$strip_classmap( $loader );
			}
		}
	);

	if ( '1' === getenv( 'CCAP_AUTOLOAD_GUARD' ) ) {
		\WP_CLI::add_hook(
			'after_wp_load',
			static function (): void {
				// Resolved now (symlinked /tmp, e.g. macOS): the plugin directory may not exist yet
				// (`plugin install`) or be gone at shutdown (`plugin uninstall`), WP_PLUGIN_DIR exists.
				$raw      = rtrim( (string) WP_PLUGIN_DIR, '/' ) . '/course-captain/';
				$resolved = realpath( (string) WP_PLUGIN_DIR );
				$allowed  = array_unique( array_filter( array( $raw, false === $resolved ? '' : $resolved . '/course-captain/' ) ) );
				register_shutdown_function(
					static function () use ( $allowed, $raw ): void {
						$names   = array_merge( get_declared_classes(), get_declared_interfaces(), get_declared_traits() );
						$count   = 0;
						$outside = array();
						foreach ( $names as $name ) {
							if ( ! str_starts_with( $name, 'CourseCaptain\\' ) ) {
								continue;
							}
							++$count;
							$file = (string) ( new \ReflectionClass( $name ) )->getFileName();
							$ok   = false;
							foreach ( $allowed as $prefix ) {
								$ok = $ok || str_starts_with( $file, $prefix );
							}
							if ( ! $ok ) {
								$outside[] = $name . ' <- ' . ( '' === $file ? '(no file)' : $file );
							}
						}
						$command = implode( ' ', array_filter( array_slice( (array) ( $GLOBALS['argv'] ?? array() ), 1 ), static fn( $a ): bool => ! str_starts_with( (string) $a, '--' ) ) );
						$line    = ( array() === $outside ? 'OK' : 'FAIL' ) . " $count $raw :: $command";
						$log     = getenv( 'CCAP_AUTOLOAD_GUARD_LOG' );
						if ( is_string( $log ) && '' !== $log ) {
							file_put_contents( $log, $line . "\n", FILE_APPEND );
						}
						if ( array() !== $outside ) {
							fwrite( STDERR, 'FAIL: ' . count( $outside ) . " Course Captain class(es) loaded from outside $raw:\n  " . implode( "\n  ", $outside ) . "\n" );
							exit( 1 );
						}
					}
				);
			}
		);
	}
}
