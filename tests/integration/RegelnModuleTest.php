<?php
/**
 * Business rules, module switches, drip plan, reports, uninstall word and
 * closed enrollment (SET-CC-13, SET-CC-24, SET-CC-25, SET-CC-26, UI-F2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\ContentGate;
use CourseCaptain\Access\GroupService;
use CourseCaptain\Access\SelfEnrollment;
use CourseCaptain\Activity\ActivityProgress;
use CourseCaptain\Activity\ActivityService;
use CourseCaptain\Activity\ExternalMedia;
use CourseCaptain\Admin\AdminUi;
use CourseCaptain\Admin\CertificatesScreen;
use CourseCaptain\Admin\ExamsScreen;
use CourseCaptain\Admin\LearnersScreen;
use CourseCaptain\Certificate\CertificateAdmin;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Privacy\Pseudonym;
use CourseCaptain\Progress\CourseReport;
use CourseCaptain\Publish\CourseValidator;
use CourseCaptain\Security\Hardening;
use CourseCaptain\Settings\BusinessRules;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Slice "regeln-module" of the settings analysis (23.11.2026).
 */
final class RegelnModuleTest extends SchemaTestCase {

	/**
	 * Administrator.
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
	 * Modules in course order.
	 *
	 * @var list<int>
	 */
	private array $modules = array();

	/**
	 * Lessons by kind (`lesson`, `assignment`, `flashcard`).
	 *
	 * @var array<string, int>
	 */
	private array $lessons = array();

	/**
	 * Course with a lesson, an assignment and a flashcard module (published).
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'display_name' => 'Rita Regel',
				'user_email'   => 'rita-' . wp_generate_password( 6, false ) . '@example.org',
			)
		);
		$this->course  = $this->post( 'ccap_course', 'C-Regeln' );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		foreach ( array( 'lesson', 'assignment', 'flashcard' ) as $kind ) {
			$module          = $this->post( 'ccap_module', 'M-' . $kind );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module );
			$lesson                 = $this->post( 'ccap_lesson', 'L-' . $kind, $module );
			$this->lessons[ $kind ] = $lesson;
			if ( 'assignment' === $kind ) {
				ActivityService::current()->define(
					$admin,
					$lesson,
					'assignment',
					array(
						'submission_type' => 'text',
						'max_points'      => 10,
						'passing_points'  => 6,
						'instructions'    => 'Describe the plan.',
					),
					array( 'type' => 'graded' )
				);
			} elseif ( 'flashcard' === $kind ) {
				ActivityService::current()->define( $admin, $lesson, 'flashcard', array( 'mode' => 'self_assessment' ), array( 'type' => 'all_known' ) );
			} else {
				ActivityService::current()->define( $admin, $lesson, 'lesson', array(), array( 'type' => 'manual_confirm' ) );
			}
		}
		CoursePublisher::current()->publish( $admin, $this->course );
	}

	/**
	 * Remove filters and the REST server.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'course_captain_business_rule' );
		remove_all_filters( 'course_captain_hardening_limits' );
		remove_all_filters( 'course_captain_trusted_media_hosts' );
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global reset for a fresh route registry.
		wp_set_current_user( 0 );
		ContentGate::set_now( null );
		parent::tear_down();
	}

	/**
	 * Published post.
	 *
	 * @param string $type      Post type.
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
	 * Save register values as the system (all-or-nothing validation).
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $values Values.
	 * @return void
	 */
	private static function set( string $tab, array $values ): void {
		Settings::save( Actor::cli(), $tab, $values, array_keys( $values ), Settings::rev( $tab ) );
	}

	/**
	 * Active manual grant of the learner.
	 *
	 * @return int
	 */
	private function grant(): int {
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
	 * Registered routes of a fresh REST server.
	 *
	 * @return list<string>
	 */
	private static function routes(): array {
		$GLOBALS['wp_rest_server'] = null; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core global reset for a fresh route registry.
		return array_keys( rest_get_server()->get_routes() );
	}

	/**
	 * SET-CC-13: without saved values every rule equals the former constant
	 * (no behaviour change after the update); `GroupService::INVITE_DAYS` is gone.
	 *
	 * @group SET-CC-13
	 */
	public function test_SET_CC_13_defaults_equal_former_constants(): void {
		self::assertSame( ExamSessions::START_LIMIT_HOUR, BusinessRules::exam_starts_per_hour() );
		self::assertSame( 40, BusinessRules::exam_starts_per_hour() );
		self::assertSame( Hardening::DEVICE_DAYS, BusinessRules::known_device_days() );
		self::assertSame( 30, BusinessRules::invite_valid_days() );
		self::assertFalse( defined( GroupService::class . '::INVITE_DAYS' ), 'constant replaced by enrollment.invite_valid_days' );
		self::assertSame( SmartTrainer::REVIEW_INTERVAL_DAYS, BusinessRules::review_intervals() );
		self::assertSame( array( 7, 21, 60 ), array( SmartTrainer::review_interval( 4 ), SmartTrainer::review_interval( 5 ), SmartTrainer::review_interval( 9 ) ) );
		self::assertSame( Hardening::LIMITS, Hardening::limits() );
		self::assertSame( array(), BusinessRules::trusted_media_hosts() );
		self::assertSame( array( 'cdn.other.test' ), ExternalMedia::foreign_hosts( array( 'https://cdn.other.test/a.mp4' ) ) );
		foreach ( array( 'exams.starts_per_hour', 'hardening.known_device_days', 'hardening.login_limits', 'learning.review_intervals', 'privacy.trusted_media_hosts', 'enrollment.invite_valid_days' ) as $key ) {
			list( $tab, $field ) = explode( '.', $key );
			self::assertArrayHasKey( $field, SettingsSchema::fields()[ $tab ], $key );
		}
	}

	/**
	 * SET-CC-13: saved values change the behaviour; the filters win (S2);
	 * invalid filter values are ignored; the register validates.
	 *
	 * @group SET-CC-13
	 */
	public function test_SET_CC_13_settings_change_behaviour_and_filter_wins(): void {
		self::set(
			'learning',
			array(
				'review_intervals' => array(
					'first'  => 3,
					'second' => 9,
					'third'  => 27,
				),
			)
		);
		self::assertSame( array( 3, 9, 27 ), array( SmartTrainer::review_interval( 4 ), SmartTrainer::review_interval( 5 ), SmartTrainer::review_interval( 6 ) ) );
		try {
			self::set(
				'learning',
				array(
					'review_intervals' => array(
						'first'  => 10,
						'second' => 5,
						'third'  => 20,
					),
				)
			);
			self::fail( 'decreasing intervals' );
		} catch ( CourseException $e ) {
			self::assertSame( 'interval_order', $e->data['fields']['review_intervals'] );
		}

		self::set( 'privacy', array( 'trusted_media_hosts' => array( 'Media.Example.org' ) ) );
		self::assertSame( array( 'media.example.org' ), Settings::get( 'privacy.trusted_media_hosts' ) );
		self::assertSame( array( 'cdn.other.test' ), ExternalMedia::foreign_hosts( array( 'https://media.example.org/x.mp4', 'https://cdn.other.test/y.mp4' ) ) );
		add_filter( 'course_captain_trusted_media_hosts', static fn( array $hosts ): array => array_merge( $hosts, array( 'cdn.other.test' ) ) );
		self::assertSame( array(), ExternalMedia::foreign_hosts( array( 'https://media.example.org/x.mp4', 'https://cdn.other.test/y.mp4' ) ), 'filter extends the setting' );
		try {
			self::set( 'privacy', array( 'trusted_media_hosts' => array( 'https://media.example.org/path' ) ) );
			self::fail( 'invalid host' );
		} catch ( CourseException $e ) {
			self::assertSame( 'host', $e->data['fields']['trusted_media_hosts'] );
		}

		self::set(
			'hardening',
			array(
				'login_limits'      => array(
					'ip'         => 3,
					'account_ip' => 2,
					'account'    => 20,
				),
				'known_device_days' => 30,
			)
		);
		self::assertSame( array( 3, 900 ), Hardening::limits()['login_ip'] );
		self::assertSame( array( 2, 900 ), Hardening::limits()['login_account_ip'] );
		self::assertSame( Hardening::LIMITS['reset_ip'], Hardening::limits()['reset_ip'], 'reset limits stay constants' );
		self::assertSame( 30, BusinessRules::known_device_days() );
		add_filter(
			'course_captain_hardening_limits',
			static function ( array $limits ): array {
				$limits['login_ip']      = array( 7, 60 );
				$limits['login_account'] = array( 0, 60 );
				return $limits;
			}
		);
		self::assertSame( array( 7, 60 ), Hardening::limits()['login_ip'], 'filter wins (incl. window)' );
		self::assertSame( array( 20, 900 ), Hardening::limits()['login_account'], 'invalid filter entry: setting stays (fail closed)' );

		add_filter( 'course_captain_business_rule', static fn( $value, string $key ) => 'exams.starts_per_hour' === $key ? 5 : $value, 10, 2 );
		self::assertSame( 5, BusinessRules::exam_starts_per_hour() );
		remove_all_filters( 'course_captain_business_rule' );
		add_filter( 'course_captain_business_rule', static fn( $value, string $key ) => 'exams.starts_per_hour' === $key ? 0 : $value, 10, 2 );
		self::assertSame( 40, BusinessRules::exam_starts_per_hour(), 'out-of-range filter value is ignored' );
		remove_all_filters( 'course_captain_business_rule' );

		// Invitations: validity from enrollment.invite_valid_days.
		self::set( 'enrollment', array( 'invite_valid_days' => 7 ) );
		$group = GroupService::current()->create( Actor::user( $this->admin ), $this->admin, 'G-Regeln', 3, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$seat  = GroupService::current()->invite( Actor::user( $this->admin ), $group, 'eingeladen@example.org' );
		self::assertIsArray( $seat );
		$db      = Database::current();
		$expires = (int) strtotime( (string) $db->scalar( $db->prepare( 'SELECT claim_expires_at FROM `' . $db->table( 'ccap_seat' ) . '` WHERE id = %d', $seat['seat_id'] ) ) . ' UTC' );
		self::assertEqualsWithDelta( time() + 7 * DAY_IN_SECONDS, $expires, 120 );
	}

	/**
	 * SET-CC-13: the register migration v1 → v2 keeps every stored value;
	 * new keys read their defaults; idempotent; fresh sites untouched.
	 *
	 * @group SET-CC-13
	 */
	public function test_SET_CC_13_migration_without_behaviour_change(): void {
		self::assertSame( array(), Settings::maybe_migrate(), 'fresh site: nothing stored, nothing to do' );
		update_option(
			Settings::OPTION_PREFIX . 'exams',
			array(
				'_schema'     => 1,
				'_rev'        => 4,
				'_updated_at' => '2026-11-01T00:00:00+00:00',
				'_updated_by' => 0,
				'values'      => array(
					'cooldown_seconds' => 60,
					'timer_mode'       => 'practice',
				),
			),
			false
		);
		update_option( Settings::VERSION_OPTION, 1, true );
		self::assertSame( array( 'exams' ), Settings::maybe_migrate() );
		$raw = get_option( Settings::OPTION_PREFIX . 'exams' );
		self::assertSame( SettingsSchema::VERSION, $raw['_schema'] );
		self::assertSame( 4, $raw['_rev'] );
		self::assertSame( 60, Settings::get( 'exams.cooldown_seconds' ) );
		self::assertSame( 'practice', Settings::get( 'exams.timer_mode' ) );
		self::assertSame( ExamSessions::START_LIMIT_HOUR, Settings::get( 'exams.starts_per_hour' ) );
		self::assertSame( SettingsSchema::VERSION, (int) get_option( Settings::VERSION_OPTION ) );
		self::assertSame( array(), Settings::maybe_migrate(), 'idempotent' );
	}

	/**
	 * SET-CC-24: switching modules off hides UI, REST and jobs and the
	 * course check ignores them; nothing is deleted; switching on restores.
	 *
	 * @group SET-CC-24
	 */
	public function test_SET_CC_24_module_switches(): void {
		$this->grant();
		$learner = Actor::user( $this->learner );
		wp_set_current_user( $this->learner );
		ActivityProgress::current()->interact( $learner, $this->course, $this->lessons['flashcard'], 'cards' );
		$db       = Database::current();
		$progress = fn(): int => (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_activity_progress' ) . '` WHERE activity_id = %d', $this->lessons['flashcard'] ) );
		$before   = $progress();
		self::assertGreaterThan( 0, $before );

		$routes = self::routes();
		self::assertContains( '/course-captain/v1/me/certificates', $routes );
		self::assertNotSame( array(), preg_grep( '#/assignments/#', $routes ) );
		self::assertArrayHasKey( 'grading', ExamsScreen::areas( $this->admin ) );
		$path = 'kurs/' . $this->uid() . '/lektion/' . $this->lessons['assignment'];
		self::assertStringContainsString( 'Describe the plan.', LearningArea::page( $learner, $path ) );

		// Broken flashcard settings: reported while the module is on.
		update_post_meta( $this->lessons['flashcard'], ActivityService::SETTINGS_META, wp_slash( (string) wp_json_encode( array( 'mode' => 'nonsense' ) ) ) );
		$codes = static function ( int $course, int $lesson ): array {
			$out = array();
			foreach ( CourseValidator::validate( $course )['issues'] as $issue ) {
				if ( (int) $issue['object']['id'] === $lesson ) {
					$out[] = (string) $issue['code'];
				}
			}
			return $out;
		};
		self::assertContains( 'VAL-025', $codes( $this->course, $this->lessons['flashcard'] ) );

		self::set(
			'learning',
			array(
				'flashcards_enabled'  => false,
				'assignments_enabled' => false,
			)
		);
		self::set( 'certificates', array( 'enabled' => false ) );

		$routes = self::routes();
		self::assertNotContains( '/course-captain/v1/me/certificates', $routes, 'REST hidden' );
		self::assertSame( array(), preg_grep( '#/assignments/#', $routes ) );
		self::assertContains( '/course-captain/v1/certificates/verify/(?P<token>[0-9a-zA-Z]{1,64})', $routes, 'issued certificates stay verifiable' );
		self::assertArrayNotHasKey( 'grading', ExamsScreen::areas( $this->admin ), 'grading UI hidden' );
		wp_set_current_user( $this->admin );
		self::assertStringContainsString( 'Certificates are switched off', CertificatesScreen::html( array(), $this->admin ) );
		self::assertSame( 'ccap_module_disabled', CertificatesScreen::save_template( array( '_wpnonce' => wp_create_nonce( CertificatesScreen::TEMPLATE_ACTION ) ), $this->admin ) );
		try {
			CertificateAdmin::export_csv( Actor::user( $this->admin ), array(), 'audit' );
			self::fail( 'export while off' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_module_disabled', $e->error_code );
		}
		wp_set_current_user( $this->learner );
		$page = LearningArea::page( $learner, $path );
		self::assertStringContainsString( 'This activity is not available on this website at the moment.', $page );
		self::assertStringNotContainsString( 'Describe the plan.', $page );
		self::assertSame( array(), A11yDom::document_problems( $page ) );
		try {
			ActivityProgress::current()->interact( $learner, $this->course, $this->lessons['flashcard'], 'cards' );
			self::fail( 'flashcards off' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_module_disabled', $e->error_code );
		}
		self::assertSame( array(), $codes( $this->course, $this->lessons['flashcard'] ), 'the course check ignores the module' );
		self::assertSame( $before, $progress(), 'no data deleted' );

		// Switching on again restores everything.
		self::set(
			'learning',
			array(
				'flashcards_enabled'  => true,
				'assignments_enabled' => true,
			)
		);
		self::set( 'certificates', array( 'enabled' => true ) );
		$routes = self::routes();
		self::assertContains( '/course-captain/v1/me/certificates', $routes );
		self::assertNotSame( array(), preg_grep( '#/assignments/#', $routes ) );
		self::assertArrayHasKey( 'grading', ExamsScreen::areas( $this->admin ) );
		self::assertStringContainsString( 'Describe the plan.', LearningArea::page( $learner, $path ) );
		self::assertContains( 'VAL-025', $codes( $this->course, $this->lessons['flashcard'] ) );
		self::assertSame( $before, $progress() );
	}

	/**
	 * SET-CC-25: global drip plan (`enrollment.default_drip_days`) read via
	 * EffectiveSettings, own module rule wins, started modules stay open.
	 *
	 * @group SET-CC-25
	 */
	public function test_SET_CC_25_default_drip_plan(): void {
		$this->grant();
		$learner = Actor::user( $this->learner );
		$check   = fn( int $module ) => ContentGate::current()->module( $learner, $this->course, $module );
		self::assertNull( Settings::get( 'enrollment.default_drip_days' ) );
		foreach ( $this->modules as $module ) {
			self::assertTrue( $check( $module )->allowed(), 'no plan: everything open (as before)' );
		}

		self::set( 'enrollment', array( 'default_drip_days' => 7 ) );
		$start = ContentGate::current()->access_start( $this->learner, $this->course );
		self::assertTrue( $check( $this->modules[0] )->allowed() );
		$second = $check( $this->modules[1] );
		self::assertSame( 'drip', $second->reason );
		self::assertSame( array( 'drip:default' ), $second->rule_refs );
		self::assertSame( gmdate( 'Y-m-d H:i:s', $start + 7 * DAY_IN_SECONDS ), $second->unlock_at );
		self::assertSame( gmdate( 'Y-m-d H:i:s', $start + 14 * DAY_IN_SECONDS ), $check( $this->modules[2] )->unlock_at );
		ContentGate::set_now( $start + 8 * DAY_IN_SECONDS );
		self::assertTrue( $check( $this->modules[1] )->allowed() );
		self::assertFalse( $check( $this->modules[2] )->allowed() );
		ContentGate::set_now( null );

		// Own drip rule of a module wins (0 days = open at once).
		Database::current()->insert(
			'ccap_access_rule',
			array(
				'course_id'   => $this->course,
				'module_id'   => $this->modules[2],
				'scope'       => 'view',
				'access_type' => 'drip',
				'limit_value' => 0,
			)
		);
		self::assertTrue( $check( $this->modules[2] )->allowed() );

		// Started module stays open (RV-14).
		ActivityProgress::current()->record_evidence( $this->learner, $this->course, $this->lessons['assignment'], array( 'submitted' => true ) );
		self::assertTrue( $check( $this->modules[1] )->allowed() );

		// Read through EffectiveSettings (site-wide key, source `global`).
		$resolved = \CourseCaptain\Settings\EffectiveSettings::resolve( 'enrollment.default_drip_days' );
		self::assertSame( array( 7, 'global' ), array( $resolved['value'], $resolved['source'] ) );
	}

	/**
	 * SET-CC-26: CSV columns (setting and per export), pseudonymised export
	 * with a stable pseudonym and without name, user ID or email; audit.
	 *
	 * @group SET-CC-26
	 */
	public function test_SET_CC_26_report_csv_and_pseudonyms(): void {
		$this->grant();
		ActivityProgress::current()->interact( Actor::user( $this->learner ), $this->course, $this->lessons['lesson'], 'confirm' );
		$admin  = Actor::user( $this->admin );
		$report = CourseReport::current();
		$lines  = static fn( string $csv ): array => explode( "\r\n", trim( substr( $csv, 3 ) ) );

		$full = $lines( $report->csv( $admin, $this->course ) );
		self::assertSame( implode( ',', CourseReport::CSV_COLUMNS ), $full[0], 'default: every column' );
		self::assertStringContainsString( '"Rita Regel"', $full[1] );
		self::assertStringNotContainsString( '@example.org', implode( "\n", $full ), 'never an email address' );

		$chosen = $lines( $report->csv( $admin, $this->course, array( 'status', 'completion_percent' ) ) );
		self::assertSame( 'status,completion_percent', $chosen[0] );
		try {
			$report->csv( $admin, $this->course, array( 'nope' ) );
			self::fail( 'no columns' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_validation_failed', $e->error_code );
		}

		$pseudo = $lines( $report->csv( $admin, $this->course, null, true ) );
		self::assertSame( 'pseudonym,' . implode( ',', array_diff( CourseReport::CSV_COLUMNS, CourseReport::IDENTIFYING ) ), $pseudo[0] );
		self::assertStringStartsWith( '"' . Pseudonym::of( $this->learner ) . '"', $pseudo[1] );
		self::assertStringNotContainsString( 'Rita', implode( "\n", $pseudo ) );
		self::assertMatchesRegularExpression( '/^P-[0-9a-f]{12}$/', Pseudonym::of( $this->learner ) );
		self::assertSame( Pseudonym::of( $this->learner ), Pseudonym::of( $this->learner ), 'stable' );
		self::assertNotSame( Pseudonym::of( $this->learner ), Pseudonym::of( $this->admin ) );

		self::set(
			'advanced',
			array(
				'report_csv_columns'  => array( 'display_name', 'status' ),
				'report_pseudonymize' => true,
			)
		);
		$forced = $lines( $report->csv( $admin, $this->course ) );
		self::assertSame( 'pseudonym,status', $forced[0], 'setting columns, identifying ones replaced' );
		wp_set_current_user( $this->admin );
		$cert = CertificateAdmin::export_csv( $admin, array(), 'Audit' );
		self::assertStringContainsString( ',pseudonym,', $cert );
		self::assertStringNotContainsString( ',holder,', $cert );

		$db    = Database::current();
		$audit = $db->rows( "SELECT context FROM `{$db->table( 'ccap_audit_log' )}` WHERE action = 'report_exported'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		self::assertCount( 4, $audit );
		self::assertStringContainsString( '"pseudonymous":true', (string) end( $audit )['context'] );

		// Admin download form and handler.
		$html = LearnersScreen::html( array( 'course' => $this->course ), $this->admin );
		self::assertStringContainsString( 'Download progress report', $html );
		self::assertSame( array(), A11yDom::fragment_problems( $html ) );
		self::assertSame( 'ccap_invalid_nonce', LearnersScreen::export( array( 'course' => $this->course ), $this->admin )['error'] );
		$out = LearnersScreen::export(
			array(
				'course'   => $this->course,
				'columns'  => array( 'status' ),
				'_wpnonce' => wp_create_nonce( LearnersScreen::EXPORT_ACTION . ':' . $this->course ),
			),
			$this->admin
		);
		self::assertSame( '', $out['error'] );
		self::assertSame( 'pseudonym,status', $lines( $out['csv'] )[0] );
		$none = LearnersScreen::export(
			array(
				'course'   => $this->course,
				'columns'  => array(),
				'_wpnonce' => wp_create_nonce( LearnersScreen::EXPORT_ACTION . ':' . $this->course ),
			),
			$this->admin
		);
		self::assertSame( 'pseudonym', $lines( $none['csv'] )[0], 'pseudonymised export keeps at least the pseudonym' );
		$learner = LearnersScreen::export(
			array(
				'course'   => $this->course,
				'_wpnonce' => wp_create_nonce( LearnersScreen::EXPORT_ACTION . ':' . $this->course ),
			),
			$this->learner
		);
		self::assertSame( 'ccap_forbidden', $learner['error'] );
	}

	/**
	 * UI-F2 / E-108: closed enrollment — no button and a clear message,
	 * REST refuses with `ccap_enrollment_closed`, existing access stays,
	 * reopening restores self-enrollment; audited.
	 *
	 * @group UI-F2
	 * @group E-108
	 */
	public function test_UI_F2_closed_enrollment(): void {
		self::set( 'enrollment', array( 'self_enrollment' => true ) );
		$learner = Actor::user( $this->learner );
		wp_set_current_user( $this->learner );
		self::assertStringContainsString( 'value="enroll"', LearningArea::page( $learner, 'kurs/' . $this->uid() ) );

		$post = array(
			'op'     => 'enrollment',
			'object' => $this->course,
			'course' => $this->course,
			'state'  => 'closed',
		);
		self::assertSame( 'ccap_forbidden', LearnersScreen::process( $post + array( '_wpnonce' => wp_create_nonce( AdminUi::nonce_action( LearnersScreen::ACTION, 'enrollment', $this->course ) ) ), $this->learner )['result'] );
		wp_set_current_user( $this->admin );
		$out = LearnersScreen::process( $post + array( '_wpnonce' => wp_create_nonce( AdminUi::nonce_action( LearnersScreen::ACTION, 'enrollment', $this->course ) ) ), $this->admin );
		self::assertSame( 'ok', $out['result'] );
		self::assertSame( 1, $out['count'] );
		self::assertTrue( SelfEnrollment::closed( $this->course ) );
		self::assertStringContainsString( 'Enrollment is closed', LearnersScreen::html( array( 'course' => $this->course ), $this->admin ) );

		wp_set_current_user( $this->learner );
		$room = LearningArea::page( $learner, 'kurs/' . $this->uid() );
		self::assertStringContainsString( 'Enrollment for this course is closed.', $room );
		self::assertStringNotContainsString( 'value="enroll"', $room );
		self::assertSame( array(), A11yDom::document_problems( $room ) );
		self::assertStringNotContainsString( 'Enroll in C-Regeln for free', LearningArea::render_catalog( array() ) );
		self::assertFalse( SelfEnrollment::current()->open( $this->course ) );
		$enroll   = array(
			'ccap_do'  => 'enroll',
			'_wpnonce' => wp_create_nonce( LearningArea::ENROLL_NONCE . ':' . $this->course ),
		);
		$location = LearningArea::handle_post( $learner, 'kurs/' . $this->uid(), $enroll );
		self::assertStringContainsString( 'ccap_status=ccap_enrollment_closed', $location );

		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$response = rest_do_request( new \WP_REST_Request( 'POST', '/course-captain/v1/courses/' . $this->uid() . '/enrollment' ) );
		self::assertSame( 403, $response->get_status() );
		self::assertSame( 'ccap_enrollment_closed', $response->get_data()['code'] );
		self::assertSame( 0, (int) Database::current()->scalar( Database::current()->prepare( 'SELECT COUNT(*) FROM `' . Database::current()->table( 'ccap_grant' ) . '` WHERE user_id = %d', $this->learner ) ) );

		// Reopen: the REST route enrolls.
		wp_set_current_user( $this->admin );
		self::assertTrue( SelfEnrollment::current()->set_closed( Actor::user( $this->admin ), $this->course, false ) );
		self::assertFalse( SelfEnrollment::current()->set_closed( Actor::user( $this->admin ), $this->course, false ), 'unchanged' );
		wp_set_current_user( $this->learner );
		PolicyService::flush();
		$response = rest_do_request( new \WP_REST_Request( 'POST', '/course-captain/v1/courses/' . $this->uid() . '/enrollment' ) );
		self::assertSame( 200, $response->get_status() );
		self::assertTrue( $response->get_data()['created'] );

		// Closing again keeps the existing access (E-108 stage 1).
		wp_set_current_user( $this->admin );
		SelfEnrollment::current()->set_closed( Actor::user( $this->admin ), $this->course, true );
		PolicyService::flush();
		self::assertTrue( SelfEnrollment::current()->enrolled( $learner, $this->course ) );
		self::assertFalse( SelfEnrollment::current()->enroll( $learner, $this->course )['created'], 'existing grant returned, no error' );

		$db      = Database::current();
		$actions = $db->wpdb()->get_col( "SELECT action FROM `{$db->table( 'ccap_audit_log' )}` WHERE action LIKE 'course_enrollment_%' ORDER BY id" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		self::assertSame( array( 'course_enrollment_closed', 'course_enrollment_opened', 'course_enrollment_closed' ), $actions );
	}
}
