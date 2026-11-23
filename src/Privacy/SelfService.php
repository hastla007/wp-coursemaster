<?php
/**
 * Self-service export and erasure requests (R0-05 GM-174; R0-19 R-48,
 * `[ccap_privacy_request]`; R0-07 RG-17/M-23; E-43).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Privacy;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;
use CourseCaptain\Storage\RateLimiter;

defined( 'ABSPATH' ) || exit;

/**
 * A logged-in person files a WordPress data request for the own account
 * (never for another address). The confirmation mail links to an own page:
 * GET only shows a confirmation button and changes nothing, the POST
 * confirms the request (core hook `user_request_action_confirmed`), which
 * then follows the privacy settings (export in the background; account
 * deletion only with `privacy.auto_erase_on_confirm`, E-43). A second
 * request of the same type while one is open creates no new request but
 * sends the mail again (`resent`) or reports `confirmed`. Staff accounts
 * cannot request their own erasure (RB-9). 5 requests per day (bucket
 * `privacy`).
 */
final class SelfService {

	public const TYPES          = array(
		'export' => 'export_personal_data',
		'erase'  => 'remove_personal_data',
	);
	public const META           = '_ccap_self_service';
	public const LIMIT_DAY      = 5;
	public const ACTION_REQUEST = 'ccap_privacy_request';
	public const ACTION_CONFIRM = 'ccap_privacy_confirm';
	public const SHORTCODE      = 'ccap_privacy_request';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'user_request_action_email_content', array( self::class, 'mail_content' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION_REQUEST, array( self::class, 'handle_request' ) );
		add_action( 'admin_post_' . self::ACTION_CONFIRM, array( self::class, 'handle_confirm' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION_CONFIRM, array( self::class, 'handle_confirm' ) );
		\CourseCaptain\Ops\Compatibility::add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
	}

	/**
	 * File a request for the own account (REST R-48, admin-post, shortcode).
	 *
	 * @param Actor    $actor Person.
	 * @param string   $type  export|erase.
	 * @param int|null $now   Time (tests).
	 * @return array{status: string, request_id: int}
	 * @throws CourseException 401, 400, 403 (staff erasure), 429.
	 */
	public static function request( Actor $actor, string $type, ?int $now = null ): array {
		$user = $actor->user_id > 0 ? get_userdata( $actor->user_id ) : false;
		if ( ! $user instanceof \WP_User ) {
			throw new CourseException( 'ccap_auth_required', 'Please log in.', 401 );
		}
		if ( ! isset( self::TYPES[ $type ] ) ) {
			throw new CourseException( 'ccap_invalid_param', 'type', 400 );
		}
		if ( 'erase' === $type && PrivacySettings::protected_account( $user ) ) {
			throw new CourseException( 'ccap_forbidden', 'Accounts with administrative rights cannot request their own erasure.', 403, array( 'reason' => 'staff_account' ) );
		}
		$db = Database::current();
		RateLimiter::hit( $db, 'privacy', (int) $user->ID, DAY_IN_SECONDS, self::LIMIT_DAY, $now ?? time() );
		$action = self::TYPES[ $type ];
		$open   = self::open_request( (string) $user->user_email, $action );
		if ( null !== $open ) {
			if ( 'request-confirmed' === $open->status ) {
				return array(
					'status'     => 'confirmed',
					'request_id' => (int) $open->ID,
				);
			}
			wp_send_user_request( (int) $open->ID );
			return array(
				'status'     => 'resent',
				'request_id' => (int) $open->ID,
			);
		}
		$id = wp_create_user_request( (string) $user->user_email, $action, array(), 'pending' );
		if ( is_wp_error( $id ) ) {
			throw new CourseException( 'ccap_validation_failed', 'The request could not be created.', 422 );
		}
		update_post_meta( (int) $id, self::META, 1 );
		wp_send_user_request( (int) $id );
		AuditLog::write( $db, Actor::user( (int) $user->ID ), 'privacy_request_created', 'privacy_request', (string) $id, null, array( 'type' => $type ), (int) $user->ID );
		return array(
			'status'     => 'created',
			'request_id' => (int) $id,
		);
	}

	/**
	 * Open (pending or confirmed, not completed) request of an address.
	 *
	 * @param string $email  Address.
	 * @param string $action Core action name.
	 * @return \WP_User_Request|null
	 */
	private static function open_request( string $email, string $action ): ?\WP_User_Request {
		$ids = get_posts(
			array(
				'post_type'      => 'user_request',
				'post_name__in'  => array( $action ),
				'title'          => $email,
				'post_status'    => array( 'request-pending', 'request-confirmed' ),
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		foreach ( $ids as $id ) {
			$request = wp_get_user_request( (int) $id );
			if ( $request instanceof \WP_User_Request && $request->email === $email ) {
				return $request;
			}
		}
		return null;
	}

	/**
	 * Mail of an own request: the link goes to the confirmation page (GET = view only).
	 *
	 * @param mixed $content    Mail text with `###CONFIRM_URL###`.
	 * @param mixed $email_data Core mail data.
	 * @return mixed
	 */
	public static function mail_content( $content, $email_data ) {
		$request = is_array( $email_data ) ? ( $email_data['request'] ?? null ) : null;
		if ( ! is_string( $content ) || ! $request instanceof \WP_User_Request || '1' !== (string) get_post_meta( (int) $request->ID, self::META, true ) ) {
			return $content;
		}
		$query = array();
		wp_parse_str( (string) wp_parse_url( (string) ( $email_data['confirm_url'] ?? '' ), PHP_URL_QUERY ), $query );
		$key = is_string( $query['confirm_key'] ?? null ) ? $query['confirm_key'] : '';
		return str_replace( '###CONFIRM_URL###', esc_url_raw( self::confirm_url( (int) $request->ID, $key ) ), $content );
	}

	/**
	 * URL of the confirmation page.
	 *
	 * @param int    $request_id Request.
	 * @param string $key        Confirmation key.
	 * @return string
	 */
	public static function confirm_url( int $request_id, string $key ): string {
		return add_query_arg(
			array(
				'action'      => self::ACTION_CONFIRM,
				'request_id'  => $request_id,
				'confirm_key' => rawurlencode( $key ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Confirmation page (GET): explains the request, changes nothing.
	 *
	 * @param int    $request_id Request.
	 * @param string $key        Confirmation key.
	 * @return string HTML.
	 */
	public static function confirm_page( int $request_id, string $key ): string {
		$request = wp_get_user_request( $request_id );
		$valid   = $request instanceof \WP_User_Request && true === wp_validate_user_request_key( $request_id, $key );
		if ( ! $valid ) {
			return '<p>' . esc_html__( 'This confirmation link is invalid or has already been used.', 'course-captain' ) . '</p>';
		}
		$erase = 'remove_personal_data' === $request->action_name;
		$text  = $erase
			? __( 'Please confirm that your personal data on this site shall be erased. Nothing happens until you press the button.', 'course-captain' )
			: __( 'Please confirm that you want an export of your personal data on this site. Nothing happens until you press the button.', 'course-captain' );
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<p>' . esc_html( $text ) . '</p>'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_CONFIRM ) . '">'
			. '<input type="hidden" name="request_id" value="' . esc_attr( (string) $request_id ) . '">'
			. '<input type="hidden" name="confirm_key" value="' . esc_attr( $key ) . '">'
			. '<p><button type="submit">' . esc_html( $erase ? __( 'Confirm erasure', 'course-captain' ) : __( 'Confirm export', 'course-captain' ) ) . '</button></p>'
			. '</form>';
	}

	/**
	 * Confirm (POST only): the key is checked and the core confirmation runs.
	 *
	 * @param int    $request_id Request.
	 * @param string $key        Confirmation key.
	 * @return string `confirmed` or `invalid`.
	 */
	public static function confirm( int $request_id, string $key ): string {
		$request = wp_get_user_request( $request_id );
		if ( ! $request instanceof \WP_User_Request || true !== wp_validate_user_request_key( $request_id, $key ) ) {
			return 'invalid';
		}
		do_action( 'user_request_action_confirmed', $request_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook (confirmation, then the privacy settings).
		return 'confirmed';
	}

	/**
	 * `admin-post.php?action=ccap_privacy_confirm`: GET shows, POST confirms.
	 *
	 * @return void
	 */
	public static function handle_confirm(): void {
		// The confirmation key is the authorisation (sent to the address only); no nonce for logged-out people.
		$id     = isset( $_REQUEST['request_id'] ) ? absint( wp_unslash( $_REQUEST['request_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key    = isset( $_REQUEST['confirm_key'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['confirm_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'POST' === $method ) {
			$html = 'confirmed' === self::confirm( $id, $key )
				? '<p>' . esc_html__( 'Thank you. Your request has been confirmed.', 'course-captain' ) . '</p>'
				: '<p>' . esc_html__( 'This confirmation link is invalid or has already been used.', 'course-captain' ) . '</p>';
		} else {
			$html = self::confirm_page( $id, $key );
		}
		wp_die( self::page_html( $html ), esc_html__( 'Data request', 'course-captain' ), array( 'response' => 200 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- page_html() is wp_kses with the post allowlist plus the confirmation form elements.
	}

	/**
	 * Output filter of the confirmation pages: like `wp_kses_post`, plus the
	 * form elements of the confirmation (wp_kses_post strips forms, which left
	 * a button without a form and made confirmation impossible).
	 *
	 * @param string $html Page HTML (already escaped).
	 * @return string
	 */
	public static function page_html( string $html ): string {
		$allowed           = wp_kses_allowed_html( 'post' );
		$allowed['form']   = array(
			'method' => true,
			'action' => true,
		);
		$allowed['input']  = array(
			'type'  => true,
			'name'  => true,
			'value' => true,
		);
		$allowed['button'] = array( 'type' => true );
		return wp_kses( $html, $allowed );
	}

	/**
	 * `admin-post.php?action=ccap_privacy_request` (form of the shortcode).
	 *
	 * @return void
	 */
	public static function handle_request(): void {
		check_admin_referer( self::ACTION_REQUEST );
		$type   = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( (string) $_POST['type'] ) ) : '';
		$status = 'error';
		try {
			$status = self::request( Actor::user( get_current_user_id() ), $type )['status'];
		} catch ( CourseException $e ) {
			$status = 'ccap_rate_limited' === $e->error_code ? 'limited' : 'error';
		}
		$back = wp_get_referer();
		wp_safe_redirect( add_query_arg( 'ccap_privacy', $status, $back ? $back : home_url( '/' ) ) );
		exit;
	}

	/**
	 * `[ccap_privacy_request]`: two buttons for logged-in people.
	 *
	 * @return string
	 */
	public static function shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to request an export or erasure of your data.', 'course-captain' ) . '</p>';
		}
		$status = isset( $_GET['ccap_privacy'] ) ? sanitize_key( wp_unslash( (string) $_GET['ccap_privacy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$notes  = array(
			'created'   => __( 'Please check your mailbox and confirm the request.', 'course-captain' ),
			'resent'    => __( 'The request is already open; the confirmation mail was sent again.', 'course-captain' ),
			'confirmed' => __( 'The request is confirmed and being processed.', 'course-captain' ),
			'limited'   => __( 'Too many requests today. Please try again tomorrow.', 'course-captain' ),
			'error'     => __( 'The request could not be sent.', 'course-captain' ),
		);
		$out    = isset( $notes[ $status ] ) ? '<p role="status">' . esc_html( $notes[ $status ] ) . '</p>' : '';
		foreach ( array(
			'export' => __( 'Request a copy of my data', 'course-captain' ),
			'erase'  => __( 'Request erasure of my data', 'course-captain' ),
		) as $type => $label ) {
			$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
				. wp_nonce_field( self::ACTION_REQUEST, '_wpnonce', true, false )
				. '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_REQUEST ) . '">'
				. '<input type="hidden" name="type" value="' . esc_attr( $type ) . '">'
				. '<button type="submit">' . esc_html( $label ) . '</button></form>';
		}
		return '<div class="ccap-privacy-request">' . $out . '</div>';
	}
}
