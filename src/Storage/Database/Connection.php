<?php
/**
 * Database access layer around wpdb.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Storage\Database;

use LogicException;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * The only place that talks to `$wpdb` (R1-02).
 *
 * - Values always go through prepared statements (`%d`, `%s`, `%f`; table and
 *   column names through `%i`).
 * - Every failed statement throws {@see DatabaseError} with the server error
 *   number; nothing continues silently after an error (R0-21 T-1).
 * - Transaction depth and after-commit callbacks are tracked here so that
 *   nested {@see TxRunner::run()} calls stay flat (T-4) and the
 *   {@see \CourseCaptain\Storage\Lock\LockService} can refuse locks inside an
 *   open transaction (R0-21 §2.4).
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- This class is the storage layer; caching happens above it (R0-21 §3).
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Statements reach these methods already prepared via self::prepare().
 */
final class Connection {

	public const ER_DUP_FIELDNAME      = 1060;
	public const ER_DUP_KEYNAME        = 1061;
	public const ER_DUP_ENTRY          = 1062;
	public const ER_BAD_FIELD_ERROR    = 1054;
	public const ER_NO_SUCH_TABLE      = 1146;
	public const ER_TABLEACCESS_DENIED = 1142;
	public const ER_LOCK_WAIT_TIMEOUT  = 1205;
	public const ER_LOCK_DEADLOCK      = 1213;
	public const ER_FK_DUP_NAME        = 1826;
	public const CR_SERVER_GONE_ERROR  = 2006;
	public const CR_SERVER_LOST        = 2013;

	/** Prefix of every own table after the site prefix (E-207). */
	public const TABLE_PREFIX = 'ccap_';

	/**
	 * Connections per wpdb instance, so that depth and callbacks are shared.
	 *
	 * @var array<int, self>
	 */
	private static array $instances = array();

	/**
	 * Open transaction depth (0 = autocommit).
	 *
	 * @var int
	 */
	private int $depth = 0;

	/**
	 * Callbacks to run after the outermost commit.
	 *
	 * @var list<callable(): void>
	 */
	private array $after_commit = array();

	/**
	 * Test-only fault injector: receives the SQL, returns an error number to simulate (0 = none).
	 *
	 * @var (callable(string): int)|null
	 */
	private $fault = null;

	/**
	 * Session value of innodb_lock_wait_timeout set by this layer.
	 *
	 * @var int|null
	 */
	private ?int $lock_wait_timeout = null;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb Database object (the global one or a dedicated connection).
	 */
	public function __construct( private wpdb $wpdb ) {
	}

	/**
	 * Connection of the global `$wpdb`.
	 *
	 * @return self
	 */
	public static function current(): self {
		global $wpdb;
		return self::for_wpdb( $wpdb );
	}

	/**
	 * Shared connection object for a wpdb instance.
	 *
	 * @param wpdb $db Database object.
	 * @return self
	 */
	public static function for_wpdb( wpdb $db ): self {
		$key = spl_object_id( $db );
		if ( ! isset( self::$instances[ $key ] ) || self::$instances[ $key ]->wpdb !== $db ) {
			self::$instances[ $key ] = new self( $db );
		}
		return self::$instances[ $key ];
	}

	/**
	 * Underlying wpdb object.
	 *
	 * @return wpdb
	 */
	public function wpdb(): wpdb {
		return $this->wpdb;
	}

	/**
	 * Site table prefix (`$wpdb->prefix`, differs per site in multisite).
	 *
	 * @return string
	 */
	public function prefix(): string {
		return (string) $this->wpdb->prefix;
	}

	/**
	 * Full name of an own table.
	 *
	 * @param string $name Name without prefixes, e.g. `grant`.
	 * @return string
	 */
	public function table( string $name ): string {
		return $this->prefix() . self::TABLE_PREFIX . $name;
	}

	/**
	 * Install or remove the test-only fault injector.
	 *
	 * @param (callable(string): int)|null $fault Callback returning an error number to simulate.
	 * @return void
	 */
	public function set_fault_injector( ?callable $fault ): void {
		$this->fault = $fault;
	}

	/**
	 * Prepare a statement. Without arguments the query is returned unchanged.
	 *
	 * @param string $query Query with placeholders.
	 * @param mixed  ...$args Values.
	 * @return string
	 */
	public function prepare( string $query, mixed ...$args ): string {
		if ( array() === $args ) {
			return $query;
		}
		$prepared = $this->wpdb->prepare( $query, ...$args );
		if ( ! is_string( $prepared ) || '' === $prepared ) {
			throw new DatabaseError( 'Statement could not be prepared.', 0, $query );
		}
		return $prepared;
	}

	/**
	 * Run a statement that returns no rows.
	 *
	 * @param string $sql Prepared statement.
	 * @return int Affected rows.
	 */
	public function execute( string $sql ): int {
		$result = $this->call( $sql, fn() => $this->wpdb->query( $sql ) );
		if ( false === $result ) {
			throw new DatabaseError( 'Statement failed.', $this->errno(), $sql );
		}
		return is_int( $result ) ? $result : (int) $this->wpdb->rows_affected;
	}

	/**
	 * First row as associative array.
	 *
	 * @param string $sql Prepared statement.
	 * @return array<string, string|null>|null
	 */
	public function get_row( string $sql ): ?array {
		$row = $this->call( $sql, fn() => $this->wpdb->get_row( $sql, ARRAY_A ) );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * All rows as associative arrays.
	 *
	 * @param string $sql Prepared statement.
	 * @return list<array<string, string|null>>
	 */
	public function get_results( string $sql ): array {
		$rows = $this->call( $sql, fn() => $this->wpdb->get_results( $sql, ARRAY_A ) );
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * First column of all rows.
	 *
	 * @param string $sql Prepared statement.
	 * @return list<string|null>
	 */
	public function get_col( string $sql ): array {
		$col = $this->call( $sql, fn() => $this->wpdb->get_col( $sql ) );
		return is_array( $col ) ? array_values( $col ) : array();
	}

	/**
	 * Single value.
	 *
	 * @param string $sql Prepared statement.
	 * @return string|null
	 */
	public function get_var( string $sql ): ?string {
		$value = $this->call( $sql, fn() => $this->wpdb->get_var( $sql ) );
		return null === $value ? null : (string) $value;
	}

	/**
	 * Insert one row.
	 *
	 * @param string                                $table Full table name.
	 * @param array<string, int|float|string|bool|null> $row   Column => value.
	 * @param bool                                  $ignore Use INSERT IGNORE.
	 * @return int Affected rows (0 when ignored).
	 */
	public function insert( string $table, array $row, bool $ignore = false ): int {
		if ( array() === $row ) {
			throw new LogicException( 'Insert without columns.' );
		}
		$columns = array();
		$values  = array();
		$args    = array( $table );
		foreach ( $row as $column => $value ) {
			$columns[] = '%i';
			$args[]    = $column;
		}
		foreach ( $row as $value ) {
			[ $placeholder, $value ] = self::placeholder( $value );
			$values[]                = $placeholder;
			if ( null !== $value ) {
				$args[] = $value;
			}
		}
		$sql = ( $ignore ? 'INSERT IGNORE INTO' : 'INSERT INTO' ) . ' %i (' . implode( ', ', $columns ) . ') VALUES (' . implode( ', ', $values ) . ')';
		return $this->execute( $this->prepare( $sql, ...$args ) );
	}

	/**
	 * Build a `col = value` list for UPDATE statements.
	 *
	 * @param array<string, int|float|string|bool|null> $changes Column => value.
	 * @return array{0: string, 1: list<int|float|string>} SQL fragment with placeholders and its arguments.
	 */
	public static function assignments( array $changes ): array {
		$parts = array();
		$args  = array();
		foreach ( $changes as $column => $value ) {
			[ $placeholder, $value ] = self::placeholder( $value );
			$parts[]                 = '%i = ' . $placeholder;
			$args[]                  = $column;
			if ( null !== $value ) {
				$args[] = $value;
			}
		}
		return array( implode( ', ', $parts ), $args );
	}

	/**
	 * Placeholder and normalised value for one column value.
	 *
	 * @param int|float|string|bool|null $value Value.
	 * @return array{0: string, 1: int|float|string|null}
	 */
	private static function placeholder( int|float|string|bool|null $value ): array {
		if ( null === $value ) {
			return array( 'NULL', null );
		}
		if ( is_bool( $value ) ) {
			return array( '%d', $value ? 1 : 0 );
		}
		if ( is_int( $value ) ) {
			return array( '%d', $value );
		}
		if ( is_float( $value ) ) {
			// Floats are passed as strings to keep decimal columns exact.
			return array( '%s', (string) $value );
		}
		return array( '%s', $value );
	}

	/**
	 * Last AUTO_INCREMENT value of this connection.
	 *
	 * @return int
	 */
	public function insert_id(): int {
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Server connection id (bound locks die with it, R0-21 §2.4).
	 *
	 * @return int
	 */
	public function connection_id(): int {
		return (int) $this->get_var( 'SELECT CONNECTION_ID()' );
	}

	/**
	 * Start a transaction, or join the open one (flat nesting, T-4).
	 *
	 * @param int|null $lock_wait_timeout Session innodb_lock_wait_timeout for this transaction.
	 * @return void
	 */
	public function begin( ?int $lock_wait_timeout = null ): void {
		if ( $this->depth > 0 ) {
			++$this->depth;
			return;
		}
		if ( null !== $lock_wait_timeout && $lock_wait_timeout !== $this->lock_wait_timeout ) {
			$this->execute( $this->prepare( 'SET SESSION innodb_lock_wait_timeout = %d', $lock_wait_timeout ) );
			$this->lock_wait_timeout = $lock_wait_timeout;
		}
		$this->execute( 'START TRANSACTION' );
		$this->depth        = 1;
		$this->after_commit = array();
	}

	/**
	 * Commit the outermost transaction and run the after-commit callbacks (T-5).
	 *
	 * @return void
	 */
	public function commit(): void {
		if ( $this->depth > 1 ) {
			--$this->depth;
			return;
		}
		if ( 1 !== $this->depth ) {
			throw new LogicException( 'Commit without transaction.' );
		}
		$this->execute( 'COMMIT' );
		$this->depth = 0;
		$callbacks   = $this->after_commit;

		$this->after_commit = array();
		foreach ( $callbacks as $callback ) {
			$callback();
		}
	}

	/**
	 * Roll back the whole transaction; callbacks are discarded (T-5).
	 *
	 * Rolls back the outermost transaction even when called from a nested
	 * level, because after any failure the operation is aborted as a whole.
	 *
	 * @return void
	 */
	public function rollback(): void {
		$this->after_commit = array();
		if ( 0 === $this->depth ) {
			return;
		}
		$this->depth = 0;
		$this->call( 'ROLLBACK', fn() => $this->wpdb->query( 'ROLLBACK' ) );
	}

	/**
	 * Whether a transaction of this layer is open.
	 *
	 * @return bool
	 */
	public function in_transaction(): bool {
		return $this->depth > 0;
	}

	/**
	 * Run a callback after the outermost commit, or now in autocommit.
	 *
	 * @param callable(): void $callback Callback.
	 * @return void
	 */
	public function after_commit( callable $callback ): void {
		if ( 0 === $this->depth ) {
			$callback();
			return;
		}
		$this->after_commit[] = $callback;
	}

	/**
	 * Run one wpdb call with errors suppressed and convert failures.
	 *
	 * @template T
	 * @param string      $sql  Statement (for the fault injector and the exception).
	 * @param callable(): T $call wpdb call.
	 * @return T
	 */
	private function call( string $sql, callable $call ): mixed {
		if ( null !== $this->fault ) {
			$errno = ( $this->fault )( $sql );
			if ( $errno > 0 ) {
				if ( Connection::ER_LOCK_DEADLOCK === $errno && $this->depth > 0 ) {
					// A real deadlock rolls back the whole transaction on the server.
					$this->wpdb->query( 'ROLLBACK' );
				}
				throw new DatabaseError( 'Injected error ' . $errno, $errno, $sql );
			}
		}

		$suppress = $this->wpdb->suppress_errors( true );
		try {
			$result = $call();
		} finally {
			$this->wpdb->suppress_errors( $suppress );
		}

		if ( '' !== (string) $this->wpdb->last_error ) {
			throw new DatabaseError( (string) $this->wpdb->last_error, $this->errno(), $sql );
		}
		return $result;
	}

	/**
	 * Error number of the last statement.
	 *
	 * @return int
	 */
	private function errno(): int {
		$dbh = $this->wpdb->__get( 'dbh' );
		if ( $dbh instanceof \mysqli ) {
			return mysqli_errno( $dbh );
		}
		return 0;
	}
}
