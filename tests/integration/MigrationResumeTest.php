<?php
/**
 * R1-40: migration with lock, crash, retry, backfill and restore (R0-37 §7).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\Plugin;
use CourseCaptain\Ops\HealthChecks;
use CourseCaptain\Ops\MigrateCommand;
use CourseCaptain\Storage\BackfillGate;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\MigrationBudget;
use CourseCaptain\Storage\MigrationFault;
use CourseCaptain\Storage\MigrationTrigger;
use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\NamedLock;
use CourseCaptain\Storage\Schema;
use CourseCaptain\Storage\StepLog;
use CourseCaptain\Storage\StorageException;
use RuntimeException;

/**
 * Real MariaDB/MySQL, real tables. Parallel runners and hard aborts
 * (`kill -9`) use forked processes with their own DB connection
 * (start barrier after Pass `TESTING.md`, R0-37 QB-14); fault points use
 * `course_captain_migration_fault` (COURSE_CAPTAIN_TESTING).
 *
 * @group R1-40
 */
final class MigrationResumeTest extends SchemaTestCase {

	private const STEP_SEAT = 'core:0002_seat_active_user';
	private const STEP_EXAM = 'core:0001_exam_session_running';
	private const OLD       = '0.9.0+000000000000';

	/**
	 * Fork support is required for the multi-process cases.
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'pcntl_fork' ) || ! function_exists( 'posix_kill' ) ) {
			self::markTestSkipped( 'pcntl/posix required.' );
		}
	}

	/**
	 * Drop the probe table after the schema cleanup.
	 */
	public function tear_down(): void {
		remove_all_actions( MigrationFault::HOOK );
		parent::tear_down();
		ProbeBackfill::drop();
		$GLOBALS['wpdb']->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	// ---------------------------------------------------------------------
	// Parallel triggers.
	// ---------------------------------------------------------------------

	/**
	 * MIG-001 / MIG-002: admin, cron, activation, two CLI runs and frontend
	 * requests start together; exactly one runner migrates, all others skip,
	 * no DDL runs twice, `ccap_migrate` is scheduled at most once.
	 *
	 * @group MIG-001
	 * @group MIG-002
	 */
	public function test_MIG_001_parallel_triggers_migrate_exactly_once(): void {
		self::upgrade_due();
		self::clear_own_cron();
		$before = self::steps();

		$runner   = static function ( string $context ): \Closure {
			return static function () use ( $context ): array {
				$ddl = self::count_ddl();
				self::slow_winner();
				$result = Migrator::for_current_site()->run( $context );
				return array(
					'status' => $result['status'],
					'ddl'    => $ddl(),
				);
			};
		};
		$cli      = static function (): array {
			$ddl = self::count_ddl();
			self::slow_winner();
			$out = MigrateCommand::execute( Migrator::for_current_site(), array() );
			return array(
				'status' => $out['status'],
				'exit'   => $out['exit'],
				'ddl'    => $ddl(),
			);
		};
		$frontend = static function (): array {
			$ddl = self::count_ddl();
			Migrator::for_current_site()->on_request();
			return array(
				'status' => 'frontend',
				'ddl'    => $ddl(),
			);
		};

		$results = self::in_parallel(
			array(
				'admin'      => $runner( MigrationTrigger::CONTEXT_ADMIN ),
				'cron'       => $runner( MigrationTrigger::CONTEXT_CRON ),
				'activation' => $runner( MigrationTrigger::CONTEXT_ACTIVATION ),
				'cli-1'      => $cli,
				'cli-2'      => $cli,
				'front-1'    => $frontend,
				'front-2'    => $frontend,
				'front-3'    => $frontend,
			)
		);

		$statuses = array_map( static fn( $r ) => is_array( $r ) ? $r['status'] : 'crashed', $results );
		self::assertSame( 1, count( array_keys( $statuses, 'done', true ) ), 'exactly one runner: ' . wp_json_encode( $statuses ) );
		self::assertSame( 4, count( array_keys( $statuses, 'skipped_locked', true ) ), wp_json_encode( $statuses ) );
		self::assertSame( 0, $results['cli-1']['exit'] );
		self::assertSame( 0, $results['cli-2']['exit'], 'MIG-002: skipped CLI run exits 0' );

		$ddl    = array_map( static fn( array $r ): int => $r['ddl'], $results );
		$winner = (string) array_search( 'done', $statuses, true );
		self::assertGreaterThan( 0, $ddl[ $winner ] );
		self::assertSame( $ddl[ $winner ], array_sum( $ddl ), 'no DDL outside the winner' );

		$after = self::steps();
		foreach ( $before as $id => $row ) {
			self::assertSame( (int) $row['attempts'] + 1, (int) $after[ $id ]['attempts'], 'each step ran once: ' . $id );
			self::assertSame( StepLog::DONE, $after[ $id ]['status'] );
			self::assertSame( '0', (string) $after[ $id ]['crash_count'] );
		}
		self::assertSame( Schema::db_version(), self::raw_option( Migrator::DB_VERSION ) );
		self::assertContains( 'active_user', self::inspector()->columns( 'ccap_seat' ) );
		self::assertLessThanOrEqual( 1, count( array_keys( self::own_cron_events(), CronHooks::MIGRATE, true ) ) );
		self::assertNull( self::lease_row(), 'lease released' );
	}

	/**
	 * MIG-003: without GET_LOCK (NULL), the bootstrap option lock and the
	 * lease alone exclude parallel runners on a fresh install; the counter
	 * feeds the health warning.
	 *
	 * @group MIG-003
	 */
	public function test_MIG_003_lease_alone_excludes_without_named_lock(): void {
		$job = static function ( string $context ): \Closure {
			return static function () use ( $context ): array {
				$ddl = self::count_ddl();
				self::slow_winner();
				$result = Migrator::for_current_site()->without_named_lock()->run( $context );
				return array(
					'status' => $result['status'],
					'ddl'    => $ddl(),
				);
			};
		};

		$results = self::in_parallel(
			array(
				'admin'      => $job( MigrationTrigger::CONTEXT_ADMIN ),
				'cron'       => $job( MigrationTrigger::CONTEXT_CRON ),
				'cli'        => $job( MigrationTrigger::CONTEXT_CLI ),
				'activation' => $job( MigrationTrigger::CONTEXT_ACTIVATION ),
			)
		);

		$statuses = array_map( static fn( $r ) => is_array( $r ) ? $r['status'] : 'crashed', $results );
		self::assertSame( 1, count( array_keys( $statuses, 'done', true ) ), wp_json_encode( $statuses ) );
		self::assertSame( 3, count( array_keys( $statuses, 'skipped_locked', true ) ), wp_json_encode( $statuses ) );
		$ddl    = array_map( static fn( array $r ): int => $r['ddl'], $results );
		$winner = (string) array_search( 'done', $statuses, true );
		self::assertSame( $ddl[ $winner ], array_sum( $ddl ), 'no DDL outside the winner' );
		self::assertCount( count( Schema::table_names() ), array_intersect( Schema::table_names(), array_keys( self::inspector()->tables() ) ) );
		self::assertSame( array(), self::inspector()->drift( Schema::table_names() ) );
		self::assertFalse( self::raw_option( Migrator::BOOTSTRAP_LOCK ), 'bootstrap lock removed' );
		self::assertGreaterThan( 0, (int) self::raw_option( Migrator::LOCK_NULL_COUNT ) );

		$health = HealthChecks::test_migration( Migrator::for_current_site() );
		self::assertSame( 'recommended', $health['status'] );
		self::assertStringContainsString( 'named locks', $health['label'] );
	}

	/**
	 * MIG-004: the named lock is lost during the run (reconnect) and another
	 * connection holds it; the runner stops before the next DDL and writes
	 * no status for the interrupted step.
	 *
	 * @group MIG-004
	 */
	public function test_MIG_004_lost_named_lock_stops_before_next_ddl(): void {
		self::upgrade_due();
		global $wpdb;
		$name  = NamedLock::schema_migration( Database::current() )->name;
		$other = self::connection();
		self::on_fault(
			Migrator::STEP_TABLES,
			'before_status',
			static function () use ( $wpdb, $other, $name ): void {
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$other->query( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) );
			}
		);
		$ddl    = self::count_ddl();
		$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN );

		self::assertSame( 'fenced', $result['status'] );
		self::assertSame( 0, $ddl(), 'no DDL after the loss' );
		self::assertNotContains( 'active_user', self::inspector()->columns( 'ccap_seat' ) );
		self::assertSame( StepLog::RUNNING, self::steps()[ self::STEP_EXAM ]['status'], 'resume point, not failed' );
		self::assertSame( self::OLD, self::raw_option( Migrator::DB_VERSION ) );

		$other->query( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		$other->close();
		remove_all_actions( MigrationFault::HOOK );

		self::assertSame( 'done', Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN )['status'] );
		self::assertSame( '1', (string) self::steps()[ self::STEP_EXAM ]['crash_count'] );
		self::assertContains( 'active_user', self::inspector()->columns( 'ccap_seat' ) );
	}

	/**
	 * MIG-005: zombie runner in NULL mode – its lease expires, a second
	 * runner takes over (fence + 1); the first one wakes up, is fenced and
	 * writes nothing.
	 *
	 * @group MIG-005
	 */
	public function test_MIG_005_zombie_runner_is_fenced_after_takeover(): void {
		self::upgrade_due();

		$results = self::in_parallel(
			array(
				'zombie'   => static function ( string $dir ): array {
					$ddl = null;
					add_action(
						MigrationFault::HOOK,
						static function ( string $step, string $point ) use ( $dir, &$ddl ): void {
							if ( Migrator::STEP_TABLES === $step && 'before_status' === $point ) {
								touch( $dir . '/paused' );
								self::await_file( $dir . '/taken-over', 30 );
								$ddl = self::count_ddl();
							}
						},
						10,
						2
					);
					$result = Migrator::for_current_site()->without_named_lock()->run( MigrationTrigger::CONTEXT_CRON );
					return array(
						'status'     => $result['status'],
						'ddl_resume' => null === $ddl ? -1 : $ddl(),
					);
				},
				'takeover' => static function ( string $dir ): array {
					self::await_file( $dir . '/paused', 30 );
					// One hour later: the zombie's lease (30 min) has expired.
					$late   = new Database( $GLOBALS['wpdb'], static fn(): int => time() + HOUR_IN_SECONDS );
					$result = ( new Migrator( $late, COURSE_CAPTAIN_VERSION ) )->without_named_lock()->run( MigrationTrigger::CONTEXT_CRON );
					touch( $dir . '/taken-over' );
					return array( 'status' => $result['status'] );
				},
			)
		);

		self::assertSame( 'done', $results['takeover']['status'] );
		self::assertSame( 'fenced', $results['zombie']['status'] );
		self::assertSame( 0, $results['zombie']['ddl_resume'], 'zombie writes nothing after waking up' );
		$steps = self::steps();
		self::assertSame( StepLog::DONE, $steps[ Migrator::STEP_TABLES ]['status'] );
		self::assertSame( '1', (string) $steps[ Migrator::STEP_TABLES ]['crash_count'], 'takeover saw the interrupted step' );
		self::assertSame( 2, (int) $steps[ self::STEP_SEAT ]['attempts'], 'seat step ran once after the takeover' );
		self::assertSame( Schema::db_version(), self::raw_option( Migrator::DB_VERSION ) );
	}

	// ---------------------------------------------------------------------
	// Hard aborts (kill -9) and fatal errors.
	// ---------------------------------------------------------------------

	/**
	 * MIG-006: `kill -9` in the middle of `dbDelta` (P2) on a fresh install;
	 * the next run takes over the lease, repeats `dbDelta` and succeeds.
	 *
	 * @group MIG-006
	 */
	public function test_MIG_006_kill_during_dbdelta_resumes(): void {
		$results = self::in_parallel(
			array(
				'victim' => static function (): array {
					$creates = 0;
					add_filter(
						'query',
						static function ( $query ) use ( &$creates ) {
							if ( 1 === preg_match( '/^\s*CREATE\s+TABLE/i', (string) $query ) && ++$creates >= 10 ) {
								posix_kill( getmypid(), SIGKILL );
							}
							return $query;
						}
					);
					return Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN );
				},
			)
		);
		self::assertNull( $results['victim'], 'process was killed' );
		$tables = array_intersect( Schema::table_names(), array_keys( self::inspector()->tables() ) );
		self::assertLessThan( count( Schema::table_names() ), count( $tables ), 'schema incomplete' );
		self::assertSame( StepLog::RUNNING, self::steps()[ Migrator::STEP_TABLES ]['status'] );

		self::await_lock_free();
		$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN );

		self::assertSame( 'done', $result['status'], $result['error'] );
		self::assertSame( array(), self::inspector()->drift( Schema::table_names() ) );
		self::assertCount( count( Schema::foreign_keys() ), self::inspector()->foreign_keys() );
		$step = self::steps()[ Migrator::STEP_TABLES ];
		self::assertSame( '1', (string) $step['crash_count'] );
		self::assertSame( '2', (string) $step['attempts'] );
	}

	/**
	 * MIG-007: `kill -9` after the DDL, before the status write (P3); the
	 * next run finds the target state (I-M1) and marks the step done
	 * without a second DDL.
	 *
	 * @group MIG-007
	 */
	public function test_MIG_007_kill_after_ddl_marks_done_without_second_ddl(): void {
		self::upgrade_due();
		$results = self::in_parallel(
			array(
				'victim' => static function (): array {
					self::kill_at( self::STEP_SEAT, 'before_status' );
					return Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CRON );
				},
			)
		);
		self::assertNull( $results['victim'] );
		self::assertContains( 'active_user', self::inspector()->columns( 'ccap_seat' ), 'DDL was applied before the kill' );
		self::assertSame( StepLog::RUNNING, self::steps()[ self::STEP_SEAT ]['status'] );

		self::await_lock_free();
		$alters = array();
		add_filter(
			'query',
			static function ( $query ) use ( &$alters ) {
				if ( 1 === preg_match( '/^\s*ALTER\s+TABLE\s+`?\w*ccap_seat`?\s/i', (string) $query ) ) {
					$alters[] = (string) $query;
				}
				return $query;
			}
		);
		$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CRON );

		self::assertSame( 'done', $result['status'], $result['error'] );
		self::assertSame( array(), $alters, 'no second DDL on ccap_seat' );
		$step = self::steps()[ self::STEP_SEAT ];
		self::assertSame( StepLog::DONE, $step['status'] );
		self::assertSame( '1', (string) $step['crash_count'] );
		self::assertSame( Schema::db_version(), self::raw_option( Migrator::DB_VERSION ) );
	}

	/**
	 * MIG-010: PHP fatal in step n. The shutdown handler marks n failed;
	 * steps before n are done, later steps did not run; the version stays;
	 * writes get 503, reads pass; automatic runs wait for the backoff; the
	 * CLI finishes the migration once the cause is gone.
	 *
	 * @group MIG-010
	 * @group GM-166
	 */
	public function test_MIG_010_fatal_in_step_marks_failed_and_keeps_w1(): void {
		self::upgrade_due();
		self::clear_own_cron();
		$results = self::in_parallel(
			array(
				'victim' => static function (): array {
					$migrator = Migrator::for_current_site();
					add_action(
						MigrationFault::HOOK,
						static function ( string $step, string $point ) use ( $migrator ): void {
							if ( self::STEP_SEAT === $step && 'before_status' === $point ) {
								// What PHP passes to the registered shutdown function after an out-of-memory fatal.
								$migrator->handle_fatal(
									array(
										'type'    => E_ERROR,
										'message' => 'Allowed memory size of 134217728 bytes exhausted (tried to allocate 20480 bytes) in /srv/www/wp-content/plugins/x/y.php on line 12',
									)
								);
								posix_kill( getmypid(), SIGKILL );
							}
						},
						10,
						2
					);
					return $migrator->run( MigrationTrigger::CONTEXT_ADMIN );
				},
			)
		);
		self::assertNull( $results['victim'] );

		$steps = self::steps();
		self::assertSame( StepLog::FAILED, $steps[ self::STEP_SEAT ]['status'] );
		self::assertStringStartsWith( 'PHP fatal error: Allowed memory size', (string) $steps[ self::STEP_SEAT ]['last_error'] );
		self::assertStringNotContainsString( '/srv/www', (string) $steps[ self::STEP_SEAT ]['last_error'], 'no paths stored' );
		self::assertSame( StepLog::DONE, $steps[ Migrator::STEP_TABLES ]['status'] );
		self::assertSame( StepLog::DONE, $steps[ self::STEP_EXAM ]['status'] );
		self::assertSame( '2', (string) $steps[ self::STEP_EXAM ]['attempts'] );
		self::assertSame( '1', (string) $steps[ Migrator::STEP_FOREIGN ]['attempts'], 'later step did not run' );
		self::assertSame( self::OLD, self::raw_option( Migrator::DB_VERSION ) );
		self::assertNull( self::lease_row(), 'handler released the lease' );

		$migrator = Migrator::for_current_site();
		self::assertW1( $migrator );
		self::assertSame( MigrationTrigger::WAIT, $migrator->dispatch( MigrationTrigger::CONTEXT_ADMIN ), 'backoff' );
		self::assertSame( MigrationTrigger::WAIT, $migrator->dispatch( MigrationTrigger::CONTEXT_CRON ) );
		self::assertSame( StepLog::FAILED, self::steps()[ self::STEP_SEAT ]['status'] );
		self::assertSame( 'critical', HealthChecks::test_migration( $migrator )['status'] );

		$out = MigrateCommand::execute( Migrator::for_current_site(), array() );
		self::assertSame( 0, $out['exit'], implode( "\n", $out['lines'] ) );
		self::assertSame( StepLog::DONE, self::steps()[ self::STEP_SEAT ]['status'] );
		self::assertSame( Schema::db_version(), self::raw_option( Migrator::DB_VERSION ) );
		self::assertSame( 'good', HealthChecks::test_migration( Migrator::for_current_site() )['status'] );
	}

	/**
	 * MIG-011: three aborts in the same step poison it; cron/admin/frontend
	 * no longer try, the CLI does and clears the mark.
	 *
	 * @group MIG-011
	 */
	public function test_MIG_011_repeated_crash_poisons_step(): void {
		self::upgrade_due();
		for ( $i = 1; $i <= 3; $i++ ) {
			$results = self::in_parallel(
				array(
					'victim' => static function (): array {
						self::kill_at( self::STEP_SEAT, 'before_status' );
						return Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CRON );
					},
				)
			);
			self::assertNull( $results['victim'], 'crash ' . $i );
			self::await_lock_free();
		}
		self::assertSame( '2', (string) self::steps()[ self::STEP_SEAT ]['crash_count'], 'third abort not yet counted' );

		$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CRON );
		self::assertSame( 'poison', $result['status'] );
		$step = self::steps()[ self::STEP_SEAT ];
		self::assertSame( StepLog::FAILED, $step['status'] );
		self::assertSame( StepLog::POISON, $step['blocked_reason'] );
		self::assertSame( '3', (string) $step['crash_count'] );

		$migrator = Migrator::for_current_site();
		foreach ( array( MigrationTrigger::CONTEXT_CRON, MigrationTrigger::CONTEXT_ADMIN, MigrationTrigger::CONTEXT_FRONTEND ) as $context ) {
			self::assertSame( MigrationTrigger::NONE, $migrator->dispatch( $context ), $context );
		}
		self::assertSame( 'poison', $migrator->run( MigrationTrigger::CONTEXT_ADMIN )['status'] );
		self::assertSame( $step['attempts'], self::steps()[ self::STEP_SEAT ]['attempts'], 'no automatic attempt' );
		$health = HealthChecks::test_migration( $migrator );
		self::assertSame( 'critical', $health['status'] );
		self::assertStringContainsString( self::STEP_SEAT, $health['label'] );
		self::assertW1( $migrator );

		$out = MigrateCommand::execute( Migrator::for_current_site(), array() );
		self::assertSame( 0, $out['exit'], implode( "\n", $out['lines'] ) );
		$step = self::steps()[ self::STEP_SEAT ];
		self::assertSame( StepLog::DONE, $step['status'] );
		self::assertNull( $step['blocked_reason'] );
		self::assertSame( '0', (string) $step['crash_count'] );
	}

	/**
	 * R0-37 §4.6: five failed attempts of the same step poison it as well.
	 *
	 * @group MIG-011
	 */
	public function test_five_failed_attempts_poison_step(): void {
		self::upgrade_due();
		self::on_fault(
			self::STEP_SEAT,
			'before_status',
			static function (): void {
				throw new RuntimeException( 'Assertion failed in step.' );
			}
		);
		// Activation was attempt 1; attempts 2–4 fail without poison.
		for ( $i = 2; $i <= 4; $i++ ) {
			self::assertSame( 'failed', Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CRON )['status'] );
			self::assertNull( self::steps()[ self::STEP_SEAT ]['blocked_reason'], 'attempt ' . $i );
		}
		self::assertSame( 'failed', Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CRON )['status'] );
		$step = self::steps()[ self::STEP_SEAT ];
		self::assertSame( '5', (string) $step['attempts'] );
		self::assertSame( StepLog::POISON, $step['blocked_reason'] );
		self::assertSame( 'poison', Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CRON )['status'] );
	}

	/**
	 * MIG-012: permanent error (missing ALTER privilege, 1142): step failed
	 * with the DB message, W1 stays, automatic runs back off, the CLI may
	 * retry at once and succeeds after the cause is fixed.
	 *
	 * @group MIG-012
	 */
	public function test_MIG_012_permanent_error_fails_step_and_cli_retries(): void {
		self::upgrade_due();
		self::clear_own_cron();
		self::on_fault(
			self::STEP_SEAT,
			'before_ddl',
			static function (): void {
				throw new StorageException( "ALTER command denied to user 'cc'@'localhost' for table 'wptests_ccap_seat'", 1142 );
			}
		);
		$result = Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_ADMIN );

		self::assertSame( 'failed', $result['status'] );
		self::assertStringContainsString( 'ALTER command denied', $result['error'] );
		$step = self::steps()[ self::STEP_SEAT ];
		self::assertSame( StepLog::FAILED, $step['status'] );
		self::assertStringContainsString( 'ALTER command denied', (string) $step['last_error'] );
		self::assertSame( self::OLD, self::raw_option( Migrator::DB_VERSION ) );

		$migrator = Migrator::for_current_site();
		self::assertW1( $migrator );
		// The 503 path scheduled an immediate run; the cron run itself only waits.
		self::clear_own_cron();
		self::assertSame( MigrationTrigger::WAIT, $migrator->dispatch( MigrationTrigger::CONTEXT_CRON ) );
		$next = wp_next_scheduled( CronHooks::MIGRATE );
		self::assertNotFalse( $next );
		self::assertGreaterThan( time() + MigrationTrigger::BACKOFF_SECONDS - 60, $next, 'retry after the backoff' );

		$out = MigrateCommand::execute( Migrator::for_current_site(), array() );
		self::assertSame( 1, $out['exit'], 'CLI ignores the backoff, still fails' );
		self::assertSame( 3, (int) self::steps()[ self::STEP_SEAT ]['attempts'] );

		remove_all_actions( MigrationFault::HOOK );
		$out = MigrateCommand::execute( Migrator::for_current_site(), array() );
		self::assertSame( 0, $out['exit'] );
		self::assertSame( Schema::db_version(), self::raw_option( Migrator::DB_VERSION ) );
	}

	/**
	 * MIG-013: update callback (old code), admin request, due cron and
	 * reactivation: the update callback only schedules, exactly one run.
	 *
	 * @group MIG-013
	 */
	public function test_MIG_013_double_trigger_runs_once(): void {
		self::upgrade_due();
		self::clear_own_cron();
		$plugin = Plugin::instance();
		self::assertNotNull( $plugin );
		$before = self::steps();

		$update = array(
			'type'    => 'plugin',
			'plugins' => array( self::basename() ),
		);
		$plugin->on_upgrader_process_complete( null, $update );
		$plugin->on_upgrader_process_complete( null, $update );
		self::assertSame( self::OLD, self::raw_option( Migrator::DB_VERSION ), 'update callback never migrates' );
		self::assertSame( array( CronHooks::MIGRATE ), self::own_cron_events() );

		$plugin->on_admin_init();
		self::assertSame( Schema::db_version(), self::raw_option( Migrator::DB_VERSION ) );
		do_action( CronHooks::MIGRATE ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Own hook constant.
		Plugin::activate( false );

		$after = self::steps();
		foreach ( $before as $id => $row ) {
			self::assertSame( (int) $row['attempts'] + 1, (int) $after[ $id ]['attempts'], $id );
		}
		$events = self::own_cron_events();
		self::assertSame( array_values( array_unique( $events ) ), $events, 'no duplicate cron events' );
	}

	/**
	 * MIG-026: admin-ajax and admin-post requests never run the migration,
	 * they only schedule it.
	 *
	 * @group MIG-026
	 * @group GM-166
	 */
	public function test_MIG_026_ajax_and_admin_post_only_schedule(): void {
		self::upgrade_due();
		self::clear_own_cron();
		$plugin = Plugin::instance();
		self::assertNotNull( $plugin );

		add_filter( 'wp_doing_ajax', '__return_true' );
		$plugin->on_admin_init();
		$plugin->on_init();
		remove_filter( 'wp_doing_ajax', '__return_true' );
		self::assertSame( self::OLD, self::raw_option( Migrator::DB_VERSION ) );

		$pagenow            = $GLOBALS['pagenow'] ?? null;
		$GLOBALS['pagenow'] = 'admin-post.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		try {
			$plugin->on_admin_init();
		} finally {
			$GLOBALS['pagenow'] = $pagenow; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
		self::assertSame( self::OLD, self::raw_option( Migrator::DB_VERSION ) );
		self::assertSame( array( CronHooks::MIGRATE ), self::own_cron_events() );
	}

	/**
	 * MIG-025: background jobs pause in W1 and run again afterwards.
	 *
	 * @group MIG-025
	 */
	public function test_MIG_025_background_jobs_pause_in_w1(): void {
		Plugin::activate( false );
		self::clear_own_cron();
		delete_option( HealthChecks::STATE_OPTION );
		update_option( Migrator::FINGERPRINT, 'stale' );
		self::assertFalse( Plugin::schema_ready() );

		do_action( CronHooks::HOURLY ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Own hook constant.
		self::assertFalse( get_option( HealthChecks::STATE_OPTION ), 'hourly housekeeping paused' );
		Plugin::run_outbox_dispatch();
		self::assertNotFalse( wp_next_scheduled( CronHooks::OUTBOX_DISPATCH ), 'dispatch postponed, not dropped' );

		Migrator::for_current_site()->run( MigrationTrigger::CONTEXT_CRON, true );
		self::assertTrue( Plugin::schema_ready() );
		do_action( CronHooks::HOURLY ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Own hook constant.
		self::assertIsArray( get_option( HealthChecks::STATE_OPTION ), 'housekeeping runs after W1' );
	}

	/**
	 * MIG-024: schema newer than code – no action, no DDL, health critical,
	 * CLI exit code 1, writes 503.
	 *
	 * @group MIG-024
	 * @group GM-169
	 */
	public function test_MIG_024_newer_schema_is_never_touched(): void {
		Plugin::activate( false );
		update_option( Migrator::DB_VERSION, '9.0.0+ffffffffffff' );
		update_option( Migrator::FINGERPRINT, 'stale' );
		$ddl = self::count_ddl();

		$out = MigrateCommand::execute( Migrator::for_current_site(), array() );

		self::assertSame( 1, $out['exit'] );
		self::assertSame( 'newer', $out['status'] );
		self::assertSame( 0, $ddl() );
		self::assertSame( 'critical', HealthChecks::test_migration( Migrator::for_current_site() )['status'] );
		self::assertSame( 503, self::rest_write_status( Migrator::for_current_site() ) );
	}

	// ---------------------------------------------------------------------
	// Backfill (P5), budget, restore.
	// ---------------------------------------------------------------------

	/**
	 * MIG-008: `kill -9` inside a batch transaction at 40 %; the batch is
	 * rolled back, the next run resumes at the stored cursor; the result
	 * equals a run without abort and no row is written twice.
	 *
	 * @group MIG-008
	 * @group GM-171
	 */
	public function test_MIG_008_kill_inside_backfill_batch_resumes_at_cursor(): void {
		self::backfill_due( 1000 );
		self::assertFalse( BackfillGate::done( ProbeBackfill::ID ) );

		$results = self::in_parallel(
			array(
				'victim' => static function (): array {
					self::kill_at( ProbeBackfill::ID, 'in_batch', 4 );
					return self::probe_migrator()->with_batch_size( 100 )->run( MigrationTrigger::CONTEXT_CRON );
				},
			)
		);
		self::assertNull( $results['victim'] );
		$step = self::steps()[ ProbeBackfill::ID ];
		self::assertSame( StepLog::RUNNING, $step['status'] );
		self::assertSame( '0:300', $step['cursor'], 'cursor of the last committed batch' );
		self::assertSame( '300', (string) $step['rows_done'] );
		self::assertSame( 300, ProbeBackfill::stats()['filled'], 'batch 4 rolled back' );
		self::assertSame( Schema::db_version(), self::raw_option( Migrator::DB_VERSION ), 'W1 ended before P5' );
		self::assertFalse( BackfillGate::done( ProbeBackfill::ID ), 'gate closed during backfill' );

		self::await_lock_free();
		$result = self::probe_migrator()->with_batch_size( 100 )->run( MigrationTrigger::CONTEXT_CRON );

		self::assertSame( 'done', $result['status'], $result['error'] );
		self::assertSame(
			array(
				'rows'       => 1000,
				'filled'     => 1000,
				'correct'    => 1000,
				'max_writes' => 1,
				'sum_writes' => 1000,
			),
			ProbeBackfill::stats()
		);
		$step = self::steps()[ ProbeBackfill::ID ];
		self::assertSame( StepLog::DONE, $step['status'] );
		self::assertSame( '1', (string) $step['crash_count'] );
		self::assertSame( '1000', (string) $step['rows_done'] );
		self::assertTrue( BackfillGate::done( ProbeBackfill::ID ) );
	}

	/**
	 * MIG-021: restore of a DB snapshot taken in the middle of P5. The
	 * orphaned lease is taken over and the backfill continues to the same
	 * result as the reference run.
	 *
	 * @group MIG-021
	 */
	public function test_MIG_021_restore_of_mid_migration_snapshot_continues(): void {
		self::backfill_due( 600 );
		self::in_parallel(
			array(
				'victim' => static function (): array {
					self::kill_at( ProbeBackfill::ID, 'after_batch', 3 );
					return self::probe_migrator()->with_batch_size( 100 )->run( MigrationTrigger::CONTEXT_CRON );
				},
			)
		);
		self::await_lock_free();
		$snapshot = self::snapshot();
		self::assertNotNull( $snapshot['ccap_lease'][0] ?? null, 'snapshot contains the orphaned lease' );

		self::assertSame( 'done', self::probe_migrator()->run( MigrationTrigger::CONTEXT_CRON )['status'] );
		$reference = ProbeBackfill::stats();

		self::restore( $snapshot );
		self::assertSame( 300, ProbeBackfill::stats()['filled'] );
		$result = self::probe_migrator()->with_batch_size( 100 )->run( MigrationTrigger::CONTEXT_CRON );

		self::assertSame( 'done', $result['status'], $result['error'] );
		self::assertSame( $reference, ProbeBackfill::stats() );
		self::assertSame( 1, ProbeBackfill::stats()['max_writes'] );
		self::assertSame( '1', (string) self::steps()[ ProbeBackfill::ID ]['crash_count'] );
		self::assertTrue( BackfillGate::done( ProbeBackfill::ID ) );
	}

	/**
	 * MIG-009: with a used-up budget each cron run does one batch, hands the
	 * step back to `pending` (no crash count) and re-schedules
	 * `ccap_migrate`; W2 does not block writes.
	 *
	 * @group MIG-009
	 */
	public function test_MIG_009_time_budget_splits_backfill_over_runs(): void {
		self::backfill_due( 450 );
		self::clear_own_cron();

		$first = self::probe_migrator()->with_budget( new MigrationBudget( 0.0 ) )->with_batch_size( 100 )->run( MigrationTrigger::CONTEXT_CRON );
		self::assertSame( 'partial', $first['status'] );
		self::assertSame( 'budget', $first['backfill'][ ProbeBackfill::ID ] );
		self::assertSame( StepLog::PENDING, self::steps()[ ProbeBackfill::ID ]['status'] );
		self::assertSame( 100, ProbeBackfill::stats()['filled'] );
		self::assertSame( array( CronHooks::MIGRATE ), self::own_cron_events(), 'continues in the next cron run' );

		// W2: normal operation, the gate stays closed; frontend only schedules.
		$migrator = self::probe_migrator();
		self::assertTrue( $migrator->fingerprint_current() );
		self::assertSame( 200, self::rest_write_status( $migrator ) );
		self::assertSame( MigrationTrigger::SCHEDULE, $migrator->dispatch( MigrationTrigger::CONTEXT_FRONTEND ) );
		self::assertFalse( BackfillGate::done( ProbeBackfill::ID ) );
		self::assertSame( 'recommended', HealthChecks::test_migration( $migrator )['status'] );

		$runs = 1;
		do {
			$result = self::probe_migrator()->with_budget( new MigrationBudget( 0.0 ) )->with_batch_size( 100 )->run( MigrationTrigger::CONTEXT_CRON );
			++$runs;
		} while ( 'partial' === $result['status'] && $runs < 20 );

		self::assertSame( 'done', $result['status'] );
		self::assertSame( 6, $runs, '5 batches + verify' );
		self::assertSame( 1, ProbeBackfill::stats()['max_writes'] );
		self::assertSame( 450, ProbeBackfill::stats()['correct'] );
		self::assertSame( '0', (string) self::steps()[ ProbeBackfill::ID ]['crash_count'] );
		self::assertTrue( BackfillGate::done( ProbeBackfill::ID ) );

		self::assertNull( MigrationBudget::for_context( MigrationTrigger::CONTEXT_CLI )->seconds(), 'CLI unlimited' );
		self::assertSame( 15.0, MigrationBudget::for_context( MigrationTrigger::CONTEXT_CRON, 30 )->seconds() );
		self::assertSame( 20.0, MigrationBudget::for_context( MigrationTrigger::CONTEXT_ADMIN, 0 )->seconds() );
	}

	/**
	 * MIG-017: rows written below the cursor during the backfill (old code,
	 * downgrade) are found by the verify query and filled in a second pass;
	 * a row that keeps reappearing fails the step after three passes.
	 *
	 * @group MIG-017
	 */
	public function test_MIG_017_verify_pass_catches_rows_below_cursor(): void {
		self::backfill_due( 300 );
		$db    = Database::current();
		$table = $db->table( ProbeBackfill::TABLE );
		$calls = 0;
		self::on_fault(
			ProbeBackfill::ID,
			'after_batch',
			static function () use ( $db, $table, &$calls ): void {
				if ( 2 === ++$calls ) {
					// Old code writes a row without the new value below the cursor, new code adds one above.
					$db->execute( "UPDATE `{$table}` SET val = NULL WHERE id = 5" );
					$db->execute( "INSERT INTO `{$table}` (src) VALUES (301)" );
				}
			}
		);
		$result = self::probe_migrator()->with_batch_size( 100 )->run( MigrationTrigger::CONTEXT_CRON );

		self::assertSame( 'done', $result['status'], $result['error'] );
		$stats = ProbeBackfill::stats();
		self::assertSame( 301, $stats['rows'] );
		self::assertSame( 301, $stats['correct'], 'no row lost' );
		self::assertSame( '1:5', self::steps()[ ProbeBackfill::ID ]['cursor'], 'second pass' );

		// A row that reappears after every batch: failed after three passes, with report.
		self::backfill_due( 50 );
		remove_all_actions( MigrationFault::HOOK );
		self::on_fault(
			ProbeBackfill::ID,
			'after_batch',
			static function () use ( $db, $table ): void {
				$db->execute( "UPDATE `{$table}` SET val = NULL WHERE id = 1" );
			}
		);
		$result = self::probe_migrator()->with_batch_size( 100 )->run( MigrationTrigger::CONTEXT_CRON );
		self::assertSame( 'failed', $result['status'] );
		self::assertStringContainsString( 'Verify found 1 rows after 3 passes', (string) self::steps()[ ProbeBackfill::ID ]['last_error'] );
		self::assertFalse( BackfillGate::done( ProbeBackfill::ID ) );
	}

	/**
	 * Transient errors (deadlock 1213) are retried inside the batch; after
	 * the last retry the run ends without `failed` and resumes later.
	 *
	 * @group MIG-008
	 */
	public function test_deadlock_in_batch_is_retried_then_paused(): void {
		self::backfill_due( 200 );
		$calls = 0;
		self::on_fault(
			ProbeBackfill::ID,
			'in_batch',
			static function () use ( &$calls ): void {
				++$calls;
				if ( 1 === $calls || $calls >= 3 ) {
					throw new StorageException( 'Deadlock found when trying to get lock; try restarting transaction', 1213 );
				}
			}
		);
		$result = self::probe_migrator()->with_batch_size( 100 )->run( MigrationTrigger::CONTEXT_CRON );

		self::assertSame( 'partial', $result['status'] );
		self::assertSame( 'transient', $result['backfill'][ ProbeBackfill::ID ] );
		self::assertSame( 100, ProbeBackfill::stats()['filled'], 'first batch succeeded on retry, second rolled back' );
		self::assertSame( StepLog::PENDING, self::steps()[ ProbeBackfill::ID ]['status'], 'not failed' );
		self::assertSame( 1, ProbeBackfill::stats()['max_writes'] );

		remove_all_actions( MigrationFault::HOOK );
		self::assertSame( 'done', self::probe_migrator()->run( MigrationTrigger::CONTEXT_CRON )['status'] );
		self::assertSame( 200, ProbeBackfill::stats()['correct'] );
		self::assertSame( '0', (string) self::steps()[ ProbeBackfill::ID ]['crash_count'] );
	}

	/**
	 * MIG-023: a done step whose definition changed afterwards is reported
	 * (health warning) and not run again.
	 *
	 * @group MIG-023
	 */
	public function test_MIG_023_changed_definition_is_reported_not_rerun(): void {
		self::backfill_due( 20 );
		self::assertSame( 'done', self::probe_migrator()->run( MigrationTrigger::CONTEXT_CRON )['status'] );

		$changed = new Migrator( Database::current(), COURSE_CAPTAIN_VERSION, array( new ProbeBackfill( 'b000000000000000000000000000000000000002' ) ) );
		self::assertSame( array( ProbeBackfill::ID ), $changed->report()['checksum_mismatch'] );
		$health = HealthChecks::test_migration( $changed );
		self::assertSame( 'recommended', $health['status'] );
		self::assertStringContainsString( ProbeBackfill::ID, $health['label'] );

		$changed->run( MigrationTrigger::CONTEXT_CLI, true );
		self::assertSame( 1, ProbeBackfill::stats()['max_writes'], 'no re-run' );
		self::assertSame( 'a000000000000000000000000000000000000001', self::steps()[ ProbeBackfill::ID ]['checksum'] );
	}

	/**
	 * CLI `--status`: report without writes.
	 *
	 * @group MIG-022
	 */
	public function test_cli_status_reports_without_writing(): void {
		self::backfill_due( 10 );
		$before = self::steps();
		$out    = MigrateCommand::execute( self::probe_migrator(), array( 'status' => '' ) );

		self::assertSame( 1, $out['exit'], 'pending migration' );
		self::assertSame( 'pending', $out['status'] );
		self::assertSame( $before, self::steps() );
		self::assertNull( self::lease_row() );

		$json = MigrateCommand::execute( self::probe_migrator(), array( 'format' => 'json' ) );
		self::assertSame( 0, $json['exit'] );
		$data = json_decode( $json['lines'][0], true );
		self::assertSame( 'done', $data['status'] );
		self::assertSame( 'done', $data['backfill'][ ProbeBackfill::ID ] );
		$status = MigrateCommand::execute( self::probe_migrator(), array( 'status' => '' ) );
		self::assertSame( 0, $status['exit'] );
		self::assertSame( 'ok', $status['status'] );
		self::assertStringContainsString( ProbeBackfill::ID, implode( "\n", $status['lines'] ) );
	}

	// ---------------------------------------------------------------------
	// Helpers.
	// ---------------------------------------------------------------------

	/**
	 * Installed schema with an upgrade due: the generated seat column is
	 * missing and the stored version is older than the code.
	 */
	private static function upgrade_due(): void {
		Plugin::activate( false );
		$db    = Database::current();
		$table = $db->table( 'ccap_seat' );
		$db->execute( "ALTER TABLE `{$table}` DROP INDEX `active_member`, DROP COLUMN `active_user`" );
		update_option( Migrator::DB_VERSION, self::OLD );
		update_option( Migrator::FINGERPRINT, 'stale' );
		$GLOBALS['wpdb']->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Installed schema plus a probe backfill introduced by an upgrade.
	 *
	 * @param int $rows Probe rows.
	 */
	private static function backfill_due( int $rows ): void {
		if ( false === get_option( Migrator::DB_VERSION ) ) {
			Plugin::activate( false );
		}
		$db = Database::current();
		$db->execute( 'DELETE FROM `' . $db->table( 'ccap_migration_step' ) . '` WHERE step_id = ' . $db->prepare( '%s', ProbeBackfill::ID ) );
		ProbeBackfill::create( $rows );
		update_option( Migrator::DB_VERSION, self::OLD );
		update_option( Migrator::FINGERPRINT, 'stale' );
		$GLOBALS['wpdb']->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Migrator of the current site with the probe backfill.
	 *
	 * @return Migrator
	 */
	private static function probe_migrator(): Migrator {
		return new Migrator( Database::current(), COURSE_CAPTAIN_VERSION, array( new ProbeBackfill() ) );
	}

	/**
	 * Run jobs in forked processes, each with its own DB connection, behind
	 * a start barrier. A job that kills itself yields null.
	 *
	 * @param array<string, callable> $jobs Name => job( string $dir ).
	 * @return array<string, mixed>
	 */
	private static function in_parallel( array $jobs ): array {
		global $wpdb;
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$dir = get_temp_dir() . 'ccap-r140-' . wp_generate_password( 8, false );
		wp_mkdir_p( $dir );
		$pids = array();
		foreach ( $jobs as $name => $job ) {
			$pid = pcntl_fork();
			if ( -1 === $pid ) {
				self::fail( 'fork failed' );
			}
			if ( 0 === $pid ) {
				self::child( $dir, (string) $name, $job );
			}
			$pids[ $name ] = $pid;
		}
		touch( $dir . '/go' );
		foreach ( $pids as $pid ) {
			pcntl_waitpid( $pid, $status );
		}
		$results = array();
		foreach ( array_keys( $jobs ) as $name ) {
			$file = $dir . '/' . $name . '.json';
			if ( ! is_file( $file ) ) {
				$results[ $name ] = null;
				continue;
			}
			$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( empty( $data['ok'] ) ) {
				self::fail( 'job ' . $name . ': ' . ( $data['error'] ?? 'unknown' ) );
			}
			$results[ $name ] = $data['value'];
		}
		foreach ( (array) glob( $dir . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();
		return $results;
	}

	/**
	 * Child process: own connection, wait for the barrier, run, report, and
	 * end with SIGKILL so that no destructor touches the parent's connection.
	 *
	 * @param string   $dir  Exchange directory.
	 * @param string   $name Job name.
	 * @param callable $job  Job.
	 * @return never
	 */
	private static function child( string $dir, string $name, callable $job ) {
		try {
			$base = $GLOBALS['wpdb'];
			$own  = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$own->set_prefix( $base->base_prefix );
			if ( is_multisite() ) {
				$own->set_blog_id( get_current_blog_id() );
			}
			$GLOBALS['wpdb'] = $own; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_cache_flush();
			self::await_file( $dir . '/go', 10 );
			$value = $job( $dir );
			file_put_contents( $dir . '/' . $name . '.json', (string) wp_json_encode( array( 'ok' => true, 'value' => $value ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		} catch ( \Throwable $e ) {
			file_put_contents( $dir . '/' . $name . '.json', (string) wp_json_encode( array( 'ok' => false, 'error' => get_class( $e ) . ': ' . $e->getMessage() ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		}
		posix_kill( getmypid(), SIGKILL );
		exit( 1 );
	}

	/**
	 * Wait until a file exists.
	 *
	 * @param string $file    Path.
	 * @param int    $seconds Timeout.
	 */
	private static function await_file( string $file, int $seconds ): void {
		$deadline = microtime( true ) + $seconds;
		while ( ! file_exists( $file ) && microtime( true ) < $deadline ) {
			usleep( 1000 );
		}
	}

	/**
	 * Wait until the server has released the named lock of a killed process.
	 */
	private static function await_lock_free(): void {
		global $wpdb;
		$name     = NamedLock::schema_migration( Database::current() )->name;
		$deadline = microtime( true ) + 10;
		while ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_FREE_LOCK(%s)', $name ) ) && microtime( true ) < $deadline ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			usleep( 20000 );
		}
	}

	/**
	 * The runner that gets the locks stays inside them for a while, so
	 * runners started at the barrier collide with it.
	 */
	private static function slow_winner(): void {
		add_action(
			MigrationFault::HOOK,
			static function ( string $step, string $point ): void {
				if ( Migrator::STEP_TABLES === $step && 'before_ddl' === $point ) {
					usleep( 1500000 );
				}
			},
			10,
			2
		);
	}

	/**
	 * SIGKILL the own process at the n-th time a fault point is reached.
	 *
	 * @param string $step  Step ID.
	 * @param string $point Point.
	 * @param int    $nth   Occurrence.
	 */
	private static function kill_at( string $step, string $point, int $nth = 1 ): void {
		$seen = 0;
		add_action(
			MigrationFault::HOOK,
			static function ( string $at_step, string $at_point ) use ( $step, $point, $nth, &$seen ): void {
				if ( $step === $at_step && $point === $at_point && ++$seen >= $nth ) {
					posix_kill( getmypid(), SIGKILL );
				}
			},
			10,
			2
		);
	}

	/**
	 * Run a callback at a fault point (same process).
	 *
	 * @param string   $step     Step ID.
	 * @param string   $point    Point.
	 * @param callable $callback Callback.
	 */
	private static function on_fault( string $step, string $point, callable $callback ): void {
		add_action(
			MigrationFault::HOOK,
			static function ( string $at_step, string $at_point ) use ( $step, $point, $callback ): void {
				if ( $step === $at_step && $point === $at_point ) {
					$callback();
				}
			},
			10,
			2
		);
	}

	/**
	 * Count CREATE/ALTER TABLE statements from now on.
	 *
	 * @return \Closure(): int
	 */
	private static function count_ddl(): \Closure {
		$count = 0;
		add_filter(
			'query',
			static function ( $query ) use ( &$count ) {
				if ( 1 === preg_match( '/^\s*(CREATE|ALTER)\s+TABLE/i', (string) $query ) ) {
					++$count;
				}
				return $query;
			}
		);
		return static function () use ( &$count ): int {
			return $count;
		};
	}

	/**
	 * Step rows straight from the DB.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function steps(): array {
		return ( new StepLog( Database::current() ) )->all();
	}

	/**
	 * Lease row of the migration, if any.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function lease_row(): ?array {
		$db = Database::current();
		return $db->row( $db->prepare( 'SELECT * FROM `' . $db->table( 'ccap_lease' ) . '` WHERE name = %s', Migrator::LEASE_NAME ) );
	}

	/**
	 * Option value bypassing the cache (other processes wrote it).
	 *
	 * @param string $name Option.
	 * @return string|false
	 */
	private static function raw_option( string $name ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return null === $value ? false : (string) $value;
	}

	/**
	 * Second DB connection on the same site.
	 *
	 * @return \wpdb
	 */
	private static function connection(): \wpdb {
		global $wpdb;
		$other = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$other->set_prefix( $wpdb->prefix );
		return $other;
	}

	/**
	 * W1 for requests: writes to the own namespace 503, reads pass.
	 *
	 * @param Migrator $migrator Migrator.
	 */
	private static function assertW1( Migrator $migrator ): void {
		self::assertFalse( $migrator->fingerprint_current() );
		self::assertSame( 503, self::rest_write_status( $migrator ) );
		self::assertNull( $migrator->filter_rest_maintenance( null, null, new \WP_REST_Request( 'GET', '/course-captain/v1/probe' ) ), 'reads pass' );
	}

	/**
	 * Status the maintenance filter gives a write request (200 = passes).
	 *
	 * @param Migrator $migrator Migrator.
	 * @return int
	 */
	private static function rest_write_status( Migrator $migrator ): int {
		$response = $migrator->filter_rest_maintenance( null, null, new \WP_REST_Request( 'POST', '/course-captain/v1/probe' ) );
		return $response instanceof \WP_REST_Response ? $response->get_status() : 200;
	}

	/**
	 * Snapshot of migration state and probe data ("DB backup").
	 *
	 * @return array<string, mixed>
	 */
	private static function snapshot(): array {
		$db   = Database::current();
		$data = array();
		foreach ( array( 'ccap_migration_step', 'ccap_lease', ProbeBackfill::TABLE ) as $table ) {
			$data[ $table ] = $db->rows( 'SELECT * FROM `' . $db->table( $table ) . '`' );
		}
		foreach ( array( Migrator::DB_VERSION, Migrator::FINGERPRINT, Migrator::BACKFILL_OPEN ) as $option ) {
			$data['options'][ $option ] = self::raw_option( $option );
		}
		return $data;
	}

	/**
	 * Restore a snapshot.
	 *
	 * @param array<string, mixed> $snapshot Snapshot.
	 */
	private static function restore( array $snapshot ): void {
		global $wpdb;
		$db = Database::current();
		foreach ( array( 'ccap_migration_step', 'ccap_lease', ProbeBackfill::TABLE ) as $table ) {
			$db->execute( 'DELETE FROM `' . $db->table( $table ) . '`' );
			foreach ( $snapshot[ $table ] as $row ) {
				$wpdb->insert( $db->table( $table ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		foreach ( $snapshot['options'] as $option => $value ) {
			$wpdb->update( $wpdb->options, array( 'option_value' => $value ), array( 'option_name' => $option ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();
	}
}
