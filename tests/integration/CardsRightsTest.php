<?php
/**
 * Owner decisions D2 (flashcards never in exams, B-03) and D8 (rights
 * validator VAL-100…105, import of course/module rights, own exports).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\EditorScreens;
use CourseCaptain\Admin\RightsArea;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Import\ImportService;
use CourseCaptain\Import\PackageExporter;
use CourseCaptain\Import\PackageValidator;
use CourseCaptain\Import\Rights;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\RestoreCheck;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Publish\CourseValidator;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables.
 *
 * @group SET-CC-karten-rechte
 */
final class CardsRightsTest extends SchemaTestCase {

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
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Module with 3 questions and 2 flashcards.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Questions of the module.
	 *
	 * @var list<int>
	 */
	private array $questions = array();

	/**
	 * Flashcards of the module.
	 *
	 * @var list<int>
	 */
	private array $cards = array();

	/**
	 * Scratch directory.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Course with one required module (3 single choice + 2 flashcards).
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course', 'Cards course' );
		$this->module  = $this->post( 'ccap_module', 'Cards module' );
		PolicyService::flush();
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $this->module );
		for ( $i = 0; $i < 3; $i++ ) {
			$this->questions[] = $this->question( $this->module, QuestionTypes::SINGLE );
		}
		for ( $i = 0; $i < 2; $i++ ) {
			$this->cards[] = $this->question( $this->module, QuestionTypes::FLASHCARD );
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
		$this->dir = get_temp_dir() . 'ccap-kr-' . wp_generate_password( 6, false, false );
		wp_mkdir_p( $this->dir );
	}

	/**
	 * Remove scratch files.
	 */
	public function tear_down(): void {
		foreach ( (array) glob( $this->dir . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		if ( is_dir( $this->dir ) ) {
			rmdir( $this->dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Test cleanup.
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
				'post_title'   => $title,
				'post_content' => 'About',
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * Published question or flashcard in a pool.
	 *
	 * @param int    $module Module.
	 * @param string $type   Type.
	 * @return int
	 */
	private function question( int $module, string $type ): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$data  = QuestionTypes::FLASHCARD === $type
			? array(
				'question_type' => $type,
				'question_text' => 'Card ' . wp_generate_password( 6, false ),
				'settings'      => array( 'back' => 'Back' ),
			)
			: array(
				'question_type' => $type,
				'question_text' => 'Q ' . wp_generate_password( 6, false ),
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
			);
		$id    = $repo->create( $admin, $data );
		$repo->publish( $admin, $id );
		$repo->add_to_pool( $admin, $module, $id );
		return $id;
	}

	/**
	 * Published rule drawing `$count` questions from a module.
	 *
	 * @param int $module Module.
	 * @param int $count  Questions.
	 * @return int
	 */
	private function rule( int $module, int $count ): int {
		$rules = ExamRules::current();
		$admin = Actor::user( $this->admin );
		$rule  = $rules->create(
			$admin,
			$this->course,
			'v' . wp_generate_password( 4, false ),
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			600
		);
		$rules->set_blueprint( $admin, $rule, $module, $count );
		$rules->publish( $admin, $rule );
		return $rule;
	}

	/**
	 * Issues of a code.
	 *
	 * @param array<string, mixed> $report Report.
	 * @param string               $code   Code.
	 * @return list<array<string, mixed>>
	 */
	private static function issues( array $report, string $code ): array {
		return array_values( array_filter( $report['issues'], static fn( array $i ): bool => $code === $i['code'] ) );
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 * @return CourseException
	 */
	private function rejected( callable $call, string $code ): CourseException {
		try {
			$call();
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code );
			return $e;
		}
		self::fail( 'expected ' . $code );
	}

	/**
	 * D2: blueprint draws never contain flashcards; validator VAL-099.
	 */
	public function test_blueprint_never_draws_flashcards(): void {
		$rule     = $this->rule( $this->module, 3 );
		$sessions = ExamSessions::current();
		$db       = Database::current();
		foreach ( array( '20300115', '1', '42', '777', '98765' ) as $seed ) {
			$session = $sessions->start( Actor::user( $this->learner ), $rule, null, wp_generate_uuid4(), new Randomizer( $seed ) );
			$drawn   = array_map( 'intval', $db->wpdb()->get_col( $db->prepare( 'SELECT question_id FROM `' . $db->table( 'ccap_exam_session_question' ) . '` WHERE session_id = %d', $session ) ) );
			sort( $drawn );
			self::assertSame( $this->questions, $drawn, 'all three scorable questions, never a flashcard' );
			$sessions->cancel( Actor::user( $this->learner ), $session );
		}
		self::assertSame( array(), self::issues( CourseValidator::validate( $this->course ), 'VAL-099' ), 'enough scorable questions: no warning' );
		self::assertSame( 3, count( QuestionRepository::current()->pool_revisions( $this->module, true ) ) );
		self::assertSame( 5, count( QuestionRepository::current()->pool_revisions( $this->module ) ), 'flashcards stay in the pool' );

		// Too few scorable questions: warning VAL-099 next to the pool rule VAL-086.
		unset( $rule );
		$this->rule( $this->module, 5 );
		$report = CourseValidator::validate( $this->course );
		$found  = self::issues( $report, 'VAL-099' );
		self::assertCount( 1, $found );
		self::assertSame( 'warning', $found[0]['severity'] );
		self::assertSame( 'cc.val.exam.pool_flashcards', $found[0]['message_key'] );
		self::assertSame( 3, $found[0]['params']['available'] );
		self::assertSame( 2, $found[0]['params']['flashcards'] );
		self::assertSame( 3, self::issues( $report, 'VAL-086' )[0]['params']['available'], 'flashcards are not counted as available' );

		// Only flashcards in the pool.
		$cards_only = $this->post( 'ccap_module', 'Cards only' );
		CourseStructure::current()->assign_module( Actor::user( $this->admin ), $this->course, $cards_only );
		$this->question( $cards_only, QuestionTypes::FLASHCARD );
		$this->rule( $cards_only, 1 );
		$report = CourseValidator::validate( $this->course );
		self::assertContains( 'cc.val.exam.pool_only_flashcards', array_column( self::issues( $report, 'VAL-099' ), 'message_key' ) );
		self::assertContains( $cards_only, array_column( array_column( self::issues( $report, 'VAL-085' ), 'object' ), 'id' ), 'not startable: pool rule VAL-085' );
	}

	/**
	 * D2: fixed variants reject flashcards (create, edit, freeze, start, validator).
	 */
	public function test_variants_reject_flashcards(): void {
		$rules = ExamRules::current();
		$admin = Actor::user( $this->admin );
		self::assertSame( $this->questions, array_keys( ExamRules::variant_candidates( $this->course ) ), 'flashcards are not offered for a variant' );
		$this->rejected( fn() => $rules->create_variant( $admin, $this->course, 1, array( $this->questions[0], $this->cards[0] ) ), 'ccap_validation_failed' );
		$draft = $rules->create_variant_draft( $admin, $this->course );
		$this->rejected( fn() => $rules->set_variant_questions( $admin, $draft, array( $this->cards[1] ) ), 'ccap_validation_failed' );
		$rules->set_variant_questions( $admin, $draft, $this->questions );
		self::assertSame( array(), $rules->variant_blockers( $draft ) );

		// A card that reaches a variant anyway (older data, import, type change) blocks freeze, start and publish.
		$db = Database::current();
		$db->insert(
			'ccap_exam_variant_question',
			array(
				'variant_id'  => $draft,
				'question_id' => $this->cards[0],
				'position'    => 4,
			)
		);
		self::assertSame( array( 'flashcard' ), $rules->variant_blockers( $draft ) );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_variant' ) . "` SET status = 'published' WHERE id = %d", $draft ) );
		$found = self::issues( CourseValidator::validate( $this->course ), 'VAL-089' );
		self::assertSame( array( 'cc.val.variant.flashcard' ), array_column( $found, 'message_key' ) );
		self::assertSame( 'blocker', $found[0]['severity'] );

		$rule = $rules->create(
			$admin,
			$this->course,
			'fixed',
			array(
				'min_ratio' => array(
					'num' => 1,
					'den' => 2,
				),
			),
			600
		);
		$rules->set_blueprint( $admin, $rule, $this->module, 1 );
		$rules->set_form_mode( $admin, $rule, 'variants' );
		$rules->publish( $admin, $rule );
		$e = $this->rejected( fn() => ExamSessions::current()->start( Actor::user( $this->learner ), $rule, $draft, wp_generate_uuid4(), new Randomizer( '1' ) ), 'ccap_exam_not_startable' );
		self::assertSame( 'variant_flashcard', $e->getMessage() );
	}

	/**
	 * Media row linked to an object.
	 *
	 * @param string               $object_type Object type.
	 * @param int                  $object_id   Object.
	 * @param string               $system      Source system.
	 * @param string               $status      Rights status.
	 * @param array<string, mixed> $license     Licence fields.
	 * @return int
	 */
	private function media( string $object_type, int $object_id, string $system, string $status, array $license = array() ): int {
		$db    = Database::current();
		$media = $db->insert(
			'ccap_media',
			array(
				'uid'               => $db->uuid(),
				'source_system'     => $system,
				'source_id'         => 'local' === $system ? null : 'MED-' . wp_generate_password( 4, false ),
				'package_path'      => 'media/photo-' . $status . '.jpg',
				'media_type'        => 'image',
				'mime_type'         => 'image/jpeg',
				'sha256'            => hash( 'sha256', wp_generate_password( 12, false ) ),
				'alt_text'          => 'Photo',
				'rights_status'     => $status,
				'license_reference' => array() === $license ? null : (string) wp_json_encode( $license ),
			),
			array( 'created_at', 'updated_at' )
		);
		$db->insert(
			'ccap_content_media',
			array(
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'media_id'    => $media,
				'role'        => 'inline',
			)
		);
		return $media;
	}

	/**
	 * D8: VAL-100…VAL-105 with the jump target "Rights & release".
	 */
	public function test_rights_rules(): void {
		$admin  = Actor::user( $this->admin );
		$rights = Rights::current();
		self::assertSame( array(), array_values( array_filter( CourseValidator::validate( $this->course )['issues'], static fn( array $i ): bool => in_array( $i['code'], array( 'VAL-100', 'VAL-101', 'VAL-102', 'VAL-103', 'VAL-104', 'VAL-105' ), true ) ) ), 'own content without rights data: no finding' );

		// Own content: never blocked (E-18).
		$rights->override( $admin, 'question', $this->questions[0], 'restricted', 'Photo of a third party' );
		$rights->override( $admin, 'question', $this->questions[1], 'unknown', 'Unclear' );
		$rights->override( $admin, 'flashcard', $this->cards[0], 'review', 'Being checked' );
		self::assertSame( array(), $rights->set_course_meta( $admin, $this->course, array( 'status' => 'licensed', 'license' => 'CC-BY-SA-4.0' ), 'Licence documented' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$report = CourseValidator::validate( $this->course );
		self::assertSame( array( 'warning' ), array_column( self::issues( $report, 'VAL-102' ), 'severity' ) );
		self::assertSame( $this->questions[0], self::issues( $report, 'VAL-102' )[0]['object']['id'] );
		self::assertSame( array( 'notice' ), array_column( self::issues( $report, 'VAL-103' ), 'severity' ) );
		self::assertSame( array( 'flashcard' ), array_column( array_column( self::issues( $report, 'VAL-104' ), 'object' ), 'type' ) );
		$attribution = self::issues( $report, 'VAL-105' );
		self::assertSame( array( 'course' ), array_column( array_column( $attribution, 'object' ), 'type' ) );
		self::assertStringContainsString( 'CC-BY-SA-4.0', $attribution[0]['message'] );
		self::assertSame( array(), self::issues( $report, 'VAL-100' ) );
		foreach ( array( 'VAL-102', 'VAL-103', 'VAL-104', 'VAL-105' ) as $code ) {
			$target = self::issues( $report, $code )[0]['target'];
			self::assertSame( 'rights', $target['page'] );
			self::assertSame( RightsArea::url( $this->course ), $target['url'] );
		}
		self::assertSame( array(), $rights->set_course_meta( $admin, $this->course, array( 'status' => 'licensed', 'license' => 'CC-BY-SA-4.0', 'attribution' => 'Photo: A. Author' ), 'Attribution added' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame( array(), self::issues( CourseValidator::validate( $this->course ), 'VAL-105' ) );
		self::assertTrue( Rights::requires_attribution( 'cc by 4.0' ) );
		self::assertTrue( Rights::requires_attribution( 'DL-DE-BY-2.0' ) );
		self::assertFalse( Rights::requires_attribution( 'CC0-1.0' ) );

		// Foreign media: required path → VAL-100 blocker, optional module → VAL-101 warning, expired licence → hidden too.
		$lesson = (int) wp_insert_post(
			array(
				'post_type'    => 'ccap_lesson',
				'post_status'  => 'publish',
				'post_title'   => 'Harbour lesson',
				'post_parent'  => $this->module,
				'post_content' => 'Text',
			)
		);
		$this->media( 'lesson', $lesson, 'fixture-photos', 'unknown' );
		$this->media( 'course', $this->course, 'fixture-photos', 'licensed', array( 'valid_until' => '2001-01-01' ) );
		$optional = $this->post( 'ccap_module', 'Extra module' );
		CourseStructure::current()->assign_module( $admin, $this->course, $optional, 20, false );
		$this->media( 'module', $optional, 'fixture-photos', 'restricted' );
		$this->media( 'module', $optional, 'local', 'review' );
		$report = CourseValidator::validate( $this->course );
		$hidden = self::issues( $report, 'VAL-100' );
		self::assertCount( 1, $hidden, 'one issue with the count' );
		self::assertSame( 'blocker', $hidden[0]['severity'] );
		self::assertSame( 2, $hidden[0]['params']['count'] );
		self::assertStringContainsString( '2 imported items have unclear rights', $hidden[0]['message'] );
		self::assertSame( RightsArea::url( $this->course ), $hidden[0]['target']['url'] );
		$optional_issue = self::issues( $report, 'VAL-101' );
		self::assertSame( array( 'warning' ), array_column( $optional_issue, 'severity' ) );
		self::assertSame( 1, $optional_issue[0]['params']['count'] );
		self::assertStringContainsString( 'Extra module', $optional_issue[0]['message'] );
		self::assertCount( 2, self::issues( $report, 'VAL-104' ), 'own media in review is a warning too' );
		self::assertFalse( CourseValidator::publishable( $report ) );

		// Jump link: with the release right a link, otherwise "inform the course administration".
		wp_set_current_user( $this->admin );
		$panel = EditorScreens::validation_panel( $this->course );
		self::assertStringContainsString( esc_url( RightsArea::url( $this->course ) ), $panel );
		self::assertStringContainsString( 'Check rights', $panel );
		self::assertSame( array(), A11yDom::fragment_problems( $panel ) );
		$editor = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		self::assertStringContainsString( 'Please inform the course administration.', EditorScreens::validation_panel( $this->course ) );
		wp_set_current_user( 0 );
	}

	/**
	 * Package with a course (rights optional), one required module and a question.
	 *
	 * @param string                    $system        Source system.
	 * @param array<string, mixed>|null $course_rights Course rights.
	 * @param array<string, mixed>|null $defaults      `default_rights` of the manifest.
	 * @param string                    $title         Course title (changes the package).
	 * @return string
	 */
	private function package( string $system, ?array $course_rights, ?array $defaults = null, string $title = 'Imported course' ): string {
		$course = array(
			'source_id'        => 'C-1',
			'title'            => $title,
			'slug'             => 'imported-' . $system,
			'summary'          => '',
			'description_html' => '<p>About</p>',
			'modules'          => array(
				array(
					'module'   => 'M-1',
					'sort'     => 10,
					'required' => true,
				),
			),
		);
		if ( null !== $course_rights ) {
			$course['rights'] = $course_rights;
		}
		$records = array(
			'questions' => array(
				PackageExporter::sealed(
					array(
						'source_id' => 'Q-1',
						'revision'  => array(
							'question_type' => 'single_choice',
							'text_html'     => 'Imported question',
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
			'courses'   => array( PackageExporter::sealed( $course ) ),
		);
		$file    = PackageExporter::write( $records, $system, 'Fixture', $this->dir . '/' . $system . '-' . wp_generate_password( 4, false ) . '.zip' );
		if ( null !== $defaults ) {
			$zip = new \ZipArchive();
			$zip->open( $file );
			$manifest                   = (array) json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
			$manifest['default_rights'] = $defaults;
			$manifest['publisher']      = array( 'name' => 'Harbour Press' );
			$zip->addFromString( 'manifest.json', (string) wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			$zip->close();
		}
		return $file;
	}

	/**
	 * Run an import to the end.
	 *
	 * @param string $file Package.
	 * @return int Batch.
	 */
	private function import( string $file ): int {
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $file );
		self::assertSame( 'validated', $service->validate( $admin, $batch )['status'] );
		self::assertSame( 'previewed', $service->preview( $admin, $batch )['status'] );
		$service->approve( $admin, $batch );
		$service->run( $admin, $batch );
		return $batch;
	}

	/**
	 * Object of a source ID.
	 *
	 * @param string $system Source system.
	 * @param string $type   Type.
	 * @param string $source Source ID.
	 * @return int
	 */
	private static function object( string $system, string $type, string $source ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT object_id FROM `' . $db->table( 'ccap_source_map' ) . '` WHERE source_system = %s AND object_type = %s AND source_id = %s', $system, $type, $source ) );
	}

	/**
	 * D8: course/module `rights` and `default_rights` land in `_ccap_rights`; local rights win.
	 */
	public function test_import_course_and_module_rights(): void {
		$file = $this->package(
			'fixture-kr',
			array(
				'status'      => 'licensed',
				'owner'       => 'Harbour Press',
				'license'     => 'CC-BY-4.0',
				'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
				'attribution' => 'Harbour Press',
			),
			array( 'status' => 'own' )
		);
		self::assertTrue( PackageValidator::validate( $file )['ok'] );
		$this->import( $file );
		$course = self::object( 'fixture-kr', 'course', 'C-1' );
		$module = self::object( 'fixture-kr', 'module', 'M-1' );
		self::assertGreaterThan( 0, $course );
		self::assertSame(
			array(
				'status'      => 'licensed',
				'owner'       => 'Harbour Press',
				'license'     => 'CC-BY-4.0',
				'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
				'attribution' => 'Harbour Press',
			),
			Rights::course_meta( $course )
		);
		self::assertSame(
			array(
				'status' => 'licensed',
				'owner'  => 'Harbour Press',
			),
			Rights::course_meta( $module ),
			'module without rights: default_rights; foreign own becomes licensed with the publisher (E-62)'
		);
		self::assertSame( 'publish', get_post_status( $course ) );

		// Locally saved rights survive a re-import.
		self::assertSame( array(), Rights::current()->set_course_meta( Actor::user( $this->admin ), $course, array( 'status' => 'review' ), 'Checking the contract' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		$this->import( $this->package( 'fixture-kr', array( 'status' => 'public' ), null, 'Imported course v2' ) );
		self::assertSame( array( 'status' => 'review' ), Rights::course_meta( $course ) );
		self::assertSame( array(), Rights::course_meta( $module ), 'the new package has no default_rights: the imported default leaves the unchanged module too (SET-CC-rechte-auslieferung)' );

		// Foreign course with unclear rights: reported on activation, not held; publishing by hand blocks.
		$this->import( $this->package( 'fixture-kr2', array( 'status' => 'unknown' ) ) );
		$unclear = self::object( 'fixture-kr2', 'course', 'C-1' );
		self::assertSame( 'publish', get_post_status( $unclear ) );
		self::assertSame( array( 'course:' . $unclear ), self::issues( CourseValidator::validate( $unclear ), 'VAL-100' )[0]['params']['items'] );
		$db = Database::current();
		self::assertGreaterThan( 0, (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_import_issue' ) . "` WHERE rule = 'VAL-100'" ) );

		// Shape errors: invalid course rights → record error; invalid default_rights → manifest error.
		$bad     = $this->package( 'fixture-kr3', array( 'status' => 'mine' ) );
		$service = ImportService::current();
		$admin   = Actor::user( $this->admin );
		$batch   = $service->upload( $admin, $bad );
		$service->validate( $admin, $batch );
		$items = array_column( array_filter( $service->preview( $admin, $batch )['items'], static fn( array $i ): bool => null !== $i['rule'] ), 'rule', 'source_id' );
		self::assertSame( 'schema', $items['C-1'] );
		$service->discard( $admin, $batch );
		self::assertContains( 'V5_default_rights', array_column( PackageValidator::validate( $this->package( 'fixture-kr4', null, array( 'status' => 'own', 'license_url' => 'http://example.com' ) ) )['errors'], 'rule' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertFalse( Rights::valid_package_rights( array( 'status' => 'own', 'extra' => 'x' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertFalse( Rights::valid_package_rights( array( 'status' => 'own', 'valid_until' => '2026-13-40' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
	}

	/**
	 * D8: an own export carries `source.exported_from_site`; its re-import is own content.
	 */
	public function test_own_export_reimport_is_own_content(): void {
		delete_option( RestoreCheck::INSTALLATION_UUID );
		$admin   = Actor::user( $this->admin );
		$records = PackageExporter::records( $admin, array( $this->course ) );
		$file    = PackageExporter::write( $records, 'site-own', 'Own export', $this->dir . '/own.zip', PackageExporter::RECORDS_PER_FILE, array(), true );
		$check   = PackageValidator::validate( $file );
		self::assertTrue( $check['ok'] );
		$uuid = (string) get_option( RestoreCheck::INSTALLATION_UUID );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid );
		self::assertSame( $uuid, $check['manifest']['source']['exported_from_site'] );
		self::assertSame( $uuid, PackageExporter::installation_uuid(), 'stable' );
		self::assertArrayNotHasKey( 'exported_from_site', PackageValidator::validate( PackageExporter::write( $records, 'site-own', 'Fixture', $this->dir . '/plain.zip' ) )['manifest']['source'], 'only own exports' );

		// Re-import (new copy, since the source system is new on this site): own content, nothing blocked or licensed.
		$batch = $this->import( $file );
		self::assertSame( 'published', ImportService::current()->get( $batch )['status'] );
		$question = self::object( 'site-own', 'question', (string) $records['questions'][0]['source_id'] );
		self::assertGreaterThan( 0, $question );
		self::assertSame( 'own', Rights::current()->effective( 'question', $question ) );
		self::assertTrue( Rights::current()->own_system( 'site-own' ) );
		$copy = self::object( 'site-own', 'course', (string) $records['courses'][0]['source_id'] );
		self::assertSame( 0, Rights::current()->course_summary( $copy )['counts']['own']['foreign'] );

		// The same package from another site is foreign.
		update_option( RestoreCheck::INSTALLATION_UUID, wp_generate_uuid4() );
		self::assertFalse( Rights::current()->own_system( 'site-own' ) );
	}
}
