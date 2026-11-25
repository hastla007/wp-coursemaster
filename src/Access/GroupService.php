<?php
/**
 * Groups, seats, invitations and claims (R0-09 §1.2, §4).
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

defined( 'ABSPATH' ) || exit;

/**
 * Seats are assigned under `group-seats:<id>` (≤ 2 s wait) with a recount
 * after insert. Tokens are 256 bit, only their SHA-256 hash is stored.
 * Removed/expired seats are never revived; a new seat is a new row.
 * Seat grants are created in the same transaction as the seat.
 */
final class GroupService {

	private const LOCK_WAIT   = 2;
	private const TOKEN_BYTES = 32;

	/**
	 * Constructor.
	 *
	 * @param Database      $db        Database access.
	 * @param PolicyService $policy    Policy.
	 * @param Relations     $relations Relations.
	 * @param GrantService  $grants    Grants.
	 */
	public function __construct( private Database $db, private PolicyService $policy, private Relations $relations, private GrantService $grants ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		$db = Database::current();
		return new self( $db, PolicyService::instance(), new Relations( $db ), GrantService::current() );
	}

	/**
	 * Create a group manually (E-17), idempotent per `request_key`.
	 *
	 * @param Actor                                                         $actor        Actor (`ccap_manage_groups`).
	 * @param int                                                           $owner        Owner account.
	 * @param string                                                        $name         Name (sanitized: no links/addresses).
	 * @param int                                                           $seats        Seats ≥ 1.
	 * @param list<array{course_id: int, module_id?: int, scopes?: string}> $targets Targets.
	 * @param string                                                        $request_key  UUID.
	 * @param array<string, mixed>                                          $options      `valid_until`, `claim_policy`, `reason`.
	 * @return int Group ID.
	 * @throws CourseException On invalid input or missing rights.
	 */
	public function create( Actor $actor, int $owner, string $name, int $seats, array $targets, string $request_key, array $options = array() ): int {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_manage_groups' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to create groups.', 403 );
		}
		$name = self::sanitize_name( $name );
		// SET-CC-P1: without an explicit choice a new group starts with `enrollment.claim_policy`.
		$policy = (string) ( $options['claim_policy'] ?? \CourseCaptain\Settings\Settings::get( 'enrollment.claim_policy' ) );
		if ( '' === $name || $seats < 1 || array() === $targets || false === get_userdata( $owner ) || ! in_array( $policy, array( 'transferable', 'invited_address_only' ), true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid group data.', 422 );
		}
		foreach ( $targets as $target ) {
			if ( null === $this->relations->post( (int) $target['course_id'], Relations::COURSE_TYPE ) ) {
				throw new CourseException( 'ccap_not_found', 'Unknown course.', 404 );
			}
		}
		$table    = $this->db->table( 'ccap_group' );
		$existing = $this->db->scalar( $this->db->prepare( "SELECT id FROM `{$table}` WHERE source_type = 'manual' AND source_id = %s", $request_key ) );
		if ( null !== $existing ) {
			return (int) $existing;
		}
		$id = $this->db->transaction(
			function ( Database $db ) use ( $owner, $name, $seats, $targets, $request_key, $options, $policy, $actor ): int {
				$group = $db->insert(
					'ccap_group',
					array(
						'uid'           => $db->uuid(),
						'owner_user_id' => $owner,
						'name'          => $name,
						'seats'         => $seats,
						'claim_policy'  => $policy,
						'valid_until'   => $options['valid_until'] ?? null,
						'source_type'   => 'manual',
						'source_id'     => $request_key,
						'reason'        => $options['reason'] ?? null,
					),
					array( 'created_at', 'updated_at' )
				);
				foreach ( $targets as $target ) {
					$db->insert(
						'ccap_group_course',
						array(
							'group_id'  => $group,
							'course_id' => (int) $target['course_id'],
							'module_id' => (int) ( $target['module_id'] ?? 0 ),
							'scopes'    => (string) ( $target['scopes'] ?? 'all' ),
						)
					);
				}
				$this->group_event( $group, 'group.created', $actor );
				return $group;
			}
		);
		AuditLog::write( $this->db, $actor, 'group.created', 'group', (string) $id, (string) ( $options['reason'] ?? '' ), array( 'seats' => $seats ) );
		return $id;
	}

	/**
	 * Invite an address (personal token `cci_`), if a seat is free.
	 *
	 * @param Actor  $actor    Owner or manager (M-15, write).
	 * @param int    $group_id Group.
	 * @param string $email    Address (communication only, never identity).
	 * @return array{seat_id: int, token: string}|null Null when no seat is free (no mail).
	 * @throws CourseException On missing rights or invalid address.
	 */
	public function invite( Actor $actor, int $group_id, string $email ): ?array {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_GROUP, array( $group_id ), array( 'write' => true ) ), 'Not allowed to manage this group.' );
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid address.', 422 );
		}
		return $this->locked(
			$group_id,
			function () use ( $group_id, $email, $actor ): ?array {
				$group = (array) $this->relations->row( 'ccap_group', $group_id );
				if ( $this->occupied( $group_id ) >= (int) $group['seats'] ) {
					return null;
				}
				$token = self::token( 'cci' );
				$seat  = $this->db->transaction(
					function ( Database $db ) use ( $group_id, $email, $token, $actor ): int {
						$seat = $db->insert(
							'ccap_seat',
							array(
								'group_id'         => $group_id,
								'invite_email'     => $email,
								'status'           => 'invited',
								'claim_token_hash' => hash( 'sha256', $token ),
								'invited_at'       => $db->now(),
								'claim_expires_at' => $db->now( \CourseCaptain\Settings\BusinessRules::invite_valid_days() * DAY_IN_SECONDS ),
							),
							array( 'created_at', 'updated_at' )
						);
						$this->seat_event( $group_id, $seat, 'seat.invited', $actor, 0 );
						return $seat;
					}
				);
				return array(
					'seat_id' => $seat,
					'token'   => $token,
				);
			}
		);
	}

	/**
	 * New group link (`ccg_`), the old one is invalid at once (§4.7).
	 *
	 * @param Actor $actor    Owner or manager.
	 * @param int   $group_id Group.
	 * @return string Raw token (shown once).
	 * @throws CourseException When the group only accepts invited addresses.
	 */
	public function rotate_link( Actor $actor, int $group_id ): string {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_GROUP, array( $group_id ), array( 'write' => true ) ), 'Not allowed to manage this group.' );
		$group = (array) $this->relations->row( 'ccap_group', $group_id );
		if ( 'invited_address_only' === $group['claim_policy'] ) {
			throw new CourseException( 'ccap_invalid_param', 'Group links are disabled for invited addresses only.', 409 );
		}
		$token = self::token( 'ccg' );
		$this->db->compare_and_swap( 'ccap_group', $group_id, (int) $group['row_version'], array( 'join_token_hash' => hash( 'sha256', $token ) ) );
		do_action( 'course_captain_policy_changed' );
		AuditLog::write( $this->db, $actor, 'group.token_rotated', 'group', (string) $group_id );
		return $token;
	}

	/**
	 * Claim a seat with a personal invitation or the group link (§4.3).
	 *
	 * @param Actor  $actor Learner.
	 * @param string $token Raw token.
	 * @return int Seat ID (existing active seat on repeated claims).
	 * @throws CourseException With `ccap_claim_used`, `ccap_group_full`, `ccap_group_busy` etc.
	 */
	public function claim( Actor $actor, string $token ): int {
		$decision = $this->policy->decide( $actor, PolicyService::CLAIM_SEAT, array(), array( 'token' => $token ) );
		$target   = $this->relations->claim_target( $token );
		$group_id = (int) ( $target['group']['id'] ?? 0 );
		if ( 0 !== $group_id ) {
			$member = $this->active_seat( $group_id, $actor->user_id );
			if ( null !== $member ) {
				return $member;
			}
		}
		if ( ! $decision->allowed ) {
			throw self::claim_refused( $decision ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: machine code for the entry point.
		}
		return $this->locked(
			$group_id,
			function () use ( $actor, $token, $group_id ): int {
				// Re-resolve under the lock (§4.3 step 3).
				$target = $this->relations->claim_target( $token );
				$group  = (array) $target['group'];
				$member = $this->active_seat( $group_id, $actor->user_id );
				if ( null !== $member ) {
					return $member;
				}
				$seat = $target['seat'];
				return $this->db->transaction(
					function ( Database $db ) use ( $actor, $token, $group, $seat, $group_id ): int {
						// R-1 parent row (CON-001/CON-006): serialises the claims of one group in the DB, so
						// the recount below sees every older committed seat even when GET_LOCK is unavailable.
						$db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d FOR UPDATE', $group_id ) );
						// Second tab of the same account after the row lock (CON-002): existing membership.
						$member = $this->active_seat( $group_id, $actor->user_id );
						if ( null !== $member ) {
							return $member;
						}
						// R0-09 R-12 / ID-014: the account is checked inside the transaction. The share lock
						// keeps a parallel account deletion waiting until this join has committed; the
						// deletion's second pass (`deleted_user`) then removes the seat and its grants.
						$users = $db->wpdb()->users;
						if ( null === $db->scalar( $db->prepare( "SELECT ID FROM `{$users}` WHERE ID = %d LOCK IN SHARE MODE", $actor->user_id ) ) ) {
							throw new CourseException( 'ccap_forbidden', 'The account no longer exists.', 403 );
						}
						$now = $db->now();
						if ( null !== $seat ) {
							$rows = $db->execute(
								$db->prepare(
									'UPDATE `' . $db->table( 'ccap_seat' ) . "` SET status = 'active', user_id = %d, invite_email = NULL, claimed_at = %s, updated_at = %s WHERE id = %d AND status = 'invited' AND claim_token_hash = %s AND claim_expires_at > %s",
									$actor->user_id,
									$now,
									$now,
									(int) $seat['id'],
									hash( 'sha256', $token ),
									$now
								)
							);
							if ( 1 !== $rows ) {
								throw new CourseException( 'ccap_claim_used', 'Invitation already used or expired.', 409 );
							}
							$seat_id = (int) $seat['id'];
						} else {
							$seat_id = $db->insert(
								'ccap_seat',
								array(
									'group_id'   => $group_id,
									'user_id'    => $actor->user_id,
									'status'     => 'active',
									'claimed_at' => $now,
								),
								array( 'created_at', 'updated_at' )
							);
							// Recount after insert; older rows win (§4.3 step 6).
							$older = (int) $db->scalar(
								$db->prepare(
									'SELECT COUNT(*) FROM `' . $db->table( 'ccap_seat' ) . "` WHERE group_id = %d AND id < %d AND (status = 'active' OR (status = 'invited' AND claim_expires_at > %s))",
									$group_id,
									$seat_id,
									$now
								)
							);
							if ( $older >= (int) $group['seats'] ) {
								throw new CourseException( 'ccap_group_full', 'No free seat.', 409 );
							}
						}
						$this->seat_grants( $group, $seat_id, $actor->user_id, $actor );
						$this->seat_event( $group_id, $seat_id, 'seat.claimed', $actor, $actor->user_id );
						return $seat_id;
					}
				);
			}
		);
	}

	/**
	 * Exception for a refused claim (RBAC-054/055 codes).
	 *
	 * @param \CourseCaptain\Policy\Decision $decision Denial.
	 * @return CourseException
	 */
	private static function claim_refused( \CourseCaptain\Policy\Decision $decision ): CourseException {
		if ( '' === $decision->reason ) {
			return new CourseException( $decision->code, 'Claim refused.', $decision->status() );
		}
		$status = 'group_not_active' === $decision->reason ? 409 : $decision->status();
		return new CourseException( 'ccap_' . $decision->reason, 'Claim refused.', $status );
	}

	/**
	 * Free a seat (§4.4): seat `removed`, its grants `revoked`.
	 *
	 * @param Actor  $actor    Owner or manager.
	 * @param int    $group_id Group.
	 * @param int    $seat_id  Seat.
	 * @param string $reason  Reason code.
	 * @return void
	 * @throws CourseException 404 for seats of other groups.
	 */
	public function remove_seat( Actor $actor, int $group_id, int $seat_id, string $reason = 'group_member_removed' ): void {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_GROUP, array( $group_id ), array( 'write' => true ) ), 'Not allowed to manage this group.' );
		$seat = $this->relations->row( 'ccap_seat', $seat_id );
		if ( null === $seat || (int) $seat['group_id'] !== $group_id ) {
			throw new CourseException( 'ccap_not_found', 'Unknown seat.', 404 );
		}
		$this->locked( $group_id, fn() => $this->end_seat( $actor, $seat, $reason ) );
	}

	/**
	 * Reduce seats (§4.4, GM-101): open invitations (newest first), then
	 * members (last joined first). `dry_run` returns the affected seats only.
	 *
	 * @param Actor $actor    Manager.
	 * @param int   $group_id Group.
	 * @param int   $seats    New number of seats.
	 * @param bool  $dry_run  Preview only.
	 * @return list<int> Affected seat IDs.
	 * @throws CourseException On invalid input.
	 */
	public function reduce_seats( Actor $actor, int $group_id, int $seats, bool $dry_run = false ): array {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_manage_groups' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to manage groups.', 403 );
		}
		if ( $seats < 0 ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid number of seats.', 422 );
		}
		return $this->locked(
			$group_id,
			function () use ( $actor, $group_id, $seats, $dry_run ): array {
				$table    = $this->db->table( 'ccap_seat' );
				$rows     = $this->db->rows(
					$this->db->prepare(
						"SELECT * FROM `{$table}` WHERE group_id = %d AND (status = 'active' OR (status = 'invited' AND claim_expires_at > %s)) ORDER BY (status = 'active'), COALESCE(claimed_at, invited_at) DESC, id DESC",
						$group_id,
						$this->db->now()
					)
				);
				$excess   = max( 0, count( $rows ) - $seats );
				$affected = array_slice( $rows, 0, $excess );
				if ( ! $dry_run ) {
					$group = (array) $this->relations->row( 'ccap_group', $group_id );
					$this->db->compare_and_swap( 'ccap_group', $group_id, (int) $group['row_version'], array( 'seats' => $seats ) );
					foreach ( $affected as $seat ) {
						$this->end_seat( $actor, $seat, 'group_seats_reduced' );
					}
					$this->db->transaction( fn() => $this->group_event( $group_id, 'group.seats_changed', $actor ) );
				}
				return array_map( static fn( array $s ): int => (int) $s['id'], $affected );
			}
		);
	}

	/**
	 * Change the group status (§4.5) and pass it on to the seat grants.
	 *
	 * @param Actor  $actor    Manager.
	 * @param int    $group_id Group.
	 * @param string $status   active|suspended|revoked.
	 * @return void
	 * @throws CourseException On invalid transitions.
	 */
	public function set_status( Actor $actor, int $group_id, string $status ): void {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_manage_groups' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to manage groups.', 403 );
		}
		$group = $this->relations->row( 'ccap_group', $group_id );
		if ( null === $group || 'revoked' === $group['status'] || ! in_array( $status, array( 'active', 'suspended', 'revoked' ), true ) ) {
			throw new CourseException( 'ccap_conflict', 'Invalid group transition.', 409 );
		}
		$this->locked(
			$group_id,
			function () use ( $actor, $group, $group_id, $status ): void {
				$this->db->compare_and_swap( 'ccap_group', $group_id, (int) $group['row_version'], array( 'status' => $status ) );
				$grants = $this->db->rows(
					$this->db->prepare(
						'SELECT g.id, g.status, g.manual_override FROM `' . $this->db->table( 'ccap_grant' ) . '` g JOIN `' . $this->db->table( 'ccap_seat' ) . "` s ON s.id = g.seat_id WHERE s.group_id = %d AND s.status = 'active' AND g.source_type = 'group_seat'",
						$group_id
					)
				);
				foreach ( $grants as $grant ) {
					if ( null !== $grant['manual_override'] && 'revoked' !== $status ) {
						continue;
					}
					$map = array(
						'suspended' => array( GrantTransitions::SUSPENDED, 'group_suspended' ),
						'active'    => array( GrantTransitions::ACTIVE, 'group_resumed' ),
						'revoked'   => array( GrantTransitions::REVOKED, 'group_revoked' ),
					);
					$this->grants->transition( $actor, (int) $grant['id'], $map[ $status ][0], $map[ $status ][1] );
				}
				$this->db->transaction( fn() => $this->group_event( $group_id, 'group.status_changed', $actor ) );
			}
		);
	}

	/**
	 * Reinstate an ended group (owner decision D11, R0-09 §4.8, E-29).
	 *
	 * Only administrators (`ccap_manage_groups` and `manage_options`), with a
	 * reason. Under the group lock (L3) the group becomes `active` with
	 * `source_revision + 1` (CAS, outbox `group.reinstated`); afterwards every
	 * seat grant that ended with `group_revoked` in the old revision on a seat
	 * that is still `active` gets a new grant revision (G-15 via
	 * {@see GrantService::reactivate()}, which takes the chain lock L4 itself:
	 * order L3 → L4 → DB-TX, no lock inside an open transaction). A grant is
	 * only renewed while it is still valid: its own end when it was set
	 * individually (`valid_until_locked`), else the group's end. Removed seats
	 * stay removed; open invitations work again until their deadline.
	 *
	 * @param Actor  $actor    Administrator.
	 * @param int    $group_id Ended group.
	 * @param string $reason   Reason (required, audited).
	 * @return array{revision: int, grants: int, skipped: int} New revision, renewed grants, grants left ended because their validity is over.
	 * @throws CourseException `ccap_forbidden`, `ccap_reason_required`, `ccap_not_found`, `ccap_conflict`.
	 */
	public function reinstate( Actor $actor, int $group_id, string $reason ): array {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_manage_groups' ) || ! user_can( $actor->user_id, 'manage_options' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Only administrators can reinstate a group.', 403 );
		}
		$reason = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_reason_required', 'A reason is required.', 422 );
		}
		if ( null === $this->relations->row( 'ccap_group', $group_id ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown group.', 404 );
		}
		$result = $this->locked(
			$group_id,
			function () use ( $actor, $group_id ): array {
				// Re-read under the lock: a parallel reinstatement must not create a second revision.
				$group = (array) $this->relations->row( 'ccap_group', $group_id );
				if ( 'revoked' !== ( $group['status'] ?? '' ) ) {
					throw new CourseException( 'ccap_conflict', 'Only an ended group can be reinstated.', 409 );
				}
				$old      = (int) $group['source_revision'];
				$revision = $old + 1;
				$this->db->transaction(
					function ( Database $db ) use ( $group, $group_id, $revision, $actor ): void {
						if ( ! $db->compare_and_swap(
							'ccap_group',
							$group_id,
							(int) $group['row_version'],
							array(
								'status'          => 'active',
								'source_revision' => $revision,
							)
						) ) {
							throw new CourseException( 'ccap_conflict', 'Concurrent change, please retry.', 409 );
						}
						$this->group_event( $group_id, 'group.reinstated', $actor );
					}
				);
				$ended   = $this->db->rows(
					$this->db->prepare(
						'SELECT g.id, g.valid_until, g.valid_until_locked FROM `' . $this->db->table( 'ccap_grant' ) . '` g JOIN `' . $this->db->table( 'ccap_seat' ) . "` s ON s.id = g.seat_id WHERE s.group_id = %d AND s.status = 'active' AND s.user_id IS NOT NULL AND g.source_type = 'group_seat' AND g.status = 'revoked' AND g.revocation_reason = 'group_revoked' AND g.source_revision = %d ORDER BY g.id",
						$group_id,
						$old
					)
				);
				$now     = $this->db->now();
				$renewed = 0;
				$skipped = 0;
				foreach ( $ended as $grant ) {
					$until = 1 === (int) $grant['valid_until_locked'] ? $grant['valid_until'] : $group['valid_until'];
					if ( null !== $until && (string) $until <= $now ) {
						++$skipped;
						continue;
					}
					$this->grants->reactivate( $actor, (int) $grant['id'], 'reinstated', null === $until ? null : (string) $until );
					++$renewed;
				}
				return array(
					'revision' => $revision,
					'grants'   => $renewed,
					'skipped'  => $skipped,
				);
			}
		);
		AuditLog::write( $this->db, $actor, 'group.reinstated', 'group', (string) $group_id, $reason, $result );
		PolicyService::flush();
		\CourseCaptain\Cache\CacheStore::bump_content();
		return $result;
	}

	/**
	 * Change the validity (GF-05): seat grants take the new end unless
	 * their end was set individually (`valid_until_locked`, R0-09 §3.3).
	 *
	 * @param Actor       $actor       Manager (#11).
	 * @param int         $group_id    Group.
	 * @param string|null $valid_until New end (UTC `Y-m-d H:i:s`) or null (unlimited).
	 * @return int Number of seat grants that took the new end.
	 * @throws CourseException `ccap_forbidden`, `ccap_not_found`, `ccap_invalid_param`, `ccap_conflict`.
	 */
	public function set_validity( Actor $actor, int $group_id, ?string $valid_until ): int {
		$group = $this->managed( $actor, $group_id );
		if ( null !== $valid_until && 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $valid_until ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid end date.', 422 );
		}
		return $this->locked(
			$group_id,
			function () use ( $actor, $group, $group_id, $valid_until ): int {
				if ( ! $this->db->compare_and_swap( 'ccap_group', $group_id, (int) $group['row_version'], array( 'valid_until' => $valid_until ) ) ) {
					throw new CourseException( 'ccap_conflict', 'Concurrent change, please retry.', 409 );
				}
				$grants = $this->db->table( 'ccap_grant' );
				$seats  = $this->db->table( 'ccap_seat' );
				$count  = $this->db->execute(
					$this->db->prepare(
						"UPDATE `{$grants}` g JOIN `{$seats}` s ON s.id = g.seat_id SET g.valid_until = " . ( null === $valid_until ? 'NULL' : $this->db->prepare( '%s', $valid_until ) ) . ", g.row_version = g.row_version + 1, g.updated_at = %s WHERE s.group_id = %d AND g.source_type = 'group_seat' AND g.valid_until_locked = 0 AND g.status IN ('pending', 'active', 'suspended')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Value prepared above.
						$this->db->now(),
						$group_id
					)
				);
				AuditLog::write( $this->db, $actor, 'group.validity_changed', 'group', (string) $group_id, null, array( 'valid_until' => $valid_until, 'grants' => $count ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				\CourseCaptain\Cache\CacheStore::bump_content();
				return $count;
			}
		);
	}

	/**
	 * Change the owner (GF-07): reason mandatory, the old owner loses access
	 * at once, the group link stops working (the new owner creates a new one).
	 *
	 * @param Actor  $actor    Manager (#11).
	 * @param int    $group_id Group.
	 * @param int    $owner    New owner.
	 * @param string $reason   Reason.
	 * @return void
	 * @throws CourseException `ccap_reason_required`, `ccap_invalid_param`, `ccap_conflict`.
	 */
	public function change_owner( Actor $actor, int $group_id, int $owner, string $reason ): void {
		$group  = $this->managed( $actor, $group_id );
		$reason = sanitize_textarea_field( $reason );
		if ( '' === trim( $reason ) ) {
			throw new CourseException( 'ccap_reason_required', 'A reason is required.', 422 );
		}
		if ( false === get_userdata( $owner ) || (int) $group['owner_user_id'] === $owner ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid owner.', 422 );
		}
		$this->db->transaction(
			function ( Database $db ) use ( $group, $group_id, $owner, $actor ): void {
				if ( ! $db->compare_and_swap(
					'ccap_group',
					$group_id,
					(int) $group['row_version'],
					array(
						'owner_user_id'   => $owner,
						'join_token_hash' => null,
					)
				) ) {
					throw new CourseException( 'ccap_conflict', 'Concurrent change, please retry.', 409 );
				}
				$this->group_event( $group_id, 'group.owner_changed', $actor );
			}
		);
		AuditLog::write( $this->db, $actor, 'group.owner_changed', 'group', (string) $group_id, $reason, array( 'previous_owner' => (int) $group['owner_user_id'], 'owner' => $owner ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		PolicyService::flush();
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * `claim_policy` of a group (GF-08); `invited_address_only` disables the
	 * group link (its hash is removed, a later switch back needs a new link).
	 *
	 * @param Actor  $actor    Owner (M-15) or manager.
	 * @param int    $group_id Group.
	 * @param string $policy   transferable|invited_address_only.
	 * @return void
	 * @throws CourseException On invalid input or missing rights.
	 */
	public function set_claim_policy( Actor $actor, int $group_id, string $policy ): void {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_GROUP, array( $group_id ), array( 'write' => true ) ), 'Not allowed to manage this group.' );
		if ( ! in_array( $policy, array( 'transferable', 'invited_address_only' ), true ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid claim policy.', 422 );
		}
		$group = (array) $this->relations->row( 'ccap_group', $group_id );
		if ( $policy === $group['claim_policy'] ) {
			return;
		}
		$change = array( 'claim_policy' => $policy );
		if ( 'invited_address_only' === $policy ) {
			$change['join_token_hash'] = null;
		}
		if ( ! $this->db->compare_and_swap( 'ccap_group', $group_id, (int) $group['row_version'], $change ) ) {
			throw new CourseException( 'ccap_conflict', 'Concurrent change, please retry.', 409 );
		}
		AuditLog::write( $this->db, $actor, 'group.policy_changed', 'group', (string) $group_id, null, array( 'claim_policy' => $policy ) );
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * Rename a group (GF-14, name sanitized like SEAT-021).
	 *
	 * @param Actor  $actor    Owner (M-15) or manager.
	 * @param int    $group_id Group.
	 * @param string $name     New name.
	 * @return string Stored name.
	 * @throws CourseException On invalid input or missing rights.
	 */
	public function rename( Actor $actor, int $group_id, string $name ): string {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_GROUP, array( $group_id ), array( 'write' => true ) ), 'Not allowed to manage this group.' );
		$name = self::sanitize_name( $name );
		if ( '' === $name ) {
			throw new CourseException( 'ccap_invalid_param', 'Invalid group name.', 422 );
		}
		$group = (array) $this->relations->row( 'ccap_group', $group_id );
		if ( ! $this->db->compare_and_swap( 'ccap_group', $group_id, (int) $group['row_version'], array( 'name' => $name ) ) ) {
			throw new CourseException( 'ccap_conflict', 'Concurrent change, please retry.', 409 );
		}
		AuditLog::write( $this->db, $actor, 'group.renamed', 'group', (string) $group_id );
		return $name;
	}

	/**
	 * Groups overview for managers (A-10, §1.4 figures).
	 *
	 * @param Actor  $actor  Manager (#11).
	 * @param string $status Filter (empty = all).
	 * @return list<array{id: int, name: string, owner: string, status: string, seats_total: int, seats_used: int, invites_open: int, seats_ended: int, valid_until: string|null, claim_policy: string, courses: list<int>}>
	 * @throws CourseException `ccap_forbidden`.
	 */
	public function overview( Actor $actor, string $status = '' ): array {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_manage_groups' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to manage groups.', 403 );
		}
		$table = $this->db->table( 'ccap_group' );
		$where = in_array( $status, array( 'active', 'suspended', 'revoked' ), true ) ? $this->db->prepare( 'WHERE status = %s', $status ) : '';
		$out   = array();
		foreach ( $this->db->rows( "SELECT * FROM `{$table}` {$where} ORDER BY id DESC LIMIT 500" ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Prepared above.
			$id    = (int) $row['id'];
			$owner = get_userdata( (int) $row['owner_user_id'] );
			$out[] = array(
				'id'           => $id,
				'name'         => (string) $row['name'],
				'owner'        => $owner instanceof \WP_User ? (string) $owner->display_name : '',
				'status'       => (string) $row['status'],
				'seats_total'  => (int) $row['seats'],
				'seats_used'   => $this->occupied( $id ),
				'invites_open' => (int) $this->db->scalar( $this->db->prepare( 'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_seat' ) . "` WHERE group_id = %d AND status = 'invited' AND claim_expires_at > %s", $id, $this->db->now() ) ),
				'seats_ended'  => (int) $this->db->scalar( $this->db->prepare( 'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_seat' ) . "` WHERE group_id = %d AND status = 'removed' AND user_id IS NOT NULL", $id ) ),
				'valid_until'  => null === $row['valid_until'] ? null : (string) $row['valid_until'],
				'claim_policy' => (string) $row['claim_policy'],
				'courses'      => array_map( 'intval', $this->db->wpdb()->get_col( $this->db->prepare( 'SELECT course_id FROM `' . $this->db->table( 'ccap_group_course' ) . '` WHERE group_id = %d ORDER BY id', $id ) ) ),
			);
		}
		return $out;
	}

	/**
	 * Members and seats (GF-15): status, display name, masked address; never
	 * learning results (GR-7). The full address only for the owner (SEAT-017).
	 *
	 * @param Actor $actor    Owner (M-15) or manager.
	 * @param int   $group_id Group.
	 * @return list<array{seat_id: int, status: string, name: string, address: string, since: string|null}>
	 * @throws CourseException 404 without rights.
	 */
	public function members( Actor $actor, int $group_id ): array {
		CourseException::unless_allowed( $this->policy->decide( $actor, PolicyService::MANAGE_GROUP, array( $group_id ) ), 'Not allowed to view this group.' );
		$group = (array) $this->relations->row( 'ccap_group', $group_id );
		$owner = (int) $group['owner_user_id'] === $actor->user_id;
		$out   = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT id, user_id, invite_email, status, invited_at, claimed_at, claim_expires_at FROM `' . $this->db->table( 'ccap_seat' ) . "` WHERE group_id = %d AND status IN ('invited', 'active') ORDER BY id", $group_id ) ) as $seat ) {
			$user  = null === $seat['user_id'] ? null : get_userdata( (int) $seat['user_id'] );
			$email = (string) ( $seat['invite_email'] ?? '' );
			$out[] = array(
				'seat_id' => (int) $seat['id'],
				'status'  => 'invited' === $seat['status'] && (string) $seat['claim_expires_at'] <= $this->db->now() ? 'expired' : (string) $seat['status'],
				'name'    => $user instanceof \WP_User ? (string) $user->display_name : '',
				'address' => '' === $email ? '' : ( $owner ? $email : self::mask_email( $email ) ),
				'since'   => null === ( $seat['claimed_at'] ?? $seat['invited_at'] ) ? null : (string) ( $seat['claimed_at'] ?? $seat['invited_at'] ),
			);
		}
		return $out;
	}

	/**
	 * Invitation preview (R0-09 §1.2 "Vorschau", R0-19 R-31): changes
	 * nothing; group name, owner display name, course titles, whether a
	 * seat is free, the deadline, whether the invitation is bound to
	 * another address (masked, E-81) and whether the viewer is already a
	 * member or has the courses from another source (§4.3 Doppelzugang).
	 *
	 * @param Actor  $actor Viewer (logged in).
	 * @param string $token Raw token.
	 * @return array{group: array{uid: string, name: string, status: string}, owner_name: string, course_titles: list<string>, course_ids: list<int>, seats_free: bool, bound_to_other_address: bool, invite_email_masked: string|null, expires_at: string|null, active: bool, already_member: bool, access_elsewhere: bool, personal: bool}
	 * @throws CourseException `ccap_invalid_token` (422) unknown/used/replaced, `ccap_invite_expired` (410).
	 */
	public function preview( Actor $actor, string $token ): array {
		$target = $this->relations->claim_target( $token );
		$group  = $target['group'];
		$seat   = $target['seat'];
		if ( null === $group ) {
			throw new CourseException( 'ccap_invalid_token', 'Unknown invitation.', 422 );
		}
		$id     = (int) $group['id'];
		$now    = $this->db->now();
		$member = $actor->user_id > 0 && null !== $this->active_seat( $id, $actor->user_id );
		if ( null !== $seat && ! $member ) {
			if ( 'invited' !== $seat['status'] ) {
				throw new CourseException( 'ccap_invalid_token', 'Invitation used.', 422 );
			}
			if ( null !== $seat['claim_expires_at'] && (string) $seat['claim_expires_at'] <= $now ) {
				throw new CourseException( 'ccap_invite_expired', 'Invitation expired.', 410 );
			}
		}
		$active  = 'active' === $group['status'] && ( null === $group['valid_until'] || (string) $group['valid_until'] > $now ) && ( null === $group['valid_from'] || (string) $group['valid_from'] <= $now );
		$account = $actor->user_id > 0 ? get_userdata( $actor->user_id ) : false;
		$email   = null === $seat ? '' : (string) ( $seat['invite_email'] ?? '' );
		$other   = '' !== $email && ( false === $account || 0 !== strcasecmp( (string) $account->user_email, $email ) );
		$ids     = array_map( 'intval', $this->db->wpdb()->get_col( $this->db->prepare( 'SELECT course_id FROM `' . $this->db->table( 'ccap_group_course' ) . '` WHERE group_id = %d ORDER BY id', $id ) ) );
		$titles  = array();
		$access  = false;
		foreach ( array_unique( $ids ) as $course ) {
			$post = $this->relations->post( $course, Relations::COURSE_TYPE );
			if ( null === $post ) {
				continue;
			}
			$titles[] = wp_strip_all_tags( $post->post_title );
			$access   = $access || ( $actor->user_id > 0 && 'ok' === $this->relations->effective_access( $actor->user_id, $course, 0, 'view' ) );
		}
		$owner = get_userdata( (int) $group['owner_user_id'] );
		return array(
			'group'                  => array(
				'uid'    => (string) $group['uid'],
				'name'   => (string) $group['name'],
				'status' => (string) $group['status'],
			),
			'owner_name'             => $owner instanceof \WP_User ? (string) $owner->display_name : '',
			'course_titles'          => $titles,
			'course_ids'             => array_values( array_unique( $ids ) ),
			// A personal invitation already holds its seat.
			'seats_free'             => null !== $seat || $this->occupied( $id ) < (int) $group['seats'],
			'bound_to_other_address' => $other && 'invited_address_only' === $group['claim_policy'],
			'invite_email_masked'    => $other ? self::mask_email( $email ) : null,
			'expires_at'             => null !== $seat ? ( null === $seat['claim_expires_at'] ? null : (string) $seat['claim_expires_at'] ) : ( null === $group['valid_until'] ? null : (string) $group['valid_until'] ),
			'active'                 => $active,
			'already_member'         => $member,
			'access_elsewhere'       => ! $member && $access,
			'personal'               => null !== $seat,
		);
	}

	/**
	 * Groups the actor owns (R0-11 L-18 "Meine Gruppen"), newest first.
	 *
	 * @param Actor $actor Owner.
	 * @return list<array{id: int, uid: string, name: string, status: string, seats_total: int, seats_used: int, invites_open: int, claim_policy: string, valid_until: string|null, has_link: bool, courses: list<string>}>
	 */
	public function owned( Actor $actor ): array {
		if ( $actor->user_id <= 0 ) {
			return array();
		}
		$out = array();
		foreach ( $this->db->rows( $this->db->prepare( 'SELECT * FROM `' . $this->db->table( 'ccap_group' ) . '` WHERE owner_user_id = %d ORDER BY id DESC LIMIT 200', $actor->user_id ) ) as $row ) {
			$id = (int) $row['id'];
			if ( ! $this->policy->decide( $actor, PolicyService::MANAGE_GROUP, array( $id ) )->allowed ) {
				continue;
			}
			$titles = array();
			foreach ( $this->db->wpdb()->get_col( $this->db->prepare( 'SELECT course_id FROM `' . $this->db->table( 'ccap_group_course' ) . '` WHERE group_id = %d ORDER BY id', $id ) ) as $course ) {
				$post = $this->relations->post( (int) $course, Relations::COURSE_TYPE );
				if ( null !== $post ) {
					$titles[] = wp_strip_all_tags( $post->post_title );
				}
			}
			$out[] = array(
				'id'           => $id,
				'uid'          => (string) $row['uid'],
				'name'         => (string) $row['name'],
				'status'       => (string) $row['status'],
				'seats_total'  => (int) $row['seats'],
				'seats_used'   => $this->occupied( $id ),
				'invites_open' => (int) $this->db->scalar( $this->db->prepare( 'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_seat' ) . "` WHERE group_id = %d AND status = 'invited' AND claim_expires_at > %s", $id, $this->db->now() ) ),
				'claim_policy' => (string) $row['claim_policy'],
				'valid_until'  => null === $row['valid_until'] ? null : (string) $row['valid_until'],
				'has_link'     => null !== $row['join_token_hash'],
				'courses'      => array_values( array_unique( $titles ) ),
			);
		}
		return $out;
	}

	/**
	 * Whether an address is already in a group: an active member with this
	 * account or invitation address, or an open invitation to it (R0-19
	 * R-35 `already_member`).
	 *
	 * @param int    $group_id Group.
	 * @param string $email    Address.
	 * @return bool
	 */
	public function has_address( int $group_id, string $email ): bool {
		$seats = $this->db->table( 'ccap_seat' );
		$users = $this->db->wpdb()->users;
		return null !== $this->db->scalar(
			$this->db->prepare(
				"SELECT 1 FROM `{$seats}` s LEFT JOIN `{$users}` u ON u.ID = s.user_id WHERE s.group_id = %d AND ((s.status = 'active' AND (s.invite_email = %s OR u.user_email = %s)) OR (s.status = 'invited' AND s.invite_email = %s AND s.claim_expires_at > %s)) LIMIT 1",
				$group_id,
				$email,
				$email,
				$email,
				$this->db->now()
			)
		);
	}

	/**
	 * Masked address (E-81): first character and domain.
	 *
	 * @param string $email Address.
	 * @return string
	 */
	public static function mask_email( string $email ): string {
		$at = strpos( $email, '@' );
		if ( false === $at || 0 === $at ) {
			return '***';
		}
		return mb_substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}

	/**
	 * Group row a manager (#11) may change.
	 *
	 * @param Actor $actor    Actor.
	 * @param int   $group_id Group.
	 * @return array<string, mixed>
	 * @throws CourseException `ccap_forbidden`, `ccap_not_found`, `ccap_conflict` (revoked).
	 */
	private function managed( Actor $actor, int $group_id ): array {
		if ( $actor->user_id <= 0 || ! user_can( $actor->user_id, 'ccap_manage_groups' ) ) {
			throw new CourseException( 'ccap_forbidden', 'Not allowed to manage groups.', 403 );
		}
		$group = $this->relations->row( 'ccap_group', $group_id );
		if ( null === $group ) {
			throw new CourseException( 'ccap_not_found', 'Unknown group.', 404 );
		}
		if ( 'revoked' === $group['status'] ) {
			throw new CourseException( 'ccap_conflict', 'An ended group is read-only.', 409 );
		}
		return $group;
	}

	/**
	 * Expire open invitations after their deadline (cron, SEAT-007).
	 *
	 * @return int Number of expired invitations.
	 */
	public function expire_invitations(): int {
		$table = $this->db->table( 'ccap_seat' );
		return $this->db->execute(
			$this->db->prepare( "UPDATE `{$table}` SET status = 'expired', claim_token_hash = NULL, updated_at = %s WHERE status = 'invited' AND claim_expires_at <= %s", $this->db->now(), $this->db->now() )
		);
	}

	/**
	 * Occupied seats: active + open, unexpired invitations (§4.1).
	 *
	 * @param int $group_id Group.
	 * @return int
	 */
	public function occupied( int $group_id ): int {
		return (int) $this->db->scalar(
			$this->db->prepare(
				'SELECT COUNT(*) FROM `' . $this->db->table( 'ccap_seat' ) . "` WHERE group_id = %d AND (status = 'active' OR (status = 'invited' AND claim_expires_at > %s))",
				$group_id,
				$this->db->now()
			)
		);
	}

	/**
	 * Group name without links, addresses or domains (GS:521-531 behaviour).
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function sanitize_name( string $name ): string {
		$name = sanitize_text_field( $name );
		$name = (string) preg_replace( '~(https?://\S+|www\.\S+|\S+@\S+|\b[\w-]+(\.[\w-]+)*\.[a-z]{2,}\b)~iu', '', $name );
		return mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', $name ) ), 0, 190 );
	}

	/**
	 * Seat grants for every group target (G-03, `group_seat`).
	 *
	 * @param array<string, mixed> $group   Group row.
	 * @param int                  $seat_id Seat.
	 * @param int                  $user_id Learner.
	 * @param Actor                $actor   Actor.
	 * @return void
	 */
	private function seat_grants( array $group, int $seat_id, int $user_id, Actor $actor ): void {
		$targets = $this->db->rows( $this->db->prepare( 'SELECT course_id, module_id, scopes FROM `' . $this->db->table( 'ccap_group_course' ) . '` WHERE group_id = %d ORDER BY id', (int) $group['id'] ) );
		foreach ( $targets as $target ) {
			$this->grants->create(
				array(
					'user_id'         => $user_id,
					'course_id'       => (int) $target['course_id'],
					'module_id'       => (int) $target['module_id'],
					'scopes'          => (string) $target['scopes'],
					'source_type'     => 'group_seat',
					'source_id'       => (string) $group['uid'],
					'source_line'     => (string) $seat_id,
					'source_revision' => (int) $group['source_revision'],
					'seat_id'         => $seat_id,
					'status'          => GrantTransitions::ACTIVE,
					'valid_until'     => $group['valid_until'],
					'renewal_mode'    => 'parallel',
				),
				$actor,
				'seat_claimed'
			);
		}
	}

	/**
	 * End a seat and revoke its grants.
	 *
	 * @param Actor                $actor  Actor.
	 * @param array<string, mixed> $seat   Seat row.
	 * @param string               $reason Reason code.
	 * @return void
	 */
	private function end_seat( Actor $actor, array $seat, string $reason ): void {
		$now = $this->db->now();
		$this->db->execute(
			$this->db->prepare(
				'UPDATE `' . $this->db->table( 'ccap_seat' ) . "` SET status = 'removed', removed_at = %s, invite_email = NULL, claim_token_hash = NULL, updated_at = %s WHERE id = %d AND status IN ('invited', 'active')",
				$now,
				$now,
				(int) $seat['id']
			)
		);
		$grants = $this->db->rows( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_grant' ) . "` WHERE seat_id = %d AND status NOT IN ('revoked', 'expired')", (int) $seat['id'] ) );
		foreach ( $grants as $grant ) {
			$this->grants->transition( $actor, (int) $grant['id'], GrantTransitions::REVOKED, $reason );
		}
		$this->db->transaction( fn() => $this->seat_event( (int) $seat['group_id'], (int) $seat['id'], 'seat.removed', $actor, (int) ( $seat['user_id'] ?? 0 ) ) );
		do_action( 'course_captain_policy_changed' );
	}

	/**
	 * Active seat of a user in a group.
	 *
	 * @param int $group_id Group.
	 * @param int $user_id  User.
	 * @return int|null
	 */
	private function active_seat( int $group_id, int $user_id ): ?int {
		$id = $this->db->scalar( $this->db->prepare( 'SELECT id FROM `' . $this->db->table( 'ccap_seat' ) . "` WHERE group_id = %d AND user_id = %d AND status = 'active'", $group_id, $user_id ) );
		return null === $id ? null : (int) $id;
	}

	/**
	 * Run under the group lock (≤ 2 s, else `ccap_group_busy`).
	 *
	 * @template T
	 * @param int           $group_id Group.
	 * @param callable(): T $work  Work.
	 * @return T
	 * @throws CourseException When the lock is busy.
	 */
	private function locked( int $group_id, callable $work ) {
		$lock  = NamedLock::for_key( $this->db, 'group-seats:' . $group_id );
		$state = $lock->acquire( self::LOCK_WAIT );
		if ( NamedLock::BUSY === $state ) {
			throw new CourseException( 'ccap_group_busy', 'Group is busy, please retry.', 409 );
		}
		try {
			// Without GET_LOCK the recount after insert keeps the limit (SEAT-006).
			return $work();
		} finally {
			$lock->release();
			do_action( 'course_captain_policy_changed' );
		}
	}

	/**
	 * New token: 256 bit, base64url with type prefix (§1.2).
	 *
	 * @param string $prefix cci|ccg.
	 * @return string
	 */
	private static function token( string $prefix ): string {
		return $prefix . '_' . rtrim( strtr( base64_encode( random_bytes( self::TOKEN_BYTES ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Token encoding.
	}

	/**
	 * Outbox event of a group.
	 *
	 * @param int    $group_id Group.
	 * @param string $type     Event type.
	 * @param Actor  $actor    Actor.
	 * @return void
	 */
	private function group_event( int $group_id, string $type, Actor $actor ): void {
		$group = (array) $this->relations->row( 'ccap_group', $group_id );
		Outbox::record(
			$this->db,
			$type,
			'seat',
			(string) $group['uid'],
			(int) $group['row_version'] + 1,
			array(
				'status' => $group['status'],
				'seats'  => (int) $group['seats'],
			),
			array(
				'actor_type' => $actor->is_system() ? 'system' : 'user',
				'actor_ref'  => $actor->key(),
			)
		);
	}

	/**
	 * Outbox event of a seat (never an address).
	 *
	 * @param int    $group_id Group.
	 * @param int    $seat_id  Seat.
	 * @param string $type     Event type.
	 * @param Actor  $actor    Actor.
	 * @param int    $user_id  Learner (0 before claim).
	 * @return void
	 */
	private function seat_event( int $group_id, int $seat_id, string $type, Actor $actor, int $user_id ): void {
		$group = (array) $this->relations->row( 'ccap_group', $group_id );
		Outbox::record(
			$this->db,
			$type,
			'seat',
			(string) $group['uid'],
			$seat_id,
			array( 'seat_id' => $seat_id ),
			array(
				'user_id'      => $user_id,
				'actor_type'   => $actor->is_system() ? 'system' : 'user',
				'actor_ref'    => $actor->key(),
				'privacy_tags' => array( 'personal_ref' ),
			)
		);
	}
}
