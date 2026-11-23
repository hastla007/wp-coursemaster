<?php
/**
 * PSR-4 autoloader for the CourseCaptain\ namespace.
 *
 * Course Captain Free ships without Composer runtime dependencies (R0-18 T-6),
 * so production code is loaded by this small autoloader. Composer's own
 * autoloader (same PSR-4 mapping) is used only for development and tests.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'CourseCaptain\\';
		if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		if ( '' === $relative || 1 !== preg_match( '/^[A-Za-z0-9_\\\\]+$/', $relative ) ) {
			return;
		}

		$file = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
