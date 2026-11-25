<?php
/**
 * UI-F8 part 1: author screens for fixed exam forms, course export,
 * import by link/folder, rights & release per course and the change of a
 * question's type.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\AdminUi;
use CourseCaptain\Admin\ExamsScreen;
use CourseCaptain\Admin\ExportArea;
use CourseCaptain\Admin\ImportScreen;
use CourseCaptain\Admin\QuestionsScreen;
use CourseCaptain\Admin\RightsArea;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\ImportSources;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Import\Rights;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\ExamRoom;
use CourseCaptain\Ops\AuditView;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\TypeChange;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables; every screen through its testable `process()` / `html()`.
 *
 * @group UI-F8
 */
final class AuthorUiF8Test extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner with access.
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
	 * Modules.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Published pool questions per module.
	 *
	 * @var array<int, list<int>>
	 */
	private array $pool = array();

	/**
	 * Temporary files.
	 *
	 * @var list<string>
	 */
	private array $tmp = array();

	/**
	 * Course with two modules, three published single-choice questions each, and a learner grant.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course', 'Course' );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		for ( $m = 1; $m <= 2; $m++ ) {
			$module          = $this->post( 'ccap_module', 'Module ' . $m );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module, $m );
			for ( $i = 0; $i < 3; $i++ ) {
				$this->pool[ $module ][] = $this->question( $module, 'M' . $m . ' question ' . $i );
			}
		}
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
	 * @param string $type  Type.
	 * @param string $title Title.
	 * @return int
	 */
	private function post( string $type, string $title ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_author'  => $this->admin,
				'post_title'   => $title,
				'post_content' => 'Text',
			)
		);
	}

	/**
	 * Published single-choice question in a module pool.
	 *
	 * @param int    $module Module.
	 * @param string $text   Text.
	 * @param string $type   Type.
	 * @param array  $answers Answers.
	 * @return int
	 */
	private function question( int $module, string $text, string $type = QuestionTypes::SINGLE, array $answers = array() ): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$id    = $repo->create(
			$admin,
			array(
				'question_type' => $type,
				'question_text' => $text,
				'answers'       => array() !== $answers ? $answers : array(
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
		$repo->add_to_pool( $admin, $module, $id );
		return $id;
	}

	/**
	 * POST of a screen with a valid nonce.
	 *
	 * @param string               $action    admin-post action.
	 * @param string               $op        Operation.
	 * @param int                  $object_id Object.
	 * @param array<string, mixed> $data      Fields.
	 * @param int                  $user      User the nonce is made for.
	 * @return array<string, mixed>
	 */
	private function form( string $action, string $op, int $object_id, array $data, int $user = 0 ): array {
		wp_set_current_user( 0 === $user ? $this->admin : $user );
		return array(
			'op'       => $op,
			'object'   => $object_id,
			'_wpnonce' => wp_create_nonce( AdminUi::nonce_action( $action, $op, $object_id ) ),
		) + $data;
	}

	/**
	 * Audit entries of an action.
	 *
	 * @param string $action Action.
	 * @return int
	 */
	private static function audits( string $action ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s', $action ) );
	}

	/**
	 * Draft rule through the screen.
	 *
	 * @return int
	 */
	private function draft_rule(): int {
		$out = ExamsScreen::process(
			$this->form(
				ExamsScreen::ACTION,
				'create',
				0,
				array(
					'course'       => $this->course,
					'pass_percent' => '50',
					'version'      => 'Final',
				)
			),
			$this->admin
		);
		self::assertSame( 'ok', $out['result'] );
		return $out['rule'];
	}

	/**
	 * Fixed exam forms: mode choice, create, positions, freeze, retire, learner start.
	 */
	public function test_fixed_exam_forms(): void {
		$rule  = $this->draft_rule();
		$rules = ExamRules::current();
		$save  = array(
			'pass_percent'      => '50',
			'form_mode'         => 'variants',
			'time_mode'         => 'inherit',
			'attempts_mode'     => 'inherit',
			'shuffle_questions' => 'inherit',
			'feedback'          => 'inherit',
		);
		self::assertSame( 'ok', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'save', $rule, $save ), $this->admin )['result'] );
		self::assertSame( 'variants', ExamRules::form_mode( (array) $rules->get( $rule ) ) );
		self::assertContains( 'The exam uses fixed exam forms: freeze at least one exam form first.', ExamsScreen::publish_blockers( $rule ) );

		// Rights and nonce.
		self::assertSame( 'ccap_forbidden', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'variant_create', $rule, array(), $this->learner ), $this->learner )['result'] );
		$bad              = $this->form( ExamsScreen::ACTION, 'variant_create', $rule, array() );
		$bad['_wpnonce'] .= 'x';
		self::assertSame( 'ccap_invalid_nonce', ExamsScreen::process( $bad, $this->admin )['result'] );

		self::assertSame( 'ok', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'variant_create', $rule, array() ), $this->admin )['result'] );
		$variants = $rules->variants( $this->course );
		self::assertCount( 1, $variants );
		self::assertSame( 'draft', $variants[0]['status'] );
		$variant = $variants[0]['id'];

		$empty = ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'variant_freeze', $variant, array( 'rule' => $rule ) ), $this->admin );
		self::assertSame( 'ccap_validation_failed', $empty['result'], 'empty form cannot be frozen' );

		$q        = $this->pool;
		$position = array(
			$q[ $this->modules[1] ][0] => '1',
			$q[ $this->modules[0] ][2] => '2',
			$q[ $this->modules[0] ][0] => '',
		);
		self::assertSame( 'ccap_validation_failed', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'variant_save', $variant, array( 'rule' => $rule, 'position' => array( $q[ $this->modules[0] ][0] => 'x' ) ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'variant_save', $variant, array( 'rule' => $rule, 'position' => $position ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( array( $q[ $this->modules[1] ][0], $q[ $this->modules[0] ][2] ), $rules->variants( $this->course )[0]['questions'] );
		$foreign = $this->question( $this->post( 'ccap_module', 'Other' ), 'Not in the course' );
		$this->assertCode( 'ccap_validation_failed', fn() => $rules->set_variant_questions( Actor::user( $this->admin ), $variant, array( $foreign ) ) );

		// Detail page lists the form with the positions; accessible without JS.
		$page = ExamsScreen::html( array( 'rule' => $rule ), $this->admin );
		self::assertStringContainsString( 'Fixed exam forms', $page );
		self::assertStringContainsString( 'Exam form 1 (draft)', $page );
		self::assertSame( array(), A11yDom::fragment_problems( $page ) );

		self::assertSame( 'ok', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'variant_freeze', $variant, array( 'rule' => $rule ) ), $this->admin )['result'] );
		self::assertSame( 'ccap_variant_frozen', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'variant_save', $variant, array( 'rule' => $rule, 'position' => $position ) ), $this->admin )['result'], 'frozen form never changes' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 1, self::audits( 'exam_variant_frozen' ) );
		self::assertSame( array(), ExamsScreen::publish_blockers( $rule ), 'no blueprint needed in variant mode' );
		self::assertSame( 'ok', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'publish', $rule, array() ), $this->admin )['result'] );
		$page = ExamsScreen::html( array( 'rule' => $rule ), $this->admin );
		self::assertStringContainsString( 'How questions are chosen: fixed exam forms', $page );
		self::assertStringContainsString( 'M2 question 0', $page, 'questions of the frozen form are listed' );
		self::assertSame( array(), A11yDom::fragment_problems( $page ) );

		// Learner: the room offers the form; a start without a form is refused.
		$learner = Actor::user( $this->learner );
		$room    = ExamRoom::course_exams( $learner, $this->course );
		self::assertStringContainsString( 'Choose an exam form', $room );
		self::assertSame( array(), A11yDom::fragment_problems( $room ) );
		$this->assertCode( 'ccap_exam_not_startable', fn() => ExamSessions::current()->start( $learner, $rule, null, 'key-' . wp_generate_password( 8, false ) ) );
		wp_set_current_user( $this->learner );
		$session = ExamRoom::start(
			$learner,
			$this->course,
			array(
				'_wpnonce'    => wp_create_nonce( ExamRoom::NONCE ),
				'rule'        => $rule,
				'variant'     => $variant,
				'request_key' => 'key-' . wp_generate_password( 8, false ),
			)
		);
		self::assertIsInt( $session, 'start with the chosen form from the course room' );
		$db    = Database::current();
		$items = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d ORDER BY position', $session ) ) );
		self::assertSame( array( $q[ $this->modules[1] ][0], $q[ $this->modules[0] ][2] ), $items );

		// Retire: needs confirmation; afterwards no form is offered, the attempt stays.
		wp_set_current_user( $this->admin );
		self::assertSame( 'ccap_confirmation_required', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'variant_retire', $variant, array( 'rule' => $rule ) ), $this->admin )['result'] );
		self::assertSame( 'ok', ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'variant_retire', $variant, array( 'rule' => $rule, 'confirm' => '1' ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'retired', $rules->variants( $this->course )[0]['status'] );
		self::assertStringContainsString( 'no exam form is available', ExamRoom::course_exams( Actor::user( $this->admin ), $this->course ) );
		self::assertSame( 1, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) ) );
	}

	/**
	 * Random mode refuses variants; rules without a choice keep both ways; new version keeps the mode.
	 */
	public function test_form_mode_random_and_legacy(): void {
		$admin   = Actor::user( $this->admin );
		$rules   = ExamRules::current();
		$variant = $rules->create_variant( $admin, $this->course, 7, array( $this->pool[ $this->modules[0] ][0] ) );
		$legacy  = $rules->create( $admin, $this->course, 'legacy', array( 'min_ratio' => array( 'num' => 1, 'den' => 2 ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$rules->set_blueprint( $admin, $legacy, $this->modules[0], 1 );
		self::assertSame( '', ExamRules::form_mode( (array) $rules->get( $legacy ) ) );
		$rules->set_form_mode( $admin, $legacy, 'random' );
		$rules->publish( $admin, $legacy );
		$this->assertCode( 'ccap_rule_frozen', fn() => $rules->set_form_mode( $admin, $legacy, 'variants' ) );
		$this->assertCode( 'ccap_exam_not_startable', fn() => ExamSessions::current()->start( Actor::user( $this->learner ), $legacy, $variant, 'key-' . wp_generate_password( 8, false ) ) );
		self::assertGreaterThan( 0, ExamSessions::current()->start( Actor::user( $this->learner ), $legacy, null, 'key-' . wp_generate_password( 8, false ) ) );
		$copy = ExamsScreen::process( $this->form( ExamsScreen::ACTION, 'new_version', $legacy, array() ), $this->admin );
		self::assertSame( 'random', ExamRules::form_mode( (array) $rules->get( $copy['rule'] ) ), 'new version keeps the mode' );
	}

	/**
	 * Course export: download of a valid package, rights and options.
	 */
	public function test_course_export(): void {
		self::assertSame( 'ccap_forbidden', ExportArea::process( $this->form( ExportArea::ACTION, 'export', 0, array( 'courses' => array( $this->course ) ), $this->learner ), $this->learner )['result'] );
		self::assertSame( 'ccap_invalid_nonce', ExportArea::process( array( 'op' => 'export', 'object' => 0, '_wpnonce' => 'x' ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_export_no_course', ExportArea::process( $this->form( ExportArea::ACTION, 'export', 0, array() ), $this->admin )['result'] );
		self::assertSame( 'ccap_invalid_param', ExportArea::process( $this->form( ExportArea::ACTION, 'export', 0, array( 'courses' => array( $this->course ), 'per_file' => '9999' ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.

		$out = ExportArea::process(
			$this->form(
				ExportArea::ACTION,
				'export',
				0,
				array(
					'courses'  => array( $this->course ),
					'title'    => 'My export',
					'system'   => 'site-a',
					'per_file' => '2',
				)
			),
			$this->admin
		);
		self::assertSame( 'ok', $out['result'] );
		$this->tmp[] = $out['file'];
		self::assertFileExists( $out['file'] );
		self::assertStringStartsWith( 'my-export-', $out['name'] );
		self::assertSame( 6, $out['counts']['questions'] );
		$zip = new \ZipArchive();
		self::assertTrue( $zip->open( $out['file'] ) );
		$manifest = (array) json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
		self::assertSame( 'site-a', $manifest['source']['system'] );
		self::assertNotFalse( $zip->locateName( 'data/questions/000003.json' ), 'two records per file' );
		$zip->close();
		self::assertSame( 1, self::audits( 'course_exported' ) );

		// The package goes through the normal import check.
		$batch = ImportService::current()->upload( Actor::user( $this->admin ), $out['file'] );
		self::assertSame( 'validated', ImportService::current()->validate( Actor::user( $this->admin ), $batch )['status'] );

		$page = ImportScreen::html( array( 'area' => 'export', 'course' => $this->course ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertStringContainsString( 'Export and download', $page );
		self::assertStringContainsString( 'aria-current="page"', $page );
		self::assertSame( array(), A11yDom::fragment_problems( $page ) );
	}

	/**
	 * Package fixture as ZIP (one imported course, one question without rights).
	 *
	 * @param string $file   Target.
	 * @param string $system Source system.
	 * @param string $rights Rights status of the question ('' = none).
	 * @return string
	 */
	private function package( string $file, string $system, string $rights = '' ): string {
		$revision = array(
			'question_type' => 'single_choice',
			'text_html'     => 'Imported question',
			'settings'      => (object) array(),
		);
		if ( '' !== $rights ) {
			$revision['rights'] = array( 'status' => $rights );
		}
		$records     = array(
			'questions' => array(
				PackageExporter::sealed(
					array(
						'source_id' => 'Q-1',
						'revision'  => $revision,
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
				),
			),
			'modules'   => array(
				PackageExporter::sealed(
					array(
						'source_id'     => 'M-1',
						'title'         => 'Imported module',
						'summary'       => '',
						'lessons'       => array(),
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
						'slug'             => 'imported-course-' . $system,
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
		$zip         = PackageExporter::write( $records, $system, 'Fixture', $file );
		$this->tmp[] = $zip;
		return $zip;
	}

	/**
	 * Import from a link (SSRF guard, same test run) and from the server folder.
	 */
	public function test_import_by_link_and_folder(): void {
		$dir = get_temp_dir() . 'ccap-f8-' . wp_generate_password( 6, false, false );
		wp_mkdir_p( $dir );
		$zip       = $this->package( $dir . '/p.zip', 'fixture-f8' );
		$transport = static function ( string $url, string $ip, string $file, int $limit ) use ( $zip ): array {
			unset( $url, $ip, $limit );
			copy( $zip, $file );
			return array(
				'code'     => 200,
				'location' => null,
			);
		};
		$public    = new ImportSources( ImportService::current(), static fn( string $host ): array => '' === $host ? array() : array( '93.184.216.34' ), $transport );
		$private   = new ImportSources( ImportService::current(), static fn( string $host ): array => '' === $host ? array() : array( '10.0.0.5' ), $transport );

		self::assertSame( 'ccap_link_invalid', ImportScreen::process( $this->form( ImportScreen::ACTION, 'link', 0, array( 'url' => 'http://example.com/p.zip' ) ), $this->admin, '', UPLOAD_ERR_OK, $public )['result'] );
		self::assertSame( 'ccap_link_invalid', ImportScreen::process( $this->form( ImportScreen::ACTION, 'link', 0, array( 'url' => '' ) ), $this->admin, '', UPLOAD_ERR_OK, $public )['result'] );
		self::assertSame( 'ccap_link_blocked', ImportScreen::process( $this->form( ImportScreen::ACTION, 'link', 0, array( 'url' => 'https://intranet.example.com/p.zip' ) ), $this->admin, '', UPLOAD_ERR_OK, $private )['result'] );
		self::assertSame( 'ccap_link_blocked', ImportScreen::process( $this->form( ImportScreen::ACTION, 'link', 0, array( 'url' => 'https://127.0.0.1/p.zip' ) ), $this->admin, '', UPLOAD_ERR_OK, $public )['result'] );
		self::assertSame( 'ccap_link_checksum', ImportScreen::process( $this->form( ImportScreen::ACTION, 'link', 0, array( 'url' => 'https://example.com/p.zip', 'sha256' => str_repeat( 'a', 64 ) ) ), $this->admin, '', UPLOAD_ERR_OK, $public )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_forbidden', ImportScreen::process( $this->form( ImportScreen::ACTION, 'link', 0, array( 'url' => 'https://example.com/p.zip' ), $this->learner ), $this->learner, '', UPLOAD_ERR_OK, $public )['result'] );
		$link = ImportScreen::process( $this->form( ImportScreen::ACTION, 'link', 0, array( 'url' => 'https://example.com/p.zip', 'sha256' => (string) hash_file( 'sha256', $zip ) ) ), $this->admin, '', UPLOAD_ERR_OK, $public ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'previewed', $link['result'], 'link import ends in the same test run as an upload' );
		self::assertSame( 'previewed', ImportService::current()->get( $link['batch'] )['status'] );
		ImportService::current()->discard( Actor::user( $this->admin ), $link['batch'] );

		// Server folder: base name only, inside the drop folder.
		$drop = ImportSources::drop_dir();
		copy( $zip, $drop . '/f8.zip' );
		$this->tmp[] = $drop . '/f8.zip';
		$listing     = ImportScreen::html( array(), $this->admin );
		self::assertStringContainsString( 'f8.zip', $listing );
		self::assertStringContainsString( 'Import from a link', $listing );
		self::assertSame( array(), A11yDom::fragment_problems( $listing ) );
		self::assertSame( 'ccap_validation_failed', ImportScreen::process( $this->form( ImportScreen::ACTION, 'folder', 0, array( 'name' => '../f8.zip' ) ), $this->admin )['result'] );
		self::assertSame( 'ccap_not_found', ImportScreen::process( $this->form( ImportScreen::ACTION, 'folder', 0, array( 'name' => 'missing.zip' ) ), $this->admin )['result'] );
		$folder = ImportScreen::process( $this->form( ImportScreen::ACTION, 'folder', 0, array( 'name' => 'f8.zip' ) ), $this->admin );
		self::assertSame( 'previewed', $folder['result'] );
		self::assertFileDoesNotExist( $drop . '/f8.zip', 'taken over package leaves the folder' );
		foreach ( (array) glob( $dir . '/*' ) as $left ) {
			wp_delete_file( (string) $left );
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test cleanup.
	}

	/**
	 * Rights & release per course: summary, release with confirmation, course rights, single item.
	 */
	public function test_rights_and_release(): void {
		$dir = get_temp_dir() . 'ccap-f8r-' . wp_generate_password( 6, false, false );
		wp_mkdir_p( $dir );
		$zip   = $this->package( $dir . '/r.zip', 'fixture-f8r', 'unknown' );
		$admin = Actor::user( $this->admin );
		$up    = ImportScreen::process( $this->form( ImportScreen::ACTION, 'upload', 0, array() ), $this->admin, $zip );
		self::assertSame( 'previewed', $up['result'] );
		self::assertSame( 'imported', ImportScreen::process( $this->form( ImportScreen::ACTION, 'approve', $up['batch'], array() ), $this->admin )['result'] );
		$db       = Database::current();
		$imported = (int) $db->scalar( "SELECT object_id FROM `{$db->table( 'ccap_source_map' )}` WHERE source_system = 'fixture-f8r' AND object_type = 'course'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name.
		self::assertGreaterThan( 0, $imported );

		$rights  = Rights::current();
		$summary = $rights->course_summary( $imported );
		self::assertSame( 1, $summary['blocked'] );
		self::assertSame( 1, $summary['counts']['unknown']['foreign'] );
		self::assertSame( 0, $rights->course_summary( $this->course )['blocked'], 'own content is never blocked' );
		self::assertNotNull( $rights->package_rights( $imported ) );
		self::assertNull( $rights->package_rights( $this->course ) );

		$page = ImportScreen::html( array( 'area' => 'rights', 'course' => $imported ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertStringContainsString( 'Imported course. Source: fixture-f8r', $page );
		self::assertStringContainsString( 'not shown to learners', $page );
		self::assertStringContainsString( 'Release 1 item', $page );
		self::assertSame( array(), A11yDom::fragment_problems( $page ) );

		self::assertSame( 'ccap_forbidden', RightsArea::process( $this->form( RightsArea::ACTION, 'release', $imported, array( 'count' => '1', 'reason' => 'x' ), $this->learner ), $this->learner )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$stale = RightsArea::process( $this->form( RightsArea::ACTION, 'release', $imported, array( 'count' => '5', 'reason' => 'Licence bought' ) ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ccap_confirmation_required', $stale['result'] );
		self::assertSame( 1, $stale['count'] );
		self::assertSame( 'ccap_validation_failed', RightsArea::process( $this->form( RightsArea::ACTION, 'release', $imported, array( 'count' => '1', 'reason' => ' ' ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$done = RightsArea::process( $this->form( RightsArea::ACTION, 'release', $imported, array( 'count' => '1', 'reason' => 'Licence bought' ) ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'released', $done['result'] );
		self::assertSame( 0, $rights->course_summary( $imported )['blocked'] );
		self::assertSame( 1, self::audits( 'rights_course_release' ) );

		// Course rights metadata.
		$bad = RightsArea::process(
			$this->form(
				RightsArea::ACTION,
				'save_meta',
				$imported,
				array(
					'rights' => array(
						'status'      => 'licensed',
						'license_url' => 'http://example.com',
						'valid_until' => '2027-02-30',
					),
					'reason' => 'Contract',
				)
			),
			$this->admin
		);
		self::assertSame( 'ccap_validation_failed', $bad['result'] );
		self::assertSame( array( 'license_url', 'valid_until' ), array_keys( $bad['fields'] ) );
		$kept = ImportScreen::html( array( 'area' => 'rights', 'course' => $imported, 'ccap_result' => 'ccap_validation_failed' ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertStringContainsString( 'aria-invalid="true"', $kept );
		self::assertSame( array(), A11yDom::fragment_problems( $kept ) );
		$ok = RightsArea::process(
			$this->form(
				RightsArea::ACTION,
				'save_meta',
				$imported,
				array(
					'rights' => array(
						'status'      => 'licensed',
						'owner'       => 'Publisher Ltd',
						'license_url' => 'https://example.com/licence',
						'valid_until' => '2027-12-31',
					),
					'reason' => 'Contract 2026',
				)
			),
			$this->admin
		);
		self::assertSame( 'ok', $ok['result'] );
		self::assertSame( 'Publisher Ltd', Rights::course_meta( $imported )['owner'] );
		self::assertSame( 1, self::audits( 'course_rights_saved' ) );

		// Single item.
		$item = $rights->course_items( $imported )[0];
		self::assertSame( 'ccap_not_found', RightsArea::process( $this->form( RightsArea::ACTION, 'override', $imported, array( 'item' => $this->pool[ $this->modules[0] ][0], 'status' => 'restricted', 'reason' => 'x' ) ), $this->admin )['result'], 'only items of this course' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'ok', RightsArea::process( $this->form( RightsArea::ACTION, 'override', $imported, array( 'item' => $item['object_id'], 'type' => $item['type'], 'status' => 'restricted', 'reason' => 'Licence ended' ) ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 1, $rights->course_summary( $imported )['blocked'] );
		unset( $admin );
		foreach ( (array) glob( $dir . '/*' ) as $left ) {
			wp_delete_file( (string) $left );
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test cleanup.
	}

	/**
	 * Type change plan: mapping, losses, completeness.
	 */
	public function test_type_change_plan(): void {
		$multi  = array(
			'question_type' => QuestionTypes::MULTI,
			'question_text' => 'Which?',
			'settings'      => array( 'shuffle' => false ),
			'answers'       => array(
				array(
					'key'     => 'A',
					'text'    => 'One',
					'correct' => true,
					'sort'    => 0,
				),
				array(
					'key'     => 'B',
					'text'    => 'Two',
					'correct' => true,
					'sort'    => 1,
				),
				array(
					'key'     => 'C',
					'text'    => 'Three',
					'correct' => false,
					'sort'    => 2,
				),
			),
			'tags'          => array(),
		);
		$single = TypeChange::plan( $multi, QuestionTypes::SINGLE );
		self::assertSame( array( 'correct' ), $single['lost'] );
		self::assertTrue( $single['complete'] );
		self::assertSame( array( true, false, false ), array_column( $single['data']['answers'], 'correct' ) );

		$back = TypeChange::plan( $single['data'], QuestionTypes::MULTI );
		self::assertSame( array(), $back['lost'], 'single → multi loses nothing' );
		self::assertTrue( $back['complete'] );

		$open = TypeChange::plan( $multi, QuestionTypes::OPEN_TEXT );
		self::assertSame( "One\nTwo", $open['data']['answers'][0]['text'] );
		self::assertContains( 'options', $open['lost'] );
		self::assertTrue( $open['complete'] );

		$card = TypeChange::plan( $open['data'], QuestionTypes::FLASHCARD );
		self::assertSame( array(), $card['lost'] );
		self::assertSame( "One\nTwo", $card['data']['settings']['back'] );

		$tf = TypeChange::plan(
			array(
				'question_type' => QuestionTypes::SINGLE,
				'question_text' => 'Sky is blue?',
				'answers'       => array(
					array(
						'key'     => 'A',
						'text'    => 'True',
						'correct' => true,
					),
					array(
						'key'     => 'B',
						'text'    => 'False',
						'correct' => false,
					),
				),
			),
			QuestionTypes::TRUE_FALSE
		);
		self::assertTrue( $tf['complete'] );
		self::assertTrue( $tf['data']['answers'][0]['correct'] );

		$order = TypeChange::plan( $multi, QuestionTypes::ORDERING );
		self::assertSame( array( 'One', 'Two', 'Three' ), array_column( $order['data']['settings']['items'], 'text' ) );
		self::assertContains( 'correct', $order['lost'] );

		$num = TypeChange::plan(
			array(
				'question_type' => QuestionTypes::OPEN_TEXT,
				'question_text' => 'How much?',
				'answers'       => array(
					array(
						'key'     => 'MODEL',
						'text'    => '42 kg',
						'correct' => true,
					),
				),
			),
			QuestionTypes::NUMERIC
		);
		self::assertSame( '42', $num['data']['settings']['target'] );
		self::assertTrue( $num['complete'] );

		$cloze = TypeChange::plan( $multi, QuestionTypes::CLOZE );
		self::assertFalse( $cloze['complete'], 'needs gaps before publishing' );
		$this->expectException( \InvalidArgumentException::class );
		TypeChange::plan( $multi, QuestionTypes::MULTI );
	}

	/**
	 * Type change in the question bank: new revision, confirmation on loss, exam snapshots untouched.
	 */
	public function test_type_change_keeps_exam_snapshots(): void {
		$question = $this->question(
			$this->modules[0],
			'Pick the right ones',
			QuestionTypes::MULTI,
			array(
				array(
					'key'     => 'A',
					'text'    => 'One',
					'correct' => true,
				),
				array(
					'key'     => 'B',
					'text'    => 'Two',
					'correct' => true,
				),
				array(
					'key'  => 'C',
					'text' => 'Three',
				),
			)
		);
		$admin    = Actor::user( $this->admin );
		$rules    = ExamRules::current();
		$rule     = $rules->create( $admin, $this->course, 'snap', array( 'min_ratio' => array( 'num' => 1, 'den' => 2 ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$rules->set_blueprint( $admin, $rule, $this->modules[0], 4 );
		$rules->publish( $admin, $rule );
		$session = ExamSessions::current()->start( Actor::user( $this->learner ), $rule, null, 'key-' . wp_generate_password( 8, false ) );
		$db      = Database::current();
		$before  = $db->row( $db->prepare( 'SELECT revision_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d AND question_id = %d', $session, $question ) );
		self::assertNotNull( $before );
		$revision = (int) $before['revision_id'];
		$snapshot = (string) $db->scalar( $db->prepare( 'SELECT snapshot FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		$rev_row  = $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE id = %d', $revision ) );

		$page = QuestionsScreen::html( array( 'action' => 'edit', 'question' => $question, 'change_to' => QuestionTypes::SINGLE ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertStringContainsString( 'Lost in the new version: marking of the correct answers.', $page );
		self::assertSame( array(), A11yDom::fragment_problems( $page ) );

		self::assertSame( 'ccap_forbidden', QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, 'change_type', $question, array( 'to' => QuestionTypes::SINGLE ), $this->learner ), $this->learner )['result'] );
		self::assertSame( 'ccap_invalid_param', QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, 'change_type', $question, array( 'to' => QuestionTypes::MULTI ) ), $this->admin )['result'], 'same type' );
		self::assertSame( 'ccap_confirmation_required', QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, 'change_type', $question, array( 'to' => QuestionTypes::SINGLE ) ), $this->admin )['result'] );
		$out = QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, 'change_type', $question, array( 'to' => QuestionTypes::SINGLE, 'confirm_loss' => '1' ) ), $this->admin ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( 'type_changed', $out['result'] );
		self::assertSame( 1, self::audits( 'question_type_changed' ) );

		$latest = QuestionsScreen::latest_revision( $question );
		self::assertNotSame( $revision, $latest, 'new revision' );
		$new = (array) QuestionRepository::current()->revision( $latest );
		self::assertSame( QuestionTypes::SINGLE, $new['question_type'] );
		self::assertSame( 'draft', $new['editorial_status'] );
		self::assertSame( $rev_row, $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_question_revision' ) . '` WHERE id = %d', $revision ) ), 'published revision unchanged' );
		self::assertSame( $revision, (int) $db->scalar( $db->prepare( 'SELECT revision_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d AND question_id = %d', $session, $question ) ) );
		self::assertSame( $snapshot, (string) $db->scalar( $db->prepare( 'SELECT snapshot FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) ) );
		self::assertSame( $revision, (int) $db->scalar( $db->prepare( 'SELECT current_revision_id FROM `' . $db->table( 'ccap_question' ) . '` WHERE id = %d', $question ) ), 'published revision stays current until the new one is published' );
		self::assertSame( 'ok', QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, 'publish', $question, array() ), $this->admin )['result'] );
		self::assertSame( $revision, (int) $db->scalar( $db->prepare( 'SELECT revision_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d AND question_id = %d', $session, $question ) ), 'running attempt keeps its revision after publishing' );

		// Without loss no confirmation is needed (single → multi).
		self::assertSame( 'type_changed', QuestionsScreen::process( $this->form( QuestionsScreen::ACTION, 'change_type', $question, array( 'to' => QuestionTypes::MULTI ) ), $this->admin )['result'] );
	}

	/**
	 * Areas of the import page follow the caps (M-2); new audit actions are registered.
	 */
	public function test_areas_and_registry(): void {
		self::assertSame( array( 'packages', 'export', 'rights' ), array_keys( ImportScreen::areas( $this->admin ) ) );
		self::assertSame( array(), ImportScreen::areas( $this->learner ) );
		$exporter = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( $exporter )->add_cap( 'ccap_export_content' );
		self::assertSame( array( 'export' ), array_keys( ImportScreen::areas( $exporter ) ) );
		self::assertStringContainsString( 'There is no course you may export.', ImportScreen::html( array(), $exporter ) );
		foreach ( array( 'exam_variant_saved', 'exam_variant_frozen', 'exam_variant_retired', 'question_type_changed', 'course_exported', 'course_rights_saved', 'rights_course_release' ) as $action ) {
			self::assertArrayHasKey( $action, AuditView::registry() );
		}
		self::assertContains( Rights::COURSE_META, \CourseCaptain\Kernel\UninstallManifest::POST_META );
	}

	/**
	 * Expect a domain error code.
	 *
	 * @param string   $code Code.
	 * @param callable $call Call.
	 * @return void
	 */
	private function assertCode( string $code, callable $call ): void {
		try {
			$call();
			self::fail( 'Expected ' . $code );
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code );
		}
	}
}
