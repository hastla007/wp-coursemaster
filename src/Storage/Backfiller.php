<?php
/**
 * Online backfill engine (R0-37 §4.4, phase P5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Runs backfill steps in batches with a cursor on the primary key.
 *
 * One transaction per batch: lease heartbeat with fence check, `SELECT …
 * FOR UPDATE`, data change, cursor/progress write (I-M4). An abort inside
 * the batch rolls everything back; the next run resumes at the stored
 * cursor (at most one batch read twice, never written twice). At the end a
 * verify query counts the remaining rows; rows below the cursor (written
 * by old code) trigger another pass, at most {@see self::MAX_PASSES}.
 *
 * Cursor format: `<pass>:<last id>`.
 */
final class Backfiller {

	public const BATCH_CLI   = 500;
	public const BATCH_WEB   = 200;
	public const MAX_PASSES  = 3;
	public const TX_RETRIES  = 3;
	public const SLOW_BATCH  = 2.0;
	public const LEASE_TTL   = 600;
	public const DONE        = 'done';
	public const BUDGET      = 'budget';
	public const FAILED      = 'failed';
	public const TRANSIENT   = 'transient';
	public const POISON      = 'poison';
	private const KIND       = 'backfill';
	private const CURSOR_SEP = ':';

	/**
	 * Batches run by this instance (the budget allows at least one per run).
	 *
	 * @var int
	 */
	private int $batches = 0;

	/**
	 * Constructor.
	 *
	 * @param Database        $db         Database access.
	 * @param StepLog         $steps      Step log.
	 * @param Lease           $lease      Held migration lease.
	 * @param MigrationBudget $budget     Time budget of this run.
	 * @param int             $batch_size Rows per batch.
	 * @param string          $release    Plugin version (`released_in`).
	 * @param bool            $manual     Manual run (CLI).
	 */
	public function __construct(
		private Database $db,
		private StepLog $steps,
		private Lease $lease,
		private MigrationBudget $budget,
		private int $batch_size,
		private string $release,
		private bool $manual,
	) {
	}

	/**
	 * Run (or resume) one backfill step.
	 *
	 * @param BackfillStep $step Step.
	 * @return array{status: string, error: string, rows: int}
	 * @throws MigrationFencedException When the lease is lost (no status written).
	 * @throws \CourseCaptain\Course\CourseException Domain errors other than `ccap_db_busy`.
	 */
	public function run( BackfillStep $step ): array {
		$id  = $step->id();
		$row = $this->steps->get( $id );
		if ( null !== $row && StepLog::DONE === $row['status'] ) {
			return self::outcome( self::DONE );
		}
		if ( ! $this->steps->start( $id, $step->module(), self::KIND, $step->checksum(), $this->release, $this->lease, $this->manual ) ) {
			return self::outcome( self::POISON, 'Step is poisoned.' );
		}

		[ $pass, $cursor ] = self::parse_cursor( null === $row ? null : $row['cursor'] );
		$table             = $this->db->table( $step->table() );
		$where             = $step->pending_where();
		$size              = max( 1, $this->batch_size );
		$rows              = 0;
		if ( null === $row || null === $row['rows_total'] ) {
			$this->steps->set_total( $id, (int) $this->db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}" ), $this->lease );
		}

		try {
			while ( true ) {
				if ( $this->batches > 0 && $this->budget->expired() ) {
					$this->steps->pause( $id, $this->lease );
					return self::outcome( self::BUDGET, '', $rows );
				}
				$started = microtime( true );
				$ids     = $this->batch( $step, $table, $where, $pass, $cursor, $size );
				++$this->batches;
				MigrationFault::point( $id, 'after_batch' );

				if ( array() === $ids ) {
					$left = (int) $this->db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}" );
					if ( 0 === $left ) {
						MigrationFault::point( $id, 'before_status' );
						$this->steps->done( $id, $this->lease );
						BackfillGate::close( $id );
						return self::outcome( self::DONE, '', $rows );
					}
					++$pass;
					if ( $pass >= self::MAX_PASSES ) {
						$error = sprintf( 'Verify found %d rows after %d passes.', $left, $pass );
						$this->steps->failed( $id, $error, $this->lease );
						return self::outcome( self::FAILED, $error, $rows );
					}
					$cursor = 0;
					$this->steps->progress( $id, $pass . self::CURSOR_SEP . '0', 0, $this->lease );
					continue;
				}

				$rows  += count( $ids );
				$cursor = (int) max( $ids );
				if ( microtime( true ) - $started > self::SLOW_BATCH ) {
					$size = max( 1, intdiv( $size, 2 ) );
				}
			}
		} catch ( MigrationFencedException $e ) {
			throw $e;
		} catch ( \CourseCaptain\Course\CourseException $e ) {
			// `Database::transaction()` already retried deadlocks (R0-21 T-2): treat as transient.
			if ( 'ccap_db_busy' !== $e->error_code ) {
				throw $e;
			}
			$this->steps->pause( $id, $this->lease );
			return self::outcome( self::TRANSIENT, 'ccap_db_busy', $rows );
		} catch ( StorageException $e ) {
			if ( $e->is_transient() ) {
				$this->steps->pause( $id, $this->lease );
				return self::outcome( self::TRANSIENT, $e->getMessage(), $rows );
			}
			$this->steps->failed( $id, $e->getMessage(), $this->lease );
			return self::outcome( self::FAILED, $e->getMessage(), $rows );
		}
	}

	/**
	 * One batch in one transaction; deadlocks/lock waits are retried.
	 *
	 * @param BackfillStep $step   Step.
	 * @param string       $table  Full table name.
	 * @param string       $where  Pending condition.
	 * @param int          $pass   Pass number.
	 * @param int          $cursor Last processed ID.
	 * @param int          $size   Batch size.
	 * @return list<int> IDs processed (empty at the end).
	 * @throws StorageException After the last retry.
	 */
	private function batch( BackfillStep $step, string $table, string $where, int $pass, int $cursor, int $size ): array {
		$attempt = 0;
		while ( true ) {
			++$attempt;
			try {
				return $this->db->transaction(
					function ( Database $db ) use ( $step, $table, $where, $pass, $cursor, $size ): array {
						if ( ! $this->lease->heartbeat( self::LEASE_TTL ) ) {
							throw new MigrationFencedException( 'Lease lost.' );
						}
						$found = $db->rows( $db->prepare( "SELECT id FROM `{$table}` WHERE id > %d AND ({$where}) ORDER BY id LIMIT %d FOR UPDATE", $cursor, $size ) );
						if ( array() === $found ) {
							return array();
						}
						$ids = array_values( array_map( static fn( array $r ): int => (int) $r['id'], $found ) );
						$step->apply( $db, $ids );
						MigrationFault::point( $step->id(), 'in_batch' );
						$this->steps->progress( $step->id(), $pass . self::CURSOR_SEP . max( $ids ), count( $ids ), $this->lease );
						return $ids;
					}
				);
			} catch ( StorageException $e ) {
				if ( ! $e->is_transient() || $attempt >= self::TX_RETRIES ) {
					throw $e;
				}
			}
		}
	}

	/**
	 * Parse `<pass>:<id>`.
	 *
	 * @param mixed $cursor Stored cursor.
	 * @return array{0: int, 1: int}
	 */
	public static function parse_cursor( $cursor ): array {
		if ( ! is_string( $cursor ) || 1 !== preg_match( '/^(\d+):(\d+)$/', $cursor, $m ) ) {
			return array( 0, 0 );
		}
		return array( (int) $m[1], (int) $m[2] );
	}

	/**
	 * Result shape.
	 *
	 * @param string $status Status.
	 * @param string $error  Error.
	 * @param int    $rows   Rows written in this run.
	 * @return array{status: string, error: string, rows: int}
	 */
	private static function outcome( string $status, string $error = '', int $rows = 0 ): array {
		return array(
			'status' => $status,
			'error'  => $error,
			'rows'   => $rows,
		);
	}
}
