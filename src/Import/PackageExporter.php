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
use CourseCaptain\Ops\RestoreCheck;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Exports courses with their modules, lessons and pool questions. IDs are
 * never exported, only `source_id`s (the source map value or the stable
 * object UID). Learner data, grants, attempts and settings are never part
 * of a package. The archive goes to the private `packages` folder.
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
 * SET-CC-rechte-auslieferung).
 */
final class PackageExporter {

	/**
	 * Maximum records per data file (R0-10 §1.1); larger entities are split.
	 */
	public const RECORDS_PER_FILE = 5000;

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
	 * @return array{records: array<string, list<array<string, mixed>>>, export: array{course_ids: list<string>, included_foreign: bool, excluded: list<array{entity: string, source_id: string, reason: string}>}, foreign: int}
	 *         `foreign`: number of foreign or withheld records exported because of the opt-in.
	 * @throws CourseException When a course may not be edited.
	 */
	public static function collect( Actor $actor, array $courses, bool $include_foreign = false ): array {
		$db       = Database::current();
		$gate     = RightsGate::current();
		$rights   = Rights::current();
		$excluded = array();
		$foreign  = array();
		$ids      = array();
		$skip     = static function ( string $entity, string $source_id, ?string $reason ) use ( $include_foreign, &$excluded, &$foreign ): bool {
			if ( null === $reason ) {
				return false;
			}
			if ( $include_foreign ) {
				$foreign[ $entity . "\0" . $source_id ] = true;
				return false;
			}
			$excluded[ $entity . "\0" . $source_id ] = array(
				'entity'    => $entity,
				'source_id' => $source_id,
				'reason'    => $reason,
			);
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
		$seen     = array();
		foreach ( $courses as $course_id ) {
			CourseException::unless_allowed( PolicyService::instance()->decide( $actor, PolicyService::EDIT_COURSE, array( $course_id ) ), 'Not allowed to export this course.' );
			$course = get_post( $course_id );
			if ( ! $course instanceof \WP_Post ) {
				throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
			}
			$modules = array();
			foreach ( CourseStructure::current()->modules( $course_id ) as $link ) {
				$module = get_post( $link['module_id'] );
				if ( ! $module instanceof \WP_Post ) {
					continue;
				}
				$module_id = self::source_id( 'module', $link['module_id'], $identity->ensure( $module ) );
				$modules[] = array(
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
					$out['lessons'][] = self::sealed(
						array(
							'source_id'     => $lesson_id,
							'title'         => $lesson->post_title,
							'body_html'     => $lesson->post_content,
							'activity_type' => '' === $definition['type'] ? 'lesson' : $definition['type'],
							'settings'      => (object) $definition['settings'],
						) + self::rights( (int) $lesson->ID )
					);
				}
				$pool = array();
				foreach ( $repo->pool( $link['module_id'], true ) as $question_id ) {
					$uid     = (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question_id ) );
					$qsource = self::source_id( 'question', $question_id, $uid );
					if ( $skip( 'questions', $qsource, self::excluded_reason( $rights, $gate->question( $question_id ), $question_id, array( 'question', 'flashcard' ) ) ) ) {
						continue;
					}
					$pool[] = array(
						'question' => $qsource,
						'sort'     => count( $pool ) + 1,
					);
					if ( isset( $seen[ 'q' . $question_id ] ) ) {
						continue;
					}
					$seen[ 'q' . $question_id ] = true;
					$current                    = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question_id ) );
					$revision                   = (array) $repo->revision( $current );
					$out['questions'][]         = self::sealed(
						array(
							'source_id' => $qsource,
							'revision'  => array(
								'question_type' => (string) $revision['question_type'],
								'text_html'     => (string) $revision['question_text'],
								'settings'      => (object) ( $revision['settings'] ?? array() ),
							) + self::explanations( $revision ),
							'answers'   => array_map(
								static fn( array $a ): array => array(
									'key'       => (string) $a['key'],
									'text_html' => (string) $a['text'],
									'correct'   => ! empty( $a['correct'] ),
								),
								array_values( (array) $revision['answers'] )
							),
							'tags'      => array_values( (array) $revision['tags'] ),
						)
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
			$course_source    = self::source_id( 'course', $course_id, $identity->ensure( $course ) );
			$ids[]            = $course_source;
			$out['courses'][] = self::sealed(
				array(
					'source_id'        => $course_source,
					'title'            => $course->post_title,
					'slug'             => $course->post_name,
					'summary'          => $course->post_excerpt,
					'description_html' => $course->post_content,
					'modules'          => $modules,
				) + self::rights( $course_id )
			);
		}
		ksort( $excluded, SORT_STRING );
		return array(
			'records' => $out,
			'export'  => array(
				'course_ids'       => $ids,
				'included_foreign' => $include_foreign,
				'excluded'         => array_values( $excluded ),
			),
			'foreign' => count( $foreign ),
		);
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
	 * @param array<string, list<array<string, mixed>>>               $records Records per entity.
	 * @param string                                                  $system  `source.system`.
	 * @param string                                                  $title   Title.
	 * @param string|null                                             $file    Target path (default: packages folder).
	 * @param int                                                     $per_file Records per data file (R0-10 §1.1: ≤ 5 000).
	 * @param array<string, string|array{data: string, mime: string}> $media Media files `media/…` → content (MIME from the extension) or content with declared MIME.
	 * @param bool                                                    $own_export Export of this site's courses: the manifest carries `source.exported_from_site` (installation UUID, D8).
	 * @param array<string, mixed>|null                               $export Manifest block `export` from {@see self::collect()} (R0-10 §1.2); null leaves it out.
	 * @return string Path.
	 * @throws CourseException When the archive cannot be written.
	 */
	public static function write( array $records, string $system, string $title, ?string $file = null, int $per_file = self::RECORDS_PER_FILE, array $media = array(), bool $own_export = false, ?array $export = null ): string {
		$files  = array();
		$mimes  = array();
		$counts = array();
		foreach ( $records as $entity => $list ) {
			$counts[ $entity ] = count( $list );
			foreach ( array_chunk( $list, max( 1, min( self::RECORDS_PER_FILE, $per_file ) ) ) as $i => $part ) {
				$files[ sprintf( 'data/%s/%06d.json', $entity, $i + 1 ) ] = (string) wp_json_encode( $part, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
		}
		foreach ( $media as $path => $entry ) {
			$ext            = strtolower( (string) pathinfo( (string) $path, PATHINFO_EXTENSION ) );
			$files[ $path ] = is_array( $entry ) ? $entry['data'] : $entry;
			$mimes[ $path ] = is_array( $entry ) ? $entry['mime'] : ( PackageValidator::MEDIA_TYPES[ $ext ] ?? 'application/octet-stream' );
		}
		$content  = PackageValidator::content_sha256( $files );
		$manifest = array(
			'format'              => 'cc-package',
			'schema_version'      => '1.0.0',
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
				'warnings'      => array(),
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
		foreach ( $files as $path => $data ) {
			$manifest['files'][] = array(
				'path'      => $path,
				'size'      => strlen( $data ),
				'mime_type' => $mimes[ $path ] ?? 'application/json',
				'sha256'    => hash( 'sha256', $data ),
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
		$zip->close();
		return $file;
	}

	/**
	 * Installation UUID of this site (R0-10 §2.2, R0-38 §3.3), created on
	 * first use. It identifies the site only, never a person.
	 *
	 * @return string
	 */
	public static function installation_uuid(): string {
		$uuid = get_option( RestoreCheck::INSTALLATION_UUID );
		if ( is_string( $uuid ) && 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid ) ) {
			return $uuid;
		}
		$uuid = strtolower( wp_generate_uuid4() );
		update_option( RestoreCheck::INSTALLATION_UUID, $uuid, false );
		return $uuid;
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
