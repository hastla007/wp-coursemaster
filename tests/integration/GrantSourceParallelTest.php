<?php
/**
 * R1-42: parallel grant sources, invitations, seat reduction, expiry and
 * idempotency keys against the real DB (R0-09 §6, R0-21 §5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Access\FakeSource;
use CourseCaptain\Access\GrantReconciler;
use CourseCaptain\Access\GrantService;
use CourseCaptain\Access\GrantSourceAdapter;
use CourseCaptain\Access\GrantTransitions;
use CourseCaptain\Access\GroupService;
use CourseCaptain\Access\SourceStatusMapper;
use CourseCaptain\Course\CourseStructure;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Learner\LearnerQueries;
use CourseCaptain\Learning\AnswerService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Question\QuestionPresenter;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Question\QuestionTypes;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\NamedLock;
use CourseCaptain\Tests\Support\RaceRunner;

/**
 * Multi-process scenarios (`P`) and one fault injection (`I`, GRANT-017)
 * for the cells R0-41 §4.2 lists as gaps (GRANT-005/011/017,
 * CON-003/004/010/013/018).
 *
 * @group R1-42
 */
final class GrantSourceParallelTest extends SchemaTestCase {

	/**
	 * Option holding the shared state of the source object (CON-010).
	 */
	private const SOURCE_OPTION = 'ccap_test_con010_source';

	/**
	 * Admin.
	 *
	 * @var int
	 */
	private int $admin;

	/**
	 * Course.
	 *
	 * @var int
	 */
	private int $course;

	/**
	 * Module.
	 *
	 * @var int
	 */
	private int $module;

	/**
	 * Setup: one published course with one module (one question).
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! RaceRunner::available() ) {
			self::markTestSkipped( 'pcntl/posix required.' );
		}
		Plugin::activate( false );
		$this->admin  = (int) self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->course = $this->post( 'ccap_course' );
		$this->module = $this->post( 'ccap_module' );
		PolicyService::flush();
		$admin = Actor::user( $this->admin );
		CourseStructure::current()->assign_module( $admin, $this->course, $this->module );
		$repo     = QuestionRepository::current();
		$question = $repo->create(
			$admin,
			array(
				'question_type' => QuestionTypes::SINGLE,
				'question_text' => 'Q',
				'answers'       => array(
					array(
						'key'     => 'A',
						'text'    => 'a',
						'correct' => true,
					),
					array(
						'key'  => 'B',
						'text' => 'b',
					),
				),
			)
		);
		$repo->publish( $admin, $question );
		$repo->add_to_pool( $admin, $this->module, $question );
	}

	/**
	 * Remove the shared source state.
	 */
	public function tear_down(): void {
		delete_option( self::SOURCE_OPTION );
		parent::tear_down();
	}

	/**
	 * Published post.
	 *
	 * @param string $type Type.
	 * @return int
	 */
	private function post( string $type ): int {
		return (int) wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $type,
				'post_content' => 'content',
				'post_author'  => $this->admin,
			)
		);
	}

	/**
	 * New learner account.
	 *
	 * @return int
	 */
	private function user(): int {
		return (int) self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Count rows.
	 *
	 * @param string $table Table.
	 * @param string $where Trusted condition.
	 * @return int
	 */
	private static function rows( string $table, string $where ): int {
		$db = Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( $table ) . '` WHERE ' . $where );
	}

	/**
	 * One grant row.
	 *
	 * @param int $grant_id Grant.
	 * @return array<string, mixed>
	 */
	private static function grant( int $grant_id ): array {
		$db = Database::current();
		return (array) $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_grant' ) . '` WHERE id = %d', $grant_id ) );
	}

	/**
	 * Event types of a grant in order.
	 *
	 * @param int $grant_id Grant.
	 * @return list<string>
	 */
	private static function events( int $grant_id ): array {
		$db = Database::current();
		return array_values( array_map( 'strval', array_column( $db->rows( $db->prepare( 'SELECT event_type FROM `' . $db->table( 'ccap_grant_event' ) . '` WHERE grant_id = %d ORDER BY id', $grant_id ) ), 'event_type' ) ) );
	}

	/**
	 * Only the listed outcomes occurred.
	 *
	 * @param array<string, array<string, mixed>> $results Results.
	 * @param array<int, string>                  $allowed Allowed outcome keys.
	 * @return array<string, int>
	 */
	private static function assert_outcomes( array $results, array $allowed ): array {
		$outcomes = RaceRunner::outcomes( $results );
		self::assertSame( array(), array_diff( array_keys( $outcomes ), $allowed ), (string) wp_json_encode( $results ) );
		return $outcomes;
	}

	/**
	 * GRANT-005 (ACCESS-01/02, CORE-03 parallel): N processes with the same
	 * source key (source request and manual request key) → exactly one grant,
	 * one `created` event, one audit entry; the others get the same grant.
	 *
	 * @group GRANT-005
	 */
	public function test_GRANT_005_same_source_key_in_parallel(): void {
		$rounds = RaceRunner::repeat( 1 );
		for ( $round = 0; $round < $rounds; $round++ ) {
			$user      = $this->user();
			$course    = $this->course;
			$reference = 'order-g005-' . $round . '-' . wp_generate_password( 6, false );
			$request   = array(
				'user_id'          => $user,
				'course_id'        => $course,
				'source_type'      => 'fake',
				'source_reference' => $reference,
				'duration_days'    => 30,
				'renewal_mode'     => 'parallel',
			);
			$jobs      = array();
			$limit_i   = RaceRunner::n( 8 );
			for ( $i = 0; $i < $limit_i; $i++ ) {
				$jobs[ 'cb' . $i ] = static fn() => GrantService::current()->grant_from_source( $request, Actor::connector( 'fake' ) );
			}
			$results  = RaceRunner::run( 'GRANT-005-source', $jobs );
			$outcomes = self::assert_outcomes( $results, array( 'ok', 'ccap_db_busy' ) );
			self::assertGreaterThanOrEqual( 1, $outcomes['ok'] ?? 0 );
			$ok = array_filter( $results, static fn( array $r ): bool => ! empty( $r['ok'] ) );
			self::assertCount( 1, array_unique( array_map( static fn( array $r ): int => (int) $r['value']['grant_id'], $ok ) ), (string) wp_json_encode( $results ) );
			self::assertCount( 1, array_filter( $ok, static fn( array $r ): bool => 'created' === $r['value']['code'] ), 'exactly one creator' );
			$db    = Database::current();
			$where = $db->prepare( "source_type = 'fake' AND source_id = %s", $reference );
			self::assertSame( 1, self::rows( 'ccap_grant', $where ) );
			$grant = (int) reset( $ok )['value']['grant_id'];
			self::assertSame( array( 'created' ), self::events( $grant ) );
			self::assertSame( 1, self::rows( 'ccap_audit_log', $db->prepare( "action = 'grant.source_created' AND object_id = %s", (string) $grant ) ) );

			// Parallel manual grant with the same request key (form sent twice).
			$learner = $this->user();
			$admin   = $this->admin;
			$key     = wp_generate_uuid4();
			$jobs    = array();
			for ( $i = 0; $i < $limit_i; $i++ ) {
				$jobs[ 'm' . $i ] = static fn() => GrantService::current()->grant_manual( Actor::user( $admin ), $learner, $course, $key, 'GRANT-005' );
			}
			$results = RaceRunner::run( 'GRANT-005-manual', $jobs );
			self::assert_outcomes( $results, array( 'ok', 'ccap_db_busy' ) );
			$ids = array_unique( array_column( array_filter( $results, static fn( array $r ): bool => ! empty( $r['ok'] ) ), 'value' ) );
			self::assertCount( 1, $ids, (string) wp_json_encode( $results ) );
			self::assertSame( 1, self::rows( 'ccap_grant', $db->prepare( "source_type = 'manual' AND source_id = %s", $key ) ) );
			self::assertSame( array( 'created' ), self::events( (int) reset( $ids ) ) );
			self::assertSame( 1, self::rows( 'ccap_audit_log', $db->prepare( "action = 'grant.manual_created' AND object_id = %s", (string) reset( $ids ) ) ) );
		}
	}

	/**
	 * GRANT-011 (ACCESS-03 parallel): revocation of one grant while a second
	 * source grants the same course → union stays, the permanent source wins.
	 *
	 * @group GRANT-011
	 */
	public function test_GRANT_011_revocation_and_second_source_in_parallel(): void {
		$user    = $this->user();
		$course  = $this->course;
		$admin   = $this->admin;
		$service = GrantService::current();
		$kept    = $service->grant_manual( Actor::user( $admin ), $user, $course, wp_generate_uuid4(), 'kept', array( 'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 5 * DAY_IN_SECONDS ) ) );
		$revoked = $service->grant_manual(
			Actor::user( $admin ),
			$user,
			$course,
			wp_generate_uuid4(),
			'revoked',
			array(
				'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 9 * DAY_IN_SECONDS ),
				'scopes'      => 'exam',
			)
		);
		$request = array(
			'user_id'          => $user,
			'course_id'        => $course,
			'source_type'      => 'fake',
			'source_reference' => 'order-g011',
		);
		$results = RaceRunner::run(
			'GRANT-011',
			array(
				'revoke' => static fn() => GrantService::current()->transition( Actor::user( $admin ), $revoked, GrantTransitions::REVOKED, 'manual_revoke', true ),
				'source' => static fn() => GrantService::current()->grant_from_source( $request, Actor::connector( 'fake' ) )['code'],
			)
		);
		self::assertSame( array( 'ok' => 2 ), RaceRunner::outcomes( $results ), (string) wp_json_encode( $results ) );
		self::assertSame( GrantTransitions::REVOKED, $results['revoke']['value'] );
		self::assertSame( 'created', $results['source']['value'] );
		self::assertSame( GrantTransitions::REVOKED, self::grant( $revoked )['status'] );
		self::assertSame( GrantTransitions::ACTIVE, self::grant( $kept )['status'], 'the other grant is untouched' );
		PolicyService::flush();
		$relations = new Relations( Database::current() );
		self::assertSame( 'ok', $relations->effective_access( $user, $course, 0, 'view' ) );
		self::assertSame( 'ok', $relations->effective_access( $user, $course, 0, 'exam' ), 'the source grant covers all scopes' );
		$state = LearnerQueries::current()->access_state( Actor::user( $user ), $course );
		self::assertSame( 'active', $state['state'] );
		self::assertNull( $state['valid_until'], 'permanent wins over the latest end' );
	}

	/**
	 * GRANT-017 (ACCESS-04): CAS conflict between two transitions with the
	 * same `row_version`. Injected (`I`): the competing transition commits
	 * between read and CAS of the first; the loser is re-evaluated, every
	 * call leaves its event. Parallel (`P`): suspend and revoke race.
	 *
	 * @group GRANT-017
	 */
	public function test_GRANT_017_cas_conflict_reevaluates_without_lost_events(): void {
		$db    = Database::current();
		$table = $db->table( 'ccap_grant' );

		// I-1: winner suspends, loser (revoke) is re-evaluated and still applies.
		// I-2: winner revokes, loser (suspend) is re-evaluated and logged as ignored.
		$cases = array(
			array( GrantTransitions::SUSPENDED, GrantTransitions::REVOKED, GrantTransitions::REVOKED, array( 'created', 'status_changed', 'status_changed' ) ),
			array( GrantTransitions::REVOKED, GrantTransitions::SUSPENDED, 'ignored', array( 'created', 'status_changed', 'ignored' ) ),
		);
		foreach ( $cases as [ $winner, $loser, $expected, $events ] ) {
			$grant  = GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->user(), $this->course, wp_generate_uuid4(), 'GRANT-017' );
			$fired  = false;
			$needle = '`row_version` = `row_version` + 1 WHERE `id` = ' . $grant . ' AND';
			$filter = static function ( $sql ) use ( &$fired, $needle, $table, $grant, $winner ) {
				if ( $fired || ! is_string( $sql ) || ! str_starts_with( $sql, 'UPDATE `' . $table . '`' ) || ! str_contains( $sql, $needle ) ) {
					return $sql;
				}
				$fired = true;
				GrantService::current()->transition( Actor::connector( 'fake' ), $grant, $winner, 'competing_change' );
				return $sql;
			};
			add_filter( 'query', $filter );
			try {
				$outcome = GrantService::current()->transition( Actor::user( $this->admin ), $grant, $loser, 'manual_change', true );
			} finally {
				remove_filter( 'query', $filter );
			}
			self::assertTrue( $fired, 'conflict injected' );
			self::assertSame( $expected, $outcome );
			self::assertSame( GrantTransitions::REVOKED, self::grant( $grant )['status'] );
			self::assertSame( $events, self::events( $grant ), 'no lost event' );
		}

		// P: suspend and revoke of the same grant at the same time.
		$rounds = RaceRunner::repeat( 2 );
		for ( $round = 0; $round < $rounds; $round++ ) {
			$grant   = GrantService::current()->grant_manual( Actor::user( $this->admin ), $this->user(), $this->course, wp_generate_uuid4(), 'GRANT-017' );
			$admin   = $this->admin;
			$results = RaceRunner::run(
				'GRANT-017',
				array(
					'suspend' => static fn() => GrantService::current()->transition( Actor::connector( 'fake' ), $grant, GrantTransitions::SUSPENDED, 'payment_disputed' ),
					'revoke'  => static fn() => GrantService::current()->transition( Actor::user( $admin ), $grant, GrantTransitions::REVOKED, 'manual_revoke', true ),
				)
			);
			self::assertSame( array( 'ok' => 2 ), RaceRunner::outcomes( $results ), (string) wp_json_encode( $results ) );
			self::assertSame( GrantTransitions::REVOKED, $results['revoke']['value'], 'revocation always applies' );
			self::assertContains( $results['suspend']['value'], array( GrantTransitions::SUSPENDED, 'ignored' ) );
			self::assertSame( GrantTransitions::REVOKED, self::grant( $grant )['status'] );
			$expected = 'ignored' === $results['suspend']['value'] ? array( 'created', 'status_changed', 'ignored' ) : array( 'created', 'status_changed', 'status_changed' );
			self::assertSame( $expected, self::events( $grant ), 'one event per call' );
			self::assertSame( 1, self::rows( 'ccap_outbox_event', "event_type = 'grant.revoked' AND object_id = '" . esc_sql( (string) self::grant( $grant )['uid'] ) . "'" ) );
		}
	}

	/**
	 * CON-003 (ID-02, ID-03, CORE-02): one personal invitation redeemed by two
	 * accounts at the same time → one winner, the other `ccap_claim_used`.
	 *
	 * @group CON-003
	 */
	public function test_CON_003_personal_invitation_two_accounts(): void {
		$rounds = RaceRunner::repeat( 2 );
		for ( $round = 0; $round < $rounds; $round++ ) {
			$service = GroupService::current();
			$owner   = $this->user();
			$group   = $service->create( Actor::user( $this->admin ), $owner, 'Team', 2, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4(), array( 'claim_policy' => 'transferable' ) );
			$invite  = (array) $service->invite( Actor::user( $owner ), $group, 'invitee@example.org' );
			$token   = (string) $invite['token'];
			$first   = $this->user();
			$second  = $this->user();
			$results = RaceRunner::run(
				'CON-003',
				array(
					'a' => static fn() => GroupService::current()->claim( Actor::user( $first ), $token ),
					'b' => static fn() => GroupService::current()->claim( Actor::user( $second ), $token ),
				)
			);
			self::assertSame(
				array(
					'ccap_claim_used' => 1,
					'ok'              => 1,
				),
				RaceRunner::outcomes( $results ),
				(string) wp_json_encode( $results )
			);
			self::assertSame( 1, self::rows( 'ccap_seat', "status = 'active' AND group_id = " . (int) $group ) );
			self::assertSame( (int) $invite['seat_id'], (int) ( $results['a']['value'] ?? $results['b']['value'] ) );
			$winner = ! empty( $results['a']['ok'] ) ? $first : $second;
			$loser  = $winner === $first ? $second : $first;
			self::assertSame( 1, self::rows( 'ccap_grant', "source_type = 'group_seat' AND user_id = " . $winner ) );
			self::assertSame( 0, self::rows( 'ccap_grant', 'user_id = ' . $loser ) );
		}
	}

	/**
	 * CON-004 (ACCESS-07): seat reduction parallel to N joins → afterwards
	 * occupation ≤ new number of seats; no active seat grant without seat.
	 *
	 * @group CON-004
	 * @group SEAT-019
	 */
	public function test_CON_004_reduction_parallel_to_joins(): void {
		$service = GroupService::current();
		$owner   = $this->user();
		$n       = RaceRunner::n( 8 );
		$group   = $service->create( Actor::user( $this->admin ), $owner, 'Team', $n + 4, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4() );
		$link    = $service->rotate_link( Actor::user( $owner ), $group );
		$admin   = $this->admin;
		$target  = 3;
		$jobs    = array( 'reduce' => static fn() => count( GroupService::current()->reduce_seats( Actor::user( $admin ), $group, $target ) ) );
		for ( $i = 0; $i < $n; $i++ ) {
			$user             = $this->user();
			$jobs[ 'j' . $i ] = static fn() => GroupService::current()->claim( Actor::user( $user ), $link );
		}
		$results = RaceRunner::run( 'CON-004', $jobs );
		self::assertTrue( ! empty( $results['reduce']['ok'] ), (string) wp_json_encode( $results['reduce'] ) );
		unset( $results['reduce'] );
		self::assert_outcomes( $results, array( 'ok', 'ccap_group_full', 'ccap_group_busy' ) );
		$active = self::rows( 'ccap_seat', "status = 'active' AND group_id = " . (int) $group );
		self::assertLessThanOrEqual( $target, $active );
		self::assertLessThanOrEqual( $target, $service->occupied( $group ) );
		$db    = Database::current();
		$seats = $db->table( 'ccap_seat' );
		self::assertSame( $active, self::rows( 'ccap_grant', "status = 'active' AND source_type = 'group_seat' AND seat_id IN (SELECT id FROM `{$seats}` WHERE group_id = " . (int) $group . ')' ) );
		self::assertSame( 0, self::rows( 'ccap_grant', "status = 'active' AND source_type = 'group_seat' AND seat_id IN (SELECT id FROM `{$seats}` WHERE group_id = " . (int) $group . " AND status <> 'active')" ), 'removed seats keep no active grant' );
	}

	/**
	 * CON-010 (ACCESS-06, CORE-03/07, OPS-03): `paid` and `refunded` of the
	 * same source object in parallel, the paid callback reading slowly →
	 * at most one grant, never active, end state = highest source revision.
	 *
	 * @group CON-010
	 * @group R-04
	 */
	public function test_CON_010_paid_and_refund_in_parallel(): void {
		$user    = $this->user();
		$course  = $this->course;
		$adapter = new class() implements GrantSourceAdapter, SourceStatusMapper {

			/**
			 * Microseconds to wait after reading the snapshot.
			 *
			 * @var int
			 */
			public int $delay = 0;

			/**
			 * Source type.
			 *
			 * @return string
			 */
			public function source_type(): string {
				return 'fake';
			}

			/**
			 * Contract version.
			 *
			 * @return string
			 */
			public function contract_version(): string {
				return '1.0.0';
			}

			/**
			 * Current shared state (read from the DB, no cache).
			 *
			 * @param string $reference Reference.
			 * @return array<string, mixed>|null
			 */
			public function snapshot( string $reference ): ?array {
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'ccap_test_con010_source' ) );
				$state = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( $this->delay > 0 ) {
					usleep( $this->delay );
				}
				return is_array( $state ) && $reference === $state['reference'] ? $state : null;
			}

			/**
			 * Desired grants (FakeSource mapping).
			 *
			 * @param array<string, mixed> $snapshot Snapshot.
			 * @return array{grants: list<array<string, mixed>>, no_change: bool}
			 */
			public function desired_grants( array $snapshot ): array {
				return ( new FakeSource() )->desired_grants( $snapshot );
			}
		};
		$state   = static fn( string $reference, string $status, int $revision ): string => (string) wp_json_encode(
			array(
				'source_type' => 'fake',
				'reference'   => $reference,
				'status'      => $status,
				'revision'    => $revision,
				'user_id'     => $user,
				'lines'       => array(
					array(
						'line'    => '1',
						'targets' => array( array( 'course_id' => $course ) ),
					),
				),
			)
		);
		$db      = Database::current();
		$rounds  = max( 2, RaceRunner::repeat( 2 ) );
		for ( $round = 0; $round < $rounds; $round++ ) {
			$reference = 'order-c010-' . $round;
			update_option( self::SOURCE_OPTION, $state( $reference, 'paid', 1 ), false );
			// Even rounds: the paid callback reads first and is slow; odd rounds: the refund goes first.
			$slow    = 0 === $round % 2 ? 300000 : 0;
			$results = RaceRunner::run(
				'CON-010',
				array(
					'paid'   => static function () use ( $adapter, $reference, $slow ) {
						$adapter->delay = $slow;
						return GrantReconciler::current()->reconcile( $adapter, $adapter, $reference, 'evt-paid' );
					},
					'refund' => static function () use ( $adapter, $reference, $state, $slow ) {
						usleep( 0 === $slow ? 0 : 50000 );
						global $wpdb;
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", $state( $reference, 'refunded', 2 ), 'ccap_test_con010_source' ) );
						return GrantReconciler::current()->reconcile( $adapter, $adapter, $reference, 'evt-refund' );
					},
				)
			);
			self::assertSame( array( 'ok' => 2 ), RaceRunner::outcomes( $results ), (string) wp_json_encode( $results ) );
			$rows = $db->rows( $db->prepare( 'SELECT status, source_seq FROM `' . $db->table( 'ccap_grant' ) . "` WHERE source_type = 'fake' AND source_id = %s", $reference ) );
			self::assertLessThanOrEqual( 1, count( $rows ), 'at most one grant' );
			foreach ( $rows as $row ) {
				self::assertSame( GrantTransitions::REVOKED, $row['status'], 'no revival after the refund: ' . wp_json_encode( $results ) );
				self::assertSame( '2', (string) $row['source_seq'], 'end state = highest source revision' );
			}
			PolicyService::flush();
			self::assertNotSame( 'ok', ( new Relations( $db ) )->effective_access( $user, $course, 0, 'view' ) );

			// A late paid callback afterwards reads the refunded state and changes nothing.
			$late = GrantReconciler::current()->reconcile( $adapter, $adapter, $reference, 'evt-late-paid' );
			self::assertSame( 0, $late['created'] );
			self::assertSame( 0, $late['changed'] );
		}
	}

	/**
	 * CON-013 (ACCESS-05): expiry cron and manual extension in parallel →
	 * never "active with `valid_until` in the past".
	 *
	 * @group CON-013
	 */
	public function test_CON_013_expiry_cron_and_extension_in_parallel(): void {
		$db     = Database::current();
		$table  = $db->table( 'ccap_grant' );
		$admin  = $this->admin;
		$rounds = max( 2, RaceRunner::repeat( 2 ) );
		for ( $round = 0; $round < $rounds; $round++ ) {
			$grant = GrantService::current()->grant_manual( Actor::user( $admin ), $this->user(), $this->course, wp_generate_uuid4(), 'CON-013', array( 'valid_until' => gmdate( 'Y-m-d H:i:s', time() + 3600 ) ) );
			// Due: the end passed a minute ago, the cron has not run yet.
			$db->execute( $db->prepare( "UPDATE `{$table}` SET valid_until = %s WHERE id = %d", gmdate( 'Y-m-d H:i:s', time() - 60 ), $grant ) );
			$until   = gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS );
			$results = RaceRunner::run(
				'CON-013',
				array(
					'cron'  => static fn() => GrantService::current()->expire_due(),
					'admin' => static fn() => GrantService::current()->extend( Actor::user( $admin ), $grant, $until, 'CON-013' ),
				)
			);
			self::assertTrue( ! empty( $results['cron']['ok'] ), (string) wp_json_encode( $results ) );
			self::assertTrue( ! empty( $results['admin']['ok'] ) || 'ccap_conflict' === ( $results['admin']['code'] ?? '' ), (string) wp_json_encode( $results ) );
			$row = self::grant( $grant );
			self::assertFalse( GrantTransitions::ACTIVE === $row['status'] && (string) $row['valid_until'] < $db->now(), 'never active with an end in the past: ' . wp_json_encode( $row ) );
			if ( GrantTransitions::ACTIVE === $row['status'] ) {
				self::assertSame( $until, (string) $row['valid_until'], 'active only after the extension' );
			}
			self::assertSame( 0, self::rows( 'ccap_grant', "status = 'active' AND valid_until IS NOT NULL AND valid_until < '" . esc_sql( $db->now() ) . "'" ) );
		}
	}

	/**
	 * CON-018 (OPS-03, R0-19 §2.1): same `Idempotency-Key`, same body in
	 * parallel → one execution, the others the stored answer or `409
	 * ccap_request_in_progress`; same key with another body → `409
	 * ccap_idempotency_conflict`.
	 *
	 * @group CON-018
	 */
	public function test_CON_018_same_idempotency_key_in_parallel(): void {
		$learner = $this->user();
		GrantService::current()->grant_manual( Actor::user( $this->admin ), $learner, $this->course, wp_generate_uuid4(), 'CON-018' );
		$question = (int) QuestionRepository::current()->pool( $this->module )[0];
		$deliver  = fn(): string => (string) QuestionPresenter::current()->deliver( Actor::user( $learner ), $this->course, $this->module, $question )['token'];
		$token    = $deliver();
		$key      = wp_generate_uuid4();
		$jobs     = array();
		$limit_i  = RaceRunner::n( 6 );
		for ( $i = 0; $i < $limit_i; $i++ ) {
			$jobs[ 'r' . $i ] = static fn() => AnswerService::current()->answer( Actor::user( $learner ), $token, array( 'A' ), $key )['repeated'];
		}
		$results  = RaceRunner::run( 'CON-018', $jobs );
		$outcomes = self::assert_outcomes( $results, array( 'ok', 'ccap_request_in_progress' ) );
		self::assertGreaterThanOrEqual( 1, $outcomes['ok'] ?? 0 );
		$fresh = array_filter( $results, static fn( array $r ): bool => ! empty( $r['ok'] ) && false === $r['value'] );
		self::assertCount( 1, $fresh, 'one execution: ' . wp_json_encode( $results ) );
		$db = Database::current();
		self::assertSame( 1, self::rows( 'ccap_attempt', $db->prepare( 'user_id = %d AND idempotency_key = %s', $learner, $key ) ) );
		self::assertSame( 1, self::rows( 'ccap_request_idempotency', $db->prepare( 'user_id = %d AND idem_key = %s AND status_code = 200', $learner, $key ) ) );

		// Same key, other body, in parallel: one wins, the other is a conflict.
		$token2  = $deliver();
		$key2    = wp_generate_uuid4();
		$results = RaceRunner::run(
			'CON-018-conflict',
			array(
				'a' => static fn() => AnswerService::current()->answer( Actor::user( $learner ), $token2, array( 'A' ), $key2 )['repeated'],
				'b' => static fn() => AnswerService::current()->answer( Actor::user( $learner ), $token2, array( 'B' ), $key2 )['repeated'],
			)
		);
		self::assertSame(
			array(
				'ccap_idempotency_conflict' => 1,
				'ok'                        => 1,
			),
			RaceRunner::outcomes( $results ),
			(string) wp_json_encode( $results )
		);
		self::assertSame( 1, self::rows( 'ccap_attempt', $db->prepare( 'user_id = %d AND idempotency_key = %s', $learner, $key2 ) ) );
		self::assertSame( 1, self::rows( 'ccap_request_idempotency', $db->prepare( 'user_id = %d AND idem_key = %s AND status_code = 200', $learner, $key2 ) ) );
	}

	/**
	 * SEAT-004 (R-02): the same learner joins N times in parallel over the
	 * group link → one seat, one grant per course of the group.
	 *
	 * @group SEAT-004
	 */
	public function test_SEAT_004_same_user_joins_in_parallel(): void {
		$service = GroupService::current();
		$owner   = $this->user();
		$second  = $this->post( 'ccap_course' );
		$group   = $service->create(
			Actor::user( $this->admin ),
			$owner,
			'Team',
			3,
			array(
				array( 'course_id' => $this->course ),
				array( 'course_id' => $second ),
			),
			wp_generate_uuid4()
		);
		$link    = $service->rotate_link( Actor::user( $owner ), $group );
		$user    = $this->user();
		$jobs    = array();
		$limit_i = RaceRunner::n( 8 );
		for ( $i = 0; $i < $limit_i; $i++ ) {
			$jobs[ 't' . $i ] = static fn() => GroupService::current()->claim( Actor::user( $user ), $link );
		}
		$results  = RaceRunner::run( 'SEAT-004', $jobs );
		$outcomes = self::assert_outcomes( $results, array( 'ok', 'ccap_group_busy' ) );
		self::assertGreaterThanOrEqual( 1, $outcomes['ok'] ?? 0 );
		$ok = array_filter( $results, static fn( array $r ): bool => ! empty( $r['ok'] ) );
		self::assertCount( 1, array_unique( array_column( $ok, 'value' ) ), 'every answer = the one seat' );
		self::assertSame( 1, self::rows( 'ccap_seat', 'group_id = ' . (int) $group . ' AND user_id = ' . $user ) );
		foreach ( array( $this->course, $second ) as $course ) {
			self::assertSame( 1, self::rows( 'ccap_grant', "source_type = 'group_seat' AND user_id = " . $user . ' AND course_id = ' . (int) $course ) );
		}
	}

	/**
	 * SEAT-006: lock held by another process (wait 2 s) → `ccap_group_busy`,
	 * nothing written; `GET_LOCK` returns NULL → the claim runs without the
	 * lock and the recount after insert still keeps the limit.
	 *
	 * @group SEAT-006
	 */
	public function test_SEAT_006_lock_busy_and_lock_unavailable(): void {
		$service = GroupService::current();
		$owner   = $this->user();
		$group   = $service->create( Actor::user( $this->admin ), $owner, 'Team', 1, array( array( 'course_id' => $this->course ) ), wp_generate_uuid4(), array( 'claim_policy' => 'transferable' ) );
		$link    = $service->rotate_link( Actor::user( $owner ), $group );
		$user    = $this->user();

		// Busy: the parent connection holds the group lock while the child claims.
		$lock = NamedLock::for_key( Database::current(), 'group-seats:' . $group );
		self::assertSame( NamedLock::ACQUIRED, $lock->acquire( 0 ) );
		try {
			$results = RaceRunner::run( 'SEAT-006', array( 'claim' => static fn() => GroupService::current()->claim( Actor::user( $user ), $link ) ) );
		} finally {
			$lock->release();
		}
		self::assertSame( array( 'ccap_group_busy' => 1 ), RaceRunner::outcomes( $results ), (string) wp_json_encode( $results ) );
		self::assertSame( 0, self::rows( 'ccap_seat', 'group_id = ' . (int) $group ) );
		self::assertSame( 0, self::rows( 'ccap_grant', 'user_id = ' . $user ) );

		// Unavailable: an open invitation takes the only seat; the link claim passes the
		// policy (no active seat yet) and is stopped by the recount after insert.
		self::assertNotNull( $service->invite( Actor::user( $owner ), $group, 'invitee@example.org' ) );
		$recount = 0;
		$filter  = static function ( $sql ) use ( &$recount ) {
			if ( ! is_string( $sql ) ) {
				return $sql;
			}
			if ( str_starts_with( $sql, 'SELECT GET_LOCK(' ) ) {
				return 'SELECT NULL';
			}
			if ( str_contains( $sql, 'SELECT COUNT(*) FROM' ) && str_contains( $sql, 'AND id < ' ) ) {
				++$recount;
			}
			return $sql;
		};
		PolicyService::flush();
		add_filter( 'query', $filter );
		try {
			$code = '';
			try {
				GroupService::current()->claim( Actor::user( $user ), $link );
			} catch ( \CourseCaptain\Course\CourseException $e ) {
				$code = $e->error_code;
			}
		} finally {
			remove_filter( 'query', $filter );
		}
		self::assertSame( 'ccap_group_full', $code );
		self::assertSame( 1, $recount, 'the recount ran without the lock' );
		self::assertSame( 0, self::rows( 'ccap_seat', 'group_id = ' . (int) $group . ' AND user_id = ' . $user ), 'seat insert rolled back' );
		self::assertSame( 1, self::rows( 'ccap_seat', 'group_id = ' . (int) $group ) );
		self::assertSame( 0, self::rows( 'ccap_grant', 'user_id = ' . $user ) );
	}

	/**
	 * GRANT-013 (R0-09 §3.3): an `extend` grant with other scopes than the
	 * running chain starts at once (parallel); with the same scopes it starts
	 * where the chain ends.
	 *
	 * @group GRANT-013
	 */
	public function test_GRANT_013_extend_with_other_scopes_starts_now(): void {
		$service = GrantService::current();
		$admin   = Actor::user( $this->admin );
		$user    = $this->user();
		$first   = $service->grant_manual( $admin, $user, $this->course, wp_generate_uuid4(), 'chain', array( 'duration_days' => 30 ) );
		$other   = $service->grant_manual(
			$admin,
			$user,
			$this->course,
			wp_generate_uuid4(),
			'other scopes',
			array(
				'duration_days' => 10,
				'scopes'        => 'exam',
			)
		);
		$same    = $service->grant_manual( $admin, $user, $this->course, wp_generate_uuid4(), 'same scopes', array( 'duration_days' => 10 ) );
		$row     = self::grant( $first );
		self::assertSame( 'extend', self::grant( $other )['renewal_mode'] );
		self::assertLessThanOrEqual( 2, abs( (int) strtotime( self::grant( $other )['valid_from'] . ' UTC' ) - time() ), 'other scopes: starts now' );
		self::assertSame( 10 * DAY_IN_SECONDS, (int) strtotime( self::grant( $other )['valid_until'] . ' UTC' ) - (int) strtotime( self::grant( $other )['valid_from'] . ' UTC' ) );
		self::assertSame( $row['valid_until'], self::grant( $same )['valid_from'], 'same scopes: appended to the chain' );
	}
}
