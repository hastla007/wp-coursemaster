<?php
/**
 * Base class of table repositories.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Database;

use CourseCaptain\Storage\Clock\Clock;

defined( 'ABSPATH' ) || exit;

/**
 * Shared access helpers for one own table (R0-06 §4.0).
 *
 * Subclasses (later tasks) add the domain queries. Frozen columns get no
 * update method (R0-06 §5), state changes use {@see self::compare_and_swap()}.
 */
abstract class Repository {

	/** CAS column of R0-06 §4.0. */
	public const VERSION_COLUMN = 'row_version';

	/**
	 * Constructor.
	 *
	 * @param Connection $db    Connection.
	 * @param Clock      $clock UTC clock for `*_at` columns.
	 */
	public function __construct( protected Connection $db, protected Clock $clock ) {
	}

	/**
	 * Table name without site prefix and `ccap_`, e.g. `grant`.
	 *
	 * @return string
	 */
	abstract protected function table_name(): string;

	/**
	 * Primary key column.
	 *
	 * @return string
	 */
	protected function primary_key(): string {
		return 'id';
	}

	/**
	 * Full table name of the current site.
	 *
	 * @return string
	 */
	public function table(): string {
		return $this->db->table( $this->table_name() );
	}

	/**
	 * Row by primary key.
	 *
	 * @param int|string $id Primary key value.
	 * @return array<string, string|null>|null
	 */
	public function find( int|string $id ): ?array {
		return $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE %i = ' . ( is_int( $id ) ? '%d' : '%s' ), $this->table(), $this->primary_key(), $id )
		);
	}

	/**
	 * Insert a row and return its AUTO_INCREMENT id (0 for natural keys).
	 *
	 * @param array<string, int|float|string|bool|null> $row Column => value.
	 * @return int
	 */
	public function insert( array $row ): int {
		$this->db->insert( $this->table(), $row );
		return $this->db->insert_id();
	}

	/**
	 * Insert unless a unique key already exists (UNIQUE strategy, R0-21 §2.1).
	 *
	 * @param array<string, int|float|string|bool|null> $row Column => value.
	 * @return bool Whether the row was inserted.
	 */
	public function insert_ignore( array $row ): bool {
		return 1 === $this->db->insert( $this->table(), $row, true );
	}

	/**
	 * Compare-and-swap update (R0-06 §4.0 CAS, R0-21 §2.1).
	 *
	 * `UPDATE … SET …, row_version = row_version + 1 WHERE pk = ? AND row_version = ? [AND col = ?]`.
	 * A failed CAS (0 rows) is no database error (R0-21 T-7).
	 *
	 * @param int|string                                $id               Primary key value.
	 * @param int                                       $expected_version Version read before.
	 * @param array<string, int|float|string|bool|null> $changes          Column => new value.
	 * @param array<string, int|string>                 $conditions       Extra equality guards, e.g. `status => 'active'`.
	 * @return bool Whether exactly one row changed.
	 */
	public function compare_and_swap( int|string $id, int $expected_version, array $changes, array $conditions = array() ): bool {
		[ $set, $args ] = Connection::assignments( $changes );

		$sql  = 'UPDATE %i SET ' . ( '' === $set ? '' : $set . ', ' ) . '%i = %i + 1 WHERE %i = ' . ( is_int( $id ) ? '%d' : '%s' ) . ' AND %i = %d';
		$args = array_merge( array( $this->table() ), $args, array( self::VERSION_COLUMN, self::VERSION_COLUMN, $this->primary_key(), $id, self::VERSION_COLUMN, $expected_version ) );
		foreach ( $conditions as $column => $value ) {
			$sql   .= ' AND %i = ' . ( is_int( $value ) ? '%d' : '%s' );
			$args[] = $column;
			$args[] = $value;
		}
		return 1 === $this->db->execute( $this->db->prepare( $sql, ...$args ) );
	}

	/**
	 * Repeat a read-check-CAS cycle up to three times (R0-21 T-7).
	 *
	 * @param callable(): bool $attempt Reads fresh state, re-checks guards and returns the CAS result.
	 * @param int              $max     Attempts.
	 * @return void
	 * @throws ConcurrencyConflict When every attempt lost the race.
	 */
	public static function retry_cas( callable $attempt, int $max = 3 ): void {
		for ( $i = 0; $i < $max; $i++ ) {
			if ( $attempt() ) {
				return;
			}
		}
		throw new ConcurrencyConflict( 'Row changed concurrently.' );
	}
}
