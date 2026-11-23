<?php
/**
 * Contract of an online backfill step (R0-37 §4.4, phase P5).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * A backfill moves existing rows in batches after the version is set (W2).
 *
 * Rules (R0-37 I-M4): the table has an ascending integer primary key `id`;
 * `pending_where()` selects exactly the rows that still need the backfill,
 * so applying a batch twice changes nothing (natural idempotency). Steps are
 * immutable after release; `checksum()` changes with the definition (MIG-023).
 */
interface BackfillStep {

	/**
	 * Step ID `<module>:<NNNN>_<slug>`.
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Module of the step (`core`, `pro`, …).
	 *
	 * @return string
	 */
	public function module(): string;

	/**
	 * Own table without site prefix, e.g. `ccap_grant`.
	 *
	 * @return string
	 */
	public function table(): string;

	/**
	 * SQL condition (constant, no user input) of rows still to be filled.
	 *
	 * @return string
	 */
	public function pending_where(): string;

	/**
	 * Fill the given rows; runs inside the batch transaction.
	 *
	 * @param Database $db  Database access (same connection as the batch).
	 * @param array    $ids Primary keys, ascending, locked `FOR UPDATE`.
	 * @phpstan-param list<int> $ids
	 * @return int Rows changed.
	 */
	public function apply( Database $db, array $ids ): int;

	/**
	 * Checksum of the definition (40 hex characters).
	 *
	 * @return string
	 */
	public function checksum(): string;
}
