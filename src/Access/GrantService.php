<?php
/**
 * Grants: creation, transitions, extension, chain, expiry (R0-09 §2–§3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Event\Outbox;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\NamedLock;
use CourseCaptain\Storage\StorageException;

defined( 'ABSPATH' ) || exit;

/**
 * Every transition: CAS on `row_version` + `ccap_grant_event` + internal
 * outbox in one transaction (R0-09 §2.4). The unique `grant_source` key is
 * the idempotency key; the same key for another learner is
 * `ccap_idempotency_conflict`.
 */
final class GrantService {

	private const CAS_RETRIES = 3;

	/**
	 * Constructor.
	 *
	 * @param Database      $db        Database access.
	 * @param PolicyService $policy    Policy.
	 * @param Relations     $relations Relations.
	 */
	public function __construct( private Database $db, private PolicyService $policy, private Relations $relations ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, PolicyService::instance(), new Relations( $db ) );
	}

	/**
	 * Manual grant by an admin/manager (M-14), idempotent per `request_key`.
	 *
	 * @param Actor                $actor       Actor.
	 * @param int                  $user_id     Learner.
	 * @param int                  $course_id   Course.
	 * @param string               $request_key UUID of the form submission.
	 * @param string               $reason      Mandatory justification.
	 * @param array<string, mixed> $options     `module_id`, `scopes`, `valid_until` (UTC), `duration_days`, `renewal_mode`, `depends_on_course_id`.
	 * @return int Grant ID.
	 * @throws CourseException On missing rights or invalid input.
	 */
	public function grant_manual( Actor $actor, int $user_id, int $course_id, string $request_key, string $reason, array $options = array() ): int {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::CREATE_GRANT, array( $course_id ) ), 'Not allowed to grant access.' );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_reason_required', 'A reason is required.', 422 );
		}
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/', $request_key ) || false === get_userdata( $user_id ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid request key or user.', 400 );
		}
		$until = isset( $options['valid_until'] ) ? (string) $options['valid_until'] : null;
		if ( null !== $until && $until <= $this->db->now() ) {
			throw new CourseException( 'ccap_invalid_param', 'The end date must be in the future.', 422 );
		}
		$depends = (int) ( $options['depends_on_course_id'] ?? 0 );
		if ( 0 !== $depends ) {
			$this->assert_dependency( $user_id, $course_id, $depends );
		}
		$result = $this->create(
			array(
				'user_id'              => $user_id,
				'course_id'            => $course_id,
				'module_id'            => (int) ( $options['module_id'] ?? 0 ),
				'scopes'               => (string) ( $options['scopes'] ?? 'all' ),
				'source_type'          => 'manual',
				'source_id'            => $request_key,
				'status'               => GrantTransitions::ACTIVE,
				'valid_until'          => $until,
				'duration_days'        => isset( $options['duration_days'] ) ? (int) $options['duration_days'] : null,
				'renewal_mode'         => (string) ( $options['renewal_mode'] ?? 'extend' ),
				'depends_on_course_id' => $depends,
				'granted_by'           => $actor->user_id,
				'reason'               => $reason,
			),
			$actor,
			'manual_grant'
		);
		if ( $result['created'] ) {
			AuditLog::write( $this->db, $actor, 'grant.manual_created', 'grant', (string) $result['grant_id'], $reason, array( 'course_id' => $course_id ), $user_id );
		}
		return $result['grant_id'];
	}

	/**
	 * Grant from a source request (grant rules, R0-05 GM-089; core of the
	 * Pro API `grant_access_v1`, R0-19 §4.1): `extend` with a duration
	 * starts where the running bounded chain of the same target ends,
	 * `parallel` starts now, no duration means permanent. When the learner
	 * already holds the target permanently, nothing is created
	 * (`ccap_access_exists`); without a learner the request is
	 * `ccap_user_required`. The same source key returns the stored grant
	 * (`duplicate`). New grants are audited with learner, source and
	 * reference (GM-090).
	 *
	 * @param array<string, mixed> $request `user_id`, `course_id`, `module_id?`, `scopes?`, `source_type`, `source_reference`, `source_line?`, `duration_days?`, `renewal_mode?` (extend|parallel), `reason?`.
	 * @param Actor                $actor   Connector or system actor.
	 * @return array{grant_id: int, code: string}
	 * @throws CourseException `ccap_user_required`, `ccap_invalid_param`, `ccap_not_found`, `ccap_access_exists`, `ccap_idempotency_conflict`.
	 */
	public function grant_from_source( array $request, Actor $actor ): array {
		$user_id   = (int) ( $request['user_id'] ?? 0 );
		$course_id = (int) ( $request['course_id'] ?? 0 );
		$module_id = (int) ( $request['module_id'] ?? 0 );
		$scopes    = (string) ( $request['scopes'] ?? 'all' );
		$type      = (string) ( $request['source_type'] ?? '' );
		$reference = (string) ( $request['source_reference'] ?? '' );
		$line      = (string) ( $request['source_line'] ?? '' );
		$mode      = (string) ( $request['renewal_mode'] ?? 'extend' );
		$duration  = isset( $request['duration_days'] ) ? (int) $request['duration_days'] : null;
		if ( $user_id <= 0 || false === get_userdata( $user_id ) ) {
			throw new CourseException( 'ccap_user_required', 'A learner account is required.', 401 );
		}
		if ( 1 !== preg_match( '/^[a-z0-9_]{1,32}$/', $type ) || '' === $reference || strlen( $reference ) > 191 || ! in_array( $mode, array( 'extend', 'parallel' ), true ) || ( null !== $duration && $duration < 1 ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid grant request.', 400 );
		}
		if ( null === $this->relations->post( $course_id, 'ccap_course' ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
		}
		$existing = $this->db->row(
			$this->db->prepare(
				'SELECT id, user_id FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE source_type = %s AND source_id = %s AND source_line = %s AND course_id = %d AND module_id = %d ORDER BY source_revision DESC LIMIT 1',
				$type,
				$reference,
				$line,
				$course_id,
				$module_id
			)
		);
		if ( null !== $existing ) {
			if ( (int) $existing['user_id'] !== $user_id ) {
				throw new CourseException( 'ccap_idempotency_conflict', 'The source key belongs to another learner.', 409 );
			}
			return array(
				'grant_id' => (int) $existing['id'],
				'code'     => 'duplicate',
			);
		}
		if ( $this->holds_permanently( $user_id, $course_id, $module_id, $scopes ) ) {
			throw new CourseException( 'ccap_access_exists', 'The learner already has permanent access.', 409 );
		}
		$result = $this->create(
			array(
				'user_id'       => $user_id,
				'course_id'     => $course_id,
				'module_id'     => $module_id,
				'scopes'        => $scopes,
				'source_type'   => $type,
				'source_id'     => $reference,
				'source_line'   => $line,
				'source_plugin' => $type,
				'status'        => GrantTransitions::ACTIVE,
				'duration_days' => $duration,
				'renewal_mode'  => $mode,
				'reason'        => isset( $request['reason'] ) ? (string) $request['reason'] : null,
			),
			$actor,
			(string) ( $request['reason'] ?? 'source_grant' )
		);
		if ( $result['created'] ) {
			$row = (array) $this->relations->row( 'ccap_grant', $result['grant_id'] );
			AuditLog::write(
				$this->db,
				$actor,
				'grant.source_created',
				'grant',
				(string) $result['grant_id'],
				null,
				array(
					'source_type'      => $type,
					'source_reference' => $reference,
					'course_id'        => $course_id,
					'valid_from'       => $row['valid_from'] ?? null,
					'valid_until'      => $row['valid_until'] ?? null,
				),
				$user_id
			);
		}
		return array(
			'grant_id' => $result['grant_id'],
			'code'     => $result['created'] ? 'created' : 'duplicate',
		);
	}

	/**
	 * Revocation by rule for all grants of one source reference (GM-089):
	 * `revoke` ends them (`revoked`, a following `extend` grant moves
	 * forward), `keep` leaves them unchanged. Both are audited (GM-090).
	 *
	 * @param Actor  $actor       Connector or system actor.
	 * @param string $source_type Source type.
	 * @param string $reference   Source reference.
	 * @param string $rule        revoke|keep.
	 * @param string $reason_code Reason code of the revocation.
	 * @return array{code: string, grant_ids: list<int>}
	 * @throws CourseException `ccap_invalid_param` for unknown rules, `ccap_not_found` without grants.
	 */
	public function revoke_source( Actor $actor, string $source_type, string $reference, string $rule, string $reason_code = 'source_revoked' ): array {
		if ( ! in_array( $rule, array( 'revoke', 'keep' ), true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Unknown revocation rule.', 400 );
		}
		$rows = $this->db->rows( $this->db->prepare( 'SELECT id, user_id, status FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE source_type = %s AND source_id = %s ORDER BY id', $source_type, $reference ) );
		if ( array() === $rows ) {
			throw new CourseException( 'ccap_not_found', 'No grant for this reference.', 404 );
		}
		$ids     = array();
		$changed = false;
		foreach ( $rows as $row ) {
			$ids[] = (int) $row['id'];
			if ( 'revoke' === $rule && ! GrantTransitions::is_final( (string) $row['status'] ) ) {
				$changed = GrantTransitions::REVOKED === $this->transition( $actor, (int) $row['id'], GrantTransitions::REVOKED, $reason_code ) || $changed;
			}
		}
		$action = array(
			'revoke' => 'grant.source_revoked',
			'keep'   => 'grant.source_kept',
		)[ $rule ];
		foreach ( $rows as $row ) {
			AuditLog::write(
				$this->db,
				$actor,
				$action,
				'grant',
				(string) $row['id'],
				null,
				array(
					'source_type'      => $source_type,
					'source_reference' => $reference,
					'rule'             => $rule,
				),
				null === $row['user_id'] ? null : (int) $row['user_id']
			);
		}
		return array(
			'code'      => $changed ? 'updated' : 'unchanged',
			'grant_ids' => $ids,
		);
	}

	/**
	 * Grants of a source reference, newest first (grant search, GM-090).
	 *
	 * @param string $reference   Source reference (for manual grants the request key).
	 * @param string $source_type Optional source type.
	 * @return list<array<string, mixed>>
	 */
	public function find_by_reference( string $reference, string $source_type = '' ): array {
		$table = $this->db->table( 'ccap_grant' );
		$sql   = '' === $source_type
			? $this->db->prepare( "SELECT * FROM `{$table}` WHERE source_id = %s ORDER BY id DESC", $reference )
			: $this->db->prepare( "SELECT * FROM `{$table}` WHERE source_id = %s AND source_type = %s ORDER BY id DESC", $reference, $source_type );
		return array_values( $this->db->rows( $sql ) );
	}

	/**
	 * Whether the learner already holds the target permanently: an active,
	 * started grant without end on the whole course or the same module whose
	 * scopes cover the requested ones.
	 *
	 * @param int    $user   Learner.
	 * @param int    $course Course.
	 * @param int    $module Module (0 = whole course).
	 * @param string $scopes Requested scopes (`all` or list).
	 * @return bool
	 */
	private function holds_permanently( int $user, int $course, int $module, string $scopes ): bool {
		$table  = $this->db->table( 'ccap_grant' );
		$rows   = $this->db->rows(
			$this->db->prepare(
				"SELECT scopes, depends_on_course_id FROM `{$table}` WHERE user_id = %d AND course_id = %d AND module_id IN (0, %d) AND status = 'active' AND valid_until IS NULL AND (valid_from IS NULL OR valid_from <= %s)",
				$user,
				$course,
				$module,
				$this->db->now()
			)
		);
		$wanted = 'all' === $scopes ? null : array_map( 'trim', explode( ',', $scopes ) );
		foreach ( $rows as $row ) {
			if ( 0 !== (int) $row['depends_on_course_id'] ) {
				continue;
			}
			$have = (string) $row['scopes'];
			if ( 'all' === $have || ( null !== $wanted && array() === array_diff( $wanted, array_map( 'trim', explode( ',', $have ) ) ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Create a grant idempotently from a source (no policy check: callers
	 * are the manual path above, seat claims and source adapters).
	 *
	 * @param array<string, mixed> $spec            Grant fields.
	 * @param Actor                $actor           Actor for the event.
	 * @param string               $reason_code     Event reason.
	 * @param string|null          $source_event_id External event ID (dedupe).
	 * @return array{grant_id: int, created: bool}
	 * @throws CourseException `ccap_idempotency_conflict` when the key belongs to another learner.
	 */
	public function create( array $spec, Actor $actor, string $reason_code, ?string $source_event_id = null ): array {
		$user_id = isset( $spec['user_id'] ) ? (int) $spec['user_id'] : 0;
		$course  = (int) $spec['course_id'];
		$module  = (int) ( $spec['module_id'] ?? 0 );
		$scopes  = (string) ( $spec['scopes'] ?? 'all' );
		$lock    = NamedLock::for_key( $this->db, 'grant-chain:' . $user_id . ':' . $course );
		$lock->acquire( 2 );
		try {
			$before = $this->has_access( $user_id, $course );
			return $this->db->transaction(
				function ( Database $db ) use ( $spec, $actor, $reason_code, $source_event_id, $user_id, $course, $module, $scopes, $before ): array {
					// R-12 / CON-014: the account row is share-locked until commit, so a parallel
					// account deletion waits for this grant (and erases it afterwards) or wins and this
					// grant is refused; never a grant on a deleted account.
					if ( 0 !== $user_id && null === $db->scalar( $db->prepare( 'SELECT ID FROM `' . $db->wpdb()->users . '` WHERE ID = %d LOCK IN SHARE MODE', $user_id ) ) ) {
						throw new CourseException( 'ccap_invalid_param', 'Unknown user.', 400 );
					}
					$status = (string) ( $spec['status'] ?? GrantTransitions::ACTIVE );
					$paid   = (string) ( $spec['paid_at'] ?? $db->now() );
					$from   = null;
					$until  = isset( $spec['valid_until'] ) ? (string) $spec['valid_until'] : null;
					if ( GrantTransitions::ACTIVE === $status ) {
						$from = (string) ( $spec['valid_from'] ?? $db->now() );
						if ( null === $until && isset( $spec['duration_days'] ) ) {
							if ( 'extend' === ( $spec['renewal_mode'] ?? 'extend' ) ) {
								$from = max( $from, (string) ( $this->chain_end( $user_id, $course, $module, $scopes ) ?? $from ) );
							}
							$until = gmdate( 'Y-m-d H:i:s', (int) strtotime( $from . ' UTC' ) + 86400 * (int) $spec['duration_days'] );
						}
					}
					$row   = array(
						'uid'                  => $db->uuid(),
						'user_id'              => 0 === $user_id ? null : $user_id,
						'course_id'            => $course,
						'module_id'            => $module,
						'scopes'               => $scopes,
						'depends_on_course_id' => (int) ( $spec['depends_on_course_id'] ?? 0 ),
						'status'               => $status,
						'source_type'          => (string) $spec['source_type'],
						'source_id'            => (string) $spec['source_id'],
						'source_line'          => (string) ( $spec['source_line'] ?? '' ),
						'source_revision'      => (int) ( $spec['source_revision'] ?? 1 ),
						'source_seq'           => (int) ( $spec['source_seq'] ?? 0 ),
						'source_plugin'        => $spec['source_plugin'] ?? null,
						'seat_id'              => isset( $spec['seat_id'] ) ? (int) $spec['seat_id'] : null,
						'valid_from'           => $from,
						'valid_until'          => $until,
						'paid_at'              => $paid,
						'chain_anchor_at'      => (string) ( $spec['chain_anchor_at'] ?? $paid ),
						'renewal_mode'         => (string) ( $spec['renewal_mode'] ?? 'extend' ),
						'granted_by'           => isset( $spec['granted_by'] ) && (int) $spec['granted_by'] > 0 ? (int) $spec['granted_by'] : null,
						'reason'               => $spec['reason'] ?? null,
					);
					$quiet = $db->wpdb()->suppress_errors( true );
					try {
						$id = $db->insert( 'ccap_grant', $row, array( 'created_at', 'updated_at' ) );
					} catch ( StorageException $e ) {
						$db->wpdb()->suppress_errors( $quiet );
						if ( ! $e->is_duplicate() ) {
							throw $e;
						}
						$existing = $this->by_source_key( $row );
						if ( null === $existing || (int) ( $existing['user_id'] ?? 0 ) !== $user_id ) {
							throw new CourseException( 'ccap_idempotency_conflict', 'The source key belongs to another learner.', 409 );
						}
						return array(
							'grant_id' => (int) $existing['id'],
							'created'  => false,
						);
					}
					$db->wpdb()->suppress_errors( $quiet );
					$this->event( $id, 'created', null, $status, $reason_code, $actor, $source_event_id );
					$this->outbox( $id, 'grant.created', $actor );
					if ( GrantTransitions::ACTIVE === $status ) {
						$this->outbox( $id, 'grant.activated', $actor );
					}
					$this->access_changed( $user_id, $course, $before, $actor );
					return array(
						'grant_id' => $id,
						'created'  => true,
					);
				}
			);
		} finally {
			$lock->release();
			do_action( 'course_captain_policy_changed' );
		}
	}

	/**
	 * Apply a status transition (G-04…G-13) with CAS and guard.
	 *
	 * @param Actor       $actor           Actor.
	 * @param int         $grant_id        Grant.
	 * @param string      $to              Target status.
	 * @param string      $reason_code     Reason.
	 * @param bool        $manual          Admin decision (manual override).
	 * @param string|null $source_event_id External event ID.
	 * @return string New status, or `ignored` when the guard refused (logged).
	 * @throws CourseException When the grant does not exist or CAS keeps failing.
	 */
	public function transition( Actor $actor, int $grant_id, string $to, string $reason_code, bool $manual = false, ?string $source_event_id = null ): string {
		for ( $attempt = 0; $attempt < self::CAS_RETRIES; $attempt++ ) {
			$row = $this->relations->row( 'ccap_grant', $grant_id );
			if ( null === $row ) {
				throw new CourseException( 'ccap_not_found', 'Unknown grant.', 404 );
			}
			$from        = (string) $row['status'];
			$manual_lock = 'suspended' === $row['manual_override'];
			$allowed     = GrantTransitions::allowed( $from, $to );
			// A manual suspension is only lifted manually; revocation always wins (§2.3 Vorrang).
			if ( $allowed && GrantTransitions::SUSPENDED === $from && GrantTransitions::ACTIVE === $to && $manual_lock && ! $manual ) {
				$allowed = false;
			}
			if ( ! $allowed ) {
				$this->db->transaction(
					function () use ( $grant_id, $from, $to, $reason_code, $actor, $source_event_id ): void {
						$this->event( $grant_id, 'ignored', $from, $to, $reason_code, $actor, $source_event_id );
					}
				);
				return 'ignored';
			}
			$user   = (int) ( $row['user_id'] ?? 0 );
			$course = (int) $row['course_id'];
			$before = $this->has_access( $user, $course );
			$done   = $this->db->transaction(
				function ( Database $db ) use ( $row, $grant_id, $from, $to, $reason_code, $manual, $actor, $source_event_id, $user, $course, $before ): bool {
					$data = array( 'status' => $to );
					if ( $manual && GrantTransitions::SUSPENDED === $to ) {
						$data['manual_override'] = 'suspended';
					}
					if ( GrantTransitions::ACTIVE === $to ) {
						if ( $manual ) {
							$data['manual_override'] = null;
						}
						if ( null === $row['valid_from'] ) {
							$data['valid_from'] = $db->now();
						}
					}
					if ( GrantTransitions::REVOKED === $to ) {
						$data['revocation_reason'] = $reason_code;
					}
					if ( ! $db->compare_and_swap( 'ccap_grant', $grant_id, (int) $row['row_version'], $data ) ) {
						return false;
					}
					$this->event( $grant_id, 'status_changed', $from, $to, $reason_code, $actor, $source_event_id );
					$type = array(
						GrantTransitions::ACTIVE    => GrantTransitions::SUSPENDED === $from ? 'grant.resumed' : 'grant.activated',
						GrantTransitions::SUSPENDED => 'grant.suspended',
						GrantTransitions::EXPIRED   => 'grant.expired',
						GrantTransitions::REVOKED   => 'grant.revoked',
						GrantTransitions::FAILED    => 'grant.failed',
					)[ $to ] ?? 'grant.retried';
					$this->outbox( $grant_id, $type, $actor );
					$this->access_changed( $user, $course, $before, $actor );
					return true;
				}
			);
			if ( $done ) {
				do_action( 'course_captain_policy_changed' );
				if ( GrantTransitions::REVOKED === $to && 'extend' === $row['renewal_mode'] && $user > 0 ) {
					$this->restack( $actor, $user, $course, (int) $row['module_id'], (string) $row['scopes'] );
				}
				return $to;
			}
		}
		throw new CourseException( 'ccap_conflict', 'Concurrent change, please retry.', 409 );
	}

	/**
	 * Manual extension (G-14) or end in the past (G-10).
	 *
	 * @param Actor  $actor       Actor (M-13).
	 * @param int    $grant_id    Grant.
	 * @param string $valid_until New end (UTC).
	 * @param string $reason      Justification.
	 * @return string Resulting status.
	 * @throws CourseException When not allowed or not active.
	 */
	public function extend( Actor $actor, int $grant_id, string $valid_until, string $reason ): string {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_GRANT, array( $grant_id ) ), 'Not allowed to manage this grant.' );
		$row = (array) $this->relations->row( 'ccap_grant', $grant_id );
		if ( GrantTransitions::ACTIVE !== $row['status'] ) {
			throw new CourseException( 'ccap_conflict', 'Only active grants can be extended; reactivate instead.', 409 );
		}
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_reason_required', 'A reason is required.', 422 );
		}
		if ( $valid_until <= $this->db->now() ) {
			return $this->transition( $actor, $grant_id, GrantTransitions::EXPIRED, 'validity_ended', true );
		}
		$this->db->transaction(
			function ( Database $db ) use ( $row, $grant_id, $valid_until, $actor ): void {
				if ( ! $db->compare_and_swap(
					'ccap_grant',
					$grant_id,
					(int) $row['row_version'],
					array(
						'valid_until'        => $valid_until,
						'valid_until_locked' => 1,
					)
				) ) {
					throw new CourseException( 'ccap_conflict', 'Concurrent change, please retry.', 409 );
				}
				$this->event( $grant_id, 'extended', GrantTransitions::ACTIVE, GrantTransitions::ACTIVE, 'extended', $actor, null );
				$this->outbox( $grant_id, 'grant.extended', $actor );
			}
		);
		AuditLog::write( $this->db, $actor, 'grant.extended', 'grant', (string) $grant_id, $reason, array( 'valid_until' => $valid_until ), (int) $row['user_id'] );
		do_action( 'course_captain_policy_changed' );
		return GrantTransitions::ACTIVE;
	}

	/**
	 * Reactivation after `expired`/`revoked` as a new source revision (G-15, E-29).
	 *
	 * @param Actor       $actor       Actor.
	 * @param int         $grant_id    Ended grant.
	 * @param string      $reason_code reactivated|refund_reversed|reinstated.
	 * @param string|null $valid_until New end.
	 * @return int New grant ID.
	 * @throws CourseException When the grant is not final.
	 */
	public function reactivate( Actor $actor, int $grant_id, string $reason_code = 'reactivated', ?string $valid_until = null ): int {
		$row = $this->relations->row( 'ccap_grant', $grant_id );
		if ( null === $row || ! GrantTransitions::is_final( (string) $row['status'] ) ) {
			throw new CourseException( 'ccap_conflict', 'Only ended grants can be reactivated.', 409 );
		}
		$latest = (int) $this->db->scalar(
			$this->db->prepare(
				'SELECT MAX(source_revision) FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE source_type = %s AND source_id = %s AND source_line = %s AND course_id = %d AND module_id = %d',
				$row['source_type'],
				$row['source_id'],
				$row['source_line'],
				$row['course_id'],
				$row['module_id']
			)
		);
		return $this->create(
			array(
				'user_id'              => (int) $row['user_id'],
				'course_id'            => (int) $row['course_id'],
				'module_id'            => (int) $row['module_id'],
				'scopes'               => (string) $row['scopes'],
				'source_type'          => (string) $row['source_type'],
				'source_id'            => (string) $row['source_id'],
				'source_line'          => (string) $row['source_line'],
				'source_revision'      => $latest + 1,
				'seat_id'              => null === $row['seat_id'] ? null : (int) $row['seat_id'],
				'status'               => GrantTransitions::ACTIVE,
				'valid_until'          => $valid_until,
				'renewal_mode'         => (string) $row['renewal_mode'],
				'depends_on_course_id' => (int) $row['depends_on_course_id'],
				'chain_anchor_at'      => $row['chain_anchor_at'],
				'granted_by'           => $actor->user_id,
			),
			$actor,
			$reason_code
		)['grant_id'];
	}

	/**
	 * Expiry job (G-10/G-11, cosmetic: access never depends on it).
	 *
	 * @return int Number of expired grants.
	 */
	public function expire_due(): int {
		$table = $this->db->table( 'ccap_grant' );
		$ids   = $this->db->rows( $this->db->prepare( "SELECT id FROM `{$table}` WHERE status IN ('active', 'suspended') AND valid_until IS NOT NULL AND valid_until < %s ORDER BY id LIMIT 500", $this->db->now() ) );
		$count = 0;
		foreach ( $ids as $row ) {
			if ( GrantTransitions::EXPIRED === $this->transition( Actor::cron(), (int) $row['id'], GrantTransitions::EXPIRED, 'validity_ended' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Pending timeout (E-201): `pending_confirmation` older than 24 h → `failed`.
	 *
	 * @param int $hours Timeout.
	 * @return int Number of failed grants.
	 */
	public function fail_stale_pending( int $hours = 24 ): int {
		$table = $this->db->table( 'ccap_grant' );
		$ids   = $this->db->rows( $this->db->prepare( "SELECT id FROM `{$table}` WHERE status = 'pending_confirmation' AND created_at < %s ORDER BY id LIMIT 500", $this->db->now( -3600 * $hours ) ) );
		$count = 0;
		foreach ( $ids as $row ) {
			if ( GrantTransitions::FAILED === $this->transition( Actor::cron(), (int) $row['id'], GrantTransitions::FAILED, 'pending_timeout' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Re-chain the `extend` grants of a target (R0-09 §3.3): start =
	 * max(end of the previous active grant, own payment), duration unchanged.
	 *
	 * @param Actor  $actor  Actor.
	 * @param int    $user   Learner.
	 * @param int    $course Course.
	 * @param int    $module Module.
	 * @param string $scopes Scopes.
	 * @return void
	 */
	public function restack( Actor $actor, int $user, int $course, int $module, string $scopes ): void {
		$table  = $this->db->table( 'ccap_grant' );
		$rows   = $this->db->rows(
			$this->db->prepare(
				"SELECT id, valid_from, valid_until, paid_at, row_version FROM `{$table}` WHERE user_id = %d AND course_id = %d AND module_id = %d AND scopes = %s AND renewal_mode = 'extend' AND status = 'active' AND valid_until IS NOT NULL AND valid_until_locked = 0 ORDER BY chain_anchor_at, id",
				$user,
				$course,
				$module,
				$scopes
			)
		);
		$cursor = null;
		foreach ( $rows as $row ) {
			$duration = (int) strtotime( $row['valid_until'] . ' UTC' ) - (int) strtotime( $row['valid_from'] . ' UTC' );
			$start    = max( (string) $row['paid_at'], (string) ( $cursor ?? $row['paid_at'] ) );
			$end      = gmdate( 'Y-m-d H:i:s', (int) strtotime( $start . ' UTC' ) + $duration );
			if ( $start !== $row['valid_from'] ) {
				$this->db->transaction(
					function ( Database $db ) use ( $row, $start, $end, $actor ): void {
						if ( $db->compare_and_swap(
							'ccap_grant',
							(int) $row['id'],
							(int) $row['row_version'],
							array(
								'valid_from'  => $start,
								'valid_until' => $end,
							)
						) ) {
							$this->event( (int) $row['id'], 'rescheduled', GrantTransitions::ACTIVE, GrantTransitions::ACTIVE, 'rescheduled', $actor, null );
							$this->outbox( (int) $row['id'], 'grant.rescheduled', $actor );
						}
					}
				);
			}
			$cursor = $end;
		}
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * End of the latest active, bounded `extend` grant of the same target (§3.3).
	 *
	 * @param int    $user   Learner.
	 * @param int    $course Course.
	 * @param int    $module Module.
	 * @param string $scopes Scopes.
	 * @return string|null
	 */
	private function chain_end( int $user, int $course, int $module, string $scopes ): ?string {
		$table = $this->db->table( 'ccap_grant' );
		return $this->db->scalar(
			$this->db->prepare(
				"SELECT MAX(valid_until) FROM `{$table}` WHERE user_id = %d AND course_id = %d AND module_id = %d AND scopes = %s AND status = 'active' AND valid_until IS NOT NULL AND valid_until > %s",
				$user,
				$course,
				$module,
				$scopes,
				$this->db->now()
			)
		);
	}

	/**
	 * Row by the unique source key.
	 *
	 * @param array<string, mixed> $row Row with key columns.
	 * @return array<string, mixed>|null
	 */
	private function by_source_key( array $row ): ?array {
		return $this->db->row(
			$this->db->prepare(
				'SELECT * FROM `' . $this->db->table( 'ccap_grant' ) . '` WHERE source_type = %s AND source_id = %s AND source_line = %s AND source_revision = %d AND course_id = %d AND module_id = %d',
				$row['source_type'],
				$row['source_id'],
				$row['source_line'],
				$row['source_revision'],
				$row['course_id'],
				$row['module_id']
			)
		);
	}

	/**
	 * Dependency rules (§3.5): one level, no cycles.
	 *
	 * @param int $user    Learner.
	 * @param int $course  Add-on course.
	 * @param int $depends Base course.
	 * @return void
	 * @throws CourseException On invalid dependency.
	 */
	private function assert_dependency( int $user, int $course, int $depends ): void {
		$table = $this->db->table( 'ccap_grant' );
		$cycle = $this->db->scalar( $this->db->prepare( "SELECT 1 FROM `{$table}` WHERE user_id = %d AND course_id = %d AND depends_on_course_id <> 0 LIMIT 1", $user, $depends ) );
		if ( $depends === $course || null !== $cycle ) {
			throw new CourseException( 'ccap_validation_failed', 'Dependencies have one level and no cycles.', 422 );
		}
	}

	/**
	 * Whether a learner has view access to a course now.
	 *
	 * @param int $user   Learner.
	 * @param int $course Course.
	 * @return bool
	 */
	private function has_access( int $user, int $course ): bool {
		return $user > 0 && 'ok' === $this->relations->effective_access( $user, $course, 0, 'view' );
	}

	/**
	 * Emit `access.changed` only when the effective access flips (§3.4).
	 *
	 * @param int   $user   Learner.
	 * @param int   $course Course.
	 * @param bool  $before Access before.
	 * @param Actor $actor  Actor.
	 * @return void
	 */
	private function access_changed( int $user, int $course, bool $before, Actor $actor ): void {
		if ( $user <= 0 || $before === $this->has_access( $user, $course ) ) {
			return;
		}
		$course_uid = (string) $this->db->scalar( $this->db->prepare( 'SELECT uid FROM `' . $this->db->table( 'ccap_source_map' ) . "` WHERE object_type = 'course' AND object_id = %d", $course ) );
		Outbox::record(
			$this->db,
			'access.changed',
			'course',
			'' === $course_uid ? (string) $course : $course_uid,
			1,
			array( 'has_access' => ! $before ),
			array(
				'user_id'      => $user,
				'course_uid'   => '' === $course_uid ? null : $course_uid,
				'actor_type'   => $actor->is_system() ? 'system' : 'user',
				'actor_ref'    => $actor->key(),
				'privacy_tags' => array( 'personal_ref', 'access_state' ),
			)
		);
	}

	/**
	 * Append a grant event.
	 *
	 * @param int         $grant_id        Grant.
	 * @param string      $type            Event type.
	 * @param string|null $from            From status.
	 * @param string|null $to              To status.
	 * @param string      $reason          Reason code.
	 * @param Actor       $actor           Actor.
	 * @param string|null $source_event_id External event ID.
	 * @return void
	 */
	private function event( int $grant_id, string $type, ?string $from, ?string $to, string $reason, Actor $actor, ?string $source_event_id ): void {
		$now = $this->db->now();
		$this->db->insert(
			'ccap_grant_event',
			array(
				'grant_id'        => $grant_id,
				'event_type'      => $type,
				'from_status'     => $from,
				'to_status'       => $to,
				'reason_code'     => $reason,
				'actor_type'      => $actor->is_system() ? ( Actor::CONNECTOR === $actor->type ? 'connector' : 'system' ) : 'user',
				'actor_id'        => $actor->user_id > 0 ? $actor->user_id : null,
				'source_event_id' => $source_event_id,
				'occurred_at'     => $now,
				'created_at'      => $now,
			)
		);
	}

	/**
	 * Outbox entry for a grant event.
	 *
	 * @param int    $grant_id Grant.
	 * @param string $type     Event type.
	 * @param Actor  $actor    Actor.
	 * @return void
	 */
	private function outbox( int $grant_id, string $type, Actor $actor ): void {
		$row = (array) $this->relations->row( 'ccap_grant', $grant_id );
		Outbox::record(
			$this->db,
			$type,
			'grant',
			(string) $row['uid'],
			(int) $row['source_revision'] * 1000 + (int) $row['row_version'],
			array(
				'status'          => $row['status'],
				'scopes'          => $row['scopes'],
				'module_id'       => (int) $row['module_id'],
				'valid_until'     => $row['valid_until'],
				'source_type'     => $row['source_type'],
				'source_revision' => (int) $row['source_revision'],
			),
			array(
				'user_id'      => (int) ( $row['user_id'] ?? 0 ),
				'actor_type'   => $actor->is_system() ? 'system' : 'user',
				'actor_ref'    => $actor->key(),
				'privacy_tags' => array( 'personal_ref', 'access_state' ),
			)
		);
	}
}
