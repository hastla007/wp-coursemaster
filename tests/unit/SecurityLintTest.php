<?php
/**
 * Gate C-13 (R0-18 §2.1, R0-17): token-based regression guard for the
 * Semgrep rules in tools/semgrep/cc-rules.yml, so the high-severity rules
 * also hold in every unit run without the Semgrep CLI.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Reads local source files.

/**
 * Shipped PHP/JS without dangerous sinks.
 *
 * @group R1-18
 * @group C-13
 */
final class SecurityLintTest extends TestCase {

	/**
	 * Shipped PHP files.
	 *
	 * @return list<string>
	 */
	private static function php_files(): array {
		$root  = dirname( __DIR__, 2 );
		$files = array( $root . '/course-captain.php', $root . '/uninstall.php' );
		$it    = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( $file instanceof \SplFileInfo && 'php' === $file->getExtension() ) {
				$files[] = $file->getPathname();
			}
		}
		sort( $files );
		return $files;
	}

	/**
	 * Called function names (lower case) with line numbers, from PHP tokens.
	 *
	 * @param string $path File.
	 * @return list<array{0: string, 1: int, 2: string}> name, line, previous token
	 */
	private static function calls( string $path ): array {
		$tokens = \PhpToken::tokenize( (string) file_get_contents( $path ) );
		$out    = array();
		$count  = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! $tokens[ $i ]->is( array( T_STRING, T_EVAL, T_NAME_FULLY_QUALIFIED ) ) ) {
				continue;
			}
			$next = $i + 1;
			while ( $next < $count && $tokens[ $next ]->isIgnorable() ) {
				++$next;
			}
			if ( $next >= $count || '(' !== $tokens[ $next ]->text ) {
				continue;
			}
			$prev = $i - 1;
			while ( $prev >= 0 && $tokens[ $prev ]->isIgnorable() ) {
				--$prev;
			}
			$out[] = array( strtolower( ltrim( $tokens[ $i ]->text, '\\' ) ), $tokens[ $i ]->line, $prev >= 0 ? $tokens[ $prev ]->text : '' );
		}
		return $out;
	}

	/**
	 * Rules cc-eval-shell, cc-unserialize, cc-zip-extract-to,
	 * cc-remote-request-unsafe (ERROR = "hoch"): none in shipped PHP.
	 */
	public function test_C_13_no_high_severity_sinks_in_shipped_php(): void {
		$forbidden = array( 'eval', 'exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'unserialize', 'extractto', 'wp_remote_get', 'wp_remote_post', 'wp_remote_request', 'wp_remote_head', 'create_function' );
		$found     = array();
		$root      = dirname( __DIR__, 2 ) . '/';
		foreach ( self::php_files() as $file ) {
			foreach ( self::calls( $file ) as [ $name, $line, $prev ] ) {
				if ( 'function' === strtolower( $prev ) || 'new' === strtolower( $prev ) ) {
					continue;
				}
				// Methods named like a sink (e.g. Database::execute()) are not the sink, except extractTo().
				if ( in_array( $prev, array( '->', '::', '?->' ), true ) && 'extractto' !== $name ) {
					continue;
				}
				if ( in_array( $name, $forbidden, true ) ) {
					$found[] = substr( $file, strlen( $root ) ) . ':' . $line . ' ' . $name;
				}
			}
		}
		self::assertSame( array(), $found );
	}

	/**
	 * Rule cc-js-innerhtml / cc-js-eval: shipped scripts set text, never HTML
	 * (R0-17 T-05).
	 */
	public function test_C_13_no_html_sinks_in_shipped_js(): void {
		$root  = dirname( __DIR__, 2 );
		$files = array_merge( glob( $root . '/assets/js/*.js' ) ? glob( $root . '/assets/js/*.js' ) : array(), glob( $root . '/blocks/*/*.js' ) ? glob( $root . '/blocks/*/*.js' ) : array() );
		self::assertNotSame( array(), $files );
		$found = array();
		foreach ( $files as $file ) {
			foreach ( (array) file( $file ) as $n => $line ) {
				if ( 1 === preg_match( '/\.(innerHTML|outerHTML)\s*=|insertAdjacentHTML\s*\(|document\.write\s*\(|\beval\s*\(|new\s+Function\s*\(/', (string) $line ) ) {
					$found[] = basename( $file ) . ':' . ( $n + 1 );
				}
			}
		}
		self::assertSame( array(), $found );
	}

	/**
	 * The guard itself detects a sink (negative test).
	 */
	public function test_C_13_guard_detects_sinks(): void {
		$file = sys_get_temp_dir() . '/ccap-seclint-' . bin2hex( random_bytes( 4 ) ) . '.php';
		file_put_contents( $file, "<?php\n\$r = wp_remote_get( \$u );\n\$z->extractTo( \$d );\n\$db->execute( 'x' );\n" );
		try {
			$names = array_map( static fn( array $c ): string => $c[0] . '@' . $c[2], self::calls( $file ) );
		} finally {
			unlink( $file );
		}
		self::assertContains( 'wp_remote_get@=', $names );
		self::assertContains( 'extractto@->', $names );
		self::assertContains( 'execute@->', $names );
	}

	/**
	 * Semgrep rule file and its rule tests are present (R0-18 §2.1: rules in
	 * the repository, not fetched from a registry).
	 */
	public function test_C_13_semgrep_rules_in_repository(): void {
		$dir   = dirname( __DIR__, 2 ) . '/tools/semgrep';
		$rules = (string) file_get_contents( $dir . '/cc-rules.yml' );
		foreach ( array( 'cc-wpdb-interpolated-sql', 'cc-superglobal-unsanitized', 'cc-rest-route-public', 'cc-remote-request-unsafe', 'cc-zip-extract-to', 'cc-unserialize', 'cc-weak-token-random', 'cc-eval-shell', 'cc-echo-unescaped-request', 'cc-js-innerhtml', 'cc-js-eval' ) as $id ) {
			self::assertStringContainsString( 'id: ' . $id, $rules );
		}
		self::assertFileExists( $dir . '/cc-rules.php' );
		self::assertFileExists( $dir . '/cc-rules.js' );
	}
}
