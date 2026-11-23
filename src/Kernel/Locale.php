<?php
/**
 * UI locale resolution for the own text domain (R0-25 §3.0/§3.1, R0-15 §3.5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * - Variant fallback (D-25-07): a locale without own translation file uses a
 *   variant of the same language (`de_AT` → `de_DE`), else the English
 *   source texts. Only for the domain `course-captain`.
 * - The resolved locale (not the requested one) drives `lang`/`dir` of the
 *   learning area, so texts and formats match.
 * - Text direction of BCP 47 tags (LOC-003).
 * - Learning area in the learner's profile language (E-124, D-25-10).
 */
final class Locale {

	public const DOMAIN = 'course-captain';

	/**
	 * Languages (and scripts) written right to left.
	 *
	 * @var list<string>
	 */
	private const RTL_LANGUAGES = array( 'ar', 'arc', 'azb', 'ckb', 'dv', 'fa', 'ha', 'he', 'khw', 'ks', 'ps', 'sd', 'syr', 'ug', 'ur', 'yi' );

	/**
	 * RTL scripts (ISO 15924) that override the language default (`ku-Arab`).
	 *
	 * @var list<string>
	 */
	private const RTL_SCRIPTS = array( 'arab', 'hebr', 'thaa', 'syrc', 'nkoo', 'adlm', 'rohg' );

	/**
	 * Fixed variant table (R0-25 §3.0).
	 *
	 * @var array<string, list<string>>
	 */
	private const VARIANTS = array(
		'de_AT'          => array( 'de_DE', 'de_DE_formal' ),
		'de_CH'          => array( 'de_DE', 'de_DE_formal' ),
		'de_CH_informal' => array( 'de_DE', 'de_DE_formal' ),
		'de_DE_formal'   => array( 'de_DE' ),
		'de_DE'          => array( 'de_DE_formal' ),
		'pt_PT'          => array( 'pt_BR' ),
		'pt_PT_ao90'     => array( 'pt_PT', 'pt_BR' ),
		'pt_BR'          => array( 'pt_PT' ),
		'nl_BE'          => array( 'nl_NL' ),
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'load_translation_file', array( self::class, 'translation_file' ), 10, 3 );
		add_filter( 'lang_dir_for_domain', array( self::class, 'lang_dir' ), 10, 3 );
	}

	/**
	 * Directory of the bundled translations.
	 *
	 * @return string
	 */
	public static function bundled_dir(): string {
		return dirname( __DIR__, 2 ) . '/languages';
	}

	/**
	 * `lang_dir_for_domain`: bundled directory when WordPress found none.
	 *
	 * @param string|false $path   Path.
	 * @param string       $domain Domain.
	 * @param string       $locale Locale.
	 * @return string|false
	 */
	public static function lang_dir( $path, $domain, $locale ) {
		if ( self::DOMAIN !== $domain || false !== $path ) {
			return $path;
		}
		return null === self::file_for( (string) $locale ) ? $path : self::bundled_dir() . '/';
	}

	/**
	 * `load_translation_file`: missing file → bundled file → variant of the same language.
	 *
	 * @param string $file   File.
	 * @param string $domain Domain.
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function translation_file( $file, $domain, $locale = '' ) {
		$file = (string) $file;
		if ( self::DOMAIN !== $domain || is_readable( $file ) ) {
			return $file;
		}
		$locale = '' !== (string) $locale ? (string) $locale : determine_locale();
		$suffix = str_ends_with( $file, '.l10n.php' ) ? '.l10n.php' : '.mo';
		foreach ( array_merge( array( $locale ), self::variants( $locale ) ) as $candidate ) {
			foreach ( array( dirname( $file ), WP_LANG_DIR . '/plugins', self::bundled_dir() ) as $dir ) {
				$path = $dir . '/' . self::DOMAIN . '-' . $candidate . $suffix;
				if ( is_readable( $path ) ) {
					return $path;
				}
			}
		}
		return $file;
	}

	/**
	 * Variants of the same language, most specific first (no `en_*`: source texts).
	 *
	 * @param string $locale Locale.
	 * @return list<string>
	 */
	public static function variants( string $locale ): array {
		$language = strtolower( (string) strtok( $locale, '_' ) );
		if ( 'en' === $language ) {
			return array();
		}
		$list = self::VARIANTS[ $locale ] ?? array();
		if ( 'es' === $language && 'es_ES' !== $locale ) {
			$list[] = 'es_ES';
		}
		if ( 'fr' === $language && 'fr_FR' !== $locale ) {
			$list[] = 'fr_FR';
		}
		foreach ( array( WP_LANG_DIR . '/plugins', self::bundled_dir() ) as $dir ) {
			foreach ( (array) glob( $dir . '/' . self::DOMAIN . '-' . $language . '{_,.}*', GLOB_BRACE ) as $path ) {
				if ( 1 === preg_match( '/' . preg_quote( self::DOMAIN, '/' ) . '-([a-z]{2,3}(?:_[A-Za-z0-9]+)*)\.(?:mo|l10n\.php)$/', (string) $path, $m ) && $m[1] !== $locale ) {
					$list[] = $m[1];
				}
			}
		}
		return array_values( array_unique( $list ) );
	}

	/**
	 * Translation file for exactly this locale, or null.
	 *
	 * @param string $locale Locale.
	 * @return string|null
	 */
	private static function file_for( string $locale ): ?string {
		foreach ( array( WP_LANG_DIR . '/plugins', self::bundled_dir() ) as $dir ) {
			foreach ( array( '.l10n.php', '.mo' ) as $suffix ) {
				$path = $dir . '/' . self::DOMAIN . '-' . $locale . $suffix;
				if ( is_readable( $path ) ) {
					return $path;
				}
			}
		}
		return null;
	}

	/**
	 * Locale whose texts are shown for a requested locale (§3.0): exact file,
	 * variant of the same language, else `en_US` (source texts, E-121).
	 *
	 * @param string $locale Requested locale.
	 * @return string
	 */
	public static function resolve( string $locale ): string {
		if ( '' === $locale || str_starts_with( $locale, 'en_' ) || 'en' === $locale ) {
			return '' === $locale ? 'en_US' : $locale;
		}
		foreach ( array_merge( array( $locale ), self::variants( $locale ) ) as $candidate ) {
			if ( null !== self::file_for( $candidate ) ) {
				return $candidate;
			}
		}
		return 'en_US';
	}

	/**
	 * BCP 47 tag of the current UI (resolved request locale).
	 *
	 * @return string
	 */
	public static function ui_tag(): string {
		// WordPress variants such as `de_DE_formal` are not BCP 47 subtags: the language stays `de-DE`.
		$locale = (string) preg_replace( '/_(formal|informal)$/', '', self::resolve( determine_locale() ) );
		return str_replace( '_', '-', $locale );
	}

	/**
	 * Text direction of a BCP 47 tag or WordPress locale (LOC-003).
	 *
	 * @param string $tag Tag (`ar`, `he-IL`, `ku-Arab`, `de_DE`).
	 * @return string `rtl` or `ltr`.
	 */
	public static function dir( string $tag ): string {
		$parts = explode( '-', str_replace( '_', '-', strtolower( trim( $tag ) ) ) );
		foreach ( array_slice( $parts, 1 ) as $subtag ) {
			if ( 4 === strlen( $subtag ) && ctype_alpha( $subtag ) ) {
				return in_array( $subtag, self::RTL_SCRIPTS, true ) ? 'rtl' : 'ltr';
			}
		}
		return in_array( $parts[0] ?? '', self::RTL_LANGUAGES, true ) ? 'rtl' : 'ltr';
	}

	/**
	 * `lang`/`dir` attributes of the UI (learning area document, A11Y-032).
	 *
	 * @return string Attribute string starting with a space.
	 */
	public static function ui_attributes(): string {
		$tag = self::ui_tag();
		$dir = is_rtl() ? 'rtl' : self::dir( $tag );
		return ' lang="' . esc_attr( $tag ) . '" dir="' . esc_attr( $dir ) . '"';
	}

	/**
	 * `lang`/`dir` for a content container when its language differs from the UI (A11Y-032, LOC-004/005).
	 *
	 * @param string $content_tag Content language (BCP 47).
	 * @return string Attribute string starting with a space, or ''.
	 */
	public static function content_attributes( string $content_tag ): string {
		$content_tag = str_replace( '_', '-', trim( $content_tag ) );
		if ( '' === $content_tag || 0 === strcasecmp( $content_tag, self::ui_tag() ) ) {
			return '';
		}
		$dir = self::dir( $content_tag );
		return ' lang="' . esc_attr( $content_tag ) . '"' . ( ( is_rtl() ? 'rtl' : 'ltr' ) !== $dir ? ' dir="' . esc_attr( $dir ) . '"' : '' );
	}

	/**
	 * Whether the learning area follows the profile language (E-124): the
	 * setting, by default on when more than one language is installed.
	 *
	 * @return bool
	 */
	public static function learning_area_uses_profile(): bool {
		$value = Settings::get( 'general.use_profile_locale' );
		return null === $value ? array() !== get_available_languages() : (bool) $value;
	}

	/**
	 * Switch to the UI chain of a logged-in person (§3.1): profile language when
	 * the own domain has a translation for it (or a variant, or it is English),
	 * otherwise the next link, the site language (LOC-013).
	 *
	 * @param int $user_id User.
	 * @return bool Whether the locale was switched (restore with `restore_previous_locale()`).
	 */
	public static function switch_to_user( int $user_id ): bool {
		$profile = get_user_locale( $user_id );
		if ( 'en_US' === self::resolve( $profile ) && ! str_starts_with( $profile, 'en' ) ) {
			return switch_to_locale( get_locale() );
		}
		return switch_to_user_locale( $user_id );
	}

	/**
	 * Run a callback in the learning-area locale of a user and restore afterwards.
	 *
	 * @template T
	 * @param int           $user_id  User (0 = guest: request locale).
	 * @param callable(): T $callback Callback.
	 * @return T
	 */
	public static function in_learning_locale( int $user_id, callable $callback ) {
		$switched = $user_id > 0 && self::learning_area_uses_profile() && self::switch_to_user( $user_id );
		try {
			return $callback();
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * Percentage as text with the translatable pattern (I18N-R-27).
	 *
	 * @param int|float $value    Value 0–100.
	 * @param int       $decimals Decimals.
	 * @return string
	 */
	public static function percent( $value, int $decimals = 0 ): string {
		/* translators: %s: percentage number, e.g. "75" (add a space before the sign if your language requires one). */
		return sprintf( __( '%s%%', 'course-captain' ), number_format_i18n( (float) $value, $decimals ) );
	}
}
