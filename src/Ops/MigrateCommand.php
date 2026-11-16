<?php
/**
 * WP-CLI `wp course-captain migrate` (R0-37 T-6, R0-19 §4.7).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Storage\Migrator;
use CourseCaptain\Storage\MigrationTrigger;

defined( 'ABSPATH' ) || exit;

/**
 * Operator path for the schema migration of the current site (`--url`).
 *
 * Runs without time budget and ignores the backoff; a poisoned step is
 * tried again (manual run). Exit code 0 for `done`, `none` and
 * `skipped_locked` (another runner is active, MIG-002), otherwise 1.
 *
 * Supported: `--status` (report only, no write), `--format=json`.
 * Not yet: `--dry-run`, `--step`, `--allow-contract`, `--all-sites`
 * (see R1-40 proof).
 */
final class MigrateCommand {

	/**
	 * Statuses that end with exit code 0.
	 *
	 * @var list<string>
	 */
	public const SUCCESS = array( 'done', 'none', 'skipped_locked' );

	/**
	 * Register the command when WP-CLI is loaded.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'course-captain migrate', array( self::class, 'invoke' ) );
		}
	}

	/**
	 * WP-CLI entry point.
	 *
	 * ## OPTIONS
	 *
	 * [--status]
	 * : Show the migration state without changing anything.
	 *
	 * [--format=<format>]
	 * : Output format: text or json.
	 *
	 * @param array<int, string>    $args  Positional arguments (unused).
	 * @param array<string, string> $assoc Options.
	 * @return void
	 */
	public static function invoke( array $args, array $assoc ): void {
		unset( $args );
		$out = self::execute( Migrator::for_current_site(), $assoc );
		foreach ( $out['lines'] as $line ) {
			\WP_CLI::line( $line );
		}
		\WP_CLI::halt( $out['exit'] );
	}

	/**
	 * Testable core of the command.
	 *
	 * @param Migrator              $migrator Migrator of the site.
	 * @param array<string, string> $assoc    Options.
	 * @return array{exit: int, status: string, lines: list<string>}
	 */
	public static function execute( Migrator $migrator, array $assoc ): array {
		$json = 'json' === ( $assoc['format'] ?? 'text' );
		if ( isset( $assoc['status'] ) ) {
			$report = $migrator->report();
			$lines  = $json ? array( (string) wp_json_encode( $report ) ) : self::report_lines( $report );
			return array(
				'exit'   => in_array( $report['state'], array( 'ok', 'backfill', 'running' ), true ) ? 0 : 1,
				'status' => $report['state'],
				'lines'  => $lines,
			);
		}

		$result = $migrator->run( MigrationTrigger::CONTEXT_CLI );
		$exit   = in_array( $result['status'], self::SUCCESS, true ) ? 0 : 1;
		if ( $json ) {
			$lines = array( (string) wp_json_encode( $result ) );
		} else {
			$lines = array( 'Result: ' . $result['status'] );
			if ( '' !== $result['error'] ) {
				$lines[] = 'Error: ' . $result['error'];
			}
			foreach ( $result['backfill'] as $id => $status ) {
				$lines[] = 'Backfill ' . $id . ': ' . $status;
			}
		}
		return array(
			'exit'   => $exit,
			'status' => $result['status'],
			'lines'  => $lines,
		);
	}

	/**
	 * Text lines of a status report.
	 *
	 * @param array{state: string, stored_version: string|null, code_version: string, steps: array<string, array{status: string, kind: string, attempts: int, crash_count: int, blocked_reason: string|null, rows_done: int, rows_total: int|null, last_error: string|null}>} $report Report.
	 * @return list<string>
	 */
	private static function report_lines( array $report ): array {
		$lines = array(
			'State: ' . $report['state'],
			'Schema: ' . ( $report['stored_version'] ?? '-' ) . ' (code ' . $report['code_version'] . ')',
		);
		foreach ( $report['steps'] as $id => $step ) {
			$line = sprintf( '%s  %s  attempts=%d crashes=%d', $id, $step['status'], $step['attempts'], $step['crash_count'] );
			if ( null !== $step['blocked_reason'] ) {
				$line .= ' blocked=' . $step['blocked_reason'];
			}
			if ( 'backfill' === $step['kind'] ) {
				$line .= sprintf( ' rows=%d/%s', $step['rows_done'], null === $step['rows_total'] ? '?' : (string) $step['rows_total'] );
			}
			$lines[] = $line;
		}
		return $lines;
	}
}
