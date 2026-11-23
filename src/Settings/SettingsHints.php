<?php
/**
 * Hint lines at the end of a settings tab (SET-CC-01, E-346 S1/S5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

use CourseCaptain\Admin\SettingsFields;

defined( 'ABSPATH' ) || exit;

/**
 * A hint line is plain data `{id, tab, title, text, link}` registered
 * through the filter `course_captain_settings_hints_v1`. It is a grey text
 * line at the end of a tab: no field, no switch, no stored value, no
 * function code and no rights key (E-346 S1). The core list is empty (S5:
 * a line appears only once the function is sold; an extension adds it).
 * Every person can hide a line for themselves; the hidden ids are a
 * per-site user option (MS-R-05, uninstall manifest).
 */
final class SettingsHints {

	public const FILTER    = 'course_captain_settings_hints_v1';
	public const USER_KEY  = 'course_captain_hints_dismissed';
	public const MAX       = 100;
	public const TITLE_MAX = 80;
	public const TEXT_MAX  = 300;

	/**
	 * The fields of a hint line; anything else is dropped.
	 *
	 * @var list<string>
	 */
	public const SHAPE = array( 'id', 'tab', 'title', 'text', 'link' );

	/**
	 * Valid hint lines (core list empty, S5), keyed by id.
	 *
	 * @return array<string, array{id: string, tab: string, title: string, text: string, link: string}>
	 */
	public static function all(): array {
		/**
		 * Hint lines at the end of a settings tab (contract v1).
		 *
		 * @since 1.0.0
		 * @param list<array{id: string, tab: string, title: string, text: string, link?: string}> $hints Hint lines (core: none).
		 */
		$raw = apply_filters( 'course_captain_settings_hints_v1', array() );
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $hint ) {
			$hint = self::normalize( $hint );
			if ( null !== $hint && ! isset( $out[ $hint['id'] ] ) && count( $out ) < self::MAX ) {
				$out[ $hint['id'] ] = $hint;
			}
		}
		return $out;
	}

	/**
	 * Visible lines of a tab for a person.
	 *
	 * @param string $tab     Tab.
	 * @param int    $user_id Person.
	 * @return list<array{id: string, tab: string, title: string, text: string, link: string}>
	 */
	public static function for_tab( string $tab, int $user_id ): array {
		$hidden = self::dismissed( $user_id );
		return array_values( array_filter( self::all(), static fn( array $hint ): bool => $tab === $hint['tab'] && ! in_array( $hint['id'], $hidden, true ) ) );
	}

	/**
	 * Ids a person has hidden on this site.
	 *
	 * @param int $user_id Person.
	 * @return list<string>
	 */
	public static function dismissed( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		$stored = get_user_option( self::USER_KEY, $user_id );
		return is_array( $stored ) ? array_values( array_filter( array_map( 'strval', $stored ), static fn( string $id ): bool => 1 === preg_match( '/^[a-z0-9_]{1,40}$/', $id ) ) ) : array();
	}

	/**
	 * Hide a line for a person (only registered ids; at most {@see self::MAX}).
	 *
	 * @param int    $user_id Person.
	 * @param string $id      Hint id.
	 * @return bool Hidden now or already.
	 */
	public static function dismiss( int $user_id, string $id ): bool {
		if ( $user_id <= 0 || ! isset( self::all()[ $id ] ) ) {
			return false;
		}
		$hidden = self::dismissed( $user_id );
		if ( ! in_array( $id, $hidden, true ) ) {
			$hidden[] = $id;
			update_user_option( $user_id, self::USER_KEY, array_slice( $hidden, -self::MAX ) );
		}
		return true;
	}

	/**
	 * Data shape check: exactly the text fields, no code, no rights.
	 *
	 * @param mixed $hint Raw entry.
	 * @return array{id: string, tab: string, title: string, text: string, link: string}|null
	 */
	public static function normalize( $hint ): ?array {
		if ( ! is_array( $hint ) ) {
			return null;
		}
		$hint += array( 'link' => '' );
		foreach ( self::SHAPE as $field ) {
			if ( ! is_string( $hint[ $field ] ?? null ) ) {
				return null;
			}
		}
		$id    = $hint['id'];
		$tab   = $hint['tab'];
		$title = sanitize_text_field( $hint['title'] );
		$text  = sanitize_text_field( $hint['text'] );
		$link  = trim( $hint['link'] );
		if ( 1 !== preg_match( '/^[a-z0-9_]{1,40}$/', $id ) || ! in_array( $tab, SettingsFields::TAB_ORDER, true ) || '' === $title || '' === $text ) {
			return null;
		}
		if ( mb_strlen( $title ) > self::TITLE_MAX || mb_strlen( $text ) > self::TEXT_MAX ) {
			return null;
		}
		// External links only over https; pages of this administration are fine on any scheme.
		if ( '' !== $link && ! \CourseCaptain\Activity\SettingsSchema::is_https_url( $link ) && ! str_starts_with( $link, admin_url() ) ) {
			return null;
		}
		return array(
			'id'    => $id,
			'tab'   => $tab,
			'title' => $title,
			'text'  => $text,
			'link'  => '' === $link ? '' : esc_url_raw( $link ),
		);
	}
}
