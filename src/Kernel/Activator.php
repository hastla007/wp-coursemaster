<?php
/**
 * Activation of the plugin on a single site.
 *
 * @package CourseCaptain
 */

declare(strict_types=1);

namespace CourseCaptain\Kernel;

use CourseCaptain\Infrastructure\Migration\MigrationRunner;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Order matters: every check runs before anything is written (MS-R-01,
 * R0-20 §1.2), and the migration runs before capabilities, cron and rewrite
 * rules (R0-37 T-1).
 */
final class Activator {

	/**
	 * Constructor.
	 *
	 * @param MigrationRunner $migrations Schema upgrade trigger point.
	 */
	public function __construct( private MigrationRunner $migrations ) {
	}

	/**
	 * Activate on the current site.
	 *
	 * @param bool $network_wide Whether WordPress requested a network activation.
	 * @return void
	 */
	public function activate( bool $network_wide ): void {
		if ( NetworkGuard::must_block_activation( $network_wide ) ) {
			self::abort( array( NetworkGuard::blocked_message() ) );
		}

		$failures = Requirements::evaluate( EnvironmentSnapshot::with_database() );
		if ( array() !== $failures ) {
			$messages = array();
			foreach ( $failures as $failure ) {
				$messages[] = $failure->message();
			}
			self::abort( $messages );
		}

		// The activation error page re-runs this hook ("sandbox scraping"). It
		// may report errors but must never write anything.
		if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
			return;
		}

		try {
			$this->migrations->run_on_activation();
		} catch ( Throwable $e ) {
			self::abort( array( __( 'The database could not be prepared. Nothing else has been changed; please check the system status and try again.', 'course-captain' ) ) );
		}

		Capabilities::grant_activation_defaults();
		CronHooks::schedule_recurring( time() );
		// Rewrite rules are flushed once on the next request, after all rules are registered.
		delete_option( Plugin::REWRITE_VERSION_OPTION );
	}

	/**
	 * Abort the activation with a readable message. WP-CLI turns this into exit code 1.
	 *
	 * @param list<string> $messages Translated messages.
	 * @return never
	 */
	private static function abort( array $messages ): never {
		$items = '';
		foreach ( $messages as $message ) {
			$items .= '<li>' . esc_html( $message ) . '</li>';
		}

		wp_die(
			'<p>' . esc_html__( 'Course Captain cannot be activated:', 'course-captain' ) . '</p><ul>' . $items . '</ul>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part is escaped above.
			esc_html__( 'Course Captain cannot be activated', 'course-captain' ),
			array(
				'response'  => 500,
				'back_link' => true,
			)
		);
		exit; // wp_die() handlers in tests may return; never continue the activation.
	}
}
