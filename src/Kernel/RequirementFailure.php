<?php
/**
 * A single unmet requirement.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

defined( 'ABSPATH' ) || exit;

/**
 * Value object with a stable error code (E-285 prefix).
 *
 * The message is translated lazily, so that the requirement check can run on
 * `plugins_loaded` without loading translations too early.
 */
final readonly class RequirementFailure {

	public const PHP       = 'ccap_requirement_php';
	public const WORDPRESS = 'ccap_requirement_wordpress';
	public const DATABASE  = 'ccap_requirement_database';
	public const INNODB    = 'ccap_requirement_innodb';
	public const JSON      = 'ccap_requirement_json';
	public const UTF8MB4   = 'ccap_requirement_utf8mb4';

	/**
	 * Constructor.
	 *
	 * @param string       $code Stable `ccap_*` code (one of the constants).
	 * @param list<string> $args Values for the message placeholders.
	 */
	public function __construct(
		public string $code,
		public array $args = array(),
	) {
	}

	/**
	 * Translated, human readable explanation.
	 *
	 * @return string
	 */
	public function message(): string {
		$a = array_pad( $this->args, 3, '' );

		switch ( $this->code ) {
			case self::PHP:
				/* translators: 1: required PHP version, 2: current PHP version. */
				return sprintf( __( 'PHP %1$s or newer is required; this site runs PHP %2$s.', 'course-captain' ), $a[0], $a[1] );
			case self::WORDPRESS:
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				return sprintf( __( 'WordPress %1$s or newer is required; this site runs WordPress %2$s.', 'course-captain' ), $a[0], $a[1] );
			case self::DATABASE:
				return sprintf(
					/* translators: 1: detected database server version, 2: minimum MariaDB version, 3: minimum MySQL version. */
					__( 'The database server "%1$s" is not supported. MariaDB %2$s or MySQL %3$s or newer is required.', 'course-captain' ),
					'' === $a[0] ? __( 'unknown', 'course-captain' ) : $a[0],
					$a[1],
					$a[2]
				);
			case self::INNODB:
				return __( 'The InnoDB storage engine is required but not available.', 'course-captain' );
			case self::JSON:
				return __( 'The database must support JSON functions (JSON_VALID).', 'course-captain' );
			case self::UTF8MB4:
				return __( 'The database connection must support the utf8mb4 character set.', 'course-captain' );
			default:
				return $this->code;
		}
	}
}
