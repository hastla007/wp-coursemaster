<?php
/**
 * Reads the actual schema from information_schema.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Schema;

use CourseCaptain\Storage\Database\Connection;

defined( 'ABSPATH' ) || exit;

/**
 * Actual-state checks for the migration (R0-37 I-M1): drift after dbDelta
 * including the column order of indexes (R0-06 §6.2), table ownership via
 * the comment marker (KOMP-131) and existing foreign keys.
 */
final class SchemaInspector {

	/**
	 * Constructor.
	 *
	 * @param Connection $db Connection.
	 */
	public function __construct( private Connection $db ) {
	}

	/**
	 * Own-named tables of the current site: full name => [comment, engine].
	 *
	 * @return array<string, array{comment: string, engine: string}>
	 */
	public function existing_tables(): array {
		$rows   = $this->db->get_results(
			$this->db->prepare(
				'SELECT TABLE_NAME AS name, TABLE_COMMENT AS comment, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s',
				$this->db->wpdb()->esc_like( $this->db->prefix() . Connection::TABLE_PREFIX ) . '%'
			)
		);
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row['name'] ] = array(
				'comment' => (string) $row['comment'],
				'engine'  => (string) $row['engine'],
			);
		}
		return $result;
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $short Short table name.
	 * @return bool
	 */
	public function table_exists( string $short ): bool {
		return null !== $this->db->get_var(
			$this->db->prepare(
				'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
				$this->db->table( $short )
			)
		);
	}

	/**
	 * Tables with a Course Captain name that exist without the ownership marker (KOMP-131).
	 *
	 * @param array<string, Table> $tables Definitions.
	 * @return list<string> Full table names.
	 */
	public function foreign_tables( array $tables ): array {
		$existing = $this->existing_tables();
		$foreign  = array();
		foreach ( $tables as $table ) {
			$full = $table->full_name( $this->db->prefix() );
			if ( isset( $existing[ $full ] ) && ! self::is_owned( $existing[ $full ]['comment'] ) ) {
				$foreign[] = $full;
			}
		}
		return $foreign;
	}

	/**
	 * Whether a table comment carries the ownership marker.
	 *
	 * @param string $comment Table comment.
	 * @return bool
	 */
	public static function is_owned( string $comment ): bool {
		return str_starts_with( $comment, Schema::MARKER_PREFIX );
	}

	/**
	 * Columns of a table: name => [type, nullable, extra].
	 *
	 * @param string $full Full table name.
	 * @return array<string, array{type: string, nullable: bool, extra: string}>
	 */
	public function columns( string $full ): array {
		$rows   = $this->db->get_results(
			$this->db->prepare(
				'SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, IS_NULLABLE AS nullable, EXTRA AS extra FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY ORDINAL_POSITION',
				$full
			)
		);
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row['name'] ] = array(
				'type'     => strtolower( (string) $row['type'] ),
				'nullable' => 'YES' === $row['nullable'],
				'extra'    => strtolower( (string) $row['extra'] ),
			);
		}
		return $result;
	}

	/**
	 * Indexes of a table: name => [unique, columns in order].
	 *
	 * @param string $full Full table name.
	 * @return array<string, array{unique: bool, columns: list<string>}>
	 */
	public function indexes( string $full ): array {
		$rows   = $this->db->get_results(
			$this->db->prepare(
				'SELECT INDEX_NAME AS name, NON_UNIQUE AS non_unique, COLUMN_NAME AS col, SUB_PART AS sub_part FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s ORDER BY INDEX_NAME, SEQ_IN_INDEX',
				$full
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
			$result[ $name ]['columns'][] = (string) $row['col'] . ( null === $row['sub_part'] ? '' : '(' . $row['sub_part'] . ')' );
		}
		return $result;
	}

	/**
	 * Names of the foreign keys present on the current site's own tables.
	 *
	 * @return list<string>
	 */
	public function foreign_key_names(): array {
		$names = $this->db->get_col(
			$this->db->prepare(
				"SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND TABLE_NAME LIKE %s",
				$this->db->wpdb()->esc_like( $this->db->prefix() . Connection::TABLE_PREFIX ) . '%'
			)
		);
		return array_values( array_map( 'strval', $names ) );
	}

	/**
	 * Differences between definition and actual table (empty = no drift).
	 *
	 * Extra columns and indexes are allowed (Expand/Contract, generated
	 * columns of later steps); missing ones, other types and other index
	 * column orders are drift.
	 *
	 * @param Table $table Definition.
	 * @return list<string>
	 */
	public function drift( Table $table ): array {
		$full     = $table->full_name( $this->db->prefix() );
		$existing = $this->existing_tables();
		if ( ! isset( $existing[ $full ] ) ) {
			return array( $full . ': table missing' );
		}

		$problems = array();
		if ( 'innodb' !== strtolower( $existing[ $full ]['engine'] ) ) {
			$problems[] = $full . ': engine ' . $existing[ $full ]['engine'] . ' instead of InnoDB';
		}
		if ( ! self::is_owned( $existing[ $full ]['comment'] ) ) {
			$problems[] = $full . ': ownership marker missing';
		}

		$columns = $this->columns( $full );
		foreach ( $table->columns as $name => $definition ) {
			if ( ! isset( $columns[ $name ] ) ) {
				$problems[] = $full . '.' . $name . ': column missing';
				continue;
			}
			$expected = self::normalise_type( self::declared_type( $definition ) );
			$actual   = self::normalise_type( $columns[ $name ]['type'] );
			if ( $expected !== $actual ) {
				$problems[] = $full . '.' . $name . ': type ' . $actual . ' instead of ' . $expected;
			}
		}

		$indexes = $this->indexes( $full );
		foreach ( $table->keys() as $key => $key_columns ) {
			if ( ! isset( $indexes[ $key ] ) ) {
				$problems[] = $full . ': key ' . $key . ' missing';
				continue;
			}
			if ( $indexes[ $key ]['columns'] !== $key_columns ) {
				$problems[] = $full . ': key ' . $key . ' has columns (' . implode( ',', $indexes[ $key ]['columns'] ) . ') instead of (' . implode( ',', $key_columns ) . ')';
			}
			$must_be_unique = 'PRIMARY' === $key || isset( $table->unique[ $key ] );
			if ( $must_be_unique !== $indexes[ $key ]['unique'] ) {
				$problems[] = $full . ': key ' . $key . ( $must_be_unique ? ' is not unique' : ' is unique' );
			}
		}
		return $problems;
	}

	/**
	 * Type part of a column definition (`bigint(20) unsigned NOT NULL …` → `bigint(20) unsigned`).
	 *
	 * @param string $definition Column definition.
	 * @return string
	 */
	public static function declared_type( string $definition ): string {
		if ( 1 === preg_match( '/^([a-z]+(?:\([^)]*\))?(?: unsigned)?)/i', trim( $definition ), $m ) ) {
			return strtolower( $m[1] );
		}
		return strtolower( trim( $definition ) );
	}

	/**
	 * Type without integer display width (MySQL 8 drops it, MariaDB keeps it).
	 *
	 * @param string $type Column type.
	 * @return string
	 */
	public static function normalise_type( string $type ): string {
		return (string) preg_replace( '/\b(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', strtolower( trim( $type ) ) );
	}
}
