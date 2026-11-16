<?php
/**
 * Risk fields of the register (R0-27 Spalte "Risiko", R0-30 §4.2,
 * R0-31 §1.2/§1.3 PD-2, Δ-1, Δ-6).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Policy\Relations;

defined( 'ABSPATH' ) || exit;

/**
 * Maps a change of a tab (before → after) to the confirmations it needs.
 * RC-01…RC-18 are the dialogs of R0-30 §4.2; RC-19…RC-22 are the R
 * fields R0-31 added later without a dialog number (Δ-1 solutions after
 * submit, Δ-6 avatars, `consent_category = functional`, file delivery).
 * Stricter changes never need a confirmation (PD-1). RC-02 (R+) is
 * confirmed by the typed site name instead of a token (E-89).
 */
final class RiskRules {

	/**
	 * Codes in display order.
	 *
	 * @var list<string>
	 */
	public const CODES = array( 'RC-01', 'RC-03', 'RC-04', 'RC-05', 'RC-06', 'RC-07', 'RC-08', 'RC-09', 'RC-10', 'RC-12', 'RC-13', 'RC-14', 'RC-15', 'RC-16', 'RC-19', 'RC-20', 'RC-21', 'RC-22' );

	/**
	 * Confirmations needed for a change.
	 *
	 * @param string               $tab    Tab.
	 * @param array<string, mixed> $before Values before.
	 * @param array<string, mixed> $after  Values after.
	 * @param bool                 $setup  Changed from the setup wizard (W-6 exception for RC-01).
	 * @return list<string>
	 */
	public static function detect( string $tab, array $before, array $after, bool $setup = false ): array {
		$risks   = array();
		$changed = static fn( string $field ): bool => array_key_exists( $field, $after ) && ( $after[ $field ] ?? null ) !== ( $before[ $field ] ?? null );
		switch ( $tab ) {
			case 'general':
				if ( ( $changed( 'learning_area_slug' ) || $changed( 'course_slug' ) ) && ! ( $setup && self::first_setup() ) ) {
					$risks[] = 'RC-01';
				}
				break;
			case 'display':
				if ( $changed( 'require_captions' ) && true === $after['require_captions'] ) {
					$risks[] = 'RC-03';
				}
				break;
			case 'learning':
				if ( $changed( 'mastery_algorithm' ) ) {
					$risks[] = 'RC-04';
				}
				break;
			case 'exams':
				if ( $changed( 'feedback' ) && 'after_submit' === ( $after['feedback']['show_correct_answers_when'] ?? '' ) && 'after_submit' !== ( $before['feedback']['show_correct_answers_when'] ?? '' ) ) {
					$risks[] = 'RC-19';
				}
				break;
			case 'enrollment':
				if ( $changed( 'self_enrollment' ) && true === $after['self_enrollment'] ) {
					$risks[] = 'RC-05';
				}
				if ( $changed( 'groups_enabled' ) && false === $after['groups_enabled'] && self::active_groups() > 0 ) {
					$risks[] = 'RC-06';
				}
				break;
			case 'emails':
				if ( $changed( 'types' ) ) {
					foreach ( (array) $after['types'] as $type => $enabled ) {
						if ( false === $enabled && true === ( $before['types'][ $type ] ?? true ) ) {
							$risks[] = 'RC-08';
							break;
						}
					}
				}
				break;
			case 'privacy':
				if ( $changed( 'auto_erase_on_confirm' ) && true === $after['auto_erase_on_confirm'] ) {
					$risks[] = 'RC-09';
				}
				if ( $changed( 'keep_evidence_on_erasure' ) && ! empty( $after['keep_evidence_on_erasure']['enabled'] ) && empty( $before['keep_evidence_on_erasure']['enabled'] ) ) {
					$risks[] = 'RC-10';
				}
				if ( $changed( 'trusted_proxies' ) && array() !== $after['trusted_proxies'] ) {
					$risks[] = 'RC-12';
				}
				if ( $changed( 'csp_mode' ) && 'report_only' === $after['csp_mode'] ) {
					$risks[] = 'RC-13';
				}
				if ( $changed( 'show_avatars' ) && true === $after['show_avatars'] ) {
					$risks[] = 'RC-20';
				}
				if ( $changed( 'consent_category' ) && 'functional' === $after['consent_category'] ) {
					$risks[] = 'RC-21';
				}
				break;
			case 'hardening':
				// RC-14: switching the login limit on (R0-30) and `force`/`off` of an active limit (R0-27, R0-31 §1.3).
				foreach ( array( 'login_limit', 'reset_limit' ) as $field ) {
					if ( $changed( $field ) && ( 'force' === $after[ $field ] || ( 'login_limit' === $field && 'off' === ( $before[ $field ] ?? 'off' ) ) || ( 'off' === $after[ $field ] && self::hardening_confirmed() ) ) ) {
						$risks[] = 'RC-14';
						break;
					}
				}
				if ( $changed( 'xmlrpc' ) && 'off' !== $after['xmlrpc'] && 'off' === ( $before['xmlrpc'] ?? 'off' ) ) {
					$risks[] = 'RC-15';
				}
				break;
			case 'advanced':
				if ( $changed( 'file_delivery' ) && 'php' !== $after['file_delivery'] ) {
					$risks[] = 'RC-22';
				}
				break;
		}
		/**
		 * Additional confirmations of a change (e.g. RC-07 of the role tab).
		 *
		 * @param list<string>         $risks  Codes so far.
		 * @param string               $tab    Tab.
		 * @param array<string, mixed> $before Before.
		 * @param array<string, mixed> $after  After.
		 */
		$risks = (array) apply_filters( 'course_captain_settings_risks', $risks, $tab, $before, $after );
		return array_values( array_unique( array_filter( $risks, 'is_string' ) ) );
	}

	/**
	 * W-6: no slug was saved yet and no course is published.
	 *
	 * @return bool
	 */
	public static function first_setup(): bool {
		$stored = get_option( Settings::OPTION_PREFIX . 'general' );
		$values = is_array( $stored ) && is_array( $stored['values'] ?? null ) ? $stored['values'] : array();
		if ( isset( $values['learning_area_slug'] ) || isset( $values['course_slug'] ) ) {
			return false;
		}
		return 0 === (int) ( wp_count_posts( Relations::COURSE_TYPE )->publish ?? 0 );
	}

	/**
	 * Number of active groups (RC-06).
	 *
	 * @return int
	 */
	public static function active_groups(): int {
		$db = \CourseCaptain\Storage\Database::current();
		return (int) $db->scalar( 'SELECT COUNT(*) FROM `' . $db->table( 'ccap_group' ) . "` WHERE status = 'active'" );
	}

	/**
	 * Whether the hardening tab was saved once (HM-0).
	 *
	 * @return bool
	 */
	private static function hardening_confirmed(): bool {
		return Settings::rev( 'hardening' ) > 0;
	}
}
