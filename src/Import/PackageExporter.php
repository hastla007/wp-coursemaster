<?php
/**
 * Course export as `cc-package` v1 (R0-10 §6.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Access\RightsGate;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Exports courses with their modules, lessons, pool questions, the media
 * linked to lessons and questions (files in `media/…` with SHA-256, size,
 * MIME type, access class and rights) and the exam rules of the courses
 * as blueprints with their fixed variants (R0-10 §6.1, E-382). IDs are
 * never exported, only `source_id`s (the source map value or the stable
 * object UID). A re-import matches through `ccap_source_map` only, never
 * through the UID (E-382). Learner data, grants, attempts and settings
 * outside the course are never part of a package. The archive goes to the
 * private `packages` folder; media files are streamed into it from disk.
 *
 * Rights (R0-10 §6.1, E-66, IMP-031): foreign lessons and questions
 * (R0-10 §2.2, {@see Rights::own_system()}) and those the delivery lock
 * withholds ({@see RightsGate}: `unknown`, `restricted`, expired licence,
 * required image withheld) are left out by default and listed in the
 * manifest under `export.excluded[] {entity, source_id, reason}`; the
 * references to them (module lessons, question pool) are dropped so the
 * package stays valid. `$include_foreign` (confirmed and audited by the
 * caller) exports them anyway. Courses and modules are containers and
 * always exported with their rights metadata (they withhold nothing,
 * SET-CC-rechte-auslieferung). Media follow the same rule (foreign source
 * or withheld by the delivery lock); a question whose required image is
 * left out is left out with it. Media the package format cannot carry
 * (file type outside the V4 allowlist, missing file) are always left out
 * (`unsupported_type`, `file_missing`), whatever the opt-in.
 *
 * Exam rules (draft and published, not retired) become blueprint records;
 * frozen (published) fixed variants of the course are attached to the
 * blueprint they were imported with, else to the published rule (else the
 * newest draft). Rules or variants that would not import (module or
 * question not in the package, invalid passing rule) are left out and
 * named in `report.warnings[]`. Fields beyond schema 1.0 (form mode,
 * setting overrides, points per draw, part labels, empty-part policy,
 * draws-free variant blueprints, stored media roles) raise the package to
 * schema 1.1 only when a record uses them ({@see self::schema_minor()}).
 */
final class PackageExporter {

	/**
	 * Maximum records per data file (R0-10 §1.1); larger entities are split.
	 */
	public const RECORDS_PER_FILE = 5000;

	/**
	 * Media roles of schema 1.0 (R0-10 §1.3) in the order the export tries
	 * them ({@see self::package_role()}); any other role needs 1.1.
	 */
	public const ROLES_V10 = array( 'caption', 'attachment', 'primary', 'inline' );

	/**
	 * Build the package records (no file I/O).
	 *
	 * @param Actor $actor           Author.
	 * @param array $courses         Courses.
	 * @param bool  $include_foreign Export foreign and withheld content too (see {@see self::collect()}).
	 * @phpstan-param list<int> $courses
	 * @return array<string, list<array<string, mixed>>>
	 * @throws CourseException When a course may not be edited.
	 */
	public static function records( Actor $actor, array $courses, bool $include_foreign = false ): array {
		return self::collect( $actor, $courses, $include_foreign )['records'];
	}

	/**
	 * Build the package records and the manifest block `export`
	 * (R0-10 §1.2: `{course_ids[], included_foreign, excluded[]}`).
	 *
	 * @param Actor $actor           Author.
	 * @param array $courses         Courses.
	 * @param bool  $include_foreign Export foreign and withheld content too (confirmed opt-in).
	 * @phpstan-param list<int> $courses
	 * @return array{records: array<string, list<array<string, mixed>>>, export: array{course_ids: list<string>, included_foreign: bool, excluded: list<array{entity: string, source_id: string, reason: string}>}, foreign: int, media: array<string, array{file: string, mime: string}>, warnings: list<array{code: string, entity: string, source_id: string, message: string}>}
	 *         `foreign`: number of foreign or withheld records exported because of the opt-in;
	 *         `media`: package path → file on disk for {@see self::write()};
	 *         `warnings`: blueprints or variants left out because they would not import.
	 * @throws CourseException When a course may not be edited, or `ccap_export_too_large` when the media exceed the package limits.
	 */
	public static function collect( Actor $actor, array $courses, bool $include_foreign = false ): array {
		$db       = Database::current();
		$gate     = RightsGate::current();
		$rights   = Rights::current();
		$excluded = array();
		$foreign  = array();
		$ids      = array();
		$exclude  = static function ( string $entity, string $source_id, string $reason ) use ( &$excluded ): void {
			$excluded[ $entity . "\0" . $source_id ] = array(
				'entity'    => $entity,
				'source_id' => $source_id,
				'reason'    => $reason,
			);
		};
		$skip     = static function ( string $entity, string $source_id, ?string $reason ) use ( $include_foreign, &$foreign, $exclude ): bool {
			if ( null === $reason ) {
				return false;
			}
			if ( $include_foreign ) {
				$foreign[ $entity . "\0" . $source_id ] = true;
				return false;
			}
			$exclude( $entity, $source_id, $reason );
			return true;
		};
		$identity = new ObjectIdentity( $db );
		$repo     = QuestionRepository::current();
		$out      = array(
			'courses'   => array(),
			'modules'   => array(),
			'lessons'   => array(),
			'questions' => array(),
		);
		$media    = array(
			'records' => array(),
			'files'   => array(),
			'ids'     => array(),
			'bytes'   => 0,
		);
		$warnings = array();
		// Media links `{media, role, sort}` of an object; `$dropped` tells whether a linked medium was left out.
		$links    = static function ( string $object_type, int $object_id, bool &$dropped ) use ( $db, $gate, $rights, $skip, $exclude, &$media ): array {
			$dropped = false;
			$out     = array();
			$rows    = $db->rows( $db->prepare( 'SELECT cm.role, cm.sort_order, m.* FROM `' . $db->table( 'ccap_content_media' ) . '` cm JOIN `' . $db->table( 'ccap_media' ) . "` m ON m.id = cm.media_id WHERE cm.object_type = %s AND cm.object_id = %d AND m.status = 'active' ORDER BY cm.sort_order, m.id, cm.role", $object_type, $object_id ) );
			foreach ( $rows as $row ) {
				$id = (int) $row['id'];
				if ( ! array_key_exists( $id, $media['ids'] ) ) {
					$media['ids'][ $id ] = self::medium( $row, $gate, $rights, $skip, $exclude, $media );
				}
				$source = $media['ids'][ $id ];
				if ( null === $source ) {
					$dropped = true;
					continue;
				}
				$out[] = array(
					'media' => $source,
					'role'  => self::package_role( (string) $row['role'], (string) $media['records'][ $source ]['media_type'] ),
					'sort'  => (int) $row['sort_order'],
				);
			}
			return $out;
		};
		$seen     = array();
		$modules  = array();
		$qsources = array();
		foreach ( $courses as $course_id ) {
			CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::EDIT_COURSE, array( $course_id ) ), 'Not allowed to export this course.' );
			$course = get_post( $course_id );
			if ( ! $course instanceof \WP_Post ) {
				throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
			}
			$course_source = self::source_id( 'course', $course_id, $identity->ensure( $course ) );
			$course_links  = array();
			foreach ( CourseStructure::current()->modules( $course_id ) as $link ) {
				$module = get_post( $link['module_id'] );
				if ( ! $module instanceof \WP_Post ) {
					continue;
				}
				$module_id                     = self::source_id( 'module', $link['module_id'], $identity->ensure( $module ) );
				$modules[ $link['module_id'] ] = $module_id;
				$course_links[]                = array(
					'module'   => $module_id,
					'sort'     => $link['sort_order'],
					'required' => $link['required'],
				);
				if ( isset( $seen[ 'm' . $link['module_id'] ] ) ) {
					continue;
				}
				$seen[ 'm' . $link['module_id'] ] = true;
				$lessons                          = array();
				foreach ( CourseStructure::current()->lessons( $link['module_id'] ) as $lesson ) {
					if ( 'publish' !== $lesson->post_status ) {
						continue;
					}
					$lesson_id = self::source_id( 'lesson', (int) $lesson->ID, $identity->ensure( $lesson ) );
					if ( $skip( 'lessons', $lesson_id, self::excluded_reason( $rights, $gate->post( (int) $lesson->ID ), (int) $lesson->ID, array( 'lesson' ) ) ) ) {
						continue;
					}
					$lessons[]        = array(
						'lesson' => $lesson_id,
						'sort'   => (int) $lesson->menu_order,
					);
					$definition       = ActivityService::definition( (int) $lesson->ID );
					$dropped          = false;
					$lesson_media     = $links( 'lesson', (int) $lesson->ID, $dropped );
					$out['lessons'][] = self::sealed(
						array(
							'source_id'     => $lesson_id,
							'title'         => $lesson->post_title,
							'body_html'     => $lesson->post_content,
							'activity_type' => '' === $definition['type'] ? 'lesson' : $definition['type'],
							'settings'      => (object) $definition['settings'],
						) + ( array() === $lesson_media ? array() : array( 'media' => $lesson_media ) ) + self::rights( (int) $lesson->ID )
					);
				}
				$pool = array();
				foreach ( $repo->pool( $link['module_id'], true ) as $question_id ) {
					$uid     = (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question_id ) );
					$qsource = self::source_id( 'question', $question_id, $uid );
					if ( $skip( 'questions', $qsource, self::excluded_reason( $rights, $gate->question( $question_id ), $question_id, array( 'question', 'flashcard' ) ) ) ) {
						continue;
					}
					if ( isset( $seen[ 'q' . $question_id ] ) ) {
						if ( isset( $qsources[ $question_id ] ) ) {
							$pool[] = array(
								'question' => $qsource,
								'sort'     => count( $pool ) + 1,
							);
						}
						continue;
					}
					$seen[ 'q' . $question_id ] = true;
					$current                    = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question_id ) );
					$revision                   = (array) $repo->revision( $current );
					$required                   = '1' === (string) $db->scalar( $db->prepare( 'SELECT image_required FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE id = %d', $current ) );
					$dropped                    = false;
					$question_media             = $links( 'question_revision', $current, $dropped );
					if ( $required && $dropped ) {
						// The required image was left out: the question would not import (R0-38 §5.3).
						$exclude( 'questions', $qsource, 'media_excluded' );
						continue;
					}
					$pool[]                   = array(
						'question' => $qsource,
						'sort'     => count( $pool ) + 1,
					);
					$qsources[ $question_id ] = $qsource;
					$out['questions'][]       = self::sealed(
						array(
							'source_id' => $qsource,
							'revision'  => array(
								'question_type' => (string) $revision['question_type'],
								'text_html'     => (string) $revision['question_text'],
								'settings'      => (object) ( $revision['settings'] ?? array() ),
							) + self::explanations( $revision ) + ( $required ? array( 'image_required' => true ) : array() ),
							'answers'   => array_map(
								static fn( array $a ): array => array(
									'key'       => (string) $a['key'],
									'text_html' => (string) $a['text'],
									'correct'   => ! empty( $a['correct'] ),
								),
								array_values( (array) $revision['answers'] )
							),
							'tags'      => array_values( (array) $revision['tags'] ),
						) + ( array() === $question_media ? array() : array( 'media' => $question_media ) )
					);
				}
				$out['modules'][] = self::sealed(
					array(
						'source_id'     => $module_id,
						'title'         => $module->post_title,
						'summary'       => $module->post_excerpt,
						'lessons'       => $lessons,
						'question_pool' => $pool,
					) + self::rights( $link['module_id'] )
				);
			}
			$ids[]            = $course_source;
			$out['courses'][] = self::sealed(
				array(
					'source_id'        => $course_source,
					'title'            => $course->post_title,
					'slug'             => $course->post_name,
					'summary'          => $course->post_excerpt,
					'description_html' => $course->post_content,
					'modules'          => $course_links,
				) + self::rights( $course_id )
			);
			foreach ( self::blueprints( $course_id, $course_source, $modules, $qsources, $warnings ) as $blueprint ) {
				$out['blueprints'][] = $blueprint;
			}
		}
		if ( array() !== $media['records'] ) {
			$out = array( 'media' => array_values( $media['records'] ) ) + $out;
		}
		ksort( $excluded, SORT_STRING );
		return array(
			'records'  => $out,
			'export'   => array(
				'course_ids'       => $ids,
				'included_foreign' => $include_foreign,
				'excluded'         => array_values( $excluded ),
			),
			// Left out after the opt-in (file type, missing file, required image): not counted as exported.
			'foreign'  => count( array_diff_key( $foreign, $excluded ) ),
			'media'    => $media['files'],
			'warnings' => $warnings,
		);
	}

	/**
	 * Media record of a `ccap_media` row (R0-10 §1.3 `media`), registered
	 * in `$media`; null when the medium is left out (rights, file type,
	 * missing file). The file is not read into memory: size and SHA-256
	 * come from the file on disk.
	 *
	 * @param array<string, mixed> $row     Media row.
	 * @param RightsGate           $gate    Delivery lock.
	 * @param Rights               $rights  Rights rules.
	 * @param callable             $skip    Rights exclusion (entity, source ID, reason) → left out.
	 * @param callable             $exclude Unconditional exclusion (entity, source ID, reason).
	 * @param array                $media   Collected media (updated).
	 * @phpstan-param array{records: array<string, array<string, mixed>>, files: array<string, array{file: string, mime: string}>, ids: array<int, string|null>, bytes: int} $media
	 * @return string|null Source ID.
	 * @throws CourseException `ccap_export_too_large` when the media exceed the package limits.
	 */
	private static function medium( array $row, RightsGate $gate, Rights $rights, callable $skip, callable $exclude, array &$media ): ?string {
		$id     = (int) $row['id'];
		$source = self::source_id( 'media', $id, (string) $row['uid'] );
		$reason = $gate->medium( $id ) ?? ( $rights->own_system( (string) ( $row['source_system'] ?? '' ) ) ? null : 'foreign' );
		if ( $skip( 'media', $source, $reason ) ) {
			return null;
		}
		$attachment = (int) ( $row['attachment_id'] ?? 0 );
		$file       = $attachment > 0 ? get_attached_file( $attachment ) : false;
		if ( ! is_string( $file ) || ! is_file( $file ) || ! is_readable( $file ) ) {
			$exclude( 'media', $source, 'file_missing' );
			return null;
		}
		$mime = (string) $row['mime_type'];
		$name = self::media_name( wp_basename( $file ), $mime );
		if ( null === $name || null !== PackageValidator::file_error( $name, $file ) ) {
			// V4 (R0-10 §1.1): only file types of the allowlist whose content matches the type.
			$exclude( 'media', $source, 'unsupported_type' );
			return null;
		}
		$sha  = (string) hash_file( 'sha256', $file );
		$size = (int) filesize( $file );
		$path = 'media/' . substr( $sha, 0, 12 ) . '/' . $name;
		if ( ! isset( $media['files'][ $path ] ) ) {
			$media['bytes'] += $size;
			if ( $media['bytes'] > PackageValidator::MAX_ZIP || count( $media['files'] ) + 2 > PackageValidator::MAX_ENTRIES ) {
				// Media are stored uncompressed: the archive would exceed the package limits (R0-10 §3.2 V1).
				throw new CourseException( 'ccap_export_too_large', 'The media of the chosen courses exceed the package limits.', 413 );
			}
			$media['files'][ $path ] = array(
				'file' => $file,
				'mime' => $mime,
			);
		}
		$type   = self::media_type( (string) $row['media_type'], $mime );
		$alt    = sanitize_text_field( (string) ( $row['alt_text'] ?? '' ) );
		$alt    = '' === $alt && 'image' === $type ? sanitize_text_field( (string) get_post_meta( $attachment, '_wp_attachment_image_alt', true ) ) : $alt;
		$alt    = substr( $alt, 0, 255 );
		$title  = sanitize_text_field( (string) get_post_field( 'post_title', $attachment ) );
		$record = array(
			'source_id'  => $source,
			'path'       => $path,
			'title'      => '' === $title ? (string) pathinfo( $name, PATHINFO_FILENAME ) : $title,
			'media_type' => $type,
			'mime_type'  => $mime,
			'size'       => $size,
			'sha256'     => $sha,
			'alt_text'   => $alt,
		);
		if ( 'image' === $type && '' === trim( $alt ) ) {
			// Without an alternative text an image only imports as decorative (R0-15).
			$record['decorative'] = true;
		}
		$record['caption']           = sanitize_textarea_field( (string) ( $row['caption'] ?? '' ) );
		$record['description']       = sanitize_textarea_field( (string) ( $row['description'] ?? '' ) );
		$record['access']            = 'public' === $row['access'] ? 'public' : 'protected';
		$record['rights']            = self::media_rights( $row );
		$media['records'][ $source ] = self::sealed( $record );
		return $source;
	}

	/**
	 * Package file name of a medium: the stored name with a safe stem
	 * (no inner dots, R0-10 §1.1 V4) and the extension of its MIME type;
	 * null when the type has no extension in the V4 allowlist.
	 *
	 * @param string $base Stored file name.
	 * @param string $mime MIME type.
	 * @return string|null
	 */
	private static function media_name( string $base, string $mime ): ?string {
		$ext = strtolower( (string) pathinfo( $base, PATHINFO_EXTENSION ) );
		if ( ( PackageValidator::MEDIA_TYPES[ $ext ] ?? '' ) !== $mime ) {
			$ext = array_search( $mime, PackageValidator::MEDIA_TYPES, true );
			if ( false === $ext ) {
				return null;
			}
		}
		$stem = trim( (string) preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string) pathinfo( $base, PATHINFO_FILENAME ) ), '-_' );
		$stem = '' === $stem ? 'media' : substr( $stem, 0, 100 );
		return $stem . '.' . $ext;
	}

	/**
	 * Package media type (`image/audio/video/document/captions`) of a stored medium.
	 *
	 * @param string $stored Stored media type (editor uploads keep the MIME main type).
	 * @param string $mime   MIME type.
	 * @return string
	 */
	private static function media_type( string $stored, string $mime ): string {
		if ( in_array( $stored, ImportMedia::TYPES, true ) ) {
			return $stored;
		}
		if ( 'text/vtt' === $mime ) {
			return 'captions';
		}
		$main = explode( '/', $mime )[0];
		return in_array( $main, array( 'image', 'audio', 'video' ), true ) ? $main : 'document';
	}

	/**
	 * Package role of a stored media role: the schema 1.0 role that imports
	 * back to the same stored role ({@see ImportMedia::role()}), else the
	 * stored role itself (schema 1.1).
	 *
	 * @param string $role       Stored role.
	 * @param string $media_type Package media type.
	 * @return string
	 */
	public static function package_role( string $role, string $media_type ): string {
		foreach ( self::ROLES_V10 as $candidate ) {
			if ( ImportMedia::role( $candidate, $media_type ) === $role ) {
				return $candidate;
			}
		}
		return $role;
	}

	/**
	 * Rights object of a medium (R0-10 §2.1): status, owner and the licence
	 * fields, in the field order of {@see Rights::COURSE_FIELDS}.
	 *
	 * @param array<string, mixed> $row Media row.
	 * @return array<string, string>
	 */
	private static function media_rights( array $row ): array {
		$license = json_decode( (string) ( $row['license_reference'] ?? '' ), true );
		$license = is_array( $license ) ? $license : array();
		$values  = array(
			'status' => in_array( (string) $row['rights_status'], Rights::STATUSES, true ) ? (string) $row['rights_status'] : 'unknown',
			'owner'  => (string) ( $row['rights_owner'] ?? '' ),
		) + $license;
		if ( '' !== (string) ( $row['source_url'] ?? '' ) && ! isset( $values['source_url'] ) ) {
			$values['source_url'] = (string) $row['source_url'];
		}
		$out = array();
		foreach ( Rights::COURSE_FIELDS as $field ) {
			$value = $values[ $field ] ?? null;
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}
			$candidate = $out + array( $field => $value );
			if ( Rights::valid_package_rights( $candidate ) ) {
				$out = $candidate;
			}
		}
		return $out;
	}

	/**
	 * Blueprint records of a course (R0-10 §1.3 `blueprints`): its draft
	 * and published exam rules with their module draws and passing rule,
	 * and the frozen fixed variants. Rules and variants that would not
	 * import are left out with a warning.
	 *
	 * @param int                $course_id Course.
	 * @param string             $course    Course source ID.
	 * @param array<int, string> $modules   Exported modules: ID → source ID.
	 * @param array<int, string> $questions Exported questions: ID → source ID.
	 * @param array              $warnings  Warnings (appended).
	 * @phpstan-param list<array{code: string, entity: string, source_id: string, message: string}> $warnings
	 * @return list<array<string, mixed>>
	 */
	private static function blueprints( int $course_id, string $course, array $modules, array $questions, array &$warnings ): array {
		$db      = Database::current();
		$rules   = ExamRules::current();
		$records = array();
		$warn    = static function ( string $code, string $source, string $message ) use ( &$warnings ): void {
			$warnings[] = array(
				'code'      => $code,
				'entity'    => 'blueprints',
				'source_id' => $source,
				'message'   => $message,
			);
		};
		$default = null;
		foreach ( $db->rows( $db->prepare( 'SELECT id, uid, status FROM `' . $db->table( 'ccap_exam_rule' ) . "` WHERE course_id = %d AND status IN ('draft', 'published') ORDER BY id", $course_id ) ) as $row ) {
			$rule   = $rules->get( (int) $row['id'] );
			$source = self::source_id( 'blueprint', (int) $row['id'], (string) $row['uid'] );
			$record = null === $rule ? null : self::blueprint( $rule, $source, $course, $modules );
			if ( null === $record ) {
				$warn( 'blueprint_not_exported', $source, 'Exam rule not exported: a module of its draws or passing rule is not part of the course, or the passing rule is invalid.' );
				continue;
			}
			$records[ $source ] = $record;
			if ( 'published' === $row['status'] || null === $default || 'published' !== $records[ $default ]['status'] ) {
				$default = $source;
			}
		}
		$variants = $db->rows( $db->prepare( 'SELECT id, variant_no, source_id FROM `' . $db->table( 'ccap_exam_variant' ) . "` WHERE course_id = %d AND status = 'published' ORDER BY variant_no, id", $course_id ) );
		foreach ( $variants as $variant ) {
			$label  = 'variant ' . (int) $variant['variant_no'];
			$target = null;
			$key    = (string) (int) $variant['variant_no'];
			foreach ( array_keys( $records ) as $candidate ) {
				$prefix = $candidate . '#';
				if ( str_starts_with( (string) $variant['source_id'], $prefix ) && strlen( (string) $variant['source_id'] ) > strlen( $prefix ) ) {
					$target = $candidate;
					$key    = substr( (string) $variant['source_id'], strlen( $prefix ) );
				}
			}
			$target = $target ?? $default;
			if ( null === $target ) {
				$warn( 'variant_not_exported', $label, 'Fixed variant not exported: the course has no exam rule to carry it.' );
				continue;
			}
			$ids  = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_exam_variant_question' ) . '` WHERE variant_id = %d ORDER BY position', (int) $variant['id'] ) ) );
			$list = array();
			foreach ( $ids as $question ) {
				if ( ! isset( $questions[ $question ] ) ) {
					$list = array();
					break;
				}
				$list[] = $questions[ $question ];
			}
			$keys = array_column( $records[ $target ]['fixed_variants'], 'key' );
			if ( in_array( $key, $keys, true ) ) {
				$key = 'v' . (int) $variant['variant_no'];
			}
			if ( array() === $list || in_array( $key, $keys, true ) ) {
				$warn( 'variant_not_exported', $target . '#' . $key, 'Fixed variant not exported: one of its questions is not part of the package.' );
				continue;
			}
			$records[ $target ]['fixed_variants'][] = array(
				'key'       => $key,
				'questions' => $list,
			);
		}
		$out = array();
		foreach ( $records as $source => $record ) {
			if ( array() === $record['fixed_variants'] ) {
				unset( $record['fixed_variants'] );
			}
			if ( null !== ImportBlueprints::record_error( $record ) ) {
				$warn( 'blueprint_not_exported', $source, 'Exam rule not exported: it does not meet the package rules (draws, time limit or settings).' );
				continue;
			}
			$out[] = self::sealed( $record );
		}
		return $out;
	}

	/**
	 * Blueprint record of a rule (without fixed variants and hash), or null
	 * when a module is not exported or the passing rule is invalid.
	 *
	 * @param array<string, mixed> $rule    Rule from {@see ExamRules::get()}.
	 * @param string               $source  Blueprint source ID.
	 * @param string               $course  Course source ID.
	 * @param array<int, string>   $modules Exported modules: ID → source ID.
	 * @return array<string, mixed>|null
	 */
	private static function blueprint( array $rule, string $source, string $course, array $modules ): ?array {
		$passing = $rule['passing_rule'];
		if ( ! is_array( $passing ) || array() !== ExamRules::validate_passing_rule( $passing ) ) {
			return null;
		}
		$out_rule = array(
			'min_ratio' => array(
				'num' => (int) $passing['min_ratio']['num'],
				'den' => (int) $passing['min_ratio']['den'],
			),
		);
		if ( isset( $passing['parts'] ) ) {
			$out_rule['parts'] = array();
			foreach ( array_values( (array) $passing['parts'] ) as $i => $part ) {
				$sections = isset( $part['sections'] ) ? (array) $part['sections'] : array( $part['section'] );
				$list     = array();
				foreach ( $sections as $section ) {
					if ( ! isset( $modules[ (int) $section ] ) ) {
						return null;
					}
					$list[] = $modules[ (int) $section ];
				}
				$entry = array(
					'key'         => (string) ( $part['key'] ?? 'P' . ( $i + 1 ) ),
					'modules'     => $list,
					'min_correct' => (int) $part['min_ratio']['num'],
					'of'          => (int) $part['min_ratio']['den'],
				);
				if ( isset( $part['label'] ) && is_string( $part['label'] ) && '' !== $part['label'] ) {
					$entry['label'] = $part['label'];
				}
				$out_rule['parts'][] = $entry;
			}
		}
		if ( isset( $passing['empty_part_policy'] ) ) {
			$out_rule['empty_part_policy'] = (string) $passing['empty_part_policy'];
		}
		$draws = array();
		foreach ( (array) $rule['blueprints'] as $blueprint ) {
			if ( ! isset( $modules[ (int) $blueprint['module_id'] ] ) ) {
				return null;
			}
			$points  = json_decode( (string) $blueprint['rules'], true );
			$points  = is_array( $points ) && is_int( $points['points'] ?? null ) ? $points['points'] : 1;
			$draws[] = array(
				'module' => $modules[ (int) $blueprint['module_id'] ],
				'count'  => (int) $blueprint['question_count'],
			) + ( 1 === $points ? array() : array( 'points' => $points ) );
		}
		$stored   = (array) $rule['settings_stored'];
		$settings = array( 'purpose' => in_array( $stored['purpose'] ?? null, ImportBlueprints::PURPOSES, true ) ? (string) $stored['purpose'] : 'evidence' );
		$mode     = ExamRules::form_mode( $rule );
		if ( '' !== $mode ) {
			$settings['form_mode'] = $mode;
		}
		$overrides = ExamRules::SETTINGS_FORMAT === (int) ( $stored['_format'] ?? 0 ) ? (array) ( $stored['overrides'] ?? array() ) : array();
		if ( array() !== $overrides ) {
			ksort( $overrides );
			$settings['overrides'] = $overrides;
		}
		return array(
			'source_id'      => $source,
			'course'         => $course,
			'version'        => substr( (string) $rule['version'], 0, 48 ),
			'status'         => 'published' === $rule['status'] ? 'published' : 'draft',
			'time_limit_s'   => $rule['time_limit_column'],
			'passing_rule'   => $out_rule,
			'draws'          => $draws,
			'fixed_variants' => array(),
			'settings'       => $settings,
		);
	}

	/**
	 * Lowest schema minor version that carries the records (R0-10 §1.6):
	 * 1 when a blueprint uses form mode, setting overrides, points per
	 * draw, part labels, the empty-part policy or no draws, or a media
	 * link uses a stored role outside {@see self::ROLES_V10}; else 0, so
	 * that packages without these fields stay importable on 1.0 sites.
	 *
	 * @param array<string, list<array<string, mixed>>> $records Records per entity.
	 * @return int
	 */
	public static function schema_minor( array $records ): int {
		foreach ( $records['blueprints'] ?? array() as $record ) {
			$settings = (array) ( $record['settings'] ?? array() );
			$passing  = (array) ( $record['passing_rule'] ?? array() );
			if ( isset( $settings['form_mode'] ) || isset( $settings['overrides'] ) || isset( $passing['empty_part_policy'] ) || array() === (array) ( $record['draws'] ?? array() ) ) {
				return 1;
			}
			foreach ( (array) ( $record['draws'] ?? array() ) as $draw ) {
				if ( is_array( $draw ) && isset( $draw['points'] ) ) {
					return 1;
				}
			}
			foreach ( (array) ( $passing['parts'] ?? array() ) as $part ) {
				if ( is_array( $part ) && isset( $part['label'] ) ) {
					return 1;
				}
			}
		}
		foreach ( array( 'lessons', 'questions' ) as $entity ) {
			foreach ( $records[ $entity ] ?? array() as $record ) {
				foreach ( (array) ( $record['media'] ?? array() ) as $link ) {
					if ( is_array( $link ) && ! in_array( (string) ( $link['role'] ?? '' ), self::ROLES_V10, true ) ) {
						return 1;
					}
				}
			}
		}
		return 0;
	}

	/**
	 * Why a lesson or question is left out of an export by default: the
	 * reason of the delivery lock (`unknown`, `restricted`, `expired`), else
	 * `foreign` for content of a foreign source system, else null.
	 *
	 * @param Rights      $rights   Rights rules.
	 * @param string|null $withheld Verdict of the delivery lock.
	 * @param int         $id       Object ID.
	 * @param array       $types    Object types in the source map.
	 * @phpstan-param list<string> $types
	 * @return string|null
	 */
	private static function excluded_reason( Rights $rights, ?string $withheld, int $id, array $types ): ?string {
		if ( null !== $withheld ) {
			return $withheld;
		}
		$db     = Database::current();
		$system = (string) $db->scalar( $db->prepare( 'SELECT source_system FROM `' . $db->table( 'ccap_source_map' ) . '` WHERE object_id = %d AND object_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') LIMIT 1', $id, ...$types ) );
		return $rights->own_system( $system ) ? null : 'foreign';
	}

	/**
	 * Write a package from records.
	 *
	 * @param array<string, list<array<string, mixed>>>                                                 $records Records per entity.
	 * @param string                                                                                    $system  `source.system`.
	 * @param string                                                                                    $title   Title.
	 * @param string|null                                                                               $file    Target path (default: packages folder).
	 * @param int                                                                                       $per_file Records per data file (R0-10 §1.1: ≤ 5 000).
	 * @param array<string, string|array{data: string, mime: string}|array{file: string, mime: string}> $media Media files `media/…` → content (MIME from the extension), content with declared MIME, or a file on disk with declared MIME (streamed, never read into memory; {@see self::collect()}).
	 * @param bool                                                                                      $own_export Export of this site's courses: the manifest carries `source.exported_from_site` (installation UUID, D8).
	 * @param array<string, mixed>|null                                                                 $export Manifest block `export` from {@see self::collect()} (R0-10 §1.2); null leaves it out.
	 * @param array                                                                                     $warnings Manifest `report.warnings[]` (`code`, `entity`, `source_id`, `message`).
	 * @phpstan-param list<array{code: string, entity: string, source_id: string, message: string}> $warnings
	 * @return string Path.
	 * @throws CourseException When the archive cannot be written, or `ccap_export_too_large` when it exceeds the package limits.
	 */
	public static function write( array $records, string $system, string $title, ?string $file = null, int $per_file = self::RECORDS_PER_FILE, array $media = array(), bool $own_export = false, ?array $export = null, array $warnings = array() ): string {
		$files  = array();
		$disk   = array();
		$mimes  = array();
		$hashes = array();
		$sizes  = array();
		$counts = array();
		foreach ( $records as $entity => $list ) {
			$counts[ $entity ] = count( $list );
			foreach ( array_chunk( $list, max( 1, min( self::RECORDS_PER_FILE, $per_file ) ) ) as $i => $part ) {
				$files[ sprintf( 'data/%s/%06d.json', $entity, $i + 1 ) ] = (string) wp_json_encode( $part, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
		}
		foreach ( $media as $path => $entry ) {
			$ext = strtolower( (string) pathinfo( (string) $path, PATHINFO_EXTENSION ) );
			if ( is_array( $entry ) && isset( $entry['file'] ) ) {
				$disk[ $path ]   = $entry['file'];
				$hashes[ $path ] = (string) hash_file( 'sha256', $entry['file'] );
				$sizes[ $path ]  = (int) filesize( $entry['file'] );
			} else {
				$files[ $path ] = is_array( $entry ) ? $entry['data'] : $entry;
			}
			$mimes[ $path ] = is_array( $entry ) ? $entry['mime'] : ( PackageValidator::MEDIA_TYPES[ $ext ] ?? 'application/octet-stream' );
		}
		foreach ( $files as $path => $data ) {
			$hashes[ $path ] = hash( 'sha256', $data );
			$sizes[ $path ]  = strlen( $data );
		}
		if ( array_sum( $sizes ) > PackageValidator::MAX_UNPACKED || count( $sizes ) + 1 > PackageValidator::MAX_ENTRIES ) {
			throw new CourseException( 'ccap_export_too_large', 'The package would exceed the package limits.', 413 );
		}
		ksort( $hashes, SORT_STRING );
		$lines = '';
		foreach ( $hashes as $path => $sha ) {
			$lines .= $path . "\t" . $sha . "\n";
		}
		$content  = hash( 'sha256', $lines );
		$manifest = array(
			'format'              => 'cc-package',
			'schema_version'      => '1.' . self::schema_minor( $records ) . '.0',
			'package_id'          => $system . '-' . substr( $content, 0, 12 ),
			'title'               => $title,
			'created_at'          => gmdate( 'c' ),
			'generator'           => array(
				'name'    => 'course-captain-export',
				'version' => '1',
			),
			'source'              => array(
				'system'   => $system,
				'revision' => substr( $content, 0, 12 ),
			),
			'previous_package_id' => null,
			'languages'           => array( substr( get_locale(), 0, 2 ) ),
			'requires'            => array(
				'core'               => '>=1.0',
				'question_types'     => array(),
				'activity_providers' => array(),
			),
			'counts'              => $counts,
			'content_sha256'      => $content,
			'files'               => array(),
			'report'              => array(
				'warnings'      => $warnings,
				'skipped'       => array(),
				'not_converted' => array(),
			),
		);
		if ( $own_export ) {
			// D8 (R0-10 §2.2): a re-import on this site is recognised as own content.
			$manifest['source']['exported_from_site'] = self::installation_uuid();
		}
		if ( null !== $export ) {
			$manifest['export'] = $export;
		}
		foreach ( array_keys( $files + $disk ) as $path ) {
			$manifest['files'][] = array(
				'path'      => $path,
				'size'      => $sizes[ $path ],
				'mime_type' => $mimes[ $path ] ?? 'application/json',
				'sha256'    => $hashes[ $path ],
			);
		}
		$file = $file ?? ProtectedStorage::dir( 'packages' ) . '/export-' . wp_generate_uuid4() . '.zip';
		$zip  = new \ZipArchive();
		if ( true !== $zip->open( $file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Export archive not writable.', 500 );
		}
		$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		foreach ( $files as $path => $data ) {
			$zip->addFromString( $path, $data );
		}
		foreach ( $disk as $path => $source ) {
			// Streamed from disk when the archive is closed; media are stored, not compressed again.
			$zip->addFile( $source, $path );
			$zip->setCompressionName( $path, \ZipArchive::CM_STORE );
		}
		if ( ! $zip->close() ) {
			throw new CourseException( 'ccap_storage_unavailable', 'Export archive not writable.', 500 );
		}
		clearstatcache( true, $file );
		if ( (int) filesize( $file ) > PackageValidator::MAX_ZIP ) {
			wp_delete_file( $file );
			throw new CourseException( 'ccap_export_too_large', 'The package would exceed the package limits.', 413 );
		}
		return $file;
	}

	/**
	 * Installation UUID of this site (R0-10 §2.2, R0-38 §3.3), created on
	 * first use. It identifies the site only, never a person.
	 *
	 * @return string
	 */
	public static function installation_uuid(): string {
		return \CourseCaptain\Kernel\InstallationUuid::get();
	}

	/**
	 * `rights` of a course, module or lesson record from `_ccap_rights`
	 * (D8, R0-10 §2.1); nothing when no status is stored.
	 *
	 * @param int $post_id Course, module or lesson.
	 * @return array<string, array<string, string>>
	 */
	private static function rights( int $post_id ): array {
		$meta = Rights::course_meta( $post_id );
		return isset( $meta['status'] ) ? array( 'rights' => $meta ) : array();
	}

	/**
	 * Record with its hash.
	 *
	 * @param array<string, mixed> $record Record.
	 * @return array<string, mixed>
	 */
	public static function sealed( array $record ): array {
		$record['hash'] = Canonical::hash( $record );
		return $record;
	}

	/**
	 * Explanation texts of a revision (R0-10 §1.3, E-20): part of the format
	 * and exported in Free, even though only Pro delivers them (GM-197).
	 * Empty texts are left out so packages without explanations keep their hash.
	 *
	 * @param array<string, mixed> $revision Revision.
	 * @return array<string, string>
	 */
	private static function explanations( array $revision ): array {
		$out = array();
		foreach ( array( 'feedback_correct', 'feedback_incorrect' ) as $field ) {
			if ( '' !== trim( (string) ( $revision[ $field ] ?? '' ) ) ) {
				$out[ $field ] = (string) $revision[ $field ];
			}
		}
		return $out;
	}

	/**
	 * Source ID of an object: source map value, else the object UID.
	 *
	 * @param string $type     Object type.
	 * @param int    $id       Object ID.
	 * @param string $fallback UID.
	 * @return string
	 */
	private static function source_id( string $type, int $id, string $fallback ): string {
		$db     = Database::current();
		$mapped = $db->scalar( $db->prepare( 'SELECT source_id FROM `' . $db->table( 'ccap_source_map' ) . '` WHERE object_type = %s AND object_id = %d', $type, $id ) );
		return null === $mapped || '' === $mapped ? $fallback : (string) $mapped;
	}
}
