<?php
/**
 * Course export as `cc-package` v1 (R0-10 §6.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
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
 */
final class PackageExporter {

	/**
	 * Build the package records (no file I/O).
	 *
	 * @param Actor $actor   Author.
	 * @param array $courses Courses.
	 * @phpstan-param list<int> $courses
	 * @return array<string, list<array<string, mixed>>>
	 * @throws CourseException When a course may not be edited.
	 */
	public static function records( Actor $actor, array $courses ): array {
		$db       = Database::current();
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
					$lesson_id        = self::source_id( 'lesson', (int) $lesson->ID, $identity->ensure( $lesson ) );
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
						)
					);
				}
				$pool = array();
				foreach ( $repo->pool( $link['module_id'] ) as $i => $question_id ) {
					$uid     = (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question_id ) );
					$qsource = self::source_id( 'question', $question_id, $uid );
					$pool[]  = array(
						'question' => $qsource,
						'sort'     => $i + 1,
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
							),
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
					)
				);
			}
			$out['courses'][] = self::sealed(
				array(
					'source_id'        => self::source_id( 'course', $course_id, $identity->ensure( $course ) ),
					'title'            => $course->post_title,
					'slug'             => $course->post_name,
					'summary'          => $course->post_excerpt,
					'description_html' => $course->post_content,
					'modules'          => $modules,
				)
			);
		}
		return $out;
	}

	/**
	 * Write a package from records.
	 *
	 * @param array<string, list<array<string, mixed>>> $records Records per entity.
	 * @param string                                    $system  `source.system`.
	 * @param string                                    $title   Title.
	 * @param string|null                               $file    Target path (default: packages folder).
	 * @return string Path.
	 * @throws CourseException When the archive cannot be written.
	 */
	public static function write( array $records, string $system, string $title, ?string $file = null ): string {
		$files  = array();
		$counts = array();
		foreach ( $records as $entity => $list ) {
			$counts[ $entity ] = count( $list );
			if ( array() !== $list ) {
				$files[ 'data/' . $entity . '/000001.json' ] = (string) wp_json_encode( $list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
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
		foreach ( $files as $path => $data ) {
			$manifest['files'][] = array(
				'path'      => $path,
				'size'      => strlen( $data ),
				'mime_type' => 'application/json',
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
