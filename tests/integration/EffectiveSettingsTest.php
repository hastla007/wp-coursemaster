<?php
/**
 * R1-33: course/exam overrides and snapshot policy (R0-28 §5, INH-*).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\CourseSettingsPanel;
use CourseCaptain\Certificate\CertificateService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Settings\SettingsSchema;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-33
 */
final class EffectiveSettingsTest extends SchemaTestCase {

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
	 * Module with questions.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Course with one module of three questions; learner with grant.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = $this->post( 'ccap_course' );
		$this->module  = $this->post( 'ccap_module' );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module );
		$repo = QuestionRepository::current();
		for ( $i = 0; $i < 3; $i++ ) {
			$id = $repo->create(
				$admin,
				array(
					'question_type' => QuestionTypes::SINGLE,
					'question_text' => 'Q ' . $i,
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
	}

	/**
	 * Published post.
	 *
	 * @param string $type Type.
	 * @return int
	 */
	private function post( string $type ): int {
		return (int) wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => $type,
				'post_author' => $this->admin,
			)
		);
	}

	/**
	 * Save one global value.
	 *
	 * @param string $key   Register key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	private function set_global( string $key, $value ): void {
		list( $tab, $field ) = explode( '.', $key, 2 );
		Settings::save( Actor::user( $this->admin ), $tab, array( $field => $value ), array( $field ), Settings::rev( $tab ) );
	}

	/**
	 * Draft rule with one blueprint.
	 *
	 * @param array<string, mixed> $settings   Settings.
	 * @param int|null             $time_limit Seconds.
	 * @param bool                 $publish    Publish.
	 * @return int
	 */
	private function rule( array $settings = array(), ?int $time_limit = null, bool $publish = true ): int {
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
			$time_limit,
			$settings
		);
		$rules->set_blueprint( $admin, $rule, $this->module, 2 );
		if ( $publish ) {
			$rules->publish( $admin, $rule );
		}
		return $rule;
	}

	/**
	 * Start an attempt.
	 *
	 * @param int $rule Rule.
	 * @return array<string, mixed> Session row with decoded snapshot.
	 */
	private function start( int $rule ): array {
		$id              = ExamSessions::current()->start( Actor::user( $this->learner ), $rule, null, wp_generate_uuid4(), new Randomizer( '20300115' ) );
		$db              = Database::current();
		$row             = (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $id ) );
		$row['snapshot'] = json_decode( (string) $row['snapshot'], true );
		return $row;
	}

	/**
	 * Expect a CourseException code.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 */
	private function assertRejected( callable $call, string $code ): void {
		try {
			$call();
			self::fail( 'expected ' . $code );
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code );
		}
	}

	/**
	 * Audit actions.
	 *
	 * @param string $action Action.
	 * @return list<array<string, mixed>>
	 */
	private static function audit( string $action ): array {
		$db = Database::current();
		return $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s ORDER BY id', $action ) );
	}

	/**
	 * INH-001 / INH-002: every overridable key has class and lock flag;
	 * inherit, override, lock, resting per §1.3; `null` is a value.
	 *
	 * @group INH-001
	 * @group INH-002
	 */
	public function test_INH_001_002_resolver_table(): void {
		$fields = SettingsSchema::fields();
		foreach ( SettingsSchema::overridable() as $key => $meta ) {
			list( $tab, $field ) = explode( '.', $key, 2 );
			self::assertArrayHasKey( $field, $fields[ $tab ], $key . ' is a register key' );
			self::assertContains( $meta['class'], array( 'L', 'K', 'R', 'G', 'E', 'A' ) );
			self::assertIsBool( $meta['lockable'] );
			self::assertArrayHasKey( $key, EffectiveSettings::labels() );
		}
		self::assertArrayNotHasKey( 'general.course_slug', SettingsSchema::overridable(), 'global-only key' );
		self::assertFalse( SettingsSchema::overridable()['exams.shuffle_questions']['lockable'] );

		$inherit = EffectiveSettings::resolve( 'display.breadcrumbs', array( 'course' => array() ) );
		self::assertTrue( $inherit['value'] );
		self::assertSame( 'global', $inherit['source'] );
		$own = EffectiveSettings::resolve( 'display.breadcrumbs', array( 'course' => array( 'display.breadcrumbs' => false ) ) );
		self::assertFalse( $own['value'] );
		self::assertSame( 'course', $own['source'] );

		// Leaf merge: course visibility + activity show_correct_answers (INH-004).
		$leaf = EffectiveSettings::resolve(
			'learning.feedback',
			array(
				'course'   => array( 'learning.feedback' => array( 'visibility' => 'after_submit' ) ),
				'activity' => array( 'learning.feedback' => array( 'show_correct_answers' => false ) ),
			)
		);
		self::assertSame(
			array(
				'visibility'           => 'after_submit',
				'show_correct_answers' => false,
			),
			$leaf['value']
		);
		self::assertSame( 'activity', $leaf['source'] );

		// INH-002: explicit null (unlimited) vs inherit with global 3.
		$this->set_global( 'exams.max_attempts', 3 );
		self::assertSame( 3, EffectiveSettings::resolve( 'exams.max_attempts', array( 'rule' => array( 'overrides' => array() ) ) )['value'] );
		$null = EffectiveSettings::resolve( 'exams.max_attempts', array( 'rule' => array( 'overrides' => array( 'exams.max_attempts' => null ) ) ) );
		self::assertNull( $null['value'] );
		self::assertSame( 'exam_rule', $null['source'] );
		$rule   = $this->rule( array( 'overrides' => array( 'exams.max_attempts' => null ) ), null, false );
		$stored = json_decode( (string) Database::current()->scalar( Database::current()->prepare( 'SELECT settings FROM `' . Database::current()->table( 'ccap_exam_rule' ) . '` WHERE id = %d', $rule ) ), true );
		self::assertArrayHasKey( 'exams.max_attempts', $stored['overrides'] );
		self::assertNull( $stored['overrides']['exams.max_attempts'] );
		self::assertStringNotContainsString( 'inherit', (string) wp_json_encode( $stored ), 'no sentinel stored' );
		self::assertNull( ExamRules::current()->get( $rule )['settings']['attempt_policy']['max_attempts'] );

		// Schema default when the option lacks the key (§1.3 step 4).
		update_option( Settings::OPTION_PREFIX . 'display', array( 'values' => array() ) );
		self::assertSame( 'schema_default', EffectiveSettings::resolve( 'display.container_width' )['source'] );
	}

	/**
	 * INH-003 / INH-005: global-only key or exam key as course override
	 * rejected, nothing saved; author without M-03 and unknown course.
	 *
	 * @group INH-003
	 * @group INH-005
	 */
	public function test_INH_003_005_rejections(): void {
		$admin = Actor::user( $this->admin );
		$this->assertRejected(
			fn() => EffectiveSettings::save_course(
				$admin,
				$this->course,
				array(
					'display.breadcrumbs' => false,
					'general.course_slug' => 'x',
				)
			),
			'ccap_validation_failed'
		);
		self::assertSame( array(), EffectiveSettings::course_overrides( $this->course ), 'all or nothing' );
		$this->assertRejected( fn() => EffectiveSettings::save_course( $admin, $this->course, array( 'exams.max_attempts' => 2 ) ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => EffectiveSettings::save_course( $admin, $this->course, array( 'display.container_width' => 'huge' ) ), 'ccap_validation_failed' );
		$this->assertRejected( fn() => ExamRules::current()->create( $admin, $this->course, 'v9', array( 'min_ratio' => array( 'num' => 1, 'den' => 2 ) ), null, array( 'overrides' => array( 'display.breadcrumbs' => true ) ) ), 'ccap_validation_failed' ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound

		$stranger = Actor::user( (int) self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		try {
			EffectiveSettings::save_course( $stranger, $this->course, array( 'display.breadcrumbs' => false ) );
			self::fail( 'stranger' );
		} catch ( CourseException $e ) {
			self::assertContains( $e->status, array( 403, 404 ) );
		}
		$this->assertRejected( fn() => EffectiveSettings::save_course( $admin, 999999, array( 'display.breadcrumbs' => false ) ), 'ccap_not_found' );
		self::assertSame( array(), EffectiveSettings::course_overrides( $this->course ) );
	}

	/**
	 * INH-009 / INH-010 / INH-011: class L live without a new revision,
	 * class K frozen in the revision until the next publication; an
	 * override equal to the default stays an override.
	 *
	 * @group INH-009
	 * @group INH-010
	 * @group INH-011
	 */
	public function test_INH_009_010_011_live_and_revision(): void {
		$admin = Actor::user( $this->admin );
		$other = $this->post( 'ccap_course' );
		$this->set_global( 'display.breadcrumbs', false );
		EffectiveSettings::save_course( $admin, $other, array( 'display.breadcrumbs' => false ) );
		CoursePublisher::current()->publish( $admin, $other );
		CoursePublisher::current()->publish( $admin, $this->course );
		$this->set_global( 'display.breadcrumbs', true );
		self::assertTrue( EffectiveSettings::for_course( 'display.breadcrumbs', $this->course ), 'inheriting course follows at once' );
		self::assertFalse( EffectiveSettings::for_course( 'display.breadcrumbs', $other ), 'K-8: override equal to the old default stays' );

		// Class K.
		$revision = (int) CoursePublisher::current()->current_revision( $this->course )['id'];
		$this->set_global( 'learning.course_completion_default', 'required_modules_and_exam' );
		self::assertSame( 'required_modules', EffectiveSettings::for_course( 'learning.course_completion_default', $this->course ), 'published revision unchanged' );
		$panel = array_column( EffectiveSettings::course_panel( $this->course ), null, 'key' );
		self::assertSame( 'required_modules_and_exam', $panel['learning.course_completion_default']['value'], 'draft preview' );
		self::assertSame( 'required_modules', $panel['learning.course_completion_default']['published'] );
		self::assertSame( 'applies from the next publication', $panel['learning.course_completion_default']['applies'] );
		$next = CoursePublisher::current()->publish( $admin, $this->course );
		self::assertNotSame( $revision, $next, 'changed inherited K value → new revision' );
		self::assertSame( 'required_modules_and_exam', EffectiveSettings::for_course( 'learning.course_completion_default', $this->course ) );
		$frozen = EffectiveSettings::revision_settings( $this->course );
		self::assertSame( 'global', $frozen['resolved']['learning.course_completion_default']['src'] );

		// Course override takes effect with publication only.
		EffectiveSettings::save_course( $admin, $this->course, array( 'certificates.auto_issue' => true ) );
		self::assertFalse( EffectiveSettings::for_course( 'certificates.auto_issue', $this->course ), 'draft only' );
		CoursePublisher::current()->publish( $admin, $this->course );
		self::assertTrue( EffectiveSettings::for_course( 'certificates.auto_issue', $this->course ) );
		self::assertCount( 2, self::audit( 'course_settings_saved' ) );
	}

	/**
	 * INH-015 / INH-019 / INH-022 / INH-047: rule drafts inherit live,
	 * publication materializes `{v, src}`, running attempts keep their
	 * snapshot, history reads only the snapshot.
	 *
	 * @group INH-015
	 * @group INH-019
	 * @group INH-022
	 * @group INH-047
	 */
	public function test_INH_015_019_022_047_exam_rules(): void {
		$rules = ExamRules::current();
		$draft = $this->rule( array(), null, false );
		$this->set_global( 'exams.late_tolerance_seconds', 300 );
		$this->set_global( 'exams.cooldown_seconds', 60 );
		$preview = $rules->get( $draft );
		self::assertSame( 300, $preview['settings']['late_tolerance_seconds'], 'draft preview follows the global value' );
		self::assertSame( 'global', $preview['settings_provenance']['exams.late_tolerance_seconds']['src'] );
		$rules->publish( Actor::user( $this->admin ), $draft );
		$this->set_global( 'exams.late_tolerance_seconds', 30 );
		$published = $rules->get( $draft );
		self::assertSame( 300, $published['settings']['late_tolerance_seconds'], 'published rule frozen (E-227)' );
		self::assertSame( 60, $published['settings']['attempt_policy']['cooldown_seconds'] );
		self::assertSame( 'global', json_decode( (string) Database::current()->scalar( Database::current()->prepare( 'SELECT settings FROM `' . Database::current()->table( 'ccap_exam_rule' ) . '` WHERE id = %d', $draft ) ), true )['resolved']['exams.late_tolerance_seconds']['src'] );

		$session = $this->start( $draft );
		self::assertSame( 300, $session['snapshot']['timing']['late_tolerance_seconds'] );
		self::assertSame( 2, $session['snapshot']['format_version'] );
		self::assertSame( 'global', $session['snapshot']['settings_provenance']['exams.late_tolerance_seconds']['src'] );
		self::assertSame( 'site', $session['snapshot']['settings_provenance']['exams.late_tolerance_seconds']['ref'] );
		$this->set_global( 'exams.late_tolerance_seconds', 0 );
		$db    = Database::current();
		$again = json_decode( (string) $db->scalar( $db->prepare( 'SELECT snapshot FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session['id'] ) ), true );
		self::assertSame( 300, $again['timing']['late_tolerance_seconds'], 'running attempt unchanged (SN-2)' );
		self::assertSame( 300, ExamSessions::settings_provenance( $again )['exams.late_tolerance_seconds']['v'], 'history from the snapshot (SN-3)' );
		self::assertSame( 'unknown', ExamSessions::settings_provenance( array( 'format_version' => 1 ) )['exams.max_attempts']['src'], 'SN-4' );

		// INH-022: new version with an own limit; each snapshot carries its own policy.
		$v2 = $this->rule( array( 'attempt_policy' => array( 'max_attempts' => 5 ) ) );
		self::assertSame( 5, $rules->get( $v2 )['settings']['attempt_policy']['max_attempts'] );
		self::assertSame( 'exam_rule', $rules->get( $v2 )['settings_provenance']['exams.max_attempts']['src'] );
		self::assertSame( 'retired', $rules->get( $draft )['status'] );

		// Draft overrides: set and reset to inherit.
		$draft2 = $this->rule( array(), null, false );
		$rules->set_overrides( Actor::user( $this->admin ), $draft2, array( 'exams.shuffle_answers' => false ) );
		self::assertFalse( $rules->get( $draft2 )['settings']['shuffle_answers'] );
		$rules->set_overrides( Actor::user( $this->admin ), $draft2, array( 'exams.shuffle_answers' => EffectiveSettings::INHERIT ) );
		self::assertTrue( $rules->get( $draft2 )['settings']['shuffle_answers'] );
		$this->assertRejected( fn() => $rules->set_overrides( Actor::user( $this->admin ), $v2, array( 'exams.shuffle_answers' => false ) ), 'ccap_rule_frozen' );
		$panel = array_column( $rules->panel( $draft2 ), null, 'key' );
		self::assertSame( 'global', $panel['exams.shuffle_answers']['source'] );
		self::assertFalse( $panel['exams.shuffle_answers']['overridden'] );
	}

	/**
	 * INH-006 / INH-032 / INH-035 / INH-036 / INH-037: locks rest
	 * overrides from the next start, removal reactivates; K-2; preflight.
	 *
	 * @group INH-006
	 * @group INH-032
	 * @group INH-035
	 * @group INH-036
	 * @group INH-037
	 */
	public function test_INH_006_032_035_036_037_locks(): void {
		$admin = Actor::user( $this->admin );
		$rule  = $this->rule( array( 'late_tolerance_seconds' => 120 ) );
		$this->set_global( 'exams.late_tolerance_seconds', 0 );
		$manager = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->assertRejected( fn() => EffectiveSettings::lock( Actor::user( $manager ), 'exams.late_tolerance_seconds', 'x' ), 'ccap_forbidden' );
		$this->assertRejected( fn() => EffectiveSettings::lock( $admin, 'exams.shuffle_questions', 'x' ), 'ccap_validation_failed' );

		self::assertSame( 1, EffectiveSettings::lock( $admin, 'exams.late_tolerance_seconds', 'Exam regulations' ) );
		$locked = ExamRules::current()->get( $rule );
		self::assertSame( 0, $locked['settings']['late_tolerance_seconds'] );
		self::assertSame( 'global_lock', $locked['settings_provenance']['exams.late_tolerance_seconds']['src'] );
		$session = $this->start( $rule );
		self::assertSame( 0, $session['snapshot']['timing']['late_tolerance_seconds'], 'next start uses the lock (E-225)' );
		self::assertSame( 'global_lock', $session['snapshot']['settings_provenance']['exams.late_tolerance_seconds']['src'] );
		$audit = self::audit( 'settings_lock_set' );
		self::assertCount( 1, $audit );
		self::assertSame( 1, json_decode( (string) $audit[0]['context'], true )['resting'] );
		$this->assertRejected( fn() => $this->rule( array( 'late_tolerance_seconds' => 60 ) ), 'ccap_setting_locked' );

		self::assertSame( 1, EffectiveSettings::unlock( $admin, 'exams.late_tolerance_seconds' ) );
		self::assertSame( 120, ExamRules::current()->get( $rule )['settings']['late_tolerance_seconds'], 'override active again' );
		self::assertCount( 1, self::audit( 'settings_lock_removed' ) );

		// K-2: saved before the lock → rests; after the lock → refused, other keys saved.
		EffectiveSettings::save_course( $admin, $this->course, array( 'display.breadcrumbs' => false ) );
		EffectiveSettings::lock( $admin, 'display.breadcrumbs', '' );
		$r = EffectiveSettings::resolve( 'display.breadcrumbs', array( 'course' => EffectiveSettings::course_overrides( $this->course ) ) );
		self::assertTrue( $r['value'] );
		self::assertTrue( $r['resting'] );
		EffectiveSettings::lock( $admin, 'display.container_width', '' );
		$result = EffectiveSettings::save_course(
			$admin,
			$this->course,
			array(
				'display.container_width'    => 'wide',
				'learning.favorites_enabled' => false,
			)
		);
		self::assertSame( array( 'learning.favorites_enabled' ), $result['saved'] );
		self::assertSame( array( 'display.container_width' => 'ccap_setting_locked' ), $result['rejected'] );
		$this->assertRejected( fn() => EffectiveSettings::save_course( $admin, $this->course, array( 'display.container_width' => 'full' ) ), 'ccap_setting_locked' );
		self::assertArrayHasKey( 'display.breadcrumbs', EffectiveSettings::course_overrides( $this->course ), 'lock deletes no override' );
		$html = CourseSettingsPanel::html( $this->course );
		self::assertStringContainsString( 'aria-readonly="true"', $html );
		self::assertStringContainsString( 'rests while the website setting applies', $html );
		self::assertStringContainsString( '<legend>', $html );

		// INH-032: lock on practice with a published evidence rule.
		$this->set_global( 'exams.timer_mode', 'practice' );
		$this->assertRejected( fn() => EffectiveSettings::lock( $admin, 'exams.timer_mode', '' ), 'ccap_setting_lock_conflict' );
		self::assertArrayNotHasKey( 'exams.timer_mode', EffectiveSettings::locks(), 'no partial state' );
	}

	/**
	 * INH-016 / INH-017 / INH-018: accommodation over the time limit
	 * (rule > course), also over a lock; practice timer is a blocker.
	 *
	 * @group INH-016
	 * @group INH-017
	 * @group INH-018
	 */
	public function test_INH_016_017_018_time_limit(): void {
		$db = Database::current();
		foreach ( array(
			array( 'course', $this->course, 150 ),
		) as $acc ) {
			$db->insert(
				'ccap_accommodation',
				array(
					'user_id'         => $this->learner,
					'scope_type'      => $acc[0],
					'scope_id'        => $acc[1],
					'time_factor_pct' => $acc[2],
					'created_at'      => $db->now(),
				)
			);
		}
		$rule = $this->rule( array(), 3600 );
		$db->insert(
			'ccap_accommodation',
			array(
				'user_id'         => $this->learner,
				'scope_type'      => 'exam_rule',
				'scope_id'        => $rule,
				'time_factor_pct' => 200,
				'created_at'      => $db->now(),
			)
		);
		$session = $this->start( $rule );
		self::assertSame( 7200, strtotime( $session['deadline_at'] . ' UTC' ) - strtotime( $session['started_at'] . ' UTC' ), 'rule scope wins: 60 min × 200 %' );
		self::assertSame( 'exam_rule', $session['snapshot']['accommodation']['scope'] );
		self::assertSame( 60, $session['snapshot']['settings_provenance']['exams.time_limit_minutes']['v'] );
		ExamSessions::current()->cancel( Actor::user( $this->learner ), (int) $session['id'] );

		// INH-017: lock on 30 minutes, accommodation "no limit".
		$this->set_global( 'exams.time_limit_minutes', 30 );
		EffectiveSettings::lock( Actor::user( $this->admin ), 'exams.time_limit_minutes', '' );
		self::assertSame( 1800, ExamRules::current()->get( $rule )['time_limit_seconds'] );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_accommodation' ) . "` SET unlimited = 1, time_factor_pct = NULL WHERE scope_type = 'exam_rule' AND user_id = %d", $this->learner ) );
		$second = $this->start( $rule );
		self::assertNull( $second['deadline_at'], 'C-5: lock never removes the accommodation' );
		EffectiveSettings::unlock( Actor::user( $this->admin ), 'exams.time_limit_minutes' );

		// INH-018.
		$draft = $this->rule( array( 'timer_mode' => 'practice' ), null, false );
		$this->assertRejected( fn() => ExamRules::current()->publish( Actor::user( $this->admin ), $draft ), 'ccap_validation_failed' );
		$practice = $this->rule(
			array(
				'timer_mode' => 'practice',
				'purpose'    => 'practice',
			)
		);
		self::assertSame( 'published', ExamRules::current()->get( $practice )['status'] );
	}

	/**
	 * INH-027 / INH-028: `auto_issue` per course from the published
	 * revision; base template leaves per course, new template version.
	 *
	 * @group INH-027
	 * @group INH-028
	 */
	public function test_INH_027_028_certificates(): void {
		$admin = Actor::user( $this->admin );
		$this->set_global( 'certificates.base_template', array( 'issuer_name' => 'Site', 'footer_text' => 'Old' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$site = CertificateService::template();
		EffectiveSettings::save_course( $admin, $this->course, array( 'certificates.base_template' => array( 'issuer_name' => 'Academy' ) ) );
		CoursePublisher::current()->publish( $admin, $this->course );
		$course = CertificateService::template( $this->course );
		self::assertSame( 'Academy', $course['definition']['issuer_name'] );
		self::assertSame( 'Old', $course['definition']['footer_text'] );
		self::assertNotSame( $site['version'], $course['version'] );
		$this->set_global( 'certificates.base_template', array( 'issuer_name' => 'Site', 'footer_text' => 'New' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$after = CertificateService::template( $this->course );
		self::assertSame( 'Academy', $after['definition']['issuer_name'] );
		self::assertSame( 'New', $after['definition']['footer_text'] );
		self::assertGreaterThan( $course['version'], $after['version'] );
		self::assertSame( $after['version'], CertificateService::template( $this->course )['version'], 'same definition, same version' );

		// INH-027: global off, course on → decided per course at the event.
		self::assertFalse( Settings::get( 'certificates.auto_issue' ) );
		EffectiveSettings::save_course( $admin, $this->course, array( 'certificates.auto_issue' => true ) );
		CoursePublisher::current()->publish( $admin, $this->course );
		self::assertTrue( EffectiveSettings::for_course( 'certificates.auto_issue', $this->course ) );
		self::assertFalse( EffectiveSettings::for_course( 'certificates.auto_issue', $this->post( 'ccap_course' ) ) );
	}

	/**
	 * INH-030 / INH-033: monotonic keys (only shorter / only stricter).
	 *
	 * @group INH-030
	 * @group INH-033
	 */
	public function test_INH_030_033_monotonic(): void {
		self::assertTrue( EffectiveSettings::validate_override( 'enrollment.invite_valid_days', 7, 'group' )['ok'] );
		self::assertSame( 'ccap_setting_not_stricter', EffectiveSettings::validate_override( 'enrollment.invite_valid_days', 40, 'group' )['code'] );
		self::assertSame( 7, EffectiveSettings::resolve( 'enrollment.invite_valid_days', array( 'group' => array( 'enrollment.invite_valid_days' => 7 ) ) )['value'] );
		self::assertFalse( EffectiveSettings::validate_override( 'enrollment.invite_valid_days', 7, 'course' )['ok'], 'group scope only' );

		self::assertTrue( EffectiveSettings::validate_override( 'privacy.external_media_mode', 'click_to_load', 'media' )['ok'] );
		$this->set_global( 'privacy.external_media_mode', 'click_to_load' );
		self::assertTrue( EffectiveSettings::validate_override( 'privacy.external_media_mode', 'link_only', 'media' )['ok'] );
		self::assertFalse( EffectiveSettings::validate_override( 'privacy.external_media_mode', 'always_load', 'media' )['ok'] );
		$this->set_global( 'privacy.external_media_mode', 'link_only' );
		$r = EffectiveSettings::resolve( 'privacy.external_media_mode', array( 'media' => 'click_to_load' ) );
		self::assertSame( 'link_only', $r['value'], 'C-1: stricter global wins' );
		self::assertContains( 'stricter_global', $r['chain'] );
		self::assertTrue( $r['resting'] );
	}

	/**
	 * INH-042: reset to inherit with undo; the editor form maps modes.
	 *
	 * @group INH-042
	 */
	public function test_INH_042_reset_undo_and_form(): void {
		$admin   = Actor::user( $this->admin );
		$changes = CourseSettingsPanel::changes(
			$this->course,
			array(
				'mode'  => array(
					'display.breadcrumbs'            => 'set',
					'enrollment.default_access_days' => 'set',
					'general.catalog_indexing'       => 'set',
					'learning.favorites_enabled'     => 'inherit',
				),
				'value' => array(
					'display.breadcrumbs'            => '0',
					'enrollment.default_access_days' => '',
					'general.catalog_indexing'       => 'noindex',
				),
			)
		);
		self::assertArrayNotHasKey( 'learning.favorites_enabled', $changes, 'inherit without override is no change' );
		EffectiveSettings::save_course( $admin, $this->course, $changes );
		$overrides = EffectiveSettings::course_overrides( $this->course );
		self::assertFalse( $overrides['display.breadcrumbs'] );
		self::assertNull( $overrides['enrollment.default_access_days'], 'empty = explicit unlimited' );
		self::assertSame( 'noindex', get_post_meta( $this->course, '_ccap_seo_index', true ), 'catalog indexing uses the live SEO meta' );

		self::assertCount( 3, EffectiveSettings::reset_course( $admin, $this->course ) );
		self::assertSame( array(), EffectiveSettings::course_overrides( $this->course ) );
		self::assertSame( '', get_post_meta( $this->course, '_ccap_seo_index', true ) );
		self::assertCount( 1, self::audit( 'course_settings_reset' ) );
		self::assertTrue( EffectiveSettings::undo_course( $admin, $this->course ) );
		self::assertCount( 3, EffectiveSettings::course_overrides( $this->course ) );
		self::assertFalse( EffectiveSettings::undo_course( $admin, $this->course ), 'one generation' );
	}
}
