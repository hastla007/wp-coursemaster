<?php
/**
 * R1-03: roles, policy service and meta capabilities (R0-07 §5 at service level).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Kernel\Uninstaller;
use CourseCaptain\Kernel\UninstallManifest;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Decision;
use CourseCaptain\Policy\EscalationGuard;
use CourseCaptain\Policy\PolicyService as P;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Storage\Database;

/**
 * Negative and positive RBAC/IDOR cases without HTTP entry points; the
 * entry-point variants follow with the routes (R1-05 ff., R1-14, R1-28).
 *
 * @group R1-03
 */
final class PolicyTest extends SchemaTestCase {

	/**
	 * Fixture world.
	 *
	 * @var PolicyFixtures
	 */
	private PolicyFixtures $f;

	/**
	 * Activate (tables, caps, roles) and build fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		add_role(
			'shop_manager',
			'Shop manager',
			array(
				'read'               => true,
				'manage_woocommerce' => true,
			)
		);
		Plugin::activate( false );
		$this->f = new PolicyFixtures();
		P::flush();
	}

	/**
	 * Remove the foreign test role.
	 */
	public function tear_down(): void {
		remove_role( 'shop_manager' );
		parent::tear_down();
	}

	/**
	 * Decision for a user.
	 *
	 * @param int                  $user    User.
	 * @param string               $action  Action.
	 * @param array                $args    Arguments.
	 * @phpstan-param list<mixed> $args
	 * @param array<string, mixed> $context Context.
	 * @return Decision
	 */
	private static function d( int $user, string $action, array $args = array(), array $context = array() ): Decision {
		return P::instance()->decide( Actor::user( $user ), $action, $args, $context );
	}

	/**
	 * Assert a denial code and reason.
	 *
	 * @param Decision $decision Decision.
	 * @param string   $code     Expected code.
	 * @param string   $reason   Expected reason ('' = any).
	 */
	private static function assertDenied( Decision $decision, string $code, string $reason = '' ): void {
		self::assertFalse( $decision->allowed, 'expected denial ' . $code );
		self::assertSame( $code, $decision->code );
		self::assertSame( array( 'do_not_allow' ), $decision->caps );
		if ( '' !== $reason ) {
			self::assertSame( $reason, $decision->reason );
		}
	}

	/**
	 * Change roles inside a test and let the policy see it (P-11).
	 *
	 * @param int    $user User.
	 * @param string $role New role.
	 */
	private static function set_role( int $user, string $role ): void {
		( new \WP_User( $user ) )->set_role( $role );
		clean_user_cache( $user );
	}

	/**
	 * RBAC-097 / P-10: only administrator and the four own roles get caps.
	 *
	 * @group RBAC-097
	 */
	public function test_RBAC_097_roles_and_defaults(): void {
		self::assertSame( Capabilities::PRIMITIVE, self::ccap_caps_of( 'administrator' ) );
		self::assertSame( array(), self::ccap_caps_of( 'editor' ) );
		self::assertSame( array(), self::ccap_caps_of( 'shop_manager' ) );
		foreach ( Roles::capabilities() as $slug => $caps ) {
			$role = get_role( $slug );
			self::assertNotNull( $role, $slug );
			foreach ( $caps as $cap ) {
				self::assertTrue( $role->has_cap( $cap ), "{$slug} {$cap}" );
			}
		}
		self::assertFalse( user_can( $this->f->tut, 'ccap_edit_courses' ) );
		self::assertFalse( user_can( $this->f->mgr, 'ccap_manage_settings' ) );
		self::assertFalse( user_can( $this->f->sup, 'ccap_view_attempt_details' ) );
	}

	/**
	 * RBAC-001/002/009/020/008: learning access and status codes.
	 *
	 * @group RBAC-001
	 * @group RBAC-002
	 * @group RBAC-008
	 * @group RBAC-009
	 * @group RBAC-020
	 */
	public function test_RBAC_001_002_008_009_020_learning_access(): void {
		self::assertTrue( self::d( $this->f->l1, P::LEARN, array( $this->f->course_a ) )->allowed );
		self::assertTrue( user_can( $this->f->l1, P::LEARN, $this->f->course_a ) );
		self::assertTrue( self::d( $this->f->x, P::VIEW_COURSE, array( $this->f->course_a ) )->allowed, 'catalog stays visible' );
		self::assertDenied( self::d( $this->f->x, P::LEARN, array( $this->f->course_a ) ), Decision::CONTENT_LOCKED, 'no_access' );

		$draft    = self::d( $this->f->l1, P::LEARN, array( $this->f->course_c ) );
		$archived = self::d( $this->f->l1, P::LEARN, array( $this->f->course_d ) );
		$missing  = self::d( $this->f->l1, P::LEARN, array( 999999 ) );
		self::assertDenied( $draft, Decision::NOT_FOUND );
		self::assertEquals( $missing, $draft );
		self::assertEquals( $missing, $archived );

		self::assertDenied( self::d( $this->f->x, P::LEARN, array( $this->f->course_b ) ), Decision::CONTENT_LOCKED, 'expired' );
		self::assertDenied( self::d( $this->f->blk, P::LEARN, array( $this->f->course_a ) ), Decision::ACCOUNT_LOCKED, 'locked' );
		self::assertDenied( self::d( $this->f->norole, P::LEARN, array( $this->f->course_a ) ), Decision::ACCOUNT_LOCKED, 'no_role' );
		self::assertDenied( self::d( 0, P::LEARN, array( $this->f->course_a ) ), Decision::AUTH_REQUIRED );
		self::assertDenied( self::d( $this->f->l2, P::LEARN, array( $this->f->course_a ) ), Decision::CONTENT_LOCKED, 'revoked' );
	}

	/**
	 * RBAC-012: a module of another course is invisible.
	 *
	 * @group RBAC-012
	 */
	public function test_RBAC_012_module_of_other_course_is_not_found(): void {
		self::assertDenied( self::d( $this->f->l1, P::LEARN, array( $this->f->course_a ), array( 'module' => $this->f->module_b1 ) ), Decision::NOT_FOUND );
	}

	/**
	 * RBAC-003/004/005/006: course editing.
	 *
	 * @group RBAC-003
	 * @group RBAC-004
	 * @group RBAC-005
	 * @group RBAC-006
	 */
	public function test_RBAC_003_to_006_course_editing(): void {
		self::assertFalse( user_can( $this->f->au2, 'edit_post', $this->f->course_a ), 'foreign course' );
		self::assertTrue( user_can( $this->f->au2, 'edit_post', $this->f->course_b ), 'own course' );
		self::assertFalse( user_can( $this->f->au2, P::PUBLISH_COURSE, $this->f->course_b ), 'author cannot publish' );
		self::assertFalse( user_can( $this->f->tut, 'edit_post', $this->f->course_a ), 'trainer cannot edit' );
		self::assertTrue( user_can( $this->f->mgr, 'edit_post', $this->f->course_a ) );
		self::assertTrue( user_can( $this->f->mgr, P::PUBLISH_COURSE, $this->f->course_b ) );

		self::set_role( $this->f->au2, 'subscriber' );
		self::assertFalse( user_can( $this->f->au2, 'edit_post', $this->f->course_b ), 'immediately after role change' );
	}

	/**
	 * Assigned author (B-2) and course items (M-06).
	 */
	public function test_staff_author_and_course_items(): void {
		self::assertFalse( user_can( $this->f->au2, 'edit_post', $this->f->lesson_a1 ) );
		$this->f->staff( $this->f->au2, $this->f->course_a, 'author', 0 );
		do_action( 'course_captain_policy_changed' );
		self::assertTrue( user_can( $this->f->au2, 'edit_post', $this->f->course_a ) );
		self::assertTrue( user_can( $this->f->au2, 'edit_post', $this->f->lesson_a1 ) );
		self::assertTrue( self::d( $this->f->au2, P::EDIT_COURSE_ITEM, array( $this->f->rule_a, 'exam_rule' ) )->allowed );
		self::assertFalse( user_can( $this->f->au2, 'delete_post', $this->f->lesson_a1 ), 'delete needs #4' );
	}

	/**
	 * RBAC-007/039/049/063/083: removed rights take effect immediately (P-11).
	 *
	 * @group RBAC-007
	 * @group RBAC-049
	 * @group RBAC-063
	 * @group RBAC-083
	 */
	public function test_RBAC_007_049_063_083_changes_apply_immediately(): void {
		self::assertTrue( self::d( $this->f->tut, P::VIEW_LEARNER, array( $this->f->l1, $this->f->course_a ) )->allowed );
		Database::current()->execute( Database::current()->prepare( 'DELETE FROM `' . Database::current()->table( 'ccap_course_staff' ) . '` WHERE user_id = %d', $this->f->tut ) );
		do_action( 'course_captain_policy_changed' );
		self::assertDenied( self::d( $this->f->tut, P::VIEW_LEARNER, array( $this->f->l1, $this->f->course_a ) ), Decision::NOT_FOUND );

		self::assertTrue( self::d( $this->f->mgr, P::MANAGE_GRANT, array( $this->f->grant_l1 ) )->allowed );
		self::assertTrue( self::d( $this->f->mgr, P::MANAGE_CERTIFICATE, array( $this->f->certificate_l1 ) )->allowed );
		self::assertTrue( self::d( $this->f->mgr, P::MANAGE_IMPORT, array( $this->f->import_batch ) )->allowed );
		self::set_role( $this->f->mgr, 'subscriber' );
		self::assertFalse( self::d( $this->f->mgr, P::MANAGE_GRANT, array( $this->f->grant_l1 ) )->allowed );
		self::assertFalse( self::d( $this->f->mgr, P::MANAGE_CERTIFICATE, array( $this->f->certificate_l1 ) )->allowed );
		self::assertFalse( self::d( $this->f->mgr, P::MANAGE_IMPORT, array( $this->f->import_batch ) )->allowed );
	}

	/**
	 * RBAC-024/025/026: questions.
	 *
	 * @group RBAC-024
	 * @group RBAC-025
	 * @group RBAC-026
	 */
	public function test_RBAC_024_025_026_questions(): void {
		self::assertFalse( user_can( $this->f->au2, P::EDIT_QUESTION, $this->f->question_a ) );
		self::assertFalse( user_can( $this->f->au2, P::EDIT_QUESTION, $this->f->question_shared ), 'shared question needs rights on all courses' );
		self::assertTrue( user_can( $this->f->au2, P::EDIT_QUESTION, $this->f->question_own_au2 ) );
		self::assertTrue( user_can( $this->f->adm, P::EDIT_QUESTION, $this->f->question_shared ) );
		self::assertFalse( user_can( $this->f->tut, P::EDIT_QUESTION, $this->f->question_a ) );
		self::assertDenied( self::d( $this->f->au2, P::EDIT_QUESTION, array( 999999 ) ), Decision::NOT_FOUND );

		self::set_role( $this->f->au2, 'subscriber' );
		self::assertFalse( user_can( $this->f->au2, P::EDIT_QUESTION, $this->f->question_own_au2 ) );
	}

	/**
	 * RBAC-029/030/037/038/096: attempts are invisible to others, same body.
	 *
	 * @group RBAC-029
	 * @group RBAC-030
	 * @group RBAC-037
	 * @group RBAC-038
	 * @group RBAC-096
	 */
	public function test_RBAC_029_030_037_038_096_attempt_idor(): void {
		$foreign = self::d( $this->f->x, P::VIEW_ATTEMPT, array( $this->f->session_l1 ) );
		$missing = self::d( $this->f->x, P::VIEW_ATTEMPT, array( 999999 ) );
		self::assertDenied( $foreign, Decision::NOT_FOUND );
		self::assertEquals( $missing, $foreign, 'no existence oracle' );
		self::assertSame( $missing->status(), $foreign->status() );
		self::assertEquals( self::d( $this->f->x, P::USE_ATTEMPT, array( 999999 ) ), self::d( $this->f->x, P::USE_ATTEMPT, array( $this->f->session_l1 ) ) );

		self::assertTrue( self::d( $this->f->l1, P::USE_ATTEMPT, array( $this->f->session_l1 ) )->allowed );
		self::assertTrue( self::d( $this->f->l1, P::VIEW_ATTEMPT, array( $this->f->session_l1 ) )->allowed );

		Database::current()->execute( Database::current()->prepare( 'UPDATE `' . Database::current()->table( 'ccap_grant' ) . "` SET status = 'revoked' WHERE id = %d", $this->f->grant_l1 ) );
		do_action( 'course_captain_policy_changed' );
		self::assertDenied( self::d( $this->f->l1, P::USE_ATTEMPT, array( $this->f->session_l1 ) ), Decision::CONTENT_LOCKED, 'revoked' );
		self::assertTrue( self::d( $this->f->l1, P::VIEW_ATTEMPT, array( $this->f->session_l1 ) )->allowed, 'result stays readable' );

		$this->f->lock( $this->f->l1 );
		do_action( 'course_captain_policy_changed' );
		self::assertDenied( self::d( $this->f->l1, P::USE_ATTEMPT, array( $this->f->session_l1 ) ), Decision::ACCOUNT_LOCKED );
	}

	/**
	 * RBAC-032/033/034/035/036: grading and reading attempts by staff.
	 *
	 * @group RBAC-032
	 * @group RBAC-033
	 * @group RBAC-034
	 * @group RBAC-035
	 * @group RBAC-036
	 */
	public function test_RBAC_032_to_036_staff_on_attempts(): void {
		self::assertDenied( self::d( $this->f->tut, P::GRADE_ATTEMPT, array( $this->f->session_tut ) ), Decision::FORBIDDEN, 'self_grading' );
		self::assertTrue( self::d( $this->f->tut, P::GRADE_ATTEMPT, array( $this->f->session_l1_submitted ) )->allowed );
		grant_super_admin( $this->f->tut );
		self::assertFalse( user_can( $this->f->tut, P::GRADE_ATTEMPT, $this->f->session_tut ), 'also for super admins' );
		revoke_super_admin( $this->f->tut );

		self::assertDenied( self::d( $this->f->tut, P::VIEW_ATTEMPT, array( $this->f->session_b ) ), Decision::NOT_FOUND );
		self::assertTrue( self::d( $this->f->tut, P::VIEW_ATTEMPT, array( $this->f->session_l1_submitted ) )->allowed, 'in review, assigned course' );
		self::assertDenied( self::d( $this->f->tut, P::VIEW_ATTEMPT, array( $this->f->session_l1 ) ), Decision::NOT_FOUND, '', );

		self::assertDenied( self::d( $this->f->trb, P::VIEW_ATTEMPT, array( $this->f->session_l1_submitted ) ), Decision::NOT_FOUND );

		self::assertDenied( self::d( $this->f->sup, P::VIEW_ATTEMPT, array( $this->f->session_l1_submitted ) ), Decision::NOT_FOUND );
		self::assertTrue( self::d( $this->f->sup, P::VIEW_LEARNER, array( $this->f->l1 ) )->allowed );

		self::assertFalse( self::d( $this->f->go, P::VIEW_GROUP_PROGRESS, array( $this->f->group_1 ) )->allowed );
		self::assertDenied( self::d( $this->f->go, P::VIEW_LEARNER, array( $this->f->l3 ) ), Decision::NOT_FOUND );
	}

	/**
	 * E-82: foreign attempt details only with justification, always audited.
	 */
	public function test_foreign_attempt_details_need_reason_and_audit(): void {
		self::assertDenied( self::d( $this->f->mgr, P::VIEW_ATTEMPT, array( $this->f->session_l1 ) ), Decision::FORBIDDEN, 'reason_required' );
		$decision = self::d( $this->f->mgr, P::VIEW_ATTEMPT, array( $this->f->session_l1 ), array( 'reason' => 'support ticket 42' ) );
		self::assertTrue( $decision->allowed );
		self::assertTrue( $decision->audit );
	}

	/**
	 * RBAC-042/045/046(part)/090/047: grants and system actors.
	 *
	 * @group RBAC-042
	 * @group RBAC-045
	 * @group RBAC-047
	 * @group RBAC-090
	 */
	public function test_RBAC_042_045_047_090_grants_and_system_actors(): void {
		self::assertFalse( self::d( $this->f->tut, P::CREATE_GRANT, array( $this->f->course_a ) )->allowed );
		self::assertTrue( self::d( $this->f->mgr, P::CREATE_GRANT, array( $this->f->course_a ) )->allowed );
		self::assertFalse( self::d( $this->f->au2, P::MANAGE_GRANT, array( $this->f->grant_l1 ) )->allowed );
		self::assertDenied( self::d( $this->f->mgr, P::MANAGE_GRANT, array( 999999 ) ), Decision::NOT_FOUND );

		$connector = P::instance()->decide( Actor::connector( 'theme' ), P::CREATE_GRANT, array( $this->f->course_a ) );
		self::assertDenied( $connector, Decision::FORBIDDEN, 'feature_unavailable' );
		self::assertTrue( P::instance()->decide( Actor::cron(), P::MANAGE_GRANT, array( 999999 ) )->allowed, 'cron: fixed permission, no current_user_can' );
		self::assertTrue( P::instance()->decide( Actor::cron(), P::MANAGE_GRANT, array( 1 ) )->audit );
		self::assertFalse( P::instance()->decide( Actor::cron(), P::LOCK_LEARNER, array( 1 ) )->allowed );
	}

	/**
	 * RBAC-050/056: a lock blocks learning without changing grants.
	 *
	 * @group RBAC-050
	 * @group RBAC-056
	 */
	public function test_RBAC_050_056_lock_and_unlock(): void {
		$db    = Database::current();
		$lock  = $this->f->lock( $this->f->l1 );
		$grant = $db->row( $db->prepare( 'SELECT status, row_version FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $this->f->grant_l1 ) );
		do_action( 'course_captain_policy_changed' );
		self::assertDenied( self::d( $this->f->l1, P::LEARN, array( $this->f->course_a ) ), Decision::ACCOUNT_LOCKED );

		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_learner_lock' ) . '` SET lifted_at = %s WHERE id = %d', $db->now(), $lock ) );
		do_action( 'course_captain_policy_changed' );
		self::assertTrue( self::d( $this->f->l1, P::LEARN, array( $this->f->course_a ) )->allowed );
		self::assertSame( $grant, $db->row( $db->prepare( 'SELECT status, row_version FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $this->f->grant_l1 ) ) );

		$this->f->lock( $this->f->go );
		do_action( 'course_captain_policy_changed' );
		self::assertDenied( self::d( $this->f->go, P::MANAGE_GROUP, array( $this->f->group_1 ) ), Decision::ACCOUNT_LOCKED );
		self::assertTrue( self::d( $this->f->l3, P::LEARN, array( $this->f->course_a ) )->allowed, 'members keep access' );
	}

	/**
	 * RBAC-051/053/057/058: groups.
	 *
	 * @group RBAC-051
	 * @group RBAC-053
	 * @group RBAC-057
	 * @group RBAC-058
	 */
	public function test_RBAC_051_053_057_058_groups(): void {
		self::assertTrue( self::d( $this->f->go, P::MANAGE_GROUP, array( $this->f->group_1 ) )->allowed );
		self::assertDenied( self::d( $this->f->x, P::MANAGE_GROUP, array( $this->f->group_1 ) ), Decision::NOT_FOUND );
		self::assertDenied( self::d( $this->f->l3, P::MANAGE_GROUP, array( $this->f->group_1 ) ), Decision::NOT_FOUND, '' );

		self::assertTrue( self::d( $this->f->trb, P::VIEW_LEARNER, array( $this->f->l3, $this->f->course_a ) )->allowed );
		self::assertDenied( self::d( $this->f->trb, P::VIEW_LEARNER, array( $this->f->l1, $this->f->course_a ) ), Decision::NOT_FOUND );

		$db = Database::current();
		$db->compare_and_swap( 'ccap_group', $this->f->group_1, 0, array( 'owner_user_id' => $this->f->l3 ) );
		do_action( 'course_captain_policy_changed' );
		self::assertDenied( self::d( $this->f->go, P::MANAGE_GROUP, array( $this->f->group_1 ) ), Decision::NOT_FOUND );

		$db->compare_and_swap( 'ccap_group', $this->f->group_1, 1, array( 'status' => 'revoked' ) );
		do_action( 'course_captain_policy_changed' );
		self::assertTrue( self::d( $this->f->l3, P::MANAGE_GROUP, array( $this->f->group_1 ) )->allowed, 'read stays' );
		self::assertDenied( self::d( $this->f->l3, P::MANAGE_GROUP, array( $this->f->group_1 ), array( 'write' => true ) ), Decision::FORBIDDEN, 'group_revoked' );
	}

	/**
	 * RBAC-054/055: claiming seats.
	 *
	 * @group RBAC-054
	 * @group RBAC-055
	 */
	public function test_RBAC_054_055_claims(): void {
		$db = Database::current();
		$db->compare_and_swap( 'ccap_group', $this->f->group_1, 0, array( 'claim_policy' => 'invited_address_only' ) );
		$this->f->seat(
			$this->f->group_1,
			0,
			'invited',
			array(
				'invite_email'     => get_userdata( $this->f->l2 )->user_email,
				'claim_token_hash' => hash( 'sha256', 'INVITE-L2' ),
			)
		);
		self::assertDenied( self::d( $this->f->x, P::CLAIM_SEAT, array(), array( 'token' => 'INVITE-L2' ) ), Decision::FORBIDDEN, 'invite_other_account' );
		self::assertTrue( self::d( $this->f->l2, P::CLAIM_SEAT, array(), array( 'token' => 'INVITE-L2' ) )->allowed );
		self::assertDenied( self::d( $this->f->l2, P::CLAIM_SEAT, array(), array( 'token' => 'WRONG' ) ), Decision::FORBIDDEN, 'invalid_token' );

		$db->compare_and_swap( 'ccap_group', $this->f->group_1, 1, array( 'status' => 'suspended' ) );
		do_action( 'course_captain_policy_changed' );
		self::assertDenied( self::d( $this->f->l2, P::CLAIM_SEAT, array(), array( 'token' => 'INVITE-L2' ) ), Decision::FORBIDDEN, 'group_not_active' );
	}

	/**
	 * RBAC-060/062/064/065/066: certificates.
	 *
	 * @group RBAC-060
	 * @group RBAC-062
	 * @group RBAC-064
	 * @group RBAC-065
	 * @group RBAC-066
	 */
	public function test_RBAC_060_062_064_065_066_certificates(): void {
		self::assertDenied( self::d( $this->f->x, P::VIEW_CERTIFICATE, array( $this->f->certificate_l1 ) ), Decision::NOT_FOUND );
		self::assertDenied( self::d( $this->f->tut, P::MANAGE_CERTIFICATE, array( $this->f->certificate_l1 ) ), Decision::FORBIDDEN );

		( new \WP_User( $this->f->l1 ) )->add_cap( 'ccap_manage_certificates' );
		self::assertDenied( self::d( $this->f->l1, P::MANAGE_CERTIFICATE, array( $this->f->certificate_l1 ) ), Decision::FORBIDDEN, 'own_certificate' );
		( new \WP_User( $this->f->l1 ) )->remove_cap( 'ccap_manage_certificates' );

		Database::current()->execute( Database::current()->prepare( 'UPDATE `' . Database::current()->table( 'ccap_grant' ) . "` SET status = 'revoked' WHERE id = %d", $this->f->grant_l1 ) );
		do_action( 'course_captain_policy_changed' );
		self::assertTrue( self::d( $this->f->l1, P::VIEW_CERTIFICATE, array( $this->f->certificate_l1 ) )->allowed, 'history stays' );

		$this->f->lock( $this->f->l1 );
		do_action( 'course_captain_policy_changed' );
		self::assertDenied( self::d( $this->f->l1, P::VIEW_CERTIFICATE, array( $this->f->certificate_l1 ) ), Decision::ACCOUNT_LOCKED );
	}

	/**
	 * RBAC-067/068/072/074: protected files.
	 *
	 * @group RBAC-067
	 * @group RBAC-068
	 * @group RBAC-072
	 * @group RBAC-074
	 */
	public function test_RBAC_067_068_072_074_files(): void {
		self::assertTrue( self::d( $this->f->l1, P::DOWNLOAD_FILE, array( $this->f->file_a ) )->allowed );
		self::assertTrue( self::d( $this->f->adm, P::DOWNLOAD_FILE, array( $this->f->file_c ) )->allowed, 'editor of the draft' );
		self::assertDenied( self::d( $this->f->x, P::DOWNLOAD_FILE, array( $this->f->file_a ) ), Decision::CONTENT_LOCKED );
		self::assertDenied( self::d( $this->f->l1, P::DOWNLOAD_FILE, array( $this->f->file_c ) ), Decision::NOT_FOUND );
		self::assertEquals( self::d( $this->f->l1, P::DOWNLOAD_FILE, array( $this->f->file_c ) ), self::d( $this->f->l1, P::DOWNLOAD_FILE, array( wp_generate_uuid4() ) ) );
		self::assertDenied( self::d( $this->f->blk, P::DOWNLOAD_FILE, array( $this->f->file_a ) ), Decision::ACCOUNT_LOCKED );
	}

	/**
	 * RBAC-077/080/085/086/089: import, export, personal data.
	 *
	 * @group RBAC-077
	 * @group RBAC-080
	 * @group RBAC-085
	 * @group RBAC-086
	 * @group RBAC-089
	 */
	public function test_RBAC_077_080_085_086_089_import_export_privacy(): void {
		self::assertDenied( self::d( $this->f->au2, P::MANAGE_IMPORT, array( $this->f->import_batch ) ), Decision::FORBIDDEN );
		( new \WP_User( $this->f->au2 ) )->add_cap( 'ccap_import_content' );
		self::assertTrue( self::d( $this->f->au2, P::MANAGE_IMPORT, array( $this->f->import_batch ), array( 'step' => 'validate' ) )->allowed );
		self::assertDenied( self::d( $this->f->au2, P::MANAGE_IMPORT, array( $this->f->import_batch ), array( 'step' => 'publish' ) ), Decision::FORBIDDEN );

		$export = array(
			'created_by' => $this->f->mgr,
			'expires_at' => Database::current()->now( 600 ),
			'kind'       => 'report',
		);
		self::assertTrue( self::d( $this->f->mgr, P::DOWNLOAD_EXPORT, array(), $export )->allowed );
		self::assertDenied( self::d( $this->f->x, P::DOWNLOAD_EXPORT, array(), $export ), Decision::NOT_FOUND );
		$export['expires_at'] = Database::current()->now( -1 );
		self::assertDenied( self::d( $this->f->mgr, P::DOWNLOAD_EXPORT, array(), $export ), Decision::FORBIDDEN, 'export_expired' );

		self::assertTrue( self::d( $this->f->blk, P::EXPORT_PERSONAL_DATA, array( $this->f->blk ) )->allowed, 'also when locked' );
		self::assertFalse( self::d( $this->f->l1, P::EXPORT_PERSONAL_DATA, array( $this->f->x ) )->allowed );
	}

	/**
	 * RBAC-091/092: escalation protection.
	 *
	 * @group RBAC-091
	 * @group RBAC-092
	 */
	public function test_RBAC_091_092_escalation(): void {
		wp_set_current_user( $this->f->mgr );
		$roles = EscalationGuard::filter_editable_roles( wp_roles()->roles );
		self::assertArrayNotHasKey( 'administrator', $roles );
		self::assertArrayNotHasKey( Roles::COURSE_MANAGER, $roles );
		self::assertArrayHasKey( Roles::AUTHOR, $roles );
		self::assertArrayHasKey( Roles::TRAINER, $roles );
		// §3.5 lets managers assign support, but support carries #21 which managers lack;
		// the stricter rule "never above the own set" wins (open point in the R1-03 proof).
		self::assertArrayNotHasKey( Roles::SUPPORT, $roles );
		wp_set_current_user( $this->f->adm );
		self::assertArrayHasKey( 'administrator', EscalationGuard::filter_editable_roles( wp_roles()->roles ) );
		wp_set_current_user( 0 );

		self::assertDenied( self::d( $this->f->sup, P::LOCK_LEARNER, array( $this->f->mgr ) ), Decision::FORBIDDEN, 'higher_privileges' );
		self::assertDenied( self::d( $this->f->sup, P::LOCK_LEARNER, array( $this->f->adm ) ), Decision::FORBIDDEN, 'higher_privileges' );
		self::assertDenied( self::d( $this->f->sup, P::LOCK_LEARNER, array( $this->f->sup ) ), Decision::FORBIDDEN, 'self_lock' );
		self::assertTrue( self::d( $this->f->sup, P::LOCK_LEARNER, array( $this->f->l1 ) )->allowed );
	}

	/**
	 * RBAC-098/099: module grants and dependent grants (E-286, E-287).
	 *
	 * @group RBAC-098
	 * @group RBAC-099
	 */
	public function test_RBAC_098_099_module_and_dependent_grants(): void {
		$this->f->grant( $this->f->l2, $this->f->course_a, array( 'module_id' => $this->f->module_a1 ) );
		do_action( 'course_captain_policy_changed' );
		self::assertTrue( self::d( $this->f->l2, P::LEARN, array( $this->f->course_a ), array( 'module' => $this->f->module_a1 ) )->allowed );
		self::assertDenied( self::d( $this->f->l2, P::LEARN, array( $this->f->course_a ), array( 'module' => $this->f->module_a2 ) ), Decision::CONTENT_LOCKED );
		self::assertFalse( self::d( $this->f->l2, P::LEARN, array( $this->f->course_a ) )->allowed, 'no whole-course access' );

		$addon = $this->f->grant( $this->f->x, $this->f->course_a, array( 'depends_on_course_id' => $this->f->course_b ) );
		do_action( 'course_captain_policy_changed' );
		self::assertDenied( self::d( $this->f->x, P::LEARN, array( $this->f->course_a ) ), Decision::CONTENT_LOCKED );
		$db = Database::current();
		self::assertSame( 'active', $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $addon ) ) );
		$this->f->grant( $this->f->x, $this->f->course_b );
		do_action( 'course_captain_policy_changed' );
		self::assertTrue( self::d( $this->f->x, P::LEARN, array( $this->f->course_a ) )->allowed );
	}

	/**
	 * Bundles grant access to contained courses (E-25a, E-297).
	 */
	public function test_bundle_grants_access_to_contained_courses(): void {
		$db     = Database::current();
		$bundle = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Bundle',
			)
		);
		$db->insert(
			'ccap_course_relation',
			array(
				'course_id'         => $bundle,
				'related_course_id' => $this->f->course_b,
			),
			array( 'created_at', 'updated_at' )
		);
		self::assertFalse( self::d( $this->f->l1, P::LEARN, array( $this->f->course_b ) )->allowed );
		$this->f->grant( $this->f->l1, $bundle );
		do_action( 'course_captain_policy_changed' );
		self::assertTrue( self::d( $this->f->l1, P::LEARN, array( $this->f->course_b ) )->allowed );
	}

	/**
	 * RBAC-101/102/103 (policy part of R1-46): grading submissions (E-288).
	 *
	 * @group RBAC-101
	 * @group RBAC-102
	 * @group RBAC-103
	 */
	public function test_RBAC_101_102_103_submission_grading(): void {
		$db         = Database::current();
		$submission = static fn( int $user, int $course ): int => $db->insert(
			'ccap_assignment_submission',
			array(
				'uid'          => $db->uuid(),
				'user_id'      => $user,
				'course_id'    => $course,
				'activity_id'  => 1,
				'activity_uid' => $db->uuid(),
				'status'       => 'submitted',
			),
			array( 'created_at', 'updated_at' )
		);
		$in_b       = $submission( $this->f->x, $this->f->course_b );
		self::assertTrue( self::d( $this->f->au2, P::GRADE_SUBMISSION, array( $in_b ) )->allowed, 'author of the course' );
		self::assertTrue( self::d( $this->f->au2, P::GRADE_SUBMISSION, array( $in_b ) )->audit );
		self::assertFalse( self::d( $this->f->tut, P::GRADE_SUBMISSION, array( $in_b ) )->allowed, 'trainer of another course' );
		self::assertFalse( self::d( $this->f->sup, P::GRADE_SUBMISSION, array( $in_b ) )->allowed );

		$own = $submission( $this->f->tut, $this->f->course_a );
		self::assertDenied( self::d( $this->f->tut, P::GRADE_SUBMISSION, array( $own ) ), Decision::FORBIDDEN, 'self_grading' );
		$admin_own = $submission( $this->f->adm, $this->f->course_a );
		self::assertDenied( self::d( $this->f->adm, P::GRADE_SUBMISSION, array( $admin_own ) ), Decision::FORBIDDEN, 'self_grading' );
	}

	/**
	 * RBAC-095: deactivation keeps roles; confirmed uninstall removes own
	 * roles and caps, foreign roles stay untouched.
	 *
	 * @group RBAC-095
	 */
	public function test_RBAC_095_roles_on_deactivate_and_uninstall(): void {
		Plugin::deactivate();
		self::assertNotNull( get_role( Roles::TRAINER ) );
		update_option( UninstallManifest::GENERAL_SETTINGS_OPTION, array( 'values' => array( UninstallManifest::DELETE_DATA_KEY => true ) ) );
		Uninstaller::uninstall_current_site();
		foreach ( Roles::ALL as $slug ) {
			self::assertNull( get_role( $slug ), $slug );
		}
		self::assertNotNull( get_role( 'shop_manager' ) );
		self::assertTrue( get_role( 'shop_manager' )->has_cap( 'manage_woocommerce' ) );
		self::assertSame( array(), self::ccap_caps_of( 'administrator' ) );
	}
}
