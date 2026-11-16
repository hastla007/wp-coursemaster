<?php
/**
 * Client IP and HTTPS behind trusted reverse proxies (R0-20 KOMP-015, KOMP-045).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Safe default: without configured proxies the remote address counts and
 * forwarding headers are ignored. Only when the remote address belongs to
 * `privacy.trusted_proxies` (IPs or CIDR ranges) does Course Captain read
 * `CF-Connecting-IP`, then `X-Forwarded-For` from right to left (first
 * address that is not a trusted proxy), and `X-Forwarded-Proto: https`.
 */
final class ClientIp {

	public const OPTION = 'course_captain_settings_privacy';

	/**
	 * Remote address of the connection.
	 *
	 * @return string
	 */
	public static function remote(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		return false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
	}

	/**
	 * Configured trusted proxies (raw option, usable before `init`).
	 *
	 * @return list<string>
	 */
	public static function proxies(): array {
		$stored = get_option( self::OPTION, array() );
		$list   = is_array( $stored ) && isset( $stored['values']['trusted_proxies'] ) && is_array( $stored['values']['trusted_proxies'] ) ? $stored['values']['trusted_proxies'] : array();
		return array_values( array_filter( array_map( 'strval', $list ), static fn( string $entry ): bool => '' !== $entry ) );
	}

	/**
	 * Whether an address belongs to a trusted proxy.
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	public static function trusted( string $ip ): bool {
		foreach ( self::proxies() as $range ) {
			if ( self::in_range( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Client address for rate limits and logs.
	 *
	 * @return string
	 */
	public static function get(): string {
		$remote = self::remote();
		if ( 'unknown' === $remote || ! self::trusted( $remote ) ) {
			return $remote;
		}
		$cf = isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) ) : '';
		if ( false !== filter_var( $cf, FILTER_VALIDATE_IP ) ) {
			return $cf;
		}
		$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
		foreach ( array_reverse( explode( ',', $forwarded ) ) as $hop ) {
			$hop = trim( $hop );
			if ( false === filter_var( $hop, FILTER_VALIDATE_IP ) ) {
				break;
			}
			if ( ! self::trusted( $hop ) ) {
				return $hop;
			}
		}
		return $remote;
	}

	/**
	 * Whether a trusted proxy reports HTTPS.
	 *
	 * @return bool
	 */
	public static function forwarded_https(): bool {
		$proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) : '';
		return 'https' === trim( (string) strtok( $proto, ',' ) ) && self::trusted( self::remote() );
	}

	/**
	 * Make `is_ssl()` correct behind a trusted TLS-terminating proxy.
	 *
	 * @return void
	 */
	public static function apply_forwarded_https(): void {
		if ( ! is_ssl() && self::forwarded_https() ) {
			$_SERVER['HTTPS'] = 'on';
		}
	}

	/**
	 * Address in an IP or CIDR range (IPv4 and IPv6).
	 *
	 * @param string $ip    Address.
	 * @param string $range IP or CIDR.
	 * @return bool
	 */
	public static function in_range( string $ip, string $range ): bool {
		$parts  = explode( '/', $range, 2 );
		$needle = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid input returns false.
		$base   = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid input returns false.
		if ( false === $needle || false === $base || strlen( $needle ) !== strlen( $base ) ) {
			return false;
		}
		$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $base ) * 8;
		if ( $bits < 0 || $bits > strlen( $base ) * 8 ) {
			return false;
		}
		$bytes = intdiv( $bits, 8 );
		if ( substr( $needle, 0, $bytes ) !== substr( $base, 0, $bytes ) ) {
			return false;
		}
		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}
		$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
		return ( ord( $needle[ $bytes ] ) & $mask ) === ( ord( $base[ $bytes ] ) & $mask );
	}
}
