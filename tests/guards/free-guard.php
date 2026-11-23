<?php
/**
 * Free guard: no license client, no updater, no add-on probing in the Free core.
 *
 * Patterns G-1 ... G-14 of R0-39 §1.2 (extends R0-18 §2.5) plus G-15
 * (rights keys, SET-CC-01). Runs in CI job
 * J-10 on the source tree and in J-17 on the unpacked release ZIP.
 *
 * Usage:
 *   php tests/guards/free-guard.php [--root=<dir>] [--allow=<file>]
 *   php tests/guards/free-guard.php --self-test
 *
 * Exit code 0 = clean, 1 = findings or invalid allowlist, 2 = usage error.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Guards;

/**
 * The guard itself; also used by the unit tests.
 */
final class FreeGuard {

	/**
	 * Patterns per guard ID (R0-39 §1.2). G-12 and G-13 have extra rules.
	 *
	 * @var array<string, list<string>>
	 */
	public const PATTERNS = array(
		'G-1'  => array( '/\b(LicenseClient|LicenseManager|LicenseStatus|OptionStore)\b/' ),
		'G-2'  => array( '/WPLC\\\\Client|CourseCaptainPro\\\\Licensing|Captain\\\\Licensing/' ),
		'G-3'  => array( '#wplc/v1#', '/\bwplc_/i', '/\bWPLC_[A-Z_]+/' ),
		'G-4'  => array( '/Update URI\s*:/i' ),
		'G-5'  => array( '/pre_set_site_transient_update_plugins|[\'"]site_transient_update_plugins[\'"]|update_plugins_|[\'"]plugins_api[\'"]|upgrader_pre_download|upgrader_source_selection/' ),
		'G-6'  => array( '#api\.github\.com|github\.com/[^\'"]+/releases#' ),
		'G-7'  => array( '/entitlement/i' ),
		'G-8'  => array( '/license_key|licence_key|is_entitled|license_status|cache_expires_at/i' ),
		'G-9'  => array( '/CourseCaptainPro\\\\|course_captain_pro_|COURSE_CAPTAIN_PRO_/' ),
		'G-10' => array( '/[\'"]course-captain-pro[\'"]\s*\)/' ),
		'G-11' => array( '/delete_option\s*\(\s*[\'"]course_captain_pro/', '/LIKE\s+[\'"]course_captain_%/i' ),
		'G-12' => array( '/feedback_explanation|show_explanations/' ),
		// G-13 is a header rule, see self::check_header().
		'G-14' => array( '/learnboating|bootspruefung|\bbp_|kurswasser|boatingschool|\blbac_|\blbvc_/i' ),
		// G-15 (SET-CC-01/P6, E-346 S1): no rights keys of the license catalogue (`course.<feature>`, SET-CC-05) in the core.
		'G-15' => array( '/[\'"]course\.(drip_campaigns|reminders|certificate_designer|commerce|groups_advanced|explanations|advanced_assessment|gradebook|analytics|advanced_roles|multi_instructor|advanced_paths|content_workflows|custom_activities|frontend_builder|events|webhooks|connectors|white_label)[\'"]/' ),
	);

	/** Guard IDs that must never be allowlisted (R0-39 FG-07). */
	public const NOT_ALLOWABLE = array( 'G-7' );

	/** Scanned file extensions (R0-39 §1.2). */
	public const EXTENSIONS = array( 'php', 'js', 'json', 'pot', 'txt' );

	/** Scanned directories below the root. */
	public const DIRECTORIES = array( 'src', 'assets', 'blocks', 'templates', 'build' );

	/** Directory in which G-12 field names are allowed (storage, import, export). */
	public const G12_ALLOWED_DIRECTORY = 'src/Domain/Question/';

	/**
	 * Constructor.
	 *
	 * @param string $root  Plugin root (source tree or unpacked ZIP folder).
	 * @param string $allow Allowlist file, may not exist.
	 */
	public function __construct(
		private string $root,
		private string $allow = '',
	) {
		$this->root = rtrim( $root, '/' );
	}

	/**
	 * Files to scan, relative to the root.
	 *
	 * @return list<string>
	 */
	public function files(): array {
		$files = array();
		foreach ( array( 'course-captain.php', 'uninstall.php', 'readme.txt' ) as $file ) {
			if ( is_file( $this->root . '/' . $file ) ) {
				$files[] = $file;
			}
		}
		$pots = glob( $this->root . '/languages/*.pot' );
		foreach ( false === $pots ? array() : $pots as $pot ) {
			$files[] = substr( $pot, strlen( $this->root ) + 1 );
		}
		foreach ( self::DIRECTORIES as $directory ) {
			$path = $this->root . '/' . $directory;
			if ( ! is_dir( $path ) ) {
				continue;
			}
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file instanceof \SplFileInfo && $file->isFile() && in_array( strtolower( $file->getExtension() ), self::EXTENSIONS, true ) ) {
					$files[] = substr( $file->getPathname(), strlen( $this->root ) + 1 );
				}
			}
		}
		sort( $files );
		return array_values( array_unique( $files ) );
	}

	/**
	 * Run the guard.
	 *
	 * @return array{findings: list<array{id: string, file: string, line: int, match: string}>, errors: list<string>}
	 */
	public function run(): array {
		$parsed   = $this->allowlist();
		$allowed  = $parsed['entries'];
		$errors   = $parsed['errors'];
		$findings = array();

		foreach ( $this->files() as $file ) {
			$lines = file( $this->root . '/' . $file, FILE_IGNORE_NEW_LINES );
			if ( false === $lines ) {
				$errors[] = 'Cannot read ' . $file;
				continue;
			}
			foreach ( $lines as $number => $line ) {
				foreach ( self::PATTERNS as $id => $patterns ) {
					if ( 'G-12' === $id && str_starts_with( $file, self::G12_ALLOWED_DIRECTORY ) ) {
						continue;
					}
					foreach ( $patterns as $pattern ) {
						if ( 1 === preg_match( $pattern, $line, $match ) && ! isset( $allowed[ $file ][ $id ] ) ) {
							$findings[] = array(
								'id'    => $id,
								'file'  => $file,
								'line'  => $number + 1,
								'match' => $match[0],
							);
							break;
						}
					}
				}
			}
		}

		$header = $this->check_header();
		if ( null !== $header && ! isset( $allowed['course-captain.php']['G-13'] ) ) {
			$findings[] = $header;
		}

		return array(
			'findings' => $findings,
			'errors'   => $errors,
		);
	}

	/**
	 * G-13: the plugin header must not contain `Requires Plugins`.
	 *
	 * @return array{id: string, file: string, line: int, match: string}|null
	 */
	private function check_header(): ?array {
		$file = $this->root . '/course-captain.php';
		if ( ! is_file( $file ) ) {
			return null;
		}
		$header = (string) file_get_contents( $file, false, null, 0, 8192 );
		if ( 1 === preg_match( '/^[ \t\/*#@]*Requires Plugins\s*:/mi', $header, $match, PREG_OFFSET_CAPTURE ) ) {
			return array(
				'id'    => 'G-13',
				'file'  => 'course-captain.php',
				'line'  => substr_count( substr( $header, 0, (int) $match[0][1] ), "\n" ) + 1,
				'match' => 'Requires Plugins',
			);
		}
		return null;
	}

	/**
	 * Parse the allowlist: `path | guard-id | reason | expires (YYYY-MM-DD)` per line.
	 *
	 * @return array{entries: array<string, array<string, true>>, errors: list<string>}
	 */
	public function allowlist(): array {
		$entries = array();
		$errors  = array();
		if ( '' === $this->allow || ! is_file( $this->allow ) ) {
			return array(
				'entries' => $entries,
				'errors'  => $errors,
			);
		}

		$lines = file( $this->allow, FILE_IGNORE_NEW_LINES );
		$lines = false === $lines ? array() : $lines;
		$today = gmdate( 'Y-m-d' );
		foreach ( $lines as $number => $line ) {
			$line = trim( $line );
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line ) );
			$where = 'allowlist line ' . ( $number + 1 );
			if ( 4 !== count( $parts ) || in_array( '', $parts, true ) ) {
				$errors[] = $where . ': expected "path | guard-id | reason | expires".';
				continue;
			}
			list( $path, $id, , $expires ) = $parts;
			if ( in_array( $id, self::NOT_ALLOWABLE, true ) ) {
				$errors[] = $where . ': ' . $id . ' is not allowable (FG-07).';
				continue;
			}
			if ( 'G-13' !== $id && ! isset( self::PATTERNS[ $id ] ) ) {
				$errors[] = $where . ': unknown guard ID ' . $id . '.';
				continue;
			}
			if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expires ) || $expires < $today ) {
				$errors[] = $where . ': expiry date missing or in the past (' . $expires . ').';
				continue;
			}
			$entries[ $path ][ $id ] = true;
		}

		return array(
			'entries' => $entries,
			'errors'  => $errors,
		);
	}

	/**
	 * Self-test (LIC-010): the hit fixture yields exactly one finding per guard ID, the clean one none.
	 *
	 * @param string $fixtures Fixture directory with `hits/` and `clean/`.
	 * @return list<string> Problems; empty when the self-test passes.
	 */
	public static function self_test( string $fixtures ): array {
		$problems = array();

		$hits = ( new self( $fixtures . '/hits' ) )->run();
		$ids  = array_column( $hits['findings'], 'id' );
		sort( $ids, SORT_NATURAL );
		$expected = array_merge( array_keys( self::PATTERNS ), array( 'G-13' ) );
		sort( $expected, SORT_NATURAL );
		if ( $ids !== $expected ) {
			$problems[] = 'Hit fixture: expected one finding for each of ' . implode( ', ', $expected ) . '; got ' . implode( ', ', $ids ) . '.';
		}

		$clean = ( new self( $fixtures . '/clean' ) )->run();
		if ( array() !== $clean['findings'] || array() !== $clean['errors'] ) {
			$problems[] = 'Clean fixture: expected no findings, got ' . count( $clean['findings'] ) . '.';
		}

		return $problems;
	}

	/**
	 * CLI entry point.
	 *
	 * @return int Exit code.
	 */
	public static function main(): int {
		$options = getopt( '', array( 'root:', 'allow:', 'self-test' ) );
		$options = false === $options ? array() : $options;
		$repo    = dirname( __DIR__, 2 );

		if ( isset( $options['self-test'] ) ) {
			$problems = self::self_test( __DIR__ . '/fixtures/free-guard' );
			foreach ( $problems as $problem ) {
				fwrite( STDERR, 'free-guard self-test: ' . $problem . PHP_EOL );
			}
			if ( array() !== $problems ) {
				return 1;
			}
			fwrite( STDOUT, 'free-guard self-test: OK (' . count( self::PATTERNS ) . ' pattern guard IDs + G-13, clean fixture 0 findings)' . PHP_EOL );
			return 0;
		}

		$root  = is_string( $options['root'] ?? null ) ? $options['root'] : $repo;
		$allow = is_string( $options['allow'] ?? null ) ? $options['allow'] : $repo . '/tools/qa/free-guard-allow.txt';
		if ( ! is_dir( $root ) ) {
			fwrite( STDERR, 'free-guard: root not found: ' . $root . PHP_EOL );
			return 2;
		}

		$guard  = new self( $root, $allow );
		$result = $guard->run();
		foreach ( $result['errors'] as $error ) {
			fwrite( STDERR, 'free-guard: ' . $error . PHP_EOL );
		}
		foreach ( $result['findings'] as $finding ) {
			fwrite( STDERR, sprintf( 'free-guard: %s %s:%d "%s"', $finding['id'], $finding['file'], $finding['line'], $finding['match'] ) . PHP_EOL );
		}
		fwrite( STDOUT, sprintf( 'free-guard: %d files scanned, %d findings, %d allowlist errors', count( $guard->files() ), count( $result['findings'] ), count( $result['errors'] ) ) . PHP_EOL );
		return array() === $result['findings'] && array() === $result['errors'] ? 0 : 1;
	}
}

if ( PHP_SAPI === 'cli' && realpath( (string) ( get_included_files()[0] ?? '' ) ) === __FILE__ ) {
	exit( FreeGuard::main() );
}
