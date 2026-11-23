<?php
/**
 * WP-CLI `wp course-captain import status|run|cancel|report` (R0-38 §4.6, E-178).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Import;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * Runs as the WP-CLI user (`--user=<id>` with `ccap_import_content` and,
 * for `run`, `ccap_publish_courses`). `run` uses the same runner as
 * background and browser without a time budget: it starts an approved
 * batch, resumes an aborted one or continues a running one. Exit code 0 =
 * ok, 1 = error or the run did not reach `published`.
 */
final class ImportCommand {

	public const COMMANDS = array( 'status', 'run', 'cancel', 'report' );

	/**
	 * Register the commands when WP-CLI is loaded.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( ! ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) ) {
			return;
		}
		foreach ( self::COMMANDS as $command ) {
			\WP_CLI::add_command(
				'course-captain import ' . $command,
				static function ( array $args, array $assoc ) use ( $command ): void {
					$out = self::execute( $command, $args, $assoc, Actor::user( get_current_user_id() ) );
					foreach ( $out['lines'] as $line ) {
						\WP_CLI::line( $line );
					}
					\WP_CLI::halt( $out['exit'] );
				}
			);
		}
	}

	/**
	 * Testable core.
	 *
	 * @param string                $command Sub-command.
	 * @param array<int, string>    $args    Positional arguments: batch ID.
	 * @param array<string, string> $assoc   Options (`format`, `output`).
	 * @param Actor                 $actor   Actor.
	 * @return array{exit: int, lines: list<string>}
	 */
	public static function execute( string $command, array $args, array $assoc, Actor $actor ): array {
		$batch = (int) ( $args[0] ?? 0 );
		if ( $actor->user_id <= 0 || $batch <= 0 || ! in_array( $command, self::COMMANDS, true ) ) {
			return array(
				'exit'  => 1,
				'lines' => array( 'Usage: wp course-captain import ' . implode( '|', self::COMMANDS ) . ' <batch> --user=<id>' ),
			);
		}
		$runner = ImportRunner::current();
		try {
			switch ( $command ) {
				case 'run':
					$row = (array) ImportService::current()->get( $batch );
					if ( 'approved' === ( $row['status'] ?? '' ) ) {
						$runner->start( $actor, $batch, false );
					} elseif ( 'aborted' === ( $row['status'] ?? '' ) ) {
						$runner->resume( $actor, $batch, false );
					}
					$out = $runner->step( $actor, $batch, null );
					return array(
						'exit'  => 'published' === $out['status'] ? 0 : 1,
						'lines' => array( (string) wp_json_encode( $out ) ),
					);
				case 'cancel':
					$out = $runner->cancel( $actor, $batch );
					break;
				case 'report':
					$format = (string) ( $assoc['format'] ?? 'csv' );
					$report = ImportReport::export( $actor, $batch, $format, array(), 0, ImportReport::EXPORT_BLOCK );
					$text   = is_string( $report ) ? $report : (string) wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
					if ( isset( $assoc['output'] ) && '' !== $assoc['output'] ) {
						$written = file_put_contents( (string) $assoc['output'], $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- CLI writes the file the operator named.
						return array(
							'exit'  => false === $written ? 1 : 0,
							'lines' => array( false === $written ? 'Report could not be written.' : 'Report written.' ),
						);
					}
					return array(
						'exit'  => 0,
						'lines' => array( $text ),
					);
				default:
					$out = $runner->progress( $actor, $batch );
			}
		} catch ( CourseException $e ) {
			return array(
				'exit'  => 1,
				'lines' => array( $e->error_code . ': ' . $e->getMessage() ),
			);
		}
		return array(
			'exit'  => 0,
			'lines' => array( (string) wp_json_encode( $out ) ),
		);
	}
}
