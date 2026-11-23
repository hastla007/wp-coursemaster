<?php
/**
 * Media entity of `cc-package` v1 (R0-10 §1.3, §3.5, §4.1; R0-38 §3.4, §4.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * - V6 per record: path listed in the manifest with the same size, SHA-256
 *   and MIME type, known media type and access class, alternative text for
 *   images unless decorative. A file the validator rejected (V4) makes its
 *   record an object error (`rejected`, R0-38 IMP-V4.0x, class O).
 * - Files are written outside the database work: temporary file in the
 *   target folder, SHA-256 check, `rename` (atomic). The target path is
 *   addressed by the SHA-256, so a file written by a chunk that was rolled
 *   back is recognised and taken over on the retry (I-1, IMP-029); one file
 *   per content and access class, one media row per origin (R0-38 §3.4).
 * - Protected media (default, E-65) lie in the protected folder and are
 *   served only through PHP; media with blocking rights are never public.
 *   Every medium gets an attachment so that the existing delivery, listing
 *   and restore checks work unchanged.
 */
final class ImportMedia {

	public const TYPES  = array( 'image', 'audio', 'video', 'document', 'captions' );
	public const ACCESS = array( 'public', 'protected' );

	/**
	 * Package role → stored role of `ccap_content_media` (MediaLibrary::ROLES).
	 *
	 * @var array<string, array<string, string>>
	 */
	private const ROLE_MAP = array(
		'primary'    => array(
			'image'    => 'image',
			'audio'    => 'video',
			'video'    => 'video',
			'captions' => 'subtitle',
			'document' => 'download',
		),
		'inline'     => array(
			'image' => 'image',
		),
		'caption'    => array(),
		'attachment' => array(),
	);

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current() );
	}

	/**
	 * V4/V6 rule of a media record, or null.
	 *
	 * @param array<string, mixed>                $record   Record.
	 * @param array<string, array<string, mixed>> $files    Manifest files by path.
	 * @param array<string, string>               $rejected Paths rejected by the validator (V4) → rule.
	 * @return string|null
	 */
	public static function record_error( array $record, array $files, array $rejected ): ?string {
		$path = (string) ( $record['path'] ?? '' );
		if ( isset( $rejected[ $path ] ) ) {
			return $rejected[ $path ];
		}
		if ( ! str_starts_with( $path, 'media/' ) || ! isset( $files[ $path ] ) ) {
			return 'schema';
		}
		$file = $files[ $path ];
		$type = (string) ( $record['media_type'] ?? '' );
		if ( ! in_array( $type, self::TYPES, true ) || ! in_array( (string) ( $record['access'] ?? 'protected' ), self::ACCESS, true ) ) {
			return 'schema';
		}
		if ( (string) ( $record['sha256'] ?? '' ) !== (string) ( $file['sha256'] ?? '' ) || (int) ( $record['size'] ?? -1 ) !== (int) ( $file['size'] ?? -2 ) || (string) ( $record['mime_type'] ?? '' ) !== (string) ( $file['mime_type'] ?? '' ) ) {
			return 'schema';
		}
		$decorative = ! empty( $record['decorative'] );
		if ( ( 'image' === $type && ! $decorative && '' === trim( (string) ( $record['alt_text'] ?? '' ) ) ) || ( $decorative && 'image' !== $type ) ) {
			return 'schema';
		}
		if ( isset( $record['rights'] ) && ! in_array( (string) ( $record['rights']['status'] ?? '' ), Rights::STATUSES, true ) ) {
			return 'schema';
		}
		return null;
	}

	/**
	 * Media references `media[] {media, role, sort}` of a record.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return list<string>
	 */
	public static function references( array $record ): array {
		$out = array();
		foreach ( (array) ( $record['media'] ?? array() ) as $link ) {
			if ( is_array( $link ) && '' !== (string) ( $link['media'] ?? '' ) ) {
				$out[] = (string) $link['media'];
			}
		}
		return $out;
	}

	/**
	 * Store the file of a record and create or update its media row.
	 *
	 * @param string                                                                   $zip      Package copy.
	 * @param string                                                                   $system   Source system.
	 * @param string                                                                   $package  Package ID.
	 * @param array<string, mixed>                                                     $record   Record.
	 * @param int|null                                                                 $existing Media row of this origin.
	 * @param array{status: string, owner: string|null, license: array<string, mixed>} $rights Resolved rights (E-62).
	 * @return int Media ID.
	 * @throws CourseException When the file differs from its record or cannot be written.
	 */
	public function write( string $zip, string $system, string $package, array $record, ?int $existing, array $rights ): int {
		$path    = (string) $record['path'];
		$sha     = (string) $record['sha256'];
		$archive = new \ZipArchive();
		$data    = false;
		if ( true === $archive->open( $zip, \ZipArchive::RDONLY ) ) {
			$data = $archive->getFromName( $path );
			$archive->close();
		}
		if ( ! is_string( $data ) || hash( 'sha256', $data ) !== $sha ) {
			throw new CourseException( 'ccap_import_failed', 'Media file differs from its record.', 422 );
		}
		// E-65: protected by default; media with blocking rights never lie in the public folder.
		$access = 'public' === ( $record['access'] ?? 'protected' ) && ! in_array( $rights['status'], Rights::BLOCKING, true ) ? 'public' : 'protected';
		$name   = sanitize_file_name( wp_basename( $path ) );
		$file   = self::store( $data, $sha, $name, $access );
		$table  = $this->db->table( 'ccap_media' );
		$row    = null === $existing ? null : $this->db->row( $this->db->prepare( "SELECT id, attachment_id FROM `{$table}` WHERE id = %d", $existing ) );
		$title  = sanitize_text_field( (string) ( $record['title'] ?? pathinfo( $name, PATHINFO_FILENAME ) ) );
		$post   = (int) ( $row['attachment_id'] ?? 0 );
		if ( $post > 0 && null !== get_post( $post ) ) {
			update_attached_file( $post, $file );
			wp_update_post(
				array(
					'ID'             => $post,
					'post_title'     => $title,
					'post_mime_type' => (string) $record['mime_type'],
				)
			);
		} else {
			$post = wp_insert_attachment(
				array(
					'post_title'     => $title,
					'post_mime_type' => (string) $record['mime_type'],
					'post_status'    => 'inherit',
				),
				$file,
				0,
				true
			);
			if ( ! is_int( $post ) || $post <= 0 ) {
				throw new CourseException( 'ccap_import_failed', 'Attachment could not be written.', 500 );
			}
		}
		$values = array(
			'source_system'     => $system,
			'source_id'         => (string) $record['source_id'],
			'source_hash'       => (string) ( $record['hash'] ?? '' ),
			'package_id'        => $package,
			'imported_at'       => $this->db->now(),
			'attachment_id'     => $post,
			'storage_key'       => ( 'public' === $access ? 'media/' : 'protected/import/' ) . $sha . '/' . $name,
			'package_path'      => substr( $path, 0, 255 ),
			'media_type'        => (string) $record['media_type'],
			'mime_type'         => (string) $record['mime_type'],
			'size_bytes'        => strlen( $data ),
			'sha256'            => $sha,
			'access'            => $access,
			'alt_text'          => substr( sanitize_text_field( (string) ( $record['alt_text'] ?? '' ) ), 0, 255 ),
			'caption'           => sanitize_textarea_field( (string) ( $record['caption'] ?? '' ) ),
			'description'       => sanitize_textarea_field( (string) ( $record['description'] ?? '' ) ),
			'source_url'        => isset( $rights['license']['source_url'] ) ? esc_url_raw( (string) $rights['license']['source_url'], array( 'https' ) ) : null,
			'rights_owner'      => null === $rights['owner'] ? null : substr( sanitize_text_field( $rights['owner'] ), 0, 255 ),
			'license_reference' => array() === $rights['license'] ? null : (string) wp_json_encode( $rights['license'] ),
			'rights_status'     => $rights['status'],
			'status'            => 'active',
		);
		if ( null !== $row ) {
			$assign = array();
			$args   = array();
			foreach ( $values as $column => $value ) {
				$assign[] = null === $value ? "`{$column}` = NULL" : "`{$column}` = %s";
				if ( null !== $value ) {
					$args[] = (string) $value;
				}
			}
			$args[] = $this->db->now();
			$args[] = (int) $row['id'];
			$this->db->execute( $this->db->prepare( "UPDATE `{$table}` SET " . implode( ', ', $assign ) . ', updated_at = %s WHERE id = %d', ...$args ) );
			return (int) $row['id'];
		}
		return $this->db->insert( 'ccap_media', array( 'uid' => $this->db->uuid() ) + $values, array( 'created_at', 'updated_at' ) );
	}

	/**
	 * Write a file atomically below the storage class; an existing file
	 * with the same SHA-256 is taken over (resume, I-1), a half-written or
	 * different one is replaced (IMP-029).
	 *
	 * @param string $data   Content.
	 * @param string $sha    Expected SHA-256.
	 * @param string $name   File name.
	 * @param string $access public|protected.
	 * @return string Absolute path.
	 * @throws CourseException When the folder or file is not writable.
	 */
	public static function store( string $data, string $sha, string $name, string $access ): string {
		$dir    = 'public' === $access ? ProtectedStorage::dir( 'media' ) . '/' . $sha : ProtectedStorage::dir( 'protected' ) . '/import/' . $sha;
		$target = $dir . '/' . $name;
		if ( is_file( $target ) && ! is_link( $target ) && hash_file( 'sha256', $target ) === $sha ) {
			return $target;
		}
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Media folder not writable.', 500 );
		}
		$tmp     = $dir . '/' . wp_generate_password( 12, false, false ) . '.part';
		$written = file_put_contents( $tmp, $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary file in the own media folder.
		if ( strlen( $data ) !== $written || hash_file( 'sha256', $tmp ) !== $sha || ! @rename( $tmp, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- Atomic replace; failure is reported.
			if ( is_file( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			throw new CourseException( 'ccap_storage_unavailable', 'Media file not writable.', 500 );
		}
		return $target;
	}

	/**
	 * Replace the imported media links of an object (`media[]`); local
	 * links and links of other origins stay. Unknown or archived media are
	 * left out (the reference drops, R0-38 §5.3).
	 *
	 * @param string            $system      Source system.
	 * @param string            $object_type lesson|question_revision|course|module.
	 * @param int               $object_id   Object.
	 * @param array<int, mixed> $links       Entries `{media, role, sort}`.
	 * @return int Links written.
	 */
	public function link( string $system, string $object_type, int $object_id, array $links ): int {
		$cm    = $this->db->table( 'ccap_content_media' );
		$media = $this->db->table( 'ccap_media' );
		$this->db->execute( $this->db->prepare( "DELETE cm FROM `{$cm}` cm JOIN `{$media}` m ON m.id = cm.media_id WHERE cm.object_type = %s AND cm.object_id = %d AND m.source_system = %s", $object_type, $object_id, $system ) );
		$written = 0;
		foreach ( array_values( $links ) as $i => $link ) {
			if ( ! is_array( $link ) ) {
				continue;
			}
			$row = $this->db->row( $this->db->prepare( "SELECT m.id, m.media_type FROM `{$media}` m JOIN `" . $this->db->table( 'ccap_source_map' ) . "` s ON s.object_id = m.id AND s.object_type = 'media' WHERE s.source_system = %s AND s.source_id = %s AND m.status = 'active'", $system, (string) ( $link['media'] ?? '' ) ) );
			if ( null === $row ) {
				continue;
			}
			$this->db->execute(
				$this->db->prepare(
					"INSERT INTO `{$cm}` (object_type, object_id, media_id, role, sort_order) VALUES (%s, %d, %d, %s, %d) ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)",
					$object_type,
					$object_id,
					(int) $row['id'],
					self::role( (string) ( $link['role'] ?? 'attachment' ), (string) $row['media_type'] ),
					(int) ( $link['sort'] ?? $i + 1 )
				)
			);
			++$written;
		}
		return $written;
	}

	/**
	 * Stored role of a package role.
	 *
	 * @param string $role       primary|inline|attachment|caption.
	 * @param string $media_type Media type.
	 * @return string
	 */
	public static function role( string $role, string $media_type ): string {
		if ( 'caption' === $role || 'captions' === $media_type ) {
			return 'subtitle';
		}
		return self::ROLE_MAP[ $role ][ $media_type ] ?? 'attachment';
	}

	/**
	 * Archive a medium (never deleted; the file stays).
	 *
	 * @param int $media_id Medium.
	 * @return void
	 */
	public function archive( int $media_id ): void {
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_media' ) . "` SET status = 'archived', updated_at = %s WHERE id = %d", $this->db->now(), $media_id ) );
	}

	/**
	 * Remove a medium a discarded batch created (never activated). The file
	 * stays for the retention job (R0-38 §5.1 step 4).
	 *
	 * @param int $media_id Medium.
	 * @return void
	 */
	public function delete( int $media_id ): void {
		$table = $this->db->table( 'ccap_media' );
		$post  = (int) $this->db->scalar( $this->db->prepare( "SELECT attachment_id FROM `{$table}` WHERE id = %d", $media_id ) );
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_content_media' ) . '` WHERE media_id = %d', $media_id ) );
		$this->db->execute( $this->db->prepare( "DELETE FROM `{$table}` WHERE id = %d", $media_id ) );
		if ( $post > 0 && null !== get_post( $post ) ) {
			delete_post_meta( $post, '_wp_attached_file' );
			delete_post_meta( $post, '_wp_attachment_metadata' );
			wp_delete_post( $post, true );
		}
	}
}
