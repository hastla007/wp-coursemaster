<?php
/**
 * Read-only view of the live schema via information_schema.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the actual state of own tables (R0-37 I-M1: steps check the real
 * state, not only their status). Always bypasses caches.
 */
final class SchemaInspector {

	/**
	 * Constructor.
	 *
	 * @param Database $db Database access.
	 */
	public function __construct( private Database $db ) {
	}

	/**
	 * Existing own-name tables of the current site with engine and comment.
	 *
	 * @return array<string, array{engine: string, comment: string}> Keyed by name without site prefix.
	 */
	public function tables(): array {
		$prefix = $this->db->wpdb()->prefix;
		$rows   = $this->db->rows(
			$this->db->prepare(
				'SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_COMMENT AS comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s',
				$this->db->wpdb()->esc_like( $prefix . 'ccap_' ) . '%'
			)
		);
		$result = array();
		foreach ( $rows as $row ) {
			$short = substr( (string) $row['name'], strlen( $prefix ) );
			// A prefix like `wp_` also matches `wp_2_ccap_*` of other sites; keep exact matches only.
			if ( ! in_array( $short, Schema::table_names(), true ) && 1 !== preg_match( '/^ccap_[a-z0-9_]+$/', $short ) ) {
				continue;
			}
			$result[ $short ] = array(
				'engine'  => (string) $row['engine'],
				'comment' => (string) $row['comment'],
			);
		}
		return $result;
	}

	/**
	 * Column names of a table.
	 *
	 * @param string $table Table name without prefix.
	 * @return list<string>
	 */
	public function columns( string $table ): array {
		$rows = $this->db->rows(
			$this->db->prepare(
				'SELECT COLUMN_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
				$this->db->table( $table )
			)
		);
		return array_map( static fn( array $row ): string => (string) $row['name'], $rows );
	}

	/**
	 * Indexes of a table: name => ordered columns and uniqueness.
	 *
	 * @param string $table Table name without prefix.
	 * @return array<string, array{unique: bool, columns: list<string>}>
	 */
	public function indexes( string $table ): array {
		$rows   = $this->db->rows(
			$this->db->prepare(
				'SELECT INDEX_NAME AS name, NON_UNIQUE AS non_unique, COLUMN_NAME AS col FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX',
				$this->db->table( $table )
			)
		);
		$result = array();
		foreach ( $rows as $row ) {
			$name = (string) $row['name'];
			if ( ! isset( $result[ $name ] ) ) {
				$result[ $name ] = array(
					'unique'  => '0' === (string) $row['non_unique'],
					'columns' => array(),
				);
			}
			$result[ $name ]['columns'][] = (string) $row['col'];
		}
		return $result;
	}

	/**
	 * Foreign key constraint names of the current site's own tables.
	 *
	 * @return array<string, array{table: string, column: string, ref_table: string, ref_column: string}>
	 */
	public function foreign_keys(): array {
		$prefix = $this->db->wpdb()->prefix;
		$rows   = $this->db->rows(
			$this->db->prepare(
				'SELECT CONSTRAINT_NAME AS name, TABLE_NAME AS tbl, COLUMN_NAME AS col, REFERENCED_TABLE_NAME AS ref_tbl, REFERENCED_COLUMN_NAME AS ref_col FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_NAME LIKE %s',
				$this->db->wpdb()->esc_like( $prefix . 'ccap_' ) . '%'
			)
		);
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row['name'] ] = array(
				'table'      => substr( (string) $row['tbl'], strlen( $prefix ) ),
				'column'     => (string) $row['col'],
				'ref_table'  => substr( (string) $row['ref_tbl'], strlen( $prefix ) ),
				'ref_column' => (string) $row['ref_col'],
			);
		}
		return $result;
	}

	/**
	 * Drift check after `dbDelta` (R0-06 §6.2, R0-37 P2): every declared
	 * table, column and key (including column order of keys) must exist.
	 *
	 * @param array $tables Tables to check (without prefix).
	 * @phpstan-param list<string> $tables
	 * @return list<string> Human readable findings; empty when in sync.
	 */
	public function drift( array $tables ): array {
		$existing = $this->tables();
		$findings = array();
		$all      = Schema::tables();
		foreach ( $tables as $table ) {
			if ( ! isset( $existing[ $table ] ) ) {
				$findings[] = "missing table {$table}";
				continue;
			}
			if ( 'InnoDB' !== $existing[ $table ]['engine'] ) {
				$findings[] = "table {$table} is not InnoDB";
			}
			$columns = $this->columns( $table );
			foreach ( array_keys( $all[ $table ]['columns'] ) as $column ) {
				if ( ! in_array( $column, $columns, true ) ) {
					$findings[] = "missing column {$table}.{$column}";
				}
			}
			$indexes = $this->indexes( $table );
			foreach ( Schema::key_columns( $table ) as $name => $key ) {
				if ( ! isset( $indexes[ $name ] ) ) {
					$findings[] = "missing key {$table}.{$name}";
					continue;
				}
				if ( $indexes[ $name ]['columns'] !== $key['columns'] || $indexes[ $name ]['unique'] !== $key['unique'] ) {
					$findings[] = "key {$table}.{$name} differs";
				}
			}
		}
		return $findings;
	}
}
