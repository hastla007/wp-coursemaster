<?php
/**
 * Backfill of `due_at` for legacy learning progress (R0-05 GM-068, R0-08 §5.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Learning;

use CourseCaptain\Storage\BackfillStep;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Contract (R0-08 §5.2): `due_at` is mandatory for `secure`/`mastered`.
 * Rows that come without it (legacy data of a separate migration tool,
 * E-03) keep their stage and get the due date of the legacy rule:
 * `secure` = last answer + 20 h, `mastered` = last answer + 7 days. The
 * smart trainer then counts and presents them like any other due row.
 *
 * The step is not part of `Migrator::core_backfills()` (Course Captain
 * itself never writes such rows); a migration tool registers it through
 * the Migrator constructor or applies it in its own batches.
 */
final class LegacyDueBackfill implements BackfillStep {

	public const ID = 'core:0100_question_progress_due_at';

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
		return 'ccap_question_progress';
	}

	/**
	 * {@inheritDoc}
	 */
	public function pending_where(): string {
		return "learn_status IN ('secure', 'mastered') AND due_at IS NULL AND last_answered_at IS NOT NULL";
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Database $db  Database.
	 * @param array    $ids IDs.
	 * @phpstan-param list<int> $ids
	 */
	public function apply( Database $db, array $ids ): int {
		if ( array() === $ids ) {
			return 0;
		}
		$table = $db->table( $this->table() );
		$list  = implode( ',', array_map( 'intval', $ids ) );
		$gap   = SmartTrainer::CONSOLIDATE_GAP;
		$days  = SmartTrainer::REVIEW_INTERVAL_DAYS[0];
		return $db->execute( "UPDATE `{$table}` SET due_at = IF(learn_status = 'secure', DATE_ADD(last_answered_at, INTERVAL {$gap} SECOND), DATE_ADD(last_answered_at, INTERVAL {$days} DAY)), row_version = row_version + 1 WHERE id IN ({$list}) AND " . $this->pending_where() ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Integer list and class constants.
	}

	/**
	 * {@inheritDoc}
	 */
	public function checksum(): string {
		return sha1( self::ID . '|' . $this->pending_where() . '|secure+' . SmartTrainer::CONSOLIDATE_GAP . 's|mastered+' . SmartTrainer::REVIEW_INTERVAL_DAYS[0] . 'd' );
	}
}
