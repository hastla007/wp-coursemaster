<?php
/**
 * Thin, testable access layer over $wpdb for all repositories.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage;

use Closure;
use Throwable;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Shared conventions for every repository (R0-06 §2, §4.0):
 * - table names `{$wpdb->prefix}ccap_*` of the current site,
 * - UTC timestamps from PHP, never from the DB session,
 * - UUID v4 for stable external IDs,
 * - short transactions with explicit rollback,
 * - compare-and-swap on `row_version`,
 * - every DB error becomes a `StorageException` (no silent `false`).
 */
final class Database {

	/**
	 * Nesting depth of `transaction()`.
	 *
	 * @var int
	 */
	private int $depth = 0;

	/**
	 * Constructor.
	 *
	 * @param wpdb         $wpdb  WordPress database object.
	 * @param Closure|null $clock Returns the current Unix time (tests).
	 */
	public function __construct( private wpdb $wpdb, private ?Closure $clock = null ) {
	}

	/**
	 * Instance for the current site.
	 *
	 * @return self
	 */
	public static function current(): self {
		global $wpdb;
		return new self( $wpdb );
	}

	/**
	 * The wrapped $wpdb.
	 *
	 * @return wpdb
	 */
	public function wpdb(): wpdb {
		return $this->wpdb;
	}

	/**
	 * Full table name of an own table on the current site.
	 *
	 * @param string $name Table name without site prefix, e.g. `ccap_grant`.
	 * @return string
	 * @throws StorageException For foreign table names.
	 */
	public function table( string $name ): string {
		if ( ! str_starts_with( $name, 'ccap_' ) ) {
			throw new StorageException( 'Only own tables can be addressed.' );
		}
		return $this->wpdb->prefix . $name;
	}

	/**
	 * Current Unix time.
	 *
	 * @return int
	 */
	public function time(): int {
		return null === $this->clock ? time() : (int) ( $this->clock )();
	}

	/**
	 * Current UTC time as MySQL datetime.
	 *
	 * @param int $offset Seconds to add.
	 * @return string
	 */
	public function now( int $offset = 0 ): string {
		return gmdate( 'Y-m-d H:i:s', $this->time() + $offset );
	}

	/**
	 * New UUID v4.
	 *
	 * @return string
	 */
	public function uuid(): string {
		return wp_generate_uuid4();
	}

	/**
	 * Run a callback inside a transaction. Nested calls join the outer
	 * transaction. Any exception rolls back and is rethrown.
	 *
	 * @template T
	 * @param Closure $work Unit of work.
	 * @phpstan-param Closure(self): T $work
	 * @return T
	 * @throws Throwable Rethrown from the unit of work after rollback.
	 */
	public function transaction( Closure $work ) {
		if ( $this->depth > 0 ) {
			++$this->depth;
			try {
				return $work( $this );
			} finally {
				--$this->depth;
			}
		}

		$this->execute( 'START TRANSACTION' );
		$this->depth = 1;
		try {
			$result = $work( $this );
			$this->execute( 'COMMIT' );
			return $result;
		} catch ( Throwable $e ) {
			// Explicit rollback, also after lock wait timeouts (R0-21 T-3).
			$this->wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			throw $e;
		} finally {
			$this->depth = 0;
		}
	}

	/**
	 * Whether a transaction is open.
	 *
	 * @return bool
	 */
	public function in_transaction(): bool {
		return $this->depth > 0;
	}

	/**
	 * Insert a row; fills `created_at`/`updated_at` if the caller left them
	 * out and the table has them (pass `$timestamps`).
	 *
	 * @param string               $table      Table name without prefix.
	 * @param array<string, mixed> $data       Column => value.
	 * @param array                $timestamps Timestamp columns to fill.
	 * @phpstan-param list<string> $timestamps
	 * @return int Insert ID (0 for tables without auto increment).
	 * @throws StorageException On database errors.
	 */
	public function insert( string $table, array $data, array $timestamps = array() ): int {
		foreach ( $timestamps as $column ) {
			if ( ! array_key_exists( $column, $data ) ) {
				$data[ $column ] = $this->now();
			}
		}
		$this->wpdb->last_error = '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $this->wpdb->insert( $this->table( $table ), $data );
		if ( false === $result ) {
			throw StorageException::from_wpdb( $this->wpdb, 'insert into ' . $table ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: shortened DB error for logs.
		}
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Compare-and-swap update on `row_version` (Kap. 37, R0-06 §4.0).
	 *
	 * @param string               $table            Table name without prefix.
	 * @param int                  $id               Primary key.
	 * @param int                  $expected_version Version read before.
	 * @param array<string, mixed> $data             Column => value.
	 * @return bool True when exactly this version was updated.
	 * @throws StorageException On database errors.
	 */
	public function compare_and_swap( string $table, int $id, int $expected_version, array $data ): bool {
		if ( array() === $data ) {
			throw new StorageException( 'Nothing to update.' );
		}
		$sets   = array();
		$values = array();
		foreach ( $data as $column => $value ) {
			self::assert_identifier( $column );
			if ( null === $value ) {
				$sets[] = "`{$column}` = NULL";
				continue;
			}
			$sets[]   = "`{$column}` = " . ( is_int( $value ) ? '%d' : '%s' );
			$values[] = $value;
		}
		$values[] = $id;
		$values[] = $expected_version;
		$sql      = 'UPDATE `' . $this->table( $table ) . '` SET ' . implode( ', ', $sets ) . ', `row_version` = `row_version` + 1 WHERE `id` = %d AND `row_version` = %d';
		return 1 === $this->execute( $this->prepare( $sql, ...$values ) );
	}

	/**
	 * Run a write statement and return the affected rows.
	 *
	 * @param string $sql Prepared SQL.
	 * @return int
	 * @throws StorageException On database errors.
	 */
	public function execute( string $sql ): int {
		$this->wpdb->last_error = '';
		$result                 = $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Callers pass prepared SQL.
		if ( false === $result || '' !== self::error_of( $this->wpdb ) ) {
			throw StorageException::from_wpdb( $this->wpdb, $sql ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: shortened DB error for logs.
		}
		return (int) $result;
	}

	/**
	 * One row as associative array.
	 *
	 * @param string $sql Prepared SQL.
	 * @return array<string, mixed>|null
	 * @throws StorageException On database errors.
	 */
	public function row( string $sql ): ?array {
		$this->wpdb->last_error = '';
		$row                    = $this->wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Callers pass prepared SQL.
		if ( '' !== self::error_of( $this->wpdb ) ) {
			throw StorageException::from_wpdb( $this->wpdb, $sql ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: shortened DB error for logs.
		}
		return is_array( $row ) ? $row : null;
	}

	/**
	 * All rows as associative arrays.
	 *
	 * @param string $sql Prepared SQL.
	 * @return list<array<string, mixed>>
	 * @throws StorageException On database errors.
	 */
	public function rows( string $sql ): array {
		$this->wpdb->last_error = '';
		$rows                   = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Callers pass prepared SQL.
		if ( '' !== self::error_of( $this->wpdb ) ) {
			throw StorageException::from_wpdb( $this->wpdb, $sql ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: shortened DB error for logs.
		}
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * A single scalar.
	 *
	 * @param string $sql Prepared SQL.
	 * @return string|null
	 * @throws StorageException On database errors.
	 */
	public function scalar( string $sql ): ?string {
		$this->wpdb->last_error = '';
		$value                  = $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Callers pass prepared SQL.
		if ( '' !== self::error_of( $this->wpdb ) ) {
			throw StorageException::from_wpdb( $this->wpdb, $sql ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: shortened DB error for logs.
		}
		return null === $value ? null : (string) $value;
	}

	/**
	 * Prepare a statement (wrapper for testability).
	 *
	 * @param string $sql    SQL with placeholders.
	 * @param mixed  ...$args Values.
	 * @return string
	 */
	public function prepare( string $sql, ...$args ): string {
		// @phpstan-ignore argument.type (Table names are interpolated from Schema constants; values stay placeholders.)
		return (string) $this->wpdb->prepare( $sql, ...$args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This is the prepare wrapper.
	}

	/**
	 * Last error of $wpdb (helper without type narrowing).
	 *
	 * @param wpdb $wpdb Database object.
	 * @return string
	 */
	public static function error_of( wpdb $wpdb ): string {
		return (string) $wpdb->last_error;
	}

	/**
	 * Guard for column/key identifiers built into SQL.
	 *
	 * @param string $identifier Identifier.
	 * @return void
	 * @throws StorageException For invalid identifiers.
	 */
	public static function assert_identifier( string $identifier ): void {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/', $identifier ) ) {
			throw new StorageException( esc_html__( 'Invalid identifier.', 'course-captain' ) );
		}
	}
}
