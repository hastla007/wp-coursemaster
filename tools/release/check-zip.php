<?php
/**
 * J-17 artefact checks on the release ZIP (R0-35 §1.4, §3.1, §3.2, RC-0xx; R0-18 §3.2).
 *
 * Usage: php tools/release/check-zip.php <zip> [--json]
 * Exit 0 = all checks passed, 1 = findings, 2 = usage error.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

$args = array_values( array_filter( array_slice( $argv, 1 ), static fn( string $a ): bool => '--json' !== $a ) );
$json = in_array( '--json', $argv, true );
if ( 1 !== count( $args ) || ! is_file( $args[0] ) ) {
	fwrite( STDERR, "Usage: php tools/release/check-zip.php <zip> [--json]\n" );
	exit( 2 );
}
$zip_path = $args[0];
$findings = array();
$fail     = static function ( string $id, string $message ) use ( &$findings ): void {
	$findings[] = array(
		'id'      => $id,
		'message' => $message,
	);
};

// Size limit for the directory submission (≤ 10 MB).
if ( filesize( $zip_path ) > 10 * 1024 * 1024 ) {
	$fail( 'SIZE', 'ZIP larger than 10 MB' );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path ) ) {
	fwrite( STDERR, "Cannot open ZIP\n" );
	exit( 2 );
}
$names = array();
for ( $i = 0; $i < $zip->numFiles; $i++ ) {
	$names[] = (string) $zip->getNameIndex( $i );
}

// Exactly one root folder `course-captain/`.
$roots = array_unique( array_map( static fn( string $n ): string => explode( '/', $n )[0], $names ) );
if ( array( 'course-captain' ) !== array_values( $roots ) ) {
	$fail( 'ROOT', 'expected exactly one root folder course-captain/, found: ' . implode( ', ', $roots ) );
}

// Positive list (§3.1); the bundled German translations (du and Sie, SET-CC-14) stay until the language pack is approved (E-208, R1-19).
foreach ( array( 'course-captain.php', 'uninstall.php', 'readme.txt', 'LICENSE', 'CREDITS.md', 'CHANGELOG.md', 'languages/course-captain.pot', 'languages/course-captain-de_DE.mo', 'languages/course-captain-de_DE_formal.mo', 'src/autoload.php' ) as $required ) {
	if ( ! in_array( 'course-captain/' . $required, $names, true ) ) {
		$fail( 'POSITIVE', 'missing ' . $required );
	}
}

// Negative list (R0-18 §3.2): development files, VCS, dumps, source maps, secrets, legacy names.
$negative = '#^course-captain/(tests|tools|docs|node_modules|vendor|\.github|\.wordpress-org)/|/\.git|\.(map|sql|log|bak|orig|swp|zip|phar)$|/(composer\.(json|lock)|phpunit[^/]*\.xml(\.dist)?|phpcs\.xml(\.dist)?|phpstan[^/]*\.neon(\.dist)?|\.env[^/]*|TODO[^/]*|konzept\.md|wp-config\.php)$#i';
foreach ( $names as $name ) {
	if ( 1 === preg_match( $negative, $name ) ) {
		$fail( 'NEGATIVE', 'not allowed in the package: ' . $name );
	}
	if ( 1 === preg_match( '/bootspruefung|learnboating|learn-boating/i', $name ) ) {
		$fail( 'RC-014', 'legacy name in path: ' . $name );
	}
}

// Contents.
$read    = static fn( string $file ): string => (string) $zip->getFromName( 'course-captain/' . $file );
$header  = $read( 'course-captain.php' );
$readme  = $read( 'readme.txt' );
$log     = $read( 'CHANGELOG.md' );
$version = preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $header, $m ) ? $m[1] : '';
$stable  = preg_match( '/^Stable tag:\s*(\S+)/mi', $readme, $m ) ? $m[1] : '';
$const   = preg_match( "/define\(\s*'COURSE_CAPTAIN_VERSION',\s*'([^']+)'/", $header, $m ) ? $m[1] : '';
$top     = preg_match( '/^##\s+([0-9][^\s]*)/m', $log, $m ) ? $m[1] : '';
if ( '' === $version || 1 !== count( array_unique( array( $version, $stable, $const, $top ) ) ) ) {
	$fail( 'VERSION', sprintf( 'versions differ: header %s, Stable tag %s, constant %s, changelog %s', $version, $stable, $const, $top ) );
}
foreach ( array( 'Requires at least', 'Requires PHP', 'License', 'Text Domain' ) as $key ) {
	if ( ! preg_match( '/^\s*\*\s*' . preg_quote( $key, '/' ) . ':\s*\S+/m', $header ) ) {
		$fail( 'HEADER', 'plugin header lacks ' . $key );
	}
}
if ( preg_match( '/^\s*\*\s*Update URI:/mi', $header ) ) {
	$fail( 'RC-020', 'Update URI header in the free package' );
}
foreach ( array( 'Requires at least', 'Requires PHP' ) as $key ) {
	$h = preg_match( '/^\s*\*\s*' . preg_quote( $key, '/' ) . ':\s*(\S+)/m', $header, $m ) ? $m[1] : '';
	$r = preg_match( '/^' . preg_quote( $key, '/' ) . ':\s*(\S+)/mi', $readme, $m ) ? $m[1] : '';
	if ( $h !== $r ) {
		$fail( 'README', sprintf( '%s differs between header (%s) and readme (%s)', $key, $h, $r ) );
	}
}
if ( preg_match( '/^Tags:\s*(.+)$/mi', $readme, $m ) && count( array_filter( array_map( 'trim', explode( ',', $m[1] ) ) ) ) > 5 ) {
	$fail( 'README', 'more than 5 tags' );
}
$after_header = preg_split( '/\R\R/', trim( (string) preg_replace( '/^===.*?===\R(.*?\R)*?\R/s', '', $readme, 1 ) ) )[0] ?? '';
if ( '' === trim( $after_header ) || mb_strlen( trim( $after_header ) ) > 150 ) {
	$fail( 'README', 'short description missing or longer than 150 characters' );
}
foreach ( array( '== Description ==', '== Changelog ==' ) as $section ) {
	if ( false === strpos( $readme, $section ) ) {
		$fail( 'README', 'missing section ' . $section );
	}
}
if ( '' !== $version && false === strpos( $readme, '= ' . $version ) ) {
	$fail( 'README', 'changelog entry for ' . $version . ' missing in readme.txt' );
}
if ( false === strpos( $read( 'LICENSE' ), 'GNU GENERAL PUBLIC LICENSE' ) ) {
	$fail( 'RC-011', 'LICENSE is not the GPL text' );
}
// SEC-045 (R0-17 §4.3): no forbidden claims in readme.txt and translation files.
$words = require dirname( __DIR__ ) . '/qa/sec-045-wordlist.php';
foreach ( $names as $name ) {
	if ( 1 !== preg_match( '#^course-captain/(readme\.txt|languages/[^/]+\.(pot|po|json))$#', $name ) ) {
		continue;
	}
	$text = (string) $zip->getFromName( $name );
	foreach ( $words as $word => $pattern ) {
		if ( 1 === preg_match( $pattern, $text ) ) {
			$fail( 'SEC-045', 'forbidden claim "' . $word . '" in ' . substr( $name, strlen( 'course-captain/' ) ) );
		}
	}
}
// block.json contract per block in the package.
foreach ( $names as $name ) {
	if ( 1 === preg_match( '#^course-captain/blocks/[^/]+/block\.json$#', $name ) ) {
		$block = json_decode( (string) $zip->getFromName( $name ), true );
		if ( ! is_array( $block ) || 3 !== ( $block['apiVersion'] ?? null ) || 'course-captain' !== ( $block['textdomain'] ?? null ) ) {
			$fail( 'BLOCK', 'invalid block.json: ' . $name );
		}
	}
}

// Extract for php -l and the free guard.
$dir = sys_get_temp_dir() . '/ccap-zipcheck-' . bin2hex( random_bytes( 4 ) );
mkdir( $dir );
$zip->extractTo( $dir );
$zip->close();
$php = PHP_BINARY;
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir . '/course-captain', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( 'php' === $file->getExtension() ) {
		exec( escapeshellarg( $php ) . ' -l ' . escapeshellarg( $file->getPathname() ) . ' 2>&1', $out, $code );
		if ( 0 !== $code ) {
			$fail( 'LINT', 'syntax error in ' . substr( $file->getPathname(), strlen( $dir ) + 1 ) );
		}
	}
}
$guard = dirname( __DIR__, 2 ) . '/tests/guards/free-guard.php';
if ( is_file( $guard ) ) {
	exec( escapeshellarg( $php ) . ' ' . escapeshellarg( $guard ) . ' --root=' . escapeshellarg( $dir . '/course-captain' ) . ' 2>&1', $guard_out, $guard_code );
	if ( 0 !== $guard_code ) {
		$fail( 'RC-020', 'free guard on the package: ' . implode( ' ', array_slice( $guard_out, -3 ) ) );
	}
}
exec( 'rm -rf ' . escapeshellarg( $dir ) );

if ( $json ) {
	echo json_encode(
		array(
			'zip'      => basename( $zip_path ),
			'files'    => count( $names ),
			'findings' => $findings,
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n";
} else {
	foreach ( $findings as $finding ) {
		echo '[' . $finding['id'] . '] ' . $finding['message'] . "\n";
	}
	echo ( array() === $findings ? 'check-zip: OK' : 'check-zip: ' . count( $findings ) . ' finding(s)' ) . ' (' . count( $names ) . " entries)\n";
}
exit( array() === $findings ? 0 : 1 );
