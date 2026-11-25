<?php
/**
 * Withheld media embedded in the lesson text get a placeholder (R0-10 §2.2;
 * open point "Bilder im Lektionstext" of SET-CC-rechte-auslieferung).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ProtectedFiles;
use CourseCaptain\Admin\PreviewScreen;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LessonView;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables.
 *
 * @group SET-CC-rechte-auslieferung
 */
final class RightsLessonImagesTest extends SchemaTestCase {

	private const FOREIGN = 'fixture-foreign';

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner with grant.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Published course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Required module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Course with one module and one pool question, learner with a grant.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$admin         = Actor::user( $this->admin );
		$this->course  = $this->post( 'ccap_course', 'Image rights course' );
		$this->module  = $this->post( 'ccap_module', 'Image rights module' );
		PolicyService::flush();
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module );
		$repo = QuestionRepository::current();
		$id   = $repo->create(
			$admin,
			array(
				'question_type' => QuestionTypes::SINGLE,
				'question_text' => 'Question ' . wp_generate_password( 6, false ),
				'answers'       => array(
					array(
						'key'     => 'A',
						'text'    => 'right',
						'correct' => true,
					),
					array(
						'key'  => 'B',
						'text' => 'wrong',
					),
				),
			)
		);
		$repo->publish( $admin, $id );
		$repo->add_to_pool( $admin, $this->module, $id );
		$db = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $this->learner,
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		// The author view of the lesson needs course access as well.
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $this->admin,
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
	}

	/**
	 * Reset the current user.
	 */
	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Type.
	 * @param string $title     Title.
	 * @param int    $parent_id Parent.
	 * @param string $content   Content.
	 * @return int
	 */
	private function post( string $type, string $title, int $parent_id = 0, string $content = 'About' ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Protected image of a lesson.
	 *
	 * @param int    $lesson Lesson.
	 * @param string $system Source system.
	 * @param string $status Rights status.
	 * @return string Media UID.
	 */
	private function image( int $lesson, string $system, string $status ): string {
		$db    = Database::current();
		$uid   = $db->uuid();
		$media = $db->insert(
			'ccap_media',
			array(
				'uid'           => $uid,
				'source_system' => $system,
				'source_id'     => 'local' === $system ? null : 'IMG-' . wp_generate_password( 6, false ),
				'package_path'  => 'media/image-' . $status . '.png',
				'media_type'    => 'image',
				'mime_type'     => 'image/png',
				'sha256'        => hash( 'sha256', wp_generate_password( 12, false ) ),
				'access'        => 'protected',
				'rights_status' => $status,
			),
			array( 'created_at', 'updated_at' )
		);
		$db->insert(
			'ccap_content_media',
			array(
				'object_type' => 'lesson',
				'object_id'   => $lesson,
				'media_id'    => $media,
				'role'        => 'attachment',
			)
		);
		return $uid;
	}

	/**
	 * Learners see "Media not available" in place of a withheld embedded
	 * image and link (no UID, no path), released images stay; editors see
	 * the reason; the lesson text and its activity stay.
	 */
	public function test_withheld_embedded_media_become_placeholder(): void {
		$admin  = Actor::user( $this->admin );
		$lesson = $this->post( 'ccap_lesson', 'Lesson with images', $this->module, 'placeholder' );
		$bad    = $this->image( $lesson, self::FOREIGN, 'unknown' );
		$gone   = $this->image( $lesson, self::FOREIGN, 'restricted' );
		$own    = $this->image( $lesson, 'local', 'own' );
		wp_update_post(
			array(
				'ID'           => $lesson,
				'post_content' => '<p>Lesson text before.</p>'
					. '<figure class="wp-block-image"><img src="' . esc_url( ProtectedFiles::url( $bad ) ) . '" alt="Foreign chart"/><figcaption>Chart caption</figcaption></figure>'
					. '<p><a href="' . esc_url( ProtectedFiles::url( $gone ) ) . '">Download the foreign sheet</a></p>'
					. '<p><img src="' . esc_url( ProtectedFiles::url( $own ) ) . '" alt="Own photo"/></p>'
					. '<p>Lesson text after.</p>',
			)
		);
		ActivityService::current()->define( $admin, $lesson, 'lesson', array() );
		CoursePublisher::current()->publish( $admin, $this->course );

		$learner = Actor::user( $this->learner );
		wp_set_current_user( $this->learner );
		$page = LessonView::render( $learner, $this->course, $lesson );
		self::assertSame( 200, $page['status'] );
		self::assertStringContainsString( 'Lesson text before.', $page['body'] );
		self::assertStringContainsString( 'Lesson text after.', $page['body'] );
		self::assertStringContainsString( 'Chart caption', $page['body'] );
		self::assertStringContainsString( 'Media not available', $page['body'] );
		self::assertStringNotContainsString( $bad, $page['body'], 'no UID of the withheld image' );
		self::assertStringNotContainsString( $gone, $page['body'], 'no link to the withheld file' );
		self::assertStringNotContainsString( 'Foreign chart', $page['body'] );
		self::assertStringContainsString( 'Download the foreign sheet', $page['body'], 'the link text stays' );
		self::assertStringContainsString( $own, $page['body'], 'own media stay (E-18)' );
		self::assertStringNotContainsString( 'Not shown to learners', $page['body'] );
		self::assertSame( array(), A11yDom::fragment_problems( $page['body'] ) );

		wp_set_current_user( $this->admin );
		PolicyService::flush();
		$author = LessonView::render( $admin, $this->course, $lesson );
		self::assertStringContainsString( 'Not shown to learners: the rights of this imported content are unclear.', $author['body'] );
		self::assertStringContainsString( 'Not shown to learners: imported content marked as "must not be shown".', html_entity_decode( $author['body'], ENT_QUOTES ) );
		self::assertStringNotContainsString( $bad, $author['body'] );
		self::assertStringContainsString( $own, $author['body'] );

		// Preview as learner (owner decision 25.11.2026): the lesson text exactly as learners
		// see it, the reason only in the notice above the lesson.
		$preview = PreviewScreen::html( $this->course, $this->admin );
		self::assertStringContainsString( 'Lesson text before.', $preview );
		self::assertStringContainsString( 'Chart caption', $preview );
		self::assertSame( 1, substr_count( $preview, '<span class="ccap-withheld ccap-withheld-media">Media not available</span>' ), 'the withheld image becomes the learner placeholder' );
		self::assertStringContainsString( '<span class="ccap-withheld-link">Download the foreign sheet</span>', $preview, 'the link text stays, the link goes' );
		self::assertStringNotContainsString( $bad, $preview, 'no UID of the withheld image' );
		self::assertStringNotContainsString( $gone, $preview, 'no link to the withheld file' );
		self::assertStringNotContainsString( 'Foreign chart', $preview );
		self::assertStringContainsString( $own, $preview, 'own media stay (E-18)' );
		self::assertStringContainsString( 'Learners see &quot;Media not available&quot; in place of a medium of this lesson.', $preview, 'the editor notice stays' );
		self::assertSame( array(), A11yDom::fragment_problems( $preview ) );

		// Releasing the image (licence) brings it back for learners at the next request.
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_media' ) . "` SET rights_status = 'licensed' WHERE uid = %s", $bad ) );
		wp_set_current_user( $this->learner );
		$page = LessonView::render( $learner, $this->course, $lesson );
		self::assertStringContainsString( $bad, $page['body'] );
		self::assertStringContainsString( 'Foreign chart', $page['body'] );
		self::assertStringNotContainsString( $gone, $page['body'] );
	}
}
