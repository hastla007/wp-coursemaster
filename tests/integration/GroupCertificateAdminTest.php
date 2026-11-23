<?php
/**
 * R1-35: Free group and base certificate management (R0-29 §4.1, §4.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GroupService;
use CourseCaptain\Admin\CertificatesScreen;
use CourseCaptain\Admin\GroupsScreen;
use CourseCaptain\Certificate\CertificateAdmin;
use CourseCaptain\Certificate\CertificateService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Settings\EffectiveSettings;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group R1-35
 */
final class GroupCertificateAdminTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Group owner.
	 *
	 * @var int
	 */
	private int $owner;

	/**
	 * Learners.
	 *
	 * @var list<int>
	 */
	private array $learners = array();

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$this->admin = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->owner = (int) self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'owner' . wp_generate_password( 6, false, false ) . '@example.org',
			)
		);
		for ( $i = 0; $i < 4; $i++ ) {
			$this->learners[] = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		}
		$this->course = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Course A',
				'post_author' => $this->admin,
			)
		);
		PolicyService::flush();
		wp_set_current_user( $this->admin );
	}

	/**
	 * Run a group operation as a user with a valid nonce.
	 *
	 * @param string               $op    Operation.
	 * @param int                  $group Group.
	 * @param array<string, mixed> $data  Fields.
	 * @param int                  $user  User (default admin).
	 * @return array{op: string, group: int, result: string, count: int, token: string}
	 */
	private function op( string $op, int $group, array $data = array(), int $user = 0 ): array {
		$user = 0 === $user ? $this->admin : $user;
		wp_set_current_user( $user );
		$out = GroupsScreen::process(
			array(
				'op'       => $op,
				'group'    => $group,
				'_wpnonce' => wp_create_nonce( 'ccap_group_' . $op . '_' . $group ),
			) + $data,
			$user
		);
		wp_set_current_user( $this->admin );
		PolicyService::flush();
		return $out;
	}

	/**
	 * Group with groups switched on globally.
	 *
	 * @param int $seats Seats.
	 * @return int
	 */
	private function group( int $seats = 10 ): int {
		Settings::save( Actor::user( $this->admin ), 'enrollment', array( 'groups_enabled' => true ), array( 'groups_enabled' ), Settings::rev( 'enrollment' ) );
		$out = $this->op(
			'create',
			0,
			array(
				'name'              => 'Team Blue',
				'owner'             => (string) $this->owner,
				'seats'             => $seats,
				'courses'           => array( $this->course ),
				'unlimited_confirm' => '1',
				'request_key'       => wp_generate_uuid4(),
			)
		);
		self::assertSame( 'ok', $out['result'] );
		return $out['group'];
	}

	/**
	 * Group row.
	 *
	 * @param int $group Group.
	 * @return array<string, mixed>
	 */
	private static function row( int $group ): array {
		$db = Database::current();
		return (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $group ) );
	}

	/**
	 * Claim an invitation as a learner.
	 *
	 * @param int $group   Group.
	 * @param int $learner Learner.
	 * @return int Seat.
	 */
	private function member( int $group, int $learner ): int {
		$invite = GroupService::current()->invite( Actor::user( $this->admin ), $group, 'm' . $learner . '@example.org' );
		self::assertIsArray( $invite );
		PolicyService::flush();
		return GroupService::current()->claim( Actor::user( $learner ), $invite['token'] );
	}

	/**
	 * Audit rows of an action.
	 *
	 * @param string $action Action.
	 * @return list<array<string, mixed>>
	 */
	private static function audit( string $action ): array {
		$db = Database::current();
		return $db->rows( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s ORDER BY id', $action ) );
	}

	/**
	 * GRP-001 / GRP-002 / GRP-003: groups switch (global and per course),
	 * manual creation, field errors.
	 *
	 * @group GRP-001
	 * @group GRP-002
	 * @group GRP-003
	 * @group GM-106
	 */
	public function test_GRP_001_002_003_create(): void {
		$base = array(
			'name'              => 'Team https://spam.example.com Blue',
			'owner'             => (string) get_userdata( $this->owner )->user_email,
			'seats'             => 10,
			'courses'           => array( $this->course ),
			'unlimited_confirm' => '1',
			'request_key'       => wp_generate_uuid4(),
		);
		self::assertSame( 'ccap_groups_disabled', $this->op( 'create', 0, $base )['result'], 'groups off by default (E-222)' );

		// INH-026: course override switches groups on for this course only.
		EffectiveSettings::save_course( Actor::user( $this->admin ), $this->course, array( 'enrollment.groups_enabled' => true ) );
		CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
		$out = $this->op( 'create', 0, $base );
		self::assertSame( 'ok', $out['result'] );
		$row = self::row( $out['group'] );
		self::assertSame( 'Team Blue', $row['name'], 'links removed (SEAT-021)' );
		self::assertSame( 'manual', $row['source_type'] );
		self::assertSame( $this->owner, (int) $row['owner_user_id'] );
		self::assertNull( $row['valid_until'] );
		$db = Database::current();
		self::assertSame( '0', $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_grant' ) . '` WHERE user_id = %d', $this->owner ) ), 'owner gets no grant' );
		self::assertCount( 1, self::audit( 'group.created' ) );

		self::assertSame( 'ccap_invalid_param', $this->op( 'create', 0, array_merge( $base, array( 'owner' => 'nobody', 'request_key' => wp_generate_uuid4() ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		self::assertSame( 'ccap_invalid_param', $this->op( 'create', 0, array_merge( $base, array( 'seats' => 0, 'request_key' => wp_generate_uuid4() ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		self::assertSame( 'ccap_confirmation_required', $this->op( 'create', 0, array_merge( $base, array( 'unlimited_confirm' => '', 'request_key' => wp_generate_uuid4() ) ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		self::assertCount( 1, self::audit( 'group.created' ), 'nothing stored on errors' );

		// Rights and nonce.
		self::assertSame( 'ccap_invalid_nonce', GroupsScreen::process( array( 'op' => 'create', 'group' => 0, '_wpnonce' => 'x' ), $this->admin )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		self::assertSame( 'ccap_forbidden', $this->op( 'create', 0, array_merge( $base, array( 'request_key' => wp_generate_uuid4() ) ), $this->learners[0] )['result'] );
	}

	/**
	 * GRP-004 / GRP-005 / GRP-011 / GRP-013: seats up, reduction with
	 * preview and confirmation, invitations with invalid/limited, withdraw.
	 *
	 * @group GRP-004
	 * @group GRP-005
	 * @group GRP-011
	 * @group GRP-013
	 */
	public function test_GRP_004_005_011_013_seats_and_invitations(): void {
		$group = $this->group( 3 );
		$out   = $this->op( 'invite', $group, array( 'emails' => "a@example.org\nnot-an-address\nb@example.org" ) );
		self::assertSame( 'partial_1_0', $out['result'] );
		self::assertSame( 2, $out['count'] );
		$this->member( $group, $this->learners[0] );
		self::assertSame( 'partial_0_1', $this->op( 'invite', $group, array( 'emails' => 'c@example.org' ) )['result'], 'no free seat → limited' );

		self::assertSame( 'ok', $this->op( 'seats', $group, array( 'seats' => 5 ) )['result'] );
		self::assertSame( 5, (int) self::row( $group )['seats'] );

		$preview = GroupsScreen::html(
			array(
				'group'         => $group,
				'preview_seats' => 1,
			),
			$this->admin
		);
		self::assertStringContainsString( 'This affects 2 seats', $preview );
		$denied = $this->op( 'seats', $group, array( 'seats' => 1 ) );
		self::assertSame( 'ccap_confirmation_required', $denied['result'] );
		self::assertSame( 2, $denied['count'] );
		self::assertSame( 5, (int) self::row( $group )['seats'], 'preview writes nothing' );
		self::assertSame( 2, $this->op( 'seats', $group, array( 'seats' => 1, 'confirm' => '1' ) )['count'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		self::assertSame( 1, GroupService::current()->occupied( $group ), 'open invitations removed first, member stays' );

		// GRP-013: withdraw an open invitation.
		$this->op( 'seats', $group, array( 'seats' => 3 ) );
		$invite = GroupService::current()->invite( Actor::user( $this->admin ), $group, 'w@example.org' );
		self::assertSame( 'ccap_confirmation_required', $this->op( 'withdraw', $group, array( 'seat' => $invite['seat_id'] ) )['result'] );
		self::assertSame( 'ok', $this->op( 'withdraw', $group, array( 'seat' => $invite['seat_id'], 'confirm' => '1' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$db   = Database::current();
		$seat = $db->row( $db->prepare( 'SELECT status, invite_email, claim_token_hash FROM `' . $db->table( 'ccap_seat' ) . '` WHERE id = %d', $invite['seat_id'] ) );
		self::assertSame( 'removed', $seat['status'] );
		self::assertNull( $seat['invite_email'], 'address deleted at once' );
		self::assertNull( $seat['claim_token_hash'] );
	}

	/**
	 * GRP-006 / GRP-007: validity follows except locked grants; status
	 * suspend/resume/end; an ended group is read-only.
	 *
	 * @group GRP-006
	 * @group GRP-007
	 * @group GM-097
	 * @group GM-100
	 */
	public function test_GRP_006_007_validity_and_status(): void {
		$group = $this->group();
		$one   = $this->member( $group, $this->learners[0] );
		$two   = $this->member( $group, $this->learners[1] );
		$db    = Database::current();
		$grant = $db->table( 'ccap_grant' );
		$db->execute( $db->prepare( "UPDATE `{$grant}` SET valid_until = '2040-01-01 00:00:00', valid_until_locked = 1 WHERE seat_id = %d", $two ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::assertSame( 'ccap_confirmation_required', $this->op( 'validity', $group, array( 'valid_until' => '' ) )['result'] );
		$out = $this->op( 'validity', $group, array( 'valid_until' => '2031-06-30' ) );
		self::assertSame( 'ok', $out['result'] );
		self::assertSame( 1, $out['count'] );
		self::assertSame( '2031-06-30 23:59:59', $db->scalar( $db->prepare( "SELECT valid_until FROM `{$grant}` WHERE seat_id = %d", $one ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::assertSame( '2040-01-01 00:00:00', $db->scalar( $db->prepare( "SELECT valid_until FROM `{$grant}` WHERE seat_id = %d", $two ) ), 'locked end stays' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::assertSame( '2031-06-30 23:59:59', self::row( $group )['valid_until'] );

		self::assertSame( 'ok', $this->op( 'status', $group, array( 'status' => 'suspended' ) )['result'] );
		self::assertSame( 'suspended', $db->scalar( $db->prepare( "SELECT status FROM `{$grant}` WHERE seat_id = %d", $one ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::assertSame( 'ok', $this->op( 'status', $group, array( 'status' => 'active' ) )['result'] );
		self::assertSame( 'active', $db->scalar( $db->prepare( "SELECT status FROM `{$grant}` WHERE seat_id = %d", $one ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::assertSame( 'ccap_confirmation_required', $this->op( 'status', $group, array( 'status' => 'revoked' ) )['result'] );
		self::assertSame( 'ok', $this->op( 'status', $group, array( 'status' => 'revoked', 'confirm' => '1' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		self::assertSame( 'revoked', $db->scalar( $db->prepare( "SELECT status FROM `{$grant}` WHERE seat_id = %d", $one ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::assertSame( 'ccap_forbidden', $this->op( 'rename', $group, array( 'name' => 'New' ) )['result'], 'ended group read-only' );
		self::assertSame( 'ccap_conflict', $this->op( 'validity', $group, array( 'valid_until' => '2032-01-01' ) )['result'] );
		self::assertStringContainsString( 'can only be viewed', GroupsScreen::html( array( 'group' => $group ), $this->admin ) );
	}

	/**
	 * GRP-008 / GRP-009 / GRP-010 / GRP-018: owner change with reason
	 * (old owner 404, link invalid), claim policy disables the link,
	 * member list without learning data and with masked addresses.
	 *
	 * @group GRP-008
	 * @group GRP-009
	 * @group GRP-010
	 * @group GRP-018
	 * @group GM-105
	 */
	public function test_GRP_008_009_010_018_owner_policy_members(): void {
		$group = $this->group();
		$link  = $this->op( 'link', $group );
		self::assertStringStartsWith( 'ccg_', $link['token'] );
		self::assertNotNull( self::row( $group )['join_token_hash'] );
		GroupService::current()->invite( Actor::user( $this->owner ), $group, 'visible@example.org' );

		$owner_view = GroupService::current()->members( Actor::user( $this->owner ), $group );
		self::assertSame( 'visible@example.org', $owner_view[0]['address'], 'owner sees the open invitation in plain text' );
		$admin_view = GroupService::current()->members( Actor::user( $this->admin ), $group );
		self::assertSame( 'v***@example.org', $admin_view[0]['address'] );
		$html = GroupsScreen::html( array( 'group' => $group ), $this->admin );
		self::assertStringNotContainsString( 'visible@example.org', $html );
		foreach ( array( 'Progress', 'Score', 'Certificate' ) as $word ) {
			self::assertStringNotContainsString( $word, $html, 'GR-7' );
		}

		self::assertSame( 'ccap_reason_required', $this->op( 'owner', $group, array( 'owner' => (string) $this->learners[0] ) )['result'] );
		self::assertSame( 'ok', $this->op( 'owner', $group, array( 'owner' => (string) $this->learners[0], 'reason' => 'Ticket 42' ) )['result'] ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		self::assertSame( $this->learners[0], (int) self::row( $group )['owner_user_id'] );
		self::assertNull( self::row( $group )['join_token_hash'], 'old group link invalid' );
		try {
			GroupService::current()->members( Actor::user( $this->owner ), $group );
			self::fail( 'old owner' );
		} catch ( CourseException $e ) {
			self::assertSame( 404, $e->status );
		}
		self::assertCount( 1, self::audit( 'group.owner_changed' ) );

		$this->op( 'link', $group );
		self::assertSame( 'ok', $this->op( 'policy', $group, array( 'claim_policy' => 'invited_address_only' ) )['result'] );
		self::assertSame( 'invited_address_only', self::row( $group )['claim_policy'] );
		self::assertNull( self::row( $group )['join_token_hash'], 'link disabled' );
		self::assertSame( 'ccap_invalid_param', $this->op( 'link', $group )['result'] );
		self::assertSame( 'ok', $this->op( 'rename', $group, array( 'name' => 'Team Red' ) )['result'] );
		self::assertSame( 'Team Red', self::row( $group )['name'] );

		// GRP-012: send again (NTF-029 budget in the notification service).
		$seat = (int) $admin_view[0]['seat_id'];
		self::assertSame( 'ok', $this->op( 'resend', $group, array( 'seat' => $seat ) )['result'] );
		self::assertCount( 1, self::audit( 'invitation_resent' ) );
	}

	/**
	 * Certificate row inserted directly (list/export only).
	 *
	 * @param string $holder Printed holder name.
	 * @param string $status issued|revoked.
	 * @return string UID.
	 */
	private function certificate( string $holder, string $status = 'issued' ): string {
		$revision = CoursePublisher::current()->publish( Actor::user( $this->admin ), $this->course );
		$db       = Database::current();
		$uid      = $db->uuid();
		$snapshot = array(
			'display_id' => 'CC-' . strtoupper( substr( $uid, 0, 4 ) ) . '-0000',
			'holder'     => array( 'display_name' => $holder ),
			'course'     => array( 'title' => 'Course A' ),
			'issuer'     => array(
				'name' => 'Site',
				'logo' => array(
					'attachment_id' => 777,
					'sha256'        => null,
					'alt'           => '',
				),
			),
			'template'   => array( 'kind' => 'base' ),
		);
		$db->insert(
			'ccap_certificate',
			array(
				'certificate_uid'    => $uid,
				'revision_no'        => 1,
				'user_id'            => $this->learners[0],
				'course_id'          => $this->course,
				'course_revision_id' => $revision,
				'evidence_type'      => 'course_completion',
				'evidence_id'        => wp_rand( 1, 999999 ),
				'status'             => $status,
				'template_version'   => '1',
				'snapshot'           => (string) wp_json_encode( $snapshot ),
				'snapshot_hash'      => 'sha256:' . hash( 'sha256', $uid ),
				'verify_token_hash'  => hash( 'sha256', 'v' . $uid ),
				'issued_at'          => $db->now(),
				'created_at'         => $db->now(),
			)
		);
		return $uid;
	}

	/**
	 * CRT-026 / CRT-024 / CRT-063: list and filter, CSV only with reason
	 * (no email, formula-safe, audited), logo usage warning, no Pro elements.
	 *
	 * @group CRT-024
	 * @group CRT-026
	 * @group CRT-063
	 */
	public function test_CRT_024_026_063_list_export_logo(): void {
		update_user_meta( $this->learners[0], 'nickname', 'x' );
		$this->certificate( '=HYPERLINK("http://evil")' );
		$this->certificate( 'Rita Revoked', 'revoked' );
		$admin = Actor::user( $this->admin );
		self::assertSame( 2, CertificateAdmin::query( $admin )['total'] );
		self::assertSame( 1, CertificateAdmin::query( $admin, array( 'status' => 'revoked' ) )['total'] );
		self::assertSame( 1, CertificateAdmin::query( $admin, array( 'status' => 'valid' ) )['total'] );
		self::assertSame( 0, CertificateAdmin::query( $admin, array( 'kind' => 'designer' ) )['total'] );
		try {
			CertificateAdmin::query( Actor::user( $this->learners[1] ) );
			self::fail( 'learner' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_forbidden', $e->error_code );
		}

		try {
			CertificateAdmin::export_csv( $admin, array(), '  ' );
			self::fail( 'reason' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_reason_required', $e->error_code );
		}
		$csv = CertificateAdmin::export_csv( $admin, array(), 'Audit request 7' );
		self::assertStringContainsString( "\"'=HYPERLINK(\"\"http://evil\"\")\"", $csv, 'formula neutralized' );
		self::assertStringNotContainsString( '@', $csv, 'no email column' );
		self::assertStringContainsString( 'display_id,certificate_uid', $csv );
		$audit = self::audit( 'certificates_exported' );
		self::assertCount( 1, $audit );
		self::assertSame( 'Audit request 7', $audit[0]['reason'] );
		self::assertSame( 2, json_decode( (string) $audit[0]['context'], true )['count'] );

		$usage = CertificateAdmin::logo_usage( 777 );
		self::assertSame( 2, $usage['certificates'] );
		self::assertFalse( $usage['template'] );
		$attachment = (int) self::factory()->attachment->create( array( 'post_mime_type' => 'image/png' ) );
		self::assertSame( array(), CertificateAdmin::logo_warning_field( array(), get_post( $attachment ) ), 'unused image: no warning' );
		Settings::save( $admin, 'certificates', array( 'base_template' => array( 'logo_id' => $attachment ) ), array( 'base_template' ), Settings::rev( 'certificates' ) );
		self::assertTrue( CertificateAdmin::logo_usage( $attachment )['template'] );
		$fields = CertificateAdmin::logo_warning_field( array(), get_post( $attachment ) );
		self::assertStringContainsString( 'logo of the certificate template', $fields['ccap_logo_use']['html'] );

		$html = CertificatesScreen::html( array(), $this->admin ) . CertificatesScreen::html( array( 'tab' => 'template' ), $this->admin );
		foreach ( array( 'PDF', 'pdf_url', 'Designer', 'licen', 'Pro' ) as $word ) {
			self::assertStringNotContainsString( $word, $html, 'CRT-063 / CR-6' );
		}
		self::assertStringContainsString( 'Rita Revoked', $html );
		self::assertStringNotContainsString( 'Rita Revoked', CertificatesScreen::html( array( 'status' => 'valid' ), $this->admin ) );
	}

	/**
	 * CRT-018: base template change creates a new version; #19, nonce and
	 * logo rules; issued certificates keep their version. CRT-024 warning
	 * for the template logo.
	 *
	 * @group CRT-018
	 */
	public function test_CRT_018_template(): void {
		$post   = static fn( array $data ): array => $data + array(
			'_wpnonce' => wp_create_nonce( CertificatesScreen::TEMPLATE_ACTION ),
			'rev'      => Settings::rev( 'certificates' ),
		);
		$before = CertificateService::template();
		self::assertSame( 'ccap_invalid_nonce', CertificatesScreen::save_template( array( '_wpnonce' => 'x' ), $this->admin ) );
		self::assertSame( 'ok', CertificatesScreen::save_template( $post( array( 'issuer_name' => 'Academy', 'footer_text' => 'Valid without signature.' ) ), $this->admin ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$versions = CertificateAdmin::template_versions();
		self::assertSame( $before['version'] + 1, $versions[0]['version'], 'new template version' );
		self::assertSame( 'Academy', CertificateService::template()['definition']['issuer_name'] );

		$text = self::factory()->attachment->create( array( 'post_mime_type' => 'text/plain' ) );
		self::assertSame( 'ccap_logo_invalid', CertificatesScreen::save_template( $post( array( 'logo_id' => $text ) ), $this->admin ) );
		$editor = (int) self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		self::assertSame( 'ccap_forbidden', CertificatesScreen::save_template( $post( array( 'issuer_name' => 'X' ) ), $editor ) );
		wp_set_current_user( $this->admin );
		self::assertSame( 'Academy', CertificateService::template()['definition']['issuer_name'] );
	}

	/**
	 * CRT-020: learning area slug change keeps old certificate links (301
	 * target), unless a page uses the old slug (E-220).
	 *
	 * @group CRT-020
	 */
	public function test_CRT_020_slug_history(): void {
		$this->set_permalink_structure( '/%postname%/' );
		CertificateAdmin::register();
		Settings::save( Actor::user( $this->admin ), 'general', array( 'learning_area_slug' => 'akademie' ), array( 'learning_area_slug' ), Settings::rev( 'general' ) );
		self::assertContains( 'lernen', CertificateAdmin::history()['learning_area_slug'] ?? array() );
		self::assertSame( home_url( '/akademie/nachweis/abcDEF123' ), CertificateAdmin::redirect_target( '/lernen/nachweis/abcDEF123' ) );
		self::assertSame( home_url( '/akademie/nachweise' ), CertificateAdmin::redirect_target( '/lernen/nachweise' ) );
		self::assertNull( CertificateAdmin::redirect_target( '/lernen/other' ) );
		self::assertNull( CertificateAdmin::redirect_target( '/akademie/nachweis/abc' ), 'current slug is no redirect' );
		self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'lernen',
				'post_status' => 'publish',
			)
		);
		self::assertNull( CertificateAdmin::redirect_target( '/lernen/nachweis/abcDEF123' ), 'page took the old slug' );
	}
}
