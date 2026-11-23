<?php
/**
 * Course FAQ for learners (R0-05 GM-190; R0-19 R-38; R0-07 RG-13; E-16).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Course;

use CourseCaptain\Access\ContentProtection;
use CourseCaptain\Cache\CacheStore;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * FAQ entries are `ccap_faq` posts below a course (`post_parent`). The
 * list contains only published entries (never drafts) and is readable for
 * people with access to at least one module of the course (R0-08: objects
 * without module reference). The raw list is cached per course generation
 * (K1g) and every change of an FAQ entry bumps that generation, so edits are
 * visible at once. The answer is rendered per request, so protected
 * sections (`[ccap_protected]`) show the notice to people without access.
 */
final class FaqService {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'clean_post_cache', array( self::class, 'on_change' ), 10, 2 );
	}

	/**
	 * A post changed: bump the course generation of an FAQ entry.
	 *
	 * @param mixed $post_id Post ID.
	 * @param mixed $post    Post.
	 * @return void
	 */
	public static function on_change( $post_id, $post ): void {
		unset( $post_id );
		if ( $post instanceof \WP_Post && Relations::FAQ_TYPE === $post->post_type && (int) $post->post_parent > 0 ) {
			CacheStore::bump_course( (int) $post->post_parent );
		}
	}

	/**
	 * FAQ of a course for a person (REST `GET /courses/{course}/faqs`).
	 *
	 * @param Actor $actor  Person.
	 * @param int   $course Course.
	 * @return list<array{uid: string, question: string, answer_html: string, category: null}>
	 * @throws CourseException 401, 404, 403 `ccap_content_locked`.
	 */
	public static function for_course( Actor $actor, int $course ): array {
		if ( $actor->user_id <= 0 ) {
			throw new CourseException( 'ccap_auth_required', 'Please log in.', 401 );
		}
		$post = get_post( $course );
		if ( ! $post instanceof \WP_Post || Relations::COURSE_TYPE !== $post->post_type || Relations::STATUS_PUBLISHED !== Relations::course_status( $post ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		if ( ! user_can( $actor->user_id, 'edit_post', $course ) && ContentProtection::course_locked( $course, $actor->user_id ) ) {
			throw new CourseException( 'ccap_content_locked', 'No access to this course.', 403 );
		}
		$out = array();
		foreach ( self::entries( $course ) as $entry ) {
			$out[] = array(
				'uid'         => $entry['uid'],
				'question'    => $entry['question'],
				'answer_html' => wp_kses_post( do_shortcode( do_blocks( $entry['answer'] ) ) ),
				'category'    => null,
			);
		}
		return $out;
	}

	/**
	 * Published entries of a course (cached per course generation).
	 *
	 * @param int $course Course.
	 * @return list<array{uid: string, question: string, answer: string}>
	 */
	public static function entries( int $course ): array {
		$entries = CacheStore::shared(
			'faq:' . $course,
			static function () use ( $course ): array {
				$posts    = get_posts(
					array(
						'post_type'        => Relations::FAQ_TYPE,
						'post_parent'      => $course,
						'post_status'      => 'publish',
						'posts_per_page'   => 100,
						'orderby'          => array(
							'menu_order' => 'ASC',
							'ID'         => 'ASC',
						),
						'suppress_filters' => true,
					)
				);
				$identity = new ObjectIdentity( Database::current() );
				$list     = array();
				foreach ( $posts as $faq ) {
					$list[] = array(
						'uid'      => (string) ( $identity->uid_of( 'faq', (int) $faq->ID ) ?? $identity->ensure( $faq ) ),
						'question' => (string) $faq->post_title,
						'answer'   => (string) $faq->post_content,
					);
				}
				return $list;
			},
			$course
		);
		/**
		 * Cached list.
		 *
		 * @var list<array{uid: string, question: string, answer: string}> $list
		 */
		$list = is_array( $entries ) ? array_values( $entries ) : array();
		return $list;
	}
}
