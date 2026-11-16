<?php
/**
 * R1-02: static checks of the schema definition.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Unit;

use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\Schema;

/**
 * DDL hygiene (R0-06 §4.0, R0-37 I-M2) without a database.
 *
 * @group R1-02
 */
final class SchemaTest extends TestCase {

	/**
	 * Every table is dbDelta-ready and follows the conventions.
	 */
	public function test_ddl_conventions(): void {
		foreach ( Schema::table_names() as $table ) {
			self::assertMatchesRegularExpression( '/^ccap_[a-z_]+$/', $table );
			$sql = Schema::create_statement( $table, 'wp_', 'DEFAULT CHARSET=utf8mb4' );
			self::assertStringContainsString( 'PRIMARY KEY  (', $sql, $table );
			self::assertStringContainsString( 'ENGINE=InnoDB', $sql, $table );
			self::assertStringContainsString( "COMMENT='course-captain:core'", $sql, $table );
			self::assertStringNotContainsStringIgnoringCase( 'CURRENT_TIMESTAMP', $sql, $table );
			self::assertStringNotContainsStringIgnoringCase( 'FOREIGN KEY', $sql, $table );
			self::assertStringNotContainsStringIgnoringCase( 'GENERATED', $sql, $table );
			self::assertDoesNotMatchRegularExpression( '/\bINDEX\b/', $sql, $table );
		}
	}

	/**
	 * Integer types carry the display width MariaDB reports, otherwise
	 * dbDelta alters them on every run.
	 */
	public function test_integer_types_have_display_width(): void {
		foreach ( Schema::tables() as $table => $definition ) {
			foreach ( $definition['columns'] as $column => $type ) {
				self::assertDoesNotMatchRegularExpression( '/^(tinyint|smallint|int|bigint)\b(?!\()/', $type, "{$table}.{$column}" );
			}
		}
	}

	/**
	 * Columns of unique keys are NOT NULL, except where several empty rows
	 * are intended (R0-06 §4.0).
	 */
	public function test_unique_key_columns_are_not_null(): void {
		$nullable_allowed = array( 'source_id', 'source_event_id', 'attachment_id', 'join_token_hash', 'claim_token_hash' );
		foreach ( Schema::table_names() as $table ) {
			$columns = Schema::tables()[ $table ]['columns'];
			foreach ( Schema::key_columns( $table ) as $name => $key ) {
				if ( ! $key['unique'] ) {
					continue;
				}
				foreach ( $key['columns'] as $column ) {
					self::assertArrayHasKey( $column, $columns, "{$table}.{$name}" );
					if ( ! in_array( $column, $nullable_allowed, true ) ) {
						self::assertStringContainsString( 'NOT NULL', $columns[ $column ], "{$table}.{$name}.{$column}" );
					}
				}
			}
		}
	}

	/**
	 * Every key column exists; every FK joins compatible columns of own tables.
	 */
	public function test_keys_and_foreign_keys_reference_existing_columns(): void {
		$tables = Schema::tables();
		foreach ( array_keys( $tables ) as $table ) {
			foreach ( Schema::key_columns( $table ) as $name => $key ) {
				foreach ( $key['columns'] as $column ) {
					self::assertArrayHasKey( $column, $tables[ $table ]['columns'], "{$table}.{$name}" );
				}
			}
		}
		foreach ( Schema::foreign_keys() as $fk ) {
			self::assertArrayHasKey( $fk['column'], $tables[ $fk['table'] ]['columns'] );
			self::assertArrayHasKey( $fk['ref_column'], $tables[ $fk['ref_table'] ]['columns'] );
			$base = static fn( string $type ): string => (string) preg_replace( '/ (NOT NULL|NULL).*$/', '', $type );
			self::assertSame( $base( $tables[ $fk['ref_table'] ]['columns'][ $fk['ref_column'] ] ), $base( $tables[ $fk['table'] ]['columns'][ $fk['column'] ] ), $fk['table'] . '.' . $fk['column'] );
			self::assertContains( $fk['on_delete'], array( 'CASCADE', 'RESTRICT', 'SET NULL' ) );
			if ( 'SET NULL' === $fk['on_delete'] ) {
				self::assertStringNotContainsString( 'NOT NULL', $tables[ $fk['table'] ]['columns'][ $fk['column'] ] );
			}
			self::assertLessThan(
				array_search( $fk['table'], array_keys( $tables ), true ) + ( $fk['table'] === $fk['ref_table'] ? 1 : 0 ),
				array_search( $fk['ref_table'], array_keys( $tables ), true ),
				'parent created first: ' . $fk['table']
			);
			self::assertLessThanOrEqual( 64, strlen( Migrator::foreign_key_name( 'a_very_long_prefix_of_a_site_42_', $fk['table'], $fk['column'] ) ) );
		}
	}

	/**
	 * Uniqueness of a grant source key fits into the index length limit.
	 */
	public function test_grant_source_key_fits_index_length(): void {
		$bytes = ( 32 + 191 + 64 ) * 4 + 4 + 8 + 8;
		self::assertLessThan( 3072, $bytes );
		self::assertSame(
			array( 'source_type', 'source_id', 'source_line', 'source_revision', 'course_id', 'module_id' ),
			Schema::key_columns( 'ccap_grant' )['grant_source']['columns']
		);
	}

	/**
	 * Versions: semver plus hash; fingerprint depends on plugin version.
	 */
	public function test_versions(): void {
		self::assertMatchesRegularExpression( '/^\d+\.\d+\.\d+\+[0-9a-f]{12}$/', Schema::db_version() );
		self::assertSame( Schema::VERSION, Schema::semver_of( Schema::db_version() ) );
		self::assertNotSame( Schema::fingerprint( '1.0.0' ), Schema::fingerprint( '1.0.1' ) );
		self::assertSame( array_reverse( Schema::table_names() ), Schema::drop_order() );
		self::assertSame( array( 'ccap_lease', 'ccap_migration_step' ), array_slice( Schema::table_names(), 0, 2 ) );
	}
}
