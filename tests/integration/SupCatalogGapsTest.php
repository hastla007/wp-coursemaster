<?php
/**
 * Support catalogue cases of R0-13 §9 without a test so far (R0-41 §4.1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\AdminUi;
use CourseCaptain\Admin\LearnersScreen;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\AuditView;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Decision;
use CourseCaptain\Policy\LearnerLock;
use CourseCaptain\Policy\PolicyService as P;
use CourseCaptain\Rest\RestContract;
use CourseCaptain\Storage\Database;

/**
 * SUP-002, SUP-003, SUP-005, SUP-011, SUP-033, SUP-037 on the services and
 * entry points that exist in R1 (R0-13 §9, R1-38).
 *
 * @group R1-38
 */
final class SupCatalogGapsTest extends SchemaTestCase {

	/**
	 * Fixture world.
	 *
	 * @var PolicyFixtures
	 */
	private PolicyFixtures $f;

	/**
	 * Activate and build fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->f = new PolicyFixtures();
		P::flush();
	}

	/**
	 * SUP-002: support (no #9) sends a valid revoke form: refused, the grant,
	 * its event history and the audit log stay as they were.
	 *
	 * @group SUP-002
	 */
	public function test_SUP_002_support_cannot_revoke(): void {
		$db     = Database::current();
		$grant  = $this->f->grant_l1;
		$before = $db->row( $db->prepare( 'SELECT status, row_version FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $grant ) );
		$events = self::count( 'ccap_grant_event' );
		$audits = self::count( 'ccap_audit_log' );
		$post   = $this->form(
			$this->f->sup,
			'revoke',
			$grant,
			array(
				'course'  => $this->f->course_a,
				'confirm' => '1',
				'reason'  => 'Support tries to revoke',
			)
		);
		$result = LearnersScreen::process( $post, $this->f->sup )['result'];
		self::assertContains( $result, array( Decision::FORBIDDEN, Decision::NOT_FOUND ) );
		self::assertSame( $before, $db->row( $db->prepare( 'SELECT status, row_version FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $grant ) ) );
		self::assertSame( $events, self::count( 'ccap_grant_event' ), 'no grant event' );
		self::assertSame( $audits, self::count( 'ccap_audit_log' ), 'no audit entry' );
	}

	/**
	 * SUP-003: support (no #14) may not open the details of a foreign
	 * attempt, with or without a reason; the existence is not revealed.
	 *
	 * @group SUP-003
	 */
	public function test_SUP_003_support_without_attempt_details(): void {
		foreach ( array( '', 'Support case 123' ) as $reason ) {
			$decision = P::instance()->decide( Actor::user( $this->f->sup ), P::VIEW_ATTEMPT, array( $this->f->session_l1_submitted ), array( 'reason' => $reason ) );
			self::assertFalse( $decision->allowed );
			self::assertSame( Decision::NOT_FOUND, $decision->code );
		}
	}

	/**
	 * SUP-005: the course manager has no #21 and cannot read the audit log;
	 * support can.
	 *
	 * @group SUP-005
	 */
	public function test_SUP_005_manager_without_audit_log(): void {
		try {
			AuditView::query( Actor::user( $this->f->mgr ) );
			self::fail( 'manager must not read the audit log' );
		} catch ( CourseException $e ) {
			self::assertSame( Decision::FORBIDDEN, $e->error_code );
			self::assertSame( 403, $e->status );
		}
		self::assertArrayHasKey( 'items', AuditView::query( Actor::user( $this->f->sup ) ) );
	}

	/**
	 * SUP-011: a revoked grant cannot be resumed; a new manual grant is a
	 * new row and the old one stays `revoked`.
	 *
	 * @group SUP-011
	 */
	public function test_SUP_011_revoked_is_final(): void {
		$db      = Database::current();
		$revoked = (int) $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_grant' ) . "` WHERE user_id = %d AND course_id = %d AND status = 'revoked'", $this->f->l2, $this->f->course_a ) );
		self::assertGreaterThan( 0, $revoked );
		$post = $this->form(
			$this->f->adm,
			'resume',
			$revoked,
			array(
				'course' => $this->f->course_a,
				'reason' => 'Undo the revocation',
			)
		);
		self::assertSame( 'ccap_conflict', LearnersScreen::process( $post, $this->f->adm )['result'] );
		self::assertSame( 'revoked', $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $revoked ) ) );

		$new = \CourseCaptain\Access\GrantService::current()->grant_manual( Actor::user( $this->f->adm ), $this->f->l2, $this->f->course_a, wp_generate_uuid4(), 'Goodwill after revocation' );
		self::assertNotSame( $revoked, $new );
		self::assertSame( 'revoked', $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $revoked ) ), 'old revision stays revoked' );
	}

	/**
	 * SUP-033: no REST route of the plugin changes or deletes audit rows.
	 *
	 * @group SUP-033
	 */
	public function test_SUP_033_no_audit_write_route(): void {
		do_action( 'rest_api_init' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- Core hook under test.
		$routes = rest_get_server()->get_routes( RestContract::NAMESPACE );
		self::assertNotEmpty( $routes );
		foreach ( $routes as $route => $handlers ) {
			if ( false === stripos( (string) $route, 'audit' ) ) {
				continue;
			}
			foreach ( $handlers as $handler ) {
				$methods = array_keys( array_filter( (array) ( $handler['methods'] ?? array() ) ) );
				self::assertSame( array(), array_diff( $methods, array( 'GET', 'HEAD' ) ), (string) $route . ' must be read-only' );
			}
		}
	}

	/**
	 * SUP-037: a correction whose audit insert fails is rolled back as a
	 * whole (fail-closed, R0-13 §4.3), here K-12 learner lock.
	 *
	 * @group SUP-037
	 */
	public function test_SUP_037_failed_audit_rolls_back_the_lock(): void {
		$db     = Database::current();
		$prefix = 'INSERT INTO `' . $db->table( 'ccap_audit_log' ) . '`';
		$break  = static function ( string $query ) use ( $prefix ): string {
			return str_starts_with( $query, $prefix ) ? 'INSERT INTO `ccap_missing_table_sup037` (id) VALUES (1)' : $query;
		};
		add_filter( 'query', $break );
		$quiet  = $db->wpdb()->suppress_errors( true );
		$failed = false;
		try {
			LearnerLock::current()->lock( Actor::user( $this->f->sup ), $this->f->l1, 'abuse' );
		} catch ( \Throwable $e ) {
			$failed = true;
		} finally {
			remove_filter( 'query', $break );
			$db->wpdb()->suppress_errors( $quiet );
		}
		self::assertTrue( $failed, 'the failed audit insert surfaces' );
		self::assertSame( '0', $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_learner_lock' ) . '` WHERE user_id = %d', $this->f->l1 ) ), 'no lock without audit' );
		do_action( 'course_captain_policy_changed' );
		self::assertTrue( P::instance()->decide( Actor::user( $this->f->l1 ), P::LEARN, array( $this->f->course_a ) )->allowed );
	}

	/**
	 * POST data with a valid nonce of the learner screen.
	 *
	 * @param int                  $user   Acting user.
	 * @param string               $op     Operation.
	 * @param int                  $object_id Object ID.
	 * @param array<string, mixed> $data   Fields.
	 * @return array<string, mixed>
	 */
	private function form( int $user, string $op, int $object_id, array $data ): array {
		wp_set_current_user( $user );
		return array(
			'op'       => $op,
			'object'   => $object_id,
			'_wpnonce' => wp_create_nonce( AdminUi::nonce_action( LearnersScreen::ACTION, $op, $object_id ) ),
		) + $data;
	}

	/**
	 * Row count of a table.
	 *
	 * @param string $table Table without prefix.
	 * @return int
	 */
	private static function count( string $table ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '`' );
	}
}
