<?php
/**
 * Client address with trusted proxies (R0-27 `privacy.trusted_proxies`,
 * INV-D-037, SET-115, RC-12).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Security;

use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * `REMOTE_ADDR` is the client unless it belongs to a trusted proxy; then
 * the right-most address of `X-Forwarded-For` that is not itself a trusted
 * proxy wins. Without trusted proxies the header is ignored, so it cannot
 * be used to bypass limits (XFF spoofing).
 */
final class ClientIp {

	/**
	 * Current client address.
	 *
	 * @return string IP or `unknown`.
	 */
	public static function current(): string {
		$remote  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$forward = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
		try {
			$proxies = array_values( array_map( 'strval', (array) Settings::get( 'privacy.trusted_proxies' ) ) );
		} catch ( \Throwable $e ) {
			unset( $e );
			$proxies = array();
		}
		return self::resolve( $remote, $forward, $proxies );
	}

	/**
	 * Pure resolution.
	 *
	 * @param string $remote  `REMOTE_ADDR`.
	 * @param string $forward `X-Forwarded-For`.
	 * @param array  $proxies Trusted IPs/CIDRs.
	 * @phpstan-param list<string> $proxies
	 * @return string
	 */
	public static function resolve( string $remote, string $forward, array $proxies ): string {
		if ( false === filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			return 'unknown';
		}
		if ( array() === $proxies || '' === $forward || ! self::in_list( $remote, $proxies ) ) {
			return $remote;
		}
		$chain = array_reverse( array_map( 'trim', explode( ',', $forward ) ) );
		foreach ( $chain as $ip ) {
			if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $remote;
			}
			if ( ! self::in_list( $ip, $proxies ) ) {
				return $ip;
			}
		}
		return $remote;
	}

	/**
	 * Whether an IP is in a list of IPs/CIDRs.
	 *
	 * @param string $ip   IP.
	 * @param array  $entries List.
	 * @phpstan-param list<string> $entries
	 * @return bool
	 */
	public static function in_list( string $ip, array $entries ): bool {
		$bin = inet_pton( $ip );
		if ( false === $bin ) {
			return false;
		}
		foreach ( $entries as $entry ) {
			$parts = explode( '/', $entry, 2 );
			$net   = inet_pton( $parts[0] );
			if ( false === $net || strlen( $net ) !== strlen( $bin ) ) {
				continue;
			}
			$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $bin ) * 8;
			$full = intdiv( $bits, 8 );
			$rest = $bits % 8;
			if ( substr( $bin, 0, $full ) !== substr( $net, 0, $full ) ) {
				continue;
			}
			if ( 0 === $rest ) {
				return true;
			}
			$mask = ( 0xff << ( 8 - $rest ) ) & 0xff;
			if ( ( ord( $bin[ $full ] ) & $mask ) === ( ord( $net[ $full ] ) & $mask ) ) {
				return true;
			}
		}
		return false;
	}
}
