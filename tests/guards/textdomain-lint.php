<?php
/**
 * Text domain lint (R0-18 J-03, R0-15 §3.8, E-121).
 *
 * Checks every gettext call in shipped PHP code: the text domain is the
 * literal `course-captain`, source strings are English (heuristic: no German
 * umlauts or sharp s) and `languages/course-captain.pot` exists.
 *
 * Usage: php tests/guards/textdomain-lint.php [--root=<dir>]
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Guards;

/**
 * Token based lint.
 */
final class TextdomainLint {

	public const DOMAIN = 'course-captain';

	/**
	 * Gettext functions => zero-based index of the text domain argument.
	 *
	 * @var array<string, int>
	 */
	public const FUNCTIONS = array(
		'__'                     => 1,
		'_e'                     => 1,
		'esc_html__'             => 1,
		'esc_html_e'             => 1,
		'esc_attr__'             => 1,
		'esc_attr_e'             => 1,
		'_x'                     => 2,
		'_ex'                    => 2,
		'esc_html_x'             => 2,
		'esc_attr_x'             => 2,
		'_n'                     => 3,
		'_nx'                    => 4,
		'_n_noop'                => 2,
		'_nx_noop'               => 3,
		'translate'              => 1,
		'load_plugin_textdomain' => 0,
	);

	/**
	 * Constructor.
	 *
	 * @param string $root Plugin root.
	 */
	public function __construct( private string $root ) {
		$this->root = rtrim( $root, '/' );
	}

	/**
	 * Shipped PHP files relative to the root.
	 *
	 * @return list<string>
	 */
	public function files(): array {
		$files = array();
		foreach ( array( 'course-captain.php', 'uninstall.php' ) as $file ) {
			if ( is_file( $this->root . '/' . $file ) ) {
				$files[] = $file;
			}
		}
		foreach ( array( 'src', 'templates', 'blocks' ) as $directory ) {
			if ( ! is_dir( $this->root . '/' . $directory ) ) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->root . '/' . $directory, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file instanceof \SplFileInfo && 'php' === $file->getExtension() ) {
					$files[] = substr( $file->getPathname(), strlen( $this->root ) + 1 );
				}
			}
		}
		sort( $files );
		return $files;
	}

	/**
	 * Run the lint.
	 *
	 * @return list<string> Problems.
	 */
	public function run(): array {
		$problems = array();
		if ( ! is_file( $this->root . '/languages/' . self::DOMAIN . '.pot' ) ) {
			$problems[] = 'languages/' . self::DOMAIN . '.pot is missing.';
		}

		foreach ( $this->files() as $file ) {
			$tokens = token_get_all( (string) file_get_contents( $this->root . '/' . $file ) );
			$count  = count( $tokens );
			for ( $i = 0; $i < $count; $i++ ) {
				$token = $tokens[ $i ];
				if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( self::FUNCTIONS[ $token[1] ] ) ) {
					continue;
				}
				$prev = $i - 1;
				while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
					--$prev;
				}
				if ( $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
					continue;
				}
				$arguments = self::arguments( $tokens, $i + 1 );
				if ( null === $arguments ) {
					continue;
				}
				$where  = $file . ':' . $token[2];
				$domain = $arguments[ self::FUNCTIONS[ $token[1] ] ] ?? array();
				if ( 1 !== count( $domain ) || ! is_array( $domain[0] ) || T_CONSTANT_ENCAPSED_STRING !== $domain[0][0] ) {
					$problems[] = $where . ': text domain of ' . $token[1] . '() must be a string literal.';
				} elseif ( self::DOMAIN !== substr( $domain[0][1], 1, -1 ) ) {
					$problems[] = $where . ': text domain ' . $domain[0][1] . ' is not "' . self::DOMAIN . '".';
				}
				if ( 'load_plugin_textdomain' === $token[1] ) {
					continue;
				}
				foreach ( $arguments[0] ?? array() as $part ) {
					if ( is_array( $part ) && T_CONSTANT_ENCAPSED_STRING === $part[0] && 1 === preg_match( '/[äöüÄÖÜß]/u', $part[1] ) ) {
						$problems[] = $where . ': source string is not English (E-121): ' . $part[1];
					}
				}
			}
		}
		return $problems;
	}

	/**
	 * Arguments (significant tokens) of a call whose name ends before $start.
	 *
	 * @param array<int, mixed> $tokens Tokens.
	 * @param int               $start  Index after the function name.
	 * @return list<list<mixed>>|null
	 */
	private static function arguments( array $tokens, int $start ): ?array {
		$count = count( $tokens );
		$i     = $start;
		while ( $i < $count && is_array( $tokens[ $i ] ) && T_WHITESPACE === $tokens[ $i ][0] ) {
			++$i;
		}
		if ( $i >= $count || '(' !== $tokens[ $i ] ) {
			return null;
		}
		$arguments = array( array() );
		$depth     = 0;
		for ( ++$i; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( in_array( $token, array( '(', '[', '{' ), true ) ) {
				++$depth;
			} elseif ( in_array( $token, array( ')', ']', '}' ), true ) ) {
				if ( 0 === $depth ) {
					break;
				}
				--$depth;
			} elseif ( ',' === $token && 0 === $depth ) {
				$arguments[] = array();
				continue;
			}
			if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$arguments[ count( $arguments ) - 1 ][] = $token;
		}
		return $arguments;
	}

	/**
	 * CLI entry point.
	 *
	 * @return int Exit code.
	 */
	public static function main(): int {
		$options = getopt( '', array( 'root:' ) );
		$root    = is_array( $options ) && is_string( $options['root'] ?? null ) ? $options['root'] : dirname( __DIR__, 2 );
		$lint    = new self( $root );
		$result  = $lint->run();
		foreach ( $result as $problem ) {
			fwrite( STDERR, 'textdomain-lint: ' . $problem . PHP_EOL );
		}
		fwrite( STDOUT, sprintf( 'textdomain-lint: %d files, %d problems', count( $lint->files() ), count( $result ) ) . PHP_EOL );
		return array() === $result ? 0 : 1;
	}
}

if ( PHP_SAPI === 'cli' && realpath( (string) ( get_included_files()[0] ?? '' ) ) === __FILE__ ) {
	exit( TextdomainLint::main() );
}
