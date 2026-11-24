<?php
/**
 * Identity-free structure hash of a course (R0-05 GM-137, GM-133).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Import\Canonical;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * `ccap_course_revision.structure_hash` freezes a published revision and
 * therefore contains post IDs, UIDs and WP revision IDs: two courses with
 * the same content never share it. This fingerprint compares content only
 * (a hand-built course against an imported one, an export against its
 * re-import): course texts, modules in order with `required`, published
 * lessons in order with their activity type and settings, and the content
 * hash of every published pool question (`ccap_question_revision.content_hash`,
 * already identity-free). Slugs, IDs, UIDs, authors, dates and status
 * are left out. Canonical JSON as in packages (R0-10 §1.4).
 */
final class StructureFingerprint {

	/**
	 * Fingerprint (`sha256:` + 64 hex).
	 *
	 * @param int $course_id Course.
	 * @return string
	 * @throws CourseException For unknown courses.
	 */
	public static function hash( int $course_id ): string {
		return 'sha256:' . hash( 'sha256', Canonical::encode( self::structure( $course_id ) ) );
	}

	/**
	 * The compared structure.
	 *
	 * @param int $course_id Course.
	 * @return array<string, mixed>
	 * @throws CourseException For unknown courses.
	 */
	public static function structure( int $course_id ): array {
		$course = get_post( $course_id );
		if ( ! $course instanceof \WP_Post || \CourseCaptain\Policy\Relations::COURSE_TYPE !== $course->post_type ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		$db        = Database::current();
		$structure = CourseStructure::current();
		$repo      = QuestionRepository::current();
		$modules   = array();
		foreach ( $structure->modules( $course_id ) as $link ) {
			$module = get_post( $link['module_id'] );
			if ( ! $module instanceof \WP_Post || 'publish' !== $module->post_status ) {
				continue;
			}
			$lessons = array();
			foreach ( $structure->lessons( $link['module_id'] ) as $lesson ) {
				if ( 'publish' !== $lesson->post_status ) {
					continue;
				}
				$definition = ActivityService::definition( (int) $lesson->ID );
				$lessons[]  = array(
					'title'    => $lesson->post_title,
					'body'     => $lesson->post_content,
					'type'     => '' === $definition['type'] ? 'lesson' : $definition['type'],
					'settings' => $definition['settings'],
				);
			}
			$pool = array();
			foreach ( $repo->pool( $link['module_id'], true ) as $question_id ) {
				$pool[] = (string) $db->scalar( $db->prepare( 'SELECT r.content_hash FROM `' . $db->table( 'ccap_question' ) . '` q JOIN `' . $db->table( 'ccap_question_revision' ) . '` r ON r.id = q.current_revision_id WHERE q.id = %d', $question_id ) );
			}
			$modules[] = array(
				'title'    => $module->post_title,
				'summary'  => $module->post_excerpt,
				'required' => (bool) $link['required'],
				'lessons'  => $lessons,
				'pool'     => $pool,
			);
		}
		return array(
			'title'       => $course->post_title,
			'summary'     => $course->post_excerpt,
			'description' => $course->post_content,
			'modules'     => $modules,
		);
	}
}
