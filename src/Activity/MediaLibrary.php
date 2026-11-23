<?php
/**
 * Learning media: ccap_media and ccap_content_media (R0-06 E-13/E-14, R0-10).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Editor uploads stay WordPress attachments and get one `ccap_media` row
 * (idempotent per attachment) with SHA-256, MIME, size and access
 * `protected` by default (E-65). Assignments to lessons or question
 * revisions require the edit right on that object.
 */
final class MediaLibrary {

	public const ROLES = array( 'attachment', 'image', 'video', 'subtitle', 'poster', 'download' );

	/**
	 * Constructor.
	 *
	 * @param Database      $db     Database access.
	 * @param PolicyService $policy Policy.
	 */
	public function __construct( private Database $db, private PolicyService $policy ) {
	}

	/**
	 * Instance for the request.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), PolicyService::instance() );
	}

	/**
	 * Allowed MIME types (R0-10 §4.5; filterable, can only be narrowed by sites).
	 *
	 * @return list<string>
	 */
	public static function allowed_mime_types(): array {
		$defaults = array( 'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'audio/mpeg', 'text/vtt', 'text/plain', 'application/vnd.oasis.opendocument.text', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
		$filtered = apply_filters( 'course_captain_media_mime_types', $defaults );
		return array_values( array_intersect( $defaults, is_array( $filtered ) ? $filtered : array() ) );
	}

	/**
	 * Register an attachment as course medium (idempotent).
	 *
	 * @param Actor  $actor         Actor (needs `upload_files` and `ccap_edit_courses`).
	 * @param int    $attachment_id Attachment.
	 * @param string $access        protected|public.
	 * @return int Media ID.
	 * @throws CourseException On missing rights, missing file or forbidden type.
	 */
	public function register_attachment( Actor $actor, int $attachment_id, string $access = 'protected' ): int {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'upload_files' ) || ! user_can( $actor->user_id, 'ccap_edit_courses' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to register media.', 403 );
		}
		$table    = $this->db->table( 'ccap_media' );
		$existing = $this->db->scalar( $this->db->prepare( "SELECT id FROM `{$table}` WHERE attachment_id = %d", $attachment_id ) );
		if ( null !== $existing ) {
			return (int) $existing;
		}
		$file = get_attached_file( $attachment_id );
		$mime = (string) get_post_mime_type( $attachment_id );
		if ( false === $file || ! is_readable( $file ) ) {
			throw new CourseException( 'ccap_not_found', 'Attachment file missing.', 404 );
		}
		if ( ! in_array( $mime, self::allowed_mime_types(), true ) ) {
			throw new CourseException( 'ccap_media_type_forbidden', 'File type not allowed.', 422 );
		}
		return $this->db->insert(
			'ccap_media',
			array(
				'uid'           => $this->db->uuid(),
				'attachment_id' => $attachment_id,
				'media_type'    => explode( '/', $mime )[0],
				'mime_type'     => $mime,
				'size_bytes'    => (int) filesize( $file ),
				'sha256'        => (string) hash_file( 'sha256', $file ),
				'access'        => 'public' === $access ? 'public' : 'protected',
			),
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Assign a medium to a lesson, module, course or question revision.
	 *
	 * @param Actor  $actor       Actor.
	 * @param int    $media_id    Medium.
	 * @param string $object_type course|module|lesson|question_revision.
	 * @param int    $object_id   Object ID.
	 * @param string $role        Role (ROLES).
	 * @param int    $sort_order  Position.
	 * @return void
	 * @throws CourseException When not allowed or invalid.
	 */
	public function attach( Actor $actor, int $media_id, string $object_type, int $object_id, string $role = 'attachment', int $sort_order = 0 ): void {
		if ( ! in_array( $role, self::ROLES, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown media role.', 400 );
		}
		switch ( $object_type ) {
			case 'course':
				$decision = $this->policy->decide( $actor, PolicyService::EDIT_COURSE, array( $object_id ) );
				break;
			case 'module':
			case 'lesson':
				$decision = $this->policy->decide( $actor, PolicyService::EDIT_COURSE_ITEM, array( $object_id, $object_type ) );
				break;
			case 'question_revision':
				$question = (int) $this->db->scalar( $this->db->prepare( 'SELECT question_id FROM `' . $this->db->table( 'ccap_question_revision' ) . "` WHERE id = %d AND editorial_status = 'draft'", $object_id ) );
				if ( 0 === $question ) {
					throw new CourseException( 'ccap_revision_frozen', 'Only draft revisions take new media.', 409 );
				}
				$decision = $this->policy->decide( $actor, PolicyService::EDIT_QUESTION, array( $question ) );
				break;
			default:
				throw new CourseException( 'ccap_invalid_param', 'Unknown object type.', 400 );
		}
		CourseException::unless_allowed( $decision, 'Not allowed to edit this object.' );
		$table = $this->db->table( 'ccap_content_media' );
		$this->db->execute(
			$this->db->prepare(
				"INSERT INTO `{$table}` (object_type, object_id, media_id, role, sort_order) VALUES (%s, %d, %d, %s, %d) ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)",
				$object_type,
				$object_id,
				$media_id,
				$role,
				$sort_order
			)
		);
	}

	/**
	 * Media of an object in order.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object.
	 * @return list<array<string, mixed>>
	 */
	public function media_of( string $object_type, int $object_id ): array {
		$cm = $this->db->table( 'ccap_content_media' );
		$m  = $this->db->table( 'ccap_media' );
		return $this->db->rows( $this->db->prepare( "SELECT m.uid, m.mime_type, m.access, cm.role, cm.sort_order FROM `{$cm}` cm JOIN `{$m}` m ON m.id = cm.media_id WHERE cm.object_type = %s AND cm.object_id = %d ORDER BY cm.sort_order, m.id", $object_type, $object_id ) );
	}
}
