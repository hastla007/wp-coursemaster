<?php
/**
 * Self test of the protected folders (R0-10 §4.2, §4.4; R0-05 GM-114, GM-115).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Access\ProtectedStorage;

defined( 'ABSPATH' ) || exit;

/**
 * For `protected` and `packages`: a folder without a public URL (outside
 * the uploads folder, the content folder and the WordPress root) is
 * `private` and needs no request. Otherwise a harmless file with a random
 * token is placed in the folder, fetched over HTTP (3 s, no redirects) and
 * removed again: 2xx with the token in the body → `exposed` (critical,
 * with the nginx rule), any other answer → `ok`, no answer → `unknown`.
 * The public `media` folder gets a script-block probe (R0-10 §4.4 row 3):
 * a probe file with an extension the server must refuse (`.html`) and the
 * token; 2xx with the token → `exposed` (critical, with the nginx rule
 * against scripts), any other answer → `ok`, no answer → `unknown`, no
 * public URL → `private`. The overall state is the worst of all probes.
 * The result is cached for 12 h (`unknown`: 1 h); "Check again" on the
 * system status page runs it fresh (RepairService). The output never
 * contains the folder secret.
 */
final class StorageSelfTest {

	public const TRANSIENT = 'course_captain_storage_selftest';
	public const KINDS     = array( 'protected', 'packages' );
	public const SCRIPTS   = 'php[0-9]?|phtml|phar|html?|svgz?|js|cgi|pl|py|sh';

	/**
	 * Run the self test or return the cached result.
	 *
	 * @param bool $fresh Ignore the cache ("check again").
	 * @return array{state: string, folders: array<string, string>, scripts: string, checked_at: int}
	 */
	public static function run( bool $fresh = false ): array {
		$cached = $fresh ? null : self::cached();
		if ( null !== $cached ) {
			return $cached;
		}
		$folders = array();
		foreach ( self::KINDS as $kind ) {
			try {
				$folders[ $kind ] = self::probe( ProtectedStorage::dir( $kind ) );
			} catch ( \Throwable $e ) {
				$folders[ $kind ] = 'unknown';
			}
		}
		try {
			$scripts = self::probe( ProtectedStorage::dir( 'media' ), 'html' );
		} catch ( \Throwable $e ) {
			$scripts = 'unknown';
		}
		$state = 'private';
		foreach ( array( 'exposed', 'unknown', 'ok' ) as $candidate ) {
			if ( in_array( $candidate, $folders, true ) || $candidate === $scripts ) {
				$state = $candidate;
				break;
			}
		}
		$result = array(
			'state'      => $state,
			'folders'    => $folders,
			'scripts'    => $scripts,
			'checked_at' => time(),
		);
		set_transient( self::TRANSIENT, $result, 'unknown' === $state ? HOUR_IN_SECONDS : 12 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Cached result without running a probe (null when none is cached).
	 *
	 * @return array{state: string, folders: array<string, string>, scripts: string, checked_at: int}|null
	 */
	public static function cached(): ?array {
		$cached = get_transient( self::TRANSIENT );
		if ( ! is_array( $cached ) || ! isset( $cached['state'], $cached['folders'] ) || ! is_array( $cached['folders'] ) ) {
			return null;
		}
		return array(
			'state'      => (string) $cached['state'],
			'folders'    => array_map( 'strval', $cached['folders'] ),
			'scripts'    => (string) ( $cached['scripts'] ?? 'unknown' ),
			'checked_at' => (int) ( $cached['checked_at'] ?? 0 ),
		);
	}

	/**
	 * Probe one folder.
	 *
	 * @param string $dir       Folder.
	 * @param string $extension Extension of the probe file (`txt` for the
	 *                          private folders, `html` for the script block).
	 * @return string private|ok|exposed|unknown
	 */
	public static function probe( string $dir, string $extension = 'txt' ): string {
		$url = ProtectedStorage::public_url( $dir );
		if ( null === $url ) {
			return 'private';
		}
		$token = wp_generate_password( 32, false, false );
		$name  = 'ccap-selftest-' . strtolower( wp_generate_password( 12, false, false ) ) . '.' . ( 'html' === $extension ? 'html' : 'txt' );
		$file  = $dir . '/' . $name;
		if ( false === file_put_contents( $file, $token ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Harmless probe file in the own folder.
			return 'unknown';
		}
		try {
			$response = wp_remote_get(
				$url . '/' . $name,
				array(
					'timeout'     => 3,
					'redirection' => 0,
				)
			);
		} finally {
			wp_delete_file( $file );
		}
		if ( is_wp_error( $response ) ) {
			return 'unknown';
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 0 === $code ) {
			return 'unknown';
		}
		return $code >= 200 && $code < 300 && str_contains( (string) wp_remote_retrieve_body( $response ), $token ) ? 'exposed' : 'ok';
	}

	/**
	 * Nginx rule for the actual folder location (no secret in it).
	 *
	 * @return string
	 */
	public static function nginx_rule(): string {
		$url  = ProtectedStorage::public_url( ProtectedStorage::base() );
		$path = null === $url ? '/wp-content/uploads/course-captain' : (string) wp_parse_url( $url, PHP_URL_PATH );
		return 'location ~* ^' . untrailingslashit( $path ) . '/(packages|protected)- { deny all; }';
	}

	/**
	 * Nginx rule against scripts in the public media folder (R0-10 §4.2).
	 *
	 * @return string
	 */
	public static function nginx_media_rule(): string {
		$url  = ProtectedStorage::public_url( ProtectedStorage::base() );
		$path = null === $url ? '/wp-content/uploads/course-captain' : (string) wp_parse_url( $url, PHP_URL_PATH );
		return 'location ~* ^' . untrailingslashit( $path ) . '/media/.*\\.(' . self::SCRIPTS . ')$ { deny all; }';
	}

	/**
	 * Site Health test `course_captain_storage`.
	 *
	 * @return array<string, mixed>
	 */
	public static function health(): array {
		$result = self::run();
		switch ( $result['state'] ) {
			case 'exposed':
				$status  = 'critical';
				$folders = in_array( 'exposed', $result['folders'], true );
				$label   = $folders ? __( 'Protected course files can be downloaded directly from the web server.', 'course-captain' ) : __( 'The web server delivers script files from the Course Captain media folder.', 'course-captain' );
				/* translators: %s: nginx configuration rule */
				$hint = $folders ? sprintf( __( 'Block direct access on the web server. For nginx add this rule: %s. Or set COURSE_CAPTAIN_PRIVATE_DIR to a folder outside the web root.', 'course-captain' ), self::nginx_rule() ) : '';
				if ( 'exposed' === $result['scripts'] ) {
					/* translators: %s: nginx configuration rule */
					$hint = trim( $hint . ' ' . sprintf( __( 'Block scripts in the media folder on the web server. For nginx add this rule: %s.', 'course-captain' ), self::nginx_media_rule() ) );
				}
				break;
			case 'unknown':
				$status = 'recommended';
				$label  = __( 'Course Captain could not check whether the protected folders are blocked on the web server.', 'course-captain' );
				/* translators: %s: nginx configuration rule */
				$hint = sprintf( __( 'Check the web server rules manually. For nginx the rule is: %s.', 'course-captain' ), self::nginx_rule() );
				break;
			case 'private':
				$status = 'good';
				$label  = __( 'Protected course files are stored outside the web root (private).', 'course-captain' );
				$hint   = '';
				break;
			default:
				$status = 'good';
				$label  = __( 'Direct access to the protected folders is blocked.', 'course-captain' );
				$hint   = '';
		}
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Course Captain', 'course-captain' ),
				'color' => 'good' === $status ? 'blue' : 'orange',
			),
			'description' => '' === $hint ? '' : '<p>' . esc_html( $hint ) . '</p>',
			'actions'     => '',
			'test'        => 'course_captain_storage',
			'state'       => $result['state'],
		);
	}
}
