<?php
/**
 * Test mail from the emails tab and setup step 5 (R0-30 §2.4, F-63, F4.4; RDY-07).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Notification\NotificationService;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the test mail to the admin's own profile address (5 per hour,
 * fail-closed) and remembers the last successful send for the readiness
 * check. A failed send is a warning, never a blocker.
 */
final class TestMail {

	public const ACTION = 'ccap_test_mail';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Admin-post entry.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$post   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in process().
		$result = self::process( $post, get_current_user_id() );
		$back   = isset( $post['back'] ) && 'setup' === $post['back']
			? add_query_arg(
				array(
					'page'           => SetupWizard::SLUG,
					'step'           => 'mail_privacy',
					'ccap_test_mail' => $result,
				),
				admin_url( 'admin.php' )
			)
			: SettingsScreen::url( 'emails', array( 'ccap_test_mail' => $result ) );
		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Testable core.
	 *
	 * @param array<string, mixed> $post    POST.
	 * @param int                  $user_id User.
	 * @return string `sent`, `failed`, `ccap_rate_limited`, `ccap_forbidden`, `ccap_invalid_nonce`.
	 */
	public static function process( array $post, int $user_id ): string {
		if ( false === wp_verify_nonce( (string) ( $post['_wpnonce'] ?? '' ), self::ACTION ) ) {
			return 'ccap_invalid_nonce';
		}
		if ( $user_id <= 0 || ! user_can( $user_id, Settings::CAP ) ) {
			return 'ccap_forbidden';
		}
		try {
			$out = NotificationService::current()->send_test_mail( Actor::user( $user_id ) );
		} catch ( CourseException $e ) {
			return $e->error_code;
		}
		if ( 'sent' !== $out['status'] ) {
			return 'failed';
		}
		$state               = get_option( NotificationService::STATE_OPTION );
		$state               = is_array( $state ) ? $state : array();
		$state['test_ok_at'] = gmdate( 'c' );
		update_option( NotificationService::STATE_OPTION, $state, false );
		return 'sent';
	}

	/**
	 * Button form (own form, so Enter in a field never sends a test mail).
	 *
	 * @param int    $user_id User.
	 * @param string $back    `settings` or `setup`.
	 * @return string
	 */
	public static function form( int $user_id, string $back ): string {
		$user = get_userdata( $user_id );
		/* translators: %s: email address of the current user */
		$label = sprintf( __( 'Send test mail to %s', 'course-captain' ), $user instanceof \WP_User ? $user->user_email : '' );
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccap-inline-form"><input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '"><input type="hidden" name="back" value="' . esc_attr( $back ) . '">'
			. wp_nonce_field( self::ACTION, '_wpnonce', true, false )
			. '<button type="submit" class="button" aria-describedby="ccap-test-mail-help">' . esc_html( $label ) . '</button> <span id="ccap-test-mail-help" class="description">' . esc_html__( 'The test mail goes to the address of your profile.', 'course-captain' ) . '</span></form>';
	}

	/**
	 * Result message in the status region.
	 *
	 * @param string $result Result.
	 * @return string
	 */
	public static function message( string $result ): string {
		switch ( $result ) {
			case 'sent':
				return __( 'Test mail sent. Check your inbox (also spam).', 'course-captain' );
			case 'failed':
				return __( 'Warning: the test mail could not be sent. A frequent cause: the server may not send mails. An SMTP plugin helps. You can continue the setup anyway.', 'course-captain' );
			case 'ccap_rate_limited':
				return __( 'You sent 5 test mails in the last hour. The next one is possible in one hour.', 'course-captain' );
			case 'ccap_forbidden':
				return __( 'You are not allowed to send test mails.', 'course-captain' );
			case 'ccap_invalid_nonce':
				return __( 'The form has expired. Reload the page and try again.', 'course-captain' );
		}
		return '';
	}
}
