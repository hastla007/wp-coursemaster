<?php
/**
 * R1-18 Golden-Master traceability: every GM ID of the R0-05 catalogue
 * (§1 tables) has a test (`@group GM-xxx` in tests/ or `@group GM-xxx` in
 * a tools/qa/e2e script) or an entry in the versioned exception list
 * `tools/qa/gm-coverage-exceptions.php`.
 *
 * Usage: php tools/qa/gm-coverage.php [--matrix]
 * Exit 0 = no uncovered ID and no stale/invalid exception; 1 otherwise.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tools\Qa;

/**
 * Pure functions, also used by tests/unit/GmCoverageTest.php.
 */
final class GmCoverage {

	public const CATEGORIES = array( 'open', 'deviation', 'r2', 'r3', 'human' );

	/**
	 * GM IDs of the catalogue tables in R0-05 §1 (column "GM-ID").
	 *
	 * @param string $markdown R0-05 text.
	 * @return list<string>
	 */
	public static function catalogue( string $markdown ): array {
		$ids     = array();
		$section = false;
		foreach ( explode( "\n", $markdown ) as $line ) {
			if ( str_starts_with( $line, '## ' ) ) {
				$section = str_starts_with( $line, '## 1.' );
			}
			if ( ! $section || ! str_starts_with( $line, '|' ) ) {
				continue;
			}
			$cells = array_map( 'trim', explode( '|', trim( $line, " |\t" ) ) );
			if ( isset( $cells[5] ) && 1 === preg_match( '/^GM-\d{3}$/', $cells[5] ) ) {
				$ids[] = $cells[5];
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * `@group GM-xxx` tags per ID with the test that carries them.
	 *
	 * @param list<string> $dirs Directories to scan (*.php, *.js).
	 * @return array<string, list<string>> ID → list of "file::method".
	 */
	public static function groups( array $dirs ): array {
		$out = array();
		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $files as $file ) {
				$path = (string) $file;
				if ( ! preg_match( '/\.(php|js)$/', $path ) || str_contains( $path, '/fixtures/' ) ) {
					continue;
				}
				$text = (string) file_get_contents( $path );
				if ( ! preg_match_all( '/@group (GM-\d{3})\b/', $text, $m, PREG_OFFSET_CAPTURE ) ) {
					continue;
				}
				foreach ( $m[1] as $hit ) {
					$after  = substr( $text, (int) $hit[1] );
					$method = preg_match( '/function\s+(\w+)|(?:flow|step)\(\s*[\'"]([^\'"]+)/', $after, $f ) ? ( $f[1] ?: ( $f[2] ?? '' ) ) : '';
					$out[ $hit[0] ][] = basename( $path ) . '::' . $method;
				}
			}
		}
		ksort( $out );
		return array_map( static fn( array $l ): array => array_values( array_unique( $l ) ), $out );
	}

	/**
	 * Coverage report.
	 *
	 * @param list<string>                $ids        Catalogue IDs.
	 * @param array<string, list<string>> $groups     Tagged tests.
	 * @param array<string, mixed>        $exceptions Exception list file contents.
	 * @return array{covered: array<string, list<string>>, excepted: array<string, array{0: string, 1: string, 2: string}>, missing: list<string>, stale: list<string>, invalid: list<string>, unknown: list<string>}
	 */
	public static function report( array $ids, array $groups, array $exceptions ): array {
		$list   = (array) ( $exceptions['exceptions'] ?? array() );
		$report = array(
			'covered'  => array(),
			'excepted' => array(),
			'missing'  => array(),
			'stale'    => array(),
			'invalid'  => array(),
			'unknown'  => array_values( array_diff( array_merge( array_keys( $groups ), array_keys( $list ), array_keys( (array) ( $exceptions['partial'] ?? array() ) ) ), $ids ) ),
		);
		foreach ( $ids as $id ) {
			if ( isset( $groups[ $id ] ) ) {
				$report['covered'][ $id ] = $groups[ $id ];
				if ( isset( $list[ $id ] ) ) {
					$report['stale'][] = $id;
				}
				continue;
			}
			if ( ! isset( $list[ $id ] ) ) {
				$report['missing'][] = $id;
				continue;
			}
			$entry = $list[ $id ];
			if ( ! is_array( $entry ) || 3 !== count( $entry ) || ! in_array( $entry[0], self::CATEGORIES, true ) || '' === trim( (string) $entry[1] ) || strlen( trim( (string) $entry[2] ) ) < 10 ) {
				$report['invalid'][] = $id;
				continue;
			}
			$report['excepted'][ $id ] = array( (string) $entry[0], (string) $entry[1], (string) $entry[2] );
		}
		foreach ( array_keys( (array) ( $exceptions['partial'] ?? array() ) ) as $id ) {
			if ( ! isset( $groups[ $id ] ) && in_array( $id, $ids, true ) ) {
				$report['stale'][] = $id;
			}
		}
		$report['unknown'] = array_values( array_unique( $report['unknown'] ) );
		return $report;
	}

	/**
	 * Report of this repository.
	 *
	 * @param string $root Repository root.
	 * @return array{covered: array<string, list<string>>, excepted: array<string, array{0: string, 1: string, 2: string}>, missing: list<string>, stale: list<string>, invalid: list<string>, unknown: list<string>}
	 */
	public static function repository( string $root ): array {
		$exceptions = require $root . '/tools/qa/gm-coverage-exceptions.php';
		return self::report(
			self::catalogue( (string) file_get_contents( $root . '/docs/r0/R0-05-golden-master.md' ) ),
			self::groups( array( $root . '/tests', $root . '/tools/qa/e2e' ) ),
			is_array( $exceptions ) ? $exceptions : array()
		);
	}
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	$root   = dirname( __DIR__, 2 );
	$report = GmCoverage::repository( $root );
	$counts = array_count_values( array_column( $report['excepted'], 0 ) );
	if ( in_array( '--matrix', $argv, true ) ) {
		$partial = (array) ( ( require $root . '/tools/qa/gm-coverage-exceptions.php' )['partial'] ?? array() );
		echo "| GM-ID | Status | Test / Grund |\n|---|---|---|\n";
		$rows = array();
		foreach ( array_merge( array_keys( $report['covered'] ), array_keys( $report['excepted'] ), $report['missing'] ) as $id ) {
			if ( isset( $report['covered'][ $id ] ) ) {
				$status = isset( $partial[ $id ] ) ? 'Test (teilweise)' : 'Test';
				$text   = '`' . implode( '`, `', $report['covered'][ $id ] ) . '`' . ( isset( $partial[ $id ] ) ? ' – ' . $partial[ $id ] : '' );
			} elseif ( isset( $report['excepted'][ $id ] ) ) {
				$status = $report['excepted'][ $id ][0];
				$text   = $report['excepted'][ $id ][1] . ': ' . $report['excepted'][ $id ][2];
			} else {
				$status = 'FEHLT';
				$text   = '';
			}
			$rows[ $id ] = '| ' . $id . ' | ' . $status . ' | ' . str_replace( '|', '\|', $text ) . " |\n";
		}
		ksort( $rows );
		echo implode( '', $rows );
	}
	printf(
		"GM catalogue: %d IDs; tested %d; exceptions %d (%s); missing %d; stale %d; invalid %d; unknown %d\n",
		count( $report['covered'] ) + count( $report['excepted'] ) + count( $report['missing'] ),
		count( $report['covered'] ),
		count( $report['excepted'] ),
		implode( ', ', array_map( static fn( string $k, int $v ): string => $k . ' ' . $v, array_keys( $counts ), $counts ) ),
		count( $report['missing'] ),
		count( $report['stale'] ),
		count( $report['invalid'] ),
		count( $report['unknown'] )
	);
	foreach ( array( 'missing', 'stale', 'invalid', 'unknown' ) as $key ) {
		if ( array() !== $report[ $key ] ) {
			echo $key . ': ' . implode( ' ', $report[ $key ] ) . "\n";
		}
	}
	exit( array() === $report['missing'] && array() === $report['stale'] && array() === $report['invalid'] && array() === $report['unknown'] ? 0 : 1 );
}
