<?php
/**
 * Migration runner without schema (R1-01).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Infrastructure\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * No-op implementation until R1-02 provides the versioned schema.
 */
final class NullMigrationRunner implements MigrationRunner {

	/**
	 * Nothing to migrate yet.
	 *
	 * @return void
	 */
	public function run_on_activation(): void {
	}

	/**
	 * Nothing to schedule yet.
	 *
	 * @return void
	 */
	public function schedule_after_update(): void {
	}

	/**
	 * Nothing to run yet.
	 *
	 * @return void
	 */
	public function maybe_run_in_admin(): void {
	}

	/**
	 * No-op.
	 *
	 * @return void
	 */
	public function run_cron(): void {
	}

	/**
	 * No-op.
	 *
	 * @return void
	 */
	public function on_request(): void {
	}

	/**
	 * No conflicts.
	 *
	 * @return list<string>
	 */
	public function conflicting_tables(): array {
		return array();
	}
}
