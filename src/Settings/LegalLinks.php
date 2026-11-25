<?php
/**
 * Links to the operator's legal texts (SET-CC-06, `general.legal_links`).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One source for the footer of the learning area, the mail footer and the
 * mail placeholders `imprint_url`, `terms_url`, `withdrawal_url` and
 * `privacy_policy_url`. An empty privacy link falls back to the privacy
 * policy page of WordPress (Settings › Privacy). Links that are empty are
 * never shown; which of them are legally required is the operator's
 * decision (marked "Legal review" in the settings).
 */
final class LegalLinks {

	/** Mail placeholder per sub-key. */
	public const PLACEHOLDERS = array(
		'imprint'    => 'imprint_url',
		'terms'      => 'terms_url',
		'withdrawal' => 'withdrawal_url',
		'privacy'    => 'privacy_policy_url',
	);

	/**
	 * Effective URLs per sub-key ('' = not set).
	 *
	 * @return array{imprint: string, terms: string, withdrawal: string, privacy: string}
	 */
	public static function urls(): array {
		$stored = (array) Settings::get( 'general.legal_links' );
		$out    = array(
			'imprint'    => '',
			'terms'      => '',
			'withdrawal' => '',
			'privacy'    => '',
		);
		foreach ( SettingsSchema::LEGAL_LINKS as $key ) {
			$out[ $key ] = is_string( $stored[ $key ] ?? null ) ? (string) $stored[ $key ] : '';
		}
		if ( '' === $out['privacy'] ) {
			$out['privacy'] = (string) get_privacy_policy_url();
		}
		return $out;
	}

	/**
	 * Link texts in the current locale.
	 *
	 * @return array{imprint: string, terms: string, withdrawal: string, privacy: string}
	 */
	public static function labels(): array {
		return array(
			'imprint'    => __( 'Imprint', 'course-captain' ),
			'terms'      => __( 'Terms and conditions', 'course-captain' ),
			'withdrawal' => __( 'Right of withdrawal', 'course-captain' ),
			'privacy'    => __( 'Privacy policy', 'course-captain' ),
		);
	}

	/**
	 * Links that are set, in display order: key => [label, url].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function present(): array {
		$labels = self::labels();
		$out    = array();
		foreach ( self::urls() as $key => $url ) {
			if ( '' !== $url ) {
				$out[ $key ] = array( $labels[ $key ], $url );
			}
		}
		return $out;
	}

	/**
	 * Mail placeholder values (every placeholder exists, '' when not set).
	 *
	 * @return array<string, string>
	 */
	public static function mail_vars(): array {
		$out = array();
		foreach ( self::urls() as $key => $url ) {
			$out[ self::PLACEHOLDERS[ $key ] ] = $url;
		}
		return $out;
	}

	/**
	 * List items for a footer navigation of the learning area.
	 *
	 * @return string `<li>` elements, '' when no link is set.
	 */
	public static function list_items(): string {
		$html = '';
		foreach ( self::present() as $link ) {
			$html .= '<li><a href="' . esc_url( $link[1] ) . '">' . esc_html( $link[0] ) . '</a></li>';
		}
		return $html;
	}
}
