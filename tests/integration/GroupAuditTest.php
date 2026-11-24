<?php
/**
 * SET-CC gruppen-audit: reinstating ended groups (owner decision D11) and the
 * pseudonymised audit hash chain (owner decision D12, RT-23).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\GroupService;
use CourseCaptain\Admin\GroupsScreen;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditChain;
use CourseCaptain\Event\AuditIdentity;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Event\AuditResealBackfill;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\AuditView;
use CourseCaptain\Ops\OpsCommand;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Roles;
use CourseCaptain\Privacy\PrivacyService;
use CourseCaptain\Privacy\RetentionService;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;
use CourseCaptain\Tests\Support\A11yDom;

/**
 * Real tables.
 *
 * @group SET-CC-gruppen-audit
 */
final class GroupAuditTest extends SchemaTestCase {

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Course manager (manages groups, no `manage_options`).
	 *
	 * @var int
	 */
	private int $manager;

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
		delete_option( AuditIdentity::RESEAL_STATE );
		$this->admin   = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->manager = (int) self::factory()->user->create( array( 'role' => Roles::COURSE_MANAGER ) );
		for ( $i = 0; $i < 4; $i++ ) {
			$this->learners[] = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		}
		$this->course = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'Course R',
				'post_author' => $this->admin,
			)
		);
		PolicyService::flush();
		wp_set_current_user( $this->admin );
	}

	/**
	 * Group with members.
	 *
	 * @param int         $members     Members.
	 * @param string|null $valid_until Group end.
	 * @return array{0: int, 1: list<int>} Group and seats.
	 */
	private function group( int $members, ?string $valid_until = null ): array {
		Settings::save( Actor::user( $this->admin ), 'enrollment', array( 'groups_enabled' => true ), array( 'groups_enabled' ), Settings::rev( 'enrollment' ) );
		$service = GroupService::current();
		$group   = $service->create( Actor::user( $this->admin ), $this->admin, 'Team Red', 10, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4(), array( 'valid_until' => $valid_until ) );
		$seats   = array();
		for ( $i = 0; $i < $members; $i++ ) {
			$invite = $service->invite( Actor::user( $this->admin ), $group, 'r' . $i . '@example.org' );
			self::assertIsArray( $invite );
			PolicyService::flush();
			$seats[] = $service->claim( Actor::user( $this->learners[ $i ] ), $invite['token'] );
		}
		PolicyService::flush();
		return array( $group, $seats );
	}

	/**
	 * Grants of a seat.
	 *
	 * @param int $seat Seat.
	 * @return list<array<string, mixed>>
	 */
	private static function grants( int $seat ): array {
		$db = Database::current();
		return $db->rows( $db->prepare( 'SELECT id, status, source_revision, revocation_reason, valid_until FROM `' . $db->table( 'ccap_grant' ) . '` WHERE seat_id = %d ORDER BY id', $seat ) );
	}

	/**
	 * Group operation through the admin screen.
	 *
	 * @param string               $op    Operation.
	 * @param int                  $group Group.
	 * @param array<string, mixed> $data  Fields.
	 * @param int                  $user  User.
	 * @return array{op: string, group: int, result: string, count: int, token: string}
	 */
	private function op( string $op, int $group, array $data, int $user ): array {
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
	 * D11: an ended group is reinstated by an administrator with a reason:
	 * new source revision, audit entry, grants of members whose seat is still
	 * active come back as a new revision; removed seats stay out; a grant
	 * whose own end is over stays ended. Managers without `manage_options`
	 * cannot, a reason is required, an active group cannot be reinstated.
	 *
	 * @group D11
	 * @group SEAT-013
	 */
	public function test_D11_reinstate_ended_group(): void {
		list( $group, $seats ) = $this->group( 3 );
		$service               = GroupService::current();
		$service->remove_seat( Actor::user( $this->admin ), $group, $seats[2] );
		// Seat 1: individually set end, already over when the group is reinstated.
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_grant' ) . '` SET valid_until = %s, valid_until_locked = 1 WHERE seat_id = %d', $db->now( -3600 ), $seats[1] ) );

		self::assertSame( 'ccap_conflict', $this->op( 'reinstate', $group, array( 'reason' => 'x' ), $this->admin )['result'], 'only ended groups' );
		self::assertSame(
			'ok',
			$this->op(
				'status',
				$group,
				array(
					'status'  => 'revoked',
					'confirm' => '1',
				),
				$this->admin
			)['result']
		);
		self::assertSame( 'revoked', self::grants( $seats[0] )[0]['status'] );

		$detail = GroupsScreen::html( array( 'group' => $group ), $this->admin );
		self::assertStringContainsString( 'name="op" value="reinstate"', $detail );
		self::assertSame( array(), A11yDom::fragment_problems( $detail ), 'reinstate form accessible' );
		self::assertStringNotContainsString( 'value="reinstate"', GroupsScreen::html( array( 'group' => $group ), $this->manager ), 'managers do not see the form' );

		self::assertSame( 'ccap_forbidden', $this->op( 'reinstate', $group, array( 'reason' => 'Refund reversed' ), $this->manager )['result'] );
		self::assertSame( 'ccap_reason_required', $this->op( 'reinstate', $group, array( 'reason' => '  ' ), $this->admin )['result'] );
		self::assertSame(
			'ccap_invalid_nonce',
			GroupsScreen::process(
				array(
					'op'       => 'reinstate',
					'group'    => $group,
					'_wpnonce' => 'x',
					'reason'   => 'y',
				),
				$this->admin
			)['result']
		);

		$out = $this->op( 'reinstate', $group, array( 'reason' => 'Refund reversed (ticket 4711)' ), $this->admin );
		self::assertSame( 'ok', $out['result'] );
		self::assertSame( 1, $out['count'], 'one grant renewed' );
		$row = (array) $db->row( $db->prepare( 'SELECT status, source_revision FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $group ) );
		self::assertSame( array( 'active', '2' ), array( $row['status'], (string) $row['source_revision'] ) );

		$member = self::grants( $seats[0] );
		self::assertSame( array( 'revoked', 'active' ), array_column( $member, 'status' ), 'old revision stays revoked, new one active' );
		self::assertSame( array( '1', '2' ), array_map( 'strval', array_column( $member, 'source_revision' ) ) );
		self::assertTrue( GroupService::current()->occupied( $group ) >= 2 );
		self::assertSame( array( 'revoked' ), array_column( self::grants( $seats[1] ), 'status' ), 'expired own end: not renewed' );
		self::assertSame( array( 'revoked' ), array_column( self::grants( $seats[2] ), 'status' ), 'removed seat stays out' );
		self::assertSame( 'group_member_removed', self::grants( $seats[2] )[0]['revocation_reason'] );

		$audit = $db->rows( "SELECT * FROM `{$db->table( 'ccap_audit_log' )}` WHERE action = 'group.reinstated'" );
		self::assertCount( 1, $audit );
		self::assertSame( 'Refund reversed (ticket 4711)', $audit[0]['reason'] );
		self::assertSame(
			array(
				'revision' => 2,
				'grants'   => 1,
				'skipped'  => 1,
			),
			json_decode( (string) $audit[0]['context'], true )
		);
		self::assertSame( $this->admin, AuditIdentity::user_of( $db, $audit[0]['actor_ref'] ) );
		self::assertArrayHasKey( 'group.reinstated', AuditView::registry() );
		self::assertSame( 1, (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_event' ) . "` WHERE event_type = 'group.reinstated' AND object_id = %s", (string) $db->scalar( $db->prepare( 'SELECT uid FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $group ) ) ) ) );

		// A new member joins with the new revision.
		$invite = $service->invite( Actor::user( $this->admin ), $group, 'late@example.org' );
		self::assertIsArray( $invite );
		$late = $service->claim( Actor::user( $this->learners[3] ), $invite['token'] );
		self::assertSame( '2', (string) self::grants( $late )[0]['source_revision'] );
		self::assertTrue( AuditChain::verify( $db )['ok'] );
	}

	/**
	 * D11: when the group's validity is over, the group is reinstated but no
	 * access comes back; the service refuses a second reinstatement.
	 *
	 * @group D11
	 */
	public function test_D11_reinstate_after_validity_end(): void {
		$db                    = Database::current();
		list( $group, $seats ) = $this->group( 2, $db->now( 86400 ) );
		$service               = GroupService::current();
		$service->set_status( Actor::user( $this->admin ), $group, 'revoked' );
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_group' ) . '` SET valid_until = %s WHERE id = %d', $db->now( -60 ), $group ) );
		$result = $service->reinstate( Actor::user( $this->admin ), $group, 'Storno reversed' );
		self::assertSame(
			array(
				'revision' => 2,
				'grants'   => 0,
				'skipped'  => 2,
			),
			$result
		);
		foreach ( $seats as $seat ) {
			self::assertSame( array( 'revoked' ), array_column( self::grants( $seat ), 'status' ) );
		}
		try {
			$service->reinstate( Actor::user( $this->admin ), $group, 'again' );
			self::fail( 'second reinstatement' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_conflict', $e->error_code );
		}
		try {
			$service->reinstate( Actor::user( $this->admin ), 999999, 'x' );
			self::fail( 'unknown group' );
		} catch ( CourseException $e ) {
			self::assertSame( 'ccap_not_found', $e->error_code );
		}
	}

	/**
	 * D12: new entries hash over pseudonyms only; the user ID columns stay
	 * empty; the mapping resolves them for the audit view. Erasure deletes
	 * the mapping, the entries stay and the chain still verifies (before and
	 * after); a later change of any hashed field is detected.
	 * SUP-035: eraser for one person, chain valid afterwards.
	 *
	 * @group D12
	 * @group RT-23
	 * @group SUP-035
	 */
	public function test_D12_pseudonymised_chain_survives_erasure(): void {
		$db      = Database::current();
		$learner = $this->learners[0];
		AuditLog::write( $db, Actor::user( $this->admin ), 'grant.extended', 'grant', '17', 'Support case', array( 'valid_until' => '2030-01-01 00:00:00' ), $learner );
		AuditLog::write( $db, Actor::user( $learner ), 'notification_optout_changed', 'user', (string) $learner, null, array( 'type' => 'reminder' ), $learner );
		AuditLog::write(
			$db,
			Actor::user( $this->admin ),
			'group.owner_changed',
			'group',
			'3',
			'Handover',
			array(
				'previous_owner' => $learner,
				'owner'          => $this->admin,
			)
		);
		AuditLog::write( $db, Actor::user( $this->admin ), 'cache_flushed', 'cache', 'user:' . $learner, null, array( 'scope' => array( 'user:' . $learner ) ), $learner );

		$table = $db->table( 'ccap_audit_log' );
		$ref   = AuditIdentity::ref_of( $db, $learner );
		self::assertIsString( $ref );
		self::assertMatchesRegularExpression( '/^A-[0-9a-f]{16}$/', $ref );
		self::assertSame( '0', (string) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE actor_user_id IS NOT NULL OR subject_user_id IS NOT NULL" ) );
		$dump = (string) wp_json_encode( $db->rows( "SELECT actor_ref, subject_ref, object_id, context FROM `{$table}`" ) );
		self::assertStringNotContainsString( '"' . $learner . '"', $dump );
		self::assertStringNotContainsString( 'user:' . $learner . '"', $dump );
		self::assertStringNotContainsString( ':' . $learner . ',', $dump );
		self::assertSame( 4, (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE actor_ref = %s OR subject_ref = %s OR object_id LIKE %s OR context LIKE %s", $ref, $ref, '%' . $ref . '%', '%' . $ref . '%' ) ) );
		self::assertSame( 3, AuditView::query( Actor::user( $this->admin ), array( 'user' => $learner ) )['total'], 'view resolves the pseudonym' );
		$before = AuditChain::verify( $db );
		self::assertTrue( $before['ok'], (string) wp_json_encode( $before ) );
		self::assertSame( 4, $before['epochs'][ AuditLog::EPOCH ]['rows'] );

		self::assertGreaterThan( 0, PrivacyService::erase_user( $learner ) );
		self::assertNull( AuditIdentity::ref_of( $db, $learner ), 'mapping deleted' );
		self::assertNull( AuditIdentity::user_of( $db, $ref ) );
		self::assertSame( 4, (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE actor_ref = %s OR subject_ref = %s OR object_id LIKE %s OR context LIKE %s", $ref, $ref, '%' . $ref . '%', '%' . $ref . '%' ) ), 'entries stay' );
		self::assertSame( 0, AuditView::query( Actor::user( $this->admin ), array( 'user' => $learner ) )['total'], 'no longer linkable' );
		$after = AuditChain::verify( $db );
		self::assertTrue( $after['ok'], (string) wp_json_encode( $after ) );
		self::assertSame( 5, $after['epochs'][ AuditLog::EPOCH ]['rows'], 'erasure audited' );
		$cli = OpsCommand::execute( 'audit verify', array(), array(), Actor::cli() );
		self::assertSame( 0, $cli['exit'] );
		self::assertStringStartsWith( 'Audit chain: ok', $cli['lines'][0] );

		$second = (int) $db->scalar( "SELECT id FROM `{$table}` ORDER BY id LIMIT 1 OFFSET 1" );
		$db->execute( $db->prepare( "UPDATE `{$table}` SET reason = 'edited' WHERE id = %d", $second ) );
		$broken = AuditChain::verify( $db );
		self::assertFalse( $broken['ok'] );
		self::assertSame( $second, $broken['epochs'][ AuditLog::EPOCH ]['broken_at'] );
		self::assertSame( 1, OpsCommand::execute( 'audit verify', array(), array(), Actor::cli() )['exit'] );
	}

	/**
	 * D12 migration: legacy rows (hashed over user IDs) are re-sealed by the
	 * backfill as chain epoch 1 after their legacy hashes were checked; a
	 * legacy row pseudonymised by an erasure before the re-seal is counted,
	 * not checked; the marker `audit_chain_resealed` documents the epoch.
	 * The chain verifies before and after a later erasure. A tampered legacy
	 * row is reported in the marker.
	 *
	 * @group D12
	 * @group RT-23
	 */
	public function test_D12_legacy_reseal_with_epoch_marker(): void {
		$db      = Database::current();
		$table   = $db->table( 'ccap_audit_log' );
		$learner = $this->learners[1];
		$other   = $this->learners[2];
		$ids     = $this->legacy_rows( array( array( $this->admin, $learner, 'grant.extended', 'grant', '5', '{"valid_until":"2031-01-01"}' ), array( $other, $other, 'notification_optout_changed', 'user', (string) $other, null ), array( $this->admin, null, 'group.owner_changed', 'group', '9', '{"previous_owner":' . $learner . ',"owner":' . $this->admin . '}' ), array( 0, null, 'retention_applied', 'retention', 'run', null ) ) );
		AuditLog::write( $db, Actor::user( $this->admin ), 'settings_saved', 'settings', 'general' );

		$pending = AuditChain::verify( $db );
		self::assertTrue( $pending['ok'], (string) wp_json_encode( $pending ) );
		self::assertSame( 4, $pending['legacy']['rows'] );

		// Erasure of `other` before the re-seal: its legacy row is pseudonymised and counted.
		PrivacyService::erase_user( $other );
		self::assertSame( '0', (string) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE actor_user_id = %d OR subject_user_id = %d", $other, $other ) ) );
		$erased = AuditChain::verify( $db );
		self::assertTrue( $erased['ok'], (string) wp_json_encode( $erased ) );
		self::assertSame( 1, $erased['legacy']['erased'] );

		$step = new AuditResealBackfill();
		self::assertSame( 'chain_epoch = 0', $step->pending_where() );
		self::assertSame( 2, $step->apply( $db, array_slice( $ids, 0, 2 ) ), 'first batch' );
		self::assertSame( 0, $step->apply( $db, array_slice( $ids, 0, 2 ) ), 'idempotent' );
		self::assertSame( 0, (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE action = 'audit_chain_resealed'" ), 'no marker before the end' );
		self::assertTrue( AuditChain::verify( $db )['ok'], 'half re-sealed' );
		self::assertSame( 2, $step->apply( $db, array_slice( $ids, 2 ) ) );

		$result = AuditChain::verify( $db );
		self::assertTrue( $result['ok'], (string) wp_json_encode( $result ) );
		self::assertSame( 4, $result['epochs'][ AuditLog::EPOCH_RESEALED ]['rows'] );
		self::assertSame( 0, $result['legacy']['rows'] );
		self::assertSame( 0, $result['personal_refs'] );
		self::assertSame( '0', (string) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE actor_user_id IS NOT NULL OR subject_user_id IS NOT NULL" ) );
		$marker = (array) json_decode( (string) $db->scalar( "SELECT context FROM `{$table}` WHERE action = 'audit_chain_resealed'" ), true );
		self::assertSame( 1, $marker['epoch'] );
		self::assertSame( 4, $marker['rows'] );
		self::assertSame( array( $ids[0], $ids[3] ), array( $marker['first_id'], $marker['last_id'] ) );
		self::assertTrue( $marker['legacy_verified'] );
		self::assertSame( 1, $marker['erased_before'] );
		self::assertSame( (string) $db->scalar( $db->prepare( "SELECT row_hash FROM `{$table}` WHERE id = %d", $ids[3] ) ), $marker['resealed_head'], 'marker binds the re-sealed head' );
		self::assertArrayHasKey( 'audit_chain_resealed', AuditView::registry() );
		$owner_change = (array) json_decode( (string) $db->scalar( $db->prepare( "SELECT context FROM `{$table}` WHERE id = %d", $ids[2] ) ), true );
		self::assertSame( AuditIdentity::ref_of( $db, $learner ), $owner_change['previous_owner'] );
		self::assertSame( $learner, AuditIdentity::user_of( $db, (string) $db->scalar( $db->prepare( "SELECT subject_ref FROM `{$table}` WHERE id = %d", $ids[0] ) ) ) );

		PrivacyService::erase_user( $learner );
		$final = AuditChain::verify( $db );
		self::assertTrue( $final['ok'], 'verifies after erasure' );
		self::assertNull( AuditIdentity::ref_of( $db, $learner ) );
	}

	/**
	 * D12 migration: a legacy row changed before the re-seal is reported
	 * (legacy check and marker), the re-sealed chain itself verifies from then on.
	 *
	 * @group D12
	 */
	public function test_D12_tampered_legacy_row_is_documented(): void {
		$db    = Database::current();
		$table = $db->table( 'ccap_audit_log' );
		$ids   = $this->legacy_rows( array( array( $this->admin, null, 'settings_saved', 'settings', 'general', null ), array( $this->admin, null, 'settings_saved', 'settings', 'display', null ) ) );
		$db->execute( $db->prepare( "UPDATE `{$table}` SET object_id = 'learning' WHERE id = %d", $ids[1] ) );
		$check = AuditChain::verify( $db );
		self::assertFalse( $check['ok'] );
		self::assertSame( $ids[1], $check['legacy']['first_mismatch'] );
		( new AuditResealBackfill() )->apply( $db, $ids );
		$marker = (array) json_decode( (string) $db->scalar( "SELECT context FROM `{$table}` WHERE action = 'audit_chain_resealed'" ), true );
		self::assertFalse( $marker['legacy_verified'] );
		self::assertSame( $ids[1], $marker['first_broken_id'] );
		self::assertTrue( AuditChain::verify( $db )['ok'] );
	}

	/**
	 * D12 with retention (RT-23): after old rows are removed the remaining
	 * chain starts at the anchor of the summary entry and still verifies.
	 * SUP-035: prune after the period with `audit_pruned` anchor.
	 *
	 * @group D12
	 * @group RT-23
	 * @group SUP-035
	 */
	public function test_D12_chain_verifies_after_retention(): void {
		global $wpdb;
		$now = time();
		$old = new Database( $wpdb, static fn(): int => (int) strtotime( '-6 years', $now ) );
		for ( $i = 0; $i < 3; $i++ ) {
			AuditLog::write( $old, Actor::user( $this->admin ), 'settings_saved', 'settings', 'old-' . $i, null, array(), $this->learners[0] );
		}
		$db = Database::current();
		AuditLog::write( $db, Actor::user( $this->admin ), 'settings_saved', 'settings', 'young' );
		self::assertTrue( AuditChain::verify( $db )['ok'] );
		self::assertSame( 3, RetentionService::prune_audit( $db, $now ) );
		$summary = (array) json_decode( (string) $db->scalar( 'SELECT context FROM `' . $db->table( 'ccap_audit_log' ) . "` WHERE action = 'audit_pruned'" ), true );
		self::assertArrayHasKey( AuditLog::EPOCH, $summary['anchors'] );
		$result = AuditChain::verify( $db );
		self::assertTrue( $result['ok'], (string) wp_json_encode( $result ) );
		self::assertSame( 2, $result['epochs'][ AuditLog::EPOCH ]['rows'] );
	}

	/**
	 * Legacy rows as written before D12: user IDs in the columns, hash over them.
	 *
	 * @param list<array{0: int, 1: int|null, 2: string, 3: string, 4: string, 5: string|null}> $specs Actor, subject, action, object type, object ID, context.
	 * @return list<int>
	 */
	private function legacy_rows( array $specs ): array {
		$db   = Database::current();
		$prev = '';
		$ids  = array();
		foreach ( $specs as $spec ) {
			$row             = array(
				'actor_user_id'   => $spec[0] > 0 ? $spec[0] : null,
				'actor_type'      => $spec[0] > 0 ? 'user' : 'system',
				'subject_user_id' => $spec[1],
				'action'          => $spec[2],
				'object_type'     => $spec[3],
				'object_id'       => $spec[4],
				'reason'          => null,
				'context'         => $spec[5],
				'created_at'      => $db->now(),
			);
			$row['row_hash'] = AuditLog::legacy_seal( $prev, $row );
			$prev            = $row['row_hash'];
			$ids[]           = $db->insert( 'ccap_audit_log', $row );
		}
		return $ids;
	}
}
