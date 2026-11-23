<?php
/**
 * Owner REST routes of the group area (R0-19 R-33…R-37, RG-12; R0-11
 * L-18/F3.7; UI-F1).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Access\GroupService;
use CourseCaptain\Admin\GroupsScreen;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * The same owner operations as the learning area page "My groups"
 * ({@see GroupsView}), all through {@see GroupService} and
 * {@see GroupsScreen::invite_list()}:
 *
 * - R-33 `GET /me/groups`: own groups with seats, members (masked
 *   addresses, never learning results, GR-7) and the invitation budget
 *   (F3.7: `invite_budget.next_at`/`retry_after` = wait time until the next
 *   invitation can be sent).
 * - R-34 `PUT /me/groups/{group}`: rename (`If-Match` optional).
 * - R-35 `POST /me/groups/{group}/invite`: invite addresses
 *   (`Idempotency-Key` required).
 * - R-36 `POST /me/groups/{group}/token`: new group link, the old one stops
 *   working (shown once).
 * - R-37 `DELETE /me/groups/{group}/members/{member}`: free a seat or
 *   withdraw an invitation (204).
 *
 * `{group}` is the group UID; groups the viewer does not own are 404
 * (RBAC-052). Suspended or ended groups are read-only (409
 * `ccap_group_not_active`, F3.5). `{member}` is the seat ID (no seat UID
 * yet, as in R-32, E-348).
 */
final class GroupOwnerRoutes {

	/** Idempotency scope of R-35. */
	private const IDEMPOTENCY_SCOPE = 'group_invite';

	/** Days an idempotency key is kept (R0-19 §2.1). */
	private const IDEMPOTENCY_DAYS = 7;

	private const GROUP = '(?P<uid>[0-9a-f-]{36})';

	/**
	 * Register the routes (called from {@see LearnerRoutes::routes()}).
	 *
	 * @return void
	 */
	public static function routes(): void {
		$routes = array(
			array( '/me/groups', 'GET', static fn( Actor $a ): array => self::index( $a ), array() ),
			array(
				'/me/groups/' . self::GROUP,
				'PUT',
				static function ( Actor $a, \WP_REST_Request $r ): \WP_REST_Response {
					$match = $r->get_header( 'if_match' );
					$match = null === $match || '' === trim( $match, ' "W/' ) ? null : (int) trim( $match, ' "W/' );
					$group = self::rename( $a, (string) $r['uid'], (string) $r->get_param( 'name' ), $match );
					$out   = new \WP_REST_Response( $group );
					$out->header( 'ETag', '"' . $group['row_version'] . '"' );
					return $out;
				},
				array(
					'name' => array(
						'type'      => 'string',
						'required'  => true,
						'maxLength' => 190,
					),
				),
			),
			array(
				'/me/groups/' . self::GROUP . '/invite',
				'POST',
				static fn( Actor $a, \WP_REST_Request $r ): array => self::invite( $a, (string) $r['uid'], $r->get_param( 'emails' ), (bool) $r->get_param( 'bind_to_address' ), self::idempotency_key( $r ) ),
				array(
					'emails'          => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'string' ),
						'maxItems' => 200,
					),
					'bind_to_address' => array( 'type' => 'boolean' ),
				),
			),
			array( '/me/groups/' . self::GROUP . '/token', 'POST', static fn( Actor $a, \WP_REST_Request $r ): array => self::token( $a, (string) $r['uid'] ), array() ),
			array(
				'/me/groups/' . self::GROUP . '/members/(?P<member>\d+)',
				'DELETE',
				static function ( Actor $a, \WP_REST_Request $r ): \WP_REST_Response {
					self::remove( $a, (string) $r['uid'], (int) $r['member'], $r->get_param( 'reason' ) );
					return new \WP_REST_Response( null, 204 );
				},
				array(
					'reason' => array(
						'type'      => 'string',
						'maxLength' => 500,
					),
				),
			),
		);
		foreach ( $routes as $route ) {
			list( $path, $method, $callback, $args ) = $route;
			register_rest_route(
				LearnerRoutes::NAMESPACE,
				$path,
				array(
					'methods'             => $method,
					'callback'            => static fn( \WP_REST_Request $r ) => self::run( $callback, $r ),
					'permission_callback' => array( LearnerRoutes::class, 'logged_in' ),
					'args'                => $args,
				)
			);
		}
	}

	/**
	 * R-33: own groups.
	 *
	 * @param Actor $actor Owner.
	 * @return list<array<string, mixed>>
	 */
	public static function index( Actor $actor ): array {
		$service = GroupService::current();
		$db      = Database::current();
		$out     = array();
		foreach ( $service->owned( $actor ) as $group ) {
			$row     = (array) $db->row( $db->prepare( 'SELECT row_version FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $group['id'] ) );
			$members = array();
			foreach ( $service->members( $actor, $group['id'] ) as $member ) {
				$members[] = array(
					'seat_id'      => $member['seat_id'],
					'display_name' => $member['name'],
					'email_masked' => '' === $member['address'] ? null : GroupService::mask_email( $member['address'] ),
					'status'       => $member['status'],
					'since'        => self::time( $member['since'] ),
				);
			}
			$link  = 'invited_address_only' !== $group['claim_policy'] && $group['has_link'];
			$out[] = array(
				'uid'                    => $group['uid'],
				'name'                   => $group['name'],
				'status'                 => $group['status'],
				'row_version'            => (int) ( $row['row_version'] ?? 0 ),
				'claim_policy'           => $group['claim_policy'],
				'valid_until'            => self::time( $group['valid_until'] ),
				'courses'                => $group['courses'],
				'seats'                  => array(
					'total'        => $group['seats_total'],
					'used'         => $group['seats_used'],
					'free'         => max( 0, $group['seats_total'] - $group['seats_used'] ),
					'invites_open' => $group['invites_open'],
				),
				'invite_link'            => $link,
				// A group link has no own deadline: it works until it is replaced or the group ends.
				'invite_link_expires_at' => $link ? self::time( $group['valid_until'] ) : null,
				'invite_budget'          => self::budget( $group['id'] ),
				'members'                => $members,
			);
		}
		return $out;
	}

	/**
	 * Invitation budget for the response (F3.7).
	 *
	 * @param int $group_id Group.
	 * @return array{limit: int, used: int, remaining: int, next_at: string|null, retry_after: int}
	 */
	public static function budget( int $group_id ): array {
		$status = NotificationService::current()->group_invite_status( $group_id );
		return array(
			'limit'       => $status['limit'],
			'used'        => $status['used'],
			'remaining'   => $status['remaining'],
			'next_at'     => self::time( $status['next_at'] ),
			'retry_after' => null === $status['next_at'] ? 0 : max( 1, (int) strtotime( $status['next_at'] . ' UTC' ) - time() ),
		);
	}

	/**
	 * R-34: rename.
	 *
	 * @param Actor    $actor    Owner.
	 * @param string   $uid      Group UID.
	 * @param string   $name     New name.
	 * @param int|null $if_match Expected row version.
	 * @return array{uid: string, name: string, row_version: int}
	 * @throws CourseException `ccap_not_found`, `ccap_group_not_active`, `ccap_version_conflict`, `ccap_invalid_param`.
	 */
	public static function rename( Actor $actor, string $uid, string $name, ?int $if_match ): array {
		$id      = self::writable( $actor, $uid );
		$db      = Database::current();
		$version = static fn(): int => (int) $db->scalar( $db->prepare( 'SELECT row_version FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $id ) );
		if ( null !== $if_match && $if_match !== $version() ) {
			throw new CourseException( 'ccap_version_conflict', 'The group was changed in the meantime.', 409 );
		}
		try {
			$stored = GroupService::current()->rename( $actor, $id, $name );
		} catch ( CourseException $e ) {
			if ( 'ccap_conflict' === $e->error_code ) {
				throw new CourseException( 'ccap_version_conflict', 'The group was changed in the meantime.', 409 );
			}
			throw $e;
		}
		return array(
			'uid'         => $uid,
			'name'        => $stored,
			'row_version' => $version(),
		);
	}

	/**
	 * R-35: invite addresses. The binding to the invited address follows
	 * the group setting "Only the invited email address" (E-28); the
	 * response names it in `bound_to_address`.
	 *
	 * @param Actor  $actor           Owner.
	 * @param string $uid             Group UID.
	 * @param mixed  $emails          Addresses.
	 * @param bool   $bind_to_address Requested binding (informational).
	 * @param string $key             Idempotency key.
	 * @return array<string, mixed> `results[{email_masked, status}]`, `bound_to_address`, `seats{free}`, `invite_budget` (a repeat returns the stored response).
	 * @throws CourseException `ccap_idempotency_key_required` 428, `ccap_group_not_active` 409, `ccap_group_full` 409, `ccap_rate_limited` 429, `ccap_invalid_param` 422.
	 */
	public static function invite( Actor $actor, string $uid, $emails, bool $bind_to_address, string $key ): array {
		unset( $bind_to_address );
		if ( 1 !== preg_match( '/^[0-9a-f-]{36}$/', $key ) ) {
			throw new CourseException( 'ccap_idempotency_key_required', 'Idempotency key required.', 428 );
		}
		$id   = self::writable( $actor, $uid );
		$list = is_array( $emails ) ? array_values( array_map( static fn( $e ): string => is_scalar( $e ) ? (string) $e : '', $emails ) ) : array();
		$hash = hash( 'sha256', $uid . "\n" . wp_json_encode( $list ) );
		$done = self::claim_key( $actor, $key, $hash );
		if ( null !== $done ) {
			return $done;
		}
		try {
			if ( array() === $list || count( $list ) > 200 ) {
				throw new CourseException( 'ccap_invalid_param', 'emails', 422 );
			}
			$service = GroupService::current();
			$db      = Database::current();
			$group   = (array) $db->row( $db->prepare( 'SELECT seats, claim_policy FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $id ) );
			if ( $service->occupied( $id ) >= (int) $group['seats'] ) {
				throw new CourseException( 'ccap_group_full', 'No free place.', 409 );
			}
			$budget = self::budget( $id );
			if ( $budget['remaining'] <= 0 ) {
				throw new CourseException(
					'ccap_rate_limited',
					'Invitation budget used up.',
					429,
					array(
						'bucket'      => 'group_invite',
						'retry_after' => $budget['retry_after'],
						'next_at'     => $budget['next_at'],
					)
				);
			}
			$results = array();
			foreach ( GroupsScreen::invite_list( $actor, $id, $list ) as $row ) {
				$results[] = array(
					'email_masked' => is_email( $row['email'] ) ? GroupService::mask_email( $row['email'] ) : '***',
					// The invitation budget is a limit like a full group for the client (R0-19 status set).
					'status'       => 'budget' === $row['status'] ? 'limited' : $row['status'],
				);
			}
			$response = array(
				'results'          => $results,
				'bound_to_address' => 'invited_address_only' === (string) $group['claim_policy'],
				'seats'            => array( 'free' => max( 0, (int) $group['seats'] - $service->occupied( $id ) ) ),
				'invite_budget'    => self::budget( $id ),
			);
		} catch ( CourseException $e ) {
			self::release_key( $actor, $key );
			throw $e;
		}
		self::complete_key( $actor, $key, $response, $uid );
		return $response;
	}

	/**
	 * R-36: new group link.
	 *
	 * @param Actor  $actor Owner.
	 * @param string $uid   Group UID.
	 * @return array{invite_url: string, expires_at: string|null}
	 * @throws CourseException `ccap_group_not_active` 409; 409 `ccap_invalid_param` when only invited addresses may join.
	 */
	public static function token( Actor $actor, string $uid ): array {
		$id    = self::writable( $actor, $uid );
		$token = GroupService::current()->rotate_link( $actor, $id );
		$db    = Database::current();
		$until = $db->scalar( $db->prepare( 'SELECT valid_until FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $id ) );
		return array(
			'invite_url' => Invitation::url( $token ),
			'expires_at' => self::time( null === $until ? null : (string) $until ),
		);
	}

	/**
	 * R-37: free a seat (member) or withdraw an invitation.
	 *
	 * @param Actor  $actor  Owner.
	 * @param string $uid    Group UID.
	 * @param int    $seat   Seat ID.
	 * @param mixed  $reason Optional note (≤ 500 characters; not stored, the audit keeps the reason code).
	 * @return void
	 * @throws CourseException `ccap_not_found` for seats of other groups or ended seats.
	 */
	public static function remove( Actor $actor, string $uid, int $seat, $reason = null ): void {
		unset( $reason );
		$id     = self::writable( $actor, $uid );
		$db     = Database::current();
		$status = (string) $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_seat' ) . "` WHERE id = %d AND group_id = %d AND status IN ('active', 'invited')", $seat, $id ) );
		if ( '' === $status ) {
			throw new CourseException( 'ccap_not_found', 'Unknown member.', 404 );
		}
		GroupService::current()->remove_seat( $actor, $id, $seat, 'active' === $status ? 'group_member_removed' : 'invitation_withdrawn' );
	}

	/**
	 * Own group that may be changed now.
	 *
	 * @param Actor  $actor Owner.
	 * @param string $uid   Group UID.
	 * @return int
	 * @throws CourseException 404 foreign/unknown, 409 `ccap_group_not_active`.
	 */
	private static function writable( Actor $actor, string $uid ): int {
		$id = GroupsView::group_id( $actor, $uid );
		$db = Database::current();
		if ( 'active' !== (string) $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $id ) ) ) {
			throw new CourseException( 'ccap_group_not_active', 'Group not active.', 409 );
		}
		return $id;
	}

	/**
	 * SQL time (UTC) as RFC 3339.
	 *
	 * @param string|null $time `Y-m-d H:i:s` (UTC).
	 * @return string|null
	 */
	private static function time( ?string $time ): ?string {
		if ( null === $time || '' === $time ) {
			return null;
		}
		$stamp = strtotime( $time . ' UTC' );
		return false === $stamp ? null : gmdate( 'Y-m-d\TH:i:s\Z', $stamp );
	}

	/**
	 * Idempotency key from the header `Idempotency-Key` or the parameter.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string
	 */
	private static function idempotency_key( \WP_REST_Request $request ): string {
		$header = trim( (string) $request->get_header( 'idempotency_key' ) );
		return '' !== $header ? $header : (string) $request->get_param( 'idempotency_key' );
	}

	/**
	 * Claim a key: null for a new request, the stored response for a repeat.
	 *
	 * @param Actor  $actor Owner.
	 * @param string $key   Key.
	 * @param string $hash  Request hash.
	 * @return array<string, mixed>|null
	 * @throws CourseException `ccap_idempotency_conflict`, `ccap_request_in_progress`.
	 */
	private static function claim_key( Actor $actor, string $key, string $hash ): ?array {
		$db    = Database::current();
		$table = $db->table( 'ccap_request_idempotency' );
		// An entry "in progress" for more than 300 s is orphaned (R0-19 §2.1).
		$db->execute( $db->prepare( "DELETE FROM `{$table}` WHERE user_id = %d AND scope = %s AND idem_key = %s AND status_code = 0 AND created_at < %s", $actor->user_id, self::IDEMPOTENCY_SCOPE, $key, $db->now( -300 ) ) );
		$inserted = $db->execute( $db->prepare( "INSERT IGNORE INTO `{$table}` (user_id, scope, idem_key, request_hash, status_code, created_at, expires_at) VALUES (%d, %s, %s, %s, 0, %s, %s)", $actor->user_id, self::IDEMPOTENCY_SCOPE, $key, $hash, $db->now(), $db->now( self::IDEMPOTENCY_DAYS * DAY_IN_SECONDS ) ) );
		if ( 1 === $inserted ) {
			return null;
		}
		$row = (array) $db->row( $db->prepare( "SELECT request_hash, status_code, response FROM `{$table}` WHERE user_id = %d AND scope = %s AND idem_key = %s", $actor->user_id, self::IDEMPOTENCY_SCOPE, $key ) );
		if ( ! hash_equals( (string) ( $row['request_hash'] ?? '' ), $hash ) ) {
			throw new CourseException( 'ccap_idempotency_conflict', 'The request key was used for another request.', 409 );
		}
		$response = json_decode( (string) ( $row['response'] ?? '' ), true );
		if ( 200 !== (int) ( $row['status_code'] ?? 0 ) || ! is_array( $response ) ) {
			throw new CourseException( 'ccap_request_in_progress', 'The request is still in progress.', 409, array( 'retry_after' => 2 ) );
		}
		return $response;
	}

	/**
	 * Store the response of a key.
	 *
	 * @param Actor                $actor    Owner.
	 * @param string               $key      Key.
	 * @param array<string, mixed> $response Response.
	 * @param string               $uid      Group UID.
	 * @return void
	 */
	private static function complete_key( Actor $actor, string $key, array $response, string $uid ): void {
		$db = Database::current();
		$db->execute( $db->prepare( 'UPDATE `' . $db->table( 'ccap_request_idempotency' ) . '` SET status_code = 200, response = %s, resource = %s WHERE user_id = %d AND scope = %s AND idem_key = %s', (string) wp_json_encode( $response ), 'group:' . $uid, $actor->user_id, self::IDEMPOTENCY_SCOPE, $key ) );
	}

	/**
	 * Release a key after a rejected request (the client may retry with it).
	 *
	 * @param Actor  $actor Owner.
	 * @param string $key   Key.
	 * @return void
	 */
	private static function release_key( Actor $actor, string $key ): void {
		$db = Database::current();
		$db->execute( $db->prepare( 'DELETE FROM `' . $db->table( 'ccap_request_idempotency' ) . '` WHERE user_id = %d AND scope = %s AND idem_key = %s AND status_code = 0', $actor->user_id, self::IDEMPOTENCY_SCOPE, $key ) );
	}

	/**
	 * Run and map errors; responses are never cached.
	 *
	 * @param callable         $callback fn(Actor, WP_REST_Request).
	 * @param \WP_REST_Request $request  Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private static function run( callable $callback, \WP_REST_Request $request ) {
		try {
			$result   = $callback( Actor::user( get_current_user_id() ), $request );
			$response = $result instanceof \WP_REST_Response ? $result : new \WP_REST_Response( $result );
			$response->header( 'Cache-Control', 'private, no-store' );
			return $response;
		} catch ( CourseException $e ) {
			// `retry_after` becomes the `Retry-After` header in the error format (RestContract).
			return new \WP_Error( $e->error_code, $e->getMessage(), array( 'status' => $e->status ) + $e->data );
		}
	}
}
