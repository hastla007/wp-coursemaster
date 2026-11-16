<?php
/**
 * R1-03: pure parts of the policy (no database).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Kernel\Capabilities;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Decision;
use CourseCaptain\Policy\MetaCaps;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Policy\SystemPermissions;

/**
 * Role table, meta cap list, decisions, scopes, system actors.
 *
 * @group R1-03
 */
final class PolicyUnitTest extends TestCase {

	/**
	 * R0-07 §3.1: role defaults use only known caps; nobody but the
	 * administrator gets settings, integrations or maintenance.
	 */
	public function test_role_table(): void {
		self::assertSame( array( 'ccap_course_manager', 'ccap_author', 'ccap_trainer', 'ccap_support' ), Roles::ALL );
		foreach ( Roles::capabilities() as $slug => $caps ) {
			foreach ( $caps as $cap ) {
				self::assertTrue( in_array( $cap, Capabilities::PRIMITIVE, true ) || in_array( $cap, array( 'read', 'upload_files' ), true ), "{$slug}: {$cap}" );
			}
			foreach ( array( 'ccap_manage_settings', 'ccap_manage_integrations', 'ccap_run_maintenance' ) as $admin_only ) {
				self::assertNotContains( $admin_only, $caps, $slug );
			}
		}
		self::assertCount( 22, Roles::capabilities()[ Roles::COURSE_MANAGER ], 'read, upload_files + caps 1–18, 22, 24' );
	}

	/**
	 * 24 meta caps plus M-10a, all prefixed, none equal to a primitive except #16.
	 */
	public function test_meta_caps(): void {
		self::assertCount( 25, PolicyService::META );
		self::assertSame( array( 'ccap_manage_learner_progress' ), array_values( array_intersect( PolicyService::META, Capabilities::PRIMITIVE ) ) );
		foreach ( PolicyService::META as $cap ) {
			self::assertStringStartsWith( 'ccap_', $cap );
		}
		$map = MetaCaps::post_type_capabilities();
		self::assertSame( 'ccap_edit_courses', $map['edit_posts'] );
		self::assertSame( 'ccap_edit_others_courses', $map['edit_others_posts'] );
		self::assertSame( 'ccap_publish_courses', $map['publish_posts'] );
		self::assertSame( 'ccap_edit_courses', $map['create_posts'] );
	}

	/**
	 * P-08 status codes.
	 */
	public function test_decision_codes(): void {
		self::assertSame( 404, Decision::not_found()->status() );
		self::assertSame( 403, Decision::deny( Decision::FORBIDDEN )->status() );
		self::assertSame( 403, Decision::deny( Decision::CONTENT_LOCKED, 'expired' )->status() );
		self::assertSame( 401, Decision::deny( Decision::AUTH_REQUIRED )->status() );
		self::assertSame( 200, Decision::allow( array( 'read' ) )->status() );
		self::assertSame( array( 'do_not_allow' ), Decision::not_found()->caps );
		self::assertEquals( Decision::not_found(), Decision::not_found() );
	}

	/**
	 * Scope matching (E-286).
	 */
	public function test_scopes(): void {
		self::assertTrue( Relations::scope_matches( 'all', 'exam' ) );
		self::assertTrue( Relations::scope_matches( 'view,download', 'download' ) );
		self::assertFalse( Relations::scope_matches( 'view', 'exam' ) );
		self::assertTrue( Relations::scope_matches( 'exam', 'view' ), 'exam implies view' );
	}

	/**
	 * P-12: system actors have fixed permissions; connectors none in Free.
	 */
	public function test_system_actors(): void {
		self::assertTrue( SystemPermissions::decide( Actor::cron(), PolicyService::MANAGE_GRANT )->allowed );
		self::assertFalse( SystemPermissions::decide( Actor::cron(), PolicyService::VIEW_ATTEMPT )->allowed );
		self::assertTrue( SystemPermissions::decide( Actor::cli(), PolicyService::MANAGE_IMPORT )->allowed );
		self::assertSame( 'feature_unavailable', SystemPermissions::decide( Actor::connector( 'x' ), PolicyService::CREATE_GRANT )->reason );
		self::assertSame( 'connector:x', Actor::connector( 'x' )->key() );
		self::assertSame( 'user:0', Actor::user( -5 )->key() );
	}
}
