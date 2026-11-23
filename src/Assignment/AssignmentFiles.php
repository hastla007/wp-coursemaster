<?php
/**
 * File submissions of assignments: type check, protected storage, delivery
 * rights (R0-10 §4.5, MED-027…MED-030, MED-033, MED-034; R0-05 GM-196).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Assignment;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Activity\Providers\AssignmentProvider;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Checks follow V4 (R0-10 §3.2): extension from the allowlist, no dangerous
 * inner extension, declared and `finfo`-detected MIME match the extension;
 * Office containers are additionally checked for their marker entry.
 * Before a file enters the protected folder the filter
 * `course_captain_assignment_file_scan` may reject it (E-289, no built-in
 * scanner). Files live in `protected-<secret>/assignments/<submission_uid>/`
 * under a random key without extension; the original name is metadata only
 * and never part of a path. Media rows carry `source_system = assignment`,
 * no attachment, and are never in the media library.
 */
final class AssignmentFiles {

	public const SOURCE = 'assignment';

	/** Extension => [canonical MIME, accepted declared MIMEs, accepted detected MIMEs]. */
	private const TYPES = array(
		'pdf'  => array( 'application/pdf', array( 'application/pdf' ), array( 'application/pdf' ) ),
		'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ), array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip' ) ),
		'odt'  => array( 'application/vnd.oasis.opendocument.text', array( 'application/vnd.oasis.opendocument.text' ), array( 'application/vnd.oasis.opendocument.text', 'application/zip' ) ),
		'txt'  => array( 'text/plain', array( 'text/plain' ), array( 'text/plain' ) ),
		'jpg'  => array( 'image/jpeg', array( 'image/jpeg', 'image/pjpeg' ), array( 'image/jpeg' ) ),
		'png'  => array( 'image/png', array( 'image/png' ), array( 'image/png' ) ),
	);

	/** Never accepted, also not as inner extension (R0-10 §4.5). */
	private const DANGEROUS = array( 'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phps', 'html', 'htm', 'xhtml', 'js', 'mjs', 'svg', 'svgz', 'shtml', 'cgi', 'pl', 'py', 'sh', 'exe', 'htaccess', 'asp', 'aspx', 'jsp' );

	/**
	 * Constructor.
	 *
	 * @param Database      $db     Database access.
	 * @param PolicyService $policy Policy.
	 */
	public function __construct( private Database $db, private PolicyService $policy ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), PolicyService::instance() );
	}

	/**
	 * Allowlist extension of a file name (`jpeg` counts as `jpg`).
	 *
	 * @param string $name File name.
	 * @return string
	 */
	public static function extension( string $name ): string {
		$ext = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		return 'jpeg' === $ext ? 'jpg' : $ext;
	}

	/**
	 * Validate a file against the effective rules; returns the canonical MIME.
	 *
	 * @param string              $path     Temporary file.
	 * @param string              $name     Original name.
	 * @param string              $declared Declared MIME type.
	 * @param array<string,mixed> $rules  Effective rules (`allowlist`, `max_bytes`).
	 * @return string Canonical MIME.
	 * @throws CourseException `ccap_submission_file_rejected` with `reason`.
	 */
	public static function check( string $path, string $name, string $declared, array $rules ): string {
		$base  = strtolower( wp_basename( $name ) );
		$parts = explode( '.', $base );
		$ext   = self::extension( $base );
		array_pop( $parts );
		if ( '' === $ext || in_array( $ext, self::DANGEROUS, true ) || array() !== array_intersect( $parts, self::DANGEROUS ) || str_starts_with( $base, '.' ) ) {
			self::reject( 'dangerous' );
		}
		if ( ! isset( self::TYPES[ $ext ] ) || ! in_array( $ext, (array) $rules['allowlist'], true ) ) {
			self::reject( 'type' );
		}
		$size = is_file( $path ) ? (int) filesize( $path ) : 0;
		if ( $size <= 0 ) {
			self::reject( 'empty' );
		}
		if ( $size > (int) $rules['max_bytes'] ) {
			self::reject( 'size', array( 'max_bytes' => (int) $rules['max_bytes'] ) );
		}
		list( $canonical, $declared_ok, $detected_ok ) = self::TYPES[ $ext ];
		if ( ! in_array( strtolower( trim( $declared ) ), $declared_ok, true ) ) {
			self::reject( 'mime' );
		}
		$finfo    = finfo_open( FILEINFO_MIME_TYPE );
		$detected = false === $finfo ? '' : (string) finfo_file( $finfo, $path );
		if ( ! in_array( $detected, $detected_ok, true ) || ( 'application/zip' === $detected && ! self::office_marker( $path, $ext ) ) ) {
			self::reject( 'mime' );
		}
		return $canonical;
	}

	/**
	 * Office container check: `word/document.xml` (docx) or the stored `mimetype` entry (odt).
	 *
	 * @param string $path File.
	 * @param string $ext  docx|odt.
	 * @return bool
	 */
	private static function office_marker( string $path, string $ext ): bool {
		if ( 'odt' === $ext ) {
			$head = (string) file_get_contents( $path, false, null, 0, 80 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temp file.
			return str_contains( $head, 'mimetypeapplication/vnd.oasis.opendocument.text' );
		}
		if ( ! class_exists( \ZipArchive::class ) ) {
			return false;
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path, \ZipArchive::RDONLY ) ) {
			return false;
		}
		$ok = false !== $zip->locateName( 'word/document.xml' );
		$zip->close();
		return $ok;
	}

	/**
	 * Scanner hook (MED-034): anything but `true` rejects the file.
	 *
	 * @param string               $path Temporary file.
	 * @param array<string, mixed> $meta `name`, `mime`, `size_bytes`, `sha256`, `submission_uid`.
	 * @return void
	 * @throws CourseException `ccap_submission_file_rejected` (`reason: scan`).
	 */
	public static function scan( string $path, array $meta ): void {
		/**
		 * Virus scan of an assignment file before it enters protected storage (E-289).
		 * Return `true` for a clean file; `false`, a finding text or a `WP_Error` rejects it.
		 *
		 * @since 1.0.0
		 * @param bool|string|\WP_Error $clean Result (default `true`: no scanner).
		 * @param string                $path  Temporary file path.
		 * @param array<string, mixed>  $meta  Name, MIME, size, SHA-256, submission UID.
		 */
		$result = apply_filters( 'course_captain_assignment_file_scan', true, $path, $meta );
		if ( true !== $result ) {
			self::reject( 'scan' );
		}
	}

	/**
	 * Copy a checked file into protected storage.
	 *
	 * @param string $path           Temporary file.
	 * @param string $submission_uid Submission (folder).
	 * @return string Storage key relative to the protected folder.
	 * @throws CourseException When the copy fails.
	 */
	public static function store( string $path, string $submission_uid ): string {
		$dir = ProtectedStorage::dir( 'protected' ) . '/assignments';
		foreach ( array( $dir, $dir . '/' . $submission_uid ) as $folder ) {
			if ( ! is_dir( $folder ) && ! wp_mkdir_p( $folder ) ) {
				throw new CourseException( 'ccap_storage_unavailable', 'Protected folder not writable.', 500 );
			}
			if ( ! is_file( $folder . '/index.php' ) ) {
				file_put_contents( $folder . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Guard file in own folder.
			}
		}
		$key = 'assignments/' . $submission_uid . '/' . bin2hex( random_bytes( 16 ) );
		if ( ! @copy( $path, ProtectedStorage::dir( 'protected' ) . '/' . $key ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Failure reported below.
			throw new CourseException( 'ccap_storage_unavailable', 'Storing the file failed.', 500 );
		}
		return $key;
	}

	/**
	 * Absolute path of a storage key (inside the protected folder only).
	 *
	 * @param string $key Storage key.
	 * @return string|null
	 */
	public static function path( string $key ): ?string {
		if ( 1 !== preg_match( '#^assignments/[0-9a-f-]{36}/[0-9a-f]{32}$#', $key ) ) {
			return null;
		}
		$path = ProtectedStorage::dir( 'protected' ) . '/' . $key;
		return is_file( $path ) && ProtectedStorage::is_protected_path( $path ) ? $path : null;
	}

	/**
	 * Delete a medium and its file unless another submission still references it.
	 *
	 * @param Database $db       Database.
	 * @param int      $media_id Medium.
	 * @return int Deleted rows.
	 */
	public static function delete_media_if_unused( Database $db, int $media_id ): int {
		$refs = (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_assignment_file' ) . '` WHERE media_id = %d', $media_id ) );
		if ( $refs > 0 ) {
			return 0;
		}
		$media = $db->row( $db->prepare( 'SELECT storage_key, source_system FROM `' . $db->table( 'ccap_media' ) . '` WHERE id = %d', $media_id ) );
		if ( null === $media || self::SOURCE !== (string) $media['source_system'] ) {
			return 0;
		}
		$path = self::path( (string) $media['storage_key'] );
		if ( null !== $path ) {
			wp_delete_file( $path );
		}
		return $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_media' ) . '` WHERE id = %d', $media_id ) );
	}

	/**
	 * Remove empty submission folders (after erase/retention).
	 *
	 * @param string $submission_uid Submission UID.
	 * @return void
	 */
	public static function remove_folder( string $submission_uid ): void {
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/', $submission_uid ) ) {
			return;
		}
		$dir = ProtectedStorage::dir( 'protected' ) . '/assignments/' . $submission_uid;
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$left = array_diff( (array) scandir( $dir ), array( '.', '..', 'index.php' ) );
		if ( array() === $left ) {
			wp_delete_file( $dir . '/index.php' );
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Empty own folder.
		}
	}

	/**
	 * Delivery of an assignment file (called by `ProtectedFiles::prepare()`):
	 * the submitting person, or a person with grading rights for the
	 * course (E-288); every foreign access is audited. Others get 404.
	 *
	 * @param Actor                $actor Requesting user.
	 * @param array<string, mixed> $media Media row.
	 * @return array{path: string, name: string, mime: string}
	 * @throws CourseException 401/404.
	 */
	public function resolve( Actor $actor, array $media ): array {
		if ( $actor->user_id <= 0 ) {
			throw new CourseException( 'ccap_auth_required', 'Login required.', 401 );
		}
		$file  = $this->db->table( 'ccap_assignment_file' );
		$subs  = $this->db->table( 'ccap_assignment_submission' );
		$rows  = $this->db->rows( $this->db->prepare( "SELECT f.original_name, f.mime_type, s.id, s.uid, s.user_id FROM `{$file}` f JOIN `{$subs}` s ON s.id = f.submission_id WHERE f.media_id = %d ORDER BY s.submission_no", (int) $media['id'] ) );
		$found = null;
		$audit = false;
		foreach ( $rows as $row ) {
			if ( (int) $row['user_id'] === $actor->user_id ) {
				$found = $row;
				$audit = false;
				break;
			}
			if ( null === $found && $this->policy->decide( $actor, PolicyService::GRADE_SUBMISSION, array( (int) $row['id'] ) )->allowed ) {
				$found = $row;
				$audit = true;
			}
		}
		$path = self::path( (string) ( $media['storage_key'] ?? '' ) );
		if ( null === $found || null === $path ) {
			throw new CourseException( 'ccap_not_found', 'Unknown file.', 404 );
		}
		if ( $audit ) {
			AuditLog::write( $this->db, $actor, 'assignment_file_accessed', 'assignment_submission', (string) $found['uid'], null, array( 'media_uid' => (string) $media['uid'] ), (int) $found['user_id'] );
		}
		return array(
			'path' => $path,
			'name' => (string) $found['original_name'],
			'mime' => (string) $found['mime_type'],
		);
	}

	/**
	 * Effective file rules: the task may only be stricter than the site
	 * (R0-27 SET-134); the smallest of PHP/WP limit, site and task wins.
	 *
	 * @param array<string, mixed> $settings Assignment settings.
	 * @return array{allowlist: list<string>, max_files: int, max_bytes: int}
	 */
	public static function rules( array $settings ): array {
		$global    = self::site_rules();
		$own       = is_array( $settings['file_rules'] ?? null ) ? $settings['file_rules'] : array();
		$allowlist = is_array( $own['allowlist'] ?? null ) ? array_values( array_intersect( array_map( 'strval', $own['allowlist'] ), $global['allowlist'] ) ) : $global['allowlist'];
		return array(
			'allowlist' => $allowlist,
			'max_files' => min( $global['max_files'], max( 1, (int) ( $own['max_files'] ?? $global['max_files'] ) ) ),
			'max_bytes' => min( $global['max_bytes'], max( 1, (int) ( $own['max_bytes'] ?? $global['max_bytes'] ) ) ),
		);
	}

	/**
	 * Site rules (`learning.assignment_*`, PHP/WP upload limit).
	 *
	 * @return array{allowlist: list<string>, max_files: int, max_bytes: int}
	 */
	private static function site_rules(): array {
		$types = array_values( array_intersect( AssignmentProvider::FILE_TYPES, array_map( 'strval', (array) \CourseCaptain\Settings\Settings::get( 'learning.assignment_file_types' ) ) ) );
		$bytes = (int) \CourseCaptain\Settings\Settings::get( 'learning.assignment_max_file_mb' ) * 1048576;
		$limit = (int) wp_max_upload_size();
		return array(
			'allowlist' => $types,
			'max_files' => max( 1, (int) \CourseCaptain\Settings\Settings::get( 'learning.assignment_max_files' ) ),
			'max_bytes' => $limit > 0 ? min( $bytes, $limit ) : $bytes,
		);
	}

	/**
	 * Throw a file rejection.
	 *
	 * @param string               $reason  Machine reason.
	 * @param array<string, mixed> $details Extra details.
	 * @return never
	 * @throws CourseException Always.
	 */
	private static function reject( string $reason, array $details = array() ): never {
		throw new CourseException( 'ccap_submission_file_rejected', esc_html( $reason ), 422, array( 'reason' => $reason ) + $details ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Machine reason; details are integers.
	}
}
