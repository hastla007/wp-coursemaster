<?php
/**
 * Extension point for the schema upgrade (R0-37 trigger matrix).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Infrastructure\Migration;

defined( 'ABSPATH' ) || exit;

/**
 * Trigger points of the schema upgrade (R0-37 §2.1).
 *
 * R1-01 wires the triggers T-1 (activation), T-2 (plugin update) and
 * T-3 (`admin_init`) to this interface. The schema itself, locks, steps and
 * the decision function follow in R1-02 and R1-40.
 */
interface MigrationRunner {

	/**
	 * T-1: run the migration during activation. Must throw on failure so that
	 * activation aborts before capabilities, cron and rewrite rules are written.
	 *
	 * @return void
	 */
	public function run_on_activation(): void;

	/**
	 * T-2: the plugin files were replaced. Never migrates (old code is loaded),
	 * only schedules `ccap_migrate` once.
	 *
	 * @return void
	 */
	public function schedule_after_update(): void;

	/**
	 * T-3: admin request (not Ajax, not admin-post, not network admin).
	 *
	 * @return void
	 */
	public function maybe_run_in_admin(): void;
}
