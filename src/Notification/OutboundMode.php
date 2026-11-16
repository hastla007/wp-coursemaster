<?php
/**
 * Outbound mode and mail sink (R0-27 §3.6; E-85, E-86).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Notification;

use CourseCaptain\Kernel\SiteIdentity;

defined( 'ABSPATH' ) || exit;

/**
 * `COURSE_CAPTAIN_OUTBOUND` = on|catch|off; without the constant `on` in
 * `production`, else `catch`. In `catch` mode mails only go to
 * `COURSE_CAPTAIN_MAIL_CATCH_ADDRESS` or `admin_email`; an invalid catch
 * address turns the mode `off` (with a Site Health warning). The hold mode
 * (site identity changed or DB restored, E-85) has precedence; it is
 * detected by `Kernel\SiteIdentity` (R1-23) and passes through the filter
 * `course_captain_notification_hold`.
 */
final class OutboundMode {

	public const ON    = 'on';
	public const CATCH = 'catch';
	public const OFF   = 'off';

	/**
	 * Pure resolution (tests: NTF-006, NTF-017).
	 *
	 * @param string|null $constant    Value of `COURSE_CAPTAIN_OUTBOUND` or null.
	 * @param string      $environment `wp_get_environment_type()`.
	 * @param string|null $catch_address Value of `COURSE_CAPTAIN_MAIL_CATCH_ADDRESS` or null.
	 * @param string      $admin_email Site admin address.
	 * @return array{mode: string, catch_address: string, warning: string}
	 */
	public static function resolve( ?string $constant, string $environment, ?string $catch_address, string $admin_email ): array {
		$mode = in_array( $constant, array( self::ON, self::CATCH, self::OFF ), true ) ? (string) $constant : ( 'production' === $environment ? self::ON : self::CATCH );
		$out  = array(
			'mode'          => $mode,
			'catch_address' => '',
			'warning'       => '',
		);
		if ( self::CATCH !== $mode ) {
			return $out;
		}
		$address = null === $catch_address || '' === $catch_address ? $admin_email : $catch_address;
		if ( false === is_email( $address ) ) {
			$out['mode']    = self::OFF;
			$out['warning'] = 'invalid_catch_address';
			return $out;
		}
		$out['catch_address'] = $address;
		return $out;
	}

	/**
	 * Effective mode of this request.
	 *
	 * @return array{mode: string, catch_address: string, warning: string}
	 */
	public static function current(): array {
		$resolved = self::resolve(
			defined( 'COURSE_CAPTAIN_OUTBOUND' ) ? (string) constant( 'COURSE_CAPTAIN_OUTBOUND' ) : null,
			wp_get_environment_type(),
			defined( 'COURSE_CAPTAIN_MAIL_CATCH_ADDRESS' ) ? (string) constant( 'COURSE_CAPTAIN_MAIL_CATCH_ADDRESS' ) : null,
			(string) get_option( 'admin_email' )
		);
		/**
		 * Effective outbound mode (tests and MU plugins; the constants stay the source in production).
		 *
		 * @since 1.0.0
		 * @param array{mode: string, catch_address: string, warning: string} $resolved Resolved mode.
		 */
		$filtered = apply_filters( 'course_captain_outbound_mode', $resolved );
		return is_array( $filtered ) && isset( $filtered['mode'], $filtered['catch_address'] ) ? array(
			'mode'          => (string) $filtered['mode'],
			'catch_address' => (string) $filtered['catch_address'],
			'warning'       => (string) ( $filtered['warning'] ?? '' ),
		) : $resolved;
	}

	/**
	 * Whether outbound mails are held (site identity hold mode, E-85).
	 *
	 * @return bool
	 */
	public static function is_held(): bool {
		/**
		 * Whether mails are held because the site identity changed (E-85; detection R1-23).
		 *
		 * @since 1.0.0
		 * @param bool $held Detected hold state of the site (`SiteIdentity::is_held()`).
		 */
		return true === apply_filters( 'course_captain_notification_hold', SiteIdentity::is_held() );
	}
}
