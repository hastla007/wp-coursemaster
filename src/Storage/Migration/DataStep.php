<?php
/**
 * Data step processed in chunks.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Migration;

use CourseCaptain\Storage\Database\Connection;

defined( 'ABSPATH' ) || exit;

/**
 * Data step (P4) or backfill (P5): every chunk runs in one transaction
 * together with the fencing heartbeat, the cursor and the status (R0-37
 * I-M4). Each chunk must be naturally idempotent (`WHERE new IS NULL`, hash
 * comparison, `INSERT … ON DUPLICATE KEY`).
 */
abstract class DataStep extends Step {

	/**
	 * Process one chunk inside the open transaction.
	 *
	 * @param Connection  $db         Connection with an open transaction.
	 * @param string|null $cursor     Cursor of the previous chunk (null = start).
	 * @param int         $batch_size Maximum rows.
	 * @return array{cursor: string|null, rows: int, done: bool}
	 */
	abstract public function run_chunk( Connection $db, ?string $cursor, int $batch_size ): array;

	/**
	 * Rows still matching the predicate (verify query and dry run, R0-37 §4.4).
	 *
	 * @param MigrationContext $context Context.
	 * @return int
	 */
	abstract public function remaining( MigrationContext $context ): int;

	/**
	 * Applied when no row matches the predicate any more.
	 *
	 * @param MigrationContext $context Context.
	 * @return bool
	 */
	public function is_applied( MigrationContext $context ): bool {
		return 0 === $this->remaining( $context );
	}
}
