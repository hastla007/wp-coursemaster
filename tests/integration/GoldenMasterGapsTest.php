<?php
/**
 * R1-18 follow-up: Golden-Master gaps GM-051 (server part), GM-061, GM-093,
 * GM-146, GM-147, GM-148 and finding F-4 on `cc-fixture-neutral-v1`.
 * The browser cases GM-050/GM-051 are in tools/qa/e2e/exam-room.js.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\ObjectIdentity;
use CourseCaptain\Exam\ExamSessions;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\ExamRoom;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Learning\SmartTrainer;
use CourseCaptain\Ops\HealthChecks;
use CourseCaptain\Ops\Monitor;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Decision;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Question\Randomizer;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Tests\Support\Fixtures\NeutralFixture;

/**
 * Each test loads the fixture itself (idempotent, D-03).
 *
 * @group R1-18
 */
final class GoldenMasterGapsTest extends SchemaTestCase {

	/**
	 * Loaded fixture.
	 *
	 * @var array{users: array<string, int>, courses: array<string, int>, modules: array<string, int>, rules: array<string, int>, variants: array<string, int>, media: array<string, int>, pages: array<string, int>, groups: array<string, int>}
	 */
	private array $fx;

	/**
	 * Load the fixture.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$dir = get_temp_dir() . 'ccap-fx-' . wp_generate_password( 6, false );
		wp_mkdir_p( $dir );
		$this->fx = NeutralFixture::load( $dir );
		NeutralFixture::release_rights( $this->fx );
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
	}

	/**
	 * Reset clocks and overrides.
	 */
	public function tear_down(): void {
		SmartTrainer::set_now( null );
		Monitor::set_now( null );
		Monitor::with_key( null );
		remove_all_filters( 'course_captain_can_access' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Expect a CourseException with a code; returns it.
	 *
	 * @param callable $call Call.
	 * @param string   $code Code.
	 * @return CourseException
	 */
	private function rejected( callable $call, string $code ): CourseException {
		try {
			$call();
		} catch ( CourseException $e ) {
			self::assertSame( $code, $e->error_code, $e->getMessage() );
			return $e;
		}
		self::fail( 'expected ' . $code );
	}

	/**
	 * REST call incl. `rest_post_dispatch`.
	 *
	 * @param string                $method  Method.
	 * @param string                $path    Path below the namespace.
	 * @param array<string, mixed>  $params  Parameters.
	 * @param array<string, string> $headers Headers.
	 * @return \WP_REST_Response
	 */
	private static function rest( string $method, string $path, array $params = array(), array $headers = array() ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/course-captain/v1' . $path );
		foreach ( $params as $name => $value ) {
			$request->set_param( $name, $value );
		}
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}
		return apply_filters( 'rest_post_dispatch', rest_do_request( $request ), rest_get_server(), $request ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core filter under test.
	}

	/**
	 * Token of Q-A1-001 for U-L1.
	 *
	 * @return string
	 */
	private function token(): string {
		return QuestionPresenter::current()->deliver( Actor::user( $this->fx['users']['lea'] ), $this->fx['courses']['C-A'], $this->fx['modules']['M-A1'], NeutralFixture::question( 'A1-001' ), new Randomizer( '20300115' ) )['token'];
	}

	/**
	 * GM-061: learning answers with an idempotency key – repeat, "in progress"
	 * (5 s), other content, orphaned entry (> 5 min) taken over. R0-05 names the
	 * conflict `ccap_idempotency_key_reused`; R0-19 §2.1/§8 maps it to
	 * `ccap_idempotency_conflict`.
	 *
	 * @group GM-061
	 * @group GM-150
	 */
	public function test_GM_061_learning_answer_idempotency(): void {
		$lea     = Actor::user( $this->fx['users']['lea'] );
		$service = AnswerService::current();
		$token   = $this->token();
		$key     = NeutralFixture::uuid( 'fx-061-1' );
		$now     = time();
		SmartTrainer::set_now( $now );

		$first = $service->answer( $lea, $token, array( 'A' ), $key );
		self::assertFalse( $first['repeated'] );
		$again = $service->answer( $lea, $token, array( 'A' ), $key );
		self::assertTrue( $again['repeated'], 'idempotent repeat' );
		self::assertSame( $first['attempts'], $again['attempts'] );
		$conflict = $this->rejected( fn() => $service->answer( $lea, $token, array( 'B' ), $key ), 'ccap_idempotency_conflict' );
		self::assertSame( 409, $conflict->status );

		// Another request with the same key and body is still running (entry 5 s old, no response yet).
		$db      = Database::current();
		$table   = $db->table( 'ccap_request_idempotency' );
		$running = NeutralFixture::uuid( 'fx-061-2' );
		$db->insert(
			'ccap_request_idempotency',
			array(
				'user_id'      => $lea->user_id,
				'scope'        => AnswerService::IDEMPOTENCY_SCOPE,
				'idem_key'     => $running,
				'request_hash' => hash( 'sha256', $token . "\n" . wp_json_encode( array( 'B' ) ) ),
				'status_code'  => 0,
				'created_at'   => gmdate( 'Y-m-d H:i:s', $now - 5 ),
				'expires_at'   => gmdate( 'Y-m-d H:i:s', $now + WEEK_IN_SECONDS ),
			)
		);
		$busy = $this->rejected( fn() => $service->answer( $lea, $token, array( 'B' ), $running ), 'ccap_request_in_progress' );
		self::assertSame( 409, $busy->status );
		self::assertSame( 2, $busy->data['retry_after'] );

		wp_set_current_user( $lea->user_id );
		$response = self::rest(
			'POST',
			'/answers',
			array(
				'token'           => $token,
				'answer'          => array( 'B' ),
				'idempotency_key' => $running,
			)
		);
		self::assertSame( 409, $response->get_status() );
		self::assertSame( 'ccap_request_in_progress', $response->get_data()['code'] );
		self::assertSame( '2', (string) $response->get_headers()['Retry-After'] );

		// After more than 5 minutes the entry counts as orphaned and is taken over.
		SmartTrainer::set_now( $now + 301 );
		$taken = $service->answer( $lea, $token, array( 'B' ), $running );
		self::assertFalse( $taken['repeated'] );
		self::assertFalse( $taken['correct'] );
		self::assertSame( $first['attempts'] + 1, $taken['attempts'] );
		self::assertSame( '200', (string) $db->scalar( $db->prepare( "SELECT status_code FROM `{$table}` WHERE user_id = %d AND idem_key = %s", $lea->user_id, $running ) ) );
		self::assertTrue( $service->answer( $lea, $token, array( 'B' ), $running )['repeated'] );

		// A rejected request releases its key (the client may retry with it).
		$invalid = NeutralFixture::uuid( 'fx-061-3' );
		$this->rejected( fn() => $service->answer( $lea, $token, array(), $invalid ), 'ccap_invalid_answer' );
		self::assertNull( $db->scalar( $db->prepare( "SELECT id FROM `{$table}` WHERE user_id = %d AND idem_key = %s", $lea->user_id, $invalid ) ) );
	}

	/**
	 * GM-150: `POST /answers` with the idempotency key over REST (Q-A1-001,
	 * key `fx-150-1`): without a key no attempt is stored and the catalog code
	 * `ccap_idempotency_key_required` comes back (428 per R0-19 R-08/§8; R0-05
	 * names 422, see gm-coverage-exceptions); the same key (header
	 * `Idempotency-Key` or body field) returns the same result without a second
	 * attempt; the same key with another payload is 409; the token counts once.
	 *
	 * @group GM-150
	 */
	public function test_GM_150_answer_idempotency_key_over_rest(): void {
		$lea   = $this->fx['users']['lea'];
		$token = $this->token();
		$db    = Database::current();
		$count = static fn(): int => (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_attempt' ) . '` WHERE user_id = %d', $lea ) );
		$base  = $count();
		wp_set_current_user( $lea );

		foreach ( array( array(), array( 'idempotency_key' => 'fx-150-1' ) ) as $extra ) {
			$missing = self::rest(
				'POST',
				'/answers',
				array(
					'token'  => $token,
					'answer' => array( 'A' ),
				) + $extra
			);
			self::assertSame( 428, $missing->get_status() );
			self::assertSame( 'ccap_idempotency_key_required', $missing->get_data()['code'] );
			self::assertSame( $base, $count(), 'nothing stored without a valid key' );
		}

		$key   = NeutralFixture::uuid( 'fx-150-1' );
		$first = self::rest(
			'POST',
			'/answers',
			array(
				'token'  => $token,
				'answer' => array( 'A' ),
			),
			array( 'Idempotency-Key' => $key )
		);
		self::assertSame( 200, $first->get_status() );
		self::assertFalse( $first->get_data()['repeated'] );
		self::assertTrue( $first->get_data()['correct'] );
		self::assertSame( $base + 1, $count() );

		$again = self::rest(
			'POST',
			'/answers',
			array(
				'token'           => $token,
				'answer'          => array( 'A' ),
				'idempotency_key' => $key,
			)
		);
		self::assertSame( 200, $again->get_status() );
		self::assertTrue( $again->get_data()['repeated'] );
		$result = array_flip( array( 'correct', 'points_milli', 'attempts', 'correct_ids' ) );
		self::assertSame( array_intersect_key( $first->get_data(), $result ), array_intersect_key( $again->get_data(), $result ), 'same evaluation result' );
		self::assertCount( 4, array_intersect_key( $again->get_data(), $result ) );
		self::assertSame( $base + 1, $count(), 'no second attempt: the token counts once' );

		$conflict = self::rest(
			'POST',
			'/answers',
			array(
				'token'  => $token,
				'answer' => array( 'B' ),
			),
			array( 'Idempotency-Key' => $key )
		);
		self::assertSame( 409, $conflict->get_status() );
		self::assertSame( 'ccap_idempotency_conflict', $conflict->get_data()['code'] );
		self::assertSame( $base + 1, $count() );
	}

	/**
	 * GM-093: public filter "owns course" = `course_captain_can_access` (R0-19 §4.3):
	 * U-L1 true, U-L3 false, guest false; an extension keeping the value
	 * leaves U-L1 allowed; it can only restrict (E-84).
	 *
	 * @group GM-093
	 * @group SEC-037
	 */
	public function test_GM_093_can_access_filter(): void {
		$course = $this->fx['courses']['C-A'];
		$uid    = ( new ObjectIdentity( Database::current() ) )->ensure( get_post( $course ) );
		$seen   = array();
		$decide = static function ( int $user ) use ( $course ): Decision {
			PolicyService::flush();
			return PolicyService::instance()->decide( Actor::user( $user ), PolicyService::LEARN, array( $course ), array( 'scope' => 'view' ) );
		};
		add_filter(
			'course_captain_can_access',
			static function ( $allowed, $user, $course_uid, $scope, $decision ) use ( &$seen ) {
				$seen[ $user ] = array( $allowed, $course_uid, $scope, $decision instanceof Decision );
				return $allowed; // Another plugin keeping the value.
			},
			10,
			5
		);
		self::assertTrue( $decide( $this->fx['users']['lea'] )->allowed );
		self::assertFalse( $decide( $this->fx['users']['free'] )->allowed );
		self::assertFalse( $decide( 0 )->allowed );
		self::assertSame( array( true, $uid, 'view', true ), $seen[ $this->fx['users']['lea'] ] );
		self::assertSame( array( false, $uid, 'view', true ), $seen[ $this->fx['users']['free'] ] );
		self::assertFalse( $seen[0][0], 'guest' );

		// Only restrictive: true for U-L3 is ignored and audited; false for U-L1 locks.
		add_filter( 'course_captain_can_access', static fn( $allowed, $user ) => (int) $user === (int) $GLOBALS['ccap_gm093_free'] ? true : $allowed, 20, 2 );
		$GLOBALS['ccap_gm093_free'] = $this->fx['users']['free'];
		self::assertFalse( $decide( $this->fx['users']['free'] )->allowed );
		$db = Database::current();
		self::assertSame( '1', (string) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'access_filter_ignored' AND subject_ref = %s", \CourseCaptain\Event\AuditIdentity::ref_of( $db, $this->fx['users']['free'] ) ) ) );
		add_filter( 'course_captain_can_access', static fn( $allowed, $user ) => (int) $user === (int) $GLOBALS['ccap_gm093_free'] ? $allowed : false, 30, 2 );
		$locked = $decide( $this->fx['users']['lea'] );
		self::assertFalse( $locked->allowed );
		self::assertSame( array( Decision::CONTENT_LOCKED, 'filtered' ), array( $locked->code, $locked->reason ) );
		unset( $GLOBALS['ccap_gm093_free'] );
		PolicyService::flush();

		$hooks = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/docs/api/hooks.v1.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Snapshot.
		self::assertSame( 'filter', $hooks['course_captain_can_access'] ?? null, 'filter is part of the hook snapshot' );
	}

	/**
	 * GM-146: `GET /status` only for #22 – 200 with the current schema; guest 401, learner 403.
	 *
	 * @group GM-146
	 */
	public function test_GM_146_status_route(): void {
		$guest = self::rest( 'GET', '/status' );
		self::assertSame( 401, $guest->get_status() );
		self::assertSame( 'ccap_auth_required', $guest->get_data()['code'] );
		wp_set_current_user( $this->fx['users']['lea'] );
		self::assertSame( 403, self::rest( 'GET', '/status' )->get_status() );
		self::assertSame( 'ccap_forbidden', self::rest( 'GET', '/status' )->get_data()['code'] );

		wp_set_current_user( $this->fx['users']['admin'] );
		$response = self::rest( 'GET', '/status' );
		$data     = $response->get_data();
		self::assertSame( 200, $response->get_status() );
		self::assertSame( array( 'plugin_version', 'contract_version', 'schema_version', 'outbound_mode', 'hold_mode', 'checks' ), array_keys( $data ) );
		self::assertSame( COURSE_CAPTAIN_VERSION, $data['plugin_version'] );
		self::assertSame( COURSE_CAPTAIN_CONTRACT_VERSION, $data['contract_version'] );
		self::assertSame( Migrator::for_current_site()->report()['code_version'], $data['schema_version'], 'current schema' );
		self::assertFalse( $data['hold_mode'] );
		self::assertNotEmpty( $data['checks'] );
		foreach ( $data['checks'] as $check ) {
			self::assertSame( array( 'id', 'status', 'message' ), array_keys( $check ) );
			self::assertContains( $check['status'], array( 'ok', 'warn', 'fail' ) );
		}
		self::assertStringContainsString( 'no-store', $response->get_headers()['Cache-Control'] );
	}

	/**
	 * Healthy maintenance state at `$now`.
	 *
	 * @param int $now Time of the last hourly run.
	 * @return void
	 */
	private static function healthy( int $now ): void {
		CronHooks::schedule_recurring( $now );
		update_option(
			HealthChecks::STATE_OPTION,
			array(
				'last_run' => $now,
				'failures' => array(),
			),
			false
		);
		Monitor::set_now( $now );
	}

	/**
	 * GM-147: health for guests – 200, overall status only, no plugin version, `no-store`.
	 * SUP-023: `?key=` is ignored (overall status only).
	 *
	 * @group GM-147
	 * @group RBAC-094
	 * @group SUP-023
	 * @group SEC-035
	 */
	public function test_GM_147_health_without_version(): void {
		self::healthy( time() );
		Monitor::with_key( 'gm-147-monitor-key' );
		foreach ( array( '/health', '/monitor' ) as $route ) {
			$response = self::rest( 'GET', $route, array( 'key' => 'gm-147-monitor-key' ) );
			self::assertSame( 200, $response->get_status(), (string) wp_json_encode( Monitor::checks() ) );
			self::assertSame( array( 'status' => 'ok' ), $response->get_data(), '?key= is ignored (SEC-035)' );
			self::assertStringContainsString( 'no-store', $response->get_headers()['Cache-Control'] );
			$json = (string) wp_json_encode( $response->get_data() ) . (string) wp_json_encode( $response->get_headers() );
			self::assertStringNotContainsString( COURSE_CAPTAIN_VERSION, $json );
			self::assertStringNotContainsString( 'version', $json );
		}
	}

	/**
	 * GM-148: monitor – anonymous overall status, single checks with the header
	 * key or for #22, cron standstill (clock + 3 h 1 min without a run) → 503.
	 * SUP-023: without key overall status only, with `X-CC-Monitor-Key` details.
	 * SUP-022: cron run older than 3 h → `fail` and 503.
	 *
	 * @group GM-148
	 * @group SUP-022
	 * @group SUP-023
	 * @group SEC-035
	 */
	public function test_GM_148_monitor_and_cron_stall(): void {
		$t0 = time();
		self::healthy( $t0 );
		Monitor::with_key( 'gm-148-monitor-key' );
		$ids = array( 'H-02', 'H-04', 'H-07', 'H-10', 'H-14', 'H-26' );

		$keyed = self::rest( 'GET', '/monitor', array(), array( Monitor::HEADER => 'gm-148-monitor-key' ) );
		self::assertSame( 200, $keyed->get_status() );
		self::assertSame( 'ok', $keyed->get_data()['status'] );
		self::assertSame( $ids, array_column( $keyed->get_data()['checks'], 'id' ) );
		self::assertArrayNotHasKey( 'checks', self::rest( 'GET', '/monitor', array(), array( Monitor::HEADER => 'wrong-key' ) )->get_data() );
		Monitor::with_key( '' );
		self::assertArrayNotHasKey( 'checks', self::rest( 'GET', '/monitor', array(), array( Monitor::HEADER => '' ) )->get_data(), 'no constant: overall status only' );
		wp_set_current_user( $this->fx['users']['lea'] );
		self::assertArrayNotHasKey( 'checks', self::rest( 'GET', '/health' )->get_data() );
		wp_set_current_user( $this->fx['users']['admin'] );
		self::assertSame( $ids, array_column( self::rest( 'GET', '/health' )->get_data()['checks'], 'id' ), 'admin (#22) sees single checks' );

		// Clock + 3 h 1 min without a maintenance run.
		Monitor::set_now( $t0 + 3 * HOUR_IN_SECONDS + MINUTE_IN_SECONDS );
		$admin = self::rest( 'GET', '/health' );
		self::assertSame( 503, $admin->get_status() );
		self::assertSame( 'error', $admin->get_data()['status'] );
		self::assertSame( 'fail', array_column( $admin->get_data()['checks'], 'status', 'id' )['H-07'] );
		wp_set_current_user( 0 );
		$guest = self::rest( 'GET', '/monitor' );
		self::assertSame( 503, $guest->get_status() );
		self::assertSame( array( 'status' => 'error' ), $guest->get_data() );
		self::assertStringContainsString( 'no-store', $guest->get_headers()['Cache-Control'] );

		// A run brings it back.
		self::healthy( $t0 + 3 * HOUR_IN_SECONDS + MINUTE_IN_SECONDS );
		self::assertSame( 200, self::rest( 'GET', '/monitor' )->get_status() );
	}

	/**
	 * Start ER-A for a fixture user.
	 *
	 * @param string $user Login key.
	 * @return int
	 */
	private function start( string $user ): int {
		return ExamSessions::current()->start( Actor::user( $this->fx['users'][ $user ] ), $this->fx['rules']['ER-A'], null, wp_generate_uuid4(), new Randomizer( '20300115' ) );
	}

	/**
	 * F-4 (R1-16, A11Y flows): open questions in the exam room without
	 * JavaScript – "Show model answer" button, then the self-assessment as
	 * radio buttons in the same form.
	 *
	 * @group R1-16
	 */
	public function test_F4_open_question_without_javascript(): void {
		$lea     = Actor::user( $this->fx['users']['lea'] );
		$session = $this->start( 'lea' );
		wp_set_current_user( $lea->user_id );
		$view = ExamSessions::current()->view( $lea, $session );
		$open = array_values( array_filter( $view['questions'], static fn( array $q ): bool => QuestionTypes::OPEN_TEXT === $q['question']['type'] ) );
		self::assertNotEmpty( $open, 'ER-A contains open questions' );
		$position = (int) $open[0]['position'];

		$body = ExamRoom::render( $lea, $session )['body'];
		self::assertStringContainsString( '<button type="submit" class="ccap-button" name="ccap_reveal" value="' . $position . '">', $body );
		self::assertStringNotContainsString( 'cannot be answered in this view', $body );
		$nonce = wp_create_nonce( ExamRoom::NONCE );

		// Self-assessment before the reveal is ignored; the reveal itself keeps the other answers.
		self::assertSame(
			'saved',
			ExamRoom::handle(
				$lea,
				$session,
				array(
					'_wpnonce' => $nonce,
					'answer'   => array( $position => array( 'known' => '1' ) ),
					'ccap_do'  => 'save',
				)
			)
		);
		self::assertFalse( ExamSessions::current()->view( $lea, $session )['questions'][ $position - 1 ]['answered'] );
		self::assertSame(
			'revealed:' . $position,
			ExamRoom::handle(
				$lea,
				$session,
				array(
					'_wpnonce'    => $nonce,
					'ccap_reveal' => (string) $position,
				)
			)
		);
		self::assertStringEndsWith(
			'#ccap-q-' . $position,
			LearningArea::handle_post(
				$lea,
				'pruefung/' . $session,
				array(
					'_wpnonce'    => $nonce,
					'ccap_reveal' => (string) $position,
				)
			)
		);

		$body = ExamRoom::render( $lea, $session, 'revealed' )['body'];
		self::assertStringContainsString( 'The model answer is shown. Please assess your answer.', $body );
		self::assertStringContainsString( 'class="ccap-model-answer"', $body );
		self::assertStringContainsString( 'name="answer[' . $position . '][known]" value="1"', $body );
		self::assertStringContainsString( 'name="answer[' . $position . '][known]" value="0"', $body );
		self::assertStringContainsString( '<legend>Did you know the answer?</legend>', $body );

		self::assertSame(
			'saved',
			ExamRoom::handle(
				$lea,
				$session,
				array(
					'_wpnonce' => $nonce,
					'answer'   => array( $position => array( 'known' => '0' ) ),
					'ccap_do'  => 'save',
				)
			)
		);
		$item = ExamSessions::current()->view( $lea, $session )['questions'][ $position - 1 ];
		self::assertTrue( $item['answered'] );
		self::assertSame( array( 'known' => false ), $item['answer'] );
		self::assertSame(
			'saved',
			ExamRoom::handle(
				$lea,
				$session,
				array(
					'_wpnonce' => $nonce,
					'answer'   => array( $position => array( 'known' => '1' ) ),
					'ccap_do'  => 'save',
				)
			)
		);
		self::assertSame( array( 'known' => true ), ExamSessions::current()->view( $lea, $session )['questions'][ $position - 1 ]['answer'] );
		self::assertStringContainsString( 'id="ccap-q-' . $position . '-known-1" name="answer[' . $position . '][known]" value="1" checked', ExamRoom::render( $lea, $session )['body'] );
		// A post without a choice keeps the stored self-assessment.
		self::assertSame(
			'saved',
			ExamRoom::handle(
				$lea,
				$session,
				array(
					'_wpnonce' => $nonce,
					'ccap_do'  => 'save',
				)
			)
		);
		self::assertSame( array( 'known' => true ), ExamSessions::current()->view( $lea, $session )['questions'][ $position - 1 ]['answer'] );
	}

	/**
	 * GM-051 (server part of the abort dialog): confirmation page, cancel,
	 * back to the course room with the start button and exactly one message.
	 *
	 * @group GM-051
	 * @group GM-040
	 */
	public function test_GM_051_cancel_dialog(): void {
		$lea     = Actor::user( $this->fx['users']['lea'] );
		$session = $this->start( 'lea' );
		wp_set_current_user( $lea->user_id );
		$nonce = wp_create_nonce( ExamRoom::NONCE );
		$room  = ExamRoom::render( $lea, $session )['body'];
		self::assertStringContainsString( 'name="ccap_do" value="cancel"', $room );

		$single = array_values( array_filter( ExamSessions::current()->view( $lea, $session )['questions'], static fn( array $q ): bool => QuestionTypes::SINGLE === $q['question']['type'] ) )[0];
		$pos    = (int) $single['position'];
		$key    = (string) $single['question']['options'][0]['key'];
		$url    = LearningArea::handle_post(
			$lea,
			'pruefung/' . $session,
			array(
				'_wpnonce' => $nonce,
				'answer'   => array( $pos => $key ),
				'ccap_do'  => 'cancel',
			)
		);
		self::assertStringContainsString( 'ccap_status=confirm_cancel', $url );
		self::assertSame( 'in_progress', ExamSessions::current()->view( $lea, $session )['status'], 'asking does not cancel' );
		$dialog = ExamRoom::render( $lea, $session, 'confirm_cancel' );
		self::assertSame( 'Cancel the exam?', $dialog['title'] );
		self::assertStringContainsString( 'Do you really want to cancel the exam?', $dialog['body'] );
		self::assertStringContainsString( 'name="ccap_do" value="cancel_confirmed"', $dialog['body'] );
		self::assertStringContainsString( 'No, continue the exam', $dialog['body'] );

		$target = LearningArea::handle_post(
			$lea,
			'pruefung/' . $session,
			array(
				'_wpnonce' => $nonce,
				'ccap_do'  => 'cancel_confirmed',
			)
		);
		$uid    = LearningArea::course_uid( $this->fx['courses']['C-A'] );
		self::assertSame( LearningArea::url( 'kurs/' . $uid ) . '#ccap-main', $target );
		$view = ExamSessions::current()->view( $lea, $session );
		self::assertSame( 'cancelled', $view['status'] );
		self::assertSame( $key, $view['questions'][ $pos - 1 ]['answer'], 'answers stay stored (GM-040)' );

		$course = LearningArea::resolve( $lea, 'kurs/' . $uid )['body'];
		self::assertSame( 1, substr_count( $course, 'The exam was cancelled.' ) );
		self::assertSame( 1, substr_count( $course, '>Start exam</button>' ), 'start button back' );
		self::assertSame( 0, substr_count( LearningArea::resolve( $lea, 'kurs/' . $uid )['body'], 'The exam was cancelled.' ), 'shown once' );
		self::assertStringContainsString( 'This attempt was cancelled.', ExamRoom::render( $lea, $session )['body'] );
		self::assertStringContainsString( 'localStorage.removeItem', ExamRoom::render( $lea, $session )['body'], 'buffer removed after the attempt (PRIV-018)' );
	}
}
