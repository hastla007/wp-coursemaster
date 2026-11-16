<?php
/**
 * Readiness check of the setup assistant (R0-30 §2.4 step 8, RDY-01…RDY-15).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Admin;

use CourseCaptain\Ops\HealthChecks;
use CourseCaptain\Security\Hardening;
use CourseCaptain\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Every finding has a severity word (`blocker`, `warning`, `info`, `ok`),
 * a cause and a remedy (R0-22 §3.4). Checks that need a loopback request
 * (RDY-01 direct file access, RDY-03 learning area reachable, RDY-06 REST,
 * RDY-15 page cache) are reported as "checked in the system status" so the
 * assistant never makes external or loopback requests (E-210, ADM-045).
 */
final class Readiness {

	/**
	 * Run all checks.
	 *
	 * @return list<array{id: string, severity: string, message: string, fix: string}>
	 */
	public static function run(): array {
		$out = array();
		// RDY-02 Permalinks.
		$out[] = '' === (string) get_option( 'permalink_structure' )
			? self::row( 'RDY-02', 'blocker', __( 'The learning area needs readable addresses.', 'course-captain' ), __( 'Choose a structure other than “Plain” under Settings › Permalinks.', 'course-captain' ) )
			: self::row( 'RDY-02', 'ok', __( 'Readable addresses are switched on.', 'course-captain' ) );
		// RDY-04 Storage (and the guard files of RDY-01, without loopback).
		$storage = HealthChecks::test_storage();
		$out[]   = 'good' === $storage['status']
			? self::row( 'RDY-04', 'ok', (string) $storage['label'] )
			: self::row( 'RDY-04', 'blocker', __( 'Course Captain cannot write to the folder for protected files.', 'course-captain' ), __( 'Ask your host to set write permissions for the course-captain folder in uploads. Protected media are not delivered until this is fixed.', 'course-captain' ) );
		$out[]   = self::row( 'RDY-01', 'info', __( 'Direct access to protected files is checked in the system status.', 'course-captain' ), __( 'On nginx add a rule that denies direct access to the course-captain protected folder.', 'course-captain' ) );
		// RDY-05 Background jobs.
		$cron  = HealthChecks::test_cron();
		$out[] = 'good' === $cron['status']
			? self::row( 'RDY-05', 'ok', (string) $cron['label'] )
			: self::row( 'RDY-05', 'warning', (string) $cron['label'], __( 'Mails and evaluations are delayed. Set up a real cron job.', 'course-captain' ) );
		// RDY-07 Mail.
		$mail  = get_option( 'course_captain_mail_state' );
		$out[] = is_array( $mail ) && ! empty( $mail['test_ok_at'] )
			? self::row( 'RDY-07', 'ok', __( 'A test mail was sent successfully.', 'course-captain' ) )
			: self::row( 'RDY-07', 'warning', __( 'No successful test mail yet.', 'course-captain' ), __( 'Send a test mail in step 5 or under Settings › Emails.', 'course-captain' ) );
		// RDY-08 Privacy policy.
		$policy = (int) get_option( 'wp_page_for_privacy_policy' );
		$out[]  = $policy > 0 && 'publish' === get_post_status( $policy )
			? self::row( 'RDY-08', 'ok', __( 'A privacy policy is set.', 'course-captain' ) )
			: self::row( 'RDY-08', 'warning', __( 'No privacy policy is set.', 'course-captain' ), __( 'Choose a published page under Settings › Privacy.', 'course-captain' ) );
		// RDY-09 HTTPS.
		$out[] = str_starts_with( (string) home_url(), 'https://' )
			? self::row( 'RDY-09', 'ok', __( 'The website uses HTTPS.', 'course-captain' ) )
			: self::row( 'RDY-09', 'warning', __( 'The website runs without HTTPS. Logins and exams are then not protected.', 'course-captain' ), __( 'Ask your host for a TLS certificate.', 'course-captain' ) );
		// RDY-10 Default role.
		$role  = get_role( (string) get_option( 'default_role', 'subscriber' ) );
		$risky = array();
		foreach ( null === $role ? array() : array_keys( array_filter( $role->capabilities ) ) as $cap ) {
			if ( in_array( $cap, array( 'edit_posts', 'promote_users', 'manage_options' ), true ) || str_starts_with( (string) $cap, 'ccap_' ) ) {
				$risky[] = (string) $cap;
			}
		}
		$out[] = array() === $risky
			? self::row( 'RDY-10', 'ok', __( 'New accounts get a role without administrative permissions.', 'course-captain' ) )
			/* translators: %s: list of capabilities */
			: self::row( 'RDY-10', 'warning', sprintf( __( 'New accounts get a role with permissions like %s.', 'course-captain' ), implode( ', ', $risky ) ), __( 'Choose a role without editing permissions under Settings › General in WordPress.', 'course-captain' ) );
		// RDY-12 Hardening.
		$held = array();
		foreach ( Hardening::states() as $feature => $state ) {
			if ( str_starts_with( $state, 'held:' ) ) {
				$held[ substr( $state, 5 ) ] = true;
			}
		}
		if ( 'off' !== Hardening::mode( 'login_limit' ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && array() === (array) Settings::get( 'privacy.trusted_proxies' ) ) {
			$out[] = self::row( 'RDY-12', 'warning', __( 'All requests seem to come from one proxy, but no trusted proxy is entered.', 'course-captain' ), __( 'Enter the proxy under Settings › Privacy & security › Trusted proxies.', 'course-captain' ) );
		} elseif ( array() !== $held ) {
			/* translators: %s: plugin slugs */
			$out[] = self::row( 'RDY-12', 'info', sprintf( __( 'Hardening functions pause because of %s.', 'course-captain' ), implode( ', ', array_keys( $held ) ) ) );
		} else {
			$out[] = self::row( 'RDY-12', 'ok', __( 'No hardening conflicts.', 'course-captain' ) );
		}
		// RDY-13 Time zone.
		$out[] = '' === (string) get_option( 'timezone_string' )
			? self::row( 'RDY-13', 'warning', __( 'The time zone is set as a fixed offset. Deadlines then shift with daylight saving time.', 'course-captain' ), __( 'Choose a city, for example Berlin, under Settings › General in WordPress.', 'course-captain' ) )
			: self::row( 'RDY-13', 'ok', __( 'The time zone is a city.', 'course-captain' ) );
		// RDY-14 Translation.
		$locale = determine_locale();
		$out[]  = 'en_US' !== $locale && ! is_textdomain_loaded( 'course-captain' )
			/* translators: %s: locale */
			? self::row( 'RDY-14', 'info', sprintf( __( 'Course Captain is not yet fully translated for %s. Parts of the interface appear in English.', 'course-captain' ), $locale ), __( 'Translations arrive via Dashboard › Updates.', 'course-captain' ) )
			: self::row( 'RDY-14', 'ok', __( 'The interface is available in the website language.', 'course-captain' ) );
		$out[] = self::row( 'RDY-03', 'info', __( 'Reachability of the learning area, the interface for guests and page caching are checked in the system status.', 'course-captain' ) );
		return $out;
	}

	/**
	 * Number of blockers.
	 *
	 * @param list<array{id: string, severity: string, message: string, fix: string}> $rows Rows.
	 * @return int
	 */
	public static function blockers( array $rows ): int {
		return count( array_filter( $rows, static fn( array $row ): bool => 'blocker' === $row['severity'] ) );
	}

	/**
	 * Severity word (symbol and word, R0-30 §2.4 step 8).
	 *
	 * @param string $severity Severity.
	 * @return string
	 */
	public static function word( string $severity ): string {
		switch ( $severity ) {
			case 'blocker':
				return __( 'Blocker:', 'course-captain' );
			case 'warning':
				return __( 'Warning:', 'course-captain' );
			case 'info':
				return __( 'Info:', 'course-captain' );
		}
		return __( 'OK:', 'course-captain' );
	}

	/**
	 * One row.
	 *
	 * @param string $id       ID.
	 * @param string $severity Severity.
	 * @param string $message  Cause.
	 * @param string $fix      Remedy.
	 * @return array{id: string, severity: string, message: string, fix: string}
	 */
	private static function row( string $id, string $severity, string $message, string $fix = '' ): array {
		return array(
			'id'       => $id,
			'severity' => $severity,
			'message'  => $message,
			'fix'      => $fix,
		);
	}
}
