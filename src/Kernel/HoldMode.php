<?php
/**
 * Hold mode: the plugin boots without routes, migration, cron or outbound effects.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

defined( 'ABSPATH' ) || exit;

/**
 * Scaffold for the hold mode.
 *
 * R1-01 detects the forced network activation (R0-25 MS-R-02, D-25-08).
 * The site identity hold mode after restore or copy (E-85, R0-16) reuses this
 * class later; its reason constant is reserved here and not detected yet.
 */
final class HoldMode {

	/** Plugin listed in `active_sitewide_plugins` (MS-R-02). */
	public const REASON_NETWORK_FORCED = 'network_forced';

	/** Reserved: site identity changed after restore/copy (E-85). Not detected in R1-01. */
	public const REASON_SITE_IDENTITY = 'site_identity';

	/**
	 * Active reasons.
	 *
	 * @var list<string>
	 */
	private array $reasons;

	/**
	 * Constructor.
	 *
	 * @param list<string> $reasons Active reasons.
	 */
	public function __construct( array $reasons ) {
		$this->reasons = array_values( array_unique( $reasons ) );
	}

	/**
	 * Detect the hold mode reasons of the current request.
	 *
	 * @param string $basename Plugin basename.
	 * @return self
	 */
	public static function detect( string $basename ): self {
		$reasons = array();
		if ( NetworkGuard::is_network_forced( $basename ) ) {
			$reasons[] = self::REASON_NETWORK_FORCED;
		}
		return new self( $reasons );
	}

	/**
	 * Whether the plugin must stay in hold mode.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return array() !== $this->reasons;
	}

	/**
	 * Whether a specific reason is active.
	 *
	 * @param string $reason One of the REASON_* constants.
	 * @return bool
	 */
	public function has( string $reason ): bool {
		return in_array( $reason, $this->reasons, true );
	}

	/**
	 * Active reasons.
	 *
	 * @return list<string>
	 */
	public function reasons(): array {
		return $this->reasons;
	}

	/**
	 * Translated notice text for administrators.
	 *
	 * @return string
	 */
	public function message(): string {
		if ( $this->has( self::REASON_NETWORK_FORCED ) ) {
			return __( 'Course Captain is network activated, which is not supported. It is paused (hold mode): no course pages, no REST routes, no migrations and no scheduled tasks run. Deactivate it in the network admin and activate it on each site individually. No data has been deleted.', 'course-captain' );
		}
		return '';
	}
}
