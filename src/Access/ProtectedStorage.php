<?php
/**
 * Storage classes for files (R0-10 §4.1, §4.2; R0-05 GM-111, GM-112).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Course\CourseException;

defined( 'ABSPATH' ) || exit;

/**
 * `<private>` = constant `COURSE_CAPTAIN_PRIVATE_DIR` / filter
 * `course_captain_private_dir` (never a silent fallback once configured),
 * else `uploads/course-captain`. Folder names carry a random secret that is
 * never replaced (defence in depth, not a replacement for the server rule);
 * every folder gets `index.php`; protected folders get a deny-all
 * `.htaccess` (Apache 2.2/2.4), the public media folder a script block.
 * Foreign `.htaccess` files are never overwritten.
 */
final class ProtectedStorage {

	public const SECRET_OPTION = 'course_captain_storage_secret';
	public const MARKER        = '# course-captain';

	private const HTACCESS_PRIVATE = "# course-captain\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
	private const HTACCESS_MEDIA   = "# course-captain\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|html?|svgz?|js|cgi|pl|py|sh)$\">\n\t<IfModule mod_authz_core.c>\n\t\tRequire all denied\n\t</IfModule>\n\t<IfModule !mod_authz_core.c>\n\t\tOrder allow,deny\n\t\tDeny from all\n\t</IfModule>\n</FilesMatch>\nOptions -ExecCGI -Indexes\n";

	/**
	 * Base directory (no trailing slash).
	 *
	 * @return string
	 * @throws CourseException When a configured private dir is unusable.
	 */
	public static function base(): string {
		$configured = defined( 'COURSE_CAPTAIN_PRIVATE_DIR' ) ? (string) constant( 'COURSE_CAPTAIN_PRIVATE_DIR' ) : '';
		/**
		 * Private base directory (absolute, preferably outside the web root).
		 *
		 * @since 1.0.0
		 * @param string $dir Configured directory or empty.
		 */
		$configured = (string) apply_filters( 'course_captain_private_dir', $configured );
		if ( '' !== $configured ) {
			if ( ! path_is_absolute( $configured ) || ( ! is_dir( $configured ) && ! wp_mkdir_p( $configured ) ) ) {
				throw new CourseException( 'ccap_storage_unavailable', 'Private directory is not usable.', 500 );
			}
			return untrailingslashit( $configured );
		}
		$uploads = wp_upload_dir( null, false );
		return untrailingslashit( (string) $uploads['basedir'] ) . '/course-captain';
	}

	/**
	 * Directory of a storage class, created and protected on demand.
	 *
	 * @param string $kind protected|packages|media.
	 * @return string
	 * @throws CourseException For unknown classes or write errors.
	 */
	public static function dir( string $kind ): string {
		if ( ! in_array( $kind, array( 'protected', 'packages', 'media' ), true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown storage class.', 400 );
		}
		$base = self::base();
		$dir  = 'media' === $kind ? $base . '/media' : $base . '/' . $kind . '-' . self::secret();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Storage directory not writable.', 500 );
		}
		self::guard( $base, null );
		self::guard( $dir, 'media' === $kind ? self::HTACCESS_MEDIA : self::HTACCESS_PRIVATE );
		return $dir;
	}

	/**
	 * Site secret for folder names (generated once, never replaced).
	 *
	 * @return string
	 */
	public static function secret(): string {
		$secret = get_option( self::SECRET_OPTION );
		if ( ! is_string( $secret ) || 1 !== preg_match( '/^[a-z0-9]{24}$/', $secret ) ) {
			$secret = strtolower( wp_generate_password( 24, false, false ) );
			add_option( self::SECRET_OPTION, $secret, '', false );
			$secret = (string) get_option( self::SECRET_OPTION );
		}
		return $secret;
	}

	/**
	 * Whether a path lies inside the protected folder (realpath, no symlinks).
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	public static function is_protected_path( string $path ): bool {
		$real = realpath( $path );
		$base = realpath( self::dir( 'protected' ) );
		return false !== $real && false !== $base && ! is_link( $path ) && str_starts_with( $real, $base . DIRECTORY_SEPARATOR );
	}

	/**
	 * Move an attachment (all sizes) into the protected folder (GM-111).
	 *
	 * @param int $attachment_id Attachment.
	 * @param int $media_id      Medium (folder name).
	 * @return string New main file path.
	 * @throws CourseException When the move fails (protection then not active).
	 */
	public static function protect_attachment( int $attachment_id, int $media_id ): string {
		$file = get_attached_file( $attachment_id, true );
		if ( false === $file || ! is_file( $file ) ) {
			throw new CourseException( 'ccap_not_found', 'Attachment file missing.', 404 );
		}
		$target = self::dir( 'protected' ) . '/' . $media_id;
		if ( self::is_protected_path( $file ) ) {
			return $file;
		}
		if ( ! is_dir( $target ) && ! wp_mkdir_p( $target ) ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Protected folder not writable.', 500 );
		}
		self::guard( $target, null );
		$moved = array();
		foreach ( self::files( $attachment_id, $file ) as $source ) {
			$dest = $target . '/' . wp_basename( $source );
			if ( ! @rename( $source, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Same-volume move; failure is reported below.
				foreach ( $moved as $back => $from ) {
					@rename( $back, $from ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Rollback.
				}
				throw new CourseException( 'ccap_storage_move_failed', 'Moving the file failed; protection is not active.', 500 );
			}
			$moved[ $dest ] = $source;
		}
		update_post_meta( $attachment_id, '_wp_attached_file', $target . '/' . wp_basename( $file ) );
		update_post_meta( $attachment_id, '_ccap_protected_origin', $file );
		return $target . '/' . wp_basename( $file );
	}

	/**
	 * Move a protected attachment back to its original folder.
	 *
	 * @param int $attachment_id Attachment.
	 * @return void
	 * @throws CourseException When the move fails.
	 */
	public static function unprotect_attachment( int $attachment_id ): void {
		$origin = (string) get_post_meta( $attachment_id, '_ccap_protected_origin', true );
		$file   = get_attached_file( $attachment_id, true );
		if ( '' === $origin || false === $file || ! is_file( $file ) ) {
			return;
		}
		$dir = dirname( $origin );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Target folder not writable.', 500 );
		}
		foreach ( self::files( $attachment_id, $file ) as $source ) {
			if ( ! @rename( $source, $dir . '/' . wp_basename( $source ) ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Reported below.
				throw new CourseException( 'ccap_storage_move_failed', 'Moving the file back failed.', 500 );
			}
		}
		$uploads  = wp_upload_dir( null, false );
		$relative = ltrim( str_replace( untrailingslashit( (string) $uploads['basedir'] ), '', $origin ), '/' );
		update_post_meta( $attachment_id, '_wp_attached_file', $relative );
		delete_post_meta( $attachment_id, '_ccap_protected_origin' );
	}

	/**
	 * Main file plus all generated sizes.
	 *
	 * @param int    $attachment_id Attachment.
	 * @param string $file          Main file.
	 * @return list<string>
	 */
	private static function files( int $attachment_id, string $file ): array {
		$out  = array( $file );
		$meta = wp_get_attachment_metadata( $attachment_id );
		foreach ( (array) ( is_array( $meta ) ? ( $meta['sizes'] ?? array() ) : array() ) as $size ) {
			$path = dirname( $file ) . '/' . (string) ( $size['file'] ?? '' );
			if ( is_array( $size ) && ! empty( $size['file'] ) && is_file( $path ) && ! in_array( $path, $out, true ) ) {
				$out[] = $path;
			}
		}
		if ( is_array( $meta ) && ! empty( $meta['original_image'] ) ) {
			$path = dirname( $file ) . '/' . (string) $meta['original_image'];
			if ( is_file( $path ) && ! in_array( $path, $out, true ) ) {
				$out[] = $path;
			}
		}
		return $out;
	}

	/**
	 * Write `index.php` and (own) `.htaccess`; never overwrite foreign files.
	 *
	 * @param string      $dir      Directory.
	 * @param string|null $htaccess Content or null.
	 * @return void
	 */
	private static function guard( string $dir, ?string $htaccess ): void {
		if ( ! is_file( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Guard file in own folder.
		}
		if ( null === $htaccess ) {
			return;
		}
		$path = $dir . '/.htaccess';
		if ( is_file( $path ) && ! str_starts_with( (string) file_get_contents( $path ), self::MARKER ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
			return;
		}
		file_put_contents( $path, $htaccess ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Own server rule.
	}
}
