<?php
/**
 * Backfill: split single partial-access limits into one limit per kind (E-332).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Access;

use CourseCaptain\Storage\BackfillStep;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Rows `free_limited` with `limit_type` `first_items` or NULL (one limit
 * for questions, lessons, variants and files) become four rows with the
 * same value, so the effective access does not change (the gate reads
 * legacy rows as "every kind" until the step is done). Natural idempotency:
 * a split row has a kind as `limit_type` and leaves `pending_where()`.
 */
final class AccessRuleKindsBackfill implements BackfillStep {

	public const ID = 'core:0004_access_rule_kind_limits';

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return self::ID;
	}

	/**
	 * {@inheritDoc}
	 */
	public function module(): string {
		return 'core';
	}

	/**
	 * {@inheritDoc}
	 */
	public function table(): string {
		return 'ccap_access_rule';
	}

	/**
	 * {@inheritDoc}
	 */
	public function pending_where(): string {
		return "access_type = 'free_limited' AND (limit_type IS NULL OR limit_type NOT IN ('first_questions', 'first_lessons', 'first_variants', 'first_files'))";
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Database $db  Database.
	 * @param array    $ids IDs.
	 * @phpstan-param list<int> $ids
	 */
	public function apply( Database $db, array $ids ): int {
		return TrialLimits::split( $db, array_values( array_map( 'intval', $ids ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function checksum(): string {
		return sha1( self::ID . '|' . $this->pending_where() . '|' . implode( ',', array_keys( TrialLimits::KINDS ) ) );
	}
}
