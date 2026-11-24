<?php
/**
 * E-342 / R1-29 W-12: authoring screens for the usability tasks (A-07, A-08,
 * grading, A-13, A-09, preview) and the alt-text rule VAL-140/141 (B-05).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Admin\AdminUi;
use CourseCaptain\Admin\ExamsScreen;
use CourseCaptain\Admin\GradingArea;
use CourseCaptain\Admin\ImportScreen;
use CourseCaptain\Admin\LearnersScreen;
use CourseCaptain\Admin\PreviewScreen;
use CourseCaptain\Admin\QuestionForm;
use CourseCaptain\Admin\QuestionsScreen;
use CourseCaptain\Assignment\AssignmentService;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Publish\CourseValidator;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;

/**
 * Real tables; every screen through its testable `process()` / `html()`.
 *
 * @group R1-05
 * @group E-342
 */
final class AuthoringScreensTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Subscriber without authoring rights.
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
	 * Modules.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Temporary files.
	 *
	 * @var list<string>
	 */
	private array $tmp = array();

	/**
	 * Course with two modules and one text lesson each.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin      = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course     = $this->post( 'ccap_course', 0, 'Course' );
		PolicyService::flush();
		for ( $i = 1; $i <= 2; $i++ ) {
			$module          = $this->post( 'ccap_module', 0, 'Module ' . $i );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $module, $i );
			$lesson = $this->post( 'ccap_lesson', $module, 'Lesson ' . $i );
			ActivityService::current()->define( Actor::user( $this->admin ), $lesson, 'lesson', array() );
		}
		wp_set_current_user( $this->admin );
	}

	/**
	 * Cleanup.
	 */
	public function tear_down(): void {
		foreach ( $this->tmp as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
		parent::tear_down();
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Type.
	 * @param int    $parent_id Parent.
	 * @param string $title     Title.
	 * @param string $content   Content.
	 * @return int
	 */
	private function post( string $type, int $parent_id, string $title, string $content = 'Text' ): int {
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
	 * POST of a screen with a valid nonce.
	 *
	 * @param string               $action   admin-post action.
	 * @param string               $op       Operation.
	 * @param int                  $object_id   Object.
	 * @param array<string, mixed> $data     Fields.
	 * @param string               $nonce_op Nonce group of a multi-button form ('' = op).
	 * @param int                  $user     User the nonce is made for.
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
	 * Run the question screen.
	 *
	 * @param string               $op       Operation.
	 * @param int                  $question Question.
	 * @param array<string, mixed> $data     Fields.
	 * @param int                  $user     User.
	 * @return array<string, mixed>
	 */
	private function question( string $op, int $question, array $data, int $user = 0 ): array {
		$user  = 0 === $user ? $this->admin : $user;
		$group = in_array( $op, array( 'save', 'save_publish' ), true ) ? 'editor' : '';
		$out   = QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, $op, $question, $data, $group, $user ), $user );
		wp_set_current_user( $this->admin );
		PolicyService::flush();
		return $out;
	}

	/**
	 * Image attachment.
	 *
	 * @return int
	 */
	private function image(): int {
		$upload = wp_upload_dir();
		wp_mkdir_p( $upload['path'] );
		$file  = $upload['path'] . '/ccap-test-' . wp_generate_password( 6, false, false ) . '.png';
		$image = imagecreatetruecolor( 4, 4 );
		imagepng( $image, $file );
		$this->tmp[] = $file;
		return (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Chart',
				'post_status'    => 'inherit',
			),
			$file
		);
	}

	/**
	 * Codes of a report by severity.
	 *
	 * @param array<string, mixed> $report   Report.
	 * @param string               $severity Severity.
	 * @return list<string>
	 */
	private static function codes( array $report, string $severity = 'blocker' ): array {
		return array_values( array_unique( array_column( array_filter( $report['issues'], static fn( array $i ): bool => $severity === $i['severity'] ), 'code' ) ) );
	}

	/**
	 * Newest revision data of a question.
	 *
	 * @param int $question Question.
	 * @return array<string, mixed>
	 */
	private static function revision( int $question ): array {
		return (array) QuestionRepository::current()->revision( QuestionsScreen::latest_revision( $question ) );
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
	 * Valid editor input per neutral question type.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function inputs(): array {
		return array(
			QuestionTypes::SINGLE     => array(
				'question_text'  => 'Which colour has the sky?',
				'answers'        => array( array( 'text' => 'Blue' ), array( 'text' => 'Green' ), array( 'text' => '' ) ),
				'correct_single' => '0',
				'shuffle'        => '1',
			),
			QuestionTypes::MULTI      => array(
				'question_text' => 'Which are fruits?',
				'answers'       => array(
					array(
						'text'    => 'Apple',
						'correct' => '1',
					),
					array(
						'text'    => 'Pear',
						'correct' => '1',
					),
					array( 'text' => 'Stone' ),
				),
				'scoring'       => QuestionTypes::PROPORTIONAL_PENALTY,
			),
			QuestionTypes::TRUE_FALSE => array(
				'question_text' => 'Water is wet.',
				'tf_correct'    => 'true',
			),
			QuestionTypes::OPEN_TEXT  => array(
				'question_text' => 'Explain a bowline.',
				'model_answer'  => 'A fixed loop.',
			),
			QuestionTypes::FLASHCARD  => array(
				'question_text' => 'Port',
				'back'          => 'Left side',
			),
			QuestionTypes::NUMERIC    => array(
				'question_text'  => 'How many metres?',
				'target'         => '12,5',
				'tolerance'      => '0.5',
				'tolerance_type' => 'absolute',
				'unit'           => 'm',
			),
			QuestionTypes::MATCHING   => array(
				'question_text' => 'Match the pairs.',
				'pairs'         => array(
					array(
						'left'  => 'Red',
						'right' => 'Port',
					),
					array(
						'left'  => 'Green',
						'right' => 'Starboard',
					),
				),
				'distractors'   => "Bow\nStern",
			),
			QuestionTypes::ORDERING   => array(
				'question_text' => 'Order the steps.',
				'items'         => "First\nSecond\nThird",
			),
			QuestionTypes::CLOZE      => array(
				'question_text'  => 'The capital of France is [1].',
				'gaps'           => array(
					array(
						'id'       => '1',
						'accepted' => 'Paris|paris',
					),
				),
				'case_sensitive' => '1',
			),
		);
	}

	/**
	 * Question editor: rights, nonce, all neutral types saved and published, pool.
	 */
	public function test_question_editor_all_types_rights_and_nonce(): void {
		$denied = $this->question( 'save_publish', 0, array( 'question_type' => 'single_choice' ) + self::inputs()['single_choice'], $this->subscriber );
		self::assertSame( 'ccap_forbidden', $denied['result'] );
		self::assertStringContainsString( 'not allowed', QuestionsScreen::html( array(), $this->subscriber ) );
		$forged = QuestionsScreen::process(
			array(
				'op'       => 'save',
				'object'   => 0,
				'nonce_op' => 'editor',
				'_wpnonce' => 'bad',
			),
			$this->admin
		);
		self::assertSame( 'ccap_invalid_nonce', $forged['result'] );
		$wrong_group = $this->form( QuestionsScreen::ACTION, 'archive', 0, array(), 'editor' );
		self::assertSame( 'ccap_invalid_nonce', QuestionsScreen::process( $wrong_group, $this->admin )['result'], 'editor nonce does not cover archive' );

		foreach ( self::inputs() as $type => $input ) {
			$out = $this->question( 'save_publish', 0, array( 'question_type' => $type, 'module' => $this->modules[0] ) + $input ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
			self::assertSame( 'published', $out['result'], $type );
			$revision = self::revision( $out['question'] );
			self::assertSame( $type, $revision['question_type'] );
			self::assertSame( array(), QuestionTypes::validate( $revision ), $type );
			self::assertContains( $out['question'], QuestionRepository::current()->pool( $this->modules[0] ), $type );
		}
		self::assertSame( 9, self::audits( 'question_published' ) );
		$numeric = self::revision( (int) array_reverse( QuestionRepository::current()->pool( $this->modules[0] ) )[3] );
		self::assertSame( '12.5', $numeric['settings']['target'], 'decimal comma accepted' );
		self::assertStringContainsString( 'Course', QuestionsScreen::html( array( 'module' => $this->modules[0] ), $this->admin ) );
		self::assertStringContainsString( '9 questions found', QuestionsScreen::html( array( 'module' => $this->modules[0] ), $this->admin ) );
		$step = QuestionsScreen::html( array( 'action' => 'new' ), $this->admin );
		self::assertStringContainsString( 'Step 1 of 2', $step );
		$form = QuestionsScreen::html(
			array(
				'action' => 'new',
				'type'   => 'numeric_tolerance',
			),
			$this->admin
		);
		self::assertStringContainsString( 'name="tolerance"', $form );
		self::assertStringNotContainsString( 'name="correct_single"', $form, 'only the fields of the chosen type' );
	}

	/**
	 * Validation: incomplete question stays a draft with messages; input is kept.
	 */
	public function test_question_validation_and_error_guidance(): void {
		$input = array(
			'question_type' => 'single_choice',
			'question_text' => 'No correct answer marked',
			'answers'       => array( array( 'text' => 'A' ), array( 'text' => 'B' ) ),
		);
		$out   = $this->question( 'save_publish', 0, $input );
		self::assertSame( 'ccap_saved_not_published', $out['result'] );
		self::assertContains( 'Mark exactly one answer as correct.', $out['messages'] );
		self::assertSame( 'draft', Database::current()->scalar( Database::current()->prepare( 'SELECT status FROM `' . Database::current()->table( 'ccap_question' ) . '` WHERE id = %d', $out['question'] ) ) );
		$page = QuestionsScreen::html(
			array(
				'action'      => 'edit',
				'question'    => $out['question'],
				'ccap_result' => $out['result'],
			),
			$this->admin
		);
		self::assertStringContainsString( 'role="alert"', $page );
		self::assertStringContainsString( 'Mark exactly one answer as correct.', $page );
		self::assertStringContainsString( 'No correct answer marked', $page, 'draft kept' );

		$missing = $this->question( 'save', 0, array( 'question_type' => 'nonsense' ) );
		self::assertSame( 'ccap_validation_failed', $missing['result'] );
		self::assertArrayHasKey( 'ccap-q-type', $missing['fields'] );
		self::assertSame( 0, $missing['question'], 'nothing created' );

		$publish = $this->question( 'publish', $out['question'], array() );
		self::assertSame( 'ccap_validation_failed', $publish['result'] );
		$fixed = $this->question( 'save_publish', $out['question'], array( 'correct_single' => '1' ) + $input );
		self::assertSame( 'published', $fixed['result'] );
		$other_type = $this->question( 'save', $out['question'], array( 'question_type' => 'multi_choice' ) + $input );
		self::assertSame( 'ccap_invalid_param', $other_type['result'], 'type of an existing question is fixed' );
	}

	/**
	 * AU-T10: editing a published question warns, creates a new revision and keeps the old one.
	 */
	public function test_published_question_edit_creates_revision(): void {
		$created = $this->question( 'save_publish', 0, array( 'question_type' => 'true_false' ) + self::inputs()['true_false'] );
		$old     = QuestionsScreen::latest_revision( $created['question'] );
		$page    = QuestionsScreen::html(
			array(
				'action'   => 'edit',
				'question' => $created['question'],
			),
			$this->admin
		);
		self::assertStringContainsString( 'Saving creates a new version. Running exam attempts keep the version they started with', $page );
		self::assertStringContainsString( 'name="change_class"', $page );
		$saved = $this->question(
			'save',
			$created['question'],
			array(
				'question_type' => 'true_false',
				'question_text' => 'Water is wet (typo fixed).',
				'tf_correct'    => 'true',
				'change_class'  => 'editorial',
			)
		);
		self::assertSame( 'ok', $saved['result'] );
		$new = QuestionsScreen::latest_revision( $created['question'] );
		self::assertNotSame( $old, $new );
		self::assertSame( 'Water is wet.', QuestionRepository::current()->revision( $old )['question_text'], 'old revision unchanged' );
		self::assertSame( 'published', QuestionRepository::current()->revision( $old )['editorial_status'] );
		self::assertSame( 'draft', QuestionRepository::current()->revision( $new )['editorial_status'] );
		self::assertStringContainsString( 'unpublished changes', QuestionsScreen::html( array(), $this->admin ) );
		self::assertSame( 'ok', $this->question( 'publish', $created['question'], array() )['result'] );
		self::assertSame( 'deprecated', QuestionRepository::current()->revision( $old )['editorial_status'] );
		self::assertSame( 'ccap_confirmation_required', $this->question( 'archive', $created['question'], array() )['result'] );
		self::assertSame( 'ok', $this->question( 'archive', $created['question'], array( 'confirm' => '1' ) )['result'] );
		self::assertSame( 1, self::audits( 'question_archived' ) );
	}

	/**
	 * B-05: question image needs an alt text or "decorative"; VAL-140/141 block publishing.
	 *
	 * @group PUB-040
	 */
	public function test_question_image_alt_text_rule(): void {
		$image = $this->image();
		$input = array(
			'question_type' => 'single_choice',
			'module'        => $this->modules[0],
			'image_id'      => $image,
		) + self::inputs()['single_choice'];
		$out   = $this->question( 'save_publish', 0, $input );
		self::assertSame( 'ccap_alt_missing', $out['result'] );
		self::assertArrayHasKey( 'ccap-q-image-alt', $out['fields'] );
		self::assertStringContainsString( 'alt=""', self::revision( $out['question'] )['question_text'] );
		$db = Database::current();
		self::assertNull( $db->scalar( $db->prepare( 'SELECT alt_text FROM `' . $db->table( 'ccap_media' ) . '` WHERE attachment_id = %d', $image ) ) );
		// Publish the question by hand: the validator still blocks the course (VAL-140).
		QuestionRepository::current()->publish( Actor::user( $this->admin ), $out['question'] );
		$report = CourseValidator::validate( $this->course );
		self::assertContains( 'VAL-140', self::codes( $report ) );
		self::assertFalse( CourseValidator::publishable( $report ) );

		$described = $this->question( 'save_publish', $out['question'], array( 'image_alt' => 'A bar chart with three bars' ) + $input );
		self::assertSame( 'published', $described['result'] );
		self::assertSame( array( 'attachment' => $image, 'alt' => 'A bar chart with three bars', 'decorative' => false ), QuestionForm::split_text( self::revision( $out['question'] )['question_text'] )['image'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Compact expectation.
		self::assertNotContains( 'VAL-140', self::codes( CourseValidator::validate( $this->course ) ) );

		$decorative = $this->question( 'save_publish', 0, array( 'image_decorative' => '1' ) + $input );
		self::assertSame( 'published', $decorative['result'] );
		self::assertNotContains( 'VAL-140', self::codes( CourseValidator::validate( $this->course ) ) );

		$required = $this->question(
			'save_publish',
			0,
			array(
				'image_decorative' => '1',
				'image_required'   => '1',
			) + $input
		);
		self::assertSame( 'ccap_alt_missing', $required['result'], 'decorative is not enough when the image is needed' );
		QuestionRepository::current()->publish( Actor::user( $this->admin ), $required['question'] );
		self::assertContains( 'VAL-141', self::codes( CourseValidator::validate( $this->course ) ) );
	}

	/**
	 * VAL-140 for lesson images; explicit decorative markers count, missing alt does not.
	 */
	public function test_lesson_image_alt_rule(): void {
		$lesson = (int) get_posts(
			array(
				'post_type'   => 'ccap_lesson',
				'post_parent' => $this->modules[1],
				'fields'      => 'ids',
			)
		)[0];
		wp_update_post(
			array(
				'ID'           => $lesson,
				'post_content' => '<p>Text</p><img src="a.png"><img src="b.png" alt=""><img src="c.png" alt="" role="presentation"><img src="d.png" alt="A boat">',
			)
		);
		$issues = array_values( array_filter( CourseValidator::validate( $this->course )['issues'], static fn( array $i ): bool => 'VAL-140' === $i['code'] ) );
		self::assertCount( 1, $issues );
		self::assertSame( 'lesson', $issues[0]['object']['type'] );
		self::assertSame( 2, $issues[0]['params']['count'] );
		self::assertSame(
			array(
				'total'       => 4,
				'missing'     => 2,
				'undescribed' => 3,
			),
			CourseValidator::images_without_alt( (string) get_post_field( 'post_content', $lesson ) )
		);
	}

	/**
	 * AU-T07: exam editor with blueprint, module-group parts (E-331), time, attempts; publish guard; new version.
	 */
	public function test_exam_editor_save_publish_and_new_version(): void {
		$denied = ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'create', 0, array( 'course' => $this->course, 'pass_percent' => '60' ), '', $this->subscriber ), $this->subscriber ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_forbidden', $denied['result'] );
		$bad = ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'create', 0, array( 'course' => $this->course, 'pass_percent' => '0' ) ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_validation_failed', $bad['result'] );
		$created = ExamsScreen::process(
			$this->form(
				ExamsScreen::ACTION,
				'create',
				0,
				array(
					'course'       => $this->course,
					'version'      => 'Final exam',
					'pass_percent' => '60',
				)
			),
			$this->admin
		);
		self::assertSame( 'ok', $created['result'] );
		$rule = $created['rule'];
		self::assertGreaterThan( 0, $rule );
		list( $m1, $m2 )                = $this->modules;
		$save                           = array(
			'version'           => 'Final exam',
			'pass_percent'      => '60',
			'blueprint'         => array(
				$m1 => array(
					'count'  => '2',
					'points' => '1',
				),
				$m2 => array(
					'count'  => '1',
					'points' => '2',
				),
			),
			'parts'             => array(
				array(
					'label'   => 'All modules',
					'modules' => array( (string) $m1, (string) $m2 ),
					'percent' => '50',
				),
				array(
					'label'   => '',
					'modules' => array(),
					'percent' => '',
				),
			),
			'time_mode'         => 'value',
			'time'              => '45',
			'attempts_mode'     => 'value',
			'attempts'          => '3',
			'shuffle_questions' => 'all',
			'feedback'          => 'score',
		);
		$invalid                        = $save;
		$invalid['parts'][0]['modules'] = array();
		$rejected                       = ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'save', $rule, $invalid ), $this->admin );
		self::assertSame( 'ccap_validation_failed', $rejected['result'] );
		self::assertNotSame( array(), $rejected['messages'] );
		self::assertSame( array(), ExamRules::current()->get( $rule )['blueprints'], 'nothing saved on errors' );
		$page = ExamsScreen::html(
			array(
				'area'        => 'exams',
				'rule'        => $rule,
				'ccap_result' => 'ccap_validation_failed',
			),
			$this->admin
		);
		self::assertStringContainsString( 'Part 1: choose at least one module', $page );

		self::assertSame( 'ok', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'save', $rule, $save ), $this->admin )['result'] );
		$stored = (array) ExamRules::current()->get( $rule );
		self::assertSame(
			array(
				'num' => 3,
				'den' => 5,
			),
			$stored['passing_rule']['min_ratio']
		);
		self::assertSame( array( min( $m1, $m2 ), max( $m1, $m2 ) ), $stored['passing_rule']['parts'][0]['sections'], 'module group (E-331)' );
		self::assertSame( array(), ExamRules::validate_passing_rule( $stored['passing_rule'] ) );
		self::assertSame( 2700, $stored['time_limit_seconds'] );
		self::assertSame( 3, $stored['settings']['attempt_policy']['max_attempts'] );
		self::assertSame( 'all', $stored['settings']['shuffle_questions'] );
		self::assertFalse( $stored['settings']['feedback']['show_correct_answers'] );
		self::assertCount( 2, $stored['blueprints'] );

		// Pools are empty: publishing is refused with "pool too small".
		$refused = ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'publish', $rule, array() ), $this->admin );
		self::assertSame( 'ccap_exam_not_ready', $refused['result'] );
		self::assertStringContainsString( 'pool too small (0 of 2', implode( ' ', $refused['messages'] ) );
		foreach ( array( $m1, $m1, $m2 ) as $i => $module ) {
			$this->question( 'save_publish', 0, array( 'question_type' => 'true_false', 'module' => $module, 'question_text' => 'Q' . $i, 'tf_correct' => 'true' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		}
		self::assertSame( 'ok', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'publish', $rule, array() ), $this->admin )['result'] );
		self::assertSame( 'published', ExamRules::current()->get( $rule )['status'] );
		self::assertSame( 'ccap_rule_frozen', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'save', $rule, $save ), $this->admin )['result'] );
		self::assertSame( 1, self::audits( 'exam_rule_published' ) );

		$copy = ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'new_version', $rule, array() ), $this->admin );
		self::assertSame( 'ok', $copy['result'] );
		$draft = (array) ExamRules::current()->get( $copy['rule'] );
		self::assertSame( 'draft', $draft['status'] );
		self::assertSame( $stored['passing_rule'], $draft['passing_rule'] );
		self::assertCount( 2, $draft['blueprints'] );
		self::assertSame( 2700, $draft['time_limit_seconds'] );
		self::assertStringContainsString( 'Final exam', ExamsScreen::html( array( 'course' => $this->course ), $this->admin ) );
	}

	/**
	 * AU-T13: grading area – queue, grade, return with comment, errors, rights.
	 */
	public function test_grading_area(): void {
		$trainer  = (int) self::factory()->user->create( array( 'role' => 'ccap_trainer' ) );
		$learners = array( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ), (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$db       = Database::current();
		foreach ( $learners as $learner ) {
			$db->insert(
				'ccap_grant',
				array(
					'uid'         => $db->uuid(),
					'user_id'     => $learner,
					'course_id'   => $this->course,
					'status'      => 'active',
					'source_type' => 'manual',
					'source_id'   => $db->uuid(),
				),
				array( 'created_at', 'updated_at' )
			);
		}
		$db->insert(
			'ccap_course_staff',
			array(
				'course_id' => $this->course,
				'user_id'   => $trainer,
				'role'      => 'trainer',
				'group_id'  => 0,
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
		$lesson = $this->post( 'ccap_lesson', $this->modules[0], 'Task' );
		ActivityService::current()->define(
			Actor::user( $this->admin ),
			$lesson,
			'assignment',
			array(
				'submission_type' => 'text',
				'max_points'      => 10,
				'passing_points'  => 6,
				'rubric_text'     => 'Grading guide text',
			),
			array( 'type' => 'graded' )
		);
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
		$service = AssignmentService::current();
		$uids    = array();
		foreach ( $learners as $learner ) {
			$draft  = $service->save_draft( Actor::user( $learner ), $this->course, $lesson, 'My answer' );
			$uids[] = $service->submit( Actor::user( $learner ), $draft['submission_uid'] )['submission_uid'];
		}
		self::assertSame( array( 'exams', 'grading' ), array_keys( ExamsScreen::areas( $this->admin ) ) );
		self::assertSame( array( 'grading' ), array_keys( ExamsScreen::areas( $trainer ) ), 'trainer sees only grading (M-2)' );
		self::assertSame( array(), ExamsScreen::areas( $this->subscriber ) );
		self::assertSame( array( 'ccap_grade_attempts' ), ExamsScreen::map_meta_cap( array(), ExamsScreen::MENU_CAP, $trainer ) );
		$queue = ExamsScreen::html( array( 'area' => 'grading' ), $trainer );
		self::assertStringContainsString( '2 submissions are waiting.', $queue );
		self::assertStringContainsString( 'Grading guide text', ExamsScreen::html( array( 'area' => 'grading', 'submission' => $uids[0] ), $trainer ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.

		$grade = function ( string $op, string $uid, array $data, int $user ): array {
			return GradingArea::process( $this->form( GradingArea::ACTION, $op, 0, array( 'submission' => $uid, 'request_key' => wp_generate_uuid4() ) + $data, 'grading', $user ), $user ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		};
		self::assertSame( 'ccap_points_invalid', $grade( 'grade', $uids[0], array( 'points' => 'eight' ), $trainer )['result'] );
		self::assertSame( 'ccap_grade_invalid_points', $grade( 'grade', $uids[0], array( 'points' => '11' ), $trainer )['result'] );
		self::assertSame( 'ccap_forbidden', $grade( 'grade', $uids[0], array( 'points' => '8' ), $learners[0] )['result'], 'no self-grading' );
		self::assertSame( 'ccap_invalid_nonce', GradingArea::process( array( 'op' => 'grade', 'submission' => $uids[0], 'nonce_op' => 'grading', '_wpnonce' => 'x' ), $trainer )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$graded = $grade( 'grade', $uids[0], array( 'points' => '8', 'feedback' => 'Good' ), $trainer ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', $graded['result'] );
		self::assertSame( 'graded', $graded['status'] );
		self::assertStringContainsString( 'Status now: graded', ExamsScreen::html( array( 'area' => 'grading', 'ccap_result' => 'ok', 'ccap_status' => 'graded' ), $trainer ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.

		$no_comment = $grade( 'return', $uids[1], array( 'feedback' => '  ' ), $trainer );
		self::assertSame( 'ccap_grade_invalid_comment_required', $no_comment['result'] );
		self::assertStringContainsString( 'write a comment', ExamsScreen::html( array( 'area' => 'grading', 'ccap_result' => $no_comment['result'], 'submission' => $uids[1] ), $trainer ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$returned = $grade( 'return', $uids[1], array( 'feedback' => 'Please add an example.' ), $trainer );
		self::assertSame( 'returned', $returned['status'] );
		self::assertSame( 'graded', $service->detail( Actor::user( $learners[0] ), $uids[0] )['status'] );
		self::assertSame( 'Please add an example.', $service->detail( Actor::user( $learners[1] ), $uids[1] )['return_comment'] );
		self::assertStringContainsString( 'Nothing to grade right now.', ExamsScreen::html( array( 'area' => 'grading' ), $trainer ) );
	}

	/**
	 * AU-T06: upload → check → test run → approve and import; discard; rollback with reason; report links.
	 */
	public function test_import_screen(): void {
		$dir = get_temp_dir() . 'ccap-h10-' . wp_generate_password( 6, false, false );
		wp_mkdir_p( $dir );
		$question    = PackageExporter::sealed(
			array(
				'source_id' => 'Q-1',
				'revision'  => array(
					'question_type' => 'single_choice',
					'text_html'     => 'What is A?',
					'settings'      => (object) array(),
				),
				'answers'   => array(
					array(
						'key'       => 'A',
						'text_html' => 'right',
						'correct'   => true,
					),
					array(
						'key'       => 'B',
						'text_html' => 'wrong',
						'correct'   => false,
					),
				),
				'tags'      => array(),
			)
		);
		$records     = array(
			'questions' => array( $question ),
			'lessons'   => array(
				PackageExporter::sealed(
					array(
						'source_id'     => 'L-1',
						'title'         => 'Lesson 1',
						'body_html'     => '<p>Text</p>',
						'activity_type' => 'lesson',
						'settings'      => (object) array(),
					)
				),
			),
			'modules'   => array(
				PackageExporter::sealed(
					array(
						'source_id'     => 'M-1',
						'title'         => 'Module 1',
						'summary'       => '',
						'lessons'       => array(
							array(
								'lesson' => 'L-1',
								'sort'   => 0,
							),
						),
						'question_pool' => array(
							array(
								'question' => 'Q-1',
								'sort'     => 1,
							),
						),
					)
				),
			),
			'courses'   => array(
				PackageExporter::sealed(
					array(
						'source_id'        => 'C-1',
						'title'            => 'Imported course',
						'slug'             => 'imported-course',
						'summary'          => '',
						'description_html' => '<p>About</p>',
						'modules'          => array(
							array(
								'module'   => 'M-1',
								'sort'     => 10,
								'required' => true,
							),
						),
					)
				),
			),
		);
		$zip         = PackageExporter::write( $records, 'fixture-h10', 'Fixture', $dir . '/p.zip' );
		$this->tmp[] = $zip;
		$bad         = $dir . '/bad.zip';
		file_put_contents( $bad, 'not a zip' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test fixture.
		$this->tmp[] = $bad;

		self::assertStringContainsString( 'not allowed', ImportScreen::html( array(), $this->subscriber ) );
		self::assertSame( 'ccap_forbidden', ImportScreen::process( $this->form( ImportScreen::ACTION, 'upload', 0, array(), '', $this->subscriber ), $this->subscriber, $zip )['result'] );
		self::assertSame( 'ccap_upload_missing', ImportScreen::process( $this->form( ImportScreen::ACTION, 'upload', 0, array() ), $this->admin, '', UPLOAD_ERR_NO_FILE )['result'] );
		self::assertSame( 'ccap_upload_too_large', ImportScreen::process( $this->form( ImportScreen::ACTION, 'upload', 0, array() ), $this->admin, '', UPLOAD_ERR_INI_SIZE )['result'] );
		self::assertSame( 'ccap_upload_not_zip', ImportScreen::process( $this->form( ImportScreen::ACTION, 'upload', 0, array() ), $this->admin, $bad )['result'] );

		$up = ImportScreen::process( $this->form( ImportScreen::ACTION, 'upload', 0, array() ), $this->admin, $zip );
		self::assertSame( 'previewed', $up['result'] );
		$page = ImportScreen::html( array( 'batch' => $up['batch'], 'ccap_result' => 'previewed' ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertStringContainsString( 'Nothing has been imported yet', $page );
		self::assertStringContainsString( 'Result of the test run', $page );
		self::assertStringContainsString( 'format=csv', $page );
		self::assertStringContainsString( 'Approve and import', $page );
		self::assertSame( 0, (int) Database::current()->scalar( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_source_map' ) . "` WHERE source_system = 'fixture-h10'" ), 'dry run writes no content' );

		$run = ImportScreen::process( $this->form( ImportScreen::ACTION, 'approve', $up['batch'], array() ), $this->admin );
		self::assertSame( 'imported', $run['result'] );
		self::assertSame( 'published', ImportService::current()->get( $up['batch'] )['status'] );
		self::assertSame( 1, self::audits( 'import_approved' ) );

		$second = ImportScreen::process( $this->form( ImportScreen::ACTION, 'upload', 0, array() ), $this->admin, $zip );
		self::assertSame( 'ccap_confirmation_required', ImportScreen::process( $this->form( ImportScreen::ACTION, 'discard', $second['batch'], array() ), $this->admin )['result'] );
		self::assertSame( 'ok', ImportScreen::process( $this->form( ImportScreen::ACTION, 'discard', $second['batch'], array( 'confirm' => '1' ) ), $this->admin )['result'] );
		self::assertSame( 'discarded', ImportService::current()->get( $second['batch'] )['status'] );

		self::assertSame( 'ccap_validation_failed', ImportScreen::process( $this->form( ImportScreen::ACTION, 'rollback', $up['batch'], array( 'reason' => ' ' ) ), $this->admin )['result'] );
		$rollback = ImportScreen::process( $this->form( ImportScreen::ACTION, 'rollback', $up['batch'], array( 'reason' => 'Wrong version' ) ), $this->admin );
		self::assertSame( 'previewed', $rollback['result'] );
		self::assertNotSame( $up['batch'], $rollback['batch'] );
		self::assertStringContainsString( 'Rollback to import', ImportScreen::html( array( 'batch' => $rollback['batch'] ), $this->admin ) );
		self::assertSame( 'ccap_import_state', ImportScreen::process( $this->form( ImportScreen::ACTION, 'run', $rollback['batch'], array() ), $this->admin )['result'], 'run needs approval first' );
		foreach ( (array) glob( $dir . '/*' ) as $left ) {
			wp_delete_file( (string) $left );
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test cleanup.
	}

	/**
	 * AU-T11: manual grant for selected modules (E-27), access list with progress, end access.
	 */
	public function test_learners_screen(): void {
		$learner = (int) self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'user_login'   => 'ux-learner',
				'user_email'   => 'ux.learner@example.org',
				'display_name' => 'UX Learner',
			)
		);
		self::assertStringContainsString( 'not allowed', LearnersScreen::html( array(), $this->subscriber ) );
		$key  = wp_generate_uuid4();
		$base = array(
			'course'      => $this->course,
			'learner'     => 'ux-learner',
			'scope'       => 'modules',
			'modules'     => array( (string) $this->modules[0], (string) $this->modules[1] ),
			'reason'      => 'Usability test',
			'request_key' => $key,
		);
		self::assertSame( 'ccap_forbidden', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'grant', 0, $base, '', $this->subscriber ), $this->subscriber )['result'] );
		self::assertSame( 'ccap_learner_unknown', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'grant', 0, array( 'learner' => 'nobody' ) + $base ), $this->admin )['result'] );
		self::assertSame( 'ccap_reason_required', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'grant', 0, array( 'reason' => ' ' ) + $base ), $this->admin )['result'] );
		self::assertSame( 'ccap_modules_required', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'grant', 0, array( 'modules' => array() ) + $base ), $this->admin )['result'] );
		$out = LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'grant', 0, $base ), $this->admin );
		self::assertSame( 'ok', $out['result'] );
		self::assertSame( 2, $out['count'] );
		$again = LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'grant', 0, $base ), $this->admin );
		self::assertSame( 'ok', $again['result'], 'same form twice = same grants' );
		$db     = Database::current();
		$grants = $db->rows( $db->prepare( 'SELECT id, module_id, status FROM `' . $db->table( 'ccap_grant' ) . '` WHERE user_id = %d AND course_id = %d ORDER BY module_id', $learner, $this->course ) );
		self::assertSame( array( min( $this->modules ), max( $this->modules ) ), array_map( 'intval', array_column( $grants, 'module_id' ) ) );
		self::assertSame( array( 'active', 'active' ), array_column( $grants, 'status' ) );
		$list = LearnersScreen::html( array( 'course' => $this->course ), $this->admin );
		self::assertStringContainsString( 'UX Learner', $list );
		self::assertStringContainsString( 'u…@example.org', $list, 'address masked (E-81)' );
		self::assertStringNotContainsString( 'ux.learner@example.org', $list );
		self::assertStringContainsString( 'Progress of UX Learner', $list );
		$detail = LearnersScreen::html( array( 'course' => $this->course, 'learner' => $learner ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertStringContainsString( 'Lesson 1', $detail );
		self::assertStringContainsString( 'not opened', $detail );

		$grant = (int) $grants[0]['id'];
		self::assertSame( 'ccap_confirmation_required', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'revoke', $grant, array( 'course' => $this->course, 'reason' => 'x' ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', LearnersScreen::process( $this->form( LearnersScreen::ACTION, 'revoke', $grant, array( 'course' => $this->course, 'reason' => 'Test over', 'confirm' => '1' ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'revoked', $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $grant ) ) );
		self::assertSame( 1, self::audits( 'grant.revoked_manually' ) );
		self::assertSame( 'ccap_invalid_nonce', LearnersScreen::process( array( 'op' => 'grant', 'object' => 0, '_wpnonce' => 'x' ) + $base, $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
	}

	/**
	 * AU-T08: preview as learner shows the draft without solution data and writes nothing.
	 */
	public function test_preview_as_learner(): void {
		$this->question( 'save_publish', 0, array( 'question_type' => 'single_choice', 'module' => $this->modules[0] ) + self::inputs()['single_choice'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$db     = Database::current();
		$before = (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_activity_progress' ) . '`' );
		$html   = PreviewScreen::html( $this->course, $this->admin );
		self::assertStringContainsString( 'Preview as learner: Course', $html );
		self::assertStringContainsString( 'Module 1: Module 1', $html );
		self::assertStringContainsString( 'Lesson 2', $html );
		self::assertStringContainsString( 'Which colour has the sky?', $html );
		self::assertStringContainsString( '<fieldset class="ccap-pv-question" disabled', $html );
		self::assertStringNotContainsString( 'correct', strtolower( wp_strip_all_tags( $html ) ), 'no solution data' );
		self::assertSame( $before, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_activity_progress' ) . '`' ), 'no progress written' );
		self::assertStringContainsString( 'may not edit it', PreviewScreen::html( $this->course, $this->subscriber ) );
		$actions = PreviewScreen::row_action( array(), get_post( $this->course ) );
		self::assertArrayHasKey( 'ccap_preview', $actions );
	}

	/**
	 * Form helpers: percent ↔ ratio and masking.
	 */
	public function test_ui_helpers(): void {
		self::assertSame(
			array(
				'num' => 3,
				'den' => 5,
			),
			AdminUi::ratio( '60' )
		);
		self::assertSame(
			array(
				'num' => 133,
				'den' => 200,
			),
			AdminUi::ratio( '66,5 %' )
		);
		self::assertNull( AdminUi::ratio( '0' ) );
		self::assertNull( AdminUi::ratio( '101' ) );
		self::assertNull( AdminUi::ratio( 'abc' ) );
		self::assertSame( '70', AdminUi::percent( 7, 10 ) );
		self::assertSame( '66.67', AdminUi::percent( 2, 3 ) );
		self::assertSame( 'a…@b.de', LearnersScreen::mask( 'anna@b.de' ) );
	}
}
