<?php
/**
 * Static scan of storage keys used in PHP code (REC-020, KOMP-130).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Support;

/**
 * Collects the keys passed to option, transient, meta and cron functions.
 *
 * A key argument is recognised as a string literal or as a class constant
 * (`self::X`, `static::X`, `Foo::X`) that can be resolved via reflection.
 */
final class StorageKeyScanner {

	/**
	 * Storage functions and the zero-based index of their key argument.
	 *
	 * @var array<string, int>
	 */
	public const FUNCTIONS = array(
		'get_option'               => 0,
		'update_option'            => 0,
		'add_option'               => 0,
		'delete_option'            => 0,
		'get_site_option'          => 0,
		'update_site_option'       => 0,
		'add_site_option'          => 0,
		'delete_site_option'       => 0,
		'get_transient'            => 0,
		'set_transient'            => 0,
		'delete_transient'         => 0,
		'get_site_transient'       => 0,
		'set_site_transient'       => 0,
		'delete_site_transient'    => 0,
		'get_user_meta'            => 1,
		'update_user_meta'         => 1,
		'add_user_meta'            => 1,
		'delete_user_meta'         => 1,
		'get_user_option'          => 0,
		'update_user_option'       => 1,
		'delete_user_option'       => 1,
		'get_post_meta'            => 1,
		'update_post_meta'         => 1,
		'add_post_meta'            => 1,
		'delete_post_meta'         => 1,
		'delete_post_meta_by_key'  => 0,
		'wp_schedule_event'        => 2,
		'wp_schedule_single_event' => 1,
		'wp_next_scheduled'        => 0,
		'wp_unschedule_hook'       => 0,
		'wp_clear_scheduled_hook'  => 0,
	);

	/** Prefixes of own keys (R0-16 §2.5). */
	public const OWN_PREFIXES = array( 'ccap_', '_ccap_', 'course_captain_' );

	/**
	 * PHP files below the given paths.
	 *
	 * @param string[] $paths Files or directories.
	 * @phpstan-param list<string> $paths
	 * @return list<string>
	 */
	public static function php_files( array $paths ): array {
		$files = array();
		foreach ( $paths as $path ) {
			if ( is_file( $path ) ) {
				$files[] = $path;
				continue;
			}
			if ( ! is_dir( $path ) ) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file instanceof \SplFileInfo && 'php' === $file->getExtension() ) {
					$files[] = $file->getPathname();
				}
			}
		}
		sort( $files );
		return $files;
	}

	/**
	 * Own keys found in the given files.
	 *
	 * @param string[] $files PHP files.
	 * @phpstan-param list<string> $files
	 * @return array<string, list<string>> Key => list of "file:line".
	 */
	public static function own_keys( array $files ): array {
		$keys = array();
		foreach ( $files as $file ) {
			foreach ( self::keys_in_file( $file ) as $found ) {
				list( $key, $line ) = $found;
				foreach ( self::OWN_PREFIXES as $prefix ) {
					if ( str_starts_with( $key, $prefix ) ) {
						$keys[ $key ][] = $file . ':' . $line;
						break;
					}
				}
			}
		}
		ksort( $keys );
		return $keys;
	}

	/**
	 * All resolvable keys of one file.
	 *
	 * @param string $file PHP file.
	 * @return list<array{0: string, 1: int}>
	 */
	public static function keys_in_file( string $file ): array {
		$code   = (string) file_get_contents( $file );
		$tokens = token_get_all( $code );
		$count  = count( $tokens );
		$found  = array();

		$namespace = '';
		$class     = '';

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				continue;
			}

			if ( T_NAMESPACE === $token[0] ) {
				$namespace = self::read_name( $tokens, $i + 1 );
				continue;
			}
			if ( T_CLASS === $token[0] || T_INTERFACE === $token[0] || T_TRAIT === $token[0] || T_ENUM === $token[0] ) {
				$next = self::next_significant( $tokens, $i + 1 );
				if ( null !== $next && is_array( $tokens[ $next ] ) && T_STRING === $tokens[ $next ][0] ) {
					$class = ( '' === $namespace ? '' : $namespace . '\\' ) . $tokens[ $next ][1];
				}
				continue;
			}

			if ( T_STRING !== $token[0] || ! array_key_exists( strtolower( $token[1] ), self::FUNCTIONS ) ) {
				continue;
			}
			$prev = self::previous_significant( $tokens, $i - 1 );
			if ( null !== $prev && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR ), true ) ) {
				continue;
			}
			$open = self::next_significant( $tokens, $i + 1 );
			if ( null === $open || '(' !== $tokens[ $open ] ) {
				continue;
			}

			$argument = self::argument_tokens( $tokens, $open, self::FUNCTIONS[ strtolower( $token[1] ) ] );
			$key      = self::resolve( $argument, $namespace, $class );
			if ( null !== $key ) {
				$found[] = array( $key, (int) $token[2] );
			}
		}

		return $found;
	}

	/**
	 * Tokens of the n-th argument of a call.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $open   Index of the opening parenthesis.
	 * @param int               $index  Argument index.
	 * @return list<mixed>
	 */
	private static function argument_tokens( array $tokens, int $open, int $index ): array {
		$depth    = 0;
		$position = 0;
		$result   = array();
		$count    = count( $tokens );

		for ( $i = $open + 1; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( in_array( $token, array( '(', '[', '{' ), true ) || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
				++$depth;
			} elseif ( in_array( $token, array( ')', ']', '}' ), true ) ) {
				if ( 0 === $depth ) {
					break;
				}
				--$depth;
			} elseif ( ',' === $token && 0 === $depth ) {
				++$position;
				continue;
			}
			if ( $position === $index && ! ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) ) {
				$result[] = $token;
			}
		}
		return $result;
	}

	/**
	 * Resolve an argument to a string, if it is a literal or a class constant.
	 *
	 * @param mixed[] $argument          Argument tokens.
	 * @param string  $current_namespace Current namespace.
	 * @param string  $current_class     Current class.
	 * @phpstan-param list<mixed> $argument
	 * @return string|null
	 */
	private static function resolve( array $argument, string $current_namespace, string $current_class ): ?string {
		if ( 1 === count( $argument ) && is_array( $argument[0] ) && T_CONSTANT_ENCAPSED_STRING === $argument[0][0] ) {
			return stripcslashes( substr( $argument[0][1], 1, -1 ) );
		}

		if ( 3 === count( $argument ) && is_array( $argument[0] ) && is_array( $argument[1] ) && T_DOUBLE_COLON === $argument[1][0] && is_array( $argument[2] ) && T_STRING === $argument[2][0] ) {
			$owner = $argument[0][1];
			if ( in_array( strtolower( $owner ), array( 'self', 'static' ), true ) ) {
				$owner = $current_class;
			} elseif ( ! str_contains( $owner, '\\' ) && '' !== $current_namespace ) {
				$owner = $current_namespace . '\\' . $owner;
			}
			$owner    = ltrim( $owner, '\\' );
			$constant = $owner . '::' . $argument[2][1];
			if ( '' !== $owner && ( class_exists( $owner ) || interface_exists( $owner ) ) && defined( $constant ) ) {
				$value = constant( $constant );
				return is_string( $value ) ? $value : null;
			}
		}

		return null;
	}

	/**
	 * Read a (qualified) name starting at an index.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $start  Start index.
	 * @return string
	 */
	private static function read_name( array $tokens, int $start ): string {
		$next = self::next_significant( $tokens, $start );
		if ( null === $next || ! is_array( $tokens[ $next ] ) ) {
			return '';
		}
		return in_array( $tokens[ $next ][0], array( T_NAME_QUALIFIED, T_STRING ), true ) ? (string) $tokens[ $next ][1] : '';
	}

	/**
	 * Next non-whitespace token index.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $start  Start index.
	 * @return int|null
	 */
	private static function next_significant( array $tokens, int $start ): ?int {
		$count = count( $tokens );
		for ( $i = $start; $i < $count; $i++ ) {
			if ( ! ( is_array( $tokens[ $i ] ) && in_array( $tokens[ $i ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Previous non-whitespace token index.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $start  Start index.
	 * @return int|null
	 */
	private static function previous_significant( array $tokens, int $start ): ?int {
		for ( $i = $start; $i >= 0; $i-- ) {
			if ( ! ( is_array( $tokens[ $i ] ) && in_array( $tokens[ $i ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) ) {
				return $i;
			}
		}
		return null;
	}
}
