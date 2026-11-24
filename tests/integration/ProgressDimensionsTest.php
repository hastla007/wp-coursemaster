<?php
/**
 * Kap. 28 "Drei getrennte Fortschrittsdimensionen" (TODO Z. 710/Z. 668):
 * REST output and display of completion, mastery and competency
 * (R0-08 §5, E-73), slice SET-CC kompetenz.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\LearnersScreen;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Exam\ExamRules;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Progress\Competency;
use CourseCaptain\Progress\CourseProgress;
use CourseCaptain\Progress\ProgressDimensions;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables.
 *
 * @group K28-710
 * @group SET-CC-kompetenz
 */
final class ProgressDimensionsTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Learner with an evidenced objective.
	 *
	 * @var int
	 */
	private int $learner;

	/**
	 * Second learner of the same course.
	 *
	 * @var int
	 */
	private int $other;

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
	 * Questions of the first module.
	 *
	 * @var list<int>
	 */
	private array $questions = array();

	/**
	 * Course with two modules (a question of the first carries
	 * `competency: Navigation`), two learners with grants.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->course = $this->post( 'ccap_course' );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		foreach ( array( 3, 3 ) as $index => $count ) {
			$module          = $this->post( 'ccap_module' );
			$this->modules[] = $module;
			CourseStructure::current()->assign_module( $admin, $this->course, $module );
			for ( $i = 0; $i < $count; $i++ ) {
				$id = $this->question( $module, 0 === $index && 0 === $i );
				if ( 0 === $index ) {
					$this->questions[] = $id;
				}
			}
		}
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->other   = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->grant( $this->learner, $this->course );
		$this->grant( $this->other, $this->course );
	}

	/**
	 * Published post.
	 *
	 * @param string $type   Type.
	 * @param string $status Status.
	 * @return int
	 */
	private function post( string $type, string $status = 'publish' ): int {
		return (int) wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => $status,
				'post_title'  => $type,
				'post_author' => $this->admin,
			)
		);
	}

	/**
	 * Active manual grant.
	 *
	 * @param int $user   Learner.
	 * @param int $course Course.
	 */
	private function grant( int $user, int $course ): void {
		$db = Database::current();
		$db->insert(
			'ccap_grant',
			array(
				'uid'         => $db->uuid(),
				'user_id'     => $user,
				'course_id'   => $course,
				'status'      => 'active',
				'source_type' => 'manual',
				'source_id'   => $db->uuid(),
			),
			array( 'created_at', 'updated_at' )
		);
	}

	/**
	 * Staff assignment.
	 *
	 * @param int $user   User.
	 * @param int $course Course.
	 * @param int $group  Group (0 = whole course).
	 */
	private function staff( int $user, int $course, int $group = 0 ): void {
		Database::current()->insert(
			'ccap_course_staff',
			array(
				'course_id' => $course,
				'user_id'   => $user,
				'role'      => 'trainer',
				'group_id'  => $group,
			),
			array( 'created_at', 'updated_at' )
		);
		PolicyService::flush();
	}

	/**
	 * Published single-choice question (correct option A) in a pool.
	 *
	 * @param int  $module     Module.
	 * @param bool $navigation Tag `competency: Navigation`.
	 * @return int
	 */
	private function question( int $module, bool $navigation ): int {
		$repo  = QuestionRepository::current();
		$admin = Actor::user( $this->admin );
		$id    = $repo->create(
			$admin,
			array(
				'question_type' => QuestionTypes::SINGLE,
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
				'tags'          => $navigation ? array(
					array(
						'type'  => 'competency',
						'key'   => 'navigation',
						'label' => 'Navigation',
					),
				) : array(),
			)
		);
		$repo->publish( $admin, $id );
		$repo->add_to_pool( $admin, $module, $id );
		return $id;
	}

	/**
	 * Published rule (2 + 2 questions, pass mark 2/4).
	 *
	 * @param array<string, mixed> $settings Own rule settings.
	 * @return int
	 */
	private function rule( array $settings ): int {
		$rules = ExamRules::current();
		$admin = Actor::user( $this->admin );
		$rule  = $rules->create(
			$admin,
			$this->course,
			'v' . wp_generate_password( 4, false ),
			array(
				'min_ratio' => array(
					'num' => 2,
					'den' => 4,
				),
			),
			600,
			$settings
		);
		$rules->set_blueprint( $admin, $rule, $this->modules[0], 2 );
		$rules->set_blueprint( $admin, $rule, $this->modules[1], 2 );
		$rules->publish( $admin, $rule );
		return $rule;
	}

	/**
	 * Passed attempt of the learner (now).
	 *
	 * @param int $rule Rule.
	 * @return int Session.
	 */
	private function pass( int $rule ): int {
		$sessions = ExamSessions::current();
		$actor    = Actor::user( $this->learner );
		$session  = $sessions->start( $actor, $rule, null, wp_generate_uuid4(), new Randomizer( '20300115' ) );
		foreach ( range( 1, 4 ) as $position ) {
			$sessions->save_answer( $actor, $session, $position, array( 'A' ) );
		}
		self::assertTrue( $sessions->submit( $actor, $session )['passed'] );
		return $session;
	}

	/**
	 * REST call as a user.
	 *
	 * @param int                  $user  User (0 = guest).
	 * @param string               $path  Path below the namespace.
	 * @param array<string, mixed> $query Query parameters.
	 * @return \WP_REST_Response
	 */
	private function get( int $user, string $path, array $query = array() ): \WP_REST_Response {
		wp_set_current_user( $user );
		PolicyService::flush();
		$request = new \WP_REST_Request( 'GET', '/course-captain/v1' . $path );
		$request->set_query_params( $query );
		return rest_do_request( $request );
	}

	/**
	 * RFC 3339 of a UTC SQL time.
	 *
	 * @param string|null $sql SQL time.
	 * @return string|null
	 */
	private static function rfc( ?string $sql ): ?string {
		return null === $sql ? null : gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( $sql . ' UTC' ) );
	}

	/**
	 * Own route: no-store, values equal the three services, explanations
	 * for every dimension; the trainer route returns the same body.
	 */
	public function test_values_match_the_services(): void {
		$rule    = $this->rule(
			array(
				'certifies_objectives'     => array( 'obj-x' ),
				'competency_validity_days' => 365,
			)
		);
		$session = $this->pass( $rule );
		$db      = Database::current();
		$db->insert(
			'ccap_course_progress',
			array(
				'user_id'          => $this->learner,
				'course_id'        => $this->course,
				'status'           => 'in_progress',
				'completion_ratio' => '0.3000',
				'activities_done'  => 3,
				'activities_total' => 10,
				'last_activity_at' => '2026-11-20 08:00:00',
			),
			array( 'updated_at' )
		);
		$db->insert(
			'ccap_question_progress',
			array(
				'user_id'          => $this->learner,
				'question_id'      => $this->questions[1],
				'attempts'         => 4,
				'correct_count'    => 4,
				'streak'           => 4,
				'learn_status'     => 'mastered',
				'last_answered_at' => gmdate( 'Y-m-d H:i:s' ),
				'due_at'           => gmdate( 'Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS ),
			)
		);

		$response = $this->get( $this->learner, '/courses/' . $this->course . '/progress' );
		self::assertSame( 200, $response->get_status() );
		self::assertStringContainsString( 'no-store', (string) $response->get_headers()['Cache-Control'] );
		$body = $response->get_data();

		$completion = (array) CourseProgress::current()->get( $this->learner, $this->course );
		self::assertSame( 'in_progress', $body['completion']['status'] );
		self::assertSame( (int) floor( (float) $completion['completion_ratio'] * 100 ), $body['completion']['percent'] );
		self::assertSame( array( 3, 10 ), array( $body['completion']['done'], $body['completion']['total'] ) );
		self::assertSame( '2026-11-20T08:00:00Z', $body['completion']['last_activity_at'] );
		self::assertSame( '3 of 10 required lessons completed (' . \CourseCaptain\Kernel\Locale::percent( 30 ) . ').', $body['completion']['summary'] );

		$stages = SmartTrainer::current()->summary( Actor::user( $this->learner ), $this->course );
		unset( $stages['topics'] );
		self::assertSame( $stages, $body['mastery']['stages'] );
		self::assertSame( 1, $body['mastery']['stages']['mastered'] );
		self::assertTrue( $body['mastery']['has_data'] );
		self::assertSame( 'smarttrainer.v1', $body['mastery']['algorithm_version'] );

		$states = Competency::current()->states( $this->learner, $this->course, array( 'navigation', 'obj-x' ) );
		self::assertSame( array( 'navigation', 'obj-x' ), array_column( $body['competency']['objectives'], 'uid' ), 'known objectives: question tag + rule' );
		$navigation = $body['competency']['objectives'][0];
		self::assertSame( array( 'Navigation', 'not_evidenced', 'not evidenced' ), array( $navigation['title'], $navigation['state'], $navigation['state_label'] ) );
		self::assertArrayNotHasKey( 'source', $navigation, 'no estimated ratio' );
		$x   = $body['competency']['objectives'][1];
		$row = (array) $db->row( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_exam_session' ) . '` WHERE id = %d', $session ) );
		self::assertSame( 'evidenced', $x['state'] );
		self::assertSame( $states['obj-x']['source'], $x['source'] );
		self::assertSame( (string) $row['uid'], $x['source'] );
		self::assertSame( 'exam_session', $x['source_type'] );
		self::assertSame( $states['obj-x']['rule_version'], $x['rule_version'] );
		self::assertSame( self::rfc( $states['obj-x']['evaluated_at'] ), $x['evaluated_at'] );
		self::assertSame( self::rfc( $states['obj-x']['valid_until'] ), $x['valid_until'] );
		self::assertSame( array( 4000, 4000 ), array( $x['points_milli'], $x['max_points_milli'] ) );
		self::assertStringStartsWith( 'Evidenced by a passed exam (rule version ' . $states['obj-x']['rule_version'] . ')', $x['summary'] );
		self::assertStringContainsString( 'with 4 of 4 points. Valid until ', $x['summary'] );
		self::assertSame( array( 1, 2 ), array( $body['competency']['evidenced'], $body['competency']['total'] ) );
		foreach ( array( 'completion', 'mastery', 'competency' ) as $dimension ) {
			self::assertNotSame( '', $body[ $dimension ]['rule'], $dimension . ': rule text' );
			self::assertNotSame( '', $body[ $dimension ]['summary'], $dimension . ': value text' );
		}

		// Same read model for the trainer route.
		$report = $this->get( $this->admin, '/reports/courses/' . $this->course . '/learners/' . $this->learner . '/progress' );
		self::assertSame( 200, $report->get_status() );
		self::assertStringContainsString( 'no-store', (string) $report->get_headers()['Cache-Control'] );
		self::assertSame( $body, $report->get_data() );

		// Second learner: nothing evidenced, no mastery data ("no data yet", never 0 %).
		$other = $this->get( $this->other, '/courses/' . $this->course . '/progress' )->get_data();
		self::assertSame( array( 'not_evidenced', 'not_evidenced' ), array_column( $other['competency']['objectives'], 'state' ) );
		self::assertFalse( $other['mastery']['has_data'] );
		self::assertNull( $other['mastery']['percent'] );
		self::assertSame( 'No data yet: no practice question has been answered.', $other['mastery']['summary'] );
		self::assertSame( 'not_started', $other['completion']['status'] );

		// Course addressed by UID.
		$uid = ( new ObjectIdentity( $db ) )->ensure( get_post( $this->course ) );
		self::assertSame( $body, $this->get( $this->learner, '/courses/' . $uid . '/progress' )->get_data() );
	}

	/**
	 * RBAC/IDOR: own data only on the learner route; the report route needs
	 * M-11 (assigned trainer, site-wide staff); everything else is 404.
	 */
	public function test_rbac_and_idor(): void {
		$own    = '/courses/' . $this->course . '/progress';
		$report = static fn( int $course, int $user ): string => '/reports/courses/' . $course . '/learners/' . $user . '/progress';

		$guest = $this->get( 0, $own );
		self::assertSame( 401, $guest->get_status() );
		self::assertSame( 'ccap_auth_required', $guest->as_error()->get_error_code() );
		self::assertSame( 401, $this->get( 0, $report( $this->course, $this->learner ) )->get_status() );

		// The learner route has no user parameter: a foreign user ID is ignored.
		$foreign = $this->get( $this->other, $own, array( 'user' => $this->learner ) );
		self::assertSame( $this->other, $foreign->get_data()['user_id'] );

		// Learner → other learner over the report route: 404, not 403 (no oracle).
		$idor = $this->get( $this->other, $report( $this->course, $this->learner ) );
		self::assertSame( 404, $idor->get_status() );
		self::assertSame( 'ccap_not_found', $idor->as_error()->get_error_code() );
		self::assertSame( 200, $this->get( $this->learner, $report( $this->course, $this->learner ) )->get_status(), 'self over the report route' );

		// Trainer: assigned to the course → 200; another course → 404; group-limited without seat → 404.
		$trainer = (int) self::factory()->user->create( array( 'role' => 'ccap_trainer' ) );
		self::assertSame( 404, $this->get( $trainer, $report( $this->course, $this->learner ) )->get_status(), 'not assigned' );
		$second = $this->post( 'ccap_course' );
		$this->grant( $this->learner, $second );
		$this->staff( $trainer, $second );
		self::assertSame( 200, $this->get( $trainer, $report( $second, $this->learner ) )->get_status(), 'assigned to the second course' );
		self::assertSame( 404, $this->get( $trainer, $report( $this->course, $this->learner ) )->get_status(), 'assignment of another course does not count' );
		$grouped = (int) self::factory()->user->create( array( 'role' => 'ccap_trainer' ) );
		$this->staff( $grouped, $this->course, 999999 );
		self::assertSame( 404, $this->get( $grouped, $report( $this->course, $this->learner ) )->get_status(), 'group-limited, learner without seat' );
		$this->staff( $trainer, $this->course );
		self::assertSame( 200, $this->get( $trainer, $report( $this->course, $this->learner ) )->get_status() );

		// Site-wide staff; unknown learner, unknown and unpublished course.
		self::assertSame( 200, $this->get( $this->admin, $report( $this->course, $this->other ) )->get_status() );
		self::assertSame( 404, $this->get( $this->admin, $report( $this->course, 99999999 ) )->get_status() );
		self::assertSame( 404, $this->get( $this->admin, $report( 99999999, $this->learner ) )->get_status() );
		self::assertSame( 404, $this->get( $this->learner, '/courses/99999999/progress' )->get_status() );
		$draft = $this->post( 'ccap_course', 'draft' );
		self::assertSame( 404, $this->get( $this->learner, '/courses/' . $draft . '/progress' )->get_status(), 'unpublished course' );
		self::assertSame( 200, $this->get( $this->admin, $report( $draft, $this->learner ) )->get_status(), 'staff may look at a draft course' );

		// Read-only: no write method.
		wp_set_current_user( $this->learner );
		self::assertSame( 404, rest_do_request( new \WP_REST_Request( 'PUT', '/course-captain/v1' . $own ) )->get_status() );
	}

	/**
	 * Evidence stays frozen with its snapshot after the course changes: a new
	 * rule version names other objectives, the question tag and a module
	 * are removed – the REST output keeps source, version, date and validity.
	 * REV-025 (R0-23 RV-12, RV-17): the attempt passed under rule version 1
	 * stays evidence after version 2 is published, with the validity of the
	 * version 1 snapshot; version 2 is not evidenced by it.
	 *
	 * @group REV-025
	 */
	public function test_evidence_frozen_after_course_change(): void {
		$rule = $this->rule(
			array(
				'certifies_objectives'     => array( 'obj-x' ),
				'competency_validity_days' => 30,
			)
		);
		$this->pass( $rule );
		$path   = '/courses/' . $this->course . '/progress';
		$before = $this->get( $this->learner, $path )->get_data()['competency']['objectives'];
		$byuid  = array_column( $before, null, 'uid' );
		self::assertSame( 'evidenced', $byuid['obj-x']['state'] );

		$this->rule(
			array(
				'certifies_objectives'     => array( 'obj-y' ),
				'competency_validity_days' => 1,
			)
		);
		$admin = Actor::user( $this->admin );
		QuestionRepository::current()->archive( $admin, $this->questions[0] );
		CourseStructure::current()->remove_module( $admin, $this->course, $this->modules[1] );

		$after = array_column( $this->get( $this->learner, $path )->get_data()['competency']['objectives'], null, 'uid' );
		self::assertSame( $byuid['obj-x'], $after['obj-x'], 'evidence unchanged: source, rule version, date, points, validity' );
		self::assertSame( gmdate( 'Y-m-d\TH:i:s\Z', (int) strtotime( (string) $after['obj-x']['evaluated_at'] ) + 30 * DAY_IN_SECONDS ), $after['obj-x']['valid_until'], 'validity of the old rule version' );
		self::assertSame( 'not_evidenced', $after['obj-y']['state'], 'the new rule version is not evidenced by the old attempt' );
		self::assertArrayNotHasKey( 'navigation', $after, 'archived question no longer names the objective' );
	}

	/**
	 * Learning area course view and trainer view: three separate sections
	 * with rule texts, states as words; DOM checks (R0-15, A11yDom).
	 */
	public function test_ui_learner_room_and_trainer_view(): void {
		$rule = $this->rule( array( 'certifies_objectives' => array( 'obj-x' ) ) );
		$this->pass( $rule );
		$uid = ( new ObjectIdentity( Database::current() ) )->ensure( get_post( $this->course ) );
		wp_set_current_user( $this->learner );
		$page = LearningArea::page( Actor::user( $this->learner ), 'kurs/' . $uid );
		self::assertSame( array(), A11yDom::document_problems( $page ) );
		$xpath = A11yDom::xpath( $page );
		self::assertSame( 1, $xpath->query( '//section[@aria-labelledby="ccap-progress-heading"]/h2[.="Progress overview"]' )->length );
		$headings = $xpath->query( '//section[contains(@class,"ccap-dimensions")]//h3' );
		self::assertSame( array( 'Worked through', 'Mastered', 'Competency evidence' ), array_map( static fn( int $i ): string => trim( (string) $headings->item( $i )?->textContent ), array( 0, 1, 2 ) ) );
		self::assertSame( 3, $xpath->query( '//p[@class="ccap-dimension-rule"]/strong[.="Why this value:"]' )->length );
		self::assertSame( 'evidenced', trim( (string) $xpath->query( '//table[contains(@class,"ccap-objectives")]//tr[th[.="obj-x"]]/td[1]' )->item( 0 )?->textContent ), 'state as text' );
		self::assertSame( 'not evidenced', trim( (string) $xpath->query( '//table[contains(@class,"ccap-objectives")]//tr[th[.="Navigation"]]/td[1]' )->item( 0 )?->textContent ) );

		// No panel for a visitor without access.
		$visitor = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $visitor );
		self::assertStringNotContainsString( 'ccap-dimensions', LearningArea::page( Actor::user( $visitor ), 'kurs/' . $uid ) );

		// Trainer view per learner.
		wp_set_current_user( $this->admin );
		$screen = LearnersScreen::html(
			array(
				'course'  => $this->course,
				'learner' => $this->learner,
			),
			$this->admin
		);
		self::assertSame( array(), A11yDom::fragment_problems( $screen ) );
		$admin = A11yDom::xpath( $screen );
		self::assertSame( 1, $admin->query( '//h3[.="Progress overview"]' )->length );
		self::assertSame( 3, $admin->query( '//section[contains(@class,"ccap-dimensions")]//h4' )->length );
		self::assertStringContainsString( 'Evidenced by a passed exam', $screen );
		$expected = ProgressDimensions::current()->build( $this->learner, $this->course );
		self::assertStringContainsString( esc_html( $expected['mastery']['rule'] ), $screen );
	}
}
