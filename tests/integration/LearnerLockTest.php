<?php
/**
 * Learner lock write path and audit (R0-13 K-12, R0-07 §3.6, E-79, M-24).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Admin\AdminUi;
use CourseCaptain\Admin\LearnersScreen;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Decision;
use CourseCaptain\Policy\LearnerLock;
use CourseCaptain\Policy\PolicyService as P;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Set and lift a lock through {@see LearnerLock} and the learner screen:
 * rights (M-24), reason codes, audit in the same transaction, grants
 * unchanged, optional end of all sessions.
 *
 * @group R1-38
 * @group ID-05
 */
final class LearnerLockTest extends SchemaTestCase {

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
	 * SUP-004, GRANT-019, RBAC-050: support locks a learner (audit
	 * `learner_locked`), learning is denied, grants stay; lifting needs a
	 * reason, is audited and gives access back at once.
	 *
	 * @group SUP-004
	 * @group GRANT-019
	 * @group RBAC-050
	 */
	public function test_SUP_004_GRANT_019_lock_and_lift_with_audit(): void {
		$db      = Database::current();
		$grant   = $db->row( $db->prepare( 'SELECT status, row_version FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $this->f->grant_l1 ) );
		$service = LearnerLock::current();
		$support = Actor::user( $this->f->sup );

		$result = $service->lock( $support, $this->f->l1, 'abuse' );
		self::assertGreaterThan( 0, $result['lock_id'] );
		self::assertFalse( $result['sessions_terminated'] );
		$row = $db->row( $db->prepare( 'SELECT user_id, reason_code, reason, set_by, lifted_at FROM `' . $db->table( 'ccap_learner_lock' ) . '` WHERE id = %d', $result['lock_id'] ) );
		self::assertSame( array( (string) $this->f->l1, 'abuse', null, (string) $this->f->sup, null ), array_values( (array) $row ) );
		$audit = $db->row( $db->prepare( 'SELECT action, object_type, reason, subject_ref, context FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE id = %d', $result['audit_id'] ) );
		self::assertSame( 'learner_locked', $audit['action'] );
		self::assertSame( 'learner_lock', $audit['object_type'] );
		self::assertSame( 'abuse', $audit['reason'] );
		self::assertNotEmpty( $audit['subject_ref'], 'affected learner is recorded (pseudonym)' );
		self::assertSame( 'abuse', json_decode( (string) $audit['context'], true )['reason_code'] );

		$decision = P::instance()->decide( Actor::user( $this->f->l1 ), P::LEARN, array( $this->f->course_a ) );
		self::assertFalse( $decision->allowed );
		self::assertSame( Decision::ACCOUNT_LOCKED, $decision->code );
		self::assertSame( $grant, $db->row( $db->prepare( 'SELECT status, row_version FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $this->f->grant_l1 ) ), 'grants unchanged' );

		self::assertSame( 'ccap_conflict', self::code( fn() => $service->lock( $support, $this->f->l1, 'other', 'again' ) ), 'one active lock per learner' );
		self::assertSame( 'ccap_reason_required', self::code( fn() => $service->lift( $support, $this->f->l1, ' ' ) ) );

		$lifted = $service->lift( $support, $this->f->l1, 'Case closed' );
		self::assertSame( $result['lock_id'], $lifted['lock_id'] );
		self::assertSame( (string) $this->f->sup, $db->scalar( $db->prepare( 'SELECT lifted_by FROM `' . $db->table( 'ccap_learner_lock' ) . '` WHERE id = %d AND lifted_at IS NOT NULL', $result['lock_id'] ) ) );
		self::assertSame( 'learner_unlocked', $db->scalar( $db->prepare( 'SELECT action FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE id = %d', $lifted['audit_id'] ) ) );
		self::assertSame( 'Case closed', $db->scalar( $db->prepare( 'SELECT reason FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE id = %d', $lifted['audit_id'] ) ) );
		self::assertTrue( P::instance()->decide( Actor::user( $this->f->l1 ), P::LEARN, array( $this->f->course_a ) )->allowed, 'access back at once' );
		self::assertSame( 'ccap_not_found', self::code( fn() => $service->lift( $support, $this->f->l1, 'again' ) ), 'no active lock' );
		self::assertNull( $service->active( $this->f->l1 ) );
	}

	/**
	 * SUP-004, M-24: not against oneself, not against more rights, not
	 * without #18; system actors cannot lock.
	 *
	 * @group SUP-004
	 */
	public function test_SUP_004_rights(): void {
		$service = LearnerLock::current();
		self::assertSame( Decision::FORBIDDEN, self::code( fn() => $service->lock( Actor::user( $this->f->sup ), $this->f->adm, 'abuse' ) ), 'admin has more rights' );
		self::assertSame( Decision::FORBIDDEN, self::code( fn() => $service->lock( Actor::user( $this->f->sup ), $this->f->sup, 'abuse' ) ), 'never oneself' );
		self::assertSame( Decision::FORBIDDEN, self::code( fn() => $service->lock( Actor::user( $this->f->l2 ), $this->f->l1, 'abuse' ) ), 'learner without #18' );
		self::assertSame( Decision::FORBIDDEN, self::code( fn() => $service->lock( Actor::user( $this->f->editor ), $this->f->l1, 'abuse' ) ), 'editor without #18' );
		self::assertNotSame( 'ok', self::code( fn() => $service->lock( Actor::cron(), $this->f->l1, 'abuse' ) ), 'system actor' );
		self::assertSame( Decision::NOT_FOUND, self::code( fn() => $service->lock( Actor::user( $this->f->sup ), 999999, 'abuse' ) ) );
		self::assertSame( 'ok', self::code( fn() => $service->lock( Actor::user( $this->f->adm ), $this->f->mgr, 'legal_hold' ) ), 'admin may lock a manager' );
		self::assertSame( Decision::FORBIDDEN, self::code( fn() => $service->lift( Actor::user( $this->f->mgr ), $this->f->mgr, 'self' ) ), 'locked staff cannot lift their own lock' );
		self::assertSame( 0, self::audits( 'learner_locked', $this->f->l1 ) );
	}

	/**
	 * Input checks: reason code list, text for `other`, end in the future;
	 * an expired lock does not block a new one.
	 */
	public function test_input_and_expiry(): void {
		$db      = Database::current();
		$service = LearnerLock::current();
		$support = Actor::user( $this->f->sup );
		self::assertSame( 'ccap_invalid_param', self::code( fn() => $service->lock( $support, $this->f->l1, 'test' ) ) );
		self::assertSame( 'ccap_reason_required', self::code( fn() => $service->lock( $support, $this->f->l1, 'other' ) ) );
		self::assertSame( 'ccap_date_invalid', self::code( fn() => $service->lock( $support, $this->f->l1, 'abuse', '', '2000-01-01 23:59:59' ) ) );
		self::assertSame( 'ccap_date_invalid', self::code( fn() => $service->lock( $support, $this->f->l1, 'abuse', '', 'tomorrow' ) ) );
		self::assertNull( $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_learner_lock' ) . '` WHERE user_id = %d', $this->f->l1 ) ), 'nothing written' );

		$first = $service->lock( $support, $this->f->l1, 'other', 'Chargeback dispute', $db->now( 3600 ) );
		self::assertSame( 'Chargeback dispute', $db->scalar( $db->prepare( 'SELECT reason FROM `' . $db->table( 'ccap_learner_lock' ) . '` WHERE id = %d', $first['lock_id'] ) ) );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_learner_lock' ) . '` SET valid_until = %s WHERE id = %d', $db->now( -60 ), $first['lock_id'] ) );
		do_action( 'course_captain_policy_changed' );
		self::assertNull( $service->active( $this->f->l1 ) );
		self::assertTrue( P::instance()->decide( Actor::user( $this->f->l1 ), P::LEARN, array( $this->f->course_a ) )->allowed, 'expired lock' );
		self::assertSame( 'ok', self::code( fn() => $service->lock( $support, $this->f->l1, 'abuse' ) ), 'expired lock does not block a new one' );
		self::assertSame( 2, self::audits( 'learner_locked', $this->f->l1 ) );
	}

	/**
	 * E-79: optional end of all sessions, audited as `sessions_terminated`.
	 */
	public function test_end_sessions(): void {
		$tokens = \WP_Session_Tokens::get_instance( $this->f->l1 );
		$tokens->create( time() + HOUR_IN_SECONDS );
		self::assertNotEmpty( $tokens->get_all() );
		$result = LearnerLock::current()->lock( Actor::user( $this->f->sup ), $this->f->l1, 'account_compromised', '', null, true );
		self::assertTrue( $result['sessions_terminated'] );
		self::assertSame( array(), \WP_Session_Tokens::get_instance( $this->f->l1 )->get_all() );
		self::assertSame( 1, self::audits( 'sessions_terminated', $this->f->l1 ) );
	}

	/**
	 * Learner screen: the lock form appears with M-24 only; lock and lift
	 * through the admin-post core with nonce and reason.
	 *
	 * @group SUP-004
	 */
	public function test_learners_screen_lock_and_unlock(): void {
		$request = array(
			'course'  => $this->f->course_a,
			'learner' => $this->f->l1,
		);
		wp_set_current_user( $this->f->sup );
		$detail = LearnersScreen::html( $request, $this->f->sup );
		self::assertStringContainsString( 'Lock learning access', $detail );
		self::assertSame( array(), A11yDom::fragment_problems( $detail ) );
		self::assertStringNotContainsString( 'Lock learning access', LearnersScreen::html( array( 'course' => $this->f->course_a, 'learner' => $this->f->adm ), $this->f->sup ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.

		$base = array( 'course' => $this->f->course_a );
		self::assertSame( 'ccap_invalid_nonce', LearnersScreen::process( array( 'op' => 'lock', 'object' => $this->f->l1, '_wpnonce' => 'x', 'reason_code' => 'abuse' ) + $base, $this->f->sup )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Short test input.
		self::assertSame(
			'ccap_date_invalid',
			LearnersScreen::process(
				$this->form(
					'lock',
					$this->f->l1,
					$base + array(
						'reason_code' => 'abuse',
						'valid_until' => '24.12.2099',
					)
				),
				$this->f->sup
			)['result']
		);
		self::assertSame(
			'ok',
			LearnersScreen::process(
				$this->form(
					'lock',
					$this->f->l1,
					$base + array(
						'reason_code' => 'abuse',
						'valid_until' => gmdate( 'Y-m-d', time() + 7 * DAY_IN_SECONDS ),
					)
				),
				$this->f->sup
			)['result']
		);
		self::assertSame( 1, self::audits( 'learner_locked', $this->f->l1 ) );
		self::assertStringContainsString(
			'Learning access locked',
			LearnersScreen::html(
				$request + array(
					'ccap_result' => 'ok',
					'ccap_op'     => 'lock',
				),
				$this->f->sup
			)
		);
		self::assertStringContainsString( 'Lift lock', LearnersScreen::html( $request, $this->f->sup ) );

		self::assertSame( 'ccap_reason_required', LearnersScreen::process( $this->form( 'unlock', $this->f->l1, $base ), $this->f->sup )['result'] );
		self::assertSame( 'ok', LearnersScreen::process( $this->form( 'unlock', $this->f->l1, $base + array( 'reason' => 'Resolved' ) ), $this->f->sup )['result'] );
		self::assertSame( 1, self::audits( 'learner_unlocked', $this->f->l1 ) );
		self::assertNull( LearnerLock::current()->active( $this->f->l1 ) );
	}

	/**
	 * POST data with a valid nonce of the learner screen.
	 *
	 * @param string               $op      lock|unlock.
	 * @param int                  $learner Learner.
	 * @param array<string, mixed> $data    Fields.
	 * @return array<string, mixed>
	 */
	private function form( string $op, int $learner, array $data ): array {
		wp_set_current_user( $this->f->sup );
		return array(
			'op'       => $op,
			'object'   => $learner,
			'_wpnonce' => wp_create_nonce( AdminUi::nonce_action( LearnersScreen::ACTION, $op, $learner ) ),
		) + $data;
	}

	/**
	 * Error code of a call, `ok` without exception.
	 *
	 * @param callable $call Call.
	 * @return string
	 */
	private static function code( callable $call ): string {
		try {
			$call();
			return 'ok';
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
	}

	/**
	 * Audit rows of an action about one user (pseudonymised subject).
	 *
	 * @param string $action Action.
	 * @param int    $user   Affected user.
	 * @return int
	 */
	private static function audits( string $action, int $user ): int {
		$db  = Database::current();
		$ref = \CourseCaptain\Event\AuditIdentity::ref_for( $db, $user );
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s AND subject_ref = %s', $action, $ref ) );
	}
}
