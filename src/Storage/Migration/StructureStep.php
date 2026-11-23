<?php
/**
 * Structure (DDL) step.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * DDL step of phase P3 (or P6 for contract steps): DDL → check actual
 * state → status `done` (R0-37 I-M3). DDL commits implicitly and cannot be
 * split; the migrator checks the fence and the mutex right before it.
 */
abstract class StructureStep extends Step {

	/**
	 * Run the DDL. Must be idempotent against the actual state.
	 *
	 * @param MigrationContext $context Context.
	 * @return void
	 */
	abstract public function apply( MigrationContext $context ): void;

	/**
	 * Non-fatal problems of the last apply (e.g. foreign keys, E-48).
	 *
	 * @return list<string>
	 */
	public function warnings(): array {
		return array();
	}

	/**
	 * Count for `--dry-run` (statements that would run).
	 *
	 * @param MigrationContext $context Context.
	 * @return int
	 */
	public function dry_run_count( MigrationContext $context ): int {
		return $this->is_applied( $context ) ? 0 : 1;
	}
}
