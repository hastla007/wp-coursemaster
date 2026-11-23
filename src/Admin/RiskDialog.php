<?php
/**
 * Texts and confirmation page of the risk dialogs (R0-30 §4, D-1…D-8).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Policy\Relations;
use CourseCaptain\Settings\RiskRules;

defined( 'ABSPATH' ) || exit;

/**
 * The confirmation is a server-rendered page (D-7), so it works with and
 * without JavaScript and against rebuilt requests: one section (`h3`) per
 * risk (D-5), concrete consequences with numbers (D-2), the action button
 * names the action (D-3) and the initial focus is on "Cancel".
 */
final class RiskDialog {

	/**
	 * Title, text and action label of a risk.
	 *
	 * @param string               $code   RC code.
	 * @param array<string, mixed> $before Values before.
	 * @param array<string, mixed> $after  Values after.
	 * @return array{title: string, text: string, action: string}
	 */
	public static function text( string $code, array $before, array $after ): array {
		$home      = untrailingslashit( home_url() );
		$published = (int) ( wp_count_posts( Relations::COURSE_TYPE )->publish ?? 0 );
		switch ( $code ) {
			case 'RC-01':
				$field = ( $after['learning_area_slug'] ?? null ) !== ( $before['learning_area_slug'] ?? null ) ? 'learning_area_slug' : 'course_slug';
				return array(
					'title'  => __( 'Change address?', 'course-captain' ),
					/* translators: 1: new address, 2: old address */
					'text'   => sprintf( __( 'New address: %1$s (so far %2$s). Links in emails, on certificates and in bookmarks point to the old address. Course Captain redirects them permanently as long as no other page uses the old address. Search engines take over the new address only after some time.', 'course-captain' ), $home . '/' . (string) ( $after[ $field ] ?? '' ) . '/', $home . '/' . (string) ( $before[ $field ] ?? '' ) . '/' ),
					'action' => __( 'Change address', 'course-captain' ),
				);
			case 'RC-03':
				return array(
					'title'  => __( 'Make captions mandatory?', 'course-captain' ),
					'text'   => __( 'Published courses with videos without captions stay online. New versions of these courses can only be published once the captions are added.', 'course-captain' ),
					'action' => __( 'Require captions', 'course-captain' ),
				);
			case 'RC-04':
				return array(
					'title'  => __( 'Change repetition strategy?', 'course-captain' ),
					'text'   => __( 'New answers are evaluated with the new strategy from now on. Existing learning progress is kept; the choice of the next practice questions can change.', 'course-captain' ),
					'action' => __( 'Change strategy', 'course-captain' ),
				);
			case 'RC-05':
				/* translators: %d: number of published courses */
				$text = sprintf( _n( 'Every person with an account on this website can then enroll themselves and gets access immediately. This applies to %d published course without an own setting and to all future courses.', 'Every person with an account on this website can then enroll themselves and gets access immediately. This applies to %d published courses without an own setting and to all future courses.', $published, 'course-captain' ), $published );
				if ( get_option( 'users_can_register' ) ) {
					$text .= ' ' . __( 'As anyone can register on this website, this practically applies to all visitors.', 'course-captain' );
				}
				return array(
					'title'  => __( 'Switch self-enrollment on?', 'course-captain' ),
					'text'   => $text . ' ' . __( 'Courses with the own setting “off” stay closed.', 'course-captain' ),
					'action' => __( 'Switch self-enrollment on', 'course-captain' ),
				);
			case 'RC-06':
				$groups = RiskRules::active_groups();
				return array(
					'title'  => __( 'Switch groups off?', 'course-captain' ),
					/* translators: %d: number of active groups */
					'text'   => sprintf( _n( '%d active group stays; members keep their access and existing groups can still be managed. You can create new groups only after switching groups on again.', '%d active groups stay; members keep their access and existing groups can still be managed. You can create new groups only after switching groups on again.', $groups, 'course-captain' ), $groups ),
					'action' => __( 'Switch groups off', 'course-captain' ),
				);
			case 'RC-08':
				return array(
					'title'  => __( 'Switch message off?', 'course-captain' ),
					'text'   => __( 'Learners no longer receive this message. Already planned messages of this type are not sent.', 'course-captain' ),
					'action' => __( 'Switch message off', 'course-captain' ),
				);
			case 'RC-09':
				return array(
					'title'  => __( 'Delete accounts automatically?', 'course-captain' ),
					'text'   => __( 'When a person confirms their erasure request, WordPress deletes their account without further check by you, including learning progress and exam attempts. Accounts with administrative permissions are excluded.', 'course-captain' ),
					'action' => __( 'Delete automatically', 'course-captain' ),
				);
			case 'RC-10':
				return array(
					'title'  => __( 'Keep evidence despite erasure requests?', 'course-captain' ),
					/* translators: %s: reason entered by the admin */
					'text'   => sprintf( __( 'Exam attempts and certificates of people requesting erasure are kept pseudonymised instead of deleted. Your reason appears in the result of every request: “%s”. Without a legal basis this is not allowed.', 'course-captain' ), (string) ( $after['keep_evidence_on_erasure']['reason'] ?? '' ) ),
					'action' => __( 'Keep evidence', 'course-captain' ),
				);
			case 'RC-12':
				$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '-';
				return array(
					'title'  => __( 'Change trusted proxies?', 'course-captain' ),
					/* translators: 1: list of proxies, 2: IP address of the current request */
					'text'   => sprintf( __( 'Course Captain then takes the address from the X-Forwarded-For header when a request comes from %1$s. If an address here does not belong to your own proxy, attackers can bypass limits and lockouts. Your current request comes from %2$s.', 'course-captain' ), implode( ', ', array_map( 'strval', (array) ( $after['trusted_proxies'] ?? array() ) ) ), $remote ),
					'action' => __( 'Save proxies', 'course-captain' ),
				);
			case 'RC-13':
				return array(
					'title'  => __( 'Only report the Content Security Policy?', 'course-captain' ),
					'text'   => __( 'Course Captain pages then no longer block injected scripts but only report them. Use this only briefly to find conflicts with the theme.', 'course-captain' ),
					'action' => __( 'Report only', 'course-captain' ),
				);
			case 'RC-14':
				return array(
					'title'  => __( 'Change the login limit?', 'course-captain' ),
					'text'   => __( 'With the login limit, further login attempts are blocked until the end of the time window after 10 failures from one address or 5 for one account within 15 minutes. If the counter storage fails, login and “Lost password” are blocked except on known devices. “Force” keeps the limit active even next to a security plugin (double lockouts possible); switching it off removes the protection. Emergency: define( \'COURSE_CAPTAIN_HARDENING\', \'off\' ); in wp-config.php or wp course-captain hardening disable.', 'course-captain' ),
					'action' => __( 'Change login limit', 'course-captain' ),
				);
			case 'RC-15':
				return array(
					'title'  => __( 'Switch XML-RPC off?', 'course-captain' ),
					'text'   => __( 'External editors, the WordPress app and pingbacks can then no longer connect.', 'course-captain' ),
					'action' => __( 'Switch XML-RPC off', 'course-captain' ),
				);
			case 'RC-19':
				return array(
					'title'  => __( 'Show correct answers after every submission?', 'course-captain' ),
					'text'   => __( 'Learners then see the solutions while further attempts are still open; question pools can be collected across repeated attempts. Running attempts keep their setting.', 'course-captain' ),
					'action' => __( 'Show solutions', 'course-captain' ),
				);
			case 'RC-20':
				return array(
					'title'  => __( 'Show profile pictures?', 'course-captain' ),
					'text'   => __( 'Every page view with a profile picture transfers a hash of the email address to Gravatar, a third-party service.', 'course-captain' ),
					'action' => __( 'Show profile pictures', 'course-captain' ),
				);
			case 'RC-21':
				return array(
					'title'  => __( 'Use the category “Functional”?', 'course-captain' ),
					'text'   => __( 'Consent tools usually treat functional services as not requiring consent. External videos then load without asking learners.', 'course-captain' ),
					'action' => __( 'Use “Functional”', 'course-captain' ),
				);
		}
		/**
		 * Text of further risks (e.g. RC-07 of the role tab).
		 *
		 * @param array{title: string, text: string, action: string} $text   Text.
		 * @param string                                             $code   Code.
		 * @param array<string, mixed>                               $before Before.
		 * @param array<string, mixed>                               $after  After.
		 */
		return (array) apply_filters(
			'course_captain_risk_text',
			array(
				'title'  => __( 'Confirm change?', 'course-captain' ),
				'text'   => __( 'This change weakens a protection or changes many accounts at once.', 'course-captain' ),
				'action' => __( 'Confirm change', 'course-captain' ),
			),
			$code,
			$before,
			$after
		) + array(
			'title'  => '',
			'text'   => '',
			'action' => '',
		);
	}

	/**
	 * Confirmation page.
	 *
	 * @param array                $risks  Codes.
	 * @param array<string, mixed> $before Before.
	 * @param array<string, mixed> $after  After.
	 * @param array<string, mixed> $form   `action`, `nonce`, `token`, `hidden`, `cancel`.
	 * @phpstan-param array<mixed> $risks
	 * @return string
	 */
	public static function page( array $risks, array $before, array $after, array $form ): string {
		$texts = array();
		foreach ( $risks as $code ) {
			$texts[ (string) $code ] = self::text( (string) $code, $before, $after );
		}
		$single = 1 === count( $texts );
		$first  = reset( $texts );
		$title  = $single && is_array( $first ) ? $first['title'] : __( 'Confirm changes', 'course-captain' );
		$action = $single && is_array( $first ) ? $first['action'] : __( 'Confirm changes and save', 'course-captain' );
		$html   = '<section class="ccap-confirm" aria-labelledby="ccap-confirm-title" aria-describedby="ccap-confirm-text"><h2 id="ccap-confirm-title">' . esc_html( $title ) . '</h2><div id="ccap-confirm-text">';
		foreach ( $texts as $code => $text ) {
			if ( ! $single ) {
				$html .= '<h3>' . esc_html( $text['title'] ) . '</h3>';
			}
			$html .= '<p data-ccap-risk="' . esc_attr( $code ) . '">' . esc_html( $text['text'] ) . '</p>';
		}
		$html .= '</div><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$html .= '<input type="hidden" name="action" value="' . esc_attr( (string) $form['action'] ) . '"><input type="hidden" name="_ccap_confirm" value="1"><input type="hidden" name="_ccap_token" value="' . esc_attr( (string) $form['token'] ) . '">';
		foreach ( (array) ( $form['hidden'] ?? array() ) as $name => $value ) {
			$html .= '<input type="hidden" name="' . esc_attr( (string) $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		$html .= wp_nonce_field( (string) $form['nonce'], '_wpnonce', true, false );
		$html .= '<button type="submit" class="button button-primary">' . esc_html( $action ) . '</button> ';
		$html .= '<a class="button" href="' . esc_url( (string) $form['cancel'] ) . '" data-ccap-focus="1">' . esc_html__( 'Cancel', 'course-captain' ) . '</a></form></section>';
		return $html;
	}
}
