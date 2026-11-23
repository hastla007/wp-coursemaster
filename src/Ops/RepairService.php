<?php
/**
 * Safe repair actions of the operations screen and CLI (R0-13 §3 K-16,
 * K-17 part, §3.2 RC-5 note; R0-34 §2.5, INT-012…INT-017; R0-37 §3.5
 * "Retry now"; R0-21 §3.7 manual cache flush; R0-10 §4.4 "Check again"
 * for the storage self test).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Cache\CacheStore;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Event\OutboxDispatcher;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\MigrationTrigger;
use CourseCaptain\Storage\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Common pattern of every action (R0-13 §3): capability `ccap_run_maintenance`
 * (#23; the CLI acts as system actor), justification where the catalog asks
 * for one (at least 10 characters), work and audit entry in one transaction
 * (an audit failure rolls the action back), idempotent (a repeated call finds
 * nothing left to do; the same `request_key` returns the first result
 * without a second effect or audit entry). Nothing is deleted: dead letters
 * become `skipped/discarded` and stay visible, held mails become `discarded`.
 */
final class RepairService {

	public const CAP              = 'ccap_run_maintenance';
	public const REASON_MIN       = 10;
	public const IDEMPOTENCY_DAYS = 1;
	public const MAX_AUDIT_IDS    = 50;

	/**
	 * Constructor.
	 *
	 * @param Database $db Database.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current() );
	}

	/**
	 * Whether the actor may run repair actions.
	 *
	 * @param Actor $actor Actor.
	 * @return bool
	 */
	public static function can( Actor $actor ): bool {
		return $actor->is_system() || ( $actor->user_id > 0 && user_can( $actor->user_id, self::CAP ) );
	}

	/**
	 * "Retry now" for the schema migration: manual run that ignores the
	 * backoff and tries a poisoned step again (like `wp course-captain migrate`).
	 *
	 * @param Actor         $actor       Actor.
	 * @param Migrator|null $migrator    Migrator (tests); default current site.
	 * @param string        $request_key Form key (dedupe).
	 * @return array{status: string, error: string}
	 * @throws CourseException Without the capability.
	 */
	public function retry_migration( Actor $actor, ?Migrator $migrator = null, string $request_key = '' ): array {
		self::assert_can( $actor );
		$stored = $this->claim( $actor, 'ops_migration_retry', $request_key, '' );
		if ( null !== $stored ) {
			return array(
				'status' => (string) ( $stored['status'] ?? '' ),
				'error'  => (string) ( $stored['error'] ?? '' ),
			);
		}
		$migrator = $migrator ?? Migrator::for_current_site();
		$before   = $migrator->report();
		// DDL commits implicitly: the migration cannot share a transaction with the audit entry.
		$run    = $migrator->run( MigrationTrigger::CONTEXT_CLI );
		$result = array(
			'status' => $run['status'],
			'error'  => self::short( $run['error'] ),
		);
		AuditLog::write(
			$this->db,
			$actor,
			'job_retried',
			'migration',
			'schema',
			null,
			array(
				'job'    => 'migration',
				'before' => $before['state'],
				'steps'  => array_values( array_merge( $before['poison'], $before['failed'] ) ),
				'status' => $run['status'],
			)
		);
		$this->complete( $actor, 'ops_migration_retry', $request_key, $result );
		return $result;
	}

	/**
	 * "Check again" for the storage self test (R0-10 §4.4, R1-18 g3 H-4):
	 * runs the HTTP probes of the private folders and the script block of
	 * the media folder without the cache. The probes are HTTP requests and
	 * cannot share a transaction with the audit entry; the audit follows
	 * the run (like the migration retry).
	 *
	 * @param Actor  $actor       Actor.
	 * @param string $request_key Form key (dedupe).
	 * @return array{state: string, folders: array<string, string>, scripts: string}
	 * @throws CourseException Without the capability.
	 */
	public function recheck_storage( Actor $actor, string $request_key = '' ): array {
		self::assert_can( $actor );
		$stored = $this->claim( $actor, 'ops_storage_recheck', $request_key, '' );
		if ( null !== $stored ) {
			return array(
				'state'   => (string) ( $stored['state'] ?? '' ),
				'folders' => array_map( 'strval', (array) ( $stored['folders'] ?? array() ) ),
				'scripts' => (string) ( $stored['scripts'] ?? '' ),
			);
		}
		$before = StorageSelfTest::cached();
		$run    = StorageSelfTest::run( true );
		$result = array(
			'state'   => $run['state'],
			'folders' => $run['folders'],
			'scripts' => $run['scripts'],
		);
		AuditLog::write(
			$this->db,
			$actor,
			'job_retried',
			'storage',
			'selftest',
			null,
			array(
				'job'     => 'storage_selftest',
				'before'  => null === $before ? 'unchecked' : $before['state'],
				'state'   => $run['state'],
				'folders' => $run['folders'],
				'scripts' => $run['scripts'],
			)
		);
		$this->complete( $actor, 'ops_storage_recheck', $request_key, $result );
		return $result;
	}

	/**
	 * Retry all dead internal deliveries (K-16, INT-012): same event ID,
	 * attempts reset, manual retry counted.
	 *
	 * @param Actor  $actor       Actor.
	 * @param string $request_key Form key (dedupe).
	 * @return array{count: int}
	 * @throws CourseException Without the capability.
	 */
	public function retry_deliveries( Actor $actor, string $request_key = '' ): array {
		self::assert_can( $actor );
		$result = $this->db->transaction(
			function ( Database $db ) use ( $actor, $request_key ): array {
				$stored = $this->claim( $actor, 'ops_outbox_retry', $request_key, '' );
				if ( null !== $stored ) {
					return array( 'count' => (int) ( $stored['count'] ?? 0 ) );
				}
				$table = $db->table( 'ccap_outbox_delivery' );
				$ids   = array_map( 'intval', array_column( $db->rows( "SELECT id FROM `{$table}` WHERE status = 'dead' ORDER BY id FOR UPDATE" ), 'id' ) );
				$count = array() === $ids ? 0 : $db->execute( "UPDATE `{$table}` SET status = 'pending', attempts = 0, next_attempt_at = NULL, manual_retries = manual_retries + 1, locked_until = NULL, lock_owner = NULL WHERE status = 'dead' AND id IN (" . implode( ',', $ids ) . ')' );
				if ( $count > 0 ) {
					AuditLog::write(
						$db,
						$actor,
						'job_retried',
						'outbox_delivery',
						'dead',
						null,
						array(
							'job'   => 'outbox_delivery',
							'count' => $count,
							'ids'   => array_slice( $ids, 0, self::MAX_AUDIT_IDS ),
						)
					);
				}
				$result = array( 'count' => $count );
				$this->complete( $actor, 'ops_outbox_retry', $request_key, $result );
				return $result;
			}
		);
		if ( $result['count'] > 0 ) {
			OutboxDispatcher::schedule_soon();
		}
		return $result;
	}

	/**
	 * Discard all dead internal deliveries (K-16, INT-014): `skipped` with
	 * `skip_reason = discarded`; the event stays in the log.
	 *
	 * @param Actor  $actor       Actor.
	 * @param string $reason      Justification (≥ 10 characters).
	 * @param string $request_key Form key (dedupe).
	 * @return array{count: int}
	 * @throws CourseException Without the capability or justification.
	 */
	public function discard_deliveries( Actor $actor, string $reason, string $request_key = '' ): array {
		self::assert_can( $actor );
		$reason = self::reason( $reason );
		return $this->db->transaction(
			function ( Database $db ) use ( $actor, $reason, $request_key ): array {
				$stored = $this->claim( $actor, 'ops_outbox_discard', $request_key, $reason );
				if ( null !== $stored ) {
					return array( 'count' => (int) ( $stored['count'] ?? 0 ) );
				}
				$table = $db->table( 'ccap_outbox_delivery' );
				$ids   = array_map( 'intval', array_column( $db->rows( "SELECT id FROM `{$table}` WHERE status = 'dead' ORDER BY id FOR UPDATE" ), 'id' ) );
				$count = array() === $ids ? 0 : $db->execute( $db->prepare( "UPDATE `{$table}` SET status = 'skipped', skip_reason = 'discarded', discarded_at = %s, locked_until = NULL, lock_owner = NULL WHERE status = 'dead' AND id IN (" . implode( ',', $ids ) . ')', $db->now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer IDs.
				if ( $count > 0 ) {
					AuditLog::write(
						$db,
						$actor,
						'job_discarded',
						'outbox_delivery',
						'dead',
						$reason,
						array(
							'job'   => 'outbox_delivery',
							'count' => $count,
							'ids'   => array_slice( $ids, 0, self::MAX_AUDIT_IDS ),
						)
					);
				}
				$result = array( 'count' => $count );
				$this->complete( $actor, 'ops_outbox_discard', $request_key, $result );
				return $result;
			}
		);
	}

	/**
	 * Release held notifications (E-88; `held_outbound_released` needs a reason).
	 *
	 * @param Actor  $actor       Actor (also needs `ccap_manage_settings`).
	 * @param string $reason      Justification.
	 * @param string $request_key Form key (dedupe).
	 * @return array{count: int}
	 * @throws CourseException Without the capability or justification.
	 */
	public function release_held( Actor $actor, string $reason, string $request_key = '' ): array {
		return $this->held( $actor, $reason, $request_key, true );
	}

	/**
	 * Discard held notifications (E-88 default action).
	 *
	 * @param Actor  $actor       Actor (also needs `ccap_manage_settings`).
	 * @param string $reason      Justification.
	 * @param string $request_key Form key (dedupe).
	 * @return array{count: int}
	 * @throws CourseException Without the capability or justification.
	 */
	public function discard_held( Actor $actor, string $reason, string $request_key = '' ): array {
		return $this->held( $actor, $reason, $request_key, false );
	}

	/**
	 * Manual cache flush (R0-21 §3.7): content generation; with a user the
	 * user generation, with a course the course generation (+ page purge).
	 * Access decisions are never cached, so a flush is always safe.
	 *
	 * @param Actor $actor  Actor.
	 * @param int   $user   User (0 = none).
	 * @param int   $course Course (0 = none).
	 * @return array{scope: list<string>}
	 * @throws CourseException Without the capability or with an unknown user/course.
	 */
	public function flush_cache( Actor $actor, int $user = 0, int $course = 0 ): array {
		self::assert_can( $actor );
		if ( $user < 0 || ( $user > 0 && false === get_userdata( $user ) ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown user.', 404 );
		}
		if ( $course < 0 || ( $course > 0 && 'ccap_course' !== get_post_type( $course ) ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		$scope = array();
		if ( $user > 0 ) {
			$scope[] = 'user:' . $user;
		}
		if ( $course > 0 ) {
			$scope[] = 'course:' . $course;
		}
		if ( array() === $scope ) {
			$scope[] = 'content';
		}
		$this->db->transaction(
			function ( Database $db ) use ( $actor, $user, $course, $scope ): void {
				AuditLog::write( $db, $actor, 'cache_flushed', 'cache', implode( ',', $scope ), null, array( 'scope' => $scope ), $user > 0 ? $user : null );
				if ( $user > 0 ) {
					CacheStore::bump_user( $user );
				}
				if ( $course > 0 ) {
					CacheStore::bump_course( $course, true );
				}
				if ( 0 === $user && 0 === $course ) {
					CacheStore::bump_content();
				}
			}
		);
		return array( 'scope' => $scope );
	}

	/**
	 * Release or discard held notifications.
	 *
	 * @param Actor  $actor       Actor.
	 * @param string $reason      Justification.
	 * @param string $request_key Form key.
	 * @param bool   $release     Release (true) or discard.
	 * @return array{count: int}
	 * @throws CourseException Without the capability or justification.
	 */
	private function held( Actor $actor, string $reason, string $request_key, bool $release ): array {
		self::assert_can( $actor );
		$reason = self::reason( $reason );
		$scope  = $release ? 'ops_held_release' : 'ops_held_discard';
		return $this->db->transaction(
			function () use ( $actor, $reason, $request_key, $release, $scope ): array {
				$stored = $this->claim( $actor, $scope, $request_key, $reason );
				if ( null !== $stored ) {
					return array( 'count' => (int) ( $stored['count'] ?? 0 ) );
				}
				$service = new NotificationService( $this->db );
				$count   = $release ? $service->release_held( $actor, $reason ) : $service->discard_held( $actor, $reason );
				$result  = array( 'count' => $count );
				$this->complete( $actor, $scope, $request_key, $result );
				return $result;
			}
		);
	}

	/**
	 * Capability check.
	 *
	 * @param Actor $actor Actor.
	 * @return void
	 * @throws CourseException Without `ccap_run_maintenance`.
	 */
	private static function assert_can( Actor $actor ): void {
		if ( ! self::can( $actor ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to run repair actions.', 403 );
		}
	}

	/**
	 * Mandatory justification (R0-13 §3 step 3).
	 *
	 * @param string $reason Raw reason.
	 * @return string
	 * @throws CourseException Shorter than 10 characters.
	 */
	private static function reason( string $reason ): string {
		$reason = trim( $reason );
		if ( mb_strlen( $reason ) < self::REASON_MIN ) {
			throw new CourseException( 'ccap_reason_required', 'A reason of at least 10 characters is required.', 422 );
		}
		return mb_substr( $reason, 0, 500 );
	}

	/**
	 * Register a request key; returns the stored result of a finished
	 * request with the same key, null for a new request (or no key).
	 *
	 * @param Actor  $actor  Actor.
	 * @param string $scope  Action scope.
	 * @param string $key    Request key.
	 * @param string $params Request parameters (hashed).
	 * @return array<string, mixed>|null
	 * @throws CourseException `ccap_idempotency_conflict`, `ccap_request_in_progress`.
	 */
	private function claim( Actor $actor, string $scope, string $key, string $params ): ?array {
		$key = substr( trim( $key ), 0, 36 );
		if ( '' === $key ) {
			return null;
		}
		$hash     = hash( 'sha256', $scope . '|' . $params );
		$table    = $this->db->table( 'ccap_request_idempotency' );
		$inserted = $this->db->execute(
			$this->db->prepare(
				"INSERT IGNORE INTO `{$table}` (user_id, scope, idem_key, request_hash, status_code, created_at, expires_at) VALUES (%d, %s, %s, %s, 0, %s, %s)",
				$actor->user_id,
				$scope,
				$key,
				$hash,
				$this->db->now(),
				$this->db->now( self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS )
			)
		);
		if ( 1 === $inserted ) {
			return null;
		}
		$row = (array) $this->db->row( $this->db->prepare( "SELECT request_hash, status_code, response FROM `{$table}` WHERE user_id = %d AND scope = %s AND idem_key = %s", $actor->user_id, $scope, $key ) );
		if ( ! hash_equals( (string) ( $row['request_hash'] ?? '' ), $hash ) ) {
			throw new CourseException( 'ccap_idempotency_conflict', 'The request key was used for another request.', 409 );
		}
		$response = json_decode( (string) ( $row['response'] ?? '' ), true );
		if ( 200 !== (int) ( $row['status_code'] ?? 0 ) || ! is_array( $response ) ) {
			throw new CourseException( 'ccap_request_in_progress', 'The request is still in progress.', 409 );
		}
		return $response;
	}

	/**
	 * Store the result of a request key.
	 *
	 * @param Actor                $actor  Actor.
	 * @param string               $scope  Scope.
	 * @param string               $key    Request key.
	 * @param array<string, mixed> $result Result.
	 * @return void
	 */
	private function complete( Actor $actor, string $scope, string $key, array $result ): void {
		$key = substr( trim( $key ), 0, 36 );
		if ( '' === $key ) {
			return;
		}
		$this->db->execute(
			$this->db->prepare(
				'UPDATE `' . $this->db->table( 'ccap_request_idempotency' ) . '` SET status_code = 200, response = %s WHERE user_id = %d AND scope = %s AND idem_key = %s',
				(string) wp_json_encode( $result ),
				$actor->user_id,
				$scope,
				$key
			)
		);
	}

	/**
	 * Shortened error text without paths (for screen and audit).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function short( string $text ): string {
		return mb_substr( Diagnostics::scrub( $text ), 0, 300 );
	}
}
