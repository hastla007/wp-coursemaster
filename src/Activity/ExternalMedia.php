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
		$trusted = \CourseCaptain\Settings\BusinessRules::trusted_media_hosts();
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
	 * Privacy-friendly embed URL of a YouTube or Vimeo page (R0-10 §5.1,
	 * R0-31 MC-2): YouTube over `youtube-nocookie.com`, Vimeo with `dnt=1`.
	 * Other URLs (files, unknown pages) have no embed URL.
	 *
	 * @param string $url Page or embed URL (https).
	 * @return string|null
	 */
	public static function embed_url( string $url ): ?string {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$host = str_starts_with( $host, 'www.' ) ? substr( $host, 4 ) : $host;
		$id   = '';
		if ( in_array( $host, array( 'youtube.com', 'm.youtube.com', 'youtube-nocookie.com' ), true ) ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			if ( '/watch' === $path ) {
				$id = is_string( $query['v'] ?? null ) ? $query['v'] : '';
			} elseif ( 1 === preg_match( '#^/(?:embed|shorts|live)/([^/]+)/?$#', $path, $m ) ) {
				$id = $m[1];
			}
		} elseif ( 'youtu.be' === $host && 1 === preg_match( '#^/([^/]+)/?$#', $path, $m ) ) {
			$id = $m[1];
		}
		if ( '' !== $id ) {
			return 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', $id ) ? 'https://www.youtube-nocookie.com/embed/' . $id : null;
		}
		if ( 'vimeo.com' === $host && 1 === preg_match( '#^/(?:channels/[^/]+/|groups/[^/]+/videos/)?(\d{1,12})/?$#', $path, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
		}
		if ( 'player.vimeo.com' === $host && 1 === preg_match( '#^/video/(\d{1,12})/?$#', $path, $m ) ) {
			return 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1';
		}
		return null;
	}

	/**
	 * Service name shown to learners (R0-10 §5.1 "Dienstname") and the link
	 * to its privacy policy; unknown hosts are named by their host.
	 *
	 * @param string $host Foreign host.
	 * @return array{name: string, privacy_url: string|null}
	 */
	public static function service( string $host ): array {
		$host = strtolower( $host );
		if ( 'youtube-nocookie.com' === $host || str_ends_with( $host, '.youtube-nocookie.com' ) || 'youtube.com' === $host || str_ends_with( $host, '.youtube.com' ) || 'youtu.be' === $host ) {
			return array(
				'name'        => 'YouTube',
				'privacy_url' => 'https://policies.google.com/privacy',
			);
		}
		if ( 'vimeo.com' === $host || str_ends_with( $host, '.vimeo.com' ) ) {
			return array(
				'name'        => 'Vimeo',
				'privacy_url' => 'https://vimeo.com/privacy',
			);
		}
		return array(
			'name'        => $host,
			'privacy_url' => null,
		);
	}

	/**
	 * Learner view of a video activity (server side).
	 *
	 * `embed_url` is set when a YouTube/Vimeo page may be embedded now. In
	 * `click_to_load` without consent nothing of the foreign host is loaded;
	 * `pending` then carries what the browser loads after the click (R0-10
	 * §5.2.2) and `watch_url` the page for the link without JavaScript.
	 *
	 * @param array<string, mixed> $settings  Video settings.
	 * @param bool                 $consented Learner consented for this video/host.
	 * @param string               $mode      Mode (default from settings).
	 * @return array{video_url: string|null, subtitles_url: string|null, consent_required: bool, foreign_host: string|null, link_url: string|null, embed_url: string|null, watch_url: string|null, pending: array{kind: string, src: string, subtitles: string|null}|null}
	 */
	public static function video_view( array $settings, bool $consented = false, ?string $mode = null ): array {
		$mode      = $mode ?? self::mode();
		$consented = $consented || ( self::CLICK_TO_LOAD === $mode && self::consent()['granted'] );
		$url       = (string) ( $settings['url'] ?? '' );
		$valid_url = ! empty( $settings['placeholder'] ) || '' === $url || ! SettingsSchema::is_https_url( $url ) ? null : $url;
		$subtitles = 'vtt' === ( $settings['subtitles'] ?? 'off' ) && SettingsSchema::is_https_url( (string) ( $settings['subtitle_url'] ?? '' ) ) ? (string) $settings['subtitle_url'] : null;
		$foreign   = null === $valid_url ? array() : self::foreign_hosts( array( $valid_url ) );
		$host      = $foreign[0] ?? null;
		$embed     = null === $valid_url ? null : self::embed_url( $valid_url );
		$view      = array(
			'video_url'        => $valid_url,
			'subtitles_url'    => null === $valid_url || null !== $embed ? null : $subtitles,
			'consent_required' => false,
			'foreign_host'     => $host,
			'link_url'         => null,
			'embed_url'        => $embed,
			'watch_url'        => null === $host ? null : $valid_url,
			'pending'          => null,
		);
		if ( null !== $host && self::LINK_ONLY === $mode ) {
			// Never embedded: the learner opens the external page in a new tab (R0-31).
			return array(
				'video_url'     => null,
				'subtitles_url' => null,
				'link_url'      => $valid_url,
				'embed_url'     => null,
			) + $view;
		}
		if ( null !== $host && self::CLICK_TO_LOAD === $mode && ! $consented ) {
			return array(
				'video_url'        => null,
				'subtitles_url'    => null,
				'consent_required' => true,
				'embed_url'        => null,
				'pending'          => array(
					'kind'      => null === $embed ? 'file' : 'embed',
					'src'       => (string) ( $embed ?? $valid_url ),
					'subtitles' => null === $embed ? $subtitles : null,
				),
			) + $view;
		}
		return $view;
	}
}
