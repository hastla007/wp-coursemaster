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

	public const INVITE_DAYS  = 30;
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
		$name   = self::sanitize_name( $name );
		$policy = (string) ( $options['claim_policy'] ?? 'transferable' );
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
								'claim_expires_at' => $db->now( self::INVITE_DAYS * DAY_IN_SECONDS ),
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
