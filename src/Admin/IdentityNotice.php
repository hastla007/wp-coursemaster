<?php
/**
 * Admin notice of the site identity hold mode (R0-16 §2.3, E-85).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * While the hold is active, site administrators see one notice with the
 * three ways of R0-16 §2.3: "same place", "moved", "copy". The buttons post
 * to `admin-post.php?action=ccap_site_identity` with a nonce; the handler
 * calls {@see SiteIdentity::confirm()}. The notice cannot be dismissed.
 */
final class IdentityNotice {

	public const ACTION = 'ccap_site_identity';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Render the notice for site administrators.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$state = SiteIdentity::check();
		if ( ! $state['held'] ) {
			return;
		}
		$copy = SiteIdentity::STATUS_COPY === $state['state'];
		if ( $copy ) {
			$text = __( 'Course Captain: this site is marked as a copy. Messages are never sent from here.', 'course-captain' );
		} else {
			/* translators: %s: changed parts, e.g. "site address, database" */
			$text = sprintf( __( 'Course Captain holds all outgoing messages because this site changed (%s). Was it restored, moved or copied? Held messages are not sent automatically; review them afterwards (by default they are discarded).', 'course-captain' ), implode( ', ', array_map( array( SiteIdentity::class, 'label' ), $state['changed'] ) ) );
		}
		$buttons = array(
			SiteIdentity::CHOICE_SAME_PLACE => __( 'Restored at the same place', 'course-captain' ),
			SiteIdentity::CHOICE_MOVED      => __( 'Moved to a new address', 'course-captain' ),
			SiteIdentity::CHOICE_COPY       => __( 'Copy or staging (keep messages blocked)', 'course-captain' ),
		);
		if ( $copy ) {
			unset( $buttons[ SiteIdentity::CHOICE_COPY ] );
		}
		echo '<div class="notice ' . ( $copy ? 'notice-info' : 'notice-error' ) . ' ccap-identity-notice"><p>' . esc_html( $text ) . '</p><p>';
		foreach ( $buttons as $choice => $label ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin-inline-end:8px">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
			echo '<input type="hidden" name="choice" value="' . esc_attr( $choice ) . '" />';
			wp_nonce_field( self::ACTION );
			echo '<button type="submit" class="button">' . esc_html( $label ) . '</button></form>';
		}
		echo '</p></div>';
	}

	/**
	 * `admin-post` handler.
	 *
	 * @return void
	 */
	public static function handle(): void {
		check_admin_referer( self::ACTION );
		$choice = isset( $_POST['choice'] ) ? sanitize_key( wp_unslash( (string) $_POST['choice'] ) ) : '';
		try {
			SiteIdentity::confirm( Actor::current(), $choice );
		} catch ( CourseException $e ) {
			wp_die( esc_html__( 'The site identity could not be confirmed.', 'course-captain' ), '', array( 'response' => (int) $e->status ) );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}
}
