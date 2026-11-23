<?php
/**
 * UI-F8 part 2: question tags and bulk edit, access management in the
 * learners screen (suspend/resume/extend, access rules, progress reset),
 * "decorative" images, quiz lessons and exam mistakes in the learning
 * area, transcript fields and VAL-142.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\AccessRules;
use CourseCaptain\Access\ContentGate;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Admin\AccessRulesPanel;
use CourseCaptain\Admin\AdminUi;
use CourseCaptain\Admin\LearnersScreen;
use CourseCaptain\Admin\LessonMediaFields;
use CourseCaptain\Admin\QuestionsScreen;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Learner\LessonView;
use CourseCaptain\Learner\TrainerView;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Publish\CourseValidator;
use CourseCaptain\Publish\DecorativeImages;
use CourseCaptain\Question\QuestionBulk;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables; every screen through its testable `process()` / `html()`.
 *
 * @group UI-F8
 */
final class AutorUi2Test extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Subscriber without rights.
	 *
	 * @var int
	 */
	private int $subscriber;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Modules M1, M2.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Questions in M1 (q1 single, q2 single).
	 *
	 * @var list<int>
	 */
	private array $questions = array();

	/**
	 * Fixture: course with two modules, a text lesson in M1, two published single choice questions in M1.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin      = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner    = (int) self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'display_name' => 'Quinn Learner',
			)
		);
		$this->subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course     = $this->post( 'ccap_course', 'Course F8' );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		foreach ( array( 'M1', 'M2' ) as $i => $title ) {
			$module          = $this->post( 'ccap_module', $title );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module, $i + 1 );
		}
		$lesson = $this->post( 'ccap_lesson', 'Text lesson', $this->modules[0] );
		ActivityService::current()->define( $admin, $lesson, 'lesson', array(), array( 'type' => 'manual_confirm' ) );
		$lesson2 = $this->post( 'ccap_lesson', 'Text lesson 2', $this->modules[1] );
		ActivityService::current()->define( $admin, $lesson2, 'lesson', array(), array( 'type' => 'manual_confirm' ) );
		foreach ( array( 'First question', 'Second question' ) as $text ) {
			$this->questions[] = $this->question(
				$text,
				array(
					array(
						'type'  => 'topic',
						'key'   => 'knots',
						'label' => 'Knots',
					),
				)
			);
		}
		wp_set_current_user( $this->admin );
	}

	/**
	 * Cleanup.
	 */
	public function tear_down(): void {
		ContentGate::set_now( null );
		\CourseCaptain\Learning\SmartTrainer::set_now( null );
		remove_all_filters( 'pre_option_' . \CourseCaptain\Settings\Settings::OPTION_PREFIX . 'display' );
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
	private function post( string $type, string $title, int $parent_id = 0, string $content = 'Text' ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Published single choice question in M1 (A correct).
	 *
	 * @param string                            $text Text.
	 * @param array<int, array<string, string>> $tags Tags.
	 * @return int
	 */
	private function question( string $text, array $tags = array() ): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$id    = $repo->create(
			$admin,
			array(
				'question_type' => QuestionTypes::SINGLE,
				'question_text' => $text,
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
				'tags'          => $tags,
			)
		);
		$repo->publish( $admin, $id );
		$repo->add_to_pool( $admin, $this->modules[0], $id );
		return $id;
	}

	/**
	 * POST of an admin form with a valid nonce.
	 *
	 * @param string               $action    admin-post action.
	 * @param string               $op        Operation.
	 * @param int                  $object_id Object.
	 * @param array<string, mixed> $data      Fields.
	 * @param string               $nonce_op  Nonce group of a multi-button form ('' = op).
	 * @param int                  $user      User the nonce is made for.
	 * @return array<string, mixed>
	 */
	private function form( string $action, string $op, int $object_id, array $data, string $nonce_op = '', int $user = 0 ): array {
		wp_set_current_user( 0 === $user ? $this->admin : $user );
		$group = '' === $nonce_op ? $op : $nonce_op;
		$post  = array(
			'op'       => $op,
			'object'   => $object_id,
			'_wpnonce' => wp_create_nonce( AdminUi::nonce_action( $action, $group, $object_id ) ),
		) + $data;
		if ( '' !== $nonce_op ) {
			$post['nonce_op'] = $nonce_op;
		}
		return $post;
	}

	/**
	 * Bulk run on the question screen.
	 *
	 * @param array<string, mixed> $data Fields.
	 * @param int                  $user User.
	 * @return array<string, mixed>
	 */
	private function bulk( array $data, int $user = 0 ): array {
		$user = 0 === $user ? $this->admin : $user;
		$out  = QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, 'bulk', 0, $data, 'bulk', $user ), $user );
		wp_set_current_user( $this->admin );
		PolicyService::flush();
		return $out;
	}

	/**
	 * Newest revision of a question.
	 *
	 * @param int $question Question.
	 * @return array<string, mixed>
	 */
	private static function latest( int $question ): array {
		return (array) QuestionRepository::current()->revision( QuestionsScreen::latest_revision( $question ) );
	}

	/**
	 * Tag keys of the newest revision.
	 *
	 * @param int $question Question.
	 * @return list<string>
	 */
	private static function tags( int $question ): array {
		$keys = array_map( static fn( array $t ): string => $t['type'] . ':' . $t['key'], (array) self::latest( $question )['tags'] );
		sort( $keys );
		return $keys;
	}

	/**
	 * Number of audit rows of an action.
	 *
	 * @param string $action Action.
	 * @return int
	 */
	private static function audits( string $action ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s', $action ) );
	}

	/**
	 * Active manual grant of the learner.
	 *
	 * @param string|null $valid_until End.
	 * @return int
	 */
	private function grant( ?string $valid_until = null ): int {
		$db = Database::current();
		$id = $db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $this->learner,
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
				'valid_until' => $valid_until,
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		return $id;
	}

	/**
	 * Course UID.
	 *
	 * @return string
	 */
	private function uid(): string {
		return ( new ObjectIdentity( Database::current() ) )->ensure( get_post( $this->course ) );
	}

	/**
	 * Location's `ccap_status`.
	 *
	 * @param string $location URL.
	 * @return string
	 */
	private static function status_of( string $location ): string {
		parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
		return (string) ( $query['ccap_status'] ?? '' );
	}

	/**
	 * Tag parsing: known kinds by prefix, everything else is a topic.
	 */
	public function test_tag_parsing(): void {
		self::assertSame(
			array(
				'type'  => 'competency',
				'key'   => 'navigation',
				'label' => 'Navigation',
			),
			QuestionBulk::parse_tag( 'Competency: Navigation' )
		);
		self::assertSame( 'topic', QuestionBulk::parse_tag( 'Rules: part 1' )['type'] ?? '', 'unknown prefix stays part of a topic label' );
		self::assertNull( QuestionBulk::parse_tag( '  ' ) );
		$tags = QuestionBulk::parse_tags( "Knots\nscope: Inland, knots" );
		self::assertSame( array( 'topic:knots', 'scope:inland' ), array_map( static fn( array $t ): string => $t['type'] . ':' . $t['key'], $tags ), 'duplicates merged' );
		self::assertSame( "Knots\nscope: Inland", QuestionBulk::format_tags( $tags ) );
	}

	/**
	 * Editor: tags field saves tags; a save without the field keeps them;
	 * a tag change of a published question is a new revision.
	 */
	public function test_editor_tags_and_revisions(): void {
		$q    = $this->questions[0];
		$old  = QuestionsScreen::latest_revision( $q );
		$page = QuestionsScreen::html( array( 'action' => 'edit', 'question' => $q ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertStringContainsString( 'name="tags"', $page );
		self::assertStringContainsString( '>Knots</textarea>', $page );
		$base = array(
			'question_type'  => QuestionTypes::SINGLE,
			'question_text'  => 'First question',
			'answers'        => array(
				array( 'text' => 'right' ),
				array( 'text' => 'wrong' ),
			),
			'correct_single' => '0',
			'change_class'   => 'editorial',
		);
		$out  = QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, 'save_publish', $q, $base + array( 'tags' => "Knots\ncompetency: Seamanship" ), 'editor' ), $this->admin );
		self::assertContains( $out['result'], array( 'published', 'ok' ), (string) wp_json_encode( $out ) );
		self::assertSame( array( 'competency:seamanship', 'topic:knots' ), self::tags( $q ) );
		self::assertNotSame( $old, QuestionsScreen::latest_revision( $q ), 'published revision never changed in place' );
		QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, 'save', $q, array( 'question_text' => 'First question!' ) + $base, 'editor' ), $this->admin );
		self::assertSame( array( 'competency:seamanship', 'topic:knots' ), self::tags( $q ), 'save without the tags field keeps the tags' );
	}

	/**
	 * Bulk tag add/remove (new revisions, published at once unless a draft
	 * is pending), filter by tag, move to pool, deprecate with confirmation,
	 * rights, audit, DOM.
	 */
	public function test_bulk_edit_and_tag_filter(): void {
		list( $q1, $q2 ) = $this->questions;
		$draft           = QuestionRepository::current()->create(
			Actor::user( $this->admin ),
			array(
				'question_type' => QuestionTypes::SINGLE,
				'question_text' => 'Draft question',
				'answers'       => array(
					array(
						'key'     => 'A',
						'text'    => 'x',
						'correct' => true,
					),
					array(
						'key'  => 'B',
						'text' => 'y',
					),
				),
			)
		);
		$list            = QuestionsScreen::html( array(), $this->admin );
		self::assertSame( array(), A11yDom::fragment_problems( $list ) );
		self::assertStringContainsString( 'name="questions[]"', $list );
		self::assertStringContainsString( 'Select: First question', $list );
		self::assertStringContainsString( '<option value="topic:knots"', $list, 'tag filter offers tags in use' );

		$before = QuestionsScreen::latest_revision( $q1 );
		self::assertSame( 'ccap_nothing_selected', $this->bulk( array( 'bulk_action' => 'tag_add', 'bulk_tag' => 'Ropes' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_invalid_param', $this->bulk( array( 'bulk_action' => 'tag_add', 'bulk_tag' => '', 'questions' => array( $q1 ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_forbidden', $this->bulk( array( 'bulk_action' => 'tag_add', 'bulk_tag' => 'Ropes', 'questions' => array( $q1 ) ), $this->subscriber )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$out = $this->bulk(
			array(
				'bulk_action'  => 'tag_add',
				'bulk_tag'     => 'smart_category: Ropes',
				'bulk_publish' => '1',
				'questions'    => array( $q1, $q2, $draft ),
			)
		);
		self::assertSame( 'ok', $out['result'] );
		self::assertSame( '3 questions changed.', $out['messages'][0] );
		self::assertStringContainsString( '2 changed questions were published', implode( ' ', $out['messages'] ) );
		self::assertStringContainsString( '1 changed question is a draft', implode( ' ', $out['messages'] ) );
		self::assertSame( array( 'smart_category:ropes', 'topic:knots' ), self::tags( $q1 ) );
		$db = Database::current();
		self::assertNotSame( $before, QuestionsScreen::latest_revision( $q1 ), 'new revision' );
		self::assertSame( QuestionsScreen::latest_revision( $q1 ), (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q1 ) ), 'published at once' );
		self::assertSame( 'deprecated', $db->scalar( $db->prepare( 'SELECT editorial_status FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE id = %d', $before ) ), 'old revision replaced, not changed' );
		self::assertSame( 1, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE question_id = %d', $draft ) ), 'draft updated in place' );
		self::assertSame( 3, self::audits( 'question_tags_changed' ) );
		// Again: nothing to change.
		self::assertStringContainsString( '3 questions were already in this state.', implode( ' ', $this->bulk( array( 'bulk_action' => 'tag_add', 'bulk_tag' => 'smart_category: Ropes', 'questions' => array( $q1, $q2, $draft ) ) )['messages'] ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.

		// Pending draft: the tag change joins the draft; nothing else is published.
		QuestionRepository::current()->save(
			Actor::user( $this->admin ),
			$q2,
			array(
				'question_type' => QuestionTypes::SINGLE,
				'question_text' => 'Second question, reworded',
				'answers'       => self::latest( $q2 )['answers'],
				'tags'          => self::latest( $q2 )['tags'],
			)
		);
		$current = (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q2 ) );
		$this->bulk(
			array(
				'bulk_action'  => 'tag_remove',
				'bulk_tag'     => 'Knots',
				'bulk_publish' => '1',
				'questions'    => array( $q1, $q2 ),
			)
		);
		self::assertSame( array( 'smart_category:ropes' ), self::tags( $q2 ) );
		self::assertSame( $current, (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $q2 ) ), 'pending draft not published' );
		self::assertSame( 'Second question, reworded', self::latest( $q2 )['question_text'] );

		// Filter by tag.
		$filtered = QuestionsScreen::html( array( 'tag' => 'smart_category:ropes' ), $this->admin );
		self::assertStringContainsString( '3 questions found.', $filtered );
		self::assertStringContainsString( '0 questions found.', QuestionsScreen::html( array( 'tag' => 'topic:knots' ), $this->admin ), 'Knots removed everywhere' );
		self::assertStringContainsString( 'Practice category: Ropes', $filtered, 'tags column' );

		// Move to M2: removed from M1.
		self::assertSame( 'ok', $this->bulk( array( 'bulk_action' => 'pool_move', 'bulk_module' => $this->modules[1], 'questions' => array( $q1 ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( array( $this->modules[1] ), array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT module_id FROM `' . $db->table( 'ccap_module_question' ) . '` WHERE question_id = %d', $q1 ) ) ) );
		self::assertSame( 'ok', $this->bulk( array( 'bulk_action' => 'pool_add', 'bulk_module' => $this->modules[0], 'questions' => array( $q1 ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 2, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_module_question' ) . '` WHERE question_id = %d', $q1 ) ) );
		self::assertSame( 'ccap_invalid_param', $this->bulk( array( 'bulk_action' => 'pool_add', 'bulk_module' => 0, 'questions' => array( $q1 ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.

		// Deprecate = archive, with confirmation.
		self::assertSame( 'ccap_confirmation_required', $this->bulk( array( 'bulk_action' => 'deprecate', 'questions' => array( $draft ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', $this->bulk( array( 'bulk_action' => 'deprecate', 'bulk_confirm' => '1', 'questions' => array( $draft ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'archived', $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $draft ) ) );
		self::assertSame( 1, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $draft ) ), 'never hard deleted' );
		self::assertSame( 'ccap_invalid_nonce', QuestionsScreen::process( array( 'op' => 'bulk', 'nonce_op' => 'bulk', 'object' => 0, '_wpnonce' => 'x' ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
	}

	/**
	 * Tag-only revisions keep rights and validity of the revision.
	 */
	public function test_tag_change_keeps_revision_columns(): void {
		$db = Database::current();
		$q  = $this->questions[0];
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_question_revision' ) . "` SET image_required = 1, rights_status = 'licensed', rights_owner = 'Owner', valid_to = '2099-12-31' WHERE id = %d", QuestionsScreen::latest_revision( $q ) ) );
		wp_cache_flush();
		$state = QuestionBulk::current()->change_tags( Actor::user( $this->admin ), $q, array( QuestionBulk::parse_tag( 'scope: Coast' ) ), array(), true );
		self::assertSame( 'published', $state );
		$row = $db->row( $db->prepare( 'SELECT image_required, rights_status, rights_owner, valid_to FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE id = %d', QuestionsScreen::latest_revision( $q ) ) );
		self::assertSame( array( '1', 'licensed', 'Owner', '2099-12-31' ), array_values( (array) $row ) );
	}

	/**
	 * Suspend, resume and extend an access with reason and audit (GrantService).
	 */
	public function test_suspend_resume_extend(): void {
		$grant = $this->grant( gmdate( 'Y-m-d H:i:s', time() + 10 * DAY_IN_SECONDS ) );
		$db    = Database::current();
		$list  = LearnersScreen::html( array( 'course' => $this->course ), $this->admin );
		self::assertStringContainsString( 'Suspend, resume or extend the access of Quinn Learner', $list );
		self::assertStringContainsString( 'view=rules', $list );
		$detail = LearnersScreen::html( array( 'course' => $this->course, 'grant' => $grant ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( array(), A11yDom::fragment_problems( $detail ) );
		self::assertStringContainsString( 'Suspend access', $detail );
		self::assertStringContainsString( 'Extend access', $detail );

		$base = array( 'course' => $this->course );
		self::assertSame( 'ccap_reason_required', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'suspend', $grant, $base ), $this->admin )['result'] );
		self::assertSame( 'ccap_not_found', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'suspend', $grant, $base + array( 'reason' => 'x' ), '', $this->subscriber ), $this->subscriber )['result'], 'no right: existence not revealed' );
		self::assertSame( 'ccap_not_found', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'suspend', $grant, array( 'course' => $this->modules[0], 'reason' => 'x' ) ), $this->admin )['result'], 'grant of another course' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'suspend', $grant, $base + array( 'reason' => 'Unpaid invoice' ) ), $this->admin )['result'] );
		self::assertSame( array( 'suspended', 'suspended' ), array_values( (array) $db->row( $db->prepare( 'SELECT status, manual_override FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $grant ) ) ) );
		self::assertSame( 1, self::audits( 'grant.suspended_manually' ) );
		self::assertStringContainsString( 'Resume access', LearnersScreen::html( array( 'course' => $this->course, 'grant' => $grant ), $this->admin ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_conflict', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'extend', $grant, $base + array( 'reason' => 'x', 'valid_until' => gmdate( 'Y-m-d', time() + 40 * DAY_IN_SECONDS ) ) ), $this->admin )['result'], 'suspended grants are not extended' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'resume', $grant, $base + array( 'reason' => 'Paid' ) ), $this->admin )['result'] );
		self::assertSame( 'active', $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $grant ) ) );
		self::assertSame( 1, self::audits( 'grant.resumed_manually' ) );

		self::assertSame( 'ccap_date_invalid', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'extend', $grant, $base + array( 'reason' => 'x', 'valid_until' => '2000-01-01' ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$until = gmdate( 'Y-m-d', time() + 40 * DAY_IN_SECONDS );
		self::assertSame( 'ok', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'extend', $grant, $base + array( 'reason' => 'Goodwill', 'valid_until' => $until ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( $until . ' 23:59:59', $db->scalar( $db->prepare( 'SELECT valid_until FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $grant ) ) );
		self::assertSame( 1, self::audits( 'grant.extended' ) );
		self::assertSame( 'Goodwill', $db->scalar( "SELECT reason FROM `{$db->table( 'ccap_audit_log' )}` WHERE action = 'grant.extended'" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
	}

	/**
	 * Access rules: free access, drip, partial access, removal; read-only without the right.
	 */
	public function test_access_rules_panel(): void {
		$page = LearnersScreen::html( array( 'course' => $this->course, 'view' => 'rules' ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( array(), A11yDom::fragment_problems( $page ) );
		self::assertStringContainsString( 'No rules', $page );
		self::assertStringContainsString( 'Add free access', $page );
		$run = fn( string $op, array $data, int $user = 0 ): array => AccessRulesPanel::process( $this->form( AccessRulesPanel::ACTION, $op, $this->course, $data, '', 0 === $user ? $this->admin : $user ), 0 === $user ? $this->admin : $user );

		self::assertSame( 'ccap_forbidden', $run( 'drip_set', array( 'module' => $this->modules[1], 'days' => '7' ), $this->subscriber )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_invalid_param', $run( 'drip_set', array( 'module' => $this->modules[1], 'days' => 'x' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_invalid_param', $run( 'drip_set', array( 'module' => 999999, 'days' => '7' ) )['result'], 'module of another course' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', $run( 'drip_set', array( 'module' => $this->modules[1], 'days' => '7' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', $run( 'drip_set', array( 'module' => $this->modules[1], 'days' => '14' ) )['result'], 'one drip rule per module' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', $run( 'free_add', array( 'module' => 0, 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_invalid_param', $run( 'free_add', array( 'module' => 0, 'valid_from' => '2026-12-31', 'valid_until' => '2026-01-01' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', $run( 'limits_set', array( 'module' => $this->modules[0], 'limits' => array( 'first_questions' => '1', 'first_lessons' => '' ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$rules = AccessRules::current()->rules( $this->course );
		$kinds = array_map( static fn( array $r ): string => $r['access_type'] . ':' . $r['module_id'] . ':' . (string) $r['limit_value'], $rules );
		sort( $kinds );
		self::assertSame( array( 'drip:' . $this->modules[1] . ':14', 'free:0:', 'free_limited:' . $this->modules[0] . ':1' ), $kinds );
		self::assertSame( 4, self::audits( 'access_rule_changed' ) );

		// The gate reads the rules at once: M2 is locked by drip for a new learner.
		$this->grant();
		$gate = ContentGate::current()->module( Actor::user( $this->learner ), $this->course, $this->modules[1] );
		self::assertSame( 'drip', $gate->reason );

		$page = LearnersScreen::html( array( 'course' => $this->course, 'view' => 'rules' ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertStringContainsString( 'opens 14 days after the start', $page );
		self::assertStringContainsString( 'partial access: questions', $page );
		self::assertStringContainsString( '2026-01-01 00:00:00 to 2026-12-31 23:59:59', $page );
		$drip = 0;
		foreach ( $rules as $rule ) {
			if ( 'drip' === $rule['access_type'] ) {
				$drip = $rule['id'];
			}
		}
		self::assertSame( 'ccap_confirmation_required', $run( 'rule_delete', array( 'rule' => $drip ) )['result'] );
		self::assertSame( 'ok', $run( 'rule_delete', array( 'rule' => $drip, 'confirm' => '1' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertCount( 2, AccessRules::current()->rules( $this->course ) );
		$editor = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		self::assertStringContainsString( 'not allowed', LearnersScreen::html( array( 'course' => $this->course, 'view' => 'rules' ), $editor ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
	}

	/**
	 * Progress reset: reason, typed word, journal and audit; question states kept unless chosen.
	 */
	public function test_progress_reset(): void {
		$this->grant();
		$db     = Database::current();
		$lesson = (int) CourseStructure::current()->lessons( $this->modules[0] )[0]->ID;
		ActivityProgress::current()->interact( Actor::user( $this->learner ), $this->course, $lesson, 'confirm' );
		$db->insert(
			'ccap_question_progress',
			array(
				'user_id'     => $this->learner,
				'question_id' => $this->questions[0],
				'attempts'    => 1,
			)
		);
		$detail = LearnersScreen::html( array( 'course' => $this->course, 'learner' => $this->learner ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( array(), A11yDom::fragment_problems( $detail ) );
		self::assertStringContainsString( 'type ZURÜCKSETZEN or RESET', $detail );
		$base = array( 'course' => $this->course );
		self::assertSame( 'ccap_reason_required', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'reset', $this->learner, $base + array( 'confirm_word' => 'RESET' ) ), $this->admin )['result'] );
		self::assertSame( 'ccap_confirm_word', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'reset', $this->learner, $base + array( 'reason' => 'Retake', 'confirm_word' => 'yes' ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_not_found', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'reset', $this->learner, $base + array( 'reason' => 'Retake', 'confirm_word' => 'RESET' ), '', $this->subscriber ), $this->subscriber )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$out = LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'reset', $this->learner, $base + array( 'reason' => 'Retake', 'confirm_word' => 'zurücksetzen' ) ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', $out['result'] );
		self::assertGreaterThan( 0, $out['count'] );
		self::assertSame( 0, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_activity_progress' ) . '` WHERE user_id = %d', $this->learner ) ) );
		self::assertSame( 1, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_question_progress' ) . '` WHERE user_id = %d', $this->learner ) ), 'question learning states stay (R0-23 §3.3)' );
		self::assertSame( 1, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_reset_journal' ) . '` WHERE user_id = %d', $this->learner ) ), 'nothing deleted silently: journal' );
		self::assertSame( 1, self::audits( 'progress_reset' ) );
		self::assertSame( 'ok', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'reset', $this->learner, $base + array( 'reason' => 'Full', 'confirm_word' => 'RESET', 'questions' => '1' ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 0, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_question_progress' ) . '` WHERE user_id = %d', $this->learner ) ) );
		self::assertStringContainsString( 'moved to the reset journal', LearnersScreen::html( array( 'course' => $this->course, 'learner' => $this->learner, 'ccap_result' => 'ok', 'ccap_count' => 2 ), $this->admin ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
	}

	/**
	 * Decorative switch: block attribute marks empty alt as decorative for VAL-140; render adds the marker.
	 */
	public function test_decorative_images(): void {
		$plain      = '<!-- wp:image {"id":5} --><figure class="wp-block-image"><img src="a.png" alt=""/></figure><!-- /wp:image -->';
		$decorative = '<!-- wp:image {"id":5,"ccapDecorative":true} --><figure class="wp-block-image"><img src="a.png" alt=""/></figure><!-- /wp:image -->';
		$described  = '<!-- wp:image {"id":5,"ccapDecorative":true} --><figure class="wp-block-image"><img src="a.png" alt="A chart"/></figure><!-- /wp:image -->';
		self::assertSame( 1, CourseValidator::images_without_alt( $plain )['missing'], 'empty alt from the block editor is missing (E-349)' );
		self::assertSame( 0, CourseValidator::images_without_alt( $decorative )['missing'] );
		self::assertSame( 1, CourseValidator::images_without_alt( $decorative )['undescribed'], 'still not enough for VAL-141' );
		self::assertSame( 0, CourseValidator::images_without_alt( $described )['undescribed'] );
		self::assertSame( 1, CourseValidator::images_without_alt( $plain . $decorative )['missing'], 'only the marked block' );
		self::assertStringContainsString( 'data-ccap-decorative="1"', DecorativeImages::mark( $decorative ) );

		$rendered = DecorativeImages::render( '<figure><img src="a.png"></figure>', array( 'attrs' => array( 'ccapDecorative' => true ) ) );
		self::assertStringContainsString( 'alt=""', $rendered );
		self::assertStringContainsString( 'data-ccap-decorative="1"', $rendered );
		self::assertSame( '<figure><img src="a.png" alt="x"></figure>', DecorativeImages::render( '<figure><img src="a.png" alt="x"></figure>', array( 'attrs' => array( 'ccapDecorative' => true ) ) ) );
		$args = DecorativeImages::block_args( array( 'attributes' => array() ), 'core/image' );
		self::assertSame( 'boolean', $args['attributes']['ccapDecorative']['type'] );

		// Course check: a lesson with a decorative image block has no VAL-140.
		$lesson = $this->post( 'ccap_lesson', 'Pictures', $this->modules[1], $decorative . '<p>Text</p>' );
		ActivityService::current()->define( Actor::user( $this->admin ), $lesson, 'lesson', array() );
		$report = CourseValidator::validate( $this->course );
		$found  = array_filter( $report['issues'], static fn( array $i ): bool => 'VAL-140' === $i['code'] && $lesson === $i['object']['id'] );
		self::assertSame( array(), array_values( $found ) );
		wp_update_post(
			array(
				'ID'           => $lesson,
				'post_content' => $plain,
			)
		);
		$report = CourseValidator::validate( $this->course );
		self::assertNotEmpty( array_filter( $report['issues'], static fn( array $i ): bool => 'VAL-140' === $i['code'] && $lesson === $i['object']['id'] ) );
	}

	/**
	 * Transcript fields and VAL-142 (captions notice, blocker with the setting).
	 */
	public function test_transcript_fields_and_captions_rule(): void {
		$html = LessonMediaFields::html( 'video', array( 'transcript' => 'Hello' ) );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );
		self::assertStringContainsString( '>Hello</textarea>', $html );
		$merged = LessonMediaFields::merge(
			'video',
			array(
				'url'        => 'https://example.org/v.mp4',
				'transcript' => 'old',
			),
			array(
				'ccap_transcript'     => " Spoken text \n",
				'ccap_transcript_url' => '',
			)
		);
		self::assertSame(
			array(
				'url'        => 'https://example.org/v.mp4',
				'transcript' => 'Spoken text',
			),
			$merged
		);
		self::assertSame( array( 'a' => 1 ), LessonMediaFields::merge( 'lesson', array( 'a' => 1 ), array( 'ccap_transcript' => 'x' ) ), 'other types untouched' );

		$admin  = Actor::user( $this->admin );
		$lesson = $this->post( 'ccap_lesson', 'Video', $this->modules[1] );
		ActivityService::current()->define(
			$admin,
			$lesson,
			'video',
			array(
				'provider'   => 'url',
				'url'        => 'https://example.org/v.mp4',
				'transcript' => 'Text',
			)
		);
		$val142 = function () use ( $lesson ): array {
			$report = CourseValidator::validate( $this->course );
			return array_values( array_map( static fn( array $i ): string => $i['severity'], array_filter( $report['issues'], static fn( array $i ): bool => 'VAL-142' === $i['code'] && $lesson === $i['object']['id'] ) ) );
		};
		self::assertSame( array( 'notice' ), $val142() );
		$key = 'pre_option_' . \CourseCaptain\Settings\Settings::OPTION_PREFIX . 'display';
		add_filter( $key, static fn(): array => array( 'values' => array( 'require_captions' => true ) ) );
		self::assertSame( array( 'blocker' ), $val142() );
		ActivityService::current()->define(
			$admin,
			$lesson,
			'video',
			array(
				'provider'     => 'url',
				'url'          => 'https://example.org/v.mp4',
				'transcript'   => 'Text',
				'subtitles'    => 'vtt',
				'subtitle_url' => 'https://example.org/v.vtt',
			)
		);
		self::assertSame( array(), $val142(), 'captions track present' );
	}

	/**
	 * Quiz lesson from the module pool: render, answer, feedback, score,
	 * completion and a new attempt, without JavaScript.
	 */
	public function test_quiz_lesson_without_js(): void {
		$admin  = Actor::user( $this->admin );
		$lesson = $this->post( 'ccap_lesson', 'Quiz', $this->modules[0] );
		ActivityService::current()->define(
			$admin,
			$lesson,
			'quiz',
			array(
				'source' => 'module_pool',
				'count'  => 2,
			),
			array( 'type' => 'attempted' )
		);
		CoursePublisher::current()->publish( $admin, $this->course );
		$this->grant();
		$actor = Actor::user( $this->learner );
		wp_set_current_user( $this->learner );
		$clock = time() - 600;
		\CourseCaptain\Learning\SmartTrainer::set_now( $clock );
		$path = 'kurs/' . $this->uid() . '/lektion/' . $lesson;
		$page = LearningArea::resolve( $actor, $path );
		self::assertSame( 200, $page['status'] );
		self::assertSame( array(), A11yDom::fragment_problems( $page['body'] ) );
		self::assertStringContainsString( 'Question 1 of 2', $page['body'] );
		self::assertStringContainsString( 'Answered: 0 of 2 questions, correct: 0', $page['body'] );
		self::assertStringNotContainsString( 'cannot be used on this page yet', $page['body'] );

		$answer = function ( string $body, string $key ) use ( $actor, $path, $lesson ): string {
			preg_match( '/name="token" value="([^"]+)"/', $body, $token );
			return self::status_of(
				LearningArea::handle_post(
					$actor,
					$path,
					array(
						'_wpnonce'    => wp_create_nonce( LessonView::NONCE . ':' . $lesson ),
						'ccap_do'     => 'quiz_answer',
						'token'       => html_entity_decode( $token[1] ),
						'request_key' => wp_generate_uuid4(),
						'answer'      => $key,
					)
				)
			);
		};
		self::assertSame( 'empty_answer', $answer( $page['body'], '' ) );
		self::assertSame( 'quiz_answered', $answer( $page['body'], 'A' ) );
		$page = LearningArea::resolve( $actor, $path, 'quiz_answered' );
		self::assertStringContainsString( 'Correct!', $page['body'] );
		self::assertStringContainsString( 'Question 2 of 2', $page['body'] );
		self::assertStringContainsString( 'Status: not completed yet', $page['body'] );
		self::assertSame( 'quiz_answered', $answer( $page['body'], 'B' ) );
		$page = LearningArea::resolve( $actor, $path, 'quiz_answered' );
		self::assertSame( array(), A11yDom::fragment_problems( $page['body'] ) );
		self::assertStringContainsString( 'Not correct.', $page['body'] );
		self::assertStringContainsString( 'Score: 1 of 2 questions correct (50 %)', $page['body'] );
		self::assertStringContainsString( 'Status: completed', $page['body'] );
		self::assertSame( 'completed', ActivityProgress::current()->state( $this->learner, $lesson )['status'] ?? '' );

		// New attempt: new draw, count starts again; the completion stays (monotonic).
		\CourseCaptain\Learning\SmartTrainer::set_now( $clock + 60 );
		$status = self::status_of(
			LearningArea::handle_post(
				$actor,
				$path,
				array(
					'_wpnonce' => wp_create_nonce( LessonView::NONCE . ':' . $lesson ),
					'ccap_do'  => 'quiz_restart',
				)
			)
		);
		self::assertSame( 'quiz_restarted', $status );
		$page = LearningArea::resolve( $actor, $path, $status );
		self::assertStringContainsString( 'A new attempt has started', $page['body'] );
		self::assertStringContainsString( 'Answered: 0 of 2 questions', $page['body'] );
		self::assertStringContainsString( 'Status: completed', $page['body'] );
		self::assertSame( 'ccap_invalid_token', self::status_of( LearningArea::handle_post( $actor, $path, array( 'ccap_do' => 'quiz_answer', '_wpnonce' => 'x' ) ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
	}

	/**
	 * "Practise exam mistakes" entry: course room link, empty state without
	 * an exam, the wrong question of the latest evaluated exam afterwards.
	 */
	public function test_practise_exam_mistakes(): void {
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
		$this->grant();
		$actor = Actor::user( $this->learner );
		wp_set_current_user( $this->learner );
		$room = LearningArea::resolve( $actor, 'kurs/' . $this->uid() );
		self::assertStringContainsString( 'pruefungsfehler', $room['body'] );
		self::assertStringContainsString( 'Practise exam mistakes', LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben' )['body'] );
		$empty = LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben/pruefungsfehler' );
		self::assertSame( 200, $empty['status'] );
		self::assertStringContainsString( 'no evaluated exam in this course yet', $empty['body'] );

		$db       = Database::current();
		$rule     = ExamRules::current()->create(
			Actor::user( $this->admin ),
			$this->course,
			'v1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			)
		);
		$snapshot = '{}';
		$session  = $db->insert(
			'ccap_exam_session',
			array(
				'uid'           => $db->uuid(),
				'user_id'       => $this->learner,
				'course_id'     => $this->course,
				'rule_id'       => $rule,
				'mode'          => 'random',
				'form_key'      => 'rule:' . $rule . ':f8',
				'status'        => 'evaluated',
				'start_token'   => $db->uuid(),
				'snapshot'      => $snapshot,
				'snapshot_hash' => 'sha256:' . hash( 'sha256', $snapshot ),
				'started_at'    => $db->now(),
				'result'        => (string) wp_json_encode(
					array(
						'questions' => array(
							array(
								'position' => 1,
								'correct'  => false,
							),
							array(
								'position' => 2,
								'correct'  => true,
							),
						),
					)
				),
			)
		);
		foreach ( $this->questions as $i => $question ) {
			$db->insert(
				'ccap_exam_session_question',
				array(
					'session_id'  => $session,
					'position'    => $i + 1,
					'question_id' => $question,
					'revision_id' => (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) ),
				)
			);
		}
		$page = LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben/pruefungsfehler' );
		self::assertSame( array(), A11yDom::fragment_problems( $page['body'] ) );
		self::assertStringContainsString( 'First question', $page['body'] );
		self::assertStringContainsString( '1 question of your latest exam was answered incorrectly', $page['body'] );
		self::assertSame( 'pruefungsfehler', TrainerView::mode( 'exam' ) );
		preg_match( '/name="token" value="([^"]+)"/', $page['body'], $token );
		$status = TrainerView::handle(
			$actor,
			$this->course,
			'pruefungsfehler',
			array(
				'_wpnonce'    => wp_create_nonce( TrainerView::NONCE ),
				'token'       => html_entity_decode( $token[1] ),
				'request_key' => wp_generate_uuid4(),
				'answer'      => 'A',
			)
		);
		self::assertSame( 'answered', $status );
		self::assertStringContainsString( 'Correct!', LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben/pruefungsfehler', 'answered' )['body'] );
	}
}
