<?php
/**
 * Multi-process race runner for integration tests (R0-21 §5 "P", CB-18).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Tests\Support;

/**
 * Forks one process per job. Every child opens its own DB connection,
 * waits for a shared start barrier, runs its job and reports a JSON
 * result. Children end with SIGKILL so no destructor touches the parent's
 * connection. Fixtures must be committed before `run()` (the WP test suite
 * runs inside a transaction that children cannot see).
 *
 * Scale: `CCAP_RACE_N` (processes per scenario) and `CCAP_RACE_REPEAT`
 * (repetitions); `CCAP_RACE_REPORT` appends a measurement line per run.
 */
final class RaceRunner {

	/**
	 * Whether forking is available.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return function_exists( 'pcntl_fork' ) && function_exists( 'posix_kill' );
	}

	/**
	 * Processes per scenario.
	 *
	 * @param int $fallback Default.
	 * @return int
	 */
	public static function n( int $fallback ): int {
		$n = (int) getenv( 'CCAP_RACE_N' );
		return $n > 1 ? $n : $fallback;
	}

	/**
	 * Repetitions.
	 *
	 * @param int $fallback Default.
	 * @return int
	 */
	public static function repeat( int $fallback ): int {
		$n = (int) getenv( 'CCAP_RACE_REPEAT' );
		return $n > 0 ? $n : $fallback;
	}

	/**
	 * Run jobs in parallel.
	 *
	 * @param string                  $scenario Scenario ID (for the report).
	 * @param array<string, callable> $jobs     Name => job(): mixed (JSON-serialisable).
	 * @param int                     $timeout  Seconds per child.
	 * @return array<string, array{ok: bool, value?: mixed, code?: string, error?: string, ms?: float}>
	 * @throws \RuntimeException When forking fails.
	 */
	public static function run( string $scenario, array $jobs, int $timeout = 30 ): array {
		global $wpdb;
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$dir = get_temp_dir() . 'ccap-race-' . wp_generate_password( 10, false );
		wp_mkdir_p( $dir );
		$pids  = array();
		$start = microtime( true );
		foreach ( $jobs as $name => $job ) {
			$pid = pcntl_fork();
			if ( -1 === $pid ) {
				throw new \RuntimeException( 'fork failed' );
			}
			if ( 0 === $pid ) {
				self::child( $dir, (string) $name, $job, $timeout );
			}
			$pids[ $name ] = $pid;
		}
		touch( $dir . '/go' );
		foreach ( $pids as $pid ) {
			pcntl_waitpid( $pid, $status );
		}
		$elapsed = ( microtime( true ) - $start ) * 1000;
		$results = array();
		foreach ( array_keys( $jobs ) as $name ) {
			$file             = $dir . '/' . $name . '.json';
			$results[ $name ] = is_file( $file ) ? (array) json_decode( (string) file_get_contents( $file ), true ) : array( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				'ok'    => false,
				'error' => 'no result',
			);
		}
		foreach ( (array) glob( $dir . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_cache_flush();
		self::report( $scenario, $results, $elapsed );
		return $results;
	}

	/**
	 * Count results by outcome (`ok` or error code).
	 *
	 * @param array<string, array<string, mixed>> $results Results.
	 * @return array<string, int>
	 */
	public static function outcomes( array $results ): array {
		$out = array();
		foreach ( $results as $result ) {
			$key         = ! empty( $result['ok'] ) ? 'ok' : (string) ( $result['code'] ?? 'error' );
			$out[ $key ] = ( $out[ $key ] ?? 0 ) + 1;
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Child body.
	 *
	 * @param string   $dir     Exchange directory.
	 * @param string   $name    Job name.
	 * @param callable $job     Job.
	 * @param int      $timeout Barrier timeout.
	 * @return never
	 */
	private static function child( string $dir, string $name, callable $job, int $timeout ) {
		$t0       = microtime( true );
		$baseline = \CourseCaptain\Storage\Database::deadlocks();
		try {
			$base = $GLOBALS['wpdb'];
			$own  = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$own->set_prefix( $base->base_prefix );
			$own->suppress_errors( true );
			if ( is_multisite() ) {
				$own->set_blog_id( get_current_blog_id() );
			}
			$GLOBALS['wpdb'] = $own; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			wp_cache_flush();
			\CourseCaptain\Policy\PolicyService::reset_instance();
			$deadline = microtime( true ) + $timeout;
			while ( ! file_exists( $dir . '/go' ) && microtime( true ) < $deadline ) {
				usleep( 500 );
			}
			$t0     = microtime( true );
			$value  = $job();
			$result = array(
				'ok'    => true,
				'value' => $value,
			);
		} catch ( \CourseCaptain\Course\CourseException $e ) {
			$result = array(
				'ok'   => false,
				'code' => $e->error_code,
			);
		} catch ( \Throwable $e ) {
			$result = array(
				'ok'    => false,
				'code'  => 'exception',
				'error' => get_class( $e ) . ': ' . $e->getMessage(),
			);
		}
		$result['ms']        = round( ( microtime( true ) - $t0 ) * 1000, 1 );
		$result['deadlocks'] = \CourseCaptain\Storage\Database::deadlocks() - $baseline;
		file_put_contents( $dir . '/' . $name . '.json', (string) wp_json_encode( $result ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		posix_kill( getmypid(), SIGKILL );
		exit( 1 );
	}

	/**
	 * Append a measurement line (JSON) when `CCAP_RACE_REPORT` names a file.
	 *
	 * @param string                              $scenario Scenario.
	 * @param array<string, array<string, mixed>> $results  Results.
	 * @param float                               $elapsed  Wall time (ms).
	 * @return void
	 */
	private static function report( string $scenario, array $results, float $elapsed ): void {
		$file = (string) getenv( 'CCAP_RACE_REPORT' );
		if ( '' === $file ) {
			return;
		}
		$times = array_map( static fn( array $r ): float => (float) ( $r['ms'] ?? 0 ), $results );
		sort( $times );
		$p95  = $times[ (int) max( 0, ceil( count( $times ) * 0.95 ) - 1 ) ] ?? 0;
		$line = array(
			'scenario'  => $scenario,
			'n'         => count( $results ),
			'outcomes'  => self::outcomes( $results ),
			'deadlocks' => array_sum( array_map( static fn( array $r ): int => (int) ( $r['deadlocks'] ?? 0 ), $results ) ),
			'wall_ms'   => round( $elapsed, 1 ),
			'p95_ms'    => $p95,
			'db'        => (string) $GLOBALS['wpdb']->get_var( 'SELECT VERSION()' ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		);
		file_put_contents( $file, wp_json_encode( $line ) . "\n", FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
}
