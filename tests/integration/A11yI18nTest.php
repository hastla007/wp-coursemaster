<?php
/**
 * R1-19: multilingual UI and accessibility (R0-15 A11Y and I18N cases, R0-25 LOC cases).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\CertificatesScreen;
use CourseCaptain\Admin\CourseSettingsPanel;
use CourseCaptain\Admin\EditorScreens;
use CourseCaptain\Admin\GroupsScreen;
use CourseCaptain\Admin\RoleSettings;
use CourseCaptain\Admin\SettingsScreen;
use CourseCaptain\Admin\SetupState;
use CourseCaptain\Admin\SetupWizard;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Locale;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\ExamRoom;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Notification\MailTemplates;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Ops\AuditScreen;
use CourseCaptain\Ops\OpsScreen;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables.
 *
 * @group R1-19
 */
final class A11yI18nTest extends SchemaTestCase {

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
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Published exam rule (4 questions, 10 minutes).
	 *
	 * @var int
	 */
	private int $rule;

	/**
	 * Locales the locale switcher knew before the test.
	 *
	 * @var list<string>
	 */
	private array $available = array();

	/**
	 * Fixture: published course with two modules (lesson + 3 single-choice
	 * questions each), published exam drawing 2 + 2, learner with access.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$admin         = Actor::user( $this->admin );
		$this->course  = $this->post( 'ccap_course', 'Kurs A' );
		$modules       = array();
		foreach ( array( 'M1', 'M2' ) as $title ) {
			$module    = $this->post( 'ccap_module', $title );
			$modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module );
			$lesson = $this->post( 'ccap_lesson', 'L ' . $title, $module );
			ActivityService::current()->define( $admin, $lesson, 'lesson', array() );
			for ( $i = 0; $i < 3; $i++ ) {
				$repo = QuestionRepository::current();
				$id   = $repo->create(
					$admin,
					array(
						'question_type' => QuestionTypes::SINGLE,
						'question_text' => 'Q ' . $title . $i,
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
				$repo->add_to_pool( $admin, $module, $id );
			}
		}
		CoursePublisher::current()->publish( $admin, $this->course );
		$rules      = ExamRules::current();
		$this->rule = $rules->create(
			$admin,
			$this->course,
			'v1',
			array(
				'min_ratio' => array(
					'num' => 3,
					'den' => 4,
				),
			),
			600
		);
		$rules->set_blueprint( $admin, $this->rule, $modules[0], 2 );
		$rules->set_blueprint( $admin, $this->rule, $modules[1], 2 );
		$rules->publish( $admin, $this->rule );
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
		PolicyService::flush();
		$this->available = $this->switcher_locales();
	}

	/**
	 * Restore locale state and clocks.
	 */
	public function tear_down(): void {
		while ( is_locale_switched() ) {
			restore_previous_locale();
		}
		$this->set_switcher_locales( $this->available );
		$GLOBALS['wp_locale']->text_direction = 'ltr';
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
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_parent' => $parent_id,
				'post_author' => $this->admin,
			)
		);
	}

	/**
	 * Languages the locale switcher accepts (normally installed core packs).
	 *
	 * @return list<string>
	 */
	private function switcher_locales(): array {
		$property = new \ReflectionProperty( $GLOBALS['wp_locale_switcher'], 'available_languages' );
		return array_values( (array) $property->getValue( $GLOBALS['wp_locale_switcher'] ) );
	}

	/**
	 * Pretend core language packs are installed (the test install has none).
	 *
	 * @param array $locales Locales.
	 * @phpstan-param list<string> $locales
	 */
	private function set_switcher_locales( array $locales ): void {
		$property = new \ReflectionProperty( $GLOBALS['wp_locale_switcher'], 'available_languages' );
		$property->setValue( $GLOBALS['wp_locale_switcher'], $locales );
	}

	/**
	 * Install pretend core packs for some locales.
	 *
	 * @param array $locales Locales.
	 * @phpstan-param list<string> $locales
	 */
	private function allow_locales( array $locales ): void {
		$this->set_switcher_locales( array_values( array_unique( array_merge( $this->available, $locales ) ) ) );
	}

	/**
	 * Setting "learning area in profile language".
	 *
	 * @param bool $on On.
	 */
	private function profile_locale( bool $on ): void {
		Settings::save( Actor::user( $this->admin ), 'general', array( 'use_profile_locale' => $on ), array( 'use_profile_locale' ), Settings::rev( 'general' ) );
	}

	/**
	 * UID of the course.
	 *
	 * @return string
	 */
	private function uid(): string {
		return ( new ObjectIdentity( Database::current() ) )->ensure( get_post( $this->course ) );
	}

	/**
	 * Started exam session of the learner.
	 *
	 * @return int
	 */
	private function session(): int {
		return ExamSessions::current()->start( Actor::user( $this->learner ), $this->rule, null, wp_generate_uuid4() );
	}

	/**
	 * Write a rendered page for the browser check (tools/qa/e2e).
	 *
	 * @param string $name File name.
	 * @param string $html HTML.
	 */
	private static function dump( string $name, string $html ): void {
		$dir = (string) getenv( 'CCAP_DUMP_DIR' );
		if ( '' !== $dir ) {
			file_put_contents( $dir . '/' . $name, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Browser check (R1-19).
		}
	}

	/**
	 * LOC-003: text direction from BCP 47 tags and WordPress locales.
	 *
	 * @group LOC-003
	 */
	public function test_LOC_003_direction(): void {
		foreach ( array( 'ar', 'he-IL', 'fa', 'ur', 'ku-Arab', 'ar_EG' ) as $tag ) {
			self::assertSame( 'rtl', Locale::dir( $tag ), $tag );
		}
		foreach ( array( 'de-DE', 'sr-Latn', 'en', 'de_DE_formal', 'az-Latn' ) as $tag ) {
			self::assertSame( 'ltr', Locale::dir( $tag ), $tag );
		}
	}

	/**
	 * Bundled de_DE translation loads and translates; D-25-07 variant fallback (`de_AT` → `de_DE`), `en_*` and missing languages → source texts.
	 *
	 * @group LOC-012
	 * @group LOC-013
	 */
	public function test_de_DE_translation_and_variant_fallback(): void {
		self::assertFileExists( Locale::bundled_dir() . '/course-captain-de_DE.mo' );
		self::assertFileExists( Locale::bundled_dir() . '/course-captain-de_DE.po' );
		$this->allow_locales( array( 'de_DE', 'de_AT', 'fr_FR' ) );
		self::assertSame( 'de_DE', Locale::resolve( 'de_DE' ) );
		self::assertSame( 'de_DE', Locale::resolve( 'de_AT' ) );
		self::assertSame( 'de_DE', Locale::resolve( 'de_CH_informal' ) );
		self::assertSame( 'en_US', Locale::resolve( 'fr_FR' ), 'no French file: source texts' );
		self::assertSame( 'en_GB', Locale::resolve( 'en_GB' ) );

		self::assertTrue( switch_to_locale( 'de_DE' ) );
		self::assertSame( 'Zum Inhalt springen', __( 'Skip to content', 'course-captain' ) );
		self::assertSame( 'Bitte melde dich an.', __( 'Please log in.', 'course-captain' ) );
		/* translators: %s: number of unanswered questions */
		$plural = sprintf( _n( '%s question is unanswered.', '%s questions are unanswered.', 3, 'course-captain' ), number_format_i18n( 3 ) );
		self::assertSame( '3 Fragen sind unbeantwortet.', $plural, 'plural form' );
		self::assertSame( '1.234 %', Locale::percent( 1234 ), 'I18N-R-27 percent pattern' );
		restore_previous_locale();

		self::assertTrue( switch_to_locale( 'de_AT' ) );
		self::assertSame( 'Zum Inhalt springen', __( 'Skip to content', 'course-captain' ), 'de_AT uses the de_DE file' );
		self::assertSame( 'de-DE', Locale::ui_tag(), 'lang follows the resolved locale' );
		restore_previous_locale();

		self::assertTrue( switch_to_locale( 'fr_FR' ) );
		self::assertSame( 'Skip to content', __( 'Skip to content', 'course-captain' ) );
		self::assertSame( 'en-US', Locale::ui_tag() );
		restore_previous_locale();
		self::assertSame( 'Skip to content', __( 'Skip to content', 'course-captain' ) );
	}

	/**
	 * LOC-012 / I18N-011 / E-124: learning area in the profile language when the setting is on.
	 *
	 * @group LOC-012
	 * @group I18N-011
	 */
	public function test_LOC_012_learning_area_profile_language(): void {
		$this->allow_locales( array( 'de_DE', 'de_AT' ) );
		update_user_meta( $this->learner, 'locale', 'de_DE' );
		$actor = Actor::user( $this->learner );

		$this->profile_locale( true );
		$room = LearningArea::page( $actor, 'kurs/' . $this->uid() );
		self::assertStringContainsString( '<html lang="de-DE" dir="ltr">', $room );
		self::assertStringContainsString( 'Zum Inhalt springen', $room );
		self::assertStringContainsString( 'Prüfung starten', $room );
		self::assertSame( 'en_US', determine_locale(), 'locale restored after rendering' );

		update_user_meta( $this->learner, 'locale', 'de_AT' );
		self::assertStringContainsString( '<html lang="de-DE"', LearningArea::page( $actor, '' ), 'resolved locale, not the requested one' );

		$this->allow_locales( array( 'de_DE', 'de_AT', 'fr_FR' ) );
		update_user_meta( $this->learner, 'locale', 'fr_FR' );
		self::assertFalse( Locale::switch_to_user( $this->learner ), 'LOC-013: no French file → next link, the site language (en_US)' );
		self::assertStringContainsString( '<html lang="en-US"', LearningArea::page( $actor, '' ) );

		update_user_meta( $this->learner, 'locale', 'de_DE' );
		$this->profile_locale( false );
		$english = LearningArea::page( $actor, '' );
		self::assertStringContainsString( '<html lang="en-US" dir="ltr">', $english );
		self::assertStringContainsString( 'Skip to content', $english );
	}

	/**
	 * LOC-015: REST error text in the profile language with `_locale=user`; code unchanged.
	 *
	 * @group LOC-015
	 */
	public function test_LOC_015_rest_error_locale(): void {
		$this->allow_locales( array( 'de_DE' ) );
		update_user_meta( $this->learner, 'locale', 'de_DE' );
		wp_set_current_user( $this->learner );
		$request = new \WP_REST_Request( 'GET', '/course-captain/v1/exam-sessions/999999' );
		$serve   = static fn( \WP_REST_Request $r ) => apply_filters( 'rest_post_dispatch', rest_do_request( $r ), rest_get_server(), $r );
		$plain   = $serve( $request );
		$request->set_param( '_locale', 'user' );
		$german = $serve( $request );
		self::assertSame( 404, $german->get_status() );
		self::assertSame( $plain->get_data()['code'], $german->get_data()['code'], 'codes are never translated (I18N-R-08)' );
		self::assertSame( 'The requested item was not found.', $plain->get_data()['message'] );
		self::assertSame( 'Das angeforderte Element wurde nicht gefunden.', $german->get_data()['message'] );
		self::assertSame( 'Nicht gefunden', $german->get_data()['title'] );
		self::assertSame( 'en_US', determine_locale() );
	}

	/**
	 * I18N-013 / LOC-016 (works text): mails in the recipient's profile language, locale restored.
	 *
	 * @group I18N-013
	 */
	public function test_I18N_013_mail_in_recipient_locale(): void {
		$this->allow_locales( array( 'de_DE' ) );
		$german = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $german, 'locale', 'de_DE' );
		$service = NotificationService::current();
		$vars    = MailTemplates::site_vars() + array( 'recipient_display_name' => 'Lea' );
		$de      = $service->render( 'test_mail', 'de_DE', $german, $vars, true );
		$en      = $service->render( 'test_mail', 'en_US', $this->learner, $vars, true );
		self::assertStringStartsWith( 'Testnachricht von', $de['subject'] );
		self::assertStringContainsString( 'Hallo Lea', $de['text'] );
		self::assertStringContainsString( 'lang="de-DE"', $de['html'] );
		self::assertStringStartsWith( 'Test message from', $en['subject'] );
		self::assertSame( 'en_US', determine_locale(), 'restored after each recipient' );
	}

	/**
	 * I18N-007 / I18N-R-12 / LOC-005: RTL document direction, logical CSS only, content container with own direction.
	 *
	 * @group I18N-007
	 * @group LOC-005
	 */
	public function test_I18N_007_rtl(): void {
		$actor                                = Actor::user( $this->learner );
		$GLOBALS['wp_locale']->text_direction = 'rtl';
		$page                                 = LearningArea::page( $actor, 'kurs/' . $this->uid() );
		self::dump( 'rtl.html', $page );
		self::assertMatchesRegularExpression( '/<html lang="[^"]+" dir="rtl">/', $page );
		preg_match( '#<style>(.*?)</style>#s', $page, $css );
		self::assertSame( 0, preg_match( '/(?<![-\w])(margin|padding|border)-(left|right)|(?<![-\w])(left|right)\s*:|text-align\s*:\s*(left|right)|float\s*:/', $css[1] ), 'no physical properties (I18N-R-12)' );
		self::assertStringContainsString( 'inset-inline-start', $css[1] );
		$GLOBALS['wp_locale']->text_direction = 'ltr';

		update_post_meta( $this->course, CourseStructure::LANGUAGE_META, 'ar' );
		$room  = LearningArea::page( $actor, 'kurs/' . $this->uid() );
		$xpath = A11yDom::xpath( $room );
		self::assertSame( 'en-US', $xpath->query( '/html/@lang' )->item( 0 )?->nodeValue, 'UI stays LTR' );
		$h1 = $xpath->query( '//h1' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $h1 );
		self::assertSame( array( 'ar', 'rtl' ), array( $h1->getAttribute( 'lang' ), $h1->getAttribute( 'dir' ) ), 'course title carries the content language (A11Y-032)' );
		self::assertGreaterThan( 0, $xpath->query( '//h2[@lang="ar"][@dir="rtl"]' )->length, 'module titles' );
		self::assertSame( '', Locale::content_attributes( 'en-US' ), 'same language: no attributes' );
		self::assertSame( ' lang="en"', Locale::content_attributes( 'en' ), 'LOC-004: other language, same direction' );
	}

	/**
	 * A11Y-020/-022/-025/-071/-007/-032 and K-02/K-05: rendered learning area documents.
	 *
	 * @group A11Y-020
	 * @group A11Y-022
	 * @group A11Y-071
	 */
	public function test_A11Y_learning_area_documents(): void {
		$actor   = Actor::user( $this->learner );
		$session = $this->session();
		$pages   = array(
			'home.html' => LearningArea::page( $actor, '' ),
			'room.html' => LearningArea::page( $actor, 'kurs/' . $this->uid() ),
			'exam.html' => LearningArea::page( $actor, 'pruefung/' . $session ),
		);
		foreach ( $pages as $name => $html ) {
			self::dump( $name, $html );
			self::assertSame( array(), A11yDom::document_problems( $html ), $name );
			$xpath = A11yDom::xpath( $html );
			self::assertSame( 'ccap-skip', $xpath->query( '(//a|//button|//input|//select|//textarea)[1]/@class' )->item( 0 )?->nodeValue, $name . ': skip link first (K-02)' );
			self::assertMatchesRegularExpression( '/<html lang="[a-z]{2}-[A-Z]{2}" dir="(ltr|rtl)">/', $html, $name );
			self::assertSame( 1, $xpath->query( '//header' )->length, $name . ': banner landmark' );
			self::assertStringContainsString( ':focus-visible', $html, $name . ': visible focus (A11Y-003)' );
			self::assertStringContainsString( 'prefers-reduced-motion', $html, $name . ': A11Y-060' );
			self::assertSame( 0, $xpath->query( '//*[@aria-live="assertive"]' )->length, $name );
		}

		$exam  = A11yDom::xpath( $pages['exam.html'] );
		$timer = $exam->query( '//*[@role="timer"]' );
		self::assertSame( 1, $timer->length, 'A11Y-071 timer' );
		self::assertInstanceOf( \DOMElement::class, $timer->item( 0 ) );
		self::assertSame( 'off', $timer->item( 0 )->getAttribute( 'aria-live' ) );
		self::assertSame( 'ltr', $timer->item( 0 )->getAttribute( 'dir' ), 'I18N-R-13 clock stays LTR' );
		// The session starts in session() before the page is rendered; a second boundary in between shows 9:59.
		self::assertMatchesRegularExpression( '/^(10:00|9:5\d)$/', trim( (string) $timer->item( 0 )->textContent ), 'timer starts at the 10-minute limit' );
		self::assertStringContainsString( '10 minutes remaining.', $timer->item( 0 )->getAttribute( 'data-t600' ) );
		self::assertStringContainsString( '1 minute remaining.', $timer->item( 0 )->getAttribute( 'data-t60' ), '_n singular' );
		self::assertSame( 1, $exam->query( '//*[@role="status"][@aria-live="polite"][@id="ccap-exam-live"]' )->length, 'A11Y-025 one polite status region' );
		self::assertSame( 4, $exam->query( '//fieldset[legend]' )->length, 'A11Y-022 one fieldset per question' );
		$fieldset = $exam->query( '//fieldset' )->item( 0 );
		self::assertInstanceOf( \DOMElement::class, $fieldset );
		self::assertMatchesRegularExpression( '/^Question 1 of 4 Choose one answer\. Q M[12]\d$/', A11yDom::name( $exam, $fieldset ), 'name: number, hint, text' );
		self::assertSame( 8, $exam->query( '//fieldset//input[@type="radio"]' )->length );
		self::assertSame( 1, $exam->query( '//nav[@aria-label="Questions"]' )->length, 'A11Y-007 question navigation' );
		self::assertStringContainsString( 'Question 1 (open)', (string) $exam->query( '//nav[@aria-label="Questions"]//li[1]' )->item( 0 )?->textContent, 'state as text' );
		self::assertStringContainsString( '4 questions are unanswered. 0 questions are marked for review.', $pages['exam.html'], 'A11Y-074 confirmation names open questions' );
		self::assertSame( 1, $exam->query( '//button[@id="ccap-timer-announce"][@type="button"]' )->length, 'announce remaining time' );
		self::assertStringContainsString( '<script', $pages['exam.html'] );
	}

	/**
	 * A11Y-001/-009/-073/-074/-081 without JavaScript: save, confirmation, submit, result; expiry as alert.
	 *
	 * @group A11Y-073
	 * @group A11Y-074
	 */
	public function test_A11Y_exam_flow_without_javascript(): void {
		$actor   = Actor::user( $this->learner );
		$path    = 'kurs/' . $this->uid();
		$nonce   = static fn(): string => wp_create_nonce( ExamRoom::NONCE );
		$expired = $this->session();
		ExamSessions::current()->cancel( $actor, $expired );

		wp_set_current_user( $this->learner );
		self::assertStringContainsString( 'ccap_status=ccap_invalid_token', LearningArea::handle_post( $actor, $path, array( 'ccap_do' => 'start_exam' ) ) );
		$target = LearningArea::handle_post(
			$actor,
			$path,
			array(
				'ccap_do'     => 'start_exam',
				'rule'        => $this->rule,
				'request_key' => wp_generate_uuid4(),
				'_wpnonce'    => $nonce(),
			)
		);
		self::assertSame( 1, preg_match( '#pruefung(?:/|%2F)(\d+)$#', $target, $m ), $target );
		$session = (int) $m[1];
		self::assertStringContainsString( 'Continue exam', LearningArea::page( $actor, $path ) );

		$view    = ExamSessions::current()->view( $actor, $session );
		$answers = array();
		foreach ( $view['questions'] as $item ) {
			$answers[ $item['position'] ] = 'A';
		}
		$post = array(
			'_wpnonce' => $nonce(),
			'answer'   => $answers,
			'flag'     => array( 2 => '1' ),
			'ccap_do'  => 'save',
		);
		self::assertSame( 'saved', ExamRoom::handle( $actor, $session, $post ) );
		$page = LearningArea::page( $actor, 'pruefung/' . $session, 'saved' );
		self::assertStringContainsString( 'role="status" aria-live="polite">Your answers have been saved.</div>', $page );
		self::assertStringContainsString( 'Question 2 (answered, marked for review)', wp_strip_all_tags( $page ) );
		self::assertStringContainsString( '0 questions are unanswered. 1 question is marked for review.', $page );
		self::assertSame( 4, A11yDom::xpath( $page )->query( '//input[@type="radio"][@checked]' )->length, 'saved answers preselected' );

		self::assertSame( 'confirm_required', ExamRoom::handle( $actor, $session, array( 'ccap_do' => 'submit' ) + $post ) );
		self::assertStringContainsString( 'role="alert">Please confirm that you want to submit the exam.', LearningArea::page( $actor, 'pruefung/' . $session, 'confirm_required' ) );
		self::assertSame( 'ccap_invalid_token', ExamRoom::handle( $actor, $session, array( '_wpnonce' => 'x' ) + $post ) );
		self::assertSame(
			'submitted',
			ExamRoom::handle(
				$actor,
				$session,
				array(
					'ccap_do' => 'submit',
					'confirm' => '1',
				) + $post
			)
		);
		$result = LearningArea::page( $actor, 'pruefung/' . $session );
		self::assertSame( array(), A11yDom::document_problems( $result ) );
		self::assertStringContainsString( '<h1>Exam result</h1>', $result );
		self::assertStringContainsString( '<h2>Passed</h2>', $result );
		self::assertStringContainsString( '4 of 4 questions answered correctly.', $result );
		self::assertStringNotContainsString( 'role="alert"', $result );
		self::assertSame( 'ccap_exam_already_submitted', ExamRoom::handle( $actor, $session, $post ), 'K-05: catalog code, text from the translation' );
		self::assertStringContainsString( 'This attempt has already been submitted.', LearningArea::page( $actor, 'pruefung/' . $session . '/', 'ccap_exam_already_submitted' ) . LearningArea::page( $actor, $path, 'ccap_exam_already_submitted' ) );

		$late = $this->session();
		$db   = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_exam_session' ) . '` SET started_at = %s, deadline_at = %s WHERE id = %d', gmdate( 'Y-m-d H:i:s', time() - 4200 ), gmdate( 'Y-m-d H:i:s', time() - 3600 ), $late ) );
		$expired_page = LearningArea::page( $actor, 'pruefung/' . $late );
		self::assertStringContainsString( '<p role="alert">The time is up. Your answers have been submitted.</p>', $expired_page, 'A11Y-073' );
		self::assertSame( array(), A11yDom::document_problems( $expired_page ) );
		self::assertSame( 'Not passed', trim( (string) A11yDom::xpath( $expired_page )->query( '//h2' )->item( 0 )?->textContent ) );

		$stranger = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertStringContainsString( 'This course is not available.', LearningArea::page( $stranger, 'pruefung/' . $late ), 'foreign attempt' );
	}

	/**
	 * A11Y-020/-028/-080/-083 (server side): admin screens (incl. settings tabs, roles, setup steps) and blocks have labelled controls, named actions, table headers with scope, no positive tabindex.
	 *
	 * @group A11Y-028
	 * @group GM-141
	 */
	public function test_A11Y_admin_screens_and_blocks(): void {
		wp_set_current_user( $this->admin );
		$screens = array(
			'groups'       => GroupsScreen::html( array(), $this->admin ),
			'certificates' => CertificatesScreen::html( array(), $this->admin ),
			'template'     => CertificatesScreen::html( array( 'tab' => 'template' ), $this->admin ),
			'audit'        => AuditScreen::html( array() ),
			'audit-set'    => AuditScreen::html( array( 'area' => 'settings' ) ),
			'locks'        => \CourseCaptain\Admin\SettingLocksScreen::html( array(), $this->admin ),
			'backup'       => \CourseCaptain\Admin\SettingsBackup::html( array(), $this->admin ),
			'panel'        => CourseSettingsPanel::html( $this->course ),
			'catalog'      => LearningArea::render_catalog( array() ),
			'link'         => LearningArea::render_link( array() ),
		);
		foreach ( SettingsScreen::tabs_for( $this->admin ) as $tab ) {
			$screens[ 'settings-' . $tab ] = SettingsScreen::html( array( 'tab' => $tab ), $this->admin );
		}
		$screens['roles'] = RoleSettings::html( array(), $this->admin );
		foreach ( SetupState::STEPS as $step ) {
			$screens[ 'setup-' . $step ] = SetupWizard::html( array( 'step' => $step ), $this->admin );
		}
		foreach ( array( '', 'jobs', 'integrations', 'diagnostics' ) as $area ) {
			$screens[ 'ops-' . $area ] = OpsScreen::html( array( 'area' => $area ) );
		}
		ob_start();
		EditorScreens::render_structure( get_post( $this->course ) );
		EditorScreens::render_publish( get_post( $this->course ) );
		$screens['editor'] = (string) ob_get_clean();
		foreach ( $screens as $name => $html ) {
			self::assertNotSame( '', trim( $html ), $name );
			self::assertSame( array(), A11yDom::fragment_problems( $html ), $name );
		}
		self::assertStringContainsString( 'role="status"', $screens['editor'], 'A11Y-031 validation summary as status' );
		self::assertSame( 1, substr_count( $screens['setup-addresses'], 'aria-current="step"' ), 'A11Y-083 step list marks the current step' );

		// The checker itself finds the problems it claims to find.
		$bad = A11yDom::fragment_problems( '<input name="x"><button></button><a href="#"></a><span tabindex="2">x</span><img src="a.png"><table><tr><td>1</td></tr></table><div aria-describedby="nope"></div>' );
		self::assertCount( 7, $bad, implode( "\n", $bad ) );
		self::assertContains( 'not exactly one main landmark', A11yDom::document_problems( '<html><head><title>x</title></head><body><h1>x</h1><h3>y</h3></body></html>' ) );
		self::assertContains( 'heading level skipped: h1 -> h3', A11yDom::document_problems( '<html lang="en"><head><title>x</title></head><body><main><h1>x</h1><h3>y</h3></main></body></html>' ) );
	}
}
