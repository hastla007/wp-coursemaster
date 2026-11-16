<?php
/**
 * Bundled admin notices (R0-05 GM-140).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Notices are collected per request and shown as **one** bundle, errors
 * first. Errors can only be snoozed (they come back), other notices can be
 * dismissed. State is per user (user meta `ccap_notice_state`, at most 50
 * entries); the action link carries a nonce of the current user only.
 */
final class Notices {

	public const META       = 'ccap_notice_state';
	public const MAX        = 50;
	public const SNOOZE     = 86400;
	public const SEVERITIES = array( 'error', 'warning', 'info' );

	/**
	 * Notices of the request.
	 *
	 * @var array<string, array{severity: string, message: string}>
	 */
	private static array $queue = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
		add_action( 'admin_post_ccap_notice', array( self::class, 'handle' ) );
	}

	/**
	 * Add a notice.
	 *
	 * @param string $code     Stable code.
	 * @param string $severity error|warning|info.
	 * @param string $message  Translated text.
	 * @return void
	 */
	public static function add( string $code, string $severity, string $message ): void {
		self::$queue[ sanitize_key( $code ) ] = array(
			'severity' => in_array( $severity, self::SEVERITIES, true ) ? $severity : 'info',
			'message'  => $message,
		);
	}

	/**
	 * Visible notices for a user, errors first.
	 *
	 * @param int      $user_id User.
	 * @param int|null $now     Time.
	 * @return array<string, array{severity: string, message: string}>
	 */
	public static function visible( int $user_id, ?int $now = null ): array {
		$now   = $now ?? time();
		$state = self::state( $user_id );
		$out   = array_filter(
			self::$queue,
			static function ( array $notice, string $code ) use ( $state, $now ): bool {
				$entry = $state[ $code ] ?? null;
				if ( null === $entry ) {
					return true;
				}
				return 'dismissed' === $entry ? 'error' === $notice['severity'] : (int) $entry <= $now;
			},
			ARRAY_FILTER_USE_BOTH
		);
		uksort(
			$out,
			static function ( string $a, string $b ) use ( $out ): int {
				$order = array_flip( self::SEVERITIES );
				return array( $order[ $out[ $a ]['severity'] ], $a ) <=> array( $order[ $out[ $b ]['severity'] ], $b );
			}
		);
		return $out;
	}

	/**
	 * Snooze (errors) or dismiss (others) a notice for a user.
	 *
	 * @param int      $user_id User.
	 * @param string   $code    Code.
	 * @param bool     $dismiss Dismiss permanently (ignored for errors).
	 * @param int|null $now     Time.
	 * @return void
	 */
	public static function hide( int $user_id, string $code, bool $dismiss, ?int $now = null ): void {
		$code  = sanitize_key( $code );
		$state = self::state( $user_id );
		$error = 'error' === ( self::$queue[ $code ]['severity'] ?? 'error' );
		$state = array( $code => $dismiss && ! $error ? 'dismissed' : ( $now ?? time() ) + self::SNOOZE ) + $state;
		$state = array_slice( $state, 0, self::MAX, true );
		update_user_meta( $user_id, self::META, $state );
	}

	/**
	 * Render the bundle.
	 *
	 * @return void
	 */
	public static function render(): void {
		$user    = get_current_user_id();
		$notices = self::visible( $user );
		if ( array() === $notices ) {
			return;
		}
		$first = reset( $notices );
		echo '<div class="notice notice-' . esc_attr( $first['severity'] ) . '"><p><strong>' . esc_html__( 'Course Captain', 'course-captain' ) . '</strong></p><ul>';
		foreach ( $notices as $code => $notice ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=ccap_notice&code=' . rawurlencode( $code ) . ( 'error' === $notice['severity'] ? '' : '&dismiss=1' ) ), 'ccap_notice_' . $code );
			echo '<li><span class="ccap-severity">' . esc_html( self::label( $notice['severity'] ) ) . ':</span> ' . esc_html( $notice['message'] ) . ' <a href="' . esc_url( $url ) . '">' . esc_html( 'error' === $notice['severity'] ? __( 'Remind me tomorrow', 'course-captain' ) : __( 'Dismiss', 'course-captain' ) ) . '</a></li>';
		}
		echo '</ul></div>';
	}

	/**
	 * `admin-post` handler (nonce bound to the current user).
	 *
	 * @return void
	 */
	public static function handle(): void {
		$code = isset( $_GET['code'] ) ? sanitize_key( wp_unslash( (string) $_GET['code'] ) ) : '';
		check_admin_referer( 'ccap_notice_' . $code );
		self::hide( get_current_user_id(), $code, isset( $_GET['dismiss'] ) );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Severity label (text instead of symbols, GM-141).
	 *
	 * @param string $severity Severity.
	 * @return string
	 */
	private static function label( string $severity ): string {
		return 'error' === $severity ? __( 'Error', 'course-captain' ) : ( 'warning' === $severity ? __( 'Warning', 'course-captain' ) : __( 'Note', 'course-captain' ) );
	}

	/**
	 * Stored state.
	 *
	 * @param int $user_id User.
	 * @return array<string, int|string>
	 */
	private static function state( int $user_id ): array {
		$state = get_user_meta( $user_id, self::META, true );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Reset the request queue (tests).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$queue = array();
	}
}
