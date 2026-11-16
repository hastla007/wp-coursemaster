<?php
/**
 * Test backfill on a probe table (R1-40).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Integration;

use CourseCaptain\Storage\BackfillStep;
use CourseCaptain\Storage\Database;

/**
 * Fills `val = src * 2` and counts writes per row in `writes`, so a batch
 * written twice is visible (MIG-008).
 */
final class ProbeBackfill implements BackfillStep {

	public const TABLE = 'ccap_backfill_probe';
	public const ID    = 'core:9001_probe_backfill';

	/**
	 * Constructor.
	 *
	 * @param string $checksum Definition checksum (MIG-023 changes it).
	 */
	public function __construct( private string $checksum = 'a000000000000000000000000000000000000001' ) {
	}

	/**
	 * Create the probe table with `$rows` unfilled rows.
	 *
	 * @param int $rows Rows.
	 * @return void
	 */
	public static function create( int $rows ): void {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		$wpdb->query( "CREATE TABLE `{$table}` (id bigint(20) unsigned NOT NULL AUTO_INCREMENT, src int(11) NOT NULL, val int(11) NULL, writes int(11) NOT NULL DEFAULT 0, PRIMARY KEY (id)) ENGINE=InnoDB" );
		$values = array();
		for ( $i = 1; $i <= $rows; $i++ ) {
			$values[] = '(' . $i . ')';
		}
		$wpdb->query( "INSERT INTO `{$table}` (src) VALUES " . implode( ',', $values ) );
		$wpdb->query( 'COMMIT' );
		// phpcs:enable
	}

	/**
	 * Drop the probe table.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . self::TABLE . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Aggregates of the probe table.
	 *
	 * @return array{rows: int, filled: int, correct: int, max_writes: int, sum_writes: int}
	 */
	public static function stats(): array {
		$db  = Database::current();
		$row = (array) $db->row( 'SELECT COUNT(*) AS c, SUM(val IS NOT NULL) AS f, SUM(val = src * 2) AS ok, MAX(writes) AS mw, SUM(writes) AS sw FROM `' . $db->table( self::TABLE ) . '`' );
		return array(
			'rows'       => (int) $row['c'],
			'filled'     => (int) $row['f'],
			'correct'    => (int) $row['ok'],
			'max_writes' => (int) $row['mw'],
			'sum_writes' => (int) $row['sw'],
		);
	}

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
		return self::TABLE;
	}

	/**
	 * {@inheritDoc}
	 */
	public function pending_where(): string {
		return 'val IS NULL';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Database $db  Database.
	 * @param array    $ids IDs.
	 */
	public function apply( Database $db, array $ids ): int {
		$table = $db->table( self::TABLE );
		return $db->execute( "UPDATE `{$table}` SET val = src * 2, writes = writes + 1 WHERE val IS NULL AND id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function checksum(): string {
		return $this->checksum;
	}
}
