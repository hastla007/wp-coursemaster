<?php
/**
 * MS-008 (R0-25 §4.2, MS-R-05): static check of network-wide user meta.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Tests\Support\StorageKeyScanner;

/**
 * Values per learner belong in per-site user options
 * (`update_user_option()`, key with blog prefix). `*_user_meta()` with an
 * own key is only allowed for the network-wide exception list
 * `UninstallManifest::USER_META`; a key of `UninstallManifest::USER_OPTIONS`
 * is never written or read as plain user meta.
 *
 * @group R1-02
 * @group MS-008
 */
final class UserMetaScopeTest extends TestCase {

	private const META_FUNCTIONS = array( 'get_user_meta', 'update_user_meta', 'add_user_meta', 'delete_user_meta' );

	/**
	 * MS-008: no `*_user_meta()` call with an own key outside the exception list.
	 */
	public function test_MS_008_user_meta_only_for_the_exception_list(): void {
		$root  = self::root();
		$calls = array();
		foreach ( StorageKeyScanner::php_files( array( $root . '/course-captain.php', $root . '/uninstall.php', $root . '/src' ) ) as $file ) {
			$calls = array_merge( $calls, self::meta_calls( $file ) );
		}
		self::assertNotSame( array(), $calls, 'scanner finds the known calls' );

		$allowed    = UninstallManifest::user_meta();
		$violations = array();
		foreach ( $calls as $call ) {
			if ( null === $call['key'] ) {
				$violations[] = $call['at'] . ': ' . $call['function'] . ' with a key the check cannot resolve';
				continue;
			}
			$own = false;
			foreach ( StorageKeyScanner::OWN_PREFIXES as $prefix ) {
				$own = $own || str_starts_with( $call['key'], $prefix );
			}
			if ( $own && ! in_array( $call['key'], $allowed, true ) ) {
				$violations[] = $call['at'] . ': ' . $call['function'] . ' ' . $call['key'];
			}
		}
		self::assertSame( array(), $violations, 'per-site values use update_user_option() (MS-R-05)' );
	}

	/**
	 * MS-008: exception list and per-site user options do not overlap, and
	 * every listed exception is really used.
	 */
	public function test_MS_008_exception_list_is_disjoint_and_used(): void {
		self::assertSame( array(), array_values( array_intersect( UninstallManifest::user_meta(), UninstallManifest::user_options() ) ) );
		$root = self::root();
		$used = array();
		foreach ( StorageKeyScanner::php_files( array( $root . '/src' ) ) as $file ) {
			foreach ( self::meta_calls( $file ) as $call ) {
				$used[] = (string) $call['key'];
			}
		}
		foreach ( UninstallManifest::user_meta() as $key ) {
			self::assertContains( $key, $used, $key . ' is listed as network-wide user meta' );
		}
	}

	/**
	 * The scanner resolves literal keys and class constants, and reports
	 * dynamic keys as unresolved.
	 */
	public function test_scanner_resolves_literals_constants_and_flags_dynamic_keys(): void {
		$file = (string) tempnam( sys_get_temp_dir(), 'ccap-ms008-' );
		file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
			$file,
			"<?php\nnamespace CourseCaptain\\Kernel;\nclass Fixture {\n\tfunction a() {\n\t\tupdate_user_meta( \$u, 'ccap_x', 1 );\n\t\tget_user_meta( \$u, UninstallManifest::GENERAL_SETTINGS_OPTION, true );\n\t\tdelete_user_meta( \$u, \$key );\n\t\t\$o->get_user_meta( 1, 'ccap_y' );\n\t}\n}\n"
		);
		try {
			$calls = self::meta_calls( $file );
		} finally {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture.
		}
		self::assertSame( array( 'ccap_x', UninstallManifest::GENERAL_SETTINGS_OPTION, null ), array_column( $calls, 'key' ) );
	}

	/**
	 * `*_user_meta()` calls of a file with their resolved key.
	 *
	 * @param string $file PHP file.
	 * @return list<array{function: string, key: string|null, at: string}>
	 */
	private static function meta_calls( string $file ): array {
		$tokens    = token_get_all( (string) file_get_contents( $file ) );
		$count     = count( $tokens );
		$namespace = '';
		$class     = '';
		$calls     = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				continue;
			}
			if ( T_NAMESPACE === $token[0] ) {
				$namespace = self::name_after( $tokens, $i + 1 );
				continue;
			}
			if ( T_CLASS === $token[0] && '' === $class ) {
				$class = self::name_after( $tokens, $i + 1 );
				continue;
			}
			if ( T_STRING !== $token[0] && T_NAME_FULLY_QUALIFIED !== $token[0] ) {
				continue;
			}
			$function = ltrim( strtolower( $token[1] ), '\\' );
			if ( ! in_array( $function, self::META_FUNCTIONS, true ) ) {
				continue;
			}
			$prev = self::significant( $tokens, $i - 1, -1 );
			if ( null !== $prev && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
				continue;
			}
			$open = self::significant( $tokens, $i + 1, 1 );
			if ( null === $open || '(' !== $tokens[ $open ] ) {
				continue;
			}
			$calls[] = array(
				'function' => $function,
				'key'      => self::second_argument_key( $tokens, $open, $namespace, $class ),
				'at'       => basename( $file ) . ':' . $token[2],
			);
		}
		return $calls;
	}

	/**
	 * Key of the second argument: string literal or resolvable class constant.
	 *
	 * @param array<int, mixed> $tokens    Tokens.
	 * @param int               $open      Index of the opening parenthesis.
	 * @param string            $file_ns   Namespace of the file.
	 * @param string            $file_cls  Class of the file.
	 * @return string|null
	 */
	private static function second_argument_key( array $tokens, int $open, string $file_ns, string $file_cls ): ?string {
		$depth = 0;
		$arg   = 0;
		$parts = array();
		$count = count( $tokens );
		for ( $i = $open + 1; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			$text  = is_array( $token ) ? $token[1] : $token;
			if ( in_array( $text, array( '(', '[', '{' ), true ) ) {
				++$depth;
			} elseif ( in_array( $text, array( ')', ']', '}' ), true ) ) {
				if ( 0 === $depth ) {
					break;
				}
				--$depth;
			} elseif ( ',' === $text && 0 === $depth ) {
				++$arg;
				continue;
			}
			if ( 1 === $arg && ! ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT ), true ) ) ) {
				$parts[] = $token;
			}
		}
		if ( 1 === count( $parts ) && is_array( $parts[0] ) && T_CONSTANT_ENCAPSED_STRING === $parts[0][0] ) {
			return stripslashes( substr( $parts[0][1], 1, -1 ) );
		}
		if ( 3 === count( $parts ) && is_array( $parts[0] ) && is_array( $parts[1] ) && T_DOUBLE_COLON === $parts[1][0] && is_array( $parts[2] ) && T_STRING === $parts[2][0] ) {
			$owner = $parts[0][1];
			if ( in_array( strtolower( $owner ), array( 'self', 'static' ), true ) ) {
				$owner = $file_ns . '\\' . $file_cls;
			} elseif ( ! str_starts_with( $owner, '\\' ) ) {
				$owner = $file_ns . '\\' . $owner;
			}
			$constant = ltrim( $owner, '\\' ) . '::' . $parts[2][1];
			return defined( $constant ) && is_string( constant( $constant ) ) ? (string) constant( $constant ) : null;
		}
		return null;
	}

	/**
	 * Name (possibly qualified) after a keyword.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $from   Start index.
	 * @return string
	 */
	private static function name_after( array $tokens, int $from ): string {
		$index = self::significant( $tokens, $from, 1 );
		if ( null === $index || ! is_array( $tokens[ $index ] ) ) {
			return '';
		}
		return in_array( $tokens[ $index ][0], array( T_STRING, T_NAME_QUALIFIED ), true ) ? (string) $tokens[ $index ][1] : '';
	}

	/**
	 * Next significant token index in a direction.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $from   Start index.
	 * @param int               $step   1 or -1.
	 * @return int|null
	 */
	private static function significant( array $tokens, int $from, int $step ): ?int {
		$count = count( $tokens );
		for ( $i = $from; $i >= 0 && $i < $count; $i += $step ) {
			if ( ! ( is_array( $tokens[ $i ] ) && in_array( $tokens[ $i ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) ) {
				return $i;
			}
		}
		return null;
	}
}
