<?php
/**
 * Opt-out per informational type and the unsubscribe page (R0-27 §3.7).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Notification;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Event\AuditLog;
use CourseCaptain\Learner\LearningArea;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * User option `course_captain_notification_prefs` = `{optout: list<type>,
 * updated_at}` (MS-R-05). Only informational types; only the person
 * (account) or a valid signed link (RFC 8058 one-click) can change it, the
 * operator can never lift it. Signature: HMAC-SHA256 over
 * `user_id:type:user_registered` with a purpose-bound key from
 * `wp_salt('auth')`, without expiry. GET shows a confirmation button (link
 * scanners), POST applies at once; a wrong signature changes nothing.
 */
final class NotificationPrefs {

	public const OPTION = 'course_captain_notification_prefs';
	public const PATH   = 'benachrichtigungen';
	public const NONCE  = 'ccap_notification_prefs';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'serve' ), 4 );
	}

	/**
	 * Unsubscribed types of a user.
	 *
	 * @param int $user_id User.
	 * @return list<string>
	 */
	public static function optouts( int $user_id ): array {
		$prefs = get_user_option( self::OPTION, $user_id );
		$list  = is_array( $prefs ) ? (array) ( $prefs['optout'] ?? array() ) : array();
		return array_values( array_intersect( NotificationTypes::informational(), array_map( 'strval', $list ) ) );
	}

	/**
	 * Whether a user unsubscribed from a type (required types never).
	 *
	 * @param int    $user_id User.
	 * @param string $type    Type.
	 * @return bool
	 */
	public static function is_opted_out( int $user_id, string $type ): bool {
		return NotificationTypes::is_informational( $type ) && in_array( $type, self::optouts( $user_id ), true );
	}

	/**
	 * Change the opt-out of one type.
	 *
	 * @param Actor  $actor   The person themselves (channel `account`) or system (channel `link`, signature checked by the caller).
	 * @param int    $user_id User.
	 * @param string $type    Informational type.
	 * @param bool   $optout  True = unsubscribe.
	 * @param string $channel account|link.
	 * @return bool Whether something changed.
	 * @throws CourseException `ccap_forbidden` for anybody else, `ccap_invalid_param` for required types.
	 */
	public static function set( Actor $actor, int $user_id, string $type, bool $optout, string $channel ): bool {
		if ( ! NotificationTypes::is_informational( $type ) ) {
			throw new CourseException( 'ccap_invalid_param', 'Required messages cannot be unsubscribed.', 422 );
		}
		$own = 'account' === $channel && ! $actor->is_system() && $actor->user_id === $user_id && $user_id > 0;
		// The signed link may only unsubscribe, never re-subscribe (no operator override, NTF-015).
		$link = 'link' === $channel && $optout;
		if ( ! $own && ! $link ) {
			throw new CourseException( 'ccap_forbidden', 'Only the person can change their notification settings.', 403 );
		}
		$list = self::optouts( $user_id );
		$has  = in_array( $type, $list, true );
		if ( $has === $optout ) {
			return false;
		}
		$list = $optout ? array_values( array_unique( array_merge( $list, array( $type ) ) ) ) : array_values( array_diff( $list, array( $type ) ) );
		update_user_option(
			$user_id,
			self::OPTION,
			array(
				'optout'     => $list,
				'updated_at' => time(),
			)
		);
		AuditLog::write(
			Database::current(),
			$actor,
			'notification_optout_changed',
			'user',
			(string) $user_id,
			null,
			array(
				'type'    => $type,
				'channel' => $channel,
				'optout'  => $optout,
			),
			$user_id
		);
		return true;
	}

	/**
	 * Signature of an unsubscribe link.
	 *
	 * @param int    $user_id User.
	 * @param string $type    Type.
	 * @return string
	 */
	public static function signature( int $user_id, string $type ): string {
		$user = get_userdata( $user_id );
		$reg  = $user instanceof \WP_User ? (string) $user->user_registered : '';
		return hash_hmac( 'sha256', $user_id . ':' . $type . ':' . $reg, wp_salt( 'auth' ) . '|ccap-unsubscribe' );
	}

	/**
	 * Signed one-click URL.
	 *
	 * @param int    $user_id User.
	 * @param string $type    Type.
	 * @return string
	 */
	public static function unsubscribe_url( int $user_id, string $type ): string {
		return add_query_arg(
			array(
				'u' => $user_id,
				't' => $type,
				's' => self::signature( $user_id, $type ),
			),
			LearningArea::url( self::PATH . '/' )
		);
	}

	/**
	 * `template_redirect` for `/<area>/benachrichtigungen/` (works without login for signed links).
	 *
	 * @return void
	 */
	public static function serve(): void {
		$path = get_query_var( LearningArea::QUERY_VAR, null );
		if ( ! is_string( $path ) || self::PATH !== trim( $path, '/' ) ) {
			return;
		}
		\CourseCaptain\Access\ContentProtection::mark_uncacheable();
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		// phpcs:ignore WordPress.Security.NonceVerification -- Signed link (HMAC) or nonce checked in handle().
		$params = array_map( static fn( $v ): string => is_scalar( $v ) ? sanitize_text_field( wp_unslash( (string) $v ) ) : '', array_merge( $_GET, $_POST ) );
		$result = self::handle( $method, $params, get_current_user_id() );
		status_header( $result['status'] );
		nocache_headers();
		echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>' . esc_html__( 'Notifications', 'course-captain' ) . '</title></head><body><main>' . $result['html'] . '</main></body></html>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped while building.
		exit;
	}

	/**
	 * Request handling (testable): signed link GET/POST or account form.
	 *
	 * @param string                $method  GET|POST.
	 * @param array<string, string> $params  Query and body values.
	 * @param int                   $current Logged-in user (0 = anonymous).
	 * @return array{status: int, html: string, changed: bool}
	 */
	public static function handle( string $method, array $params, int $current ): array {
		$title = '<h1>' . esc_html__( 'Notifications', 'course-captain' ) . '</h1>';
		if ( isset( $params['s'] ) ) {
			$user = (int) ( $params['u'] ?? 0 );
			$type = (string) ( $params['t'] ?? '' );
			if ( $user <= 0 || ! NotificationTypes::is_informational( $type ) || ! hash_equals( self::signature( $user, $type ), (string) $params['s'] ) ) {
				return array(
					'status'  => 403,
					'html'    => $title . '<p role="alert">' . esc_html__( 'This link is not valid. Nothing was changed.', 'course-captain' ) . '</p>',
					'changed' => false,
				);
			}
			if ( 'POST' !== $method ) {
				return array(
					'status'  => 200,
					'html'    => $title . '<form method="post" action="' . esc_url( self::unsubscribe_url( $user, $type ) ) . '"><p>' . esc_html__( 'Do you want to stop receiving this kind of message?', 'course-captain' ) . '</p><button type="submit" name="List-Unsubscribe" value="One-Click">' . esc_html__( 'Unsubscribe', 'course-captain' ) . '</button></form>',
					'changed' => false,
				);
			}
			$changed = self::set( Actor::cron(), $user, $type, true, 'link' );
			return array(
				'status'  => 200,
				'html'    => $title . '<p role="status">' . esc_html__( 'You have been unsubscribed.', 'course-captain' ) . '</p>',
				'changed' => $changed,
			);
		}
		if ( $current <= 0 ) {
			return array(
				'status'  => 403,
				'html'    => $title . '<p><a href="' . esc_url( wp_login_url( LearningArea::url( self::PATH . '/' ) ) ) . '">' . esc_html__( 'Log in to manage your notifications.', 'course-captain' ) . '</a></p>',
				'changed' => false,
			);
		}
		$changed = false;
		if ( 'POST' === $method && wp_verify_nonce( (string) ( $params['_wpnonce'] ?? '' ), self::NONCE ) ) {
			foreach ( NotificationTypes::informational() as $type ) {
				$changed = self::set( Actor::user( $current ), $current, $type, ! isset( $params[ 'keep_' . $type ] ), 'account' ) || $changed;
			}
		}
		$optouts = self::optouts( $current );
		$rows    = '';
		foreach ( NotificationTypes::informational() as $type ) {
			$rows .= '<li><label><input type="checkbox" name="keep_' . esc_attr( $type ) . '" value="1"' . checked( ! in_array( $type, $optouts, true ), true, false ) . '> ' . esc_html( self::label( $type ) ) . '</label></li>';
		}
		return array(
			'status'  => 200,
			'html'    => $title . '<form method="post"><fieldset><legend>' . esc_html__( 'Messages I want to receive', 'course-captain' ) . '</legend><ul>' . $rows . '</ul></fieldset><p>' . esc_html__( 'Messages that are necessary for your account or course participation are always sent.', 'course-captain' ) . '</p>' . wp_nonce_field( self::NONCE, '_wpnonce', true, false ) . '<button type="submit">' . esc_html__( 'Save', 'course-captain' ) . '</button></form>',
			'changed' => $changed,
		);
	}

	/**
	 * Readable label of an informational type.
	 *
	 * @param string $type Type.
	 * @return string
	 */
	public static function label( string $type ): string {
		$labels = array(
			'access_granted'       => __( 'New course access', 'course-captain' ),
			'seat_claimed'         => __( 'Group membership confirmed', 'course-captain' ),
			'course_completed'     => __( 'Course completed', 'course-captain' ),
			'certificate_issued'   => __( 'Certificate issued', 'course-captain' ),
			'access_transferred'   => __( 'Access transferred to me', 'course-captain' ),
			'grading_completed'    => __( 'Exam graded', 'course-captain' ),
			'access_expired'       => __( 'Access ended', 'course-captain' ),
			'assignment_submitted' => __( 'New assignment submission (trainers)', 'course-captain' ),
			'assignment_returned'  => __( 'Assignment returned', 'course-captain' ),
			'assignment_graded'    => __( 'Assignment graded', 'course-captain' ),
		);
		return $labels[ $type ] ?? $type;
	}
}
