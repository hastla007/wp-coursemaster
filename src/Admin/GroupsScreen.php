<?php
/**
 * Admin screen A-10 "Groups" (R0-29 §1.2 GF-01…GF-15, R1-35).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Access\GroupService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Policy\Relations;
use CourseCaptain\Settings\EffectiveSettings;

defined( 'ABSPATH' ) || exit;

/**
 * List and detail of groups for `ccap_manage_groups` (#11). Every action is
 * an admin-post form (works without JavaScript) with one nonce per
 * operation, routed through {@see GroupService} / {@see NotificationService}.
 * Seat reduction shows a preview of the affected seats before the
 * confirmed change (GF-04); a new group link is shown once (E-112). The
 * member list never shows learning results (GR-7) and masks addresses
 * (E-81). Creating a group needs `enrollment.groups_enabled` for every
 * target course (GF-01, course override possible).
 */
final class GroupsScreen {

	public const SLUG   = 'course-captain-groups';
	public const ACTION = 'ccap_group_admin';
	public const CAP    = 'ccap_manage_groups';

	/**
	 * Operations.
	 *
	 * @var list<string>
	 */
	public const OPS = array( 'create', 'seats', 'validity', 'status', 'owner', 'policy', 'rename', 'invite', 'resend', 'withdraw', 'remove', 'link' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'admin_menu',
			static function (): void {
				add_submenu_page( EditorScreens::MENU, __( 'Groups', 'course-captain' ), __( 'Groups', 'course-captain' ), self::CAP, self::SLUG, array( self::class, 'render' ) );
			},
			20
		);
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Page output.
	 *
	 * @return void
	 */
	public static function render(): void {
		try {
			echo self::html( $_GET, get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Security.NonceVerification.Recommended -- Escaped while building; read-only view parameters.
		} catch ( CourseException $e ) {
			wp_die( esc_html__( 'You are not allowed to manage groups.', 'course-captain' ), '', array( 'response' => (int) $e->status ) );
		}
	}

	/**
	 * Admin-post entry point.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$out  = self::process( wp_unslash( $_POST ), get_current_user_id() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in process().
		$args = array(
			'page'              => self::SLUG,
			'ccap_group_op'     => $out['op'],
			'ccap_group_result' => $out['result'],
			'ccap_group_count'  => $out['count'],
		);
		if ( $out['group'] > 0 ) {
			$args['group'] = $out['group'];
		}
		if ( '' !== $out['token'] ) {
			// E-112: shown once, never in a URL.
			set_transient( 'ccap_group_link_' . get_current_user_id(), $out['token'], 300 );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Testable core of the handler.
	 *
	 * @param array<string, mixed> $post    Unslashed POST data.
	 * @param int                  $user_id Current user.
	 * @return array{op: string, group: int, result: string, count: int, token: string}
	 * @throws CourseException Never; domain errors become the result code.
	 */
	public static function process( array $post, int $user_id ): array {
		$op    = isset( $post['op'] ) ? sanitize_key( (string) $post['op'] ) : '';
		$group = absint( $post['group'] ?? 0 );
		$out   = array(
			'op'     => $op,
			'group'  => $group,
			'result' => 'ok',
			'count'  => 0,
			'token'  => '',
		);
		if ( ! in_array( $op, self::OPS, true ) ) {
			$out['result'] = 'ccap_not_found';
			return $out;
		}
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), 'ccap_group_' . $op . '_' . $group ) ) {
			$out['result'] = 'ccap_invalid_nonce';
			return $out;
		}
		$actor   = Actor::user( $user_id );
		$service = GroupService::current();
		$text    = static fn( string $key ): string => isset( $post[ $key ] ) ? sanitize_text_field( (string) $post[ $key ] ) : '';
		try {
			switch ( $op ) {
				case 'create':
					$out['group'] = self::create( $actor, $post );
					break;
				case 'seats':
					$seats = absint( $post['seats'] ?? 0 );
					if ( $seats < 1 ) {
						throw new CourseException( 'ccap_invalid_param', 'seats', 422 );
					}
					$affected = $service->reduce_seats( $actor, $group, $seats, true );
					if ( array() !== $affected && empty( $post['confirm'] ) ) {
						$out['result'] = 'ccap_confirmation_required';
						$out['count']  = count( $affected );
						break;
					}
					$out['count'] = count( $service->reduce_seats( $actor, $group, $seats ) );
					break;
				case 'validity':
					$out['count'] = $service->set_validity( $actor, $group, self::end_of_day( $text( 'valid_until' ), ! empty( $post['unlimited_confirm'] ) ) );
					break;
				case 'status':
					if ( 'revoked' === $text( 'status' ) && empty( $post['confirm'] ) ) {
						$out['result'] = 'ccap_confirmation_required';
						break;
					}
					$service->set_status( $actor, $group, $text( 'status' ) );
					break;
				case 'owner':
					$service->change_owner( $actor, $group, self::user_id( $text( 'owner' ) ), isset( $post['reason'] ) ? (string) $post['reason'] : '' );
					break;
				case 'policy':
					$service->set_claim_policy( $actor, $group, $text( 'claim_policy' ) );
					break;
				case 'rename':
					$service->rename( $actor, $group, $text( 'name' ) );
					break;
				case 'invite':
					$result        = self::invite( $actor, $group, isset( $post['emails'] ) ? (string) $post['emails'] : '' );
					$out['count']  = $result['invited'];
					$out['result'] = 0 === $result['invalid'] && 0 === $result['limited'] ? 'ok' : 'partial_' . $result['invalid'] . '_' . $result['limited'];
					break;
				case 'resend':
					NotificationService::current()->resend_invitation( $actor, absint( $post['seat'] ?? 0 ) );
					break;
				case 'withdraw':
				case 'remove':
					if ( empty( $post['confirm'] ) ) {
						$out['result'] = 'ccap_confirmation_required';
						break;
					}
					$service->remove_seat( $actor, $group, absint( $post['seat'] ?? 0 ), 'withdraw' === $op ? 'invitation_withdrawn' : 'group_member_removed' );
					break;
				case 'link':
					$out['token'] = $service->rotate_link( $actor, $group );
					break;
			}
		} catch ( CourseException $e ) {
			$out['result'] = $e->error_code;
		}
		return $out;
	}

	/**
	 * Create a group (GF-02) after the GF-01 check.
	 *
	 * @param Actor                $actor Manager.
	 * @param array<string, mixed> $post  POST data.
	 * @return int Group ID.
	 * @throws CourseException `ccap_groups_disabled`, `ccap_confirmation_required`, `ccap_invalid_param`.
	 */
	private static function create( Actor $actor, array $post ): int {
		$courses = array_values( array_filter( array_map( 'absint', (array) ( $post['courses'] ?? array() ) ) ) );
		foreach ( $courses as $course ) {
			if ( true !== EffectiveSettings::for_course( 'enrollment.groups_enabled', $course ) ) {
				throw new CourseException( 'ccap_groups_disabled', 'Groups are switched off for this course.', 409 );
			}
		}
		$until = self::end_of_day( isset( $post['valid_until'] ) ? sanitize_text_field( (string) $post['valid_until'] ) : '', ! empty( $post['unlimited_confirm'] ) );
		$key   = isset( $post['request_key'] ) && wp_is_uuid( (string) $post['request_key'] ) ? (string) $post['request_key'] : wp_generate_uuid4();
		return GroupService::current()->create(
			$actor,
			self::user_id( isset( $post['owner'] ) ? sanitize_text_field( (string) $post['owner'] ) : '' ),
			isset( $post['name'] ) ? (string) $post['name'] : '',
			absint( $post['seats'] ?? 0 ),
			array_map( static fn( int $c ): array => array( 'course_id' => $c ), $courses ),
			$key,
			array(
				'valid_until'  => $until,
				'claim_policy' => isset( $post['claim_policy'] ) ? sanitize_key( (string) $post['claim_policy'] ) : 'transferable',
				'reason'       => isset( $post['reason'] ) ? sanitize_textarea_field( (string) $post['reason'] ) : '',
			)
		);
	}

	/**
	 * Invite up to 200 addresses (GF-09). Addresses that are already in the
	 * group and addresses beyond the invitation budget (F3.7) count as
	 * `limited` here (no seat taken, no mail).
	 *
	 * @param Actor  $actor  Manager.
	 * @param int    $group  Group.
	 * @param string $emails One address per line or separated by commas.
	 * @return array{invited: int, invalid: int, limited: int, already_member: int, budget: int}
	 * @throws CourseException `ccap_invalid_param` above 200 addresses.
	 */
	public static function invite( Actor $actor, int $group, string $emails ): array {
		$parts = preg_split( '/[\s,;]+/', $emails );
		$list  = array_values( array_filter( array_map( 'trim', false === $parts ? array() : $parts ) ) );
		$out   = array(
			'invited'        => 0,
			'invalid'        => 0,
			'limited'        => 0,
			'already_member' => 0,
			'budget'         => 0,
		);
		foreach ( self::invite_list( $actor, $group, $list ) as $row ) {
			switch ( $row['status'] ) {
				case 'invited':
					++$out['invited'];
					break;
				case 'invalid':
					++$out['invalid'];
					break;
				case 'already_member':
					++$out['already_member'];
					break;
				case 'budget':
					++$out['budget'];
					break;
				default:
					++$out['limited'];
			}
		}
		$out['limited'] += $out['already_member'] + $out['budget'];
		return $out;
	}

	/**
	 * Invite addresses one by one (GF-09, R0-19 R-35): `invalid` address,
	 * `already_member` (an active seat or an open invitation of the group
	 * with this address), `limited` (no free seat), `budget` (invitation
	 * budget of the group used up, F3.7: the mail would not be sent), or
	 * `invited`. Duplicates in the list are handled once.
	 *
	 * @param Actor $actor  Owner (M-15) or manager.
	 * @param int   $group  Group.
	 * @param array $emails Addresses (1…200).
	 * @phpstan-param list<string> $emails
	 * @return list<array{email: string, status: string}>
	 * @throws CourseException `ccap_invalid_param` for an empty list or more than 200 addresses; rights of {@see GroupService::invite()}.
	 */
	public static function invite_list( Actor $actor, int $group, array $emails ): array {
		if ( array() === $emails || count( $emails ) > 200 ) {
			throw new CourseException( 'ccap_invalid_param', 'emails', 422 );
		}
		$service = GroupService::current();
		$budget  = null;
		$out     = array();
		$unique  = array();
		foreach ( $emails as $email ) {
			$email                            = trim( (string) $email );
			$unique[ strtolower( $email ) ] ??= $email;
		}
		foreach ( $unique as $email ) {
			if ( ! is_email( $email ) ) {
				$out[] = array(
					'email'  => $email,
					'status' => 'invalid',
				);
				continue;
			}
			if ( $service->has_address( $group, $email ) ) {
				$out[] = array(
					'email'  => $email,
					'status' => 'already_member',
				);
				continue;
			}
			$budget ??= NotificationService::current()->group_invite_status( $group )['remaining'];
			if ( $budget <= 0 ) {
				$out[] = array(
					'email'  => $email,
					'status' => 'budget',
				);
				continue;
			}
			$seat = $service->invite( $actor, $group, $email );
			if ( null !== $seat ) {
				--$budget;
			}
			$out[] = array(
				'email'  => $email,
				'status' => null === $seat ? 'limited' : 'invited',
			);
		}
		return $out;
	}

	/**
	 * Date `Y-m-d` → end of that day (UTC) or null with confirmation.
	 *
	 * @param string $date      Date or empty.
	 * @param bool   $unlimited Unlimited confirmed (GRP-003).
	 * @return string|null
	 * @throws CourseException `ccap_confirmation_required`, `ccap_invalid_param`.
	 */
	private static function end_of_day( string $date, bool $unlimited ): ?string {
		if ( '' === $date ) {
			if ( ! $unlimited ) {
				throw new CourseException( 'ccap_confirmation_required', 'unlimited', 422 );
			}
			return null;
		}
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			throw new CourseException( 'ccap_invalid_param', 'valid_until', 422 );
		}
		return $date . ' 23:59:59';
	}

	/**
	 * Account from ID, login or address.
	 *
	 * @param string $value Input.
	 * @return int 0 when unknown.
	 */
	private static function user_id( string $value ): int {
		if ( '' === $value ) {
			return 0;
		}
		$user = ctype_digit( $value ) ? get_userdata( (int) $value ) : ( is_email( $value ) ? get_user_by( 'email', $value ) : get_user_by( 'login', $value ) );
		return $user instanceof \WP_User ? (int) $user->ID : 0;
	}

	/**
	 * Page HTML.
	 *
	 * @param array<string, mixed> $request GET.
	 * @param int                  $user_id Viewer.
	 * @return string
	 * @throws CourseException Without #11.
	 */
	public static function html( array $request, int $user_id ): string {
		$actor = Actor::user( $user_id );
		$group = absint( $request['group'] ?? 0 );
		$html  = '<div class="wrap"><h1>' . esc_html__( 'Groups', 'course-captain' ) . '</h1>' . self::notice( $request );
		if ( $group > 0 ) {
			return $html . self::detail( $actor, $group, $request ) . '</div>';
		}
		$rows  = GroupService::current()->overview( $actor, isset( $request['status'] ) ? sanitize_key( (string) $request['status'] ) : '' );
		$html .= '<table class="widefat striped"><caption>' . esc_html__( 'All groups', 'course-captain' ) . '</caption><thead><tr>';
		foreach ( array( __( 'Name', 'course-captain' ), __( 'Owner', 'course-captain' ), __( 'Status', 'course-captain' ), __( 'Seats used / total', 'course-captain' ), __( 'Open invitations', 'course-captain' ), __( 'Seats freed so far', 'course-captain' ), __( 'Valid until', 'course-captain' ) ) as $label ) {
			$html .= '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		if ( array() === $rows ) {
			$html .= '<tr><td colspan="7">' . esc_html__( 'No groups yet.', 'course-captain' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$url   = add_query_arg(
				array(
					'page'  => self::SLUG,
					'group' => $row['id'],
				),
				admin_url( 'admin.php' )
			);
			$html .= '<tr><th scope="row"><a href="' . esc_url( $url ) . '">' . esc_html( $row['name'] ) . '</a></th><td>' . esc_html( $row['owner'] ) . '</td><td>' . esc_html( self::status_label( $row['status'] ) ) . '</td><td>' . (int) $row['seats_used'] . ' / ' . (int) $row['seats_total'] . '</td><td>' . (int) $row['invites_open'] . '</td><td>' . (int) $row['seats_ended'] . '</td><td>' . esc_html( null === $row['valid_until'] ? __( 'unlimited', 'course-captain' ) : $row['valid_until'] ) . '</td></tr>';
		}
		return $html . '</tbody></table>' . self::create_form() . '</div>';
	}

	/**
	 * Group detail.
	 *
	 * @param Actor                $actor   Viewer.
	 * @param int                  $group   Group.
	 * @param array<string, mixed> $request GET.
	 * @return string
	 */
	private static function detail( Actor $actor, int $group, array $request ): string {
		$rows = array_values( array_filter( GroupService::current()->overview( $actor ), static fn( array $r ): bool => $r['id'] === $group ) );
		if ( array() === $rows ) {
			return '<p>' . esc_html__( 'This group does not exist.', 'course-captain' ) . '</p>';
		}
		$row      = $rows[0];
		$readonly = 'revoked' === $row['status'];
		$titles   = array_map( static fn( int $c ): string => (string) get_the_title( $c ), $row['courses'] );
		$html     = '<h2>' . esc_html( $row['name'] ) . '</h2><dl>';
		$html    .= '<dt>' . esc_html__( 'Status', 'course-captain' ) . '</dt><dd>' . esc_html( self::status_label( $row['status'] ) ) . '</dd>';
		$html    .= '<dt>' . esc_html__( 'Owner', 'course-captain' ) . '</dt><dd>' . esc_html( $row['owner'] ) . '</dd>';
		$html    .= '<dt>' . esc_html__( 'Courses', 'course-captain' ) . '</dt><dd>' . esc_html( implode( ', ', $titles ) ) . '</dd>';
		$html    .= '<dt>' . esc_html__( 'Seats', 'course-captain' ) . '</dt><dd>' . esc_html( sprintf( /* translators: 1: used, 2: total, 3: open invitations */ __( '%1$d of %2$d used (%3$d open invitations)', 'course-captain' ), $row['seats_used'], $row['seats_total'], $row['invites_open'] ) ) . '</dd>';
		$html    .= '<dt>' . esc_html__( 'Valid until', 'course-captain' ) . '</dt><dd>' . esc_html( null === $row['valid_until'] ? __( 'unlimited', 'course-captain' ) : $row['valid_until'] ) . '</dd>';
		$html    .= '<dt>' . esc_html__( 'Who may redeem an invitation', 'course-captain' ) . '</dt><dd>' . esc_html( 'invited_address_only' === $row['claim_policy'] ? __( 'only the invited address', 'course-captain' ) : __( 'anyone with the link', 'course-captain' ) ) . '</dd></dl>';
		$token    = get_transient( 'ccap_group_link_' . $actor->user_id );
		if ( is_string( $token ) && '' !== $token ) {
			delete_transient( 'ccap_group_link_' . $actor->user_id );
			$link  = add_query_arg( 'ccap_claim', $token, \CourseCaptain\Learner\LearningArea::url() );
			$html .= '<div class="notice notice-info" role="status"><p><label for="ccap-group-link">' . esc_html__( 'New group link (shown only now, copy it):', 'course-captain' ) . '</label> <input id="ccap-group-link" type="text" readonly class="large-text" value="' . esc_attr( $link ) . '"></p></div>';
		}
		$html .= '<table class="widefat striped"><caption>' . esc_html__( 'Members and invitations', 'course-captain' ) . '</caption><thead><tr><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Name', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Address', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Since', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Actions', 'course-captain' ) . '</th></tr></thead><tbody>';
		foreach ( GroupService::current()->members( $actor, $group ) as $seat ) {
			$actions = '';
			if ( ! $readonly ) {
				if ( 'invited' === $seat['status'] ) {
					$actions .= self::form( 'resend', $group, '<input type="hidden" name="seat" value="' . (int) $seat['seat_id'] . '">', __( 'Send again', 'course-captain' ) );
				}
				$op       = 'active' === $seat['status'] ? 'remove' : 'withdraw';
				$actions .= self::form( $op, $group, '<input type="hidden" name="seat" value="' . (int) $seat['seat_id'] . '"><label><input type="checkbox" name="confirm" value="1"> ' . esc_html( 'remove' === $op ? __( 'Confirm: remove member (their learning history stays)', 'course-captain' ) : __( 'Confirm: withdraw invitation', 'course-captain' ) ) . '</label> ', 'remove' === $op ? __( 'Remove', 'course-captain' ) : __( 'Withdraw', 'course-captain' ) );
			}
			$html .= '<tr><td>' . esc_html( self::seat_label( $seat['status'] ) ) . '</td><td>' . esc_html( $seat['name'] ) . '</td><td>' . esc_html( $seat['address'] ) . '</td><td>' . esc_html( (string) $seat['since'] ) . '</td><td>' . $actions . '</td></tr>';
		}
		$html .= '</tbody></table>';
		if ( $readonly ) {
			return $html . '<p>' . esc_html__( 'This group has ended and can only be viewed.', 'course-captain' ) . '</p>';
		}
		$preview = absint( $request['preview_seats'] ?? 0 );
		$html   .= '<h3>' . esc_html__( 'Change the group', 'course-captain' ) . '</h3>';
		$html   .= self::form( 'invite', $group, '<label for="ccap-invite">' . esc_html__( 'Email addresses (one per line, at most 200)', 'course-captain' ) . '</label><br><textarea id="ccap-invite" name="emails" rows="4" class="large-text"></textarea><br>', __( 'Invite', 'course-captain' ) );
		$html   .= self::form( 'seats', $group, '<label for="ccap-seats">' . esc_html__( 'Number of seats', 'course-captain' ) . '</label> <input id="ccap-seats" type="number" min="1" name="seats" value="' . (int) ( $preview > 0 ? $preview : $row['seats_total'] ) . '"> ' . self::seat_preview( $actor, $group, $preview ), __( 'Save seats', 'course-captain' ) );
		$html   .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="group" value="' . (int) $group . '"><label for="ccap-preview-seats">' . esc_html__( 'Preview a reduction to', 'course-captain' ) . '</label> <input id="ccap-preview-seats" type="number" min="1" name="preview_seats" value="' . (int) $row['seats_total'] . '"> <button class="button" type="submit">' . esc_html__( 'Show affected seats', 'course-captain' ) . '</button></form>';
		$html   .= self::form( 'validity', $group, '<label for="ccap-valid">' . esc_html__( 'Valid until (date, empty = unlimited)', 'course-captain' ) . '</label> <input id="ccap-valid" type="date" name="valid_until" value="' . esc_attr( null === $row['valid_until'] ? '' : substr( $row['valid_until'], 0, 10 ) ) . '"> <label><input type="checkbox" name="unlimited_confirm" value="1"> ' . esc_html__( 'Confirm: unlimited', 'course-captain' ) . '</label> ', __( 'Save validity', 'course-captain' ) );
		$html   .= self::form( 'status', $group, '<label for="ccap-status">' . esc_html__( 'Status', 'course-captain' ) . '</label> <select id="ccap-status" name="status"><option value="active"' . selected( $row['status'], 'active', false ) . '>' . esc_html__( 'active', 'course-captain' ) . '</option><option value="suspended"' . selected( $row['status'], 'suspended', false ) . '>' . esc_html__( 'suspended', 'course-captain' ) . '</option><option value="revoked">' . esc_html__( 'ended (final)', 'course-captain' ) . '</option></select> <label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Confirm: ending is final and removes access of all members', 'course-captain' ) . '</label> ', __( 'Save status', 'course-captain' ) );
		$html   .= self::form( 'owner', $group, '<label for="ccap-owner">' . esc_html__( 'New owner (user name, email or ID)', 'course-captain' ) . '</label> <input id="ccap-owner" type="text" name="owner"> <label for="ccap-owner-reason">' . esc_html__( 'Reason (required)', 'course-captain' ) . '</label> <input id="ccap-owner-reason" type="text" name="reason" required> ', __( 'Change owner', 'course-captain' ) );
		$html   .= self::form( 'policy', $group, '<label for="ccap-policy">' . esc_html__( 'Who may redeem an invitation', 'course-captain' ) . '</label> <select id="ccap-policy" name="claim_policy"><option value="transferable"' . selected( $row['claim_policy'], 'transferable', false ) . '>' . esc_html__( 'anyone with the link', 'course-captain' ) . '</option><option value="invited_address_only"' . selected( $row['claim_policy'], 'invited_address_only', false ) . '>' . esc_html__( 'only the invited address (disables the group link)', 'course-captain' ) . '</option></select> ', __( 'Save', 'course-captain' ) );
		$html   .= self::form( 'rename', $group, '<label for="ccap-name">' . esc_html__( 'Name', 'course-captain' ) . '</label> <input id="ccap-name" type="text" name="name" value="' . esc_attr( $row['name'] ) . '"> ', __( 'Rename', 'course-captain' ) );
		if ( 'transferable' === $row['claim_policy'] ) {
			$html .= self::form( 'link', $group, '<span>' . esc_html__( 'A new link replaces the old one at once.', 'course-captain' ) . '</span> ', __( 'Create new group link', 'course-captain' ) );
		}
		return $html;
	}

	/**
	 * Preview of a seat reduction (GF-04): affected seats, nothing written.
	 *
	 * @param Actor $actor   Manager.
	 * @param int   $group   Group.
	 * @param int   $seats   Target seats (0 = none).
	 * @return string
	 */
	private static function seat_preview( Actor $actor, int $group, int $seats ): string {
		if ( $seats < 1 ) {
			return '';
		}
		try {
			$affected = GroupService::current()->reduce_seats( $actor, $group, $seats, true );
		} catch ( CourseException $e ) {
			return '';
		}
		if ( array() === $affected ) {
			return '<p role="status">' . esc_html__( 'No member or invitation is affected.', 'course-captain' ) . '</p>';
		}
		$members = array_column( GroupService::current()->members( $actor, $group ), null, 'seat_id' );
		$html    = '<div role="status"><p>' . esc_html( sprintf( /* translators: %d: affected seats */ _n( 'This affects %d seat:', 'This affects %d seats:', count( $affected ), 'course-captain' ), count( $affected ) ) ) . '</p><ul>';
		foreach ( $affected as $seat ) {
			$m     = $members[ $seat ] ?? null;
			$html .= '<li>' . esc_html( null === $m ? (string) $seat : self::seat_label( $m['status'] ) . ': ' . ( '' !== $m['name'] ? $m['name'] : $m['address'] ) ) . '</li>';
		}
		return $html . '</ul><label><input type="checkbox" name="confirm" value="1"> ' . esc_html__( 'Confirm: remove these seats', 'course-captain' ) . '</label></div>';
	}

	/**
	 * Create form (GF-02).
	 *
	 * @return string
	 */
	private static function create_form(): string {
		$courses = get_posts(
			array(
				'post_type'   => Relations::COURSE_TYPE,
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$fields  = '<p><label for="ccap-new-name">' . esc_html__( 'Name', 'course-captain' ) . '</label><br><input id="ccap-new-name" type="text" name="name" class="regular-text" required></p>';
		$fields .= '<p><label for="ccap-new-owner">' . esc_html__( 'Owner (user name, email or ID)', 'course-captain' ) . '</label><br><input id="ccap-new-owner" type="text" name="owner" class="regular-text" required></p>';
		$fields .= '<p><label for="ccap-new-seats">' . esc_html__( 'Seats', 'course-captain' ) . '</label><br><input id="ccap-new-seats" type="number" min="1" name="seats" value="10" required></p>';
		$fields .= '<fieldset><legend>' . esc_html__( 'Courses', 'course-captain' ) . '</legend>';
		foreach ( $courses as $course ) {
			if ( $course instanceof \WP_Post ) {
				// One paragraph per choice: enough space between the targets (WCAG 2.5.8, axe target-size).
				$fields .= '<p><label><input type="checkbox" name="courses[]" value="' . (int) $course->ID . '"> ' . esc_html( $course->post_title ) . '</label></p>';
			}
		}
		$fields .= '</fieldset><p><label for="ccap-new-valid">' . esc_html__( 'Valid until (date, empty = unlimited)', 'course-captain' ) . '</label><br><input id="ccap-new-valid" type="date" name="valid_until"> <label><input type="checkbox" name="unlimited_confirm" value="1"> ' . esc_html__( 'Confirm: unlimited', 'course-captain' ) . '</label></p>';
		$fields .= '<p><label for="ccap-new-policy">' . esc_html__( 'Who may redeem an invitation', 'course-captain' ) . '</label><br><select id="ccap-new-policy" name="claim_policy"><option value="transferable">' . esc_html__( 'anyone with the link', 'course-captain' ) . '</option><option value="invited_address_only">' . esc_html__( 'only the invited address', 'course-captain' ) . '</option></select></p>';
		$fields .= '<p><label for="ccap-new-reason">' . esc_html__( 'Note (optional, for example the order number)', 'course-captain' ) . '</label><br><input id="ccap-new-reason" type="text" name="reason" class="regular-text"></p>';
		$fields .= '<input type="hidden" name="request_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
		return '<h2>' . esc_html__( 'Create group', 'course-captain' ) . '</h2>' . self::form( 'create', 0, $fields, __( 'Create group', 'course-captain' ) );
	}

	/**
	 * One admin-post form.
	 *
	 * @param string $op     Operation.
	 * @param int    $group  Group.
	 * @param string $fields Escaped inner HTML.
	 * @param string $button Button label.
	 * @return string
	 */
	private static function form( string $op, int $group, string $fields, string $button ): string {
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-group-form"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="op" value="' . esc_attr( $op ) . '"><input type="hidden" name="group" value="' . (int) $group . '">' . wp_nonce_field( 'ccap_group_' . $op . '_' . $group, '_wpnonce', true, false ) . $fields . '<button type="submit" class="button">' . esc_html( $button ) . '</button></form>';
	}

	/**
	 * Result notice.
	 *
	 * @param array<string, mixed> $request GET.
	 * @return string
	 */
	private static function notice( array $request ): string {
		if ( empty( $request['ccap_group_result'] ) ) {
			return '';
		}
		$code  = sanitize_key( (string) $request['ccap_group_result'] );
		$count = absint( $request['ccap_group_count'] ?? 0 );
		$texts = array(
			'ccap_invalid_nonce'         => __( 'The form has expired. Reload the page and try again.', 'course-captain' ),
			'ccap_forbidden'             => __( 'You are not allowed to run this action.', 'course-captain' ),
			'ccap_not_found'             => __( 'The group, seat or account was not found.', 'course-captain' ),
			'ccap_invalid_param'         => __( 'Please check the entries: name, owner, seats (at least 1), courses and date are required.', 'course-captain' ),
			'ccap_reason_required'       => __( 'Please enter a reason.', 'course-captain' ),
			'ccap_groups_disabled'       => __( 'Groups are switched off. Switch them on in the settings (Enrollment) or for the course.', 'course-captain' ),
			'ccap_group_busy'            => __( 'The group is busy. Please try again in a moment.', 'course-captain' ),
			'ccap_conflict'              => __( 'The group was changed in the meantime or has ended. Reload the page.', 'course-captain' ),
			'ccap_rate_limited'          => __( 'This invitation was already sent again three times.', 'course-captain' ),
			/* translators: %d: affected seats */
			'ccap_confirmation_required' => sprintf( __( 'Please confirm the change (tick the confirmation box). Affected seats: %d.', 'course-captain' ), $count ),
		);
		if ( 'ok' === $code ) {
			/* translators: %d: affected entries */
			$text = sprintf( __( 'Saved. Affected entries: %d.', 'course-captain' ), $count );
			$type = 'success';
		} elseif ( 1 === preg_match( '/^partial_(\d+)_(\d+)$/', $code, $m ) ) {
			/* translators: 1: invited, 2: invalid addresses, 3: no free seat */
			$text = sprintf( __( 'Invited: %1$d. Invalid addresses: %2$d. Without a free seat: %3$d.', 'course-captain' ), $count, (int) $m[1], (int) $m[2] );
			$type = 'warning';
		} else {
			/* translators: %s: error code */
			$text = $texts[ $code ] ?? sprintf( __( 'The action failed (%s).', 'course-captain' ), $code );
			$type = 'error';
		}
		return '<div class="notice notice-' . esc_attr( $type ) . '" role="' . ( 'success' === $type ? 'status' : 'alert' ) . '"><p>' . esc_html( $text ) . '</p></div>';
	}

	/**
	 * Group status as text.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function status_label( string $status ): string {
		$labels = array(
			'active'    => __( 'active', 'course-captain' ),
			'suspended' => __( 'suspended', 'course-captain' ),
			'revoked'   => __( 'ended', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * Seat status as text.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function seat_label( string $status ): string {
		$labels = array(
			'active'  => __( 'member', 'course-captain' ),
			'invited' => __( 'invited', 'course-captain' ),
			'expired' => __( 'invitation expired', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}
}
