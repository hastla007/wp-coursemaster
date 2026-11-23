<?php
/**
 * E-342: learner and group surfaces in the learning area (self-enrollment
 * B-02, smart trainer/mistakes/due, assignment submission, group owner
 * page, invitation page with R-31/R-32, ordering and cloze in the exam room).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GroupService;
use CourseCaptain\Access\SelfEnrollment;
use CourseCaptain\Admin\GroupsScreen;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Assignment\AssignmentService;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\ExamRoom;
use CourseCaptain\Learner\GroupsView;
use CourseCaptain\Learner\Invitation;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Learner\LessonView;
use CourseCaptain\Learner\TrainerView;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables; course C-UI with module M1 (single choice, ordering, cloze).
 *
 * @group R1-16
 * @group E-342
 */
final class LearnerUiTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner without access.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Questions by type.
	 *
	 * @var array<string, int>
	 */
	private array $questions = array();

	/**
	 * Fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'ui-' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		$this->course  = $this->post( 'ccap_course', 'C-UI' );
		$this->module  = $this->post( 'ccap_module', 'M1' );
		PolicyService::flush();
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $this->module );
		$lesson = $this->post( 'ccap_lesson', 'L1', $this->module );
		ActivityService::current()->define( Actor::user( $this->admin ), $lesson, 'lesson', array(), array( 'type' => 'manual_confirm' ) );
		$this->questions['single']   = $this->question(
			array(
				'question_type' => QuestionTypes::SINGLE,
				'question_text' => 'Pick the right one',
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
		$this->questions['ordering'] = $this->question(
			array(
				'question_type' => QuestionTypes::ORDERING,
				'question_text' => 'Put in order',
				'settings'      => array(
					'items' => array(
						array( 'text' => 'first' ),
						array( 'text' => 'second' ),
						array( 'text' => 'third' ),
					),
				),
			)
		);
		$this->questions['cloze']    = $this->question(
			array(
				'question_type' => QuestionTypes::CLOZE,
				'question_text' => 'The formula ={{g1}}(A1:A5) adds the {{g2}}.',
				'settings'      => array(
					'gaps' => array(
						array(
							'id'       => 'g1',
							'accepted' => array( 'SUM' ),
						),
						array(
							'id'       => 'g2',
							'accepted' => array( 'numbers' ),
						),
					),
				),
			)
		);
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
	}

	/**
	 * Cleanup of filters.
	 */
	public function tear_down(): void {
		remove_all_filters( 'pre_option_' . \CourseCaptain\Settings\Settings::OPTION_PREFIX . 'enrollment' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Type.
	 * @param string $title     Title.
	 * @param int    $parent_id Parent.
	 * @return int
	 */
	private function post( string $type, string $title, int $parent_id = 0 ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_parent'  => $parent_id,
				'post_author'  => $this->admin,
				'post_content' => 'content',
			)
		);
	}

	/**
	 * Published question in M1.
	 *
	 * @param array<string, mixed> $data Revision data.
	 * @return int
	 */
	private function question( array $data ): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$id    = $repo->create( $admin, $data );
		$repo->publish( $admin, $id );
		$repo->add_to_pool( $admin, $this->module, $id );
		return $id;
	}

	/**
	 * Active manual grant.
	 *
	 * @param int $user User.
	 * @return int
	 */
	private function grant( int $user ): int {
		$db = Database::current();
		$id = $db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $this->course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		return $id;
	}

	/**
	 * Enrollment settings without option writes (SchemaTestCase commits).
	 *
	 * @param bool     $on   Self-enrollment.
	 * @param int|null $days Access days.
	 */
	private static function enrollment( bool $on, ?int $days = null ): void {
		$key = 'pre_option_' . \CourseCaptain\Settings\Settings::OPTION_PREFIX . 'enrollment';
		remove_all_filters( $key );
		add_filter(
			$key,
			static fn(): array => array(
				'values' => array(
					'self_enrollment'     => $on,
					'default_access_days' => $days,
				),
			)
		);
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
	 * Grants of the learner by source.
	 *
	 * @param string $source Source type.
	 * @return list<array<string, mixed>>
	 */
	private function grants_of( string $source ): array {
		$db = Database::current();
		return $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_grant' ) . '` WHERE user_id = %d AND source_type = %s ORDER BY id', $this->learner, $source ) );
	}

	/**
	 * B-02 / LE-T01: self-enrollment from catalog and course room
	 * (F2.1 off → contact hint, no button; on → grant `free_enrollment`,
	 * idempotent; ended grant → new revision; duration from the setting).
	 *
	 * @group B-02
	 * @group UX-020
	 * @group SET-070
	 */
	public function test_self_enrollment(): void {
		$actor = Actor::user( $this->learner );
		wp_set_current_user( $this->learner );
		self::enrollment( false );
		$room = LearningArea::page( $actor, 'kurs/' . $this->uid() );
		self::assertStringContainsString( 'Access to this course is granted by the course administration', $room );
		self::assertStringNotContainsString( 'value="enroll"', $room );
		self::assertStringNotContainsString( 'Enroll in C-UI for free', LearningArea::render_catalog( array() ) );
		$post = array(
			'ccap_do'  => 'enroll',
			'_wpnonce' => wp_create_nonce( LearningArea::ENROLL_NONCE . ':' . $this->course ),
		);
		self::assertSame( 'ccap_forbidden', self::status_of( LearningArea::handle_post( $actor, 'kurs/' . $this->uid(), $post ) ) );
		self::assertSame( array(), $this->grants_of( SelfEnrollment::SOURCE ) );

		self::enrollment( true, 30 );
		self::assertStringContainsString( 'Enroll in C-UI for free', LearningArea::render_catalog( array() ) );
		$room = LearningArea::page( $actor, 'kurs/' . $this->uid() );
		self::assertSame( array(), A11yDom::document_problems( $room ) );
		self::assertStringContainsString( 'value="enroll"', $room );
		self::assertStringContainsString( 'valid for 30 days', $room );
		self::assertSame( 'ccap_invalid_token', self::status_of( LearningArea::handle_post( $actor, 'kurs/' . $this->uid(), array( 'ccap_do' => 'enroll' ) + array( '_wpnonce' => 'x' ) ) ) );
		self::assertSame( 'enrolled', self::status_of( LearningArea::handle_post( $actor, 'kurs/' . $this->uid(), $post ) ) );
		$grants = $this->grants_of( SelfEnrollment::SOURCE );
		self::assertCount( 1, $grants );
		self::assertSame( 'active', $grants[0]['status'] );
		self::assertSame( (string) $this->learner, $grants[0]['source_id'] );
		self::assertNotNull( $grants[0]['valid_until'], 'default_access_days applies' );
		self::assertSame( 'enrolled', self::status_of( LearningArea::handle_post( $actor, 'kurs/' . $this->uid(), $post ) ), 'repeat is idempotent' );
		self::assertCount( 1, $this->grants_of( SelfEnrollment::SOURCE ) );
		PolicyService::flush();
		$room = LearningArea::page( $actor, 'kurs/' . $this->uid(), 'enrolled' );
		self::assertStringContainsString( 'You are enrolled.', $room );
		self::assertStringNotContainsString( 'value="enroll"', $room );
		self::assertStringContainsString( TrainerView::url( $this->course, 'smart' ), $room, 'practice entry in the course room' );

		// Ended grant: a new revision, never a revival (E-29).
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . "` SET status = 'revoked' WHERE id = %d", (int) $grants[0]['id'] ) );
		PolicyService::flush();
		self::enrollment( true );
		SelfEnrollment::current()->enroll( $actor, $this->course );
		$grants = $this->grants_of( SelfEnrollment::SOURCE );
		self::assertCount( 2, $grants );
		self::assertSame( '2', (string) $grants[1]['source_revision'] );
		self::assertNull( $grants[1]['valid_until'], 'empty duration = permanent' );

		// Unpublished courses are 404; guests need a login.
		wp_update_post(
			array(
				'ID'          => $this->course,
				'post_status' => 'draft',
			)
		);
		try {
			SelfEnrollment::current()->enroll( Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) ), $this->course );
			self::fail( 'unpublished course must be 404' );
		} catch ( \CourseCaptain\Course\CourseException $e ) {
			self::assertSame( 404, $e->status );
		}
		try {
			SelfEnrollment::current()->enroll( Actor::user( 0 ), $this->course );
			self::fail( 'guest must log in' );
		} catch ( \CourseCaptain\Course\CourseException $e ) {
			self::assertSame( 'ccap_auth_required', $e->error_code );
		}
	}

	/**
	 * LE-T04/T05/T07: smart trainer, mistakes and due reviews without JS
	 * (token form, idempotency key, feedback after PRG, empty states).
	 *
	 * @group UX-023
	 * @group UX-024
	 * @group UX-027
	 */
	public function test_trainer_modes(): void {
		$this->grant( $this->learner );
		$actor = Actor::user( $this->learner );
		wp_set_current_user( $this->learner );
		$overview = LearningArea::page( $actor, 'kurs/' . $this->uid() . '/ueben' );
		self::assertSame( array(), A11yDom::document_problems( $overview ) );
		self::assertStringContainsString( 'Not practised yet: 3', $overview );
		self::assertStringContainsString( 'No reviews are due today.', $overview );

		// Answer every delivered question once; the single choice one wrong.
		$seen = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$page = LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben/smart' );
			self::assertSame( 200, $page['status'] );
			self::assertSame( array(), A11yDom::fragment_problems( $page['body'] ) );
			preg_match( '/name="token" value="([^"]+)"/', $page['body'], $token );
			$claims = \CourseCaptain\Question\QuestionPresenter::current()->verify( $actor, html_entity_decode( $token[1] ) );
			$type   = array_search( (int) $claims['q'], $this->questions, true );
			$seen[] = $type;
			$answer = array(
				'single'   => 'B',
				'ordering' => array(
					'I1' => '1',
					'I2' => '2',
					'I3' => '3',
				),
				'cloze'    => array(
					'g1' => ' sum ',
					'g2' => 'numbers',
				),
			)[ $type ];
			if ( 'ordering' === $type ) {
				self::assertSame( 3, substr_count( $page['body'], '<select' ), 'one position field per item' );
				$bad = TrainerView::handle(
					$actor,
					$this->course,
					'smart',
					array(
						'_wpnonce'    => wp_create_nonce( TrainerView::NONCE ),
						'token'       => html_entity_decode( $token[1] ),
						'request_key' => wp_generate_uuid4(),
						'answer'      => array(
							'I1' => '1',
							'I2' => '1',
							'I3' => '',
						),
					)
				);
				self::assertSame( 'ordering_incomplete', $bad );
			}
			if ( 'cloze' === $type ) {
				self::assertStringContainsString( '<span class="ccap-gap">Gap 1</span>', $page['body'] );
				self::assertStringNotContainsString( '{{g1}}', $page['body'] );
			}
			$location = LearningArea::handle_post(
				$actor,
				'kurs/' . $this->uid() . '/ueben/smart',
				array(
					'_wpnonce'    => wp_create_nonce( TrainerView::NONCE ),
					'token'       => html_entity_decode( $token[1] ),
					'request_key' => wp_generate_uuid4(),
					'answer'      => $answer,
				)
			);
			self::assertSame( 'answered', self::status_of( $location ) );
			$feedback = LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben/smart', 'answered' )['body'];
			self::assertStringContainsString( 'single' === $type ? 'Not correct.' : 'Correct!', $feedback, $type );
			if ( 'single' === $type ) {
				self::assertStringContainsString( '<li>right</li>', $feedback, 'correct answer shown (E-147 default)' );
			}
			self::assertStringContainsString( 'Next question', $feedback );
		}
		sort( $seen );
		self::assertSame( array( 'cloze', 'ordering', 'single' ), $seen );

		// Mistakes: only the wrong single choice question.
		$wrong = LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben/fehler' )['body'];
		self::assertStringContainsString( 'Pick the right one', $wrong );
		// Due: nothing yet, then a consolidated question with a past due date.
		self::assertStringContainsString( 'All done', LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben/faellig' )['body'] );
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_question_progress' ) . "` SET learn_status = 'secure', streak = 3, due_at = %s WHERE user_id = %d AND question_id = %d", gmdate( 'Y-m-d H:i:s', time() - 3600 ), $this->learner, $this->questions['ordering'] ) );
		self::assertStringContainsString( '1 question is due for review today.', LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben' )['body'] );
		self::assertStringContainsString( 'Put in order', LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben/due' )['body'] );

		// Foreign token, bad nonce, unknown mode, no access.
		self::assertSame( 'ccap_invalid_token', TrainerView::handle( $actor, $this->course, 'smart', array( '_wpnonce' => 'x' ) ) );
		self::assertSame( 404, LearningArea::resolve( $actor, 'kurs/' . $this->uid() . '/ueben/unknown' )['status'] );
		$other = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertNotSame( 200, LearningArea::resolve( $other, 'kurs/' . $this->uid() . '/ueben/smart' )['status'] );
	}

	/**
	 * LE-T11/T12: assignment draft, submit with confirmation, return and
	 * revision, grade shown; file field with the R0-10 checks.
	 *
	 * @group UX-036
	 * @group UX-037
	 */
	public function test_assignment_submission(): void {
		$this->grant( $this->learner );
		$admin  = Actor::user( $this->admin );
		$lesson = $this->post( 'ccap_lesson', 'Task', $this->module );
		ActivityService::current()->define(
			$admin,
			$lesson,
			'assignment',
			array(
				'submission_type' => 'text_and_file',
				'max_points'      => 10,
				'passing_points'  => 6,
				'instructions'    => 'Describe your plan.',
				'text_limits'     => array(
					'min_chars' => 10,
					'max_chars' => 500,
				),
			),
			array( 'type' => 'graded' )
		);
		CoursePublisher::current()->publish( $admin, $this->course );
		$actor = Actor::user( $this->learner );
		wp_set_current_user( $this->learner );
		$path = 'kurs/' . $this->uid() . '/lektion/' . $lesson;
		$page = LearningArea::page( $actor, $path );
		self::assertSame( array(), A11yDom::document_problems( $page ) );
		self::assertStringContainsString( 'Describe your plan.', $page );
		self::assertStringContainsString( 'Text: 10 to 500 characters', $page );
		self::assertStringContainsString( 'enctype="multipart/form-data"', $page );
		self::assertStringContainsString( 'Not started yet.', $page );
		$form = static fn( array $extra ): array => $extra + array( '_wpnonce' => wp_create_nonce( LessonView::NONCE . ':' . $lesson ) );

		self::assertSame(
			'draft_saved',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					$path,
					$form(
						array(
							'ccap_do'         => 'assignment_save',
							'assignment_text' => 'Short',
						)
					)
				)
			)
		);
		self::assertSame(
			'asg_text_too_short',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					$path,
					$form(
						array(
							'ccap_do'        => 'assignment_submit',
							'confirm_submit' => '1',
						)
					)
				)
			)
		);
		$text = 'My plan has three steps and a date for each.';
		self::assertSame(
			'submit_confirm_required',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					$path,
					$form(
						array(
							'ccap_do'         => 'assignment_submit',
							'assignment_text' => $text,
						)
					)
				)
			)
		);
		self::assertStringContainsString( $text, LearningArea::page( $actor, $path ), 'draft kept, nothing lost' );

		// File: a PDF through the form field.
		$tmp = (string) wp_tempnam( 'ccap-ui' );
		file_put_contents( $tmp, "%PDF-1.4\n1 0 obj<<>>endobj\n%" . str_repeat( 'A', 500 ) . "\ntrailer<<>>\n%%EOF\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test fixture.
		$files = array(
			'assignment_file' => array(
				'name'     => 'plan.pdf',
				'type'     => 'application/pdf',
				'tmp_name' => $tmp,
				'error'    => UPLOAD_ERR_OK,
				'size'     => filesize( $tmp ),
			),
		);
		self::assertSame( 'draft_saved', self::status_of( LearningArea::handle_post( $actor, $path, $form( array( 'ccap_do' => 'assignment_save' ) ), $files ) ) );
		self::assertStringContainsString( 'Remove plan.pdf', LearningArea::page( $actor, $path ) );
		self::assertSame(
			'submitted',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					$path,
					$form(
						array(
							'ccap_do'        => 'assignment_submit',
							'confirm_submit' => '1',
						)
					)
				)
			)
		);
		$page = LearningArea::page( $actor, $path, 'submitted' );
		self::assertStringContainsString( 'Your assignment has been submitted.', $page );
		self::assertStringContainsString( 'You cannot change it any more', $page );
		self::assertStringNotContainsString( 'name="assignment_text"', $page, 'no edit form after submitting' );

		// Return with comment → revise → resubmit → grade visible.
		$view = AssignmentService::current()->view( $actor, $this->course, $lesson );
		$uid  = (string) $view['submissions'][0]['uid'];
		AssignmentService::current()->grade( $admin, $uid, 'return', null, 'Please add a date to every goal.', '', wp_generate_uuid4() );
		$page = LearningArea::page( $actor, $path );
		self::assertStringContainsString( 'Please add a date to every goal.', $page );
		self::assertStringContainsString( 'Revise your submission', $page );
		self::assertSame(
			'submitted',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					$path,
					$form(
						array(
							'ccap_do'         => 'assignment_submit',
							'assignment_text' => $text . ' Dates added.',
							'confirm_submit'  => '1',
						)
					)
				)
			)
		);
		$view = AssignmentService::current()->view( $actor, $this->course, $lesson );
		self::assertCount( 2, $view['submissions'] );
		AssignmentService::current()->grade( $admin, (string) $view['submissions'][1]['uid'], 'grade', 8000, 'Well done.', '', wp_generate_uuid4() );
		$page = LearningArea::page( $actor, $path );
		self::assertStringContainsString( 'Points: 8.0 of 10.0', $page );
		self::assertStringContainsString( 'Well done.', $page );
		self::assertStringContainsString( 'Earlier versions', $page );

		// Wrong nonce; foreign learner cannot act on this chain.
		self::assertSame(
			'ccap_invalid_token',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					$path,
					array(
						'ccap_do'  => 'assignment_save',
						'_wpnonce' => 'x',
					)
				)
			)
		);
	}

	/**
	 * Ordering and cloze in the exam room without JS (E-342, LE-T06):
	 * position selects and gap fields, incomplete order is reported and
	 * not stored, other answers are kept, submission evaluates them.
	 *
	 * @group UX-025
	 */
	public function test_exam_room_ordering_and_cloze(): void {
		$this->grant( $this->learner );
		$admin  = Actor::user( $this->admin );
		$module = $this->post( 'ccap_module', 'M2' );
		CourseStructure::current()->assign_module( $admin, $this->course, $module );
		QuestionRepository::current()->add_to_pool( $admin, $module, $this->questions['ordering'] );
		QuestionRepository::current()->add_to_pool( $admin, $module, $this->questions['cloze'] );
		$rules = ExamRules::current();
		$rule  = $rules->create(
			$admin,
			$this->course,
			'v1',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			null
		);
		$rules->set_blueprint( $admin, $rule, $module, 2 );
		$rules->publish( $admin, $rule );
		$actor   = Actor::user( $this->learner );
		$session = ExamSessions::current()->start( $actor, $rule, null, wp_generate_uuid4(), new Randomizer( '20301122' ) );
		$page    = LearningArea::page( $actor, 'pruefung/' . $session );
		self::assertSame( array(), A11yDom::document_problems( $page ) );
		self::assertStringNotContainsString( 'cannot be answered in this view', $page );
		self::assertStringContainsString( 'Position 1 of 3', $page );
		self::assertStringContainsString( 'Gap 2', $page );
		$positions = array();
		foreach ( ExamSessions::current()->view( $actor, $session )['questions'] as $item ) {
			$positions[ (string) $item['question']['type'] ] = (int) $item['position'];
		}
		$post  = static fn( array $answers, string $action = 'save' ): array => array(
			'_wpnonce' => wp_create_nonce( ExamRoom::NONCE ),
			'ccap_do'  => $action,
			'answer'   => $answers,
		);
		$cloze = array(
			'g1' => 'SUM',
			'g2' => '',
		);
		$order = $positions[ QuestionTypes::ORDERING ];
		$gap   = $positions[ QuestionTypes::CLOZE ];
		self::assertSame(
			'ordering_incomplete:' . $order,
			ExamRoom::handle(
				$actor,
				$session,
				$post(
					array(
						$order => array(
							'I1' => '2',
							'I2' => '2',
							'I3' => '',
						),
						$gap   => $cloze,
					)
				)
			)
		);
		$items = array_column( ExamSessions::current()->view( $actor, $session )['questions'], null, 'position' );
		self::assertFalse( (bool) $items[ $order ]['answered'], 'incomplete order not stored' );
		self::assertTrue( (bool) $items[ $gap ]['answered'], 'other answers kept' );
		self::assertStringContainsString( 'its own position', LearningArea::page( $actor, 'pruefung/' . $session, 'ordering_incomplete' ) );
		self::assertSame(
			'saved',
			ExamRoom::handle(
				$actor,
				$session,
				$post(
					array(
						$order => array(
							'I3' => '3',
							'I1' => '1',
							'I2' => '2',
						),
						$gap   => array(
							'g1' => 'SUM',
							'g2' => 'numbers',
						),
					)
				)
			)
		);
		$page = LearningArea::page( $actor, 'pruefung/' . $session );
		self::assertMatchesRegularExpression( '/<option value="3" selected=\'selected\'>Position 3 of 3<\/option>/', $page, 'stored order shown' );
		self::assertStringContainsString( 'value="numbers"', $page );
		// The real form re-sends every field with the submission.
		$final = array(
			$order => array(
				'I1' => '1',
				'I2' => '2',
				'I3' => '3',
			),
			$gap   => array(
				'g1' => 'sum',
				'g2' => 'Numbers',
			),
		);
		self::assertSame( 'submitted', ExamRoom::handle( $actor, $session, $post( $final, 'submit' ) + array( 'confirm' => '1' ) ) );
		$view = ExamSessions::current()->view( $actor, $session );
		self::assertSame( 'evaluated', $view['status'] );
		self::assertSame( 2, (int) $view['result']['correct'] );
	}

	/**
	 * GV-T01…T06: owner page (seats, invite, new link once, address
	 * binding, free a seat with confirmation; foreign groups 404; suspended
	 * groups read-only).
	 *
	 * @group UX-050
	 * @group UX-051
	 * @group UX-053
	 * @group UX-054
	 * @group RBAC-052
	 */
	public function test_group_owner_page(): void {
		$owner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$group = GroupService::current()->create( Actor::user( $this->admin ), $owner, 'Team A', 3, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$uid   = (string) Database::current()->scalar( Database::current()->prepare( 'SELECT uid FROM `' . Database::current()->table( 'ccap_group' ) . '` WHERE id = %d', $group ) );
		$actor = Actor::user( $owner );
		wp_set_current_user( $owner );
		$list = LearningArea::page( $actor, 'gruppen' );
		self::assertSame( array(), A11yDom::document_problems( $list ) );
		self::assertStringContainsString( 'My groups', $list, 'header link for owners' );
		self::assertStringContainsString( '3 of 3 places free', $list );
		$detail = LearningArea::page( $actor, 'gruppen/' . $uid );
		self::assertSame( array(), A11yDom::document_problems( $detail ) );
		self::assertStringContainsString( 'Nobody has been invited yet.', $detail );
		$form = static fn( array $extra ): array => $extra + array( '_wpnonce' => wp_create_nonce( GroupsView::NONCE . ':' . $group ) );

		// Invite: two valid addresses, one invalid.
		$location = LearningArea::handle_post(
			$actor,
			'gruppen/' . $uid,
			$form(
				array(
					'ccap_op' => 'invite',
					'emails'  => "a@example.org, b@example.org\nnot-an-address",
				)
			)
		);
		self::assertSame( 'invited', self::status_of( $location ) );
		$page = LearningArea::page( $actor, 'gruppen/' . $uid, 'invited' );
		self::assertStringContainsString( '2 invitations have been sent. 1 address is not valid.', $page );
		self::assertStringContainsString( '1 of 3 places free (2 open invitations)', $page );
		self::assertStringContainsString( 'a@example.org', $page, 'owner sees the full address (SEAT-017)' );

		// New group link, shown once.
		self::assertSame( 'link_created', self::status_of( LearningArea::handle_post( $actor, 'gruppen/' . $uid, $form( array( 'ccap_op' => 'link' ) ) ) ) );
		$page = LearningArea::page( $actor, 'gruppen/' . $uid, 'link_created' );
		preg_match( '/id="ccap-group-link-url" size="60" value="([^"]+)"/', $page, $link );
		self::assertNotEmpty( $link );
		self::assertStringNotContainsString( 'id="ccap-group-link-url"', LearningArea::page( $actor, 'gruppen/' . $uid ), 'shown only once (E-112)' );
		parse_str( (string) wp_parse_url( html_entity_decode( $link[1] ), PHP_URL_QUERY ), $query );
		$token = (string) $query[ Invitation::PARAM ];

		// A member joins through the link; the owner sees them as active.
		$member = Actor::user( $this->learner );
		wp_set_current_user( $this->learner );
		$invite = LearningArea::resolve( $member, '', '', $token )['body'];
		self::assertStringContainsString( 'Courses: C-UI', $invite );
		self::assertStringContainsString( 'A place in the group is free for you.', $invite );
		LearningArea::handle_post(
			$member,
			'',
			array(
				'ccap_do'         => 'claim',
				Invitation::PARAM => $token,
				'_wpnonce'        => wp_create_nonce( Invitation::NONCE ),
			)
		);
		self::assertStringContainsString( 'You have joined the group.', LearningArea::page( $member, '' ) );
		wp_set_current_user( $owner );
		$page = LearningArea::page( $actor, 'gruppen/' . $uid );
		self::assertStringContainsString( 'Member (active)', $page );
		self::assertStringContainsString( '0 of 3 places free', $page );

		// Rotating makes the old link neutral-invalid (F3.3).
		LearningArea::handle_post( $actor, 'gruppen/' . $uid, $form( array( 'ccap_op' => 'link' ) ) );
		wp_set_current_user( $this->learner );
		$other = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertStringContainsString( 'This invitation is no longer valid.', LearningArea::resolve( $other, '', '', $token )['body'] );
		wp_set_current_user( $owner );

		// Only invited addresses: the group link is switched off.
		self::assertSame(
			'policy_saved',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					'gruppen/' . $uid,
					$form(
						array(
							'ccap_op'      => 'policy',
							'claim_policy' => 'invited_address_only',
						)
					)
				)
			)
		);
		self::assertStringContainsString( 'The group link is switched off', LearningArea::page( $actor, 'gruppen/' . $uid ) );

		// Free the member's seat: confirmation first, then removed; grant revoked, progress kept.
		$seat     = (int) Database::current()->scalar( Database::current()->prepare( 'SELECT id FROM `' . Database::current()->table( 'ccap_seat' ) . "` WHERE group_id = %d AND status = 'active'", $group ) );
		$location = LearningArea::handle_post(
			$actor,
			'gruppen/' . $uid,
			$form(
				array(
					'ccap_op' => 'remove',
					'seat'    => (string) $seat,
				)
			)
		);
		self::assertStringContainsString( 'ccap_seat=' . $seat, $location );
		self::assertStringContainsString( 'Their learning progress is kept', LearningArea::page( $actor, 'gruppen/' . $uid, '', '', array( 'ccap_seat' => $seat ) ) );
		self::assertSame(
			'removed',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					'gruppen/' . $uid,
					$form(
						array(
							'ccap_op' => 'remove',
							'seat'    => (string) $seat,
							'confirm' => '1',
						)
					)
				)
			)
		);
		self::assertSame( 'revoked', (string) Database::current()->scalar( Database::current()->prepare( 'SELECT status FROM `' . Database::current()->table( 'ccap_grant' ) . "` WHERE seat_id = %d AND source_type = 'group_seat'", $seat ) ) );

		// IDOR: another user's view and post answer 404; foreign seat 404.
		$stranger = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertSame( 404, LearningArea::resolve( $stranger, 'gruppen/' . $uid )['status'] );
		self::assertSame(
			'ccap_not_found',
			self::status_of(
				LearningArea::handle_post(
					$stranger,
					'gruppen/' . $uid,
					$form(
						array(
							'ccap_op' => 'rename',
							'name'    => 'Hijack',
						)
					)
				)
			)
		);
		$second  = GroupService::current()->create( Actor::user( $this->admin ), (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ), 'Team B', 1, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$foreign = (int) Database::current()->insert(
			'ccap_seat',
			array(
				'group_id'   => $second,
				'user_id'    => $this->learner,
				'status'     => 'active',
				'claimed_at' => Database::current()->now(),
			),
			array( 'created_at', 'updated_at' )
		);
		self::assertSame(
			'ccap_not_found',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					'gruppen/' . $uid,
					$form(
						array(
							'ccap_op' => 'resend',
							'seat'    => (string) $foreign,
						)
					)
				)
			)
		);
		self::assertSame(
			'ccap_not_found',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					'gruppen/' . $uid,
					$form(
						array(
							'ccap_op' => 'remove',
							'seat'    => (string) $foreign,
							'confirm' => '1',
						)
					)
				)
			)
		);

		// Suspended (F3.5): read-only.
		GroupService::current()->set_status( Actor::user( $this->admin ), $group, 'suspended' );
		$page = LearningArea::page( $actor, 'gruppen/' . $uid );
		self::assertStringContainsString( 'This group is not active.', $page );
		self::assertStringNotContainsString( 'name="emails"', $page );
		self::assertSame(
			'ccap_group_not_active',
			self::status_of(
				LearningArea::handle_post(
					$actor,
					'gruppen/' . $uid,
					$form(
						array(
							'ccap_op' => 'rename',
							'name'    => 'New',
						)
					)
				)
			)
		);
	}

	/**
	 * UI-F1: owner REST R-33…R-37 (R0-19 RG-12) and the invitation budget
	 * with the waiting time (F3.7) on the owner page and in R-33/R-35.
	 *
	 * @group UI-F1
	 * @group R-33
	 * @group RBAC-052
	 */
	public function test_owner_rest_and_invitation_budget(): void {
		$owner   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$service = GroupService::current();
		$group   = $service->create( Actor::user( $this->admin ), $owner, 'Team R', 3, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$db      = Database::current();
		$uid     = (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $group ) );
		$call    = static function ( string $method, string $path, array $params = array(), array $headers = array() ): \WP_REST_Response {
			$request = new \WP_REST_Request( $method, '/course-captain/v1' . $path );
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
			foreach ( $headers as $key => $value ) {
				$request->set_header( $key, $value );
			}
			return rest_do_request( $request );
		};
		$code    = static fn( \WP_REST_Response $r ): array => array( $r->get_status(), (string) ( $r->get_data()['code'] ?? '' ) );

		self::assertSame( 401, $call( 'GET', '/me/groups' )->get_status() );
		wp_set_current_user( $this->learner );
		self::assertSame( array(), $call( 'GET', '/me/groups' )->get_data(), 'only own groups' );
		self::assertSame( array( 404, 'ccap_not_found' ), $code( $call( 'PUT', '/me/groups/' . $uid, array( 'name' => 'Mine' ) ) ), 'foreign group' );

		wp_set_current_user( $owner );
		$list = $call( 'GET', '/me/groups' );
		self::assertSame( 200, $list->get_status() );
		self::assertStringContainsString( 'no-store', (string) $list->get_headers()['Cache-Control'] );
		$row = $list->get_data()[0];
		self::assertSame( array( $uid, 'Team R', 'active' ), array( $row['uid'], $row['name'], $row['status'] ) );
		self::assertSame( 3, $row['seats']['total'] );
		self::assertSame( 0, $row['seats']['used'] );
		self::assertFalse( $row['invite_link'] );
		self::assertNull( $row['invite_link_expires_at'] );
		self::assertSame( array( 20, 0, 20, null, 0 ), array_values( $row['invite_budget'] ), 'max(20, 2 × seats) per day' );

		// R-34 rename with If-Match.
		self::assertSame( array( 409, 'ccap_version_conflict' ), $code( $call( 'PUT', '/me/groups/' . $uid, array( 'name' => 'Team X' ), array( 'If-Match' => '"999"' ) ) ) );
		$renamed = $call( 'PUT', '/me/groups/' . $uid, array( 'name' => 'Team R2' ), array( 'If-Match' => '"' . $row['row_version'] . '"' ) );
		self::assertSame( 200, $renamed->get_status() );
		self::assertSame( 'Team R2', $renamed->get_data()['name'] );
		self::assertSame( '"' . $renamed->get_data()['row_version'] . '"', $renamed->get_headers()['ETag'] );

		// R-35 invite with a required idempotency key.
		$emails = array( 'Ann@example.org', 'ann@example.org', 'not-an-address' );
		self::assertSame( array( 428, 'ccap_idempotency_key_required' ), $code( $call( 'POST', '/me/groups/' . $uid . '/invite', array( 'emails' => $emails ) ) ) );
		$key    = wp_generate_uuid4();
		$invite = $call( 'POST', '/me/groups/' . $uid . '/invite', array( 'emails' => $emails ), array( 'Idempotency-Key' => $key ) );
		self::assertSame( 200, $invite->get_status() );
		self::assertSame(
			array(
				array(
					'email_masked' => 'A***@example.org',
					'status'       => 'invited',
				),
				array(
					'email_masked' => '***',
					'status'       => 'invalid',
				),
			),
			$invite->get_data()['results']
		);
		self::assertFalse( $invite->get_data()['bound_to_address'] );
		self::assertSame( $invite->get_data(), $call( 'POST', '/me/groups/' . $uid . '/invite', array( 'emails' => $emails ), array( 'Idempotency-Key' => $key ) )->get_data(), 'repeat: same answer' );
		self::assertSame( 1, $service->occupied( $group ), 'no second seat on repeat' );
		self::assertSame( array( 409, 'ccap_idempotency_conflict' ), $code( $call( 'POST', '/me/groups/' . $uid . '/invite', array( 'emails' => array( 'other@example.org' ) ), array( 'Idempotency-Key' => $key ) ) ) );
		$again = $call( 'POST', '/me/groups/' . $uid . '/invite', array( 'emails' => array( 'ann@example.org' ) ), array( 'Idempotency-Key' => wp_generate_uuid4() ) );
		self::assertSame( 'already_member', $again->get_data()['results'][0]['status'] );

		$members = $call( 'GET', '/me/groups' )->get_data()[0]['members'];
		self::assertSame( array( 'A***@example.org', 'invited' ), array( $members[0]['email_masked'], $members[0]['status'] ), 'masked, no learning results' );
		self::assertSame( array( 'seat_id', 'display_name', 'email_masked', 'status', 'since' ), array_keys( $members[0] ) );

		// R-36 new group link.
		$token = $call( 'POST', '/me/groups/' . $uid . '/token' );
		self::assertSame( 200, $token->get_status() );
		self::assertStringContainsString( Invitation::PARAM . '=', $token->get_data()['invite_url'] );
		self::assertTrue( $call( 'GET', '/me/groups' )->get_data()[0]['invite_link'] );

		// R-37 withdraw the invitation; unknown seats are 404.
		self::assertSame( 204, $call( 'DELETE', '/me/groups/' . $uid . '/members/' . $members[0]['seat_id'] )->get_status() );
		self::assertSame( 0, $service->occupied( $group ) );
		self::assertSame( array( 404, 'ccap_not_found' ), $code( $call( 'DELETE', '/me/groups/' . $uid . '/members/' . $members[0]['seat_id'] ) ) );

		// F3.7: budget used up (invitations of the last 24 hours) → 429 with waiting time, shown on the page.
		for ( $i = 0; $i < 20; $i++ ) {
			$db->insert(
				'ccap_seat',
				array(
					'group_id'     => $group,
					'invite_email' => 'old' . $i . '@example.org',
					'status'       => 'expired',
					'invited_at'   => gmdate( 'Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS ),
				),
				array( 'created_at', 'updated_at' )
			);
		}
		$budget = $call( 'GET', '/me/groups' )->get_data()[0]['invite_budget'];
		self::assertSame( 0, $budget['remaining'] );
		self::assertNotNull( $budget['next_at'] );
		self::assertGreaterThan( 20 * HOUR_IN_SECONDS, $budget['retry_after'] );
		$limited = $call( 'POST', '/me/groups/' . $uid . '/invite', array( 'emails' => array( 'late@example.org' ) ), array( 'Idempotency-Key' => wp_generate_uuid4() ) );
		self::assertSame( array( 429, 'ccap_rate_limited' ), $code( $limited ) );
		self::assertSame( 'group_invite', $limited->get_data()['data']['bucket'] );
		self::assertGreaterThan( 20 * HOUR_IN_SECONDS, $limited->get_data()['data']['retry_after'], 'Retry-After of the error format (RestContract)' );
		$page = LearningArea::page( Actor::user( $owner ), 'gruppen/' . $uid );
		self::assertSame( array(), A11yDom::document_problems( $page ) );
		self::assertStringContainsString( 'The invitations for today are used up (at most 20 per day). You can send the next invitation in', $page );
		self::assertSame( 'budget', GroupsScreen::invite_list( Actor::user( $owner ), $group, array( 'late@example.org' ) )[0]['status'], 'the owner form is limited too' );

		// Suspended groups are read-only (F3.5).
		$service->set_status( Actor::user( $this->admin ), $group, 'suspended' );
		self::assertSame( array( 409, 'ccap_group_not_active' ), $code( $call( 'POST', '/me/groups/' . $uid . '/token' ) ) );
		self::assertSame( 'suspended', $call( 'GET', '/me/groups' )->get_data()[0]['status'] );
	}

	/**
	 * R-31/R-32 and the invitation page messages (GV-T07, F3.1/F3.2/F3.4).
	 *
	 * @group UX-055
	 * @group R-31
	 * @group R-32
	 */
	public function test_join_rest_and_invitation_messages(): void {
		$owner   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$service = GroupService::current();
		$group   = $service->create( Actor::user( $this->admin ), $owner, 'Team J', 1, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$token   = $service->rotate_link( Actor::user( $owner ), $group );
		wp_set_current_user( $this->learner );
		$call    = static function ( string $method, string $path, array $params = array() ): \WP_REST_Response {
			$request = new \WP_REST_Request( $method, '/course-captain/v1' . $path );
			foreach ( $params as $key => $value ) {
				$request->set_param( $key, $value );
			}
			return rest_do_request( $request );
		};
		$preview = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/groups/join/' . $token ) );
		self::assertSame( 200, $preview->get_status() );
		self::assertSame(
			array(
				'group'                  => array( 'name' => 'Team J' ),
				'course_titles'          => array( 'C-UI' ),
				'seats_free'             => true,
				'bound_to_other_address' => false,
			),
			$preview->get_data()
		);
		self::assertSame( 422, rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/groups/join/ccg_' . str_repeat( 'x', 43 ) ) )->get_status() );
		$join = $call( 'POST', '/groups/join', array( 'token' => $token ) );
		self::assertSame( 200, $join->get_status() );
		self::assertSame( 'active', $join->get_data()['status'] );
		$again = $call( 'POST', '/groups/join', array( 'token' => $token ) );
		self::assertSame( $join->get_data(), $again->get_data(), 'already member → same answer' );

		// F3.1: full group.
		$late = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $late );
		$full = $call( 'POST', '/groups/join', array( 'token' => $token ) );
		self::assertSame( 409, $full->get_status() );
		self::assertSame( 'ccap_group_full', $full->as_error()->get_error_code() );
		self::assertStringContainsString( 'There is no free place in this group', LearningArea::resolve( Actor::user( $late ), '', '', $token )['body'] );

		// F3.2: expired personal invitation → 410 and the neutral text.
		$seats = $service->reduce_seats( Actor::user( $this->admin ), $group, 3 );
		unset( $seats );
		$invite = $service->invite( Actor::user( $owner ), $group, 'bound@example.org' );
		self::assertNotNull( $invite );
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_seat' ) . '` SET claim_expires_at = %s WHERE id = %d', gmdate( 'Y-m-d H:i:s', time() - 60 ), $invite['seat_id'] ) );
		self::assertSame( 410, rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/groups/join/' . $invite['token'] ) )->get_status() );
		self::assertSame( 410, $call( 'POST', '/groups/join', array( 'token' => $invite['token'] ) )->get_status() );
		self::assertStringContainsString( 'This invitation is no longer valid.', LearningArea::resolve( Actor::user( $late ), '', '', $invite['token'] )['body'] );

		// F3.4: address binding → 403, masked address on the page.
		$service->set_claim_policy( Actor::user( $owner ), $group, 'invited_address_only' );
		$bound = $service->invite( Actor::user( $owner ), $group, 'bound2@example.org' );
		self::assertNotNull( $bound );
		$preview = rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/groups/join/' . $bound['token'] ) );
		self::assertTrue( $preview->get_data()['bound_to_other_address'] );
		self::assertStringNotContainsString( 'bound2@example.org', (string) wp_json_encode( $preview->get_data() ) );
		$denied = $call( 'POST', '/groups/join', array( 'token' => $bound['token'] ) );
		self::assertSame( 403, $denied->get_status() );
		self::assertSame( 'ccap_invite_other_account', $denied->as_error()->get_error_code() );
		$page = LearningArea::resolve( Actor::user( $late ), '', '', $bound['token'] )['body'];
		self::assertStringContainsString( 'b***@example.org', $page );
		self::assertStringNotContainsString( 'value="claim"', $page );

		// Anonymous: 401 (CT-02).
		wp_set_current_user( 0 );
		self::assertSame( 401, rest_do_request( new \WP_REST_Request( 'GET', '/course-captain/v1/groups/join/' . $token ) )->get_status() );
	}
}
