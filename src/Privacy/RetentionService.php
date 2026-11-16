<?php
/**
 * Retention jobs (R0-31 §2.1, §2.3 RR-1…RR-8; R0-05 GM-099, GM-131, GM-161).
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
 * (no IDs, RR-5). Defaults follow RT-xx; the `privacy.retention` sub-keys
 * become editable with R1-34.
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

	/**
	 * Run all steps.
	 *
	 * @param int|null $now Unix time (tests).
	 * @return array<string, int|string> Count per type, or `error:<class>`.
	 */
	public static function run( ?int $now = null ): array {
		$now   = $now ?? time();
		$db    = Database::current();
		$sql   = static fn( int $days ): string => gmdate( 'Y-m-d H:i:s', $now - $days * DAY_IN_SECONDS );
		$steps = array(
			'RT-04_answers'          => static fn(): int => $db->execute( $db->prepare( 'DELETE a, x FROM `' . $db->table( 'ccap_attempt' ) . '` a LEFT JOIN `' . $db->table( 'ccap_attempt_answer' ) . '` x ON x.attempt_id = a.id WHERE a.created_at < %s', $sql( self::ANSWERS_DAYS ) ) ),
			'RT-15_invite_emails'    => static fn(): int => $db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_seat' ) . "` SET invite_email = NULL, claim_token_hash = NULL WHERE invite_email IS NOT NULL AND ( (status = 'expired' AND claim_expires_at < %s) OR (status = 'removed' AND removed_at IS NOT NULL) )", $sql( self::INVITE_EMAIL_DAYS ) ) ),
			'RT-17_removed_seats'    => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_seat' ) . "` WHERE status = 'removed' AND removed_at < %s", $sql( self::REMOVED_SEATS_DAYS ) ) ),
			'RT-22_lifted_locks'     => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_learner_lock' ) . '` WHERE lifted_at IS NOT NULL AND lifted_at < %s', $sql( self::LIFTED_LOCKS_DAYS ) ) ),
			'RT-24_import_unchanged' => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_import_item_log' ) . "` WHERE action = 'unchanged' AND created_at < %s", $sql( self::IMPORT_UNCHANGED_DAYS ) ) ),
			'RT-25_import_log'       => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_import_item_log' ) . '` WHERE created_at < %s', $sql( self::IMPORT_LOG_DAYS ) ) ) + $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_import_issue' ) . '` WHERE created_at < %s', $sql( self::IMPORT_LOG_DAYS ) ) ),
			'RT-26_package_copies'   => static fn(): int => self::package_copies( $db ),
			'RT-30_notifications'    => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_notification' ) . "` WHERE status NOT IN ('queued', 'retry_wait', 'sending', 'held') AND created_at < %s", $sql( self::NOTIFICATION_LOG_DAYS ) ) ),
			'RT-39_idempotency'      => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_request_idempotency' ) . '` WHERE expires_at < %s', gmdate( 'Y-m-d H:i:s', $now ) ) ),
			'RT-40_rate_limits'      => static fn(): int => $db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_rate_limit' ) . '` WHERE expires_at < %s', gmdate( 'Y-m-d H:i:s', $now ) ) ),
			'RT-42_settings_undo'    => static fn(): int => self::settings_undo( $now ),
		);
		$out   = array();
		foreach ( $steps as $type => $step ) {
			try {
				$count        = (int) $step();
				$out[ $type ] = $count;
				if ( $count > 0 ) {
					AuditLog::write( $db, Actor::cron(), 'retention_applied', 'retention', $type, null, array( 'count' => $count ) );
				}
			} catch ( \Throwable $e ) {
				$out[ $type ] = 'error:' . get_class( $e );
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
	 * Package copies: active (published) + the 2 newest per source system stay; batches in work stay.
	 *
	 * @param Database $db Database.
	 * @return int Deleted files.
	 */
	private static function package_copies( Database $db ): int {
		$rows    = $db->rows( 'SELECT id, status, manifest, storage_path FROM `' . $db->table( 'ccap_import_batch' ) . "` WHERE storage_path IS NOT NULL AND status IN ('published', 'superseded', 'discarded', 'failed') ORDER BY id DESC" );
		$kept    = array();
		$deleted = 0;
		foreach ( $rows as $row ) {
			$manifest = (array) json_decode( (string) $row['manifest'], true );
			$system   = (string) ( $manifest['source']['system'] ?? '' );
			$keep     = 'published' === $row['status'] || ( 'superseded' === $row['status'] && ( $kept[ $system ] ?? 0 ) < self::KEEP_PACKAGES );
			if ( $keep ) {
				$kept[ $system ] = ( $kept[ $system ] ?? 0 ) + 1;
				continue;
			}
			$path = (string) $row['storage_path'];
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
				++$deleted;
			}
			$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_import_batch' ) . '` SET storage_path = NULL WHERE id = %d', (int) $row['id'] ) );
		}
		return $deleted;
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
