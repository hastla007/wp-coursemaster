<?php
/**
 * Reproducible release ZIP from a commit or the working tree (R0-35 §3.1, R0-18 §3.1).
 *
 * Files come from `git ls-files` minus everything with the `export-ignore`
 * attribute (.gitattributes). Timestamps are fixed to the commit time
 * (SOURCE_DATE_EPOCH) so two builds of the same commit are byte-identical.
 *
 * Usage: php tools/release/build-zip.php [--package=free] [--ref=<commit>|--worktree] [--out=<dir>]
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

$opts    = getopt( '', array( 'package::', 'ref::', 'worktree', 'out::' ) );
$package = (string) ( $opts['package'] ?? 'free' );
if ( 'free' !== $package ) {
	fwrite( STDERR, "Only --package=free is built in this repository (Pro: R3-18).\n" );
	exit( 2 );
}
$root     = dirname( __DIR__, 2 );
$out      = rtrim( (string) ( $opts['out'] ?? $root . '/dist' ), '/' );
$worktree = isset( $opts['worktree'] );
$ref      = (string) ( $opts['ref'] ?? 'HEAD' );
$git      = static function ( string $args ) use ( $root ): string {
	$output = shell_exec( 'git -C ' . escapeshellarg( $root ) . ' ' . $args . ' 2>/dev/null' );
	return is_string( $output ) ? $output : '';
};

$files = $worktree
	? array_filter( explode( "\0", $git( 'ls-files -z --cached --others --exclude-standard' ) ) )
	: array_filter( explode( "\0", $git( 'ls-tree -r -z --name-only ' . escapeshellarg( $ref ) ) ) );
$files = array_values( array_unique( $files ) );
sort( $files );

// export-ignore attribute per path.
$attr     = proc_open( array( 'git', '-C', $root, 'check-attr', '-z', '--stdin', 'export-ignore' ), array( array( 'pipe', 'r' ), array( 'pipe', 'w' ) ), $pipes );
fwrite( $pipes[0], implode( "\0", $files ) . "\0" );
fclose( $pipes[0] );
$raw = explode( "\0", (string) stream_get_contents( $pipes[1] ) );
fclose( $pipes[1] );
proc_close( $attr );
$ignored = array();
for ( $i = 0; $i + 2 < count( $raw ); $i += 3 ) {
	if ( 'set' === $raw[ $i + 2 ] ) {
		$ignored[ $raw[ $i ] ] = true;
	}
}
$files = array_values( array_filter( $files, static fn( string $f ): bool => ! isset( $ignored[ $f ] ) && ( $worktree ? is_file( $root . '/' . $f ) : true ) ) );

$epoch   = (int) ( getenv( 'SOURCE_DATE_EPOCH' ) ?: trim( $git( 'log -1 --format=%ct ' . escapeshellarg( $worktree ? 'HEAD' : $ref ) ) ) );
$version = '';
$header  = $worktree ? (string) file_get_contents( $root . '/course-captain.php' ) : $git( 'show ' . escapeshellarg( $ref . ':course-captain.php' ) );
if ( preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $header, $m ) ) {
	$version = $m[1];
}
if ( ! is_dir( $out ) ) {
	mkdir( $out, 0755, true );
}
$zip_path = $out . '/course-captain-' . ( '' === $version ? 'unknown' : $version ) . '.zip';
if ( file_exists( $zip_path ) ) {
	unlink( $zip_path );
}
$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::EXCL ) ) {
	fwrite( STDERR, "Cannot create {$zip_path}\n" );
	exit( 1 );
}
$zip->addEmptyDir( 'course-captain' );
$zip->setMtimeName( 'course-captain/', $epoch );
foreach ( $files as $file ) {
	$content = $worktree ? (string) file_get_contents( $root . '/' . $file ) : $git( 'show ' . escapeshellarg( $ref . ':' . $file ) );
	$name    = 'course-captain/' . $file;
	$zip->addFromString( $name, $content );
	$zip->setMtimeName( $name, $epoch );
	$zip->setExternalAttributesName( $name, ZipArchive::OPSYS_UNIX, 0644 << 16 );
}
$zip->close();
$sum = hash_file( 'sha256', $zip_path );
file_put_contents( $out . '/SHA256SUMS', $sum . '  ' . basename( $zip_path ) . "\n" );
fwrite( STDOUT, $zip_path . "\n" );
