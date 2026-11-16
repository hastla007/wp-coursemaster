<?php
/**
 * System status: one check list for screen, CLI and diagnostics
 * (R0-13 §2.1 H-01…H-34 subset; R0-30 S-22).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Notification\OutboundMode;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\Migrator;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Every check returns `id`, `status` (`ok`/`warn`/`fail`/`info`), a short
 * text, a hint and object IDs (delivery, grant, session or batch IDs) –
 * never personal data, paths or secrets. Read-only; requires
 * `ccap_view_system_status` (#22) or a system actor (CLI).
 */
final class OpsStatus {

	public const CAP               = 'ccap_view_system_status';
	public const MAX_IDS           = 20;
	public const HEARTBEAT_STALE   = 900;
	public const BACKLOG_STALE     = 3600;
	public const PENDING_GRANT_H   = 24;
	public const DELIVERY_STATUSES = array( 'pending', 'dispatching', 'delivered', 'failed', 'dead', 'skipped' );

	/**
	 * Full report.
	 *
	 * @param Actor         $actor    Viewer.
	 * @param Migrator|null $migrator Migrator (tests); default current site.
	 * @return array{overall: string, checks: list<array{id: string, status: string, label: string, hint: string, ids: list<string>}>, migration: array<string, mixed>, outbox: array<string, int>, notifications: array<string, int>, integrations: list<array<string, mixed>>}
	 * @throws CourseException Without the capability.
	 */
	public static function report( Actor $actor, ?Migrator $migrator = null ): array {
		self::assert_can_view( $actor );
		$db           = Database::current();
		$migration    = ( $migrator ?? Migrator::for_current_site() )->report();
		$outbox       = self::outbox_counts( $db );
		$notes        = self::notification_counts( $db );
		$integrations = IntegrationStatus::all( $actor );
		$checks       = array(
			/* translators: 1: plugin version, 2: schema version, 3: contract version */
			self::check( 'H-01', 'info', sprintf( __( 'Course Captain %1$s, schema %2$s, contract %3$s', 'course-captain' ), COURSE_CAPTAIN_VERSION, (string) ( $migration['stored_version'] ?? '-' ), defined( 'COURSE_CAPTAIN_CONTRACT_VERSION' ) ? (string) COURSE_CAPTAIN_CONTRACT_VERSION : '-' ), '' ),
			self::migration_check( $migration ),
			self::from_site_health( 'H-07', HealthChecks::test_cron() ),
			self::from_site_health( 'H-12', HealthChecks::test_storage() ),
			self::outbound_check(),
			self::hold_check( $notes['held'] ),
			self::jobs_check( $db, $outbox ),
			self::backlog_check( $db ),
			self::from_site_health( 'mail', NotificationService::test_mail_health() ),
			self::grants_check( $db ),
			self::broken_attempts_check( $db ),
			self::imports_check( $db ),
			self::secret_check(),
			self::integrations_check( $integrations ),
			self::from_site_health( 'hardening', \CourseCaptain\Security\Hardening::health_result() ),
		);
		// R1-30: conflicts with other plugins and the server setup (R0-20 §2, §3).
		$checks = array_merge( $checks, Compatibility::checks() );
		return array(
			'overall'       => self::overall( $checks ),
			'checks'        => $checks,
			'migration'     => $migration,
			'outbox'        => $outbox,
			'notifications' => $notes,
			'integrations'  => $integrations,
		);
	}

	/**
	 * Capability check (#22; system actors such as the CLI pass).
	 *
	 * @param Actor $actor Actor.
	 * @return void
	 * @throws CourseException Without the capability.
	 */
	public static function assert_can_view( Actor $actor ): void {
		if ( $actor->is_system() ) {
			return;
		}
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, self::CAP ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to view the system status.', 403 );
		}
	}

	/**
	 * Worst status of a check list (`info` counts as `ok`).
	 *
	 * @param list<array{status: string}> $checks Checks.
	 * @return string ok|warn|fail
	 */
	public static function overall( array $checks ): string {
		$statuses = array_column( $checks, 'status' );
		if ( in_array( 'fail', $statuses, true ) ) {
			return 'fail';
		}
		return in_array( 'warn', $statuses, true ) ? 'warn' : 'ok';
	}

	/**
	 * Deliveries per status.
	 *
	 * @param Database $db Database.
	 * @return array<string, int>
	 */
	public static function outbox_counts( Database $db ): array {
		$out = array_fill_keys( self::DELIVERY_STATUSES, 0 );
		foreach ( $db->rows( 'SELECT status, COUNT(*) AS n FROM `' . $db->table( 'ccap_outbox_delivery' ) . '` GROUP BY status' ) as $row ) {
			$out[ (string) $row['status'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Notification counters: held, failed (24 h), unknown, queued.
	 *
	 * @param Database $db Database.
	 * @return array<string, int>
	 */
	public static function notification_counts( Database $db ): array {
		$table = $db->table( 'ccap_notification' );
		return array(
			'held'    => (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'held'" ),
			'failed'  => (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'failed' AND created_at > %s", $db->now( -DAY_IN_SECONDS ) ) ),
			'unknown' => (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'unknown'" ),
			'queued'  => (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE status IN ('queued', 'retry_wait')" ),
		);
	}

	/**
	 * Translated status word (never colour only, R0-30).
	 *
	 * @param string $status ok|warn|fail|info.
	 * @return string
	 */
	public static function status_label( string $status ): string {
		return match ( $status ) {
			'fail'  => __( 'Error', 'course-captain' ),
			'warn'  => __( 'Warning', 'course-captain' ),
			'info'  => __( 'Info', 'course-captain' ),
			default => __( 'OK', 'course-captain' ),
		};
	}

	/**
	 * H-02/H-03 from the migration report.
	 *
	 * @param array<string, mixed> $report Migrator::report().
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function migration_check( array $report ): array {
		$ids  = array_values( array_merge( (array) $report['poison'], (array) $report['failed'] ) );
		$hint = __( 'Use "Retry now" below or run "wp course-captain migrate".', 'course-captain' );
		switch ( $report['state'] ) {
			case 'newer':
				return self::check( 'H-02', 'fail', __( 'The database schema is newer than the plugin.', 'course-captain' ), __( 'Install the matching plugin version or restore database and files together.', 'course-captain' ) );
			case 'blocked':
				return self::check( 'H-03', 'fail', __( 'The database update stopped after repeated aborts.', 'course-captain' ), $hint, $ids );
			case 'failed':
				return self::check( 'H-03', 'fail', __( 'The database update failed.', 'course-captain' ), $hint, $ids );
			case 'pending':
				return self::check( 'H-02', 'warn', __( 'A database update is due.', 'course-captain' ), $hint );
			case 'running':
				return self::check( 'H-02', 'info', __( 'The database update is running.', 'course-captain' ), '' );
			case 'backfill':
				return self::check( 'H-02', 'info', __( 'Existing data is being updated in the background.', 'course-captain' ), '' );
		}
		return self::check( 'H-02', 'ok', __( 'The database schema is up to date.', 'course-captain' ), '' );
	}

	/**
	 * Map a Site Health result.
	 *
	 * @param string               $id     Check ID.
	 * @param array<string, mixed> $result Site Health result.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function from_site_health( string $id, array $result ): array {
		$status = match ( (string) $result['status'] ) {
			'critical'    => 'fail',
			'recommended' => 'warn',
			default       => 'ok',
		};
		return self::check( $id, $status, (string) $result['label'], wp_strip_all_tags( (string) $result['description'] ) );
	}

	/**
	 * H-25 outbound mode.
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function outbound_check(): array {
		$mode = OutboundMode::current();
		/* translators: %s: outbound mode on, catch or off */
		$label = sprintf( __( 'Outbound mode: %s', 'course-captain' ), $mode['mode'] );
		$warn  = OutboundMode::OFF === $mode['mode'] && 'production' === wp_get_environment_type();
		return self::check( 'H-25', $warn ? 'warn' : 'info', $label, $warn ? __( 'Mails and deliveries are switched off on a production site.', 'course-captain' ) : '' );
	}

	/**
	 * H-26 hold mode.
	 *
	 * @param int $held Held notifications.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function hold_check( int $held ): array {
		if ( OutboundMode::is_held() ) {
			/* translators: %d: number of held messages */
			return self::check( 'H-26', 'fail', sprintf( __( 'Hold mode is active; %d messages are held.', 'course-captain' ), $held ), __( 'Check the site identity, then release or discard the held messages.', 'course-captain' ) );
		}
		if ( $held > 0 ) {
			/* translators: %d: number of held messages */
			return self::check( 'H-26', 'warn', sprintf( __( '%d messages are still held.', 'course-captain' ), $held ), __( 'Release or discard the held messages.', 'course-captain' ) );
		}
		return self::check( 'H-26', 'ok', __( 'No messages are held.', 'course-captain' ), '' );
	}

	/**
	 * H-27 jobs: internal deliveries by state with IDs.
	 *
	 * @param Database           $db     Database.
	 * @param array<string, int> $counts Delivery counts.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function jobs_check( Database $db, array $counts ): array {
		$table = $db->table( 'ccap_outbox_delivery' );
		if ( $counts['dead'] > 0 ) {
			$ids = self::ids( $db, $db->prepare( "SELECT CONCAT('delivery:', id, ' ', COALESCE(last_error_class, '')) FROM `{$table}` WHERE status = 'dead' ORDER BY id LIMIT %d", self::MAX_IDS ) );
			/* translators: %d: number of deliveries */
			return self::check( 'H-27', 'fail', sprintf( _n( '%d internal delivery failed permanently (dead letter).', '%d internal deliveries failed permanently (dead letters).', $counts['dead'], 'course-captain' ), $counts['dead'] ), __( 'Fix the cause (usually mail or storage), then retry or discard the dead letters.', 'course-captain' ), $ids );
		}
		$stale = self::ids( $db, $db->prepare( "SELECT CONCAT('delivery:', id) FROM `{$table}` WHERE status = 'dispatching' AND locked_until < %s ORDER BY id LIMIT %d", $db->now(), self::MAX_IDS ) );
		if ( array() !== $stale ) {
			return self::check( 'H-27', 'warn', __( 'Deliveries with an expired lease wait for the next dispatch run.', 'course-captain' ), __( 'The next run takes them over automatically; check that WP-Cron runs.', 'course-captain' ), $stale );
		}
		/* translators: %d: number of waiting deliveries */
		return self::check( 'H-27', 'ok', sprintf( __( 'No dead letters; %d deliveries waiting.', 'course-captain' ), $counts['pending'] + $counts['failed'] ), '' );
	}

	/**
	 * H-28 outbox backlog (oldest open delivery > 1 h).
	 *
	 * @param Database $db Database.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function backlog_check( Database $db ): array {
		$oldest = $db->scalar( 'SELECT MIN(created_at) FROM `' . $db->table( 'ccap_outbox_delivery' ) . "` WHERE status IN ('pending', 'dispatching')" );
		if ( null !== $oldest && strtotime( $oldest . ' UTC' ) < $db->time() - self::BACKLOG_STALE ) {
			/* translators: %s: UTC time */
			return self::check( 'H-28', 'warn', sprintf( __( 'The oldest open delivery is from %s (UTC).', 'course-captain' ), $oldest ), __( 'Check that WP-Cron or a server cron job runs regularly.', 'course-captain' ) );
		}
		return self::check( 'H-28', 'ok', __( 'No delivery backlog.', 'course-captain' ), '' );
	}

	/**
	 * H-29 failed grants and pending grants past the timeout.
	 *
	 * @param Database $db Database.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function grants_check( Database $db ): array {
		$table   = $db->table( 'ccap_grant' );
		$failed  = (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'failed'" );
		$pending = (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE status LIKE %s AND created_at < %s", 'pending%', $db->now( -self::PENDING_GRANT_H * HOUR_IN_SECONDS ) ) );
		if ( 0 === $failed && 0 === $pending ) {
			return self::check( 'H-29', 'ok', __( 'No failed or stuck access grants.', 'course-captain' ), '' );
		}
		$ids = self::ids( $db, $db->prepare( "SELECT CONCAT('grant:', uid) FROM `{$table}` WHERE status = 'failed' OR (status LIKE %s AND created_at < %s) ORDER BY id LIMIT %d", 'pending%', $db->now( -self::PENDING_GRANT_H * HOUR_IN_SECONDS ), self::MAX_IDS ) );
		/* translators: 1: failed grants, 2: pending grants */
		return self::check( 'H-29', 'warn', sprintf( __( 'Access grants: %1$d failed, %2$d pending for more than 24 hours.', 'course-captain' ), $failed, $pending ), __( 'Clarify the source, then process the grant again; avoid manual duplicate grants.', 'course-captain' ), $ids );
	}

	/**
	 * H-30 broken exam attempts.
	 *
	 * @param Database $db Database.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function broken_attempts_check( Database $db ): array {
		$table  = $db->table( 'ccap_exam_session' );
		$broken = (int) $db->scalar( "SELECT COUNT(*) FROM `{$table}` WHERE status = 'broken'" );
		if ( 0 === $broken ) {
			return self::check( 'H-30', 'ok', __( 'No broken exam attempts.', 'course-captain' ), '' );
		}
		$ids = self::ids( $db, $db->prepare( "SELECT CONCAT('attempt:', uid) FROM `{$table}` WHERE status = 'broken' ORDER BY id LIMIT %d", self::MAX_IDS ) );
		/* translators: %d: number of attempts */
		return self::check( 'H-30', 'warn', sprintf( _n( '%d exam attempt is broken.', '%d exam attempts are broken.', $broken, 'course-captain' ), $broken ), __( 'The exam snapshot is damaged; contact the technical operator.', 'course-captain' ), $ids );
	}

	/**
	 * H-31 imports without heartbeat.
	 *
	 * @param Database $db Database.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function imports_check( Database $db ): array {
		$ids = self::ids( $db, $db->prepare( "SELECT CONCAT('import:', id) FROM `" . $db->table( 'ccap_import_batch' ) . "` WHERE status IN ('importing', 'activating') AND (heartbeat_at IS NULL OR heartbeat_at < %s) ORDER BY id LIMIT %d", $db->now( -self::HEARTBEAT_STALE ), self::MAX_IDS ) );
		if ( array() === $ids ) {
			return self::check( 'H-31', 'ok', __( 'No stalled imports.', 'course-captain' ), '' );
		}
		return self::check( 'H-31', 'warn', __( 'Imports without a heartbeat for more than 15 minutes.', 'course-captain' ), __( 'Resume or discard the import in the import screen.', 'course-captain' ), $ids );
	}

	/**
	 * H-33 secret key constant (never the value).
	 *
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function secret_check(): array {
		if ( defined( 'COURSE_CAPTAIN_SECRET_KEY' ) ) {
			return self::check( 'H-33', 'ok', __( 'COURSE_CAPTAIN_SECRET_KEY is set (from constant).', 'course-captain' ), '' );
		}
		return self::check( 'H-33', 'warn', __( 'COURSE_CAPTAIN_SECRET_KEY is not set; keys are derived from the WordPress salts.', 'course-captain' ), __( 'Set the constant in wp-config.php so a salt change does not invalidate tokens.', 'course-captain' ) );
	}

	/**
	 * H-34 integrations summary (read-only partner detection).
	 *
	 * @param list<array<string, mixed>> $records Integration records.
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function integrations_check( array $records ): array {
		$detected = array_values(
			array_map(
				static fn( array $r ): string => (string) $r['provider_id'] . ' ' . (string) $r['state'],
				array_filter( $records, static fn( array $r ): bool => IntegrationStatus::NOT_INSTALLED !== $r['state'] )
			)
		);
		/* translators: %d: number of detected plugins */
		return self::check( 'H-34', 'info', sprintf( __( '%d partner plugins detected; no data exchange in the free edition.', 'course-captain' ), count( $detected ) ), '', $detected );
	}

	/**
	 * First column of a query as a string list.
	 *
	 * @param Database $db  Database.
	 * @param string   $sql Prepared SQL.
	 * @return list<string>
	 */
	private static function ids( Database $db, string $sql ): array {
		try {
			return array_values( array_map( static fn( array $row ): string => trim( (string) reset( $row ) ), $db->rows( $sql ) ) );
		} catch ( Throwable $e ) {
			unset( $e );
			return array();
		}
	}

	/**
	 * Check record.
	 *
	 * @param string $id     ID.
	 * @param string $status ok|warn|fail|info.
	 * @param string $label  Text.
	 * @param string $hint   Hint.
	 * @param array  $ids    Object IDs.
	 * @phpstan-param list<string> $ids
	 * @return array{id: string, status: string, label: string, hint: string, ids: list<string>}
	 */
	private static function check( string $id, string $status, string $label, string $hint, array $ids = array() ): array {
		return array(
			'id'     => $id,
			'status' => $status,
			'label'  => $label,
			'hint'   => $hint,
			'ids'    => $ids,
		);
	}
}
