<?php
/**
 * Foreign key between two own tables.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Hard foreign key, only between `ccap_` tables (E-48). Created after
 * dbDelta in an own, non-fatal step (R0-06 §6.2, R0-37 P3).
 */
final class ForeignKey {

	public const RESTRICT = 'RESTRICT';
	public const CASCADE  = 'CASCADE';
	public const SET_NULL = 'SET NULL';

	/**
	 * Constructor.
	 *
	 * @param string $column     Referencing column.
	 * @param string $ref_table  Referenced table (short name).
	 * @param string $ref_column Referenced column.
	 * @param string $on_delete  RESTRICT, CASCADE or SET NULL.
	 */
	public function __construct(
		public readonly string $column,
		public readonly string $ref_table,
		public readonly string $ref_column,
		public readonly string $on_delete,
	) {
	}

	/**
	 * Constraint name; constraint names are unique per database, so the site
	 * prefix is part of it. Longer than 64 characters → md5 form (R0-06 §6.2).
	 *
	 * @param string $prefix Site table prefix.
	 * @param string $table  Referencing table (short name).
	 * @return string
	 */
	public function name( string $prefix, string $table ): string {
		$name = $prefix . 'ccap_' . $table . '_' . $this->column . '_fk';
		return strlen( $name ) <= 64 ? $name : 'ccap_fk_' . md5( $name );
	}

	/**
	 * `ALTER TABLE` statement that adds the constraint.
	 *
	 * @param string $prefix Site table prefix.
	 * @param string $table  Referencing table (short name).
	 * @return string
	 */
	public function add_sql( string $prefix, string $table ): string {
		return sprintf(
			'ALTER TABLE `%1$s` ADD CONSTRAINT `%2$s` FOREIGN KEY (`%3$s`) REFERENCES `%4$s` (`%5$s`) ON DELETE %6$s',
			$prefix . 'ccap_' . $table,
			$this->name( $prefix, $table ),
			$this->column,
			$prefix . 'ccap_' . $this->ref_table,
			$this->ref_column,
			$this->on_delete
		);
	}
}
