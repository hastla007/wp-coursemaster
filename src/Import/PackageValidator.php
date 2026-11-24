<?php
/**
 * Package validation V1–V5 (R0-10 §1.1, §1.2, §3.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the archive directly (no extraction into the web root):
 *
 * - V1 archive: valid, not empty, ≤ 20 000 entries, ≤ 200 MB, unpacked ≤ 800 MB;
 * - V2 zip bomb: ratio ≤ 200 per file > 1 MB, ≤ 50 in total > 50 MB, no zero sizes;
 * - V3 paths: no absolute/backslash/`..`/NUL/drive/hidden segments, no symlinks,
 *   at most 32 segments of at most 255 bytes, no two entries that differ only
 *   in case or Unicode normalisation (NFC/NFD, `V3_collision`, SEC-028),
 *   only `manifest.json`, `data/<entity>/<6 digits>.json`, `media/…`;
 * - V4 media: extension allowlist, no dangerous inner extension, declared and
 *   detected MIME match (reported here; the import treats a V4 finding as
 *   an object error of the medium, R0-38 §2.3 class O, see
 *   {@see ImportService::validate()});
 * - V5 integrity: manifest fields, major 1 and minor ≤ {@see SCHEMA_MINOR}, every listed file present with size
 *   and SHA-256, no unlisted files, `content_sha256`.
 */
final class PackageValidator {

	/**
	 * Highest supported minor version of `schema_version` 1.x (R0-10 §1.2, IMP-005).
	 */
	public const SCHEMA_MINOR = 0;

	public const MAX_ENTRIES  = 20000;
	public const MAX_ZIP      = 209715200;
	public const MAX_UNPACKED = 838860800;
	public const ENTITIES     = array( 'courses', 'modules', 'lessons', 'questions', 'media', 'blueprints', 'flashcards' );
	public const MEDIA_TYPES  = array(
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
		'mp3'  => 'audio/mpeg',
		'm4a'  => 'audio/mp4',
		'mp4'  => 'video/mp4',
		'webm' => 'video/webm',
		'pdf'  => 'application/pdf',
		'vtt'  => 'text/vtt',
	);
	private const DANGEROUS   = array( 'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'html', 'htm', 'js', 'svg', 'svgz', 'shtml', 'cgi', 'pl', 'py', 'sh', 'exe', 'htaccess' );
	private const JUNK        = '#(^|/)(__MACOSX/|\.DS_Store$|Thumbs\.db$)#';
	public const MAX_DEPTH    = 32;
	public const MAX_SEGMENT  = 255;

	/**
	 * Validate a package file.
	 *
	 * @param string $path ZIP path.
	 * @return array{ok: bool, errors: list<array{rule: string, path: string}>, manifest: array<string, mixed>|null}
	 */
	public static function validate( string $path ): array {
		$errors = array();
		$fail   = static function ( string $rule, string $entry = '' ) use ( &$errors ): void {
			$errors[] = array(
				'rule' => $rule,
				'path' => $entry,
			);
		};
		$zip    = new \ZipArchive();
		if ( ! is_file( $path ) || (int) filesize( $path ) > self::MAX_ZIP || true !== $zip->open( $path, \ZipArchive::RDONLY ) ) {
			$fail( 'V1_archive' );
			return self::result( $errors, null );
		}
		$count = $zip->count();
		if ( 0 === $count || $count > self::MAX_ENTRIES ) {
			$fail( 'V1_entries' );
			$zip->close();
			return self::result( $errors, null );
		}
		$total   = 0;
		$packed  = 0;
		$entries = array();
		$folded  = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( false === $stat ) {
				$fail( 'V1_archive' );
				continue;
			}
			$name = (string) $stat['name'];
			if ( 1 === preg_match( self::JUNK, $name ) || str_ends_with( $name, '/' ) ) {
				continue;
			}
			$size  = (int) $stat['size'];
			$csize = (int) $stat['comp_size'];
			if ( $size > 0 && $csize <= 0 ) {
				$fail( 'V2_bomb', $name );
			} elseif ( $size > 1048576 && $size / max( 1, $csize ) > 200 ) {
				$fail( 'V2_bomb', $name );
			}
			$total  += $size;
			$packed += $csize;
			$opsys   = 0;
			$attr    = 0;
			$zip->getExternalAttributesIndex( $i, $opsys, $attr );
			if ( \ZipArchive::OPSYS_UNIX === $opsys && 0120000 === ( ( $attr >> 16 ) & 0170000 ) ) {
				$fail( 'V3_symlink', $name );
			}
			$path_error = self::path_error( $name );
			if ( null !== $path_error ) {
				$fail( $path_error, $name );
				continue;
			}
			// SEC-028: names that differ only in case or normalisation would overwrite each other on many file systems.
			$key = self::fold( $name );
			if ( isset( $folded[ $key ] ) ) {
				$fail( 'V3_collision', $name );
				continue;
			}
			$folded[ $key ]   = true;
			$entries[ $name ] = $size;
		}
		if ( $total > self::MAX_UNPACKED || ( $total > 52428800 && $total / max( 1, $packed ) > 50 ) ) {
			$fail( 'V2_bomb' );
		}
		$manifest = null;
		if ( ! isset( $entries['manifest.json'] ) ) {
			$fail( 'V5_manifest_missing' );
		} else {
			$manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
			$manifest = is_array( $manifest ) ? $manifest : null;
			foreach ( self::manifest_errors( $manifest ) as $rule ) {
				$fail( $rule, 'manifest.json' );
			}
		}
		if ( null !== $manifest && is_array( $manifest['files'] ?? null ) ) {
			$listed = array();
			$lines  = array();
			foreach ( $manifest['files'] as $file ) {
				$name = is_array( $file ) ? (string) ( $file['path'] ?? '' ) : '';
				if ( ! isset( $entries[ $name ] ) ) {
					$fail( 'V5_file_missing', $name );
					continue;
				}
				$data = (string) $zip->getFromName( $name );
				if ( strlen( $data ) !== (int) ( $file['size'] ?? -1 ) || hash( 'sha256', $data ) !== (string) ( $file['sha256'] ?? '' ) ) {
					$fail( 'V5_checksum', $name );
				}
				if ( str_starts_with( $name, 'media/' ) ) {
					$media_error = self::media_error( $name, (string) ( $file['mime_type'] ?? '' ), $data );
					if ( null !== $media_error ) {
						$fail( $media_error, $name );
					}
				}
				$listed[ $name ] = true;
				$lines[ $name ]  = $name . "\t" . hash( 'sha256', $data ) . "\n";
			}
			foreach ( array_keys( $entries ) as $name ) {
				if ( 'manifest.json' !== $name && ! isset( $listed[ $name ] ) ) {
					$fail( 'V5_unlisted', $name );
				}
			}
			ksort( $lines, SORT_STRING );
			if ( hash( 'sha256', implode( '', $lines ) ) !== (string) ( $manifest['content_sha256'] ?? '' ) ) {
				$fail( 'V5_content_sha256', 'manifest.json' );
			}
		}
		$zip->close();
		return self::result( $errors, $manifest );
	}

	/**
	 * `content_sha256` over `path\tsha256\n` sorted by path.
	 *
	 * @param array<string, string> $files Path → content.
	 * @return string
	 */
	public static function content_sha256( array $files ): string {
		ksort( $files, SORT_STRING );
		$out = '';
		foreach ( $files as $path => $data ) {
			$out .= $path . "\t" . hash( 'sha256', $data ) . "\n";
		}
		return hash( 'sha256', $out );
	}

	/**
	 * V3 path rule.
	 *
	 * @param string $name Entry name.
	 * @return string|null
	 */
	private static function path_error( string $name ): ?string {
		if ( '' === $name || str_starts_with( $name, '/' ) || str_contains( $name, '\\' ) || str_contains( $name, "\0" ) || 1 === preg_match( '/^[a-z]:/i', $name ) ) {
			return 'V3_path';
		}
		$segments = explode( '/', $name );
		if ( count( $segments ) > self::MAX_DEPTH ) {
			return 'V3_path';
		}
		foreach ( $segments as $segment ) {
			if ( '..' === $segment || '.' === $segment || str_starts_with( $segment, '.' ) || strlen( $segment ) > self::MAX_SEGMENT ) {
				return 'V3_path';
			}
		}
		if ( 'manifest.json' === $name || 1 === preg_match( '#^data/(' . implode( '|', self::ENTITIES ) . ')/\d{6}\.json$#', $name ) || str_starts_with( $name, 'media/' ) ) {
			return null;
		}
		return 'V3_entry';
	}

	/**
	 * Collision key of an entry name: NFC, then case-folded (SEC-028).
	 * Invalid UTF-8 is compared byte-wise, lower-cased.
	 *
	 * @param string $name Entry name.
	 * @return string
	 */
	private static function fold( string $name ): string {
		if ( class_exists( \Normalizer::class ) ) {
			$nfc  = \Normalizer::normalize( $name, \Normalizer::FORM_C );
			$name = false === $nfc ? $name : $nfc;
		}
		return 1 === preg_match( '//u', $name ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
	}

	/**
	 * V4 media rule.
	 *
	 * @param string $name     Path.
	 * @param string $declared Declared MIME.
	 * @param string $data     Content.
	 * @return string|null
	 */
	private static function media_error( string $name, string $declared, string $data ): ?string {
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		return self::media_rule( $name, $declared, false === $finfo ? '' : (string) finfo_buffer( $finfo, $data ) );
	}

	/**
	 * V4 rule of a stored file (legacy quarantine): the declared type is
	 * the type of its extension.
	 *
	 * @param string $name File name.
	 * @param string $file Absolute path.
	 * @return string|null Rule or null when allowed.
	 */
	public static function file_error( string $name, string $file ): ?string {
		$parts = explode( '.', strtolower( wp_basename( $name ) ) );
		$ext   = (string) end( $parts );
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		return self::media_rule( $name, self::MEDIA_TYPES[ $ext ] ?? '', false === $finfo || ! is_file( $file ) ? '' : (string) finfo_file( $finfo, $file ) );
	}

	/**
	 * V4: extension allowlist, no dangerous inner extension, declared and
	 * detected MIME match the extension.
	 *
	 * @param string $name     Path or name.
	 * @param string $declared Declared MIME.
	 * @param string $detected Detected MIME (`finfo`).
	 * @return string|null
	 */
	private static function media_rule( string $name, string $declared, string $detected ): ?string {
		$parts = explode( '.', strtolower( wp_basename( $name ) ) );
		$ext   = (string) array_pop( $parts );
		if ( array() !== array_intersect( $parts, self::DANGEROUS ) || in_array( $ext, self::DANGEROUS, true ) ) {
			return 'V4_dangerous';
		}
		if ( ! isset( self::MEDIA_TYPES[ $ext ] ) ) {
			return 'V4_extension';
		}
		if ( self::MEDIA_TYPES[ $ext ] !== $declared ) {
			return 'V4_mime_declared';
		}
		$text  = 'vtt' === $ext && in_array( $detected, array( 'text/plain', 'text/vtt' ), true );
		$audio = 'm4a' === $ext && in_array( $detected, array( 'audio/mp4', 'audio/x-m4a', 'video/mp4' ), true );
		return $text || $audio || $detected === $declared ? null : 'V4_mime_detected';
	}

	/**
	 * Manifest rules.
	 *
	 * @param array<string, mixed>|null $manifest Manifest.
	 * @return list<string>
	 */
	private static function manifest_errors( ?array $manifest ): array {
		if ( null === $manifest ) {
			return array( 'V5_manifest_json' );
		}
		$errors = array();
		if ( 'cc-package' !== ( $manifest['format'] ?? '' ) ) {
			$errors[] = 'V5_format';
		}
		if ( 1 !== preg_match( '/^1\.(\d+)\.\d+$/', (string) ( $manifest['schema_version'] ?? '' ), $version ) ) {
			$errors[] = 'V5_major';
		} elseif ( (int) $version[1] > self::SCHEMA_MINOR ) {
			// IMP-005: a newer minor version may use fields this plugin does not know yet (update the plugin).
			$errors[] = 'V5_minor';
		}
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9-]{1,40}-[0-9a-f]{12}$/', (string) ( $manifest['package_id'] ?? '' ) ) ) {
			$errors[] = 'V5_package_id';
		} elseif ( ! str_ends_with( (string) $manifest['package_id'], substr( (string) ( $manifest['content_sha256'] ?? '' ), 0, 12 ) ) ) {
			$errors[] = 'V5_package_id_hash';
		}
		foreach ( array( 'title', 'created_at', 'generator', 'source', 'languages', 'requires', 'counts', 'content_sha256', 'files', 'report' ) as $field ) {
			if ( ! array_key_exists( $field, $manifest ) ) {
				$errors[] = 'V5_field_' . $field;
			}
		}
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9._-]{1,31}$/', (string) ( $manifest['source']['system'] ?? '' ) ) ) {
			$errors[] = 'V5_source_system';
		}
		// D8: provenance of an own export (installation UUID) and the package-wide rights fallback (R0-10 §1.2).
		if ( isset( $manifest['source']['exported_from_site'] ) && 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) $manifest['source']['exported_from_site'] ) ) {
			$errors[] = 'V5_exported_from_site';
		}
		if ( array_key_exists( 'default_rights', $manifest ) && null !== $manifest['default_rights'] && ! Rights::valid_package_rights( $manifest['default_rights'] ) ) {
			$errors[] = 'V5_default_rights';
		}
		return $errors;
	}

	/**
	 * Result.
	 *
	 * @param list<array{rule: string, path: string}> $errors   Errors.
	 * @param array<string, mixed>|null               $manifest Manifest.
	 * @return array{ok: bool, errors: list<array{rule: string, path: string}>, manifest: array<string, mixed>|null}
	 */
	private static function result( array $errors, ?array $manifest ): array {
		return array(
			'ok'       => array() === $errors,
			'errors'   => $errors,
			'manifest' => $manifest,
		);
	}
}
