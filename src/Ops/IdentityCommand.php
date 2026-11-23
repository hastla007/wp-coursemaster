<?php
/**
 * WP-CLI `wp course-captain identity` (R0-19 §4.7, R0-16 §2.3).
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Ops;

use CourseCaptain\Course\CourseException;
use CourseCaptain\Kernel\SiteIdentity;
use CourseCaptain\Policy\Actor;

defined( 'ABSPATH' ) || exit;

/**
 * `identity [status] [--format=json]` shows the hold state of the current
 * site (`--url`): exit 0 for `ok`, `unrecorded` and `copy`, 1 while held.
 * `identity confirm <same_place|moved|copy>` confirms like the admin notice.
 * Not yet: `--new` (new installation UUID; the UUID is not used in R1).
 */
final class IdentityCommand {

	/**
	 * Register the command when WP-CLI is loaded.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command( 'course-captain identity', array( self::class, 'invoke' ) );
		}
	}

	/**
	 * WP-CLI entry point.
	 *
	 * ## OPTIONS
	 *
	 * [<action>]
	 * : status (default) or confirm.
	 *
	 * [<choice>]
	 * : For confirm: same_place, moved or copy.
	 *
	 * [--format=<format>]
	 * : Output format: text or json.
	 *
	 * @param array<int, string>    $args  Positional arguments.
	 * @param array<string, string> $assoc Options.
	 * @return void
	 */
	public static function invoke( array $args, array $assoc ): void {
		$out = self::execute( $args, $assoc );
		foreach ( $out['lines'] as $line ) {
			\WP_CLI::line( $line );
		}
		\WP_CLI::halt( $out['exit'] );
	}

	/**
	 * Testable core of the command.
	 *
	 * @param array<int, string>    $args  Positional arguments.
	 * @param array<string, string> $assoc Options.
	 * @return array{exit: int, state: string, lines: list<string>}
	 */
	public static function execute( array $args, array $assoc ): array {
		$action = (string) ( $args[0] ?? 'status' );
		if ( 'confirm' === $action ) {
			try {
				$state = SiteIdentity::confirm( Actor::cli(), (string) ( $args[1] ?? '' ) );
			} catch ( CourseException $e ) {
				return array(
					'exit'  => 1,
					'state' => 'error',
					'lines' => array( 'Error: ' . $e->error_code . ' (choose same_place, moved or copy)' ),
				);
			}
			return self::output( $state, $assoc );
		}
		if ( 'status' !== $action ) {
			return array(
				'exit'  => 1,
				'state' => 'error',
				'lines' => array( 'Error: unknown action ' . $action ),
			);
		}
		return self::output( SiteIdentity::check(), $assoc );
	}

	/**
	 * Output of a state.
	 *
	 * @param array{state: string, held: bool, changed: list<string>, detected_at: int} $state State.
	 * @param array<string, string>                                                     $assoc Options.
	 * @return array{exit: int, state: string, lines: list<string>}
	 */
	private static function output( array $state, array $assoc ): array {
		if ( 'json' === ( $assoc['format'] ?? 'text' ) ) {
			$lines = array( (string) wp_json_encode( $state ) );
		} else {
			$lines = array( 'State: ' . $state['state'] );
			if ( array() !== $state['changed'] ) {
				$lines[] = 'Changed: ' . implode( ', ', $state['changed'] );
			}
			if ( $state['detected_at'] > 0 ) {
				$lines[] = 'Detected: ' . gmdate( 'Y-m-d H:i:s', $state['detected_at'] ) . ' UTC';
			}
		}
		return array(
			'exit'  => SiteIdentity::STATUS_HELD === $state['state'] ? 1 : 0,
			'state' => $state['state'],
			'lines' => $lines,
		);
	}
}
