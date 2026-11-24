<?php
/**
 * ACCESS-09 / SET-075: operator policy for existing grants when their
 * source disappears (`enrollment.source_disabled_policy`, R0-27; konzept
 * 35.2; R0-09 RF-15, GRANT-021).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\FakeSource;
use CourseCaptain\Access\GrantReconciler;
use CourseCaptain\Access\GrantTransitions;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\AuditView;
use CourseCaptain\Ops\OpsStatus;
use CourseCaptain\Ops\SourceReview;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Settings\Settings;
use CourseCaptain\Storage\Database;

/**
 * Real tables.
 *
 * @group ACCESS-09
 * @group SET-075
 * @group GRANT-021
 */
final class SourceDisabledPolicyTest extends SchemaTestCase {

	/**
	 * Learner.
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
	 * Learner and one published course.
	 */
	public function set_up(): void {
		parent::set_up();
		Plugin::activate( false );
		$admin         = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->learner = (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->course  = (int) wp_insert_post(
			array(
				'post_type'   => 'ccap_course',
				'post_status' => 'publish',
				'post_title'  => 'ACCESS-09',
				'post_author' => $admin,
			)
		);
		PolicyService::flush();
	}

	/**
	 * Default `keep`: a missing source changes nothing, no review list.
	 */
	public function test_ACCESS_09_policy_keep_changes_nothing(): void {
		self::assertSame( 'keep', SourceReview::policy() );
		$this->granted( 'order-a09-keep' );
		$before = $this->state();

		$off = new FakeSource();
		self::assertTrue( GrantReconciler::current()->reconcile( $off, $off, 'order-a09-keep' )['no_change'] );

		self::assertSame( $before, $this->state(), 'grant, events, outbox and meta unchanged' );
		self::assertSame( 0, self::audits( SourceReview::FLAGGED ) );
		$check = self::check( OpsStatus::report( Actor::cli() ), SourceReview::CHECK_ID );
		self::assertSame( 'ok', $check['status'] );
		self::assertSame( array(), $check['ids'] );
		self::assertSame( 'ok', $this->access() );
	}

	/**
	 * `keep_and_flag`: access stays, grants are marked once, listed in the
	 * system status and audited; the mark goes when the source is back.
	 */
	public function test_ACCESS_09_policy_keep_and_flag_marks_for_review(): void {
		$this->set_policy( 'keep_and_flag' );
		self::assertSame( 'keep_and_flag', SourceReview::policy() );
		$source = $this->granted( 'order-a09-flag' );
		$this->granted( 'order-a09-other', $source );
		$events = self::rows_in( 'ccap_grant_event' );
		$outbox = self::rows_in( 'ccap_outbox_event' );

		$off        = new FakeSource();
		$reconciler = GrantReconciler::current();
		self::assertTrue( $reconciler->reconcile( $off, $off, 'order-a09-flag' )['no_change'] );
		// A second run while the source is still away marks nothing twice.
		$reconciler->reconcile( $off, $off, 'order-a09-flag' );

		$grant = $this->grant( 'order-a09-flag' );
		self::assertSame( GrantTransitions::ACTIVE, $grant['status'], 'never revoked automatically' );
		$meta = json_decode( (string) $grant['meta'], true );
		self::assertSame( 'source_unavailable', $meta[ SourceReview::META_KEY ]['reason'] );
		self::assertNull( json_decode( (string) $this->grant( 'order-a09-other' )['meta'], true ), 'other references untouched' );
		self::assertSame( $events, self::rows_in( 'ccap_grant_event' ), 'no grant event' );
		self::assertSame( $outbox, self::rows_in( 'ccap_outbox_event' ), 'no access.changed' );
		self::assertSame( 1, self::audits( SourceReview::FLAGGED ) );
		self::assertArrayHasKey( SourceReview::FLAGGED, AuditView::registry() );
		self::assertArrayHasKey( SourceReview::CLEARED, AuditView::registry() );
		self::assertSame( 'ok', $this->access() );

		$check = self::check( OpsStatus::report( Actor::cli() ), SourceReview::CHECK_ID );
		self::assertSame( 'warn', $check['status'] );
		self::assertSame( array( 'grant:' . $grant['uid'] ), $check['ids'] );

		// Source back: mark removed, audited, check ok again, still no grant change.
		$source->put( $this->snapshot( 'order-a09-flag', 2 ) );
		$again = $reconciler->reconcile( $source, $source, 'order-a09-flag' );
		self::assertSame( array( 0, 0 ), array( $again['created'], $again['changed'] ) );
		self::assertNull( json_decode( (string) $this->grant( 'order-a09-flag' )['meta'], true ) );
		self::assertSame( 1, self::audits( SourceReview::CLEARED ) );
		self::assertSame( 'ok', self::check( OpsStatus::report( Actor::cli() ), SourceReview::CHECK_ID )['status'] );
		self::assertSame( $events, self::rows_in( 'ccap_grant_event' ) );
	}

	/**
	 * Final grants (revoked/expired) are not put on the review list.
	 */
	public function test_ACCESS_09_final_grants_are_not_flagged(): void {
		$this->set_policy( 'keep_and_flag' );
		$source = $this->granted( 'order-a09-final' );
		$source->put( $this->snapshot( 'order-a09-final', 2, 'refunded' ) );
		GrantReconciler::current()->reconcile( $source, $source, 'order-a09-final' );
		self::assertSame( GrantTransitions::REVOKED, $this->grant( 'order-a09-final' )['status'] );

		$off = new FakeSource();
		GrantReconciler::current()->reconcile( $off, $off, 'order-a09-final' );
		self::assertNull( json_decode( (string) $this->grant( 'order-a09-final' )['meta'], true ) );
		self::assertSame( 0, self::audits( SourceReview::FLAGGED ) );
		self::assertSame( 0, SourceReview::open_items( Database::current() )['count'] );
	}

	/**
	 * Save the policy.
	 *
	 * @param string $policy keep|keep_and_flag.
	 * @return void
	 */
	private function set_policy( string $policy ): void {
		$saved = Settings::save( Actor::cli(), 'enrollment', array( 'source_disabled_policy' => $policy ), array( 'source_disabled_policy' ), Settings::rev( 'enrollment' ) );
		self::assertSame( array( 'source_disabled_policy' ), $saved['changed'] );
	}

	/**
	 * Paid source object reconciled into an active grant.
	 *
	 * @param string          $reference Reference.
	 * @param FakeSource|null $source    Source to reuse.
	 * @return FakeSource
	 */
	private function granted( string $reference, ?FakeSource $source = null ): FakeSource {
		$source = $source ?? new FakeSource();
		$source->put( $this->snapshot( $reference, 1 ) );
		self::assertSame( 1, GrantReconciler::current()->reconcile( $source, $source, $reference )['created'] );
		return $source;
	}

	/**
	 * Snapshot of a source object.
	 *
	 * @param string $reference Reference.
	 * @param int    $revision  Revision.
	 * @param string $status    Source status.
	 * @return array<string, mixed>
	 */
	private function snapshot( string $reference, int $revision, string $status = 'paid' ): array {
		return array(
			'reference' => $reference,
			'status'    => $status,
			'revision'  => $revision,
			'user_id'   => $this->learner,
			'lines'     => array(
				array(
					'line'    => '1',
					'targets' => array( array( 'course_id' => $this->course ) ),
				),
			),
		);
	}

	/**
	 * Grant row of a reference.
	 *
	 * @param string $reference Reference.
	 * @return array<string, mixed>
	 */
	private function grant( string $reference ): array {
		$db  = Database::current();
		$row = $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_grant' ) . "` WHERE source_type = 'fake' AND source_id = %s", $reference ) );
		self::assertIsArray( $row );
		return $row;
	}

	/**
	 * Snapshot of grant rows, grant events and outbox.
	 *
	 * @return array<string, mixed>
	 */
	private function state(): array {
		$db = Database::current();
		return array(
			'grants' => $db->rows( 'SELECT id, status, valid_until, meta, row_version FROM `' . $db->table( 'ccap_grant' ) . '` ORDER BY id' ),
			'events' => self::rows_in( 'ccap_grant_event' ),
			'outbox' => self::rows_in( 'ccap_outbox_event' ),
		);
	}

	/**
	 * Rows of a table.
	 *
	 * @param string $table Table without prefix.
	 * @return int
	 */
	private static function rows_in( string $table ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '`' );
	}

	/**
	 * Audit entries of an action.
	 *
	 * @param string $action Action.
	 * @return int
	 */
	private static function audits( string $action ): int {
		$db = Database::current();
		return (int) $db->scalar( $db->prepare( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_audit_log' ) . '` WHERE action = %s', $action ) );
	}

	/**
	 * Effective access of the learner.
	 *
	 * @return string
	 */
	private function access(): string {
		PolicyService::flush();
		return ( new Relations( Database::current() ) )->effective_access( $this->learner, $this->course, 0, 'view' );
	}

	/**
	 * Check of a report.
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
}
