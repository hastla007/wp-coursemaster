<?php
/**
 * WP-CLI `wp course-captain status|cache flush|outbox|integrations|diagnostics`
 * (R0-19 §4.7; R0-21 §3.7; R0-13 §2.1, §2.2).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;
use CourseCaptain\Storage\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Actor `system:cli`. Exit code 0 = ok, 1 = error or a failing check.
 * Writing outbox commands need `--yes` (or the interactive confirmation)
 * and write audit entries; discarding needs `--reason`.
 *
 * `cache flush --user=<id>`: WP-CLI consumes `--user` as its global
 * parameter (current user); the command therefore takes the target user
 * from the current user when no own `user` option arrives.
 */
final class OpsCommand {

	/**
	 * Register the commands when WP-CLI is loaded.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) ) {
			return;
		}
		foreach ( array( 'status', 'cache flush', 'outbox status', 'outbox retry', 'outbox discard', 'integrations status', 'diagnostics', 'backup-check' ) as $command ) {
			\WP_CLI::add_command(
				'course-captain ' . $command,
				static function ( array $args, array $assoc ) use ( $command ): void {
					self::invoke( $command, $args, $assoc );
				}
			);
		}
	}

	/**
	 * WP-CLI entry point.
	 *
	 * @param string                $command Sub-command.
	 * @param array<int, string>    $args    Positional arguments.
	 * @param array<string, string> $assoc   Options.
	 * @return void
	 */
	public static function invoke( string $command, array $args, array $assoc ): void {
		if ( 'cache flush' === $command && ! isset( $assoc['user'] ) && get_current_user_id() > 0 ) {
			$assoc['user'] = (string) get_current_user_id();
		}
		if ( in_array( $command, array( 'outbox retry', 'outbox discard' ), true ) && ! isset( $assoc['yes'] ) ) {
			\WP_CLI::confirm( 'outbox retry' === $command ? 'Retry all dead letters?' : 'Discard all dead letters?' );
			$assoc['yes'] = '';
		}
		$out = self::execute( $command, $args, $assoc, Actor::cli() );
		foreach ( $out['lines'] as $line ) {
			\WP_CLI::line( $line );
		}
		\WP_CLI::halt( $out['exit'] );
	}

	/**
	 * Testable core.
	 *
	 * @param string                $command Sub-command.
	 * @param array<int, string>    $args    Positional arguments (unused).
	 * @param array<string, string> $assoc   Options.
	 * @param Actor                 $actor   Actor.
	 * @return array{exit: int, lines: list<string>}
	 */
	public static function execute( string $command, array $args, array $assoc, Actor $actor ): array {
		unset( $args );
		$json = 'json' === ( $assoc['format'] ?? 'text' );
		try {
			switch ( $command ) {
				case 'status':
					$report = OpsStatus::report( $actor );
					$data   = array(
						'overall' => $report['overall'],
						'checks'  => $report['checks'],
					);
					$lines  = array( 'Overall: ' . $report['overall'] );
					foreach ( $report['checks'] as $check ) {
						$lines[] = sprintf( '%-5s %-4s %s%s', $check['id'], $check['status'], $check['label'], array() === $check['ids'] ? '' : ' [' . implode( ', ', $check['ids'] ) . ']' );
					}
					return self::out( 'fail' === $report['overall'] ? 1 : 0, $json, $data, $lines );
				case 'cache flush':
					$result = RepairService::current()->flush_cache( $actor, (int) ( $assoc['user'] ?? 0 ), (int) ( $assoc['course'] ?? 0 ) );
					return self::out( 0, $json, $result, array( 'Success: cache generation renewed (' . implode( ', ', $result['scope'] ) . ').' ) );
				case 'outbox status':
					$counts = OpsStatus::outbox_counts( Database::current() );
					$lines  = array();
					foreach ( $counts as $status => $count ) {
						$lines[] = $status . ': ' . $count;
					}
					return self::out( 0, $json, $counts, $lines );
				case 'outbox retry':
				case 'outbox discard':
					if ( ! isset( $assoc['yes'] ) ) {
						return self::out( 1, $json, array( 'error' => 'ccap_confirmation_required' ), array( 'Error: add --yes to confirm.' ) );
					}
					$service = RepairService::current();
					$result  = 'outbox retry' === $command ? $service->retry_deliveries( $actor ) : $service->discard_deliveries( $actor, (string) ( $assoc['reason'] ?? '' ) );
					return self::out( 0, $json, $result, array( 'Success: ' . $result['count'] . ' deliveries ' . ( 'outbox retry' === $command ? 'queued again.' : 'discarded.' ) ) );
				case 'integrations status':
					$records = IntegrationStatus::all( $actor, isset( $assoc['detected'] ) );
					$lines   = array();
					foreach ( $records as $record ) {
						$lines[] = sprintf( '%-18s %s/%s %s', $record['provider_id'], $record['state'], $record['state_reason'], (string) ( $record['partner']['version'] ?? $record['partner']['contract_version'] ?? '' ) );
					}
					return self::out( 0, $json, $records, $lines );
				case 'backup-check':
					// Read only (R0-16 §1.4): exit 1 on fail, 2 on warn; `--full` hashes every file.
					$report = RestoreCheck::run( isset( $assoc['full'] ) );
					$lines  = array( 'Overall: ' . $report['status'] );
					foreach ( $report['checks'] as $check ) {
						$lines[] = sprintf( '%-12s %-4s %s', $check['id'], $check['status'], $check['message'] );
						foreach ( $check['items'] as $item ) {
							$lines[] = '  - ' . $item;
						}
					}
					return self::out(
						array(
							'ok'   => 0,
							'warn' => 2,
						)[ $report['status'] ] ?? 1,
						$json,
						$report,
						$lines
					);
				case 'diagnostics':
					$package = Diagnostics::current()->export( $actor );
					if ( isset( $assoc['output'] ) && '' !== $assoc['output'] ) {
						Diagnostics::write_file( (string) $assoc['output'], $package['body'] );
						return array(
							'exit'  => 0,
							'lines' => array( 'Success: written ' . $package['bytes'] . ' bytes, sha256 ' . $package['sha256'] . '.' ),
						);
					}
					return array(
						'exit'  => 0,
						'lines' => array( $package['body'] ),
					);
			}
		} catch ( CourseException $e ) {
			return self::out( 1, $json, array( 'error' => $e->error_code ), array( 'Error: ' . $e->error_code . ' ' . $e->getMessage() ) );
		}
		return self::out( 1, $json, array( 'error' => 'ccap_not_found' ), array( 'Error: unknown command.' ) );
	}

	/**
	 * Output as text lines or one JSON line.
	 *
	 * @param int   $code  Exit code.
	 * @param bool  $json  JSON format.
	 * @param mixed $data  Data for JSON.
	 * @param array $lines Text lines.
	 * @phpstan-param list<string> $lines
	 * @return array{exit: int, lines: list<string>}
	 */
	private static function out( int $code, bool $json, $data, array $lines ): array {
		return array(
			'exit'  => $code,
			'lines' => $json ? array( (string) wp_json_encode( $data ) ) : $lines,
		);
	}
}
