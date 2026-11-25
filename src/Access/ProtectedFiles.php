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
use CourseCaptain\Policy\Decision;
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
 *
 * Owner decisions of 25.11.2026: a guest's browser request is redirected
 * to the login with the file URL as return address, REST/JSON requests
 * keep `401 ccap_auth_required` (E-373, MED-001); a file whose every
 * module is still drip-locked for the learner stays locked, a course
 * grant does not bypass drip (E-374); media of a question in the
 * learner's running exam attempt (snapshot revision) are delivered to
 * that learner, everyone else needs normal course access (E-375,
 * MED-004); the experimental filter `course_captain_protected_file_sendfile`
 * may hand the body to the web server (E-377, MED-016).
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
			nocache_headers();
			$login = self::login_redirect( $e->status, $uid, self::wants_json() );
			if ( null !== $login ) {
				wp_safe_redirect( $login, 302 );
				exit;
			}
			status_header( 401 === $e->status ? 401 : ( 404 === $e->status ? 404 : 403 ) );
		}
		exit;
	}

	/**
	 * Login redirect for a guest's browser request (E-373, MED-001): only
	 * for 401 and a non-JSON request; the return address is the checked
	 * file URL (media UID only, never a path).
	 *
	 * @param int    $status HTTP status of the denial.
	 * @param string $uid    Media UID.
	 * @param bool   $json   REST/API or JSON request.
	 * @return string|null Login URL, null = answer with the status.
	 */
	public static function login_redirect( int $status, string $uid, bool $json ): ?string {
		if ( 401 !== $status || $json || 1 !== preg_match( '/^[0-9a-f-]{36}$/', $uid ) ) {
			return null;
		}
		return wp_login_url( self::url( $uid ) );
	}

	/**
	 * Whether the current request is a REST, AJAX or JSON request
	 * (`Accept`/`Content-Type: application/json`).
	 *
	 * @return bool
	 */
	public static function wants_json(): bool {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() || wp_is_json_request();
	}

	/**
	 * Check rights and prepare the response (no output).
	 *
	 * @param Actor       $actor     Requesting user.
	 * @param string      $uid       Media UID.
	 * @param string|null $range     `Range` header.
	 * @param int|null    $course_id Course context (partial access).
	 * @return array{path: string, status: int, headers: array<string, string>, offset: int, length: int, media_id: int, media: array<string, mixed>}
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
			if ( null !== $course_id && ! in_array( $course_id, ( new Relations( $this->db ) )->courses_of_media( (int) $media['id'] ), true ) ) {
				// MED-003: a course context only counts for a course that uses the file (no IDOR via another course).
				throw new CourseException( 'ccap_forbidden', 'No access to this file.', 403 );
			}
			$this->authorize( $actor, $uid, (int) $media['id'], $course_id );
			$this->assert_rights( $actor, (int) $media['id'], $course_id );
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
			'media'    => $media,
		);
	}

	/**
	 * Final response: the prepared one, or, when the filter
	 * `course_captain_protected_file_sendfile` returns headers (E-377,
	 * MED-016), status 200 with the content headers plus those headers and
	 * no PHP body (`stream = false`; the web server answers ranges and the
	 * length). The filter runs only after the rights check, so a denied request
	 * never gets a sendfile header.
	 *
	 * @param Actor       $actor Requesting user.
	 * @param string      $uid   Media UID.
	 * @param string|null $range `Range` header.
	 * @return array{path: string, status: int, headers: array<string, string>, offset: int, length: int, media_id: int, media: array<string, mixed>, stream: bool}
	 * @throws CourseException 404/401/403.
	 */
	public function response( Actor $actor, string $uid, ?string $range = null ): array {
		$response = $this->prepare( $actor, $uid, $range );
		/**
		 * Hand the file body to the web server (experimental, E-377).
		 *
		 * Return a header array such as `['X-Accel-Redirect' => '/protected/…']`
		 * (nginx `internal` location) or `['X-Sendfile' => $path]`; `null` or an
		 * empty array keeps the PHP streaming. Content headers of Course
		 * Captain cannot be replaced.
		 *
		 * @since 1.0.0
		 * @param array<string, string>|null $headers Sendfile headers (default null).
		 * @param string                     $path    Absolute file path (checked, inside the protected folder).
		 * @param array<string, mixed>       $media   Media row (`ccap_media`).
		 */
		$sendfile = self::sendfile_headers( apply_filters( 'course_captain_protected_file_sendfile', null, $response['path'], $response['media'] ), $response['headers'] );
		if ( array() === $sendfile ) {
			return $response + array( 'stream' => true );
		}
		$headers = $response['headers'];
		unset( $headers['Content-Length'], $headers['Content-Range'] );
		$response['status']  = 200;
		$response['headers'] = $headers + $sendfile;
		$response['offset']  = 0;
		$response['length']  = 0;
		return $response + array( 'stream' => false );
	}

	/**
	 * Valid headers of the sendfile filter: token names, single-line
	 * string values; names of the content headers are dropped.
	 *
	 * @param mixed                 $value   Filter result.
	 * @param array<string, string> $content Content headers.
	 * @return array<string, string>
	 */
	private static function sendfile_headers( $value, array $content ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$taken = array_map( 'strtolower', array_keys( $content ) );
		$out   = array();
		foreach ( $value as $name => $header ) {
			if ( ! is_string( $name ) || 1 !== preg_match( '/^[A-Za-z0-9-]{1,64}$/', $name ) || ! is_scalar( $header ) ) {
				continue;
			}
			$header = (string) $header;
			if ( '' === $header || 1 === preg_match( '/[\r\n\0]/', $header ) || in_array( strtolower( $name ), $taken, true ) ) {
				continue;
			}
			$out[ $name ] = $header;
		}
		return $out;
	}

	/**
	 * Stream a file (1 MB blocks, aborts with the connection). Output of
	 * other plugins in open buffers is discarded first (T105, MED-015);
	 * a buffer that cannot be removed stops the loop instead of spinning.
	 *
	 * @param Actor       $actor        Requesting user.
	 * @param string      $uid          Media UID.
	 * @param string|null $range        `Range` header.
	 * @param int         $keep_buffers Buffer levels to keep (0 = discard all; tests capture the body below).
	 * @return void
	 */
	public function serve( Actor $actor, string $uid, ?string $range, int $keep_buffers = 0 ): void {
		$response = $this->response( $actor, $uid, $range );
		while ( ob_get_level() > max( 0, $keep_buffers ) ) {
			$status = ob_get_status();
			if ( 0 === ( (int) ( $status['flags'] ?? 0 ) & PHP_OUTPUT_HANDLER_REMOVABLE ) || ! ob_end_clean() ) {
				break;
			}
		}
		nocache_headers();
		status_header( $response['status'] );
		if ( ! headers_sent() ) {
			foreach ( $response['headers'] as $name => $value ) {
				header( $name . ': ' . $value );
			}
		}
		if ( ! $response['stream'] || 0 === $response['length'] ) {
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
		$out      = array();
		$files    = $this->gate->course_files( $course_id );
		$withheld = RightsGate::exempt( $actor->user_id, $course_id ) || array() === $files ? array() : RightsGate::current()->media( $files );
		foreach ( $files as $media_id ) {
			if ( isset( $withheld[ $media_id ] ) ) {
				// R0-10 §2.2: withheld foreign files are not listed for learners.
				continue;
			}
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
	 * Rights lock (R0-10 §2.2, §4.4 media route): a withheld foreign file
	 * answers 404 like an unknown one (no hint, no path); editors of a
	 * course using the file are exempt (editorial side).
	 *
	 * @param Actor    $actor     User.
	 * @param int      $media_id  Medium.
	 * @param int|null $course_id Course context.
	 * @return void
	 * @throws CourseException 404 when withheld.
	 */
	private function assert_rights( Actor $actor, int $media_id, ?int $course_id ): void {
		if ( null === RightsGate::current()->medium( $media_id ) ) {
			return;
		}
		$courses = null === $course_id ? ( new Relations( $this->db ) )->courses_of_media( $media_id ) : array( $course_id );
		foreach ( $courses as $course ) {
			if ( RightsGate::exempt( $actor->user_id, (int) $course ) ) {
				return;
			}
		}
		if ( array() === $courses && $actor->user_id > 0 && user_can( $actor->user_id, 'ccap_edit_courses' ) ) {
			return;
		}
		throw new CourseException( 'ccap_not_found', 'Unknown file.', 404 );
	}

	/**
	 * Rights: editors always; the owner of a running exam attempt with the
	 * medium (E-375); else policy M-17 plus drip (E-374), else partial
	 * access via the gate.
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
		$courses  = null === $course_id ? ( new Relations( $this->db ) )->courses_of_media( $media_id ) : array( $course_id );
		if ( $decision->allowed && $this->edits_any( $actor, $courses ) ) {
			return;
		}
		// E-375: running attempts keep working (also after the grant ended); never over a lock, login or the access filter.
		$overridable = $decision->allowed || ( ! in_array( $decision->code, array( Decision::AUTH_REQUIRED, Decision::ACCOUNT_LOCKED ), true ) && 'filtered' !== $decision->reason );
		if ( $overridable && $this->gate->running_exam_media( $actor->user_id, $media_id ) ) {
			return;
		}
		if ( $decision->allowed ) {
			$this->assert_drip( $actor, $media_id, $courses );
			return;
		}
		foreach ( $courses as $course ) {
			if ( $this->gate->file( $actor, (int) $course, $media_id )->allowed() ) {
				return;
			}
		}
		CourseException::unless_allowed( $decision, 'No access to this file.' );
	}

	/**
	 * Whether the user may edit one of the courses (editorial side: no drip).
	 *
	 * @param Actor $actor   User.
	 * @param array $courses Courses.
	 * @phpstan-param list<int> $courses
	 * @return bool
	 */
	private function edits_any( Actor $actor, array $courses ): bool {
		foreach ( $courses as $course ) {
			if ( $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( (int) $course ) )->allowed ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Drip (E-374, R0-10 §4.3): a file used by the course itself (no
	 * module) is open with course access; otherwise at least one module
	 * that uses the file must pass the module gate (scope `download`). When
	 * every accessible module is drip-locked the drip lock is thrown
	 * (`ccap_content_locked`, reason `drip`); a course grant does not
	 * bypass it. Prerequisites do not lock files.
	 *
	 * @param Actor $actor    Learner.
	 * @param int   $media_id Medium.
	 * @param array $courses  Courses using the medium.
	 * @phpstan-param list<int> $courses
	 * @return void
	 * @throws CourseException Drip lock.
	 */
	private function assert_drip( Actor $actor, int $media_id, array $courses ): void {
		$relations = new Relations( $this->db );
		$drip      = null;
		foreach ( $courses as $course ) {
			$course = (int) $course;
			if ( in_array( $media_id, $this->gate->course_files( $course ), true ) && $this->policy->decide( $actor, PolicyService::LEARN, array( $course ), array( 'scope' => 'download' ) )->allowed ) {
				return;
			}
			foreach ( $relations->modules_of_media( $media_id, $course ) as $module ) {
				$gate = $this->gate->module( $actor, $course, $module, 'download', null );
				if ( 'drip' === $gate->reason ) {
					$drip = $drip ?? $gate;
					continue;
				}
				if ( $gate->allowed() || 'prerequisite' === $gate->reason ) {
					return;
				}
			}
		}
		if ( null !== $drip ) {
			$drip->assert();
		}
	}
}
