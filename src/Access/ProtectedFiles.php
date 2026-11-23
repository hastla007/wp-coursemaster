<?php
/**
 * Protected file delivery (R0-10 §4.3; R0-05 GM-015, GM-016).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Resolution only by `media_uid`; unknown or not protected → 404. Rights:
 * content editors always; learners per the policy (M-17 `download_file`)
 * or, under partial access, the content gate (first *n* files, GM-015).
 * The file must lie inside the protected folder (realpath, no symlinks).
 * Headers: `private, no-store`, `nosniff`, `sandbox` CSP except PDF,
 * `Content-Disposition` with ASCII fallback and `filename*=UTF-8''`
 * (RFC 6266/8187, GM-016); inline only for PDF, raster images, audio,
 * video, VTT. Single byte ranges → 206, unsatisfiable → 416, multiple
 * ranges → whole file. Assignment files (`source_system = assignment`,
 * R1-46) are authorized by `AssignmentFiles::resolve()` and always served
 * as download with `sandbox`.
 */
final class ProtectedFiles {

	public const QUERY_VAR = 'course_captain_file';
	public const INLINE    = array( 'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'text/vtt' );

	/**
	 * Constructor.
	 *
	 * @param Database      $db     Database access.
	 * @param PolicyService $policy Policy.
	 * @param ContentGate   $gate   Content gate.
	 */
	public function __construct( private Database $db, private PolicyService $policy, private ContentGate $gate ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), PolicyService::instance(), ContentGate::current() );
	}

	/**
	 * Rewrite endpoint `/cc-file/<media_uid>/` and URL filter.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'init',
			static function (): void {
				add_rewrite_rule( '^cc-file/([0-9a-f-]{36})/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
			}
		);
		add_filter(
			'query_vars',
			static function ( array $vars ): array {
				$vars[] = self::QUERY_VAR;
				return $vars;
			}
		);
		add_action( 'template_redirect', array( self::class, 'on_template_redirect' ), 0 );
		add_filter( 'wp_get_attachment_url', array( self::class, 'attachment_url' ), 10, 2 );
	}

	/**
	 * Protected attachments point to the checked endpoint (GM-111).
	 *
	 * @param mixed $url           URL.
	 * @param mixed $attachment_id Attachment.
	 * @return mixed
	 */
	public static function attachment_url( $url, $attachment_id ) {
		$uid = Database::current()->scalar( Database::current()->prepare( 'SELECT uid FROM `' . Database::current()->table( 'ccap_media' ) . "` WHERE attachment_id = %d AND access = 'protected' AND status = 'active'", (int) $attachment_id ) );
		if ( null === $uid ) {
			return $url;
		}
		return self::url( (string) $uid );
	}

	/**
	 * URL of the checked endpoint for a media UID.
	 *
	 * @param string $uid Media UID.
	 * @return string
	 */
	public static function url( string $uid ): string {
		// KOMP-016: query fallback with plain permalinks.
		return \CourseCaptain\Ops\Compatibility::plain_permalinks() ? add_query_arg( self::QUERY_VAR, $uid, home_url( '/' ) ) : home_url( '/cc-file/' . $uid . '/' );
	}

	/**
	 * Endpoint handler.
	 *
	 * @return void
	 */
	public static function on_template_redirect(): void {
		$uid = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $uid ) {
			return;
		}
		$range = isset( $_SERVER['HTTP_RANGE'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_RANGE'] ) ) : null;
		try {
			self::current()->serve( Actor::user( get_current_user_id() ), $uid, $range );
		} catch ( CourseException $e ) {
			status_header( 401 === $e->status ? 401 : ( 404 === $e->status ? 404 : 403 ) );
			nocache_headers();
		}
		exit;
	}

	/**
	 * Check rights and prepare the response (no output).
	 *
	 * @param Actor       $actor     Requesting user.
	 * @param string      $uid       Media UID.
	 * @param string|null $range     `Range` header.
	 * @param int|null    $course_id Course context (partial access).
	 * @return array{path: string, status: int, headers: array<string, string>, offset: int, length: int, media_id: int}
	 * @throws CourseException 404/401/403.
	 */
	public function prepare( Actor $actor, string $uid, ?string $range = null, ?int $course_id = null ): array {
		$media = 1 === preg_match( '/^[0-9a-f-]{36}$/', $uid ) ? ( new Relations( $this->db ) )->media_by_uid( $uid ) : null;
		if ( null === $media || 'protected' !== $media['access'] || 'active' !== $media['status'] ) {
			throw new CourseException( 'ccap_not_found', 'Unknown file.', 404 );
		}
		if ( \CourseCaptain\Assignment\AssignmentFiles::SOURCE === (string) $media['source_system'] ) {
			// Learner submissions (R0-10 §4.5): owner or grader, always as download.
			$file    = \CourseCaptain\Assignment\AssignmentFiles::current()->resolve( $actor, $media );
			$path    = $file['path'];
			$size    = (int) filesize( $path );
			$headers = self::headers( $file['name'], $file['mime'], $size, true );
		} else {
			$this->authorize( $actor, $uid, (int) $media['id'], $course_id );
			$path = (string) get_attached_file( (int) $media['attachment_id'], true );
			if ( '' === $path || ! is_file( $path ) || ( ! ProtectedStorage::is_protected_path( $path ) && ! apply_filters( 'course_captain_allow_unmoved_protected_file', false, $path ) ) ) {
				throw new CourseException( 'ccap_not_found', 'File not available.', 404 );
			}
			$size    = (int) filesize( $path );
			$mime    = (string) $media['mime_type'];
			$name    = self::download_name( (string) get_post_field( 'post_title', (int) $media['attachment_id'], 'raw' ), $path );
			$headers = self::headers( $name, $mime, $size );
		}
		$span   = self::range( $range, $size );
		$status = 200;
		$offset = 0;
		$length = $size;
		if ( false === $span ) {
			$status                   = 416;
			$headers['Content-Range'] = 'bytes */' . $size;
			$length                   = 0;
		} elseif ( null !== $span ) {
			$status                   = 206;
			$offset                   = $span[0];
			$length                   = $span[1] - $span[0] + 1;
			$headers['Content-Range'] = sprintf( 'bytes %d-%d/%d', $span[0], $span[1], $size );
		}
		$headers['Content-Length'] = (string) $length;
		return array(
			'path'     => $path,
			'status'   => $status,
			'headers'  => $headers,
			'offset'   => $offset,
			'length'   => $length,
			'media_id' => (int) $media['id'],
		);
	}

	/**
	 * Stream a file (1 MB blocks, aborts with the connection).
	 *
	 * @param Actor       $actor Requesting user.
	 * @param string      $uid   Media UID.
	 * @param string|null $range `Range` header.
	 * @return void
	 */
	public function serve( Actor $actor, string $uid, ?string $range ): void {
		$response = $this->prepare( $actor, $uid, $range );
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		status_header( $response['status'] );
		foreach ( $response['headers'] as $name => $value ) {
			header( $name . ': ' . $value );
		}
		if ( 0 === $response['length'] ) {
			return;
		}
		$handle = fopen( $response['path'], 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming.
		if ( false === $handle ) {
			return;
		}
		fseek( $handle, $response['offset'] );
		$left = $response['length'];
		while ( $left > 0 && ! feof( $handle ) && 0 === connection_aborted() ) {
			$chunk = (string) fread( $handle, min( 1048576, $left ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Streaming.
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary file body.
			$left -= strlen( $chunk );
			flush();
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming.
	}

	/**
	 * Files of a course for a learner: all listed, locked ones marked (GM-015).
	 *
	 * @param Actor $actor     Learner.
	 * @param int   $course_id Course.
	 * @return list<array{uid: string, name: string, locked: bool, reason: string|null}>
	 */
	public function listing( Actor $actor, int $course_id ): array {
		$out = array();
		foreach ( $this->gate->course_files( $course_id ) as $media_id ) {
			$row      = (array) $this->db->row( $this->db->prepare( 'SELECT uid, attachment_id FROM `' . $this->db->table( 'ccap_media' ) . '` WHERE id = %d', $media_id ) );
			$decision = $this->policy->decide( $actor, PolicyService::DOWNLOAD_FILE, array( (string) $row['uid'] ) );
			$gate     = $decision->allowed ? GateDecision::unlocked() : $this->gate->file( $actor, $course_id, $media_id );
			$out[]    = array(
				'uid'    => (string) $row['uid'],
				'name'   => (string) get_post_field( 'post_title', (int) $row['attachment_id'], 'raw' ),
				'locked' => ! $gate->allowed(),
				'reason' => $gate->reason,
			);
		}
		return $out;
	}

	/**
	 * Response headers for a file.
	 *
	 * @param string $name       File name.
	 * @param string $mime       MIME type.
	 * @param int    $size       Size.
	 * @param bool   $attachment Always download (learner uploads), with `sandbox`.
	 * @return array<string, string>
	 */
	public static function headers( string $name, string $mime, int $size, bool $attachment = false ): array {
		$inline  = ! $attachment && ( in_array( $mime, self::INLINE, true ) || str_starts_with( $mime, 'audio/' ) || str_starts_with( $mime, 'video/' ) );
		$headers = array(
			'Content-Type'           => $inline ? $mime : 'application/octet-stream',
			'Content-Disposition'    => self::content_disposition( $name, $inline ? 'inline' : 'attachment' ),
			'X-Content-Type-Options' => 'nosniff',
			'Cache-Control'          => 'private, no-store, max-age=0',
			'Accept-Ranges'          => 'bytes',
			'Content-Length'         => (string) $size,
		);
		if ( $attachment || 'application/pdf' !== $mime ) {
			$headers['Content-Security-Policy'] = 'sandbox';
		}
		return $headers;
	}

	/**
	 * Display name: attachment title plus the file extension, else the file name.
	 *
	 * @param string $title Attachment title.
	 * @param string $path  File path.
	 * @return string
	 */
	public static function download_name( string $title, string $path ): string {
		$ext   = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		$title = trim( html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' === $title ) {
			return wp_basename( $path );
		}
		return '' === $ext || str_ends_with( strtolower( $title ), '.' . $ext ) ? $title : $title . '.' . $ext;
	}

	/**
	 * `Content-Disposition` with ASCII fallback and RFC 8187 form (GM-016).
	 *
	 * @param string $name File name (UTF-8).
	 * @param string $type attachment|inline.
	 * @return string
	 */
	public static function content_disposition( string $name, string $type = 'attachment' ): string {
		$name  = str_replace( array( "\r", "\n", '/', '\\' ), '', $name );
		$ascii = (string) preg_replace( '/[^\x20-\x7E]/', '', remove_accents( $name ) );
		$ascii = '' === trim( $ascii ) ? 'download' : $ascii;
		$ascii = str_replace( '"', '\\"', $ascii );
		return sprintf( '%s; filename="%s"; filename*=UTF-8\'\'%s', $type, $ascii, rawurlencode( $name ) );
	}

	/**
	 * Parse one byte range: null = whole file, false = unsatisfiable.
	 *
	 * @param string|null $header `Range` header.
	 * @param int         $size   File size.
	 * @return array{0: int, 1: int}|false|null
	 */
	public static function range( ?string $header, int $size ) {
		if ( null === $header || '' === trim( $header ) ) {
			return null;
		}
		if ( 1 !== preg_match( '/^bytes=(\d*)-(\d*)$/', trim( $header ), $m ) ) {
			// Multiple or malformed ranges: deliver the whole file.
			return null;
		}
		if ( '' === $m[1] && '' === $m[2] ) {
			return null;
		}
		if ( '' === $m[1] ) {
			$suffix = (int) $m[2];
			if ( 0 === $suffix || 0 === $size ) {
				return false;
			}
			return array( max( 0, $size - $suffix ), $size - 1 );
		}
		$start = (int) $m[1];
		$end   = '' === $m[2] ? $size - 1 : min( (int) $m[2], $size - 1 );
		if ( $start >= $size || $start > $end ) {
			return false;
		}
		return array( $start, $end );
	}

	/**
	 * Rights: policy M-17, else partial access via the gate.
	 *
	 * @param Actor    $actor     User.
	 * @param string   $uid       UID.
	 * @param int      $media_id  Medium.
	 * @param int|null $course_id Course context.
	 * @return void
	 * @throws CourseException When denied.
	 */
	private function authorize( Actor $actor, string $uid, int $media_id, ?int $course_id ): void {
		$decision = $this->policy->decide( $actor, PolicyService::DOWNLOAD_FILE, array( $uid ) );
		if ( $decision->allowed ) {
			return;
		}
		$courses = null === $course_id ? ( new Relations( $this->db ) )->courses_of_media( $media_id ) : array( $course_id );
		foreach ( $courses as $course ) {
			if ( $this->gate->file( $actor, (int) $course, $media_id )->allowed() ) {
				return;
			}
		}
		CourseException::unless_allowed( $decision, 'No access to this file.' );
	}
}
