<?php
/**
 * Result of the content gate (R0-08 §1.4).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Decision;

defined( 'ABSPATH' ) || exit;

/**
 * `GateDecision { state, reason, unlock_at, requires, rule_refs }` plus the
 * partial-access limit (`trial`) and, for `no_access`, the underlying
 * policy decision (keeps 401/404 semantics).
 */
final class GateDecision {

	/**
	 * Constructor.
	 *
	 * @param string        $state     unlocked|locked.
	 * @param string|null   $reason    no_access|trial|drip|prerequisite|exam_running.
	 * @param string|null   $unlock_at UTC datetime (drip).
	 * @param array         $requires  Missing prerequisites / locked modules.
	 * @param array         $rule_refs Deciding rules.
	 * @param int|null      $limit     Partial access: first n elements per section.
	 * @param Decision|null $policy    Underlying policy denial.
	 * @phpstan-param list<string> $requires
	 * @phpstan-param list<string> $rule_refs
	 */
	public function __construct(
		public readonly string $state,
		public readonly ?string $reason = null,
		public readonly ?string $unlock_at = null,
		public readonly array $requires = array(),
		public readonly array $rule_refs = array(),
		public readonly ?int $limit = null,
		public readonly ?Decision $policy = null
	) {
	}

	/**
	 * Unlocked.
	 *
	 * @param array    $refs  Rules.
	 * @param int|null $limit Partial access limit.
	 * @phpstan-param list<string> $refs
	 * @return self
	 */
	public static function unlocked( array $refs = array(), ?int $limit = null ): self {
		return new self( 'unlocked', null, null, array(), $refs, $limit );
	}

	/**
	 * Locked.
	 *
	 * @param string        $reason    Reason.
	 * @param array         $refs      Rules.
	 * @param string|null   $unlock_at Unlock time.
	 * @param array         $requires  Requirements.
	 * @param Decision|null $policy    Policy denial.
	 * @phpstan-param list<string> $refs
	 * @phpstan-param list<string> $requires
	 * @return self
	 */
	public static function locked( string $reason, array $refs = array(), ?string $unlock_at = null, array $requires = array(), ?Decision $policy = null ): self {
		return new self( 'locked', $reason, $unlock_at, $requires, $refs, null, $policy );
	}

	/**
	 * Whether the content may be delivered.
	 *
	 * @return bool
	 */
	public function allowed(): bool {
		return 'unlocked' === $this->state;
	}

	/**
	 * Throw the matching error when locked: the policy denial for
	 * `no_access`, otherwise `ccap_content_locked` (403) with the reason.
	 *
	 * @return void
	 * @throws CourseException When locked.
	 */
	public function assert(): void {
		if ( $this->allowed() ) {
			return;
		}
		if ( null !== $this->policy ) {
			CourseException::unless_allowed( $this->policy, 'No learning access.' );
		}
		throw new CourseException( Decision::CONTENT_LOCKED, esc_html( (string) $this->reason ), 403 ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Machine code constant.
	}

	/**
	 * Contract payload.
	 *
	 * @return array{state: string, reason: string|null, unlock_at: string|null, requires: list<string>, rule_refs: list<string>}
	 */
	public function to_array(): array {
		return array(
			'state'     => $this->state,
			'reason'    => $this->reason,
			'unlock_at' => $this->unlock_at,
			'requires'  => $this->requires,
			'rule_refs' => $this->rule_refs,
		);
	}
}
