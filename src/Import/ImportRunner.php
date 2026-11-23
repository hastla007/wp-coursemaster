<?php
/**
 * Budgeted, resumable commit and activation of large imports (R0-38 §4, §5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Course\CoursePublisher;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Publish\CourseValidator;
use CourseCaptain\Question\QuestionRepository;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Lease;
use CourseCaptain\Storage\MigrationBudget;
use CourseCaptain\Storage\NamedLock;
use CourseCaptain\Storage\StorageException;

defined( 'ABSPATH' ) || exit;

/**
 * One runner for background (`ccap_task_continue` with `[import:<id>,
 * cursor]`), browser polling (REST step), WP-CLI and the synchronous
 * {@see ImportService::run()}; a run may switch between them.
 *
 * - Phases: `plan` (stream the package once into a private NDJSON spool,
 *   SHA-256 kept) → `apply` (chunks from the spool) → status `activating`
 *   → `questions` (switch revisions) → `archive` → `courses` (validator,
 *   one transaction per course, blockers hold the course, E-179) →
 *   `finalize` (`published`, supersede, audit).
 * - A chunk is one transaction: lease heartbeat (fencing), objects (each
 *   in a savepoint: a failing object is isolated, E-176), item log,
 *   issues, cursor. "Written" ⇔ "cursor behind it"; a crash repeats at
 *   most one chunk. Deadlocks/lock waits retry the chunk three times.
 * - A step runs chunks until its budget (min(20 s, max_execution_time/2),
 *   CLI unlimited) or 75 % of the memory limit is used; the chunk size
 *   adapts (AIMD, 1…500, kept in the cursor).
 * - One import at a time per site: lease `import` (TTL 900 s); a second
 *   step gets `busy`, a zombie fails at the fence. Stale runs (heartbeat
 *   older than 600 s, lease expired) are resumed by the hourly watchdog up
 *   to three times, then aborted (E-181).
 * - Commit object errors above max(100, 2 % of the records) abort the run
 *   (IMP-B1.05, E-180); dry-run errors are not counted again.
 * - Cancel stops at the next chunk boundary (`aborted`, IMP-B1.09). Before
 *   activation, discard removes the objects this batch created.
 *
 * Cursor (`stats.run`): `{phase, line, offset, after, chunk, done, total,
 * records, failed, held[], activated, spool, spool_sha256,
 * cancel_requested, auto_resumes, reason}`.
 */
final class ImportRunner {

	public const LEASE              = 'import';
	public const LEASE_TTL          = 900;
	public const STALE_SECONDS      = 600;
	public const TASK_PREFIX        = 'import:';
	public const CHUNK_START        = 50;
	public const CHUNK_MAX          = 500;
	public const TX_RETRIES         = 3;
	public const AUTO_RESUME_DELAYS = array( 60, 300, 900 );
	public const MAX_SECONDS        = 20;

	/**
	 * Validator blockers that only exist because media import is not part
	 * of R1 (video lessons reference external media); reported, not held.
	 *
	 * @var list<string>
	 */
	public const MEDIA_PENDING_RULES = array( 'VAL-027' );

	/**
	 * Fault injection for tests (COURSE_CAPTAIN_TESTING only).
	 *
	 * @var (\Closure(string, int): void)|null
	 */
	private static ?\Closure $fault = null;

	/**
	 * Chunk size chosen after the last chunk (AIMD).
	 *
	 * @var int|null
	 */
	private ?int $next_chunk = null;

	/**
	 * Posts written in the current chunk (caches cleaned on rollback).
	 *
	 * @var list<int>
	 */
	private array $touched = array();

	/**
	 * Audit entries to write after the chunk commit.
	 *
	 * @var list<array{0: string, 1: array<string, mixed>}>
	 */
	private array $audits = array();

	/**
	 * Constructor.
	 *
	 * @param Database      $db      Database access.
	 * @param ImportService $service Import service.
	 */
	public function __construct( private Database $db, private ImportService $service ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), ImportService::current() );
	}

	/**
	 * Background continuation handler.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( CronHooks::TASK_CONTINUE, array( self::class, 'run_task' ), 10, 2 );
	}

	/**
	 * Install a fault hook (tests only; ignored in production).
	 *
	 * @param (\Closure(string, int): void)|null $fault Receives point and position.
	 * @return void
	 */
	public static function inject_fault( ?\Closure $fault ): void {
		self::$fault = $fault;
	}

	/**
	 * `ccap_task_continue` handler for `import:<batch>`; runs as the approver.
	 *
	 * @param mixed $task   Task.
	 * @param mixed $cursor Units done when scheduled (keeps events distinct).
	 * @return void
	 */
	public static function run_task( $task, $cursor = 0 ): void {
		unset( $cursor );
		if ( ! is_string( $task ) || ! str_starts_with( $task, self::TASK_PREFIX ) ) {
			return;
		}
		self::current()->background( (int) substr( $task, strlen( self::TASK_PREFIX ) ) );
	}

	/**
	 * One background step (H) for a batch.
	 *
	 * @param int $batch Batch.
	 * @return array<string, mixed>|null Progress, null when nothing to do.
	 */
	public function background( int $batch ): ?array {
		$row = $this->service->get( $batch );
		if ( null === $row || ! in_array( (string) $row['status'], array( 'importing', 'activating' ), true ) ) {
			return null;
		}
		$approver = (int) $row['approved_by'];
		if ( $approver <= 0 || ! user_can( $approver, 'ccap_import_content' ) || ! user_can( $approver, 'ccap_publish_courses' ) ) {
			$this->abort( $batch, 'approver_rights' );
			return $this->snapshot( $batch, 'aborted' );
		}
		return $this->step( Actor::user( $approver ), $batch, self::budget(), true );
	}

	/**
	 * Default step budget: min(20 s, max_execution_time / 2).
	 *
	 * @param int|null $max_execution_time Override (tests).
	 * @return float
	 */
	public static function budget( ?int $max_execution_time = null ): float {
		$limit = $max_execution_time ?? (int) ini_get( 'max_execution_time' );
		return $limit > 0 ? min( (float) self::MAX_SECONDS, $limit / 2 ) : (float) self::MAX_SECONDS;
	}

	/**
	 * Start an approved batch (`approved → importing`).
	 *
	 * @param Actor $actor      Importer with `ccap_publish_courses`.
	 * @param int   $batch      Batch.
	 * @param bool  $background Schedule the first background step.
	 * @return array<string, mixed> Progress.
	 * @throws CourseException 409 when another import runs.
	 */
	public function start( Actor $actor, int $batch, bool $background = true ): array {
		$this->service->authorize( $actor, $batch, 'publish', array( 'approved' ) );
		$lease = Lease::acquire( $this->db, self::LEASE, self::LEASE_TTL, false );
		if ( null === $lease ) {
			throw new CourseException( 'ccap_import_busy', 'Another import is running.', 409 );
		}
		try {
			$row          = (array) $this->service->get( $batch );
			$stats        = (array) json_decode( (string) $row['stats'], true );
			$stats['run'] = array(
				'phase'        => 'plan',
				'chunk'        => self::CHUNK_START,
				'done'         => 0,
				'total'        => 0,
				'auto_resumes' => 0,
			);
			$this->service->move(
				$batch,
				array( 'approved' ),
				'importing',
				array(
					'heartbeat_at' => $this->db->now(),
					'stats'        => (string) wp_json_encode( $stats ),
				)
			);
		} finally {
			$lease->release();
		}
		if ( $background ) {
			self::schedule( $batch, 0, 0 );
		}
		return $this->snapshot( $batch, 'started' );
	}

	/**
	 * Start and run to the end in this request (no budget).
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return array<string, array<string, int>> Actions per entity.
	 * @throws CourseException When busy (409) or the run aborts (500).
	 */
	public function run_all( Actor $actor, int $batch ): array {
		$this->start( $actor, $batch, false );
		for ( $round = 0; $round <= self::TX_RETRIES; $round++ ) {
			$out = $this->step( $actor, $batch, null );
			if ( 'published' === $out['status'] ) {
				return $this->actions( $batch );
			}
			if ( 'busy' === $out['outcome'] ) {
				throw new CourseException( 'ccap_import_busy', 'Another import is running.', 409 );
			}
			if ( ! in_array( $out['status'], array( 'importing', 'activating' ), true ) ) {
				break;
			}
		}
		$this->abort( $batch, 'error' );
		throw new CourseException( 'ccap_import_failed', 'Import failed.', 500 );
	}

	/**
	 * Run chunks until the budget is used up, the run ends or stops.
	 *
	 * Outcomes: `progress` (more work), `busy` (another step holds the
	 * lease), `transient` (retry scheduled), `fenced` (lease lost), or the
	 * final batch status (`published`, `aborted`).
	 *
	 * @param Actor      $actor   Importer with `ccap_publish_courses`.
	 * @param int        $batch   Batch.
	 * @param float|null $seconds Budget; null = unlimited (CLI, synchronous run).
	 * @param bool       $chain   Schedule the next background step on `progress`.
	 * @return array<string, mixed> Progress with `outcome`.
	 */
	public function step( Actor $actor, int $batch, ?float $seconds, bool $chain = false ): array {
		$this->service->authorize( $actor, $batch, 'publish', array( 'importing', 'activating' ) );
		$lease = Lease::acquire( $this->db, self::LEASE, self::LEASE_TTL, false );
		if ( null === $lease ) {
			return $this->snapshot( $batch, 'busy' );
		}
		$budget  = new MigrationBudget( $seconds );
		$outcome = 'progress';
		$chunks  = 0;
		ImportService::importing( true );
		try {
			while ( true ) {
				// Re-read after the lease (T199): another step may have finished or aborted the run.
				$row    = (array) $this->service->get( $batch );
				$status = (string) $row['status'];
				$run    = self::run_state( $row );
				if ( ! in_array( $status, array( 'importing', 'activating' ), true ) ) {
					$outcome = $status;
					break;
				}
				if ( ! empty( $run['cancel_requested'] ) ) {
					$this->abort( $batch, 'cancelled' );
					$outcome = 'aborted';
					break;
				}
				if ( $chunks > 0 && ( $budget->expired() || self::memory_ratio() > 0.75 ) ) {
					break;
				}
				$started = microtime( true );
				$result  = $this->chunk_with_retries( $actor, $batch, $row, $lease );
				++$chunks;
				$this->flush_audits( $actor, $batch );
				$this->tune( (int) ( $run['chunk'] ?? self::CHUNK_START ), microtime( true ) - $started );
				if ( 'threshold' === $result ) {
					$this->abort( $batch, 'threshold' );
					AuditLog::write( $this->db, $actor, 'import_threshold_exceeded', 'import_batch', (string) $batch, null, array( 'failed' => (int) ( self::run_state( (array) $this->service->get( $batch ) )['failed'] ?? 0 ) ) );
					$outcome = 'aborted';
					break;
				}
				if ( function_exists( 'wp_cache_flush_runtime' ) && wp_using_ext_object_cache() ) {
					wp_cache_flush_runtime();
				}
			}
		} catch ( LeaseLostException $e ) {
			$outcome = 'fenced';
		} catch ( \Throwable $e ) {
			if ( self::is_transient( $e ) ) {
				$outcome = $this->transient( $actor, $batch );
			} else {
				$this->abort( $batch, $e instanceof ImportStopException ? $e->reason : 'error' );
				$outcome = 'aborted';
			}
		} finally {
			ImportService::importing( false );
			$lease->release();
		}
		$progress = $this->snapshot( $batch, $outcome );
		if ( 'progress' === $outcome && $chain ) {
			self::schedule( $batch, (int) $progress['done'], 0 );
		}
		return $progress;
	}

	/**
	 * Resume an aborted run after the pre-checks (R0-38 §4.5).
	 *
	 * @param Actor $actor      Importer with `ccap_publish_courses`.
	 * @param int   $batch      Batch.
	 * @param bool  $background Schedule a background step.
	 * @return array<string, mixed> Progress.
	 * @throws CourseException 409 with the rule code when a pre-check fails.
	 */
	public function resume( Actor $actor, int $batch, bool $background = true ): array {
		$row   = $this->service->authorize( $actor, $batch, 'publish', array( 'aborted' ) );
		$stats = (array) json_decode( (string) $row['stats'], true );
		$run   = (array) ( $stats['run'] ?? array() );
		$this->precheck( $row, $run );
		$run['cancel_requested'] = false;
		$run['reason']           = null;
		$run['phase']            = (string) ( $run['phase'] ?? 'plan' );
		$stats['run']            = $run;
		$this->service->move(
			$batch,
			array( 'aborted' ),
			empty( $run['activated'] ) ? 'importing' : 'activating',
			array(
				'error'        => null,
				'heartbeat_at' => $this->db->now(),
				'stats'        => (string) wp_json_encode( $stats ),
			)
		);
		AuditLog::write( $this->db, $actor, 'import_resumed', 'import_batch', (string) $batch, null, array( 'phase' => $run['phase'] ) );
		if ( $background ) {
			self::schedule( $batch, (int) ( $run['done'] ?? 0 ), 0 );
		}
		return $this->snapshot( $batch, 'resumed' );
	}

	/**
	 * Request cancellation; stops at the next chunk boundary (IMP-B1.09).
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return array<string, mixed> Progress.
	 */
	public function cancel( Actor $actor, int $batch ): array {
		$this->service->authorize( $actor, $batch, 'cancel', array( 'importing', 'activating' ) );
		$this->db->transaction(
			function () use ( $batch ): void {
				$stats                            = $this->lock_stats( $batch );
				$stats['run']['cancel_requested'] = true;
				$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_import_batch' ) . '` SET stats = %s WHERE id = %d', (string) wp_json_encode( $stats ), $batch ) );
			}
		);
		AuditLog::write( $this->db, $actor, 'import_cancel_requested', 'import_batch', (string) $batch );
		// No live step: stop now instead of waiting for one.
		$lease = Lease::acquire( $this->db, self::LEASE, self::LEASE_TTL, false );
		if ( null !== $lease ) {
			try {
				$this->abort( $batch, 'cancelled' );
			} finally {
				$lease->release();
			}
		}
		return $this->snapshot( $batch, 'cancel_requested' );
	}

	/**
	 * Compact progress (R0-38 §4.7), no paths and no personal data.
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return array<string, mixed>
	 */
	public function progress( Actor $actor, int $batch ): array {
		$this->service->authorize( $actor, $batch, 'progress', array( 'uploaded', 'validated', 'failed', 'previewed', 'approved', 'importing', 'activating', 'published', 'superseded', 'aborted', 'discarded' ) );
		return $this->snapshot( $batch, 'status' );
	}

	/**
	 * Hourly watchdog: stale runs are resumed up to three times (E-181),
	 * then aborted.
	 *
	 * @return int Batches handled.
	 */
	public function watchdog(): int {
		$lease = $this->db->scalar( $this->db->prepare( 'SELECT 1 FROM `' . $this->db->table( 'ccap_lease' ) . '` WHERE name = %s AND expires_at >= %s', self::LEASE, $this->db->now() ) );
		if ( null !== $lease ) {
			return 0;
		}
		$rows = $this->db->rows( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_import_batch' ) . "` WHERE status IN ('importing', 'activating') AND (heartbeat_at IS NULL OR heartbeat_at < %s) ORDER BY id LIMIT 10", $this->db->now( -self::STALE_SECONDS ) ) );
		foreach ( $rows as $row ) {
			$batch = (int) $row['id'];
			$run   = self::run_state( $row );
			$tries = (int) ( $run['auto_resumes'] ?? 0 );
			try {
				$this->precheck( $row, $run );
			} catch ( CourseException $e ) {
				$this->abort( $batch, 'package_missing' );
				continue;
			}
			if ( $tries >= count( self::AUTO_RESUME_DELAYS ) ) {
				$this->abort( $batch, 'stale' );
				continue;
			}
			$this->bump_auto_resume( $batch );
			AuditLog::write( $this->db, Actor::cron(), 'import_auto_resumed', 'import_batch', (string) $batch, null, array( 'attempt' => $tries + 1 ) );
			self::schedule( $batch, (int) ( $run['done'] ?? 0 ), 0 );
		}
		return count( $rows );
	}

	/**
	 * Remove objects an aborted batch created (never activated), in chunks.
	 *
	 * @param int $batch Batch.
	 * @return int Objects removed.
	 * @throws CourseException 409 while a step holds the lease.
	 */
	public function discard_created( int $batch ): int {
		$lease = Lease::acquire( $this->db, self::LEASE, self::LEASE_TTL, false );
		if ( null === $lease ) {
			throw new CourseException( 'ccap_import_busy', 'Another import is running.', 409 );
		}
		$removed = 0;
		try {
			do {
				$done = $this->db->transaction(
					function () use ( $batch, $lease, &$removed ): int {
						if ( ! $lease->heartbeat( self::LEASE_TTL ) ) {
							throw new LeaseLostException( 'Lease lost.' );
						}
						$log  = $this->db->table( 'ccap_import_item_log' );
						$rows = $this->db->rows( $this->db->prepare( "SELECT id, entity, object_id FROM `{$log}` WHERE batch_id = %d AND action = 'create' AND outcome = 'ok' AND object_id IS NOT NULL ORDER BY id LIMIT %d", $batch, self::CHUNK_MAX ) );
						foreach ( $rows as $row ) {
							$type = ImportService::ENTITY_TYPES[ (string) $row['entity'] ] ?? '';
							$this->db->execute( 'SAVEPOINT ccap_import_item' );
							try {
								$this->delete_object( $type, (int) $row['object_id'] );
								$this->db->execute( 'RELEASE SAVEPOINT ccap_import_item' );
								$outcome = 'discarded';
								++$removed;
							} catch ( \Throwable $e ) {
								if ( self::is_transient( $e ) ) {
									throw $e;
								}
								$this->db->execute( 'ROLLBACK TO SAVEPOINT ccap_import_item' );
								$outcome = 'discard_kept';
							}
							$this->db->execute( $this->db->prepare( "UPDATE `{$log}` SET outcome = %s WHERE id = %d", $outcome, (int) $row['id'] ) );
						}
						return count( $rows );
					}
				);
			} while ( $done > 0 );
		} finally {
			$lease->release();
		}
		$row = $this->service->get( $batch );
		if ( null !== $row ) {
			self::delete_spool( self::run_state( $row ) );
		}
		return $removed;
	}

	/**
	 * Cursor of a batch row.
	 *
	 * @param array<string, mixed> $row Batch row.
	 * @return array<string, mixed>
	 */
	public static function run_state( array $row ): array {
		$stats = json_decode( (string) ( $row['stats'] ?? '' ), true );
		return is_array( $stats ) && is_array( $stats['run'] ?? null ) ? $stats['run'] : array();
	}

	/**
	 * One chunk with retries for deadlocks and lock waits (RB-04).
	 *
	 * @param Actor                $actor Importer.
	 * @param int                  $batch Batch.
	 * @param array<string, mixed> $row   Batch row.
	 * @param Lease                $lease Held lease.
	 * @return string Chunk result.
	 * @throws \Throwable Non-transient errors, and transient ones after the last retry.
	 */
	private function chunk_with_retries( Actor $actor, int $batch, array $row, Lease $lease ): string {
		$attempt = 0;
		while ( true ) {
			++$attempt;
			$this->touched = array();
			$this->audits  = array();
			try {
				return $this->chunk( $actor, $batch, $row, $lease );
			} catch ( \Throwable $e ) {
				$this->forget_touched();
				if ( ! self::is_transient( $e ) || $attempt >= self::TX_RETRIES ) {
					throw $e;
				}
				usleep( wp_rand( 50000, 200000 ) );
			}
		}
	}

	/**
	 * Dispatch one chunk of the current phase.
	 *
	 * @param Actor                $actor Importer.
	 * @param int                  $batch Batch.
	 * @param array<string, mixed> $row   Batch row.
	 * @param Lease                $lease Held lease.
	 * @return string
	 */
	private function chunk( Actor $actor, int $batch, array $row, Lease $lease ): string {
		switch ( (string) ( self::run_state( $row )['phase'] ?? 'plan' ) ) {
			case 'plan':
				return $this->plan( $batch, $row, $lease );
			case 'apply':
				return $this->apply_chunk( $actor, $batch, $row, $lease );
			case 'questions':
				return $this->publish_chunk( $actor, $batch, $lease );
			case 'archive':
				return $this->archive_chunk( $actor, $batch, $lease );
			case 'courses':
				return $this->course_chunk( $actor, $batch, $row, $lease );
			default:
				return $this->finalize( $actor, $batch, $row, $lease );
		}
	}

	/**
	 * Phase `plan`: stream the package into the spool (no content written).
	 *
	 * @param int                  $batch Batch.
	 * @param array<string, mixed> $row   Batch row.
	 * @param Lease                $lease Held lease.
	 * @return string
	 * @throws ImportStopException When the package copy is missing.
	 */
	private function plan( int $batch, array $row, Lease $lease ): string {
		if ( ! is_file( (string) $row['storage_path'] ) ) {
			throw new ImportStopException( 'package_missing' );
		}
		self::delete_spool( self::run_state( $row ) );
		$stats    = (array) json_decode( (string) $row['stats'], true );
		$manifest = (array) json_decode( (string) $row['manifest'], true );
		$name     = 'spool-' . $batch . '-' . wp_generate_password( 12, false ) . '.ndjson';
		$path     = ProtectedStorage::dir( 'packages' ) . '/' . $name;
		$handle   = fopen( $path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Private spool file (R0-38 §4.2).
		if ( false === $handle ) {
			throw new ImportStopException( 'package_missing' );
		}
		$counts = array(
			'lines'     => 0,
			'questions' => 0,
			'archive'   => 0,
			'courses'   => 0,
		);
		try {
			$scan = $this->service->scan(
				$row,
				$manifest,
				! empty( $stats['partial'] ),
				static function ( array $item ) use ( $handle, &$counts ): void {
					fwrite( $handle, (string) wp_json_encode( $item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Private spool file.
					++$counts['lines'];
					if ( in_array( $item['entity'], array( 'questions', 'flashcards' ), true ) && null !== $item['record'] ) {
						++$counts['questions'];
					} elseif ( 'archive' === $item['action'] ) {
						++$counts['archive'];
					} elseif ( 'courses' === $item['entity'] && 'error' !== $item['action'] ) {
						++$counts['courses'];
					}
				}
			);
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Private spool file.
		}
		$sha = (string) hash_file( 'sha256', $path );
		$this->db->transaction(
			function () use ( $batch, $lease, $name, $sha, $counts, $scan ): void {
				$stats        = $this->lock_stats( $batch, $lease );
				$run          = (array) ( $stats['run'] ?? array() );
				$stats['run'] = array_merge(
					$run,
					array(
						'phase'        => 'apply',
						'line'         => 0,
						'offset'       => 0,
						'after'        => 0,
						'chunk'        => (int) ( $run['chunk'] ?? self::CHUNK_START ),
						'done'         => 0,
						'total'        => $counts['lines'] + $counts['questions'] + $counts['archive'] + $counts['courses'],
						'records'      => max( 1, $scan['records'] ),
						'failed'       => 0,
						'held'         => array(),
						'activated'    => false,
						'spool'        => $name,
						'spool_sha256' => $sha,
					)
				);
				$this->save( $batch, $stats );
			}
		);
		return 'planned';
	}

	/**
	 * Phase `apply`: next records from the spool in one transaction.
	 *
	 * @param Actor                $actor Importer.
	 * @param int                  $batch Batch.
	 * @param array<string, mixed> $row   Batch row.
	 * @param Lease                $lease Held lease.
	 * @return string `applied`, `threshold` or `applied_all`.
	 */
	private function apply_chunk( Actor $actor, int $batch, array $row, Lease $lease ): string {
		$manifest = (array) json_decode( (string) $row['manifest'], true );
		$system   = (string) ( $manifest['source']['system'] ?? '' );
		$package  = (string) ( $manifest['package_id'] ?? '' );
		return $this->db->transaction(
			function () use ( $actor, $batch, $lease, $system, $package ): string {
				$stats  = $this->lock_stats( $batch, $lease );
				$run    = (array) $stats['run'];
				$size   = max( 1, min( self::CHUNK_MAX, $this->next_chunk ?? (int) ( $run['chunk'] ?? self::CHUNK_START ) ) );
				$handle = fopen( self::spool_path( $run ), 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Private spool file.
				if ( false === $handle ) {
					throw new ImportStopException( 'package_missing' );
				}
				fseek( $handle, (int) ( $run['offset'] ?? 0 ) );
				$logs   = array();
				$issues = array();
				$failed = null;
				$read   = 0;
				while ( $read < $size ) {
					$line = fgets( $handle );
					if ( false === $line ) {
						break;
					}
					$item = json_decode( $line, true );
					if ( ! is_array( $item ) ) {
						fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Private spool file.
						throw new ImportStopException( 'package_missing' );
					}
					self::fault( 'apply', (int) ( $run['line'] ?? 0 ) + $read );
					++$read;
					$entity = (string) ( $item['entity'] ?? '' );
					$type   = ImportService::ENTITY_TYPES[ $entity ] ?? '';
					$source = (string) ( $item['source_id'] ?? '' );
					$action = (string) ( $item['action'] ?? '' );
					$record = is_array( $item['record'] ?? null ) ? $item['record'] : null;
					$file   = (string) ( $item['file'] ?? '' );
					$item   = array(
						'entity'    => $entity,
						'source_id' => $source,
						'action'    => $action,
						'record'    => $record,
					);
					if ( '' === $type ) {
						fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Private spool file.
						throw new ImportStopException( 'package_missing' );
					}
					$object  = null;
					$outcome = 'ok';
					$message = null;
					if ( 'error' === $action ) {
						// Known from the dry run (issue already stored, not counted again, E-180).
						$outcome = 'error';
					} elseif ( 'conflict' === $action ) {
						$object  = $this->service->apply_item( $actor, $batch, $system, $package, $item )['object'];
						$outcome = 'conflict_kept';
					} elseif ( 'archive' === $action ) {
						$object  = $this->service->apply_item( $actor, $batch, $system, $package, $item )['object'];
						$outcome = 'pending';
					} else {
						if ( null !== $record ) {
							$failed = $failed ?? $this->failed_keys( $batch );
							foreach ( ImportService::references( $entity, $record ) as $ref_type => $refs ) {
								foreach ( $refs as $ref ) {
									if ( isset( $failed[ $ref_type . ':' . $ref ] ) ) {
										$outcome = 'skipped_dependency';
									}
								}
							}
						}
						if ( 'skipped_dependency' === $outcome ) {
							$issues[]                        = ImportReport::issue( $this->db, $batch, 'commit', $entity, $source, 'dependency_failed', 'error', 'O', $action, array(), $file );
							$failed[ $type . ':' . $source ] = true;
						} else {
							$this->db->execute( 'SAVEPOINT ccap_import_item' );
							try {
								self::fault( 'write', (int) ( $run['line'] ?? 0 ) + $read - 1 );
								$result  = $this->service->apply_item( $actor, $batch, $system, $package, $item );
								$object  = $result['object'];
								$message = null === $result['revision'] ? null : (string) wp_json_encode(
									array_filter(
										array(
											'revision' => $result['revision'],
											'draft'    => $result['draft'],
										)
									)
								);
								if ( ! in_array( $type, ImportService::QUESTION_TYPES, true ) && null !== $object ) {
									$this->touched[] = $object;
								}
								$this->db->execute( 'RELEASE SAVEPOINT ccap_import_item' );
							} catch ( LeaseLostException | ImportStopException $e ) {
								throw $e;
							} catch ( \Throwable $e ) {
								if ( self::is_transient( $e ) ) {
									throw $e;
								}
								$outcome = $this->object_failed( $batch, $entity, $source, $action, $file, $e, $issues, $failed );
							}
							if ( 'failed' === $outcome ) {
								$run['failed'] = (int) ( $run['failed'] ?? 0 ) + 1;
							}
						}
					}
					$logs[] = array(
						'batch_id'   => $batch,
						'attempt'    => 1,
						'entity'     => $entity,
						'source_id'  => substr( $source, 0, 191 ),
						'object_id'  => $object,
						'action'     => $action,
						'outcome'    => $outcome,
						'message'    => $message,
						'created_at' => $this->db->now(),
					);
				}
				$offset = (int) ftell( $handle );
				$end    = false === fgets( $handle );
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Private spool file.
				ImportReport::insert_many( $this->db, 'ccap_import_item_log', $logs );
				ImportReport::insert_many( $this->db, 'ccap_import_issue', $issues );
				$run['line']   = (int) ( $run['line'] ?? 0 ) + $read;
				$run['offset'] = $offset;
				$run['done']   = (int) ( $run['done'] ?? 0 ) + $read;
				$run['chunk']  = $size;
				$status        = null;
				if ( $end ) {
					$run['phase']     = 'questions';
					$run['after']     = 0;
					$run['activated'] = true;
					$status           = 'activating';
				}
				$stats['run'] = $run;
				$this->save( $batch, $stats, $status );
				$limit = max( 100, (int) ceil( 0.02 * (int) ( $run['records'] ?? 1 ) ) );
				return (int) $run['failed'] > $limit ? 'threshold' : ( $end ? 'applied_all' : 'applied' );
			}
		);
	}

	/**
	 * Record an isolated object failure (savepoint rolled back, IMP-B1.04).
	 *
	 * @param int                        $batch  Batch.
	 * @param string                     $entity Entity.
	 * @param string                     $source Source ID.
	 * @param string                     $action Planned action.
	 * @param string                     $file   Package file reference.
	 * @param \Throwable                 $error  Error.
	 * @param list<array<string, mixed>> $issues Issues (appended).
	 * @param array<string, bool>|null   $failed Failed keys (appended).
	 * @param-out array<string, bool>    $failed
	 * @return string `failed`
	 */
	private function object_failed( int $batch, string $entity, string $source, string $action, string $file, \Throwable $error, array &$issues, ?array &$failed ): string {
		$this->db->execute( 'ROLLBACK TO SAVEPOINT ccap_import_item' );
		$this->forget_touched();
		// Only the error code, never the raw message (paths, SQL).
		$code     = $error instanceof CourseException ? $error->error_code : ( $error instanceof StorageException ? 'storage' : 'error' );
		$issues[] = ImportReport::issue( $this->db, $batch, 'commit', $entity, $source, 'write_failed', 'error', 'O', $action, array( 'code' => $code ), $file );
		$failed   = $failed ?? array();
		$failed[ ImportService::ENTITY_TYPES[ $entity ] . ':' . $source ] = true;
		return 'failed';
	}

	/**
	 * Phase `questions`: switch the imported revisions (course-independent, E-50).
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @param Lease $lease Held lease.
	 * @return string
	 */
	private function publish_chunk( Actor $actor, int $batch, Lease $lease ): string {
		return $this->db->transaction(
			function () use ( $actor, $batch, $lease ): string {
				$stats = $this->lock_stats( $batch, $lease );
				$run   = (array) $stats['run'];
				$size  = max( 1, min( self::CHUNK_MAX, $this->next_chunk ?? (int) ( $run['chunk'] ?? self::CHUNK_START ) ) );
				$log   = $this->db->table( 'ccap_import_item_log' );
				$rows  = $this->db->rows( $this->db->prepare( "SELECT id, source_id, object_id, message FROM `{$log}` WHERE batch_id = %d AND entity IN ('questions', 'flashcards') AND action IN ('create', 'update') AND outcome = 'ok' AND id > %d ORDER BY id LIMIT %d", $batch, (int) ( $run['after'] ?? 0 ), $size ) );
				$repo  = QuestionRepository::current();
				$bad   = array();
				foreach ( $rows as $row ) {
					self::fault( 'publish', (int) $row['id'] );
					$meta     = json_decode( (string) $row['message'], true );
					$revision = is_array( $meta ) ? (int) ( $meta['revision'] ?? 0 ) : 0;
					if ( is_array( $meta ) && ! empty( $meta['draft'] ) ) {
						// Draft/review records are imported but not published (R0-10 §1.3, Q-A3-011).
						continue;
					}
					$this->db->execute( 'SAVEPOINT ccap_import_item' );
					try {
						$repo->publish( $actor, (int) $row['object_id'], $revision > 0 ? $revision : null );
						$this->db->execute( 'RELEASE SAVEPOINT ccap_import_item' );
					} catch ( CourseException $e ) {
						if ( self::is_transient( $e ) ) {
							throw $e;
						}
						$this->db->execute( 'ROLLBACK TO SAVEPOINT ccap_import_item' );
						$bad[] = ImportReport::issue( $this->db, $batch, 'activate', 'questions', (string) $row['source_id'], 'write_failed', 'error', 'O', 'publish', array( 'code' => $e->error_code ) );
						$this->db->execute( $this->db->prepare( "UPDATE `{$log}` SET outcome = 'failed' WHERE id = %d", (int) $row['id'] ) );
					}
				}
				ImportReport::insert_many( $this->db, 'ccap_import_issue', $bad );
				if ( array() === $rows ) {
					$run['phase'] = 'archive';
					$run['after'] = 0;
				} else {
					$run['after'] = (int) end( $rows )['id'];
					$run['done']  = (int) ( $run['done'] ?? 0 ) + count( $rows );
				}
				$run['chunk'] = $size;
				$stats['run'] = $run;
				$this->save( $batch, $stats );
				return 'published_questions';
			}
		);
	}

	/**
	 * Phase `archive`: archive objects missing from a full package (never deleted).
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @param Lease $lease Held lease.
	 * @return string
	 */
	private function archive_chunk( Actor $actor, int $batch, Lease $lease ): string {
		return $this->db->transaction(
			function () use ( $actor, $batch, $lease ): string {
				$stats = $this->lock_stats( $batch, $lease );
				$run   = (array) $stats['run'];
				$log   = $this->db->table( 'ccap_import_item_log' );
				$rows  = $this->db->rows( $this->db->prepare( "SELECT id, entity, object_id FROM `{$log}` WHERE batch_id = %d AND action = 'archive' AND outcome = 'pending' ORDER BY id LIMIT %d", $batch, self::CHUNK_MAX ) );
				foreach ( $rows as $row ) {
					if ( null !== $row['object_id'] ) {
						$this->service->archive_object( $actor, ImportService::ENTITY_TYPES[ (string) $row['entity'] ], (int) $row['object_id'] );
					}
					$this->db->execute( $this->db->prepare( "UPDATE `{$log}` SET outcome = 'ok' WHERE id = %d", (int) $row['id'] ) );
				}
				if ( array() === $rows ) {
					$run['phase'] = 'courses';
					$run['after'] = 0;
				} else {
					$run['done'] = (int) ( $run['done'] ?? 0 ) + count( $rows );
				}
				$stats['run'] = $run;
				$this->save( $batch, $stats );
				return 'archived';
			}
		);
	}

	/**
	 * Phase `courses`: one course per transaction; validator first
	 * (`context = publish`), blockers hold the course (E-179).
	 *
	 * @param Actor                $actor Importer.
	 * @param int                  $batch Batch.
	 * @param array<string, mixed> $row   Batch row.
	 * @param Lease                $lease Held lease.
	 * @return string
	 * @throws StorageException When the course is locked by an editor (retried).
	 */
	private function course_chunk( Actor $actor, int $batch, array $row, Lease $lease ): string {
		$log  = $this->db->table( 'ccap_import_item_log' );
		$next = $this->db->row( $this->db->prepare( "SELECT id, source_id, object_id FROM `{$log}` WHERE batch_id = %d AND entity = 'courses' AND action NOT IN ('archive', 'error') AND outcome IN ('ok', 'conflict_kept') AND object_id IS NOT NULL AND id > %d ORDER BY id LIMIT 1", $batch, (int) ( self::run_state( $row )['after'] ?? 0 ) ) );
		if ( null === $next ) {
			return $this->db->transaction(
				function () use ( $batch, $lease ): string {
					$stats                 = $this->lock_stats( $batch, $lease );
					$stats['run']['phase'] = 'finalize';
					$this->save( $batch, $stats );
					return 'courses_done';
				}
			);
		}
		$course = (int) $next['object_id'];
		$lock   = NamedLock::for_key( $this->db, 'course-publish:' . $course );
		if ( NamedLock::ACQUIRED !== $lock->acquire( 2 ) ) {
			throw new StorageException( 'Course is locked.', 1205 );
		}
		try {
			$manifest = (array) json_decode( (string) $row['manifest'], true );
			return $this->db->transaction(
				function () use ( $actor, $batch, $lease, $next, $course, $manifest ): string {
					$stats    = $this->lock_stats( $batch, $lease );
					$run      = (array) $stats['run'];
					$report   = CourseValidator::validate( $course, 'publish' );
					$issues   = array();
					$blockers = 0;
					foreach ( $report['issues'] as $found ) {
						if ( ! in_array( $found['severity'], array( 'blocker', 'warning' ), true ) ) {
							continue;
						}
						$pending = in_array( $found['code'], self::MEDIA_PENDING_RULES, true );
						if ( 'blocker' === $found['severity'] && ! $pending ) {
							++$blockers;
						}
						$issues[] = ImportReport::issue(
							$this->db,
							$batch,
							'activate',
							'courses',
							(string) $next['source_id'],
							(string) $found['code'],
							'blocker' === $found['severity'] && ! $pending ? 'blocker' : 'warning',
							'blocker' === $found['severity'] && ! $pending ? 'O' : 'W',
							'activate',
							array(
								'message' => (string) $found['message'],
								'object'  => (string) ( $found['object']['type'] ?? '' ),
							),
							'',
							(string) $found['message_key']
						);
					}
					ImportReport::insert_many( $this->db, 'ccap_import_issue', $issues );
					if ( $blockers > 0 ) {
						$run['held']    = array_values( array_unique( array_merge( (array) ( $run['held'] ?? array() ), array( $course ) ) ) );
						$this->audits[] = array(
							'import_course_held',
							array(
								'course'   => $course,
								'blockers' => $blockers,
							),
						);
					} else {
						self::fault( 'course', $course );
						/* translators: %s: package ID */
						$note = sprintf( __( 'Import %s', 'course-captain' ), (string) ( $manifest['package_id'] ?? '' ) );
						$this->db->execute( 'SAVEPOINT ccap_import_item' );
						try {
							CoursePublisher::current()->publish( $actor, $course, 'minor', $note );
							$this->db->execute( 'RELEASE SAVEPOINT ccap_import_item' );
						} catch ( CourseException $e ) {
							if ( self::is_transient( $e ) ) {
								throw $e;
							}
							$this->db->execute( 'ROLLBACK TO SAVEPOINT ccap_import_item' );
							$blockers       = 1;
							$run['held']    = array_values( array_unique( array_merge( (array) ( $run['held'] ?? array() ), array( $course ) ) ) );
							$this->audits[] = array(
								'import_course_held',
								array(
									'course'   => $course,
									'blockers' => 0,
								),
							);
							ImportReport::insert_many( $this->db, 'ccap_import_issue', array( ImportReport::issue( $this->db, $batch, 'activate', 'courses', (string) $next['source_id'], 'write_failed', 'blocker', 'O', 'activate', array( 'code' => $e->error_code ) ) ) );
						}
					}
					$run['after'] = (int) $next['id'];
					$run['done']  = (int) ( $run['done'] ?? 0 ) + 1;
					$stats['run'] = $run;
					$this->save( $batch, $stats );
					return $blockers > 0 ? 'course_held' : 'course_activated';
				}
			);
		} finally {
			$lock->release();
		}
	}

	/**
	 * Phase `finalize`: `published`, supersede earlier batches, audit.
	 *
	 * @param Actor                $actor Importer.
	 * @param int                  $batch Batch.
	 * @param array<string, mixed> $row   Batch row.
	 * @param Lease                $lease Held lease.
	 * @return string
	 */
	private function finalize( Actor $actor, int $batch, array $row, Lease $lease ): string {
		$manifest = (array) json_decode( (string) $row['manifest'], true );
		$run      = $this->db->transaction(
			function () use ( $batch, $lease ): array {
				$stats                   = $this->lock_stats( $batch, $lease );
				$run                     = (array) $stats['run'];
				$run['phase']            = 'done';
				$run['done']             = (int) ( $run['total'] ?? 0 );
				$stats['run']            = $run;
				$stats['failed_objects'] = (int) $this->db->scalar( $this->db->prepare( 'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_import_item_log' ) . "` WHERE batch_id = %d AND outcome IN ('failed', 'skipped_dependency', 'error')", $batch ) );
				$stats['held_courses']   = array_values( (array) ( $run['held'] ?? array() ) );
				$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_import_batch' ) . "` SET status = 'published', published_at = %s, heartbeat_at = %s, stats = %s WHERE id = %d AND status = 'activating'", $this->db->now(), $this->db->now(), (string) wp_json_encode( $stats ), $batch ) );
				return $stats;
			}
		);
		$this->service->supersede( $batch, (string) ( $manifest['source']['system'] ?? '' ) );
		self::delete_spool( (array) $run['run'] );
		AuditLog::write(
			$this->db,
			$actor,
			'import_published',
			'import_batch',
			(string) $batch,
			null,
			array(
				'package_id'     => (string) $row['package_id'],
				'failed_objects' => (int) $run['failed_objects'],
				'held_courses'   => count( (array) $run['held_courses'] ),
			)
		);
		return 'finalized';
	}

	/**
	 * Lock the batch row, check the fence, return the stats (inside a transaction).
	 *
	 * @param int        $batch Batch.
	 * @param Lease|null $lease Held lease (null: no fence check).
	 * @return array<string, mixed>
	 * @throws LeaseLostException When the lease was taken over (IMP-B1.02).
	 */
	private function lock_stats( int $batch, ?Lease $lease = null ): array {
		$row = $this->db->row( $this->db->prepare( 'SELECT stats FROM `' . $this->db->table( 'ccap_import_batch' ) . '` WHERE id = %d FOR UPDATE', $batch ) );
		if ( null !== $lease && ! $lease->heartbeat( self::LEASE_TTL ) ) {
			throw new LeaseLostException( 'Lease lost.' );
		}
		$stats        = json_decode( (string) ( $row['stats'] ?? '' ), true );
		$stats        = is_array( $stats ) ? $stats : array();
		$stats['run'] = is_array( $stats['run'] ?? null ) ? $stats['run'] : array();
		return $stats;
	}

	/**
	 * Write stats (cursor) and heartbeat, optionally the status.
	 *
	 * @param int                  $batch  Batch.
	 * @param array<string, mixed> $stats  Stats.
	 * @param string|null          $status New status.
	 * @return void
	 */
	private function save( int $batch, array $stats, ?string $status = null ): void {
		$stats['run']['updated_at'] = gmdate( 'c', $this->db->time() );
		$set                        = null === $status ? '' : $this->db->prepare( ', status = %s', $status );
		$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_import_batch' ) . '` SET stats = %s, heartbeat_at = %s' . $set . ' WHERE id = %d', (string) wp_json_encode( $stats ), $this->db->now(), $batch ) );
	}

	/**
	 * Abort a running batch with a fatal finding (keeps the cursor).
	 *
	 * @param int    $batch  Batch.
	 * @param string $reason Internal rule (see ImportReport::CODES).
	 * @return void
	 */
	private function abort( int $batch, string $reason ): void {
		$this->db->transaction(
			function () use ( $batch, $reason ): void {
				$stats                  = $this->lock_stats( $batch );
				$stats['run']['reason'] = $reason;
				$done                   = $this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_import_batch' ) . "` SET status = 'aborted', error = %s, stats = %s WHERE id = %d AND status IN ('importing', 'activating')", ImportReport::code( $reason ), (string) wp_json_encode( $stats ), $batch ) );
				if ( 1 === $done ) {
					ImportReport::insert_many( $this->db, 'ccap_import_issue', array( ImportReport::issue( $this->db, $batch, 'run', 'batch', null, $reason, 'fatal', in_array( $reason, array( 'stale', 'transient', 'lease_lost' ), true ) ? 'T' : 'F', 'aborted' ) ) );
				}
			}
		);
	}

	/**
	 * Transient failure: schedule an automatic resume (1/5/15 min, E-181),
	 * abort after the third.
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return string Outcome.
	 */
	private function transient( Actor $actor, int $batch ): string {
		$run   = self::run_state( (array) $this->service->get( $batch ) );
		$tries = (int) ( $run['auto_resumes'] ?? 0 );
		if ( $tries >= count( self::AUTO_RESUME_DELAYS ) ) {
			$this->abort( $batch, 'transient' );
			return 'aborted';
		}
		$this->bump_auto_resume( $batch );
		AuditLog::write( $this->db, $actor, 'import_auto_resumed', 'import_batch', (string) $batch, null, array( 'attempt' => $tries + 1 ) );
		self::schedule( $batch, (int) ( $run['done'] ?? 0 ), self::AUTO_RESUME_DELAYS[ $tries ] );
		return 'transient';
	}

	/**
	 * Count an automatic resume.
	 *
	 * @param int $batch Batch.
	 * @return void
	 */
	private function bump_auto_resume( int $batch ): void {
		$this->db->transaction(
			function () use ( $batch ): void {
				$stats                        = $this->lock_stats( $batch );
				$stats['run']['auto_resumes'] = (int) ( $stats['run']['auto_resumes'] ?? 0 ) + 1;
				$this->db->execute( $this->db->prepare( 'UPDATE `' . $this->db->table( 'ccap_import_batch' ) . '` SET stats = %s WHERE id = %d', (string) wp_json_encode( $stats ), $batch ) );
			}
		);
	}

	/**
	 * Pre-checks before a resume: not superseded (IMP-B1.06), package copy
	 * and spool present and unchanged (IMP-B1.07).
	 *
	 * @param array<string, mixed> $row Batch row.
	 * @param array<string, mixed> $run Cursor.
	 * @return void
	 * @throws CourseException 409 with the rule code.
	 */
	private function precheck( array $row, array $run ): void {
		$manifest = (array) json_decode( (string) $row['manifest'], true );
		$system   = (string) ( $manifest['source']['system'] ?? '' );
		$table    = $this->db->table( 'ccap_import_batch' );
		foreach ( $this->db->rows( $this->db->prepare( "SELECT manifest FROM `{$table}` WHERE status IN ('published', 'superseded') AND id > %d", (int) $row['id'] ) ) as $later ) {
			if ( ( ( (array) json_decode( (string) $later['manifest'], true ) )['source']['system'] ?? '' ) === $system ) {
				throw new CourseException( 'ccap_import_state', 'IMP-B1.06', 409 );
			}
		}
		if ( ! is_file( (string) $row['storage_path'] ) ) {
			throw new CourseException( 'ccap_import_state', 'IMP-B1.07', 409 );
		}
		if ( ! empty( $run['spool'] ) && 'plan' !== ( $run['phase'] ?? 'plan' ) ) {
			$path = self::spool_path( $run );
			if ( in_array( $run['phase'], array( 'apply' ), true ) && ( ! is_file( $path ) || hash_file( 'sha256', $path ) !== $run['spool_sha256'] ) ) {
				throw new CourseException( 'ccap_import_state', 'IMP-B1.07', 409 );
			}
		}
	}

	/**
	 * Keys `type:source_id` of objects that failed in this batch's commit.
	 *
	 * @param int $batch Batch.
	 * @return array<string, bool>
	 */
	private function failed_keys( int $batch ): array {
		$out = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT entity, source_id FROM `' . $this->db->table( 'ccap_import_item_log' ) . "` WHERE batch_id = %d AND outcome IN ('failed', 'skipped_dependency')", $batch ) ) as $row ) {
			$out[ ( ImportService::ENTITY_TYPES[ (string) $row['entity'] ] ?? '' ) . ':' . (string) $row['source_id'] ] = true;
		}
		return $out;
	}

	/**
	 * Delete an object this batch created (discard before activation).
	 *
	 * @param string $type   Object type.
	 * @param int    $object_id Object.
	 * @return void
	 * @throws CourseException When WordPress refuses the deletion.
	 */
	private function delete_object( string $type, int $object_id ): void {
		if ( in_array( $type, ImportService::QUESTION_TYPES, true ) ) {
			$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_question_revision' ) . '` WHERE question_id = %d', $object_id ) );
			$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_question' ) . '` WHERE id = %d', $object_id ) );
		} else {
			$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_course_module' ) . '` WHERE course_id = %d OR module_id = %d', $object_id, $object_id ) );
			if ( null !== get_post( $object_id ) && ! wp_delete_post( $object_id, true ) ) {
				throw new CourseException( 'ccap_conflict', 'Object kept.', 409 );
			}
		}
		$this->db->execute( $this->db->prepare( 'DELETE FROM `' . $this->db->table( 'ccap_source_map' ) . '` WHERE object_type = %s AND object_id = %d', $type, $object_id ) );
	}

	/**
	 * Actions per entity of the batch (item log).
	 *
	 * @param int $batch Batch.
	 * @return array<string, array<string, int>>
	 */
	private function actions( int $batch ): array {
		$out = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT entity, action, COUNT(*) AS n FROM `' . $this->db->table( 'ccap_import_item_log' ) . '` WHERE batch_id = %d GROUP BY entity, action', $batch ) ) as $row ) {
			$out[ (string) $row['entity'] ][ (string) $row['action'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Progress snapshot (≤ 3 queries).
	 *
	 * @param int    $batch   Batch.
	 * @param string $outcome Outcome of the call.
	 * @return array<string, mixed>
	 */
	private function snapshot( int $batch, string $outcome ): array {
		$row    = (array) $this->service->get( $batch );
		$run    = self::run_state( $row );
		$counts = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT outcome, COUNT(*) AS n FROM `' . $this->db->table( 'ccap_import_item_log' ) . '` WHERE batch_id = %d GROUP BY outcome', $batch ) ) as $r ) {
			$counts[ (string) $r['outcome'] ] = (int) $r['n'];
		}
		$total = (int) ( $run['total'] ?? 0 );
		$done  = min( $total, (int) ( $run['done'] ?? 0 ) );
		return array(
			'batch'            => $batch,
			'status'           => (string) ( $row['status'] ?? '' ),
			'outcome'          => $outcome,
			'phase'            => (string) ( $run['phase'] ?? '' ),
			'done'             => $done,
			'total'            => $total,
			'percent'          => $total > 0 ? (int) floor( 100 * $done / $total ) : ( 'published' === ( $row['status'] ?? '' ) ? 100 : 0 ),
			'chunk_size'       => (int) ( $run['chunk'] ?? 0 ),
			'counts'           => (object) $counts,
			'failed'           => (int) ( $run['failed'] ?? 0 ),
			'held_courses'     => count( (array) ( $run['held'] ?? array() ) ),
			'cancel_requested' => ! empty( $run['cancel_requested'] ),
			'reason'           => isset( $run['reason'] ) ? ImportReport::code( (string) $run['reason'] ) : null,
			'heartbeat_at'     => $row['heartbeat_at'] ?? null,
		);
	}

	/**
	 * AIMD chunk size: double when fast and lean, halve when slow or heavy.
	 *
	 * @param int   $size     Size of the last chunk.
	 * @param float $duration Seconds.
	 * @return void
	 */
	private function tune( int $size, float $duration ): void {
		$size   = $this->next_chunk ?? $size;
		$memory = self::memory_ratio();
		if ( $duration > 1.5 || $memory > 0.7 ) {
			$this->next_chunk = max( 1, intdiv( $size, 2 ) );
		} elseif ( $duration < 0.5 && $memory < 0.5 ) {
			$this->next_chunk = min( self::CHUNK_MAX, $size * 2 );
		} else {
			$this->next_chunk = $size;
		}
	}

	/**
	 * Used share of the memory limit.
	 *
	 * @return float
	 */
	private static function memory_ratio(): float {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );
		return $limit > 0 ? memory_get_usage( true ) / $limit : 0.0;
	}

	/**
	 * Deadlock, lock wait or "database busy" (retry the chunk, class T).
	 *
	 * @param \Throwable $error Error.
	 * @return bool
	 */
	private static function is_transient( \Throwable $error ): bool {
		return ( $error instanceof StorageException && $error->is_transient() ) || ( $error instanceof CourseException && 'ccap_db_busy' === $error->error_code );
	}

	/**
	 * Clean the caches of posts written in a rolled-back chunk or savepoint.
	 *
	 * @return void
	 */
	private function forget_touched(): void {
		foreach ( $this->touched as $post ) {
			clean_post_cache( $post );
		}
	}

	/**
	 * Write the audit entries collected in the last chunk.
	 *
	 * @param Actor $actor Importer.
	 * @param int   $batch Batch.
	 * @return void
	 */
	private function flush_audits( Actor $actor, int $batch ): void {
		foreach ( $this->audits as $audit ) {
			AuditLog::write( $this->db, $actor, $audit[0], 'import_batch', (string) $batch, null, $audit[1] );
		}
		$this->audits = array();
	}

	/**
	 * Schedule a background step (Action Scheduler if present, else WP-Cron).
	 *
	 * @param int $batch  Batch.
	 * @param int $cursor Units done (keeps consecutive events distinct).
	 * @param int $delay  Seconds.
	 * @return void
	 */
	public static function schedule( int $batch, int $cursor, int $delay ): void {
		$args = array( self::TASK_PREFIX . $batch, $cursor );
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + $delay, CronHooks::TASK_CONTINUE, $args, CronHooks::ACTION_SCHEDULER_GROUP );
			return;
		}
		if ( false === wp_next_scheduled( CronHooks::TASK_CONTINUE, $args ) ) {
			wp_schedule_single_event( time() + $delay, CronHooks::TASK_CONTINUE, $args );
		}
	}

	/**
	 * Absolute spool path of a cursor.
	 *
	 * @param array<string, mixed> $run Cursor.
	 * @return string
	 */
	private static function spool_path( array $run ): string {
		return ProtectedStorage::dir( 'packages' ) . '/' . basename( (string) ( $run['spool'] ?? 'missing' ) );
	}

	/**
	 * Remove the spool file of a cursor.
	 *
	 * @param array<string, mixed> $run Cursor.
	 * @return void
	 */
	private static function delete_spool( array $run ): void {
		if ( empty( $run['spool'] ) ) {
			return;
		}
		$path = self::spool_path( $run );
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Fault injection point (tests only).
	 *
	 * @param string $point    Point.
	 * @param int    $position Position.
	 * @return void
	 */
	private static function fault( string $point, int $position ): void {
		if ( null !== self::$fault && defined( 'COURSE_CAPTAIN_TESTING' ) && true === constant( 'COURSE_CAPTAIN_TESTING' ) ) {
			( self::$fault )( $point, $position );
		}
	}
}
