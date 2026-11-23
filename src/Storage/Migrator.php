<?php
/**
 * Schema migrator: phases P0–P4 of R0-37 for the current site.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

use CourseCaptain\Infrastructure\Migration\MigrationRunner;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\NetworkGuard;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the migration for the current site only (`$wpdb->prefix`, R0-06 §6.6).
 *
 * Phases per run (R0-37 §4.1): P0 bootstrap (`ccap_lease`,
 * `ccap_migration_step`) → P1 preflight → P2 `dbDelta` + drift check → P3
 * imperative steps (generated columns + unique keys, foreign keys) → version
 * → P5 online backfill in batches within the time budget (R1-40). Crash
 * detection (`crash_count`, poison), fatal-error handler and fault points
 * follow R0-37 §4.5/§7. P4 and contract (P6) have no steps yet.
 */
final class Migrator implements MigrationRunner {

	public const LEASE_NAME      = 'schema-migration';
	public const LEASE_TTL_DDL   = 1800;
	public const DB_VERSION      = 'course_captain_db_version';
	public const FINGERPRINT     = 'course_captain_schema_fingerprint';
	public const COMPAT_MIN      = 'course_captain_schema_compat_min';
	public const BACKFILL_OPEN   = 'course_captain_backfill_open';
	public const FK_ISSUES       = 'course_captain_fk_issues';
	public const BOOTSTRAP_LOCK  = 'course_captain_migration_bootstrap_lock';
	public const STEP_TABLES     = 'core:0000_tables';
	public const STEP_FOREIGN    = 'core:0003_foreign_keys';
	public const RETRY_AFTER     = 30;
	public const LOCK_NULL_COUNT = 'course_captain_named_lock_unavailable';
	private const BOOTSTRAP_TTL  = 300;
	private const MODULE         = 'core';
	private const ERR_DUP_COLUMN = 1060;
	private const ERR_DUP_KEY    = 1061;

	/**
	 * Inspector of the live schema.
	 *
	 * @var SchemaInspector
	 */
	private SchemaInspector $inspector;

	/**
	 * Registered backfill steps in run order.
	 *
	 * @var list<BackfillStep>
	 */
	private array $backfills;

	/**
	 * Fixed time budget (tests); default per context.
	 *
	 * @var MigrationBudget|null
	 */
	private ?MigrationBudget $budget = null;

	/**
	 * Fixed batch size (tests); default per context.
	 *
	 * @var int|null
	 */
	private ?int $batch_size = null;

	/**
	 * Whether `GET_LOCK` is used (false simulates `GET_LOCK = NULL`).
	 *
	 * @var bool
	 */
	private bool $use_named_lock = true;

	/**
	 * Lease of the running run (fatal-error handler).
	 *
	 * @var Lease|null
	 */
	private ?Lease $active_lease = null;

	/**
	 * Step the running run is working on (fatal-error handler).
	 *
	 * @var string|null
	 */
	private ?string $current_step = null;

	/**
	 * Whether the shutdown handler is registered for this instance.
	 *
	 * @var bool
	 */
	private bool $shutdown_registered = false;

	/**
	 * Constructor.
	 *
	 * @param Database                $db             Database access.
	 * @param string                  $plugin_version Plugin version (fingerprint, `released_in`).
	 * @param list<BackfillStep>|null $backfills      Backfill steps; default {@see self::core_backfills()}.
	 */
	public function __construct( private Database $db, private string $plugin_version, ?array $backfills = null ) {
		$this->inspector = new SchemaInspector( $db );
		$this->backfills = $backfills ?? self::core_backfills();
	}

	/**
	 * Migrator for the current site.
	 *
	 * @return self
	 */
	public static function for_current_site(): self {
		return new self( Database::current(), COURSE_CAPTAIN_VERSION );
	}

	/**
	 * Backfill steps of the core schema: split single partial-access
	 * limits into one limit per kind (E-332).
	 *
	 * @return list<BackfillStep>
	 */
	public static function core_backfills(): array {
		return array( new \CourseCaptain\Access\AccessRuleKindsBackfill() );
	}

	/**
	 * Test seam: fixed time budget.
	 *
	 * @internal
	 * @param MigrationBudget $budget Budget.
	 * @return self
	 */
	public function with_budget( MigrationBudget $budget ): self {
		$this->budget = $budget;
		return $this;
	}

	/**
	 * Test seam: fixed batch size.
	 *
	 * @internal
	 * @param int $size Rows per batch.
	 * @return self
	 */
	public function with_batch_size( int $size ): self {
		$this->batch_size = $size;
		return $this;
	}

	/**
	 * Test seam: behave as if `GET_LOCK` returned NULL (ProxySQL, Galera).
	 *
	 * @internal
	 * @return self
	 */
	public function without_named_lock(): self {
		$this->use_named_lock = false;
		return $this;
	}

	/**
	 * Own-name tables that exist without the ownership marker (KOMP-131).
	 *
	 * @return list<string>
	 */
	public function conflicting_tables(): array {
		$conflicts = array();
		foreach ( $this->inspector->tables() as $name => $table ) {
			if ( in_array( $name, Schema::table_names(), true ) && ! str_starts_with( $table['comment'], Schema::OWNER_COMMENT_PREFIX ) ) {
				$conflicts[] = $name;
			}
		}
		sort( $conflicts );
		return $conflicts;
	}

	/**
	 * Trigger T-1: migrate now; a failure aborts the activation.
	 *
	 * @return void
	 * @throws StorageException When the migration failed.
	 */
	public function run_on_activation(): void {
		$result = $this->run( MigrationTrigger::CONTEXT_ACTIVATION );
		if ( 'failed' === $result['status'] ) {
			throw new StorageException( esc_html( $result['error'] ) );
		}
		if ( 'poison' === $result['status'] ) {
			throw new StorageException( esc_html( 'Migration step blocked after repeated aborts (' . $result['error'] . '); run "wp course-captain migrate".' ) );
		}
		if ( 'skipped_locked' === $result['status'] ) {
			$this->schedule();
		}
	}

	/**
	 * Trigger T-2: only schedule (old code is loaded).
	 *
	 * @return void
	 */
	public function schedule_after_update(): void {
		$this->schedule();
	}

	/**
	 * Trigger T-3 (admin).
	 *
	 * @return void
	 */
	public function maybe_run_in_admin(): void {
		$this->dispatch( MigrationTrigger::CONTEXT_ADMIN );
	}

	/**
	 * Trigger T-5 (`ccap_migrate`).
	 *
	 * @return void
	 */
	public function run_cron(): void {
		$this->dispatch( MigrationTrigger::CONTEXT_CRON );
	}

	/**
	 * Trigger T-4 (frontend, REST, Ajax): cheap fingerprint check; schedules
	 * and guards writes while the schema is not current (W1, R0-37 §3.3).
	 *
	 * @return void
	 */
	public function on_request(): void {
		if ( $this->fingerprint_current() ) {
			return;
		}
		add_filter( 'rest_pre_dispatch', array( $this, 'filter_rest_maintenance' ), 5, 3 );
		$this->dispatch( MigrationTrigger::CONTEXT_FRONTEND );
	}

	/**
	 * `rest_pre_dispatch`: writes to `course-captain/v1` get 503 while the
	 * schema is pending (GM-149); reads pass.
	 *
	 * @param mixed           $result  Pre-dispatch result.
	 * @param mixed           $server  REST server (unused).
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function filter_rest_maintenance( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof WP_REST_Request ) {
			return $result;
		}
		if ( ! str_starts_with( ltrim( $request->get_route(), '/' ), 'course-captain/v1' ) ) {
			return $result;
		}
		if ( in_array( $request->get_method(), array( 'GET', 'HEAD', 'OPTIONS' ), true ) || $this->fingerprint_current() ) {
			return $result;
		}
		$this->schedule();
		return new WP_REST_Response(
			array(
				'code'    => 'ccap_maintenance',
				'message' => __( 'Course Captain is updating its database. Please try again in a few seconds.', 'course-captain' ),
				'data'    => array(
					'status'      => 503,
					'details'     => array( 'reason' => 'migration' ),
					'retry_after' => self::RETRY_AFTER,
				),
			),
			503,
			array( 'Retry-After' => (string) self::RETRY_AFTER )
		);
	}

	/**
	 * Whether the stored fingerprint matches this code (autoload option).
	 *
	 * @return bool
	 */
	public function fingerprint_current(): bool {
		return get_option( self::FINGERPRINT ) === Schema::fingerprint( $this->plugin_version );
	}

	/**
	 * Decide and act for a context.
	 *
	 * @param string $context Trigger context.
	 * @return string Decision taken.
	 */
	public function dispatch( string $context ): string {
		$action = MigrationTrigger::action( $context, $this->state( false ), $this->db->time() );
		if ( MigrationTrigger::RUN === $action ) {
			$result = $this->run( $context );
			if ( 'skipped_locked' === $result['status'] ) {
				$this->schedule();
			}
		} elseif ( MigrationTrigger::SCHEDULE === $action ) {
			$this->schedule();
		} elseif ( MigrationTrigger::WAIT === $action ) {
			$failed = $this->state( false )['last_failed_at'] ?? null;
			$this->schedule( null === $failed ? null : $failed + MigrationTrigger::BACKOFF_SECONDS );
		}
		return $action;
	}

	/**
	 * Schedule `ccap_migrate` once (QB-03).
	 *
	 * @param int|null $at Unix time; default now.
	 * @return void
	 */
	public function schedule( ?int $at = null ): void {
		if ( false === wp_next_scheduled( CronHooks::MIGRATE ) ) {
			wp_schedule_single_event( $at ?? $this->db->time(), CronHooks::MIGRATE );
		}
	}

	/**
	 * Run the migration for the current site.
	 *
	 * Statuses: `done`, `none` (nothing to do), `partial` (budget used up or
	 * transient error; `ccap_migrate` re-scheduled), `skipped_locked`,
	 * `failed`, `poison`, `fenced`, `newer`, `blocked`.
	 *
	 * @param string $context Trigger context.
	 * @param bool   $force   Run all phases even when the version is current (CLI/tests).
	 * @return array{status: string, error: string, dbdelta: list<string>, drift: list<string>, fk_issues: array<string, string>, backfill: array<string, string>}
	 * @throws MigrationFencedException Internally only; reported as status `fenced`.
	 */
	public function run( string $context, bool $force = false ): array {
		$result = array(
			'status'    => 'none',
			'error'     => '',
			'dbdelta'   => array(),
			'drift'     => array(),
			'fk_issues' => array(),
			'backfill'  => array(),
		);

		if ( NetworkGuard::is_network_forced( $this->network_basename() ) ) {
			$result['status'] = 'blocked';
			return $result;
		}

		$manual    = MigrationTrigger::CONTEXT_CLI === $context;
		$budget    = $this->budget ?? MigrationBudget::for_context( $context );
		$lock      = NamedLock::schema_migration( $this->db );
		$lock_mode = $this->use_named_lock ? $lock->acquire() : NamedLock::UNAVAILABLE;
		if ( NamedLock::BUSY === $lock_mode ) {
			$result['status'] = 'skipped_locked';
			return $result;
		}
		if ( NamedLock::UNAVAILABLE === $lock_mode ) {
			// R0-21 §2.5: counter for the health check.
			update_option( self::LOCK_NULL_COUNT, (int) get_option( self::LOCK_NULL_COUNT, 0 ) + 1, false );
		}
		if ( ! $manual ) {
			$this->prepare_long_run();
		}

		$bootstrap_lock = false;
		$lease          = null;
		try {
			// P0 bootstrap. Without GET_LOCK and before both bootstrap tables exist, an option lock protects it.
			if ( NamedLock::UNAVAILABLE === $lock_mode && array() !== array_diff( Schema::BOOTSTRAP_TABLES, array_keys( $this->inspector->tables() ) ) ) {
				$bootstrap_lock = $this->acquire_bootstrap_lock();
				if ( ! $bootstrap_lock ) {
					$result['status'] = 'skipped_locked';
					return $result;
				}
			}
			$result['dbdelta'] = $this->db_delta( Schema::BOOTSTRAP_TABLES );

			$lease = Lease::acquire( $this->db, self::LEASE_NAME, self::LEASE_TTL_DDL, NamedLock::ACQUIRED === $lock_mode );
			if ( null === $lease ) {
				$result['status'] = 'skipped_locked';
				return $result;
			}
			$this->active_lease = $lease;
			$this->register_shutdown_handler();

			// Double check inside the lock, bypassing the options cache (QB-10).
			$state = $this->state( true );
			$steps = new StepLog( $this->db );
			if ( ! $force && $state['stored_version'] === $state['code_version'] && ! $state['steps_open'] ) {
				$this->write_signals();
				return $result;
			}

			// P1 preflight: schema newer than code → no action (R0-06 §6.1).
			if ( null !== $state['stored_version'] && version_compare( Schema::semver_of( $state['stored_version'] ), Schema::VERSION, '>' ) ) {
				$result['status'] = 'newer';
				return $result;
			}

			// Poison (R0-37 §4.6): only a manual run (CLI) tries again.
			if ( $state['poison'] && ! $manual ) {
				$result['status'] = 'poison';
				return $result;
			}

			if ( $force || $state['stored_version'] !== $state['code_version'] || $this->structure_open( $steps ) ) {
				$result = $this->run_structure( $result, $steps, $lock, $lease, $manual );
				if ( 'none' !== $result['status'] ) {
					return $result;
				}
			}

			// P5 backfill (W2), serial, within the time budget.
			$result = $this->run_backfills( $result, $steps, $lease, $budget, $context, $manual );
			if ( 'none' === $result['status'] ) {
				$result['status'] = 'done';
			}
		} catch ( MigrationFencedException $e ) {
			$result['status'] = 'fenced';
			$result['error']  = $e->getMessage();
		} catch ( Throwable $e ) {
			$result['status'] = 'failed';
			$result['error']  = ( null === $this->current_step ? '' : $this->current_step . ': ' ) . $e->getMessage();
			$this->mark_current_failed( $e->getMessage() );
		} finally {
			$this->current_step = null;
			$this->active_lease = null;
			if ( null !== $lease ) {
				try {
					$lease->release();
				} catch ( Throwable $e ) {
					// The lease expires on its own.
					unset( $e );
				}
			}
			if ( $bootstrap_lock ) {
				delete_option( self::BOOTSTRAP_LOCK );
			}
			$lock->release();
		}
		return $result;
	}

	/**
	 * Phases P2–P3 and the version switch (end of W1).
	 *
	 * @param array{status: string, error: string, dbdelta: list<string>, drift: list<string>, fk_issues: array<string, string>, backfill: array<string, string>} $result Result so far.
	 * @param StepLog                                                                                                                                             $steps  Step log.
	 * @param NamedLock                                                                                                                                           $lock   Named lock.
	 * @param Lease                                                                                                                                               $lease  Lease.
	 * @param bool                                                                                                                                                $manual Manual run (CLI).
	 * @return array{status: string, error: string, dbdelta: list<string>, drift: list<string>, fk_issues: array<string, string>, backfill: array<string, string>} Status `none` = continue.
	 * @throws MigrationFencedException When the lease or lock is lost.
	 */
	private function run_structure( array $result, StepLog $steps, NamedLock $lock, Lease $lease, bool $manual ): array {
		// Gates of new backfills close before the structure changes (W2 signal).
		$pending = array();
		foreach ( $this->backfills as $backfill ) {
			$row = $steps->get( $backfill->id() );
			if ( null === $row || StepLog::DONE !== $row['status'] ) {
				$pending[] = $backfill->id();
			}
		}
		BackfillGate::open( $pending );

		// P2 declarative structure + drift check.
		if ( ! $this->begin( $steps, self::STEP_TABLES, Schema::definition_hash(), $lease, $manual ) ) {
			return self::poisoned( $result, self::STEP_TABLES );
		}
		$this->assert_lock( $lock, $lease );
		MigrationFault::point( self::STEP_TABLES, 'before_ddl' );
		$result['dbdelta'] = array_merge( $result['dbdelta'], $this->db_delta( Schema::table_names() ) );
		MigrationFault::point( self::STEP_TABLES, 'after_ddl' );
		$result['drift'] = $this->inspector->drift( Schema::table_names() );
		if ( array() !== $result['drift'] ) {
			$steps->failed( self::STEP_TABLES, 'Schema drift: ' . implode( '; ', $result['drift'] ), $lease );
			$this->current_step = null;
			$result['status']   = 'failed';
			$result['error']    = 'Schema drift: ' . implode( '; ', $result['drift'] );
			return $result;
		}
		MigrationFault::point( self::STEP_TABLES, 'before_status' );
		$steps->done( self::STEP_TABLES, $lease );
		$this->current_step = null;

		// P3 imperative structure: generated columns + unique keys (I-M1/I-M3).
		foreach ( Schema::generated_columns() as $step_id => $definition ) {
			if ( ! $this->begin( $steps, $step_id, sha1( (string) wp_json_encode( $definition ) ), $lease, $manual ) ) {
				return self::poisoned( $result, $step_id );
			}
			try {
				$this->assert_lock( $lock, $lease );
				$this->apply_generated_column( $step_id, $definition );
				MigrationFault::point( $step_id, 'before_status' );
			} catch ( MigrationFencedException $e ) {
				throw $e;
			} catch ( Throwable $e ) {
				$steps->failed( $step_id, $e->getMessage(), $lease );
				$this->current_step = null;
				$result['status']   = 'failed';
				$result['error']    = $step_id . ': ' . $e->getMessage();
				return $result;
			}
			$steps->done( $step_id, $lease );
			$this->current_step = null;
		}

		// P3 foreign keys: non-fatal (E-48).
		if ( ! $this->begin( $steps, self::STEP_FOREIGN, sha1( (string) wp_json_encode( Schema::foreign_keys() ) ), $lease, $manual ) ) {
			return self::poisoned( $result, self::STEP_FOREIGN );
		}
		$this->assert_lock( $lock, $lease );
		MigrationFault::point( self::STEP_FOREIGN, 'before_ddl' );
		$result['fk_issues'] = $this->apply_foreign_keys();
		update_option( self::FK_ISSUES, $result['fk_issues'], false );
		MigrationFault::point( self::STEP_FOREIGN, 'before_status' );
		$steps->done( self::STEP_FOREIGN, $lease );
		$this->current_step = null;

		// End of W1: version, fingerprint, compatibility mark.
		if ( ! $lease->heartbeat( self::LEASE_TTL_DDL ) ) {
			throw new MigrationFencedException( 'Lease lost.' );
		}
		update_option( self::DB_VERSION, Schema::db_version(), true );
		update_option( self::COMPAT_MIN, Schema::COMPAT_MIN, true );
		if ( false === get_option( self::BACKFILL_OPEN ) ) {
			update_option( self::BACKFILL_OPEN, array(), true );
		}
		$this->write_signals();
		return $result;
	}

	/**
	 * Phase P5: backfills one after another (one lease, R0-37 §4.4).
	 *
	 * @param array{status: string, error: string, dbdelta: list<string>, drift: list<string>, fk_issues: array<string, string>, backfill: array<string, string>} $result Result so far.
	 * @param StepLog                                                                                                                                             $steps   Step log.
	 * @param Lease                                                                                                                                               $lease   Lease.
	 * @param MigrationBudget                                                                                                                                     $budget  Time budget.
	 * @param string                                                                                                                                              $context Trigger context.
	 * @param bool                                                                                                                                                $manual  Manual run (CLI).
	 * @return array{status: string, error: string, dbdelta: list<string>, drift: list<string>, fk_issues: array<string, string>, backfill: array<string, string>}
	 * @throws MigrationFencedException When the lease is lost.
	 */
	private function run_backfills( array $result, StepLog $steps, Lease $lease, MigrationBudget $budget, string $context, bool $manual ): array {
		if ( array() === $this->backfills ) {
			return $result;
		}
		$size       = $this->batch_size ?? ( MigrationTrigger::CONTEXT_CLI === $context ? Backfiller::BATCH_CLI : Backfiller::BATCH_WEB );
		$backfiller = new Backfiller( $this->db, $steps, $lease, $budget, $size, $this->plugin_version, $manual );
		foreach ( $this->backfills as $backfill ) {
			$this->current_step                    = $backfill->id();
			$outcome                               = $backfiller->run( $backfill );
			$this->current_step                    = null;
			$result['backfill'][ $backfill->id() ] = $outcome['status'];
			if ( Backfiller::DONE === $outcome['status'] ) {
				continue;
			}
			if ( Backfiller::BUDGET === $outcome['status'] || Backfiller::TRANSIENT === $outcome['status'] ) {
				// Continue right away in the next cron run (R0-37 §2.3).
				$this->schedule();
				$result['status'] = 'partial';
			} elseif ( Backfiller::POISON === $outcome['status'] ) {
				$result['status'] = 'poison';
				$result['error']  = $backfill->id();
			} else {
				$result['status'] = 'failed';
				$result['error']  = $backfill->id() . ': ' . $outcome['error'];
			}
			break;
		}
		return $result;
	}

	/**
	 * Start a structure step and remember it for the fatal-error handler.
	 *
	 * @param StepLog $steps    Step log.
	 * @param string  $step_id  Step ID.
	 * @param string  $checksum Checksum.
	 * @param Lease   $lease    Lease.
	 * @param bool    $manual   Manual run.
	 * @return bool False when poisoned.
	 */
	private function begin( StepLog $steps, string $step_id, string $checksum, Lease $lease, bool $manual ): bool {
		if ( ! $steps->start( $step_id, self::MODULE, 'structure', $checksum, $this->plugin_version, $lease, $manual ) ) {
			return false;
		}
		$this->current_step = $step_id;
		return true;
	}

	/**
	 * Result for a step that was poisoned now.
	 *
	 * @param array{status: string, error: string, dbdelta: list<string>, drift: list<string>, fk_issues: array<string, string>, backfill: array<string, string>} $result  Result.
	 * @param string                                                                                                                                              $step_id Step ID.
	 * @return array{status: string, error: string, dbdelta: list<string>, drift: list<string>, fk_issues: array<string, string>, backfill: array<string, string>}
	 */
	private static function poisoned( array $result, string $step_id ): array {
		$result['status'] = 'poison';
		$result['error']  = $step_id;
		return $result;
	}

	/**
	 * Whether a non-backfill step is not done (interrupted structure run).
	 *
	 * @param StepLog $steps Step log.
	 * @return bool
	 */
	private function structure_open( StepLog $steps ): bool {
		foreach ( $steps->all() as $row ) {
			if ( 'backfill' !== $row['kind'] && StepLog::DONE !== $row['status'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Mark the current step `failed` after an unexpected error (only with a
	 * valid fence, R0-37 §4.5).
	 *
	 * @param string $error Message.
	 * @return void
	 */
	private function mark_current_failed( string $error ): void {
		if ( null === $this->current_step || null === $this->active_lease ) {
			return;
		}
		try {
			( new StepLog( $this->db ) )->failed( $this->current_step, $error, $this->active_lease );
		} catch ( Throwable $e ) {
			// Fence lost: another runner owns the step.
			unset( $e );
		}
	}

	/**
	 * Shutdown callback: a PHP fatal inside a step marks it `failed`.
	 *
	 * @return void
	 */
	public function on_shutdown(): void {
		$this->handle_fatal( error_get_last() );
	}

	/**
	 * Fatal-error handler (R0-37 §4.5): with a valid fence, the step the run
	 * died in becomes `failed`; without, it stays `running` (resume point).
	 *
	 * @param array{type: int, message: string, file?: string, line?: int}|null $error Last error.
	 * @return bool Whether a status was written.
	 */
	public function handle_fatal( ?array $error ): bool {
		if ( null === $error || null === $this->current_step || null === $this->active_lease ) {
			return false;
		}
		if ( ! in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			return false;
		}
		// No paths in stored messages (R0-37 §3.5).
		$message = (string) preg_replace( '#\s+in\s+\S+(?:\s+on line \d+|:\d+).*$#s', '', $error['message'] );
		try {
			( new StepLog( $this->db ) )->failed( $this->current_step, 'PHP fatal error: ' . substr( $message, 0, 200 ), $this->active_lease );
			$this->active_lease->release();
		} catch ( Throwable $e ) {
			unset( $e );
			return false;
		}
		$this->current_step = null;
		$this->active_lease = null;
		return true;
	}

	/**
	 * Register the shutdown handler once per instance.
	 *
	 * @return void
	 */
	private function register_shutdown_handler(): void {
		if ( ! $this->shutdown_registered ) {
			register_shutdown_function( array( $this, 'on_shutdown' ) );
			$this->shutdown_registered = true;
		}
	}

	/**
	 * Admin/cron/overdue: keep running after the client left (R0-37 QB-05).
	 *
	 * @return void
	 */
	private function prepare_long_run(): void {
		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) && ! str_contains( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			set_time_limit( 0 );
		}
	}

	/**
	 * Status report for health check and CLI (`--status`).
	 *
	 * @return array{state: string, stored_version: string|null, code_version: string, steps: array<string, array{status: string, kind: string, attempts: int, crash_count: int, blocked_reason: string|null, rows_done: int, rows_total: int|null, last_error: string|null}>, poison: list<string>, failed: list<string>, checksum_mismatch: list<string>, backfill_open: list<string>, lease_held: bool, named_lock_unavailable: int}
	 */
	public function report(): array {
		$state    = $this->state( true );
		$steps    = array();
		$poison   = array();
		$failed   = array();
		$mismatch = array();
		$sums     = array();
		foreach ( $this->backfills as $backfill ) {
			$sums[ $backfill->id() ] = $backfill->checksum();
		}
		$lease_held = false;
		if ( isset( $this->inspector->tables()['ccap_migration_step'] ) ) {
			foreach ( ( new StepLog( $this->db ) )->all() as $id => $row ) {
				$steps[ $id ] = array(
					'status'         => (string) $row['status'],
					'kind'           => (string) $row['kind'],
					'attempts'       => (int) $row['attempts'],
					'crash_count'    => (int) $row['crash_count'],
					'blocked_reason' => null === $row['blocked_reason'] ? null : (string) $row['blocked_reason'],
					'rows_done'      => (int) $row['rows_done'],
					'rows_total'     => null === $row['rows_total'] ? null : (int) $row['rows_total'],
					'last_error'     => null === $row['last_error'] || '' === $row['last_error'] ? null : (string) $row['last_error'],
				);
				if ( StepLog::POISON === $row['blocked_reason'] ) {
					$poison[] = $id;
				} elseif ( StepLog::FAILED === $row['status'] ) {
					$failed[] = $id;
				}
				// MIG-023: a done step whose definition changed is reported, never re-run.
				if ( StepLog::DONE === $row['status'] && isset( $sums[ $id ] ) && $sums[ $id ] !== $row['checksum'] ) {
					$mismatch[] = $id;
				}
			}
			$lease      = $this->db->table( 'ccap_lease' );
			$lease_held = null !== $this->db->scalar( $this->db->prepare( "SELECT 1 FROM `{$lease}` WHERE name = %s AND expires_at >= %s", self::LEASE_NAME, $this->db->now() ) );
		}

		$open = BackfillGate::open_ids();
		if ( null !== $state['stored_version'] && version_compare( Schema::semver_of( $state['stored_version'] ), Schema::VERSION, '>' ) ) {
			$summary = 'newer';
		} elseif ( array() !== $poison ) {
			$summary = 'blocked';
		} elseif ( array() !== $failed ) {
			$summary = 'failed';
		} elseif ( $lease_held ) {
			$summary = 'running';
		} elseif ( $state['stored_version'] !== $state['code_version'] || ! $state['fingerprint_current'] ) {
			$summary = 'pending';
		} elseif ( $state['steps_open'] || array() !== $open ) {
			$summary = 'backfill';
		} else {
			$summary = 'ok';
		}

		return array(
			'state'                  => $summary,
			'stored_version'         => $state['stored_version'],
			'code_version'           => $state['code_version'],
			'steps'                  => $steps,
			'poison'                 => $poison,
			'failed'                 => $failed,
			'checksum_mismatch'      => $mismatch,
			'backfill_open'          => $open,
			'lease_held'             => $lease_held,
			'named_lock_unavailable' => (int) get_option( self::LOCK_NULL_COUNT, 0 ),
		);
	}

	/**
	 * State for the trigger decision.
	 *
	 * @param bool $fresh Read the version directly from the DB.
	 * @return array{network_forced: bool, stored_version: string|null, code_version: string, fingerprint_current: bool, steps_open: bool, poison: bool, last_failed_at: int|null, scheduled_at: int|null}
	 */
	public function state( bool $fresh ): array {
		$stored = $fresh ? $this->stored_option_raw( self::DB_VERSION ) : get_option( self::DB_VERSION, null );
		$open   = false;
		$poison = false;
		$failed = null;
		if ( isset( $this->inspector->tables()['ccap_migration_step'] ) ) {
			$steps = new StepLog( $this->db );
			$rows  = $steps->all();
			foreach ( $rows as $row ) {
				if ( StepLog::DONE !== $row['status'] ) {
					$open = true;
				}
				if ( 'poison' === $row['blocked_reason'] ) {
					$poison = true;
				}
			}
			// A registered backfill without a row is open as well.
			foreach ( $this->backfills as $backfill ) {
				if ( ! isset( $rows[ $backfill->id() ] ) ) {
					$open = true;
				}
			}
			$failed = $steps->last_failure();
		}
		$scheduled = wp_next_scheduled( CronHooks::MIGRATE );
		return array(
			'network_forced'      => NetworkGuard::is_network_forced( $this->network_basename() ),
			'stored_version'      => is_string( $stored ) ? $stored : null,
			'code_version'        => Schema::db_version(),
			'fingerprint_current' => $this->fingerprint_current(),
			'steps_open'          => $open,
			'poison'              => $poison,
			'last_failed_at'      => $failed,
			'scheduled_at'        => false === $scheduled ? null : (int) $scheduled,
		);
	}

	/**
	 * Run dbDelta for the given tables.
	 *
	 * @param array $tables Tables without prefix.
	 * @phpstan-param list<string> $tables
	 * @return list<string> Messages of executed changes.
	 * @throws StorageException On database errors.
	 */
	private function db_delta( array $tables ): array {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$prefix  = $this->db->wpdb()->prefix;
		$charset = $this->db->wpdb()->get_charset_collate();
		$sql     = array();
		foreach ( $tables as $table ) {
			$sql[] = Schema::create_statement( $table, $prefix, $charset );
		}
		$this->db->wpdb()->last_error = '';
		$messages                     = dbDelta( $sql );
		if ( '' !== Database::error_of( $this->db->wpdb() ) ) {
			throw StorageException::from_wpdb( $this->db->wpdb(), 'dbDelta' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: shortened DB error for logs.
		}
		return array_values( array_map( 'strval', $messages ) );
	}

	/**
	 * Add a generated column and its unique key if missing (I-M1/I-M3).
	 *
	 * @param string                                                                                           $step_id    Step ID (fault points).
	 * @param array{table: string, column: string, definition: string, key: string, key_columns: list<string>} $definition Step definition.
	 * @return void
	 * @throws StorageException When the column or key is missing afterwards.
	 */
	private function apply_generated_column( string $step_id, array $definition ): void {
		$table = $this->db->table( $definition['table'] );
		if ( ! in_array( $definition['column'], $this->inspector->columns( $definition['table'] ), true ) ) {
			MigrationFault::point( $step_id, 'before_ddl' );
			$this->ddl( "ALTER TABLE `{$table}` ADD COLUMN `{$definition['column']}` {$definition['definition']}", self::ERR_DUP_COLUMN );
			MigrationFault::point( $step_id, 'after_ddl' );
		}
		$indexes = $this->inspector->indexes( $definition['table'] );
		if ( ! isset( $indexes[ $definition['key'] ] ) ) {
			$columns = '`' . implode( '`,`', $definition['key_columns'] ) . '`';
			MigrationFault::point( $step_id, 'before_ddl' );
			$this->ddl( "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$definition['key']}` ({$columns})", self::ERR_DUP_KEY );
			MigrationFault::point( $step_id, 'after_ddl' );
		}
		$indexes = $this->inspector->indexes( $definition['table'] );
		if ( ! in_array( $definition['column'], $this->inspector->columns( $definition['table'] ), true )
			|| ! isset( $indexes[ $definition['key'] ] )
			|| $indexes[ $definition['key'] ]['columns'] !== $definition['key_columns'] ) {
			throw new StorageException( 'Generated column or key missing after DDL.' );
		}
	}

	/**
	 * Create missing foreign keys; returns failures (non-fatal).
	 *
	 * @return array<string, string> Constraint name => error.
	 */
	private function apply_foreign_keys(): array {
		$existing = $this->inspector->foreign_keys();
		$issues   = array();
		foreach ( Schema::foreign_keys() as $fk ) {
			$name = self::foreign_key_name( $this->db->wpdb()->prefix, $fk['table'], $fk['column'] );
			if ( isset( $existing[ $name ] ) ) {
				continue;
			}
			$table = $this->db->table( $fk['table'] );
			$ref   = $this->db->table( $fk['ref_table'] );
			try {
				$this->db->execute( "ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$fk['column']}`) REFERENCES `{$ref}` (`{$fk['ref_column']}`) ON DELETE {$fk['on_delete']} ON UPDATE RESTRICT" );
			} catch ( StorageException $e ) {
				$issues[ $fk['table'] . '.' . $fk['column'] ] = substr( $e->getMessage(), 0, 200 );
			}
		}
		return $issues;
	}

	/**
	 * Deterministic FK name, unique per database and ≤ 64 characters.
	 *
	 * @param string $prefix Site table prefix.
	 * @param string $table  Table without prefix.
	 * @param string $column Column.
	 * @return string
	 */
	public static function foreign_key_name( string $prefix, string $table, string $column ): string {
		return 'ccapfk_' . md5( $prefix . $table . '.' . $column );
	}

	/**
	 * Run DDL; a "duplicate" error counts as success (R0-37 §3.1 Restrisiko).
	 *
	 * @param string $sql         DDL.
	 * @param int    $dup_errno   Error number meaning "already there".
	 * @return void
	 * @throws StorageException On other errors.
	 */
	private function ddl( string $sql, int $dup_errno ): void {
		try {
			$this->db->execute( $sql );
		} catch ( StorageException $e ) {
			if ( $dup_errno !== $e->getCode() ) {
				throw $e;
			}
		}
	}

	/**
	 * Before DDL: lease and (if real) named lock must still be ours.
	 *
	 * @param NamedLock $lock  Named lock.
	 * @param Lease     $lease Lease.
	 * @return void
	 * @throws MigrationFencedException When the lease or lock is lost.
	 */
	private function assert_lock( NamedLock $lock, Lease $lease ): void {
		if ( ! $lease->heartbeat( self::LEASE_TTL_DDL ) ) {
			throw new MigrationFencedException( 'Lease lost.' );
		}
		if ( NamedLock::ACQUIRED === $lock->acquire_state() && ! $lock->still_held() ) {
			throw new MigrationFencedException( 'Named lock lost.' );
		}
	}

	/**
	 * Option lock for the bootstrap without GET_LOCK (R0-37 §3.1).
	 *
	 * @return bool
	 */
	private function acquire_bootstrap_lock(): bool {
		$options = $this->db->wpdb()->options;
		$now     = (string) $this->db->time();
		$this->db->execute( $this->db->prepare( "DELETE FROM `{$options}` WHERE option_name = %s AND option_value < %s", self::BOOTSTRAP_LOCK, $now ) );
		$rows = $this->db->execute(
			$this->db->prepare( "INSERT IGNORE INTO `{$options}` (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::BOOTSTRAP_LOCK, (string) ( $this->db->time() + self::BOOTSTRAP_TTL ) )
		);
		return 1 === $rows;
	}

	/**
	 * Option value straight from the table, bypassing all caches.
	 *
	 * @param string $name Option name.
	 * @return string|null
	 */
	private function stored_option_raw( string $name ): ?string {
		$options = $this->db->wpdb()->options;
		return $this->db->scalar( $this->db->prepare( "SELECT option_value FROM `{$options}` WHERE option_name = %s", $name ) );
	}

	/**
	 * Write the cheap autoload signal for frontend checks.
	 *
	 * @return void
	 */
	private function write_signals(): void {
		update_option( self::FINGERPRINT, Schema::fingerprint( $this->plugin_version ), true );
	}

	/**
	 * Plugin basename for the network check.
	 *
	 * @return string
	 */
	private function network_basename(): string {
		return defined( 'COURSE_CAPTAIN_FILE' ) ? plugin_basename( COURSE_CAPTAIN_FILE ) : 'course-captain/course-captain.php';
	}
}
