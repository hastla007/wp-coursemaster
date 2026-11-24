<?php
/**
 * Registry of the purchase grant sources (R0-09 §7, R0-31 RT-13/RT-14).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

defined( 'ABSPATH' ) || exit;

/**
 * Grant sources whose history is kept pseudonymised after an account
 * erasure (RT-13) instead of being deleted at once (RT-14). The core knows
 * the two purchase sources of R0-09 §7 (`woo_order`, `voucher`); a connector
 * with its own purchase source type (R0-19 §5.8, e.g. another shop)
 * registers it on the filter `course_captain_purchase_sources` instead of
 * changing a hard-coded list.
 *
 * The filter can only add: the core types always stay (the retention
 * period of E-44 must not be shortened by an extension), the sources that
 * R0-09 §7 names as "übrige Quellen" (`manual`, `group_seat`, `workflow`)
 * and the self-enrollment source cannot become purchase sources (RT-14
 * would otherwise be extended without a decision), and every type must
 * match the grant source pattern `[a-z0-9_]{1,32}` (R0-19 §4.1).
 */
final class PurchaseSources {

	/**
	 * Purchase sources of the core (R0-09 §7 "Kaufquellen (`woo_order`, `voucher`)").
	 *
	 * @var list<string>
	 */
	public const CORE = array( 'woo_order', 'voucher' );

	/**
	 * Sources that are never purchase sources (R0-09 §7, RT-14).
	 *
	 * @var list<string>
	 */
	public const NEVER = array( 'manual', 'group_seat', 'workflow', SelfEnrollment::SOURCE );

	/**
	 * All registered purchase sources: core types first, then valid additions in filter order.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		/**
		 * Register additional purchase grant sources (pseudonymised grant
		 * history after an erasure, R0-09 §7, RT-13). Additive only:
		 * removed core types, invalid names and the non-purchase sources
		 * `manual`, `group_seat`, `workflow`, `free_enrollment` are ignored.
		 *
		 * @since 0.1.0
		 * @param string[] $sources Source types (`[a-z0-9_]{1,32}`).
		 */
		$filtered = apply_filters( 'course_captain_purchase_sources', self::CORE );
		$sources  = self::CORE;
		foreach ( is_array( $filtered ) ? $filtered : array() as $type ) {
			if ( is_string( $type ) && self::valid( $type ) && ! in_array( $type, $sources, true ) ) {
				$sources[] = $type;
			}
		}
		return $sources;
	}

	/**
	 * Whether a source type is a registered purchase source.
	 *
	 * @param string $source_type Source type.
	 * @return bool
	 */
	public static function is_purchase( string $source_type ): bool {
		return in_array( $source_type, self::all(), true );
	}

	/**
	 * SQL list of the registered types for `source_type IN (…)`; every
	 * entry matches `[a-z0-9_]{1,32}`, so quoting is safe.
	 *
	 * @return string
	 */
	public static function sql_list(): string {
		return "'" . implode( "', '", self::all() ) . "'";
	}

	/**
	 * Whether a type may be registered as purchase source.
	 *
	 * @param string $type Source type.
	 * @return bool
	 */
	private static function valid( string $type ): bool {
		return 1 === preg_match( '/^[a-z0-9_]{1,32}$/', $type ) && ! in_array( $type, self::NEVER, true );
	}
}
