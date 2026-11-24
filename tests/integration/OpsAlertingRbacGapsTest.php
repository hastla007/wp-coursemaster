<?php
/**
 * R0-41 §4.2 gaps: OPS-05 alerting (R0-12 §1.1), CORE-02 owner view of the
 * own group (RBAC-059, PRIV-019) and CORE-09 trainer report CSV (RBAC-087).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GrantService;
use CourseCaptain\Admin\LearnersScreen;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\HealthChecks;
use CourseCaptain\Ops\Monitor;
use CourseCaptain\Ops\OpsStatus;
use CourseCaptain\Ops\RepairService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService as P;
use CourseCaptain\Progress\CourseReport;
use CourseCaptain\Storage\Database;
use RuntimeException;

/**
 * Real tables, RBAC fixtures of R0-07 §5.1.
 *
 * @group R0-41
 */
final class OpsAlertingRbacGapsTest extends SchemaTestCase {

	private const CANARY_EMAIL = 'upstream.canary@mailhost.test';

	/**
	 * Fixture world.
	 *
	 * @var PolicyFixtures
	 */
	private PolicyFixtures $f;

	/**
	 * The `cache` subscriber fails while true.
	 *
	 * @var bool
	 */
	private bool $failing = true;

	/**
	 * Activate and build fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->f = new PolicyFixtures();
		P::flush();
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
	}

	/**
	 * Reset clocks, overrides and the current user.
	 */
	public function tear_down(): void {
		remove_filter( 'course_captain_internal_subscriber_handlers', array( $this, 'handlers' ) );
		Monitor::set_now( null );
		Monitor::with_key( null );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Handler override: `cache` fails while `$failing` (the error text carries
	 * an address that must never reach an alert), `notification` succeeds.
	 *
	 * @param array<string, callable> $handlers Handlers.
	 * @return array<string, callable>
	 */
	public function handlers( array $handlers ): array {
		$handlers['cache']        = function (): void {
			if ( $this->failing ) {
				throw new RuntimeException( esc_html( 'upstream down for ' . self::CANARY_EMAIL ) );
			}
		};
		$handlers['notification'] = static function (): void {};
		return $handlers;
	}

	/**
	 * OPS-05 Recovery "Alarmierung" (R0-12 §1.1, R0-41 §4.2): a delivery that
	 * fails up to the dead letter raises every alert channel – the signal
	 * `course_captain_outbox_dead_lettered`, Site Health
	 * `course_captain_outbox` and the system status H-27 with the delivery
	 * ID – without the error text or personal data; after the manual repair
	 * every alert is gone. A failed maintenance step turns the external
	 * monitor (H-10) to 503 and Site Health `course_captain_cron` to
	 * "recommended", and the next clean run clears both.
	 *
	 * @group OPS-05
	 */
	public function test_OPS_05_alerting_dead_letter_and_failed_maintenance(): void {
		$admin = Actor::user( $this->f->adm );
		self::assertSame( 'good', HealthChecks::test_outbox()['status'], 'baseline' );
		self::assertNotSame( 'fail', self::check( OpsStatus::report( $admin ), 'H-27' )['status'] );

		add_filter( 'course_captain_internal_subscriber_handlers', array( $this, 'handlers' ) );
		$signals = array();
		add_action(
			'course_captain_outbox_dead_lettered',
			static function ( $id ) use ( &$signals ): void {
				$signals[] = (int) $id;
			}
		);
		GrantService::current()->grant_manual( $admin, $this->f->x, $this->f->course_a, wp_generate_uuid4(), 'ops-05' );
		$db         = Database::current();
		$deliveries = $db->table( 'ccap_outbox_delivery' );
		$dispatcher = OutboxDispatcher::current();
		for ( $i = 0; $i <= OutboxDispatcher::MAX_ATTEMPTS + 1 && array() === $signals; $i++ ) {
			$dispatcher->dispatch();
			$db->execute( "UPDATE `{$deliveries}` SET next_attempt_at = NULL WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		}
		$dead = array_map( 'intval', $db->wpdb()->get_col( "SELECT id FROM `{$deliveries}` WHERE status = 'dead' ORDER BY id" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Own table.
		self::assertNotSame( array(), $dead, 'dead letters after the last attempt' );
		sort( $signals );
		self::assertSame( $dead, $signals, 'one alert signal per dead letter' );

		$health = HealthChecks::test_outbox();
		self::assertSame( 'recommended', $health['status'] );
		self::assertStringContainsString( (string) count( $dead ), $health['label'] );
		$report = OpsStatus::report( $admin );
		$h27    = self::check( $report, 'H-27' );
		self::assertSame( 'fail', $h27['status'] );
		self::assertSame( 'fail', $report['overall'] );
		self::assertStringStartsWith( 'delivery:' . $dead[0], $h27['ids'][0] );
		$alerts = (string) wp_json_encode( array( $health, $h27 ) );
		foreach ( array( self::CANARY_EMAIL, 'upstream down', (string) get_userdata( $this->f->x )->user_email ) as $leak ) {
			self::assertStringNotContainsString( $leak, $alerts, 'alert without error text or personal data' );
		}

		$this->failing = false;
		self::assertSame( count( $dead ), RepairService::current()->retry_deliveries( $admin, wp_generate_uuid4() )['count'] );
		self::assertGreaterThanOrEqual( count( $dead ), $dispatcher->dispatch()['delivered'], 'delivered after the repair' );
		self::assertSame( 0, (int) $db->scalar( "SELECT COUNT(*) FROM `{$deliveries}` WHERE status = 'dead'" ), 'no dead letter left' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.
		self::assertSame( 'good', HealthChecks::test_outbox()['status'], 'alert cleared' );
		self::assertNotSame( 'fail', self::check( OpsStatus::report( $admin ), 'H-27' )['status'] );

		// Failed maintenance step: external monitor and Site Health.
		$now = time();
		CronHooks::schedule_recurring( $now );
		Monitor::set_now( $now );
		Monitor::with_key( 'ops-05-monitor-key' );
		HealthChecks::run_isolated( array( 'ok' => static fn() => null ) );
		self::assertSame( 200, self::rest( 'GET', '/monitor' )->get_status(), 'baseline monitor' );

		HealthChecks::run_isolated(
			array(
				'broken' => static function (): void {
					throw new RuntimeException( esc_html( 'secret ' . self::CANARY_EMAIL ) );
				},
			)
		);
		$guest = self::rest( 'GET', '/monitor' );
		self::assertSame( 503, $guest->get_status() );
		self::assertSame( array( 'status' => 'error' ), $guest->get_data(), 'guests only see the overall status' );
		$keyed = self::rest( 'GET', '/monitor', array(), array( Monitor::HEADER => 'ops-05-monitor-key' ) );
		self::assertSame( 'fail', array_column( $keyed->get_data()['checks'], 'status', 'id' )['H-10'] );
		$cron = HealthChecks::test_cron();
		self::assertSame( 'recommended', $cron['status'] );
		self::assertStringContainsString( 'broken', $cron['label'] );
		self::assertStringNotContainsString( self::CANARY_EMAIL, (string) wp_json_encode( array( $cron, $keyed->get_data() ) ) );
		self::assertSame( 1, (int) $db->scalar( "SELECT COUNT(*) FROM `{$db->table( 'ccap_audit_log' )}` WHERE action = 'cron_task_failed'" ), 'audited once' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.

		HealthChecks::run_isolated( array( 'ok' => static fn() => null ) );
		self::assertSame( 200, self::rest( 'GET', '/monitor' )->get_status(), 'next clean run clears the alert' );
		self::assertSame( 'good', HealthChecks::test_cron()['status'] );
	}

	/**
	 * RBAC-059 / PRIV-019 (CORE-02 Ablehnung): U-GO reads GRP-1 over REST
	 * (`GET /me/groups`, R-33) and gets only seat and invitation status,
	 * display name and masked address – no progress, no certificate, no
	 * full address (E-81, D07-4); the group progress stays denied
	 * (`owner_no_progress`). Members, the group trainer and strangers see
	 * no foreign group.
	 *
	 * @group RBAC-059
	 * @group PRIV-019
	 * @group CORE-02
	 */
	public function test_RBAC_059_PRIV_019_owner_sees_seat_status_only(): void {
		$db      = Database::current();
		$invitee = 'invitee.private@mailhost.test';
		$this->f->seat(
			$this->f->group_1,
			0,
			'invited',
			array(
				'invite_email'     => $invitee,
				'invited_at'       => $db->now(),
				'claim_expires_at' => $db->now( 7 * DAY_IN_SECONDS ),
			)
		);
		self::progress( $this->f->l3, $this->f->course_a, 'completed', '1.0000' );
		$member = get_userdata( $this->f->l3 );

		wp_set_current_user( $this->f->go );
		$response = self::rest( 'GET', '/me/groups' );
		self::assertSame( 200, $response->get_status() );
		self::assertStringContainsString( 'no-store', (string) $response->get_headers()['Cache-Control'] );
		$groups = $response->get_data();
		self::assertCount( 1, $groups );
		$group = $groups[0];
		self::assertSame( 'GRP-1', $group['name'] );
		self::assertSame( array( 'claim_policy', 'courses', 'invite_budget', 'invite_link', 'invite_link_expires_at', 'members', 'name', 'row_version', 'seats', 'status', 'uid', 'valid_until' ), self::sorted_keys( $group ), 'no progress or certificate fields' );
		self::assertSame( array( 2, 1 ), array( $group['seats']['used'], $group['seats']['invites_open'] ), 'active seat plus open invitation' );
		self::assertCount( 2, $group['members'] );
		foreach ( $group['members'] as $row ) {
			self::assertSame( array( 'display_name', 'email_masked', 'seat_id', 'since', 'status' ), self::sorted_keys( $row ) );
		}
		$by_status = array_column( $group['members'], null, 'status' );
		self::assertSame( $member->display_name, $by_status['active']['display_name'] );
		self::assertNull( $by_status['active']['email_masked'], 'no address of an active member' );
		self::assertSame( 'i***@mailhost.test', $by_status['invited']['email_masked'] );
		$json = (string) wp_json_encode( $groups );
		foreach ( array( $invitee, (string) $member->user_email, 'completed', 'completion', 'certificate' ) as $leak ) {
			self::assertStringNotContainsString( $leak, $json );
		}
		$progress = P::instance()->decide( Actor::user( $this->f->go ), P::VIEW_GROUP_PROGRESS, array( $this->f->group_1 ) );
		self::assertFalse( $progress->allowed );
		self::assertSame( 'owner_no_progress', $progress->reason );

		foreach ( array( $this->f->l3, $this->f->trb, $this->f->x ) as $other ) {
			wp_set_current_user( $other );
			self::assertSame( array(), self::rest( 'GET', '/me/groups' )->get_data(), 'only own groups' );
		}
	}

	/**
	 * RBAC-087 (CORE-09 Ablehnung): report CSV of a trainer. U-TUT for C-B:
	 * 403 in the service and in the download handler, no file, no audit;
	 * for C-A: every learner of the course. U-TRB (scoped to GRP-1) for C-A:
	 * only the assigned learner U-L3. Never answers or addresses (D07-4);
	 * each allowed export is audited.
	 *
	 * @group RBAC-087
	 * @group CORE-09
	 */
	public function test_RBAC_087_trainer_report_csv(): void {
		self::progress( $this->f->l1, $this->f->course_a, 'in_progress', '0.5000' );
		self::progress( $this->f->l3, $this->f->course_a, 'in_progress', '0.2500' );
		self::progress( $this->f->x, $this->f->course_b, 'in_progress', '0.7500' );
		$report = CourseReport::current();
		$tutor  = Actor::user( $this->f->tut );
		$db     = Database::current();
		$audits = static fn(): int => (int) $db->scalar( "SELECT COUNT(*) FROM `{$db->table( 'ccap_audit_log' )}` WHERE action = 'report_exported'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own table.

		try {
			$report->csv( $tutor, $this->f->course_b );
			self::fail( 'C-B is not assigned to U-TUT' );
		} catch ( CourseException $e ) {
			self::assertSame( array( 'ccap_forbidden', 403 ), array( $e->error_code, $e->status ) );
		}
		wp_set_current_user( $this->f->tut );
		$denied = LearnersScreen::export(
			array(
				'course'   => $this->f->course_b,
				'_wpnonce' => wp_create_nonce( LearnersScreen::EXPORT_ACTION . ':' . $this->f->course_b ),
			),
			$this->f->tut
		);
		self::assertSame( array( 'ccap_forbidden', '' ), array( $denied['error'], $denied['csv'] ), 'download handler: no file' );
		self::assertSame( 0, $audits(), 'denied exports are not audited' );

		$lines = static fn( string $csv ): array => explode( "\r\n", trim( substr( $csv, 3 ) ) );
		$ids   = static fn( array $rows ): array => array_map( static fn( string $line ): int => (int) trim( explode( ',', $line )[0], '"' ), $rows );

		$full = $lines( $report->csv( $tutor, $this->f->course_a ) );
		self::assertSame( implode( ',', CourseReport::CSV_COLUMNS ), $full[0], 'no answer columns' );
		$expected = array( $this->f->l1, $this->f->l3 );
		sort( $expected );
		self::assertSame( $expected, $ids( array_slice( $full, 1 ) ), 'U-TUT: every learner of C-A, none of C-B' );

		$scoped = $lines( $report->csv( Actor::user( $this->f->trb ), $this->f->course_a ) );
		self::assertSame( array( $this->f->l3 ), $ids( array_slice( $scoped, 1 ) ), 'U-TRB: only the assigned learner' );

		foreach ( array( $full, $scoped ) as $csv ) {
			self::assertStringNotContainsString( '@', implode( "\n", $csv ), 'never an address' );
		}
		self::assertSame( 2, $audits() );
	}

	/**
	 * Course progress row.
	 *
	 * @param int    $user   User.
	 * @param int    $course Course.
	 * @param string $status Status.
	 * @param string $ratio  Completion ratio.
	 */
	private static function progress( int $user, int $course, string $status, string $ratio ): void {
		Database::current()->insert(
			'ccap_course_progress',
			array(
				'user_id'          => $user,
				'course_id'        => $course,
				'status'           => $status,
				'completion_ratio' => $ratio,
				'last_activity_at' => Database::current()->now(),
			),
			array( 'updated_at' )
		);
	}

	/**
	 * Sorted keys of a row.
	 *
	 * @param array<string, mixed> $row Row.
	 * @return list<string>
	 */
	private static function sorted_keys( array $row ): array {
		$keys = array_map( 'strval', array_keys( $row ) );
		sort( $keys );
		return $keys;
	}

	/**
	 * A check of a status report.
	 *
	 * @param array<string, mixed> $report Report.
	 * @param string               $id     Check ID.
	 * @return array<string, mixed>
	 */
	private static function check( array $report, string $id ): array {
		foreach ( $report['checks'] as $check ) {
			if ( $id === $check['id'] ) {
				return $check;
			}
		}
		self::fail( 'check ' . $id . ' missing' );
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
}
