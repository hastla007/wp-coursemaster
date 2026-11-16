<?php
/**
 * Registry of all Course Captain cron and background hooks.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

defined( 'ABSPATH' ) || exit;

/**
 * Every own cron hook is listed here so that deactivation and uninstall can
 * remove all events of the current site, including single events
 * (R0-19 §4.8, GM-158, MS-R-10).
 */
final class CronHooks {

	public const HOURLY          = 'ccap_hourly';
	public const OUTBOX_DISPATCH = 'ccap_outbox_dispatch';
	public const TASK_CONTINUE   = 'ccap_task_continue';
	public const MIGRATE         = 'ccap_migrate';
	public const PRIVACY_EXPORT  = 'ccap_privacy_export_generate';

	/**
	 * All R1 cron hooks (R0-19 §4.8).
	 *
	 * @var list<string>
	 */
	public const ALL = array(
		self::HOURLY,
		self::OUTBOX_DISPATCH,
		self::TASK_CONTINUE,
		self::MIGRATE,
		self::PRIVACY_EXPORT,
	);

	/**
	 * Action Scheduler group used for background actions.
	 */
	public const ACTION_SCHEDULER_GROUP = 'course-captain';

	/**
	 * Schedule the recurring events of a freshly activated site.
	 *
	 * @param int $now Current Unix timestamp.
	 * @return void
	 */
	public static function schedule_recurring( int $now ): void {
		if ( false === wp_next_scheduled( self::HOURLY ) ) {
			wp_schedule_event( $now + HOUR_IN_SECONDS, 'hourly', self::HOURLY );
		}
	}

	/**
	 * Remove every event of every own hook on the current site.
	 *
	 * @return void
	 */
	public static function unschedule_all(): void {
		foreach ( self::ALL as $hook ) {
			wp_unschedule_hook( $hook );
		}

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( self::ALL as $hook ) {
				as_unschedule_all_actions( $hook, array(), self::ACTION_SCHEDULER_GROUP );
			}
		}
	}

	/**
	 * Handler of `ccap_hourly`.
	 *
	 * The task runner (R0-19 §4.8, registry slot `tasks`) is added by later
	 * tasks; R1-01 only provides the hook so that the event life cycle is real.
	 *
	 * @return void
	 */
	public static function run_hourly(): void {
		// Access housekeeping (R0-09 G-10, E-201, SEAT-007); access itself never depends on it.
		\CourseCaptain\Access\GrantService::current()->expire_due();
		\CourseCaptain\Access\GrantService::current()->fail_stale_pending();
		\CourseCaptain\Access\GroupService::current()->expire_invitations();
		\CourseCaptain\Event\OutboxDispatcher::current()->purge();
	}
}
