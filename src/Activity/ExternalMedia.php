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
	 * Current mode from `course_captain_settings_privacy.values.external_media_mode`.
	 *
	 * @return string
	 */
	public static function mode(): string {
		$privacy = get_option( 'course_captain_settings_privacy', array() );
		$mode    = is_array( $privacy ) ? (string) ( $privacy['values']['external_media_mode'] ?? '' ) : '';
		return self::CLICK_TO_LOAD === $mode ? self::CLICK_TO_LOAD : self::ALWAYS_LOAD;
	}

	/**
	 * Learner view of a video activity (server side).
	 *
	 * @param array<string, mixed> $settings  Video settings.
	 * @param bool                 $consented Learner consented for this video/host.
	 * @param string               $mode      Mode (default from settings).
	 * @return array{video_url: string|null, subtitles_url: string|null, consent_required: bool, foreign_host: string|null}
	 */
	public static function video_view( array $settings, bool $consented = false, ?string $mode = null ): array {
		$mode      = $mode ?? self::mode();
		$url       = (string) ( $settings['url'] ?? '' );
		$valid_url = ! empty( $settings['placeholder'] ) || '' === $url || ! SettingsSchema::is_https_url( $url ) ? null : $url;
		$subtitles = 'vtt' === ( $settings['subtitles'] ?? 'off' ) && SettingsSchema::is_https_url( (string) ( $settings['subtitle_url'] ?? '' ) ) ? (string) $settings['subtitle_url'] : null;
		$foreign   = null === $valid_url ? array() : self::foreign_hosts( array( $valid_url ) );
		$host      = $foreign[0] ?? null;
		if ( null !== $host && self::CLICK_TO_LOAD === $mode && ! $consented ) {
			return array(
				'video_url'        => null,
				'subtitles_url'    => null,
				'consent_required' => true,
				'foreign_host'     => $host,
			);
		}
		return array(
			'video_url'        => $valid_url,
			'subtitles_url'    => null === $valid_url ? null : $subtitles,
			'consent_required' => false,
			'foreign_host'     => $host,
		);
	}
}
