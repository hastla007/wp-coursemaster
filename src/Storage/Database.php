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
	 * Nesting depth of `transaction()` per connection (wrappers are per call).
	 *
	 * @var array<int, int>
	 */
	private static array $depths = array();

	/**
	 * Callbacks for the next commit per connection (R0-21 T-5); dropped on rollback.
	 *
	 * @var array<int, list<callable>>
	 */
	private static array $queues = array();

	/**
	 * Deadlocks seen in this process.
	 *
	 * @var int
	 */
	private static int $deadlocks = 0;

	/**
	 * Connections whose session was prepared (MariaDB snapshot isolation), keyed by
	 * wpdb object and server thread so a reconnect is prepared again.
	 *
	 * @var array<string, true>
	 */
	private static array $sessions = array();

	/**
	 * Total attempts of a unit of work on deadlocks (T-2).
	 */
	public const DEADLOCK_RETRIES = 3;

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
	 * @throws \CourseCaptain\Course\CourseException `ccap_db_busy` after 3 deadlocks or a lock wait timeout.
	 */
	public function transaction( Closure $work ) {
		$conn = spl_object_id( $this->wpdb );
		if ( ( self::$depths[ $conn ] ?? 0 ) > 0 ) {
			++self::$depths[ $conn ];
			try {
				return $work( $this );
			} finally {
				--self::$depths[ $conn ];
			}
		}

		// T-2: a deadlock (1213) re-runs the whole unit up to three times; T-3: a lock wait
		// timeout (1205) is not retried. Both end in `503 ccap_db_busy` with `retry_after`.
		$this->prepare_session();
		for ( $attempt = 1; ; $attempt++ ) {
			$this->execute( 'START TRANSACTION' );
			self::$depths[ $conn ] = 1;
			self::$queues[ $conn ] = array();
			try {
				$result = $work( $this );
				$this->execute( 'COMMIT' );
			} catch ( Throwable $e ) {
				// Explicit rollback, also after lock wait timeouts (R0-21 T-3).
				$this->wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::$queues[ $conn ] = array();
				self::$depths[ $conn ] = 0;
				$code                  = $e instanceof StorageException ? (int) $e->getCode() : 0;
				// 1020 "record has changed since last read" (MariaDB snapshot isolation) is retried like a deadlock.
				if ( 1213 === $code || 1020 === $code ) {
					++self::$deadlocks;
					if ( $attempt < self::DEADLOCK_RETRIES ) {
						usleep( wp_rand( 5000, 25000 ) * $attempt );
						continue;
					}
				}
				if ( 1213 === $code || 1205 === $code || 1020 === $code ) {
					throw new \CourseCaptain\Course\CourseException( 'ccap_db_busy', 'Database busy.', 503, array( 'retry_after' => 2 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: machine code for the entry point.
				}
				throw $e;
			}
			self::$depths[ $conn ] = 0;
			break;
		}
		$callbacks             = self::$queues[ $conn ];
		self::$queues[ $conn ] = array();
		foreach ( $callbacks as $callback ) {
			$callback();
		}
		return $result;
	}

	/**
	 * MariaDB 11.6.2+ (MINVER-1 target 12.3) enables `innodb_snapshot_isolation` by default:
	 * a consistent read followed by `SELECT … FOR UPDATE` on a row changed meanwhile fails with
	 * error 1020 and rolls back the whole transaction. The lock order of R0-21 relies on the
	 * MySQL/MariaDB-10 semantics (locking reads see the latest committed row), so the session
	 * switches it off once per connection. MySQL 9.7 has no such variable.
	 *
	 * @return void
	 */
	private function prepare_session(): void {
		$dbh    = $this->wpdb->dbh ?? null;
		$thread = $dbh instanceof \mysqli ? (string) $dbh->thread_id : '0';
		$key    = spl_object_id( $this->wpdb ) . ':' . $thread;
		if ( isset( self::$sessions[ $key ] ) ) {
			return;
		}
		self::$sessions[ $key ] = true;
		$version                = (string) $this->wpdb->get_var( 'SELECT VERSION()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! self::needs_snapshot_isolation_off( $version ) ) {
			return;
		}
		$this->wpdb->query( 'SET SESSION innodb_snapshot_isolation = OFF' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Whether a server version has `innodb_snapshot_isolation` (MariaDB ≥ 11.6.2).
	 *
	 * @param string $version Raw `SELECT VERSION()` result.
	 * @return bool
	 */
	public static function needs_snapshot_isolation_off( string $version ): bool {
		if ( false === stripos( $version, 'mariadb' ) ) {
			return false;
		}
		$clean = preg_replace( '/^5\.5\.5-/', '', $version );
		if ( ! is_string( $clean ) || 1 !== preg_match( '/^(\d+\.\d+\.\d+)/', $clean, $m ) ) {
			return false;
		}
		return version_compare( $m[1], '11.6.2', '>=' );
	}

	/**
	 * Deadlocks seen in this process (measurement, R0-21 CON-046).
	 *
	 * @return int
	 */
	public static function deadlocks(): int {
		return self::$deadlocks;
	}

	/**
	 * Run after the outer commit, or now outside a transaction (T-5).
	 *
	 * @param callable $callback Callback without arguments.
	 * @return void
	 */
	public function after_commit( callable $callback ): void {
		$conn = spl_object_id( $this->wpdb );
		if ( ( self::$depths[ $conn ] ?? 0 ) > 0 ) {
			self::$queues[ $conn ][] = $callback;
			return;
		}
		$callback();
	}

	/**
	 * Whether a transaction is open.
	 *
	 * @return bool
	 */
	public function in_transaction(): bool {
		return ( self::$depths[ spl_object_id( $this->wpdb ) ] ?? 0 ) > 0;
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
		// Explicit formats: core's `$wpdb->field_types` maps e.g. `object_id` to %d and would cast strings (hashes, UIDs).
		$formats = array_map( static fn( $value ): string => is_int( $value ) || is_bool( $value ) ? '%d' : ( is_float( $value ) ? '%f' : '%s' ), array_values( $data ) );
		$result  = $this->wpdb->insert( $this->table( $table ), $data, $formats );
		if ( false === $result ) {
			throw StorageException::from_wpdb( $this->wpdb, 'insert into ' . $table ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not output: shortened DB error for logs.
		}
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Insert one row with a prepared statement and fixed formats, without
	 * the column metadata lookup of `$wpdb->insert()` (`SHOW FULL COLUMNS`
	 * once per table and request; R0-14 OP-04/OP-05 hot paths). Strings
	 * are stored as valid UTF-8 (invalid bytes are stripped); NULL stays NULL.
	 *
	 * @param string               $table Table name without prefix.
	 * @param array<string, mixed> $data  Column => int|float|bool|string|null.
	 * @return int Insert ID.
	 * @throws StorageException On database errors (duplicates: code 1062).
	 */
	public function insert_values( string $table, array $data ): int {
		$columns = array();
		$places  = array();
		$values  = array();
		foreach ( $data as $column => $value ) {
			self::assert_identifier( (string) $column );
			$columns[] = '`' . $column . '`';
			if ( null === $value ) {
				$places[] = 'NULL';
				continue;
			}
			if ( is_int( $value ) || is_bool( $value ) ) {
				$places[] = '%d';
				$values[] = (int) $value;
			} elseif ( is_float( $value ) ) {
				$places[] = '%f';
				$values[] = $value;
			} else {
				$places[] = '%s';
				$values[] = wp_check_invalid_utf8( (string) $value, true );
			}
		}
		$sql = 'INSERT INTO `' . $this->table( $table ) . '` (' . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $places ) . ')';
		$this->execute( array() === $values ? $sql : $this->prepare( $sql, ...$values ) );
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
