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
 * imperative steps (generated columns + unique keys, foreign keys) → version.
 * Backfill (P5) and contract (P6) have no steps yet; lock/resume edge cases
 * are proven in R1-40.
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
	 * Constructor.
	 *
	 * @param Database $db             Database access.
	 * @param string   $plugin_version Plugin version (fingerprint, `released_in`).
	 */
	public function __construct( private Database $db, private string $plugin_version ) {
		$this->inspector = new SchemaInspector( $db );
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
	 * @param string $context Trigger context.
	 * @param bool   $force   Run all phases even when the version is current (CLI/tests).
	 * @return array{status: string, error: string, dbdelta: list<string>, drift: list<string>, fk_issues: array<string, string>}
	 * @throws MigrationFencedException Internally only; reported as status `fenced`.
	 */
	public function run( string $context, bool $force = false ): array {
		$result = array(
			'status'    => 'none',
			'error'     => '',
			'dbdelta'   => array(),
			'drift'     => array(),
			'fk_issues' => array(),
		);

		if ( NetworkGuard::is_network_forced( $this->network_basename() ) ) {
			$result['status'] = 'blocked';
			return $result;
		}

		$lock      = NamedLock::schema_migration( $this->db );
		$lock_mode = $lock->acquire();
		if ( NamedLock::BUSY === $lock_mode ) {
			$result['status'] = 'skipped_locked';
			return $result;
		}

		$bootstrap_lock = false;
		$lease          = null;
		try {
			// P0 bootstrap. Without GET_LOCK and before ccap_lease exists, an option lock protects it.
			if ( NamedLock::UNAVAILABLE === $lock_mode && ! isset( $this->inspector->tables()['ccap_lease'] ) ) {
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

			// Double check inside the lock, bypassing the options cache (QB-10).
			$state = $this->state( true );
			if ( ! $force && $state['stored_version'] === $state['code_version'] && ! $state['steps_open'] ) {
				$this->write_signals();
				return $result;
			}

			// P1 preflight: schema newer than code → no action (R0-06 §6.1).
			if ( null !== $state['stored_version'] && version_compare( Schema::semver_of( $state['stored_version'] ), Schema::VERSION, '>' ) ) {
				$result['status'] = 'newer';
				return $result;
			}

			$steps = new StepLog( $this->db );

			// P2 declarative structure + drift check.
			$steps->start( self::STEP_TABLES, self::MODULE, 'structure', Schema::definition_hash(), $this->plugin_version, $lease );
			$this->assert_lock( $lock, $lease );
			$result['dbdelta'] = array_merge( $result['dbdelta'], $this->db_delta( Schema::table_names() ) );
			$result['drift']   = $this->inspector->drift( Schema::table_names() );
			if ( array() !== $result['drift'] ) {
				$steps->failed( self::STEP_TABLES, 'Schema drift: ' . implode( '; ', $result['drift'] ), $lease );
				$result['status'] = 'failed';
				$result['error']  = 'Schema drift: ' . implode( '; ', $result['drift'] );
				return $result;
			}
			$steps->done( self::STEP_TABLES, $lease );

			// P3 imperative structure: generated columns + unique keys.
			foreach ( Schema::generated_columns() as $step_id => $definition ) {
				$steps->start( $step_id, self::MODULE, 'structure', sha1( (string) wp_json_encode( $definition ) ), $this->plugin_version, $lease );
				try {
					$this->assert_lock( $lock, $lease );
					$this->apply_generated_column( $definition );
				} catch ( MigrationFencedException $e ) {
					throw $e;
				} catch ( Throwable $e ) {
					$steps->failed( $step_id, $e->getMessage(), $lease );
					$result['status'] = 'failed';
					$result['error']  = $step_id . ': ' . $e->getMessage();
					return $result;
				}
				$steps->done( $step_id, $lease );
			}

			// P3 foreign keys: non-fatal (E-48).
			$steps->start( self::STEP_FOREIGN, self::MODULE, 'structure', sha1( (string) wp_json_encode( Schema::foreign_keys() ) ), $this->plugin_version, $lease );
			$this->assert_lock( $lock, $lease );
			$result['fk_issues'] = $this->apply_foreign_keys();
			update_option( self::FK_ISSUES, $result['fk_issues'], false );
			$steps->done( self::STEP_FOREIGN, $lease );

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
			$result['status'] = 'done';
		} catch ( MigrationFencedException $e ) {
			$result['status'] = 'fenced';
			$result['error']  = $e->getMessage();
		} catch ( Throwable $e ) {
			$result['status'] = 'failed';
			$result['error']  = $e->getMessage();
		} finally {
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
			foreach ( $steps->all() as $row ) {
				if ( StepLog::DONE !== $row['status'] ) {
					$open = true;
				}
				if ( 'poison' === $row['blocked_reason'] ) {
					$poison = true;
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
	 * @param array{table: string, column: string, definition: string, key: string, key_columns: list<string>} $definition Step definition.
	 * @return void
	 * @throws StorageException When the column or key is missing afterwards.
	 */
	private function apply_generated_column( array $definition ): void {
		$table = $this->db->table( $definition['table'] );
		if ( ! in_array( $definition['column'], $this->inspector->columns( $definition['table'] ), true ) ) {
			$this->ddl( "ALTER TABLE `{$table}` ADD COLUMN `{$definition['column']}` {$definition['definition']}", self::ERR_DUP_COLUMN );
		}
		$indexes = $this->inspector->indexes( $definition['table'] );
		if ( ! isset( $indexes[ $definition['key'] ] ) ) {
			$columns = '`' . implode( '`,`', $definition['key_columns'] ) . '`';
			$this->ddl( "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$definition['key']}` ({$columns})", self::ERR_DUP_KEY );
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
