<?php
/**
 * Foreign hosts and the external media mode (R0-05 GM-012…GM-014, E-45/E-45a).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Activity;

defined( 'ABSPATH' ) || exit;

/**
 * The site's own domain, its subdomains and relative URLs are never
 * foreign. Default mode `always_load` embeds directly; `click_to_load`
 * delivers no URL of a foreign host before consent.
 */
final class ExternalMedia {

	public const ALWAYS_LOAD   = 'always_load';
	public const CLICK_TO_LOAD = 'click_to_load';
	public const LINK_ONLY     = 'link_only';

	/**
	 * Foreign hosts among URLs (sorted, unique).
	 *
	 * @param array $urls URLs.
	 * @phpstan-param list<string> $urls
	 * @return list<string>
	 */
	public static function foreign_hosts( array $urls ): array {
		$site    = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$trusted = array_map( 'strtolower', (array) apply_filters( 'course_captain_trusted_media_hosts', array() ) );
		$hosts   = array();
		foreach ( $urls as $url ) {
			$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
			if ( '' === $host || $host === $site || str_ends_with( $host, '.' . $site ) || in_array( $host, $trusted, true ) ) {
				continue;
			}
			$hosts[ $host ] = true;
		}
		$list = array_keys( $hosts );
		sort( $list );
		return $list;
	}

	/**
	 * Current mode (`privacy.external_media_mode`, read through the settings service).
	 *
	 * @return string
	 */
	public static function mode(): string {
		try {
			$mode = (string) \CourseCaptain\Settings\Settings::get( 'privacy.external_media_mode' );
		} catch ( \Throwable $e ) {
			unset( $e );
			$mode = self::ALWAYS_LOAD;
		}
		return in_array( $mode, array( self::CLICK_TO_LOAD, self::LINK_ONLY ), true ) ? $mode : self::ALWAYS_LOAD;
	}

	/**
	 * Consent configuration for the learner client (MC-3, MC-5, E-246):
	 * WP Consent API category, whether the "always load" box is offered
	 * (never pre-ticked) and whether the Consent API already granted it.
	 *
	 * @return array{mode: string, category: string, remember_offer: bool, remember_prechecked: bool, consent_api: bool, granted: bool}
	 */
	public static function consent(): array {
		$mode     = self::mode();
		$category = 'marketing';
		$remember = true;
		try {
			$category = (string) \CourseCaptain\Settings\Settings::get( 'privacy.consent_category' );
			$remember = true === \CourseCaptain\Settings\Settings::get( 'privacy.external_media_remember' );
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$api     = function_exists( 'wp_has_consent' );
		$granted = $api && self::CLICK_TO_LOAD === $mode && self::api_consent( 'wp_has_consent', $category );
		return array(
			'mode'                => $mode,
			'category'            => $category,
			'remember_offer'      => self::CLICK_TO_LOAD === $mode && $remember,
			'remember_prechecked' => false,
			'consent_api'         => $api,
			'granted'             => $granted,
		);
	}

	/**
	 * Ask the WP Consent API (only called when it exists).
	 *
	 * @param string $callback Consent API function.
	 * @param string $category Category.
	 * @return bool
	 */
	private static function api_consent( string $callback, string $category ): bool {
		return is_callable( $callback ) && (bool) call_user_func( $callback, $category );
	}

	/**
	 * Learner view of a video activity (server side).
	 *
	 * @param array<string, mixed> $settings  Video settings.
	 * @param bool                 $consented Learner consented for this video/host.
	 * @param string               $mode      Mode (default from settings).
	 * @return array{video_url: string|null, subtitles_url: string|null, consent_required: bool, foreign_host: string|null, link_url: string|null}
	 */
	public static function video_view( array $settings, bool $consented = false, ?string $mode = null ): array {
		$mode      = $mode ?? self::mode();
		$consented = $consented || ( self::CLICK_TO_LOAD === $mode && self::consent()['granted'] );
		$url       = (string) ( $settings['url'] ?? '' );
		$valid_url = ! empty( $settings['placeholder'] ) || '' === $url || ! SettingsSchema::is_https_url( $url ) ? null : $url;
		$subtitles = 'vtt' === ( $settings['subtitles'] ?? 'off' ) && SettingsSchema::is_https_url( (string) ( $settings['subtitle_url'] ?? '' ) ) ? (string) $settings['subtitle_url'] : null;
		$foreign   = null === $valid_url ? array() : self::foreign_hosts( array( $valid_url ) );
		$host      = $foreign[0] ?? null;
		if ( null !== $host && self::LINK_ONLY === $mode ) {
			// Never embedded: the learner opens the external page in a new tab (R0-31).
			return array(
				'video_url'        => null,
				'subtitles_url'    => null,
				'consent_required' => false,
				'foreign_host'     => $host,
				'link_url'         => $valid_url,
			);
		}
		if ( null !== $host && self::CLICK_TO_LOAD === $mode && ! $consented ) {
			return array(
				'video_url'        => null,
				'subtitles_url'    => null,
				'consent_required' => true,
				'foreign_host'     => $host,
				'link_url'         => null,
			);
		}
		return array(
			'video_url'        => $valid_url,
			'subtitles_url'    => null === $valid_url ? null : $subtitles,
			'consent_required' => false,
			'foreign_host'     => $host,
			'link_url'         => null,
		);
	}
}
