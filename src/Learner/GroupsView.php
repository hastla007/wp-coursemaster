<?php
/**
 * "My groups" for group owners in the learning area (R0-11 L-18, Flow 3;
 * R0-09 §4.3/§4.4/§4.7; R0-26 GV-T01…GV-T06; E-342).
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
use CourseCaptain\Policy\PolicyService;
use CourseCaptain\Rest\RestContract;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * `/<area>/gruppen` lists the groups the viewer owns with their seats;
 * `/<area>/gruppen/<group-uid>` shows members and the owner actions of
 * M-15: invite by address, create a new group link (shown once, E-112),
 * switch "only invited addresses" (E-28), send an invitation again,
 * withdraw it, free a seat (with confirmation, F3.9: learning progress is
 * kept) and rename. Creating groups and changing the number of seats stay
 * with the administration (A-10, R0-09 §4.2). Foreign or unknown groups
 * answer 404 (R0-07 RBAC-052); suspended or ended groups are read-only
 * (F3.5). Members never show learning results (GR-7). Every action is a
 * form with a nonce per group (works without JavaScript, PRG).
 */
final class GroupsView {

	public const NONCE = 'ccap_group_owner';

	/**
	 * Owner operations.
	 *
	 * @var list<string>
	 */
	public const OPS = array( 'invite', 'link', 'policy', 'rename', 'resend', 'withdraw', 'remove' );

	/**
	 * URL of the list or of one group.
	 *
	 * @param string $uid Group UID ('' = list).
	 * @return string
	 */
	public static function url( string $uid = '' ): string {
		return LearningArea::url( 'gruppen' . ( '' === $uid ? '' : '/' . $uid ) );
	}

	/**
	 * Whether the viewer owns at least one group (header link).
	 *
	 * @param Actor $actor Viewer.
	 * @return bool
	 */
	public static function has_groups( Actor $actor ): bool {
		if ( $actor->user_id <= 0 ) {
			return false;
		}
		$db = Database::current();
		return null !== $db->scalar( $db->prepare( 'SELECT 1 FROM `' . $db->table( 'ccap_group' ) . '` WHERE owner_user_id = %d LIMIT 1', $actor->user_id ) );
	}

	/**
	 * List page.
	 *
	 * @param Actor $actor Owner.
	 * @return array{status: int, title: string, title_attr: string, body: string}
	 */
	public static function index( Actor $actor ): array {
		$groups = GroupService::current()->owned( $actor );
		$html   = '';
		if ( array() === $groups ) {
			$html .= '<p>' . esc_html__( 'You do not manage any group.', 'course-captain' ) . '</p>';
		} else {
			$html .= '<ul class="ccap-cards">';
			foreach ( $groups as $group ) {
				$html .= '<li class="ccap-card"><h2><a href="' . esc_url( self::url( $group['uid'] ) ) . '">' . esc_html( $group['name'] ) . '</a></h2>';
				$html .= '<p>' . esc_html( self::status_label( $group['status'] ) ) . '</p>';
				$html .= '<p>' . esc_html( self::seats_text( $group ) ) . '</p>';
				if ( array() !== $group['courses'] ) {
					/* translators: %s: list of course titles */
					$html .= '<p>' . esc_html( sprintf( __( 'Courses: %s', 'course-captain' ), implode( ', ', $group['courses'] ) ) ) . '</p>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
		}
		return array(
			'status'     => 200,
			'title'      => __( 'My groups', 'course-captain' ),
			'title_attr' => '',
			'body'       => $html,
		);
	}

	/**
	 * Detail page of one owned group.
	 *
	 * @param Actor                $actor   Owner.
	 * @param string               $uid     Group UID.
	 * @param string               $status  Status after a form post.
	 * @param array<string, mixed> $request Query (`ccap_seat` = seat to confirm).
	 * @return array{status: int, title: string, title_attr: string, body: string}
	 * @throws CourseException 404 for unknown or foreign groups.
	 */
	public static function detail( Actor $actor, string $uid, string $status = '', array $request = array() ): array {
		$id      = self::group_id( $actor, $uid );
		$service = GroupService::current();
		$group   = null;
		foreach ( $service->owned( $actor ) as $row ) {
			$group = $row['id'] === $id ? $row : $group;
		}
		if ( null === $group ) {
			throw new CourseException( 'ccap_not_found', 'Unknown group.', 404 );
		}
		$members  = $service->members( $actor, $id );
		$writable = 'active' === $group['status'];
		$flash    = LearningArea::take_flash( $actor->user_id );
		$html     = '<p class="ccap-back"><a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Back to my groups', 'course-captain' ) . '</a></p>';
		$html    .= self::notice( $status, $flash );
		$html    .= '<section aria-labelledby="ccap-group-seats"><h2 id="ccap-group-seats">' . esc_html__( 'Places', 'course-captain' ) . '</h2><ul>';
		$html    .= '<li>' . esc_html( self::status_label( $group['status'] ) ) . '</li><li>' . esc_html( self::seats_text( $group ) ) . '</li>';
		if ( array() !== $group['courses'] ) {
			/* translators: %s: list of course titles */
			$html .= '<li>' . esc_html( sprintf( __( 'Courses: %s', 'course-captain' ), implode( ', ', $group['courses'] ) ) ) . '</li>';
		}
		if ( null !== $group['valid_until'] ) {
			/* translators: %s: date */
			$html .= '<li>' . esc_html( sprintf( __( 'Valid until: %s', 'course-captain' ), wp_date( (string) get_option( 'date_format' ), (int) strtotime( $group['valid_until'] . ' UTC' ) ) ) ) . '</li>';
		}
		$html .= '</ul>';
		if ( ! $writable ) {
			$html .= '<p class="ccap-error" role="alert">' . esc_html__( 'This group is not active. You can view it, but you cannot invite anyone or change it.', 'course-captain' ) . '</p>';
		}
		$html .= '</section>';
		$seat  = absint( $request['ccap_seat'] ?? 0 );
		if ( $writable && $seat > 0 ) {
			$html .= self::confirm_remove( $id, $uid, $seat, $members );
		}
		$html .= self::members_table( $id, $uid, $members, $writable );
		if ( $writable ) {
			$html .= self::invite_form( $id, $uid );
			$html .= self::link_section( $id, $uid, $group, $flash );
			$html .= self::rename_form( $id, $uid, $group['name'] );
		}
		return array(
			'status'     => 200,
			'title'      => $group['name'],
			'title_attr' => '',
			'body'       => $html,
		);
	}

	/**
	 * Form post on a group page; returns the redirect target (PRG).
	 *
	 * @param Actor                $actor Owner.
	 * @param string               $uid   Group UID.
	 * @param array<string, mixed> $post  Unslashed POST data.
	 * @return string
	 */
	public static function handle( Actor $actor, string $uid, array $post ): string {
		$back = self::url( $uid );
		try {
			$id = self::group_id( $actor, $uid );
		} catch ( CourseException $e ) {
			return add_query_arg( 'ccap_status', $e->error_code, self::url() );
		}
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::NONCE . ':' . $id ) ) {
			return add_query_arg( 'ccap_status', 'ccap_invalid_token', $back );
		}
		$db = Database::current();
		if ( 'active' !== (string) $db->scalar( $db->prepare( 'SELECT status FROM `' . $db->table( 'ccap_group' ) . '` WHERE id = %d', $id ) ) ) {
			// F3.5: a suspended or ended group is read-only for its owner.
			return add_query_arg( 'ccap_status', 'ccap_group_not_active', $back ) . '#ccap-main';
		}
		$op      = (string) ( $post['ccap_op'] ?? '' );
		$service = GroupService::current();
		$seat    = absint( $post['seat'] ?? 0 );
		try {
			switch ( $op ) {
				case 'invite':
					$result = GroupsScreen::invite( $actor, $id, is_string( $post['emails'] ?? null ) ? (string) $post['emails'] : '' );
					LearningArea::flash( $actor->user_id, 'invited', $result );
					return add_query_arg( 'ccap_status', 'invited', $back ) . '#ccap-main';
				case 'link':
					$token = $service->rotate_link( $actor, $id );
					LearningArea::flash( $actor->user_id, 'link', array( 'url' => Invitation::url( $token ) ) );
					return add_query_arg( 'ccap_status', 'link_created', $back ) . '#ccap-group-link';
				case 'policy':
					$policy = isset( $post['claim_policy'] ) && 'invited_address_only' === $post['claim_policy'] ? 'invited_address_only' : 'transferable';
					$service->set_claim_policy( $actor, $id, $policy );
					return add_query_arg( 'ccap_status', 'policy_saved', $back ) . '#ccap-main';
				case 'rename':
					$service->rename( $actor, $id, is_string( $post['name'] ?? null ) ? (string) $post['name'] : '' );
					return add_query_arg( 'ccap_status', 'renamed', $back ) . '#ccap-main';
				case 'resend':
					self::own_seat( $id, $seat );
					NotificationService::current()->resend_invitation( $actor, $seat );
					return add_query_arg( 'ccap_status', 'resent', $back ) . '#ccap-main';
				case 'withdraw':
					$service->remove_seat( $actor, $id, $seat, 'invitation_withdrawn' );
					return add_query_arg( 'ccap_status', 'withdrawn', $back ) . '#ccap-main';
				case 'remove':
					if ( empty( $post['confirm'] ) ) {
						return add_query_arg( 'ccap_seat', $seat, $back ) . '#ccap-confirm-remove';
					}
					$service->remove_seat( $actor, $id, $seat, 'group_member_removed' );
					return add_query_arg( 'ccap_status', 'removed', $back ) . '#ccap-main';
				default:
					return add_query_arg( 'ccap_status', 'ccap_invalid_param', $back );
			}
		} catch ( CourseException $e ) {
			return add_query_arg( 'ccap_status', $e->error_code, $back ) . '#ccap-main';
		}
	}

	/**
	 * Group ID of an owned (or managed) group by UID.
	 *
	 * @param Actor  $actor Owner.
	 * @param string $uid   Group UID.
	 * @return int
	 * @throws CourseException 404 when unknown or not the actor's group.
	 */
	private static function group_id( Actor $actor, string $uid ): int {
		$db = Database::current();
		$id = 1 === preg_match( '/^[0-9a-f-]{36}$/', $uid ) ? (int) $db->scalar( $db->prepare( 'SELECT id FROM `' . $db->table( 'ccap_group' ) . '` WHERE uid = %s AND owner_user_id = %d', $uid, $actor->user_id ) ) : 0;
		if ( $id <= 0 || ! PolicyService::instance()->decide( $actor, PolicyService::MANAGE_GROUP, array( $id ) )->allowed ) {
			throw new CourseException( 'ccap_not_found', 'Unknown group.', 404 );
		}
		return $id;
	}

	/**
	 * A seat of this group (other groups' seats are 404, RBAC-052).
	 *
	 * @param int $group_id Group.
	 * @param int $seat_id  Seat.
	 * @return void
	 * @throws CourseException 404.
	 */
	private static function own_seat( int $group_id, int $seat_id ): void {
		$db = Database::current();
		if ( $group_id !== (int) $db->scalar( $db->prepare( 'SELECT group_id FROM `' . $db->table( 'ccap_seat' ) . '` WHERE id = %d', $seat_id ) ) ) {
			throw new CourseException( 'ccap_not_found', 'Unknown seat.', 404 );
		}
	}

	/**
	 * Members and invitations as a table.
	 *
	 * @param int                        $id       Group.
	 * @param string                     $uid      Group UID.
	 * @param list<array<string, mixed>> $members  Seats.
	 * @param bool                       $writable Actions allowed.
	 * @return string
	 */
	private static function members_table( int $id, string $uid, array $members, bool $writable ): string {
		$html = '<section aria-labelledby="ccap-group-members"><h2 id="ccap-group-members">' . esc_html__( 'Members and invitations', 'course-captain' ) . '</h2>';
		if ( array() === $members ) {
			return $html . '<p>' . esc_html__( 'Nobody has been invited yet.', 'course-captain' ) . '</p></section>';
		}
		$html .= '<table class="ccap-members"><thead><tr><th scope="col">' . esc_html__( 'Person', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Status', 'course-captain' ) . '</th><th scope="col">' . esc_html__( 'Since', 'course-captain' ) . '</th>' . ( $writable ? '<th scope="col">' . esc_html__( 'Actions', 'course-captain' ) . '</th>' : '' ) . '</tr></thead><tbody>';
		foreach ( $members as $member ) {
			$who   = '' !== (string) $member['name'] ? (string) $member['name'] : (string) $member['address'];
			$html .= '<tr><th scope="row">' . esc_html( '' === $who ? '–' : $who ) . '</th><td>' . esc_html( self::seat_label( (string) $member['status'] ) ) . '</td><td>' . esc_html( null === $member['since'] ? '–' : (string) wp_date( (string) get_option( 'date_format' ), (int) strtotime( $member['since'] . ' UTC' ) ) ) . '</td>';
			if ( $writable ) {
				$html .= '<td>';
				if ( 'active' === $member['status'] ) {
					$html .= self::button( $id, $uid, 'remove', (int) $member['seat_id'], sprintf( /* translators: %s: person */ __( 'Free the place of %s', 'course-captain' ), $who ) );
				} else {
					if ( 'invited' === $member['status'] ) {
						$html .= self::button( $id, $uid, 'resend', (int) $member['seat_id'], sprintf( /* translators: %s: person */ __( 'Send the invitation to %s again', 'course-captain' ), $who ) ) . ' ';
					}
					$html .= self::button( $id, $uid, 'withdraw', (int) $member['seat_id'], sprintf( /* translators: %s: person */ __( 'Withdraw the invitation of %s', 'course-captain' ), $who ) );
				}
				$html .= '</td>';
			}
			$html .= '</tr>';
		}
		return $html . '</tbody></table></section>';
	}

	/**
	 * One-button form of a seat action.
	 *
	 * @param int    $id    Group.
	 * @param string $uid   Group UID.
	 * @param string $op    Operation.
	 * @param int    $seat  Seat.
	 * @param string $label Accessible label.
	 * @return string
	 */
	private static function button( int $id, string $uid, string $op, int $seat, string $label ): string {
		return '<form method="post" class="ccap-inline" action="' . esc_url( self::url( $uid ) ) . '">' . wp_nonce_field( self::NONCE . ':' . $id, '_wpnonce', false, false ) . '<input type="hidden" name="ccap_op" value="' . esc_attr( $op ) . '"><input type="hidden" name="seat" value="' . (int) $seat . '"><button type="submit" class="ccap-button">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Confirmation before freeing a seat (F3.9).
	 *
	 * @param int                        $id      Group.
	 * @param string                     $uid     Group UID.
	 * @param int                        $seat    Seat.
	 * @param list<array<string, mixed>> $members Seats.
	 * @return string
	 */
	private static function confirm_remove( int $id, string $uid, int $seat, array $members ): string {
		foreach ( $members as $member ) {
			if ( (int) $member['seat_id'] !== $seat || 'active' !== $member['status'] ) {
				continue;
			}
			$who = '' !== (string) $member['name'] ? (string) $member['name'] : (string) $member['address'];
			return '<section class="ccap-confirm" id="ccap-confirm-remove" aria-labelledby="ccap-confirm-remove-text"><p id="ccap-confirm-remove-text" role="alert">' . esc_html( sprintf( /* translators: %s: person */ __( 'Free the place of %s? The person loses access to the group courses at once. Their learning progress is kept, and the place is free for someone else.', 'course-captain' ), $who ) ) . '</p>'
				. '<form method="post" action="' . esc_url( self::url( $uid ) ) . '">' . wp_nonce_field( self::NONCE . ':' . $id, '_wpnonce', false, false ) . '<input type="hidden" name="ccap_op" value="remove"><input type="hidden" name="seat" value="' . (int) $seat . '"><input type="hidden" name="confirm" value="1">'
				. '<p><button type="submit" class="ccap-button">' . esc_html__( 'Yes, free the place', 'course-captain' ) . '</button> <a class="ccap-button" href="' . esc_url( self::url( $uid ) ) . '">' . esc_html__( 'No, keep the member', 'course-captain' ) . '</a></p></form></section>';
		}
		return '';
	}

	/**
	 * Invitation by address.
	 *
	 * @param int    $id  Group.
	 * @param string $uid Group UID.
	 * @return string
	 */
	private static function invite_form( int $id, string $uid ): string {
		return '<section aria-labelledby="ccap-group-invite"><h2 id="ccap-group-invite">' . esc_html__( 'Invite by email', 'course-captain' ) . '</h2>'
			. '<form method="post" action="' . esc_url( self::url( $uid ) ) . '">' . wp_nonce_field( self::NONCE . ':' . $id, '_wpnonce', false, false ) . '<input type="hidden" name="ccap_op" value="invite">'
			. '<p><label for="ccap-invite-emails">' . esc_html__( 'Email addresses', 'course-captain' ) . '</label><br><textarea id="ccap-invite-emails" name="emails" rows="4" cols="50" aria-describedby="ccap-invite-emails-hint" autocomplete="off"></textarea><br><span id="ccap-invite-emails-hint">' . esc_html__( 'One address per line or separated by commas, at most 200. Every invitation takes one place until it is accepted or expires. The address is only used for the invitation.', 'course-captain' ) . '</span></p>'
			. '<p><button type="submit" class="ccap-button">' . esc_html__( 'Send invitations', 'course-captain' ) . '</button></p></form></section>';
	}

	/**
	 * Group link and "only invited addresses".
	 *
	 * @param int                       $id    Group.
	 * @param string                    $uid   Group UID.
	 * @param array<string, mixed>      $group Group summary.
	 * @param array<string, mixed>|null $flash One-time data.
	 * @return string
	 */
	private static function link_section( int $id, string $uid, array $group, ?array $flash ): string {
		$nonce = wp_nonce_field( self::NONCE . ':' . $id, '_wpnonce', false, false );
		$only  = 'invited_address_only' === $group['claim_policy'];
		$html  = '<section aria-labelledby="ccap-group-link"><h2 id="ccap-group-link">' . esc_html__( 'Group link', 'course-captain' ) . '</h2>';
		if ( null !== $flash && 'link' === $flash['code'] && is_string( $flash['data']['url'] ?? null ) ) {
			$html .= '<p><label for="ccap-group-link-url">' . esc_html__( 'New group link (shown only now – copy it and keep it safe):', 'course-captain' ) . '</label><br><input type="text" readonly id="ccap-group-link-url" size="60" value="' . esc_attr( $flash['data']['url'] ) . '"></p>';
		}
		if ( $only ) {
			$html .= '<p>' . esc_html__( 'The group link is switched off: only people you invite by email can join, and only with the invited address.', 'course-captain' ) . '</p>';
		} else {
			$html .= '<p>' . esc_html( $group['has_link'] ? __( 'A group link exists. Anyone with the link can take a free place. For security the link is only shown once, when it is created. If the link got into the wrong hands, create a new one: the old link stops working at once.', 'course-captain' ) : __( 'There is no group link yet. Anyone with the link can take a free place.', 'course-captain' ) ) . '</p>';
			$html .= '<form method="post" action="' . esc_url( self::url( $uid ) ) . '">' . $nonce . '<input type="hidden" name="ccap_op" value="link"><p><button type="submit" class="ccap-button">' . esc_html( $group['has_link'] ? __( 'Create a new group link', 'course-captain' ) : __( 'Create a group link', 'course-captain' ) ) . '</button></p></form>';
		}
		$html .= '<form method="post" action="' . esc_url( self::url( $uid ) ) . '">' . $nonce . '<input type="hidden" name="ccap_op" value="policy"><fieldset><legend>' . esc_html__( 'Who may join?', 'course-captain' ) . '</legend><ul class="ccap-options">';
		$html .= '<li><input type="radio" id="ccap-policy-transferable" name="claim_policy" value="transferable"' . checked( $only, false, false ) . '> <label for="ccap-policy-transferable">' . esc_html__( 'Anyone with an invitation or the group link', 'course-captain' ) . '</label></li>';
		$html .= '<li><input type="radio" id="ccap-policy-only" name="claim_policy" value="invited_address_only"' . checked( $only, true, false ) . ' aria-describedby="ccap-policy-only-hint"> <label for="ccap-policy-only">' . esc_html__( 'Only the invited email address', 'course-captain' ) . '</label> <span id="ccap-policy-only-hint">' . esc_html__( 'An invitation can then only be accepted with an account that uses the invited address. The group link is switched off.', 'course-captain' ) . '</span></li>';
		return $html . '</ul><p><button type="submit" class="ccap-button">' . esc_html__( 'Save', 'course-captain' ) . '</button></p></fieldset></form></section>';
	}

	/**
	 * Rename form.
	 *
	 * @param int    $id   Group.
	 * @param string $uid  Group UID.
	 * @param string $name Current name.
	 * @return string
	 */
	private static function rename_form( int $id, string $uid, string $name ): string {
		return '<section aria-labelledby="ccap-group-rename"><h2 id="ccap-group-rename">' . esc_html__( 'Rename the group', 'course-captain' ) . '</h2><form method="post" action="' . esc_url( self::url( $uid ) ) . '">' . wp_nonce_field( self::NONCE . ':' . $id, '_wpnonce', false, false ) . '<input type="hidden" name="ccap_op" value="rename">'
			. '<p><label for="ccap-group-name">' . esc_html__( 'Group name', 'course-captain' ) . '</label><br><input type="text" id="ccap-group-name" name="name" maxlength="190" value="' . esc_attr( $name ) . '"></p><p><button type="submit" class="ccap-button">' . esc_html__( 'Rename', 'course-captain' ) . '</button></p></form></section>';
	}

	/**
	 * Notice after a form post.
	 *
	 * @param string                    $status Status.
	 * @param array<string, mixed>|null $flash  One-time data.
	 * @return string
	 */
	private static function notice( string $status, ?array $flash ): string {
		if ( '' === $status ) {
			return '';
		}
		$ok = array(
			'link_created' => __( 'A new group link has been created. The old link no longer works.', 'course-captain' ),
			'policy_saved' => __( 'The setting has been saved.', 'course-captain' ),
			'renamed'      => __( 'The group has been renamed.', 'course-captain' ),
			'resent'       => __( 'The invitation will be sent again.', 'course-captain' ),
			'withdrawn'    => __( 'The invitation has been withdrawn. The place is free again.', 'course-captain' ),
			'removed'      => __( 'The place has been freed. The person no longer has access through this group; their learning progress is kept.', 'course-captain' ),
		);
		if ( 'invited' === $status && null !== $flash && 'invited' === $flash['code'] ) {
			$data  = (array) $flash['data'];
			$parts = array(
				sprintf(
					/* translators: %s: number of invitations */
					_n( '%s invitation has been sent.', '%s invitations have been sent.', (int) ( $data['invited'] ?? 0 ), 'course-captain' ),
					number_format_i18n( (int) ( $data['invited'] ?? 0 ) )
				),
			);
			if ( (int) ( $data['invalid'] ?? 0 ) > 0 ) {
				$parts[] = sprintf(
					/* translators: %s: number of addresses */
					_n( '%s address is not valid.', '%s addresses are not valid.', (int) $data['invalid'], 'course-captain' ),
					number_format_i18n( (int) $data['invalid'] )
				);
			}
			if ( (int) ( $data['limited'] ?? 0 ) > 0 ) {
				$parts[] = sprintf(
					/* translators: %s: number of addresses */
					_n( '%s address was not invited because no place is free.', '%s addresses were not invited because no place is free.', (int) $data['limited'], 'course-captain' ),
					number_format_i18n( (int) $data['limited'] )
				);
			}
			$error = (int) ( $data['invalid'] ?? 0 ) + (int) ( $data['limited'] ?? 0 ) > 0;
			return '<p class="' . ( $error ? 'ccap-error' : 'ccap-status' ) . '" role="' . ( $error ? 'alert' : 'status' ) . '">' . esc_html( implode( ' ', $parts ) ) . '</p>';
		}
		if ( isset( $ok[ $status ] ) ) {
			return '<p class="ccap-status" role="status">' . esc_html( $ok[ $status ] ) . '</p>';
		}
		$texts = array(
			'ccap_invalid_param' => __( 'Please check your input: enter at least one and at most 200 email addresses or a valid name.', 'course-captain' ),
			'ccap_conflict'      => __( 'This is no longer possible. Please reload the page.', 'course-captain' ),
		);
		return '<p class="ccap-error" role="alert">' . esc_html( $texts[ $status ] ?? RestContract::user_message( $status ) ) . '</p>';
	}

	/**
	 * Seats as text (GV-T01).
	 *
	 * @param array<string, mixed> $group Group summary.
	 * @return string
	 */
	private static function seats_text( array $group ): string {
		$free = max( 0, (int) $group['seats_total'] - (int) $group['seats_used'] );
		return sprintf(
			/* translators: 1: free places, 2: all places, 3: open invitations */
			_n( '%1$s of %2$s places free (%3$s open invitation).', '%1$s of %2$s places free (%3$s open invitations).', (int) $group['invites_open'], 'course-captain' ),
			number_format_i18n( $free ),
			number_format_i18n( (int) $group['seats_total'] ),
			number_format_i18n( (int) $group['invites_open'] )
		);
	}

	/**
	 * Group status as text.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private static function status_label( string $status ): string {
		$labels = array(
			'active'    => __( 'Group active', 'course-captain' ),
			'suspended' => __( 'Group suspended', 'course-captain' ),
			'revoked'   => __( 'Group ended', 'course-captain' ),
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
			'active'  => __( 'Member (active)', 'course-captain' ),
			'invited' => __( 'Invited, not accepted yet', 'course-captain' ),
			'expired' => __( 'Invitation expired', 'course-captain' ),
		);
		return $labels[ $status ] ?? $status;
	}
}
