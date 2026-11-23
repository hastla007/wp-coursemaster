<?php
/**
 * Invitation page in the learning area and return after login/registration (R0-05 GM-104; R0-09 §4.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learner;

use CourseCaptain\Access\GroupService;
use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Rest\RestContract;

defined( 'ABSPATH' ) || exit;

/**
 * The invitation link is `<learning area>/?ccap_claim=<token>`. Guests see
 * a hint with login and registration links that both lead back to the
 * invitation; logged-in learners see the group and accept with a form
 * (nonce, PRG). After login (`login_redirect`) and registration
 * (`registration_redirect`: login page with the invitation as target) the
 * learner returns to the invitation. A return target that names an
 * invitation but is not one of this site (other host, other path,
 * malformed token) is ignored. Guests never see group data.
 *
 * E-342 (R0-11 L-17, Flow 3): logged-in viewers get the preview of
 * {@see GroupService::preview()} (owner, courses, free place, deadline,
 * masked address of a bound invitation, "already member" with course
 * links, access from another source) and the messages of F3.1–F3.5 with
 * the next step. Unknown, replaced, used and expired invitations share
 * one neutral text (F3.3, no existence hint).
 */
final class Invitation {

	public const PARAM = 'ccap_claim';
	public const NONCE = 'ccap_claim';

	private const TOKEN = '/^cc[gi]_[A-Za-z0-9_-]{43}$/';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 20, 2 );
		add_filter( 'registration_redirect', array( self::class, 'registration_redirect' ), 20 );
	}

	/**
	 * Invitation URL of a token.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function url( string $token ): string {
		return add_query_arg( self::PARAM, rawurlencode( $token ), LearningArea::url() );
	}

	/**
	 * Valid token or null.
	 *
	 * @param mixed $token Candidate.
	 * @return string|null
	 */
	public static function token( $token ): ?string {
		return is_string( $token ) && 1 === preg_match( self::TOKEN, $token ) ? $token : null;
	}

	/**
	 * Return target: the invitation URL when the requested URL is an
	 * invitation of this site, else empty.
	 *
	 * @param string $requested Requested URL.
	 * @return string
	 */
	public static function return_target( string $requested ): string {
		$parts = wp_parse_url( $requested );
		$home  = wp_parse_url( LearningArea::url() );
		if ( ! is_array( $parts ) || ! is_array( $home ) || empty( $parts['query'] ) ) {
			return '';
		}
		parse_str( (string) $parts['query'], $query );
		$token = self::token( $query[ self::PARAM ] ?? null );
		$same  = strtolower( (string) ( $parts['host'] ?? '' ) ) === strtolower( (string) ( $home['host'] ?? '' ) )
			&& (int) ( $parts['port'] ?? 0 ) === (int) ( $home['port'] ?? 0 )
			&& untrailingslashit( (string) ( $parts['path'] ?? '' ) ) === untrailingslashit( (string) ( $home['path'] ?? '' ) );
		return null !== $token && $same ? self::url( $token ) : '';
	}

	/**
	 * Whether a URL names an invitation at all (valid or not).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function names_invitation( string $url ): bool {
		return str_contains( $url, self::PARAM . '=' );
	}

	/**
	 * `login_redirect`: back to the invitation; a foreign invitation target is ignored.
	 *
	 * @param mixed $redirect_to Redirect so far.
	 * @param mixed $requested   Requested redirect.
	 * @return string
	 */
	public static function login_redirect( $redirect_to, $requested = '' ): string {
		$requested = is_string( $requested ) ? $requested : '';
		if ( ! self::names_invitation( $requested ) ) {
			return is_string( $redirect_to ) ? $redirect_to : '';
		}
		$target = self::return_target( $requested );
		return '' === $target ? LearningArea::url() : $target;
	}

	/**
	 * `registration_redirect`: after registering, the login page with the
	 * invitation as target; a foreign invitation target is ignored.
	 *
	 * @param mixed $redirect Requested redirect.
	 * @return string
	 */
	public static function registration_redirect( $redirect ): string {
		$redirect = is_string( $redirect ) ? $redirect : '';
		if ( ! self::names_invitation( $redirect ) ) {
			return $redirect;
		}
		$target = self::return_target( $redirect );
		return '' === $target ? '' : add_query_arg( 'checkemail', 'registered', wp_login_url( $target ) );
	}

	/**
	 * Page body for the invitation.
	 *
	 * @param Actor  $actor  Viewer (user 0 = guest).
	 * @param string $token  Raw token.
	 * @param string $status Status after a form post.
	 * @return string
	 */
	public static function body( Actor $actor, string $token, string $status = '' ): string {
		$notice = '' === $status ? '' : '<p class="ccap-error" role="alert">' . esc_html( RestContract::user_message( $status ) ) . '</p>';
		$back   = self::url( $token );
		if ( $actor->user_id <= 0 ) {
			$html  = '<p>' . esc_html__( 'You have been invited to a group. Log in or create an account to accept the invitation; you will come back here afterwards.', 'course-captain' ) . '</p>';
			$html .= '<p><a class="ccap-button" href="' . esc_url( wp_login_url( $back ) ) . '">' . esc_html__( 'Log in', 'course-captain' ) . '</a>';
			// Multisite: the network setting decides (`registration` user/all).
			$open = is_multisite() ? in_array( get_site_option( 'registration' ), array( 'user', 'all' ), true ) : (bool) get_option( 'users_can_register' );
			if ( $open ) {
				$html .= ' <a href="' . esc_url( add_query_arg( 'redirect_to', rawurlencode( $back ), wp_registration_url() ) ) . '">' . esc_html__( 'Create an account', 'course-captain' ) . '</a>';
			}
			return $notice . $html . '</p>';
		}
		try {
			$preview = GroupService::current()->preview( $actor, $token );
		} catch ( CourseException $e ) {
			// F3.2/F3.3: unknown, replaced, used and expired invitations get the same neutral text (no existence hint).
			return '<p role="alert">' . esc_html( RestContract::user_message( 'ccap_invite_expired' ) ) . '</p><p><a href="' . esc_url( LearningArea::url() ) . '">' . esc_html__( 'Go to my learning', 'course-captain' ) . '</a></p>';
		}
		$group = $preview['group'];
		if ( $preview['already_member'] ) {
			$html = '<p role="status">' . esc_html( sprintf( /* translators: %s: group name */ __( 'You are already a member of the group "%s".', 'course-captain' ), (string) $group['name'] ) ) . '</p>';
			return $notice . $html . self::course_links( $preview['course_ids'] );
		}
		$html  = '<p>' . esc_html( sprintf( /* translators: %s: group name */ __( 'You have been invited to the group "%s".', 'course-captain' ), (string) $group['name'] ) ) . '</p>';
		$html .= '<ul class="ccap-invitation">';
		if ( '' !== $preview['owner_name'] ) {
			$html .= '<li>' . esc_html( sprintf( /* translators: %s: display name */ __( 'Managed by: %s', 'course-captain' ), $preview['owner_name'] ) ) . '</li>';
		}
		if ( array() !== $preview['course_titles'] ) {
			$html .= '<li>' . esc_html( sprintf( /* translators: %s: list of course titles */ __( 'Courses: %s', 'course-captain' ), implode( ', ', $preview['course_titles'] ) ) ) . '</li>';
		}
		$html .= '<li>' . esc_html( $preview['seats_free'] ? __( 'A place in the group is free for you.', 'course-captain' ) : __( 'There is no free place in the group at the moment.', 'course-captain' ) ) . '</li>';
		if ( null !== $preview['expires_at'] ) {
			$html .= '<li>' . esc_html( sprintf( /* translators: %s: date */ __( 'Valid until: %s', 'course-captain' ), wp_date( (string) get_option( 'date_format' ), (int) strtotime( $preview['expires_at'] . ' UTC' ) ) ) ) . '</li>';
		}
		$html .= '</ul>';
		if ( ! $preview['active'] ) {
			return $notice . $html . '<p class="ccap-error" role="alert">' . esc_html( RestContract::user_message( 'ccap_group_not_active' ) ) . '</p>';
		}
		if ( $preview['bound_to_other_address'] ) {
			return $notice . $html . '<p class="ccap-error" role="alert">' . esc_html( sprintf( /* translators: %s: masked email address */ __( 'This invitation was sent to %s and can only be accepted with that account. Log in with that account or ask the person who manages the group for a new invitation.', 'course-captain' ), (string) $preview['invite_email_masked'] ) ) . '</p>';
		}
		if ( ! $preview['seats_free'] ) {
			return $notice . $html . '<p class="ccap-error" role="alert">' . esc_html( RestContract::user_message( 'ccap_group_full' ) ) . '</p>';
		}
		if ( null !== $preview['invite_email_masked'] ) {
			$html .= '<p>' . esc_html( sprintf( /* translators: %s: masked email address */ __( 'This invitation was sent to %s. You can accept it with your account.', 'course-captain' ), (string) $preview['invite_email_masked'] ) ) . '</p>';
		}
		if ( $preview['access_elsewhere'] ) {
			$html .= '<p>' . esc_html__( 'You already have access to these courses. If you accept, you still take one place in the group.', 'course-captain' ) . '</p>';
		}
		$html .= '<form method="post" action="' . esc_url( LearningArea::url() ) . '">' . wp_nonce_field( self::NONCE, '_wpnonce', false, false );
		$html .= '<input type="hidden" name="ccap_do" value="claim"><input type="hidden" name="' . esc_attr( self::PARAM ) . '" value="' . esc_attr( $token ) . '">';
		$html .= '<button type="submit" class="ccap-button">' . esc_html__( 'Accept invitation', 'course-captain' ) . '</button></form>';
		return $notice . $html;
	}

	/**
	 * Links to the course rooms of the group courses ("open course").
	 *
	 * @param array $course_ids Courses.
	 * @phpstan-param list<int> $course_ids
	 * @return string
	 */
	private static function course_links( array $course_ids ): string {
		$html = '<ul>';
		foreach ( $course_ids as $course ) {
			$post = get_post( $course );
			if ( $post instanceof \WP_Post && 'publish' === $post->post_status ) {
				$html .= '<li><a href="' . esc_url( LearningArea::url( 'kurs/' . LearningArea::course_uid( $course ) ) ) . '">' . esc_html( sprintf( /* translators: %s: course title */ __( 'Open the course %s', 'course-captain' ), $post->post_title ) ) . '</a></li>';
			}
		}
		return $html . '</ul>';
	}

	/**
	 * Accept the invitation (form post); returns the redirect target.
	 *
	 * @param Actor                $actor Learner.
	 * @param array<string, mixed> $post  Unslashed POST data.
	 * @return string
	 */
	public static function handle( Actor $actor, array $post ): string {
		$token = self::token( $post[ self::PARAM ] ?? null );
		if ( null === $token ) {
			return add_query_arg( 'ccap_status', 'ccap_invalid_token', LearningArea::url() );
		}
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::NONCE ) ) {
			return add_query_arg( 'ccap_status', 'ccap_forbidden', self::url( $token ) );
		}
		try {
			GroupService::current()->claim( $actor, $token );
		} catch ( CourseException $e ) {
			return add_query_arg( 'ccap_status', $e->error_code, self::url( $token ) );
		}
		LearningArea::flash( $actor->user_id, 'joined' );
		return LearningArea::url();
	}
}
