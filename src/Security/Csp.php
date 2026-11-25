<?php
/**
 * Content Security Policy of the learning area (SET-CC-10; R0-17 §3.5,
 * R0-31 PSD-052, E-224).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Security;

use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * `privacy.csp_mode` = `enforce` (header `Content-Security-Policy`) or
 * `report_only` (`Content-Security-Policy-Report-Only`, violations only in
 * the browser console). The policy is sent on every page of the learning
 * area (lessons, practice, exam room, groups, invitations), independent of
 * `hardening.site_headers` (PSD-052).
 *
 * Compatibility (decision, see docs/r1/SET-CC-mail-felder.md):
 * - Scripts: per-request nonce plus `'strict-dynamic'`. WordPress prints
 *   every enqueued and inline script through `wp_get_script_tag()` /
 *   `wp_get_inline_script_tag()`, so the nonce reaches theme, plugin and
 *   own scripts (exam room, external media) through the filters
 *   `wp_script_attributes` / `wp_inline_script_attributes`; scripts loaded
 *   by those scripts are allowed by `'strict-dynamic'`. Only scripts echoed
 *   raw or inline event handlers are blocked – that is the protection.
 *   `'unsafe-inline' https:` are fallbacks for browsers without CSP 3 and
 *   ignored by all others.
 * - Frames: this website, the privacy-friendly players of YouTube/Vimeo and
 *   the regular YouTube embed (oEmbed in lesson text) plus
 *   `course_captain_trusted_media_hosts` and `course_captain_csp_frame_hosts`.
 *   Click-to-load stays a markup decision (nothing loads before consent).
 * - Styles, images, media and connections are not restricted (theme fonts,
 *   video files on any HTTPS host, REST calls of the exam room).
 * - `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`,
 *   `frame-ancestors 'self'`.
 */
final class Csp {

	public const ENFORCE     = 'enforce';
	public const REPORT_ONLY = 'report_only';

	/** Frame hosts of the built-in video embeds (ExternalMedia::embed_url, oEmbed). */
	public const VIDEO_FRAME_HOSTS = array( 'www.youtube-nocookie.com', 'www.youtube.com', 'youtube.com', 'player.vimeo.com' );

	/**
	 * Nonce of this request ('' while no policy is active).
	 *
	 * @var string
	 */
	private static string $nonce = '';

	/**
	 * Header name and value for a mode (pure; tests).
	 *
	 * @param string $mode        `enforce` or `report_only`.
	 * @param string $nonce       Script nonce (base64).
	 * @param array  $frame_hosts Allowed frame hosts.
	 * @phpstan-param list<string> $frame_hosts
	 * @return array{0: string, 1: string}
	 */
	public static function header( string $mode, string $nonce, array $frame_hosts ): array {
		$hosts = array();
		foreach ( $frame_hosts as $host ) {
			$host = strtolower( trim( (string) $host ) );
			// Plain host names only: anything else could widen or break the policy.
			if ( 1 === preg_match( '/^[a-z0-9.-]+$/', $host ) && str_contains( $host, '.' ) ) {
				$hosts[ 'https://' . $host ] = true;
			}
		}
		$directives = array(
			"script-src 'nonce-" . $nonce . "' 'strict-dynamic' 'unsafe-inline' https:",
			"object-src 'none'",
			"base-uri 'self'",
			"form-action 'self'",
			"frame-ancestors 'self'",
			trim( "frame-src 'self' " . implode( ' ', array_keys( $hosts ) ) ),
		);
		return array(
			self::REPORT_ONLY === $mode ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy',
			implode( '; ', $directives ),
		);
	}

	/**
	 * Allowed frame hosts (built-in players plus filters).
	 *
	 * @return list<string>
	 */
	public static function frame_hosts(): array {
		$hosts = array_values( array_map( 'strval', array_merge( self::VIDEO_FRAME_HOSTS, (array) apply_filters( 'course_captain_trusted_media_hosts', array() ) ) ) );
		/**
		 * Further hosts the learning area may show in frames (CSP `frame-src`).
		 *
		 * @since 1.0.0
		 * @param list<string> $hosts Host names without scheme.
		 */
		$filtered = apply_filters( 'course_captain_csp_frame_hosts', $hosts );
		return array_values( array_unique( array_map( 'strval', is_array( $filtered ) ? $filtered : $hosts ) ) );
	}

	/**
	 * Start the policy for the current learning-area response: create the
	 * nonce, add it to every script WordPress prints and send the header.
	 *
	 * @return array{0: string, 1: string} Header name and value.
	 */
	public static function start(): array {
		if ( '' === self::$nonce ) {
			self::$nonce = rtrim( strtr( base64_encode( random_bytes( 18 ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Random nonce, not obfuscation.
			add_filter( 'wp_script_attributes', array( self::class, 'script_attributes' ) );
			add_filter( 'wp_inline_script_attributes', array( self::class, 'script_attributes' ) );
		}
		$header = self::header( (string) Settings::get( 'privacy.csp_mode' ), self::$nonce, self::frame_hosts() );
		if ( ! headers_sent() ) {
			header( $header[0] . ': ' . $header[1] );
		}
		return $header;
	}

	/**
	 * Stop the policy (tests; the request ends after the learning area).
	 *
	 * @return void
	 */
	public static function stop(): void {
		remove_filter( 'wp_script_attributes', array( self::class, 'script_attributes' ) );
		remove_filter( 'wp_inline_script_attributes', array( self::class, 'script_attributes' ) );
		self::$nonce = '';
	}

	/**
	 * Current nonce ('' when inactive).
	 *
	 * @return string
	 */
	public static function nonce(): string {
		return self::$nonce;
	}

	/**
	 * `wp_script_attributes` / `wp_inline_script_attributes`: add the nonce.
	 *
	 * @param mixed $attributes Attributes.
	 * @return mixed
	 */
	public static function script_attributes( $attributes ) {
		if ( is_array( $attributes ) && '' !== self::$nonce && ! isset( $attributes['nonce'] ) ) {
			$attributes['nonce'] = self::$nonce;
		}
		return $attributes;
	}
}
