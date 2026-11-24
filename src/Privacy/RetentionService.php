<?php
/**
 * Retention jobs (R0-31 §2.1, §2.3 RR-1…RR-8; R0-05 GM-099, GM-131, GM-161;
 * SET-CC-02 periods from `privacy.retention`).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Privacy;

use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Each data type is an isolated step (one failure never stops the others);
 * each step with effect writes `retention_applied` with type and count
 * (no IDs, RR-5). The periods come from `privacy.retention`
 * ({@see RetentionPolicy}: setting, then the filter
 * `course_captain_retention_days`); the constants below are the former
 * fixed values and stay the defaults.
 *
 * {@see self::preview()} counts, read-only, what the next run would delete
 * per sub-key (rows, files or people), also for candidate values before
 * saving (RC-23). Steps whose job runs elsewhere in the hourly run
 * (outbox purge, held mails, log files) are counted here and executed there
 * with the same period.
 */
final class RetentionService {

	public const ANSWERS_DAYS          = 730;
	public const INVITE_EMAIL_DAYS     = 30;
	public const REMOVED_SEATS_DAYS    = 365;
	public const LIFTED_LOCKS_DAYS     = 30;
	public const IMPORT_UNCHANGED_DAYS = 14;
	public const IMPORT_LOG_DAYS       = 180;
	public const KEEP_PACKAGES         = 2;
	public const NOTIFICATION_LOG_DAYS = 90;
	public const CERTIFICATES_DAYS     = 3653;
	public const AUDIT_YEARS           = 4;
	public const AUDIT_BATCH           = 5000;

	/** Rows per run of the daily-type steps (R0-27 §1.4 "≤ 1 000 je Lauf"). */
	public const BATCH = 1000;

	/** Import batches in work whose package copy expires after `import_package_days` (R0-10 §3.6). */
	public const WORK_STATUSES = array( 'uploaded', 'validated', 'previewed', 'approved', 'aborted' );

	/** Grant states that keep a group or learning data alive. */
	private const LIVE_GRANTS = "'active', 'suspended', 'pending_claim', 'pending_confirmation'";

	/**
	 * Run all steps.
	 *
	 * @param int|null $now Unix time (tests).
	 * @return array<string, int|string> Count per type, or `error:<class>`.
	 */
	public static function run( ?int $now = null ): array {
		$now = $now ?? time();
		$db  = Database::current();
		$out = array();
		foreach ( self::steps( $db, RetentionPolicy::effective(), $now ) as $type => $step ) {
			if ( null === $step['run'] ) {
				continue;
			}
			try {
				$count        = (int) ( $step['run'] )();
				$out[ $type ] = $count;
				if ( $count > 0 ) {
					AuditLog::write( $db, Actor::cron(), 'retention_applied', 'retention', $type, null, array( 'count' => $count ) );
				}
			} catch ( \Throwable $e ) {
				$out[ $type ] = 'error:' . get_class( $e );
				\CourseCaptain\Ops\Log::write(
					'error',
					'retention.step_failed',
					array(
						'step'  => $type,
						'error' => get_class( $e ),
					)
				);
			}
		}
		update_option(
			'course_captain_retention_state',
			array(
				'last_run' => gmdate( 'c', $now ),
				'result'   => $out,
			),
			false
		);
		return $out;
	}

	/**
	 * Read-only preview: per sub-key what the next run would delete.
	 *
	 * @param array<string, mixed>|null $values Candidate values of `privacy.retention` (null = stored setting).
	 * @param int|null                  $now    Unix time (tests).
	 * @return array<string, array{value: int|string|null, unit: string, kind: string, count: int|null, error: bool}>
	 */
	public static function preview( ?array $values = null, ?int $now = null ): array {
		$now    = $now ?? time();
		$policy = RetentionPolicy::effective( $values );
		$steps  = self::steps( Database::current(), $policy, $now );
		$out    = array();
		foreach ( RetentionPolicy::KEYS as $key => $def ) {
			$entry = array(
				'value' => $policy[ $key ],
				'unit'  => $def['unit'],
				'kind'  => 'rows',
				'count' => null,
				'error' => false,
			);
			if ( 'fixed' !== $def['unit'] && null !== $policy[ $key ] ) {
				$entry['count'] = 0;
				foreach ( $def['steps'] as $type ) {
					$step          = $steps[ $type ];
					$entry['kind'] = $step['kind'];
					if ( null === $step['count'] ) {
						continue;
					}
					try {
						$entry['count'] += (int) ( $step['count'] )();
					} catch ( \Throwable $e ) {
						unset( $e );
						$entry['error'] = true;
					}
				}
			}
			$out[ $key ] = $entry;
		}
		return $out;
	}

	/**
	 * Step table: key (sub-key of `privacy.retention` or null), kind of the
	 * count, count closure (read-only) and run closure (null = runs in
	 * another hourly step with the same period).
	 *
	 * @param Database                       $db  Database.
	 * @param array<string, int|string|null> $p   Effective periods.
	 * @param int                            $now Unix time.
	 * @return array<string, array{key: string|null, kind: string, count: (callable(): int)|null, run: (callable(): int)|null}>
	 */
	private static function steps( Database $db, array $p, int $now ): array {
		$cut     = static fn( string $key ): string => (string) RetentionPolicy::cutoff( $key, $p[ $key ], $now );
		$t       = static fn( string $name ): string => '`' . $db->table( $name ) . '`';
		$count   = static fn( string $sql ): int => (int) $db->scalar( $sql );
		$answers = $cut( 'raw_answers_years_after_course_end' );
		$steps   = array(
			'RT-04_answers'               => array(
				'key'   => 'raw_answers_years_after_course_end',
				'kind'  => 'rows',
				'count' => static fn(): int => $count( $db->prepare( 'SELECT COUNT(*) FROM ' . $t( 'ccap_attempt' ) . ' WHERE created_at < %s', $answers ) ),
				'run'   => static fn(): int => $db->execute( $db->prepare( 'DELETE a, x FROM ' . $t( 'ccap_attempt' ) . ' a LEFT JOIN ' . $t( 'ccap_attempt_answer' ) . ' x ON x.attempt_id = a.id WHERE a.created_at < %s', $answers ) ),
			),
			'RT-15_invite_emails'         => array(
				'key'   => 'invite_email_days_after_expiry',
				'kind'  => 'rows',
				'count' => static fn(): int => $count( $db->prepare( 'SELECT COUNT(*) FROM ' . $t( 'ccap_seat' ) . " WHERE invite_email IS NOT NULL AND ( (status = 'expired' AND claim_expires_at < %s) OR (status = 'removed' AND removed_at IS NOT NULL) )", $cut( 'invite_email_days_after_expiry' ) ) ),
				'run'   => static fn(): int => $db->execute( $db->prepare( 'UPDATE ' . $t( 'ccap_seat' ) . " SET invite_email = NULL, claim_token_hash = NULL WHERE invite_email IS NOT NULL AND ( (status = 'expired' AND claim_expires_at < %s) OR (status = 'removed' AND removed_at IS NOT NULL) )", $cut( 'invite_email_days_after_expiry' ) ) ),
			),
			'RT-17_removed_seats'         => array(
				'key'   => 'removed_seats_days',
				'kind'  => 'rows',
				'count' => static fn(): int => $count( $db->prepare( 'SELECT COUNT(*) FROM ' . $t( 'ccap_seat' ) . " WHERE status = 'removed' AND removed_at < %s", $cut( 'removed_seats_days' ) ) ),
				'run'   => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM ' . $t( 'ccap_seat' ) . " WHERE status = 'removed' AND removed_at < %s", $cut( 'removed_seats_days' ) ) ),
			),
			'RT-22_lifted_locks'          => array(
				'key'   => 'lifted_locks_days',
				'kind'  => 'rows',
				'count' => static fn(): int => $count( $db->prepare( 'SELECT COUNT(*) FROM ' . $t( 'ccap_learner_lock' ) . ' WHERE lifted_at IS NOT NULL AND lifted_at < %s', $cut( 'lifted_locks_days' ) ) ),
				'run'   => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM ' . $t( 'ccap_learner_lock' ) . ' WHERE lifted_at IS NOT NULL AND lifted_at < %s', $cut( 'lifted_locks_days' ) ) ),
			),
			'RT-24_import_unchanged'      => array(
				'key'   => 'import_unchanged_days',
				'kind'  => 'rows',
				'count' => static fn(): int => $count( $db->prepare( 'SELECT COUNT(*) FROM ' . $t( 'ccap_import_item_log' ) . " WHERE action = 'unchanged' AND created_at < %s", $cut( 'import_unchanged_days' ) ) ),
				'run'   => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM ' . $t( 'ccap_import_item_log' ) . " WHERE action = 'unchanged' AND created_at < %s", $cut( 'import_unchanged_days' ) ) ),
			),
			'RT-25_import_log'            => array(
				'key'   => 'import_log_days',
				'kind'  => 'rows',
				'count' => static fn(): int => $count( $db->prepare( 'SELECT COUNT(*) FROM ' . $t( 'ccap_import_item_log' ) . ' WHERE created_at < %s', $cut( 'import_log_days' ) ) ) + $count( $db->prepare( 'SELECT COUNT(*) FROM ' . $t( 'ccap_import_issue' ) . ' WHERE created_at < %s', $cut( 'import_log_days' ) ) ),
				'run'   => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM ' . $t( 'ccap_import_item_log' ) . ' WHERE created_at < %s', $cut( 'import_log_days' ) ) ) + $db->execute( $db->prepare( 'DELETE FROM ' . $t( 'ccap_import_issue' ) . ' WHERE created_at < %s', $cut( 'import_log_days' ) ) ),
			),
			'RT-26_package_copies'        => array(
				'key'   => 'import_keep_packages',
				'kind'  => 'files',
				'count' => static fn(): int => self::package_copies( $db, (int) $p['import_keep_packages'], true ),
				'run'   => static fn(): int => self::package_copies( $db, (int) $p['import_keep_packages'], false ),
			),
			'RT-26_package_work'          => array(
				'key'   => 'import_package_days',
				'kind'  => 'files',
				'count' => static fn(): int => self::package_work( $db, $cut( 'import_package_days' ), true ),
				'run'   => static fn(): int => self::package_work( $db, $cut( 'import_package_days' ), false ),
			),
			'RT-27_inbox'                 => array(
				'key'   => 'inbox_days',
				'kind'  => 'rows',
				'count' => static fn(): int => $count( $db->prepare( 'SELECT COUNT(*) FROM ' . $t( 'ccap_inbox_event' ) . ' WHERE processed_at IS NOT NULL AND received_at < %s', $cut( 'inbox_days' ) ) ),
				'run'   => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM ' . $t( 'ccap_inbox_event' ) . ' WHERE processed_at IS NOT NULL AND received_at < %s', $cut( 'inbox_days' ) ) ),
			),
			'RT-28_outbox_delivered'      => array(
				'key'   => 'inbox_days',
				'kind'  => 'rows',
				'count' => static fn(): int => \CourseCaptain\Event\OutboxDispatcher::current()->due( (int) $p['outbox_event_days'], (int) $p['inbox_days'] )['purged'],
				'run'   => null,
			),
			'RT-29_outbox_open'           => array(
				'key'   => 'outbox_event_days',
				'kind'  => 'rows',
				'count' => static fn(): int => \CourseCaptain\Event\OutboxDispatcher::current()->due( (int) $p['outbox_event_days'], (int) $p['inbox_days'] )['dead'],
				'run'   => null,
			),
			'RT-30_notifications'         => array(
				'key'   => 'notification_log_days',
				'kind'  => 'rows',
				'count' => static fn(): int => $count( $db->prepare( 'SELECT COUNT(*) FROM ' . $t( 'ccap_notification' ) . " WHERE status NOT IN ('queued', 'retry_wait', 'sending', 'held') AND created_at < %s", $cut( 'notification_log_days' ) ) ),
				'run'   => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM ' . $t( 'ccap_notification' ) . " WHERE status NOT IN ('queued', 'retry_wait', 'sending', 'held') AND created_at < %s", $cut( 'notification_log_days' ) ) ),
			),
			'RT-31_held_mails'            => array(
				'key'   => 'held_mail_days',
				'kind'  => 'rows',
				'count' => static fn(): int => \CourseCaptain\Notification\NotificationService::expire_held( $db, (int) $p['held_mail_days'], true ),
				'run'   => null,
			),
			'RT-33_error_logs'            => array(
				'key'   => 'error_log_days',
				'kind'  => 'files',
				'count' => static fn(): int => \CourseCaptain\Ops\Log::prune( $now, array( 'error' => (int) $p['error_log_days'] ), true ),
				'run'   => null,
			),
			'RT-34_debug_logs'            => array(
				'key'   => 'debug_file_days',
				'kind'  => 'files',
				'count' => static fn(): int => \CourseCaptain\Ops\Log::prune( $now, array( 'debug' => (int) $p['debug_file_days'] ), true ),
				'run'   => null,
			),
			'RT-09_certificates'          => array(
				'key'   => 'certificates_years',
				'kind'  => 'rows',
				'count' => static fn(): int => \CourseCaptain\Certificate\CertificateService::expire_retention( $db, $cut( 'certificates_years' ), true ),
				'run'   => static fn(): int => \CourseCaptain\Certificate\CertificateService::expire_retention( $db, $cut( 'certificates_years' ) ),
			),
			'RT-45_assignments'           => array(
				'key'   => 'raw_answers_years_after_course_end',
				'kind'  => 'rows',
				'count' => static fn(): int => \CourseCaptain\Assignment\AssignmentService::expire_retention( $db, $answers, true ),
				'run'   => static fn(): int => \CourseCaptain\Assignment\AssignmentService::expire_retention( $db, $answers ),
			),
			'RT-06_exam_attempts'         => self::batch_step(
				$db,
				'exam_attempts_years',
				'FROM ' . $t( 'ccap_exam_session' ) . " s WHERE s.status <> 'in_progress' AND COALESCE(s.evaluated_at, s.submitted_at, s.started_at) < %s AND NOT EXISTS (SELECT 1 FROM " . $t( 'ccap_certificate' ) . " c WHERE c.evidence_type = 'exam_session' AND c.evidence_id = s.id)",
				$cut( 'exam_attempts_years' ),
				's.id',
				array( array( 'ccap_exam_session_question', 'session_id' ), array( 'ccap_exam_session', 'id' ) )
			),
			'RT-12_ended_grants'          => self::batch_step(
				$db,
				'ended_grants_years',
				'FROM ' . $t( 'ccap_grant' ) . " g WHERE g.user_id IS NOT NULL AND g.status IN ('expired', 'revoked', 'failed') AND g.updated_at < %s",
				$cut( 'ended_grants_years' ),
				'g.id',
				array( array( 'ccap_grant_event', 'grant_id' ), array( 'ccap_grant', 'id' ) )
			),
			'RT-13_grant_history_deleted' => self::batch_step(
				$db,
				'grant_history_deleted_accounts_years',
				'FROM ' . $t( 'ccap_grant' ) . ' g WHERE g.user_id IS NULL AND g.subject_ref IS NOT NULL AND g.updated_at < %s',
				$cut( 'grant_history_deleted_accounts_years' ),
				'g.id',
				array( array( 'ccap_grant_event', 'grant_id' ), array( 'ccap_grant', 'id' ) )
			),
			'RT-18_closed_groups'         => self::batch_step(
				$db,
				'closed_groups_years',
				'FROM ' . $t( 'ccap_group' ) . " grp WHERE ( (grp.status = 'revoked' AND grp.updated_at < %s) OR (grp.valid_until IS NOT NULL AND grp.valid_until < %s) ) AND NOT EXISTS (SELECT 1 FROM " . $t( 'ccap_seat' ) . ' s JOIN ' . $t( 'ccap_grant' ) . ' g ON g.seat_id = s.id WHERE s.group_id = grp.id AND g.status IN (' . self::LIVE_GRANTS . '))',
				$cut( 'closed_groups_years' ),
				'grp.id',
				array( array( 'ccap_seat', 'group_id' ), array( 'ccap_group_course', 'group_id' ), array( 'ccap_group', 'id' ) )
			),
			'RT-02_inactive_learning'     => self::batch_step(
				$db,
				'inactive_learning_data_years',
				'FROM ' . $t( 'ccap_course_progress' ) . ' p WHERE NOT EXISTS (SELECT 1 FROM ' . $t( 'ccap_grant' ) . ' g WHERE g.user_id = p.user_id AND g.status IN (' . self::LIVE_GRANTS . ')) AND NOT EXISTS (SELECT 1 FROM ' . $t( 'ccap_activity_progress' ) . ' a WHERE a.user_id = p.user_id AND a.last_access_at >= %s) AND NOT EXISTS (SELECT 1 FROM ' . $t( 'ccap_question_progress' ) . ' q WHERE q.user_id = p.user_id AND q.last_answered_at >= %s) GROUP BY p.user_id HAVING MAX(COALESCE(p.last_activity_at, p.updated_at)) < %s',
				null === $p['inactive_learning_data_years'] ? '' : $cut( 'inactive_learning_data_years' ),
				'p.user_id',
				array( array( 'ccap_question_progress', 'user_id' ), array( 'ccap_activity_progress', 'user_id' ), array( 'ccap_course_progress', 'user_id' ) ),
				'people'
			),
			'RT-39_idempotency'           => array(
				'key'   => null,
				'kind'  => 'rows',
				'count' => null,
				'run'   => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM ' . $t( 'ccap_request_idempotency' ) . ' WHERE expires_at < %s', gmdate( 'Y-m-d H:i:s', $now ) ) ),
			),
			'RT-40_rate_limits'           => array(
				'key'   => null,
				'kind'  => 'rows',
				'count' => null,
				'run'   => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM ' . $t( 'ccap_rate_limit' ) . ' WHERE expires_at < %s', gmdate( 'Y-m-d H:i:s', $now ) ) ),
			),
			'RT-42_settings_undo'         => array(
				'key'   => null,
				'kind'  => 'rows',
				'count' => null,
				'run'   => static fn(): int => self::settings_undo( $now ),
			),
			'RT-23_audit'                 => array(
				'key'   => 'audit_log_years',
				'kind'  => 'rows',
				'count' => static fn(): int => self::prune_audit( $db, $now, (int) $p['audit_log_years'], true ),
				'run'   => static fn(): int => self::prune_audit( $db, $now, (int) $p['audit_log_years'] ),
			),
			'RT-36_data_exports'          => array(
				'key'   => null,
				'kind'  => 'files',
				'count' => null,
				'run'   => static fn(): int => DataExport::purge_files( $now ),
			),
		);
		if ( null === $p['inactive_learning_data_years'] ) {
			// RT-02 is off by default (E-154): nothing runs and nothing is counted.
			$steps['RT-02_inactive_learning']['run']   = null;
			$steps['RT-02_inactive_learning']['count'] = static fn(): int => 0;
		}
		return $steps;
	}

	/**
	 * A step that selects at most {@see self::BATCH} IDs per run and
	 * deletes the rows of each table by that ID (children first). The
	 * condition has one placeholder per `%s`, all filled with the cutoff.
	 *
	 * @param Database                          $db     Database.
	 * @param string                            $key    Sub-key.
	 * @param string                            $from   `FROM … WHERE …` (may end in GROUP BY/HAVING).
	 * @param string                            $cutoff Cutoff.
	 * @param string                            $id     Selected ID expression.
	 * @param list<array{0: string, 1: string}> $tables Table and ID column to delete from, in order.
	 * @param string                            $kind   Kind of the count.
	 * @return array{key: string, kind: string, count: callable(): int, run: callable(): int}
	 */
	private static function batch_step( Database $db, string $key, string $from, string $cutoff, string $id, array $tables, string $kind = 'rows' ): array {
		$sql = static fn( string $select ): string => $db->prepare( 'SELECT ' . $select . ' ' . $from, ...array_fill( 0, substr_count( $from, '%s' ), $cutoff ) );
		return array(
			'key'   => $key,
			'kind'  => $kind,
			'count' => static fn(): int => (int) $db->scalar( 'SELECT COUNT(*) FROM (' . $sql( 'DISTINCT ' . $id . ' AS id' ) . ') t' ),
			'run'   => static function () use ( $db, $sql, $id, $tables ): int {
				$ids = array_map( 'intval', array_column( $db->rows( $sql( 'DISTINCT ' . $id . ' AS id' ) . ' ORDER BY id LIMIT ' . self::BATCH ), 'id' ) );
				if ( array() === $ids ) {
					return 0;
				}
				$in    = implode( ',', $ids );
				$count = 0;
				foreach ( $tables as $i => $table ) {
					$n = $db->execute( 'DELETE FROM `' . $db->table( $table[0] ) . '` WHERE `' . $table[1] . "` IN ({$in})" );
					// The count is the number of selected objects (last table); children are deleted with them.
					if ( count( $tables ) - 1 === $i ) {
						$count = 'p.user_id' === $id ? count( $ids ) : $n;
					}
				}
				return $count;
			},
		);
	}

	/**
	 * RT-23 / R0-13 §4.4: audit rows older than the retention period, at most
	 * {@see self::AUDIT_BATCH} per run (oldest first). The run writes one
	 * summary entry `audit_pruned` (count, oldest and newest date, hash of the
	 * last removed row as anchor of the remaining chain).
	 *
	 * @param Database $db    Database.
	 * @param int      $now   Time.
	 * @param int|null $years Period (null = effective `audit_log_years`).
	 * @param bool     $dry   Only count everything due (preview).
	 * @return int Deleted (or due) rows.
	 */
	public static function prune_audit( Database $db, int $now, ?int $years = null, bool $dry = false ): int {
		$years  = $years ?? RetentionPolicy::int( 'audit_log_years' );
		$table  = $db->table( 'ccap_audit_log' );
		$cutoff = gmdate( 'Y-m-d H:i:s', (int) strtotime( '-' . $years . ' years', $now ) );
		if ( $dry ) {
			return (int) $db->scalar( $db->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE created_at < %s", $cutoff ) );
		}
		$last = $db->scalar( $db->prepare( "SELECT MAX(id) FROM ( SELECT id FROM `{$table}` WHERE created_at < %s ORDER BY id LIMIT %d ) t", $cutoff, self::AUDIT_BATCH ) );
		if ( null === $last ) {
			return 0;
		}
		$last   = (int) $last;
		$span   = $db->rows( $db->prepare( "SELECT MIN(created_at) AS oldest, MAX(created_at) AS newest FROM `{$table}` WHERE id <= %d AND created_at < %s", $last, $cutoff ) );
		$anchor = (string) $db->scalar( $db->prepare( "SELECT row_hash FROM `{$table}` WHERE id = %d", $last ) );
		$count  = $db->execute( $db->prepare( "DELETE FROM `{$table}` WHERE id <= %d AND created_at < %s ORDER BY id LIMIT %d", $last, $cutoff, self::AUDIT_BATCH ) );
		if ( $count > 0 ) {
			AuditLog::write(
				$db,
				Actor::cron(),
				'audit_pruned',
				'retention',
				'RT-23_audit',
				null,
				array(
					'count'  => $count,
					'oldest' => (string) ( $span[0]['oldest'] ?? '' ),
					'newest' => (string) ( $span[0]['newest'] ?? '' ),
					'anchor' => $anchor,
				)
			);
		}
		return $count;
	}

	/**
	 * Package copies: active (published) + the newest `import_keep_packages`
	 * per source system stay; discarded and failed batches lose their copy;
	 * batches in work are handled by {@see self::package_work()}.
	 *
	 * @param Database $db   Database.
	 * @param int      $keep Copies kept per source system (including the active one).
	 * @param bool     $dry  Only count.
	 * @return int Deleted (or due) files.
	 */
	private static function package_copies( Database $db, int $keep, bool $dry ): int {
		$rows    = $db->rows( 'SELECT id, status, manifest, storage_path FROM `' . $db->table( 'ccap_import_batch' ) . "` WHERE storage_path IS NOT NULL AND status IN ('published', 'superseded', 'discarded', 'failed') ORDER BY id DESC" );
		$kept    = array();
		$deleted = 0;
		foreach ( $rows as $row ) {
			$manifest = (array) json_decode( (string) $row['manifest'], true );
			$system   = (string) ( $manifest['source']['system'] ?? '' );
			if ( 'published' === $row['status'] || ( 'superseded' === $row['status'] && ( $kept[ $system ] ?? 0 ) < $keep ) ) {
				$kept[ $system ] = ( $kept[ $system ] ?? 0 ) + 1;
				continue;
			}
			$deleted += self::drop_copy( $db, $row, $dry );
		}
		return $deleted;
	}

	/**
	 * Copies of imports in work (uploaded … approved, aborted) without any
	 * activity since the cutoff (`import_package_days`, R0-10 §3.6 "Importe
	 * in Arbeit 30 Tage"). Running imports (`importing`, `activating`) are
	 * never touched (GM-131: the running import stays). Resuming such a
	 * batch afterwards needs a new upload (IMP-B1.07).
	 *
	 * @param Database $db     Database.
	 * @param string   $cutoff Cutoff.
	 * @param bool     $dry    Only count.
	 * @return int Deleted (or due) files.
	 */
	private static function package_work( Database $db, string $cutoff, bool $dry ): int {
		$in      = "'" . implode( "', '", self::WORK_STATUSES ) . "'";
		$rows    = $db->rows( $db->prepare( 'SELECT id, storage_path FROM `' . $db->table( 'ccap_import_batch' ) . "` WHERE storage_path IS NOT NULL AND status IN ({$in}) AND GREATEST(created_at, COALESCE(approved_at, created_at), COALESCE(heartbeat_at, created_at)) < %s ORDER BY id", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Constant status list.
		$deleted = 0;
		foreach ( $rows as $row ) {
			$deleted += self::drop_copy( $db, $row, $dry );
		}
		return $deleted;
	}

	/**
	 * Delete one package copy and clear its path.
	 *
	 * @param Database             $db  Database.
	 * @param array<string, mixed> $row Batch row (`id`, `storage_path`).
	 * @param bool                 $dry Only count.
	 * @return int 1 when a file is (or would be) deleted.
	 */
	private static function drop_copy( Database $db, array $row, bool $dry ): int {
		$path = (string) $row['storage_path'];
		$file = is_file( $path );
		if ( $dry ) {
			return $file ? 1 : 0;
		}
		if ( $file ) {
			wp_delete_file( $path );
		}
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_import_batch' ) . '` SET storage_path = NULL WHERE id = %d', (int) $row['id'] ) );
		return $file ? 1 : 0;
	}

	/**
	 * Expired settings undo slots.
	 *
	 * @param int $now Time.
	 * @return int
	 */
	private static function settings_undo( int $now ): int {
		$count = 0;
		foreach ( array_keys( \CourseCaptain\Settings\SettingsSchema::TABS ) as $tab ) {
			$undo = get_option( \CourseCaptain\Settings\Settings::UNDO_PREFIX . $tab );
			if ( is_array( $undo ) && (int) ( $undo['expires_at'] ?? 0 ) < $now ) {
				delete_option( \CourseCaptain\Settings\Settings::UNDO_PREFIX . $tab );
				++$count;
			}
		}
		return $count;
	}
}
