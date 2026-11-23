<?php
/**
 * Result of a policy decision.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Allow or deny with error code, HTTP status and reason (R0-07 P-02, P-08).
 *
 * `caps` are the primitive capabilities the decision maps to for
 * `map_meta_cap`; a denial maps to `do_not_allow`.
 */
final class Decision {

	public const NOT_FOUND      = 'ccap_not_found';
	public const FORBIDDEN      = 'ccap_forbidden';
	public const CONTENT_LOCKED = 'ccap_content_locked';
	public const ACCOUNT_LOCKED = 'ccap_account_locked';
	public const AUTH_REQUIRED  = 'ccap_auth_required';

	/**
	 * HTTP status per code.
	 *
	 * @var array<string, int>
	 */
	private const STATUS = array(
		self::NOT_FOUND      => 404,
		self::FORBIDDEN      => 403,
		self::CONTENT_LOCKED => 403,
		self::ACCOUNT_LOCKED => 403,
		self::AUTH_REQUIRED  => 401,
	);

	/**
	 * Constructor.
	 *
	 * @param bool   $allowed Allowed.
	 * @param array  $caps    Primitive caps (allow) or `do_not_allow`.
	 * @phpstan-param list<string> $caps
	 * @param string $code    Error code on denial.
	 * @param string $reason  Machine reason, e.g. `expired`.
	 * @param bool   $audit   Access must be audited (R0-07 §3.6).
	 */
	private function __construct(
		public readonly bool $allowed,
		public readonly array $caps,
		public readonly string $code,
		public readonly string $reason,
		public readonly bool $audit,
	) {
	}

	/**
	 * Allow, mapped to primitive capabilities.
	 *
	 * @param array $caps  Primitive caps.
	 * @phpstan-param list<string> $caps
	 * @param bool  $audit Audit required.
	 * @return self
	 */
	public static function allow( array $caps, bool $audit = false ): self {
		return new self( true, array_values( array_unique( $caps ) ), '', '', $audit );
	}

	/**
	 * Deny.
	 *
	 * @param string $code   One of the code constants.
	 * @param string $reason Machine reason.
	 * @return self
	 */
	public static function deny( string $code, string $reason = '' ): self {
		return new self( false, array( 'do_not_allow' ), $code, $reason, false );
	}

	/**
	 * Unknown or invisible object: 404 without existence oracle (P-08).
	 *
	 * @return self
	 */
	public static function not_found(): self {
		return self::deny( self::NOT_FOUND );
	}

	/**
	 * HTTP status of a denial (200 when allowed).
	 *
	 * @return int
	 */
	public function status(): int {
		return $this->allowed ? 200 : self::STATUS[ $this->code ];
	}
}
