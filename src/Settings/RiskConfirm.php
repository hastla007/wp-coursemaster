<?php
/**
 * Confirmation tokens for risk fields (R0-27 S-7, R0-30 D-6, R0-31 PD-3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * A token is bound to the user, a scope (`settings:<tab>`, `setup:<step>`,
 * `import`) and the hash of the values to be written; it is valid for ten
 * minutes and can be used once. Only the hash of the token is stored.
 *
 * The pending submission (input that still needs a confirmation) is kept
 * in a per-user transient, so the confirmation page works without
 * JavaScript (D-7) and the confirmed request cannot carry other values.
 */
final class RiskConfirm {

	public const TTL            = 600;
	public const TOKEN_PREFIX   = 'ccap_risk_';
	public const PENDING_PREFIX = 'ccap_settings_pending_';

	/**
	 * Issue a token.
	 *
	 * @param int                  $user_id User.
	 * @param string               $scope   Scope.
	 * @param array<string, mixed> $values  Values the token confirms.
	 * @param array                $risks   Confirmed codes.
	 * @phpstan-param list<string> $risks
	 * @return string Token.
	 */
	public static function issue( int $user_id, string $scope, array $values, array $risks ): string {
		$token = wp_generate_password( 32, false, false );
		set_transient(
			self::TOKEN_PREFIX . self::key( $user_id, $token ),
			array(
				'scope' => $scope,
				'hash'  => self::hash( $values ),
				'risks' => $risks,
			),
			self::TTL
		);
		return $token;
	}

	/**
	 * Verify and consume a token; false when missing, expired, used, of
	 * another user/scope or when the values changed (F-89).
	 *
	 * @param int                  $user_id User.
	 * @param string               $scope   Scope.
	 * @param string               $token   Token.
	 * @param array<string, mixed> $values  Values to be written.
	 * @return list<string>|null Confirmed codes, null when invalid.
	 */
	public static function consume( int $user_id, string $scope, string $token, array $values ): ?array {
		if ( $user_id <= 0 || '' === $token ) {
			return null;
		}
		$name   = self::TOKEN_PREFIX . self::key( $user_id, $token );
		$stored = get_transient( $name );
		delete_transient( $name );
		if ( ! is_array( $stored ) || ( $stored['scope'] ?? '' ) !== $scope || ! hash_equals( (string) ( $stored['hash'] ?? '' ), self::hash( $values ) ) ) {
			return null;
		}
		return array_values( array_filter( (array) ( $stored['risks'] ?? array() ), 'is_string' ) );
	}

	/**
	 * Keep a pending submission (one per user).
	 *
	 * @param int                  $user_id User.
	 * @param array<string, mixed> $pending Data.
	 * @return void
	 */
	public static function park( int $user_id, array $pending ): void {
		set_transient( self::PENDING_PREFIX . $user_id, $pending, self::TTL );
	}

	/**
	 * Pending submission of a user.
	 *
	 * @param int $user_id User.
	 * @return array<string, mixed>|null
	 */
	public static function pending( int $user_id ): ?array {
		$pending = get_transient( self::PENDING_PREFIX . $user_id );
		return is_array( $pending ) ? $pending : null;
	}

	/**
	 * Drop the pending submission.
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	public static function drop( int $user_id ): void {
		delete_transient( self::PENDING_PREFIX . $user_id );
	}

	/**
	 * Stable hash of values.
	 *
	 * @param array<string, mixed> $values Values.
	 * @return string
	 */
	public static function hash( array $values ): string {
		ksort( $values );
		return hash( 'sha256', (string) wp_json_encode( $values ) );
	}

	/**
	 * Transient key of a token.
	 *
	 * @param int    $user_id User.
	 * @param string $token   Token.
	 * @return string
	 */
	private static function key( int $user_id, string $token ): string {
		return substr( hash_hmac( 'sha256', $user_id . '|' . $token, wp_salt( 'nonce' ) ), 0, 40 );
	}
}
