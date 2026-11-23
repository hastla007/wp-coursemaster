<?php
/**
 * Site Health tests and cron self-healing (R0-10 §4.4, R0-13; R0-05 GM-156, GM-157).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Access\ProtectedStorage;
use CourseCaptain\Access\StorageLayout;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Kernel\CronHooks;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Tests: protected storage (folder, rules), maintenance runs (last run,
 * failed steps), event delivery (dead letters), retention backlog. Each
 * maintenance step is isolated: a failing step is recorded and audited,
 * the others still run (GM-156). Missing recurring events are re-planned
 * on admin and cron requests without double planning (GM-157).
 */
final class HealthChecks {

	public const STATE_OPTION = 'course_captain_cron_state';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'site_status_tests', array( self::class, 'tests' ) );
		add_action( 'admin_init', array( self::class, 'heal' ) );
		add_action(
			'wp_loaded',
			static function (): void {
				if ( wp_doing_cron() ) {
					self::heal();
				}
			}
		);
	}

	/**
	 * Re-plan missing recurring events (idempotent).
	 *
	 * @return void
	 */
	public static function heal(): void {
		CronHooks::schedule_recurring( time() );
	}

	/**
	 * Run maintenance steps in isolation.
	 *
	 * @param array<string, callable> $steps Steps.
	 * @return array<string, string> Failures (step → error class).
	 */
	public static function run_isolated( array $steps ): array {
		$failures = array();
		foreach ( $steps as $name => $step ) {
			try {
				$step();
			} catch ( \Throwable $e ) {
				$failures[ $name ] = get_class( $e );
				Log::write(
					'error',
					'cron.task_failed',
					array(
						'task'  => (string) $name,
						'error' => get_class( $e ),
					)
				);
				AuditLog::write( Database::current(), Actor::cron(), 'cron_task_failed', 'cron', (string) $name, null, array( 'error' => get_class( $e ) ) );
			}
		}
		update_option(
			self::STATE_OPTION,
			array(
				'last_run' => time(),
				'failures' => $failures,
			),
			false
		);
		return $failures;
	}

	/**
	 * Register the tests.
	 *
	 * @param array<string, mixed> $tests Tests.
	 * @return array<string, mixed>
	 */
	public static function tests( $tests ): array {
		$tests                                     = (array) $tests;
		$tests['direct']['course_captain_store']   = array(
			'label' => __( 'Course Captain protected storage', 'course-captain' ),
			'test'  => array( self::class, 'test_storage' ),
		);
		$tests['direct']['course_captain_storage'] = array(
			'label' => __( 'Course Captain direct access to protected files', 'course-captain' ),
			'test'  => array( StorageSelfTest::class, 'health' ),
		);
		$tests['direct']['course_captain_cron']    = array(
			'label' => __( 'Course Captain maintenance', 'course-captain' ),
			'test'  => array( self::class, 'test_cron' ),
		);
		$tests['direct']['course_captain_outbox']  = array(
			'label' => __( 'Course Captain event delivery', 'course-captain' ),
			'test'  => array( self::class, 'test_outbox' ),
		);

		$tests['direct']['course_captain_migration'] = array(
			'label' => __( 'Course Captain database', 'course-captain' ),
			'test'  => array( self::class, 'test_migration' ),
		);
		$tests['direct']['course_captain_identity']  = array(
			'label' => __( 'Course Captain site identity', 'course-captain' ),
			'test'  => array( self::class, 'test_identity' ),
		);
		return $tests;
	}

	/**
	 * Site Health test `course_captain_identity` (R0-16 §1.4/§2.3, E-85).
	 *
	 * @return array<string, mixed>
	 */
	public static function test_identity(): array {
		$state = SiteIdentity::check();
		if ( SiteIdentity::STATUS_COPY === $state['state'] ) {
			return self::result( 'course_captain_identity', 'recommended', __( 'This site is marked as a copy: Course Captain never sends messages here.', 'course-captain' ), __( 'If this copy became a site of its own, confirm "moved" with "wp course-captain identity confirm moved".', 'course-captain' ) );
		}
		if ( $state['held'] ) {
			$changed = implode( ', ', array_map( array( SiteIdentity::class, 'label' ), $state['changed'] ) );
			/* translators: %s: changed parts, e.g. "site address, database" */
			return self::result( 'course_captain_identity', 'critical', sprintf( __( 'Course Captain holds all outgoing messages because the site changed (%s).', 'course-captain' ), $changed ), __( 'After a restore or move, confirm it in the notice on the dashboard or with "wp course-captain identity confirm same_place|moved|copy". Held messages are then listed for release; by default they are discarded.', 'course-captain' ) );
		}
		return self::result( 'course_captain_identity', 'good', __( 'The site identity is unchanged.', 'course-captain' ), '' );
	}

	/**
	 * Site Health test `course_captain_migration` (R0-37 §3.5).
	 *
	 * @param Migrator|null $migrator Migrator (tests); default current site.
	 * @return array<string, mixed>
	 */
	public static function test_migration( ?Migrator $migrator = null ): array {
		$report = ( $migrator ?? Migrator::for_current_site() )->report();
		$cli    = __( 'Run "wp course-captain migrate" or check the database permissions.', 'course-captain' );
		switch ( $report['state'] ) {
			case 'newer':
				return self::result( 'course_captain_migration', 'critical', __( 'The database schema is newer than the plugin.', 'course-captain' ), __( 'Install the matching plugin version or restore database and files together.', 'course-captain' ) );
			case 'blocked':
				/* translators: %s: step IDs */
				return self::result( 'course_captain_migration', 'critical', sprintf( __( 'The database update stopped after repeated aborts: %s', 'course-captain' ), implode( ', ', $report['poison'] ) ), $cli );
			case 'failed':
				/* translators: %s: step IDs */
				return self::result( 'course_captain_migration', 'critical', sprintf( __( 'The database update failed: %s', 'course-captain' ), implode( ', ', $report['failed'] ) ), $cli );
			case 'running':
				return self::result( 'course_captain_migration', 'recommended', __( 'The database update is running.', 'course-captain' ), '' );
			case 'pending':
				return self::result( 'course_captain_migration', 'recommended', __( 'A database update is due.', 'course-captain' ), $cli );
			case 'backfill':
				return self::result( 'course_captain_migration', 'recommended', __( 'Existing data is being updated in the background.', 'course-captain' ), '' );
		}
		if ( array() !== $report['checksum_mismatch'] ) {
			/* translators: %s: step IDs */
			return self::result( 'course_captain_migration', 'recommended', sprintf( __( 'Completed update steps were changed afterwards: %s', 'course-captain' ), implode( ', ', $report['checksum_mismatch'] ) ), '' );
		}
		if ( $report['named_lock_unavailable'] > 0 ) {
			return self::result( 'course_captain_migration', 'recommended', __( 'The database does not support named locks; updates are protected by a lease only.', 'course-captain' ), '' );
		}
		return self::result( 'course_captain_migration', 'good', __( 'The database schema is up to date.', 'course-captain' ), '' );
	}

	/**
	 * Storage test.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_storage(): array {
		$layout = StorageLayout::state();
		if ( $layout['failed_at'] > 0 ) {
			return self::result( 'course_captain_store', 'critical', __( 'Moving the protected folders to the new location failed; the previous folder is still in use.', 'course-captain' ), __( 'Check that the new folder is writable and has enough space. Course Captain tries again after one hour.', 'course-captain' ) );
		}
		try {
			$dir = ProtectedStorage::dir( 'protected' );
			$ok  = wp_is_writable( $dir ) && is_file( $dir . '/.htaccess' ) && is_file( $dir . '/index.php' );
		} catch ( \Throwable $e ) {
			$ok = false;
		}
		return self::result( 'course_captain_store', $ok ? 'good' : 'critical', $ok ? __( 'Protected files are stored in a guarded folder.', 'course-captain' ) : __( 'The protected folder is missing or not writable.', 'course-captain' ), __( 'On nginx add a rule that denies direct access to the course-captain protected and packages folders, or set COURSE_CAPTAIN_PRIVATE_DIR outside the web root.', 'course-captain' ) );
	}

	/**
	 * Maintenance test.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_cron(): array {
		$state    = get_option( self::STATE_OPTION );
		$state    = is_array( $state ) ? $state : array();
		$last     = (int) ( $state['last_run'] ?? 0 );
		$failures = (array) ( $state['failures'] ?? array() );
		if ( false === wp_next_scheduled( CronHooks::HOURLY ) ) {
			return self::result( 'course_captain_cron', 'critical', __( 'The hourly maintenance is not scheduled.', 'course-captain' ), '' );
		}
		if ( array() !== $failures ) {
			/* translators: %s: step names */
			return self::result( 'course_captain_cron', 'recommended', sprintf( __( 'Maintenance steps failed: %s', 'course-captain' ), implode( ', ', array_keys( $failures ) ) ), '' );
		}
		if ( 0 === $last || $last < time() - 3 * HOUR_IN_SECONDS ) {
			return self::result( 'course_captain_cron', 'recommended', __( 'The maintenance has not run in the last three hours.', 'course-captain' ), __( 'Check that WP-Cron or a server cron job runs regularly.', 'course-captain' ) );
		}
		return self::result( 'course_captain_cron', 'good', __( 'Maintenance runs regularly.', 'course-captain' ), '' );
	}

	/**
	 * Event delivery test.
	 *
	 * @return array<string, mixed>
	 */
	public static function test_outbox(): array {
		$db   = Database::current();
		$dead = (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_outbox_delivery' ) . "` WHERE status = 'dead'" );
		/* translators: %d: number */
		return 0 === $dead ? self::result( 'course_captain_outbox', 'good', __( 'All internal events were delivered.', 'course-captain' ), '' ) : self::result( 'course_captain_outbox', 'recommended', sprintf( _n( '%d event delivery failed permanently.', '%d event deliveries failed permanently.', $dead, 'course-captain' ), $dead ), '' );
	}

	/**
	 * Site Health result.
	 *
	 * @param string $test        Test.
	 * @param string $status      good|recommended|critical.
	 * @param string $label       Label.
	 * @param string $description Help.
	 * @return array<string, mixed>
	 */
	private static function result( string $test, string $status, string $label, string $description ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Course Captain', 'course-captain' ),
				'color' => 'good' === $status ? 'blue' : 'orange',
			),
			'description' => '' === $description ? '' : '<p>' . esc_html( $description ) . '</p>',
			'actions'     => '',
			'test'        => $test,
		);
	}
}
