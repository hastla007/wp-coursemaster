<?php
/**
 * Declarative definition of one own table.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Columns, keys and foreign keys of one table (R0-06 §4).
 *
 * The CREATE statement is `dbDelta` compatible (one field per line,
 * `PRIMARY KEY  (…)` with two spaces, no foreign keys, no generated columns,
 * R0-37 I-M2). It carries the ownership marker as table comment (KOMP-131).
 */
final class Table {

	/**
	 * Constructor.
	 *
	 * @param string                      $name    Short name without prefixes.
	 * @param array<string, string>       $columns Column => definition.
	 * @param list<string>                $primary Primary key columns.
	 * @param array<string, list<string>> $unique  Unique key name => columns (`col` or `col(len)`).
	 * @param array<string, list<string>> $index   Index name => columns.
	 * @param list<ForeignKey>            $foreign Foreign keys.
	 * @param string                      $module  Owning module (marker `course-captain:<module>`).
	 */
	public function __construct(
		public readonly string $name,
		public readonly array $columns,
		public readonly array $primary,
		public readonly array $unique = array(),
		public readonly array $index = array(),
		public readonly array $foreign = array(),
		public readonly string $module = 'core',
	) {
	}

	/**
	 * Ownership marker stored as table comment (KOMP-131, R0-20 D-20-03).
	 *
	 * @return string
	 */
	public function marker(): string {
		return Schema::MARKER_PREFIX . $this->module;
	}

	/**
	 * Full table name.
	 *
	 * @param string $prefix Site table prefix.
	 * @return string
	 */
	public function full_name( string $prefix ): string {
		return $prefix . 'ccap_' . $this->name;
	}

	/**
	 * `dbDelta` compatible CREATE TABLE statement.
	 *
	 * @param string $prefix          Site table prefix.
	 * @param string $charset_collate Result of `$wpdb->get_charset_collate()`.
	 * @return string
	 */
	public function create_sql( string $prefix, string $charset_collate ): string {
		$lines = array();
		foreach ( $this->columns as $column => $definition ) {
			$lines[] = $column . ' ' . $definition;
		}
		$lines[] = 'PRIMARY KEY  (' . implode( ',', $this->primary ) . ')';
		foreach ( $this->unique as $key => $columns ) {
			$lines[] = 'UNIQUE KEY ' . $key . ' (' . implode( ',', $columns ) . ')';
		}
		foreach ( $this->index as $key => $columns ) {
			$lines[] = 'KEY ' . $key . ' (' . implode( ',', $columns ) . ')';
		}
		return 'CREATE TABLE ' . $this->full_name( $prefix ) . " (\n" . implode( ",\n", $lines ) . "\n) ENGINE=InnoDB " . trim( $charset_collate ) . " COMMENT='" . $this->marker() . "';";
	}

	/**
	 * Every key with its columns, primary key as `PRIMARY`.
	 *
	 * @return array<string, list<string>>
	 */
	public function keys(): array {
		return array( 'PRIMARY' => $this->primary ) + $this->unique + $this->index;
	}
}
