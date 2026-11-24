<?php
/**
 * Write path of the learner lock (E-79, R0-13 K-12, R0-07 §3.6, M-24).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

use CourseCaptain\Cache\CacheStore;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Sets and lifts rows of `ccap_learner_lock`. Both need M-24
 * (`ccap_lock_learners`, never oneself, never someone with more rights),
 * a reason code from R0-13 K-12 and are audited (`learner_locked` /
 * `learner_unlocked`, R0-07 §3.6) in the same transaction as the write.
 * Grants stay unchanged (R0-09 §3.6, GRANT-019); the next request of the
 * learner is denied with `ccap_account_locked` ({@see Relations::is_locked()}).
 * Optionally all sessions of the account end (`sessions_terminated`, E-79).
 * No mail goes to the learner (R0-13 K-12).
 */
final class LearnerLock {

	/** Reason codes (R0-13 K-12). */
	public const REASONS = array( 'abuse', 'account_compromised', 'legal_hold', 'other' );

	/**
	 * Constructor.
	 *
	 * @param Database      $db     Database access.
	 * @param PolicyService $policy Policy.
	 */
	public function __construct( private Database $db, private PolicyService $policy ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		return new self( Database::current(), PolicyService::instance() );
	}

	/**
	 * Active lock of a user: not lifted, started, not expired.
	 *
	 * @param int $user_id User.
	 * @return array<string, mixed>|null
	 */
	public function active( int $user_id ): ?array {
		$table = $this->db->table( 'ccap_learner_lock' );
		$now   = $this->db->now();
		return $this->db->row( $this->db->prepare( "SELECT * FROM `{$table}` WHERE user_id = %d AND lifted_at IS NULL AND valid_from <= %s AND (valid_until IS NULL OR valid_until > %s) ORDER BY id DESC LIMIT 1", $user_id, $now, $now ) );
	}

	/**
	 * Lock a learner.
	 *
	 * @param Actor       $actor       Acting user (M-24).
	 * @param int         $user_id     Learner.
	 * @param string      $reason_code One of {@see self::REASONS}.
	 * @param string      $reason      Free text; required for `other`.
	 * @param string|null $valid_until End of the lock (UTC, `Y-m-d H:i:s`), null = until lifted.
	 * @param bool        $end_sessions End all sessions of the account.
	 * @return array{lock_id: int, audit_id: int, sessions_terminated: bool}
	 * @throws CourseException `ccap_not_found`, `ccap_forbidden`, `ccap_invalid_param` (422), `ccap_date_invalid` (422), `ccap_conflict` (409, already locked).
	 */
	public function lock( Actor $actor, int $user_id, string $reason_code, string $reason = '', ?string $valid_until = null, bool $end_sessions = false ): array {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::LOCK_LEARNER, array( $user_id ) ), 'Not allowed to lock this learner.' );
		$reason = trim( $reason );
		if ( ! in_array( $reason_code, self::REASONS, true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'reason_code', 422 );
		}
		if ( 'other' === $reason_code && '' === $reason ) {
			throw new CourseException( 'ccap_reason_required', 'reason', 422 );
		}
		if ( null !== $valid_until && ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valid_until ) || $valid_until <= $this->db->now() ) ) {
			throw new CourseException( 'ccap_date_invalid', 'valid_until', 422 );
		}
		$result = $this->db->transaction(
			function ( Database $db ) use ( $actor, $user_id, $reason_code, $reason, $valid_until, $end_sessions ): array {
				$table = $db->table( 'ccap_learner_lock' );
				$now   = $db->now();
				// Row lock on the open locks of the user: two parallel requests do not create two locks.
				$open = $db->scalar( $db->prepare( "SELECT id FROM `{$table}` WHERE user_id = %d AND lifted_at IS NULL AND (valid_until IS NULL OR valid_until > %s) ORDER BY id LIMIT 1 FOR UPDATE", $user_id, $now ) );
				if ( null !== $open ) {
					throw new CourseException( 'ccap_conflict', 'locked', 409 );
				}
				$lock  = $db->insert(
					'ccap_learner_lock',
					array(
						'user_id'     => $user_id,
						'reason_code' => $reason_code,
						'reason'      => '' === $reason ? null : $reason,
						'valid_from'  => $now,
						'valid_until' => $valid_until,
						'set_by'      => $actor->user_id > 0 ? $actor->user_id : null,
					),
					array( 'created_at', 'updated_at' )
				);
				$audit = AuditLog::write(
					$db,
					$actor,
					'learner_locked',
					'learner_lock',
					(string) $lock,
					'' === $reason ? $reason_code : $reason,
					array(
						'reason_code'         => $reason_code,
						'valid_until'         => $valid_until,
						'sessions_terminated' => $end_sessions,
					),
					$user_id
				);
				if ( $end_sessions ) {
					AuditLog::write( $db, $actor, 'sessions_terminated', 'user', (string) $user_id, $reason_code, array( 'lock_id' => $lock ), $user_id );
				}
				return array(
					'lock_id'             => $lock,
					'audit_id'            => $audit,
					'sessions_terminated' => $end_sessions,
				);
			}
		);
		if ( $end_sessions && class_exists( '\WP_Session_Tokens' ) ) {
			\WP_Session_Tokens::get_instance( $user_id )->destroy_all();
		}
		CacheStore::bump_user( $user_id );
		do_action( 'course_captain_policy_changed' );
		return $result;
	}

	/**
	 * Lift the active lock of a learner.
	 *
	 * @param Actor  $actor   Acting user (M-24).
	 * @param int    $user_id Learner.
	 * @param string $reason  Justification (required).
	 * @return array{lock_id: int, audit_id: int}
	 * @throws CourseException `ccap_not_found` (also: no active lock), `ccap_forbidden`, `ccap_reason_required` (422), `ccap_conflict` (409, lifted in the meantime).
	 */
	public function lift( Actor $actor, int $user_id, string $reason ): array {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::LOCK_LEARNER, array( $user_id ) ), 'Not allowed to unlock this learner.' );
		$reason = trim( $reason );
		if ( '' === $reason ) {
			throw new CourseException( 'ccap_reason_required', 'reason', 422 );
		}
		$active = $this->active( $user_id );
		if ( null === $active ) {
			throw new CourseException( 'ccap_not_found', 'lock', 404 );
		}
		$lock   = (int) $active['id'];
		$result = $this->db->transaction(
			function ( Database $db ) use ( $actor, $user_id, $reason, $lock, $active ): array {
				$table   = $db->table( 'ccap_learner_lock' );
				$now     = $db->now();
				$changed = $db->execute( $db->prepare( "UPDATE `{$table}` SET lifted_at = %s, lifted_by = %d, updated_at = %s WHERE id = %d AND lifted_at IS NULL", $now, $actor->user_id, $now, $lock ) );
				if ( 1 !== $changed ) {
					throw new CourseException( 'ccap_conflict', 'lifted', 409 );
				}
				$audit = AuditLog::write(
					$db,
					$actor,
					'learner_unlocked',
					'learner_lock',
					(string) $lock,
					$reason,
					array(
						'reason_code' => (string) $active['reason_code'],
						'locked_at'   => (string) $active['valid_from'],
					),
					$user_id
				);
				return array(
					'lock_id'  => $lock,
					'audit_id' => $audit,
				);
			}
		);
		CacheStore::bump_user( $user_id );
		do_action( 'course_captain_policy_changed' );
		return $result;
	}
}
